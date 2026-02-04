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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tipo = $_POST["tipo"];
$datos = $_POST["datosval"];

$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];

list($fechainicio, $fechafin, $fechainicioAper, $fechafinAper) = $inputs;
list($estado, $filtroVen, $filtroAper) = $radios;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++ VALIDACIONES [[`finicio`,`ffin`,`finicioAper`,`ffinAper`],[],[`r1`,`r2`,`r3`],[]] +++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if ($filtroVen == "frango") {
    if (!validateDate($fechainicio, 'Y-m-d') || !validateDate($fechafin, 'Y-m-d')) {
        echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
        return;
    }
    if ($fechainicio > $fechafin) {
        echo json_encode(['mensaje' => 'Rango de fechas Inválido', 'status' => 0]);
        return;
    }
}

if ($filtroAper == "frango2") {
    if (!validateDate($fechainicioAper, 'Y-m-d') || !validateDate($fechafinAper, 'Y-m-d')) {
        echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
        return;
    }
    if ($fechainicioAper > $fechafinAper) {
        echo json_encode(['mensaje' => 'Rango de fechas Inválido', 'status' => 0]);
        return;
    }
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$where = ($filtroAper == "frango2") ? " AND cr.fec_apertura BETWEEN ? AND ?" : "";
$params = ($filtroAper == "frango2") ? [$fechainicioAper, $fechafinAper] : [];

$where .= ($filtroVen == "frango") ? " AND cr.fec_ven BETWEEN ? AND ?" : "";
$params = ($filtroVen == "frango") ? array_merge($params, [$fechainicio, $fechafin]) : $params;

$where .= ($estado == 'all') ? "" : " AND liquidado=?";
$params = ($estado == 'all') ? $params : array_merge($params, [$estado]);

$query = " SELECT cr.ccodcrt, cr.codaho,cli.short_name,cr.fec_apertura, cr.fec_ven,cr.liquidado, cr.fec_liq,cr.montoapr,cr.plazo,cr.interes,ofi.nom_agencia AS oficina, cr.calint as periodo
            FROM ahomcrt cr 
            INNER JOIN ahomcta cta ON cta.ccodaho=cr.codaho
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
            INNER JOIN tb_usuario usu ON usu.id_usu = cr.codusu
            INNER JOIN tb_agencia ofi ON ofi.id_agencia = usu.id_agencia
            WHERE cr.estado=1 $where 
            ORDER BY cr.ccodcrt + 0 ASC;";
// echo json_encode(['mensaje' => 'Rango de fechas Inválido', 'status' => 0]);
// return;
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, $params);
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
    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

switch ($tipo) {
    case 'xlsx':
        printxls($result);
        break;
    case 'pdf':
        printpdf($result, $info);
        break;
}

