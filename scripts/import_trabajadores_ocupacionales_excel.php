<?php
/**
 * Importador masivo FASE 1: trabajador-empresa (sin ordenes/certificados).
 *
 * Objetivo:
 * - Crear/actualizar paciente clinico por DNI (tabla pacientes).
 * - Crear/reactivar/actualizar relacion ocupacional en pacientes_ocupacionales.
 *
 * Uso:
 *   php scripts/import_trabajadores_ocupacionales_excel.php --file="C:/ruta/lote.xlsx" --empresa-id=12 --dry-run=1
 *   php scripts/import_trabajadores_ocupacionales_excel.php --file="C:/ruta/lote.xlsx" --empresa-ruc=20123456789 --dry-run=0
 *
 * Parametros:
 *   --file=RUTA_XLSX                  obligatorio
 *   --empresa-id=ID                   opcional (recomendado)
 *   --empresa-ruc=RUC                 opcional (si no se envia empresa-id)
 *   --sheet=1                         opcional (default 1)
 *   --header-row=N                    opcional (1-based, autodetecta si no se envia)
 *   --dry-run=1|0                     opcional (default 1)
 *   --upsert-paciente=1|0             opcional (default 1)
 *   --sync-core=1|0                   opcional (default 0) actualiza nombre/apellido/sexo en paciente existente
 *   --update-activo=1|0               opcional (default 1) actualiza puesto/area/fecha si trabajador ya activo
 *   --default-fecha-ingreso=YYYY-MM-DD opcional
 *   --default-puesto=TEXTO            opcional
 *   --default-area=TEXTO              opcional
 *   --default-sexo=M|F                opcional
 *   --error-file=RUTA_CSV             opcional
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_ocupacional.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(400);
    echo "Este script debe ejecutarse por CLI." . PHP_EOL;
    exit(1);
}

$options = getopt('', [
    'file:',
    'empresa-id::',
    'empresa-ruc::',
    'sheet::',
    'header-row::',
    'dry-run::',
    'upsert-paciente::',
    'sync-core::',
    'update-activo::',
    'default-fecha-ingreso::',
    'default-puesto::',
    'default-area::',
    'default-sexo::',
    'error-file::',
]);

$filePath = isset($options['file']) ? trim((string)$options['file']) : '';
$empresaIdArg = isset($options['empresa-id']) ? (int)$options['empresa-id'] : 0;
$empresaRucArg = isset($options['empresa-ruc']) ? preg_replace('/\D+/', '', (string)$options['empresa-ruc']) : '';
$sheetNumber = isset($options['sheet']) ? max(1, (int)$options['sheet']) : 1;
$headerRowNumber = isset($options['header-row']) ? max(1, (int)$options['header-row']) : 0;
$dryRun = isset($options['dry-run']) ? ((int)$options['dry-run'] === 1) : true;
$upsertPaciente = isset($options['upsert-paciente']) ? ((int)$options['upsert-paciente'] === 1) : true;
$syncCore = isset($options['sync-core']) ? ((int)$options['sync-core'] === 1) : false;
$updateActivo = isset($options['update-activo']) ? ((int)$options['update-activo'] === 1) : true;
$defaultFechaIngreso = isset($options['default-fecha-ingreso']) ? trim((string)$options['default-fecha-ingreso']) : '';
$defaultPuesto = isset($options['default-puesto']) ? trim((string)$options['default-puesto']) : '';
$defaultArea = isset($options['default-area']) ? trim((string)$options['default-area']) : '';
$defaultSexo = isset($options['default-sexo']) ? normalize_sexo((string)$options['default-sexo']) : '';
$errorFileOption = isset($options['error-file']) ? trim((string)$options['error-file']) : '';

if ($filePath === '') {
    echo "ERROR: Debe indicar --file=RUTA_XLSX" . PHP_EOL;
    exit(1);
}
if (!is_file($filePath)) {
    echo "ERROR: Archivo no encontrado: {$filePath}" . PHP_EOL;
    exit(1);
}
if (!class_exists('ZipArchive')) {
    echo "ERROR: Extension ZipArchive no disponible en PHP." . PHP_EOL;
    exit(1);
}
if ($empresaIdArg <= 0 && $empresaRucArg === '') {
    echo "ERROR: Debe indicar --empresa-id o --empresa-ruc" . PHP_EOL;
    exit(1);
}
if ($defaultFechaIngreso !== '' && normalize_date_value($defaultFechaIngreso) === null) {
    echo "ERROR: --default-fecha-ingreso debe tener formato valido (YYYY-MM-DD o DD/MM/YYYY)." . PHP_EOL;
    exit(1);
}

function normalize_header($text)
{
    $value = trim((string)$text);
    if ($value === '') {
        return '';
    }
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = strtolower((string)$value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function normalize_text($value)
{
    return trim((string)$value);
}

function normalize_doc_tipo($value)
{
    $v = strtoupper(trim((string)$value));
    if ($v === '') {
        return 'DNI';
    }
    if ($v === 'DNI') {
        return 'DNI';
    }
    if ($v === 'CE' || $v === 'CARNET_EXTRANJERIA' || $v === 'CARNET DE EXTRANJERIA') {
        return 'CE';
    }
    if ($v === 'PASAPORTE' || $v === 'PASS') {
        return 'PASAPORTE';
    }
    return $v;
}

function normalize_sexo($value)
{
    $v = strtoupper(trim((string)$value));
    if ($v === '') {
        return '';
    }
    if (in_array($v, ['M', 'MASCULINO', 'HOMBRE', 'VARON'], true)) {
        return 'M';
    }
    if (in_array($v, ['F', 'FEMENINO', 'MUJER'], true)) {
        return 'F';
    }
    return '';
}

function normalize_date_value($value)
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    if (is_numeric($raw)) {
        $days = (int)floor((float)$raw);
        if ($days > 0) {
            $unix = ($days - 25569) * 86400;
            if ($unix > 0) {
                return gmdate('Y-m-d', $unix);
            }
        }
    }

    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt && $dt->format($format) === $raw) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function col_ref_to_index($cellRef)
{
    if (!preg_match('/^([A-Z]+)/', strtoupper((string)$cellRef), $m)) {
        return -1;
    }
    $letters = $m[1];
    $index = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

function read_xlsx_rows($xlsxFile, $sheetNumber)
{
    $zip = new ZipArchive();
    if ($zip->open($xlsxFile) !== true) {
        throw new RuntimeException('No se pudo abrir el XLSX.');
    }

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relsXml === false) {
        $zip->close();
        throw new RuntimeException('Estructura XLSX invalida (workbook).');
    }

    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);

    $relMap = [];
    foreach ($rels->Relationship as $rel) {
        $relMap[(string)$rel['Id']] = (string)$rel['Target'];
    }

    $sheetIndex = 0;
    $sheetPath = '';
    foreach ($workbook->sheets->sheet as $sheet) {
        $sheetIndex++;
        if ($sheetIndex !== $sheetNumber) {
            continue;
        }
        $rid = (string)$sheet->attributes('r', true)->id;
        if (!isset($relMap[$rid])) {
            break;
        }
        $sheetPath = 'xl/' . ltrim($relMap[$rid], '/');
        break;
    }

    if ($sheetPath === '') {
        $zip->close();
        throw new RuntimeException('No se encontro la hoja solicitada.');
    }

    $sheetXml = $zip->getFromName($sheetPath);
    if ($sheetXml === false) {
        $zip->close();
        throw new RuntimeException('No se pudo leer la hoja del XLSX.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = simplexml_load_string($sharedXml);
        foreach ($shared->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else {
                $text = '';
                foreach ($si->r as $run) {
                    $text .= (string)$run->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    $sheetData = simplexml_load_string($sheetXml);
    $rows = [];

    foreach ($sheetData->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $idx = col_ref_to_index((string)$cell['r']);
            if ($idx < 0) {
                continue;
            }
            $type = (string)$cell['t'];
            $value = '';
            if (isset($cell->v)) {
                $raw = (string)$cell->v;
                if ($type === 's') {
                    $sIdx = (int)$raw;
                    $value = $sharedStrings[$sIdx] ?? '';
                } else {
                    $value = $raw;
                }
            } elseif (isset($cell->is->t)) {
                $value = (string)$cell->is->t;
            }
            $cells[$idx] = $value;
        }

        if (!empty($cells)) {
            ksort($cells);
            $maxIndex = max(array_keys($cells));
            $flat = array_fill(0, $maxIndex + 1, '');
            foreach ($cells as $i => $v) {
                $flat[$i] = $v;
            }
            $rows[] = $flat;
        }
    }

    $zip->close();
    return $rows;
}

function detect_header_row_index($rows, $aliases)
{
    $maxScan = min(count($rows), 20);
    $bestIndex = -1;
    $bestScore = -1;

    for ($i = 0; $i < $maxScan; $i++) {
        $row = $rows[$i] ?? [];
        $normalized = [];
        foreach ($row as $cell) {
            $normalized[] = normalize_header($cell);
        }

        $score = 0;
        foreach ($aliases as $group) {
            foreach ($group as $candidate) {
                if (in_array($candidate, $normalized, true)) {
                    $score++;
                    break;
                }
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestIndex = $i;
        }
    }

    return $bestScore >= 2 ? $bestIndex : -1;
}

function split_full_name($fullName)
{
    $clean = preg_replace('/\s+/', ' ', trim((string)$fullName));
    if ($clean === '') {
        return ['', ''];
    }
    $parts = array_values(array_filter(explode(' ', $clean), function ($v) {
        return trim((string)$v) !== '';
    }));

    if (count($parts) >= 4) {
        $apellido = implode(' ', array_slice($parts, 0, 2));
        $nombre = implode(' ', array_slice($parts, 2));
        return [$nombre, $apellido];
    }
    if (count($parts) === 3) {
        return [$parts[2], $parts[0] . ' ' . $parts[1]];
    }
    if (count($parts) === 2) {
        return [$parts[1], $parts[0]];
    }
    return ['', $parts[0]];
}

function csv_escape($value)
{
    $text = str_replace('"', '""', (string)$value);
    return '"' . $text . '"';
}

function write_error_report_csv($path, $rows)
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear directorio para reporte: ' . $dir);
        }
    }

    $fp = fopen($path, 'wb');
    if ($fp === false) {
        throw new RuntimeException('No se pudo escribir reporte: ' . $path);
    }

    $headers = [
        'linea',
        'motivo',
        'dni',
        'nombre',
        'apellido',
        'sexo',
        'empresa_id',
        'puesto_trabajo',
        'fecha_ingreso',
    ];
    fwrite($fp, implode(',', array_map('csv_escape', $headers)) . PHP_EOL);

    foreach ($rows as $row) {
        $line = [
            $row['linea'] ?? '',
            $row['motivo'] ?? '',
            $row['dni'] ?? '',
            $row['nombre'] ?? '',
            $row['apellido'] ?? '',
            $row['sexo'] ?? '',
            $row['empresa_id'] ?? '',
            $row['puesto_trabajo'] ?? '',
            $row['fecha_ingreso'] ?? '',
        ];
        fwrite($fp, implode(',', array_map('csv_escape', $line)) . PHP_EOL);
    }

    fclose($fp);
}

function build_default_error_report_path($sourceFilePath)
{
    $baseDir = __DIR__;
    $baseName = pathinfo((string)$sourceFilePath, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$baseName);
    if ($baseName === '') {
        $baseName = 'trabajadores';
    }
    $stamp = date('Ymd_His');
    return $baseDir . '/import_errors_trabajadores_' . $baseName . '_' . $stamp . '.csv';
}

function empresa_id_resolver($mysqliOcup, $empresaIdArg, $empresaRucArg)
{
    if ($empresaIdArg > 0) {
        $stmt = $mysqliOcup->prepare('SELECT id, ruc, razon_social, estado FROM empresas_ocupacionales WHERE id = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar consulta de empresa por id.');
        }
        $stmt->bind_param('i', $empresaIdArg);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new RuntimeException('No existe empresa ocupacional para --empresa-id=' . $empresaIdArg);
        }
        return $row;
    }

    $stmt = $mysqliOcup->prepare('SELECT id, ruc, razon_social, estado FROM empresas_ocupacionales WHERE ruc = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar consulta de empresa por ruc.');
    }
    $stmt->bind_param('s', $empresaRucArg);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('No existe empresa ocupacional para --empresa-ruc=' . $empresaRucArg);
    }
    return $row;
}

function hc_next_num_init($mysqliCore)
{
    $stmt = $mysqliCore->prepare("SELECT historia_clinica
                                  FROM pacientes
                                  WHERE historia_clinica REGEXP '^HC[0-9]+$'
                                  ORDER BY CAST(SUBSTRING(historia_clinica, 3) AS UNSIGNED) DESC
                                  LIMIT 1");
    if (!$stmt) {
        return 1;
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $last = (string)($row['historia_clinica'] ?? '');
    if (preg_match('/^HC(\d+)$/', $last, $m)) {
        return ((int)$m[1]) + 1;
    }
    return 1;
}

function hc_next_value(&$hcNextNum)
{
    $value = 'HC' . str_pad((string)$hcNextNum, 6, '0', STR_PAD_LEFT);
    $hcNextNum++;
    return $value;
}

function find_paciente_by_dni($mysqliCore, $dni)
{
    $stmt = $mysqliCore->prepare('SELECT id, dni, nombre, apellido, sexo FROM pacientes WHERE dni = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar busqueda de paciente por dni.');
    }
    $stmt->bind_param('s', $dni);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function insert_paciente_core($mysqliCore, $data)
{
    $stmt = $mysqliCore->prepare('INSERT INTO pacientes (dni, nombre, apellido, historia_clinica, fecha_nacimiento, sexo, tipo_documento)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar insercion de paciente.');
    }
    $stmt->bind_param(
        'sssssss',
        $data['dni'],
        $data['nombre'],
        $data['apellido'],
        $data['historia_clinica'],
        $data['fecha_nacimiento'],
        $data['sexo'],
        $data['tipo_documento']
    );
    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('No se pudo insertar paciente: ' . $msg);
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function update_paciente_core_min($mysqliCore, $id, $data)
{
    $sets = [];
    $params = [];
    $types = '';

    if ($data['nombre'] !== '') {
        $sets[] = 'nombre = ?';
        $params[] = $data['nombre'];
        $types .= 's';
    }
    if ($data['apellido'] !== '') {
        $sets[] = 'apellido = ?';
        $params[] = $data['apellido'];
        $types .= 's';
    }
    if ($data['sexo'] !== '') {
        $sets[] = 'sexo = ?';
        $params[] = $data['sexo'];
        $types .= 's';
    }
    if ($data['fecha_nacimiento'] !== null && $data['fecha_nacimiento'] !== '') {
        $sets[] = 'fecha_nacimiento = ?';
        $params[] = $data['fecha_nacimiento'];
        $types .= 's';
    }

    if (empty($sets)) {
        return;
    }

    $sql = 'UPDATE pacientes SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
    $stmt = $mysqliCore->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar actualizacion de paciente.');
    }
    $params[] = (int)$id;
    $types .= 'i';

    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('No se pudo actualizar paciente: ' . $msg);
    }
    $stmt->close();
}

function find_trabajador_ocup($mysqliOcup, $empresaId, $externalPatientId)
{
    $stmt = $mysqliOcup->prepare('SELECT id, estado_laboral
                                  FROM pacientes_ocupacionales
                                  WHERE empresa_id = ? AND external_patient_id = ?
                                  LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar busqueda de trabajador ocupacional.');
    }
    $stmt->bind_param('ii', $empresaId, $externalPatientId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function insert_trabajador_ocup($mysqliOcup, $payload)
{
    $stmt = $mysqliOcup->prepare('INSERT INTO pacientes_ocupacionales
                                  (empresa_id, external_patient_id, documento_tipo, documento_numero, puesto_trabajo, area_riesgo, tipo_contrato, estado_laboral, fecha_ingreso)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, "activo", ?)');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar insercion de trabajador ocupacional.');
    }
    $stmt->bind_param(
        'iissssss',
        $payload['empresa_id'],
        $payload['external_patient_id'],
        $payload['documento_tipo'],
        $payload['documento_numero'],
        $payload['puesto_trabajo'],
        $payload['area_riesgo'],
        $payload['tipo_contrato'],
        $payload['fecha_ingreso']
    );
    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('No se pudo insertar trabajador ocupacional: ' . $msg);
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function update_trabajador_ocup_activo($mysqliOcup, $id, $payload)
{
    $stmt = $mysqliOcup->prepare('UPDATE pacientes_ocupacionales
                                  SET documento_tipo = ?, documento_numero = ?, puesto_trabajo = ?, area_riesgo = ?, tipo_contrato = ?, fecha_ingreso = ?, updated_at = NOW()
                                  WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar actualizacion de trabajador activo.');
    }
    $stmt->bind_param(
        'ssssssi',
        $payload['documento_tipo'],
        $payload['documento_numero'],
        $payload['puesto_trabajo'],
        $payload['area_riesgo'],
        $payload['tipo_contrato'],
        $payload['fecha_ingreso'],
        $id
    );
    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('No se pudo actualizar trabajador activo: ' . $msg);
    }
    $stmt->close();
}

function reactivate_trabajador_ocup($mysqliOcup, $id, $payload)
{
    $stmt = $mysqliOcup->prepare('UPDATE pacientes_ocupacionales
                                  SET documento_tipo = ?, documento_numero = ?, puesto_trabajo = ?, area_riesgo = ?, tipo_contrato = ?,
                                      fecha_ingreso = ?, estado_laboral = "activo", anulacion_motivo = NULL, anulado_at = NULL, anulado_by = NULL,
                                      updated_at = NOW()
                                  WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar reactivacion de trabajador.');
    }
    $stmt->bind_param(
        'ssssssi',
        $payload['documento_tipo'],
        $payload['documento_numero'],
        $payload['puesto_trabajo'],
        $payload['area_riesgo'],
        $payload['tipo_contrato'],
        $payload['fecha_ingreso'],
        $id
    );
    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('No se pudo reactivar trabajador: ' . $msg);
    }
    $stmt->close();
}

try {
    $empresa = empresa_id_resolver($mysqliOcup, $empresaIdArg, $empresaRucArg);
    $empresaId = (int)$empresa['id'];

    if ((string)($empresa['estado'] ?? '') !== 'activo') {
        throw new RuntimeException('La empresa seleccionada esta inactiva.');
    }

    $rows = read_xlsx_rows($filePath, $sheetNumber);
    if (count($rows) < 2) {
        throw new RuntimeException('El archivo no contiene datos (cabecera + filas).');
    }

    $aliases = [
        'dni' => ['dni', 'documento', 'numero_documento', 'nro_documento'],
        'tipo_documento' => ['tipo_documento', 'tipo_doc'],
        'nombres' => ['nombres', 'nombre'],
        'apellidos' => ['apellidos', 'apellido'],
        'apellidos_nombres' => ['apellidos_nombres', 'apellidos_y_nombres', 'nombre_completo'],
        'genero' => ['genero', 'sexo'],
        'fecha_nacimiento' => ['fecha_nacimiento', 'fec_nac', 'fecha_de_nacimiento'],
        'puesto_trabajo' => ['cargo_categoria', 'cargo', 'puesto_trabajo', 'puesto', 'ocupacion'],
        'area_riesgo' => ['area', 'area_riesgo'],
        'fecha_ingreso' => ['fecha_ingreso', 'fecha_ingreso_empresa'],
        'tipo_contrato' => ['tipo_contrato'],
    ];

    $headerIndex = $headerRowNumber > 0 ? ($headerRowNumber - 1) : detect_header_row_index($rows, $aliases);
    if (!isset($rows[$headerIndex])) {
        throw new RuntimeException('No se pudo resolver fila de cabecera. Use --header-row=N');
    }

    $header = $rows[$headerIndex];
    $headerMap = [];
    foreach ($header as $i => $name) {
        $normalized = normalize_header($name);
        if ($normalized !== '') {
            $headerMap[$normalized] = $i;
        }
    }

    $colIndex = [];
    foreach ($aliases as $target => $candidates) {
        $colIndex[$target] = -1;
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $headerMap)) {
                $colIndex[$target] = $headerMap[$candidate];
                break;
            }
        }
    }

    $dataRows = array_slice($rows, $headerIndex + 1);

    $coreInserted = 0;
    $coreUpdated = 0;
    $coreSkipped = 0;
    $ocupInserted = 0;
    $ocupUpdated = 0;
    $ocupReactivated = 0;
    $ocupSkipped = 0;
    $emptyRows = 0;
    $errors = 0;
    $errorRows = [];

    $errorReportPath = $errorFileOption !== '' ? $errorFileOption : build_default_error_report_path($filePath);

    $hcNextNum = hc_next_num_init($mysqli);

    $mysqli->begin_transaction();
    $mysqliOcup->begin_transaction();

    $line = $headerIndex + 1;
    foreach ($dataRows as $row) {
        $line++;

        $get = function ($key) use ($colIndex, $row) {
            $idx = $colIndex[$key] ?? -1;
            if ($idx < 0) {
                return '';
            }
            return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
        };

        $dni = preg_replace('/\D+/', '', $get('dni'));
        $tipoDocumento = normalize_doc_tipo($get('tipo_documento'));
        $nombre = normalize_text($get('nombres'));
        $apellido = normalize_text($get('apellidos'));
        $full = normalize_text($get('apellidos_nombres'));
        if (($nombre === '' || $apellido === '') && $full !== '') {
            list($nameSplit, $lastSplit) = split_full_name($full);
            if ($nombre === '') {
                $nombre = $nameSplit;
            }
            if ($apellido === '') {
                $apellido = $lastSplit;
            }
        }

        $sexo = normalize_sexo($get('genero'));
        if ($sexo === '' && $defaultSexo !== '') {
            $sexo = $defaultSexo;
        }

        $fechaNacimiento = normalize_date_value($get('fecha_nacimiento'));

        $puesto = normalize_text($get('puesto_trabajo'));
        if ($puesto === '' && $defaultPuesto !== '') {
            $puesto = $defaultPuesto;
        }

        $area = normalize_text($get('area_riesgo'));
        if ($area === '' && $defaultArea !== '') {
            $area = $defaultArea;
        }

        $fechaIngreso = normalize_date_value($get('fecha_ingreso'));
        if ($fechaIngreso === null && $defaultFechaIngreso !== '') {
            $fechaIngreso = normalize_date_value($defaultFechaIngreso);
        }

        $tipoContrato = normalize_text($get('tipo_contrato'));

        if ($dni === '' && $nombre === '' && $apellido === '' && $puesto === '') {
            $emptyRows++;
            continue;
        }

        $motivos = [];
        if ($dni === '' || !preg_match('/^\d{8}$/', $dni)) {
            $motivos[] = 'dni invalido (debe ser 8 digitos)';
        }
        if ($nombre === '') {
            $motivos[] = 'nombre vacio';
        }
        if ($apellido === '') {
            $motivos[] = 'apellido vacio';
        }
        if ($sexo === '') {
            $motivos[] = 'sexo vacio/no reconocible (M/F)';
        }
        if ($puesto === '') {
            $motivos[] = 'puesto_trabajo vacio';
        }
        if ($fechaIngreso === null) {
            $motivos[] = 'fecha_ingreso invalida';
        }

        if (!empty($motivos)) {
            $errors++;
            $errorRows[] = [
                'linea' => $line,
                'motivo' => implode('; ', $motivos),
                'dni' => $dni,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'sexo' => $sexo,
                'empresa_id' => $empresaId,
                'puesto_trabajo' => $puesto,
                'fecha_ingreso' => $fechaIngreso ?? '',
            ];
            continue;
        }

        try {
            $paciente = find_paciente_by_dni($mysqli, $dni);
            $externalPatientId = 0;

            if ($paciente) {
                $externalPatientId = (int)$paciente['id'];
                if ($syncCore) {
                    update_paciente_core_min($mysqli, $externalPatientId, [
                        'nombre' => $nombre,
                        'apellido' => $apellido,
                        'sexo' => $sexo,
                        'fecha_nacimiento' => $fechaNacimiento,
                    ]);
                    $coreUpdated++;
                } else {
                    $coreSkipped++;
                }
            } else {
                if (!$upsertPaciente) {
                    throw new RuntimeException('paciente clinico no existe y upsert-paciente=0');
                }
                $externalPatientId = insert_paciente_core($mysqli, [
                    'dni' => $dni,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'historia_clinica' => hc_next_value($hcNextNum),
                    'fecha_nacimiento' => $fechaNacimiento,
                    'sexo' => $sexo,
                    'tipo_documento' => strtolower($tipoDocumento === 'DNI' ? 'dni' : ($tipoDocumento === 'CE' ? 'carnet_extranjeria' : 'pasaporte')),
                ]);
                $coreInserted++;
            }

            $ocupPayload = [
                'empresa_id' => $empresaId,
                'external_patient_id' => $externalPatientId,
                'documento_tipo' => $tipoDocumento,
                'documento_numero' => $dni,
                'puesto_trabajo' => $puesto,
                'area_riesgo' => $area,
                'tipo_contrato' => $tipoContrato,
                'fecha_ingreso' => $fechaIngreso,
            ];

            $trab = find_trabajador_ocup($mysqliOcup, $empresaId, $externalPatientId);
            if (!$trab) {
                insert_trabajador_ocup($mysqliOcup, $ocupPayload);
                $ocupInserted++;
            } else {
                $estado = (string)($trab['estado_laboral'] ?? '');
                $trabId = (int)$trab['id'];
                if ($estado === 'activo') {
                    if ($updateActivo) {
                        update_trabajador_ocup_activo($mysqliOcup, $trabId, $ocupPayload);
                        $ocupUpdated++;
                    } else {
                        $ocupSkipped++;
                    }
                } else {
                    reactivate_trabajador_ocup($mysqliOcup, $trabId, $ocupPayload);
                    $ocupReactivated++;
                }
            }
        } catch (Throwable $e) {
            $errors++;
            $errorRows[] = [
                'linea' => $line,
                'motivo' => $e->getMessage(),
                'dni' => $dni,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'sexo' => $sexo,
                'empresa_id' => $empresaId,
                'puesto_trabajo' => $puesto,
                'fecha_ingreso' => $fechaIngreso ?? '',
            ];
        }
    }

    if ($dryRun || $errors > 0) {
        $mysqli->rollback();
        $mysqliOcup->rollback();
    } else {
        $mysqli->commit();
        $mysqliOcup->commit();
    }

    if (!empty($errorRows)) {
        write_error_report_csv($errorReportPath, $errorRows);
    }

    echo PHP_EOL;
    echo 'Archivo: ' . $filePath . PHP_EOL;
    echo 'Sheet: ' . $sheetNumber . PHP_EOL;
    echo 'Empresa: [' . $empresaId . '] ' . (string)($empresa['razon_social'] ?? '') . ' (RUC ' . (string)($empresa['ruc'] ?? '') . ')' . PHP_EOL;
    echo 'Dry-run: ' . ($dryRun ? 'SI' : 'NO') . PHP_EOL;
    echo 'Upsert paciente: ' . ($upsertPaciente ? 'SI' : 'NO') . PHP_EOL;
    echo 'Sync core: ' . ($syncCore ? 'SI' : 'NO') . PHP_EOL;
    echo 'Update activo: ' . ($updateActivo ? 'SI' : 'NO') . PHP_EOL;
    echo str_repeat('-', 72) . PHP_EOL;
    echo 'Pacientes insertados: ' . $coreInserted . PHP_EOL;
    echo 'Pacientes actualizados: ' . $coreUpdated . PHP_EOL;
    echo 'Pacientes sin cambios: ' . $coreSkipped . PHP_EOL;
    echo 'Trabajadores insertados: ' . $ocupInserted . PHP_EOL;
    echo 'Trabajadores actualizados: ' . $ocupUpdated . PHP_EOL;
    echo 'Trabajadores reactivados: ' . $ocupReactivated . PHP_EOL;
    echo 'Trabajadores omitidos: ' . $ocupSkipped . PHP_EOL;
    echo 'Filas vacias: ' . $emptyRows . PHP_EOL;
    echo 'Errores: ' . $errors . PHP_EOL;

    if (!empty($errorRows)) {
        echo 'Reporte de errores: ' . $errorReportPath . PHP_EOL;
    }

    if ($dryRun) {
        echo 'Resultado: SIMULADO (sin cambios persistidos).' . PHP_EOL;
    } elseif ($errors > 0) {
        echo 'Resultado: ROLLBACK por errores (sin cambios persistidos).' . PHP_EOL;
    } else {
        echo 'Resultado: OK (cambios persistidos).' . PHP_EOL;
    }

    exit(0);
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        try {
            $mysqli->rollback();
        } catch (Throwable $ignored) {
        }
    }
    if (isset($mysqliOcup) && $mysqliOcup instanceof mysqli) {
        try {
            $mysqliOcup->rollback();
        } catch (Throwable $ignored) {
        }
    }

    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
