<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
require '../../../fpdf/fpdf.php';
require "../../../vendor/autoload.php";

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

date_default_timezone_set('America/Guatemala');

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

$oficina = Utf8::decode($info[0]["nom_agencia"]);
$institucion = Utf8::decode($info[0]["nomb_comple"]);
$direccionins = Utf8::decode($info[0]["muni_lug"]);
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
// $rutalogoins = "../../../includes/img/logomicro.png";

//se crea el array que recibe los datos
// $datos = array();
// $datos = $_POST["data"];
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

// //se obtienen las variables del get
// $estado = $datos[5];
// $cuenta = $datos[6];
// $tipo = $datos[7];
// $usuario = $datos[8];
// $oficina = $datos[9];
// $tip_report = $datos[2];
$estado = $radios[0];
$cuenta = $radios[1];
$tipo = $selects[0];
$usuario = $_SESSION['id'];
$oficina = $_SESSION['agencia'];
$tip_report = $_POST["tipo"];

if ($radios[1] == "2") {
    if ($selects[0] == "0") {
        echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar un tipo de cuenta']);
        return;
    }
}

// if ($radios[1] == "1") {
//     if ($selects[0] != "0") {
//         echo json_encode(["Error en su solicitud", '0']);
//         return;
//     }
// }


$fuente = "Courier";
$tamanioFuente = 9;
$tamanioTitulo = 11;
$tamanio_linea = 4; //altura de la linea/celda
$ancho_linea = 30; //anchura de la linea/celda
$espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
$ancho_linea2 = 20; //anchura de la linea/celda
$espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda

$hoy = date("Y-m-d H:i:s");

$fuente_encabezado = "Arial";
$tamanioFecha = 9; //tamaño de letra de la fecha y usuario
$tamanioEncabezado = 14; //tamaño de letra del encabezado
$tamanioTabla = 11; //tamaño de letra de la fecha y usuario
$linea = 14;

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
    public $user;
    public $conexion;
    public $estado;
    public $cuenta;
    public $tipo;

    public function __construct($conexion, $institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $user, $estado, $cuenta, $tipo)
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
        $this->user = $user;
        $this->conexion = $conexion;
        $this->estado = $estado;
        $this->cuenta = $cuenta;
        $this->tipo = $tipo;
    }

    // Cabecera de página
    function Header()
    {
        //consultar los estados
        $texto_cuentas = "";
        //variables para el texto
        $fuente = "Courier";
        $tamanioFuente = 9;
        $tamanioTitulo = 11;
        $tamanio_linea = 4; //altura de la linea/celda
        $ancho_linea = 30; //anchura de la linea/celda
        $espacio_blanco = 10; //tamaño del espacio en blanco entre cada celda
        $ancho_linea2 = 20; //anchura de la linea/celda
        $espacio_blanco2 = 4; //tamaño del espacio en blanco entre cada celda

        //consultar todas las cuentas
        if ($this->tipo == '0') {
            $texto_cuentas = 'TODAS LAS CUENTAS';
        } else {
            $data_cuentas = mysqli_query($this->conexion, "SELECT `nombre` FROM `ahomtip` WHERE `id_tipo`=$this->tipo");
            while ($rowcuentas = mysqli_fetch_array($data_cuentas, MYSQLI_ASSOC)) {
                $texto_cuentas = strtoupper(Utf8::decode($rowcuentas["nombre"]));
            }
        }

        // ACA ES DONDE EMPIEZA LO DEL FORMATO DE REPORTE---------------------------------------------------
        $hoy = date("Y-m-d H:i:s");
        //fecha y usuario que genero el reporte
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 2, $hoy, 0, 1, 'R');
        $this->Ln(1);
        $this->Cell(0, 2, $this->user, 0, 1, 'R');

        // Logo de la agencia
        $this->Image($this->pathlogoins, 10, 13, 33);

        //tipo de letra para el encabezado
        $this->SetFont('Arial', '', 8);
        // Título
        $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
        $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
        $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
        $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
        $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
        // Salto de línea
        $this->Ln(3);

        $this->SetFont($fuente, '', 10);
        //SECCION DE DATOS DEL CLIENTE
        //TITULO DE REPORTE
        $this->SetFillColor(255, 255, 255);
        $this->Cell(0, 5, 'LISTADO DE CUENTAS ACTIVAS/INACTIVAS', 0, 1, 'C', true);
        $this->Cell(0, 5, $texto_cuentas, 0, 1, 'C', true);
        $this->Ln(5);
        //Fuente
        $this->SetFont($fuente, '', 10);
        //encabezado de tabla
        $this->Cell($ancho_linea2 - 10, $tamanio_linea + 1, ' NUM', 'B', 0, 'C', true); //numero
        $this->Cell($ancho_linea + 5, $tamanio_linea + 1, 'CUENTA', 'B', 0, 'C', true); // cuenta
        $this->Cell(($ancho_linea + $ancho_linea + 5), $tamanio_linea + 1, 'NOMBRE COMPLETO', 'B', 0, 'C', true); //nombre
        $this->Cell($ancho_linea2, $tamanio_linea + 1, 'ESTADO', 'B', 0, 'C', true); //Estado
        // $this->Cell($ancho_linea2, $tamanio_linea + 1, 'ACTIVACION', 'B', 0, 'C', true); //activacion
        $this->Cell($ancho_linea, $tamanio_linea + 1, 'APERTURA', 'B', 0, 'C', true); //apertura
        $this->Cell($ancho_linea, $tamanio_linea + 1, 'CANCELACION', 'B', 0, 'C', true); //cancelacion
        $this->Ln(8);
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

