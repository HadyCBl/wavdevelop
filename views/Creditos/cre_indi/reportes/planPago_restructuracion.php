<?php
session_start();
// TODAS LAS LIBRERIAS 
// include '../../../../includes/BD_con/db_con.php';
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
//include '../../../src/funcphp/func_gen.php';
require("../../../../fpdf/fpdf.php");
include '../../../../src/funcphp/fun_ppg.php';
//include '../../../src/funcphp/calcuFechas.php';
//include '../../../src/funcphp/cuota_fija.php';
// include '../../../../src/funcphp/valida.php';

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$datos = $_POST['datosval'];

// TODAS LAS LIBRERIAS 
$inputs = $datos[0];
$selects = $datos[1];
$archivo = $inputs[0];

//Selecciona el numero de credito
$codcre = $inputs[0];

if ($codcre == "") {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay ningun código de crédito']);
    return;
}

$traerDatosCliente = mysqli_query($conexion, "SELECT CodCli,CCODCTA,short_name,CCODPRD,MonSug,NIntApro,DfecDsbls,DfecPago,noPeriodo,NtipPerC,CtipCre,pr.dias_calculo FROM cremcre_meta crem
    INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli 
    INNER JOIN cre_productos pr on pr.id=crem.CCODPRD
    WHERE CCODCTA = '$codcre'");
$dtcre = mysqli_fetch_array($traerDatosCliente);
$diascalculo    = ($dtcre["dias_calculo"]);
$fechadesembolso    = ($dtcre["DfecDsbls"]);

// AGREGAR UNA OPCION SI SE ENVIA VACIO EL TIPO DE CREDITO NO SE MUESTRE NADA O UN MESAJE DE QUE FALTA PARA EL CALCULO 
// TAMBIEN REVISAR QUE PASA CON LAS FECHAS DE LOS CREDITOS DIARIOS, SE REPITEN LOS QUE SON PRIMOS PARECE SER 
//AGREGAR UNA VARIABLE EN CON EL ID DE LA COOPERATIVA
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}

// TRAE LOS DATOS DE LA CONSULTA 
// $traerDatosCliente = mysqli_query($conexion, "SELECT CodCli,CCODCTA,short_name,CCODPRD,MonSug,NIntApro,DfecDsbls,DfecPago,noPeriodo,NtipPerC,CtipCre FROM cremcre_meta crem
// INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli WHERE CCODCTA = '$codcre'");
// $dtcre = mysqli_fetch_array($traerDatosCliente);

$CtipCre    = $selects[0];
$compl_name = $inputs[1];
$DfecPago   = $inputs[6];
$noPeriodo  = $inputs[7];
$NtipPerC   = $selects[1];
$MonSug     = $inputs[3];
$interes    = $inputs[4];
$CCODCTA    = $inputs[0];
$CODPRDT    = $inputs[2];
// Trae las variables solo el nombre de los creditos
$tipcre = mysqli_query($general, "SELECT descr FROM `tb_credito` where abre = '$CtipCre'");
$dtcre2 = mysqli_fetch_array($tipcre);

$ahoprgr = 0;
// $ahoprgr =  ($dtcre["P_ahoCr"]) / ($dtcre["noPeriodo"]);
//-----------------------------------------------------

// CALCULOS DE LOS DESCUENTOS, 
//$calcu = gstAdmin($CCODCTA, $CODPRDT, "calc", $conexion); // $ccodcta,$CODPRDT, $tipo  --  $Dscnt, $cuota, $name 
$calcu = gastoscuota($CODPRDT, $codcre, $conexion); // $ccodcta,$CODPRDT, $tipo  --  $Dscnt, $cuota, $name 
//$ttlCuota = array_sum($calcu[1]); // Suma de todas cuotas
//$porCuota = $ttlCuota / $noPeriodo; //
$porCuota = $calcu;

//-----------------------------------------------------
$interes_calc = new profit_cmpst(); //inizializa mi clase
$NtipPerC2 = $interes_calc->ntipPerc($NtipPerC);    //$mes, $frecuencia, $periodo
$rate  = (($interes / 100) / $NtipPerC2[1]);
$future_value = 0;        //// OJO!!!! ESTE VALOR ES MUY IMPORTANTE YA QUE CON ESTE SE PODRAN REALIZAR DESEMBOLSOS A FUTURO DESEMBOLSOS PARCIALES 
$beginning = false;
$Cap_amrt = round($MonSug / $noPeriodo, 2);
//TITULOS PARA CABEZERA //AGREGAR AHORRO
$titulos = ['Fecha', 'No Cuota', 'Cuota', 'Capital', 'Interes', 'Saldo Cap'];
if ($porCuota != null) {
    array_pop($titulos);
    array_push($titulos, 'Otros', 'Saldo Cap');
}
//--------------------------------------
$datalaboral = dias_habiles($conexion,$CODPRDT);
if ($datalaboral == false) {
    echo json_encode(['status' => 0, 'mensaje' => 'Falló al recuperar dias laborales']);
    return;
}

