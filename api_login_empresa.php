<?php
require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_ocupacional.php';

const EMPRESA_LOGIN_DOMAIN = 'clinica2demayo.com';

function out_empresa_login($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalize_slug_empresa_login($value)
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted !== false && $converted !== '') {
        $text = $converted;
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    $text = preg_replace('/_+/', '_', (string)$text);
    return trim((string)$text, '_');
}

function parse_empresa_login_email($email)
{
    $email = strtolower(trim((string)$email));
    if ($email === '' || strpos($email, '@') === false) {
        return null;
    }
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return null;
    }
    $localPart = trim((string)$parts[0]);
    $domain = trim((string)$parts[1]);
    if ($localPart === '' || $domain !== EMPRESA_LOGIN_DOMAIN) {
        return null;
    }
    return [
        'email' => $email,
        'local' => $localPart,
    ];
}

function ensure_empresa_portal_table($mysqliOcup)
{
    $sql = 'CREATE TABLE IF NOT EXISTS ocupacional_empresas_portal_usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        email_login VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        estado ENUM("activo", "inactivo") NOT NULL DEFAULT "activo",
        ultimo_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_email_login (email_login),
        KEY idx_empresa_id (empresa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    if (!$mysqliOcup->query($sql)) {
        out_empresa_login(500, ['success' => false, 'error' => 'No se pudo inicializar credenciales de portal empresa']);
    }
}

function set_empresa_session($account, $empresa)
{
    session_regenerate_id(true);
    $_SESSION['empresa_portal'] = [
        'usuario_portal_id' => (int)($account['id'] ?? 0),
        'empresa_id' => (int)($empresa['id'] ?? 0),
        'email_login' => (string)($account['email_login'] ?? ''),
        'nombre_empresa' => (string)($empresa['razon_social'] ?? ''),
        'ruc' => (string)($empresa['ruc'] ?? ''),
    ];

    unset($_SESSION['usuario']);
    unset($_SESSION['medico']);
    unset($_SESSION['medico_id']);

    return [
        'id' => (int)($account['id'] ?? 0),
        'usuario' => (string)($account['email_login'] ?? ''),
        'nombre' => (string)($empresa['razon_social'] ?? ''),
        'rol' => 'empresa',
        'empresa_id' => (int)($empresa['id'] ?? 0),
        'ruc' => (string)($empresa['ruc'] ?? ''),
        'permisos' => ['empresa_portal_read'],
    ];
}

function find_empresa_legacy_match($mysqliOcup, $localPart)
{
    $stmt = $mysqliOcup->prepare('SELECT id, ruc, razon_social, nombre_comercial, rrhh_usuario, rrhh_password, doctor_usuario, doctor_password, estado FROM empresas_ocupacionales WHERE estado = "activo"');
    if (!$stmt) {
        out_empresa_login(500, ['success' => false, 'error' => 'No se pudo validar empresa para login']);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $localNorm = normalize_slug_empresa_login($localPart);
    $found = null;
    while ($row = $res->fetch_assoc()) {
        $candidates = [
            normalize_slug_empresa_login($row['rrhh_usuario'] ?? ''),
            normalize_slug_empresa_login($row['doctor_usuario'] ?? ''),
            normalize_slug_empresa_login($row['nombre_comercial'] ?? ''),
            normalize_slug_empresa_login($row['razon_social'] ?? ''),
            normalize_slug_empresa_login((string)($row['id'] ?? '')),
            normalize_slug_empresa_login((string)($row['ruc'] ?? '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $candidate === $localNorm) {
                $found = $row;
                break 2;
            }
        }
    }
    $stmt->close();
    return $found;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$usuario = (string)($data['usuario'] ?? $data['email'] ?? '');
$password = (string)($data['password'] ?? '');
if (trim($usuario) === '' || trim($password) === '') {
    out_empresa_login(400, ['success' => false, 'error' => 'Usuario y contraseña requeridos']);
}

$parsedEmail = parse_empresa_login_email($usuario);
if (!$parsedEmail) {
    out_empresa_login(422, ['success' => false, 'error' => 'Use un login empresarial valido (nombre_empresa@clinica2demayo.com)']);
}

ensure_empresa_portal_table($mysqliOcup);

$stmtAcc = $mysqliOcup->prepare('SELECT pu.id, pu.empresa_id, pu.email_login, pu.password_hash, pu.estado, e.ruc, e.razon_social, e.estado AS empresa_estado
                                 FROM ocupacional_empresas_portal_usuarios pu
                                 INNER JOIN empresas_ocupacionales e ON e.id = pu.empresa_id
                                 WHERE LOWER(pu.email_login) = LOWER(?)
                                 LIMIT 1');
if (!$stmtAcc) {
    out_empresa_login(500, ['success' => false, 'error' => 'No se pudo autenticar empresa']);
}
$stmtAcc->bind_param('s', $parsedEmail['email']);
$stmtAcc->execute();
$account = $stmtAcc->get_result()->fetch_assoc();
$stmtAcc->close();

if ($account) {
    if (($account['estado'] ?? '') !== 'activo' || ($account['empresa_estado'] ?? '') !== 'activo') {
        out_empresa_login(403, ['success' => false, 'error' => 'Cuenta empresarial inactiva']);
    }

    if (!password_verify($password, (string)($account['password_hash'] ?? ''))) {
        out_empresa_login(401, ['success' => false, 'error' => 'Credenciales incorrectas']);
    }

    $stmtTouch = $mysqliOcup->prepare('UPDATE ocupacional_empresas_portal_usuarios SET ultimo_login_at = NOW() WHERE id = ? LIMIT 1');
    if ($stmtTouch) {
        $accId = (int)$account['id'];
        $stmtTouch->bind_param('i', $accId);
        $stmtTouch->execute();
        $stmtTouch->close();
    }

    $usuarioPortal = set_empresa_session($account, [
        'id' => (int)$account['empresa_id'],
        'ruc' => (string)($account['ruc'] ?? ''),
        'razon_social' => (string)($account['razon_social'] ?? ''),
    ]);
    out_empresa_login(200, ['success' => true, 'usuario' => $usuarioPortal]);
}

$empresaLegacy = find_empresa_legacy_match($mysqliOcup, (string)$parsedEmail['local']);
if (!$empresaLegacy) {
    out_empresa_login(401, ['success' => false, 'error' => 'Credenciales incorrectas']);
}

$rrhhPass = (string)($empresaLegacy['rrhh_password'] ?? '');
$doctorPass = (string)($empresaLegacy['doctor_password'] ?? '');
$legacyMatch = ($rrhhPass !== '' && hash_equals($rrhhPass, $password)) || ($doctorPass !== '' && hash_equals($doctorPass, $password));
if (!$legacyMatch) {
    out_empresa_login(401, ['success' => false, 'error' => 'Credenciales incorrectas']);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmtUpsert = $mysqliOcup->prepare('INSERT INTO ocupacional_empresas_portal_usuarios (empresa_id, email_login, password_hash, estado, ultimo_login_at)
                                    VALUES (?, ?, ?, "activo", NOW())
                                    ON DUPLICATE KEY UPDATE
                                        empresa_id = VALUES(empresa_id),
                                        password_hash = VALUES(password_hash),
                                        estado = "activo",
                                        ultimo_login_at = NOW()');
if (!$stmtUpsert) {
    out_empresa_login(500, ['success' => false, 'error' => 'No se pudo crear credencial de portal empresa']);
}
$empresaIdLegacy = (int)$empresaLegacy['id'];
$emailLogin = (string)$parsedEmail['email'];
$stmtUpsert->bind_param('iss', $empresaIdLegacy, $emailLogin, $hash);
$stmtUpsert->execute();
$stmtUpsert->close();

$stmtReload = $mysqliOcup->prepare('SELECT id, empresa_id, email_login FROM ocupacional_empresas_portal_usuarios WHERE LOWER(email_login) = LOWER(?) LIMIT 1');
if (!$stmtReload) {
    out_empresa_login(500, ['success' => false, 'error' => 'No se pudo finalizar login empresa']);
}
$stmtReload->bind_param('s', $emailLogin);
$stmtReload->execute();
$accountReload = $stmtReload->get_result()->fetch_assoc();
$stmtReload->close();

if (!$accountReload) {
    out_empresa_login(500, ['success' => false, 'error' => 'No se pudo finalizar login empresa']);
}

$usuarioPortal = set_empresa_session($accountReload, [
    'id' => (int)$empresaLegacy['id'],
    'ruc' => (string)($empresaLegacy['ruc'] ?? ''),
    'razon_social' => (string)($empresaLegacy['razon_social'] ?? ''),
]);

out_empresa_login(200, [
    'success' => true,
    'usuario' => $usuarioPortal,
    'migrated_from_legacy' => true,
]);
