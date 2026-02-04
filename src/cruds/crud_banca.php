<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
// Suprimir warnings para evitar contaminar JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
// Capturar cualquier output no deseado
ob_start();

session_start();
include '../../includes/BD_con/db_con.php';
include '../../includes/BD_con/conexion_bank.php';
require_once __DIR__ . '/../../includes/Config/database.php';

// Configuración adicional para Banca Virtual
$db_virtual_host = $_ENV['DB_VIRTUAL_HOST'] ?? null;
$db_virtual_port = $_ENV['DB_VIRTUAL_PORT'] ?? '3306';
$db_virtual_database = $_ENV['DB_VIRTUAL_DATABASE'] ?? null;
$db_virtual_username = $_ENV['DB_VIRTUAL_USERNAME'] ?? null;
$db_virtual_password = $_ENV['DB_VIRTUAL_PASSWORD'] ?? null;

$databaseBanca = new Database($db_virtual_host, $db_virtual_database, $db_virtual_username, $db_virtual_password, $db_virtual_database);

// Importar clases necesarias
use App\Generic\Agencia;
use App\Generic\FileProcessor;

$conn = isset($virtual) ? $virtual : $conexion;
mysqli_set_charset($conn, 'utf8');

// Validar sesión
if (!isset($_SESSION['id_agencia'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
    exit;
}

date_default_timezone_set('America/Guatemala');

// Log para debugging - ver qué datos llegan
error_log("=== INICIO CRUD_BANCA ===");
error_log("POST completo: " . print_r($_POST, true));

// Procesar datos que vienen de obtiene() o directamente de POST
// obtiene() envía: { inputs: [], selects: [], radios: [], condi: '', id: '', archivo: '' }
// POST directo envía: { action: '', codcli: '', usuario: '', pass: '', id: '' }

$action = $_POST['condi'] ?? $_POST['action'] ?? '';
$id = $_POST['id'] ?? '0';

error_log("Action recibida: " . $action);
error_log("ID recibido: " . $id);

// Si viene de obtiene(), extraer de arrays
// NOTA: getinputsval() devuelve un OBJETO con claves nombradas, no un array numérico
// Ejemplo: { csrf_token: 'xxx', codcli: '001', usuario: 'u001', pass: 'abc123' }
if (isset($_POST['inputs']) && is_array($_POST['inputs'])) {
    error_log("Datos vienen de obtiene() - formato objeto con claves");
    error_log("Inputs array: " . print_r($_POST['inputs'], true));
    
    // Leer por nombre de clave (NO por índice numérico)
    $codcli = $_POST['inputs']['codcli'] ?? '';
    $usuario = $_POST['inputs']['usuario'] ?? '';
    $pass = $_POST['inputs']['pass'] ?? '';
    
    error_log("Extraído por clave - codcli: '" . $codcli . "', usuario: '" . $usuario . "', pass: " . (empty($pass) ? 'VACÍO' : '*** (longitud: ' . strlen($pass) . ')'));
} else {
    error_log("Datos vienen directamente de POST");
    // Si viene directamente de POST
    $codcli = $_POST['codcli'] ?? '';
    $usuario = $_POST['usuario'] ?? '';
    $pass = $_POST['pass'] ?? '';
    error_log("Directo - codcli: '" . $codcli . "', usuario: '" . $usuario . "', pass: " . (empty($pass) ? 'VACÍO' : '***'));
}

$response = ['status' => 0, 'msg' => 'Acción no válida'];

// Función helper para enviar JSON limpio
function sendJSON($data) {
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Crear credencial - Verificar condiciones paso a paso
error_log("=== VERIFICACIÓN CREAR CREDENCIAL ===");
error_log("Condición 1 - Action === 'crear_credencial': " . ($action === 'crear_credencial' ? 'SÍ' : 'NO') . " (valor: '" . $action . "')");
error_log("Condición 2 - codcli existe: " . (!empty($codcli) ? 'SÍ' : 'NO') . " (valor: '" . $codcli . "')");
error_log("Condición 3 - usuario existe: " . (!empty($usuario) ? 'SÍ' : 'NO') . " (valor: '" . $usuario . "')");
error_log("Condición 4 - pass existe: " . (!empty($pass) ? 'SÍ' : 'NO') . " (longitud: " . strlen($pass) . ")");
error_log("Todas las condiciones cumplidas: " . (($action === 'crear_credencial' && $codcli && $usuario && $pass) ? 'SÍ' : 'NO'));

if ($action === 'crear_credencial' && $codcli && $usuario && $pass) {
    // Log para debugging
    error_log("=== CREAR CREDENCIAL - PROCESANDO ===");
    error_log("Action: " . $action);
    error_log("Codcli: " . $codcli);
    error_log("Usuario: " . $usuario);
    error_log("Pass: " . (empty($pass) ? 'VACÍO' : '*** (longitud: ' . strlen($pass) . ')'));
    
    // Verificar que el cliente existe en el core bancario
    $stmtCheck = $conexion->prepare("SELECT idcod_cliente, compl_name FROM tb_cliente WHERE idcod_cliente = ? AND estado = 1");
    if (!$stmtCheck) {
        error_log("Error en prepare: " . $conexion->error);
        sendJSON(['status' => 0, 'message' => 'Error en validación: ' . $conexion->error, 'msg' => 'Error en validación: ' . $conexion->error]);
    }
    
    $stmtCheck->bind_param('s', $codcli);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    
    if ($resultCheck->num_rows === 0) {
        $stmtCheck->close();
        error_log("Cliente no encontrado: " . $codcli);
        sendJSON(['status' => 0, 'message' => 'Cliente no encontrado o inactivo', 'msg' => 'Cliente no encontrado o inactivo']);
    }
    $clienteData = $resultCheck->fetch_assoc();
    $stmtCheck->close();
    
    error_log("Cliente encontrado: " . $clienteData['compl_name']);

    // Verificar conexión a base de datos virtual
    if (!$virtual) {
        error_log("ERROR: No hay conexión a BD virtual");
        sendJSON(['status' => 0, 'message' => 'Error: No hay conexión con la base de datos de Banca Virtual', 'msg' => 'Error: No hay conexión con la base de datos de Banca Virtual']);
    }
    
    error_log("Conexión a BD virtual: OK");

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    $stmt = $virtual->prepare(
        "INSERT INTO users (`name`, email, `password`, created_at, updated_at) " .
        "VALUES (?,?,?,NOW(),NOW()) " .
        "ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password), updated_at=NOW()"
    );
    if ($stmt) {
        $stmt->bind_param('sss', $usuario, $codcli, $hash);

        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            error_log("Credencial insertada/actualizada. Filas afectadas: " . $affectedRows);
            
            // Devolver la contraseña en texto plano para que pueda ser enviada
            // Usar tanto 'message' como 'msg' para compatibilidad
            sendJSON([
                'status' => 1, 
                'message' => 'Credenciales creadas correctamente para: ' . $clienteData['compl_name'],
                'msg' => 'Credenciales creadas correctamente para: ' . $clienteData['compl_name'],
                'data' => [
                    'usuario' => $usuario,
                    'password' => $pass, // Contraseña en texto plano
                    'codcli' => $codcli,
                    'nombre' => $clienteData['compl_name']
                ]
            ]);
        } else {
            error_log("Error al ejecutar INSERT: " . $stmt->error);
            sendJSON(['status' => 0, 'message' => 'Error al guardar: ' . $stmt->error, 'msg' => 'Error al guardar: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        error_log("Error en prepare INSERT: " . $virtual->error);
        sendJSON(['status' => 0, 'message' => 'Error en la consulta: ' . $virtual->error, 'msg' => 'Error en la consulta: ' . $virtual->error]);
    }
}

// Editar credencial
elseif ($action === 'editar_credencial' && $id && $usuario) {
    // Verificar conexión a base de datos virtual
    if (!$virtual) {
        sendJSON(['status' => 0, 'msg' => 'Error: No hay conexión con la base de datos de Banca Virtual']);
    }

    $updateQuery = "UPDATE users SET name = ?, updated_at = NOW()";
    $params = [$usuario];
    $types = 's';
    
    // Si se proporciona nueva contraseña, incluirla en la actualización
    if (!empty($pass)) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $updateQuery .= ", password = ?";
        $params[] = $hash;
        $types .= 's';
    }
    
    $updateQuery .= " WHERE id = ?";
    $params[] = $id;
    $types .= 'i';
    
    $stmt = $virtual->prepare($updateQuery);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                sendJSON(['status' => 1, 'msg' => 'Credenciales actualizadas correctamente']);
            } else {
                sendJSON(['status' => 0, 'msg' => 'No se encontró la credencial o no hubo cambios']);
            }
        } else {
            sendJSON(['status' => 0, 'msg' => 'Error al actualizar: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en la consulta: ' . $virtual->error]);
    }
}

// Eliminar credencial
elseif ($action === 'eliminar_credencial' && $id) {
    // Verificar conexión a base de datos virtual
    if (!$virtual) {
        sendJSON(['status' => 0, 'msg' => 'Error: No hay conexión con la base de datos de Banca Virtual']);
    }

    $stmt = $virtual->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                sendJSON(['status' => 1, 'msg' => 'Credencial eliminada correctamente']);
            } else {
                sendJSON(['status' => 0, 'msg' => 'No se encontró la credencial']);
            }
        } else {
            sendJSON(['status' => 0, 'msg' => 'Error al eliminar: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en la consulta: ' . $virtual->error]);
    }
}

