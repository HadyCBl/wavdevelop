<?php

use Micro\Models\Departamento;
use Micro\Models\Municipio;

session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

// Debug incoming data
error_log("Received data: " . print_r($_POST, true));

// Check if datosval is set and properly formatted
if (!isset($_POST['datosval'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos no recibidos', 'error_type' => 'missing_data']);
    return;
}

// Parse the JSON data
// $datos = json_decode($_POST['datosval'], true);
// if (!$datos) {
//     echo json_encode(['status' => 0, 'mensaje' => 'Error al decodificar datos', 'error_type' => 'json_error']);
//     return;
// }

// Check if client ID exists
// if (!isset($datos[3][0]) || empty($datos[3][0])) {
//     echo json_encode(['status' => 0, 'mensaje' => 'ID de cliente no proporcionado', 'error_type' => 'missing_id']);
//     return;
// }

// $codigo = mysqli_real_escape_string($conexion, $datos[3][0]);

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

$oficina = decode_utf8($info[0]["nom_agencia"]);
$institucion = decode_utf8($info[0]["nomb_comple"]);
$direccionins = decode_utf8($info[0]["muni_lug"]);
$emailins = $info[0]["emai"];
$telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
;
$nitins = $info[0]["nit"];
$rutalogomicro = "../../../includes/img/logomicro.png";
$rutalogoins = "../../.." . $info[0]["log_img"];


$usuario = $_SESSION['id'];


$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];

$codigo = $archivo[0];

$queryInfoCliente = "SELECT 
cl.*,
(IFNULL((SELECT pais.nombre FROM tb_paises pais WHERE cl.pais_nacio=pais.abreviatura LIMIT 1),'--')) AS pais_nacio1,
(IFNULL((SELECT pais2.nombre FROM tb_paises pais2 WHERE cl.nacionalidad=pais2.abreviatura LIMIT 1),'--')) AS nacionalidad1,
(IFNULL((SELECT pais3.nombre FROM tb_paises pais3 WHERE cl.otra_nacion=pais3.abreviatura LIMIT 1),'--')) AS nacionalidad2,
(IFNULL((SELECT ng1.Negocio FROM $db_name_general.tb_negocio ng1 WHERE cl.vivienda_Condi=ng1.id_Negocio LIMIT 1),'--')) AS vivienda2,
    
    /* References relationships */
    (SELECT p.descripcion 
     FROM tb_parentescos p 
     INNER JOIN tb_cliente_atributo ca ON CAST(ca.valor AS UNSIGNED) = p.id 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 1) as parentesco_ref1,
    (SELECT p.descripcion 
     FROM tb_parentescos p 
     INNER JOIN tb_cliente_atributo ca ON CAST(ca.valor AS UNSIGNED) = p.id
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 2) as parentesco_ref2,
    (SELECT p.descripcion 
     FROM tb_parentescos p 
     INNER JOIN tb_cliente_atributo ca ON CAST(ca.valor AS UNSIGNED) = p.id 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 3) as parentesco_ref3,
    
    /* Reference addresses */
    (SELECT ca.valor FROM tb_cliente_atributo ca 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 4) as direccion_ref1,
    (SELECT ca.valor FROM tb_cliente_atributo ca 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 5) as direccion_ref2,
    (SELECT ca.valor FROM tb_cliente_atributo ca 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 6) as direccion_ref3,
    
    /* Address references */
    (SELECT ca.valor FROM tb_cliente_atributo ca 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 7) as ref_direccion1,
    (SELECT ca.valor FROM tb_cliente_atributo ca 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 8) as ref_direccion2,
    (SELECT ca.valor FROM tb_cliente_atributo ca 
     WHERE ca.id_cliente = cl.idcod_cliente AND ca.id_atributo = 9) as ref_direccion3
    
FROM tb_cliente cl 
WHERE cl.idcod_cliente = '" . $codigo . "'";

// echo json_encode(['status' => 0, 'mensaje' => $queryInfoCliente]);
//     return;
$sql = mysqli_query($conexion, $queryInfoCliente);

$infocliente[] = [];
$j = 0;
while ($row = mysqli_fetch_array($sql)) {
    $infocliente[$j] = $row;
    $j++;
}

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Cliente no encontrado']);
    return;
}

// $infocliente = mysqli_fetch_array($sql, MYSQLI_ASSOC);

