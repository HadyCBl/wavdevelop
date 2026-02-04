<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
include __DIR__ . '/../funciones/func_ctb.php';

require '../../../fpdf/fpdf.php';
require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

list($finicio, $ffin) = $inputs;
list($codofi, $fondoid) = $selects;
list($rfondo, $ragencia) = $radios;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++ ([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`],[]],`pdf`,`balancecom`,0) ++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

if (!validateDate($finicio, 'Y-m-d') || !validateDate($ffin, 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    return;
}
if ($finicio > $ffin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}

if (date('Y', strtotime($finicio)) !== date('Y', strtotime($ffin))) {
    echo json_encode(['status' => 0, 'mensaje' => 'Las fechas deben pertenecer al mismo año']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++ ARMANDO LA CONSULTA ++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

$condi = ($ragencia == "anyofi") ? " AND id_agencia2=?" : "";
$condi .= ($rfondo == "anyf") ? " AND id_fuente_fondo=?" : "";

$parameters = [$finicio, $ffin];
$parameters = ($ragencia == "anyofi") ? array_merge($parameters, [$codofi]) : $parameters;
$parameters = ($rfondo == "anyf") ? array_merge($parameters, [$fondoid]) : $parameters;

$strquery = "SELECT id_ctb_nomenclatura,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber 
                FROM ctb_diario_mov 
                WHERE estado=1 AND feccnt BETWEEN ? AND ? AND id_tipopol != 9 AND id_tipopol!=13  $condi GROUP BY ccodcta ORDER BY ccodcta";

$titlereport = " DEL " . setdatefrench($finicio) . " AL " . setdatefrench($ffin);

$fechaini = strtotime($finicio);
$fechafin = strtotime($ffin);
$mesini = date("m", $fechaini);
$anioini = date("Y", $fechaini);
$mesfin = date("m", $fechafin);
$aniofin = date("Y", $fechafin);

$showmensaje = false;
try {
    $database->openConnection();
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++ CONSULTA FINAL SIN LA PARTIDA DE APERTURA Y EN EL RANGO DE FECHAS INDICADO+++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $ctbmovdata = $database->getAllResults($strquery, $parameters);
    if (empty($ctbmovdata)) {
        $showmensaje = true;
        throw new Exception("No hay datos en la fecha indicada");
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++ CONSULTA PARTIDA DE APERTURA INGRESADA EN ENERO DEL AÑO DEL BALANCE+++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $inianio = $anioini . '-01-01';

    $qparapr = "SELECT id_ctb_nomenclatura,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber from ctb_diario_mov WHERE estado=1 AND id_tipopol = 9 AND feccnt BETWEEN ? AND ? GROUP BY ccodcta ORDER BY ccodcta";

    $apertura = $database->getAllResults($qparapr, [$inianio, $ffin]);

    /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++ CONSULTA DE REGISTROS ANTES DE LA FECHA QUE SE INGRESO SIN LA PARTIDA DE APERTURA +++++++
        +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $querysali = "SELECT id_ctb_nomenclatura,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber from ctb_diario_mov WHERE estado=1 AND id_tipopol != 9 AND id_tipopol != 13 AND feccnt >= ? AND feccnt < ? GROUP BY ccodcta ORDER BY ccodcta";

    $salinidata = $database->getAllResults($querysali, [$inianio, $finicio]);

    /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++ CUENTAS PARAMETRIZADAS PAL BALANCE +++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $parametros = $database->selectColumns("ctb_parametros_cuentas", ["*"], "id_tipo>=1 AND id_tipo<=6");

    /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++++++++++++++++++ NOMENCLATURA CONTABLE +++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $nomenclatura = $database->selectColumns("ctb_nomenclatura", ["id", "ccodcta", "cdescrip"], "estado=1 AND tipo='D'", [], "ccodcta");

    /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++ INFO INSTITUCION +++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $info = $database->getAllResults("SELECT * FROM $db_name_general.info_coperativa ins
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

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

$GLOBALS['agencia_indica'] = ($ragencia == "anyofi") ? 'AGENCIA: ' . $codofi : 'CONSOLIDADO';

//TIPO DE ARCHIVO A IMPRIMIR
switch ($tipo) {
    case 'xlsx';
        printxls($ctbmovdata, $apertura, $salinidata, $parametros, $nomenclatura);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport], $info, $apertura, $salinidata, $parametros, $nomenclatura);
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info, $apertura, $salinidata, $parametros, $nomenclatura)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
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

            //tipo de letra para el encabezado
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
            $this->Cell(0, 5, 'BALANCE DE COMPROBACION ' . $GLOBALS['agencia_indica'] . $this->datos[0], 0, 1, 'C', true);
            $this->Cell(0, 5, '(CIFRAS EN QUETZALES)', 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 31;

            $this->Cell($ancho_linea, 5, 'CUENTA', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, 'DESCRIPCION', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'Saldo Anterior', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'Debe', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'Haber', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'Deudor', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'Acreedor', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'Saldo', 'B', 1, 'R');
            $this->Ln(2);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo 
            // $this->Image($this->pathlogo, 175, 279, 28);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
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

    $totaldebe = 0;
    $totalhaber = 0;
    $sumsalant = 0;

    $lastPrint = '1';

    foreach ($nomenclatura as $key => $cuentaContable) {

        $salapertura = !empty($apertura) ? array_sum(array_column(filtroXIdCuenta($apertura, $cuentaContable['id']), 'saldo')) : 0;
        $salanterior = !empty($salinidata) ? array_sum(array_column(filtroXIdCuenta($salinidata, $cuentaContable['id']), 'saldo')) : 0;
        $debe = !empty($registro) ? array_sum(array_column(filtroXIdCuenta($registro, $cuentaContable['id']), 'debe')) : 0;
        $haber = !empty($registro) ? array_sum(array_column(filtroXIdCuenta($registro, $cuentaContable['id']), 'haber')) : 0;
        $saldo = !empty($registro) ? array_sum(array_column(filtroXIdCuenta($registro, $cuentaContable['id']), 'saldo')) : 0;

        if ($salapertura == 0 && $salanterior == 0 && $debe == 0 && $haber == 0 && $saldo == 0) {
            continue;
        }

        $saldoanterior = ($salapertura)  + $salanterior;

        $saldofinal = $saldoanterior + $debe - $haber;
        $sumsalant += $saldoanterior;

        //DEFINICION NATURALEZA DE CUENTAS
        $clase = substr($cuentaContable['ccodcta'], 0, 1);
        $salacreedor = [2, 3, 4, '2', '3', '4'];
        $indexresult = array_search($clase, array_column($parametros, 'clase'));
        if ($indexresult != false && in_array($parametros[$indexresult]['id_tipo'], $salacreedor)) {
            $saldofinal = $saldofinal * (-1);
        }

        $saldeu = ($saldo >= 0) ? moneda($saldo) : " ";
        $salacre = ($saldo < 0) ? moneda(abs($saldo)) : " ";

        /**
         * * Se agrega un salto de linea si la cuenta contable siguiente no es del mismo grupo
         */
        if ($key != 0) {
            if (substr($lastPrint, 0, 1) != substr($cuentaContable['ccodcta'], 0, 1)) {
                $pdf->Ln(2);
            }
        }

        /**
         * Impresion de datos
         */

        $lastPrint = $cuentaContable['ccodcta'];

        $pdf->CellFit($ancho_linea2, $tamanio_linea, $cuentaContable['ccodcta'], '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, decode_utf8($cuentaContable['cdescrip']), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, moneda($saldoanterior), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, moneda($debe), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, moneda($haber), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, ($saldeu), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, ($salacre), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, moneda($saldofinal), '', 1, 'R', 0, '', 1, 0);

        //***************SUMATORIAS*********************
        $aux = ($saldo > 0) ? $saldo : 0;
        $totaldebe = $totaldebe + $aux;

        $aux = ($saldo < 0) ? abs($saldo) : 0;
        $totalhaber = $totalhaber + $aux;
    }

    $debetotal = array_sum(array_column($registro, 'debe'));
    $habertotal = array_sum(array_column($registro, 'haber'));
    $pdf->Ln(4);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, 'TOTAL GENERAL: ', '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($sumsalant, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($debetotal, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($habertotal, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($totaldebe, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($totalhaber, 2, '.', ','), 'BT', 1, 'R');
    $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, ' ', '', 0, 'R');
    $pdf->Cell($ancho_linea2 * 5, $tamanio_linea / 4, ' ', 'B', 1, 'R');

    $pdf->firmas(1, ['PRESIDENTE', 'GERENTE', 'CONTADOR']);
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Balance de Comprobacion",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $apertura, $salinidata, $parametros, $nomenclatura)
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

    $lastPrint = '1';
    $i = 2;

    foreach ($nomenclatura as $key => $cuentaContable) {

        $salapertura = !empty($apertura) ? array_sum(array_column(filtroXIdCuenta($apertura, $cuentaContable['id']), 'saldo')) : 0;
        $salanterior = !empty($salinidata) ? array_sum(array_column(filtroXIdCuenta($salinidata, $cuentaContable['id']), 'saldo')) : 0;
        $debe = !empty($registro) ? array_sum(array_column(filtroXIdCuenta($registro, $cuentaContable['id']), 'debe')) : 0;
        $haber = !empty($registro) ? array_sum(array_column(filtroXIdCuenta($registro, $cuentaContable['id']), 'haber')) : 0;
        $saldo = !empty($registro) ? array_sum(array_column(filtroXIdCuenta($registro, $cuentaContable['id']), 'saldo')) : 0;

        if ($salapertura == 0 && $salanterior == 0 && $debe == 0 && $haber == 0 && $saldo == 0) {
            continue;
        }

        $saldoanterior = ($salapertura)  + $salanterior;

        $saldofinal = $saldoanterior + $debe - $haber;


        //DEFINICION NATURALEZA DE CUENTAS
        $clase = substr($cuentaContable['ccodcta'], 0, 1);
        $salacreedor = [2, 3, 4, '2', '3', '4'];
        $indexresult = array_search($clase, array_column($parametros, 'clase'));
        if ($indexresult != false && in_array($parametros[$indexresult]['id_tipo'], $salacreedor)) {
            $saldofinal = $saldofinal * (-1);
        }

        $saldeu = ($saldo >= 0) ? ($saldo) : " ";
        $salacre = ($saldo < 0) ? (abs($saldo)) : " ";

        /**
         * * Se agrega un salto de linea si la cuenta contable siguiente no es del mismo grupo
         */
        if ($key != 0) {
            if (substr($lastPrint, 0, 1) != substr($cuentaContable['ccodcta'], 0, 1)) {
                $i++;
            }
        }

        /**
         * Impresion de datos
         */

        $lastPrint = $cuentaContable['ccodcta'];

        $activa->setCellValueExplicit('A' . $i, $cuentaContable['ccodcta'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('B' . $i, $cuentaContable['cdescrip']);
        $activa->setCellValue('C' . $i, $saldoanterior);
        $activa->setCellValue('D' . $i, $debe);
        $activa->setCellValue('E' . $i, $haber);
        $activa->setCellValue('F' . $i, $saldeu);
        $activa->setCellValue('G' . $i, $salacre);
        $activa->setCellValue('H' . $i, $saldofinal);

        $i++;
    }

    $activa->setCellValue('B' . ($i), 'TOTALES');
    $activa->setCellValueExplicit('C' . ($i), '=SUM(C2:C' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('D' . ($i), '=SUM(D2:D' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('E' . ($i), '=SUM(E2:E' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('F' . ($i), '=SUM(F2:F' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $activa->setCellValueExplicit('G' . ($i), '=SUM(G2:G' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    // $activa->setCellValueExplicit('H' . ($i), '=SUM(H2:H' . ($i - 1) . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

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
