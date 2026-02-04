<?php
/*
 * REPORTE: RESUMEN DE CARTERAS POR EJECUTIVOS (POR CLIENTES Y GRUPOS)
 * Versión: Con datos reales de base de datos
 * Propósito: Generar PDF y Excel agrupado por clientes y grupos
 * Fecha: 2026-01-08
 */
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

use App\DatabaseAdapter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ========== RECEPCIÓN DE DATOS ==========
// [[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`,`rregion`],[usuario]]
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

// Validar fecha
if (!validateDate($inputs[0], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    exit;
}

// Validaciones según radios
if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar fuente de fondos']);
    exit;
}
if ($radios[3] == "anyasesor" && $selects[2] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Asesor']);
    exit;
}
if ($radios[4] == "anyregion" && (empty($selects[3]) || $selects[3] == 0)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    exit;
}

// Extraer parámetros
$fecha_corte = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($fecha_corte));
$usuario = $archivo[0];

// Construir filtros SQL (igual que cartera_consolidada.php)
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . (int)$selects[0] : "";
$filfondo = ($radios[1] == "anyf") ? " AND prod.id_fondo = " . (int)$selects[1] : "";
$filasesor = ($radios[3] == "anyasesor") ? " AND cremi.CodAnal = " . (int)$selects[2] : "";
$filregion = ($radios[4] == "anyregion" && !empty($selects[3])) 
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . (int)$selects[3] . ")"
    : "";
// El filtro de status se aplica después en el proceso, no en el WHERE principal
$status = "";

// Determinar si mostrar región y nombre de región
$mostrarRegionCol = ($radios[4] == "anyregion" && !empty($selects[3]));
$regionNombre = '';
if ($mostrarRegionCol) {
    try {
        $database_temp = new DatabaseAdapter();
        $database_temp->openConnection();
        $region_result = $database_temp->getAllResults(
            "SELECT nombre_region FROM cre_regiones WHERE id_region = ?",
            [$selects[3]]
        );
        if (!empty($region_result)) {
            $regionNombre = $region_result[0]['nombre_region'];
        }
        $database_temp->closeConnection();
    } catch (Exception $e) {
        // Si falla, regionNombre queda vacío
    }
}

// ========== CONEXIÓN A BASE DE DATOS ==========
$database = new DatabaseAdapter();
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

