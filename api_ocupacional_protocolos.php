<?php

require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/db_ocupacional.php';

function out_proto($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function parse_session_permisos_proto()
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

function require_ocup_permiso_proto($permiso)
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out_proto(401, ['success' => false, 'error' => 'No autenticado']);
    }

    $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }

    $permisos = parse_session_permisos_proto();
    if (!in_array('access_salud_ocupacional', $permisos, true) || !in_array($permiso, $permisos, true)) {
        out_proto(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
    }
}

function bind_params_dynamic_proto($stmt, $types, $params)
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

function table_exists_proto($conn, $table)
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

$requiredTables = [
    'empresas_ocupacionales',
    'ocupacional_catalogo_empresas',
    'ocupacional_examenes_generales',
    'ocupacional_tipos_evaluacion',
    'ocupacional_protocolos_empresa',
    'ocupacional_protocolo_detalle',
    'ocupacional_protocolo_condiciones',
    'pacientes_ocupacionales',
];

foreach ($requiredTables as $table) {
    if (!table_exists_proto($mysqliOcup, $table)) {
        out_proto(500, [
            'success' => false,
            'error' => 'Falta la tabla ' . $table . '. Aplicar sql/2026-06-15_ocupacional_fase2_protocolos.sql en la base ocupacional.',
        ]);
    }
}

