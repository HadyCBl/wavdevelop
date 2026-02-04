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


use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

$fechainicio = $inputs[0];
$fechafin = $inputs[1];
$tipocuenta = $selects[0];
$tipostransaccion = (isset($archivo[0])) ? $archivo[0] : null;
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++ VALIDACIONES [[`finicio`, `ffin`],[`tipcuenta`],[`filter_cuenta`],[checkedValues]], ++++++++++++++
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
if ($radios[0] == "2" && $tipocuenta == '0') {
    echo json_encode(['mensaje' => 'Seleccione un tipo de cuenta válido', 'status' => 0]);
    return;
}
if (!is_array($tipostransaccion)) {
    echo json_encode(['mensaje' => 'Tipo de transaccion inválido', 'status' => 0]);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$where = ($radios[0] == "1") ? "" : " AND SUBSTR(aho.ccodaport,7,2)='$tipocuenta'";
if (!in_array('all', $tipostransaccion)) {
    $params = array(
        "lib" => "(crazon LIKE '%CAMBIO LIBRETA%' OR crazon LIKE '%SALDO INI%')",
        "int" => "(ctipope='D' AND crazon LIKE '%INTERES%')",
        "dep" => "(ctipope='D' AND crazon NOT LIKE '%INTERES%' AND crazon NOT LIKE '%SALDO INI%')",
        "isr" => "(ctipope='R' AND crazon LIKE '%INTERES%')",
        "ret" => "(ctipope='R' AND crazon NOT LIKE '%INTERES%' AND crazon NOT LIKE '%CAMBIO LIBRETA%')"
    );
    $where .= " AND (";
    foreach ($tipostransaccion as $key => $trans) {
        $where .= $params[$trans];
        $where .= ($key !== array_key_last($tipostransaccion)) ? " OR " : ") ";
    }
}

$titlereport = " DEL " . date("d-m-Y", strtotime($fechainicio)) . " AL " . date("d-m-Y", strtotime($fechafin));

$query = "SELECT aho.ccodaport, cl.short_name, mov.dfecope, mov.cnumdoc, mov.ctipdoc, mov.ctipope, mov.monto,mov.crazon,tip.nombre tipocuenta,ag.nom_agencia AS agencia
            FROM tb_cliente cl
            INNER JOIN aprcta aho ON cl.idcod_cliente = aho.ccodcli
            INNER JOIN aprtip tip ON tip.ccodtip=SUBSTR(aho.ccodaport,7,2)
            INNER JOIN aprmov mov ON mov.ccodaport = aho.ccodaport 
            INNER JOIN tb_usuario usu ON mov.codusu = usu.id_usu
            INNER JOIN tb_agencia ag ON ag.id_agencia = usu.id_agencia
            WHERE mov.cestado!=2 AND (mov.dfecope BETWEEN ? AND ?) " . $where . "  ORDER BY mov.dfecope,mov.ccodaport";

// $opResult = array('status' => 0, 'mensaje' => $query);
// echo json_encode($opResult);
// return;

//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$fechainicio, $fechafin]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

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
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

switch ($tipo) {
    case 'xlsx';
        printxls($result);
        break;
    case 'pdf':
        printpdf($result, [$titlereport], $info);
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
        $fila["dfecope"] = date("d-m-Y", strtotime($fila["dfecope"]));
        $valores[$i] = $fila;
        $i++;
    }

    $keys = ["dfecope", "ccodaport", "short_name", "cnumdoc", "ctipope", "ctipdoc", "monto", "crazon"];
    $encabezados = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "NUMDOC", "OPERACION", "FORMA_PAGO", "MONTO", "RAZON"];

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
            // $this->DefOrientation = 'L';
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
            $this->Cell(0, 5, 'LISTADO DE MOVIMIENTOS' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 25;
            $this->Cell($ancho_linea, 5, 'CUENTA', 'B', 0, 'C');
            $this->Cell($ancho_linea * 2, 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'FECHA', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'DOCUMENTO', 'B', 0, 'C');
            $this->Cell($ancho_linea / 2, 5, 'TIP-DOC', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'DEPOSITO', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'RETIRO', 'B', 0, 'R');
            $this->Ln(8);
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
    $tamanio_linea = 3;
    $ancho_linea2 = 25;

    $pdf->SetFont($fuente, '', 8);

    foreach ($registro as $key => $fil) {
        //["dfecope", "ccodaho", "short_name", "cnumdoc", "ctipope", "ctipdoc", "monto", "crazon"
        $cuenta = $fil["ccodaport"];
        $nombre = decode_utf8($fil["short_name"]);
        $fecha = setdatefrench($fil["dfecope"]);
        $numdoc = $fil["cnumdoc"];
        $tipdoc = $fil["ctipdoc"];
        $tipope = $fil["ctipope"];
        $deposito = ($tipope == "D") ? $fil["monto"] : 0;
        $retiro = ($tipope == "R") ? $fil["monto"] : 0;
        $registro[$key]["deposito"] = $deposito;
        $registro[$key]["retiro"] = $retiro;

        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fecha, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $numdoc, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, $tipdoc, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($deposito, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($retiro, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    }
    $sumadepositos = array_sum(array_column($registro, "deposito"));
    $sumaretiros = array_sum(array_column($registro, "retiro"));

    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->CellFit($ancho_linea2 * 5.5, $tamanio_linea + 1, 'TOTAL INGRESOS: ' . count($registro), '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sumadepositos, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sumaretiros, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Listado de movimientos",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function printxls($registro)
{
    $fuente = "Courier";
    $excel = new Spreadsheet();
    $excel
    ->getProperties()
    ->setCreator("MICROSYSTEM")
    ->setLastModifiedBy('MICROSYSTEM')
    ->setTitle('Reporte')
    ->setSubject('Listado de Movimientos')
    ->setDescription('Este reporte fue generado por el sistema MICROSYSTEMPLUS')
    ->setKeywords('PHPSpreadsheet')
    ->setCategory('Excel');


    $activa = $excel->getActiveSheet();
    $activa->setTitle("Listado del dia");
    # Escribir encabezado de la tabla
    $encabezado_tabla = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "DOCUMENTO", "TIPO DOC", "RAZON", "DEPOSITO", "RETIRO","TIPO CUENTA","AGENCIA"];
    // $activa->getStyle('A1:J1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor xd
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle('A1:S1')->getFont()->setName($fuente)->setBold(true);

    foreach ($registro as $key => $fil) {
        $cuenta = $fil["ccodaport"];
        $nombre = ($fil["short_name"]);
        $fecha = setdatefrench($fil["dfecope"]);
        $numdoc = $fil["cnumdoc"];
        $tipdoc = $fil["ctipdoc"];
        $tipope = $fil["ctipope"];
        $razon = $fil["crazon"];
        $tipocuenta = $fil["tipocuenta"];
        $agencia = $fil["agencia"];
        $deposito = ($tipope == "D") ? $fil["monto"] : 0;
        $deposito = number_format($deposito, 2, '.', '');

        $retiro = ($tipope == "R") ? $fil["monto"] : 0;
        $retiro = number_format($retiro, 2, '.', '');

        $registro[$key]["deposito"] = $deposito;
        $registro[$key]["retiro"] = $retiro;

        $activa->setCellValue('A' . $key + 2, $fecha);
        $activa->setCellValueExplicit('B' . $key + 2, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('C' . $key + 2, strtoupper($nombre));
        $activa->setCellValueExplicit('D' . $key + 2, $numdoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('E' . $key + 2, $tipdoc);
        $activa->setCellValue('F' . $key + 2, $razon);

        // $activa->getStyle('G' . $key + 2 . ':H' . $key + 2)->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
        $activa->setCellValueExplicit('G' . $key + 2, $deposito, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('H' . $key + 2, $retiro, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValue('I' . $key + 2, $tipocuenta);
        $activa->setCellValue('J' . $key + 2, $agencia);
    }
    $activa->getStyle("A1:J" . $key + 2)->getFont()->setName($fuente);
    
    $columnas = range('A', 'J'); // Genera un array con las letras de A a O
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
        'namefile' => "Listado del día",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
