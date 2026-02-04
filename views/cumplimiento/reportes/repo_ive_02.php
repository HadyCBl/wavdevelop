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
$radios = $datos[2];      // Array de radios [ragencia, tipofecha2]
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
// CONSTRUCCIÓN DE CONSULTA SQL - CLIENTES CON TODOS SUS PRODUCTOS DETALLADOS
// ============================================================================
$filtro_agencia = ($radios[0] == "anyofi") ? " AND cli.agencia = " . intval($selects[0]) : "";

// Filtro de fecha sobre fecha de apertura del producto
if ($tipofecha === 'corte') {
    $filtro_fecha_aho = " AND aho.fecha_apertura <= ?";
    $filtro_fecha_apr = " AND apr.fecha_apertura <= ?";
    $filtro_fecha_cre = " AND cre.DFecDsbls <= ?";
    $params_fecha = [$ffin, $ffin, $ffin];
} else {
    $filtro_fecha_aho = " AND aho.fecha_apertura BETWEEN ? AND ?";
    $filtro_fecha_apr = " AND apr.fecha_apertura BETWEEN ? AND ?";
    $filtro_fecha_cre = " AND cre.DFecDsbls BETWEEN ? AND ?";
    $params_fecha = [$finicio, $ffin, $finicio, $ffin, $finicio, $ffin];
}

// ========== CONSULTA DETALLADA - CADA PRODUCTO EN UNA FILA ==========
$strquery = "
-- PRODUCTOS DE AHORRO
SELECT 
    cli.idcod_cliente AS codigo,
    cli.compl_name AS nombre,
    cli.no_identifica AS dpi,
    'Ahorro' AS tipo_producto,
    aho.ccodaho AS numero_producto,
    COALESCE(aht.nombre, 'Cuenta de Ahorro') AS nombre_producto,
    cli.fecha_alta AS fecha_inicio_relacion,
    aho.fecha_apertura AS fecha_producto,
    calcular_saldo_aho_tipcuenta(aho.ccodaho, ?) AS saldo,
    COALESCE(CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia), 'Sin agencia') AS agencia
FROM ahomcta aho
INNER JOIN tb_cliente cli ON aho.ccodcli = cli.idcod_cliente
LEFT JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN ahomtip aht ON SUBSTR(aho.ccodaho,7,2) = aht.ccodtip
WHERE cli.id_tipoCliente = 'Natural'
    AND cli.estado = 1
    AND cli.fiador != 1
    AND aho.estado = 1
    {$filtro_agencia}
    {$filtro_fecha_aho}

UNION ALL

-- PRODUCTOS DE APORTACIÓN
SELECT 
    cli.idcod_cliente AS codigo,
    cli.compl_name AS nombre,
    cli.no_identifica AS dpi,
    'Aportación' AS tipo_producto,
    apr.ccodaport AS numero_producto,
    COALESCE(apt.nombre, 'Cuenta de Aportación') AS nombre_producto,
    cli.fecha_alta AS fecha_inicio_relacion,
    apr.fecha_apertura AS fecha_producto,
    calcular_saldo_apr_tipcuenta(apr.ccodaport, ?) AS saldo,
    COALESCE(CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia), 'Sin agencia') AS agencia
FROM aprcta apr
INNER JOIN tb_cliente cli ON apr.ccodcli = cli.idcod_cliente
LEFT JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN aprtip apt ON SUBSTR(apr.ccodaport,7,2) = apt.ccodtip
WHERE cli.id_tipoCliente = 'Natural'
    AND cli.estado = 1
    AND cli.fiador != 1
    AND apr.estado = 1
    {$filtro_agencia}
    {$filtro_fecha_apr}

UNION ALL

-- PRODUCTOS DE CRÉDITO
SELECT 
    cli.idcod_cliente AS codigo,
    cli.compl_name AS nombre,
    cli.no_identifica AS dpi,
    'Crédito' AS tipo_producto,
    cre.CCODCTA AS numero_producto,
    COALESCE(tcred.descr, 'Crédito') AS nombre_producto,
    cli.fecha_alta AS fecha_inicio_relacion,
    cre.DFecDsbls AS fecha_producto,
    (cre.NCapDes - COALESCE((
        SELECT SUM(kar.KP) 
        FROM CREDKAR kar 
        WHERE kar.CCODCTA = cre.CCODCTA 
            AND kar.CESTADO = 1
    ), 0)) AS saldo,
    COALESCE(CONCAT(ag.cod_agenc, ' - ', ag.nom_agencia), 'Sin agencia') AS agencia
