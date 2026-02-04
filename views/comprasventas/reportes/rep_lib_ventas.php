<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
    exit;
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    exit;
}

$titlereport = "Libro de Ventas";

$idusuario = $_SESSION['id'];
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

$fecinicio = $inputs[0];
$fecfin = $inputs[1];

if (!validateDate($fecinicio, 'Y-m-d') || !validateDate($fecfin, 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    exit;
}
if ($fecinicio > $hoy || $fecfin > $hoy) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha digitada no puede ser mayor que la fecha actual']);
    exit;
}

// Actualizar el título del reporte con fechas
$titlereport = "Libro de ventas del " . date("d/m/Y", strtotime($fecinicio)) . " al " . date("d/m/Y", strtotime($fecfin));

//Nueva consulta simplificada
$strquery = "
    SELECT 
        ck.DFECPRO, 
        ck.CNROCUO, 
        ck.INTERES, 
        ck.MORA, 
        clh.short_name AS nombre_cliente
    FROM 
        CREDKAR ck
    INNER JOIN 
        cremcre_meta cm ON ck.CCODCTA = cm.CCODCTA
    INNER JOIN 
        tb_cliente clh ON clh.idcod_cliente = cm.CodCli
    WHERE 
        ck.CESTADO <> 'X' AND  
        cm.Cestado IN ('F', 'G') AND  
        ck.DFECPRO BETWEEN ? AND ?  
    ORDER BY 
        ck.DFECPRO, ck.CODKAR;
";

