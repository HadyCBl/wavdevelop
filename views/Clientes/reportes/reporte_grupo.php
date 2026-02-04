<?php
session_start();
use PDF as GlobalPDF;

include '../../../includes/BD_con/db_con.php';
require '../../../fpdf/fpdf.php';

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
$oficina = mb_convert_encoding($info[0]["nom_agencia"], 'ISO-8859-1', 'UTF-8');
$institucion = mb_convert_encoding($info[0]["nomb_comple"], 'ISO-8859-1', 'UTF-8');
$direccionins = mb_convert_encoding($info[0]["muni_lug"], 'ISO-8859-1', 'UTF-8');
$emailins = $info[0]["emai"];
$telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
$nitins = $info[0]["nit"];
$rutalogomicro = "../../../includes/img/logomicro.png";
$rutalogoins = "../../.." . $info[0]["log_img"];

$codigo = $_GET["codgrupo"];

$queryGrupo = mysqli_query($conexion, "SELECT * FROM tb_grupo WHERE codigo_grupo = " . $codigo);
$row = mysqli_fetch_array($queryGrupo);

$cgrupo = $row['id_grupos'];
$queryCliGrupo = mysqli_query($conexion, "SELECT c.idcod_cliente,c.short_name, c.no_identifica, c.date_birth FROM tb_cliente_tb_grupo cg INNER JOIN tb_cliente c ON c.idcod_cliente=cg.cliente_id WHERE cg.estado ='1' AND cg.Codigo_grupo = " . $cgrupo);
$nombre = $row['NombreGrupo'];
$fec = $row['fecha_sys'];
$fecha = date("d-m-Y", strtotime($fec)); //formatear fecha en dia/mes/año
$depa = $row['Depa'];
$muni = $row['Muni'];
$canton = $row['canton'];
$direccion = $row['direc'];

class PDF extends FPDF
{
    public $institucion;

    public $pathlogo;
    public $pathlogoins;
    public $oficina;
    public $direccion;
    public $email;
    public $telefonos;
    public $nit;
    public function __construct($institucion,$pathlogo,$pathlogoins,$oficina,$dire,$email,$tel,$nit)
    {
        parent::__construct();
        $this->institucion=$institucion;
        $this->pathlogo=$pathlogo;
        $this->pathlogoins=$pathlogoins;
        $this->oficina=$oficina;
        $this->direccion=$dire;
        $this->email=$email;
        $this->telefonos=$tel;
        $this->nit=$nit;
    }

    // Cabecera de página
    function Header()
    { 
        // Logo 
        $this->Image($this->pathlogoins,10,8,33);
        // Arial bold 15
        $this->SetFont('Arial', '', 8);
        // Movernos a la derecha
        //$this->Cell(80);
        // Título
        $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
        $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
        $this->Cell(0, 3, 'Email: '. $this->email, 0, 1, 'C');
        $this->Cell(0, 3, 'Tel: '. $this->telefonos, 0, 1, 'C');
        $this->Cell(0, 3, 'NIT: ' .$this->nit, 'B', 1, 'C');
        // Salto de línea
        $this->Ln(15);
    }

    // Pie de página
    function Footer()
    {
        
        // Posición: a 1,5 cm del final
        $this->SetY(-15);
        // Logo 
        // $this->Image($this->pathlogo,165,280,33);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}
$fuente = "Arial";

$tamanioFuente = 9;
$tamanioTitulo = 11;
$tamanio_linea = 4; //altura de la linea/celda
$ancho_linea = 40; //anchura de la linea/celda
$espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
$ancho_linea2 = 35; //anchura de la linea/celda
$espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda
// Creación del objeto de la clase heredada
//$pdf = new PDF("P","mm","Carta");
$pdf = new PDF($institucion,$rutalogomicro,$rutalogoins,$oficina,$direccionins,$emailins,$telefonosins,$nitins);
$pdf->AliasNbPages();
$pdf->AddPage();
//$pdf->Rect(9, 58, 192, 31, 'D'); //CUADRO 1 DATOS GENERALES
 
$pdf->SetFont($fuente, 'B', $tamanioTitulo);
$pdf->Cell($ancho_linea*2, 10, 'Oficina:  ' . $oficina, 0, 1);
//$pdf->Cell($ancho_linea*2, 10, 'Institucion:  ' . $institucion, 0, 1);
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'INFORME POR GRUPOS SOLIDARIOS', 0, 1, 'C', true);

$pdf->Ln(3);
$pdf->SetDrawColor(225, 226, 226);
$pdf->Cell(0, 6, 'Codigo De Grupo:  ' . $codigo, 'B', 1, 'C'); //codigo grupo
$pdf->Cell(0, 6, 'Nombre De Grupo:  ' . $nombre, 'B', 1, 'C'); //codigo grupo
$pdf->Ln(4);
$pdf->Cell($ancho_linea, $tamanio_linea, 'Codigo cliente', 'B', 0, 'C'); //codigo cliente titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 'B', 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Nombre cliente ', 'B', 0, 'C'); //nombre titulo
$pdf->Cell($espacio_blanco*3, $tamanio_linea, '', 'B', 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, 'Identificacion', 'B', 0, 'C'); //identificaicon titulo
$pdf->Cell($ancho_linea2, $tamanio_linea, 'Fecha Nacim.', 'B', 1, 'C'); //fecha titulo


$pdf->SetfillColor(230, 235, 236);
$pdf->SetFont($fuente, '', $tamanioFuente);
$contador=0;
while ($cligrupo = mysqli_fetch_array($queryCliGrupo, MYSQLI_ASSOC)) {
    $pdf->Ln(2);
    $codigocli = ($cligrupo['idcod_cliente']);
    $nombrecli = mb_convert_encoding($cligrupo['short_name'], 'ISO-8859-1', 'UTF-8');
    $dpi = ($cligrupo['no_identifica']);
    $fechanac = date("d-m-Y", strtotime(($cligrupo['date_birth'])));

    $pdf->Cell(35, $tamanio_linea, $codigocli, 0, 0, 'C');
    $pdf->Cell(85, $tamanio_linea, $nombrecli, 0, 0);
    $pdf->Cell(35, $tamanio_linea, $dpi, 0, 0, 'C');
    $pdf->Cell(30, $tamanio_linea, $fechanac, 0, 1, 'C');

    $contador=$contador+1;
}
$pdf->Ln(2);
$pdf->SetDrawColor(225, 226, 226);
$pdf->Cell(0, 6, ' ', 'T', 1, 'C'); //linea divisora
$pdf->Cell(50, $tamanio_linea, "TOTAL: ". $contador, 0, 0, 'C');
$pdf->Output("Reporte_Grupo.pdf",'I');
//$pdf->Output("Reporte_Grupo.pdf",'D'); //lo descarga de una vez