function listar_tipos_evaluacion_proto($conn)
{
    $sql = 'SELECT id, codigo, nombre, orden FROM ocupacional_tipos_evaluacion WHERE estado = "activo" ORDER BY orden ASC, id ASC';
    $res = $conn->query($sql);
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'codigo' => (string)$r['codigo'],
                'nombre' => (string)$r['nombre'],
                'orden' => (int)$r['orden'],
            ];
        }
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_ocup_permiso_proto('gestionar_empresas_ocupacional');

    $accion = trim((string)($_GET['accion'] ?? 'tipos'));

    if ($accion === 'tipos') {
        out_proto(200, ['success' => true, 'data' => listar_tipos_evaluacion_proto($mysqliOcup)]);
    }

    if ($accion === 'listar_protocolos') {
        $empresaId = (int)($_GET['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            out_proto(422, ['success' => false, 'error' => 'empresa_id es obligatorio']);
        }

        $estado = trim((string)($_GET['estado'] ?? 'activo'));
        if (!in_array($estado, ['activo', 'inactivo', 'todos'], true)) {
            out_proto(422, ['success' => false, 'error' => 'estado invalido']);
        }

        $whereEstado = '';
        $types = 'i';
        $params = [$empresaId];
        if ($estado !== 'todos') {
            $whereEstado = ' AND estado = ?';
            $types .= 's';
            $params[] = $estado;
        }

        $stmt = $mysqliOcup->prepare('SELECT id, empresa_id, descripcion, estado, created_at, updated_at FROM ocupacional_protocolos_empresa WHERE empresa_id = ?' . $whereEstado . ' ORDER BY descripcion ASC, id DESC');
        if (!$stmt) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo listar protocolos']);
        }

        bind_params_dynamic_proto($stmt, $types, $params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'empresa_id' => (int)$r['empresa_id'],
                'descripcion' => (string)$r['descripcion'],
                'estado' => (string)$r['estado'],
                'created_at' => (string)($r['created_at'] ?? ''),
                'updated_at' => (string)($r['updated_at'] ?? ''),
            ];
        }
        $stmt->close();

        out_proto(200, ['success' => true, 'data' => $rows]);
    }

    if ($accion === 'listar_matriz') {
        $empresaId = (int)($_GET['empresa_id'] ?? 0);
        $protocoloId = (int)($_GET['protocolo_id'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 50);

        if ($empresaId <= 0 || $protocoloId <= 0) {
            out_proto(422, ['success' => false, 'error' => 'empresa_id y protocolo_id son obligatorios']);
        }

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $offset = ($page - 1) * $perPage;

        $where = [
            'c.empresa_id = ?',
            'c.estado = "activo"',
            'e.estado = "activo"',
        ];
        $types = 'i';
        $params = [$empresaId];

        if ($q !== '') {
            $where[] = '(e.codigo LIKE ? OR e.descripcion LIKE ? OR e.grupo LIKE ? OR e.subgrupo LIKE ?)';
            $term = '%' . $q . '%';
            $types .= 'ssss';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $sqlCount = 'SELECT COUNT(*) AS total
                     FROM ocupacional_catalogo_empresas c
                     INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id'
                     . $whereSql;
        $stmtCount = $mysqliOcup->prepare($sqlCount);
        if (!$stmtCount) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo preparar conteo de matriz']);
        }
        bind_params_dynamic_proto($stmtCount, $types, $params);
        $stmtCount->execute();
        $resCount = $stmtCount->get_result();
        $rowCount = $resCount ? $resCount->fetch_assoc() : ['total' => 0];
        $total = (int)($rowCount['total'] ?? 0);
        $stmtCount->close();

        $sqlRows = 'SELECT
                        c.id AS catalogo_id,
                        c.examen_id,
                        e.codigo,
                        e.descripcion,
                        e.grupo,
                        e.subgrupo,
                        e.precio
                    FROM ocupacional_catalogo_empresas c
                    INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id'
                    . $whereSql
                    . ' ORDER BY e.grupo ASC, e.subgrupo ASC, e.descripcion ASC, e.id DESC LIMIT ? OFFSET ?';
        $stmtRows = $mysqliOcup->prepare($sqlRows);
        if (!$stmtRows) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo preparar listado de matriz']);
        }
        $typesRows = $types . 'ii';
        $paramsRows = $params;
        $paramsRows[] = $perPage;
        $paramsRows[] = $offset;
        bind_params_dynamic_proto($stmtRows, $typesRows, $paramsRows);
        $stmtRows->execute();
        $resRows = $stmtRows->get_result();

        $items = [];
        $catalogoIds = [];
        while ($r = $resRows->fetch_assoc()) {
            $catalogoId = (int)$r['catalogo_id'];
            $catalogoIds[] = $catalogoId;
            $items[$catalogoId] = [
                'catalogo_id' => $catalogoId,
                'examen_id' => (int)$r['examen_id'],
                'codigo' => (string)$r['codigo'],
                'descripcion' => (string)$r['descripcion'],
                'grupo' => (string)($r['grupo'] ?? ''),
                'subgrupo' => (string)($r['subgrupo'] ?? ''),
                'precio' => (float)($r['precio'] ?? 0),
                'montos' => [],
            ];
        }
        $stmtRows->close();

        $tipos = listar_tipos_evaluacion_proto($mysqliOcup);
        foreach ($items as &$row) {
            foreach ($tipos as $t) {
                $row['montos'][(string)$t['id']] = '';
            }
        }
        unset($row);

        if (!empty($catalogoIds)) {
            $placeholders = implode(',', array_fill(0, count($catalogoIds), '?'));
            $typesDet = 'i' . str_repeat('i', count($catalogoIds));
            $paramsDet = array_merge([$protocoloId], $catalogoIds);

            $sqlDet = 'SELECT catalogo_id, tipo_evaluacion_id, monto
                       FROM ocupacional_protocolo_detalle
                       WHERE protocolo_id = ? AND catalogo_id IN (' . $placeholders . ')';
            $stmtDet = $mysqliOcup->prepare($sqlDet);
            if ($stmtDet) {
                bind_params_dynamic_proto($stmtDet, $typesDet, $paramsDet);
                $stmtDet->execute();
                $resDet = $stmtDet->get_result();
                while ($d = $resDet->fetch_assoc()) {
                    $cId = (int)$d['catalogo_id'];
                    $tId = (int)$d['tipo_evaluacion_id'];
                    if (isset($items[$cId])) {
                        $items[$cId]['montos'][(string)$tId] = number_format((float)$d['monto'], 2, '.', '');
                    }
                }
                $stmtDet->close();
            }
        }

        $totales = [];
        foreach ($tipos as $t) {
            $totales[(string)$t['id']] = '0.00';
        }

        $sqlTot = 'SELECT pd.tipo_evaluacion_id, COALESCE(SUM(pd.monto), 0) AS total
                   FROM ocupacional_protocolo_detalle pd
                   INNER JOIN ocupacional_catalogo_empresas c ON c.id = pd.catalogo_id
                   INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
                   WHERE pd.protocolo_id = ?
                     AND c.empresa_id = ?
                     AND c.estado = "activo"
                     AND e.estado = "activo"
                   GROUP BY pd.tipo_evaluacion_id';
        $stmtTot = $mysqliOcup->prepare($sqlTot);
        if ($stmtTot) {
            $stmtTot->bind_param('ii', $protocoloId, $empresaId);
            $stmtTot->execute();
            $resTot = $stmtTot->get_result();
            while ($tt = $resTot->fetch_assoc()) {
                $totales[(string)((int)$tt['tipo_evaluacion_id'])] = number_format((float)$tt['total'], 2, '.', '');
            }
            $stmtTot->close();
        }

        out_proto(200, [
            'success' => true,
            'tipos' => $tipos,
            'data' => array_values($items),
            'totales' => $totales,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ]);
    }

    if ($accion === 'listar_puestos') {
        $empresaId = (int)($_GET['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            out_proto(422, ['success' => false, 'error' => 'empresa_id es obligatorio']);
        }

        $sql = 'SELECT DISTINCT puesto_trabajo
                FROM pacientes_ocupacionales
                WHERE empresa_id = ?
                  AND estado_laboral = "activo"
                  AND puesto_trabajo IS NOT NULL
                  AND TRIM(puesto_trabajo) <> ""
                ORDER BY puesto_trabajo ASC';
        $stmt = $mysqliOcup->prepare($sql);
        if (!$stmt) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo listar puestos']);
        }
        $stmt->bind_param('i', $empresaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'puesto_trabajo' => (string)$r['puesto_trabajo'],
            ];
        }
        $stmt->close();

        out_proto(200, ['success' => true, 'data' => $rows]);
    }

    if ($accion === 'listar_condiciones') {
        $protocoloId = (int)($_GET['protocolo_id'] ?? 0);
        $catalogoId = (int)($_GET['catalogo_id'] ?? 0);
        if ($protocoloId <= 0 || $catalogoId <= 0) {
            out_proto(422, ['success' => false, 'error' => 'protocolo_id y catalogo_id son obligatorios']);
        }

        $sql = 'SELECT id, protocolo_id, catalogo_id, puesto_trabajo, sexo, edad_min, edad_max, created_at, updated_at
                FROM ocupacional_protocolo_condiciones
                WHERE protocolo_id = ? AND catalogo_id = ?
                ORDER BY id DESC';
        $stmt = $mysqliOcup->prepare($sql);
        if (!$stmt) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo listar condiciones']);
        }
        $stmt->bind_param('ii', $protocoloId, $catalogoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'protocolo_id' => (int)$r['protocolo_id'],
                'catalogo_id' => (int)$r['catalogo_id'],
                'puesto_trabajo' => (string)($r['puesto_trabajo'] ?? ''),
                'sexo' => (string)($r['sexo'] ?? ''),
                'edad_min' => isset($r['edad_min']) ? (int)$r['edad_min'] : null,
                'edad_max' => isset($r['edad_max']) ? (int)$r['edad_max'] : null,
                'created_at' => (string)($r['created_at'] ?? ''),
                'updated_at' => (string)($r['updated_at'] ?? ''),
            ];
        }
        $stmt->close();

        out_proto(200, ['success' => true, 'data' => $rows]);
    }

    out_proto(422, ['success' => false, 'error' => 'accion GET no soportada']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_proto(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

require_ocup_permiso_proto('gestionar_empresas_ocupacional');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$accion = trim((string)($payload['accion'] ?? ''));
$usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;

if ($accion === 'guardar_protocolo') {
    $id = (int)($payload['id'] ?? 0);
    $empresaId = (int)($payload['empresa_id'] ?? 0);
    $descripcion = trim((string)($payload['descripcion'] ?? ''));

    if ($empresaId <= 0 || $descripcion === '') {
        out_proto(422, ['success' => false, 'error' => 'empresa_id y descripcion son obligatorios']);
    }

    if ($id > 0) {
        $stmtDup = $mysqliOcup->prepare('SELECT id FROM ocupacional_protocolos_empresa WHERE empresa_id = ? AND descripcion = ? AND id <> ? LIMIT 1');
        if (!$stmtDup) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo validar protocolo']);
        }
        $stmtDup->bind_param('isi', $empresaId, $descripcion, $id);
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();
        if ($dup) {
            out_proto(409, ['success' => false, 'error' => 'Ya existe un protocolo con esa descripcion en la empresa']);
        }

        $stmt = $mysqliOcup->prepare('UPDATE ocupacional_protocolos_empresa SET descripcion = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ? LIMIT 1');
        if (!$stmt) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo actualizar protocolo']);
        }
        $stmt->bind_param('siii', $descripcion, $usuarioId, $id, $empresaId);
        $stmt->execute();
        $stmt->close();

        out_proto(200, [
            'success' => true,
            'data' => ['id' => $id, 'empresa_id' => $empresaId, 'descripcion' => $descripcion],
        ]);
    }

    $stmtDup = $mysqliOcup->prepare('SELECT id FROM ocupacional_protocolos_empresa WHERE empresa_id = ? AND descripcion = ? LIMIT 1');
    if (!$stmtDup) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo validar protocolo']);
    }
    $stmtDup->bind_param('is', $empresaId, $descripcion);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) {
        out_proto(409, ['success' => false, 'error' => 'Ya existe un protocolo con esa descripcion en la empresa']);
    }

    $stmt = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolos_empresa (empresa_id, descripcion, estado, created_by, updated_by) VALUES (?, ?, "activo", ?, ?)');
    if (!$stmt) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo registrar protocolo']);
    }
    $stmt->bind_param('isii', $empresaId, $descripcion, $usuarioId, $usuarioId);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    out_proto(201, [
        'success' => true,
        'data' => ['id' => $newId, 'empresa_id' => $empresaId, 'descripcion' => $descripcion, 'estado' => 'activo'],
    ]);
}

