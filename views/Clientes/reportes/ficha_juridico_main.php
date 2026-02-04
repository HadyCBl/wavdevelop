<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

// -----------------------------------------------------------------------------
// 1. Obtener datos de la institución (para encabezados y logotipos)
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop
    WHERE ag.id_agencia = " . $_SESSION['id_agencia']);
$info = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}

$oficina       = decode_utf8($info[0]["nom_agencia"]);
$institucion   = decode_utf8($info[0]["nomb_comple"]);
$direccionins  = decode_utf8($info[0]["muni_lug"]);
$emailins      = $info[0]["emai"];
$telefonosins  = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
$nitins        = $info[0]["nit"];
$rutalogomicro = "../../../includes/img/logomicro.png";
$rutalogoins   = "../../.." . $info[0]["log_img"];

// Usuario de sesión
$usuario = $_SESSION['id'];

// -----------------------------------------------------------------------------
// 2. Recibir el código del cliente a consultar
$datos   = $_POST["datosval"];
$inputs  = $datos[0];
$archivo = $datos[3];
$codigo  = $archivo[0];

// -----------------------------------------------------------------------------
// 3. Consulta a la tabla de clientes jurídicos (tb_cliente)
$sql = mysqli_query($conexion, "SELECT 
    idcod_cliente,
    id_tipoCliente,
    agencia,
    primer_name,
    segundo_name,
    tercer_name,
    primer_last,
    segundo_last,
    casada_last,
    short_name,
    compl_name,
    LEFT(url_img,256) AS url_img,
    date_birth,
    genero,
    estado_civil,
    origen,
    pais_nacio,
    depa_nacio,
    aldea,
    type_doc,
    no_identifica,
    pais_extiende,
    nacionalidad,
    depa_extiende,
    otra_nacion,
    identi_tribu,
    no_tributaria,
    no_igss,
    profesion,
    Direccion,
    depa_reside,
    aldea_reside,
    tel_no1,
    tel_no2,
    area,
    ano_reside,
    vivienda_Condi,
    email,
    relac_propo,
    monto_ingre,
    actu_Propio,
    representante_name,
    repre_calidad,
    id_religion,
    leer,
    escribir,
    firma,
    cargo_grupo,
    educa,
    idioma,
    Rel_insti,
    LEFT(datos_Adicionales,256) AS datos_Adicionales,
    LEFT(Conyuge,256) AS Conyuge,
    telconyuge,
    zona,
    barrio,
    hijos,
    dependencia,
    Nomb_Ref1,
    Nomb_Ref2,
    Nomb_Ref3,
    Tel_Ref1,
    Tel_Ref2,
    Tel_Ref3,
    PEP,
    CPE,
    control_interno,
    estado,
    created_by,
    updated_by,
    fecha_alta,
    fecha_baja,
    fecha_mod,
    deleted_by,
    observaciones
FROM tb_cliente 
WHERE idcod_cliente = '" . $codigo . "'");

if (!$infocliente = mysqli_fetch_array($sql)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Cliente no encontrado']);
    return;
}

// -----------------------------------------------------------------------------
// 4. Consulta a la tabla de socios jurídicos (tb_socios_juri)
$sql_socios = mysqli_query($conexion, "SELECT 
    id_socio,
    LEFT(name_socio,256) AS name_socio,
    puesto_socio 
FROM tb_socios_juri 
WHERE id_clnt_ntral = '" . $codigo . "'
ORDER BY name_socio ASC");

$socios = [];
while ($row = mysqli_fetch_array($sql_socios, MYSQLI_ASSOC)) {
    $socios[] = $row;
}

// -----------------------------------------------------------------------------
// 5. Preparar datos para la ficha
// Al eliminar el campo de la foto, no se requiere procesar la imagen

// Para cliente jurídico se usa la razón social y se muestra el representante legal
$nombre        = decode_utf8($infocliente['compl_name']); 
$representante = decode_utf8($infocliente['representante_name']);

// Documentación e identificación
$tipoDocumento  = $infocliente['type_doc'];
$identificacion = $infocliente['no_identifica'];
$noNit          = $infocliente['no_tributaria'];

// Datos de contacto
$direccion = decode_utf8($infocliente['Direccion']);
$email     = $infocliente['email'];
$tel1      = $infocliente['tel_no1'];
$tel2      = $infocliente['tel_no2'];

// Datos adicionales y observaciones
$datosAdicionales = decode_utf8($infocliente['datos_Adicionales']);
$observaciones    = decode_utf8($infocliente['observaciones']);

// -----------------------------------------------------------------------------
// 6. Definición de la clase PDF (se mantiene la lógica original)
class PDF extends FPDF
{
    public $institucion;
    public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $dire, $email, $tel, $nit, $user)
    {
        parent::__construct();
        $this->institucion = $institucion;
        $this->pathlogo    = $pathlogo;
        $this->pathlogoins = $pathlogoins;
        $this->oficina     = $oficina;
        $this->direccion   = $dire;
        $this->email       = $email;
        $this->telefonos   = $tel;
        $this->nit         = $nit;
        $this->user        = $user;
    }

    // Cabecera de página
    function Header()
    {
        $hoy = date("Y-m-d H:i:s");
        // Logotipo de la institución
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
        $this->Cell(10, 2, $this->user, 0, 1, 'L');
        $this->Ln(15);
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

// Variables de formato y dimensiones
$fuente          = "Arial";
$tamanioFuente   = 9;
$tamanioTitulo   = 11;
$tamanio_linea   = 4;   // Altura de la línea/celda
$ancho_linea     = 40;  // Ancho de la celda
$espacio_blanco  = 10;  // Espacio entre celdas

// -----------------------------------------------------------------------------
// 7. Creación del objeto PDF y armado de la ficha
$pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $usuario);
$pdf->AliasNbPages();
$pdf->AddPage();

// Ajuste de rectángulos:
// Se elimina el rectángulo destinado a la foto y se ajusta el cuadro de Datos Generales a todo el ancho.
$pdf->Rect(9, 47, 192, 31, 'D'); // CUADRO DATOS GENERALES
$pdf->Rect(9, 80, 192, 40, 'D');  // CUADRO DATOS ADICIONALES
$pdf->Rect(9, 121, 192, 30, 'D'); // CUADRO SOCIOS

$pdf->SetY(25);
$pdf->SetFont($fuente, 'B', $tamanioTitulo);
$pdf->Cell(0, 10, 'Codigo Cliente:  ' . $codigo, 0, 1, 'C');

// ***** SECCIÓN: DATOS GENERALES *****
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'DATOS GENERALES', 0, 1, 'C', true);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
// Se utiliza Cell(0,…) para abarcar todo el ancho disponible y centrar el contenido
$pdf->Cell(0, 6, 'Nombre / Razon Social:  ' . $nombre, 'B', 1, 'C');
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->Ln(2);

// Fila con Representante, Tipo de documento y No. Identificación
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'Representante Legal', 0, 0, 'C');
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C');
$pdf->Cell($ancho_linea, $tamanio_linea, 'Tipo Doc.', 0, 0, 'C');
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C');
$pdf->Cell($ancho_linea, $tamanio_linea, 'No. Identificacion', 0, 1, 'C');
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->SetFillColor(230, 235, 236);
$pdf->Cell($ancho_linea, $tamanio_linea, $representante, 0, 0, 'C', true);
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C', true);
$pdf->Cell($ancho_linea, $tamanio_linea, $tipoDocumento, 0, 0, 'C', true);
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C', true);
$pdf->Cell($ancho_linea, $tamanio_linea, $identificacion, 0, 1, 'C', true);
$pdf->Ln(2);

