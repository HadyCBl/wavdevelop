<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
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

use Micro\Helpers\Log;
use Creditos\Utilidades\CalculoPagosDiarios;
use Creditos\Utilidades\CalculoPagosQuincenales;
use Creditos\Utilidades\CalculoPagosSemanales;
use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

date_default_timezone_set('America/Guatemala');

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$datos = $_POST["datosval"];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

list($montoCredito, $idProducto, $tipoAmortizacion, $noPeriodo, $tipoPeriodo, $fechaDesembolso, $fechaPago, $peripagcap, $afectaInteres) = $archivo;

$showmensaje = false;
try {
    $database->openConnection();
    $selectColumns = ['id', 'nombre', 'descripcion', 'monto_maximo', 'tasa_interes', 'dias_calculo', 'id_tipo_periodo', 'diasCorridos'];
    $producto = $database->selectColumns('cre_productos', $selectColumns, "id=?", [$idProducto]);
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

switch ($tipo) {
    case 'xlsx';
        // printxls($result, [$texto_reporte, $_SESSION['id'], $hoy]);
        break;
    case 'pdf':
        printpdf($info, $fchspgs, $amortiza, $montoCredito, $gastosDistribuidos, $gastosCuota);
        break;
}

function printpdf($info, $fchspgs, $amortiza, $montoCredito, $planPago, $gastosCuota = [])
{
    class PDF extends FPDF
    {
        private $montoCredito;
        public $info;

        public function __construct($montoCredito, $info)
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

            $this->Image('../../../../' . $this->info[0]["log_img"], 20, 12, 25);
            $this->Cell(190, 3, decode_utf8($this->info[0]["nomb_comple"]), 0, 1, 'C');
            $this->Cell(190, 3, decode_utf8($this->info[0]["nomb_cor"]), 0, 1, 'C');
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(190, 3, $this->info[0]["muni_lug"], 0, 1, 'C');
            $this->Cell(190, 3, 'Email: ' . $this->info[0]["emai"], 0, 1, 'C');
            $this->Cell(190, 3, 'Tel: ' . $this->info[0]["tel_1"] . " Y " . $this->info[0]["tel_2"], 0, 1, 'C');
            $this->Cell(190, 3, 'NIT: ' . $this->info[0]["nit"], 0, 1, 'C');
            $this->Cell(0, 3, mb_strtoupper($this->info[0]["nom_agencia"], 'utf-8'), 'B', 1, 'C');
            $this->SetFont('Arial', '', 7);
            $this->SetXY(-30, 5);
            $this->Cell(10, 2, $hoy, 0, 1, 'L');
            $this->SetXY(-25, 8);
            $this->Ln(25);

            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, decode_utf8('Calendario de pagos'), 0, 0, 'C');
            $this->Ln(7);

            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 10, decode_utf8('Monto del crédito: ') . number_format($this->montoCredito, 2), 0, 0, 'L');
            $this->Ln(10);
        }

        // Pie de página
        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, decode_utf8('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        // Tabla de datos
        function TableData($fchspgs, $amortiza, $montoCredito, $planPago, $gastosCuota = [])
        {
            // echo '<script>console.log("Count planPago: " + ' . count($planPago) . ');</script>';
            $countPlanPago = count($planPago);
            
            if ($countPlanPago <= 12) {
                $sizefont = 12;
                $higt = 9;
            } elseif ($countPlanPago <= 24) {
                $sizefont = 10;
                $higt = 7;
            } elseif ($countPlanPago <= 48) {
                $sizefont = 7;
                $higt = 4;
            } else {
                $sizefont = 6;
                $higt = 3;
            }
            
            $this->SetFont('Arial', '', $sizefont);
            $w = array(15, 25, 25, 25, 25, 25, 25);
            $this->SetDrawColor(255, 255, 255);
            // Encabezados
            $this->SetFillColor(82, 179, 79);
            $this->SetTextColor(255, 255, 255);
            $this->Cell($w[0], $higt, '#', 1, 0, 'C', true);
            $this->Cell($w[1], $higt, 'Fecha', 1, 0, 'C', true);
            $this->Cell($w[2], $higt, 'Capital', 1, 0, 'C', true);
            $this->Cell($w[3], $higt, decode_utf8('Interés'), 1, 0, 'C', true);
            $this->Cell($w[4], $higt, 'Otros', 1, 0, 'C', true);
            $this->Cell($w[5], $higt, 'Cuota', 1, 0, 'C', true);
            $this->Cell($w[6], $higt, 'Saldo', 1, 0, 'C', true);
            $this->SetTextColor(0, 0, 0);
            $this->Ln();

            $this->SetFillColor(230, 230, 230);

            $fill = false;

            foreach ($planPago as $key => $row) {
                $otros = (!empty($row['gastos'])) ? array_sum(array_column($row['gastos'], 'monto')) : 0;

                $this->Cell($w[0], $higt, ($key + 1), 1, 0, 'C', $fill);
                $this->Cell($w[1], $higt, setdatefrench($row['fecha']), 1, 0, 'C', $fill);
                $this->Cell($w[2], $higt, moneda($row['capital']), 1, 0, 'R', $fill);
                $this->Cell($w[3], $higt, moneda($row['interes']), 1, 0, 'R', $fill);
                $this->Cell($w[4], $higt, moneda($otros), 1, 0, 'R', $fill);
                $this->Cell($w[5], $higt, moneda($row['cuota']), 1, 0, 'R', $fill);
                $this->Cell($w[6], $higt, moneda($row['saldo']), 1, 0, 'R', $fill);
                $this->Ln();
                $fill = !$fill;
            }


            // Total row
            $this->SetFillColor(82, 179, 79);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', $sizefont);
            $this->Ln(2);

            $totalCapital = array_sum(array_column($planPago, 'capital'));
            $totalInteres = array_sum(array_column($planPago, 'interes'));
            $totalOtros = 0;
            foreach ($planPago as $row) {
                $totalOtros += (!empty($row['gastos'])) ? array_sum(array_column($row['gastos'], 'monto')) : 0;
            }
            $totalCuota = array_sum(array_column($planPago, 'cuota'));

            $this->Cell($w[0], $higt, '', 1, 0, 'C', true);
            $this->Cell($w[1], $higt, 'TOTAL', 1, 0, 'C', true);
            $this->Cell($w[2], $higt, moneda($totalCapital), 1, 0, 'R', true);
            $this->Cell($w[3], $higt, moneda($totalInteres), 1, 0, 'R', true);
            $this->Cell($w[4], $higt, moneda($totalOtros), 1, 0, 'R', true);
            $this->Cell($w[5], $higt, moneda($totalCuota), 1, 0, 'R', true);
            $this->Cell($w[6], $higt, '', 1, 0, 'R', true);

            $this->SetTextColor(0, 0, 0);
        }
    }

    $pdf = new PDF($montoCredito, $info);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->TableData($fchspgs, $amortiza, $montoCredito, $planPago, $gastosCuota);
    


    
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 6, decode_utf8('La información proporcionada anteriormente representa una estimación general respecto al tipo de crédito, monto y plazo. No obstante, estos parámetros pueden variar en función de las particularidades de cada caso. La validación final de la tabla de amortización de capital se llevará a cabo durante el proceso previo al desembolso, tras un análisis detallado de su situación.'), 0, 'J');

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "PlanPago",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
