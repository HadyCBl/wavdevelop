<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/WriteTag.php';
require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");

use Complex\Functions;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

//*****************ARMANDO LA CONSULTA**************

$consulta = "SELECT tp.recibo AS recibo, tp.cliente AS cli, tp.fecha AS fecha, IF(tpi.tipo = 1, 'INGRESO', 'EGRESO') AS tipomov, tpi.nombre_gasto AS detalle, tpm.monto AS monto, tp.agencia AS agencia, descripcion FROM otr_pago_mov tpm 
INNER JOIN otr_tipo_ingreso tpi ON tpm.id_otr_tipo_ingreso=tpi.id 
INNER JOIN otr_pago tp ON tpm.id_otr_pago=tp.id WHERE tp.id='$archivo[0]'";
//--------------------------------------------------
$query = mysqli_query($conexion, $consulta);
$data[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($query)) {
    $data[$j] = $fil;
    $j++;
}

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos']);
    return;
}

$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $data[0]['agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}
//----------------------
$id_usu = $_SESSION['id'];
switch ($tipo) {
    case 'xlsx';
        // printxls($data, $titlereport, $archivo[0]);
        break;
    case 'pdf':
        printpdf($data, $info, $id_usu, $conexion);
        break;
}
//funcion para generar pdf
function printpdf($registro, $info, $id_usu, $conexion)
{
    class PDF extends PDF_WriteTag
    {
        public function __construct()
        {
            parent::__construct();
            $this->DefOrientation = 'P';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Ln(7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            
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
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // DATOS DE RECIBO
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;

    $pdf->SetFont($fuente, '', 11);
    $pdf->Ln(20);
    $pdf->CellFit($ancho_linea2 + 135, $tamanio_linea + 1, 'RECIBO DE ' . $registro[0]['tipomov'] . ': ', 0, 0, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0]['recibo'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(7);

    // Stylesheet
    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("h1", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("a", $fuente, "BU", 10, "0,0,0");
    $pdf->SetStyle("pers", $fuente, "I", 0, "0,0,0");
    $pdf->SetStyle("place", $fuente, "U", 0, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", 0, "0,0,0");


    //NOMBRE DEL CLIENTE
    $texto = "<p>NOMBRE DE CLIENTE: <vb><pers><place>" . $registro[0]['cli'] . "</place></pers></vb></p>";
    $pdf->WriteTag(0, 5, $texto, 0, "C", 0, 0);
    $pdf->Ln(4);

    //FECHA
    $texto = "<p>FECHA: <vb><pers><place>" . date("d-m-Y", strtotime($registro[0]['fecha'])) . "</place></pers></vb>        DIRECCION: <vb><pers><place>" . $info[0]['muni_lug'] . "</place></pers></vb></p>";
    $pdf->WriteTag(0, 5, utf8_decode($texto), 0, "C", 0, 0);
    $pdf->Ln(4);

    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, utf8_decode('DESCRIPCIÓN'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    foreach ($registro as $key => $value) {

        $pdf->SetFont($fuente, '', 11);
        $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ($key + 1) . '.', 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 60, $tamanio_linea + 1, utf8_decode($value['detalle']), 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, number_format(round($value['monto'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
        $pdf->Ln(5);
    }
    //$total = number_format(round((array_sum(array_column($registro, 'monto'))), 2), 2);
    $total = round((array_sum(array_column($registro, 'monto'))), 2);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 60, $tamanio_linea + 1, utf8_decode('TOTAL'), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1,number_format( $total, 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(7);

    //MONTO EN LETRAS
    $pdf->SetFont($fuente, '', 11);
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $total);
    $res = (!isset($decimal[1])? 0 : (($decimal[1] == 0) ? 0 : $decimal[1]));
    //$res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->MultiCell(0, $tamanio_linea + 1, utf8_decode($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 'R');
    $pdf->Ln(5);

    $pdf->MultiCell(100, $tamanio_linea + 0, $registro[0]['descripcion'], 0, 'L');

    $pdf->Ln(10);

    //USUARIOS
    $pdf->SetFont($fuente, 'IU', 11);
    $pdf->CellFit(0, $tamanio_linea + 1, 'ID:' . $id_usu, 0, 0, 'C', 0, '', 1, 0);

    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Cheque generado correctamente',
        'namefile' => "ReciboNo-" . $registro[0]['recibo'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
