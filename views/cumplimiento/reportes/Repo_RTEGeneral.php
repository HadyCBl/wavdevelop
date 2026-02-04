<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada']);
    exit;
}

require_once __DIR__ . '/../../../fpdf/fpdf.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

include __DIR__ . '/../../../includes/Config/database.php';
include __DIR__ . '/../../../src/funcphp/func_gen.php';

date_default_timezone_set('America/Guatemala');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '120');

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

$datos   = $_POST['datosval'] ?? [];
$tipoPet = $_POST['tipo'] ?? 'pdf';
$inputs  = $datos[0] ?? [];
$selects = $datos[1] ?? [];
$radios  = $datos[2] ?? [];

$finicio = $inputs[0] ?? '';
$ffin    = $inputs[1] ?? '';
$agencia = $selects[0] ?? '';

if (!validateDate($finicio, 'Y-m-d') || !validateDate($ffin, 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    exit;
}
if ($finicio > $ffin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas incorrecto']);
    exit;
}

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$showmensaje = false;
try {
    $database->openConnection();
    $params = [$finicio, $ffin];
    $sql = "SELECT r.Id_RTE,
                   r.ccdocta,
                   CONCAT_WS(' ', r.Nombre1, r.Nombre2, r.Nombre3,
                                r.Apellido1, r.Apellido2, r.Apellido_de_casada) AS nombre,
                   r.DPI,
                   r.ori_fondos,
                   r.desti_fondos,
                   r.Mon,
                   r.propietario,
                   r.Cretadate,
                   c.agencia
            FROM tb_RTE_use r
            LEFT JOIN tb_cliente c ON r.ccdocta = c.no_identifica
            WHERE (r.Deletedate IS NULL OR r.Deletedate='')
              AND DATE(r.Cretadate) BETWEEN ? AND ?";
    if (!empty($agencia) && $agencia !== 'Consolidado') {
        $sql .= " AND c.agencia = ?";
        $params[] = $agencia;
    }
    $sql .= " ORDER BY r.Cretadate, r.Id_RTE";

    $result = $database->getAllResults($sql, $params);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception('No se encontraron registros');
    }
    foreach ($result as &$row) {
        $row['desti_fondos'] = trim($row['desti_fondos']);
        $row['ori_fondos']   = trim($row['ori_fondos']);
        if (preg_match('/(.*[^\d])([\d,\.]+)$/', $row['desti_fondos'], $m)) {
            $row['desti_fondos'] = trim($m[1]);
            $row['Mon']          = (float)str_replace(',', '', $m[2]);
        }
        $row['Mon'] = number_format((float)$row['Mon'], 2, '.', '');
    }
    unset($row);

    $info = $database->getAllResults(
        "SELECT * FROM {$db_name_general}.info_coperativa ins
         INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop
         WHERE ag.id_agencia = ? LIMIT 1",
        [$_SESSION['id_agencia']]
    );
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception('Institucion asignada a la agencia no encontrada');
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores(
            $e->getMessage(),
            __FILE__,
            __LINE__,
            $e->getFile(),
            $e->getLine()
        );
    }
    $mensaje = $showmensaje
        ? $e->getMessage()
        : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    echo json_encode(['status' => 0, 'mensaje' => $mensaje]);
    exit;
}

class PDF_Listado extends FPDF
{
    public $institucion;
    public $direccion;
    public $email;
    public $telefonos;
    public $nit;
    public $rutalogoins;
    public $filtros;

    function Header()
    {
        // logo + encabezados de institución
        if (!empty($this->rutalogoins) && file_exists($this->rutalogoins)) {
            $this->Image($this->rutalogoins, 10, 8, 25);
        }
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, decode_utf8($this->institucion), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, decode_utf8($this->direccion), 0, 1, 'C');
        $this->Cell(0, 5, decode_utf8("Email: {$this->email} | Tel: {$this->telefonos}"), 0, 1, 'C');
        $this->Cell(0, 5, decode_utf8("NIT: {$this->nit}"), 'B', 1, 'C');
        $this->Ln(3);

