<?php
/**
 * REPORTE IVE 09 - CRÉDITOS BACK TO BACK (GARANTÍA DEPOSITARIA)
 * 
 * Requisitos IVE:
 * - Número de crédito
 * - Nombre cliente
 * - Tipo persona (Individual/Jurídica)
 * - Tipo garantía depositaria (Cuenta Ahorro/Aportación)
 * - Número cuenta garantía
 * - Monto garantía
 * - Fecha concesión
 * - Monto crédito
 * - Fecha vencimiento
 * - Saldo crédito
 * - Agencia
 * 
 * Back to Back = Créditos con garantía tipo:
 * - idTipoDoc = 8 (Cuenta de Ahorro)
 * - idTipoDoc = 18 (Aportación)
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
// reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx/pdf', 'repo_ive_09', 1/0)
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
// CONSULTA SQL OPTIMIZADA PARA CRÉDITOS BACK TO BACK IVE
// ============================================================================
// Back to Back = Créditos con garantía depositaria (idTipoDoc IN (8, 18))
// 8 = Cuenta de Ahorro, 18 = Aportación
$strquery = "SELECT 
    cre.CCODCTA AS numero_credito,
    cli.compl_name AS nombre_cliente,
    CASE 
        WHEN cli.id_tipoCliente = 'Natural' THEN 'Individual'
        ELSE 'Juridica'
    END AS tipo_persona,
    CASE 
        WHEN clgar.idTipoDoc = 8 THEN 'Cuenta de Ahorro'
        WHEN clgar.idTipoDoc = 18 THEN 'Aportacion'
        ELSE 'Depositaria'
    END AS tipo_garantia_depositaria,
    clgar.descripcionGarantia AS numero_cuenta_garantia,
    COALESCE(clgar.montoGravamen, 0) AS monto_garantia,
    DATE_FORMAT(cre.DFecDsbls, '%d/%m/%Y') AS fecha_concesion,
    cre.NCapDes AS monto_credito,
    DATE_FORMAT(cre.DFecVen, '%d/%m/%Y') AS fecha_vencimiento,
    (cre.NCapDes - COALESCE(
        (SELECT SUM(kar.KP) 
         FROM CREDKAR kar 
         WHERE kar.CCODCTA = cre.CCODCTA 
           AND kar.CESTADO != 'X'
           AND kar.CTIPPAG = 'P'
        ), 0)
    ) AS saldo_credito,
    CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia,
    cre.DFecDsbls AS fecha_orden
FROM cremcre_meta cre
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cre.CodCli
INNER JOIN tb_agencia ag ON ag.cod_agenc = cre.CODAgencia
INNER JOIN tb_garantias_creditos garcre ON garcre.id_cremcre_meta = cre.CCODCTA
INNER JOIN cli_garantia clgar ON garcre.id_garantia = clgar.idGarantia
    AND clgar.estado = 1
    AND clgar.idTipoDoc IN (8, 18)
WHERE cre.DFecDsbls BETWEEN ? AND ?
  AND cre.Cestado NOT IN ('X', 'A')
  {$filagencia}
GROUP BY cre.CCODCTA, clgar.idGarantia
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
        throw new Exception("No se encontraron creditos Back to Back en el periodo seleccionado");
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
    $sheet->setTitle('Creditos Back to Back');
    
    // Configurar UTF-8
    $spreadsheet->getProperties()->setTitle('Creditos Back to Back');
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - CREDITOS BACK TO BACK (GARANTIA DEPOSITARIA)');
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
        'A' => 'NUMERO CREDITO',
        'B' => 'NOMBRE CLIENTE',
        'C' => 'TIPO PERSONA',
        'D' => 'TIPO GARANTIA',
        'E' => 'NO. CUENTA GARANTIA',
        'F' => 'MONTO GARANTIA',
        'G' => 'FECHA CONCESION',
        'H' => 'MONTO CREDITO',
        'I' => 'FECHA VENCIMIENTO',
        'J' => 'SALDO CREDITO',
        'K' => 'AGENCIA'
    ];
    
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . $fila, $header);
    }
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':K' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    $totalMontoGarantia = 0;
    $totalMontoCredito = 0;
    $totalSaldo = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['numero_credito']);
        $sheet->setCellValue('B' . $fila, $reg['nombre_cliente']);
        $sheet->setCellValue('C' . $fila, $reg['tipo_persona']);
        $sheet->setCellValue('D' . $fila, $reg['tipo_garantia_depositaria']);
        $sheet->setCellValue('E' . $fila, $reg['numero_cuenta_garantia']);
        $sheet->setCellValue('F' . $fila, floatval($reg['monto_garantia']));
        $sheet->setCellValue('G' . $fila, $reg['fecha_concesion']);
        $sheet->setCellValue('H' . $fila, floatval($reg['monto_credito']));
        $sheet->setCellValue('I' . $fila, $reg['fecha_vencimiento']);
        $sheet->setCellValue('J' . $fila, floatval($reg['saldo_credito']));
        $sheet->setCellValue('K' . $fila, $reg['agencia']);
        
        $totalMontoGarantia += floatval($reg['monto_garantia']);
        $totalMontoCredito += floatval($reg['monto_credito']);
        $totalSaldo += floatval($reg['saldo_credito']);
        
        $fila++;
    }
    
    // ========== TOTALES ==========
    $sheet->setCellValue('E' . $fila, 'TOTALES:');
    $sheet->setCellValue('F' . $fila, $totalMontoGarantia);
    $sheet->setCellValue('H' . $fila, $totalMontoCredito);
    $sheet->setCellValue('J' . $fila, $totalSaldo);
    $sheet->getStyle('E' . $fila . ':K' . $fila)->getFont()->setBold(true);
    
    // ========== FORMATO NUMÉRICO ==========
    $sheet->getStyle('F8:F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('H8:H' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('J8:J' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    // Formato para códigos y números largos (evitar notación científica)
    $sheet->getStyle('A8:A' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Número de crédito como texto
    $sheet->getStyle('E8:E' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Número cuenta garantía como texto
    $sheet->getStyle('G8:G' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Código cliente como texto
    
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
        'namefile' => "Reporte_IVE_CreditosBackToBack" . str_replace([' ', '/'], ['_', '-'], $titlereport),
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
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'REPORTE IVE - CREDITOS BACK TO BACK (GARANTIA DEPOSITARIA)'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(2);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 5);
    $pdf->SetFillColor(46, 125, 50); // Verde oscuro
    $pdf->SetTextColor(255, 255, 255);
    
    // Anchos de columna optimizados para landscape Letter (279mm - 10mm márgenes = 269mm)
    $w = [22, 48, 18, 25, 28, 22, 18, 22, 18, 22, 25]; // Total ~268mm
    
    $pdf->Cell($w[0], 5, 'No. Credito', 1, 0, 'C', true);
    $pdf->Cell($w[1], 5, 'Nombre Cliente', 1, 0, 'C', true);
    $pdf->Cell($w[2], 5, 'Tipo Pers.', 1, 0, 'C', true);
    $pdf->Cell($w[3], 5, 'Tipo Garantia', 1, 0, 'C', true);
    $pdf->Cell($w[4], 5, 'No. Cta. Garantia', 1, 0, 'C', true);
    $pdf->Cell($w[5], 5, 'Monto Garantia', 1, 0, 'C', true);
    $pdf->Cell($w[6], 5, 'Fec. Conces.', 1, 0, 'C', true);
    $pdf->Cell($w[7], 5, 'Monto Credito', 1, 0, 'C', true);
    $pdf->Cell($w[8], 5, 'Fec. Venc.', 1, 0, 'C', true);
    $pdf->Cell($w[9], 5, 'Saldo Credito', 1, 0, 'C', true);
    $pdf->Cell($w[10], 5, 'Agencia', 1, 1, 'C', true);
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 5);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $totalMontoGarantia = 0;
    $totalMontoCredito = 0;
    $totalSaldo = 0;
    
    foreach ($registro as $reg) {
        if ($pdf->GetY() > 190) {
            $pdf->AddPage();
            // Repetir encabezados
            $pdf->SetFont('Arial', 'B', 5);
            $pdf->SetFillColor(46, 125, 50);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell($w[0], 5, 'No. Credito', 1, 0, 'C', true);
            $pdf->Cell($w[1], 5, 'Nombre Cliente', 1, 0, 'C', true);
            $pdf->Cell($w[2], 5, 'Tipo Pers.', 1, 0, 'C', true);
            $pdf->Cell($w[3], 5, 'Tipo Garantia', 1, 0, 'C', true);
            $pdf->Cell($w[4], 5, 'No. Cta. Garantia', 1, 0, 'C', true);
            $pdf->Cell($w[5], 5, 'Monto Garantia', 1, 0, 'C', true);
            $pdf->Cell($w[6], 5, 'Fec. Conces.', 1, 0, 'C', true);
            $pdf->Cell($w[7], 5, 'Monto Credito', 1, 0, 'C', true);
            $pdf->Cell($w[8], 5, 'Fec. Venc.', 1, 0, 'C', true);
            $pdf->Cell($w[9], 5, 'Saldo Credito', 1, 0, 'C', true);
            $pdf->Cell($w[10], 5, 'Agencia', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 5);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        $pdf->Cell($w[0], 4, $reg['numero_credito'] ?? '', 1, 0, 'L', $fill);
        $pdf->Cell($w[1], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['nombre_cliente'] ?? '', 0, 38)), 1, 0, 'L', $fill);
        $pdf->Cell($w[2], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['tipo_persona'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell($w[3], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['tipo_garantia_depositaria'] ?? '', 0, 18)), 1, 0, 'C', $fill);
        $pdf->Cell($w[4], 4, $reg['numero_cuenta_garantia'] ?? '', 1, 0, 'L', $fill);
        $pdf->Cell($w[5], 4, number_format(floatval($reg['monto_garantia'] ?? 0), 2), 1, 0, 'R', $fill);
        $pdf->Cell($w[6], 4, $reg['fecha_concesion'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell($w[7], 4, number_format(floatval($reg['monto_credito'] ?? 0), 2), 1, 0, 'R', $fill);
        $pdf->Cell($w[8], 4, $reg['fecha_vencimiento'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell($w[9], 4, number_format(floatval($reg['saldo_credito'] ?? 0), 2), 1, 0, 'R', $fill);
        $pdf->Cell($w[10], 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['agencia'] ?? '', 0, 18)), 1, 1, 'L', $fill);
        
        $totalMontoGarantia += floatval($reg['monto_garantia'] ?? 0);
        $totalMontoCredito += floatval($reg['monto_credito'] ?? 0);
        $totalSaldo += floatval($reg['saldo_credito'] ?? 0);
        $fill = !$fill;
    }
    
    // ========== TOTALES ==========
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Cell($w[0]+$w[1]+$w[2]+$w[3]+$w[4], 5, 'TOTALES:', 1, 0, 'R', false);
    $pdf->Cell($w[5], 5, number_format($totalMontoGarantia, 2), 1, 0, 'R', false);
    $pdf->Cell($w[6], 5, '', 1, 0, 'C', false);
    $pdf->Cell($w[7], 5, number_format($totalMontoCredito, 2), 1, 0, 'R', false);
    $pdf->Cell($w[8], 5, '', 1, 0, 'C', false);
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
        'namefile' => "Reporte_IVE_CreditosBackToBack" . str_replace([' ', '/'], ['_', '-'], $titlereport),
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
