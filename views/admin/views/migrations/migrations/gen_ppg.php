<?php

/**
 * Este archivo maneja la recepción de un código de migración y realiza una consulta en la base de datos
 * para verificar la existencia de registros asociados a dicho código. Si se encuentran registros, se 
 * almacenan en la sesión y se devuelve una respuesta de éxito en formato JSON. En caso de error, se 
 * devuelve una respuesta de error en formato JSON.
 * 
 * Dependencias:
 * - Autoload de Composer
 * - Configuración de la base de datos
 * - Funciones personalizadas (fun_ppg.php, PaymentManager.php)
 * 
 * Variables de sesión:
 * - idusuario: ID del usuario almacenado en la sesión
 * - codeMigration: Código de migración almacenado en la sesión
 * - tipopp: Tipo de migración almacenado en la sesión (opcional) [1 commit, 0 rollback]
 * 
 * Funciones:
 * - sendSSEMessage($event, $data): Envía un mensaje SSE (Server-Sent Events) al cliente
 * 
 * Flujo principal:
 * - Verifica si la solicitud es de tipo POST y contiene el código de migración
 * - Establece una conexión a la base de datos
 * - Realiza una consulta para verificar la existencia de registros asociados al código de migración
 * - Si se encuentran registros, se almacenan en la sesión y se devuelve una respuesta de éxito en formato JSON
 * - En caso de error, se devuelve una respuesta de error en formato JSON
 * - Cierra la conexión a la base de datos
 * 
 * @package MicrosystemPlus
 * @subpackage Migrations
 * @author [beneq]
 * @version 1.0
 * @since 2024
 */
ob_start();
session_start();
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3000');

// require __DIR__ . '/../../../../../vendor/autoload.php';
// require_once __DIR__ . '/../../../../../includes/Config/database.php';
// // require_once __DIR__ . '/../../../../../src/funcphp/func_gen.php';
// // include __DIR__ . '/../../../../../src/funcphp/valida.php';
// include __DIR__ . '/../../../../../src/funcphp/fun_ppg.php';
// include __DIR__ . '/../../../../../src/funcphp/creditos/PaymentManager.php';

// use Creditos\Utilidades\PaymentManager;
include __DIR__ . '/../../../../../includes/Config/database.php';
require __DIR__ . '/../../../../../vendor/autoload.php';
include __DIR__ . '/../../../../../src/funcphp/fun_ppg.php';

use Creditos\Utilidades\CalculoPagosDiarios;
use Creditos\Utilidades\CalculoPagosQuincenales;
// use Creditos\Utilidades\CalculoPagosMensuales;
use Creditos\Utilidades\CalculoPagosSemanales;
use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

function sendSSEMessage($event, $data)
{
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Manejar la recepción del código de migración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrationCode'])) {
    $migrationCode = $_POST['migrationCode'];
    $tipo = $_POST['tipo'] ?? 0;

    try {
        // Establece conexión a la base de datos
        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
        $database->openConnection();
        $query = "SELECT CCODCTA FROM cremcre_meta WHERE CCodAho= ?";
        $datos = $database->getAllResults($query, [$migrationCode]);

        if (empty($datos)) {
            throw new Exception("No se encontraron registros para el código de migración proporcionado.");
        }
        $_SESSION['codeMigration'] = $migrationCode;
        if (isset($_POST['tipo'])) {
            $_SESSION['tipopp'] = $_POST['tipo'];
        }
        echo json_encode(['status' => 'success', 'message' => 'Codigos de cuenta encontrados']);
    } catch (Exception $e) {
        // sendSSEMessage('error', ['message' => $e->getMessage()]);
        echo json_encode(['status' => 'error', 'message' => 'Error al procesar la solicitud' . $e->getMessage()]);
    } finally {
        $database->closeConnection();
    }
    exit;
}

