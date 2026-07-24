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

function listar_plantillas_condiciones_proto()
{
    return [
        [
            'codigo' => 'MAYOR_40_ECG',
            'nombre' => 'Mayores de 40 - Electrocardiograma',
            'descripcion' => 'Plantilla referencial editable para agregar electrocardiograma a pacientes desde 40 anios.',
            'modo' => 'legacy_aditivo',
            'reglas' => [
                [
                    'filtro_q' => 'electrocardiograma',
                    'puesto_trabajo' => '',
                    'sexo' => '',
                    'edad_min' => 40,
                    'edad_max' => null,
                ],
            ],
        ],
    ];
}

function parse_bool_proto($value)
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'si', 'yes', 'on'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_ocup_permiso_proto('gestionar_empresas_ocupacional');

    $accion = trim((string)($_GET['accion'] ?? 'tipos'));

    if ($accion === 'tipos') {
        out_proto(200, ['success' => true, 'data' => listar_tipos_evaluacion_proto($mysqliOcup)]);
    }

    if ($accion === 'listar_plantillas_condiciones') {
        out_proto(200, ['success' => true, 'data' => listar_plantillas_condiciones_proto()]);
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
                $row['montos'][(string)$t['id']] = [
                    'valor' => number_format((float)($row['precio'] ?? 0), 2, '.', ''),
                    'origen' => 'examen_general',
                ];
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
                        $montoValor = (float)$d['monto'];
                        $items[$cId]['montos'][(string)$tId] = [
                            'valor' => $montoValor > 0 ? number_format($montoValor, 2, '.', '') : '',
                            'origen' => $montoValor > 0 ? 'protocolo' : 'protocolo_excluido',
                        ];
                    }
                }
                $stmtDet->close();
            }
        }

        $totales = [];
        foreach ($tipos as $t) {
            $totales[(string)$t['id']] = '0.00';
        }
        foreach ($items as $row) {
            foreach ($tipos as $t) {
                $tipoKey = (string)$t['id'];
                $totales[$tipoKey] = number_format(
                    (float)$totales[$tipoKey] + (float)(($row['montos'][$tipoKey]['valor'] ?? 0)),
                    2,
                    '.',
                    ''
                );
            }
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
                                FROM (
                                        SELECT p.puesto_trabajo AS puesto_trabajo
                                        FROM pacientes_ocupacionales p
                                        WHERE p.empresa_id = ?
                                            AND p.estado_laboral = "activo"
                                            AND p.puesto_trabajo IS NOT NULL
                                            AND TRIM(p.puesto_trabajo) <> ""

                                        UNION

                                        SELECT c.puesto_trabajo AS puesto_trabajo
                                        FROM ocupacional_protocolo_condiciones c
                                        INNER JOIN ocupacional_protocolos_empresa pe ON pe.id = c.protocolo_id
                                        WHERE pe.empresa_id = ?
                                            AND c.puesto_trabajo IS NOT NULL
                                            AND TRIM(c.puesto_trabajo) <> ""
                                ) t
                                ORDER BY puesto_trabajo ASC';
        $stmt = $mysqliOcup->prepare($sql);
        if (!$stmt) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo listar puestos']);
        }
        $stmt->bind_param('ii', $empresaId, $empresaId);
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
    $sembrarMontosBase = parse_bool_proto($payload['sembrar_montos_base'] ?? true);

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

    $montosBaseSembrados = 0;
    if ($sembrarMontosBase) {
        $tipos = listar_tipos_evaluacion_proto($mysqliOcup);
        if (!empty($tipos)) {
            $stmtCatalogo = $mysqliOcup->prepare('SELECT c.id AS catalogo_id, e.precio
                                                  FROM ocupacional_catalogo_empresas c
                                                  INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
                                                  WHERE c.empresa_id = ?
                                                    AND c.estado = "activo"
                                                    AND e.estado = "activo"');
            if ($stmtCatalogo) {
                $stmtCatalogo->bind_param('i', $empresaId);
                $stmtCatalogo->execute();
                $resCatalogo = $stmtCatalogo->get_result();
                $catalogos = [];
                while ($row = $resCatalogo->fetch_assoc()) {
                    $catalogos[] = [
                        'catalogo_id' => (int)$row['catalogo_id'],
                        'precio' => (float)($row['precio'] ?? 0),
                    ];
                }
                $stmtCatalogo->close();

                if (!empty($catalogos)) {
                    $stmtSeed = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolo_detalle
                                                      (protocolo_id, catalogo_id, tipo_evaluacion_id, monto, created_by, updated_by)
                                                      VALUES (?, ?, ?, ?, ?, ?)');
                    if ($stmtSeed) {
                        foreach ($catalogos as $catalogo) {
                            foreach ($tipos as $tipo) {
                                $catalogoId = (int)$catalogo['catalogo_id'];
                                $tipoId = (int)$tipo['id'];
                                $monto = (float)$catalogo['precio'];
                                $stmtSeed->bind_param('iiidii', $newId, $catalogoId, $tipoId, $monto, $usuarioId, $usuarioId);
                                $stmtSeed->execute();
                                $montosBaseSembrados += ((int)$stmtSeed->affected_rows > 0) ? 1 : 0;
                            }
                        }
                        $stmtSeed->close();
                    }
                }
            }
        }
    }

    out_proto(201, [
        'success' => true,
        'data' => [
            'id' => $newId,
            'empresa_id' => $empresaId,
            'descripcion' => $descripcion,
            'estado' => 'activo',
            'sembrar_montos_base' => $sembrarMontosBase,
            'montos_base_sembrados' => $montosBaseSembrados,
        ],
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

if ($accion === 'copiar_configuracion_protocolo') {
    $empresaId = (int)($payload['empresa_id'] ?? 0);
    $protocoloOrigenId = (int)($payload['protocolo_origen_id'] ?? 0);
    $protocoloDestinoId = (int)($payload['protocolo_destino_id'] ?? 0);
    $copiarMontos = parse_bool_proto($payload['copiar_montos'] ?? true);
    $copiarCondiciones = parse_bool_proto($payload['copiar_condiciones'] ?? true);
    $soloPrevisualizar = parse_bool_proto($payload['solo_previsualizar'] ?? false);

    if ($empresaId <= 0 || $protocoloOrigenId <= 0 || $protocoloDestinoId <= 0) {
        out_proto(422, ['success' => false, 'error' => 'empresa_id, protocolo_origen_id y protocolo_destino_id son obligatorios']);
    }
    if ($protocoloOrigenId === $protocoloDestinoId) {
        out_proto(422, ['success' => false, 'error' => 'El protocolo origen debe ser distinto al destino']);
    }
    if (!$copiarMontos && !$copiarCondiciones) {
        out_proto(422, ['success' => false, 'error' => 'Debe copiar al menos montos o condiciones']);
    }

    $stmtProt = $mysqliOcup->prepare('SELECT id, descripcion FROM ocupacional_protocolos_empresa WHERE empresa_id = ? AND id IN (?, ?)');
    if (!$stmtProt) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo validar protocolos']);
    }
    $stmtProt->bind_param('iii', $empresaId, $protocoloOrigenId, $protocoloDestinoId);
    $stmtProt->execute();
    $resProt = $stmtProt->get_result();
    $protocolosMap = [];
    while ($row = $resProt->fetch_assoc()) {
        $protocolosMap[(int)$row['id']] = [
            'id' => (int)$row['id'],
            'descripcion' => (string)$row['descripcion'],
        ];
    }
    $stmtProt->close();

    if (!isset($protocolosMap[$protocoloOrigenId]) || !isset($protocolosMap[$protocoloDestinoId])) {
        out_proto(422, ['success' => false, 'error' => 'Origen o destino no corresponden a la empresa seleccionada']);
    }

    $resumen = [
        'modo' => 'legacy_aditivo',
        'montos_en_origen' => 0,
        'montos_procesados' => 0,
        'condiciones_en_origen' => 0,
        'condiciones_insertadas' => 0,
        'condiciones_omitidas_duplicado' => 0,
    ];

    if ($copiarMontos) {
        $stmtMontos = $mysqliOcup->prepare('SELECT catalogo_id, tipo_evaluacion_id, monto FROM ocupacional_protocolo_detalle WHERE protocolo_id = ?');
        if (!$stmtMontos) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo consultar montos del protocolo origen']);
        }
        $stmtMontos->bind_param('i', $protocoloOrigenId);
        $stmtMontos->execute();
        $resMontos = $stmtMontos->get_result();
        $montosRows = [];
        while ($row = $resMontos->fetch_assoc()) {
            $montosRows[] = $row;
        }
        $stmtMontos->close();

        $resumen['montos_en_origen'] = count($montosRows);

        if (!$soloPrevisualizar && !empty($montosRows)) {
            $stmtUpMonto = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolo_detalle (protocolo_id, catalogo_id, tipo_evaluacion_id, monto, created_by, updated_by)
                                                 VALUES (?, ?, ?, ?, ?, ?)
                                                 ON DUPLICATE KEY UPDATE monto = VALUES(monto), updated_by = VALUES(updated_by), updated_at = NOW()');
            if (!$stmtUpMonto) {
                out_proto(500, ['success' => false, 'error' => 'No se pudo preparar copia de montos']);
            }
            foreach ($montosRows as $row) {
                $catalogoId = (int)$row['catalogo_id'];
                $tipoEvaluacionId = (int)$row['tipo_evaluacion_id'];
                $monto = (float)$row['monto'];
                $stmtUpMonto->bind_param('iiidii', $protocoloDestinoId, $catalogoId, $tipoEvaluacionId, $monto, $usuarioId, $usuarioId);
                $stmtUpMonto->execute();
                $resumen['montos_procesados']++;
            }
            $stmtUpMonto->close();
        } else {
            $resumen['montos_procesados'] = $resumen['montos_en_origen'];
        }
    }

    if ($copiarCondiciones) {
        $stmtCond = $mysqliOcup->prepare('SELECT catalogo_id, puesto_trabajo, sexo, edad_min, edad_max FROM ocupacional_protocolo_condiciones WHERE protocolo_id = ? ORDER BY id ASC');
        if (!$stmtCond) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo consultar condiciones del protocolo origen']);
        }
        $stmtCond->bind_param('i', $protocoloOrigenId);
        $stmtCond->execute();
        $resCond = $stmtCond->get_result();
        $condRows = [];
        while ($row = $resCond->fetch_assoc()) {
            $condRows[] = $row;
        }
        $stmtCond->close();

        $resumen['condiciones_en_origen'] = count($condRows);

        $stmtDupCond = $mysqliOcup->prepare('SELECT id
                                             FROM ocupacional_protocolo_condiciones
                                             WHERE protocolo_id = ?
                                               AND catalogo_id = ?
                                               AND COALESCE(puesto_trabajo, "") = ?
                                               AND COALESCE(sexo, "") = ?
                                               AND IFNULL(edad_min, -1) = ?
                                               AND IFNULL(edad_max, -1) = ?
                                             LIMIT 1');
        if (!$stmtDupCond) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo validar duplicados de condiciones']);
        }

        $stmtInsCond = null;
        if (!$soloPrevisualizar) {
            $stmtInsCond = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolo_condiciones
                                                 (protocolo_id, catalogo_id, puesto_trabajo, sexo, edad_min, edad_max, created_by, updated_by)
                                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if (!$stmtInsCond) {
                $stmtDupCond->close();
                out_proto(500, ['success' => false, 'error' => 'No se pudo preparar copia de condiciones']);
            }
        }

        foreach ($condRows as $row) {
            $catalogoId = (int)$row['catalogo_id'];
            $puestoTrabajo = (string)($row['puesto_trabajo'] ?? '');
            $sexo = (string)($row['sexo'] ?? '');
            $edadMin = isset($row['edad_min']) ? (int)$row['edad_min'] : null;
            $edadMax = isset($row['edad_max']) ? (int)$row['edad_max'] : null;
            $edadMinCmp = $edadMin === null ? -1 : $edadMin;
            $edadMaxCmp = $edadMax === null ? -1 : $edadMax;

            $stmtDupCond->bind_param('iissii', $protocoloDestinoId, $catalogoId, $puestoTrabajo, $sexo, $edadMinCmp, $edadMaxCmp);
            $stmtDupCond->execute();
            $dup = $stmtDupCond->get_result()->fetch_assoc();
            if ($dup) {
                $resumen['condiciones_omitidas_duplicado']++;
                continue;
            }

            if (!$soloPrevisualizar && $stmtInsCond) {
                $puestoSave = $puestoTrabajo === '' ? null : $puestoTrabajo;
                $sexoSave = $sexo === '' ? null : $sexo;
                $stmtInsCond->bind_param('iissiiii', $protocoloDestinoId, $catalogoId, $puestoSave, $sexoSave, $edadMin, $edadMax, $usuarioId, $usuarioId);
                $stmtInsCond->execute();
            }
            $resumen['condiciones_insertadas']++;
        }

        $stmtDupCond->close();
        if ($stmtInsCond) {
            $stmtInsCond->close();
        }
    }

    out_proto(200, [
        'success' => true,
        'message' => $soloPrevisualizar ? 'Previsualizacion completada (sin cambios)' : 'Configuracion copiada correctamente',
        'data' => [
            'empresa_id' => $empresaId,
            'protocolo_origen' => $protocolosMap[$protocoloOrigenId],
            'protocolo_destino' => $protocolosMap[$protocoloDestinoId],
            'copiar_montos' => $copiarMontos,
            'copiar_condiciones' => $copiarCondiciones,
            'resumen' => $resumen,
        ],
    ]);
}

