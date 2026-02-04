<?php

use Micro\Helpers\Beneq;
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/WriteTag.php';
require '../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';
$hoy = date("Y-m-d");

use Complex\Functions;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Luecano\NumeroALetras\NumeroALetras;
use Micro\Generic\Utf8;
use Micro\Models\Municipio;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

//*****************ARMANDO LA CONSULTA**************
$consulta = "SELECT tp.recibo AS recibo, tp.cliente AS cli, tp.fecha AS fecha, IF(tpi.tipo = 1, 'INGRESO', 'EGRESO') AS tipomov, tp.tipoadicional,
tpi.nombre_gasto AS detalle, tpm.monto AS monto, tp.agencia AS agencia, descripcion, ifnull(usu.nombre, '') as nombre_usuario, ifnull(usu.apellido, '') as apellido_usuario FROM otr_pago_mov tpm 
INNER JOIN otr_tipo_ingreso tpi ON tpm.id_otr_tipo_ingreso=tpi.id 
INNER JOIN otr_pago tp ON tpm.id_otr_pago=tp.id 
LEFT JOIN tb_usuario usu on tp.cliente =  usu.id_usu and tp.tipoadicional = 3
WHERE tp.id='$archivo[0]'";
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
            // $fuente = "Courier";
            // $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            // $this->SetFont($fuente, '', 7);
            // $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(7);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            // $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // DATOS DE RECIBO
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 15;
    $pdf->SetFont($fuente, '', 11);
    // $pdf->CellFit($ancho_linea2 + 135, $tamanio_linea + 1, 'RECIBO DE ' . $registro[0]['tipomov'] . ': ', 0, 0, 'R', 0, '', 1, 0);
    // $pdf->SetFont($fuente, 'B', 11);
    $pdf->Ln(7);

    // Stylesheet
    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("h1", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("a", $fuente, "BU", 10, "0,0,0");
    $pdf->SetStyle("pers", $fuente, "I", 0, "0,0,0");
    $pdf->SetStyle("place", $fuente, "U", 0, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", 0, "0,0,0");

    $tipoadicional = ($registro[0]['tipoadicional'] == 3) ? $registro[0]['nombre_usuario'] . ' ' . $registro[0]['apellido_usuario'] : $registro[0]['cli'];
    //NOMBRE DEL CLIENTE
    $texto = "<p> <vb><pers><place>" . decode_utf8($tipoadicional) . "</place></pers></vb></p>";

    $pdf->Ln(16);
    //PRIMERA LINEA
    $pdf->Cell($ancho_linea2 + 120, 5, ' ', 0, 0, 'L');
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'RECIBO: ' . $registro[0]['recibo'], 0, 1, 'L', 0, '', 1, 0);
    $pdf->Cell($ancho_linea2 + 2, 5, ' ', 0, 0, 'L');
    $pdf->Cell($ancho_linea2 + 60, 5, Utf8::decode($tipoadicional), 0, 0, 'L');
    $pdf->Cell($ancho_linea2 + 20, 5, ' ', 0, 0, 'L');
    $pdf->Cell($ancho_linea2, 5, date("d-m-Y", strtotime($registro[0]['fecha'])), 0, 1, 'L');
    $pdf->Ln(2.5);

    //SEGUNDA LINEA
    $pdf->Cell($ancho_linea2 + 2, 5, ' ', 0, 0, 'L');
    $pdf->Cell($ancho_linea2 + 60, 5, Utf8::decode(karely(Municipio::obtenerNombrePorCodigo($info[0]['municipio']))), 0, 0, 'L');
    $pdf->Cell($ancho_linea2 + 20, 5, ' ', 0, 0, 'L');

    $pdf->Ln(17);

    $pdf->SetFont($fuente, 'B', 11);
    $pdf->Cell($ancho_linea2, 5, ' ', 0, 0, 'L');
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 3, decode_utf8('DESCRIPCIÓN'), 0, 1, 'L', 0, '', 1, 0);

    foreach ($registro as $key => $value) {

        $pdf->SetFont($fuente, '', 8);
        $pdf->Cell($ancho_linea2 - 13, $tamanio_linea, ' ', 0, 0, 'L');
        $pdf->CellFit($ancho_linea2, $tamanio_linea, ($key + 1) . ' ', 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 7.6, $tamanio_linea, decode_utf8($value['detalle']), 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 2, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea, 'Q.' . number_format(round($value['monto'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
        $pdf->Ln(3);
    }
    $total = round((array_sum(array_column($registro, 'monto'))), 2);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 7, $tamanio_linea + 1, decode_utf8('TOTAL'), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Q.' . number_format($total, 2), 0, 1, 'L', 0, '', 1, 0);
    // $pdf->Ln(7);

    //MONTO EN LETRAS
    $pdf->SetFont($fuente, '', 8);
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $total);
    $res = (!isset($decimal[1])) ? 0 : (($decimal[1] == 0) ? 0 : $decimal[1]);
    // $pdf->MultiCell(0, $tamanio_linea + 1, decode_utf8($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 'C');
    // $pdf->Ln(5);

    $pdf->CellFit(5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->MultiCell(130, $tamanio_linea, Utf8::decode($registro[0]['descripcion']), 0, 'L');

    $pdf->Ln(-7);

    //USUARIOS
    $pdf->SetFont($fuente, 'IU', 9);
    // $pdf->CellFit(0, $tamanio_linea + 1, 'ID:' . $id_usu . ' USUARIO:' . decode_utf8(decode_utf8($_SESSION['nombre'])) . ' ' . decode_utf8(decode_utf8($_SESSION['apellido'])), 0, 0, 'C', 0, '', 1, 0);

    // Espacios para 2 firmas
    $pdf->Ln(10);
    $pdf->SetFont($fuente, '', 9);
    // Línea de firma 1 y 2
    $pdf->Cell(8, 5, ' ', 0, 0, 'L');
    $pdf->CellFit(50, $tamanio_linea + 4, 'F.', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(20, $tamanio_linea + 4, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(50, $tamanio_linea + 4, 'F.', 'B', 1, 'L', 0, '', 1, 0);
    // Etiquetas debajo de las líneas
    $pdf->Ln(2);
    $pdf->Cell(8, 5, ' ', 0, 0, 'L');
    $pdf->CellFit(50, $tamanio_linea, Utf8::decode($_SESSION['nombre'] . ' ' . $_SESSION['apellido']), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(50, $tamanio_linea, Utf8::decode(Beneq::karely($tipoadicional)), 0, 1, 'C', 0, '', 1, 0);

    // $pdf->firmas(2, [$_SESSION['nombre'] . ' ' . $_SESSION['apellido'], Beneq::karely($tipoadicional)]);


    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "ReciboNo-" . $registro[0]['recibo'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
