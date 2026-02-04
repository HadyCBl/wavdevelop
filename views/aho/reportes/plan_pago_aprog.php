<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
    exit;
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

require_once __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$database->openConnection();
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../includes/Config/model/ahorros/CalculadoraPagos.php';
require_once __DIR__ . '/../../../fpdf/fpdf.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


$datos = $_POST['datosval'];
$inputs = $datos[0] ?? [];
$selects = $datos[1] ?? [];
$extras = $datos[3] ?? [];
$tipo = $_POST['tipo'];


if (!empty($extras) && !empty($extras[1])) {
    $rows = json_decode($extras[1], true) ?? [];
    $tablaPagos = [];
    $saldo = 0;
    foreach ($rows as $r) {
        $monto = floatval($r[4]);
        $saldo += $monto;
        $tablaPagos[] = [
            'no' => $r[0],
            'fecha' => $r[1],
            'deposito' => $monto,
            'saldo' => $saldo
        ];
    }
} elseif (!empty($extras) && !empty($extras[0])) {
    $cuenta = $extras[0];
    $tablaPagos = [];
    $rows = $database->getAllResults(
        "SELECT nrocuo, fecven, monto FROM ahomppg WHERE ccodaho=? ORDER BY nrocuo",
        [$cuenta]
    );
    if (empty($rows)) {
        echo json_encode(['status' => 0, 'mensaje' => 'No se encontró el plan de pagos']);
        return;
    }
    $saldo = 0;
    foreach ($rows as $r) {
        $saldo += $r['monto'];
        $tablaPagos[] = [
            'no' => $r['nrocuo'],
            'fecha' => DateTime::createFromFormat('Y-m-d', $r['fecven'])->format('d/m/Y'),
            'deposito' => $r['monto'],
            'saldo' => $saldo
        ];
    }
} else {
    list(, $fechaInicio, $tasa, $monto, $plazo,) = $inputs;
    list(, $tipoAhorro,, $frecuencia,) = $selects;

    $periodos = intval($plazo / ($frecuencia / 30));
    $cuota = ($tipoAhorro === '1') ? ($monto / $periodos) : $monto;


    $calculadora = new CalculadoraPagos($cuota, 0, $tasa, $fechaInicio, $frecuencia, $plazo);
    $calculadora->calcularPagos();
    $tablaPagos = $calculadora->obtenerTablaPagos();
}

$info = $database->getAllResults(
    "SELECT * FROM " . $db_name_general . ".info_coperativa ins
        INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop
        WHERE ag.id_agencia=?",
    [$_SESSION['id_agencia']]
);
$database->closeConnection();
if (empty($info)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}

switch ($tipo) {
    case 'pdf':
        printpdf($tablaPagos, $info);
        break;
    case 'xlsx':
        printxls($tablaPagos);
        break;
}

function printpdf($tabla, $info)
{
    $oficina = decode_utf8($info[0]['nom_agencia']);
    $institucion = decode_utf8($info[0]['nomb_comple']);
    $direccionins = decode_utf8($info[0]['muni_lug']);
    $emailins = $info[0]['emai'];
    $telefonosins = $info[0]['tel_1'] . ' ' . $info[0]['tel_2'];
    $nitins = $info[0]['nit'];
    $rutalogomicro = '../../../includes/img/logomicro.png';
    $rutalogoins = '../../..' . $info[0]['log_img'];

    class PDF extends FPDF
    {
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public function __construct($institucion, $pathlogo, $pathlogoins, $direccion, $email, $telefono, $nit)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
        }
        function Header()
        {
            $fuente = 'Courier';
            $hoy = date('Y-m-d H:i:s');
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Image($this->pathlogoins, 10, 13, 33);
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->Ln(10);
            $this->SetFont($fuente, 'B', 10);
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'PLAN DE PAGO AHORRO PROGRAMADO', 0, 1, 'C', true);
            $this->Ln(2);
            $this->SetFont($fuente, 'B', 8);
            $this->Cell(20, 5, '#', 'B', 0, 'C');
            $this->Cell(35, 5, 'FECHA', 'B', 0, 'C');
            $this->Cell(35, 5, 'DEPOSITO', 'B', 0, 'C');
            $this->Cell(35, 5, 'SALDO', 'B', 1, 'C');
        }
        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $direccionins, $emailins, $telefonosins, $nitins);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Courier', '', 8);
    foreach ($tabla as $fila) {
        $pdf->Cell(20, 5, $fila['no'], 0, 0, 'C');
        $pdf->Cell(35, 5, $fila['fecha'], 0, 0, 'C');
        $pdf->Cell(35, 5, number_format($fila['deposito'], 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(35, 5, number_format($fila['saldo'], 2, '.', ','), 0, 1, 'R');
    }
    $pdf->Ln(2);
    $pdf->SetFont('Courier', 'B', 8);
    $total = $tabla[count($tabla) - 1]['saldo'];
    $pdf->Cell(90, 5, 'SALDO FINAL', 0, 0, 'R');
    $pdf->Cell(35, 5, number_format($total, 2, '.', ','), 0, 1, 'R');
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => 'Plan_Ahorro_Programado',
        'tipo' => 'pdf',
        'data' => 'data:application/pdf;base64,' . base64_encode($pdfData)
    ]);
}

function printxls($tabla)
{
    $fuente = 'Courier';
    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle('PlanPagos');
    $encabezado = ['#', 'FECHA', 'DEPOSITO', 'SALDO'];
    $activa->fromArray($encabezado, null, 'A1')->getStyle('A1:D1')->getFont()->setName($fuente)->setBold(true);
    $i = 2;
    foreach ($tabla as $fila) {
        $activa->setCellValue('A' . $i, $fila['no']);
        $activa->setCellValue('B' . $i, $fila['fecha']);
        $activa->setCellValue('C' . $i, $fila['deposito']);
        $activa->setCellValue('D' . $i, $fila['saldo']);
        $i++;
    }
    foreach (['A', 'B', 'C', 'D'] as $col) {
        $activa->getColumnDimension($col)->setAutoSize(true);
    }
    ob_start();
    $writer = IOFactory::createWriter($excel, 'Xlsx');
    $writer->save('php://output');
    $xlsData = ob_get_contents();
    ob_end_clean();
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => 'Plan_Ahorro_Programado',
        'tipo' => 'vnd.ms-excel',
        'data' => 'data:application/vnd.ms-excel;base64,' . base64_encode($xlsData)
    ]);
}
?>
