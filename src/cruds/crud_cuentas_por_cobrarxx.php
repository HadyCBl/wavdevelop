<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

/**
 * VERIFICACION DE LA SESION SIEMPRE ANTES DE HACER CUALQUIER TRANSACCION
 * se pueden excluir condis agregando en $excludes el nombre de la condi
 */
$excludes = [];
if (!in_array($_POST["condi"], $excludes) && !isset($_SESSION['id'])) {
    echo json_encode([
        'message' => 'Su sesión ha expirado, por favor inicie sesión nuevamente',
        'status' => 0
    ]);
    return;
}


$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
// require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
// require_once __DIR__ . '/../../includes/Config/database.php';
// require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';



$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");


use Micro\Helpers\Log;
use App\DatabaseAdapter;
use Micro\Generic\Validator;
use Micro\Helpers\Beneq;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Models\Ahommov;
use Micro\Models\Aprmov;
use Micro\Models\Credkar;
use Micro\Models\CuentaCobrar;
use Micro\Models\Kardex;
use Micro\Models\PlanPagos;

$database = new DatabaseAdapter();
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

/**
 * VERIFICACION DEL TOKEN CSRF
 * se pueden excluir condis agregando en $excludesCsrf el nombre de la condi
 */
$excludesCsrf = [];
if (!in_array($_POST["condi"], $excludesCsrf) && !($csrf->validateToken($_POST['inputs'][$csrf->getTokenName()] ?? '', false))) {
    $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
    $opResult = array(
        'message' => $errorcsrf,
        'status' => 0,
        "reprint" => 1,
        "timer" => 3000
    );
    echo json_encode($opResult);
    return;
}


$condi = $_POST["condi"];

