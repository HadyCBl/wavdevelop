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
$consulta = "SELECT cl.idcod_cliente AS idcli, cl.short_name AS nomcli, clb.* FROM tb_cliente cl INNER JOIN tb_cli_balance clb ON cl.idcod_cliente=clb.ccodcli WHERE clb.id='$archivo[0]'";
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
function printpdf($registro, $info, $id_usu, $conexion)
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
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, 'B', 12);
    $pdf->CellFit(0, $tamanio_linea + 1, Utf8::decode('INFORMACIÓN FINANCIERA'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(10);

    $pdf->SetFont($fuente, '', 9);
    $pdf->MultiCell(0, $tamanio_linea + 1, Utf8::decode('Con el propósito de solicitar un crédito, someto a consideración la siguiente información financiera referida a la fecha indicada en el epigrafe, compromentiendome a NO hacer ningún cambio material que disminuya el capital neto'), 0, 'J');
    $pdf->Ln(6);

    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit(0, $tamanio_linea + 1, 'ESTADO PATRIMONIAL', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(0, $tamanio_linea + 1, '(Cifras expresadas en Quetzales)', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(7);

    //primera linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Circulante', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 19, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Obligaciones a Corto Plazo', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //segunda linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Disponible', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['disponible'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 19, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Proveedores', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['proveedores'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //tercera linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Cuentas por cobrar', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['cuenta_por_cobrar2'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 19, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, Utf8::decode('Otros préstamos'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['otros_prestamos'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //cuarta linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Inventario', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['inventario'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 19, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, Utf8::decode('Préstamos a instituciones'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['prest_instituciones'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //quinta linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Total Circulante', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format((round($registro[0]['disponible'], 2) + round($registro[0]['cuenta_por_cobrar2'], 2) + round($registro[0]['inventario'], 2)), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 19, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Suma pasivo', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format((round($registro[0]['proveedores'], 2) + round($registro[0]['otros_prestamos'], 2) + round($registro[0]['prest_instituciones'], 2)), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //sexta linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Total Activo Fijo', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['activo_fijo'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 19, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'Patrimonio', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format(round($registro[0]['patrimonio'], 2), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //septima linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'SUMA TOTAL DEL ACTIVO', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format((round($registro[0]['disponible'], 2) + round($registro[0]['cuenta_por_cobrar2'], 2) + round($registro[0]['inventario'], 2) + round($registro[0]['activo_fijo'], 2)), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 19, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'PASIVO Y PATRIMONIO', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 1, number_format((round($registro[0]['proveedores'], 2) + round($registro[0]['otros_prestamos'], 2) + round($registro[0]['prest_instituciones'], 2) + round($registro[0]['patrimonio'], 2)), 2), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(15);

    //PARTE 2
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit(0, $tamanio_linea + 1, 'ESTADO DE INGRESOS Y EGRESOS', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(0, $tamanio_linea + 1, '(Cifras expresadas en Quetzales)', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(7);
    // Primera linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'Ingresos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, 'MENSUALES', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, 'ANUALES', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // Segunda linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'Ventas', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['ventas'], 2), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['ventas'], 2) * 12, 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // Segunda linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'Recup. cuentas por cobrar', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['cuenta_por_cobrar'], 2), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['cuenta_por_cobrar'], 2) * 12, 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // Tercera linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'Total ingresos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format((round($registro[0]['ventas'], 2) + round($registro[0]['cuenta_por_cobrar'], 2)), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(((round($registro[0]['ventas'], 2) * 12) + (round($registro[0]['cuenta_por_cobrar'], 2) * 12)), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // cuarta linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'EGRESOS', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // quinta linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, Utf8::decode('Compra de mercadería'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['mercaderia'], 2), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['mercaderia'], 2) * 12, 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // sexta linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'Gastos del negocio', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['negocio'], 2), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['negocio'], 2) * 12, 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // septima linea
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, Utf8::decode('Pagos de créditos'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['pago_creditos'], 2), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(round($registro[0]['pago_creditos'], 2) * 12, 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // octava linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'Total Egresos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format((round($registro[0]['mercaderia'], 2) + round($registro[0]['negocio'], 2) + round($registro[0]['pago_creditos'], 2)), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(((round($registro[0]['mercaderia'], 2) * 12) + (round($registro[0]['negocio'], 2) * 12) + (round($registro[0]['pago_creditos'], 2) * 12)), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    // novena linea
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, 'DIF. INGRESOS - EGRESOS', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format(((round($registro[0]['ventas'], 2) + round($registro[0]['cuenta_por_cobrar'], 2)) - (round($registro[0]['mercaderia'], 2) + round($registro[0]['negocio'], 2) + round($registro[0]['pago_creditos'], 2))), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 25, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 25, $tamanio_linea + 1, number_format((((round($registro[0]['ventas'], 2) * 12) + (round($registro[0]['cuenta_por_cobrar'], 2) * 12)) - ((round($registro[0]['mercaderia'], 2) * 12) + (round($registro[0]['negocio'], 2) * 12) + (round($registro[0]['pago_creditos'], 2) * 12))), 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(12);

    $pdf->SetFont($fuente, '', 9);
    $pdf->MultiCell(0, $tamanio_linea + 1, Utf8::decode('Declaro bajo juramento de ley que la presente información financiera, es correcta, veridica, y exactitud me someto a las sanciones legales correspondientes por cualquier falsedad que llegare a comprobar y autorizo a la entidad financiera a investigar cualquier dato que considera conveniente y de comprobar en la misma queda facultada a denegar el crédito solicitado'), 0, 'J');
    $pdf->Ln(3);


    $pdf->firmas(4, ['Firma declarante', 'No. Cédula', 'Lugar y Fecha', 'Vo. Bo. Asesor']);

    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Balance-economico" . $registro[0]['idcli'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
