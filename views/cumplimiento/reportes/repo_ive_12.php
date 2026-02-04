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
// Estructura esperada desde view003.php:
// reportes([['ffin', 'finicio', 'ffin'], ['codofi'], ['ragencia'], []], 'xlsx/pdf', 'repo_ive_12', 1/0)
// 
// $datos[0] = inputs de fecha: [ffin, finicio, ffin]
// $datos[1] = select: [codofi]
// $datos[2] = radios: [ragencia]
// $datos[3] = extras (puede estar vacío)
$datos = $_POST["datosval"];
$inputs = $datos[0];      // Array de fechas
$selects = $datos[1];     // Array de selects (codofi)
$radios = $datos[2];      // Array de radios (ragencia)
$archivo = isset($datos[3]) && !empty($datos[3]) ? $datos[3] : ['usuario_sistema'];
$tipo = $_POST["tipo"];   // 'xlsx', 'pdf' o 'preview'

// ============================================================================
// VALIDACIÓN DE PARÁMETROS
// ============================================================================
// Este reporte solo usa rango de fechas (no fecha de corte)
// Estructura esperada: [ffin, finicio, ffin2] o [finicio, ffin]
if (count($inputs) >= 2) {
    // Si viene con 3 elementos [ffin, finicio, ffin2], usar finicio y ffin2
    if (count($inputs) >= 3 && !empty($inputs[1]) && !empty($inputs[2])) {
        $finicio = $inputs[1];
        $ffin = $inputs[2];
    } else {
        // Si viene con 2 elementos [finicio, ffin]
        $finicio = $inputs[0];
        $ffin = $inputs[1];
    }
} else {
    echo json_encode(['mensaje' => 'Debe proporcionar rango de fechas', 'status' => 0]);
    return;
}

// Validar fechas
if (!validateDate($finicio, 'Y-m-d') || !validateDate($ffin, 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Rango de fechas inválido', 'status' => 0]);
    return;
}

// Validar que finicio <= ffin
if (strtotime($finicio) > strtotime($ffin)) {
    echo json_encode(['mensaje' => 'La fecha inicial no puede ser mayor a la fecha final', 'status' => 0]);
    return;
}

$titlereport = " DEL " . date("d-m-Y", strtotime($finicio)) . " AL " . date("d-m-Y", strtotime($ffin));

// Validar agencia si se seleccionó "Por Agencia"
if ($radios[0] == "anyofi" && (empty($selects[0]) || $selects[0] == 0)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una agencia']);
    return;
}

// ============================================================================
// INCLUIR HELPER DE MONEDA
// ============================================================================
require_once __DIR__ . '/../../../includes/Config/CurrencyHelper.php';
$currencyConfig = CurrencyHelper::getCurrencyConfig();
$tasaCambio = $currencyConfig['conversion_rate']; // 7.8 por defecto
$limiteUSD = 10000.00;
$limiteGTQ = $limiteUSD * $tasaCambio; // 78,000 GTQ

// ============================================================================
// CONSTRUCCIÓN DE FILTROS SQL
// ============================================================================
$filtro_agencia = ($radios[0] == "anyofi") ? " AND cli.agencia = " . intval($selects[0]) : "";

// ============================================================================
// CONSULTA SQL - OPERACIONES EN EFECTIVO >= $10,000 USD
// ============================================================================
// UNION de operaciones de ahorro y aportación en efectivo
// NOTA: Este reporte solo usa rango de fechas (no fecha de corte)
$strquery = "
-- OPERACIONES DE AHORRO EN EFECTIVO
SELECT 
    mov.dfecope AS fecha_operacion,
    DATE_FORMAT(mov.dfecope, '%Y-%m') AS mes,
    'Ahorro' AS tipo_producto,
    CASE 
        WHEN mov.ctipope = 'D' THEN 'Depósito'
        WHEN mov.ctipope = 'R' THEN 'Retiro'
        ELSE mov.ctipope
    END AS tipo_operacion,
    cli.idcod_cliente AS codigo_cliente,
    cli.compl_name AS nombre_cliente,
    aho.ccodaho AS numero_producto,
    COALESCE(aht.nombre, 'Cuenta de Ahorro') AS nombre_producto,
    mov.monto AS monto_original,
    CASE 
        WHEN SUBSTR(aho.ccodaho, 1, 1) = '2' THEN 'USD'
        ELSE 'GTQ'
    END AS moneda,
    CASE 
        WHEN SUBSTR(aho.ccodaho, 1, 1) = '2' THEN mov.monto
        ELSE mov.monto / ?
    END AS monto_usd_equivalente,
    CASE 
        WHEN SUBSTR(aho.ccodaho, 1, 1) = '2' THEN mov.monto * ?
        ELSE mov.monto
    END AS monto_gtq_equivalente,
    COALESCE(CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia), 'Sin agencia') AS agencia,
    ag.id_agencia AS id_agencia
