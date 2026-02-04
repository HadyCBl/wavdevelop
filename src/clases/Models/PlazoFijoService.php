<?php

namespace App\Generic\Models;

use DateTime;
use Exception;
use Micro\Helpers\Beneq;

class PlazoFijoService
{
    private $idCrt;
    private $fecha_inicio;
    private $fecha_fin;
    private $monto;
    private $tasa;
    private $periodo;
    private $diasCalculo;
    private $plazoDias;
    private $inicioCalculo;

    public function __construct($fecha_inicio, $fecha_fin, $monto, $tasa, $periodo, $diasCalculo, $plazoDias, $inicioCalculo, $idCrt = null)
    {
        $this->fecha_inicio = $fecha_inicio;
        $this->fecha_fin = $fecha_fin;
        $this->monto = $monto;
        $this->tasa = $tasa;
        $this->periodo = $periodo;
        $this->diasCalculo = $diasCalculo;
        $this->plazoDias = $plazoDias;
        $this->inicioCalculo = $inicioCalculo;
        $this->idCrt = $idCrt;
    }

    public static function getPeriodosPf(): array
    {
        return [
            'M' => 1,  // Mensual
            'B' => 2,  // Bimestral
            'T' => 3,  // Trimestral
            'C' => 4,  // Cuatrimestral
            'S' => 6,  // Semestral
            'A' => 12, // Anual
            'V' => 0  // Vencimiento
        ];
    }

    public static function getPeriodoValue($tipoPeriodo): ?int
    {
        $periodos = self::getPeriodosPf();
        return $periodos[$tipoPeriodo] ?? null;
    }

    private function addMonths($date, $monthsToAdd)
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

    private function diferencia_dias($inicio, $fin)
    {
        $diasdif = ($this->diasCalculo == 360) ? diferenciaEnDias($inicio, $fin) : dias_dif($inicio, $fin);
        return $diasdif;
    }
    private function addDays($inicio, $days)
    {
        // $plazo -= $inicioCalculo;
        $nuevafecha = ($this->diasCalculo == 360) ? sumarDiasBase30($inicio, $days) : agregarDias($inicio, $days);
        return new DateTime($nuevafecha);
    }

    private function generateVppg()
    {
        $diasdif = $this->diferencia_dias($this->fecha_inicio, $this->fecha_fin);

        $diasdif = $diasdif + $this->inicioCalculo;

        $intcal = $this->monto * ($this->tasa / 100 / $this->diasCalculo);
        $intcal = $intcal * $diasdif;
        $ppg[0] = [
            'fecha' => $this->fecha_fin,
            'monto' => $this->monto,
            'dias_diferencia' => $diasdif,
            'monto_interes' => $intcal,
        ];

        return $ppg;
    }

    public function generatePpg(): array
    {
        if ($this->periodo === 'V') {
            return $this->generateVppg();
        }
        $ppg = [];
        $fechaAnterior = $this->fecha_inicio;
        $periodoValue = self::getPeriodoValue($this->periodo);

        $diasAcumulados = 0;
        $cont = 1;

        while ($diasAcumulados < $this->plazoDias) {
            $fecha = $this->addMonths($this->fecha_inicio, $cont * $periodoValue);
            // $diasDiferencia = dias_dif($fechaAnterior, $fecha);
            $diasDiferencia = $this->diferencia_dias($fechaAnterior, $fecha);

            // Si al sumar los días supera el plazo, solo calcula los días restantes
            if ($diasAcumulados + $diasDiferencia > $this->plazoDias) {
                $diasDiferencia = $this->plazoDias - $diasAcumulados;
                $fecha = (new DateTime($fechaAnterior))->modify('+' . $diasDiferencia . ' days')->format('Y-m-d');
            }

            $montoInteres = ($this->monto * ($this->tasa / 100 / $this->diasCalculo) * $diasDiferencia);

            $ppg[] = [
                'fecha' => $fecha,
                'monto' => $this->monto,
                'dias_diferencia' => $diasDiferencia,
                'monto_interes' => round($montoInteres, 2),
            ];

            $diasAcumulados += $diasDiferencia;
            $fechaAnterior = $fecha;
            $cont++;

            // Si ya no quedan días, termina el ciclo
            if ($diasAcumulados >= $this->plazoDias) {
                break;
            }
        }

        return $ppg;
    }