$primeracuota = true; // PONER EN TRUE SI SE CALCULA LA PRIMERA CUOTA POR LA DIFERENCIA DE DIAS ENTRE FECDES Y FECPAG
//OBTIENE LOS DATOS, PagoIntereses, PagoCapital,SaldoCapital, 
if (($NtipPerC == "1D" || $NtipPerC == "7D") && $CtipCre == "Flat") {
    //if ($NtipPerC == "1D" && $CtipCre == "Flat") {
    if ($NtipPerC == "7D") {
        $frecuencia = 7;
    } else {
        $frecuencia = 1;
    }
    $amortiza = calculo_montos_diario($MonSug, $interes, $noPeriodo, $conexion,$CODPRDT);
    $fchspgs = calculo_fechas_por_nocuota($DfecPago, $noPeriodo, $frecuencia, $conexion,$CODPRDT);
} else {
    //ESPECIAL ADG
    // $fchspgs = $interes_calc->calcudate2($DfecPago, $noPeriodo, $NtipPerC2[2], $datalaboral);
    // $amortiza = amortizaespecialadg($CtipCre, $rate, $MonSug, $noPeriodo, $future_value, $beginning, $daysdif,$afectaInteres);

    // $amortiza = amortiza($CtipCre, $rate, $MonSug, $noPeriodo, $future_value, $beginning);
    // //$fchspgs = $interes_calc->calcudate($DfecPago, $noPeriodo, $NtipPerC2[2]); //$diasntrfchs, $fchspgs, $fchsreal
    // $fchspgs = $interes_calc->calcudate2($DfecPago, $noPeriodo, $NtipPerC2[2], $datalaboral);

    //VERSION ACTUAL DINAMICO SOBRE LOS DIAS PARAMETRIZADOS EN EL PRODUCTO DE CREDITO
    $fchspgs = $interes_calc->calcudate2($DfecPago, $noPeriodo, $NtipPerC2[2], $datalaboral);
    $amortiza = amortiza($CtipCre, $rate, $MonSug, $noPeriodo, $future_value, $beginning, $fchspgs[1], $fechadesembolso, $diascalculo, $primeracuota);
}
//-----------------------------------------------------
/* echo json_encode(['status' => 0, 'mensaje' =>$fchspgs]);
    return; */