$consultaIngresos = mysqli_query($conexion, "SELECT * FROM tb_ingresos WHERE id_cliente = '" . $codigo . "'");

$nombre = $infocliente[0]['short_name'];

$rutaFoto = $infocliente[0]['url_img'];

$rutaFoto = __DIR__ . "/../../../../" . $rutaFoto;
if (!is_file($rutaFoto)) {
    $rutaFoto = '../../../includes/img/fotoClienteDefault.png';
}
$extension = pathinfo($rutaFoto, PATHINFO_EXTENSION);
// }
// ... your SQL query and fetch, add this code to assign the variables
// $infocliente = mysqli_fetch_array($sql);
// // Remove the array initialization and fetch directly
//  $infocliente = mysqli_fetch_array($sql, MYSQLI_ASSOC);
// if (!$infocliente) {
//     header('Content-Type: application/json');
//     echo json_encode([
//         'status' => 0,
//         'mensaje' => 'Cliente no encontrado',
//         'error_type' => 'not_found'
//     ]);
//     exit;
// }
// $nombre = $infocliente['short_name'] ?? '';
// $rutaFoto = $infocliente['url_img'] ?? '';

// if ($rutaFoto) {
//     $rutaFoto = __DIR__ .  "/../../../../" . $rutaFoto;
//     if (!is_file($rutaFoto)) {
//         $rutaFoto = '../../../includes/img/fotoClienteDefault.png';
//     }
// }

// Reference relationships
$parentescoRef1 = $infocliente[0]['parentesco_ref1'] ?? '--';
$parentescoRef2 = $infocliente[0]['parentesco_ref2'] ?? '--';
$parentescoRef3 = $infocliente[0]['parentesco_ref3'] ?? '--';

// Reference addresses
$direccionRef1 = $infocliente[0]['direccion_ref1'] ?? '--';
$direccionRef2 = $infocliente[0]['direccion_ref2'] ?? '--';
$direccionRef3 = $infocliente[0]['direccion_ref3'] ?? '--';

// Address references
$refDireccion1 = $infocliente[0]['ref_direccion1'] ?? '--';
$refDireccion2 = $infocliente[0]['ref_direccion2'] ?? '--';
$refDireccion3 = $infocliente[0]['ref_direccion3'] ?? '--';

$origen = strtoupper($infocliente[0]['origen']);
$fecha = $infocliente[0]['date_birth'];
$fechaNacimiento = date("d-m-Y", strtotime($fecha)); //formatear fecha en dia/mes/año
$paisNac = strtoupper($infocliente[0]['pais_nacio1']);
$deparNac = $infocliente[0]['depa_nacio'];
$muniNac = $infocliente[0]['id_muni_nacio'];
$genero = $infocliente[0]['genero'];
//genero del cliente
if ($genero == 'M') {
    $genero = 'MASCULINO';
} elseif ($genero == 'F') {
    $genero = 'FEMENINO';
} elseif ($genero == 'X') {
    $genero = 'NO DEFINIDO';
}

$estado_civil = $infocliente[0]['estado_civil'];
$profesion = $infocliente[0]['profesion'];

$tipoDocumento = $infocliente[0]['type_doc'];
$identificacion = $infocliente[0]['no_identifica'];
$paisExtiende = $infocliente[0]['pais_extiende'];
$depaExtiende = $infocliente[0]['depa_extiende'];
$nacionalidad = strtoupper($infocliente[0]['nacionalidad1']);
$otraNacionalidad = strtoupper($infocliente[0]['nacionalidad2']);
$direccion = strtoupper($infocliente[0]['Direccion']);

$noNit = $infocliente[0]['no_tributaria'];
$email = $infocliente[0]['email'];
$iggs = $infocliente[0]['no_igss'];
$tel1 = $infocliente[0]['tel_no1'];
$tel2 = $infocliente[0]['tel_no2'];
$condicionVivienda = $infocliente[0]['vivienda2'];
$anioReside = $infocliente[0]['ano_reside'];
$conyuge = $infocliente[0]['Conyuge'];

