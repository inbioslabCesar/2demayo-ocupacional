<?php

require_once __DIR__ . '/config.php';

$ocupRuntimeHost = isset($runtimeConfig['DB_OCUP_HOST']) ? (string) $runtimeConfig['DB_OCUP_HOST'] : '';
$ocupRuntimeName = isset($runtimeConfig['DB_OCUP_NAME']) ? (string) $runtimeConfig['DB_OCUP_NAME'] : '';
$ocupRuntimeUser = isset($runtimeConfig['DB_OCUP_USER']) ? (string) $runtimeConfig['DB_OCUP_USER'] : '';
$ocupRuntimePass = isset($runtimeConfig['DB_OCUP_PASS']) ? (string) $runtimeConfig['DB_OCUP_PASS'] : '';
$ocupRuntimePort = isset($runtimeConfig['DB_OCUP_PORT']) ? (int) $runtimeConfig['DB_OCUP_PORT'] : 0;

$ocupHost = getenv('DB_OCUP_HOST');
if ($ocupHost === false || $ocupHost === '') {
    $ocupHost = $ocupRuntimeHost !== '' ? $ocupRuntimeHost : DB_HOST;
}

$ocupName = getenv('DB_OCUP_NAME');
if ($ocupName === false || $ocupName === '') {
    if ($ocupRuntimeName !== '') {
        $ocupName = $ocupRuntimeName;
    } elseif (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        $ocupName = DB_NAME;
    } else {
        $ocupName = '2demayo_so';
    }
}

$ocupUser = getenv('DB_OCUP_USER');
if ($ocupUser === false || $ocupUser === '') {
    $ocupUser = $ocupRuntimeUser !== '' ? $ocupRuntimeUser : DB_USER;
}

$ocupPass = getenv('DB_OCUP_PASS');
if ($ocupPass === false || $ocupPass === '') {
    if ($ocupRuntimePass !== '') {
        $ocupPass = $ocupRuntimePass;
    } else {
        $ocupPass = DB_PASS;
    }
}

$ocupPortEnv = getenv('DB_OCUP_PORT');
if ($ocupPortEnv === false || $ocupPortEnv === '') {
    $ocupPort = $ocupRuntimePort > 0 ? $ocupRuntimePort : 3306;
} else {
    $ocupPort = (int) $ocupPortEnv;
}
if ($ocupPort <= 0) {
    $ocupPort = 3306;
}

$mysqliOcup = new mysqli($ocupHost, $ocupUser, $ocupPass, $ocupName, $ocupPort);
if ($mysqliOcup->connect_errno) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo conectar a la BD de salud ocupacional'
    ]);
    exit;
}

$mysqliOcup->set_charset('utf8mb4');
$mysqliOcup->query("SET time_zone = '-05:00'");

try {
    $dsn = 'mysql:host=' . $ocupHost . ';dbname=' . $ocupName . ';port=' . $ocupPort;
    $pdoOcup = new PDO($dsn, $ocupUser, $ocupPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
    ]);
    $pdoOcup->exec("SET time_zone = '-05:00'");
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo inicializar PDO para salud ocupacional'
    ]);
    exit;
}
