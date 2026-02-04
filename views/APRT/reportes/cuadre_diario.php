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

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use Micro\Helpers\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tipo = $_POST["tipo"];
$datos = $_POST["datosval"];

$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];

$tipo = $_POST["tipo"];
$fechainicio = $inputs[0];
$fechafin = $inputs[1];
// $tipocuenta = $selects[0];
$tipostransaccion = (isset($datos[3][0])) ? $datos[3][0] : [];

$tiposDocumentosArray = $datos[3][1] ?? ['0'];
$tiposCuentasArray = $datos[3][2] ?? ['0'];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++ VALIDACIONES [[`finicio`,`ffin`],[`tipcuenta`],[`r1`],['cambiolib'] ++++++++++++++
    +++++++++++++++ tipo, `listado_dia`, download, 'dfecope', 'NMONTO', 2, 'Montos', 0  ++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

if (!validateDate($fechainicio, 'Y-m-d') || !validateDate($fechafin, 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}
if ($fechainicio > $fechafin) {
    echo json_encode(['mensaje' => 'Rango de fechas Inválido', 'status' => 0]);
    return;
}
// if ($radios[0] == "any" && $tipocuenta == '0') {
//     echo json_encode(['mensaje' => 'Seleccione un tipo de cuenta válido', 'status' => 0]);
//     return;
// }

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

$where = (empty($tiposCuentasArray) || in_array('0', $tiposCuentasArray))
    ? " "
    : " AND SUBSTR(aho.ccodaport,7,2) IN ('" . implode("','", $tiposCuentasArray) . "')";

$query2 = "SELECT SUM(CASE WHEN ctipope='D' THEN monto ELSE 0 END) AS depositos,
            SUM(CASE WHEN ctipope='R' THEN monto ELSE 0 END) AS retiros
            FROM aprmov aho WHERE dfecope<? AND cestado!=2 $where;";
$where .= (in_array('lib', $tipostransaccion)) ? "" : "AND (crazon NOT LIKE '%CAMBIO LIBRETA%' AND crazon NOT LIKE '%SALDO INI%')";

$where .= (empty($tiposDocumentosArray) || in_array('0', $tiposDocumentosArray))
    ? " "
    : " AND aho.ctipdoc IN ('" . implode("','", $tiposDocumentosArray) . "')";

// Log::info($where);

$titlereport = " DEL " . date("d-m-Y", strtotime($fechainicio)) . " AL " . date("d-m-Y", strtotime($fechafin));
$query = "SELECT dfecope,SUM(CASE WHEN ctipope='D' THEN monto ELSE 0 END) AS depositos,
            SUM(CASE WHEN ctipope='R' THEN monto ELSE 0 END) AS retiros,ctipope 
            FROM aprmov aho WHERE cestado!=2 AND (dfecope BETWEEN ? AND ?) $where 
            GROUP BY dfecope ORDER BY dfecope ASC";

$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$fechainicio, $fechafin]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }
    $result2 = $database->getAllResults($query2, [$fechainicio]);
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
    $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}
