<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';
include '../../../src/funcphp/fun_ppg.php';
require '../../../vendor/autoload.php';
$hoy = date("Y-m-d");

use Complex\Functions;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Luecano\NumeroALetras\NumeroALetras;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

//*****************ARMANDO LA CONSULTA**************
$consulta = "SELECT cd.id, cc.id AS id_cheque, cd.numcom, cd.fecdoc, cd.feccnt, ta.id_agencia, ta.cod_agenc, cm.id_fuente_fondo, cc.monchq, cd.numdoc, cc.nomchq, tb.id AS id_banco, tb.nombre AS nombrebanco,cc.id_cuenta_banco, cb.numcuenta, cc.numchq, cd.glosa, cm.id_ctb_nomenclatura, cn.ccodcta, cn.cdescrip, cm.debe, cm.haber, cc.modocheque AS modocheque  FROM ctb_diario cd
INNER JOIN ctb_mov cm ON cd.id=cm.id_ctb_diario
INNER JOIN ctb_nomenclatura cn ON cm.id_ctb_nomenclatura=cn.id
INNER JOIN ctb_chq cc ON cd.id=cc.id_ctb_diario
INNER JOIN ctb_bancos cb ON cc.id_cuenta_banco=cb.id
INNER JOIN tb_bancos tb ON cb.id_banco=tb.id
INNER JOIN tb_usuario tu ON cd.id_tb_usu=tu.id_usu
INNER JOIN tb_agencia ta ON tu.id_agencia=ta.id_agencia
WHERE cd.estado='1' AND cd.id='$archivo[0]'";
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
if ($data[0]['numchq'] == null || $data[0]['numchq'] == '') {
    echo json_encode(['status' => 0, 'mensaje' => 'No puede imprimir el cheque debido a que no tiene el numero de cheque todavia']);
    return;
}
//ACTUALIZAR EL ESTADO DE EMITIDO
// $res = $conexion->query("UPDATE `ctb_chq` SET `emitido`=1 WHERE id =" . $data[0]['id_cheque']);
// if (!$res) {
//     echo json_encode(['status' => 0, 'mensaje' => 'Error en la emisión del cheque']);
//     return;
// }
//----------------------
switch ($tipo) {
    case 'xlsx';
        // printxls($data, $titlereport, $archivo[0]);
        break;
    case 'pdf':
        printpdf($data);
        break;
}