    public function acreditaIntereses($database, $fechaAcreditacion, $montoInteres)
    {
        $showmensaje = false;
        try {
            // $database->openConnection();
            // $database->beginTransaction();
            $query = "SELECT short_name,nlibreta,ctainteres,ccodcrt,cta.ccodaho,cta.estado,crt.montoapr, tip.isr
                        FROM ahomcrt crt 
                        INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                        INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                        WHERE id_crt=?;";
            $dataCertificado = $database->getAllResults($query, [$this->idCrt]);
            if (empty($dataCertificado)) {
                $showmensaje = true;
                throw new Exception("Certificado con no {$this->idCrt} no encontrado");
            }
            if ($dataCertificado[0]['estado'] != 'A') {
                $showmensaje = true;
                throw new Exception("La cuenta de ahorro no se encuentra activa");
            }

            $short_name = strtoupper($dataCertificado[0]['short_name']);
            $nlibreta = $dataCertificado[0]['nlibreta'];
            $cueninteres = $dataCertificado[0]['ctainteres'];
            $cuentaDestino = $dataCertificado[0]['ccodaho'];

            if ($cueninteres != "" && $cueninteres != null) {
                $result = $database->selectColumns('ahomcta', ['ccodaho'], 'ccodaho=?', [$cueninteres]);
                if (empty($result)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta secundaria configurada no existe");
                }
                $cuentaDestino = $cueninteres;
            }

            $ipfcalc = ($montoInteres * ($dataCertificado[0]['isr'] / 100));

            $glosa1 = "ACREDITACION DE INTERESES DE AHORRO A PLAZO FIJO DE " . $short_name . " CON CERTIFICADO NO. " . $dataCertificado[0]['ccodcrt'];
            $datosint = array(
                "ccodaho" => $cuentaDestino,
                "dfecope" => $fechaAcreditacion,
                "ctipope" => "D",
                "cnumdoc" => $dataCertificado[0]['ccodcrt'],
                "ctipdoc" => "IN",
                "crazon" => "INTERES",
                "concepto" => $glosa1,
                "nlibreta" => $nlibreta,
                // "nrochq" => 0,
                // "tipchq" => "",
                // "dfeccomp" => NULL,
                "monto" => $montoInteres,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => date('Y-m-d H:i:s'),
                "codusu" => 4,
                "cestado" => 1,
                "auxi" => "INTCRT-CRON-" . $dataCertificado[0]['ccodcrt'],
                "created_at" => date('Y-m-d H:i:s'),
                "created_by" => 4,
            );

            $glosa2 = "RETENCION DE ISR DE $short_name";
            $datosipf = array(
                "ccodaho" => $cuentaDestino,
                "dfecope" => $fechaAcreditacion,
                "ctipope" => "R",
                "cnumdoc" => $dataCertificado[0]['ccodcrt'],
                "ctipdoc" => "IP",
                "crazon" => "IPF",
                "concepto" => $glosa2,
                "nlibreta" => $nlibreta,
                // "nrochq" => 0,
                // "tipchq" => "",
                // "dfeccomp" => NULL,
                "monto" => $ipfcalc,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => date('Y-m-d H:i:s'),
                "codusu" => 4,
                "cestado" => 1,
                "auxi" => "INTCRT-CRON-" . $dataCertificado[0]['ccodcrt'],
                "created_at" => date('Y-m-d H:i:s'),
                "created_by" => 4,
            );

            $idAhommovInt = $database->insert('ahommov', $datosint);
            if ($ipfcalc > 0) {
                $idAhommovIpf = $database->insert('ahommov', $datosipf);
            }

            $database->executeQuery('CALL ahom_ordena_noLibreta(?, ?);', [$nlibreta, $cuentaDestino]);
            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$cuentaDestino]);

            /**
             * MOVIMIENTOS EN CONTABILIDAD
             */

            $result = $database->getAllResults("SELECT ap.* FROM ahomparaintere ap INNER JOIN ahomtip tip ON tip.id_tipo=ap.id_tipo_cuenta 
                        WHERE ccodtip=SUBSTR(?,7,2) AND id_descript_intere IN (1,2)", [$cuentaDestino]);

            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas contables parametrizadas.");
            }
            $keyint = array_search(1, array_column($result, 'id_descript_intere'));
            $keyisr = array_search(2, array_column($result, 'id_descript_intere'));

