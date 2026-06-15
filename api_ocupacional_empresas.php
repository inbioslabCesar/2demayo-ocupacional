<?php

require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/db_ocupacional.php';

function out($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function bindParamsDynamic($stmt, $types, $params)
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

function parseSessionPermisos()
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

function requireOcupPermiso($permiso)
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out(401, ['success' => false, 'error' => 'No autenticado']);
    }
    $rol = strtolower(trim((string) ($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }
    $permisos = parseSessionPermisos();
    if (!in_array('access_salud_ocupacional', $permisos, true) || !in_array($permiso, $permisos, true)) {
        out(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireOcupPermiso('gestionar_empresas_ocupacional');

    $estado = trim((string) ($_GET['estado'] ?? 'activo'));
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = (int) ($_GET['page'] ?? 1);
    $perPage = (int) ($_GET['per_page'] ?? 20);
    $sortByRaw = trim((string) ($_GET['sort_by'] ?? 'razon_social'));
    $sortDirRaw = strtolower(trim((string) ($_GET['sort_dir'] ?? 'asc')));
    $page = max(1, $page);
    $perPage = max(1, min($perPage, 500));
    $offset = ($page - 1) * $perPage;

    $sortMap = [
        'razon_social' => 'razon_social',
        'ruc' => 'ruc',
        'estado' => 'estado',
        'created_at' => 'created_at',
    ];
    $sortBy = $sortMap[$sortByRaw] ?? 'razon_social';
    $sortDir = $sortDirRaw === 'desc' ? 'DESC' : 'ASC';

    if ($estado !== 'activo' && $estado !== 'inactivo' && $estado !== 'todos') {
        out(422, ['success' => false, 'error' => 'Filtro estado invalido']);
    }

    $where = [];
    $types = '';
    $params = [];

    if ($estado !== 'todos') {
        $where[] = 'estado = ?';
        $types .= 's';
        $params[] = $estado;
    }

    if ($q !== '') {
        $where[] = '(ruc LIKE ? OR razon_social LIKE ?)';
        $term = '%' . $q . '%';
        $types .= 'ss';
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

    $sqlCount = 'SELECT COUNT(*) AS total FROM empresas_ocupacionales' . $whereSql;
    $stmtCount = $mysqliOcup->prepare($sqlCount);
    if (!$stmtCount) {
        out(500, ['success' => false, 'error' => 'No se pudo preparar conteo']);
    }
    bindParamsDynamic($stmtCount, $types, $params);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    $rowCount = $resCount ? $resCount->fetch_assoc() : ['total' => 0];
    $total = (int) ($rowCount['total'] ?? 0);
    $stmtCount->close();

    $sql = 'SELECT id, ruc, razon_social, direccion, telefono, correo, estado, created_at, updated_at, created_by, updated_by FROM empresas_ocupacionales' . $whereSql . ' ORDER BY ' . $sortBy . ' ' . $sortDir . ', id DESC LIMIT ? OFFSET ?';
    $stmt = $mysqliOcup->prepare($sql);
    if (!$stmt) {
        out(500, ['success' => false, 'error' => 'No se pudo preparar listado']);
    }

    $typesData = $types . 'ii';
    $paramsData = $params;
    $paramsData[] = $perPage;
    $paramsData[] = $offset;
    bindParamsDynamic($stmt, $typesData, $paramsData);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'ruc' => (string) $row['ruc'],
            'razon_social' => (string) $row['razon_social'],
            'direccion' => (string) ($row['direccion'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
            'correo' => (string) ($row['correo'] ?? ''),
            'estado' => (string) $row['estado'],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
        ];
    }
    $stmt->close();

    out(200, [
        'success' => true,
        'data' => $rows,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'sort_by' => $sortByRaw,
            'sort_dir' => strtolower($sortDir),
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

requireOcupPermiso('gestionar_empresas_ocupacional');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$accion = trim((string) ($payload['accion'] ?? 'crear'));

if ($accion === 'inactivar') {
    $id = (int) ($payload['id'] ?? 0);
    if ($id <= 0) {
        out(422, ['success' => false, 'error' => 'id es obligatorio para inactivar']);
    }

    $usuarioId = isset($_SESSION['usuario']['id']) ? (int) $_SESSION['usuario']['id'] : null;
    $stmtInactivar = $mysqliOcup->prepare('UPDATE empresas_ocupacionales SET estado = "inactivo", updated_by = ?, updated_at = NOW() WHERE id = ? AND estado <> "inactivo" LIMIT 1');
    if (!$stmtInactivar) {
        out(500, ['success' => false, 'error' => 'No se pudo preparar la inactivacion']);
    }
    $stmtInactivar->bind_param('ii', $usuarioId, $id);
    $stmtInactivar->execute();
    $affected = (int) $stmtInactivar->affected_rows;
    $stmtInactivar->close();

    if ($affected <= 0) {
        out(404, ['success' => false, 'error' => 'Empresa no encontrada o ya inactiva']);
    }

    out(200, ['success' => true, 'message' => 'Empresa inactivada']);
}

$ruc = trim((string) ($payload['ruc'] ?? ''));
$razonSocial = trim((string) ($payload['razon_social'] ?? ''));
$direccion = trim((string) ($payload['direccion'] ?? ''));
$telefono = trim((string) ($payload['telefono'] ?? ''));
$correo = trim((string) ($payload['correo'] ?? ''));

if (!preg_match('/^[0-9]{11}$/', $ruc)) {
    out(422, ['success' => false, 'error' => 'RUC invalido. Debe tener 11 digitos']);
}

if ($razonSocial === '') {
    out(422, ['success' => false, 'error' => 'Razon social es obligatoria']);
}

if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    out(422, ['success' => false, 'error' => 'Correo invalido']);
}

$usuarioId = isset($_SESSION['usuario']['id']) ? (int) $_SESSION['usuario']['id'] : null;

$stmt = $mysqliOcup->prepare('INSERT INTO empresas_ocupacionales (ruc, razon_social, direccion, telefono, correo, estado, created_by, updated_by) VALUES (?, ?, ?, ?, ?, "activo", ?, ?)');
if (!$stmt) {
    out(500, ['success' => false, 'error' => 'No se pudo preparar la insercion']);
}

$stmt->bind_param('sssssii', $ruc, $razonSocial, $direccion, $telefono, $correo, $usuarioId, $usuarioId);
if (!$stmt->execute()) {
    $errno = (int) $stmt->errno;
    $stmt->close();
    if ($errno === 1062) {
        out(409, ['success' => false, 'error' => 'Ya existe una empresa con este RUC']);
    }
    out(500, ['success' => false, 'error' => 'No se pudo registrar la empresa']);
}

$id = (int) $stmt->insert_id;
$stmt->close();

out(201, [
    'success' => true,
    'data' => [
        'id' => $id,
        'ruc' => $ruc,
        'razon_social' => $razonSocial,
        'estado' => 'activo'
    ]
]);
