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

include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use App\DatabaseAdapter;
use Micro\Generic\Utf8;
use Micro\Helpers\ExcelHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
// $archivo = $datos[3];
$tipo = $_POST["tipo"];

// Región (la UI la envía al final del payload: radios[4], selects[3])
// $regionRadio = $radios[4] ?? null;
$regionId = isset($selects[3]) ? (int)$selects[3] : 0;
if ($radios[0]  === 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    return;
}

// Región de origen (solo cuando el reporte queda acotado a una región)
$mostrarRegionCol = false;
$regionNombre = '';
$agenciaIdSeleccionada = (($radios[0] ?? null) === 'anyofi') ? (int)($selects[0] ?? 0) : 0;

if (!validateDate($inputs[0], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}

if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar fuente de fondos']);
    return;
}
if ($radios[3] == "anyasesor" && $selects[2] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Asesor']);
    return;
}

$database = new DatabaseAdapter();
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : (($radios[0] == 'anyregion') ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region = ?) " : "");
$filasesor = ($radios[3] == "anyasesor") ? " AND cremi.CodAnal =" . $selects[2] : "";
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//FILTRO POR REGION
// $filregion = ($radios[0] === 'anyregion' && $regionId > 0)
//     ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region = ?) "
//     : "";


$strquery = "SELECT cremi.CodAnal, DAY(cremi.DfecPago) diaPago,
    CONCAT(usu.nombre, ' ', usu.apellido) AS analista,
   CASE WHEN cremi.TipoEnti = 'GRUP' THEN grupo.NombreGrupo ELSE 'INDI' END AS nombreGrupo,
   CASE WHEN cremi.TipoEnti = 'GRUP' THEN IFNULL(cli2.short_name,cli.short_name) ELSE cli.short_name END AS nombreTitular,
   cremi.NCiclo,cremi.DFecDsbls,cremi.DFecVen,
    prod.id_fondo AS id_fondos,
    ffon.descripcion AS nombre_fondo,
    COUNT(cremi.CCODCTA) AS cantidad_creditos,
    SUM(cremi.NCapDes) AS monto_otorgado,
    SUM(GREATEST(cremi.NCapDes - IFNULL(kar.sum_KP, 0), 0)) AS salcap,
    SUM(GREATEST(IFNULL(ppg_ult.sum_ncapita, 0) - IFNULL(kar.sum_KP, 0), 0)) AS capmora,
    MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED)) AS atraso_maximo
