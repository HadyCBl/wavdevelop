<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
date_default_timezone_set('America/Guatemala');

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$datos = $_POST["datosval"];
$param = $datos[3];

$idgrupo = $param[0];
$ciclo = $param[1];
$fecha = $param[2];

$strquery = "SELECT gru.codigo_grupo,gru.NombreGrupo,crem.NCiclo,crem.DFecDsbls fecdes,crem.DFecVen fecven,cli.short_name,crem.NCapDes, ppg.* FROM Cre_ppg ppg 
INNER JOIN cremcre_meta crem ON crem.CCODCTA=ppg.ccodcta
INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
INNER JOIN tb_grupo gru ON gru.id_grupos=crem.CCodGrupo
WHERE ppg.dfecven='" . $fecha . "' AND crem.CCodGrupo=" . $idgrupo . " AND crem.NCiclo=" . $ciclo . "";

$query = mysqli_query($conexion, $strquery);
$registro[] = [];

$j = 0;
while ($fil = mysqli_fetch_array($query)) {
    $registro[$j] = $fil;
    $j++;
}
//COMPROBACION: SI SE ENCONTRARON REGISTROS
if ($j == 0) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron datos',
        'dato' => $strquery
    );
    echo json_encode($opResult);
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
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}
// printpdf($doc, $idgrupo, $ciclo, $conexion, $info);
printxls($registro);
function printxls($registro)
{
    require '../../../../vendor/autoload.php';
    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Planilla");

    $activa->getColumnDimension("A")->setWidth(18);
    $activa->getColumnDimension("B")->setWidth(35);
    $activa->getColumnDimension("C")->setWidth(15);
    $activa->getColumnDimension("D")->setWidth(15);
    $activa->getColumnDimension("E")->setWidth(15);
    $activa->getColumnDimension("F")->setWidth(15);
    $activa->getColumnDimension("G")->setWidth(15);
    $activa->getColumnDimension("H")->setWidth(15);
    $activa->getColumnDimension("I")->setWidth(15);
    $activa->getColumnDimension("J")->setWidth(15);

    //APLICAR BORDE A LA CELDA
    $styleArray = [
        'borders' => [
            'outline' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                'color' => ['argb' => '00000000'],
            ],
        ],
    ];

    // CENTRAR TEXTO EN LA CELDA
    $estilocentrar = [
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ];
    //$sheet->getStyle('A1')->applyFromArray($styleArray);
    /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++ DATOS DE ENCABEZADDO +++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $activa->getStyle('B1:D3')->applyFromArray($styleArray);
    $activa->getStyle('E1:G3')->applyFromArray($styleArray);

    $activa->getStyle('B1:B3')->getFont()->setBold(true);
    $activa->getStyle('E1:E3')->getFont()->setBold(true);

    $activa->setCellValue('B1', 'NOMBRE GRUPO');
    $activa->setCellValue('B2', 'APERTURA:');
    $activa->setCellValue('B3', 'CICLO:');
    $activa->setCellValue('E1', 'CODIGO GRUPO:');
    $activa->setCellValue('E2', 'VENCIMIENTO:');
    $activa->setCellValue('E3', 'FECHA CUOTA:');

    $activa->mergeCells('C1:D1')->setCellValue('C1', $registro[0]['NombreGrupo']);
    $activa->mergeCells('C2:D2')->setCellValue('C2', $registro[0]['fecdes']);
    $activa->mergeCells('C3:D3')->setCellValue('C3', $registro[0]['NCiclo']);
    $activa->mergeCells('F1:G1')->setCellValue('F1', $registro[0]['codigo_grupo']);
    $activa->mergeCells('F2:G2')->setCellValue('F2', $registro[0]['fecven']);
    $activa->mergeCells('F3:G3')->setCellValue('F3', $registro[0]['dfecven']);

    $activa->getStyle('A5:K5')->getFont()->setBold(true);
    $activa->setCellValue('A5', 'CODIGO CREDITO');
    $activa->setCellValue('B5', 'NOMBRE CLIENTE');
    $activa->setCellValue('C5', 'OTORGADO');
    $activa->setCellValue('D5', 'CAPITAL');
    $activa->setCellValue('E5', 'INTERES');
    $activa->setCellValue('F5', 'OTROS');
    $activa->setCellValue('G5', 'TOTAL');
    $activa->setCellValue('H5', 'KP. PAGADO');
    $activa->setCellValue('I5', 'IN. PAGADO');
    $activa->setCellValue('J5', 'OTR. PAGADO');
    $activa->setCellValue('K5', 'TOTAL');

    $i = 0;
    $linea = 6;
    while ($i < count($registro)) {
        $activa->setCellValue('A' . ($linea), $registro[$i]['ccodcta']);
        $activa->setCellValue('B' . ($linea), $registro[$i]['short_name']);
        $activa->setCellValue('C' . ($linea), $registro[$i]['NCapDes']);
        $activa->setCellValue('D' . ($linea), $registro[$i]['ncapita']);
        $activa->setCellValue('E' . ($linea), $registro[$i]['nintere']);
        $activa->setCellValue('F' . ($linea), $registro[$i]['OtrosPagos']);
        $activa->setCellValue('G' . ($linea), $registro[$i]['ncapita'] + $registro[$i]['nintere'] + $registro[$i]['OtrosPagos']);
        $linea++;
        $i++;
    }
    $activa->getStyle('A5:K5')->applyFromArray($styleArray);
    $activa->getStyle('A6:C' . ($linea - 1))->applyFromArray($styleArray);
    $activa->getStyle('D6:G' . ($linea - 1))->applyFromArray($styleArray);
    $activa->getStyle('H6:K' . ($linea - 1))->applyFromArray($styleArray);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Planilla por cuota",
        'tipo' => "xlsx",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}