<?php
require __DIR__ . '/../../db_ocupacional.php';

$sqlFile = __DIR__ . '/../../migraciones/20260724_0011_ocupacional_maestro_grupos_subgrupos.sql';
$sql = @file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer archivo SQL: {$sqlFile}" . PHP_EOL);
    exit(1);
}

if (!$mysqliOcup->multi_query($sql)) {
    fwrite(STDERR, "Error ejecutando migracion: " . $mysqliOcup->error . PHP_EOL);
    exit(1);
}

do {
    if ($res = $mysqliOcup->store_result()) {
        $res->free();
    }
} while ($mysqliOcup->more_results() && $mysqliOcup->next_result());

$checkTable = $mysqliOcup->query("SELECT 1 AS ok_table FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ocupacional_grupos_examenes' LIMIT 1");
if (!$checkTable || !$checkTable->fetch_assoc()) {
    fwrite(STDERR, "No se pudo validar tabla ocupacional_grupos_examenes" . PHP_EOL);
    exit(1);
}

$checkAgg = $mysqliOcup->query("SELECT
    SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END) AS total_grupos,
    SUM(CASE WHEN parent_id > 0 THEN 1 ELSE 0 END) AS total_subgrupos
FROM ocupacional_grupos_examenes
WHERE estado = 'activo'");
if (!$checkAgg) {
    fwrite(STDERR, "No se pudo validar conteos: " . $mysqliOcup->error . PHP_EOL);
    exit(1);
}

$agg = $checkAgg->fetch_assoc() ?: ['total_grupos' => 0, 'total_subgrupos' => 0];

$dbName = $mysqliOcup->query('SELECT DATABASE() AS db_name')->fetch_assoc();
echo 'DB ocupacional activa: ' . ($dbName['db_name'] ?? '(desconocida)') . PHP_EOL;
echo 'Grupos activos: ' . (int)($agg['total_grupos'] ?? 0) . PHP_EOL;
echo 'Subgrupos activos: ' . (int)($agg['total_subgrupos'] ?? 0) . PHP_EOL;
echo "Migracion 20260724_0011 ejecutada correctamente" . PHP_EOL;
