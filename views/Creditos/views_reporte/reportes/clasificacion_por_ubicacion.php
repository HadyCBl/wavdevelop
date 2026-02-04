<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include '../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use Complex\Functions;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Micro\Generic\Utf8;
//[[`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[ $idusuario; ]]
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

if (!validateDate($inputs[0], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}

if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar fuente de fondos']);
    return;
}
if (isset($radios[3]) && $radios[3] == "anyasesor" && isset($selects[2]) && $selects[2] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Asesor']);
    return;
}

$regionRadio = $radios[3] ?? null;
$regionId = isset($selects[2]) ? (int)$selects[2] : 0;
if ($regionRadio === "anyregion" && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    return;
}

$mostrarRegionCol = false;
$regionNombre = '';
$agenciaIdSeleccionada = ($radios[0] ?? null) === 'anyofi' ? (int)($selects[0] ?? 0) : 0;


//*****************ARMANDO LA CONSULTA**************
$condi = "";
//RANGO DE FECHAS
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));
//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = (isset($radios[3]) && $radios[3] == "anyasesor" && isset($selects[2])) ? " AND cremi.CodAnal =" . $selects[2] : "";

//STATUS - EXACTO como cartera_fondos.php
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//REGION (opcional)
$filregion = ($regionRadio === "anyregion" && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $regionId . ")"
    : "";

//-----------------------------
$strquery = "SELECT 
IFNULL(muni.nombre, 'SIN ESPECIFICAR') AS nombre,
COUNT(*) AS cantidad,
CAST(SUM(CASE WHEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 THEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) ELSE 0 END) AS DECIMAL(15,2)) AS suma_NCapDes,
CAST(SUM(IFNULL(kar.sum_MORA, 0)) AS DECIMAL(15,2)) AS suma_mora,
SUM(CASE WHEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) > 0 THEN 1 ELSE 0 END) AS cantidad_mora
FROM cremcre_meta cremi 
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
LEFT JOIN tb_municipios muni ON muni.id = cli.id_muni_reside
LEFT JOIN (
    SELECT ccodcta, 
    SUM(KP) AS sum_KP, 
    MAX(dfecpro) AS dfecpro_ult, 
    SUM(interes) AS sum_interes, 
    SUM(MORA) AS sum_MORA, 
    SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
WHERE 
(cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? " . $filfondo . $filagencia . $filasesor . $filregion . $status . " 
GROUP BY IFNULL(muni.id, 0), IFNULL(muni.nombre, 'SIN ESPECIFICAR')
ORDER BY IFNULL(muni.nombre, 'SIN ESPECIFICAR')";
//--------------------------------

$showmensaje = false;
try {
    $database->openConnection();

    // Determinar región (solo si el reporte queda acotado a una sola región)
    if ($regionRadio === 'anyregion' && $regionId > 0) {
        $reg = $database->getAllResults('SELECT nombre FROM cre_regiones WHERE id = ? LIMIT 1', [$regionId]);
        if (!empty($reg) && isset($reg[0]['nombre'])) {
            $regionNombre = (string)$reg[0]['nombre'];
            $mostrarRegionCol = true;
        }
    } elseif ($agenciaIdSeleccionada > 0) {
        $reg = $database->getAllResults(
            'SELECT r.nombre FROM cre_regiones_agencias ra INNER JOIN cre_regiones r ON r.id = ra.id_region WHERE ra.id_agencia = ? ORDER BY r.estado DESC, r.nombre LIMIT 1',
            [$agenciaIdSeleccionada]
        );
        if (!empty($reg) && isset($reg[0]['nombre'])) {
            $regionNombre = (string)$reg[0]['nombre'];
            $mostrarRegionCol = true;
        }
    }
    $result = $database->getAllResults($strquery, [$filtrofecha, $filtrofecha, $filtrofecha]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    
    // Calcular totales globales
    $no_creditos_global = array_sum(array_column($result, 'cantidad'));
    $mora_global = array_sum(array_column($result, 'suma_mora'));
    $total_kap = array_sum(array_column($result, 'suma_NCapDes'));
    $total_mora = 0;
    $total_Cmora = array_sum(array_column($result, 'cantidad_mora'));
    
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

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

switch ($tipo) {
    case 'xlsx';
        printxls($result, $titlereport, $archivo[0], $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
        printpdf($result, [$titlereport], $info,  $no_creditos_global,$mora_global,$total_kap,$total_mora,$total_Cmora, $mostrarRegionCol, $regionNombre );
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info, $no_creditos_global,$mora_global,$total_kap,$total_mora,$total_Cmora, $mostrarRegionCol = false, $regionNombre = '' )
{
    /*     $oficina = "Coban";
    $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    $direccionins = "Canton vipila zona 1";
    $emailins = "fape@gmail.com";
    $telefonosins = "502 43987876";
    $nitins = "1323244234";
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../../includes/img/fape.jpeg"; */


    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
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
        public $mostrarRegionCol;
        public $regionNombre;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $no_creditos_global, $mostrarRegionCol, $regionNombre)
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
            $this->mostrarRegionCol = (bool)$mostrarRegionCol;
            $this->regionNombre = (string)$regionNombre;
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
            $this->Cell(0, 5, 'CLASIFICACION POR MUNICIPIOS ' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;
            $this->Cell($ancho_linea * 6 + 15, 5, ' ', '', 0, 'L');
            $this->Cell($ancho_linea * 3 - 2, 5, ' ',0, 1, 'C');

            $this->Cell($ancho_linea*2, 5, $this->mostrarRegionCol ? 'REGION' : ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2 , 5, 'MUNICIPIO', 'B', 0, 'L');
            $this->Cell($ancho_linea+10, 5, 'NO. DE CREDITOS', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'SALDO CAPITAL', 'B', 0, 'C'); //
            $this->Cell($ancho_linea, 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea*2-10, 5, 'CREDITOS MOROSOS', 'B', 0, 'R');
            $this->Cell($ancho_linea*2 -5 , 5, 'SALDO CAPITAL EN MORA', 'B', 0, 'R');
            $this->Cell($ancho_linea , 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea+10, 5, ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea / 2, 5, ' ', 0, 1, 'R'); //
            $this->Ln(1);
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos,  $no_creditos_global, $mostrarRegionCol, $regionNombre);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 20;
    $pdf->SetFont($fuente, '', 7);
    $aux = 0;
    $auxgrupo = 0;
    $fila = 0;
    // totales
    $sum_mora  = 0;
    $sum_cant = 0;

    while ($fila < count($registro)) {


        $nombre = Utf8::decode($registro[$fila]["nombre"]);  
        $cantidad = $registro[$fila]["cantidad"];  
        $cantidad_Ncapdes = $registro[$fila]["suma_NCapDes"];  
        $cantidad_Mora = $registro[$fila]["cantidad_mora"];  
        $cantidad_Mora_kap = $registro[$fila]["suma_mora"];  

        //TITULO GRUPO
        $pdf->CellFit($ancho_linea2*2, $tamanio_linea + 1, ($mostrarRegionCol ? Utf8::decode($regionNombre) : ' '), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 , $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad_Ncapdes, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_kap=($cantidad_Ncapdes/$total_kap)*100;
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($porcentaje_kap, 2, '.', ',') . ' %', 'R', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad_Mora, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+15, $tamanio_linea + 1, number_format($cantidad_Mora_kap, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_mora = ($cantidad_Mora_kap/$mora_global)*100;
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($porcentaje_mora, 2, '.', ',') . ' %', 0, 0, 'C', 0, '', 1, 0);

        $sum_cant += $cantidad_Ncapdes;
        $sum_mora += $cantidad_Mora;

        $pdf->Ln(4);  
        $fila++;

    }
    $pdf->Ln(2);
    $pdf->Cell($ancho_linea2*2, $tamanio_linea, ' ', 'T', 0, 'L');
    $pdf->Cell($ancho_linea2 *2, $tamanio_linea , 'Numero de frecuencias : ' . $fila, 'T', 0, 'L', 0, '', 1, 0);
    $pdf->Cell($ancho_linea2+5, $tamanio_linea, $no_creditos_global, 'T', 0, 'R');
    $pdf->CellFit($ancho_linea2+8, $tamanio_linea , number_format($sum_cant, 2, '.', ',') , 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Cell($ancho_linea2-5, $tamanio_linea, ' ', 'T', 0, 'R');
    $pdf->Cell($ancho_linea2+12, $tamanio_linea, $sum_mora, 'T', 0, 'R');

    $pdf->Cell($ancho_linea2+15, $tamanio_linea, $mora_global, 'T', 0, 'R');
    $pdf->Cell($ancho_linea2*2+10, $tamanio_linea, ' ', 'T', 0, 'R');


    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Clasificacion por municipios",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
//FUNCIONES PARA DATOS DE RESUMEN
function resumen($clasdias, $column, $con1, $con2)
{
    $keys = array_keys(array_filter($clasdias[$column], function ($var) use ($con1, $con2) {
        return ($var >= $con1 && $var <= $con2);
    }));
    $fila = 0;
    $sum1 = 0;
    $sum2 = 0;
    while ($fila < count($keys)) {
        $f = $keys[$fila];
        $sum1 += ($clasdias["salcapital"][$f]);
        $sum2 += ($clasdias["capmora"][$f]);
        $fila++;
    }
    return [$sum1, $sum2, $fila];
}

//funcion para generar archivo excel
function printxls($registro, $titlereport, $usuario, $mostrarRegionCol = false, $regionNombre = '')
{
    require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    // $tamanioEncabezado = 14;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("CarteraGeneral");
    $activa->getColumnDimension("A")->setWidth(20);
    $activa->getColumnDimension("B")->setWidth(20);
    $activa->getColumnDimension("C")->setWidth(5);
    $activa->getColumnDimension("D")->setWidth(15);
    $activa->getColumnDimension("E")->setWidth(25);
    $activa->getColumnDimension("F")->setWidth(15);
    $activa->getColumnDimension("G")->setWidth(15);
    $activa->getColumnDimension("H")->setWidth(15);


    //insertarmos la fecha y usuario
    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $activa->getStyle("A1:X1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle("A2:X2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $activa->getStyle("A1:X1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A2:X2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer pequeño las letras del encabezado de titulo
    $activa->getStyle("A4:X4")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $activa->getStyle("A5:X5")->getFont()->setSize($tamanioTabla)->setName($fuente);
    //centrar los encabezado de la tabla
    $activa->getStyle("A4:X4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A5:X5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $activa->setCellValue("A4", "REPORTE");
    $activa->setCellValue("A5", strtoupper("CLASIFICACION POR MUNICIPIOS " . $titlereport));

    //TITULO DE RECARGOS


    # Escribir encabezado de la tabla
    $encabezado_tabla = ["MUNICIPIO", "NO. DE CREDITOS", "SALDO CAPITAL", "PORCENTAJE", "CREDITOS MOROSOS", "SALDO CAPITAL EN MORA", "PORCENTAJE MORA"];
    if ($mostrarRegionCol) {
        $encabezado_tabla[] = 'REGION';
        $activa->getColumnDimension("H")->setWidth(20);
    }
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle($mostrarRegionCol ? 'A8:H8' : 'A8:G8')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $activa->mergeCells($mostrarRegionCol ? 'A1:H1' : 'A1:G1');
    $activa->mergeCells($mostrarRegionCol ? 'A2:H2' : 'A2:G2');
    $activa->mergeCells($mostrarRegionCol ? 'A4:H4' : 'A4:G4');
    $activa->mergeCells($mostrarRegionCol ? 'A5:H5' : 'A5:G5');

    $fila = 0;
    $i = 9;
    
    // Calcular totales una sola vez para toda la función
    $total_creditos = array_sum(array_column($registro, 'cantidad'));
    $total_saldo_capital = array_sum(array_column($registro, 'suma_NCapDes'));
    $total_mora_global = array_sum(array_column($registro, 'suma_mora'));
    
    while ($fila < count($registro)) {
        $nombre = $registro[$fila]["nombre"];
        $cantidad = $registro[$fila]["cantidad"];
        $suma_NCapDes = $registro[$fila]["suma_NCapDes"];
        $cantidad_mora = $registro[$fila]["cantidad_mora"];
        $suma_mora = $registro[$fila]["suma_mora"];
        
        // Calcular porcentajes correctos basados en saldo de capital
        $porcentaje_kap = ($total_saldo_capital != 0) ? ($suma_NCapDes / $total_saldo_capital) * 100 : 0;
        $porcentaje_mora = ($total_mora_global != 0) ? ($suma_mora / $total_mora_global) * 100 : 0;

        $activa->setCellValue('A' . $i, strtoupper($nombre));
        $activa->setCellValue('B' . $i, $cantidad);
        
        // Establecer formato numérico exacto para saldo capital (sin redondeo visual)
        $activa->setCellValue('C' . $i, $suma_NCapDes);
        $activa->getStyle('C' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $activa->setCellValue('D' . $i, $porcentaje_kap / 100); // Excel manejará el formato de porcentaje
        $activa->getStyle('D' . $i)->getNumberFormat()->setFormatCode('0.00%');
        
        $activa->setCellValue('E' . $i, $cantidad_mora);
        
        // Establecer formato numérico exacto para saldo mora
        $activa->setCellValue('F' . $i, $suma_mora);
        $activa->getStyle('F' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $activa->setCellValue('G' . $i, $porcentaje_mora / 100); // Excel manejará el formato de porcentaje
        $activa->getStyle('G' . $i)->getNumberFormat()->setFormatCode('0.00%');

        if ($mostrarRegionCol) {
            $activa->setCellValue('H' . $i, $regionNombre);
        }

        $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'H' : 'G') . $i)->getFont()->setName($fuente);

        $fila++;
        $i++;
    }
    
    //total de registros
    $sum_cantidad = array_sum(array_column($registro, "cantidad"));
    $sum_NCapDes = array_sum(array_column($registro, "suma_NCapDes"));
    $sum_cantidad_mora = array_sum(array_column($registro, "cantidad_mora"));
    $sum_suma_mora = array_sum(array_column($registro, "suma_mora"));

    $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'H' : 'G') . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValue('A' . $i, "TOTALES:");
    $activa->setCellValue('B' . $i, $sum_cantidad);
    
    // Formato exacto para el total de saldo capital
    $activa->setCellValue('C' . $i, $sum_NCapDes);
    $activa->getStyle('C' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
    
    $activa->setCellValue('D' . $i, 1); // 100% como decimal
    $activa->getStyle('D' . $i)->getNumberFormat()->setFormatCode('0.00%');
    
    $activa->setCellValue('E' . $i, $sum_cantidad_mora);
    
    // Formato exacto para el total de saldo mora
    $activa->setCellValue('F' . $i, $sum_suma_mora);
    $activa->getStyle('F' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
    
    $activa->setCellValue('G' . $i, 1); // 100% como decimal
    $activa->getStyle('G' . $i)->getNumberFormat()->setFormatCode('0.00%');

    if ($mostrarRegionCol) {
        $activa->setCellValue('H' . $i, $regionNombre);
    }

    $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'H' : 'G') . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    $columnas = range('A', $mostrarRegionCol ? 'H' : 'G');
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
        'namefile' => "Clasificacion por municipios " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}