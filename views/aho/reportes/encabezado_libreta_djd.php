<?php
session_start();

include '../../../includes/Config/database.php';
include '../../../src/funcphp/func_gen.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

require '../../../fpdf/fpdf_js.php';

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$cuenta = $archivo[0];

try {
    $database->openConnection();
    $result = $database->getAllResults("SELECT cli.short_name,cli.Direccion,cli.control_interno,cli.no_identifica, cta.ccodaho,cta.ccodcli,cta.nlibreta,cta.fecha_apertura,tip.xlibreta,tip.ylibreta
    FROM ahomcta cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
    INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
     WHERE ccodaho=?", [$cuenta]);

    if (empty($result)) {
        throw new Exception("No se encontraron registros en result.");
    }
    $status = 1;
} catch (Exception $e) {
    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

$nombre = $result[0]['short_name'];
$ci = $result[0]['control_interno'];
$direccion = $result[0]['Direccion'];
$dpi = $result[0]['no_identifica'];

$codaho = $result[0]['ccodaho'];
$codcli = $result[0]['ccodcli'];

$fecha_apertura = $result[0]['fecha_apertura'];
$fecha_apertura = date("d-m-Y", strtotime($fecha_apertura));
$x = $result[0]['xlibreta'];
$y = $result[0]['ylibreta'];

class PDF_AutoPrint extends PDF_JavaScript
{
    function AutoPrint($printer = '')
    {
        // Open the print dialog
        if ($printer) {
            $printer = str_replace('\\', '\\\\', $printer);
            $script = "var pp = getPrintParams();";
            $script .= "pp.interactive = pp.constants.interactionLevel.full;";
            $script .= "pp.printerName = '$printer'";
            $script .= "print(pp);";
        } else
            $script = 'print(true);';
        $this->IncludeJS($script);
    }
}
$espacio = 4;
$espaciosegundacol = 87;

$y = $y - 7;
$x = $x - 5;
$pdf = new PDF_AutoPrint();
$pdf->AddPage();
$pdf->SetCompression(false);
$pdf->AddFont('Calibri', '', 'calibri.php');
$pdf->AddFont('Calibri', 'B', 'calibrib.php');
$pdf->SetFont('Calibri', '', 7);
$pdf->Text($x + 6, $y-2, mb_convert_encoding($nombre, 'ISO-8859-1', 'UTF-8'));
$pdf->SetFont('Calibri', '', 7);
$pdf->Text($x + 10, $y + 4.5, "  " . ($dpi));

$pdf->Text($x + 7, $y + 11.5, mb_convert_encoding($direccion, 'ISO-8859-1', 'UTF-8'));
$pdf->Text($x + 70, $y + 4.5, $codaho);
// $pdf->Text($x + 62, $y , ($fecha_apertura));

ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Impresion de encabezado generado correctamente',
    'namefile' => "Encabezado-libreta-" . $cuenta,
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData)
);
echo json_encode($opResult);