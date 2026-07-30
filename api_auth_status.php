<?php
require_once __DIR__ . '/init_api.php';

try {
    if (isset($_SESSION['empresa_portal']) && is_array($_SESSION['empresa_portal'])) {
        $empresa = $_SESSION['empresa_portal'];
        $usuarioEmpresa = [
            'id' => (int)($empresa['usuario_portal_id'] ?? 0),
            'usuario' => (string)($empresa['email_login'] ?? ''),
            'nombre' => (string)($empresa['nombre_empresa'] ?? ''),
            'rol' => 'empresa',
            'empresa_id' => (int)($empresa['empresa_id'] ?? 0),
            'permisos' => ['empresa_portal_read'],
        ];
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'usuario' => $usuarioEmpresa,
            'usuario_id' => $usuarioEmpresa['id'],
            'nombre' => $usuarioEmpresa['nombre'],
            'rol' => 'empresa',
            'permisos' => $usuarioEmpresa['permisos'],
            'tipo' => 'empresa'
        ]);
        exit;
    }

    // Verificar si hay usuario autenticado
    if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
        // Usuario normal autenticado (estructura existente)
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'usuario_id' => $_SESSION['usuario']['id'] ?? null,
            'nombre' => $_SESSION['usuario']['nombre'] ?? '',
            'rol' => $_SESSION['usuario']['rol'] ?? '',
            'usuario' => $_SESSION['usuario']['usuario'] ?? '',
            'permisos' => $_SESSION['usuario']['permisos'] ?? [],
            'tipo' => 'usuario'
        ]);
    } elseif (isset($_SESSION['medico_id']) && isset($_SESSION['medico'])) {
        // Médico autenticado
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'usuario_id' => $_SESSION['medico_id'],
            'nombre' => $_SESSION['medico']['nombre'] ?? '',
            'rol' => 'medico',
            'permisos' => [],
            'tipo' => 'medico'
        ]);
    } else {
        // No autenticado
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'error' => 'Usuario no autenticado'
        ]);
    }
} catch (Exception $e) {
    error_log("Error en api_auth_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'error' => 'Error interno del servidor'
    ]);
}
?>