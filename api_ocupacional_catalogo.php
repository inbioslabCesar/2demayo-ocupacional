<?php

require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/db_ocupacional.php';

function out_catalog($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function bind_params_dynamic_catalog($stmt, $types, $params)
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

function parse_session_permisos_catalog()
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

function require_ocup_permiso_catalog($permiso)
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out_catalog(401, ['success' => false, 'error' => 'No autenticado']);
    }

    $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }

    $permisos = parse_session_permisos_catalog();
    if (!in_array('access_salud_ocupacional', $permisos, true) || !in_array($permiso, $permisos, true)) {
        out_catalog(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
    }
}

function table_exists_catalog($conn, $table)
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

if (!table_exists_catalog($mysqliOcup, 'ocupacional_catalogo_empresas')) {
    out_catalog(500, [
        'success' => false,
        'error' => 'Falta la tabla ocupacional_catalogo_empresas. Aplicar sql/2026-06-15_ocupacional_fase2_catalogo_empresa_examenes.sql en la base ocupacional.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_ocup_permiso_catalog('gestionar_empresas_ocupacional');

    $empresaId = (int)($_GET['empresa_id'] ?? 0);
    $estadoCatalogo = trim((string)($_GET['estado_catalogo'] ?? 'todos'));
    $q = trim((string)($_GET['q'] ?? ''));
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 50);

    if ($empresaId <= 0) {
        out_catalog(422, ['success' => false, 'error' => 'empresa_id es obligatorio']);
    }

    if (!in_array($estadoCatalogo, ['todos', 'activo', 'inactivo'], true)) {
        out_catalog(422, ['success' => false, 'error' => 'estado_catalogo invalido']);
    }

    $page = max(1, $page);
    $perPage = max(1, min($perPage, 500));
    $offset = ($page - 1) * $perPage;

    $where = [
        'e.estado = "activo"',
    ];
    $types = 'i';
    $params = [$empresaId];

    if ($estadoCatalogo === 'activo') {
        $where[] = 'c.estado = "activo"';
    } elseif ($estadoCatalogo === 'inactivo') {
        $where[] = '(c.id IS NULL OR c.estado = "inactivo")';
    }

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
                 FROM ocupacional_examenes_generales e
                 LEFT JOIN ocupacional_catalogo_empresas c
                   ON c.examen_id = e.id AND c.empresa_id = ?'
        . $whereSql;

    $stmtCount = $mysqliOcup->prepare($sqlCount);
    if (!$stmtCount) {
        out_catalog(500, ['success' => false, 'error' => 'No se pudo preparar conteo']);
    }
    bind_params_dynamic_catalog($stmtCount, $types, $params);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    $rowCount = $resCount ? $resCount->fetch_assoc() : ['total' => 0];
    $total = (int)($rowCount['total'] ?? 0);
    $stmtCount->close();

    $sql = 'SELECT
              e.id AS examen_id,
              e.codigo,
              e.descripcion,
              e.grupo,
              e.subgrupo,
              e.precio,
              e.estado AS examen_estado,
              c.id AS catalogo_id,
              c.estado AS catalogo_estado,
              c.updated_at AS catalogo_updated_at
            FROM ocupacional_examenes_generales e
            LEFT JOIN ocupacional_catalogo_empresas c
              ON c.examen_id = e.id AND c.empresa_id = ?'
          . $whereSql
          . ' ORDER BY e.grupo ASC, e.subgrupo ASC, e.descripcion ASC, e.id DESC LIMIT ? OFFSET ?';

    $stmt = $mysqliOcup->prepare($sql);
    if (!$stmt) {
        out_catalog(500, ['success' => false, 'error' => 'No se pudo preparar listado']);
    }

    $typesData = $types . 'ii';
    $paramsData = $params;
    $paramsData[] = $perPage;
    $paramsData[] = $offset;

    bind_params_dynamic_catalog($stmt, $typesData, $paramsData);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $catalogoEstado = (string)($row['catalogo_estado'] ?? 'inactivo');
        $habilitado = $catalogoEstado === 'activo';
        $rows[] = [
            'examen_id' => (int)$row['examen_id'],
            'codigo' => (string)($row['codigo'] ?? ''),
            'descripcion' => (string)($row['descripcion'] ?? ''),
            'grupo' => (string)($row['grupo'] ?? ''),
            'subgrupo' => (string)($row['subgrupo'] ?? ''),
            'precio' => (float)($row['precio'] ?? 0),
            'examen_estado' => (string)($row['examen_estado'] ?? 'activo'),
            'catalogo_id' => isset($row['catalogo_id']) ? (int)$row['catalogo_id'] : null,
            'catalogo_estado' => $catalogoEstado,
            'habilitado' => $habilitado,
            'catalogo_updated_at' => (string)($row['catalogo_updated_at'] ?? ''),
        ];
    }
    $stmt->close();

    out_catalog(200, [
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_catalog(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

require_ocup_permiso_catalog('gestionar_empresas_ocupacional');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$empresaId = (int)($payload['empresa_id'] ?? 0);
$examenId = (int)($payload['examen_id'] ?? 0);
$habilitadoRaw = $payload['habilitado'] ?? null;
$habilitado = $habilitadoRaw === true || $habilitadoRaw === 1 || $habilitadoRaw === '1' || $habilitadoRaw === 'true';
$estado = $habilitado ? 'activo' : 'inactivo';
$usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;

if ($empresaId <= 0 || $examenId <= 0) {
    out_catalog(422, ['success' => false, 'error' => 'empresa_id y examen_id son obligatorios']);
}

$stmtEmpresa = $mysqliOcup->prepare('SELECT id FROM empresas_ocupacionales WHERE id = ? LIMIT 1');
if (!$stmtEmpresa) {
    out_catalog(500, ['success' => false, 'error' => 'No se pudo validar empresa']);
}
$stmtEmpresa->bind_param('i', $empresaId);
$stmtEmpresa->execute();
$empresa = $stmtEmpresa->get_result()->fetch_assoc();
$stmtEmpresa->close();
if (!$empresa) {
    out_catalog(404, ['success' => false, 'error' => 'Empresa no encontrada']);
}

$stmtExamen = $mysqliOcup->prepare('SELECT id FROM ocupacional_examenes_generales WHERE id = ? LIMIT 1');
if (!$stmtExamen) {
    out_catalog(500, ['success' => false, 'error' => 'No se pudo validar examen']);
}
$stmtExamen->bind_param('i', $examenId);
$stmtExamen->execute();
$examen = $stmtExamen->get_result()->fetch_assoc();
$stmtExamen->close();
if (!$examen) {
    out_catalog(404, ['success' => false, 'error' => 'Examen no encontrado']);
}

$stmtFind = $mysqliOcup->prepare('SELECT id FROM ocupacional_catalogo_empresas WHERE empresa_id = ? AND examen_id = ? LIMIT 1');
if (!$stmtFind) {
    out_catalog(500, ['success' => false, 'error' => 'No se pudo consultar catalogo']);
}
$stmtFind->bind_param('ii', $empresaId, $examenId);
$stmtFind->execute();
$existing = $stmtFind->get_result()->fetch_assoc();
$stmtFind->close();

if ($existing) {
    $catalogoId = (int)$existing['id'];
    $stmtUpdate = $mysqliOcup->prepare('UPDATE ocupacional_catalogo_empresas SET estado = ?, updated_by = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmtUpdate) {
        out_catalog(500, ['success' => false, 'error' => 'No se pudo actualizar catalogo']);
    }
    $stmtUpdate->bind_param('sii', $estado, $usuarioId, $catalogoId);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    out_catalog(200, [
        'success' => true,
        'data' => [
            'catalogo_id' => $catalogoId,
            'empresa_id' => $empresaId,
            'examen_id' => $examenId,
            'estado' => $estado,
            'habilitado' => $habilitado,
        ],
    ]);
}

$stmtInsert = $mysqliOcup->prepare('INSERT INTO ocupacional_catalogo_empresas (empresa_id, examen_id, estado, created_by, updated_by) VALUES (?, ?, ?, ?, ?)');
if (!$stmtInsert) {
    out_catalog(500, ['success' => false, 'error' => 'No se pudo crear catalogo']);
}
$stmtInsert->bind_param('iisii', $empresaId, $examenId, $estado, $usuarioId, $usuarioId);
if (!$stmtInsert->execute()) {
    $stmtInsert->close();
    out_catalog(500, ['success' => false, 'error' => 'No se pudo registrar catalogo']);
}
$catalogoId = (int)$stmtInsert->insert_id;
$stmtInsert->close();

out_catalog(201, [
    'success' => true,
    'data' => [
        'catalogo_id' => $catalogoId,
        'empresa_id' => $empresaId,
        'examen_id' => $examenId,
        'estado' => $estado,
        'habilitado' => $habilitado,
    ],
]);
