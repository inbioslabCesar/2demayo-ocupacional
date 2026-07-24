<?php
require __DIR__ . '/../../config.php';

$sqlFile = __DIR__ . '/../../migraciones/20260724_0010_medicos_campos_legacy.sql';
$sql = @file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer archivo SQL: {$sqlFile}" . PHP_EOL);
    exit(1);
}

if (!$conn->multi_query($sql)) {
    fwrite(STDERR, "Error ejecutando migracion: " . $conn->error . PHP_EOL);
    exit(1);
}

do {
    if ($res = $conn->store_result()) {
        $res->free();
    }
} while ($conn->more_results() && $conn->next_result());

$check = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medicos' AND COLUMN_NAME IN ('dni','direccion','telefono','celular') ORDER BY COLUMN_NAME");
if (!$check) {
    fwrite(STDERR, "No se pudo validar columnas: " . $conn->error . PHP_EOL);
    exit(1);
}

$cols = [];
while ($row = $check->fetch_assoc()) {
    $cols[] = (string)$row['COLUMN_NAME'];
}

echo 'DB activa: ' . DB_NAME . PHP_EOL;
echo 'Campos legacy medicos detectados: ' . implode(', ', $cols) . PHP_EOL;
echo "Migracion 20260724_0010 ejecutada correctamente" . PHP_EOL;
