<?php

/**
 * Reporte de Cartera en Mora
 * Genera un reporte en PDF o Excel de la cartera en mora según los filtros seleccionados.
 * @deprecated este reporte ya no se usa. se reemplazo por Micro\Controllers\Reportes\Creditos\MoraController
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  include __DIR__.'/../../../../includes/Config/config.php';
  header('location: '.BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
  echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
  return;
}
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$puestouser = isset($_SESSION['puesto']) ? $_SESSION['puesto'] : '';

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

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$valida = $radios[1];
$tipo = $_POST["tipo"];

// =====================================================================
// VALIDACIÓN DE PERMISOS BACKEND - CONTROL DE ACCESO POR ROL
// Solo los puestos privilegiados pueden ver consolidado (todas las agencias)
// =====================================================================
$puestosPrivilegiados = array("ADM", "GER", "AUD", "CNT", "AAD");
$tienePermisoConsolidado = in_array($puestouser, $puestosPrivilegiados);

// Si el usuario NO tiene permiso de consolidado y envía "allg", forzar a su agencia
if (!$tienePermisoConsolidado && $valida === "allg") {
  $valida = "anyg";
  $radios[1] = "anyg";
  $selects[1] = $idagencia; // Forzar a la agencia del usuario
}

// Si es "anyg" pero no envió agencia válida, forzar la agencia del usuario
if ($valida === "anyg" && (empty($selects[1]) || $selects[1] == 0)) {
  $selects[1] = $idagencia;
}

// Información de origen para auditoría
$origen = [
  'id_usuario' => $idusuario,
  'puesto' => $puestouser,
  'id_agencia' => $idagencia,
  'tiene_permiso_consolidado' => $tienePermisoConsolidado,
  'fecha_generacion' => $hoy2
];

//validar la fecha que no sea mayor al dia de hoy
if ($inputs[0] > $hoy) {
  echo json_encode(['status' => 0, 'mensaje' => 'La fecha digitada no pueder ser mayor que la fecha actual', 'origen' => $origen]);
  return;
}

//validar la seleccion de fuente de Fondo
if ($radios[0] == "anyf") {
  if ($selects[0] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar un fuente de fondo', 'origen' => $origen]);
    return;
  }
}
//validacion de una agencia
if ($valida == "anyg") {
  if ($selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una agencia', 'origen' => $origen]);
    return;
  }
}

// =====================================================================
// FILTRO POR REGIÓN (nuevo) - NO reordena payload, solo lee al final
// =====================================================================
$regionRadio = $radios[2] ?? 'allregion';
$regionId = isset($selects[2]) ? (int)$selects[2] : 0;

if ($regionRadio === 'anyregion' && $regionId <= 0) {
  echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una región', 'origen' => $origen]);
  return;
}

//RANGO DE FECHAS
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

// Sufijo visible para indicar filtro aplicado
$regionLabel = '';
//FUENTE DE FONDO
// $fondo = ($radios[0] == "anyf") ? $selects[0] : "all";
// //AGENCIA
// $oficina = ($valida == "anyg" || $valida == "F0") ? $selects[1] : "all";


//FUENTE DE FONDOS
$filfondo = ($radios[0] == "anyf") ? " AND ffon.id=" . $selects[0] : "";

//AGENCIA
$filagencia = ($valida == "anyg" || $valida == "F0") ? " AND cremi.CODAgencia=" . $selects[1] : "";

//REGIÓN (se suma con AND, no reemplaza el filtro de agencia)
$filregion = ($regionRadio === 'anyregion' && $regionId > 0)
  ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . (int)$regionId . ")"
  : "";

$strquery = "SELECT
        ofi.id_agencia,
        cremi.CODAgencia,
        ofi.nom_agencia,
        cremi.CodAnal,
        CONCAT(usu.nombre, ' ', usu.apellido) AS analista,
        cremi.CCODCTA,
        cremi.CESTADO,
        prod.id_fondo AS id_fondos,
        ffon.descripcion AS nombre_fondo,
        prod.id AS id_producto,
        prod.descripcion AS nombre_producto,
        prod.tasa_interes AS tasa,
        prod.porcentaje_mora AS tasamora,
        cli.short_name,
        cli.date_birth,
        cli.genero,
        cli.estado_civil,
        cli.Direccion AS direccion,
        cli.tel_no1 AS tel1,
        cli.tel_no2 AS tel2,
        cli.no_identifica As dpi,
        cremi.DFecDsbls,
        cremi.NCapDes,
        IFNULL(ppg.dfecven, 0) AS fechaven,
        IFNULL(ppg.sum_nintere, 0) AS intcal,
        IFNULL(kar.dfecpro_ult, 0) AS fechaultpag,
        IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
        IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
        IFNULL(kar.sum_KP, 0) AS cappag,
        IFNULL(kar.sum_interes, 0) AS intpag,
        IFNULL(kar.sum_MORA, 0) AS morpag,
        IFNULL(kar.sum_AHOPRG_OTR, 0) AS otrpag,
        cre_dias_atraso(?, cremi.CCODCTA) AS todos,
        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso
    FROM cremcre_meta cremi
    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
    INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
    INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo
    INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
    INNER JOIN tb_agencia ofi ON ofi.cod_agenc = cremi.CODAgencia
    LEFT JOIN (
        SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(nintere) AS sum_nintere
        FROM Cre_ppg
        GROUP BY ccodcta
    ) AS ppg ON ppg.ccodcta = cremi.CCODCTA
    LEFT JOIN (
        SELECT ccodcta, MAX(dfecpro) AS dfecpro_ult, SUM(KP) AS sum_KP, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
        FROM CREDKAR
        WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
        GROUP BY ccodcta
    ) AS kar ON kar.ccodcta = cremi.CCODCTA
    LEFT JOIN (
        SELECT ccodcta, SUM(ncapita) AS sum_ncapita, SUM(nintere) AS sum_nintere
        FROM Cre_ppg
        WHERE dfecven <= ?
        GROUP BY ccodcta
    ) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
    WHERE cremi.DFecDsbls <= ? AND (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 
    " . $filfondo . $filagencia . $filregion . "
    HAVING atraso > 0
    ORDER BY prod.id_fondo, ofi.id_agencia, cremi.CodAnal, prod.id, cremi.DFecDsbls;";

//INIT TRY
$showmensaje = false;
try {
  $database->openConnection();

  if ($regionRadio === 'anyregion' && $regionId > 0) {
    $reg = $database->getAllResults('SELECT nombre FROM cre_regiones WHERE id = ? LIMIT 1', [(int)$regionId]);
    $nombreRegion = $reg[0]['nombre'] ?? '';
    if ($nombreRegion !== '') {
      $regionLabel = ' / REGIÓN: ' . strtoupper($nombreRegion);
    }
  }

  $result = $database->getAllResults($strquery, [$filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha]);
  if (empty($result)) {
    $showmensaje = true;
    throw new Exception("No se encontraron registros");
  }
  // $result = convert_to_utf8($result);

  $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
  // $info = convert_to_utf8($info);
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

// Identificador visible del filtro de región (para PDF/Excel)
$titlereport .= $regionLabel;

if ($status == 0) {
  $opResult = array('status' => 0, 'mensaje' => $mensaje, 'origen' => $origen);
  echo json_encode($opResult);
  return;
}
//FIN TRY

// echo json_encode(['status' => 0, 'mensaje' => $strquery]);
//     return;

$data[] = [];
$j = 0;
foreach ($result as $fil) {
  $diasatr = $fil["atraso"];
  if ($diasatr > 0) {
    $data[$j] = $fil;

    $todos = $fil['todos'];
    $filasaux = substr($todos, 0, -1);
    $filas = explode("#", $filasaux);

    $intmora = 0;
    for ($i = 0; $i < count($filas); $i++) {
      $data[$j]["atrasadas"][$i] = explode("_", $filas[$i]);
      // $intmora += ($data[$j]["atrasadas"][$i][1] * (($data[$j]['tasamora'] / 100) / 365) * $diasatr);
      $moracalculada = $data[$j]["atrasadas"][$i][5];
      $intmora += $moracalculada;
    }
    $data[$j]["intmora"] = $intmora;
    unset($data[$j]["todos"]);
    unset($data[$j][27]);

    /**
     * SI INTMORA NO ES MAYOR A CER0
     * NO INCLUIR EN EL REPORTE
     */
    if ($intmora <= 0) {
      unset($data[$j]);
      continue;
    }

    $j++;
  }
}