            if ($keyint === false || $keyisr === false) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas contables parametrizadas ()." . $keyisr);
            }

            $cuentaint1 = $result[$keyint]['id_cuenta1'];
            $cuentaint2 = $result[$keyint]['id_cuenta2'];
            $cuentaisr1 = $result[$keyisr]['id_cuenta1'];
            $cuentaisr2 = $result[$keyisr]['id_cuenta2'];

            //AFECTACION CONTABLE
            // $numpartida = getnumcompdo(4, $database); //Obtener numero de partida
            $numpartida = Beneq::getNumcom($database, 4, 1, $fechaAcreditacion);
            $datos = array(
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 2,
                'id_tb_moneda' => 1,
                'numdoc' => $dataCertificado[0]['ccodcrt'],
                'glosa' => $glosa1,
                'fecdoc' => $fechaAcreditacion,
                'feccnt' => $fechaAcreditacion,
                'cod_aux' => $cuentaDestino,
                'id_tb_usu' => 4,
                'fecmod' => date('Y-m-d H:i:s'),
                'estado' => 1,
                'karely' => 'AHO_'.$idAhommovInt,
                'editable' => 0,
                'id_agencia' => 1
            );

            $id_ctb_diario = $database->insert('ctb_diario', $datos);

            //AFECTACION CONTABLE MOV 1 
            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentaint1,
                'debe' => $montoInteres,
                'haber' => 0
            );
            $database->insert('ctb_mov', $datos);

            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentaint2,
                'debe' => 0,
                'haber' => $montoInteres
            );
            $database->insert('ctb_mov', $datos);

            if ($ipfcalc > 0) {
                $numpartida2 = getnumcompdo(4, $database); //Obtener numero de partida
                $ctb_diario = array(
                    'numcom' => $numpartida2,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => $dataCertificado[0]['ccodcrt'],
                    'glosa' => $glosa2,
                    'fecdoc' => $fechaAcreditacion,
                    'feccnt' => $fechaAcreditacion,
                    'cod_aux' => $cuentaDestino,
                    'id_tb_usu' => 4,
                    'fecmod' => date('Y-m-d H:i:s'),
                    'estado' => 1,
                     'karely' => 'AHO_'.$idAhommovIpf,
                    'editable' => 0,
                    'id_agencia' => 1
                );

                $id_ctb_diario2 = $database->insert('ctb_diario', $ctb_diario);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario2,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaisr1,
                    'debe' => $ipfcalc,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $datos);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario2,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaisr2,
                    'debe' => 0,
                    'haber' => $ipfcalc
                );
                $database->insert('ctb_mov', $datos);
            }

            // if ($accion == 1) {
            //     $ahomcrt = array(
            //         'liquidado' => 'S',
            //         'intcal' => round($montoInteres, 2),
            //         'recibo_liquid' => $norecibo,
            //         'fec_liq' => $fechaAcreditacion
            //     );

            //     $database->update('ahomcrt', $ahomcrt, 'id_crt=?', [$this->idCrt]);
            // }

            // $database->commit();
            $mensaje = "Acreditacion correcta";
            $status = true;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        } finally {
            // $database->closeConnection();
        }
    }

    // public function generatePpgt(): array
    // {
    //     $ppg = [];
    //     $fechaAnterior = $this->fecha_inicio;
    //     $fecha = $this->fecha_inicio;
    //     $fecha_fin = $this->fecha_fin;
    //     $periodoValue = self::getPeriodoValue($this->periodo);

    //     $cont = 1;
    //     while (strtotime($fecha) <= strtotime($fecha_fin)) {
    //         $fecha = $this->addMonths($this->fecha_inicio, $cont * $periodoValue);

    //         $diasDiferencia = dias_dif($fechaAnterior, $fecha);

    //         $montoInteres = ($this->monto * ($this->tasa / 100 / $this->diasCalculo) * $diasDiferencia);

    //         $ppg[] = [
    //             'fecha' => $fecha,
    //             'monto' => $this->monto,
    //             'dias_diferencia' => $diasDiferencia,
    //             'monto_interes' => $montoInteres,
    //         ];
    //         $fechaAnterior = $fecha;
    //         $cont++;
    //     }

    //     return $ppg;
    // }
}
