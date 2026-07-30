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

function column_exists_orden($conn, $table, $column)
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

function resolve_extra_columns_orden($conn)
{
    $cols = [
        'subcontrata_empresa_id' => false,
        'facturar_empresa_id' => false,
        'firma_doctor' => false,
        'modo' => false,
        'gestante' => false,
        'documento' => false,
        'indica_dr' => false,
        'medico_responsable_id' => false,
        'medico_nombre_snapshot' => false,
        'medico_especialidad_snapshot' => false,
        'medico_cmp_snapshot' => false,
        'medico_rne_snapshot' => false,
        'medico_rna_snapshot' => false,
        'medico_firma_snapshot' => false,
        'aptitud_registrada_by' => false,
        'aptitud_registrada_at' => false,
    ];

    foreach ($cols as $name => $_) {
        $cols[$name] = column_exists_orden($conn, 'ocupacional_ordenes', $name);
    }

    return $cols;
}

function resolve_extra_columns_detalle_orden($conn)
{
    $cols = [
        'grupo_snapshot' => false,
        'subgrupo_snapshot' => false,
        'grupo_orden_snapshot' => false,
        'examen_orden_snapshot' => false,
    ];

    foreach ($cols as $name => $_) {
        $cols[$name] = column_exists_orden($conn, 'ocupacional_orden_detalle', $name);
    }

    return $cols;
}

function normalize_text_orden($value)
{
    return strtoupper(trim((string)$value));
}

