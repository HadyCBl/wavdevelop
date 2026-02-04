<?php

include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

require_once __DIR__ . '/../../../../includes/Config/database.php';

use App\DatabaseAdapter;
use App\Generic\Agencia;
use Micro\Helpers\Log;
use Micro\Generic\Auth as SessionManager;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;

if (!SessionManager::has('id_agencia')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
    return;
}

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = SessionManager::get('id');
$idagencia = SessionManager::get('id_agencia');

$idCompra = $_POST["datosval"][3][0];
$tipo = $_POST["tipo"];

$database = new DatabaseAdapter();

$showmensaje = false;
try {

    $nombreCompletoInstitucion = (new Agencia($idagencia))->institucion?->getNombreCompletoInstitucion();

    $database->openConnection();

    // Obtener datos de la compra
    $datosCompra = $database->getAllResults(
        "SELECT 
            com.id, com.numdoc, com.fecha, com.forma_pago, com.concepto, com.estado,
            com.doc_banco,
            cli.short_name AS cliente_nombre, cli.no_identifica AS cliente_identificacion,
            IFNULL(ban.nombre, '') AS banco_nombre, IFNULL(ctb.numcuenta, '') AS banco_cuenta
        FROM cc_compras com
        INNER JOIN tb_cliente cli ON cli.idcod_cliente = com.id_cliente
        LEFT JOIN ctb_bancos ctb ON ctb.id = com.id_ctbbancos
        LEFT JOIN tb_bancos ban ON ban.id = ctb.id_banco
        WHERE com.id = ?",
        [$idCompra]
    );

    if (empty($datosCompra)) {
        $showmensaje = true;
        throw new Exception('No se encontraron datos de la compra');
    }

    // Obtener productos de la compra
    $productos = $database->getAllResults(
        "SELECT 
            det.cantidad, det.precio_unitario, det.medida, det.descripcion,
            prod.nombre AS producto_nombre
        FROM cc_compras_detalle det
        INNER JOIN cc_productos prod ON prod.id = det.id_producto
        WHERE det.id_compra = ?
        ORDER BY prod.nombre ASC",
        [$idCompra]
    );

    // Obtener descuentos de la compra
    $descuentos = $database->getAllResults(
        "SELECT 
            desc_det.monto, desc_det.observaciones,
            ds.nombre AS descuento_nombre, ds.categoria
        FROM cc_compras_descuentos desc_det
        INNER JOIN cc_descuentos ds ON ds.id = desc_det.id_descuento
        WHERE desc_det.id_compra = ?
        ORDER BY ds.nombre ASC",
        [$idCompra]
    );

    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
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
        printpdf($datosCompra, $productos, $descuentos, $nombreCompletoInstitucion);
        break;
    case 'show':
        // Mostrar en ventana
        break;
}

function printpdf($datosCompra, $productos, $descuentos, $nombreInstitucion)
{
    require __DIR__ . '/../../../../fpdf/fpdf.php';

    $datos = $datosCompra[0];

    // Calcular totales
    $totalProductos = 0;
    foreach ($productos as $prod) {
        $totalProductos += ($prod['cantidad'] * $prod['precio_unitario']);
    }

    $totalDescuentos = 0;
    foreach ($descuentos as $desc) {
        $totalDescuentos += $desc['monto'];
    }

    $totalLiquido = $totalProductos - $totalDescuentos;

    // Crear instancia de PDF
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);

    // ========== ENCABEZADO ==========
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(41, 128, 185);
    $pdf->Cell(0, 10, Utf8::decode($nombreInstitucion), 0, 1, 'C');

    // Línea decorativa
    $pdf->SetDrawColor(41, 128, 185);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(20, $pdf->GetY(), 195, $pdf->GetY());

    $pdf->Ln(8);

    // ========== TÍTULO ==========
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 10, 'RECIBO DE EGRESO', 0, 1, 'C');

    $pdf->Ln(3);

    // Número de documento
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, 'No. ' . ($datos['numdoc'] ?: 'S/N'), 0, 1, 'C');

    $pdf->Ln(10);

    // ========== INFORMACIÓN DEL CLIENTE ==========
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(40, 7, 'Cliente:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 7, Utf8::decode($datos['cliente_nombre']), 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(40, 7, Utf8::decode('Identificación:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 7, $datos['cliente_identificacion'], 0, 1, 'L');

    $pdf->Ln(8);

    // ========== INFORMACIÓN DE PAGO ==========
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(40, 7, 'Fecha:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 7, date('d/m/Y', strtotime($datos['fecha'])), 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(40, 7, 'Forma de Pago:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 7, Utf8::decode(strtoupper($datos['forma_pago'])), 0, 1, 'L');

    // Si es banco, mostrar información bancaria
    if (strtolower($datos['forma_pago']) === 'banco' && !empty($datos['banco_nombre'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->Cell(40, 7, 'Banco:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 7, Utf8::decode($datos['banco_nombre']) . ' - ' . $datos['banco_cuenta'], 0, 1, 'L');

        if (!empty($datos['doc_banco'])) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(52, 73, 94);
            $pdf->Cell(40, 7, Utf8::decode('No. Cheque:'), 0, 0, 'L');
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(44, 62, 80);
            $pdf->Cell(0, 7, $datos['doc_banco'], 0, 1, 'L');
        }
    }

    $pdf->Ln(12);

    // ========== CONCEPTO ==========
    if (!empty($datos['concepto'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->Cell(0, 7, 'Concepto:', 0, 1, 'L');

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->MultiCell(0, 6, Utf8::decode($datos['concepto']), 0, 'J');

        $pdf->Ln(8);
    }

    // ========== MONTO LÍQUIDO ==========
    $pdf->SetFillColor(41, 128, 185);
    $pdf->Rect(20, $pdf->GetY(), 175, 25, 'F');

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Ln(6);
    $pdf->Cell(0, 8, Utf8::decode('MONTO LÍQUIDO PAGADO:'), 0, 1, 'C');

    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 8, Moneda::formato($totalLiquido), 0, 1, 'C');

    $pdf->Ln(15);

    // ========== DESGLOSE SIMPLE ==========
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Subtotal Productos: ' . Moneda::formato($totalProductos), 0, 1, 'R');
    if ($totalDescuentos > 0) {
        $pdf->Cell(0, 5, 'Total Descuentos: - ' . Moneda::formato($totalDescuentos), 0, 1, 'R');
    }

    $pdf->Ln(20);

    // ========== FIRMAS ==========
    $pdf->SetY(-60);

    // Líneas de firma
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(35, $pdf->GetY(), 95, $pdf->GetY());
    $pdf->Line(120, $pdf->GetY(), 180, $pdf->GetY());

    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(85, 5, 'Entregado por', 0, 0, 'C');
    $pdf->Cell(90, 5, Utf8::decode('Recibí conforme'), 0, 1, 'C');

    // Salida del PDF
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Recibo de egreso generado correctamente',
        'namefile' => "Recibo_Egreso_" . ($datos['numdoc'] ?: $datos['id']) . ".pdf",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