if ($accion === 'inactivar_protocolo') {
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) {
        out_proto(422, ['success' => false, 'error' => 'id es obligatorio']);
    }

    $stmt = $mysqliOcup->prepare('UPDATE ocupacional_protocolos_empresa SET estado = "inactivo", updated_by = ?, updated_at = NOW() WHERE id = ? AND estado <> "inactivo" LIMIT 1');
    if (!$stmt) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo inactivar protocolo']);
    }
    $stmt->bind_param('ii', $usuarioId, $id);
    $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        out_proto(404, ['success' => false, 'error' => 'Protocolo no encontrado o ya inactivo']);
    }

    out_proto(200, ['success' => true, 'message' => 'Protocolo inactivado']);
}

if ($accion === 'guardar_monto') {
    $protocoloId = (int)($payload['protocolo_id'] ?? 0);
    $catalogoId = (int)($payload['catalogo_id'] ?? 0);
    $tipoEvaluacionId = (int)($payload['tipo_evaluacion_id'] ?? 0);
    $montoRaw = isset($payload['monto']) ? trim((string)$payload['monto']) : '';

    if ($protocoloId <= 0 || $catalogoId <= 0 || $tipoEvaluacionId <= 0) {
        out_proto(422, ['success' => false, 'error' => 'protocolo_id, catalogo_id y tipo_evaluacion_id son obligatorios']);
    }

    if ($montoRaw === '') {
        $stmtDel = $mysqliOcup->prepare('DELETE FROM ocupacional_protocolo_detalle WHERE protocolo_id = ? AND catalogo_id = ? AND tipo_evaluacion_id = ? LIMIT 1');
        if (!$stmtDel) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo limpiar monto']);
        }
        $stmtDel->bind_param('iii', $protocoloId, $catalogoId, $tipoEvaluacionId);
        $stmtDel->execute();
        $stmtDel->close();

        out_proto(200, [
            'success' => true,
            'data' => [
                'protocolo_id' => $protocoloId,
                'catalogo_id' => $catalogoId,
                'tipo_evaluacion_id' => $tipoEvaluacionId,
                'monto' => '',
            ],
        ]);
    }

    $montoNorm = str_replace(',', '.', $montoRaw);
    if (!is_numeric($montoNorm)) {
        out_proto(422, ['success' => false, 'error' => 'monto invalido']);
    }
    $monto = round((float)$montoNorm, 2);
    if ($monto < 0) {
        out_proto(422, ['success' => false, 'error' => 'monto no puede ser negativo']);
    }

    $stmtUp = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolo_detalle (protocolo_id, catalogo_id, tipo_evaluacion_id, monto, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE monto = VALUES(monto), updated_by = VALUES(updated_by), updated_at = NOW()');
    if (!$stmtUp) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo guardar monto']);
    }
    $stmtUp->bind_param('iiidii', $protocoloId, $catalogoId, $tipoEvaluacionId, $monto, $usuarioId, $usuarioId);
    $stmtUp->execute();
    $stmtUp->close();

    out_proto(200, [
        'success' => true,
        'data' => [
            'protocolo_id' => $protocoloId,
            'catalogo_id' => $catalogoId,
            'tipo_evaluacion_id' => $tipoEvaluacionId,
            'monto' => number_format($monto, 2, '.', ''),
        ],
    ]);
}

