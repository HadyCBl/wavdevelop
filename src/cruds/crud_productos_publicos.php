<?php
/**
 * CRUD para Productos Crediticios Públicos - Banca Virtual
 * Tabla: cre_prod_public
 */

session_start();
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier salida previa
ob_start();

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/BD_con/conection.php';
require_once __DIR__ . '/../../src/clases/CSRFProtection.php';

// Función helper para enviar JSON limpio
function sendJSON($data) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Validar sesión
if (!isset($_SESSION['user'])) {
    sendJSON(['status' => 0, 'msg' => 'Sesión no válida']);
}

// Instanciar CSRF
$csrf = new CSRFProtection();

// Obtener acción
$action = $_POST['action'] ?? '';

// ============================================
// GUARDAR PRODUCTO PÚBLICO
// ============================================
if ($action === 'guardarProductoPublico') {
    // Validar CSRF
    $tokenName = $csrf->getTokenName();
    $tokenValue = $_POST[$tokenName] ?? '';
    
    if (!$csrf->validateToken($tokenValue)) {
        sendJSON(['status' => 0, 'msg' => 'Token de seguridad inválido']);
    }
    
    // Obtener datos
    $nombre = trim($_POST['nomPro'] ?? '');
    $descripcion = trim($_POST['desPro'] ?? '');
    $published = isset($_POST['published']) ? (int)$_POST['published'] : 0;
    $created_by = $_SESSION['user'] ?? '';
    
    // Validaciones
    if (empty($nombre)) {
        sendJSON(['status' => 0, 'msg' => 'El nombre del producto es obligatorio']);
    }
    
    if (strlen($nombre) < 3) {
        sendJSON(['status' => 0, 'msg' => 'El nombre debe tener al menos 3 caracteres']);
    }
    
    // Preparar consulta
    $stmt = mysqli_prepare($conexion, 
        "INSERT INTO cre_prod_public (nombre, descripcion, published, created_at, updated_at) 
         VALUES (?, ?, ?, NOW(), NOW())"
    );
    
    if (!$stmt) {
        sendJSON(['status' => 0, 'msg' => 'Error al preparar consulta: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_bind_param($stmt, 'ssi', $nombre, $descripcion, $published);
    
    if (mysqli_stmt_execute($stmt)) {
        sendJSON(['status' => 1, 'msg' => 'Producto guardado correctamente']);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error al guardar: ' . mysqli_stmt_error($stmt)]);
    }
}

// ============================================
// ACTUALIZAR PRODUCTO PÚBLICO
// ============================================
if ($action === 'actualizarProductoPublico') {
    // Validar CSRF
    $tokenName = $csrf->getTokenName();
    $tokenValue = $_POST[$tokenName] ?? '';
    
    if (!$csrf->validateToken($tokenValue)) {
        sendJSON(['status' => 0, 'msg' => 'Token de seguridad inválido']);
    }
    
    // Obtener datos
    $id = (int)($_POST['idPro'] ?? 0);
    $nombre = trim($_POST['nomPro'] ?? '');
    $descripcion = trim($_POST['desPro'] ?? '');
    $published = isset($_POST['published']) ? (int)$_POST['published'] : 0;
    
    // Validaciones
    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de producto inválido']);
    }
    
    if (empty($nombre)) {
        sendJSON(['status' => 0, 'msg' => 'El nombre del producto es obligatorio']);
    }
    
    // Preparar consulta
    $stmt = mysqli_prepare($conexion, 
        "UPDATE cre_prod_public 
         SET nombre = ?, descripcion = ?, published = ?, updated_at = NOW() 
         WHERE id = ?"
    );
    
    if (!$stmt) {
        sendJSON(['status' => 0, 'msg' => 'Error al preparar consulta: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_bind_param($stmt, 'ssii', $nombre, $descripcion, $published, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        sendJSON(['status' => 1, 'msg' => 'Producto actualizado correctamente']);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error al actualizar: ' . mysqli_stmt_error($stmt)]);
    }
}

// ============================================
// CAMBIAR ESTADO DE PUBLICACIÓN
// ============================================
if ($action === 'cambiarEstadoProductoPublico') {
    // Validar CSRF
    $tokenName = $csrf->getTokenName();
    $tokenValue = $_POST[$tokenName] ?? '';
    
    if (!$csrf->validateToken($tokenValue)) {
        sendJSON(['status' => 0, 'msg' => 'Token de seguridad inválido']);
    }
    
    $id = (int)($_POST['tempId'] ?? 0);
    $published = isset($_POST['tempPublished']) ? (int)$_POST['tempPublished'] : 0;
    
    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de producto inválido']);
    }
    
    $stmt = mysqli_prepare($conexion, 
        "UPDATE cre_prod_public SET published = ?, updated_at = NOW() WHERE id = ?"
    );
    
    mysqli_stmt_bind_param($stmt, 'ii', $published, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $estado = $published == 1 ? 'publicado' : 'despublicado';
        sendJSON(['status' => 1, 'msg' => "Producto $estado correctamente"]);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error al cambiar estado']);
    }
}

// ============================================
// ELIMINAR PRODUCTO PÚBLICO
// ============================================
if ($action === 'eliminarProductoPublico') {
    // Validar CSRF
    $tokenName = $csrf->getTokenName();
    $tokenValue = $_POST[$tokenName] ?? '';
    
    if (!$csrf->validateToken($tokenValue)) {
        sendJSON(['status' => 0, 'msg' => 'Token de seguridad inválido']);
    }
    
    $id = (int)($_POST['tempIdEliminar'] ?? 0);
    
    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de producto inválido']);
    }
    
    $stmt = mysqli_prepare($conexion, "DELETE FROM cre_prod_public WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        sendJSON(['status' => 1, 'msg' => 'Producto eliminado correctamente']);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error al eliminar producto']);
    }
}

// Si no hay acción válida
sendJSON(['status' => 0, 'msg' => 'Acción no válida']);
?>
