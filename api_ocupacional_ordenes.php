<?php

require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_ocupacional.php';

function out_orden($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function bind_params_dynamic_orden($stmt, $types, $params)
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

function parse_session_permisos_orden()
{
    $raw = $_SESSION['usuario']['permisos'] ?? [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }
    return array_values(array_filter(array_map('strval', $raw), fn($v) => trim($v) !== ''));
}

function require_ocup_permiso_orden($permiso)
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out_orden(401, ['success' => false, 'error' => 'No autenticado']);
    }

    $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }

    $permisos = parse_session_permisos_orden();
    if (!in_array('access_salud_ocupacional', $permisos, true) || !in_array($permiso, $permisos, true)) {
        out_orden(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
    }
}

function require_ocup_access_orden()
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out_orden(401, ['success' => false, 'error' => 'No autenticado']);
    }

    $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }

    $permisos = parse_session_permisos_orden();
    if (!in_array('access_salud_ocupacional', $permisos, true)) {
        out_orden(403, ['success' => false, 'error' => 'No autorizado para salud ocupacional']);
    }
}

function require_ocup_permiso_any_orden($permisosValidos, $fallback = 'registrar_trabajadores_ocupacional')
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out_orden(401, ['success' => false, 'error' => 'No autenticado']);
    }

    $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }

    $permisos = parse_session_permisos_orden();
    if (!in_array('access_salud_ocupacional', $permisos, true)) {
        out_orden(403, ['success' => false, 'error' => 'No autorizado para salud ocupacional']);
    }

    $lista = is_array($permisosValidos) ? $permisosValidos : [$permisosValidos];
    if ($fallback !== '') {
        $lista[] = $fallback;
    }

    foreach ($lista as $perm) {
        $p = trim((string)$perm);
        if ($p !== '' && in_array($p, $permisos, true)) {
            return;
        }
    }

    out_orden(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
}

function table_exists_orden($conn, $table)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function normalize_text_orden($value)
{
    return strtoupper(trim((string)$value));
}

function calculate_age_orden($fechaNacimiento)
{
    $fn = trim((string)$fechaNacimiento);
    if ($fn === '') {
        return null;
    }
    $dob = DateTime::createFromFormat('Y-m-d', $fn);
    if (!$dob || $dob->format('Y-m-d') !== $fn) {
        return null;
    }
    $today = new DateTime('now', new DateTimeZone('America/Lima'));
    return (int)$today->diff($dob)->y;
}

