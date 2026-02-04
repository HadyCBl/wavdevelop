<?php

/******************************************************************
 *  reporte_boveda.php  –  Estado de cuenta de bóveda
 ******************************************************************/

/* ───────── 0. PETICIONES GET NO PERMITIDAS ───────── */
require_once __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
    exit;
}

/* ───────── 1. SESIÓN ───────── */
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e inténtalo de nuevo'
    ]);
    exit;
}
$idusuario     = $_SESSION['id'];
$agenciaSesion = $_SESSION['id_agencia'];

/* ───────── 2. DEPENDENCIAS ───────── */
require_once __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../fpdf/fpdf.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

/* ───────── 3. CONFIGURACIÓN ───────── */
// date_default_timezone_set('America/Guatemala');
$hoy = date('Y-m-d');
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* ───────── 4. UTILS ───────── */

function iso(string $t): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $t);
}
function logoPath(string $rel): string
{
    $abs = realpath(__DIR__ . '/../../../' . ltrim($rel, '/'));
    $fallback = __DIR__ . '/../../../includes/img/logomicro.png';
    return is_file($abs) ? $abs : $fallback;
}

/**
 * [`fechaInicio`,`fechaFin`],[`boveda`],[`tipo_mov`]
 */

/* ============================== 5. INPUT ============================== */
$datos   = $_POST['datosval'];        // [inputs, selects, radios]
$tipoPet = $_POST['tipo'];            // show | pdf | xlsx

list($fechaInicio, $fechaFin) = $datos[0];
list($idBoveda)  = $datos[1];

if ($fechaInicio > $fechaFin) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha inicial no puede ser mayor a la final']);
    exit;
}
if ($fechaFin > $hoy) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha final no puede ser posterior a hoy']);
    exit;
}

/* ============================== 6. QUERY ============================== */
if ($idBoveda == "0") {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccione una bóveda']);
    exit;
}
$where  = "";
$params = [$fechaInicio, $fechaFin, $idBoveda];

$sql = "SELECT bov.nombre,mov.tipo,mov.monto,mov.fecha,mov.concepto,mov.numdoc AS referencia
            FROM bov_bovedas bov
            INNER JOIN bov_movimientos mov ON mov.id_boveda=bov.id
            WHERE mov.fecha BETWEEN ? AND ? AND bov.estado='1' AND mov.estado='1' AND mov.id_boveda=? 
            {$where}
            ORDER BY mov.fecha, mov.id;";

/* ============================== 7. EXECUTE ==============================*/
$showmensaje = false;
try {
    $database->openConnection();
    $rows = $database->getAllResults($sql, $params);
    if (empty($rows)) {
        $showmensaje = true;
        throw new Exception('No se encontraron datos con los parámetros indicados');
    }

    $saldoAnterior = $database->getAllResults(
        "SELECT COALESCE(SUM(
                CASE 
                    WHEN m.tipo = 'entrada' OR m.tipo = 'inicial' THEN m.monto
                    WHEN m.tipo = 'salida' THEN -m.monto
                    ELSE 0
                END
            ), 0) AS saldo
        FROM bov_movimientos m WHERE m.estado='1' AND m.fecha<? AND m.id_boveda=?;",
        [$fechaInicio, $idBoveda]
    );

    $montoSaldoAnterior = $saldoAnterior[0]['saldo'] ?? 0;

    $info = $database->getAllResults("
        SELECT * FROM {$db_name_general}.info_coperativa ins
        JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop
        WHERE ag.id_agencia=?", [$agenciaSesion]);
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception('No se encontró información de la institución');
    }
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

/* ============================== 8. CÁLCULOS ============================*/
$saldo = $montoSaldoAnterior;
$totIng = 0;
$totEgr = 0;
foreach ($rows as &$r) {
    $ing = 0;
    $egr = 0;
    switch ($r['tipo']) {
        case 'entrada':
        case 'inicial':
            $ing = $r['monto'];
            break;
        case 'salida':
            $egr = $r['monto'];
            break;
    }
    $saldo += $ing - $egr;
    $totIng += $ing;
    $totEgr += $egr;
    $r['ingreso'] = $ing;
    $r['egreso'] = $egr;
    $r['saldo'] = $saldo;
}

