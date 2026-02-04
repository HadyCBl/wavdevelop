<?php

use Micro\Helpers\Beneq;
//VERIFICAR SI TIENE EL PARAMETRO EN LA URL
if (!isset($_GET['test']) || $_GET['test'] !== 'soygay') {
    echo "🖕";
    return false;
}

require __DIR__ . '/../../../vendor/autoload.php';
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include  __DIR__ . '/../../../src/funcphp/func_gen.php';
/**
 * EJECUTAR ESTE SCRIPT ANTES DE QUE TERMINE EL DIA, PARA NO TENER PROBLEMAS CON LA SINCRONIZACION
 * 
 */

$calcularPlazoFijo = $_ENV['PLAZO_FIJO_AUTOMATICO'] ?? 0;

use App\Generic\Models\PlazoFijoService;
use Micro\Helpers\Log;

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

function obtenerPeriodos($mesesPorPeriodo, $anio)
{
    $periodos = [];
    $numPeriodos = intval(12 / $mesesPorPeriodo);
    for ($i = 0; $i < $numPeriodos; $i++) {
        $mesInicio = ($i * $mesesPorPeriodo) + 1;
        $mesFin = $mesInicio + $mesesPorPeriodo - 1;

        $fechaInicio = date('Y-m-d', strtotime("$anio-$mesInicio-01"));
        // Último día del mes de fin
        $ultimoDia = date('t', strtotime("$anio-$mesFin-01"));
        $fechaFin = date('Y-m-d', strtotime("$anio-$mesFin-$ultimoDia"));

        $periodos[] = [
            'inicio' => $fechaInicio,
            'fin' => $fechaFin
        ];
    }
    return $periodos;
}

// Ejemplo de uso:
// $periodos = obtenerPeriodos(4, 2024); // Cada 2 meses, 6 periodos
// echo "<pre>";
// print_r($periodos);
// echo "</pre>";


