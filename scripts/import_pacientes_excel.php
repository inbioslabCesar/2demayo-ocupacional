<?php
/**
 * Importador de pacientes desde XLSX (sin dependencias externas).
 *
 * Uso CLI:
 *   php scripts/import_pacientes_excel.php --file="C:/ruta/pacientes.xlsx" --upsert=1 --dry-run=1
 *
 * Parametros:
 *   --file=RUTA_XLSX   (obligatorio)
 *   --upsert=1|0       (default 1) actualiza por DNI si ya existe
 *   --dry-run=1|0      (default 1) simula sin guardar
 *   --sheet=1          (default 1) numero de hoja, empezando en 1
 *   --header-row=N     (opcional) fila de cabecera (1-based); por defecto autodetecta
 *   --default-sexo=M|F (opcional) sexo por defecto si el Excel no lo trae
 *   --error-file=RUTA  (opcional) ruta del CSV de errores
 */

require_once __DIR__ . '/../config.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(400);
    echo "Este script debe ejecutarse por CLI." . PHP_EOL;
    exit(1);
}

$options = getopt('', ['file:', 'upsert::', 'dry-run::', 'sheet::', 'header-row::', 'default-sexo::', 'error-file::']);
$filePath = isset($options['file']) ? trim((string)$options['file']) : '';
$upsert = isset($options['upsert']) ? (int)$options['upsert'] === 1 : true;
$dryRun = isset($options['dry-run']) ? (int)$options['dry-run'] === 1 : true;
$sheetNumber = isset($options['sheet']) ? max(1, (int)$options['sheet']) : 1;
$headerRowNumber = isset($options['header-row']) ? max(1, (int)$options['header-row']) : 0;
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

function normalize_edad_unidad($value)
{
    $v = strtolower(trim((string)$value));
    if ($v === '') {
        return '';
    }
    if (in_array($v, ['d', 'dia', 'dias', 'dia(s)', 'dias(s)', 'días', 'día'], true)) {
        return 'días';
    }
    if (in_array($v, ['m', 'mes', 'meses'], true)) {
        return 'meses';
    }
    if (in_array($v, ['a', 'ano', 'anos', 'anios', 'años', 'año'], true)) {
        return 'años';
    }
    return '';
}

function normalize_bool($value)
{
    $v = strtolower(trim((string)$value));
    if ($v === '') {
        return null;
    }
    if (in_array($v, ['1', 'si', 's', 'true', 'verdadero', 'yes', 'y'], true)) {
        return 1;
    }
    if (in_array($v, ['0', 'no', 'n', 'false', 'falso'], true)) {
        return 0;
    }
    return null;
}

