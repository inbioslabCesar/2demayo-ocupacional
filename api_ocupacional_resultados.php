<?php

require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/db_ocupacional.php';

function out_result_ocup($code, $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function parse_session_permisos_result_ocup()
{
    $raw = $_SESSION['usuario']['permisos'] ?? [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }
    return array_values(array_filter(array_map('strval', $raw), fn($v) => trim($v) !== ''));
}

function require_ocup_permiso_any_result($permisosValidos, $fallback = 'ejecutar_ordenes_ocupacional')
{
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario)) {
        out_result_ocup(401, ['success' => false, 'error' => 'No autenticado']);
    }

    $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
    if ($rol === 'administrador') {
        return;
    }

    $permisos = parse_session_permisos_result_ocup();
    if (!in_array('access_salud_ocupacional', $permisos, true)) {
        out_result_ocup(403, ['success' => false, 'error' => 'No autorizado para salud ocupacional']);
    }

    $lista = is_array($permisosValidos) ? $permisosValidos : [$permisosValidos];
    if ($fallback !== '') {
        $lista[] = $fallback;
    }

    foreach ($lista as $perm) {
        $p = trim((string)$perm);
        if ($p !== '' && in_array($p, $permisos, true)) {
            return;
        }
    }

    out_result_ocup(403, ['success' => false, 'error' => 'No autorizado para esta accion']);
}

function table_exists_result_ocup($conn, $table)
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function normalize_formato_codigo_ocup($value, $fallback)
{
    $raw = trim((string)$value);
    if ($raw === '') {
        $raw = trim((string)$fallback);
    }
    if ($raw === '') {
        $raw = 'formato_general';
    }
    $raw = strtolower($raw);
    $raw = preg_replace('/[^a-z0-9_\-]+/', '_', $raw);
    $raw = trim((string)$raw, '_');
    if ($raw === '') {
        $raw = 'formato_general';
    }
    if (strlen($raw) > 40) {
        $raw = substr($raw, 0, 40);
    }
    return $raw;
}

function build_template_codigo_ocup($codigo, $descripcion, $grupo = '', $subgrupo = '')
{
    $text = strtolower(trim((string)$codigo . ' ' . (string)$descripcion . ' ' . (string)$grupo . ' ' . (string)$subgrupo));

    if (strpos($text, 'triaje') !== false || strpos($text, 'triage') !== false || strpos($text, 'signos vitales') !== false) {
        return 'triaje_clinico';
    }
    if (strpos($text, 'hemograma') !== false || strpos($text, 'lab') !== false || strpos($text, 'laboratorio') !== false) {
        return 'lab_basico';
    }
    if (strpos($text, 'audio') !== false) {
        return 'audiometria_basica';
    }
    if (strpos($text, 'oftal') !== false || strpos($text, 'vision') !== false) {
        return 'oftalmologia_basica';
    }
    if (strpos($text, 'psico') !== false) {
        return 'psicologia_basica';
    }
    if (strpos($text, 'electro') !== false || strpos($text, 'ekg') !== false || strpos($text, 'ecg') !== false) {
        return 'ekg_basico';
    }
    if (strpos($text, 'evaluacion medica') !== false
        || strpos($text, 'examen medico') !== false
        || strpos($text, 'medicina ocupacional') !== false
        || strpos($text, 'ev_0001') !== false) {
        return 'evaluacion_medica_ocupacional';
    }

    return 'general_basico';
}

function build_template_data_ocup($templateCode, $examenCodigo = '', $examenDescripcion = '', $valoresNormales = '')
{
    switch ($templateCode) {
        case 'triaje_clinico':
            return [
                'presion_sistolica' => '',
                'presion_diastolica' => '',
                'frecuencia_cardiaca' => '',
                'frecuencia_respiratoria' => '',
                'temperatura' => '',
                'saturacion_oxigeno' => '',
                'peso_kg' => '',
                'talla_cm' => '',
                'perimetro_abdominal_cm' => '',
                'imc' => '',
                'observaciones' => '',
            ];
        case 'evaluacion_medica_ocupacional':
            return [
                'motivo_evaluacion' => '',
                'antecedentes_ocupacionales' => '',
                'antecedentes_personales' => '',
                'anamnesis' => '',
                'examen_fisico' => '',
                'diagnostico' => '',
                'conclusion' => '',
                'recomendaciones' => '',
            ];
        case 'lab_basico':
            $labText = strtolower(trim((string)$examenCodigo . ' ' . (string)$examenDescripcion));
            $parametros = strpos($labText, 'hemograma') !== false
                ? [
                    ['nombre' => 'hemoglobina', 'valor' => '', 'referencia' => ''],
                    ['nombre' => 'hematocrito', 'valor' => '', 'referencia' => ''],
                    ['nombre' => 'leucocitos', 'valor' => '', 'referencia' => ''],
                ]
                : [[
                    'grupo' => '',
                    'nombre' => trim((string)$examenDescripcion) !== '' ? trim((string)$examenDescripcion) : trim((string)$examenCodigo),
                    'valor' => '',
                    'unidad' => '',
                    'referencia' => trim((string)$valoresNormales),
                ]];
            return [
                'responsable_evaluacion' => '',
                'tipo_muestra' => '',
                'condiciones_muestra' => '',
                'parametros' => $parametros,
                'hallazgos' => '',
                'conclusion' => '',
                'recomendaciones' => '',
            ];
        case 'audiometria_basica':
            return [
                'od' => ['500' => '', '1000' => '', '2000' => '', '3000' => '', '4000' => '', '6000' => '', '8000' => ''],
                'oi' => ['500' => '', '1000' => '', '2000' => '', '3000' => '', '4000' => '', '6000' => '', '8000' => ''],
                'otoscopia_od' => '',
                'otoscopia_oi' => '',
                'impresion' => '',
                'recomendaciones' => '',
            ];
        case 'oftalmologia_basica':
            return [
                'agudeza_visual_od' => '',
                'agudeza_visual_oi' => '',
                'vision_colores' => '',
                'impresion' => '',
                'recomendaciones' => '',
            ];
        case 'psicologia_basica':
            return [
                'responsable_evaluacion' => '',
                'motivo_evaluacion' => 'EVALUACION MEDICA OCUPACIONAL',
                'presentacion' => 'adecuada',
                'postura' => 'erguida',
                'discurso_ritmo' => 'fluido',
                'discurso_tono' => 'moderado',
                'discurso_articulacion' => 'sin_dificultad',
                'orientacion_tiempo' => 'orientado',
                'orientacion_espacio' => 'orientado',
                'orientacion_persona' => 'orientado',
                'nivel_intelectual' => '',
                'coordinacion_visomotriz' => '',
                'nivel_memoria' => '',
                'personalidad' => '',
                'afectividad' => '',
                'conclusion_cognitiva' => '',
                'conclusion_emocional' => '',
                'recomendaciones' => '',
                'observaciones' => '',
                'hallazgos' => '',
                'diagnostico' => '',
                'conclusion' => '',
            ];
        case 'ekg_basico':
            return [
                'ritmo' => '',
                'frecuencia' => '',
                'eje' => '',
                'hallazgos' => '',
                'conclusion' => '',
            ];
        default:
            return [
                'motivo' => '',
                'hallazgos' => '',
                'conclusion' => '',
                'recomendaciones' => '',
            ];
    }
}

