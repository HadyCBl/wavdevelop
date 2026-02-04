<?php

include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();

require __DIR__ . '/../../../../fpdf/fpdf.php';
// require __DIR__ . '/../../../../vendor/autoload.php';

use App\DatabaseAdapter;
use App\Generic\Agencia;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
    return;
}

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

$idMovimiento = $_POST["datosval"][3][0];
$tipo = $_POST["tipo"];

$database = new DatabaseAdapter();

$showmensaje = false;
try {

    $nombreCompletoInstitucion = (new Agencia($idagencia))->institucion?->getNombreCompletoInstitucion();

    $database->openConnection();

    // Obtener datos del desembolso
    $datosDesembolso = $database->getAllResults(
        "SELECT 
            kar.id, kar.total, kar.kp, kar.fecha, kar.numdoc, kar.forma_pago, kar.concepto, 
            kar.doc_banco,
            cli.short_name AS cliente_nombre, cli.no_identifica AS cliente_identificacion,
            cue.monto_inicial, cue.tasa_interes, cue.fecha_inicio, cue.fecha_fin,
            per.nombre AS periodo_nombre,
            IFNULL(ban.nombre, '') AS banco_nombre, IFNULL(ctb.numcuenta, '') AS banco_cuenta
        FROM cc_kardex kar
        INNER JOIN cc_cuentas cue ON cue.id = kar.id_cuenta
        INNER JOIN tb_cliente cli ON cli.idcod_cliente = cue.id_cliente
        LEFT JOIN cc_periodos per ON per.id = cue.id_periodo
        LEFT JOIN ctb_bancos ctb ON ctb.id = kar.id_ctbbancos
        LEFT JOIN tb_bancos ban ON ban.id = ctb.id_banco
        WHERE kar.id = ? AND kar.tipo = 'D'",
        [$idMovimiento]
    );

    if (empty($datosDesembolso)) {
        $showmensaje = true;
        throw new Exception('No se encontraron datos del desembolso');
    }

    // Obtener garantías de la cuenta
    $garantias = $database->getAllResults(
        "SELECT 
            gar.descripcion, gar.valor, gar.observaciones,
            tg.nombre AS tipo_garantia
        FROM cc_cuentas_garantias gar
        INNER JOIN cc_tipos_garantias tg ON tg.id = gar.id_tipogarantia
        INNER JOIN cc_kardex kar ON kar.id_cuenta = gar.id_cuenta
        WHERE kar.id = ? AND gar.estado = '1'",
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
        printpdf($datosDesembolso, $garantias, $nombreCompletoInstitucion);
        break;
    case 'show':
        // Mostrar en ventana
        break;
}

function printpdf($datosDesembolso, $garantias, $nombreInstitucion)
{
    $datos = $datosDesembolso[0];

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

    // Subtítulo
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(12, 5, '', 0, 0); // Espaciador para alinear con el nombre
    $pdf->Cell(0, 5, Utf8::decode('Gestión de Cuentas por Cobrar'), 0, 1, 'L');

    // Línea decorativa
    $pdf->SetDrawColor(41, 128, 185);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);

    // ========== TÍTULO DEL DOCUMENTO ==========
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 10, Utf8::decode('COMPROBANTE DE DESEMBOLSO'), 0, 1, 'C');

    // Número de documento
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'No. Documento: ' . str_pad($datos['numdoc'], 8, '0', STR_PAD_LEFT), 0, 1, 'C');

    $pdf->Ln(5);

    // ========== INFORMACIÓN DEL CLIENTE ==========
    // Caja con fondo gris
    $pdf->SetFillColor(236, 240, 241);
    $pdf->Rect(15, $pdf->GetY(), 180, 45, 'F');

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

    // Período
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(5, 6, '', 0, 0);
    $pdf->Cell(50, 6, Utf8::decode('Período:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 6, Utf8::decode($datos['periodo_nombre']), 0, 1, 'L');

    // Tasa de interés
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(5, 6, '', 0, 0);
    $pdf->Cell(50, 6, Utf8::decode('Tasa de Interés:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 6, $datos['tasa_interes'] . '%', 0, 1, 'L');

    $pdf->Ln(8);

    // ========== INFORMACIÓN DEL DESEMBOLSO ==========
    $pdf->SetFillColor(236, 240, 241);
    $alturaSeccion = (strtolower($datos['forma_pago']) === 'banco' && !empty($datos['banco_nombre'])) ? 40 : 30;
    $pdf->Rect(15, $pdf->GetY(), 180, $alturaSeccion, 'F');

    $pdf->Ln(5);

    // Fecha
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->Cell(5, 6, '', 0, 0);
    $pdf->Cell(50, 6, 'Fecha de Desembolso:', 0, 0, 'L');
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

    // ========== MONTO DESTACADO ==========
    $pdf->SetFillColor(46, 204, 113); // Verde
    $pdf->Rect(15, $pdf->GetY(), 180, 20, 'F');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Ln(5);
    $pdf->Cell(5, 5, '', 0, 0); // Margen izquierdo
    $pdf->Cell(80, 5, 'MONTO DESEMBOLSADO:', 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(20, 10, '', 0, 0); // Espaciador
    $montoFormateado = Moneda::formato($datos['total']);
    $pdf->Cell(160, 10, $montoFormateado, 0, 1, 'R');

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

    // ========== GARANTÍAS ==========
    if (!empty($garantias)) {
        // Título de sección
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 8, Utf8::decode('GARANTÍAS ASOCIADAS'), 0, 1, 'L');

        // Encabezado de tabla
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);

        $pdf->Cell(50, 8, 'TIPO', 1, 0, 'C', true);
        $pdf->Cell(70, 8, Utf8::decode('DESCRIPCIÓN'), 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'VALOR', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'OBSERVACIONES', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(44, 62, 80);

        $totalGarantias = 0;

        foreach ($garantias as $g) {
            $totalGarantias += $g['valor'];

            $pdf->SetFillColor(245, 247, 250);
            $pdf->Cell(50, 7, Utf8::decode($g['tipo_garantia']), 1, 0, 'L', true);
            $pdf->Cell(70, 7, Utf8::decode($g['descripcion']), 1, 0, 'L', true);
            $pdf->Cell(30, 7, Moneda::formato($g['valor']), 1, 0, 'R', true);
            $pdf->Cell(30, 7, Utf8::decode(substr($g['observaciones'], 0, 20)), 1, 1, 'L', true);
        }

        // Total de garantías
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(150, 8, Utf8::decode('TOTAL GARANTÍAS:'), 1, 0, 'R', true);
        $pdf->Cell(30, 8, Moneda::formato($totalGarantias), 1, 1, 'R', true);
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
        'namefile' => "Comprobante_Desembolso_" . str_pad($datos['numdoc'], 8, '0', STR_PAD_LEFT) . ".pdf",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
