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
include  __DIR__ . '/../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$fechaFinal = $inputs[0];
$tipocuenta = $selects[0];
$tipocuentaArray = array_filter(array_map('trim', explode(',', $tipocuenta)));
$estado = $radios[0];


if ($fechaFinal > $hoy) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha digitada no pueder ser mayor que la fecha actual']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ CONSULTAS EN LA BD +++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$where = ($estado == "0") ? " WHERE cta.estado IN ('A','B')" : " WHERE cta.estado = '" . $estado . "'";
$where .= (empty($tipocuentaArray) || in_array('0', $tipocuentaArray))
    ? " "
    : " AND tip.ccodtip IN ('" . implode("','", $tipocuentaArray) . "')";
$titlereport = " AL " . date("d-m-Y", strtotime($fechaFinal));

$strquery = "SELECT tip.nombre,cta.ccodaport, cli.short_name,calcular_saldo_apr_tipcuenta(cta.ccodaport,?) AS saldo, cta.tasa, cli.genero,cta.estado,cta.fecha_apertura
                FROM aprcta cta 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                INNER JOIN aprtip tip ON tip.ccodtip=SUBSTR(cta.ccodaport,7,2)" . $where . " ORDER BY cta.ccodaport,cta.fecha_apertura";
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$fechaFinal]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }
    // $result = convert_to_utf8($result);

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    // $info = convert_to_utf8($info);
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
//FIN TRY

// echo json_encode(['status' => 0, 'mensaje' => $strquery]);
//     return;


switch ($tipo) {
    case 'xlsx';
        printxls($result, $titlereport, $idusuario);
        break;
    case 'pdf':
        printpdf($result, [$titlereport, $idusuario], $info);
        break;
    case 'show':
        showresults($result);
        break;
}

function showresults($registro)
{
    $valores[] = [];
    $i = 0;
    foreach ($registro as $fila) {
        $fila["fecha_apertura"] = ($fila["fecha_apertura"] == "0000-00-00") ? "-" : date("d-m-Y", strtotime($fila["fecha_apertura"]));
        $fila["estado"]=($fila["estado"] == "B") ? "Inactiva" : "Activa";
        $valores[$i] = $fila;
        $i++;
    }

    $keys = ["ccodaport", "short_name", "genero", "estado", "fecha_apertura", "saldo", "tasa", "nombre"];
    $encabezados = ["CUENTA", "NOMBRE CLIENTE", "GENERO", "ESTADO", "APERTURA", "SALDO", "TASA", "TIPO"];

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

function printpdf($registro, $datos, $info)
{

    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins =  "../../.." . $info[0]["log_img"];

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
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Cell(0, 2, $this->datos[1], 0, 1, 'R');
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
            $this->Cell(0, 5, 'REPORTE DE SALDO DE CUENTAS' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 25;
            $this->Cell($ancho_linea, 5, 'CUENTA', 'B', 0, 'L');
            $this->Cell($ancho_linea / 2, 5, 'ESTADO', 'B', 0, 'L'); //
            $this->Cell($ancho_linea * 2, 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'APERTURA', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'SALDO', 'B', 0, 'C');
            $this->Cell($ancho_linea / 2, 5, 'TASA', 'B', 0, 'C'); //
            $this->Cell(0, 5, 'TIPO', 'B', 0, 'C'); //
            $this->Ln(10);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo 
            $this->Image($this->pathlogo, 175, 279, 28);
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
    $tamanio_linea = 3;
    $ancho_linea2 = 25;
    $pdf->SetFont($fuente, '', 8);
    $fila = 0;
    while ($fila < count($registro)) {
        $cuenta = $registro[$fila]["ccodaport"];
        $nombre = (Utf8::decode($registro[$fila]["short_name"]));
        $fecha = ($registro[$fila]["fecha_apertura"] == "0000-00-00") ? "-" : date("d-m-Y", strtotime($registro[$fila]["fecha_apertura"]));
        $tipocuenta = Utf8::decode($registro[$fila]["nombre"]);
        $saldo = $registro[$fila]["saldo"];
        $tasa = $registro[$fila]["tasa"];
        $genero = $registro[$fila]["genero"];
        $estado = $registro[$fila]["estado"];
        $estado = ($estado == "B") ? "Inactiva" : "Activa";

        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, $estado, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fecha, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($saldo, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, number_format($tasa, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(0, $tamanio_linea + 1, $tipocuenta, '', 1, 'L', 0, '', 1, 0);

        $fila++;
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->CellFit($ancho_linea2 * 4.5, $tamanio_linea + 1, 'TOTAL: ' . $fila, '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format(array_sum(array_column($registro, "saldo")), 2, '.', ','), '', 0, 'R', 0, '', 1, 0);

    //resumen de saldo
    $femenino = [];
    $masculino = [];
    $no_definido = [];

    array_walk($registro, function ($item) use (&$femenino, &$masculino, &$no_definido) {
        switch ($item['genero']) {
            case 'F':
                $femenino[] = $item;
                break;
            case 'M':
                $masculino[] = $item;
                break;
            default:
                $no_definido[] = $item;
                break;
        }
    });

    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 * 4.5, $tamanio_linea, "MUJERES: " . count($femenino), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format(array_sum(array_column($femenino, "saldo")), 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2  * 4.5, $tamanio_linea, "HOMBRES: " . count($masculino), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format(array_sum(array_column($masculino, "saldo")), 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2  * 4.5, $tamanio_linea, "INDEFINIDOS: " . count($no_definido), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format(array_sum(array_column($no_definido, "saldo")), 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Saldos de cuentas",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro)
{
    $fuente = "Courier";
    $excel = new Spreadsheet();
    $excel
        ->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Saldos por cuenta con fecha')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');

    $activa = $excel->getActiveSheet();
    $activa->setTitle("IngresosDiarios");
    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CUENTA", "NOMBRE CLIENTE", "GENERO", "ESTADO", "APERTURA", "SALDO", "TASA", "TIPO"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle('A1:S1')->getFont()->setName($fuente)->setBold(true);

    $fila = 0;
    $i = 2;
    while ($fila < count($registro)) {
        $cuenta = $registro[$fila]["ccodaport"];
        $nombre = ($registro[$fila]["short_name"]);
        $fecha = ($registro[$fila]["fecha_apertura"] == "0000-00-00") ? "-" : date("d-m-Y", strtotime($registro[$fila]["fecha_apertura"]));
        $tipocuenta = ($registro[$fila]["nombre"]);
        $saldo = $registro[$fila]["saldo"];
        $tasa = $registro[$fila]["tasa"];
        $genero = $registro[$fila]["genero"];
        $estado = $registro[$fila]["estado"];
        $estado = ($estado == "B") ? "Inactiva" : "Activa";

        $activa->setCellValueExplicit('A' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('B' . $i, strtoupper($nombre));
        $activa->setCellValue('C' . $i, ($genero));
        $activa->setCellValue('D' . $i, ($estado));
        $activa->setCellValue('E' . $i, ($fecha));
        $activa->setCellValueExplicit('F' . $i, $saldo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('G' . $i, $tasa, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValue('H' . $i, $tipocuenta);

        $fila++;
        $i++;
    }
    $activa->getStyle("A1:I" . $i)->getFont()->setName($fuente);
    $columnas = range('A', 'I'); // Genera un array con las letras de A a O
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
        'namefile' => "Reporte de saldos",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}