// ========== CONSULTA PRINCIPAL (AGRUPADO POR CLIENTES Y GRUPOS) ==========
$strquery = "
SELECT 
    cremi.CODAgencia,
    ag.nom_agencia AS nombre_agencia,
    cremi.CodAnal,
    CONCAT(usu.nombre, ' ', usu.apellido) AS nombre_asesor,
    cremi.CodCli,
    cli.short_name AS nombre_cliente,
    IFNULL(cremi.CCodGrupo, 0) AS CCodGrupo,
    IF(cremi.CCodGrupo > 0, 'GRUPAL', 'INDIVIDUAL') AS tipo_credito,
    cremi.CCODCTA AS cuenta,
    cremi.NCapDes AS monto_otorgado,
    GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0)) AS saldo,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso('$fecha_corte', cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS dias_atraso,
    GREATEST(0, IFNULL(ppg_ult.sum_ncapita, 0) - IFNULL(kar.sum_KP, 0)) AS capital_mora_total,
    cremi.DFecDsbls
FROM cremcre_meta cremi
INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
LEFT JOIN tb_agencia ag ON ag.id_agencia = cremi.CODAgencia
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP
    FROM CREDKAR
    WHERE dfecpro <= '$fecha_corte'
      AND cestado != 'X'
      AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, SUM(ncapita) AS sum_ncapita
    FROM Cre_ppg
    WHERE dfecven <= '$fecha_corte'
    GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND cremi.DFecDsbls <= '$fecha_corte'
  {$filfondo}
  {$filagencia}
  {$filasesor}
  {$filregion}
  {$status}
ORDER BY cremi.CODAgencia, cremi.CodAnal, cremi.CCodGrupo, cremi.CodCli
";

$showmensaje = false;
try {
    $database->openConnection();
    
    // Ejecutar consulta sin par\u00e1metros (valores ya est\u00e1n en el query como cartera_consolidada.php)
    $result = $database->getAllResults($strquery, []);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }
    
    // PROCESAR RESULTADOS: Agrupar por agencia -> asesor -> grupo -> cliente
    $agencias = [];
    $agencias_index = [];
    
    foreach ($result as $row) {
        // Aplicar filtro de status si es necesario
        $saldo = floatval($row['saldo']);
        if ($radios[2] == "F" && $saldo <= 0) {
            continue; // Saltar si se pide solo finiquitados y el saldo es > 0
        }
        if ($radios[2] == "V" && $saldo > 0) {
            continue; // Saltar si se pide solo vigentes y el saldo es <= 0
        }
        
        $id_agencia = $row['CODAgencia'];
        $cod_anal = $row['CodAnal'];
        $cod_grupo = intval($row['CCodGrupo']);
        $cod_cliente = $row['CodCli'];
        $tipo_credito = $row['tipo_credito']; // 'GRUPAL' o 'INDIVIDUAL'
        
        // Calcular clasificación por mora
        $dias_atraso = intval($row['dias_atraso']);
        $capital_mora = floatval($row['capital_mora_total']);
        $saldo_total = floatval($row['saldo']);
        
        // VIGENTE = saldo - capital_mora
        $vigente = max(0, $saldo_total - $capital_mora);
        
        // Clasificar mora por rangos
        $mora_1_30 = 0;
        $mora_31_60 = 0;
        $mora_61_90 = 0;
        $mora_91_180 = 0;
        $mora_mas_180 = 0;
        
        if ($dias_atraso >= 1 && $dias_atraso <= 30) {
            $mora_1_30 = $capital_mora;
        } elseif ($dias_atraso >= 31 && $dias_atraso <= 60) {
            $mora_31_60 = $capital_mora;
        } elseif ($dias_atraso >= 61 && $dias_atraso <= 90) {
            $mora_61_90 = $capital_mora;
        } elseif ($dias_atraso >= 91 && $dias_atraso <= 180) {
            $mora_91_180 = $capital_mora;
        } elseif ($dias_atraso > 180) {
            $mora_mas_180 = $capital_mora;
        }
        
        // Crear agencia si no existe
        if (!isset($agencias_index[$id_agencia])) {
            $agencias_index[$id_agencia] = count($agencias);
            $agencias[] = [
                'id_agencia' => $id_agencia,
                'nombre_agencia' => $row['nombre_agencia'] ?? 'AGENCIA ' . $id_agencia,
                'regional' => $regionNombre,
                'ejecutivos' => [],
                'ejecutivos_index' => []
            ];
        }
        
        $agencia_idx = $agencias_index[$id_agencia];
        
        // Crear ejecutivo si no existe
        if (!isset($agencias[$agencia_idx]['ejecutivos_index'][$cod_anal])) {
            $agencias[$agencia_idx]['ejecutivos_index'][$cod_anal] = count($agencias[$agencia_idx]['ejecutivos']);
            $agencias[$agencia_idx]['ejecutivos'][] = [
                'nombre_asesor' => $row['nombre_asesor'],
                'grupos' => [],
                'grupos_index' => []
            ];
        }
        
        $ejecutivo_idx = $agencias[$agencia_idx]['ejecutivos_index'][$cod_anal];
        
        // Crear grupo si no existe (si es 0 = INDIVIDUAL, se agrupa como "INDIVIDUAL")
        $grupo_key = ($cod_grupo > 0) ? "GRUPO_" . $cod_grupo : "INDIVIDUAL";
        if (!isset($agencias[$agencia_idx]['ejecutivos'][$ejecutivo_idx]['grupos_index'][$grupo_key])) {
            $agencias[$agencia_idx]['ejecutivos'][$ejecutivo_idx]['grupos_index'][$grupo_key] = count($agencias[$agencia_idx]['ejecutivos'][$ejecutivo_idx]['grupos']);
            $agencias[$agencia_idx]['ejecutivos'][$ejecutivo_idx]['grupos'][] = [
                'codigo_grupo' => $cod_grupo,
                'tipo_credito' => $tipo_credito,
                'clientes' => []
            ];
        }
        
        $grupo_idx = $agencias[$agencia_idx]['ejecutivos'][$ejecutivo_idx]['grupos_index'][$grupo_key];
        
        // Agregar cada crédito como un registro separado (no agrupar por cliente)
        // Esto permite contar créditos correctamente como en cartera_fondos.php
        $agencias[$agencia_idx]['ejecutivos'][$ejecutivo_idx]['grupos'][$grupo_idx]['clientes'][] = [
            'codigo_cliente' => $cod_cliente,
            'nombre_cliente' => $row['nombre_cliente'],
            'cuenta' => $row['cuenta'],
            'monto_otorgado' => floatval($row['monto_otorgado']),
            'saldo' => $saldo_total,
            'vigente' => $vigente,
            'mora_1_30' => $mora_1_30,
            'mora_31_60' => $mora_31_60,
            'mora_61_90' => $mora_61_90,
            'mora_91_180' => $mora_91_180,
            'mora_mas_180' => $mora_mas_180
        ];
    }
    
    // Limpiar índices auxiliares
    foreach ($agencias as &$agencia) {
        unset($agencia['ejecutivos_index']);
        foreach ($agencia['ejecutivos'] as &$ejecutivo) {
            unset($ejecutivo['grupos_index']);
        }
    }
    
    // OBTENER DESEMBOLSOS MENSUALES para cada agencia
    $fecha_inicio = date('Y-m-01', strtotime($fecha_corte . ' -2 months'));
    foreach ($agencias as &$agencia) {
        $query_desembolsos = "
            SELECT 
                DATE_FORMAT(cremi.DFecDsbls, '%M %Y') AS mes,
                SUM(cremi.NCapDes) AS total_colocacion,
                COUNT(DISTINCT cremi.CodCli) AS clientes,
                COUNT(DISTINCT CASE WHEN cremi.CCodGrupo > 0 THEN cremi.CCodGrupo END) AS grupos
            FROM cremcre_meta cremi
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
            WHERE cremi.CODAgencia = ?
              AND cremi.DFecDsbls >= ?
              AND cremi.DFecDsbls <= ?
              {$filfondo}
              {$filasesor}
            GROUP BY DATE_FORMAT(cremi.DFecDsbls, '%Y-%m')
            ORDER BY MIN(cremi.DFecDsbls) DESC
            LIMIT 3
        ";
        
        $result_desembolsos = $database->getAllResults($query_desembolsos, [$agencia['id_agencia'], $fecha_inicio, $fecha_corte]);
        $agencia['desembolsos_mensuales'] = $result_desembolsos ? $result_desembolsos : [];
    }
    
    // Obtener información institucional
    $info = $database->getAllResults(
        "SELECT * FROM " . $db_name_general . ".info_coperativa ins
         INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
         WHERE ag.id_agencia=?", 
        [$_SESSION['id_agencia']]
    );
    
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

// ========== ENRUTAMIENTO SEGÚN TIPO ==========
switch ($tipo) {
    case 'xlsx':
        printxls_estructura($agencias, $titlereport, $usuario, $info, $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
    default:
        printpdf_estructura($agencias, $titlereport, $usuario, $info, $mostrarRegionCol, $regionNombre);
        break;
}

// ========== FUNCIÓN: GENERAR PDF ==========
function printpdf_estructura($agencias, $titlereport, $usuario, $info, $mostrarRegionCol, $regionNombre)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];

    // Clase PDF personalizada
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

        function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $mostrarRegionCol, $regionNombre)
        {
            parent::__construct('L', 'mm', 'Legal');
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
        }

        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            
            // Fecha y usuario
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            
            // Logo de la agencia
            if (!empty($this->pathlogoins) && file_exists($this->pathlogoins)) {
                $this->Image($this->pathlogoins, 10, 13, 33);
            }
            
            // Información institucional
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->Ln(10);
            
            // Título del reporte
            $this->SetFont($fuente, 'B', 10);
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'CARTERA GENERAL' . $this->datos[0], 0, 1, 'C', true);
            
            // Mostrar región si aplica
            if ($this->mostrarRegionCol && $this->regionNombre !== '') {
                $this->SetFont($fuente, 'B', 8);
                $this->Cell(0, 4, 'REGION: ' . decode_utf8($this->regionNombre), 0, 1, 'C');
            }
            $this->Ln(2);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Instanciar PDF
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, [$titlereport], $mostrarRegionCol, $regionNombre);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $fuente = "Courier";
    $pdf->SetFont($fuente, '', 7);
    // Anchos de columna SIN CLI.P y CLI.PAR
    $w = array(55, 22, 22, 22, 18, 18, 18, 18, 18);

    // ITERAR POR AGENCIAS
    $primeraAgencia = true;
    foreach ($agencias as $agencia_data) {
        $nombreAgencia = decode_utf8($agencia_data['nombre_agencia']);
        $regionalAgencia = decode_utf8($agencia_data['regional']);
        
        // Separación entre agencias
        if (!$primeraAgencia) {
            $pdf->AddPage();
        }
        $primeraAgencia = false;

        // ========== BARRA CONTEXTUAL ==========
        $pdf->SetFont($fuente, 'B', 8);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(25, 5, 'Regional', 1, 0, 'C', true);
        $pdf->Cell(90, 5, $regionalAgencia, 1, 0, 'L', true);
        $pdf->Cell(20, 5, 'Agencia', 1, 0, 'C', true);
        $pdf->Cell(75, 5, $nombreAgencia, 1, 1, 'L', true);
        $pdf->Ln(3);

        // Guardar posición Y inicial
        $yInicial = $pdf->GetY();

        // ========== ENCABEZADOS DE TABLA PRINCIPAL ==========
        $pdf->SetFont($fuente, 'B', 7);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell($w[0], 5, 'CONSOLIDADO', 1, 0, 'C', true);
        $pdf->Cell($w[1], 5, 'MONTO', 1, 0, 'C', true);
        $pdf->Cell($w[2], 5, 'SALDO', 1, 0, 'C', true);
        $pdf->Cell($w[3], 5, 'VIGENTE', 1, 0, 'C', true);
        $pdf->Cell($w[4], 5, '1-30', 1, 0, 'C', true);
        $pdf->Cell($w[5], 5, '31-60', 1, 0, 'C', true);
        $pdf->Cell($w[6], 5, '61-90', 1, 0, 'C', true);
        $pdf->Cell($w[7], 5, '91-180', 1, 0, 'C', true);
        $pdf->Cell($w[8], 5, '>180', 1, 1, 'C', true);

        // Variables para totales de agencia
        $totalAgencia = array(
            'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0, 
            'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0
        );

        // Iterar ejecutivos
        $numeroEjecutivo = 0;
        foreach ($agencia_data['ejecutivos'] as $ejecutivo) {
            $numeroEjecutivo++;
            
            // Subtotal por ejecutivo
            $subtotal = array(
                'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0, 
                'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0
            );

            // ENCABEZADO DE EJECUTIVO
            $pdf->SetFont($fuente, 'B', 9);
            $pdf->Cell(array_sum($w), 5, $numeroEjecutivo . '. EJECUTIVO: ' . strtoupper(decode_utf8($ejecutivo['nombre_asesor'])), 0, 1, 'L');
            $pdf->SetFont($fuente, '', 7);

            // Separar clientes individuales y grupos
            $clientesIndividuales = array(
                'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0,
                'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0,
                'cantidad' => 0
            );
            $grupos = [];
            
            foreach ($ejecutivo['grupos'] as $grupo) {
                $totalGrupo = array(
                    'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0,
                    'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0,
                    'cantidad_creditos' => count($grupo['clientes']),
                    'codigo_grupo' => $grupo['codigo_grupo']
                );
                
                foreach ($grupo['clientes'] as $cliente) {
                    $totalGrupo['monto'] += $cliente['monto_otorgado'];
                    $totalGrupo['saldo'] += $cliente['saldo'];
                    $totalGrupo['vigente'] += $cliente['vigente'];
                    $totalGrupo['mora_1_30'] += $cliente['mora_1_30'];
                    $totalGrupo['mora_31_60'] += $cliente['mora_31_60'];
                    $totalGrupo['mora_61_90'] += $cliente['mora_61_90'];
                    $totalGrupo['mora_91_180'] += $cliente['mora_91_180'];
                    $totalGrupo['mora_mas_180'] += $cliente['mora_mas_180'];
                }
                
                if ($grupo['codigo_grupo'] == 0) {
                    // Es individual - acumular
                    $clientesIndividuales['cantidad'] += $totalGrupo['cantidad_creditos'];
                    $clientesIndividuales['monto'] += $totalGrupo['monto'];
                    $clientesIndividuales['saldo'] += $totalGrupo['saldo'];
                    $clientesIndividuales['vigente'] += $totalGrupo['vigente'];
                    $clientesIndividuales['mora_1_30'] += $totalGrupo['mora_1_30'];
                    $clientesIndividuales['mora_31_60'] += $totalGrupo['mora_31_60'];
                    $clientesIndividuales['mora_61_90'] += $totalGrupo['mora_61_90'];
                    $clientesIndividuales['mora_91_180'] += $totalGrupo['mora_91_180'];
                    $clientesIndividuales['mora_mas_180'] += $totalGrupo['mora_mas_180'];
                } else {
                    // Es grupo - guardar
                    $grupos[] = $totalGrupo;
                }
            }
            
            // MOSTRAR CLIENTES INDIVIDUALES (si hay)
            if ($clientesIndividuales['cantidad'] > 0) {
                $pdf->Cell($w[0], 5, decode_utf8('INDIVIDUALES (' . $clientesIndividuales['cantidad'] . ' créditos)'), 1, 0, 'L');
                $pdf->Cell($w[1], 5, number_format($clientesIndividuales['monto'], 2), 1, 0, 'R');
                $pdf->Cell($w[2], 5, number_format($clientesIndividuales['saldo'], 2), 1, 0, 'R');
                $pdf->Cell($w[3], 5, number_format($clientesIndividuales['vigente'], 2), 1, 0, 'R');
                $pdf->Cell($w[4], 5, number_format($clientesIndividuales['mora_1_30'], 2), 1, 0, 'R');
                $pdf->Cell($w[5], 5, number_format($clientesIndividuales['mora_31_60'], 2), 1, 0, 'R');
                $pdf->Cell($w[6], 5, number_format($clientesIndividuales['mora_61_90'], 2), 1, 0, 'R');
                $pdf->Cell($w[7], 5, number_format($clientesIndividuales['mora_91_180'], 2), 1, 0, 'R');
                $pdf->Cell($w[8], 5, number_format($clientesIndividuales['mora_mas_180'], 2), 1, 1, 'R');
                
                // Acumular al subtotal
                $subtotal['monto'] += $clientesIndividuales['monto'];
                $subtotal['saldo'] += $clientesIndividuales['saldo'];
                $subtotal['vigente'] += $clientesIndividuales['vigente'];
                $subtotal['mora_1_30'] += $clientesIndividuales['mora_1_30'];
                $subtotal['mora_31_60'] += $clientesIndividuales['mora_31_60'];
                $subtotal['mora_61_90'] += $clientesIndividuales['mora_61_90'];
                $subtotal['mora_91_180'] += $clientesIndividuales['mora_91_180'];
                $subtotal['mora_mas_180'] += $clientesIndividuales['mora_mas_180'];
            }
            
            // MOSTRAR GRUPOS
            foreach ($grupos as $totalGrupo) {
                $nombreGrupo = "GRUPO #" . $totalGrupo['codigo_grupo'] . " (" . $totalGrupo['cantidad_creditos'] . " cr�ditos)";
                
                $pdf->Cell($w[0], 5, substr(decode_utf8($nombreGrupo), 0, 40), 1, 0, 'L');
                $pdf->Cell($w[1], 5, number_format($totalGrupo['monto'], 2), 1, 0, 'R');
                $pdf->Cell($w[2], 5, number_format($totalGrupo['saldo'], 2), 1, 0, 'R');
                $pdf->Cell($w[3], 5, number_format($totalGrupo['vigente'], 2), 1, 0, 'R');
                $pdf->Cell($w[4], 5, number_format($totalGrupo['mora_1_30'], 2), 1, 0, 'R');
                $pdf->Cell($w[5], 5, number_format($totalGrupo['mora_31_60'], 2), 1, 0, 'R');
                $pdf->Cell($w[6], 5, number_format($totalGrupo['mora_61_90'], 2), 1, 0, 'R');
                $pdf->Cell($w[7], 5, number_format($totalGrupo['mora_91_180'], 2), 1, 0, 'R');
                $pdf->Cell($w[8], 5, number_format($totalGrupo['mora_mas_180'], 2), 1, 1, 'R');
                
                // Acumular al subtotal
                $subtotal['monto'] += $totalGrupo['monto'];
                $subtotal['saldo'] += $totalGrupo['saldo'];
                $subtotal['vigente'] += $totalGrupo['vigente'];
                $subtotal['mora_1_30'] += $totalGrupo['mora_1_30'];
                $subtotal['mora_31_60'] += $totalGrupo['mora_31_60'];
                $subtotal['mora_61_90'] += $totalGrupo['mora_61_90'];
                $subtotal['mora_91_180'] += $totalGrupo['mora_91_180'];
                $subtotal['mora_mas_180'] += $totalGrupo['mora_mas_180'];
            }

            // SUBTOTAL EJECUTIVO
            $pdf->SetFillColor(52, 152, 219);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont($fuente, 'B', 8);

            $pdf->Cell($w[0], 5, 'SUBTOTAL', 1, 0, 'L', true);
            $pdf->Cell($w[1], 5, number_format($subtotal['monto'], 2), 1, 0, 'R', true);
            $pdf->Cell($w[2], 5, number_format($subtotal['saldo'], 2), 1, 0, 'R', true);
            $pdf->Cell($w[3], 5, number_format($subtotal['vigente'], 2), 1, 0, 'R', true);
            $pdf->Cell($w[4], 5, number_format($subtotal['mora_1_30'], 2), 1, 0, 'R', true);
            $pdf->Cell($w[5], 5, number_format($subtotal['mora_31_60'], 2), 1, 0, 'R', true);
            $pdf->Cell($w[6], 5, number_format($subtotal['mora_61_90'], 2), 1, 0, 'R', true);
            $pdf->Cell($w[7], 5, number_format($subtotal['mora_91_180'], 2), 1, 0, 'R', true);
            $pdf->Cell($w[8], 5, number_format($subtotal['mora_mas_180'], 2), 1, 1, 'R', true);

            // CARTERA EN RIESGO
            $carteraRiesgo = $subtotal['mora_31_60'] + $subtotal['mora_61_90'] + $subtotal['mora_91_180'] + $subtotal['mora_mas_180'];
            $porcentaje = ($subtotal['saldo'] > 0) ? ($carteraRiesgo / $subtotal['saldo'] * 100) : 0;

            $pdf->Cell(array_sum(array_slice($w, 0, 3)), 5, 'CARTERA EN RIESGO (>30 dias)', 1, 0, 'R', true);
            $pdf->Cell(array_sum(array_slice($w, 3)), 5, number_format($carteraRiesgo, 2) . ' (' . number_format($porcentaje, 2) . '%)', 1, 1, 'R', true);

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont($fuente, '', 7);
            $pdf->Ln(3);

            // Acumular total agencia
            $totalAgencia['monto'] += $subtotal['monto'];
            $totalAgencia['saldo'] += $subtotal['saldo'];
            $totalAgencia['vigente'] += $subtotal['vigente'];
            $totalAgencia['mora_1_30'] += $subtotal['mora_1_30'];
            $totalAgencia['mora_31_60'] += $subtotal['mora_31_60'];
            $totalAgencia['mora_61_90'] += $subtotal['mora_61_90'];
            $totalAgencia['mora_91_180'] += $subtotal['mora_91_180'];
            $totalAgencia['mora_mas_180'] += $subtotal['mora_mas_180'];
        }
        // Guardar posición Y final de cartera
        $yFinalCartera = $pdf->GetY();

        // ========== TABLA DESEMBOLSOS MENSUALES (DERECHA) ==========
        // Posicionar en la esquina superior derecha
        $xDesembolsos = 230; // Ajustar según ancho disponible
        $pdf->SetXY($xDesembolsos, $yInicial);
        
        // Título de la tabla de desembolsos
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->SetFillColor(100, 100, 100);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(80, 5, 'DESEMBOLSOS', 1, 1, 'C', true);
        
        // Encabezados
        $pdf->SetX($xDesembolsos);
        $pdf->SetFont($fuente, 'B', 6);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(26, 4, 'COLOCACION', 1, 0, 'C', true);
        $pdf->Cell(26, 4, 'CLIENTES', 1, 0, 'C', true);
        $pdf->Cell(28, 4, 'GRUPOS', 1, 1, 'C', true);
        
        // Datos
        $totalColocacion = 0;
        $totalClientes = 0;
        $totalGrupos = 0;
        $pdf->SetFont($fuente, '', 6);
        foreach ($agencia_data['desembolsos_mensuales'] as $desembolso) {
            $pdf->SetX($xDesembolsos);
            $pdf->Cell(26, 4, number_format($desembolso['total_colocacion'], 2), 1, 0, 'R');
            $pdf->Cell(26, 4, $desembolso['clientes'], 1, 0, 'C');
            $pdf->Cell(28, 4, $desembolso['grupos'], 1, 1, 'C');
            $totalColocacion += $desembolso['total_colocacion'];
            $totalClientes += $desembolso['clientes'];
            $totalGrupos += $desembolso['grupos'];
        }
        
        // Total desembolsos
        $pdf->SetX($xDesembolsos);
        $pdf->SetFont($fuente, 'B', 6);
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(26, 4, number_format($totalColocacion, 2), 1, 0, 'R', true);
        $pdf->Cell(26, 4, $totalClientes, 1, 0, 'C', true);
        $pdf->Cell(28, 4, $totalGrupos, 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        // Volver a posición de la tabla principal
        $pdf->SetY($yFinalCartera);

        // ========== TOTALES DE CARTERA ==========
        $pdf->Ln(5);
        
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->SetFillColor(100, 100, 100);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(array_sum($w), 5, 'TOTALES - ' . strtoupper($nombreAgencia), 1, 1, 'C', true);
        
        $pdf->SetFont($fuente, 'B', 7);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($w[0], 5, 'EJECUTIVO', 1, 0, 'C', true);
        $pdf->Cell($w[1], 5, 'MONTO', 1, 0, 'C', true);
        $pdf->Cell($w[2], 5, 'SALDO', 1, 0, 'C', true);
        $pdf->Cell($w[3], 5, 'VIGENTE', 1, 0, 'C', true);
        $pdf->Cell($w[4], 5, '1-30', 1, 0, 'C', true);
        $pdf->Cell($w[5], 5, '31-60', 1, 0, 'C', true);
        $pdf->Cell($w[6], 5, '61-90', 1, 0, 'C', true);
        $pdf->Cell($w[7], 5, '91-180', 1, 0, 'C', true);
        $pdf->Cell($w[8], 5, '>180', 1, 1, 'C', true);
        
        $pdf->SetFont($fuente, '', 7);
        
        // Iterar todos los ejecutivos de la agencia
        foreach ($agencia_data['ejecutivos'] as $ejecutivo) {
            // Calcular totales del ejecutivo
            $totalEjecutivo = array(
                'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0,
                'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0
            );
            
            // Recorrer grupos del ejecutivo
            foreach ($ejecutivo['grupos'] as $grupo) {
                // Recorrer clientes del grupo
                foreach ($grupo['clientes'] as $cliente) {
                    $totalEjecutivo['monto'] += $cliente['monto_otorgado'];
                    $totalEjecutivo['saldo'] += $cliente['saldo'];
                    $totalEjecutivo['vigente'] += $cliente['vigente'];
                    $totalEjecutivo['mora_1_30'] += $cliente['mora_1_30'];
                    $totalEjecutivo['mora_31_60'] += $cliente['mora_31_60'];
                    $totalEjecutivo['mora_61_90'] += $cliente['mora_61_90'];
                    $totalEjecutivo['mora_91_180'] += $cliente['mora_91_180'];
                    $totalEjecutivo['mora_mas_180'] += $cliente['mora_mas_180'];
                }
            }
            
            $pdf->Cell($w[0], 5, substr(decode_utf8($ejecutivo['nombre_asesor']), 0, 40), 1, 0, 'L');
            $pdf->Cell($w[1], 5, number_format($totalEjecutivo['monto'], 2), 1, 0, 'R');
            $pdf->Cell($w[2], 5, number_format($totalEjecutivo['saldo'], 2), 1, 0, 'R');
            $pdf->Cell($w[3], 5, number_format($totalEjecutivo['vigente'], 2), 1, 0, 'R');
            $pdf->Cell($w[4], 5, number_format($totalEjecutivo['mora_1_30'], 2), 1, 0, 'R');
            $pdf->Cell($w[5], 5, number_format($totalEjecutivo['mora_31_60'], 2), 1, 0, 'R');
            $pdf->Cell($w[6], 5, number_format($totalEjecutivo['mora_61_90'], 2), 1, 0, 'R');
            $pdf->Cell($w[7], 5, number_format($totalEjecutivo['mora_91_180'], 2), 1, 0, 'R');
            $pdf->Cell($w[8], 5, number_format($totalEjecutivo['mora_mas_180'], 2), 1, 1, 'R');
        }
        
        // Total general de la agencia
        $pdf->SetFont($fuente, 'B', 8);
        $pdf->SetFillColor(100, 100, 100);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($w[0], 5, 'TOTAL AGENCIA', 1, 0, 'L', true);
        $pdf->Cell($w[1], 5, number_format($totalAgencia['monto'], 2), 1, 0, 'R', true);
        $pdf->Cell($w[2], 5, number_format($totalAgencia['saldo'], 2), 1, 0, 'R', true);
        $pdf->Cell($w[3], 5, number_format($totalAgencia['vigente'], 2), 1, 0, 'R', true);
        $pdf->Cell($w[4], 5, number_format($totalAgencia['mora_1_30'], 2), 1, 0, 'R', true);
        $pdf->Cell($w[5], 5, number_format($totalAgencia['mora_31_60'], 2), 1, 0, 'R', true);
        $pdf->Cell($w[6], 5, number_format($totalAgencia['mora_61_90'], 2), 1, 0, 'R', true);
        $pdf->Cell($w[7], 5, number_format($totalAgencia['mora_91_180'], 2), 1, 0, 'R', true);
        $pdf->Cell($w[8], 5, number_format($totalAgencia['mora_mas_180'], 2), 1, 1, 'R', true);
        
        $pdf->SetTextColor(0, 0, 0);
    } // FIN FOREACH AGENCIAS

    // Output PDF
    $pdfData = $pdf->Output('S');
    
    header('Content-Type: application/json');
    $opResult = array(
        'status' => 1,
        'data' => "data:application/pdf;base64," . base64_encode($pdfData),
        'mensaje' => 'Reporte generado exitosamente',
        'tipo' => 'pdf',
        'namefile' => 'Resumen_Carteras_Ejecutivos_Producto_' . date('Ymd_His')
    );
    echo json_encode($opResult);
}