switch ($condi) {
    case 'create_periodos':
        // obtiene(['<?= $csrf->getTokenName()','nombre','fecha_inicio','fecha_fin','tasa_interes','tasa_mora']
        // $decryptedID = $secureID->decrypt($encryptedID);
        $showmensaje = false;
        try {
            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'token_csrf' => $_POST['inputs'][$csrf->getTokenName()] ?? '',
                'nombre' => trim($_POST['inputs']['nombre'] ?? ''),
                'fecha_inicio' => $_POST['inputs']['fecha_inicio'] ?? null,
                'fecha_fin' => $_POST['inputs']['fecha_fin'] ?? null,
                'tasa_interes' => $_POST['inputs']['tasa_interes'] ?? 0,
                'tasa_mora' => $_POST['inputs']['tasa_mora'] ?? 0,
            ];

            $rules = [
                'token_csrf' => 'required',
                'nombre' => 'required|min:3|max:100',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date',
                'tasa_interes' => 'required|numeric|min:0',
                'tasa_mora' => 'required|numeric|min:0',
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                // Log::info("Errores de validacion create_periodos", $validator->errors());
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if ($data['fecha_fin'] < $data['fecha_inicio']) {
                $showmensaje = true;
                throw new Exception("La fecha fin no puede ser menor a la fecha inicio.");
            }

            $database->openConnection();
            $verification = $database->selectColumns('cc_periodos', ['estado'], "fecha_inicio=? AND fecha_fin=? AND estado IN ('1','2')", [$data['fecha_inicio'], $data['fecha_fin']]);

            if (!empty($verification)) {
                $statusPeriodo = ($verification[0]['estado'] == 1) ? 'activo' : 'cerrado';
                $showmensaje = true;
                throw new Exception("Ya existe un periodo $statusPeriodo en las fechas seleccionadas.");
            }

            $database->beginTransaction();

            $cc_periodos = [
                'nombre' => $data['nombre'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'tasa_interes' => $data['tasa_interes'],
                'tasa_mora' => $data['tasa_mora'],
                'estado' => '1',
                'created_at' => $hoy2,
                'created_by' => $idusuario,
            ];
            $database->insert("cc_periodos", $cc_periodos);

            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);

        break;

    case 'update_periodos':
        $showmensaje = false;
        try {
            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'token_csrf' => $_POST['inputs'][$csrf->getTokenName()] ?? '',
                'nombre' => trim($_POST['inputs']['nombre'] ?? ''),
                'fecha_inicio' => $_POST['inputs']['fecha_inicio'] ?? null,
                'fecha_fin' => $_POST['inputs']['fecha_fin'] ?? null,
                'tasa_interes' => $_POST['inputs']['tasa_interes'] ?? 0,
                'tasa_mora' => $_POST['inputs']['tasa_mora'] ?? 0,
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
            ];

            $rules = [
                'token_csrf' => 'required',
                'nombre' => 'required|min:3|max:100',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date',
                'tasa_interes' => 'required|numeric|min:0',
                'tasa_mora' => 'required|numeric|min:0',
                'id' => 'required|numeric|min:1|exists:cc_periodos,id',
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if ($data['fecha_fin'] < $data['fecha_inicio']) {
                $showmensaje = true;
                throw new Exception("La fecha fin no puede ser menor a la fecha inicio.");
            }

            $database->openConnection();
            $verification = $database->selectColumns('cc_periodos', ['estado'], "fecha_inicio=? AND fecha_fin=? AND estado IN ('1','2') AND id!=?", [$data['fecha_inicio'], $data['fecha_fin'], $data['id']]);

            if (!empty($verification)) {
                $statusPeriodo = ($verification[0]['estado'] == '1') ? 'activo' : 'cerrado';
                $showmensaje = true;
                throw new Exception("Ya existe un periodo $statusPeriodo en las fechas seleccionadas.");
            }

            $database->beginTransaction();

            $cc_periodos = [
                'nombre' => $data['nombre'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'tasa_interes' => $data['tasa_interes'],
                'tasa_mora' => $data['tasa_mora'],
                // 'estado' => 1,
                'updated_at' => $hoy2,
                'updated_by' => $idusuario,
            ];
            $database->update("cc_periodos", $cc_periodos, 'id=?', [$data['id']]);

            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han actualizado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;
    case 'delete_periodos':
        $showmensaje = false;
        try {
            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'token_csrf' => $_POST['inputs'][$csrf->getTokenName()] ?? '',
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
            ];

            $rules = [
                'token_csrf' => 'required',
                'id' => 'required|numeric|min:1|exists:cc_periodos,id',
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();
            $verification = $database->selectColumns('cc_periodos', ['estado'], "estado IN ('1') AND id=?", [$data['id']]);

            if (!empty($verification)) {
                $showmensaje = true;
                throw new Exception("No se pudo encontrar la informacion del periodo, verifique que el periodo siga vigente.");
            }

            $database->beginTransaction();

            $cc_periodos = [
                'estado' => 0,
                'deleted_at' => $hoy2,
                'deleted_by' => $idusuario,
            ];
            $database->update("cc_periodos", $cc_periodos, 'id=?', [$data['id']]);

            $database->commit();
            $status = 1;
            $mensaje = "El periodo ha sido eliminado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'create_account':
        /**
         * obtiene(['<?= $csrf->getTokenName() ?>','tasa_interes','monto_inicial','fecha_inicio_cuenta','fecha_vencimiento'],
         * ['periodo_id'],[],'create_account','0',['<?= htmlspecialchars($secureID->encrypt($codigoCliente)) ?>']
         */

        // $decryptedID = $secureID->decrypt($encryptedID);
        $showmensaje = false;
        try {

            // Log::info("Crear cuenta por cobrar - datos recibidos", $_POST);

            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'fecha_inicio' => $_POST['inputs']['fecha_inicio_cuenta'] ?? null,
                'fecha_fin' => $_POST['inputs']['fecha_vencimiento'] ?? null,
                'tasa_interes' => $_POST['inputs']['tasa_interes'] ?? 0,
                'monto_limite' => $_POST['inputs']['monto_limite'] ?? 0,
                'periodo_id' => (isset($_POST['selects']['periodo_id']) && is_numeric($_POST['selects']['periodo_id'])) ? (0 + $_POST['selects']['periodo_id']) : ($_POST['selects']['periodo_id'] ?? ''),
                'codigo_cliente' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
                'garantias_json' => $_POST['archivo'][1] ?? []
            ];

            // Log::info("Crear cuenta por cobrar - datos para validacion", $data);
            // $showmensaje = true;
            // throw new Exception("Prueba de error");

            $rules = [
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date',
                'tasa_interes' => 'required|numeric|min:0',
                'monto_limite' => 'optional|numeric|min:0',
                'periodo_id' => 'required|numeric|min:1|exists:cc_periodos,id',
                'codigo_cliente' => 'required|numeric|min:1|exists:tb_cliente,idcod_cliente',
                'garantias_json' => 'required',
            ];

            $messages = [
                'garantias_json.required' => 'Debe agregar al menos una garantía para la cuenta.',
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if ($data['fecha_fin'] < $data['fecha_inicio']) {
                $showmensaje = true;
                throw new Exception("La fecha de vencimiento no puede ser menor a la fecha de inicio.");
            }

            $database->openConnection();
            $database->beginTransaction();

            $cc_cuentas = [
                'id_cliente' => $data['codigo_cliente'],
                'id_periodo' => $data['periodo_id'],
                'tasa_interes' => $data['tasa_interes'],
                'monto_inicial' => Beneq::karely($data['monto_limite'], '0'),
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'estado' => 'SOLICITADA',
                'created_at' => $hoy2,
                'created_by' => $idusuario,
            ];
            $idCuenta = $database->insert("cc_cuentas", $cc_cuentas);

            foreach ($data['garantias_json'] as $garantia) {
                $cc_garantias = [
                    'id_cuenta' => $idCuenta,
                    'id_tipogarantia' => $garantia['tipo_id'],
                    'descripcion' => $garantia['descripcion'],
                    'valor' => $garantia['valor'],
                    'observaciones' => $garantia['observaciones'],
                    'estado' => '1',
                    'created_at' => $hoy2,
                    'created_by' => $idusuario,
                ];
                $database->insert("cc_cuentas_garantias", $cc_garantias);
            }

            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);

        break;

    case 'create_desembolso':

        // $decryptedID = $secureID->decrypt($encryptedID);
        $showmensaje = false;
        try {

            // Log::info("Crear desembolso - datos recibidos", $_POST);

            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'fecha_desembolso' => $_POST['inputs']['fecha_movimiento'] ?? null,
                'monto_desembolso' => $_POST['inputs']['monto_movimiento'] ?? 0,
                'observaciones' => trim($_POST['inputs']['observaciones'] ?? ''),
                'numdoc' => trim($_POST['inputs']['numdoc'] ?? ''),
                'forma_pago' => trim($_POST['selects']['forma_pago'] ?? ''),
                'cuenta_id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
                'banco_id' => $_POST['selects']['banco_id'] ?? '',
                'numcheque' => trim($_POST['inputs']['num_cheque'] ?? ''),
            ];

            // Log::info("Crear desembolso - datos para validacion", $data);

            $rules = [
                'fecha_desembolso' => 'required|date',
                'monto_desembolso' => 'required|numeric|min:0.01',
                'observaciones' => 'max_length:500',
                'numdoc' => 'max_length:50',
                'forma_pago' => 'required|in:efectivo,banco',
                'cuenta_id' => 'required|numeric|min:1|exists:cc_cuentas,id',
            ];

            if ($data['forma_pago'] == 'banco') {
                $rules['banco_id'] = 'required|numeric|min:1|exists:ctb_bancos,id';
                $rules['numcheque'] = 'required|max_length:50';
            }

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();

            $datosCliente = $database->getAllResults(
                "SELECT c.short_name, cc.fecha_fin,tasa_interes,cc.estado
                FROM cc_cuentas AS cc
                INNER JOIN tb_cliente AS c ON cc.id_cliente = c.idcod_cliente
                WHERE cc.id = ?",
                [$data['cuenta_id']]
            );
            if (empty($datosCliente)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información del cliente asociado a la cuenta.");
            }

            $datosAgencia = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
            if (empty($datosAgencia)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información de la agencia.");
            }
            $idNomenclaturaCaja = $datosAgencia[0]['id_nomenclatura_caja'];

            if ($data['forma_pago'] == 'banco') {
                $datosBanco = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$data['banco_id']]);
                if (empty($datosBanco)) {
                    $showmensaje = true;
                    throw new Exception("No se pudo obtener la información del banco seleccionado.");
                }
                $idNomenclaturaCaja = $datosBanco[0]['id_nomenclatura'];
            }

            $cuentaCobrar = new CuentaCobrar($database, $data['cuenta_id']);

            $idCuentaContable = $cuentaCobrar->getAccountContableFinanciamiento();

            $database->beginTransaction();

            $cc_desembolsos = [
                'id_cuenta' => $data['cuenta_id'],
                'total' => $data['monto_desembolso'],
                'kp' => $data['monto_desembolso'],
                'fecha' => $data['fecha_desembolso'],
                'tipo' => 'D',
                'numdoc' => $data['numdoc'],
                'forma_pago' => $data['forma_pago'],
                'concepto' => $data['observaciones'],
                'id_ctbbancos' => ($data['forma_pago'] == 'banco') ? $data['banco_id'] : null,
                'doc_banco' => ($data['forma_pago'] == 'banco') ? $data['numcheque'] : null,
                'estado' => '1',
                'created_at' => $hoy2,
                'created_by' => $idusuario,
            ];
            $id_movimiento = $database->insert("cc_kardex", $cc_desembolsos);

            /**
             * CALCULAR INTERES Y CREAR PRIMERA CUOTA
             */

            $dias = Beneq::days_diff($data['fecha_desembolso'], $datosCliente[0]['fecha_fin']);

            $interes = ($data['monto_desembolso'] * $datosCliente[0]['tasa_interes'] / 100 / 365) * $dias;

            $cc_ppg = array(
                'id_cuenta' => $data['cuenta_id'],
                'tipo' => 'original',
                'fecven' => $datosCliente[0]['fecha_fin'],
                'nocuota' => 1,
                'capital' => $data['monto_desembolso'],
                'interes' => $interes,
                'cappag' => $data['monto_desembolso'],
                'intpag' => $interes,
                'mora' => 0.00,
                'aux' => 'CK_' . $id_movimiento,
                'updated_at' => $hoy2,
                'updated_by' => $idusuario
            );
            $database->insert("cc_ppg", $cc_ppg);

            /**
             * Movimientos contables
             */

            // $camp_numcom = getnumcompdo($idusuario, $database);
            $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $data['fecha_desembolso']);

            $ctb_diario = array(
                "numcom" => $camp_numcom,
                "id_ctb_tipopoliza" => 15,
                "id_tb_moneda" => 1,
                "numdoc" => ($data['forma_pago'] == 'banco') ? $data['numcheque'] : $data['numdoc'],
                "glosa" => $data['observaciones'],
                "fecdoc" => $data['fecha_desembolso'],
                "feccnt" => $data['fecha_desembolso'],
                "cod_aux" => "cc_cuenta_" . $data['cuenta_id'],
                "id_tb_usu" => $idusuario,
                "karely" => "CC_" . $id_movimiento,
                "id_agencia" => $idagencia,
                "fecmod" => $hoy2,
                "estado" => 1,
                "editable" => 0,
                "created_by" => $idusuario
            );

            $idDiario = $database->insert("ctb_diario", $ctb_diario);

            $ctb_mov = array(
                "id_ctb_diario" => $idDiario,
                "id_fuente_fondo" => 1,
                "id_ctb_nomenclatura" => $idCuentaContable,
                "debe" => $data['monto_desembolso'],
                "haber" => 0.00,
            );

            $database->insert("ctb_mov", $ctb_mov);

            $ctb_mov = array(
                "id_ctb_diario" => $idDiario,
                "id_fuente_fondo" => 1,
                "id_ctb_nomenclatura" => $idNomenclaturaCaja,
                "debe" => 0.00,
                "haber" => $data['monto_desembolso'],
            );

            $database->insert("ctb_mov", $ctb_mov);

            if ($data['forma_pago'] == 'banco') {
                $ctb_chq = array(
                    "id_ctb_diario" => $idDiario,
                    "id_cuenta_banco" => $data['banco_id'],
                    "numchq" => $data['numcheque'],
                    "nomchq" => $datosCliente[0]['short_name'],
                    "monchq" => $data['monto_desembolso'],
                    "modocheque" => 0,
                    "emitido" => "0",
                );
                $database->insert("ctb_chq", $ctb_chq);
            }


            if ($datosCliente[0]['estado'] == 'SOLICITADA') {
                $database->update(
                    'cc_cuentas',
                    ['estado' => 'ACTIVA', 'updated_at' => $hoy2, 'updated_by' => $idusuario],
                    'id=?',
                    [$data['cuenta_id']]
                );
            }


            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            $status = 0;
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status,
            'idMovimiento' => $id_movimiento ?? null,
        ]);

        break;

    case 'create_anticipo':
        $showmensaje = false;
        try {

            // Log::info("Crear desembolso - datos recibidos", $_POST);

            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'fecha' => $_POST['inputs']['fecha_movimiento_anticipo'] ?? null,
                'monto' => $_POST['inputs']['monto_movimiento_anticipo'] ?? 0,
                'observaciones' => trim($_POST['inputs']['observaciones_anticipo'] ?? ''),
                'numdoc' => trim($_POST['inputs']['numdoc_anticipo'] ?? ''),
                'forma_pago' => trim($_POST['selects']['forma_pago_anticipo'] ?? ''),
                'cuenta_id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
                'tipo_movimiento' => trim($_POST['selects']['tipo_movimiento_anticipo'] ?? ''),
                'banco_id' => $_POST['selects']['banco_id_anticipo'] ?? '',
                'numcheque' => trim($_POST['inputs']['num_cheque_anticipo'] ?? ''),
            ];

            // Log::info("Crear movimiento - datos para validacion", $data);

            $rules = [
                'fecha' => 'required|date',
                'monto' => 'required|numeric|min:0.01',
                'observaciones' => 'max_length:500',
                'numdoc' => 'max_length:50',
                'forma_pago' => 'required|in:efectivo,banco',
                'cuenta_id' => 'required|numeric|min:1|exists:cc_cuentas,id',
                'tipo_movimiento' => 'required|min:1|exists:cc_tipos_movimientos,id',
            ];

            if ($data['forma_pago'] == 'banco') {
                $rules['banco_id'] = 'required|numeric|min:1|exists:ctb_bancos,id';
                $rules['numcheque'] = 'required|max_length:50';
            }

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();

            $datosCliente = $database->getAllResults(
                "SELECT c.short_name, cc.fecha_fin,tasa_interes, cc.estado
                FROM cc_cuentas AS cc
                INNER JOIN tb_cliente AS c ON cc.id_cliente = c.idcod_cliente
                WHERE cc.id = ?",
                [$data['cuenta_id']]
            );
            if (empty($datosCliente)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información del cliente asociado a la cuenta.");
            }

            $datosAgencia = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
            if (empty($datosAgencia)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información de la agencia.");
            }
            $idNomenclaturaCaja = $datosAgencia[0]['id_nomenclatura_caja'];

            if ($data['forma_pago'] == 'banco') {
                $datosBanco = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$data['banco_id']]);
                if (empty($datosBanco)) {
                    $showmensaje = true;
                    throw new Exception("No se pudo obtener la información del banco seleccionado.");
                }
                $idNomenclaturaCaja = $datosBanco[0]['id_nomenclatura'];
            }

            $datosTipoMovimiento = $database->selectColumns('cc_tipos_movimientos', ['genera_interes', 'id_nomenclatura'], 'id=?', [$data['tipo_movimiento']]);
            if (empty($datosTipoMovimiento)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información del tipo de movimiento seleccionado.");
            }

            if (is_null($datosTipoMovimiento[0]['id_nomenclatura']) || $datosTipoMovimiento[0]['id_nomenclatura'] == '') {
                $showmensaje = true;
                throw new Exception("No se encontró una cuenta contable para este tipo de movimiento.");
            }

            $database->beginTransaction();

            $cc_desembolsos = [
                'id_cuenta' => $data['cuenta_id'],
                'total' => $data['monto'],
                // 'kp' => $data['monto'],
                'fecha' => $data['fecha'],
                'tipo' => 'E',
                'numdoc' => $data['numdoc'],
                'forma_pago' => $data['forma_pago'],
                'concepto' => $data['observaciones'],
                'id_ctbbancos' => ($data['forma_pago'] == 'banco') ? $data['banco_id'] : null,
                'doc_banco' => ($data['forma_pago'] == 'banco') ? $data['numcheque'] : null,
                'estado' => '1',
                'created_at' => $hoy2,
                'created_by' => $idusuario,
            ];
            $id_movimiento = $database->insert("cc_kardex", $cc_desembolsos);

            $cc_kardex_detalle = [
                'id_kardex' => $id_movimiento,
                'id_movimiento' => $data['tipo_movimiento'],
                'monto' => $data['monto'],
            ];
            $database->insert("cc_kardex_detalle", $cc_kardex_detalle);


            /**
             * CALCULAR INTERES Y CREAR LA CUOTA
             */

            if ($datosTipoMovimiento[0]['genera_interes'] == 'yes') {

                $dias = Beneq::days_diff($data['fecha'], $datosCliente[0]['fecha_fin']);

                $interes = ($data['monto'] * $datosCliente[0]['tasa_interes'] / 100 / 365) * $dias;
            } else {
                $interes = 0.00;
            }

            $lastCuota = $database->selectColumns('cc_ppg', ['MAX(nocuota) AS maxcuota'], 'id_cuenta=?', [$data['cuenta_id']]);

            $cc_ppg = array(
                'id_cuenta' => $data['cuenta_id'],
                'tipo' => 'otros',
                'fecven' => $datosCliente[0]['fecha_fin'],
                'nocuota' => (($lastCuota[0]['maxcuota'] ?? 0) + 1),
                'capital' => $data['monto'],
                'interes' => $interes,
                'cappag' => $data['monto'],
                'intpag' => $interes,
                'mora' => 0.00,
                'id_tipomov' => $data['tipo_movimiento'],
                'aux' => 'CK_' . $id_movimiento,
                'updated_at' => $hoy2,
                'updated_by' => $idusuario
            );
            $database->insert("cc_ppg", $cc_ppg);

            /**
             * Movimientos contables
             */

            // $camp_numcom = getnumcompdo($idusuario, $database);
            $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $data['fecha']);

            $ctb_diario = array(
                "numcom" => $camp_numcom,
                "id_ctb_tipopoliza" => 15,
                "id_tb_moneda" => 1,
                "numdoc" => ($data['forma_pago'] == 'banco') ? $data['numcheque'] : $data['numdoc'],
                "glosa" => $data['observaciones'],
                "fecdoc" => $data['fecha'],
                "feccnt" => $data['fecha'],
                "cod_aux" => "cc_cuenta_" . $data['cuenta_id'],
                "id_tb_usu" => $idusuario,
                "karely" => "CC_" . $id_movimiento,
                "id_agencia" => $idagencia,
                "fecmod" => $hoy2,
                "estado" => 1,
                "editable" => 0,
                "created_by" => $idusuario
            );

            $idDiario = $database->insert("ctb_diario", $ctb_diario);

            $ctb_mov = array(
                "id_ctb_diario" => $idDiario,
                "id_fuente_fondo" => 1,
                "id_ctb_nomenclatura" => $datosTipoMovimiento[0]['id_nomenclatura'],
                "debe" => $data['monto'],
                "haber" => 0.00,
            );

            $database->insert("ctb_mov", $ctb_mov);

            $ctb_mov = array(
                "id_ctb_diario" => $idDiario,
                "id_fuente_fondo" => 1,
                "id_ctb_nomenclatura" => $idNomenclaturaCaja,
                "debe" => 0.00,
                "haber" => $data['monto'],
            );

            $database->insert("ctb_mov", $ctb_mov);

            if ($data['forma_pago'] == 'banco') {
                $ctb_chq = array(
                    "id_ctb_diario" => $idDiario,
                    "id_cuenta_banco" => $data['banco_id'],
                    "numchq" => $data['numcheque'],
                    "nomchq" => $datosCliente[0]['short_name'],
                    "monchq" => $data['monto'],
                    "modocheque" => 0,
                    "emitido" => "0",
                );
                $database->insert("ctb_chq", $ctb_chq);
            }

            if ($datosCliente[0]['estado'] == 'SOLICITADA') {
                $database->update(
                    'cc_cuentas',
                    ['estado' => 'ACTIVA', 'updated_at' => $hoy2, 'updated_by' => $idusuario],
                    'id=?',
                    [$data['cuenta_id']]
                );
            }


            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            $status = 0;
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status,
            'idMovimiento' => $id_movimiento ?? null,
        ]);
        break;
    case 'create_payment':
        /**
         * ,'fecha_pago','monto_capital','monto_interes','monto_mora','numdoc','observaciones','num_boleta'],
         * ['forma_pago','banco_id']
         */
        $showmensaje = false;
        try {

            // Log::info("Crear pago - datos recibidos", $_POST['archivo']);

            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'fecha' => $_POST['inputs']['fecha_pago'] ?? null,
                'monto_capital' => $_POST['inputs']['monto_capital'] ?? 0,
                'monto_interes' => $_POST['inputs']['monto_interes'] ?? 0,
                'monto_mora' => $_POST['inputs']['monto_mora'] ?? 0,
                'numdoc' => trim($_POST['inputs']['numdoc'] ?? ''),
                'observaciones' => trim($_POST['inputs']['observaciones'] ?? ''),
                'num_boleta' => trim($_POST['inputs']['num_boleta'] ?? ''),
                'forma_pago' => trim($_POST['selects']['forma_pago'] ?? ''),
                'banco_id' => $_POST['selects']['banco_id'] ?? '',
                'cuenta_id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
                'otros' => $_POST['archivo'][1] ?? [],
            ];

            // Log::info("Crear movimiento - datos para validacion", $data);

            $rules = [
                'fecha' => 'required|date',
                'monto_capital' => 'optional|numeric|min:0',
                'monto_interes' => 'optional|numeric|min:0',
                'monto_mora' => 'optional|numeric|min:0',
                'numdoc' => 'required|max_length:50',
                'observaciones' => 'required|max_length:500',
                'forma_pago' => 'required|in:efectivo,banco',
                'cuenta_id' => 'required|numeric|min:1|exists:cc_cuentas,id',
                'otros' => 'optional|array',
            ];

            if ($data['forma_pago'] == 'banco') {
                $rules['banco_id'] = 'required|numeric|min:1|exists:ctb_bancos,id';
                $rules['num_boleta'] = 'required|max_length:50';
            }

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            // Log::info("data ", $data);

            if (($data['monto_capital'] + $data['monto_interes'] + $data['monto_mora']) <= 0) {
                $showmensaje = true;
                throw new Exception("El monto total del pago debe ser mayor a cero.");
            }

            $database->openConnection();

            $datosCliente = $database->getAllResults(
                "SELECT cc.fecha_fin,cc.tasa_interes FROM cc_cuentas AS cc WHERE cc.id = ?",
                [$data['cuenta_id']]
            );
            if (empty($datosCliente)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información de la cuenta.");
            }

            $datosAgencia = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
            if (empty($datosAgencia)) {
                $showmensaje = true;
                throw new Exception("No se pudo obtener la información de la agencia.");
            }
            $idNomenclaturaCaja = $datosAgencia[0]['id_nomenclatura_caja'];

            if ($data['forma_pago'] == 'banco') {
                $datosBanco = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$data['banco_id']]);
                if (empty($datosBanco)) {
                    $showmensaje = true;
                    throw new Exception("No se pudo obtener la información del banco seleccionado.");
                }
                $idNomenclaturaCaja = $datosBanco[0]['id_nomenclatura'];
            }

            $database->beginTransaction();

            $totalInteres = $data['monto_interes'];
            $totalMora = $data['monto_mora'];
            $totalOtros = 0;

            // "id":"1","nombre":"ANTICIPOS","capital":"5000","interes":"0","mora":"0"

            if (!empty($data['otros'])) {
                foreach ($data['otros'] as $otroPago) {
                    $totalOtros += $otroPago['capital'];
                    $totalInteres += $otroPago['interes'];
                    $totalMora += $otroPago['mora'];
                }
            }

            $totalGeneral = $data['monto_capital'] + $totalInteres + $totalMora + $totalOtros;

            $cc_kardex = [
                'id_cuenta' => $data['cuenta_id'],
                'total' => $totalGeneral,
                'kp' => $data['monto_capital'],
                'interes' => $totalInteres,
                'mora' => $totalMora,
                'fecha' => $data['fecha'],
                'tipo' => 'I',
                'numdoc' => $data['numdoc'],
                'forma_pago' => $data['forma_pago'],
                'concepto' => $data['observaciones'],
                'id_ctbbancos' => ($data['forma_pago'] == 'banco') ? $data['banco_id'] : null,
                'doc_banco' => ($data['forma_pago'] == 'banco') ? $data['num_boleta'] : null,
                'estado' => '1',
                'created_at' => $hoy2,
                'created_by' => $idusuario,
            ];
            $id_movimiento = $database->insert("cc_kardex", $cc_kardex);

            if (!empty($data['otros'])) {
                foreach ($data['otros'] as $otroPago) {
                    $cc_kardex_detalle = [
                        'id_kardex' => $id_movimiento,
                        'id_movimiento' => $otroPago['id'],
                        'monto' => $otroPago['capital'],
                    ];
                    $database->insert("cc_kardex_detalle", $cc_kardex_detalle);
                }
            }

            /**
             * Movimientos contables
             */

            $cuentaCobrar = new CuentaCobrar($database, $data['cuenta_id']);

            // $camp_numcom = getnumcompdo($idusuario, $database);
            $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $data['fecha']);

            $ctb_diario = array(
                "numcom" => $camp_numcom,
                "id_ctb_tipopoliza" => 15,
                "id_tb_moneda" => 1,
                "numdoc" => ($data['forma_pago'] == 'banco') ? $data['num_boleta'] : $data['numdoc'],
                "glosa" => $data['observaciones'],
                "fecdoc" => $data['fecha'],
                "feccnt" => $data['fecha'],
                "cod_aux" => "cc_cuenta_" . $data['cuenta_id'],
                "id_tb_usu" => $idusuario,
                "karely" => "CC_" . $id_movimiento,
                "id_agencia" => $idagencia,
                "fecmod" => $hoy2,
                "estado" => 1,
                "editable" => 0,
                "created_by" => $idusuario
            );

            $idDiario = $database->insert("ctb_diario", $ctb_diario);

            $ctb_mov = array(
                "id_ctb_diario" => $idDiario,
                "id_fuente_fondo" => 1,
                "id_ctb_nomenclatura" => $idNomenclaturaCaja,
                "debe" => $totalGeneral,
                "haber" => 0.00,
            );

            $database->insert("ctb_mov", $ctb_mov);

            /**
             * INGRESO A CAPITAL KP
             */

            if ($data['monto_capital'] > 0) {
                $idCuentaContableFinanciamiento = $cuentaCobrar->getAccountContableFinanciamiento();
                $ctb_mov = array(
                    "id_ctb_diario" => $idDiario,
                    "id_fuente_fondo" => 1,
                    "id_ctb_nomenclatura" => $idCuentaContableFinanciamiento,
                    "debe" => 0.00,
                    "haber" => $data['monto_capital'],
                );

                $database->insert("ctb_mov", $ctb_mov);
            }

            if ($totalInteres > 0 || $totalMora > 0) {
                $cuentasContables = $cuentaCobrar->getAccountContableInteresMora();
            }

            if ($totalInteres > 0) {
                $ctb_mov = array(
                    "id_ctb_diario" => $idDiario,
                    "id_fuente_fondo" => 1,
                    "id_ctb_nomenclatura" => $cuentasContables['interes'],
                    "debe" => 0.00,
                    "haber" => $totalInteres,
                );

                $database->insert("ctb_mov", $ctb_mov);
            }

            if ($totalMora > 0) {
                $ctb_mov = array(
                    "id_ctb_diario" => $idDiario,
                    "id_fuente_fondo" => 1,
                    "id_ctb_nomenclatura" => $cuentasContables['mora'],
                    "debe" => 0.00,
                    "haber" => $totalMora,
                );

                $database->insert("ctb_mov", $ctb_mov);
            }

            if (!empty($data['otros'])) {
                foreach ($data['otros'] as $otroPago) {
                    if ($otroPago['capital'] > 0) {
                        $cuentaContableOtro = $cuentaCobrar->getAccountContableAnothers($otroPago['id']);

                        $ctb_mov = array(
                            "id_ctb_diario" => $idDiario,
                            "id_fuente_fondo" => 1,
                            "id_ctb_nomenclatura" => $cuentaContableOtro,
                            "debe" => 0.00,
                            "haber" => $otroPago['capital'],
                        );

                        $database->insert("ctb_mov", $ctb_mov);
                    }
                }
            }

            /**
             * ACTUALIZACION DE PLAN DE PAGOS
             */
            $planPagosModel = new PlanPagos($database);
            $planPagosModel->updatePlanPago($data['cuenta_id'], $data['fecha']);


            if ($data['monto_capital'] > 0 && $data['fecha'] < $datosCliente[0]['fecha_fin']) {
                //reestructurar plan de pagos cuando se abona capital antes de la fecha final
                $planPagosModel->reestructuraPlanPago($data['cuenta_id'], $data['fecha']);
            }


            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            $status = 0;
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status,
            'idMovimiento' => $id_movimiento ?? null,
        ]);

        break;

    case 'cc_catalogo_unidades_create':

        $showmensaje = false;
        try {

            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'nombre' => $_POST['inputs']['unidad_nombre'] ?? null,
                'simbolo' => $_POST['inputs']['unidad_simbolo'] ?? null,
            ];

            $rules = [
                'nombre' => 'required|max_length:100|unique:tb_unidades_medida,nombre',
                'simbolo' => 'optional|max_length:5',
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();
            $database->beginTransaction();

            $unidad_medida = [
                'nombre' => $data['nombre'],
                'simbolo' => $data['simbolo'],
            ];
            $database->insert("tb_unidades_medida", $unidad_medida);

            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() :    "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_catalogo_unidades_update':
        $showmensaje = false;
        try {
            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
                'nombre' => $_POST['inputs']['unidad_nombre'] ?? null,
                'simbolo' => $_POST['inputs']['unidad_simbolo'] ?? null,
            ];

            $rules = [
                'id' => 'required|numeric|min:1|exists:tb_unidades_medida,id',
                'nombre' => 'required|max_length:100|unique:tb_unidades_medida,nombre,' . $data['id'],
                'simbolo' => 'optional|max_length:5',
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();
            $database->beginTransaction();

            $unidad_medida = [
                'nombre' => $data['nombre'],
                'simbolo' => $data['simbolo'],
            ];
            $database->update("tb_unidades_medida", $unidad_medida, 'id=?', [$data['id']]);

            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han actualizado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() :    "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_catalogo_unidades_delete':
        $showmensaje = false;
        try {
            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
            ];
            $rules = [
                'id' => 'required|numeric|min:1|exists:tb_unidades_medida,id',
            ];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }
            $database->openConnection();
            // Verificar si la unidad de medida está siendo utilizada en cc_productos_precios
            $productosAsociados = $database->selectColumns('cc_productos_precios', ['COUNT(*) AS total'], 'id_medida=?', [$data['id']]);
            if (!empty($productosAsociados) && $productosAsociados[0]['total'] > 0) {
                $showmensaje = true;
                throw new Exception("No se puede eliminar la unidad de medida porque está siendo utilizada en productos.");
            }
            $database->beginTransaction();
            $database->delete("tb_unidades_medida", 'id=?', [$data['id']]);
            $database->commit();
            $status = 1;
            $mensaje = "La unidad de medida ha sido eliminada correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() :    "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_productos_create':
        $showmensaje = false;
        try {
            $data = [
                'nombre' => trim($_POST['inputs']['producto_nombre'] ?? ''),
                'descripcion' => trim($_POST['inputs']['producto_descripcion'] ?? ''),
                'id_nomenclatura' => trim($_POST['selects']['producto_nomenclatura'] ?? ''),
                'precios_json' => $_POST['archivo'][0] ?? []
            ];

            $rules = [
                'nombre' => 'required|min:2|max_length:100|unique:cc_productos,nombre',
                'descripcion' => 'optional|max_length:255',
                'id_nomenclatura' => 'required|numeric|min:1|exists:ctb_nomenclatura,id',
                'precios_json' => 'required',
            ];

            $messages = [
                'precios_json.required' => 'Debe agregar al menos un precio para el producto.',
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if (empty($data['precios_json']) || !is_array($data['precios_json'])) {
                $showmensaje = true;
                throw new Exception("Debe agregar al menos un precio para el producto.");
            }

            $database->openConnection();
            $database->beginTransaction();

            $producto = [
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'],
                'id_nomenclatura' => $data['id_nomenclatura'],
                'estado' => '1',
                'created_by' => $idusuario,
                'created_at' => $hoy2
            ];
            $idProducto = $database->insert("cc_productos", $producto);

            foreach ($data['precios_json'] as $precio) {
                // Validar que la unidad de medida existe
                $unidadExists = $database->selectColumns('tb_unidades_medida', ['id'], 'id=?', [$precio['id_medida']]);
                if (empty($unidadExists)) {
                    $showmensaje = true;
                    throw new Exception("La unidad de medida seleccionada no existe.");
                }

                $productoPrecio = [
                    'id_producto' => $idProducto,
                    'nombre' => $precio['nombre'],
                    'id_medida' => $precio['id_medida'],
                    'precio' => $precio['precio'],
                    'updated_by' => $idusuario,
                    'updated_at' => $hoy2
                ];
                $database->insert("cc_productos_precios", $productoPrecio);
            }

            $database->commit();
            $status = 1;
            $mensaje = "El producto se ha guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_productos_update':
        $showmensaje = false;
        try {
            $data = [
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
                'nombre' => trim($_POST['inputs']['producto_nombre'] ?? ''),
                'descripcion' => trim($_POST['inputs']['producto_descripcion'] ?? ''),
                'id_nomenclatura' => trim($_POST['selects']['producto_nomenclatura'] ?? ''),
                'precios_json' => $_POST['archivo'][1] ?? []
            ];

            $rules = [
                'id' => 'required|numeric|min:1|exists:cc_productos,id',
                'nombre' => 'required|min:2|max_length:100|unique:cc_productos,nombre,' . $data['id'],
                'descripcion' => 'optional|max_length:255',
                'id_nomenclatura' => 'required|numeric|min:1|exists:ctb_nomenclatura,id',
                'precios_json' => 'required',
            ];

            $messages = [
                'precios_json.required' => 'Debe agregar al menos un precio para el producto.',
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if (empty($data['precios_json']) || !is_array($data['precios_json'])) {
                $showmensaje = true;
                throw new Exception("Debe agregar al menos un precio para el producto.");
            }

            $database->openConnection();
            $database->beginTransaction();

            $producto = [
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'],
                'id_nomenclatura' => $data['id_nomenclatura'],
                'updated_by' => $idusuario,
                'updated_at' => $hoy2
            ];
            $database->update("cc_productos", $producto, 'id=?', [$data['id']]);

            // Eliminar precios existentes
            $database->delete("cc_productos_precios", 'id_producto=?', [$data['id']]);

            // Insertar nuevos precios
            foreach ($data['precios_json'] as $precio) {
                $unidadExists = $database->selectColumns('tb_unidades_medida', ['id'], 'id=?', [$precio['id_medida']]);
                if (empty($unidadExists)) {
                    throw new Exception("La unidad de medida seleccionada no existe.");
                }

                $productoPrecio = [
                    'id_producto' => $data['id'],
                    'nombre' => $precio['nombre'] ?? '',
                    'id_medida' => $precio['id_medida'],
                    'precio' => $precio['precio'],
                    'updated_by' => $idusuario,
                    'updated_at' => $hoy2
                ];
                $database->insert("cc_productos_precios", $productoPrecio);
            }

            $database->commit();
            $status = 1;
            $mensaje = "El producto se ha actualizado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_productos_delete':
        $showmensaje = false;
        try {
            $data = [
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
            ];
            $rules = [
                'id' => 'required|numeric|min:1|exists:cc_productos,id',
            ];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }
            $database->openConnection();
            // Verificar si el producto está siendo utilizado en cc_kardex_detalle
            $usosAsociados = $database->getAllResults(
                "SELECT COUNT(*) AS total FROM cc_compras_detalle det INNER JOIN cc_compras com ON com.id=det.id_compra
                    WHERE com.estado!='deleted' AND det.id_producto=?;",
                [$data['id']]
            );
            if (!empty($usosAsociados) && $usosAsociados[0]['total'] > 0) {
                $showmensaje = true;
                throw new Exception("No se puede eliminar el producto porque está siendo utilizado en compras realizadas o en proceso.");
            }
            $database->beginTransaction();

            $productos = array(
                'estado' => '0',
                'deleted_by' => $idusuario,
                'deleted_at' => $hoy2
            );

            $database->update("cc_productos", $productos, 'id=?', [$data['id']]);
            $database->commit();
            $status = 1;
            $mensaje = "El producto ha sido eliminado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() :    "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_descuentos_create':
        $showmensaje = false;
        try {
            $data = [
                'nombre' => trim($_POST['inputs']['descuento_nombre'] ?? ''),
                'categoria' => trim($_POST['selects']['descuento_categoria'] ?? ''),
                'monto' => $_POST['inputs']['descuento_monto'] ?? null,
                'id_nomenclatura' => $_POST['selects']['descuento_nomenclatura'] ?? null,
            ];

            $rules = [
                'nombre' => 'required|min:2|max_length:100|unique:cc_descuentos,nombre',
                'categoria' => 'required|in:financiamientos,prestamos,ahorros,aportaciones,aux_postumo,otros',
            ];

            // Validaciones condicionales si la categoría es "otros"
            if ($data['categoria'] === 'otros') {
                $rules['monto'] = 'required|numeric|min:0.01';
                $rules['id_nomenclatura'] = 'required|numeric|min:1|exists:ctb_nomenclatura,id';
            }

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();

            /**
             * SI NO ES OTROS, VERIFICAR QUE NO EXISTA OTRO DESCUENTO EN ESA CATEGORIA 
             */
            if ($data['categoria'] !== 'otros') {
                $existingDescuento = $database->selectColumns(
                    'cc_descuentos',
                    ['id'],
                    'categoria=?',
                    [$data['categoria']]
                );
                if (!empty($existingDescuento)) {
                    $showmensaje = true;
                    throw new Exception("Ya existe un tipo de descuento registrado para la categoría seleccionada.");
                }
            }

            $database->beginTransaction();

            $descuento = [
                'nombre' => $data['nombre'],
                'categoria' => $data['categoria'],
                'monto' => ($data['categoria'] === 'otros') ? $data['monto'] : null,
                'id_nomenclatura' => ($data['categoria'] === 'otros') ? $data['id_nomenclatura'] : null,
            ];
            $database->insert("cc_descuentos", $descuento);

            $database->commit();
            $status = 1;
            $mensaje = "El tipo de descuento se ha guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_descuentos_update':
        $showmensaje = false;
        try {
            $data = [
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
                'nombre' => trim($_POST['inputs']['descuento_nombre'] ?? ''),
                'categoria' => trim($_POST['selects']['descuento_categoria'] ?? ''),
                'monto' => $_POST['inputs']['descuento_monto'] ?? null,
                'id_nomenclatura' => $_POST['selects']['descuento_nomenclatura'] ?? null,
            ];

            $rules = [
                'id' => 'required|numeric|min:1|exists:cc_descuentos,id',
                'nombre' => 'required|min:2|max_length:100|unique:cc_descuentos,nombre,' . $data['id'],
                'categoria' => 'required|in:financiamientos,prestamos,ahorros,aportaciones,aux_postumo,otros',
            ];

            // Validaciones condicionales si la categoría es "otros"
            if ($data['categoria'] === 'otros') {
                $rules['monto'] = 'required|numeric|min:0.01';
                $rules['id_nomenclatura'] = 'required|numeric|min:1|exists:ctb_nomenclatura,id';
            }

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();

            /**
             * SI NO ES OTROS, VERIFICAR QUE NO EXISTA OTRO DESCUENTO EN ESA CATEGORIA 
             */
            if ($data['categoria'] !== 'otros') {
                $existingDescuento = $database->selectColumns(
                    'cc_descuentos',
                    ['id'],
                    'categoria=? AND id<>?',
                    [$data['categoria'], $data['id']]
                );
                if (!empty($existingDescuento)) {
                    $showmensaje = true;
                    throw new Exception("Ya existe un tipo de descuento registrado para la categoría seleccionada.");
                }
            }

            $database->beginTransaction();

            $descuento = [
                'nombre' => $data['nombre'],
                'categoria' => $data['categoria'],
                'monto' => ($data['categoria'] === 'otros') ? $data['monto'] : null,
                'id_nomenclatura' => ($data['categoria'] === 'otros') ? $data['id_nomenclatura'] : null,
            ];
            $database->update("cc_descuentos", $descuento, 'id=?', [$data['id']]);

            $database->commit();
            $status = 1;
            $mensaje = "El tipo de descuento se ha actualizado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_descuentos_delete':
        $showmensaje = false;
        try {
            $data = [
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
            ];
            $rules = [
                'id' => 'required|numeric|min:1|exists:cc_descuentos,id',
            ];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }
            $database->openConnection();

            // Verificar si el descuento está siendo utilizado en cc_compras_descuentos
            $usosAsociados = $database->selectColumns('cc_compras_descuentos', ['COUNT(*) AS total'], 'id_descuento=?', [$data['id']]);
            if (!empty($usosAsociados) && $usosAsociados[0]['total'] > 0) {
                $showmensaje = true;
                throw new Exception("No se puede eliminar el tipo de descuento porque está siendo utilizado en compras.");
            }

            $database->beginTransaction();
            $database->delete("cc_descuentos", 'id=?', [$data['id']]);
            $database->commit();

            $status = 1;
            $mensaje = "El tipo de descuento ha sido eliminado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_compras_create':

        /**
         *  ['<?= $csrf->getTokenName() ?>', 'compra_fecha', 'compra_numdoc', 'compra_concepto', 'compra_doc_banco'],
         * ['compra_forma_pago', 'compra_banco'], [], 'cc_compras_create', '0', [this.detalles, estado, idCliente, this.descuentos],
         */
        $showmensaje = false;
        try {

            Log::info("datos productos: " . print_r($_POST['archivo'][0] ?? [], true));
            Log::info("datos descuentos: " . print_r($_POST['archivo'][3] ?? [], true));

            // Log::info("post datos: " . print_r($_POST, true));

            $data = [
                'id_cliente' => $_POST['archivo'][2] ?? '',
                'fecha' => $_POST['inputs']['compra_fecha'] ?? null,
                'numdoc' => trim($_POST['inputs']['compra_numdoc'] ?? ''),
                'concepto' => trim($_POST['inputs']['compra_concepto'] ?? ''),
                'forma_pago' => trim($_POST['selects']['compra_forma_pago'] ?? ''),
                'id_ctbbancos' => $_POST['selects']['compra_banco'] ?? null,
                'doc_banco' => trim($_POST['inputs']['compra_doc_banco'] ?? ''),
                'detalles_json' => $_POST['archivo'][0] ?? [],
                'descuentos_json' => $_POST['archivo'][3] ?? [],
                'estado' => $_POST['archivo'][1] ?? 'settled',
            ];

            $rules = [
                'id_cliente' => 'required|numeric|min:1|exists:tb_cliente,idcod_cliente',
                'fecha' => 'required|date',
                'numdoc' => 'required|max_length:50',
                'concepto' => 'required|max_length:255',
                'forma_pago' => 'required|in:efectivo,banco',
                'detalles_json' => 'required',
                'estado' => 'required|in:draft,pending,settled',
            ];

            if ($data['forma_pago'] === 'banco') {
                $rules['id_ctbbancos'] = 'required|numeric|min:1|exists:ctb_bancos,id';
                $rules['doc_banco'] = 'required|max_length:50';
            }

            $messages = [
                'detalles_json.required' => 'Debe agregar al menos un producto.',
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if (empty($data['detalles_json']) || !is_array($data['detalles_json'])) {
                $showmensaje = true;
                throw new Exception("Debe agregar al menos un producto.");
            }

            // Log::info("detalles_json: " . print_r($data['detalles_json'], true));

            // $showmensaje = true;
            // throw new Exception("Prueba de error controlado.");

            $database->openConnection();
            $database->beginTransaction();

            // Calcular totales
            $subtotal = 0;
            foreach ($data['detalles_json'] as $detalle) {
                $subtotal += $detalle['cantidad'] * $detalle['precio_unitario'];
            }

            $totalDescuentos = 0;
            if (!empty($data['descuentos_json']) && is_array($data['descuentos_json'])) {
                foreach ($data['descuentos_json'] as $descuento) {
                    $totalDescuentos += $descuento['monto'];
                }
            }

            $total = $subtotal - $totalDescuentos;

            if ($total <= 0) {
                $showmensaje = true;
                throw new Exception("El total de la compra debe ser mayor a cero.");
            }

            // Insertar compra
            $compra = [
                'id_cliente' => $data['id_cliente'],
                'numdoc' => $data['numdoc'],
                'fecha' => $data['fecha'],
                'forma_pago' => $data['forma_pago'],
                'concepto' => $data['concepto'],
                'estado' => $data['estado'],
                'id_ctbbancos' => ($data['forma_pago'] === 'banco') ? $data['id_ctbbancos'] : null,
                'doc_banco' => ($data['forma_pago'] === 'banco') ? $data['doc_banco'] : null,
                'created_by' => $idusuario,
                'created_at' => $hoy2,
            ];
            $idCompra = $database->insert("cc_compras", $compra);

            $productosAgrupados = [];

            // Insertar detalles
            foreach ($data['detalles_json'] as $detalle) {
                $compraDetalle = [
                    'id_compra' => $idCompra,
                    'id_producto' => $detalle['id_producto'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'medida' => $detalle['medida'],
                    'descripcion' => $detalle['descripcion'],
                ];
                $database->insert("cc_compras_detalle", $compraDetalle);

                // Agrupar productos por id_producto para movimientos contables
                if (!isset($productosAgrupados[$detalle['id_producto']])) {
                    $productosAgrupados[$detalle['id_producto']] = 0;
                }
                $productosAgrupados[$detalle['id_producto']] += ($detalle['cantidad'] * $detalle['precio_unitario']);
            }

            // Insertar descuentos
            if (!empty($data['descuentos_json']) && is_array($data['descuentos_json'])) {
                foreach ($data['descuentos_json'] as $key => $descuento) {

                    $observacion = ($descuento['categoria'] !== 'otros') ? "Descuento aplicado: " . $descuento['nombre'] : "";

                    // $idReal = $descuento['id_descuento'];

                    // if ($descuento['categoria'] !== 'otros') {
                    $datoDescuento = $database->selectColumns('cc_descuentos', ['id', 'id_nomenclatura'], 'categoria=?', [($descuento['categoria'] == 'otras_entregas') ? 'financiamientos' : $descuento['categoria']]);
                    if (empty($datoDescuento)) {
                        $showmensaje = true;
                        throw new Exception("No se encontró el descuento para la categoría: " . $descuento['categoria']);
                    }
                    $idReal = ($descuento['categoria'] === 'otros') ? $descuento['id_descuento'] : $datoDescuento[0]['id'];
                    $data['descuentos_json'][$key]['id_nomenclatura'] =  ($descuento['categoria'] === 'otros') ? $datoDescuento[0]['id_nomenclatura'] : null;
                    // }

                    $compraDescuento = [
                        'id_compra' => $idCompra,
                        'id_descuento' => $idReal,
                        'monto' => $descuento['monto'],
                        'observaciones' => $observacion,
                    ];
                    $database->insert("cc_compras_descuentos", $compraDescuento);

                    /**
                     * AQUI CONTROLAR LOS DESCUENTOS ESPECIFICOS POR MODULOS, TIRARLOS A SUS MODULOS.
                     */

                    if ($descuento['categoria'] === 'financiamientos') {
                        $cckardex = new Kardex($database);
                        $partes = explode('_', $descuento['id_descuento']);
                        $dataKardex = [
                            'cuenta_id' => $partes[1],
                            'forma_pago' => 'otro',
                            'monto_capital' => $descuento['capital'],
                            'monto_interes' => $descuento['interes'],
                            'monto_mora' => $descuento['mora'],
                            'fecha' => $data['fecha'],
                            'numdoc' => $data['numdoc'],
                            'concepto' => "AUTO - COMPRA PRODUCTOS",

                        ];
                        $datosReturn = $cckardex->registrarPago($dataKardex);
                        $data['descuentos_json'][$key]['id_nomenclatura'] = $datosReturn['id_cuenta_financiamiento'] ?? null;
                        $data['descuentos_json'][$key]['id_kardex'] = $datosReturn['id_kardex'] ?? null;
                        $data['descuentos_json'][$key]['id_nomenclatura_interes'] = $datosReturn['id_cuenta_interes'] ?? null;
                        $data['descuentos_json'][$key]['id_nomenclatura_mora'] = $datosReturn['id_cuenta_mora'] ?? null;
                    }

                    if ($descuento['categoria'] === 'otras_entregas') {
                        /**
                         * Si es pago de otras entregas
                         */
                        $cckardex = new Kardex($database);
                        $partes = explode('_', $descuento['id_descuento']);
                        $dataKardex = [
                            'cuenta_id' => $descuento['idCuenta'],
                            'forma_pago' => 'otro',
                            'monto_capital' => 0,
                            'monto_interes' => $descuento['interes'],
                            'monto_mora' => $descuento['mora'],
                            'fecha' => $data['fecha'],
                            'numdoc' => $data['numdoc'],
                            'concepto' => "AUTO - COMPRA PRODUCTOS",
                            'otros_entregas' => [
                                [
                                    'id_movimiento' => $partes[1],
                                    'monto' => $descuento['capital'],
                                ]
                            ],
                        ];
                        $datosReturn = $cckardex->registrarPago($dataKardex);
                        // $data['descuentos_json'][$key]['id_nomenclatura'] = $datosReturn['id_cuenta_financiamiento'] ?? null;
                        $data['descuentos_json'][$key]['id_kardex'] = $datosReturn['id_kardex'] ?? null;
                        $data['descuentos_json'][$key]['id_nomenclatura_interes'] = $datosReturn['id_cuenta_interes'] ?? null;
                        $data['descuentos_json'][$key]['id_nomenclatura_mora'] = $datosReturn['id_cuenta_mora'] ?? null;

                        $nomenclaturaOtros = $datosReturn['nomenclaturas_otros'] ?? [];

                        foreach ($nomenclaturaOtros as $keyNomen => $nomenclatura) {
                            if ($nomenclatura['id_movimiento'] == $partes[1]) {
                                $data['descuentos_json'][$key]['id_nomenclatura'] = $nomenclatura['id_nomenclatura'] ?? null;
                                break;
                            }
                        }
                    }

                    if ($descuento['categoria'] === 'prestamos') {
                        /**
                         * Si pago algun prestamo, registro en el modulo de creditos.
                         */

                        $credkarModel = new Credkar($database);
                        $partes = explode('_', $descuento['id_descuento']);
                        $dataCredito = [
                            'cuenta_id' => $partes[1],
                            'forma_pago' => '4',
                            'monto_capital' => $descuento['capital'],
                            'monto_interes' => $descuento['interes'],
                            'monto_mora' => $descuento['mora'],
                            'fecha' => $data['fecha'],
                            'numdoc' => $data['numdoc'],
                            'concepto' => "AUTO - COMPRA PRODUCTOS",
                        ];
                        $datosReturn = $credkarModel->applyPayment($dataCredito);
                        $data['descuentos_json'][$key]['id_nomenclatura'] = $datosReturn['id_cuenta_capital'] ?? null;
                        $data['descuentos_json'][$key]['id_kardex'] = $datosReturn['id_kardex'] ?? null;
                        $data['descuentos_json'][$key]['id_nomenclatura_interes'] = $datosReturn['id_cuenta_interes'] ?? null;
                        $data['descuentos_json'][$key]['id_nomenclatura_mora'] = $datosReturn['id_cuenta_mora'] ?? null;
                    }
                    if ($descuento['categoria'] === 'ahorros') {
                        /**
                         * si pago algun ahorro, registro en el modulo de ahorros.
                         */

                        $ahommovModel = new Ahommov($database);
                        $partes = explode('_', $descuento['id_descuento']);
                        $dataAhorro = [
                            'cuenta_id' => $partes[1],
                            'forma_pago' => 'V',
                            'monto' => $descuento['monto'],
                            'fecha' => $data['fecha'],
                            'numdoc' => $data['numdoc'],
                            'razon' => "DEPOSITO VINCULADO",
                            'concepto' => "AUTO - COMPRA PRODUCTOS",
                        ];
                        $datosReturn = $ahommovModel->applyDeposito($dataAhorro);
                        $data['descuentos_json'][$key]['id_nomenclatura'] = $datosReturn['id_cuenta_contable'] ?? null;
                    }
                    if ($descuento['categoria'] === 'aportaciones') {
                        /**
                         * Si pago alguna aportacion, registro en el modulo de aportaciones.
                         */

                        $aprmovModel = new Aprmov($database);
                        $partes = explode('_', $descuento['id_descuento']);
                        $dataAportacion = [
                            'cuenta_id' => $partes[1],
                            'forma_pago' => 'V',
                            'monto' => $descuento['monto'],
                            'fecha' => $data['fecha'],
                            'numdoc' => $data['numdoc'],
                            'concepto' => "AUTO - COMPRA PRODUCTOS",
                        ];
                        $datosReturn = $aprmovModel->applyDeposito($dataAportacion);
                        $data['descuentos_json'][$key]['id_nomenclatura'] = $datosReturn['id_cuenta_contable'] ?? null;
                    }
                    if ($descuento['categoria'] === 'aux_postumo') {
                        /**
                         * Si pago algun auxilio postumo, registro en el modulo de auxilios postumos.
                         */
                    }
                }
            }

            // Si el estado es 'settled', generar movimientos contables
            if ($data['estado'] === 'settled') {
                $datosAgencia = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
                if (empty($datosAgencia)) {
                    $showmensaje = true;
                    throw new Exception("No se pudo obtener la información de la agencia.");
                }
                $idNomenclaturaCaja = $datosAgencia[0]['id_nomenclatura_caja'];

                if ($data['forma_pago'] === 'banco') {
                    $datosBanco = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$data['id_ctbbancos']]);
                    if (empty($datosBanco)) {
                        $showmensaje = true;
                        throw new Exception("No se pudo obtener la información del banco seleccionado.");
                    }
                    $idNomenclaturaCaja = $datosBanco[0]['id_nomenclatura'];
                }

                $datosCliente = $database->selectColumns('tb_cliente', ['short_name'], 'idcod_cliente=?', [$data['id_cliente']]);
                $nombreCliente = $datosCliente[0]['short_name'] ?? 'Cliente';

                // $camp_numcom = getnumcompdo($idusuario, $database);
                $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $data['fecha']);

                $ctb_diario = array(
                    "numcom" => $camp_numcom,
                    "id_ctb_tipopoliza" => 15,
                    "id_tb_moneda" => 1,
                    "numdoc" => ($data['forma_pago'] === 'banco') ? $data['doc_banco'] : $data['numdoc'],
                    "glosa" => $data['concepto'],
                    "fecdoc" => $data['fecha'],
                    "feccnt" => $data['fecha'],
                    "cod_aux" => "CCP_" . $idCompra,
                    "id_tb_usu" => $idusuario,
                    "karely" => "CCP_" . $idCompra,
                    "id_agencia" => $idagencia,
                    "fecmod" => $hoy2,
                    "estado" => 1,
                    "editable" => 0,
                    "created_by" => $idusuario
                );

                $idDiario = $database->insert("ctb_diario", $ctb_diario);

                /**
                 * MOVIMIENTO CONTABLE DE LOS PRODUCTOS AGREGADOS, DE LADO DEL DEBE
                 */

                foreach ($productosAgrupados as $idProducto => $precioTotal) {
                    $datosProducto = $database->selectColumns('cc_productos', ['id_nomenclatura', 'nombre'], 'id=?', [$idProducto]);
                    if (empty($datosProducto)) {
                        $showmensaje = true;
                        throw new Exception("No se pudo obtener la información del producto con identificacion: " . $idProducto);
                    }
                    $idNomenclaturaProducto = $datosProducto[0]['id_nomenclatura'];
                    $nombreProducto = $datosProducto[0]['nombre'];

                    $ctb_mov = array(
                        "id_ctb_diario" => $idDiario,
                        "id_fuente_fondo" => 1,
                        "id_ctb_nomenclatura" => $idNomenclaturaProducto,
                        "debe" => $precioTotal,
                        "haber" => 0.00,
                    );
                    $database->insert("ctb_mov", $ctb_mov);
                }

                /**
                 * MOVIMIENTO CONTABLE DE LOS DESCUENTOS APLICADOS, DE LADO DEL HABER
                 */

                $totalCostoProductos = array_sum($productosAgrupados);
                $totalDescuentosAplicados = 0;

                if (!empty($data['descuentos_json']) && is_array($data['descuentos_json'])) {
                    foreach ($data['descuentos_json'] as $descuento) {

                        if ($descuento['categoria'] === 'otros') {
                            $idNomenclaturaDescuento = $descuento['id_nomenclatura'] ?? null;
                            if (empty($idNomenclaturaDescuento)) {
                                $showmensaje = true;
                                throw new Exception("No se pudo obtener la información contable del descuento aplicado: " . $descuento['nombre']);
                            }

                            $ctb_mov = array(
                                "id_ctb_diario" => $idDiario,
                                "id_fuente_fondo" => 1,
                                "id_ctb_nomenclatura" => $idNomenclaturaDescuento,
                                "debe" => 0.00,
                                "haber" => $descuento['monto'],
                            );
                            $database->insert("ctb_mov", $ctb_mov);
                            // Log::info("Movimiento contable para descuento otros registrado.");

                            $totalDescuentosAplicados += $descuento['monto'];
                        }

                        if ($descuento['categoria'] === 'financiamientos' || $descuento['categoria'] === 'otras_entregas') {
                            /**
                             * POR PAGO DE LOS FINANCIAMIENTOS QUE APLICO EL CLIENTE
                             */

                            if ($descuento['capital'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['capital'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento financiamientos u otras entregas registrado.");
                            }

                            // Movimiento para cuenta de interés
                            if (!empty($descuento['id_nomenclatura_interes']) && $descuento['interes'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura_interes'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['interes'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento financiamientos u otras entregas registrado.");
                            }

                            // Movimiento para cuenta de mora
                            if (!empty($descuento['id_nomenclatura_mora']) && $descuento['mora'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura_mora'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['mora'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento financiamientos u otras entregas registrado.");
                            }

                            $totalDescuentosAplicados += ($descuento['capital'] + $descuento['interes'] + $descuento['mora']);
                        }


                        if ($descuento['categoria'] === 'prestamos') {
                            /**
                             * Si pago algun prestamo, registro en el modulo de creditos.
                             */
                            if ($descuento['capital'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['capital'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento prestamos registrado.");
                            }

                            // Movimiento para cuenta de interés
                            if (!empty($descuento['id_nomenclatura_interes']) && $descuento['interes'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura_interes'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['interes'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento prestamos registrado.");
                            }

                            // Movimiento para cuenta de mora
                            if (!empty($descuento['id_nomenclatura_mora']) && $descuento['mora'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura_mora'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['mora'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento financiamientos u otras entregas registrado.");
                            }

                            $totalDescuentosAplicados += ($descuento['capital'] + $descuento['interes'] + $descuento['mora']);
                        }
                        if ($descuento['categoria'] === 'ahorros') {
                            /**
                             * si pago algun ahorro, registro en el modulo de ahorros.
                             */
                            if ($descuento['monto'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['monto'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento ahorros registrado.");
                                $totalDescuentosAplicados += $descuento['monto'];
                            }
                        }
                        if ($descuento['categoria'] === 'aportaciones') {
                            /**
                             * Si pago alguna aportacion, registro en el modulo de aportaciones.
                             */
                            if ($descuento['monto'] > 0) {
                                $ctb_mov = array(
                                    "id_ctb_diario" => $idDiario,
                                    "id_fuente_fondo" => 1,
                                    "id_ctb_nomenclatura" => $descuento['id_nomenclatura'],
                                    "debe" => 0.00,
                                    "haber" => $descuento['monto'],
                                );
                                $database->insert("ctb_mov", $ctb_mov);
                                Log::info("Movimiento contable para descuento aportaciones registrado.");
                                $totalDescuentosAplicados += $descuento['monto'];
                            }
                        }
                        if ($descuento['categoria'] === 'aux_postumo') {
                            /**
                             * Si pago algun auxilio postumo, registro en el modulo de auxilios postumos.
                             */
                        }
                    }
                }

                $totalFinalCalculado = $totalCostoProductos - $totalDescuentosAplicados;
                if ($totalFinalCalculado > 0) {
                    $ctb_mov = array(
                        "id_ctb_diario" => $idDiario,
                        "id_fuente_fondo" => 1,
                        "id_ctb_nomenclatura" => $idNomenclaturaCaja,
                        "debe" => 0.00,
                        "haber" => $totalFinalCalculado,
                    );
                    $database->insert("ctb_mov", $ctb_mov);
                }

                if ($data['forma_pago'] === 'banco' && $totalFinalCalculado > 0) {
                    $ctb_chq = array(
                        "id_ctb_diario" => $idDiario,
                        "id_cuenta_banco" => $data['id_ctbbancos'],
                        "numchq" => $data['doc_banco'],
                        "nomchq" => $nombreCliente,
                        "monchq" => $totalFinalCalculado,
                        "modocheque" => 0,
                        "emitido" => "0",
                    );
                    $database->insert("ctb_chq", $ctb_chq);
                }
            }

            $database->commit();
            $status = 1;
            $estadoTexto = $data['estado'] === 'draft' ? 'borrador' : ($data['estado'] === 'pending' ? 'pendiente' : 'liquidada');
            $mensaje = "La compra se ha guardado correctamente";
        } catch (Throwable $e) {
            $database->rollback();
            $status = 0;
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status,
            'idCompra' => $idCompra ?? null,
        ]);
        break;

    case 'cc_grupos_create':
        $showmensaje = false;
        try {
            /**
             * Crear un grupo de cuentas por cobrar
             */
            $data = [
                'nombre' => $_POST['inputs']['nombre'] ?? null,
                'id_nomenclatura' => $_POST['selects']['id_nomenclatura'] ?? null,
                'clientes' => isset($_POST['archivo'][0]) ? json_decode($_POST['archivo'][0], true) : []
            ];

            $rules = [
                'nombre' => 'required|min_length:3|max_length:100',
                'id_nomenclatura' => 'required|numeric|min:1|exists:ctb_nomenclatura,id',
                'clientes' => 'required'
            ];

            $messages = [
                'clientes.required' => 'Debe agregar al menos un cliente al grupo'
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if (empty($data['clientes'])) {
                $showmensaje = true;
                throw new Exception("Debe agregar al menos un cliente al grupo");
            }

            $database->openConnection();

            // Verificar que no exista un grupo con el mismo nombre
            $verification = $database->selectColumns('cc_grupos', ['id'], "nombre=? AND estado='1'", [$data['nombre']]);
            if (!empty($verification)) {
                $showmensaje = true;
                throw new Exception("Ya existe un grupo con este nombre");
            }

            // Verificar que la nomenclatura existe
            $nomenclatura = $database->selectColumns('ctb_nomenclatura', ['id'], 'id=? AND estado=1', [$data['id_nomenclatura']]);
            if (empty($nomenclatura)) {
                $showmensaje = true;
                throw new Exception("La nomenclatura seleccionada no existe o no está activa");
            }

            // Verificar que ningún cliente esté en otro grupo activo
            $cadena = "('" . implode("','", array_map('addslashes', $data['clientes'])) . "')";
            $clientesEnOtrosGrupos = $database->getAllResults(
                "SELECT gc.id_cliente, g.nombre as nombre_grupo, 
                        c.short_name as nombre_cliente
                 FROM cc_grupos_clientes gc
                 INNER JOIN cc_grupos g ON gc.id_grupo = g.id
                 INNER JOIN tb_cliente c ON gc.id_cliente = c.idcod_cliente
                 WHERE gc.id_cliente IN " . $cadena . "
                 AND g.estado = '1'"
            );

            if (!empty($clientesEnOtrosGrupos)) {
                $showmensaje = true;
                $clientesConflicto = array_map(function($item) {
                    return $item['nombre_cliente'] . ' (Grupo: ' . $item['nombre_grupo'] . ')';
                }, $clientesEnOtrosGrupos);
                $mensajeClientes = implode(', ', $clientesConflicto);
                throw new Exception("Los siguientes clientes ya pertenecen a otros grupos activos: " . $mensajeClientes);
            }

            $database->beginTransaction();

            $cc_grupos = [
                'nombre' => $data['nombre'],
                'id_nomenclatura' => $data['id_nomenclatura'],
                'estado' => '1',
                'created_by' => $idusuario,
                'created_at' => $hoy2
            ];
            $idGrupo = $database->insert("cc_grupos", $cc_grupos);

            // Insertar los clientes del grupo
            foreach ($data['clientes'] as $idCliente) {
                $cc_grupos_clientes = [
                    'id_grupo' => $idGrupo,
                    'id_cliente' => $idCliente
                ];
                $database->insert("cc_grupos_clientes", $cc_grupos_clientes);
            }

            $database->commit();
            $status = 1;
            $mensaje = "El grupo se ha creado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                // $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_grupos_update':
        $showmensaje = false;
        try {
            /**
             * Actualizar un grupo de cuentas por cobrar
             */
            $data = [
                'id' => isset($_POST['archivo'][0]) ? $secureID->decrypt($_POST['archivo'][0]) : null,
                'nombre' => $_POST['inputs']['nombre'] ?? null,
                'id_nomenclatura' => $_POST['selects']['id_nomenclatura'] ?? null,
                'clientes' => isset($_POST['archivo'][1]) ? json_decode($_POST['archivo'][1], true) : []
            ];

            $rules = [
                'id' => 'required|numeric|min:1|exists:cc_grupos,id',
                'nombre' => 'required|min_length:3|max_length:100',
                'id_nomenclatura' => 'required|numeric|min:1|exists:ctb_nomenclatura,id',
                'clientes' => 'required'
            ];

            $messages = [
                'clientes.required' => 'Debe agregar al menos un cliente al grupo'
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            if (empty($data['clientes'])) {
                $showmensaje = true;
                throw new Exception("Debe agregar al menos un cliente al grupo");
            }

            $database->openConnection();

            // Verificar que el grupo existe
            $grupoExistente = $database->selectColumns('cc_grupos', ['id'], "id=? AND estado='1'", [$data['id']]);
            if (empty($grupoExistente)) {
                $showmensaje = true;
                throw new Exception("El grupo seleccionado no existe o ya fue eliminado");
            }

            // Verificar que no exista otro grupo con el mismo nombre
            $verification = $database->selectColumns('cc_grupos', ['id'], "nombre=? AND estado='1' AND id!=?", [$data['nombre'], $data['id']]);
            if (!empty($verification)) {
                $showmensaje = true;
                throw new Exception("Ya existe otro grupo con este nombre");
            }

            // Verificar que la nomenclatura existe
            $nomenclatura = $database->selectColumns('ctb_nomenclatura', ['id'], 'id=? AND estado=1', [$data['id_nomenclatura']]);
            if (empty($nomenclatura)) {
                $showmensaje = true;
                throw new Exception("La nomenclatura seleccionada no existe o no está activa");
            }

            // Verificar que ningún cliente esté en otro grupo activo (excluyendo el grupo actual)
            $cadena = "('" . implode("','", array_map('addslashes', $data['clientes'])) . "')";
            $clientesEnOtrosGrupos = $database->getAllResults(
                "SELECT gc.id_cliente, g.nombre as nombre_grupo, 
                        c.short_name as nombre_cliente
                 FROM cc_grupos_clientes gc
                 INNER JOIN cc_grupos g ON gc.id_grupo = g.id
                 INNER JOIN tb_cliente c ON gc.id_cliente = c.idcod_cliente
                 WHERE gc.id_cliente IN " . $cadena . "
                 AND g.estado = '1'
                 AND g.id != ?",
                [$data['id']]
            );

            if (!empty($clientesEnOtrosGrupos)) {
                $showmensaje = true;
                $clientesConflicto = array_map(function($item) {
                    return $item['nombre_cliente'] . ' (Grupo: ' . $item['nombre_grupo'] . ')';
                }, $clientesEnOtrosGrupos);
                $mensajeClientes = implode(', ', $clientesConflicto);
                throw new Exception("Los siguientes clientes ya pertenecen a otros grupos activos: " . $mensajeClientes);
            }

            $database->beginTransaction();

            $cc_grupos = [
                'nombre' => $data['nombre'],
                'id_nomenclatura' => $data['id_nomenclatura'],
                'updated_by' => $idusuario,
                'updated_at' => $hoy2
            ];
            $database->update("cc_grupos", $cc_grupos, 'id=?', [$data['id']]);

            // Eliminar los clientes actuales del grupo
            $database->delete("cc_grupos_clientes", 'id_grupo=?', [$data['id']]);

            // Insertar los nuevos clientes del grupo
            foreach ($data['clientes'] as $idCliente) {
                $cc_grupos_clientes = [
                    'id_grupo' => $data['id'],
                    'id_cliente' => $idCliente
                ];
                $database->insert("cc_grupos_clientes", $cc_grupos_clientes);
            }

            $database->commit();
            $status = 1;
            $mensaje = "El grupo se ha actualizado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                // $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'cc_grupos_delete':
        $showmensaje = false;
        try {
            /**
             * Eliminar un grupo (soft delete)
             */
            $data = [
                'id' => isset($_POST['archivo'][0]) ? $secureID->decrypt($_POST['archivo'][0]) : null
            ];

            $rules = [
                'id' => 'required|numeric|min:1|exists:cc_grupos,id'
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();

            // Verificar que el grupo existe
            $grupoExistente = $database->selectColumns('cc_grupos', ['id'], "id=? AND estado='1'", [$data['id']]);
            if (empty($grupoExistente)) {
                $showmensaje = true;
                throw new Exception("El grupo seleccionado no existe o ya fue eliminado");
            }

            $database->beginTransaction();

            $cc_grupos = [
                'estado' => '0',
                'deleted_by' => $idusuario,
                'deleted_at' => $hoy2
            ];
            $database->update("cc_grupos", $cc_grupos, 'id=?', [$data['id']]);

            $database->commit();
            $status = 1;
            $mensaje = "El grupo ha sido eliminado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                // $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;
}
