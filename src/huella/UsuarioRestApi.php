<?php

use Micro\Helpers\Log;

header("Acces-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");

$method = $_SERVER['REQUEST_METHOD'];

// Metodo para peticiones tipo GET
if ($method == "GET") {
    $token = $_GET['token'];
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];

    //GENERICO, SELECCIONA TODOS LOS REGISTROS DE LA TABLA, Y SE VERIFICA SI EXISTE UNO RELACIONADO A LA HUELLA QUE SE ESTA VALIDANDO
    //(agregar el join con tb usuarios tambien)
    $query = "SELECT mano, dedo, huella, imgHuella,hd.id_persona,cli.short_name FROM huella_digital hd
                INNER JOIN tb_cliente cli on cli.idcod_cliente = hd.id_persona
                WHERE hd.estado = 1 LIMIT ?,?";

    //SELECCIONA LOS REGISTROS QUE CORRESPONDEN A UN CLIENTE (1) EN ESPECIFICO
    $query2 = "SELECT mano, dedo, huella, imgHuella,hd.id_persona FROM huella_digital hd
                WHERE hd.estado = 1 AND hd.id_persona = ? AND tipo_persona=1 LIMIT ?,?";

    //SELECCIONA LOS REGISTROS QUE CORRESPONDEN A UN USUARIO DEL SISTEMA (2) EN ESPECIFICO
    $query3 = "SELECT mano, dedo, huella, imgHuella,hd.id_persona,usu.nombre FROM huella_digital hd
                INNER JOIN tb_usuario usu on usu.id_usu = hd.id_persona
                WHERE hd.estado = 1 AND hd.id_persona = ? AND tipo_persona=2 LIMIT ?,?";

    $query4 = "SELECT hd.mano, hd.dedo, hd.huella, hd.imgHuella, hd.id_persona
                    FROM huella_digital hd
                    WHERE hd.estado = 1
                    AND hd.tipo_persona = 1
                    AND hd.id_persona IN (
                        SELECT ccodcli FROM ahomcta 
                        WHERE ccodaho=?
                        UNION
                        SELECT ccodcli FROM cli_mancomunadas 
                        WHERE tipo='ahorro' AND ccodaho=? AND estado='1'
                    ) LIMIT ?,?";

    $query5 = "SELECT hd.mano, hd.dedo, hd.huella, hd.imgHuella, hd.id_persona
                    FROM huella_digital hd
                    WHERE hd.estado = 1
                    AND hd.tipo_persona = 1
                    AND hd.id_persona IN (
                        SELECT ccodcli FROM aprcta 
                        WHERE ccodaport=?
                        UNION
                        SELECT ccodcli FROM cli_mancomunadas 
                        WHERE tipo='aportacion' AND ccodaho=? AND estado='1'
                    ) LIMIT ?,?";

    $showmensaje = false;
    try {
        $database->openConnection();

        $tempHuella = $database->selectColumns('huella_temp', ['findCode', 'typeFindCode'], "pc_serial=?", [$token]);
        if (empty($tempHuella)) {
            $showmensaje = true;
            throw new Exception("No se encontró ninguna plantilla a utilizar");
        }

        if ($tempHuella[0]['typeFindCode'] == 1) {
            /**
             * PARA BUSQUEDA POR CODIGO DE CLIENTE
             */
            $dataHuellas = $database->getAllResults($query2, [$tempHuella[0]['findCode'], $desde, $hasta]);
            $totalHuellas = $database->getAllResults("SELECT count(id_persona) total FROM huella_digital WHERE estado = 1 AND id_persona = ?", [$tempHuella[0]['findCode']]);
        } elseif ($tempHuella[0]['typeFindCode'] == 2) {
            /**
             * PARA BUSQUEDA POR CODIGO DE USUARIO
             */
            $dataHuellas = $database->getAllResults($query3, [$tempHuella[0]['findCode'], $desde, $hasta]);
            $totalHuellas = $database->getAllResults("SELECT count(id_persona) total FROM huella_digital WHERE estado = 1 AND id_persona = ?", [$tempHuella[0]['findCode']]);
        } elseif ($tempHuella[0]['typeFindCode'] == 3) {
            /**
             * PARA BUSQUEDA POR CODIGO DE CUENTA DE AHORROS
             */
            $dataHuellas = $database->getAllResults($query4, [$tempHuella[0]['findCode'], $tempHuella[0]['findCode'], $desde, $hasta]);
            $totalHuellas = $database->getAllResults(
                "SELECT COUNT(*) AS total
                    FROM huella_digital hd
                    WHERE hd.estado = 1
                    AND hd.tipo_persona = 1
                    AND hd.id_persona IN (
                        SELECT ccodcli FROM ahomcta WHERE ccodaho = ?
                        UNION
                        SELECT ccodcli FROM cli_mancomunadas WHERE tipo = 'ahorro' AND ccodaho = ? AND estado = '1'
                    );",
                [$tempHuella[0]['findCode'], $tempHuella[0]['findCode']]
            );
        } elseif ($tempHuella[0]['typeFindCode'] == 4) {
            /**
             * PARA BUSQUEDA POR CODIGO DE CUENTA DE APORTACIONES
             */
            $dataHuellas = $database->getAllResults($query5, [$tempHuella[0]['findCode'], $tempHuella[0]['findCode'], $desde, $hasta]);
            $totalHuellas = $database->getAllResults(
                "SELECT COUNT(*) AS total
                    FROM huella_digital hd
                    WHERE hd.estado = 1
                    AND hd.tipo_persona = 1
                    AND hd.id_persona IN (
                        SELECT ccodcli FROM aprcta WHERE ccodaport = ?
                        UNION
                        SELECT ccodcli FROM cli_mancomunadas WHERE tipo = 'aportacion' AND ccodaho = ? AND estado = '1'
                    );",
                [$tempHuella[0]['findCode'], $tempHuella[0]['findCode']]
            );
        } else {
            $dataHuellas = $database->getAllResults($query, [$desde, $hasta]);
            $totalHuellas = $database->getAllResults("SELECT count(id_persona) total FROM huella_digital WHERE estado = 1");
        }
        $status = 1;
    } catch (Exception $e) {
        // if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        // }
        $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        $status = 0;
    } finally {
        $database->closeConnection();
    }

    if ($status == 0) {
        http_response_code(500);
        echo json_encode(array("error" => $mensaje));
        exit();
    }

    //formar el array de respuesta
    $arrayResponse = array();
    foreach ($dataHuellas as $index => $huella) {
        $arrayObject = array();
        $arrayObject["count"] = $totalHuellas[0]['total'];
        $arrayObject["documento"] = $huella["id_persona"];
        $arrayObject["nombre_completo"] = "NO DISPONIBLE";
        // $arrayObject["nombre_completo"] = $huella["short_name"];
        $arrayObject["nombre_dedo"] = "Pulgar";
        $arrayObject["mano"] = $huella["mano"];
        $arrayObject["dedo"] = $huella["dedo"];
        $arrayObject["huella"] = $huella["huella"];
        $arrayObject["imgHuella"] = $huella["imgHuella"];
        $arrayResponse[] = $arrayObject;
    }
    echo json_encode($arrayResponse);
}

