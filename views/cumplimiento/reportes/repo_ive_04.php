<?php
/**
 * REPORTE IVE 04 - CUENTAS DE CAPTACIÓN APERTURADAS
 * 
 * Este reporte muestra todas las cuentas de ahorro aperturadas en un rango de fechas,
 * incluyendo: número cuenta, tipo cuenta, nombre cliente, tipo persona, fecha apertura,
 * monto apertura (primer depósito), saldo actual y agencia.
 */
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
// Estructura desde view002.php:
// reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx/pdf', 'Repo_CuentasAperturadas_IVE', 1/0)
$datos = $_POST["datosval"];
$inputs = $datos[0];      // Array de fechas [finicio, ffin]
$selects = $datos[1];     // Array de selects [codofi]
$radios = $datos[2];      // Array de radios [ragencia]
$archivo = $datos[3];     // Nombre archivo y usuario
$tipo = $_POST["tipo"];   // 'xlsx' o 'pdf'

// ============================================================================
// VALIDACIÓN DE PARÁMETROS
// ============================================================================
// Este reporte siempre usa rango de fechas
$finicio = $inputs[0];
$ffin = $inputs[1];

if (!validateDate($finicio, 'Y-m-d') || !validateDate($ffin, 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Rango de fechas inválido', 'status' => 0]);
    return;
}

if (strtotime($finicio) > strtotime($ffin)) {
    echo json_encode(['mensaje' => 'La fecha inicial no puede ser mayor a la fecha final', 'status' => 0]);
    return;
}

$titlereport = " DEL " . date("d-m-Y", strtotime($finicio)) . " AL " . date("d-m-Y", strtotime($ffin));

// Validar agencia si se seleccionó "Por Agencia"
if ($radios[0] == "anyofi" && $selects[0] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una agencia']);
    return;
}

// ============================================================================
// CONSTRUCCIÓN DE FILTROS SQL
// ============================================================================
$filtro_agencia = ($radios[0] == "anyofi") ? " AND cli.agencia = " . intval($selects[0]) : "";

// ============================================================================
// CONSULTA SQL - CUENTAS DE AHORRO APERTURADAS EN EL PERIODO
// ============================================================================
$strquery = "
SELECT 
    aho.ccodaho AS numero_cuenta,
    COALESCE(aht.nombre, 'Cuenta de Ahorro') AS tipo_cuenta,
    cli.compl_name AS nombre_cliente,
    cli.id_tipoCliente AS tipo_persona,
    aho.fecha_apertura,
    COALESCE(
        (SELECT mov.monto 
         FROM ahommov mov 
         WHERE mov.ccodaho = aho.ccodaho 
           AND mov.ctipope = 'D' 
           AND mov.cestado = 1
         ORDER BY mov.dfecope ASC, mov.id_mov ASC 
         LIMIT 1), 
        0
    ) AS monto_apertura,
    calcular_saldo_aho_tipcuenta(aho.ccodaho, ?) AS saldo_actual,
    COALESCE(CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia), 'Sin agencia') AS agencia
FROM ahomcta aho
INNER JOIN tb_cliente cli ON aho.ccodcli = cli.idcod_cliente
LEFT JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN ahomtip aht ON SUBSTR(aho.ccodaho, 7, 2) = aht.ccodtip
WHERE aho.fecha_apertura BETWEEN ? AND ?
    AND cli.estado = 1
    {$filtro_agencia}
ORDER BY aho.fecha_apertura DESC, cli.compl_name ASC
";

// Parámetros: fecha_saldo, fecha_inicio, fecha_fin
$params = [$ffin, $finicio, $ffin];

