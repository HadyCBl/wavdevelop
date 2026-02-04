<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';
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
$cod = $datos[5];

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
    ->setSubject('Saldos por cuenta con fecha')
    ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
    ->setKeywords('PHPSpreadsheet')
    ->setCategory('Excel');
//-----------RELACIONADO CON LAS PROPIEDADES DEL ARCHIVO----------------------------
$hojaReporte = $spread->getActiveSheet();
$hojaReporte->setTitle("Reporte");

//insertarmos la fecha y usuario
$hojaReporte->setCellValue("A1", $hoy);
$hojaReporte->setCellValue("A2", $usuario);
//hacer pequeño las letras de la fecha, definir arial como tipo de letra
$hojaReporte->getStyle("A1:M1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
$hojaReporte->getStyle("A2:M2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
//centrar el texto de la fecha
$hojaReporte->getStyle("A1:M1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$hojaReporte->getStyle("A2:M2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
// //combinacion de celdas
$hojaReporte->mergeCells('A1:M1');
$hojaReporte->mergeCells('A2:M2');

# Escribir encabezado de la tabla
$encabezado_tabla = ["CUENTA", "CODIGO CLIENTE", "NOMBRE COMPLETO", "TIPOPE", "FECHA", "NUMDOC", "MONTO", "SALDO", "DIAS", "TASA", "INTERES", "ISR", "TIPOCUENTA"];
# El último argumento es por defecto A1 pero lo pongo para que se explique mejor
$hojaReporte->fromArray($encabezado_tabla, null, 'A4')->getStyle('A4:M4')->getFont()->setName($fuente)->setBold(true);

$consulta = "SELECT apin.ccodaport,apin.nomcli,apin.codcli,apin.tipope,apin.fecope,apin.numdoc,apin.tipdoc,apin.monto,apin.saldo,apin.saldoant,apin.dias,apin.tasa,apin.intcal,apin.isrcal,tip.nombre 
FROM aprintere AS apin 
INNER JOIN aprtip AS tip ON SUBSTR(apin.ccodaport,7,2)=tip.ccodtip 
WHERE apin.idcalc=" . $cod . " 
ORDER BY apin.idreg";

$linea = 5;
$data = mysqli_query($conexion, $consulta);
cargar_datos($hojaReporte, $data, $linea, $fuente);

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

//procedimiento para cargar los datos en el archivo de excel
function cargar_datos($hojaReporte, $data, $linea, $fuente)
{
    while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
        $cuenta = utf8_encode($rowdata["ccodaport"]);
        $codcli = utf8_encode($rowdata["codcli"]);
        $nombre = utf8_encode($rowdata["nomcli"]);
        $tipope = utf8_encode($rowdata["tipope"]);
        $fecha = date("d-m-Y", strtotime(utf8_encode($rowdata["fecope"])));
        $numdoc = utf8_encode($rowdata["numdoc"]);
        $tipdoc = utf8_encode($rowdata["tipdoc"]);
        $monto = utf8_encode($rowdata["monto"]);
        $saldo = utf8_encode($rowdata["saldo"]);
        $dias = utf8_encode($rowdata["dias"]);
        $tasa = utf8_encode($rowdata["tasa"]);
        $interes = utf8_encode($rowdata["intcal"]);
        $isr = utf8_encode($rowdata["isrcal"]);
        $tipocuenta = utf8_encode($rowdata["nombre"]);

        // colocar formato de moneda
        // $hojaReporte->getStyle('G' . $linea . ':H' . $linea)->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
        // $hojaReporte->getStyle('K' . $linea . ':L' . $linea)->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
        //se insertan los datos
        $hojaReporte->setCellValueByColumnAndRow(1, $linea, $cuenta);
        $hojaReporte->setCellValueByColumnAndRow(2, $linea, $codcli);
        $hojaReporte->setCellValueByColumnAndRow(3, $linea, $nombre);
        $hojaReporte->setCellValueByColumnAndRow(4, $linea, $tipope);
        $hojaReporte->setCellValueByColumnAndRow(5, $linea, $fecha);
        $hojaReporte->setCellValueByColumnAndRow(6, $linea, $numdoc);
        $hojaReporte->setCellValueByColumnAndRow(7, $linea, $monto);
        $hojaReporte->setCellValueByColumnAndRow(8, $linea, $saldo);
        $hojaReporte->setCellValueByColumnAndRow(9, $linea, $dias);
        $hojaReporte->setCellValueByColumnAndRow(10, $linea, $tasa);
        $hojaReporte->setCellValueByColumnAndRow(11, $linea, $interes);
        $hojaReporte->setCellValueByColumnAndRow(12, $linea, $isr);
        $hojaReporte->setCellValueByColumnAndRow(13, $linea, $tipocuenta);
        $hojaReporte->getStyle("A" . $linea . ":M" . $linea)->getFont()->setName($fuente);

        $linea++;
    }
    $hojaReporte->getColumnDimension('A')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('B')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('C')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('D')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('E')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('F')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('G')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('H')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('I')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('J')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('K')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('L')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('M')->setAutoSize(TRUE);
}