//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();

    /**
     * ACREDITACIONES INDIVIDUALES DE CUENTAS DE AHORRO A PLAZO FIJO
     */
    // SELECT * FROM ahomcrt WHERE liquidado='N' AND fec_apertura<"2025-08-26" AND fec_ven >"2025-08-26" 
    // AND estado=1 AND calint IN ('M','T','S','A');

    if ($calcularPlazoFijo == 1) {

        $certificados = $database->getAllResults(
            "SELECT montoapr, crt.plazo, crt.interes, cli.short_name,cta.nlibreta,cta.ccodaho,
                tip.diascalculo,crt.fec_apertura,crt.calint,tip.inicioCalculo,crt.fec_ven,crt.id_crt
            FROM ahomcrt crt
            INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
            INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
            WHERE liquidado='N' AND fec_apertura<? AND fec_ven >=? AND crt.estado=1 AND calint IN ('M','T','S','A','P','V');",
            [$hoy, $hoy]
        );


        if (!empty($certificados)) {
            $database->beginTransaction();
            foreach ($certificados as $certificado) {
                $servicio = new PlazoFijoService(
                    $certificado['fec_apertura'],
                    $certificado['fec_ven'],
                    $certificado['montoapr'],
                    $certificado['interes'],
                    $certificado['calint'],
                    $certificado['diascalculo'],
                    $certificado['plazo'],
                    $certificado['inicioCalculo'],
                    $certificado['id_crt']
                );

                $planPagos = $servicio->generatePpg();

                // Verificar si la fecha de hoy está dentro de $planPagos en la columna 'fecha'
                $hoy = date("Y-m-d");
                $existeFecha = false;
                $montoInteres = null;
                foreach ($planPagos as $pago) {
                    if (isset($pago['fecha']) && $pago['fecha'] == $hoy) {
                        $existeFecha = true;
                        $montoInteres = $pago['monto_interes'] ?? 0;
                        break;
                    }
                }

                if ($existeFecha) {
                    Log::info("El certificado de la cuenta {$certificado['ccodaho']} con apertura {$certificado['fec_apertura']} tiene un pago programado hoy {$hoy} por un monto de interés de: {$montoInteres}");
                    $servicio->acreditaIntereses($database, $hoy, $montoInteres);
                    echo "<br>El certificado {$certificado['id_crt']} de la cuenta {$certificado['ccodaho']} ha sido acreditado con éxito.<br>";
                }
            }
            $database->commit();
        }
    }

    $mensaje = "Proceso finalizado correctamente";

    $status = true;
} catch (Throwable $e) {
    Log::error("Error en la acreditacion de plazos fijos: " . $e->getMessage());
    $database->rollBack();
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

echo "<br>$mensaje";

//++++++++++++++++++++++++++++++++++++++++++++++
//++++++++++++++++++++++++++++++++++++++++++++++
//++++++++++++++++++++++++++++++++++++++++++++++
//++++++++++++++++++++++++++++++++++++++++++++++


$query = "SELECT cta.ccodaho,cta.ccodcli,cli.short_name,cta.nlibreta,cta.tasa,IFNULL(id_mov,'X') idmov,
                mov.dfecope,mov.ctipope,mov.cnumdoc,IFNULL(mov.monto,0) monto,mov.correlativo,
                IFNULL((SELECT MIN(dfecope) FROM ahommov WHERE cestado!=2 AND ccodaho=cta.ccodaho AND dfecope<=?),'X') AS fecmin,
                saldo_ahorro(cta.ccodaho, IFNULL(mov.dfecope, ?),IFNULL(mov.correlativo, (SELECT MAX(correlativo) 
                        FROM ahommov WHERE ccodaho = cta.ccodaho AND dfecope <= ?))) AS saldo,tip.mincalc,tip.isr,diascalculo
            FROM ahomcta cta 
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
            INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
            LEFT JOIN 
                (
                    SELECT * FROM ahommov WHERE dfecope BETWEEN ? AND ? AND cestado != 2
                ) mov ON mov.ccodaho = cta.ccodaho
            WHERE cta.estado = 'A' AND tip.id_tipo=? 
            ORDER BY cta.ccodaho, mov.dfecope, mov.correlativo;";
//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();

    /**
     * CONFIGURACION DE ACREDITACIONES MASIVAS
     */

    $configuraciones = $database->getAllResults("SELECT conf.tipo tipoCalculo, conf.periodo,conf.producto_id,tip.ccodtip,tip.nombre nombreProducto, conf.id idConfiguracion,provisionar
                                    FROM aho_configuraciones_int conf
                                    INNER JOIN ahomtip tip ON tip.id_tipo=conf.producto_id
                                    WHERE conf.estado=1");

    if (empty($configuraciones)) {
        $showmensaje = true;
        throw new Exception("No existen configuraciones de cálculo de intereses para cuentas de ahorro");
    }

    Log::info("configuraciones", $configuraciones);

    echo "<br> Configuraciones de cálculo de intereses: +++++++++++++++++++++++ <br>";
    echo "<pre>";
    print_r($configuraciones);
    echo "</pre>";

    //OBTENER AÑO ACTUAL
    $anio_actual = date("Y");

    foreach ($configuraciones as $configuracion) {
        $provisionar = false;
        $periodos = obtenerPeriodos($configuracion['periodo'], $anio_actual);
        // Verificar si la fecha de hoy coincide con alguna fecha de fin de los periodos
        $hoy = date("Y-m-d");
        $esFinDePeriodo = false;
        $keyPeriodo = null;
        foreach ($periodos as $key2 => $periodo) {
            if ($hoy == $periodo['fin']) {
                $esFinDePeriodo = true;
                $keyPeriodo = $key2;
                break;
            }
        }

        /**
         * VERIFICAR SI SE PROVISIONA CADA MES, SI ES ASI, CAMBIAR LOS DATOS DE FECHAS DE PERIODO, Y HACER EL CALCULO GUARDAR PARTIDA DE PROVISION
         */
        if ($configuracion['provisionar'] == 1) {
            // Verificar si hoy es fin de mes dentro del rango de periodo, pero que no sea la fecha fin del periodo
            $ultimoDiaMes = date('Y-m-t');
            // Si hoy es el último día del mes, está dentro del rango, y no es la fecha fin del periodo
            if ($hoy == $ultimoDiaMes && $hoy >= $periodos[$keyPeriodo]['inicio'] && $hoy < $periodos[$keyPeriodo]['fin']) {
                $provisionar = true;

                $periodos = array([
                    'inicio' => date('Y-m-01'),
                    'fin' => date('Y-m-t')
                ]);

                $keyPeriodo = 0;
            }
        }

        /**
         * DATOS SOLO PARA TEST
         */
        // $periodos = array([
        //     'inicio' => '2025-07-01',
        //     'fin' => '2025-08-31'
        // ]);

        // $keyPeriodo = 0;

        // $esFinDePeriodo = true;

        /**
         * FIN DATOS SOLO PARA TEST
         */

        /**
         * Si la fecha de hoy coincide con el fin de un periodo, se hace la consulta para hacer el calculo
         */
        if ($esFinDePeriodo || $provisionar) {
            $result = $database->getAllResults(
                $query,
                [
                    $periodos[$keyPeriodo]['fin'],
                    $periodos[$keyPeriodo]['fin'],
                    $periodos[$keyPeriodo]['fin'],
                    $periodos[$keyPeriodo]['inicio'],
                    $periodos[$keyPeriodo]['fin'],
                    $configuracion['producto_id']
                ]
            );

            if (empty($result)) {
                // $showmensaje = true;
                // throw new Exception("No se encontraron cuentas");
                echo "<br>No se encontraron movimientos en las cuentas para el periodo {$periodos[$keyPeriodo]['inicio']} - {$periodos[$keyPeriodo]['fin']}, producto_id: {$configuracion['producto_id']}";
                Log::info("No se encontraron movimientos en las cuentas para el periodo {$periodos[$keyPeriodo]['inicio']} - {$periodos[$keyPeriodo]['fin']}, producto_id: {$configuracion['producto_id']}");
            }

            echo "<br> MOVIMIENTOS A EVALUAR: +++++++++++++++++++++++ <br>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";

            /**
             * PROCESANDO CALCULO SOBRE LOS RESULTADOS
             */
            //INICIO PROCESO 
            $data = array();
            $auxarray = array();

            // end($result);
            // $lastKey = key($result);
            // reset($result);

            $setCorte = false;
            $auxcuenta = "X";
            $auxfecha = agregarDias($periodos[$keyPeriodo]['inicio'], -1);
            foreach ($result as $key => $fila) {
                $cuenta = $fila["ccodaho"];
                $tasa = $fila["tasa"];
                $codcli = $fila["ccodcli"];
                $idmov = $fila["idmov"];
                $fecha = ($idmov == "X") ? $periodos[$keyPeriodo]['fin'] : $fila["dfecope"];
                $tipope = ($idmov == "X") ? "D" : $fila["ctipope"];
                $monto = $fila["monto"];
                $fechamin = $fila["fecmin"];
                $mincalc = $fila["mincalc"];
                $diascalculo = $fila["diascalculo"] ?? 365;
                $porcentajeIsr = round(($fila["isr"] / 100), 2);
                $saldoactual = $fila["saldo"];
                $saldoanterior = ($tipope == "R") ? ($saldoactual + $monto) : ($saldoactual - $monto);

                $auxfecha = ($fechamin == "X") ? $periodos[$keyPeriodo]['fin'] : (($fechamin > $auxfecha) ? $fechamin : $auxfecha);

                $diasdif = dias_dif($auxfecha, $fecha);
                // $fechaant = $fecope;
                $interes = round($saldoanterior * ($tasa / 100) / $diascalculo * $diasdif, 2);
                $interes = ($saldoanterior >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

                $result[$key]["cnumdoc"] = ($idmov == "X") ? "corte" : $fila["cnumdoc"];
                $result[$key]["ctipope"] = $tipope;
                $result[$key]["dfecope"] = $fecha;
                $result[$key]["saldoant"] = $saldoanterior;
                $result[$key]["dias"] = $diasdif;
                $result[$key]["interescal"] = $interes;
                $result[$key]["isr"] = round($interes * $porcentajeIsr, 2);

                array_push($data, $result[$key]);

                $auxfecha = $fecha;
                if ($key === array_key_last($result)) {
                    $setCorte = ($fecha != $periodos[$keyPeriodo]['fin']) ? true : false;
                } else {
                    if ($result[$key + 1]['ccodaho'] != $cuenta) {
                        $auxfecha = agregarDias($periodos[$keyPeriodo]['inicio'], -1);
                        if ($fecha != $periodos[$keyPeriodo]['fin']) {
                            $setCorte = true;
                        }
                    }
                }

                //EL CORTE DE CADA CUENTA AL FINAL DEL MES
                if ($setCorte) {
                    $diasdif = dias_dif($fecha, $periodos[$keyPeriodo]['fin']);
                    $interes = round($saldoactual * ($tasa / 100) / $diascalculo * $diasdif, 2);
                    $interes = ($saldoactual >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

                    $auxarray["ccodaho"] = $cuenta;
                    $auxarray["ccodcli"] = $codcli;
                    $auxarray["short_name"] = $fila["short_name"];
                    $auxarray["ctipope"] = "D";
                    $auxarray["tasa"] = $tasa;
                    $auxarray["fecmin"] = $fechamin;
                    $auxarray["dfecope"] = $periodos[$keyPeriodo]['fin'];
                    $auxarray["monto"] = 0;
                    $auxarray["cnumdoc"] = 'corte';
                    $auxarray["mincalc"] = $mincalc;
                    $auxarray["saldo"] = $saldoactual;
                    $auxarray["saldoant"] = $saldoactual;
                    $auxarray["dias"] = $diasdif;
                    $auxarray["interescal"] = round($interes, 2);
                    $auxarray["isr"] = round(($interes * $porcentajeIsr), 2);

                    array_push($data, $auxarray);
                    $setCorte = false;
                }
            }

            Log::info("data", $data);
            echo "<br> DATOS CALCULADOS: +++++++++++++++++++++++ <br>";
            echo "<pre>";
            print_r($data);
            echo "</pre>";

            /**
             * FIN PROCESANDO CALCULO SOBRE LOS RESULTADOS
             */

            /**
             * GUARDANDO RESULTADOS EN TABLAS AUXILIARES
             */

            $database->beginTransaction();

            // $tipocuenta = ;
            $rango = "" . date("d-m-Y", strtotime($periodos[$keyPeriodo]['inicio'])) . "_" . date("d-m-Y", strtotime($periodos[$keyPeriodo]['fin']));
            $totalinteres = array_sum(array_column($data, "interescal"));
            $totalimpuesto = array_sum(array_column($data, "isr"));

            $datos = array(
                'tipo' => $configuracion['ccodtip'],
                'rango' => $rango,
                'partida' => 0,
                'acreditado' => 0,
                'int_total' => $totalinteres,
                'isr_total' => $totalimpuesto,
                'fecmod' => date("Y-m-d H:i:s"),
                'codusu' => 'CRON_' . $configuracion['idConfiguracion'],
                'fechacorte' => $periodos[$keyPeriodo]['fin'],
            );
            $idahointere = $database->insert('ahointeredetalle', $datos);

            foreach ($data as $fila) {
                if ($fila["interescal"] > 0) {
                    $datos = array(
                        'codaho' => $fila["ccodaho"],
                        'codcli' => $fila["ccodcli"],
                        'nomcli' => ($fila["short_name"]),
                        'tipope' => $fila["ctipope"],
                        'fecope' => $fila["dfecope"],
                        'numdoc' => $fila["cnumdoc"],
                        'tipdoc' => "E",
                        'monto' => $fila["monto"],
                        'saldo' => $fila["saldo"],
                        'saldoant' => $fila["saldoant"],
                        'dias' => $fila["dias"],
                        'tasa' => $fila["tasa"],
                        'intcal' => $fila["interescal"],
                        'isrcal' => $fila["isr"],
                        'idcalc' => $idahointere,
                    );
                    $database->insert('ahointere', $datos);
                }
            }

            /**
             * FIN GUARDANDO RESULTADOS EN TABLAS AUXILIARES
             */

            /**
             * INICIO ACREDITACION
             */
            if ($configuracion['tipoCalculo'] == "ACREDITACION" && !$provisionar) {
                $cuentasContables = $database->selectColumns('ahomparaintere', ['id_cuenta1', 'id_cuenta2', 'id_descript_intere'], 'id_tipo_cuenta=?', [$configuracion['producto_id']]);

                echo "<br> Cuentas contables: +++++++++++++++++++++++ <br>";
                echo "<pre>";
                print_r($cuentasContables);
                echo "</pre>";
                /**
                 * CUENTA CONTABLE PARA ACREDITACION DE INTERESES
                 */
                $cuentasInteres = array_filter($cuentasContables, function ($item) {
                    return $item['id_descript_intere'] === 1;
                });

                if (empty($cuentasInteres)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta contable para la acreditación de intereses del tipo de cuenta " . $configuracion['producto_id']);
                }

                $keyInteres = array_keys($cuentasInteres)[0];

                /**
                 * CUENTA CONTABLE PARA RETENCION DE ISR
                 */
                $cuentasIsr = array_filter($cuentasContables, function ($item) {
                    return $item['id_descript_intere'] === 2;
                });
                if (empty($cuentasIsr)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta contable para la retención de ISR del tipo de cuenta " . $configuracion['producto_id']);
                }
                $keyIsr = array_keys($cuentasIsr)[0];

                /**
                 * CONSULTA DE MOVIMIENTOS A ACREDITAR
                 */

                // $movimientos = $database->getAllResults("SELECT apint.codaho,SUM(apint.intcal) AS totalint, SUM(apint.isrcal) AS totalisr, cta.nlibreta, IFNULL(cta.ctainteres,'') ctainteres,
                //                     IFNULL((SELECT MAX(numlinea) FROM ahommov WHERE ccodaho = cta.ccodaho AND nlibreta=cta.nlibreta AND cestado=1),0) numlinea,
                //                     IFNULL((SELECT MAX(correlativo) FROM ahommov WHERE ccodaho = cta.ccodaho AND cestado=1),0) correlativo
                //                 FROM ahointere AS apint
                //                 INNER JOIN ahomcta AS cta ON cta.ccodaho=apint.codaho 
                //                 WHERE apint.idcalc=? 
                //                 GROUP BY apint.codaho", [$id]);

                // Agrupar $data por 'ccodaho' y sumar 'interescal' e 'isr'
                $movimientos = [];
                foreach ($data as $fila) {
                    $codaho = $fila['ccodaho'];
                    if (!isset($movimientos[$codaho])) {
                        $movimientos[$codaho] = [
                            'codaho' => $codaho,
                            'totalint' => 0,
                            'totalisr' => 0,
                            'nlibreta' => $fila['nlibreta'] ?? 0,
                            'ctainteres' => $fila['ctainteres'] ?? '',
                            'numlinea' => 0,
                            'correlativo' => 0,
                        ];
                    }
                    $movimientos[$codaho]['totalint'] += $fila['interescal'];
                    $movimientos[$codaho]['totalisr'] += $fila['isr'];
                }
                // Convertir a array indexado
                $movimientos = array_values($movimientos);
                Log::info("movimientos", $movimientos);

                echo "<br> MOVIMIENTOS YA AGRUPADOS PARA GUARDAR Y ACREDITAR: +++++++++++++++++++++++ <br>";
                echo "<pre>";
                print_r($movimientos);
                echo "</pre>";

                if (empty($movimientos)) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron movimientos a acreditar.");
                }
                $database->beginTransaction();

                $database->update('ahointeredetalle', ['acreditado' => 1], 'id=?', [$idahointere]);
                foreach ($movimientos as $mov) {
                    $cuenta = $mov['codaho'];
                    $totalint = $mov['totalint'];
                    $totalisr = $mov['totalisr'];
                    $nlibreta = $mov['nlibreta'];
                    // $numlinea = $mov['numlinea'];
                    // $correlativo = $mov['correlativo'];

                    if ($mov['ctainteres'] != "") {
                        $cuentaSecu = $database->selectColumns('ahomcta', ['ccodaho'], 'ccodaho=?', [$mov['ctainteres']]);
                        if (empty($cuentaSecu)) {
                            $showmensaje = true;
                            throw new Exception("No se encontró la cuenta secundaria configurada para la cuenta " . $cuenta);
                        }
                        $cuenta = $cuentaSecu[0]['ccodaho'];
                    }

                    if ($totalint > 0) {
                        $ahommov = array(
                            'ccodaho' => $cuenta,
                            'dfecope' => $periodos[$keyPeriodo]['fin'],
                            'ctipope' => 'D',
                            'cnumdoc' => 'INT',
                            'ctipdoc' => 'IN',
                            'crazon' => 'INTERES',
                            'nlibreta' => $nlibreta,
                            'monto' => $totalint,
                            'lineaprint' => 'N',
                            'numlinea' => 1,
                            'correlativo' => 1,
                            'dfecmod' => date("Y-m-d H:i:s"),
                            'codusu' => 'CRON',
                            'cestado' => 1,
                            'auxi' => 'INTERE' . $idahointere,
                            'created_at' => date("Y-m-d H:i:s"),
                            'created_by' => '4',
                        );
                        $database->insert('ahommov', $ahommov);

                        if ($totalisr > 0) {
                            $ahommov = array(
                                'ccodaho' => $cuenta,
                                'dfecope' => $periodos[$keyPeriodo]['fin'],
                                'ctipope' => 'R',
                                'cnumdoc' => 'ISR',
                                'ctipdoc' => 'IP',
                                'crazon' => 'INTERES',
                                'nlibreta' => $nlibreta,
                                'monto' => $totalisr,
                                'lineaprint' => 'N',
                                'numlinea' => 2,
                                'correlativo' => 2,
                                'dfecmod' => date("Y-m-d H:i:s"),
                                'codusu' => 'CRON',
                                'cestado' => 1,
                                'auxi' => 'INTERE' . $idahointere,
                                'created_at' => date("Y-m-d H:i:s"),
                                'created_by' => '4',
                            );
                            $database->insert('ahommov', $ahommov);
                        }
                    }
                }

                /**
                 * INGRESO DE MOVIMIENTOS EN LA CONTABILIDAD PARTIDA DE INTERES
                 */
                // $camp_numcom = getnumcompdo(4, $database);
                $camp_numcom = Beneq::getNumcom($database, 4,1,$periodos[$keyPeriodo]['fin']);

                $ctb_diario = array(
                    'numcom' => $camp_numcom,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => 'INT',
                    'glosa' => 'ACREDITACION DE INTERESES A CUENTAS DE ' . strtoupper($configuracion['nombreProducto']),
                    'fecdoc' => $periodos[$keyPeriodo]['fin'],
                    'feccnt' => $periodos[$keyPeriodo]['fin'],
                    'cod_aux' => "AHOINTERE-" . $idahointere . '-CRON',
                    'id_tb_usu' => 4,
                    'id_agencia' => 1,
                    'fecmod' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                    'editable' => 0,
                );
                $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                // Sumar los valores de totalint de los elementos filtrados
                $totalInteres = array_reduce($movimientos, function ($carry, $item) {
                    return $carry + $item['totalint'];
                }, 0);


                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasInteres[$keyInteres]['id_cuenta1'],
                    'debe' => $totalInteres,
                    'haber' => 0,
                );
                $database->insert('ctb_mov', $ctb_mov);

                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasInteres[$keyInteres]['id_cuenta2'],
                    'debe' => 0,
                    'haber' => $totalInteres,
                );
                $database->insert('ctb_mov', $ctb_mov);

                /**
                 * INGRESO DE MOVIMIENTOS EN LA CONTABILIDAD PARTIDA DE ISR
                 */
                // Sumar los valores de totalisr de los elementos filtrados
                $totalIsr = array_reduce($movimientos, function ($carry, $item) {
                    return $carry + $item['totalisr'];
                }, 0);

                if ($totalIsr > 0) {
                    // $camp_numcom = getnumcompdo(4, $database);
                    $camp_numcom = Beneq::getNumcom($database, 4,1,$periodos[$keyPeriodo]['fin']);

                    $ctb_diario = array(
                        'numcom' => $camp_numcom,
                        'id_ctb_tipopoliza' => 2,
                        'id_tb_moneda' => 1,
                        'numdoc' => 'ISR',
                        'glosa' => 'RETENCION DE ISR A CUENTAS DE ' . strtoupper($configuracion['nombreProducto']),
                        'fecdoc' => $periodos[$keyPeriodo]['fin'],
                        'feccnt' => $periodos[$keyPeriodo]['fin'],
                        'cod_aux' => "AHOINTERE-" . $idahointere . '-CRON',
                        'id_tb_usu' => 4,
                        'id_agencia' => 1,
                        'fecmod' => date('Y-m-d H:i:s'),
                        'estado' => 1,
                        'editable' => 0,
                    );
                    $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentasIsr[$keyIsr]['id_cuenta1'],
                        'debe' => $totalIsr,
                        'haber' => 0,
                    );
                    $database->insert('ctb_mov', $ctb_mov);

                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentasIsr[$keyIsr]['id_cuenta2'],
                        'debe' => 0,
                        'haber' => $totalIsr,
                    );
                    $database->insert('ctb_mov', $ctb_mov);
                }
            }
            /**
             * FIN ACREDITACION
             */
            /**
             * INICIO PROVISION
             */
            if ($provisionar) {

                $cuentasContables = $database->selectColumns('ahomparaintere', ['id_cuenta1', 'id_cuenta2', 'id_descript_intere'], 'id_tipo_cuenta=? AND id_descript_intere=3', [$configuracion['producto_id']]);
                /**
                 * CUENTA CONTABLE PARA PROVISION DE INTERESES
                 */

                if (empty($cuentasContables)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta contable para la provisión de intereses del tipo de cuenta " . $configuracion['producto_id']);
                }
                // $conexion->query("UPDATE `ahointeredetalle` SET partida=1 where id=" . $id);
                $database->update('ahointeredetalle', ['partida' => 1], 'id=?', [$idahointere]);

                /**
                 * INGRESO DE MOVIMIENTOS EN LA CONTABILIDAD PARTIDA DE PROVISION
                 */
                // $camp_numcom = getnumcompdo(4, $database);
                $camp_numcom = Beneq::getNumcom($database, 4,1,$periodos[$keyPeriodo]['fin']);

                $ctb_diario = array(
                    'numcom' => $camp_numcom,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => 'PROV',
                    'glosa' => 'PROVISION DE INTERESES DE CUENTAS DE ' . strtoupper($configuracion['nombreProducto']),
                    'fecdoc' => $periodos[$keyPeriodo]['fin'],
                    'feccnt' => $periodos[$keyPeriodo]['fin'],
                    'cod_aux' => "AHOINTERE-" . $idahointere . '-CRON',
                    'id_tb_usu' => 4,
                    'id_agencia' => 1,
                    'fecmod' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                    'editable' => 0,
                );
                $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                // Sumar los valores de totalint de los elementos filtrados
                $totalInteres = array_reduce($data, function ($carry, $item) {
                    return $carry + $item['interescal'];
                }, 0);

                if ($totalinteres > 0) {
                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentasContables[0]['id_cuenta1'],
                        'debe' => $totalInteres,
                        'haber' => 0,
                    );
                    $database->insert('ctb_mov', $ctb_mov);

                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentasContables[0]['id_cuenta2'],
                        'debe' => 0,
                        'haber' => $totalInteres,
                    );
                    $database->insert('ctb_mov', $ctb_mov);
                }
            }
            /**
             * FIN PROVISION
             */


            $database->commit();
        }
    }

    $mensaje = "Proceso finalizado correctamente";

    $status = true;
} catch (Throwable $e) {
    Log::error("Error en la acreditacion: " . $e->getMessage());
    $database->rollBack();
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

echo "<br>$mensaje";
