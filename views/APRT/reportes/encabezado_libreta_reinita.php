<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include __DIR__ . '/../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
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

$bandera = false;
$codaport = $archivo[0];

$datoscli = mysqli_query($conexion, "SELECT * FROM `aprcta` WHERE `ccodaport`=$codaport");
while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
    $idcli = $da["ccodcli"];
    $nit = $da["num_nit"];
    $fecha_apertura = $da["fecha_apertura"];
    $bandera = true;
}

if (!$bandera) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron datos, no se ha seleccionado un cliente o bien no existe su cuenta',
        'dato' => '0'
    );
    echo json_encode($opResult);
    return;
}

//se busca al cliente para extraer su nombre
$dpi = "";
$data = mysqli_query($conexion, "SELECT `short_name`,`Direccion`, `no_identifica` FROM `tb_cliente` WHERE `idcod_cliente` = '$idcli'");
while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
    $nombre = $dat["short_name"];
    $direccion = $dat["Direccion"];
    if ($direccion == "") {
        $direccion = "Sin dirección";
    }
    $dpi = ($dat["no_identifica"]);
}

$tip_aport = substr($codaport, 6, -6);  // devuelve posicion de libreta
$data_coordenadas = mysqli_query($conexion, "SELECT `nombre`,`xlibreta`,`ylibreta`,ccodtip FROM `aprtip` WHERE `ccodtip` = '$tip_aport'");
while ($dat = mysqli_fetch_array($data_coordenadas, MYSQLI_ASSOC)) {
    $x = $dat["xlibreta"];
    $y = $dat["ylibreta"];
    if ($x == "" || $x == null || $y == "" || $y == null) {
        $x = "-";
        $y = "-";
    }
    $ccodtip = $dat["ccodtip"];
}

$nombre_benef = "Sin beneficiario"; // valor por defecto
$databenef = mysqli_query($conexion, "SELECT `nombre` FROM `aprben` WHERE `codaport` = '$codaport'");
if (mysqli_num_rows($databenef) > 0) {
    while ($datb = mysqli_fetch_array($databenef, MYSQLI_ASSOC)) {
        $nombre_benef = $datb["nombre"];
        if ($nombre_benef == "" || $nombre_benef == null) {
            $nombre_benef = "Sin beneficiario";
        }
    }
}

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
$espaciosegundacol = 88;
$pdf = new PDF_AutoPrint();
$pdf->AddPage();
$pdf->SetCompression(false);
$pdf->AddFont('Calibri', '', 'calibri.php');
$pdf->AddFont('Calibri', 'B', 'calibrib.php');
$pdf->SetFont('Calibri', '', 10);

if ($ccodtip == 25) {
    $pdf->Text($x, $y, mb_convert_encoding($nombre, 'ISO-8859-1', 'UTF-8'));
    $pdf->Text($x + 5, $y + 4, $nombre_benef);
    $pdf->Text($x - 27, $y + 13, $cuenta);
} else {
    $pdf->Text($x, $y, mb_convert_encoding($nombre, 'ISO-8859-1', 'UTF-8'));
    $pdf->Text($x + 30, $y + 5, $cuenta);
}


// $fecha_apertura = date("d-m-Y", strtotime($fecha_apertura));
// $pdf->Text($x + $espaciosegundacol, $y + $espacio, $dpi);
// $pdf->Text($x + $espaciosegundacol, $y, $fecha_apertura);

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