function require_medico_responsable_orden($mysqliCore, $medicoId)
{
    $id = (int)$medicoId;
    if ($id <= 0) {
        out_orden(422, ['success' => false, 'error' => 'medico_responsable_id es obligatorio']);
    }

    $stmt = $mysqliCore->prepare('SELECT id, nombre, apellido, especialidad, cmp, rne, rna, firma,
                                         tipo_profesional, abreviatura_profesional, nro_colegiatura
                                  FROM medicos
                                  WHERE id = ? LIMIT 1');
    if (!$stmt) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar medico responsable']);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $medico = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$medico) {
        out_orden(422, ['success' => false, 'error' => 'Medico responsable no encontrado']);
    }
    if (strtolower(trim((string)($medico['tipo_profesional'] ?? 'medico'))) !== 'medico') {
        out_orden(422, ['success' => false, 'error' => 'El responsable debe estar registrado como medico']);
    }

    $cmp = trim((string)($medico['cmp'] ?? ''));
    if ($cmp === '') {
        $cmp = trim((string)($medico['nro_colegiatura'] ?? ''));
    }
    if ($cmp === '') {
        out_orden(422, ['success' => false, 'error' => 'El medico responsable no tiene CMP registrado']);
    }

    $firma = trim((string)($medico['firma'] ?? ''));
    if ($firma === '') {
        out_orden(422, ['success' => false, 'error' => 'El medico responsable no tiene firma registrada']);
    }

    $abreviatura = trim((string)($medico['abreviatura_profesional'] ?? ''));
    $nombre = trim($abreviatura . ' ' . trim((string)($medico['nombre'] ?? '') . ' ' . (string)($medico['apellido'] ?? '')));

    return [
        'id' => (int)$medico['id'],
        'nombre' => $nombre,
        'apellido' => trim((string)($medico['apellido'] ?? '')),
        'especialidad' => trim((string)($medico['especialidad'] ?? '')),
        'cmp' => $cmp,
        'rne' => trim((string)($medico['rne'] ?? '')),
        'rna' => trim((string)($medico['rna'] ?? '')),
        'firma' => $firma,
    ];
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

function get_resumen_clinico_orden($mysqliOcup, $ordenId)
{
    $stmt = $mysqliOcup->prepare('SELECT
                                    COUNT(*) AS total,
                                    SUM(CASE WHEN d.estado_ejecucion = "observado" THEN 1 ELSE 0 END) AS observados,
                                    SUM(CASE WHEN d.estado_ejecucion = "realizado"
                                              AND EXISTS (
                                                  SELECT 1
                                                  FROM ocupacional_resultados_clinicos rc
                                                  WHERE rc.orden_detalle_id = d.id
                                                    AND rc.estado = "finalizado"
                                              )
                                             THEN 1 ELSE 0 END) AS finalizados
                                  FROM ocupacional_orden_detalle d
                                  WHERE d.orden_id = ?');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $interconsultasAbiertas = 0;
    if (table_exists_orden($mysqliOcup, 'ocupacional_interconsultas')) {
        $stmtInter = $mysqliOcup->prepare('SELECT COUNT(*) AS total
                                           FROM ocupacional_interconsultas
                                           WHERE orden_id = ? AND estado IN ("solicitada", "respondida")');
        if (!$stmtInter) {
            return null;
        }
        $stmtInter->bind_param('i', $ordenId);
        $stmtInter->execute();
        $interconsultasAbiertas = (int)($stmtInter->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtInter->close();
    }

    return [
        'total' => (int)($row['total'] ?? 0),
        'observados' => (int)($row['observados'] ?? 0),
        'finalizados' => (int)($row['finalizados'] ?? 0),
        'interconsultas_abiertas' => $interconsultasAbiertas,
    ];
}

function require_orden_clinicamente_finalizada($mysqliOcup, $ordenId)
{
    $resumen = get_resumen_clinico_orden($mysqliOcup, $ordenId);
    if ($resumen === null) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar resultados clinicos de la orden']);
    }
    if ($resumen['total'] <= 0) {
        out_orden(422, ['success' => false, 'error' => 'La orden no tiene examenes para validar']);
    }
    if ($resumen['observados'] > 0) {
        out_orden(422, ['success' => false, 'error' => 'No se puede continuar. La orden tiene examenes observados pendientes de resolver']);
    }
    if ($resumen['interconsultas_abiertas'] > 0) {
        out_orden(422, ['success' => false, 'error' => 'No se puede continuar. La orden tiene interconsultas pendientes de levantar']);
    }
    if ($resumen['finalizados'] < $resumen['total']) {
        $faltantes = $resumen['total'] - $resumen['finalizados'];
        out_orden(422, [
            'success' => false,
            'error' => 'No se puede continuar. Faltan ' . $faltantes . ' resultado(s) clinico(s) finalizado(s)',
        ]);
    }

    return $resumen;
}

function sync_estado_orden_por_detalle($mysqliOcup, $ordenId, $usuarioId)
{
    $stmt = $mysqliOcup->prepare('SELECT
                                    COUNT(*) AS total,
                                    SUM(CASE WHEN d.estado_ejecucion = "pendiente" THEN 1 ELSE 0 END) AS pendientes,
                                    SUM(CASE WHEN d.estado_ejecucion = "realizado"
                                              AND EXISTS (
                                                  SELECT 1
                                                  FROM ocupacional_resultados_clinicos rc
                                                  WHERE rc.orden_detalle_id = d.id
                                                    AND rc.estado = "finalizado"
                                              )
                                             THEN 1 ELSE 0 END) AS completados
                                  FROM ocupacional_orden_detalle d
                                  WHERE d.orden_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $agg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int)($agg['total'] ?? 0);
    $pendientes = (int)($agg['pendientes'] ?? 0);
    $completados = (int)($agg['completados'] ?? 0);

    $nuevoEstado = 'emitida';
    if ($total > 0 && $completados >= $total) {
        $nuevoEstado = 'completada';
    } elseif (($total - $pendientes) > 0) {
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

    $hasGrupoMaster = table_exists_orden($mysqliOcup, 'ocupacional_grupos_examenes');
    $grupoOrdenSelect = $hasGrupoMaster ? 'COALESCE(g.orden, 0)' : '0';
    $grupoJoin = $hasGrupoMaster
        ? ' LEFT JOIN ocupacional_grupos_examenes g ON g.parent_id = 0 AND UPPER(g.nombre) = UPPER(e.grupo)'
        : '';

        $stmtRows = $mysqliOcup->prepare('SELECT
                                                                                c.id AS catalogo_id,
                                                                                c.examen_id,
                                                                                e.codigo,
                                                                                e.descripcion,
                                                                                e.grupo,
                                                                                e.subgrupo,
                                                                                ' . $grupoOrdenSelect . ' AS grupo_orden,
                                                                                COALESCE(e.posicion, 0) AS examen_orden,
                                                                                pd.monto AS monto,
                                                                                1 AS tiene_config_protocolo
                                                                            FROM ocupacional_catalogo_empresas c
                                                                            INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
                                                                            ' . $grupoJoin . '
                                                                            INNER JOIN ocupacional_protocolo_detalle pd ON pd.catalogo_id = c.id
                                                                                                                                                             AND pd.protocolo_id = ?
                                                                                                                                                             AND pd.tipo_evaluacion_id = ?
                                                                            WHERE c.empresa_id = ?
                                                                                AND c.estado = "activo"
                                                                                AND e.estado = "activo"
                                                                            ORDER BY grupo_orden ASC, e.grupo ASC, e.subgrupo ASC, examen_orden ASC, e.descripcion ASC, e.id DESC');
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
            'grupo' => (string)($row['grupo'] ?? ''),
            'subgrupo' => (string)($row['subgrupo'] ?? ''),
            'grupo_orden' => (int)($row['grupo_orden'] ?? 0),
            'examen_orden' => (int)($row['examen_orden'] ?? 0),
            'monto' => number_format((float)$row['monto'], 2, '.', ''),
            'tiene_config_protocolo' => (int)($row['tiene_config_protocolo'] ?? 0) === 1,
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
        if (!empty($item['tiene_config_protocolo']) && (float)$item['monto'] <= 0) {
            $item['aplica'] = false;
            $item['motivo'] = 'Excluido en protocolo';
            continue;
        }

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

$ordenExtraColumns = resolve_extra_columns_orden($mysqliOcup);
$detalleExtraColumns = resolve_extra_columns_detalle_orden($mysqliOcup);
$medicoSnapshotColumns = [
    'medico_responsable_id',
    'medico_nombre_snapshot',
    'medico_especialidad_snapshot',
    'medico_cmp_snapshot',
    'medico_rne_snapshot',
    'medico_rna_snapshot',
    'medico_firma_snapshot',
    'aptitud_registrada_by',
    'aptitud_registrada_at',
];
$medicoSnapshotReady = true;
foreach ($medicoSnapshotColumns as $columnName) {
    if (empty($ordenExtraColumns[$columnName])) {
        $medicoSnapshotReady = false;
        break;
    }
}

$sqlExprSubcontrataIdOrden = !empty($ordenExtraColumns['subcontrata_empresa_id']) ? 'o.subcontrata_empresa_id' : 'NULL';
$sqlExprFacturarIdOrden = !empty($ordenExtraColumns['facturar_empresa_id']) ? 'o.facturar_empresa_id' : 'NULL';
$sqlExprFirmaDoctorOrden = !empty($ordenExtraColumns['firma_doctor']) ? 'o.firma_doctor' : 'NULL';
$sqlExprModoOrden = !empty($ordenExtraColumns['modo']) ? 'o.modo' : 'NULL';
$sqlExprGestanteOrden = !empty($ordenExtraColumns['gestante']) ? 'o.gestante' : 'NULL';
$sqlExprDocumentoOrden = !empty($ordenExtraColumns['documento']) ? 'o.documento' : 'NULL';
$sqlExprIndicaDrOrden = !empty($ordenExtraColumns['indica_dr']) ? 'o.indica_dr' : 'NULL';
$sqlExprMedicoResponsableIdOrden = !empty($ordenExtraColumns['medico_responsable_id']) ? 'o.medico_responsable_id' : 'NULL';
$sqlExprMedicoNombreSnapshotOrden = !empty($ordenExtraColumns['medico_nombre_snapshot']) ? 'o.medico_nombre_snapshot' : 'NULL';
$sqlExprMedicoEspecialidadSnapshotOrden = !empty($ordenExtraColumns['medico_especialidad_snapshot']) ? 'o.medico_especialidad_snapshot' : 'NULL';
$sqlExprMedicoCmpSnapshotOrden = !empty($ordenExtraColumns['medico_cmp_snapshot']) ? 'o.medico_cmp_snapshot' : 'NULL';
$sqlExprMedicoRneSnapshotOrden = !empty($ordenExtraColumns['medico_rne_snapshot']) ? 'o.medico_rne_snapshot' : 'NULL';
$sqlExprMedicoRnaSnapshotOrden = !empty($ordenExtraColumns['medico_rna_snapshot']) ? 'o.medico_rna_snapshot' : 'NULL';
$sqlExprMedicoFirmaSnapshotOrden = !empty($ordenExtraColumns['medico_firma_snapshot']) ? 'o.medico_firma_snapshot' : 'NULL';
$sqlExprAptitudRegistradaByOrden = !empty($ordenExtraColumns['aptitud_registrada_by']) ? 'o.aptitud_registrada_by' : 'NULL';
$sqlExprAptitudRegistradaAtOrden = !empty($ordenExtraColumns['aptitud_registrada_at']) ? 'o.aptitud_registrada_at' : 'NULL';
$sqlJoinSubcontrataOrden = !empty($ordenExtraColumns['subcontrata_empresa_id'])
    ? ' LEFT JOIN empresas_ocupacionales esub ON esub.id = o.subcontrata_empresa_id'
    : '';
$sqlJoinFacturarOrden = !empty($ordenExtraColumns['facturar_empresa_id'])
    ? ' LEFT JOIN empresas_ocupacionales efac ON efac.id = o.facturar_empresa_id'
    : '';

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

        $hasInterconsultasListado = table_exists_orden($mysqliOcup, 'ocupacional_interconsultas');
        $interconsultasSelect = $hasInterconsultasListado
            ? 'COALESCE(ic.total_interconsultas, 0) AS total_interconsultas,
                        COALESCE(ic.interconsultas_abiertas, 0) AS interconsultas_abiertas,
                        COALESCE(ic.interconsultas_respondidas, 0) AS interconsultas_respondidas,
                        COALESCE(ic.interconsultas_levantadas, 0) AS interconsultas_levantadas,
                        COALESCE(ic.levantamientos_favorables, 0) AS levantamientos_favorables,
                        COALESCE(ic.levantamientos_no_favorables, 0) AS levantamientos_no_favorables,'
            : '0 AS total_interconsultas,
                        0 AS interconsultas_abiertas,
                        0 AS interconsultas_respondidas,
                        0 AS interconsultas_levantadas,
                        0 AS levantamientos_favorables,
                        0 AS levantamientos_no_favorables,';
        $interconsultasJoin = $hasInterconsultasListado
            ? ' LEFT JOIN (
                    SELECT
                        orden_id,
                        COUNT(*) AS total_interconsultas,
                        SUM(CASE WHEN estado IN ("solicitada", "respondida") THEN 1 ELSE 0 END) AS interconsultas_abiertas,
                        SUM(CASE WHEN estado = "respondida" THEN 1 ELSE 0 END) AS interconsultas_respondidas,
                        SUM(CASE WHEN estado = "levantada" THEN 1 ELSE 0 END) AS interconsultas_levantadas,
                        SUM(CASE WHEN estado = "levantada" AND resultado_levantamiento = "FAVORABLE" THEN 1 ELSE 0 END) AS levantamientos_favorables,
                        SUM(CASE WHEN estado = "levantada" AND resultado_levantamiento = "NO_FAVORABLE" THEN 1 ELSE 0 END) AS levantamientos_no_favorables
                    FROM ocupacional_interconsultas
                    WHERE estado <> "anulada"
                    GROUP BY orden_id
                ) ic ON ic.orden_id = o.id'
            : '';

        $sqlRows = 'SELECT
                        o.id,
                        o.codigo,
                        o.fecha_orden,
                        o.estado,
                        o.monto_total,
                        o.aptitud_final,
                        o.restriccion_final,
                        o.recomendacion_final,
                        o.observacion,
                        ' . $sqlExprSubcontrataIdOrden . ' AS subcontrata_empresa_id,
                        ' . $sqlExprFacturarIdOrden . ' AS facturar_empresa_id,
                        ' . $sqlExprFirmaDoctorOrden . ' AS firma_doctor,
                        ' . $sqlExprModoOrden . ' AS modo,
                        ' . $sqlExprGestanteOrden . ' AS gestante,
                        ' . $sqlExprDocumentoOrden . ' AS documento,
                        ' . $sqlExprIndicaDrOrden . ' AS indica_dr,
                        e.razon_social,
                        IFNULL(esub.razon_social, "") AS subcontrata_razon_social,
                        IFNULL(efac.razon_social, "") AS facturar_razon_social,
                        t.external_patient_id,
                        t.documento_numero,
                        t.puesto_trabajo,
                        p.descripcion AS protocolo_descripcion,
                        te.codigo AS tipo_codigo,
                        COALESCE(d.total_items, 0) AS total_items,
                        COALESCE(d.total_completados, 0) AS total_completados,
                        COALESCE(d.total_pendientes, 0) AS total_pendientes,
                        COALESCE(d.total_en_proceso, 0) AS total_en_proceso,
                        COALESCE(d.total_observados, 0) AS total_observados,
                        COALESCE(d.observaciones_resumen, "") AS observaciones_resumen,
                        COALESCE(d.triaje_detalle_id, 0) AS triaje_detalle_id,
                        COALESCE(d.triaje_finalizado, 0) AS triaje_finalizado,
                        ' . $interconsultasSelect . '
                        COALESCE(ce.total_certificados, 0) AS total_certificados,
                        ce.ultimo_certificado_at
                    FROM ocupacional_ordenes o
                    INNER JOIN empresas_ocupacionales e ON e.id = o.empresa_id'
                    . $sqlJoinSubcontrataOrden
                    . $sqlJoinFacturarOrden
                    . '
                    INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                    INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                    INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id
                    LEFT JOIN (
                        SELECT
                            orden_id,
                            COUNT(*) AS total_items,
                            SUM(CASE WHEN od.estado_ejecucion = "realizado"
                                      AND EXISTS (
                                          SELECT 1
                                          FROM ocupacional_resultados_clinicos rc
                                          WHERE rc.orden_detalle_id = od.id
                                            AND rc.estado = "finalizado"
                                      )
                                                                         THEN 1 ELSE 0 END) AS total_completados,
                                                        SUM(CASE WHEN od.estado_ejecucion = "pendiente" THEN 1 ELSE 0 END) AS total_pendientes,
                                                        SUM(CASE
                                                                WHEN od.estado_ejecucion = "en_proceso"
                                                                    OR (od.estado_ejecucion = "realizado" AND NOT EXISTS (
                                                                        SELECT 1
                                                                        FROM ocupacional_resultados_clinicos rp
                                                                        WHERE rp.orden_detalle_id = od.id
                                                                            AND rp.estado = "finalizado"
                                                                    ))
                                                                THEN 1 ELSE 0
                                                        END) AS total_en_proceso,
                                                        SUM(CASE WHEN od.estado_ejecucion = "observado" THEN 1 ELSE 0 END) AS total_observados,
                                                        GROUP_CONCAT(
                                                                CASE
                                                                        WHEN TRIM(COALESCE(od.observacion_ejecucion, "")) <> ""
                                                                        THEN CONCAT(od.examen_descripcion, ": ", od.observacion_ejecucion)
                                                                        ELSE NULL
                                                                END
                                                                ORDER BY od.id ASC SEPARATOR " | "
                                                        ) AS observaciones_resumen,
                                                        MAX(CASE
                                                                WHEN UPPER(od.examen_codigo) = "TRI_0001"
                                                                    OR LOWER(od.examen_descripcion) LIKE "%triaje%"
                                                                    OR LOWER(od.examen_descripcion) LIKE "%triage%"
                                                                THEN od.id ELSE 0
                                                        END) AS triaje_detalle_id,
                                                        MAX(CASE
                                                                WHEN (UPPER(od.examen_codigo) = "TRI_0001"
                                                                     OR LOWER(od.examen_descripcion) LIKE "%triaje%"
                                                                     OR LOWER(od.examen_descripcion) LIKE "%triage%")
                                                                    AND EXISTS (
                                                                            SELECT 1
                                                                            FROM ocupacional_resultados_clinicos tr
                                                                            WHERE tr.orden_detalle_id = od.id
                                                                                AND tr.estado = "finalizado"
                                                                    )
                                                                THEN 1 ELSE 0
                                                        END) AS triaje_finalizado
                        FROM ocupacional_orden_detalle od
                        GROUP BY orden_id
                    ) d ON d.orden_id = o.id
                    LEFT JOIN (
                        SELECT
                            orden_id,
                            COUNT(*) AS total_certificados,
                            MAX(created_at) AS ultimo_certificado_at
                        FROM ocupacional_orden_eventos
                        WHERE tipo_evento = "certificado_emitido"
                        GROUP BY orden_id
                    ) ce ON ce.orden_id = o.id'
                    . $interconsultasJoin
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
                'restriccion_final' => (string)($r['restriccion_final'] ?? ''),
                'recomendacion_final' => (string)($r['recomendacion_final'] ?? ''),
                'observacion_orden' => (string)($r['observacion'] ?? ''),
                'empresa' => (string)($r['razon_social'] ?? ''),
                'subcontrata_empresa_id' => isset($r['subcontrata_empresa_id']) ? (int)$r['subcontrata_empresa_id'] : 0,
                'subcontrata_empresa' => (string)($r['subcontrata_razon_social'] ?? ''),
                'facturar_empresa_id' => isset($r['facturar_empresa_id']) ? (int)$r['facturar_empresa_id'] : 0,
                'facturar_empresa' => (string)($r['facturar_razon_social'] ?? ''),
                'firma_doctor' => (string)($r['firma_doctor'] ?? ''),
                'modo' => (string)($r['modo'] ?? ''),
                'gestante' => isset($r['gestante']) ? (int)$r['gestante'] : 0,
                'documento' => (string)($r['documento'] ?? ''),
                'indica_dr' => (string)($r['indica_dr'] ?? ''),
                '_external_patient_id' => (int)($r['external_patient_id'] ?? 0),
                'documento_numero' => (string)($r['documento_numero'] ?? ''),
                'puesto_trabajo' => (string)($r['puesto_trabajo'] ?? ''),
                'protocolo_descripcion' => (string)($r['protocolo_descripcion'] ?? ''),
                'tipo_codigo' => (string)($r['tipo_codigo'] ?? ''),
                'total_items' => (int)($r['total_items'] ?? 0),
                'total_completados' => (int)($r['total_completados'] ?? 0),
                'total_pendientes' => (int)($r['total_pendientes'] ?? 0),
                'total_en_proceso' => (int)($r['total_en_proceso'] ?? 0),
                'total_observados' => (int)($r['total_observados'] ?? 0),
                'observaciones_resumen' => (string)($r['observaciones_resumen'] ?? ''),
                'triaje_detalle_id' => (int)($r['triaje_detalle_id'] ?? 0),
                'triaje_finalizado' => (int)($r['triaje_finalizado'] ?? 0) === 1,
                'total_interconsultas' => (int)($r['total_interconsultas'] ?? 0),
                'interconsultas_abiertas' => (int)($r['interconsultas_abiertas'] ?? 0),
                'interconsultas_respondidas' => (int)($r['interconsultas_respondidas'] ?? 0),
                'interconsultas_levantadas' => (int)($r['interconsultas_levantadas'] ?? 0),
                'levantamientos_favorables' => (int)($r['levantamientos_favorables'] ?? 0),
                'levantamientos_no_favorables' => (int)($r['levantamientos_no_favorables'] ?? 0),
                'certificado_emitido' => ((int)($r['total_certificados'] ?? 0)) > 0,
                'certificado_emitido_at' => (string)($r['ultimo_certificado_at'] ?? ''),
            ];
        }
        $stmtRows->close();

        $patientIds = array_values(array_unique(array_filter(array_column($rows, '_external_patient_id'))));
        $patientNames = [];
        if (!empty($patientIds)) {
            $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
            $stmtPatients = $mysqli->prepare('SELECT id, nombre, apellido FROM pacientes WHERE id IN (' . $placeholders . ')');
            if ($stmtPatients) {
                bind_params_dynamic_orden($stmtPatients, str_repeat('i', count($patientIds)), $patientIds);
                $stmtPatients->execute();
                $resPatients = $stmtPatients->get_result();
                while ($patient = $resPatients->fetch_assoc()) {
                    $patientNames[(int)$patient['id']] = trim((string)($patient['nombre'] ?? '') . ' ' . (string)($patient['apellido'] ?? ''));
                }
                $stmtPatients->close();
            }
        }
        foreach ($rows as &$row) {
            $externalPatientId = (int)($row['_external_patient_id'] ?? 0);
            $row['paciente_nombre_completo'] = (string)($patientNames[$externalPatientId] ?? '');
            unset($row['_external_patient_id']);
        }
        unset($row);

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
                    ' . $sqlExprSubcontrataIdOrden . ' AS subcontrata_empresa_id,
                    ' . $sqlExprFacturarIdOrden . ' AS facturar_empresa_id,
                    ' . $sqlExprFirmaDoctorOrden . ' AS firma_doctor,
                    ' . $sqlExprModoOrden . ' AS modo,
                    ' . $sqlExprGestanteOrden . ' AS gestante,
                    ' . $sqlExprDocumentoOrden . ' AS documento,
                    ' . $sqlExprIndicaDrOrden . ' AS indica_dr,
                    e.razon_social,
                    IFNULL(esub.razon_social, "") AS subcontrata_razon_social,
                    IFNULL(efac.razon_social, "") AS facturar_razon_social,
                    t.documento_numero,
                    t.puesto_trabajo,
                    p.descripcion AS protocolo_descripcion,
                    te.codigo AS tipo_codigo,
                    COALESCE(d.total_items, 0) AS total_items,
                    COALESCE(d.total_completados, 0) AS total_completados
                FROM ocupacional_ordenes o
                INNER JOIN empresas_ocupacionales e ON e.id = o.empresa_id'
                . $sqlJoinSubcontrataOrden
                . $sqlJoinFacturarOrden
                . '
                INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id
                LEFT JOIN (
                    SELECT
                        orden_id,
                        COUNT(*) AS total_items,
                        SUM(CASE WHEN od.estado_ejecucion = "realizado"
                                  AND EXISTS (
                                      SELECT 1
                                      FROM ocupacional_resultados_clinicos rc
                                      WHERE rc.orden_detalle_id = od.id
                                        AND rc.estado = "finalizado"
                                  )
                                 THEN 1 ELSE 0 END) AS total_completados
                    FROM ocupacional_orden_detalle od
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
                'subcontrata_empresa_id' => isset($r['subcontrata_empresa_id']) ? (int)$r['subcontrata_empresa_id'] : 0,
                'subcontrata_empresa' => (string)($r['subcontrata_razon_social'] ?? ''),
                'facturar_empresa_id' => isset($r['facturar_empresa_id']) ? (int)$r['facturar_empresa_id'] : 0,
                'facturar_empresa' => (string)($r['facturar_razon_social'] ?? ''),
                'firma_doctor' => (string)($r['firma_doctor'] ?? ''),
                'modo' => (string)($r['modo'] ?? ''),
                'gestante' => isset($r['gestante']) ? (int)$r['gestante'] : 0,
                'documento' => (string)($r['documento'] ?? ''),
                'indica_dr' => (string)($r['indica_dr'] ?? ''),
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

        $stmtCabSql = 'SELECT
                                            o.id,
                                            o.codigo,
                                            o.fecha_orden,
                                            o.estado,
                                            o.monto_total,
                                            o.observacion,
                                            ' . $sqlExprSubcontrataIdOrden . ' AS subcontrata_empresa_id,
                                            ' . $sqlExprFacturarIdOrden . ' AS facturar_empresa_id,
                                            ' . $sqlExprFirmaDoctorOrden . ' AS firma_doctor,
                                            ' . $sqlExprModoOrden . ' AS modo,
                                            ' . $sqlExprGestanteOrden . ' AS gestante,
                                            ' . $sqlExprDocumentoOrden . ' AS documento,
                                            ' . $sqlExprIndicaDrOrden . ' AS indica_dr,
                                            o.aptitud_final,
                                            o.restriccion_final,
                                            o.recomendacion_final,
                                            o.medico_responsable,
                                            ' . $sqlExprMedicoResponsableIdOrden . ' AS medico_responsable_id,
                                            ' . $sqlExprMedicoNombreSnapshotOrden . ' AS medico_nombre_snapshot,
                                            ' . $sqlExprMedicoEspecialidadSnapshotOrden . ' AS medico_especialidad_snapshot,
                                            ' . $sqlExprMedicoCmpSnapshotOrden . ' AS medico_cmp_snapshot,
                                            ' . $sqlExprMedicoRneSnapshotOrden . ' AS medico_rne_snapshot,
                                            ' . $sqlExprMedicoRnaSnapshotOrden . ' AS medico_rna_snapshot,
                                            ' . $sqlExprMedicoFirmaSnapshotOrden . ' AS medico_firma_snapshot,
                                            ' . $sqlExprAptitudRegistradaByOrden . ' AS aptitud_registrada_by,
                                            ' . $sqlExprAptitudRegistradaAtOrden . ' AS aptitud_registrada_at,
                                            e.razon_social AS empresa,
                                            IFNULL(esub.razon_social, "") AS subcontrata_razon_social,
                                            IFNULL(efac.razon_social, "") AS facturar_razon_social,
                                            t.external_patient_id,
                                            t.documento_numero,
                                            t.puesto_trabajo,
                                            t.area_riesgo,
                                            p.descripcion AS protocolo_descripcion,
                                            te.codigo AS tipo_codigo,
                                            te.nombre AS tipo_nombre
                                         FROM ocupacional_ordenes o
                                                      INNER JOIN empresas_ocupacionales e ON e.id = o.empresa_id'
                                                      . $sqlJoinSubcontrataOrden
                                                      . $sqlJoinFacturarOrden
                                                      . '
                                         INNER JOIN pacientes_ocupacionales t ON t.id = o.trabajador_id
                                         INNER JOIN ocupacional_protocolos_empresa p ON p.id = o.protocolo_id
                                         INNER JOIN ocupacional_tipos_evaluacion te ON te.id = o.tipo_evaluacion_id
                                                      WHERE o.id = ? LIMIT 1';
        $stmtCab = $mysqliOcup->prepare($stmtCabSql);
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

        $medicoRnaVigente = '';
        $medicoResponsableId = (int)($cab['medico_responsable_id'] ?? 0);
        if (trim((string)($cab['medico_rna_snapshot'] ?? '')) === '' && $medicoResponsableId > 0) {
            $stmtMedicoRna = $mysqli->prepare('SELECT rna FROM medicos WHERE id = ? LIMIT 1');
            if ($stmtMedicoRna) {
                $stmtMedicoRna->bind_param('i', $medicoResponsableId);
                $stmtMedicoRna->execute();
                $medicoRna = $stmtMedicoRna->get_result()->fetch_assoc();
                $stmtMedicoRna->close();
                $medicoRnaVigente = trim((string)($medicoRna['rna'] ?? ''));
            }
        }

        $pacienteNombreCompleto = '';
        $pacienteHistoriaClinica = '';
        $pacienteSexo = '';
        $pacienteFechaNacimiento = '';
        $pacienteEdad = null;
        $externalPatientId = (int)($cab['external_patient_id'] ?? 0);
        if ($externalPatientId > 0) {
            $stmtPac = $mysqli->prepare('SELECT nombre, apellido, historia_clinica, sexo, fecha_nacimiento FROM pacientes WHERE id = ? LIMIT 1');
            if ($stmtPac) {
                $stmtPac->bind_param('i', $externalPatientId);
                $stmtPac->execute();
                $pac = $stmtPac->get_result()->fetch_assoc();
                $stmtPac->close();
                if ($pac) {
                    $pacienteNombreCompleto = trim((string)($pac['nombre'] ?? '') . ' ' . (string)($pac['apellido'] ?? ''));
                    $pacienteHistoriaClinica = trim((string)($pac['historia_clinica'] ?? ''));
                    $pacienteSexo = trim((string)($pac['sexo'] ?? ''));
                    $pacienteFechaNacimiento = trim((string)($pac['fecha_nacimiento'] ?? ''));
                    $pacienteEdad = calculate_age_orden($pacienteFechaNacimiento);
                }
            }
        }

        $hasGrupoMasterDetalle = table_exists_orden($mysqliOcup, 'ocupacional_grupos_examenes');
        $grupoDetalleExpr = !empty($detalleExtraColumns['grupo_snapshot'])
            ? 'COALESCE(NULLIF(d.grupo_snapshot, ""), e.grupo, "")'
            : 'COALESCE(e.grupo, "")';
        $subgrupoDetalleExpr = !empty($detalleExtraColumns['subgrupo_snapshot'])
            ? 'COALESCE(NULLIF(d.subgrupo_snapshot, ""), e.subgrupo, "")'
            : 'COALESCE(e.subgrupo, "")';
        $grupoOrdenDetalleExpr = !empty($detalleExtraColumns['grupo_orden_snapshot'])
            ? 'CASE WHEN d.grupo_orden_snapshot > 0 THEN d.grupo_orden_snapshot ELSE ' . ($hasGrupoMasterDetalle ? 'COALESCE(g.orden, 0)' : '0') . ' END'
            : ($hasGrupoMasterDetalle ? 'COALESCE(g.orden, 0)' : '0');
        $examenOrdenDetalleExpr = !empty($detalleExtraColumns['examen_orden_snapshot'])
            ? 'CASE WHEN d.examen_orden_snapshot > 0 THEN d.examen_orden_snapshot ELSE COALESCE(e.posicion, 0) END'
            : 'COALESCE(e.posicion, 0)';
        $grupoDetalleJoin = $hasGrupoMasterDetalle
            ? ' LEFT JOIN ocupacional_grupos_examenes g ON g.parent_id = 0 AND UPPER(g.nombre) = UPPER(COALESCE(NULLIF(' . (!empty($detalleExtraColumns['grupo_snapshot']) ? 'd.grupo_snapshot' : '""') . ', ""), e.grupo, ""))'
            : '';

                $stmtDet = $mysqliOcup->prepare('SELECT
                                                                                        d.id,
                                                                                        d.catalogo_id,
                                                                                        d.examen_id,
                                                                                        d.examen_codigo,
                                                                                        d.examen_descripcion,
                                                                                        ' . $grupoDetalleExpr . ' AS examen_grupo,
                                                                                        ' . $subgrupoDetalleExpr . ' AS examen_subgrupo,
                                                                                        ' . $grupoOrdenDetalleExpr . ' AS grupo_orden,
                                                                                        ' . $examenOrdenDetalleExpr . ' AS examen_orden,
                                                                                        d.monto,
                                                                                        d.estado_ejecucion,
                                                                                        d.observacion_ejecucion,
                                                                                        d.fecha_ejecucion,
                                                                                        EXISTS (
                                                                                                SELECT 1
                                                                                                FROM ocupacional_resultados_clinicos rc
                                                                                                WHERE rc.orden_detalle_id = d.id
                                                                                                    AND rc.estado = "finalizado"
                                                                                        ) AS resultado_finalizado
                                                                                 FROM ocupacional_orden_detalle d
                                                                                 LEFT JOIN ocupacional_examenes_generales e ON e.id = d.examen_id
                                                                                 ' . $grupoDetalleJoin . '
                                                                                 WHERE d.orden_id = ?
                                                                                 ORDER BY grupo_orden ASC, examen_grupo ASC, examen_subgrupo ASC, examen_orden ASC, d.id ASC');
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
                'examen_grupo' => (string)($d['examen_grupo'] ?? ''),
                'examen_subgrupo' => (string)($d['examen_subgrupo'] ?? ''),
                'grupo_orden' => (int)($d['grupo_orden'] ?? 0),
                'examen_orden' => (int)($d['examen_orden'] ?? 0),
                'monto' => number_format((float)($d['monto'] ?? 0), 2, '.', ''),
                'estado_ejecucion' => (string)($d['estado_ejecucion'] ?? 'pendiente'),
                'observacion_ejecucion' => (string)($d['observacion_ejecucion'] ?? ''),
                'fecha_ejecucion' => (string)($d['fecha_ejecucion'] ?? ''),
                'resultado_finalizado' => (int)($d['resultado_finalizado'] ?? 0) === 1,
            ];
        }
        $stmtDet->close();

        $triajeResumen = [];
        if (table_exists_orden($mysqliOcup, 'ocupacional_resultados_clinicos')) {
            $stmtTriaje = $mysqliOcup->prepare('SELECT rc.datos_json
                                                FROM ocupacional_resultados_clinicos rc
                                                INNER JOIN ocupacional_orden_detalle d ON d.id = rc.orden_detalle_id
                                                LEFT JOIN ocupacional_examenes_generales e ON e.id = d.examen_id
                                                WHERE d.orden_id = ?
                                                  AND rc.estado = "finalizado"
                                                  AND (LOWER(d.examen_descripcion) LIKE "%triaje%"
                                                       OR LOWER(d.examen_descripcion) LIKE "%triage%"
                                                       OR LOWER(COALESCE(e.grupo, "")) LIKE "%triaje%")
                                                ORDER BY rc.updated_at DESC, rc.id DESC
                                                LIMIT 1');
            if ($stmtTriaje) {
                $stmtTriaje->bind_param('i', $ordenId);
                $stmtTriaje->execute();
                $rowTriaje = $stmtTriaje->get_result()->fetch_assoc();
                $stmtTriaje->close();
                if ($rowTriaje && trim((string)($rowTriaje['datos_json'] ?? '')) !== '') {
                    $decodedTriaje = json_decode((string)$rowTriaje['datos_json'], true);
                    $triajeResumen = is_array($decodedTriaje) ? $decodedTriaje : [];
                }
            }
        }

        $totalItems = count($detalles);
        $totalCompletados = 0;
        foreach ($detalles as $itemDet) {
            if ((string)$itemDet['estado_ejecucion'] === 'realizado' && $itemDet['resultado_finalizado']) {
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
                'subcontrata_empresa_id' => isset($cab['subcontrata_empresa_id']) ? (int)$cab['subcontrata_empresa_id'] : 0,
                'subcontrata_empresa' => (string)($cab['subcontrata_razon_social'] ?? ''),
                'facturar_empresa_id' => isset($cab['facturar_empresa_id']) ? (int)$cab['facturar_empresa_id'] : 0,
                'facturar_empresa' => (string)($cab['facturar_razon_social'] ?? ''),
                'firma_doctor' => (string)($cab['firma_doctor'] ?? ''),
                'modo' => (string)($cab['modo'] ?? ''),
                'gestante' => isset($cab['gestante']) ? (int)$cab['gestante'] : 0,
                'documento' => (string)($cab['documento'] ?? ''),
                'indica_dr' => (string)($cab['indica_dr'] ?? ''),
                'aptitud_final' => (string)($cab['aptitud_final'] ?? ''),
                'restriccion_final' => (string)($cab['restriccion_final'] ?? ''),
                'recomendacion_final' => (string)($cab['recomendacion_final'] ?? ''),
                'medico_responsable' => (string)($cab['medico_responsable'] ?? ''),
                'medico_responsable_id' => (int)($cab['medico_responsable_id'] ?? 0),
                'medico_nombre_snapshot' => (string)($cab['medico_nombre_snapshot'] ?? ''),
                'medico_especialidad_snapshot' => (string)($cab['medico_especialidad_snapshot'] ?? ''),
                'medico_cmp_snapshot' => (string)($cab['medico_cmp_snapshot'] ?? ''),
                'medico_rne_snapshot' => (string)($cab['medico_rne_snapshot'] ?? ''),
                'medico_rna_snapshot' => (string)($cab['medico_rna_snapshot'] ?? ''),
                'medico_rna_vigente' => $medicoRnaVigente,
                'medico_firma_snapshot' => (string)($cab['medico_firma_snapshot'] ?? ''),
                'aptitud_registrada_by' => isset($cab['aptitud_registrada_by']) ? (int)$cab['aptitud_registrada_by'] : null,
                'aptitud_registrada_at' => (string)($cab['aptitud_registrada_at'] ?? ''),
                'empresa' => (string)($cab['empresa'] ?? ''),
                'paciente_nombre_completo' => $pacienteNombreCompleto,
                'paciente_historia_clinica' => $pacienteHistoriaClinica,
                'paciente_sexo' => $pacienteSexo,
                'paciente_fecha_nacimiento' => $pacienteFechaNacimiento,
                'paciente_edad' => $pacienteEdad,
                'documento_numero' => (string)($cab['documento_numero'] ?? ''),
                'puesto_trabajo' => (string)($cab['puesto_trabajo'] ?? ''),
                'area_trabajo' => (string)($cab['area_riesgo'] ?? ''),
                'protocolo_descripcion' => (string)($cab['protocolo_descripcion'] ?? ''),
                'tipo_codigo' => (string)($cab['tipo_codigo'] ?? ''),
                'tipo_nombre' => (string)($cab['tipo_nombre'] ?? ''),
                'total_items' => $totalItems,
                'total_completados' => $totalCompletados,
                'items' => $detalles,
                'triaje' => $triajeResumen,
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
    $subcontrataEmpresaId = (int)($payload['subcontrata_empresa_id'] ?? 0);
    $facturarEmpresaId = (int)($payload['facturar_empresa_id'] ?? 0);
    $medicoResponsableId = (int)($payload['medico_responsable_id'] ?? 0);
    $modo = strtoupper(trim((string)($payload['modo'] ?? 'CONVALIDACION')));
    $gestanteRaw = $payload['gestante'] ?? 0;
    $gestante = ($gestanteRaw === true || $gestanteRaw === 1 || $gestanteRaw === '1' || $gestanteRaw === 'true') ? 1 : 0;
    $documento = trim((string)($payload['documento'] ?? ''));
    $indicaDr = trim((string)($payload['indica_dr'] ?? ''));

    if ($empresaId <= 0 || $trabajadorId <= 0 || $protocoloId <= 0 || $tipoEvaluacionId <= 0) {
        out_orden(422, ['success' => false, 'error' => 'empresa_id, trabajador_id, protocolo_id y tipo_evaluacion_id son obligatorios']);
    }

    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaOrden);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaOrden) {
        out_orden(422, ['success' => false, 'error' => 'fecha_orden invalida. Formato esperado YYYY-MM-DD']);
    }

    $modosValidos = ['CONVALIDACION', 'REVALIDACION', 'REEVALUACION'];
    if (!in_array($modo, $modosValidos, true)) {
        out_orden(422, ['success' => false, 'error' => 'modo invalido']);
    }

    if (!$medicoSnapshotReady) {
        out_orden(500, ['success' => false, 'error' => 'Falta aplicar migracion 20260725_0020 de medico responsable']);
    }
    $medicoResponsable = require_medico_responsable_orden($mysqli, $medicoResponsableId);
    $firmaDoctor = strtoupper($medicoResponsable['apellido'] !== '' ? $medicoResponsable['apellido'] : $medicoResponsable['nombre']);
    $firmaDoctor = function_exists('mb_substr') ? mb_substr($firmaDoctor, 0, 80, 'UTF-8') : substr($firmaDoctor, 0, 80);

    foreach ([$subcontrataEmpresaId, $facturarEmpresaId] as $empresaRelacionadaId) {
        if ($empresaRelacionadaId <= 0) {
            continue;
        }
        $stmtEmpresaExtra = $mysqliOcup->prepare('SELECT id FROM empresas_ocupacionales WHERE id = ? LIMIT 1');
        if (!$stmtEmpresaExtra) {
            out_orden(500, ['success' => false, 'error' => 'No se pudo validar empresa relacionada']);
        }
        $stmtEmpresaExtra->bind_param('i', $empresaRelacionadaId);
        $stmtEmpresaExtra->execute();
        $existsExtra = $stmtEmpresaExtra->get_result()->fetch_assoc();
        $stmtEmpresaExtra->close();
        if (!$existsExtra) {
            out_orden(422, ['success' => false, 'error' => 'Empresa relacionada invalida para subcontrata/facturar']);
        }
    }

    $resolved = resolve_examenes_orden($mysqliOcup, $mysqli, $empresaId, $trabajadorId, $protocoloId, $tipoEvaluacionId);

    $itemsAplican = array_values(array_filter($resolved['items'], fn($it) => !empty($it['aplica'])));
    if (empty($itemsAplican)) {
        out_orden(422, ['success' => false, 'error' => 'No hay examenes aplicables para registrar en la orden']);
    }

    $mysqliOcup->begin_transaction();
    try {
        $insertColumns = [
            'codigo',
            'empresa_id',
            'trabajador_id',
            'protocolo_id',
            'tipo_evaluacion_id',
            'fecha_orden',
            'estado',
            'monto_total',
            'observacion',
            'created_by',
            'updated_by',
        ];
        $insertValues = ['NULL', '?', '?', '?', '?', '?', '"emitida"', '?', '?', '?', '?'];
        $insertTypes = 'iiiisdsii';
        $insertParams = [$empresaId, $trabajadorId, $protocoloId, $tipoEvaluacionId, $fechaOrden, (float)$resolved['total'], $observacion, $usuarioId, $usuarioId];

        if (!empty($ordenExtraColumns['subcontrata_empresa_id'])) {
            $insertColumns[] = 'subcontrata_empresa_id';
            $insertValues[] = '?';
            $insertTypes .= 'i';
            $insertParams[] = $subcontrataEmpresaId > 0 ? $subcontrataEmpresaId : null;
        }
        if (!empty($ordenExtraColumns['facturar_empresa_id'])) {
            $insertColumns[] = 'facturar_empresa_id';
            $insertValues[] = '?';
            $insertTypes .= 'i';
            $insertParams[] = $facturarEmpresaId > 0 ? $facturarEmpresaId : null;
        }
        if (!empty($ordenExtraColumns['firma_doctor'])) {
            $insertColumns[] = 'firma_doctor';
            $insertValues[] = '?';
            $insertTypes .= 's';
            $insertParams[] = $firmaDoctor;
        }
        $insertColumns = array_merge($insertColumns, [
            'medico_responsable',
            'medico_responsable_id',
            'medico_nombre_snapshot',
            'medico_especialidad_snapshot',
            'medico_cmp_snapshot',
            'medico_rne_snapshot',
            'medico_rna_snapshot',
            'medico_firma_snapshot',
        ]);
        $insertValues = array_merge($insertValues, ['?', '?', '?', '?', '?', '?', '?', '?']);
        $insertTypes .= 'sissssss';
        array_push(
            $insertParams,
            $medicoResponsable['nombre'],
            $medicoResponsable['id'],
            $medicoResponsable['nombre'],
            $medicoResponsable['especialidad'],
            $medicoResponsable['cmp'],
            $medicoResponsable['rne'],
            $medicoResponsable['rna'],
            $medicoResponsable['firma']
        );
        if (!empty($ordenExtraColumns['modo'])) {
            $insertColumns[] = 'modo';
            $insertValues[] = '?';
            $insertTypes .= 's';
            $insertParams[] = $modo;
        }
        if (!empty($ordenExtraColumns['gestante'])) {
            $insertColumns[] = 'gestante';
            $insertValues[] = '?';
            $insertTypes .= 'i';
            $insertParams[] = $gestante;
        }
        if (!empty($ordenExtraColumns['documento'])) {
            $insertColumns[] = 'documento';
            $insertValues[] = '?';
            $insertTypes .= 's';
            $insertParams[] = $documento;
        }
        if (!empty($ordenExtraColumns['indica_dr'])) {
            $insertColumns[] = 'indica_dr';
            $insertValues[] = '?';
            $insertTypes .= 's';
            $insertParams[] = $indicaDr;
        }

        $sqlInsert = 'INSERT INTO ocupacional_ordenes (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';
        $stmtIns = $mysqliOcup->prepare($sqlInsert);
        if (!$stmtIns) {
            throw new Exception('No se pudo preparar insercion de orden');
        }
        bind_params_dynamic_orden($stmtIns, $insertTypes, $insertParams);
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

        $detalleSnapshotReady = !empty($detalleExtraColumns['grupo_snapshot'])
            && !empty($detalleExtraColumns['subgrupo_snapshot'])
            && !empty($detalleExtraColumns['grupo_orden_snapshot'])
            && !empty($detalleExtraColumns['examen_orden_snapshot']);
        $sqlDetalle = $detalleSnapshotReady
            ? 'INSERT INTO ocupacional_orden_detalle
               (orden_id, catalogo_id, examen_id, examen_codigo, examen_descripcion, grupo_snapshot, subgrupo_snapshot, grupo_orden_snapshot, examen_orden_snapshot, monto)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            : 'INSERT INTO ocupacional_orden_detalle
               (orden_id, catalogo_id, examen_id, examen_codigo, examen_descripcion, monto)
               VALUES (?, ?, ?, ?, ?, ?)';
        $stmtDet = $mysqliOcup->prepare($sqlDetalle);
        if (!$stmtDet) {
            throw new Exception('No se pudo preparar insercion de detalle');
        }

        foreach ($itemsAplican as $item) {
            $catalogoId = (int)$item['catalogo_id'];
            $examenId = (int)$item['examen_id'];
            $codigoEx = (string)$item['codigo'];
            $descEx = (string)$item['descripcion'];
            $grupoEx = (string)($item['grupo'] ?? '');
            $subgrupoEx = (string)($item['subgrupo'] ?? '');
            $grupoOrdenEx = (int)($item['grupo_orden'] ?? 0);
            $examenOrdenEx = (int)($item['examen_orden'] ?? 0);
            $monto = (float)$item['monto'];
            if ($detalleSnapshotReady) {
                $stmtDet->bind_param('iiissssiid', $ordenId, $catalogoId, $examenId, $codigoEx, $descEx, $grupoEx, $subgrupoEx, $grupoOrdenEx, $examenOrdenEx, $monto);
            } else {
                $stmtDet->bind_param('iiissd', $ordenId, $catalogoId, $examenId, $codigoEx, $descEx, $monto);
            }
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
                'subcontrata_empresa_id' => $subcontrataEmpresaId,
                'facturar_empresa_id' => $facturarEmpresaId,
                'firma_doctor' => $firmaDoctor,
                'medico_responsable_id' => $medicoResponsable['id'],
                'medico_responsable_id' => $medicoResponsable['id'],
                'modo' => $modo,
                'gestante' => $gestante,
                'documento' => $documento,
                'indica_dr' => $indicaDr,
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
                'subcontrata_empresa_id' => $subcontrataEmpresaId,
                'facturar_empresa_id' => $facturarEmpresaId,
                'firma_doctor' => $firmaDoctor,
                'modo' => $modo,
                'gestante' => $gestante,
                'documento' => $documento,
                'indica_dr' => $indicaDr,
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

        $stmt = $mysqliOcup->prepare('SELECT o.id, o.estado,
                                                                                EXISTS (
                                                                                        SELECT 1
                                                                                        FROM ocupacional_orden_eventos ev
                                                                                        WHERE ev.orden_id = o.id
                                                                                            AND ev.tipo_evento = "certificado_emitido"
                                                                                ) AS certificado_emitido
                                                                    FROM ocupacional_ordenes o
                                                                    WHERE o.id = ? LIMIT 1');
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
    if (in_array((string)$row['estado'], ['completada', 'cerrada'], true)) {
        out_orden(422, ['success' => false, 'error' => 'No se puede anular una orden completada o cerrada']);
    }
    if ((int)($row['certificado_emitido'] ?? 0) === 1) {
        out_orden(422, ['success' => false, 'error' => 'No se puede anular una orden con certificado emitido']);
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

    $resumenClinico = require_orden_clinicamente_finalizada($mysqliOcup, $ordenId);

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
            'total_items' => $resumenClinico['total'],
            'total_finalizados' => $resumenClinico['finalizados'],
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
    $medicoResponsableId = (int)($payload['medico_responsable_id'] ?? 0);
    $aptitudesValidas = ['APTO', 'APTO_CON_RESTRICCIONES', 'NO_APTO'];

    if ($ordenId <= 0 || !in_array($aptitudFinal, $aptitudesValidas, true)) {
        out_orden(422, ['success' => false, 'error' => 'id y aptitud_final valida son obligatorios']);
    }
    if (!$medicoSnapshotReady) {
        out_orden(500, ['success' => false, 'error' => 'Falta aplicar migracion 20260725_0020 de medico responsable']);
    }
    $medicoResponsable = require_medico_responsable_orden($mysqli, $medicoResponsableId);
    if ($aptitudFinal === 'APTO_CON_RESTRICCIONES' && $restriccionFinal === '') {
        out_orden(422, ['success' => false, 'error' => 'Debe registrar restricciones para la aptitud seleccionada']);
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
    if (!in_array((string)$orden['estado'], ['completada', 'cerrada'], true)) {
        out_orden(422, ['success' => false, 'error' => 'La aptitud final solo puede guardarse en una orden completada o cerrada']);
    }
    $resumenClinico = require_orden_clinicamente_finalizada($mysqliOcup, $ordenId);
    $cerradaAlGuardarAptitud = (string)$orden['estado'] === 'completada';

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_ordenes
                                    SET aptitud_final = ?,
                                        restriccion_final = ?,
                                        recomendacion_final = ?,
                                        medico_responsable = ?,
                                        medico_responsable_id = ?,
                                        medico_nombre_snapshot = ?,
                                        medico_especialidad_snapshot = ?,
                                        medico_cmp_snapshot = ?,
                                        medico_rne_snapshot = ?,
                                        medico_rna_snapshot = ?,
                                        medico_firma_snapshot = ?,
                                        aptitud_registrada_by = ?,
                                        aptitud_registrada_at = CURRENT_TIMESTAMP,
                                        estado = CASE WHEN estado = "completada" THEN "cerrada" ELSE estado END,
                                        updated_by = ?,
                                        updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? LIMIT 1');
    if (!$stmtUp) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo guardar aptitud final']);
    }
    $stmtUp->bind_param(
        'ssssissssssiii',
        $aptitudFinal,
        $restriccionFinal,
        $recomendacionFinal,
        $medicoResponsable['nombre'],
        $medicoResponsable['id'],
        $medicoResponsable['nombre'],
        $medicoResponsable['especialidad'],
        $medicoResponsable['cmp'],
        $medicoResponsable['rne'],
        $medicoResponsable['rna'],
        $medicoResponsable['firma'],
        $usuarioId,
        $usuarioId,
        $ordenId
    );
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
            'medico_responsable_id' => $medicoResponsable['id'],
            'medico_responsable' => $medicoResponsable['nombre'],
            'medico_cmp' => $medicoResponsable['cmp'],
            'medico_rne' => $medicoResponsable['rne'],
            'medico_rna' => $medicoResponsable['rna'],
        ]
    );

    if ($cerradaAlGuardarAptitud) {
        registrar_evento_orden(
            $mysqliOcup,
            $ordenId,
            'orden_cerrada',
            'Orden cerrada al registrar aptitud final',
            $usuarioId,
            [
                'total_examenes' => $resumenClinico['total'],
                'resultados_finalizados' => $resumenClinico['finalizados'],
                'aptitud_final' => $aptitudFinal,
            ]
        );
    }

    out_orden(200, [
        'success' => true,
        'data' => [
            'id' => $ordenId,
            'aptitud_final' => $aptitudFinal,
            'estado' => 'cerrada',
            'cerrada_al_guardar_aptitud' => $cerradaAlGuardarAptitud,
        ],
    ]);
}

if ($accion === 'registrar_emision_certificado_orden') {
    require_ocup_permiso_any_orden(['cerrar_ordenes_ocupacional', 'emitir_certificados_ocupacional']);
    $ordenId = (int)($payload['id'] ?? 0);
    $formato = trim((string)($payload['formato'] ?? 'pdf'));

    if ($ordenId <= 0) {
        out_orden(422, ['success' => false, 'error' => 'id de orden es obligatorio']);
    }
    if (!$medicoSnapshotReady) {
        out_orden(500, ['success' => false, 'error' => 'Falta aplicar migracion 20260725_0020 de medico responsable']);
    }

    $stmt = $mysqliOcup->prepare('SELECT id, codigo, estado, aptitud_final, restriccion_final,
                                         medico_responsable_id, medico_nombre_snapshot,
                                         medico_cmp_snapshot, medico_firma_snapshot
                                  FROM ocupacional_ordenes
                                  WHERE id = ? LIMIT 1');
    if (!$stmt) {
        out_orden(500, ['success' => false, 'error' => 'No se pudo validar orden para registrar certificado']);
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $orden = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orden) {
        out_orden(404, ['success' => false, 'error' => 'Orden no encontrada']);
    }
    if ((string)$orden['estado'] !== 'cerrada') {
        out_orden(422, ['success' => false, 'error' => 'El certificado solo puede emitirse para orden cerrada']);
    }
    if (trim((string)($orden['aptitud_final'] ?? '')) === '') {
        out_orden(422, ['success' => false, 'error' => 'Debe registrar aptitud final antes de emitir certificado']);
    }
    if ((int)($orden['medico_responsable_id'] ?? 0) <= 0
        || trim((string)($orden['medico_nombre_snapshot'] ?? '')) === ''
        || trim((string)($orden['medico_cmp_snapshot'] ?? '')) === ''
        || trim((string)($orden['medico_firma_snapshot'] ?? '')) === '') {
        out_orden(422, ['success' => false, 'error' => 'Debe registrar un medico responsable con CMP y firma antes de emitir certificado']);
    }
    if ((string)$orden['aptitud_final'] === 'APTO_CON_RESTRICCIONES'
        && trim((string)($orden['restriccion_final'] ?? '')) === '') {
        out_orden(422, ['success' => false, 'error' => 'Debe registrar las restricciones antes de emitir certificado']);
    }
    require_orden_clinicamente_finalizada($mysqliOcup, $ordenId);

    registrar_evento_orden(
        $mysqliOcup,
        $ordenId,
        'certificado_emitido',
        'Certificado de aptitud ocupacional emitido',
        $usuarioId,
        [
            'codigo' => (string)($orden['codigo'] ?? ''),
            'formato' => $formato !== '' ? $formato : 'pdf',
        ]
    );

    out_orden(200, [
        'success' => true,
        'data' => [
            'id' => $ordenId,
            'codigo' => (string)($orden['codigo'] ?? ''),
            'formato' => $formato !== '' ? $formato : 'pdf',
        ],
    ]);
}

if ($accion === 'actualizar_detalle_orden') {
    require_ocup_permiso_any_orden(['ejecutar_ordenes_ocupacional']);
    $detalleId = (int)($payload['detalle_id'] ?? 0);
    $estadoEjecucion = trim((string)($payload['estado_ejecucion'] ?? ''));
    $observacionEjecucion = trim((string)($payload['observacion_ejecucion'] ?? ''));
    $estadosValidos = ['pendiente', 'en_proceso', 'observado'];

    if ($detalleId <= 0 || !in_array($estadoEjecucion, $estadosValidos, true)) {
        out_orden(422, ['success' => false, 'error' => 'detalle_id y estado_ejecucion valido son obligatorios']);
    }
    if ($estadoEjecucion === 'observado' && $observacionEjecucion === '') {
        out_orden(422, ['success' => false, 'error' => 'La observacion es obligatoria para marcar un examen como observado']);
    }

    $stmt = $mysqliOcup->prepare('SELECT d.id, d.orden_id, d.estado_ejecucion, o.estado AS estado_orden
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
    if ((string)$row['estado_ejecucion'] === 'observado' && $estadoEjecucion !== 'observado') {
        out_orden(422, ['success' => false, 'error' => 'Use el levantamiento o anulacion de interconsulta para liberar un examen observado']);
    }

    $fechaEjecucion = $estadoEjecucion === 'observado' ? date('Y-m-d H:i:s') : null;
    $ordenId = (int)$row['orden_id'];
    $mysqliOcup->begin_transaction();
    try {
        $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_orden_detalle
                                        SET estado_ejecucion = ?,
                                            observacion_ejecucion = ?,
                                            fecha_ejecucion = ?,
                                            updated_by = ?,
                                            updated_at = CURRENT_TIMESTAMP
                                        WHERE id = ? LIMIT 1');
        if (!$stmtUp) {
            throw new Exception('No se pudo preparar detalle de orden');
        }
        $stmtUp->bind_param('sssii', $estadoEjecucion, $observacionEjecucion, $fechaEjecucion, $usuarioId, $detalleId);
        $stmtUp->execute();
        $stmtUp->close();

        if ($estadoEjecucion === 'observado') {
            $stmtResultados = $mysqliOcup->prepare('UPDATE ocupacional_resultados_clinicos
                                                    SET estado = "borrador", updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                                    WHERE orden_detalle_id = ? AND estado = "finalizado"');
            if (!$stmtResultados) {
                throw new Exception('No se pudo invalidar resultado observado');
            }
            $stmtResultados->bind_param('ii', $usuarioId, $detalleId);
            $stmtResultados->execute();
            $stmtResultados->close();
        }

        registrar_evento_orden(
            $mysqliOcup,
            $ordenId,
            'detalle_actualizado',
            $estadoEjecucion === 'observado' ? 'Examen observado; resultado clinico devuelto a borrador' : 'Actualizacion de estado de examen',
            $usuarioId,
            [
                'detalle_id' => $detalleId,
                'estado_ejecucion' => $estadoEjecucion,
                'observacion' => $observacionEjecucion,
            ]
        );
        sync_estado_orden_por_detalle($mysqliOcup, $ordenId, $usuarioId);
        $mysqliOcup->commit();
    } catch (Throwable $e) {
        $mysqliOcup->rollback();
        out_orden(500, ['success' => false, 'error' => 'No se pudo actualizar detalle de orden']);
    }

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
