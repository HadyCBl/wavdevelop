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

//STATUS - EXACTO como cartera_fondos.php
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//REGION (opcional)
$filregion = ($regionRadio === "anyregion" && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $regionId . ")"
    : "";

//-----------------------------
$strquery = "SELECT 
    CASE
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) < 20 THEN 1
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 20 AND 29 THEN 2
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 30 AND 39 THEN 3
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 40 AND 49 THEN 4
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 50 AND 59 THEN 5
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 60 AND 69 THEN 6
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 70 AND 79 THEN 7
        ELSE 8
    END AS rango_edad_orden,
    CASE
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) < 20 THEN 'Menores de 20'
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 20 AND 29 THEN '20 a 29'
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 30 AND 39 THEN '30 a 39'
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 40 AND 49 THEN '40 a 49'
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 50 AND 59 THEN '50 a 59'
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 60 AND 69 THEN '60 a 69'
        WHEN TIMESTAMPDIFF(YEAR, cli.date_birth, CURDATE()) BETWEEN 70 AND 79 THEN '70 a 79'
        ELSE '80 y más'
    END AS rango_edad,
    COUNT(*) AS cantidad,
    SUM(cremi.NCapDes) AS cantidad_Ncapdes,
    SUM(IFNULL(kar.sum_KP, 0)) AS total_cappag,
    SUM(IFNULL(kar.sum_interes, 0)) AS total_intpag,
    SUM(IFNULL(kar.sum_MORA, 0)) AS total_morpag,
    SUM(IFNULL(ppg_ult.sum_ncapita, 0)) AS total_capcalafec,
    SUM(IFNULL(ppg.sum_nintere, 0)) AS total_intcal,
    SUM(CAST(CASE 
        WHEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 
        THEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
        ELSE 0 
    END AS DECIMAL(15,2))) AS saldo_capital_real,
    SUM(CASE 
        WHEN IFNULL(ppg_ult.sum_ncapita, 0) - IFNULL(kar.sum_KP, 0) > 0 
        THEN 1 ELSE 0 
    END) AS cantidad_mora,
    SUM(CASE 
        WHEN IFNULL(ppg_ult.sum_ncapita, 0) - IFNULL(kar.sum_KP, 0) > 0 
        THEN IFNULL(ppg_ult.sum_ncapita, 0) - IFNULL(kar.sum_KP, 0) 
        ELSE 0 
    END) AS suma_mora
