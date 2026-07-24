<?php
require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/config.php';

function out_bio(int $status, array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function is_valid_bio_image(?string $value): bool
{
    if ($value === null || trim($value) === '') {
        return true;
    }
    return preg_match('/^data:image\/(png|jpeg|jpg);base64,[A-Za-z0-9+\/=\r\n]+$/', $value) === 1;
}

function has_bio_columns($conn): bool
{
        $sql = "SELECT COUNT(*) AS total FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                            AND TABLE_NAME = 'pacientes'
                            AND COLUMN_NAME IN ('firma_digital', 'huella_digital', 'fotografia')";
        $res = $conn->query($sql);
        $row = $res ? $res->fetch_assoc() : ['total' => 0];
        return (int)($row['total'] ?? 0) === 3;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_bio(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

if (!has_bio_columns($conn)) {
    out_bio(409, ['success' => false, 'error' => 'Faltan columnas biometricas en pacientes. Ejecute migraciones/20260724_0006_pacientes_biometria.sql']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$patientId = (int)($data['patient_id'] ?? 0);
$firma = array_key_exists('firma_digital', $data) ? (string)$data['firma_digital'] : null;
$huella = array_key_exists('huella_digital', $data) ? (string)$data['huella_digital'] : null;
$foto = array_key_exists('fotografia', $data) ? (string)$data['fotografia'] : null;

if ($patientId <= 0) {
    out_bio(422, ['success' => false, 'error' => 'patient_id es obligatorio']);
}
if (!is_valid_bio_image($firma)) {
    out_bio(422, ['success' => false, 'error' => 'firma_digital invalida']);
}
if (!is_valid_bio_image($huella)) {
    out_bio(422, ['success' => false, 'error' => 'huella_digital invalida']);
}
if (!is_valid_bio_image($foto)) {
    out_bio(422, ['success' => false, 'error' => 'fotografia invalida']);
}

$updates = [];
$types = '';
$params = [];

if ($firma !== null) {
    $updates[] = 'firma_digital = ?';
    $types .= 's';
    $params[] = $firma;
}
if ($huella !== null) {
    $updates[] = 'huella_digital = ?';
    $types .= 's';
    $params[] = $huella;
}
if ($foto !== null) {
    $updates[] = 'fotografia = ?';
    $types .= 's';
    $params[] = $foto;
}

if (empty($updates)) {
    out_bio(422, ['success' => false, 'error' => 'Debe enviar al menos un campo biometrico']);
}

$sql = 'UPDATE pacientes SET ' . implode(', ', $updates) . ' WHERE id = ? LIMIT 1';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    out_bio(500, ['success' => false, 'error' => 'No se pudo preparar actualizacion']);
}

$types .= 'i';
$params[] = $patientId;

$bind = [$types];
foreach ($params as $k => $v) {
    $bind[] = &$params[$k];
}
call_user_func_array([$stmt, 'bind_param'], $bind);

if (!$stmt->execute()) {
    $stmt->close();
    out_bio(500, ['success' => false, 'error' => 'No se pudo guardar biometria']);
}
$stmt->close();

$sel = $conn->prepare('SELECT id, firma_digital, huella_digital, fotografia FROM pacientes WHERE id = ? LIMIT 1');
if (!$sel) {
    out_bio(500, ['success' => false, 'error' => 'No se pudo leer biometria guardada']);
}
$sel->bind_param('i', $patientId);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
$sel->close();

if (!$row) {
    out_bio(404, ['success' => false, 'error' => 'Paciente no encontrado']);
}

out_bio(200, [
    'success' => true,
    'data' => [
        'id' => (int)$row['id'],
        'tiene_firma_digital' => trim((string)($row['firma_digital'] ?? '')) !== '',
        'tiene_huella_digital' => trim((string)($row['huella_digital'] ?? '')) !== '',
        'tiene_fotografia' => trim((string)($row['fotografia'] ?? '')) !== '',
    ],
]);
