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

if (!table_exists_exam($mysqliOcup, 'ocupacional_examenes_generales')) {
    out_exam(500, [
        'success' => false,
        'error' => 'Falta la tabla ocupacional_examenes_generales. Aplicar sql/2026-06-15_ocupacional_fase2_examenes_generales.sql en la base ocupacional.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_ocup_permiso_exam('gestionar_empresas_ocupacional');

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
$grupo = trim((string)($payload['grupo'] ?? ''));
$subgrupo = trim((string)($payload['subgrupo'] ?? ''));
$valoresNormales = trim((string)($payload['valores_normales'] ?? ''));
$precio = normalize_precio_exam($payload['precio'] ?? 0);
$posicion = isset($payload['posicion']) ? (int)$payload['posicion'] : 0;

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
