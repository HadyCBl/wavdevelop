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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

//[[`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[ $idusuario; ]]
$datos = $_POST["datosval"];
$inputs = $datos[0] ?? [];
$selects = $datos[1] ?? [];
$radios = $datos[2] ?? [];
$archivo = $datos[3] ?? [];
$tipo = $_POST["tipo"];

// Validar fecha inicial
if (!validateDate($inputs[0], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inicial inválida, ingrese una fecha correcta', 'status' => 0]);
    exit;
}
// Validar fecha final
if (!validateDate($inputs[1], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha final inválida, ingrese una fecha correcta', 'status' => 0]);
    exit;
}
// Validar que fecha inicial no sea mayor a fecha final
if (strtotime($inputs[0]) > strtotime($inputs[1])) {
    echo json_encode(['mensaje' => 'La fecha inicial no puede ser mayor a la fecha final', 'status' => 0]);
    exit;
}

if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar fuente de fondos']);
    exit;
}

//*****************OBTENER DATOS REALES DE BASE DE DATOS**************
$fecha_inicio = $inputs[0]; // fecinicio
$fecha_fin = $inputs[1];    // fecfinal
$titlereport = " DEL " . date("d-m-Y", strtotime($fecha_inicio)) . " AL " . date("d-m-Y", strtotime($fecha_fin));

// Construir filtros SQL
// radios: [0]=ragencia, [1]=rfondos, [2]=tipoentidad, [3]=status
$filagencia = ($radios[0] == "anyofi") ? " AND kar.CCODOFI=" . (int)$selects[0] : "";
$filfondo = ($radios[1] == "anyf") ? " AND prod.id_fondo = " . (int)$selects[1] : "";
$filtipoentidad = ($radios[2] == "call") ? "" : " AND cremi.TipoEnti = '" . $radios[2] . "'";
// Status del crédito: allstatus=todos, F=vigentes, G=cancelados  
$filstatus = ($radios[3] == "allstatus") ? "" : " AND cremi.Cestado = '" . $radios[3] . "'";

// Consulta usando la misma base que reciboCreditoIndiviudal (CREDKAR) pero ampliada para grupos
// Inspirada en el método de cartera_fondos.php para cálculo correcto de saldos
$strquery = "
SELECT 
    kar.CCODCTA AS cuenta,
    kar.DFECPRO AS fecha,
    kar.CNUMING AS no_boleta_pago,
    IFNULL(kar.boletabanco, '') AS boletabanco,
    kar.NMONTO AS monto_total_pago,
    kar.KP AS capital,
    kar.INTERES AS intereses,
    kar.MORA AS costo_mora,
    (IFNULL(kar.AHOPRG, 0) + IFNULL(kar.OTR, 0)) AS otros_ingresos,
    IFNULL(kar.OTR, 0) AS recargo_otros_cargos,
    kar.NMONTO AS ingresos_percibidos,
    kar.NMONTO AS monto_depositado,
    '' AS no_recibo_caja_iva,
    (kar.NMONTO * 0.10) AS impuesto_pagar,
    10.00 AS porcentaje_peso_impuesto,
    
    -- Datos del crédito
    cremi.CCODCTA,
    cremi.TipoEnti,
    cremi.CCodGrupo,
    IFNULL(grupo.NombreGrupo, '-') AS nombre_grupo,
    cli.short_name AS titular_pagare,
    cli.no_identifica AS dpi,
    cremi.NCapDes AS monto,
    cremi.DFecDsbls AS fecha_desembolso,
    cremi.noPeriodo AS plazo,
    cremi.NintApro AS interes_mensual,
    
    -- Saldo del crédito (calculado con subconsulta como en cartera_fondos.php)
    (cremi.NCapDes - IFNULL((
        SELECT SUM(KP) 
        FROM CREDKAR 
        WHERE ccodcta = kar.CCODCTA 
        AND dfecpro <= kar.DFECPRO 
        AND cestado = '1' 
        AND ctippag = 'P'
    ), 0)) AS saldo,
    
    -- Cuotas pendientes (calculado de plan de pagos a la fecha del pago)
    IFNULL((
        SELECT COUNT(*) 
        FROM Cre_ppg ppg 
        WHERE ppg.ccodcta = kar.CCODCTA 
        AND ppg.cflag = 0 
        AND ppg.dfecven <= kar.DFECPRO
    ), 0) AS no_falta_saldo,
    
    prod.descripcion AS nombre_producto

FROM CREDKAR kar
INNER JOIN cremcre_meta cremi ON cremi.CCODCTA = kar.CCODCTA
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo

WHERE kar.CTIPPAG = 'P'
  AND kar.CESTADO = '1'
  AND DATE(kar.DFECPRO) BETWEEN ? AND ?
  {$filfondo}
  {$filagencia}
  {$filtipoentidad}
  {$filstatus}

ORDER BY 
    CASE WHEN cremi.TipoEnti = 'GRUP' THEN 0 ELSE 1 END, -- Grupos primero
    cremi.CCodGrupo,
    kar.DFECPRO,
    kar.CCODCTA
";

// DEBUG: Descomentar para ver la consulta SQL (solo para pruebas)
// echo json_encode(['status' => 0, 'mensaje' => $strquery, 'params' => [$fecha_inicio, $fecha_fin]]);
// exit;

$showmensaje = false;
try {
    $database->openConnection();
    
    // Ejecutar consulta con las fechas seleccionadas por el usuario
    $result = $database->getAllResults($strquery, [
        $fecha_inicio,      // Fecha inicio seleccionada
        $fecha_fin          // Fecha fin seleccionada
    ]);
    
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros de pagos para el período seleccionado. Verifique que existan pagos registrados en CREDKAR entre " . date('d/m/Y', strtotime($fecha_inicio)) . " y " . date('d/m/Y', strtotime($fecha_fin)));
    }
    
    // Procesar resultados: agrupar por grupos (GRUP) y mantener individuales separados
    $recibos_temp = [];
    $grupos_consolidados = [];
    
    foreach ($result as $row) {
        $tipo_entidad = $row['TipoEnti'] ?? 'INDI';
        $cod_grupo = $row['CCodGrupo'] ?? 0;
        
        // Usar boletabanco si existe, sino CNUMING
        $no_boleta = (!empty($row['boletabanco'])) ? $row['boletabanco'] : $row['no_boleta_pago'];
        
        if ($tipo_entidad == 'GRUP' && $cod_grupo > 0) {
            // Para GRUPOS: consolidar en una sola fila por grupo
            $key_grupo = $cod_grupo . '_' . date('Y-m-d', strtotime($row['fecha']));
            
            if (!isset($grupos_consolidados[$key_grupo])) {
                // Primera entrada del grupo
                $grupos_consolidados[$key_grupo] = [
                    'tipo_entidad' => 'GRUP',
                    'prestamo' => $row['cuenta'], // Primera cuenta del grupo
                    'cuentas' => [$row['cuenta']], // Lista de cuentas
                    'nombre_grupo' => decode_utf8($row['nombre_grupo']),
                    'titular_pagare' => decode_utf8($row['nombre_grupo']), // Usar nombre del grupo
                    'dpi' => '-', // No aplica para grupos consolidados
                    'monto' => (float)$row['monto'],
                    'saldo' => (float)$row['saldo'],
                    'plazo' => $row['plazo'],
                    'interes_mensual' => (float)$row['interes_mensual'],
                    'capital' => (float)$row['capital'],
                    'intereses' => (float)$row['intereses'],
                    'costo_mora' => (float)$row['costo_mora'],
                    'otros_ingresos' => (float)$row['otros_ingresos'],
                    'recargo_otros_cargos' => (float)$row['recargo_otros_cargos'],
                    'ingresos_percibidos' => (float)$row['ingresos_percibidos'],
                    'monto_depositado' => (float)$row['monto_depositado'],
                    'no_boleta_pago' => $no_boleta,
                    'fecha' => $row['fecha'],
                    'no_recibo_caja_iva' => '',
                    'impuesto_pagar' => 0, // Vacío para grupos
                    'porcentaje_peso_impuesto' => 0,
                    'integrantes' => 1
                ];
            } else {
                // Sumar valores al grupo existente
                $grupos_consolidados[$key_grupo]['cuentas'][] = $row['cuenta'];
                $grupos_consolidados[$key_grupo]['monto'] += (float)$row['monto'];
                $grupos_consolidados[$key_grupo]['saldo'] += (float)$row['saldo'];
                $grupos_consolidados[$key_grupo]['capital'] += (float)$row['capital'];
                $grupos_consolidados[$key_grupo]['intereses'] += (float)$row['intereses'];
                $grupos_consolidados[$key_grupo]['costo_mora'] += (float)$row['costo_mora'];
                $grupos_consolidados[$key_grupo]['otros_ingresos'] += (float)$row['otros_ingresos'];
                $grupos_consolidados[$key_grupo]['recargo_otros_cargos'] += (float)$row['recargo_otros_cargos'];
                $grupos_consolidados[$key_grupo]['ingresos_percibidos'] += (float)$row['ingresos_percibidos'];
                $grupos_consolidados[$key_grupo]['monto_depositado'] += (float)$row['monto_depositado'];
                $grupos_consolidados[$key_grupo]['integrantes']++;
            }
        } else {
            // Para INDIVIDUALES: mantener registro individual
            $recibos_temp[] = [
                'tipo_entidad' => 'INDI',
                'prestamo' => $row['cuenta'],
                'nombre_grupo' => decode_utf8($row['nombre_grupo']),
                'titular_pagare' => decode_utf8($row['titular_pagare']),
                'dpi' => $row['dpi'] ?? '-',
                'monto' => (float)$row['monto'],
                'saldo' => (float)$row['saldo'],
                'plazo' => $row['plazo'],
                'interes_mensual' => (float)$row['interes_mensual'],
                'capital' => (float)$row['capital'],
                'intereses' => (float)$row['intereses'],
                'costo_mora' => (float)$row['costo_mora'],
                'otros_ingresos' => (float)$row['otros_ingresos'],
                'recargo_otros_cargos' => (float)$row['recargo_otros_cargos'],
                'ingresos_percibidos' => (float)$row['ingresos_percibidos'],
                'monto_depositado' => (float)$row['monto_depositado'],
                'no_boleta_pago' => $no_boleta,
                'fecha' => $row['fecha'],
                'no_recibo_caja_iva' => '',
                'impuesto_pagar' => 0, // Vacío
                'porcentaje_peso_impuesto' => 0
            ];
        }
    }
    
    // Combinar grupos consolidados con individuales
    $recibos_agrupados = [];
    $contador = 1;
    
    // Primero los grupos consolidados
    foreach ($grupos_consolidados as $grupo) {
        $grupo['no'] = $contador++;
        // Mostrar número de integrantes en el préstamo
        $grupo['prestamo'] = $grupo['prestamo'] . ' (' . $grupo['integrantes'] . ' int.)';
        $recibos_agrupados[] = $grupo;
    }
    
    // Luego los individuales
    foreach ($recibos_temp as $individual) {
        $individual['no'] = $contador++;
        $recibos_agrupados[] = $individual;
    }
    
    $result = $recibos_agrupados;
    
    // Obtener información institucional
    $info = $database->getAllResults(
        "SELECT * FROM " . $db_name_general . ".info_coperativa ins
         INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
         WHERE ag.id_agencia=?", 
        [$_SESSION['id_agencia']]
    );
    
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institución asignada a la agencia no encontrada");
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
    exit;
}