//funcion para generar pdf
function printpdf($registro)
{
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../../includes/img/fape.jpeg";
    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        //atributos de la clase
        public $pathlogo;
        public $pathlogoins;

        public function __construct($pathlogo, $pathlogoins)
        {
            parent::__construct();
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->DefOrientation = 'P';
        }

        // Cabecera de página
        // function Header()
        // {
        //     $fuente = "Courier";
        //     $hoy = date("Y-m-d H:i:s");
        //     //fecha y usuario que genero el reporte
        //     $this->SetFont($fuente, '', 7);
        //     $this->Cell(0, 2, $hoy, 0, 1, 'R');
        //     // Logo de la agencia
        //     $this->Image($this->pathlogoins, 10, 7, 33);
        //     $this->Ln(10);
        // }
    }
    $pdf = new PDF($rutalogomicro, $rutalogoins);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetCompression(false);
    $pdf->AddFont('Calibri', '', 'calibri.php');
    $pdf->AddFont('Calibri', 'B', 'calibrib.php');

    cheque_bancoindustrial($pdf, $registro);

    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Cheque generado correctamente',
        'namefile' => "Cheque_No_" . $registro[0]['numchq'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function cheque_bancoindustrial($pdf, $registro)
{
    

    $fuente = "Courier";
    $pdf->SetFont($fuente, 'B', 11);
    $tamanio_linea = 3;
    $ancho_linea = 30;
    $rutalogoins = __DIR__ . "/../../../includes/img/corpocredit.png";
    //Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
    $pdf->Image($rutalogoins, 5, 5, 40);
    $pdf->CellFit(0, $tamanio_linea + 1, "COOPERATIVA INTEGRAL DE AHORRO Y CREDITO REGIONAL", 0, 1, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea + 1, "CORPOCREDIT REGIONAL R.L.", 0, 1, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea + 1, "NEBAJ, QUICHE", 0, 0, 'C', 0, '', 1, 0);

    //ENCABEZADO DE CHEQUE

    $pdf->SetFont($fuente, 'B', 12);

    //fecha y monto
    $pdf->Ln(6);
    $fechaSegundos = strtotime($registro[0]['fecdoc']);
    // $diassemana = array("Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sábado");
    $meses = array("ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE");

    $pdf->CellFit(0, 10, " ", 'TRL', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea * 6, $tamanio_linea + 1, "VOUCHER NO. " . $registro[0]['numchq'], 'L', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea + 1, " ", 'R', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit(0, 7, " ", 'RL', 1, 'R', 0, '', 1, 0);
    // $pdf->Ln(8);
    $pdf->SetFont($fuente, 'B', 12);
    $pdf->CellFit($ancho_linea, $tamanio_linea * 2, "Lugar y fecha: ", 'L', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 12);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea * 2, decode_utf8("Nebaj Quiché, ")  . date("d", $fechaSegundos) . " DE " . $meses[date("n", $fechaSegundos) - 1] . " DE " . date("Y", $fechaSegundos), 'B', 0, 'L', 0, '', 1, 0);
    // $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea - 25, $tamanio_linea * 2, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea * 2, number_format($registro[0]['monchq'], 2, '.', ','), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea * 2, " ", 'R', 1, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 12);
    // $pdf->Ln(8);
    //nombre de cheque
    $pdf->CellFit($ancho_linea + 3, $tamanio_linea * 2, "Pago a Nombre de: ", 'L', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 12);
    $pdf->CellFit($ancho_linea * 4, $tamanio_linea * 2, "**** " . decode_utf8(mb_strtoupper($registro[0]['nomchq'], 'utf-8')) . " ****", 'B', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea * 2, " ", 'R', 1, 'L', 0, '', 1, 0);
    // $pdf->Ln(9);
    //numero en letras
    $format_monto = new NumeroALetras();
    $pdf->SetFont($fuente, 'B', 12);
    $pdf->CellFit($ancho_linea, $tamanio_linea * 2, "Suma de: ", 'L', 0, 'L', 0, '', 1, 0);
    $decimal = explode(".", $registro[0]['monchq']);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];

    $pdf->SetFont($fuente, '', 12);
    //function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    // $pdf->MultiCell($ancho_linea * 5 - 10, $tamanio_linea * 2, "**** " . utf8_decode($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100 ****", 'B', 'L');
    $pdf->CellFit($ancho_linea * 5, $tamanio_linea * 2, "**** " . decode_utf8($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100 ****", 'B', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea * 2, " ", 'R', 1, 'L', 0, '', 1, 0);

    $pdf->CellFit(0, $tamanio_linea * 5, " ", 'RL', 1, 'L', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea * 4, $tamanio_linea * 3, " ", 'L', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 2, $tamanio_linea * 3, "Firmas Autorizadas", 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea * 3, " ", 'R', 1, 'C', 0, '', 1, 0);

    $pdf->CellFit(0, $tamanio_linea, " ", 'RLB', 1, 'L', 0, '', 1, 0);
    // $pdf->Ln(11);

    $hoy = date("Y-m-d H:i:s");

    $pdf->SetFont($fuente, 'B', 12);

    $pdf->Ln(3);
    //glosa

    $pdf->CellFit(0, 3, " ", 'TRL', 1, 'R', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea * 3, "CUENTA BANCO:  " . $registro[0]['numcuenta'] . " " . strtoupper($registro[0]['nombrebanco']), 'RL', 1, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea * 2, "POR CONCEPTO DE: ", 'RL', 1, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 12);
    $pdf->MultiCell(0, 6, decode_utf8(mb_strtoupper($registro[0]['glosa'], 'utf-8')), 'LR', 'L');
    $pdf->CellFit(0, 5, " ", 'BRL', 1, 'R', 0, '', 1, 0);

    $pdf->Ln(5);
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea + 1, "CUENTA", 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 3, $tamanio_linea + 1, "DESCRIPCION", 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, "DEBE", 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, "HABER", 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(5);
    //ciclo para impresion de cuentas
    $pdf->SetFont($fuente, '', 11);
    for ($i = 0; $i < count($registro); $i++) {
        //cuentas y montos
        $pdf->CellFit($ancho_linea + 10, $tamanio_linea + 1, $registro[$i]['ccodcta'], 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea * 3, $tamanio_linea + 1, decode_utf8(mb_strtoupper($registro[$i]['cdescrip'], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
        // $pdf->SetFont($fuente, 'B', 13);
        $res = ($registro[$i]['debe'] == 0) ? ' ' : number_format($registro[$i]['debe'], 2, '.', ',');
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1,  $res, 0, 0, 'R', 0, '', 1, 0);
        $res = ($registro[$i]['haber'] == 0) ? ' ' : number_format($registro[$i]['haber'], 2, '.', ',');
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $res, 0, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(5);
    }
    $pdf->Ln(3);

    //suma de debe de haber
    $pdf->CellFit($ancho_linea * 2 + 10, $tamanio_linea * 2, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 2, $tamanio_linea * 2, 'TOTAL', 'LTB', 0, 'L', 0, '', 1, 0);
    $res = number_format(array_sum(array_column($registro, "debe")), 2, '.', ',');
    $pdf->CellFit($ancho_linea, $tamanio_linea * 2,  $res, 'TB', 0, 'R', 0, '', 1, 0);
    $res = number_format(array_sum(array_column($registro, "haber")), 2, '.', ',');
    $pdf->CellFit($ancho_linea, $tamanio_linea * 2, $res, 'TBR', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 12);

    // nuevas actualizaciones
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Nombre:", 'TRL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Nombre:", 'TRL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Nombre:", 'TRL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Recibido Por:", 'TRL', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(4);
    $nombre2 = (strtotime($registro[0]['fecdoc']) < strtotime('2025-04-01')) ? "Pedro de Leon Santiago" : "Pedro Santiago Solis";
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Domingo Terraza Gallego", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, $nombre2, 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Jacinto Fernando Bernal Raymundo", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, " ", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(4);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Cargo:", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Cargo:", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Cargo:", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(3, $tamanio_linea + 1, " ", 'L', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(42, $tamanio_linea + 1, " ", 'B', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(3, $tamanio_linea + 1, " ", 'R', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(4);
    $cargo1 = (strtotime($registro[0]['fecdoc']) < strtotime('2025-04-01')) ? "Gerente General" : "Gerente General y Representante Legal";
    $cargo2 = (strtotime($registro[0]['fecdoc']) < strtotime('2025-04-01')) ? "Presidente Consejo de Administracion" : "Vicepresidente Consejo de Administracion";
    $pdf->SetFont($fuente, '', 10);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, $cargo1, 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, $cargo2, 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "Presidente Comision de Vigilancia", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, " ", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(4);
    $dpi2 = (strtotime($registro[0]['fecdoc']) < strtotime('2025-04-01')) ? "DPI CUI 2562 41600 1413" : "DPI CUI 1616 77460 1413";
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "DPI CUI 1820 18695 1413", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, $dpi2, 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "DPI CUI 3400 41633 1413", 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "DPI", 'RL', 1, 'L', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea * 8, " ", 'RTL', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea * 8, " ", 'RTL', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea * 8, " ", 'RTL', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea * 8, " ", 'RTL', 1, 'C', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "FIRMA", 'RTBL', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "FIRMA", 'RTBL', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "FIRMA", 'RTBL', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea * 1.6, $tamanio_linea + 1, "FIRMA", 'RTBL', 0, 'C', 0, '', 1, 0);
    // $pdf->Ln(8); 

}

function cheque_banrural($pdf, $registro)
{
    $cargo1 = ($registro[0]['fecdoc']< '2024-04-01')?"Gerente General":"Gerente General y Representante Legal";
    //ENCABEZADO DE CHEQUE
    $fuente = "Calibri";
    $pdf->SetFont($fuente, 'B', 13);
    $tamanio_linea = 3;
    $ancho_linea = 30;
    //fecha y monto
    $pdf->Ln(14);
    $fechaSegundos = strtotime($registro[0]['fecdoc']);
    // $diassemana = array("Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sábado");
    $meses = array("ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE");
    // echo $diassemana[date('w')] . " " . date('d') . " de " . $meses[date('n') - 1] . " del " . date('Y');
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea + 1, "Nebaj Quiche, " . date("d", $fechaSegundos) . " DE " . $meses[date("n", $fechaSegundos) - 1] . " DE " . date("Y", $fechaSegundos), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea - 25, $tamanio_linea + 1, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea + 1, number_format($registro[0]['monchq'], 2, '.', ','), 0, 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->Ln(8);
    //nombre de cheque
    $pdf->CellFit($ancho_linea + 3, $tamanio_linea + 1, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 107, $tamanio_linea + 1, "**** " . decode_utf8(mb_strtoupper($registro[0]['nomchq'], 'utf-8')) . " ****", 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(9);
    //numero en letras
    $format_monto = new NumeroALetras();
    $pdf->CellFit($ancho_linea + 3, $tamanio_linea - 4, " ", 0, 0, 'L', 0, '', 1, 0);
    $decimal = explode(".", $registro[0]['monchq']);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->MultiCell($ancho_linea + 105, $tamanio_linea + 1, "**** " . decode_utf8($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100 ****", 0, 'L');
    $pdf->Ln(11);
    //no negociable
    $pdf->CellFit($ancho_linea - 5, $tamanio_linea + 1, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 130, $tamanio_linea + 1, ($registro[0]['modocheque'] == 0) ? "NO NEGOCIABLE" : "NEGOCIABLE", 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(31);
    //LOGO DE CHEQUE Y FECHA
    $fuente = "Calibri";
    $hoy = date("Y-m-d H:i:s");
    //fecha y usuario que genero el reporte
    // $pdf->SetFont($fuente, '', 7);
    // $pdf->Cell(0, 2, $hoy, 0, 1, 'R');
    // // Logo de la agencia
    // $pdf->Image($rutalogoins, 10, 80, 33);
    // $pdf->Ln(13);
    //DETALLE DE CHEQUE
    $pdf->SetFont($fuente, 'B', 13);
    //cuenta de banco
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea + 1, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 40, $tamanio_linea + 1, "CUENTA BANCO " . strtoupper($registro[0]['nombrebanco']) . " No.:", 'B', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea + 15, $tamanio_linea + 1, $registro[0]['numcuenta'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea - 5, $tamanio_linea + 1, "CHEQUE #: ", 0, 0, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea + 1, $registro[0]['numchq'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    //glosa
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->MultiCell($ancho_linea + 120, $tamanio_linea + 1, decode_utf8(mb_strtoupper($registro[0]['glosa'], 'utf-8')), 0, 'L');
    $pdf->Ln(8);
    //nombre
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 130, $tamanio_linea + 1, "**** " . decode_utf8(mb_strtoupper($registro[0]['nomchq'], 'utf-8')) . " ****", 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, 'B', 13);
    //ciclo para impresion de cuentas
    for ($i = 0; $i < count($registro); $i++) {
        //cuentas y montos
        $pdf->CellFit($ancho_linea + 10, $tamanio_linea + 1, $registro[$i]['ccodcta'], 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea + 50, $tamanio_linea + 1, decode_utf8(mb_strtoupper($registro[$i]['cdescrip'], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
        $pdf->SetFont($fuente, 'B', 13);
        $res = ($registro[$i]['debe'] == 0) ? ' ' : number_format($registro[$i]['debe'], 2, '.', ',');
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1,  $res, 0, 0, 'R', 0, '', 1, 0);
        $res = ($registro[$i]['haber'] == 0) ? ' ' : number_format($registro[$i]['haber'], 2, '.', ',');
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $res, 0, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(5);
    }
    $pdf->Ln(1);
    //linea
    $pdf->Cell($ancho_linea + 90, 0, ' ', 0, 0, 'R');
    $pdf->Cell($ancho_linea + 35, 0, ' ', 1, 0, 'R');
    $pdf->Ln(2);
    //suma de debe de haber
    $pdf->CellFit($ancho_linea + 90, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $res = number_format(array_sum(array_column($registro, "debe")), 2, '.', ',');
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1,  $res, 0, 0, 'R', 0, '', 1, 0);
    $res = number_format(array_sum(array_column($registro, "haber")), 2, '.', ',');
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $res, 0, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->Cell($ancho_linea + 90, 0, ' ', 0, 0, 'R');
    $pdf->Cell($ancho_linea + 35, 0, ' ', 1, 0, 'R');
    $pdf->Ln(1);
    $pdf->Cell($ancho_linea + 90, 0, ' ', 0, 0, 'R');
    $pdf->Cell($ancho_linea + 35, 0, ' ', 1, 0, 'R');

    // nuevas actualizaciones
    $pdf->Ln(8); //--REQ--fape--2--Ninguno
    $pdf->firmas(3, ['ELABORADO POR', 'REVISADO POR', 'AUTORIZADO POR']);
    $pdf->Ln(5);
    //DATOS DE QUIEN AUTORIZA EL CHEQUE
    $pdf->Cell($ancho_linea, $tamanio_linea + 1, 'RECIBIDO POR: ', 0, 0, 'L');
    $pdf->Cell($ancho_linea + 50, $tamanio_linea + 1, ' ', 'B', 0, 'R');
    $pdf->Cell($ancho_linea - 10, $tamanio_linea + 1, 'DPI: ', 0, 0, 'R');
    $pdf->Cell($ancho_linea + 30, $tamanio_linea + 1, ' ', 'B', 0, 'R');
    $pdf->Ln(12);
    $pdf->Cell($ancho_linea, $tamanio_linea + 1, 'FIRMA: ', 0, 0, 'L');
    $pdf->Cell($ancho_linea + 50, $tamanio_linea + 1, ' ', 'B', 0, 'R');
    $pdf->Cell($ancho_linea - 10, $tamanio_linea + 1, 'FECHA: ', 0, 0, 'R');
    $pdf->Cell($ancho_linea + 30, $tamanio_linea + 1, ' ', 'B', 0, 'R');
}
