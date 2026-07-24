<?php
require __DIR__ . '/../../config.php';

$sqlFile = __DIR__ . '/../../migraciones/20260724_0009_ubigeo_peru_completo.sql';
$sql = @file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer archivo SQL: {$sqlFile}" . PHP_EOL);
    exit(1);
}

// Evita error de parse cuando el archivo inicia con BOM UTF-8.
$sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

if (!$conn->multi_query($sql)) {
    fwrite(STDERR, "Error ejecutando migracion: " . $conn->error . PHP_EOL);
    exit(1);
}

do {
    if ($res = $conn->store_result()) {
        $res->free();
    }
} while ($conn->more_results() && $conn->next_result());

$totDep = (int)($conn->query("SELECT COUNT(*) AS total FROM departamento")->fetch_assoc()['total'] ?? 0);
$totProv = (int)($conn->query("SELECT COUNT(*) AS total FROM provincia")->fetch_assoc()['total'] ?? 0);
$totDist = (int)($conn->query("SELECT COUNT(*) AS total FROM distrito")->fetch_assoc()['total'] ?? 0);

echo 'DB activa: ' . DB_NAME . PHP_EOL;
echo 'departamento: ' . $totDep . PHP_EOL;
echo 'provincia: ' . $totProv . PHP_EOL;
echo 'distrito: ' . $totDist . PHP_EOL;

if ($totDep < 25 || $totProv < 190 || $totDist < 1800) {
    fwrite(STDERR, "Catalogo incompleto: revise origen de datos" . PHP_EOL);
    exit(1);
}

echo "Migracion 20260724_0009 ejecutada correctamente" . PHP_EOL;
