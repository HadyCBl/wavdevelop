<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use Complex\Functions;
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

// echo json_encode(['status' => 0, 'mensaje' =>  $selects[3]]);
// return;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
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

// Región (se envía al final del payload)
$regionRadio = $radios[5] ?? null;
$regionId = isset($selects[4]) ? (int)$selects[4] : 0;
if ($regionRadio === 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    return;
}

// Región de origen (solo cuando el reporte queda acotado a una región)
$mostrarRegion = false;
$regionNombre = '';
$agenciaIdSeleccionada = (($radios[0] ?? null) === 'anyofi') ? (int)($selects[0] ?? 0) : 0;

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
// Filtrar por garantía
$filagarantia = ($radios[4] == "anygarantias") ? " AND gtt.id_TiposGarantia =" . $selects[3] : "";

// Filtro por región
$filregion = ($regionRadio === 'anyregion' && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . (int)$regionId . ")"
    : "";

//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//VARIABLES GLOBALES for index
$filtroagencia = $selects[3];
function globalfil_age() {
    global $filtroagencia; // Declarar la variable como global
}
//-----------------------------
$strquery = "SELECT 
cremi.CODAgencia,
CONCAT(usu.nombre, ' ', usu.apellido) AS analista,
cremi.CCODCTA,
gtt.TiposGarantia, -- Nuevo
gtt.id_TiposGarantia,
cg.valorComercial,
cg.montoAvaluo,
cg.montoGravamen,
IFNULL(cg.descripcionGarantia, ' ') AS Descripcion_gara,
prod.descripcion AS nombre_producto,
prod.tasa_interes AS tasa,
    prod.porcentaje_mora AS tasamora,
cli.short_name,cremi.CodCli codcliente,
cremi.DFecDsbls,
cremi.MonSug,
ffon.descripcion AS nombre_fondo,
    cremi.NCapDes, cremi.DfecPago fecpago,dest.DestinoCredito destino,creper.descripcion frecuencia,
    cremi.noPeriodo numcuotas,
    IFNULL(ppg.dfecven, 0) AS fechaven,
    IFNULL(ppg.sum_nintere, 0) AS intcal,
    IFNULL(ppg_ult.dfecven, 0) AS fechacalult,
    IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
    IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
    IFNULL(kar.sum_KP, 0) AS cappag,
    IFNULL(kar.sum_interes, 0) AS intpag,
    IFNULL(kar.sum_MORA, 0) AS morpag,
    IFNULL(kar.sum_AHOPRG_OTR, 0) AS otrpag,
    IFNULL((SELECT (ncapita+nintere) FROM Cre_ppg WHERE ccodcta=cremi.CCODCTA LIMIT 1),0) AS moncuota,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso('$filtrofecha', cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso,
    IFNULL(grupo.NombreGrupo, ' ') AS NombreGrupo,
    cremi.TipoEnti,
    IFNULL(cremi.CCodGrupo, ' ') AS CCodGrupo,
    cremi.Cestado 
FROM cremcre_meta cremi 
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
INNER JOIN tb_garantias_creditos tgc ON  cremi.CCODCTA = tgc.id_cremcre_meta             -- nuevo
INNER JOIN cli_garantia cg ON tgc.id_garantia = cg.idGarantia                            -- nuevo 
LEFT JOIN $db_name_general.tb_tiposgarantia gtt ON cg.idTipoGa = gtt.id_TiposGarantia-- nuevo
LEFT JOIN $db_name_general.tb_destinocredito dest ON dest.id_DestinoCredito=cremi.Cdescre
LEFT JOIN $db_name_general.`tb_cre_periodos` creper ON creper.cod_msplus=cremi.NtipPerC
LEFT JOIN (
SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(nintere) AS sum_nintere
FROM Cre_ppg
GROUP BY ccodcta
) AS ppg ON ppg.ccodcta = cremi.CCODCTA
LEFT JOIN (
SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(ncapita) AS sum_ncapita, SUM(nintere) AS sum_nintere
FROM Cre_ppg
WHERE dfecven <= '$filtrofecha'
GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
LEFT JOIN (
SELECT ccodcta, SUM(KP) AS sum_KP, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
FROM CREDKAR
WHERE dfecpro <= '$filtrofecha' AND cestado != 'X' AND ctippag = 'P'
GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= '$filtrofecha'" . $filfondo . $filagencia . $filasesor . $status . $filagarantia . $filregion . " 
ORDER BY 
gtt.id_TiposGarantia,prod.id_fondo, cremi.TipoEnti, cremi.CCodGrupo";
//--------------------------------

// echo json_encode(['status' => 0, 'mensaje' => $strquery]);
// return;


$query = mysqli_query($conexion, $strquery);
$aux = mysqli_error($conexion);
if ($aux) {
    echo json_encode(['status' => 0, 'mensaje' => $aux]);
    return;
}

$data[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($query)) {
    $data[$j] = $fil;
    $j++;
}
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos']);
    return;
}
//----------------------
/* $data3 = mysqli_query($conexion, $consulta2);
mysqli_next_result($conexion);
 */
mysqli_next_result($conexion);
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);

/* $aux = mysqli_error($conexion);
if ($aux) {
    echo json_encode(['status' => 0, 'mensaje' => $aux]);
    return;
} */

$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}

// Resolver nombre de región cuando aplica
if ($regionRadio === 'anyregion' && $regionId > 0) {
    $qreg = mysqli_query($conexion, "SELECT nombre FROM cre_regiones WHERE id=" . (int)$regionId . " LIMIT 1");
    if ($qreg && ($r = mysqli_fetch_array($qreg))) {
        $regionNombre = (string)$r['nombre'];
        $mostrarRegion = true;
    }
} elseif ($agenciaIdSeleccionada > 0) {
    $qreg = mysqli_query($conexion, "SELECT r.nombre FROM cre_regiones_agencias ra INNER JOIN cre_regiones r ON r.id = ra.id_region WHERE ra.id_agencia=" . (int)$agenciaIdSeleccionada . " ORDER BY r.estado DESC, r.nombre LIMIT 1");
    if ($qreg && ($r = mysqli_fetch_array($qreg))) {
        $regionNombre = (string)$r['nombre'];
        $mostrarRegion = true;
    }
}

switch ($tipo) {
    case 'xlsx';
        printxls($data, $titlereport, $archivo[0], $selects[3], $mostrarRegion, $regionNombre);
        break;
    case 'pdf':
        printpdf($data, [$titlereport], $info);
        break;
}

//funcion  pdf
function printpdf($registro, $datos, $info)
{
//casi
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
function printxls($registro, $titlereport, $usuario, $filtro_gara, $mostrarRegion = false, $regionNombre = '')
{
    require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("CarteraGarantias");
    
    // Dimensiones de columnas
    $activa->getColumnDimension("A")->setWidth(20);
    $activa->getColumnDimension("B")->setWidth(20);
    $activa->getColumnDimension("C")->setWidth(5);
    $activa->getColumnDimension("D")->setWidth(15);
    $activa->getColumnDimension("E")->setWidth(25);
    $activa->getColumnDimension("F")->setWidth(15);
    $activa->getColumnDimension("G")->setWidth(15);
    $activa->getColumnDimension("H")->setWidth(15);


    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);
    
    // encabezado 
    $activa->getStyle("A1:R2")->applyFromArray([
        'font' => [
            'name' => $fuente_encabezado,
            'size' => $tamanioFecha,
            'color' => ['rgb' => '000000']
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E6F3FF'] // Azul claro formal
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        ]
    ]);

 
   
    $activa->setCellValue("A4", "                                                                   REPORTE");
    $activa->setCellValue("A5", strtoupper("                                              CARTERA DE GARANTIAS " . $titlereport));
    if ($mostrarRegion && $regionNombre !== '') {
        $activa->setCellValue("A6", strtoupper("                                              REGION: " . $regionNombre));
        $activa->mergeCells('A6:O6');
    }
   // $activa->setCellValue("E4", strtoupper("" . $filtro_gara));


    $activa->getStyle("A7:R7")->applyFromArray([
        'font' => [
            'name' => $fuente,
            'size' => $tamanioTabla,
            'bold' => true,
            'color' => ['rgb' => '000000']
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E6F3FF'] // Azul claro formal
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        ]
    ]);

    # Encabezado 
    $encabezado_tabla = ["CRÉDITO", "COD CLIENTE", "NOMBRE DEL CLIENTE", "TIPO DE GARANTIA ", "DESCRIPCION DE LA GARANTIA", "VALOR COMERCIAL", "MONTO AVALUO", "MONTO GRAVAMEN", "MONTO OTORGADO", "SALDO CAPITAL", "DIAS DE ATRASO", "MORA CAPITAL", "TASA INTERES", "TASA MORA", "PRODUCTO", "AGENCIA", "ASESOR", "ESTADO"];
    
    $activa->fromArray($encabezado_tabla, null, 'A8');
    $activa->getStyle('A8:R8')->applyFromArray([
        'font' => [
            'name' => $fuente,
            'bold' => true,
            'color' => ['rgb' => '000000']
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E6F3FF'] // Azul claro formal
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
        ]
    ]);

    //  bordes
    $borderStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
    
    $activa->getStyle('A8:R8')->applyFromArray($borderStyle);
    //combinacion 
    $activa->mergeCells('A1:O1');
    $activa->mergeCells('A2:O2');
    $activa->mergeCells('A4:D4');
    $activa->mergeCells('A5:D5');
    $activa->mergeCells('M7:O7');


    // acumuladoras
    $totalMonto = 0;
    $totalSalCap = 0;
    $totalDiasAtr = 0;
    $totalCapMora = 0;
    $totalTasa = 0;
    $totalTasaMora = 0;
    $totalvalorComercial = 0;
    $totalmontoAvaluo = 0;
    $totamontoGravamen= 0;

    $fila = 0;
    $i = 9;
    $procesados = [];  
    $contador_creditos = 0;  
    $contadorTiposGarantia = [];
    
    while ($fila < count($registro)) {
        $cuenta = $registro[$fila]["CCODCTA"];
        $codcliente = $registro[$fila]["codcliente"];
        $nombre = $registro[$fila]["short_name"];
        $TiposGarantia =  $registro[$fila]["TiposGarantia"];
        $Descripcion_gara =  $registro[$fila]["Descripcion_gara"];
        $valorComercial =  $registro[$fila]["valorComercial"];//ya
        $montoAvaluo =  $registro[$fila]["montoAvaluo"];
        $montoGravamen =  $registro[$fila]["montoGravamen"];
        $monto = $registro[$fila]["NCapDes"];
        $intcal = $registro[$fila]["intcal"];
        $capcalafec = $registro[$fila]["capcalafec"];
        $cappag = $registro[$fila]["cappag"];
        $intpag = $registro[$fila]["intpag"];
        $diasatr = $registro[$fila]["atraso"];
        $nameproducto = $registro[$fila]["nombre_producto"];
        $analista = $registro[$fila]["analista"];
        $CODAgencia = $registro[$fila]["CODAgencia"];
        $tasa = $registro[$fila]["tasa"];
        $tasamora = $registro[$fila]["tasamora"];
        $estado = $registro[$fila]["Cestado"];
        
        // SALDO DE CAPITAL A LA FECHA
        $salcap = $monto - $cappag;
        $salcap = $salcap > 0 ? $salcap : 0;
        $estado = $estado === "F" ? "ACTIVO" : ($estado === "G" ? "CANCELADO" : "OTRO");
    
        // SALDO DE INTERÉS A LA FECHA
        $salint = ($intcal - $intpag);
        $salint = ($salint > 0) ? $salint : 0;
    
        // CAPITAL EN MORA A LA FECHA
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;
            // Contar garantía
        if (isset($contadorTiposGarantia[$TiposGarantia])) {
            $contadorTiposGarantia[$TiposGarantia]++;
        } else {
            $contadorTiposGarantia[$TiposGarantia] = 1;
        }

        $totalvalorComercial += $valorComercial ;
        $totalmontoAvaluo += $montoAvaluo ;
        $totamontoGravamen += $montoGravamen ;
    
        if (!in_array($cuenta, $procesados)) {
            $activa->setCellValueByColumnAndRow(obtenerContador(1), $i, $cuenta);
            $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $codcliente);
            $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($nombre));      


            // Sumar montos
            $totalMonto += $monto;
            $totalSalCap += $salcap;
            $totalDiasAtr += $diasatr;
            $totalCapMora += $capmora;
            $totalTasa += $tasa;
            $totalTasaMora += $tasamora;


            $procesados[] = $cuenta;
            $contador_creditos++;  
        } else {
            $activa->setCellValueByColumnAndRow(obtenerContador(1), $i, '');
            $activa->setCellValueByColumnAndRow(obtenerContador(), $i, '');  //vacío
            $activa->setCellValueByColumnAndRow(obtenerContador(), $i, ''); 
            $monto = 0;
            $salcap = 0;
            $diasatr = 0;
            $capmora = 0;
            $tasa = 0;
            $tasamora = 0;
            $nameproducto = '';
            $CODAgencia = '';
            $analista = '';
            $estado = '';
        }
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($TiposGarantia));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($Descripcion_gara));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $valorComercial ); 
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $montoAvaluo ); 
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $montoGravamen ); 

        //if(!in_array())

        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $monto); 
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $salcap);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $diasatr);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $capmora);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $tasa);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $tasamora);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($nameproducto));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $CODAgencia);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($analista));

        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $estado);

        $activa->getStyle("A" . $i . ":AJ" . $i)->getFont()->setName($fuente);
    
        $fila++;
        $i++;
    }

    // estilo  de totales
    $activa->getStyle("A{$i}:R{$i}")->applyFromArray([
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'color' => ['rgb' => '90EE90'] // Verde claro
        ],
        'font' => [
            'bold' => true,
            'size' => $tamanioTabla,
            'name' => $fuente,
            'color' => ['rgb' => '000000'] // Negro
        ]
    ]);