if ($accion === 'guardar_condicion') {
    $id = (int)($payload['id'] ?? 0);
    $protocoloId = (int)($payload['protocolo_id'] ?? 0);
    $catalogoId = (int)($payload['catalogo_id'] ?? 0);
    $puestoTrabajo = trim((string)($payload['puesto_trabajo'] ?? ''));
    $sexo = strtoupper(trim((string)($payload['sexo'] ?? '')));
    $edadMinRaw = trim((string)($payload['edad_min'] ?? ''));
    $edadMaxRaw = trim((string)($payload['edad_max'] ?? ''));

    if ($protocoloId <= 0 || $catalogoId <= 0) {
        out_proto(422, ['success' => false, 'error' => 'protocolo_id y catalogo_id son obligatorios']);
    }

    if ($sexo !== '' && $sexo !== 'M' && $sexo !== 'F') {
        out_proto(422, ['success' => false, 'error' => 'sexo invalido']);
    }

    $edadMin = null;
    if ($edadMinRaw !== '') {
        if (!ctype_digit($edadMinRaw)) {
            out_proto(422, ['success' => false, 'error' => 'edad_min invalida']);
        }
        $edadMin = (int)$edadMinRaw;
    }

    $edadMax = null;
    if ($edadMaxRaw !== '') {
        if (!ctype_digit($edadMaxRaw)) {
            out_proto(422, ['success' => false, 'error' => 'edad_max invalida']);
        }
        $edadMax = (int)$edadMaxRaw;
    }

    if (($puestoTrabajo === '') && ($sexo === '') && $edadMin === null && $edadMax === null) {
        out_proto(422, ['success' => false, 'error' => 'Debe ingresar al menos un criterio: puesto, sexo o rango de edad']);
    }

    if ($edadMin !== null && ($edadMin < 0 || $edadMin > 120)) {
        out_proto(422, ['success' => false, 'error' => 'edad_min fuera de rango']);
    }
    if ($edadMax !== null && ($edadMax < 0 || $edadMax > 120)) {
        out_proto(422, ['success' => false, 'error' => 'edad_max fuera de rango']);
    }
    if ($edadMin !== null && $edadMax !== null && $edadMin > $edadMax) {
        out_proto(422, ['success' => false, 'error' => 'edad_min no puede ser mayor que edad_max']);
    }

        $edadMinCmp = $edadMin === null ? -1 : $edadMin;
        $edadMaxCmp = $edadMax === null ? -1 : $edadMax;

    $puestoSave = $puestoTrabajo === '' ? null : $puestoTrabajo;
    $sexoSave = $sexo === '' ? null : $sexo;

        if ($id > 0) {
            $stmtExists = $mysqliOcup->prepare('SELECT id FROM ocupacional_protocolo_condiciones WHERE id = ? AND protocolo_id = ? AND catalogo_id = ? LIMIT 1');
            if (!$stmtExists) {
                out_proto(500, ['success' => false, 'error' => 'No se pudo validar condicion existente']);
            }
            $stmtExists->bind_param('iii', $id, $protocoloId, $catalogoId);
            $stmtExists->execute();
            $exists = $stmtExists->get_result()->fetch_assoc();
            $stmtExists->close();
            if (!$exists) {
                out_proto(404, ['success' => false, 'error' => 'Condicion no encontrada']);
            }

            $stmtDup = $mysqliOcup->prepare('SELECT id
                                             FROM ocupacional_protocolo_condiciones
                                             WHERE protocolo_id = ?
                                               AND catalogo_id = ?
                                               AND COALESCE(puesto_trabajo, "") = ?
                                               AND COALESCE(sexo, "") = ?
                                               AND IFNULL(edad_min, -1) = ?
                                               AND IFNULL(edad_max, -1) = ?
                                               AND id <> ?
                                             LIMIT 1');
            if (!$stmtDup) {
                out_proto(500, ['success' => false, 'error' => 'No se pudo validar duplicidad de condicion']);
            }
            $stmtDup->bind_param('iissiii', $protocoloId, $catalogoId, $puestoTrabajo, $sexo, $edadMinCmp, $edadMaxCmp, $id);
            $stmtDup->execute();
            $dup = $stmtDup->get_result()->fetch_assoc();
            $stmtDup->close();
            if ($dup) {
                out_proto(409, ['success' => false, 'error' => 'La condicion ya existe']);
            }

            $stmt = $mysqliOcup->prepare('UPDATE ocupacional_protocolo_condiciones
                                          SET puesto_trabajo = ?, sexo = ?, edad_min = ?, edad_max = ?, updated_by = ?, updated_at = NOW()
                                          WHERE id = ? LIMIT 1');
            if (!$stmt) {
                out_proto(500, ['success' => false, 'error' => 'No se pudo actualizar condicion']);
            }
            $stmt->bind_param('ssiiii', $puestoSave, $sexoSave, $edadMin, $edadMax, $usuarioId, $id);
            $stmt->execute();
            $stmt->close();

            out_proto(200, [
                'success' => true,
                'data' => [
                    'id' => $id,
                    'protocolo_id' => $protocoloId,
                    'catalogo_id' => $catalogoId,
                    'puesto_trabajo' => $puestoSave,
                    'sexo' => $sexoSave,
                    'edad_min' => $edadMin,
                    'edad_max' => $edadMax,
                ],
            ]);
        }

        $stmtDup = $mysqliOcup->prepare('SELECT id
                                         FROM ocupacional_protocolo_condiciones
                                         WHERE protocolo_id = ?
                                           AND catalogo_id = ?
                                           AND COALESCE(puesto_trabajo, "") = ?
                                           AND COALESCE(sexo, "") = ?
                                           AND IFNULL(edad_min, -1) = ?
                                           AND IFNULL(edad_max, -1) = ?
                                         LIMIT 1');
        if (!$stmtDup) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo validar condicion']);
        }
        $stmtDup->bind_param('iissii', $protocoloId, $catalogoId, $puestoTrabajo, $sexo, $edadMinCmp, $edadMaxCmp);
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();
        if ($dup) {
            out_proto(409, ['success' => false, 'error' => 'La condicion ya existe']);
        }

    $stmt = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolo_condiciones
                                  (protocolo_id, catalogo_id, puesto_trabajo, sexo, edad_min, edad_max, created_by, updated_by)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo guardar condicion']);
    }
    $stmt->bind_param('iissiiii', $protocoloId, $catalogoId, $puestoSave, $sexoSave, $edadMin, $edadMax, $usuarioId, $usuarioId);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    out_proto(201, [
        'success' => true,
        'data' => [
            'id' => $newId,
            'protocolo_id' => $protocoloId,
            'catalogo_id' => $catalogoId,
            'puesto_trabajo' => $puestoSave,
            'sexo' => $sexoSave,
            'edad_min' => $edadMin,
            'edad_max' => $edadMax,
        ],
    ]);
}

if ($accion === 'eliminar_condicion') {
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) {
        out_proto(422, ['success' => false, 'error' => 'id es obligatorio']);
    }

    $stmt = $mysqliOcup->prepare('DELETE FROM ocupacional_protocolo_condiciones WHERE id = ? LIMIT 1');
    if (!$stmt) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo eliminar condicion']);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        out_proto(404, ['success' => false, 'error' => 'Condicion no encontrada']);
    }

    out_proto(200, ['success' => true, 'message' => 'Condicion eliminada']);
}

out_proto(422, ['success' => false, 'error' => 'accion POST no soportada']);
