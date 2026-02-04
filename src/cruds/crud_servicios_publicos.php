<?php
/**
 * CRUD para Servicios Públicos - Banca Virtual
 * Tabla: services_public
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
// GUARDAR SERVICIO PÚBLICO
// ============================================
if ($action === 'guardarServicioPublico') {
    // Validar CSRF
    $tokenName = $csrf->getTokenName();
    $tokenValue = $_POST[$tokenName] ?? '';
    
    if (!$csrf->validateToken($tokenValue)) {
        sendJSON(['status' => 0, 'msg' => 'Token de seguridad inválido']);
    }
    
    // Obtener datos
    $title = trim($_POST['titSer'] ?? '');
    $body = trim($_POST['bodSer'] ?? '');
    $image = ''; // La imagen se maneja separadamente con upload
    
    // Validaciones
    if (empty($title)) {
        sendJSON(['status' => 0, 'msg' => 'El título del servicio es obligatorio']);
    }
    
    if (strlen($title) < 3) {
        sendJSON(['status' => 0, 'msg' => 'El título debe tener al menos 3 caracteres']);
    }
    
    if (empty($body)) {
        sendJSON(['status' => 0, 'msg' => 'La descripción del servicio es obligatoria']);
    }
    
    if (strlen($body) < 10) {
        sendJSON(['status' => 0, 'msg' => 'La descripción debe tener al menos 10 caracteres']);
    }
    
    // Manejo de imagen (si se envió)
    if (isset($_FILES['imgSer']) && $_FILES['imgSer']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../public/uploads/servicios/';
        
        // Crear directorio si no existe
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['imgSer']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            sendJSON(['status' => 0, 'msg' => 'Formato de imagen no válido. Use: JPG, PNG, GIF o WEBP']);
        }
        
        // Generar nombre único
        $fileName = 'servicio_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['imgSer']['tmp_name'], $uploadPath)) {
            $image = 'public/uploads/servicios/' . $fileName;
        } else {
            sendJSON(['status' => 0, 'msg' => 'Error al subir la imagen']);
        }
    }
    
    // Preparar consulta
    $stmt = mysqli_prepare($conexion, 
        "INSERT INTO services_public (title, body, image, created_at, updated_at) 
         VALUES (?, ?, ?, NOW(), NOW())"
    );
    
    if (!$stmt) {
        sendJSON(['status' => 0, 'msg' => 'Error al preparar consulta: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_bind_param($stmt, 'sss', $title, $body, $image);
    
    if (mysqli_stmt_execute($stmt)) {
        sendJSON(['status' => 1, 'msg' => 'Servicio guardado correctamente']);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error al guardar: ' . mysqli_stmt_error($stmt)]);
    }
}

// ============================================
// ACTUALIZAR SERVICIO PÚBLICO
// ============================================
if ($action === 'actualizarServicioPublico') {
    // Validar CSRF
    $tokenName = $csrf->getTokenName();
    $tokenValue = $_POST[$tokenName] ?? '';
    
    if (!$csrf->validateToken($tokenValue)) {
        sendJSON(['status' => 0, 'msg' => 'Token de seguridad inválido']);
    }
    
    // Obtener datos
    $id = (int)($_POST['idSer'] ?? 0);
    $title = trim($_POST['titSer'] ?? '');
    $body = trim($_POST['bodSer'] ?? '');
    $imageActual = $_POST['imgActual'] ?? '';
    
    // Validaciones
    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de servicio inválido']);
    }
    
    if (empty($title)) {
        sendJSON(['status' => 0, 'msg' => 'El título del servicio es obligatorio']);
    }
    
    if (empty($body)) {
        sendJSON(['status' => 0, 'msg' => 'La descripción del servicio es obligatoria']);
    }
    
    // Manejo de nueva imagen (opcional)
    $image = $imageActual;
    if (isset($_FILES['imgSer']) && $_FILES['imgSer']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../public/uploads/servicios/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['imgSer']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'servicio_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['imgSer']['tmp_name'], $uploadPath)) {
                // Eliminar imagen anterior si existe
                if (!empty($imageActual) && file_exists(__DIR__ . '/../../' . $imageActual)) {
                    unlink(__DIR__ . '/../../' . $imageActual);
                }
                $image = 'public/uploads/servicios/' . $fileName;
            }
        }
    }
    
    // Preparar consulta
    $stmt = mysqli_prepare($conexion, 
        "UPDATE services_public 
         SET title = ?, body = ?, image = ?, updated_at = NOW() 
         WHERE id = ?"
    );
    
    if (!$stmt) {
        sendJSON(['status' => 0, 'msg' => 'Error al preparar consulta: ' . mysqli_error($conexion)]);
    }
    
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $body, $image, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        sendJSON(['status' => 1, 'msg' => 'Servicio actualizado correctamente']);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error al actualizar: ' . mysqli_stmt_error($stmt)]);
    }
}

// ============================================
// ELIMINAR SERVICIO PÚBLICO
// ============================================
if ($action === 'eliminarServicioPublico') {
    // Validar CSRF
    $tokenName = $csrf->getTokenName();
    $tokenValue = $_POST[$tokenName] ?? '';
    
    if (!$csrf->validateToken($tokenValue)) {
        sendJSON(['status' => 0, 'msg' => 'Token de seguridad inválido']);
    }
    
    $id = (int)($_POST['tempIdEliminar'] ?? 0);
    
    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de servicio inválido']);
    }
    
    // Obtener imagen antes de eliminar
    $queryImg = mysqli_prepare($conexion, "SELECT image FROM services_public WHERE id = ?");
    mysqli_stmt_bind_param($queryImg, 'i', $id);
    mysqli_stmt_execute($queryImg);
    $result = mysqli_stmt_get_result($queryImg);
    $row = mysqli_fetch_assoc($result);
    
    // Eliminar registro
    $stmt = mysqli_prepare($conexion, "DELETE FROM services_public WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Eliminar imagen física si existe
        if (!empty($row['image']) && file_exists(__DIR__ . '/../../' . $row['image'])) {
            unlink(__DIR__ . '/../../' . $row['image']);
        }
        sendJSON(['status' => 1, 'msg' => 'Servicio eliminado correctamente']);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error al eliminar servicio']);
    }
}

// Si no hay acción válida
sendJSON(['status' => 0, 'msg' => 'Acción no válida']);
?>