$saldoant = (empty($result2)) ? 0 : ($result2[0]['depositos'] - $result2[0]['retiros']);
switch ($tipo) {
    case 'xlsx';
        printxls($result, $saldoant);
        break;
    case 'pdf':
        printpdf($result, [$titlereport, $saldoant], $info);
        break;
    case 'show':
        showresults($result, $saldoant);
        break;
}
function showresults($registro, $saldoant)
{
    $valores[] = [];
    $i = 0;
    foreach ($registro as $key => $fila) {
        $fila["num"] = $key + 1;
        $fila["dfecope"] = date("d-m-Y", strtotime($fila["dfecope"]));
        $saldo = $fila["depositos"] - $fila["retiros"];
        $fila["depositos"] = number_format($fila["depositos"], 2);
        $fila["retiros"] = number_format($fila["retiros"], 2);
        $saldoant = $saldoant + $saldo;
        $fila["saldogen2"] = round($saldoant, 2);
        $fila["saldogen"] = number_format($saldoant, 2);
        $fila["saldo"] = number_format($saldo, 2);
        $valores[$i] = $fila;
        $i++;
    }

    $keys = ["num", "dfecope", "depositos", "retiros", "saldo", "saldogen"];
    $encabezados = ["#", "Fecha", "Total depositos", "Total Retiros", "Saldo del dia", "Saldo Acumulado"];

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'data' => $valores,
        'keys' => $keys,
        'encabezados' => $encabezados,
    );
    echo json_encode($opResult);
    return;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info)
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

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $rango, $saldoant)
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
            $this->rango = $rango;
            $this->saldoant = $saldoant;
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $tamanioTitulo = 10;
            $tamanio_linea = 4; //altura de la linea/celda
            $ancho_linea = 30; //anchura de la linea/celda
            $ancho_linea2 = 20; //anchura de la linea/celda
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

            $this->SetFont($fuente, 'B', $tamanioTitulo);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'CUADRE DIARIO DE DEPOSITOS/RETIROS', 0, 1, 'C', true);
            $this->Cell(0, 5, 'RANGO: ' . $this->rango, 0, 1, 'L');
            // $this->Cell(0, 5, 'TIPO DE CUENTA: ' . $this->tipocuenta, 0, 1, 'L');
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->Cell(0, $tamanio_linea + 1, 'Saldo Anterior ' . number_format(($this->saldoant), 2, '.', ','), 'B', 1, 'R');
            $this->Cell($ancho_linea, $tamanio_linea + 1, 'Fecha', 'B', 0, 'C', true);
            $this->Cell($ancho_linea * 2, $tamanio_linea + 1, 'Total Depositos', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea * 2, $tamanio_linea + 1, 'Total Retiros', 'B', 0, 'C', true);
            $this->Cell($ancho_linea + 10, $tamanio_linea + 1, 'Saldo', 'B', 1, 'C', true);
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
    // Creación del objeto de la clase heredada
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos[0], $datos[1]);

    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4; //altura de la linea/celda
    $ancho_linea2 = 25; //anchura de la linea/celda
    $pdf->SetFont($fuente, '', 10);
    $fila = 0;
    $saldoant = $datos[1];

    foreach ($registro as $fila) {
        $fecha = date("d-m-Y", strtotime($fila["dfecope"]));
        $saldo = $fila["depositos"] - $fila["retiros"];
        $saldoant = $saldoant + $saldo;
        $depositos = number_format($fila["depositos"], 2);
        $retiros = number_format($fila["retiros"], 2);

        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fecha, 0, 0, 'R', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 * 2 + 3, $tamanio_linea + 1, $depositos, 0, 0, 'R', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 * 2 + 3, $tamanio_linea + 1, $retiros, 0, 0, 'R', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 * 2 + 3, $tamanio_linea + 1, number_format($saldoant, 2), 0, 1, 'R', 0, '', 1, 0); // cuenta
    }

    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, "TOTALES: ", 'T', 0, 'R', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea2 * 2 + 3, $tamanio_linea + 1, number_format(array_sum(array_column($registro, "depositos")), 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea2 * 2 + 3, $tamanio_linea + 1, number_format(array_sum(array_column($registro, "retiros")), 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea2 * 2 + 3, $tamanio_linea + 1, '-', 'T', 1, 'R', 0, '', 1, 0); // cuenta

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cuadre diario",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $saldoant)
{
    $fuente = "Courier";
    $excel = new Spreadsheet();
    $excel
        ->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Cuadre Diario')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEMPLUS')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');


    $activa = $excel->getActiveSheet();
    $activa->setTitle("Listado del dia");
    $encabezado_tabla = ["FECHA", "DEPOSITOS", "RETIROS", "SALDO DEL DIA", "SALDO GENERAL"];
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle('A1:S1')->getFont()->setName($fuente)->setBold(true);

    foreach ($registro as $key => $fila) {
        $fecha = date("d-m-Y", strtotime($fila["dfecope"]));
        $saldo = $fila["depositos"] - $fila["retiros"];
        $saldoant = $saldoant + $saldo;
        $depositos = ($fila["depositos"]);
        $retiros = ($fila["retiros"]);

        $activa->setCellValue('A' . $key + 2, $fecha);
        // $activa->getStyle('B' . $key + 2 . ':E' . $key + 2)->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
        $activa->setCellValueExplicit('B' . $key + 2, $depositos, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('C' . $key + 2, $retiros, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('D' . $key + 2, $saldo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('E' . $key + 2, $saldoant, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    }
    $activa->getStyle("A1:E" . $key + 2)->getFont()->setName($fuente);
    $columnas = range('A', 'E');
    foreach ($columnas as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(TRUE);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "CuadreDiario",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