switch ($tipo) {
    case 'xlsx';
        printxls_template($result, $titlereport, $info);
        break;
    case 'pdf':
        printpdf_template($result, $titlereport, $info);
        break;
}

//========================================================================================
// FUNCIONES AUXILIARES COMPARTIDAS
//========================================================================================

/**
 * Formatea número para visualización en reporte
 * @param float $value Valor a formatear
 * @param bool $showZero Si mostrar 0.00 o '-'
 * @return string Valor formateado
 */
function formatReportValue($value, $showZero = false)
{
    $numValue = (float)$value;
    if ($numValue == 0 && !$showZero) {
        return '-';
    }
    return number_format($numValue, 2, '.', ',');
}

/**
 * Extrae datos de una fila para procesamiento
 * @param array $row Fila de datos
 * @return array Array asociativo con datos procesados
 */
function extractRowData($row)
{
    return [
        'no' => $row['no'] ?? '-',
        'prestamo' => $row['prestamo'] ?? '-',
        'nombre_grupo' => $row['nombre_grupo'] ?? '-',
        'titular' => $row['titular_pagare'] ?? '-',
        'dpi' => $row['dpi'] ?? '-',
        'monto' => (float)($row['monto'] ?? 0),
        'saldo' => (float)($row['saldo'] ?? 0),
        'plazo' => $row['plazo'] ?? '-',
        'interes' => $row['interes_mensual'] ?? '-',
        'no_falta' => $row['no_falta_saldo'] ?? 0,
        'capital' => (float)($row['capital'] ?? 0),
        'intereses' => (float)($row['intereses'] ?? 0),
        'costo_mora' => (float)($row['costo_mora'] ?? 0),
        'otros_ingresos' => (float)($row['otros_ingresos'] ?? 0),
        'recargo_otros_cargos' => (float)($row['recargo_otros_cargos'] ?? 0),
        'ingresos_percibidos' => (float)($row['ingresos_percibidos'] ?? 0),
        'monto_depositado' => (float)($row['monto_depositado'] ?? 0),
        'no_boleta_pago' => $row['no_boleta_pago'] ?? '-',
        'fecha' => $row['fecha'] ?? '-',
        'no_recibo_caja_iva' => $row['no_recibo_caja_iva'] ?? '',
        'impuesto_pagar' => (float)($row['impuesto_pagar'] ?? 0),
        'porcentaje_peso_impuesto' => $row['porcentaje_peso_impuesto'] ?? 10
    ];
}

