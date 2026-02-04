<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';
date_default_timezone_set('America/Guatemala');

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}

$oficina = decode_utf8($info[0]["nom_agencia"]);
$institucion = decode_utf8($info[0]["nomb_comple"]);
$direccionins = decode_utf8($info[0]["muni_lug"]);
$emailins = $info[0]["emai"];
$telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
$nitins = $info[0]["nit"];
$rutalogomicro = "../../../includes/img/logomicro.png";
$rutalogoins = "../../.." . $info[0]["log_img"];
$usuario = $_SESSION['id'];


//se crea el array que recibe los datos
$datos = array();
$datos = $_POST["data"];

//se obtienen las variables del get
$cod = $datos[5];

$fuente = "Courier";
$tamanioFuente = 9;
$tamanioTitulo = 11;
$tamanio_linea = 4; //altura de la linea/celda
$ancho_linea = 30; //anchura de la linea/celda
$espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
$ancho_linea2 = 20; //anchura de la linea/celda
$espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda

class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        $fuente = "Courier";
        $tamanio_linea = 4; //altura de la linea/celda
        $ancho_linea = 30; //anchura de la linea/celda
        $ancho_linea2 = 20; //anchura de la linea/celda

        // ACA ES DONDE EMPIEZA LO DEL FORMATO DE REPORTE---------------------------------------------------
        $hoy = date("Y-m-d H:i:s");
        //fecha y usuario que genero el reporte
        $this->SetFont($fuente, '', 8);
        $this->Cell(0, 2, $hoy, 0, 1, 'R');
        $this->Ln(4);
        //TITULO DE REPORTE
        $this->SetFillColor(255, 255, 255);
        $this->SetFont($fuente, '', 10);
        //encabezado de tabla
        $this->CellFit($ancho_linea - 5, $tamanio_linea + 1, 'CUENTA', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea - 5, $tamanio_linea + 1, 'CODIGO CLIENTE', 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $this->CellFit(($ancho_linea + 15), $tamanio_linea + 1, 'NOMBRE COMPLETO', 'B', 0, 'C', 0, '', 1, 0); //nombre

        $this->CellFit($ancho_linea - 15, $tamanio_linea + 1, 'TIPOPE', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea - 12, $tamanio_linea + 1, 'FECHA', 'B', 0, 'C', 0, '', 1, 0); //

        $this->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, 'NUMDOC', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, 'MONTO', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, 'SALDO', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea2 - 8, $tamanio_linea + 1, 'DIAS', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea2 - 8, $tamanio_linea + 1, 'TASA', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea2, $tamanio_linea + 1, 'INTERES', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea2, $tamanio_linea + 1, 'ISR', 'B', 0, 'C', 0, '', 1, 0); //
        $this->CellFit($ancho_linea2 + 4, $tamanio_linea + 1, 'TIPOCUENTA', 'B', 0, 'C', 0, '', 1, 0); //
        $this->Ln(6);
    }

    // Pie de página
    function Footer()
    {
        // Posición: a 1 cm del final
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
//aqui empieza el llenado de registros
//consulta a la bd
$consulta = "SELECT apin.ccodaport,apin.nomcli,apin.codcli,apin.tipope,apin.fecope,apin.numdoc,apin.tipdoc,apin.monto,apin.saldo,apin.saldoant,apin.dias,apin.tasa,apin.intcal,apin.isrcal,tip.nombre 
FROM aprintere AS apin 
INNER JOIN aprtip AS tip ON SUBSTR(apin.ccodaport,7,2)=tip.ccodtip 
WHERE apin.idcalc=" . $cod . " 
ORDER BY apin.idreg";

$data = mysqli_query($conexion, $consulta);
cargar_datos($pdf, $data, $ancho_linea, $ancho_linea2, $tamanio_linea);

//METODO DE DESCARGA DEL ARCHIVO
ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'data' => "data:application/vnd.ms-word;base64," . base64_encode($pdfData)
);
mysqli_close($conexion);
echo json_encode($opResult);

//Procedimiento para cargar los datos en el archivo pdf
function cargar_datos($pdf, $data, $ancho_linea, $ancho_linea2, $tamanio_linea)
{
    while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
        $cuenta = ($rowdata["ccodaport"]);
        $codcli = ($rowdata["codcli"]);
        $nombre = encode_utf8($rowdata["nomcli"]);
        $tipope = ($rowdata["tipope"]);
        $fecha = date("d-m-Y", strtotime(($rowdata["fecope"])));
        $numdoc = ($rowdata["numdoc"]);
        $tipdoc = ($rowdata["tipdoc"]);
        $monto = ($rowdata["monto"]);
        $saldo = ($rowdata["saldo"]);
        $saldoant = ($rowdata["saldoant"]);
        $dias = ($rowdata["dias"]);
        $tasa = ($rowdata["tasa"]);
        $interes = ($rowdata["intcal"]);
        $isr = ($rowdata["isrcal"]);
        $tipocuenta = encode_utf8($rowdata["nombre"]);

        //se insertan los registros
        $pdf->CellFit($ancho_linea - 5, $tamanio_linea + 1, $cuenta, 'B', 0, 'C', 0, '', 1, 0); //cuenta
        $pdf->CellFit($ancho_linea - 5, $tamanio_linea + 1, $codcli, 'B', 0, 'C', 0, '', 1, 0); // codigo cliente
        $pdf->CellFit(($ancho_linea + 15), $tamanio_linea + 1, $nombre, 'B', 0, 'C', 0, '', 1, 0); //nombre completo
        $pdf->CellFit($ancho_linea - 15, $tamanio_linea + 1, $tipope, 'B', 0, 'C', 0, '', 1, 0); //tipope
        $pdf->CellFit($ancho_linea - 12, $tamanio_linea + 1, $fecha, 'B', 0, 'C', 0, '', 1, 0); //fecha
        $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1,  $numdoc, 'B', 0, 'C', 0, '', 1, 0); //numdoc
        $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, $monto, 'B', 0, 'C', 0, '', 1, 0); //monto
        $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, $saldo, 'B', 0, 'C', 0, '', 1, 0); //saldo
        $pdf->CellFit($ancho_linea2 - 8, $tamanio_linea + 1, $dias, 'B', 0, 'C', 0, '', 1, 0); //dias
        $pdf->CellFit($ancho_linea2 - 8, $tamanio_linea + 1, $tasa, 'B', 0, 'C', 0, '', 1, 0); //tasa
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, round($interes, 2), 'B', 0, 'C', 0, '', 1, 0); //interes
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, round($isr, 2), 'B', 0, 'C', 0, '', 1, 0); //isr
        $pdf->CellFit($ancho_linea2 + 4, $tamanio_linea + 1, $tipocuenta, 'B', 0, 'C', 0, '', 1, 0); //tipcuenta
        $pdf->Ln(6);
    }
}