$consulta = "SELECT cta.ccodaho, cl.short_name, cta.estado, cta.fecha_apertura, cta.fecha_cancel
FROM `tb_cliente` AS cl
    INNER JOIN `ahomcta` AS cta ON cl.idcod_cliente = cta.ccodcli
    INNER JOIN `ahomtip` AS tp ON tp.ccodtip = SUBSTRING( cta.ccodaho ,7 , 2)";

if ($estado != "0" || $tipo != "0") {
    $consulta .= " WHERE";
    if ($estado != "0") {
        $consulta .= " cta.estado='$estado'";
    }
    if ($tipo != "0") {
        //obtener el codtip
        $data = mysqli_query($conexion, "SELECT `ccodtip` FROM `ahomtip` WHERE `id_tipo`='$tipo'");
        while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
            $ccodtip = ($rowdata["ccodtip"]);
        }
        if ($estado == "0") {
            $consulta .= " tp.ccodtip='$ccodtip'";
        } else {
            $consulta .= " AND tp.ccodtip='$ccodtip'";
        }
    }
}
$consulta .= " ORDER BY cta.ccodaho ASC;";

$data = mysqli_query($conexion, $consulta);
//CASO PARA LOS REPORTES
switch ($tip_report) {
    case "pdf": {
            // Creación del objeto de la clase heredada
            $pdf = new PDF($conexion, $institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins,  $usuario, $estado, $cuenta, $tipo);
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $contador = 0;
            while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                $bd_ccodaho = ($rowdata["ccodaho"]);
                $bd_shortname = strtoupper(Utf8::decode($rowdata["short_name"]));
                $bd_estado = (($rowdata["estado"]));
                $bd_fechaapertura = (($rowdata["fecha_apertura"] == "" ? " - " : $rowdata["fecha_apertura"]));
                $bd_fechacancel = (($rowdata["fecha_cancel"] == "" ? " - " : $rowdata["fecha_cancel"]));
                $contador++;

                //se insertan los registros
                $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, $contador, 'B', 0, 'C', 0, '', 1, 0); //numero
                $pdf->CellFit($ancho_linea + 5, $tamanio_linea + 1, $bd_ccodaho, 'B', 0, 'C', 0, '', 1, 0); // cuenta
                $pdf->CellFit(($ancho_linea + $ancho_linea + 5), $tamanio_linea + 1, Utf8::decode($bd_shortname), 'B', 0, 'L', 0, '', 1, 0); //nombre
                $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $bd_estado, 'B', 0, 'C', 0, '', 1, 0); //Estado
                // $this->Cell($ancho_linea2, $tamanio_linea + 1, 'ACTIVACION', 'B'rue); //activacion
                $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $bd_fechaapertura, 'B', 0, 'C', 0, '', 1, 0); //apertura
                $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $bd_fechacancel, 'B', 0, 'C', 0, '', 1, 0); //cancelacion
                $pdf->Ln(6);
            }

            //forma de migrar el archivo
            ob_start();
            $pdf->Output();
            $pdfData = ob_get_contents();
            ob_end_clean();
            $opResult = array(
                'status' => 1,
                'mensaje' => 'Reporte generado correctamente',
                'namefile' => "Listado de cuentas",
                'tipo' => "pdf",
                'data' => "data:application/pdf;base64," . base64_encode($pdfData)
            );
            mysqli_close($conexion);
            echo json_encode($opResult);
        }
        break;
    case "xlsx": {
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
            $hojaReporte->getStyle("A1:F1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
            $hojaReporte->getStyle("A2:F2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
            //centrar el texto de la fecha
            $hojaReporte->getStyle("A1:F1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $hojaReporte->getStyle("A2:F2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            //hacer grande las letras del encabezado
            $hojaReporte->getStyle("A4:F4")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
            $hojaReporte->getStyle("A5:F5")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
            $hojaReporte->getStyle("A6:F6")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
            $hojaReporte->getStyle("A7:F7")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
            $hojaReporte->getStyle("A8:F8")->getFont()->setSize($tamanioEncabezado)->setName($fuente_encabezado);
            //centrar el texto del encabezado
            $hojaReporte->getStyle("A4:F4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $hojaReporte->getStyle("A5:F5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $hojaReporte->getStyle("A6:F6")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $hojaReporte->getStyle("A7:F7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $hojaReporte->getStyle("A8:F8")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            //hacer pequeño las letras del encabezado de titulo
            $hojaReporte->getStyle("A10:F10")->getFont()->setSize($tamanioTabla)->setName($fuente);
            $hojaReporte->getStyle("A11:F11")->getFont()->setSize($tamanioTabla)->setName($fuente);
            //centrar los encabezado de la tabla
            $hojaReporte->getStyle("A10:F10")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $hojaReporte->getStyle("A11:F11")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            //titulo de reporte
            //consultar los estados
            $texto_cuentas = "";
            if ($tipo == '0') {
                $texto_cuentas = 'TODAS LAS CUENTAS';
            } else {
                $data_cuentas = mysqli_query($conexion, "SELECT `nombre` FROM `ahomtip` WHERE `id_tipo`=$tipo");
                while ($rowcuentas = mysqli_fetch_array($data_cuentas, MYSQLI_ASSOC)) {
                    $texto_cuentas = strtoupper(Utf8::decode($rowcuentas["nombre"]));
                }
            }
            $hojaReporte->setCellValue("A10", "LISTADO DE CUENTAS ACTIVAS/INACTIVAS");
            $hojaReporte->setCellValue("A11", strtoupper($texto_cuentas));

            //combinacion de celdas
            $hojaReporte->mergeCells('A1:F1');
            $hojaReporte->mergeCells('A2:F2');
            $hojaReporte->mergeCells('A4:F4');
            $hojaReporte->mergeCells('A5:F5');
            $hojaReporte->mergeCells('A6:F6');
            $hojaReporte->mergeCells('A7:F7');
            $hojaReporte->mergeCells('A8:F8');
            $hojaReporte->mergeCells('A10:F10');
            $hojaReporte->mergeCells('A11:F11');

            # Escribir encabezado de la tabla
            $encabezado_tabla = ["NUM", "CUENTA", "NOMBRE COMPLETO", "ESTADO", "APERTURA", "CANCELACION"];
            # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
            $hojaReporte->fromArray($encabezado_tabla, null, 'A13')->getStyle('A13:F13')->getFont()->setName($fuente)->setBold(true);

            $contador = 0;

            while ($rowdata = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                $bd_ccodaho = ($rowdata["ccodaho"]);
                $bd_shortname = strtoupper(Utf8::decode($rowdata["short_name"]));
                $bd_estado = (($rowdata["estado"]));
                $bd_fechaapertura = (($rowdata["fecha_apertura"] == "" ? " - " : $rowdata["fecha_apertura"]));
                $bd_fechacancel = (($rowdata["fecha_cancel"] == "" ? " - " : $rowdata["fecha_cancel"]));

                $contador++;

                $hojaReporte->setCellValueByColumnAndRow(1, $linea, $contador);
                $hojaReporte->setCellValueByColumnAndRow(2, $linea, $bd_ccodaho);
                $hojaReporte->setCellValueByColumnAndRow(3, $linea, $bd_shortname);
                $hojaReporte->setCellValueByColumnAndRow(4, $linea, $bd_estado);
                $hojaReporte->setCellValueByColumnAndRow(5, $linea, $bd_fechaapertura);
                $hojaReporte->setCellValueByColumnAndRow(6, $linea, $bd_fechacancel);

                $hojaReporte->getStyle("A" . $linea . ":F" . $linea)->getFont()->setName($fuente);


                $linea++;
            }

            $hojaReporte->getColumnDimension('B')->setAutoSize(TRUE);
            $hojaReporte->getColumnDimension('C')->setAutoSize(TRUE);
            $hojaReporte->getColumnDimension('E')->setAutoSize(TRUE);
            $hojaReporte->getColumnDimension('F')->setAutoSize(TRUE);

            //crea el archivo para que se descarge
            ob_start();
            $writer = IOFactory::createWriter($spread, 'Xlsx');
            $writer->save("php://output");
            $xlsData = ob_get_contents();
            ob_end_clean();
            //envio de repuesta a ajax para descargarlos
            $opResult = array(
                'status' => 1,
                'mensaje' => 'Reporte generado correctamente',
                'namefile' => "Listado de cuentas",
                'tipo' => "xlsx",
                'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
            );
            mysqli_close($conexion);
            echo json_encode($opResult);
        }
        break;
}
