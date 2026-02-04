<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
require '../../../vendor/autoload.php';

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$act = $_POST["activo"];
$finicio = $_POST["finicio"];
$ffin = $_POST["ffin"];
$ainicio = $_POST["ainicio"];
$afin = $_POST["afin"];
$cha = $_POST["checkalta"];
$chb = $_POST["checkbaja"];

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

$sql = mysqli_query($conexion, $strquery);

$excel = new Spreadsheet();
$activa = $excel->getActiveSheet();
$activa->setTitle("Clientes");

$activa->getColumnDimension("A")->setWidth(20);
$activa->getColumnDimension("B")->setWidth(50);
$activa->getColumnDimension("C")->setWidth(20);
$activa->getColumnDimension("D")->setWidth(15);
$activa->getColumnDimension("E")->setWidth(15);
$activa->getColumnDimension("F")->setWidth(10);
$activa->getColumnDimension("G")->setWidth(10);
$activa->getColumnDimension("H")->setWidth(20);
$activa->getColumnDimension("I")->setWidth(50);
$activa->getColumnDimension("J")->setWidth(15);
$activa->getColumnDimension("K")->setWidth(15);
$activa->getColumnDimension("L")->setWidth(25);
$activa->getColumnDimension("M")->setWidth(15);
$activa->getColumnDimension("N")->setWidth(15);
$activa->getColumnDimension("O")->setWidth(25);

$activa->setCellValue('A1', 'CODIGO CLIENTE');
$activa->setCellValue('B1', 'NOMBRE CLIENTE');
$activa->setCellValue('C1', 'DPI');
$activa->setCellValue('D1', 'FECHA NAC.');
$activa->setCellValue('E1', 'ETNIA');
$activa->setCellValue('F1', 'SEXO');
$activa->setCellValue('G1', 'EDAD');
$activa->setCellValue('H1', 'NIT');
$activa->setCellValue('I1', 'DIRECCION');
$activa->setCellValue('J1', 'TELEFONO');
$activa->setCellValue('K1', 'ESTADO CIVIL');
$activa->setCellValue('L1', 'PROFESION');
$activa->setCellValue('M1', 'FECHA ALTA');
$activa->setCellValue('N1', 'FECHA BAJA');
$activa->setCellValue('O1', 'AGENCIA');


$fila = 2;
while ($registro = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
    $idcod_cliente = ($registro["idcod_cliente"]);
    $no_identifica = ($registro["no_identifica"]);
    //$compl_name = ($registro["primer_name"] . " " . $registro["segundo_name"] . " " . $registro["tercer_name"] . " " . $registro["primer_last"] . " " . $registro["segundo_last"]);
    $compl_name = Utf8::decode($registro["short_name"]);
    $date_birth = date("d-m-Y", strtotime(($registro["date_birth"])));
    $idioma = ($registro["idiomades"]);
    // $idioma = func_idiomas($etnia,$general)[0];
    $sexo = ($registro["genero"]);
    $nit = ($registro["no_tributaria"]);
    $direccion = Utf8::decode($registro["Direccion"]);
    $telefono1 = ($registro["tel_no1"]);
    $estadocivil = ($registro["estado_civil"]);
    $agencia = Utf8::decode($registro["nom_agencia"]);
    $profesion = ($registro["profesion"] == "") ? ' ' : Utf8::decode($registro["profesion"]);
    $date_alta = ($registro["fecha_alta"] == null || $registro["fecha_alta"] == "") ? '0000-00-00' : date('d-m-Y', strtotime($registro["fecha_alta"]));
    $date_baja = ($registro["fecha_baja"] == null || $registro["fecha_baja"] == "") ? '0000-00-00' : date('d-m-Y', strtotime($registro["fecha_baja"]));

    $activa->setCellValueExplicit('A' . $fila, $idcod_cliente, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->setCellValue('B' . $fila, strtoupper($compl_name));
    $activa->setCellValueExplicit('C' . $fila, $no_identifica, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->setCellValue('D' . $fila, $date_birth);
    $activa->setCellValue('E' . $fila, $idioma);
    $activa->setCellValue('F' . $fila, $sexo);
    $activa->setCellValue('G' . $fila, '=DATEDIF(D' . $fila . ',TODAY(),"Y")');
    $activa->setCellValueExplicit('H' . $fila, $nit, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->setCellValue('I' . $fila, $direccion);
    $activa->setCellValueExplicit('J' . $fila, $telefono1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->setCellValue('K' . $fila, $estadocivil);
    $activa->setCellValue('L' . $fila, $profesion);
    $activa->setCellValue('M' . $fila, $date_alta);
    if ($act == "0") {
        $activa->setCellValue('N' . $fila, $date_baja);
    }
    $activa->setCellValue('O' . $fila, $agencia);
    $fila++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="clientes.xlsx"');
header('Cache-Control: max-age=0');

ob_start();
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
$writer->save("php://output");
$xlsData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
);
echo json_encode($opResult);
exit;
