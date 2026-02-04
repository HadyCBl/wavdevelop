<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';


$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);


ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

include_once '../envia_correo.php';
$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idagencia = $_SESSION['id_agencia'];
$tempbancos = 0;

use Micro\Helpers\Log;
use Luecano\NumeroALetras\NumeroALetras;
use Micro\Exceptions\SoftException;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Helpers\Beneq;

$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

use function PHPSTORM_META\type;

function valida($valida, $op, $conexion)
{
    switch ($op) {
        case 1:
            if (!$valida) {
                echo json_encode(["Error al ingresar los datos", '0']);
                return;
            }
            break;
        case 2:
            if (!$valida->execute()) {
                $conexion->rollback();
                echo json_encode(["Error al ingresar los datos", '0']);
                return;
            }
            break;
    }
}

$condi = $_POST["condi"];

switch ($condi) {
    case 'ins_otrGasto': //Insertar otros gastos
        $input = $_POST['inputs'];
        $select = $_POST['selects'];
        $codUsu = $_POST['archivo'];

        // Crear la consulta SQL con marcadores de posición '?'
        $sql = "INSERT INTO otr_tipo_ingreso (id_nomenclatura, nombre_gasto, grupo, tipo, tipoLinea, created_by, created_at) 
                VALUES (?,?,?,?,?,?,?)";

        // Crear una sentencia preparada
        $stmt = $conexion->prepare($sql);

        if ($stmt) {
            // Vincular parámetros y valores a los marcadores de posición
            // "ississs" - integer, string, string, integer, string, string, string
            $stmt->bind_param(
                "ississs",
                $input[1],    // id_nomenclatura (i)
                $input[0],    // nombre_gasto (s)
                $input[2],    // grupo (s)
                $select[0],   // tipo (i)
                $select[1],   // tipoLinea (s)
                $codUsu,      // created_by (s)
                $hoy2         // created_at (s)
            );

            try {
                // Ejecutar la consulta preparada para insertar los datos
                if ($stmt->execute()) {
                    echo json_encode(["Registro exitoso", "1"]);
                } else {
                    echo json_encode(["Error al realizar el registro: " . $stmt->error, "0"]);
                }
            } catch (Exception $e) {
                echo json_encode(["Error: " . $e->getMessage(), "0"]);
            } finally {
                // Cerrar la sentencia preparada
                $stmt->close();
            }
        } else {
            echo json_encode(["Error en la consulta: " . $conexion->error, "0"]);
        }

        // Cerrar la conexión a la base de datos
        $conexion->close();
        break;

    case 'act_otrGasto':
        $input = $_POST['inputs'];
        $select = $_POST['selects'];
        $codUsu = $_POST['archivo'];

        // Crear la consulta SQL con marcadores de posición '?'
        $sql = "UPDATE otr_tipo_ingreso SET id_nomenclatura = ?, nombre_gasto = ?, grupo = ?, tipo = ?, tipoLinea = ?, updated_by = ?, updated_at = ? WHERE id = ?";
        // Crear una sentencia preparada
        $stmt = $conexion->prepare($sql);

        if ($stmt) {
            // Vincular parámetros y valores a los marcadores de posición
            // Asegurarnos que tenemos el número correcto de parámetros
            $stmt->bind_param(
                "issisiss",
                $input[1],           // id_nomenclatura
                $input[2],           // nombre_gasto
                $input[3],           // grupo
                $select[0],          // tipo
                $select[1],          // tipoLinea
                $codUsu,            // updated_by
                $hoy2,              // updated_at
                $input[0]           // id (WHERE)
            );

            // Ejecutar la consulta preparada para actualizar los datos
            if ($stmt->execute()) {
                echo json_encode(["Registro actualizado exitosamente", '1']);
                return;
            } else {
                echo json_encode(["Error al actualizar el registro", '0']);
                return;
            }
            // Cerrar la sentencia preparada
            $stmt->close();
        } else {
            echo json_encode(["Error en la consulta: " . $conexion->error, '0']);
            return;
        }
        // Cerrar la conexión a la base de datos
        $conexion->close();
        break;

    case 'eli_otrGasto':
        $id = $_POST['ideliminar'];
        $codUsu = $_POST['archivo'];
        $estado = 0;

        // Crear la consulta SQL con marcadores de posición '?'
        $sql = "UPDATE otr_tipo_ingreso SET estado = ?, deleted_by = ?, deleted_at = ? WHERE id = ?";
        // Crear una sentencia preparada
        $stmt = $conexion->prepare($sql);

        if ($stmt) {
            // Vincular parámetros y valores a los marcadores de posición
            $stmt->bind_param("iisi", $estado, $codUsu, $hoy2, $id);

            // Ejecutar la consulta preparada para insertar los datos
            if ($stmt->execute()) {
                echo json_encode(["Registro exitoso ", '1']);
                return;
            } else {
                echo json_encode(["Error al realizar el registro ", '0']);
                return;
            }
            // Cerrar la sentencia preparada
            $stmt->close();
        } else {
            echo "Error en la consulta: ";
        }
        // Cerrar la conexión a la base de datos
        $conexion->close();
        // echo json_encode(["Ingreso correctamente ".$codUsu, '0']);
        // return;
        break;
    case 'cre_otrRecibo':
        //+++++++++++++++++++++++++++++++++++++++++++++++ 
        $status = 0;
        try {
            /**
             * obtiene(
             * ['fecha', 'recibo', 'cliente', 'descrip', 'banco_num_referencia', 'banco_beneficiario_cheque', 'banco_num_cheque', 'banco_negociable', 'banco_fecha'],
             * ['idusuario', 'listcuent', 'idagencia', 'tipdoc'],
             * ['opcalculo', 'tipoMovBanco'],
             * 'cre_otrRecibo',
             * '0',
             * [datosRecibo, getValFelSwitch()
             */
            $datos = [
                'fecharecibo' => $_POST['inputs'][0] ?? null,
                'recibo' => $_POST['inputs'][1] ?? null,
                'nomcliente' => $_POST['inputs'][2] ?? null,
                'descrip' => $_POST['inputs'][3] ?? null,
                'numReferencia' => $_POST['inputs'][4] ?? null,
                'banco_beneficiario' => $_POST['inputs'][5] ?? null,
                'numCheque' => $_POST['inputs'][6] ?? null,
                'negociable' => $_POST['inputs'][7] ?? null,
                'fechaBanco' => $_POST['inputs'][8] ?? null,

                //selects
                'userSeleccionado' => $_POST['selects'][0] ?? null,
                'idCuentaBanco' => $_POST['selects'][1] ?? null,
                'selectAgency' => $_POST['selects'][2] ?? null,
                'origenFondos' => $_POST['selects'][3] ?? null,

                //radios
                'tipoAdicional' => $_POST['radios'][0] ?? null,
                'tipoMovBanco' => $_POST['radios'][1] ?? null,

                //archivo
                'datosMatriz' => $_POST['archivo'][0] ?? [],
                'felSwitch' => $_POST['archivo'][2] ?? 0,
            ];

            $rules = [
                'fecharecibo' => 'required|date',
                'recibo' => 'required|string|max_length:45',
                'descrip' => 'required|string|max_length:500',
                'origenFondos' => 'required',
                'datosMatriz' => 'required|array',
            ];

            if ($datos['origenFondos'] == 'banco') {
                $rules['idCuentaBanco'] = 'required';
                $rules['fechaBanco'] = 'required|date';
                if ($datos['tipoMovBanco'] == 'nota') {
                    $rules['numReferencia'] = 'required|string|max_length:50';
                }
                if ($datos['tipoMovBanco'] == 'cheque') {
                    $rules['banco_beneficiario'] = 'required|max_length:100';
                    $rules['numCheque'] = 'required|string|max_length:50';
                }
            }

            $datos['nomcliente'] = ($datos['tipoAdicional'] == 2) ? trim($datos['nomcliente']) : '';

            if ($datos['tipoAdicional'] == 3) {
                $rules['userSeleccionado'] = 'required|exists:tb_usuario,id_usu';
                $datos['nomcliente'] = $datos['userSeleccionado'];
            }
            if ($datos['tipoAdicional'] == 4) {
                $rules['selectAgency'] = 'required|exists:tb_agencia,id_agencia';
                $datos['nomcliente'] = $datos['selectAgency'];
            }

            $validator = Validator::make($datos, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            $database->openConnection();

            //COMPROBAR CIERRE DE CAJA
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                throw new SoftException($cierre_caja[1]);
            }
            //COMPROBAR CIERRE DE MES CONTABLE
            $cierre_mes = comprobar_cierrePDO($_SESSION['id'], $datos['fecharecibo'], $database);
            if ($cierre_mes[0] == 0) {
                throw new SoftException($cierre_mes[1]);
            }

            if ($datos['tipoAdicional'] == 3 || $datos['tipoAdicional'] == 4) {
                $tablaId = ($datos['tipoAdicional'] == 3) ? "tb_usuario" : "tb_agencia";
                $columnaId = ($datos['tipoAdicional'] == 3) ? "id_usu" : "id_agencia";
                $searchId = ($datos['tipoAdicional'] == 3) ? $datos['userSeleccionado'] : $datos['selectAgency'];

                $searchAgency = $database->selectColumns($tablaId, ['id_agencia'], "$columnaId=?", [$searchId]);
                if (empty($searchAgency)) {
                    throw new SoftException("No se encontró la agencia del usuario o la agencia seleccionada");
                }
                $id_agencia_new = $searchAgency[0]['id_agencia'];
            } else {
                $id_agencia_new = $idagencia;
            }

            $cuentaContable = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$id_agencia_new]);
            if (empty($cuentaContable)) {
                throw new SoftException("No se encontró la cuenta contable de caja de la agencia");
            }

            $cuentaContableOrigen = $cuentaContable[0]['id_nomenclatura_caja'];

            if ($datos['origenFondos'] == 'banco') {
                $cuentaContable = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$datos['idCuentaBanco']]);
                if (empty($cuentaContable)) {
                    throw new SoftException("No se encontró la cuenta contable de la cuenta bancaria seleccionada");
                }

                $cuentaContableOrigen = $cuentaContable[0]['id_nomenclatura'];
            }

            /**
             * AGREGADO PARA TIPOS DE DOCUMENTOS DIFERENTES CREADOS POR EL USUARIO
             */
            if (is_numeric($datos['origenFondos'])) {
                $tiposDocumentosTransacciones = $database->selectColumns("tb_documentos_transacciones", ['id_cuenta_contable', 'tipo_dato'], "id=?", [$datos['origenFondos']]);
                if (empty($tiposDocumentosTransacciones)) {
                    throw new SoftException("No se encontró el tipo de documento");
                }

                $cuentaContableOrigen = $tiposDocumentosTransacciones[0]['id_cuenta_contable'];
            }

            $database->beginTransaction();

            /**
             * INSERTAR EL MOVIMIENTO DE PAGO
             */
            $idOtrPago = $database->insert('otr_pago', [
                'fecha' => $datos['fecharecibo'],
                'recibo' => $datos['recibo'],
                'cliente' => $datos['nomcliente'],
                'descripcion' => $datos['descrip'],
                'agencia' => $id_agencia_new,
                'estado' => 1,
                'tipoadicional' => $datos['tipoAdicional'],
                'formaPago' => $datos['origenFondos'],
                'id_ctbbanco' => ($datos['origenFondos'] == 'banco') ? $datos['idCuentaBanco'] : null,
                'doc_banco' => ($datos['origenFondos'] == 'banco') ? (($datos['tipoMovBanco'] == 'nota') ? $datos['numReferencia'] : $datos['numCheque']) : null,
                'fecha_banco' => ($datos['origenFondos'] == 'banco') ? $datos['fechaBanco'] : null,
                'user_assigned' => ($datos['tipoAdicional'] == 3) ? $datos['userSeleccionado'] : null,
                'created_by' => $idusuario,
                'created_at' => $hoy2,
            ]);

            /**
             * INSERCION EN EL LIBRO DIARIO
             */
            // $numpartida = getnumcomPDO($idusuario, $database);
            $numpartida = Beneq::getNumcom($database, $idusuario, $id_agencia_new, $datos['fecharecibo']);
            $glosa = mb_strtoupper($datos['descrip'], 'utf-8');
            $data = [
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 8,
                'id_tb_moneda' => 1,
                'numdoc' => $datos['recibo'],
                'glosa' => $glosa,
                'fecdoc' => ($datos['origenFondos'] == 'banco') ? $datos['fechaBanco'] : $datos['fecharecibo'],
                'feccnt' =>  $datos['fecharecibo'],
                'cod_aux' => "OTR-" . $idOtrPago,
                'id_tb_usu' => $idusuario,
                'karely' => "OTR-" . $idOtrPago,
                'id_agencia' => $id_agencia_new,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 0,
                'created_by' => $idusuario,
            ];

            $id_ctb_diario = $database->insert('ctb_diario', $data);

            /**
             * INSERTAR LOS MOVIMIENTOS DE PAGO EN CONTABILIDAD
             */

            /**
             * INSERCION DE REGISTROS SIN FEL
             */
            foreach ($datos['datosMatriz'] as $key => $factura) {
                Log::info("Procesando factura", $factura);
                if ($factura['tipo'] === 'FEL') {
                    // Procesar factura FEL con sus campos
                    $numDTE = $factura['numDTE'];
                    $serie = $factura['serie'];

                     $cv_otros_movimientos = array(
                        // 'id_otro' => $idOtrPago,
                        'numero_dte' => $factura['numDTE'],
                        'fecha' => $factura['fecha'],
                        'numero_serie' => $factura['serie'],
                        'concepto' => $factura['concepto'] ?? '',
                        'id_receptor' => $factura['emisorId'],
                    );

                    $idFEL = $database->insert('cv_otros_movimientos', $cv_otros_movimientos);
                }

                // Procesar items (tanto FEL como sin FEL)
                foreach ($factura['items'] as $item) {
                    /**
                     * PARA TIPOS EGRESOS
                     */
                    $otr_pago_mov = [
                        'id_otr_tipo_ingreso' => $item['idG'],
                        'id_otr_pago' => $idOtrPago,
                        'monto' => $item['monto'],
                        'id_fel' => $idFEL ?? null,
                        // 'estado' => 1,
                        'created_by' => $idusuario,
                        'created_at' => $hoy2,

                    ];

                    $idMovimientoPago = $database->insert('otr_pago_mov', $otr_pago_mov);

                    $nomenclatura = $database->selectColumns('otr_tipo_ingreso', ['id_nomenclatura'], 'id=?', [$item['idG']]);
                    if (empty($nomenclatura)) {
                        throw new SoftException("No se encontró la cuenta contable para el egreso seleccionado: " . $item['descripcion']);
                    }

                    $ctb_mov = [
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $nomenclatura[0]['id_nomenclatura'],
                        'debe' => $item['monto'],
                        'haber' => 0,
                    ];

                    $database->insert('ctb_mov', $ctb_mov);

                    foreach ($item['impuestos'] as $impKey => $impValue) {
                        if ($impKey == 'iva12' && $impValue > 0) {
                            $nomenclaturaImp = $database->selectColumns('ctb_parametros_general', ['id_ctb_nomenclatura'], 'id_tipo=?', [8]);
                            if (empty($nomenclaturaImp)) {
                                throw new SoftException("No se encontró la cuenta contable para el IVA 12%");
                            }

                            $ctb_mov_imp = [
                                'id_ctb_diario' => $id_ctb_diario,
                                'id_fuente_fondo' => 1,
                                'id_ctb_nomenclatura' => $nomenclaturaImp[0]['id_ctb_nomenclatura'],
                                'debe' => $impValue,
                                'haber' => 0,
                            ];

                            $database->insert('ctb_mov', $ctb_mov_imp);

                            $impuestosMov = array(
                                'id_tipo' => 8,
                                'monto' => $impValue,
                                'id_movimiento' => $idMovimientoPago,
                            );

                            $idMovimientoImpuesto = $database->insert('otr_pago_mov_impuestos', $impuestosMov);
                        }
                        if ($impKey == 'combustible' && isset($impValue['monto']) && $impValue['monto'] > 0) {
                            $nomenclaturaImp = $database->selectColumns('ctb_parametros_general', ['id_ctb_nomenclatura'], 'id_tipo=?', [9]);
                            if (empty($nomenclaturaImp)) {
                                throw new SoftException("No se encontró la cuenta contable para el Impuesto de Combustible");
                            }

                            $ctb_mov_imp = [
                                'id_ctb_diario' => $id_ctb_diario,
                                'id_fuente_fondo' => 1,
                                'id_ctb_nomenclatura' => $nomenclaturaImp[0]['id_ctb_nomenclatura'],
                                'debe' => $impValue['monto'],
                                'haber' => 0,
                            ];

                            $database->insert('ctb_mov', $ctb_mov_imp);

                            $impuestosMov = array(
                                'id_tipo' => 9,
                                'monto' => $impValue['monto'],
                                'id_movimiento' => $idMovimientoPago,
                            );

                            $idMovimientoImpuesto = $database->insert('otr_pago_mov_impuestos', $impuestosMov);
                        }
                    }
                }
            }
            
            /**
             * MOVIMIENTO EN EL HABER, QUE ES EL TOTAL DE LOS EGRESOS
             */

            $montoTotal = array_sum(array_map(function ($factura) {
                return array_sum(array_column($factura['items'], 'montoTotal'));
            }, $datos['datosMatriz']));

            $database->insert('ctb_mov', [
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentaContableOrigen,
                'debe' => 0,
                'haber' => $montoTotal,
            ]);


            if ($datos['origenFondos'] == 'banco') {
                if ($datos['tipoMovBanco'] == 'nota') {
                    $database->insert('ctb_ban_mov', [
                        'id_ctb_diario' => $id_ctb_diario,
                        'destino' => '-',
                        'numero' => $datos['numReferencia'],
                        'fecha' => $datos['fechaBanco'],
                    ]);
                }
                if ($datos['tipoMovBanco'] == 'cheque') {
                    $database->insert('ctb_chq', [
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_cuenta_banco' => $datos['idCuentaBanco'],
                        'numchq' => $datos['numCheque'],
                        'nomchq' => $datos['banco_beneficiario'],
                        'monchq' => $montoTotal,
                        'modocheque' => $datos['negociable'],
                        'emitido' => 0,
                    ]);
                }
            }

            $database->commit();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (SoftException $e) {
            $database->rollback();
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            $database->rollback();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status, ($idOtrPago ?? 0));
        echo json_encode($opResult);
        break;
    case 'cre_otrIngreso':

        $status = 0;
        try {
            /**
             * ['fecha', 'recibo', 'cliente', 'descrip', 'banco_fecha', 'banco_num_referencia'], ['idusuario', 'idagencia', 'listcuent', 'tipdoc'], ['opcalculo'],[matriz]
             */
            $data = [
                'fecha' => $_POST['inputs'][0] ?? null,
                'num_documento' => $_POST['inputs'][1] ?? null,
                'cliente' => $_POST['inputs'][2] ?? null,
                'concepto' => $_POST['inputs'][3] ?? 0,
                'banco_fecha' => $_POST['inputs'][4] ?? '',
                'banco_numdoc' => $_POST['inputs'][5] ?? '',
                'id_usuario' => $_POST['selects'][0] ?? '',
                'id_agencia' => $_POST['selects'][1] ?? null,
                'id_cuentabanco' => $_POST['selects'][2] ?? null,
                'forma_pago' => $_POST['selects'][3] ?? null,
                'tipoadicional' => $_POST['radios'][0] ?? null,
                'matriz' => $_POST['archivo'][0] ?? [],
            ];

            // Log::info("creando recibo de ingresos ", $data);

            $rules = [
                'fecha' => 'required|date',
                'num_documento' => 'required|string|max_length:50',
                'concepto' => 'required|string|max_length:255',
                'forma_pago' => 'required',
                'matriz' => 'required|array',
            ];

            if ($data['forma_pago'] == 'banco') {
                $rules['id_cuentabanco'] = 'required';
                $rules['banco_numdoc'] = 'required|string|max_length:50';
                $rules['banco_fecha'] = 'required|date';
            }

            if ($data['tipoadicional'] == 2) {
                $rules['cliente'] = 'required|string|max_length:100';
            }
            if ($data['tipoadicional'] == 3) {
                $rules['id_usuario'] = 'required|min:1';
            }
            if ($data['tipoadicional'] == 4) {
                $rules['id_agencia'] = 'required|min:1';
            }

            $messages = [
                'matriz.required' => 'Debe agregar al menos un movimiento',
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            $datoadicional = "";
            if ($data['tipoadicional'] == 2) {
                $datoadicional = $data['cliente'];
            }
            if ($data['tipoadicional'] == 3) {
                $datoadicional = $data['id_usuario'];
            }
            if ($data['tipoadicional'] == 4) {
                $datoadicional = $data['id_agencia'];
            }

            $database->openConnection();

            // Comprobar cierre de caja
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                throw new SoftException($cierre_caja[1]);
            }

            // Obtener ID de agencia
            if ($data['tipoadicional'] == 3 || $data['tipoadicional'] == 4) {
                $tablaId = ($data['tipoadicional'] == 3) ? "tb_usuario" : "tb_agencia";
                $columnaId = ($data['tipoadicional'] == 3) ? "id_usu" : "id_agencia";
                $searchId = ($data['tipoadicional'] == 3) ? $data['id_usuario'] : $data['id_agencia'];

                $searchAgency = $database->selectColumns($tablaId, ['id_agencia'], "$columnaId=?", [$searchId]);
                if (empty($searchAgency)) {
                    throw new SoftException("No se encontró la agencia del usuario o la agencia seleccionada");
                }
                $id_agencia_new = $searchAgency[0]['id_agencia'];
            } else {
                $id_agencia_new = $idagencia;
            }

            $database->beginTransaction();

            // Insertar en otr_pago
            $idOtrPago = $database->insert('otr_pago', [
                'fecha' => $data['fecha'],
                'recibo' => $data['num_documento'],
                'cliente' => $datoadicional,
                'descripcion' => $data['concepto'],
                'agencia' => $id_agencia_new,
                'estado' => 1,
                'tipoadicional' => $data['tipoadicional'],
                'formaPago' => $data['forma_pago'],
                'id_ctbbanco' => ($data['forma_pago'] == 'banco') ? $data['id_cuentabanco'] : null,
                'doc_banco' => ($data['forma_pago'] == 'banco') ? $data['banco_numdoc'] : null,
                'fecha_banco' => ($data['forma_pago'] == 'banco') ? $data['banco_fecha'] : null,
                'user_assigned' => ($data['tipoadicional'] == 3) ? $data['id_usuario'] : null,
                'created_by' => $idusuario,
                'created_at' => $hoy2,
            ]);

            // Insertar movimientos
            foreach ($data['matriz'] as $movimiento) {
                $database->insert('otr_pago_mov', [
                    'id_otr_tipo_ingreso' => $movimiento[0],
                    'id_otr_pago' => $idOtrPago,
                    'monto' => $movimiento[1],
                    'created_by' => $idusuario,
                    'created_at' => $hoy2
                ]);
            }

            // Inserción en libro diario
            // $numpartida = getnumcomPDO($idusuario, $database);
            $numpartida = Beneq::getNumcom($database, $idusuario, $id_agencia_new, $data['fecha']);
            $glosa = mb_strtoupper($data['concepto'], 'utf-8');

            $id_ctb_diario = $database->insert('ctb_diario', [
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 8,
                'id_tb_moneda' => 1,
                'numdoc' => $data['num_documento'],
                'glosa' => $glosa,
                'fecdoc' => ($data['forma_pago'] == 'banco') ? $data['banco_fecha'] : $data['fecha'],
                'feccnt' => $data['fecha'],
                'cod_aux' => "OTR-" . $idOtrPago,
                'id_tb_usu' => ($data['tipoadicional'] == 3) ? $data['id_usuario'] : $idusuario,
                'karely' => "OTR-" . $idOtrPago,
                'id_agencia' => $id_agencia_new,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 0,
                'created_by' => $idusuario
            ]);

            // Obtener cuenta de caja
            if ($data['forma_pago'] == 'banco') {
                $cuentaCaja = $database->selectColumns('ctb_bancos', ['id_nomenclatura AS id_nomenclatura_caja'], 'id=?', [$data['id_cuentabanco']]);
            } else {
                $cuentaCaja = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$id_agencia_new]);
            }

            if (empty($cuentaCaja)) {
                throw new SoftException("No se encontró la cuenta contable del origen de fondos");
            }
            // Insertar movimiento de caja
            $database->insert('ctb_mov', [
                'id_ctb_diario' => $id_ctb_diario,
                // 'numcom' => $numpartida,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentaCaja[0]['id_nomenclatura_caja'],
                'debe' =>  array_sum(array_column($data['matriz'], 1)),
                'haber' => 0
            ]);

            // Procesar movimientos contables
            foreach ($data['matriz'] as $movimiento) {
                // Obtener cuenta contable del otro ingreso
                $cuentaOtroIngreso = $database->selectColumns('otr_tipo_ingreso', ['id_nomenclatura'], 'id=?', [$movimiento[0]]);
                if (empty($cuentaOtroIngreso)) {
                    throw new SoftException("No se encontró la cuenta contable para el otro ingreso");
                }

                // Insertar movimiento de otro ingreso
                $database->insert('ctb_mov', [
                    'id_ctb_diario' => $id_ctb_diario,
                    // 'numcom' => $numpartida,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaOtroIngreso[0]['id_nomenclatura'],
                    'debe' => 0,
                    'haber' =>  $movimiento[1]
                ]);
            }

            $database->commit();
            $mensaje = "Registro insertado correctamente";
            $status = 1;
        } catch (SoftException $e) {
            $database->rollback();
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            $database->rollback();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status, $idOtrPago ?? 0]);
        break;

    case 'act_otrRecibo':
        $arch = $_POST['archivo'];
        $input = $_POST['inputs'];
        $mensaje_error = "";

        //comprobar
        $stmt = $conexion->prepare("SELECT CAST(created_at AS DATE) AS created_at, created_by FROM otr_pago WHERE id = ?");
        if (!$stmt) {
            echo json_encode(["Error en la consulta: " . $conexion->error, '0']);
            return;
        }
        $stmt->bind_param("s", $input[0]);
        if (!$stmt->execute()) {
            echo json_encode(["Error al ejecutar la consulta: " . $stmt->error, '0']);
            return;
        }

        $result = $stmt->get_result();
        $aux = $result->fetch_assoc();
        $fechaaux4 = $aux['created_at'];
        $usuario4 = $aux['created_by'];

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $fechaaux4);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }

        if ($cierre_caja[0] == 8) {
            if ($usuario4 != $arch[0]) {
                echo json_encode(['El usuario creador del registro no coincide con el que quiere editar, no es posible completar la acción', '0']);
                return;
            }
        }

        $conexion->autocommit(FALSE);
        try {
            //Primera consulta para otr pago 
            $sql = "UPDATE otr_pago SET fecha = ?, recibo = ?, cliente = ?, descripcion = ?, updated_by = ?, updated_at = ? WHERE id = ?";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                echo json_encode(["Error al ingresar los datos", '0']);
                return;
            }
            $stmt->bind_param("ssssisi", $input[1], $input[2], $input[3], $input[4], $arch[0], $hoy2, $input[0]);
            if (!$stmt->execute()) {
                $conexion->rollback();
                echo json_encode(["Error al ingresar los datos", '0']);
                return;
            }

            //Editar en movimiento contables
            $glosa = mb_strtoupper($input[4], 'utf-8');
            $data = array(
                'numdoc' => $input[2],
                'glosa' => $glosa,
                'fecdoc' => $input[1],
                'feccnt' => $input[1],
                'updated_by' => $arch[0],
                'updated_at' => $hoy2,

            );
            $id = "OTR-" . $input[0];
            //metodos de actualizacion
            // Columnas a actualizar
            $setCols = [];
            foreach ($data as $key => $value) {
                $setCols[] = "$key = ?";
            }
            $setStr = implode(', ', $setCols);
            $stmt3 = $conexion->prepare("UPDATE ctb_diario SET $setStr WHERE cod_aux = ?");
            if (!$stmt3) {
                throw new ErrorException("Error en la consulta 2: " . $conexion->error);
            }
            // Obtener los valores del array de datos
            $values = array_values($data);
            // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
            $values[] = $id; // Agregar ID al final
            $types = str_repeat('s', count($values));
            // Vincular los parámetros
            $stmt3->bind_param($types, ...$values);
            if (!$stmt3->execute()) {
                throw new ErrorException("Error en la ejecucion de la consulta 2: " . $stmt3->error);
            }

            $conexion->commit();
            echo json_encode(["Los datos se actualizaron con éxito", '1', $input[0]]);
        } catch (\ErrorException $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            $conexion->rollback();
            echo json_encode([$mensaje_error, '0']);
        }
        $stmt->close();
        $conexion->close();
        break;
    case 'eli_otrRecibo':
        $ideliminar = $_POST['ideliminar'];
        $archivo = $_POST['archivo'];

        $conexion->autocommit(FALSE);
        try {
            //MOVIMIENTO EN CONTA
            $data = array(
                'estado' => 0,
                'deleted_by' => $archivo[0],
                'deleted_at' => $hoy2
            );
            $id = "OTR-" . $ideliminar;
            // Columnas a actualizar
            $setCols = [];
            foreach ($data as $key => $value) {
                $setCols[] = "$key = ?";
            }
            $setStr = implode(', ', $setCols);
            $stmt = $conexion->prepare("UPDATE ctb_diario SET $setStr WHERE cod_aux = ?");
            if (!$stmt) {
                throw new ErrorException("Error en la consulta 1: " . $conexion->error);
            }
            // Obtener los valores del array de datos
            $values = array_values($data);
            // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
            $values[] = $id; // Agregar ID al final
            $types = str_repeat('s', count($values));
            // Vincular los parámetros
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                throw new ErrorException("Error en la ejecucion de la consulta 1: " . $stmt->error);
            }

            // REGISTRO DE PAGO EN RECIBOS
            $data = array(
                'estado' => 0,
                'deleted_by' => $archivo[0],
                'deleted_at' => $hoy2
            );
            $id = $ideliminar;
            // Columnas a actualizar
            $setCols = [];
            foreach ($data as $key => $value) {
                $setCols[] = "$key = ?";
            }
            $setStr = implode(', ', $setCols);
            $stmt = $conexion->prepare("UPDATE otr_pago SET $setStr WHERE id = ?");
            if (!$stmt) {
                throw new ErrorException("Error en la consulta 2: " . $conexion->error);
            }
            // Obtener los valores del array de datos
            $values = array_values($data);
            // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
            $values[] = $id; // Agregar ID al final
            $types = str_repeat('s', count($values));
            // Vincular los parámetros
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                throw new ErrorException("Error en la ejecucion de la consulta 2: " . $stmt->error);
            }
            $conexion->commit();
            echo json_encode(["Registro eliminando correctamente", '1']);
        } catch (\ErrorException $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            $conexion->rollback();
            echo json_encode([$mensaje_error, '0']);
        }
        $conexion->close();
        break;
    case 'eli_otrGasto1':
        $id = $_POST['ideliminar'];

        //comprobar
        $stmt = $conexion->prepare("SELECT CAST(ot.created_at AS DATE) AS created_at, ot.created_by FROM otr_pago ot INNER JOIN otr_pago_mov opm ON opm.id_otr_pago = ot.id WHERE opm.id = ?");
        if (!$stmt) {
            echo json_encode(["Error en la consulta: " . $conexion->error, '0']);
            return;
        }
        $stmt->bind_param("s", $id);
        if (!$stmt->execute()) {
            echo json_encode(["Error al ejecutar la consulta: " . $stmt->error, '0']);
            return;
        }

        $result = $stmt->get_result();
        $aux = $result->fetch_assoc();
        $fechaaux4 = $aux['created_at'];
        $usuario4 = $aux['created_by'];

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $fechaaux4);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }

        $conexion->autocommit(false);
        //Primera consulta para otr pago 
        $sql = "DELETE FROM otr_pago_mov WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            echo json_encode(["Error al ingresar los datos", '0']);
            return;
        }
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            $conexion->rollback();
            echo json_encode(["Error al ingresar los datos", '0']);
            return;
        }
        //Obtener la ID  de la consulta anterior 
        $conexion->commit();
        echo json_encode(["Gasto eliminado ", '1']);
        $stmt->close();
        $conexion->close();
        break;
    case 'cargar_imagen_ingreso': {
            //OBTENER CODIGO DE CLIENTE
            $ccodimage = $_POST['codimage'];
            $salida = "../../../"; //SUDOMINIOS PROPIOS
            //$salida = "../../../"; // DOMINIO PRINCIPAL CON CARPETAS
            $queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
            INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
            $infoi[] = [];
            $j = 0;
            while ($fil = mysqli_fetch_array($queryins)) {
                $infoi[$j] = $fil;
                $j++;
            }
            if ($j == 0) {
                echo json_encode(['No se encuentra la ruta de la organizacion', '0']);
                return;
            }
            $folderprincipal = $infoi[0]['folder'];
            // $entrada = "imgcoope.sotecprotech.com/" . $folderprincipal . "/" . $ccodcli;
            $entrada = "imgcoope.microsystemplus.com/otrosingresos/" . $folderprincipal . "/" . $ccodimage;
            $rutaEnServidor = $salida . $entrada;
            $extensiones = ["jpg", "jpeg", "pjpeg", "png", "gif", "pdf"];
            foreach ($extensiones as $key => $value) {
                if (file_exists($rutaEnServidor . "/" . $ccodimage . "." . $value)) {
                    unlink($rutaEnServidor . "/" . $ccodimage . "." . $value);
                }
            }
            //comprobar si existe la ruta, si no, se crea
            if (!is_dir($rutaEnServidor)) {
                mkdir($rutaEnServidor, 0777, true);
            }

            //comprobar si se subio una imagen
            if (is_uploaded_file($_FILES['fileimg']['tmp_name'])) {
                $rutaTemporal = $_FILES['fileimg']['tmp_name'];
                //con esto la imagen siempre tendra un nombre distinto
                $nombreImagen = $ccodimage;
                $info = pathinfo($_FILES['fileimg']['name']); //extrae la extension     
                $nomimagen = '/' . $nombreImagen . "." . $info['extension'];
                $rutaDestino = $rutaEnServidor . $nomimagen;

                if (($_FILES["fileimg"]["type"] == "image/pjpeg") || ($_FILES["fileimg"]["type"] == "image/jpeg") || ($_FILES["fileimg"]["type"] == "image/jpg") || ($_FILES["fileimg"]["type"] == "image/png") || ($_FILES["fileimg"]["type"] == "image/gif") || ($_FILES["fileimg"]["type"] == "application/pdf")) {
                    if (move_uploaded_file($rutaTemporal, $rutaDestino)) {
                        $conexion->autocommit(false);
                        try {
                            $consulta2 = mysqli_query($conexion, "UPDATE `otr_pago` SET `file`='" . $entrada . $nomimagen . "' WHERE id = '" . $ccodimage . "'");
                            $aux = mysqli_error($conexion);
                            if ($aux) {
                                echo json_encode(['Error en la inserción de la ruta del archivo fallo', '0']);
                                $conexion->rollback();
                                return;
                            }
                            if (!$consulta2) {
                                echo json_encode(['Inserción de la ruta del archivo falló', '0']);
                                $conexion->rollback();
                                return;
                            }
                            $conexion->commit();
                            echo json_encode(['Archivo cargado correctamente', '1']);;
                        } catch (Exception $e) {
                            $conexion->rollback();
                            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                        }
                    } else {
                        echo json_encode(['Fallo al guardar el archivo, error al mover el archivo a la ruta en el servidor', '0']);
                    }
                } else {
                    echo json_encode(['La extension del archivo no es permitida, ingrese una imagen jpeg, jpg, png, gif o un archivo pdf', '0']);
                }
            }
            mysqli_close($conexion);
        }
        break;

    case 'cargar_emisores':
        // Caso para cargar emisores FEL de forma diferida
        try {
            $database->openConnection();

            // Consultar los emisores activos
            $cv_emisores = $database->selectColumns('cv_emisor', ['id', 'nombre_comercial', 'nit'], 'estado=1');

            $database->closeConnection();

            // Devolver respuesta exitosa con estructura compatible con tu callback
            echo json_encode([
                'Datos cargados correctamente',
                1,
                'emisores' => $cv_emisores,
                'total' => count($cv_emisores),
                'timer' => 10
            ]);
        } catch (Exception $e) {
            // En caso de error, cerrar conexión si está abierta
            if (isset($database)) {
                $database->closeConnection();
            }

            // Log del error
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());

            // Devolver respuesta de error
            echo json_encode([
                'Error al cargar emisores FEL. Código de error: ' . $codigoError,
                '0'
            ]);
        }
        break;

    case 'create_proveedor_otrEgreso':
        /**
         * Crear un nuevo proveedor desde el módulo de egresos
         */
        if (!($csrf->validateToken($_POST['inputs'][0], false))) {
            echo json_encode([
                'Token de seguridad inválido o expirado. Recargue la página e intente nuevamente',
                0
            ]);
            return;
        }

        Log::info("post inputs:", $_POST['inputs']);
        Log::info("post selects:", $_POST['selects']);

        $showmensaje = false;
        try {
            $correo = $_POST['inputs'][1] ?? '';
            $nit = $_POST['inputs'][2];
            $nombre_comercial = $_POST['inputs'][3];
            $nombre = $_POST['inputs'][4];
            $direccion = $_POST['inputs'][5] ?? '';
            $id_afiliacion_iva = $_POST['selects'][0] ?? null;

            // Validar campos requeridos
            if (empty($nit) || empty($nombre_comercial) || empty($nombre)) {
                $showmensaje = true;
                throw new Exception("Los campos NIT, Nombre Comercial y Nombre son obligatorios");
            }

            $database->openConnection();

            // Verificar si el NIT ya existe
            $existente = $database->selectColumns('cv_emisor', ['id'], 'nit=? AND estado=1', [$nit]);
            if (!empty($existente)) {
                $showmensaje = true;
                throw new Exception("Ya existe un proveedor con el NIT: $nit");
            }

            // Preparar datos para insertar
            $datos = [
                'nit' => $nit,
                'nombre' => $nombre,
                'nombre_comercial' => $nombre_comercial,
                'direccion' => $direccion,
                'correo' => $correo,
                'id_afiliacion_iva' => $id_afiliacion_iva ?? null,
                'estado' => 1,
                'created_by' => $idusuario,
                'created_at' => $hoy2
            ];

            // Insertar el nuevo emisor/proveedor
            $id_emisor = $database->insert('cv_emisor', $datos);

            $mensaje = "Proveedor creado exitosamente";
            $status = 1;
        } catch (Exception $e) {
            $status = 0;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                $mensaje = "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            } else {
                $mensaje = $e->getMessage();
            }
        } finally {
            $database->closeConnection();
        }

        echo json_encode([
            $mensaje,
            $status,
            $id_emisor ?? null,
            'reprint' => 0
        ]);
        break;

    case 'download_file':
        //se recibe los datos
        $datos = $_POST["datosval"];
        //Informacion de datosval 
        $archivo = $datos[3];

        //consultar la ruta del archivo
        try {
            //Validar si de casualidad ya se hizo el cierre otro usuario
            $stmt = $conexion->prepare("SELECT op.file AS file FROM otr_pago op WHERE op.id = ?");
            if (!$stmt) {
                throw new ErrorException("Error en la consulta de la ruta del archivo: " . $conexion->error);
            }
            $stmt->bind_param("s", $archivo[0]); //El arroba omite el warning de php
            if (!$stmt->execute()) {
                throw new ErrorException("Error en la ejecucion de la consulta de la ruta del archivo: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $numFilas = $result->num_rows;
            if ($numFilas < 1) {
                throw new ErrorException("No se encontro ningun registro");
            }
            $dato = $result->fetch_assoc();
            if ($dato['file'] == "" || $dato['file'] == null) {
                throw new ErrorException("Al registro no se le ha cargado ningun archivo");
            }
            //Envio de la imagen
            $file_path =  __DIR__ . '/../../../' . $dato['file'];
            $path_parts = pathinfo($file_path);
            $extension = $path_parts['extension'];
            $image = ["jpg", "jpeg", "pjpeg", "png", "gif"];
            $archivos = ["pdf"];

            $key = in_array($extension, $image);
            $compdata = ($key) ? "image" : "";
            if (!$key) {
                $key = in_array($extension, $archivos);
                $compdata = ($key) ? "application" : "";
            }

            ob_start();
            readfile($file_path);
            $getData = ob_get_contents();
            ob_end_clean();

            $opResult = array(
                'status' => 1,
                'mensaje' => 'Recurso descargado correctamente',
                'namefile' => $archivo[0],
                'tipo' => $extension,
                'data' => "data:$compdata/$extension;base64," . base64_encode($getData)
            );
            echo json_encode($opResult);
        } catch (\ErrorException $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            $opResult = array(
                'status' => 0,
                'mensaje' => $mensaje_error,
                'namefile' => "download",
                'tipo' => 'pdf',
            );
            echo json_encode($opResult);
        } finally {
            if ($stmt !== false) {
                $stmt->close();
            }
            $conexion->close();
        }
        break;
    case 'consultar_reporte':
        $id_descripcion = $_POST["id_descripcion"];
        $validar = validar_campos_plus([
            [$id_descripcion, "", 'No se ha detectado un identificador de reporte válido', 1],
            [$id_descripcion, "0", 'Ingrese un número de reporte mayor a 0', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        try {
            //Validar si de casualidad ya se hizo el cierre otro usuario
            $stmt = $conexion->prepare("SELECT * FROM tb_documentos td WHERE td.id = ?");
            if (!$stmt) {
                throw new Exception("Error en la consulta 1: " . $conexion->error);
            }
            $stmt->bind_param("s", $id_descripcion); //El arroba omite el warning de php
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecucion de la consulta 1: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $numFilas2 = $result->num_rows;
            if ($numFilas2 == 0) {
                throw new Exception("No se encontro el reporte en el listado de documentos disponible");
            }
            $fila = $result->fetch_assoc();
            echo json_encode(["Reporte encontrado", '1', $fila['nombre']]);
        } catch (Exception $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            echo json_encode([$mensaje_error, '0']);
        } finally {
            if ($stmt !== false) {
                $stmt->close();
            }
            $conexion->close();
        }
        break;
}
function validar_campos_plus($validaciones)
{
    for ($i = 0; $i < count($validaciones); $i++) {
        if ($validaciones[$i][3] == 1) { //igual
            if ($validaciones[$i][0] == $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 2) { //menor que
            if ($validaciones[$i][0] < $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 3) { //mayor que
            if ($validaciones[$i][0] > $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 4) { //Validarexpresionesregulares
            if (validar_expresion_regular($validaciones[$i][0], $validaciones[$i][1])) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 5) { //Escapar de la validacion
        } elseif ($validaciones[$i][3] == 6) { //menor o igual
            if ($validaciones[$i][0] <= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 7) { //menor o igual
            if ($validaciones[$i][0] >= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 8) { //diferente de
            if ($validaciones[$i][0] != $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        }
    }
    return ["", '0', false];
}