FROM cremcre_meta cremi 
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven,
    COUNT(IF(cflag = 0 AND cflag IS NOT NULL, 1, NULL)) AS cflag, 
    SUM(nintere) AS sum_nintere 
    FROM Cre_ppg 
    WHERE dfecven <= ?
    GROUP BY ccodcta
) AS ppg ON ppg.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven, 
    SUM(ncapita) AS sum_ncapita, 
    SUM(nintere) AS sum_nintere
    FROM Cre_ppg
    WHERE dfecven <= ?
    GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
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
(cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? " . $filfondo . $filagencia . $filregion . $status . " 
GROUP BY rango_edad_orden, rango_edad
ORDER BY rango_edad_orden";

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
    
    // Parámetros para la consulta - 4 parámetros EXACTO como cartera_fondos.php
    $params = [$filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha];
    
    $result = $database->getAllResults($strquery, $params);
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
    
    // Procesamiento directo de datos agregados - usando cálculos EXACTOS como cartera_fondos.php
    $data_por_edad = [];
    $no_creditos_global = 0;
    $total_kap = 0.0;
    $total_Cmora = 0;
    
    foreach ($result as $row) {
        $rango_edad = $row['rango_edad'];
        $total_creditos = (int)$row['cantidad'];
        $cantidad_Ncapdes = (float)$row['cantidad_Ncapdes'];
        $total_cappag = (float)$row['total_cappag'];
        // Usar directamente el saldo capital real calculado en SQL - EXACTO como cartera_fondos.php
        $saldo_capital_real = (float)$row['saldo_capital_real'];
        $en_mora = (int)$row['cantidad_mora'];
        
        $data_por_edad[] = [
            'rango_edad_orden' => (int)$row['rango_edad_orden'],
            'rango_edad' => $rango_edad,
            'cantidad' => $total_creditos,
            'suma_NCapDes' => $cantidad_Ncapdes,
            'suma_mora' => (float)$row['suma_mora'], // Capital en mora para PDF
            'cantidad_mora' => $en_mora,
            'saldo_capital_calculado' => $saldo_capital_real, // Campo exacto de SQL agregado
            'saldo_interes_calculado' => 0.0, // No usado en el reporte final
            'capital_mora_calculado' => 0.0 // No usado en el reporte final
        ];
        
        // Acumular totales globales usando valores exactos
        $no_creditos_global += $total_creditos;
        $total_kap += $saldo_capital_real;
        $total_Cmora += $en_mora;
    }
    
    $data = $data_por_edad;
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
        printxls($data, $titlereport, $archivo[0], $no_creditos_global, array_sum(array_column($data, 'suma_NCapDes')), $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
        printpdf($data, [$titlereport], $info, $no_creditos_global, $total_kap, $total_Cmora, $mostrarRegionCol, $regionNombre);
        break;
}

//funcion para generar pdf - simplificada para datos agregados
function printpdf($registro, $datos, $info, $no_creditos_global, $total_kap, $total_Cmora, $mostrarRegionCol = false, $regionNombre = '')
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
            $this->Cell(0, 5, 'CLASIFICACION POR EDADES ' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;
            $this->Cell($ancho_linea * 6 + 15, 5, ' ', '', 0, 'L');
            $this->Cell($ancho_linea * 3 - 2, 5, ' ',0, 1, 'C');

            $this->Cell($ancho_linea*2, 5, $this->mostrarRegionCol ? 'REGION' : ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2 , 5, 'Edades', 'B', 0, 'L');
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


        $nombre = Utf8::decode($registro[$fila]["rango_edad"]);  
        $cantidad = Utf8::decode($registro[$fila]["cantidad"]);  
        $cantidad_Ncapdes = Utf8::decode($registro[$fila]["suma_NCapDes"]);  
        $cantidad_Mora = $registro[$fila]["cantidad_mora"];  
        $cantidad_Mora_kap = $registro[$fila]["suma_mora"];  

        //TITULO GRUPO
        $pdf->CellFit($ancho_linea2*2, $tamanio_linea + 1, ($mostrarRegionCol ? Utf8::decode($regionNombre) : ' '), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 , $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad_Ncapdes, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_kap=($cantidad/$no_creditos_global)*100;
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($porcentaje_kap, 2, '.', ',') . ' %', 'R', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad_Mora, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+15, $tamanio_linea + 1, number_format($cantidad_Mora_kap, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_mora = ($cantidad > 0) ? ($cantidad_Mora / $cantidad) * 100 : 0;
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

    $pdf->Cell($ancho_linea2+15, $tamanio_linea, array_sum(array_column($registro, 'suma_mora')), 'T', 0, 'R');
    $pdf->Cell($ancho_linea2*2+10, $tamanio_linea, ' ', 'T', 0, 'R');


    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cartera General",
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
function printxls($registro, $titlereport, $usuario, $total_creditos, $total_capital, $mostrarRegionCol = false, $regionNombre = '')
{
    require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Clasificacion_por_Edad");
    
    // Configurar anchos de columnas
    $activa->getColumnDimension("A")->setWidth(30);
    $activa->getColumnDimension("B")->setWidth(15);
    $activa->getColumnDimension("C")->setWidth(20);
    $activa->getColumnDimension("D")->setWidth(20);
    $activa->getColumnDimension("E")->setWidth(15);
    $activa->getColumnDimension("F")->setWidth(20);
    $activa->getColumnDimension("G")->setWidth(15);
    if ($mostrarRegionCol) {
        $activa->getColumnDimension("H")->setWidth(20);
    }

    // Fecha y usuario
    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);
    
    // Título del reporte
    $activa->setCellValue("A4", "REPORTE");
    $activa->setCellValue("A5", "CLASIFICACIÓN DE CARTERA POR EDAD " . strtoupper($titlereport));

    // Encabezados de la tabla
    $encabezado = ["RANGO DE EDAD", "TOTAL CRÉDITOS", "MONTO OTORGADO", "SALDO CAPITAL", "% PARTICIPACIÓN", "CRÉDITOS EN MORA", "% MORA"];
    if ($mostrarRegionCol) {
        $encabezado[] = 'REGION';
    }
    $activa->fromArray($encabezado, null, 'A8');
    
    // Estilos
    $rangoEnc = $mostrarRegionCol ? 'A1:H1' : 'A1:G1';
    $rangoEnc2 = $mostrarRegionCol ? 'A2:H2' : 'A2:G2';
    $rangoTit = $mostrarRegionCol ? 'A4:H4' : 'A4:G4';
    $rangoTit2 = $mostrarRegionCol ? 'A5:H5' : 'A5:G5';
    $rangoHead = $mostrarRegionCol ? 'A8:H8' : 'A8:G8';
    $activa->getStyle($rangoEnc)->getFont()->setSize(9)->setName('Arial');
    $activa->getStyle($rangoEnc2)->getFont()->setSize(9)->setName('Arial');
    $activa->getStyle($rangoTit)->getFont()->setSize(11)->setName('Arial');
    $activa->getStyle($rangoTit2)->getFont()->setSize(11)->setName('Arial');
    $activa->getStyle($rangoHead)->getFont()->setBold(true)->setName('Arial');
    
    // Centrar títulos
    $activa->getStyle($rangoEnc)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle($rangoEnc2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle($rangoTit)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle($rangoTit2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Combinar celdas
    $activa->mergeCells($mostrarRegionCol ? 'A1:H1' : 'A1:G1');
    $activa->mergeCells($mostrarRegionCol ? 'A2:H2' : 'A2:G2');
    $activa->mergeCells($mostrarRegionCol ? 'A4:H4' : 'A4:G4');
    $activa->mergeCells($mostrarRegionCol ? 'A5:H5' : 'A5:G5');

    // Llenar datos
    $fila_actual = 9;
    foreach ($registro as $row) {
        $porcentaje_participacion = ($total_creditos > 0) ? ($row['cantidad'] / $total_creditos) * 100 : 0;
        $porcentaje_mora = ($row['cantidad'] > 0) ? ($row['cantidad_mora'] / $row['cantidad']) * 100 : 0;
        
        $activa->setCellValue('A' . $fila_actual, $row['rango_edad']);
        $activa->setCellValue('B' . $fila_actual, $row['cantidad']);
        $activa->setCellValue('C' . $fila_actual, $row['suma_NCapDes']);
        $activa->setCellValue('D' . $fila_actual, $row['saldo_capital_calculado']);
        $activa->setCellValue('E' . $fila_actual, $porcentaje_participacion / 100); // Como decimal para formato %
        $activa->setCellValue('F' . $fila_actual, $row['cantidad_mora']);
        $activa->setCellValue('G' . $fila_actual, $porcentaje_mora / 100); // Como decimal para formato %
        if ($mostrarRegionCol) {
            $activa->setCellValue('H' . $fila_actual, $regionNombre);
        }
        
        $fila_actual++;
    }
    
    // Fila de totales
    $activa->setCellValue('A' . $fila_actual, 'TOTALES');
    $activa->setCellValue('B' . $fila_actual, $total_creditos);
    $activa->setCellValue('C' . $fila_actual, array_sum(array_column($registro, 'suma_NCapDes')));
    $activa->setCellValue('D' . $fila_actual, array_sum(array_column($registro, 'saldo_capital_calculado')));
    $activa->setCellValue('E' . $fila_actual, 1.0); // 100% como decimal
    $activa->setCellValue('F' . $fila_actual, array_sum(array_column($registro, 'cantidad_mora')));
    $activa->setCellValue('G' . $fila_actual, ''); // No aplica % total
    if ($mostrarRegionCol) {
        $activa->setCellValue('H' . $fila_actual, $regionNombre);
    }
    
    // Formato de totales
    $rangoTotales = $mostrarRegionCol ? ("A" . $fila_actual . ":H" . $fila_actual) : ("A" . $fila_actual . ":G" . $fila_actual);
    $activa->getStyle($rangoTotales)->getFont()->setBold(true);
    $activa->getStyle($rangoTotales)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFFF00');
    
    // Aplicar formatos numéricos - EXACTO como los otros reportes refactorizados
    $rango_datos = 'B9:D' . $fila_actual;
    $activa->getStyle($rango_datos)->getNumberFormat()->setFormatCode('#,##0.00');
    
    $rango_porcentajes = 'E9:G' . ($fila_actual - 1); // Excluir fila de totales en porcentajes
    $activa->getStyle($rango_porcentajes)->getNumberFormat()->setFormatCode('0.00%');
    
    // Ajustar columnas automáticamente
    foreach (range('A', $mostrarRegionCol ? 'H' : 'G') as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(true);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Clasificacion_por_Edad_" . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}