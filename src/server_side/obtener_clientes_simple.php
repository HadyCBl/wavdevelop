<?php
/**
 * Obtener lista simple de clientes para selector
 * Retorna array JSON con código y nombre de clientes
 */

// Evitar cualquier output antes del JSON
ob_start();

// Iniciar sesión y validar usuario
session_start();

// Validar sesión (usando la misma validación que el sistema principal)
if (!isset($_SESSION['id_agencia'])) {
    ob_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
    ob_end_flush();
    exit;
}

// Incluir conexión a base de datos principal
require_once __DIR__ . '/../../includes/BD_con/db_con.php';

// Establecer charset UTF-8
if (isset($conexion) && $conexion) {
    mysqli_set_charset($conexion, 'utf8mb4');
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar que la conexión existe
    if (!isset($conexion) || !$conexion) {
        throw new Exception('Error: No se pudo establecer conexión a la base de datos');
    }
    
    // Verificar que la conexión está activa
    if (mysqli_ping($conexion) === false) {
        throw new Exception('Error: La conexión a la base de datos se ha perdido');
    }
    
    // Consulta para obtener clientes activos
    // Incluyendo email y teléfono para envío de credenciales
    $query = "SELECT 
                idcod_cliente AS codigo,
                CONCAT(
                    COALESCE(primer_name, ''), ' ',
                    COALESCE(segundo_name, ''), ' ',
                    COALESCE(tercer_name, ''), ' ',
                    COALESCE(primer_last, ''), ' ',
                    COALESCE(segundo_last, ''), ' ',
                    COALESCE(casada_last, '')
                ) AS nombre,
                COALESCE(email, '') AS email,
                COALESCE(tel_no1, '') AS telefono
              FROM tb_cliente 
              WHERE estado = 1
              ORDER BY no_identifica ASC, primer_name ASC, primer_last ASC
              LIMIT 1000";
    
    $resultado = mysqli_query($conexion, $query);
    
    if (!$resultado) {
        $error = mysqli_error($conexion);
        error_log('Error en consulta de clientes: ' . $error);
        throw new Exception('Error en la consulta: ' . $error);
    }
    
    $clientes = [];
    while ($row = mysqli_fetch_assoc($resultado)) {
        $nombreCompleto = trim($row['nombre']);
        // Solo agregar si el nombre no está vacío
        if (!empty($nombreCompleto) && !empty($row['codigo'])) {
            $clientes[] = [
                'codigo' => $row['codigo'],
                'nombre' => $nombreCompleto,
                'email' => trim($row['email']),
                'telefono' => trim($row['telefono'])
            ];
        }
    }
    
    // Liberar resultado
    mysqli_free_result($resultado);
    
    // Limpiar cualquier output previo
    ob_clean();
    
    // Responder con los datos
    echo json_encode($clientes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Limpiar cualquier output previo
    ob_clean();
    
    // Log del error para debugging
    error_log('Error en obtener_clientes_simple.php: ' . $e->getMessage());
    
    // Responder con error
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al cargar los clientes',
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
// NO cerrar la conexión aquí, puede ser usada por otros procesos
?>
