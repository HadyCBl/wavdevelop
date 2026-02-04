<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
    exit;
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    exit;
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

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//[[`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[ $idusuario; ]]
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

if (!validateDate($inputs[0], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    exit;
}

if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar fuente de fondos']);
    exit;
}
if ($radios[3] == "anyasesor" && $selects[2] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Asesor']);
    exit;
}
// Validación de región
if (isset($radios[4]) && $radios[4] == "anyregion" && (empty($selects[3]) || $selects[3] == 0)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    exit;
}

//*****************ARMANDO LA CONSULTA**************
$condi = "";
//RANGO DE FECHAS
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = ($radios[3] == "anyasesor") ? " AND cremi.CodAnal =" . $selects[2] : "";
//REGIÓN
$filregion = (isset($radios[4]) && $radios[4] == "anyregion" && !empty($selects[3])) 
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $selects[3] . ")" 
    : "";
//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");
//consulta tabla de resumen de cartera

$strquery_resuemn = "
SELECT
    cremi.CODAgencia AS codigo_agencia,
    ffon.descripcion AS nombre_fondo,
    SUM(cremi.NCapDes) AS monto_otorgado,
    SUM(cremi.NCapDes - IFNULL(kar.sum_KP, 0)) AS saldo,
    SUM(
        IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 1 AND 30,
           cremi.NCapDes - IFNULL(kar.sum_KP, 0),
           0)
    ) AS mora_1_30,
    SUM(
        IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 31 AND 60,
           cremi.NCapDes - IFNULL(kar.sum_KP, 0),
           0)
    ) AS mora_31_60,
    SUM(
        IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 61 AND 90,
           cremi.NCapDes - IFNULL(kar.sum_KP, 0),
           0)
    ) AS mora_61_90,
    SUM(
        IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 91 AND 180,
           cremi.NCapDes - IFNULL(kar.sum_KP, 0),
           0)
    ) AS mora_91_180,
    SUM(
        IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) > 180,
           cremi.NCapDes - IFNULL(kar.sum_KP, 0),
           0)
    ) AS mora_mas_180
FROM cremcre_meta cremi
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP
    FROM CREDKAR
    WHERE dfecpro <= '$filtrofecha'
      AND cestado != 'X'
      AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND cremi.DFecDsbls <= '$filtrofecha'
  $filfondo
  $filagencia
  $filasesor
  $filregion
  $status
GROUP BY cremi.CODAgencia, ffon.descripcion
ORDER BY cremi.CODAgencia, ffon.descripcion;
";
//resumen de morosidad en asociados
$status2 = ""; // O alguna condición alternativa que no involucre kar.sum_KP
$strquerry_regitsros = "
SELECT
    ffon.descripcion AS nombre_fondo,
    COUNT(IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) = 0, 1, NULL)) AS al_dia,
    COUNT(IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 1 AND 30, 1, NULL)) AS mora_1_30,
    COUNT(IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 31 AND 60, 1, NULL)) AS mora_31_60,
    COUNT(IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 61 AND 90, 1, NULL)) AS mora_61_90,
    COUNT(IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) BETWEEN 91 AND 180, 1, NULL)) AS mora_91_180,
    COUNT(IF(cre_dias_atraso('$filtrofecha', cremi.CCODCTA) > 180, 1, NULL)) AS mora_mas_180,
    COUNT(*) AS total
FROM cremcre_meta cremi
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND cremi.DFecDsbls <= '$filtrofecha'
  $filfondo
  $filagencia
  $filasesor
  $filregion
  $status2
GROUP BY ffon.descripcion
ORDER BY ffon.descripcion;
";

//consulta tabla de resumen de cartera
$strquerry_03 = "SELECT 
    'Genero' AS categoria,
    actividad.Titulo AS descripcion,
    SUM(IF(cli.genero = 'M', 1, 0)) AS hombres,
    SUM(IF(cli.genero = 'F', 1, 0)) AS mujeres,
    COUNT(*) AS total,
    SUM(IF(cli.genero = 'M', cremi.NCapDes, 0)) AS monto_hombres,
    SUM(IF(cli.genero = 'F', cremi.NCapDes, 0)) AS monto_mujeres,
    SUM(cremi.NCapDes) AS monto_total,
    NULL AS plazo,
    NULL AS saldo,
    NULL AS num_asociados
FROM cremcre_meta cremi
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP
    FROM CREDKAR
    WHERE dfecpro <= '$filtrofecha'
      AND cestado != 'X'
      AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo
LEFT JOIN $db_name_general.tb_ActiEcono actividad ON actividad.id_ActiEcono = cremi.ActoEcono
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND cremi.DFecDsbls <= '$filtrofecha'
  $filfondo
  $filagencia
  $filasesor
  $filregion
 $status
GROUP BY actividad.Titulo UNION ALL SELECT 
    'Plazo' AS categoria,
    CASE
        WHEN cremi.DFecDsbls + INTERVAL 6 MONTH >= '$filtrofecha' THEN 'Hasta 6 meses'
        WHEN cremi.DFecDsbls + INTERVAL 12 MONTH >= '$filtrofecha' THEN '7 - 12 meses'
        WHEN cremi.DFecDsbls + INTERVAL 18 MONTH >= '$filtrofecha' THEN '13 - 18 meses'
        WHEN cremi.DFecDsbls + INTERVAL 24 MONTH >= '$filtrofecha' THEN '19 - 24 meses'
        ELSE 'Mas de 24 meses'
    END AS descripcion,
    NULL AS hombres,
    NULL AS mujeres,
    NULL AS total,
    NULL AS monto_hombres,
    NULL AS monto_mujeres,
    NULL AS monto_total,
    CASE
        WHEN cremi.DFecDsbls + INTERVAL 6 MONTH >= '$filtrofecha' THEN 'Hasta 6 meses'
        WHEN cremi.DFecDsbls + INTERVAL 12 MONTH >= '$filtrofecha' THEN '7 - 12 meses'
        WHEN cremi.DFecDsbls + INTERVAL 18 MONTH >= '$filtrofecha' THEN '13 - 18 meses'
        WHEN cremi.DFecDsbls + INTERVAL 24 MONTH >= '$filtrofecha' THEN '19 - 24 meses'
        ELSE 'Mas de 24 meses'
    END AS plazo,
    SUM(cremi.NCapDes - IFNULL(kar.sum_KP, 0)) AS saldo,
    COUNT(*) AS num_asociados
