<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
include __DIR__ . '/../funciones/func_ctb.php';

require '../../../fpdf/fpdf.php';
require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

list($finicio, $ffin, $idcuenta, $cuentaCodigo) = $inputs;
list($codofi, $fondoid) = $selects;
list($rcuenta, $rfondo, $ragencia) = $radios;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++
    +++([[`finicio`,`ffin`,`idcuenta`,`cuenta`],[`codofi`,`fondoid`],[`rcuentas`,`rfondos`,`ragencia`],[]]) ++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if ($rcuenta == "anycuen" && $idcuenta == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccione una cuenta contable']);
    return;
}
if (!validateDate($finicio, 'Y-m-d') || !validateDate($ffin, 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    return;
}
if ($finicio > $ffin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++ ARMANDO LA CONSULTA ++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

$longitudcuenta = strlen($cuentaCodigo);

$condi = ($ragencia == "anyofi") ? " AND id_agencia2=?" : "";
$condi .= ($rfondo == "anyf") ? " AND id_fuente_fondo=?" : "";
$condi .= ($rcuenta == "anycuen") ? " AND substr(ccodcta,1,$longitudcuenta)=?" : "";

$parameters = [$finicio, $ffin];
$parameters = ($ragencia == "anyofi") ? array_merge($parameters, [$codofi]) : $parameters;
$parameters = ($rfondo == "anyf") ? array_merge($parameters, [$fondoid]) : $parameters;
$parameters = ($rcuenta == "anycuen") ? array_merge($parameters, [$cuentaCodigo]) : $parameters;

$strquery = "SELECT * from ctb_diario_mov WHERE estado=1 AND feccnt BETWEEN ? AND ? $condi ORDER BY id_ctb_nomenclatura,feccnt,id";

$titlereport = " DEL " . setdatefrench($finicio) . " AL " . setdatefrench($ffin);

$showmensaje = false;
try {
    $database->openConnection();
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++ CONSULTA PRINCIPAL ++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $ctbmovdata = $database->getAllResults($strquery, $parameters);
    if (empty($ctbmovdata)) {
        $showmensaje = true;
        throw new Exception("No hay datos en la fecha indicada");
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++ SALDO ANTERIOR (DATOS DE PARTIDA DE APERTURA), CON LAS MISMAS CONDICIONES QUE LA CONSULTA PRINCIPAL +++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $strque = "SELECT sum(debe) sdebe,sum(haber) shaber,id_ctb_nomenclatura idcuensal from ctb_diario_mov 
                WHERE estado=1 AND (id_tipopol= 9) AND feccnt BETWEEN ? AND ? $condi GROUP BY id_ctb_nomenclatura ORDER BY id_ctb_nomenclatura";
    $salantdata = $database->getAllResults($strque, $parameters);

    $strquery_count = "SELECT COUNT(*) as num_registros FROM ctb_diario_mov WHERE estado=1  AND feccnt BETWEEN ? AND ? $condi";
    $num_registros = $database->getAllResults($strquery_count, $parameters)[0]['num_registros'];

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++ INFO INSTITUCION ++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

$flag = 0; //comentar esta linea por si necesiten que haya saldo inicial de la partida de apertura

//TIPO DE ARCHIVO A IMPRIMIR
switch ($tipo) {
    case 'xlsx';
        printxls($ctbmovdata, [$flag, $salantdata], $num_registros);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport, $flag, $salantdata], $info, $num_registros);
        break;
}
//funcion para generar pdf
function printpdf($registro, $datos, $info, $num_registros)
{

    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
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
            // $this->Image($this->pathlogoins, 10, 13, 33);

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 9);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            // $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            // $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            // $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            // $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(2);

            $this->SetFont($fuente, 'B', 10);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'LIBRO MAYOR ' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 30;

            $this->Cell($ancho_linea - 15, 5, 'FECHA', 'B', 0, 'L');
            $this->Cell($ancho_linea - 10, 5, 'PARTIDA', 'B', 0, 'L');
            $this->Cell($ancho_linea + 55, 5, 'DESCRIPCION', 'B', 0, 'L');
            $this->Cell($ancho_linea - 5, 5, 'DEBE', 'B', 0, 'R');
            $this->Cell($ancho_linea - 5, 5, 'HABER', 'B', 0, 'R');
            $this->Cell($ancho_linea - 5, 5, 'SALDO', 'B', 1, 'R');
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
            $this->SetFont('Arial', 'I', 6);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 2;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 8);
    $flag = $datos[1];
    $saldant = $datos[2];
    $fila = 0;
    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $saldo = 0;
    $sumtd = 0;
    $sumth = 0;
    $header = true;
    $footer = false;
    while ($fila < count($registro)) {
        $partida = $registro[$fila]["numcom"];
        $fecha = date("d-m-Y", strtotime($registro[$fila]["feccnt"]));
        $numdoc = $registro[$fila]["numdoc"];
        $glosa = decode_utf8(trim($registro[$fila]["glosa"]));
        $idcuenta = $registro[$fila]["id_ctb_nomenclatura"];
        $codcta = $registro[$fila]["ccodcta"];
        $nomcuenta = decode_utf8($registro[$fila]["cdescrip"]);
        $debe = $registro[$fila]["debe"];
        $haber = $registro[$fila]["haber"];
        $idnumcom = $registro[$fila]["id_ctb_diario"];

        if ($header) {
            //ENCABEZADOS CUENTAS INDIVIDUALES
            $pdf->SetFont($fuente, 'B', 6);
            $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, 'Cuenta: ' . $codcta, 'B', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 * 2 + 3, $tamanio_linea + 1, 'Nombre: ' . $nomcuenta, 'B', 0, 'L', 0, '', 1, 0);

            //VERIFICAR SI TIENE SALDO ANTERIOR
            if ($flag == 1) {
                $isal = array_search($idcuenta, array_column($saldant, 'idcuensal'));
                $saldo = ($isal != false) ? ($saldant[$isal]["sdebe"] - $saldant[$isal]["shaber"]) : 0;
            } else {
                $saldo = 0;
            }

            $pdf->CellFit($ancho_linea2 * 2 + 12, $tamanio_linea + 1, 'Saldo Ant.:' . number_format($saldo, 2, '.', ','), 'B', 1, 'R', 0, '', 1, 0);
            $header = false;
        }

        //DETALLES PARTIDAS INDIVIDUALES
        $pdf->SetFont($fuente, '', 6);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea, $fecha, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea, $partida, '', 0, 'L', 0, '', 1, 0);

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($ancho_linea2 + 55, 3, $glosa . ' - ' . $numdoc);
        $x += $ancho_linea2 + 55;
        $y2 = $pdf->GetY();
        if ($y > $y2) {
            $y3 = 3;
            $y = $y2;
        } else {
            $y3 = $y2 - $y;
        }
        $pdf->SetXY($x, $pdf->GetY() - $y3);
        $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, number_format($debe, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, number_format($haber, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        //SALDO 
        $sumd = $sumd + $debe;
        $sumh = $sumh + $haber;
        $saldo = $saldo + $debe - $haber;
        $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, number_format($saldo, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        $pdf->SetY($y + $y3);

        $sumtd = $sumtd + $debe;
        $sumth = $sumth + $haber;

        if ($fila != array_key_last($registro)) {
            if ($idcuenta != $registro[$fila + 1]["id_ctb_nomenclatura"]) {
                $header = true;
                $footer = true;
            }
        } else {
            $footer = true;
        }
        if ($footer) {
            // $pdf->Ln(1);
            $pdf->Cell($ancho_linea2 * 4, $tamanio_linea, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea2 - 5, $tamanio_linea, number_format($sumd, 2, '.', ','), 'BT', 0, 'R');
            $pdf->Cell($ancho_linea2 - 5, $tamanio_linea, number_format($sumh, 2, '.', ','), 'BT', 1, 'R');
            $pdf->Cell($ancho_linea2 * 4, $tamanio_linea, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea2 * 2 - 10, $tamanio_linea / 4, ' ', 'B', 1, 'R');
            $sumd = 0;
            $sumh = 0;
            $pdf->Ln(2);
            $footer = false;
        }
        $fila++;
    }
    $pdf->Cell($ancho_linea2 + 90, $tamanio_linea, 'TOTAL GENERAL: ', '', 0, 'R');
    $pdf->Cell($ancho_linea2 - 5, $tamanio_linea + 2, number_format($sumtd, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2 - 5, $tamanio_linea + 2, number_format($sumth, 2, '.', ','), 'BT', 1, 'R');
    $pdf->Cell($ancho_linea2 + 90, $tamanio_linea, ' ', '', 0, 'R');
    $pdf->Cell($ancho_linea2 * 2 - 10, $tamanio_linea / 4, ' ', 'B', 1, 'R');

    //seccion para agregar el numumero de registros en el reporte
    $pdf->SetFont($fuente, 'B', 8); // Ajusta la fuente y el tamaño para que coincidan con el resto del documento
    $pdf->Ln(10); // Añade un salto de línea para separar visualmente esta sección del resto del contenido
    $pdf->Cell(0, $tamanio_linea + 2, 'Numero de Registros: ' . $num_registros, 0, 1, 'C'); // Añade el texto y el número de registros centrado

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();
    $paginas = $pdf->PageNo();

    $opResult = array(
        'status' => 1,
        'pages' => $paginas,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Mayor",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );

    //SE OBLIGA A DESCARGA DIRECTA CUANDO LA CANTIDAD DE PAGINAS SUPERA O ES IGUAL A 500
    if ($paginas >= 400) {
        $opResult["download"] = 1;
    }

    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $datos, $num_registros)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("LibroMayor");


    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(25);
    $activa->getColumnDimension("C")->setWidth(15);
    $activa->getColumnDimension("D")->setWidth(10);
    $activa->getColumnDimension("E")->setWidth(70);
    $activa->getColumnDimension("F")->setWidth(15);
    $activa->getColumnDimension("G")->setWidth(15);
    $activa->getColumnDimension("H")->setWidth(15);
    $activa->getColumnDimension("I")->setWidth(25);
    $activa->getColumnDimension("J")->setWidth(15);

    $activa->setCellValue('A1', 'CUENTA');
    $activa->setCellValue('B1', 'NOMBRE CUENTA');
    $activa->setCellValue('C1', 'FECHA');
    $activa->setCellValue('D1', 'PARTIDA');
    $activa->setCellValue('E1', 'DESCRIPCION');
    $activa->setCellValue('F1', 'DEBE');
    $activa->setCellValue('G1', 'HABER');
    $activa->setCellValue('H1', 'SALDO');
    $activa->setCellValue('I1', 'NOMBRE CHEQUE');
    $activa->setCellValue('J1', 'NUMDOC');
    $flag = $datos[0];
    $saldant = $datos[1];

    $saldo = 0;
    $iniciom = 4;
    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $fila = 0;
    $i = 2;
    $header = true;
    $footer = false;
    while ($fila < count($registro)) {
        $partida = $registro[$fila]["numcom"];
        $fecha = date("d-m-Y", strtotime($registro[$fila]["feccnt"]));
        $numdoc = $registro[$fila]["numdoc"];
        $numdoc = ($numdoc == "" || $numdoc == NULL) ? " " : $numdoc;
        $glosa = trim($registro[$fila]["glosa"]);
        $idcuenta = $registro[$fila]["id_ctb_nomenclatura"];
        $codcta = $registro[$fila]["ccodcta"];
        $nomcuenta = $registro[$fila]["cdescrip"];
        $debe = $registro[$fila]["debe"];
        $haber = $registro[$fila]["haber"];
        $nomchq = $registro[$fila]["nombrecheque"];
        $idnumcom = $registro[$fila]["id_ctb_diario"];

        if ($header) {
            //VERIFICAR SI TIENE SALDO ANTERIOR
            if ($flag == 1) {
                $isal = array_search($idcuenta, array_column($saldant, 'idcuensal'));
                $saldo = ($isal != false) ? ($saldant[$isal]["sdebe"] - $saldant[$isal]["shaber"]) : 0;
            } else {
                $saldo = 0;
            }
            $i++;
            $finm = $i - 1; //para el fin del merge
            // $activa->mergeCells('A' . $iniciom . ':A' . $finm);
            // $activa->mergeCells('B' . $iniciom . ':B' . $finm);
            // $activa->getStyle('A' . $iniciom . ':A' . $finm)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            // $activa->getStyle('B' . $iniciom . ':B' . $finm)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

            $i++;
            $sumd = 0;
            $sumh = 0;
            $aux = $idcuenta;
            $activa->getStyle('A' . $i . ':B' . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('CCCCCC');
            $activa->getStyle('A' . $i . ':B' . $i)->getFont()->setBold(true);
            $activa->setCellValueExplicit('A' . $i, $codcta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('B' . $i, $nomcuenta);

            $activa->setCellValueExplicit('E' . ($i - 1), 'SALDO ANT.:', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('H' . ($i - 1), $saldo);
            $iniciom = $i;
            $header = false;
        }

        $activa->setCellValue('C' . $i, $fecha);
        $activa->setCellValueExplicit('D' . $i, $partida, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('E' . $i, $glosa);
        $activa->setCellValue('F' . $i, $debe);
        $activa->setCellValue('G' . $i, $haber);
        $activa->setCellValue('I' . $i, $nomchq);
        $activa->setCellValueExplicit('J' . $i, $numdoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        //SALDO
        $sumd = $sumd + $debe;
        $sumh = $sumh + $haber;
        $saldo = $saldo + $debe - $haber;
        //$activa->setCellValue('I' . $i, $saldo);
        $activa->setCellValue('H' . $i, '=H' . ($i - 1) . '+ F' . $i . '-G' . $i);

        if ($fila != array_key_last($registro)) {
            if ($idcuenta != $registro[$fila + 1]["id_ctb_nomenclatura"]) {
                $header = true;
                $footer = true;
            }
        } else {
            $footer = true;
        }
        if ($footer) {
            $i++;
            $activa->setCellValue('E' . $i, 'RESUMEN CUENTAS');
            $activa->setCellValue('F' . $i, $sumd);
            $activa->setCellValue('G' . $i, $sumh);
            $activa->getStyle('E' . $i . ':G' . $i)->getFont()->setBold(true);
            $footer = false;
        }

        $fila++;
        $i++;
    }
    $i++;
    $activa->setCellValue('F' . $i, $sumtd);
    $activa->setCellValue('G' . $i, $sumth);

    // Agregar el número de registros al final del reporte
    $i++;
    $activa->setCellValue('A' . $i, 'Número de Registros:');
    $activa->setCellValue('B' . $i, $num_registros);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Mayor",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
