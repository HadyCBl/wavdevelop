<?php
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
$hoy = date("Y-m-d");

use App\Generic\Models\TipoDocumentoTransaccion;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];

$tipo = $_POST["tipo"];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++ VALIDACIONES PAPA' [`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`]  ++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}
if ($inputs[0] > $inputs[1]) {
    echo json_encode(['mensaje' => 'Rango de fechas Inválido', 'status' => 0]);
    return;
}
if ($radios[0] == "anyf" && $selects[1] == '0') {
    echo json_encode(['mensaje' => 'Seleccione un fondo válido', 'status' => 0]);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$fecinicio = $inputs[0];
$fecfin = $inputs[1];
$where = ($radios[1] == "allofi") ? "" : " AND usu.id_agencia=" . $selects[0];
$where .= ($radios[0] == "anyf") ? " AND prod.id_fondo=" . $selects[1] : "";

$titlereport = " DEL " . date("d-m-Y", strtotime($fecinicio)) . " AL " . date("d-m-Y", strtotime($fecfin));

// negroy agregego kar.FormPago, IFNULL((SELECT tg2.nom_agencia from tb_agencia tg2 where tg2.cod_agenc=crem.CODAgencia),' ') agencia_creo
$query = "SELECT kar.CODKAR,kar.CCODCTA,cli.short_name,cli.no_identifica dpi,cli.no_tributaria nit,kar.DFECPRO,kar.NMONTO,kar.CNUMING,kar.KP,kar.INTERES,kar.MORA,kar.AHOPRG,kar.OTR,
    IFNULL((SELECT NombreGrupo from tb_grupo where id_grupos=crem.CCodGrupo),' ') NombreGrupo,ofi.nom_agencia,CONCAT(usu.nombre,' ',usu.apellido) usuario ,CONCAT(usu2.nombre,' ',usu2.apellido) analista, ofi2.nom_agencia agenciaorigen,
    FormPago,DFECBANCO,
    IFNULL((SELECT nombre FROM tb_bancos where id=CBANCO),'-') CBANCO,
    IFNULL((SELECT numcuenta FROM ctb_bancos where id=CCODBANCO),'-') CCODBANCO,boletabanco 
    FROM CREDKAR kar 
    INNER JOIN cremcre_meta crem ON crem.CCODCTA=kar.CCODCTA 
    INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli 
    INNER JOIN tb_usuario usu on usu.id_usu=kar.CCODUSU
    INNER JOIN tb_usuario usu2 on usu2.id_usu=crem.CodAnal
    INNER JOIN tb_agencia ofi on ofi.id_agencia=usu.id_agencia
    INNER JOIN tb_agencia ofi2 on ofi2.id_agencia=crem.CODAgencia
    INNER JOIN cre_productos prod on prod.id=crem.CCODPRD
    WHERE kar.CESTADO='X' AND kar.CTIPPAG='P' " . $where . " AND (kar.DFECPRO BETWEEN ? AND ?) ORDER BY kar.DFECPRO,kar.CNUMING";
//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$fecinicio, $fecfin]);
    $result = convert_to_utf8($result);
    // file_put_contents('debug.log', print_r($result, true), FILE_APPEND);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros con los filtros aplicados");
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

switch ($tipo) {
    case 'xlsx';
        printxls($result);
        break;
    case 'pdf':
        printpdf($result, [$titlereport], $info);
        break;
    case 'show':
        showresults($result);
        break;
}
function showresults($registro)
{
    $incluir = ['clave1', 'clave2', 'clave3'];
    $valores[] = [];
    $i = 0;
    foreach ($registro as $fila) {
        unset($fila['CODKAR']);
        unset($fila['dpi']);
        unset($fila['nit']);
        $formapago = $fila["FormPago"];
        $fila["DFECBANCO"] = ($formapago == '2') ? $fila["DFECBANCO"] : ' ';
        $fila["CBANCO"] = ($formapago == '2') ? $fila["CBANCO"] : ' ';
        $fila["CCODBANCO"] = ($formapago == '2') ? $fila["CCODBANCO"] : ' ';
        // $fila["FormPago"] = ($formapago == '2') ? 'BOLETA DE BANCO' : (($formapago == '4') ? 'CREF' : 'EFECTIVO');
        $fila["FormPago"] = TipoDocumentoTransaccion::getDescripcion($formapago, 3);

        $valores[$i] = $fila;
        $i++;
    }

    $keys = ["DFECPRO", "CCODCTA", "short_name", "NMONTO", "CNUMING", "nom_agencia", "usuario", "FormPago", "DFECBANCO", "CBANCO", "boletabanco"];
    $encabezados = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "TOTAL INGRESO", "NUMDOC", "AGENCIA", "USUARIO", "FORMA PAGO", "FECHA BOLETA", "BANCO", "NO BOLETA"];

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'data' => $valores,
        'keys' => $keys,
        'encabezados' => $encabezados,
    );
    echo json_encode($opResult);
    return;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info)
{

    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];

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
            $this->DefOrientation = 'L';
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
            $this->Cell(0, 5, 'INGRESOS ANULADOS' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 25;
            $Wline = [25, 15, 10]; // se agrego negroy  width LINE
            $this->Cell($ancho_linea, 5, 'FECHA', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'CUENTA', 'B', 0, 'L'); //
            $this->Cell($ancho_linea * 2, 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'TOTAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'CAPITAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'INTERES', 'B', 0, 'R'); //
            $this->Cell($Wline[1], 5, 'MORA', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'OTROS', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'DOC.', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'AGENCIA', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'ORIGEN', 'B', 0, 'R');
            $this->Cell($Wline[0], 5, 'FORMA PAGO', 'B', 1, 'R');
            $this->Ln(3);
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
    $ancho_linea2 = 25;
    $Wline = [25, 15, 10]; // se agrego negroy  width LINE
    $pdf->SetFont($fuente, '', 8);
    $fila = 0;
    $ttotal = 0;
    $tcapital = 0;
    $tinteres = 0;
    $tmora = 0;
    $totros = 0;
    while ($fila < count($registro)) {
        $id = $registro[$fila]["CODKAR"];
        $cuenta = $registro[$fila]["CCODCTA"];
        $nombre = ($registro[$fila]["short_name"]);
        $fecha = date("d-m-Y", strtotime($registro[$fila]["DFECPRO"]));
        $numdoc = $registro[$fila]["CNUMING"];
        $total = $registro[$fila]["NMONTO"];
        $capital = $registro[$fila]["KP"];
        $interes = $registro[$fila]["INTERES"];
        $mora = $registro[$fila]["MORA"];
        $ahorro = $registro[$fila]["AHOPRG"];
        $otro = $registro[$fila]["OTR"];
        $agencia = $registro[$fila]["nom_agencia"];
        //AGREGADO DEL NEGROY 
        $origen = $registro[$fila]["agenciaorigen"];

        $formapago = $registro[$fila]["FormPago"];
        $fechabanco = ($formapago == '2') ? $registro[$fila]["DFECBANCO"] : ' ';
        $nombrebanco = ($formapago == '2') ? $registro[$fila]["CBANCO"] : ' ';
        $cuentabanco = ($formapago == '2') ? $registro[$fila]["CCODBANCO"] : ' ';
        // $formapago = ($formapago == '2') ? 'BOLETA DE BANCO' : (($formapago == '4') ? 'CREF' : 'EFECTIVO');
        $formapago = TipoDocumentoTransaccion::getDescripcion($formapago, 3);


        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fecha, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($total, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($capital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($interes, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format($mora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format(($ahorro + $otro), 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, $numdoc, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, $agencia, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, $origen, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[0], $tamanio_linea + 1, karely($formapago), '', 1, 'R', 0, '', 1, 0);
        $ttotal = $ttotal + $total;
        $tcapital = $tcapital + $capital;
        $tinteres = $tinteres + $interes;
        $tmora = $tmora + $mora;
        $totros = $totros + $ahorro + $otro;
        $fila++;
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->CellFit($ancho_linea2 * 4, $tamanio_linea + 1, 'TOTAL INGRESOS: ' . $fila, '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($ttotal, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($tcapital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($tinteres, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format($tmora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format($totros, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Ingresos Anulados",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro)
{
    require '../../../../vendor/autoload.php';
    $fuente = "Courier";
    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("IngresosAnulados");
    # Escribir encabezado de la tabla
    $encabezado_tabla = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "TOTAL INGRESO", "CAPITAL", "INTERES", "MORA", "OTROS", "NUMDOC", "DPI CLIENTE", "NIT CLIENTE", "ANALISTA", "GRUPO", "AGENCIA", "USUARIO", "FORMA PAGO", "FECHA BOLETA", "BANCO", "CUENTA BANCARIA"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle('A1:S1')->getFont()->setName($fuente)->setBold(true);

    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $fila = 0;
    $i = 2;
    while ($fila < count($registro)) {
        $id = $registro[$fila]["CODKAR"];
        $cuenta = $registro[$fila]["CCODCTA"];
        $nombre = (encode_utf8($registro[$fila]["short_name"]));
        $fecha = date("d-m-Y", strtotime($registro[$fila]["DFECPRO"]));
        $numdoc = $registro[$fila]["CNUMING"];
        $total = $registro[$fila]["NMONTO"];
        $capital = $registro[$fila]["KP"];
        $interes = $registro[$fila]["INTERES"];
        $mora = $registro[$fila]["MORA"];
        $ahorro = $registro[$fila]["AHOPRG"];
        $otro = $registro[$fila]["OTR"];
        $nomgrupo = $registro[$fila]["NombreGrupo"];
        $agencia = $registro[$fila]["nom_agencia"];
        $origen = $registro[$fila]["agenciaorigen"];
        $formPay = $registro[$fila]["FormPago"];
        $dpi = $registro[$fila]["dpi"];
        $nit = $registro[$fila]["nit"];
        $asesor = $registro[$fila]["analista"];
        $usuario = $registro[$fila]["usuario"];

        $formapago = $registro[$fila]["FormPago"];
        $fechabanco = ($formapago == '2') ? $registro[$fila]["DFECBANCO"] : ' ';
        $nombrebanco = ($formapago == '2') ? $registro[$fila]["CBANCO"] : ' ';
        $cuentabanco = ($formapago == '2') ? $registro[$fila]["CCODBANCO"] : ' ';
        // $formapago = ($formapago == '2') ? 'BOLETA DE BANCO' : (($formapago == '4') ? 'CREF' : 'EFECTIVO');
        $formapago = TipoDocumentoTransaccion::getDescripcion($formapago, 3);

        $activa->setCellValue('A' . $i, $fecha);
        $activa->setCellValueExplicit('B' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('C' . $i, strtoupper($nombre));
        $activa->setCellValueExplicit('D' . $i, $total, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('E' . $i, $capital, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('F' . $i, $interes, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('G' . $i, $mora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('H' . $i, $otro, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('I' . $i, $numdoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('J' . $i, $dpi, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('K' . $i, $nit, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('L' . $i, $asesor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('M' . $i, $nomgrupo);
        $activa->setCellValue('N' . $i, $agencia);
        $activa->setCellValue('O' . $i, $usuario);
        $activa->setCellValue('P' . $i, $formapago);
        $activa->setCellValue('Q' . $i, $fechabanco);
        $activa->setCellValue('R' . $i, $nombrebanco);
        $activa->setCellValueExplicit('S' . $i, $cuentabanco, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $fila++;
        $i++;
    }

    $columnas = range('A', 'S'); // Genera un array con las letras de A a O
    foreach ($columnas as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(TRUE);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Ingresos Anulados",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
