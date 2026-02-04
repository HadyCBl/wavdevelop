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
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use Micro\Helpers\Log;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//[[`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[ $idusuario; ]]
$datos = $_POST["datosval"];

// Log::info("Datos recibidos: ", [json_encode($datos)]);

$selects = $datos[1];
$tipo = $_POST["tipo"];


$anioConsulta = $selects['anio'];


$queryEjecutivos = " WITH poa_con_fecha AS (
            SELECT poah.`year`,poah.id_ejecutivo,mes,cartera_creditos,clientes,cancel,grupos,colocaciones, 
                LAST_DAY(STR_TO_DATE(CONCAT(poah.`year`, '-', poa.mes, '-01'), '%Y-%m-%d')) AS fecha_limite
            FROM kpi_poa poa
            INNER JOIN kpi_poa_header poah ON poa.id_poa = poah.id
            )
            SELECT  
            usu.id_usu, 
            usu.nombre, 
            usu.apellido, 
            poa.mes, 
            poa.cartera_creditos,
            poa.clientes,
            -- poa.cancel,
            poa.grupos,
            poa.colocaciones,
            -- Créditos INDIVIDUALES
            (
                SELECT COUNT(DISTINCT crem.CodCli)
                FROM cremcre_meta crem
                WHERE crem.TipoEnti = 'INDI'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_clientes_indi,
            -- Créditos GRUPALES
            (
                SELECT COUNT(DISTINCT crem.CCodGrupo)
                FROM cremcre_meta crem
                WHERE crem.TipoEnti = 'GRUP'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_grupos,
            -- Colocaciones
            (
                SELECT SUM(crem.NCapDes)
                FROM cremcre_meta crem
                WHERE crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_colocaciones,
            -- Saldo de cartera: usando función de correlación
            (
                SELECT SUM(cremi.NCapDes - IFNULL(
                (SELECT SUM(k.KP)
                FROM CREDKAR k
                WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                )
                FROM cremcre_meta cremi
                INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                WHERE cremi.Cestado IN ('F', 'G')
                AND cremi.DFecDsbls <= poa.fecha_limite
                AND cremi.CodAnal = usu.id_usu
                AND (cremi.NCapDes - IFNULL(
                    (SELECT SUM(k.KP)
                    FROM CREDKAR k
                    WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                ) > 0
            ) AS saldo_cartera
            FROM tb_ejecutivos eje
            INNER JOIN tb_usuario usu ON usu.id_usu = eje.id_usuario
            LEFT JOIN poa_con_fecha poa ON usu.id_usu = poa.id_ejecutivo AND poa.`year` = ?;";

$queryAgencias = "WITH poa_con_fecha AS (
            SELECT poah.`year`,poah.id_ejecutivo,mes,cartera_creditos,clientes,cancel,grupos,colocaciones, 
                LAST_DAY(STR_TO_DATE(CONCAT(poah.`year`, '-', poa.mes, '-01'), '%Y-%m-%d')) AS fecha_limite
            FROM kpi_poa poa
            INNER JOIN kpi_poa_header poah ON poa.id_poa = poah.id
            )
            SELECT  
            age.id_agencia,
            age.nom_agencia,
            poa.mes, 
            SUM(poa.cartera_creditos) AS cartera_creditos,
            SUM(poa.clientes) AS clientes,
            SUM(poa.cancel) AS cancel,
            SUM(poa.grupos) AS grupos,
            SUM(poa.colocaciones) AS colocaciones,

            -- Créditos INDIVIDUALES
            (
                SELECT COUNT(DISTINCT crem.CodCli)
                FROM cremcre_meta crem
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
                WHERE crem.TipoEnti = 'INDI'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CODAgencia = age.cod_agenc
            ) AS total_clientes_indi,

            -- Créditos GRUPALES
            (
                SELECT COUNT(DISTINCT crem.CCodGrupo)
                FROM cremcre_meta crem
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
                WHERE crem.TipoEnti = 'GRUP'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CODAgencia = age.cod_agenc
            ) AS total_grupos,

            -- Colocaciones
            (
                SELECT SUM(crem.NCapDes)
                FROM cremcre_meta crem
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
                WHERE crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CODAgencia = age.cod_agenc
            ) AS total_colocaciones,

            -- Saldo de cartera por agencia
            (
                SELECT SUM(cremi.NCapDes - IFNULL(
                (SELECT SUM(k.KP)
                FROM CREDKAR k
                WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                )
                FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cremi.CodCli
                WHERE cremi.Cestado IN ('F', 'G')
                AND cremi.DFecDsbls <= poa.fecha_limite
                AND cremi.CODAgencia = age.cod_agenc
                AND (cremi.NCapDes - IFNULL(
                    (SELECT SUM(k.KP)
                    FROM CREDKAR k
                    WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                ) > 0
            ) AS saldo_cartera

            FROM tb_usuario usu
            INNER JOIN tb_agencia age ON age.id_agencia = usu.id_agencia
            LEFT JOIN poa_con_fecha poa ON usu.id_usu = poa.id_ejecutivo AND poa.`year` = ?
            WHERE poa.mes IS NOT NULL
            GROUP BY age.id_agencia, age.nom_agencia, poa.mes, poa.`year`
            ORDER BY age.nom_agencia, poa.mes;";
$showmensaje = false;
try {
    $database->openConnection();
    $datos = $database->getAllResults($queryEjecutivos, [$anioConsulta]);

    $datos2 = $database->getAllResults($queryAgencias, [$anioConsulta]);

    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

// echo json_encode(['status' => 0, 'mensaje' => $strquery]);
// return;

switch ($tipo) {
    case 'xlsx';
        printxls($datos, $datos2, $anioConsulta);
        break;
    case 'pdf':
        printpdf($datos, $datos2, $anioConsulta, 'nada');
        break;
}

//funcion para generar pdf
function printpdf($datosEjecutivosArray, $datosAgenciasArray, $anioConsulta, $info)
{

    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        //atributos de la clase
        public $info;
        public $anio;

        public function __construct($info, $anio)
        {
            parent::__construct();
            $this->info = $info;
            $this->anio = $anio;
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');

            $this->SetFont($fuente, 'B', 9);
            // Título
            // $this->Cell(0, 3, $this->info['nomb_comple'], 0, 1, 'C');
            // $this->Cell(0, 3, $this->info['muni_lug'], 0, 1, 'C');

            $this->Ln(4);

            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, decode_utf8('Reporte de Cumplimiento de Metas Mensuales - Año ' . $this->anio), 0, 1, 'C');
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 5, decode_utf8('Generado el: ') . date('d/m/Y H:i:s'), 0, 1, 'C');
            $this->Ln(5);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        function PrintSectionTitle($title)
        {
            $this->SetFont('Arial', 'B', 11);
            $this->SetFillColor(230, 230, 230);
            $this->Cell(0, 7, decode_utf8($title), 0, 1, 'L', true);
            $this->Ln(2);
        }

        function PrintTableHeader()
        {
            $this->SetFont('Arial', 'B', 7.5); // Tamaño de fuente reducido para cabecera
            $this->SetFillColor(200, 220, 255);
            $current_y = $this->GetY();
            $current_x = $this->GetX();

            // Primera fila de encabezados
            $this->Cell(27, 10, 'Mes', 1, 0, 'C', true); // Celda 'Mes' con altura de 2*5=10
            $this->SetXY($current_x + 27, $current_y); // Posicionar para el siguiente grupo

            $this->Cell(80, 5, 'Metas POA', 1, 0, 'C', true);
            $this->Cell(80, 5, 'Resultados Reales', 1, 0, 'C', true);
            $this->Cell(54, 5, 'Cumplimiento (%)', 1, 1, 'C', true); // Salto de línea al final

            // Segunda fila de encabezados (debajo de los grupos)
            $this->SetX($current_x + 27); // Alineado después de 'Mes'
            $this->Cell(22, 5, 'Cartera', 1, 0, 'C', true);
            $this->Cell(18, 5, 'Clientes', 1, 0, 'C', true);
            $this->Cell(18, 5, 'Grupos', 1, 0, 'C', true);
            $this->Cell(22, 5, 'Colocacion', 1, 0, 'C', true);

            $this->Cell(22, 5, 'Saldo Cart.', 1, 0, 'C', true);
            $this->Cell(18, 5, 'Clientes', 1, 0, 'C', true);
            $this->Cell(18, 5, 'Grupos', 1, 0, 'C', true);
            $this->Cell(22, 5, 'Colocacion', 1, 0, 'C', true);

            $this->Cell(18, 5, 'Cart.', 1, 0, 'C', true);
            $this->Cell(18, 5, 'Clientes', 1, 0, 'C', true);
            $this->Cell(18, 5, 'Coloc.', 1, 1, 'C', true); // Salto de línea
        }

        function PrintTableRow($mes, $poaData, $realData, $percData, $isTotal = false)
        {
            $h = 6; // Altura de celda
            // Verificar salto de página antes de imprimir la fila
            if ($this->GetY() + $h > $this->PageBreakTrigger) {
                $this->AddPage($this->CurOrientation);
                $this->PrintTableHeader(); // Repintar encabezado en nueva página
            }

            $fontStyle = $isTotal ? 'B' : '';
            $this->SetFont('Arial', $fontStyle, 7);

            $fill = $isTotal ? true : false;
            if ($isTotal) {
                $this->SetFillColor(240, 240, 240);
            }

            $this->Cell(27, $h, decode_utf8($mes), 1, 0, 'L', $fill);
            // POA
            $this->Cell(22, $h, number_format(floatval($poaData['cartera']), 2), 1, 0, 'R', $fill);
            $this->Cell(18, $h, intval($poaData['clientes']), 1, 0, 'C', $fill);
            $this->Cell(18, $h, intval($poaData['grupos']), 1, 0, 'C', $fill);
            $this->Cell(22, $h, number_format(floatval($poaData['colocacion']), 2), 1, 0, 'R', $fill);
            // Reales
            $this->Cell(22, $h, number_format(floatval($realData['cartera']), 2), 1, 0, 'R', $fill);
            $this->Cell(18, $h, intval($realData['clientes']), 1, 0, 'C', $fill);
            $this->Cell(18, $h, intval($realData['grupos']), 1, 0, 'C', $fill);
            $this->Cell(22, $h, number_format(floatval($realData['colocacion']), 2), 1, 0, 'R', $fill);
            // Cumplimiento
            $this->Cell(18, $h, number_format(floatval($percData['cartera']), 2) . '%', 1, 0, 'C', $fill);
            $this->Cell(18, $h, number_format(floatval($percData['clientes']), 2) . '%', 1, 0, 'C', $fill);
            $this->Cell(18, $h, number_format(floatval($percData['colocacion']), 2) . '%', 1, 1, 'C', $fill); // Salto de línea
        }
    }
    $pdf = new PDF($info, $anioConsulta);
    $nombresMeses = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];

    // Agrupar datos por ejecutivo
    $ejecutivosData = [];
    if (is_array($datosEjecutivosArray)) {
        foreach ($datosEjecutivosArray as $dato) {
            $ejecutivosData[$dato['id_usu']]['nombre'] = $dato['nombre'] . ' ' . $dato['apellido'];
            if ($dato['mes'] !== null && $dato['mes'] !== '') {
                $ejecutivosData[$dato['id_usu']]['meses'][] = $dato;
            } else if (!isset($ejecutivosData[$dato['id_usu']]['meses'])) {
                $ejecutivosData[$dato['id_usu']]['meses'] = [];
            }
        }
    }

    // Agrupar datos por agencia
    $agenciasData = [];
    if (is_array($datosAgenciasArray)) {
        foreach ($datosAgenciasArray as $dato) {
            $agenciasData[$dato['id_agencia']]['nombre'] = $dato['nom_agencia'];
            if ($dato['mes'] !== null && $dato['mes'] !== '') {
                $agenciasData[$dato['id_agencia']]['meses'][] = $dato;
            } else if (!isset($agenciasData[$dato['id_agencia']]['meses'])) {
                $agenciasData[$dato['id_agencia']]['meses'] = [];
            }
        }
    }

    $pdf->AliasNbPages();

    // --- SECCIÓN EJECUTIVOS ---
    if (!empty($ejecutivosData)) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, decode_utf8('DETALLE POR EJECUTIVOS'), 0, 1, 'C');
        $pdf->Ln(2);

        foreach ($ejecutivosData as $idEjecutivo => $dataEjecutivo) {
            $pdf->PrintSectionTitle('Ejecutivo: ' . $dataEjecutivo['nombre']);

            if (empty($dataEjecutivo['meses'])) {
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(0, 7, decode_utf8('No hay datos de metas mensuales para este ejecutivo.'), 0, 1, 'L');
                $pdf->Ln(3);
                continue;
            }

            $pdf->PrintTableHeader();

            $totalesPoa = ['cartera' => 0, 'clientes' => 0, 'grupos' => 0, 'colocacion' => 0];
            $totalesReal = ['cartera' => 0, 'clientes' => 0, 'grupos' => 0, 'colocacion' => 0];

            foreach ($dataEjecutivo['meses'] as $mesData) {
                $poa = [
                    'cartera' => $mesData['cartera_creditos'],
                    'clientes' => $mesData['clientes'],
                    'grupos' => $mesData['grupos'],
                    'colocacion' => $mesData['colocaciones']
                ];
                $real = [
                    'cartera' => $mesData['saldo_cartera'],
                    'clientes' => $mesData['total_clientes_indi'],
                    'grupos' => $mesData['total_grupos'],
                    'colocacion' => $mesData['total_colocaciones']
                ];
                $perc = [
                    'cartera' => calcularPorcentajePdf($real['cartera'], $poa['cartera']),
                    'clientes' => calcularPorcentajePdf($real['clientes'], $poa['clientes']),
                    'colocacion' => calcularPorcentajePdf($real['colocacion'], $poa['colocacion'])
                ];
                $pdf->PrintTableRow($nombresMeses[$mesData['mes']] ?? 'N/A', $poa, $real, $perc);

                foreach (['cartera', 'clientes', 'grupos', 'colocacion'] as $key) {
                    $totalesPoa[$key] += floatval($poa[$key]);
                    $totalesReal[$key] += floatval($real[$key]);
                }
            }
            // Fila de totales para el ejecutivo
            $percTotales = [
                'cartera' => calcularPorcentajePdf($totalesReal['cartera'], $totalesPoa['cartera']),
                'clientes' => calcularPorcentajePdf($totalesReal['clientes'], $totalesPoa['clientes']),
                'colocacion' => calcularPorcentajePdf($totalesReal['colocacion'], $totalesPoa['colocacion'])
            ];
            $pdf->PrintTableRow('TOTALES', $totalesPoa, $totalesReal, $percTotales, true);
            $pdf->Ln(5);
        }
    } else {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, decode_utf8('DETALLE POR EJECUTIVOS'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, decode_utf8('No se encontraron datos de ejecutivos para el año ' . $anioConsulta . '.'), 0, 1, 'C');
    }


    // --- SECCIÓN AGENCIAS ---
    if (!empty($agenciasData)) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, decode_utf8('DETALLE POR AGENCIAS'), 0, 1, 'C');
        $pdf->Ln(2);

        foreach ($agenciasData as $idAgencia => $dataAgencia) {
            $pdf->PrintSectionTitle('Agencia: ' . $dataAgencia['nombre']);

            if (empty($dataAgencia['meses'])) {
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(0, 7, decode_utf8('No hay datos de metas mensuales para esta agencia.'), 0, 1, 'L');
                $pdf->Ln(3);
                continue;
            }

            $pdf->PrintTableHeader();

            $totalesPoa = ['cartera' => 0, 'clientes' => 0, 'grupos' => 0, 'colocacion' => 0];
            $totalesReal = ['cartera' => 0, 'clientes' => 0, 'grupos' => 0, 'colocacion' => 0];

            foreach ($dataAgencia['meses'] as $mesData) {
                $poa = [
                    'cartera' => $mesData['cartera_creditos'],
                    'clientes' => $mesData['clientes'],
                    'grupos' => $mesData['grupos'],
                    'colocacion' => $mesData['colocaciones']
                ];
                $real = [
                    'cartera' => $mesData['saldo_cartera'],
                    'clientes' => $mesData['total_clientes_indi'],
                    'grupos' => $mesData['total_grupos'],
                    'colocacion' => $mesData['total_colocaciones']
                ];
                $perc = [
                    'cartera' => calcularPorcentajePdf($real['cartera'], $poa['cartera']),
                    'clientes' => calcularPorcentajePdf($real['clientes'], $poa['clientes']),
                    'colocacion' => calcularPorcentajePdf($real['colocacion'], $poa['colocacion'])
                ];
                $pdf->PrintTableRow($nombresMeses[$mesData['mes']] ?? 'N/A', $poa, $real, $perc);

                foreach (['cartera', 'clientes', 'grupos', 'colocacion'] as $key) {
                    $totalesPoa[$key] += floatval($poa[$key]);
                    $totalesReal[$key] += floatval($real[$key]);
                }
            }
            // Fila de totales para la agencia
            $percTotales = [
                'cartera' => calcularPorcentajePdf($totalesReal['cartera'], $totalesPoa['cartera']),
                'clientes' => calcularPorcentajePdf($totalesReal['clientes'], $totalesPoa['clientes']),
                'colocacion' => calcularPorcentajePdf($totalesReal['colocacion'], $totalesPoa['colocacion'])
            ];
            $pdf->PrintTableRow('TOTALES', $totalesPoa, $totalesReal, $percTotales, true);
            $pdf->Ln(5);
        }
    } else {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, decode_utf8('DETALLE POR AGENCIAS'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 7, decode_utf8('No se encontraron datos de agencias para el año ' . $anioConsulta . '.'), 0, 1, 'C');
    }


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


function calcularPorcentajePdf($obtenido, $meta)
{
    if ($meta == 0) {
        return ($obtenido > 0) ? 100.00 : 0.00;
    }
    return round(($obtenido / $meta) * 100, 2);
}
//funcion para generar archivo excel
function printxls($datosEjecutivosArray, $datosAgenciasArray, $anio)
{
    $excel = new Spreadsheet();

    // ---- HOJA DE EJECUTIVOS ----
    $sheetEjecutivos = $excel->getActiveSheet();
    $sheetEjecutivos->setTitle("Metas Ejecutivos " . $anio);

    // Helpers
    $nombresMeses = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    ];

    // Agrupar datos por ejecutivo
    $ejecutivosData = [];
    if (is_array($datosEjecutivosArray)) {
        foreach ($datosEjecutivosArray as $dato) {
            $ejecutivosData[$dato['id_usu']]['nombre'] = $dato['nombre'] . ' ' . $dato['apellido'];
            if ($dato['mes'] !== null && $dato['mes'] !== '') {
                $ejecutivosData[$dato['id_usu']]['meses'][] = $dato;
            } else if (!isset($ejecutivosData[$dato['id_usu']]['meses'])) {
                $ejecutivosData[$dato['id_usu']]['meses'] = [];
            }
        }
    }

    // Si quieres mostrar el símbolo de Quetzal (Q), usa un formato personalizado:
    $quetzalFormat = '"Q "#,##0.00';

    $row = 1;
    $sheetEjecutivos->setCellValue('A' . $row, 'Reporte de Cumplimiento de Metas por Ejecutivo - Año ' . $anio);
    $sheetEjecutivos->mergeCells('A' . $row . ':M' . $row);
    $sheetEjecutivos->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheetEjecutivos->getStyle('A' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $row += 2;

    if (empty($ejecutivosData)) {
        $sheetEjecutivos->setCellValue('A' . $row, 'No hay datos de ejecutivos para mostrar para el año ' . $anio);
        $row++;
    } else {
        foreach ($ejecutivosData as $idEjecutivo => $dataEjecutivo) {
            $sheetEjecutivos->setCellValue('A' . $row, 'Ejecutivo: ' . htmlspecialchars($dataEjecutivo['nombre']));
            $sheetEjecutivos->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;

            if (empty($dataEjecutivo['meses'])) {
                $sheetEjecutivos->setCellValue('A' . $row, 'No hay datos de metas mensuales para este ejecutivo.');
                $row += 2;
                continue;
            }

            // Encabezados de la tabla
            $headerRow = $row;
            $sheetEjecutivos->setCellValue('A' . $row, 'Mes');
            $sheetEjecutivos->mergeCells('A' . $row . ':A' . ($row + 1));

            $sheetEjecutivos->setCellValue('B' . $row, 'Metas POA');
            $sheetEjecutivos->mergeCells('B' . $row . ':E' . $row);
            $sheetEjecutivos->setCellValue('B' . ($row + 1), 'Cartera');
            $sheetEjecutivos->setCellValue('C' . ($row + 1), 'Clientes');
            $sheetEjecutivos->setCellValue('D' . ($row + 1), 'Grupos');
            $sheetEjecutivos->setCellValue('E' . ($row + 1), 'Colocación');

            $sheetEjecutivos->setCellValue('F' . $row, 'Resultados Reales');
            $sheetEjecutivos->mergeCells('F' . $row . ':I' . $row);
            $sheetEjecutivos->setCellValue('F' . ($row + 1), 'Saldo Cart.');
            $sheetEjecutivos->setCellValue('G' . ($row + 1), 'Clientes');
            $sheetEjecutivos->setCellValue('H' . ($row + 1), 'Grupos');
            $sheetEjecutivos->setCellValue('I' . ($row + 1), 'Colocación');

            $sheetEjecutivos->setCellValue('J' . $row, 'Cumplimiento (%)');
            $sheetEjecutivos->mergeCells('J' . $row . ':L' . $row);
            $sheetEjecutivos->setCellValue('J' . ($row + 1), 'Cart.');
            $sheetEjecutivos->setCellValue('K' . ($row + 1), 'Clientes');
            $sheetEjecutivos->setCellValue('L' . ($row + 1), 'Coloc.');

            // Estilo para encabezados
            $headerStyle = [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD3D3D3']]
            ];
            $sheetEjecutivos->getStyle('A' . $headerRow . ':L' . ($headerRow + 1))->applyFromArray($headerStyle);
            $row += 2; // Avanzar después de los encabezados de dos filas

            // Datos
            $totalesPoaCartera = 0;
            $totalesPoaClientes = 0;
            $totalesPoaGrupos = 0;
            $totalesPoaColocaciones = 0;
            $totalesRealCartera = 0;
            $totalesRealClientes = 0;
            $totalesRealGrupos = 0;
            $totalesRealColocaciones = 0;
            $startDataRow = $row;

            foreach ($dataEjecutivo['meses'] as $mesData) {
                $sheetEjecutivos->setCellValue('A' . $row, htmlspecialchars($nombresMeses[$mesData['mes']] ?? 'N/A'));
                $sheetEjecutivos->setCellValue('B' . $row, floatval($mesData['cartera_creditos']));
                $sheetEjecutivos->setCellValue('C' . $row, intval($mesData['clientes']));
                $sheetEjecutivos->setCellValue('D' . $row, intval($mesData['grupos']));
                $sheetEjecutivos->setCellValue('E' . $row, floatval($mesData['colocaciones']));

                $sheetEjecutivos->setCellValue('F' . $row, floatval($mesData['saldo_cartera']));
                $sheetEjecutivos->setCellValue('G' . $row, intval($mesData['total_clientes_indi']));
                $sheetEjecutivos->setCellValue('H' . $row, intval($mesData['total_grupos']));
                $sheetEjecutivos->setCellValue('I' . $row, floatval($mesData['total_colocaciones']));

                $porcentajeCartera = calcularPorcentajeXls(floatval($mesData['saldo_cartera']), floatval($mesData['cartera_creditos']));
                $porcentajeClientes = calcularPorcentajeXls(intval($mesData['total_clientes_indi']), intval($mesData['clientes']));
                $porcentajeColocaciones = calcularPorcentajeXls(floatval($mesData['total_colocaciones']), floatval($mesData['colocaciones']));

                $sheetEjecutivos->setCellValue('J' . $row, $porcentajeCartera);
                $sheetEjecutivos->setCellValue('K' . $row, $porcentajeClientes);
                $sheetEjecutivos->setCellValue('L' . $row, $porcentajeColocaciones);

                // Acumular totales
                $totalesPoaCartera += floatval($mesData['cartera_creditos']);
                $totalesPoaClientes += intval($mesData['clientes']);
                $totalesPoaGrupos += intval($mesData['grupos']);
                $totalesPoaColocaciones += floatval($mesData['colocaciones']);
                $totalesRealCartera += floatval($mesData['saldo_cartera']);
                $totalesRealClientes += intval($mesData['total_clientes_indi']);
                $totalesRealGrupos += intval($mesData['total_grupos']);
                $totalesRealColocaciones += floatval($mesData['total_colocaciones']);
                $row++;
            }
            $endDataRow = $row - 1;

            // Totales
            $sheetEjecutivos->setCellValue('A' . $row, 'TOTALES');
            $sheetEjecutivos->setCellValue('B' . $row, $totalesPoaCartera);
            $sheetEjecutivos->setCellValue('C' . $row, $totalesPoaClientes);
            $sheetEjecutivos->setCellValue('D' . $row, $totalesPoaGrupos);
            $sheetEjecutivos->setCellValue('E' . $row, $totalesPoaColocaciones);
            $sheetEjecutivos->setCellValue('F' . $row, $totalesRealCartera);
            $sheetEjecutivos->setCellValue('G' . $row, $totalesRealClientes);
            $sheetEjecutivos->setCellValue('H' . $row, $totalesRealGrupos);
            $sheetEjecutivos->setCellValue('I' . $row, $totalesRealColocaciones);

            $sheetEjecutivos->setCellValue('J' . $row, calcularPorcentajeXls($totalesRealCartera, $totalesPoaCartera));
            $sheetEjecutivos->setCellValue('K' . $row, calcularPorcentajeXls($totalesRealClientes, $totalesPoaClientes));
            $sheetEjecutivos->setCellValue('L' . $row, calcularPorcentajeXls($totalesRealColocaciones, $totalesPoaColocaciones));
            $sheetEjecutivos->getStyle('A' . $row . ':L' . $row)->getFont()->setBold(true);
            $sheetEjecutivos->getStyle('A' . $row . ':L' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');


            // Aplicar formatos de número
            // Quitar formato de moneda o usar símbolo de Quetzal (Q)
            // Si solo quieres quitar el formato, comenta o elimina las siguientes líneas:
            // $sheetEjecutivos->getStyle('B' . $startDataRow . ':B' . $row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            // $sheetEjecutivos->getStyle('E' . $startDataRow . ':E' . $row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            // $sheetEjecutivos->getStyle('F' . $startDataRow . ':F' . $row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
            // $sheetEjecutivos->getStyle('I' . $startDataRow . ':I' . $row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);


            $sheetEjecutivos->getStyle('B' . $startDataRow . ':B' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetEjecutivos->getStyle('E' . $startDataRow . ':E' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetEjecutivos->getStyle('F' . $startDataRow . ':F' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetEjecutivos->getStyle('I' . $startDataRow . ':I' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetEjecutivos->getStyle('J' . $startDataRow . ':L' . $row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);

            // Bordes para datos y totales
            $sheetEjecutivos->getStyle('A' . $startDataRow . ':L' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            $row += 2; // Espacio antes del siguiente ejecutivo
        }
    }
    // Ajustar ancho de columnas para Ejecutivos
    foreach (range('A', 'L') as $columnID) {
        $sheetEjecutivos->getColumnDimension($columnID)->setAutoSize(true);
    }


    // ---- HOJA DE AGENCIAS ----
    $sheetAgencias = $excel->createSheet();
    $sheetAgencias->setTitle("Metas Agencias " . $anio);

    // Agrupar datos por agencia
    $agenciasData = [];
    if (is_array($datosAgenciasArray)) {
        foreach ($datosAgenciasArray as $dato) {
            $agenciasData[$dato['id_agencia']]['nombre'] = $dato['nom_agencia'];
            if ($dato['mes'] !== null && $dato['mes'] !== '') {
                $agenciasData[$dato['id_agencia']]['meses'][] = $dato;
            } else if (!isset($agenciasData[$dato['id_agencia']]['meses'])) {
                $agenciasData[$dato['id_agencia']]['meses'] = [];
            }
        }
    }

    $row = 1; // Reiniciar contador de filas para la nueva hoja
    $sheetAgencias->setCellValue('A' . $row, 'Reporte de Cumplimiento de Metas por Agencia - Año ' . $anio);
    $sheetAgencias->mergeCells('A' . $row . ':M' . $row);
    $sheetAgencias->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
    $sheetAgencias->getStyle('A' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $row += 2;

    if (empty($agenciasData)) {
        $sheetAgencias->setCellValue('A' . $row, 'No hay datos de agencias para mostrar para el año ' . $anio);
        $row++;
    } else {
        foreach ($agenciasData as $idAgencia => $dataAgencia) {
            $sheetAgencias->setCellValue('A' . $row, 'Agencia: ' . htmlspecialchars($dataAgencia['nombre']));
            $sheetAgencias->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;

            if (empty($dataAgencia['meses'])) {
                $sheetAgencias->setCellValue('A' . $row, 'No hay datos de metas mensuales para esta agencia.');
                $row += 2;
                continue;
            }

            // Encabezados de la tabla (igual que para ejecutivos)
            $headerRow = $row;
            $sheetAgencias->setCellValue('A' . $row, 'Mes');
            $sheetAgencias->mergeCells('A' . $row . ':A' . ($row + 1));
            $sheetAgencias->setCellValue('B' . $row, 'Metas POA');
            $sheetAgencias->mergeCells('B' . $row . ':E' . $row);
            $sheetAgencias->setCellValue('B' . ($row + 1), 'Cartera');
            $sheetAgencias->setCellValue('C' . ($row + 1), 'Clientes');
            $sheetAgencias->setCellValue('D' . ($row + 1), 'Grupos');
            $sheetAgencias->setCellValue('E' . ($row + 1), 'Colocación');
            $sheetAgencias->setCellValue('F' . $row, 'Resultados Reales');
            $sheetAgencias->mergeCells('F' . $row . ':I' . $row);
            $sheetAgencias->setCellValue('F' . ($row + 1), 'Saldo Cart.');
            $sheetAgencias->setCellValue('G' . ($row + 1), 'Clientes');
            $sheetAgencias->setCellValue('H' . ($row + 1), 'Grupos');
            $sheetAgencias->setCellValue('I' . ($row + 1), 'Colocación');
            $sheetAgencias->setCellValue('J' . $row, 'Cumplimiento (%)');
            $sheetAgencias->mergeCells('J' . $row . ':L' . $row);
            $sheetAgencias->setCellValue('J' . ($row + 1), 'Cart.');
            $sheetAgencias->setCellValue('K' . ($row + 1), 'Clientes');
            $sheetAgencias->setCellValue('L' . ($row + 1), 'Coloc.');

            $headerStyle = [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD3D3D3']]
            ];
            $sheetAgencias->getStyle('A' . $headerRow . ':L' . ($headerRow + 1))->applyFromArray($headerStyle);
            $row += 2;

            // Datos
            $totalesPoaCartera = 0;
            $totalesPoaClientes = 0;
            $totalesPoaGrupos = 0;
            $totalesPoaColocaciones = 0;
            $totalesRealCartera = 0;
            $totalesRealClientes = 0;
            $totalesRealGrupos = 0;
            $totalesRealColocaciones = 0;
            $startDataRow = $row;

            foreach ($dataAgencia['meses'] as $mesData) {
                $sheetAgencias->setCellValue('A' . $row, htmlspecialchars($nombresMeses[$mesData['mes']] ?? 'N/A'));
                $sheetAgencias->setCellValue('B' . $row, floatval($mesData['cartera_creditos']));
                $sheetAgencias->setCellValue('C' . $row, intval($mesData['clientes']));
                $sheetAgencias->setCellValue('D' . $row, intval($mesData['grupos']));
                $sheetAgencias->setCellValue('E' . $row, floatval($mesData['colocaciones']));

                $sheetAgencias->setCellValue('F' . $row, floatval($mesData['saldo_cartera']));
                $sheetAgencias->setCellValue('G' . $row, intval($mesData['total_clientes_indi']));
                $sheetAgencias->setCellValue('H' . $row, intval($mesData['total_grupos']));
                $sheetAgencias->setCellValue('I' . $row, floatval($mesData['total_colocaciones']));

                $porcentajeCartera = calcularPorcentajeXls(floatval($mesData['saldo_cartera']), floatval($mesData['cartera_creditos']));
                $porcentajeClientes = calcularPorcentajeXls(intval($mesData['total_clientes_indi']), intval($mesData['clientes']));
                $porcentajeColocaciones = calcularPorcentajeXls(floatval($mesData['total_colocaciones']), floatval($mesData['colocaciones']));

                $sheetAgencias->setCellValue('J' . $row, $porcentajeCartera);
                $sheetAgencias->setCellValue('K' . $row, $porcentajeClientes);
                $sheetAgencias->setCellValue('L' . $row, $porcentajeColocaciones);

                // Acumular totales
                $totalesPoaCartera += floatval($mesData['cartera_creditos']);
                $totalesPoaClientes += intval($mesData['clientes']);
                $totalesPoaGrupos += intval($mesData['grupos']);
                $totalesPoaColocaciones += floatval($mesData['colocaciones']);
                $totalesRealCartera += floatval($mesData['saldo_cartera']);
                $totalesRealClientes += intval($mesData['total_clientes_indi']);
                $totalesRealGrupos += intval($mesData['total_grupos']);
                $totalesRealColocaciones += floatval($mesData['total_colocaciones']);
                $row++;
            }
            $endDataRow = $row - 1;

            // Totales
            $sheetAgencias->setCellValue('A' . $row, 'TOTALES');
            $sheetAgencias->setCellValue('B' . $row, $totalesPoaCartera);
            $sheetAgencias->setCellValue('C' . $row, $totalesPoaClientes);
            $sheetAgencias->setCellValue('D' . $row, $totalesPoaGrupos);
            $sheetAgencias->setCellValue('E' . $row, $totalesPoaColocaciones);
            $sheetAgencias->setCellValue('F' . $row, $totalesRealCartera);
            $sheetAgencias->setCellValue('G' . $row, $totalesRealClientes);
            $sheetAgencias->setCellValue('H' . $row, $totalesRealGrupos);
            $sheetAgencias->setCellValue('I' . $row, $totalesRealColocaciones);

            $sheetAgencias->setCellValue('J' . $row, calcularPorcentajeXls($totalesRealCartera, $totalesPoaCartera));
            $sheetAgencias->setCellValue('K' . $row, calcularPorcentajeXls($totalesRealClientes, $totalesPoaClientes));
            $sheetAgencias->setCellValue('L' . $row, calcularPorcentajeXls($totalesRealColocaciones, $totalesPoaColocaciones));
            $sheetAgencias->getStyle('A' . $row . ':L' . $row)->getFont()->setBold(true);
            $sheetAgencias->getStyle('A' . $row . ':L' . $row)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');

            // Aplicar formatos de número
            $sheetAgencias->getStyle('B' . $startDataRow . ':B' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetAgencias->getStyle('E' . $startDataRow . ':E' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetAgencias->getStyle('F' . $startDataRow . ':F' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetAgencias->getStyle('I' . $startDataRow . ':I' . $row)->getNumberFormat()->setFormatCode($quetzalFormat);
            $sheetAgencias->getStyle('J' . $startDataRow . ':L' . $row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);

            // Bordes para datos y totales
            $sheetAgencias->getStyle('A' . $startDataRow . ':L' . $row)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            $row += 2; // Espacio antes de la siguiente agencia
        }
    }
    // Ajustar ancho de columnas para Agencias
    foreach (range('A', 'L') as $columnID) {
        $sheetAgencias->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Establecer la primera hoja como activa al abrir
    $excel->setActiveSheetIndex(0);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Reporte_Metas_Mensuales_" . $anio, // Nombre de archivo dinámico
        'tipo' => "vnd.ms-excel", // Mime type para .xlsx
        'data' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," . base64_encode($xlsData) // Mime type correcto para .xlsx
    );
    echo json_encode($opResult);
    exit;
}

function calcularPorcentajeXls($obtenido, $meta)
{
    if ($meta == 0) {
        return ($obtenido > 0) ? 1 : 0; // 100% o 0%
    }
    return round(($obtenido / $meta), 4); // Devuelve como decimal para formato de porcentaje en Excel
}
