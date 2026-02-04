<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
    return;
}

$act = $_POST["activo"];
$finicio = $_POST["finicio"];
$ffin = $_POST["ffin"];
$ainicio = $_POST["ainicio"];
$afin = $_POST["afin"];
$cha = $_POST["checkalta"];
$chb = $_POST["checkbaja"];

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
$stra = "";
if ($cha == "true") {
    $stra = " AND (DATE(fecha_alta) BETWEEN '" . $ainicio . "' AND '" . $afin . "')";
}
$strb = "";
if ($chb == "true") {
    $strb = " AND (DATE(fecha_baja) BETWEEN '" . $finicio . "' AND '" . $ffin . "')";
}
$strquery = "SELECT 
IFNULL((SELECT cdescri FROM $db_name_general.tn_EtniaIdioma WHERE Id_EtinIdiom=cli.idioma),'-') idiomades, age.nom_agencia,
cli.* FROM tb_cliente cli
INNER JOIN tb_agencia age on age.cod_agenc=cli.agencia
 WHERE `id_tipoCliente`='NATURAL' ";
if ($act == "0") {
    $strquery = $strquery . " AND `estado` = '0' " . $stra . $strb;
} else {
    $strquery = $strquery . " AND `estado` = '1' " . $stra;
}

$queryingr = mysqli_query($conexion, $strquery);

if (mysqli_num_rows($queryingr) > 0) {
    // Almacenar los datos en un array
    $incomeData = [];
    while ($row = mysqli_fetch_assoc($queryingr)) {
        $incomeData[] = $row;
    }

    // Datos de la institución
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
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
            parent::__construct('L');
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
            $this->Ln(20);
        }

        // Pie de página
        function Footer()
        {
            $this->SetY(-15);
            // $this->Image($this->pathlogo, 165, 275, 20);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
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
    addTitle($pdf, 'REPORTE-CLIENTES');
    $pdf->Ln(-5);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetTextColor(0, 0, 0); // Color de texto negro
    $pdf->SetFillColor(255, 255, 255); // Color de fondo blanco para las celdas
    $pdf->SetDrawColor(0, 0, 0); // Color de borde negro para las celdas
    $maxLength = 35;
    $maxLengthD = 35;
    
    // Encabezados de la tabla
    $header = ['CODIGO CLIENTE', 'NOMBRE CLIENTE', 'DPI', 'FECHA NAC.',  'DIRECCION', 'TELEFONO',   'AGENCIA'];
    $w = [25, 70, 30, 30,   70, 20,  30]; // Ajusta el ancho de las celdas
        // Encabezados de la tabla
        foreach ($header as $i => $col) {
            $pdf->Cell($w[$i], 10, $col, 1, 0, 'C');
        }
        $pdf->Ln();

        // Datos de la tabla
        $pdf->SetFont('Helvetica', '', 8);
        
        foreach ($incomeData as $row) {
            $pdf->Cell($w[0], 10, $row['idcod_cliente'] ?: '-', 1);
        
            // Nombre
            $shortName = decode_utf8($row['short_name']);
            $truncatedName = mb_strimwidth($shortName, 0, $maxLength, '...', 'UTF-8');
            $truncatedName = str_pad($truncatedName, $maxLength, ' ', STR_PAD_RIGHT);
            $pdf->Cell($w[1], 10, $truncatedName ?: '-', 1);
            // Identificación
            $pdf->Cell($w[2], 10, decode_utf8($row['no_identifica']) ?: '-', 1);
            // Fecha de nacimiento
            $pdf->Cell($w[3], 10, $row['date_birth'] ?: '-', 1, 0, 'C');

            // Dirección
            $direccion = decode_utf8($row['Direccion']);
            $truncatedDireccion = mb_strimwidth($direccion, 0, $maxLengthD, '...', 'UTF-8');
            $truncatedDireccion = str_pad($truncatedDireccion, $maxLengthD, ' ', STR_PAD_RIGHT);
            $pdf->Cell($w[4], 10, $truncatedDireccion ?: '-', 1);
        
            // Teléfonos
            $pdf->Cell($w[5], 10, ($row['tel_no1']) ?: '-', 1);

            // Agencia
            $pdf->Cell($w[6], 10, decode_utf8($row['nom_agencia']) ?: '-', 1);
        
            $pdf->Ln();
        }

        // Fin del documento
        ob_start();
        $pdf->Output();
        $pdfData = ob_get_contents();
        ob_end_clean();

        $opResult = array(
            'status' => 1,
            'mensaje' => 'PDF generado correctamente',
            'namefile' => "Perfil_economico",
            'tipo' => "pdf",
            'data' => "data:application/pdf;base64," . base64_encode($pdfData)
        );
        echo json_encode($opResult);

    } else {
        echo json_encode(['status' => 0, 'mensaje' => 'No se encontraron registros para los parámetros proporcionados']);
    }
?>