FROM cremcre_meta cremi
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP
    FROM CREDKAR
    WHERE dfecpro <= '$filtrofecha'
      AND cestado != 'X'
      AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND cremi.DFecDsbls <= '$filtrofecha'
  $filfondo
  $filagencia
  $filasesor
  $filregion
  $status
GROUP BY plazo
ORDER BY categoria, descripcion;
";
//inicio de trycatch
$showmensaje = false;

try {
    $database->openConnection();

    // Primera consulta: Resumen de cartera
    $result = $database->getAllResults($strquery_resuemn, []);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros para el resumen de cartera.");
    }

    // Segunda consulta: Resumen de morosidad en asociados
    $result_registros = $database->getAllResults($strquerry_regitsros, []);
    if (empty($result_registros)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros para la morosidad en asociados.");
    }

    // Tercera consulta: Género y plazos
    $result_genero_plazos = $database->getAllResults($strquerry_03, []);
    if (empty($result_genero_plazos)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros para género y plazos.");
    }

    // Consulta para información de la institución
    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institución asignada a la agencia no encontrada.");
    }

    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error ($codigoError).";
    $status = 0;
} finally {
    $database->closeConnection();
}

// Manejo de error si alguna consulta falla
if ($status == 0) {
    header('Content-Type: application/json');
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    exit;
}

switch ($tipo) {
    case 'xlsx';
        printxls($result, $result_registros, $result_genero_plazos, $titlereport, $archivo[0]);
        break;
    case 'pdf':
        printpdf($result, $result_registros, $result_genero_plazos, [$titlereport], $info);
        break;
}