// Obtener credencial por ID (para edición)
elseif ($action === 'obtener_credencial' && $id) {
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $response = ['status' => 1, 'data' => $row];
            } else {
                $response = ['status' => 0, 'msg' => 'Credencial no encontrada'];
            }
        } else {
            $response = ['status' => 0, 'msg' => 'Error al consultar: ' . $stmt->error];
        }
        $stmt->close();
    } else {
        $response = ['status' => 0, 'msg' => 'Error en la consulta: ' . $conn->error];
    }
}

// -----------------------------------------------------------------------------
// GESTIÓN DE PERMISOS DE BANCA VIRTUAL
// -----------------------------------------------------------------------------

// Obtener permisos de un usuario
elseif ($action === 'obtener_permisos_usuario') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de usuario inválido']);
    }
    
    if (!$virtual) {
        sendJSON(['status' => 0, 'msg' => 'Error de conexión a base de datos virtual']);
    }
    
    $stmt = $virtual->prepare("
        SELECT permiso_id 
        FROM user_permissions 
        WHERE user_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permisos = [];
        while ($row = $result->fetch_assoc()) {
            $permisos[] = $row['permiso_id'];
        }
        
        $stmt->close();
        sendJSON(['status' => 1, 'permisos' => $permisos]);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en consulta: ' . $virtual->error]);
    }
}