FROM ahommov mov
INNER JOIN ahomcta aho ON mov.ccodaho = aho.ccodaho
INNER JOIN tb_cliente cli ON aho.ccodcli = cli.idcod_cliente
LEFT JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN ahomtip aht ON SUBSTR(aho.ccodaho, 7, 2) = aht.ccodtip
WHERE mov.ctipope IN ('D', 'R')  -- Depósitos y Retiros
    AND mov.ctipdoc = 'E'        -- Solo operaciones en EFECTIVO
    AND mov.cestado != 2         -- No eliminados
    AND cli.estado = 1
    AND mov.dfecope BETWEEN ? AND ?
    AND (
        (SUBSTR(aho.ccodaho, 1, 1) = '2' AND mov.monto >= ?)
        OR 
        (SUBSTR(aho.ccodaho, 1, 1) != '2' AND mov.monto >= ?)
    )
    {$filtro_agencia}

UNION ALL

-- OPERACIONES DE APORTACIÓN EN EFECTIVO
SELECT 
    mov.dfecope AS fecha_operacion,
    DATE_FORMAT(mov.dfecope, '%Y-%m') AS mes,
    'Aportación' AS tipo_producto,
    CASE 
        WHEN mov.ctipope = 'D' THEN 'Depósito'
        WHEN mov.ctipope = 'R' THEN 'Retiro'
        ELSE mov.ctipope
    END AS tipo_operacion,
    cli.idcod_cliente AS codigo_cliente,
    cli.compl_name AS nombre_cliente,
    apr.ccodaport AS numero_producto,
    COALESCE(apt.nombre, 'Cuenta de Aportación') AS nombre_producto,
    mov.monto AS monto_original,
    CASE 
        WHEN SUBSTR(apr.ccodaport, 1, 1) = '2' THEN 'USD'
        ELSE 'GTQ'
    END AS moneda,
    CASE 
        WHEN SUBSTR(apr.ccodaport, 1, 1) = '2' THEN mov.monto
        ELSE mov.monto / ?
    END AS monto_usd_equivalente,
    CASE 
        WHEN SUBSTR(apr.ccodaport, 1, 1) = '2' THEN mov.monto * ?
        ELSE mov.monto
    END AS monto_gtq_equivalente,
    COALESCE(CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia), 'Sin agencia') AS agencia,
    ag.id_agencia AS id_agencia
FROM aprmov mov
INNER JOIN aprcta apr ON mov.ccodaport = apr.ccodaport
INNER JOIN tb_cliente cli ON apr.ccodcli = cli.idcod_cliente
LEFT JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN aprtip apt ON SUBSTR(apr.ccodaport, 7, 2) = apt.ccodtip
WHERE mov.ctipope IN ('D', 'R')  -- Depósitos y Retiros
    AND mov.ctipdoc = 'E'        -- Solo operaciones en EFECTIVO
    AND mov.cestado != 2         -- No eliminados
    AND cli.estado = 1
    AND mov.dfecope BETWEEN ? AND ?
    AND (
        (SUBSTR(apr.ccodaport, 1, 1) = '2' AND mov.monto >= ?)
        OR 
        (SUBSTR(apr.ccodaport, 1, 1) != '2' AND mov.monto >= ?)
    )
    {$filtro_agencia}

ORDER BY mes, id_agencia, fecha_operacion, tipo_producto";

