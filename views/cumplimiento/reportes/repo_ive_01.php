<?php
error_reporting(0);
include __DIR__ . '/../../../includes/Config/config.php';
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

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ============================================================================
// RECEPCIÓN DE PARÁMETROS
// ============================================================================
$datos = $_POST["datosval"];
$inputs = $datos[0];      // Array de fechas [ffin, finicio, ffin2]
$selects = $datos[1];     // Array de selects [codofi]
$radios = $datos[2];      // Array de radios [ragencia, tipofecha]
$archivo = $datos[3];     // Nombre archivo y usuario
$tipo = $_POST["tipo"];   // 'xlsx', 'pdf' o 'preview'

// ============================================================================
// VALIDACIÓN DE PARÁMETROS
// ============================================================================
$tipofecha = isset($radios[1]) ? $radios[1] : 'corte';

if ($tipofecha === 'corte') {
    if (!validateDate($inputs[0], 'Y-m-d')) {
        echo json_encode(['mensaje' => 'Fecha de corte inválida', 'status' => 0]);
        return;
    }
    $ffin = $inputs[0];
    $finicio = null;
    $titlereport = " AL " . date("d-m-Y", strtotime($ffin));
} else {
    if (!validateDate($inputs[1], 'Y-m-d') || !validateDate($inputs[2], 'Y-m-d')) {
        echo json_encode(['mensaje' => 'Rango de fechas inválido', 'status' => 0]);
        return;
    }
    $finicio = $inputs[1];
    $ffin = $inputs[2];
    
    if (strtotime($finicio) > strtotime($ffin)) {
        echo json_encode(['mensaje' => 'La fecha inicial no puede ser mayor a la fecha final', 'status' => 0]);
        return;
    }
    $titlereport = " DEL " . date("d-m-Y", strtotime($finicio)) . " AL " . date("d-m-Y", strtotime($ffin));
}

if ($radios[0] == "anyofi" && $selects[0] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una agencia']);
    return;
}

// ============================================================================
// CONSTRUCCIÓN DE CONSULTA SQL
// ============================================================================
$filtro_agencia = ($radios[0] == "anyofi") ? " AND tu.id_agencia = " . intval($selects[0]) : "";

// Consulta simple para empleados activos
if ($tipofecha === 'corte') {
    $strquery = "
    SELECT 
        tu.id_usu AS codigo_empleado,
        CONCAT(IFNULL(tu.nombre,''), ' ', IFNULL(tu.apellido,'')) AS nombre_completo,
        IFNULL(tu.dpi, '') AS dpi,
        IFNULL(tu.puesto, 'Sin asignar') AS puesto,
        DATE(tu.created_at) AS fecha_ingreso,
        IFNULL(te.salario, 0) AS salario,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM tb_usuario tu
    LEFT JOIN tb_ejecutivos te ON te.id_usuario = tu.id_usu
    INNER JOIN tb_agencia ag ON tu.id_agencia = ag.id_agencia
    WHERE tu.estado = 1
        AND DATE(tu.created_at) <= ?
        {$filtro_agencia}
    ORDER BY tu.nombre, tu.apellido";
    
    $params = [$ffin];
    
} else {
    $strquery = "
    SELECT 
        tu.id_usu AS codigo_empleado,
        CONCAT(IFNULL(tu.nombre,''), ' ', IFNULL(tu.apellido,'')) AS nombre_completo,
        IFNULL(tu.dpi, '') AS dpi,
        IFNULL(tu.puesto, 'Sin asignar') AS puesto,
        DATE(tu.created_at) AS fecha_ingreso,
        IFNULL(te.salario, 0) AS salario,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM tb_usuario tu
    LEFT JOIN tb_ejecutivos te ON te.id_usuario = tu.id_usu
    INNER JOIN tb_agencia ag ON tu.id_agencia = ag.id_agencia
    WHERE tu.estado = 1
        AND DATE(tu.created_at) BETWEEN ? AND ?
        {$filtro_agencia}
    ORDER BY tu.nombre, tu.apellido";
    
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
        throw new Exception("No se encontraron empleados activos para el periodo seleccionado");
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
    echo json_encode(['status' => 0, 'mensaje' => $mensaje]);
    return;
}

