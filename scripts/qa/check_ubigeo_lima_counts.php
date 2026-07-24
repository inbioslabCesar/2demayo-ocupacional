<?php
require __DIR__ . '/../../config.php';

$idLima = 0;
$resLima = $conn->query("SELECT id FROM departamento WHERE UPPER(nombre) = 'LIMA' LIMIT 1");
if ($resLima && ($row = $resLima->fetch_assoc())) {
    $idLima = (int)$row['id'];
}

$idProvLima = 0;
if ($idLima > 0) {
    $resProv = $conn->query("SELECT id FROM provincia WHERE departamento = {$idLima} AND UPPER(nombre) = 'LIMA' LIMIT 1");
    if ($resProv && ($rowP = $resProv->fetch_assoc())) {
        $idProvLima = (int)$rowP['id'];
    }
}

$totProvLima = 0;
if ($idLima > 0) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM provincia WHERE departamento = {$idLima}");
    $totProvLima = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

$totDistProvLima = 0;
if ($idProvLima > 0) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM distrito WHERE provincia = {$idProvLima}");
    $totDistProvLima = $res ? (int)($res->fetch_assoc()['total'] ?? 0) : 0;
}

echo 'DB activa: ' . DB_NAME . PHP_EOL;
echo 'Departamento LIMA id: ' . $idLima . PHP_EOL;
echo 'Provincia LIMA id: ' . $idProvLima . PHP_EOL;
echo 'Provincias en departamento LIMA: ' . $totProvLima . PHP_EOL;
echo 'Distritos en provincia LIMA: ' . $totDistProvLima . PHP_EOL;