// Guardar permisos de un usuario
elseif ($action === 'guardar_permisos_usuario') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $permisos = json_decode($_POST['permisos'] ?? '[]', true);
    $otorgado_por = intval($_SESSION['id'] ?? 0);
    
    if ($user_id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de usuario inválido']);
    }
    
    if (!is_array($permisos)) {
        sendJSON(['status' => 0, 'msg' => 'Formato de permisos inválido']);
    }
    
    if (!$virtual) {
        sendJSON(['status' => 0, 'msg' => 'Error de conexión a base de datos virtual']);
    }
    
    // Iniciar transacción
    $virtual->begin_transaction();
    
    try {
        // Eliminar permisos actuales
        $stmtDelete = $virtual->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmtDelete->bind_param('i', $user_id);
        $stmtDelete->execute();
        $stmtDelete->close();
        
        // Insertar nuevos permisos
        if (count($permisos) > 0) {
            $stmtInsert = $virtual->prepare("
                INSERT INTO user_permissions (user_id, permiso_id, otorgado_por, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            foreach ($permisos as $permiso_id) {
                $permiso_id = intval($permiso_id);
                $stmtInsert->bind_param('iii', $user_id, $permiso_id, $otorgado_por);
                $stmtInsert->execute();
            }
            $stmtInsert->close();
        }
        
        $virtual->commit();
        sendJSON(['status' => 1, 'msg' => 'Permisos actualizados correctamente']);
        
    } catch (Exception $e) {
        $virtual->rollback();
        sendJSON(['status' => 0, 'msg' => 'Error al guardar permisos: ' . $e->getMessage()]);
    }
}

// Listar todos los permisos disponibles
elseif ($action === 'listar_permisos_disponibles') {
    if (!$virtual) {
        sendJSON(['status' => 0, 'msg' => 'Error de conexión a base de datos virtual', 'data' => []]);
    }
    
    $query = "
        SELECT 
            id,
            nombre,
            codigo,
            descripcion,
            categoria,
            icono,
            orden,
            activo
        FROM permisos_banca_virtual
        WHERE activo = 1
        ORDER BY categoria, orden, nombre
    ";
    
    $result = $virtual->query($query);
    
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    sendJSON(['status' => 1, 'data' => $data]);
}

// Listar permisos de todos los usuarios
elseif ($action === 'listar_permisos_usuarios') {
    if (!$virtual) {
        sendJSON(['status' => 0, 'msg' => 'Error de conexión a base de datos virtual', 'data' => []]);
    }
    
    $query = "
        SELECT 
            u.id,
            u.name AS usuario,
            u.email,
            COUNT(up.permiso_id) AS total_permisos
        FROM users u
        LEFT JOIN user_permissions up ON u.id = up.user_id
        GROUP BY u.id, u.name, u.email
        ORDER BY u.name
    ";
    
    $result = $virtual->query($query);
    
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    sendJSON(['status' => 1, 'data' => $data]);
}

// Obtener detalle de permisos de un usuario
elseif ($action === 'obtener_detalle_permisos') {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de usuario inválido']);
    }
    
    if (!$virtual) {
        sendJSON(['status' => 0, 'msg' => 'Error de conexión a base de datos virtual']);
    }
    
    $stmt = $virtual->prepare("
        SELECT 
            p.id,
            p.modulo,
            p.nombre_permiso,
            p.descripcion
        FROM user_permissions up
        INNER JOIN permisos_banca_virtual p ON up.permiso_id = p.id
        WHERE up.user_id = ?
        ORDER BY p.modulo, p.nombre_permiso
    ");
    
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $permisos = [];
        while ($row = $result->fetch_assoc()) {
            $permisos[] = $row;
        }
        
        $stmt->close();
        sendJSON(['status' => 1, 'permisos' => $permisos]);
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en consulta: ' . $virtual->error]);
    }
}

