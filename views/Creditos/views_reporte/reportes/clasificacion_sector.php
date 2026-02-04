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
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = (isset($radios[3]) && $radios[3] == "anyasesor" && isset($selects[2])) ? " AND cremi.CodAnal =" . $selects[2] : "";
//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//REGION (opcional)
$filregion = ($regionRadio === "anyregion" && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $regionId . ")"
    : "";

$strquery = "SELECT 
    cremi.CODAgencia,
    CONCAT(usu.nombre, ' ', usu.apellido) AS analista,
    cremi.CCODCTA,
    cremi.NtipPerC,
    prod.id_fondo AS id_fondos,
    ffon.descripcion AS nombre_fondo,
    prod.id AS id_producto,
    prod.descripcion AS nombre_producto,
    cremi.NintApro AS tasa,
    prod.porcentaje_mora AS tasamora,
    cli.short_name,cremi.CodCli codcliente,
    cli.date_birth,
    IFNULL(cli.genero,'X') genero,
    cli.estado_civil,cli.Direccion direccion,cli.tel_no1 tel1,cli.tel_no2 tel2,
    cremi.DFecDsbls,
    cremi.MonSug,
    cremi.NCapDes, cremi.DfecPago fecpago,dest.DestinoCredito destino,
    IFNULL(sector.SectoresEconomicos, 'SIN CLASIFICAR') AS sectorEconomico,
    IFNULL(actividad.Titulo, 'SIN ACTIVIDAD') AS actividadEconomica,
    IFNULL(ppg.dfecven, '-') AS fechaven,
    IFNULL(ppg.cflag, '') AS fallas,
    IFNULL(ppg.sum_nintere, 0) AS intcal,
    IFNULL(ppg_ult.dfecven, '-') AS fechacalult,
    IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
    IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
    IFNULL(kar.sum_KP, 0) AS cappag,
    IFNULL(kar.sum_interes, 0) AS intpag,
    IFNULL(kar.sum_MORA, 0) AS morpag,
    IFNULL(kar.sum_AHOPRG_OTR, 0) AS otrpag,
    IFNULL(kar.dfecpro_ult, '-') AS fechaultpag,
    IFNULL((SELECT (ncapita+nintere) FROM Cre_ppg WHERE ccodcta=cremi.CCODCTA LIMIT 1),0) AS moncuota,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso,
    IFNULL(grupo.NombreGrupo, ' ') AS NombreGrupo,
    cremi.TipoEnti,
    IFNULL(cremi.CCodGrupo, ' ') AS CCodGrupo,
    cremi.Cestado,
    tcred.descr as tipocredito,
    GROUP_CONCAT(tipgar.TiposGarantia SEPARATOR ', ') AS tipo_garantia,
    IFNULL(muni.nombre,'-') as municipio_reside,
    IFNULL(cli.PEP, '-') AS ES_PEP,
    IFNULL(cli.CPE, '-') AS ES_CEP
FROM cremcre_meta cremi
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
LEFT JOIN {$db_name_general}.tb_destinocredito dest ON dest.id_DestinoCredito=cremi.Cdescre
LEFT JOIN {$db_name_general}.`tb_sectoreseconomicos` sector ON sector.id_SectoresEconomicos=cremi.CSecEco
LEFT JOIN {$db_name_general}.`tb_ActiEcono` actividad ON actividad.id_ActiEcono=cremi.ActoEcono
LEFT JOIN {$db_name_general}.`tb_credito` tcred ON tcred.abre = cremi.CtipCre
LEFT JOIN tb_garantias_creditos garcre ON garcre.id_cremcre_meta = cremi.ccodcta
LEFT JOIN cli_garantia clgar ON garcre.id_garantia = clgar.idGarantia
LEFT JOIN tb_municipios muni ON muni.id = cli.id_muni_reside
LEFT JOIN {$db_name_general}.tb_tiposgarantia tipgar ON clgar.idTipoGa = tipgar.id_TiposGarantia
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven,
    COUNT(IF(cflag = 0 AND cflag IS NOT NULL, 1, NULL)) AS cflag, 
    SUM(nintere) AS sum_nintere 
    FROM 
        Cre_ppg 
        GROUP BY 
            ccodcta
) AS ppg ON ppg.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(ncapita) AS sum_ncapita, SUM(nintere) AS sum_nintere
    FROM Cre_ppg
    WHERE dfecven <= ?
    GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP, MAX(dfecpro) AS dfecpro_ult, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? {$filfondo} {$filagencia} {$filasesor} {$filregion} {$status} 
