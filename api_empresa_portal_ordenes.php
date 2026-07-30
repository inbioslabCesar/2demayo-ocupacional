<?php
require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_ocupacional.php';

function out_empresa_portal($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bind_dynamic_empresa_portal($stmt, $types, $params)
{
    if ($types === '' || empty($params)) {
        return;
    }
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function is_valid_date_empresa_portal($date)
{
    if (!is_string($date) || trim($date) === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function calculate_age_empresa_portal($birthDate)
{
    if (!is_string($birthDate) || trim($birthDate) === '') {
        return null;
    }
    try {
        $birth = new DateTime($birthDate);
        $today = new DateTime('now', new DateTimeZone('America/Lima'));
        return (int)$birth->diff($today)->y;
    } catch (Throwable $e) {
        return null;
    }
}

function require_empresa_portal_session()
{
    $empresa = $_SESSION['empresa_portal'] ?? null;
    if (!is_array($empresa) || (int)($empresa['empresa_id'] ?? 0) <= 0) {
        out_empresa_portal(401, ['success' => false, 'error' => 'No autenticado como empresa']);
    }
    return $empresa;
}

function column_exists_empresa_portal($conn, $table, $column)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function registrar_evento_descarga_empresa_portal($mysqliOcup, $ordenId, $empresaSesion)
{
    $payload = json_encode([
        'email_login' => (string)($empresaSesion['email_login'] ?? ''),
        'fuente' => 'portal_empresa',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $descripcion = 'Descarga de certificado desde portal empresa';

    $hasActorTipo = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'actor_tipo');
    $hasActorId = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'actor_id');
    $hasActorNombre = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'actor_nombre');
    $hasDescripcion = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'descripcion');
    $hasPayload = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'payload');
    $hasPayloadJson = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'payload_json');
    $hasCreatedBy = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'created_by');
    $hasCreatedAt = column_exists_empresa_portal($mysqliOcup, 'ocupacional_orden_eventos', 'created_at');

    $columns = ['orden_id', 'tipo_evento'];
    $valuesSql = ['?', '"certificado_descargado_empresa"'];
    $types = 'i';
    $params = [(int)$ordenId];

    if ($hasActorTipo) {
        $columns[] = 'actor_tipo';
        $valuesSql[] = '"empresa"';
    }
    if ($hasActorId) {
        $columns[] = 'actor_id';
        $valuesSql[] = '?';
        $types .= 'i';
        $params[] = (int)($empresaSesion['usuario_portal_id'] ?? 0);
    }
    if ($hasActorNombre) {
        $columns[] = 'actor_nombre';
        $valuesSql[] = '?';
        $types .= 's';
        $params[] = (string)($empresaSesion['nombre_empresa'] ?? 'Empresa');
    }
    if ($hasDescripcion) {
        $columns[] = 'descripcion';
        $valuesSql[] = '?';
        $types .= 's';
        $params[] = $descripcion;
    }
    if ($hasPayloadJson) {
        $columns[] = 'payload_json';
        $valuesSql[] = '?';
        $types .= 's';
        $params[] = $payload;
    }
    if ($hasPayload) {
        $columns[] = 'payload';
        $valuesSql[] = '?';
        $types .= 's';
        $params[] = $payload;
    }
    if ($hasCreatedBy) {
        $columns[] = 'created_by';
        $valuesSql[] = '?';
        $types .= 'i';
        $params[] = (int)($empresaSesion['usuario_portal_id'] ?? 0);
    }
    if ($hasCreatedAt) {
        $columns[] = 'created_at';
        $valuesSql[] = 'NOW()';
    }

    $sql = 'INSERT INTO ocupacional_orden_eventos (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $valuesSql) . ')';
    $stmtEvt = $mysqliOcup->prepare($sql);
    if (!$stmtEvt) {
        return;
    }
    bind_dynamic_empresa_portal($stmtEvt, $types, $params);
    try {
        $stmtEvt->execute();
    } catch (Throwable $e) {
        // No bloquear la descarga del certificado por fallos de auditoria.
    }
    $stmtEvt->close();
}