//funcion para generar pdf
function printpdf($registro, $result_registros, $result_genero_plazos, $datos, $info)
{
    $oficina = decode_utf8($info[0]["nom_agencia"] ?? '');
    $institucion = decode_utf8($info[0]["nomb_comple"] ?? '');
    $direccionins = decode_utf8($info[0]["muni_lug"] ?? '');
    $emailins = $info[0]["emai"] ?? '';
    $telefonosins = trim(($info[0]["tel_1"] ?? '') . '   ' . ($info[0]["tel_2"] ?? ''));
    $nitins = $info[0]["nit"] ?? '';
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = isset($info[0]["log_img"]) ? "../../../.." . $info[0]["log_img"] : '';

    class PDF extends FPDF
    {
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $datos;
        public $isSecondPage = false; // Variable para controlar si es la segunda página

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos)
        {
            parent::__construct();
            $this->institucion = (string) $institucion;
            $this->pathlogo = (string) $pathlogo;
            $this->pathlogoins = (string) $pathlogoins;
            $this->oficina = (string) $oficina;
            $this->direccion = (string) $direccion;
            $this->email = (string) $email;
            $this->telefono = (string) $telefono;
            $this->nit = (string) $nit;
            $this->datos = (array) $datos;
            $this->DefOrientation = 'L';
        }

        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            if (!empty($this->pathlogoins)) {
                $this->Image($this->pathlogoins, 10, 13, 33);
            }
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->Ln(10);
            $this->SetFont($fuente, 'B', 10);
            $this->SetFillColor(204, 229, 255);
            if ($this->isSecondPage) {
                $this->Cell(0, 5, 'MOROSIDAD EN ASOCIADOS', 0, 1, 'C', true);
                $this->Ln(2);
                $this->SetFillColor(555, 255, 204);
                $this->SetFont($fuente, 'B', 7);
                $this->Cell(10, 5, 'No', 'B', 0, 'L');
                $this->Cell(40, 5, 'FUENTE DE FONDOS', 'B', 0, 'L');
                $this->Cell(20, 5, 'AL DIA', 'B', 0, 'R');
                $this->Cell(20, 5, '1-30 DIAS', 'B', 0, 'R');
                $this->Cell(20, 5, '31-60 DIAS', 'B', 0, 'R');
                $this->Cell(20, 5, '61-90 DIAS', 'B', 0, 'R');
                $this->Cell(20, 5, '91-180 DIAS', 'B', 0, 'R');
                $this->Cell(20, 5, 'MAS DE 180 DIAS', 'B', 0, 'R');
                $this->Cell(20, 5, 'TOTAL', 'B', 1, 'R');
            } else {
                $this->Cell(0, 5, 'CARTERA GENERAL' . $this->datos[0], 0, 1, 'C', true);
                $this->Ln(2);
                $this->SetFillColor(555, 255, 204);
                $this->SetFont($fuente, 'B', 7);
                $ancho_linea = 30;
                $this->Cell($ancho_linea, 5, 'FONDO', 'B', 0, 'L');
                $this->Cell($ancho_linea, 5, 'MONTO OTORGADO', 'B', 0, 'R');
                $this->Cell($ancho_linea, 5, 'SALDO', 'B', 0, 'R');
                $this->Cell($ancho_linea, 5, 'MORA 1-30', 'B', 0, 'R');
                $this->Cell($ancho_linea, 5, 'MORA 31-60', 'B', 0, 'R');
                $this->Cell($ancho_linea, 5, 'MORA 61-90', 'B', 0, 'R');
                $this->Cell($ancho_linea, 5, 'MORA 91-180', 'B', 0, 'R');
                $this->Cell($ancho_linea, 5, 'MORA >180', 'B', 0, 'R');
                $this->Cell($ancho_linea, 5, 'TOTAL MORA', 'B', 1, 'R');
            }
            $this->Ln(1);
        }

        function Footer()
        {
            $this->SetY(-15);
            // $this->Image($this->pathlogo, 175, 279, 28);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 7);
    $total_monto_otorgado = 0;
    $total_saldo = 0;
    $total_mora_1_30 = 0;
    $total_mora_31_60 = 0;
    $total_mora_61_90 = 0;
    $total_mora_91_180 = 0;
    $total_mora_mas_180 = 0;

    foreach ($registro as $fila) {
        $nombre_fondo = (string)($fila['nombre_fondo'] ?? '');
        $monto_otorgado = (float)($fila['monto_otorgado'] ?? 0);
        $saldo = (float)($fila['saldo'] ?? 0);
        $mora_1_30 = (float)($fila['mora_1_30'] ?? 0);
        $mora_31_60 = (float)($fila['mora_31_60'] ?? 0);
        $mora_61_90 = (float)($fila['mora_61_90'] ?? 0);
        $mora_91_180 = (float)($fila['mora_91_180'] ?? 0);
        $mora_mas_180 = (float)($fila['mora_mas_180'] ?? 0);

        // Total de mora por fondo
        $total_fila = $mora_1_30 + $mora_31_60 + $mora_61_90 + $mora_91_180 + $mora_mas_180;

        $total_monto_otorgado += $monto_otorgado;
        $total_saldo += $saldo;
        $total_mora_1_30 += $mora_1_30;
        $total_mora_31_60 += $mora_31_60;
        $total_mora_61_90 += $mora_61_90;
        $total_mora_91_180 += $mora_91_180;
        $total_mora_mas_180 += $mora_mas_180;

        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, $nombre_fondo, '', 0, 'L');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($monto_otorgado, 2, '.', ','), '', 0, 'R');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($saldo, 2, '.', ','), '', 0, 'R');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($mora_1_30, 2, '.', ','), '', 0, 'R');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($mora_31_60, 2, '.', ','), '', 0, 'R');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($mora_61_90, 2, '.', ','), '', 0, 'R');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($mora_91_180, 2, '.', ','), '', 0, 'R');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($mora_mas_180, 2, '.', ','), '', 0, 'R');
        $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_fila, 2, '.', ','), '', 1, 'R');
    }

    // Totales
    $total_mora = $total_mora_1_30 + $total_mora_31_60 + $total_mora_61_90 + $total_mora_91_180 + $total_mora_mas_180;
    $porcentaje_mora = ($total_saldo > 0) ? ($total_mora / $total_saldo) * 100 : 0;
    $saldoaldia = $total_saldo - $total_mora;

    $pdf->SetFont($fuente, 'B', 7);
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, 'TOTAL MORA', '', 0, 'L');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_monto_otorgado, 2, '.', ','), '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_saldo, 2, '.', ','), '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_mora_1_30, 2, '.', ','), '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_mora_31_60, 2, '.', ','), '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_mora_61_90, 2, '.', ','), '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_mora_91_180, 2, '.', ','), '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_mora_mas_180, 2, '.', ','), '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, number_format($total_mora, 2, '.', ','), '', 1, 'R');

    // Información adicional en la esquina inferior derecha
    $pdf->Ln(10);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(0, 5, 'SALDO AL DIA: ' . number_format($saldoaldia, 2, '.', ','), 0, 1, 'R');
    $pdf->Cell(0, 5, 'MORA DEL MES: ' . number_format($porcentaje_mora, 2) . '%', 0, 1, 'R');

    //Segunda Pagina con morosidad en asociados
    // Segunda Página con Morosidad en Asociados
    $pdf->AddPage();
    $fuente = "Courier";
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell(0, 10, 'MOROSIDAD EN ASOCIADOS', 0, 1, 'C');

    // Encabezados de la tabla
    $pdf->SetFont($fuente, 'B', 7);
    $pdf->Cell(10, 7, 'No', 0, 0, 'C');
    $pdf->Cell(40, 7, 'FUENTE DE FONDOS', 0, 0, 'C');
    $pdf->Cell(20, 7, 'AL DIA', 0, 0, 'C');
    $pdf->Cell(20, 7, '1-30 DIAS', 0, 0, 'C');
    $pdf->Cell(20, 7, '31-60 DIAS', 0, 0, 'C');
    $pdf->Cell(20, 7, '61-90 DIAS', 0, 0, 'C');
    $pdf->Cell(20, 7, '91-180 DIAS', 0, 0, 'C');
    $pdf->Cell(20, 7, 'MAS DE 180 DIAS', 0, 0, 'C');
    $pdf->Cell(20, 7, 'TOTAL', 0, 1, 'C');

    // Inicializar totales
    $totales = [
        'al_dia' => 0,
        'mora_1_30' => 0,
        'mora_31_60' => 0,
        'mora_61_90' => 0,
        'mora_91_180' => 0,
        'mora_mas_180' => 0,
        'total' => 0
    ];

    // Procesar los datos de $result_registros
    $index = 1;
    foreach ($result_registros as $fila) {
        $nombre_fondo = (string)($fila['nombre_fondo'] ?? '');
        $al_dia = (int)($fila['al_dia'] ?? 0);
        $mora_1_30 = (int)($fila['mora_1_30'] ?? 0);
        $mora_31_60 = (int)($fila['mora_31_60'] ?? 0);
        $mora_61_90 = (int)($fila['mora_61_90'] ?? 0);
        $mora_91_180 = (int)($fila['mora_91_180'] ?? 0);
        $mora_mas_180 = (int)($fila['mora_mas_180'] ?? 0);
        $total = $mora_1_30 + $mora_31_60 + $mora_61_90 + $mora_91_180 + $mora_mas_180; // Excluir 'al_dia' de la suma total

        // Sumar los totales globales
        $totales['al_dia'] += $al_dia;
        $totales['mora_1_30'] += $mora_1_30;
        $totales['mora_31_60'] += $mora_31_60;
        $totales['mora_61_90'] += $mora_61_90;
        $totales['mora_91_180'] += $mora_91_180;
        $totales['mora_mas_180'] += $mora_mas_180;
        $totales['total'] += $total;

        // Agregar fila al PDF
        $pdf->SetFont($fuente, '', 8); // Aumentar ligeramente el tamaño de la fuente
        $pdf->Cell(10, 8, $index++, 0, 0, 'C');
        $pdf->Cell(40, 8, $nombre_fondo, 0, 0, 'C');
        $pdf->Cell(20, 8, number_format($al_dia, 0, '.', ','), 0, 0, 'C');
        $pdf->Cell(20, 8, number_format($mora_1_30, 0, '.', ','), 0, 0, 'C');
        $pdf->Cell(20, 8, number_format($mora_31_60, 0, '.', ','), 0, 0, 'C');
        $pdf->Cell(20, 8, number_format($mora_61_90, 0, '.', ','), 0, 0, 'C');
        $pdf->Cell(20, 8, number_format($mora_91_180, 0, '.', ','), 0, 0, 'C');
        $pdf->Cell(20, 8, number_format($mora_mas_180, 0, '.', ','), 0, 0, 'C');
        $pdf->Cell(20, 8, number_format($total, 0, '.', ','), 0, 1, 'C');
    }

    // Agregar totales al final de la tabla
    $pdf->SetFont($fuente, 'B', 8); // Aumentar ligeramente el tamaño de la fuente
    $pdf->Cell(50, 8, 'TOTAL', 0, 0, 'C');
    $pdf->Cell(20, 8, number_format($totales['al_dia'], 0, '.', ','), 0, 0, 'C');
    $pdf->Cell(20, 8, number_format($totales['mora_1_30'], 0, '.', ','), 0, 0, 'C');
    $pdf->Cell(20, 8, number_format($totales['mora_31_60'], 0, '.', ','), 0, 0, 'C');
    $pdf->Cell(20, 8, number_format($totales['mora_61_90'], 0, '.', ','), 0, 0, 'C');
    $pdf->Cell(20, 8, number_format($totales['mora_91_180'], 0, '.', ','), 0, 0, 'C');
    $pdf->Cell(20, 8, number_format($totales['mora_mas_180'], 0, '.', ','), 0, 0, 'C');
    $pdf->Cell(20, 8, number_format($totales['total'], 0, '.', ','), 0, 1, 'C');

    // Calcular los valores adicionales
    $asociados_en_mora = $totales['mora_1_30'] + $totales['mora_31_60'] + $totales['mora_61_90'] + $totales['mora_91_180'] + $totales['mora_mas_180'];
    $asociados_al_dia = $totales['al_dia'];
    $total_asociados = $asociados_en_mora + $asociados_al_dia;

    // Información adicional en la esquina inferior derecha
    $pdf->Ln(10);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(0, 5, 'ASOCIADOS EN MORA: ' . number_format($asociados_en_mora, 0, '.', ','), 0, 1, 'R');
    $pdf->Cell(0, 5, 'ASOCIADOS AL DIA: ' . number_format($asociados_al_dia, 0, '.', ','), 0, 1, 'R');
    $pdf->Cell(0, 5, 'TOTAL ASOCIADOS: ' . number_format($total_asociados, 0, '.', ','), 0, 1, 'R');

