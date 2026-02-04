<?php
session_start();
include '../../../src/funcphp/func_gen.php';
include '../../../includes/BD_con/db_con.php';
require '../../../fpdf/fpdf.php';

require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}


$datos = $_POST["datosval"];
$cod=$datos[3][0];

$tipo = $_POST["tipo"];
/* $cod = $_GET["id"];
$tipo = $_GET["tipo"]; */

//$strquery = "SELECT * FROM ahointere WHERE `idcalc`=" . $cod . " ORDER BY idreg";
$strquery = "SELECT ai.codaho,ai.nomcli,ai.codcli,ai.tipope,ai.fecope,ai.numdoc,ai.tipdoc,ai.monto,ai.saldo,ai.saldoant,ai.dias,ai.tasa,ai.intcal,ai.isrcal,ati.nombre 
FROM ahointere ai INNER JOIN ahomtip ati ON SUBSTR(ai.codaho,7,2)=ati.ccodtip WHERE ai.idcalc=" . $cod . " ORDER BY ai.idreg";
$sql = mysqli_query($conexion, $strquery);

$array[] = [];
$fila = 0;
while ($registro = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
    $array[$fila] = $registro;
    $fila++;
}

switch ($tipo) {
    case 'xlsx';
        printxls($array);
        break;
    case 'pdf':
        printpdf($array);
        break;
}

