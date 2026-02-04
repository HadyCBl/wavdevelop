<?php

//VERIFICAR SI TIENE EL PARAMETRO EN LA URL
if (!isset($_GET['test']) || $_GET['test'] !== 'soygay') {
    echo "🖕";
    return false;
}

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
require_once(__DIR__ . '/../../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

$saldo_mora = $_ENV['SALDO_MORA'] ?? 0;

use App\DatabaseAdapter;
use Micro\Helpers\Log;

try {

    $database = new DatabaseAdapter();

    $database->openConnection();

    $creditos = $database->selectColumns("cremcre_meta", ["CCODCTA"], "CESTADO = 'F'");

    if (!empty($creditos)) {
        foreach ($creditos as $credito) {
            echo '<br> +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++';
            echo '<br> +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++';
            echo '<br> INICIANDO ACTUALIZACIONES PARA LA CUENTA: ' . $credito['CCODCTA'] . '<br>';
            $cuenta = $credito['CCODCTA'];
            $database->executeQuery("CALL update_ppg_account(?)", [$cuenta]);
            echo '✔ ACTUALIZACION DE PAGOS LISTO :)<br>';

            $database->executeQuery("SELECT calculo_mora(?)", [$cuenta]);
            echo '✔ MORA LISTO :)<br>';

            $hoy = date("Y-m-d");
            $database->executeQuery("CALL verificacion_kardex(?, ?)", [$cuenta, $hoy]);
            echo '✔ KARDEX LISTO :)<br>';

            if ($saldo_mora == 1) {
                $database->executeQuery("SELECT calculo_saldo_mora(?)", [$cuenta]);
                echo '✔ SALDO DE MORA ACTUALIZADO <br>';
            }
        }
    }
} catch (Exception $e) {
    echo "Error " . $e->getMessage();
    Log::error("Error en el proceso: " . $e->getMessage());
    exit;
} finally {
    $database->closeConnection();
}