// Tercera Página con Cartera por Actividad, Género y Plazos
$pdf->AddPage();
$fuente = "Courier";

// **Cartera por Actividad**
$pdf->SetFont($fuente, 'B', 10);
$pdf->Cell(0, 10, 'CARTERA POR ACTIVIDAD', 0, 1, 'C');
$pdf->SetFont($fuente, '', 7);

// Encabezado
$pdf->Cell(50, 7, 'ACTIVIDAD', 0);
$pdf->Cell(30, 7, 'CLIENTES', 0, 0, 'C');
$pdf->Cell(40, 7, 'TOTAL', 0, 0, 'R');
$pdf->Ln();

// Agregar datos de actividad
$total_clientes = 0;
$total_actividad = 0.0;
foreach ($result_genero_plazos as $fila) {
    $categoria = (string)($fila['categoria'] ?? '');
    if ($categoria === 'Genero') {
        $descripcion = (string)($fila['descripcion'] ?? '');
        $totalFila = (int)($fila['total'] ?? 0);
        $montoTotal = (float)($fila['monto_total'] ?? 0);
        $pdf->Cell(50, 7, $descripcion, 0);
        $pdf->Cell(30, 7, $totalFila, 0, 0, 'C');
        $pdf->Cell(40, 7, number_format($montoTotal, 2, '.', ','), 0, 0, 'R');
        $total_clientes += $totalFila;
        $total_actividad += $montoTotal;
        $pdf->Ln();
    }
}
$pdf->SetFont($fuente, 'B', 7);
$pdf->Cell(50, 7, 'TOTAL', 0);
$pdf->Cell(30, 7, $total_clientes, 0, 0, 'C');
$pdf->Cell(40, 7, number_format($total_actividad, 2, '.', ','), 0, 0, 'R');
$pdf->Ln(15);

// **Distribución de la Cartera por Garantía**
$pdf->SetFont($fuente, 'B', 10);
$pdf->Cell(0, 10, 'DISTRIBUCION DE LA CARTERA POR GARANTIA', 0, 1, 'C');
$pdf->SetFont($fuente, '', 7);

