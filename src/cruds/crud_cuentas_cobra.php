<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../funcphp/func_gen.php';

require '../../vendor/autoload.php';
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

use Luecano\NumeroALetras\NumeroALetras;
use App\Generic\DocumentManager;
use Micro\Helpers\Log;

date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d H:i:s");
$hoy2 = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {

    // Crear grupo (sin clientes)
    case 'create_group':
        
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
            return;
        }

        Log::info("post inputs: " . print_r($_POST, true));
        $inputs = $_POST['inputs'] ?? [];
        
        // Validación CSRF
        require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
        $csrf = new CSRFProtection();
        
        if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            echo json_encode([
                'msg' => $errorcsrf,
                'status' => 0
            ]);
            return;
        }

        $nombre = $inputs['nombre'] ?? '';
        $id_nomenclatura = intval($inputs['id_nomenclatura'] ?? 0);
        $estado = intval($inputs['estado'] ?? 1);

        // Validaciones
        $validar = validacionescampos([
            [$nombre, "", 'El nombre del grupo es obligatorio', 1],
            [$id_nomenclatura, "0", 'Debe seleccionar una cuenta contable', 1],
        ]);
        
        if ($validar[2]) {
            echo json_encode([
                'msg' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }

        $showmensaje = false;
        try {
            $database->openConnection();
            
            // Verificar si ya existe un grupo con el mismo nombre
            $grupoExistente = $database->selectColumns('cc_grupos', ['id'], "nombre=? AND deleted_by IS NULL", [$nombre]);
            if (!empty($grupoExistente)) {
                $showmensaje = true;
                throw new Exception("Ya existe un grupo con el nombre: " . $nombre);
            }

            // Verificar que la nomenclatura existe
            $nomenclaturaExiste = $database->selectColumns('ctb_nomenclatura', ['id'], "id=? AND deleted_by IS NULL", [$id_nomenclatura]);
            if (empty($nomenclaturaExiste)) {
                $showmensaje = true;
                throw new Exception("La cuenta contable seleccionada no existe");
            }

            $database->beginTransaction();
            
            // Insertar grupo usando el método correcto de la clase Database
            $datos = [
                'nombre' => $nombre,
                'id_nomenclatura' => $id_nomenclatura,
                'estado' => $estado,
                'created_by' => $idusuario,
                'created_at' => $hoy
            ];
            
            $id_grupo = $database->insert('cc_grupos', $datos);
            
            $database->commit();
            
            echo json_encode([
                'status' => 1,
                'msg' => 'Grupo creado con éxito',
                'id_grupo' => $id_grupo
            ]);
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente o reporte este código de error ($codigoError)";
            
            echo json_encode([
                'status' => 0,
                'msg' => $mensaje
            ]);
        } finally {
            $database->closeConnection();
        }
        break;

    case 'update_group':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
            return;
        }

        $inputs = $_POST['inputs'] ?? [];
        $archivo = $_POST['archivo'] ?? [];
        
        // Validación CSRF
        require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
        require_once __DIR__ . '/../../includes/Config/SecureID.php';
        $csrf = new CSRFProtection();
        $secureID = new SecureID($key1);
        
        if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
            echo json_encode([
                'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.",
                'status' => 0
            ]);
            return;
        }

        $nombre = $inputs['nombre'] ?? '';
        $id_nomenclatura = intval($inputs['id_nomenclatura'] ?? 0);
        $estado = intval($inputs['estado'] ?? 1);
        $encryptedID = $archivo[0] ?? '';

        // Validaciones
        $validar = validacionescampos([
            [$nombre, "", 'El nombre del grupo es obligatorio', 1],
            [$id_nomenclatura, "0", 'Debe seleccionar una cuenta contable', 1],
            [$encryptedID, "", 'ID del grupo no válido', 1],
        ]);
        
        if ($validar[2]) {
            echo json_encode([
                'msg' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }

        $id_grupo = $secureID->decrypt($encryptedID);
        
        $showmensaje = false;
        try {
            $database->openConnection();
            
            // Verificar que el grupo existe
            $grupoExiste = $database->selectColumns('cc_grupos', ['id'], "id=? AND deleted_by IS NULL", [$id_grupo]);
            if (empty($grupoExiste)) {
                $showmensaje = true;
                throw new Exception("El grupo seleccionado no existe");
            }

            // Verificar si ya existe otro grupo con el mismo nombre
            $grupoExistente = $database->selectColumns('cc_grupos', ['id'], "nombre=? AND id!=? AND deleted_by IS NULL", [$nombre, $id_grupo]);
            if (!empty($grupoExistente)) {
                $showmensaje = true;
                throw new Exception("Ya existe otro grupo con el nombre: " . $nombre);
            }

            // Verificar que la nomenclatura existe
            $nomenclaturaExiste = $database->selectColumns('ctb_nomenclatura', ['id'], "id=? AND deleted_by IS NULL", [$id_nomenclatura]);
            if (empty($nomenclaturaExiste)) {
                $showmensaje = true;
                throw new Exception("La cuenta contable seleccionada no existe");
            }

            $database->beginTransaction();
            
            // Actualizar grupo usando el método correcto
            $datosUpdate = [
                'nombre' => $nombre,
                'id_nomenclatura' => $id_nomenclatura,
                'estado' => $estado,
                'updated_by' => $idusuario,
                'updated_at' => $hoy
            ];
            
            $database->update('cc_grupos', $datosUpdate, "id=?", [$id_grupo]);
            
            $database->commit();

            echo json_encode(['status' => 1, 'msg' => 'Grupo actualizado correctamente']);
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente o reporte este código de error ($codigoError)";
            
            echo json_encode([
                'status' => 0,
                'msg' => $mensaje
            ]);
        } finally {
            $database->closeConnection();
        }
        break;


    // Eliminar un grupo y sus asignaciones
    case 'delete_group':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
            return;
        }

        $inputs = $_POST['inputs'] ?? [];
        $archivo = $_POST['archivo'] ?? [];
        
        // Validación CSRF
        require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
        require_once __DIR__ . '/../../includes/Config/SecureID.php';
        $csrf = new CSRFProtection();
        $secureID = new SecureID($key1);
        
        if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
            echo json_encode([
                'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.",
                'status' => 0
            ]);
            return;
        }

        $encryptedID = $archivo[0] ?? '';
        
        if (empty($encryptedID)) {
            echo json_encode([
                'msg' => 'ID del grupo no válido',
                'status' => 0
            ]);
            return;
        }

        $id_grupo = $secureID->decrypt($encryptedID);
        
        $showmensaje = false;
        try {
            $database->openConnection();
            
            // Verificar que el grupo existe
            $grupoExiste = $database->selectColumns('cc_grupos', ['id', 'nombre'], "id=? AND deleted_by IS NULL", [$id_grupo]);
            if (empty($grupoExiste)) {
                $showmensaje = true;
                throw new Exception("El grupo seleccionado no existe o ya fue eliminado");
            }

            $database->beginTransaction();
            
            // Marcar grupo como eliminado (soft delete)
            $datosDelete = [
                'deleted_by' => $idusuario,
                'deleted_at' => $hoy
            ];
            
            $database->update('cc_grupos', $datosDelete, "id=?", [$id_grupo]);
            
            // Eliminar asignaciones de clientes si existen
            $database->executeQuery("DELETE FROM cc_grupos_clientes WHERE id_grupo = ?", [$id_grupo]);
            
            $database->commit();

            echo json_encode(['status' => 1, 'msg' => 'Grupo eliminado con éxito']);
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente o reporte este código de error ($codigoError)";
            
            echo json_encode([
                'status' => 0,
                'msg' => $mensaje
            ]);
        } finally {
            $database->closeConnection();
        }
        break;

    // Obtener datos de un grupo y sus clientes
    case 'get_group':
        $id_grupo = intval($_POST['id_grupo'] ?? 0);

        try {
            // Obtener datos del grupo
            $stmt = $conexion->prepare("SELECT id, nombre, id_nomenclatura, estado FROM cc_grupos WHERE id = ? AND deleted_by IS NULL");
            $stmt->bind_param('i', $id_grupo);
            $stmt->execute();
            $result = $stmt->get_result();
            $grupo = $result->fetch_assoc();
            $stmt->close();

            if (!$grupo) {
                echo json_encode(['status' => 0, 'msg' => 'Grupo no encontrado']);
                break;
            }

            // Obtener clientes asignados
            $stmt = $conexion->prepare("SELECT id_cliente FROM cc_grupos_clientes WHERE id_grupo = ?");
            $stmt->bind_param('i', $id_grupo);
            $stmt->execute();
            $result = $stmt->get_result();
            $clientes = [];
            while ($row = $result->fetch_assoc()) {
                $clientes[] = $row['id_cliente'];
            }
            $stmt->close();

            echo json_encode(['status' => 1, 'grupo' => $grupo, 'clientes' => $clientes]);
        } catch (Exception $e) {
            echo json_encode(['status' => 0, 'msg' => 'Error al obtener el grupo: ' . $e->getMessage()]);
        }
        break;

    // Desasignar cliente de un grupo
    case 'unassign_client':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
            return;
        }

        $inputs = $_POST['inputs'] ?? [];
        $archivo = $_POST['archivo'] ?? [];
        
        // Validación CSRF
        require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
        $csrf = new CSRFProtection();
        
        if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
            echo json_encode([
                'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.",
                'status' => 0
            ]);
            return;
        }

        $id_asignacion = intval($archivo[0] ?? 0);
        
        if ($id_asignacion === 0) {
            echo json_encode([
                'msg' => 'ID de asignación no válido',
                'status' => 0
            ]);
            return;
        }

        $showmensaje = false;
        try {
            $database->openConnection();
            
            // Verificar que la asignación existe
            $asignacionExiste = $database->selectColumns('cc_grupos_clientes', ['id', 'id_grupo', 'id_cliente'], "id=?", [$id_asignacion]);
            if (empty($asignacionExiste)) {
                $showmensaje = true;
                throw new Exception("La asignación seleccionada no existe");
            }

            $database->beginTransaction();
            
            // Eliminar asignación
            $database->executeQuery("DELETE FROM cc_grupos_clientes WHERE id = ?", [$id_asignacion]);
            
            $database->commit();

            echo json_encode(['status' => 1, 'msg' => 'Cliente desasignado del grupo con éxito']);
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente o reporte este código de error ($codigoError)";
            
            echo json_encode([
                'status' => 0,
                'msg' => $mensaje
            ]);
        } finally {
            $database->closeConnection();
        }
        break;

    // Reasignar cliente a otro grupo
    case 'reassign_client':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
            return;
        }

        $inputs = $_POST['inputs'] ?? [];
        $archivo = $_POST['archivo'] ?? [];
        
        // Validación CSRF
        require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
        $csrf = new CSRFProtection();
        
        if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
            echo json_encode([
                'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.",
                'status' => 0
            ]);
            return;
        }

        $id_asignacion = intval($archivo[0] ?? 0);
        $id_grupo_nuevo = intval($archivo[1] ?? 0);
        
        // Validaciones
        $validar = validacionescampos([
            [$id_asignacion, "0", 'ID de asignación no válido', 1],
            [$id_grupo_nuevo, "0", 'Debe seleccionar un grupo nuevo', 1],
        ]);
        
        if ($validar[2]) {
            echo json_encode([
                'msg' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }

        $showmensaje = false;
        try {
            $database->openConnection();
            
            // Verificar que la asignación existe
            $asignacionExiste = $database->selectColumns('cc_grupos_clientes', ['id', 'id_grupo', 'id_cliente'], "id=?", [$id_asignacion]);
            if (empty($asignacionExiste)) {
                $showmensaje = true;
                throw new Exception("La asignación seleccionada no existe");
            }

            $id_cliente = $asignacionExiste[0]['id_cliente'];
            $id_grupo_actual = $asignacionExiste[0]['id_grupo'];

            // Verificar que el grupo nuevo existe
            $grupoExiste = $database->selectColumns('cc_grupos', ['id'], "id=? AND deleted_by IS NULL", [$id_grupo_nuevo]);
            if (empty($grupoExiste)) {
                $showmensaje = true;
                throw new Exception("El grupo seleccionado no existe");
            }

            // Verificar si ya está asignado al nuevo grupo
            $yaAsignado = $database->selectColumns('cc_grupos_clientes', ['id'], "id_grupo=? AND id_cliente=?", [$id_grupo_nuevo, $id_cliente]);
            if (!empty($yaAsignado)) {
                $showmensaje = true;
                throw new Exception("El cliente ya está asignado al grupo seleccionado");
            }

            $database->beginTransaction();
            
            // Eliminar asignación actual
            $database->executeQuery("DELETE FROM cc_grupos_clientes WHERE id = ?", [$id_asignacion]);
            
            // Insertar nueva asignación
            $datos = [
                'id_grupo' => $id_grupo_nuevo,
                'id_cliente' => $id_cliente
            ];
            
            $database->insert('cc_grupos_clientes', $datos);
            
            $database->commit();

            echo json_encode(['status' => 1, 'msg' => 'Cliente reasignado con éxito']);
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente o reporte este código de error ($codigoError)";
            
            echo json_encode([
                'status' => 0,
                'msg' => $mensaje
            ]);
        } finally {
            $database->closeConnection();
        }
        break;

    // Asignar cliente a un grupo existente
    case 'assign_client':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
            return;
        }

        $inputs = $_POST['inputs'] ?? [];
        
        // Validación CSRF
        require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
        $csrf = new CSRFProtection();
        
        if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
            echo json_encode([
                'msg' => "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.",
                'status' => 0
            ]);
            return;
        }

        $id_grupo = intval($inputs['id_grupo'] ?? 0);
        $id_cliente = $inputs['id_cliente'] ?? '';
        
        // Validaciones
        $validar = validacionescampos([
            [$id_grupo, "0", 'Debe seleccionar un grupo', 1],
            [$id_cliente, "", 'Debe seleccionar un cliente', 1],
        ]);
        
        if ($validar[2]) {
            echo json_encode([
                'msg' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }

        $showmensaje = false;
        try {
            $database->openConnection();
            
            // Verificar que el grupo existe
            $grupoExiste = $database->selectColumns('cc_grupos', ['id'], "id=? AND deleted_by IS NULL", [$id_grupo]);
            if (empty($grupoExiste)) {
                $showmensaje = true;
                throw new Exception("El grupo seleccionado no existe");
            }

            // Verificar que el cliente existe
            $clienteExiste = $database->selectColumns('tb_cliente', ['idcod_cliente'], "idcod_cliente=?", [$id_cliente]);
            if (empty($clienteExiste)) {
                $showmensaje = true;
                throw new Exception("El cliente seleccionado no existe");
            }

            // Verificar si ya está asignado
            $yaAsignado = $database->selectColumns('cc_grupos_clientes', ['id'], "id_grupo=? AND id_cliente=?", [$id_grupo, $id_cliente]);
            if (!empty($yaAsignado)) {
                $showmensaje = true;
                throw new Exception("El cliente ya está asignado a este grupo");
            }

            $database->beginTransaction();
            
            // Insertar asignación
            $datos = [
                'id_grupo' => $id_grupo,
                'id_cliente' => $id_cliente
            ];
            
            $database->insert('cc_grupos_clientes', $datos);
            
            $database->commit();

            echo json_encode(['status' => 1, 'msg' => 'Cliente asignado al grupo con éxito']);
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente o reporte este código de error ($codigoError)";
            
            echo json_encode([
                'status' => 0,
                'msg' => $mensaje
            ]);
        } finally {
            $database->closeConnection();
        }
        break;
}
//FUNCION para obtener los depositos de los utimos 30 dias y los R(reversion y retiros) asi como el valor del dolar.


//FUNCIN para el control de las alertas

function validar_campos_plus($validaciones)
{
    for ($i = 0; $i < count($validaciones); $i++) {
        if ($validaciones[$i][3] == 1) { //igual
            if ($validaciones[$i][0] == $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 2) { //menor que
            if ($validaciones[$i][0] < $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 3) { //mayor que
            if ($validaciones[$i][0] > $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 4) { //Validarexpresionesregulares
            if (validar_expresion_regular($validaciones[$i][0], $validaciones[$i][1])) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 5) { //Escapar de la validacion
        } elseif ($validaciones[$i][3] == 6) { //menor o igual
            if ($validaciones[$i][0] <= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 7) { //menor o igual
            if ($validaciones[$i][0] >= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 8) { //diferente de
            if ($validaciones[$i][0] != $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        }
    }
    return ["", '0', false];
}
function executequery($query, $params, $typparams, $conexion)
{
    $stmt = $conexion->prepare($query);
    $aux = mysqli_error($conexion);
    if ($aux) {
        return ['ERROR: ' . $aux, false];
    }
    $types = '';
    $bindParams = [];
    $bindParams[] = &$types;
    $i = 0;
    foreach ($params as &$param) {
        // $types .= 's';
        $types .= $typparams[$i];
        $bindParams[] = &$param;
        $i++;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    if (!$stmt->execute()) {
        return ["Error en la ejecución de la consulta: " . $stmt->error, false];
    }
    $data = [];
    $resultado = $stmt->get_result();
    $i = 0;
    while ($fila = $resultado->fetch_assoc()) {
        $data[$i] = $fila;
        $i++;
    }
    $stmt->close();
    return [$data, true];
}
