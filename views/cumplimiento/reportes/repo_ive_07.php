<?php
/**
 * REPORTE IVE 07 - CUENTAS DE CAPTACIÓN CANCELADAS
 * 
 * Este reporte muestra todas las cuentas de ahorro canceladas en un rango de fechas,
 * incluyendo: tipo cuenta, moneda, nombre cliente, tipo persona, nacionalidad,
 * número cuenta, fecha apertura, fecha cancelación, monto apertura, saldo cancelación,
 * forma de cancelación y agencia.
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

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ============================================================================
// RECEPCIÓN DE PARÁMETROS
// ============================================================================
// Estructura desde view003.php:
// reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx/pdf', 'Repo_CuentasCanceladas_IVE', 1/0)
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
// CONSULTA SQL - CUENTAS DE AHORRO CANCELADAS EN EL PERIODO
// ============================================================================
$strquery = "
SELECT 
    COALESCE(aht.nombre, 'Cuenta de Ahorro') AS tipo_cuenta,
    'GTQ' AS moneda,
    cli.compl_name AS nombre_cliente,
    cli.id_tipoCliente AS tipo_persona,
    COALESCE(
        (SELECT p.nombre FROM tb_paises p WHERE p.abreviatura = cli.nacionalidad LIMIT 1),
        'Guatemala'
    ) AS nacionalidad,
    COALESCE(aht.nombre, 'Ahorro') AS tipo_producto,
    aho.ccodaho AS numero_cuenta,
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
    COALESCE(
        (SELECT 
            CASE mov.ctipdoc 
                WHEN 'E' THEN 'Efectivo'
                WHEN 'C' THEN 'Cheque'
                WHEN 'T' THEN 'Transferencia'
                ELSE 'Otro'
            END
         FROM ahommov mov 
         WHERE mov.ccodaho = aho.ccodaho 
           AND mov.ctipope = 'D' 
           AND mov.cestado = 1
         ORDER BY mov.dfecope ASC, mov.id_mov ASC 
         LIMIT 1), 
        'No registrado'
    ) AS forma_apertura,
    aho.fecha_cancel AS fecha_cancelacion,
    COALESCE(
        (SELECT ABS(mov.monto) 
         FROM ahommov mov 
         WHERE mov.ccodaho = aho.ccodaho 
           AND mov.ctipope = 'R'
           AND mov.cestado = 1
         ORDER BY mov.dfecope DESC, mov.id_mov DESC 
         LIMIT 1), 
        0
    ) AS saldo_cancelacion,
    COALESCE(
        (SELECT 
            CASE mov.ctipdoc 
                WHEN 'E' THEN 'Efectivo'
                WHEN 'C' THEN 'Cheque'
                WHEN 'T' THEN 'Transferencia'
                ELSE 'Otro'
            END
         FROM ahommov mov 
         WHERE mov.ccodaho = aho.ccodaho 
           AND mov.ctipope = 'R'
           AND mov.cestado = 1
         ORDER BY mov.dfecope DESC, mov.id_mov DESC 
         LIMIT 1), 
        'No registrado'
    ) AS forma_cancelacion,
    COALESCE(CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia), 'Sin agencia') AS agencia
FROM ahomcta aho
INNER JOIN tb_cliente cli ON aho.ccodcli = cli.idcod_cliente
LEFT JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN ahomtip aht ON SUBSTR(aho.ccodaho, 7, 2) = aht.ccodtip
WHERE aho.estado NOT IN ('A', '1')
    AND aho.fecha_cancel IS NOT NULL
    AND aho.fecha_cancel != '0000-00-00'
    AND aho.fecha_cancel BETWEEN ? AND ?
    {$filtro_agencia}
ORDER BY aho.fecha_cancel DESC, cli.compl_name ASC
";

// Parámetros: fecha_inicio_cancelacion, fecha_fin_cancelacion
$params = [$finicio, $ffin];

// ============================================================================
// EJECUCIÓN DE CONSULTA
// ============================================================================
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, $params);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron cuentas canceladas en el periodo seleccionado");
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
        // Campos: tipo_cuenta, moneda, nombre_cliente, tipo_persona, nacionalidad, tipo_producto,
        //         numero_cuenta, fecha_apertura, monto_apertura, forma_apertura,
        //         fecha_cancelacion, saldo_cancelacion, forma_cancelacion, agencia
        $dataFormatted = array_map(function($row) {
            return [
                'tipo_cuenta' => $row['tipo_cuenta'],
                'moneda' => $row['moneda'],
                'nombre_cliente' => $row['nombre_cliente'],
                'tipo_persona' => ($row['tipo_persona'] == 'Individual' || $row['tipo_persona'] == 'INDIVIDUAL') ? 'Individual' : 'Jurídica',
                'nacionalidad' => $row['nacionalidad'],
                'tipo_producto' => $row['tipo_producto'],
                'numero_cuenta' => $row['numero_cuenta'],
                'fecha_apertura' => $row['fecha_apertura'] ? date('d/m/Y', strtotime($row['fecha_apertura'])) : 'N/A',
                'monto_apertura' => floatval($row['monto_apertura']),
                'forma_apertura' => $row['forma_apertura'],
                'fecha_cancelacion' => $row['fecha_cancelacion'] ? date('d/m/Y', strtotime($row['fecha_cancelacion'])) : 'N/A',
                'saldo_cancelacion' => floatval($row['saldo_cancelacion']),
                'forma_cancelacion' => $row['forma_cancelacion'],
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
    $sheet->mergeCells('A1:L1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - CUENTAS DE CAPTACIÓN CANCELADAS');
    $sheet->mergeCells('A2:L2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Periodo: ' . $titlereport);
    $sheet->mergeCells('A3:O3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Agencia: ' . $oficina);
    $sheet->mergeCells('A4:O4');
    
    $sheet->setCellValue('A5', 'Fecha de generación: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A5:O5');
    
    // ========== ENCABEZADOS DE COLUMNAS (FILA 7) ==========
    $fila = 7;
    $sheet->setCellValue('A' . $fila, 'TIPO CUENTA');
    $sheet->setCellValue('B' . $fila, 'MONEDA');
    $sheet->setCellValue('C' . $fila, 'NOMBRE CLIENTE');
    $sheet->setCellValue('D' . $fila, 'TIPO PERSONA');
    $sheet->setCellValue('E' . $fila, 'NACIONALIDAD');
    $sheet->setCellValue('F' . $fila, 'TIPO PRODUCTO');
    $sheet->setCellValue('G' . $fila, 'NO. CUENTA');
    $sheet->setCellValue('H' . $fila, 'FECHA APERTURA');
    $sheet->setCellValue('I' . $fila, 'MONTO APERT.');
    $sheet->setCellValue('J' . $fila, 'FORMA APERT.');
    $sheet->setCellValue('K' . $fila, 'FECHA CANCEL.');
    $sheet->setCellValue('L' . $fila, 'MONTO CANCEL.');
    $sheet->setCellValue('M' . $fila, 'FORMA CANCEL.');
    $sheet->setCellValue('N' . $fila, 'AGENCIA');
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':N' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    $total_monto_apertura = 0;
    $total_saldo_cancel = 0;
    $count_efectivo = 0;
    $count_otro = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['tipo_cuenta'] ?? '');
        $sheet->setCellValue('B' . $fila, $reg['moneda'] ?? 'GTQ');
        $sheet->setCellValue('C' . $fila, $reg['nombre_cliente'] ?? '');
        $sheet->setCellValue('D' . $fila, ($reg['tipo_persona'] == 'Individual' || $reg['tipo_persona'] == 'INDIVIDUAL') ? 'Individual' : 'Jurídica');
        $sheet->setCellValue('E' . $fila, $reg['nacionalidad'] ?? '');
        $sheet->setCellValue('F' . $fila, $reg['tipo_producto'] ?? '');
        $sheet->setCellValueExplicit('G' . $fila, $reg['numero_cuenta'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('H' . $fila, $reg['fecha_apertura'] ? date('d/m/Y', strtotime($reg['fecha_apertura'])) : 'N/A');
        $sheet->setCellValue('I' . $fila, floatval($reg['monto_apertura']));
        $sheet->setCellValue('J' . $fila, $reg['forma_apertura'] ?? '');
        $sheet->setCellValue('K' . $fila, $reg['fecha_cancelacion'] ? date('d/m/Y', strtotime($reg['fecha_cancelacion'])) : 'N/A');
        $sheet->setCellValue('L' . $fila, floatval($reg['saldo_cancelacion']));
        $sheet->setCellValue('M' . $fila, $reg['forma_cancelacion'] ?? '');
        $sheet->setCellValue('N' . $fila, $reg['agencia'] ?? '');
        
        // Acumular totales
        $total_monto_apertura += floatval($reg['monto_apertura']);
        $total_saldo_cancel += floatval($reg['saldo_cancelacion']);
        
        // Contar por forma de cancelación
        if (strpos($reg['forma_cancelacion'] ?? '', 'Efectivo') !== false) {
            $count_efectivo++;
        } else {
            $count_otro++;
        }
        
        $fila++;
    }
    
    // ========== FILA DE TOTALES ==========
    $sheet->setCellValue('A' . $fila, 'TOTAL:');
    $sheet->mergeCells('A' . $fila . ':H' . $fila);
    $sheet->setCellValue('I' . $fila, $total_monto_apertura);
    $sheet->setCellValue('L' . $fila, $total_saldo_cancel);
    $sheet->getStyle('A' . $fila . ':N' . $fila)->getFont()->setBold(true);
    $sheet->getStyle('A' . $fila . ':N' . $fila)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('E7E6E6');
    
    // Formato de número para columnas monetarias
    $sheet->getStyle('I8:I' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('L8:L' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // ========== RESUMEN ==========
    $fila += 2;
    $sheet->setCellValue('A' . $fila, 'RESUMEN:');
    $sheet->getStyle('A' . $fila)->getFont()->setBold(true);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Total cuentas canceladas:');
    $sheet->setCellValue('B' . $fila, count($registro));
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Cancelación en Efectivo:');
    $sheet->setCellValue('B' . $fila, $count_efectivo);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Cancelación por Otro medio:');
    $sheet->setCellValue('B' . $fila, $count_otro);
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    $sheet->getColumnDimension('A')->setWidth(22);
    $sheet->getColumnDimension('B')->setWidth(10);
    $sheet->getColumnDimension('C')->setWidth(35);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(18);
    $sheet->getColumnDimension('G')->setWidth(18);
    $sheet->getColumnDimension('H')->setWidth(14);
    $sheet->getColumnDimension('I')->setWidth(15);
    $sheet->getColumnDimension('J')->setWidth(14);
    $sheet->getColumnDimension('K')->setWidth(14);
    $sheet->getColumnDimension('L')->setWidth(15);
    $sheet->getColumnDimension('M')->setWidth(14);
    $sheet->getColumnDimension('N')->setWidth(28);
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFilaDatos = 7 + count($registro);
    $sheet->getStyle('A7:N' . $ultimaFilaDatos)->applyFromArray([
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
        'namefile' => "Reporte_IVE_Cuentas_Canceladas" . str_replace(' ', '_', $titlereport),
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
    $rutalogoins = "../../.." . $info[0]["log_img"];
    
    $pdf = new FPDF('L', 'mm', 'Legal'); // Orientación horizontal, tamaño legal para más columnas
    $pdf->AddPage();
    $pdf->SetMargins(5, 10, 5);
    
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
    $pdf->Cell(0, 6, Utf8::decode('REPORTE IVE - CUENTAS DE CAPTACIÓN CANCELADAS'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, Utf8::decode('Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, Utf8::decode('Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetFillColor(192, 0, 0);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(28, 5, 'TIPO CUENTA', 1, 0, 'C', true);
    $pdf->Cell(12, 5, 'MONEDA', 1, 0, 'C', true);
    $pdf->Cell(45, 5, 'NOMBRE CLIENTE', 1, 0, 'C', true);
    $pdf->Cell(18, 5, 'TIPO PERS.', 1, 0, 'C', true);
    $pdf->Cell(18, 5, 'NACIONAL.', 1, 0, 'C', true);
    $pdf->Cell(25, 5, 'NO. CUENTA', 1, 0, 'C', true);
    $pdf->Cell(18, 5, 'F. APERT.', 1, 0, 'C', true);
    $pdf->Cell(18, 5, 'F. CANCEL.', 1, 0, 'C', true);
    $pdf->Cell(20, 5, 'MTO. APERT.', 1, 0, 'C', true);
    $pdf->Cell(20, 5, 'SALDO CAN.', 1, 0, 'C', true);
    $pdf->Cell(22, 5, 'FORMA CAN.', 1, 0, 'C', true);
    $pdf->Cell(38, 5, 'AGENCIA', 1, 1, 'C', true);
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $total_monto = 0;
    $total_saldo = 0;
    
    foreach ($registro as $reg) {
        // Verificar salto de página
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            // Reimprimir encabezados
            $pdf->SetFont('Arial', 'B', 6);
            $pdf->SetFillColor(192, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(28, 5, 'TIPO CUENTA', 1, 0, 'C', true);
            $pdf->Cell(12, 5, 'MONEDA', 1, 0, 'C', true);
            $pdf->Cell(45, 5, 'NOMBRE CLIENTE', 1, 0, 'C', true);
            $pdf->Cell(18, 5, 'TIPO PERS.', 1, 0, 'C', true);
            $pdf->Cell(18, 5, 'NACIONAL.', 1, 0, 'C', true);
            $pdf->Cell(25, 5, 'NO. CUENTA', 1, 0, 'C', true);
            $pdf->Cell(18, 5, 'F. APERT.', 1, 0, 'C', true);
            $pdf->Cell(18, 5, 'F. CANCEL.', 1, 0, 'C', true);
            $pdf->Cell(20, 5, 'MTO. APERT.', 1, 0, 'C', true);
            $pdf->Cell(20, 5, 'SALDO CAN.', 1, 0, 'C', true);
            $pdf->Cell(22, 5, 'FORMA CAN.', 1, 0, 'C', true);
            $pdf->Cell(38, 5, 'AGENCIA', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 5);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        }
        
        $monto = floatval($reg['monto_apertura']);
        $saldo = floatval($reg['saldo_cancelacion']);
        $total_monto += $monto;
        $total_saldo += $saldo;
        
        $pdf->Cell(28, 4, Utf8::decode(substr($reg['tipo_cuenta'], 0, 20)), 1, 0, 'L', $fill);
        $pdf->Cell(12, 4, $reg['moneda'], 1, 0, 'C', $fill);
        $pdf->Cell(45, 4, Utf8::decode(substr($reg['nombre_cliente'], 0, 35)), 1, 0, 'L', $fill);
        $pdf->Cell(18, 4, Utf8::decode(substr($reg['tipo_persona'], 0, 12)), 1, 0, 'C', $fill);
        $pdf->Cell(18, 4, Utf8::decode(substr($reg['nacionalidad'], 0, 12)), 1, 0, 'C', $fill);
        $pdf->Cell(25, 4, $reg['numero_cuenta'], 1, 0, 'L', $fill);
        $pdf->Cell(18, 4, date('d/m/Y', strtotime($reg['fecha_apertura'])), 1, 0, 'C', $fill);
        $pdf->Cell(18, 4, date('d/m/Y', strtotime($reg['fecha_cancelacion'])), 1, 0, 'C', $fill);
        $pdf->Cell(20, 4, number_format($monto, 2), 1, 0, 'R', $fill);
        $pdf->Cell(20, 4, number_format($saldo, 2), 1, 0, 'R', $fill);
        $pdf->Cell(22, 4, Utf8::decode(substr($reg['forma_cancelacion'], 0, 15)), 1, 0, 'C', $fill);
        $pdf->Cell(38, 4, Utf8::decode(substr($reg['agencia'], 0, 25)), 1, 1, 'L', $fill);
        
        $fill = !$fill;
    }
    
    // ========== TOTALES ==========
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(164, 5, 'TOTALES:', 1, 0, 'R', true);
    $pdf->Cell(20, 5, number_format($total_monto, 2), 1, 0, 'R', true);
    $pdf->Cell(20, 5, number_format($total_saldo, 2), 1, 0, 'R', true);
    $pdf->Cell(60, 5, '', 1, 1, 'L', true);
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total cuentas canceladas: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    ob_start();
    $pdf->Output('S');
    $pdfData = ob_get_contents();
    ob_end_clean();
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reporte_IVE_Cuentas_Canceladas",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