// Encabezado
$pdf->Cell(50, 7, 'GARANTIA', 0);
$pdf->Cell(30, 7, 'CANTIDAD', 0, 0, 'C');
$pdf->Cell(30, 7, 'PORCENTAJE', 0, 0, 'R');
$pdf->Ln();

// Agregar datos de garantía (simulado)
$garantia_data = [
    ['descripcion' => 'FIDUCIARIA', 'cantidad' => 69, 'porcentaje' => 14],
    ['descripcion' => 'PRENDARIA', 'cantidad' => 50, 'porcentaje' => 10],
    ['descripcion' => 'HIPOTECARIA', 'cantidad' => 343, 'porcentaje' => 71],
    ['descripcion' => 'MIXTA', 'cantidad' => 20, 'porcentaje' => 4],
];
foreach ($garantia_data as $garantia) {
    $pdf->Cell(50, 7, $garantia['descripcion'], 0);
    $pdf->Cell(30, 7, $garantia['cantidad'], 0, 0, 'C');
    $pdf->Cell(30, 7, $garantia['porcentaje'] . '%', 0, 0, 'R');
    $pdf->Ln();
}
$pdf->SetFont($fuente, 'B', 7);
$pdf->Cell(50, 7, 'TOTAL', 0);
$pdf->Cell(30, 7, 482, 0, 0, 'C');
$pdf->Cell(30, 7, '100%', 0, 0, 'R');
$pdf->Ln(15);

// **Cartera por Género**
$pdf->SetFont($fuente, 'B', 10);
$pdf->Cell(0, 10, 'CARTERA POR GENERO', 0, 1, 'C');
$pdf->SetFont($fuente, '', 7);

// Encabezado
$pdf->Cell(40, 7, 'ACTIVIDAD', 0);
$pdf->Cell(20, 7, 'HOMBRES', 0);
$pdf->Cell(20, 7, 'MUJERES', 0);
$pdf->Cell(20, 7, 'TOTAL', 0);
$pdf->Cell(30, 7, 'HOMBRES', 0, 0, 'R');
$pdf->Cell(30, 7, 'MUJERES', 0, 0, 'R');
$pdf->Cell(30, 7, 'TOTAL', 0, 0, 'R');
$pdf->Ln();