$empresaSesion = require_empresa_portal_session();
$empresaIdSesion = (int)($empresaSesion['empresa_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    out_empresa_portal(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

$accion = trim((string)($_GET['accion'] ?? 'listar'));

if ($accion === 'listar') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $q = trim((string)($_GET['q'] ?? ''));
    $estado = trim((string)($_GET['estado'] ?? ''));
    $fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
    $fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
    $soloAprobados = (string)($_GET['solo_aprobados'] ?? '1') === '1';

    $estadosValidos = ['emitida', 'en_proceso', 'completada', 'cerrada', 'anulada'];
    if ($estado !== '' && !in_array($estado, $estadosValidos, true)) {
        out_empresa_portal(422, ['success' => false, 'error' => 'estado invalido']);
    }
    if ($fechaDesde !== '' && !is_valid_date_empresa_portal($fechaDesde)) {
        out_empresa_portal(422, ['success' => false, 'error' => 'fecha_desde invalida. Use YYYY-MM-DD']);
    }
    if ($fechaHasta !== '' && !is_valid_date_empresa_portal($fechaHasta)) {
        out_empresa_portal(422, ['success' => false, 'error' => 'fecha_hasta invalida. Use YYYY-MM-DD']);
    }

    $where = ['o.empresa_id = ?'];
    $types = 'i';
    $params = [$empresaIdSesion];

    if ($soloAprobados) {
        $where[] = 'o.aptitud_final IN ("APTO", "APTO_CON_RESTRICCIONES")';
    }
    if ($q !== '') {
        $where[] = '(o.codigo LIKE ? OR t.documento_numero LIKE ? OR te.nombre LIKE ? OR p.descripcion LIKE ?)';
        $term = '%' . $q . '%';
        $types .= 'ssss';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }
    if ($estado !== '') {
        $where[] = 'o.estado = ?';
        $types .= 's';
        $params[] = $estado;
    }
    if ($fechaDesde !== '') {
        $where[] = 'o.fecha_orden >= ?';
        $types .= 's';
        $params[] = $fechaDesde;
    }
    if ($fechaHasta !== '') {
        $where[] = 'o.fecha_orden <= ?';
        $types .= 's';
        $params[] = $fechaHasta;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $sqlCount = 'SELECT COUNT(*) AS total
                 FROM ocupacional_ordenes o
                 INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                 INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                 INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id'
                 . $whereSql;
    $stmtCount = $mysqliOcup->prepare($sqlCount);
    if (!$stmtCount) {
        out_empresa_portal(500, ['success' => false, 'error' => 'No se pudo preparar conteo']);
    }
    bind_dynamic_empresa_portal($stmtCount, $types, $params);
    $stmtCount->execute();
    $total = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtCount->close();

    $sqlRows = 'SELECT
                    o.id,
                    o.codigo,
                    o.fecha_orden,
                    o.estado,
                    o.aptitud_final,
                    o.restriccion_final,
                    o.recomendacion_final,
                    o.monto_total,
                    t.documento_numero,
                    t.puesto_trabajo,
                    t.external_patient_id,
                    p.descripcion AS protocolo_descripcion,
                    te.codigo AS tipo_codigo,
                    te.nombre AS tipo_nombre,
                    COALESCE(ce.total_certificados, 0) AS total_certificados,
                    ce.ultimo_certificado_at
                FROM ocupacional_ordenes o
                INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id
                LEFT JOIN (
                    SELECT orden_id,
                           COUNT(*) AS total_certificados,
                           MAX(created_at) AS ultimo_certificado_at
                    FROM ocupacional_orden_eventos
                    WHERE tipo_evento = "certificado_emitido"
                    GROUP BY orden_id
                ) ce ON ce.orden_id = o.id'
                . $whereSql
                . ' ORDER BY o.fecha_orden DESC, o.id DESC LIMIT ? OFFSET ?';

    $stmtRows = $mysqliOcup->prepare($sqlRows);
    if (!$stmtRows) {
        out_empresa_portal(500, ['success' => false, 'error' => 'No se pudo listar ordenes']);
    }
    $typesRows = $types . 'ii';
    $paramsRows = $params;
    $paramsRows[] = $perPage;
    $paramsRows[] = $offset;
    bind_dynamic_empresa_portal($stmtRows, $typesRows, $paramsRows);
    $stmtRows->execute();
    $resRows = $stmtRows->get_result();

    $rows = [];
    $pacienteIds = [];
    while ($r = $resRows->fetch_assoc()) {
        $externalPatientId = (int)($r['external_patient_id'] ?? 0);
        if ($externalPatientId > 0) {
            $pacienteIds[$externalPatientId] = true;
        }
        $rows[] = [
            'id' => (int)$r['id'],
            'codigo' => (string)($r['codigo'] ?? ''),
            'fecha_orden' => (string)($r['fecha_orden'] ?? ''),
            'estado' => (string)($r['estado'] ?? ''),
            'aptitud_final' => (string)($r['aptitud_final'] ?? ''),
            'restriccion_final' => (string)($r['restriccion_final'] ?? ''),
            'recomendacion_final' => (string)($r['recomendacion_final'] ?? ''),
            'monto_total' => number_format((float)($r['monto_total'] ?? 0), 2, '.', ''),
            'documento_numero' => (string)($r['documento_numero'] ?? ''),
            'puesto_trabajo' => (string)($r['puesto_trabajo'] ?? ''),
            'protocolo_descripcion' => (string)($r['protocolo_descripcion'] ?? ''),
            'tipo_codigo' => (string)($r['tipo_codigo'] ?? ''),
            'tipo_nombre' => (string)($r['tipo_nombre'] ?? ''),
            'certificado_emitido' => ((int)($r['total_certificados'] ?? 0)) > 0,
            'certificado_emitido_at' => (string)($r['ultimo_certificado_at'] ?? ''),
            '_external_patient_id' => $externalPatientId,
        ];
    }
    $stmtRows->close();

    $pacienteNombreById = [];
    $ids = array_keys($pacienteIds);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtPac = $mysqli->prepare('SELECT id, nombre, apellido FROM pacientes WHERE id IN (' . $placeholders . ')');
        if ($stmtPac) {
            bind_dynamic_empresa_portal($stmtPac, str_repeat('i', count($ids)), $ids);
            $stmtPac->execute();
            $resPac = $stmtPac->get_result();
            while ($p = $resPac->fetch_assoc()) {
                $pacienteNombreById[(int)$p['id']] = trim((string)($p['nombre'] ?? '') . ' ' . (string)($p['apellido'] ?? ''));
            }
            $stmtPac->close();
        }
    }

    foreach ($rows as &$row) {
        $externalId = (int)($row['_external_patient_id'] ?? 0);
        $row['paciente_nombre_completo'] = (string)($pacienteNombreById[$externalId] ?? '');
        unset($row['_external_patient_id']);
    }
    unset($row);

    out_empresa_portal(200, [
        'success' => true,
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
        ],
    ]);
}

