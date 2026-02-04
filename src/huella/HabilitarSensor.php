<?php
//Controlado por app java

// use Micro\Helpers\Log;

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once './bd.php';
set_time_limit(0);
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$token = $_GET['token'];
$fecha_actual = 0;
$fecha_bd = 0;

// Log::info("HabilitarSensor.php: Inicio de la ejecución con token: $token");

//el metodo post es para la aplicacion java
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $fecha_actual = (isset($_POST['timestamp']) && $_POST['timestamp'] != 'null') ? $_POST['timestamp'] : 0;
} else {
    // Log::info("HabilitarSensor.php: Método GET detectado, obteniendo timestamp.");
    //el metodo get es para la web
    if (isset($_GET['timestamp']) && $_GET['timestamp'] != 'null') {
        $fecha_actual = $_GET['timestamp'];
    }
}

$con = new bd($db_host,$db_name,$db_user,$db_password);
$elapsedTime = 0;
while ($fecha_bd <= $fecha_actual) {
    // Log::info("HabilitarSensor.php: Esperando actualización de la base de datos. Tiempo transcurrido: $elapsedTime segundos.");
    $query = "Select fecha_creacion,opc from huella_temp where pc_serial = '" . $token . "' ORDER BY id DESC LIMIT 1";
    $rs = $con->findAll($query);
    usleep(500000);
    clearstatcache();
    if (count($rs) > 0) {
        $fecha_bd = strtotime($rs[0]['fecha_creacion']);
        // if($rs[0]['opc'] == 'stop'){
        //     Log::info("HabilitarSensor.php: Opción 'stop' detectada, saliendo del bucle.");
        //     break;
        // } 
    }
    $elapsedTime = $elapsedTime + 1;

    /**
     * Condición para salir del bucle después de un tiempo determinado.
     * Aquí se establece un tiempo máximo de espera de 1000 segundos (aproximadamente 16 minutos y 40 segundos).
     */
    if ($elapsedTime == 1000) {//modificar aqui si se requiere reiniciar em menos tiempo
        break;
    }
}

// Log::info("HabilitarSensor.php: Tiempo de espera finalizado. Tiempo total transcurrido: $elapsedTime segundos.");

$query = "Select fecha_creacion, opc, pc_serial from huella_temp where pc_serial = '" . $token . "' ORDER BY id DESC LIMIT 1";
$datos_query = $con->findAll($query);

$array = array('fecha_creacion' => 0, 'opc' => 'reintentar');
for ($i = 0; $i < count($datos_query); $i++) {
    $array['fecha_creacion'] = strtotime($datos_query[$i]['fecha_creacion']);
    $array['opc'] = $datos_query[$i]['opc'];
    $array['pc_serial'] = $datos_query[$i]['pc_serial'];
}
$con->desconectar();
$response = json_encode($array);
//echo "hola Mundo";
echo $response;


