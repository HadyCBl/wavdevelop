<?php
session_start();
include '../../../src/funcphp/func_gen.php';
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';

require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");

ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];

$tipo = $_POST["tipo"];
//-------VALIDACIONES------------------([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rtipo`,`rfondos`,`rfechas`],[ $idusuario]],`pdf`,`libro_diario`,0)
if ($radios[2] == "frango" && $inputs[0] > $inputs[1]) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}

//-------ARMANDO LA CONSULTA------------
$condi = "";
//AGENCIA
if ($radios[3] == "anyofi") {
    $condi = $condi . " AND id_agencia2=" . $selects[0];
}
if($selects[2] != 0){
    $condi = $condi . " AND id_tipopol=" . $selects[2];
}
//FUENTE DE FONDOS
if ($radios[1] == "anyf") {
    $condi = $condi . " AND id_fuente_fondo=" . $selects[1];
}
$titlereport = " AL " . date("d-m-Y", strtotime($hoy));
//RANGO DE FECHAS
if ($radios[2] == "frango") {
    $condi = $condi . " AND feccnt BETWEEN '" . $inputs[0] . "' AND '" . $inputs[1] . "'";
    $titlereport = " DEL " . date("d-m-Y", strtotime($inputs[0])) . " AL " . date("d-m-Y", strtotime($inputs[1]));
}

$dts ="id_tipopol, tipopol, id, id_ctb_diario, id_fuente_fondo, fuente_fondo_des,numcom, numdoc,id_ctb_nomenclatura, ccodcta, cdescrip, 
SUM(debe) AS debe, SUM(haber) AS haber, glosa, feccnt, fecdoc, id_tb_usu, id_agencia, estado, nombrecheque, orden";
//$strquery = "SELECT * from ctb_diario_mov WHERE estado=1" . $condi . "GROUP BY id_ctb_nomenclatura";
$strquery = "SELECT ". $dts ." from ctb_diario_mov WHERE estado=1" . $condi . "GROUP BY feccnt, id_ctb_nomenclatura";