function require_text_result_ocup($data, $key, $label)
{
    if (trim((string)($data[$key] ?? '')) === '') {
        out_result_ocup(422, ['success' => false, 'error' => $label . ' es obligatorio para finalizar']);
    }
}

function require_number_range_result_ocup($data, $key, $label, $min, $max)
{
    $value = $data[$key] ?? null;
    if ($value === null || trim((string)$value) === '' || !is_numeric($value)) {
        out_result_ocup(422, ['success' => false, 'error' => $label . ' debe ser numerico para finalizar']);
    }
    $number = (float)$value;
    if ($number < $min || $number > $max) {
        out_result_ocup(422, ['success' => false, 'error' => $label . ' esta fuera del rango permitido']);
    }
}

function validate_finalized_data_result_ocup($templateCode, $data)
{
    if (!is_array($data)) {
        out_result_ocup(422, ['success' => false, 'error' => 'Los datos clinicos son invalidos']);
    }

    if ($templateCode === 'triaje_clinico') {
        require_number_range_result_ocup($data, 'presion_sistolica', 'Presion sistolica', 50, 260);
        require_number_range_result_ocup($data, 'presion_diastolica', 'Presion diastolica', 30, 180);
        require_number_range_result_ocup($data, 'frecuencia_cardiaca', 'Frecuencia cardiaca', 20, 250);
        require_number_range_result_ocup($data, 'frecuencia_respiratoria', 'Frecuencia respiratoria', 5, 80);
        require_number_range_result_ocup($data, 'temperatura', 'Temperatura', 30, 45);
        require_number_range_result_ocup($data, 'saturacion_oxigeno', 'Saturacion de oxigeno', 50, 100);
        require_number_range_result_ocup($data, 'peso_kg', 'Peso', 2, 400);
        require_number_range_result_ocup($data, 'talla_cm', 'Talla', 40, 250);
        if (trim((string)($data['perimetro_abdominal_cm'] ?? '')) !== '') {
            require_number_range_result_ocup($data, 'perimetro_abdominal_cm', 'Perimetro abdominal', 20, 300);
        }
        return;
    }

    if ($templateCode === 'audiometria_basica') {
        foreach (['od' => 'OD', 'oi' => 'OI'] as $earKey => $earLabel) {
            $ear = isset($data[$earKey]) && is_array($data[$earKey]) ? $data[$earKey] : [];
            foreach (['500', '1000', '2000', '4000'] as $frequency) {
                require_number_range_result_ocup($ear, $frequency, $earLabel . ' ' . $frequency . ' Hz', -10, 130);
            }
        }
        require_text_result_ocup($data, 'impresion', 'Impresion audiometrica');
        return;
    }

    if ($templateCode === 'evaluacion_medica_ocupacional') {
        require_text_result_ocup($data, 'anamnesis', 'Anamnesis');
        require_text_result_ocup($data, 'examen_fisico', 'Examen fisico');
        require_text_result_ocup($data, 'conclusion', 'Conclusion');
        return;
    }

    if ($templateCode === 'oftalmologia_basica') {
        require_text_result_ocup($data, 'agudeza_visual_od', 'Agudeza visual OD');
        require_text_result_ocup($data, 'agudeza_visual_oi', 'Agudeza visual OI');
        require_text_result_ocup($data, 'impresion', 'Impresion oftalmologica');
        return;
    }

    if ($templateCode === 'lab_basico') {
        $parametros = isset($data['parametros']) && is_array($data['parametros']) ? $data['parametros'] : [];
        $parametrosCompletos = 0;
        foreach ($parametros as $parametro) {
            if (!is_array($parametro)) {
                continue;
            }
            $nombre = trim((string)($parametro['nombre'] ?? ''));
            $valor = trim((string)($parametro['valor'] ?? ''));
            if ($nombre !== '' && $valor !== '') {
                $parametrosCompletos++;
            }
        }
        if ($parametrosCompletos === 0) {
            out_result_ocup(422, ['success' => false, 'error' => 'Registre al menos un parametro de laboratorio con nombre y resultado']);
        }
        require_text_result_ocup($data, 'conclusion', 'Conclusion de laboratorio');
        return;
    }

    if ($templateCode === 'psicologia_basica') {
        $legacyResult = trim((string)($data['hallazgos'] ?? '')) !== ''
            && trim((string)($data['conclusion'] ?? '')) !== '';
        if ($legacyResult) {
            return;
        }
        require_text_result_ocup($data, 'motivo_evaluacion', 'Motivo de evaluacion');
        require_text_result_ocup($data, 'nivel_intelectual', 'Nivel intelectual');
        require_text_result_ocup($data, 'coordinacion_visomotriz', 'Coordinacion visomotriz');
        require_text_result_ocup($data, 'nivel_memoria', 'Nivel de memoria');
        require_text_result_ocup($data, 'personalidad', 'Personalidad');
        require_text_result_ocup($data, 'afectividad', 'Afectividad');
        require_text_result_ocup($data, 'conclusion_cognitiva', 'Conclusion del area cognitiva');
        require_text_result_ocup($data, 'conclusion_emocional', 'Conclusion del area emocional');
        require_text_result_ocup($data, 'recomendaciones', 'Recomendaciones');
        return;
    }

    require_text_result_ocup($data, 'hallazgos', 'Hallazgos');
    require_text_result_ocup($data, 'conclusion', 'Conclusion');
}

