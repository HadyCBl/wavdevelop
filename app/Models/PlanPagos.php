<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;
use Micro\Generic\Auth;

class PlanPagos
{
    private DatabaseAdapter $db;
    private $userId;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
        $this->userId = Auth::getUserId();
    }

    /**
     * Crear cuota en plan de pagos
     */
    public function crearCuota(array $datos): int
    {
        $required = ['id_cuenta', 'fecven', 'nocuota', 'capital', 'interes'];
        foreach ($required as $field) {
            if (!isset($datos[$field])) {
                throw new Exception("El campo {$field} es requerido.");
            }
        }

        $cuota = [
            'id_cuenta' => $datos['id_cuenta'],
            'tipo' => $datos['tipo'] ?? 'original',
            'fecven' => $datos['fecven'],
            'nocuota' => $datos['nocuota'],
            'capital' => $datos['capital'],
            'interes' => $datos['interes'],
            'cappag' => $datos['capital'], // Inicialmente igual al capital
            'intpag' => $datos['interes'], // Inicialmente igual al interés
            'mora' => $datos['mora'] ?? 0,
            'id_tipomov' => $datos['id_tipomov'] ?? null,
            'aux' => $datos['aux'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $datos['updated_by'] ?? null
        ];

        return $this->db->insert('cc_ppg', $cuota);
    }

    /**
     * Obtener cuotas pendientes de una cuenta
     */
    public function getCuotasPendientes(int $idCuenta, $fechaCorte = null): array
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');

        return $this->db->getAllResults(
            "SELECT *, 
                (capital - cappag) as capital_pendiente,
                (interes - intpag) as interes_pendiente
            FROM cc_ppg
            WHERE id_cuenta = ? AND fecven <= ? 
                AND ((capital - cappag) > 0.01 OR (interes - intpag) > 0.01)
            ORDER BY fecven ASC, nocuota ASC",
            [$idCuenta, $fechaCorte]
        );
    }

    /**
     * Aplicar pago a cuotas
     */
    public function aplicarPago(int $idCuenta, float $montoPago, string $fecha): array
    {
        $cuotasPendientes = $this->getCuotasPendientes($idCuenta, $fecha);
        $montoRestante = $montoPago;
        $cuotasAfectadas = [];

        foreach ($cuotasPendientes as $cuota) {
            if ($montoRestante <= 0) break;

            $capitalPendiente = $cuota['capital'] - $cuota['cappag'];
            $interesPendiente = $cuota['interes'] - $cuota['intpag'];
            $moraPendiente = $cuota['mora'];

            // Aplicar primero a mora
            $abonoMora = min($moraPendiente, $montoRestante);
            $montoRestante -= $abonoMora;

            // Luego a interés
            $abonoInteres = min($interesPendiente, $montoRestante);
            $montoRestante -= $abonoInteres;

            // Finalmente a capital
            $abonoCapital = min($capitalPendiente, $montoRestante);
            $montoRestante -= $abonoCapital;

            // Actualizar cuota
            $this->db->update(
                'cc_ppg',
                [
                    'cappag' => $cuota['cappag'] + $abonoCapital,
                    'intpag' => $cuota['intpag'] + $abonoInteres,
                    'mora' => $moraPendiente - $abonoMora,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$cuota['id']]
            );

            $cuotasAfectadas[] = [
                'id_cuota' => $cuota['id'],
                'nocuota' => $cuota['nocuota'],
                'abono_capital' => $abonoCapital,
                'abono_interes' => $abonoInteres,
                'abono_mora' => $abonoMora
            ];
        }

        return [
            'cuotas_afectadas' => $cuotasAfectadas,
            'monto_aplicado' => $montoPago - $montoRestante,
            'monto_sobrante' => $montoRestante
        ];
    }

    /**
     * Calcular mora para cuotas vencidas
     */
    public function calcularMora(int $idCuenta, float $tasaMora, $fechaCalculo = null): array
    {
        $fechaCalculo = $fechaCalculo ?? date('Y-m-d');

        $cuotasVencidas = $this->db->getAllResults(
            "SELECT id, fecven, capital, cappag, interes, intpag, mora
            FROM cc_ppg
            WHERE id_cuenta = ? AND fecven < ? 
                AND ((capital - cappag) > 0.01 OR (interes - intpag) > 0.01)
            ORDER BY fecven ASC",
            [$idCuenta, $fechaCalculo]
        );

        $cuotasActualizadas = [];

        foreach ($cuotasVencidas as $cuota) {
            $saldoPendiente = ($cuota['capital'] - $cuota['cappag']) + ($cuota['interes'] - $cuota['intpag']);
            $diasVencidos = (strtotime($fechaCalculo) - strtotime($cuota['fecven'])) / 86400;

            if ($diasVencidos > 0 && $saldoPendiente > 0) {
                $moraCalculada = ($saldoPendiente * $tasaMora / 100 / 365) * $diasVencidos;

                $this->db->update(
                    'cc_ppg',
                    [
                        'mora' => $moraCalculada,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id = ?',
                    [$cuota['id']]
                );

                $cuotasActualizadas[] = [
                    'id_cuota' => $cuota['id'],
                    'dias_vencidos' => $diasVencidos,
                    'mora_calculada' => $moraCalculada
                ];
            }
        }

        return $cuotasActualizadas;
    }

    /**
     * Obtener siguiente número de cuota
     */
    public function getSiguienteNumeroCuota(int $idCuenta): int
    {
        $resultado = $this->db->getSingleResult(
            "SELECT MAX(nocuota) as maxcuota FROM cc_ppg WHERE id_cuenta = ?",
            [$idCuenta]
        );

        return ((int)($resultado['maxcuota'] ?? 0)) + 1;
    }

    public function updatePlanPago(int $idCuenta, $fechaCalculo): bool
    {

        $showmensaje = false;

        try {

            $kardexModel = new Kardex($this->db);
            $totalPagado = $kardexModel->getSumKpPagado($idCuenta, $fechaCalculo);
            $totalInteresPagado = $kardexModel->getSumInteresPagado($idCuenta, $fechaCalculo);
            $otrosPagados = $kardexModel->getOtrosPagados($idCuenta, $fechaCalculo);

            $planPagoOriginal = $this->getPlanPago($idCuenta, 'original');

            $this->db->update('cc_ppg', ['status' => 'X'], 'id_cuenta = ?', [$idCuenta]);

            /**
             * PRIMERO: ACTUALIZAR LAS CUOTAS DEL PLAN DE PAGOS ORIGINALES
             */

            foreach ($planPagoOriginal as $cuota) {
                $idCuota = $cuota['id'];

                // Aplicar montos ya pagados repartiendo entre cuotas: primero capital (cappag), luego interés (intpag)
                $abonoCapital = min($cuota['capital'], $totalPagado);
                $cappagPendiente = $cuota['capital'] - $abonoCapital;
                $totalPagado -= $abonoCapital;

                $abonoInteres = min($cuota['interes'], $totalInteresPagado);
                $intpagPendiente = $cuota['interes'] - $abonoInteres;
                $totalInteresPagado -= $abonoInteres;

                // Asegurar no negativos por seguridad
                $cappagPendiente = max(0.0, $cappagPendiente);
                $intpagPendiente = max(0.0, $intpagPendiente);

                $datosActualizar = [
                    'status' => ($cappagPendiente <= 0 && $intpagPendiente <= 0) ? 'P' : 'X',
                    'cappag' => $cappagPendiente,
                    'intpag' => $intpagPendiente,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $this->db->update('cc_ppg', $datosActualizar, 'id = ?', [$idCuota]);
            }

            /**
             * SEGUNDO: ACTUALIZAR LAS CUOTAS DEL PLAN DE PAGOS OTROS CARGOS
             */

            foreach ($otrosPagados as $key => $mov) {
                $planPagoOtros = $this->getPlanPago($idCuenta, 'otros', $mov['id_movimiento']);
                $totalOtrosPagado = $mov['total'];
                foreach ($planPagoOtros as $cuota) {
                    $idCuota = $cuota['id'];

                    Log::info("cuota otros: " . print_r($cuota, true));


                    // Aplicar montos ya pagados repartiendo entre cuotas: primero capital (cappag)
                    $abonoCapital = min($cuota['capital'], $totalOtrosPagado);
                    $cappagPendiente = $cuota['capital'] - $abonoCapital;
                    $totalOtrosPagado -= $abonoCapital;

                    // Asegurar no negativos por seguridad
                    $cappagPendiente = max(0.0, $cappagPendiente);

                    $datosActualizar = [
                        'status' => ($cappagPendiente <= 0) ? 'P' : 'X',
                        'cappag' => $cappagPendiente,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];

                    $this->db->update('cc_ppg', $datosActualizar, 'id = ?', [$idCuota]);
                }
            }

            $this->verifyStatus($idCuenta);

            return true;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function getPlanPago(int $idCuenta, $tipo = null, $idMovimiento = null): array
    {
        $showmensaje = false;
        try {
            $where = 'id_cuenta = ?';
            $params = [$idCuenta];

            if ($tipo !== null) {
                $where .= ' AND tipo = ?';
                $params[] = $tipo;
            }

            if ($idMovimiento !== null) {
                $where .= ' AND id_tipomov = ?';
                $params[] = $idMovimiento;
            }


            return $this->db->selectColumns(
                'cc_ppg',
                ['id', 'tipo', 'fecven', 'nocuota', 'capital', 'interes', 'mora', 'id_tipomov'],
                $where,
                $params,
                'fecven ASC, nocuota ASC'
            );
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }


    public function verifyStatus(int $idCuenta): bool
    {
        $resultado = $this->db->getSingleResult("SELECT COUNT(*) as pendientes FROM cc_ppg WHERE id_cuenta = ? AND `status` = 'X'", [$idCuenta]);

        if (($resultado['pendientes'] ?? 0) === 0) {
            // Actualizar estado de la cuenta a 'CANCELADA' si no hay cuotas pendientes
            $this->db->update('cc_cuentas', ['estado' => 'CANCELADA', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$idCuenta]);
        }

        return ((int)($resultado['pendientes'] ?? 0)) > 0;
    }

    public function reestructuraPlanPago(int $idCuenta, $fecha): bool
    {
        $showmensaje = false;
        try {

            $datosCuenta = $this->db->getSingleResult("SELECT fecha_fin, tasa_interes,
             IFNULL((SELECT SUM(kp) FROM cc_kardex WHERE id_cuenta=cue.id AND tipo='D' AND estado='1' AND fecha <= ? GROUP BY id_cuenta), 0) AS financiado
             FROM cc_cuentas cue WHERE id = ?", [$fecha, $idCuenta]);

            $pagados = $this->db->getAllResults(
                "SELECT 
                    IFNULL(SUM(kp), 0) AS total_capital_pagado,
                    IFNULL(SUM(interes), 0) AS total_interes_pagado
                FROM cc_kardex
                WHERE id_cuenta = ? AND fecha <= ? AND estado = '1' AND tipo = 'I'",
                [$idCuenta, $fecha]
            );

            $totalCapitalPagado = $pagados[0]['total_capital_pagado'] ?? 0.0;
            $totalInteresPagado = $pagados[0]['total_interes_pagado'] ?? 0.0;

            $planPagoOriginal = $this->db->selectColumns('cc_ppg', ['*'], 'id_cuenta = ? AND tipo = ?', [$idCuenta, 'original']);

            $kpAcumulado = 0.0;
            $intAcumulado = 0.0;

            $noCuota = 1;
            $idsLibres = [];

            foreach ($planPagoOriginal as $cuota) {
                $idCuota = $cuota['id'];

                if ($cuota['status'] === 'P' && $cuota['fecven'] <= $fecha) {
                    $noCuota++;
                    $kpAcumulado += $cuota['capital'];
                    $intAcumulado += $cuota['interes'];
                    continue; // Saltar cuotas ya pagadas
                }
                $this->db->delete('cc_ppg', 'id = ?', [$idCuota]);
                $idsLibres[] = $idCuota;
            }

            /**
             * insertar uno para la fecha de reestructura
             */
            $cc_ppg = array(
                "id_cuenta" => $idCuenta,
                "tipo" => "original",
                "fecven" => $fecha,
                "status" => "P",
                "nocuota" => $noCuota,
                "capital" => max(0, $totalCapitalPagado - $kpAcumulado),
                "interes" => max(0, $totalInteresPagado - $intAcumulado),
                "cappag" => 0,
                "intpag" => 0,
                "mora" => 0.00,
                // "id_tipomov" => NULL,
                "updated_by" => $this->userId,
                "updated_at" => date('Y-m-d H:i:s'),
            );

            if (!empty($idsLibres)) {
                $idInsert = array_shift($idsLibres);
                $cc_ppg['id'] = $idInsert;
                $this->db->insert('cc_ppg', $cc_ppg);
            } else {
                $this->db->insert('cc_ppg', $cc_ppg);
            }


            /**
             * AGREGAR UNA CUOTA FINAL CON LA FECHA DE FINALIZACION
             */

            $cuentaCobrarModel = new CuentaCobrar($this->db, $idCuenta);
            $interesAPagar = $cuentaCobrarModel->getInteresAPagar($datosCuenta['fecha_fin']);

            $cc_ppg_final = array(
                "id_cuenta" => $idCuenta,
                "tipo" => "original",
                "fecven" => $datosCuenta['fecha_fin'],
                "status" => "X",
                "nocuota" => $noCuota + 1,
                "capital" => max(0, $datosCuenta['financiado'] - $totalCapitalPagado),
                "interes" => $interesAPagar,
                "cappag" => max(0, $datosCuenta['financiado'] - $totalCapitalPagado),
                "intpag" => $interesAPagar,
                "mora" => 0.00,
                // "id_tipomov" => NULL,
                "updated_by" => $this->userId,
                "updated_at" => date('Y-m-d H:i:s'),
            );

            if (!empty($idsLibres)) {
                $idInsert = array_shift($idsLibres);
                $cc_ppg_final['id'] = $idInsert;
                $this->db->insert('cc_ppg', $cc_ppg_final);
            } else {
                $this->db->insert('cc_ppg', $cc_ppg_final);
            }

            return true;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }
}