///---------------------------------------------------------------------------------
class PDF extends FPDF
{
    public $CCODCTA, $DfecPago, $compl_name, $MonSug, $interes, $tipcre, $info, $porCuota;
    //  VARIABLES QUE SE OBTIENEN POR CONSTRUCTOR
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
        //$this->Line(10,10,206,10);
        //$this->Line(10,35.5,206,35.5);
        //$this->Cell(80,0,'',0,0,'L',$this->Image('../../../includes/img/logomicro.png', 152,12, 19));
        $this->Cell(0, 0, '', 0, 1, 'L', $this->Image('../../../../' . $this->info[0]["log_img"], 170, 12, 19));
        $this->Cell(0, 0, '', 0, 1, 'L', $this->Image('../../../../includes/img/logomicro.png', 20, 12, 19));
        //pruebas
        $this->Cell(190, 3, '' . $this->info[0]["nomb_comple"], 0, 1, 'C');
        $this->Cell(190, 3, '' . $this->info[0]["nomb_cor"], 0, 1, 'C');
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
        //************ */
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(50, 5, 'Plan de Pago', 0, 0, 'L');
        $this->Cell(50, 5, '' . $this->tipcre, 0, 1, 'L');
        //$this->Cell(111,25,'Plan de Pago',0,0,'C', $this->Image('../../../includes/img/logomicro.png',20,12,20));
        // Salto de línea
        //$this->Ln(15);
        //   DATOS DEL CREDITO HERE 
        $this->SetFont('Arial', '', 9);
        $this->Cell(70, 5, 'Codigo Credito : ' . $this->CCODCTA, 0, 0, 'L');
        $this->Cell(0, 5, 'Cliente : ' . (mb_strtoupper($this->compl_name, 'utf-8')), 0, 1, 'L');
        $this->Cell(50, 5, 'Fecha de Pago : ' . date("d-m-Y", strtotime($this->DfecPago)), 0, 0, 'L');
        $this->Cell(40, 5, 'Monto : Q ' . number_format($this->MonSug, 2), 0, 0, 'L');
        $this->Cell(40, 5, 'Interes : ' . number_format($this->interes, 2) . '%', 0, 0, 'L');
        // $this->Cell(40, 5, 'Ahorro Programado: Q' . number_format($this->P_ahoCr, 2), 0, 0, 'L');
        //GASTOS COBROS SI HUBIERAN
        if ($this->porCuota != null) {
            $this->Ln(10);
            $this->Cell(0, 5, 'DETALLE DE OTROS COBROS ', 0, 1, 'C');
            $l = 0;
            while ($l < count($this->porCuota)) {
                $tipo = $this->porCuota[$l]['tipo_deMonto'];
                //$tip = ($tipo == 2) ? 'Porcentaje' : 'Fijo';
                $calculax = $this->porCuota[$l]['calculox'];
                $tipcalculo = ($tipo == 1) ? ' ' : (($calculax == 1) ? 'del capital de la cuota' : (($calculax == 2) ? 'del interes de la cuota' : 'del total de la cuota'));

                $cant = $this->porCuota[$l]['monto'];
                $cantt = ($tipo == 2) ? $cant . '%' : 'Q ' . $cant;

                $nombregasto =  $this->porCuota[$l]['nombre_gasto'];
                $monapro =  $this->porCuota[$l]['MonSug'];
                $tiperiodo = $this->porCuota[$l]['tiperiodo'];

                $this->CellFit(60, 5, $nombregasto, '', 0, 'L', 0, '', 1, 0);
                // $this->CellFit(30, 5, $tip, '', 0, 'C', 0, '', 1, 0);
                $this->CellFit(30, 5, $cantt, '', 0, 'R', 0, '', 1, 0);
                $this->CellFit(0, 5, $tipcalculo, '', 1, 'L', 0, '', 1, 0);
                $l++;
            }
        }

