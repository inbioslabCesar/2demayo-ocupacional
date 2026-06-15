<?php

require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_ocupacional.php';

function reply($code, $payload)
{
    http_response_code($code);
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
        reply(401, ['success' => false, 'error' => 'No autenticado']);
    }
    $rol = strtolower(trim((string) ($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }
    $permisos = parseSessionPermisos();
    if (!in_array('access_salud_ocupacional', $permisos, true) || !in_array($permiso, $permisos, true)) {
        reply(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireOcupPermiso('registrar_trabajadores_ocupacional');

    $estado = trim((string) ($_GET['estado'] ?? 'todos'));
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = (int) ($_GET['page'] ?? 1);
    $perPage = (int) ($_GET['per_page'] ?? 20);
    $empresaIdFilter = (int) ($_GET['empresa_id'] ?? 0);
    $sortByRaw = trim((string) ($_GET['sort_by'] ?? 'fecha_ingreso'));
    $sortDirRaw = strtolower(trim((string) ($_GET['sort_dir'] ?? 'desc')));
    $page = max(1, $page);
    $perPage = max(1, min($perPage, 100));
    $offset = ($page - 1) * $perPage;

    $sortMap = [
        'fecha_ingreso' => 'p.fecha_ingreso',
        'documento_numero' => 'p.documento_numero',
        'empresa' => 'e.razon_social',
        'created_at' => 'p.created_at',
    ];
    $sortBy = $sortMap[$sortByRaw] ?? 'p.fecha_ingreso';
    $sortDir = $sortDirRaw === 'asc' ? 'ASC' : 'DESC';

    if (!in_array($estado, ['activo', 'retirado', 'todos'], true)) {
        reply(422, ['success' => false, 'error' => 'Filtro estado invalido']);
    }

    $where = [];
    $types = '';
    $params = [];

    if ($estado !== 'todos') {
        $where[] = 'p.estado_laboral = ?';
        $types .= 's';
        $params[] = $estado;
    }

    if ($empresaIdFilter > 0) {
        $where[] = 'p.empresa_id = ?';
        $types .= 'i';
        $params[] = $empresaIdFilter;
    }

    if ($q !== '') {
        $where[] = '(p.documento_numero LIKE ? OR p.puesto_trabajo LIKE ? OR e.razon_social LIKE ?)';
        $term = '%' . $q . '%';
        $types .= 'sss';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

    $sqlCount = 'SELECT COUNT(*) AS total FROM pacientes_ocupacionales p INNER JOIN empresas_ocupacionales e ON e.id = p.empresa_id' . $whereSql;
    $stmtCount = $mysqliOcup->prepare($sqlCount);
    if (!$stmtCount) {
        reply(500, ['success' => false, 'error' => 'No se pudo preparar conteo']);
    }
    bindParamsDynamic($stmtCount, $types, $params);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result();
    $rowCount = $resCount ? $resCount->fetch_assoc() : ['total' => 0];
    $total = (int) ($rowCount['total'] ?? 0);
    $stmtCount->close();

    $sql = 'SELECT p.id, p.empresa_id, p.external_patient_id, p.documento_tipo, p.documento_numero, p.puesto_trabajo, p.area_riesgo, p.tipo_contrato, p.estado_laboral, p.fecha_ingreso, p.created_at, p.updated_at, p.created_by, p.updated_by, e.razon_social FROM pacientes_ocupacionales p INNER JOIN empresas_ocupacionales e ON e.id = p.empresa_id' . $whereSql . ' ORDER BY ' . $sortBy . ' ' . $sortDir . ', p.id DESC LIMIT ? OFFSET ?';
    $stmtList = $mysqliOcup->prepare($sql);

    if (!$stmtList) {
        reply(500, ['success' => false, 'error' => 'No se pudo preparar listado']);
    }

    $typesData = $types . 'ii';
    $paramsData = $params;
    $paramsData[] = $perPage;
    $paramsData[] = $offset;
    bindParamsDynamic($stmtList, $typesData, $paramsData);

    $stmtList->execute();
    $res = $stmtList->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'empresa_id' => (int) $row['empresa_id'],
            'empresa' => (string) ($row['razon_social'] ?? ''),
            'external_patient_id' => (int) $row['external_patient_id'],
            'documento_tipo' => (string) ($row['documento_tipo'] ?? ''),
            'documento_numero' => (string) ($row['documento_numero'] ?? ''),
            'puesto_trabajo' => (string) ($row['puesto_trabajo'] ?? ''),
            'area_riesgo' => (string) ($row['area_riesgo'] ?? ''),
            'tipo_contrato' => (string) ($row['tipo_contrato'] ?? ''),
            'estado_laboral' => (string) ($row['estado_laboral'] ?? ''),
            'fecha_ingreso' => (string) ($row['fecha_ingreso'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
        ];
    }
    $stmtList->close();

    reply(200, [
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
    reply(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

requireOcupPermiso('registrar_trabajadores_ocupacional');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$empresaId = (int) ($payload['empresa_id'] ?? 0);
$externalPatientId = (int) ($payload['external_patient_id'] ?? 0);
$documentoTipo = strtoupper(trim((string) ($payload['documento_tipo'] ?? 'DNI')));
$documentoNumero = strtoupper(trim((string) ($payload['documento_numero'] ?? '')));
$puestoTrabajo = trim((string) ($payload['puesto_trabajo'] ?? ''));
$areaRiesgo = trim((string) ($payload['area_riesgo'] ?? ''));
$tipoContrato = trim((string) ($payload['tipo_contrato'] ?? ''));
$fechaIngreso = trim((string) ($payload['fecha_ingreso'] ?? ''));
$estadoLaboral = trim((string) ($payload['estado_laboral'] ?? 'activo'));

if ($empresaId <= 0) {
    reply(422, ['success' => false, 'error' => 'empresa_id es obligatorio']);
}

if ($externalPatientId <= 0) {
    reply(422, ['success' => false, 'error' => 'external_patient_id es obligatorio']);
}

if (!preg_match('/^[A-Z0-9]{6,15}$/', $documentoNumero)) {
    reply(422, ['success' => false, 'error' => 'documento_numero invalido. Debe ser alfanumerico de 6 a 15 caracteres']);
}

if ($puestoTrabajo === '') {
    reply(422, ['success' => false, 'error' => 'puesto_trabajo es obligatorio']);
}

if ($fechaIngreso === '') {
    reply(422, ['success' => false, 'error' => 'fecha_ingreso es obligatoria']);
}

$fechaObj = DateTime::createFromFormat('Y-m-d', $fechaIngreso);
if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaIngreso) {
    reply(422, ['success' => false, 'error' => 'fecha_ingreso debe tener formato YYYY-MM-DD']);
}

$hoy = new DateTime('now', new DateTimeZone('America/Lima'));
if ($fechaObj > $hoy) {
    reply(422, ['success' => false, 'error' => 'fecha_ingreso no puede ser futura']);
}

if ($estadoLaboral !== 'activo' && $estadoLaboral !== 'retirado') {
    reply(422, ['success' => false, 'error' => 'estado_laboral invalido']);
}

$empresaStmt = $mysqliOcup->prepare('SELECT id FROM empresas_ocupacionales WHERE id = ? AND estado = "activo" LIMIT 1');
$empresaStmt->bind_param('i', $empresaId);
$empresaStmt->execute();
$empresaRes = $empresaStmt->get_result();
$empresa = $empresaRes ? $empresaRes->fetch_assoc() : null;
$empresaStmt->close();

if (!$empresa) {
    reply(422, ['success' => false, 'error' => 'La empresa no existe o esta inactiva']);
}

$pacStmt = $mysqli->prepare('SELECT id, dni FROM pacientes WHERE id = ? LIMIT 1');
$pacStmt->bind_param('i', $externalPatientId);
$pacStmt->execute();
$pacRes = $pacStmt->get_result();
$pacienteCore = $pacRes ? $pacRes->fetch_assoc() : null;
$pacStmt->close();

if (!$pacienteCore) {
    reply(422, ['success' => false, 'error' => 'El external_patient_id no existe en el sistema clinico']);
}

$coreDoc = strtoupper(trim((string) ($pacienteCore['dni'] ?? '')));
if ($coreDoc !== '' && $coreDoc !== $documentoNumero) {
    reply(422, ['success' => false, 'error' => 'El documento no coincide con el paciente del sistema clinico']);
}

$usuarioId = isset($_SESSION['usuario']['id']) ? (int) $_SESSION['usuario']['id'] : null;

$insert = $mysqliOcup->prepare('INSERT INTO pacientes_ocupacionales (empresa_id, external_patient_id, documento_tipo, documento_numero, puesto_trabajo, area_riesgo, tipo_contrato, estado_laboral, fecha_ingreso, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$insert) {
    reply(500, ['success' => false, 'error' => 'No se pudo preparar la insercion']);
}

$insert->bind_param(
    'iisssssssii',
    $empresaId,
    $externalPatientId,
    $documentoTipo,
    $documentoNumero,
    $puestoTrabajo,
    $areaRiesgo,
    $tipoContrato,
    $estadoLaboral,
    $fechaIngreso,
    $usuarioId,
    $usuarioId
);

if (!$insert->execute()) {
    $errno = (int) $insert->errno;
    $insert->close();
    if ($errno === 1062) {
        reply(409, ['success' => false, 'error' => 'El trabajador ya esta registrado en esta empresa']);
    }
    reply(500, ['success' => false, 'error' => 'No se pudo registrar el trabajador']);
}

$id = (int) $insert->insert_id;
$insert->close();

reply(201, [
    'success' => true,
    'data' => [
        'id' => $id,
        'empresa_id' => $empresaId,
        'external_patient_id' => $externalPatientId,
        'documento_numero' => $documentoNumero,
        'puesto_trabajo' => $puestoTrabajo,
        'estado_laboral' => $estadoLaboral,
        'fecha_ingreso' => $fechaIngreso
    ]
]);
