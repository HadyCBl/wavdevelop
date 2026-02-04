<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

include '../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ============================================================================
// RECEPCIÓN DE PARÁMETROS
// ============================================================================
// Estructura esperada desde view002.php:
// reportes([['ffin', 'finicio', 'ffin2'], ['tipofecha'], ['codofi'], ['ragencia'], []], 'xlsx/pdf', 'Repo_XXX_IVE', 1/0)
// 
// $datos[0] = inputs de fecha: [ffin, finicio, ffin2]
// $datos[1] = select: [codofi]
// $datos[2] = radios: [ragencia, tipofecha]
// $datos[3] = archivo y usuario
$datos = $_POST["datosval"];
$inputs = $datos[0];      // Array de fechas
$selects = $datos[1];     // Array de selects (codofi)
$radios = $datos[2];      // Array de radios (ragencia, tipofecha)
$archivo = $datos[3];     // Nombre archivo y usuario
$tipo = $_POST["tipo"];   // 'xlsx' o 'pdf'

// ============================================================================
// VALIDACIÓN DE PARÁMETROS
// ============================================================================
// Determinar si es fecha de corte o rango
$tipofecha = isset($radios[1]) ? $radios[1] : 'corte'; // 'corte' o 'rango'

if ($tipofecha === 'corte') {
    // Validar solo fecha de corte (ffin)
    if (!validateDate($inputs[0], 'Y-m-d')) {
        echo json_encode(['mensaje' => 'Fecha de corte inválida', 'status' => 0]);
        return;
    }
    $ffin = $inputs[0];
    $finicio = null;
    $titlereport = " AL " . date("d-m-Y", strtotime($ffin));
} else {
    // Validar rango de fechas (finicio y ffin2)
    if (!validateDate($inputs[1], 'Y-m-d') || !validateDate($inputs[2], 'Y-m-d')) {
        echo json_encode(['mensaje' => 'Rango de fechas inválido', 'status' => 0]);
        return;
    }
    $finicio = $inputs[1];
    $ffin = $inputs[2];
    
    // Validar que finicio <= ffin
    if (strtotime($finicio) > strtotime($ffin)) {
        echo json_encode(['mensaje' => 'La fecha inicial no puede ser mayor a la fecha final', 'status' => 0]);
        return;
    }
    $titlereport = " DEL " . date("d-m-Y", strtotime($finicio)) . " AL " . date("d-m-Y", strtotime($ffin));
}

// Validar agencia si se seleccionó "Por Agencia"
if ($radios[0] == "poragencia" && $selects[0] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una agencia']);
    return;
}

// ============================================================================
// CONSTRUCCIÓN DE FILTROS SQL
// ============================================================================
$filagencia = ($radios[0] == "poragencia") ? " AND tabla.CODAgencia = " . $selects[0] : "";

// AQUÍ AGREGARÁS TUS FILTROS ESPECÍFICOS POR REPORTE
// Ejemplo:
// $filtro_especifico = " AND tabla.campo = 'valor' ";

// ============================================================================
// CONSULTA SQL BASE (MODIFICA SEGÚN CADA REPORTE)
// ============================================================================
if ($tipofecha === 'corte') {
    // Consulta para fecha de corte
    $strquery = "SELECT 
        -- AQUÍ VAN TUS CAMPOS ESPECÍFICOS DEL REPORTE
        tabla.campo1,
        tabla.campo2,
        tabla.campo3
    FROM tu_tabla_principal tabla
    WHERE tabla.fecha <= ? 
    {$filagencia}
    ORDER BY tabla.campo1";
    
    $params = [$ffin];
} else {
    // Consulta para rango de fechas
    $strquery = "SELECT 
        -- AQUÍ VAN TUS CAMPOS ESPECÍFICOS DEL REPORTE
        tabla.campo1,
        tabla.campo2,
        tabla.campo3
    FROM tu_tabla_principal tabla
    WHERE tabla.fecha BETWEEN ? AND ?
    {$filagencia}
    ORDER BY tabla.campo1";
    
    $params = [$finicio, $ffin];
}

// ============================================================================
// EJECUCIÓN DE CONSULTA
// ============================================================================
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, $params);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros para el periodo seleccionado");
    }

    // Obtener información de la institución/agencia
    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
                                WHERE ag.id_agencia=?", [$idagencia]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institución asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

// ============================================================================
// GENERAR REPORTE SEGÚN TIPO
// ============================================================================
switch ($tipo) {
    case 'xlsx':
        printxls($result, $titlereport, $archivo[0], $info);
        break;
    case 'pdf':
        printpdf($result, $titlereport, $info);
        break;
}

