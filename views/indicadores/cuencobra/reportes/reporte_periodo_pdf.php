<?php
include __DIR__ . '/../../../../includes/Config/config.php';
session_start();

if (!isset($_SESSION['id_agencia'])) {
    die('Sesión expirada');
}

require_once __DIR__ . '/../../../../fpdf/fpdf.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use App\Generic\Agencia;
use Micro\Generic\Utf8;

$database = new DatabaseAdapter();
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

$periodoId =  $_POST["datosval"][3][0] ?? '0';
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';

$showmensaje = false;
try {
    if ($periodoId == '0') {
        $showmensaje = true;
        throw new Exception("Período no válido seleccionado");
    }
    $database->openConnection();

    // Obtener datos del periodo
    $datosPeriodo = $database->getAllResults(
        "SELECT id, nombre, fecha_inicio, fecha_fin, tasa_interes, tasa_mora 
         FROM cc_periodos WHERE id = ?",
        [$periodoId]
    );

    if (empty($datosPeriodo)) {
        $showmensaje = true;
        throw new Exception("No se encontró el período seleccionado");
        // die('No se encontró el periodo seleccionado');
    }

    $datosPeriodo = $datosPeriodo[0];

    // Obtener información de la agencia e institución
    $nombreCompletoInstitucion = (new Agencia($idagencia))->institucion?->getNombreCompletoInstitucion();
    
    $infoAgencia = $database->getAllResults(
        "SELECT ag.nom_agencia, ins.nomb_comple, ins.muni_lug, ins.emai, ins.tel_1, ins.nit, ins.log_img
        FROM tb_agencia ag
        INNER JOIN $db_name_general.info_coperativa ins ON ag.id_institucion = ins.id_cop
        WHERE ag.id_agencia = ?",
        [$idagencia]
    );

    // Obtener cuentas del periodo
    $cuentasPeriodo = $database->getAllResults(
        "SELECT 
            cue.id AS cuenta_id,
            cli.short_name AS cliente_nombre,
            cli.no_identifica AS cliente_identificacion,
            cue.monto_inicial,
            cue.fecha_inicio,
            cue.fecha_fin,
            cue.estado,
            cue.tasa_interes,
            
            IFNULL((
                SELECT SUM(valor) 
                FROM cc_cuentas_garantias 
                WHERE id_cuenta = cue.id AND estado = '1'
            ), 0) AS total_garantias,
            
            IFNULL((
                SELECT SUM(kp) 
                FROM cc_kardex 
                WHERE id_cuenta = cue.id AND tipo = 'D' AND estado = '1'
            ), 0) AS total_financiado,
            
            IFNULL((
                SELECT SUM(k.total)
                FROM cc_kardex k
                WHERE k.id_cuenta = cue.id AND k.tipo = 'E' AND k.estado = '1'
            ), 0) AS total_otras_entregas,
            
            IFNULL((
                SELECT SUM(kp) 
                FROM cc_kardex 
                WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
            ), 0) AS total_capital_pagado,
            
            IFNULL((
                SELECT SUM(interes) 
                FROM cc_kardex 
                WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
            ), 0) AS total_interes_pagado,
            
            IFNULL((
                SELECT SUM(mora) 
                FROM cc_kardex 
                WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
            ), 0) AS total_mora_pagado,
            
            IFNULL((
                SELECT SUM(total - kp - interes - mora) 
                FROM cc_kardex 
                WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
            ), 0) AS total_otros_pagado
            
        FROM cc_cuentas cue
        INNER JOIN tb_cliente cli ON cli.idcod_cliente = cue.id_cliente
        WHERE cue.id_periodo = ? AND cue.estado IN ('ACTIVA', 'CANCELADA')
        ORDER BY cue.id ASC",
        [$periodoId]
    );

    // Calcular saldos
    foreach ($cuentasPeriodo as &$cuenta) {
        $cuenta['saldo_financiamiento'] = $cuenta['total_financiado'] - $cuenta['total_capital_pagado'];
        $cuenta['saldo_otras_entregas'] = $cuenta['total_otras_entregas'] - $cuenta['total_otros_pagado'];
        $cuenta['saldo_total'] = $cuenta['saldo_financiamiento'] + $cuenta['saldo_otras_entregas'];
    }
    unset($cuenta);

    // $database->closeConnection();
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

// Crear PDF
class PDF extends FPDF
{
    private $titulo;
    private $periodo;
    private $fechas;
    private $infoAgencia;
    private $nombreUsuario;
    private $fechaImpresion;
    private $horaImpresion;

    function __construct($titulo, $periodo, $fechas, $infoAgencia, $nombreUsuario)
    {
        parent::__construct('L', 'mm', 'Letter'); // Orientación horizontal
        $this->titulo = $titulo;
        $this->periodo = $periodo;
        $this->fechas = $fechas;
        $this->infoAgencia = $infoAgencia;
        $this->nombreUsuario = $nombreUsuario;
        $this->fechaImpresion = date('d/m/Y');
        $this->horaImpresion = date('H:i:s');
    }

    function Header()
    {
        $info = $this->infoAgencia;
        $y_inicial = $this->GetY();

        // Logo si existe
        if (!empty($info['log_img']) && file_exists(__DIR__ . '/../../../../' . $info['log_img'])) {
            $this->Image(__DIR__ . '/../../../../' . $info['log_img'], 15, $y_inicial, 25);
        }

        // Información de la institución
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(33, 37, 41);
        $this->SetX(45);
        $this->Cell(0, 6, Utf8::decode($info['nomb_comple'] ?? ''), 0, 1, 'L');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(108, 117, 125);
        $this->SetX(45);
        $this->Cell(0, 4, Utf8::decode($info['muni_lug'] ?? ''), 0, 1, 'L');

        if (!empty($info['tel_1'])) {
            $this->SetX(45);
            $this->Cell(0, 4, 'Tel: ' . Utf8::decode($info['tel_1']), 0, 1, 'L');
        }

        if (!empty($info['nit'])) {
            $this->SetX(45);
            $this->Cell(0, 4, 'NIT: ' . Utf8::decode($info['nit']), 0, 1, 'L');
        }

        $this->Ln(3);

        // Línea separadora
        $this->SetDrawColor(52, 58, 64);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 265, $this->GetY());
        $this->Ln(5);

        // Título del documento
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(0, 8, Utf8::decode($this->titulo), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, Utf8::decode('Período: ' . $this->periodo), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(0, 5, Utf8::decode($this->fechas), 0, 1, 'C');
        
        $this->Ln(3);
    }

    function Footer()
    {
        $this->SetY(-20);
        
        // Línea separadora
        $this->SetDrawColor(206, 212, 218);
        $this->Line(15, $this->GetY(), 265, $this->GetY());
        $this->Ln(2);
        
        // Información de impresión
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(108, 117, 125);
        
        // Usuario y fecha/hora en la misma línea
        $this->Cell(0, 3, Utf8::decode('Impreso por: ' . $this->nombreUsuario . ' | Fecha: ' . $this->fechaImpresion . ' | Hora: ' . $this->horaImpresion), 0, 1, 'C');
        
        // Número de página
        $this->Cell(0, 3, Utf8::decode('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }

    function TablaReporte($cuentas)
    {
        // Cabecera
        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(200, 200, 200);

        $this->Cell(15, 7, Utf8::decode('No.'), 1, 0, 'C', true);
        $this->Cell(45, 7, Utf8::decode('Titular'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Garantías'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Financiado'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Otras Ent.'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Cap. Pagado'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Saldo Fin.'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Saldo Otras'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Int. Pagado'), 1, 0, 'C', true);
        $this->Cell(22, 7, Utf8::decode('Mora Pagada'), 1, 0, 'C', true);
        $this->Cell(18, 7, Utf8::decode('Estado'), 1, 1, 'C', true);

        // Datos
        $this->SetFont('Arial', '', 7);

        $totales = [
            'garantias' => 0,
            'financiado' => 0,
            'otras_entregas' => 0,
            'capital_pagado' => 0,
            'saldo_fin' => 0,
            'saldo_otras' => 0,
            'interes_pagado' => 0,
            'mora_pagado' => 0
        ];

        foreach ($cuentas as $cuenta) {
            $this->Cell(15, 6, $cuenta['cuenta_id'], 1, 0, 'C');
            $this->Cell(45, 6, Utf8::decode(substr($cuenta['cliente_nombre'], 0, 30)), 1, 0, 'L');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['total_garantias'], 2), 1, 0, 'R');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['total_financiado'], 2), 1, 0, 'R');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['total_otras_entregas'], 2), 1, 0, 'R');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['total_capital_pagado'], 2), 1, 0, 'R');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['saldo_financiamiento'], 2), 1, 0, 'R');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['saldo_otras_entregas'], 2), 1, 0, 'R');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['total_interes_pagado'], 2), 1, 0, 'R');
            $this->Cell(22, 6, 'Q ' . number_format($cuenta['total_mora_pagado'], 2), 1, 0, 'R');
            $this->Cell(18, 6, Utf8::decode($cuenta['estado']), 1, 1, 'C');

            $totales['garantias'] += $cuenta['total_garantias'];
            $totales['financiado'] += $cuenta['total_financiado'];
            $totales['otras_entregas'] += $cuenta['total_otras_entregas'];
            $totales['capital_pagado'] += $cuenta['total_capital_pagado'];
            $totales['saldo_fin'] += $cuenta['saldo_financiamiento'];
            $totales['saldo_otras'] += $cuenta['saldo_otras_entregas'];
            $totales['interes_pagado'] += $cuenta['total_interes_pagado'];
            $totales['mora_pagado'] += $cuenta['total_mora_pagado'];
        }

        // Totales
        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(60, 7, Utf8::decode('TOTALES:'), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['garantias'], 2), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['financiado'], 2), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['otras_entregas'], 2), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['capital_pagado'], 2), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['saldo_fin'], 2), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['saldo_otras'], 2), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['interes_pagado'], 2), 1, 0, 'R', true);
        $this->Cell(22, 7, 'Q ' . number_format($totales['mora_pagado'], 2), 1, 0, 'R', true);
        $this->Cell(18, 7, '', 1, 1, 'C', true);
    }
}

// Generar PDF
$titulo = 'REPORTE DE FINANCIAMIENTOS CONSOLIDADO';
$periodo = $datosPeriodo['nombre'];
$fechas = 'Del ' . date('d/m/Y', strtotime($datosPeriodo['fecha_inicio'])) .
    ' al ' . date('d/m/Y', strtotime($datosPeriodo['fecha_fin']));

$info = $infoAgencia[0] ?? [
    'nomb_comple' => $nombreCompletoInstitucion,
    'muni_lug' => '',
    'tel_1' => '',
    'nit' => '',
    'log_img' => ''
];

$pdf = new PDF($titulo, $periodo, $fechas, $info, $nombreUsuario);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->TablaReporte($cuentasPeriodo);
// $pdf->Output('I', 'Reporte_Periodo_' . $datosPeriodo['nombre'] . '.pdf');

// Salida del PDF
ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Estado de cuenta generado correctamente',
    'namefile' => 'Reporte_Periodo_' . $datosPeriodo['nombre'] . '.pdf',
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData)
);
echo json_encode($opResult);
exit;
