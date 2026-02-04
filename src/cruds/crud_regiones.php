<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

// Suprimir warnings para evitar contaminar el JSON de respuesta
error_reporting(E_ERROR | E_PARSE);

// Limpiar cualquier salida previa
ob_start();

function send_json($payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

use App\Log;

session_start();
include __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

include '../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];

switch ($condi) {
    
    case 'create_region':
        if (!isset($_SESSION['id_agencia'])) {
            send_json(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
        }
        
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        // Desestructuración usando list
        list($name, $id_encargado) = $inputs;
        list($userCurrent, $agencies) = [$archivo[0] ?? null, $archivo[1] ?? null];

        // Validar campos requeridos
        $validar = validar_campos([
            [$name, "", 'Ingrese nombre de región'],
            [$id_encargado, "", 'Seleccione un analista encargado'],
            [$userCurrent, "", 'No se ha detectado el usuario creador del registro'],
        ]);
        
        if ($validar[2]) {
            send_json([$validar[0], $validar[1]]);
        }
        
        // Validar que haya al menos una agencia
        if (empty($agencies)) {
            send_json(["Debe agregar al menos una agencia para crear la región", '0']);
        }

        try {
            $showmensaje = false;
            $database->openConnection();
            $database->beginTransaction();
            
            // Insertar región
            $cre_regiones = array(
                'nombre' => trim($name),
                'id_encargado' => $id_encargado,
                'estado' => 1,
                'created_by' => $userCurrent,
                'updated_by' => $userCurrent,
                'created_at' => $hoy2,
                'updated_at' => $hoy2,
            );
            
            $id_region = $database->insert('cre_regiones', $cre_regiones);

            // Insertar agencias relacionadas
            foreach ($agencies as $currentAgency) {
                $cre_regiones_agencias = array(
                    'id_region' => $id_region,
                    'id_agencia' => $currentAgency,
                );
                $database->insert('cre_regiones_agencias', $cre_regiones_agencias);
            }
            
            $database->commit();
            $mensaje = "Región creada exitosamente";
            $status = '1';
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        
        send_json([$mensaje, $status]);
        break;

    case 'update_region':
        if (!isset($_SESSION['id_agencia'])) {
            send_json(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
        }
        
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        // Desestructuración usando list
        list($name, $id_encargado, $id_region) = $inputs;
        list($userCurrent, $agencies) = [$archivo[0] ?? null, $archivo[1] ?? null];

        // Validar campos requeridos
        $validar = validar_campos([
            [$id_region, "", 'Identificador no encontrado, refresque la página'],
            [$name, "", 'Ingrese nombre de región'],
            [$id_encargado, "", 'Seleccione un analista encargado'],
            [$userCurrent, "", 'No se ha detectado el usuario editor del registro'],
        ]);
        
        if ($validar[2]) {
            send_json([$validar[0], $validar[1]]);
        }
        
        // Validar que haya al menos una agencia
        if (empty($agencies)) {
            send_json(["Debe agregar al menos una agencia para la región", '0']);
        }

        try {
            $showmensaje = false;
            $database->openConnection();
            $database->beginTransaction();
            
            // Actualizar región
            $cre_regiones = array(
                'nombre' => trim($name),
                'id_encargado' => $id_encargado,
                'updated_by' => $userCurrent,
                'updated_at' => $hoy2,
            );
            
            $database->update('cre_regiones', $cre_regiones, "id=?", [$id_region]);
            
            // Eliminar agencias existentes y volver a insertar
            $database->delete("cre_regiones_agencias", "id_region=?", [$id_region]);

            // Insertar nuevas agencias
            foreach ($agencies as $currentAgency) {
                $cre_regiones_agencias = array(
                    'id_region' => $id_region,
                    'id_agencia' => $currentAgency,
                );
                $database->insert('cre_regiones_agencias', $cre_regiones_agencias);
            }
            
            $database->commit();
            $mensaje = "Región actualizada exitosamente";
            $status = '1';
            
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        
        send_json([$mensaje, $status]);
        break;

    case 'delete_region':
        if (!isset($_SESSION['id_agencia'])) {
            send_json(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
        }
        
        $id = $_POST['ideliminar'] ?? null;
        
        $validar = validar_campos([
            [$id, "", 'Identificador no encontrado, refresque la página'],
        ]);
        
        if ($validar[2]) {
            send_json([$validar[0], $validar[1]]);
        }

        try {
            $showmensaje = false;
            $database->openConnection();
            
            // Soft delete - cambiar estado a 0
            $cre_regiones = array(
                'estado' => 0,
                'deleted_by' => $idusuario,
                'deleted_at' => $hoy2,
            );
            
            $database->update('cre_regiones', $cre_regiones, "id=?", [$id]);
            $mensaje = "Región eliminada exitosamente";
            $status = '1';
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = '0';
        } finally {
            $database->closeConnection();
        }
        
        send_json([$mensaje, $status]);
        break;

    case 'update_estado_region':
        if (!isset($_SESSION['id_agencia'])) {
            send_json(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
        }
        
        $archivo = $_POST["archivo"] ?? [];
        $id = $archivo[0] ?? null;
        $estado = $archivo[1] ?? null;
        
        // Debug logging
        error_log("update_estado_region - ID: $id, Estado: $estado");
        
        $validar = validar_campos([
            [$id, "", 'Identificador no encontrado'],
        ]);
        
        if ($validar[2]) {
            send_json([$validar[0], $validar[1]]);
        }

        try {
            $showmensaje = false;
            $database->openConnection();
            
            // Actualizar estado
            $cre_regiones = array(
                'estado' => $estado,
                'updated_by' => $idusuario,
                'updated_at' => $hoy2,
            );
            
            $database->update('cre_regiones', $cre_regiones, "id=?", [$id]);
            $mensaje = ($estado == 1) ? "Región activada exitosamente" : "Región desactivada exitosamente";
            $status = '1';
            
            error_log("update_estado_region exitoso - Respuesta: " . json_encode([$mensaje, $status]));
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = '0';
            error_log("update_estado_region error: " . $e->getMessage());
        } finally {
            $database->closeConnection();
        }
        
        send_json([$mensaje, $status]);
        break;

    default:
        send_json(['Acción no reconocida', '0']);
        break;
}

// FUNCIÓN PARA REALIZAR VALIDACIONES
function validar_campos($validaciones)
{
    for ($i = 0; $i < count($validaciones); $i++) {
        if (empty(trim($validaciones[$i][0])) && $validaciones[$i][1] != 'vacio') {
            return [$validaciones[$i][2], '0', true];
        }
    }
    return ["", '0', false];
}