// ============================================================================
// FUNCIÓN PARA GENERAR EXCEL
// ============================================================================
function printxls($registro, $titlereport, $usuario, $info)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - [NOMBRE DEL REPORTE]');
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Periodo: ' . $titlereport);
    $sheet->mergeCells('A3:G3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Agencia: ' . $oficina);
    $sheet->mergeCells('A4:G4');
    
    $sheet->setCellValue('A5', 'Fecha de generación: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A5:G5');
    
    // ========== ENCABEZADOS DE COLUMNAS (FILA 7) ==========
    $fila = 7;
    $sheet->setCellValue('A' . $fila, 'COLUMNA 1');
    $sheet->setCellValue('B' . $fila, 'COLUMNA 2');
    $sheet->setCellValue('C' . $fila, 'COLUMNA 3');
    $sheet->setCellValue('D' . $fila, 'COLUMNA 4');
    $sheet->setCellValue('E' . $fila, 'COLUMNA 5');
    $sheet->setCellValue('F' . $fila, 'COLUMNA 6');
    $sheet->setCellValue('G' . $fila, 'COLUMNA 7');
    // AGREGAR MÁS COLUMNAS SEGÚN NECESITES
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['campo1']);
        $sheet->setCellValue('B' . $fila, $reg['campo2']);
        $sheet->setCellValue('C' . $fila, $reg['campo3']);
        $sheet->setCellValue('D' . $fila, $reg['campo4']);
        $sheet->setCellValue('E' . $fila, $reg['campo5']);
        $sheet->setCellValue('F' . $fila, $reg['campo6']);
        $sheet->setCellValue('G' . $fila, $reg['campo7']);
        // AGREGAR MÁS DATOS SEGÚN COLUMNAS
        
        $fila++;
    }
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFila = $fila - 1;
    $sheet->getStyle('A7:G' . $ultimaFila)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
    
    // ========== GENERAR ARCHIVO ==========
    ob_start();
    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reporte_IVE" . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}

// ============================================================================
// FUNCIÓN PARA GENERAR PDF
// ============================================================================
function printpdf($registro, $titlereport, $info)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogoins = "../../../.." . $info[0]["log_img"];
    
    $pdf = new FPDF('L', 'mm', 'Letter'); // Orientación horizontal para más columnas
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    
    // ========== LOGO Y ENCABEZADO ==========
    if (file_exists($rutalogoins)) {
        $pdf->Image($rutalogoins, 10, 8, 25);
    }
    
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, Utf8::decode($institucion), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, Utf8::decode($direccionins), 0, 1, 'C');
    $pdf->Cell(0, 5, 'NIT: ' . $nitins . ' | Tel: ' . $telefonosins, 0, 1, 'C');
    $pdf->Ln(3);
    
    // ========== TÍTULO DEL REPORTE ==========
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, Utf8::decode('REPORTE IVE - [NOMBRE DEL REPORTE]'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, Utf8::decode('Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, Utf8::decode('Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(30, 6, 'COLUMNA 1', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'COLUMNA 2', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'COLUMNA 3', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'COLUMNA 4', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'COLUMNA 5', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'COLUMNA 6', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'COLUMNA 7', 1, 1, 'C', true);
    // AGREGAR MÁS COLUMNAS SEGÚN NECESITES
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    
    foreach ($registro as $reg) {
        $pdf->Cell(30, 5, Utf8::decode($reg['campo1']), 1, 0, 'L', $fill);
        $pdf->Cell(30, 5, Utf8::decode($reg['campo2']), 1, 0, 'L', $fill);
        $pdf->Cell(30, 5, Utf8::decode($reg['campo3']), 1, 0, 'L', $fill);
        $pdf->Cell(30, 5, Utf8::decode($reg['campo4']), 1, 0, 'L', $fill);
        $pdf->Cell(30, 5, Utf8::decode($reg['campo5']), 1, 0, 'R', $fill);
        $pdf->Cell(30, 5, Utf8::decode($reg['campo6']), 1, 0, 'R', $fill);
        $pdf->Cell(30, 5, Utf8::decode($reg['campo7']), 1, 1, 'L', $fill);
        // AGREGAR MÁS DATOS SEGÚN COLUMNAS
        
        $fill = !$fill; // Alternar color de fondo
    }
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    ob_start();
    $pdf->Output('S');
    $pdfData = ob_get_contents();
    ob_end_clean();
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reporte_IVE",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
