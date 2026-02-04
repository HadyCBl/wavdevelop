<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

require __DIR__ . '/../../../../fpdf/fpdf.php';
require __DIR__ . '/../../../../vendor/autoload.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';

use Creditos\Utilidades\CalculoPagosDiarios;
use Creditos\Utilidades\CalculoPagosQuincenales;
use Creditos\Utilidades\CalculoPagosSemanales;
use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

// date_default_timezone_set('America/Guatemala');

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$datos = $_POST["datosval"];
$archivo = $datos[3];
$codcre = $archivo[0];
$tipo = $_POST["tipo"];

if ($codcre == "") {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay ningun código de crédito']);
    return;
}


// spl_autoload_register(function ($class) {
//     error_log("Attempting to load: " . $class);
// });
$query = "SELECT CodCli,CCODCTA,short_name,CCODPRD,MonSug,NIntApro,DfecDsbls,DfecPago,noPeriodo,NtipPerC,
            CtipCre,pr.dias_calculo,peripagcap,afectaInteres,cr.descr tipoAmortizacion,pr.id_tipo_periodo,pr.diasCorridos
            FROM cremcre_meta crem
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli 
            INNER JOIN cre_productos pr on pr.id=crem.CCODPRD
            LEFT JOIN $db_name_general.tb_credito cr ON cr.abre = crem.CtipCre
            WHERE CCODCTA = ?";

$showmensaje = false;
try {
    $database->openConnection();
    $dataCredito = $database->getAllResults($query, [$codcre]);
    if (empty($dataCredito)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

    $gastosCuota = $utilidadesCreditos->gastosCuota($dataCredito[0]['CCODPRD'], $dataCredito[0]['CCODCTA'], $database);

    $diasLaborales = $utilidadesCreditos->dias_habiles($database, $dataCredito[0]['CCODPRD']);

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


$interes = $dataCredito[0]['NIntApro'];
$ntipperc = $dataCredito[0]['NtipPerC'];

$interes_calc = new profit_cmpst();
$NtipPerC2 = $interes_calc->ntipPerc($ntipperc);

$rate  = (($interes / 100) / $NtipPerC2[1]);
$rateanual = $interes / 100;
$future_value = 0;
$beginning = false;

$daysdif = diferenciaEnDias($dataCredito[0]['DfecDsbls'], $dataCredito[0]['DfecPago']);
$interesShow = $dataCredito[0]['NIntApro'];

$postRedistribucion = true;

//PARA CREDITOS DIARIOS DE TIPO FLAT
if ($ntipperc == "1D" && $dataCredito[0]["CtipCre"] == "Flat") {
    $fchspgs = calculo_fechas_por_nocuota2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], 1, $diasLaborales);
    $creditoDiario = new CalculoPagosDiarios($dataCredito[0]['MonSug'], $dataCredito[0]['NIntApro'], $dataCredito[0]['noPeriodo'], $dataCredito[0]['id_tipo_periodo'], $dataCredito[0]['dias_calculo'],$gastosCuota,$fchspgs);
    $amortiza = $creditoDiario->calculoMontosDiario();
    $gastosDistribuidos = $amortiza;
    $postRedistribucion = false;
} else if ($ntipperc == "7D" && $dataCredito[0]["CtipCre"] == "Flat") {

    $fchspgs = calculo_fechas_por_nocuota2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], 7, $diasLaborales);
    $creditoSemanal = new CalculoPagosSemanales($dataCredito[0]['MonSug'], $dataCredito[0]['NIntApro'], $dataCredito[0]['noPeriodo'], $dataCredito[0]['id_tipo_periodo'], $dataCredito[0]['diasCorridos'], $daysdif, $gastosCuota, $fchspgs);
    $amortiza = $creditoSemanal->generarTablaAmortizacion();
    $interesShow = $creditoSemanal->getTasaMensual();
    $gastosDistribuidos = $amortiza;
    $postRedistribucion = false;
} else if ($ntipperc == "15D" && $dataCredito[0]["CtipCre"] == "Flat") {
    $fchspgs = $interes_calc->calcudate2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], $NtipPerC2[2], $diasLaborales);
    $creditoQuincenal = new CalculoPagosQuincenales($dataCredito[0]['MonSug'], $dataCredito[0]['NIntApro'], $dataCredito[0]['noPeriodo'], $dataCredito[0]['id_tipo_periodo'],$gastosCuota,$fchspgs);
    $amortiza = $creditoQuincenal->generarTablaAmortizacion();
    $interesShow = $creditoQuincenal->getTasaMensual();
    $gastosDistribuidos = $amortiza;
    $postRedistribucion = false;
} else if (in_array($info[0]["id_cop"], [15, 27, 29])) {

    $fchspgs = $interes_calc->calcudate2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], $NtipPerC2[2], $diasLaborales);
    $amortiza = amortizaespecialadg($dataCredito[0]['CtipCre'], $rate, $dataCredito[0]['MonSug'], $dataCredito[0]['noPeriodo'], $future_value, $beginning, $daysdif, $ntipperc, $dataCredito[0]['peripagcap'], $dataCredito[0]['afectaInteres']);
} else {

    $fchspgs = $interes_calc->calcudate2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], $NtipPerC2[2], $diasLaborales);
    $amortiza = amortiza($dataCredito[0]['CtipCre'], $rate, $dataCredito[0]['MonSug'], $dataCredito[0]['noPeriodo'], $future_value, $beginning, $fchspgs[1], $dataCredito[0]['DfecDsbls'], $dataCredito[0]['dias_calculo'],  $dataCredito[0]['diasCorridos'], $ntipperc, $dataCredito[0]['peripagcap'], $dataCredito[0]['afectaInteres']);
    $interesShow = ($ntipperc == "15D") ? $interesShow : ($interesShow / 12);
}
if ($postRedistribucion) {
    $gastosDistribuidos = $utilidadesCreditos->distribucionGastosPorCuota($amortiza, $fchspgs, $gastosCuota);
}

