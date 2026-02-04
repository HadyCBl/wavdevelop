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

$idCuenta = $_POST["datosval"][3][0];
$tipo = $_POST["tipo"];

$database = new DatabaseAdapter();
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

$showmensaje = false;
try {

    $nombreCompletoInstitucion = (new Agencia($idagencia))->institucion?->getNombreCompletoInstitucion();

    $database->openConnection();

    // Obtener datos de la cuenta
    $datosCuenta = $database->getAllResults(
        "SELECT 
            cue.id, cue.monto_inicial, cue.tasa_interes, cue.fecha_inicio, cue.fecha_fin, cue.estado,
            cli.short_name AS cliente_nombre, cli.no_identifica AS cliente_identificacion,
            per.nombre AS periodo_nombre, per.tasa_mora,
             IFNULL((SELECT SUM(kp) FROM cc_kardex WHERE id_cuenta=cue.id AND tipo='D' AND estado='1' GROUP BY id_cuenta), 0) AS financiado
        FROM cc_cuentas cue
        INNER JOIN tb_cliente cli ON cli.idcod_cliente = cue.id_cliente
        LEFT JOIN cc_periodos per ON per.id = cue.id_periodo
        WHERE cue.id = ?",
        [$idCuenta]
    );

    if (empty($datosCuenta)) {
        $showmensaje = true;
        throw new Exception('No se encontraron datos de la cuenta');
    }

    // Obtener todos los movimientos del kardex agrupados por tipo
    $movimientos = $database->getAllResults(
        "SELECT 
            kar.id, kar.fecha, kar.tipo, kar.numdoc, kar.concepto,
            kar.total, kar.kp, kar.interes, kar.mora,
            kar.forma_pago,
            CASE 
                WHEN kar.tipo = 'D' THEN 'DESEMBOLSO'
                WHEN kar.tipo = 'E' THEN CONCAT('ENTREGA: ', IFNULL(tm.nombre, 'OTROS'))
                WHEN kar.tipo = 'I' THEN 'PAGO'
                ELSE 'OTRO'
            END AS tipo_descripcion,
            kd.monto as monto_detalle,
            tm.nombre as tipo_movimiento_nombre
        FROM cc_kardex kar
        LEFT JOIN cc_kardex_detalle kd ON kd.id_kardex = kar.id
        LEFT JOIN cc_tipos_movimientos tm ON tm.id = kd.id_movimiento
        WHERE kar.id_cuenta = ? AND kar.estado = '1'
        ORDER BY kar.fecha ASC, kar.id ASC",
        [$idCuenta]
    );

    // Obtener garantías
    $garantias = $database->getAllResults(
        "SELECT 
            gar.descripcion, gar.valor, gar.observaciones,
            tg.nombre AS tipo_garantia
        FROM cc_cuentas_garantias gar
        INNER JOIN cc_tipos_garantias tg ON tg.id = gar.id_tipogarantia
        WHERE gar.id_cuenta = ? AND gar.estado = '1'
        ORDER BY gar.id ASC",
        [$idCuenta]
    );

    // Obtener información de la agencia
    $infoAgencia = $database->getAllResults(
        "SELECT ag.nom_agencia, ins.nomb_comple, ins.muni_lug, ins.emai, ins.tel_1, ins.nit, ins.log_img
        FROM tb_agencia ag
        INNER JOIN $db_name_general.info_coperativa ins ON ag.id_institucion = ins.id_cop
        WHERE ag.id_agencia = ?",
        [$idagencia]
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
        printpdf($datosCuenta, $movimientos, $garantias, $nombreCompletoInstitucion, $infoAgencia);
        break;
    case 'show':
        // Mostrar en ventana
        break;
}

function printpdf($datosCuenta, $movimientos, $garantias, $nombreInstitucion, $infoAgencia)
{
    require __DIR__ . '/../../../../fpdf/fpdf.php';

    $datos = $datosCuenta[0];
    $info = $infoAgencia[0] ?? [];

    // Crear instancia de PDF
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetMargins(15, 15, 15);

    // ========== ENCABEZADO CON LOGO ==========
    $y_inicial = $pdf->GetY();

    // Logo si existe
    if (!empty($info['log_img']) && file_exists(__DIR__ . '/../../../../' . $info['log_img'])) {
        $pdf->Image(__DIR__ . '/../../../../' . $info['log_img'], 15, $y_inicial, 25);
    }

    // Información de la institución
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->SetX(45);
    $pdf->Cell(0, 6, Utf8::decode($info['nomb_comple'] ?? $nombreInstitucion), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->SetX(45);
    $pdf->Cell(0, 4, Utf8::decode($info['muni_lug'] ?? ''), 0, 1, 'L');

    if (!empty($info['tel_1'])) {
        $pdf->SetX(45);
        $pdf->Cell(0, 4, 'Tel: ' . Utf8::decode($info['tel_1']), 0, 1, 'L');
    }

    if (!empty($info['nit'])) {
        $pdf->SetX(45);
        $pdf->Cell(0, 4, 'NIT: ' . Utf8::decode($info['nit']), 0, 1, 'L');
    }

    $pdf->Ln(5);

    // Línea separadora
    $pdf->SetDrawColor(52, 58, 64);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

    // ========== TÍTULO DEL DOCUMENTO =========
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(0, 8, Utf8::decode('ESTADO DE CUENTA'), 0, 1, 'C');

    // Información adicional
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 5, 'Cuenta No. ' . str_pad($datos['id'], 8, '0', STR_PAD_LEFT), 0, 1, 'C');
    $pdf->Cell(0, 5, Utf8::decode('Fecha de emisión: ') . date('d/m/Y H:i'), 0, 1, 'C');

    // Estado de la cuenta
    $estadoTexto = '';
    $estadoColor = [108, 117, 125];
    switch (strtoupper($datos['estado'])) {
        case 'ACTIVA':
            $estadoTexto = 'ACTIVA';
            $estadoColor = [40, 167, 69];
            break;
        case 'CANCELADA':
            $estadoTexto = 'CANCELADA';
            $estadoColor = [220, 53, 69];
            break;
        default:
            $estadoTexto = strtoupper($datos['estado']);
    }

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor($estadoColor[0], $estadoColor[1], $estadoColor[2]);
    $pdf->Cell(0, 5, 'Estado: ' . $estadoTexto, 0, 1, 'C');

    $pdf->Ln(5);

    // ========== INFORMACIÓN DEL CLIENTE =========
    $pdf->SetFillColor(248, 249, 250);
    $pdf->Rect(15, $pdf->GetY(), 180, 35, 'F');

    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(0, 5, Utf8::decode('DATOS DEL TITULAR'), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(73, 80, 87);

    // Cliente
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(40, 5, 'Cliente:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, Utf8::decode($datos['cliente_nombre']), 0, 1, 'L');

    // Identificación
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(40, 5, Utf8::decode('Identificación:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(70, 5, $datos['cliente_identificacion'], 0, 0, 'L');

    // Período
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 5, Utf8::decode('Período:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, Utf8::decode($datos['periodo_nombre']), 0, 1, 'L');

    // Monto inicial y Tasa de interés
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(40, 5, 'Total financiamiento:', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(70, 5, Moneda::formato($datos['financiado']), 0, 0, 'L');

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 5, Utf8::decode('Tasa Interés:'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, $datos['tasa_interes'] . '%', 0, 1, 'L');

    $pdf->Ln(8);

    // ========== RESUMEN DE MOVIMIENTOS =========
    $totalDesembolsos = 0;
    $totalEntregas = 0;
    $totalRecuperaciones = 0;
    $totalCapitalRecuperado = 0;
    $totalInteresRecuperado = 0;
    $totalMoraRecuperado = 0;

    foreach ($movimientos as $mov) {
        if ($mov['tipo'] === 'D') {
            $totalDesembolsos += $mov['total'];
        } elseif ($mov['tipo'] === 'E') {
            $totalEntregas += $mov['total'];
        } elseif ($mov['tipo'] === 'I') {
            $totalRecuperaciones += $mov['total'];
            $totalCapitalRecuperado += $mov['kp'];
            $totalInteresRecuperado += $mov['interes'];
            $totalMoraRecuperado += $mov['mora'];
        }
    }

    $saldoCapital = $totalDesembolsos - $totalCapitalRecuperado;
    $saldoOtrasEntregas = $totalEntregas - ($totalRecuperaciones - $totalCapitalRecuperado - $totalInteresRecuperado - $totalMoraRecuperado);

    $pdf->SetFillColor(248, 249, 250);
    $pdf->Rect(15, $pdf->GetY(), 180, 45, 'F');

    $pdf->Ln(3);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(0, 5, 'RESUMEN DE MOVIMIENTOS', 0, 1, 'L');

    $pdf->Ln(2);

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(73, 80, 87);

    // Tabla de resumen
    $pdf->Cell(10, 5, '', 0, 0);

    // Encabezados
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(70, 5, 'Concepto', 0, 0, 'L');
    $pdf->Cell(35, 5, 'Cargos', 0, 0, 'R');
    $pdf->Cell(35, 5, 'Abonos', 0, 0, 'R');
    $pdf->Cell(30, 5, 'Saldo', 0, 1, 'R');

    $pdf->SetFont('Arial', '', 8);

    // Desembolsos
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(70, 5, 'Financiamiento (Desembolsos)', 0, 0, 'L');
    $pdf->Cell(35, 5, Moneda::formato($totalDesembolsos), 0, 0, 'R');
    $pdf->Cell(35, 5, Moneda::formato($totalCapitalRecuperado), 0, 0, 'R');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(30, 5, Moneda::formato($saldoCapital), 0, 1, 'R');

    // Otras entregas
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(70, 5, 'Otras Entregas', 0, 0, 'L');
    $pdf->Cell(35, 5, Moneda::formato($totalEntregas), 0, 0, 'R');
    $pdf->Cell(35, 5, Moneda::formato($totalRecuperaciones - $totalCapitalRecuperado - $totalInteresRecuperado - $totalMoraRecuperado), 0, 0, 'R');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(30, 5, Moneda::formato($saldoOtrasEntregas), 0, 1, 'R');

    // Intereses
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(70, 5, Utf8::decode('Intereses Pagados'), 0, 0, 'L');
    $pdf->Cell(35, 5, '-', 0, 0, 'R');
    $pdf->Cell(35, 5, Moneda::formato($totalInteresRecuperado), 0, 0, 'R');
    $pdf->Cell(30, 5, '-', 0, 1, 'R');

    // Mora
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(70, 5, 'Mora Pagada', 0, 0, 'L');
    $pdf->Cell(35, 5, '-', 0, 0, 'R');
    $pdf->Cell(35, 5, Moneda::formato($totalMoraRecuperado), 0, 0, 'R');
    $pdf->Cell(30, 5, '-', 0, 1, 'R');

    // Línea separadora
    $pdf->SetDrawColor(206, 212, 218);
    $pdf->Line(25, $pdf->GetY() + 2, 185, $pdf->GetY() + 2);
    $pdf->Ln(4);

    // Total saldo pendiente
    $saldoTotalPendiente = $saldoCapital + $saldoOtrasEntregas;
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(10, 6, '', 0, 0);
    $pdf->Cell(105, 6, 'SALDO TOTAL PENDIENTE:', 0, 0, 'R');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(220, 53, 69);
    $pdf->Cell(65, 6, Moneda::formato($saldoTotalPendiente), 0, 1, 'R');

    $pdf->Ln(8);

    // ========== DETALLE DE MOVIMIENTOS =========
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(0, 7, Utf8::decode('HISTORIAL DE MOVIMIENTOS'), 0, 1, 'L');

    $widthsMov = [15, 37, 20, 20, 20, 20, 18, 20, 20];

    // Encabezado de tabla
    $pdf->SetFillColor(52, 58, 64);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 7);

    $pdf->Cell($widthsMov[0], 6, 'FECHA', 1, 0, 'C', true);
    $pdf->Cell($widthsMov[1], 6, 'TIPO', 1, 0, 'C', true);
    $pdf->Cell($widthsMov[2], 6, 'DOC.', 1, 0, 'C', true);
    $pdf->Cell($widthsMov[3], 6, 'CARGO', 1, 0, 'C', true);
    $pdf->Cell($widthsMov[4], 6, 'ABONO', 1, 0, 'C', true);
    $pdf->Cell($widthsMov[5], 6, Utf8::decode('INTERÉS'), 1, 0, 'C', true);
    $pdf->Cell($widthsMov[6], 6, 'MORA', 1, 0, 'C', true);
    $pdf->Cell($widthsMov[7], 6, 'SALDO', 1, 0, 'C', true);
    $pdf->Cell($widthsMov[8], 6, 'F. PAGO', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(33, 37, 41);

    $saldoAcumulado = 0;
    $colorAlternado = true;

    foreach ($movimientos as $mov) {
        $cargo = 0;
        $abono = 0;

        if ($mov['tipo'] === 'D' || $mov['tipo'] === 'E') {
            $cargo = $mov['total'];
            $saldoAcumulado += $cargo;
        } elseif ($mov['tipo'] === 'I') {
            $abono = $mov['kp'];
            $abono += $mov['monto_detalle'];
            $saldoAcumulado -= $abono;
        }

        // Color de fondo alternado
        if ($colorAlternado) {
            $pdf->SetFillColor(248, 249, 250);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $colorAlternado = !$colorAlternado;

        $pdf->Cell($widthsMov[0], 5, date('d/m/Y', strtotime($mov['fecha'])), 1, 0, 'C', true);
        $pdf->Cell($widthsMov[1], 5, Utf8::decode(substr($mov['tipo_descripcion'], 0, 32)), 1, 0, 'L', true);
        $pdf->Cell($widthsMov[2], 5, Utf8::decode(substr($mov['numdoc'], 0, 12)), 1, 0, 'C', true);
        $pdf->Cell($widthsMov[3], 5, $cargo > 0 ? Moneda::formato($cargo, '') : '-', 1, 0, 'R', true);
        $pdf->Cell($widthsMov[4], 5, $abono > 0 ? Moneda::formato($abono, '') : '-', 1, 0, 'R', true);
        $pdf->Cell($widthsMov[5], 5, $mov['interes'] > 0 ? Moneda::formato($mov['interes'], '') : '-', 1, 0, 'R', true);
        $pdf->Cell($widthsMov[6], 5, $mov['mora'] > 0 ? Moneda::formato($mov['mora'], '') : '-', 1, 0, 'R', true);
        $pdf->Cell($widthsMov[7], 5, Moneda::formato($saldoAcumulado, ''), 1, 0, 'R', true);
        $pdf->Cell($widthsMov[8], 5, Utf8::decode(strtoupper(substr($mov['forma_pago'], 0, 8))), 1, 1, 'C', true);
    }

    // Totales finales
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(52, 58, 64);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($widthsMov[0] + $widthsMov[1] + $widthsMov[2], 6, 'TOTALES:', 1, 0, 'R', true);
    $pdf->Cell($widthsMov[3], 6, Moneda::formato($totalDesembolsos + $totalEntregas, ''), 1, 0, 'R', true);
    $pdf->Cell($widthsMov[4], 6, Moneda::formato($totalRecuperaciones, ''), 1, 0, 'R', true);
    $pdf->Cell($widthsMov[5], 6, Moneda::formato($totalInteresRecuperado, ''), 1, 0, 'R', true);
    $pdf->Cell($widthsMov[6], 6, Moneda::formato($totalMoraRecuperado, ''), 1, 0, 'R', true);
    $pdf->Cell($widthsMov[7], 6, Moneda::formato($saldoAcumulado, ''), 1, 0, 'R', true);
    $pdf->Cell($widthsMov[8], 6, '', 1, 1, 'C', true);

    $pdf->Ln(5);

    // ========== GARANTÍAS ==========
    if (!empty($garantias)) {
        // Verificar si cabe en la página actual
        if ($pdf->GetY() > 220) {
            $pdf->AddPage();
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->Cell(0, 7, Utf8::decode('GARANTÍAS ASOCIADAS'), 0, 1, 'L');

        $pdf->SetFillColor(52, 58, 64);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 7);

        $pdf->Cell(40, 6, 'TIPO', 1, 0, 'C', true);
        $pdf->Cell(70, 6, Utf8::decode('DESCRIPCIÓN'), 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'VALOR', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'OBSERVACIONES', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(33, 37, 41);

        $totalGarantias = 0;
        $colorAlternado = true;

        foreach ($garantias as $g) {
            $totalGarantias += $g['valor'];

            if ($colorAlternado) {
                $pdf->SetFillColor(248, 249, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            $colorAlternado = !$colorAlternado;

            $pdf->Cell(40, 5, Utf8::decode(substr($g['tipo_garantia'], 0, 25)), 1, 0, 'L', true);
            $pdf->Cell(70, 5, Utf8::decode(substr($g['descripcion'], 0, 50)), 1, 0, 'L', true);
            $pdf->Cell(30, 5, Moneda::formato($g['valor']), 1, 0, 'R', true);
            $pdf->Cell(40, 5, Utf8::decode(substr($g['observaciones'], 0, 25)), 1, 1, 'L', true);
        }

        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetFillColor(52, 58, 64);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(140, 6, Utf8::decode('TOTAL GARANTÍAS:'), 1, 0, 'R', true);
        $pdf->Cell(40, 6, Moneda::formato($totalGarantias), 1, 1, 'R', true);
    }

    // ========== PIE DE PÁGINA ==========
    $pdf->SetY(-25);

    $pdf->SetDrawColor(206, 212, 218);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

    // $pdf->Ln(3);

    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(108, 117, 125);
    // $pdf->Cell(0, 3, Utf8::decode('Este documento es un estado de cuenta generado automáticamente por el sistema.'), 0, 1, 'C');
    // $pdf->Cell(0, 3, Utf8::decode('Para cualquier consulta o aclaración, favor comunicarse con el área de cuentas por cobrar.'), 0, 1, 'C');
    $pdf->Cell(0, 3, Utf8::decode('Documento generado el ' . date('d/m/Y') . ' a las ' . date('H:i') . ' hrs.'), 0, 1, 'C');

    // Salida del PDF
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Estado de cuenta generado correctamente',
        'namefile' => "Estado_Cuenta_" . str_pad($datos['id'], 8, '0', STR_PAD_LEFT) . "_" . date('Ymd') . ".pdf",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