switch ($tipo) {
  case 'xlsx';
    printxls($data, $titlereport, $idusuario, $valida, $origen);
    break;
  case 'pdf':
    printpdf($data, [$titlereport], $info, [$idusuario, $valida], $origen);
    break;
  case 'show':
    showresults($data, $origen);
    break;
}
function showresults($registro, $origen)
{
  $valores[] = [];
  $i = 0;
  foreach ($registro as $fila) {
    $fila["DFecDsbls"] = date("d-m-Y", strtotime($fila["DFecDsbls"]));
    $fila["fechaultpag"] = ($fila["fechaultpag"] != "0") ? date("d-m-Y", strtotime($fila["fechaultpag"])) : "-";
    $fila["fechaven"] = ($fila["fechaven"] != "0") ? date("d-m-Y", strtotime($fila["fechaven"])) : "-";

    $monto = $fila["NCapDes"];
    $intcal = $fila["intcal"];
    $capcalafec = $fila["capcalafec"];
    $intcalafec = $fila["intcalafec"];
    $cappag = $fila["cappag"];
    $intpag = $fila["intpag"];

    //SALDO DE CAPITAL A LA FECHA
    $salcap = ($monto - $cappag);
    $salcap = ($salcap > 0) ? $salcap : 0;
    $fila["salcap"] = number_format($salcap, 2);

    //SALDO DE INTERES A LA FECHA
    $salint = ($intcal - $intpag);
    $salint = ($salint > 0) ? $salint : 0;
    $fila["salint"] = number_format($salint, 2);

    //CAPITAL EN MORA A LA FECHA
    $capmora = $capcalafec - $cappag;
    $capmora = ($capmora > 0) ? $capmora : 0;
    $fila["capenmora"] = number_format($capmora, 2);

    //INTERES ATRASADO A LA FECHA
    $intatrasado = $intcalafec - $intpag;
    $intatrasado = ($intatrasado > 0) ? $intatrasado : 0;
    $fila["intatrasado"] = number_format($intatrasado, 2);

    $valores[$i] = $fila;
    $i++;
  }

  $keys = ["nom_agencia", "analista", "CCODCTA", "short_name", "DFecDsbls", "fechaven", "NCapDes", "salcap", "capenmora", "intatrasado", "intmora", "atraso"];
  $encabezados = ["Agencia", "Asesor", "Cuenta", "Nombre Cliente", "Fec. Inicio", "Fec. Vence", "Monto", "Saldo kp", "Kp. Mora", "Int. Corr.", "Mora", "Días"];

  $opResult = array(
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'data' => $valores,
    'keys' => $keys,
    'encabezados' => $encabezados,
    'origen' => $origen, // Información de auditoría
  );
  echo json_encode($opResult);
  return;
}
//funciones de cada reporte
function printpdf($registro, $datos, $info, $xtra, $origen)
{
  /*$oficina="Coban";$institucion="Cooperativa Integral...";$direccionins="zona 1";$emailins="fape@gmail.com";$telefonosins="";$nitins="";$rutalogomicro="";$rutalogoins="";  */
  $oficina = Utf8::decode($info[0]["nom_agencia"]);
  $institucion = Utf8::decode($info[0]["nomb_comple"]);
  $direccionins = Utf8::decode($info[0]["muni_lug"]);
  $emailins = $info[0]["emai"];
  $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
  $nitins = $info[0]["nit"];
  $rutalogomicro = "../../../../includes/img/logomicro.png";
  $rutalogoins = "../../../.." . $info[0]["log_img"];
  $usuario = $xtra[0];
  $valida = $xtra[1];

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
    public $usuario;
    public $valida;

    public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $usuario, $valida)
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
      $this->usuario = $usuario;
      $this->valida = $valida;
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
      $this->Cell(0, 5, \Micro\Generic\Utf8::decode('CARTERA EN MORA' . $this->datos[0]), 0, 1, 'C', true);
      $this->Ln(2);
      //Color de encabezado de lista
      $this->SetFillColor(555, 255, 204);
      //TITULOS DE ENCABEZADO DE TABLA
      $this->SetFont($fuente, 'B', 7);
      $ancho_linea = 20;

      $this->Cell($ancho_linea, 5, 'CREDITO', 'B', 0, 'L');
      $this->Cell($ancho_linea * 2 + 15, 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'L');
      $this->Cell($ancho_linea, 5, 'OTORGAMIENTO', 'B', 0, 'C');
      $this->Cell($ancho_linea, 5, 'VENCIMIENTO', 'B', 0, 'C');
      $this->Cell($ancho_linea, 5, 'ULTIMO PAGO', 'B', 0, 'C');
      $this->Cell($ancho_linea, 5, 'MONTO', 'B', 0, 'C');
      $this->Cell($ancho_linea, 5, 'SAL. CAP.', 'B', 0, 'R');
      $this->Cell($ancho_linea, 5, 'CAP.MORA', 'B', 0, 'C');
      $this->Cell($ancho_linea, 5, 'INT. CORR.', 'B', 0, 'R');
      $this->Cell($ancho_linea, 5, 'INT. MORA', 'B', 0, 'R');
      $this->Cell($ancho_linea, 5, 'SALDO DEUDOR', 'B', 0, 'R');
      $this->Cell($ancho_linea / 2, 5, 'ATRASO', 'B', 1, 'R');
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
  $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $usuario, $valida);
  $pdf->AliasNbPages();
  $pdf->AddPage();
  $fuente = "Courier";
  $tamanio_linea = 3;
  $ancho_linea2 = 20;
  $pdf->SetFont($fuente, '', 7);
  $auxfondo = null;
  $auxagencia = null;
  $auxanalista = null;

  /**PARA TENER LOS TOTALES */
  $sum_montos = 0;
  $sum_cappag = 0;
  $sum_intpag = 0;
  $sum_morpag = 0;
  $sum_salcap = 0;
  $sum_salint = 0;
  $sum_capmora = 0;
  $sumintatrasado = 0;
  $sumintmora = 0;
  $fila = 0;
  $contador = 0;
  while ($fila < count($registro)) {
    $idagencia = $registro[$fila]["id_agencia"];
    $nomagencia =  Utf8::decode($registro[$fila]["nom_agencia"]);
    $codanal = $registro[$fila]["CodAnal"];
    $nomanal =  Utf8::decode($registro[$fila]["analista"]);

    /* NEGROY,  AGREGAR VALIDACION * PARA REALIZAR SOLO IMPRESION POR EL USUARIO	 */
    if ($usuario == $codanal || $valida != "F0") {
      /* NEGROY,  AGREGAR VALIDACION * PARA REALIZAR SOLO IMPRESION POR EL USUARIO	 */
      $cuenta = $registro[$fila]["CCODCTA"];
      $nombre =  Utf8::decode($registro[$fila]["short_name"]);
      $genero =  $registro[$fila]["genero"];
      $fechades = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
      $fechaven = $registro[$fila]["fechaven"];
      $fechaven = ($fechaven != "0") ? date("d-m-Y", strtotime($fechaven)) : "-";

      $fultpag = $registro[$fila]["fechaultpag"];
      $fultpag = ($fultpag != "0") ? date("d-m-Y", strtotime($fultpag)) : "-";

      $monto = $registro[$fila]["NCapDes"];
      $intcal = $registro[$fila]["intcal"];
      $capcalafec = $registro[$fila]["capcalafec"];
      $intcalafec = $registro[$fila]["intcalafec"];
      $cappag = $registro[$fila]["cappag"];
      $intpag = $registro[$fila]["intpag"];
      $morpag = $registro[$fila]["morpag"];
      $diasatr = $registro[$fila]["atraso"];
      $idfondos = $registro[$fila]["id_fondos"];
      $nombrefondos = $registro[$fila]["nombre_fondo"];
      $idproducto = $registro[$fila]["id_producto"];
      $nameproducto = $registro[$fila]["nombre_producto"];
      $intmora = $registro[$fila]["intmora"];

      //SALDO DE CAPITAL A LA FECHA
      $salcap = ($monto - $cappag);
      $salcap = ($salcap > 0) ? $salcap : 0;

      //SALDO DE INTERES A LA FECHA
      $salint = ($intcal - $intpag);
      $salint = ($salint > 0) ? $salint : 0;

      //CAPITAL EN MORA A LA FECHA
      $capmora = $capcalafec - $cappag;
      $capmora = ($capmora > 0) ? $capmora : 0;

      //INTERES ATRASADO A LA FECHA
      $intatrasado = $intcalafec - $intpag;
      $intatrasado = ($intatrasado > 0) ? $intatrasado : 0;

      $registro[$fila]["salcapital"] = $salcap;
      $registro[$fila]["salintere"] = $salint;
      $registro[$fila]["capmora"] = $capmora;
      $registro[$fila]["intatrasado"] = $intatrasado;

      //TITULO FONDO
      if ($idfondos != $auxfondo) {
        $pdf->Ln(2);
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->Cell($ancho_linea2 * 2, 5, ' FUENTE DE FONDOS: ', '', 0, 'R');
        $pdf->Cell(0, 5, strtoupper($nombrefondos), '', 1, 'L');
        $pdf->SetFont($fuente, '', 7);
        $auxfondo = $idfondos;
      }
      //TITULO AGENCIA
      if ($idagencia != $auxagencia) {
        $pdf->Ln(2);
        $pdf->SetFont($fuente, 'B', 8);
        $pdf->Cell($ancho_linea2 * 2, 5, 'AGENCIA : ', '', 0, 'R');
        $pdf->Cell(0, 5, strtoupper(($nomagencia)), '', 1, 'L');
        $pdf->SetFont($fuente, '', 7);
        $auxagencia = $idagencia;
        $auxanalista = null;
      }
      //TITULO EJECUTIVO
      if ($codanal != $auxanalista) {
        $pdf->SetFont($fuente, 'BI', 7);
        $pdf->Cell($ancho_linea2 * 2, 5, $codanal . ' EJECUTIVO : ', '', 0, 'R');
        $pdf->Cell(0, 5, strtoupper(($nomanal)), '', 1, 'L');
        $pdf->SetFont($fuente, '', 7);
        $auxanalista = $codanal;
      }

      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2 * 2 + 15, $tamanio_linea + 1, strtoupper(($nombre)), '', 0, 'L', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fechades, '', 0, 'C', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fechaven, '', 0, 'C', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fultpag, '', 0, 'C', 0, '', 1, 0);

      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($monto, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($salcap, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($capmora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($intatrasado, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($intmora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format(($capmora + $intatrasado + $intmora), 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
      $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, $diasatr, '', 1, 'R', 0, '', 1, 0);

      /** SUMAS DE LOS MONSTOS PARA TOTALES  */
      $sum_cappag += $cappag;
      $sum_montos += $monto;
      $sum_intpag += $intpag;
      $sum_morpag += $morpag;
      $sum_salcap += $salcap;
      $sum_salint += $salint;
      $sum_capmora += $capmora;
      $sumintatrasado += $intatrasado;
      $sumintmora += $intmora;
      $contador++;
    }  // FUNCION PARA SABER SI SE IMPRIME O NELSON
    $fila++;
  }
  $pdf->Ln(2);
  $pdf->SetFont($fuente, 'B', 7);

  /* $sum_montos=array_sum(array_column($registro,"NCapDes"));$sum_cappag=array_sum(array_column($registro,"cappag"));$sum_intpag=array_sum(array_column($registro,"intpag"));$sum_morpag=array_sum(array_column($registro,"morpag"));$sum_salcap=array_sum(array_column($registro,"salcapital"));$sum_salint=array_sum(array_column($registro,"salintere"));$sum_capmora=array_sum(array_column($registro,"capmora"));$sumintatrasado=array_sum(array_column($registro,"intatrasado"));$sumintmora=array_sum(array_column($registro,"intmora"));*/

  $pdf->CellFit($ancho_linea2 * 6 + 15, $tamanio_linea + 1, 'Numero de Creditos: ' . $contador, 'T', 0, 'C', 0, '', 1, 0);
  $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_montos, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
  $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_salcap, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
  $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_capmora, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
  $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sumintatrasado, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
  $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sumintmora, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
  $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_capmora + $sumintatrasado + $sumintmora, 2, '.', ','), 'T', 1, 'R', 0, '', 1, 0);

  ob_start();
  $pdf->Output();
  $pdfData = ob_get_contents();
  ob_end_clean();

  $opResult = array(
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile' => "Cartera en mora",
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData),
    'origen' => $origen // Información de auditoría
  );
  echo json_encode($opResult);
}
function printxls($registro, $titlereport, $usuario, $valida, $origen)
{
    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Cartera en mora");

    // Insertamos la fecha y el usuario
    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);

    // Formato para fecha y usuario (rango de A a S)
    $activa->getStyle("A1:S1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle("A2:S2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle("A1:S1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A2:S2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Formato del encabezado de título (rango de A a S)
    $activa->getStyle("A4:S4")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $activa->getStyle("A5:S5")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $activa->getStyle("A4:S4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A5:S5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $activa->setCellValue("A4", "REPORTE");
    $activa->setCellValue("A5", strtoupper("CARTERA EN MORA " . $titlereport));

    // Encabezado de la tabla con la columna DPI agregada al final (columna S)
    $encabezado_tabla = [
        "FONDO", "AGENCIA", "EJECUTIVO", "CRÉDITO", "NOMBRE DEL CLIENTE",
        "DIRECCION", "TEL 1", "TEL 2", "OTORGAMIENTO", "VENCIMIENTO",
        "ÚLTIMO PAGO", "MONTO DESEMBOLSADO", "SALDO CAPITAL", "CAPITAL EN MORA",
        "INTERES CORRIENTE", "INTERES EN MORA", "SALDO DEUDOR", "ATRASO", "DPI"
    ];
    $activa->fromArray($encabezado_tabla, null, 'A7')
           ->getStyle('A7:S7')->getFont()->setName($fuente)->setBold(true);

    // Combinación de celdas para los títulos (de A a S)
    $activa->mergeCells('A1:S1');
    $activa->mergeCells('A2:S2');
    $activa->mergeCells('A4:S4');
    $activa->mergeCells('A5:S5');

    $fila = 0;
    $i = 8;
    // Variables de suma para totales
    $sum_montos = 0;
    $sum_cappag = 0;
    $sum_intpag = 0;
    $sum_morpag = 0;
    $sum_salcap = 0;
    $sum_salint = 0;
    $sum_capmora = 0;
    $sumintatrasado = 0;
    $sumintmora = 0;
    $contador = 0;

    while ($fila < count($registro)) {
        $idagencia  = $registro[$fila]["id_agencia"];
        $nomagencia = $registro[$fila]["nom_agencia"];
        $codanal    = $registro[$fila]["CodAnal"];
        $nomanal    = $registro[$fila]["analista"];

        // Validación para imprimir solo si el usuario corresponde o si $valida no es "F0"
        if ($usuario == $codanal || $valida != "F0") {

            $cuenta     = $registro[$fila]["CCODCTA"];
            $nombre     = $registro[$fila]["short_name"];
            $genero     = $registro[$fila]["genero"];
            $direccion  = $registro[$fila]["direccion"];
            $tel1       = $registro[$fila]["tel1"];
            $tel2       = $registro[$fila]["tel2"];
            $fechades   = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
            $fechaven   = $registro[$fila]["fechaven"];
            $fechaven   = ($fechaven != "0") ? date("d-m-Y", strtotime($fechaven)) : "-";
            $fultpag    = $registro[$fila]["fechaultpag"];
            $fultpag    = ($fultpag != "0") ? date("d-m-Y", strtotime($fultpag)) : "-";
            $monto      = $registro[$fila]["NCapDes"];
            $intcal     = $registro[$fila]["intcal"];
            $capcalafec = $registro[$fila]["capcalafec"];
            $intcalafec = $registro[$fila]["intcalafec"];
            $cappag     = $registro[$fila]["cappag"];
            $intpag     = $registro[$fila]["intpag"];
            $morpag     = $registro[$fila]["morpag"];
            $diasatr    = $registro[$fila]["atraso"];
            $idfondos   = $registro[$fila]["id_fondos"];
            $nombrefondos = $registro[$fila]["nombre_fondo"];
            $idproducto   = $registro[$fila]["id_producto"];
            $nameproducto = $registro[$fila]["nombre_producto"];
            $intmora      = $registro[$fila]["intmora"];

            // Nuevo campo DPI: se extrae el valor del array (campo "dpi") y se controla si no existe
            $dpi = isset($registro[$fila]["dpi"]) ? $registro[$fila]["dpi"] : "";

            // Cálculos de los saldos
            $salcap = ($monto - $cappag);
            $salcap = ($salcap > 0) ? $salcap : 0;
            $salint = ($intcal - $intpag);
            $salint = ($salint > 0) ? $salint : 0;
            $capmora = $capcalafec - $cappag;
            $capmora = ($capmora > 0) ? $capmora : 0;
            $intatrasado = $intcalafec - $intpag;
            $intatrasado = ($intatrasado > 0) ? $intatrasado : 0;

            // Se asignan los saldos al array de registro para totales
            $registro[$fila]["salcapital"] = $salcap;
            $registro[$fila]["salintere"]   = $salint;
            $registro[$fila]["capmora"]     = $capmora;
            $registro[$fila]["intatrasado"] = $intatrasado;

            // Se asignan los valores a cada celda (de la A a la R)
            $activa->setCellValueExplicit('A' . $i, strtoupper($nombrefondos), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValueExplicit('B' . $i, strtoupper($nomagencia), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValueExplicit('C' . $i, strtoupper($nomanal), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValueExplicit('D' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('E' . $i, strtoupper($nombre));
            $activa->setCellValue('F' . $i, $direccion);
            $activa->setCellValue('G' . $i, $tel1);
            $activa->setCellValue('H' . $i, $tel2);
            $activa->setCellValue('I' . $i, $fechades);
            $activa->setCellValue('J' . $i, $fechaven);
            $activa->setCellValue('K' . $i, $fultpag);
            $activa->setCellValue('L' . $i, $monto);
            $activa->setCellValue('M' . $i, $salcap);
            $activa->setCellValue('N' . $i, $capmora);
            $activa->setCellValue('O' . $i, $intatrasado);
            $activa->setCellValue('P' . $i, $intmora);
            $activa->setCellValue('Q' . $i, ($capmora + $intatrasado + $intmora));
            $activa->setCellValueExplicit('R' . $i, $diasatr, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            
            // Columna DPI: Se asigna como texto para evitar formato científico
            $activa->setCellValueExplicit('S' . $i, $dpi, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Se aplica la fuente a la fila completa (de A a S)
            $activa->getStyle("A" . $i . ":S" . $i)->getFont()->setName($fuente);

            // Actualización de sumas para totales
            $sum_cappag     += $registro[$fila]["cappag"];
            $sum_montos     += $registro[$fila]["NCapDes"];
            $sum_intpag     += $registro[$fila]["intpag"];
            $sum_morpag     += $registro[$fila]["morpag"];
            $sum_salcap     += $registro[$fila]["salcapital"];
            $sum_salint     += $registro[$fila]["salintere"];
            $sum_capmora    += $registro[$fila]["capmora"];
            $sumintatrasado += $registro[$fila]["intatrasado"];
            $sumintmora     += $registro[$fila]["intmora"];

            $contador++;

            $i++;
        }
        $fila++;
    }
    $i++;

    // Fila de totales (rango actualizado de A a S)
    $activa->getStyle("A" . $i . ":S" . $i)
           ->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValueExplicit('A' . $i, "Número de créditos: " . $contador, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->mergeCells("A" . $i . ":H" . $i);
    $activa->setCellValue('L' . $i, $sum_montos);
    $activa->setCellValue('M' . $i, $sum_salcap);
    $activa->setCellValue('N' . $i, $sum_capmora);
    $activa->setCellValue('O' . $i, $sumintatrasado);
    $activa->setCellValue('P' . $i, $sumintmora);
    $activa->setCellValue('Q' . $i, ($sum_capmora + $sumintatrasado + $sumintmora));

    $activa->getStyle("A" . $i . ":S" . $i)
           ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
           ->getStartColor()->setARGB('FFFF00');

    // Autoajuste de columnas (de A a S)
    $columnas = range('A', 'S');
    foreach ($columnas as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(TRUE);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status'   => 1,
        'mensaje'  => 'Reporte generado correctamente',
        'namefile' => "CARTERA EN MORA " . $titlereport,
        'tipo'     => "vnd.ms-excel",
        'data'     => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData),
        'origen'   => $origen // Información de auditoría
    );
    echo json_encode($opResult);
    exit;
}
