<?php

require_once __DIR__ . '/config.php';

$ocupHost = getenv('DB_OCUP_HOST') ?: DB_HOST;
$ocupName = getenv('DB_OCUP_NAME') ?: '2demayo_so';
$ocupUser = getenv('DB_OCUP_USER') ?: DB_USER;
$ocupPass = getenv('DB_OCUP_PASS');
if ($ocupPass === false) {
    $ocupPass = DB_PASS;
}
$ocupPort = (int) (getenv('DB_OCUP_PORT') ?: 3306);
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
