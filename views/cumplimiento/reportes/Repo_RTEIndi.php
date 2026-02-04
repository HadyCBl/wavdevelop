<?php
session_start();

// Opcional: desactivar la salida de errores para evitar que se impriman antes del JSON
// error_reporting(E_ALL & ~E_NOTICE);
// ini_set('display_errors', '0');

// Carga de FPDF (ajusta la ruta según tu proyecto):
require '../../../fpdf/fpdf.php';
// Si usas Autoload de Composer, descomenta:
// require "../../../vendor/autoload.php";

// Incluye aquí tus configuraciones y funciones:
include __DIR__ . '/../../../includes/Config/database.php';
include __DIR__ . '/../../../src/funcphp/func_gen.php';

date_default_timezone_set('America/Guatemala');

// ---------------------------------------------------------------------------------
// Verificar sesión
// ---------------------------------------------------------------------------------
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada']);
    return;
}

// ---------------------------------------------------------------------------------
// Recibir y procesar datos de POST
// ---------------------------------------------------------------------------------
$datos = $_POST["datosval"];
$inputs = $datos[0]; // Se espera que $inputs[0] contenga el código de cuenta
$ccdocta = $inputs[0];



// ---------------------------------------------------------------------------------
// Conexión a la base de datos
// ---------------------------------------------------------------------------------
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$database->openConnection();

// ---------------------------------------------------------------------------------
// Obtener información de la agencia e institución
// ---------------------------------------------------------------------------------
$sqlInfo = "
    SELECT * 
      FROM " . $db_name_general . ".info_coperativa ins
      INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop 
     WHERE ag.id_agencia = ?
     LIMIT 1
";
$info = $database->getAllResults($sqlInfo, [$_SESSION['id_agencia']]);
if (empty($info)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institución asignada a la agencia no encontrada']);
    return;
}
$institucion   = $info[0]["nomb_comple"];
$direccion     = $info[0]["muni_lug"];
$email         = $info[0]["emai"];
$telefonos     = $info[0]["tel_1"] . ' / ' . $info[0]["tel_2"];
$nit           = $info[0]["nit"];
$rutalogoins   = "../../.." . $info[0]["log_img"];

// ---------------------------------------------------------------------------------
// Consultar tb_RTE_use por el código de cuenta ($ccdocta)
// Se incluyen Crateby y Cretadate para conocer quién creó la operación y cuándo
// ---------------------------------------------------------------------------------
$sql = "
    SELECT 
        Id_RTE,
        ccdocta,
        DPI,
        ori_fondos,
        desti_fondos,
        Mon,
        propietario,
        Crateby,
        Cretadate,
        CONCAT_WS(' ', Nombre1, Nombre2, Nombre3) AS Nombre,
        CONCAT_WS(' ', Apellido1, Apellido2, Apellido_de_casada) AS Apellidos,
        CASE 
            WHEN propietario = 1 THEN 'Si' 
            ELSE 'No'
        END AS trasaccion
    FROM tb_RTE_use
    WHERE ccdocta = ?
      AND IFNULL(Deletedate, '') = ''
    LIMIT 1
";
$results = $database->getAllResults($sql, [$ccdocta]);
$record = (!empty($results)) ? $results[0] : null;
if (empty($record)) {
    echo json_encode([
        'status'  => 0, 
        'mensaje' => 'No se encontró el registro en tb_RTE_use para la cuenta: ' . $ccdocta
    ]);
    return;
}
//Monto en quetzales 
$oriFondo=$record['ori_fondos'];
$destiFondo=$record['desti_fondos'];
$MonQ = $record['Mon'];
$MonD = round($record['Mon'] / 7.8, 2);
$trasDate=$record['Cretadate'];
// Definir la variable $lugarFecha (si no se recibe de otra parte, se le asigna un valor predeterminado)
$lugarFecha = $institucion . ' ' . $trasDate; // Concatenar con un espacio entre los textos
// ---------------------------------------------------------------------------------
// Si el registro es de propietario, obtener información del cliente a través de ahomcta y tb_cliente
// ---------------------------------------------------------------------------------
$cliente = null;
if ($record['propietario'] == 1) {
    $sqlAho = "
        SELECT ccodcli
        FROM ahomcta
        WHERE ccodaho = ?
        LIMIT 1
    ";
    $resAho = $database->getAllResults($sqlAho, [$ccdocta]);
    if (!empty($resAho)) {
        $id_cliente = $resAho[0]['ccodcli'];
        $sqlCliente = "
            SELECT 
                idcod_cliente,
                compl_name,
                no_identifica
            FROM tb_cliente
            WHERE idcod_cliente = ?
            LIMIT 1
        ";
        $resCliente = $database->getAllResults($sqlCliente, [$id_cliente]);
        if (!empty($resCliente)) {
            $cliente = $resCliente[0];
        }
    }
}
$database->closeConnection();

