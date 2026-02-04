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
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//[[`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[ $idusuario; ]]
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
// $archivo = $datos[3];
$tipo = $_POST["tipo"];

// Región (la UI la envía al final del payload: radios[4], selects[3])
$regionRadio = $radios[4] ?? null;
$regionId = isset($selects[3]) ? (int)$selects[3] : 0;
if ($regionRadio === 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    return;
}

// Región de origen (solo cuando el reporte queda acotado a una región)
$mostrarRegionCol = false;
$regionNombre = '';
$agenciaIdSeleccionada = (($radios[0] ?? null) === 'anyofi') ? (int)($selects[0] ?? 0) : 0;

$columnasExtra = [];
if ($tipo === 'xlsx' && isset($datos[3][0])) {
    $columnasExtra = $datos[3][0];
    // $columnasExtra = json_decode($datos[3][0], true);
}


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

/**
 * CONFIGURACION DE CONEXION A LA BASE DE DATOS
 */
$database = new DatabaseAdapter();
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];


/**
 * ARMANDO LA CONSULTA
 */
$condi = "";
//RANGO DE FECHAS
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = ($radios[3] == "anyasesor") ? " AND cremi.CodAnal =" . $selects[2] : "";
//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//FILTRO POR REGION
$filregion = ($regionRadio === 'anyregion' && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region = ?) "
    : "";

//-----------------------------
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
    cremi.NCapDes, cremi.DfecPago fecpago,dest.DestinoCredito destino,creper.descripcion frecuencia, 
    cremi.noPeriodo numcuotas,
    IFNULL(sector.SectoresEconomicos, '-') AS sectorEconomico,
    IFNULL(actividad.Titulo, '-') AS actividadEconomica,
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
LEFT JOIN {$db_name_general}.`tb_cre_periodos` creper ON creper.cod_msplus=cremi.NtipPerC
LEFT JOIN {$db_name_general}.`tb_sectoreseconomicos` sector ON sector.id_SectoresEconomicos=cremi.CSecEco
LEFT JOIN {$db_name_general}.`tb_ActiEcono` actividad ON actividad.id_ActiEcono=cremi.ActoEcono
LEFT JOIN {$db_name_general}.`tb_credito` tcred ON tcred.abre = cremi.CtipCre
LEFT JOIN tb_garantias_creditos garcre ON garcre.id_cremcre_meta = cremi.ccodcta
LEFT JOIN cli_garantia clgar ON garcre.id_garantia = clgar.idGarantia
LEFT JOIN tb_municipios muni ON muni.id = cli.id_muni_reside
LEFT JOIN {$db_name_general}.tb_etnia ge ON ge.id = cli.idioma
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
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? {$filfondo} {$filagencia} {$filasesor} {$status} {$filregion}
GROUP BY cremi.CCODCTA ORDER BY prod.id_fondo, cremi.TipoEnti, cremi.CCodGrupo, prod.id, cremi.DFecDsbls;";

