<?php

use Micro\Generic\Utf8;

include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];


// session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';

// if (!isset($_SESSION['id_agencia'])) {
//     echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
//     return;
// }

$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop WHERE ag.id_agencia=" . $_SESSION['id_agencia']);
$info = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institución asignada a la agencia no encontrada']);
    return;
}

$codcli = $_POST["datosval"][3][0];
$shortNameCli = 0;

// Consulta datos del cliente
$querycli = mysqli_query($conexion, "SELECT * FROM tb_cliente WHERE idcod_cliente = '$codcli'");
if (mysqli_num_rows($querycli) > 0) {
    $row = mysqli_fetch_assoc($querycli);
    $shortNameCli = $row['short_name'];
    $dpiCli = $row['no_identifica'];
    $direccionCli = $row['Direccion'];
    $tel_no1Cli = $row['tel_no1'];
    $tel_no2Cli = $row['tel_no2'];
} else {
    echo json_encode(['status' => 0, 'mensaje' => 'No se encontraron resultados']);
    return;
}

// Consulta de ingresos
$queryingr = mysqli_query($conexion, "SELECT 
    ti.id_ingre_dependi AS idtipo,
    ti.Tipo_ingreso AS tipoingreso,
    ti.nombre_empresa AS nombreempresa,
    ti.direc_negocio AS direcnegocio,
    ti.sueldo_base AS sueldobase, 
    ti.detalle_ingreso AS detalle_ingreso,
    ti.puesto_ocupa AS puesto
    FROM tb_cliente tc 
    INNER JOIN tb_ingresos ti ON tc.idcod_cliente = ti.id_cliente 
    WHERE tc.idcod_cliente ='$codcli'");

// Verificar si hay resultados
if (mysqli_num_rows($queryingr) > 0) {
    // Almacenar los datos en un array
    $incomeData = [];
    while ($row = mysqli_fetch_assoc($queryingr)) {
        $incomeData[] = $row;
    }

    // Datos de la institución
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    // Clase PDF personalizada
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
        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefonos, $nit)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefonos = $telefonos;
            $this->nit = $nit;
        }

        // Cabecera de página
        function Header()
        {
            $hoy = date("Y-m-d H:i:s");
            // Logo 
            $this->Image($this->pathlogoins, 10, 8, 33);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefonos, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->SetFont('Arial', '', 7);
            $this->SetXY(-30, 5);
            $this->Cell(10, 2, $hoy, 0, 1, 'L');
            $this->SetXY(-25, 8);
            $this->Ln(15);
        }

        // Pie de página
        function Footer()
        {
            $this->SetY(-15);
            // $this->Image($this->pathlogo, 165, 275, 20);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Página ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Funciones para añadir contenido
    function addTitle($pdf, $title)
    {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetFillColor(52, 152, 219); // Azul moderno
        $pdf->SetTextColor(255);
        $pdf->Cell(0, 12, $title, 0, 1, 'C', true);
        $pdf->Ln(10);
    }

    function addField($pdf, $label, $value, $width = 0)
    {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(52, 73, 94); // Azul oscuro para etiquetas
        $pdf->Cell(40, 10, $label, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(0);
        $pdf->SetDrawColor(189, 195, 199); // Gris claro para líneas
        $pdf->Cell($width, 10, $value, 'B', 1);
        $pdf->Ln(5);
    }

    // Creación del objeto de la clase heredada con todos los parámetros requeridos
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->Ln(5);

    // Configurar color de fondo para los campos
    $pdf->SetFillColor(220, 220, 220);

    // Título del formulario
    addTitle($pdf, 'PERFIL ECONOMICO');

    // Sección de información personal
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(41, 128, 185); // Azul para subtítulos
    $pdf->Ln(-5);
    $pdf->Cell(0, 10, 'Informacion Personal', 0, 1);
    $pdf->Ln(5);

    // Campos de información personal
    addField($pdf, 'Nombre Completo:', $shortNameCli, 100);
    addField($pdf, 'Numero de Identidad:', $dpiCli, 100);
    addField($pdf, 'Direccion:', $direccionCli, 100);
    addField($pdf, 'Numero Tel.:', $tel_no1Cli, 50);
    addTitle($pdf, 'Ingresos del Cliente');

    // Encabezados de la tabla
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(0, 0, 0); // Color de texto negro
    $pdf->SetFillColor(255, 255, 255); // Color de fondo blanco para las celdas
    $pdf->SetDrawColor(0, 0, 0); // Color de borde negro para las celdas
    $w = [10, 50, 60, 30, 30]; // Anchos de las columnas
    $header = ['#', 'Nombre Empresa', 'Direccion', 'Sueldo Base', 'Puesto'];

    foreach ($header as $i => $col) {
        $pdf->Cell($w[$i], 10, $col, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Datos de la tabla
    $pdf->SetFont('Helvetica', '', 10);

    foreach ($incomeData as $row) {
        // Obtener altura máxima de la fila
        $h = 10; // Altura base
        $nbLines = $pdf->GetStringWidth($row['direcnegocio']) / $w[2]; // Calcular cuántas líneas ocupa
        $nbLines = ceil($nbLines); // Redondear
        $h = max($h, $nbLines * 5); // Asegurar que la altura mínima sea suficiente

        $h = 10; // Altura base
        $nbLines = $pdf->GetStringWidth($row['nombreempresa']) / $w[2]; // Calcular cuántas líneas ocupa
        $nbLines = ceil($nbLines); // Redondear
        $h = max($h, $nbLines * 5); // Asegurar que la altura mínima sea suficiente



        // Posición inicial de la fila
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Imprimir cada celda asegurando que no se sobrepongan
        $pdf->Cell($w[0], $h, $row['tipoingreso'], 1);
        $pdf->Cell($w[1], $h, Utf8::decode($row['nombreempresa']), 1);

        // Celda con MultiCell para el campo largo (direcnegocio)
        $pdf->SetXY($x + $w[0] + $w[1], $y); // Ajustar posición manualmente
        $pdf->MultiCell($w[2], 5, Utf8::decode($row['direcnegocio']), 1);   
        // Ajustar posición de las siguientes celdas para que alineen correctamente
        $pdf->SetXY($x + $w[0] + $w[1] + $w[2], $y);
        $pdf->Cell($w[3], $h, number_format($row['sueldobase'], 2), 1, 0, 'R');
        $pdf->Cell($w[4], $h, Utf8::decode($row['puesto']), 1);
        // Nueva línea
        $pdf->Ln($h);
    }


    // Fin del documento
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'generado correctamente',
        'namefile' => "Perfil_economico",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