// Fila con NIT, Email y Teléfono
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, 'No. NIT', 0, 0, 'C');
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C');
$pdf->Cell($ancho_linea * 2, $tamanio_linea, 'Email', 0, 0, 'C');
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C');
$pdf->Cell($ancho_linea, $tamanio_linea, 'Telefono', 0, 1, 'C');
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->Cell($ancho_linea, $tamanio_linea, $noNit, 0, 0, 'C', true);
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C', true);
$pdf->Cell($ancho_linea * 2, $tamanio_linea, $email, 0, 0, 'C', true);
$pdf->Cell($espacio_blanco, $tamanio_linea, '', 0, 0, 'C', true);
$pdf->Cell($ancho_linea, $tamanio_linea, $tel1, 0, 1, 'C', true);

// ***** SECCIÓN: DATOS ADICIONALES *****
// Se usa MultiCell para envolver el texto si es muy largo. El ancho se iguala al de la celda (192)
$pdf->Ln(5);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'DATOS ADICIONALES', 0, 1, 'C', true);
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->MultiCell(192, $tamanio_linea, $datosAdicionales, 0, 'C', true);

// ***** SECCIÓN: SOCIOS *****
$pdf->Ln(4);
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'SOCIOS', 0, 1, 'C', true);

// Encabezado de la tabla sin borde y centrado
$pdf->SetFont($fuente, 'B', $tamanioFuente);

// El encabezado "Nombre" se muestra en azul
$pdf->SetTextColor(0, 0, 255);
$pdf->Cell(60, $tamanio_linea, 'Nombre', 0, 0, 'C');

// El encabezado "Puesto" se muestra en negro (restablecemos el color)
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(60, $tamanio_linea, 'Puesto', 0, 1, 'C');

// Datos de la tabla sin bordes y centrados
$pdf->SetFont($fuente, '', $tamanioFuente);
if (count($socios) > 0) {
    foreach ($socios as $socio) {
        // La columna "Nombre" en azul
        $pdf->SetTextColor(0, 0, 255);
        $pdf->Cell(60, $tamanio_linea, decode_utf8($socio['name_socio']), 0, 0, 'C');

        // La columna "Puesto" en negro
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(60, $tamanio_linea, decode_utf8($socio['puesto_socio']), 0, 1, 'C');
    }
} else {
    $pdf->Cell(120, $tamanio_linea, 'Sin socios registrados', 0, 1, 'C');
}


// ***** SECCIÓN: OBSERVACIONES *****
// Se utiliza MultiCell para envolver y centrar el texto
$pdf->Ln(5);
$pdf->SetFont($fuente, 'B', $tamanioFuente);
$pdf->SetFillColor(204, 229, 255);
$pdf->Cell(0, 5, 'OBSERVACIONES', 0, 1, 'C', true);
$pdf->SetFont($fuente, '', $tamanioFuente);
$pdf->MultiCell(192, $tamanio_linea, $observaciones, 0, 'C', true);

// (Opcional: sección de firmas u otros detalles)
// Se asume que el método firmas() existe o está definido en otra parte de tu código.
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(0, 0, 0);
$pdf->SetDrawColor(0, 0, 0);
$pdf->firmas(1, [strtoupper($nombre)], 'Arial');

// -----------------------------------------------------------------------------
// 8. Envío del PDF (se captura la salida, se codifica en base64 y se retorna en JSON)
ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status'   => 1,
    'mensaje'  => 'Reporte generado correctamente',
    'namefile' => "Ficha de cliente juridico",
    'tipo'     => "pdf",
    'data'     => "data:application/pdf;base64," . base64_encode($pdfData),
);
echo json_encode($opResult);
?>