// Metodo para peticiones tipo POST
if ($method == "POST") {
    $jsonString = file_get_contents("php://input");
    $jsonOBJ = json_decode($jsonString, true);

    // Log::info("usuariorestapi.php",$jsonOBJ);

    $showmensaje = false;
    try {
        $database->openConnection();
        $huella_temp = array(
            "huella" => $jsonOBJ['huella'],
            "imgHuella" => $jsonOBJ['imageHuella'],
            "update_time" => $hoy2,
            "fecha_actualizacion" => $hoy2,
            "statusPlantilla" => $jsonOBJ['statusPlantilla'],
            "texto" => $jsonOBJ['texto'],
        );
        $rows = $database->update("huella_temp", $huella_temp, "pc_serial=?", [$jsonOBJ['serial']]);
        $status = 1;
    } catch (Exception $e) {
        if (!$showmensaje) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        }
        $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        $status = 0;
    } finally {
        $database->closeConnection();
    }

    if ($status == 0) {
        http_response_code(500);
        echo json_encode(array("error" => $mensaje));
        exit();
    }
    echo json_encode("Filas Agregadas: " . $rows);
}

// Metodo para peticiones tipo PUT
if ($method == "PUT") {
    $jsonString = stripslashes(file_get_contents("php://input"));
    $jsonOBJ = json_decode($jsonString);

    $showmensaje = false;
    try {
        $database->openConnection();
        if ($jsonOBJ->option == "verificar") {
            $huella_temp = array(
                "imgHuella" => $jsonOBJ->imageHuella,
                "update_time" => $hoy2,
                "fecha_actualizacion" => $hoy2,
                "statusPlantilla" => $jsonOBJ->statusPlantilla,
                "texto" => $jsonOBJ->texto,
            );
        } else {
            $huella_temp = array(
                "imgHuella" => $jsonOBJ->imageHuella,
                "update_time" => $hoy2,
                "fecha_actualizacion" => $hoy2,
                "statusPlantilla" => $jsonOBJ->statusPlantilla,
                "texto" => $jsonOBJ->texto,
                "opc" => "stop",
            );
        }
        $rows = $database->update("huella_temp", $huella_temp, "pc_serial=?", [$jsonOBJ->serial]);
        $status = 1;
    } catch (Exception $e) {
        if (!$showmensaje) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        }
        $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        $status = 0;
    } finally {
        $database->closeConnection();
    }

    if ($status == 0) {
        http_response_code(500);
        echo json_encode(array("error" => $mensaje));
        exit();
    }
    echo json_encode("Filas Actualizadas: " . $rows);
}