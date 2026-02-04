<?php

use Micro\Helpers\Log;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);


include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];
switch ($condi) {

    case 'add_frecuencia':
        try {
            $required_fields = ['codigo_frecuencia', 'nombre', 'dias', 'pagos_mes'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("El campo '$field' es requerido");
                }
            }
            $codigo_frecuencia = strtoupper(trim($_POST['codigo_frecuencia']));
            $nombre = trim($_POST['nombre']);
            $dias = filter_var($_POST['dias'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 365]
            ]);
            $pagos_mes = filter_var($_POST['pagos_mes'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 31]
            ]);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $estado = in_array($_POST['estado'] ?? 'ACTIVO', ['ACTIVO', 'INACTIVO'])
                ? $_POST['estado']
                : 'ACTIVO';

            if ($dias === false) {
                throw new Exception("El número de días debe ser un valor entre 1 y 365");
            }

            if ($pagos_mes === false) {
                throw new Exception("El número de pagos por mes debe ser entre 1 y 31");
            }

            $check_sql = "SELECT id FROM tb_empl_frecuenciasdepago 
                     WHERE codigo_frecuencia = ? AND deleted_at IS NULL";
            $check_stmt = $conexion->prepare($check_sql);
            $check_stmt->bind_param("s", $codigo_frecuencia);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'El código de frecuencia ya existe',
                    'type' => 'warning'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $sql = "INSERT INTO tb_empl_frecuenciasdepago (
            codigo_frecuencia, 
            nombre, 
            descripcion, 
            dias, 
            pagos_mes, 
            estado,
            created_by,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }

            $stmt->bind_param(
                "sssiiss",
                $codigo_frecuencia,
                $nombre,
                $descripcion,
                $dias,
                $pagos_mes,
                $estado,
                $idusuario
            );

            if ($stmt->execute()) {
                $nuevo_id = $stmt->insert_id;
                $get_sql = "SELECT * FROM tb_empl_frecuenciasdepago WHERE id = ?";
                $get_stmt = $conexion->prepare($get_sql);
                $get_stmt->bind_param("i", $nuevo_id);
                $get_stmt->execute();
                $get_result = $get_stmt->get_result();
                $frecuencia_data = $get_result->fetch_assoc();

                echo json_encode([
                    'success' => true,
                    'message' => ' Frecuencia de pago creada exitosamente',
                    'type' => 'success',
                    'data' => $frecuencia_data
                ], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception("Error ejecutando consulta: " . $stmt->error);
            }
        } catch (Exception $e) {
            error_log("Error en add_frecuencia: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => ' Error: ' . $e->getMessage(),
                'type' => 'error'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'list_frecuencias':
        try {
            $sql = "SELECT 
            id,
            codigo_frecuencia,
            nombre,
            descripcion,
            dias,
            pagos_mes,
            estado,
            created_at,
            created_by
        FROM tb_empl_frecuenciasdepago 
        WHERE deleted_at IS NULL 
        ORDER BY codigo_frecuencia";

            $stmt = $conexion->prepare($sql);
            $stmt->execute();

            // CORRECCIÓN AQUÍ: Usar MySQLi en lugar de PDO
            $result = $stmt->get_result(); // Obtener resultado de MySQLi

            $frecuencias = [];
            while ($row = $result->fetch_assoc()) {
                $frecuencias[] = $row;
            }

            echo json_encode([
                'status' => 1,
                'data' => $frecuencias,
                'count' => count($frecuencias)
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error en list_frecuencias: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al listar frecuencias: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'delete_frecuencia':
        try {
            $id = intval($_POST['id']);

            // Verificar si la frecuencia está en uso
            $check_use_sql = "SELECT COUNT(*) as count FROM tb_emp_empleado WHERE id_frecuencia_pago = ? AND deleted_at IS NULL";
            $check_use_stmt = $conexion->prepare($check_use_sql);
            $check_use_stmt->execute([$id]);
            $use_count = $check_use_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($use_count > 0) {
                echo json_encode([
                    'status' => 0,
                    'message' => 'No se puede eliminar. Esta frecuencia está asignada a ' . $use_count . ' empleado(s)'
                ]);
                exit;
            }

            // Soft delete
            $sql = "UPDATE tb_empl_frecuenciasdepago 
                    SET deleted_at = NOW(), deleted_by = ? 
                    WHERE id = ? AND deleted_at IS NULL";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([$user_id, $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 1, 'message' => 'Frecuencia eliminada exitosamente']);
            } else {
                echo json_encode(['status' => 0, 'message' => 'Frecuencia no encontrada']);
            }
        } catch (PDOException $e) {
            error_log("Error en delete_frecuencia: " . $e->getMessage());
            echo json_encode(['status' => 0, 'message' => 'Error al eliminar frecuencia']);
        }
        break;

    case 'add_agencia':
        try {
            // Validar campos requeridos
            $required_fields = ['codigo_agencia', 'nombre_agencia', 'direccion'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("El campo '$field' es requerido");
                }
            }

            // Sanitizar y validar datos
            $codigo_agencia = strtoupper(trim($_POST['codigo_agencia']));
            $nombre_agencia = trim($_POST['nombre_agencia']);
            $direccion = trim($_POST['direccion']);
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $responsable = trim($_POST['responsable'] ?? '');
            $ciudad = trim($_POST['ciudad'] ?? '');
            $departamento = trim($_POST['departamento'] ?? '');
            $codigo_postal = trim($_POST['codigo_postal'] ?? '');
            $presupuesto_anual = floatval($_POST['presupuesto_anual'] ?? 0);
            $num_empleados = intval($_POST['num_empleados'] ?? 0);
            $estado = in_array($_POST['estado'] ?? 'ACTIVA', ['ACTIVA', 'INACTIVA', 'SUSPENDIDA'])
                ? $_POST['estado']
                : 'ACTIVA';

            // Validar formato del código
            if (!preg_match('/^[A-Z0-9_]+$/', $codigo_agencia)) {
                throw new Exception("El código debe contener solo mayúsculas, números y guiones bajos");
            }

            // Validar email si se proporciona
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El correo electrónico no es válido");
            }

            // Validar que el código no exista
            $check_sql = "SELECT id FROM tb_emp_agencias WHERE codigo_agencia = ? AND deleted_at IS NULL";
            $check_stmt = $conexion->prepare($check_sql);
            $check_stmt->bind_param("s", $codigo_agencia);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'El código de agencia ya existe',
                    'type' => 'warning'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Insertar nueva agencia
            $sql = "INSERT INTO tb_emp_agencias (
            codigo_agencia, 
            nombre, 
            direccion, 
            telefono, 
            email, 
            responsable,
            ciudad,
            departamento,
            codigo_postal,
            presupuesto_anual,
            num_empleados,
            estado,
            created_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }

            // Nota: La tabla usa 'nombre' no 'nombre_agencia'
            $stmt->bind_param(
                "sssssssssdiss",
                $codigo_agencia,
                $nombre_agencia,  // Se guarda en campo 'nombre'
                $direccion,
                $telefono,
                $email,
                $responsable,
                $ciudad,
                $departamento,
                $codigo_postal,
                $presupuesto_anual,
                $num_empleados,
                $estado,
                $idusuario
            );

            if ($stmt->execute()) {
                $nuevo_id = $stmt->insert_id;

                // Obtener los datos insertados para respuesta
                $get_sql = "SELECT * FROM tb_emp_agencias WHERE id = ?";
                $get_stmt = $conexion->prepare($get_sql);
                $get_stmt->bind_param("i", $nuevo_id);
                $get_stmt->execute();
                $get_result = $get_stmt->get_result();
                $agencia_data = $get_result->fetch_assoc();

                echo json_encode([
                    'success' => true,
                    'status' => 1,
                    'message' => 'Agencia creada exitosamente',
                    'type' => 'success',
                    'data' => $agencia_data
                ], JSON_UNESCAPED_UNICODE);
            } else {
                throw new Exception("Error ejecutando consulta: " . $stmt->error);
            }
        } catch (Exception $e) {
            error_log("Error en add_agencia: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'status' => 0,
                'message' => ' Error: ' . $e->getMessage(),
                'type' => 'error'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'list_agencias':
        try {
            $sql = "SELECT 
            id,
            codigo_agencia,
            nombre,
            direccion,
            telefono,
            email,
            responsable,
            ciudad,
            departamento,
            codigo_postal,
            presupuesto_anual,
            num_empleados,
            estado,
            created_at,
            created_by
        FROM tb_emp_agencias 
        WHERE deleted_at IS NULL 
        ORDER BY codigo_agencia";

            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();

            $agencias = [];
            while ($row = $result->fetch_assoc()) {
                $agencias[] = $row;
            }

            echo json_encode([
                'status' => 1,
                'data' => $agencias,
                'count' => count($agencias)
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error en list_agencias: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al listar agencias'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'delete_agencia':
        try {
            $id = intval($_POST['id']);

            // Verificar si la agencia tiene empleados asignados
            $check_use_sql = "SELECT COUNT(*) as count FROM tb_emp_empleado WHERE id_agencia = ? AND deleted_at IS NULL";
            $check_use_stmt = $conexion->prepare($check_use_sql);
            $check_use_stmt->bind_param("i", $id);
            $check_use_stmt->execute();
            $check_use_result = $check_use_stmt->get_result();
            $use_row = $check_use_result->fetch_assoc();
            $use_count = $use_row['count'];

            if ($use_count > 0) {
                echo json_encode([
                    'status' => 0,
                    'message' => 'No se puede eliminar. Esta agencia tiene ' . $use_count . ' empleado(s) asignado(s)'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Soft delete
            $sql = "UPDATE tb_emp_agencias 
                SET deleted_at = NOW(), deleted_by = ? 
                WHERE id = ? AND deleted_at IS NULL";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ii", $idusuario, $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'status' => 1,
                        'message' => 'Agencia eliminada exitosamente'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Agencia no encontrada'
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            error_log("Error en delete_agencia: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al eliminar agencia: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;


    case 'list_departamentos':
        try {
            $sql = "SELECT 
                    d.id,
                    d.codigo_departamento,
                    d.nombre,
                    d.descripcion,
                    d.id_agencia,
                    d.presupuesto_anual,
                    d.responsable_id,
                    d.num_empleados,
                    d.color,
                    d.icono,
                    d.estado,
                    d.created_at,
                    a.nombre as agencia_nombre  
                FROM tb_emp_departamentos d
                LEFT JOIN tb_emp_agencias a ON d.id_agencia = a.id AND a.deleted_at IS NULL
                WHERE d.deleted_at IS NULL 
                ORDER BY d.id";

            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }

            if (!$stmt->execute()) {
                throw new Exception("Error ejecutando consulta: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $departamentos = [];
            while ($row = $result->fetch_assoc()) {
                $departamentos[] = $row;
            }

            $result->free();

            echo json_encode([
                'status' => 1,
                'data' => $departamentos,
                'count' => count($departamentos),
                'message' => 'Departamentos cargados exitosamente'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error en list_departamentos [" . date('Y-m-d H:i:s') . "]: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al listar departamentos: ' . $e->getMessage(),
                'data' => [],
                'count' => 0
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'delete_departamento':
        try {
            $id = intval($_POST['id']);

            // Verificar si el departamento tiene sub-departamentos
            $check_sub_sql = "SELECT COUNT(*) as count FROM tb_emp_departamentos WHERE id_departamento_padre = ? AND deleted_at IS NULL";
            $check_sub_stmt = $conexion->prepare($check_sub_sql);
            $check_sub_stmt->bind_param("i", $id);
            $check_sub_stmt->execute();
            $check_sub_result = $check_sub_stmt->get_result();
            $sub_row = $check_sub_result->fetch_assoc();
            $sub_count = $sub_row['count'];

            if ($sub_count > 0) {
                echo json_encode([
                    'status' => 0,
                    'message' => 'No se puede eliminar. Este departamento tiene ' . $sub_count . ' sub-departamento(s) dependiente(s)'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Verificar si el departamento tiene empleados asignados
            $check_emp_sql = "SELECT COUNT(*) as count FROM tb_emp_empleado WHERE id_departamento = ? AND deleted_at IS NULL";
            $check_emp_stmt = $conexion->prepare($check_emp_sql);
            $check_emp_stmt->bind_param("i", $id);
            $check_emp_stmt->execute();
            $check_emp_result = $check_emp_stmt->get_result();
            $emp_row = $check_emp_result->fetch_assoc();
            $emp_count = $emp_row['count'];

            if ($emp_count > 0) {
                echo json_encode([
                    'status' => 0,
                    'message' => 'No se puede eliminar. Este departamento tiene ' . $emp_count . ' empleado(s) asignado(s)'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Soft delete
            $sql = "UPDATE tb_emp_departamentos 
                SET deleted_at = NOW(), deleted_by = ? 
                WHERE id = ? AND deleted_at IS NULL";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ii", $idusuario, $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'status' => 1,
                        'message' => 'Departamento eliminado exitosamente'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Departamento no encontrado'
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            error_log("Error en delete_departamento: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al eliminar departamento: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'add_departamento':
    try {
        // Validar campos requeridos
        $required_fields = ['codigo_departamento', 'nombre_departamento'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo '$field' es requerido");
            }
        }
        
        // Sanitizar y validar datos según tu tabla
        $codigo_departamento = strtoupper(trim($_POST['codigo_departamento']));
        $nombre_departamento = trim($_POST['nombre_departamento']);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $id_agencia = !empty($_POST['id_agencia']) ? intval($_POST['id_agencia']) : null;
        $presupuesto_anual = !empty($_POST['presupuesto_anual']) ? floatval($_POST['presupuesto_anual']) : null;
        $responsable_id = !empty($_POST['responsable_id']) ? intval($_POST['responsable_id']) : null;
        $num_empleados = !empty($_POST['num_empleados']) ? intval($_POST['num_empleados']) : 0;
        $color = trim($_POST['color'] ?? '#3B82F6');
        $icono = trim($_POST['icono'] ?? 'fas fa-building');
        $estado = in_array($_POST['estado'] ?? 'ACTIVO', ['ACTIVO', 'INACTIVO']) 
                ? $_POST['estado'] 
                : 'ACTIVO';
        
        // Validar formato del código
        if (!preg_match('/^[A-Z0-9_]+$/', $codigo_departamento)) {
            throw new Exception("El código debe contener solo mayúsculas, números y guiones bajos");
        }
        
        // Validar formato de color HEX
        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
            throw new Exception("El color debe estar en formato HEX (#RRGGBB)");
        }
        
        // Validar que el código no exista
        $check_sql = "SELECT id FROM tb_emp_departamentos WHERE codigo_departamento = ? AND deleted_at IS NULL";
        $check_stmt = $conexion->prepare($check_sql);
        $check_stmt->bind_param("s", $codigo_departamento);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'El código de departamento ya existe',
                'type' => 'warning'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Insertar nuevo departamento según tu estructura real
        $sql = "INSERT INTO tb_emp_departamentos (
            codigo_departamento, 
            nombre, 
            descripcion, 
            id_agencia,
            presupuesto_anual,
            responsable_id,
            num_empleados,
            color,
            icono,
            estado,
            created_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conexion->error);
        }
        
        // bind_param con la estructura correcta
        $stmt->bind_param(
            "sssidiisssi", 
            $codigo_departamento,
            $nombre_departamento,
            $descripcion,
            $id_agencia,
            $presupuesto_anual,
            $responsable_id,
            $num_empleados,
            $color,
            $icono,
            $estado,
            $idusuario
        );
        
        if ($stmt->execute()) {
            $nuevo_id = $stmt->insert_id;
            
            // Obtener los datos insertados para respuesta
            $get_sql = "SELECT * FROM tb_emp_departamentos WHERE id = ?";
            
            $get_stmt = $conexion->prepare($get_sql);
            $get_stmt->bind_param("i", $nuevo_id);
            $get_stmt->execute();
            $get_result = $get_stmt->get_result();
            $departamento_data = $get_result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'status' => 1,
                'message' => '✅ Departamento creado exitosamente',
                'type' => 'success',
                'data' => $departamento_data
            ], JSON_UNESCAPED_UNICODE);
            
        } else {
            throw new Exception("Error ejecutando consulta: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Error en add_departamento: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'status' => 0,
            'message' => '❌ Error: ' . $e->getMessage(),
            'type' => 'error'
        ], JSON_UNESCAPED_UNICODE);
    }
    break;

    case 'list_departamentos':
        try {
            $sql = "SELECT d.*, 
                       a.nombre as agencia_nombre,
                       a.codigo_agencia
                FROM tb_emp_departamentos d
                LEFT JOIN tb_emp_agencias a ON d.id_agencia = a.id
                WHERE d.deleted_at IS NULL 
                ORDER BY d.codigo_departamento";

            $stmt = $conexion->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();

            $departamentos = [];
            while ($row = $result->fetch_assoc()) {
                $departamentos[] = $row;
            }

            echo json_encode([
                'status' => 1,
                'data' => $departamentos,
                'count' => count($departamentos)
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("Error en list_departamentos: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al listar departamentos'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'delete_departamento':
        try {
            $id = intval($_POST['id']);

            // Verificar si el departamento tiene empleados asignados
            $check_emp_sql = "SELECT COUNT(*) as count FROM tb_emp_empleado WHERE id_departamento = ? AND deleted_at IS NULL";
            $check_emp_stmt = $conexion->prepare($check_emp_sql);
            $check_emp_stmt->bind_param("i", $id);
            $check_emp_stmt->execute();
            $check_emp_result = $check_emp_stmt->get_result();
            $emp_row = $check_emp_result->fetch_assoc();
            $emp_count = $emp_row['count'];

            if ($emp_count > 0) {
                echo json_encode([
                    'status' => 0,
                    'message' => 'No se puede eliminar. Este departamento tiene ' . $emp_count . ' empleado(s) asignado(s)'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Soft delete
            $sql = "UPDATE tb_emp_departamentos 
                SET deleted_at = NOW(), deleted_by = ? 
                WHERE id = ? AND deleted_at IS NULL";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ii", $idusuario, $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'status' => 1,
                        'message' => 'Departamento eliminado exitosamente'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Departamento no encontrado'
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            error_log("Error en delete_departamento: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al eliminar departamento: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
    case 'get_agencias_activas':
    // DEBUG: Verificar que llegue la petición
    error_log("DEBUG: get_agencias_activas llamado desde: " . $_SERVER['HTTP_REFERER']);
    
    try {
        $sql = "SELECT id, codigo_agencia, nombre 
                FROM tb_emp_agencias 
                WHERE estado = 'ACTIVA' AND deleted_at IS NULL 
                ORDER BY nombre";

        error_log("DEBUG: SQL ejecutado: " . $sql); // Verificar SQL
        
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conexion->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        error_log("DEBUG: Número de filas: " . $result->num_rows); // Verificar resultados

        $agencias = [];
        while ($row = $result->fetch_assoc()) {
            $agencias[] = $row;
        }

        error_log("DEBUG: Agencias encontradas: " . json_encode($agencias)); // Ver datos

        echo json_encode([
            'status' => 1,
            'data' => $agencias,
            'count' => count($agencias),
            'message' => 'Agencias cargadas exitosamente'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("Error en get_agencias_activas: " . $e->getMessage());
        echo json_encode([
            'status' => 0,
            'message' => 'Error al obtener agencias: ' . $e->getMessage(),
            'data' => [],
            'count' => 0
        ], JSON_UNESCAPED_UNICODE);
    }
    break;

  
        case 'get_departamento_by_id':
    try {
        if (!$conexion) {
            throw new Exception("No hay conexión a la base de datos");
        }
        
        $id = intval($_POST['id']);
        
        if ($id <= 0) {
            throw new Exception("ID de departamento inválido");
        }
        
        // CONSULTA CON LA ESTRUCTURA REAL DE TU TABLA
        $sql = "SELECT 
                    d.id,
                    d.codigo_departamento,
                    d.nombre,
                    d.descripcion,
                    d.id_agencia,
                    d.presupuesto_anual,
                    d.responsable_id,
                    d.num_empleados,
                    d.color,
                    d.icono,
                    d.estado,
                    d.created_at,
                    a.nombre as agencia_nombre  
                FROM tb_emp_departamentos d
                LEFT JOIN tb_emp_agencias a ON d.id_agencia = a.id AND a.deleted_at IS NULL
                WHERE d.deleted_at IS NULL 
                ORDER BY d.id";
        
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conexion->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error ejecutando consulta: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $departamento = $result->fetch_assoc();
            $result->free();
            
            echo json_encode([
                'status' => 1, 
                'data' => $departamento,
                'message' => 'Departamento encontrado'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $result->free();
            echo json_encode([
                'status' => 0, 
                'message' => 'Departamento no encontrado',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        error_log("Error en get_departamento_by_id: " . $e->getMessage());
        echo json_encode([
            'status' => 0, 
            'message' => 'Error al obtener departamento: ' . $e->getMessage(),
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
    break;

   case 'update_departamento':
    try {
        if (!$conexion) {
            throw new Exception("No hay conexión a la base de datos");
        }
        
        // Validar campos requeridos según tu tabla
        $required_fields = ['id', 'codigo_departamento', 'nombre_departamento'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo '$field' es requerido");
            }
        }
        
        $id = intval($_POST['id']);
        $codigo_departamento = strtoupper(trim($_POST['codigo_departamento']));
        $nombre_departamento = trim($_POST['nombre_departamento']);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $id_agencia = !empty($_POST['id_agencia']) ? intval($_POST['id_agencia']) : null;
        $presupuesto_anual = !empty($_POST['presupuesto_anual']) ? floatval($_POST['presupuesto_anual']) : null;
        $responsable_id = !empty($_POST['responsable_id']) ? intval($_POST['responsable_id']) : null;
        $num_empleados = !empty($_POST['num_empleados']) ? intval($_POST['num_empleados']) : 0;
        $color = trim($_POST['color'] ?? '#3B82F6');
        $icono = trim($_POST['icono'] ?? 'fas fa-building');
        $estado = in_array($_POST['estado'] ?? 'ACTIVO', ['ACTIVO', 'INACTIVO']) 
                ? $_POST['estado'] 
                : 'ACTIVO';
        
        // Validar formato del código
        if (!preg_match('/^[A-Z0-9_]+$/', $codigo_departamento)) {
            throw new Exception("El código debe contener solo mayúsculas, números y guiones bajos");
        }
        
        // Validar formato de color HEX
        if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
            throw new Exception("El color debe estar en formato HEX (#RRGGBB)");
        }
        
        // Verificar que el departamento exista
        $check_sql = "SELECT id FROM tb_emp_departamentos WHERE id = ? AND deleted_at IS NULL";
        $check_stmt = $conexion->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("Departamento no encontrado");
        }
        
        // Verificar que el nuevo código no exista en otro departamento
        $check_codigo_sql = "SELECT id FROM tb_emp_departamentos WHERE codigo_departamento = ? AND id != ? AND deleted_at IS NULL";
        $check_codigo_stmt = $conexion->prepare($check_codigo_sql);
        $check_codigo_stmt->bind_param("si", $codigo_departamento, $id);
        $check_codigo_stmt->execute();
        $check_codigo_result = $check_codigo_stmt->get_result();
        
        if ($check_codigo_result->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'status' => 0,
                'message' => 'El código de departamento ya existe en otro registro',
                'type' => 'warning'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // SQL ACTUALIZADO con la estructura real
        $sql = "UPDATE tb_emp_departamentos SET
                codigo_departamento = ?,
                nombre = ?,
                descripcion = ?,
                id_agencia = ?,
                presupuesto_anual = ?,
                responsable_id = ?,
                num_empleados = ?,
                color = ?,
                icono = ?,
                estado = ?,
                updated_by = ?,
                updated_at = NOW()
                WHERE id = ? AND deleted_at IS NULL";
        
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conexion->error);
        }
        
        // bind_param con los tipos correctos
        $stmt->bind_param(
            "sssidiissiii", 
            $codigo_departamento,
            $nombre_departamento,
            $descripcion,
            $id_agencia,
            $presupuesto_anual,
            $responsable_id,
            $num_empleados,
            $color,
            $icono,
            $estado,
            $idusuario,
            $id
        );
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Obtener datos actualizados
                $get_sql = "SELECT d.* 
                    FROM tb_emp_departamentos d
                    WHERE d.id = ?";
                
                $get_stmt = $conexion->prepare($get_sql);
                $get_stmt->bind_param("i", $id);
                $get_stmt->execute();
                $get_result = $get_stmt->get_result();
                $departamento_data = $get_result->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'status' => 1,
                    'message' => '✅ Departamento actualizado exitosamente',
                    'type' => 'success',
                    'data' => $departamento_data
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'status' => 0,
                    'message' => 'No se realizaron cambios en el departamento',
                    'type' => 'info'
                ], JSON_UNESCAPED_UNICODE);
            }
        } else {
            throw new Exception("Error ejecutando consulta: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        error_log("Error en update_departamento: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'status' => 0,
            'message' => '❌ Error: ' . $e->getMessage(),
            'type' => 'error'
        ], JSON_UNESCAPED_UNICODE);
    }
    break;

    case 'get_agencias_activas':
    try {
        // Verificar conexión
        if (!$conexion) {
            throw new Exception("No hay conexión a la base de datos");
        }
        
        $sql = "SELECT id, codigo_agencia, nombre 
                FROM tb_emp_agencias 
                WHERE estado = 'ACTIVA' AND deleted_at IS NULL 
                ORDER BY nombre";
        
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conexion->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error ejecutando consulta: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $agencias = [];
        while ($row = $result->fetch_assoc()) {
            $agencias[] = $row;
        }
        
        $result->free();
        
        echo json_encode([
            'status' => 1, 
            'data' => $agencias,
            'count' => count($agencias),
            'message' => 'Agencias cargadas exitosamente'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("Error en get_agencias_activas: " . $e->getMessage());
        echo json_encode([
            'status' => 0, 
            'message' => 'Error al obtener agencias: ' . $e->getMessage(),
            'data' => [],
            'count' => 0
        ], JSON_UNESCAPED_UNICODE);
    }
    break;

    case 'get_agencia_by_id':
    try {
        $id = intval($_POST['id']);
        $sql = "SELECT id, codigo_agencia, nombre FROM tb_emp_agencias WHERE id = ? AND deleted_at IS NULL";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 1, 'data' => $result->fetch_assoc()]);
        } else {
            echo json_encode(['status' => 0, 'message' => 'Agencia no encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 0, 'message' => $e->getMessage()]);
    }
    break;

//HERE CRUD EMPLOYEED

case 'create_empleado':
    try {
        // ══════════════════════════════════════════════════════════
        // DEBUG TEMPORAL - ELIMINAR DESPUÉS DE RESOLVER
        // ══════════════════════════════════════════════════════════
        error_log("═══════════════════════════════════════════════════════");
        error_log("DEBUG POST en create_empleado:");
        error_log("POST completo: " . print_r($_POST, true));
        error_log("clienteSelect existe? " . (isset($_POST['clienteSelect']) ? 'SÍ' : 'NO'));
        error_log("clienteSelect valor RAW: '" . ($_POST['clienteSelect'] ?? 'NO EXISTE') . "'");
        error_log("clienteSelect tipo: " . gettype($_POST['clienteSelect'] ?? null));
        
        // Ver qué pasa con el trim
        $test_cliente = trim($_POST['clienteSelect'] ?? '');
        error_log("Después de trim: '$test_cliente'");
        error_log("Length después trim: " . strlen($test_cliente));
        error_log("empty()? " . (empty($test_cliente) ? 'SÍ (VACÍO)' : 'NO (TIENE VALOR)'));
        error_log("═══════════════════════════════════════════════════════");
        // ══════════════════════════════════════════════════════════
        
        // CÓDIGO ORIGINAL CONTINÚA AQUÍ...
        error_log("POST recibido en create_empleado: " . print_r($_POST, true));
        
        if (empty($_POST)) {
            echo json_encode([
                'status' => 0,
                'message' => 'No se recibió ningún dato POST',
                'post_raw' => file_get_contents('php://input')
            ]);
            exit;
        }


        // ────────────────────────────────────────────────
        // 1. Recolectar y sanitizar TODOS los datos
        // ────────────────────────────────────────────────
        $id_cliente           = trim($_POST['clienteSelect'] ?? '');
        $codigo_empleado      = generarCodigoEmpleado($conexion);
        $id_agencia           = !empty($_POST['agencia']) ? intval($_POST['agencia']) : null;
        $id_departamento      = !empty($_POST['departamento']) ? intval($_POST['departamento']) : null;
        $puesto               = trim($_POST['puesto'] ?? '');
        $nivel                = trim($_POST['nivel'] ?? 'OPERATIVO');
        $tipo_contrato        = trim($_POST['tipo_contrato'] ?? 'INDEFINIDO');
        $fecha_ingreso        = $_POST['fecha_ingreso'] ?? $hoy;
        $fecha_contrato       = $_POST['fecha_contrato'] ?? null;
        $fecha_fin_contrato   = $_POST['fecha_fin_contrato'] ?? null;
        $id_frecuencia_pago   = !empty($_POST['frecuencia_pago']) ? intval($_POST['frecuencia_pago']) : null;
        $sueldo_base_mensual  = floatval($_POST['sueldo_base'] ?? 0);
        $sueldo_base_diario   = $sueldo_base_mensual / 30;
        
        // ✅ CAMBIO: tipo_moneda → moneda
        $moneda               = trim($_POST['tipo_moneda'] ?? 'GTQ');
        
        $tipo_salario         = trim($_POST['tipo_salario'] ?? 'FIJO');
        $porcentaje_comision  = floatval($_POST['porcentaje_comision'] ?? 0);
        
        // ✅ CAMBIO: Manejar numero_igss correctamente (puede ser NULL)
        $numero_igss          = !empty($_POST['numero_igss']) ? trim($_POST['numero_igss']) : null;
        
        // Beneficios (checkboxes → 1 o 0)
        $tiene_bono14         = isset($_POST['tiene_bono14']) ? 1 : 0;
        $tiene_aguinaldo      = isset($_POST['tiene_aguinaldo']) ? 1 : 0;
        $tiene_indemnizacion  = isset($_POST['tiene_indemnizacion']) ? 1 : 0;
        $tiene_prestaciones   = isset($_POST['tiene_prestaciones']) ? 1 : 0;
        $tiene_igss           = isset($_POST['tiene_igss']) ? 1 : 0;
        $tiene_irtra          = isset($_POST['tiene_irtra']) ? 1 : 0;
        $tiene_intecap        = isset($_POST['tiene_intecap']) ? 1 : 0;
        $tiene_vacaciones     = isset($_POST['tiene_vacaciones']) ? 1 : 0;
        $dias_vacaciones      = intval($_POST['dias_vacaciones'] ?? 15);
        
        // Beneficios empresa
        $tiene_seguro_medico      = isset($_POST['tiene_seguro_medico']) ? 1 : 0;
        $tiene_plan_pensiones     = isset($_POST['tiene_plan_pensiones']) ? 1 : 0;
        $tiene_bonos_productividad= isset($_POST['tiene_bonos_productividad']) ? 1 : 0;
        $tiene_capacitaciones     = isset($_POST['tiene_capacitaciones']) ? 1 : 0;

        // Campos opcionales / por defecto
        $observaciones        = trim($_POST['observaciones'] ?? '');
        $estado               = 'ACTIVO';

        // ────────────────────────────────────────────────
        // 2. Validaciones mínimas obligatorias
        // ────────────────────────────────────────────────
        if (empty($id_cliente)) throw new Exception("Debe seleccionar un cliente");
        if (empty($puesto))     throw new Exception("El puesto es obligatorio");
        if ($sueldo_base_mensual <= 0) throw new Exception("El sueldo base debe ser mayor a 0");

        // ────────────────────────────────────────────────
        // 3. Preparar INSERT (ajusta los campos según tu tabla real)
        // ────────────────────────────────────────────────
        // ✅ CAMBIO: tipo_moneda → moneda en el SQL
        $sql = "INSERT INTO tb_emp_empleado (
            id_cliente, codigo_empleado, id_agencia, id_departamento,
            puesto, nivel, tipo_contrato, fecha_ingreso, fecha_contrato, fecha_fin_contrato,
            id_frecuencia_pago, sueldo_base_mensual, sueldo_base_diario, moneda,
            tipo_salario, porcentaje_comision, numero_igss,
            tiene_bono14, tiene_aguinaldo, tiene_indemnizacion, tiene_prestaciones,
            tiene_igss, tiene_irtra, tiene_intecap, tiene_vacaciones, dias_vacaciones,
            tiene_seguro_medico, tiene_plan_pensiones, tiene_bonos_productividad,
            tiene_capacitaciones, observaciones, estado,
            created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conexion->error);
        }

        // ────────────────────────────────────────────────
        // 4. Construir tipos y parámetros dinámicamente
        // ────────────────────────────────────────────────
        $params = [];
        $types  = '';

        $params[] = $id_cliente;           $types .= 's';
        $params[] = $codigo_empleado;      $types .= 's';
        $params[] = $id_agencia;           $types .= 'i';
        $params[] = $id_departamento;      $types .= 'i';
        $params[] = $puesto;               $types .= 's';
        $params[] = $nivel;                $types .= 's';
        $params[] = $tipo_contrato;        $types .= 's';
        $params[] = $fecha_ingreso;        $types .= 's';
        $params[] = $fecha_contrato;       $types .= 's';
        $params[] = $fecha_fin_contrato;   $types .= 's';
        $params[] = $id_frecuencia_pago;   $types .= 'i';
        $params[] = $sueldo_base_mensual;  $types .= 'd';
        $params[] = $sueldo_base_diario;   $types .= 'd';
        
        //$tipo_moneda → $moneda
        $params[] = $moneda;               $types .= 's';
        
        $params[] = $tipo_salario;         $types .= 's';
        $params[] = $porcentaje_comision;  $types .= 'd';
        $params[] = $numero_igss;          $types .= 's';
        $params[] = $tiene_bono14;         $types .= 'i';
        $params[] = $tiene_aguinaldo;      $types .= 'i';
        $params[] = $tiene_indemnizacion;  $types .= 'i';
        $params[] = $tiene_prestaciones;   $types .= 'i';
        $params[] = $tiene_igss;           $types .= 'i';
        $params[] = $tiene_irtra;          $types .= 'i';
        $params[] = $tiene_intecap;        $types .= 'i';
        $params[] = $tiene_vacaciones;     $types .= 'i';
        $params[] = $dias_vacaciones;      $types .= 'i';
        $params[] = $tiene_seguro_medico;       $types .= 'i';
        $params[] = $tiene_plan_pensiones;      $types .= 'i';
        $params[] = $tiene_bonos_productividad; $types .= 'i';
        $params[] = $tiene_capacitaciones;      $types .= 'i';
        $params[] = $observaciones;        $types .= 's';
        $params[] = $estado;               $types .= 's';
        $params[] = $idusuario;            $types .= 'i';

        // Ahora sí: bind_param dinámico
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $nuevo_id = $stmt->insert_id;
            
            echo json_encode([
                'status'  => 1,
                'message' => 'Empleado creado exitosamente',
                'id'      => $nuevo_id,
                'codigo'  => $codigo_empleado
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception("Error al guardar: " . $stmt->error);
        }

    } catch (Exception $e) {
        error_log("Error en create_empleado: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'status'  => 0,
            'message' => 'Error al crear empleado: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    break;
        
    // ============================================================
    // OBTENER EMPLEADO POR ID (PARA EDITAR O VER DETALLE)
    // ============================================================
    case 'get_empleado_by_id':
        try {
            $id = intval($_POST['id']);
            
            if ($id <= 0) {
                throw new Exception("ID de empleado inválido");
            }
            
            $sql = "SELECT 
                    e.*,
                    d.nombre as departamento_nombre,
                    a.nombre as agencia_nombre,
                    f.nombre as frecuencia_nombre,
                    c.primer_name, c.segundo_name, c.primer_last, c.segundo_last,
                    CONCAT(c.primer_name, ' ', COALESCE(c.segundo_name, ''), ' ', 
                           c.primer_last, ' ', COALESCE(c.segundo_last, '')) as nombre_completo_cliente,
                    uc.username as creador_username,
                    uu.username as modificador_username
                    FROM tb_emp_empleado e
                    INNER JOIN tb_cliente c ON e.id_cliente = c.idcod_cliente
                    LEFT JOIN tb_emp_departamentos d ON e.id_departamento = d.id
                    LEFT JOIN tb_emp_agencias a ON e.id_agencia = a.id
                    LEFT JOIN tb_empl_frecuenciasdepago f ON e.id_frecuencia_pago = f.id
                    LEFT JOIN users uc ON e.created_by = uc.id
                    LEFT JOIN users uu ON e.updated_by = uu.id
                    WHERE e.id = ? AND e.deleted_at IS NULL";
            
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }
            
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $empleado = $result->fetch_assoc();
                
                // Formatear datos para respuesta
                $empleado['fecha_ingreso_formatted'] = $empleado['fecha_ingreso'] ? 
                    date('d/m/Y', strtotime($empleado['fecha_ingreso'])) : '';
                $empleado['fecha_contrato_formatted'] = $empleado['fecha_contrato'] ? 
                    date('d/m/Y', strtotime($empleado['fecha_contrato'])) : '';
                $empleado['fecha_fin_contrato_formatted'] = $empleado['fecha_fin_contrato'] ? 
                    date('d/m/Y', strtotime($empleado['fecha_fin_contrato'])) : '';
                $empleado['created_at_formatted'] = $empleado['created_at'] ? 
                    date('d/m/Y H:i:s', strtotime($empleado['created_at'])) : '';
                $empleado['updated_at_formatted'] = $empleado['updated_at'] ? 
                    date('d/m/Y H:i:s', strtotime($empleado['updated_at'])) : '';
                
                echo json_encode([
                    'status' => 1,
                    'data' => $empleado,
                    'message' => 'Empleado encontrado'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'status' => 0,
                    'message' => 'Empleado no encontrado'
                ], JSON_UNESCAPED_UNICODE);
            }
            
        } catch (Exception $e) {
            error_log("Error en get_empleado_by_id: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al obtener empleado: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    // ============================================================
    // ACTUALIZAR EMPLEADO
    // ============================================================
    case 'update_empleado':
        try {
            error_log("=== ACTUALIZANDO EMPLEADO ===");
            
            $id = intval($_POST['id']);
            
            if ($id <= 0) {
                throw new Exception("ID de empleado inválido");
            }
            
            // Verificar que el empleado existe
            $check_sql = "SELECT id FROM tb_emp_empleado WHERE id = ? AND deleted_at IS NULL";
            $check_stmt = $conexion->prepare($check_sql);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                throw new Exception("Empleado no encontrado");
            }
            
            // Obtener campos a actualizar
            $updates = [];
            $params = [];
            $types = "";
            
            // Campos básicos
            $update_fields = [
                'puesto', 'nivel', 'tipo_contrato', 'fecha_ingreso', 
                'fecha_contrato', 'fecha_fin_contrato', 'observaciones'
            ];
            
            foreach ($update_fields as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $_POST[$field];
                    $types .= "s";
                }
            }
            
            // Campos numéricos
            if (isset($_POST['id_departamento'])) {
                $updates[] = "id_departamento = ?";
                $params[] = intval($_POST['id_departamento']);
                $types .= "i";
            }
            
            if (isset($_POST['id_agencia'])) {
                $updates[] = "id_agencia = ?";
                $params[] = intval($_POST['id_agencia']);
                $types .= "i";
            }
            
            if (isset($_POST['id_frecuencia_pago'])) {
                $updates[] = "id_frecuencia_pago = ?";
                $params[] = intval($_POST['id_frecuencia_pago']);
                $types .= "i";
            }
            
            // Sueldo (requiere historial)
            if (isset($_POST['sueldo_base'])) {
                $nuevo_sueldo = floatval($_POST['sueldo_base']);
                
                // Obtener sueldo actual
                $get_sueldo_sql = "SELECT sueldo_base_mensual FROM tb_emp_empleado WHERE id = ?";
                $get_sueldo_stmt = $conexion->prepare($get_sueldo_sql);
                $get_sueldo_stmt->bind_param("i", $id);
                $get_sueldo_stmt->execute();
                $get_sueldo_result = $get_sueldo_stmt->get_result();
                $empleado_actual = $get_sueldo_result->fetch_assoc();
                $sueldo_anterior = $empleado_actual['sueldo_base_mensual'];
                
                $updates[] = "sueldo_base_mensual = ?";
                $updates[] = "sueldo_base_diario = ?";
                $params[] = $nuevo_sueldo;
                $params[] = $nuevo_sueldo / 30;
                $types .= "dd";
                
                // Registrar en historial si cambió
                if ($nuevo_sueldo != $sueldo_anterior) {
                    $historial_sql = "INSERT INTO tb_emp_historial_sueldos (
                        id_empleado, sueldo_anterior, sueldo_nuevo, fecha_cambio,
                        motivo, observaciones, created_by, created_at
                    ) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, NOW())";
                    
                    $motivo = $_POST['motivo_cambio'] ?? 'Ajuste de sueldo';
                    $obs = $_POST['observaciones_cambio'] ?? null;
                    
                    $historial_stmt = $conexion->prepare($historial_sql);
                    $historial_stmt->bind_param("iddssi", $id, $sueldo_anterior, $nuevo_sueldo, $motivo, $obs, $idusuario);
                    $historial_stmt->execute();
                }
            }
            
            // Beneficios (convertir checkbox)
            $beneficios_fields = [
                'tiene_bono14', 'tiene_aguinaldo', 'tiene_indemnizacion', 'tiene_prestaciones',
                'tiene_igss', 'tiene_irtra', 'tiene_intecap', 'tiene_vacaciones',
                'tiene_seguro_medico', 'tiene_plan_pensiones', 'tiene_bonos_productividad',
                'tiene_capacitaciones', 'tiene_vale_despensa', 'tiene_otros_beneficios'
            ];
            
            foreach ($beneficios_fields as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = ($_POST[$field] == '1' || $_POST[$field] === true) ? 1 : 0;
                    $types .= "i";
                }
            }
            
            // Campos específicos
            if (isset($_POST['numero_igss'])) {
                $updates[] = "numero_igss = ?";
                $params[] = trim($_POST['numero_igss']);
                $types .= "s";
            }
            
            if (isset($_POST['dias_vacaciones'])) {
                $updates[] = "dias_vacaciones = ?";
                $params[] = intval($_POST['dias_vacaciones']);
                $types .= "i";
            }
            
            if (isset($_POST['monto_vale_despensa'])) {
                $updates[] = "monto_vale_despensa = ?";
                $params[] = floatval($_POST['monto_vale_despensa']);
                $types .= "d";
            }
            
            if (isset($_POST['porcentaje_comision'])) {
                $updates[] = "porcentaje_comision = ?";
                $params[] = floatval($_POST['porcentaje_comision']);
                $types .= "d";
            }
            
            // Información bancaria
            $bancarios_fields = ['cuenta_bancaria', 'banco', 'tipo_cuenta'];
            foreach ($bancarios_fields as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = trim($_POST[$field]);
                    $types .= "s";
                }
            }
            
            // Horario
            $horario_fields = ['horario_entrada', 'horario_salida', 'turno', 'dias_trabajo'];
            foreach ($horario_fields as $field) {
                if (isset($_POST[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = trim($_POST[$field]);
                    $types .= "s";
                }
            }
            
            // Campos de auditoría
            $updates[] = "updated_by = ?";
            $updates[] = "updated_at = NOW()";
            $params[] = $idusuario;
            $types .= "i";
            
            // Agregar ID al final para WHERE
            $params[] = $id;
            $types .= "i";
            
            if (empty($updates)) {
                throw new Exception("No se proporcionaron datos para actualizar");
            }
            
            // Construir SQL dinámico
            $sql = "UPDATE tb_emp_empleado SET " . implode(", ", $updates) . " WHERE id = ? AND deleted_at IS NULL";
            
            error_log("SQL Update: " . $sql);
            error_log("Parámetros: " . print_r($params, true));
            
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }
            
            // Bind dinámico de parámetros
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'status' => 1,
                        'message' => 'Empleado actualizado exitosamente'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'No se realizaron cambios en el empleado'
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                throw new Exception("Error ejecutando consulta: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            error_log("Error en update_empleado: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al actualizar empleado: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    // ============================================================
    // ELIMINAR/DESACTIVAR EMPLEADO (SOFT DELETE)
    // ============================================================
    case 'delete_empleado':
        try {
            $id = intval($_POST['id']);
            $motivo = !empty($_POST['motivo']) ? trim($_POST['motivo']) : 'Desactivado por usuario';
            
            if ($id <= 0) {
                throw new Exception("ID de empleado inválido");
            }
            
            $sql = "UPDATE tb_emp_empleado SET 
                    estado = 'INACTIVO',
                    motivo_inactividad = ?,
                    updated_by = ?,
                    updated_at = NOW()
                    WHERE id = ? AND deleted_at IS NULL";
            
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }
            
            $stmt->bind_param("sii", $motivo, $idusuario, $id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        'status' => 1,
                        'message' => 'Empleado desactivado exitosamente'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Empleado no encontrado'
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                throw new Exception("Error ejecutando consulta: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            error_log("Error en delete_empleado: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al desactivar empleado: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    // ============================================================
    // OBTENER TABLA DE EMPLEADOS
    // ============================================================
   case 'table_empleados':
    try {
        // CORRECCIÓN: Agregar COLLATE para forzar la misma collation
        $sql = "SELECT 
                e.id,
                e.codigo_empleado,
                e.id_cliente,
                CONCAT(
                    COALESCE(c.primer_name, ''), ' ',
                    COALESCE(c.segundo_name, ''), ' ',
                    COALESCE(c.primer_last, ''), ' ',
                    COALESCE(c.segundo_last, '')
                ) as nombre_completo,
                c.no_identifica as dpi,
                e.puesto,
                d.nombre as departamento,
                a.nombre as agencia,
                e.sueldo_base_mensual as sueldo_base,
                f.nombre as frecuencia_pago,
                e.fecha_ingreso,
                e.estado,
                e.created_at,
                e.updated_at,
                uc.username as creador,
                uu.username as modificador
                FROM tb_emp_empleado e
                INNER JOIN tb_cliente c ON e.id_cliente = c.idcod_cliente COLLATE utf8mb4_unicode_ci
                LEFT JOIN tb_emp_departamentos d ON e.id_departamento = d.id
                LEFT JOIN tb_emp_agencias a ON e.id_agencia = a.id
                LEFT JOIN tb_empl_frecuenciasdepago f ON e.id_frecuencia_pago = f.id
                LEFT JOIN users uc ON e.created_by = uc.id
                LEFT JOIN users uu ON e.updated_by = uu.id
                WHERE e.deleted_at IS NULL 
                ORDER BY e.fecha_ingreso DESC";
        
        error_log("SQL table_empleados: " . $sql);
        
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conexion->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $empleados = [];
        while ($row = $result->fetch_assoc()) {
            $empleados[] = $row;
        }
        
        error_log("Empleados encontrados: " . count($empleados));
        
        echo json_encode([
            'status' => 1,
            'data' => $empleados,
            'count' => count($empleados),
            'message' => 'Datos cargados correctamente'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("Error en table_empleados: " . $e->getMessage());
        echo json_encode([
            'status' => 0,
            'message' => 'Error al listar empleados: ' . $e->getMessage(),
            'data' => [],
            'count' => 0
        ], JSON_UNESCAPED_UNICODE);
    }
    break;
        
    // ============================================================
    // OBTENER AGENCIAS ACTIVAS
    // ============================================================
    case 'get_agencias_activas':
        try {
            $sql = "SELECT id, codigo_agencia, nombre 
                    FROM tb_emp_agencias 
                    WHERE estado = 'ACTIVA' AND deleted_at IS NULL 
                    ORDER BY nombre";
            
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $agencias = [];
            while ($row = $result->fetch_assoc()) {
                $agencias[] = $row;
            }
            
            echo json_encode([
                'status' => 1,
                'data' => $agencias,
                'count' => count($agencias),
                'message' => 'Agencias cargadas exitosamente'
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            error_log("Error en get_agencias_activas: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al obtener agencias: ' . $e->getMessage(),
                'data' => [],
                'count' => 0
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    // ============================================================
    // OBTENER DEPARTAMENTOS ACTIVOS
    // ============================================================
    case 'get_departamentos_activos':
        try {
            $sql = "SELECT id, codigo_departamento, nombre 
                    FROM tb_emp_departamentos 
                    WHERE estado = 'ACTIVO' AND deleted_at IS NULL 
                    ORDER BY nombre";
            
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $departamentos = [];
            while ($row = $result->fetch_assoc()) {
                $departamentos[] = $row;
            }
            
            echo json_encode([
                'status' => 1,
                'data' => $departamentos,
                'count' => count($departamentos),
                'message' => 'Departamentos cargados exitosamente'
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            error_log("Error en get_departamentos_activos: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al obtener departamentos: ' . $e->getMessage(),
                'data' => [],
                'count' => 0
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    // ============================================================
    // OBTENER FRECUENCIAS ACTIVAS
    // ============================================================
    case 'get_frecuencias_activas':
        try {
            $sql = "SELECT id, nombre, dias, pagos_mes 
                    FROM tb_empl_frecuenciasdepago 
                    WHERE estado = 'ACTIVO' AND deleted_at IS NULL 
                    ORDER BY nombre";
            
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $conexion->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $frecuencias = [];
            while ($row = $result->fetch_assoc()) {
                $frecuencias[] = $row;
            }
            
            echo json_encode([
                'status' => 1,
                'data' => $frecuencias,
                'count' => count($frecuencias),
                'message' => 'Frecuencias cargadas exitosamente'
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            error_log("Error en get_frecuencias_activas: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al obtener frecuencias: ' . $e->getMessage(),
                'data' => [],
                'count' => 0
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    // ============================================================
    // OBTENER CUENTAS DEL CLIENTE
    // ============================================================
    case 'get_cuentas_cliente':
        try {
            $id_cliente = trim($_POST['id_cliente']);
            
            if (empty($id_cliente)) {
                throw new Exception("ID de cliente requerido");
            }
            
            // Cuentas de ahorro
            $sql_ahorro = "SELECT 
                'AHORRO' AS tipo, 
                aht.nombre AS descripcion, 
                aho.ccodaho AS cuenta, 
                calcularsaldocuentaahom(aho.ccodaho) AS saldo2, 
                aho.estado AS estado,
                CASE 
                    WHEN aho.estado = 'B' THEN 'Inactivo'
                    WHEN aho.estado = 'A' THEN 'Vigente'
                    WHEN aho.estado = 'X' THEN 'Eliminado'
                    ELSE 'Desconocido' 
                END AS estado_descripcion, 
                IFNULL(calcular_saldo_aho_tipcuenta(aho.ccodaho, CURDATE()), 0) AS saldo
            FROM ahomcta aho
            INNER JOIN tb_cliente cl ON aho.ccodcli = cl.idcod_cliente
            INNER JOIN ahomtip aht ON aht.ccodtip = SUBSTR(aho.ccodaho, 7, 2)
            WHERE aho.estado IN ('A', 'B', 'X') 
              AND cl.idcod_cliente = ?
            ORDER BY aho.ccodaho";
            
            $stmt_ahorro = $conexion->prepare($sql_ahorro);
            $stmt_ahorro->bind_param("s", $id_cliente);
            $stmt_ahorro->execute();
            $result_ahorro = $stmt_ahorro->get_result();
            
            $cuentas_ahorro = [];
            while ($row = $result_ahorro->fetch_assoc()) {
                $cuentas_ahorro[] = $row;
            }
            
            // Cuentas de aportación
            $sql_aportacion = "SELECT 
                'APORTACION' AS tipo, 
                apt.nombre AS descripcion, 
                apr.ccodaport AS cuenta, 
                NULL AS saldo2,
                apr.estado AS estado,
                CASE 
                    WHEN apr.estado = 'B' THEN 'Inactivo'
                    WHEN apr.estado = 'A' THEN 'Vigente'
                    WHEN apr.estado = 'X' THEN 'Eliminado'
                    ELSE 'Desconocido' 
                END AS estado_descripcion,
                calcular_saldo_apr_tipcuenta(apr.ccodaport, CURDATE()) AS saldo
            FROM aprcta apr 
            INNER JOIN tb_cliente cl ON apr.ccodcli = cl.idcod_cliente 
            INNER JOIN aprtip apt ON apt.ccodtip = apr.ccodtip
            WHERE apr.estado IN ('A', 'B', 'X') 
              AND cl.idcod_cliente = ?
            ORDER BY apr.ccodaport";
            
            $stmt_aportacion = $conexion->prepare($sql_aportacion);
            $stmt_aportacion->bind_param("s", $id_cliente);
            $stmt_aportacion->execute();
            $result_aportacion = $stmt_aportacion->get_result();
            
            $cuentas_aportacion = [];
            while ($row = $result_aportacion->fetch_assoc()) {
                $cuentas_aportacion[] = $row;
            }
            
            echo json_encode([
                'status' => 1,
                'ahorro' => $cuentas_ahorro,
                'aportacion' => $cuentas_aportacion,
                'count_ahorro' => count($cuentas_ahorro),
                'count_aportacion' => count($cuentas_aportacion),
                'count_total' => count($cuentas_ahorro) + count($cuentas_aportacion),
                'message' => 'Cuentas cargadas exitosamente'
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            error_log("Error en get_cuentas_cliente: " . $e->getMessage());
            echo json_encode([
                'status' => 0,
                'message' => 'Error al obtener cuentas: ' . $e->getMessage(),
                'ahorro' => [],
                'aportacion' => [],
                'count_total' => 0
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    default:
        echo json_encode(['status' => 0, 'message' => 'Condición no válida']);
         break;
}

// Función para generar código de empleado único
function generarCodigoEmpleado($conexion) {
    $prefijo = 'EMP';
    $anio = date('y');
    $mes = date('m');
    
    // Corregir la consulta para evitar errores
    $sql = "SELECT codigo_empleado 
            FROM tb_emp_empleado 
            WHERE codigo_empleado LIKE CONCAT(?, '-', ?, ?, '-%') 
            AND deleted_at IS NULL
            ORDER BY codigo_empleado DESC 
            LIMIT 1";
    
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        return $prefijo . '-' . $anio . $mes . '-0001';
    }
    
    $stmt->bind_param("sss", $prefijo, $anio, $mes);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ultimo_codigo = $row['codigo_empleado'];
        
        // Extraer el número secuencial
        $partes = explode('-', $ultimo_codigo);
        $ultimo_num = intval(end($partes));
        $nuevo_num = $ultimo_num + 1;
    } else {
        $nuevo_num = 1;
    }
    
    $nuevo_num_str = str_pad($nuevo_num, 4, '0', STR_PAD_LEFT);
    
    return $prefijo . '-' . $anio . $mes . '-' . $nuevo_num_str;
}
?>