        // filtros y título
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, decode_utf8("Filtros aplicados: {$this->filtros}"), 0, 1, 'L');
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, 'LISTADO GENERAL DE RTE', 0, 1, 'C');
        $this->Ln(3);

        // encabezados de tabla con tamaños reducidos
        $h = 5;
        $this->SetFont('Arial', 'B', 7);
        $w = [8, 20, 70, 18, 40, 40, 25, 25];
        $titles = ['ID', 'Cuenta', 'Nombre', 'DPI', 'Origen', 'Destino', 'Monto', 'Fecha'];
        foreach ($titles as $i => $t) {
            $this->Cell($w[$i], $h, decode_utf8($t), 1, $i === 7 ? 1 : 0, 'C');
        }
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

if ($tipoPet === 'show') {
    echo json_encode([
        'status'      => 1,
        'mensaje'     => 'ok',
        'data'        => $result,
        'keys'        => ['Id_RTE', 'ccdocta', 'nombre', 'DPI', 'ori_fondos', 'desti_fondos', 'Mon', 'Cretadate', 'agencia'],
        'encabezados' => ['ID', 'Cuenta', 'Nombre', 'DPI', 'Origen', 'Destino', 'Monto', 'Fecha', 'Agencia']
    ]);
    exit;
}

if ($tipoPet === 'xlsx') {
    // … deja intacta tu lógica de Excel …
    // (no afecta al PDF)
}

// ——— PDF final ———
$pdf = new PDF_Listado('L', 'mm', 'A4');
// Márgenes más ajustados
$pdf->SetMargins(8, 10, 8);
$pdf->SetAutoPageBreak(true, 8);

$pdf->institucion  = $info[0]['nomb_comple'];
$pdf->direccion    = $info[0]['muni_lug'];
$pdf->email        = $info[0]['emai'];
$pdf->telefonos    = $info[0]['tel_1'] . ' / ' . $info[0]['tel_2'];
$pdf->nit          = $info[0]['nit'];
$pdf->rutalogoins  = '../../..' . $info[0]['log_img'];
$pdf->filtros      = 'Del ' . date('d/m/Y', strtotime($finicio)) .
                     ' al ' . date('d/m/Y', strtotime($ffin)) .
                     (!empty($agencia) ? " | Agencia: $agencia" : '');

$pdf->AliasNbPages();
$pdf->AddPage();

// filas de datos con fuente y celdas reducidas
foreach ($result as $row) {
    $h = 4;                  // altura de celda un poco más baja
    $pdf->SetFont('Arial', '', 6); // fuente pequeña para que quepa todo

    $pdf->Cell( 8, $h, $row['Id_RTE'], 1, 0, 'C');
    $pdf->Cell(20, $h, $row['ccdocta'], 1, 0, 'L');
    $pdf->Cell(70, $h, decode_utf8(substr($row['nombre'], 0, 50)), 1, 0, 'L');
    $pdf->Cell(18, $h, $row['DPI'], 1, 0, 'L');
    $pdf->Cell(40, $h, decode_utf8(substr($row['ori_fondos'], 0, 35)), 1, 0, 'L');
    $pdf->Cell(40, $h, decode_utf8(substr($row['desti_fondos'], 0, 35)), 1, 0, 'L');
    $pdf->Cell(25, $h, number_format($row['Mon'], 2, '.', ','), 1, 0, 'R');
    $pdf->Cell(25, $h, date('d-m-Y H:i', strtotime($row['Cretadate'])), 1, 1, 'L');
}

ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

echo json_encode([
    'status'   => 1,
    'mensaje'  => 'Reporte generado correctamente',
    'namefile' => 'rte_general_' . date('Ymd_His'),
    'tipo'     => 'pdf',
    'data'     => 'data:application/pdf;base64,' . base64_encode($pdfData)
]);
exit;
