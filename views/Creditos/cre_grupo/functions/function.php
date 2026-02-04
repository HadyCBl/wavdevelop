<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 0]);
    return;
}

include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

require __DIR__ . '/../../../../fpdf/fpdf.php';
require __DIR__ . '/../../../../vendor/autoload.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';

use Creditos\Utilidades\CalculoPagosDiarios;
use Creditos\Utilidades\CalculoPagosSemanales;
use Creditos\Utilidades\CalculoPagosQuincenales;
use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

date_default_timezone_set('America/Guatemala');

$hoy2 = date("Y-m-d H:i:s");
$idusuario = $_SESSION["id"];

$condi = (isset($input["condi"])) ? $input["condi"] : ((isset($_POST["condi"]) ? $_POST["condi"] : 0));


switch ($condi) {
    case 'calculos':
        if (isset($_POST['datosFinales'])) {
            $datosFinales = json_decode($_POST['datosFinales'], true);

            if ($datosFinales !== null) {
                $datosGrupo = $datosFinales['datosGrupo'];
                $tipoAmortizacion = $datosFinales['tipoCredito'];
                $tipoPeriodo = $datosFinales['tipoPeriodo'];
                $fechaPago = $datosFinales['fechaCuota'];
                $noPeriodo = $datosFinales['nroCuotas'];
                $fechaDesembolso = $datosFinales['fechaDesembolso'];
                $idProducto = $datosFinales['codigoProducto'];
                $interesAsignado = $datosFinales['interesAsignado'];

                $totalMonto = 0;
                foreach ($datosGrupo as $persona) {
                    $monto = $persona['monto'];
                    $totalMonto += $monto;
                }
                $montoCredito = $totalMonto;
                $peripagcap = 1;
                $afectaInteres = 1;

                $showmensaje = false;
                try {
                    $database->openConnection();
                    $selectColumns = ['id', 'nombre', 'descripcion', 'monto_maximo', 'tasa_interes', 'dias_calculo', 'id_tipo_periodo', 'diasCorridos'];
                    $producto = $database->selectColumns('cre_productos', $selectColumns, "cod_producto=?", [$idProducto]);
                    if (empty($producto)) {
                        $showmensaje = true;
                        throw new Exception("No se encontr");
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
                    // $opResult = array('status' => 0, 'mensaje' => $mensaje);
                    $results = array(
                        "sEcho" => 0,
                        "iTotalRecords" => 0,
                        "iTotalDisplayRecords" => 0,
                        "aaData" => [],
                        "mensaje" => $mensaje
                    );
                    echo json_encode($results);
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

                //PARA CREDITOS DIARIOS DE TIPO FLAT
                $postRedistribucion = true;
                if ($ntipperc == "1D" && $tipoAmortizacion == "Flat") {
                    $fchspgs = calculo_fechas_por_nocuota2($fechaPago, $noPeriodo, 1, $diasLaborales);
                    $creditoDiario = new CalculoPagosDiarios($montoCredito, $producto[0]['tasa_interes'], $noPeriodo, $producto[0]['id_tipo_periodo'], $producto[0]['dias_calculo'], $gastosCuota, $fchspgs);
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

                function printpdf($fchspgs, $amortiza, $montoCredito, $planPago, $gastosCuota = [], $info = [])
                {
                    class PDF extends FPDF
                    {
                        private $montoCredito;
                        private $info;

                        function __construct($montoCredito, $info)
                        {
                            parent::__construct();
                            $this->montoCredito = $montoCredito;
                            $this->info = $info;
                        }

                        // Cabecera de página
                        function Header()
                        {
                            $hoy = date("Y-m-d H:i:s");
                            $this->SetFont('Arial', 'B', 8);

                            // Ruta del logo
                            $logoMicro = '../../../../includes/img/logomicro.png'; // Ruta fija para el logo

                            // Mostrar el logo a la izquierda
                            $this->Cell(0, 0, '', 0, 1, 'L', $this->Image($logoMicro, 20, 12, 19));

                            $nombreCompleto = isset($this->info[0]['nomb_comple']) ? decode_utf8($this->info[0]['nomb_comple']) : 'Información no disponible';
                            $nomb_cor = isset($this->info[0]["nomb_cor"]) ? decode_utf8($this->info[0]["nomb_cor"]) : "Información no disponible";
                            $muni_lug = isset($this->info[0]["muni_lug"]) ? $this->info[0]["muni_lug"] : "Información no disponible";
                            $email = isset($this->info[0]["emai"]) ? 'Email: ' . $this->info[0]["emai"] : "Email no disponible";
                            $tel = (isset($this->info[0]["tel_1"]) && isset($this->info[0]["tel_2"])) ? 'Tel: ' . $this->info[0]["tel_1"] . " Y " . $this->info[0]["tel_2"] : "Teléfono no disponible";
                            $nit = isset($this->info[0]["nit"]) ? 'NIT: ' . $this->info[0]["nit"] : "NIT no disponible";
                            $nom_agencia = isset($this->info[0]["nom_agencia"]) ? mb_strtoupper($this->info[0]["nom_agencia"], 'utf-8') : "Agencia no disponible";

                            // Imprimir los valores en el PDF
                            $this->Cell(190, 3, $nombreCompleto, 0, 1, 'C');
                            $this->Cell(190, 3, $nomb_cor, 0, 1, 'C');
                            $this->Cell(190, 3, $muni_lug, 0, 1, 'C');
                            $this->Cell(190, 3, $email, 0, 1, 'C');
                            $this->Cell(190, 3, $tel, 0, 1, 'C');
                            $this->Cell(190, 3, $nit, 0, 1, 'C');
                            $this->Cell(0, 3, $nom_agencia, 'B', 1, 'C');



                            $this->SetFont('Arial', '', 7);
                            $this->SetXY(-30, 5);
                            $this->Cell(10, 2, $hoy, 0, 1, 'L');
                            $this->SetXY(-25, 8);
                            $this->Ln(25);

                            $this->SetFont('Arial', 'B', 12);
                            $this->Cell(50, 5, 'Plan de Pago Grupal', 0, 1, 'L');
                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(0, 5, decode_utf8('Monto del crédito: ') . number_format($this->montoCredito, 2), 0, 0, 'L');
                            $this->Ln(10);
                        }

                        // Pie de página
                        function Footer()
                        {
                            $this->SetY(-15);
                            $this->SetFont('Arial', 'I', 8);
                            $this->Cell(0, 10, decode_utf8('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
                        }

                        function TableData($fchspgs, $amortiza, $montoCredito, $gastosCuota, $planPago)
                        {
                            $this->SetFont('Arial', '', 9);

                            // Ancho de cada columna
                            $w = array(14.55, 24.25, 24.25, 24.25, 24.25, 24.25, 24.25);

                            $this->SetDrawColor(255, 255, 255);

                            $this->SetFillColor(60, 103, 171);
                            $this->SetTextColor(255, 255, 255);
                            $this->Cell($w[0], 9, '#', 1, 0, 'C', true);
                            $this->Cell($w[1], 9, 'Fecha', 1, 0, 'C', true);
                            $this->Cell($w[2], 9, 'Capital', 1, 0, 'C', true);
                            $this->Cell($w[3], 9, decode_utf8('Interés'), 1, 0, 'C', true);
                            $this->Cell($w[4], 9, 'Otros', 1, 0, 'C', true);
                            $this->Cell($w[5], 9, 'Cuota', 1, 0, 'C', true);
                            $this->Cell($w[6], 9, 'Saldo', 1, 0, 'C', true);
                            $this->SetTextColor(0, 0, 0);
                            $this->Ln();

                            $this->SetFillColor(230, 230, 230);

                            $fill = false;

                            foreach ($planPago as $key => $row) {
                                $otros = (!empty($row['gastos'])) ? array_sum(array_column($row['gastos'], 'monto')) : 0;

                                $this->Cell($w[0], 9, ($key + 1), 1, 0, 'C', $fill);
                                $this->Cell($w[1], 9, setdatefrench($row['fecha']), 1, 0, 'C', $fill);
                                $this->Cell($w[2], 9, moneda($row['capital']), 1, 0, 'R', $fill);
                                $this->Cell($w[3], 9, moneda($row['interes']), 1, 0, 'R', $fill);
                                $this->Cell($w[4], 9, moneda($otros), 1, 0, 'R', $fill);
                                $this->Cell($w[5], 9, moneda($row['cuota']), 1, 0, 'R', $fill);
                                $this->Cell($w[6], 9, moneda($row['saldo']), 1, 0, 'R', $fill);
                                $this->Ln();
                                $fill = !$fill;
                            }
                        }
                    }

                    $pdf = new PDF($montoCredito, $info);
                    $pdf->AliasNbPages();
                    $pdf->AddPage();
                    $pdf->TableData($fchspgs, $amortiza, $montoCredito, $gastosCuota, $planPago);

                    $pdf->Ln(10);
                    $pdf->SetFont('Arial', 'I', 8);
                    $pdf->MultiCell(0, 8, decode_utf8('Aviso Importante: La información mostrada es solo una simulación basada en datos generales, como el tipo de crédito, el monto y el plazo. Estos valores pueden cambiar sin previo aviso dependiendo de las condiciones específicas de cada caso. La confirmación de los detalles, incluyendo el plan de pagos, se hará durante el proceso de evaluación y formalización, antes de que se realice el desembolso.'), 0, 'J');

                    ob_start();
                    $pdf->Output();
                    $pdfData = ob_get_contents();
                    ob_end_clean();

                    $opResult = array(
                        'status' => 'success',
                        'message' => 'Reporte generado correctamente',
                        'namefile' => "PlanPago",
                        'tipo' => "pdf",
                        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
                    );
                    echo json_encode($opResult);
                }
                $tipo = "pdf";
                switch ($tipo) {
                    case 'xlsx':
                        // printxls($result, [$texto_reporte, $_SESSION['id'], $hoy]);
                        break;
                    case 'pdf':
                        printpdf($fchspgs, $amortiza, $montoCredito, $gastosDistribuidos, $gastosCuota, $info);
                        break;
                }
                /*
                ob_clean();
                header('Content-Type: application/json');
                $response = [
                    'status' => 'success',
                    'message' => 'Datos procesados correctamente',
                    'totalMonto' => $totalMonto,
                ];
                
                // Convertir el array a JSON
                echo json_encode($response);
                exit;
        */
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo decodificar los datos JSON']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos']);
        }
        break;
    case 'calculosindi':
        if (isset($_POST['datosFinales'])) {
            $datosFinales = json_decode($_POST['datosFinales'], true);

            if ($datosFinales !== null) {
                $nombre = $datosFinales['nombre'];
                $montoCredito = $datosFinales['monto'];
                $tipoAmortizacion = $datosFinales['tipoCredito'];
                $tipoPeriodo = $datosFinales['tipoPeriodo'];
                $fechaPago = $datosFinales['fechaCuota'];
                $noPeriodo = $datosFinales['nroCuotas'];
                $fechaDesembolso = $datosFinales['fechaDesembolso'];
                $idProducto = $datosFinales['codigoProducto'];
                $interesAsignado = $datosFinales['interesAsignado'];

                $peripagcap = 1;
                $afectaInteres = 1;

                $showmensaje = false;
                try {
                    $database->openConnection();
                    $selectColumns = ['id', 'nombre', 'descripcion', 'monto_maximo', 'tasa_interes', 'dias_calculo', 'id_tipo_periodo', 'diasCorridos'];
                    $producto = $database->selectColumns('cre_productos', $selectColumns, "cod_producto=?", [$idProducto]);
                    if (empty($producto)) {
                        $showmensaje = true;
                        throw new Exception("No se encontr");
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
                    // $opResult = array('status' => 0, 'mensaje' => $mensaje);
                    $results = array(
                        "sEcho" => 0,
                        "iTotalRecords" => 0,
                        "iTotalDisplayRecords" => 0,
                        "aaData" => [],
                        "mensaje" => $mensaje
                    );
                    echo json_encode($results);
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

                //PARA CREDITOS DIARIOS DE TIPO FLAT
                $postRedistribucion = true;
                if ($ntipperc == "1D" && $tipoAmortizacion == "Flat") {
                    $fchspgs = calculo_fechas_por_nocuota2($fechaPago, $noPeriodo, 1, $diasLaborales);
                    $creditoDiario = new CalculoPagosDiarios($montoCredito, $producto[0]['tasa_interes'], $noPeriodo, $producto[0]['id_tipo_periodo'], $producto[0]['dias_calculo'], $gastosCuota, $fchspgs);
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

                function printpdf($fchspgs, $amortiza, $montoCredito, $gastosCuota, $info, $nombre, $planPago)
                {
                    class PDF extends FPDF
                    {
                        private $montoCredito;
                        private $nombre;
                        private $info;

                        function __construct($montoCredito, $info, $nombre)
                        {
                            parent::__construct();
                            $this->montoCredito = $montoCredito;
                            $this->info = $info;
                            $this->nombre = $nombre;
                        }

                        // Cabecera de página
                        function Header()
                        {
                            $hoy = date("Y-m-d H:i:s");
                            $this->SetFont('Arial', 'B', 8);

                            // Ruta del logo
                            $logoMicro = '../../../../includes/img/logomicro.png'; // Ruta fija para el logo

                            // Mostrar el logo a la izquierda
                            $this->Cell(0, 0, '', 0, 1, 'L', $this->Image($logoMicro, 20, 12, 19));

                            $nombreCompleto = isset($this->info[0]['nomb_comple']) ? decode_utf8($this->info[0]['nomb_comple']) : 'Información no disponible';
                            $nomb_cor = isset($this->info[0]["nomb_cor"]) ? decode_utf8($this->info[0]["nomb_cor"]) : "Información no disponible";
                            $muni_lug = isset($this->info[0]["muni_lug"]) ? $this->info[0]["muni_lug"] : "Información no disponible";
                            $email = isset($this->info[0]["emai"]) ? 'Email: ' . $this->info[0]["emai"] : "Email no disponible";
                            $tel = (isset($this->info[0]["tel_1"]) && isset($this->info[0]["tel_2"])) ? 'Tel: ' . $this->info[0]["tel_1"] . " Y " . $this->info[0]["tel_2"] : "Teléfono no disponible";
                            $nit = isset($this->info[0]["nit"]) ? 'NIT: ' . $this->info[0]["nit"] : "NIT no disponible";
                            $nom_agencia = isset($this->info[0]["nom_agencia"]) ? mb_strtoupper($this->info[0]["nom_agencia"], 'utf-8') : "Agencia no disponible";

                            // Imprimir los valores en el PDF
                            $this->Cell(190, 3, $nombreCompleto, 0, 1, 'C');
                            $this->Cell(190, 3, $nomb_cor, 0, 1, 'C');
                            $this->Cell(190, 3, $muni_lug, 0, 1, 'C');
                            $this->Cell(190, 3, $email, 0, 1, 'C');
                            $this->Cell(190, 3, $tel, 0, 1, 'C');
                            $this->Cell(190, 3, $nit, 0, 1, 'C');
                            $this->Cell(0, 3, $nom_agencia, 'B', 1, 'C');



                            $this->SetFont('Arial', '', 7);
                            $this->SetXY(-30, 5);
                            $this->Cell(10, 2, $hoy, 0, 1, 'L');
                            $this->SetXY(-25, 8);
                            $this->Ln(25);

                            $this->SetFont('Arial', 'B', 12);
                            $this->Cell(50, 5, 'Plan de Pago Individual', 0, 1, 'C');
                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(0, 5, 'Cliente : ' . decode_utf8(mb_strtoupper($this->nombre, 'utf-8')), 0, 1, 'L');
                            $this->Cell(0, 5, decode_utf8('Monto del crédito: ') . number_format($this->montoCredito, 2), 0, 1, 'L');

                            $this->Ln(10);
                        }

                        // Pie de página
                        function Footer()
                        {
                            $this->SetY(-15);
                            $this->SetFont('Arial', 'I', 8);
                            $this->Cell(0, 10, decode_utf8('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
                        }

                        function TableData($fchspgs, $amortiza, $montoCredito, $gastosCuota, $planPago)
                        {
                            $this->SetFont('Arial', '', 9);

                            // Ancho de cada columna
                            $w = array(14.55, 24.25, 24.25, 24.25, 24.25, 24.25, 24.25);

                            $this->SetDrawColor(255, 255, 255);

                            $this->SetFillColor(60, 103, 171);
                            $this->SetTextColor(255, 255, 255);
                            $this->Cell($w[0], 9, '#', 1, 0, 'C', true);
                            $this->Cell($w[1], 9, 'Fecha', 1, 0, 'C', true);
                            $this->Cell($w[2], 9, 'Capital', 1, 0, 'C', true);
                            $this->Cell($w[3], 9, decode_utf8('Interés'), 1, 0, 'C', true);
                            $this->Cell($w[4], 9, 'Otros', 1, 0, 'C', true);
                            $this->Cell($w[5], 9, 'Cuota', 1, 0, 'C', true);
                            $this->Cell($w[6], 9, 'Saldo', 1, 0, 'C', true);
                            $this->SetTextColor(0, 0, 0);
                            $this->Ln();

                            $this->SetFillColor(230, 230, 230);

                            $fill = false;

                            foreach ($planPago as $key => $row) {
                                $otros = (!empty($row['gastos'])) ? array_sum(array_column($row['gastos'], 'monto')) : 0;

                                $this->Cell($w[0], 9, ($key + 1), 1, 0, 'C', $fill);
                                $this->Cell($w[1], 9, setdatefrench($row['fecha']), 1, 0, 'C', $fill);
                                $this->Cell($w[2], 9, moneda($row['capital']), 1, 0, 'R', $fill);
                                $this->Cell($w[3], 9, moneda($row['interes']), 1, 0, 'R', $fill);
                                $this->Cell($w[4], 9, moneda($otros), 1, 0, 'R', $fill);
                                $this->Cell($w[5], 9, moneda($row['cuota']), 1, 0, 'R', $fill);
                                $this->Cell($w[6], 9, moneda($row['saldo']), 1, 0, 'R', $fill);
                                $this->Ln();
                                $fill = !$fill;
                            }
                        }
                    }

                    $pdf = new PDF($montoCredito, $info, $nombre);
                    $pdf->AliasNbPages();
                    $pdf->AddPage();
                    $pdf->TableData($fchspgs, $amortiza, $montoCredito, $gastosCuota, $planPago);

                    $pdf->Ln(10);
                    $pdf->SetFont('Arial', 'I', 8);
                    $pdf->MultiCell(0, 8, decode_utf8('Aviso Importante: La información mostrada es solo una simulación basada en datos generales, como el tipo de crédito, el monto y el plazo. Estos valores pueden cambiar sin previo aviso dependiendo de las condiciones específicas de cada caso. La confirmación de los detalles, incluyendo el plan de pagos, se hará durante el proceso de evaluación y formalización, antes de que se realice el desembolso.'), 0, 'J');

                    ob_start();
                    $pdf->Output();
                    $pdfData = ob_get_contents();
                    ob_end_clean();

                    $opResult = array(
                        'status' => 'success',
                        'message' => 'Reporte generado correctamente',
                        'namefile' => "PlanPago",
                        'tipo' => "pdf",
                        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
                    );
                    echo json_encode($opResult);
                }
                $tipo = "pdf";
                switch ($tipo) {
                    case 'xlsx':
                        // printxls($result, [$texto_reporte, $_SESSION['id'], $hoy]);
                        break;
                    case 'pdf':
                        printpdf($fchspgs, $amortiza, $montoCredito, $gastosCuota, $info, $nombre, $gastosDistribuidos);
                        break;
                }
                /*
                ob_clean();
                header('Content-Type: application/json');
                $response = [
                    'status' => 'success',
                    'message' => 'Datos procesados correctamente',
                    'totalMonto' => $totalMonto,
                ];
                
                // Convertir el array a JSON
                echo json_encode($response);
                exit;
        */
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo decodificar los datos JSON']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos']);
        }
        break;
}
