<?php
require __DIR__ . '/../../config.php';

$tablesToCheck = ['departamento', 'provincia', 'distrito', 'ubigeo_departamento', 'ubigeo_provincia', 'ubigeo_distrito'];
$in = "'" . implode("','", $tablesToCheck) . "'";

$sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ($in) ORDER BY table_name";
$res = $conn->query($sql);
if (!$res) {
    fwrite(STDERR, "Error consultando tablas: " . $conn->error . PHP_EOL);
    exit(1);
}

$found = [];
while ($row = $res->fetch_assoc()) {
    $key = array_key_exists('table_name', $row) ? 'table_name' : (array_key_exists('TABLE_NAME', $row) ? 'TABLE_NAME' : null);
    if ($key !== null) {
        $found[] = (string)$row[$key];
    }
}

echo 'DB activa: ' . DB_NAME . PHP_EOL;
echo 'Tablas encontradas: ' . (empty($found) ? '(ninguna)' : implode(', ', $found)) . PHP_EOL;

foreach (['departamento', 'provincia', 'distrito'] as $t) {
    if (!in_array($t, $found, true)) {
        continue;
    }
    $r = $conn->query("SELECT COUNT(*) AS total FROM {$t}");
    $n = $r ? (int)($r->fetch_assoc()['total'] ?? 0) : -1;
    echo "{$t}: {$n} registros" . PHP_EOL;
}

if (in_array('departamento', $found, true)) {
    $r = $conn->query("SELECT id, nombre FROM departamento WHERE UPPER(nombre) LIKE 'LIMA%' ORDER BY id ASC LIMIT 5");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            echo 'LIMA departamento -> id=' . $row['id'] . ', nombre=' . $row['nombre'] . PHP_EOL;
        }
    }
}
