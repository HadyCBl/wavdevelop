<?php

/**
 * Reporte Consolidado de Otros Ingresos/Egresos
 * Muestra una fila por cada movimiento registrado con información de factura FEL
 */

include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();

use Micro\Helpers\Log;
use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Helpers\CSRFProtection;

/**
 * Recupera los datos POST enviados por el formulario.
 */
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

/**
 * Valida el token CSRF.
 */
$csrf = new CSRFProtection();

if (!isset($inputs[2]) || !($csrf->validateToken($inputs[2], false))) {
    $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
    $opResult = array(
        'status' => 0,
        'mensaje' => $errorcsrf,
        'messagecontrol' => 'error'
    );
    echo json_encode($opResult);
    return;
}

/**
 * Verifica si la sesión ha expirado.
 */
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

/**
 * Recupera la información del usuario de la sesión.
 */
$idusuario = $_SESSION['id'];
$nombreusu = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];

/**
 * Incluye la configuración de la base de datos y funciones de utilidad.
 */
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';

/**
 * Carga las bibliotecas necesarias para la generación de PDF y Excel.
 */
require __DIR__ . '/../../../fpdf/fpdf.php';
// require __DIR__ . '/../../../vendor/autoload.php';

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}
if ($inputs[0] > $inputs[1]) {
    echo json_encode(['mensaje' => 'Rango de fechas Inválido', 'status' => 0]);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$fecinicio = $inputs[0];
$fecfin = $inputs[1];
$where = ($selects[0] == "0") ? "" : " AND tp.agencia=" . $selects[0];
$tipoMovimiento = $selects[1];
$where .= ($tipoMovimiento != "0") ? " AND tpm.id_otr_tipo_ingreso=" . $tipoMovimiento : "";

$titlereport = " DEL " . Date::toDmY($fecinicio) . " AL " . Date::toDmY($fecfin);

// Query para obtener datos consolidados con información de factura FEL
$query = "SELECT 
    tp.fecha AS fecha,
    tp.recibo AS n_asiento,
    COALESCE(cvo.numero_dte, '') AS comprobante,
    tp.descripcion AS concepto,
    IFNULL(emis.nombre_comercial, '') AS razon_social,
    CASE WHEN tpi.tipo = 1 THEN SUM(tpm.monto) ELSE 0 END AS debe_q,
    CASE WHEN tpi.tipo = 2 THEN SUM(tpm.monto) ELSE 0 END AS haber_q,
    tpi.nombre_gasto AS detalle,tpi.id AS id_tipo_ingreso, ofi.nom_agencia,
    tpi.tipo,
    tp.tipoadicional,
    cvo.numero_dte,
    cvo.numero_serie,
    cvo.nit
FROM otr_pago tp
INNER JOIN otr_pago_mov tpm ON tpm.id_otr_pago = tp.id 
INNER JOIN otr_tipo_ingreso tpi ON tpm.id_otr_tipo_ingreso = tpi.id 
LEFT JOIN cv_otros_movimientos cvo ON cvo.id = tpm.id_fel
LEFT JOIN cv_emisor emis ON emis.id = cvo.id_receptor
INNER JOIN tb_agencia ofi ON ofi.id_agencia = tp.agencia
WHERE tpi.tipo = ? AND (tp.fecha BETWEEN ? AND ?) AND tp.estado = 1 $where 
GROUP BY tp.id, tp.fecha, tp.recibo, cvo.numero_dte, tp.descripcion, tp.cliente, 
         tpi.nombre_gasto, tpi.tipo, 
         tp.tipoadicional, cvo.numero_serie, cvo.nit
ORDER BY tpi.id, tp.fecha, tp.id";

$tipo_ingreso = ($radios[0] == 1) ? "INGRESOS" : "EGRESOS";

$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$radios[0], $fecinicio, $fecfin]);

    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No hay registros para este periodo");
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("No se encontró la institución asignada a la agencia");
    }
    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