/* ============================== 9. RESPONDER ========================== */
$titulo = 'DEL ' . setdatefrench($fechaInicio) . ' AL ' . setdatefrench($fechaFin);

switch ($tipoPet) {
    case 'show':
        // jsonOut($rows);
        break;
    case 'pdf':
        pdfOut(
            $rows,
            $titulo,
            $idusuario,
            $info,
            $totIng,
            $totEgr,
            $saldo
        );
        break;
    case 'xlsx':
        xlsOut($rows, $titulo, $totIng, $totEgr, $saldo);
        break;
}

/* -------------------- JSON --------------------*/
// function jsonOut(array $rows)
// {
//     echo json_encode([
//         'status' => 1,
//         'mensaje' => 'ok',
//         'data' => $rows,
//         'keys' => ['fecha', 'tipo', 'concepto', 'ingreso', 'egreso', 'saldo', 'cod_agenc'],
//         'encabezados' => ['FECHA', 'MOV', 'CONCEPTO', 'INGRESO', 'EGRESO', 'SALDO', 'AGENCIA']
//     ]);
// }

/* ======================= 10. PDF ======================*/
function pdfOut(
    array $rows,
    string $titulo,
    string $user,
    array $info,
    float $totIng,
    float $totEgr,
    float $saldoFin
) {
    /* Encabezado */
    $enc = [
        'institucion' => iso($info[0]['nomb_comple']),
        'direccion' => iso($info[0]['muni_lug']),
        'email' => $info[0]['emai'],
        'tel' => $info[0]['tel_1'] . ' ' . $info[0]['tel_2'],
        'nit' => $info[0]['nit'],
        'logo' => logoPath($info[0]['log_img'])
    ];
    class PDF extends FPDF
    {
        public $enc, $titulo, $user;
        function __construct($enc, $tit, $user)
        {
            parent::__construct('P', 'mm', 'Letter');
            $this->enc = $enc;
            $this->titulo = $tit;
            $this->user = $user;
        }
        function Header()
        {
            $f = 'Courier';
            $this->SetFont($f, '', 7);
            $this->Cell(0, 2, date('Y-m-d H:i:s'), 0, 1, 'R');
            $this->Cell(0, 2, $this->user, 0, 1, 'R');
            if (is_file($this->enc['logo'])) $this->Image($this->enc['logo'], 11, 14, 30);
            $this->SetFont($f, 'B', 9);
            $this->Cell(0, 4, $this->enc['institucion'], 0, 1, 'C');
            $this->Cell(0, 3, $this->enc['direccion'], 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->enc['email'], 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->enc['tel'], 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->enc['nit'], 'B', 1, 'C');
            $this->Ln(4);
            $this->SetFont($f, 'B', 11);
            $this->SetFillColor(185, 210, 255);
            $txt = 'ESTADO DE CUENTA BOVEDA ' . $this->titulo;
            // if ($this->all) $txt .= ' · TODAS LAS AGENCIAS';
            $this->Cell(0, 7, $txt, 0, 1, 'C', true);
            $this->SetFont($f, 'B', 8);
            $this->SetFillColor(230, 230, 230);
            $w = [18, 13, 75, 15, 18, 18, 18, 0];
            foreach (['FECHA', 'MOV', 'CONCEPTO', 'NUMDOC', 'INGRESO', 'EGRESO', 'SALDO', 'BOVEDA'] as $i => $h)
                $this->Cell($w[$i], 6, $h, 'B', 0, $i >= 3 && $i <= 5 ? 'R' : 'C');
            $this->Ln();
        }
        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Página ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($enc, $titulo, $user);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Courier', '', 8);
    $w = [18, 13, 75, 15, 20, 20, 20, 0];
    foreach ($rows as $r) {
        $pdf->Cell($w[0], 5, date('d-m-Y', strtotime($r['fecha'])), 0, 0, 'L');
        $pdf->Cell($w[1], 5, strtoupper($r['tipo']), 0, 0, 'C');
        $pdf->Cell($w[2], 5, iso($r['concepto']), 0, 0, 'L');
        $pdf->Cell($w[3], 5, $r['referencia'], 0, 0, 'C');
        $pdf->Cell($w[4], 5, $r['ingreso'] ? number_format($r['ingreso'], 2, '.', ',') : ' ', 0, 0, 'R');
        $pdf->Cell($w[5], 5, $r['egreso'] ? number_format($r['egreso'], 2, '.', ',') : ' ', 0, 0, 'R');
        $pdf->Cell($w[6], 5, number_format($r['saldo'], 2, '.', ','), 0, 0, 'R');
        $pdf->Cell($w[7], 5,  iso($r['nombre']), 0, 1, 'L');
    }
    /* Totales */
    $pdf->Ln(2);
    $pdf->SetFont('Courier', 'B', 9);
    $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], 6, 'TOTALES', 0, 0, 'R');
    $pdf->Cell($w[4], 6, number_format($totIng, 2, '.', ','), 0, 0, 'R');
    $pdf->Cell($w[5], 6, number_format($totEgr, 2, '.', ','), 0, 0, 'R');
    $pdf->Cell($w[6], 6, number_format($saldoFin, 2, '.', ','), 0, 1, 'R');
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_clean();
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado',
        'namefile' => 'Estado_boveda',
        'tipo' => 'pdf',
        'data' => 'data:application/pdf;base64,' . base64_encode($pdfData)
    ]);
}