function is_valid_date_orden($value)
{
    $v = trim((string)$value);
    if ($v === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}

function registrar_evento_orden($mysqliOcup, $ordenId, $tipo, $descripcion, $usuarioId, $payload = null)
{
    $payloadJson = null;
    if (is_array($payload) && !empty($payload)) {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    $stmt = $mysqliOcup->prepare('INSERT INTO ocupacional_orden_eventos
                                  (orden_id, tipo_evento, descripcion, payload_json, created_by)
                                  VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isssi', $ordenId, $tipo, $descripcion, $payloadJson, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

function sync_estado_orden_por_detalle($mysqliOcup, $ordenId, $usuarioId)
{
    $stmt = $mysqliOcup->prepare('SELECT
                                    COUNT(*) AS total,
                                    SUM(CASE WHEN estado_ejecucion = "pendiente" THEN 1 ELSE 0 END) AS pendientes,
                                    SUM(CASE WHEN estado_ejecucion = "en_proceso" THEN 1 ELSE 0 END) AS en_proceso,
                                    SUM(CASE WHEN estado_ejecucion IN ("realizado", "observado") THEN 1 ELSE 0 END) AS completados
                                  FROM ocupacional_orden_detalle
                                  WHERE orden_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $agg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int)($agg['total'] ?? 0);
    $pendientes = (int)($agg['pendientes'] ?? 0);
    $enProceso = (int)($agg['en_proceso'] ?? 0);
    $completados = (int)($agg['completados'] ?? 0);

    $nuevoEstado = 'emitida';
    if ($total > 0 && $completados >= $total) {
        $nuevoEstado = 'completada';
    } elseif (($total - $pendientes) > 0 || $enProceso > 0) {
        $nuevoEstado = 'en_proceso';
    }

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_ordenes
                                    SET estado = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? AND estado NOT IN ("anulada", "cerrada") LIMIT 1');
    if (!$stmtUp) {
        return;
    }
    $stmtUp->bind_param('sii', $nuevoEstado, $usuarioId, $ordenId);
    $stmtUp->execute();
    $stmtUp->close();
}

function resolve_examenes_orden($mysqliOcup, $mysqliCore, $empresaId, $trabajadorId, $protocoloId, $tipoEvaluacionId)
{
    $stmtTrab = $mysqliOcup->prepare('SELECT p.id, p.empresa_id, p.external_patient_id, p.puesto_trabajo, p.estado_laboral, p.documento_numero, e.razon_social
                                      FROM pacientes_ocupacionales p
                                      INNER JOIN empresas_ocupacionales e ON e.id = p.empresa_id
                                      WHERE p.id = ? LIMIT 1');
    if (!$stmtTrab) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar trabajador']);
    }
    $stmtTrab->bind_param('i', $trabajadorId);
    $stmtTrab->execute();
    $trabajador = $stmtTrab->get_result()->fetch_assoc();
    $stmtTrab->close();

    if (!$trabajador) {
        out_orden(422, ['success' => false, 'error' => 'trabajador_id no encontrado']);
    }
    if ((int)$trabajador['empresa_id'] !== $empresaId) {
        out_orden(422, ['success' => false, 'error' => 'El trabajador no pertenece a la empresa seleccionada']);
    }
    if ((string)$trabajador['estado_laboral'] !== 'activo') {
        out_orden(422, ['success' => false, 'error' => 'El trabajador debe estar activo para generar orden']);
    }

    $stmtProt = $mysqliOcup->prepare('SELECT id, descripcion, estado FROM ocupacional_protocolos_empresa WHERE id = ? AND empresa_id = ? LIMIT 1');
    if (!$stmtProt) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar protocolo']);
    }
    $stmtProt->bind_param('ii', $protocoloId, $empresaId);
    $stmtProt->execute();
    $protocolo = $stmtProt->get_result()->fetch_assoc();
    $stmtProt->close();
    if (!$protocolo) {
        out_orden(422, ['success' => false, 'error' => 'protocolo_id no corresponde a la empresa']);
    }
    if ((string)$protocolo['estado'] !== 'activo') {
        out_orden(422, ['success' => false, 'error' => 'El protocolo esta inactivo']);
    }

    $stmtTipo = $mysqliOcup->prepare('SELECT id, codigo, nombre, estado FROM ocupacional_tipos_evaluacion WHERE id = ? LIMIT 1');
    if (!$stmtTipo) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar tipo de evaluacion']);
    }
    $stmtTipo->bind_param('i', $tipoEvaluacionId);
    $stmtTipo->execute();
    $tipo = $stmtTipo->get_result()->fetch_assoc();
    $stmtTipo->close();
    if (!$tipo || (string)$tipo['estado'] !== 'activo') {
        out_orden(422, ['success' => false, 'error' => 'tipo_evaluacion_id invalido o inactivo']);
    }

    $externalPatientId = (int)($trabajador['external_patient_id'] ?? 0);
    $stmtPac = $mysqliCore->prepare('SELECT id, nombre, apellido, sexo, fecha_nacimiento FROM pacientes WHERE id = ? LIMIT 1');
    if (!$stmtPac) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo consultar identidad del paciente']);
    }
    $stmtPac->bind_param('i', $externalPatientId);
    $stmtPac->execute();
    $paciente = $stmtPac->get_result()->fetch_assoc();
    $stmtPac->close();
    if (!$paciente) {
        out_orden(422, ['success' => false, 'error' => 'No existe el paciente clinico relacionado']);
    }

    $sexoPaciente = normalize_text_orden($paciente['sexo'] ?? '');
    $sexoPaciente = $sexoPaciente === 'F' ? 'F' : ($sexoPaciente === 'M' ? 'M' : '');
    $edadPaciente = calculate_age_orden($paciente['fecha_nacimiento'] ?? '');
    $puestoTrabajador = normalize_text_orden($trabajador['puesto_trabajo'] ?? '');

    $stmtRows = $mysqliOcup->prepare('SELECT
                                        pd.catalogo_id,
                                        c.examen_id,
                                        e.codigo,
                                        e.descripcion,
                                        pd.monto
                                      FROM ocupacional_protocolo_detalle pd
                                      INNER JOIN ocupacional_catalogo_empresas c ON c.id = pd.catalogo_id
                                      INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
                                      WHERE pd.protocolo_id = ?
                                        AND pd.tipo_evaluacion_id = ?
                                        AND c.empresa_id = ?
                                        AND c.estado = "activo"
                                        AND e.estado = "activo"
                                      ORDER BY e.grupo ASC, e.subgrupo ASC, e.descripcion ASC, e.id DESC');
    if (!$stmtRows) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo consultar detalle del protocolo']);
    }
    $stmtRows->bind_param('iii', $protocoloId, $tipoEvaluacionId, $empresaId);
    $stmtRows->execute();
    $resRows = $stmtRows->get_result();

    $items = [];
    $catalogoIds = [];
    while ($row = $resRows->fetch_assoc()) {
        $catalogoId = (int)$row['catalogo_id'];
        $catalogoIds[] = $catalogoId;
        $items[$catalogoId] = [
            'catalogo_id' => $catalogoId,
            'examen_id' => (int)$row['examen_id'],
            'codigo' => (string)$row['codigo'],
            'descripcion' => (string)$row['descripcion'],
            'monto' => number_format((float)$row['monto'], 2, '.', ''),
            'aplica' => false,
            'motivo' => 'Sin evaluacion',
        ];
    }
    $stmtRows->close();

    if (empty($items)) {
        return [
            'trabajador' => $trabajador,
            'paciente' => $paciente,
            'protocolo' => $protocolo,
            'tipo' => $tipo,
            'items' => [],
            'total' => '0.00',
            'total_items_aplican' => 0,
        ];
    }

    $condicionesByCatalogo = [];
    $placeholders = implode(',', array_fill(0, count($catalogoIds), '?'));
    $typesCond = 'i' . str_repeat('i', count($catalogoIds));
    $paramsCond = array_merge([$protocoloId], $catalogoIds);
    $sqlCond = 'SELECT catalogo_id, puesto_trabajo, sexo, edad_min, edad_max
                FROM ocupacional_protocolo_condiciones
                WHERE protocolo_id = ? AND catalogo_id IN (' . $placeholders . ')';
    $stmtCond = $mysqliOcup->prepare($sqlCond);
    if ($stmtCond) {
        bind_params_dynamic_orden($stmtCond, $typesCond, $paramsCond);
        $stmtCond->execute();
        $resCond = $stmtCond->get_result();
        while ($c = $resCond->fetch_assoc()) {
            $cId = (int)$c['catalogo_id'];
            if (!isset($condicionesByCatalogo[$cId])) {
                $condicionesByCatalogo[$cId] = [];
            }
            $condicionesByCatalogo[$cId][] = [
                'puesto_trabajo' => normalize_text_orden($c['puesto_trabajo'] ?? ''),
                'sexo' => normalize_text_orden($c['sexo'] ?? ''),
                'edad_min' => isset($c['edad_min']) ? (int)$c['edad_min'] : null,
                'edad_max' => isset($c['edad_max']) ? (int)$c['edad_max'] : null,
            ];
        }
        $stmtCond->close();
    }

    $total = 0.0;
    $aplican = 0;
    foreach ($items as $catalogoId => &$item) {
        $condiciones = $condicionesByCatalogo[$catalogoId] ?? [];

        if (empty($condiciones)) {
            $item['aplica'] = true;
            $item['motivo'] = 'Sin condiciones';
            $total += (float)$item['monto'];
            $aplican++;
            continue;
        }

        $matchAny = false;
        foreach ($condiciones as $cond) {
            $ok = true;

            if ($cond['puesto_trabajo'] !== '' && $cond['puesto_trabajo'] !== $puestoTrabajador) {
                $ok = false;
            }
            if ($ok && $cond['sexo'] !== '' && $cond['sexo'] !== $sexoPaciente) {
                $ok = false;
            }
            if ($ok && $cond['edad_min'] !== null) {
                if ($edadPaciente === null || $edadPaciente < $cond['edad_min']) {
                    $ok = false;
                }
            }
            if ($ok && $cond['edad_max'] !== null) {
                if ($edadPaciente === null || $edadPaciente > $cond['edad_max']) {
                    $ok = false;
                }
            }

            if ($ok) {
                $matchAny = true;
                break;
            }
        }

        if ($matchAny) {
            $item['aplica'] = true;
            $item['motivo'] = 'Cumple condicion';
            $total += (float)$item['monto'];
            $aplican++;
        } else {
            $item['aplica'] = false;
            $item['motivo'] = 'No cumple condicion';
        }
    }
    unset($item);

    return [
        'trabajador' => $trabajador,
        'paciente' => $paciente,
        'protocolo' => $protocolo,
        'tipo' => $tipo,
        'items' => array_values($items),
        'total' => number_format($total, 2, '.', ''),
        'total_items_aplican' => $aplican,
    ];
}

$requiredTables = [
    'empresas_ocupacionales',
    'pacientes_ocupacionales',
    'ocupacional_tipos_evaluacion',
    'ocupacional_protocolos_empresa',
    'ocupacional_catalogo_empresas',
    'ocupacional_protocolo_detalle',
    'ocupacional_protocolo_condiciones',
    'ocupacional_ordenes',
    'ocupacional_orden_detalle',
    'ocupacional_orden_eventos',
];

foreach ($requiredTables as $table) {
    if (!table_exists_orden($mysqliOcup, $table)) {
        out_orden(500, [
            'success' => false,
            'error' => 'Falta la tabla ' . $table . '. Aplicar migraciones sql/2026-06-15_ocupacional_fase3_ordenes.sql y sql/2026-06-16_ocupacional_fase3_cierre_auditoria.sql en la base ocupacional.',
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_ocup_access_orden();

    $accion = trim((string)($_GET['accion'] ?? 'listar_ordenes'));

    if ($accion === 'previsualizar') {
        require_ocup_permiso_any_orden(['registrar_ordenes_ocupacional', 'ejecutar_ordenes_ocupacional']);
        $empresaId = (int)($_GET['empresa_id'] ?? 0);
        $trabajadorId = (int)($_GET['trabajador_id'] ?? 0);
        $protocoloId = (int)($_GET['protocolo_id'] ?? 0);
        $tipoEvaluacionId = (int)($_GET['tipo_evaluacion_id'] ?? 0);

        if ($empresaId <= 0 || $trabajadorId <= 0 || $protocoloId <= 0 || $tipoEvaluacionId <= 0) {
            out_orden(422, ['success' => false, 'error' => 'empresa_id, trabajador_id, protocolo_id y tipo_evaluacion_id son obligatorios']);
        }

        $resolved = resolve_examenes_orden($mysqliOcup, $mysqli, $empresaId, $trabajadorId, $protocoloId, $tipoEvaluacionId);

        out_orden(200, [
            'success' => true,
            'data' => [
                'trabajador' => [
                    'id' => (int)$resolved['trabajador']['id'],
                    'documento_numero' => (string)$resolved['trabajador']['documento_numero'],
                    'puesto_trabajo' => (string)$resolved['trabajador']['puesto_trabajo'],
                    'empresa' => (string)$resolved['trabajador']['razon_social'],
                ],
                'paciente' => [
                    'id' => (int)$resolved['paciente']['id'],
                    'nombre_completo' => trim((string)$resolved['paciente']['nombre'] . ' ' . (string)$resolved['paciente']['apellido']),
                    'sexo' => (string)($resolved['paciente']['sexo'] ?? ''),
                    'fecha_nacimiento' => (string)($resolved['paciente']['fecha_nacimiento'] ?? ''),
                    'edad' => calculate_age_orden($resolved['paciente']['fecha_nacimiento'] ?? ''),
                ],
                'protocolo' => [
                    'id' => (int)$resolved['protocolo']['id'],
                    'descripcion' => (string)$resolved['protocolo']['descripcion'],
                ],
                'tipo_evaluacion' => [
                    'id' => (int)$resolved['tipo']['id'],
                    'codigo' => (string)$resolved['tipo']['codigo'],
                    'nombre' => (string)$resolved['tipo']['nombre'],
                ],
                'items' => $resolved['items'],
                'total' => $resolved['total'],
                'total_items_aplican' => (int)$resolved['total_items_aplican'],
            ],
        ]);
    }

    if ($accion === 'listar_ordenes') {
        require_ocup_permiso_any_orden(['ver_ordenes_ocupacional', 'registrar_ordenes_ocupacional', 'ejecutar_ordenes_ocupacional']);
        $empresaId = (int)($_GET['empresa_id'] ?? 0);
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);
        $q = trim((string)($_GET['q'] ?? ''));
        $estado = trim((string)($_GET['estado'] ?? ''));
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));

        $estadosValidos = ['emitida', 'en_proceso', 'completada', 'cerrada', 'anulada'];
        if ($estado !== '' && !in_array($estado, $estadosValidos, true)) {
            out_orden(422, ['success' => false, 'error' => 'estado invalido']);
        }
        if ($fechaDesde !== '' && !is_valid_date_orden($fechaDesde)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_desde invalida. Formato esperado YYYY-MM-DD']);
        }
        if ($fechaHasta !== '' && !is_valid_date_orden($fechaHasta)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_hasta invalida. Formato esperado YYYY-MM-DD']);
        }

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $types = '';
        $params = [];

        if ($empresaId > 0) {
            $where[] = 'o.empresa_id = ?';
            $types .= 'i';
            $params[] = $empresaId;
        }
        if ($q !== '') {
            $where[] = '(o.codigo LIKE ? OR t.documento_numero LIKE ? OR p.descripcion LIKE ?)';
            $term = '%' . $q . '%';
            $types .= 'sss';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($estado !== '') {
            $where[] = 'o.estado = ?';
            $types .= 's';
            $params[] = $estado;
        }
        if ($tipo !== '') {
            $where[] = 'te.codigo = ?';
            $types .= 's';
            $params[] = $tipo;
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

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

        $sqlCount = 'SELECT COUNT(*) AS total
                     FROM ocupacional_ordenes o
                     INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                     INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                     INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id' . $whereSql;
        $stmtCount = $mysqliOcup->prepare($sqlCount);
        if (!$stmtCount) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo preparar conteo de ordenes']);
        }
        bind_params_dynamic_orden($stmtCount, $types, $params);
        $stmtCount->execute();
        $total = (int)($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtCount->close();

        $sqlRows = 'SELECT
                        o.id,
                        o.codigo,
                        o.fecha_orden,
                        o.estado,
                        o.monto_total,
                        o.aptitud_final,
                        e.razon_social,
                        t.documento_numero,
                        t.puesto_trabajo,
                        p.descripcion AS protocolo_descripcion,
                        te.codigo AS tipo_codigo,
                        COALESCE(d.total_items, 0) AS total_items,
                        COALESCE(d.total_completados, 0) AS total_completados
                    FROM ocupacional_ordenes o
                    INNER JOIN empresas_ocupacionales e ON e.id = o.empresa_id
                    INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                    INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                    INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id
                    LEFT JOIN (
                        SELECT
                            orden_id,
                            COUNT(*) AS total_items,
                            SUM(CASE WHEN estado_ejecucion IN ("realizado", "observado") THEN 1 ELSE 0 END) AS total_completados
                        FROM ocupacional_orden_detalle
                        GROUP BY orden_id
                    ) d ON d.orden_id = o.id'
                    . $whereSql
                    . ' ORDER BY o.id DESC LIMIT ? OFFSET ?';
        $stmtRows = $mysqliOcup->prepare($sqlRows);
        if (!$stmtRows) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo listar ordenes']);
        }
        $typesRows = $types . 'ii';
        $paramsRows = $params;
        $paramsRows[] = $perPage;
        $paramsRows[] = $offset;
        bind_params_dynamic_orden($stmtRows, $typesRows, $paramsRows);
        $stmtRows->execute();
        $resRows = $stmtRows->get_result();

        $rows = [];
        while ($r = $resRows->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'codigo' => (string)($r['codigo'] ?? ''),
                'fecha_orden' => (string)($r['fecha_orden'] ?? ''),
                'estado' => (string)($r['estado'] ?? ''),
                'monto_total' => number_format((float)($r['monto_total'] ?? 0), 2, '.', ''),
                'aptitud_final' => (string)($r['aptitud_final'] ?? ''),
                'empresa' => (string)($r['razon_social'] ?? ''),
                'documento_numero' => (string)($r['documento_numero'] ?? ''),
                'puesto_trabajo' => (string)($r['puesto_trabajo'] ?? ''),
                'protocolo_descripcion' => (string)($r['protocolo_descripcion'] ?? ''),
                'tipo_codigo' => (string)($r['tipo_codigo'] ?? ''),
                'total_items' => (int)($r['total_items'] ?? 0),
                'total_completados' => (int)($r['total_completados'] ?? 0),
            ];
        }
        $stmtRows->close();

        out_orden(200, [
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

    if ($accion === 'resumen_ordenes') {
        require_ocup_permiso_any_orden(['ver_ordenes_ocupacional']);
        $empresaId = (int)($_GET['empresa_id'] ?? 0);
        $estado = trim((string)($_GET['estado'] ?? ''));
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));

        $estadosValidos = ['emitida', 'en_proceso', 'completada', 'cerrada', 'anulada'];
        if ($estado !== '' && !in_array($estado, $estadosValidos, true)) {
            out_orden(422, ['success' => false, 'error' => 'estado invalido']);
        }
        if ($fechaDesde !== '' && !is_valid_date_orden($fechaDesde)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_desde invalida. Formato esperado YYYY-MM-DD']);
        }
        if ($fechaHasta !== '' && !is_valid_date_orden($fechaHasta)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_hasta invalida. Formato esperado YYYY-MM-DD']);
        }

        $where = [];
        $types = '';
        $params = [];
        if ($empresaId > 0) {
            $where[] = 'o.empresa_id = ?';
            $types .= 'i';
            $params[] = $empresaId;
        }
        if ($estado !== '') {
            $where[] = 'o.estado = ?';
            $types .= 's';
            $params[] = $estado;
        }
        if ($tipo !== '') {
            $where[] = 'te.codigo = ?';
            $types .= 's';
            $params[] = $tipo;
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
        if ($q !== '') {
            $where[] = '(o.codigo LIKE ? OR t.documento_numero LIKE ? OR p.descripcion LIKE ?)';
            $term = '%' . $q . '%';
            $types .= 'sss';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));
        $sql = 'SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(o.monto_total), 0) AS monto_total,
                    SUM(CASE WHEN o.estado = "emitida" THEN 1 ELSE 0 END) AS emitida,
                    SUM(CASE WHEN o.estado = "en_proceso" THEN 1 ELSE 0 END) AS en_proceso,
                    SUM(CASE WHEN o.estado = "completada" THEN 1 ELSE 0 END) AS completada,
                    SUM(CASE WHEN o.estado = "cerrada" THEN 1 ELSE 0 END) AS cerrada,
                    SUM(CASE WHEN o.estado = "anulada" THEN 1 ELSE 0 END) AS anulada
                FROM ocupacional_ordenes o
                INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id' . $whereSql;
        $stmt = $mysqliOcup->prepare($sql);
        if (!$stmt) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo calcular resumen de ordenes']);
        }
        bind_params_dynamic_orden($stmt, $types, $params);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        out_orden(200, [
            'success' => true,
            'data' => [
                'total' => (int)($r['total'] ?? 0),
                'monto_total' => number_format((float)($r['monto_total'] ?? 0), 2, '.', ''),
                'emitida' => (int)($r['emitida'] ?? 0),
                'en_proceso' => (int)($r['en_proceso'] ?? 0),
                'completada' => (int)($r['completada'] ?? 0),
                'cerrada' => (int)($r['cerrada'] ?? 0),
                'anulada' => (int)($r['anulada'] ?? 0),
            ],
        ]);
    }

    if ($accion === 'reporte_ordenes') {
        require_ocup_permiso_any_orden(['ver_ordenes_ocupacional']);
        $empresaId = (int)($_GET['empresa_id'] ?? 0);
        $estado = trim((string)($_GET['estado'] ?? ''));
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = (int)($_GET['limit'] ?? 2000);
        $limit = max(1, min($limit, 10000));

        $estadosValidos = ['emitida', 'en_proceso', 'completada', 'cerrada', 'anulada'];
        if ($estado !== '' && !in_array($estado, $estadosValidos, true)) {
            out_orden(422, ['success' => false, 'error' => 'estado invalido']);
        }
        if ($fechaDesde !== '' && !is_valid_date_orden($fechaDesde)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_desde invalida. Formato esperado YYYY-MM-DD']);
        }
        if ($fechaHasta !== '' && !is_valid_date_orden($fechaHasta)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_hasta invalida. Formato esperado YYYY-MM-DD']);
        }

        $where = [];
        $types = '';
        $params = [];
        if ($empresaId > 0) {
            $where[] = 'o.empresa_id = ?';
            $types .= 'i';
            $params[] = $empresaId;
        }
        if ($estado !== '') {
            $where[] = 'o.estado = ?';
            $types .= 's';
            $params[] = $estado;
        }
        if ($tipo !== '') {
            $where[] = 'te.codigo = ?';
            $types .= 's';
            $params[] = $tipo;
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
        if ($q !== '') {
            $where[] = '(o.codigo LIKE ? OR t.documento_numero LIKE ? OR p.descripcion LIKE ?)';
            $term = '%' . $q . '%';
            $types .= 'sss';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

        $sql = 'SELECT
                    o.id,
                    o.codigo,
                    o.fecha_orden,
                    o.estado,
                    o.monto_total,
                    o.aptitud_final,
                    e.razon_social,
                    t.documento_numero,
                    t.puesto_trabajo,
                    p.descripcion AS protocolo_descripcion,
                    te.codigo AS tipo_codigo,
                    COALESCE(d.total_items, 0) AS total_items,
                    COALESCE(d.total_completados, 0) AS total_completados
                FROM ocupacional_ordenes o
                INNER JOIN empresas_ocupacionales e ON e.id = o.empresa_id
                INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id
                LEFT JOIN (
                    SELECT
                        orden_id,
                        COUNT(*) AS total_items,
                        SUM(CASE WHEN estado_ejecucion IN ("realizado", "observado") THEN 1 ELSE 0 END) AS total_completados
                    FROM ocupacional_orden_detalle
                    GROUP BY orden_id
                ) d ON d.orden_id = o.id'
                . $whereSql
                . ' ORDER BY o.id DESC LIMIT ?';

        $stmt = $mysqliOcup->prepare($sql);
        if (!$stmt) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo generar reporte de ordenes']);
        }
        $types .= 'i';
        $params[] = $limit;
        bind_params_dynamic_orden($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'codigo' => (string)($r['codigo'] ?? ''),
                'fecha_orden' => (string)($r['fecha_orden'] ?? ''),
                'estado' => (string)($r['estado'] ?? ''),
                'monto_total' => number_format((float)($r['monto_total'] ?? 0), 2, '.', ''),
                'aptitud_final' => (string)($r['aptitud_final'] ?? ''),
                'empresa' => (string)($r['razon_social'] ?? ''),
                'documento_numero' => (string)($r['documento_numero'] ?? ''),
                'puesto_trabajo' => (string)($r['puesto_trabajo'] ?? ''),
                'protocolo_descripcion' => (string)($r['protocolo_descripcion'] ?? ''),
                'tipo_codigo' => (string)($r['tipo_codigo'] ?? ''),
                'total_items' => (int)($r['total_items'] ?? 0),
                'total_completados' => (int)($r['total_completados'] ?? 0),
            ];
        }
        $stmt->close();

        out_orden(200, [
            'success' => true,
            'data' => $rows,
        ]);
    }

    if ($accion === 'detalle_orden') {
        require_ocup_permiso_any_orden(['ver_ordenes_ocupacional', 'ejecutar_ordenes_ocupacional']);
        $ordenId = (int)($_GET['id'] ?? 0);
        if ($ordenId <= 0) {
            out_orden(422, ['success' => false, 'error' => 'id de orden es obligatorio']);
        }

        $stmtCab = $mysqliOcup->prepare('SELECT
                                            o.id,
                                            o.codigo,
                                            o.fecha_orden,
                                            o.estado,
                                            o.monto_total,
                                            o.observacion,
                                            o.aptitud_final,
                                            o.restriccion_final,
                                            o.recomendacion_final,
                                            o.medico_responsable,
                                            e.razon_social AS empresa,
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
                                         WHERE o.id = ? LIMIT 1');
        if (!$stmtCab) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo consultar cabecera de orden']);
        }
        $stmtCab->bind_param('i', $ordenId);
        $stmtCab->execute();
        $cab = $stmtCab->get_result()->fetch_assoc();
        $stmtCab->close();

        if (!$cab) {
            out_orden(404, ['success' => false, 'error' => 'Orden no encontrada']);
        }

        $stmtDet = $mysqliOcup->prepare('SELECT
                                            id,
                                            catalogo_id,
                                            examen_id,
                                            examen_codigo,
                                            examen_descripcion,
                                                          monto,
                                                          estado_ejecucion,
                                                          observacion_ejecucion,
                                                          fecha_ejecucion
                                         FROM ocupacional_orden_detalle
                                         WHERE orden_id = ?
                                         ORDER BY id ASC');
        if (!$stmtDet) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo consultar detalle de orden']);
        }
        $stmtDet->bind_param('i', $ordenId);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();

        $detalles = [];
        while ($d = $resDet->fetch_assoc()) {
            $detalles[] = [
                'id' => (int)$d['id'],
                'catalogo_id' => (int)$d['catalogo_id'],
                'examen_id' => (int)$d['examen_id'],
                'examen_codigo' => (string)($d['examen_codigo'] ?? ''),
                'examen_descripcion' => (string)($d['examen_descripcion'] ?? ''),
                'monto' => number_format((float)($d['monto'] ?? 0), 2, '.', ''),
                'estado_ejecucion' => (string)($d['estado_ejecucion'] ?? 'pendiente'),
                'observacion_ejecucion' => (string)($d['observacion_ejecucion'] ?? ''),
                'fecha_ejecucion' => (string)($d['fecha_ejecucion'] ?? ''),
            ];
        }
        $stmtDet->close();

        $totalItems = count($detalles);
        $totalCompletados = 0;
        foreach ($detalles as $itemDet) {
            if (in_array((string)$itemDet['estado_ejecucion'], ['realizado', 'observado'], true)) {
                $totalCompletados++;
            }
        }

        $stmtEvt = $mysqliOcup->prepare('SELECT
                                            id,
                                            tipo_evento,
                                            descripcion,
                                            payload_json,
                                            created_by,
                                            created_at
                                         FROM ocupacional_orden_eventos
                                         WHERE orden_id = ?
                                         ORDER BY id DESC
                                         LIMIT 50');
        $eventos = [];
        if ($stmtEvt) {
            $stmtEvt->bind_param('i', $ordenId);
            $stmtEvt->execute();
            $resEvt = $stmtEvt->get_result();
            while ($ev = $resEvt->fetch_assoc()) {
                $eventos[] = [
                    'id' => (int)$ev['id'],
                    'tipo_evento' => (string)($ev['tipo_evento'] ?? ''),
                    'descripcion' => (string)($ev['descripcion'] ?? ''),
                    'payload_json' => (string)($ev['payload_json'] ?? ''),
                    'created_by' => isset($ev['created_by']) ? (int)$ev['created_by'] : null,
                    'created_at' => (string)($ev['created_at'] ?? ''),
                ];
            }
            $stmtEvt->close();
        }

        out_orden(200, [
            'success' => true,
            'data' => [
                'id' => (int)$cab['id'],
                'codigo' => (string)($cab['codigo'] ?? ''),
                'fecha_orden' => (string)($cab['fecha_orden'] ?? ''),
                'estado' => (string)($cab['estado'] ?? ''),
                'monto_total' => number_format((float)($cab['monto_total'] ?? 0), 2, '.', ''),
                'observacion' => (string)($cab['observacion'] ?? ''),
                'aptitud_final' => (string)($cab['aptitud_final'] ?? ''),
                'restriccion_final' => (string)($cab['restriccion_final'] ?? ''),
                'recomendacion_final' => (string)($cab['recomendacion_final'] ?? ''),
                'medico_responsable' => (string)($cab['medico_responsable'] ?? ''),
                'empresa' => (string)($cab['empresa'] ?? ''),
                'documento_numero' => (string)($cab['documento_numero'] ?? ''),
                'puesto_trabajo' => (string)($cab['puesto_trabajo'] ?? ''),
                'protocolo_descripcion' => (string)($cab['protocolo_descripcion'] ?? ''),
                'tipo_codigo' => (string)($cab['tipo_codigo'] ?? ''),
                'tipo_nombre' => (string)($cab['tipo_nombre'] ?? ''),
                'total_items' => $totalItems,
                'total_completados' => $totalCompletados,
                'items' => $detalles,
                'eventos' => $eventos,
            ],
        ]);
    }

    if ($accion === 'eventos_orden') {
        require_ocup_permiso_any_orden(['ver_auditoria_ordenes_ocupacional', 'ver_ordenes_ocupacional']);
        $ordenId = (int)($_GET['id'] ?? 0);
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = max(1, min($limit, 500));

        if ($ordenId <= 0) {
            out_orden(422, ['success' => false, 'error' => 'id de orden es obligatorio']);
        }

        if ($fechaDesde !== '' && !is_valid_date_orden($fechaDesde)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_desde invalida. Formato esperado YYYY-MM-DD']);
        }
        if ($fechaHasta !== '' && !is_valid_date_orden($fechaHasta)) {
            out_orden(422, ['success' => false, 'error' => 'fecha_hasta invalida. Formato esperado YYYY-MM-DD']);
        }

        $where = ['orden_id = ?'];
        $types = 'i';
        $params = [$ordenId];

        if ($tipo !== '') {
            $where[] = 'tipo_evento = ?';
            $types .= 's';
            $params[] = $tipo;
        }
        if ($fechaDesde !== '') {
            $where[] = 'DATE(created_at) >= ?';
            $types .= 's';
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $where[] = 'DATE(created_at) <= ?';
            $types .= 's';
            $params[] = $fechaHasta;
        }

        $sql = 'SELECT id, tipo_evento, descripcion, payload_json, created_by, created_at
                FROM ocupacional_orden_eventos
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY id DESC
                LIMIT ?';
        $stmt = $mysqliOcup->prepare($sql);
        if (!$stmt) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo listar eventos de orden']);
        }

        $types .= 'i';
        $params[] = $limit;
        bind_params_dynamic_orden($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($ev = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$ev['id'],
                'tipo_evento' => (string)($ev['tipo_evento'] ?? ''),
                'descripcion' => (string)($ev['descripcion'] ?? ''),
                'payload_json' => (string)($ev['payload_json'] ?? ''),
                'created_by' => isset($ev['created_by']) ? (int)$ev['created_by'] : null,
                'created_at' => (string)($ev['created_at'] ?? ''),
            ];
        }
        $stmt->close();

        out_orden(200, [
            'success' => true,
            'data' => $rows,
        ]);
    }

    out_orden(422, ['success' => false, 'error' => 'accion GET no soportada']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_orden(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

require_ocup_access_orden();

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$accion = trim((string)($payload['accion'] ?? ''));
$usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;

if ($accion === 'registrar_orden') {
    require_ocup_permiso_any_orden(['registrar_ordenes_ocupacional']);
    $empresaId = (int)($payload['empresa_id'] ?? 0);
    $trabajadorId = (int)($payload['trabajador_id'] ?? 0);
    $protocoloId = (int)($payload['protocolo_id'] ?? 0);
    $tipoEvaluacionId = (int)($payload['tipo_evaluacion_id'] ?? 0);
    $fechaOrden = trim((string)($payload['fecha_orden'] ?? date('Y-m-d')));
    $observacion = trim((string)($payload['observacion'] ?? ''));

    if ($empresaId <= 0 || $trabajadorId <= 0 || $protocoloId <= 0 || $tipoEvaluacionId <= 0) {
        out_orden(422, ['success' => false, 'error' => 'empresa_id, trabajador_id, protocolo_id y tipo_evaluacion_id son obligatorios']);
    }

    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaOrden);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaOrden) {
        out_orden(422, ['success' => false, 'error' => 'fecha_orden invalida. Formato esperado YYYY-MM-DD']);
    }

    $resolved = resolve_examenes_orden($mysqliOcup, $mysqli, $empresaId, $trabajadorId, $protocoloId, $tipoEvaluacionId);

    $itemsAplican = array_values(array_filter($resolved['items'], fn($it) => !empty($it['aplica'])));
    if (empty($itemsAplican)) {
        out_orden(422, ['success' => false, 'error' => 'No hay examenes aplicables para registrar en la orden']);
    }

    $mysqliOcup->begin_transaction();
    try {
        $stmtIns = $mysqliOcup->prepare('INSERT INTO ocupacional_ordenes
                                         (codigo, empresa_id, trabajador_id, protocolo_id, tipo_evaluacion_id, fecha_orden, estado, monto_total, observacion, created_by, updated_by)
                                         VALUES (NULL, ?, ?, ?, ?, ?, "emitida", ?, ?, ?, ?)');
        if (!$stmtIns) {
            throw new Exception('No se pudo preparar insercion de orden');
        }
        $montoTotal = (float)$resolved['total'];
        $stmtIns->bind_param('iiiisdsii', $empresaId, $trabajadorId, $protocoloId, $tipoEvaluacionId, $fechaOrden, $montoTotal, $observacion, $usuarioId, $usuarioId);
        $stmtIns->execute();
        $ordenId = (int)$stmtIns->insert_id;
        $stmtIns->close();

        $codigo = 'OO' . str_pad((string)$ordenId, 6, '0', STR_PAD_LEFT);
        $stmtCode = $mysqliOcup->prepare('UPDATE ocupacional_ordenes SET codigo = ? WHERE id = ? LIMIT 1');
        if (!$stmtCode) {
            throw new Exception('No se pudo actualizar codigo de orden');
        }
        $stmtCode->bind_param('si', $codigo, $ordenId);
        $stmtCode->execute();
        $stmtCode->close();

        $stmtDet = $mysqliOcup->prepare('INSERT INTO ocupacional_orden_detalle
                                         (orden_id, catalogo_id, examen_id, examen_codigo, examen_descripcion, monto)
                                         VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmtDet) {
            throw new Exception('No se pudo preparar insercion de detalle');
        }

        foreach ($itemsAplican as $item) {
            $catalogoId = (int)$item['catalogo_id'];
            $examenId = (int)$item['examen_id'];
            $codigoEx = (string)$item['codigo'];
            $descEx = (string)$item['descripcion'];
            $monto = (float)$item['monto'];
            $stmtDet->bind_param('iiissd', $ordenId, $catalogoId, $examenId, $codigoEx, $descEx, $monto);
            $stmtDet->execute();
        }
        $stmtDet->close();

        registrar_evento_orden(
            $mysqliOcup,
            $ordenId,
            'orden_registrada',
            'Orden ocupacional registrada',
            $usuarioId,
            [
                'codigo' => $codigo,
                'total_items' => count($itemsAplican),
                'monto_total' => number_format((float)$resolved['total'], 2, '.', ''),
            ]
        );

        $mysqliOcup->commit();

        out_orden(201, [
            'success' => true,
            'data' => [
                'id' => $ordenId,
                'codigo' => $codigo,
                'empresa_id' => $empresaId,
                'trabajador_id' => $trabajadorId,
                'protocolo_id' => $protocoloId,
                'tipo_evaluacion_id' => $tipoEvaluacionId,
                'fecha_orden' => $fechaOrden,
                'monto_total' => number_format((float)$resolved['total'], 2, '.', ''),
                'total_items' => count($itemsAplican),
            ],
        ]);
    } catch (Throwable $e) {
        $mysqliOcup->rollback();
        out_orden(500, ['success' => false, 'error' => 'No se pudo registrar la orden ocupacional']);
    }
}

if ($accion === 'anular_orden') {
    require_ocup_permiso_any_orden(['anular_ordenes_ocupacional']);
    $ordenId = (int)($payload['id'] ?? 0);
    $motivo = trim((string)($payload['motivo'] ?? ''));
    if ($ordenId <= 0) {
        out_orden(422, ['success' => false, 'error' => 'id de orden es obligatorio']);
    }
    if ($motivo === '') {
        out_orden(422, ['success' => false, 'error' => 'motivo es obligatorio para anular']);
    }

    $stmt = $mysqliOcup->prepare('SELECT id, estado FROM ocupacional_ordenes WHERE id = ? LIMIT 1');
    if (!$stmt) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar orden']);
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        out_orden(404, ['success' => false, 'error' => 'Orden no encontrada']);
    }
    if ((string)$row['estado'] === 'anulada') {
        out_orden(422, ['success' => false, 'error' => 'La orden ya se encuentra anulada']);
    }
    if ((string)$row['estado'] === 'completada') {
        out_orden(422, ['success' => false, 'error' => 'No se puede anular una orden completada']);
    }

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_ordenes SET estado = "anulada", updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE id = ? LIMIT 1');
    if (!$stmtUp) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo anular la orden']);
    }
    $stmtUp->bind_param('ii', $usuarioId, $ordenId);
    $stmtUp->execute();
    $affected = $stmtUp->affected_rows;
    $stmtUp->close();

    if ($affected <= 0) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo anular la orden']);
    }

    registrar_evento_orden(
        $mysqliOcup,
        $ordenId,
        'orden_anulada',
        'Orden anulada por usuario',
        $usuarioId,
        ['motivo' => $motivo]
    );

    out_orden(200, [
        'success' => true,
        'data' => [
            'id' => $ordenId,
            'estado' => 'anulada',
        ],
    ]);
}