$Nombrereferencia1 = strtoupper($infocliente[0]['Nomb_Ref1']);
$Nombrereferencia2 = strtoupper($infocliente[0]['Nomb_Ref2']);
$Nombrereferencia3 = strtoupper($infocliente[0]['Nomb_Ref3']);
$telReferencia1 = $infocliente[0]['Tel_Ref1'];
$telReferencia2 = $infocliente[0]['Tel_Ref2'];
$telReferencia3 = $infocliente[0]['Tel_Ref3'];
$hijos = $infocliente[0]['hijos'];
$dependen = $infocliente[0]['dependencia'];
$telconyuge = ($infocliente[0]['telconyuge'] == "" || $infocliente[0]['telconyuge'] == NULL) ? " " : $infocliente[0]['telconyuge'];
// $zona = ($infocliente[0]['zona'] == " " || $infocliente[0]['zona'] == NULL) ? '' . $infocliente[0]['zona'] : " ";
// $barrio = ($infocliente[0]['barrio'] == " " || $infocliente[0]['barrio'] == NULL) ? strtoupper($infocliente[0]['barrio']) : " ";
$zona = $infocliente[0]['zona'] ?? "X";
$barrio = $infocliente[0]['barrio'] ?? "X";



$actuaPropio = $infocliente[0]['actu_Propio'];
$calidadActua = $infocliente[0]['repre_calidad'];

$propositoRelacion = $infocliente[0]['Rel_insti'];
/*
//nuevas refecias del cliente
//parentezco
$ParentezcoRef1 = $inforef[0]['Parent_Ref1'];
$ParentezcoRef2 = $inforef[0]['Parent_Ref2'];
$ParentezcoRef3 = $inforef[0]['Parent_Ref3'];
//direccion
$direccionRef1 = $inforef[0]['Direc_Ref1'];
$direccionRef2 = $inforef[0]['Direc_Ref2'];
$direccionRef3 = $inforef[0]['Direc_Ref3'];
//refecica de direccion
$direccionRef1 = $inforef[0]['Direc_Ref1'];
$direccionRef2 = $inforef[0]['Direc_Ref2'];
$direccionRef3 = $inforef[0]['Direc_Ref3'];
*/


class PDF extends FPDF
{
    public $institucion;
    public $telefonos;
    public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $dire, $email, $tel, $nit, $user)
    {
        parent::__construct('P', 'mm', 'Legal');
        $this->institucion = $institucion;
        $this->pathlogo = $pathlogo;
        $this->pathlogoins = $pathlogoins;
        $this->oficina = $oficina;
        $this->direccion = $dire;
        $this->email = $email;
        $this->telefonos = $tel;
        $this->nit = $nit;
        $this->user = $user;
    }

    // Cabecera de página
    function Header()
    {
        $hoy = date("Y-m-d H:i:s");
        // Logo 
        $this->Image($this->pathlogoins, 10, 8, 33);


        $this->SetFont('Arial', '', 8);
        // Movernos a la derecha
        //$this->Cell(80);

        // Título
        $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
        $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
        $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
        $this->Cell(0, 3, 'Tel: ' . $this->telefonos, 0, 1, 'C');
        $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');

        $this->SetFont('Arial', '', 7);
        $this->SetXY(-30, 5);
        $this->Cell(10, 2, $hoy, 0, 1, 'L');
        $this->SetXY(-25, 8);
        $this->Cell(10, 2, $this->user, 0, 1, 'L');

        // Salto de línea
        $this->Ln(15);
    }

    // Pie de página
    function Footer()
    {
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->SetY(-15);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

/* echo json_encode(['status' => 0, 'mensaje' => 'Cliente no encontrado']);
return; */
$fuente = "Arial";

$tamanioFuente = 9;
$tamanioTitulo = 11;
$tamanio_linea = 4; //altura de la linea/celda
$ancho_linea = 40; //anchura de la linea/celda
$espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
$ancho_linea2 = 35; //anchura de la linea/celda
$espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda
// Creación del objeto de la clase heredada
$pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $usuario);
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->Rect(9, 47, 148, 31, 'D'); //CUADRO 1 DATOS GENERALES
$pdf->Rect(160, 47, 31, 31, 'D'); //CUADRO foto
$pdf->Rect(9, 80, 192, 33, 'D'); //CUADRO 2 DATOS GENERALES
$pdf->Rect(9, 115, 192, 40, 'D'); //CUADRO 3 DATOS GENERALES
// $pdf->Rect(9, 164, 192, 18, 'D'); //CUADRO 1 REFERENCIAS
$pdf->SetY(25);

$pdf->SetFont($fuente, 'B', $tamanioTitulo);
$pdf->Cell(0, 10, 'Codigo Cliente:  ' . $codigo, 0, 1);

