<?php
include_once __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
// include __DIR__ . '/../../../src/funcphp/func_gen.php';

include __DIR__ . '/../../../src/funcphp/fun_ppg.php';

use Micro\Helpers\Log;
use Creditos\Utilidades\CalculoPagosDiarios;
use Creditos\Utilidades\CalculoPagosQuincenales;
// use Creditos\Utilidades\CalculoPagosMensuales;
use Creditos\Utilidades\CalculoPagosSemanales;
use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

setAppTimezone();

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$montoCredito = $_POST['montoCredito'];
$idProducto = $_POST['idProducto'];
$tipoAmortizacion = $_POST['tipoAmortizacion'];
$noPeriodo = $_POST['noPeriodo'];
$tipoPeriodo = $_POST['tipoPeriodo'];
$fechaDesembolso = $_POST['fechaDesembolso'];
$fechaPago = $_POST['fechaPago'];
$peripagcap = $_POST['peripagcap'];
$afectaInteres = $_POST['afectaInteres'];

$showmensaje = false;
try {
    $database->openConnection();
    $selectColumns = ['id', 'nombre', 'descripcion', 'monto_maximo', 'tasa_interes', 'dias_calculo', 'id_tipo_periodo', 'diasCorridos'];
    $producto = $database->selectColumns('cre_productos', $selectColumns, "id=?", [$idProducto]);
    if (empty($producto)) {
        $showmensaje = true;
        throw new Exception("No se encontro");
    }

    $gastosCuota = $utilidadesCreditos->gastosEnCuota($producto[0]['id'], $database);

    $diasLaborales = $utilidadesCreditos->dias_habiles($database, $producto[0]['id']);

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
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
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}


$interes = $producto[0]['tasa_interes'];
$ntipperc = $tipoPeriodo;

$interes_calc = new profit_cmpst();
$NtipPerC2 = $interes_calc->ntipPerc($ntipperc);

$rate  = (($interes / 100) / $NtipPerC2[1]);
$rateanual = $interes / 100;
$future_value = 0;
$beginning = false;

$daysdif = diferenciaEnDias($fechaDesembolso, $fechaPago);

$postRedistribucion = true;

if ($ntipperc == "1D" && $tipoAmortizacion == "Flat") {
    $fchspgs = calculo_fechas_por_nocuota2($fechaPago, $noPeriodo, 1, $diasLaborales);
    $creditoDiario = new CalculoPagosDiarios($montoCredito, $producto[0]['tasa_interes'], $noPeriodo, $producto[0]['id_tipo_periodo'], $producto[0]['dias_calculo'],$gastosCuota,$fchspgs);
    $amortiza = $creditoDiario->calculoMontosDiario();
    $gastosDistribuidos = $amortiza;
    $postRedistribucion = false;
} else if ($ntipperc == "7D" && $tipoAmortizacion == "Flat") {
    $fchspgs = calculo_fechas_por_nocuota2($fechaPago, $noPeriodo, 7, $diasLaborales);
    $creditoSemanal = new CalculoPagosSemanales($montoCredito, $producto[0]['tasa_interes'], $noPeriodo, $producto[0]['id_tipo_periodo'], $producto[0]['diasCorridos'], $daysdif, $gastosCuota, $fchspgs);
    $amortiza = $creditoSemanal->generarTablaAmortizacion();
    $gastosDistribuidos = $amortiza;
    $postRedistribucion = false;
} else if ($ntipperc == "15D" && $tipoAmortizacion == "Flat") {
    $fchspgs = $interes_calc->calcudate2($fechaPago, $noPeriodo, $NtipPerC2[2], $diasLaborales);
    $creditoQuincenal = new CalculoPagosQuincenales($montoCredito, $producto[0]['tasa_interes'], $noPeriodo, $producto[0]['id_tipo_periodo'],$gastosCuota,$fchspgs);
    $amortiza = $creditoQuincenal->generarTablaAmortizacion();
    $interesShow = $creditoQuincenal->getTasaMensual();
    $gastosDistribuidos = $amortiza;
    $postRedistribucion = false;
} else if (in_array($info[0]["id_cop"], [15, 27, 29])) {

    $fchspgs = $interes_calc->calcudate2($fechaPago, $noPeriodo, $NtipPerC2[2], $diasLaborales);
    $amortiza = amortizaespecialadg($tipoAmortizacion, $rate, $montoCredito, $noPeriodo, $future_value, $beginning, $daysdif, $ntipperc, $peripagcap, $afectaInteres);
} else {

    $fchspgs = $interes_calc->calcudate2($fechaPago, $noPeriodo, $NtipPerC2[2], $diasLaborales);
    $amortiza = amortiza($tipoAmortizacion, $rate, $montoCredito, $noPeriodo, $future_value, $beginning, $fchspgs[1], $fechaDesembolso, $producto[0]['dias_calculo'], $producto[0]['diasCorridos'], $ntipperc, $peripagcap, $afectaInteres);
}

if ($postRedistribucion) {
    $gastosDistribuidos = $utilidadesCreditos->distribucionGastosPorCuota($amortiza, $fchspgs, $gastosCuota, $montoCredito);
}

$array_datos = array();

foreach ($gastosDistribuidos as $key => $row) {
    $otros = (!empty($row['gastos'])) ? array_sum(array_column($row['gastos'], 'monto')) : 0;

    $array_datos[$key] = array(
        "0" => $key + 1,
        "1" => setdatefrench($row['fecha']),
        "2" => moneda($row['capital']),
        "3" => moneda($row['interes']),
        "4" => moneda($otros),
        "5" => moneda($row['cuota']),
        "6" => moneda($row['saldo']),
    );
}

$results = array(
    "sEcho" => 1,
    "iTotalRecords" => count($array_datos),
    "iTotalDisplayRecords" => count($array_datos),
    "aaData" => $array_datos
);
echo json_encode($results);