        $this->Ln(10);
        //$this->Line(10,40,206,40);
    }
    // Pie de página
    function Footer()
    {
        // Posición: a 1,5 cm del final
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // PARA EL RESTO DEL ENCABEZADO
    function encabezado($CCODCTA, $DfecPago, $compl_name, $MonSug, $interes, $tipcre)
    {
        $this->Cell(60, 5, '' . $tipcre, 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(50, 5, 'Codigo Credito : ' . $CCODCTA, 0, 1, 'L');
        $this->Cell(50, 5, 'Cliente : ' . $compl_name, 0, 1, 'L');
        $this->Cell(50, 5, 'Fecha de Pago : ' . date("d-m-Y", strtotime($DfecPago)), 0, 0, 'L');
        $this->Cell(50, 5, 'Monto : Q ' . number_format($MonSug), 0, 0, 'L');
        $this->Cell(40, 5, 'Interes : ' . $interes, 0, 0, 'L');
        //$this->Cell(40,5,'Ahorro : '.$interes,0,0,'L');
        $this->Ln(15);
        $this->Line(10, 40, 206, 40);
    }
    //TABLA DE AMORTIZACION GERMAN EDITION
    function htable($titulos)
    {
        $w = array(25, 20, 25, 25, 20, 25, 25, 25);
        //$this->SetFillColor(255,0,0);
        //$this->SetTextColor(255);
        $this->SetDrawColor(0, 128, 0);
        $this->SetLineWidth(.3);
        $this->SetFont('', 'B');
        // Header
        for ($i = 0; $i < count($titulos); $i++)
            $this->Cell($w[$i], 7, $titulos[$i], 1, 0, 'C');
        $this->Ln();
    }
    //EL CONTENIDO DE LA TABLA 
    function btable($amortiza, $fchspgs, $ahoprgr, $porCuota)
    {
        $w = array(25, 20, 25, 25, 20, 25);
        $amortiza0 = $amortiza[0];
        $amortiza1 = $amortiza[1];
        $amortiza2 = $amortiza[2];
        $fchspgs1  = $fchspgs[1];
        $i = 0;
        //PRUEBA PARA AGREGAR COLORES 
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('');
        $fill = false;
        if ($porCuota != null) {
            array_push($w, 25);
        }
        foreach ($amortiza0 as $row) {
            //AJUSTE INICIO
            if ($i == array_key_last($amortiza0) && $amortiza2[$i] != 0) {
                $amortiza1[$i] = $amortiza1[$i] + $amortiza2[$i];
                $amortiza2[$i] = 0;
            }
            //AJUSTE FIN
            $this->Cell($w[0], 6, date("d-m-Y", strtotime($fchspgs1[$i])), 'LR', 0, 'C', $fill);
            $this->Cell($w[1], 6, $i + 1, 'LR', 0, 'C', $fill);
            $this->Cell($w[2], 6, number_format(($amortiza1[$i] + $row), 2), 'LR', 0, 'C', $fill); // TOTAL DE CUOTA 
            $this->Cell($w[3], 6, number_format($amortiza1[$i], 2), 'LR', 0, 'C', $fill);  //Capital
            $this->Cell($w[4], 6, number_format($row, 2), 'LR', 0, 'C', $fill);    //Interes
            //$this->Cell($w[6], 6, number_format($ahoprgr, 2), 'LR', 0, 'R', $fill);    //AHORRO PROGRA
            if ($porCuota != null) {
                //----------
                $l = 0;
                $montocobro = 0;
                while ($l < count($porCuota)) {
                    $tipo = $porCuota[$l]['tipo_deMonto'];
                    $monsugc = $porCuota[$l]['MonSug'];
                    $cant = $porCuota[$l]['monto'];
                    $calculax = $porCuota[$l]['calculox'];
                    if ($tipo == 1) {
                        // $mongas = $cant;
                        $mongas = ($calculax == 1) ? $cant : (($calculax == 2) ? ($cant / count($amortiza0)) : $cant);
                    }
                    if ($tipo == 2) {
                        // $mongas = ($calculax == 1) ? ($cant / 100 * $amortiza1[$i]) : (($calculax == 2) ? ($cant / 100 * $row) : (($calculax == 3) ? ($cant / 100 * ($amortiza1[$i] + $row)) : 0));
                        // $mongas = ($calculax == 1) ? ($cant / 100 * $amortiza1[$i]) : (($calculax == 2) ? ($cant / 100 * $row) : (($calculax == 3) ? ($cant / 100 * ($amortiza1[$i] + $row)) : (($calculax == 4) ? ($monsugc * $cant / 100 / 12) : 0)));
                        $mongas = ($calculax == 1) ? ($cant / 100 * $amortiza1[$i]) : (($calculax == 2) ? ($cant / 100 * $row) : (($calculax == 3) ? ($cant / 100 * ($amortiza1[$i] + $row)) : (($calculax == 4) ? ($monsugc * $cant / 100 / 12) : (($calculax == 5) ? ($monsugc * $cant / 100 / count($amortiza0)) : 0))));
                    }
                    $montocobro = $montocobro + round($mongas, 2);
                    $l++;
                }
                //----------
                $this->Cell($w[6], 6, number_format(abs($montocobro), 2), 'LR', 0, 'C', $fill);
            }
            $this->Cell($w[5], 6, number_format(abs($amortiza2[$i]), 2), 'LR', 0, 'C', $fill);
            $this->Ln();
            $fill = !$fill;
            $i++;
        }
        $this->Cell(array_sum($w), 0, '', 'T', 1);

        $fill = false;
        $this->Cell($w[0] + $w[1], 6, 'TOTALES', 0, 0, 'C', $fill);
        $this->Cell($w[2], 6, number_format(array_sum($amortiza0) + array_sum($amortiza1), 2), 0, 0, 'C', $fill); // TOTAL DE CUOTA 
        $this->Cell($w[3], 6, number_format(array_sum($amortiza1), 2), 0, 0, 'C', $fill);  //Capital
        $this->Cell($w[4], 6, number_format(array_sum($amortiza0), 2), 0, 0, 'C', $fill);    //Interes
    }
}
// Creación del objeto de la clase heredada
//$pdf = new PDF();
$pdf = new PDF($CCODCTA, $DfecPago, $compl_name, $MonSug, $interes, $dtcre2["descr"], $info, $porCuota);
$pdf->AliasNbPages();
$pdf->AddPage();
//$pdf->encabezado($CCODCTA,$DfecPago,$compl_name,$MonSug,$interes,$dtcre2["descr"]);
$pdf->SetFont('Times', '', 12);
/* if ($porCuota != 0) {
    $pdf->Cell(0, 10, $porCuota, 0, 1);
} */
$pdf->htable($titulos);
$pdf->btable($amortiza, $fchspgs, $ahoprgr, $porCuota);
$pdf->firmas(1, ['Asesor']);

ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile' => "PlanPago_No_" . $archivo[0],
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData)
);
echo json_encode($opResult);
