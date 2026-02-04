<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
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

// $cuenta=$_GET["cod"];
$datoscli = mysqli_query($conexion, "SELECT * FROM `ahomcta` WHERE `ccodaho`='$cuenta'");
$bandera = false;

while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
    $idcli = ($da["ccodcli"]);
    $nit = $da["num_nit"];
    $nlibreta = ($da["nlibreta"]);
    $fecha_apertura = ($da["fecha_apertura"]);
    $tasa_interes = $da["tasa"];
    $plazo = $da["plazo"];
    $bandera = true;
}

//COMPROBACION: SI SE ENCONTRARON REGISTROS
if (!$bandera) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron datos, no se ha seleccionado un cliente o bien no existe su cuenta',
        'dato' => '0'
    );
    echo json_encode($opResult);
    return;
}

$data = mysqli_query($conexion, "SELECT `short_name`, `no_identifica`, `Direccion`, `tel_no1`, `no_tributaria` FROM `tb_cliente` WHERE `idcod_cliente` = '$idcli'");
//$data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente`='$idcli' OR `no_tributaria` = '$nit'");
$dat = mysqli_fetch_array($data, MYSQLI_ASSOC);
$nombre = ($dat["short_name"]);
$dpi = ($dat["no_identifica"]);
$direccion = ($dat["Direccion"]);
$telefono = ($dat["tel_no1"]);
$nit_cliente = ($dat["no_tributaria"]);

$tip = substr($cuenta, 6, 2);
$queryxy = mysqli_query($conexion, "SELECT `tipcuen`,`xlibreta`,`ylibreta` FROM `ahomtip` WHERE `ccodtip`='$tip'");
$xy = mysqli_fetch_array($queryxy, MYSQLI_ASSOC);
$x = ($xy["xlibreta"]);
$y = ($xy["ylibreta"]);
$tipcuen = $xy["tipcuen"];

mysqli_close($conexion);
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
$pdf = new PDF_AutoPrint();
$pdf->AddPage();
$pdf->SetCompression(false);
$pdf->AddFont('Calibri', '', 'calibri.php');
$pdf->AddFont('Calibri', 'B', 'calibrib.php');
$pdf->SetFont('Calibri', '', 9);

if ($tipcuen == "pr" || $tipcuen == "pf") {
    $pdf->Text($x, $y, $cuenta);
    $pdf->Text($x + $espaciosegundacol + 30, $y, ($tasa_interes) . "%");
    $pdf->Text($x, $y + $espacio * 2, mb_convert_encoding($nombre, 'ISO-8859-1', 'UTF-8'));
    $pdf->Text($x + $espaciosegundacol + 24, $y + $espacio * 2, $dpi);
    $pdf->Text($x, $y + $espacio * 4, $direccion);
    $pdf->Text($x + 5, $y + $espacio * 6, $telefono);
    $pdf->Text($x + $espaciosegundacol - 26, $y + $espacio * 6, $nit_cliente);
    $pdf->Text($x + 5, $y + $espacio * 8 - 1, $plazo);
    if ($tipcuen == "pf") {
        $pdf->Text($x + 5, $y + $espacio * 10 - 1, "SI");
    }
} else {
    $pdf->Text($x, $y, $cuenta);
    $pdf->Text($x + $espaciosegundacol + 30, $y, ($tasa_interes) . "%");
    $pdf->Text($x, $y + $espacio * 2, mb_convert_encoding($nombre, 'ISO-8859-1', 'UTF-8'));
    $pdf->Text($x + $espaciosegundacol + 24, $y + $espacio * 2, $dpi);
    $pdf->Text($x, $y + $espacio * 4, mb_convert_encoding($direccion, 'ISO-8859-1', 'UTF-8'));
    $pdf->Text($x + 5, $y + $espacio * 6, $telefono);
    $pdf->Text($x + $espaciosegundacol - 26, $y + $espacio * 6, $nit_cliente);
}


// $pdf->Text($x + $espaciosegundacol, $y+ $espacio, $fecha_apertura);
// $pdf->AutoPrint();
// $pdf->Output();
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