$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'DATOS GENERALES', 0, 1, 'C', true);

$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->SetDrawColor(225, 226, 226);

// Calculate the dimensions and position for the image
$imageX = 160;
$imageY = 47;
$imageWidth = 31;
$imageHeight = 31;
list($origWidth, $origHeight) = getimagesize($rutaFoto);
$ratio = min($imageWidth / $origWidth, $imageHeight / $origHeight);
$newWidth = $origWidth * $ratio;
$newHeight = $origHeight * $ratio;
$centerX = $imageX + ($imageWidth - $newWidth) / 2;
$centerY = $imageY + ($imageHeight - $newHeight) / 2;

$pdf->Image($rutaFoto, $centerX, $centerY, $newWidth, $newHeight, $extension);

$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell(0, 6, 'Nombre Completo:  ' . decode_utf8(mb_strtoupper($nombre, 'utf-8')), 'B', 1, 'C'); //Nombre cliente
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->Ln(2);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'Fecha de nacimiento', 0, 0, 'C'); //fecha nacimiento titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Sexo ', 0, 0, 'C'); //Sexo titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Estado civil', 0, 1, 'C'); //Estado civiio titulo
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->SetfillColor(230, 235, 236);
$pdf->Cell($ancho_linea, $tamanio_linea, $fechaNacimiento, 0, 0, 'C', true); //fecha nacimiento  DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, $genero, 0, 0, 'C', true); //sexo  DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, $estado_civil, 0, 1, 'C', true); //estado civil  DATO
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->Ln(2);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'Pais de nacimiento', 0, 0, 'C'); //pais nacimiento titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Departamento de nacimiento', 0, 0, 'C'); //departament nacimiento titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Municipio de nacimiento', 0, 1, 'C'); //muni nacimiento titulo
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->Cell($ancho_linea, $tamanio_linea, $paisNac, 0, 0, 'C', true); //pais nacimiento  DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8(karely(Departamento::obtenerNombre($deparNac))), 0, 0, 'C', true); //departamento nacimiento  DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8(karely(Municipio::obtenerNombre($muniNac??0))), 0, 1, 'C', true); //municipio nacimiento dato

$pdf->Ln(2);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea * 2, $tamanio_linea, 'Profesion u Oficio', 0, 1, 'C'); //Profesion titulo
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->Cell($ancho_linea * 2, $tamanio_linea, decode_utf8($profesion ?? " "), 0, 1, 'C', true); //profesion  DATO

$pdf->Ln(5);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'Condicion Migratoria', 0, 0, 'C'); //condicion migratoria titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Nacionalidad', 0, 0, 'C'); //Nacionalidad titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Otra nacionalidad', 0, 1, 'C'); //otra nacionalidad titulo

$pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8(decode_utf8($origen ?? " ")), 0, 0, 'C', true); //
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8(decode_utf8($nacionalidad ?? " ")), 0, 0, 'C', true); //Nacionalidad dato
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8(decode_utf8($otraNacionalidad ?? " ")), 0, 1, 'C', true); //otra nacionalidad DATO

$pdf->Ln(4);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea2, $tamanio_linea, 'Doc de Identificacion ', 0, 0, 'C'); //Doc De identificacio titulo
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, 'Numero de ' . $tipoDocumento, 0, 0, 'C'); //numero de identificacion titulo
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, 'Extendido', 0, 0, 'C'); //pais extendido titulo
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, 'Departamento', 0, 0, 'C'); //Departamento extendido titulo
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, 'Municipio', 0, 1, 'C'); //Departamento extendido titulo
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->Cell($ancho_linea2, $tamanio_linea, $tipoDocumento, 0, 0, 'C', true); //doc  DATO
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, $identificacion, 0, 0, 'C', true); //numero doc DATO
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, $paisExtiende, 0, 0, 'C', true); //pais extendido0 DATO
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, decode_utf8(karely(Departamento::obtenerNombre($deparNac))), 0, 0, 'C', true); //departamento extendido DATO
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2, $tamanio_linea, decode_utf8(karely(Municipio::obtenerNombre($muniNac??0))), 0, 1, 'C', true); //departamento extendido DATO

