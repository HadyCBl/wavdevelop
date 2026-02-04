<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

include '../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

//[[`fecinicio`,`fecfinal`],[`codofi`,`fondoid`,`ejecutivo`],[`ragencia`,`rfondos`,`rasesor`,`tipoentidad`],[]],`pdf`,`prepago_recuperado`,0)
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++++ VALIDACIONES PAPA' +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}
if ($radios[0] == "anyofi" && $selects[0] == 0) {
    echo json_encode(['mensaje' => 'Seleccione una agencia válida', 'status' => 0]);
    return;
}
if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['mensaje' => 'Seleccione un fondo válido', 'status' => 0]);
    return;
}
if ($radios[2] == "anyasesor" && $selects[2] == 0) {
    echo json_encode(['mensaje' => 'Seleccione un asesor válido', 'status' => 0]);
    return;
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$fecinicio = $inputs[0];
$fecfin = $inputs[1];
$where = ($radios[0] == "anyofi") ? " AND ag.id_agencia=" . $selects[0] : "";
$where .= ($radios[1] == "anyf") ? " AND pr.id_fondo=" . $selects[1] : "";
$where .= ($radios[2] == "anyasesor") ? " AND crem.CodAnal=" . $selects[2] : "";
$where .= ($radios[3] == "call") ? "" : " AND crem.TipoEnti='$radios[3]'";
$statuscondi = ($radios[4] == "allstatus") ? "'F','G'" : "'{$radios[4]}'";
$statustitle = ($radios[4] == "F") ? " (VIGENTES) " : (($radios[4] == "G") ? " (CANCELADOS) " : " (VIGENTES Y CANCELADOS)");
$texto_reporte = "Reembolsos de Montos debidos del " . setdatefrench($fecinicio) . " al " . setdatefrench($fecfin) . $statustitle;

$query = "SELECT crem.ccodcta,cli.short_name,ag.nom_agencia,concat(us.nombre,' ',us.apellido) asesor,crem.TipoEnti tipenti,cuotas.*,cli.tel_no1,
IFNULL(cuotasxpag.capxpag,0) capxpag, 
IFNULL(cuotasxpag.intxpag,0) intxpag, 
IFNULL(cuotasxpag.otrxpag,0) otrxpag,
IFNULL(pagos.cappag,0) cappag, 
IFNULL(pagos.intpag,0) intpag, 
IFNULL(pagos.otrpag,0) otrpag,
IFNULL(pagosf.cappagf,0) cappagf, 
IFNULL(pagosf.intpagf,0) intpagf, 
IFNULL(pagosf.otrpagf,0) otrpagf
FROM cremcre_meta crem
INNER JOIN tb_cliente cli ON crem.CodCli=cli.idcod_cliente
INNER JOIN tb_usuario us ON crem.CodAnal=us.id_usu
INNER JOIN tb_agencia ag ON crem.CODAgencia=ag.cod_agenc
INNER JOIN cre_productos pr ON pr.id=crem.CCODPRD
LEFT JOIN ( SELECT ccodcta acc,SUM(nintere) nintere,SUM(ncapita) ncapita, SUM(OtrosPagos) otros FROM Cre_ppg
	WHERE dfecven BETWEEN ? AND ? GROUP BY ccodcta ) AS cuotas ON cuotas.acc=crem.ccodcta
LEFT JOIN ( SELECT ccodcta cxpag,SUM(nintere) intxpag,SUM(ncapita) capxpag, SUM(OtrosPagos) otrxpag FROM Cre_ppg
	WHERE dfecven < ? GROUP BY ccodcta ) AS cuotasxpag ON cuotasxpag.cxpag=crem.ccodcta
LEFT JOIN ( SELECT CCODCTA cuenta,SUM(KP) cappag, SUM(INTERES) intpag, SUM(OTR) otrpag FROM CREDKAR
	WHERE DFECPRO <= ? AND CESTADO!='X' AND CTIPPAG='P' GROUP BY CCODCTA ) AS pagos ON pagos.cuenta=crem.ccodcta
LEFT JOIN ( SELECT CCODCTA cuentaf,SUM(KP) cappagf, SUM(INTERES) intpagf, SUM(OTR) otrpagf FROM CREDKAR
	WHERE DFECPRO BETWEEN  ? AND ? AND CESTADO!='X' AND CTIPPAG='P' GROUP BY CCODCTA ) AS pagosf ON pagosf.cuentaf=crem.ccodcta
WHERE crem.Cestado IN ($statuscondi) AND acc IS NOT NULL " . $where . " ORDER BY ag.id_agencia,crem.CodAnal,crem.TipoEnti,crem.CcodGrupo, crem.ccodcta";

// echo json_encode(['mensaje' => $query, 'status' => 0]);
// return;
//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$fecinicio, $fecfin, $fecinicio, $fecfin, $fecinicio, $fecfin]);

    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros en result.");
    }

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
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}
//FIN TRY

