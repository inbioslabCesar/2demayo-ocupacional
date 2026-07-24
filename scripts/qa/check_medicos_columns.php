<?php
require_once __DIR__ . '/../../config.php';

$res = $conn->query('SHOW COLUMNS FROM medicos');
if (!$res) {
    fwrite(STDERR, "ERROR: " . $conn->error . PHP_EOL);
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . PHP_EOL;
}
