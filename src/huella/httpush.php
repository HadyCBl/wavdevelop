<?php

// use Micro\Helpers\Log;

require_once './bd.php';
set_time_limit(0);
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");

$fecha_actual = 0;
$fecha_bd = 0;
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $fecha_actual = (isset($_POST['timestamp']) && $_POST['timestamp'] != 'null') ? $_POST['timestamp'] : 0;
} else {
    if (isset($_GET['timestamp']) && $_GET['timestamp'] != 'null') {
        $fecha_actual = $_GET['timestamp'];
    }
}
// Log::info("httpush.php: Inicio de la ejecución con timestamp: $fecha_actual");
$conn = new bd($db_host,$db_name,$db_user,$db_password);

while ($fecha_bd <= $fecha_actual) {
    $sql = "SELECT update_time FROM huella_temp where pc_serial = '" . $_POST['token'] . "'  ORDER BY update_time DESC LIMIT 1";
    $rows = $conn->findAll($sql);
    usleep(500000); // Espera 0.5 segundos (500 milisegundos)
    clearstatcache();

    // Log::info("httpush.php: Esperando actualización de la base de datos. Tiempo transcurrido: " . (time() - strtotime($hoy2)) . " segundos.");

    // if (count($rows) > 0) {
    //     $fecha_bd = strtotime($rows[0]['update_time']);
    // }
    if (count($rows) > 0 && !empty($rows[0]['update_time'])) {
        $fecha_bd = strtotime($rows[0]['update_time']);
    } else {
        // Si no hay un valor válido, puedes asignar un valor por defecto
        $fecha_bd = 0; // o cualquier otro valor que tenga sentido en tu lógica
    }
}

// Log::info("httpush.php: Tiempo de espera finalizado. Tiempo total transcurrido: " . (time() - strtotime($hoy2)) . " segundos.");

//VERIFICAR SI QUIZA NECESITA QUE SE LE PONGA COMO CONDICION EL TOKEN PC
$sql = "SELECT pc_serial,imgHuella,update_time,texto,statusPlantilla,opc" . " FROM huella_temp ORDER BY update_time DESC LIMIT 1";
$rows = $conn->findAll($sql);

$reponse = array();
$reponse["id"] = $rows[0]['pc_serial'];
$reponse["timestamp"] = strtotime($rows[0]['update_time']);
$reponse["texto"] = $rows[0]['texto'];
$reponse["statusPlantilla"] = $rows[0]['statusPlantilla'];
$reponse["imgHuella"] = $rows[0]['imgHuella'];
$reponse["tipo"] = $rows[0]['opc'];

$datosJson = json_encode($reponse);
$conn->desconectar();
echo $datosJson;