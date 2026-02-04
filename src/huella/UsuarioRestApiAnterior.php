<?php
//CONTROLADO POR APP JAVA
//Api Rest
header("Acces-Control-Allow-Origin: *");
header("Content-Type: application/json");
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
include_once './bd.php';
$con = new bd($db_host, $db_name, $db_user, $db_password);

$method = $_SERVER['REQUEST_METHOD'];


// Metodo para peticiones tipo GET
if ($method == "GET") {
    //    eliminar el token
    $token = $_GET['token'];
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];

    $sql = "SELECT mano, dedo, huella, imgHuella,hd.id_persona,cli.short_name FROM huella_digital hd
                INNER JOIN tb_cliente cli on cli.idcod_cliente = hd.id_persona
                WHERE hd.estado = 1 LIMIT " . $desde . "," . $hasta . " ";
    $rs = $con->findAll($sql);

    $sql_ = "select count(id_persona) total from huella_digital where estado = 1";
    $rs_c = $con->findAll($sql_);

    $arrayResponse = array();
    for ($index = 0; $index < count($rs); $index++) {
        $arrayObject = array();
        $arrayObject["count"] = $rs_c[0]['total'];
        $arrayObject["documento"] = $rs[$index]["id_persona"];
        $arrayObject["nombre_completo"] = $rs[$index]["short_name"];
        $arrayObject["nombre_dedo"] = "Pulgar";
        $arrayObject["mano"] = $rs[$index]["mano"];
        $arrayObject["dedo"] = $rs[$index]["dedo"];
        $arrayObject["huella"] = $rs[$index]["huella"];
        $arrayObject["imgHuella"] = $rs[$index]["imgHuella"];
        $arrayResponse[] = $arrayObject;
    }
    //echo count($arrayResponse); die;
    echo json_encode($arrayResponse);
}
// // Metodo para peticiones tipo GET
// if ($method == "GET") {
// //    eliminar el token
//     $token = $_GET['token'];
//     $desde = $_GET['desde'];
//     $hasta = $_GET['hasta'];

//     $sql = "select u.documento, u.nombre_completo, h.nombre_dedo, h.huella, h.imgHuella "
//             . "from usuarios u "
//             . "inner join huellas h on u.documento  = h. documento limit " . $desde . "," . $hasta . " ";
//     $rs = $con->findAll($sql);    

//     $sql_ = "select count(documento) total from usuarios";
//     $rs_c = $con->findAll($sql_);

//     $arrayResponse = array();
//     for ($index = 0; $index < count($rs); $index++) {
//         $arrayObject = array();
//         $arrayObject["count"] = $rs_c[0]['total'];
//         $arrayObject["documento"] = $rs[$index]["documento"];
//         $arrayObject["nombre_completo"] = $rs[$index]["nombre_completo"];
//         $arrayObject["nombre_dedo"] = $rs[$index]["nombre_dedo"];
//         $arrayObject["huella"] = $rs[$index]["huella"];
//         $arrayObject["imgHuella"] = $rs[$index]["imgHuella"];
//         $arrayResponse[] = $arrayObject;
//     }
// //echo count($arrayResponse); die;
//     echo json_encode($arrayResponse);
// }


// Metodo para peticiones tipo POST
if ($method == "POST") {
    $jsonString = file_get_contents("php://input");
    $jsonOBJ = json_decode($jsonString, true);
    $query = "update huella_temp set huella = '" . $jsonOBJ['huella'] . "', imgHuella = '" . $jsonOBJ['imageHuella'] . "',"
        . "update_time = '$hoy2',fecha_actualizacion = '$hoy2', statusPlantilla = '" . $jsonOBJ['statusPlantilla'] . "',"
        . "texto = '" . $jsonOBJ['texto'] . "' "
        . "where pc_serial = '" . $jsonOBJ['serial'] . "'";


    //    echo $query;
    $row = $con->exec($query);
    $con->desconectar();
    echo json_encode("Filas Agregadas: " . $row);
}

