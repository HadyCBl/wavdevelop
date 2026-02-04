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

date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d H:i:s");
$hoy2 = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {
    //CRUD - TIPOS DE Opciones 
    //-----CREATE
    case 'create_puclic_credit': 
           
    break;
    //-----ACTUALIZAR
    case 'update_puclic_credit': 

    break;

    //-----ELIMINAR
    case 'delete_puclic_credit': 

    break;
    //Obtener datos del Producto 
    case 'get_datos_producto': 

    break;


    //-----CREATE
    case 'create_puclic_services':         
    break;
    //-----ACTUALIZAR
    case 'update_puclic_services': 
    break;

    //-----ELIMINAR
    case 'delete_puclic_services': 

    break;
    //Obtener datos del Servicio 
    case 'get_puclic_services': 

    break;

     //-----CREATE
    case 'creae_user_banca': 
           
        break;
    //-----ACTUALIZAR
    case 'update_user_banca': 
        break;

    //-----ELIMINAR
    case 'delete_user_banca': 

    break;

    case 'get_user_banca': 

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