function normalize_date_value($value)
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    if (is_numeric($raw)) {
        // Excel serial date: 25569 = 1970-01-01
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

function read_xlsx_rows($xlsxFile, $sheetNumber = 1)
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
        $id = (string)$rel['Id'];
        $target = (string)$rel['Target'];
        $relMap[$id] = $target;
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
            $ref = (string)$cell['r'];
            $idx = col_ref_to_index($ref);
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

function get_next_historia_clinica($mysqli)
{
    $prefix = 'HC';
    $stmt = $mysqli->prepare("SELECT historia_clinica
                              FROM pacientes
                              WHERE historia_clinica REGEXP '^HC[0-9]+$'
                              ORDER BY CAST(SUBSTRING(historia_clinica, 3) AS UNSIGNED) DESC
                              LIMIT 1");
    if (!$stmt) {
        return $prefix . '000001';
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $last = isset($result['historia_clinica']) ? (string)$result['historia_clinica'] : '';
    if (!preg_match('/^HC(\d+)$/', $last, $m)) {
        return $prefix . '000001';
    }
    $next = (int)$m[1] + 1;
    return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

function find_existing_paciente_id($mysqli, $dni)
{
    $stmt = $mysqli->prepare('SELECT id FROM pacientes WHERE dni = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $dni);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function build_default_error_report_path($sourceFilePath)
{
    $baseDir = __DIR__;
    $baseName = pathinfo((string)$sourceFilePath, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$baseName);
    if ($baseName === '') {
        $baseName = 'pacientes';
    }
    $stamp = date('Ymd_His');
    return $baseDir . '/import_errors_' . $baseName . '_' . $stamp . '.csv';
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
            throw new RuntimeException('No se pudo crear directorio para reporte de errores: ' . $dir);
        }
    }

    $fp = fopen($path, 'wb');
    if ($fp === false) {
        throw new RuntimeException('No se pudo escribir reporte de errores: ' . $path);
    }

    $headers = ['linea', 'motivo', 'dni', 'nombre', 'apellido', 'sexo'];
    fwrite($fp, implode(',', array_map('csv_escape', $headers)) . PHP_EOL);

    foreach ($rows as $row) {
        $line = [
            $row['linea'] ?? '',
            $row['motivo'] ?? '',
            $row['dni'] ?? '',
            $row['nombre'] ?? '',
            $row['apellido'] ?? '',
            $row['sexo'] ?? '',
        ];
        fwrite($fp, implode(',', array_map('csv_escape', $line)) . PHP_EOL);
    }

    fclose($fp);
}

function split_full_name($fullName)
{
    $clean = preg_replace('/\s+/', ' ', trim((string)$fullName));
    if ($clean === '') {
        return ['', ''];
    }
    $parts = array_values(array_filter(explode(' ', $clean), fn($v) => trim((string)$v) !== ''));
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

function detect_header_row_index($rows, $aliases)
{
    $maxScan = min(count($rows), 20);
    $bestIndex = -1;
    $bestScore = -1;

    for ($i = 0; $i < $maxScan; $i++) {
        $row = $rows[$i] ?? [];
        $normalizedRow = [];
        foreach ($row as $cell) {
            $normalizedRow[] = normalize_header($cell);
        }

        $score = 0;
        foreach ($aliases as $candidates) {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $normalizedRow, true)) {
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

try {
    $rows = read_xlsx_rows($filePath, $sheetNumber);
    if (count($rows) < 2) {
        throw new RuntimeException('El archivo no tiene datos (se requiere cabecera + filas).');
    }

    $aliases = [
        'dni' => ['dni', 'documento', 'num_documento', 'nro_documento'],
        'nombre' => ['nombre', 'nombres', 'primer_nombre'],
        'apellido' => ['apellido', 'apellidos', 'apellido_paterno', 'apellidos_y_nombres'],
        'historia_clinica' => ['historia_clinica', 'hc', 'n_historia'],
        'fecha_nacimiento' => ['fecha_nacimiento', 'fecha_de_nacimiento', 'fec_nac', 'nacimiento'],
        'edad' => ['edad'],
        'edad_unidad' => ['edad_unidad', 'unidad_edad'],
        'procedencia' => ['procedencia'],
        'departamento' => ['departamento'],
        'provincia' => ['provincia'],
        'distrito' => ['distrito'],
        'gradoinstruccion' => ['gradoinstruccion', 'grado_instruccion'],
        'estadocivil' => ['estadocivil', 'estado_civil'],
        'nombrepadre' => ['nombrepadre', 'nombre_padre'],
        'nombremadre' => ['nombremadre', 'nombre_madre'],
        'acompanante' => ['acompanante'],
        'trabajoresidencia' => ['trabajoresidencia', 'trabajo_residencia'],
        'tiemporesidencia' => ['tiemporesidencia', 'tiempo_residencia'],
        'tipo_seguro' => ['tipo_seguro', 'seguro'],
        'sexo' => ['sexo', 'genero'],
        'direccion' => ['direccion', 'domicilio'],
        'calle' => ['calle'],
        'urbanizacion' => ['urbanizacion', 'urbanizacion_residencia'],
        'ocupacion' => ['ocupacion', 'profesion'],
        'hijos' => ['hijos'],
        'hijosdependientes' => ['hijosdependientes', 'hijos_dependientes'],
        'telefono' => ['telefono', 'tel'],
        'celular' => ['celular', 'movil'],
        'email' => ['email', 'correo', 'correo_electronico'],
        'tipo_documento' => ['tipo_documento', 'tipodocumento'],
    ];

    $headerIndex = $headerRowNumber > 0 ? ($headerRowNumber - 1) : detect_header_row_index($rows, $aliases);
    if (!isset($rows[$headerIndex])) {
        throw new RuntimeException('No se pudo resolver la fila de cabecera. Use --header-row=N.');
    }

    $header = $rows[$headerIndex];
    $headerMap = [];
    foreach ($header as $i => $name) {
        $normalized = normalize_header($name);
        if ($normalized !== '') {
            $headerMap[$normalized] = $i;
        }
    }

    $dataRows = array_slice($rows, $headerIndex + 1);
    $colIndex = [];
    foreach ($aliases as $target => $candidates) {
        $colIndex[$target] = -1;
        foreach ($candidates as $alias) {
            if (array_key_exists($alias, $headerMap)) {
                $colIndex[$target] = $headerMap[$alias];
                break;
            }
        }
    }

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    $errorRows = [];
    $errorReportPath = $errorFileOption !== '' ? $errorFileOption : build_default_error_report_path($filePath);

    $mysqli->begin_transaction();

    $line = $headerIndex + 1;
    foreach ($dataRows as $row) {
        $line++;

        $get = static function ($key) use ($colIndex, $row) {
            $idx = $colIndex[$key] ?? -1;
            if ($idx < 0) {
                return '';
            }
            return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
        };

        $dni = preg_replace('/\D+/', '', $get('dni'));
        $nombre = normalize_text($get('nombre'));
        $apellido = normalize_text($get('apellido'));
        $sexo = normalize_sexo($get('sexo'));

        if ($nombre === '' && $apellido !== '') {
            [$nombreSplit, $apellidoSplit] = split_full_name($apellido);
            if ($nombreSplit !== '' || $apellidoSplit !== '') {
                $nombre = $nombreSplit;
                $apellido = $apellidoSplit;
            }
        }
        if ($sexo === '' && $defaultSexo !== '') {
            $sexo = $defaultSexo;
        }

        if ($dni === '' && $nombre === '' && $apellido === '') {
            $skipped++;
            continue;
        }

        if ($dni === '' || $nombre === '' || $apellido === '' || $sexo === '') {
            $errors++;
            $reason = 'faltan campos obligatorios (dni, nombre, apellido, sexo)';
            $errorRows[] = [
                'linea' => $line,
                'motivo' => $reason,
                'dni' => $dni,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'sexo' => $sexo,
            ];
            echo "[ERROR] Fila {$line}: {$reason}." . PHP_EOL;
            continue;
        }

        $historia = normalize_text($get('historia_clinica'));
        if ($historia === '') {
            $historia = get_next_historia_clinica($mysqli);
        }

        $fechaNacimiento = normalize_date_value($get('fecha_nacimiento'));
        $edad = normalize_text($get('edad'));
        $edadUnidad = normalize_edad_unidad($get('edad_unidad'));
        $procedencia = normalize_text($get('procedencia'));
        $departamento = normalize_text($get('departamento'));
        $provincia = normalize_text($get('provincia'));
        $distrito = normalize_text($get('distrito'));
        $gradoInstruccion = normalize_text($get('gradoinstruccion'));
        $estadoCivil = normalize_text($get('estadocivil'));
        $nombrePadre = normalize_text($get('nombrepadre'));
        $nombreMadre = normalize_text($get('nombremadre'));
        $acompanante = normalize_text($get('acompanante'));
        $trabajoResidencia = normalize_bool($get('trabajoresidencia'));
        $tiempoResidencia = normalize_text($get('tiemporesidencia'));
        $tipoSeguro = normalize_text($get('tipo_seguro'));
        $direccion = normalize_text($get('direccion'));
        $calle = normalize_text($get('calle'));
        $urbanizacion = normalize_text($get('urbanizacion'));
        $ocupacion = normalize_text($get('ocupacion'));
        $hijos = normalize_text($get('hijos'));
        $hijosDependientes = normalize_text($get('hijosdependientes'));
        $telefono = normalize_text($get('telefono'));
        $celular = normalize_text($get('celular'));
        $email = normalize_text($get('email'));
        $tipoDocumento = normalize_text($get('tipo_documento'));

        $existingId = find_existing_paciente_id($mysqli, $dni);

        if ($existingId > 0) {
            if (!$upsert) {
                $skipped++;
                continue;
            }

            $sql = 'UPDATE pacientes
                    SET nombre = ?, apellido = ?, sexo = ?, historia_clinica = ?,
                        fecha_nacimiento = ?, edad = ?, edad_unidad = ?, procedencia = ?,
                        departamento = ?, provincia = ?, distrito = ?, gradoinstruccion = ?,
                        estadocivil = ?, nombrepadre = ?, nombremadre = ?, acompanante = ?,
                        trabajoresidencia = ?, tiemporesidencia = ?, tipo_seguro = ?, direccion = ?,
                        calle = ?, urbanizacion = ?, ocupacion = ?, hijos = ?, hijosdependientes = ?,
                        telefono = ?, celular = ?, email = ?, tipo_documento = ?
                    WHERE id = ? LIMIT 1';
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                $errors++;
                $reason = 'no se pudo preparar UPDATE';
                $errorRows[] = [
                    'linea' => $line,
                    'motivo' => $reason,
                    'dni' => $dni,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'sexo' => $sexo,
                ];
                echo "[ERROR] Fila {$line}: {$reason}." . PHP_EOL;
                continue;
            }

            $trabajoResidenciaValue = is_null($trabajoResidencia) ? null : (int)$trabajoResidencia;
            $tiempoResidenciaValue = $tiempoResidencia === '' ? null : (int)$tiempoResidencia;
            $hijosValue = $hijos === '' ? null : (int)$hijos;
            $hijosDependientesValue = $hijosDependientes === '' ? null : (int)$hijosDependientes;
            $fechaNacimientoValue = $fechaNacimiento === null ? null : $fechaNacimiento;

            $stmt->bind_param(
                'ssssssssssssssssiisssssiissssi',
                $nombre,
                $apellido,
                $sexo,
                $historia,
                $fechaNacimientoValue,
                $edad,
                $edadUnidad,
                $procedencia,
                $departamento,
                $provincia,
                $distrito,
                $gradoInstruccion,
                $estadoCivil,
                $nombrePadre,
                $nombreMadre,
                $acompanante,
                $trabajoResidenciaValue,
                $tiempoResidenciaValue,
                $tipoSeguro,
                $direccion,
                $calle,
                $urbanizacion,
                $ocupacion,
                $hijosValue,
                $hijosDependientesValue,
                $telefono,
                $celular,
                $email,
                $tipoDocumento,
                $existingId
            );

            if (!$dryRun) {
                if (!$stmt->execute()) {
                    $errors++;
                    $reason = $stmt->error;
                    $errorRows[] = [
                        'linea' => $line,
                        'motivo' => $reason,
                        'dni' => $dni,
                        'nombre' => $nombre,
                        'apellido' => $apellido,
                        'sexo' => $sexo,
                    ];
                    echo "[ERROR] Fila {$line}: {$reason}" . PHP_EOL;
                    $stmt->close();
                    continue;
                }
            }
            $stmt->close();
            $updated++;
            continue;
        }

        $sql = 'INSERT INTO pacientes (
                    nombre, apellido, historia_clinica, fecha_nacimiento, edad, edad_unidad,
                    procedencia, departamento, provincia, distrito, gradoinstruccion,
                    estadocivil, nombrepadre, nombremadre, acompanante, trabajoresidencia,
                    tiemporesidencia, tipo_seguro, sexo, direccion, calle, urbanizacion,
                    ocupacion, hijos, hijosdependientes, telefono, celular, email, dni,
                    tipo_documento
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?
                )';

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            $errors++;
            $reason = 'no se pudo preparar INSERT';
            $errorRows[] = [
                'linea' => $line,
                'motivo' => $reason,
                'dni' => $dni,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'sexo' => $sexo,
            ];
            echo "[ERROR] Fila {$line}: {$reason}." . PHP_EOL;
            continue;
        }

        $trabajoResidenciaValue = is_null($trabajoResidencia) ? null : (int)$trabajoResidencia;
        $tiempoResidenciaValue = $tiempoResidencia === '' ? null : (int)$tiempoResidencia;
        $hijosValue = $hijos === '' ? null : (int)$hijos;
        $hijosDependientesValue = $hijosDependientes === '' ? null : (int)$hijosDependientes;
        $fechaNacimientoValue = $fechaNacimiento === null ? null : $fechaNacimiento;

        $stmt->bind_param(
            'sssssssssssssssiissssssiisssss',
            $nombre,
            $apellido,
            $historia,
            $fechaNacimientoValue,
            $edad,
            $edadUnidad,
            $procedencia,
            $departamento,
            $provincia,
            $distrito,
            $gradoInstruccion,
            $estadoCivil,
            $nombrePadre,
            $nombreMadre,
            $acompanante,
            $trabajoResidenciaValue,
            $tiempoResidenciaValue,
            $tipoSeguro,
            $sexo,
            $direccion,
            $calle,
            $urbanizacion,
            $ocupacion,
            $hijosValue,
            $hijosDependientesValue,
            $telefono,
            $celular,
            $email,
            $dni,
            $tipoDocumento
        );

        if (!$dryRun) {
            if (!$stmt->execute()) {
                $errors++;
                $reason = $stmt->error;
                $errorRows[] = [
                    'linea' => $line,
                    'motivo' => $reason,
                    'dni' => $dni,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'sexo' => $sexo,
                ];
                echo "[ERROR] Fila {$line}: {$reason}" . PHP_EOL;
                $stmt->close();
                continue;
            }
        }

        $stmt->close();
        $inserted++;
    }

    if ($dryRun || $errors > 0) {
        $mysqli->rollback();
    } else {
        $mysqli->commit();
    }

    echo PHP_EOL;
    echo 'Archivo: ' . $filePath . PHP_EOL;
    echo 'Sheet: ' . $sheetNumber . PHP_EOL;
    echo 'Modo upsert: ' . ($upsert ? 'SI' : 'NO') . PHP_EOL;
    echo 'Modo dry-run: ' . ($dryRun ? 'SI' : 'NO') . PHP_EOL;
    echo 'Insertados: ' . $inserted . PHP_EOL;
    echo 'Actualizados: ' . $updated . PHP_EOL;
    echo 'Saltados: ' . $skipped . PHP_EOL;
    echo 'Errores: ' . $errors . PHP_EOL;

    if ($errors > 0) {
        write_error_report_csv($errorReportPath, $errorRows);
        echo 'Reporte errores: ' . $errorReportPath . PHP_EOL;
    }

    if ($dryRun) {
        echo 'Resultado: SIMULACION (ROLLBACK).' . PHP_EOL;
    } elseif ($errors > 0) {
        echo 'Resultado: ERROR (ROLLBACK por errores).' . PHP_EOL;
    } else {
        echo 'Resultado: OK (COMMIT).' . PHP_EOL;
    }

    exit($errors > 0 ? 1 : 0);
} catch (Throwable $e) {
    if ($mysqli->errno === 0) {
        // noop
    }
    if ($mysqli->connect_errno === 0) {
        @$mysqli->rollback();
    }
    echo 'FATAL: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