//========================================================================================
// FUNCIÓN PARA GENERAR PDF - TEMPLATE RECIBOS DE CAJA
//========================================================================================
function printpdf_template($rows, $titlereport, $info)
{
    $institucion = decode_utf8($info[0]["nomb_comple"] ?? 'Asesoría de Desarrollo Financiero Integral, S.A.');
    $direccionins = decode_utf8($info[0]["muni_lug"] ?? '');
    $emailins = $info[0]["emai"] ?? '';
    $telefonosins = trim(($info[0]["tel_1"] ?? '') . '   ' . ($info[0]["tel_2"] ?? ''));
    $nitins = $info[0]["nit"] ?? '';
    $rutalogoins = isset($info[0]["log_img"]) ? "../../../.." . $info[0]["log_img"] : '';

    class PDF_RECIBOS extends FPDF
    {
        public $institucion;
        public $pathlogoins;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $titlereport;

        public function __construct($institucion, $pathlogoins, $direccion, $email, $telefono, $nit, $titlereport)
        {
            parent::__construct('L', 'mm', 'Legal'); // Horizontal, Legal
            $this->institucion = (string) $institucion;
            $this->pathlogoins = (string) $pathlogoins;
            $this->direccion = (string) $direccion;
            $this->email = (string) $email;
            $this->telefono = (string) $telefono;
            $this->nit = (string) $nit;
            $this->titlereport = (string) $titlereport;
        }

        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            
            // Fecha y hora
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            
            // Logo si existe (solo si tiene ruta válida)
            if (!empty($this->pathlogoins) && $this->pathlogoins != '../../../..' && file_exists($this->pathlogoins)) {
                $this->Image($this->pathlogoins, 10, 13, 33);
            }
            
            // Información institucional
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->Ln(3);
            
            // BARRA DE SECCIONES (3 bloques con bordes)
            $this->SetFont($fuente, 'B', 8);
            $this->SetFillColor(240, 240, 240); // Gris claro
            $ancho_bloque = 114; // 342mm / 3
            
            // Bloque 1: EMISION DE RECIBOS DE CAJAS
            $this->SetX(10);
            $this->Cell($ancho_bloque, 5, 'EMISION DE RECIBOS DE CAJAS', 1, 0, 'C', true);
            
            // Bloque 2: CONSOLIDADO MENSUAL
            $this->SetFillColor(204, 229, 255); // Azul claro
            $this->Cell($ancho_bloque, 5, 'CONSOLIDADO MENSUAL ' . $this->titlereport, 1, 0, 'C', true);
            
            // Bloque 3: PAGOS NORMALES
            $this->SetFillColor(240, 240, 240);
            $this->Cell($ancho_bloque, 5, 'PAGOS NORMALES', 1, 1, 'C', true);
            
            $this->Ln(2);
            
            // ENCABEZADOS DE TABLA
            $this->SetFont($fuente, 'B', 6);
            $this->SetFillColor(255, 255, 204); // Amarillo claro para datos crédito
            $this->SetTextColor(0, 0, 0);
            
            // Fila 1 de encabezados - Bloque 1: Datos del crédito/grupo
            $this->Cell(8, 6, 'No.', 1, 0, 'C', true);
            $this->Cell(32, 6, 'Prestamo', 1, 0, 'C', true); // Más espacio
            $this->Cell(30, 6, 'Nombre del grupo', 1, 0, 'C', true);
            $this->Cell(30, 6, 'Titular del pagare', 1, 0, 'C', true);
            $this->Cell(16, 6, 'DPI', 1, 0, 'C', true); // Reducido
            $this->Cell(14, 6, 'Monto', 1, 0, 'C', true);
            $this->Cell(14, 6, 'Saldo', 1, 0, 'C', true);
            $this->Cell(10, 6, 'Plazo', 1, 0, 'C', true);
            $this->Cell(12, 6, 'Int. Mens', 1, 0, 'C', true);
            
            // Bloque 2: Pagos normales - AZUL CLARO
            $this->SetFillColor(204, 229, 255);
            $this->Cell(14, 6, 'Capital', 1, 0, 'C', true);
            $this->Cell(14, 6, 'Intereses', 1, 0, 'C', true);
            $this->Cell(12, 6, 'Mora', 1, 0, 'C', true);
            $this->SetTextColor(255, 0, 0); // Rojo para OTROS CARGOS
            $this->Cell(18, 6, 'OTROS CARGOS', 1, 0, 'C', true); // Más espacio
            
            // Bloque 3: Ingresos y boleta
            $this->SetTextColor(0, 0, 0);
            $this->Cell(14, 6, 'Ing Percib', 1, 0, 'C', true);
            $this->SetFillColor(51, 102, 204); // Azul fuerte para Monto depositado
            $this->SetTextColor(255, 255, 255); // Texto blanco
            $this->Cell(14, 6, 'Mto Depos', 1, 0, 'C', true);
            $this->SetFillColor(204, 229, 255);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(16, 6, 'Boleta', 1, 0, 'C', true);
            $this->Cell(14, 6, 'Fecha', 1, 0, 'C', true);
            $this->Cell(16, 6, 'Recibo', 1, 0, 'C', true);
            
            // Bloque 4: Impuestos (vacíos)
            $this->Cell(14, 6, '', 1, 0, 'C', true);
            $this->Cell(14, 6, '', 1, 1, 'C', true);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF_RECIBOS($institucion, $rutalogoins, $direccionins, $emailins, $telefonosins, $nitins, $titlereport);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Arial";
    
    // RECORRER LOS DATOS
    $pdf->SetFont($fuente, '', 6);
    $pdf->SetTextColor(0, 0, 0);
    
    // Inicializar totalizadores
    $totales = [
        'capital' => 0,
        'intereses' => 0,
        'mora' => 0,
        'otros_ingresos' => 0,
        'otros_cargos' => 0,
        'ingresos' => 0,
        'depositado' => 0,
        'impuesto' => 0
    ];
    
    foreach ($rows as $row) {
        $data = extractRowData($row);
        
        // Acumular totales
        $totales['capital'] += $data['capital'];
        $totales['intereses'] += $data['intereses'];
        $totales['mora'] += $data['costo_mora'];
        $totales['otros_ingresos'] += $data['otros_ingresos'];
        $totales['otros_cargos'] += $data['recargo_otros_cargos'];
        $totales['ingresos'] += $data['ingresos_percibidos'];
        $totales['depositado'] += $data['monto_depositado'];
        $totales['impuesto'] += $data['impuesto_pagar'];
        
        // Imprimir fila - Bloque 1: Datos del crédito
        $pdf->Cell(8, 4, $data['no'], 1, 0, 'C');
        $pdf->Cell(32, 4, substr($data['prestamo'], 0, 24), 1, 0, 'L'); // Más espacio
        $pdf->Cell(30, 4, substr($data['nombre_grupo'], 0, 22), 1, 0, 'L');
        $pdf->Cell(30, 4, substr($data['titular'], 0, 22), 1, 0, 'L');
        $pdf->Cell(16, 4, $data['dpi'], 1, 0, 'L'); // Reducido
        $pdf->Cell(14, 4, formatReportValue($data['monto']), 1, 0, 'R');
        $pdf->Cell(14, 4, formatReportValue($data['saldo']), 1, 0, 'R');
        $pdf->Cell(10, 4, $data['plazo'], 1, 0, 'C');
        $pdf->Cell(12, 4, $data['interes'], 1, 0, 'R');
        
        // Bloque 2: Pagos normales
        $pdf->Cell(14, 4, formatReportValue($data['capital']), 1, 0, 'R');
        $pdf->Cell(14, 4, formatReportValue($data['intereses']), 1, 0, 'R');
        $pdf->Cell(12, 4, formatReportValue($data['costo_mora']), 1, 0, 'R');
        
        // OTROS CARGOS en ROJO - más espacio
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(18, 4, formatReportValue($data['recargo_otros_cargos']), 1, 0, 'R');
        $pdf->SetTextColor(0, 0, 0);
        
        // Bloque 3: Ingresos y documentos
        $pdf->Cell(14, 4, formatReportValue($data['ingresos_percibidos']), 1, 0, 'R');
        
        // Monto depositado con fondo azul
        $pdf->SetFillColor(51, 102, 204);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(14, 4, formatReportValue($data['monto_depositado']), 1, 0, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->Cell(16, 4, substr($data['no_boleta_pago'], 0, 12), 1, 0, 'C');
        $pdf->Cell(14, 4, date('d/m/Y', strtotime($data['fecha'])), 1, 0, 'C');
        $pdf->Cell(16, 4, substr($data['no_recibo_caja_iva'], 0, 12), 1, 0, 'C');
        
        // Bloque 4: Impuestos (vacíos)
        $pdf->Cell(14, 4, '', 1, 0, 'R');
        $pdf->Cell(14, 4, '', 1, 1, 'R');
    }
    
    // FILA DE TOTALES
    $pdf->SetFont($fuente, 'B', 6);
    $pdf->SetFillColor(220, 220, 220);
    // Primeras 9 columnas: 8+32+30+30+16+14+14+10+12 = 166
    $pdf->Cell(166, 4, 'TOTALES', 1, 0, 'R', true);
    $pdf->Cell(14, 4, formatReportValue($totales['capital'], true), 1, 0, 'R', true);
    $pdf->Cell(14, 4, formatReportValue($totales['intereses'], true), 1, 0, 'R', true);
    $pdf->Cell(12, 4, formatReportValue($totales['mora'], true), 1, 0, 'R', true);
    $pdf->Cell(18, 4, formatReportValue($totales['otros_cargos'], true), 1, 0, 'R', true); // Sin columna vacía
    $pdf->Cell(14, 4, formatReportValue($totales['ingresos'], true), 1, 0, 'R', true);
    $pdf->Cell(14, 4, formatReportValue($totales['depositado'], true), 1, 0, 'R', true);
    // Columnas sin totales: 16+14+16 = 46
    $pdf->Cell(46, 4, '', 1, 0, 'C', true);
    $pdf->Cell(14, 4, '', 1, 0, 'R', true); // Impuesto vacío
    $pdf->Cell(14, 4, '', 1, 1, 'R', true);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    header('Content-Type: application/json');
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Recibos de Caja",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}


//========================================================================================
// FUNCIÓN PARA GENERAR EXCEL - TEMPLATE RECIBOS DE CAJA
//========================================================================================
function printxls_template($rows, $titlereport, $info)
{
    $institucion = $info[0]["nomb_comple"] ?? 'Asesoría de Desarrollo Financiero Integral, S.A.';
    
    $excel = new Spreadsheet();
    $sheet = $excel->getActiveSheet();
    $sheet->setTitle("Recibos de Caja");
    
    // CONFIGURAR ORIENTACIÓN Y TAMAÑO
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL);
    
    // ANCHOS DE COLUMNAS (ajustados para evitar sobreposición)
    $sheet->getColumnDimension('A')->setWidth(8);  // No
    $sheet->getColumnDimension('B')->setWidth(32); // Prestamo - más espacio
    $sheet->getColumnDimension('C')->setWidth(30); // Nombre grupo
    $sheet->getColumnDimension('D')->setWidth(30); // Titular
    $sheet->getColumnDimension('E')->setWidth(16); // DPI - reducido
    $sheet->getColumnDimension('F')->setWidth(15); // Monto
    $sheet->getColumnDimension('G')->setWidth(15); // Saldo
    $sheet->getColumnDimension('H')->setWidth(10); // Plazo
    $sheet->getColumnDimension('I')->setWidth(14); // Interes
    $sheet->getColumnDimension('J')->setWidth(14); // Capital
    $sheet->getColumnDimension('K')->setWidth(14); // Intereses
    $sheet->getColumnDimension('L')->setWidth(12); // Ct. Mor
    $sheet->getColumnDimension('M')->setWidth(18); // Otros Cargos
    $sheet->getColumnDimension('N')->setWidth(16); // Ing Percibidos
    $sheet->getColumnDimension('O')->setWidth(16); // Monto Depositado
    $sheet->getColumnDimension('P')->setWidth(18); // Boleta
    $sheet->getColumnDimension('Q')->setWidth(14); // Fecha
    $sheet->getColumnDimension('R')->setWidth(18); // Recibo
    $sheet->getColumnDimension('S')->setWidth(14); // Impuesto
    $sheet->getColumnDimension('T')->setWidth(16); // % peso
    
    $row = 1;
    $hoy = date("Y-m-d H:i:s");
    $usuario = isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : 'Usuario';
    
    // FILA 1: Fecha/hora generación
    $sheet->setCellValue('A' . $row, $hoy);
    $sheet->mergeCells('A' . $row . ':T' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setSize(9);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $row++;
    
    // FILA 2: Usuario
    $sheet->setCellValue('A' . $row, $usuario);
    $sheet->mergeCells('A' . $row . ':T' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setSize(9);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row += 2;
    
    // TÍTULO PRINCIPAL - GRANDE Y AZUL
    $sheet->setCellValue('A' . $row, $institucion);
    $sheet->mergeCells('A' . $row . ':T' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setSize(16)->setBold(true);
    $sheet->getStyle('A' . $row)->getFont()->getColor()->setARGB('003366');
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
    
    // INFORMACIÓN DE CONTACTO
    $contacto = ($info[0]["muni_lug"] ?? '') . ' | Tel: ' . ($info[0]["tel_1"] ?? '') . ' | Email: ' . ($info[0]["emai"] ?? '') . ' | NIT: ' . ($info[0]["nit"] ?? '');
    $sheet->setCellValue('A' . $row, $contacto);
    $sheet->mergeCells('A' . $row . ':T' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setSize(9);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row += 2;
    
    // BARRA DE SECCIONES (3 bloques)
    $sheet->setCellValue('A' . $row, 'EMISION DE RECIBOS DE CAJAS');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F0F0F0');
    $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $row . ':G' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $sheet->setCellValue('H' . $row, 'CONSOLIDADO ' . $titlereport);
    $sheet->mergeCells('H' . $row . ':N' . $row);
    $sheet->getStyle('H' . $row . ':N' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('CCE5FF');
    $sheet->getStyle('H' . $row . ':N' . $row)->getFont()->setBold(true);
    $sheet->getStyle('H' . $row . ':N' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H' . $row . ':N' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $sheet->setCellValue('O' . $row, 'PAGOS NORMALES');
    $sheet->mergeCells('O' . $row . ':T' . $row);
    $sheet->getStyle('O' . $row . ':T' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F0F0F0');
    $sheet->getStyle('O' . $row . ':T' . $row)->getFont()->setBold(true);
    $sheet->getStyle('O' . $row . ':T' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('O' . $row . ':T' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row += 2;
    
    // ENCABEZADOS DE COLUMNAS
    $headers = [
        'No.', 'Prestamo', 'Nombre del grupo', 'Titular del pagaré', 'DPI',
        'Monto', 'Saldo', 'Plazo', 'Interés Mensual',
        'Capital', 'Interes.', 'Ct. Mor.', 'OTROS CARGOS',
        'Ingresos Percibidos', 'Monto depositado', 'No. Boleta de pago', 'Fecha',
        'No. de Recibo de caja (IVA)', '', '' // Impuesto vacío
    ];
    
    $col = 'A';
    foreach ($headers as $idx => $header) {
        $sheet->setCellValue($col . $row, $header);
        
        // Bloque 1 (datos crédito): Amarillo claro (columnas A-I, índices 0-8)
        if ($idx < 9) {
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFCC');
        }
        // Bloque 2/3/4 (pagos/ingresos): Azul claro
        else {
            $sheet->getStyle($col . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('CCE5FF');
        }
        
        // Monto depositado: Azul fuerte (columna P, índice 15)
        if ($idx == 15) {
            $sheet->getStyle($col . $row)->getFill()->getStartColor()->setARGB('3366CC');
            $sheet->getStyle($col . $row)->getFont()->getColor()->setARGB('FFFFFF');
        }
        
        // OTROS CARGOS: texto rojo (columna N, índice 13)
        if ($idx == 13) {
            $sheet->getStyle($col . $row)->getFont()->getColor()->setARGB('FF0000');
        }
        
        $sheet->getStyle($col . $row)->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($col . $row)->getAlignment()->setWrapText(true);
        
        $col++;
    }
    $row++;
    
    // DATOS
    $totales = [
        'capital' => 0,
        'intereses' => 0,
        'mora' => 0,
        'otros_ingresos' => 0,
        'otros_cargos' => 0,
        'ingresos' => 0,
        'depositado' => 0,
        'impuesto' => 0
    ];
    
    foreach ($rows as $rowData) {
        $data = extractRowData($rowData);
        
        $sheet->setCellValue('A' . $row, $data['no']);
        $sheet->setCellValue('B' . $row, $data['prestamo']);
        $sheet->setCellValue('C' . $row, $data['nombre_grupo']);
        $sheet->setCellValue('D' . $row, $data['titular']);
        $sheet->setCellValue('E' . $row, $data['dpi']);
        $sheet->setCellValue('F' . $row, $data['monto'] > 0 ? $data['monto'] : '-');
        $sheet->setCellValue('G' . $row, $data['saldo'] > 0 ? $data['saldo'] : '-');
        $sheet->setCellValue('H' . $row, $data['plazo']);
        $sheet->setCellValue('I' . $row, $data['interes']);
        $sheet->setCellValue('J' . $row, $data['capital'] > 0 ? $data['capital'] : '-');
        $sheet->setCellValue('K' . $row, $data['intereses'] > 0 ? $data['intereses'] : '-');
        $sheet->setCellValue('L' . $row, $data['costo_mora'] > 0 ? $data['costo_mora'] : '-');
        $sheet->setCellValue('M' . $row, $data['recargo_otros_cargos'] > 0 ? $data['recargo_otros_cargos'] : '-');
        $sheet->setCellValue('N' . $row, $data['ingresos_percibidos'] > 0 ? $data['ingresos_percibidos'] : '-');
        $sheet->setCellValue('O' . $row, $data['monto_depositado'] > 0 ? $data['monto_depositado'] : '-');
        $sheet->setCellValue('P' . $row, $data['no_boleta_pago']);
        $sheet->setCellValue('Q' . $row, date('d/m/Y', strtotime($data['fecha'])));
        $sheet->setCellValue('R' . $row, !empty($data['no_recibo_caja_iva']) ? $data['no_recibo_caja_iva'] : '');
        $sheet->setCellValue('S' . $row, ''); // Impuesto vacío
        $sheet->setCellValue('T' . $row, ''); // % peso vacío
        
        // Acumular totales
        $totales['capital'] += $data['capital'];
        $totales['intereses'] += $data['intereses'];
        $totales['mora'] += $data['costo_mora'];
        $totales['otros_ingresos'] += $data['otros_ingresos'];
        $totales['otros_cargos'] += $data['recargo_otros_cargos'];
        $totales['ingresos'] += $data['ingresos_percibidos'];
        $totales['depositado'] += $data['monto_depositado'];
        $totales['impuesto'] += $data['impuesto_pagar'];
        
        // Formato numérico
        $sheet->getStyle('F' . $row . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('J' . $row . ':P' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('T' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Alineaciones
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('F' . $row . ':T' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('Q' . $row . ':S' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Monto depositado: fondo azul (columna P)
        $sheet->getStyle('P' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('3366CC');
        $sheet->getStyle('P' . $row)->getFont()->getColor()->setARGB('FFFFFF');
        
        // OTROS CARGOS: texto rojo (columna N)
        $sheet->getStyle('N' . $row)->getFont()->getColor()->setARGB('FF0000');
        
        // Bordes
        $sheet->getStyle('A' . $row . ':T' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        $row++;
    }
    
    // FILA DE TOTALES
    $sheet->setCellValue('A' . $row, 'TOTALES');
    $sheet->mergeCells('A' . $row . ':I' . $row);
    $sheet->setCellValue('J' . $row, $totales['capital']);
    $sheet->setCellValue('K' . $row, $totales['intereses']);
    $sheet->setCellValue('L' . $row, $totales['mora']);
    $sheet->setCellValue('M' . $row, '');
    $sheet->setCellValue('N' . $row, $totales['otros_cargos']);
    $sheet->setCellValue('O' . $row, $totales['ingresos']);
    $sheet->setCellValue('P' . $row, $totales['depositado']);
    $sheet->setCellValue('T' . $row, ''); // Impuesto vacío
    
    $sheet->getStyle('A' . $row . ':T' . $row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $row . ':T' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('DCDCDC');
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('J' . $row . ':P' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('T' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('A' . $row . ':T' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // REPETIR ENCABEZADOS EN CADA PÁGINA
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 10);

    ob_start();
    $writer = new Xlsx($excel);
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    header('Content-Type: application/json');
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Recibos de Caja " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
function obtenerLetra($columna)
{
    $letra = '';
    $columna--;

    while ($columna >= 0) {
        $letra = chr($columna % 26 + 65) . $letra;
        $columna = intval($columna / 26) - 1;
    }

    return $letra;
}