$nomagencia = ($selects[0] == 0) ? " DE TODAS LAS AGENCIAS " : " DE LA AGENCIA: " . strtoupper($result[0]['nom_agencia']);
$texto_reporte = "REPORTE CONSOLIDADO DE " . $tipo_ingreso . " DEL " .  setdatefrench($inputs[0]) . " AL " . setdatefrench($inputs[1]) . $nomagencia;

// Calcular totales acumulados
$total_acumulado = 0;
foreach ($result as $key => &$row) {
    $total_acumulado += $row['debe_q'];
    $row['parcial_q'] = $total_acumulado;
}

switch ($tipo) {
    case 'xlsx':
        printxls($result, $texto_reporte, $info, $tipo_ingreso);
        break;
    case 'pdf':
        printpdf($result, $texto_reporte, $info, $tipo_ingreso);
        break;
}

//FUNCION PARA GENERAR EL REPORTE EN PDF
function printpdf($datos, $texto_reporte, $info, $tipo_ingreso)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    class PDF extends FPDF
    {
        public $oficina;
        public $institucion;
        public $direccionins;
        public $emailins;
        public $telefonosins;
        public $nitins;
        public $rutalogomicro;
        public $rutalogoins;
        public $texto_reporte;
        public $tipo_ingreso;

        function __construct($oficina, $institucion, $direccionins, $emailins, $telefonosins, $nitins, $rutalogomicro, $rutalogoins, $texto_reporte, $tipo_ingreso)
        {
            parent::__construct('L', 'mm', 'Legal'); // Orientación horizontal
            $this->oficina = $oficina;
            $this->institucion = $institucion;
            $this->direccionins = $direccionins;
            $this->emailins = $emailins;
            $this->telefonosins = $telefonosins;
            $this->nitins = $nitins;
            $this->rutalogomicro = $rutalogomicro;
            $this->rutalogoins = $rutalogoins;
            $this->texto_reporte = $texto_reporte;
            $this->tipo_ingreso = $tipo_ingreso;
        }

        function Header()
        {
            // Fondo del encabezado
            $this->SetFillColor(245, 245, 245);
            $this->Rect(0, 0, $this->GetPageWidth(), 35, 'F');

            // Logo izquierdo con borde
            if (file_exists($this->rutalogomicro)) {
                $this->Image($this->rutalogomicro, 12, 8, 22);
            }

            // Logo derecho con borde
            if (file_exists($this->rutalogoins)) {
                $this->Image($this->rutalogoins, 318, 8, 22);
            }

            // Información de la institución
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(44, 62, 80);
            $this->Cell(0, 6, Utf8::decode($this->institucion), 0, 1, 'C');
            
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(52, 73, 94);
            $this->Cell(0, 4, Utf8::decode($this->direccionins), 0, 1, 'C');
            $this->Cell(0, 4, 'Tel: ' . $this->telefonosins . ' | Email: ' . $this->emailins, 0, 1, 'C');
            $this->Cell(0, 4, 'NIT: ' . $this->nitins, 0, 1, 'C');

            // Línea separadora
            $this->SetDrawColor(52, 152, 219);
            $this->SetLineWidth(0.5);
            $this->Line(10, 33, $this->GetPageWidth() - 10, 33);
            
            $this->Ln(2);

            // Título del reporte con fondo
            $this->SetFont('Arial', 'B', 11);
            $this->SetFillColor(52, 152, 219);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(0, 7, Utf8::decode($this->texto_reporte), 0, 1, 'C', true);
            
            $this->Ln(3);

            // Encabezados de tabla con degradado visual
            $this->SetFont('Arial', 'B', 8);
            $this->SetFillColor(41, 128, 185);
            $this->SetTextColor(255, 255, 255);
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);

            $widths = [5, 20, 20, 50, 100, 75, 25, 25];

            $this->Cell($widths[0], 7, '#', 1, 0, 'C', true);
            $this->Cell($widths[1], 7, 'Fecha', 1, 0, 'C', true);
            $this->Cell($widths[2], 7, 'Ref.', 1, 0, 'C', true);
            $this->Cell($widths[3], 7, 'Comprobante', 1, 0, 'C', true);
            $this->Cell($widths[4], 7, 'Concepto', 1, 0, 'C', true);
            $this->Cell($widths[5], 7, 'Entidad', 1, 0, 'C', true);
            $this->Cell($widths[6], 7, 'Ingresos', 1, 0, 'C', true);
            $this->Cell($widths[7], 7, 'Egresos', 1, 1, 'C', true);

            $this->SetTextColor(0, 0, 0);
            $this->SetDrawColor(220, 220, 220);
        }

        function Footer()
        {
            // Línea superior del footer
            $this->SetY(-18);
            $this->SetDrawColor(52, 152, 219);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
            
            // Información del footer
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 6);
            $this->SetTextColor(100, 100, 100);
            
            // Fecha de generación (izquierda)
            $this->Cell(100, 5, 'Generado: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
            
            // Página (centro)
            $this->Cell(0, 5, Utf8::decode('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
            
            // Sistema (derecha) - ajustar posición
            // $this->SetX(-60);
            // $this->Cell(50, 5, 'Sistema de Gestion', 0, 0, 'R');
            
            $this->SetTextColor(0, 0, 0);
        }

        // Función para ajustar texto
        function CellFit($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $scale = false, $force = true)
        {
            $str_width = $this->GetStringWidth($txt);

            if ($w == 0)
                $w = $this->w - $this->rMargin - $this->x;

            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;

            if ($str_width > $wmax) {
                $this->SetFont('', '', $this->FontSize * $wmax / $str_width);
                $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
                $this->SetFont('', '', $this->FontSize);
            } else {
                $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
            }
        }
    }

    $fuente = "Arial";
    $tamanio_linea = 6;

    // Creación del objeto PDF
    $pdf = new PDF($oficina, $institucion, $direccionins, $emailins, $telefonosins, $nitins, $rutalogomicro, $rutalogoins, $texto_reporte, $tipo_ingreso);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont($fuente, '', 8);
    $pdf->SetAutoPageBreak(true, 20);

    $total_debe = 0;
    $total_haber = 0;

    $widths = [5, 20, 20, 50, 100, 75, 25, 25];
    $currentIdTipoIngreso = null;
    $fill = false; // Para filas alternadas
    
    // Recorrer datos
    foreach ($datos as $key => $row) {
        $fecha = Date::toDmY($row['fecha']);
        $n_asiento = $row['n_asiento'];
        $comprobante = !empty($row['numero_dte']) ? $row['numero_serie'] . ' ' . $row['numero_dte'] : '';
        $concepto = $row['concepto'];
        $razon_social = $row['razon_social'];
        $debe = $row['debe_q'] ?? 0;
        $haber = $row['haber_q'] ?? 0;

        $total_debe += $debe;
        $total_haber += $haber;

        // Encabezado de grupo por tipo de movimiento
        if ($currentIdTipoIngreso !== $row['id_tipo_ingreso']) {
            if ($currentIdTipoIngreso !== null) {
                $pdf->Ln(3);
            }
            
            // Título del grupo con estilo
            $pdf->SetFont($fuente, 'B', 9);
            $pdf->SetFillColor(236, 240, 241);
            $pdf->SetTextColor(52, 73, 94);
            $pdf->Cell(array_sum($widths), 6, Utf8::decode('  TIPO DE MOVIMIENTO: ' . $row['detalle']), 1, 1, 'L', true);
            $pdf->SetFont($fuente, '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $currentIdTipoIngreso = $row['id_tipo_ingreso'];
            $fill = false; // Reiniciar alternancia
        }

        // Filas con color alternado para mejor legibilidad
        if ($fill) {
            $pdf->SetFillColor(250, 250, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        // Datos de la fila
        $pdf->Cell($widths[0], $tamanio_linea, $key + 1, 1, 0, 'C', true);
        $pdf->Cell($widths[1], $tamanio_linea, $fecha, 1, 0, 'C', true);
        $pdf->Cell($widths[2], $tamanio_linea, $n_asiento, 1, 0, 'C', true);
        $pdf->CellFit($widths[3], $tamanio_linea, Utf8::decode($comprobante), 1, 0, 'L', true, '', true, false);
        $pdf->CellFit($widths[4], $tamanio_linea, Utf8::decode($concepto), 1, 0, 'L', true, '', true, false);
        $pdf->CellFit($widths[5], $tamanio_linea, Utf8::decode($razon_social), 1, 0, 'L', true, '', true, false);
        
        // Resaltar montos con colores sutiles
        if ($debe > 0) {
            $pdf->SetTextColor(39, 174, 96); // Verde para ingresos
        }
        $pdf->Cell($widths[6], $tamanio_linea, $debe > 0 ? number_format($debe, 2) : '', 1, 0, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        
        if ($haber > 0) {
            $pdf->SetTextColor(231, 76, 60); // Rojo para egresos
        }
        $pdf->Cell($widths[7], $tamanio_linea, $haber > 0 ? number_format($haber, 2) : '', 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);

        $fill = !$fill; // Alternar color de fondo
    }

    // Espacio antes de totales
    $pdf->Ln(4);

    // Total acumulado con estilo destacado
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(array_sum(array_slice($widths, 0, 6)), 7, 'TOTAL ACUMULADO', 1, 0, 'R', true);
    
    $pdf->SetFillColor(39, 174, 96);
    $pdf->Cell($widths[6], 7, number_format($total_debe, 2), 1, 0, 'R', true);
    
    $pdf->SetFillColor(231, 76, 60);
    $pdf->Cell($widths[7], 7, number_format($total_haber, 2), 1, 1, 'R', true);

    // Resultado neto
    // $resultado = $total_debe - $total_haber;
    // $pdf->SetFillColor(44, 62, 80);
    // $pdf->Cell(array_sum(array_slice($widths, 0, 6)), 7, 'RESULTADO NETO', 1, 0, 'R', true);
    // $pdf->Cell(array_sum(array_slice($widths, 6, 2)), 7, number_format($resultado, 2), 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado exitosamente',
        'data' => "data:application/pdf;base64," . base64_encode($pdfData),
        'namefile' => 'reporte_consolidado_' . date('Y-m-d_His'),
        'tipo' => 'pdf'
    );
    echo json_encode($opResult);
}

//FUNCION PARA GENERAR EL REPORTE EN EXCEL
function printxls($datos, $texto_reporte, $info, $tipo_ingreso)
{
    $hoy = date("Y-m-d H:i:s");

    $spread = new Spreadsheet();
    $spread
        ->getProperties()
        ->setCreator("Sistema de Gestión")
        ->setLastModifiedBy("Sistema de Gestión")
        ->setTitle("Reporte Consolidado")
        ->setSubject("Reporte Consolidado de " . $tipo_ingreso)
        ->setDescription("Reporte consolidado generado el " . $hoy)
        ->setKeywords("reporte consolidado excel")
        ->setCategory('Excel');

    // Hoja de reporte
    $hojaReporte = $spread->getActiveSheet();
    $hojaReporte->setTitle("Consolidado");

    // Encabezado
    $hojaReporte->setCellValue("A1", $hoy);
    $hojaReporte->setCellValue("A2", $info[0]["nomb_comple"]);
    $hojaReporte->setCellValue("A3", $texto_reporte);

    // Estilo del encabezado
    $hojaReporte->getStyle("A1:H1")->getFont()->setSize(9)->setBold(true);
    $hojaReporte->getStyle("A2:H2")->getFont()->setSize(11)->setBold(true);
    $hojaReporte->getStyle("A3:H3")->getFont()->setSize(10)->setBold(true);
    $hojaReporte->getStyle("A1:H3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $hojaReporte->mergeCells('A1:H1');
    $hojaReporte->mergeCells('A2:H2');
    $hojaReporte->mergeCells('A3:H3');

    // Encabezados de columnas - Coincide con PDF
    $encabezado_tabla = ["#", "Fecha", "Ref.", "Comprobante", "Concepto", "Entidad", "Ingresos", "Egresos"];
    $hojaReporte->fromArray($encabezado_tabla, null, 'A5');

    $hojaReporte->getStyle('A5:H5')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '3498DB'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ]);

    // Datos
    $linea = 6;
    $total_debe = 0;
    $total_haber = 0;
    $currentIdTipoIngreso = null;
    $contador = 0;

    // Recorrer datos con agrupación por tipo de movimiento
    foreach ($datos as $key => $row) {
        $contador++;
        
        // Si cambia el tipo de ingreso, agregar encabezado de grupo
        if ($currentIdTipoIngreso !== $row['id_tipo_ingreso']) {
            // Agregar espacio entre grupos
            if ($currentIdTipoIngreso !== null) {
                $linea++;
            }
            
            // Título de la nueva sección
            $hojaReporte->setCellValue("A{$linea}", 'TIPO DE MOVIMIENTO: ' . $row['detalle']);
            $hojaReporte->mergeCells("A{$linea}:H{$linea}");
            $hojaReporte->getStyle("A{$linea}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 10,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8E8E8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ]);
            $linea++;
            
            $currentIdTipoIngreso = $row['id_tipo_ingreso'];
        }

        $comprobante = !empty($row['numero_dte']) ? $row['numero_serie'] . ' ' . $row['numero_dte'] : '';
        $debe = $row['debe_q'] ?? 0;
        $haber = $row['haber_q'] ?? 0;

        $hojaReporte->setCellValue("A{$linea}", $contador);
        $hojaReporte->setCellValue("B{$linea}", Date::toDmY($row['fecha']));
        $hojaReporte->setCellValue("C{$linea}", $row['n_asiento']);
        $hojaReporte->setCellValue("D{$linea}", $comprobante);
        $hojaReporte->setCellValue("E{$linea}", $row['concepto']);
        $hojaReporte->setCellValue("F{$linea}", $row['razon_social']);
        $hojaReporte->setCellValue("G{$linea}", $debe);
        $hojaReporte->setCellValue("H{$linea}", $haber);

        // Formato de números
        $hojaReporte->getStyle("G{$linea}:H{$linea}")->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // Bordes para la fila de datos
        $hojaReporte->getStyle("A{$linea}:H{$linea}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // Centrar columna #
        $hojaReporte->getStyle("A{$linea}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hojaReporte->getStyle("B{$linea}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hojaReporte->getStyle("C{$linea}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $total_debe += $debe;
        $total_haber += $haber;

        $linea++;
    }

    // Espacio antes del total
    $linea++;

    // Total acumulado
    $hojaReporte->setCellValue("F{$linea}", "TOTAL ACUMULADO");
    $hojaReporte->setCellValue("G{$linea}", $total_debe);
    $hojaReporte->setCellValue("H{$linea}", $total_haber);
    
    $hojaReporte->getStyle("F{$linea}:H{$linea}")->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 10,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9D9D9'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_RIGHT,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ]);
    
    $hojaReporte->getStyle("G{$linea}:H{$linea}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Agregar una fila para el resultado neto
    $linea++;
    $hojaReporte->setCellValue("F{$linea}", "RESULTADO NETO");
    $hojaReporte->setCellValue("H{$linea}", $total_debe - $total_haber);
    
    $hojaReporte->getStyle("F{$linea}:H{$linea}")->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 10,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'C0C0C0'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_RIGHT,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ]);
    
    $hojaReporte->getStyle("H{$linea}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Ajustar ancho de columnas
    $hojaReporte->getColumnDimension('A')->setWidth(5);
    $hojaReporte->getColumnDimension('B')->setWidth(12);
    $hojaReporte->getColumnDimension('C')->setWidth(10);
    $hojaReporte->getColumnDimension('D')->setWidth(20);
    $hojaReporte->getColumnDimension('E')->setWidth(35);
    $hojaReporte->getColumnDimension('F')->setWidth(30);
    $hojaReporte->getColumnDimension('G')->setWidth(15);
    $hojaReporte->getColumnDimension('H')->setWidth(15);

    // Generar archivo
    $writer = new Xlsx($spread);
    ob_start();
    $writer->save('php://output');
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado exitosamente',
        'data' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," . base64_encode($xlsData),
        'namefile' => 'reporte_consolidado_' . date('Y-m-d_His'),
        'tipo' => 'xlsx'
    );
    echo json_encode($opResult);
}