GROUP BY cremi.CCODCTA ORDER BY sector.SectoresEconomicos, actividad.Titulo, cremi.DFecDsbls;";

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
    $result = $database->getAllResults($strquery, [$filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha]);
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
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = isset($e) ? $e->getMessage() : 'Error desconocido';
        error_log("Error en clasificacion_sector.php: " . $codigoError);
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

// Agrupar datos por actividad económica y calcular totales globales
$actividades = [];
$total_cantcreditos = 0;
$capital_global = 0;
$mora_global = 0;
$total_cremorosos = 0;

foreach ($result as $fila) {
    // Calcular saldos para este crédito
    $monto = $fila["NCapDes"];
    $cappag = $fila["cappag"];
    $capcalafec = $fila["capcalafec"];
    $actividad = $fila['actividadEconomica'] ?? 'SIN ACTIVIDAD';
    
    // SALDO DE CAPITAL A LA FECHA
    $salcap = ($monto - $cappag);
    $salcap = ($salcap > 0) ? $salcap : 0;
    
    // CAPITAL EN MORA A LA FECHA
    $capmora = $capcalafec - $cappag;
    $capmora = ($capmora > 0) ? $capmora : 0;
    
    // Acumular totales globales
    $total_cantcreditos++;
    $capital_global += $salcap;
    $mora_global += $capmora;
    if ($capmora > 0) {
        $total_cremorosos++;
    }
    
    // Agrupar por actividad económica
    if (!isset($actividades[$actividad])) {
        $actividades[$actividad] = [
            'Titulo_Actividad' => $actividad,
            'cantidad_datos' => 0,
            'cantidad_Ncapdes' => 0,
            'suma_mora' => 0,
            'cantidad_mora' => 0
        ];
    }
    
    $actividades[$actividad]['cantidad_datos']++;
    $actividades[$actividad]['cantidad_Ncapdes'] += $salcap;
    $actividades[$actividad]['suma_mora'] += $capmora;
    if ($capmora > 0) {
        $actividades[$actividad]['cantidad_mora']++;
    }
}

// Convertir array asociativo a array indexado para la función PDF
$data_agrupada = array_values($actividades);

