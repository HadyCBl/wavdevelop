<?php

use Micro\Generic\Utf8;

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
    $idcli = utf8_encode($da["ccodcli"]);
    $nit = utf8_encode($da["num_nit"]);
    $nlibreta = utf8_encode($da["nlibreta"]);
    $fecha_apertura = utf8_encode($da["fecha_apertura"]);
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
$dpi="";
$data = mysqli_query($conexion, "SELECT `short_name`,`Direccion`, `no_identifica` FROM `tb_cliente` WHERE `idcod_cliente` = '$idcli'");
while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
    $nombre = Utf8::decode($dat["short_name"]);
    $direccion = Utf8::decode($dat["Direccion"]);
    if ($direccion == "") {
        $direccion = "Sin dirección";
    }
    $dpi = ($dat["no_identifica"]);
}

$tip_aport = substr($codaport, 6, -6);  // devuelve posicion de libreta
$data_coordenadas = mysqli_query($conexion, "SELECT `xlibreta`,`ylibreta` FROM `aprtip` WHERE `ccodtip` = '$tip_aport'");
while ($dat = mysqli_fetch_array($data_coordenadas, MYSQLI_ASSOC)) {
    $x = $dat["xlibreta"];
    $y = $dat["ylibreta"];
    if ($x == "" || $x == null || $y == "" || $y == null) {
        $x = "-";
        $y = "-";
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
$pdf->SetFont('Arial', '', 10);
$pdf->Text($x + 75, $y, "Libreta No. " . $nlibreta);
$pdf->Text($x - 5, $y + 14 , $cuenta);
$pdf->Text($x - 5, $y + 19, $nombre);
$fecha_apertura = date("d-m-Y", strtotime($fecha_apertura));
$pdf->Text($x - 5, $y + 24, $fecha_apertura);
$pdf->Text($x + 80, $y + 14, $dpi);
$pdf->Text($x + 80, $y + 24, $idcli);

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