// ---------------------------------------------------------------------------------
// Obtener información de la moneda desde variables de entorno
// ---------------------------------------------------------------------------------
$monedaId = getenv('MONEDAID');
$monedaDefault = getenv('DEFAULT_CURRENCY');
$monedaSingular = getenv('DEFAULT_CURRENCY_SINGULAR');
$monedaPlural = getenv('DEFAULT_CURRENCY_PLURAL');
$monedaCentSingular = getenv('DEFAULT_CURRENCY_CENT_SINGULAR');
$monedaCentPlural = getenv('DEFAULT_CURRENCY_CENT_PLURAL');
$conversionMoneda = floatval(getenv('CONERVSION_MONEDA'));
$simboloMoneda = getenv('SYMBOL_CURRENCY');

// ---------------------------------------------------------------------------------
// Preparar datos para el PDF
// ---------------------------------------------------------------------------------
$esPropietario = ($record['propietario'] == 1) ? 'Si' : 'No';
if ($esPropietario == 'Si' && !empty($cliente)) {
    $nombreCompleto = $cliente['compl_name'];
    $identificacion = $cliente['no_identifica'];
} else {
    $nombreCompleto = trim($record['Nombre'] . ' ' . $record['Apellidos']);
    $identificacion = $record['DPI'];
}
if ($esPropietario == 'No') {
    $creadoPor      = $record['Crateby'];
    $fechaOperacion = $record['Cretadate'];
} else {
    $creadoPor      = 'N/A';
    $fechaOperacion = 'N/A';
}

// ---------------------------------------------------------------------------------
// Clase para generar el PDF con FPDF
// ---------------------------------------------------------------------------------
class PDF_Formulario extends FPDF
{
    public $widthTotal    = 190; 
    public $colorHeader   = [50, 100, 200];
    public $colorVerde    = [153, 255, 153];
    public $colorAzul     = [173, 216, 230];
    public $colorAmarillo = [255, 229, 153];
    
    public $institucion;
    public $direccion;
    public $email;
    public $telefonos;
    public $nit;
    public $rutalogoins;
    public $monedaDefault;
    public $simboloMoneda;
    
    function Header()
    {
        if (!empty($this->rutalogoins) && file_exists($this->rutalogoins)) {
            $this->Image($this->rutalogoins, 10, 10, 30);
        }
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor($this->colorHeader[0], $this->colorHeader[1], $this->colorHeader[2]);
        $this->SetTextColor(255,255,255);
        $this->SetFont('Arial','B',9);
        $this->SetTextColor(0,0,0);
        $this->Ln(2);
        $this->Cell($this->widthTotal,5,iconv('UTF-8','ISO-8859-1//TRANSLIT',$this->institucion),0,1,'C');
        $this->SetFont('Arial','',8);
        $this->Cell($this->widthTotal,5,iconv('UTF-8','ISO-8859-1//TRANSLIT',$this->direccion),0,1,'C');
        $this->Cell($this->widthTotal,5,iconv('UTF-8','ISO-8859-1//TRANSLIT','Email: '.$this->email.' | Tel: '.$this->telefonos),0,1,'C');
        $this->Cell($this->widthTotal,5,iconv('UTF-8','ISO-8859-1//TRANSLIT','NIT: '.$this->nit),'B',1,'C');
        $this->Ln(3);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(0,0,0);
        $this->Cell(0,10,iconv('UTF-8','ISO-8859-1//TRANSLIT','Página ').$this->PageNo(),0,0,'C');
    }
    
    function SeccionTitulo($titulo, $fillColor = null)
    {
        if(!$fillColor) {
            $fillColor = $this->colorVerde;
        }
        $this->SetFillColor($fillColor[0],$fillColor[1],$fillColor[2]);
        $this->SetFont('Arial','B',9);
        $this->SetTextColor(0,0,0);
        $this->Cell($this->widthTotal,7,iconv('UTF-8','ISO-8859-1//TRANSLIT',$titulo),1,1,'L',true);
        $this->Ln(1);
    }
    
    function EtiquetaValor($etiqueta, $valor, $labelWidth=70, $valueWidth=120)
    {
        $this->SetFont('Arial','',8);
        $this->SetFillColor(255,255,255);
        $this->Cell($labelWidth,6,iconv('UTF-8','ISO-8859-1//TRANSLIT',$etiqueta),1,0,'L',true);
        $this->Cell($valueWidth,6,iconv('UTF-8','ISO-8859-1//TRANSLIT',$valor),1,1,'L',true);
    }
    
    function Opcion($texto, $seleccionado = false, $width = 50)
    {
        $this->SetFont('Arial','',8);
        $this->Cell(6,6,($seleccionado ? 'X' : ' '),1,0,'C');
        $this->Cell($width-6,6,iconv('UTF-8','ISO-8859-1//TRANSLIT',$texto),1,0,'L');
    }
}

