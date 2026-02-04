<?php
/**
 * REPORTE IVE 08 - CRÉDITOS CONCEDIDOS
 * 
 * Requisitos IVE:
 * - Tipo y número de crédito
 * - Nombre cliente
 * - Tipo persona (Individual/Jurídica)
 * - Fecha concesión
 * - Monto concedido
 * - Tipo garantía (concatenadas si hay varias)
 * - Forma desembolso
 * - Fecha vencimiento
 * - Saldo actual
 * - Agencia
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
// reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx/pdf', 'repo_ive_08', 1/0)
$datos = $_POST["datosval"];
$inputs = $datos[0];      // Array de fechas [finicio, ffin]
$selects = $datos[1];     // Array de selects [codofi]
$radios = $datos[2];      // Array de radios [ragencia]
$archivo = isset($datos[3]) && !empty($datos[3]) ? $datos[3] : ['usuario_sistema'];
$tipo = $_POST["tipo"];   // 'xlsx' o 'pdf'

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
// CONSULTA SQL OPTIMIZADA PARA CRÉDITOS CONCEDIDOS IVE
// ============================================================================
$strquery = "SELECT 
    tcre.abre AS tipo_credito,
    cre.CCODCTA AS numero_credito,
    cli.compl_name AS nombre_cliente,
    CASE 
        WHEN cli.id_tipoCliente = 'Natural' THEN 'Individual'
        ELSE 'Juridica'
    END AS tipo_persona,
    DATE_FORMAT(cre.DFecDsbls, '%d/%m/%Y') AS fecha_concesion,
    cre.NCapDes AS monto_concedido,
    COALESCE(
        (SELECT GROUP_CONCAT(DISTINCT tipgar.TiposGarantia SEPARATOR ', ')
         FROM tb_garantias_creditos garcre
         INNER JOIN cli_garantia clgar ON garcre.id_garantia = clgar.idGarantia AND clgar.estado = 1
         INNER JOIN {$db_name_general}.tb_tiposgarantia tipgar ON clgar.idTipoGa = tipgar.id_TiposGarantia
         WHERE garcre.id_cremcre_meta = cre.CCODCTA
        ), 'Sin garantia'
    ) AS tipo_garantia,
    CASE 
        WHEN desemb.FormPago = '1' THEN 'Efectivo'
        WHEN desemb.FormPago = '2' THEN 'Cheque'
        WHEN desemb.FormPago = '3' THEN 'Transferencia'
        WHEN desemb.FormPago = '4' THEN 'Cheque'
        ELSE 'Otro medio'
    END AS forma_desembolso,
    DATE_FORMAT(cre.DFecVen, '%d/%m/%Y') AS fecha_vencimiento,
    (cre.NCapDes - COALESCE(
        (SELECT SUM(kar.KP) 
         FROM CREDKAR kar 
         WHERE kar.CCODCTA = cre.CCODCTA 
           AND kar.CESTADO != 'X'
           AND kar.CTIPPAG = 'P'
        ), 0)
    ) AS saldo_actual,
    CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia,
    cre.DFecDsbls AS fecha_orden
FROM cremcre_meta cre
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cre.CodCli
INNER JOIN tb_agencia ag ON ag.cod_agenc = cre.CODAgencia
LEFT JOIN {$db_name_general}.tb_credito tcre ON tcre.abre = cre.CtipCre
LEFT JOIN (
    SELECT ccodcta, FormPago
    FROM CREDKAR
    WHERE CTIPPAG = 'D' AND CESTADO != 'X'
    GROUP BY ccodcta
) desemb ON desemb.ccodcta = cre.CCODCTA
WHERE cre.DFecDsbls BETWEEN ? AND ?
  AND cre.Cestado NOT IN ('X', 'A')
  {$filagencia}
ORDER BY ag.nom_agencia, cre.DFecDsbls";

// ============================================================================
// EJECUCIÓN DE CONSULTA
// ============================================================================
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, $params);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron creditos concedidos en el periodo seleccionado");
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
    $sheet->setTitle('Creditos Concedidos');
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - CREDITOS CONCEDIDOS');
    $sheet->mergeCells('A2:K2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Periodo: ' . $titlereport);
    $sheet->mergeCells('A3:K3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Agencia: ' . $oficina);
    $sheet->mergeCells('A4:K4');
    
    $sheet->setCellValue('A5', 'Fecha de generacion: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A5:K5');
    
    // ========== ENCABEZADOS DE COLUMNAS (FILA 7) ==========
    $fila = 7;
    $headers = [
        'A' => 'TIPO CREDITO',
        'B' => 'NUMERO CREDITO',
        'C' => 'NOMBRE CLIENTE',
        'D' => 'TIPO PERSONA',
        'E' => 'FECHA CONCESION',
        'F' => 'MONTO CONCEDIDO',
        'G' => 'TIPO GARANTIA',
        'H' => 'FORMA DESEMBOLSO',
        'I' => 'FECHA VENCIMIENTO',
        'J' => 'SALDO ACTUAL',
        'K' => 'AGENCIA'
    ];
    
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . $fila, $header);
    }
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':K' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    $totalMontoConcedido = 0;
    $totalSaldo = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['tipo_credito']);
        $sheet->setCellValue('B' . $fila, $reg['numero_credito']);
        $sheet->setCellValue('C' . $fila, $reg['nombre_cliente']);
        $sheet->setCellValue('D' . $fila, $reg['tipo_persona']);
        $sheet->setCellValue('E' . $fila, $reg['fecha_concesion']);
        $sheet->setCellValue('F' . $fila, floatval($reg['monto_concedido']));
        $sheet->setCellValue('G' . $fila, $reg['tipo_garantia']);
        $sheet->setCellValue('H' . $fila, $reg['forma_desembolso']);
        $sheet->setCellValue('I' . $fila, $reg['fecha_vencimiento']);
        $sheet->setCellValue('J' . $fila, floatval($reg['saldo_actual']));
        $sheet->setCellValue('K' . $fila, $reg['agencia']);
        
        $totalMontoConcedido += floatval($reg['monto_concedido']);
        $totalSaldo += floatval($reg['saldo_actual']);
        
        $fila++;
    }
    
    // ========== TOTALES ==========
    $sheet->setCellValue('E' . $fila, 'TOTALES:');
    $sheet->setCellValue('F' . $fila, $totalMontoConcedido);
    $sheet->setCellValue('J' . $fila, $totalSaldo);
    $sheet->getStyle('E' . $fila . ':K' . $fila)->getFont()->setBold(true);
    
    // ========== FORMATO NUMÉRICO ==========
    $sheet->getStyle('I8:I' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    // Formato para códigos y números largos (evitar notación científica)
    $sheet->getStyle('A8:A' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Número de crédito como texto
    $sheet->getStyle('E8:E' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Número garantía como texto
    $sheet->getStyle('G8:G' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Código cliente como texto
    $sheet->getStyle('J8:J' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFila = $fila;
    $sheet->getStyle('A7:K' . $ultimaFila)->applyFromArray([
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
        'namefile' => "Reporte_IVE_CreditosConcedidos" . str_replace([' ', '/'], ['_', '-'], $titlereport),
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
    $telefonosins = ($info[0]["tel_1"] ?? '') . '   ' . ($info[0]["tel_2"] ?? '');
    $nitins = $info[0]["nit"] ?? '';
    $rutalogoins = "../../../.." . ($info[0]["log_img"] ?? '');
    
    $pdf = new FPDF('L', 'mm', 'Letter'); // Orientación horizontal
    $pdf->AddPage();
    $pdf->SetMargins(5, 10, 5);
    
    // ========== LOGO Y ENCABEZADO ==========
    if (file_exists($rutalogoins)) {
        $pdf->Image($rutalogoins, 10, 8, 20);
    }
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $institucion), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $direccionins), 0, 1, 'C');
    $pdf->Cell(0, 4, 'NIT: ' . $nitins . ' | Tel: ' . $telefonosins, 0, 1, 'C');
    $pdf->Ln(2);
    
    // ========== TÍTULO DEL REPORTE ==========
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'REPORTE IVE - CREDITOS CONCEDIDOS'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(2);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 5);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    // Anchos de columna optimizados para landscape Letter (279mm - 10mm márgenes = 269mm)
    $w = [18, 22, 45, 18, 18, 22, 35, 22, 18, 22, 28]; // Total ~268mm
    
    $pdf->Cell($w[0], 5, 'Tipo Cred.', 1, 0, 'C', true);
    $pdf->Cell($w[1], 5, 'No. Credito', 1, 0, 'C', true);
    $pdf->Cell($w[2], 5, 'Nombre Cliente', 1, 0, 'C', true);
    $pdf->Cell($w[3], 5, 'Tipo Pers.', 1, 0, 'C', true);
    $pdf->Cell($w[4], 5, 'Fec. Conces.', 1, 0, 'C', true);
    $pdf->Cell($w[5], 5, 'Monto Conced.', 1, 0, 'C', true);
    $pdf->Cell($w[6], 5, 'Tipo Garantia', 1, 0, 'C', true);
    $pdf->Cell($w[7], 5, 'Forma Desemb.', 1, 0, 'C', true);
    $pdf->Cell($w[8], 5, 'Fec. Venc.', 1, 0, 'C', true);
    $pdf->Cell($w[9], 5, 'Saldo Actual', 1, 0, 'C', true);
    $pdf->Cell($w[10], 5, 'Agencia', 1, 1, 'C', true);
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $totalMonto = 0;
    $totalSaldo = 0;
    
    foreach ($registro as $reg) {
        if ($pdf->GetY() > 190) {
            $pdf->AddPage();
            // Repetir encabezados
            $pdf->SetFont('Arial', 'B', 5);
            $pdf->SetFillColor(68, 114, 196);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($w[0], 5, 'Tipo Cred.', 1, 0, 'C', true);
            $pdf->Cell($w[1], 5, 'No. Credito', 1, 0, 'C', true);
            $pdf->Cell($w[2], 5, 'Nombre Cliente', 1, 0, 'C', true);
            $pdf->Cell($w[3], 5, 'Tipo Pers.', 1, 0, 'C', true);
            $pdf->Cell($w[4], 5, 'Fec. Conces.', 1, 0, 'C', true);
            $pdf->Cell($w[5], 5, 'Monto Conced.', 1, 0, 'C', true);
            $pdf->Cell($w[6], 5, 'Tipo Garantia', 1, 0, 'C', true);
            $pdf->Cell($w[7], 5, 'Forma Desemb.', 1, 0, 'C', true);
            $pdf->Cell($w[8], 5, 'Fec. Venc.', 1, 0, 'C', true);
            $pdf->Cell($w[9], 5, 'Saldo Actual', 1, 0, 'C', true);
            $pdf->Cell($w[10], 5, 'Agencia', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 5);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        $pdf->Cell($w[0], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['tipo_credito'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell($w[1], 4, $reg['numero_credito'] ?? '', 1, 0, 'L', $fill);
        $pdf->Cell($w[2], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['nombre_cliente'] ?? '', 0, 35)), 1, 0, 'L', $fill);
        $pdf->Cell($w[3], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['tipo_persona'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell($w[4], 4, $reg['fecha_concesion'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell($w[5], 4, number_format(floatval($reg['monto_concedido'] ?? 0), 2), 1, 0, 'R', $fill);
        $pdf->Cell($w[6], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['tipo_garantia'] ?? '', 0, 28)), 1, 0, 'L', $fill);
        $pdf->Cell($w[7], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['forma_desembolso'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell($w[8], 4, $reg['fecha_vencimiento'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell($w[9], 4, number_format(floatval($reg['saldo_actual'] ?? 0), 2), 1, 0, 'R', $fill);
        $pdf->Cell($w[10], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['agencia'] ?? '', 0, 22)), 1, 1, 'L', $fill);
        
        $totalMonto += floatval($reg['monto_concedido'] ?? 0);
        $totalSaldo += floatval($reg['saldo_actual'] ?? 0);
        $fill = !$fill;
    }
    
    // ========== TOTALES ==========
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Cell($w[0]+$w[1]+$w[2]+$w[3]+$w[4], 5, 'TOTALES:', 1, 0, 'R', false);
    $pdf->Cell($w[5], 5, number_format($totalMonto, 2), 1, 0, 'R', false);
    $pdf->Cell($w[6]+$w[7]+$w[8], 5, '', 1, 0, 'C', false);
    $pdf->Cell($w[9], 5, number_format($totalSaldo, 2), 1, 0, 'R', false);
    $pdf->Cell($w[10], 5, '', 1, 1, 'C', false);
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->Cell(0, 4, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    $pdfData = $pdf->Output('S');
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reporte_IVE_CreditosConcedidos" . str_replace([' ', '/'], ['_', '-'], $titlereport),
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
