<?php
session_start();
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');

include '../../../src/funcphp/func_gen.php';
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';
require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intenta nuevamente']);
    exit;
}

$datos   = $_POST["datosval"] ?? [];
$inputs  = $datos[0] ?? [];
$selects = $datos[1] ?? [];
$radios  = $datos[2] ?? [];
$archivo = $datos[3] ?? [];
$tipo    = $_POST["tipo"] ?? '';

/* Validaciones */
if (empty($inputs) || empty($selects) || empty($radios)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Error en los parámetros recibidos']);
    exit;
}

if ($radios[0] == "anycuen" && empty($inputs[2])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccione una cuenta contable']);
    exit;
}

if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    exit;
}

if ($inputs[0] > $inputs[1]) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    exit;
}

/* Construcción de la condición de consulta */
$condi = "";
if ($selects[0] != "0") {
    $condi .= " AND d.id_agencia=" . intval($selects[0]);
}

if ($radios[0] == "anyf") {
    $condi .= " AND m.id_fuente_fondo=" . intval($selects[1]);
}

$condi .= " AND m.feccnt BETWEEN '" . mysqli_real_escape_string($conexion, $inputs[0]) . "' 
            AND '" . mysqli_real_escape_string($conexion, $inputs[1]) . "'";
$titlereport = " DEL " . date("d-m-Y", strtotime($inputs[0])) . " AL " . date("d-m-Y", strtotime($inputs[1]));

/* Consulta AGRUPADA por tipo de póliza */
$ctbmovdata = [];
try {
    $strquery = "SELECT 
                    p.descripcion AS tipo_poliza,
                    m.id_fuente_fondo,
                    COUNT(DISTINCT m.numcom) as cantidad_polizas,
                    SUM(m.debe) AS total_debe,
                    SUM(m.haber) AS total_haber,
                    MIN(m.feccnt) as fecha_inicio,
                    MAX(m.feccnt) as fecha_fin
                FROM ctb_diario d
                INNER JOIN ctb_diario_mov m ON d.id = m.id
                INNER JOIN $db_name_general.ctb_tipo_poliza p ON d.id_ctb_tipopoliza = p.id
                WHERE m.estado = 1 
                  AND m.id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia)
                $condi
                GROUP BY p.descripcion, m.id_fuente_fondo
                ORDER BY p.descripcion, m.id_fuente_fondo";
    
    $querypol = mysqli_query($conexion, $strquery);

    if (!$querypol) {
        throw new Exception("Error en la consulta: " . mysqli_error($conexion));
    }

    while ($fil = mysqli_fetch_assoc($querypol)) {
        $ctbmovdata[] = $fil;
    }

    if (empty($ctbmovdata)) {
        echo json_encode(['status' => 0, 'mensaje' => 'No hay datos']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 0, 'mensaje' => $e->getMessage()]);
    exit;
}

/* Consulta de totales generales */
try {
    $strqueryTot = "SELECT 
                        SUM(m.debe) AS total_debe,
                        SUM(m.haber) AS total_haber,
                        COUNT(DISTINCT m.numcom) as total_polizas
                    FROM ctb_diario d
                    INNER JOIN ctb_diario_mov m ON d.id = m.id
                    WHERE m.estado = 1 
                      AND m.id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia)
                    $condi";
    $queryTot = mysqli_query($conexion, $strqueryTot);
    if (!$queryTot) {
        throw new Exception("Error en la consulta de totales: " . mysqli_error($conexion));
    }
    $totales = mysqli_fetch_assoc($queryTot);
} catch (Exception $e) {
    echo json_encode(['status' => 0, 'mensaje' => $e->getMessage()]);
    exit;
}

/* Consulta de información de agencia */
$info = [];
try {
    $queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
        INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop WHERE ag.id_agencia=" . intval($_SESSION['id_agencia']));

    while ($fil = mysqli_fetch_assoc($queryins)) {
        $info[] = $fil;
    }

    if (empty($info)) {
        echo json_encode(['status' => 0, 'mensaje' => 'Institución asignada a la agencia no encontrada']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 0, 'mensaje' => $e->getMessage()]);
    exit;
}