//funcion para generar pdf
function printpdf($registro)
{
    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        // Cabecera de página
        function Header()
        {
            //consultar los estados
            $texto_cuentas = "";
            //consultar la fecha
            $texto_fecha = "";
            //variables para el texto
            $fuente = "Courier";
            $tamanio_linea = 4; //altura de la linea/celda
            $ancho_linea = 30; //anchura de la linea/celda
            $ancho_linea2 = 20; //anchura de la linea/celda

            // ACA ES DONDE EMPIEZA LO DEL FORMATO DE REPORTE---------------------------------------------------
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            //TITULO DE REPORTE
            $this->SetFillColor(255, 255, 255);
            $this->SetFont($fuente, '', 10);
            //encabezado de tabla
            $this->Cell($ancho_linea-7, $tamanio_linea + 1, 'CUENTA', 'B', 0, 'C', true); // cuenta
            $this->CellFit($ancho_linea-5, $tamanio_linea + 1, 'COD. CLIENTE', 'B', 0, 'C', true); // cuenta
            $this->Cell($ancho_linea + 25, $tamanio_linea + 1, 'NOMBRE COMPLETO', 'B', 0, 'C', true); //nombre
            $this->CellFit($ancho_linea / 2 - 7, $tamanio_linea + 1, 'OPE', 'B', 0, 'C', true); //
            $this->CellFit($ancho_linea - 8, $tamanio_linea + 1, 'FECHA', 'B', 0, 'C', 0, '', 1, 0); //
            $this->Cell($ancho_linea2-5, $tamanio_linea + 1, 'DOC', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2 + 1, $tamanio_linea + 1, 'MONTO', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2, $tamanio_linea + 1, 'SALDO', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2 - 6, $tamanio_linea + 1, 'DIAS', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2 - 6, $tamanio_linea + 1, 'TASA', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2, $tamanio_linea + 1, 'INTERES', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2, $tamanio_linea + 1, 'ISR', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2, $tamanio_linea + 1, 'TIPOCUENTA', 'B', 0, 'C', true); //
            $this->Ln(8);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4; //altura de la linea/celda
    $ancho_linea2 = 20; //anchura de la linea/celda
    $pdf->SetFont($fuente, '', 7);
    $i = 0;
    $fila = 0;
    while ($fila < count($registro)) {
        $cuenta = encode_utf8($registro[$fila]["codaho"]);
        $codcli = encode_utf8($registro[$fila]["codcli"]);
        $nombre = encode_utf8($registro[$fila]["nomcli"]);
        $tipope = encode_utf8($registro[$fila]["tipope"]);
        $fecha = date("d-m-Y", strtotime(encode_utf8($registro[$fila]["fecope"])));
        $numdoc = encode_utf8($registro[$fila]["numdoc"]);
        $tipdoc = encode_utf8($registro[$fila]["tipdoc"]);
        $monto = encode_utf8($registro[$fila]["monto"]);
        $saldo = encode_utf8($registro[$fila]["saldo"]);
        $saldoant = encode_utf8($registro[$fila]["saldoant"]);
        $dias = encode_utf8($registro[$fila]["dias"]);
        $tasa = encode_utf8($registro[$fila]["tasa"]);
        $interes = encode_utf8($registro[$fila]["intcal"]);
        $isr = encode_utf8($registro[$fila]["isrcal"]);
        $tipocuenta = encode_utf8($registro[$fila]["nombre"]);

        $pdf->CellFit($ancho_linea2 + 4, $tamanio_linea + 1, $cuenta, 'B', 0, 'L', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 + 4, $tamanio_linea + 1, $codcli, 'B', 0, 'L', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2*3-5, $tamanio_linea + 1, $nombre, 'B', 0, 'L', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2/2, $tamanio_linea + 1, $tipope, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2-2, $tamanio_linea + 1, $fecha, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2-3, $tamanio_linea + 1, $numdoc, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1,round($monto,2) , 'B', 0, 'R', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 , $tamanio_linea + 1,round($saldo,2), 'B', 0, 'R', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2-6 , $tamanio_linea + 1, $dias, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2-6 , $tamanio_linea + 1, $tasa, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $interes, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $isr, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2+15, $tamanio_linea + 1, $tipocuenta, 'B', 1, 'l', 0, '', 1, 0); // cuenta

        $fila++;
    }

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();
     
    $opResult = array(
            'status' => 1,
            'mensaje' => 'Reporte generado correctamente',
            'namefile' => "InteresesCalculados",
            'data'=>"data:application/vnd.ms-word;base64,".base64_encode($pdfData)
         );
        echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Calculo");


    $activa->getColumnDimension("A")->setWidth(20);
    $activa->getColumnDimension("B")->setWidth(20);
    $activa->getColumnDimension("C")->setWidth(60);
    $activa->getColumnDimension("D")->setWidth(10);
    $activa->getColumnDimension("E")->setWidth(15);
    $activa->getColumnDimension("F")->setWidth(10);
    $activa->getColumnDimension("G")->setWidth(10);
    $activa->getColumnDimension("H")->setWidth(15);
    $activa->getColumnDimension("I")->setWidth(15);
    $activa->getColumnDimension("J")->setWidth(15);
    $activa->getColumnDimension("K")->setWidth(10);
    $activa->getColumnDimension("L")->setWidth(10);
    $activa->getColumnDimension("M")->setWidth(20);
    $activa->getColumnDimension("N")->setWidth(30);

    $activa->setCellValue('A1', 'CODIGO CUENTA');
    $activa->setCellValue('B1', 'CODIGO CLIENTE');
    $activa->setCellValue('C1', 'NOMBRE CLIENTE');
    $activa->setCellValue('D1', 'TIPOPE');
    $activa->setCellValue('E1', 'FECHA');
    $activa->setCellValue('F1', 'NUMDOC');
    $activa->setCellValue('G1', 'TIPDOC');
    $activa->setCellValue('H1', 'MONTO');
    $activa->setCellValue('I1', 'SALDO');
    $activa->setCellValue('J1', 'SALDOANT');
    $activa->setCellValue('K1', 'DIAS');
    $activa->setCellValue('L1', 'TASA');
    $activa->setCellValue('M1', 'INTERES');
    $activa->setCellValue('N1', 'ISR');
    $activa->setCellValue('O1', 'TIPO CUENTA');
    $fila = 0;
    $i = 2;
    while ($fila < count($registro)) {
        $cuenta = encode_utf8($registro[$fila]["codaho"]);
        $codcli = encode_utf8($registro[$fila]["codcli"]);
        $nombre = encode_utf8($registro[$fila]["nomcli"]);
        $tipope = encode_utf8($registro[$fila]["tipope"]);
        $fecha = date("d-m-Y", strtotime(encode_utf8($registro[$fila]["fecope"])));
        $numdoc = encode_utf8($registro[$fila]["numdoc"]);
        $tipdoc = encode_utf8($registro[$fila]["tipdoc"]);
        $monto = encode_utf8($registro[$fila]["monto"]);
        $saldo = encode_utf8($registro[$fila]["saldo"]);
        $saldoant = encode_utf8($registro[$fila]["saldoant"]);
        $dias = encode_utf8($registro[$fila]["dias"]);
        $tasa = encode_utf8($registro[$fila]["tasa"]);
        $interes = encode_utf8($registro[$fila]["intcal"]);
        $isr = encode_utf8($registro[$fila]["isrcal"]);
        $tipocuenta = encode_utf8($registro[$fila]["nombre"]);

        $activa->setCellValueExplicit('A' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('B' . $i, $codcli, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('C' . $i, strtoupper($nombre));
        $activa->setCellValue('D' . $i, $tipope);
        $activa->setCellValue('E' . $i, $fecha);
        $activa->setCellValueExplicit('F' . $i, $numdoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('G' . $i, $tipdoc);
        $activa->setCellValue('H' . $i, $monto);
        $activa->setCellValue('I' . $i, $saldo);
        $activa->setCellValue('J' . $i, $saldoant);
        $activa->setCellValue('K' . $i, $dias);
        $activa->setCellValue('L' . $i, $tasa);
        $activa->setCellValue('M' . $i, $interes);
        $activa->setCellValue('N' . $i, $isr);
        $activa->setCellValue('O' . $i, $tipocuenta);
        $fila++;
        $i++;
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();
     
    $opResult = array(
            'status' => 1,
            'mensaje' => 'Reporte generado correctamente',
            'namefile' => "InteresesCalculados",
            'data'=>"data:application/vnd.ms-excel;base64,".base64_encode($xlsData)
         );
        echo json_encode($opResult);
    exit;
}