$pdf->Ln(2);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'No de NIT', 0, 0, 'C'); //nit titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea * 2, $tamanio_linea, 'Email', 0, 0, 'C'); //email titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Afiliacion IGGS', 0, 1, 'C'); //iggs  titulo
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->Cell($ancho_linea, $tamanio_linea, $noNit, 0, 0, 'C', true); //nit DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea * 2, $tamanio_linea, ($email ?? " "), 0, 0, 'C', true); //email  DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, $iggs, 0, 1, 'C', true); //iggs DATO

$pdf->Ln(5);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell(0, $tamanio_linea, 'Direccion', 0, 0, 'C'); //direccion titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 1, 'C'); //espacio
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->Cell(0, $tamanio_linea, decode_utf8($direccion ?? " "), 0, 1, 'C', true); //direccion DATO
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea + 20, $tamanio_linea, 'Telefono #1', 0, 0, 'C'); //tel1 titulo
$pdf->Cell(1, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Telefono #2', 0, 1, 'C'); //tel2 titulo
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, $tel1, 0, 0, 'C', true); //tel1 DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, $tel2, 0, 1, 'C', true); //tel2 DATO

$pdf->Ln(2);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'Condicion de la vivienda', 0, 0, 'C'); //condicion vivienda titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8('Año Vivienda'), 0, 0, 'C'); //año vivienda titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea * 2, $tamanio_linea, 'Nombre de Conyuge', 0, 1, 'C'); //nombre conyuge titulo
$pdf->SetFont($fuente, '', $tamanioFuente);


$pdf->Cell($ancho_linea, $tamanio_linea, $condicionVivienda ?? " ", 0, 0, 'C', true); //condicion vivienda DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, $anioReside ?? " ", 0, 0, 'C', true); //año vivienda DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea * 2, $tamanio_linea, $conyuge ?? " ", 0, 1, 'C', true); //nombre conyuge dato
$pdf->Ln(2);

$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'Zona', 0, 0, 'C'); //condicion vivienda titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, 'Barrio', 0, 0, 'C'); //año vivienda titulo
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea * 2, $tamanio_linea, 'Tel. de Conyuge', 0, 1, 'C'); //nombre conyuge titulo
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->Cell($ancho_linea, $tamanio_linea, $zona, 0, 0, 'C', true); //condicion vivienda DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea, $tamanio_linea, $barrio, 0, 0, 'C', true); //año vivienda DATO
$pdf->Cell($espacio_blanco, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea * 2, $tamanio_linea, $telconyuge, 0, 1, 'C', true); //nombre conyuge dato

$pdf->Ln(4);

// Header for references section with blue background

// Reset to normal font
$pdf->SetFont($fuente, '', $tamanioFuente);

// Set light gray background for data rows
// Header for references section with blue background
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'REFERENCIAS', 0, 1, 'C', true);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
// Set up column headers with blue background
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(63, $tamanio_linea, 'Referencia #1', 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, 'Referencia #2', 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, 'Referencia #3', 1, 1, 'C', true);

// Reset to normal font
$pdf->SetFont($fuente, '', $tamanioFuente);

// Set light gray background for data rows
$pdf->SetFillColor(230, 235, 236);

