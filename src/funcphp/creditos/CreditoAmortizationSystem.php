<?php

namespace Creditos\Utilidades;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// use App\DatabaseAdapter;
use Micro\Helpers\Log;
use DateTime;
use Exception;
use Throwable;

class CreditoAmortizationSystem
{
    private $ccodcta;
    private $capital_inicial;
    private $tasa_interes;
    private $cuotas_original;
    private $tipo_amortizacion; // 'francesa' o 'alemana'
    public $tabla_original;
    private $tabla_actual;
    private $fecha_ultimo_pago;
    private $cuotaExistente = false;
    private $pagos_realizados;
    private $pagos_adelantados;
    private $total_kp_pagado;
    private $total_int_pagado;
    private $fecha_primera_cuota;
    private $tipo_reestructuracion = 0;
    private $fecha_primer_pago_original;
    private $cuotaOriginal;
    private $tipo_periodo;
    private $id_producto;
    private $tipPerC;
    public $newMontos = [];
    public $newFechas = [];
    private $lastNoCuota = 0;
    private $diascalculo = 360; // Por defecto, se usa 360 días para el cálculo de intereses
    private $fecha_vence;

    /**
     * esta bandera es temporal, indica que se debe hacer con el acumulado, si la fecha de pago no coincide con la fecha de una cuota
     * 1 debe registrar una nueva cuota con la fecha del pago registrado tal como se hace en la primera version de ADG. 
     * 2 buscar la cuota cercana al ultimo pago, ya sea anterior o posterior
     * 3 usar la cuota con fecha posterior al pago
     * 4 usar la cuota anterior a la fecha de pago
     */
    private $opcion_de_acumulacion = 1;
    private $db = null;

    const TIPO_FRANCESA = 'Franc';
    const TIPO_ALEMANA = 'Germa';
    const OPCION_REDUCIR_PLAZO = 'reducir_plazo';
    const OPCION_REDUCIR_CUOTA = 'reducir_cuota';

    // public function __construct($capital, $tasa_anual, $plazo_meses, $tipo_amortizacion = self::TIPO_FRANCESA)
    public function __construct($ccodcta, $db = null)
    {
        $this->ccodcta = $ccodcta;
        $this->pagos_realizados = [];
        $this->pagos_adelantados = [];
        $this->db = $db;

        $this->loadOriginalTable();
        $this->tabla_actual = $this->tabla_original;
        $this->loadDataAccount();
    }

    // private function conectarDb(): void
    // {
    //     if ($this->db === null) {
    //         try {
    //             $this->db = new DatabaseAdapter();
    //             $this->db->openConnection(1);
    //         } catch (Exception $e) {
    //             Log::error("Error al conectar BD: " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    //             throw new Exception("Error crítico al conectar BD.", 0, $e);
    //         }
    //     }
    // }

