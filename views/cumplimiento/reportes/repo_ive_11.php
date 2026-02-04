<?php
/**
 * REPORTE IVE 11 - DEPÓSITOS PROVENIENTES DE CUENTAS BANCARIAS
 * 
 * Requisitos IVE:
 * - Fecha transacción
 * - Nombre del banco origen
 * - Número de cuenta banco origen
 * - Número boleta/referencia
 * - Número cuenta cliente (ahorro)
 * - Tipo de cuenta cliente
 * - Código cliente
 * - Nombre cliente
 * - Monto del depósito
 * - Nivel de riesgo
 * - Agencia
 * 
 * Criterios:
 * - Movimientos de tipo Depósito (ctipope = 'D')
 * - Origen: transferencia bancaria (cbanco IS NOT NULL)
 * - Estado activo (cestado != 2)
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
// reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx/pdf/preview', 'repo_ive_11', 1/0)
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
// CONSULTA SQL PARA DEPÓSITOS BANCARIOS IVE
// ============================================================================
// Depósitos que provienen de cuentas bancarias (transferencias)
// Campos: cbanco (nombre banco), ccodbanco (número cuenta banco), cnumdoc (boleta/referencia)
$strquery = "SELECT 
    DATE_FORMAT(mov.dfecope, '%d/%m/%Y') AS fecha_transaccion,
    CASE 
        WHEN mov.idCuentaBanco IS NOT NULL AND mov.idCuentaBanco > 0 THEN
            CONCAT(COALESCE(tb.nombre, 'Banco'), ' - ', COALESCE(cb.numcuenta, 'Sin cuenta'))
        ELSE 'Transferencia bancaria'
    END AS nombre_banco,
    COALESCE(cb.numcuenta, 'N/A') AS numero_cuenta_banco,
    COALESCE(mov.cnumdoc, 'N/A') AS boleta_referencia,
    aho.ccodaho AS cuenta_cliente,
    COALESCE(aht.nombre, 'Ahorro') AS tipo_cuenta,
    cli.idcod_cliente AS codigo_cliente,
    cli.compl_name AS nombre_cliente,
    mov.monto AS monto_deposito,
    CASE
        WHEN cli.id_tipoCliente = 'Natural' THEN 'Bajo'
        ELSE 'Medio'
    END AS nivel_riesgo,
    CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia,
    mov.dfecope AS fecha_orden
FROM ahommov mov
INNER JOIN ahomcta aho ON mov.ccodaho = aho.ccodaho
INNER JOIN tb_cliente cli ON aho.ccodcli = cli.idcod_cliente
INNER JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN ahomtip aht ON SUBSTR(aho.ccodaho, 7, 2) = aht.ccodtip
LEFT JOIN ctb_bancos cb ON mov.idCuentaBanco = cb.id
LEFT JOIN tb_bancos tb ON cb.id_banco = tb.id
WHERE mov.dfecope BETWEEN ? AND ?
  AND mov.ctipope = 'D'           -- Solo Depósitos
  AND mov.cestado != 2            -- No eliminados
  AND cli.estado = 1              -- Cliente activo
  -- Depósitos provenientes de bancos (transferencias bancarias)
  AND (
      mov.idCuentaBanco IS NOT NULL AND mov.idCuentaBanco > 0 -- Transferencias con cuenta bancaria
      OR mov.ctipdoc IN ('T', 'D', '11', '14')  -- Tipos de documento bancarios
  )
  {$filagencia}
ORDER BY ag.nom_agencia, mov.dfecope, mov.ccodaho";

// ============================================================================
// EJECUCIÓN DE CONSULTA
// ============================================================================
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, $params);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron depositos bancarios en el periodo seleccionado");
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
    $sheet->setTitle('Depositos Bancarios');
    
    // Configurar UTF-8
    $spreadsheet->getProperties()->setTitle('Depositos Provenientes de Cuentas Bancarias');
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - DEPOSITOS PROVENIENTES DE CUENTAS BANCARIAS');
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
        'A' => 'FECHA TRANSACCION',
        'B' => 'BANCO ORIGEN',
        'C' => 'CTA. BANCO ORIGEN',
        'D' => 'BOLETA/REF.',
        'E' => 'CUENTA CLIENTE',
        'F' => 'TIPO CUENTA',
        'G' => 'COD. CLIENTE',
        'H' => 'NOMBRE CLIENTE',
        'I' => 'MONTO',
        'J' => 'NIVEL RIESGO',
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
    $totalMonto = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['fecha_transaccion']);
        $sheet->setCellValue('B' . $fila, $reg['nombre_banco']);
        $sheet->setCellValue('C' . $fila, $reg['numero_cuenta_banco']);
        $sheet->setCellValue('D' . $fila, $reg['boleta_referencia']);
        $sheet->setCellValue('E' . $fila, $reg['cuenta_cliente']);
        $sheet->setCellValue('F' . $fila, $reg['tipo_cuenta']);
        $sheet->setCellValue('G' . $fila, $reg['codigo_cliente']);
        $sheet->setCellValue('H' . $fila, $reg['nombre_cliente']);
        $sheet->setCellValue('I' . $fila, floatval($reg['monto_deposito']));
        $sheet->setCellValue('J' . $fila, $reg['nivel_riesgo']);
        $sheet->setCellValue('K' . $fila, $reg['agencia']);
        
        $totalMonto += floatval($reg['monto_deposito']);
        
        $fila++;
    }
    
    // ========== FILA DE TOTALES ==========
    $sheet->setCellValue('H' . $fila, 'TOTAL:');
    $sheet->setCellValue('I' . $fila, $totalMonto);
    $sheet->getStyle('H' . $fila . ':I' . $fila)->getFont()->setBold(true);
    
    // Formato numérico para montos
    $sheet->getStyle('I8:I' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    // Formato para códigos y números largos (evitar notación científica)
    $sheet->getStyle('C8:C' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Número cuenta banco como texto
    $sheet->getStyle('D8:D' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Boleta/referencia como texto
    $sheet->getStyle('E8:E' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Cuenta cliente como texto
    $sheet->getStyle('G8:G' . ($fila-1))->getNumberFormat()->setFormatCode('@'); // Código cliente como texto
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFila = $fila - 1;
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
        'namefile' => "IVE11_Depositos_Bancarios" . str_replace(' ', '_', $titlereport),
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
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'REPORTE IVE - DEPOSITOS PROVENIENTES DE CUENTAS BANCARIAS'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    // Anchos de columna para 11 columnas en horizontal (total ~277mm para Letter)
    $widths = [20, 28, 25, 20, 22, 18, 18, 45, 22, 18, 30];
    $headers = ['Fecha', 'Banco Origen', 'Cta. Banco', 'Boleta/Ref', 'Cta. Cliente', 'Tipo Cta.', 'Cod. Cli.', 'Nombre Cliente', 'Monto', 'Nivel Riesgo', 'Agencia'];
    
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $headers[$i]), 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $totalMonto = 0;
    
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
        
        $pdf->Cell($widths[0], 5, $reg['fecha_transaccion'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['nombre_banco'], 0, 18)), 1, 0, 'L', $fill);
        $pdf->Cell($widths[2], 5, substr($reg['numero_cuenta_banco'], 0, 16), 1, 0, 'C', $fill);
        $pdf->Cell($widths[3], 5, substr($reg['boleta_referencia'], 0, 12), 1, 0, 'C', $fill);
        $pdf->Cell($widths[4], 5, $reg['cuenta_cliente'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[5], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['tipo_cuenta'], 0, 12)), 1, 0, 'C', $fill);
        $pdf->Cell($widths[6], 5, $reg['codigo_cliente'], 1, 0, 'C', $fill);
        $pdf->Cell($widths[7], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['nombre_cliente'], 0, 30)), 1, 0, 'L', $fill);
        $pdf->Cell($widths[8], 5, number_format($reg['monto_deposito'], 2), 1, 0, 'R', $fill);
        $pdf->Cell($widths[9], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $reg['nivel_riesgo']), 1, 0, 'C', $fill);
        $pdf->Cell($widths[10], 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', substr($reg['agencia'], 0, 20)), 1, 1, 'L', $fill);
        
        $totalMonto += floatval($reg['monto_deposito']);
        $fill = !$fill;
    }
    
    // ========== FILA DE TOTALES ==========
    $pdf->SetFont('Arial', 'B', 7);
    $sumaAnchos = $widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4] + $widths[5] + $widths[6] + $widths[7];
    $pdf->Cell($sumaAnchos, 6, 'TOTAL:', 1, 0, 'R');
    $pdf->Cell($widths[8], 6, number_format($totalMonto, 2), 1, 0, 'R');
    $pdf->Cell($widths[9] + $widths[10], 6, '', 1, 1, 'C');
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    $pdfData = $pdf->Output('S');
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "IVE11_Depositos_Bancarios",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