// Names row
$pdf->Cell(63, $tamanio_linea, decode_utf8($Nombrereferencia1 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($Nombrereferencia2 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($Nombrereferencia3 ?? " "), 1, 1, 'C', true);

// Relationship row
$pdf->Cell(63, $tamanio_linea, decode_utf8($parentescoRef1 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($parentescoRef2 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($parentescoRef3 ?? " "), 1, 1, 'C', true);

// Phone row
$pdf->Cell(63, $tamanio_linea, ($telReferencia1 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, ($telReferencia2 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, ($telReferencia3 ?? " "), 1, 1, 'C', true);

// Address row
$pdf->Cell(63, $tamanio_linea, decode_utf8($direccionRef1 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($direccionRef2 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($direccionRef3 ?? " "), 1, 1, 'C', true);

// Address reference row
$pdf->Cell(63, $tamanio_linea, decode_utf8($refDireccion1 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($refDireccion2 ?? " "), 1, 0, 'C', true);
$pdf->Cell(63, $tamanio_linea, decode_utf8($refDireccion3 ?? " "), 1, 1, 'C', true);

$pdf->Ln(2);

$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Ln(5);
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'ADICIONALES', 0, 1, 'C', true);
$pdf->SetFont($fuente, '', $tamanioFuente);

// $pdf->Ln(3);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea2 * 1.7, $tamanio_linea, 'No. hijos', 0, 0, 'C'); //referencias titulo
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2 * 2, $tamanio_linea, decode_utf8('Personas de relación de dependencia'), 0, 0, 'C'); //referencias titulo
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2 + 10, $tamanio_linea, 'Proposito de relacion: ', 0, 1, 'C');
$pdf->SetFont($fuente, '', $tamanioFuente);

$pdf->SetfillColor(230, 235, 236);
$pdf->Cell($ancho_linea2 * 1.7, $tamanio_linea, $hijos, 0, 0, 'C', true); //referencia nombre DATO
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2 + 25, $tamanio_linea, $dependen, 0, 0, 'C', true);  //referencia nombre  DATO
$pdf->Cell($espacio_blanco2, $tamanio_linea, ' ', 0, 0, 'C'); //espacio
$pdf->Cell($ancho_linea2 + 24, $tamanio_linea, $propositoRelacion, 0, 0, 'C', true);  //referencia nombre  DATO

$pdf->Ln(6);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'INFORMACION ECONOMICA DEL SOLICITANTE', 0, 1, 'C', true);
$pdf->SetFont($fuente, '', $tamanioFuente);


$pdf->Ln(3);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->SetFillColor(555, 255, 204);
$pdf->Cell(35, $tamanio_linea, 'Tipo de fuente', 1, 0, 'C', true); //
$pdf->Cell(80, $tamanio_linea, 'Nombre', 1, 0, 'C', true);
$pdf->Cell(50, $tamanio_linea, 'Puesto', 1, 0, 'C', true);
// $pdf->Cell(50, $tamanio_linea, 'Direccion', 1, 0, 'C', true);
$pdf->Cell(25, $tamanio_linea, 'Monto Ingresos', 1, 1, 'C', true);
$pdf->SetFont($fuente, '', $tamanioFuente);

while ($ingresos = mysqli_fetch_array($consultaIngresos, MYSQLI_ASSOC)) {
    if ($ingresos['Tipo_ingreso'] == '1') {
        $tipoIngreso = 'Independiente';
    } elseif ($ingresos['Tipo_ingreso'] == '2') {
        $tipoIngreso = 'Dependiente';
    } else {
        $tipoIngreso = 'Otros';
    }
    $nombreEmpresa = decode_utf8($ingresos['nombre_empresa']);
    $puesto = encode_utf8($ingresos['puesto_ocupa']);
    // $direccionEmpresa = encode_utf8($ingresos['direc_negocio']);
    $montoIngreso = encode_utf8($ingresos['sueldo_base']);

    $pdf->Cell(35, $tamanio_linea, $tipoIngreso, 1, 0, 'C');
    $pdf->Cell(80, $tamanio_linea, $nombreEmpresa, 1, 0, 'C');
    $pdf->Cell(50, $tamanio_linea, $puesto, 1, 0, 'C');
    // $pdf->Cell(50, $tamanio_linea, $direccionEmpresa, 1, 0, 'C');
    $pdf->Cell(25, $tamanio_linea, $montoIngreso, 1, 1, 'C');
}
//fin ingresos



$pdf->SetFont($fuente, '', ($tamanioFuente - 1));

$pdf->Ln(4);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'PRODUCTOS', 0, 1, 'C', true);
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->Ln(3);
// $pdf->CellFit($ancho_linea2 + 13, $tamanio_linea + 1, $bd_ccodaport, 'B', 0, 'C', 0, '', 1, 0); // cuenta

$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->SetFillColor(555, 255, 204);
$pdf->CellFit($ancho_linea2, $tamanio_linea, ' Tipo', 1, 0, 'C', true, '', 1, 0);
$pdf->CellFit($ancho_linea2 * 2 + 5, $tamanio_linea, 'Descripcion', 1, 0, 'C', true, '', 1, 0);
$pdf->CellFit($ancho_linea, $tamanio_linea, 'Cuenta', 1, 0, 'C', true, '', 1, 0);
$pdf->CellFit($ancho_linea, $tamanio_linea, 'Monto', 1, 1, 'C', true, '', 1, 0);
$pdf->SetFont($fuente, '', $tamanioFuente);

// $pdf->Ln(5);
//productos
//consulta a cuentas de ahorro
$consulta1 = mysqli_query($conexion, "SELECT 'Ahorro' AS tipo, aht.nombre AS descripcion, aho.ccodaho AS cuenta, IFNULL(calcular_saldo_aho_tipcuenta(aho.ccodaho,'" . date("Y-m-d") . "'),0)  AS saldo FROM ahomcta aho
INNER JOIN tb_cliente cl ON aho.ccodcli = cl.idcod_cliente
INNER JOIN ahomtip aht ON aht.ccodtip = SUBSTR(aho.ccodaho, 7, 2)
WHERE aho.estado='A' AND cl.idcod_cliente = '" . $codigo . "'");
//consulta a cuentas de aportaciones
$consulta2 = mysqli_query($conexion, "SELECT 'Aportación' AS tipo, apt.nombre AS descripcion, apr.ccodaport AS cuenta, IFNULL(calcular_saldo_apr_tipcuenta(apr.ccodaport,'" . date("Y-m-d") . "'),0) AS saldo FROM aprcta apr
INNER JOIN tb_cliente cl ON apr.ccodcli = cl.idcod_cliente
INNER JOIN aprtip apt ON apt.ccodtip = apr.ccodtip
WHERE apr.estado='A' AND cl.idcod_cliente ='" . $codigo . "'");
//Consulta a cuentas de creditos 
$consulta3 = mysqli_query($conexion, "SELECT 'Crédito' AS tipo, pr.descripcion AS descripcion, cm.CCODCTA AS cuenta, cm.MonSug AS saldo FROM cremcre_meta cm
INNER JOIN cre_productos pr ON cm.CCODPRD= pr.id
WHERE cm.Cestado='F' AND cm.CodCli = '" . $codigo . "'");
//unificar el resultado de las 3 consultas
$datos1[] = [];
$datos2[] = [];
$datos3[] = [];

$bandera = false;
$bandera2 = false;
$bandera3 = false;
$i = 0;
while ($fila = mysqli_fetch_array($consulta1, MYSQLI_ASSOC)) {
    $datos1[$i] = $fila;
    $datos1[$i]['numero'] = $i + 1;
    $i++;
    $bandera = true;
}
$i = 0;
while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
    $datos2[$i] = $fila;
    $datos2[$i]['numero'] = $i + 1;
    $i++;
    $bandera2 = true;
}
$i = 0;
while ($fila = mysqli_fetch_array($consulta3, MYSQLI_ASSOC)) {
    $datos3[$i] = $fila;
    $datos3[$i]['numero'] = $i + 1;
    $i++;
    $bandera3 = true;
}

$j = 0;
$k = 0;
if ($bandera) {
    foreach ($datos1 as $dato) {
        // $k++;
        // // $j=$dato['tipo'];
        // // break;
        $pdf->CellFit($ancho_linea2, $tamanio_linea, decode_utf8($dato['tipo']), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 + 5, $tamanio_linea, decode_utf8($dato['descripcion']), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea, $dato['cuenta'], 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea, $dato['saldo'], 1, 1, 'C', '', '0', 1, 0);
    }
}

if ($bandera2) {
    foreach ($datos2 as $dato) {
        $pdf->CellFit($ancho_linea2, $tamanio_linea, decode_utf8($dato['tipo']), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 + 5, $tamanio_linea, decode_utf8($dato['descripcion']), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea, $dato['cuenta'], 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea, $dato['saldo'], 1, 1, 'C', 0, '', 1, 0);
    }
}

if ($bandera3) {
    foreach ($datos3 as $dato) {
        $pdf->CellFit($ancho_linea2, $tamanio_linea, decode_utf8($dato['tipo']), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 + 5, $tamanio_linea, decode_utf8($dato['descripcion']), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea, $dato['cuenta'], 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea, $dato['saldo'], 1, 1, 'C', 0, '', 1, 0);
    }
}

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(0, 0, 0);
$pdf->SetDrawColor(0, 0, 0);
$pdf->firmas(1, [(strtoupper($nombre))], 'Arial');
//fin productos
//$pdf->Output();
error_reporting(0); // Disable error reporting
ob_start();
try {
    // Your existing PDF generation code
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Ficha de cliente",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData),
    );
} catch (Exception $e) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'Error al generar el reporte: ' . $e->getMessage()
    );
}

// Clear any previous output
ob_end_clean();

// Set proper JSON headers
header('Content-Type: application/json');
echo json_encode($opResult);
exit;
