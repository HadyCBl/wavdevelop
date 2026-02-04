<?php
/**
 * REPORTE IVE 10 - CRÉDITOS CANCELADOS ANTICIPADAMENTE (>=75%)
 * 
 * Requisitos IVE:
 * - Número de crédito
 * - Tipo de crédito
 * - Nombre cliente
 * - Tipo persona (Individual/Jurídica)
 * - Fecha concesión
 * - Fecha vencimiento
 * - Fecha cancelación (último pago)
 * - Monto concedido
 * - Monto cancelado (suma de pagos de capital)
 * - % Cancelado
 * - Forma pago del último pago
 * - Agencia
 * 
 * Criterios:
 * - Crédito cancelado (Cestado IN ('C','X')) O saldo = 0
 * - Fecha de cancelación < Fecha de vencimiento (anticipado)
 * - Porcentaje cancelado >= 75%
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
// Estructura esperada desde view003.php:
// reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx/pdf/preview', 'repo_ive_10', 1/0)
$datos = $_POST["datosval"];
$inputs = $datos[0];      // Array de fechas [finicio, ffin]
$selects = $datos[1];     // Array de selects [codofi]
$radios = $datos[2];      // Array de radios [ragencia]
$archivo = isset($datos[3]) && !empty($datos[3]) ? $datos[3] : ['usuario_sistema'];
$tipo = $_POST["tipo"];   // 'preview', 'xlsx' o 'pdf'

// ============================================================================
// VALIDACIÓN DE PARÁMETROS
// ============================================================================
if (count($inputs) < 2) {
    echo json_encode(['mensaje' => 'Debe proporcionar rango de fechas', 'status' => 0]);
    return;
}

$finicio = $inputs[0];
$ffin = $inputs[1];

// Validar fechas
if (!validateDate($finicio, 'Y-m-d') || !validateDate($ffin, 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Rango de fechas invalido', 'status' => 0]);
    return;
}

// Validar que finicio <= ffin
if (strtotime($finicio) > strtotime($ffin)) {
    echo json_encode(['mensaje' => 'La fecha inicial no puede ser mayor a la fecha final', 'status' => 0]);
    return;
}

$titlereport = " DEL " . date("d-m-Y", strtotime($finicio)) . " AL " . date("d-m-Y", strtotime($ffin));

// Validar agencia si se seleccionó "Por Agencia"
$modoAgencia = isset($radios[0]) ? $radios[0] : 'anyofi';
if ($modoAgencia == "anyofi" && (empty($selects[0]) || $selects[0] == 0)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una agencia']);
    return;
}

// ============================================================================
// CONSTRUCCIÓN DE FILTROS SQL
// ============================================================================
$filagencia = "";
$params = [$finicio, $ffin];

if ($modoAgencia == "anyofi" && !empty($selects[0])) {
    $filagencia = " AND ag.id_agencia = ?";
    $params[] = $selects[0];
}

// ============================================================================
// CONSULTA SQL OPTIMIZADA PARA CRÉDITOS CANCELADOS ANTICIPADAMENTE IVE
// ============================================================================
// Lógica:
// - Créditos con saldo 0 o estado cancelado
// - Fecha de último pago ANTES de fecha vencimiento (anticipado)
// - Total pagado >= 75% del capital desembolsado
// - Filtro por fecha de cancelación (último pago) dentro del rango
$strquery = "SELECT 
    cre.CCODCTA AS numero_credito,
    COALESCE(tcre.abre, cre.CtipCre) AS tipo_credito,
    cli.compl_name AS nombre_cliente,
    CASE 
        WHEN cli.id_tipoCliente = 'Natural' THEN 'Individual'
        ELSE 'Juridica'
    END AS tipo_persona,
    DATE_FORMAT(cre.DFecDsbls, '%d/%m/%Y') AS fecha_concesion,
    DATE_FORMAT(cre.DFecVen, '%d/%m/%Y') AS fecha_vencimiento,
    DATE_FORMAT(canc.fecha_cancelacion, '%d/%m/%Y') AS fecha_cancelacion,
    cre.NCapDes AS monto_concedido,
    canc.monto_cancelado AS monto_cancelado,
    ROUND((canc.monto_cancelado / cre.NCapDes) * 100, 2) AS porcentaje_cancelado,
    CASE 
        WHEN canc.forma_pago_ult = '1' THEN 'Efectivo'
        WHEN canc.forma_pago_ult = '2' THEN 'Cheque'
        WHEN canc.forma_pago_ult = '3' THEN 'Transferencia'
        WHEN canc.forma_pago_ult = '4' THEN 'Cheque'
        ELSE 'Otro medio'
    END AS forma_pago,
    CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia,
    canc.fecha_cancelacion AS fecha_orden
FROM cremcre_meta cre
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cre.CodCli
INNER JOIN tb_agencia ag ON ag.cod_agenc = cre.CODAgencia
LEFT JOIN {$db_name_general}.tb_credito tcre ON tcre.abre = cre.CtipCre
-- Subconsulta para obtener datos de cancelación
INNER JOIN (
    SELECT 
        kar.CCODCTA,
        MAX(kar.dfecpro) AS fecha_cancelacion,
        SUM(CASE WHEN kar.CTIPPAG = 'P' THEN kar.KP ELSE 0 END) AS monto_cancelado,
        -- Forma de pago del último movimiento
        (SELECT k2.FormPago 
         FROM CREDKAR k2 
         WHERE k2.CCODCTA = kar.CCODCTA 
           AND k2.CESTADO != 'X' 
           AND k2.CTIPPAG = 'P'
         ORDER BY k2.DFECPRO DESC, k2.CODKAR DESC
         LIMIT 1
        ) AS forma_pago_ult
    FROM CREDKAR kar
    WHERE kar.CESTADO != 'X'
      AND kar.CTIPPAG = 'P'
    GROUP BY kar.CCODCTA
) canc ON canc.CCODCTA = cre.CCODCTA
WHERE 
    -- El crédito debe tener fecha de desembolso
    cre.DFecDsbls IS NOT NULL
    -- Filtrar por fecha de cancelación dentro del rango
    AND canc.fecha_cancelacion BETWEEN ? AND ?
    -- Cancelación anticipada: fecha de cancelación < fecha vencimiento
    AND canc.fecha_cancelacion < cre.DFecVen
    -- Porcentaje cancelado >= 75%
    AND (canc.monto_cancelado / cre.NCapDes) >= 0.75
    -- Crédito efectivamente cancelado (saldo 0) o estado cancelado
    AND (
        cre.Cestado IN ('C', 'X')
        OR (cre.NCapDes - canc.monto_cancelado) <= 0.01
    )
    {$filagencia}
ORDER BY ag.nom_agencia, canc.fecha_cancelacion, cre.CCODCTA";

// ============================================================================
// EJECUCIÓN DE CONSULTA
// ============================================================================
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, $params);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron creditos cancelados anticipadamente en el periodo seleccionado");
    }

    // Obtener información de la institución/agencia
    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
                                WHERE ag.id_agencia=?", [$idagencia]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
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
        // Retornar datos para DataTable
        echo json_encode([
            'status' => 1,
            'mensaje' => 'Datos cargados correctamente',
            'data' => $result,
            'total' => count($result)
        ]);
        break;
    case 'xlsx':
        printxls($result, $titlereport, $archivo[0] ?? 'usuario', $info);
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
    $sheet->setTitle('Creditos Canc. Anticip.');
    
    // Configurar UTF-8
    $spreadsheet->getProperties()->setTitle('Creditos Cancelados Anticipadamente');
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:L1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - CREDITOS CANCELADOS ANTICIPADAMENTE (>=75%)');
    $sheet->mergeCells('A2:L2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Periodo: ' . $titlereport);
    $sheet->mergeCells('A3:L3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Agencia: ' . $oficina);
    $sheet->mergeCells('A4:L4');
    
    $sheet->setCellValue('A5', 'Fecha de generacion: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A5:L5');
    
    // ========== ENCABEZADOS DE COLUMNAS (FILA 7) ==========
    $fila = 7;
    $headers = [
        'A' => 'NUMERO CREDITO',
        'B' => 'TIPO CREDITO',
        'C' => 'NOMBRE CLIENTE',
        'D' => 'TIPO PERSONA',
        'E' => 'FECHA CONCESION',
        'F' => 'FECHA VENCIMIENTO',
        'G' => 'FECHA CANCELACION',
        'H' => 'MONTO CONCEDIDO',
        'I' => 'MONTO CANCELADO',
        'J' => '% CANCELADO',
        'K' => 'FORMA PAGO',
        'L' => 'AGENCIA'
    ];
    
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . $fila, $header);
    }
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':L' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    $totalMontoConcedido = 0;
    $totalMontoCancelado = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['numero_credito']);
        $sheet->setCellValue('B' . $fila, $reg['tipo_credito']);
        $sheet->setCellValue('C' . $fila, $reg['nombre_cliente']);
        $sheet->setCellValue('D' . $fila, $reg['tipo_persona']);
        $sheet->setCellValue('E' . $fila, $reg['fecha_concesion']);
        $sheet->setCellValue('F' . $fila, $reg['fecha_vencimiento']);
        $sheet->setCellValue('G' . $fila, $reg['fecha_cancelacion']);
        $sheet->setCellValue('H' . $fila, floatval($reg['monto_concedido']));
        $sheet->setCellValue('I' . $fila, floatval($reg['monto_cancelado']));
        $sheet->setCellValue('J' . $fila, $reg['porcentaje_cancelado'] . '%');
        $sheet->setCellValue('K' . $fila, $reg['forma_pago']);
        $sheet->setCellValue('L' . $fila, $reg['agencia']);
        
        $totalMontoConcedido += floatval($reg['monto_concedido']);
        $totalMontoCancelado += floatval($reg['monto_cancelado']);
        
        $fila++;
    }
    
    // ========== FILA DE TOTALES ==========
    $sheet->setCellValue('G' . $fila, 'TOTALES:');
    $sheet->setCellValue('H' . $fila, $totalMontoConcedido);
    $sheet->setCellValue('I' . $fila, $totalMontoCancelado);
    $sheet->getStyle('G' . $fila . ':I' . $fila)->getFont()->setBold(true);
    
    // Formato numérico para montos
    $sheet->getStyle('H8:I' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    // Formato para códigos y números largos (evitar notación científica)
    $sheet->getStyle('A8:A' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Número de crédito como texto
    $sheet->getStyle('J8:J' . ($fila-1))->getNumberFormat()->setFormatCode('0.00'); // Porcentaje con decimales
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    foreach (range('A', 'L') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFila = $fila - 1;
    $sheet->getStyle('A7:L' . $ultimaFila)->applyFromArray([
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
        'namefile' => "IVE10_Creditos_Cancelados_Anticipadamente" . str_replace(' ', '_', $titlereport),
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
    $oficina = $info[0]["nom_agencia"] ?? '';
    $institucion = $info[0]["nomb_comple"] ?? '';
    $direccionins = $info[0]["muni_lug"] ?? '';
    $emailins = $info[0]["emai"] ?? '';
    $telefonosins = ($info[0]["tel_1"] ?? '') . '   ' . ($info[0]["tel_2"] ?? '');
    $nitins = $info[0]["nit"] ?? '';
    $rutalogoins = __DIR__ . "/../../../.." . ($info[0]["log_img"] ?? '');
    
    $pdf = new FPDF('L', 'mm', 'Letter'); // Orientación horizontal para más columnas
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
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'REPORTE IVE - CREDITOS CANCELADOS ANTICIPADAMENTE (>=75%)'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    // Anchos de columna para 12 columnas en horizontal (total ~277mm para Letter)
    $widths = [22, 14, 48, 17, 22, 22, 22, 22, 22, 13, 20, 26];
    $headers = ['No. Credito', 'Tipo', 'Nombre Cliente', 'Tipo Pers.', 'F. Concesion', 'F. Vencim.', 'F. Cancelac.', 'Mto Conced.', 'Mto Cancel.', '% Canc.', 'Forma Pago', 'Agencia'];
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $headers[$i]), 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $totalConcedido = 0;
    $totalCancelado = 0;
    
    foreach ($registro as $reg) {
        // Control de salto de página
        if ($pdf->GetY() > 185) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(68, 114, 196);
            $pdf->SetTextColor(255, 255, 255);
            for ($i = 0; $i < count($headers); $i++) {
                $pdf->Cell($widths[$i], 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $headers[$i]), 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 6);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        if ($fill) {
            $pdf->SetFillColor(240, 240, 240);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        $pdf->Cell($widths[0], 5, $reg['numero_credito'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], 5, $reg['tipo_credito'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[2], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['nombre_cliente'], 0, 32)), 1, 0, 'L', $fill);
        $pdf->Cell($widths[3], 5, $reg['tipo_persona'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[4], 5, $reg['fecha_concesion'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[5], 5, $reg['fecha_vencimiento'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[6], 5, $reg['fecha_cancelacion'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[7], 5, number_format($reg['monto_concedido'], 2), 1, 0, 'R', $fill);
        $pdf->Cell($widths[8], 5, number_format($reg['monto_cancelado'], 2), 1, 0, 'R', $fill);
        $pdf->Cell($widths[9], 5, $reg['porcentaje_cancelado'] . '%', 1, 0, 'C', $fill);
        $pdf->Cell($widths[10], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['forma_pago']), 1, 0, 'C', $fill);
        $pdf->Cell($widths[11], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['agencia'], 0, 18)), 1, 1, 'L', $fill);
        
        $totalConcedido += floatval($reg['monto_concedido']);
        $totalCancelado += floatval($reg['monto_cancelado']);
        $fill = !$fill;
    }
    
    // ========== FILA DE TOTALES ==========
    $pdf->SetFont('Arial', 'B', 7);
    $sumaAnchos = $widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4] + $widths[5] + $widths[6];
    $pdf->Cell($sumaAnchos, 6, 'TOTALES:', 1, 0, 'R');
    $pdf->Cell($widths[7], 6, number_format($totalConcedido, 2), 1, 0, 'R');
    $pdf->Cell($widths[8], 6, number_format($totalCancelado, 2), 1, 0, 'R');
    $pdf->Cell($widths[9] + $widths[10] + $widths[11], 6, '', 1, 1, 'C');
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    $pdfData = $pdf->Output('S');
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "IVE10_Creditos_Cancelados_Anticipadamente",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
