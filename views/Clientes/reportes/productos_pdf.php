<?php

use Matrix\Decomposition\Decomposition;

session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';
require "../../../vendor/autoload.php";


if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
    return;
}

$codcli = $_POST["datosval"][3][0];

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


$shortNameCli = 0;

// Consulta datos del cliente
$querycli = mysqli_query($conexion, "SELECT * FROM tb_cliente WHERE idcod_cliente = '$codcli'");
if (mysqli_num_rows($querycli) > 0) {
    $row = mysqli_fetch_assoc($querycli);
    $shortNameCli = decode_utf8($row['short_name']);
    $dpiCli = $row['no_identifica'];
    $direccionCli = decode_utf8($row['Direccion']);
    $tel_no1Cli = $row['tel_no1'];
} else {
    echo json_encode(['status' => 0, 'mensaje' => 'No se encontraron resultados']);
    return;
}

// consultas
    $consulta1 = mysqli_query($conexion, "SELECT 
    'Ahorro' AS tipo, 
    aht.nombre AS descripcion, 
    aho.fecha_apertura AS fechapert,
    aho.ccodaho AS cuenta, 
    calcular_saldo_aho_tipcuenta(aho.ccodaho,'".date("Y-m-d")."') AS saldo,
    aho.estado AS estado,
    CASE 
        WHEN aho.estado = 'B' THEN 'Inactivo'
        WHEN aho.estado = 'A' THEN 'Vigente'
        WHEN aho.estado = 'X' THEN 'Eliminado'
        ELSE 'Desconocido' 
    END AS Estado_descrip
FROM 
    ahomcta aho
INNER JOIN 
    tb_cliente cl ON aho.ccodcli = cl.idcod_cliente
INNER JOIN 
    ahomtip aht ON aht.ccodtip = SUBSTR(aho.ccodaho, 7, 2)
WHERE 
    aho.estado IN ('A', 'B', 'X') 
    AND cl.idcod_cliente = '$codcli'");
    
    $consulta2 = mysqli_query($conexion, "SELECT 'Aportación' AS tipo, apt.nombre AS descripcion, apr.fecha_apertura AS fechapert, apr.ccodaport AS cuenta, calcular_saldo_apr_tipcuenta(apr.ccodaport,'".date("Y-m-d")."') AS saldo, apr.estado AS estado,CASE 
    WHEN apr.estado = 'B' THEN 'Inactivo'
    WHEN apr.estado = 'A' THEN 'Vigente'
    WHEN apr.estado = 'X' THEN 'Eliminado'
    ELSE 'Desconocido' 
    END AS Estado_descrip FROM aprcta apr INNER JOIN tb_cliente cl ON apr.ccodcli = cl.idcod_cliente INNER JOIN aprtip apt ON apt.ccodtip = apr.ccodtip
    WHERE apr.estado IN ('A', 'B', 'X')  AND cl.idcod_cliente = '$codcli'");
    
    $consulta3 = mysqli_query($conexion, "SELECT 'Crédito' AS tipo, pr.descripcion AS descripcion, cm.DFecDsbls AS fechapert, cm.CCODCTA AS cuenta, cm.MonSug AS saldo ,cm.Cestado AS estado,  COALESCE(ge.EstadoCredito, '-?') AS Estado_descrip
    FROM cremcre_meta cm 
    LEFT JOIN $db_name_general.tb_estadocredito ge ON ge.id_EstadoCredito = cm.Cestado
    INNER JOIN cre_productos pr ON cm.CCODPRD = pr.id  AND cm.CodCli = '$codcli'");
    
    if (!$consulta1 || !$consulta2 || !$consulta3) {
        echo json_encode(['status' => 0, 'mensaje' => 'Error en consultas de productos: ' . mysqli_error($conexion)]);
        return;
    }

     // Array para almacenar los datos
     $datos = [];
     $i = 0;
 
     // Unificar resultados de consulta 1
     while ($fila = mysqli_fetch_array($consulta1, MYSQLI_ASSOC)) {
         $fila['numero'] = $i + 1;
         $fila['descripcion'] = decode_utf8($fila['descripcion']); // Codificar descripción en UTF-8
         $datos[$i] = $fila;
         $i++;
     }
 
     // Unificar resultados de consulta 2
     while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
         $fila['numero'] = $i + 1;
         $fila['descripcion'] = decode_utf8($fila['descripcion']); // Codificar descripción en UTF-8
         $datos[$i] = $fila;
         $i++;
     }
 
     // Unificar resultados de consulta 3
     while ($fila = mysqli_fetch_array($consulta3, MYSQLI_ASSOC)) {
         $fila['numero'] = $i + 1;
         $fila['descripcion'] = decode_utf8($fila['descripcion']); // Codificar descripción en UTF-8
         $datos[$i] = $fila;
         $i++;
     }
    


    // Datos de la institución
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion =  encode_utf8($info[0]["nomb_comple"]);
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
    function addTitle($pdf, $title){
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetFillColor(52, 152, 219); // Azul moderno
        $pdf->SetTextColor(255);
        $pdf->Cell(0, 12, $title, 0, 1, 'C', true);
        $pdf->Ln(10);
    }

    function addField($pdf, $label, $value, $width = 0){
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
    addTitle($pdf, 'Reporte de Productos');

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
    addTitle($pdf, 'Productos');

$pdf->SetFont('Arial', 'B', 7);
$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(0, 0, 0); // Texto negro

// Encabezados de la tabla
$pdf->Cell(10, 10, '#', 1, 0, 'C', true);
$pdf->Cell(20, 10, 'Tipo', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Descripcion', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Cuenta', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Saldo (Q)', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Fecha Apertura', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Estado', 1, 1, 'C', true); // Cambia aquí


// Datos de la tabla
$pdf->SetFont('Arial', '', 7);

foreach ($datos as $dato) {
    $pdf->Cell(10, 10, $dato['numero'], 1, 0, 'C');
    $pdf->Cell(20, 10, decode_utf8($dato['tipo']), 1, 0, 'C');
    $pdf->Cell(50, 10, decode_utf8($dato['descripcion']), 1, 0, 'C');
    $pdf->Cell(30, 10, decode_utf8($dato['cuenta']), 1, 0, 'C');
    $pdf->Cell(30, 10, 'Q. ' . number_format($dato['saldo'], 2, '.', ','), 1, 0, 'C');
    $pdf->Cell(25, 10, $dato['fechapert'], 1, 0, 'C');
    $pdf->Cell(25, 10, decode_utf8($dato['Estado_descrip']), 1, 1, 'C'); // Mueve esto dentro del bucle
}

// Si deseas agregar un salto de línea adicional después de cada fila
    $pdf->Ln();
    // Fin del documento
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();
    $opResult = array(
        'status' => 1,
        'mensaje' => ' generado correctamente',
        'namefile' => "Perfil_economico",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);

?>