$querypol = mysqli_query($conexion, $strquery);
$ctbmovdata[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($querypol)) {
    $ctbmovdata[$j] = $fil;
    $j++;
}
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos']);
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
//----------------------
//$ctbmovdata[$j] = $ctbmovdata[0];
switch ($tipo) {
    case 'xlsx';
        printxls($ctbmovdata);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport], $info);
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info)
{
    /*     $oficina = "Coban";
    $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    $direccionins = "Canton vipila zona 1";
    $emailins = "fape@gmail.com";
    $telefonosins = "502 43987876";
    $nitins = "1323244234";
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../../includes/img/fape.jpeg"; */

    $oficina = utf8_decode($info[0]["nom_agencia"]);
    $institucion = utf8_decode($info[0]["nomb_comple"]);
    $direccionins = utf8_decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        //atributos de la clase
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $rango;
        public $tipocuenta;
        public $saldoant;
        public $datos;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->datos = $datos;
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
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
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(10);

            $this->SetFont($fuente, 'B', 10);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'PARTIDA DOBLE CONSOLIDADA' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 47;

            $this->Cell($ancho_linea, 5, 'CODIGO', 'B', 0, 'L');
            $this->Cell($ancho_linea + 21, 5, 'NOMBRE', 'B', 0, 'L'); //
            $this->Cell($ancho_linea - 10, 5, 'DEBE', 'B', 0, 'R');
            $this->Cell($ancho_linea - 10, 5, 'HABER', 'B', 1, 'R');
            $this->Ln(4);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo 
            // $this->Image($this->pathlogo, 175, 279, 28);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 47;
    $pdf->SetFont($fuente, '', 8);
    $fila = 0;
    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $header = true;
    $footer = false;
    $fechN=0; $nn=0;
while ($fila < count($registro)) {
        $partida = $registro[$fila]["numcom"];
        $fecha = date("d-m-Y", strtotime($registro[$fila]["feccnt"]));
        $numdoc = $registro[$fila]["numdoc"];
        $glosa = $registro[$fila]["glosa"];
        $codcta = $registro[$fila]["ccodcta"];
        $nomcuenta = utf8_decode($registro[$fila]["cdescrip"]);
        $debe = $registro[$fila]["debe"];
        $haber = $registro[$fila]["haber"];
        $idnumcom = $registro[$fila]["id_ctb_diario"];
        
        if ($header) {
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea + 1, 'Fecha: ' . $fecha, 'B', 0, 'L', 0, '', 1, 0);
            //$pdf->CellFit($ancho_linea2 + 16, $tamanio_linea + 1, 'Partida No.: ' . $partida, 'B', 0, 'L', 0, '', 1, 0);
              $pdf->CellFit($ancho_linea2 + 16, $tamanio_linea + 1, '                       ', 'B', 0, 'L', 0, '', 1, 0);
            //$pdf->CellFit($ancho_linea2 + 16, $tamanio_linea + 1, 'Doc.:' . $numdoc, 'B', 1, 'L', 0, '', 1, 0);
              $pdf->CellFit($ancho_linea2 + 16, $tamanio_linea + 1, '               ', 'B', 1, 'L', 0, '', 1, 0);
            $header = false;
        }
        if ($fila != array_key_last($registro)) {
            $fechaN = date("d-m-Y", strtotime($registro[$fila + 1]["feccnt"]));
            if ($idnumcom != $registro[$fila + 1]["id_ctb_diario"] AND $fechaN != $fecha) {
                $header = true;
                $footer = true;
            }
        } else {
            $footer = true;
        }
        /* 
        //****** INICIO ******   Agrupacion de DEBE Y HABER en la misma fila
        $pdf->SetFont($fuente, '', 8);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $codcta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 21, $tamanio_linea + 1, $nomcuenta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($debe, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($haber, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        */
        //******  FIN ****** 
          //****** INICIO ****** DEBE Y HABER separado por fila
        if($debe>0){
            $pdf->SetFont($fuente, '', 8);
            $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $codcta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 + 21, $tamanio_linea + 1, $nomcuenta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($debe, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format(0, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        }
        if($haber>0){
            $pdf->SetFont($fuente, '', 8);
            $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $codcta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 + 21, $tamanio_linea + 1, $nomcuenta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format(0, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($haber, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        }
        //  ******  FIN ******
        $sumd = $sumd + $debe;
        $sumh = $sumh + $haber;
        $sumtd = $sumtd + $debe;
        $sumth = $sumth + $haber;


        if ($footer) {
            //GLOSA PARTIDAS INDIVIDUALES
            $pdf->Cell($ancho_linea2 * 2 + 21, $tamanio_linea / 3, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea2 * 2 - 20, $tamanio_linea / 3, ' ', 'B', 1, 'R');
            //-------
            $pdf->Ln(2);
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->SetFont($fuente, 'I', 8);
            $pdf->MultiCell($ancho_linea2 * 2 + 21, 4, utf8_decode('CONSOLIDACION DE TODAS LAS OPERACIONES CORRESPONDIENTES AL DIA ' . $fecha));
            $x += $ancho_linea2 * 2 + 21;
            $y2 = $pdf->GetY();

            $y3 = ($y > $y2) ? 3 : $y2 - $y;

            $pdf->SetXY($x, $pdf->GetY() - $y3);

            $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, number_format($sumd, 2, '.', ','), 'B', 0, 'R');
            $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, number_format($sumh, 2, '.', ','), 'B', 1, 'R');
            $pdf->Cell($ancho_linea2 * 2 + 21, $tamanio_linea, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea2 * 2 - 20, $tamanio_linea / 4, ' ', 'B', 1, 'R');
            $sumd = 0;
            $sumh = 0;
            $footer = false;
            $pdf->Ln(10);
        }
        $fila++;
    }
    $pdf->Cell($ancho_linea2 * 2 + 21, $tamanio_linea, 'TOTAL GENERAL: ', '', 0, 'R');
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, number_format($sumtd, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, number_format($sumth, 2, '.', ','), 'BT', 1, 'R');
    $pdf->Cell($ancho_linea2 * 2 + 21, $tamanio_linea, ' ', '', 0, 'R');
    $pdf->Cell($ancho_linea2 * 2 - 20, $tamanio_linea / 4, ' ', 'B', 1, 'R');

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $paginas = $pdf->PageNo();

    $opResult = array(
        'status' => 1,
        'pages' => $paginas,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Partida Contable Consolidada",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );

    //SE OBLIGA A DESCARGA DIRECTA CUANDO LA CANTIDAD DE PAGINAS SUPERA O ES IGUAL A 500
    if ($paginas >= 500) {
        $opResult["download"] = 1;
    }
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("PartidaContableConsolidada");


    //$activa->getColumnDimension("A")->setWidth(15);
    //$activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(15);
    $activa->getColumnDimension("C")->setWidth(35);
    $activa->getColumnDimension("D")->setWidth(25);
    $activa->getColumnDimension("E")->setWidth(15);
    $activa->getColumnDimension("F")->setWidth(15);
    // $activa->getColumnDimension("I")->setWidth(15);

    //$activa->setCellValue('A1', 'PARTIDA');
    $activa->setCellValue('A1', 'FECHA');
    //$activa->setCellValue('B1', 'DOCUMENTO');
    $activa->setCellValue('B1', 'CUENTA');
    $activa->setCellValue('C1', 'NOMBRE CUENTA');
    $activa->setCellValue('D1', 'FONDO');
    $activa->setCellValue('E1', 'DEBE');
    $activa->setCellValue('F1', 'HABER');
    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $fila = 0;
    $header = true;
    $footer = false;
    $i = 2;
    while ($fila < count($registro)) {
        $partida = $registro[$fila]["numcom"];
        $fecha = date("d-m-Y", strtotime($registro[$fila]["feccnt"]));
        $numdoc = $registro[$fila]["numdoc"];
        $glosa = $registro[$fila]["glosa"];
        $codcta = $registro[$fila]["ccodcta"];
        $nomcuenta = $registro[$fila]["cdescrip"];
        $debe = $registro[$fila]["debe"];
        $haber = $registro[$fila]["haber"];
        $idfondo = $registro[$fila]["id_fuente_fondo"];
        $idnumcom = $registro[$fila]["id_ctb_diario"];
        $fondo = $registro[$fila]["fuente_fondo_des"];

        if ($header) {
            //$activa->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);
           // $activa->setCellValueExplicit('A' . $i, $partida, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('A' . $i, $fecha);
            //$activa->setCellValueExplicit('B' . $i, $numdoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $header = false;
        }

        $activa->setCellValueExplicit('B' . $i, $codcta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('C' . $i, $nomcuenta);
        $activa->setCellValue('D' . $i, $fondo);
        $activa->setCellValue('E' . $i, $debe);
        $activa->setCellValue('F' . $i, $haber);
        //$activa->setCellValue('H' . $i, $idfondo);
        $sumd += $debe;
        $sumh += $haber;
        $sumtd += $debe;
        $sumth += $haber;

        if ($fila != array_key_last($registro)) {
            $fechaN = date("d-m-Y", strtotime($registro[$fila + 1]["feccnt"]));
            if ($idnumcom != $registro[$fila + 1]["id_ctb_diario"] AND $fechaN != $fecha) {
                $header = true;
                $footer = true;
            }
        } else {
            $footer = true;
        }
        $i++;
        if ($footer) {
            $activa->mergeCells('A' . $i . ':D' . $i);
            $activa->getStyle('A' . $i . ':F' . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('CCCCCC');
            $activa->getStyle('A' . $i . ':F' . $i)->getFont()->setBold(true);
            $activa->setCellValue('A' . $i, 'CONSOLIDACION DE TODAS LAS OPERACIONES CORRESPONDIENTES AL DIA ' . $fecha);
            $activa->setCellValue('E' . $i, $sumd);
            $activa->setCellValue('F' . $i, $sumh);
            $sumd = 0;
            $sumh = 0;
            $footer = false;
            $i++;
            $i++;
        }
        $fila++;
    }
    $i++;
    $activa->setCellValue('E' . $i, $sumtd);
    $activa->setCellValue('F' . $i, $sumth);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Partida Contable Consolidada",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}