switch ($tipo) {
    case 'xlsx';
        printxls($dataCredito, $titlereport, $archivo[0]);
        break;
    case 'pdf':
        printpdf($dataCredito, $info, $fchspgs, $amortiza, $gastosCuota, $interesShow, $gastosDistribuidos);
        break;
}

function printpdf($dataCredito, $info, $fechasPagos, $amortiza, $gastosCuota, $interesShow, $gastosDistribuidos)
{
    class PDF extends FPDF
    {
        public $CCODCTA, $DfecPago, $compl_name, $MonSug, $interes, $tipcre, $info, $porCuota;
        public function __construct($CCODCTA, $DfecPago, $compl_name, $MonSug, $interes, $tipcre, $info, $porCuota)
        {
            parent::__construct();
            $this->CCODCTA = $CCODCTA;
            $this->DfecPago = $DfecPago;
            $this->compl_name = $compl_name;
            $this->MonSug = $MonSug;
            $this->interes = $interes;
            $this->tipcre = $tipcre;
            $this->info = $info;
            $this->porCuota = $porCuota;
        }

        // Cabecera de página
        function Header()
        {
            $hoy = date("Y-m-d H:i:s");
            // Arial bold 15
            $this->SetFont('Arial', 'B', 8);

            $this->Cell(0, 0, '', 0, 1, 'L', $this->Image('../../../../' . $this->info[0]["log_img"], 20, 12, 25));
            // $this->Cell(0, 0, '', 0, 1, 'L', $this->Image('../../../../includes/img/logomicro.png', 20, 12, 19));
            //pruebas
            $this->Cell(190, 3, '' . decode_utf8($this->info[0]["nomb_comple"]), 0, 1, 'C');
            $this->Cell(190, 3, '' . decode_utf8($this->info[0]["nomb_cor"]), 0, 1, 'C');
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(190, 3, $this->info[0]["muni_lug"], 0, 1, 'C');
            $this->Cell(190, 3, 'Email:' . $this->info[0]["emai"], 0, 1, 'C');
            $this->Cell(190, 3, 'Tel:' . $this->info[0]["tel_1"] . " Y " . $this->info[0]["tel_2"], 0, 1, 'C');
            $this->Cell(190, 3, 'NIT:' . $this->info[0]["nit"], 0, 1, 'C');
            $this->Cell(0, 3, mb_strtoupper($this->info[0]["nom_agencia"], 'utf-8'), 'B', 1, 'C');
            $this->SetFont('Arial', '', 7);
            $this->SetXY(-30, 5);
            $this->Cell(10, 2, $hoy, 0, 1, 'L');
            $this->SetXY(-25, 8);
            $this->Ln(25);

            $this->SetFont('Arial', 'B', 12);
            $this->Cell(50, 5, 'Plan de Pago', 0, 0, 'L');
            $this->Cell(50, 5, '' . decode_utf8($this->tipcre), 0, 1, 'L');

            $this->SetFont('Arial', '', 9);
            $this->Cell(70, 5, 'Codigo Credito : ' . $this->CCODCTA, 0, 0, 'L');
            $this->Cell(0, 5, 'Cliente : ' . decode_utf8(mb_strtoupper($this->compl_name, 'utf-8')), 0, 1, 'L');
            $this->Cell(50, 5, 'Fecha de Pago : ' . date("d-m-Y", strtotime($this->DfecPago)), 0, 0, 'L');
            $this->Cell(40, 5, 'Monto : Q ' . number_format($this->MonSug, 2), 0, 0, 'L');
            $this->Cell(40, 5, 'Interes : ' . number_format($this->interes, 2) . '%', 0, 0, 'L');
            //GASTOS COBROS SI HUBIERAN
            if ($this->porCuota != null) {
                $this->Ln(5);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 5, 'DETALLE DE OTROS COBROS ', 'T', 1, 'C');
                $this->SetFont('Arial', '', 10);
                $l = 0;
                while ($l < count($this->porCuota)) {
                    $tipo = $this->porCuota[$l]['tipo_deMonto'];

                    $calculax = $this->porCuota[$l]['calculox'];
                    $tipcalculo = ($tipo == 1) ? ' ' : (($calculax == 1) ? 'del capital de la cuota' : (($calculax == 2) ? 'del interes de la cuota' : (($calculax == 3) ? 'del total de la cuota' : (($calculax == 4) ? 'del Monto Desembolsado' : 'del Monto Desembolsado / no. cuotas'))));

                    $cant = $this->porCuota[$l]['monto'];
                    $cantt = ($tipo == 2) ? $cant . '%' : 'Q ' . $cant;

                    $nombregasto =  $this->porCuota[$l]['nombre_gasto'];
                    $monapro =  $this->porCuota[$l]['MonSug'];
                    $tiperiodo = $this->porCuota[$l]['tiperiodo'];

                    $this->CellFit(60, 5, decode_utf8($nombregasto), '', 0, 'L', 0, '', 1, 0);
                    // $this->CellFit(30, 5, $tip, '', 0, 'C', 0, '', 1, 0);
                    $this->CellFit(30, 5, $cantt, '', 0, 'R', 0, '', 1, 0);
                    $this->CellFit(0, 5, $tipcalculo, '', 1, 'L', 0, '', 1, 0);
                    $l++;
                }
            }

            $this->Ln(7);
        }
        // Pie de página
        function Footer()
        {
            // Posición: a 1,5 cm del final
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 7);
            // Número de página
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        // PARA EL RESTO DEL ENCABEZADO
        function encabezado($CCODCTA, $DfecPago, $compl_name, $MonSug, $interes, $tipcre)
        {
            $this->Cell(60, 4, '' . $tipcre, 0, 0, 'L');
            $this->SetFont('Arial', '', 7);
            $this->Cell(50, 4, 'Codigo Credito : ' . $CCODCTA, 0, 1, 'L');
            $this->Cell(50, 4, 'Cliente : ' . $compl_name, 0, 1, 'L');
            $this->Cell(50, 4, 'Fecha de Pago : ' . date("d-m-Y", strtotime($DfecPago)), 0, 0, 'L');
            $this->Cell(50, 4, 'Monto : Q ' . number_format($MonSug), 0, 0, 'L');
            $this->Cell(40, 4, 'Interes : ' . $interes, 0, 0, 'L');
            //$this->Cell(40,5,'Ahorro : '.$interes,0,0,'L');
            $this->Ln(10);
            $this->Line(10, 40, 206, 40);
        }

        function htable($titulos)
        {
            $w = array(25, 20, 25, 25, 20, 25, 25, 25);

            // calcular ancho total de la tabla
            $totalWidth = array_sum(array_slice($w, 0, count($titulos)));

            // calcular posición X para centrar
            $pageWidth = $this->GetPageWidth();
            $margins = $this->lMargin + $this->rMargin;
            $x = ($pageWidth - $margins - $totalWidth) / 2 + $this->lMargin;

            $this->SetDrawColor(0, 128, 0);
            $this->SetLineWidth(.3);
            $this->SetFont('', 'B');

            $this->SetX($x); // posición inicial centrada
            for ($i = 0; $i < count($titulos); $i++) {
                $this->Cell($w[$i], 7, $titulos[$i], 1, 0, 'C');
            }
            $this->Ln();
        }
        //EL CONTENIDO DE LA TABLA 
        function btable($planPago, $porCuota)
        {
            $w = array(25, 20, 25, 25, 20, 25);

            $columnOtros = false;
            if (!empty($porCuota)) {
                array_push($w, 25);
                $columnOtros = true;
            }

            $totalWidth = array_sum($w);
            $pageWidth = $this->GetPageWidth();
            $margins = $this->lMargin + $this->rMargin;
            $x = ($pageWidth - $margins - $totalWidth) / 2 + $this->lMargin;

            $this->SetFillColor(224, 235, 255);
            $this->SetTextColor(0);
            $sizefont = (count($planPago) <= 24) ? 12 : 8;
            $higt = (count($planPago) <= 24) ? 6 : 4;
            $this->SetFont('Arial', '', $sizefont);
            $fill = false;

            $otrosTotales = 0;

            foreach ($planPago as $row) {
                $otros = (!empty($row['gastos'])) ? array_sum(array_column($row['gastos'], 'monto')) : 0;

                $this->SetX($x); // mover cursor para centrar cada fila
                $this->Cell($w[0], $higt, setdatefrench($row['fecha']), 'LR', 0, 'C', $fill);
                $this->Cell($w[1], $higt, $row['nrocuota'], 'LR', 0, 'C', $fill);
                // $totalCuota = $row['cuota'] + ($otros > 0 ? $otros : 0);
                $this->Cell($w[2], $higt, moneda($row['cuota']), 'LR', 0, 'C', $fill);
                $this->Cell($w[3], $higt, moneda($row['capital']), 'LR', 0, 'C', $fill);
                $this->Cell($w[4], $higt, moneda($row['interes']), 'LR', 0, 'C', $fill);

                if ($row['cuota'] > 0 || $columnOtros) {
                    $otrosTotales += $otros;
                    $this->Cell($w[5], $higt, moneda($otros), 'LR', 0, 'C', $fill);
                }

                $this->Cell($w[count($w) - 1], $higt, moneda(abs($row['saldo'])), 'LR', 0, 'C', $fill);
                $this->Ln();
                $fill = !$fill;
            }

            // Línea final
            $this->SetX($x);
            $this->Cell($totalWidth, 0, '', 'T', 1);

            // Totales
            $this->SetX($x);
            $this->Cell($w[0] + $w[1], $higt, 'TOTAL', 0, 0, 'C', false);
            $this->Cell($w[2], $higt, ' ', 0, 0, 'C', false);
            $this->Cell($w[3], $higt, moneda(array_sum(array_column($planPago, 'capital'))), 0, 0, 'C', false);
            $this->Cell($w[4], $higt, ' ', 0, 0, 'C', false);
            if (!empty($porCuota)) {
                $this->Cell($w[5], $higt, ' ', 0, 0, 'C', false);
            }
        }
    }

    $pdf = new PDF($dataCredito[0]['CCODCTA'], $dataCredito[0]['DfecPago'], $dataCredito[0]['short_name'], $dataCredito[0]['MonSug'], $interesShow, $dataCredito[0]['tipoAmortizacion'], $info, $gastosCuota);

    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('Arial', '', 12);

    //TITULOS PARA CABECERA
    $titulos = ['Fecha', 'No Cuota', 'Cuota', 'Capital', 'Interes', 'Saldo Cap'];
    if (!empty($gastosCuota)) {
        array_pop($titulos);
        array_push($titulos, 'Otros', 'Saldo Cap');
    }
    $pdf->htable($titulos);
    $pdf->btable($gastosDistribuidos, $gastosCuota);
    // $pdf->btable($amortiza, $fechasPagos, 0, $gastosCuota);
    $pdf->firmas(2, ['Asesor', $pdf->compl_name]);
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "PlanPago_No_" . $dataCredito[0]['CCODCTA'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
