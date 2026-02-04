<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
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

// $oficina = "Coban";
// $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
// $direccionins = "Canton vipila zona 1";
// $emailins = "fape@gmail.com";
// $telefonosins = "502 43987876";
// $nitins = "1323244234";
// $usuario = "9999";

// $rutalogomicro = "../../../includes/img/logomicro.png";
// $rutalogoins = "../../../includes/img/fape.jpeg";

//se crea el array que recibe los datos
$datos = array();
$datos = $_POST["data"];

//se obtienen las variables del get
$estado = $datos[5];
$cuenta = $datos[6];
$tipo = $datos[7];
$usuario = $datos[8];
$oficina = $datos[9];

$fuente = "Courier";
$tamanioFuente = 9;
$tamanioTitulo = 11;
$tamanio_linea = 4; //altura de la linea/celda
$ancho_linea = 30; //anchura de la linea/celda
$espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
$ancho_linea2 = 20; //anchura de la linea/celda
$espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda

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
    public $user;
    public $conexion;
    public $estado;
    public $cuenta;
    public $tipo;

    public function __construct($conexion, $institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $user, $estado, $cuenta, $tipo)
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
        $this->user = $user;
        $this->conexion = $conexion;
        $this->estado = $estado;
        $this->cuenta = $cuenta;
        $this->tipo = $tipo;
    }

    // Cabecera de página
    function Header()
    {
        //consultar los estados
        $texto_cuentas = "";
        //variables para el texto
        $fuente = "Courier";
        $tamanioFuente = 9;
        $tamanioTitulo = 11;
        $tamanio_linea = 4; //altura de la linea/celda
        $ancho_linea = 30; //anchura de la linea/celda
        $espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
        $ancho_linea2 = 20; //anchura de la linea/celda
        $espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda

        //consultar todas las cuentas
        if ($this->tipo == '0') {
            $texto_cuentas = 'TODAS LAS CUENTAS';
        } else {
            $data_cuentas = mysqli_query($this->conexion, "SELECT `nombre` FROM `aprtip` WHERE `id_tipo`=$this->tipo");
            while ($rowcuentas = mysqli_fetch_array($data_cuentas, MYSQLI_ASSOC)) {
                $texto_cuentas = strtoupper(decode_utf8($rowcuentas["nombre"]));
            }
        }

        // ACA ES DONDE EMPIEZA LO DEL FORMATO DE REPORTE---------------------------------------------------
        $hoy = date("Y-m-d H:i:s");
        //fecha y usuario que genero el reporte
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 2, $hoy, 0, 1, 'R');
        $this->Ln(1);
        $this->Cell(0, 2, $this->user, 0, 1, 'R');

        // Logo de la agencia
        $this->Image($this->pathlogoins, 10, 13, 33);

        //tipo de letra para el encabezado
        $this->SetFont('Arial', '', 8);
        // Título
        $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
        $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
        $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
        $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
        $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
        // Salto de línea
        $this->Ln(3);

        $this->SetFont($fuente, '', 10);
        //SECCION DE DATOS DEL CLIENTE
        //TITULO DE REPORTE
        $this->SetFillColor(255, 255, 255);
        $this->Cell(0, 5, 'LISTADO DE CUENTAS ACTIVAS/INACTIVAS.', 0, 1, 'C', true);
        $this->Cell(0, 5, $texto_cuentas, 0, 1, 'C', true);
        $this->Ln(5);
        //Fuente
        $this->SetFont($fuente, '', 10);
        //encabezado de tabla
        $this->Cell($ancho_linea2 - 10, $tamanio_linea + 1, ' NUM', 'B', 0, 'C', true); //numero
        $this->Cell($ancho_linea + 5, $tamanio_linea + 1, 'CUENTA', 'B', 0, 'C', true); // cuenta
        $this->Cell(($ancho_linea + $ancho_linea + 5), $tamanio_linea + 1, 'NOMBRE COMPLETO', 'B', 0, 'C', true); //nombre
        $this->Cell($ancho_linea2, $tamanio_linea + 1, 'ESTADO', 'B', 0, 'C', true); //Estado
        // $this->Cell($ancho_linea2, $tamanio_linea + 1, 'ACTIVACION', 'B', 0, 'C', true); //activacion
        $this->Cell($ancho_linea, $tamanio_linea + 1, 'APERTURA', 'B', 0, 'C', true); //apertura
        $this->Cell($ancho_linea, $tamanio_linea + 1, 'CANCELACION', 'B', 0, 'C', true); //cancelacion
        $this->Ln(8);
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
$pdf = new PDF($conexion, $institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins,  $usuario, $estado, $cuenta, $tipo);
$pdf->AliasNbPages();
$pdf->AddPage();

//aca colocar todos los registros
$contador = 0;
$consulta = "SELECT cta.ccodaport, cl.short_name, cta.estado, cta.fecha_apertura, cta.fecha_cancel
FROM `tb_cliente` AS cl
    INNER JOIN `aprcta` AS cta ON cl.idcod_cliente = cta.ccodcli
    INNER JOIN `aprtip` AS tp ON tp.ccodtip = cta.ccodtip";

if ($estado != "0" || $tipo != "0") {
    $consulta .= " WHERE";
    if ($estado != "0") {
        $consulta .= " cta.estado='$estado'";
    }
    if ($tipo != "0") {
        //obtener el codtip
        $data = mysqli_query($conexion, "SELECT `ccodtip` FROM `aprtip` WHERE `id_tipo`='$tipo'");
        while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
            $ccodtip = $rowdata["ccodtip"];
        }
        if ($estado == "0") {
            $consulta .= " cta.ccodtip='$ccodtip'";
        } else {
            $consulta .= " AND cta.ccodtip='$ccodtip'";
        }
    }
}

//se cargan cada una de las filas
$data2 = mysqli_query($conexion, $consulta);
cargar_datos($pdf, $contador, $data2, $ancho_linea, $ancho_linea2, $tamanio_linea);
$contador = 0;

//forma de migrar el archivo
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

//Procedimiento para cargar todos los datos al archivo pdf
function cargar_datos($pdf, $contador, $data, $ancho_linea, $ancho_linea2, $tamanio_linea)
{
    while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
        $bd_ccodaport = $rowdata["ccodaport"];
        $bd_shortname = strtoupper(decode_utf8($rowdata["short_name"]));
        $bd_estado = strtoupper(($rowdata["estado"]));
        $bd_fechaapertura = ($rowdata["fecha_apertura"]);
        $bd_fechacancel = ($rowdata["fecha_cancel"]);
        $contador++;

        //se insertan los registros
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, $contador, 'B', 0, 'C', 0, '', 1, 0); //numero
        $pdf->CellFit($ancho_linea + 5, $tamanio_linea + 1, $bd_ccodaport, 'B', 0, 'C', 0, '', 1, 0); // cuenta
        $pdf->CellFit(($ancho_linea + $ancho_linea + 5), $tamanio_linea + 1, $bd_shortname, 'B', 0, 'L', 0, '', 1, 0); //nombre
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $bd_estado, 'B', 0, 'C', 0, '', 1, 0); //Estado
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $bd_fechaapertura, 'B', 0, 'C', 0, '', 1, 0); //apertura
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $bd_fechacancel == null ? '0000-00-00' : $bd_fechacancel, 'B', 0, 'C', 0, '', 1, 0); //cancelacion
        $pdf->Ln(6);
    }
}