if ($accion === 'cerrar_orden') {
    require_ocup_permiso_any_orden(['cerrar_ordenes_ocupacional']);
    $ordenId = (int)($payload['id'] ?? 0);
    if ($ordenId <= 0) {
        out_orden(422, ['success' => false, 'error' => 'id de orden es obligatorio']);
    }

    $stmt = $mysqliOcup->prepare('SELECT id, estado FROM ocupacional_ordenes WHERE id = ? LIMIT 1');
    if (!$stmt) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar orden']);
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $orden = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orden) {
        out_orden(404, ['success' => false, 'error' => 'Orden no encontrada']);
    }
    $estadoOrden = (string)($orden['estado'] ?? '');
    if ($estadoOrden === 'anulada') {
        out_orden(422, ['success' => false, 'error' => 'No se puede cerrar una orden anulada']);
    }
    if ($estadoOrden === 'cerrada') {
        out_orden(422, ['success' => false, 'error' => 'La orden ya se encuentra cerrada']);
    }

    $stmtAgg = $mysqliOcup->prepare('SELECT
                                        COUNT(*) AS total,
                                        SUM(CASE WHEN estado_ejecucion IN ("realizado", "observado") THEN 1 ELSE 0 END) AS completados
                                     FROM ocupacional_orden_detalle
                                     WHERE orden_id = ?');
    if (!$stmtAgg) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar avance de la orden']);
    }
    $stmtAgg->bind_param('i', $ordenId);
    $stmtAgg->execute();
    $agg = $stmtAgg->get_result()->fetch_assoc();
    $stmtAgg->close();

    $total = (int)($agg['total'] ?? 0);
    $completados = (int)($agg['completados'] ?? 0);
    if ($total <= 0 || $completados < $total) {
        out_orden(422, ['success' => false, 'error' => 'No se puede cerrar la orden. Faltan examenes por resolver']);
    }

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_ordenes
                                    SET estado = "cerrada", updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? LIMIT 1');
    if (!$stmtUp) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo cerrar la orden']);
    }
    $stmtUp->bind_param('ii', $usuarioId, $ordenId);
    $stmtUp->execute();
    $affected = $stmtUp->affected_rows;
    $stmtUp->close();

    if ($affected <= 0) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo cerrar la orden']);
    }

    registrar_evento_orden(
        $mysqliOcup,
        $ordenId,
        'orden_cerrada',
        'Orden cerrada formalmente',
        $usuarioId,
        [
            'total_items' => $total,
            'total_completados' => $completados,
        ]
    );

    out_orden(200, [
        'success' => true,
        'data' => [
            'id' => $ordenId,
            'estado' => 'cerrada',
        ],
    ]);
}

