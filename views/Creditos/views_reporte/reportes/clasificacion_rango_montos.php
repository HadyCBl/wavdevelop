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
    CASE
         WHEN cremi.NCapDes BETWEEN 1 AND 499 THEN 'Hasta 500'
         WHEN cremi.NCapDes BETWEEN 500 AND 999 THEN 'Monto de Q.500.00 a Q.999.00'
         WHEN cremi.NCapDes BETWEEN 1000 AND 1999 THEN 'Monto de Q.1000.00 a Q.1999.00'
         WHEN cremi.NCapDes BETWEEN 2000 AND 2999 THEN 'Monto de Q.2000.00 a Q.2999.00'
         WHEN cremi.NCapDes BETWEEN 3000 AND 3999 THEN 'Monto de Q.3000.00 a Q.3999.00'
         WHEN cremi.NCapDes BETWEEN 4000 AND 4999 THEN 'Monto de Q.4000.00 a Q.4999.00'
         WHEN cremi.NCapDes BETWEEN 5000 AND 5999 THEN 'Monto de Q.5000.00 a Q.5999.00'
         WHEN cremi.NCapDes BETWEEN 6000 AND 6999 THEN 'Monto de Q.6000.00 a Q.6999.00'
         WHEN cremi.NCapDes BETWEEN 7000 AND 7999 THEN 'Monto de Q.7000.00 a Q.7999.00'
         WHEN cremi.NCapDes BETWEEN 8000 AND 8999 THEN 'Monto de Q.8000.00 a Q.8999.00'
         WHEN cremi.NCapDes BETWEEN 9000 AND 9999 THEN 'Monto de Q.9000.00 a Q.9999.00'
         WHEN cremi.NCapDes BETWEEN 10000 AND 10999 THEN 'Monto de Q.10000.00 a Q.10999.00'
         WHEN cremi.NCapDes BETWEEN 11000 AND 11999 THEN 'Monto de Q.11000.00 a Q.11999.00'
         WHEN cremi.NCapDes BETWEEN 12000 AND 12999 THEN 'Monto de Q.12000.00 a Q.12999.00'
         WHEN cremi.NCapDes BETWEEN 13000 AND 13999 THEN 'Monto de Q.13000.00 a Q.13999.00'
         WHEN cremi.NCapDes BETWEEN 14000 AND 14999 THEN 'Monto de Q.14000.00 a Q.14999.00'
         WHEN cremi.NCapDes BETWEEN 15000 AND 15999 THEN 'Monto de Q.15000.00 a Q.15999.00'
         WHEN cremi.NCapDes BETWEEN 16000 AND 16999 THEN 'Monto de Q.16000.00 a Q.16999.00'
         WHEN cremi.NCapDes BETWEEN 17000 AND 17999 THEN 'Monto de Q.17000.00 a Q.17999.00'
         WHEN cremi.NCapDes BETWEEN 18000 AND 18999 THEN 'Monto de Q.18000.00 a Q.18999.00'
         WHEN cremi.NCapDes BETWEEN 19000 AND 19999 THEN 'Monto de Q.19000.00 a Q.19999.00'
         WHEN cremi.NCapDes BETWEEN 20000 AND 20999 THEN 'Monto de Q.20000.00 a Q.20999.00'
         WHEN cremi.NCapDes BETWEEN 21000 AND 21999 THEN 'Monto de Q.21000.00 a Q.21999.00'
         WHEN cremi.NCapDes BETWEEN 22000 AND 22999 THEN 'Monto de Q.22000.00 a Q.22999.00'
         WHEN cremi.NCapDes BETWEEN 23000 AND 23999 THEN 'Monto de Q.23000.00 a Q.23999.00'
         WHEN cremi.NCapDes BETWEEN 24000 AND 24999 THEN 'Monto de Q.24000.00 a Q.24999.00'
         WHEN cremi.NCapDes BETWEEN 25000 AND 25999 THEN 'Monto de Q.25000.00 a Q.25999.00'
         WHEN cremi.NCapDes BETWEEN 26000 AND 26999 THEN 'Monto de Q.26000.00 a Q.26999.00'
         WHEN cremi.NCapDes BETWEEN 27000 AND 27999 THEN 'Monto de Q.27000.00 a Q.27999.00'
         WHEN cremi.NCapDes BETWEEN 28000 AND 28999 THEN 'Monto de Q.28000.00 a Q.28999.00'
         WHEN cremi.NCapDes BETWEEN 29000 AND 29999 THEN 'Monto de Q.29000.00 a Q.29999.00'
         WHEN cremi.NCapDes >= 30000 THEN 'Monto de Q.30000.00 o más'
         ELSE 'Sin Clasificar'
     END AS rango_monto,
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
LEFT JOIN {$db_name_general}.`tb_credito` tcred ON tcred.abre = cremi.CtipCre
LEFT JOIN tb_garantias_creditos garcre ON garcre.id_cremcre_meta = cremi.ccodcta
LEFT JOIN cli_garantia clgar ON garcre.id_garantia = clgar.idGarantia
LEFT JOIN tb_municipios muni ON muni.id = cli.id_muni_reside
LEFT JOIN {$db_name_general}.tb_tiposgarantia tipgar ON clgar.idTipoGa = tipgar.id_TiposGarantia
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven,
    COUNT(IF(cflag = 0 AND cflag IS NOT NULL, 1, NULL)) AS cflag, 
    SUM(nintere) AS sum_nintere 
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
    SELECT ccodcta, SUM(KP) AS sum_KP, MAX(dfecpro) AS dfecpro_ult, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? {$filfondo} {$filagencia} {$filasesor} {$filregion} {$status} 
