<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';
require '../../../vendor/autoload.php';
$hoy = date("Y-m-d");

use Complex\Functions;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Luecano\NumeroALetras\NumeroALetras;
use Micro\Generic\Utf8;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

//*****************ARMANDO LA CONSULTA**************
$consulta = "SELECT cl.idcod_cliente AS codcli, cl.short_name AS nombre, cl.no_identifica AS dpi, cl.Direccion AS direccion, cl.date_birth AS fechacumple,  cl.tel_no1 AS telefono, cl.genero AS genero, clb.* FROM tb_cliente cl INNER JOIN tb_cli_balance clb ON cl.idcod_cliente=clb.ccodcli WHERE clb.id='$archivo[0]'";
//--------------------------------------------------
$query = mysqli_query($conexion, $consulta);
$data[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($query)) {
    $data[$j] = $fil;
    $j++;
}

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos' . $consulta]);
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

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}
//----------------------
$id_usu = $_SESSION['id'];
switch ($tipo) {
    case 'xlsx';
        // printxls($data, $titlereport, $archivo[0]);
        break;
    case 'pdf':
        printpdf($data, $info, $id_usu, $conexion);
        break;
}
//funcion para generar pdf
function printpdf($datos, $info, $id_usu, $conexion)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

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
        public $id_usu;
        public $conexion;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $id_usu, $conexion)
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
            $this->id_usu = $id_usu;
            $this->conexion = $conexion;
            $this->DefOrientation = 'P';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(1);
            //realizar la consulta para obtener el usuario
            $data_usu = mysqli_query($this->conexion, "SELECT usu FROM tb_usuario WHERE id_usu=" . $this->id_usu);
            while ($res = mysqli_fetch_array($data_usu, MYSQLI_ASSOC)) {
                $codusu = strtoupper(($res["usu"]));
            }
            $this->Cell(0, 2, $codusu, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 13, 30);
            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 9);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(5);

            $this->SetFont($fuente, 'B', 10);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $id_usu, $conexion);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea = 30;

    //formato
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea+10, $tamanio_linea, ('Nombre del Cliente:'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(50, $tamanio_linea, Utf8::decode((($datos[0]['nombre'] == '' || $datos[0]['nombre'] == null) ? ' ' : $datos[0]['nombre'])), 0, 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea+10, $tamanio_linea, ('Codigo de Cliente:'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(50, $tamanio_linea, ((($datos[0]['codcli'] == '' || $datos[0]['codcli'] == null) ? ' ' : $datos[0]['codcli'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea+10, $tamanio_linea, ('DPI:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(50, $tamanio_linea, (((($datos[0]['dpi'] == '' || $datos[0]['dpi'] == null) ? ' ' : $datos[0]['dpi']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea+10, $tamanio_linea, ('Direccion:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(50, $tamanio_linea, Utf8::decode((($datos[0]['direccion'] == '' || $datos[0]['direccion'] == null) ? ' ' : $datos[0]['direccion'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea+10, $tamanio_linea, ('Fecha de Nacimiento:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(50, $tamanio_linea, ((($datos[0]['fechacumple'] == '' || $datos[0]['fechacumple'] == null) ? ' ' : $datos[0]['fechacumple'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea+10, $tamanio_linea, ('Telefono:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(50, $tamanio_linea, ((($datos[0]['telefono'] == '' || $datos[0]['telefono'] == null) ? ' ' : $datos[0]['telefono'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea+10, $tamanio_linea, ('Genero:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(50, $tamanio_linea, ((($datos[0]['genero'] == '' || $datos[0]['genero'] == null) ? ' ' : $datos[0]['genero'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'b', 10);
    $pdf->CellFit(0, $tamanio_linea, ('__________________________________________________________________________________'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(12);

    //fecha de evaluacion 
    $pdf->SetFont($fuente, '', 10);
    $pdf->CellFit(0, $tamanio_linea, ('FECHA DE EVALUACION DE BALANCE:'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(12);

    //FECHA DE EVALUACION
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Fecha de eval. :'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fechaeval'] == '' || $datos[0]['fechaeval'] == null || $datos[0]['fechaeval'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fechaeval']))), 0, 0, 'L', 0, '', 1, 0);
    //FECHA DE BALANCE
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Fecha de Balance:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fechabalance'] == '' || $datos[0]['fechabalance'] == null || $datos[0]['fechabalance'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fechabalance']))), 0, 0, 'L', 0, '', 1, 0);

    $pdf->SetFont($fuente, '', 9);

    $pdf->CellFit(90, $tamanio_linea, (' '), 0, 0, 'C', 0, '', 1, 0);

    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'b', 10);
    $pdf->CellFit(0, $tamanio_linea, ('__________________________________________________________________________________'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(12);
    $pdf->SetFont($fuente, '', 10);
    $pdf->CellFit(0, $tamanio_linea, ('INGRESO, EGRESO, SALDO:'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(12);

    $pdf->CellFit($ancho_linea, $tamanio_linea, ('   '), 0, 0, 'L', 0, '', 1, 0);
    //vacio
    $pdf->CellFit($ancho_linea-10, $tamanio_linea, ('   '), 0, 0, 'L', 0, '', 1, 0);
    //

    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Sub Sub Cuentas'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(7, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Sub Cuentas'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Cuentas'), 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(12);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Activo  circulante'), 0, 0, 'L', 0, '', 1, 0);

    // Columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    // Columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //suma
    $activo_c = 0;
    $activo_c += ($datos[0]['disponible'] == '' || $datos[0]['disponible'] == null) ? 0 : $datos[0]['disponible'];
    $activo_c += ($datos[0]['cuenta_por_cobrar'] == '' || $datos[0]['cuenta_por_cobrar'] == null) ? 0 : $datos[0]['cuenta_por_cobrar'];
    $activo_c += ($datos[0]['inventario'] == '' || $datos[0]['inventario'] == null) ? 0 : $datos[0]['inventario'];
    $activo_c += ($datos[0]['cuenta_por_cobrar2'] == '' || $datos[0]['cuenta_por_cobrar2'] == null) ? 0 : $datos[0]['cuenta_por_cobrar2'];
    // Columna 2 (Disponible + cuenta_por_cobrar)
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $activo_c, 0, 0, 'L', 0, '', 1, 0);

    // Columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //Disponible
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Disponible'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['disponible'] == '' || $datos[0]['disponible'] == null) ? ' ' : ($datos[0]['disponible'])), 0, 0, 'L', 0, '', 1, 0);

    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(6);

    //Cuentas por cobrar
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Cuentas por cobrar'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['cuenta_por_cobrar'] == '' || $datos[0]['cuenta_por_cobrar'] == null) ? ' ' : ($datos[0]['cuenta_por_cobrar'])), 0, 0, 'L', 0, '', 1, 0);
    //$pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);

    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    //Cuentas por cobrar
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Otras cuentas por Cob.'), 0, 0, 'L', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['cuenta_por_cobrar2'] == '' || $datos[0]['cuenta_por_cobrar2'] == null) ? ' ' : ($datos[0]['cuenta_por_cobrar2'])), 0, 0, 'L', 0, '', 1, 0);
    //$pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);

    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //Invetario
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Inventario'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['inventario'] == '' || $datos[0]['inventario'] == null) ? ' ' : ($datos[0]['inventario'])), 0, 0, 'L', 0, '', 1, 0);

    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //Activo Fijo
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Activo Fijo'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['activo_fijo'] == '' || $datos[0]['activo_fijo'] == null) ? ' ' : ($datos[0]['activo_fijo'])), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //Total activo 
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Total activo'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);

    //columna 3
    $total_a = 0;
    $total_a += ($datos[0]['disponible'] == '' || $datos[0]['disponible'] == null) ? 0 : $datos[0]['disponible'];
    $total_a += ($datos[0]['cuenta_por_cobrar'] == '' || $datos[0]['cuenta_por_cobrar'] == null) ? 0 : $datos[0]['cuenta_por_cobrar'];
    $total_a += ($datos[0]['inventario'] == '' || $datos[0]['inventario'] == null) ? 0 : $datos[0]['inventario'];
    $total_a += ($datos[0]['cuenta_por_cobrar2'] == '' || $datos[0]['cuenta_por_cobrar2'] == null) ? 0 : $datos[0]['cuenta_por_cobrar2'];
    $total_a += ($datos[0]['activo_fijo'] == '' || $datos[0]['activo_fijo'] == null) ? 0 : $datos[0]['activo_fijo'];

    // Columna 2 ( 'campo'+ 'campo2' = 'Total' )
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $total_a, 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(6);

    //PAsivo
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Pasivo '), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);

    //columna 3
    $total_pasivo = 0;
    $total_pasivo += ($datos[0]['proveedores'] == '' || $datos[0]['proveedores'] == null) ? 0 : $datos[0]['proveedores'];
    $total_pasivo += ($datos[0]['otros_prestamos'] == '' || $datos[0]['otros_prestamos'] == null) ? 0 : $datos[0]['otros_prestamos'];
    $total_pasivo += ($datos[0]['prest_instituciones'] == '' || $datos[0]['prest_instituciones'] == null) ? 0 : $datos[0]['prest_instituciones'];

    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $total_pasivo, 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(6);

    //Proveedores
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Proveedores '), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['proveedores'] == '' || $datos[0]['proveedores'] == null) ? ' ' : ($datos[0]['proveedores'])), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    //Otros prestamos 
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Otros prestamos '), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['otros_prestamos'] == '' || $datos[0]['otros_prestamos'] == null) ? ' ' : ($datos[0]['otros_prestamos'])), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //Prestamo de IOnstituciones 
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Prestamos Inst. '), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['prest_instituciones'] == '' || $datos[0]['prest_instituciones'] == null) ? ' ' : ($datos[0]['prest_instituciones'])), 0, 0, 'L', 0, '', 1, 0);

    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //Patrimonio
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Patrimonio '), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['patrimonio'] == '' || $datos[0]['patrimonio'] == null) ? ' ' : ($datos[0]['patrimonio'])), 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(6);


    //
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Ingresos '), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3

    $total_ingresos_fam = 0;
    $total_ingresos_fam += ($datos[0]['ventas'] == '' || $datos[0]['ventas'] == null) ? 0 : $datos[0]['ventas'];
    $total_ingresos_fam += ($datos[0]['negocio'] == '' || $datos[0]['negocio'] == null) ? 0 : $datos[0]['negocio'];
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $total_ingresos_fam, 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(6);
    //
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Ingresos Famil.'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2

    $total_ingresos_fam = 0;
    $total_ingresos_fam += ($datos[0]['ventas'] == '' || $datos[0]['ventas'] == null) ? 0 : $datos[0]['ventas'];
    $total_ingresos_fam += ($datos[0]['negocio'] == '' || $datos[0]['negocio'] == null) ? 0 : $datos[0]['negocio'];
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $total_ingresos_fam, 0, 0, 'L', 0, '', 1, 0);


    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //VENTAS
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Ventas'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['ventas'] == '' || $datos[0]['ventas'] == null) ? ' ' : ($datos[0]['ventas'])), 0, 0, 'L', 0, '', 1, 0);

    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Negocio'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1

    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['negocio'] == '' || $datos[0]['negocio'] == null) ? ' ' : ($datos[0]['negocio'])), 0, 0, 'L', 0, '', 1, 0);

    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('  '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Egresos'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3

    $total_egresos = 0;
    $total_egresos += ($datos[0]['mercaderia'] == '' || $datos[0]['mercaderia'] == null) ? 0 : $datos[0]['mercaderia'];
    $total_egresos += ($datos[0]['pago_creditos'] == '' || $datos[0]['pago_creditos'] == null) ? 0 : $datos[0]['pago_creditos'];
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $total_egresos, 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(6);
    //
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Compra de Merc.'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['mercaderia'] == '' || $datos[0]['mercaderia'] == null) ? ' ' : ($datos[0]['mercaderia'])), 0, 0, 'L', 0, '', 1, 0);
    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Pago de prest.'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2

    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['pago_creditos'] == '' || $datos[0]['pago_creditos'] == null) ? ' ' : ($datos[0]['pago_creditos'])), 0, 0, 'L', 0, '', 1, 0);


    //columna 3
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(12);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit(0, $tamanio_linea, ('____________________________________________________________________________________________'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(12);
    $pdf->SetFont($fuente, '', 10);
    //
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Balance'), 0, 0, 'L', 0, '', 1, 0);
    //columna 0
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 1
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 2
    $pdf->CellFit($ancho_linea, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    //columna 3

    $total_balance = 0;
    $total_balance += ($datos[0]['ventas'] == '' || $datos[0]['ventas'] == null) ? 0 : $datos[0]['ventas'];
    $total_balance += ($datos[0]['negocio'] == '' || $datos[0]['negocio'] == null) ? 0 : $datos[0]['negocio'];
    $total_balance -= ($datos[0]['mercaderia'] == '' || $datos[0]['mercaderia'] == null) ? 0 : $datos[0]['mercaderia'];
    $total_balance -= ($datos[0]['pago_creditos'] == '' || $datos[0]['pago_creditos'] == null) ? 0 : $datos[0]['pago_creditos'];
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $total_balance, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(12);

    $pdf->CellFit(0, $tamanio_linea, ('____________________________________________________________________________________________'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(12);


    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Balance personal generado correctamente',
        'namefile' => "Balance-economico-personal" . $datos[0]['codcli'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
