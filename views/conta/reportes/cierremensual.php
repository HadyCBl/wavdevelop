<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
session_start();
include '../../../src/funcphp/func_gen.php';
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';

require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];
$condi = "";


if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

//echo json_encode(['status' => 0, 'mensaje' => $radios[1]]);return;
//echo json_encode(['status' => 0, 'mensaje' => $selects[0]]);return;


$combinaciones = [
    'allofi-allani' => '',
    'anyofi-allani' => "WHERE id_agencia = " . intval($selects[0]),
    'allofi-anyani' => "WHERE anio = " . intval($selects[1]),
    'anyofi-anyani' => "WHERE id_agencia = " . intval($selects[0]) . " AND anio = " . intval($selects[1]),
];
$clave = implode('-', $radios);
$condi = $combinaciones[$clave] ?? ''; 
//echo json_encode(['status' => 0, 'mensaje' => $condi]);return;



if ($radios[0] == 'allofi') {
    // Consolidado
    $GLOBALS['agencia_indica'] = ' CONSOLIDADO ';
} elseif ($radios[0] == 'anyofi') {
    // Por agencia
    $GLOBALS['agencia_indica'] = 'AGENCIA:' . $selects[0];
}

$strquery = "SELECT * FROM ctb_meses " . $condi . " ORDER BY anio DESC, num_mes DESC";



$querypol = mysqli_query($conexion, $strquery);
$ctbmovdata[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($querypol)) {
    $ctbmovdata[$j] = $fil;
    $j++;
}
//COMPROBAR SI HAY REGISTROS
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos']);
    return;
}
//$ctbmovdata[$j] = $ctbmovdata[0];
$titlereport = " AL " . date("d-m-Y", strtotime($hoy));


/*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++ INFO DE LA INSTITUCION +++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
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

//TIPO DE ARCHIVO A IMPRIMIR
switch ($tipo) {
    case 'xlsx';
        printxls($ctbmovdata);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport], $info);
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        //atributos de la clase
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $rango;
        public $tipocuenta;
        public $saldoant;
        public $datos;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->datos = $datos;
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 13, 33);
            $this->SetFont($fuente, 'B', 9);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(10);
            $this->SetFont($fuente, 'B', 10);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'CIERRE MENSUAL '.  $GLOBALS['agencia_indica'] , 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 31;

            $this->Cell($ancho_linea, 5, 'MES', 'B', 0, 'L');
            $this->Cell($ancho_linea +15, 5, Utf8::decode('AÑO'), 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'Estado del mes', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'Agencia', 'B', 0, 'R');
            $this->Cell($ancho_linea-5, 5, ' ', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'Abierto', 'B', 0, 'R');
            $this->Cell($ancho_linea+17, 5, 'Cerrado ', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, ' ', 'B', 1, 'R');
            $this->Ln(2);
        }

        // Pie de página
        function Footer()
        {
            $this->SetY(-15);
            // $this->Image($this->pathlogo, 175, 279, 28);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4;
    $ancho_linea2 = 31;
    $pdf->SetFont($fuente, '', 8);


    $meses = [1 => "Enero", 2 => "Febrero", 3 => "Marzo", 4 => "Abril",5 => "Mayo", 6 => "Junio", 7 => "Julio", 8 => "Agosto", 9 => "Septiembre", 10 => "Octubre", 11 => "Noviembre", 12 => "Diciembre"
    ];
    
    $f = 0;
    while ($f < count($registro)) {
        $num_mes = $registro[$f]["num_mes"];
        $anio = $registro[$f]["anio"];
        $cierre = $registro[$f]["cierre"];
        $id_agencia = $registro[$f]["id_agencia"];
        $open_at = $registro[$f]["open_at"];
        $close_at = $registro[$f]["close_at"];
        $cierreTxt = ($cierre == 1) ? 'Abierto' : 'Cerrado';
    
        $mes = isset($meses[$num_mes]) ? $meses[$num_mes] : "Mes inválido";
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $mes, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 +15, $tamanio_linea, $anio, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 +20, $tamanio_linea, $cierreTxt, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 +11, $tamanio_linea, $id_agencia, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea, !empty($open_at) ? $open_at : '-', '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea, !empty($close_at) ? $close_at : '-', '', 0, 'L', 0, '', 1, 0);
        $pdf->Ln();
        $f++;
    }       
    
    $pdf->firmas(1, ['PRESIDENTE', 'GERENTE', 'CONTADOR']);
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cierre Mensual",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
function calculo2($data, $cuenta)
{
    return (array_filter($data, function ($var) use ($cuenta) {
        return ($var['ccodcta']  == $cuenta);
    }));
}

//funcion para generar archivo excel
function printxls($registro)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Balance_Comprobacion");
    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(65);
    $activa->getColumnDimension("C")->setWidth(20);
    $activa->getColumnDimension("D")->setWidth(20);
    $activa->getColumnDimension("E")->setWidth(20);
    $activa->getColumnDimension("F")->setWidth(20);
    $activa->getColumnDimension("G")->setWidth(20);
    $activa->getColumnDimension("H")->setWidth(20);

    $activa->setCellValue('A1', 'CUENTA');
    $activa->setCellValue('B1', 'NOMBRE CUENTA');
    $activa->setCellValue('C1', 'SALDO ANTERIOR');
    $activa->setCellValue('D1', 'DEBE');
    $activa->setCellValue('E1', 'HABER');
    $activa->setCellValue('F1', 'DEUDOR');
    $activa->setCellValue('G1', 'ACREEDOR');
    $activa->setCellValue('H1', 'SALDO FINAL');
    //-------
    $totaldebe = 0;
    $totalhaber = 0;
    $f = 0;
    $i = 2;
    while ($f < count($registro)) {
        $codcta = $registro[$f]["ccodcta"];
        $nombre = $registro[$f]["cdescrip"];
        $saldo = $registro[$f]["saldo"];
        $debe = $registro[$f]["debe"];
        $haber = $registro[$f]["haber"];

        $saldoanterior = ($salapertura)  + $salanterior;
        $saldofinal = $saldoanterior + $debe - $haber;

        //DEFINICION NATURALEZA DE CUENTAS
        $clase = substr($codcta, 0, 1);
        $salacreedor = [2, 3, 4, '2', '3', '4'];

        //FIN DEFINICION NATURALEZA DE CUENTAS
        $saldeu = ($saldo >= 0) ? $saldo : "";
        $salacre = ($saldo < 0) ? abs($saldo) : "";
        $activa->setCellValueExplicit('A' . $i, $codcta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('B' . $i, $nombre);
        $activa->setCellValue('C' . $i, $saldoanterior);
        $activa->setCellValue('D' . $i, $debe);
        $activa->setCellValue('E' . $i, $haber);
        $activa->setCellValue('F' . $i, $saldeu);
        $activa->setCellValue('G' . $i, $salacre);
        $activa->setCellValue('H' . $i, $saldofinal);
        $i++;

        $f++;
    }
    $activa->setCellValue('B' . ($i), 'TOTALES');
    $activa->setCellValueExplicit('C' . ($i), '=SUM(C2:C' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('D' . ($i), '=SUM(D2:D' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('E' . ($i), '=SUM(E2:E' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('F' . ($i), '=SUM(F2:F' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('G' . ($i), '=SUM(G2:G' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('H' . ($i), '=SUM(H2:H' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Balance de Comprobacion",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
