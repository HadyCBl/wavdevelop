<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;
use Micro\Generic\Auth;

class Ahommov
{
    protected $table = 'ahommov';
    protected $primaryKey = 'id_mov';

    private DatabaseAdapter $db;
    private $userId;
    private $agencyId;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
        $this->userId = Auth::getUserId();
        $this->agencyId = Auth::get('id_agencia');
    }

    public function applyDeposito($datos)
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

            $ahomctaModel = new \Micro\Models\Ahomcta($this->db);
            $nlibreta = $ahomctaModel->getCurrentLibretaNumber($datos['cuenta_id']);

            $datos['monto'] = $datos['monto'] ?? 0.00;

            $ahommov = array(
                "ccodaho" => $datos['cuenta_id'],
                "dfecope" => $datos['fecha'],
                "ctipope" => "D",
                "cnumdoc" => $datos['numdoc'],
                "ctipdoc" => $datos['forma_pago'],
                "crazon" => $datos['razon'] ?? '',
                "concepto" => $datos['concepto'] ?? '',
                "nlibreta" => $nlibreta,
                "nrochq" => '0',
                "tipchq" => "0",
                "dfeccomp" => "0000-00-00",
                "monto" => $datos['monto'],
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => date('Y-m-d H:i:s'),
                "codusu" => $this->userId,
                "cestado" => 1,
                "auxi" => '',
                "created_at" => date('Y-m-d H:i:s'),
                "created_by" => $this->userId,
            );
            $id_movimiento = $this->db->insert('ahommov', $ahommov);

            $idCuentaContable = $ahomctaModel->getAccountContable($datos['cuenta_id']);

            if ($datos['forma_pago'] == 'banco' || $datos['forma_pago'] == 'efectivo') {
                /**
                 * Movimientos contables solo aplica si no es pago desde otros modulos
                 */

                // $camp_numcom = getnumcompdo($this->userId, $this->db);

                // $ctb_diario = array(
                //     "numcom" => $camp_numcom,
                //     "id_ctb_tipopoliza" => 15,
                //     "id_tb_moneda" => 1,
                //     "numdoc" => ($datos['forma_pago'] == 'banco') ? $datos['num_boleta'] : $datos['numdoc'],
                //     "glosa" => $datos['observaciones'],
                //     "fecdoc" => $datos['fecha'],
                //     "feccnt" => $datos['fecha'],
                //     "cod_aux" => "cc_cuenta_" . $datos['cuenta_id'],
                //     "id_tb_usu" => $this->userId,
                //     "karely" => "CC_" . $id_movimiento,
                //     "id_agencia" => $this->agencyId,
                //     "fecmod" => date('Y-m-d H:i:s'),
                //     "estado" => 1,
                //     "editable" => 0,
                //     "created_by" => $this->userId
                // );

                // $idDiario = $this->db->insert("ctb_diario", $ctb_diario);

                // if ($datos['monto_capital'] > 0) {
                //     $ctb_mov = array(
                //         "id_ctb_diario" => $idDiario,
                //         "id_fuente_fondo" => 1,
                //         "id_ctb_nomenclatura" => $idCuentaFinanciamiento,
                //         "debe" => 0.00,
                //         "haber" => $datos['monto_capital'],
                //     );

                //     $this->db->insert("ctb_mov", $ctb_mov);
                // }

                // if ($datos['monto_interes'] > 0) {
                //     $ctb_mov = array(
                //         "id_ctb_diario" => $idDiario,
                //         "id_fuente_fondo" => 1,
                //         "id_ctb_nomenclatura" => $cuentasIntMora['interes'],
                //         "debe" => 0.00,
                //         "haber" => $datos['monto_interes'],
                //     );

                //     $this->db->insert("ctb_mov", $ctb_mov);
                // }

                // if ($datos['monto_mora'] > 0) {
                //     $ctb_mov = array(
                //         "id_ctb_diario" => $idDiario,
                //         "id_fuente_fondo" => 1,
                //         "id_ctb_nomenclatura" => $cuentasIntMora['mora'],
                //         "debe" => 0.00,
                //         "haber" => $datos['monto_mora'],
                //     );

                //     $this->db->insert("ctb_mov", $ctb_mov);
                // }

                // $ctb_mov = array(
                //     "id_ctb_diario" => $idDiario,
                //     "id_fuente_fondo" => 1,
                //     "id_ctb_nomenclatura" => $idNomenclaturaCaja,
                //     "debe" => $datos['monto_capital'] + $datos['monto_interes'] + $datos['monto_mora'],
                //     "haber" => 0.00
                // );

                // $this->db->insert("ctb_mov", $ctb_mov);
            }


            /**
             * ORDENAMIENTO DE TRANSACCIONES
             */
            $this->db->executeQuery('CALL ahom_ordena_noLibreta(?,?);', [$nlibreta, $datos['cuenta_id']]);
            $this->db->executeQuery('CALL ahom_ordena_Transacciones(?);', [$datos['cuenta_id']]);

            return [
                'id_movimiento' => $id_movimiento,
                'id_diario' => $idDiario ?? null,
                'id_cuenta_contable' => $idCuentaContable ?? null,
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

    public function getNextCuo($ccodcta)
    {
        $result = $this->db->selectColumns($this->table, ['IFNULL(MAX(CNROCUO), 0) as max_cuo'], 'CCODCTA=?', [$ccodcta]);
        return $result[0]['max_cuo'] + 1;
    }
}