// $query = "update huellas_temp set huella = '" . $jsonOBJ['huella'] . "', imgHuella = '" . $jsonOBJ['imageHuella'] . "',"
// . "update_time = NOW(), statusPlantilla = '" . $jsonOBJ['statusPlantilla'] . "',"
// . "texto = '" . $jsonOBJ['texto'] . "' "
// . "where pc_serial = '" . $jsonOBJ['serial'] . "'";


// Metodo para peticiones tipo PUT
if ($method == "PUT") {
    $jsonString = stripslashes(file_get_contents("php://input"));
    $jsonOBJ = json_decode($jsonString);

    if ($jsonOBJ->option == "verificar") {
        $query = "update huella_temp set imgHuella = '" . $jsonOBJ->imageHuella . "',"
            . "update_time =  '$hoy2',fecha_actualizacion = '$hoy2',"
            . "statusPlantilla = '" . $jsonOBJ->statusPlantilla . "',"
            . "texto = '" . $jsonOBJ->texto . "' "
            . "where pc_serial = '" . $jsonOBJ->serial . "'";
    } else {
        $query = "update huella_temp set imgHuella = '" . $jsonOBJ->imageHuella . "',"
            . "update_time =  '$hoy2',fecha_actualizacion = '$hoy2', statusPlantilla = '" . $jsonOBJ->statusPlantilla . "',"
            . " texto = '" . $jsonOBJ->texto . "', opc = 'stop' "
            . "where pc_serial = '" . $jsonOBJ->serial . "'";
    }


    $row = $con->exec($query);
    $con->desconectar();
    echo json_encode("Filas Actualizadas: " . $row);
}

// Metodo para peticiones tipo PUT
// if ($method == "PUT") {
//     $jsonString = stripslashes(file_get_contents("php://input"));
//     $jsonOBJ = json_decode($jsonString);

//     if ($jsonOBJ->option == "verificar") {
//         $query = "update huella_temp set imgHuella = '" . $jsonOBJ->imageHuella . "',"
//             . "update_time =  '$hoy2',fecha_actualizacion = '$hoy2',"
//             . "statusPlantilla = '" . $jsonOBJ->statusPlantilla . "',"
//             . "texto = '" . $jsonOBJ->texto . "',"
//             . "documento =  '" . $jsonOBJ->documento . "',"
//             . "nombre = '" . $jsonOBJ->nombre . "',"
//             . "dedo =  '" . $jsonOBJ->dedo . "' "
//             . "where pc_serial = '" . $jsonOBJ->serial . "'";
//     } else {
//         $query = "update huella_temp set imgHuella = '" . $jsonOBJ->imageHuella . "',"
//             . "update_time =  '$hoy2',fecha_actualizacion = '$hoy2', statusPlantilla = '" . $jsonOBJ->statusPlantilla . "',"
//             . " texto = '" . $jsonOBJ->texto . "', opc = 'stop' "
//             . "where pc_serial = '" . $jsonOBJ->serial . "'";
//     }


//     $row = $con->exec($query);
//     $con->desconectar();
//     echo json_encode("Filas Actualizadas: " . $row);
// }



// Metodo para peticiones tipo PATCH
if ($method == "PATCH") {
    $jsonString = file_get_contents("php://input");
    $jsonOBJ = json_decode($jsonString, true);
    $query = "update huella_temp set imgHuella = '" . $jsonOBJ['imgHuella'] . "',"
        . "update_time =  '$hoy2',fecha_actualizacion = '$hoy2', statusPlantilla = '" . $jsonOBJ['statusPlantilla'] . "', texto = '" . $jsonOBJ['texto'] . "', "
        . "' where pc_serial = '" . $jsonOBJ['serial'] . "'";
    $row = $con->exec($query);
    $con->desconectar();
    echo json_encode("Filas Actualizadas: " . $row);
}
