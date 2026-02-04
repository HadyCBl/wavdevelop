<?php

namespace Creditos\Utilidades;

use Micro\Helpers\Log;
use DateTime;
use Exception;
use profit_cmpst;

// include_once __DIR__ . '/../calcuFechas.php';
// use Creditos\Utilidades\profit_cmpst;

class PaymentManager
{
    /**
     * Genera un código único de cuenta basado en la agencia y el tipo de cuenta.
     *
     * @param object $database Instancia de la base de datos para ejecutar la consulta.
     * @param int $idAgencia Identificador de la agencia.
     * @param string $tipo Tipo de cuenta ('01' para individuales, '02' para grupos).
     * @return string Código único de cuenta generado.
     * @throws Exception Si ocurre un error al generar el código de cuenta.
     */
    public function generateAccountCode($database, $idAgencia, $tipo)
    {
        try {
            $result = $database->getAllResults("SELECT cre_crecodcta(?,?) ccodcta", [$idAgencia, $tipo]);
            if (empty($result)) {
                throw new Exception("Error al generar el código de cuenta MS1024");
            }
            return $result[0]['ccodcta'];
        } catch (\Exception $e) {
            throw new Exception("Error al generar el código de cuenta: " . $e->getMessage());
        }
    }


    /**
     * Obtiene los datos del primer pago de la tabla Cre_ppg.
     *
     * @param string $ccodcta Código de cuenta.
     * @param object $database Conexión a la base de datos.
     * @return array Datos del primer pago.
     */
    public function creppg_temporal($ccodcta, $database, $db_name_general = "jpxdcegu_bd_general_coopera")
    {
        try {
            $query = "SELECT CodCli,CCODCTA,short_name,CCODPRD,MonSug,NIntApro,DfecDsbls,DfecPago,noPeriodo,NtipPerC,
            CtipCre,pr.dias_calculo,peripagcap,afectaInteres,cr.descr tipoAmortizacion,pr.id_tipo_periodo,pr.diasCorridos
            FROM cremcre_meta crem
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli 
            INNER JOIN cre_productos pr on pr.id=crem.CCODPRD
            LEFT JOIN $db_name_general.tb_credito cr ON cr.abre = crem.CtipCre
            WHERE CCODCTA = ?";
            $dataCredito = $database->getAllResults($query, [$ccodcta]);
            if (empty($dataCredito)) {
                // $showmensaje = true;
                throw new Exception("No se encontró la información del crédito para el plan de pagos temp.");
            }

            $gastosCuota = $this->gastosCuota($dataCredito[0]['CCODPRD'], $dataCredito[0]['CCODCTA'], $database);

            $diasLaborales = $this->dias_habiles($database, $dataCredito[0]['CCODPRD']);

            $info = $database->getAllResults("SELECT id_cop FROM " . $db_name_general . ".info_coperativa ins
            INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

            $interes = $dataCredito[0]['NIntApro'];
            $ntipperc = $dataCredito[0]['NtipPerC'];

            $interes_calc = new profit_cmpst();
            $NtipPerC2 = $interes_calc->ntipPerc($ntipperc);

            $rate  = (($interes / 100) / $NtipPerC2[1]);
            $rateanual = $interes / 100;
            $future_value = 0;
            $beginning = false;

            $daysdif = diferenciaEnDias($dataCredito[0]['DfecDsbls'], $dataCredito[0]['DfecPago']);
            $interesShow = $dataCredito[0]['NIntApro'];
            //PARA CREDITOS DIARIOS DE TIPO FLAT

            $postRedistribucion = true;

            if ($ntipperc == "1D" && $dataCredito[0]["CtipCre"] == "Flat") {
                $fchspgs = calculo_fechas_por_nocuota2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], 1, $diasLaborales);
                $creditoDiario = new CalculoPagosDiarios(
                    $dataCredito[0]['MonSug'], 
                    $dataCredito[0]['NIntApro'], 
                    $dataCredito[0]['noPeriodo'], 
                    $dataCredito[0]['id_tipo_periodo'], 
                    $dataCredito[0]['dias_calculo'],
                    $gastosCuota ?? [], // gastos
                    $fchspgs ?? []      // fechas
                );
                $amortiza = $creditoDiario->calculoMontosDiario();
                // La tabla ya viene con la estructura completa, no necesita procesamiento adicional
                return $amortiza;
            } else if ($ntipperc == "7D" && $dataCredito[0]["CtipCre"] == "Flat") {
                $fchspgs = calculo_fechas_por_nocuota2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], 7, $diasLaborales);
                $creditoSemanal = new CalculoPagosSemanales(
                    $dataCredito[0]['MonSug'],
                    $dataCredito[0]['NIntApro'],
                    $dataCredito[0]['noPeriodo'],
                    $dataCredito[0]['id_tipo_periodo'],
                    $dataCredito[0]['diasCorridos'],
                    $daysdif,
                    $gastosCuota ?? [], // gastos
                    $fchspgs ?? []   // fechas
                );
                $amortiza = $creditoSemanal->generarTablaAmortizacion();
                $interesShow = $creditoSemanal->getTasaMensual();
                $gastosDistribuidos = $amortiza;
                $postRedistribucion = false;
                // La tabla ya viene con la estructura completa, no necesita procesamiento adicional
                return $amortiza;
            } else if ($ntipperc == "15D" && $dataCredito[0]["CtipCre"] == "Flat") {
                $fchspgs = $interes_calc->calcudate2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], $NtipPerC2[2], $diasLaborales);
                $creditoQuincenal = new CalculoPagosQuincenales(
                    $dataCredito[0]['MonSug'], 
                    $dataCredito[0]['NIntApro'], 
                    $dataCredito[0]['noPeriodo'], 
                    $dataCredito[0]['id_tipo_periodo'],
                    $gastosCuota ?? [], // gastos
                    $fchspgs ?? []      // fechas
                );
                $amortiza = $creditoQuincenal->generarTablaAmortizacion();
                $interesShow = $creditoQuincenal->getTasaMensual();
                // La tabla ya viene con la estructura completa, no necesita procesamiento adicional
                return $amortiza;
            } else if (in_array($info[0]["id_cop"], [15, 27, 29])) {

                $fchspgs = $interes_calc->calcudate2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], $NtipPerC2[2], $diasLaborales);
                $amortiza = amortizaespecialadg($dataCredito[0]['CtipCre'], $rate, $dataCredito[0]['MonSug'], $dataCredito[0]['noPeriodo'], $future_value, $beginning, $daysdif, $ntipperc, $dataCredito[0]['peripagcap'], $dataCredito[0]['afectaInteres']);
            } else {

                $fchspgs = $interes_calc->calcudate2($dataCredito[0]['DfecPago'], $dataCredito[0]['noPeriodo'], $NtipPerC2[2], $diasLaborales);
                $amortiza = amortiza($dataCredito[0]['CtipCre'], $rate, $dataCredito[0]['MonSug'], $dataCredito[0]['noPeriodo'], $future_value, $beginning, $fchspgs[1], $dataCredito[0]['DfecDsbls'], $dataCredito[0]['dias_calculo'],  $dataCredito[0]['diasCorridos'], $ntipperc, $dataCredito[0]['peripagcap'], $dataCredito[0]['afectaInteres']);
                $interesShow = $interesShow / 12;
            }

            if ($postRedistribucion) {
                $gastosDistribuidos = $this->distribucionGastosPorCuota($amortiza, $fchspgs, $gastosCuota, $dataCredito[0]['MonSug']);
            }

            foreach ($gastosDistribuidos as $key => $row) {

                $totalOtros = array_sum(array_column($row['gastos'] ?? [], 'monto'));
                $totalOtros = (!empty($row['gastos'])) ? array_sum(array_column($row['gastos'], 'monto')) : 0;

                // $auxiliar[$key]['ccodcta'] = $ccodcta;
                $auxiliar[$key]['dfecven'] = $row['fecha'];
                // $auxiliar[$key]['cestado'] = 'X';
                // $auxiliar[$key]['ctipope'] = '0';
                $auxiliar[$key]['cnrocuo'] = ($key + 1);
                $auxiliar[$key]['SaldoCapital'] = $row['saldo'];
                $auxiliar[$key]['nintere'] = $row['interes'];
                $auxiliar[$key]['ncapita'] = $row['capital'];
                // $auxiliar[$key]['NAhoProgra'] = $dataCredito[0]['MonSug'];
                $auxiliar[$key]['OtrosPagos'] = $totalOtros;
                $auxiliar[$key]['nintpag'] = $row['interes'];
                $auxiliar[$key]['ncappag'] = $row['capital'];
                // $auxiliar[$key]['AhoPrgPag'] = $dataCredito[0]['MonSug'];
                $auxiliar[$key]['OtrosPagosPag'] = $totalOtros;
                // $auxiliar[$key]['dfecmod'] = date("Y-m-d");
                $auxiliar[$key]['cuota'] = $row['cuota'];
            }
            return $auxiliar;
        } catch (Exception $e) {
            throw new Exception("Error al obtener los datos de Cre_ppg: " . $e->getMessage());
        }
    }


    /**
     * Obtiene el plan de pagos de la tabla Cre_ppg para un código de cuenta específico.
     *
     * @param string $ccodcta Código de cuenta.
     * @param object $database Conexión a la base de datos.
     * @return array|false Datos de la tabla Cre_ppg o false si no se encuentran resultados.
     * @throws Exception Si ocurre un error al obtener los datos.
     */
    public function creppg_get($ccodcta, $database)
    {
        try {
            $sql = "SELECT * FROM Cre_ppg WHERE ccodcta = ?";
            $params = [$ccodcta];
            $result = $database->getAllResults($sql, $params);

            if (empty($result)) {
                return false;
            }

            $data = [];
            foreach ($result as $fila) {
                $fila['cuota'] = $fila['ncapita'] + $fila['nintere'];
                $data[] = $fila;
            }

            return $data;
        } catch (\Exception $e) {
            throw new Exception("Error al obtener los datos de Cre_ppg: " . $e->getMessage());
        }
    }

    private function dia($fecha)
    {
        return date('N', strtotime($fecha));
    }

    public function gastosCuota($idProducto, $account, $database)
    {
        try {
            $sql = "SELECT cg.*, cm.CCODPRD, cm.MonSug, cm.CodCli, tipg.nombre_gasto, cm.NtipPerC tiperiodo, cm.noPeriodo, cl.short_name,afecta_modulo 
                    FROM cremcre_meta cm 
                    INNER JOIN cre_productos_gastos cg ON cm.CCODPRD = cg.id_producto 
                    INNER JOIN cre_tipogastos tipg ON tipg.id = cg.id_tipo_deGasto
                    INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
                    WHERE cm.CCODCTA = ? AND cm.CCODPRD = ? AND tipo_deCobro = 2 AND cg.estado = 1";

            $params = [$account, $idProducto];
            $result = $database->getAllResults($sql, $params);

            if (empty($result)) {
                return null;
            }

            return $result;
        } catch (\Exception $e) {
            // // Log the exception or handle it as needed
            // error_log($e->getMessage());
            // return null;
            throw new Exception("Error al obtener los gastos : " . $e->getMessage());
        }
    }
    public function gastosEnCuota($idProducto, $database)
    {
        try {
            $sql = "SELECT cg.*, tipg.nombre_gasto,tipg.afecta_modulo
                        FROM cre_productos_gastos cg
                        INNER JOIN cre_tipogastos tipg ON tipg.id = cg.id_tipo_deGasto
                        WHERE cg.id_producto = ? AND tipo_deCobro = 2 AND cg.estado = 1;";

            $params = [$idProducto];
            $result = $database->getAllResults($sql, $params);

            if (empty($result)) {
                return null;
            }

            return $result;
        } catch (\Exception $e) {
            // // Log the exception or handle it as needed
            // error_log($e->getMessage());
            // return null;
            throw new Exception("Error al obtener los gastos : " . $e->getMessage());
        }
    }
    // Obtiene dias laborales por producto
    public function dias_habiles($database, $idProducto = 0)
    {
        try {
            $sql = "SELECT * FROM tb_dias_laborales WHERE producto = ? ORDER BY id_dia";
            $params = [$idProducto];
            $result = $database->getAllResults($sql, $params);

            if (empty($result)) {
                $sql = "SELECT * FROM tb_dias_laborales WHERE producto = 0 ORDER BY id_dia";
                $result = $database->getAllResults($sql);
            }

            return empty($result) ? false : $result;
        } catch (\Exception $e) {
            throw new Exception("Error al obtener los días laborales: " . $e->getMessage());
        }
    }

    /**
     * distribucion de gastos por cuota
     * @param array $gastos
     * @param array $cuotas
     * @return array
     */
    public function distribucionGastosPorCuota($plan, $fechas, $gastos, $montoCredito = NULL)
    {
        $result = [];

        $intereses = $plan[0];
        $capitales = $plan[1];
        $saldosCuo = $plan[2];
        $fechasCuota  = $fechas[1];
        foreach ($fechasCuota as $key => $row) {
            //AJUSTE INICIO
            if ($key == array_key_last($fechasCuota) && $saldosCuo[$key] != 0) {
                $capitales[$key] = $capitales[$key] + $saldosCuo[$key];
                $saldosCuo[$key] = 0;
            }
            //AJUSTE FIN
            $result[$key] = [
                'nrocuota' => $key + 1,
                'fecha' => $row,
                'cuota' => $intereses[$key] + $capitales[$key],
                'interes' => $intereses[$key],
                'capital' => $capitales[$key],
                'saldo' => $saldosCuo[$key],
                'gastos' => []
            ];

            if (!empty($gastos)) {
                foreach ($gastos as $gasto) {
                    $cantidadCuotas = count($fechasCuota);
                    $tipo = $gasto['tipo_deMonto'];
                    $monto = $gasto['monto'];
                    $calculox = $gasto['calculox'];
                    $monsugc = $montoCredito ?? $gasto['MonSug'];
                    $distribucion = $gasto['distribucion'];

                    /**
                     * VALIDACION PARA LA DISTRIBUCION DE GASTOS
                     * Si el gasto tiene una distribucion, se valida que sea un numero entre 1 y la cantidad de cuotas,
                     * de lo contrario, se toma que no tiene distribucion y se cobra en todas las cuotas.
                     */
                    if (is_numeric($distribucion) && $distribucion > 0 && $distribucion <= count($fechasCuota)) {
                        $cantidadCuotas = $distribucion;
                    }

                    if ($tipo == 1) {
                        // Monto fijo por cuota
                        $mongas = ($calculox == 1) ? $monto : (($calculox == 2) ? ($monto / $cantidadCuotas) : $monto);
                    } elseif ($tipo == 2) {
                        // Porcentaje del monto de la cuota
                        $mongas = ($calculox == 1) ? ($monto / 100 * $capitales[$key]) : (($calculox == 2) ? ($monto / 100 * $intereses[$key]) : (($calculox == 3) ? ($monto / 100 * ($capitales[$key] + $intereses[$key])) : (($calculox == 4) ? ($monsugc * $monto / 100 / 12) : (($calculox == 5) ? ($monsugc * $monto / 100 / $cantidadCuotas) : 0))));
                    }
                    // Si la cuota es mayor a la cantidad de cuotas distribuidas, no se cobra el gasto
                    if (($key + 1) <= $cantidadCuotas) {
                        // Agregar el gasto a la cuota correspondiente
                        $result[$key]['gastos'][] = [
                            'id' => $gasto['id'],
                            'afecta_modulo' => $gasto['afecta_modulo'],
                            'monto' => round($mongas, 2)
                        ];
                    }
                }

                /**
                 * Calcular el total de gastos para la cuota actual
                 */
                $totalGastos = array_sum(array_column($result[$key]['gastos'], 'monto'));
                $result[$key]['cuota'] += $totalGastos;
            }
        }
        return $result;
    }

    public function ajusteDia($fechaini, $diaslaborales)
    {
        $daY = $fechaini;
        $numdia = date('N', strtotime($fechaini));
        $indice = array_search($numdia, array_column($diaslaborales, 'id_dia'));
        if ($diaslaborales[$indice]['laboral'] == 0) {
            $diareemplazo = $diaslaborales[$indice]['id_dia_ajuste'];
            $j = $indice;
            $flag = false;
            $cont = 0;
            while (!$flag && $cont < 100) {
                $j = ($j >= 6) ? 0 : $j + 1;
                if ($diaslaborales[$j]['id_dia'] == $diareemplazo) {
                    $flag = true;
                }
                $cont++;
            }
            if ($flag) {
                $cantdias = ($cont <= 3) ? '+ ' . $cont : '- ' . ($numdia - ($cont - (7 - $numdia)));
                $daY = date('Y-m-d', strtotime($fechaini . ' ' . $cantdias . ' day'));
            } else {
                $daY = $fechaini;
            }
        }
        return $daY;
    }

    public function getDatesPayments($fechaini, $NoCuota, $periodo, $diaslaborales)
    {
        $fechaini2 = $fechaini;
        $fchspgs = [];
        $daY = $fechaini;

        $meses = (strpos($periodo, 'months') !== false) ? true : false;
        $mesesASumar = (int)trim(str_replace(['months', '+'], '', $periodo));
        for ($i = 1; $i <= $NoCuota; $i++) {
            /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                            AGREGADO POR BENEQ*/
            $daY = $this->ajusteDia($fechaini, $diaslaborales);
            // $cantdias = 0;
            // $numdia = date('N', strtotime($fechaini));
            // $indice = array_search($numdia, array_column($diaslaborales, 'id_dia'));
            // if ($diaslaborales[$indice]['laboral'] == 0) {
            //     $diareemplazo = $diaslaborales[$indice]['id_dia_ajuste'];
            //     $j = $indice;
            //     $flag = false;
            //     $cont = 0;
            //     while (!$flag) {
            //         $j = ($j >= 6) ? 0 : $j + 1;
            //         if ($diaslaborales[$j]['id_dia'] == $diareemplazo) {
            //             $flag = true;
            //         }
            //         $cont++;
            //     }
            //     $cantdias = ($cont <= 3) ? '+ ' . $cont : '- ' . ($numdia - ($cont - (7 - $numdia)));
            //     $daY = date('Y-m-d', strtotime($fechaini . ' ' . $cantdias . ' day'));
            //     $dia = date('D', strtotime($daY));
            // }
            /*                    FIN AGREGADO POR BENEQ
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            array_push($fchspgs, $daY);

            $daY = date('Y-m-d', strtotime($fechaini . $periodo));
            if ($meses) {
                $daY = self::addMonths2($fechaini2, $mesesASumar * $i);
            }
            $fechaini = $daY;
        }
        return $fchspgs;
    }

    private function addMonths2($date, $monthsToAdd)
    {
        if (!$date instanceof DateTime) {
            $date = new DateTime($date);
        }
        $tmpDate = clone $date;
        $tmpDate->modify('first day of +' . (int) $monthsToAdd . ' month');

        if ($date->format('j') > $tmpDate->format('t')) {
            $daysToAdd = $tmpDate->format('t') - 1;
        } else {
            $daysToAdd = $date->format('j') - 1;
        }

        $tmpDate->modify('+ ' . $daysToAdd . ' days');


        return $tmpDate->format('Y-m-d');
    }

    public function ntipPerc($periodo)
    {
        switch ($periodo) {
            // Pago mensual
            case '1M':
                return ' + 1 months';
                break;
            //// Pago Bimensual
            case '2M':
                return ' + 2 months';
                break;
            //// Pago Trimestral
            case '3M':
                return ' + 3 months';
            case '4M':
                return ' + 4 months';
            case '5M':
                return ' + 5 months';
                // PAGO Semestral
            case '6M':
                return ' + 6 months';
                break;
            // Pago DIARIO, falta semanal y quincenal
            case '1D':
                return ' + 1 Day';
                break;
            //  PAGO semanal 
            case '7D':
                return ' + 7 Day';
                break;
            // PAGO quincenal
            case '15D':
                return ' + 15 Day';
                break;
            // PAGO Catorcenal
            case '14D':
                return ' + 14 Day';
                break;
            // POR DEFECTO SE DARA PAGO MENSUAL
            default:
                return ' + 1 months';
                break;
        }
    }

    public function getFactor($ntipperc)
    {
        switch ($ntipperc) {
            case '1M':
                return 1;
            case '2M':
                return 2;
            case '3M':
                return 3;
            case '4M':
                return 4;
            case '5M':
                return 5;
            case '6M':
                return 6;
            default:
                return 1;
        }
    }

    //PRUEBA PARA OBTENER DATOS DEPENDIENDO DEL TIPO DE CREDITO,  TABIEN ESTA EN FUN_PPG.PHP  SE COLO AHI
    public function amortiza($CtipCre, $rate, $MonSug, $periods, $future_value, $beginning, $fechas, $fechadesembolso, $diascalculo, $primeracuota = false, $ntipperc = "1M", $peripagcap = 1, $afectaInteres = 1)
    {
        $pagoInteres = [];
        $pagoCapital = [];
        $saldocapital = [];
        $result = [];

        switch ($CtipCre) {
            case 'Amer':
                $CAPITAL = abs($MonSug);
                $ipmt = round(($rate * $CAPITAL), 2);
                for ($i = 1; $i <= ($periods - 1); $i++) {
                    //interes
                    // array_push($pagoInteres, $ipmt);
                    // array_push($pagoCapital, 0);
                    // array_push($saldocapital, $CAPITAL);
                    $result[] = [
                        'interes' => $ipmt,
                        'capital' => 0,
                        'saldo' => $CAPITAL
                    ];
                }
                $result[] = [
                    'interes' => $ipmt,
                    'capital' => $CAPITAL,
                    'saldo' => 0
                ];
                return $result;
                // array_push($pagoInteres, $ipmt);
                // array_push($pagoCapital, $CAPITAL);
                // array_push($saldocapital, 0);
                // return array($pagoInteres, $pagoCapital, $saldocapital);

                //  HAY QUE VER QUE AL LLGAR AL FINAL SE DEBE DE 
                break;
            // -------------------> TABLA DE AMORTIZACION FRANCESA
            case 'Franc':
                //VERSION ACTUAL
                $CAPITAL = $MonSug;
                $MonSug = $MonSug * -1;
                $when = $beginning ? 1 : 0;


                // $rate = ($rate * 12) / 365;
                //SE CALCULA LA CUOTA ORIGINAL
                // $cuota = $CAPITAL * ($rate * pow(1 + $rate, $periods)) / (pow(1 + $rate, $periods) - 1);
                $cuota =  ($future_value + ($CAPITAL * \pow(1 + $rate, $periods)))
                    /
                    ((1 + $rate * $when) / $rate * (\pow(1 + $rate, $periods) - 1));


                $i = 1;
                // $rate = $rate * 365;
                $rate = ($ntipperc == "1M") ? ($rate * 12) : (($ntipperc == "6M") ? ($rate * 2) : (($ntipperc == "3M") ? ($rate * 4) : (($ntipperc == "2M") ? ($rate * 6) : $rate)));

                // $rate = 0.6;
                $saldo = $CAPITAL;
                // $fechanterior = $fechadesembolso;

                $cuotas = $this->refixedcuotas($cuota, $CAPITAL, $fechas, $fechadesembolso, $rate, $diascalculo, $CAPITAL, $primeracuota, 1, $ntipperc);

                return $cuotas;
                // return array($pagoInteres, $pagoCapital, $saldocapital);
                break;

            case 'Flat':
                $CAPITAL   = abs($MonSug);
                // $peripagcap = ($ntipperc == "1M") ? $peripagcap : $periods;
                $intstotal = round(($CAPITAL * $rate), 2); // interes flat
                $periodokp = (int)($periods / $peripagcap);
                $capflatreal   =  round($CAPITAL / $periodokp, 2); //capital flat  
                $auxshow = 1;
                //,$ntipperc,$peripagcap
                for ($i = 1; $i <= $periods; $i++) {
                    $capflat = ($auxshow >= $peripagcap) ? $capflatreal : 0;
                    $auxshow = ($auxshow >= $peripagcap) ? 1 : $auxshow + 1;
                    $intflat = ($ntipperc == "1M" && $peripagcap > 1 && $afectaInteres == 1) ? round(($CAPITAL * $rate), 2) : $intstotal;

                    $cuota     = $intflat + $capflat;
                    $CAPITAL   = $CAPITAL - $capflat;
                    // array_push($saldocapital, round($CAPITAL, 2));
                    // array_push($pagoCapital, round($capflat, 2));
                    // array_push($pagoInteres, round($intflat, 2));
                    $result[] = [
                        'interes' => $intflat,
                        'capital' => $capflat,
                        'saldo' => $CAPITAL
                    ];
                }
                return $result;
                break;
            case 'Germa':
                $Cap_amrt = round(($MonSug) / $periods, 2);
                // $fechanterior = $fechadesembolso;
                $fechanterior = agregarMes($fechas[0], -1);
                $rate = $rate * 12;
                foreach ($fechas as $key => $fecha) {
                    //interes
                    $dias = ($diascalculo == 360) ? 30 : dias_dif($fechanterior, $fecha); //VERSION 1  USANDO LA FECHA DE DESEMBOLSO COMO PUNTO DE PARTIDA
                    // $dias = ($diascalculo == 360) ? 30 : 31; //VERSION 2  USANDO LA 31 DIAS PARA CADA MES
                    // $dias = ($diascalculo == 360) ? 30 : date('t', strtotime($fecha)); //VERSION 3  USANDO LOS DIAS DEL MES DE LA FECHA DE PAGO
                    if ($primeracuota && $key == 0) {
                        $dias = dias_dif($fechadesembolso, $fecha);
                    }
                    $ipmt = abs($MonSug) * ($rate) / $diascalculo * $dias;

                    // $ipmt = round(($rate * $MonSug), 2);
                    // array_push($pagoInteres, $ipmt);


                    //Saldo Capital
                    $MonSug = $MonSug - $Cap_amrt;
                    // array_push($saldocapital, $MonSug);
                    //CUOTA A PAGAR 
                    //$ppmt = abs($Cap_amrt) + $ipmt;
                    // Cambie $ppmt por $Cap_amrt en el array_push
                    // array_push($pagoCapital, abs($Cap_amrt));
                    $result[] = [
                        'interes' => round($ipmt, 2),
                        'capital' => abs($Cap_amrt),
                        'saldo' => round($MonSug, 2)
                    ];
                    $fechanterior = $fecha;
                }
                return $result;
                break;
            // ------------------->
            default:
                return $result;
                break;
        }
    }

    public function refixedcuotas($cuota, $diferencia, $fechas, $fechadesembolso, $rate, $diascalculo, $CAPITAL, $primeracuota, $repet, $ntipperc = "1M")
    {
        $pagoInteres = [];
        $pagoCapital = [];
        $saldocapital = [];
        $result = [];

        $cuota2 = ($repet == 1) ? $cuota : ($cuota + ($diferencia / count($fechas)));
        $cuota = ($cuota + $cuota2) / 2;
        // $cuota = round($cuota, 2);

        $saldo = $CAPITAL;
        $fechanterior = $fechadesembolso;
        $i = 0;
        foreach ($fechas as $fecha) {
            $dias = ($diascalculo == 360) ? 30 * $this->getFactor($ntipperc) : dias_dif($fechanterior, $fecha);
            if ($primeracuota && $i == 0) {
                $dias = ($diascalculo == 360) ? diferenciaEnDias($fechadesembolso, $fecha) : dias_dif($fechadesembolso, $fecha);
            }

            //CUOTA INTERES
            // $ipmt = abs($saldo) * ($rate) / $diascalculo * $dias;
            $ipmt = ($saldo) * ($rate) / $diascalculo * $dias;
            $ipmt = round($ipmt, 2);
            // array_push($pagoInteres, $ipmt);

            //CUOTA CAPITAL
            // $ppmt = $cuota - $ipmt;
            $ppmt = $cuota - (($saldo) * ($rate) / $diascalculo * $dias);
            $ppmt = ($ppmt > 0) ? round($ppmt, 2) : 0;

            // array_push($pagoCapital, $ppmt);

            //SALDO
            $saldo  = $saldo - $ppmt;
            $saldo = round($saldo, 2);
            // array_push($saldocapital, $saldo);
            $result[] = [
                'interes' => round($ipmt, 2),
                'capital' => abs($ppmt),
                'saldo' => round($saldo, 2)
            ];

            $fechanterior = $fecha;
            $i++;
        }
        $cuotas = $result;
        // $cuotas = array($pagoInteres, $pagoCapital, $saldocapital);
        // $diferencia = ($CAPITAL) - (array_sum($pagoCapital));
        // $diferencia = (count($fechas) * $cuota) - (array_sum($pagoCapital) + array_sum($pagoInteres));
        $diferencia = $saldo;
        $diferencia = abs((int)($diferencia));
        if ($diferencia > 4 && $repet <= 2000) {
            // if ($diferencia > 4) {
            $cuotas = $this->refixedcuotas($cuota, $saldo, $fechas, $fechadesembolso, $rate, $diascalculo, $CAPITAL, $primeracuota, $repet + 1);
        }
        return $cuotas;
    }

    public function descuentosDesembolso($codcuenta, $database)
    {

        try {
            $sql = "SELECT cg.*, cm.CCODPRD, cm.MonSug, cm.CodCli, tipg.nombre_gasto, cm.NtipPerC tiperiodo, cm.noPeriodo, cl.short_name, tipg.id_nomenclatura, tipg.afecta_modulo, DFecDsbls fecdes
                    FROM cremcre_meta cm
                    INNER JOIN cre_productos_gastos cg ON cm.CCODPRD = cg.id_producto
                    INNER JOIN cre_tipogastos tipg ON tipg.id = cg.id_tipo_deGasto
                    INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
                    WHERE cm.CCODCTA = ? AND tipo_deCobro = 1 AND cg.estado = 1 AND tipg.estado=1 AND tipg.afecta_modulo NOT IN (3)";

            $result = $database->getAllResults($sql, [$codcuenta]);

            if (empty($result)) {
                return null;
            }

            foreach ($result as $key => $gasto) {
                $tipo = $gasto['tipo_deMonto'];
                $nombregasto = $gasto['nombre_gasto'];
                $monapro = $gasto['MonSug'];
                $cant = $gasto['monto'];
                $calculax = $gasto['calculox'];
                $cuotas = $gasto['noPeriodo'];
                $tiperiodo = $gasto['tiperiodo'];
                $plazo = ($tiperiodo == '1M') ? $cuotas : (($tiperiodo == '15D' || $tiperiodo == '14D') ? ($cuotas / 2) : (($tiperiodo == '7D') ? ($cuotas / 4) : (($tiperiodo == '1D') ? ($cuotas / 28) : $cuotas)));
                $mongas = 0;
                if ($tipo == 1) {
                    $mongas = ($calculax == 1) ? ($cant) : (($calculax == 2) ? ($cant * $plazo) : (($calculax == 3) ? ($cant * $plazo * $monapro) : ($cant * $monapro)));
                }
                if ($tipo == 2) {
                    $mongas = ($calculax == 1) ? ($cant / 100 * $monapro) : (($calculax == 2) ? ($cant / 100 * $plazo) : (($calculax == 3) ? ($cant / 100 * $plazo * $monapro) : ($cant / 100 * $monapro)));
                }
                $result[$key]['mongas'] = round($mongas, 2);
                if ($gasto['afecta_modulo'] == 3) {
                    $result[$key]['cuentaAnteriores'] = $this->getcuentas($codcuenta, $database);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Error al obtener los descuentos: " . $e->getMessage());
            throw new Exception("Error al obtener los descuentos : " . $e->getMessage());
        }
    }

    public function getcuentas($idc, $database)
    {
        try {
            $datoscreditos = $database->getAllResults('SELECT CCODCTA,NCapDes,DfecPago fecpago,NIntApro intapro,IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG="P" AND CESTADO!="X"),0) pagadokp,
            IFNULL((SELECT SUM(nintpag) FROM Cre_ppg WHERE ccodcta=cm.CCODCTA),0) intpen,
            IFNULL((SELECT MAX(dfecven) from Cre_ppg where cestado="P" AND ccodcta=cm.CCODCTA),"-") fecult,cm.DFecDsbls fechaDesembolso
            FROM cremcre_meta cm WHERE CodCli IN (SELECT Codcli FROM cremcre_meta WHERE CCODCTA=?)
            AND CCODCTA!=? AND Cestado="F" AND TipoEnti="INDI";', [$idc, $idc]);
            if (empty($datoscreditos)) {
                return null;
            }
            // foreach ($datoscreditos as $key => $credito) {
            //     $fecult = ($credito['fecult'] == "-") ? $fecdes : $credito['fecult'];
            //     $fecult = (($fecult) > ($hoy)) ? $hoy : $fecult;
            //     $intapro = $cuentas[$j]['intapro'];
            //     $saldo = round($capdes - $pagadokp, 2);
            //     $diasdif = dias_dif($fecult, $hoy);
            //     if ($calculointeres) {
            //         $intpen = $saldo * $intapro / 100 / 360 * $diasdif;
            //     }
            //     $intpen = round($intpen, 2);
            //     $intpen = ($intpen < 0) ? 0 : $intpen;
            // }
            return $datoscreditos;
        } catch (\Exception $e) {
            throw new Exception("Error al obtener los creditos anteriores: ");
            Log::error("Error al obtener los creditos anteriores: " . $e->getMessage());
        }
    }
}