// ============================================================================
// EJECUCIÓN DE CONSULTA
// ============================================================================
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, $params);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron cuentas aperturadas en el periodo seleccionado");
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
    case 'preview':
        // Formatear datos para la tabla de vista previa
        // Campos esperados: numero_cuenta, tipo_cuenta, nombre_cliente, tipo_persona, 
        //                   fecha_apertura, monto_apertura, saldo_actual, agencia
        $dataFormatted = array_map(function($row) {
            return [
                'numero_cuenta' => $row['numero_cuenta'],
                'tipo_cuenta' => $row['tipo_cuenta'],
                'nombre_cliente' => $row['nombre_cliente'],
                'tipo_persona' => $row['tipo_persona'] == 'Individual' ? 'Individual' : 'Jurídica',
                'fecha_apertura' => date('d/m/Y', strtotime($row['fecha_apertura'])),
                'monto_apertura' => floatval($row['monto_apertura']),
                'saldo_actual' => floatval($row['saldo_actual']),
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
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - CUENTAS DE CAPTACIÓN APERTURADAS');
    $sheet->mergeCells('A2:H2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Periodo: ' . $titlereport);
    $sheet->mergeCells('A3:H3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Agencia: ' . $oficina);
    $sheet->mergeCells('A4:H4');
    
    $sheet->setCellValue('A5', 'Fecha de generación: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A5:H5');
    
    // ========== ENCABEZADOS DE COLUMNAS (FILA 7) ==========
    $fila = 7;
    $sheet->setCellValue('A' . $fila, 'NÚMERO CUENTA');
    $sheet->setCellValue('B' . $fila, 'TIPO CUENTA');
    $sheet->setCellValue('C' . $fila, 'NOMBRE CLIENTE');
    $sheet->setCellValue('D' . $fila, 'TIPO PERSONA');
    $sheet->setCellValue('E' . $fila, 'FECHA APERTURA');
    $sheet->setCellValue('F' . $fila, 'MONTO APERTURA');
    $sheet->setCellValue('G' . $fila, 'SALDO ACTUAL');
    $sheet->setCellValue('H' . $fila, 'AGENCIA');
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':H' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    $total_monto_apertura = 0;
    $total_saldo = 0;
    $count_natural = 0;
    $count_juridico = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValueExplicit('A' . $fila, $reg['numero_cuenta'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $fila, $reg['tipo_cuenta'] ?? '');
        $sheet->setCellValue('C' . $fila, $reg['nombre_cliente'] ?? '');
        $sheet->setCellValue('D' . $fila, $reg['tipo_persona'] ?? '');
        $sheet->setCellValue('E' . $fila, date('d/m/Y', strtotime($reg['fecha_apertura'])));
        $sheet->setCellValue('F' . $fila, floatval($reg['monto_apertura']));
        $sheet->setCellValue('G' . $fila, floatval($reg['saldo_actual']));
        $sheet->setCellValue('H' . $fila, $reg['agencia'] ?? '');
        
        // Acumular totales
        $total_monto_apertura += floatval($reg['monto_apertura']);
        $total_saldo += floatval($reg['saldo_actual']);
        
        // Contar por tipo de persona
        $tipo = strtoupper($reg['tipo_persona'] ?? '');
        if ($tipo == 'NATURAL' || $tipo == 'PERSONA NATURAL') {
            $count_natural++;
        } else {
            $count_juridico++;
        }
        
        $fila++;
    }
    
    // ========== FILA DE TOTALES ==========
    $sheet->setCellValue('A' . $fila, 'TOTAL:');
    $sheet->mergeCells('A' . $fila . ':E' . $fila);
    $sheet->setCellValue('F' . $fila, $total_monto_apertura);
    $sheet->setCellValue('G' . $fila, $total_saldo);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getFont()->setBold(true);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('E7E6E6');
    
    // Formato de número para columnas monetarias
    $sheet->getStyle('F8:G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // ========== RESUMEN ==========
    $fila += 2;
    $sheet->setCellValue('A' . $fila, 'RESUMEN:');
    $sheet->getStyle('A' . $fila)->getFont()->setBold(true);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Total cuentas aperturadas:');
    $sheet->setCellValue('B' . $fila, count($registro));
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Personas Naturales:');
    $sheet->setCellValue('B' . $fila, $count_natural);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Personas Jurídicas:');
    $sheet->setCellValue('B' . $fila, $count_juridico);
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    $sheet->getColumnDimension('A')->setWidth(18);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(40);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(18);
    $sheet->getColumnDimension('G')->setWidth(18);
    $sheet->getColumnDimension('H')->setWidth(30);
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFilaDatos = 7 + count($registro);
    $sheet->getStyle('A7:H' . $ultimaFilaDatos)->applyFromArray([
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
        'namefile' => "Reporte_IVE_Cuentas_Aperturadas" . str_replace(' ', '_', $titlereport),
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
    
    $pdf = new FPDF('L', 'mm', 'Letter'); // Orientación horizontal
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    
    // ========== LOGO Y ENCABEZADO ==========
    if (file_exists($rutalogoins)) {
        $pdf->Image($rutalogoins, 10, 8, 25);
    }
    
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, decode_utf8($institucion), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, decode_utf8($direccionins), 0, 1, 'C');
    $pdf->Cell(0, 5, 'NIT: ' . $nitins . ' | Tel: ' . $telefonosins, 0, 1, 'C');
    $pdf->Ln(3);
    
    // ========== TÍTULO DEL REPORTE ==========
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, decode_utf8('REPORTE IVE - CUENTAS DE CAPTACIÓN APERTURADAS'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, decode_utf8('Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, decode_utf8('Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(28, 6, 'NO. CUENTA', 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'TIPO CUENTA', 1, 0, 'C', true);
    $pdf->Cell(55, 6, 'NOMBRE CLIENTE', 1, 0, 'C', true);
    $pdf->Cell(22, 6, 'TIPO PERSONA', 1, 0, 'C', true);
    $pdf->Cell(22, 6, 'FECHA APERT.', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'MONTO APERT.', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'SALDO ACTUAL', 1, 0, 'C', true);
    $pdf->Cell(45, 6, 'AGENCIA', 1, 1, 'C', true);
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $total_monto = 0;
    $total_saldo = 0;
    
    foreach ($registro as $reg) {
        // Verificar salto de página
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            // Reimprimir encabezados
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(68, 114, 196);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(28, 6, 'NO. CUENTA', 1, 0, 'C', true);
            $pdf->Cell(35, 6, 'TIPO CUENTA', 1, 0, 'C', true);
            $pdf->Cell(55, 6, 'NOMBRE CLIENTE', 1, 0, 'C', true);
            $pdf->Cell(22, 6, 'TIPO PERSONA', 1, 0, 'C', true);
            $pdf->Cell(22, 6, 'FECHA APERT.', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'MONTO APERT.', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'SALDO ACTUAL', 1, 0, 'C', true);
            $pdf->Cell(45, 6, 'AGENCIA', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 6);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        }
        
        $monto = floatval($reg['monto_apertura']);
        $saldo = floatval($reg['saldo_actual']);
        $total_monto += $monto;
        $total_saldo += $saldo;
        
        $pdf->Cell(28, 5, $reg['numero_cuenta'], 1, 0, 'L', $fill);
        $pdf->Cell(35, 5, decode_utf8(substr($reg['tipo_cuenta'], 0, 25)), 1, 0, 'L', $fill);
        $pdf->Cell(55, 5, decode_utf8(substr($reg['nombre_cliente'], 0, 40)), 1, 0, 'L', $fill);
        $pdf->Cell(22, 5, decode_utf8($reg['tipo_persona']), 1, 0, 'C', $fill);
        $pdf->Cell(22, 5, date('d/m/Y', strtotime($reg['fecha_apertura'])), 1, 0, 'C', $fill);
        $pdf->Cell(25, 5, number_format($monto, 2), 1, 0, 'R', $fill);
        $pdf->Cell(25, 5, number_format($saldo, 2), 1, 0, 'R', $fill);
        $pdf->Cell(45, 5, decode_utf8(substr($reg['agencia'], 0, 30)), 1, 1, 'L', $fill);
        
        $fill = !$fill;
    }
    
    // ========== TOTALES ==========
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(162, 6, 'TOTALES:', 1, 0, 'R', true);
    $pdf->Cell(25, 6, number_format($total_monto, 2), 1, 0, 'R', true);
    $pdf->Cell(25, 6, number_format($total_saldo, 2), 1, 0, 'R', true);
    $pdf->Cell(45, 6, '', 1, 1, 'L', true);
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total cuentas: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    ob_start();
    $pdf->Output('S');
    $pdfData = ob_get_contents();
    ob_end_clean();
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reporte_IVE_Cuentas_Aperturadas",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