function normalize_template_codigo_ocup($value, $fallback = 'plantilla')
{
    $raw = strtolower(trim((string)$value));
    if ($raw === '') {
        $raw = strtolower(trim((string)$fallback));
    }
    $raw = preg_replace('/[^a-z0-9_\-]+/', '_', $raw);
    $raw = trim((string)$raw, '_');
    if ($raw === '') {
        $raw = 'plantilla';
    }
    if (strlen($raw) > 60) {
        $raw = substr($raw, 0, 60);
    }
    return $raw;
}

function safe_json_decode_assoc_result_ocup($value)
{
    $decoded = json_decode((string)$value, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [];
    }
    return $decoded;
}

function ensure_template_table_result_ocup($conn)
{
    if (table_exists_result_ocup($conn, 'ocupacional_plantillas_resultado')) {
        return true;
    }

    $sql = 'CREATE TABLE IF NOT EXISTS ocupacional_plantillas_resultado (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        codigo VARCHAR(60) NOT NULL,
        nombre VARCHAR(120) NOT NULL,
        template_code VARCHAR(50) NOT NULL,
        examen_codigo VARCHAR(60) NULL,
        formato_codigo VARCHAR(40) NULL,
        datos_json LONGTEXT NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        updated_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_ocup_plantilla_codigo (codigo),
        KEY idx_ocup_plantilla_template (template_code),
        KEY idx_ocup_plantilla_examen (examen_codigo),
        KEY idx_ocup_plantilla_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return (bool)$conn->query($sql);
}

function fetch_template_catalog_result_ocup($conn, $templateCode, $examenCodigo, $formatoCodigo)
{
    if (!ensure_template_table_result_ocup($conn)) {
        return [];
    }

    $templateCode = trim((string)$templateCode);
    $examenCodigo = trim((string)$examenCodigo);
    $formatoCodigo = trim((string)$formatoCodigo);

    $sql = 'SELECT id, codigo, nombre, template_code, examen_codigo, formato_codigo, datos_json
            FROM ocupacional_plantillas_resultado
            WHERE activo = 1
              AND (
                    (examen_codigo IS NOT NULL AND examen_codigo <> "" AND examen_codigo = ?)
                 OR (template_code = ? AND (examen_codigo IS NULL OR examen_codigo = ""))
                  )
              AND (formato_codigo IS NULL OR formato_codigo = "" OR formato_codigo = ?)
            ORDER BY CASE WHEN examen_codigo = ? THEN 0 ELSE 1 END, id DESC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ssss', $examenCodigo, $templateCode, $formatoCodigo, $examenCodigo);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int)$row['id'],
            'codigo' => (string)($row['codigo'] ?? ''),
            'nombre' => (string)($row['nombre'] ?? ''),
            'template_code' => (string)($row['template_code'] ?? ''),
            'examen_codigo' => (string)($row['examen_codigo'] ?? ''),
            'formato_codigo' => (string)($row['formato_codigo'] ?? ''),
            'origen' => 'catalogo',
            'datos_json' => safe_json_decode_assoc_result_ocup($row['datos_json'] ?? '{}'),
        ];
    }
    return $out;
}

function default_template_entry_result_ocup($templateCode, $templateData, $examenCodigo, $formatoCodigo)
{
    return [
        'id' => 0,
        'codigo' => 'default_' . normalize_template_codigo_ocup($templateCode, 'general'),
        'nombre' => 'Plantilla sugerida del sistema',
        'template_code' => (string)$templateCode,
        'examen_codigo' => (string)$examenCodigo,
        'formato_codigo' => (string)$formatoCodigo,
        'origen' => 'sistema',
        'datos_json' => is_array($templateData) ? $templateData : [],
    ];
}

function merge_templates_with_default_result_ocup($catalog, $templateCode, $templateData, $examenCodigo, $formatoCodigo)
{
    $list = is_array($catalog) ? $catalog : [];
    $default = default_template_entry_result_ocup($templateCode, $templateData, $examenCodigo, $formatoCodigo);

    if (count($list) === 0) {
        return [$default];
    }

    $list[] = $default;
    return $list;
}

function decode_json_field_result_ocup($value, $fieldName)
{
    if (is_array($value)) {
        return [json_encode($value, JSON_UNESCAPED_UNICODE), $value];
    }
    if (is_object($value)) {
        $arr = json_decode(json_encode($value), true);
        return [json_encode($arr, JSON_UNESCAPED_UNICODE), $arr];
    }
    $text = trim((string)$value);
    if ($text === '') {
        return [json_encode([], JSON_UNESCAPED_UNICODE), []];
    }
    $decoded = json_decode($text, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        out_result_ocup(422, ['success' => false, 'error' => $fieldName . ' debe ser JSON valido']);
    }
    return [json_encode($decoded, JSON_UNESCAPED_UNICODE), $decoded];
}