    private function loadOriginalTable()
    {
        $showmensaje = false;
        try {
            // $this->conectarDb();

            $paymentManager = new PaymentManager();
            $this->tabla_original = $paymentManager->creppg_get($this->ccodcta, $this->db);
            if (empty($this->tabla_original)) {
                $showmensaje = true;
                throw new Exception("No se pudo cargar la tabla de amortización original para la cuenta: {$this->ccodcta}");
            }
            $this->tabla_actual = $this->tabla_original;
            //VERIFICAR SI LA ULTIMA FECHA DE PAGO, ESTA EN LA TABLA DE AMORTIZACION ORIGINAL
            $ultimaFechaPago = $this->db->getSingleResult("SELECT dfecven AS ultima_fecha FROM Cre_ppg WHERE ccodcta = ? AND dfecven=?", [$this->ccodcta, $this->fecha_ultimo_pago]);
            if (!empty($ultimaFechaPago)) {
                Log::info("La última fecha de pago {$this->fecha_ultimo_pago} existe en la tabla de amortización para la cuenta: {$this->ccodcta}");
                $this->cuotaExistente = true;
            }
        } catch (Throwable $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function loadDataAccount()
    {
        $showmensaje = false;
        try {
            // $this->conectarDb();
            $query = "SELECT NCapDes, NIntApro, noPeriodo, CtipCre, NtipPerC, DFecDsbls, DfecPago, reestructuracion, 
                        id_tipo_periodo,dias_calculo,NMonCuo,prod.id idProducto,crem.DFecVen,
                        (select SUM(KP) FROM CREDKAR WHERE CCODCTA=crem.CCODCTA AND CTIPPAG='P' AND CESTADO!='X') pagadokp,
                        (select SUM(INTERES) FROM CREDKAR WHERE CCODCTA=crem.CCODCTA AND CTIPPAG='P' AND CESTADO!='X') pagadoint,
                        IFNULL((select DFECPRO FROM CREDKAR WHERE CCODCTA=crem.CCODCTA AND CTIPPAG='P' AND CESTADO!='X' ORDER BY DFECPRO DESC LIMIT 1), '') fecha_ultimo_pago
                        FROM cremcre_meta crem
                        INNER JOIN cre_productos prod on prod.id=crem.CCODPRD
                        WHERE CCODCTA = ?";
            $result = $this->db->getSingleResult($query, [$this->ccodcta]);
            if (!empty($result)) {
                $this->capital_inicial = $result['NCapDes'];
                $this->tasa_interes = $result['NIntApro'];
                $this->cuotas_original = $result['noPeriodo'];
                $this->tipo_amortizacion = $result['CtipCre'];
                $this->total_kp_pagado = $result['pagadokp'];
                // Validar si fecha_ultimo_pago no viene vacío o null, si es así, usar la fecha de desembolso
                if (!empty($result['fecha_ultimo_pago'])) {
                    $this->fecha_ultimo_pago = $result['fecha_ultimo_pago'];
                } else {
                    $this->fecha_ultimo_pago = $result['DFecDsbls'];
                }
                $this->total_int_pagado = $result['pagadoint'];
                $this->cuotaOriginal = $result['NMonCuo'];
                $this->diascalculo = $result['dias_calculo'];
                $this->fecha_primer_pago_original = $result['DfecPago'];
                $this->tipo_periodo = $result['id_tipo_periodo'];
                $this->id_producto = $result['idProducto'];
                $this->tipPerC = $result['NtipPerC'];
                $this->fecha_vence = $result['DFecVen'];
                /**
                 * Tipo de reestructuración, OPCIONES DISPONIBLES
                 * 0 = Ninguna
                 * 1 = Reducir Plazo
                 * 2 = Reducir Cuota
                 */
                $this->tipo_reestructuracion = $result['reestructuracion'];
            } else {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta con codigo: {$this->ccodcta}");
            }
        } catch (Throwable $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    public function getTasaMensual()
    {
        switch ($this->tipo_periodo) {

            case 9: // Anual

                return $this->tasa_interes / 12;

            default:

                return $this->tasa_interes;
        }
    }

    public function procesaReestructura()
    {
        $showmensaje = false;
        // $this->db->beginTransaction();
        try {
            $this->saveBitacoraCreppg();
            if ($this->opcion_de_acumulacion == 1) {
                Log::info("La opción de acumulación es 1 para la cuenta: {$this->ccodcta}");
                /**
                 * PASO 1, ELIMINACION DE LAS CUOTAS NO PAGADAS
                 */
                $this->db->delete("Cre_ppg", "ccodcta=? AND (cestado='X' OR (dfecven>=? AND cestado='P')) ", [$this->ccodcta, $this->fecha_ultimo_pago]);

                $datappg = $this->db->getAllResults("SELECT IFNULL(SUM(ncapita),0) sumkp,IFNULL(SUM(nintere),0) sumint,IFNULL(MAX(cnrocuo),0) cnrocuo FROM Cre_ppg WHERE ccodcta =? AND cestado='P'", [$this->ccodcta]);

                /**
                 * SE AGREGA UNA CUOTA PARA ACUMULAR LA DIFERENCIA, O EL RESTANTE DE LA CUOTA PAGADA, CON LA FECHA DEL ULTIMO PAGO QUE SE HIZO
                 */
                $kpnuevo = $this->total_kp_pagado - $datappg[0]['sumkp'];
                $intnuevo = $this->total_int_pagado - $datappg[0]['sumint'];
                $saldo = $this->capital_inicial - $this->total_kp_pagado;
                $nrocuo = $datappg[0]['cnrocuo'] + 1;
                $this->lastNoCuota = $nrocuo;
                $datos = array(
                    'ccodcta' => $this->ccodcta,
                    'dfecven' => $this->fecha_ultimo_pago,
                    'dfecpag' => "0000-00-00",
                    'cestado' => "P",
                    'ctipope' => "0",
                    'cnrocuo' => $nrocuo,
                    'SaldoCapital' => $saldo,
                    'nmorpag' => 0,
                    'ncappag' => $kpnuevo,
                    'nintpag' => $intnuevo,
                    'AhoPrgPag' => 0,
                    'OtrosPagosPag' => 0,
                    'ccodusu' => 4,
                    'dfecmod' => "0000-00-00",
                    'cflag' => "0",
                    'codigo' => "S",
                    'creditosaf' => "1",
                    'saldo' => $saldo,
                    'nintmor' => 0,
                    'ncapita' => $kpnuevo,
                    'nintere' => $intnuevo,
                    'NAhoProgra' => 0,
                    'OtrosPagos' => 0
                );
                $this->db->insert('Cre_ppg', $datos);

                Log::info("Se ha insertado una nueva cuota en la tabla Cre_ppg para la cuenta: {$this->ccodcta}");

                /**
                 * CALCULAR LA PRIMERA FECHA DEL NUEVO PLAN DE PAGO
                 */
                // Convertir las cadenas de fecha en objetos DateTime
                $fechaPagoObj = DateTime::createFromFormat('Y-m-d', $this->fecha_primer_pago_original);
                $fechaDesembolsoObj = DateTime::createFromFormat('Y-m-d', $this->fecha_ultimo_pago);

                // Obtener el mes siguiente al de la fecha de desembolso
                $mesSiguiente = (int) $fechaDesembolsoObj->format('m') + 1;
                $anioSiguiente = $fechaDesembolsoObj->format('Y');
                if ($mesSiguiente > 12) {
                    $mesSiguiente = 1;
                    $anioSiguiente++;
                }

                // Crear la fecha de la primera cuota usando el día de pago del mes siguiente
                $fechaPrimeraCuotaObj = new DateTime("$anioSiguiente-$mesSiguiente-" . $fechaPagoObj->format('d'));

                // Formatear la fecha de la primera cuota como año-mes-día
                $this->fecha_primera_cuota = $fechaPrimeraCuotaObj->format('Y-m-d');
                // $this->fecha

                if ($this->tipo_reestructuracion == 1) {
                    $this->recalcularReduciendoPlazo();
                } elseif ($this->tipo_reestructuracion == 2) {
                    Log::info("Reestructuración de crédito: Reduciendo cuota para la cuenta: {$this->ccodcta}");
                    $this->recalcularReduciendoCuota();
                }

                $this->saveNewTable();

                Log::info("Se ha recalculado el nuevo plan de pagos para la cuenta: {$this->ccodcta}");
                Log::info("tabla original: " . json_encode($this->tabla_original));
                Log::info("nuevo plan de pagos: " . json_encode($this->newMontos));
                Log::info("nuevas fechas de pagos: " . json_encode($this->newFechas));
            }
            // $this->db->commit();
            // $this->db->rollback();
        } catch (Throwable $e) {
            // $this->db->rollback();
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function saveBitacoraCreppg()
    {
        $showmensaje = false;
        try {
            if ($this->opcion_de_acumulacion == 1) {
                $datosAnteriores = $this->db->selectColumns('Cre_ppg', ['*'], "ccodcta=? AND (cestado='X' OR (dfecven>=? AND cestado='P'))", [$this->ccodcta, $this->fecha_ultimo_pago]);
            } else {
            }
            if (!empty($datosAnteriores)) {
                foreach ($datosAnteriores as $key => $anterior) {
                    $bitacora = array(
                        'id_real' => $anterior['Id_ppg'],
                        'ccodcta' => $anterior['ccodcta'],
                        'dfecven' => $anterior['dfecven'],
                        'dfecpag' => $anterior['dfecpag'],
                        'cestado' => $anterior['cestado'],
                        'ctipope' => $anterior['ctipope'] ?? '',
                        'cnrocuo' => $anterior['cnrocuo'],
                        'SaldoCapital' => $anterior['SaldoCapital'],
                        'nmorpag' => $anterior['nmorpag'],
                        'ncappag' => $anterior['ncappag'],
                        'nintpag' => $anterior['nintpag'],
                        'AhoPrgPag' => 0,
                        'OtrosPagosPag' => $anterior['OtrosPagosPag'],
                        'ccodusu' => $anterior['ccodusu'],
                        'dfecmod' => $anterior['dfecmod'],
                        'cflag' => $anterior['cflag'],
                        'codigo' => $anterior['codigo'],
                        'creditosaf' => $anterior['creditosaf'],
                        'saldo' => $anterior['saldo'],
                        'nintmor' => $anterior['nintmor'],
                        'ncapita' => $anterior['ncapita'],
                        'nintere' => $anterior['nintere'],
                        'NAhoProgra' => 0,
                        'OtrosPagos' => $anterior['OtrosPagos'],
                        'delete_by' => $_SESSION['id'],
                        'delete_at' => date('Y-m-d H:i:s'),
                    );

                    $this->db->insert('bitacora_Cre_ppg', $bitacora);
                }
            }
        } catch (Throwable $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    /**
     * Recalcula manteniendo la cuota y reduciendo el plazo
     */
    private function recalcularReduciendoPlazo()
    {
        $showmensaje = false;
        try {
            $result = [];
            $saldo_actual = $this->capital_inicial - $this->total_kp_pagado;
            $rate = $this->getTasaMensual() / 100;

            $utilidadesCreditos = new PaymentManager();
            $diasLaborales = $utilidadesCreditos->dias_habiles($this->db, $this->id_producto);

            if ($this->tipo_amortizacion === self::TIPO_FRANCESA) {
                /**
                 * CALCULO DE MONTOS
                 */
                $cuota_original = $this->cuotaOriginal;
                if ($cuota_original <= 0) {
                    $showmensaje = true;
                    throw new Exception("La cuota original no puede ser menor o igual a cero.");
                }
                $fecaux = $this->fecha_primera_cuota;
                $fecant = $this->fecha_ultimo_pago;
                $i = 1;
                Log::info("cuota original: $cuota_original");
                Log::info("saldo actual: $saldo_actual");
                while ($saldo_actual > 0) {
                    $fecaux = $utilidadesCreditos->ajusteDia($fecaux, $diasLaborales);
                    $dias = ($this->diascalculo == 360) ? 30 : dias_dif($fecant, $fecaux);
                    if (!$this->cuotaExistente && $i == 1) {
                        $dias = dias_dif($fecant, $fecaux);
                    }

                    //CUOTA INTERES
                    $ipmt = ($saldo_actual) * ($rate * 12) / $this->diascalculo * $dias;
                    // array_push($pagoInteres, round($ipmt, 2));
                    // $result['interes'][] = round($ipmt, 2);
                    //PAGO CAPITAL
                    if ($saldo_actual > ($cuota_original - $ipmt)) {
                        $ppmt = $cuota_original - $ipmt;
                    } else {
                        $ppmt = $saldo_actual;
                    }
                    $saldo_actual  = $saldo_actual - $ppmt;
                    // $result['capital'][] = round($ppmt, 2);
                    // $result['saldo'][] = round($saldo_actual, 2);

                    $result[] = [
                        'interes' => round($ipmt, 2),
                        'capital' => round($ppmt, 2),
                        'saldo' => round($saldo_actual, 2)
                    ];
                    Log::info("Recalculando cuota: $i", $result);
                    $fecant = $fecaux;
                    $fecaux = agregarMes($fecaux, 1);

                    if ($i > 100) {
                        $saldo_actual = 0; // Evitar bucles infinitos en caso de error en el cálculo
                        break; // Evitar bucles infinitos en caso de error en el cálculo
                    }
                    $i++;
                }

                $this->newMontos = $result;

                /**
                 * CALCULO DE NUEVAS FECHAS
                 */

                $tipPerC2 = $utilidadesCreditos->ntipPerc($this->tipPerC);

                $fechasPagos = $utilidadesCreditos->getDatesPayments($this->fecha_primera_cuota, count($result), $tipPerC2, $diasLaborales);
                $this->newFechas = $fechasPagos;
            } else if ($this->tipo_amortizacion === self::TIPO_ALEMANA) {
                $capital_fijo = round($this->capital_inicial / $this->cuotas_original, 2);

                // $fechanterior = agregarMes($fechas[0], -1);
                $fecaux = $this->fecha_primera_cuota;
                $fecant = $this->fecha_ultimo_pago;
                $i = 1;
                while ($saldo_actual > 0) {
                    $fecaux = $utilidadesCreditos->ajusteDia($fecaux, $diasLaborales);
                    $dias = ($this->diascalculo == 360) ? 30 : dias_dif($fecant, $fecaux);
                    if (!$this->cuotaExistente && $i == 1) {
                        $dias = dias_dif($fecant, $fecaux);
                    }
                    //CUOTA INTERES
                    $ipmt = abs($saldo_actual) * ($rate * 12) / $this->diascalculo * $dias;
                    // array_push($pagoInteres, round($ipmt, 2));
                    // $result['interes'][] = round($ipmt, 2);

                    //PAGO CAPITAL
                    if ($saldo_actual > $capital_fijo) {
                        $ppmt = $capital_fijo;
                    } else {
                        $ppmt = $saldo_actual;
                    }
                    $saldo_actual  = $saldo_actual - $ppmt;
                    // $result['capital'][] = round($ppmt, 2);
                    // $result['saldo'][] = round($saldo_actual, 2);
                    $result[] = [
                        'interes' => round($ipmt, 2),
                        'capital' => round($ppmt, 2),
                        'saldo' => round($saldo_actual, 2)
                    ];
                    $fecant = $fecaux;
                    $fecaux = agregarMes($fecaux, 1);
                    $i++;
                }

                $this->newMontos = $result;

                /**
                 * CALCULO DE NUEVAS FECHAS
                 */

                $tipPerC2 = $utilidadesCreditos->ntipPerc($this->tipPerC);

                $fechasPagos = $utilidadesCreditos->getDatesPayments($this->fecha_primera_cuota, count($result), $tipPerC2, $diasLaborales);
                $this->newFechas = $fechasPagos;
            }
        } catch (Throwable $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }



    /**
     * Recalcula manteniendo el plazo y reduciendo la cuota
     */
    private function recalcularReduciendoCuota()
    {
        $showmensaje = false;
        try {
            Log::info("Reestructuración de crédito: Reduciendo cuota para la cuenta: {$this->ccodcta}");
            $utilidadesCreditos = new PaymentManager();

            // calcular nueva fecha de pago, cuantos meses hay entre fecha de primera cuota y fecha de vencimiento
            $fechaInicio = new DateTime($this->fecha_primera_cuota);
            $fechaFin = new DateTime($this->fecha_vence);
            $intervalo = $fechaInicio->diff($fechaFin);
            $nuevosMeses = ($intervalo->y * 12) + $intervalo->m;

            /**
             * sumar 1 para incluir el mes de la primera cuota
             */
            $nuevosMeses = $nuevosMeses + 1;
            Log::info("Reestructuración de crédito: Nuevos meses calculados para la cuenta: {$this->ccodcta}", ['nuevosMeses' => $nuevosMeses]);
            $tipPerC2 = $utilidadesCreditos->ntipPerc($this->tipPerC);
            $diasLaborales = $utilidadesCreditos->dias_habiles($this->db, $this->id_producto);
            $rate = $this->getTasaMensual() / 100;

            $saldo_actual = $this->capital_inicial - $this->total_kp_pagado;
            if ($this->tipo_amortizacion === self::TIPO_FRANCESA || $this->tipo_amortizacion === self::TIPO_ALEMANA) {
                $fechasPagos = $utilidadesCreditos->getDatesPayments($this->fecha_primera_cuota, $nuevosMeses, $tipPerC2, $diasLaborales);
                $montos = $utilidadesCreditos->amortiza($this->tipo_amortizacion, $rate, $saldo_actual, $nuevosMeses, 0, false, $fechasPagos, $this->fecha_ultimo_pago, $this->diascalculo, true, $this->tipPerC);
            }

            $this->newMontos = $montos;
            $this->newFechas = $fechasPagos;
            Log::info("Reestructuración de crédito: Nuevos montos calculados para la cuenta: {$this->ccodcta}", $this->newMontos);
        } catch (Throwable $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function saveNewTable()
    {
        $showmensaje = false;
        try {
            $nrocuo = $this->lastNoCuota;

            foreach ($this->newMontos as $key => $montos) {
                $nrocuo++;
                // $saldo = $saldo - $capital[$j];
                //AJUSTE INICIO
                if ($key == array_key_last($this->newMontos) && $montos['saldo'] != 0) {
                    $montos['capital'] = $montos['capital'] + $montos['saldo'];
                    $montos['saldo'] = 0;
                }
                //AJUSTE FIN

                $datos = array(
                    'ccodcta' => $this->ccodcta,
                    'dfecven' => $this->newFechas[$key],
                    'dfecpag' => "0000-00-00",
                    'cestado' => "X",
                    'ctipope' => "0",
                    'cnrocuo' => $nrocuo,
                    'SaldoCapital' => $montos['saldo'],
                    'nmorpag' => 0,
                    'ncappag' => $montos['capital'],
                    'nintpag' => $montos['interes'],
                    'AhoPrgPag' => 0,
                    'OtrosPagosPag' => 0,
                    'ccodusu' => 4,
                    'dfecmod' => "0000-00-00",
                    'cflag' => "0",
                    'codigo' => "Y",
                    'creditosaf' => "1",
                    'saldo' => $montos['saldo'],
                    'nintmor' => 0,
                    'ncapita' => $montos['capital'],
                    'nintere' => $montos['interes'],
                    'NAhoProgra' => 0,
                    'OtrosPagos' => 0,
                    'OtrosPagos' => 0
                );
                $this->db->insert('Cre_ppg', $datos);
            }
            $this->db->executeQuery('CALL update_ppg_account(?);', [$this->ccodcta]);
        } catch (Throwable $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}

// Ejemplo de uso
// try {
//     // Crear crédito de $100,000 a 12% anual por 24 meses con amortización francesa
//     $credito = new CreditAmortizationSystem(100000, 12, 24, CreditAmortizationSystem::TIPO_FRANCESA);

//     // Mostrar resumen inicial
//     echo "=== RESUMEN INICIAL ===\n";
//     print_r($credito->getResumen());

//     // Simular pago adelantado en la cuota 6
//     echo "\n=== SIMULACIÓN PAGO ADELANTADO ===\n";
//     $simulacion_plazo = $credito->simularPagoAdelantado(6, 20000, CreditAmortizationSystem::OPCION_REDUCIR_PLAZO);
//     print_r($simulacion_plazo);

//     $simulacion_cuota = $credito->simularPagoAdelantado(6, 20000, CreditAmortizationSystem::OPCION_REDUCIR_CUOTA);
//     print_r($simulacion_cuota);

//     // Aplicar pago adelantado
//     $credito->registrarPagoAdelantado(6, 20000, CreditAmortizationSystem::OPCION_REDUCIR_PLAZO);

//     // Mostrar resumen después del pago adelantado
//     echo "\n=== RESUMEN DESPUÉS DEL PAGO ADELANTADO ===\n";
//     print_r($credito->getResumen());
// } catch (Exception $e) {
//     echo "Error: " . $e->getMessage();
// }