/* Generación de reportes */
switch ($tipo) {
    case 'xlsx':
        printxls($ctbmovdata, [$titlereport], $totales, $selects, $radios, $inputs);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport], $info, $totales, $selects, $radios, $inputs);
        break;
}

/* Función mejorada para generar PDF */
function printpdf($registro, $datos, $info, $totales, $selects, $radios, $inputs)
{
    $oficina       = decode_utf8($info[0]["nom_agencia"]);
    $institucion   = decode_utf8($info[0]["nomb_comple"]);
    $direccionins  = decode_utf8($info[0]["muni_lug"]);
    $emailins      = $info[0]["emai"];
    $telefonosins  = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins        = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins   = "../../.." . $info[0]["log_img"];

    class PDF extends FPDF
    {
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $datos;
        public $selects;
        public $radios;
        public $inputs;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $selects, $radios, $inputs)
        {
            parent::__construct();
            $this->institucion  = $institucion;
            $this->pathlogo     = $pathlogo;
            $this->pathlogoins  = $pathlogoins;
            $this->oficina      = $oficina;
            $this->direccion    = $direccion;
            $this->email        = $email;
            $this->telefono     = $telefono;
            $this->nit          = $nit;
            $this->datos        = $datos;
            $this->selects      = $selects;
            $this->radios       = $radios;
            $this->inputs       = $inputs;
            $this->DefOrientation = 'L';
        }

        function Header()
        {
            $fuente = "Helvetica";
            
            // Logo y encabezado institucional
            if (file_exists($this->pathlogoins)) {
                $this->Image($this->pathlogoins, 10, 10, 35);
            }
            
            $this->SetFont($fuente, 'B', 14);
            $this->Cell(0, 6, $this->institucion, 0, 1, 'C');
            $this->SetFont($fuente, '', 10);
            $this->Cell(0, 4, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 4, 'Email: ' . $this->email . ' | Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 4, 'NIT: ' . $this->nit, 0, 1, 'C');
            $this->Ln(5);

            // Título del reporte
            $this->SetFont($fuente, 'B', 12);
            $this->SetFillColor(70, 130, 180);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(0, 8, 'LIBRO CAJA - RESUMEN POR TIPO DE POLIZA ' . $this->datos[0], 0, 1, 'C', true);
            $this->SetTextColor(0, 0, 0);
            $this->Ln(3);

            // Información de filtros en cuadro
            $this->SetFont($fuente, '', 9);
            $this->SetFillColor(245, 245, 245);
            $this->Cell(0, 1, '', 0, 1); // Línea separadora
            $this->Cell(90, 6, 'Agencia: ' . ($this->selects[0] != '0' ? $this->selects[0] : 'Todas las agencias'), 1, 0, 'L', true);
            $this->Cell(90, 6, 'Fuente Fondo: ' . ($this->radios[0] == 'anyf' ? $this->selects[1] : 'Todas las fuentes'), 1, 0, 'L', true);
            $this->Cell(90, 6, 'Periodo: ' . date("d/m/Y", strtotime($this->inputs[0])) . ' - ' . date("d/m/Y", strtotime($this->inputs[1])), 1, 1, 'L', true);
            $this->Ln(5);

            // Encabezados de tabla con mejor diseño
            $this->SetFont($fuente, 'B', 10);
            $this->SetFillColor(230, 230, 230);
            $this->SetDrawColor(100, 100, 100);
            $this->Cell(80, 8, 'TIPO DE POLIZA', 1, 0, 'C', true);
            $this->Cell(35, 8, 'FUENTE FONDO', 1, 0, 'C', true);
            $this->Cell(30, 8, 'CANTIDAD', 1, 0, 'C', true);
            $this->Cell(45, 8, 'TOTAL DEBE', 1, 0, 'C', true);
            $this->Cell(45, 8, 'TOTAL HABER', 1, 0, 'C', true);
            $this->Cell(35, 8, 'DIFERENCIA', 1, 1, 'C', true);
            $this->Ln(2);
        }

        function Footer()
        {
            $this->SetY(-20);
            $this->SetFont('Arial', 'I', 8);
            $this->SetDrawColor(100, 100, 100);
            $this->Line(10, $this->GetY(), 290, $this->GetY());
            $this->Ln(2);
            $this->Cell(0, 5, 'Generado el ' . date('d/m/Y H:i:s'), 0, 0, 'L');
            $this->Cell(0, 5, 'Página ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $selects, $radios, $inputs);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    $fuente = "Helvetica";
    $pdf->SetFont($fuente, '', 9);
    $pdf->SetDrawColor(200, 200, 200);

    // Variables para colores alternos
    $fill = false;
    $total_debe_general = 0;
    $total_haber_general = 0;
    $total_polizas_general = 0;

    // Imprimir filas de datos agrupados
    foreach ($registro as $row) {
        $tipo_poliza    = decode_utf8($row['tipo_poliza']);
        $fuente_fondo   = $row['id_fuente_fondo'];
        $cantidad       = number_format($row['cantidad_polizas'], 0, '.', ',');
        $debe           = floatval($row['total_debe']);
        $haber          = floatval($row['total_haber']);
        $diferencia     = $debe - $haber;
        
        $debe_formatted = number_format($debe, 2, '.', ',');
        $haber_formatted = number_format($haber, 2, '.', ',');
        $diferencia_formatted = number_format($diferencia, 2, '.', ',');
        
        // Acumular totales
        $total_debe_general += $debe;
        $total_haber_general += $haber;
        $total_polizas_general += $row['cantidad_polizas'];
        
        // Color de fondo alternado
        if ($fill) {
            $pdf->SetFillColor(248, 248, 248);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Color para diferencia (rojo si negativo, verde si positivo)
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->Cell(80, 6, $tipo_poliza, 1, 0, 'L', $fill);
        $pdf->Cell(35, 6, $fuente_fondo, 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $cantidad, 1, 0, 'C', $fill);
        $pdf->Cell(45, 6, $debe_formatted, 1, 0, 'R', $fill);
        $pdf->Cell(45, 6, $haber_formatted, 1, 0, 'R', $fill);
        
        // Colorear la diferencia según el signo
        if ($diferencia < 0) {
            $pdf->SetTextColor(200, 0, 0); // Rojo para negativo
        } elseif ($diferencia > 0) {
            $pdf->SetTextColor(0, 150, 0); // Verde para positivo
        }
        
        $pdf->Cell(35, 6, $diferencia_formatted, 1, 1, 'R', $fill);
        $pdf->SetTextColor(0, 0, 0); // Restablecer color
        
        $fill = !$fill; // Alternar color
    }

    // Línea de separación antes de totales
    $pdf->Ln(3);
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->Line(10, $pdf->GetY(), 280, $pdf->GetY());
    $pdf->Ln(3);

    // Fila de totales con mejor formato
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->SetFillColor(70, 130, 180);
    $pdf->SetTextColor(255, 255, 255);
    
    $diferencia_total = $total_debe_general - $total_haber_general;
    
    $pdf->Cell(115, 8, 'TOTALES GENERALES', 1, 0, 'C', true);
    $pdf->Cell(30, 8, number_format($total_polizas_general, 0, '.', ','), 1, 0, 'C', true);
    $pdf->Cell(45, 8, number_format($total_debe_general, 2, '.', ','), 1, 0, 'R', true);
    $pdf->Cell(45, 8, number_format($total_haber_general, 2, '.', ','), 1, 0, 'R', true);
    $pdf->Cell(35, 8, number_format($diferencia_total, 2, '.', ','), 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    
    // Información adicional
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', 8);
    $pdf->Cell(0, 4, 'Resumen: Se procesaron ' . count($registro) . ' tipos de póliza diferentes', 0, 1, 'L');
    $pdf->Cell(0, 4, 'Total de pólizas individuales: ' . number_format($total_polizas_general, 0, '.', ','), 0, 1, 'L');

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status'   => 1,
        'mensaje'  => 'Reporte generado correctamente',
        'namefile' => "Libro_Caja_Resumen_" . date("Y-m-d"),
        'tipo'     => "pdf",
        'data'     => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

/* Función mejorada para generar Excel */
function printxls($ctbmovdata, $params, $totales, $selects, $radios, $inputs)
{
    $titlereport = $params[0];

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    // Título principal
    $sheet->setCellValue('A1', 'LIBRO CAJA - RESUMEN POR TIPO DE POLIZA' . $titlereport);
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF4682B4');
    $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');

    // Información de filtros
    $sheet->setCellValue('A3', 'Agencia: ' . ($selects[0] != '0' ? $selects[0] : 'Todas las agencias'));
    $sheet->setCellValue('C3', 'Fuente Fondo: ' . ($radios[0] == 'anyf' ? $selects[1] : 'Todas las fuentes'));
    $sheet->setCellValue('E3', 'Periodo: ' . date("d/m/Y", strtotime($inputs[0])) . ' - ' . date("d/m/Y", strtotime($inputs[1])));

    // Encabezados de columnas con estilo
    $headers = ['Tipo de Poliza', 'Fuente Fondo', 'Cantidad Pólizas', 'Total Debe', 'Total Haber', 'Diferencia', 'Fecha Rango'];
    $cols    = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    $rowNum  = 5;
    
    foreach ($headers as $i => $header) {
        $sheet->setCellValue($cols[$i] . $rowNum, $header);
        $sheet->getStyle($cols[$i] . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle($cols[$i] . $rowNum)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6E6E6');
    }

    // Datos agrupados
    $rowNum = 6;
    $total_debe_excel = 0;
    $total_haber_excel = 0;
    $total_polizas_excel = 0;
    
    foreach ($ctbmovdata as $row) {
        $debe = floatval($row['total_debe']);
        $haber = floatval($row['total_haber']);
        $diferencia = $debe - $haber;
        
        $sheet->setCellValue('A' . $rowNum, $row['tipo_poliza']);
        $sheet->setCellValue('B' . $rowNum, $row['id_fuente_fondo']);
        $sheet->setCellValue('C' . $rowNum, $row['cantidad_polizas']);
        $sheet->setCellValue('D' . $rowNum, $debe);
        $sheet->setCellValue('E' . $rowNum, $haber);
        $sheet->setCellValue('F' . $rowNum, $diferencia);
        $sheet->setCellValue('G' . $rowNum, date("d/m/Y", strtotime($row['fecha_inicio'])) . ' - ' . date("d/m/Y", strtotime($row['fecha_fin'])));
        
        // Formatear números
        $sheet->getStyle('D' . $rowNum . ':F' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Color para diferencias negativas
        if ($diferencia < 0) {
            $sheet->getStyle('F' . $rowNum)->getFont()->getColor()->setARGB('FFFF0000');
        } elseif ($diferencia > 0) {
            $sheet->getStyle('F' . $rowNum)->getFont()->getColor()->setARGB('FF008000');
        }
        
        $total_debe_excel += $debe;
        $total_haber_excel += $haber;
        $total_polizas_excel += $row['cantidad_polizas'];
        
        $rowNum++;
    }

    // Fila de totales
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, "TOTALES GENERALES");
    $sheet->mergeCells('A' . $rowNum . ':B' . $rowNum);
    $sheet->setCellValue('C' . $rowNum, $total_polizas_excel);
    $sheet->setCellValue('D' . $rowNum, $total_debe_excel);
    $sheet->setCellValue('E' . $rowNum, $total_haber_excel);
    $sheet->setCellValue('F' . $rowNum, $total_debe_excel - $total_haber_excel);
    
    // Estilo para la fila de totales
    $sheet->getStyle("A{$rowNum}:G{$rowNum}")->getFont()->setBold(true);
    $sheet->getStyle("A{$rowNum}:G{$rowNum}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF4682B4');
    $sheet->getStyle("A{$rowNum}:G{$rowNum}")->getFont()->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle("C{$rowNum}:F{$rowNum}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Ajustar ancho de columnas
    foreach ($cols as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status'   => 1,
        'mensaje'  => 'Reporte generado correctamente',
        'namefile' => "Libro_Caja_Resumen_" . date("Y-m-d"),
        'tipo'     => "vnd.ms-excel",
        'data'     => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
?>