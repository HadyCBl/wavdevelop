<?php
// Agregar reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

// Debug: Verificar que las variables estén definidas
if (empty($db_host) || empty($db_name) || empty($db_user) || empty($db_name_general)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Error: Variables de configuración de base de datos no definidas']);
    return;
}

include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Micro\Generic\Utf8;

// Validar datos POST
if (!isset($_POST["datosval"]) || !isset($_POST["tipo"])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos POST requeridos no encontrados']);
    return;
}

$datos = $_POST["datosval"];
if (!is_array($datos) || count($datos) < 4) {
    echo json_encode(['status' => 0, 'mensaje' => 'Estructura de datos inválida']);
    return;
}

$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

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
// Configuración de filtros
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

// Construcción de filtros
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id = ?" : "";
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia = ?" : "";
$filregion = ($regionRadio === "anyregion") ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region = ?)" : "";
$status = ($radios[2] == "allstatus") ? "(cremi.CESTADO='F' OR cremi.CESTADO='G')" : "cremi.CESTADO = ?";

// Query principal para clasificación por sexo - usando EXACTAMENTE la misma lógica que cartera_fondos.php
$strquery = "SELECT 
    IFNULL(cli.genero,'SIN ESPECIFICAR') as genero,
    COUNT(*) AS cantidad,
    SUM(cremi.NCapDes) AS cantidad_Ncapdes,
    SUM(IFNULL(kar.sum_KP, 0)) AS total_cappag,
    SUM(IFNULL(kar.sum_interes, 0)) AS total_intpag,
    SUM(IFNULL(kar.sum_MORA, 0)) AS total_morpag,
    SUM(IFNULL(ppg_ult.sum_ncapita, 0)) AS total_capcalafec,
    SUM(IFNULL(ppg.sum_nintere, 0)) AS total_intcal,
    SUM(CASE 
        WHEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 
        THEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
        ELSE 0 
    END) AS saldo_capital_real,
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
    SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(nintere) AS sum_nintere 
    FROM Cre_ppg 
    GROUP BY ccodcta
) AS ppg ON ppg.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(ncapita) AS sum_ncapita, SUM(nintere) AS sum_nintere
    FROM Cre_ppg
    WHERE dfecven <= ?
    GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
WHERE 
    ({$status}) AND cremi.DFecDsbls <= ?" . $filfondo . $filagencia . $filregion . " 
GROUP BY cli.genero";

// Preparar parámetros en el mismo orden de los placeholders "?" del SQL
// 1) ppg_ult.dfecven <= ?
// 2) kar.dfecpro <= ?
// 3) status (si aplica)
// 4) cremi.DFecDsbls <= ?
$params = [$filtrofecha, $filtrofecha];
$types = "ss";

if ($radios[2] != "allstatus") {
    $params[] = $radios[2];
    $types .= "s";
}

$params[] = $filtrofecha;
$types .= "s";

if ($radios[1] == "anyf") {
    $params[] = $selects[1];
    $types .= "i";
}

if ($radios[0] == "anyofi") {
    $params[] = $selects[0];
    $types .= "i";
}

if ($regionRadio === "anyregion") {
    $params[] = $regionId;
    $types .= "i";
}

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
    
    // Ejecutar consulta principal
    $result = $database->getAllResults($strquery, $params);
    
    if (empty($result)) {
        throw new Exception("No hay datos para mostrar en el rango seleccionado");
    }

    // Procesar resultados directamente desde la consulta agregada
    $data = [];
    $total_cantcreditos = 0;
    $capital_global = 0.0;
    $mora_global = 0.0;
    $total_cremorosos = 0;

    foreach ($result as $fila) {
        // Usar cálculos directos de la consulta SQL agregada - EXACTO como cartera_fondos.php
        $genero = $fila['genero'];
        $cantidad = intval($fila['cantidad']);
        $cantidad_Ncapdes = floatval($fila['cantidad_Ncapdes']);
        $total_cappag = floatval($fila['total_cappag']);
        
        // Saldo de capital usando EXACTAMENTE el mismo campo que cartera_fondos.php
        $saldo_capital = floatval($fila['saldo_capital_real']);
        
        // Mora directa de la consulta
        $cantidad_mora = intval($fila['cantidad_mora']);
        $suma_mora = floatval($fila['suma_mora']);
        
        // Acumular totales globales
        $total_cantcreditos += $cantidad;
        $capital_global += $saldo_capital;
        $mora_global += $suma_mora;
        $total_cremorosos += $cantidad_mora;
        
        // Agregar al array de datos con campos calculados exactos
        $data[] = [
            'genero' => $genero,
            'cantidad' => $cantidad,
            'saldo_capital_calculado' => $saldo_capital, // Campo exacto de SQL, no recalculado
            'cantidad_mora' => $cantidad_mora,
            'capital_mora_calculado' => $suma_mora
        ];
    }

    // Obtener información de la institución
    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        throw new Exception("Institución asignada a la agencia no encontrada");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 0, 'mensaje' => 'Error en la consulta: ' . $e->getMessage()]);
    return;
} finally {
    $database->closeConnection();
}

