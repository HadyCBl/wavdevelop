<?php

/**
 * @deprecated Este archivo está deprecado. Usar Eloquent ORM o DatabaseAdapter (PDO) en su lugar.
 * @see \Micro\Database\DB Para conexiones usando Eloquent
 * @see \App\DatabaseAdapter Para conexiones usando PDO
 */

use Micro\Helpers\Log;

// Marcar como deprecated
// trigger_error(
//     'El archivo db_con.php está deprecado. Por favor, usar Eloquent ORM (DB::connection()) o DatabaseAdapter para conexiones a la base de datos.',
//     E_USER_DEPRECATED
// );

require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];
$type_host = $_ENV['BANDERA'];
$type_timezone = $_ENV['BANDERA_TIMEZONE'];


$key1 = $_ENV['MYKEYPASS'];
$key2 = $_ENV['MYKEYALEATORIO'];
try {
    /**
     * @deprecated Variable $conexion está deprecada. Usar Eloquent DB::connection() o DatabaseAdapter->openConnection(1) en su lugar.
     */
    $conexion = mysqli_connect($db_host, $db_user, $db_password, $db_name);
    if (mysqli_connect_errno()) {
        error_log('Error de conexión a base de datos principal: ' . mysqli_connect_error());
        $conexion = null;
    } else {

        if ($type_timezone == '1') {
            $conexion->query("SET time_zone = 'America/Guatemala'");
        }
    }
    
    /**
     * @deprecated Variable $general está deprecada. Usar Eloquent DB::connection('general') o DatabaseAdapter->openConnection(2) en su lugar.
     */
    $general = mysqli_connect($db_host, $db_user, $db_password, $db_name_general);
    if (mysqli_connect_errno()) {
        error_log('Error de conexión a base de datos general: ' . mysqli_connect_error());
        $general = null;
    } else {

        if ($type_timezone == '1') {
            $general->query("SET time_zone = 'America/Guatemala'");
        }
    }
    
} catch (Exception $e) {
    Log::error('Error de conexión a base de datos: ' . $e->getMessage());
    // error_log('Excepción en conexión a base de datos: ' . $e->getMessage());
    $conexion = null;
    $general = null;
    // $virtual = null;
}