FROM cremcre_meta cre
INNER JOIN tb_cliente cli ON cre.CodCli = cli.idcod_cliente
LEFT JOIN tb_agencia ag ON cli.agencia = ag.id_agencia
LEFT JOIN {$db_name_general}.tb_credito tcred ON tcred.abre = cre.CtipCre
WHERE cli.id_tipoCliente = 'Natural'
    AND cli.estado = 1
    AND cli.fiador != 1
    AND cre.Cestado IN ('1', 'V', 'A')
    {$filtro_agencia}
    {$filtro_fecha_cre}

ORDER BY nombre, tipo_producto, fecha_producto";

// Parámetros: fecha para saldo ahorro, filtro fecha ahorro, fecha saldo aportación, filtro fecha aportación, filtro fecha crédito
if ($tipofecha === 'corte') {
    // 1:saldo_aho, 2:filtro_aho, 3:saldo_apr, 4:filtro_apr, 5:filtro_cre
    $params = [$ffin, $ffin, $ffin, $ffin, $ffin];
} else {
    // 1:saldo_aho, 2-3:filtro_aho(ini,fin), 4:saldo_apr, 5-6:filtro_apr(ini,fin), 7-8:filtro_cre(ini,fin)
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
        throw new Exception("No se encontraron clientes individuales con productos para el periodo seleccionado");
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
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
                'dpi' => $row['dpi'],
                'tipo_producto' => $row['tipo_producto'],
                'fecha_inicio_relacion' => date('d/m/Y', strtotime($row['fecha_inicio_relacion'])),
                'fecha_producto' => date('d/m/Y', strtotime($row['fecha_producto'])),
                'saldo' => floatval($row['saldo']),
                'agencia' => $row['agencia']
            ];
        }, $result);
        
        // Calcular totales por tipo de producto
        $totales = [
            'ahorro' => 0,
            'aportacion' => 0,
            'credito' => 0,
            'total' => 0
        ];
        foreach ($result as $row) {
            $saldo = floatval($row['saldo']);
            $totales['total'] += $saldo;
            switch ($row['tipo_producto']) {
                case 'Ahorro': $totales['ahorro'] += $saldo; break;
                case 'Aportación': $totales['aportacion'] += $saldo; break;
                case 'Crédito': $totales['credito'] += $saldo; break;
            }
        }
        
        echo json_encode([
            'status' => 1,
            'mensaje' => 'Datos cargados correctamente',
            'data' => $dataFormatted,
            'total' => count($dataFormatted),
            'totales' => $totales
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
    // Para Excel NO usar decode_utf8, PhpSpreadsheet necesita UTF-8 directamente
    $oficina = $info[0]["nom_agencia"] ?? '';
    $institucion = $info[0]["nomb_comple"] ?? '';
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // ========== ENCABEZADO ==========
    $sheet->setCellValue('A1', $institucion);
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'REPORTE IVE - CLIENTES PERSONAS INDIVIDUALES');
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
    $sheet->setCellValue('A' . $fila, 'CÓDIGO');
    $sheet->setCellValue('B' . $fila, 'NOMBRE');
    $sheet->setCellValue('C' . $fila, 'DPI');
    $sheet->setCellValue('D' . $fila, 'TIPO PRODUCTO');
    $sheet->setCellValue('E' . $fila, 'NO. PRODUCTO');
    $sheet->setCellValue('F' . $fila, 'FECHA PRODUCTO');
    $sheet->setCellValue('G' . $fila, 'SALDO');
    $sheet->setCellValue('H' . $fila, 'AGENCIA');
    
    // Estilo para encabezados
    $sheet->getStyle('A' . $fila . ':H' . $fila)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    
    // ========== DATOS ==========
    $fila = 8;
    $total_ahorro = 0;
    $total_aportacion = 0;
    $total_credito = 0;
    $count_ahorro = 0;
    $count_aportacion = 0;
    $count_credito = 0;
    
    foreach ($registro as $reg) {
        $sheet->setCellValue('A' . $fila, $reg['codigo'] ?? '');
        $sheet->setCellValue('B' . $fila, $reg['nombre'] ?? '');
        // Forzar DPI como texto para evitar notación científica
        $sheet->setCellValueExplicit('C' . $fila, $reg['dpi'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('D' . $fila, $reg['tipo_producto'] ?? '');
        $sheet->setCellValueExplicit('E' . $fila, $reg['numero_producto'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('F' . $fila, date('d/m/Y', strtotime($reg['fecha_producto'])));
        $sheet->setCellValue('G' . $fila, floatval($reg['saldo']));
        $sheet->setCellValue('H' . $fila, $reg['agencia'] ?? '');
        
        // Acumular totales por tipo
        $saldo = floatval($reg['saldo']);
        switch ($reg['tipo_producto']) {
            case 'Ahorro': 
                $total_ahorro += $saldo; 
                $count_ahorro++;
                break;
            case 'Aportación': 
                $total_aportacion += $saldo; 
                $count_aportacion++;
                break;
            case 'Crédito': 
                $total_credito += $saldo; 
                $count_credito++;
                break;
        }
        $fila++;
    }
    
    // ========== FILA DE TOTALES ==========
    $sheet->setCellValue('A' . $fila, 'TOTAL GENERAL:');
    $sheet->mergeCells('A' . $fila . ':F' . $fila);
    $sheet->setCellValue('G' . $fila, $total_ahorro + $total_aportacion + $total_credito);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getFont()->setBold(true);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('E7E6E6');
    
    // Formato de número para columna de saldos
    $sheet->getStyle('G8:G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // ========== RESUMEN POR TIPO DE PRODUCTO ==========
    $fila += 2;
    $sheet->setCellValue('A' . $fila, 'RESUMEN POR TIPO DE PRODUCTO:');
    $sheet->getStyle('A' . $fila)->getFont()->setBold(true);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Tipo');
    $sheet->setCellValue('B' . $fila, 'Cantidad');
    $sheet->setCellValue('C' . $fila, 'Saldo Total');
    $sheet->getStyle('A' . $fila . ':C' . $fila)->getFont()->setBold(true);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Ahorro');
    $sheet->setCellValue('B' . $fila, $count_ahorro);
    $sheet->setCellValue('C' . $fila, $total_ahorro);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Aportación');
    $sheet->setCellValue('B' . $fila, $count_aportacion);
    $sheet->setCellValue('C' . $fila, $total_aportacion);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'Crédito');
    $sheet->setCellValue('B' . $fila, $count_credito);
    $sheet->setCellValue('C' . $fila, $total_credito);
    $fila++;
    
    $sheet->setCellValue('A' . $fila, 'TOTAL');
    $sheet->setCellValue('B' . $fila, $count_ahorro + $count_aportacion + $count_credito);
    $sheet->setCellValue('C' . $fila, $total_ahorro + $total_aportacion + $total_credito);
    $sheet->getStyle('A' . $fila . ':C' . $fila)->getFont()->setBold(true);
    
    // Formato de números en resumen
    $sheet->getStyle('C' . ($fila - 4) . ':C' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // ========== AJUSTAR ANCHOS DE COLUMNA ==========
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(35);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(30);
    
    // ========== BORDES PARA TODA LA TABLA ==========
    $ultimaFilaDatos = $fila - 7; // fila antes del resumen
    $sheet->getStyle('A7:H' . $ultimaFilaDatos)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
    
    // Alinear columnas numéricas a la derecha
    $sheet->getStyle('G8:G' . ($ultimaFilaDatos - 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    
    // ========== GENERAR ARCHIVO ==========
    ob_start();
    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();
    
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Repo_ClientesIndividuales_IVE" . str_replace(' ', '_', $titlereport),
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
    $rutalogoins = "../../.." . ($info[0]["log_img"] ?? '');
    
    $pdf = new FPDF('L', 'mm', 'Letter');
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
    $pdf->Cell(0, 6, decode_utf8('REPORTE IVE - CLIENTES PERSONAS INDIVIDUALES'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, decode_utf8('Periodo: ' . $titlereport), 0, 1, 'C');
    $pdf->Cell(0, 5, decode_utf8('Agencia: ' . $oficina), 0, 1, 'C');
    $pdf->Ln(5);
    
    // ========== ENCABEZADOS DE TABLA ==========
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(15, 6, decode_utf8('CÓDIGO'), 1, 0, 'C', true);
    $pdf->Cell(55, 6, 'NOMBRE', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'DPI', 1, 0, 'C', true);
    $pdf->Cell(22, 6, 'PRODUCTO', 1, 0, 'C', true);
    $pdf->Cell(22, 6, 'NO. PROD.', 1, 0, 'C', true);
    $pdf->Cell(22, 6, 'FECHA', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'SALDO', 1, 0, 'C', true);
    $pdf->Cell(68, 6, 'AGENCIA', 1, 1, 'C', true);
    
    // ========== DATOS ==========
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $total_ahorro = 0;
    $total_aportacion = 0;
    $total_credito = 0;
    $count_ahorro = 0;
    $count_aportacion = 0;
    $count_credito = 0;
    
    foreach ($registro as $reg) {
        // Verificar si necesitamos nueva página
        if ($pdf->GetY() > 175) {
            $pdf->AddPage();
            // Re-imprimir encabezados
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(68, 114, 196);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(15, 6, decode_utf8('CÓDIGO'), 1, 0, 'C', true);
            $pdf->Cell(55, 6, 'NOMBRE', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'DPI', 1, 0, 'C', true);
            $pdf->Cell(22, 6, 'PRODUCTO', 1, 0, 'C', true);
            $pdf->Cell(22, 6, 'NO. PROD.', 1, 0, 'C', true);
            $pdf->Cell(22, 6, 'FECHA', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'SALDO', 1, 0, 'C', true);
            $pdf->Cell(68, 6, 'AGENCIA', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 6);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        $pdf->Cell(15, 5, $reg['codigo'] ?? '', 1, 0, 'L', $fill);
        $pdf->Cell(55, 5, decode_utf8(substr($reg['nombre'] ?? '', 0, 45)), 1, 0, 'L', $fill);
        $pdf->Cell(25, 5, $reg['dpi'] ?? '', 1, 0, 'C', $fill);
        $pdf->Cell(22, 5, decode_utf8($reg['tipo_producto'] ?? ''), 1, 0, 'C', $fill);
        $pdf->Cell(22, 5, substr($reg['numero_producto'] ?? '', 0, 15), 1, 0, 'C', $fill);
        $pdf->Cell(22, 5, date('d/m/Y', strtotime($reg['fecha_producto'])), 1, 0, 'C', $fill);
        $pdf->Cell(25, 5, 'Q ' . number_format(floatval($reg['saldo']), 2), 1, 0, 'R', $fill);
        $pdf->Cell(68, 5, decode_utf8(substr($reg['agencia'] ?? '', 0, 45)), 1, 1, 'L', $fill);
        
        // Acumular totales
        $saldo = floatval($reg['saldo']);
        switch ($reg['tipo_producto']) {
            case 'Ahorro': 
                $total_ahorro += $saldo; 
                $count_ahorro++;
                break;
            case 'Aportación': 
                $total_aportacion += $saldo; 
                $count_aportacion++;
                break;
            case 'Crédito': 
                $total_credito += $saldo; 
                $count_credito++;
                break;
        }
        $fill = !$fill;
    }
    
    // ========== FILA DE TOTALES ==========
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(68, 114, 196);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(161, 6, 'TOTAL GENERAL:', 1, 0, 'R', true);
    $pdf->Cell(25, 6, 'Q ' . number_format($total_ahorro + $total_aportacion + $total_credito, 2), 1, 0, 'R', true);
    $pdf->Cell(68, 6, '', 1, 1, 'C', true);
    
    // ========== RESUMEN POR TIPO ==========
    $pdf->Ln(5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 5, 'RESUMEN POR TIPO DE PRODUCTO:', 0, 1, 'L');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(40, 5, 'Tipo', 1, 0, 'C', true);
    $pdf->Cell(30, 5, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(40, 5, 'Saldo Total', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(40, 5, 'Ahorro', 1, 0, 'L');
    $pdf->Cell(30, 5, $count_ahorro, 1, 0, 'C');
    $pdf->Cell(40, 5, 'Q ' . number_format($total_ahorro, 2), 1, 1, 'R');
    
    $pdf->Cell(40, 5, decode_utf8('Aportación'), 1, 0, 'L');
    $pdf->Cell(30, 5, $count_aportacion, 1, 0, 'C');
    $pdf->Cell(40, 5, 'Q ' . number_format($total_aportacion, 2), 1, 1, 'R');
    
    $pdf->Cell(40, 5, decode_utf8('Crédito'), 1, 0, 'L');
    $pdf->Cell(30, 5, $count_credito, 1, 0, 'C');
    $pdf->Cell(40, 5, 'Q ' . number_format($total_credito, 2), 1, 1, 'R');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(40, 5, 'TOTAL', 1, 0, 'L');
    $pdf->Cell(30, 5, $count_ahorro + $count_aportacion + $count_credito, 1, 0, 'C');
    $pdf->Cell(40, 5, 'Q ' . number_format($total_ahorro + $total_aportacion + $total_credito, 2), 1, 1, 'R');
    
    // ========== PIE DE PÁGINA ==========
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s') . ' | Total registros: ' . count($registro), 0, 1, 'R');
    
    // ========== GENERAR SALIDA ==========
    $pdfData = $pdf->Output('S');
    
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Repo_ClientesIndividuales_IVE",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    ]);
}