/* ======================= 11. EXCEL ======================*/
function xlsOut(array $rows, string $tit, float $totIng, float $totEgr, float $saldoFin)
{
    $xl = new Spreadsheet();
    $xl->getProperties()->setCreator('MICROSYSTEM')->setTitle('Estado de cuenta');
    $sh = $xl->getActiveSheet();
    $sh->setTitle('EstadoBoveda');
    $head = ['FECHA', 'MOV', 'CONCEPTO', 'INGRESO', 'EGRESO', 'SALDO', 'BOVEDA', 'REFERENCIA'];
    $sh->fromArray($head, null, 'A1');
    $sh->getStyle('A1:H1')->getFont()->setBold(true);
    $r = 2;
    foreach ($rows as $row) {
        $sh->fromArray([
            date('d-m-Y', strtotime($row['fecha'])),
            strtoupper($row['tipo']),
            $row['concepto'],
            $row['ingreso'] ?: '',
            $row['egreso'] ?: '',
            $row['saldo'],
            $row['nombre'],
            $row['referencia']
        ], null, 'A' . $r);
        foreach (['D', 'E', 'F'] as $col)
            $sh->getStyle($col . $r)->getNumberFormat()->setFormatCode('#,##0.00');
        $r++;
    }
    /* Totales */
    $sh->setCellValue('C' . $r, 'TOTALES');
    $sh->setCellValue('D' . $r, $totIng);
    $sh->setCellValue('E' . $r, $totEgr);
    $sh->setCellValue('F' . $r, $saldoFin);
    foreach (['D', 'E', 'F'] as $col)
        $sh->getStyle($col . $r)->getNumberFormat()->setFormatCode('#,##0.00');
    foreach (range('A', 'G') as $col) $sh->getColumnDimension($col)->setAutoSize(true);

    ob_start();
    (new Xlsx($xl))->save('php://output');
    $xlsData = ob_get_clean();
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado',
        'namefile' => 'Estado_boveda',
        'tipo' => 'vnd.ms-excel',
        'data' => 'data:application/vnd.ms-excel;base64,' . base64_encode($xlsData)
    ]);
}