// Parámetros: tasaCambio (aho), tasaCambio (aho), finicio, ffin, limiteUSD, limiteGTQ, tasaCambio (apr), tasaCambio (apr), finicio, ffin, limiteUSD, limiteGTQ
$params = [
    $tasaCambio, $tasaCambio, $finicio, $ffin, $limiteUSD, $limiteGTQ,
    $tasaCambio, $tasaCambio, $finicio, $ffin, $limiteUSD, $limiteGTQ
];

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
    case 'preview':
        // Vista previa - devolver JSON con los datos
        $dataFormatted = array_map(function($row) {
            return [
                'fecha_operacion' => date('d/m/Y', strtotime($row['fecha_operacion'])),
                'tipo_operacion' => $row['tipo_operacion'],
                'codigo_cliente' => $row['codigo_cliente'],
                'nombre_cliente' => $row['nombre_cliente'],
                'tipo_producto' => $row['tipo_producto'],
                'numero_producto' => $row['numero_producto'],
                'monto_usd' => floatval($row['monto_usd_equivalente']),
                'monto_gtq' => floatval($row['monto_gtq_equivalente']),
                'total_usd_equivalente' => floatval($row['monto_usd_equivalente']),
                'nivel_riesgo' => '', // Campo pendiente de implementar
                'agencia' => $row['agencia']
            ];
        }, $result);
        
        echo json_encode([
            'status' => 1,
            'mensaje' => 'Datos cargados correctamente',
            'data' => $dataFormatted,
            'total' => count($dataFormatted)
        ]);
        break;
        
    case 'xlsx':
        $usuario = is_array($archivo) && isset($archivo[0]) ? $archivo[0] : 'usuario_sistema';
        printxls($result, $titlereport, $usuario, $info, $tasaCambio);
        break;
    case 'pdf':
        printpdf($result, $titlereport, $info, $tasaCambio);
        break;
}

