<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use Micro\Helpers\Beneq;
use Micro\Helpers\Log;
use Micro\Generic\Auth;
use Exception;

class Kardex
{
    private DatabaseAdapter $db;
    private $userId;
    private $agencyId;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
        $this->userId = Auth::getUserId();
        $this->agencyId = Auth::get('id_agencia');
    }

    /**
     * Registrar desembolso
     */
    public function registrarDesembolso(array $datos): int
    {
        $required = ['id_cuenta', 'total', 'fecha', 'forma_pago'];
        foreach ($required as $field) {
            if (!isset($datos[$field])) {
                throw new Exception("El campo {$field} es requerido para registrar desembolso.");
            }
        }

        $kardex = [
            'id_cuenta' => $datos['id_cuenta'],
            'total' => $datos['total'],
            'kp' => $datos['total'], // En desembolso, KP = total
            'fecha' => $datos['fecha'],
            'tipo' => 'D',
            'numdoc' => $datos['numdoc'] ?? '',
            'forma_pago' => $datos['forma_pago'],
            'concepto' => $datos['concepto'] ?? '',
            'id_ctbbancos' => $datos['id_ctbbancos'] ?? null,
            'doc_banco' => $datos['doc_banco'] ?? null,
            'estado' => '1',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $datos['created_by'] ?? null
        ];

        return $this->db->insert('cc_kardex', $kardex);
    }

    /**
     * Registrar entrega (otros cargos)
     */
    public function registrarEntrega(array $datos): int
    {
        $required = ['id_cuenta', 'total', 'fecha', 'id_movimiento'];
        foreach ($required as $field) {
            if (!isset($datos[$field])) {
                throw new Exception("El campo {$field} es requerido para registrar entrega.");
            }
        }

        $kardex = [
            'id_cuenta' => $datos['id_cuenta'],
            'total' => $datos['total'],
            'fecha' => $datos['fecha'],
            'tipo' => 'E',
            'numdoc' => $datos['numdoc'] ?? '',
            'forma_pago' => $datos['forma_pago'] ?? '',
            'concepto' => $datos['concepto'] ?? '',
            'id_ctbbancos' => $datos['id_ctbbancos'] ?? null,
            'doc_banco' => $datos['doc_banco'] ?? null,
            'estado' => '1',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $datos['created_by'] ?? null
        ];

        $idKardex = $this->db->insert('cc_kardex', $kardex);

        // Registrar detalle
        $detalle = [
            'id_kardex' => $idKardex,
            'id_movimiento' => $datos['id_movimiento'],
            'monto' => $datos['total']
        ];
        $this->db->insert('cc_kardex_detalle', $detalle);

        return $idKardex;
    }

    /**
     * Registrar pago/ingreso
     */
    public function registrarPago(array $datos): array
    {
        $showmensaje = false;
        try {

            $datosAgencia = $this->db->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$this->agencyId]);
            if (empty($datosAgencia)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información de la agencia.");
            }
            $idNomenclaturaCaja = $datosAgencia[0]['id_nomenclatura_caja'];

            if ($datos['forma_pago'] == 'banco') {
                $datosBanco = $this->db->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$datos['banco_id']]);
                if (empty($datosBanco)) {
                    $showmensaje = true;
                    throw new Exception("No se pudo obtener la información del banco seleccionado.");
                }
                $idNomenclaturaCaja = $datosBanco[0]['id_nomenclatura'];
            }

            $datos['monto_capital'] = $datos['monto_capital'] ?? 0.00;
            $datos['monto_interes'] = $datos['monto_interes'] ?? 0.00;
            $datos['monto_mora'] = $datos['monto_mora'] ?? 0.00;
            $datos['monto_otros'] = (isset($datos['otros_entregas']) && is_array($datos['otros_entregas'])) ? array_sum(array_column($datos['otros_entregas'], 'monto')) : 0.00;

            $cc_kardex = [
                'id_cuenta' => $datos['cuenta_id'],
                'total' => $datos['monto_capital'] + $datos['monto_interes'] + $datos['monto_mora'] + $datos['monto_otros'],
                'kp' => $datos['monto_capital'],
                'interes' => $datos['monto_interes'],
                'mora' => $datos['monto_mora'],
                'fecha' => $datos['fecha'],
                'tipo' => 'I',
                'numdoc' => $datos['numdoc'],
                'forma_pago' => $datos['forma_pago'],
                'concepto' => $datos['concepto'],
                'id_ctbbancos' => ($datos['forma_pago'] == 'banco') ? $datos['banco_id'] : null,
                'doc_banco' => ($datos['forma_pago'] == 'banco') ? $datos['num_boleta'] : null,
                'estado' => '1',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->userId,
            ];
            $id_movimiento = $this->db->insert("cc_kardex", $cc_kardex);

            $nomenclaturas_otros = [];

            if (isset($datos['otros_entregas']) && is_array($datos['otros_entregas'])) {
                foreach ($datos['otros_entregas'] as $otro) {
                    if (!isset($otro['id_movimiento']) || !isset($otro['monto']) || $otro['monto'] <= 0) {
                        continue;
                    }
                    $detalle = [
                        'id_kardex' => $id_movimiento,
                        'id_movimiento' => $otro['id_movimiento'],
                        'monto' => $otro['monto']
                    ];
                    $this->db->insert('cc_kardex_detalle', $detalle);

                    $nomenclatura = $this->db->selectColumns('cc_tipos_movimientos', ['id_nomenclatura'], 'id=?', [$otro['id_movimiento']]);
                    if (!empty($nomenclatura)) {
                        $nomenclaturas_otros[] = [
                            'id_movimiento' => $otro['id_movimiento'],
                            'id_nomenclatura' => $nomenclatura[0]['id_nomenclatura']
                        ];
                    }
                }
            }

            $cuentaCobrarModel = new \Micro\Models\CuentaCobrar($this->db, $datos['cuenta_id']);
            if ($datos['monto_capital'] > 0) {
                $idCuentaFinanciamiento = $cuentaCobrarModel->getAccountContableFinanciamiento(); //getAccountContableCapital
            }

            if ($datos['monto_interes'] > 0 || $datos['monto_mora'] > 0) {
                $cuentasIntMora = $cuentaCobrarModel->getAccountContableInteresMora();
            }

            if ($datos['forma_pago'] == 'banco' || $datos['forma_pago'] == 'efectivo') {
                /**
                 * Movimientos contables
                 */

                // $camp_numcom = getnumcompdo($this->userId, $this->db);
                $camp_numcom = Beneq::getNumcom($this->db, $this->userId, $this->agencyId, $datos['fecha']);

                $ctb_diario = array(
                    "numcom" => $camp_numcom,
                    "id_ctb_tipopoliza" => 15,
                    "id_tb_moneda" => 1,
                    "numdoc" => ($datos['forma_pago'] == 'banco') ? $datos['num_boleta'] : $datos['numdoc'],
                    "glosa" => $datos['observaciones'],
                    "fecdoc" => $datos['fecha'],
                    "feccnt" => $datos['fecha'],
                    "cod_aux" => "cc_cuenta_" . $datos['cuenta_id'],
                    "id_tb_usu" => $this->userId,
                    "karely" => "CC_" . $id_movimiento,
                    "id_agencia" => $this->agencyId,
                    "fecmod" => date('Y-m-d H:i:s'),
                    "estado" => 1,
                    "editable" => 0,
                    "created_by" => $this->userId
                );

                $idDiario = $this->db->insert("ctb_diario", $ctb_diario);

                if ($datos['monto_capital'] > 0) {
                    $ctb_mov = array(
                        "id_ctb_diario" => $idDiario,
                        "id_fuente_fondo" => 1,
                        "id_ctb_nomenclatura" => $idCuentaFinanciamiento,
                        "debe" => 0.00,
                        "haber" => $datos['monto_capital'],
                    );

                    $this->db->insert("ctb_mov", $ctb_mov);
                }

                if ($datos['monto_interes'] > 0) {
                    $ctb_mov = array(
                        "id_ctb_diario" => $idDiario,
                        "id_fuente_fondo" => 1,
                        "id_ctb_nomenclatura" => $cuentasIntMora['interes'],
                        "debe" => 0.00,
                        "haber" => $datos['monto_interes'],
                    );

                    $this->db->insert("ctb_mov", $ctb_mov);
                }

                if ($datos['monto_mora'] > 0) {
                    $ctb_mov = array(
                        "id_ctb_diario" => $idDiario,
                        "id_fuente_fondo" => 1,
                        "id_ctb_nomenclatura" => $cuentasIntMora['mora'],
                        "debe" => 0.00,
                        "haber" => $datos['monto_mora'],
                    );

                    $this->db->insert("ctb_mov", $ctb_mov);
                }

                $ctb_mov = array(
                    "id_ctb_diario" => $idDiario,
                    "id_fuente_fondo" => 1,
                    "id_ctb_nomenclatura" => $idNomenclaturaCaja,
                    "debe" => $datos['monto_capital'] + $datos['monto_interes'] + $datos['monto_mora'],
                    "haber" => 0.00
                );

                $this->db->insert("ctb_mov", $ctb_mov);
            }


            /**
             * ACTUALIZACION DE PLAN DE PAGOS
             */
            $planPagosModel = new \Micro\Models\PlanPagos($this->db);
            $planPagosModel->updatePlanPago($datos['cuenta_id'], $datos['fecha']);


            return [
                'id_kardex' => $id_movimiento,
                'id_diario' => $idDiario ?? null,
                'id_cuenta_financiamiento' => $idCuentaFinanciamiento ?? null,
                'id_cuenta_interes' => $cuentasIntMora['interes'] ?? null,
                'id_cuenta_mora' => $cuentasIntMora['mora'] ?? null,
                'nomenclaturas_otros' => $nomenclaturas_otros ?? [],
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
     * Obtener movimiento por ID
     */
    public function getMovimiento(int $idKardex): ?array
    {
        return $this->db->getSingleResult(
            "SELECT * FROM cc_kardex WHERE id = ?",
            [$idKardex]
        );
    }

    /**
     * Anular movimiento
     */
    public function anularMovimiento(int $idKardex, int $userId): bool
    {
        return $this->db->update(
            'cc_kardex',
            [
                'estado' => '0',
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $userId
            ],
            'id = ?',
            [$idKardex]
        );
    }

    public function getSumKpPagado(int $idCuenta, $fechaCorte = null): float
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');

        $resultado = $this->db->getSingleResult(
            "SELECT SUM(kp) as total_kp FROM cc_kardex WHERE id_cuenta = ? AND tipo = 'I' AND estado = '1' AND fecha <= ?",
            [$idCuenta, $fechaCorte]
        );

        return (float)($resultado['total_kp'] ?? 0.00);
    }

    public function getSumInteresPagado(int $idCuenta, $fechaCorte = null): float
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');

        $resultado = $this->db->getSingleResult(
            "SELECT SUM(interes) as total_interes FROM cc_kardex WHERE id_cuenta = ? AND tipo = 'I' AND estado = '1' AND fecha <= ?",
            [$idCuenta, $fechaCorte]
        );

        return (float)($resultado['total_interes'] ?? 0.00);
    }

    public function getOtrosPagados(int $idCuenta, $fechaCorte = null): array
    {
        $fechaCorte = $fechaCorte ?? date('Y-m-d');
        $showmensaje = false;
        try {
            return $this->db->getAllResults(
                "SELECT det.id_movimiento, IFNULL(SUM(det.monto), 0) as total
                    FROM cc_kardex kar
                    INNER JOIN cc_kardex_detalle det ON det.id_kardex = kar.id
                    WHERE kar.estado = '1' AND kar.tipo = 'I' AND kar.id_cuenta = ?
                    GROUP BY det.id_movimiento",
                [$idCuenta]
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

    public function getDataLastPaymentInteres(int $idCuenta): ?array
    {
        return $this->db->getSingleResult(
            "SELECT fecha, interes
            FROM cc_kardex
            INNER JOIN cc_cuentas ON cc_cuentas.id = cc_kardex.id_cuenta
            WHERE id_cuenta = ? AND tipo = 'I' AND estado = '1' AND interes > 0
            ORDER BY fecha DESC, id DESC
            LIMIT 1",
            [$idCuenta]
        );
    }
}