if ($accion === 'guardar_aptitud_orden') {
    require_ocup_permiso_any_orden(['cerrar_ordenes_ocupacional', 'emitir_certificados_ocupacional']);
    $ordenId = (int)($payload['id'] ?? 0);
    $aptitudFinal = trim((string)($payload['aptitud_final'] ?? ''));
    $restriccionFinal = trim((string)($payload['restriccion_final'] ?? ''));
    $recomendacionFinal = trim((string)($payload['recomendacion_final'] ?? ''));
    $medicoResponsable = trim((string)($payload['medico_responsable'] ?? ''));
    $aptitudesValidas = ['APTO', 'APTO_CON_RESTRICCIONES', 'NO_APTO'];

    if ($ordenId <= 0 || !in_array($aptitudFinal, $aptitudesValidas, true)) {
        out_orden(422, ['success' => false, 'error' => 'id y aptitud_final valida son obligatorios']);
    }

    $stmt = $mysqliOcup->prepare('SELECT id, estado FROM ocupacional_ordenes WHERE id = ? LIMIT 1');
    if (!$stmt) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar orden']);
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $orden = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orden) {
        out_orden(404, ['success' => false, 'error' => 'Orden no encontrada']);
    }
    if ((string)$orden['estado'] !== 'cerrada') {
        out_orden(422, ['success' => false, 'error' => 'La aptitud final solo puede guardarse en orden cerrada']);
    }

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_ordenes
                                    SET aptitud_final = ?,
                                        restriccion_final = ?,
                                        recomendacion_final = ?,
                                        medico_responsable = ?,
                                        updated_by = ?,
                                        updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? LIMIT 1');
    if (!$stmtUp) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo guardar aptitud final']);
    }
    $stmtUp->bind_param('ssssii', $aptitudFinal, $restriccionFinal, $recomendacionFinal, $medicoResponsable, $usuarioId, $ordenId);
    $stmtUp->execute();
    $stmtUp->close();

    registrar_evento_orden(
        $mysqliOcup,
        $ordenId,
        'aptitud_final_guardada',
        'Aptitud ocupacional final registrada',
        $usuarioId,
        [
            'aptitud_final' => $aptitudFinal,
            'restriccion_final' => $restriccionFinal,
            'recomendacion_final' => $recomendacionFinal,
            'medico_responsable' => $medicoResponsable,
        ]
    );

    out_orden(200, [
        'success' => true,
        'data' => [
            'id' => $ordenId,
            'aptitud_final' => $aptitudFinal,
        ],
    ]);
}