// ============================================================================
// FUNCIÓN PARA GENERAR EXCEL
// ============================================================================
function printxls($registro, $titlereport, $usuario, $info, $tasaCambio)
{
    $oficina = $info[0]["nom_agencia"] ?? '';
    $institucion = $info[0]["nomb_comple"] ?? '';
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - OPERACIONES EN EFECTIVO >= $10,000 USD');
    $sheet->mergeCells('A2:K2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Periodo: ' . $titlereport);
    $sheet->mergeCells('A3:K3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Agencia: ' . $oficina);
    $sheet->mergeCells('A4:K4');
    
    $sheet->setCellValue('A5', 'Fecha de generación: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A5:K5');
    
    $sheet->setCellValue('A6', 'Tasa de cambio: 1 USD = ' . number_format($tasaCambio, 2) . ' GTQ');
    $sheet->mergeCells('A6:K6');
    
    // ========== ENCABEZADOS DE COLUMNAS (FILA 8) ==========
    $fila = 8;
    $sheet->setCellValue('A' . $fila, 'Mes');
    $sheet->setCellValue('B' . $fila, 'Fecha Operación');
    $sheet->setCellValue('C' . $fila, 'Tipo Operación');
    $sheet->setCellValue('D' . $fila, 'Código Cliente');
    $sheet->setCellValue('E' . $fila, 'Nombre Cliente');
    $sheet->setCellValue('F' . $fila, 'Tipo Producto');
    $sheet->setCellValue('G' . $fila, 'Número Producto');
    $sheet->setCellValue('H' . $fila, 'Monto Original');
    $sheet->setCellValue('I' . $fila, 'Moneda');
    $sheet->setCellValue('J' . $fila, 'Monto USD Equivalente');
    $sheet->setCellValue('K' . $fila, 'Agencia');
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':K' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 9;
    $totalUSD = 0;
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['mes']);
        $sheet->setCellValue('B' . $fila, date('d/m/Y', strtotime($reg['fecha_operacion'])));
        $sheet->setCellValue('C' . $fila, $reg['tipo_operacion']);
        $sheet->setCellValue('D' . $fila, $reg['codigo_cliente']);
        $sheet->setCellValue('E' . $fila, $reg['nombre_cliente']);
        $sheet->setCellValue('F' . $fila, $reg['tipo_producto']);
        $sheet->setCellValue('G' . $fila, $reg['numero_producto']);
        $sheet->setCellValue('H' . $fila, number_format(floatval($reg['monto_original']), 2, '.', ','));
        $sheet->setCellValue('I' . $fila, $reg['moneda']);
        $sheet->setCellValue('J' . $fila, number_format(floatval($reg['monto_usd_equivalente']), 2, '.', ','));
        $sheet->setCellValue('K' . $fila, $reg['agencia']);
        
        $totalUSD += floatval($reg['monto_usd_equivalente']);
        $fila++;
    }
    
    // ========== TOTALES ==========
    $fila++;
    $sheet->setCellValue('I' . $fila, 'TOTAL USD:');
    $sheet->setCellValue('J' . $fila, number_format($totalUSD, 2, '.', ','));
    $sheet->getStyle('I' . $fila . ':J' . $fila)->getFont()->setBold(true);
    $sheet->getStyle('I' . $fila . ':J' . $fila)->applyFromArray([
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']]
    ]);
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFila = $fila;
    $sheet->getStyle('A8:K' . $ultimaFila)->applyFromArray([
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
        'namefile' => "Reporte_IVE_OperacionesEfectivo" . str_replace([' ', '/'], ['_', '-'], $titlereport),
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}

// ============================================================================
// FUNCIÓN PARA GENERAR PDF
// ============================================================================
function printpdf($registro, $titlereport, $info, $tasaCambio)
{
    $oficina = $info[0]["nom_agencia"] ?? '';
    $institucion = $info[0]["nomb_comple"] ?? '';
    $direccionins = $info[0]["muni_lug"] ?? '';
    $emailins = $info[0]["emai"] ?? '';
    $telefonosins = ($info[0]["tel_1"] ?? '') . '   ' . ($info[0]["tel_2"] ?? '');
    $nitins = $info[0]["nit"] ?? '';
    $rutalogoins = "../../../.." . ($info[0]["log_img"] ?? '');
    
    $pdf = new FPDF('L', 'mm', 'Letter'); // Orientación horizontal
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    
    // ========== LOGO Y ENCABEZADO ==========
    if (file_exists($rutalogoins)) {
        $pdf->Image($rutalogoins, 10, 8, 25);
    }
    
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $institucion), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $direccionins), 0, 1, 'C');
    $pdf->Cell(0, 5, 'NIT: ' . $nitins . ' | Tel: ' . $telefonosins, 0, 1, 'C');
    $pdf->Ln(3);
    
    // ========== TÍTULO DEL REPORTE ==========
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'REPORTE IVE - OPERACIONES EN EFECTIVO >= $10,000 USD'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Cell(0, 5, 'Tasa de cambio: 1 USD = ' . number_format($tasaCambio, 2) . ' GTQ', 0, 1, 'C');
    $pdf->Ln(3);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(18, 6, 'Mes', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'Tipo Op.', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Codigo', 1, 0, 'C', true);
    $pdf->Cell(45, 6, 'Nombre Cliente', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'Producto', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Numero', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Monto Orig.', 1, 0, 'C', true);
    $pdf->Cell(15, 6, 'Moneda', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'USD Equiv.', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'Agencia', 1, 1, 'C', true);
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $totalUSD = 0;
    
    foreach ($registro as $reg) {
        if ($pdf->GetY() > 180) { // Nueva página si se acerca al final
            $pdf->AddPage();
            // Repetir encabezados
            $pdf->SetFont('Arial', 'B', 6);
            $pdf->SetFillColor(68, 114, 196);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(18, 6, 'Mes', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'Fecha', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'Tipo Op.', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Codigo', 1, 0, 'C', true);
            $pdf->Cell(45, 6, 'Nombre Cliente', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'Producto', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Numero', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Monto Orig.', 1, 0, 'C', true);
            $pdf->Cell(15, 6, 'Moneda', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'USD Equiv.', 1, 0, 'C', true);
            $pdf->Cell(30, 6, 'Agencia', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 5);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        $pdf->Cell(18, 4, $reg['mes'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell(20, 4, date('d/m/Y', strtotime($reg['fecha_operacion'])), 1, 0, 'C', $fill);
        $pdf->Cell(20, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['tipo_operacion'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell(25, 4, $reg['codigo_cliente'] ?? '', 1, 0, 'L', $fill);
        $pdf->Cell(45, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['nombre_cliente'] ?? '', 0, 30)), 1, 0, 'L', $fill);
        $pdf->Cell(20, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['tipo_producto'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell(25, 4, $reg['numero_producto'] ?? '', 1, 0, 'L', $fill);
        $pdf->Cell(25, 4, number_format(floatval($reg['monto_original'] ?? 0), 2), 1, 0, 'R', $fill);
        $pdf->Cell(15, 4, $reg['moneda'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell(25, 4, number_format(floatval($reg['monto_usd_equivalente'] ?? 0), 2), 1, 0, 'R', $fill);
        $pdf->Cell(30, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['agencia'] ?? '', 0, 20)), 1, 1, 'L', $fill);
        
        $totalUSD += floatval($reg['monto_usd_equivalente'] ?? 0);
        $fill = !$fill;
    }
    
    // ========== TOTALES ==========
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(0, 5, 'TOTAL USD EQUIVALENTE: $' . number_format($totalUSD, 2, '.', ','), 0, 1, 'R');
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    $pdfData = $pdf->Output('S');
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reporte_IVE_OperacionesEfectivo" . str_replace([' ', '/'], ['_', '-'], $titlereport),
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
