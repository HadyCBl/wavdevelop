<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

require "../../../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

$oficina = utf8_decode($info[0]["nom_agencia"]);
$institucion = utf8_decode($info[0]["nomb_comple"]);
$direccionins = utf8_decode($info[0]["muni_lug"]);
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

$hoy = date("Y-m-d H:i:s");

$fuente_encabezado = "Arial";
$fuente = "Courier";
$tamanioFecha = 9; //tamaño de letra de la fecha y usuario
$tamanioEncabezado = 14; //tamaño de letra del encabezado
$tamanioTabla = 11; //tamaño de letra de la fecha y usuario
$tamanio_linea = 4; //altura de la linea/celda
$ancho_linea = 30; //anchura de la linea/celda
$espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
$ancho_linea2 = 20; //anchura de la linea/celda
$espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda

//-----------RELACIONADO CON LAS PROPIEDADES DEL ARCHIVO----------------------------
$spread = new Spreadsheet();
$spread
    ->getProperties()
    ->setCreator("MICROSYSTEM")
    ->setLastModifiedBy('MICROSYSTEM')
    ->setTitle('Reporte')
    ->setSubject('Listado de Cuentas Activas e Inactivas')
    ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
    ->setKeywords('PHPSpreadsheet')
    ->setCategory('Excel');
//-----------RELACIONADO CON LAS PROPIEDADES DEL ARCHIVO----------------------------

//-----------RELACIONADO CON EL ENCABEZADO----------------------------
# Como ya hay una hoja por defecto, la obtenemos, no la creamos
$hojaReporte = $spread->getActiveSheet();
$hojaReporte->setTitle("Reporte de cuentas");

//insertarmos la fecha y usuario
$hojaReporte->setCellValue("A1", $hoy);
$hojaReporte->setCellValue("A2", $usuario);
//informacion de la agencia o cooperativa
$hojaReporte->setCellValue("A4", $institucion);
$hojaReporte->setCellValue("A5", $direccionins);
$hojaReporte->setCellValue("A6", "Email: " . $emailins);
$hojaReporte->setCellValue("A7", "Tel: " . $telefonosins);
$hojaReporte->setCellValue("A8", "NIT: " . $nitins);