if ($accion === 'guardar_monto') {
    $protocoloId = (int)($payload['protocolo_id'] ?? 0);
    $catalogoId = (int)($payload['catalogo_id'] ?? 0);
    $tipoEvaluacionId = (int)($payload['tipo_evaluacion_id'] ?? 0);
    $montoRaw = isset($payload['monto']) ? trim((string)$payload['monto']) : '';
    $restablecerBase = parse_bool_proto($payload['restablecer_base'] ?? false);

    if ($protocoloId <= 0 || $catalogoId <= 0 || $tipoEvaluacionId <= 0) {
        out_proto(422, ['success' => false, 'error' => 'protocolo_id, catalogo_id y tipo_evaluacion_id son obligatorios']);
    }

    if ($restablecerBase) {
        $stmtDel = $mysqliOcup->prepare('DELETE FROM ocupacional_protocolo_detalle WHERE protocolo_id = ? AND catalogo_id = ? AND tipo_evaluacion_id = ? LIMIT 1');
        if (!$stmtDel) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo restablecer monto base']);
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
                'origen' => 'examen_general',
            ],
        ]);
    }

    if ($montoRaw === '') {
        $stmtUpZero = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolo_detalle (protocolo_id, catalogo_id, tipo_evaluacion_id, monto, created_by, updated_by)
                                            VALUES (?, ?, ?, 0, ?, ?)
                                            ON DUPLICATE KEY UPDATE monto = 0, updated_by = VALUES(updated_by), updated_at = NOW()');
        if (!$stmtUpZero) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo excluir examen del protocolo']);
        }
        $stmtUpZero->bind_param('iiiii', $protocoloId, $catalogoId, $tipoEvaluacionId, $usuarioId, $usuarioId);
        $stmtUpZero->execute();
        $stmtUpZero->close();

        out_proto(200, [
            'success' => true,
            'data' => [
                'protocolo_id' => $protocoloId,
                'catalogo_id' => $catalogoId,
                'tipo_evaluacion_id' => $tipoEvaluacionId,
                'monto' => '',
                'origen' => 'protocolo_excluido',
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
                                                                                             AND id <> ?
                                                                                         LIMIT 1');
            if (!$stmtDup) {
                out_proto(500, ['success' => false, 'error' => 'No se pudo validar duplicidad de condicion']);
            }
                        $stmtDup->bind_param('iisi', $protocoloId, $catalogoId, $puestoTrabajo, $id);
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
                                                                                 LIMIT 1');
        if (!$stmtDup) {
            out_proto(500, ['success' => false, 'error' => 'No se pudo validar condicion']);
        }
                $stmtDup->bind_param('iis', $protocoloId, $catalogoId, $puestoTrabajo);
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

if ($accion === 'aplicar_condicion_masiva') {
    $protocoloId = (int)($payload['protocolo_id'] ?? 0);
    $empresaId = (int)($payload['empresa_id'] ?? 0);
    $filtroQ = trim((string)($payload['filtro_q'] ?? ''));
    $puestoTrabajo = trim((string)($payload['puesto_trabajo'] ?? ''));
    $sexo = strtoupper(trim((string)($payload['sexo'] ?? '')));
    $edadMinRaw = trim((string)($payload['edad_min'] ?? ''));
    $edadMaxRaw = trim((string)($payload['edad_max'] ?? ''));
    $soloPrevisualizarRaw = $payload['solo_previsualizar'] ?? false;
    $soloPrevisualizar = false;
    if (is_bool($soloPrevisualizarRaw)) {
        $soloPrevisualizar = $soloPrevisualizarRaw;
    } else {
        $soloPrevisualizar = in_array(strtolower(trim((string)$soloPrevisualizarRaw)), ['1', 'true', 'si', 'yes'], true);
    }

    if ($protocoloId <= 0 || $empresaId <= 0) {
        out_proto(422, ['success' => false, 'error' => 'protocolo_id y empresa_id son obligatorios']);
    }

    if ($filtroQ === '') {
        out_proto(422, ['success' => false, 'error' => 'filtro_q es obligatorio para aplicar de forma masiva']);
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

    $stmtProt = $mysqliOcup->prepare('SELECT id FROM ocupacional_protocolos_empresa WHERE id = ? AND empresa_id = ? LIMIT 1');
    if (!$stmtProt) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo validar protocolo']);
    }
    $stmtProt->bind_param('ii', $protocoloId, $empresaId);
    $stmtProt->execute();
    $prot = $stmtProt->get_result()->fetch_assoc();
    $stmtProt->close();
    if (!$prot) {
        out_proto(422, ['success' => false, 'error' => 'protocolo_id no corresponde a la empresa']);
    }

    $term = '%' . $filtroQ . '%';
    $stmtIds = $mysqliOcup->prepare('SELECT c.id AS catalogo_id
                                     FROM ocupacional_catalogo_empresas c
                                     INNER JOIN ocupacional_examenes_generales e ON e.id = c.examen_id
                                     WHERE c.empresa_id = ?
                                       AND c.estado = "activo"
                                       AND e.estado = "activo"
                                       AND (e.codigo LIKE ? OR e.descripcion LIKE ? OR e.grupo LIKE ? OR e.subgrupo LIKE ?)
                                     ORDER BY e.grupo ASC, e.subgrupo ASC, e.descripcion ASC, e.id DESC');
    if (!$stmtIds) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo buscar examenes para aplicar condicion']);
    }
    $stmtIds->bind_param('issss', $empresaId, $term, $term, $term, $term);
    $stmtIds->execute();
    $resIds = $stmtIds->get_result();
    $catalogoIds = [];
    while ($row = $resIds->fetch_assoc()) {
        $catalogoIds[] = (int)$row['catalogo_id'];
    }
    $stmtIds->close();

    if (empty($catalogoIds)) {
        out_proto(422, ['success' => false, 'error' => 'No hay examenes activos que coincidan con el filtro']);
    }

    if ($soloPrevisualizar) {
        out_proto(200, [
            'success' => true,
            'message' => 'Previsualizacion completada (sin cambios)',
            'data' => [
                'protocolo_id' => $protocoloId,
                'empresa_id' => $empresaId,
                'filtro_q' => $filtroQ,
                'criterio' => [
                    'puesto_trabajo' => $puestoTrabajo === '' ? null : $puestoTrabajo,
                    'sexo' => $sexo === '' ? null : $sexo,
                    'edad_min' => $edadMin,
                    'edad_max' => $edadMax,
                ],
                'resumen' => [
                    'catalogos_coincidentes' => count($catalogoIds),
                    'modo' => 'legacy_aditivo',
                ],
            ],
        ]);
    }

    $puestoSave = $puestoTrabajo === '' ? null : $puestoTrabajo;
    $sexoSave = $sexo === '' ? null : $sexo;
    $stmtDup = $mysqliOcup->prepare('SELECT id
                                     FROM ocupacional_protocolo_condiciones
                                     WHERE protocolo_id = ?
                                       AND catalogo_id = ?
                                       AND COALESCE(puesto_trabajo, "") = ?
                                     LIMIT 1');
    if (!$stmtDup) {
        out_proto(500, ['success' => false, 'error' => 'No se pudo preparar validacion de duplicidad']);
    }

    $stmtIns = $mysqliOcup->prepare('INSERT INTO ocupacional_protocolo_condiciones
                                     (protocolo_id, catalogo_id, puesto_trabajo, sexo, edad_min, edad_max, created_by, updated_by)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmtIns) {
        $stmtDup->close();
        out_proto(500, ['success' => false, 'error' => 'No se pudo preparar insercion masiva']);
    }

    $considerados = 0;
    $insertados = 0;
    $omitidos = 0;

    foreach ($catalogoIds as $catalogoId) {
        $considerados++;

        $puestoCmp = $puestoTrabajo;
        $stmtDup->bind_param('iis', $protocoloId, $catalogoId, $puestoCmp);
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        if ($dup) {
            $omitidos++;
            continue;
        }

        $stmtIns->bind_param('iissiiii', $protocoloId, $catalogoId, $puestoSave, $sexoSave, $edadMin, $edadMax, $usuarioId, $usuarioId);
        $stmtIns->execute();
        $insertados += ((int)$stmtIns->affected_rows > 0) ? 1 : 0;
    }

    $stmtDup->close();
    $stmtIns->close();

    out_proto(200, [
        'success' => true,
        'message' => 'Aplicacion masiva completada',
        'data' => [
            'protocolo_id' => $protocoloId,
            'empresa_id' => $empresaId,
            'filtro_q' => $filtroQ,
            'criterio' => [
                'puesto_trabajo' => $puestoSave,
                'sexo' => $sexoSave,
                'edad_min' => $edadMin,
                'edad_max' => $edadMax,
            ],
            'resumen' => [
                'catalogos_considerados' => $considerados,
                'insertados' => $insertados,
                'omitidos_duplicado' => $omitidos,
                'modo' => 'legacy_aditivo',
            ],
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
