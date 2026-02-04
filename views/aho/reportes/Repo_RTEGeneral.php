<?php
session_start();
require '../../../fpdf/fpdf.php';
require "../../../vendor/autoload.php";
include __DIR__ . '/../../../includes/Config/database.php';
include __DIR__ . '/../../../src/funcphp/func_gen.php';

date_default_timezone_set('America/Guatemala');

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada']);
    return;
}

// Se espera recibir el código de cuenta (ccdocta) por GET o POST
$ccdocta = isset($_GET['ccdocta']) ? $_GET['ccdocta'] : (isset($_POST['ccdocta']) ? $_POST['ccdocta'] : null);
if (!$ccdocta) {
    echo json_encode(['status' => 0, 'mensaje' => 'No se proporcionó el código de cuenta']);
    return;
}

// Conexión a la base de datos
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$database->openConnection();

// Obtener información de la agencia e institución
$info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop WHERE ag.id_agencia=?", [$_SESSION['id_agencia']]);
if (empty($info)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institución asignada a la agencia no encontrada']);
    return;
}
$institucion   = $info[0]["nomb_comple"];
$direccion     = $info[0]["muni_lug"];
$email         = $info[0]["emai"];
$telefonos     = $info[0]["tel_1"] . ' / ' . $info[0]["tel_2"];
$nit           = $info[0]["nit"];
$rutalogoins   = "../../.." . $info[0]["log_img"];

// Consultar tb_RTE_use por ccdocta (código de cuenta) con concatenación de nombres y apellidos
$sql = "SELECT 
            Id_RTE,
            ccdocta,
            DPI,
            ori_fondos,
            desti_fondos,
            Mon,
            propietario,
            CONCAT_WS(' ', Nombre1, Nombre2, Nombre3) AS Nombre,
            CONCAT_WS(' ', Apellido1, Apellido2, Apellido_de_casada) AS Apellidos
        FROM tb_RTE_use
        WHERE ccdocta = ? AND IFNULL(Deletedate, '') = '' LIMIT 1";
$results = $database->getAllResults($sql, [$ccdocta]);
$record = (!empty($results)) ? $results[0] : null;

if (empty($record)) {
    echo json_encode(['status' => 0, 'mensaje' => 'No se encontró el registro en tb_RTE_use para la cuenta: ' . $ccdocta]);
    return;
}

// Si el registro es de propietario (propietario = 1), obtener información del cliente
if ($record['propietario'] == 1) {
    $sql2 = "SELECT 
                idcod_cliente,
                primer_name,
                segundo_name,
                tercer_name,
                primer_last,
                segundo_last,
                casada_last,
                no_identifica
             FROM tb_cliente
             WHERE idcod_cliente = ? LIMIT 1";
    $results_cliente = $database->getAllResults($sql2, [$ccdocta]);
    $cliente = (!empty($results_cliente)) ? $results_cliente[0] : null;
} else {
    $cliente = null;
}

$database->closeConnection();

// Calcular si es propietario
$esPropietario = ($record['propietario'] == 1 ? 'Si' : 'No');

// Si no se concatenó el nombre (por ejemplo, si las columnas son nulas), se pueden rellenar manualmente
$nombreCompleto = trim($record['Nombre']);
$apellidosCompleto = trim($record['Apellidos']);

// Clase personalizada para el PDF
class PDF_Report extends FPDF
{
    protected $width_page = 190;

    function Header()
    {
        global $institucion, $direccion, $email, $telefonos, $nit, $rutalogoins;

        // Logo
        if (file_exists($rutalogoins)) {
            $this->Image($rutalogoins, 10, 10, 30);
        }
        // Encabezado con información de la institución
        $this->SetFont('Times', 'B', 10);
        $this->Cell(0, 5, decode_utf8($institucion), 0, 1, 'C');
        $this->SetFont('Times', '', 8);
        $this->Cell(0, 5, decode_utf8($direccion), 0, 1, 'C');
        $this->Cell(0, 5, ('Email: ' . $email . ' | Tel: ' . $telefonos), 0, 1, 'C');
        $this->Cell(0, 5, ('NIT: ' . $nit), 'B', 1, 'C');
        $this->Ln(8);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Times', 'I', 7);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, decode_utf8('Página ') . $this->PageNo(), 0, 0, 'C');
    }

    function SectionTitle($title, $color = [220, 220, 220])
    {
        $this->SetFont('Times', 'B', 9);
        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($this->width_page, 7, decode_utf8($title), 1, 1, 'C', true);
        $this->Ln(3);
    }

    function LabelValueRow($label, $value, $labelWidth = 70, $valueWidth = 120)
    {
        $this->SetFont('Times', 'B', 9);
        $this->Cell($labelWidth, 8, decode_utf8($label), 1, 0, 'L');
        $this->SetFont('Times', '', 9);
        $this->Cell($valueWidth, 8, decode_utf8($value), 1, 1, 'L');
    }

    function CheckBoxRow($label, $checked = false, $labelWidth = 180)
    {
        $this->SetFont('Times', '', 9);
        $this->Cell(10, 8, $checked ? '[X]' : '[ ]', 1, 0, 'C');
        $this->Cell($labelWidth, 8, decode_utf8($label), 1, 1);
    }
}

$pdf = new PDF_Report();
$pdf->AddPage();

// Rellenar el reporte con datos basados en el código de cuenta
$pdf->SectionTitle('Información de la Transacción', [153, 255, 153]);
$pdf->LabelValueRow('Número de cuenta:', $record['ccdocta']);
$pdf->LabelValueRow('DPI:', $record['DPI']);
$pdf->LabelValueRow('Origen de Fondos:', $record['ori_fondos']);
$pdf->LabelValueRow('Destino de Fondos:', $record['desti_fondos']);
$pdf->LabelValueRow('Monto:', $record['Mon']);
$pdf->LabelValueRow('Propietario:', $esPropietario);

// Agregar nombre completo (si no viene concatenado en la consulta, se construye)
$pdf->LabelValueRow('Nombre Completo:', trim($nombreCompleto . ' ' . $apellidosCompleto));

// Si el registro es de propietario y se obtuvo información del cliente, agregar datos del cliente
if ($esPropietario == 'Si' && !empty($cliente)) {
    $pdf->SectionTitle('Información del Cliente', [173, 216, 230]);
    $nombreCliente = trim(
        ($cliente['primer_name'] ?? '') . ' ' .
            ($cliente['segundo_name'] ?? '') . ' ' .
            ($cliente['tercer_name'] ?? '') . ' ' .
            ($cliente['primer_last'] ?? '') . ' ' .
            ($cliente['segundo_last'] ?? '') . ' ' .
            ($cliente['casada_last'] ?? '')
    );
    $pdf->LabelValueRow('Nombre Completo:', $nombreCliente);
    $pdf->LabelValueRow('Número de identificación:', $cliente['no_identifica'] ?? '');
}

// Información adicional fija (firmas, etc.)
$pdf->Ln(5);
$pdf->SetFont('Times', 'B', 9);
$pdf->Cell(95, 10, decode_utf8('Firma de la persona que realiza la transacción'), 0, 0, 'C');
$pdf->Cell(95, 10, decode_utf8('Firma y código del empleado responsable'), 0, 1, 'C');
$pdf->Cell(95, 10, '_________________________', 0, 0, 'C');
$pdf->Cell(95, 10, '_________________________', 0, 1, 'C');

ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

echo json_encode([
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile' => "Reporte de Transaccion",
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData)
]);