switch ($tipo) {
    case 'xlsx';
        printxls($data, $titlereport, $archivo[0], $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
        printpdf($data, [$titlereport], $info, $mora_global, $total_cremorosos, $capital_global, $total_cantcreditos, $mostrarRegionCol, $regionNombre);
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info, $mora_global, $total_cremorosos, $capital_global, $total_cantcreditos, $mostrarRegionCol = false, $regionNombre = '')
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
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

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $mostrarRegionCol, $regionNombre)
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
            $this->Cell(0, 5, 'CLASIFICACION DE LOS CREDITOS POR SEXO' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;
            $this->Cell($ancho_linea-10 * 6 + 15, 5, ' ', '', 0, 'L');
            $this->Cell($ancho_linea * 3 - 2, 5, ' ', 0, 1, 'C');
            $this->Cell($ancho_linea-5, 5, $this->mostrarRegionCol ? 'REGION' : ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea , 5, ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2 + 15, 5, ' SEXO', 'B', 0, 'L');
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $mostrarRegionCol, $regionNombre);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 20;
    $pdf->SetFont($fuente, '', 7);
    $aux = 0;
    $auxgrupo = 0;
    $fila = 0;
    $suma_total =0;
    $sum_cantidad_Ncapdes =0 ;
    $sum_cantidad_mora =0;

    while ($fila < count($registro)) {
        $nombre = Utf8::decode($registro[$fila]["genero"]);
        $cantidad_creditos = $registro[$fila]["cantidad"];
        $saldo_capital = $registro[$fila]["saldo_capital_calculado"]; // Usar campo calculado
        $cantidad_mora = $registro[$fila]["cantidad_mora"];
        $capital_mora = $registro[$fila]["capital_mora_calculado"]; // Usar campo calculado
        
        //FILA DE DATOS
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ($mostrarRegionCol ? Utf8::decode($regionNombre) : ' '), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2-5, $tamanio_linea + 1, ' ', '', 0, 'C', 0, '', 1, 0);//vacio
        $pdf->CellFit($ancho_linea2 * 2 + 19, $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);//GENERO 
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, $cantidad_creditos, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($saldo_capital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_cap = ($capital_global > 0) ? ($saldo_capital / $capital_global) * 100 : 0;
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($porcentaje_cap, 2, '.', ',') . ' %', 'R', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad_mora), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+13, $tamanio_linea + 1, number_format($capital_mora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_mora = ($mora_global > 0) ? ($capital_mora / $mora_global) * 100 : 0;
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($porcentaje_mora, 2, '.', ',') . ' %', 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, ' ', '', 1, 'R', 0, '', 1, 0);

        $suma_total += $cantidad_creditos;
        $sum_cantidad_Ncapdes += $saldo_capital;
        $sum_cantidad_mora += $cantidad_mora;
        $fila++;      
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 7);

    $pdf->CellFit($ancho_linea2 *3+10, $tamanio_linea , Utf8::decode('Numero de géneros: ') . $fila, 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2*2 , $tamanio_linea , $total_cantcreditos , 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+10 , $tamanio_linea , number_format($capital_global, 2, '.', ',') , 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 , $tamanio_linea , ' ' , 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+10 , $tamanio_linea , $total_cremorosos , 'T', 0, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 2-6, $tamanio_linea, number_format($mora_global, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2*3 , $tamanio_linea , ' ' , 'T', 0, 'R', 0, '', 1, 0);

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
function printxls($registro, $titlereport, $usuario, $mostrarRegionCol = false, $regionNombre = '')
{
    require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("ClasificacionGenero");
    
    // Configurar anchos de columna
    $activa->getColumnDimension("A")->setWidth(20); // Género
    $activa->getColumnDimension("B")->setWidth(15); // Cantidad
    $activa->getColumnDimension("C")->setWidth(20); // Saldo Capital
    $activa->getColumnDimension("D")->setWidth(15); // % Participación
    $activa->getColumnDimension("E")->setWidth(15); // Créditos en Mora
    $activa->getColumnDimension("F")->setWidth(20); // Monto en Mora
    $activa->getColumnDimension("G")->setWidth(15); // % Mora
    if ($mostrarRegionCol) {
        $activa->getColumnDimension("H")->setWidth(20); // Región
    }

    // Encabezado de fecha y usuario
    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);
    $rangoEnc = $mostrarRegionCol ? 'A1:H1' : 'A1:G1';
    $rangoEnc2 = $mostrarRegionCol ? 'A2:H2' : 'A2:G2';
    $rangoTit = $mostrarRegionCol ? 'A4:H4' : 'A4:G4';
    $rangoTit2 = $mostrarRegionCol ? 'A5:H5' : 'A5:G5';
    $rangoHead = $mostrarRegionCol ? 'A7:H7' : 'A7:G7';
    $activa->getStyle($rangoEnc)->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle($rangoEnc2)->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle($rangoEnc)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle($rangoEnc2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Título del reporte
    $activa->setCellValue("A4", "REPORTE");
    $activa->setCellValue("A5", strtoupper("CLASIFICACIÓN POR GÉNERO " . $titlereport));
    $activa->getStyle($rangoTit)->getFont()->setSize($tamanioTabla)->setName($fuente);
    $activa->getStyle($rangoTit2)->getFont()->setSize($tamanioTabla)->setName($fuente);
    $activa->getStyle($rangoTit)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle($rangoTit2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Encabezados de columna
    $encabezado_tabla = [
        "GÉNERO", 
        "CANTIDAD", 
        "SALDO CAPITAL", 
        "% PARTICIPACIÓN", 
        "CRÉDITOS EN MORA", 
        "MONTO EN MORA", 
        "% MORA"
    ];
    if ($mostrarRegionCol) {
        $encabezado_tabla[] = 'REGION';
    }
    $activa->fromArray($encabezado_tabla, null, 'A7')->getStyle($rangoHead)->getFont()->setName($fuente)->setBold(true);

    // Combinar celdas
    $activa->mergeCells($mostrarRegionCol ? 'A1:H1' : 'A1:G1');
    $activa->mergeCells($mostrarRegionCol ? 'A2:H2' : 'A2:G2');
    $activa->mergeCells($mostrarRegionCol ? 'A4:H4' : 'A4:G4');
    $activa->mergeCells($mostrarRegionCol ? 'A5:H5' : 'A5:G5');

    $i = 8;
    $total_creditos = 0;
    $total_capital = 0.0;
    $total_creditos_mora = 0;
    $total_monto_mora = 0.0;

    // Llenar datos
    foreach ($registro as $fila) {
        $genero = $fila['genero'];
        $cantidad = $fila['cantidad'];
        $saldo_capital = $fila['saldo_capital_calculado'];
        $creditos_mora = $fila['cantidad_mora'];
        $monto_mora = $fila['capital_mora_calculado'];

        // Acumular totales
        $total_creditos += $cantidad;
        $total_capital += $saldo_capital;
        $total_creditos_mora += $creditos_mora;
        $total_monto_mora += $monto_mora;

        // Escribir fila
        $activa->setCellValue('A' . $i, $genero);
        $activa->setCellValueExplicit('B' . $i, $cantidad, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('C' . $i, $saldo_capital, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        // Porcentaje participación - calcular al final cuando tengamos total
        $activa->setCellValueExplicit('E' . $i, $creditos_mora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('F' . $i, $monto_mora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        // Porcentaje mora - calcular al final

        if ($mostrarRegionCol) {
            $activa->setCellValue('H' . $i, $regionNombre);
        }

        $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'H' : 'G') . $i)->getFont()->setName($fuente);
        $i++;
    }

    // Calcular y llenar porcentajes después de tener totales
    $row_start = 8;
    foreach ($registro as $index => $fila) {
        $row_current = $row_start + $index;
        $saldo_capital = $fila['saldo_capital_calculado'];
        $monto_mora = $fila['capital_mora_calculado'];
        
        // Porcentaje de participación
        $porcentaje_participacion = ($total_capital > 0) ? ($saldo_capital / $total_capital) : 0;
        $activa->setCellValueExplicit('D' . $row_current, $porcentaje_participacion, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        
        // Porcentaje de mora
        $porcentaje_mora = ($saldo_capital > 0) ? ($monto_mora / $saldo_capital) : 0;
        $activa->setCellValueExplicit('G' . $row_current, $porcentaje_mora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    }

    // Fila de totales
    $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'H' : 'G') . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValue('A' . $i, "TOTAL GÉNEROS: " . count($registro));
    $activa->setCellValueExplicit('B' . $i, $total_creditos, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('C' . $i, $total_capital, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('D' . $i, 1.0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC); // 100%
    $activa->setCellValueExplicit('E' . $i, $total_creditos_mora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('F' . $i, $total_monto_mora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    
    // Porcentaje total de mora
    $porcentaje_mora_total = ($total_capital > 0) ? ($total_monto_mora / $total_capital) : 0;
    $activa->setCellValueExplicit('G' . $i, $porcentaje_mora_total, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    if ($mostrarRegionCol) {
        $activa->setCellValue('H' . $i, $regionNombre);
    }

    $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'H' : 'G') . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    // Aplicar formato de número exacto a columnas monetarias
    $activa->getStyle('C8:C' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
    $activa->getStyle('F8:F' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // Aplicar formato de porcentaje exacto
    $activa->getStyle('D8:D' . $i)->getNumberFormat()->setFormatCode('0.00%');
    $activa->getStyle('G8:G' . $i)->getNumberFormat()->setFormatCode('0.00%');

    // Auto-dimensionar columnas
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
        'namefile' => "Clasificación por Género " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}