if ($accion === 'actualizar_detalle_orden') {
    require_ocup_permiso_any_orden(['ejecutar_ordenes_ocupacional']);
    $detalleId = (int)($payload['detalle_id'] ?? 0);
    $estadoEjecucion = trim((string)($payload['estado_ejecucion'] ?? ''));
    $observacionEjecucion = trim((string)($payload['observacion_ejecucion'] ?? ''));
    $estadosValidos = ['pendiente', 'en_proceso', 'realizado', 'observado'];

    if ($detalleId <= 0 || !in_array($estadoEjecucion, $estadosValidos, true)) {
        out_orden(422, ['success' => false, 'error' => 'detalle_id y estado_ejecucion valido son obligatorios']);
    }

    $stmt = $mysqliOcup->prepare('SELECT d.id, d.orden_id, o.estado AS estado_orden
                                  FROM ocupacional_orden_detalle d
                                  INNER JOIN ocupacional_ordenes o ON o.id = d.orden_id
                                  WHERE d.id = ? LIMIT 1');
    if (!$stmt) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar detalle de orden']);
    }
    $stmt->bind_param('i', $detalleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        out_orden(404, ['success' => false, 'error' => 'Detalle de orden no encontrado']);
    }
    if ((string)$row['estado_orden'] === 'anulada') {
        out_orden(422, ['success' => false, 'error' => 'No se puede ejecutar detalle de una orden anulada']);
    }
    if ((string)$row['estado_orden'] === 'cerrada') {
        out_orden(422, ['success' => false, 'error' => 'No se puede ejecutar detalle de una orden cerrada']);
    }

    $fechaEjecucion = null;
    if (in_array($estadoEjecucion, ['realizado', 'observado'], true)) {
        $fechaEjecucion = date('Y-m-d H:i:s');
    }

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_orden_detalle
                                    SET estado_ejecucion = ?,
                                        observacion_ejecucion = ?,
                                        fecha_ejecucion = ?,
                                        updated_by = ?,
                                        updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? LIMIT 1');
    if (!$stmtUp) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo actualizar detalle de orden']);
    }
    $stmtUp->bind_param('sssii', $estadoEjecucion, $observacionEjecucion, $fechaEjecucion, $usuarioId, $detalleId);
    $stmtUp->execute();
    $stmtUp->close();

    $ordenId = (int)$row['orden_id'];
    registrar_evento_orden(
        $mysqliOcup,
        $ordenId,
        'detalle_actualizado',
        'Actualizacion de estado de examen',
        $usuarioId,
        [
            'detalle_id' => $detalleId,
            'estado_ejecucion' => $estadoEjecucion,
            'observacion' => $observacionEjecucion,
        ]
    );
    sync_estado_orden_por_detalle($mysqliOcup, $ordenId, $usuarioId);

    out_orden(200, [
        'success' => true,
        'data' => [
            'detalle_id' => $detalleId,
            'orden_id' => $ordenId,
            'estado_ejecucion' => $estadoEjecucion,
            'fecha_ejecucion' => $fechaEjecucion,
        ],
    ]);
}

out_orden(422, ['success' => false, 'error' => 'accion POST no soportada']);
