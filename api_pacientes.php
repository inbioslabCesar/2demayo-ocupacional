<?php
require_once __DIR__ . '/init_api.php';
require_once __DIR__ . '/config.php';

function validar_data_url_imagen(?string $valor): bool {
    if ($valor === null || trim($valor) === '') {
        return true;
    }
    return preg_match('/^data:image\/(png|jpeg|jpg);base64,[A-Za-z0-9+\/=\r\n]+$/', $valor) === 1;
}

function construir_filtro_busqueda_pacientes(string $busqueda): array {
    $busqueda = trim($busqueda);
    if ($busqueda === '') {
        return ['', [], ''];
    }

    $busquedaUpper = strtoupper($busqueda);

    // Búsqueda exacta por historia clínica completa: aprovecha mejor el índice.
    if (preg_match('/^HC\d+$/i', $busquedaUpper)) {
        return ['WHERE historia_clinica = ?', [$busquedaUpper], 's'];
    }

    // Si es numérico, priorizar DNI exacto o prefijo de historia clínica.
    if (preg_match('/^\d+$/', $busqueda)) {
        if (strlen($busqueda) >= 8) {
            return ['WHERE dni = ?', [$busqueda], 's'];
        }
        return ['WHERE dni LIKE ? OR historia_clinica LIKE ?', ["{$busqueda}%", "HC{$busqueda}%"], 'ss'];
    }

    $busquedaLike = "%$busqueda%";
    return [
        "WHERE nombre LIKE ? OR apellido LIKE ? OR CONCAT(nombre, ' ', apellido) LIKE ?",
        [$busquedaLike, $busquedaLike, $busquedaLike],
        'sss'
    ];
}

