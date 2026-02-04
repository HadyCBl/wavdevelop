<?php
session_start();
include '../../../src/funcphp/func_gen.php';
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';

date_default_timezone_set('America/Guatemala');

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$tipo = $_POST["tipo"];
$datos = $_POST["datosval"];
$id = $datos[3][0];

//-----------------
$querypol = mysqli_query($conexion, "SELECT tp.descripcion tipopol, dia.id,mov.id_fuente_fondo, dia.numcom,mov.id_ctb_nomenclatura,cue.ccodcta,cue.cdescrip, mov.debe,mov.haber,dia.glosa,dia.feccnt,dia.fecdoc,dia.id_tb_usu FROM ctb_mov AS mov 
INNER JOIN ctb_diario AS dia ON mov.id_ctb_diario = dia.id 
INNER JOIN ctb_nomenclatura AS cue ON cue.id=mov.id_ctb_nomenclatura 
INNER JOIN $db_name_general.ctb_tipo_poliza AS tp ON tp.id=dia.id_ctb_tipopoliza
WHERE dia.id=$id and dia.estado=1 ORDER BY mov.haber,mov.id_ctb_nomenclatura");
$j = 0;
while ($fil = mysqli_fetch_array($querypol)) {
    $ctbmovdata[$j] = $fil;
    $j++;
}
//institucion
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}

mysqli_close($conexion);

/* $oficina = "Coban";
$institucion = "Cooperativa Integral De Ahorro y credito Imperial";
$direccionins = "Canton vipila zona 1";
$emailins = "fape@gmail.com";
$telefonosins = "502 43987876";
$nitins = "1323244234";
$rutalogomicro = "../../../includes/img/logomicro.png";
$rutalogoins = "../../../includes/img/fape.jpeg"; */

$oficina = decode_utf8($info[0]["nom_agencia"]);
$institucion = decode_utf8($info[0]["nomb_comple"]);
$direccionins = decode_utf8($info[0]["muni_lug"]);
$emailins = decode_utf8($info[0]["emai"]);
$telefonosins = decode_utf8($info[0]["tel_1"]) . '   ' . decode_utf8($info[0]["tel_2"]);
$nitins = decode_utf8($info[0]["nit"]);
$rutalogomicro = "../../../includes/img/logomicro.png";
$rutalogoins = "../../.." . $info[0]["log_img"];

//lo que se tiene que repetir en cada una de las hojas
class PDF extends FPDF
{
    //atributos de la clase
    public $institucion;
    public $pathlogo;
    public $pathlogoins;
    public $oficina;
    public $direccion;
    public $email;
    public $telefono;
    public $nit;
    public $rango;
    public $tipocuenta;
    public $saldoant;

    public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit)
    {
        parent::__construct();
        $this->institucion = $institucion;
        $this->pathlogo = $pathlogo;
        $this->pathlogoins = $pathlogoins;
        $this->oficina = $oficina;
        $this->direccion = $direccion;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->nit = $nit;
    }

    // Cabecera de página
    function Header()
    {
        $fuente = "Courier";
        $hoy = date("Y-m-d H:i:s");
        //fecha y usuario que genero el reporte
        $this->SetFont($fuente, '', 7);
        $this->Cell(0, 2, $hoy, 0, 1, 'R');
        // Logo de la agencia
        $this->Image($this->pathlogoins, 10, 13, 33);

        //tipo de letra para el encabezado
        $this->SetFont($fuente, 'B', 9);
        // Título
        $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
        $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
        $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
        $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
        $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
        // Salto de línea
        $this->Ln(10);
    }

    // Pie de página
    function Footer()
    {
        // Posición: a 1 cm del final
        $this->SetY(-15);
        // Logo 
        // $this->Image($this->pathlogo, 175, 279, 28);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}
// Creación del objeto de la clase heredada
$pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);

$pdf->AliasNbPages();
$pdf->AddPage();
$fuente = "Courier";
$tamanio_linea = 4; //altura de la linea/celda
$ancho_linea = 35; //anchura de la linea/celda
$ancho_linea2 = 25; //anchura de la linea/celda
$pdf->SetFont($fuente, '', 10);
//------------REFERENCIAS PARTIDA--------------
$pdf->Cell($ancho_linea + 15, $tamanio_linea + 1, 'Partida No.' . $ctbmovdata[0]["numcom"], 'B', 0, 'L');
$pdf->Cell($ancho_linea * 3, $tamanio_linea + 1, 'TIPO DE ASIENTO: ' . $ctbmovdata[0]["tipopol"], 'B', 0, 'L'); //
$pdf->Cell($ancho_linea, $tamanio_linea + 1, 'FECHA: ' . date("d-m-Y", strtotime($ctbmovdata[0]["feccnt"])), 'B', 1, 'R');
//---------------------------------------------
//------------TITULOS PARTIDA--------------
$pdf->Cell($ancho_linea, $tamanio_linea + 1, 'CODIGO', 'B', 0, 'C');
$pdf->Cell($ancho_linea + 20, $tamanio_linea + 1, 'NOMBRE', 'B', 0, 'C'); //
$pdf->Cell($ancho_linea + 15, $tamanio_linea + 1, 'DEBE', 'B', 0, 'R');
$pdf->Cell($ancho_linea + 15, $tamanio_linea + 1, 'HABER', 'B', 1, 'R');
//--------------------------------------------

$it = 0;
$debe = 0;
$haber = 0;
while ($it < count($ctbmovdata)) {
    $idcuenta = $ctbmovdata[$it]["ccodcta"];
    $nomcuenta = decode_utf8($ctbmovdata[$it]["cdescrip"]);
    $mondebe = $ctbmovdata[$it]["debe"];
    $monhaber = $ctbmovdata[$it]["haber"];
    $debe = $debe + $mondebe;
    $haber = $haber + $monhaber;

    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $idcuenta, 0, 0, 'L', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea + 20, $tamanio_linea + 1, $nomcuenta, 0, 0, 'L', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea + 15, $tamanio_linea + 1, number_format($mondebe, 2, '.', ','), 0, 0, 'R', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea + 15, $tamanio_linea + 1, number_format($monhaber, 2, '.', ','), 0, 1, 'R', 0, '', 1, 0); // cuenta
    $it++;
}

$pdf->SetFont($fuente, 'B', 10);
$pdf->CellFit($ancho_linea * 3 - 15, $tamanio_linea + 1, "TOTALES: ", 'T', 0, 'R', 0, '', 1, 0); // cuenta
$pdf->CellFit($ancho_linea + 15, $tamanio_linea + 1, number_format($debe, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0); // cuenta
$pdf->CellFit($ancho_linea + 15, $tamanio_linea + 1, number_format($haber, 2, '.', ','), 'T', 1, 'R', 0, '', 1, 0); // cuenta

$pdf->MultiCell(0, $tamanio_linea + 1, 'CONCEPTO: ' . decode_utf8($ctbmovdata[0]["glosa"])); // cuenta
$pdf->firmas(2,['ELABORADO POR','Vo.Bo']);

ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile' => "Partida de diario",
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData)
);
echo json_encode($opResult);