// Agregar datos de género
foreach ($result_genero_plazos as $fila) {
    $categoria = (string)($fila['categoria'] ?? '');
    if ($categoria === 'Genero') {
        $descripcion = (string)($fila['descripcion'] ?? '');
        $hombres = (int)($fila['hombres'] ?? 0);
        $mujeres = (int)($fila['mujeres'] ?? 0);
        $total = (int)($fila['total'] ?? 0);
        $montoHombres = (float)($fila['monto_hombres'] ?? 0);
        $montoMujeres = (float)($fila['monto_mujeres'] ?? 0);
        $montoTotal = (float)($fila['monto_total'] ?? 0);
        $pdf->Cell(40, 7, $descripcion, 0);
        $pdf->Cell(20, 7, $hombres, 0);
        $pdf->Cell(20, 7, $mujeres, 0);
        $pdf->Cell(20, 7, $total, 0);
        $pdf->Cell(30, 7, number_format($montoHombres, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(30, 7, number_format($montoMujeres, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(30, 7, number_format($montoTotal, 2, '.', ','), 0, 0, 'R');
        $pdf->Ln();
    }
}
$pdf->Ln(15);

// **Cartera por Plazos**
$pdf->SetFont($fuente, 'B', 10);
$pdf->Cell(0, 10, 'CARTERA POR PLAZOS', 0, 1, 'C');
$pdf->SetFont($fuente, '', 7);

// Encabezado
$pdf->Cell(40, 7, 'Plazo', 0);
$pdf->Cell(40, 7, 'Saldo', 0, 0, 'R');
$pdf->Cell(40, 7, 'No. de Asociados', 0, 0, 'C');
$pdf->Ln();

// Agregar datos de plazos
foreach ($result_genero_plazos as $fila) {
    $categoria = (string)($fila['categoria'] ?? '');
    if ($categoria === 'Plazo') {
        $descripcion = (string)($fila['descripcion'] ?? '');
        $saldoPlazo = (float)($fila['saldo'] ?? 0);
        $numAsociados = (int)($fila['num_asociados'] ?? 0);
        $pdf->Cell(40, 7, $descripcion, 0);
        $pdf->Cell(40, 7, number_format($saldoPlazo, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(40, 7, $numAsociados, 0, 0, 'C');
        $pdf->Ln();
    }
}



    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    header('Content-Type: application/json');
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
function printxls($registro, $result_registros, $result_genero_plazos, $titlereport, $usuario)
{
    require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    // $tamanioEncabezado = 14;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    
    // === HOJA 1: RESUMEN CARTERA CONSOLIDADA ===
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Resumen Cartera");
    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(30);
    $activa->getColumnDimension("C")->setWidth(18);
    $activa->getColumnDimension("D")->setWidth(18);
    $activa->getColumnDimension("E")->setWidth(18);
    $activa->getColumnDimension("F")->setWidth(18);
    $activa->getColumnDimension("G")->setWidth(18);
    $activa->getColumnDimension("H")->setWidth(18);
    $activa->getColumnDimension("I")->setWidth(18);


    //insertarmos la fecha y usuario
    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $activa->getStyle("A1:I1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle("A2:I2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $activa->getStyle("A1:I1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A2:I2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer pequeño las letras del encabezado de titulo
    $activa->getStyle("A4:I4")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $activa->getStyle("A5:I5")->getFont()->setSize($tamanioTabla)->setName($fuente);
    //centrar los encabezado de la tabla
    $activa->getStyle("A4:I4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A5:I5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $activa->setCellValue("A4", "REPORTE CONSOLIDADO");
    $activa->setCellValue("A5", strtoupper("CARTERA CONSOLIDADA " . $titlereport));

    # Escribir encabezado de la tabla CONSOLIDADA
    $encabezado_tabla = ["AGENCIA", "FUENTE DE FONDOS", "MONTO OTORGADO", "SALDO", "MORA 1-30", "MORA 31-60", "MORA 61-90", "MORA 91-180", "MORA >180"];
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle('A8:I8')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $activa->mergeCells('A1:I1');
    $activa->mergeCells('A2:I2');
    $activa->mergeCells('A4:I4');
    $activa->mergeCells('A5:I5');

    // DATOS CONSOLIDADOS - Protección contra undefined keys
    $fila = 0;
    $i = 9;
    
    // Totalizadores
    $sum_monto = 0;
    $sum_saldo = 0;
    $sum_mora_1_30 = 0;
    $sum_mora_31_60 = 0;
    $sum_mora_61_90 = 0;
    $sum_mora_91_180 = 0;
    $sum_mora_mas_180 = 0;
    
    while ($fila < count($registro)) {
        // Lectura SEGURA de campos consolidados
        $codigo_agencia = $registro[$fila]["codigo_agencia"] ?? '';
        $nombrefondos = $registro[$fila]["nombre_fondo"] ?? '';
        $monto = floatval($registro[$fila]["monto_otorgado"] ?? 0);
        $saldo = floatval($registro[$fila]["saldo"] ?? 0);
        $mora_1_30 = floatval($registro[$fila]["mora_1_30"] ?? 0);
        $mora_31_60 = floatval($registro[$fila]["mora_31_60"] ?? 0);
        $mora_61_90 = floatval($registro[$fila]["mora_61_90"] ?? 0);
        $mora_91_180 = floatval($registro[$fila]["mora_91_180"] ?? 0);
        $mora_mas_180 = floatval($registro[$fila]["mora_mas_180"] ?? 0);
        
        // Acumular totales
        $sum_monto += $monto;
        $sum_saldo += $saldo;
        $sum_mora_1_30 += $mora_1_30;
        $sum_mora_31_60 += $mora_31_60;
        $sum_mora_61_90 += $mora_61_90;
        $sum_mora_91_180 += $mora_91_180;
        $sum_mora_mas_180 += $mora_mas_180;
        
        // ESCRIBIR FILA CONSOLIDADA (NO detallada)
        $activa->setCellValueByColumnAndRow(1, $i, $codigo_agencia);
        $activa->setCellValueByColumnAndRow(2, $i, $nombrefondos);
        $activa->setCellValueByColumnAndRow(3, $i, $monto);
        $activa->setCellValueByColumnAndRow(4, $i, $saldo);
        $activa->setCellValueByColumnAndRow(5, $i, $mora_1_30);
        $activa->setCellValueByColumnAndRow(6, $i, $mora_31_60);
        $activa->setCellValueByColumnAndRow(7, $i, $mora_61_90);
        $activa->setCellValueByColumnAndRow(8, $i, $mora_91_180);
        $activa->setCellValueByColumnAndRow(9, $i, $mora_mas_180);
        
        $activa->getStyle("A" . $i . ":I" . $i)->getFont()->setName($fuente);
        
        $fila++;
        $i++;
    }
    
    // TOTALES
    $activa->getStyle("A" . $i . ":I" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValueExplicit('A' . $i, "TOTALES", \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->setCellValue('B' . $i, '');
    $activa->setCellValue('C' . $i, $sum_monto);
    $activa->setCellValue('D' . $i, $sum_saldo);
    $activa->setCellValue('E' . $i, $sum_mora_1_30);
    $activa->setCellValue('F' . $i, $sum_mora_31_60);
    $activa->setCellValue('G' . $i, $sum_mora_61_90);
    $activa->setCellValue('H' . $i, $sum_mora_91_180);
    $activa->setCellValue('I' . $i, $sum_mora_mas_180);
    
    $activa->getStyle("A" . $i . ":I" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    // Autoajustar columnas consolidadas (A-I)
    $columnas_consolidadas = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
    foreach ($columnas_consolidadas as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(TRUE);
    }

    // === HOJA 2: MOROSIDAD EN ASOCIADOS ===
    $hoja2 = $excel->createSheet();
    $hoja2->setTitle("Morosidad Asociados");
    
    // Título
    $hoja2->setCellValue("A1", "MOROSIDAD EN ASOCIADOS");
    $hoja2->mergeCells('A1:H1');
    $hoja2->getStyle("A1")->getFont()->setBold(true)->setSize(14);
    $hoja2->getStyle("A1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Encabezados
    $hoja2->setCellValue("A3", "NO");
    $hoja2->setCellValue("B3", "FUENTE DE FONDOS");
    $hoja2->setCellValue("C3", "AL DIA");
    $hoja2->setCellValue("D3", "1-30 DIAS");
    $hoja2->setCellValue("E3", "31-60 DIAS");
    $hoja2->setCellValue("F3", "61-90 DIAS");
    $hoja2->setCellValue("G3", "91-180 DIAS");
    $hoja2->setCellValue("H3", "MAS 180 DIAS");
    $hoja2->getStyle("A3:H3")->getFont()->setBold(true);
    $hoja2->getStyle("A3:H3")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9D9D9');
    
    // Datos
    $fila_mor = 4;
    $index = 1;
    $totales_mor = ['al_dia' => 0, 'mora_1_30' => 0, 'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0];
    
    foreach ($result_registros as $row) {
        $hoja2->setCellValue("A" . $fila_mor, $index++);
        $hoja2->setCellValue("B" . $fila_mor, $row["nombre_fondo"] ?? '');
        $hoja2->setCellValue("C" . $fila_mor, $row["al_dia"] ?? 0);
        $hoja2->setCellValue("D" . $fila_mor, $row["mora_1_30"] ?? 0);
        $hoja2->setCellValue("E" . $fila_mor, $row["mora_31_60"] ?? 0);
        $hoja2->setCellValue("F" . $fila_mor, $row["mora_61_90"] ?? 0);
        $hoja2->setCellValue("G" . $fila_mor, $row["mora_91_180"] ?? 0);
        $hoja2->setCellValue("H" . $fila_mor, $row["mora_mas_180"] ?? 0);
        
        $totales_mor['al_dia'] += ($row["al_dia"] ?? 0);
        $totales_mor['mora_1_30'] += ($row["mora_1_30"] ?? 0);
        $totales_mor['mora_31_60'] += ($row["mora_31_60"] ?? 0);
        $totales_mor['mora_61_90'] += ($row["mora_61_90"] ?? 0);
        $totales_mor['mora_91_180'] += ($row["mora_91_180"] ?? 0);
        $totales_mor['mora_mas_180'] += ($row["mora_mas_180"] ?? 0);
        
        $fila_mor++;
    }
    
    // Totales hoja 2
    $hoja2->setCellValue("A" . $fila_mor, "TOTAL");
    $hoja2->setCellValue("B" . $fila_mor, "");
    $hoja2->setCellValue("C" . $fila_mor, $totales_mor['al_dia']);
    $hoja2->setCellValue("D" . $fila_mor, $totales_mor['mora_1_30']);
    $hoja2->setCellValue("E" . $fila_mor, $totales_mor['mora_31_60']);
    $hoja2->setCellValue("F" . $fila_mor, $totales_mor['mora_61_90']);
    $hoja2->setCellValue("G" . $fila_mor, $totales_mor['mora_91_180']);
    $hoja2->setCellValue("H" . $fila_mor, $totales_mor['mora_mas_180']);
    $hoja2->getStyle("A" . $fila_mor . ":H" . $fila_mor)->getFont()->setBold(true);
    $hoja2->getStyle("A" . $fila_mor . ":H" . $fila_mor)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFFFFF00');
    
    foreach (range('A', 'H') as $col) {
        $hoja2->getColumnDimension($col)->setAutoSize(true);
    }

    // === HOJA 3: CARTERA POR ACTIVIDAD Y GÉNERO ===
    $hoja3 = $excel->createSheet();
    $hoja3->setTitle("Actividad y Genero");
    
    // SECCIÓN 1: Cartera por Actividad
    $hoja3->setCellValue("A1", "CARTERA POR ACTIVIDAD ECONÓMICA");
    $hoja3->mergeCells('A1:C1');
    $hoja3->getStyle("A1")->getFont()->setBold(true)->setSize(12);
    $hoja3->getStyle("A1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $hoja3->setCellValue("A3", "ACTIVIDAD");
    $hoja3->setCellValue("B3", "CLIENTES");
    $hoja3->setCellValue("C3", "MONTO TOTAL");
    $hoja3->getStyle("A3:C3")->getFont()->setBold(true);
    $hoja3->getStyle("A3:C3")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9D9D9');
    
    $fila_act = 4;
    $total_clientes_act = 0;
    $total_monto_act = 0;
    
    foreach ($result_genero_plazos as $row) {
        if (($row['categoria'] ?? '') === 'Genero') {
            $hoja3->setCellValue("A" . $fila_act, $row["descripcion"] ?? '');
            $hoja3->setCellValue("B" . $fila_act, $row["total"] ?? 0);
            $hoja3->setCellValue("C" . $fila_act, $row["monto_total"] ?? 0);
            $hoja3->getStyle("C" . $fila_act)->getNumberFormat()->setFormatCode('#,##0.00');
            
            $total_clientes_act += ($row["total"] ?? 0);
            $total_monto_act += ($row["monto_total"] ?? 0);
            $fila_act++;
        }
    }
    
    $hoja3->setCellValue("A" . $fila_act, "TOTAL");
    $hoja3->setCellValue("B" . $fila_act, $total_clientes_act);
    $hoja3->setCellValue("C" . $fila_act, $total_monto_act);
    $hoja3->getStyle("A" . $fila_act . ":C" . $fila_act)->getFont()->setBold(true);
    $hoja3->getStyle("C" . $fila_act)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // SECCIÓN 2: Cartera por Género (más abajo)
    $fila_gen = $fila_act + 3;
    $hoja3->setCellValue("A" . $fila_gen, "CARTERA POR GÉNERO");
    $hoja3->mergeCells("A" . $fila_gen . ":G" . $fila_gen);
    $hoja3->getStyle("A" . $fila_gen)->getFont()->setBold(true)->setSize(12);
    $hoja3->getStyle("A" . $fila_gen)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $fila_gen += 2;
    $hoja3->setCellValue("A" . $fila_gen, "ACTIVIDAD");
    $hoja3->setCellValue("B" . $fila_gen, "HOMBRES");
    $hoja3->setCellValue("C" . $fila_gen, "MUJERES");
    $hoja3->setCellValue("D" . $fila_gen, "TOTAL");
    $hoja3->setCellValue("E" . $fila_gen, "MONTO HOMBRES");
    $hoja3->setCellValue("F" . $fila_gen, "MONTO MUJERES");
    $hoja3->setCellValue("G" . $fila_gen, "MONTO TOTAL");
    $hoja3->getStyle("A" . $fila_gen . ":G" . $fila_gen)->getFont()->setBold(true);
    $hoja3->getStyle("A" . $fila_gen . ":G" . $fila_gen)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9D9D9');
    
    $fila_gen++;
    foreach ($result_genero_plazos as $row) {
        if (($row['categoria'] ?? '') === 'Genero') {
            $hoja3->setCellValue("A" . $fila_gen, $row["descripcion"] ?? '');
            $hoja3->setCellValue("B" . $fila_gen, $row["hombres"] ?? 0);
            $hoja3->setCellValue("C" . $fila_gen, $row["mujeres"] ?? 0);
            $hoja3->setCellValue("D" . $fila_gen, $row["total"] ?? 0);
            $hoja3->setCellValue("E" . $fila_gen, $row["monto_hombres"] ?? 0);
            $hoja3->setCellValue("F" . $fila_gen, $row["monto_mujeres"] ?? 0);
            $hoja3->setCellValue("G" . $fila_gen, $row["monto_total"] ?? 0);
            $hoja3->getStyle("E" . $fila_gen . ":G" . $fila_gen)->getNumberFormat()->setFormatCode('#,##0.00');
            $fila_gen++;
        }
    }
    
    foreach (range('A', 'G') as $col) {
        $hoja3->getColumnDimension($col)->setAutoSize(true);
    }

    // === HOJA 4: CARTERA POR PLAZOS ===
    $hoja4 = $excel->createSheet();
    $hoja4->setTitle("Plazos");
    
    $hoja4->setCellValue("A1", "CARTERA POR PLAZOS");
    $hoja4->mergeCells('A1:C1');
    $hoja4->getStyle("A1")->getFont()->setBold(true)->setSize(12);
    $hoja4->getStyle("A1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $hoja4->setCellValue("A3", "PLAZO");
    $hoja4->setCellValue("B3", "SALDO");
    $hoja4->setCellValue("C3", "NO. ASOCIADOS");
    $hoja4->getStyle("A3:C3")->getFont()->setBold(true);
    $hoja4->getStyle("A3:C3")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9D9D9');
    
    $fila_plaz = 4;
    foreach ($result_genero_plazos as $row) {
        if (($row['categoria'] ?? '') === 'Plazo') {
            $hoja4->setCellValue("A" . $fila_plaz, $row["descripcion"] ?? '');
            $hoja4->setCellValue("B" . $fila_plaz, $row["saldo"] ?? 0);
            $hoja4->setCellValue("C" . $fila_plaz, $row["num_asociados"] ?? 0);
            $hoja4->getStyle("B" . $fila_plaz)->getNumberFormat()->setFormatCode('#,##0.00');
            $fila_plaz++;
        }
    }
    
    foreach (range('A', 'C') as $col) {
        $hoja4->getColumnDimension($col)->setAutoSize(true);
    }

    // === HOJA 5: DISTRIBUCIÓN DE LA CARTERA POR GARANTÍA ===
    $hoja5 = $excel->createSheet();
    $hoja5->setTitle("Garantias");
    
    $hoja5->setCellValue("A1", "DISTRIBUCIÓN DE LA CARTERA POR GARANTÍA");
    $hoja5->mergeCells('A1:C1');
    $hoja5->getStyle("A1")->getFont()->setBold(true)->setSize(12);
    $hoja5->getStyle("A1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $hoja5->setCellValue("A3", "GARANTÍA");
    $hoja5->setCellValue("B3", "CANTIDAD");
    $hoja5->setCellValue("C3", "PORCENTAJE");
    $hoja5->getStyle("A3:C3")->getFont()->setBold(true);
    $hoja5->getStyle("A3:C3")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9D9D9');
    $hoja5->getStyle("B3:C3")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Datos de garantía (hardcoded como en el PDF)
    $garantia_data = [
        ['descripcion' => 'FIDUCIARIA', 'cantidad' => 69, 'porcentaje' => 14],
        ['descripcion' => 'PRENDARIA', 'cantidad' => 50, 'porcentaje' => 10],
        ['descripcion' => 'HIPOTECARIA', 'cantidad' => 343, 'porcentaje' => 71],
        ['descripcion' => 'MIXTA', 'cantidad' => 20, 'porcentaje' => 4],
    ];
    
    $fila_gar = 4;
    $total_cantidad = 0;
    $total_porcentaje = 0;
    
    foreach ($garantia_data as $garantia) {
        $hoja5->setCellValue("A" . $fila_gar, $garantia['descripcion']);
        $hoja5->setCellValue("B" . $fila_gar, $garantia['cantidad']);
        $hoja5->setCellValue("C" . $fila_gar, $garantia['porcentaje'] . '%');
        $hoja5->getStyle("B" . $fila_gar . ":C" . $fila_gar)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $total_cantidad += $garantia['cantidad'];
        $total_porcentaje += $garantia['porcentaje'];
        $fila_gar++;
    }
    
    // Totales
    $hoja5->setCellValue("A" . $fila_gar, "TOTAL");
    $hoja5->setCellValue("B" . $fila_gar, $total_cantidad);
    $hoja5->setCellValue("C" . $fila_gar, $total_porcentaje . '%');
    $hoja5->getStyle("A" . $fila_gar . ":C" . $fila_gar)->getFont()->setBold(true);
    $hoja5->getStyle("B" . $fila_gar . ":C" . $fila_gar)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hoja5->getStyle("A" . $fila_gar . ":C" . $fila_gar)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFFFFF00');
    
    foreach (range('A', 'C') as $col) {
        $hoja5->getColumnDimension($col)->setAutoSize(true);
    }

    // Generar archivo
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cartera Consolidada " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
function obtenerContador($restart = false)
{
    static $contador = 0;
    $contador = ($restart == false) ? $contador + 1 : $restart;
    return $contador;
}
function obtenerLetra($columna)
{
    $letra = '';
    $columna--; // Decrementar la columna para que coincida con el índice de las letras del abecedario (empezando desde 0)

    while ($columna >= 0) {
        $letra = chr($columna % 26 + 65) . $letra; // Convertir el índice de columna a letra de Excel
        $columna = intval($columna / 26) - 1;
    }

    return $letra;
}