if ($accion === 'certificado_data') {
    $ordenId = (int)($_GET['id'] ?? 0);
    if ($ordenId <= 0) {
        out_empresa_portal(422, ['success' => false, 'error' => 'id de orden es obligatorio']);
    }

    $stmtCab = $mysqliOcup->prepare('SELECT
        o.id,
        o.empresa_id,
        o.codigo,
        o.fecha_orden,
        o.estado,
        o.aptitud_final,
        o.restriccion_final,
        o.recomendacion_final,
        o.medico_responsable_id,
        o.medico_nombre_snapshot,
        o.medico_especialidad_snapshot,
        o.medico_cmp_snapshot,
        o.medico_rne_snapshot,
        o.medico_rna_snapshot,
        o.medico_firma_snapshot,
        e.razon_social AS empresa,
        t.external_patient_id,
        t.documento_numero,
        t.puesto_trabajo,
        p.descripcion AS protocolo_descripcion,
        te.codigo AS tipo_codigo,
        te.nombre AS tipo_nombre
    FROM ocupacional_ordenes o
    INNER JOIN empresas_ocupacionales e ON e.id = o.empresa_id
    INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
    INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
    INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id
    WHERE o.id = ? AND o.empresa_id = ?
    LIMIT 1');
    if (!$stmtCab) {
        out_empresa_portal(500, ['success' => false, 'error' => 'No se pudo consultar orden']);
    }
    $stmtCab->bind_param('ii', $ordenId, $empresaIdSesion);
    $stmtCab->execute();
    $cab = $stmtCab->get_result()->fetch_assoc();
    $stmtCab->close();

    if (!$cab) {
        out_empresa_portal(404, ['success' => false, 'error' => 'Orden no encontrada']);
    }
    if (trim((string)($cab['aptitud_final'] ?? '')) === '') {
        out_empresa_portal(409, ['success' => false, 'error' => 'La orden aun no tiene aptitud final']);
    }

    $externalPatientId = (int)($cab['external_patient_id'] ?? 0);
    $paciente = [
        'nombre_completo' => '',
        'historia_clinica' => '',
        'sexo' => '',
        'fecha_nacimiento' => '',
        'edad' => null,
    ];

    if ($externalPatientId > 0) {
        $stmtPac = $mysqli->prepare('SELECT nombre, apellido, historia_clinica, sexo, fecha_nacimiento FROM pacientes WHERE id = ? LIMIT 1');
        if ($stmtPac) {
            $stmtPac->bind_param('i', $externalPatientId);
            $stmtPac->execute();
            $rowPac = $stmtPac->get_result()->fetch_assoc();
            $stmtPac->close();
            if ($rowPac) {
                $paciente['nombre_completo'] = trim((string)($rowPac['nombre'] ?? '') . ' ' . (string)($rowPac['apellido'] ?? ''));
                $paciente['historia_clinica'] = trim((string)($rowPac['historia_clinica'] ?? ''));
                $paciente['sexo'] = trim((string)($rowPac['sexo'] ?? ''));
                $paciente['fecha_nacimiento'] = trim((string)($rowPac['fecha_nacimiento'] ?? ''));
                $paciente['edad'] = calculate_age_empresa_portal($paciente['fecha_nacimiento']);
            }
        }
    }

    $medico = [
        'nombre' => trim((string)($cab['medico_nombre_snapshot'] ?? '')),
        'especialidad' => trim((string)($cab['medico_especialidad_snapshot'] ?? '')),
        'cmp' => trim((string)($cab['medico_cmp_snapshot'] ?? '')),
        'rne' => trim((string)($cab['medico_rne_snapshot'] ?? '')),
        'rna' => trim((string)($cab['medico_rna_snapshot'] ?? '')),
        'firma' => trim((string)($cab['medico_firma_snapshot'] ?? '')),
    ];

    $medicoResponsableId = (int)($cab['medico_responsable_id'] ?? 0);
    if ($medicoResponsableId > 0) {
        $stmtMedico = $mysqli->prepare('SELECT nombre, apellido, especialidad, cmp, rne, rna, firma FROM medicos WHERE id = ? LIMIT 1');
        if ($stmtMedico) {
            $stmtMedico->bind_param('i', $medicoResponsableId);
            $stmtMedico->execute();
            $rowMedico = $stmtMedico->get_result()->fetch_assoc();
            $stmtMedico->close();
            if ($rowMedico) {
                if ($medico['nombre'] === '') {
                    $medico['nombre'] = trim((string)($rowMedico['nombre'] ?? '') . ' ' . (string)($rowMedico['apellido'] ?? ''));
                }
                if ($medico['especialidad'] === '') {
                    $medico['especialidad'] = trim((string)($rowMedico['especialidad'] ?? ''));
                }
                if ($medico['cmp'] === '') {
                    $medico['cmp'] = trim((string)($rowMedico['cmp'] ?? ''));
                }
                if ($medico['rne'] === '') {
                    $medico['rne'] = trim((string)($rowMedico['rne'] ?? ''));
                }
                if ($medico['rna'] === '') {
                    $medico['rna'] = trim((string)($rowMedico['rna'] ?? ''));
                }
                if ($medico['firma'] === '') {
                    $medico['firma'] = trim((string)($rowMedico['firma'] ?? ''));
                }
            }
        }
    }

    registrar_evento_descarga_empresa_portal($mysqliOcup, $ordenId, $empresaSesion);

    out_empresa_portal(200, [
        'success' => true,
        'data' => [
            'orden' => [
                'id' => (int)$cab['id'],
                'codigo' => (string)($cab['codigo'] ?? ''),
                'fecha_orden' => (string)($cab['fecha_orden'] ?? ''),
                'estado' => (string)($cab['estado'] ?? ''),
                'empresa' => (string)($cab['empresa'] ?? ''),
                'tipo_codigo' => (string)($cab['tipo_codigo'] ?? ''),
                'tipo_nombre' => (string)($cab['tipo_nombre'] ?? ''),
                'protocolo_descripcion' => (string)($cab['protocolo_descripcion'] ?? ''),
                'documento_numero' => (string)($cab['documento_numero'] ?? ''),
                'puesto_trabajo' => (string)($cab['puesto_trabajo'] ?? ''),
                'aptitud_final' => (string)($cab['aptitud_final'] ?? ''),
                'restriccion_final' => (string)($cab['restriccion_final'] ?? ''),
                'recomendacion_final' => (string)($cab['recomendacion_final'] ?? ''),
            ],
            'paciente' => $paciente,
            'medico' => [
                'nombre' => (string)($medico['nombre'] ?? ''),
                'especialidad' => (string)($medico['especialidad'] ?? ''),
                'cmp' => (string)($medico['cmp'] ?? ''),
                'rne' => (string)($medico['rne'] ?? ''),
                'rna' => (string)($medico['rna'] ?? ''),
                'firma' => (string)($medico['firma'] ?? ''),
            ],
        ],
    ]);
}

out_empresa_portal(422, ['success' => false, 'error' => 'accion no soportada']);
