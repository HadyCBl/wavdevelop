<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 0]);
    return;
}
//NUEVA CONEXION
include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

//Antigua Conexion
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

require __DIR__ . '/../../../../fpdf/fpdf.php';
require __DIR__ . '/../../../../vendor/autoload.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';

use Creditos\Utilidades\CalculoPagosDiarios;
use Creditos\Utilidades\CalculoPagosSemanales;
use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

date_default_timezone_set('America/Guatemala');

$hoy2 = date("Y-m-d H:i:s");
$idusuario = $_SESSION["id"];


use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (isset($_POST['condi'], $_POST['codGrup'], $_POST['NCiclo'])) {
    $condi = $_POST['condi'];
    $codGrup = $_POST['codGrup'];
    $NCiclo = $_POST['NCiclo'];
    $rutalogo = "../../../../includes/img/logomicro.png";
    $datacre = mysqli_query($conexion, 'SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
    cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.CODAgencia, agen.nom_agencia, cre.MontoSol,cre.CodAnal, us.nombre, us.apellido,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,
    cre.MonSug,cre.DFecDsbls, cre.NIntApro
    From cremcre_meta cre
    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
    INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
    INNER JOIN tb_usuario us ON us.id_usu = cre.CodAnal
    INNER JOIN tb_agencia agen ON agen.cod_agenc = cre.CODAgencia
    WHERE cre.TipoEnti = "GRUP" AND cre.cestado = "E" AND cre.CCodGrupo = "' . $codGrup . '" AND cre.NCiclo = ' . $NCiclo);

    $datos = array();
    while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
        $datos[] = $da;
    }
   
    try {
        $database->openConnection();
        $gastosCuota = $utilidadesCreditos->gastosEnCuota($datos[0]['CCODCTA'], $database);
        $diasLaborales = $utilidadesCreditos->dias_habiles($database, $datos[0]['CCODCTA']);
        $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    }catch(Exception $e){
        $mensaje = "ERROR: " . $e;
    }finally {
        $database->closeConnection();
    }

    $interes = $datos[0]['NIntApro'];
    $ntipperc = $datos[0]['NtipPerC'];

    $interes_calc = new profit_cmpst();
    $NtipPerC2 = $interes_calc->ntipPerc($ntipperc);

    $rate  = (($interes / 100) / $NtipPerC2[1]);
    $rateanual = $interes / 100;
    $future_value = 0;
    $beginning = false;
    $fechaPago = $datos[0]['DfecPago'];
    $fechaDesembolso = $datos[0]['DFecDsbls'];
    $noPeriodo = $datos[0]['noPeriodo'];
    $daysdif = diferenciaEnDias($fechaDesembolso, $fechaPago);

    if ($ntipperc == "1D" && $tipoAmortizacion == "Flat") {
        $fchspgs = calculo_fechas_por_nocuota2($fechaPago, $noPeriodo, 1, $diasLaborales);
    } else if ($ntipperc == "7D" && $tipoAmortizacion == "Flat") {
        $fchspgs = calculo_fechas_por_nocuota2($fechaPago, $noPeriodo, 7, $diasLaborales);
    } else if (in_array($info[0]["id_cop"], [15, 27, 29])) {
        $fchspgs = $interes_calc->calcudate2($fechaPago, $noPeriodo, $NtipPerC2[2], $diasLaborales);
    } else {
        $fchspgs = $interes_calc->calcudate2($fechaPago, $noPeriodo, $NtipPerC2[2], $diasLaborales);
    }
    if (!empty($fchspgs[1])) {
        $ultimoRegistro = end($fchspgs[1]);
    } else {
        $ultimoRegistro = 0; 
    }
    
/*
    // Construimos la respuesta
    $response = array(
        'status' => 'success',
        'message' => 'Datos recibidos correctamente',
        'condi' => $condi,
        'codGrup' => $codGrup,
        'NCiclo' => $NCiclo,
        'data' => $datos 
    );
    echo json_encode($response);*/
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Faltan parámetros'));
}