// -----------------------------------------------------------------------------
// CRUD para tablas públicas (externas)
// subaction: list | create | update | delete
// table: cre_prod_public | services_public
// params (POST): subaction, table, data (array), id (for update/delete), limit, offset
// -----------------------------------------------------------------------------
if ($action === 'public_crud') {
    error_log("=== INICIO public_crud ===");
    
    $sub = $_POST['subaction'] ?? 'list';
    $table = $_POST['table'] ?? '';
    $allowed = ['cre_prod_public', 'services_public'];
    
    error_log("Subaction: $sub, Table: $table");
    
    // Usar conexión principal (las tablas están en la BD principal)
    $dbConn = $conexion;
    
    if (!$dbConn) {
        error_log("ERROR: No hay conexión disponible");
        sendJSON(['status' => 0, 'msg' => 'Error: No hay conexión con la base de datos']);
    }
    
    mysqli_set_charset($dbConn, 'utf8mb4');
    
    // Verificar y crear tablas si no existen
    $createTableSQL = [
        'cre_prod_public' => "CREATE TABLE IF NOT EXISTS cre_prod_public (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            descripcion TEXT,
            published TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'services_public' => "CREATE TABLE IF NOT EXISTS services_public (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            image VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    // Crear tabla si es necesario
    if (in_array($table, $allowed) && isset($createTableSQL[$table])) {
        $createResult = $dbConn->query($createTableSQL[$table]);
        if (!$createResult) {
            error_log("Error al crear/verificar tabla $table: " . $dbConn->error);
        }
    }

    if (!in_array($table, $allowed)) {
        $response = ['status' => 0, 'msg' => 'Tabla no permitida: ' . $table];
    } else {
        // Usar nombre de tabla simple (sin prefijo de esquema)
        $fqtable = $table;

        try {
            if ($sub === 'list') {
                $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 1000;
                $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

                // columns to select depending on table
                if ($table === 'cre_prod_public') {
                    $cols = 'id, nombre, descripcion, published, created_at, updated_at';
                } else {
                    $cols = 'id, title, body, image, created_at, updated_at';
                }

                $sql = "SELECT $cols FROM $fqtable ORDER BY id DESC LIMIT ? OFFSET ?";
                error_log("SQL: $sql");
                
                $stmt = $dbConn->prepare($sql);
                if (!$stmt) {
                    error_log("Prepare error: " . $dbConn->error);
                    throw new Exception('Prepare falló: ' . $dbConn->error);
                }
                $stmt->bind_param('ii', $limit, $offset);
                $stmt->execute();
                $res = $stmt->get_result();
                $rows = [];
                while ($r = $res->fetch_assoc()) $rows[] = $r;
                $stmt->close();
                $response = ['status' => 1, 'data' => $rows];

            } elseif ($sub === 'create') {
                $data = $_POST['data'] ?? [];
                if ($table === 'cre_prod_public') {
                    // expected fields: nombre, descripcion, published
                    $nombre = $data['nombre'] ?? '';
                    $descripcion = $data['descripcion'] ?? '';
                    $published = isset($data['published']) ? intval($data['published']) : 0;
                    $sql = "INSERT INTO $fqtable (nombre, descripcion, published, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
                    $stmt = $dbConn->prepare($sql);
                    if (!$stmt) throw new Exception('Prepare falló: ' . $dbConn->error);
                    $stmt->bind_param('ssi', $nombre, $descripcion, $published);
                } else {
                    // services_public: title, body, image
                    $title = $data['title'] ?? '';
                    $body = $data['body'] ?? '';
                    $image = $data['image'] ?? '';
                    $sql = "INSERT INTO $fqtable (title, body, image, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
                    $stmt = $dbConn->prepare($sql);
                    if (!$stmt) throw new Exception('Prepare falló: ' . $dbConn->error);
                    $stmt->bind_param('sss', $title, $body, $image);
                }
                if ($stmt->execute()) {
                    $insertId = $stmt->insert_id;
                    $response = ['status' => 1, 'id' => $insertId, 'msg' => 'Registro creado'];
                } else {
                    $response = ['status' => 0, 'msg' => 'Error al crear: ' . $stmt->error];
                }
                $stmt->close();

            } elseif ($sub === 'update') {
                $idToUpdate = intval($_POST['id'] ?? 0);
                $data = $_POST['data'] ?? [];
                if ($idToUpdate <= 0) throw new Exception('ID inválido');

                if ($table === 'cre_prod_public') {
                    $nombre = $data['nombre'] ?? '';
                    $descripcion = $data['descripcion'] ?? '';
                    $published = isset($data['published']) ? intval($data['published']) : 0;
                    $sql = "UPDATE $fqtable SET nombre = ?, descripcion = ?, published = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $dbConn->prepare($sql);
                    if (!$stmt) throw new Exception('Prepare falló: ' . $dbConn->error);
                    $stmt->bind_param('ssii', $nombre, $descripcion, $published, $idToUpdate);
                } else {
                    $title = $data['title'] ?? '';
                    $body = $data['body'] ?? '';
                    $image = $data['image'] ?? '';
                    $sql = "UPDATE $fqtable SET title = ?, body = ?, image = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $dbConn->prepare($sql);
                    if (!$stmt) throw new Exception('Prepare falló: ' . $dbConn->error);
                    $stmt->bind_param('sssi', $title, $body, $image, $idToUpdate);
                }
                if ($stmt->execute()) {
                    $response = ['status' => 1, 'affected' => $stmt->affected_rows, 'msg' => 'Registro actualizado'];
                } else {
                    $response = ['status' => 0, 'msg' => 'Error al actualizar: ' . $stmt->error];
                }
                $stmt->close();

            } elseif ($sub === 'delete') {
                $idToDelete = intval($_POST['id'] ?? 0);
                if ($idToDelete <= 0) throw new Exception('ID inválido');
                $sql = "DELETE FROM $fqtable WHERE id = ?";
                $stmt = $dbConn->prepare($sql);
                if (!$stmt) throw new Exception('Prepare falló: ' . $dbConn->error);
                $stmt->bind_param('i', $idToDelete);
                if ($stmt->execute()) {
                    $response = ['status' => 1, 'affected' => $stmt->affected_rows, 'msg' => 'Registro eliminado'];
                } else {
                    $response = ['status' => 0, 'msg' => 'Error al eliminar: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                $response = ['status' => 0, 'msg' => 'Subacción no reconocida'];
            }
        } catch (Exception $e) {
            error_log("Excepción en public_crud: " . $e->getMessage());
            $response = ['status' => 0, 'msg' => 'Excepción: ' . $e->getMessage()];
        }
    }
    
    error_log("=== FIN public_crud - Response: " . json_encode($response));
    sendJSON($response);
}

// ============================================
// GESTIÓN DE PRODUCTOS PÚBLICOS DE CRÉDITO
// ============================================

// Guardar nuevo producto público
elseif ($action === 'guardarProductoPublico') {
    // Validación CSRF
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    $inputs = $_POST['inputs'] ?? [];
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        sendJSON([
            'status' => 0,
            'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar."
        ]);
    }

    // Limpiar y validar datos con UTF-8
    $nombre = mb_convert_encoding(trim($_POST['nomPro'] ?? ''), 'UTF-8', 'auto');
    $descripcion = mb_convert_encoding(trim($_POST['desPro'] ?? ''), 'UTF-8', 'auto');
    $published = intval($_POST['published'] ?? 0);

    if (empty($nombre)) {
        sendJSON(['status' => 0, 'msg' => 'El nombre del producto es obligatorio']);
    }

    if (mb_strlen($nombre) < 3) {
        sendJSON(['status' => 0, 'msg' => 'El nombre debe tener al menos 3 caracteres']);
    }

    // Usar conexión principal ($conexion) en lugar de $virtual
    if (!$conexion) {
        sendJSON(['status' => 0, 'msg' => 'Error: No hay conexión con la base de datos']);
    }

    // Asegurar UTF-8 en la conexión
    mysqli_set_charset($conexion, 'utf8mb4');

    $stmt = $conexion->prepare(
        "INSERT INTO cre_prod_public (nombre, descripcion, published, created_at, updated_at) 
         VALUES (?, ?, ?, NOW(), NOW())"
    );

    if ($stmt) {
        $stmt->bind_param('ssi', $nombre, $descripcion, $published);

        if ($stmt->execute()) {
            sendJSON([
                'status' => 1, 
                'msg' => 'Producto creado correctamente: ' . $nombre,
                'id' => $stmt->insert_id
            ]);
        } else {
            sendJSON(['status' => 0, 'msg' => 'Error al guardar: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en la consulta: ' . $conexion->error]);
    }
}

// Actualizar producto público existente
elseif ($action === 'actualizarProductoPublico') {
    // Validación CSRF
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    $inputs = $_POST['inputs'] ?? [];
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        sendJSON([
            'status' => 0,
            'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar."
        ]);
    }

    $id = intval($_POST['idPro'] ?? 0);
    $nombre = mb_convert_encoding(trim($_POST['nomPro'] ?? ''), 'UTF-8', 'auto');
    $descripcion = mb_convert_encoding(trim($_POST['desPro'] ?? ''), 'UTF-8', 'auto');
    $published = intval($_POST['published'] ?? 0);

    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de producto inválido']);
    }

    if (empty($nombre)) {
        sendJSON(['status' => 0, 'msg' => 'El nombre del producto es obligatorio']);
    }

    if (mb_strlen($nombre) < 3) {
        sendJSON(['status' => 0, 'msg' => 'El nombre debe tener al menos 3 caracteres']);
    }

    // Usar conexión principal ($conexion)
    if (!$conexion) {
        sendJSON(['status' => 0, 'msg' => 'Error: No hay conexión con la base de datos']);
    }

    // Asegurar UTF-8 en la conexión
    mysqli_set_charset($conexion, 'utf8mb4');

    $stmt = $conexion->prepare(
        "UPDATE cre_prod_public 
         SET nombre = ?, descripcion = ?, published = ?, updated_at = NOW() 
         WHERE id = ?"
    );

    if ($stmt) {
        $stmt->bind_param('ssii', $nombre, $descripcion, $published, $id);

        if ($stmt->execute()) {
            sendJSON([
                'status' => 1, 
                'msg' => 'Producto actualizado correctamente',
                'affected' => $stmt->affected_rows
            ]);
        } else {
            sendJSON(['status' => 0, 'msg' => 'Error al actualizar: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en la consulta: ' . $conexion->error]);
    }
}

// Cambiar estado de publicación de un producto
elseif ($action === 'cambiarEstadoProductoPublico') {
    // Validación CSRF
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    $inputs = $_POST['inputs'] ?? [];
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        sendJSON([
            'status' => 0,
            'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar."
        ]);
    }

    $id = intval($_POST['tempId'] ?? 0);
    $published = intval($_POST['tempPublished'] ?? 0);

    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de producto inválido']);
    }

    // Usar conexión principal ($conexion)
    if (!$conexion) {
        sendJSON(['status' => 0, 'msg' => 'Error: No hay conexión con la base de datos']);
    }

    $stmt = $conexion->prepare(
        "UPDATE cre_prod_public 
         SET published = ?, updated_at = NOW() 
         WHERE id = ?"
    );

    if ($stmt) {
        $stmt->bind_param('ii', $published, $id);

        if ($stmt->execute()) {
            $accion = $published == 1 ? 'publicado' : 'despublicado';
            sendJSON([
                'status' => 1, 
                'msg' => "Producto $accion correctamente",
                'affected' => $stmt->affected_rows
            ]);
        } else {
            sendJSON(['status' => 0, 'msg' => 'Error al cambiar estado: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en la consulta: ' . $conexion->error]);
    }
}

// NOTA: Código duplicado eliminado - public_crud se maneja en el bloque principal (línea ~460)

// Eliminar producto público
elseif ($action === 'eliminarProductoPublico') {
    // Validación CSRF
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    $inputs = $_POST['inputs'] ?? [];
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        sendJSON([
            'status' => 0,
            'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar."
        ]);
    }

    $id = intval($_POST['tempIdEliminar'] ?? 0);

    if ($id <= 0) {
        sendJSON(['status' => 0, 'msg' => 'ID de producto inválido']);
    }

    // Usar conexión principal ($conexion)
    if (!$conexion) {
        sendJSON(['status' => 0, 'msg' => 'Error: No hay conexión con la base de datos']);
    }

    $stmt = $conexion->prepare("DELETE FROM cre_prod_public WHERE id = ?");

    if ($stmt) {
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            sendJSON([
                'status' => 1, 
                'msg' => 'Producto eliminado correctamente',
                'affected' => $stmt->affected_rows
            ]);
        } else {
            sendJSON(['status' => 0, 'msg' => 'Error al eliminar: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        sendJSON(['status' => 0, 'msg' => 'Error en la consulta: ' . $conexion->error]);
    }
}

// ============================================
// SERVICIOS PÚBLICOS - CRUD OPERATIONS
// ============================================

// Guardar Servicio Público
if (isset($_POST['action']) && $_POST['action'] == 'guardarServicioPublico') {
    
    ob_start();
    
    // Configurar UTF-8
    mysqli_set_charset($conexion, 'utf8mb4');
    
    // Validar CSRF token
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    $inputs = $_POST['inputs'] ?? [];
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        sendJSON([
            'success' => false,
            'mensaje' => 'Token de seguridad inválido. Por favor, actualice la página e intente nuevamente.'
        ]);
        exit;
    }
    
    // Obtener y limpiar datos
    $titulo = mb_convert_encoding(trim($_POST['titSer'] ?? ''), 'UTF-8', 'UTF-8');
    $descripcion = mb_convert_encoding(trim($_POST['bodSer'] ?? ''), 'UTF-8', 'UTF-8');
    
    // Validar campos requeridos
    if (empty($titulo) || mb_strlen($titulo) < 3) {
        sendJSON(['success' => false, 'mensaje' => 'El título es requerido (mínimo 3 caracteres)']);
        exit;
    }
    
    if (empty($descripcion)) {
        sendJSON(['success' => false, 'mensaje' => 'La descripción es requerida']);
        exit;
    }
    
    // Procesar imagen usando el patrón del sistema
    $imageUrl = '';
    if (isset($_FILES['imgSer']) && $_FILES['imgSer']['error'] === UPLOAD_ERR_OK) {
        try {
            $file = $_FILES['imgSer'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            
            // Validar tipo de archivo
            if (!in_array($file['type'], $allowed)) {
                sendJSON(['success' => false, 'mensaje' => 'Formato de imagen no válido. Solo JPG, PNG o GIF']);
                exit;
            }
            
            // Validar tamaño
            if ($file['size'] > $maxSize) {
                sendJSON(['success' => false, 'mensaje' => 'La imagen no debe superar 2MB']);
                exit;
            }
            
            // Usar clase Agencia para obtener el folder de la institución
            $id_agencia = $_SESSION['id_agencia'] ?? 1;
            $folderInstitucion = (new Agencia($id_agencia))->institucion?->getFolderInstitucion();
            
            if ($folderInstitucion === null) {
                sendJSON(['success' => false, 'mensaje' => 'No se pudo obtener la carpeta de la institución']);
                exit;
            }
            
            // Construir ruta siguiendo el patrón: imgcoope.microsystemplus.com/{folder}/services/
            $salida = "../../../"; // 3 niveles arriba desde src/cruds/
            $entrada = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/services";
            $rutaEnServidor = $salida . $entrada;
            
            // Crear directorio si no existe
            if (!is_dir($rutaEnServidor)) {
                if (!mkdir($rutaEnServidor, 0777, true)) {
                    sendJSON(['success' => false, 'mensaje' => 'No se pudo crear el directorio de servicios']);
                    exit;
                }
            }
            
            // Generar nombre único con hash seguro
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $hash = substr(md5(uniqid() . time() . $file['name']), 0, 15);
            $filename = "service_" . time() . "_{$hash}.{$extension}";
            
            // Rutas completas
            $ruta_completa = $rutaEnServidor . "/" . $filename;
            $ruta_relativa = $entrada . "/" . $filename;
            
            // Mover archivo al destino final
            if (!move_uploaded_file($file['tmp_name'], $ruta_completa)) {
                sendJSON(['success' => false, 'mensaje' => 'Error al guardar la imagen en el servidor']);
                exit;
            }
            
            // Verificar que el archivo se guardó
            if (!file_exists($ruta_completa)) {
                sendJSON(['success' => false, 'mensaje' => 'El archivo no se guardó correctamente']);
                exit;
            }
            
            // Guardar la ruta relativa en la base de datos
            $imageUrl = $ruta_relativa;
            
        } catch (Exception $e) {
            sendJSON(['success' => false, 'mensaje' => 'Error al procesar la imagen: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Insertar en base de datos
    $sql = "INSERT INTO services_public (title, body, image, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())";
    
    $stmt = mysqli_prepare($conexion, $sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'mensaje' => 'Error al preparar consulta: ' . mysqli_error($conexion)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "sss", $titulo, $descripcion, $imageUrl);
    
    if (mysqli_stmt_execute($stmt)) {
        $nuevoId = mysqli_insert_id($conexion);
        sendJSON([
            'success' => true, 
            'mensaje' => 'Servicio público guardado exitosamente',
            'id' => $nuevoId
        ]);
    } else {
        sendJSON(['success' => false, 'mensaje' => 'Error al guardar: ' . mysqli_stmt_error($stmt)]);
    }
    
    mysqli_stmt_close($stmt);
    ob_end_flush();
    exit;
}

// Actualizar Servicio Público
if (isset($_POST['action']) && $_POST['action'] == 'actualizarServicioPublico') {
    
    ob_start();
    
    // Configurar UTF-8
    mysqli_set_charset($conexion, 'utf8mb4');
    
    // Validar CSRF token
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    $inputs = $_POST['inputs'] ?? [];
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        sendJSON([
            'success' => false,
            'mensaje' => 'Token de seguridad inválido. Por favor, actualice la página e intente nuevamente.'
        ]);
        exit;
    }
    
    // Obtener y limpiar datos
    $id = intval($_POST['idSerUpdate'] ?? 0);
    $titulo = mb_convert_encoding(trim($_POST['titSerUpdate'] ?? ''), 'UTF-8', 'UTF-8');
    $descripcion = mb_convert_encoding(trim($_POST['bodSerUpdate'] ?? ''), 'UTF-8', 'UTF-8');
    
    // Validar ID
    if ($id <= 0) {
        sendJSON(['success' => false, 'mensaje' => 'ID de servicio inválido']);
        exit;
    }
    
    // Validar campos requeridos
    if (empty($titulo) || mb_strlen($titulo) < 3) {
        sendJSON(['success' => false, 'mensaje' => 'El título es requerido (mínimo 3 caracteres)']);
        exit;
    }
    
    if (empty($descripcion)) {
        sendJSON(['success' => false, 'mensaje' => 'La descripción es requerida']);
        exit;
    }
    
    // Obtener imagen actual
    $sqlCurrent = "SELECT image FROM services_public WHERE id = ?";
    $stmtCurrent = mysqli_prepare($conexion, $sqlCurrent);
    mysqli_stmt_bind_param($stmtCurrent, "i", $id);
    mysqli_stmt_execute($stmtCurrent);
    $resultCurrent = mysqli_stmt_get_result($stmtCurrent);
    $currentData = mysqli_fetch_assoc($resultCurrent);
    $oldImageUrl = $currentData['image'] ?? '';
    mysqli_stmt_close($stmtCurrent);
    
    // Procesar nueva imagen si se subió
    $imageUrl = $oldImageUrl; // Mantener la actual por defecto
    
    if (isset($_FILES['imgSerUpdate']) && $_FILES['imgSerUpdate']['error'] === UPLOAD_ERR_OK) {
        try {
            $file = $_FILES['imgSerUpdate'];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            
            // Validar tipo
            if (!in_array($file['type'], $allowed)) {
                sendJSON(['success' => false, 'mensaje' => 'Formato de imagen no válido. Solo JPG, PNG o GIF']);
                exit;
            }
            
            // Validar tamaño
            if ($file['size'] > $maxSize) {
                sendJSON(['success' => false, 'mensaje' => 'La imagen no debe superar 2MB']);
                exit;
            }
            
            // Usar clase Agencia para obtener el folder
            $id_agencia = $_SESSION['id_agencia'] ?? 1;
            $folderInstitucion = (new Agencia($id_agencia))->institucion?->getFolderInstitucion();
            
            if ($folderInstitucion === null) {
                sendJSON(['success' => false, 'mensaje' => 'No se pudo obtener la carpeta de la institución']);
                exit;
            }
            
            // Construir ruta: imgcoope.microsystemplus.com/{folder}/services/
            $salida = "../../../";
            $entrada = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/services";
            $rutaEnServidor = $salida . $entrada;
            
            // Crear directorio si no existe
            if (!is_dir($rutaEnServidor)) {
                if (!mkdir($rutaEnServidor, 0777, true)) {
                    sendJSON(['success' => false, 'mensaje' => 'No se pudo crear el directorio']);
                    exit;
                }
            }
            
            // Generar nombre único
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $hash = substr(md5(uniqid() . time() . $file['name']), 0, 15);
            $filename = "service_" . time() . "_{$hash}.{$extension}";
            
            $ruta_completa = $rutaEnServidor . "/" . $filename;
            $ruta_relativa = $entrada . "/" . $filename;
            
            // Mover archivo nuevo
            if (!move_uploaded_file($file['tmp_name'], $ruta_completa)) {
                sendJSON(['success' => false, 'mensaje' => 'Error al guardar la nueva imagen']);
                exit;
            }
            
            // Verificar que se guardó
            if (!file_exists($ruta_completa)) {
                sendJSON(['success' => false, 'mensaje' => 'El archivo no se guardó correctamente']);
                exit;
            }
            
            // Eliminar imagen antigua si existe
            if (!empty($oldImageUrl)) {
                $oldPath = $salida . $oldImageUrl;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
            
            // Actualizar URL con ruta relativa
            $imageUrl = $ruta_relativa;
            
        } catch (Exception $e) {
            sendJSON(['success' => false, 'mensaje' => 'Error al procesar la imagen: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Actualizar en base de datos
    $sql = "UPDATE services_public 
            SET title = ?, body = ?, image = ?, updated_at = NOW() 
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'mensaje' => 'Error al preparar consulta: ' . mysqli_error($conexion)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "sssi", $titulo, $descripcion, $imageUrl, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        sendJSON([
            'success' => true, 
            'mensaje' => 'Servicio público actualizado exitosamente'
        ]);
    } else {
        sendJSON(['success' => false, 'mensaje' => 'Error al actualizar: ' . mysqli_stmt_error($stmt)]);
    }
    
    mysqli_stmt_close($stmt);
    ob_end_flush();
    exit;
}

// Eliminar Servicio Público
if (isset($_POST['action']) && $_POST['action'] == 'eliminarServicioPublico') {
    
    ob_start();
    
    // Configurar UTF-8
    mysqli_set_charset($conexion, 'utf8mb4');
    
    // Validar CSRF token
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    $inputs = $_POST['inputs'] ?? [];
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        sendJSON([
            'success' => false,
            'mensaje' => 'Token de seguridad inválido. Por favor, actualice la página e intente nuevamente.'
        ]);
        exit;
    }
    
    // Obtener ID
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        sendJSON(['success' => false, 'mensaje' => 'ID de servicio inválido']);
        exit;
    }
    
    // Obtener información del servicio para eliminar imagen
    $sqlSelect = "SELECT image FROM services_public WHERE id = ?";
    $stmtSelect = mysqli_prepare($conexion, $sqlSelect);
    mysqli_stmt_bind_param($stmtSelect, "i", $id);
    mysqli_stmt_execute($stmtSelect);
    $resultSelect = mysqli_stmt_get_result($stmtSelect);
    $serviceData = mysqli_fetch_assoc($resultSelect);
    mysqli_stmt_close($stmtSelect);
    
    if (!$serviceData) {
        sendJSON(['success' => false, 'mensaje' => 'Servicio no encontrado']);
        exit;
    }
    
    // Eliminar de base de datos
    $sqlDelete = "DELETE FROM services_public WHERE id = ?";
    $stmtDelete = mysqli_prepare($conexion, $sqlDelete);
    
    if (!$stmtDelete) {
        sendJSON(['success' => false, 'mensaje' => 'Error al preparar consulta: ' . mysqli_error($conexion)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmtDelete, "i", $id);
    
    if (mysqli_stmt_execute($stmtDelete)) {
        
        // Eliminar imagen del servidor si existe
        if (!empty($serviceData['image'])) {
            // La ruta en BD es relativa: imgcoope.microsystemplus.com/{folder}/services/{file}
            // Necesitamos 3 niveles arriba desde src/cruds/
            $salida = "../../../";
            $imagePath = $salida . $serviceData['image'];
            
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
        
        sendJSON([
            'success' => true, 
            'mensaje' => 'Servicio público eliminado exitosamente'
        ]);
    } else {
        sendJSON(['success' => false, 'mensaje' => 'Error al eliminar: ' . mysqli_stmt_error($stmtDelete)]);
    }
    
    mysqli_stmt_close($stmtDelete);
    ob_end_flush();
    exit;
}

// Listar Servicios Públicos (para recarga dinámica)
if (isset($_GET['action']) && $_GET['action'] == 'services_crud' && isset($_GET['subaction']) && $_GET['subaction'] == 'list') {
    
    ob_start();
    
    // Configurar UTF-8
    mysqli_set_charset($conexion, 'utf8mb4');
    
    $sql = "SELECT id, title, body, image, created_at, updated_at 
            FROM services_public 
            ORDER BY created_at DESC";
    
    $result = mysqli_query($conexion, $sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'mensaje' => 'Error al consultar servicios: ' . mysqli_error($conexion)]);
        exit;
    }
    
    // Usar FileProcessor para procesar imágenes (igual que clientes)
    $fileProcessor = new FileProcessor(__DIR__ . '/../../');
    
    $servicios = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Procesar imagen con FileProcessor si existe
        $imageData = [
            'exists' => false,
            'is_image' => false,
            'data_uri' => null,
            'path' => $row['image']
        ];
        
        if (!empty($row['image'])) {
            // Verificar si el archivo existe
            if ($fileProcessor->fileExists($row['image'])) {
                $imageData['exists'] = true;
                $imageData['is_image'] = $fileProcessor->isImage($row['image']);
                
                // Si es imagen, generar Data URI (base64)
                if ($imageData['is_image']) {
                    $imageData['data_uri'] = $fileProcessor->getDataUri($row['image']);
                }
            }
        }
        
        // Convertir a UTF-8
        $servicios[] = [
            'id' => $row['id'],
            'title' => mb_convert_encoding($row['title'], 'UTF-8', 'UTF-8'),
            'body' => mb_convert_encoding($row['body'], 'UTF-8', 'UTF-8'),
            'image' => $imageData, // Objeto con información completa de la imagen
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    mysqli_free_result($result);
    
    sendJSON(['success' => true, 'servicios' => $servicios]);
    
    ob_end_flush();
    exit;
}

// Limpiar buffer y enviar respuesta
error_log("=== FINAL CRUD_BANCA ===");
error_log("Action procesada: " . $action);
error_log("Status de respuesta: " . $response['status']);
error_log("Mensaje de respuesta: " . ($response['msg'] ?? 'N/A'));
error_log("Respuesta completa: " . print_r($response, true));

if ($response['status'] == 0) {
    error_log("⚠️ Ninguna acción coincidió o hubo un error");
    error_log("⚠️ Valores finales - Action: '" . $action . "', codcli: '" . $codcli . "', usuario: '" . $usuario . "', pass: " . (empty($pass) ? 'VACÍO' : '***'));
    
    // Diagnóstico adicional
    if ($action !== 'crear_credencial') {
        error_log("❌ PROBLEMA: La acción recibida ('" . $action . "') no coincide con 'crear_credencial'");
    }
    if (empty($codcli)) {
        error_log("❌ PROBLEMA: codcli está vacío");
    }
    if (empty($usuario)) {
        error_log("❌ PROBLEMA: usuario está vacío");
    }
    if (empty($pass)) {
        error_log("❌ PROBLEMA: pass está vacío");
    }
}

ob_end_flush();
sendJSON($response);
