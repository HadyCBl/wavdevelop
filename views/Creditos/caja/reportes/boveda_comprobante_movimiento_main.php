<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];
// include __DIR__ . '/../../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../../fpdf/fpdf.php';
require __DIR__ . '/../../../../vendor/autoload.php';

use App\DatabaseAdapter;
use App\Generic\Agencia;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$usuario = $_SESSION["id"];
$idagencia = $_SESSION["id_agencia"];

$idMovimiento = $_POST["datosval"][3][0];
$tipo = $_POST["tipo"];

$database = new DatabaseAdapter();

$showmensaje = false;
try {

    $nombreCompletoInstitucion = (new Agencia($idagencia))->institucion?->getNombreCompletoInstitucion();

    $database->openConnection();

    $datosEncabezado = $database->getAllResults(
        "SELECT bov.nombre,bov.id_agencia, mov.tipo,mov.monto,mov.fecha, mov.concepto, mov.numdoc,mov.forma, 
            IFNULL(ctb.numcuenta,'-') numcuenta, ban.nombre nombreBanco, mov.banco_numdoc, mov.banco_fecha
            FROM bov_movimientos mov
            INNER JOIN bov_bovedas bov ON bov.id=mov.id_boveda
            LEFT JOIN ctb_bancos ctb ON ctb.id=mov.id_cuentabanco
            LEFT JOIN tb_bancos ban ON ban.id=ctb.id_banco
            WHERE mov.id=?;",
        [$idMovimiento]
    );

    if (empty($datosEncabezado)) {
        $showmensaje = true;
        throw new Exception('No se encontraron datos del movimiento');
        // echo json_encode(['status' => 0, 'mensaje' => 'No se encontraron datos del movimiento']);
        // return;
    }

    $datosDenominaciones = $database->getAllResults(
        "SELECT den.monto,den.tipo,det.cantidad FROM bov_detalles det
            INNER JOIN tb_denominaciones den ON den.id=det.id_denominacion
            WHERE id_movimiento=?;",
        [$idMovimiento]
    );

    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

switch ($tipo) {
    case 'pdf':
        printpdf($datosEncabezado, $datosDenominaciones, $nombreCompletoInstitucion);
        break;
    case 'show':
        //showresults($data);
        break;
}

function printpdf($datosEncabezado, $datosDenominaciones, $nombreInstitucion)
{

    $datos = $datosEncabezado[0];

    // Crear instancia de PDF
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetMargins(15, 15, 15);

    // ========== ENCABEZADO ==========
    // Cuadro decorativo para logo
    $pdf->SetFillColor(41, 128, 185); // Azul profesional
    $pdf->Rect(15, 15, 12, 12, 'F');

    // Iniciales en blanco
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(15, 18);
    $pdf->Cell(12, 6, 'BV', 0, 0, 'C');

    // Nombre de la institución
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(41, 128, 185);
    $pdf->SetXY(32, 15);
    $pdf->Cell(0, 7, Utf8::decode($nombreInstitucion), 0, 1, 'L');

    // Subtítulo
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetX(32);
    $pdf->Cell(0, 5, 'Sistema de Gestion de Bovedas', 0, 1, 'L');

    // Línea decorativa
    $pdf->SetDrawColor(41, 128, 185);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, 32, 195, 32);

    // ========== TÍTULO DEL DOCUMENTO ==========
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(44, 62, 80);
    $tipoMovimiento = strtoupper($datos['tipo']);
    $pdf->Cell(0, 10, Utf8::decode("COMPROBANTE DE {$tipoMovimiento}"), 0, 1, 'C');

    // Número de documento
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'No. Documento: ' . str_pad($datos['numdoc'], 8, '0', STR_PAD_LEFT), 0, 1, 'C');

    $pdf->Ln(3);

    // ========== INFORMACIÓN PRINCIPAL ==========
    // Caja con fondo gris para información principal
    $pdf->SetFillColor(236, 240, 241);
    $pdf->Rect(15, $pdf->GetY(), 180, 20, 'F');

    $yStart = $pdf->GetY() + 3;

    // Columna izquierda
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->SetXY(20, $yStart);
    $pdf->Cell(50, 4, 'Boveda:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 4, Utf8::decode($datos['nombre']), 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->SetX(20);
    $pdf->Cell(50, 4, 'Fecha:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 4, date('d/m/Y', strtotime($datos['fecha'])), 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->SetX(20);
    $pdf->Cell(50, 4, 'Forma de Pago:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 4, Utf8::decode(strtoupper($datos['forma'])), 0, 1, 'L');

    if (in_array(strtolower($datos['forma']), ['banco'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->SetX(20);
        $pdf->Cell(50, 4, 'Banco:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 4, Utf8::decode($datos['nombreBanco']) . ' - ' . $datos['numcuenta'], 0, 1, 'L');
    }

    $pdf->Ln(3);

    // ========== MONTO DESTACADO ==========
    $pdf->SetFillColor(46, 204, 113); // Verde
    if ($datos['tipo'] === 'salida') {
        $pdf->SetFillColor(231, 76, 60); // Rojo
    }

    $pdf->Rect(15, $pdf->GetY(), 180, 12, 'F');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(255, 255, 255);
    $yMonto = $pdf->GetY() + 3;
    $pdf->SetXY(20, $yMonto);
    $pdf->Cell(80, 5, 'MONTO TOTAL:', 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetX(20);
    $montoFormateado = Moneda::formato($datos['monto']);
    $pdf->Cell(160, 6, $montoFormateado, 0, 1, 'R');

    $pdf->Ln(4);

    // ========== CONCEPTO ==========
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(0, 4, 'Concepto:', 0, 1, 'L');

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->MultiCell(0, 5, Utf8::decode($datos['concepto']), 0, 'L');

    $pdf->Ln(5);

    // ========== DENOMINACIONES ==========
    if (!empty($datosDenominaciones)) {
        // Título de sección
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 8, Utf8::decode('DETALLE DE DENOMINACIONES'), 0, 1, 'L');

        // Encabezado de tabla
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);

        $pdf->Cell(40, 8, 'TIPO', 1, 0, 'C', true);
        $pdf->Cell(50, 8, Utf8::decode('DENOMINACION'), 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'CANTIDAD', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'SUBTOTAL', 1, 1, 'C', true);

        // Agrupar por tipo
        $billetes = [];
        $monedas = [];

        foreach ($datosDenominaciones as $denom) {
            if ($denom['tipo'] === '1') {
                $billetes[] = $denom;
            } else {
                $monedas[] = $denom;
            }
        }

        // Ordenar de mayor a menor
        usort($billetes, function ($a, $b) {
            return $b['monto'] <=> $a['monto'];
        });
        usort($monedas, function ($a, $b) {
            return $b['monto'] <=> $a['monto'];
        });

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(44, 62, 80);

        $totalBilletes = 0;
        $totalMonedas = 0;

        // Imprimir billetes
        foreach ($billetes as $item) {
            $subtotal = $item['monto'] * $item['cantidad'];
            $totalBilletes += $subtotal;

            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell(40, 5, 'BILLETE', 1, 0, 'C', true);
            $pdf->Cell(50, 5, Moneda::formato($item['monto']), 1, 0, 'R', true);
            $pdf->Cell(40, 5, number_format($item['cantidad'], 0, '', ','), 1, 0, 'C', true);
            $pdf->Cell(50, 5, Moneda::formato($subtotal), 1, 1, 'R', true);
        }

        // Imprimir monedas
        foreach ($monedas as $item) {
            $subtotal = $item['monto'] * $item['cantidad'];
            $totalMonedas += $subtotal;

            $pdf->SetFillColor(250, 250, 250);
            $pdf->Cell(40, 5, 'MONEDA', 1, 0, 'C', true);
            $pdf->Cell(50, 5, Moneda::formato($item['monto']), 1, 0, 'R', true);
            $pdf->Cell(40, 5, number_format($item['cantidad'], 0, '', ','), 1, 0, 'C', true);
            $pdf->Cell(50, 5, Moneda::formato($subtotal), 1, 1, 'R', true);
        }

        // Subtotales
        if ($totalBilletes > 0) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(189, 195, 199);
            $pdf->Cell(130, 5, 'TOTAL BILLETES:', 1, 0, 'R', true);
            $pdf->Cell(50, 5, Moneda::formato($totalBilletes), 1, 1, 'R', true);
        }

        if ($totalMonedas > 0) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(189, 195, 199);
            $pdf->Cell(130, 5, 'TOTAL MONEDAS:', 1, 0, 'R', true);
            $pdf->Cell(50, 5, Moneda::formato($totalMonedas), 1, 1, 'R', true);
        }

        // Total general
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(130, 6, 'TOTAL GENERAL:', 1, 0, 'R', true);
        $pdf->Cell(50, 6, Moneda::formato($totalBilletes + $totalMonedas), 1, 1, 'R', true);
    }

    // ========== PIE DE PÁGINA ==========
    $pdf->SetY(-55);

    // Líneas de firma
    $pdf->Ln(7);
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->Line(30, $pdf->GetY(), 90, $pdf->GetY());
    $pdf->Line(120, $pdf->GetY(), 180, $pdf->GetY());

    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(90, 5, 'Receptor Pagador', 0, 0, 'C');
    $pdf->Cell(90, 5, 'Gerencia', 0, 1, 'C');

    // Información del sistema
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 4, 'Documento generado el ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Cell(0, 4, Utf8::decode('Este es un documento oficial del sistema de gestión de bóvedas'), 0, 1, 'C');

    // Salida del PDF
    // $pdf->Output('I', 'Comprobante_Movimiento_' . str_pad($datos['numdoc'], 8, '0', STR_PAD_LEFT) . '.pdf');

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Comprobante_Movimiento_" . str_pad($datos['numdoc'], 8, '0', STR_PAD_LEFT) . ".pdf",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