// ========== FUNCIÓN: GENERAR EXCEL ==========
function printxls_estructura($agencias, $titlereport, $usuario, $info, $mostrarRegionCol, $regionNombre)
{
    $hoy = date("Y-m-d H:i:s");
    $fuente_encabezado = "Arial";
    $fuente = "Courier";

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Resumen Ejecutivos");

    // Configurar anchos de columna (SIN CLI.P y CLI.PAR)
    $activa->getColumnDimension("A")->setWidth(40); // PRODUCTO
    $activa->getColumnDimension("B")->setWidth(18); // MONTO
    $activa->getColumnDimension("C")->setWidth(18); // SALDO
    $activa->getColumnDimension("D")->setWidth(18); // VIGENTE
    $activa->getColumnDimension("E")->setWidth(15); // 1-30
    $activa->getColumnDimension("F")->setWidth(15); // 31-60
    $activa->getColumnDimension("G")->setWidth(15); // 61-90
    $activa->getColumnDimension("H")->setWidth(15); // 91-180
    $activa->getColumnDimension("I")->setWidth(15); // >180
    $activa->getColumnDimension("J")->setWidth(20); // COLOCACION
    $activa->getColumnDimension("K")->setWidth(18); // CLIENTES
    $activa->getColumnDimension("L")->setWidth(15); // GRUPOS

    // Fila 1: Fecha/hora generación
    $activa->setCellValue("A1", $hoy);
    $activa->mergeCells('A1:I1');
    $activa->getStyle("A1")->getFont()->setSize(9)->setName($fuente_encabezado);
    $activa->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Fila 2: Usuario
    $activa->setCellValue("A2", $usuario);
    $activa->mergeCells('A2:I2');
    $activa->getStyle("A2")->getFont()->setSize(9)->setName($fuente_encabezado);
    $activa->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Fila 4: Título principal
    $activa->setCellValue("A4", "CARTERA GENERAL" . $titlereport);
    $activa->mergeCells('A4:I4');
    $activa->getStyle("A4")->getFont()->setSize(12)->setName($fuente)->setBold(true);
    $activa->getStyle("A4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Fila 5: Región (si aplica)
    $fila = 6;
    if ($mostrarRegionCol && $regionNombre !== '') {
        $activa->setCellValue("A5", "REGION: " . $regionNombre);
        $activa->mergeCells('A5:I5');
        $activa->getStyle("A5")->getFont()->setSize(10)->setName($fuente)->setBold(true);
        $activa->getStyle("A5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $fila = 7;
    }

    // ITERAR POR AGENCIAS
    foreach ($agencias as $agencia_data) {
        $nombreAgencia = $agencia_data['nombre_agencia'];
        $regionalAgencia = $agencia_data['regional'];
        
        // ========== BARRA CONTEXTUAL (Regional | Agencia) ==========
        $activa->setCellValue("A{$fila}", "Regional");
        $activa->setCellValue("B{$fila}", $regionalAgencia);
        $activa->mergeCells("B{$fila}:C{$fila}");
        $activa->setCellValue("D{$fila}", "Agencia");
        $activa->setCellValue("E{$fila}", $nombreAgencia);
        $activa->mergeCells("E{$fila}:F{$fila}");
        
        $activa->getStyle("A{$fila}:F{$fila}")->getFont()->setBold(true)->setSize(9);
        $activa->getStyle("A{$fila}:F{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCDCDC');
        $activa->getStyle("A{$fila}:F{$fila}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $activa->getStyle("A{$fila}:F{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $fila += 2;
        $filaInicioTablas = $fila;

        // ========== TABLA PRINCIPAL: ENCABEZADO ==========
        $encabezados = [
            "PRODUCTO", "MONTO", "SALDO", "VIGENTE", "1-30", "31-60", "61-90", "91-180", ">180"
        ];
        $activa->fromArray($encabezados, null, "A{$fila}");
        $activa->getStyle("A{$fila}:I{$fila}")->getFont()->setName($fuente)->setBold(true)->setSize(10);
        $activa->getStyle("A{$fila}:I{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9D9D9');
        $activa->getStyle("A{$fila}:I{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        $fila++;

        // Variables para totales de agencia
        $totalAgencia = array(
            'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0, 
            'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0
        );

        // Iterar ejecutivos
        $numeroEjecutivo = 0;
        foreach ($agencia_data['ejecutivos'] as $ejecutivo) {
            $numeroEjecutivo++;
            
            // Subtotal por ejecutivo
            $subtotal = array(
                'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0, 
                'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0
            );

            // ENCABEZADO DE EJECUTIVO
            $activa->setCellValue("A{$fila}", $numeroEjecutivo . '. EJECUTIVO: ' . strtoupper($ejecutivo['nombre_asesor']));
            $activa->mergeCells("A{$fila}:I{$fila}");
            $activa->getStyle("A{$fila}")->getFont()->setName($fuente)->setBold(true)->setSize(10);
            $activa->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $activa->getStyle("A{$fila}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8E8E8');
            $fila++;

            // Separar clientes individuales y grupos
            $clientesIndividuales = array(
                'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0,
                'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0,
                'cantidad' => 0
            );
            $grupos = [];
            
            foreach ($ejecutivo['grupos'] as $grupo) {
                $totalGrupo = array(
                    'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0,
                    'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0,
                    'cantidad_creditos' => count($grupo['clientes']),
                    'codigo_grupo' => $grupo['codigo_grupo']
                );
                
                foreach ($grupo['clientes'] as $cliente) {
                    $totalGrupo['monto'] += $cliente['monto_otorgado'];
                    $totalGrupo['saldo'] += $cliente['saldo'];
                    $totalGrupo['vigente'] += $cliente['vigente'];
                    $totalGrupo['mora_1_30'] += $cliente['mora_1_30'];
                    $totalGrupo['mora_31_60'] += $cliente['mora_31_60'];
                    $totalGrupo['mora_61_90'] += $cliente['mora_61_90'];
                    $totalGrupo['mora_91_180'] += $cliente['mora_91_180'];
                    $totalGrupo['mora_mas_180'] += $cliente['mora_mas_180'];
                }
                
                if ($grupo['codigo_grupo'] == 0) {
                    // Es individual - acumular
                    $clientesIndividuales['cantidad'] += $totalGrupo['cantidad_creditos'];
                    $clientesIndividuales['monto'] += $totalGrupo['monto'];
                    $clientesIndividuales['saldo'] += $totalGrupo['saldo'];
                    $clientesIndividuales['vigente'] += $totalGrupo['vigente'];
                    $clientesIndividuales['mora_1_30'] += $totalGrupo['mora_1_30'];
                    $clientesIndividuales['mora_31_60'] += $totalGrupo['mora_31_60'];
                    $clientesIndividuales['mora_61_90'] += $totalGrupo['mora_61_90'];
                    $clientesIndividuales['mora_91_180'] += $totalGrupo['mora_91_180'];
                    $clientesIndividuales['mora_mas_180'] += $totalGrupo['mora_mas_180'];
                } else {
                    // Es grupo - guardar
                    $grupos[] = $totalGrupo;
                }
            }
            
            // MOSTRAR CLIENTES INDIVIDUALES (si hay)
            if ($clientesIndividuales['cantidad'] > 0) {
                $activa->setCellValue("A{$fila}", 'Individuales (' . $clientesIndividuales['cantidad'] . ' cr�ditos)');
                $activa->setCellValue("B{$fila}", $clientesIndividuales['monto']);
                $activa->setCellValue("C{$fila}", $clientesIndividuales['saldo']);
                $activa->setCellValue("D{$fila}", $clientesIndividuales['vigente']);
                $activa->setCellValue("E{$fila}", $clientesIndividuales['mora_1_30']);
                $activa->setCellValue("F{$fila}", $clientesIndividuales['mora_31_60']);
                $activa->setCellValue("G{$fila}", $clientesIndividuales['mora_61_90']);
                $activa->setCellValue("H{$fila}", $clientesIndividuales['mora_91_180']);
                $activa->setCellValue("I{$fila}", $clientesIndividuales['mora_mas_180']);

                $activa->getStyle("B{$fila}:I{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
                $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                
                // Acumular al subtotal
                $subtotal['monto'] += $clientesIndividuales['monto'];
                $subtotal['saldo'] += $clientesIndividuales['saldo'];
                $subtotal['vigente'] += $clientesIndividuales['vigente'];
                $subtotal['mora_1_30'] += $clientesIndividuales['mora_1_30'];
                $subtotal['mora_31_60'] += $clientesIndividuales['mora_31_60'];
                $subtotal['mora_61_90'] += $clientesIndividuales['mora_61_90'];
                $subtotal['mora_91_180'] += $clientesIndividuales['mora_91_180'];
                $subtotal['mora_mas_180'] += $clientesIndividuales['mora_mas_180'];
                
                $fila++;
            }
            
            // MOSTRAR GRUPOS
            foreach ($grupos as $totalGrupo) {
                $nombreGrupo = "GRUPO #" . $totalGrupo['codigo_grupo'] . " (" . $totalGrupo['cantidad_creditos'] . " cr�ditos)";
                
                $activa->setCellValue("A{$fila}", $nombreGrupo);
                $activa->setCellValue("B{$fila}", $totalGrupo['monto']);
                $activa->setCellValue("C{$fila}", $totalGrupo['saldo']);
                $activa->setCellValue("D{$fila}", $totalGrupo['vigente']);
                $activa->setCellValue("E{$fila}", $totalGrupo['mora_1_30']);
                $activa->setCellValue("F{$fila}", $totalGrupo['mora_31_60']);
                $activa->setCellValue("G{$fila}", $totalGrupo['mora_61_90']);
                $activa->setCellValue("H{$fila}", $totalGrupo['mora_91_180']);
                $activa->setCellValue("I{$fila}", $totalGrupo['mora_mas_180']);

                $activa->getStyle("B{$fila}:I{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
                $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                
                // Acumular al subtotal
                $subtotal['monto'] += $totalGrupo['monto'];
                $subtotal['saldo'] += $totalGrupo['saldo'];
                $subtotal['vigente'] += $totalGrupo['vigente'];
                $subtotal['mora_1_30'] += $totalGrupo['mora_1_30'];
                $subtotal['mora_31_60'] += $totalGrupo['mora_31_60'];
                $subtotal['mora_61_90'] += $totalGrupo['mora_61_90'];
                $subtotal['mora_91_180'] += $totalGrupo['mora_91_180'];
                $subtotal['mora_mas_180'] += $totalGrupo['mora_mas_180'];
                
                $fila++;
            }

            // SUBTOTAL EJECUTIVO (fondo azul)
            $activa->setCellValue("A{$fila}", "SUBTOTAL");
            $activa->setCellValue("B{$fila}", $subtotal['monto']);
            $activa->setCellValue("C{$fila}", $subtotal['saldo']);
            $activa->setCellValue("D{$fila}", $subtotal['vigente']);
            $activa->setCellValue("E{$fila}", $subtotal['mora_1_30']);
            $activa->setCellValue("F{$fila}", $subtotal['mora_31_60']);
            $activa->setCellValue("G{$fila}", $subtotal['mora_61_90']);
            $activa->setCellValue("H{$fila}", $subtotal['mora_91_180']);
            $activa->setCellValue("I{$fila}", $subtotal['mora_mas_180']);

            $activa->getStyle("A{$fila}:I{$fila}")->getFont()->setBold(true);
            $activa->getStyle("A{$fila}:I{$fila}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF3498DB'); // Azul
            $activa->getStyle("A{$fila}:I{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF'); // Blanco
            $activa->getStyle("B{$fila}:I{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
            $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            $fila++;

            // CARTERA EN RIESGO (>30 días)
            $activa->setCellValue("A{$fila}", "CARTERA EN RIESGO (>30 días)");
            $activa->mergeCells("A{$fila}:C{$fila}");
            $activa->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // Fórmula con IFERROR
            $filaAnterior = $fila - 1;
            $activa->setCellValue("D{$fila}", "=IFERROR(SUM(F{$filaAnterior}:I{$filaAnterior})/C{$filaAnterior},0)");
            $activa->mergeCells("D{$fila}:I{$fila}");
            $activa->getStyle("D{$fila}")->getNumberFormat()->setFormatCode('0.00%');
            
            $activa->getStyle("A{$fila}:I{$fila}")->getFont()->setBold(true);
            $activa->getStyle("A{$fila}:I{$fila}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF3498DB');
            $activa->getStyle("A{$fila}:I{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF');
            $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            
            $fila++;
            $fila++; // Espacio entre ejecutivos

            // Acumular total agencia
            $totalAgencia['monto'] += $subtotal['monto'];
            $totalAgencia['saldo'] += $subtotal['saldo'];
            $totalAgencia['vigente'] += $subtotal['vigente'];
            $totalAgencia['mora_1_30'] += $subtotal['mora_1_30'];
            $totalAgencia['mora_31_60'] += $subtotal['mora_31_60'];
            $totalAgencia['mora_61_90'] += $subtotal['mora_61_90'];
            $totalAgencia['mora_91_180'] += $subtotal['mora_91_180'];
            $totalAgencia['mora_mas_180'] += $subtotal['mora_mas_180'];
        }

        // ========== TABLA DESEMBOLSOS MENSUALES (COLUMNA J-L) ==========
        $filaDesembolsos = $filaInicioTablas;
        
        // Título
        $activa->setCellValue("J{$filaDesembolsos}", "DESEMBOLSOS");
        $activa->mergeCells("J{$filaDesembolsos}:L{$filaDesembolsos}");
        $activa->getStyle("J{$filaDesembolsos}")->getFont()->setBold(true)->setSize(10);
        $activa->getStyle("J{$filaDesembolsos}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF646464');
        $activa->getStyle("J{$filaDesembolsos}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $activa->getStyle("J{$filaDesembolsos}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        $filaDesembolsos++;
        
        // Encabezados
        $activa->setCellValue("J{$filaDesembolsos}", "COLOCACION");
        $activa->setCellValue("K{$filaDesembolsos}", "CLIENTES");
        $activa->setCellValue("L{$filaDesembolsos}", "GRUPOS");
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getFont()->setBold(true);
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9D9D9');
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        $filaDesembolsos++;
        
        // Datos
        $totalColocacion = 0;
        $totalClientes = 0;
        $totalGrupos = 0;
        foreach ($agencia_data['desembolsos_mensuales'] as $desembolso) {
            $activa->setCellValue("J{$filaDesembolsos}", $desembolso['total_colocacion']);
            $activa->setCellValue("K{$filaDesembolsos}", $desembolso['clientes']);
            $activa->setCellValue("L{$filaDesembolsos}", $desembolso['grupos']);
            
            $activa->getStyle("J{$filaDesembolsos}")->getNumberFormat()->setFormatCode('#,##0.00');
            $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            
            $totalColocacion += $desembolso['total_colocacion'];
            $totalClientes += $desembolso['clientes'];
            $totalGrupos += $desembolso['grupos'];
            $filaDesembolsos++;
        }
        
        // Total
        $activa->setCellValue("J{$filaDesembolsos}", $totalColocacion);
        $activa->setCellValue("K{$filaDesembolsos}", $totalClientes);
        $activa->setCellValue("L{$filaDesembolsos}", $totalGrupos);
        
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getFont()->setBold(true);
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF3498DB');
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $activa->getStyle("J{$filaDesembolsos}")->getNumberFormat()->setFormatCode('#,##0.00');
        $activa->getStyle("J{$filaDesembolsos}:L{$filaDesembolsos}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // ========== TOTALES DE CARTERA ==========
        $fila += 2;
        
        $activa->setCellValue("A{$fila}", "TOTALES - " . strtoupper($nombreAgencia));
        $activa->mergeCells("A{$fila}:I{$fila}");
        $activa->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(11);
        $activa->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activa->getStyle("A{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF646464');
        $activa->getStyle("A{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $fila++;
        
        // Encabezados
        $encabezadosTotales = ["EJECUTIVO", "MONTO", "SALDO", "VIGENTE", "1-30", "31-60", "61-90", "91-180", ">180"];
        $activa->fromArray($encabezadosTotales, null, "A{$fila}");
        $activa->getStyle("A{$fila}:I{$fila}")->getFont()->setBold(true);
        $activa->getStyle("A{$fila}:I{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9D9D9');
        $activa->getStyle("A{$fila}:I{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $fila++;
        
        // Iterar todos los ejecutivos de la agencia
        foreach ($agencia_data['ejecutivos'] as $ejecutivo) {
            // Calcular totales del ejecutivo
            $totalEjecutivo = array(
                'monto' => 0, 'saldo' => 0, 'vigente' => 0, 'mora_1_30' => 0,
                'mora_31_60' => 0, 'mora_61_90' => 0, 'mora_91_180' => 0, 'mora_mas_180' => 0
            );
            
            // Recorrer grupos del ejecutivo
            foreach ($ejecutivo['grupos'] as $grupo) {
                // Recorrer clientes del grupo
                foreach ($grupo['clientes'] as $cliente) {
                    $totalEjecutivo['monto'] += $cliente['monto_otorgado'];
                    $totalEjecutivo['saldo'] += $cliente['saldo'];
                    $totalEjecutivo['vigente'] += $cliente['vigente'];
                    $totalEjecutivo['mora_1_30'] += $cliente['mora_1_30'];
                    $totalEjecutivo['mora_31_60'] += $cliente['mora_31_60'];
                    $totalEjecutivo['mora_61_90'] += $cliente['mora_61_90'];
                    $totalEjecutivo['mora_91_180'] += $cliente['mora_91_180'];
                    $totalEjecutivo['mora_mas_180'] += $cliente['mora_mas_180'];
                }
            }
            
            $activa->setCellValue("A{$fila}", $ejecutivo['nombre_asesor']);
            $activa->setCellValue("B{$fila}", $totalEjecutivo['monto']);
            $activa->setCellValue("C{$fila}", $totalEjecutivo['saldo']);
            $activa->setCellValue("D{$fila}", $totalEjecutivo['vigente']);
            $activa->setCellValue("E{$fila}", $totalEjecutivo['mora_1_30']);
            $activa->setCellValue("F{$fila}", $totalEjecutivo['mora_31_60']);
            $activa->setCellValue("G{$fila}", $totalEjecutivo['mora_61_90']);
            $activa->setCellValue("H{$fila}", $totalEjecutivo['mora_91_180']);
            $activa->setCellValue("I{$fila}", $totalEjecutivo['mora_mas_180']);
            
            $activa->getStyle("B{$fila}:I{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
            $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            $fila++;
        }
        
        // Total general de la agencia
        $activa->setCellValue("A{$fila}", "TOTAL AGENCIA");
        $activa->setCellValue("B{$fila}", $totalAgencia['monto']);
        $activa->setCellValue("C{$fila}", $totalAgencia['saldo']);
        $activa->setCellValue("D{$fila}", $totalAgencia['vigente']);
        $activa->setCellValue("E{$fila}", $totalAgencia['mora_1_30']);
        $activa->setCellValue("F{$fila}", $totalAgencia['mora_31_60']);
        $activa->setCellValue("G{$fila}", $totalAgencia['mora_61_90']);
        $activa->setCellValue("H{$fila}", $totalAgencia['mora_91_180']);
        $activa->setCellValue("I{$fila}", $totalAgencia['mora_mas_180']);

        $activa->getStyle("A{$fila}:I{$fila}")->getFont()->setBold(true);
        $activa->getStyle("A{$fila}:I{$fila}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF646464');
        $activa->getStyle("A{$fila}:I{$fila}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $activa->getStyle("B{$fila}:I{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
        $activa->getStyle("A{$fila}:I{$fila}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        $fila += 4; // Espacio entre agencias
    } // FIN FOREACH AGENCIAS

    // Generar archivo Excel
    ob_start();
    $writer = new Xlsx($excel);
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    // Respuesta JSON
    header('Content-Type: application/json');
    $opResult = array(
        'status' => 1,
        'data' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," . base64_encode($xlsData),
        'mensaje' => 'Reporte generado exitosamente',
        'tipo' => 'xlsx',
        'namefile' => 'Resumen_Carteras_Ejecutivos_Producto_' . date('Ymd_His')
    );
    echo json_encode($opResult);
}