FROM cremcre_meta cremi
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo
LEFT JOIN (
    SELECT ccodcta, SUM(ncapita) AS sum_ncapita
    FROM Cre_ppg
    WHERE dfecven <= ?
    GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
LEFT JOIN tb_cliente_tb_grupo tg ON tg.id_grupo=grupo.id_grupos AND tg.cod_cargo=2
LEFT JOIN tb_cliente cli2 ON cli2.idcod_cliente=tg.cliente_id
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? {$filfondo} {$filagencia} {$filasesor} {$status}
GROUP BY cremi.CodAnal, cremi.TipoEnti, 
 CASE WHEN cremi.TipoEnti = 'GRUP' THEN CCodGrupo ELSE cremi.CCODCTA END, cremi.NCiclo, prod.id_fondo
ORDER BY cremi.CodAnal, TipoEnti, NCiclo;";

$showmensaje = false;
try {
    $database->openConnection();

    // Determinar región (solo si el reporte queda acotado)
    if ($radios[0] === 'anyregion' && $regionId > 0) {
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

    $queryParams = [$filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha];
    if ($radios[0] === "anyregion" && $regionId > 0) {
        $queryParams[] = $regionId;
    }

    $result = $database->getAllResults($strquery, $queryParams);
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
        printxls($result, $titlereport, $idusuario, $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
        printpdf($result, [$titlereport], $info, $mostrarRegionCol, $regionNombre);
        break;
}

function printpdf($registro, $datos, $info, $mostrarRegionCol = false, $regionNombre = '')
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];

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

        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Image($this->pathlogoins, 10, 13, 33);
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->Ln(10);
            $this->SetFont($fuente, 'B', 10);
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'RESUMEN DE CARTERA POR ASESOR Y GRUPO' . $this->datos[0], 0, 1, 'C', true);
            if ($this->mostrarRegionCol && $this->regionNombre !== '') {
                $this->SetFont($fuente, 'B', 8);
                $this->Cell(0, 4, 'REGION: ' . Utf8::decode($this->regionNombre), 0, 1, 'C');
            }
            $this->Ln(2);
            $this->SetFillColor(555, 255, 204);
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 22;
            $this->Cell($ancho_linea * 2, 5, 'ASESOR/GRUPO', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'CANT. CRED.', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'MONTO OTORG.', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'CAP. PAGADO', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'INT. PAGADO', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'SALDO CAP.', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'SALDO INT.', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'SALDO TOTAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'CAP. MORA', 'B', 0, 'R');
            $this->Cell($ancho_linea / 2, 5, 'ATR.PROM', 'B', 1, 'C');
            $this->Ln(1);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $mostrarRegionCol, $regionNombre);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $fuente = "Courier";
    $ancho_linea = 22;

    $resumenAsesor = [
        'cantidad' => 0,
        'monto' => 0,
        'cappag' => 0,
        'intpag' => 0,
        'salcap' => 0,
        'salint' => 0,
        'capmora' => 0
    ];

    $resumenFondo = [
        'cantidad' => 0,
        'monto' => 0,
        'cappag' => 0,
        'intpag' => 0,
        'salcap' => 0,
        'salint' => 0,
        'capmora' => 0
    ];

    $auxAsesor = 0;
    $auxFondo = 0;
    $fila = 0;

    while ($fila < count($registro)) {
        $codasesor = $registro[$fila]["CodAnal"];
        $asesor = Utf8::decode($registro[$fila]["analista"]);
        $tipoenti = $registro[$fila]["TipoEnti"];
        $grupo = Utf8::decode($registro[$fila]["NombreGrupo"]);
        $idfondo = $registro[$fila]["id_fondos"];
        $nombrefondo = $registro[$fila]["nombre_fondo"];
        $cantidad = $registro[$fila]["cantidad_creditos"];
        $monto = $registro[$fila]["monto_otorgado"];
        $cappag = $registro[$fila]["cappag"];
        $intpag = $registro[$fila]["intpag"];
        $salcap = $registro[$fila]["salcap"];
        $salint = $registro[$fila]["salint"];
        $capmora = $registro[$fila]["capmora"];
        $atraso_prom = round($registro[$fila]["atraso_promedio"]);

        // Cambio de fondo
        if ($idfondo != $auxFondo && $auxFondo != 0) {
            // Subtotal del último asesor
            if ($resumenAsesor['cantidad'] > 0) {
                $pdf->SetFont($fuente, 'B', 7);
                $pdf->CellFit($ancho_linea * 2, 4, 'SUBTOTAL ASESOR', 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, $resumenAsesor['cantidad'], 'T', 0, 'C', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['monto'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['cappag'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['intpag'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salcap'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salint'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salcap'] + $resumenAsesor['salint'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['capmora'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->Cell($ancho_linea / 2, 4, '', 'T', 1, 'C');
            }

            // Total del fondo
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->SetFillColor(255, 255, 153);
            $pdf->CellFit($ancho_linea * 2, 5, 'TOTAL FONDO', 'TB', 0, 'R', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, $resumenFondo['cantidad'], 'TB', 0, 'C', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['monto'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['cappag'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['intpag'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['salcap'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['salint'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['salcap'] + $resumenFondo['salint'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
            $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['capmora'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
            $pdf->Cell($ancho_linea / 2, 5, '', 'TB', 1, 'C', true);

            // Resetear
            $resumenFondo = ['cantidad' => 0, 'monto' => 0, 'cappag' => 0, 'intpag' => 0, 'salcap' => 0, 'salint' => 0, 'capmora' => 0];
            $resumenAsesor = ['cantidad' => 0, 'monto' => 0, 'cappag' => 0, 'intpag' => 0, 'salcap' => 0, 'salint' => 0, 'capmora' => 0];
            $auxAsesor = 0;
        }

        // Cambio de asesor
        if ($codasesor != $auxAsesor && $auxAsesor != 0) {
            $pdf->Ln(1);
            $pdf->SetFont($fuente, 'B', 7);
            $pdf->CellFit($ancho_linea * 2, 4, 'SUBTOTAL ASESOR', 'T', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, $resumenAsesor['cantidad'], 'T', 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['monto'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['cappag'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['intpag'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salcap'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salint'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salcap'] + $resumenAsesor['salint'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['capmora'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
            $pdf->Cell($ancho_linea / 2, 4, '', 'T', 1, 'C');

            $resumenAsesor = ['cantidad' => 0, 'monto' => 0, 'cappag' => 0, 'intpag' => 0, 'salcap' => 0, 'salint' => 0, 'capmora' => 0];
        }

        // Título de fondo
        if ($idfondo != $auxFondo) {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 9);
            $pdf->Cell(0, 5, 'FONDO: ' . strtoupper($nombrefondo), '', 1, 'L');
            $auxFondo = $idfondo;
        }

        // Título de asesor
        if ($codasesor != $auxAsesor) {
            $pdf->Ln(1);
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->Cell(0, 4, 'ASESOR: ' . strtoupper($asesor), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxAsesor = $codasesor;
        }

        // Línea de grupo
        $pdf->CellFit($ancho_linea * 2, 3.5, '  ' . strtoupper($grupo), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, $cantidad, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, number_format($monto, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, number_format($cappag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, number_format($intpag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, number_format($salcap, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, number_format($salint, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, number_format($salcap + $salint, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 3.5, number_format($capmora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->Cell($ancho_linea / 2, 3.5, $atraso_prom, '', 1, 'C');

        // Acumular
        $resumenAsesor['cantidad'] += $cantidad;
        $resumenAsesor['monto'] += $monto;
        $resumenAsesor['cappag'] += $cappag;
        $resumenAsesor['intpag'] += $intpag;
        $resumenAsesor['salcap'] += $salcap;
        $resumenAsesor['salint'] += $salint;
        $resumenAsesor['capmora'] += $capmora;

        $resumenFondo['cantidad'] += $cantidad;
        $resumenFondo['monto'] += $monto;
        $resumenFondo['cappag'] += $cappag;
        $resumenFondo['intpag'] += $intpag;
        $resumenFondo['salcap'] += $salcap;
        $resumenFondo['salint'] += $salint;
        $resumenFondo['capmora'] += $capmora;

        $fila++;
    }

    // Último subtotal de asesor
    if ($resumenAsesor['cantidad'] > 0) {
        $pdf->Ln(1);
        $pdf->SetFont($fuente, 'B', 7);
        $pdf->CellFit($ancho_linea * 2, 4, 'SUBTOTAL ASESOR', 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, $resumenAsesor['cantidad'], 'T', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['monto'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['cappag'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['intpag'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salcap'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salint'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['salcap'] + $resumenAsesor['salint'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, 4, number_format($resumenAsesor['capmora'], 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->Cell($ancho_linea / 2, 4, '', 'T', 1, 'C');
    }

    // Último total de fondo
    if ($resumenFondo['cantidad'] > 0) {
        $pdf->Ln(2);
        $pdf->SetFont($fuente, 'B', 8);
        $pdf->SetFillColor(255, 255, 153);
        $pdf->CellFit($ancho_linea * 2, 5, 'TOTAL FONDO', 'TB', 0, 'R', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, $resumenFondo['cantidad'], 'TB', 0, 'C', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['monto'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['cappag'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['intpag'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['salcap'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['salint'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['salcap'] + $resumenFondo['salint'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
        $pdf->CellFit($ancho_linea, 5, number_format($resumenFondo['capmora'], 2, '.', ','), 'TB', 0, 'R', true, '', 1, 0);
        $pdf->Cell($ancho_linea / 2, 5, '', 'TB', 1, 'C', true);
    }

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Resumen Cartera por Asesor",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function printxls($registro, $titlereport, $usuario, $mostrarRegionCol = false, $regionNombre = '')
{
    $hoy = date("Y-m-d H:i:s");
    $fuente = "Courier";
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("ResumenCartera");

    // Clasificaciones por días de atraso
    $clasificaciones = array(
        [
            'titulo' => 'AL DIA',
            'color' => 'FF99CC00',
            'mindays' => 0,
            'maxdays' => 0
        ],
        [
            'titulo' => '1 a 30 DIAS',
            'color' => 'FFFF0000',
            'mindays' => 1,
            'maxdays' => 30
        ],
        [
            'titulo' => '31 a 60 DIAS',
            'color' => 'FFFF9900',
            'mindays' => 31,
            'maxdays' => 60
        ],
        [
            'titulo' => '61 a 90 DIAS',
            'color' => 'FFFFCC00',
            'mindays' => 61,
            'maxdays' => 90
        ],
        [
            'titulo' => '91 A 180 DIAS',
            'color' => 'FF999999',
            'mindays' => 91,
            'maxdays' => 180
        ],
        [
            'titulo' => 'MAS DE 180 DIAS',
            'color' => 'FF666666',
            'mindays' => 181,
            'maxdays' => 9999
        ]
    );

    // Encabezado del reporte
    $activa->setCellValue("C1", $hoy);
    $activa->setCellValue("C2", $usuario);
    $activa->setCellValue("C4", "CARTERA CLASIFICADA " . strtoupper($titlereport));

    $activa->getStyle("C1:I1")->getFont()->setSize(9)->setName("Arial");
    $activa->getStyle("C2:I2")->getFont()->setSize(9)->setName("Arial");
    $activa->getStyle("C4:I4")->getFont()->setSize(11)->setName("Arial")->setBold(true);
    $activa->getStyle("C4:I4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->mergeCells('C1:I1');
    $activa->mergeCells('C2:I2');
    $activa->mergeCells('C4:I4');

    // Encabezado de tabla principal
    $encabezado_tabla = ["No.", "ENTIDAD", "TITULAR", "CANT.", "CICLO", "OTORGAMIENTO", "VENCIMIENTO", "DIA PAGO", "MONTO OTORG.", "SALDO CAP.", "CAP. MORA"];

    // Agregar columnas de clasificación de atraso
    foreach ($clasificaciones as $clasificacion) {
        $encabezado_tabla[] = $clasificacion['titulo'];
    }

    if ($mostrarRegionCol) {
        $encabezado_tabla[] = 'REGION';
    }

    $lastColumn = chr(65 + count($encabezado_tabla) - 1); // Calcular última columna
    $activa->fromArray($encabezado_tabla, null, 'A6')->getStyle('A6:' . $lastColumn . '6')->getFont()->setName($fuente)->setBold(true);
    $activa->getStyle("A6:" . $lastColumn . "6")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Aplicar colores a las columnas de clasificación
    for ($j = 0; $j < count($clasificaciones); $j++) {
        $colLetra = chr(65 + 11 + $j); // Columna L en adelante
        $activa->getStyle($colLetra . "6")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($clasificaciones[$j]['color']);
        $activa->getColumnDimension($colLetra)->setWidth(15);
    }

    // Variables de control
    $resumenAsesor = [
        'cantidad' => 0,
        'monto' => 0,
        'salcap' => 0,
        'capmora' => 0,
        'clasificacion' => array_fill(0, count($clasificaciones), 0)
    ];

    $auxAsesor = 0;
    $auxFondo = 0;
    $fila = 0;
    $i = 7;

    while ($fila < count($registro)) {
        $codasesor = $registro[$fila]["CodAnal"];
        $asesor = $registro[$fila]["analista"];
        $grupo = $registro[$fila]["nombreGrupo"];
        $titular = $registro[$fila]["nombreTitular"];
        $idfondo = $registro[$fila]["id_fondos"];
        $nombrefondo = $registro[$fila]["nombre_fondo"];
        $cantidad = $registro[$fila]["cantidad_creditos"];
        $monto = $registro[$fila]["monto_otorgado"];
        $salcap = $registro[$fila]["salcap"];
        $capmora = $registro[$fila]["capmora"];
        $atraso = $registro[$fila]["atraso_maximo"];
        $ciclo = $registro[$fila]["NCiclo"];
        $fechades = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
        $fechaven = ($registro[$fila]["DFecVen"]) ? date("d-m-Y", strtotime($registro[$fila]["DFecVen"])) : '-';
        $diapago = $registro[$fila]["diaPago"];

        // Determinar clasificación por días de atraso
        $clasificacionIndex = -1;
        for ($j = 0; $j < count($clasificaciones); $j++) {
            if ($atraso >= $clasificaciones[$j]['mindays'] && $atraso <= $clasificaciones[$j]['maxdays']) {
                $clasificacionIndex = $j;
                break;
            }
        }

        // Cambio de asesor
        if ($codasesor != $auxAsesor && $auxAsesor != 0) {
            $activa->getStyle("A" . $i . ":" . $lastColumn . $i)->getFont()->setBold(true);
            $activa->getStyle("A" . $i . ":" . $lastColumn . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('E0E0E0');

            $activa->setCellValue('C' . $i, 'SUBTOTAL ASESOR');
            $activa->setCellValue('D' . $i, $resumenAsesor['cantidad']);
            ExcelHelper::setCellByColumnRow($activa, 9, $i, $resumenAsesor['monto']);
            ExcelHelper::setCellByColumnRow($activa, 10, $i, $resumenAsesor['salcap']);
            ExcelHelper::setCellByColumnRow($activa, 11, $i, $resumenAsesor['capmora']);

            // Escribir clasificaciones
            for ($j = 0; $j < count($clasificaciones); $j++) {
                ExcelHelper::setCellByColumnRow($activa, 12 + $j, $i, $resumenAsesor['clasificacion'][$j]);
            }
            $i++;
            $i++; // Línea en blanco

            $resumenAsesor = ['cantidad' => 0, 'monto' => 0, 'salcap' => 0, 'capmora' => 0, 'clasificacion' => array_fill(0, count($clasificaciones), 0)];
        }

        // Título de asesor
        if ($codasesor != $auxAsesor) {
            $activa->getStyle("A" . $i . ":" . $lastColumn . $i)->getFont()->setBold(true)->setSize(10);
            $activa->getStyle("A" . $i . ":" . $lastColumn . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('CCE5FF');
            $activa->setCellValue('C' . $i, 'ASESOR: ' . strtoupper($asesor));
            $activa->mergeCells("C" . $i . ":" . $lastColumn . $i);
            $i++;
            $auxAsesor = $codasesor;
        }

        // Línea de grupo/crédito
        $valoresLinea = [
            ($fila + 1),
            strtoupper($grupo),
            strtoupper($titular),
            $cantidad,
            $ciclo,
            $fechades,
            $fechaven,
            $diapago,
            $monto,
            $salcap,
            $capmora
        ];

        // Agregar clasificación por días de atraso (solo mostrar en la columna correspondiente)
        for ($j = 0; $j < count($clasificaciones); $j++) {
            $valoresLinea[] = ($j == $clasificacionIndex) ? $salcap : 0;
        }

        if ($mostrarRegionCol) {
            $valoresLinea[] = $regionNombre;
        }

        ExcelHelper::setRowValues($activa, 1, $i, $valoresLinea);
        $activa->getStyle("A" . $i . ":" . $lastColumn . $i)->getFont()->setName($fuente);

        // Acumular
        $resumenAsesor['cantidad'] += $cantidad;
        $resumenAsesor['monto'] += $monto;
        $resumenAsesor['salcap'] += $salcap;
        $resumenAsesor['capmora'] += $capmora;
        if ($clasificacionIndex >= 0) {
            $resumenAsesor['clasificacion'][$clasificacionIndex] += $salcap;
        }

        $fila++;
        $i++;
    }

    // Último subtotal de asesor
    if ($resumenAsesor['cantidad'] > 0) {
        $activa->getStyle("A" . $i . ":" . $lastColumn . $i)->getFont()->setBold(true);
        $activa->getStyle("A" . $i . ":" . $lastColumn . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('E0E0E0');

        $activa->setCellValue('C' . $i, 'SUBTOTAL ASESOR');
        $activa->setCellValue('D' . $i, $resumenAsesor['cantidad']);
        ExcelHelper::setCellByColumnRow($activa, 9, $i, $resumenAsesor['monto']);
        ExcelHelper::setCellByColumnRow($activa, 10, $i, $resumenAsesor['salcap']);
        ExcelHelper::setCellByColumnRow($activa, 11, $i, $resumenAsesor['capmora']);

        for ($j = 0; $j < count($clasificaciones); $j++) {
            ExcelHelper::setCellByColumnRow($activa, 12 + $j, $i, $resumenAsesor['clasificacion'][$j]);
        }
        $i++;
    }

    $columnas = range('A', $lastColumn);
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