switch ($condi) {
    case '1'; //EXCEL
        printxls($datos, $rutalogo, $conexion, $ultimoRegistro);
        break;
    case '2': //PDF
        //printpdf($ctbmovdata, [$titlereport], $info);
        break;
}

function printxls($registro, $rutalogo, $conexion, $ultimoRegistro)
{
    require '../../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Planilla");

    $activa->getColumnDimension("A")->setWidth(5);
    $activa->getColumnDimension("B")->setWidth(30);
    $activa->getColumnDimension("C")->setWidth(20); 
    $activa->getColumnDimension("D")->setWidth(20);
    $activa->getColumnDimension("E")->setWidth(20);
    $activa->getColumnDimension("F")->setWidth(15);
    $activa->getColumnDimension("G")->setWidth(20);

    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setPath($rutalogo);
    $drawing->setCoordinates('E2');
    $drawing->setHeight(175);
    $drawing->setWorksheet($activa);

    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ];
    $activa->getStyle('B2:C10')->applyFromArray($styleArray);

    $activa->getStyle('B2:C10')->getAlignment()->setHorizontal('left');
    $activa->setCellValue('B2', 'NOMBRE DEL GRUPO:');
    $activa->setCellValue('C2', $registro[0]["NombreGrupo"]);

    $activa->setCellValue('B3', 'DIRECCION DEL GRUPO:');
    $activa->setCellValue('C3', $registro[0]["direc"]);

    $activa->setCellValue('B4', 'CICLO:');
    $activa->setCellValue('C4', $registro[0]["NCiclo"]);

    $activa->setCellValue('B5', 'FECHA DE INICIO:');
    $activa->setCellValue('C5', $registro[0]["DfecPago"]);

    $activa->setCellValue('B6', 'FECHA DE FINALIZACION:');
    $activa->setCellValue('C6', $ultimoRegistro);

    $activa->setCellValue('B7', 'PLAZO:');
    $activa->setCellValue('C7', $registro[0]["noPeriodo"]);

    $activa->setCellValue('B8', 'REGIONAL:');
    $activa->setCellValue('C8', '');

    $activa->setCellValue('B9', 'AGENCIA:');
    $activa->setCellValue('C9', $registro[0]["nom_agencia"]);

    $activa->setCellValue('B10', 'EJECUTIVO DE NEGOCIOS:');
    $activa->setCellValue('C10', $registro[0]["nombre"] . ' ' . $registro[0]["apellido"]);


    $activa->getStyle('A12:G12')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle('A12:G12')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $activa->getStyle('A12:G12')->getFont()->setBold(true);
    $activa->getStyle('A12:G12')->applyFromArray($styleArray);
    $activa->getStyle('E12')->getAlignment()->setWrapText(true);

    $activa->setCellValue('A12', 'No.');
    $activa->setCellValue('B12', 'NOMBRE DEL CLIENTE');
    $activa->setCellValue('C12', 'DPI');
    $activa->setCellValue('D12', 'MONTO OTORGADO');
    $activa->setCellValue('E12', 'DEVOLUCIONES NO PREVISTAS');
    $activa->setCellValue('F12', 'MONTO REAL');
    $activa->setCellValue('G12', 'FIRMA DEL CLIENTE');

    $filaInicio = 13;
    $totalMontoOtorgado = 0; 
    $montReal = 0;
    foreach ($registro as $index => $data) {
        $stmt = $conexion->prepare("SELECT SUM(cg.monto) AS monto 
            FROM cremcre_meta cm
            INNER JOIN cre_productos_gastos cg ON cm.CCODPRD = cg.id_producto
            INNER JOIN cre_tipogastos tipg ON tipg.id = cg.id_tipo_deGasto
            INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
            WHERE cm.CCODCTA = ? AND tipo_deCobro = 1 AND cg.estado = 1");
        $stmt->bind_param("i", $data['CCODCTA']);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $monto = 0;
        if ($fila = $resultado->fetch_assoc()) {
            $monto = isset($fila['monto']) ? (float)$fila['monto'] : 0;
        }
        $stmt->close();

        $activa->setCellValue('A' . $filaInicio, $index + 1);
        $activa->setCellValue('B' . $filaInicio, $data["short_name"]);
        $activa->setCellValueExplicit('C' . $filaInicio, $data["no_identifica"], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $activa->setCellValue('D' . $filaInicio, $data["MonSug"]);
        $activa->setCellValue('F' . $filaInicio, $data["MonSug"] - $monto);

        $activa->getStyle('A' . $filaInicio . ':G' . $filaInicio)->applyFromArray($styleArray);
        $activa->getStyle('D' . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');
        $activa->getStyle('F' . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');

        $totalMontoOtorgado += $data["MonSug"];
        $montReal += $data["MonSug"] - $monto;
        $filaInicio++;
    }

    $activa->setCellValue('C' . $filaInicio, 'TOTAL:');
    $activa->setCellValue('D' . $filaInicio, $totalMontoOtorgado);
    $activa->setCellValue('F' . $filaInicio, $montReal);
    $activa->getStyle('C' . $filaInicio . ':F' . $filaInicio)->getFont()->setBold(true);
    $activa->getStyle('C' . $filaInicio . ':F' . $filaInicio)->applyFromArray($styleArray);
    $activa->getStyle('D' . $filaInicio . ':F' . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');    

    //----------------------------------------------------SEGUNDA HOJA -------------------------------------------------------------------
    $pr = $conexion->prepare("SELECT cg.monto, tipg.nombre_gasto 
    FROM cremcre_meta cm
    INNER JOIN cre_productos_gastos cg ON cm.CCODPRD = cg.id_producto
    INNER JOIN cre_tipogastos tipg ON tipg.id = cg.id_tipo_deGasto
    INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
    WHERE cm.CCODCTA = ? AND tipo_deCobro = 1 AND cg.estado = 1");
    $pr->bind_param("i", $registro[0]["CCODCTA"]);
    $pr->execute();
    $resultados = $pr->get_result();
    $gastos = [];

    if ($resultados->num_rows > 0) {
        while ($fila = $resultados->fetch_assoc()) {
            $gastos[] = [
                'monto' => (float)$fila['monto'], 
                'nombre_gasto' => $fila['nombre_gasto']
            ];
        }
    } else {
        $gastos = [];
    }
    $pr->close();
    
    $detalle = $excel->createSheet();
    $detalle->setTitle("Detalle");
    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setPath($rutalogo);
    $drawing->setCoordinates('B1');
    $drawing->setHeight(75);
    $drawing->setWorksheet($detalle);

    $detalle->getStyle('A6:C6')->getFont()->setBold(true);
    $detalle->getStyle('A6:C6')->applyFromArray($styleArray);
    $detalle->getStyle('A6:C6')->getAlignment()->setWrapText(true);
    $detalle->getStyle('A6:Z6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $detalle->getStyle('A6:Z6')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    $detalle->getColumnDimension("A")->setWidth(7);
    $detalle->getColumnDimension("B")->setWidth(30);
    $detalle->getColumnDimension("C")->setWidth(30);
    $cont=0;

    if (!empty($gastos)) {
        $columnaInicio = 'D';
        $filaInicio = 6;     
        foreach ($gastos as $gasto) {
            $detalle->setCellValue($columnaInicio . $filaInicio, $gasto['nombre_gasto']);
            $detalle->getColumnDimension($columnaInicio)->setWidth(20);
            $detalle->getStyle($columnaInicio . $filaInicio)->getFont()->setBold(true);
            $detalle->getStyle($columnaInicio . $filaInicio)->applyFromArray($styleArray);
            $detalle->getStyle($columnaInicio . $filaInicio)->getAlignment()->setWrapText(true);
            $columnaInicio = chr(ord($columnaInicio) + 1);
        }
        $detalle->setCellValue($columnaInicio . '6', 'Monto Real');
        $detalle->getColumnDimension($columnaInicio)->setWidth(20);
        $detalle->getStyle($columnaInicio . '6')->getFont()->setBold(true);
        $detalle->getStyle($columnaInicio . '6')->applyFromArray($styleArray);
        $detalle->getStyle($columnaInicio . '6')->getAlignment()->setWrapText(true);
    } else {
        $detalle->setCellValue('D6', 'NO SE ENCONTRARON GASTOS');
    }
    
    $detalle->setCellValue('A6', 'No.');
    $detalle->setCellValue('B6', 'NOMBRE DEL CLIENTE');
    $detalle->setCellValue('C6', 'MONTO OTORGADO');

    $totalMontoOtorgado=0;
    $totalesGastos = [];
    $columnaInicio = 'D';
    $filaInicio = 7;
    
    foreach ($registro as $index => $data) {
        $detalle->setCellValue('A' . $filaInicio, $index + 1);
        $detalle->setCellValue('B' . $filaInicio, $data["short_name"]);
        $detalle->setCellValue('C' . $filaInicio, $data["MonSug"]);
        
        $totalMontoOtorgado += $data["MonSug"];
        
        $detalle->getStyle('A' . $filaInicio . ':C' . $filaInicio)->applyFromArray($styleArray);
        $detalle->getStyle('C' . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');
        
        $columnaGastos = 'D';
        $montoRestado = $data["MonSug"];
        
        foreach ($gastos as $gasto) {
            $columna = $columnaGastos;
            $encabezado = $detalle->getCell($columna . '6')->getValue();
            if ($gasto['nombre_gasto'] == $encabezado) {
                $detalle->setCellValue($columna . $filaInicio, $gasto['monto']);
                $montoRestado -= $gasto['monto']; 
                if (!isset($totalesGastos[$columna])) {
                    $totalesGastos[$columna] = 0;
                }
                $totalesGastos[$columna] += $gasto['monto'];
            } else {
                $detalle->setCellValue($columna . $filaInicio, 0);
            }
            
            $detalle->getStyle($columna . $filaInicio)->applyFromArray($styleArray);
            $detalle->getStyle($columna . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');
            
            $columnaGastos = chr(ord($columnaGastos) + 1);
        }

        $columnaResultado = $columnaGastos; 
        $temp = $columnaGastos;
        $detalle->setCellValue($columnaResultado . $filaInicio, $montoRestado);
        $detalle->getStyle($columnaResultado . $filaInicio)->applyFromArray($styleArray);
        $detalle->getStyle($columnaResultado . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');
        if (!isset($totalesGastos[($temp)])) {
            $totalesGastos[$temp] = 0;
        }
        $totalesGastos[$temp] += $montoRestado;
        $filaInicio++;
    }

    $detalle->setCellValue('B' . $filaInicio, 'TOTALES:');
    $detalle->setCellValue('C' . $filaInicio, $totalMontoOtorgado);
    $detalle->getStyle('C' . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');
    $detalle->getStyle('B' . $filaInicio . ':D' . $filaInicio)->getFont()->setBold(true);
    $detalle->getStyle('B' . $filaInicio . ':D' . $filaInicio)->applyFromArray($styleArray);

    $columnaInicio = 'D'; 
    $columnaIndex = ord($columnaInicio) - 65; 
    $columnasEliminadas = 0;
    
    foreach ($totalesGastos as $columna => $total) {
        if ($total == 0) {
            $detalle->removeColumn($columna);
            $columnasEliminadas++;
        } else {
            $ajusteColumna = chr(65 + $columnaIndex); 
            $detalle->setCellValue($ajusteColumna . $filaInicio, $total);
            $detalle->getStyle($ajusteColumna . $filaInicio)->applyFromArray($styleArray);
            $detalle->getStyle($ajusteColumna . $filaInicio)->getFont()->setBold(true);
            $detalle->getStyle($ajusteColumna . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');

            $columnaIndex++;
        }
    }
    $detalle->getStyle('C' . $filaInicio)->getNumberFormat()->setFormatCode('"Q" #,##0.00');


    //-----------------------------------------FIN SEGUNDA HOJA-----------------------------------------------------------------------------
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Planilla_Grupos.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save('php://output');
    exit;
}