$activa->setCellValue('A' . $i, "Totales:");
$activa->setCellValue('F' . $i, $totalvalorComercial);
$activa->setCellValue('G' . $i, $totalmontoAvaluo);
$activa->setCellValue('H' . $i, $totamontoGravamen);
$activa->setCellValue('I' . $i, $totalMonto);
$activa->setCellValue('J' . $i, $totalSalCap);
$activa->setCellValue('L' . $i, $totalCapMora);

        $activa->mergeCells("A" . $i . ":E" . $i);
        $i++; 

    
        //total
        $activa->getStyle("A" . $i . ":O" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
        $activa->setCellValueExplicit('A' . $i, "Número de créditos: " . $contador_creditos, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->mergeCells("A" . $i . ":E" . $i);

        // conteo garantía
        $i++;
        $activa->setCellValue('A' . $i, "Tipos de Garantía y su Frecuencia:");
        $activa->getStyle("A" . $i)->getFont()->setBold(true);
        $i++;

        foreach ($contadorTiposGarantia as $tipo => $cantidad) {
            $activa->setCellValue('A' . $i, $tipo);
            $activa->setCellValue('B' . $i, $cantidad);
            $i++;
        }



    $activa->getStyle("A" . $i . ":O" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    $columnas = range(1, 40);
    foreach ($columnas as $columna) {
        $letra = obtenerLetra($columna);
        $activa->getColumnDimension($letra)->setAutoSize(TRUE);

        // $activa->getColumnDimension($columna)->setAutoSize(TRUE);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cartera garantias " . $titlereport,
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