// ============================================================================
// GENERAR REPORTE SEGÚN TIPO
// ============================================================================
switch ($tipo) {
    case 'preview':
        // Formatear datos para la tabla de vista previa
        // Campos esperados: nombre, puesto, fecha_ingreso, salario, productos_vigentes, saldos, agencia
        $dataFormatted = array_map(function($row) {
            return [
                'nombre' => $row['nombre_completo'],
                'puesto' => $row['puesto'],
                'fecha_ingreso' => date('d/m/Y', strtotime($row['fecha_ingreso'])),
                'salario' => floatval($row['salario']),
                'productos_vigentes' => 'N/A', // Por ahora no calculamos productos
                'saldos' => 0, // Por ahora no calculamos saldos
                'agencia' => $row['agencia']
            ];
        }, $result);
        
        echo json_encode([
            'status' => 1,
            'data' => $dataFormatted,
            'mensaje' => 'Datos cargados correctamente',
            'total' => count($dataFormatted)
        ]);
        break;
        
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
    $oficina = $info[0]["nom_agencia"] ?? '';
    $institucion = $info[0]["nomb_comple"] ?? '';
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - EMPLEADOS ACTIVOS');
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
    $sheet->setCellValue('A' . $fila, 'CÓDIGO');
    $sheet->setCellValue('B' . $fila, 'NOMBRE COMPLETO');
    $sheet->setCellValue('C' . $fila, 'DPI');
    $sheet->setCellValue('D' . $fila, 'PUESTO');
    $sheet->setCellValue('E' . $fila, 'FECHA INGRESO');
    $sheet->setCellValue('F' . $fila, 'SALARIO');
    $sheet->setCellValue('G' . $fila, 'AGENCIA');
    
    $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    $total_salario = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['codigo_empleado'] ?? '');
        $sheet->setCellValue('B' . $fila, $reg['nombre_completo'] ?? '');
        $sheet->setCellValueExplicit('C' . $fila, $reg['dpi'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('D' . $fila, $reg['puesto'] ?? '');
        $sheet->setCellValue('E' . $fila, date('d/m/Y', strtotime($reg['fecha_ingreso'])));
        $sheet->setCellValue('F' . $fila, floatval($reg['salario']));
        $sheet->setCellValue('G' . $fila, $reg['agencia'] ?? '');
        
        $total_salario += floatval($reg['salario']);
        $fila++;
    }
    
    // ========== FILA DE TOTALES ==========
    $sheet->setCellValue('A' . $fila, 'TOTAL:');
    $sheet->mergeCells('A' . $fila . ':E' . $fila);
    $sheet->setCellValue('F' . $fila, $total_salario);
    $sheet->getStyle('A' . $fila . ':G' . $fila)->getFont()->setBold(true);
    $sheet->getStyle('A' . $fila . ':G' . $fila)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('E7E6E6');
    
    // Formato de número para columna de salarios
    $sheet->getStyle('F8:F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(35);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(12);
    $sheet->getColumnDimension('G')->setWidth(25);
    
    // ========== BORDES ==========
    $ultimaFila = $fila;
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
    
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Repo_EmpleadosActivos_IVE" . str_replace(' ', '_', $titlereport),
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    ]);
    exit;
}

// ============================================================================
// FUNCIÓN PARA GENERAR PDF
// ============================================================================
function printpdf($registro, $titlereport, $info)
{
    $oficina = $info[0]["nom_agencia"] ?? '';
    $institucion = $info[0]["nomb_comple"] ?? '';
    $direccionins = $info[0]["muni_lug"] ?? '';
    $telefonosins = ($info[0]["tel_1"] ?? '') . '   ' . ($info[0]["tel_2"] ?? '');
    $nitins = $info[0]["nit"] ?? '';
    $rutalogoins = "../../.." . ($info[0]["log_img"] ?? '');
    
    $pdf = new FPDF('L', 'mm', 'Letter');
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    
    if (file_exists($rutalogoins)) {
        $pdf->Image($rutalogoins, 10, 8, 25);
    }
    
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, decode_utf8($institucion), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, decode_utf8($direccionins), 0, 1, 'C');
    $pdf->Cell(0, 5, 'NIT: ' . $nitins . ' | Tel: ' . $telefonosins, 0, 1, 'C');
    $pdf->Ln(3);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, decode_utf8('REPORTE IVE - EMPLEADOS ACTIVOS'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, decode_utf8('Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, decode_utf8('Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(15, 6, decode_utf8('CÓD'), 1, 0, 'C', true);
    $pdf->Cell(60, 6, 'NOMBRE COMPLETO', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'DPI', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'PUESTO', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'F. INGRESO', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'SALARIO', 1, 0, 'C', true);
    $pdf->Cell(61, 6, 'AGENCIA', 1, 1, 'C', true);
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $total_salario = 0;
    
    foreach ($registro as $reg) {
        $pdf->Cell(15, 5, $reg['codigo_empleado'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell(60, 5, decode_utf8(substr($reg['nombre_completo'] ?? '', 0, 45)), 1, 0, 'L', $fill);
        $pdf->Cell(30, 5, $reg['dpi'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell(40, 5, decode_utf8(substr($reg['puesto'] ?? '', 0, 25)), 1, 0, 'L', $fill);
        $pdf->Cell(25, 5, date('d/m/Y', strtotime($reg['fecha_ingreso'])), 1, 0, 'C', $fill);
        $pdf->Cell(25, 5, 'Q ' . number_format(floatval($reg['salario']), 2), 1, 0, 'R', $fill);
        $pdf->Cell(61, 5, decode_utf8(substr($reg['agencia'] ?? '', 0, 40)), 1, 1, 'L', $fill);
        
        $total_salario += floatval($reg['salario']);
        $fill = !$fill;
    }
    
    // ========== FILA DE TOTALES ==========
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(170, 6, 'TOTAL:', 1, 0, 'R', true);
    $pdf->Cell(25, 6, 'Q ' . number_format($total_salario, 2), 1, 0, 'R', true);
    $pdf->Cell(61, 6, '', 1, 1, 'C', true);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    $pdfData = $pdf->Output('S');
    
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Repo_EmpleadosActivos_IVE",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    ]);
}
