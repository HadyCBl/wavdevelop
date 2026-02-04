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
    $pdf->SetY(18);
    $pdf->Cell(12, 6, 'CC', 0, 0, 'C');

    // Nombre de la institución
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(41, 128, 185);
    $pdf->Cell(0, 7, Utf8::decode($nombreInstitucion), 0, 1, 'L');

    // Línea decorativa
    $pdf->SetDrawColor(41, 128, 185);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);

    // ========== TÍTULO DEL DOCUMENTO ==========
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 10, Utf8::decode('COMPROBANTE DE COMPRA'), 0, 1, 'C');

    // Número de documento
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'No. Documento: ' . ($datos['numdoc'] ?: 'N/A'), 0, 1, 'C');

    // Estado
    $estadoTexto = '';
    switch ($datos['estado']) {
        case 'draft':
            $estadoTexto = 'BORRADOR';
            break;
        case 'pending':
            $estadoTexto = 'PENDIENTE';
            break;
        case 'settled':
            $estadoTexto = 'LIQUIDADA';
            break;
        default:
            $estadoTexto = strtoupper($datos['estado']);
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 5, 'Estado: ' . $estadoTexto, 0, 1, 'C');

    $pdf->Ln(5);

    // ========== INFORMACIÓN DEL CLIENTE ==========
    // Caja con fondo gris
    $pdf->SetFillColor(236, 240, 241);
    $pdf->Rect(15, $pdf->GetY(), 180, 35, 'F');

    $pdf->Ln(5);

    // Título de sección
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(41, 128, 185);
    $pdf->Cell(5, 6, '', 0, 0); // Margen izquierdo
    $pdf->Cell(0, 6, Utf8::decode('INFORMACIÓN DEL CLIENTE'), 0, 1, 'L');

    $pdf->Ln(2);

    // Cliente
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(5, 6, '', 0, 0); // Margen izquierdo
    $pdf->Cell(50, 6, 'Cliente:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 6, Utf8::decode($datos['cliente_nombre']), 0, 1, 'L');

    // Identificación
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(5, 6, '', 0, 0);
    $pdf->Cell(50, 6, Utf8::decode('Identificación:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 6, $datos['cliente_identificacion'], 0, 1, 'L');

    $pdf->Ln(8);

    // ========== INFORMACIÓN DE LA COMPRA ==========
    $pdf->SetFillColor(236, 240, 241);
    $alturaSeccion = (strtolower($datos['forma_pago']) === 'banco' && !empty($datos['banco_nombre'])) ? 40 : 30;
    $pdf->Rect(15, $pdf->GetY(), 180, $alturaSeccion, 'F');

    $pdf->Ln(5);

    // Fecha
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(5, 6, '', 0, 0);
    $pdf->Cell(50, 6, 'Fecha de Compra:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 6, date('d/m/Y', strtotime($datos['fecha'])), 0, 1, 'L');

    // Forma de pago
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(5, 6, '', 0, 0);
    $pdf->Cell(50, 6, 'Forma de Pago:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 6, Utf8::decode(strtoupper($datos['forma_pago'])), 0, 1, 'L');

    // Si es banco, mostrar información bancaria
    if (strtolower($datos['forma_pago']) === 'banco' && !empty($datos['banco_nombre'])) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->Cell(5, 6, '', 0, 0);
        $pdf->Cell(50, 6, 'Banco:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(0, 6, Utf8::decode($datos['banco_nombre']) . ' - ' . $datos['banco_cuenta'], 0, 1, 'L');

        if (!empty($datos['doc_banco'])) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(52, 73, 94);
            $pdf->Cell(5, 6, '', 0, 0);
            $pdf->Cell(50, 6, Utf8::decode('No. Cheque:'), 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(44, 62, 80);
            $pdf->Cell(0, 6, $datos['doc_banco'], 0, 1, 'L');
        }
    }

    $pdf->Ln(8);

    // ========== PRODUCTOS ==========
    if (!empty($productos)) {
        // Título de sección
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 8, Utf8::decode('PRODUCTOS'), 0, 1, 'L');

        // Encabezado de tabla
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 8);

        $widthsProducts = [70, 28, 20, 25, 20, 25];

        $pdf->Cell($widthsProducts[0], 8, Utf8::decode('PRODUCTO'), 1, 0, 'C', true);
        $pdf->Cell($widthsProducts[1], 8, 'DET.', 1, 0, 'C', true);
        $pdf->Cell($widthsProducts[2], 8, 'CANTIDAD', 1, 0, 'C', true);
        $pdf->Cell($widthsProducts[3], 8, 'MEDIDA', 1, 0, 'C', true);
        $pdf->Cell($widthsProducts[4], 8, 'P. UNIT.', 1, 0, 'C', true);
        $pdf->Cell($widthsProducts[5], 8, 'SUBTOTAL', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(44, 62, 80);

        $totalProductos = 0;

        foreach ($productos as $prod) {
            $subtotalProd = $prod['cantidad'] * $prod['precio_unitario'];
            $totalProductos += $subtotalProd;

            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell($widthsProducts[0], 7, Utf8::decode($prod['producto_nombre']), 1, 0, 'L', true);
            $pdf->Cell($widthsProducts[1], 7, Utf8::decode($prod['descripcion']), 1, 0, 'C', true);
            $pdf->Cell($widthsProducts[2], 7, number_format($prod['cantidad'], 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($widthsProducts[3], 7, Utf8::decode($prod['medida']), 1, 0, 'C', true);
            $pdf->Cell($widthsProducts[4], 7, Moneda::formato($prod['precio_unitario']), 1, 0, 'R', true);
            $pdf->Cell($widthsProducts[5], 7, Moneda::formato($subtotalProd), 1, 1, 'R', true);
        }

        // Total de productos
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($widthsProducts[0] + $widthsProducts[1] + $widthsProducts[2] + $widthsProducts[3] + $widthsProducts[4], 8, Utf8::decode('SUBTOTAL:'), 1, 0, 'R', true);
        $pdf->Cell($widthsProducts[5], 8, Moneda::formato($totalProductos), 1, 1, 'R', true);
    }

    $pdf->Ln(5);

    // ========== DESCUENTOS ==========
    $totalDescuentos = 0;
    if (!empty($descuentos)) {
        // Título de sección
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 8, Utf8::decode('DESCUENTOS APLICADOS'), 0, 1, 'L');

        // Encabezado de tabla
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);

        $pdf->Cell(80, 8, 'TIPO', 1, 0, 'C', true);
        $pdf->Cell(40, 8, Utf8::decode('CATEGORÍA'), 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'MONTO', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'OBSERVACIONES', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(44, 62, 80);

        foreach ($descuentos as $desc) {
            $totalDescuentos += $desc['monto'];

            $pdf->SetFillColor(254, 243, 199);
            $pdf->Cell(80, 7, Utf8::decode($desc['descuento_nombre']), 1, 0, 'L', true);
            $pdf->Cell(40, 7, Utf8::decode(ucfirst($desc['categoria'])), 1, 0, 'C', true);
            $pdf->Cell(40, 7, Moneda::formato($desc['monto']), 1, 0, 'R', true);
            $pdf->Cell(30, 7, Utf8::decode(substr($desc['observaciones'] ?? '', 0, 20)), 1, 1, 'L', true);
        }

        // Total de descuentos
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(234, 179, 8);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(160, 8, Utf8::decode('TOTAL DESCUENTOS:'), 1, 0, 'R', true);
        $pdf->Cell(30, 8, Moneda::formato($totalDescuentos), 1, 1, 'R', true);
    }

    $pdf->Ln(5);

    // ========== TOTAL FINAL ==========
    $pdf->SetFillColor(46, 204, 113); // Verde
    $pdf->Rect(15, $pdf->GetY(), 180, 20, 'F');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Ln(5);
    $pdf->Cell(5, 5, '', 0, 0); // Margen izquierdo
    $pdf->Cell(80, 5, 'TOTAL A PAGAR:', 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(20, 10, '', 0, 0); // Espaciador
    $totalFinal = $totalProductos - $totalDescuentos;
    $pdf->Cell(160, 10, Moneda::formato($totalFinal), 0, 1, 'R');

    $pdf->Ln(5);

    // ========== CONCEPTO ==========
    if (!empty($datos['concepto'])) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->Cell(0, 6, 'Concepto:', 0, 1, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->MultiCell(0, 5, Utf8::decode($datos['concepto']), 0, 'L');

        $pdf->Ln(5);
    }

    // ========== PIE DE PÁGINA ==========
    $pdf->SetY(-40);

    // Líneas de firma
    $pdf->Ln(10);
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->Line(30, $pdf->GetY(), 90, $pdf->GetY());
    $pdf->Line(120, $pdf->GetY(), 180, $pdf->GetY());

    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(90, 5, 'Elaborado por', 0, 0, 'C');
    $pdf->Cell(90, 5, 'Recibido por', 0, 1, 'C');

    // Salida del PDF
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Comprobante_Compra_" . ($datos['numdoc'] ?: $datos['id']) . ".pdf",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