/**
 * Manejar SSE (Server-Sent Events) para la migración de datos de créditos.
 * 
 * Este script escucha solicitudes SSE y procesa la migración de datos de créditos,
 * generando planes de pago y almacenándolos en la base de datos.
 * 
 * Parámetros de entrada:
 * - $_GET['listen']: Si está presente, inicia el proceso de SSE.
 * - $_SESSION['codeMigration']: Código de migración necesario para procesar los créditos.
 * - $_SESSION['tipopp']: Tipo de procesamiento (1 para commit, 0 para rollback).
 * 
 * Proceso:
 * 1. Inicia la conexión SSE y envía un mensaje de inicio.
 * 2. Verifica la existencia del código de migración en la sesión.
 * 3. Abre una conexión a la base de datos.
 * 4. Selecciona los créditos a migrar basados en el código de migración.
 * 5. Inicia una transacción en la base de datos.
 * 6. Procesa cada crédito, validando datos y generando planes de pago.
 * 7. Inserta los planes de pago en la base de datos.
 * 8. Envía mensajes de progreso durante el procesamiento.
 * 9. Realiza commit o rollback de la transacción según el tipo de procesamiento.
 * 10. Envía un mensaje de finalización o error según corresponda.
 * 11. Cierra la conexión a la base de datos.
 * 
 * Excepciones:
 * - Si ocurre un error durante el procesamiento, se realiza un rollback y se envía un mensaje de error.
 * 
 * @throws Exception Si ocurre un error durante el procesamiento de los créditos.
 */