$showmensaje = false;
try {
    $database->openConnection();

    // Determinar región (solo si el reporte queda acotado)
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

    $queryParams = [$filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha];
    if ($filregion !== "") {
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
    case 'xlsx':
    printxls($result, $titlereport, $idusuario, $columnasExtra, $mostrarRegionCol, $regionNombre);
        break;
}

function printxls($registro, $titlereport, $usuario, $columnasExtra, $mostrarRegionCol = false, $regionNombre = '')
{
    // require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    $tamanioTabla = 11;


    $columnasConfig = [
        // Columnas básicas (siempre incluidas)
        'basicas' => [
            'credito' => ['titulo' => 'CRÉDITO', 'campo' => 'CCODCTA'],
            'cliente' => ['titulo' => 'NOMBRE DEL CLIENTE', 'campo' => 'short_name'],
            'otorgamiento' => ['titulo' => 'OTORGAMIENTO', 'campo' => 'DFecDsbls', 'formato' => 'fecha'],
            'vencimiento' => ['titulo' => 'VENCIMIENTO', 'campo' => 'fechaven', 'formato' => 'fecha'],
            'monto' => ['titulo' => 'MONTO OTORGADO', 'campo' => 'NCapDes'],
            'saldo_capital' => ['titulo' => 'SALDO CAPITAL', 'campo' => 'salcapital'],
            'saldo_interes' => ['titulo' => 'SALDO INTERES', 'campo' => 'salintere'],
            'saldo_mora' => ['titulo' => 'SALDO MORA', 'campo' => 'morpag'],
            'capital_pagado' => ['titulo' => 'CAPITAL PAGADO', 'campo' => 'cappag'],
            'interes_pagado' => ['titulo' => 'INTERES PAGADO', 'campo' => 'intpag'],
            'mora_pagado' => ['titulo' => 'MORA PAGADO', 'campo' => 'morpag'],
            'dias_atraso' => ['titulo' => 'DIAS DE ATRASO', 'campo' => 'atraso'],
            'saldo_total' => ['titulo' => 'SALDO CAP MAS INTERES', 'campo' => null], // calculado
            'mora_capital' => ['titulo' => 'MORA CAPITAL', 'campo' => 'capmora'],
        ],
        // Columnas opcionales
        'opcionales' => [
            'fondo' => ['titulo' => 'FONDO', 'campo' => 'nombre_fondo'],
            'codcliente' => ['titulo' => 'COD CLIENTE', 'campo' => 'codcliente'],
            'genero' => ['titulo' => 'GENERO', 'campo' => 'genero'],
            'fecha_nacimiento' => ['titulo' => 'FECHA DE NACIMIENTO', 'campo' => 'date_birth'],
            'direccion' => ['titulo' => 'DIRECCION', 'campo' => 'direccion'],
            'municipio' => ['titulo' => 'MUNICIPIO DE RESIDENCIA', 'campo' => 'municipio_reside'],
            'telefonos' => ['titulo' => ['TEL1', 'TEL2'], 'campo' => ['tel1', 'tel2']],
            'ultimo_pago' => ['titulo' => 'FECHA DE ULTIMO PAGO', 'campo' => 'fechaultpag', 'formato' => 'fecha'],
            'monto_cuota' => ['titulo' => 'MONTO CUOTA', 'campo' => 'moncuota'],
            'interes_total' => ['titulo' => 'TOTAL INTERES A PAGAR', 'campo' => 'intcal'],
            'otros' => ['titulo' => 'OTROS', 'campo' => 'otrpag'],
            'tasas' => ['titulo' => ['TASA INTERES', 'TASA MORA'], 'campo' => ['tasa', 'tasamora']],
            'producto' => ['titulo' => 'PRODUCTO', 'campo' => 'nombre_producto'],
            'asesor' => ['titulo' => 'ASESOR', 'campo' => 'analista'],
            'tipo_credito' => ['titulo' => ['TIPO CREDITO', 'GRUPO', 'ESTADO'], 'campo' => ['TipoEnti', 'NombreGrupo', 'Cestado']],
            'destino' => ['titulo' => 'DESTINO', 'campo' => 'destino'],
            'frecuencia' => ['titulo' => ['DIA PAGO', 'FRECUENCIA'], 'campo' => ['fecpago', 'frecuencia']],
            'num_cuotas' => ['titulo' => 'NO CUOTAS', 'campo' => 'numcuotas'],
            'fallas' => ['titulo' => 'FALLAS', 'campo' => 'fallas'],
            'sector_economico' => ['titulo' => 'SECTOR ECONOMICO', 'campo' => 'sectorEconomico'],
            'actividad_economica' => ['titulo' => 'ACTIVIDAD ECONOMICA', 'campo' => 'actividadEconomica'],
            'garantia' => ['titulo' => 'TIPO DE GARANTIA', 'campo' => 'tipo_garantia'],
            'pep_cpe' => ['titulo' => ['¿EL CLIENTE ES PEP?', '¿EL CLIENTE EL CPE?'], 'campo' => ['ES_PEP', 'ES_CEP']],
        ]
    ];

    // Construir encabezado dinámico
    $encabezado_tabla = [];
    $camposAMostrar = [];

    // Agregar columnas básicas
    foreach ($columnasConfig['basicas'] as $key => $config) {
        if (is_array($config['titulo'])) {
            $encabezado_tabla = array_merge($encabezado_tabla, $config['titulo']);
        } else {
            $encabezado_tabla[] = $config['titulo'];
        }
        $camposAMostrar[] = ['key' => $key, 'config' => $config];
    }

    // Agregar columnas opcionales seleccionadas
    foreach ($columnasExtra as $columna) {
        if (isset($columnasConfig['opcionales'][$columna])) {
            $config = $columnasConfig['opcionales'][$columna];
            if (is_array($config['titulo'])) {
                $encabezado_tabla = array_merge($encabezado_tabla, $config['titulo']);
            } else {
                $encabezado_tabla[] = $config['titulo'];
            }
            $camposAMostrar[] = ['key' => $columna, 'config' => $config];
        }
    }

    if ($mostrarRegionCol) {
        $encabezado_tabla[] = 'REGION';
    }

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
    $activa->setCellValue("R7", "RECUPERACIONES");

    # Escribir encabezado de la tabla
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle('A8:AZ8')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $activa->mergeCells('A1:X1');
    $activa->mergeCells('A2:X2');
    $activa->mergeCells('A4:X4');
    $activa->mergeCells('A5:X5');
    $activa->mergeCells('M7:O7');

    // VARIABLES DE CONTROL PARA GRUPOS Y FONDOS
    $resumenBloqueGrupo = [
        'montos' => 0,
        'cappag' => 0,
        'intpag' => 0,
        'morpag' => 0,
        'salcap' => 0,
        'salint' => 0,
        'capmora' => 0,
        'cantidad' => 0,
        'intcal' => 0,
        'otrpag' => 0
    ];
    $resumenBloqueFondo = [
        'montos' => 0,
        'cappag' => 0,
        'intpag' => 0,
        'morpag' => 0,
        'salcap' => 0,
        'salint' => 0,
        'capmora' => 0,
        'cantidad' => 0,
        'intcal' => 0,
        'otrpag' => 0
    ];

    $aux = 0;
    $auxgrupo = 0;
    $groupCounter = 0;
    $fila = 0;
    $i = 9;

    /**
     * Bandera para imprimir el resumen al cambiar de grupo, por si despues se maneje la opcion de no imprimirlo
     */
    $printResumen = false;

    while ($fila < count($registro)) {
        $cuenta = $registro[$fila]["CCODCTA"];
        $codcliente = $registro[$fila]["codcliente"];
        $nombre = $registro[$fila]["short_name"];
        $direccion = $registro[$fila]["direccion"];
        $tel1 = $registro[$fila]["tel1"];
        $tel2 = $registro[$fila]["tel2"];
        $genero = $registro[$fila]["genero"];
        $date_birth = $registro[$fila]["date_birth"];
        $fechades = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
        $fechaven = $registro[$fila]["fechaven"];
        $fechaven = ($fechaven == '-') ? "-" : date("d-m-Y", strtotime($fechaven));
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
        $analista = $registro[$fila]["analista"];
        $CODAgencia = $registro[$fila]["CODAgencia"];
        $tasa = $registro[$fila]["tasa"];
        $tasamora = $registro[$fila]["tasamora"];
        $otrpag = $registro[$fila]["otrpag"];
        $tipoenti = $registro[$fila]["TipoEnti"];
        $nomgrupo = $registro[$fila]["NombreGrupo"];
        $codgrupo = ($tipoenti == 'GRUP') ? $registro[$fila]["CCodGrupo"] : ' ';
        $estado = $registro[$fila]["Cestado"];
        $destino = $registro[$fila]["destino"];
        $frec = $registro[$fila]["frecuencia"];
        $ncuotas = $registro[$fila]["numcuotas"];
        $moncuota = $registro[$fila]["moncuota"];
        $diapago = date('d', strtotime($registro[$fila]["fecpago"]));
        $fallas = $registro[$fila]["fallas"];
        $lastpayment = ($registro[$fila]["fechaultpag"] == '-') ? "-" : setdatefrench($registro[$fila]["fechaultpag"]);

        //SALDO DE CAPITAL A LA FECHA
        $salcap = ($monto - $cappag);
        $salcap = ($salcap > 0) ? $salcap : 0;
        $estado = ($salcap > 0) ? "VIGENTE" : "CANCELADO";

        //SALDO DE INTERES A LA FECHA
        $salint = ($intcal - $intpag);
        $salint = ($salint > 0) ? $salint : 0;

        //CAPITAL EN MORA A LA FECHA
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;

        $registro[$fila]["salcapital"] = $salcap;
        $registro[$fila]["salintere"] = $salint;
        $registro[$fila]["capmora"] = $capmora;

        // DETECTAR CAMBIO DE GRUPO (Imprimir subtotal del grupo anterior)
        if ($codgrupo != $auxgrupo && $auxgrupo != 0 && $fila > 0 && $printResumen) {
            $activa->getStyle("A" . $i . ":AR" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
            $activa->getStyle("A" . $i . ":AR" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('E0E0E0');

            // Obtener el nombre del grupo anterior
            $nombreGrupoAnterior = $registro[$fila - 1]["NombreGrupo"];
            $tipoentiAnterior = $registro[$fila - 1]["TipoEnti"];
            $tituloGrupoAnterior = ($tipoentiAnterior == 'GRUP') ? 'GRUPO: ' . strtoupper($nombreGrupoAnterior) : 'INDIVIDUALES';

            $activa->setCellValue('A' . $i, $tituloGrupoAnterior . ' (' . $resumenBloqueGrupo['cantidad'] . ' créditos)');
            $activa->mergeCells("A" . $i . ":M" . $i);

            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(14), $i, $resumenBloqueGrupo['montos']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['intcal']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['salcap']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['salint']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['cappag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['intpag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['morpag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['otrpag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ' ');
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ($resumenBloqueGrupo['salcap'] + $resumenBloqueGrupo['salint']));
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['capmora']);

            $i++;

            // Resetear resumen de grupo
            $resumenBloqueGrupo = [
                'montos' => 0,
                'cappag' => 0,
                'intpag' => 0,
                'morpag' => 0,
                'salcap' => 0,
                'salint' => 0,
                'capmora' => 0,
                'cantidad' => 0,
                'intcal' => 0,
                'otrpag' => 0
            ];
        }

        // DETECTAR CAMBIO DE FONDO (Imprimir subtotal del fondo anterior)
        if ($idfondos != $aux && $aux != 0 && $printResumen) {
            // Imprimir subtotal del último grupo del fondo anterior
            if ($resumenBloqueGrupo['cantidad'] > 0) {
                $activa->getStyle("A" . $i . ":AR" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
                $activa->getStyle("A" . $i . ":AR" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('E0E0E0');

                $nombreGrupoAnterior = $registro[$fila - 1]["NombreGrupo"];
                $tipoentiAnterior = $registro[$fila - 1]["TipoEnti"];
                $tituloGrupoAnterior = ($tipoentiAnterior == 'GRUP') ? 'GRUPO: ' . strtoupper($nombreGrupoAnterior) : 'INDIVIDUALES';

                $activa->setCellValue('A' . $i, $tituloGrupoAnterior . ' (' . $resumenBloqueGrupo['cantidad'] . ' créditos)');
                $activa->mergeCells("A" . $i . ":M" . $i);

                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(14), $i, $resumenBloqueGrupo['montos']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['intcal']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['salcap']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['salint']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['cappag']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['intpag']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['morpag']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['otrpag']);
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ' ');
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ($resumenBloqueGrupo['salcap'] + $resumenBloqueGrupo['salint']));
                ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['capmora']);
                $i++;
            }

            // Imprimir TOTAL del fondo
            $activa->getStyle("A" . $i . ":AR" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
            $activa->getStyle("A" . $i . ":AR" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF99');

            // Obtener el nombre del fondo anterior
            $nombreFondoAnterior = $registro[$fila - 1]["nombre_fondo"];

            $activa->setCellValue('A' . $i, 'FONDO: ' . strtoupper($nombreFondoAnterior) . ' (' . $resumenBloqueFondo['cantidad'] . ' créditos)');
            $activa->mergeCells("A" . $i . ":M" . $i);

            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['montos']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['intcal']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['salcap']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['salint']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['cappag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['intpag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['morpag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['otrpag']);
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ' ');
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ($resumenBloqueFondo['salcap'] + $resumenBloqueFondo['salint']));
            ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['capmora']);

            $i++;
            $i++; // Línea en blanco

            // Resetear contadores
            $resumenBloqueFondo = [
                'montos' => 0,
                'cappag' => 0,
                'intpag' => 0,
                'morpag' => 0,
                'salcap' => 0,
                'salint' => 0,
                'capmora' => 0,
                'cantidad' => 0,
                'intcal' => 0,
                'otrpag' => 0
            ];
            $resumenBloqueGrupo = [
                'montos' => 0,
                'cappag' => 0,
                'intpag' => 0,
                'morpag' => 0,
                'salcap' => 0,
                'salint' => 0,
                'capmora' => 0,
                'cantidad' => 0,
                'intcal' => 0,
                'otrpag' => 0
            ];
            $auxgrupo = -1;
            $groupCounter = 0;
        }

        if ($idfondos != $aux) {
            $aux = $idfondos;
        }

        if ($codgrupo != $auxgrupo) {
            $auxgrupo = $codgrupo;
            $groupCounter += ($tipoenti == 'GRUP') ? 1 : 0;
        }

        // IMPRIMIR DATOS DEL CRÉDITO DINÁMICAMENTE
        $columnaExcel = 1;
        foreach ($camposAMostrar as $campoInfo) {
            $config = $campoInfo['config'];

            // Manejar campos múltiples (arrays)
            if (is_array($config['campo'])) {
                foreach ($config['campo'] as $campo) {
                    $valor = obtenerValorCampo($registro[$fila], $campo, $config['formato'] ?? null);
                    ExcelHelper::setCellByColumnRow($activa, $columnaExcel, $i, $valor);
                    $columnaExcel++;
                }
            } else {
                // Campo calculado especial
                if ($campoInfo['key'] === 'saldo_total') {
                    $valor = $salcap + $salint;
                } else {
                    $valor = obtenerValorCampo($registro[$fila], $config['campo'], $config['formato'] ?? null);
                }
                ExcelHelper::setCellByColumnRow($activa, $columnaExcel, $i, $valor);
                $columnaExcel++;
            }
        }

        if ($mostrarRegionCol) {
            ExcelHelper::setCellByColumnRow($activa, $columnaExcel, $i, $regionNombre);
            $columnaExcel++;
        }
        //definir tipo de letra
        $activa->getStyle("A" . $i . ":AZ" . $i)->getFont()->setName($fuente);

        // ACUMULAR EN RESUMEN DE GRUPO
        $resumenBloqueGrupo['montos'] += $monto;
        $resumenBloqueGrupo['intcal'] += $intcal;
        $resumenBloqueGrupo['cappag'] += $cappag;
        $resumenBloqueGrupo['intpag'] += $intpag;
        $resumenBloqueGrupo['morpag'] += $morpag;
        $resumenBloqueGrupo['otrpag'] += $otrpag;
        $resumenBloqueGrupo['salcap'] += $salcap;
        $resumenBloqueGrupo['salint'] += $salint;
        $resumenBloqueGrupo['capmora'] += $capmora;
        $resumenBloqueGrupo['cantidad']++;

        // ACUMULAR EN RESUMEN DE FONDO
        $resumenBloqueFondo['montos'] += $monto;
        $resumenBloqueFondo['intcal'] += $intcal;
        $resumenBloqueFondo['cappag'] += $cappag;
        $resumenBloqueFondo['intpag'] += $intpag;
        $resumenBloqueFondo['morpag'] += $morpag;
        $resumenBloqueFondo['otrpag'] += $otrpag;
        $resumenBloqueFondo['salcap'] += $salcap;
        $resumenBloqueFondo['salint'] += $salint;
        $resumenBloqueFondo['capmora'] += $capmora;
        $resumenBloqueFondo['cantidad']++;

        $fila++;
        $i++;
    }

    // IMPRIMIR SUBTOTAL DEL ÚLTIMO GRUPO
    if ($resumenBloqueGrupo['cantidad'] > 0 && $printResumen) {
        $activa->getStyle("A" . $i . ":AR" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
        $activa->getStyle("A" . $i . ":AR" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('E0E0E0');

        $nombreGrupoUltimo = $registro[$fila - 1]["NombreGrupo"];
        $tipoentiUltimo = $registro[$fila - 1]["TipoEnti"];
        $tituloGrupoUltimo = ($tipoentiUltimo == 'GRUP') ? 'GRUPO: ' . strtoupper($nombreGrupoUltimo) : 'INDIVIDUALES';

        $activa->setCellValue('A' . $i, $tituloGrupoUltimo . ' (' . $resumenBloqueGrupo['cantidad'] . ' créditos)');
        $activa->mergeCells("A" . $i . ":M" . $i);

        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(14), $i, $resumenBloqueGrupo['montos']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['intcal']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['salcap']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['salint']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['cappag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['intpag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['morpag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['otrpag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ' ');
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ($resumenBloqueGrupo['salcap'] + $resumenBloqueGrupo['salint']));
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueGrupo['capmora']);

        $i++;
    }

    // IMPRIMIR TOTAL DEL ÚLTIMO FONDO
    if ($resumenBloqueFondo['cantidad'] > 0 && $printResumen) {
        $activa->getStyle("A" . $i . ":AR" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
        $activa->getStyle("A" . $i . ":AR" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF99');

        $nombreFondoUltimo = $registro[$fila - 1]["nombre_fondo"];

        $activa->setCellValue('A' . $i, 'FONDO: ' . strtoupper($nombreFondoUltimo) . ' (' . $resumenBloqueFondo['cantidad'] . ' créditos)');
        $activa->mergeCells("A" . $i . ":M" . $i);

        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(14), $i, $resumenBloqueFondo['montos']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['intcal']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['salcap']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['salint']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['cappag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['intpag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['morpag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['otrpag']);
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ' ');
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ($resumenBloqueFondo['salcap'] + $resumenBloqueFondo['salint']));
        ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $resumenBloqueFondo['capmora']);

        $i++;
    }

    $i++; // Línea en blanco antes del total general

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

    //insertar fila de totales
    $activa->getStyle("A" . $i . ":AR" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValueExplicit('A' . $i, "TOTAL GENERAL - Número de créditos: " . $fila, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->mergeCells("A" . $i . ":M" . $i);

    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(14), $i, $sum_monto);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_intcal);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_salcap);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_salint);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, 0);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_cappag);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_intpag);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_morpag);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_otrpag);
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ' ');
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, ($sum_salcap + $sum_salint));
    ExcelHelper::setCellByColumnRow($activa, ExcelHelper::columnCounter(), $i, $sum_capmora);

    //colorear la fila de totales general
    $activa->getStyle("A" . $i . ":AR" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    //alinear a la derecha los totales
    $columnas = range(1, 45);
    foreach ($columnas as $columna) {
        $letra = obtenerLetra($columna);
        $activa->getColumnDimension($letra)->setAutoSize(TRUE);
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

// function obtenerContador($restart = false)
// {
//     static $contador = 0;
//     $contador = ($restart == false) ? $contador + 1 : $restart;
//     return $contador;
// }
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

// Función helper para obtener y formatear valores
function obtenerValorCampo($registro, $campo, $formato = null)
{
    $valor = $registro[$campo] ?? '';

    switch ($formato) {
        case 'fecha':
            if ($valor && $valor !== '-') {
                return date("d-m-Y", strtotime($valor));
            }
            return $valor;
        default:
            return $valor;
    }
}
