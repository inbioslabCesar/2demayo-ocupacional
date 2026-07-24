<?php

require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/db_ocupacional.php';

function out_exam($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function bind_params_dynamic_exam($stmt, $types, $params)
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

function parse_session_permisos_exam()
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

function require_ocup_permiso_exam($permiso)
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out_exam(401, ['success' => false, 'error' => 'No autenticado']);
    }

    $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }

    $permisos = parse_session_permisos_exam();
    if (!in_array('access_salud_ocupacional', $permisos, true) || !in_array($permiso, $permisos, true)) {
        out_exam(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
    }
}

function normalize_precio_exam($raw)
{
    if ($raw === '' || $raw === null) return 0;
    $value = is_string($raw) ? str_replace(',', '.', trim($raw)) : $raw;
    $precio = (float)$value;
    if (!is_finite($precio) || $precio < 0) {
        return null;
    }
    return round($precio, 2);
}

function table_exists_exam($conn, $table)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function has_master_grupos_exam($conn)
{
    return table_exists_exam($conn, 'ocupacional_grupos_examenes');
}

function normalize_nombre_grupo_exam($value)
{
    return trim(preg_replace('/\s+/u', ' ', (string)$value));
}

function listar_catalogo_grupos_subgrupos_master_exam($conn)
{
    $grupos = [];
    $subgruposPorGrupo = [];

    $stmtGrupos = $conn->prepare('SELECT id, nombre
                                  FROM ocupacional_grupos_examenes
                                  WHERE parent_id = 0 AND estado = "activo"
                                  ORDER BY orden ASC, nombre ASC, id ASC');
    if ($stmtGrupos) {
        $stmtGrupos->execute();
        $resGrupos = $stmtGrupos->get_result();
        while ($row = $resGrupos->fetch_assoc()) {
            $grupoId = (int)($row['id'] ?? 0);
            $nombre = (string)($row['nombre'] ?? '');
            if ($grupoId <= 0 || $nombre === '') {
                continue;
            }
            $grupos[] = ['id' => $grupoId, 'nombre' => $nombre];
            $subgruposPorGrupo[(string)$grupoId] = [];
        }
        $stmtGrupos->close();
    }

    $stmtSubs = $conn->prepare('SELECT id, nombre, parent_id
                                FROM ocupacional_grupos_examenes
                                WHERE parent_id > 0 AND estado = "activo"
                                ORDER BY parent_id ASC, orden ASC, nombre ASC, id ASC');
    if ($stmtSubs) {
        $stmtSubs->execute();
        $resSubs = $stmtSubs->get_result();
        while ($row = $resSubs->fetch_assoc()) {
            $subId = (int)($row['id'] ?? 0);
            $parentId = (int)($row['parent_id'] ?? 0);
            $nombre = (string)($row['nombre'] ?? '');
            if ($subId <= 0 || $parentId <= 0 || $nombre === '') {
                continue;
            }
            $parentKey = (string)$parentId;
            if (!isset($subgruposPorGrupo[$parentKey])) {
                $subgruposPorGrupo[$parentKey] = [];
            }
            $subgruposPorGrupo[$parentKey][] = ['id' => $subId, 'nombre' => $nombre];
        }
        $stmtSubs->close();
    }

    return [
        'modo' => 'maestro',
        'grupos' => $grupos,
        'subgrupos_por_grupo' => $subgruposPorGrupo,
    ];
}

function listar_catalogo_grupos_subgrupos_exam($conn)
{
    if (has_master_grupos_exam($conn)) {
        return listar_catalogo_grupos_subgrupos_master_exam($conn);
    }

    $grupos = [];
    $subgruposPorGrupo = [];

    $stmtGrupos = $conn->prepare('SELECT DISTINCT TRIM(grupo) AS grupo
                                  FROM ocupacional_examenes_generales
                                  WHERE grupo IS NOT NULL AND TRIM(grupo) <> ""
                                  ORDER BY grupo ASC');
    if ($stmtGrupos) {
        $stmtGrupos->execute();
        $resGrupos = $stmtGrupos->get_result();
        while ($row = $resGrupos->fetch_assoc()) {
            $grupo = (string)($row['grupo'] ?? '');
            if ($grupo !== '') {
                $grupos[] = $grupo;
                $subgruposPorGrupo[$grupo] = [];
            }
        }
        $stmtGrupos->close();
    }

    $stmtSubs = $conn->prepare('SELECT TRIM(grupo) AS grupo, TRIM(subgrupo) AS subgrupo
                                FROM ocupacional_examenes_generales
                                WHERE grupo IS NOT NULL AND TRIM(grupo) <> ""
                                  AND subgrupo IS NOT NULL AND TRIM(subgrupo) <> ""
                                GROUP BY TRIM(grupo), TRIM(subgrupo)
                                ORDER BY TRIM(grupo) ASC, TRIM(subgrupo) ASC');
    if ($stmtSubs) {
        $stmtSubs->execute();
        $resSubs = $stmtSubs->get_result();
        while ($row = $resSubs->fetch_assoc()) {
            $grupo = (string)($row['grupo'] ?? '');
            $subgrupo = (string)($row['subgrupo'] ?? '');
            if ($grupo === '' || $subgrupo === '') {
                continue;
            }
            if (!isset($subgruposPorGrupo[$grupo])) {
                $subgruposPorGrupo[$grupo] = [];
            }
            $subgruposPorGrupo[$grupo][] = $subgrupo;
        }
        $stmtSubs->close();
    }

    foreach ($subgruposPorGrupo as $grupo => $subs) {
        $subgruposPorGrupo[$grupo] = array_values(array_unique($subs));
    }

    return [
        'modo' => 'legacy_texto',
        'grupos' => array_values(array_unique($grupos)),
        'subgrupos_por_grupo' => $subgruposPorGrupo,
    ];
}

function resolve_nombre_grupo_subgrupo_exam($conn, $grupoId, $subgrupoId, $grupoRaw, $subgrupoRaw)
{
    $grupo = normalize_nombre_grupo_exam($grupoRaw);
    $subgrupo = normalize_nombre_grupo_exam($subgrupoRaw);

    if (!has_master_grupos_exam($conn)) {
        return [$grupo, $subgrupo];
    }

    if ($grupoId > 0) {
        $stmtGrupo = $conn->prepare('SELECT id, nombre FROM ocupacional_grupos_examenes WHERE id = ? AND parent_id = 0 AND estado = "activo" LIMIT 1');
        if (!$stmtGrupo) {
            out_exam(500, ['success' => false, 'error' => 'No se pudo validar grupo maestro']);
        }
        $stmtGrupo->bind_param('i', $grupoId);
        $stmtGrupo->execute();
        $rowGrupo = $stmtGrupo->get_result()->fetch_assoc();
        $stmtGrupo->close();
        if (!$rowGrupo) {
            out_exam(422, ['success' => false, 'error' => 'grupo_id invalido o inactivo']);
        }
        $grupo = (string)$rowGrupo['nombre'];
    }

    if ($subgrupoId > 0) {
        if ($grupoId <= 0) {
            out_exam(422, ['success' => false, 'error' => 'subgrupo_id requiere grupo_id']);
        }

        $stmtSub = $conn->prepare('SELECT id, nombre, parent_id
                                   FROM ocupacional_grupos_examenes
                                   WHERE id = ? AND parent_id = ? AND estado = "activo"
                                   LIMIT 1');
        if (!$stmtSub) {
            out_exam(500, ['success' => false, 'error' => 'No se pudo validar subgrupo maestro']);
        }
        $stmtSub->bind_param('ii', $subgrupoId, $grupoId);
        $stmtSub->execute();
        $rowSub = $stmtSub->get_result()->fetch_assoc();
        $stmtSub->close();
        if (!$rowSub) {
            out_exam(422, ['success' => false, 'error' => 'subgrupo_id invalido para el grupo seleccionado']);
        }
        $subgrupo = (string)$rowSub['nombre'];
    }

    return [$grupo, $subgrupo];
}

function obtener_grupo_maestro_exam($conn, $id)
{
    $stmt = $conn->prepare('SELECT id, nombre, parent_id, estado FROM ocupacional_grupos_examenes WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

if (!table_exists_exam($mysqliOcup, 'ocupacional_examenes_generales')) {
    out_exam(500, [
        'success' => false,
        'error' => 'Falta la tabla ocupacional_examenes_generales. Aplicar sql/2026-06-15_ocupacional_fase2_examenes_generales.sql en la base ocupacional.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_ocup_permiso_exam('gestionar_empresas_ocupacional');

    $accion = trim((string)($_GET['accion'] ?? 'listar'));

    if ($accion === 'catalogo_grupos') {
        out_exam(200, [
            'success' => true,
            'data' => listar_catalogo_grupos_subgrupos_exam($mysqliOcup),
        ]);
    }

    if ($accion === 'listar_grupos_maestro') {
        if (!has_master_grupos_exam($mysqliOcup)) {
            out_exam(422, ['success' => false, 'error' => 'No existe maestro de grupos. Ejecute migracion 20260724_0011_ocupacional_maestro_grupos_subgrupos.sql']);
        }

        $estado = trim((string)($_GET['estado'] ?? 'activo'));
        $q = trim((string)($_GET['q'] ?? ''));
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);

        if (!in_array($estado, ['activo', 'inactivo', 'todos'], true)) {
            out_exam(422, ['success' => false, 'error' => 'Filtro estado invalido']);
        }

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $types = '';
        $params = [];

        if ($estado !== 'todos') {
            $where[] = 'g.estado = ?';
            $types .= 's';
            $params[] = $estado;
        }

        if ($q !== '') {
            $where[] = '(g.nombre LIKE ? OR IFNULL(p.nombre, "") LIKE ? OR CASE WHEN g.parent_id = 0 THEN "GRUPO" ELSE "SUB GRUPO" END LIKE ?)';
            $term = '%' . $q . '%';
            $types .= 'sss';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

        $sqlCount = 'SELECT COUNT(*) AS total
                     FROM ocupacional_grupos_examenes g
                     LEFT JOIN ocupacional_grupos_examenes p ON p.id = g.parent_id'
                    . $whereSql;
        $stmtCount = $mysqliOcup->prepare($sqlCount);
        if (!$stmtCount) {
            out_exam(500, ['success' => false, 'error' => 'No se pudo preparar conteo de grupos']);
        }
        bind_params_dynamic_exam($stmtCount, $types, $params);
        $stmtCount->execute();
        $total = (int)(($stmtCount->get_result()->fetch_assoc()['total'] ?? 0));
        $stmtCount->close();

        $sqlRows = 'SELECT
                        g.id,
                        g.nombre,
                        g.parent_id,
                        g.orden,
                        g.estado,
                        IFNULL(p.nombre, "") AS grupo_padre,
                        CASE WHEN g.parent_id = 0 THEN "GRUPO" ELSE "SUB GRUPO" END AS tipo
                    FROM ocupacional_grupos_examenes g
                    LEFT JOIN ocupacional_grupos_examenes p ON p.id = g.parent_id'
                    . $whereSql
                    . ' ORDER BY g.parent_id ASC, g.orden ASC, g.nombre ASC, g.id DESC LIMIT ? OFFSET ?';
        $stmtRows = $mysqliOcup->prepare($sqlRows);
        if (!$stmtRows) {
            out_exam(500, ['success' => false, 'error' => 'No se pudo preparar listado de grupos']);
        }

        $typesRows = $types . 'ii';
        $paramsRows = $params;
        $paramsRows[] = $perPage;
        $paramsRows[] = $offset;
        bind_params_dynamic_exam($stmtRows, $typesRows, $paramsRows);
        $stmtRows->execute();
        $resRows = $stmtRows->get_result();

        $rows = [];
        while ($r = $resRows->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$r['id'],
                'nombre' => (string)$r['nombre'],
                'parent_id' => (int)$r['parent_id'],
                'orden' => (int)($r['orden'] ?? 0),
                'estado' => (string)($r['estado'] ?? 'activo'),
                'grupo_padre' => (string)($r['grupo_padre'] ?? ''),
                'tipo' => (string)($r['tipo'] ?? ''),
            ];
        }
        $stmtRows->close();

        out_exam(200, [
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

    $estado = trim((string)($_GET['estado'] ?? 'activo'));
    $q = trim((string)($_GET['q'] ?? ''));
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    $sortByRaw = trim((string)($_GET['sort_by'] ?? 'descripcion'));
    $sortDirRaw = strtolower(trim((string)($_GET['sort_dir'] ?? 'asc')));

    $page = max(1, $page);
    $perPage = max(1, min($perPage, 200));
    $offset = ($page - 1) * $perPage;

    if (!in_array($estado, ['activo', 'inactivo', 'todos'], true)) {
        out_exam(422, ['success' => false, 'error' => 'Filtro estado invalido']);
    }

    $sortMap = [
        'codigo' => 'codigo',
        'descripcion' => 'descripcion',
        'grupo' => 'grupo',
        'precio' => 'precio',
        'created_at' => 'created_at',
    ];
    $sortBy = $sortMap[$sortByRaw] ?? 'descripcion';
    $sortDir = $sortDirRaw === 'desc' ? 'DESC' : 'ASC';

    $where = [];
    $types = '';
    $params = [];

    if ($estado !== 'todos') {
        $where[] = 'estado = ?';
        $types .= 's';
        $params[] = $estado;
    }

    if ($q !== '') {
        $where[] = '(codigo LIKE ? OR descripcion LIKE ? OR grupo LIKE ? OR subgrupo LIKE ?)';
        $term = '%' . $q . '%';
        $types .= 'ssss';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

    $sqlCount = 'SELECT COUNT(*) AS total FROM ocupacional_examenes_generales' . $whereSql;
    $stmtCount = $mysqliOcup->prepare($sqlCount);
    if (!$stmtCount) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo preparar conteo']);
    }
    bind_params_dynamic_exam($stmtCount, $types, $params);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    $rowCount = $resCount ? $resCount->fetch_assoc() : ['total' => 0];
    $total = (int)($rowCount['total'] ?? 0);
    $stmtCount->close();

    $sql = 'SELECT id, codigo, descripcion, grupo, subgrupo, valores_normales, precio, posicion, estado, created_at, updated_at
            FROM ocupacional_examenes_generales'
            . $whereSql
            . ' ORDER BY ' . $sortBy . ' ' . $sortDir . ', id DESC LIMIT ? OFFSET ?';
    $stmt = $mysqliOcup->prepare($sql);
    if (!$stmt) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo preparar listado']);
    }

    $typesData = $types . 'ii';
    $paramsData = $params;
    $paramsData[] = $perPage;
    $paramsData[] = $offset;
    bind_params_dynamic_exam($stmt, $typesData, $paramsData);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'codigo' => (string)($row['codigo'] ?? ''),
            'descripcion' => (string)($row['descripcion'] ?? ''),
            'grupo' => (string)($row['grupo'] ?? ''),
            'subgrupo' => (string)($row['subgrupo'] ?? ''),
            'valores_normales' => (string)($row['valores_normales'] ?? ''),
            'precio' => (float)($row['precio'] ?? 0),
            'posicion' => (int)($row['posicion'] ?? 0),
            'estado' => (string)($row['estado'] ?? 'activo'),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
    $stmt->close();

    out_exam(200, [
        'success' => true,
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
            'sort_by' => $sortByRaw,
            'sort_dir' => strtolower($sortDir),
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_exam(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

require_ocup_permiso_exam('gestionar_empresas_ocupacional');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$accion = trim((string)($payload['accion'] ?? 'guardar'));
$usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;

if ($accion === 'guardar_grupo_maestro') {
    if (!has_master_grupos_exam($mysqliOcup)) {
        out_exam(422, ['success' => false, 'error' => 'No existe maestro de grupos. Ejecute migracion 20260724_0011_ocupacional_maestro_grupos_subgrupos.sql']);
    }

    $nivel = strtolower(trim((string)($payload['nivel'] ?? 'grupo')));
    $nombre = normalize_nombre_grupo_exam($payload['nombre'] ?? '');
    $parentId = (int)($payload['parent_id'] ?? 0);

    if (!in_array($nivel, ['grupo', 'subgrupo'], true)) {
        out_exam(422, ['success' => false, 'error' => 'nivel invalido']);
    }
    if ($nombre === '') {
        out_exam(422, ['success' => false, 'error' => 'nombre es obligatorio']);
    }

    if ($nivel === 'grupo') {
        $parentId = 0;
    } else {
        if ($parentId <= 0) {
            out_exam(422, ['success' => false, 'error' => 'parent_id es obligatorio para subgrupo']);
        }
        $stmtParent = $mysqliOcup->prepare('SELECT id FROM ocupacional_grupos_examenes WHERE id = ? AND parent_id = 0 AND estado = "activo" LIMIT 1');
        if (!$stmtParent) {
            out_exam(500, ['success' => false, 'error' => 'No se pudo validar grupo padre']);
        }
        $stmtParent->bind_param('i', $parentId);
        $stmtParent->execute();
        $existsParent = (bool)$stmtParent->get_result()->fetch_assoc();
        $stmtParent->close();
        if (!$existsParent) {
            out_exam(422, ['success' => false, 'error' => 'parent_id invalido']);
        }
    }

    $stmtDup = $mysqliOcup->prepare('SELECT id, estado FROM ocupacional_grupos_examenes WHERE parent_id = ? AND UPPER(nombre) = UPPER(?) LIMIT 1');
    if (!$stmtDup) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo validar duplicado de grupo']);
    }
    $stmtDup->bind_param('is', $parentId, $nombre);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();

    if ($dup) {
        $dupId = (int)$dup['id'];
        $dupEstado = (string)($dup['estado'] ?? 'activo');
        if ($dupEstado !== 'activo') {
            $stmtReact = $mysqliOcup->prepare('UPDATE ocupacional_grupos_examenes SET estado = "activo", updated_by = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            if ($stmtReact) {
                $stmtReact->bind_param('ii', $usuarioId, $dupId);
                $stmtReact->execute();
                $stmtReact->close();
            }
        }

        out_exam(200, [
            'success' => true,
            'data' => [
                'id' => $dupId,
                'nombre' => $nombre,
                'parent_id' => $parentId,
                'nivel' => $nivel,
            ],
        ]);
    }

    $stmtIns = $mysqliOcup->prepare('INSERT INTO ocupacional_grupos_examenes (nombre, parent_id, estado, created_by, updated_by) VALUES (?, ?, "activo", ?, ?)');
    if (!$stmtIns) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo registrar grupo maestro']);
    }
    $stmtIns->bind_param('siii', $nombre, $parentId, $usuarioId, $usuarioId);
    if (!$stmtIns->execute()) {
        $stmtIns->close();
        out_exam(500, ['success' => false, 'error' => 'No se pudo guardar grupo maestro']);
    }
    $newId = (int)$stmtIns->insert_id;
    $stmtIns->close();

    out_exam(201, [
        'success' => true,
        'data' => [
            'id' => $newId,
            'nombre' => $nombre,
            'parent_id' => $parentId,
            'nivel' => $nivel,
        ],
    ]);
}

if ($accion === 'actualizar_grupo_maestro') {
    if (!has_master_grupos_exam($mysqliOcup)) {
        out_exam(422, ['success' => false, 'error' => 'No existe maestro de grupos. Ejecute migracion 20260724_0011_ocupacional_maestro_grupos_subgrupos.sql']);
    }

    $idGrupo = (int)($payload['id'] ?? 0);
    $nivel = strtolower(trim((string)($payload['nivel'] ?? '')));
    $nombre = normalize_nombre_grupo_exam($payload['nombre'] ?? '');
    $parentId = (int)($payload['parent_id'] ?? 0);

    if ($idGrupo <= 0) {
        out_exam(422, ['success' => false, 'error' => 'id es obligatorio']);
    }
    if ($nombre === '') {
        out_exam(422, ['success' => false, 'error' => 'nombre es obligatorio']);
    }

    $actual = obtener_grupo_maestro_exam($mysqliOcup, $idGrupo);
    if (!$actual) {
        out_exam(404, ['success' => false, 'error' => 'Grupo no encontrado']);
    }

    $currentParent = (int)($actual['parent_id'] ?? 0);
    if (!in_array($nivel, ['grupo', 'subgrupo'], true)) {
        $nivel = $currentParent === 0 ? 'grupo' : 'subgrupo';
    }

    $newParent = $nivel === 'grupo' ? 0 : $parentId;
    if ($nivel === 'subgrupo' && $newParent <= 0) {
        out_exam(422, ['success' => false, 'error' => 'parent_id es obligatorio para subgrupo']);
    }
    if ($newParent === $idGrupo) {
        out_exam(422, ['success' => false, 'error' => 'Un grupo no puede depender de si mismo']);
    }

    if ($currentParent === 0 && $newParent > 0) {
        $stmtChildren = $mysqliOcup->prepare('SELECT COUNT(*) AS total FROM ocupacional_grupos_examenes WHERE parent_id = ? AND estado = "activo"');
        if ($stmtChildren) {
            $stmtChildren->bind_param('i', $idGrupo);
            $stmtChildren->execute();
            $childrenTotal = (int)($stmtChildren->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtChildren->close();
            if ($childrenTotal > 0) {
                out_exam(409, [
                    'success' => false,
                    'error' => 'No se puede convertir a subgrupo porque tiene subgrupos activos',
                    'error_code' => 'GRUPO_TIENE_SUBGRUPOS_ACTIVOS',
                    'data' => [
                        'id' => $idGrupo,
                        'subgrupos_activos' => $childrenTotal,
                    ],
                ]);
            }
        }
    }

    if ($newParent > 0) {
        $parentRow = obtener_grupo_maestro_exam($mysqliOcup, $newParent);
        if (!$parentRow || (int)($parentRow['parent_id'] ?? -1) !== 0 || (string)($parentRow['estado'] ?? '') !== 'activo') {
            out_exam(422, ['success' => false, 'error' => 'parent_id invalido']);
        }
    }

    $stmtDup = $mysqliOcup->prepare('SELECT id FROM ocupacional_grupos_examenes WHERE parent_id = ? AND UPPER(nombre) = UPPER(?) AND id <> ? LIMIT 1');
    if (!$stmtDup) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo validar duplicado']);
    }
    $stmtDup->bind_param('isi', $newParent, $nombre, $idGrupo);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) {
        out_exam(409, [
            'success' => false,
            'error' => 'Ya existe un registro con ese nombre en el mismo nivel',
            'error_code' => 'NOMBRE_DUPLICADO_MISMO_NIVEL',
            'data' => [
                'id_conflicto' => (int)$dup['id'],
                'parent_id' => $newParent,
                'nombre' => $nombre,
            ],
        ]);
    }

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_grupos_examenes SET nombre = ?, parent_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmtUp) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo actualizar grupo maestro']);
    }
    $stmtUp->bind_param('siii', $nombre, $newParent, $usuarioId, $idGrupo);
    $stmtUp->execute();
    $stmtUp->close();

    out_exam(200, [
        'success' => true,
        'data' => [
            'id' => $idGrupo,
            'nombre' => $nombre,
            'parent_id' => $newParent,
            'nivel' => $nivel,
        ],
    ]);
}

if ($accion === 'eliminar_grupo_maestro') {
    if (!has_master_grupos_exam($mysqliOcup)) {
        out_exam(422, ['success' => false, 'error' => 'No existe maestro de grupos. Ejecute migracion 20260724_0011_ocupacional_maestro_grupos_subgrupos.sql']);
    }

    $idGrupo = (int)($payload['id'] ?? 0);
    if ($idGrupo <= 0) {
        out_exam(422, ['success' => false, 'error' => 'id es obligatorio']);
    }

    $row = obtener_grupo_maestro_exam($mysqliOcup, $idGrupo);
    if (!$row) {
        out_exam(404, ['success' => false, 'error' => 'Grupo no encontrado']);
    }

    $nombre = (string)$row['nombre'];
    $parent = (int)($row['parent_id'] ?? 0);

    if ($parent === 0) {
        $stmtChildren = $mysqliOcup->prepare('SELECT COUNT(*) AS total FROM ocupacional_grupos_examenes WHERE parent_id = ? AND estado = "activo"');
        if ($stmtChildren) {
            $stmtChildren->bind_param('i', $idGrupo);
            $stmtChildren->execute();
            $childrenTotal = (int)($stmtChildren->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtChildren->close();
            if ($childrenTotal > 0) {
                out_exam(409, [
                    'success' => false,
                    'error' => 'No se puede inactivar: el grupo tiene subgrupos activos',
                    'error_code' => 'GRUPO_TIENE_SUBGRUPOS_ACTIVOS',
                    'data' => [
                        'id' => $idGrupo,
                        'subgrupos_activos' => $childrenTotal,
                    ],
                ]);
            }
        }

        $stmtUso = $mysqliOcup->prepare('SELECT COUNT(*) AS total FROM ocupacional_examenes_generales WHERE UPPER(TRIM(grupo)) = UPPER(?)');
        if ($stmtUso) {
            $stmtUso->bind_param('s', $nombre);
            $stmtUso->execute();
            $usoTotal = (int)($stmtUso->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtUso->close();
            if ($usoTotal > 0) {
                out_exam(409, [
                    'success' => false,
                    'error' => 'No se puede inactivar: el grupo esta usado en examenes',
                    'error_code' => 'GRUPO_EN_USO',
                    'data' => [
                        'id' => $idGrupo,
                        'examenes_relacionados' => $usoTotal,
                    ],
                ]);
            }
        }
    } else {
        $parentRow = obtener_grupo_maestro_exam($mysqliOcup, $parent);
        $nombreParent = (string)($parentRow['nombre'] ?? '');

        if ($nombreParent !== '') {
            $stmtUsoSub = $mysqliOcup->prepare('SELECT COUNT(*) AS total
                                                FROM ocupacional_examenes_generales
                                                WHERE UPPER(TRIM(grupo)) = UPPER(?)
                                                  AND UPPER(TRIM(subgrupo)) = UPPER(?)');
            if ($stmtUsoSub) {
                $stmtUsoSub->bind_param('ss', $nombreParent, $nombre);
                $stmtUsoSub->execute();
                $usoTotal = (int)($stmtUsoSub->get_result()->fetch_assoc()['total'] ?? 0);
                $stmtUsoSub->close();
                if ($usoTotal > 0) {
                    out_exam(409, [
                        'success' => false,
                        'error' => 'No se puede inactivar: el subgrupo esta usado en examenes',
                        'error_code' => 'SUBGRUPO_EN_USO',
                        'data' => [
                            'id' => $idGrupo,
                            'examenes_relacionados' => $usoTotal,
                        ],
                    ]);
                }
            }
        }
    }

    $stmtInac = $mysqliOcup->prepare('UPDATE ocupacional_grupos_examenes SET estado = "inactivo", updated_by = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmtInac) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo inactivar grupo maestro']);
    }
    $stmtInac->bind_param('ii', $usuarioId, $idGrupo);
    $stmtInac->execute();
    $stmtInac->close();

    out_exam(200, ['success' => true, 'message' => 'Registro inactivado']);
}

if ($accion === 'inactivar') {
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) {
        out_exam(422, ['success' => false, 'error' => 'id es obligatorio para inactivar']);
    }

    $stmtInactivar = $mysqliOcup->prepare('UPDATE ocupacional_examenes_generales SET estado = "inactivo", updated_by = ?, updated_at = NOW() WHERE id = ? AND estado <> "inactivo" LIMIT 1');
    if (!$stmtInactivar) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo preparar la inactivacion']);
    }

    $stmtInactivar->bind_param('ii', $usuarioId, $id);
    $stmtInactivar->execute();
    $affected = (int)$stmtInactivar->affected_rows;
    $stmtInactivar->close();

    if ($affected <= 0) {
        out_exam(404, ['success' => false, 'error' => 'Examen no encontrado o ya inactivo']);
    }

    out_exam(200, ['success' => true, 'message' => 'Examen inactivado']);
}

$id = (int)($payload['id'] ?? 0);
$codigo = strtoupper(trim((string)($payload['codigo'] ?? '')));
$descripcion = trim((string)($payload['descripcion'] ?? ''));
$grupoId = (int)($payload['grupo_id'] ?? 0);
$subgrupoId = (int)($payload['subgrupo_id'] ?? 0);
$grupo = trim((string)($payload['grupo'] ?? ''));
$subgrupo = trim((string)($payload['subgrupo'] ?? ''));
$valoresNormales = trim((string)($payload['valores_normales'] ?? ''));
$precio = normalize_precio_exam($payload['precio'] ?? 0);
$posicion = isset($payload['posicion']) ? (int)$payload['posicion'] : 0;

[$grupo, $subgrupo] = resolve_nombre_grupo_subgrupo_exam($mysqliOcup, $grupoId, $subgrupoId, $grupo, $subgrupo);

if ($codigo === '') {
    out_exam(422, ['success' => false, 'error' => 'codigo es obligatorio']);
}
if ($descripcion === '') {
    out_exam(422, ['success' => false, 'error' => 'descripcion es obligatoria']);
}
if ($precio === null) {
    out_exam(422, ['success' => false, 'error' => 'precio invalido']);
}
if ($posicion < 0) {
    out_exam(422, ['success' => false, 'error' => 'posicion invalida']);
}

if ($id > 0) {
    $stmtDup = $mysqliOcup->prepare('SELECT id FROM ocupacional_examenes_generales WHERE codigo = ? AND id <> ? LIMIT 1');
    if (!$stmtDup) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo validar codigo']);
    }
    $stmtDup->bind_param('si', $codigo, $id);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) {
        out_exam(409, ['success' => false, 'error' => 'Ya existe un examen con este codigo']);
    }

    $stmt = $mysqliOcup->prepare('UPDATE ocupacional_examenes_generales SET codigo = ?, descripcion = ?, grupo = ?, subgrupo = ?, valores_normales = ?, precio = ?, posicion = ?, updated_by = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmt) {
        out_exam(500, ['success' => false, 'error' => 'No se pudo preparar actualizacion']);
    }
    $stmt->bind_param('sssssdiii', $codigo, $descripcion, $grupo, $subgrupo, $valoresNormales, $precio, $posicion, $usuarioId, $id);
    $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        $stmtCheck = $mysqliOcup->prepare('SELECT id FROM ocupacional_examenes_generales WHERE id = ? LIMIT 1');
        if ($stmtCheck) {
            $stmtCheck->bind_param('i', $id);
            $stmtCheck->execute();
            $exists = (bool)$stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();
            if (!$exists) {
                out_exam(404, ['success' => false, 'error' => 'Examen no encontrado']);
            }
        }
    }

    out_exam(200, [
        'success' => true,
        'data' => [
            'id' => $id,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
        ],
    ]);
}

$stmtDup = $mysqliOcup->prepare('SELECT id FROM ocupacional_examenes_generales WHERE codigo = ? LIMIT 1');
if (!$stmtDup) {
    out_exam(500, ['success' => false, 'error' => 'No se pudo validar codigo']);
}
$stmtDup->bind_param('s', $codigo);
$stmtDup->execute();
$dup = $stmtDup->get_result()->fetch_assoc();
$stmtDup->close();
if ($dup) {
    out_exam(409, ['success' => false, 'error' => 'Ya existe un examen con este codigo']);
}

$stmt = $mysqliOcup->prepare('INSERT INTO ocupacional_examenes_generales (codigo, descripcion, grupo, subgrupo, valores_normales, precio, posicion, estado, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, "activo", ?, ?)');
if (!$stmt) {
    out_exam(500, ['success' => false, 'error' => 'No se pudo preparar insercion']);
}
$stmt->bind_param('sssssdiii', $codigo, $descripcion, $grupo, $subgrupo, $valoresNormales, $precio, $posicion, $usuarioId, $usuarioId);

if (!$stmt->execute()) {
    $stmt->close();
    out_exam(500, ['success' => false, 'error' => 'No se pudo registrar el examen']);
}

$newId = (int)$stmt->insert_id;
$stmt->close();

out_exam(201, [
    'success' => true,
    'data' => [
        'id' => $newId,
        'codigo' => $codigo,
        'descripcion' => $descripcion,
        'estado' => 'activo',
    ],
]);