//hacer pequeño las letras de la fecha, definir arial como tipo de letra
$hojaReporte->getStyle("A1:G1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
$hojaReporte->getStyle("A2:G2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
//centrar el texto de la fecha
$hojaReporte->getStyle("A1:G1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$hojaReporte->getStyle("A2:G2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

//hacer grande las letras del encabezado
$hojaReporte->getStyle("A4:G4")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
$hojaReporte->getStyle("A5:G5")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
$hojaReporte->getStyle("A6:G6")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
$hojaReporte->getStyle("A7:G7")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
$hojaReporte->getStyle("A8:G8")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
//centrar el texto del encabezado
$hojaReporte->getStyle("A4:G4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$hojaReporte->getStyle("A5:G5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$hojaReporte->getStyle("A6:G6")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$hojaReporte->getStyle("A7:G7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$hojaReporte->getStyle("A8:G8")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

//hacer pequeño las letras del encabezado de titulo
$hojaReporte->getStyle("A10:G10")->getFont()->setSize($tamanioTabla)->setName($fuente);
$hojaReporte->getStyle("A11:G11")->getFont()->setSize($tamanioTabla)->setName($fuente);
//centrar los encabezado de la tabla
$hojaReporte->getStyle("A10:G10")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$hojaReporte->getStyle("A11:G11")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

//titulo de reporte
//consultar los estados
$texto_cuentas = "";
if ($tipo == '0') {
    $texto_cuentas = 'TODAS LAS CUENTAS';
} else {
    $data_cuentas = mysqli_query($conexion, "SELECT `nombre` FROM `aprtip` WHERE `id_tipo`=$tipo");
    while ($rowcuentas = mysqli_fetch_array($data_cuentas, MYSQLI_ASSOC)) {
        $texto_cuentas = strtoupper(($rowcuentas["nombre"]));
    }
}
$hojaReporte->setCellValue("A10", "LISTADO DE CUENTAS ACTIVAS/INACTIVAS");
$hojaReporte->setCellValue("A11", strtoupper($texto_cuentas));

//combinacion de celdas
$hojaReporte->mergeCells('A1:G1');
$hojaReporte->mergeCells('A2:G2');
$hojaReporte->mergeCells('A4:G4');
$hojaReporte->mergeCells('A5:G5');
$hojaReporte->mergeCells('A6:G6');
$hojaReporte->mergeCells('A7:G7');
$hojaReporte->mergeCells('A8:G8');
$hojaReporte->mergeCells('A10:G10');
$hojaReporte->mergeCells('A11:G11');

# Escribir encabezado de la tabla
$encabezado_tabla = ["NUM", "TIPO DE APORTACIÓN", "CUENTA", "NOMBRE COMPLETO", "ESTADO", "APERTURA", "CANCELACION"];
# El último argumento es por defecto A1 pero lo pongo para que se explique mejor
$hojaReporte->fromArray($encabezado_tabla, null, 'A13')->getStyle('A13:G13')->getFont()->setName($fuente)->setBold(true);

//ingreso de los datos de tabla
$contador = 0;
$consulta = "SELECT cta.ccodaport, tp.nombre, cl.short_name, cta.estado, cta.fecha_apertura, cta.fecha_cancel
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
        //extraer el ccodtip de codigo de aportacion
        if ($estado == "0") {
            $consulta .= " cta.ccodtip='$ccodtip'";
        } else {
            $consulta .= " AND cta.ccodtip='$ccodtip'";
        }
    }
}
//se hace la consulta segun los tipos de filtro
$linea = 14;
$data2 = mysqli_query($conexion, $consulta);
cargar_datos($hojaReporte, $contador, $data2, $linea, $fuente);
$contador = 0;

//-----------RELACIONADO CON LA BAJADA O DESCARGA DEL ARCHIVO----------------------------
//crea el archivo para que se descarge
ob_start();
$writer = IOFactory::createWriter($spread, 'Xlsx');
$writer->save("php://output");
$xlsData = ob_get_contents();
ob_end_clean();
//envio de repuesta a ajax para descargarlos
$opResult = array(
    'status' => 1,
    'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
);
mysqli_close($conexion);
echo json_encode($opResult);
exit;
//-----------RELACIONADO CON LA BAJADA O DESCARGA DEL ARCHIVO----------------------------

//funcion para cargar los datos al archivo de excel
function cargar_datos($hojaReporte, $contador, $data, $linea, $fuente)
{
    while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
        $bd_ccodaport = $rowdata["ccodaport"];
        $bd_shortname = strtoupper($rowdata["short_name"]);
        $bd_tipocuenta = strtoupper($rowdata["nombre"]);
        $bd_estado = strtoupper($rowdata["estado"]);
        $bd_fechaapertura = strtoupper($rowdata["fecha_apertura"]);
        $bd_fechacancel = strtoupper($rowdata["fecha_cancel"]);

        $contador++;

        $hojaReporte->setCellValueByColumnAndRow(1, $linea, $contador);
        $hojaReporte->setCellValueByColumnAndRow(2, $linea, $bd_ccodaport);
        $hojaReporte->setCellValueByColumnAndRow(3, $linea, $bd_tipocuenta);
        $hojaReporte->setCellValueByColumnAndRow(4, $linea, $bd_shortname);
        $hojaReporte->setCellValueByColumnAndRow(5, $linea, $bd_estado);
        $hojaReporte->setCellValueByColumnAndRow(6, $linea, $bd_fechaapertura);
        $hojaReporte->setCellValueByColumnAndRow(7, $linea, $bd_fechacancel);

        $hojaReporte->getStyle("A" . $linea . ":G" . $linea)->getFont()->setName($fuente);


        $linea++;
    }

    $hojaReporte->getColumnDimension('B')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('C')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('E')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('F')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('G')->setAutoSize(TRUE);
}