switch ($tipo) {
    case 'xlsx';
        printxls($result, $titlereport, $archivo[0], $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
        printpdf($data_agrupada, [$titlereport], $info, $total_cantcreditos, $capital_global, $mora_global, $total_cremorosos, count($actividades), $mostrarRegionCol, $regionNombre);
}

//funcion para generar pdf
function printpdf($registro, $datos, $info, $total_cantcreditos, $capital_global, $mora_global, $total_cremorosos, $total_actividades, $mostrarRegionCol = false, $regionNombre = '')
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
            $this->Cell(0, 5, 'CLASIFICACION DE LOS CREDITOS POR ACTIVIDAD ECONOMICA' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);

            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;
            
            $this->Cell($ancho_linea*2, 5, $this->mostrarRegionCol ? 'REGION' : ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2 , 5, 'ACTIVIDAD ECONOMICA', 'B', 0, 'L');
            $this->Cell($ancho_linea+10, 5, 'NO. DE CREDITOS', 'B', 0, 'C');
            $this->Cell($ancho_linea+5, 5, 'SALDO CAPITAL', 'B', 0, 'C');
            $this->Cell($ancho_linea+5, 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea+5, 5, 'CREDITOS MOROSOS', 'B', 0, 'R');
            $this->Cell($ancho_linea+15, 5, 'SALDO CAPITAL EN MORA', 'B', 0, 'R');
            $this->Cell($ancho_linea+10, 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea / 2, 5, ' ', 0, 1, 'R');
            $this->Ln(1);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
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

    // Imprimir datos agrupados por actividad económica
    foreach ($registro as $actividad) {
        $nombre = Utf8::decode($actividad["Titulo_Actividad"] ?? 'Sin título');
        $cantidad = $actividad["cantidad_datos"] ?? 0;
        $saldo_capital = $actividad["cantidad_Ncapdes"] ?? 0;
        $creditos_morosos = $actividad["cantidad_mora"] ?? 0;
        $saldo_mora = $actividad["suma_mora"] ?? 0;

        $pdf->CellFit($ancho_linea2*2, $tamanio_linea + 1, ($mostrarRegionCol ? Utf8::decode($regionNombre) : ' '), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 , $tamanio_linea + 1, ' ' . ($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+10, $tamanio_linea + 1, number_format($cantidad, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($saldo_capital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        
        if ($capital_global != 0) {
            $porcentaje_kap = ($saldo_capital / $capital_global) * 100;
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, number_format($porcentaje_kap, 2, '.', ',') . '%', '', 0, 'C', 0, '', 1, 0);
        } else {
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'N/A', '', 0, 'C', 0, '', 1, 0);
        }

        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($creditos_morosos, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+15, $tamanio_linea + 1, number_format($saldo_mora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        
        if ($mora_global != 0) {
            $porcentaje_mora = ($saldo_mora / $mora_global) * 100;
            $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea + 1, number_format($porcentaje_mora, 2, '.', ',') . '%', '', 0, 'C', 0, '', 1, 0);
        } else {
            $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea + 1, 'N/A', '', 0, 'C', 0, '', 1, 0);
        }
        
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, ' ', '', 1, 'R', 0, '', 1, 0);
    }
    
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 7);
    
    // Totales finales
    $pdf->CellFit($ancho_linea2*2, $tamanio_linea, ' ', 'T', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, 'TOTAL ACTIVIDADES: ' . $total_actividades, 'T', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+10, $tamanio_linea, number_format($total_cantcreditos, 0, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+5, $tamanio_linea, number_format($capital_global, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+5, $tamanio_linea, '100.00%', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+5, $tamanio_linea, number_format($total_cremorosos, 0, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+15, $tamanio_linea, number_format($mora_global, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+10, $tamanio_linea, '100.00%', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2/2, $tamanio_linea, ' ', 'T', 1, 'R', 0, '', 1, 0);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Clasificacion por Actividad Economica",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//FUNCIONES PARA DATOS DE RESUMEN
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
    if ($mostrarRegionCol) {
        $activa->getColumnDimension("AG")->setWidth(20);
    }


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
    $activa->setCellValue("A5", strtoupper("CARTERA GENERAL " . $titlereport));

    //TITULO DE RECARGOS

    //titulo de recargos
    $activa->getStyle("A7:X7")->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->getStyle("A7:X7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->setCellValue("P7", "RECUPERACIONES");

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CRÉDITO", "FONDO", "GENERO", "FECHA DE NACIMIENTO", "NOMBRE DEL CLIENTE","DIRECCION","TEL1","TEL2", "OTORGAMIENTO", "VENCIMIENTO", "MONTO OTORGADO", "TOTAL INTERES A PAGAR", "SALDO CAPITAL", "SALDO INTERES", "SALDO MORA", "CAPITAL PAGADO", "INTERES PAGADO", "MORA PAGADO", "OTROS", "DIAS DE ATRASO", "SALDO CAP MAS INTERES", "MORA CAPITAL", "TASA INTERES", "TASA MORA", "PRODUCTO", "AGENCIA", "ASESOR", "TIPO CREDITO", "GRUPO","ESTADO","DESTINO","DIA PAGO"];
    if ($mostrarRegionCol) {
        $encabezado_tabla[] = 'REGION';
    }
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle($mostrarRegionCol ? 'A8:AG8' : 'A8:AF8')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $activa->mergeCells('A1:X1');
    $activa->mergeCells('A2:X2');
    $activa->mergeCells('A4:X4');
    $activa->mergeCells('A5:X5');
    $activa->mergeCells('M7:O7');

    $fila = 0;
    $i = 9;
    while ($fila < count($registro)) {
        $cuenta = $registro[$fila]["CCODCTA"];
        $nombre =  $registro[$fila]["short_name"];
        $direccion =  $registro[$fila]["direccion"];
        $tel1 =  $registro[$fila]["tel1"];
        $tel2 =  $registro[$fila]["tel2"];
        $genero =  $registro[$fila]["genero"];
        $date_birth =  $registro[$fila]["date_birth"];
        $fechades = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
        $fechaven = $registro[$fila]["fechaven"];
        $fechaven = ($fechaven != "0") ? date("d-m-Y", strtotime($fechaven)) : "-";
        $monto = $registro[$fila]["NCapDes"];
        $intcal = $registro[$fila]["intcal"];
        $capcalafec = $registro[$fila]["capcalafec"];
        $intcalafec = $registro[$fila]["intcalafec"];
        $cappag = $registro[$fila]["cappag"];
        $intpag = $registro[$fila]["intpag"];
        $morpag = $registro[$fila]["morpag"];

        $idfondos = $registro[$fila]["id_fondos"];
        $nombrefondos = $registro[$fila]["nombre_fondo"];
        $idproducto = $registro[$fila]["id_producto"];
        $nameproducto = $registro[$fila]["nombre_producto"];
        $analista = $registro[$fila]["analista"];
        $CODAgencia = $registro[$fila]["CODAgencia"];
        $tasa = $registro[$fila]["tasa"];
        $tasamora = $registro[$fila]["tasamora"];
        $otrpag = $registro[$fila]["otrpag"];
        $tipoenti = $registro[$fila]["TipoEnti"];
        $nomgrupo = $registro[$fila]["NombreGrupo"];
        $estado = $registro[$fila]["Cestado"];
        $destino = $registro[$fila]["destino"];
        $diapago =date('d', strtotime($registro[$fila]["fecpago"]));
        $estado=($estado=="F")?"VIGENTE":"CANCELADO";

        //SALDO DE CAPITAL A LA FECHA
        $salcap = ($monto - $cappag);
        $salcap = ($salcap > 0) ? $salcap : 0;

        //SALDO DE INTERES A LA FECHA
        $salint = ($intcal - $intpag);
        $salint = ($salint > 0) ? $salint : 0;

        //CAPITAL EN MORA A LA FECHA
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;

        $registro[$fila]["salcapital"] = $salcap;
        $registro[$fila]["salintere"] = $salint;
        $registro[$fila]["capmora"] = $capmora;


        $activa->setCellValueExplicit('A' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('B' . $i, $nombrefondos);
        $activa->setCellValue('C' . $i, strtoupper($genero));
        $activa->setCellValue('D' . $i, $date_birth);
        $activa->setCellValue('E' . $i, strtoupper($nombre));
        $activa->setCellValue('F' . $i, $direccion);
        $activa->setCellValue('G' . $i, $tel1);
        $activa->setCellValue('H' . $i, $tel2);
        $activa->setCellValue('I' . $i, $fechades);
        $activa->setCellValue('J' . $i, $fechaven);
        $activa->setCellValueExplicit('K' . $i, $monto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('L' . $i, $intcal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('M' . $i, $salcap, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('N' . $i, $salint, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('O' . $i, 0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('P' . $i, $cappag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('Q' . $i, $intpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('R' . $i, $morpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('S' . $i, $otrpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('T' . $i, $diasatr, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('U' . $i, ($salcap + $salint), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('V' . $i, $capmora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('W' . $i, $tasa, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('X' . $i, $tasamora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('Y' . $i, strtoupper($nameproducto), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('Z' . $i, $CODAgencia, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AA' . $i, strtoupper($analista), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AB' . $i, $tipoenti, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AC' . $i, $nomgrupo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AD' . $i, $estado, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AE' . $i, $destino, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AF' . $i, $diapago, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        if ($mostrarRegionCol) {
            $activa->setCellValueExplicit('AG' . $i, $regionNombre, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }

        $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'AG' : 'AF') . $i)->getFont()->setName($fuente);

        $fila++;
        $i++;
    }
    //total de registros
    $sum_monto = array_sum(array_column($registro, "NCapDes"));
    $sum_intcal = array_sum(array_column($registro, "intcal"));
    $sum_cappag = array_sum(array_column($registro, "cappag"));
    $sum_intpag = array_sum(array_column($registro, "intpag"));
    $sum_morpag = array_sum(array_column($registro, "morpag"));
    $sum_salcap = array_sum(array_column($registro, "salcapital"));
    $sum_salint = array_sum(array_column($registro, "salintere"));
    $sum_capmora = array_sum(array_column($registro, "capmora"));
    $sum_otrpag = array_sum(array_column($registro, "otrpag"));
    $sum_capmora = array_sum(array_column($registro, "capmora"));
    $sum_tasa = array_sum(array_column($registro, "tasa"));
    $sum_tasamora = array_sum(array_column($registro, "tasamora"));

    $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'AG' : 'AF') . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValueExplicit('A' . $i, "Número de créditos: " . $i, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->mergeCells("A" . $i . ":G" . $i);

    $activa->setCellValueExplicit('K' . $i, $sum_monto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('L' . $i, $sum_intcal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('M' . $i, $sum_salcap, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('N' . $i, $sum_salint, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('O' . $i, 0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('P' . $i, $sum_cappag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('Q' . $i, $sum_intpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('R' . $i, $sum_morpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('S' . $i, $sum_otrpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    $activa->setCellValueExplicit('T' . $i, ($sum_salcap + $sum_salint), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('U' . $i, $sum_capmora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('V' . $i, $sum_tasa, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('W' . $i, $sum_tasamora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    $activa->getStyle("A" . $i . ":" . ($mostrarRegionCol ? 'AG' : 'AF') . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    $columnas = range('A', $mostrarRegionCol ? 'AG' : 'AF');
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
        'namefile' => "Cartera general " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}