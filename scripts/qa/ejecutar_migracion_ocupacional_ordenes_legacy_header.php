<?php
require __DIR__ . '/../../db_ocupacional.php';

$sqlFile = __DIR__ . '/../../sql/2026-07-24_ocupacional_fase3_ordenes_campos_legacy_header_idempotente.sql';
$sql = @file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer archivo SQL: {$sqlFile}" . PHP_EOL);
    exit(1);
}

if (!$mysqliOcup->multi_query($sql)) {
    fwrite(STDERR, 'Error ejecutando migracion: ' . $mysqliOcup->error . PHP_EOL);
    exit(1);
}

do {
    if ($res = $mysqliOcup->store_result()) {
        $res->free();
    }
} while ($mysqliOcup->more_results() && $mysqliOcup->next_result());

$cols = [
    'subcontrata_empresa_id',
    'facturar_empresa_id',
    'firma_doctor',
    'modo',
    'gestante',
    'documento',
    'indica_dr',
];

$in = "'" . implode("','", $cols) . "'";
$sqlCheck = "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'ocupacional_ordenes' AND column_name IN ({$in}) ORDER BY column_name";
$resCheck = $mysqliOcup->query($sqlCheck);
if (!$resCheck) {
    fwrite(STDERR, 'No se pudo validar columnas: ' . $mysqliOcup->error . PHP_EOL);
    exit(1);
}

$found = [];
while ($r = $resCheck->fetch_assoc()) {
    $val = '';
    if (array_key_exists('column_name', $r)) {
        $val = (string)$r['column_name'];
    } elseif (array_key_exists('COLUMN_NAME', $r)) {
        $val = (string)$r['COLUMN_NAME'];
    } elseif (!empty($r)) {
        $val = (string)reset($r);
    }
    if ($val !== '') {
        $found[] = $val;
    }
}

$missing = array_values(array_diff($cols, $found));
if (!empty($missing)) {
    fwrite(STDERR, 'Faltan columnas tras migracion: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$dbName = $mysqliOcup->query('SELECT DATABASE() AS db_name')->fetch_assoc();
echo 'DB ocupacional activa: ' . ($dbName['db_name'] ?? '(desconocida)') . PHP_EOL;
echo 'Columnas verificadas en ocupacional_ordenes: ' . implode(', ', $found) . PHP_EOL;
echo 'Migracion legacy header de ordenes ejecutada correctamente' . PHP_EOL;
