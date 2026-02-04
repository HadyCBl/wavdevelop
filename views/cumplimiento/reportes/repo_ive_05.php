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
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

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
// CONSTRUCCIÓN DE CONSULTA SQL - PERSONAS EXPUESTAS POLÍTICAMENTE (PEP)
// ============================================================================
$filtro_agencia = ($radios[0] == "anyofi") ? " AND cli.agencia = " . intval($selects[0]) : "";
$filtro_pep = " AND cli.PEP = 'Si'";

if ($tipofecha === 'corte') {
    $strquery = "
    -- PEP CON PRODUCTOS DE AHORRO
    SELECT 
        cli.compl_name AS nombre,
        cli.no_identifica AS dpi,
        'Ahorro' AS tipo_producto,
        aho.ccodaho AS numero_producto,
        IFNULL(tip.nombre, 'Cuenta Ahorro') AS nombre_tipo_cuenta,
        aho.fecha_apertura AS fecha_apertura,
        0 AS monto_apertura,
        calcular_saldo_aho_tipcuenta(aho.ccodaho, ?) AS saldo,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM ahomcta aho
    INNER JOIN tb_cliente cli ON aho.ccodcli = cli.idcod_cliente
    INNER JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
    LEFT JOIN ahomtip tip ON SUBSTR(aho.ccodaho,7,2) = tip.ccodtip
    WHERE cli.estado = 1
        AND aho.estado IN ('A', 'B', '1')
        AND aho.fecha_apertura <= ?
        {$filtro_pep}
        {$filtro_agencia}

    UNION ALL

    -- PEP CON PRODUCTOS DE APORTACIÓN
    SELECT 
        cli.compl_name AS nombre,
        cli.no_identifica AS dpi,
        'Aportación' AS tipo_producto,
        apr.ccodaport AS numero_producto,
        IFNULL(apt.nombre, 'Aportación') AS nombre_tipo_cuenta,
        apr.fecha_apertura AS fecha_apertura,
        0 AS monto_apertura,
        calcular_saldo_apr_tipcuenta(apr.ccodaport, ?) AS saldo,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM aprcta apr
    INNER JOIN tb_cliente cli ON apr.ccodcli = cli.idcod_cliente
    INNER JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
    LEFT JOIN aprtip apt ON apt.ccodtip = apr.ccodtip
    WHERE cli.estado = 1
        AND apr.estado IN ('A', 'B', '1')
        AND apr.fecha_apertura <= ?
        {$filtro_pep}
        {$filtro_agencia}

    UNION ALL

    -- PEP CON PRODUCTOS DE CRÉDITO
    SELECT 
        cli.compl_name AS nombre,
        cli.no_identifica AS dpi,
        'Crédito' AS tipo_producto,
        cre.CCODCTA AS numero_producto,
        IFNULL(tcred.descr, 'Crédito') AS nombre_tipo_cuenta,
        cre.DfecSol AS fecha_apertura,
        cre.NCapDes AS monto_apertura,
        (cre.NCapDes - COALESCE((
            SELECT SUM(kar.KP) 
            FROM CREDKAR kar 
            WHERE kar.CCODCTA = cre.CCODCTA 
                AND kar.CESTADO = 1
        ), 0)) AS saldo,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM cremcre_meta cre
    INNER JOIN tb_cliente cli ON cre.CodCli = cli.idcod_cliente
    INNER JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
    LEFT JOIN {$db_name_general}.tb_credito tcred ON tcred.abre = cre.CtipCre
    WHERE cli.estado = 1
        AND cre.Cestado IN ('1', 'V', 'A')
        AND cre.DfecSol <= ?
        {$filtro_pep}
        {$filtro_agencia}

    ORDER BY nombre, tipo_producto";

    $params = [$ffin, $ffin, $ffin, $ffin, $ffin];

} else {
    $strquery = "
    -- PEP CON PRODUCTOS DE AHORRO
    SELECT 
        cli.compl_name AS nombre,
        cli.no_identifica AS dpi,
        'Ahorro' AS tipo_producto,
        aho.ccodaho AS numero_producto,
        IFNULL(tip.nombre, 'Cuenta Ahorro') AS nombre_tipo_cuenta,
        aho.fecha_apertura AS fecha_apertura,
        0 AS monto_apertura,
        calcular_saldo_aho_tipcuenta(aho.ccodaho, ?) AS saldo,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM ahomcta aho
    INNER JOIN tb_cliente cli ON aho.ccodcli = cli.idcod_cliente
    INNER JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
    LEFT JOIN ahomtip tip ON SUBSTR(aho.ccodaho,7,2) = tip.ccodtip
    WHERE cli.estado = 1
        AND aho.estado IN ('A', 'B', '1')
        AND aho.fecha_apertura BETWEEN ? AND ?
        {$filtro_pep}
        {$filtro_agencia}

    UNION ALL

    -- PEP CON PRODUCTOS DE APORTACIÓN
    SELECT 
        cli.compl_name AS nombre,
        cli.no_identifica AS dpi,
        'Aportación' AS tipo_producto,
        apr.ccodaport AS numero_producto,
        IFNULL(apt.nombre, 'Aportación') AS nombre_tipo_cuenta,
        apr.fecha_apertura AS fecha_apertura,
        0 AS monto_apertura,
        calcular_saldo_apr_tipcuenta(apr.ccodaport, ?) AS saldo,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM aprcta apr
    INNER JOIN tb_cliente cli ON apr.ccodcli = cli.idcod_cliente
    INNER JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
    LEFT JOIN aprtip apt ON apt.ccodtip = apr.ccodtip
    WHERE cli.estado = 1
        AND apr.estado IN ('A', 'B', '1')
        AND apr.fecha_apertura BETWEEN ? AND ?
        {$filtro_pep}
        {$filtro_agencia}

    UNION ALL

    -- PEP CON PRODUCTOS DE CRÉDITO
    SELECT 
        cli.compl_name AS nombre,
        cli.no_identifica AS dpi,
        'Crédito' AS tipo_producto,
        cre.CCODCTA AS numero_producto,
        IFNULL(tcred.descr, 'Crédito') AS nombre_tipo_cuenta,
        cre.DfecSol AS fecha_apertura,
        cre.NCapDes AS monto_apertura,
        (cre.NCapDes - COALESCE((
            SELECT SUM(kar.KP) 
            FROM CREDKAR kar 
            WHERE kar.CCODCTA = cre.CCODCTA 
                AND kar.CESTADO = 1
        ), 0)) AS saldo,
        CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia) AS agencia
    FROM cremcre_meta cre
    INNER JOIN tb_cliente cli ON cre.CodCli = cli.idcod_cliente
    INNER JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
    LEFT JOIN {$db_name_general}.tb_credito tcred ON tcred.abre = cre.CtipCre
    WHERE cli.estado = 1
        AND cre.Cestado IN ('1', 'V', 'A')
        AND cre.DfecSol BETWEEN ? AND ?
        {$filtro_pep}
        {$filtro_agencia}

    ORDER BY nombre, tipo_producto";

    $params = [$ffin, $finicio, $ffin, $ffin, $finicio, $ffin, $finicio, $ffin];
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
        throw new Exception("No se encontraron personas PEP con productos activos para el periodo seleccionado");
    }

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
        $dataFormatted = array_map(function($row) {
            return [
                'nombre' => $row['nombre'],
                'dpi' => $row['dpi'],
                'tipo_producto' => $row['tipo_producto'],
                'numero_producto' => $row['numero_producto'],
                'nombre_tipo_cuenta' => $row['nombre_tipo_cuenta'],
                'fecha_apertura' => date('d/m/Y', strtotime($row['fecha_apertura'])),
                'monto_apertura' => floatval($row['monto_apertura']),
                'saldo' => floatval($row['saldo']),
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
    
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:I1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - PERSONAS EXPUESTAS POLITICAMENTE (PEP)');
    $sheet->mergeCells('A2:I2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Periodo: ' . $titlereport);
    $sheet->mergeCells('A3:I3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A4', 'Agencia: ' . $oficina);
    $sheet->mergeCells('A4:I4');
    
    $sheet->setCellValue('A5', 'Fecha de generacion: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A5:I5');
    
    $fila = 7;
    $sheet->setCellValue('A' . $fila, 'NOMBRE');
    $sheet->setCellValue('B' . $fila, 'DPI');
    $sheet->setCellValue('C' . $fila, 'TIPO PRODUCTO');
    $sheet->setCellValue('D' . $fila, 'NUMERO');
    $sheet->setCellValue('E' . $fila, 'NOMBRE CUENTA/CREDITO');
    $sheet->setCellValue('F' . $fila, 'FECHA APERTURA');
    $sheet->setCellValue('G' . $fila, 'MONTO APERTURA');
    $sheet->setCellValue('H' . $fila, 'SALDO');
    $sheet->setCellValue('I' . $fila, 'AGENCIA');
    
    $sheet->getStyle('A' . $fila . ':I' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    $fila = 8;
    $total_monto = 0;
    $total_saldo = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['nombre'] ?? '');
        // Forzar DPI como texto para evitar notación científica
        $sheet->setCellValueExplicit('B' . $fila, $reg['dpi'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $fila, $reg['tipo_producto'] ?? '');
        // Forzar número de producto como texto
        $sheet->setCellValueExplicit('D' . $fila, $reg['numero_producto'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('E' . $fila, $reg['nombre_tipo_cuenta'] ?? '');
        $sheet->setCellValue('F' . $fila, date('d/m/Y', strtotime($reg['fecha_apertura'])));
        $sheet->setCellValue('G' . $fila, floatval($reg['monto_apertura']));
        $sheet->setCellValue('H' . $fila, floatval($reg['saldo']));
        $sheet->setCellValue('I' . $fila, $reg['agencia'] ?? '');
        
        $total_monto += floatval($reg['monto_apertura']);
        $total_saldo += floatval($reg['saldo']);
        $fila++;
    }
    
    $sheet->setCellValue('A' . $fila, 'TOTAL:');
    $sheet->mergeCells('A' . $fila . ':F' . $fila);
    $sheet->setCellValue('G' . $fila, $total_monto);
    $sheet->setCellValue('H' . $fila, $total_saldo);
    $sheet->getStyle('A' . $fila . ':I' . $fila)->getFont()->setBold(true);
    $sheet->getStyle('A' . $fila . ':I' . $fila)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('E7E6E6');
    
    // Formato de moneda para columnas G y H
    $sheet->getStyle('G8:H' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    $sheet->getColumnDimension('A')->setWidth(35);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(25);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(15);
    $sheet->getColumnDimension('I')->setWidth(25);
    
    $ultimaFila = $fila;
    $sheet->getStyle('A7:I' . $ultimaFila)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
    
    ob_start();
    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();
    
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Repo_PEP_IVE" . str_replace(' ', '_', $titlereport),
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
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"] ?? '');
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
    $pdf->Cell(0, 6, decode_utf8('REPORTE IVE - PERSONAS EXPUESTAS POLÍTICAMENTE (PEP)'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, decode_utf8('Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, decode_utf8('Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(50, 6, 'NOMBRE', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'DPI', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'PRODUCTO', 1, 0, 'C', true);
    $pdf->Cell(18, 6, decode_utf8('NÚMERO'), 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'NOMBRE CTA/CRE', 1, 0, 'C', true);
    $pdf->Cell(20, 6, 'FECHA', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'MONTO APERT.', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'SALDO', 1, 0, 'C', true);
    $pdf->Cell(42, 6, 'AGENCIA', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $total_monto = 0;
    $total_saldo = 0;
    
    foreach ($registro as $reg) {
        $pdf->Cell(50, 5, decode_utf8(substr($reg['nombre'] ?? '', 0, 40)), 1, 0, 'L', $fill);
        $pdf->Cell(25, 5, $reg['dpi'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell(20, 5, decode_utf8($reg['tipo_producto'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell(18, 5, $reg['numero_producto'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell(35, 5, decode_utf8(substr($reg['nombre_tipo_cuenta'] ?? '', 0, 25)), 1, 0, 'L', $fill);
        $pdf->Cell(20, 5, date('d/m/Y', strtotime($reg['fecha_apertura'])), 1, 0, 'C', $fill);
        $pdf->Cell(25, 5, 'Q ' . number_format($reg['monto_apertura'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(25, 5, 'Q ' . number_format($reg['saldo'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(42, 5, decode_utf8(substr($reg['agencia'] ?? '', 0, 28)), 1, 1, 'L', $fill);
        
        $total_monto += $reg['monto_apertura'];
        $total_saldo += $reg['saldo'];
        $fill = !$fill;
    }
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(168, 6, 'TOTAL:', 1, 0, 'R', true);
    $pdf->Cell(25, 6, 'Q ' . number_format($total_monto, 2), 1, 0, 'R', true);
    $pdf->Cell(25, 6, 'Q ' . number_format($total_saldo, 2), 1, 0, 'R', true);
    $pdf->Cell(42, 6, '', 1, 1, 'C', true);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    $pdfData = $pdf->Output('S');
    
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Repo_PEP_IVE",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    ]);
}