//se manda a impresion
switch ($tipo) {
    case 'xlsx';
        printxls($result, [$texto_reporte, $_SESSION['id'], $hoy]);
        break;
    case 'pdf':
        printpdf($result, [$texto_reporte, $_SESSION['id'], $hoy], $info);
        break;
}

//FUNCION PARA GENERAR EL REPORTE EN PDF

function printxls($datos, $otros)
{
    $hoy = date("Y-m-d H:i:s");
    // $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    // $direccionins = "Canton vipila zona 1";
    // $emailins = "fape@gmail.com";
    // $telefonosins = "502 43987876";
    // $nitins = "1323244234";

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    // $tamanioEncabezado = 14;
    $tamanioTabla = 11;

    $spread = new Spreadsheet();
    $spread
        ->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Visitas prepago')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');
    //-----------RELACIONADO CON LAS PROPIEDADES DEL ARCHIVO----------------------------

    //-----------RELACIONADO CON EL ENCABEZADO----------------------------
    # Como ya hay una hoja por defecto, la obtenemos, no la creamos
    $hojaReporte = $spread->getActiveSheet();
    $hojaReporte->setTitle("Reporte de Visitas Prepago");

    //insertarmos la fecha y usuario
    $hojaReporte->setCellValue("A1", $hoy);
    $hojaReporte->setCellValue("A2", $otros[1]);

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $hojaReporte->getStyle("A1:K1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A2:K2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $hojaReporte->getStyle("A1:K1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A2:K2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer pequeño las letras del encabezado de titulo
    $hojaReporte->getStyle("A4:K4")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $hojaReporte->getStyle("A5:K5")->getFont()->setSize($tamanioTabla)->setName($fuente);
    //centrar los encabezado de la tabla
    $hojaReporte->getStyle("A4:K4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A5:K5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $hojaReporte->setCellValue("A4", "REPORTE");
    $hojaReporte->setCellValue("A5", strtoupper($otros[0]));

    //TITULO DE RECARGOS

    //titulo de recargos
    $hojaReporte->getStyle("A7:K7")->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $hojaReporte->getStyle("A7:K7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->setCellValue("F7", "RECARGOS");

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CÓDIGO CUENTA", "NOMBRE CLIENTE", "AGENCIA", "ASESOR", "KP PACTADO", "KP RECUPERADO", "%", "INT PACTADO", "INT PAGADO", "%", "OTR PACTADO", "OTR RECUPERADO", "%", "TOTAL PACTADO", "TOTAL PAGADO", "TELEFONO"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $hojaReporte->fromArray($encabezado_tabla, null, 'A8')->getStyle('A8:P8')->getFont()->setName($fuente)->setBold(true);

    // Ajustar automáticamente el ancho de las columnas
    $columnas = range('A', 'P');
    foreach ($columnas as $columna) {
        $hojaReporte->getColumnDimension($columna)->setAutoSize(true);
    }

    //combinacion de celdas
    $hojaReporte->mergeCells('A1:K1');
    $hojaReporte->mergeCells('A2:K2');
    $hojaReporte->mergeCells('A4:K4');
    $hojaReporte->mergeCells('A5:K5');
    $hojaReporte->mergeCells('F7:H7');

    $sumcappag = 0;
    $sumintpag = 0;
    $sumotrpag = 0;
    $sumcapita = 0;
    $sumintere = 0;
    $sumotros = 0;

    //CARGAR LOS DATOS
    $fila = 0;
    $linea = 9;

    $fila = 0;
    while ($fila < count($datos)) {
        $cuenta = $datos[$fila]["ccodcta"];
        $nombre = strtoupper(($datos[$fila]["short_name"]));
        $agencia = strtoupper(($datos[$fila]["nom_agencia"]));
        $asesor = strtoupper(($datos[$fila]["asesor"]));
        $tipoenti = strtoupper(($datos[$fila]["tipenti"]));
        $ncapita = $datos[$fila]["ncapita"];
        $nintere = $datos[$fila]["nintere"];
        $notros = $datos[$fila]["otros"];
        $telefono = $datos[$fila]["tel_no1"];


        //+++++++++++++ VERSION 3.0 eliminar si sale mal XD ++++++++++++++++++++++++++++
        //KP
        $ncapita = ($datos[$fila]["cappag"] - $datos[$fila]["cappagf"]) - $datos[$fila]["capxpag"];
        if ($ncapita < 0) {
            //SI NO SE COMPLETO EL PAGO DE CAPITAL ANTES DE LA FECHA INICIO
            //OP1: SE LE SUMARA A LA CUOTA DE CAPITAL DEL MES EN CONSULTA
            // $ncapita = $ncapita + $datos[$fila]["ncapita"];
            //OP2: NO SE LE SUMARA A LA CUOTA DE CAPITAL DEL MES EN CONSULTA, SOLO SE TOMARA LA CUOTA DEL MES EN CONSULTA
            $ncapita = $datos[$fila]["ncapita"];
        } else {
            //SI SE COMPLETO EL PAGO DE CAPITAL ANTES DE LA FECHA INICIO, SE ABONA A LA CUOTA DE CAPITAL DEL MES EN CONSULTA
            $ncapita = $datos[$fila]["ncapita"] - $ncapita;
        }
        $ncapita = max($ncapita, 0);

        //INTERES
        $nintere = ($datos[$fila]["intpag"] - $datos[$fila]["intpagf"]) - $datos[$fila]["intxpag"];
        if ($nintere < 0) {
            //SI NO SE COMPLETO EL PAGO DE INTERES ANTES DE LA FECHA INICIO
            //OP1: SE LE SUMARA A LA CUOTA DE INTERES DEL MES EN CONSULTA
            // $nintere = $nintere + $datos[$fila]["nintere"];
            //OP2: NO SE LE SUMARA A LA CUOTA DE INTERES DEL MES EN CONSULTA, SOLO SE TOMARA LA CUOTA DEL MES EN CONSULTA
            $nintere = $datos[$fila]["nintere"];
        } else {
            //SI SE COMPLETO EL PAGO DE INTERES ANTES DE LA FECHA INICIO, SE ABONA A LA CUOTA DE INTERES DEL MES EN CONSULTA
            $nintere = $datos[$fila]["nintere"] - $nintere;
        }
        $nintere = max($nintere, 0);

        //OTROS
        $notros = ($datos[$fila]["otrpag"] - $datos[$fila]["otrpagf"]) - $datos[$fila]["otrxpag"];
        if ($notros < 0) {
            //SI NO SE COMPLETO EL PAGO DE OTROS ANTES DE LA FECHA INICIO
            //OP1: SE LE SUMARA A LA CUOTA DE OTROS DEL MES EN CONSULTA
            // $notros = $notros + $datos[$fila]["otros"];
            //OP2: NO SE LE SUMARA A LA CUOTA DE OTROS DEL MES EN CONSULTA, SOLO SE TOMARA LA CUOTA DEL MES EN CONSULTA
            $notros = $datos[$fila]["otros"];
        } else {
            //SI SE COMPLETO EL PAGO DE OTROS ANTES DE LA FECHA INICIO, SE ABONA A LA CUOTA DE OTROS DEL MES EN CONSULTA
            $notros = $datos[$fila]["otros"] - $notros;
        }
        $notros = max($notros, 0);
        //+++++++++++++ FIN VERSION 3.0 ++++++++++++++++++++++++++++++++++++++++++++++++

        $sumcapita += $ncapita;
        $sumintere += $nintere;
        $sumotros += $notros;

        //+++++++++++++ VERSION 2.0 DE LO RECUPERADO ++++++++++++++++++++++++++++++++++++
        $cappag = $datos[$fila]["cappag"] - $datos[$fila]["capxpag"];
        $cappag = ($cappag > $datos[$fila]["cappagf"]) ? $datos[$fila]["cappagf"] : $cappag;
        $cappag = ($cappag > 0) ? $cappag : 0;
        $sumcappag += $cappag;

        $intpag = $datos[$fila]["intpag"] - $datos[$fila]["intxpag"];
        $intpag = ($intpag > $datos[$fila]["intpagf"]) ? $datos[$fila]["intpagf"] : $intpag;
        $intpag = ($intpag > 0) ? $intpag : 0;
        $sumintpag += $intpag;

        $otrpag = $datos[$fila]["otrpag"] - $datos[$fila]["otrxpag"];
        $otrpag = ($otrpag > $datos[$fila]["otrpagf"]) ? $datos[$fila]["otrpagf"] : $otrpag;
        $otrpag = ($otrpag > 0) ? $otrpag : 0;
        $sumotrpag += $otrpag;
        //+++++++++++++ VERSION 2.0 DE LO RECUPERADO +++++++++++++++++++++++++++++++++++

        if ($ncapita == 0 && $nintere == 0 && $notros == 0 && $cappag == 0 && $intpag == 0 && $otrpag == 0) {
            $fila++;
            continue;
        }

        $porkp = ($ncapita > 0) ? number_format(($cappag * 100 / $ncapita), 2) : (($cappag > 0) ? "Excedido al 100%" : number_format($cappag, 2));
        $porint = ($nintere > 0) ? number_format(($intpag * 100 / $nintere), 2) : (($intpag > 0) ? "Excedido al 100%" : number_format($intpag, 2));
        $porotr = ($notros > 0) ? number_format(($otrpag * 100 / $notros), 2) : (($otrpag > 0) ? "Excedido al 100%" : number_format($otrpag, 2));

        $totrecuperado = $cappag + $intpag + $otrpag;
        $totproyectado = $ncapita + $nintere + $notros;

        $hojaReporte->setCellValueByColumnAndRow(1, $linea, $cuenta);
        $hojaReporte->setCellValueByColumnAndRow(2, $linea, $nombre);
        $hojaReporte->setCellValueByColumnAndRow(3, $linea, $agencia);
        $hojaReporte->setCellValueByColumnAndRow(4, $linea, $asesor);
        $hojaReporte->setCellValueByColumnAndRow(5, $linea, round($ncapita, 2));
        $hojaReporte->setCellValueByColumnAndRow(6, $linea, round($cappag, 2));
        $hojaReporte->setCellValueByColumnAndRow(7, $linea, ($porkp));
        $hojaReporte->setCellValueByColumnAndRow(8, $linea, round($nintere, 2));
        $hojaReporte->setCellValueByColumnAndRow(9, $linea, round($intpag, 2));
        $hojaReporte->setCellValueByColumnAndRow(10, $linea, ($porint));
        $hojaReporte->setCellValueByColumnAndRow(11, $linea, round($notros, 2));
        $hojaReporte->setCellValueByColumnAndRow(12, $linea, round($otrpag, 2));
        $hojaReporte->setCellValueByColumnAndRow(13, $linea, ($porotr));
        $hojaReporte->setCellValueByColumnAndRow(14, $linea, round($totproyectado, 2));
        $hojaReporte->setCellValueByColumnAndRow(15, $linea, round($totrecuperado, 2));
        $hojaReporte->setCellValueByColumnAndRow(16, $linea, $telefono);
        $fila++;
        $linea++;
    }
    $columnas = range('A', 'S');
    foreach ($columnas as $columna) {
        $hojaReporte->getColumnDimension($columna)->setAutoSize(TRUE);
    }


    //SECCION PARA DESCARGA EL ARCHIVO
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spread, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reembolsos de montos debidos",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
}

function printpdf($datos, $otros, $info)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];

    class PDF extends FPDF
    {
        //atributos de la clase
        public $oficina;
        public $institucion;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $pathlogo;
        public $pathlogoins;
        public $titulo;
        public $user;

        public function __construct($oficina, $institucion, $direccion, $email, $telefono, $nit, $pathlogo, $pathlogoins, $titulo, $user)
        {
            parent::__construct();
            $this->oficina = $oficina;
            $this->institucion = $institucion;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->titulo = $titulo;
            $this->user = $user;
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $tamanio_linea = 4; //altura de la linea/celda
            $ancho_linea = 20; //anchura de la linea/celda
            $ancho_linea2 = 20; //anchura de la linea/celda

            // ACA ES DONDE EMPIEZA LO DEL FORMATO DE REPORTE---------------------------------------------------
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont('Courier', '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(1);
            $this->Cell(0, 2, $this->user, 0, 1, 'R');

            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 13, 33);

            //tipo de letra para el encabezado
            $this->SetFont('Courier', '', 8);
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
            $this->Cell(0, 5, 'REPORTE', 0, 1, 'C', true);
            $this->Cell(0, 5, $this->titulo, 1, 1, 'C', true);

            $this->Ln(5);
            //Fuente
            $this->SetFont($fuente, '', 8);
            //encabezado de tabla
            $this->CellFit($ancho_linea * 3, $tamanio_linea + 1, " ", 0, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea * 3, $tamanio_linea + 1, "CAPITAL", 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea * 3, $tamanio_linea + 1, "INTERES", 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea * 3, $tamanio_linea + 1, "OTROS", 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea * 2, $tamanio_linea + 1, "TOTALES", 1, 0, 'C', 0, '', 1, 0);

            $this->Ln(5);

            $this->CellFit($ancho_linea, $tamanio_linea + 1, "CUENTA", 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea * 2, $tamanio_linea + 1, 'NOMBRE CLIENTE', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'PACTADO', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'RECUPERADO', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, '%', 1, 0, 'C', 0, '', 1, 0);

            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'PACTADO', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'RECUPERADO', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, '%', 1, 0, 'C', 0, '', 1, 0);

            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'PACTADO', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'RECUPERADO', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, '%', 1, 0, 'C', 0, '', 1, 0);

            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'PACTADO', 1, 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'RECUPERADO', 1, 0, 'C', 0, '', 1, 0);
            $this->Ln(5);
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

    $fuente = "Courier";
    $tamanio_linea = 4;
    $ancho_linea = 20;

    // Creación del objeto de la clase heredada
    $pdf = new PDF($oficina, $institucion, $direccionins, $emailins, $telefonosins, $nitins, $rutalogomicro, $rutalogoins, $otros[0], $otros[1]);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont($fuente, '', 8);
    $auxtipoenti = null;
    $auxagencia = null;
    $auxanalista = null;
    $sumcappag = 0;
    $sumintpag = 0;
    $sumotrpag = 0;
    $sumcapita = 0;
    $sumintere = 0;
    $sumotros = 0;

    $fila = 0;
    while ($fila < count($datos)) {
        $cuenta = $datos[$fila]["ccodcta"];
        $nombre = strtoupper(decode_utf8($datos[$fila]["short_name"]));
        $agencia = strtoupper(decode_utf8($datos[$fila]["nom_agencia"]));
        $asesor = strtoupper(decode_utf8($datos[$fila]["asesor"]));
        $tipoenti = strtoupper(($datos[$fila]["tipenti"]));
        $ncapita = $datos[$fila]["ncapita"];
        $nintere = $datos[$fila]["nintere"];
        $notros = $datos[$fila]["otros"];

        //+++++++++++++ VERSION 3.0 eliminar si sale mal XD ++++++++++++++++++++++++++++
        //KP
        $ncapita = ($datos[$fila]["cappag"] - $datos[$fila]["cappagf"]) - $datos[$fila]["capxpag"];
        if ($ncapita < 0) {
            //SI NO SE COMPLETO EL PAGO DE CAPITAL ANTES DE LA FECHA INICIO
            //OP1: SE LE SUMARA A LA CUOTA DE CAPITAL DEL MES EN CONSULTA
            // $ncapita = $ncapita + $datos[$fila]["ncapita"];
            //OP2: NO SE LE SUMARA A LA CUOTA DE CAPITAL DEL MES EN CONSULTA, SOLO SE TOMARA LA CUOTA DEL MES EN CONSULTA
            $ncapita = $datos[$fila]["ncapita"];
        } else {
            //SI SE COMPLETO EL PAGO DE CAPITAL ANTES DE LA FECHA INICIO, SE ABONA A LA CUOTA DE CAPITAL DEL MES EN CONSULTA
            $ncapita = $datos[$fila]["ncapita"] - $ncapita;
        }
        $ncapita = max($ncapita, 0);

        //INTERES
        $nintere = ($datos[$fila]["intpag"] - $datos[$fila]["intpagf"]) - $datos[$fila]["intxpag"];
        if ($nintere < 0) {
            //SI NO SE COMPLETO EL PAGO DE INTERES ANTES DE LA FECHA INICIO
            //OP1: SE LE SUMARA A LA CUOTA DE INTERES DEL MES EN CONSULTA
            // $nintere = $nintere + $datos[$fila]["nintere"];
            //OP2: NO SE LE SUMARA A LA CUOTA DE INTERES DEL MES EN CONSULTA, SOLO SE TOMARA LA CUOTA DEL MES EN CONSULTA
            $nintere = $datos[$fila]["nintere"];
        } else {
            //SI SE COMPLETO EL PAGO DE INTERES ANTES DE LA FECHA INICIO, SE ABONA A LA CUOTA DE INTERES DEL MES EN CONSULTA
            $nintere = $datos[$fila]["nintere"] - $nintere;
        }
        $nintere = max($nintere, 0);

        //OTROS
        $notros = ($datos[$fila]["otrpag"] - $datos[$fila]["otrpagf"]) - $datos[$fila]["otrxpag"];
        if ($notros < 0) {
            //SI NO SE COMPLETO EL PAGO DE OTROS ANTES DE LA FECHA INICIO
            //OP1: SE LE SUMARA A LA CUOTA DE OTROS DEL MES EN CONSULTA
            // $notros = $notros + $datos[$fila]["otros"];
            //OP2: NO SE LE SUMARA A LA CUOTA DE OTROS DEL MES EN CONSULTA, SOLO SE TOMARA LA CUOTA DEL MES EN CONSULTA
            $notros = $datos[$fila]["otros"];
        } else {
            //SI SE COMPLETO EL PAGO DE OTROS ANTES DE LA FECHA INICIO, SE ABONA A LA CUOTA DE OTROS DEL MES EN CONSULTA
            $notros = $datos[$fila]["otros"] - $notros;
        }
        $notros = max($notros, 0);
        //+++++++++++++ FIN VERSION 3.0 ++++++++++++++++++++++++++++++++++++++++++++++++


        $sumcapita += $ncapita;
        $sumintere += $nintere;
        $sumotros += $notros;


        //+++++++++++++ VERSION 2.0 DE LO RECUPERADO ++++++++++++++++++++++++++++++++++++
        $cappag = $datos[$fila]["cappag"] - $datos[$fila]["capxpag"];
        $cappag = ($cappag > $datos[$fila]["cappagf"]) ? $datos[$fila]["cappagf"] : $cappag;
        $cappag = ($cappag > 0) ? $cappag : 0;
        $sumcappag += $cappag;

        $intpag = $datos[$fila]["intpag"] - $datos[$fila]["intxpag"];
        $intpag = ($intpag > $datos[$fila]["intpagf"]) ? $datos[$fila]["intpagf"] : $intpag;
        $intpag = ($intpag > 0) ? $intpag : 0;
        $sumintpag += $intpag;

        $otrpag = $datos[$fila]["otrpag"] - $datos[$fila]["otrxpag"];
        $otrpag = ($otrpag > $datos[$fila]["otrpagf"]) ? $datos[$fila]["otrpagf"] : $otrpag;
        $otrpag = ($otrpag > 0) ? $otrpag : 0;
        $sumotrpag += $otrpag;
        //+++++++++++++ VERSION 2.0 DE LO RECUPERADO +++++++++++++++++++++++++++++++++++

        if ($ncapita == 0 && $nintere == 0 && $notros == 0 && $cappag == 0 && $intpag == 0 && $otrpag == 0) {
            $fila++;
            continue;
        }

        $porkp = ($ncapita > 0) ? number_format(($cappag * 100 / $ncapita), 2) : (($cappag > 0) ? "Excedido al 100%" : number_format($cappag, 2));
        $porint = ($nintere > 0) ? number_format(($intpag * 100 / $nintere), 2) : (($intpag > 0) ? "Excedido al 100%" : number_format($intpag, 2));
        $porotr = ($notros > 0) ? number_format(($otrpag * 100 / $notros), 2) : (($otrpag > 0) ? "Excedido al 100%" : number_format($otrpag, 2));

        $totrecuperado = $cappag + $intpag + $otrpag;
        $totproyectado = $ncapita + $nintere + $notros;


        if ($auxagencia != $agencia) {
            $pdf->SetFont($fuente, 'B', 9);
            $pdf->Cell(0, 5, 'OFICINA: ' . strtoupper($agencia), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxagencia = $agencia;
            $auxtipoenti = null;
            $auxanalista = null;
        }
        if ($auxanalista != $asesor) {
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->Cell($ancho_linea * 2, 5, '   EJECUTIVO: ' . strtoupper($asesor), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxanalista = $asesor;
            $auxtipoenti = null;
        }
        if ($auxtipoenti != $tipoenti) {
            $pdf->SetFont($fuente, 'B', 7);
            $pdf->Cell($ancho_linea * 2, 5, ($tipoenti == 'GRUP') ? '       GRUPALES' : '       INDIVIDUALES', '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxtipoenti = $tipoenti;
        }

        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $cuenta, 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea * 2, $tamanio_linea + 1, $nombre, 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($ncapita, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($cappag, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $porkp, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($nintere, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($intpag, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $porint, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($notros, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($otrpag, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $porotr, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($totproyectado, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($totrecuperado, 2), 0, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(5);
        $fila++;
    }
    $pdf->CellFit($ancho_linea * 14, $tamanio_linea + 1, ' ', 'T', 0, 'C', 0, '', 1, 0);

    // $sumcapita = array_sum(array_column($datos, 'ncapita'));
    // $sumcappag = array_sum(array_column($datos, 'cappag'));
    // $sumintere = array_sum(array_column($datos, 'nintere'));
    // $sumintpag = array_sum(array_column($datos, 'intpag'));
    // $sumotros = array_sum(array_column($datos, 'otros'));
    // $sumotrpag = array_sum(array_column($datos, 'otrpag'));

    $sumporkp = ($sumcapita > 0) ? ($sumcappag * 100 / $sumcapita) : (($sumcappag > 0) ? 100 : $sumcappag);
    $sumporint = ($sumintere > 0) ? ($sumintpag * 100 / $sumintere) : (($sumintpag > 0) ? 100 : $sumintpag);
    $sumporotr = ($sumotros > 0) ? ($sumotrpag * 100 / $sumotros) : (($sumotrpag > 0) ? 100 : $sumotrpag);

    $totrecuperado = $sumcappag + $sumintpag + $sumotrpag;
    $totproyectado = $sumcapita + $sumintere + $sumotros;

    $pdf->SetFont($fuente, 'B', 8);
    $pdf->Ln(1);

    $pdf->CellFit($ancho_linea * 3, $tamanio_linea + 1, 'TOTALES', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumcapita, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumcappag, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumporkp, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumintere, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumintpag, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumporint, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumotros, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumotrpag, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumporotr, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($totproyectado, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($totrecuperado, 2), 0, 0, 'R', 0, '', 1, 0);
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "proyectado vs recuperado",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