//funcion para generar pdf
function printpdf($registro, $info)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    ;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

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

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit)
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
            $this->DefOrientation = 'L';
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
            $this->Cell(0, 5, 'LISTADO DE CERTIFICADOS', 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->Cell($ancho_linea2, $tamanio_linea + 1, 'No.Cert.', 'B', 0, 'C', true);
            $this->Cell($ancho_linea - 3, $tamanio_linea + 1, 'Cuenta', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2 * 4, $tamanio_linea + 1, 'Nombre del cliente', 'B', 0, 'C', true);
            $this->Cell($ancho_linea2, $tamanio_linea + 1, 'Apertura', 'B', 0, 'C', true);
            $this->Cell($ancho_linea2 + 3, $tamanio_linea + 1, 'Vencimiento', 'B', 0, 'C', true);
            $this->Cell($ancho_linea2 + 5, $tamanio_linea + 1, 'Cancelacion', 'B', 0, 'C', true); //
            $this->Cell($ancho_linea2, $tamanio_linea + 1, 'Monto', 'B', 0, 'C', true);
            $this->Cell($ancho_linea2 - 3, $tamanio_linea + 1, 'Plazo', 'B', 0, 'C', true);
            $this->Cell($ancho_linea2 + 7, $tamanio_linea + 1, 'Periodo', 'B', 0, 'C', true);
            $this->Cell($ancho_linea2 - 2, $tamanio_linea + 1, 'Interes', 'B', 1, 'C', true);
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);

    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4; //altura de la linea/celda
    $ancho_linea2 = 20; //anchura de la linea/celda
    $pdf->SetFont($fuente, '', 8);
    $fila = 0;
    while ($fila < count($registro)) {
        $crt = ($registro[$fila]["ccodcrt"]);
        $cuenta = ($registro[$fila]["codaho"]);
        $nombre = decode_utf8($registro[$fila]["short_name"]);
        $apertura = date("d-m-Y", strtotime(($registro[$fila]["fec_apertura"])));
        $vence = date("d-m-Y", strtotime(($registro[$fila]["fec_ven"])));
        $cancel = $registro[$fila]["fec_liq"];
        $cancelacion = ($cancel == '0000-00-00') ? "----------" : date("d-m-Y", strtotime($cancel));
        $monto = ($registro[$fila]["montoapr"]);
        $plazo = ($registro[$fila]["plazo"]);
        $interes = number_format(($registro[$fila]["interes"]), 2, '.', '');
        $periodo = ($registro[$fila]["periodo"]);
        $periodoMap = ['M' => 'Mensual', 'T' => 'Trimestral', 'S' => 'Semestral', 'A' => 'Anual', 'V' => 'Vencimiento'];
        $periodo = $periodoMap[$periodo] ?? $periodo;


        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $crt, 'B', 0, 'L', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 + 7, $tamanio_linea + 1, $cuenta, 'B', 0, 'L', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 * 4, $tamanio_linea + 1, $nombre, 'B', 0, 'L', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $apertura, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, $vence, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, $cancelacion, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $monto, 'B', 0, 'R', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 - 3, $tamanio_linea + 1, $plazo, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 + 7, $tamanio_linea + 1, $periodo, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea2 - 2, $tamanio_linea + 1, $interes, 'B', 1, 'C', 0, '', 1, 0); // cuenta
        $fila++;
    }

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "listadocertificados",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("CERTIFICADOS");

    foreach (range('A', 'L') as $columnID) {
        $activa->getColumnDimension($columnID)->setAutoSize(true);
    }
    $headers = ['CERTIFICADO', 'CODIGO CUENTA', 'NOMBRE CLIENTE', 'FECHA APERTURA', 'FECHA VENCIMIENTO', 'FECHA CANCELACION', 'MONTO', 'PLAZO', 'PERIODO', 'INTERES', 'LIQUIDADO', 'AGENCIA'];

    foreach ($headers as $index => $header) {
        $cell = chr(65 + $index) . '1'; // Convert index to corresponding column letter
        $activa->setCellValue($cell, $header)->getStyle($cell)->getFont()->setBold(true);
    }

    // Establecer el color de fondo de las celdas a gris claro
    $styleArray = [
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'color' => ['argb' => 'FFCCCCCC'], // Color gris claro
        ],
    ];
    $activa->getStyle('A1:L1')->applyFromArray($styleArray);

    $fila = 0;
    $i = 2;
    while ($fila < count($registro)) {
        $crt = ($registro[$fila]["ccodcrt"]);
        $cuenta = ($registro[$fila]["codaho"]);
        $nombre = ($registro[$fila]["short_name"]);
        $apertura = date("d-m-Y", strtotime(($registro[$fila]["fec_apertura"])));
        $vence = date("d-m-Y", strtotime(($registro[$fila]["fec_ven"])));
        $cancel = $registro[$fila]["fec_liq"];
        ($cancel == '0000-00-00') ? $cancelacion = "" : $cancelacion = date("d-m-Y", strtotime(($registro[$fila]["fec_liq"])));

        $monto = ($registro[$fila]["montoapr"]);
        $plazo = ($registro[$fila]["plazo"]);
        $interes = ($registro[$fila]["interes"]);
        $est = ($registro[$fila]["liquidado"]);
        ($est == "S") ? $estado = "LIQUIDADO" : $estado = "NO LIQUIDADO";
        $oficina = decode_utf8($registro[$fila]["oficina"]);
        $periodo = ($registro[$fila]["periodo"]);
        $periodoMap = ['M' => 'Mensual', 'T' => 'Trimestral', 'S' => 'Semestral', 'A' => 'Anual', 'V' => 'Vencimiento'];
        $periodo = $periodoMap[$periodo] ?? $periodo;


        $activa->setCellValueExplicit('A' . $i, $crt, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('B' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('C' . $i, strtoupper($nombre));
        $activa->setCellValue('D' . $i, $apertura);
        $activa->setCellValue('E' . $i, $vence);
        $activa->setCellValue('F' . $i, $cancelacion);
        $activa->setCellValue('G' . $i, $monto);
        $activa->setCellValue('H' . $i, $periodo);
        $activa->setCellValue('I' . $i, $plazo);
        $activa->setCellValue('J' . $i, $interes);
        $activa->setCellValue('K' . $i, $estado);
        $activa->setCellValue('L' . $i, $oficina);
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
        'namefile' => "listadocertificados",
        'tipo' => "xlsx",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