try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$fecinicio, $fecfin]);
    if (empty($result)) {
        throw new Exception("No se encontraron registros");
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
                                WHERE ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        throw new Exception("Institución asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    $mensaje = $e->getMessage();
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    echo json_encode(['status' => 0, 'mensaje' => $mensaje]);
    exit;
}

switch ($tipo) {
    case 'xlsx':
        printxls($result, [$titlereport, $idusuario]);
        break;
    case 'pdf':
        printpdf($result, [$titlereport, $idusuario], $info);
        break;
    case 'show':
        showresults($result);
        break;
    default:
        echo json_encode(['status' => 0, 'mensaje' => 'Tipo de reporte no válido']);
        break;
}


// ================== FUNCION PARA PDF =====================
function printpdf($registro, $datos, $info)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];
    $usuario = $datos[0];

    class PDF extends FPDF
    {
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $datos;
        public $usuario;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $usuario)
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
            $this->usuario = $usuario;
            $this->DefOrientation = 'L';
        }

        function Header()
        {
            $fuente = "Arial";
            $hoy = date("d/m/Y H:i:s");

            // Línea superior decorativa
            $this->SetDrawColor(41, 128, 185);
            $this->SetLineWidth(0.8);
            $this->Line(10, 8, 287, 8);

            // Fecha y hora en esquina superior derecha
            $this->SetFont($fuente, 'I', 7);
            $this->SetTextColor(100, 100, 100);
            $this->SetXY(10, 10);
            $this->Cell(0, 3, 'Fecha de impresion: ' . $hoy, 0, 1, 'R');

            // Logo institucional
            $this->Image($this->pathlogoins, 12, 15, 35);

            // Información de la institución con mejor formato
            $this->SetTextColor(0, 0, 0);
            $this->SetFont($fuente, 'B', 11);
            $this->SetY(15);
            $this->Cell(0, 5, $this->institucion, 0, 1, 'C');

            $this->SetFont($fuente, '', 8);
            $this->Cell(0, 4, $this->oficina, 0, 1, 'C');

            $this->Cell(0, 4, $this->direccion, 0, 1, 'C');

            $this->Cell(0, 4, 'Tel: ' . $this->telefono . ' | Email: ' . $this->email, 0, 1, 'C');

            $this->SetFont($fuente, 'B', 8);
            $this->Cell(0, 4, 'NIT: ' . $this->nit, 0, 1, 'C');

            // Línea separadora con color
            $this->SetDrawColor(41, 128, 185);
            $this->SetLineWidth(0.5);
            $this->Line(10, 39, 287, 39);

            $this->Ln(6);

            // Título del reporte con fondo de color
            $this->SetFillColor(41, 128, 185);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont($fuente, 'B', 11);
            $this->Cell(0, 6, decode_utf8($this->datos[0]), 0, 1, 'C', true);

            $this->Ln(3);

            // Cabecera del reporte con colores profesionales
            $this->SetFillColor(52, 73, 94);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont($fuente, 'B', 7);
            $this->SetDrawColor(200, 200, 200);

            $widthCells = [10, 20, 20, 75, 30, 30, 30, 32];

            $this->Cell($widthCells[0], 6, 'No.', 1, 0, 'C', true);
            $this->Cell($widthCells[1], 6, 'FECHA', 1, 0, 'C', true);
            $this->Cell($widthCells[2], 6, decode_utf8('Nº Doc'), 1, 0, 'C', true);
            $this->Cell($widthCells[3], 6, decode_utf8('ADQUIRIENTE'), 1, 0, 'C', true);
            $this->Cell($widthCells[4], 6, decode_utf8('SERVICIOS GR'), 1, 0, 'C', true);
            $this->Cell($widthCells[5], 6, decode_utf8('SERVICIOS EX'), 1, 0, 'C', true);
            $this->Cell($widthCells[6], 6, decode_utf8('IVA SOPORTADO'), 1, 0, 'C', true);
            $this->Cell($widthCells[7], 6, decode_utf8('TOTAL'), 1, 1, 'C', true);

            $this->SetTextColor(0, 0, 0);
            $this->SetDrawColor(220, 220, 220);
        }

        function Footer()
        {
            // Línea decorativa superior
            $this->SetY(-18);
            $this->SetDrawColor(41, 128, 185);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY(), 287, $this->GetY());

            $this->Ln(2);

            // Información del footer
            $this->SetFont('Arial', 'I', 7);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 4, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $usuario);
    $pdf->AliasNbPages();
    $pdf->AddPage('L', 'Letter'); // Landscape, tamaño carta
    $fuente = "Arial";
    $tamanio_linea = 4;
    $pdf->SetFont($fuente, '', 7);

    $widthCells = [10, 20, 20, 75, 30, 30, 30, 32];

    $total_servicios_gr = 0;
    $total_servicios_ex = 0;
    $total_iva_soportado = 0;
    $total_general = 0;

    // Alternar colores de fila
    $fill = false;
    $contador = 0;

    foreach ($registro as $fila) {
        $contador++;
        $fecha = date('d/m/Y', strtotime($fila["DFECPRO"]));
        $numero = $fila["CNROCUO"];
        $adquiriente = decode_utf8($fila["nombre_cliente"]);

        // =============== AJUSTE ===============
        // El total ya incluye IVA:
        $total = $fila["INTERES"] + $fila["MORA"];
        // Sacar la base sin IVA y luego el IVA
        $servicios_gr = round($total / 1.12, 2);
        $iva_soportado = round($total - $servicios_gr, 2);
        // =======================================

        $servicios_ex = 0; // En tu ejemplo lo dejas en cero

        $total_servicios_gr += $servicios_gr;
        $total_servicios_ex += $servicios_ex;
        $total_iva_soportado += $iva_soportado;
        $total_general += $total;

        // Establecer color de fondo alternado
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->CellFit($widthCells[0], $tamanio_linea, $contador, 'LR', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[1], $tamanio_linea, $fecha, 'LR', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[2], $tamanio_linea, $numero, 'LR', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[3], $tamanio_linea, $adquiriente, 'LR', 0, 'L', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[4], $tamanio_linea, 'Q ' . number_format($servicios_gr, 2, '.', ','), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[5], $tamanio_linea, 'Q ' . number_format($servicios_ex, 2, '.', ','), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[6], $tamanio_linea, 'Q ' . number_format($iva_soportado, 2, '.', ','), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[7], $tamanio_linea, 'Q ' . number_format($total, 2, '.', ','), 'LR', 1, 'R', $fill, '', 1, 0);

        $fill = !$fill;
    }

    // Línea de cierre de la tabla
    $pdf->Cell(array_sum($widthCells), 0, '', 'T', 1);

    $pdf->Ln(2);

    // Fila de totales con diseño destacado
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);

    $pdf->CellFit($widthCells[0] + $widthCells[1] + $widthCells[2] + $widthCells[3], 5, 'TOTALES GENERALES', 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[4], 5, 'Q ' . number_format($total_servicios_gr, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[5], 5, 'Q ' . number_format($total_servicios_ex, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[6], 5, 'Q ' . number_format($total_iva_soportado, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[7], 5, 'Q ' . number_format($total_general, 2, '.', ','), 1, 1, 'R', true, '', 1, 0);

    // Línea decorativa final
    $pdf->Ln(3);
    $pdf->SetDrawColor(41, 128, 185);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro_Ventas",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    ]);
}

// ================== FUNCION PARA EXCEL =====================
function printxls($registro, $data)
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Ventas");

    // Título del reporte
    $sheet->setCellValue('A1', 'REPORTE');
    $sheet->setCellValue('A2', strtoupper($data[0]));
    $sheet->mergeCells('A1:H1');
    $sheet->mergeCells('A2:H2');

    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

    // Encabezados de columna
    $encabezados = ['No.', 'FECHA', 'Nº', 'ADQUIRIENTE', 'SERVICIOS GR', 'SERVICIOS EX', 'IVA SOPORTADO', 'TOTAL'];
    $sheet->fromArray($encabezados, null, 'A4');
    $sheet->getStyle('A4:H4')->getFont()->setBold(true);
    $sheet->getStyle('A4:H4')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF34495E');
    $sheet->getStyle('A4:H4')->getFont()->getColor()->setARGB('FFFFFFFF');

    $row = 5;
    $total_servicios_gr = 0;
    $total_servicios_ex = 0;
    $total_iva_soportado = 0;
    $total_general = 0;
    $contador = 0;

    foreach ($registro as $fila) {
        $contador++;
        $fecha = date('d-m-Y', strtotime($fila["DFECPRO"]));
        $numero = $fila["CNROCUO"];
        $adquiriente = $fila["nombre_cliente"];

        // =============== AJUSTE ===============
        $total = $fila["INTERES"] + $fila["MORA"];
        $servicios_gr = round($total / 1.12, 2);
        $iva_soportado = round($total - $servicios_gr, 2);
        // =======================================

        $servicios_ex = 0;

        $total_servicios_gr += $servicios_gr;
        $total_servicios_ex += $servicios_ex;
        $total_iva_soportado += $iva_soportado;
        $total_general += $total;

        $sheet->setCellValue('A' . $row, $contador);
        $sheet->setCellValue('B' . $row, $fecha);
        $sheet->setCellValueExplicit('C' . $row, $numero, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('D' . $row, $adquiriente);
        $sheet->setCellValueExplicit('E' . $row, $servicios_gr, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('F' . $row, $servicios_ex, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('G' . $row, $iva_soportado, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('H' . $row, $total, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $row++;
    }

    // Fila de totales
    $sheet->setCellValue('D' . $row, 'TOTALES:');
    $sheet->setCellValueExplicit('E' . $row, $total_servicios_gr, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValueExplicit('F' . $row, $total_servicios_ex, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValueExplicit('G' . $row, $total_iva_soportado, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $sheet->setCellValueExplicit('H' . $row, $total_general, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    $sheet->getStyle('D' . $row . ':H' . $row)->getFont()->setBold(true);
    $sheet->getStyle('D' . $row . ':H' . $row)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF34495E');
    $sheet->getStyle('D' . $row . ':H' . $row)->getFont()->getColor()->setARGB('FFFFFFFF');

    // Formato de números
    $sheet->getStyle('E5:H' . $row)->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

    // Bordes
    $sheet->getStyle('A4:H' . $row)->getBorders()->getAllBorders()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Ajustar ancho de columnas
    foreach (range('A', 'H') as $columna) {
        $sheet->getColumnDimension($columna)->setAutoSize(true);
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $xlsData = ob_get_contents();
    ob_end_clean();

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro_Ventas",
        'tipo' => "xlsx",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    ]);
}

// ================== MOSTRAR RESULTADOS EN PANTALLA =====================
function showresults($registro)
{
    $valores = [];
    $total_servicios_gr = 0;
    $total_servicios_ex = 0;
    $total_iva_soportado = 0;
    $total_general = 0;

    foreach ($registro as $fila) {
        // =============== AJUSTE ===============
        $total = $fila["INTERES"] + $fila["MORA"];
        $servicios_gr = round($total / 1.12, 2);
        $iva_soportado = round($total - $servicios_gr, 2);
        // =======================================

        $servicios_ex = 0;

        $total_servicios_gr += $servicios_gr;
        $total_servicios_ex += $servicios_ex;
        $total_iva_soportado += $iva_soportado;
        $total_general += $total;

        $valores[] = [
            'FECHA'          => date('d-m-Y', strtotime($fila["DFECPRO"])),
            'Nº'             => $fila["CNROCUO"],
            'ADQUIRIENTE'    => $fila["nombre_cliente"],
            'SERVICIOS GR'   => $servicios_gr,
            'SERVICIOS EX'   => $servicios_ex,
            'IVA SOPORTADO'  => $iva_soportado,
            'TOTAL'          => $total
        ];
    }

    // Fila de totales
    $valores[] = [
        'FECHA'          => 'Total',
        'Nº'             => '',
        'ADQUIRIENTE'    => '',
        'SERVICIOS GR'   => $total_servicios_gr,
        'SERVICIOS EX'   => $total_servicios_ex,
        'IVA SOPORTADO'  => $total_iva_soportado,
        'TOTAL'          => $total_general
    ];

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'data' => $valores
    ]);
}