GROUP BY cremi.CCODCTA ORDER BY cremi.NCapDes;";

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
        $mensaje = "No se encontraron datos para la consulta especificada";
        $status = 0;
    } else {
        $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
                                    WHERE ag.id_agencia=" . $_SESSION['id_agencia']);

        if (empty($info)) {
            $mensaje = "Institucion asignada a la agencia no encontrada";
            $status = 0;
        } else {
            $status = 1;
        }
    }
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = uniqid('ERR_');
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

// Agrupar datos por rango de montos y calcular totales globales
$rangos = [];
$total_cantcreditos = 0;
$capital_global = 0;
$mora_global = 0;
$total_cremorosos = 0;

foreach ($result as $fila) {
    // Calcular saldos para este crédito
    $monto = $fila["NCapDes"];
    $cappag = $fila["cappag"];
    $capcalafec = $fila["capcalafec"];
    $rango = $fila['rango_monto'] ?? 'Sin Clasificar';

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

    // Agrupar por rango de montos
    if (!isset($rangos[$rango])) {
        $rangos[$rango] = [
            'rango' => $rango,
            'cantidad_datos' => 0,
            'cantidad_Ncapdes' => 0,
            'suma_mora' => 0,
            'cantidad_mora' => 0
        ];
    }

    $rangos[$rango]['cantidad_datos']++;
    $rangos[$rango]['cantidad_Ncapdes'] += $salcap;
    $rangos[$rango]['suma_mora'] += $capmora;
    if ($capmora > 0) {
        $rangos[$rango]['cantidad_mora']++;
    }
}

// Convertir array asociativo a array indexado para la función PDF
$data_agrupada = array_values($rangos);

