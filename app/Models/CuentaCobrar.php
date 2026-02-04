<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class CuentaCobrar
{
    private DatabaseAdapter $db;
    private int $idCuenta;
    private ?array $datoCuenta = null;
    private array $cache = [];

    public function __construct(DatabaseAdapter $db, int $idCuenta)
    {
        $this->db = $db;
        $this->idCuenta = $idCuenta;
    }

    /**
     * Obtener datos completos de la cuenta
     */
    public function getDatosCuenta(): array
    {
        if ($this->datoCuenta !== null) {
            return $this->datoCuenta;
        }

        $this->datoCuenta = $this->db->getSingleResult(
            "SELECT cue.*, cli.short_name, cli.no_identifica, per.nombre as periodo_nombre
            FROM cc_cuentas cue
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cue.id_cliente
            LEFT JOIN cc_periodos per ON per.id = cue.id_periodo
            WHERE cue.id = ?",
            [$this->idCuenta]
        );

        if (empty($this->datoCuenta)) {
            throw new Exception("La cuenta {$this->idCuenta} no existe.");
        }

        return $this->datoCuenta;
    }

    /**
     * Calcular saldo total de financiamientos otorgados hasta una fecha
     */
    public function getSaldoFinanciamientos($fechaCorte = null): float
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');
        $cacheKey = "financ_{$fechaCorte}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Total financiado (desembolsado)
        $financiado = $this->db->getSingleResult(
            "SELECT IFNULL(SUM(kp), 0) as total FROM cc_kardex
            WHERE estado = '1' AND tipo = 'D' AND id_cuenta = ? AND fecha <= ?",
            [$this->idCuenta, $fechaCorte]
        );

        // Total recuperado
        $recuperado = $this->db->getSingleResult(
            "SELECT IFNULL(SUM(kp), 0) as total FROM cc_kardex
            WHERE estado = '1' AND tipo = 'I' AND id_cuenta = ? AND fecha <= ?",
            [$this->idCuenta, $fechaCorte]
        );

        $saldo = ($financiado['total'] ?? 0) - ($recuperado['total'] ?? 0);
        $this->cache[$cacheKey] = $saldo;

        return $saldo;
    }

    public function getSaldoInteres($fechaCorte = null): float
    {
        $saldoActual = $this->getSaldoFinanciamientos($fechaCorte);

        $tasaInteres = $this->getTasaInteres();

        $kardexModel = new Kardex($this->db);

        $ultimoPagoInteres = $kardexModel->getDataLastPaymentInteres($this->idCuenta);

        $fechaUltimoPago = $ultimoPagoInteres['fecha'] ?? null;

        $saldoInteres = $saldoActual * ($tasaInteres / 100);

        return $saldoInteres;
    }

    public function getInteresAPagar($fechaCorte = null): float
    {
        /**
         * CONSIDERAR QUE CUANDO SE REALICEN ABONOS A CAPITAL ANTES DE LA FECHA DE VENCIMIENTO, HACER UNA MINI REESTRUCTURACIÓN
         * PARA QUE EL INTERÉS SE CALCULE CORRECTAMENTE HASTA LA FECHA DE CORTE
         */

        $showmensaje = false;
        try {

            $movimientos = $this->db->getAllResults(
                "SELECT kp, fecha,interes,tipo FROM cc_kardex
                WHERE estado = '1' AND id_cuenta = ? AND fecha <= ? ORDER BY fecha ASC, id ASC",
                [$this->idCuenta, $fechaCorte ?? date('Y-m-d')]
            );

            $tasaInteres = $this->getTasaInteres();

            /**
             * CALCULAR INTERES POR CADA DESEMBOLSO, A LA FECHA DE HOY O FECHA DE CORTE
             */

            // $totalKpPagado = array_sum(array_column($pagados, 'kp'));
            $totalInteresPagado = array_sum(array_column($movimientos, 'interes'));
            $currentSaldoKp = 0.00;
            $saldoAnterior = 0.00;
            $totalInteresGenerado = 0.00;

            $fechaAnt = null;

            foreach ($movimientos as $key => $mov) {
                $fecha = $mov['fecha'];
                $montoKp = $mov['kp'];

                $saldoAnterior = $currentSaldoKp;
                if ($mov['tipo'] == 'D') {
                    // Es un desembolso
                    $currentSaldoKp += $montoKp;
                } elseif ($mov['tipo'] == 'I') {
                    $currentSaldoKp -= $montoKp;
                }

                if ($key == 0) {
                    $fechaAnt = $fecha;
                    continue;
                }

                // Calcular interés del periodo anterior
                $diasTranscurridos = (new \DateTime($fecha))->diff(new \DateTime($fechaAnt))->days;
                $interesGenerado = $saldoAnterior * ($tasaInteres / 100 / 365) * $diasTranscurridos;
                $totalInteresGenerado += $interesGenerado;

                $fechaAnt = $fecha;
                // Log::info("Cálculo interés: Desembolso {$saldoAnterior} el {$fechaAnt}, días: {$diasTranscurridos}, tasa: {$tasaInteres}% => Interés: {$interesGenerado}, tipo: {$mov['tipo']}, kp: {$mov['kp']}");
            }

            if($fechaAnt !== null && $fechaAnt < ($fechaCorte ?? date('Y-m-d'))){
                // Calcular interés hasta la fecha de corte o fecha de hoy
                $fechaFinCalculo = $fechaCorte ?? date('Y-m-d');

                $diasTranscurridos = (new \DateTime($fechaFinCalculo))->diff(new \DateTime($fechaAnt))->days;
                $interesGenerado = $currentSaldoKp * ($tasaInteres / 100 / 365) * $diasTranscurridos;
                $totalInteresGenerado += $interesGenerado;

                // Log::info("Cálculo interés: corte {$currentSaldoKp} el {$fechaAnt}, días: {$diasTranscurridos}, tasa: {$tasaInteres}% => Interés: {$interesGenerado}, tipo: N/A, kp: 0.00");
            }

            // $interesesPagados = array_sum(array_column($pagados, 'interes'));

            $saldoInteresPendiente = $totalInteresGenerado - $totalInteresPagado;
            $saldoInteresPendiente = max(0, $saldoInteresPendiente); // Evitar saldos negativos

            return $saldoInteresPendiente;
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
    public function getInteresAPagarant($fechaCorte = null): float
    {
        /**
         * CONSIDERAR QUE CUANDO SE REALICEN ABONOS A CAPITAL ANTES DE LA FECHA DE VENCIMIENTO, HACER UNA MINI REESTRUCTURACIÓN
         * PARA QUE EL INTERÉS SE CALCULE CORRECTAMENTE HASTA LA FECHA DE CORTE
         */

        $showmensaje = false;
        try {

            $financiamientos = $this->db->getAllResults(
                "SELECT kp, fecha FROM cc_kardex
                WHERE estado = '1' AND tipo = 'D' AND id_cuenta = ? AND fecha <= ?",
                [$this->idCuenta, $fechaCorte ?? date('Y-m-d')]
            );

            $pagados = $this->db->getAllResults(
                "SELECT kp, fecha,interes FROM cc_kardex
                WHERE estado = '1' AND tipo = 'I' AND id_cuenta = ? AND fecha <= ?",
                [$this->idCuenta, $fechaCorte ?? date('Y-m-d')]
            );

            $tasaInteres = $this->getTasaInteres();

            /**
             * CALCULAR INTERES POR CADA DESEMBOLSO, A LA FECHA DE HOY O FECHA DE CORTE
             */

            $totalKpPagado = array_sum(array_column($pagados, 'kp'));
            $totalInteresGenerado = 0.00;

            foreach ($financiamientos as $desembolso) {
                $fechaDesembolso = $desembolso['fecha'];
                $montoDesembolso = $desembolso['kp'];


                /**
                 * ANALIZAR MUY BIEN ESTA PARTE, APLICA CUANDO SE HAYA REALIZADO PAGOS A CAPITAL ANTES DE LA FECHA DE CORTE
                 */
                if ($totalKpPagado >= $montoDesembolso) {
                    // Este desembolso ya fue pagado en su totalidad
                    $totalKpPagado -= $montoDesembolso;
                    continue;
                } elseif ($totalKpPagado > 0) {
                    // Parte del desembolso ha sido pagado
                    $montoDesembolso -= $totalKpPagado;
                    $totalKpPagado = 0;
                }

                /**
                 * FIN DE LA PARTE A ANALIZAR
                 */

                // Calcular interés hasta la fecha de corte o fecha de hoy
                $fechaFinCalculo = $fechaCorte ?? date('Y-m-d');

                $diasTranscurridos = (new \DateTime($fechaFinCalculo))->diff(new \DateTime($fechaDesembolso))->days;


                $interesGenerado = $montoDesembolso * ($tasaInteres / 100 / 365) * $diasTranscurridos;

                // Log::info("Cálculo interés: Desembolso {$montoDesembolso} el {$fechaDesembolso}, días: {$diasTranscurridos}, tasa: {$tasaInteres}% => Interés: {$interesGenerado}");

                $totalInteresGenerado += $interesGenerado;
            }

            $interesesPagados = array_sum(array_column($pagados, 'interes'));

            $saldoInteresPendiente = $totalInteresGenerado - $interesesPagados;
            $saldoInteresPendiente = max(0, $saldoInteresPendiente); // Evitar saldos negativos

            return $saldoInteresPendiente;
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

    public function getTasaInteres(): float
    {
        $datosCuenta = $this->db->getSingleResult(
            "SELECT tasa_interes FROM cc_cuentas WHERE id = ?",
            [$this->idCuenta]
        );
        return (float)($datosCuenta['tasa_interes'] ?? 0);
    }

    /**
     * Calcular saldo de otros cargos (entregas) por tipo de movimiento
     */
    public function getSaldoOtrosCargos($idTipoMovimiento = null, $fechaCorte = null): array
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');
        $cacheKey = "otros_" . ($idTipoMovimiento ?? 'all') . "_{$fechaCorte}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $whereTipo = $idTipoMovimiento ? "AND det.id_movimiento = ?" : "";
        $params = $idTipoMovimiento ? [$this->idCuenta, $fechaCorte, $idTipoMovimiento] : [$this->idCuenta, $fechaCorte];

        // Otros cargos entregados (tipo E)
        $otorgados = $this->db->getAllResults(
            "SELECT det.id_movimiento, tm.nombre as tipo_nombre, IFNULL(SUM(det.monto), 0) as total
            FROM cc_kardex kar
            INNER JOIN cc_kardex_detalle det ON det.id_kardex = kar.id
            INNER JOIN cc_tipos_movimientos tm ON tm.id = det.id_movimiento
            WHERE kar.estado = '1' AND kar.tipo = 'E' AND kar.id_cuenta = ? AND kar.fecha <= ? {$whereTipo}
            GROUP BY det.id_movimiento, tm.nombre",
            $params
        );

        // Otros cargos recuperados (tipo I con detalle)
        $recuperados = $this->db->getAllResults(
            "SELECT det.id_movimiento, IFNULL(SUM(det.monto), 0) as total
            FROM cc_kardex kar
            INNER JOIN cc_kardex_detalle det ON det.id_kardex = kar.id
            WHERE kar.estado = '1' AND kar.tipo = 'I' AND kar.id_cuenta = ? AND kar.fecha <= ? {$whereTipo}
            GROUP BY det.id_movimiento",
            $params
        );

        // Crear array asociativo para recuperados
        $mapRecuperados = [];
        foreach ($recuperados as $rec) {
            $mapRecuperados[$rec['id_movimiento']] = $rec['total'];
        }

        // Calcular saldos
        $resultado = [];
        foreach ($otorgados as $otorg) {
            $idMov = $otorg['id_movimiento'];
            $recuperado = $mapRecuperados[$idMov] ?? 0;
            $resultado[] = [
                'id_movimiento' => $idMov,
                'tipo_nombre' => $otorg['tipo_nombre'],
                'otorgado' => (float)$otorg['total'],
                'recuperado' => (float)$recuperado,
                'saldo' => (float)$otorg['total'] - (float)$recuperado
            ];
        }

        $this->cache[$cacheKey] = $resultado;
        return $resultado;
    }

    /**
     * Calcular saldo total general (financiamientos + otros cargos)
     */
    public function getSaldoTotal($fechaCorte = null): array
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');
        $cacheKey = "total_{$fechaCorte}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $saldoFinan = $this->getSaldoFinanciamientos($fechaCorte);
        $otrosCargos = $this->getSaldoOtrosCargos(null, $fechaCorte);

        $totalOtros = array_sum(array_column($otrosCargos, 'saldo'));

        $resultado = [
            'financiamientos' => $saldoFinan,
            'otros_cargos' => $totalOtros,
            'total_general' => $saldoFinan + $totalOtros,
            'detalle_otros' => $otrosCargos,
            'fecha_corte' => $fechaCorte
        ];

        $this->cache[$cacheKey] = $resultado;
        return $resultado;
    }

    /**
     * Calcular intereses pendientes según plan de pagos
     */
    public function getInteresesPendientes($fechaCorte = null): array
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');

        $resultado = $this->db->getAllResults(
            "SELECT 
                SUM(interes) as interes_total,
                SUM(intpag) as interes_pagado,
                SUM(interes - intpag) as interes_pendiente,
                SUM(mora) as mora_total
            FROM cc_ppg
            WHERE id_cuenta = ? AND fecven <= ?",
            [$this->idCuenta, $fechaCorte]
        );

        return [
            'interes_total' => (float)($resultado[0]['interes_total'] ?? 0),
            'interes_pagado' => (float)($resultado[0]['interes_pagado'] ?? 0),
            'interes_pendiente' => (float)($resultado[0]['interes_pendiente'] ?? 0),
            'mora_total' => (float)($resultado[0]['mora_total'] ?? 0),
            'fecha_corte' => $fechaCorte
        ];
    }

    /**
     * Obtener resumen completo de la cuenta
     */
    public function getResumenCuenta($fechaCorte = null): array
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');

        $datos = $this->getDatosCuenta();
        $saldos = $this->getSaldoTotal($fechaCorte);
        $intereses = $this->getInteresesPendientes($fechaCorte);

        return [
            'cuenta' => [
                'id' => $this->idCuenta,
                'cliente' => $datos['short_name'] ?? '',
                'identificacion' => $datos['no_identifica'] ?? '',
                'periodo' => $datos['periodo_nombre'] ?? '',
                'monto_inicial' => (float)($datos['monto_inicial'] ?? 0),
                'tasa_interes' => (float)($datos['tasa_interes'] ?? 0),
                'fecha_inicio' => $datos['fecha_inicio'] ?? '',
                'fecha_fin' => $datos['fecha_fin'] ?? '',
                'estado' => $datos['estado'] ?? ''
            ],
            'saldos' => $saldos,
            'intereses' => $intereses,
            'total_adeudo' => $saldos['total_general'] + $intereses['interes_pendiente'] + $intereses['mora_total']
        ];
    }

    /**
     * Verificar si la cuenta tiene saldo pendiente
     */
    public function tieneSaldoPendiente($fechaCorte = null): bool
    {
        $saldos = $this->getSaldoTotal($fechaCorte);
        $intereses = $this->getInteresesPendientes($fechaCorte);

        $totalDeuda = $saldos['total_general'] + $intereses['interes_pendiente'] + $intereses['mora_total'];

        return $totalDeuda > 0.01; // Tolerancia de 1 centavo
    }

    /**
     * Obtener historial de movimientos
     */
    public function getHistorialMovimientos($fechaInicio = null, $fechaFin = null): array
    {
        $fechaFin = $fechaFin ?? date('Y-m-d');
        $fechaInicio = $fechaInicio ?? '1900-01-01';

        return $this->db->getAllResults(
            "SELECT 
                kar.id, kar.fecha, kar.tipo, kar.numdoc, kar.forma_pago,
                kar.total, kar.kp, kar.interes, kar.mora, kar.concepto,
                CASE 
                    WHEN kar.tipo = 'D' THEN 'Desembolso'
                    WHEN kar.tipo = 'E' THEN 'Entrega'
                    WHEN kar.tipo = 'I' THEN 'Pago/Ingreso'
                    ELSE 'Otro'
                END as tipo_nombre
            FROM cc_kardex kar
            WHERE kar.estado = '1' AND kar.id_cuenta = ? 
                AND kar.fecha BETWEEN ? AND ?
            ORDER BY kar.fecha DESC, kar.id DESC",
            [$this->idCuenta, $fechaInicio, $fechaFin]
        );
    }

    /**
     * Obtener cuenta contable para movimientos de financiamiento
     */

    public function getAccountContableFinanciamiento(): ?int
    {
        $showmensaje = false;
        try {
            /**
             * BUSCAR X ORDEN DE PRIORIDAD
             * 1. Cuenta contable definida por el grupo de la persona
             * 2. Cuenta contable definida de manera general
             */

            $result = $this->db->getSingleResult(
                "SELECT id_nomenclatura FROM cc_grupos grup
                INNER JOIN cc_grupos_clientes cli ON cli.id_grupo=grup.id AND grup.estado='1'
                INNER JOIN cc_cuentas cue ON cue.id_cliente=cli.id_cliente
                WHERE cue.id = ? AND grup.id_nomenclatura IS NOT NULL",
                [$this->idCuenta]
            );

            if (!empty($result['id_nomenclatura']) && $result['id_nomenclatura'] > 0) {
                return $result['id_nomenclatura'];
            }

            $params = $this->db->selectColumns("cc_parametros", ["id_nomenclatura"], "tipo='kp'");

            if (empty($params) || empty($params[0]['id_nomenclatura'])) {
                $showmensaje = true;
                throw new Exception("No se encontró una cuenta contable general para los movimientos de financiamientos y el titular no pertenece a ningún grupo activo.");
            }

            return $params[0]['id_nomenclatura'] ?? null;
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

    /**
     * obtener cuenta contable para otros cargos
     */
    public function getAccountContableAnothers($idTipo): ?int
    {
        $showmensaje = false;
        try {
            $params = $this->db->selectColumns("cc_tipos_movimientos", ["id_nomenclatura"], "id = ? AND id_nomenclatura IS NOT NULL", [$idTipo]);

            if (empty($params) || empty($params[0]['id_nomenclatura'])) {
                $showmensaje = true;
                throw new Exception("No se encontró una cuenta contable para este tipo de movimiento.");
            }

            return $params[0]['id_nomenclatura'] ?? null;
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

    public function getAccountContableInteresMora(): ?array
    {
        $showmensaje = false;
        try {
            $params = $this->db->selectColumns("cc_parametros", ["id_nomenclatura","tipo"], "tipo IN ('interes','mora')");

            if (empty($params)) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas contables para intereses y mora.");
            }

            $idCuentInteres = null;
            $idCuentMora = null;

            foreach ($params as $param) {
                if ($param['tipo'] === 'interes') {
                    $idCuentInteres = $param['id_nomenclatura'];
                } elseif ($param['tipo'] === 'mora') {
                    $idCuentMora = $param['id_nomenclatura'];
                }
            }

            if (is_null($idCuentInteres) || is_null($idCuentMora)) {
                $showmensaje = true;
                throw new Exception("Faltan cuentas contables para intereses o mora.");
            }

            return [
                'interes' => $idCuentInteres,
                'mora' => $idCuentMora
            ];
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

    /**
     * Limpiar cache
     */
    public function limpiarCache(): void
    {
        $this->cache = [];
        $this->datoCuenta = null;
    }

    /**
     * Getter para ID de cuenta
     */
    public function getIdCuenta(): int
    {
        return $this->idCuenta;
    }
}