function bind_params_dynamic_result_ocup($stmt, $types, $params)
{
    if ($types === '' || empty($params)) {
        return;
    }
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function registrar_evento_result_ocup($mysqliOcup, $ordenId, $tipo, $descripcion, $usuarioId, $payload = null)
{
    $ordenId = (int)$ordenId;
    if ($ordenId <= 0) {
        return;
    }
    if (!table_exists_result_ocup($mysqliOcup, 'ocupacional_orden_eventos')) {
        return;
    }

    $payloadJson = null;
    if (is_array($payload) && !empty($payload)) {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    $stmt = $mysqliOcup->prepare('INSERT INTO ocupacional_orden_eventos
                                  (orden_id, tipo_evento, descripcion, payload_json, created_by)
                                  VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isssi', $ordenId, $tipo, $descripcion, $payloadJson, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

function resolve_orden_id_by_detalle_result_ocup($mysqliOcup, $ordenDetalleId)
{
    $id = (int)$ordenDetalleId;
    if ($id <= 0) {
        return 0;
    }
    $stmt = $mysqliOcup->prepare('SELECT orden_id FROM ocupacional_orden_detalle WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['orden_id'] ?? 0);
}

function sync_estado_orden_por_detalle_result_ocup($mysqliOcup, $ordenId, $usuarioId)
{
    $stmt = $mysqliOcup->prepare('SELECT
                                    COUNT(*) AS total,
                                                                        SUM(CASE WHEN d.estado_ejecucion = "pendiente" THEN 1 ELSE 0 END) AS pendientes,
                                                                        SUM(CASE WHEN d.estado_ejecucion = "realizado"
                                                                                            AND EXISTS (
                                                                                                    SELECT 1
                                                                                                    FROM ocupacional_resultados_clinicos rc
                                                                                                    WHERE rc.orden_detalle_id = d.id
                                                                                                        AND rc.estado = "finalizado"
                                                                                            )
                                                                                         THEN 1 ELSE 0 END) AS completados
                                                                    FROM ocupacional_orden_detalle d
                                                                    WHERE d.orden_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $ordenId);
    $stmt->execute();
    $agg = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int)($agg['total'] ?? 0);
    $pendientes = (int)($agg['pendientes'] ?? 0);
    $completados = (int)($agg['completados'] ?? 0);

    $nuevoEstado = 'emitida';
    if ($total > 0 && $completados >= $total) {
        $nuevoEstado = 'completada';
    } elseif (($total - $pendientes) > 0) {
        $nuevoEstado = 'en_proceso';
    }

    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_ordenes
                                    SET estado = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? AND estado NOT IN ("anulada", "cerrada") LIMIT 1');
    if (!$stmtUp) {
        return;
    }
    $stmtUp->bind_param('sii', $nuevoEstado, $usuarioId, $ordenId);
    $stmtUp->execute();
    $stmtUp->close();
}

$requiredTables = [
    'ocupacional_ordenes',
    'ocupacional_orden_detalle',
    'ocupacional_resultados_clinicos',
];

foreach ($requiredTables as $table) {
    if (!table_exists_result_ocup($mysqliOcup, $table)) {
        out_result_ocup(500, [
            'success' => false,
            'error' => 'Falta la tabla ' . $table . '. Aplicar scripts SQL de Fase 4 en la base ocupacional.',
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_ocup_permiso_any_result(['ver_ordenes_ocupacional', 'ejecutar_ordenes_ocupacional']);

    $accion = trim((string)($_GET['accion'] ?? 'obtener'));

    if ($accion === 'listar_plantillas') {
        $examenCodigoRaw = trim((string)($_GET['examen_codigo'] ?? ''));
        $examenDescripcion = trim((string)($_GET['examen_descripcion'] ?? ''));
        $templateCode = build_template_codigo_ocup($examenCodigoRaw, $examenDescripcion);
        $formatoCodigo = normalize_formato_codigo_ocup($_GET['formato_codigo'] ?? '', $examenCodigoRaw !== '' ? $examenCodigoRaw : 'formato_general');
        $catalog = fetch_template_catalog_result_ocup($mysqliOcup, $templateCode, $examenCodigoRaw, $formatoCodigo);
        $templateData = build_template_data_ocup($templateCode, $examenCodigoRaw, $examenDescripcion);
        $plantillas = merge_templates_with_default_result_ocup($catalog, $templateCode, $templateData, $examenCodigoRaw, $formatoCodigo);

        out_result_ocup(200, [
            'success' => true,
            'data' => [
                'template_code' => $templateCode,
                'formato_codigo' => $formatoCodigo,
                'plantillas' => $plantillas,
                'plantilla_sugerida' => (count($catalog) > 0 ? $catalog[0]['datos_json'] : $templateData),
            ],
        ]);
    }

    if ($accion !== 'obtener') {
        out_result_ocup(422, ['success' => false, 'error' => 'accion GET no soportada']);
    }

    $ordenDetalleId = (int)($_GET['orden_detalle_id'] ?? 0);
    if ($ordenDetalleId <= 0) {
        out_result_ocup(422, ['success' => false, 'error' => 'orden_detalle_id es obligatorio']);
    }

    $stmtDet = $mysqliOcup->prepare('SELECT d.id, d.orden_id, d.examen_id, d.examen_codigo, d.examen_descripcion,
                                            eg.grupo AS examen_grupo, eg.subgrupo AS examen_subgrupo, eg.valores_normales,
                                            o.estado AS estado_orden
                                     FROM ocupacional_orden_detalle d
                                     INNER JOIN ocupacional_ordenes o ON o.id = d.orden_id
                                     LEFT JOIN ocupacional_examenes_generales eg ON eg.id = d.examen_id
                                     WHERE d.id = ? LIMIT 1');
    if (!$stmtDet) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo consultar detalle de orden']);
    }
    $stmtDet->bind_param('i', $ordenDetalleId);
    $stmtDet->execute();
    $detalle = $stmtDet->get_result()->fetch_assoc();
    $stmtDet->close();

    if (!$detalle) {
        out_result_ocup(404, ['success' => false, 'error' => 'Detalle de orden no encontrado']);
    }

    $formatoCodigo = normalize_formato_codigo_ocup($_GET['formato_codigo'] ?? '', $detalle['examen_codigo'] ?? 'formato_general');
    $templateCode = build_template_codigo_ocup(
        $detalle['examen_codigo'] ?? '',
        $detalle['examen_descripcion'] ?? '',
        $detalle['examen_grupo'] ?? '',
        $detalle['examen_subgrupo'] ?? ''
    );
    $templateData = build_template_data_ocup(
        $templateCode,
        $detalle['examen_codigo'] ?? '',
        $detalle['examen_descripcion'] ?? '',
        $detalle['valores_normales'] ?? ''
    );
    $catalog = fetch_template_catalog_result_ocup(
        $mysqliOcup,
        $templateCode,
        (string)($detalle['examen_codigo'] ?? ''),
        $formatoCodigo
    );
    $plantillasDisponibles = merge_templates_with_default_result_ocup(
        $catalog,
        $templateCode,
        $templateData,
        (string)($detalle['examen_codigo'] ?? ''),
        $formatoCodigo
    );

    $stmtRes = $mysqliOcup->prepare('SELECT *
                                     FROM ocupacional_resultados_clinicos
                                     WHERE orden_detalle_id = ? AND formato_codigo = ?
                                     LIMIT 1');
    if (!$stmtRes) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo consultar resultado clinico']);
    }
    $stmtRes->bind_param('is', $ordenDetalleId, $formatoCodigo);
    $stmtRes->execute();
    $row = $stmtRes->get_result()->fetch_assoc();
    $stmtRes->close();

    $dataRow = null;
    if ($row) {
        $dataRow = [
            'id' => (int)$row['id'],
            'orden_detalle_id' => (int)$row['orden_detalle_id'],
            'orden_id' => (int)$row['orden_id'],
            'examen_id' => (int)$row['examen_id'],
            'formato_codigo' => (string)$row['formato_codigo'],
            'datos_json' => $row['datos_json'] ? json_decode((string)$row['datos_json'], true) : [],
            'estado' => (string)($row['estado'] ?? 'borrador'),
            'observacion' => (string)($row['observacion'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    out_result_ocup(200, [
        'success' => true,
        'detalle' => [
            'id' => (int)$detalle['id'],
            'orden_id' => (int)$detalle['orden_id'],
            'examen_id' => (int)$detalle['examen_id'],
            'examen_codigo' => (string)($detalle['examen_codigo'] ?? ''),
            'examen_descripcion' => (string)($detalle['examen_descripcion'] ?? ''),
            'estado_orden' => (string)($detalle['estado_orden'] ?? ''),
            'formato_codigo' => $formatoCodigo,
            'template_code' => $templateCode,
        ],
        'data' => $dataRow,
        'plantilla_sugerida' => (count($catalog) > 0 ? $catalog[0]['datos_json'] : $templateData),
        'plantillas_disponibles' => $plantillasDisponibles,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out_result_ocup(405, ['success' => false, 'error' => 'Metodo no permitido']);
}

require_ocup_permiso_any_result(['ejecutar_ordenes_ocupacional']);

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$accion = trim((string)($payload['accion'] ?? ''));
if ($accion === 'guardar_plantilla') {
    if (!ensure_template_table_result_ocup($mysqliOcup)) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo inicializar catalogo de plantillas']);
    }

    $nombre = trim((string)($payload['nombre'] ?? ''));
    if ($nombre === '') {
        out_result_ocup(422, ['success' => false, 'error' => 'nombre es obligatorio']);
    }
    if (strlen($nombre) < 4) {
        out_result_ocup(422, ['success' => false, 'error' => 'nombre debe tener al menos 4 caracteres']);
    }
    if (strlen($nombre) > 120) {
        out_result_ocup(422, ['success' => false, 'error' => 'nombre excede 120 caracteres']);
    }

    $examenCodigo = trim((string)($payload['examen_codigo'] ?? ''));
    $examenDescripcion = trim((string)($payload['examen_descripcion'] ?? ''));
    $templateCode = normalize_template_codigo_ocup(
        $payload['template_code'] ?? build_template_codigo_ocup($examenCodigo, $examenDescripcion),
        'general_basico'
    );
    $formatoCodigo = normalize_formato_codigo_ocup($payload['formato_codigo'] ?? '', $examenCodigo !== '' ? $examenCodigo : 'formato_general');
    $codigoInput = trim((string)($payload['codigo'] ?? ''));
    if ($codigoInput === '') {
        $codigoInput = $templateCode . '_' . date('YmdHis');
    }
    $codigo = normalize_template_codigo_ocup($codigoInput, 'plantilla');
    [$datosJsonText, $datosDecoded] = decode_json_field_result_ocup($payload['datos_json'] ?? [], 'datos_json');

    if ($datosDecoded === [] && trim((string)($payload['datos_json'] ?? '')) !== '[]') {
        // allow empty object as valid template body.
    }

    $usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;
    $plantillaId = (int)($payload['id'] ?? 0);
    $ordenDetalleId = (int)($payload['orden_detalle_id'] ?? 0);
    $ordenIdEvento = resolve_orden_id_by_detalle_result_ocup($mysqliOcup, $ordenDetalleId);

    if ($plantillaId > 0) {
        $stmtUpd = $mysqliOcup->prepare('UPDATE ocupacional_plantillas_resultado
                                         SET codigo = ?, nombre = ?, template_code = ?, examen_codigo = ?, formato_codigo = ?, datos_json = ?, activo = 1, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                         WHERE id = ?
                                         LIMIT 1');
        if (!$stmtUpd) {
            out_result_ocup(500, ['success' => false, 'error' => 'No se pudo actualizar plantilla']);
        }
        $stmtUpd->bind_param('ssssssii', $codigo, $nombre, $templateCode, $examenCodigo, $formatoCodigo, $datosJsonText, $usuarioId, $plantillaId);
        try {
            $okUpd = $stmtUpd->execute();
        } catch (Throwable $e) {
            $stmtUpd->close();
            out_result_ocup(500, ['success' => false, 'error' => 'No se pudo actualizar plantilla (codigo duplicado?)']);
        }
        $affectedUpd = $stmtUpd->affected_rows;
        $stmtUpd->close();
        if (!$okUpd) {
            out_result_ocup(500, ['success' => false, 'error' => 'No se pudo actualizar plantilla (codigo duplicado?)']);
        }
        if ($affectedUpd <= 0) {
            out_result_ocup(404, ['success' => false, 'error' => 'Plantilla no encontrada para actualizar']);
        }
        $savedId = $plantillaId;
    } else {
        $stmtInsTpl = $mysqliOcup->prepare('INSERT INTO ocupacional_plantillas_resultado
                                            (codigo, nombre, template_code, examen_codigo, formato_codigo, datos_json, activo, created_by, updated_by)
                                            VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)');
        if (!$stmtInsTpl) {
            out_result_ocup(500, ['success' => false, 'error' => 'No se pudo registrar plantilla']);
        }
        $stmtInsTpl->bind_param('ssssssii', $codigo, $nombre, $templateCode, $examenCodigo, $formatoCodigo, $datosJsonText, $usuarioId, $usuarioId);
        try {
            $okIns = $stmtInsTpl->execute();
        } catch (Throwable $e) {
            $stmtInsTpl->close();
            out_result_ocup(500, ['success' => false, 'error' => 'No se pudo registrar plantilla (codigo duplicado?)']);
        }
        $savedId = (int)$stmtInsTpl->insert_id;
        $stmtInsTpl->close();
        if (!$okIns || $savedId <= 0) {
            out_result_ocup(500, ['success' => false, 'error' => 'No se pudo registrar plantilla (codigo duplicado?)']);
        }
    }

    $stmtOutTpl = $mysqliOcup->prepare('SELECT id, codigo, nombre, template_code, examen_codigo, formato_codigo, datos_json, activo
                                        FROM ocupacional_plantillas_resultado
                                        WHERE id = ?
                                        LIMIT 1');
    if (!$stmtOutTpl) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo consultar plantilla guardada']);
    }
    $stmtOutTpl->bind_param('i', $savedId);
    $stmtOutTpl->execute();
    $savedTpl = $stmtOutTpl->get_result()->fetch_assoc();
    $stmtOutTpl->close();

    registrar_evento_result_ocup(
        $mysqliOcup,
        $ordenIdEvento,
        'plantilla_guardada',
        'Plantilla clinica guardada: ' . (string)($savedTpl['nombre'] ?? $nombre),
        $usuarioId,
        [
            'plantilla_id' => (int)($savedTpl['id'] ?? 0),
            'codigo' => (string)($savedTpl['codigo'] ?? ''),
            'template_code' => (string)($savedTpl['template_code'] ?? ''),
            'formato_codigo' => (string)($savedTpl['formato_codigo'] ?? ''),
        ]
    );

    out_result_ocup(200, [
        'success' => true,
        'message' => 'Plantilla guardada',
        'data' => [
            'id' => (int)($savedTpl['id'] ?? 0),
            'codigo' => (string)($savedTpl['codigo'] ?? ''),
            'nombre' => (string)($savedTpl['nombre'] ?? ''),
            'template_code' => (string)($savedTpl['template_code'] ?? ''),
            'examen_codigo' => (string)($savedTpl['examen_codigo'] ?? ''),
            'formato_codigo' => (string)($savedTpl['formato_codigo'] ?? ''),
            'origen' => 'catalogo',
            'datos_json' => safe_json_decode_assoc_result_ocup($savedTpl['datos_json'] ?? '{}'),
            'activo' => (int)($savedTpl['activo'] ?? 0),
        ],
    ]);
}

if ($accion === 'eliminar_plantilla') {
    if (!ensure_template_table_result_ocup($mysqliOcup)) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo inicializar catalogo de plantillas']);
    }
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) {
        out_result_ocup(422, ['success' => false, 'error' => 'id es obligatorio']);
    }
    $usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;
    $ordenDetalleId = (int)($payload['orden_detalle_id'] ?? 0);
    $ordenIdEvento = resolve_orden_id_by_detalle_result_ocup($mysqliOcup, $ordenDetalleId);

    $stmtTpl = $mysqliOcup->prepare('SELECT id, codigo, nombre, activo FROM ocupacional_plantillas_resultado WHERE id = ? LIMIT 1');
    if (!$stmtTpl) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo validar plantilla']);
    }
    $stmtTpl->bind_param('i', $id);
    $stmtTpl->execute();
    $tplRow = $stmtTpl->get_result()->fetch_assoc();
    $stmtTpl->close();
    if (!$tplRow) {
        out_result_ocup(404, ['success' => false, 'error' => 'Plantilla no encontrada']);
    }
    if ((int)($tplRow['activo'] ?? 0) === 0) {
        out_result_ocup(200, ['success' => true, 'message' => 'Plantilla ya estaba eliminada']);
    }

    $stmtDelTpl = $mysqliOcup->prepare('UPDATE ocupacional_plantillas_resultado
                                        SET activo = 0, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                        WHERE id = ?
                                        LIMIT 1');
    if (!$stmtDelTpl) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo eliminar plantilla']);
    }
    $stmtDelTpl->bind_param('ii', $usuarioId, $id);
    $stmtDelTpl->execute();
    $affected = $stmtDelTpl->affected_rows;
    $stmtDelTpl->close();

    if ($affected <= 0) {
        out_result_ocup(404, ['success' => false, 'error' => 'Plantilla no encontrada']);
    }

    registrar_evento_result_ocup(
        $mysqliOcup,
        $ordenIdEvento,
        'plantilla_eliminada',
        'Plantilla clinica eliminada: ' . (string)($tplRow['nombre'] ?? $id),
        $usuarioId,
        [
            'plantilla_id' => (int)$id,
            'codigo' => (string)($tplRow['codigo'] ?? ''),
        ]
    );

    out_result_ocup(200, ['success' => true, 'message' => 'Plantilla eliminada']);
}

if ($accion === 'registrar_emision_pdf') {
    $ordenDetalleId = (int)($payload['orden_detalle_id'] ?? 0);
    if ($ordenDetalleId <= 0) {
        out_result_ocup(422, ['success' => false, 'error' => 'orden_detalle_id es obligatorio']);
    }
    $formatoCodigo = normalize_formato_codigo_ocup($payload['formato_codigo'] ?? '', 'formato_general');
    $stmtPdf = $mysqliOcup->prepare('SELECT rc.id, rc.orden_id, rc.estado, d.examen_codigo, d.examen_descripcion
                                     FROM ocupacional_resultados_clinicos rc
                                     INNER JOIN ocupacional_orden_detalle d ON d.id = rc.orden_detalle_id
                                     WHERE rc.orden_detalle_id = ? AND rc.formato_codigo = ?
                                     LIMIT 1');
    if (!$stmtPdf) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo validar resultado para PDF']);
    }
    $stmtPdf->bind_param('is', $ordenDetalleId, $formatoCodigo);
    $stmtPdf->execute();
    $resultadoPdf = $stmtPdf->get_result()->fetch_assoc();
    $stmtPdf->close();
    if (!$resultadoPdf) {
        out_result_ocup(404, ['success' => false, 'error' => 'Resultado clinico no encontrado']);
    }
    if ((string)($resultadoPdf['estado'] ?? '') !== 'finalizado') {
        out_result_ocup(422, ['success' => false, 'error' => 'Solo se puede emitir PDF de un resultado finalizado']);
    }

    $usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;
    registrar_evento_result_ocup(
        $mysqliOcup,
        (int)$resultadoPdf['orden_id'],
        'resultado_pdf_emitido',
        'PDF de resultado clinico emitido: ' . (string)($resultadoPdf['examen_codigo'] ?? $formatoCodigo),
        $usuarioId,
        [
            'resultado_id' => (int)$resultadoPdf['id'],
            'orden_detalle_id' => $ordenDetalleId,
            'formato_codigo' => $formatoCodigo,
            'examen_codigo' => (string)($resultadoPdf['examen_codigo'] ?? ''),
            'examen_descripcion' => (string)($resultadoPdf['examen_descripcion'] ?? ''),
            'formato_documento' => 'pdf',
        ]
    );
    out_result_ocup(200, ['success' => true, 'message' => 'Emision de PDF clinico registrada']);
}

if ($accion !== 'guardar') {
    out_result_ocup(422, ['success' => false, 'error' => 'accion POST no soportada']);
}

$ordenDetalleId = (int)($payload['orden_detalle_id'] ?? 0);
if ($ordenDetalleId <= 0) {
    out_result_ocup(422, ['success' => false, 'error' => 'orden_detalle_id es obligatorio']);
}

$stmtDet = $mysqliOcup->prepare('SELECT d.id, d.orden_id, d.examen_id, d.examen_codigo, d.examen_descripcion, d.estado_ejecucion,
                                        eg.grupo AS examen_grupo, eg.subgrupo AS examen_subgrupo,
                                        o.estado AS estado_orden
                                 FROM ocupacional_orden_detalle d
                                 INNER JOIN ocupacional_ordenes o ON o.id = d.orden_id
                                 LEFT JOIN ocupacional_examenes_generales eg ON eg.id = d.examen_id
                                 WHERE d.id = ? LIMIT 1');
if (!$stmtDet) {
    out_result_ocup(500, ['success' => false, 'error' => 'No se pudo validar detalle de orden']);
}
$stmtDet->bind_param('i', $ordenDetalleId);
$stmtDet->execute();
$detalle = $stmtDet->get_result()->fetch_assoc();
$stmtDet->close();

if (!$detalle) {
    out_result_ocup(404, ['success' => false, 'error' => 'Detalle de orden no encontrado']);
}

$estadoOrden = (string)($detalle['estado_orden'] ?? '');
if (in_array($estadoOrden, ['cerrada', 'anulada'], true)) {
    out_result_ocup(422, ['success' => false, 'error' => 'No se puede guardar resultado clinico en orden cerrada o anulada']);
}

$formatoCodigo = normalize_formato_codigo_ocup($payload['formato_codigo'] ?? '', $detalle['examen_codigo'] ?? 'formato_general');
[$datosJsonText, $datosDecoded] = decode_json_field_result_ocup($payload['datos_json'] ?? [], 'datos_json');

$estado = strtolower(trim((string)($payload['estado'] ?? 'borrador')));
if (!in_array($estado, ['borrador', 'finalizado', 'anulado'], true)) {
    out_result_ocup(422, ['success' => false, 'error' => 'estado invalido']);
}
if ($estado === 'finalizado') {
    $templateCode = build_template_codigo_ocup(
        $detalle['examen_codigo'] ?? '',
        $detalle['examen_descripcion'] ?? '',
        $detalle['examen_grupo'] ?? '',
        $detalle['examen_subgrupo'] ?? ''
    );
    validate_finalized_data_result_ocup($templateCode, $datosDecoded);
}

$observacion = trim((string)($payload['observacion'] ?? ''));
$usuarioId = isset($_SESSION['usuario']['id']) ? (int)$_SESSION['usuario']['id'] : null;

$stmtFind = $mysqliOcup->prepare('SELECT id FROM ocupacional_resultados_clinicos WHERE orden_detalle_id = ? AND formato_codigo = ? LIMIT 1');
if (!$stmtFind) {
    out_result_ocup(500, ['success' => false, 'error' => 'No se pudo consultar resultado existente']);
}
$stmtFind->bind_param('is', $ordenDetalleId, $formatoCodigo);
$stmtFind->execute();
$exist = $stmtFind->get_result()->fetch_assoc();
$stmtFind->close();

if ($exist) {
    $resultadoId = (int)$exist['id'];
    $stmtUp = $mysqliOcup->prepare('UPDATE ocupacional_resultados_clinicos
                                    SET datos_json = ?, estado = ?, observacion = ?, ejecutado_by = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? LIMIT 1');
    if (!$stmtUp) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo actualizar resultado clinico']);
    }
    $stmtUp->bind_param('sssiii', $datosJsonText, $estado, $observacion, $usuarioId, $usuarioId, $resultadoId);
    $stmtUp->execute();
    $stmtUp->close();
} else {
    $stmtIns = $mysqliOcup->prepare('INSERT INTO ocupacional_resultados_clinicos
                                     (orden_detalle_id, orden_id, examen_id, formato_codigo, datos_json, estado, ejecutado_by, observacion, created_by, updated_by)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmtIns) {
        out_result_ocup(500, ['success' => false, 'error' => 'No se pudo registrar resultado clinico']);
    }
    $ordenId = (int)$detalle['orden_id'];
    $examenId = (int)$detalle['examen_id'];
    $stmtIns->bind_param('iiisssisii', $ordenDetalleId, $ordenId, $examenId, $formatoCodigo, $datosJsonText, $estado, $usuarioId, $observacion, $usuarioId, $usuarioId);
    $stmtIns->execute();
    $resultadoId = (int)$stmtIns->insert_id;
    $stmtIns->close();
}

// Sincronizar estado de detalle segun todos sus formatos clinicos.
$stmtFinal = $mysqliOcup->prepare('SELECT EXISTS (
                                      SELECT 1
                                      FROM ocupacional_resultados_clinicos
                                      WHERE orden_detalle_id = ?
                                        AND estado = "finalizado"
                                   ) AS tiene_finalizado');
$tieneFinalizado = false;
if ($stmtFinal) {
    $stmtFinal->bind_param('i', $ordenDetalleId);
    $stmtFinal->execute();
    $tieneFinalizado = (int)($stmtFinal->get_result()->fetch_assoc()['tiene_finalizado'] ?? 0) === 1;
    $stmtFinal->close();
}

$nuevoEstadoDetalle = null;
if ($tieneFinalizado) {
    $nuevoEstadoDetalle = 'realizado';
} elseif ((string)($detalle['estado_ejecucion'] ?? '') === 'realizado'
    || ($estado === 'borrador' && (string)($detalle['estado_ejecucion'] ?? '') === 'pendiente')) {
    $nuevoEstadoDetalle = 'en_proceso';
}

if ($nuevoEstadoDetalle !== null) {
    $stmtUpdDet = $mysqliOcup->prepare('UPDATE ocupacional_orden_detalle
                                        SET estado_ejecucion = ?, observacion_ejecucion = ?, fecha_ejecucion = CASE WHEN ? = "realizado" THEN CURRENT_TIMESTAMP ELSE NULL END, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                                        WHERE id = ? LIMIT 1');
    if ($stmtUpdDet) {
        $stmtUpdDet->bind_param('sssii', $nuevoEstadoDetalle, $observacion, $nuevoEstadoDetalle, $usuarioId, $ordenDetalleId);
        $stmtUpdDet->execute();
        $stmtUpdDet->close();
    }
    sync_estado_orden_por_detalle_result_ocup($mysqliOcup, (int)$detalle['orden_id'], (int)$usuarioId);
}

$stmtOut = $mysqliOcup->prepare('SELECT * FROM ocupacional_resultados_clinicos WHERE id = ? LIMIT 1');
if (!$stmtOut) {
    out_result_ocup(500, ['success' => false, 'error' => 'No se pudo consultar resultado guardado']);
}
$stmtOut->bind_param('i', $resultadoId);
$stmtOut->execute();
$saved = $stmtOut->get_result()->fetch_assoc();
$stmtOut->close();

out_result_ocup(200, [
    'success' => true,
    'message' => 'Resultado clinico guardado',
    'data' => [
        'id' => (int)$saved['id'],
        'orden_detalle_id' => (int)$saved['orden_detalle_id'],
        'orden_id' => (int)$saved['orden_id'],
        'examen_id' => (int)$saved['examen_id'],
        'formato_codigo' => (string)$saved['formato_codigo'],
        'datos_json' => $saved['datos_json'] ? json_decode((string)$saved['datos_json'], true) : $datosDecoded,
        'estado' => (string)($saved['estado'] ?? 'borrador'),
        'observacion' => (string)($saved['observacion'] ?? ''),
        'updated_at' => (string)($saved['updated_at'] ?? ''),
    ],
]);
