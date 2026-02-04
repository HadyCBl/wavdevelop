<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';

require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$selects = $datos[1];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

//-------ARMANDO LA CONSULTA------------
$consulta = "SELECT nom.ccodcta, nom.cdescrip, nom.tipo FROM ctb_nomenclatura nom WHERE estado='1' ";
//validando si ha seleccionado un nivel
if ($selects[0] != '0') {
    $consulta .= " AND (nom.ccodcta LIKE '$selects[0]%')";
}
if ($selects[1] != '0') {
    $consulta .= " AND (LENGTH(nom.ccodcta)= '$selects[1]')";
}
$consulta .= " order by nom.ccodcta";
//SE CARGA LA CONSULTA
$resultados = mysqli_query($conexion, $consulta);
$ctbdata[] = [];

$i = 0;
while ($fila = mysqli_fetch_array($resultados)) {
    $ctbdata[$i] = $fila;
    $i++;
}
if ($i == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos']);
    return;
}
// $ctbmovdata[$j] = $ctbmovdata[0];
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

switch ($tipo) {
    case 'xlsx';
        printxls($ctbdata, [$selects[0], $selects[1], $archivo[0]], $conexion, $info);
        break;
    case 'pdf':
        printpdf($ctbdata, [$selects[0], $selects[1], $archivo[0]], $conexion, $info);
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $conexion, $info)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
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
        public $datos;
        public $conexion;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $conexion)
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
            $this->conexion = $conexion;
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(1);
            //realizar la consulta para obtener el usuario
            $data_usu = mysqli_query($this->conexion, "SELECT usu FROM tb_usuario WHERE id_usu=" . $this->datos[2]);
            while ($res = mysqli_fetch_array($data_usu, MYSQLI_ASSOC)) {
                $codusu = strtoupper(($res["usu"]));
            }
            $this->Cell(0, 2, $codusu, 0, 1, 'R');
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
            $this->Ln(5);

            $this->SetFont($fuente, 'B', 10);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);

            //Se hace la consulta para saber si hay una clase seleccionada o bien un nivel
            $texto_titulo = "";
            ($this->datos[0] != '0') ? ($texto_titulo .= "CUENTAS DE LA CLASE " . $this->datos[0] . "/") : ($texto_titulo .= "TODAS LAS CLASES/");
            ($this->datos[1] != '0') ? ($texto_titulo .= "NIVEL " . $this->datos[1]) : ($texto_titulo .= "TODOS LOS NIVELES");
            $this->Cell(0, 5, 'CATALOGO DE CUENTAS', 0, 1, 'C', true);
            $this->Cell(0, 5, $texto_titulo, 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            $this->Ln(4);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 50;

            $this->CellFit($ancho_linea - 25, 5, '#', 1, 0, 'C', 0, '', 1, 0); //
            $this->CellFit($ancho_linea, 5, 'CUENTA', 1, 0, 'C', 0, '', 1, 0); //
            $this->CellFit($ancho_linea + 30, 5, 'NOMBRE', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea - 15, 5, 'TIPO', 1, 0, 'C', 0, '', 1, 0);
            $this->Ln(6);
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $conexion);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    //LISTADO DE REGISTROS
    $fuente = "Courier";
    $tamanio_linea = 4; //altura de la linea/celda
    $ancho_linea = 50; //anchura de la linea/celda
    $pdf->SetFont($fuente, '', 9);
    $fila = 0;
    $num = 1;
    while ($fila < count($registro)) {
        $bd_ccodcta = $registro[$fila]["ccodcta"];
        $bd_cdescrip = strtoupper(Utf8::decode($registro[$fila]["cdescrip"]));
        $bd_tipo = strtoupper(Utf8::decode($registro[$fila]["tipo"]));

        //se insertan los registros
        $pdf->CellFit($ancho_linea - 25, $tamanio_linea + 1, $num, 0, 0, 'L', 0, '', 1, 0); //numero
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $bd_ccodcta, 0, 0, 'L', 0, '', 1, 0); // cuenta
        $pdf->CellFit($ancho_linea + 30, $tamanio_linea + 1, $bd_cdescrip, 0, 0, 'L', 0, '', 1, 0); //Estado
        $pdf->CellFit($ancho_linea - 15, $tamanio_linea + 1, (($bd_tipo == 'R') ? 'RESUMEN' : 'DETALLE'), 0, 0, 'C', 0, '', 1, 0); //apertura
        $pdf->Ln(5);
        $fila++;
        $num++;
    }

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Diario",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $datos, $conexion, $info)
{
    require '../../../vendor/autoload.php';

    // $oficina = "Coban";
    // $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    // $direccionins = "Canton vipila zona 1";
    // $emailins = "fape@gmail.com";
    // $telefonosins = "502 43987876";
    // $nitins = "1323244234";
    // $usuario = "9999";

    // $rutalogomicro = "../../../includes/img/logomicro.png";
    // $rutalogoins = "../../../includes/img/fape.jpeg";


    $oficina = ($info[0]["nom_agencia"]);
    $institucion = ($info[0]["nomb_comple"]);
    $direccionins = ($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

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
        ->setSubject('Listado de Cuentas Contables')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');
    //-----------RELACIONADO CON LAS PROPIEDADES DEL ARCHIVO----------------------------

    //-----------RELACIONADO CON EL ENCABEZADO----------------------------
    # Como ya hay una hoja por defecto, la obtenemos, no la creamos
    $hojaReporte = $spread->getActiveSheet();
    $hojaReporte->setTitle("Cuentas contables");

    //realizar la consulta para obtener el usuario
    $data_usu = mysqli_query($conexion, "SELECT usu FROM tb_usuario WHERE id_usu=" . $datos[2]);
    while ($res = mysqli_fetch_array($data_usu, MYSQLI_ASSOC)) {
        $codusu = strtoupper(($res["usu"]));
    }

    //insertarmos la fecha y usuario
    $hojaReporte->setCellValue("A1", $hoy);
    $hojaReporte->setCellValue("A2", $codusu);
    //informacion de la agencia o cooperativa
    $hojaReporte->setCellValue("A4", $institucion);
    $hojaReporte->setCellValue("A5", $direccionins);
    $hojaReporte->setCellValue("A6", "Email: " . $emailins);
    $hojaReporte->setCellValue("A7", "Tel: " . $telefonosins);
    $hojaReporte->setCellValue("A8", "NIT: " . $nitins);

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $hojaReporte->getStyle("A1:D1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A2:D2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $hojaReporte->getStyle("A1:D1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A2:D2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer grande las letras del encabezado
    $hojaReporte->getStyle("A4:D4")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A5:D5")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A6:D6")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A7:D7")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A8:D8")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
    //centrar el texto del encabezado
    $hojaReporte->getStyle("A4:D4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A5:D5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A6:D6")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A7:D7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A8:D8")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer pequeño las letras del encabezado de titulo
    $hojaReporte->getStyle("A10:D10")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $hojaReporte->getStyle("A11:D11")->getFont()->setSize($tamanioTabla)->setName($fuente);
    //centrar los encabezado de la tabla
    $hojaReporte->getStyle("A10:D10")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A11:D11")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //titulo de reporte
    //consultar los estados
    $texto_titulo = "";
    ($datos[0] != '0') ? ($texto_titulo .= "CUENTAS DE LA CLASE " . $datos[0] . "/") : ($texto_titulo .= "TODAS LAS CLASES/");
    ($datos[1] != '0') ? ($texto_titulo .= "NIVEL " . $datos[1]) : ($texto_titulo .= "TODOS LOS NIVELES");

    $hojaReporte->setCellValue("A10", "CATALOGO DE CUENTAS");
    $hojaReporte->setCellValue("A11", strtoupper($texto_titulo));

    //combinacion de celdas
    $hojaReporte->mergeCells('A1:D1');
    $hojaReporte->mergeCells('A2:D2');
    $hojaReporte->mergeCells('A4:D4');
    $hojaReporte->mergeCells('A5:D5');
    $hojaReporte->mergeCells('A6:D6');
    $hojaReporte->mergeCells('A7:D7');
    $hojaReporte->mergeCells('A8:D8');
    $hojaReporte->mergeCells('A10:D10');
    $hojaReporte->mergeCells('A11:D11');

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["NO.", "CUENTA", "NOMBRE", "TIPO"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $hojaReporte->fromArray($encabezado_tabla, null, 'A13')->getStyle('A13:D13')->getFont()->setName($fuente)->setBold(true);

    //Insercion de registros
    $linea = 14;
    $fila = 0;
    $num = 1;
    while ($fila < count($registro)) {
        $bd_ccodcta = $registro[$fila]["ccodcta"];
        $bd_cdescrip = strtoupper(($registro[$fila]["cdescrip"]));
        $bd_tipo = strtoupper(($registro[$fila]["tipo"]));

        $hojaReporte->setCellValueByColumnAndRow(1, $linea, $num);
        $hojaReporte->setCellValueByColumnAndRow(2, $linea, $bd_ccodcta);
        $hojaReporte->setCellValueByColumnAndRow(3, $linea, $bd_cdescrip);
        $hojaReporte->setCellValueByColumnAndRow(4, $linea, (($bd_tipo == 'R') ? 'RESUMEN' : 'DETALLE'));

        $hojaReporte->getStyle("A" . $linea . ":D" . $linea)->getFont()->setName($fuente);


        $linea++;
        $fila++;
        $num++;
    }

    $hojaReporte->getColumnDimension('A')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('B')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('D')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('C')->setAutoSize(TRUE);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spread, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cuentas Contables",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
