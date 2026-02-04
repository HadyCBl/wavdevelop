<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';
date_default_timezone_set('America/Guatemala');

mysqli_set_charset($conexion, "utf8");

$datos = $_POST["datosval"];
$archivo = $datos[3];
$tipo = $_POST["tipo"];
$ccodcta = $archivo[0];

// Validate account code
if (empty($ccodcta)) {
    die('Código de cuenta no proporcionado');
}

// Fetch account details with client name
$accountQuery = mysqli_query($conexion, "SELECT a.*, c.short_name as cnomcli, c.no_identifica as dpi, c.Direccion as direccion, c.tel_no1 as telefono, c.no_tributaria as nit
                                         FROM aprcta a
                                         INNER JOIN tb_cliente c ON a.ccodcli = c.idcod_cliente
                                         WHERE a.ccodaport='$ccodcta'");
if (!$accountQuery) {
    die('Error en la consulta: ' . mysqli_error($conexion));
}

$accountDetails = mysqli_fetch_array($accountQuery, MYSQLI_ASSOC);

// Check if account exists
if (!$accountDetails) {
    die('No se encontraron detalles para la cuenta: ' . $ccodcta);
}

// Ensure cnomcli exists
if (!isset($accountDetails['cnomcli']) || empty($accountDetails['cnomcli'])) {
    $accountDetails['cnomcli'] = 'Cliente no identificado';
}

// Fetch beneficiaries - Fixed database reference for tb_parentesco
$beneficiariesQuery = mysqli_query($conexion, "SELECT b.*, p.descripcion as parentesco_desc
                                              FROM aprben b
                                              LEFT JOIN tb_parentescos p ON b.codparent = p.id
                                              WHERE b.codaport='$ccodcta'
                                              ORDER BY b.id_ben ASC");
if (!$beneficiariesQuery) {
    die('Error al consultar beneficiarios: ' . mysqli_error($conexion));
}

// Check if there are beneficiaries
if (mysqli_num_rows($beneficiariesQuery) == 0) {
    die('No hay beneficiarios registrados para esta cuenta');
}

// Store beneficiaries in an array to avoid resource issues
$beneficiaries = [];
while ($row = mysqli_fetch_assoc($beneficiariesQuery)) {
    $beneficiaries[] = $row;
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
$telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
$nitins = $info[0]["nit"];
$nom_cor = $info[0]["nomb_cor"];
$rutalogomicro = "../../../includes/img/logomicro.png";
$rutalogoins = "../../.." . $info[0]["log_img"];
$hoy2 = date("Y-m-d");
$fechaletra = fechletras($hoy2);
// Generate the PDF
class PDF extends FPDF
{
    public $accountDetails;
    public $beneficiaries;

    // Define colors
    private $primaryColor = [41, 128, 185]; // Blue
    private $secondaryColor = [44, 62, 80]; // Dark blue/gray
    private $accentColor = [231, 76, 60];   // Red
    private $lightGray = [236, 240, 241];   // Light gray

    public $institucion;
    public $pathlogo;
    public $pathlogoins;
    public $oficina;
    public $direccion;
    public $email;
    public $telefono;
    public $nit;
    public $direccionins;
    public $fechaletra;
    public $nom_cor;

    public function __construct($accountDetails, $beneficiaries, $institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $direccionins, $fechaletra,$nom_cor)
    {
        parent::__construct();
        $this->accountDetails = $accountDetails;
        $this->beneficiaries = $beneficiaries;
        $this->institucion = $institucion;
        $this->pathlogo = $pathlogo;
        $this->pathlogoins = $pathlogoins;
        $this->oficina = $oficina;
        $this->direccion = $direccion;
        $this->email = $email;
        $this->telefono = $telefono;
        $this->nit = $nit;
        $this->direccionins = $direccionins;
        $this->fechaletra = $fechaletra;
        $this->nom_cor = $nom_cor;
    }

    function Header()
    {
        $fuente = "Courier";
        $tamanioTitulo = 10;
        $tamanio_linea = 4; //altura de la linea/celda
        $ancho_linea = 30; //anchura de la linea/celda
        $ancho_linea2 = 20; //anchura de la linea/celda
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
        $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
        // Salto de línea
        $this->Ln(10);
    }

    function AccountInfo()
    {
        // Add a styled box for account info
        // $this->SetFillColor($this->lightGray[0], $this->lightGray[1], $this->lightGray[2]);
        // $this->Rect(10, 35, 190, 25, 'F');

        $this->SetY(35);
        $this->SetFont('Arial', 'B', 12);
        // $this->SetTextColor($this->secondaryColor[0], $this->secondaryColor[1], $this->secondaryColor[2]);
        $this->Cell(0, 8, 'SOLICITUD DE INGRESO COMO ASOCIADO A LA COOPERATIVA', 1, 1, 'C');
        $this->Ln(2);

        $this->SetFont('Arial', 'B', 8);
        $this->MultiCell(0, 4, 
            "Por este medio solicito al Consejo de " . decode_utf8("administración") . " ser admitido como asociado de '" . $this->institucion . "', para lo cual me comprometo a cumplir con las disposiciones vigentes de los Estatutos y la Ley General de Cooperativas DECRETO NUMERO 82-78 y sus reglamentos."
        , 0, 'L');

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(55, 6, 'NOMBRES Y APELLIDOS: ', 'LT', 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, mb_convert_encoding($this->accountDetails['cnomcli'], 'ISO-8859-1', 'UTF-8'), 'RT', 1);

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(55, 6, 'CUI:', 'L', 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, $this->accountDetails['dpi'], 'R', 1);

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(55, 6, 'DIRECCION RESIDENCIAL:', 'L', 0);
        $this->SetFont('Arial', '', 11);
        $direccion = isset($this->accountDetails['direccion']) && !empty($this->accountDetails['direccion']) ? $this->accountDetails['direccion'] : ' ';
        $this->Cell(0, 6, mb_convert_encoding($direccion, 'ISO-8859-1', 'UTF-8'), 'R', 1);

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(55, 6, 'TELEFONO:', 'L', 0);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, $this->accountDetails['telefono'], 'R', 1);

        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(55, 6, 'NIT:', 'LB', 0);
        $this->SetFont('Arial', '', 11);
        $nit = isset($this->accountDetails['nit']) && !empty($this->accountDetails['nit']) ? $this->accountDetails['nit'] : ' ';
        $this->Cell(0, 6, $nit, 'RB', 1);
        $this->Ln(1);

        // $this->SetFont('Arial', 'B', 8);
        // $this->MultiCell(0, 4, mb_convert_encoding(
        //     "De ser admitido me comprometo a cumplir con los compromisos tales como : Cancelación de mi Aportacion, Cuota de Ingreso y la apertura de una cuenta de Ahorro Corriente con deposito inicial de: Cincuenta quetrales exactos 00/100 (0. 50.00). ",
        //     'ISO-8859-1',
        //     'UTF-8'
        // ), 0, 'L');
        // $this->Ln(1);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, 'NUMERO DE CUENTA DE APORTACION: ' . $this->accountDetails['ccodaport'], 0, 1, 'L');
        // $this->SetFont('Arial', '', 11);
        // $this->Cell(0, 6, 'APORTACION NOMINATIVA DE: Doscientos quetzales exactos 00/100 (Q.200.00) ', 0, 1, 'L');

    }

    function BeneficiariesTable()
    {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, ' ', 'T', 1);



        // $this->SetFont('Arial', 'B', 10);
        // $this->Cell(70, 8, 'Nombre', 1, 0, 'C');
        // $this->Cell(40, 8, 'Parentesco', 1, 0, 'C');
        // // $this->Cell(30, 8, 'Teléfono', 1, 0, 'C', true);
        // $this->Cell(30, 8, 'Porcentaje', 1, 1, 'C');

        // Table data with alternating row colors
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $totalPorcentaje = 0;
        $rowCount = 1;

        foreach ($this->beneficiaries as $row) {
            // // Alternate row colors
            // if ($rowCount % 2 == 0) {
            //     $this->SetFillColor(255, 255, 255);
            // } else {
            //     $this->SetFillColor($this->lightGray[0], $this->lightGray[1], $this->lightGray[2]);
            // }

            $nombre = isset($row['nombre']) ? mb_convert_encoding($row['nombre'], 'ISO-8859-1', 'UTF-8') : '';
            // $this->MultiCell(0, 4, mb_convert_encoding(
            //     "En caso de fallecimiento y otro prablema de incapacidad, dejo como beneficiario unico de mi participacion como asociado/a a: {$nombre}.",
            //     'ISO-8859-1',
            //     'UTF-8'
            // ), 0, 'L');

            $parentesco = isset($row['parentesco_desc']) ? mb_convert_encoding($row['parentesco_desc'], 'ISO-8859-1', 'UTF-8') : $row['codparent'];
            $telefono = isset($row['telefono']) ? $row['telefono'] : '';
            $porcentaje = isset($row['porcentaje']) ? $row['porcentaje'] : '0';
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(35, 7, "BENEFICIARIO ({$rowCount}):", 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 7, $nombre, 'B', 1, 'L');
            $this->Cell(35, 7, " ", 0, 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(29, 7, "PARENTESCO: ", 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(40, 7, $parentesco, 'B', 0, 'C');
            // $this->Cell(30, 7, $telefono, 1, 0, 'C');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(43, 7, "   % DEL BENEFICIARIO: ", 0, 0, 'L');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 7, $porcentaje . '%', 'B', 1, 'C');

            $totalPorcentaje += floatval($porcentaje);
            $rowCount++;
            $this->Ln(5);
        }

        // Total%
        // $this->SetFont('Arial', 'B', 10);
        // $this->Cell(156, 8, 'Total', 0, 0, 'R');
        // $this->Cell(30, 8, $totalPorcentaje . '%', 0, 1, 'C');
        // $this->Ln(2);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, $this->direccionins . ', ' . $this->fechaletra, 'T', 1, 'L');
        $this->Ln(5);

        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 8, ' ', 0, 0, 'C');
        $this->MultiCell(125, 4, mb_convert_encoding(
            "Yo, " . $this->accountDetails['cnomcli'] . "\nDeclaro bajo protesta de decir verdad que los datos asentados en la presente solicitud son correctos y fueron presentados por mi persona. Para usos legales que correspondan, dejo la impresión dactilar de mi dedo pulgar derecho y firmo.",
            'ISO-8859-1',
            'UTF-8'
        ), 0, 'L');

        $this->Ln(8);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(60, 8, ' ', 0, 0, 'L');
        $this->Cell(30, 30, ' ', 1, 0, 'L');
        $this->Ln(30);
        $this->Cell(60, 8, 'F._________________________', 0, 0, 'L');
        $this->Cell(30, 4, 'Huella', 0, 0, 'C');
        $this->Cell(50, 8, ' ', 0, 0, 'L');
        // $this->Cell(30, 4, 'Sello: ' . $this->nom_cor, 0, 1, 'C');
        $this->Ln(5);
        $this->Cell(150, 8, mb_convert_encoding($this->accountDetails['cnomcli'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        // $this->Ln(5);
  

        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(100, 8, ' ', 0, 0, 'L');
        $this->Cell(0, 8, 'F._________________________', 0, 1, 'R');
        // $this->Cell(100, 8, ' ', 0, 0, 'L');
        $this->Cell(0, 8, 'Gerente de Agencia          ', 0, 1, 'R');

    
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 8, 'OBSERVACIONES:', 0, 1, 'L');
        $this->Cell(0, 8, ' ', 'T', 1, 'L');
        $this->Cell(0, 8, ' ', 'T', 1, 'L');
    }
    

    function Footer()
    {
        $this->SetY(-20);

        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, decode_utf8('Página ') . $this->PageNo() . '/{nb}', 'T', 0, 'C');
    }
}
$pdf = new PDF($accountDetails, $beneficiaries, $institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $direccionins, $fechaletra,$nom_cor);
$pdf->SetMargins(20, 10, 10);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->AccountInfo();
$pdf->BeneficiariesTable();
ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Comprobante generado correctamente',
    'namefile' => "aho_bene",
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData)
);
echo json_encode($opResult);

// try {
//     // Clear any output before sending PDF
//     ob_end_clean();

//     $pdf = new PDF($accountDetails, $beneficiaries);
//     $pdf->AliasNbPages();
//     $pdf->AddPage();
//     $pdf->AccountInfo();
//     $pdf->BeneficiariesTable();

//     // Force download instead of inline display
//     $pdf->Output('D', 'Beneficiarios_Cuenta.pdf');
//     exit;
// } catch (Exception $e) {
//     ob_end_clean();
//     echo 'Error al generar el PDF: ' . $e->getMessage();
// } finally {
//     mysqli_close($conexion);
// }
?>