function pacientes_table_exists($conn, string $table): bool {
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

function pacientes_bio_columns_available($conn): bool {
    $required = ['firma_digital', 'huella_digital', 'fotografia'];
    $placeholders = implode(',', array_fill(0, count($required), '?'));
    $types = str_repeat('s', count($required));
    $sql = 'SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = "pacientes"
              AND COLUMN_NAME IN (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param($types, ...$required);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : ['total' => 0];
    $stmt->close();
    return (int)($row['total'] ?? 0) === count($required);
}

function pacientes_columns_state($conn, array $columns): array {
    $state = [];
    foreach ($columns as $column) {
        $state[$column] = false;
    }
    if (empty($columns)) {
        return $state;
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $types = str_repeat('s', count($columns));
    $sql = 'SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = "pacientes"
              AND COLUMN_NAME IN (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $state;
    }
    $stmt->bind_param($types, ...$columns);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $col = (string) ($row['COLUMN_NAME'] ?? '');
        if ($col !== '' && array_key_exists($col, $state)) {
            $state[$col] = true;
        }
    }
    $stmt->close();
    return $state;
}

function bind_dynamic_params($stmt, string $types, array &$params): void {
    if ($types === '') {
        return;
    }
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $k => &$v) {
        $refs[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function obtener_paciente_por_id($conn, int $id, bool $hasBioColumns, array $legacyState): ?array {
    $columns = [
        'id',
        'historia_clinica',
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'edad',
        'edad_unidad',
        'procedencia',
        'tipo_seguro',
        'direccion',
        'telefono',
        'email',
        'dni',
        'sexo',
    ];

    if ($hasBioColumns) {
        $columns[] = 'firma_digital';
        $columns[] = 'huella_digital';
        $columns[] = 'fotografia';
    }

    foreach ($legacyState as $column => $exists) {
        if ($exists) {
            $columns[] = $column;
        }
    }

    $columns[] = 'creado_en';
    $sql = 'SELECT ' . implode(', ', $columns) . ' FROM pacientes WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

// Función para generar el próximo número de historia clínica
function generarProximaHistoriaClinica($conn) {
    // Obtener el último número de HC de la base de datos
    $query = "SELECT historia_clinica FROM pacientes 
              WHERE historia_clinica LIKE 'HC%' 
              ORDER BY CAST(SUBSTRING(historia_clinica, 3) AS UNSIGNED) DESC 
              LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ultimaHC = $row['historia_clinica'];
        
        // Extraer el número de la HC (por ejemplo: HC00123 -> 123)
        $numero = intval(substr($ultimaHC, 2));
        $proximoNumero = $numero + 1;
        
        // Formatear con 5 dígitos con ceros a la izquierda
        return 'HC' . str_pad($proximoNumero, 5, '0', STR_PAD_LEFT);
    } else {
        // Si no hay registros, empezar con HC00001
        return 'HC00001';
    }
}

// Eliminar paciente (DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? intval($data['id']) : 0;
    if ($id > 0) {
        // Verificar si el paciente tiene atenciones asociadas
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM atenciones WHERE paciente_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row && $row['total'] > 0) {
            echo json_encode(['success' => false, 'error' => 'No se puede eliminar el paciente porque tiene atenciones registradas.']);
            exit;
        }
        // Si no tiene atenciones, eliminar normalmente
        $stmt = $conn->prepare("DELETE FROM pacientes WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al eliminar paciente: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'ID de paciente no válido']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $hasBioColumns = pacientes_bio_columns_available($conn);
    
    $id = isset($data['id']) ? intval($data['id']) : 0;
    $dni = $data['dni'] ?? '';
    $nombre = $data['nombre'] ?? '';
    $apellido = $data['apellido'] ?? '';
        $historia = $data['historia_clinica'] ?? '';
        
        // Si no se proporciona historia clínica, generar automáticamente
        if (empty($historia)) {
            $historia = generarProximaHistoriaClinica($conn);
        } else {
            // Prefijo automático HC si no lo tiene
            if (stripos($historia, 'HC') !== 0) {
                $historia = 'HC' . $historia;
            }
        }
        
        // Validar que la historia clínica no esté duplicada (solo para nuevos pacientes)
        if ($id == 0) {
            $stmtCheck = $conn->prepare("SELECT id FROM pacientes WHERE historia_clinica = ?");
            $stmtCheck->bind_param('s', $historia);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            
            if ($resultCheck->num_rows > 0) {
                // Si está duplicada, generar una nueva automáticamente
                $historia = generarProximaHistoriaClinica($conn);
            }
            $stmtCheck->close();
        }
    $fecha_nacimiento = isset($data['fecha_nacimiento']) && $data['fecha_nacimiento'] !== '' ? $data['fecha_nacimiento'] : null;
    $edad = $data['edad'] ?? null;
    $edad_unidad = $data['edad_unidad'] ?? null;
    $procedencia = $data['procedencia'] ?? null;
    $tipo_seguro = $data['tipo_seguro'] ?? ($data['seguro'] ?? null);
    $sexo = $data['sexo'] ?? 'M';
    $direccion = $data['direccion'] ?? null;
    $telefono = $data['telefono'] ?? null;
    $email = $data['email'] ?? null;
    $firma_digital = array_key_exists('firma_digital', $data) ? (string) $data['firma_digital'] : null;
    $huella_digital = array_key_exists('huella_digital', $data) ? (string) $data['huella_digital'] : null;
    $fotografia = array_key_exists('fotografia', $data) ? (string) $data['fotografia'] : null;

    $legacyColumns = [
        'tipo_documento',
        'lugarnacimiento',
        'calle',
        'urbanizacion',
        'ocupacion',
        'hijos',
        'hijosdependientes',
        'departamento',
        'provincia',
        'distrito',
        'gradoinstruccion',
        'estadocivil',
        'nombrepadre',
        'nombremadre',
        'acompanante',
        'trabajoresidencia',
        'tiemporesidencia',
        'celular',
    ];
    $legacyState = pacientes_columns_state($conn, $legacyColumns);

    $residenciaRaw = $data['trabajoresidencia'] ?? $data['residencia'] ?? null;
    if (is_string($residenciaRaw)) {
        $resUpper = strtoupper(trim($residenciaRaw));
        if ($resUpper === 'SI' || $resUpper === '1') {
            $residenciaRaw = 1;
        } elseif ($resUpper === 'NO' || $resUpper === '0') {
            $residenciaRaw = 0;
        }
    }

    $legacyValues = [
        'tipo_documento' => isset($data['tipo_documento']) ? strtolower(trim((string)$data['tipo_documento'])) : null,
        'lugarnacimiento' => isset($data['lugarnacimiento']) ? trim((string)$data['lugarnacimiento']) : null,
        'calle' => isset($data['calle']) ? trim((string)$data['calle']) : null,
        'urbanizacion' => isset($data['urbanizacion']) ? trim((string)$data['urbanizacion']) : null,
        'ocupacion' => isset($data['ocupacion']) ? trim((string)$data['ocupacion']) : null,
        'hijos' => ($data['hijos'] ?? '') !== '' ? max(0, (int)$data['hijos']) : null,
        'hijosdependientes' => ($data['hijosdependientes'] ?? '') !== '' ? max(0, (int)$data['hijosdependientes']) : null,
        'departamento' => isset($data['departamento']) ? trim((string)$data['departamento']) : null,
        'provincia' => isset($data['provincia']) ? trim((string)$data['provincia']) : null,
        'distrito' => isset($data['distrito']) ? trim((string)$data['distrito']) : null,
        'gradoinstruccion' => isset($data['gradoinstruccion']) ? trim((string)$data['gradoinstruccion']) : null,
        'estadocivil' => isset($data['estadocivil']) ? trim((string)$data['estadocivil']) : null,
        'nombrepadre' => isset($data['nombrepadre']) ? trim((string)$data['nombrepadre']) : null,
        'nombremadre' => isset($data['nombremadre']) ? trim((string)$data['nombremadre']) : null,
        'acompanante' => isset($data['acompanante']) ? trim((string)$data['acompanante']) : null,
        'trabajoresidencia' => $residenciaRaw === null || $residenciaRaw === '' ? null : ((int)$residenciaRaw > 0 ? 1 : 0),
        'tiemporesidencia' => ($data['tiemporesidencia'] ?? '') !== '' ? max(0, (int)$data['tiemporesidencia']) : null,
        'celular' => isset($data['celular']) ? trim((string)$data['celular']) : null,
    ];

        if (!validar_data_url_imagen($firma_digital)) {
            echo json_encode(['success' => false, 'error' => 'Firma digital invalida. Debe ser imagen PNG o JPG en base64.']);
            exit;
        }
        if (!validar_data_url_imagen($huella_digital)) {
            echo json_encode(['success' => false, 'error' => 'Huella digital invalida. Debe ser imagen PNG o JPG en base64.']);
            exit;
        }
        if (!validar_data_url_imagen($fotografia)) {
            echo json_encode(['success' => false, 'error' => 'Fotografia invalida. Debe ser imagen PNG o JPG en base64.']);
            exit;
        }

        // Validar campos obligatorios
        if (!$dni) {
            echo json_encode(['success' => false, 'error' => 'El campo DNI no debe estar vacío']);
            exit;
        }
        if (!$nombre) {
            echo json_encode(['success' => false, 'error' => 'El campo Nombre no debe estar vacío']);
            exit;
        }
        if (!$apellido) {
            echo json_encode(['success' => false, 'error' => 'El campo Apellido no debe estar vacío']);
            exit;
        }
        // La historia clínica ya no es obligatoria desde el frontend
        // Se genera automáticamente si está vacía

        $fields = [
            'dni' => $dni,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'historia_clinica' => $historia,
            'fecha_nacimiento' => $fecha_nacimiento,
            'edad' => $edad,
            'edad_unidad' => $edad_unidad,
            'procedencia' => $procedencia,
            'tipo_seguro' => $tipo_seguro,
            'sexo' => $sexo,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'email' => $email,
        ];

        if ($hasBioColumns) {
            $fields['firma_digital'] = $firma_digital;
            $fields['huella_digital'] = $huella_digital;
            $fields['fotografia'] = $fotografia;
        }

        foreach ($legacyValues as $column => $value) {
            if (!empty($legacyState[$column])) {
                $fields[$column] = $value;
            }
        }

        if ($id > 0) {
            $sets = [];
            $params = [];
            foreach ($fields as $column => $value) {
                $sets[] = $column . ' = ?';
                $params[] = $value;
            }
            $types = str_repeat('s', count($params)) . 'i';
            $params[] = $id;

            $sql = 'UPDATE pacientes SET ' . implode(', ', $sets) . ' WHERE id = ?';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Error al preparar actualización: ' . $conn->error]);
                exit;
            }
            bind_dynamic_params($stmt, $types, $params);

            if ($stmt->execute()) {
                $paciente = obtener_paciente_por_id($conn, $id, $hasBioColumns, $legacyState);
                echo json_encode(['success' => true, 'paciente' => $paciente]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar paciente: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            $columns = array_keys($fields);
            $params = array_values($fields);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $types = str_repeat('s', count($params));

            $sql = 'INSERT INTO pacientes (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Error al preparar registro: ' . $conn->error]);
                exit;
            }
            bind_dynamic_params($stmt, $types, $params);

            if ($stmt->execute()) {
                $id = $conn->insert_id;
                $paciente = obtener_paciente_por_id($conn, $id, $hasBioColumns, $legacyState);
                echo json_encode(['success' => true, 'paciente' => $paciente]);
            } else {
                if (strpos($stmt->error, 'Duplicate entry') !== false && strpos($stmt->error, 'dni') !== false) {
                    echo json_encode(['success' => false, 'error' => 'El DNI ingresado ya está registrado en el sistema.']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al registrar paciente: ' . $stmt->error]);
                }
            }
            $stmt->close();
        }
        exit;
}



// Listar un paciente por id (GET ?id=...)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $hasBioColumns = pacientes_bio_columns_available($conn);
    $legacyState = pacientes_columns_state($conn, [
        'tipo_documento',
        'lugarnacimiento',
        'calle',
        'urbanizacion',
        'ocupacion',
        'hijos',
        'hijosdependientes',
        'departamento',
        'provincia',
        'distrito',
        'gradoinstruccion',
        'estadocivil',
        'nombrepadre',
        'nombremadre',
        'acompanante',
        'trabajoresidencia',
        'tiemporesidencia',
        'celular',
    ]);
    $row = obtener_paciente_por_id($conn, $id, $hasBioColumns, $legacyState);
    if ($row) {
        // Calcular edad si no está
        if (empty($row['edad']) && !empty($row['fecha_nacimiento'])) {
            $birth = new DateTime($row['fecha_nacimiento']);
            $today = new DateTime();
            $row['edad'] = $today->diff($birth)->y;
            $row['edad_unidad'] = 'años';
        }
        echo json_encode(['success' => true, 'paciente' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Paciente no encontrado']);
    }
    exit;
}

// Listar todos los pacientes (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Paginación: page y limit por GET
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;

    // Filtro de búsqueda
    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
    [$where, $params, $types] = construir_filtro_busqueda_pacientes($busqueda);

    $selectContratoActivo = '0 AS contrato_activo';
    if (pacientes_table_exists($conn, 'contratos_paciente')) {
        $selectContratoActivo = "CASE
            WHEN EXISTS (
                SELECT 1 FROM contratos_paciente cp
                WHERE cp.paciente_id = pacientes.id
                  AND cp.estado = 'activo'
                  AND CURDATE() BETWEEN cp.fecha_inicio AND cp.fecha_fin
            ) THEN 2
            WHEN EXISTS (
                SELECT 1 FROM contratos_paciente cp
                WHERE cp.paciente_id = pacientes.id
            ) THEN 1
            ELSE 0
        END AS contrato_activo";
    }

    // Obtener el total de pacientes filtrados
    if ($where) {
        $sqlTotal = "SELECT COUNT(*) as total FROM pacientes $where";
        $stmtTotal = $conn->prepare($sqlTotal);
        $stmtTotal->bind_param($types, ...$params);
        $stmtTotal->execute();
        $resTotal = $stmtTotal->get_result();
        $rowTotal = $resTotal->fetch_assoc();
        $total = intval($rowTotal['total']);
        $stmtTotal->close();
    } else {
        $resTotal = $conn->query("SELECT COUNT(*) as total FROM pacientes");
        $rowTotal = $resTotal->fetch_assoc();
        $total = intval($rowTotal['total']);
    }

    // Obtener solo los pacientes de la página actual filtrados
    if ($where) {
        $sql = "SELECT id, historia_clinica, nombre, apellido, fecha_nacimiento, edad, edad_unidad, procedencia, tipo_seguro, direccion, telefono, email, dni, sexo, creado_en, $selectContratoActivo FROM pacientes $where ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $stmt = $conn->prepare("SELECT id, historia_clinica, nombre, apellido, fecha_nacimiento, edad, edad_unidad, procedencia, tipo_seguro, direccion, telefono, email, dni, sexo, creado_en, $selectContratoActivo FROM pacientes ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    $pacientes = [];
    while ($row = $result->fetch_assoc()) {
        // Si edad está en la BD, úsala; si no, calcula desde fecha_nacimiento
        if (!empty($row['edad'])) {
            // Ya viene de la BD
        } else if (!empty($row['fecha_nacimiento'])) {
            $birth = new DateTime($row['fecha_nacimiento']);
            $today = new DateTime();
            $row['edad'] = $today->diff($birth)->y;
            $row['edad_unidad'] = 'años';
        } else {
            $row['edad'] = null;
        }
        $pacientes[] = $row;
    }
    $stmt->close();
    echo json_encode([
        'success' => true,
        'pacientes' => $pacientes,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => ceil($total / $limit)
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