// ---------------------------------------------------------------------------------
// Generar el PDF
// ---------------------------------------------------------------------------------
$pdf = new PDF_Formulario('P','mm','Letter');
$pdf->institucion  = $institucion;
$pdf->direccion    = $direccion;
$pdf->email        = $email;
$pdf->telefonos    = $telefonos;
$pdf->nit          = $nit;
$pdf->rutalogoins  = $rutalogoins;
$pdf->monedaDefault = $monedaDefault;
$pdf->simboloMoneda = $simboloMoneda;
$pdf->AddPage();

// Sección: Información de la Transacción
$pdf->SeccionTitulo('Información de la Transacción', $pdf->colorVerde);
$pdf->EtiquetaValor('ID de la transacción:', $record['Id_RTE'] . '-106');
$pdf->EtiquetaValor('Lugar y fecha de la transacción:', $lugarFecha);
$pdf->EtiquetaValor('Denominación social:', "");
$pdf->EtiquetaValor('Nombre de la central, sucursal o agencia:', '');
$pdf->EtiquetaValor('Número de cuenta:', $record['ccdocta']);
if ($esPropietario == 'No') {
    $pdf->EtiquetaValor('Creado por:', $creadoPor);
    $pdf->EtiquetaValor('Fecha de la operación:', $fechaOperacion);
}
$pdf->SetFont('Arial','B',8);
$pdf->Cell(70,6,iconv('UTF-8','ISO-8859-1//TRANSLIT','Tipo de cuenta:'),1,0,'L',true);
$pdf->Cell(120,6,'',1,1,'L'); 
$pdf->Opcion('Alto flujo de efectivo', false, 65);
$pdf->Opcion('Cuenta colectora', false, 65);
$pdf->Opcion('Otras Cuentas', true, 50);
$pdf->Ln(6);
$tipoTransaccion = "Depósito";
$pdf->EtiquetaValor('Tipo de transacción:', $tipoTransaccion);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(70,6,iconv('UTF-8','ISO-8859-1//TRANSLIT','Características de la transacción:'),1,0,'L',true);
$pdf->Cell(120,6,'',1,1,'L');

$pdf->EtiquetaValor('Tipo de moneda:', $monedaDefault);
$pdf->EtiquetaValor('Monto de la transacción:', $simboloMoneda . ' ' . number_format($MonQ, 2));
$pdf->EtiquetaValor('Monto en dólares:', '$ ' . number_format($MonD, 2));
$pdf->EtiquetaValor('Procedencia de los fondos:', $oriFondo);
$pdf->EtiquetaValor('Finalidad de la transacción:', $destiFondo);

// Sección: Información del Cliente
$pdf->Ln(3);
$pdf->SeccionTitulo('Información del Cliente', $pdf->colorAzul);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(70,6,iconv('UTF-8','ISO-8859-1//TRANSLIT','Tipo de cliente:'),1,0,'L',true);
$pdf->Cell(120,6,'Individual',1,1,'L');
$pdf->EtiquetaValor('Nombre Completo:', $nombreCompleto);
$pdf->EtiquetaValor('Número de identificación:', $identificacion);

// Sección: Información de la persona física que realiza la transacción
$pdf->Ln(3);
$pdf->SeccionTitulo('Información de la persona física que realiza la transacción', $pdf->colorAmarillo);
$pdf->EtiquetaValor('Nombre Completo:', $nombreCompleto);
$pdf->EtiquetaValor('Tipo de identificación:', 'DPI');
$pdf->EtiquetaValor('Número de identificación:', $identificacion);
$pdf->EtiquetaValor('Nacionalidad:', 'GUATEMALTECA');

// Sección: Firmas
$pdf->Ln(8);
$pdf->SetFont('Arial','',9);
$wFirma = 95;
$pdf->Cell($wFirma,5,iconv('UTF-8','ISO-8859-1//TRANSLIT','Firma de la persona que realiza la transacción'),0,0,'C');
$pdf->Cell($wFirma,5,iconv('UTF-8','ISO-8859-1//TRANSLIT','Firma y código del empleado responsable'),0,1,'C');
$pdf->Ln(10);
$pdf->Cell($wFirma,5,'_________________________',0,0,'C');
$pdf->Cell($wFirma,5,'_________________________',0,1,'C');

// ---------------------------------------------------------------------------------
// Salida del PDF en Base64 (para retornar JSON)
// ---------------------------------------------------------------------------------
ob_start();
$pdf->Output('I');
$pdfData = ob_get_contents();
ob_end_clean();

// Es importante que no se envíe ninguna salida adicional (espacios, líneas en blanco, etc.)
echo json_encode([
    'status'  => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile'=> "formulario_transaccion",
    'tipo'    => "pdf",
    'data'    => "data:application/pdf;base64," . base64_encode($pdfData)
]);
?>
