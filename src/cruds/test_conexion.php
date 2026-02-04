<?php
// Archivo de prueba para diagnosticar conexiones
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../includes/BD_con/db_con.php';
    require_once __DIR__ . '/../../includes/BD_con/conexion_bank.php';

    $connBank = isset($virtual) ? $virtual : null;
    $connMain = isset($conexion) ? $conexion : null;
    
    $diagnostico = [
        'conexion_bank' => $connBank ? 'OK' : 'FALLO',
        'conexion_main' => $connMain ? 'OK' : 'FALLO',
        'error_bank' => $connBank ? null : mysqli_connect_error(),
        'error_main' => $connMain ? null : mysqli_connect_error()
    ];
    
    // Probar consulta en base de datos virtual
    if ($connBank) {
        $result = $connBank->query("SHOW TABLES LIKE 'users'");
        $diagnostico['tabla_users_existe'] = $result && $result->num_rows > 0 ? 'SI' : 'NO';
        
        if ($diagnostico['tabla_users_existe'] === 'SI') {
            $result = $connBank->query("SELECT COUNT(*) as total FROM users");
            if ($result) {
                $row = $result->fetch_assoc();
                $diagnostico['total_users'] = $row['total'];
            }
        }
    }
    
    // Probar consulta en base de datos principal
    if ($connMain) {
        $result = $connMain->query("SHOW TABLES LIKE 'tb_cliente'");
        $diagnostico['tabla_cliente_existe'] = $result && $result->num_rows > 0 ? 'SI' : 'NO';
    }
    
    echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