switch ($tipo) {
    case 'xlsx';
        printxls($result, $titlereport, $archivo[0], $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
        printpdf($data_agrupada, [$titlereport], $info, $total_cantcreditos, $capital_global, $mora_global, $total_cremorosos, count($rangos), $mostrarRegionCol, $regionNombre);
}

//funcion para generar pdf
function printpdf($registro, $datos, $info, $total_cantcreditos, $capital_global, $mora_global, $total_cremorosos, $total_rangos, $mostrarRegionCol = false, $regionNombre = '')
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
            $this->Cell(0, 5, 'CLASIFICACION DE LOS CREDITOS POR RANGO DE MONTOS' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);

            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;

            $this->Cell($ancho_linea * 2, 5, $this->mostrarRegionCol ? 'REGION' : ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, 'RANGO DE MONTOS', 'B', 0, 'L');
            $this->Cell($ancho_linea + 10, 5, 'NO. DE CREDITOS', 'B', 0, 'C');
            $this->Cell($ancho_linea + 5, 5, 'SALDO CAPITAL', 'B', 0, 'C');
            $this->Cell($ancho_linea + 5, 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea + 5, 5, 'CREDITOS MOROSOS', 'B', 0, 'R');
            $this->Cell($ancho_linea + 15, 5, 'SALDO CAPITAL EN MORA', 'B', 0, 'R');
            $this->Cell($ancho_linea + 10, 5, 'PORCENTAJE', 'B', 0, 'R');
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

    // Imprimir datos agrupados por rango de montos
    foreach ($registro as $rango) {
        $nombre = Utf8::decode($rango["rango"] ?? 'Sin rango');
        $cantidad = $rango["cantidad_datos"] ?? 0;
        $saldo_capital = $rango["cantidad_Ncapdes"] ?? 0;
        $creditos_morosos = $rango["cantidad_mora"] ?? 0;
        $saldo_mora = $rango["suma_mora"] ?? 0;

        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, ($mostrarRegionCol ? Utf8::decode($regionNombre) : ' '), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, ' ' . ($nombre), 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea + 1, number_format($cantidad, 0, '.', ','), '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, number_format($saldo_capital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);

        if ($capital_global != 0) {
            $porcentaje_kap = ($saldo_capital / $capital_global) * 100;
            $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea + 1, number_format($porcentaje_kap, 2, '.', ',') . '%', '', 0, 'C', 0, '', 1, 0);
        } else {
            $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea + 1, 'N/A', '', 0, 'C', 0, '', 1, 0);
        }

        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($creditos_morosos, 0, '.', ','), '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea + 1, number_format($saldo_mora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);

        if ($mora_global != 0) {
            $porcentaje_mora = ($saldo_mora / $mora_global) * 100;
            $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea + 1, number_format($porcentaje_mora, 2, '.', ',') . '%', '', 0, 'C', 0, '', 1, 0);
        } else {
            $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea + 1, 'N/A', '', 0, 'C', 0, '', 1, 0);
        }

        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, ' ', '', 1, 'R', 0, '', 1, 0);
    }

    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 7);

    // Totales finales
    $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', 'T', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+40, $tamanio_linea, 'TOTAL RANGOS: ' . $total_rangos, "T", 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, number_format($total_cantcreditos, 0, '.', ','), "T", 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, number_format($capital_global, 2, '.', ','), "T", 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, '100.00%', "T", 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 , $tamanio_linea, number_format($total_cremorosos, 0, '.', ','), "T", 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea, number_format($mora_global, 2, '.', ','), "T", 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea, '100.00%', "T", 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea, ' ', "T", 0, 'R', 0, '', 1, 0);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Clasificacion por Rango de Montos",
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
        $sum1 += $clasdias["salcapital"][$keys[$fila]];
        $sum2 += $clasdias["salcapital"][$keys[$fila]];
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
    $activa->setTitle("ClasificacionRangoMontos");
    $activa->getColumnDimension("A")->setWidth(20);
    $activa->getColumnDimension("B")->setWidth(20);
    $activa->getColumnDimension("C")->setWidth(15);
    $activa->getColumnDimension("D")->setWidth(15);
    $activa->getColumnDimension("E")->setWidth(25);
    if ($mostrarRegionCol) {
        $activa->getColumnDimension("F")->setWidth(20);
    }

    $fila = 1;
    $i = 1;

    $rangoMax = $mostrarRegionCol ? 'F' : 'E';

    //fecha de generacion del reporte
    $activa->getStyle("A" . $i . ":" . $rangoMax . $i)->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->setCellValue('A' . $i, 'Reporte generado: ' . $hoy);
    $activa->mergeCells("A" . $i . ":C" . $i);
    $activa->setCellValue(($mostrarRegionCol ? 'F' : 'E') . $i, 'Usuario: ' . $usuario);

    $i = $i + 3;

    //cargamos el titulo del reporte
    $activa->getStyle("A" . $i . ":" . $rangoMax . $i)->getFont()->setSize($tamanioTabla)->setName($fuente_encabezado)->setBold(true);
    $activa->setCellValue('A' . $i, 'CLASIFICACION POR RANGO DE MONTOS' . $titlereport);
    $activa->mergeCells("A" . $i . ":" . $rangoMax . $i);

    $i = $i + 2;

    // Encabezados
    $activa->getStyle("A" . $i . ":" . $rangoMax . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValue('A' . $i, 'RANGO DE MONTOS');
    $activa->setCellValue('B' . $i, 'NO. DE CREDITOS');
    $activa->setCellValue('C' . $i, 'SALDO CAPITAL');
    $activa->setCellValue('D' . $i, 'CREDITOS MOROSOS');
    $activa->setCellValue('E' . $i, 'SALDO CAPITAL EN MORA');
    if ($mostrarRegionCol) {
        $activa->setCellValue('F' . $i, 'REGION');
    }
    $i++;

    // Datos
    foreach ($registro as $fil) {
        $activa->getStyle("A" . $i . ":" . $rangoMax . $i)->getFont()->setName($fuente);
        $activa->setCellValue('A' . $i, $fil["short_name"]);
        $activa->setCellValue('B' . $i, $fil["CCODCTA"]);
        $activa->setCellValue('C' . $i, $fil["NCapDes"]);
        $activa->setCellValue('D' . $i, $fil["cappag"]);
        $activa->setCellValue('E' . $i, $fil["intcal"]);
        if ($mostrarRegionCol) {
            $activa->setCellValue('F' . $i, $regionNombre);
        }
        $i++;
    }

    // Totales
    $total_registros = count($registro);
    $activa->getStyle("A" . $i . ":" . $rangoMax . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValue('A' . $i, "Número de créditos: " . $total_registros);
    $activa->mergeCells("A" . $i . ":" . $rangoMax . $i);

    $columnas = range('A', $rangoMax);
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
        'namefile' => "Clasificacion por Rango de Montos " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
