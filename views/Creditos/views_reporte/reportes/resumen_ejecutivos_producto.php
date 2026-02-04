<?php
/*
 * REPORTE: RESUMEN DE CARTERAS POR EJECUTIVOS Y POR PRODUCTO
 * Versión: Estructura Mock (sin base de datos)
 * Propósito: Generar PDF y Excel con estructura visual estándar
 * Fecha: 2026-01-08
 */

session_start();

// Validación básica de sesión
if (!isset($_SESSION['id_agencia'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión no válida']);
    exit;
}

// Importar librerías necesarias
require_once("../../../../fpdf/fpdf.php");
require_once("../../../../vendor/autoload.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Función para decodificar UTF-8
function decode_utf8($cadena)
{
    return mb_convert_encoding($cadena, 'ISO-8859-1', 'UTF-8');
}

// ========== RECEPCIÓN DE DATOS ==========
$tipo = isset($_POST["tipo"]) ? $_POST["tipo"] : 'pdf';
$payload = isset($_POST["payload"]) ? $_POST["payload"] : [];

// Extraer datos del payload o usar defaults
$regional = isset($payload['regional']) ? $payload['regional'] : 'QUICHE-ALTA VERAPAZ';
$agencia = isset($payload['agencia']) ? $payload['agencia'] : 'San Cristobal Verapaz';
$fecha_corte = isset($payload['fecha_corte']) ? $payload['fecha_corte'] : date('d-M-y');
$usuario = isset($payload['usuario']) ? $payload['usuario'] : 'Usuario Demo';
$institucion = isset($payload['institucion']) ? $payload['institucion'] : 'COOPERATIVA DE AHORRO Y CRÉDITO MICROSYSTEM PLUS';
$direccion = isset($payload['direccion']) ? $payload['direccion'] : 'Guatemala, Guatemala';
$email = isset($payload['email']) ? $payload['email'] : 'info@microsystemplus.com';
$telefono = isset($payload['telefono']) ? $payload['telefono'] : '2222-2222   3333-3333';
$nit = isset($payload['nit']) ? $payload['nit'] : '12345678-9';
$rutalogoins = isset($payload['logo']) ? $payload['logo'] : '';

// ========== DATOS MOCK (ESTRUCTURA) ==========
$ejecutivos_mock = [
    [
        'id_ejecutivo' => 1,
        'nombre_ejecutivo' => 'Gregorio Ixim Pop',
        'productos' => [
            [
                'categoria_producto' => 'GRUPOS SOLIDARIOS',
                'monto_otorgado' => 0,
                'saldo' => 0,
                'clientes_principales' => 0,
                'clientes_paralelos' => 0,
                'vigente' => 0,
                'mora_1_30' => 0,
                'mora_31_60' => 0,
                'mora_61_90' => 0,
                'mora_91_180' => 0,
                'mora_mas_180' => 0
            ],
            [
                'categoria_producto' => 'CREDITOS INDIVIDUALES',
                'monto_otorgado' => 0,
                'saldo' => 0,
                'clientes_principales' => 0,
                'clientes_paralelos' => 0,
                'vigente' => 0,
                'mora_1_30' => 0,
                'mora_31_60' => 0,
                'mora_61_90' => 0,
                'mora_91_180' => 0,
                'mora_mas_180' => 0
            ]
        ]
    ],
    [
        'id_ejecutivo' => 2,
        'nombre_ejecutivo' => 'Maria Lopez Garcia',
        'productos' => [
            [
                'categoria_producto' => 'GRUPOS SOLIDARIOS',
                'monto_otorgado' => 0,
                'saldo' => 0,
                'clientes_principales' => 0,
                'clientes_paralelos' => 0,
                'vigente' => 0,
                'mora_1_30' => 0,
                'mora_31_60' => 0,
                'mora_61_90' => 0,
                'mora_91_180' => 0,
                'mora_mas_180' => 0
            ],
            [
                'categoria_producto' => 'CREDITOS INDIVIDUALES',
                'monto_otorgado' => 0,
                'saldo' => 0,
                'clientes_principales' => 0,
                'clientes_paralelos' => 0,
                'vigente' => 0,
                'mora_1_30' => 0,
                'mora_31_60' => 0,
                'mora_61_90' => 0,
                'mora_91_180' => 0,
                'mora_mas_180' => 0
            ]
        ]
    ],
    [
        'id_ejecutivo' => 3,
        'nombre_ejecutivo' => 'Juan Carlos Ramirez',
        'productos' => [
            [
                'categoria_producto' => 'GRUPOS SOLIDARIOS',
                'monto_otorgado' => 0,
                'saldo' => 0,
                'clientes_principales' => 0,
                'clientes_paralelos' => 0,
                'vigente' => 0,
                'mora_1_30' => 0,
                'mora_31_60' => 0,
                'mora_61_90' => 0,
                'mora_91_180' => 0,
                'mora_mas_180' => 0
            ],
            [
                'categoria_producto' => 'CREDITOS INDIVIDUALES',
                'monto_otorgado' => 0,
                'saldo' => 0,
                'clientes_principales' => 0,
                'clientes_paralelos' => 0,
                'vigente' => 0,
                'mora_1_30' => 0,
                'mora_31_60' => 0,
                'mora_61_90' => 0,
                'mora_91_180' => 0,
                'mora_mas_180' => 0
            ]
        ]
    ]
];

// Información institucional mock
$info_mock = [
    'institucion' => $institucion,
    'direccion' => $direccion,
    'email' => $email,
    'telefono' => $telefono,
    'nit' => $nit,
    'regional' => $regional,
    'agencia' => $agencia,
    'fecha_corte' => $fecha_corte,
    'logo' => $rutalogoins
];

// ========== ENRUTAMIENTO SEGÚN TIPO ==========
switch ($tipo) {
    case 'xlsx':
        printxls_estructura($ejecutivos_mock, $info_mock, $usuario);
        break;
    case 'pdf':
    default:
        printpdf_estructura($ejecutivos_mock, $info_mock, $usuario);
        break;
}

// ========== FUNCIÓN: GENERAR PDF ==========
function printpdf_estructura($ejecutivos, $info, $usuario)
{
    $institucion = decode_utf8($info['institucion']);
    $direccion = decode_utf8($info['direccion']);
    $email = $info['email'];
    $telefono = $info['telefono'];
    $nit = $info['nit'];
    $regional = decode_utf8($info['regional']);
    $agencia = decode_utf8($info['agencia']);
    $fechaCorte = $info['fecha_corte'];
    $rutalogoins = $info['logo'];

    // Clase PDF personalizada
    class PDF extends FPDF
    {
        public $institucion;
        public $pathlogoins;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $regional;
        public $agencia;
        public $fechaCorte;

        function __construct($institucion, $logo, $direccion, $email, $telefono, $nit, $regional, $agencia, $fechaCorte)
        {
            parent::__construct('L', 'mm', 'Legal'); // Horizontal, Legal
            $this->institucion = $institucion;
            $this->pathlogoins = $logo;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->regional = $regional;
            $this->agencia = $agencia;
            $this->fechaCorte = $fechaCorte;
        }

        function Header()
        {
            $fuente = "Courier";
            
            // Fecha/hora generación (esquina superior derecha)
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, date("Y-m-d H:i:s"), 0, 1, 'R');
            
            // Logo institucional (si existe)
            if (!empty($this->pathlogoins) && file_exists($this->pathlogoins)) {
                $this->Image($this->pathlogoins, 10, 13, 33);
            }
            
            // Encabezado institucional (centrado)
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
            $this->Ln(5);
            
            // TÍTULO DEL REPORTE
            $this->SetFont($fuente, 'B', 12);
            $this->Cell(0, 5, 'RESUMEN DE CARTERAS POR', 0, 1, 'C');
            $this->Cell(0, 5, 'EJECUTIVOS Y POR PRODUCTO', 0, 1, 'C');
            $this->Ln(3);
            
            // ENCABEZADO CONTEXTUAL (Regional | Agencia | Fecha)
            $this->SetFont($fuente, '', 9);
            $this->Cell(100, 4, 'Regional: ' . $this->regional, 0, 0, 'L');
            $this->Cell(100, 4, 'Agencia: ' . $this->agencia, 0, 0, 'C');
            $this->Cell(0, 4, 'Fecha: ' . $this->fechaCorte, 0, 1, 'R');
            $this->Ln(3);
            
            // ENCABEZADO DE COLUMNAS
            $this->SetFont($fuente, 'B', 7);
            $this->SetFillColor(200, 200, 200);
            $w = array(45, 20, 18, 12, 12, 18, 16, 16, 16, 16, 16);
            $this->Cell($w[0], 5, 'PRODUCTO', 1, 0, 'C', true);
            $this->Cell($w[1], 5, 'MONTO', 1, 0, 'C', true);
            $this->Cell($w[2], 5, 'SALDO', 1, 0, 'C', true);
            $this->Cell($w[3], 5, 'CLI.P', 1, 0, 'C', true);
            $this->Cell($w[4], 5, 'CLI.PAR', 1, 0, 'C', true);
            $this->Cell($w[5], 5, 'VIGENTE', 1, 0, 'C', true);
            $this->Cell($w[6], 5, '1-30', 1, 0, 'C', true);
            $this->Cell($w[7], 5, '31-60', 1, 0, 'C', true);
            $this->Cell($w[8], 5, '61-90', 1, 0, 'C', true);
            $this->Cell($w[9], 5, '91-180', 1, 0, 'C', true);
            $this->Cell($w[10], 5, '>180', 1, 1, 'C', true);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Courier', 'I', 7);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Instanciar PDF
    $pdf = new PDF($institucion, $rutalogoins, $direccion, $email, $telefono, $nit, $regional, $agencia, $fechaCorte);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $fuente = "Courier";
    $pdf->SetFont($fuente, '', 7);
    $w = array(45, 20, 18, 12, 12, 18, 16, 16, 16, 16, 16);

    // Variables para totales
    $totalAgencia = array(
        'monto' => 0, 'saldo' => 0, 'clientes_principales' => 0, 'clientes_paralelos' => 0,
        'vigente' => 0, 'mora_1_30' => 0, 'mora_31_60' => 0, 'mora_61_90' => 0,
        'mora_91_180' => 0, 'mora_mas_180' => 0
    );

    // Iterar ejecutivos
    $numeroEjecutivo = 0;
    foreach ($ejecutivos as $ejecutivo) {
        $numeroEjecutivo++;
        
        // Subtotal por ejecutivo
        $subtotal = array(
            'monto' => 0, 'saldo' => 0, 'clientes_principales' => 0, 'clientes_paralelos' => 0,
            'vigente' => 0, 'mora_1_30' => 0, 'mora_31_60' => 0, 'mora_61_90' => 0,
            'mora_91_180' => 0, 'mora_mas_180' => 0
        );

        // ENCABEZADO DE EJECUTIVO
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->Cell(0, 5, $numeroEjecutivo . '. EJECUTIVO: ' . strtoupper(decode_utf8($ejecutivo['nombre_ejecutivo'])), 0, 1, 'L');
        $pdf->SetFont($fuente, '', 7);

        // Iterar productos del ejecutivo
        foreach ($ejecutivo['productos'] as $producto) {
            // Fila de producto
            $pdf->Cell($w[0], 4, substr(decode_utf8($producto['categoria_producto']), 0, 30), 1, 0, 'L');
            $pdf->Cell($w[1], 4, number_format($producto['monto_otorgado'], 0), 1, 0, 'R');
            $pdf->Cell($w[2], 4, number_format($producto['saldo'], 0), 1, 0, 'R');
            $pdf->Cell($w[3], 4, $producto['clientes_principales'], 1, 0, 'C');
            $pdf->Cell($w[4], 4, $producto['clientes_paralelos'], 1, 0, 'C');
            $pdf->Cell($w[5], 4, number_format($producto['vigente'], 0), 1, 0, 'R');
            $pdf->Cell($w[6], 4, number_format($producto['mora_1_30'], 0), 1, 0, 'R');
            $pdf->Cell($w[7], 4, number_format($producto['mora_31_60'], 0), 1, 0, 'R');
            $pdf->Cell($w[8], 4, number_format($producto['mora_61_90'], 0), 1, 0, 'R');
            $pdf->Cell($w[9], 4, number_format($producto['mora_91_180'], 0), 1, 0, 'R');
            $pdf->Cell($w[10], 4, number_format($producto['mora_mas_180'], 0), 1, 1, 'R');

            // Acumular subtotal
            $subtotal['monto'] += $producto['monto_otorgado'];
            $subtotal['saldo'] += $producto['saldo'];
            $subtotal['clientes_principales'] += $producto['clientes_principales'];
            $subtotal['clientes_paralelos'] += $producto['clientes_paralelos'];
            $subtotal['vigente'] += $producto['vigente'];
            $subtotal['mora_1_30'] += $producto['mora_1_30'];
            $subtotal['mora_31_60'] += $producto['mora_31_60'];
            $subtotal['mora_61_90'] += $producto['mora_61_90'];
            $subtotal['mora_91_180'] += $producto['mora_91_180'];
            $subtotal['mora_mas_180'] += $producto['mora_mas_180'];
        }

        // SUBTOTAL EJECUTIVO (fondo azul)
        $pdf->SetFillColor(52, 152, 219); // Azul
        $pdf->SetTextColor(255, 255, 255); // Blanco
        $pdf->SetFont($fuente, 'B', 8);

        $pdf->Cell($w[0], 5, 'SUBTOTAL', 1, 0, 'L', true);
        $pdf->Cell($w[1], 5, number_format($subtotal['monto'], 0), 1, 0, 'R', true);
        $pdf->Cell($w[2], 5, number_format($subtotal['saldo'], 0), 1, 0, 'R', true);
        $pdf->Cell($w[3], 5, $subtotal['clientes_principales'], 1, 0, 'C', true);
        $pdf->Cell($w[4], 5, $subtotal['clientes_paralelos'], 1, 0, 'C', true);
        $pdf->Cell($w[5], 5, number_format($subtotal['vigente'], 0), 1, 0, 'R', true);
        $pdf->Cell($w[6], 5, number_format($subtotal['mora_1_30'], 0), 1, 0, 'R', true);
        $pdf->Cell($w[7], 5, number_format($subtotal['mora_31_60'], 0), 1, 0, 'R', true);
        $pdf->Cell($w[8], 5, number_format($subtotal['mora_61_90'], 0), 1, 0, 'R', true);
        $pdf->Cell($w[9], 5, number_format($subtotal['mora_91_180'], 0), 1, 0, 'R', true);
        $pdf->Cell($w[10], 5, number_format($subtotal['mora_mas_180'], 0), 1, 1, 'R', true);

        // CARTERA EN RIESGO (>30 días)
        $carteraRiesgo = $subtotal['mora_31_60'] + $subtotal['mora_61_90'] + $subtotal['mora_91_180'] + $subtotal['mora_mas_180'];
        $porcentaje = ($subtotal['saldo'] > 0) ? ($carteraRiesgo / $subtotal['saldo'] * 100) : 0;

        $pdf->Cell(array_sum(array_slice($w, 0, 5)), 5, 'CARTERA EN RIESGO (>30 dias)', 1, 0, 'R', true);
        $pdf->Cell(array_sum(array_slice($w, 5)), 5, number_format($carteraRiesgo, 0) . ' (' . number_format($porcentaje, 2) . '%)', 1, 1, 'R', true);

        $pdf->SetTextColor(0, 0, 0); // Restaurar a negro
        $pdf->SetFont($fuente, '', 7);
        $pdf->Ln(3);

        // Acumular total agencia
        $totalAgencia['monto'] += $subtotal['monto'];
        $totalAgencia['saldo'] += $subtotal['saldo'];
        $totalAgencia['clientes_principales'] += $subtotal['clientes_principales'];
        $totalAgencia['clientes_paralelos'] += $subtotal['clientes_paralelos'];
        $totalAgencia['vigente'] += $subtotal['vigente'];
        $totalAgencia['mora_1_30'] += $subtotal['mora_1_30'];
        $totalAgencia['mora_31_60'] += $subtotal['mora_31_60'];
        $totalAgencia['mora_61_90'] += $subtotal['mora_61_90'];
        $totalAgencia['mora_91_180'] += $subtotal['mora_91_180'];
        $totalAgencia['mora_mas_180'] += $subtotal['mora_mas_180'];
    }

    // TOTAL AGENCIA (fondo gris)
    $pdf->Ln(5);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->SetFillColor(100, 100, 100); // Gris oscuro
    $pdf->SetTextColor(255, 255, 255); // Blanco
    $pdf->Cell(0, 6, 'TOTAL AGENCIA', 1, 1, 'C', true);

    $pdf->SetFont($fuente, 'B', 8);
    $pdf->Cell($w[0], 5, 'TOTALES', 1, 0, 'M', true);
    $pdf->Cell($w[1], 5, number_format($totalAgencia['monto'], 0), 1, 0, 'R', true);
    $pdf->Cell($w[2], 5, number_format($totalAgencia['saldo'], 0), 1, 0, 'R', true);
    $pdf->Cell($w[3], 5, $totalAgencia['clientes_principales'], 1, 0, 'C', true);
    $pdf->Cell($w[4], 5, $totalAgencia['clientes_paralelos'], 1, 0, 'C', true);
    $pdf->Cell($w[5], 5, number_format($totalAgencia['vigente'], 0), 1, 0, 'R', true);
    $pdf->Cell($w[6], 5, number_format($totalAgencia['mora_1_30'], 0), 1, 0, 'R', true);
    $pdf->Cell($w[7], 5, number_format($totalAgencia['mora_31_60'], 0), 1, 0, 'R', true);
    $pdf->Cell($w[8], 5, number_format($totalAgencia['mora_61_90'], 0), 1, 0, 'R', true);
    $pdf->Cell($w[9], 5, number_format($totalAgencia['mora_91_180'], 0), 1, 0, 'R', true);
    $pdf->Cell($w[10], 5, number_format($totalAgencia['mora_mas_180'], 0), 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);

    // Generar PDF
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    // Respuesta JSON
    header('Content-Type: application/json');
    $opResult = array(
        'status' => 1,
        'data' => "data:application/pdf;base64," . base64_encode($pdfData),
        'mensaje' => 'Reporte generado exitosamente',
        'tipo' => 'pdf',
        'namefile' => 'Resumen_Carteras_Ejecutivos_Producto_' . date('Ymd_His')
    );
    echo json_encode($opResult);
}

// ========== FUNCIÓN: GENERAR EXCEL ==========
function printxls_estructura($ejecutivos, $info, $usuario)
{
    $hoy = date("Y-m-d H:i:s");
    $fuente_encabezado = "Arial";
    $fuente = "Courier";

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Resumen Ejecutivos");

    // Configurar anchos de columna
    $activa->getColumnDimension("A")->setWidth(35);
    $activa->getColumnDimension("B")->setWidth(18);
    $activa->getColumnDimension("C")->setWidth(18);
    $activa->getColumnDimension("D")->setWidth(12);
    $activa->getColumnDimension("E")->setWidth(12);
    $activa->getColumnDimension("F")->setWidth(18);
    $activa->getColumnDimension("G")->setWidth(15);
    $activa->getColumnDimension("H")->setWidth(15);
    $activa->getColumnDimension("I")->setWidth(15);
    $activa->getColumnDimension("J")->setWidth(15);
    $activa->getColumnDimension("K")->setWidth(15);

    // Fila 1: Fecha/hora generación
    $activa->setCellValue("A1", $hoy);
    $activa->mergeCells('A1:K1');
    $activa->getStyle("A1")->getFont()->setSize(9)->setName($fuente_encabezado);
    $activa->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Fila 2: Usuario
    $activa->setCellValue("A2", $usuario);
    $activa->mergeCells('A2:K2');
    $activa->getStyle("A2")->getFont()->setSize(9)->setName($fuente_encabezado);
    $activa->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Fila 4: Título principal
    $activa->setCellValue("A4", "RESUMEN DE CARTERAS POR EJECUTIVOS Y POR PRODUCTO");
    $activa->mergeCells('A4:K4');
    $activa->getStyle("A4")->getFont()->setSize(12)->setName($fuente)->setBold(true);
    $activa->getStyle("A4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Fila 5: Encabezado contextual
    $contexto = "Regional: {$info['regional']}     Agencia: {$info['agencia']}     Fecha: {$info['fecha_corte']}";
    $activa->setCellValue("A5", $contexto);
    $activa->mergeCells('A5:K5');
    $activa->getStyle("A5")->getFont()->setSize(10)->setName($fuente)->setBold(true);
    $activa->getStyle("A5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Fila 7: Encabezado de columnas
    $encabezados = [
        "PRODUCTO", "MONTO", "SALDO", "CLI.P", "CLI.PAR", "VIGENTE",
        "1-30", "31-60", "61-90", "91-180", ">180"
    ];
    $activa->fromArray($encabezados, null, 'A7');
    $activa->getStyle('A7:K7')->getFont()->setName($fuente)->setBold(true)->setSize(10);
    $activa->getStyle('A7:K7')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9D9D9'); // Gris claro
    $activa->getStyle('A7:K7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $activa->getStyle('A7:K7')->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    // Variables para totales
    $totalAgencia = array(
        'monto' => 0, 'saldo' => 0, 'clientes_principales' => 0, 'clientes_paralelos' => 0,
        'vigente' => 0, 'mora_1_30' => 0, 'mora_31_60' => 0, 'mora_61_90' => 0,
        'mora_91_180' => 0, 'mora_mas_180' => 0
    );

    $fila = 8;
    $numeroEjecutivo = 0;

    // Iterar ejecutivos
    foreach ($ejecutivos as $ejecutivo) {
        $numeroEjecutivo++;
        
        // Subtotal por ejecutivo
        $subtotal = array(
            'monto' => 0, 'saldo' => 0, 'clientes_principales' => 0, 'clientes_paralelos' => 0,
            'vigente' => 0, 'mora_1_30' => 0, 'mora_31_60' => 0, 'mora_61_90' => 0,
            'mora_91_180' => 0, 'mora_mas_180' => 0
        );

        // ENCABEZADO DE EJECUTIVO (fila completa)
        $activa->setCellValue("A{$fila}", $numeroEjecutivo . '. EJECUTIVO: ' . strtoupper($ejecutivo['nombre_ejecutivo']));
        $activa->mergeCells("A{$fila}:K{$fila}");
        $activa->getStyle("A{$fila}")->getFont()->setName($fuente)->setBold(true)->setSize(10);
        $activa->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $activa->getStyle("A{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8E8E8'); // Gris muy claro
        $fila++;

        // Productos del ejecutivo
        foreach ($ejecutivo['productos'] as $producto) {
            $activa->setCellValue("A{$fila}", $producto['categoria_producto']);
            $activa->setCellValue("B{$fila}", $producto['monto_otorgado']);
            $activa->setCellValue("C{$fila}", $producto['saldo']);
            $activa->setCellValue("D{$fila}", $producto['clientes_principales']);
            $activa->setCellValue("E{$fila}", $producto['clientes_paralelos']);
            $activa->setCellValue("F{$fila}", $producto['vigente']);
            $activa->setCellValue("G{$fila}", $producto['mora_1_30']);
            $activa->setCellValue("H{$fila}", $producto['mora_31_60']);
            $activa->setCellValue("I{$fila}", $producto['mora_61_90']);
            $activa->setCellValue("J{$fila}", $producto['mora_91_180']);
            $activa->setCellValue("K{$fila}", $producto['mora_mas_180']);

            // Formato numérico
            $activa->getStyle("B{$fila}:K{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
            $activa->getStyle("A{$fila}:K{$fila}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            // Acumular subtotal
            $subtotal['monto'] += $producto['monto_otorgado'];
            $subtotal['saldo'] += $producto['saldo'];
            $subtotal['clientes_principales'] += $producto['clientes_principales'];
            $subtotal['clientes_paralelos'] += $producto['clientes_paralelos'];
            $subtotal['vigente'] += $producto['vigente'];
            $subtotal['mora_1_30'] += $producto['mora_1_30'];
            $subtotal['mora_31_60'] += $producto['mora_31_60'];
            $subtotal['mora_61_90'] += $producto['mora_61_90'];
            $subtotal['mora_91_180'] += $producto['mora_91_180'];
            $subtotal['mora_mas_180'] += $producto['mora_mas_180'];

            $fila++;
        }

        // SUBTOTAL EJECUTIVO (fondo azul)
        $activa->setCellValue("A{$fila}", "SUBTOTAL");
        $activa->setCellValue("B{$fila}", $subtotal['monto']);
        $activa->setCellValue("C{$fila}", $subtotal['saldo']);
        $activa->setCellValue("D{$fila}", $subtotal['clientes_principales']);
        $activa->setCellValue("E{$fila}", $subtotal['clientes_paralelos']);
        $activa->setCellValue("F{$fila}", $subtotal['vigente']);
        $activa->setCellValue("G{$fila}", $subtotal['mora_1_30']);
        $activa->setCellValue("H{$fila}", $subtotal['mora_31_60']);
        $activa->setCellValue("I{$fila}", $subtotal['mora_61_90']);
        $activa->setCellValue("J{$fila}", $subtotal['mora_91_180']);
        $activa->setCellValue("K{$fila}", $subtotal['mora_mas_180']);

        $activa->getStyle("A{$fila}:K{$fila}")->getFont()->setBold(true);
        $activa->getStyle("A{$fila}:K{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF3498DB'); // Azul
        $activa->getStyle("A{$fila}:K{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF'); // Blanco
        $activa->getStyle("B{$fila}:K{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
        $activa->getStyle("A{$fila}:K{$fila}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $fila++;

        // CARTERA EN RIESGO (>30 días)
        $carteraRiesgo = $subtotal['mora_31_60'] + $subtotal['mora_61_90'] + $subtotal['mora_91_180'] + $subtotal['mora_mas_180'];
        
        $activa->setCellValue("A{$fila}", "CARTERA EN RIESGO (>30 días)");
        $activa->mergeCells("A{$fila}:E{$fila}");
        $activa->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Fórmula con IFERROR para evitar #DIV/0!
        $filaAnterior = $fila - 1;
        $activa->setCellValue("F{$fila}", "=IFERROR(SUM(H{$filaAnterior}:K{$filaAnterior})/C{$filaAnterior},0)");
        $activa->mergeCells("F{$fila}:K{$fila}");
        $activa->getStyle("F{$fila}")->getNumberFormat()->setFormatCode('0.00%');
        
        $activa->getStyle("A{$fila}:K{$fila}")->getFont()->setBold(true);
        $activa->getStyle("A{$fila}:K{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF3498DB'); // Azul
        $activa->getStyle("A{$fila}:K{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF'); // Blanco
        $activa->getStyle("A{$fila}:K{$fila}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        $fila++;
        $fila++; // Espacio entre ejecutivos

        // Acumular total agencia
        $totalAgencia['monto'] += $subtotal['monto'];
        $totalAgencia['saldo'] += $subtotal['saldo'];
        $totalAgencia['clientes_principales'] += $subtotal['clientes_principales'];
        $totalAgencia['clientes_paralelos'] += $subtotal['clientes_paralelos'];
        $totalAgencia['vigente'] += $subtotal['vigente'];
        $totalAgencia['mora_1_30'] += $subtotal['mora_1_30'];
        $totalAgencia['mora_31_60'] += $subtotal['mora_31_60'];
        $totalAgencia['mora_61_90'] += $subtotal['mora_61_90'];
        $totalAgencia['mora_91_180'] += $subtotal['mora_91_180'];
        $totalAgencia['mora_mas_180'] += $subtotal['mora_mas_180'];
    }

    // TOTAL AGENCIA (fondo gris oscuro)
    $fila++;
    $activa->setCellValue("A{$fila}", "TOTAL AGENCIA");
    $activa->mergeCells("A{$fila}:K{$fila}");
    $activa->getStyle("A{$fila}")->getFont()->setName($fuente)->setBold(true)->setSize(11);
    $activa->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A{$fila}")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF646464'); // Gris oscuro
    $activa->getStyle("A{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF'); // Blanco
    $fila++;

    $activa->setCellValue("A{$fila}", "TOTALES");
    $activa->setCellValue("B{$fila}", $totalAgencia['monto']);
    $activa->setCellValue("C{$fila}", $totalAgencia['saldo']);
    $activa->setCellValue("D{$fila}", $totalAgencia['clientes_principales']);
    $activa->setCellValue("E{$fila}", $totalAgencia['clientes_paralelos']);
    $activa->setCellValue("F{$fila}", $totalAgencia['vigente']);
    $activa->setCellValue("G{$fila}", $totalAgencia['mora_1_30']);
    $activa->setCellValue("H{$fila}", $totalAgencia['mora_31_60']);
    $activa->setCellValue("I{$fila}", $totalAgencia['mora_61_90']);
    $activa->setCellValue("J{$fila}", $totalAgencia['mora_91_180']);
    $activa->setCellValue("K{$fila}", $totalAgencia['mora_mas_180']);

    $activa->getStyle("A{$fila}:K{$fila}")->getFont()->setBold(true);
    $activa->getStyle("A{$fila}:K{$fila}")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF646464'); // Gris oscuro
    $activa->getStyle("A{$fila}:K{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF'); // Blanco
    $activa->getStyle("B{$fila}:K{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
    $activa->getStyle("A{$fila}:K{$fila}")->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    // Generar archivo Excel
    ob_start();
    $writer = new Xlsx($excel);
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    // Respuesta JSON
    header('Content-Type: application/json');
    $opResult = array(
        'status' => 1,
        'data' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," . base64_encode($xlsData),
        'mensaje' => 'Reporte generado exitosamente',
        'tipo' => 'xlsx',
        'namefile' => 'Resumen_Carteras_Ejecutivos_Producto_' . date('Ymd_His')
    );
    echo json_encode($opResult);
}