// Manejar SSE (Server-Sent Events)
if (isset($_GET['listen'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    sendSSEMessage('progress', [
        'row' => 0,
        'total' => 0,
        'message' => "INICIO DE PROCESO"
    ]);
    if (!isset($_SESSION['codeMigration'])) {
        sendSSEMessage('error', ['message' => 'No se ha cargado ningún codigo de migracion']);
        exit;
    }

    $utilidadesCreditos = new PaymentManager();
    $codeMigration = $_SESSION['codeMigration'];
    $tipopp = $_SESSION['tipopp'] ?? 0;
    // $tipopp =  0;

    try {

        $datos = [];

        $identificadormigracion = generarCodigoAleatorio();

        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

        $database->openConnection();

        $creditos = $database->selectColumns('cremcre_meta', ['CCODCTA', 'CCODPRD', 'NtipPerC', 'Dictamen', 'TipDocDes', 'DfecPago', 'peripagcap', 'afectaInteres', 'CtipCre', 'DFecDsbls', 'NIntApro', 'noPeriodo', 'MonSug', 'NCapDes', 'DFecVig'], "CCodAho=?", [$codeMigration]);
        $database->beginTransaction();

        sendSSEMessage('progress', [
            'row' => 0,
            'total' => 0,
            'message' => "INICIANDO, CODIGO DE MIGRACION: $identificadormigracion"
        ]);

        $totalRows = count($creditos);
        $conterrors = 0;
        foreach ($creditos as $key => $value) {
            if (is_null($value["DFecDsbls"]) || $value['DFecDsbls'] == '') {
                throw new Exception("$key No existe una fecha de desembolso [DFecDsbls]");
            }
            if ($value['NtipPerC'] == '') {
                throw new Exception("$key No existe un tipo de pago [NtipPerC]");
            }
            if (is_null($value["NIntApro"]) || $value['NIntApro'] == '') {
                throw new Exception("$key No existe una tasa de interes [NIntApro]");
            }
            if (is_null($value["CCODPRD"]) || $value['CCODPRD'] == '') {
                throw new Exception("$key No existe un codigo de producto [CCODPRD]");
            }
            if (is_null($value["CtipCre"]) || $value['CtipCre'] == '') {
                throw new Exception("$key No existe un tipo de amortizacion [CtipCre]");
            }

            $result = $database->selectColumns('Cre_ppg', ['ccodcta'], "ccodcta=?", [$value["CCODCTA"]]);
            if (!empty($result)) {
                // if (5 == 4) {
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $value['CCODCTA'] . " YA EXISTEN REGISTROS DE PLANES DE PAGO PARA ESTE CRÉDITO"
                ]);
                continue;
            }

            $producto = $database->selectColumns('cre_productos', ['dias_calculo', 'id_tipo_periodo', 'diasCorridos'], "id=?", [$value["CCODPRD"]]);

            if (empty($producto)) {
                $conterrors++;
                throw new Exception("$key NO SE ENCONTRO EL PRODUCTO ASOCIADO A ESTE CREDITO" . $value['CCODPRD']);
            }

            $gastosCuota = $utilidadesCreditos->gastosCuota($value['CCODPRD'], $value['CCODCTA'], $database);

            $diasLaborales = $utilidadesCreditos->dias_habiles($database, $value["CCODPRD"]);

            $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

            if (empty($info)) {
                $conterrors++;
                throw new Exception("Institucion asignada a la agencia no encontrada");
            }

            // $interes = $value["NIntApro"];
            // $clase = new profit_cmpst();
            // $ntipperc2 = $clase->ntipPerc($value["NtipPerC"]);
            // $rate  = (($interes / 100) / $ntipperc2[1]);
            // $rateanual = $interes / 100;
            // $future_value = 0;
            // $beginning = false;

            // $diasLaborales = $utilidadesCreditos->dias_habiles($database, $value['CCODPRD']);
            // $daysdif = diferenciaEnDias($value['DFecDsbls'], $value['DfecPago']);
            // $primeracuota = false;

            // //VERSION ACTUAL, SE CALCULA SOBRE LAS FECHAS CALCULADAS EN EL PLAN DE PAGO,
            // $fechas = $clase->calcudate2($value['DfecPago'], $value['noPeriodo'], $ntipperc2[2], $diasLaborales);

            // $amortiza = amortiza($value['CtipCre'], $rate, $value['MonSug'],  $value['noPeriodo'], $future_value, $beginning, $fechas[1], $value['DFecDsbls'], $producto[0]['dias_calculo'], $primeracuota, $value["NtipPerC"], $value['peripagcap'], $value['afectaInteres']);

            //+++++++++++++++++++++++++

            $interes = $value['NIntApro'];
            $ntipperc = $value['NtipPerC'];

            $interes_calc = new profit_cmpst();
            $NtipPerC2 = $interes_calc->ntipPerc($ntipperc);

            $rate  = (($interes / 100) / $NtipPerC2[1]);
            $rateanual = $interes / 100;
            $future_value = 0;
            $beginning = false;

            $daysdif = diferenciaEnDias($value['DFecDsbls'], $value['DfecPago']);
            $interesShow = $value['NIntApro'];

            $postRedistribucion = true;

            //PARA CREDITOS DIARIOS DE TIPO FLAT
            if ($ntipperc == "1D" && $value["CtipCre"] == "Flat") {
                $fechas = calculo_fechas_por_nocuota2($value['DfecPago'], $value['noPeriodo'], 1, $diasLaborales);
                $creditoDiario = new CalculoPagosDiarios($value['MonSug'], $value['NIntApro'], $value['noPeriodo'], $producto[0]['id_tipo_periodo'], $producto[0]['dias_calculo'], $gastosCuota, $fechas);
                $amortiza = $creditoDiario->calculoMontosDiario();
                $gastosDistribuidos = $amortiza;
                $postRedistribucion = false;
            } else if ($ntipperc == "7D" && $value["CtipCre"] == "Flat") {

                $fechas = calculo_fechas_por_nocuota2($value['DfecPago'], $value['noPeriodo'], 7, $diasLaborales);
                $creditoSemanal = new CalculoPagosSemanales($value['MonSug'], $value['NIntApro'], $value['noPeriodo'], $producto[0]['id_tipo_periodo'], $producto[0]['diasCorridos'], $daysdif, $gastosCuota, $fechas);
                $amortiza = $creditoSemanal->generarTablaAmortizacion();
                $interesShow = $creditoSemanal->getTasaMensual();
                $gastosDistribuidos = $amortiza;
                $postRedistribucion = false;
            } else if ($ntipperc == "15D" && $value["CtipCre"] == "Flat") {
                $fechas = $interes_calc->calcudate2($value['DfecPago'], $value['noPeriodo'], $NtipPerC2[2], $diasLaborales);
                $creditoQuincenal = new CalculoPagosQuincenales($value['MonSug'], $value['NIntApro'], $value['noPeriodo'], $producto[0]['id_tipo_periodo'],$gastosCuota, $fechas);
                $amortiza = $creditoQuincenal->generarTablaAmortizacion();
                $interesShow = $creditoQuincenal->getTasaMensual();
                $gastosDistribuidos = $amortiza;
                $postRedistribucion = false;
            } else if (in_array($info[0]["id_cop"], [15, 27, 29])) {

                $fechas = $interes_calc->calcudate2($value['DfecPago'], $value['noPeriodo'], $NtipPerC2[2], $diasLaborales);
                $amortiza = amortizaespecialadg($value['CtipCre'], $rate, $value['MonSug'], $value['noPeriodo'], $future_value, $beginning, $daysdif, $ntipperc, $value['peripagcap'], $value['afectaInteres']);
            } else {

                $fechas = $interes_calc->calcudate2($value['DfecPago'], $value['noPeriodo'], $NtipPerC2[2], $diasLaborales);
                $amortiza = amortiza($value['CtipCre'], $rate, $value['MonSug'], $value['noPeriodo'], $future_value, $beginning, $fechas[1], $value['DFecDsbls'], $producto[0]['dias_calculo'],  $producto[0]['diasCorridos'], $ntipperc, $value['peripagcap'], $value['afectaInteres']);
                $interesShow = $interesShow / 12;
            }

            if (empty($amortiza)) {
                throw new Exception("Error al calcular el plan de pago para el crédito " . $value['CCODCTA']);
            }

            if ($postRedistribucion) {
                $gastosDistribuidos = $utilidadesCreditos->distribucionGastosPorCuota($amortiza, $fechas, $gastosCuota);
            }

            sendSSEMessage('progress', [
                'row' => $key + 1,
                'total' => $totalRows,
                'message' => "+++ Procesando registro " . ($key + 1) . " de $totalRows => " . $value['CCODCTA'] . " " . ($value['MonSug'] ?? "")
            ]);

            //+++++++++++++
            foreach ($gastosDistribuidos as $currentkey => $currentrow) {

                $totalOtros = 0;
                $gastosCreppg = array();
                foreach ($currentrow['gastos'] as $currentkey2 => $gasto) {
                    $cantidadCuotas = count($fechas[1]);
                    $mongas = $gasto['monto'];

                    if ($mongas > 0) {
                        /**
                         * INSERTAR CADA GASTO DE CADA CUOTA EN LA TABLA CREPPG_DETALLE
                         */
                        $gastosCreppg[] = array(
                            "id" => $gasto['id'],
                            "gasto" => $mongas
                        );
                        $totalOtros += round($mongas, 2);
                    }
                }
                $creppgplan = array(
                    "ccodcta" => $value['CCODCTA'],
                    "dfecven" => $currentrow['fecha'],
                    "dfecpag" => $currentrow['fecha'],
                    "cestado" => "X",
                    "ctipope" => "0",
                    "cnrocuo" => ($currentkey + 1),
                    "SaldoCapital" => $currentrow['saldo'],
                    "nmorpag" => 0,
                    "ncappag" => $currentrow['capital'],
                    "nintpag" => $currentrow['interes'],
                    "AhoPrgPag" => 0,
                    "OtrosPagosPag" => $totalOtros,
                    "ccodusu" => $idusuario,
                    "dfecmod" => date("Y-m-d"),
                    "cflag" => "0",
                    "codigo" => "",
                    "creditosaf" => $identificadormigracion,
                    "saldo" => $currentrow['saldo'],
                    "nintmor" => 0,
                    "ncapita" => $currentrow['capital'],
                    "nintere" => $currentrow['interes'],
                    "NAhoProgra" => 0,
                    "OtrosPagos" => $totalOtros,
                );
                $idCreppg = $database->insert('Cre_ppg', $creppgplan);

                if (!empty($gastosCreppg)) {
                    //INSERTAR LOS GASTOS DE CADA CUOTA EN LA TABLA CREPPG_DETALLE
                    foreach ($gastosCreppg as $currentGasto) {
                        $detalleGasto = array(
                            // "id_creppg" => $idCreppg, //version anterior, vinculando el id de creppg
                            "id_creppg" => ($currentkey + 1), //version nueva, vinculando el nro de cuota
                            "id_tipo" => $currentGasto['id'],
                            "monto" => $currentGasto['gasto'],
                            "ccodcta" => $value['CCODCTA'],
                        );
                        $database->insert('creppg_detalle', $detalleGasto);
                    }
                }

                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "Procesando Cuota " . ($currentkey + 1) . "  => " . $value['CCODCTA']
                ]);
            }

            if (count($gastosDistribuidos) > 1) {
                $cuota = $gastosDistribuidos[1]['capital'] + $gastosDistribuidos[1]['interes'];
            } else {
                $cuota = $gastosDistribuidos[0]['capital'] + $gastosDistribuidos[0]['interes'];
            }
            $cremcre_meta = array(
                'DFecVen' => $fechas[1][count($fechas[1]) - 1],
                'NMonCuo' => $cuota,
                'DsmblsAproba' => date("Y-m-d H:i:s")
            );
            $database->update('cremcre_meta', $cremcre_meta, 'CCODCTA=?', [$value['CCODCTA']]);
        }

        if ($tipopp == 1) {
            $database->commit();
        } else {
            $database->rollback();
        }
        sendSSEMessage('done', ['message' => "Proceso concluido correctamente. Se insertaron " . ($totalRows - $conterrors) . " registros. Registros no insertados: $conterrors; de un total de $totalRows"]);
    } catch (Exception $e) {
        if (isset($database)) {
            $database->rollback();
        }
        sendSSEMessage('error', ['message' => $e->getMessage()]);
    } finally {
        $database->closeConnection();
    }

    exit;
}
