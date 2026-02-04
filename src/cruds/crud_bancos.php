<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
// require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';


use Micro\Helpers\Log;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Helpers\Beneq;
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
//++++++++++++
// session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
// include '../../src/funcphp/func_gen.php';
// date_default_timezone_set('America/Guatemala');
// $hoy2 = date("Y-m-d H:i:s");
// $hoy = date("Y-m-d");
// $idusuario = $_SESSION['id'];
// $idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];
switch ($condi) {
    case 'create_cuentasbancos':

        //[`cuenta`],[`id_banco`,`id_ctb_nomenclatura`],[]
        list($noCuenta) = $_POST['inputs'];
        list($idBanco, $idNomenclatura) = $_POST['selects'];

        if ($noCuenta == "") {
            echo json_encode(['Debe digitar una cuenta', '0']);
            return;
        }
        if ($idBanco == "" || $idNomenclatura == "") {
            echo json_encode(['Debe seleccionar una cuenta contable', '0']);
            return;
        }

        $showmensaje = false;
        try {

            $database->openConnection();

            $verificacion = $database->selectColumns('ctb_bancos', ['id'], 'id_banco=? AND id_nomenclatura=? AND numcuenta=? AND estado=1', [$idBanco, $idNomenclatura, $noCuenta]);
            if (!empty($verificacion)) {
                $showmensaje = true;
                throw new Exception('Ya existe una cuenta con los mismos datos, verifique e intente nuevamente');
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++ CREACION DE CUENTA DE BANCO ++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $ctb_bancos = array(
                "id_banco" => $idBanco,
                "numcuenta" => $noCuenta,
                "id_nomenclatura" => $idNomenclatura,
                "correlativo" => 0,
                "dfecmod" => $hoy2,
                "estado" => 1,
                "codusu" => $idusuario,
            );

            $idCtbDiario = $database->insert('ctb_bancos', $ctb_bancos);

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, cuenta creada';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
    case 'update_cuentasbancos':

        //[`cuenta`],[`id_banco`,`id_ctb_nomenclatura`]
        list($noCuenta) = $_POST['inputs'];
        list($idBanco, $idNomenclatura) = $_POST['selects'];
        list($idCuentaSeleccionada) = $_POST['archivo'];

        if ($noCuenta == "") {
            echo json_encode(['Debe digitar una cuenta', '0']);
            return;
        }
        if ($idBanco == "" || $idNomenclatura == "") {
            echo json_encode(['Debe seleccionar una cuenta contable', '0']);
            return;
        }

        $showmensaje = false;
        try {

            $database->openConnection();

            $verificacion = $database->selectColumns('ctb_bancos', ['id'], 'estado=1 AND id=?', [$idCuentaSeleccionada]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception('No se encontró la cuenta, verifique que aún esté activa');
            }

            $verificacion = $database->selectColumns('ctb_bancos', ['id'], 'id_banco=? AND id_nomenclatura=? AND numcuenta=? AND estado=1 AND id!=?', [$idBanco, $idNomenclatura, $noCuenta, $idCuentaSeleccionada]);
            if (!empty($verificacion)) {
                $showmensaje = true;
                throw new Exception('Ya existe una cuenta con los mismos datos, verifique e intente nuevamente');
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++ CREACION DE CUENTA DE BANCO ++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $ctb_bancos = array(
                "id_banco" => $idBanco,
                "numcuenta" => $noCuenta,
                "id_nomenclatura" => $idNomenclatura,
                // "correlativo" => 0,
                "dfecmod" => $hoy2,
                "estado" => 1,
                "codusu" => $idusuario,
            );

            $database->update('ctb_bancos', $ctb_bancos, 'id=?', [$idCuentaSeleccionada]);

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, cuenta actualizada';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);

        break;
    case 'delete_cuentasbancos':
        list($idCuentaSeleccionada) = $_POST['archivo'];

        $showmensaje = false;
        try {

            $database->openConnection();

            $verificacion = $database->selectColumns('ctb_bancos', ['id'], 'estado=1 AND id=?', [$idCuentaSeleccionada]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception('No se encontró la cuenta, verifique que aún esté activa');
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++ ELIMINACION DE CUENTA DE BANCO ++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $ctb_bancos = array(
                "estado" => 0,
                "deleted_at" => $hoy2,
                "codusu" => $idusuario,
            );

            $database->update('ctb_bancos', $ctb_bancos, 'id=?', [$idCuentaSeleccionada]);

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, cuenta eliminada';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;

    case 'cambioSaldoCuentaBanco':
        //['saldo_<?= $anioActual_<?= $mes'],[],[],`cambioSaldoCuentaBanco`,`0`,[<?= $idCuentaSeleccionadaA ,<?= $anioActual ,<?= $mes ,<?= $idSaldo ]

        list($saldo) = $_POST['inputs'];
        list($idCuentaSeleccionada, $anio, $mes,  $idSaldo) = $_POST['archivo'];

        $showmensaje = false;
        try {

            if (!is_numeric($saldo)) {
                $showmensaje = true;
                throw new Exception('El saldo debe ser un número válido');
            }

            if ($saldo < 0) {
                $showmensaje = true;
                throw new Exception('El saldo no puede ser negativo');
            }

            $database->openConnection();

            $verificacion = $database->selectColumns('ctb_bancos', ['id'], 'estado=1 AND id=?', [$idCuentaSeleccionada]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception('No se encontró la cuenta, verifique que aún esté activa');
            }

            if ($idSaldo == 0) {
                //SI SUPUESTAMENTE NO ESTA REGISTRADO PERO EXISTE UNO SIMILAR, ACTUALIZARLO
                $verificacion = $database->selectColumns('ctb_saldos_bancos', ['id'], 'id_cuenta_banco=? AND mes=? AND anio=?', [$idCuentaSeleccionada, $mes, $anio]);
                if (!empty($verificacion)) {
                    $idSaldo = $verificacion[0]['id'];
                }
            } else {
                $verificacion = $database->selectColumns('ctb_saldos_bancos', ['id'], 'id=?', [$idSaldo]);
                if (empty($verificacion)) {
                    $idSaldo = 0;
                }
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++ CREACION O ACTUALIZACION DE SALDOS +++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            if ($idSaldo == 0) {
                $ctb_saldos_bancos = array(
                    "id_cuenta_banco" => $idCuentaSeleccionada,
                    "mes" => $mes,
                    "anio" => $anio,
                    "saldo_inicial" => $saldo,
                    "created_by" => $idusuario,
                    "created_at" => $hoy2,
                );

                $database->insert('ctb_saldos_bancos', $ctb_saldos_bancos);
            } else {
                $ctb_saldos_bancos = array(
                    "saldo_inicial" => $saldo,
                    "updated_by" => $idusuario,
                    "updated_at" => $hoy2,
                );

                $database->update('ctb_saldos_bancos', $ctb_saldos_bancos, 'id=?', [$idSaldo]);
            }

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, saldo actualizado';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);

        break;
    case 'change_status_cheque':
        list($idDiario, $newStatus, $opcion) = $_POST['archivo'];

        $showmensaje = false;
        try {

            $database->openConnection();

            $verificacion = $database->selectColumns('ctb_diario', ['karely'], 'estado=1 AND id=?', [$idDiario]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception('No se encontró la poliza correspondiente');
            }

            // Extraer el número después del guion en karely
            $idRegistro = null;
            $karely = $verificacion[0]['karely'] ?? '';
            $pos = strpos($karely, '_');
            if ($pos !== false && strlen($karely) > $pos + 1) {
                $idRegistro = substr($karely, $pos + 1);
            }

            // Log::info("ID Registro extraído: " . $idRegistro);
            // Log::info("Karely: " . $karely);

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++ CREACION O ACTUALIZACION DE SALDOS +++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            $database->update('ctb_ban_mov', ['estado' => $newStatus], 'id_ctb_diario=?', [$idDiario]);

            if ($newStatus == 0) {
                if ($opcion == 1) {
                    //ELIMINAR LA TRANSACCION
                    if (substr($verificacion[0]['karely'], 0, 4) === 'AHO_') {
                        $ahommov = [
                            'cestado' => 2,
                            'deleted_at' => date('Y-m-d H:i:s'),
                            'deleted_by' => $idusuario,
                        ];
                        $database->update('ahommov', $ahommov, 'id_mov=?', [$idRegistro]);
                    } elseif (substr($verificacion[0]['karely'], 0, 4) === 'APR_') {
                        $aprmov = [
                            'cestado' => 2,
                            'deleted_at' => date('Y-m-d H:i:s'),
                            'deleted_by' => $idusuario,
                        ];
                        $database->update('aprmov', $aprmov, 'id_mov=?', [$idRegistro]);
                    } elseif (substr($verificacion[0]['karely'], 0, 4) === 'CRE_') {
                        $CREDKAR = [
                            'CESTADO' => 'X',
                            'deleted_by' => $idusuario,
                            'deleted_at' => date('Y-m-d H:i:s'),
                        ];

                        $datosCredito = $database->selectColumns('CREDKAR', ['CCODCTA'], 'CODKAR=?', [$idRegistro]);
                        if (empty($datosCredito)) {
                            $showmensaje = true;
                            throw new Exception("No se encontró la cuenta de crédito");
                        }
                        $database->update('CREDKAR', $CREDKAR, 'CODKAR=?', [$idRegistro]);
                        $database->executeQuery('CALL update_ppg_account(?);', [$datosCredito[0]['CCODCTA']]);
                    } else {
                        Log::info("No se encontró la transacción correspondiente en las tablas de movimientos.s");
                    }
                    $ctb_diario = array(
                        'estado' => 0,
                        'deleted_by' => $idusuario,
                        'deleted_at' => date('Y-m-d H:i:s'),
                    );
                    $database->update('ctb_diario', $ctb_diario, 'id=?', [$idDiario]);
                }
                if ($opcion == 2) {
                    //REVERSAR LA TRANSACCION
                    if (substr($verificacion[0]['karely'], 0, 4) === 'AHO_') {
                        $datoReal = $database->selectColumns('ahommov', ['*'], 'id_mov=?', [$idRegistro]);
                        if (empty($datoReal)) {
                            $showmensaje = true;
                            throw new Exception("No se encontró la transacción correspondiente en la tabla de ahorros.");
                        }
                        $ahommov = [
                            'ccodaho' => $datoReal[0]['ccodaho'],
                            'dfecope' => $datoReal[0]['dfecope'],
                            'ctipope' => 'R',
                            'cnumdoc' => $datoReal[0]['cnumdoc'],
                            'ctipdoc' => $datoReal[0]['ctipdoc'],
                            'crazon' => 'REVERSION',
                            'concepto' => $datoReal[0]['concepto'] . ' (REVERSION)',
                            'nlibreta' => $datoReal[0]['nlibreta'],
                            'nrochq' => '',
                            'tipchq' => '',
                            'fechaBanco' => null,
                            'idCuentaBanco' => null,
                            'dfeccomp' => '0000-00-00',
                            'numpartida' => '0',
                            'monto' => $datoReal[0]['monto'],
                            'lineaprint' => 'N',
                            'numlinea' => 1,
                            'correlativo' => 1,
                            'dfecmod' => date('Y-m-d H:i:s'),
                            'codusu' => $idusuario,
                            'cestado' => 1,
                            'auxi' => 'REVERSION_AHOMMOV_' . $idRegistro,
                            'created_at' => date('Y-m-d H:i:s'),
                            'created_by' => $idusuario,
                        ];

                        $database->insert('ahommov', $ahommov);
                    } elseif (substr($verificacion[0]['karely'], 0, 4) === 'APR_') {
                        $database->update('aprmov', ['cestado' => 3], 'id_mov=?', [$idRegistro]);
                    } elseif (substr($verificacion[0]['karely'], 0, 4) === 'CRE_') {
                        $database->update('CREDKAR', ['CESTADO' => 'R'], 'CODKAR=?', [$idRegistro]);
                    } else {
                        Log::info("No se encontró la transacción correspondiente en las tablas de movimientos.");
                    }
                }
            }



            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, estado del cheque actualizado';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
    case 'buscar_cuentas':
        $id = $_POST['id'];
        $data[] = [];
        $bandera = true;
        $consulta = mysqli_query($conexion, "SELECT cbn.id, cbn.numcuenta FROM tb_bancos bn INNER JOIN ctb_bancos cbn ON bn.id=cbn.id_banco WHERE bn.estado='1' AND cbn.estado='1' AND bn.id='$id'");
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode(['Error en la recuperacion de cuentas de bancos, intente nuevamente', '0']);
            return;
        }
        if ($consulta) {
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = false;
                $data[$i] = $fila;
                $i++;
            }

            if ($bandera) {
                echo json_encode(['El banco no tiene cuentas creadas, por lo que no se puede completar la transacción', '0']);
                return;
            }
            echo json_encode(['Satisfactorio', '1', $data]);
        } else {
            echo json_encode(['Error en la recuperacion de cuentas de bancos, intente nuevamente', '0']);
        }
        break;
    case 'create_cheques':
        $inputs = $_POST["inputs"];
        $datospartida = $inputs[0];
        $datosdebe = $inputs[1];
        $datoshaber = $inputs[2];
        $datoscuentas = $inputs[3];
        $datosfondos = $inputs[4];
        $selects = $_POST["selects"];
        $tipoAsignacion = $_POST["radios"][0] ?? "byAgency";
        $archivo = $_POST["archivo"];

        //getinputsval(['datedoc', 'datecont', 'codofi', 'id_agencia', 'cantidad', 'numdoc', 'paguese', 'numletras', 'numcheque', 'glosa', 'totdebe', 'tothaber']
        list($dateDoc, $dateCont, $codofi, $idAgencia, $cantidad, $numDoc, $paguese, $numLetras, $numCheque, $glosa, $totdebe, $tothaber) = $datospartida;

        //['negociable', 'bancoid', 'cuentaid', 'id_usuario_asignado']
        list($negociable, $bancoid, $cuentaid, $id_usuario_asignado) = $selects;

        //validar cada uno de los Campos
        //validar fechas
        if ($dateDoc > date("Y-m-d")) {
            echo json_encode(['La fecha de documento no puede ser mayor que la fecha de hoy', '0']);
            return;
        }
        if ($dateCont > date("Y-m-d")) {
            echo json_encode(['La fecha contable no puede ser mayor que la fecha de hoy', '0']);
            return;
        }

        // $idAgencia = $datospartida[3];
        //validar agencia
        if ($idAgencia == "") {
            echo json_encode(['Para completar el registro es necesario una agencia', '0']);
            return;
        }
        //validar fondos propios
        // if ($selects[0] == "") {
        //     echo json_encode(['Debe seleccionar una fuente de fondo', '0']);
        //     return;
        // }
        //validar cantidad
        if ($cantidad < 1) {
            echo json_encode(['Debe ingresar una cantidad mayor a 0.00 quetzales', '0']);
            return;
        }
        //validar tipo de cheque
        if ($negociable == "") {
            echo json_encode(['Debe seleccionar un tipo de cheque', '0']);
            return;
        }
        //validar numero de documento
        if ($numDoc == "" || $numDoc == "X") {
            echo json_encode(['Debe ingresar un numero de documento', '0']);
            return;
        }
        //validar paguese
        if ($paguese == "") {
            echo json_encode(['Debe digitar un nombre para el campo paguese a la orden de', '0']);
            return;
        }
        //validar numero en letras
        if ($numLetras == "") {
            echo json_encode(['Se necesita descripcion de cantidad en quetzales', '0']);
            return;
        }
        //validar banco
        if ($bancoid == "") {
            echo json_encode(['Debe seleccionar un banco', '0']);
            return;
        }
        //validar cuenta
        if ($cuentaid == "") {
            echo json_encode(['Debe seleccionar una cuenta de banco', '0']);
            return;
        }
        //validar numero de cheque
        if ($numCheque == "") {
            echo json_encode(['Debe digitar un numero de cheque', '0']);
            return;
        }
        //validar concepto
        if ($glosa == "") {
            echo json_encode(['Debe digitar un concepto', '0']);
            return;
        }
        //validar tipo de cheque
        if ($totdebe != $tothaber) {
            echo json_encode(['Sumatoria de debe no es igual a la del haber', '0']);
            return;
        }
        if ($totdebe == 0 || $tothaber == 0) {
            echo json_encode(['Sumatoria es igual a 0, Ingrese montos', '0']);
            return;
        }
        if ($totdebe < 0 || $tothaber < 0) {
            echo json_encode(['Las sumatorias del debe y del haber no deben ser negativas', '0']);
            return;
        }

        $usuarioAsignado = ($tipoAsignacion == 'byUser') ? $id_usuario_asignado : $idusuario; // Si no se asigna un usuario, se usa el usuario actual

        // Log::info("user",[$tipoAsignacion, $usuarioAsignado, $idusuario, $idAgencia, $dateDoc, $dateCont, $codofi, $cantidad, $numDoc, $paguese, $numLetras, $numCheque, $glosa, $totdebe, $tothaber]);


        // Log::info("user",[$tipoAsignacion]);

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $showmensaje = false;
        try {

            // throw new Exception('Error en la transacción');

            $database->openConnection();

            if ($tipoAsignacion == 'byUser') {
                $dataAgencia = $database->selectColumns('tb_usuario', ['id_agencia'], 'id_usu=?', [$usuarioAsignado]);
                if (empty($dataAgencia)) {
                    $showmensaje = true;
                    throw new Exception('No se encontró el usuario asignado o no tiene agencia asignada');
                }
                $idAgencia = $dataAgencia[0]['id_agencia'];
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++ CREACION DE PARTIDA CONTABLE ++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            // $numpartida = getnumcompdo($idusuario, $database);
            $numpartida = Beneq::getNumcom($database, $idusuario, $idAgencia, $dateCont);
            $ctb_diario = [
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 7,
                'id_tb_moneda' => 1,
                'numdoc' => $numDoc,
                'glosa' => strtoupper($glosa),
                'fecdoc' => $dateDoc,
                'feccnt' => $dateCont,
                'cod_aux' => 'CHEQUES',
                'id_tb_usu' => $usuarioAsignado,
                'id_agencia' => $idAgencia,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 0,
                'created_by' => $idusuario,
            ];

            $idCtbDiario = $database->insert('ctb_diario', $ctb_diario);

            foreach ($datoscuentas as $index => $cuenta) {
                $datosMov = [
                    "id_ctb_diario" => $idCtbDiario,
                    "id_fuente_fondo" => $datosfondos[$index],
                    "id_ctb_nomenclatura" => $cuenta,
                    "debe" => $datosdebe[$index],
                    "haber" => $datoshaber[$index]
                ];
                $database->insert('ctb_mov', $datosMov);
            }

            $ctb_chq = [
                'id_ctb_diario' => $idCtbDiario,
                'id_cuenta_banco' => $cuentaid,
                'numchq' => $numCheque,
                'nomchq' => strtoupper($paguese),
                'monchq' => $cantidad,
                'emitido' => 0,
                'modocheque' => $negociable
            ];
            $database->insert('ctb_chq', $ctb_chq);

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, Cheque generado con Partida No.: ' . $numpartida;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
    case 'update_cheques':
        $inputs = $_POST["inputs"];
        $datospartida = $inputs[0];
        $datosdebe = $inputs[1];
        $datoshaber = $inputs[2];
        $datoscuentas = $inputs[3];
        $datosfondos = $inputs[4];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];
        $tipoAsignacion = $_POST["radios"][0] ?? "byAgency";

        //getinputsval(['datedoc', 'datecont', 'codofi', 'id_agencia', 'cantidad', 'numdoc', 'paguese', 'numletras', 'numcheque', 'glosa', 'totdebe', 'tothaber']
        list($dateDoc, $dateCont, $codofi, $idAgencia, $cantidad, $numDoc, $paguese, $numLetras, $numCheque, $glosa, $totdebe, $tothaber) = $datospartida;

        //['negociable', 'bancoid', 'cuentaid', 'id_usuario_asignado']
        list($negociable, $bancoid, $cuentaid, $id_usuario_asignado) = $selects;

        list($userSinSentido, $idCtbDiario) = $archivo;

        //validar cada uno de los Campos
        if ($idCtbDiario == "") {
            echo json_encode(['No ha seleccionado un identificador de registro a editar', '0']);
            return;
        }

        //validar agencia
        $idAgencia = $datospartida[3];
        if ($idAgencia == "") {
            echo json_encode(['Para completar el registro es necesario una agencia', '0']);
            return;
        }
        //validar fondos propios
        // if ($selects[0] == "") {
        //     echo json_encode(['Debe seleccionar una fuente de fondo', '0']);
        //     return;
        // }
        //validar cantidad
        if ($cantidad < 1) {
            echo json_encode(['Debe ingresar una cantidad mayor a 0.00 quetzales', '0']);
            return;
        }
        //validar tipo de cheque
        if ($negociable == "") {
            echo json_encode(['Debe seleccionar un tipo de cheque', '0']);
            return;
        }
        //validar numero de documento
        if ($numDoc == "" || $numDoc == "X") {
            echo json_encode(['Debe ingresar un numero de documento', '0']);
            return;
        }
        //validar paguese
        if ($paguese == "") {
            echo json_encode(['Debe digitar un nombre para el campo paguese a la orden de', '0']);
            return;
        }
        //validar numero en letras
        if ($numLetras == "") {
            echo json_encode(['Se necesita descripcion de cantidad en quetzales', '0']);
            return;
        }
        //validar banco
        if ($bancoid == "") {
            echo json_encode(['Debe seleccionar un banco', '0']);
            return;
        }
        //validar cuenta
        if ($cuentaid == "") {
            echo json_encode(['Debe seleccionar una cuenta de banco', '0']);
            return;
        }
        //validar numero de cheque
        if ($numCheque == "") {
            echo json_encode(['Debe digitar un numero de cheque', '0']);
            return;
        }
        //validar concepto
        if ($glosa == "") {
            echo json_encode(['Debe digitar un concepto', '0']);
            return;
        }
        //validar tipo de cheque
        if ($totdebe != $tothaber) {
            echo json_encode(['Sumatoria de debe no es igual a la del haber', '0']);
            return;
        }
        if ($totdebe == 0 || $tothaber == 0) {
            echo json_encode(['Sumatoria es igual a 0, Ingrese montos', '0']);
            return;
        }
        if ($totdebe < 0 || $tothaber < 0) {
            echo json_encode(['Las sumatorias del debe y del haber no deben ser negativas', '0']);
            return;
        }

        $usuarioAsignado = ($tipoAsignacion == 'byUser') ? $id_usuario_asignado : $idusuario;

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $showmensaje = false;
        try {
            $database->openConnection();

            if ($tipoAsignacion == 'byUser') {
                $dataAgencia = $database->selectColumns('tb_usuario', ['id_agencia'], 'id_usu=?', [$usuarioAsignado]);
                if (empty($dataAgencia)) {
                    $showmensaje = true;
                    throw new Exception('No se encontró el usuario asignado o no tiene agencia asignada');
                }
                $idAgencia = $dataAgencia[0]['id_agencia'];
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++ ACTUALIZACION DE PARTIDA CONTABLE ++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $ctb_diario = [
                'numdoc' => $numDoc,
                'glosa' => strtoupper($glosa),
                'fecdoc' => $dateDoc,
                'feccnt' => $dateCont,
                'id_tb_usu' => $usuarioAsignado,
                'id_agencia' => $idAgencia,
                'updated_at' => $hoy2,
                'updated_by' => $idusuario,
            ];

            $database->update('ctb_diario', $ctb_diario, "id=?", [$idCtbDiario]);

            $database->delete('ctb_mov', 'id_ctb_diario=?', [$idCtbDiario]);

            foreach ($datoscuentas as $index => $cuenta) {
                $datosMov = [
                    "id_ctb_diario" => $idCtbDiario,
                    "id_fuente_fondo" => $datosfondos[$index],
                    "id_ctb_nomenclatura" => $cuenta,
                    "debe" => $datosdebe[$index],
                    "haber" => $datoshaber[$index]
                ];
                $database->insert('ctb_mov', $datosMov);
            }

            $ctb_chq = [
                'id_cuenta_banco' => $cuentaid,
                'numchq' => $numCheque,
                'nomchq' => strtoupper($paguese),
                'monchq' => $cantidad,
                'modocheque' => $negociable
            ];
            $database->update('ctb_chq', $ctb_chq, "id_ctb_diario=?", [$idCtbDiario]);

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, Cheque actualizado ';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);

        break;
    case 'delete_cheques':
        // $id = $_POST["ideliminar"];
        $archivo = $_POST["archivo"];
        $idCtbDiario = $archivo[0];

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $showmensaje = false;
        try {
            $database->openConnection();

            $dataDiario = $database->selectColumns('ctb_diario', ['estado'], 'id=?', [$idCtbDiario]);
            if (empty($dataDiario)) {
                $showmensaje = true;
                throw new Exception('No se encontró la partida contable asociada al cheque');
            }

            if ($dataDiario[0]['estado'] == 0) {
                $showmensaje = true;
                throw new Exception('La partida contable ya se encuentra eliminada');
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++ ACTUALIZACION DE PARTIDA CONTABLE ++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $ctb_diario = [
                'estado' => 0,
                'deleted_at' => $hoy2,
                'deleted_by' => $idusuario,
            ];

            $database->update('ctb_diario', $ctb_diario, "id=?", [$idCtbDiario]);

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, Cheque eliminado ';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
    case 'anular_cheques':
        $archivo = $_POST["archivo"];
        $idCtbDiario = $archivo[0];

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $showmensaje = false;
        try {
            $database->openConnection();

            $dataDiario = $database->selectColumns('ctb_diario', ['estado', 'glosa'], 'id=?', [$idCtbDiario]);
            if (empty($dataDiario)) {
                $showmensaje = true;
                throw new Exception('No se encontró la partida contable asociada al cheque');
            }

            if ($dataDiario[0]['estado'] == 0) {
                $showmensaje = true;
                throw new Exception('La partida contable ya se encuentra eliminada');
            }

            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++ ACTUALIZACION DE PARTIDA CONTABLE ++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $ctb_diario = [
                'glosa' => $dataDiario[0]['glosa'] . " (CHEQUE ANULADO)",
                'updated_at' => $hoy2,
                'updated_by' => $idusuario,
            ];

            $database->update('ctb_diario', $ctb_diario, "id=?", [$idCtbDiario]);

            $database->update('ctb_mov', ['debe' => 0, 'haber' => 0], "id_ctb_diario=?", [$idCtbDiario]);

            $database->update('ctb_chq', ['monchq' => '0', 'emitido' => 2], "id_ctb_diario=?", [$idCtbDiario]);

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, Cheque anulado';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
    case 'listar_cheques':
        $id_agencia = $_POST['id_agencia'];
        $consulta = mysqli_query($conexion, "SELECT dia.id,dia.numcom,dia.feccnt,SUM(mov.debe) debe,SUM(mov.haber) haber, ch.monchq AS moncheque, ch.emitido AS estado, ch.numchq FROM ctb_mov AS mov 
        INNER JOIN ctb_diario dia ON mov.id_ctb_diario = dia.id
        INNER JOIN ctb_chq ch ON dia.id=ch.id_ctb_diario
        INNER JOIN tb_usuario tu ON dia.id_tb_usu=tu.id_usu
        INNER JOIN tb_agencia ta ON tu.id_agencia=ta.id_agencia
        WHERE dia.estado=1 AND ta.id_agencia='$id_agencia'
        GROUP BY mov.id_ctb_diario");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $imp = '';
            if ($fila["estado"] == 1) {
                $imp = '<span class="badge bg-success">Sí</span>';
            } else {
                if ($fila["numchq"] == '' || $fila["numchq"] == null) {
                    $imp = '<span class="badge bg-danger">No</span>';
                } else {
                    $imp = '<span class="badge bg-warning text-dark">No</span>';
                }
            }
            $array_datos[] = array(
                "0" => $fila["numcom"],
                "1" => $fila["feccnt"],
                "2" => $fila["debe"],
                "3" => $fila["moncheque"],
                "4" => $imp,
                "5" => '<td> <button class="btn btn-outline-success btn-sm" title="Ver Cheque" onclick="printdiv2(`#cuadro`, ' . $fila["id"] . ')"><i class="fa-sharp fa-solid fa-eye"></i></i></button></td>'
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;
    case 'cheque_automatico':
        $id_cuenta_banco = $_POST['id_cuenta_banco'];
        $id_reg_cheque = $_POST['id_reg_cheque'];
        $numcheque = 'NA';
        //verificar si ya tiene numero de Cheque
        if ($id_reg_cheque != 0) {
            $datos = mysqli_query($conexion, "SELECT ch.numchq AS numerocheque FROM ctb_chq ch WHERE ch.id='" . $id_reg_cheque . "'");
            while ($row = mysqli_fetch_array($datos)) {
                $numcheque = $row["numerocheque"];
            }
        }
        //crear el siguiente numero
        if ($numcheque == '' || $numcheque == null || $numcheque == 'NA') {
            $datos = mysqli_query($conexion, "SELECT CAST((MAX(ch.numchq)+1) AS CHAR) AS numerocheque FROM ctb_chq ch WHERE ch.id_cuenta_banco='" . $id_cuenta_banco . "'");
            while ($row = mysqli_fetch_array($datos)) {
                $numcheque = $row["numerocheque"];
            }
        }
        echo json_encode(['Numero de cheque automatico', '1', $numcheque]);
        break;
    case 'create_depositos_bancos':
        $inputs = $_POST["inputs"];
        $datospartida = $inputs[0];
        $datosdebe = $inputs[1];
        $datoshaber = $inputs[2];
        $datoscuentas = $inputs[3];
        $datosfondos = $inputs[4];
        $archivo = $_POST["archivo"];
        $idusuario = $_SESSION['id'];

        //validar cada uno de los Campos
        //datainputs = getinputsval(['datedoc', 'datecont', 'numdoc', 'glosa', 'totdebe', 'tothaber', 'idtipo_poliza','id_agencia'])
        /* generico([datainputs, datainputsd, datainputsh, datacuentas,datafondos], [], [], condio, idr, [idr]); */
        //validar fechas

        list($dateDoc, $dateCont, $numDoc, $glosa, $totDebe, $totHaber, $idTipoPoliza, $idAgencia, $destino) = $datospartida;
        if ($dateDoc > date("Y-m-d")) {
            echo json_encode(['La fecha de documento no puede ser mayor que la fecha de hoy', '0']);
            return;
        }
        if ($dateCont > date("Y-m-d")) {
            echo json_encode(['La fecha contable no puede ser mayor que la fecha de hoy', '0']);
            return;
        }
        //validar agencia
        if ($idAgencia == "") {
            echo json_encode(['Para completar el registro es necesario una agencia', '0']);
            return;
        }
        //validar numero de documento
        if ($numDoc == "") {
            echo json_encode(['Debe ingresar un numero de documento', '0']);
            return;
        }
        //validar concepto
        if ($glosa == "") {
            echo json_encode(['Debe digitar un concepto', '0']);
            return;
        }
        //validar montos debe y haber
        if ($totDebe != $totHaber) {
            echo json_encode(['Sumatoria de debe no es igual a la del haber', '0']);
            return;
        }
        if ($totDebe == 0 || $totHaber == 0) {
            echo json_encode(['Sumatoria es igual a 0, Ingrese montos', '0']);
            return;
        }
        if ($totDebe < 0 || $totHaber < 0) {
            echo json_encode(['Las sumatorias del debe y del haber no deben ser negativas', '0']);
            return;
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $query = "SELECT ctb.id id_cuenta, ctb.ccodcta, ctb.cdescrip,ban.numcuenta FROM ctb_nomenclatura ctb 
                    INNER JOIN ctb_bancos ban ON ban.id_nomenclatura=ctb.id WHERE ban.estado=1 AND ctb.estado=1";

        $showmensaje = false;
        try {
            $database->openConnection();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++ COMPROBACION DE QUE EXISTA AL MENOS UNA CUENTA DE BANCOS +++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $cuentasBancos = $database->getAllResults($query);

            $flagCuenta = false;
            foreach ($cuentasBancos as $cuenta) {
                if (in_array($cuenta['id_cuenta'], $datoscuentas)) {
                    $flagCuenta = true;
                    break;
                }
            }
            if (!$flagCuenta) {
                $showmensaje = true;
                throw new Exception('Falta al menos una cuenta bancaria');
            }


            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++ CREACION DE PARTIDA CONTABLE ++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $numpartida = getnumcompdo($idusuario, $database);
            $datosPartida = [
                "numcom" => $numpartida,
                "id_ctb_tipopoliza" => $idTipoPoliza,
                "id_tb_moneda" => 1,
                "numdoc" => $numDoc,
                "glosa" => strtoupper($glosa),
                "fecdoc" => $dateDoc,
                "feccnt" => $dateCont,
                "cod_aux" => 'DEPOSITOS_BANCOS',
                "id_tb_usu" => $idusuario,
                "fecmod" => $hoy2,
                "estado" => 1,
                "id_agencia" => $idAgencia
            ];
            $idCtbDiario = $database->insert('ctb_diario', $datosPartida);

            foreach ($datoscuentas as $index => $cuenta) {
                $datosMov = [
                    "id_ctb_diario" => $idCtbDiario,
                    "id_fuente_fondo" => $datosfondos[$index],
                    "id_ctb_nomenclatura" => $cuenta,
                    "debe" => $datosdebe[$index],
                    "haber" => $datoshaber[$index]
                ];
                $database->insert('ctb_mov', $datosMov);
            }

            if ($destino != "") {
                $ctb_ban_mov = [
                    "id_ctb_diario" => $idCtbDiario,
                    "destino" => $destino
                ];
                $database->insert('ctb_ban_mov', $ctb_ban_mov);
            }

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto,  Deposito registrado con Partida No.: ' . $numpartida;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);

        break;
    case 'update_depositos_bancos':
        $inputs = $_POST["inputs"];
        $datospartida = $inputs[0];
        $datosdebe = $inputs[1];
        $datoshaber = $inputs[2];
        $datoscuentas = $inputs[3];
        $datosfondos = $inputs[4];
        $archivo = $_POST["archivo"];
        $idusuario = $_SESSION['id'];

        $idCtbDiario = $archivo[0];

        list($dateDoc, $dateCont, $numDoc, $glosa, $totDebe, $totHaber, $idTipoPoliza, $idAgencia, $destino) = $datospartida;
        if ($dateDoc > date("Y-m-d")) {
            echo json_encode(['La fecha de documento no puede ser mayor que la fecha de hoy', '0']);
            return;
        }
        if ($dateCont > date("Y-m-d")) {
            echo json_encode(['La fecha contable no puede ser mayor que la fecha de hoy', '0']);
            return;
        }
        //validar agencia
        if ($idAgencia == "") {
            echo json_encode(['Para completar el registro es necesario una agencia', '0']);
            return;
        }
        //validar numero de documento
        if ($numDoc == "") {
            echo json_encode(['Debe ingresar un numero de documento', '0']);
            return;
        }
        //validar concepto
        if ($glosa == "") {
            echo json_encode(['Debe digitar un concepto', '0']);
            return;
        }
        //validar montos debe y haber
        if ($totDebe != $totHaber) {
            echo json_encode(['Sumatoria de debe no es igual a la del haber', '0']);
            return;
        }
        if ($totDebe == 0 || $totHaber == 0) {
            echo json_encode(['Sumatoria es igual a 0, Ingrese montos', '0']);
            return;
        }
        if ($totDebe < 0 || $totHaber < 0) {
            echo json_encode(['Las sumatorias del debe y del haber no deben ser negativas', '0']);
            return;
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $query = "SELECT ctb.id id_cuenta, ctb.ccodcta, ctb.cdescrip,ban.numcuenta FROM ctb_nomenclatura ctb 
                    INNER JOIN ctb_bancos ban ON ban.id_nomenclatura=ctb.id WHERE ban.estado=1 AND ctb.estado=1";

        $showmensaje = false;
        try {
            $database->openConnection();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++ COMPROBACION DE QUE EXISTA AL MENOS UNA CUENTA DE BANCOS +++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $cuentasBancos = $database->getAllResults($query);

            $flagCuenta = false;
            foreach ($cuentasBancos as $cuenta) {
                if (in_array($cuenta['id_cuenta'], $datoscuentas)) {
                    $flagCuenta = true;
                    break;
                }
            }
            if (!$flagCuenta) {
                $showmensaje = true;
                throw new Exception('Falta al menos una cuenta bancaria');
            }


            $database->beginTransaction();

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++ ACTUALIZACION DE PARTIDA CONTABLE ++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $diario = $database->selectColumns('ctb_diario', ['numcom'], "id=?", [$idCtbDiario]);
            if (empty($diario)) {
                $showmensaje = true;
                throw new Exception('Partida contable no encontrada');
            }
            //comprobar si el numero de registros es mayor a 1
            if (count($diario) > 1) {
                $showmensaje = true;
                throw new Exception('Error: Partida contable duplicada, contacte al administrador');
            }

            $datosPartida = [
                // "numcom" => $numpartida,
                "id_ctb_tipopoliza" => $idTipoPoliza,
                // "id_tb_moneda" => 1,
                "numdoc" => $numDoc,
                "glosa" => strtoupper($glosa),
                "fecdoc" => $dateDoc,
                "feccnt" => $dateCont,
                "cod_aux" => 'DEPOSITOS_BANCOS',
                "updated_by" => $idusuario,
                "updated_at" => $hoy2,
                "estado" => 1,
                "id_agencia" => $idAgencia
            ];
            $database->update('ctb_diario', $datosPartida, "id=?", [$idCtbDiario]);

            $database->delete('ctb_mov', "id_ctb_diario=?", [$idCtbDiario]);

            foreach ($datoscuentas as $index => $cuenta) {
                $datosMov = [
                    "id_ctb_diario" => $idCtbDiario,
                    "id_fuente_fondo" => $datosfondos[$index],
                    "id_ctb_nomenclatura" => $cuenta,
                    "debe" => $datosdebe[$index],
                    "haber" => $datoshaber[$index]
                ];
                $database->insert('ctb_mov', $datosMov);
            }

            //actualizar o insertar en ctb_ban_mov
            $ctbBanMov = $database->selectColumns('ctb_ban_mov', ['id'], "id_ctb_diario=?", [$idCtbDiario]);
            if (empty($ctbBanMov)) {
                //si no existe, insertar
                if ($destino != "") {
                    $ctb_ban_mov = [
                        "id_ctb_diario" => $idCtbDiario,
                        "destino" => $destino
                    ];
                    $database->insert('ctb_ban_mov', $ctb_ban_mov);
                }
            } else {
                $database->update('ctb_ban_mov', ['destino' => $destino], "id=?", [$ctbBanMov[0]['id']]);
            }

            $database->commit();
            $status = 1;
            $mensaje = 'Correcto, Deposito actualizado';
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);

        break;
    case 'delete_depositos_bancos':
        $id = $_POST["ideliminar"];
        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO 
        $consulta = mysqli_query($conexion, "SELECT feccnt FROM ctb_diario WHERE id =" . $id);
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $fechapoliza = $fila["feccnt"];
        }

        $cierre = comprobar_cierre($idusuario, $fechapoliza, $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }
        $conexion->autocommit(false);
        try {
            $conexion->query("UPDATE `ctb_diario` SET `deleted_at`='$hoy2',`deleted_by`=$idusuario,`estado`=0 WHERE id =" . $id);
            $conexion->commit();
            echo json_encode(['Correcto,  Depósito a bancos Eliminado: ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la eliminacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'movimientos_banco':

        list($idcuenta, $fecha_inicio, $fecha_fin) = $_POST['datas'];
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++ CONSULTA DE TODOS LOS MOVIMIENTOS DE LA CUENTA EN LA FECHA INDICADA +++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $strquery = " SELECT dia.id, debe, haber, dia.glosa, dia.fecdoc,dia.numdoc,dia.id_ctb_tipopoliza, 
                            IFNULL(chq.nomchq,'-') AS nombrecheque,IFNULL(cbm.destino,'-') as destinoBanco,IFNULL(chq.numchq,'-') as numcheque
                        FROM ctb_diario dia 
                        INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                        LEFT JOIN ctb_chq chq ON chq.id_ctb_diario=dia.id
                        LEFT JOIN ctb_ban_mov cbm ON cbm.id_ctb_diario=dia.id
                        WHERE dia.estado=1 AND dia.id_ctb_tipopoliza!=9 AND mov.id_ctb_nomenclatura=?  AND fecdoc BETWEEN ? AND ? 
                        ORDER BY fecdoc";

        $showmensaje = false;
        $array_datos = array();
        try {
            $database->openConnection();

            $datos = $database->getAllResults($strquery, [$idcuenta, $fecha_inicio, $fecha_fin]);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("No hay datos");
            }
            $i = 0;
            foreach ($datos as $fila) {
                $nomcheque = $fila["nombrecheque"];
                $destinoShow = ($nomcheque == '-') ? $fila["destinoBanco"] : $nomcheque;
                $disabled = ($nomcheque != '-' || $fila["id_ctb_tipopoliza"] == 7) ? '' : ' disabled';
                $switch = '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" value="' .  $fila["id"] . '" ' . $disabled . '></div>';
                $numdocShow = ($fila["numcheque"] == '-') ? $fila["numdoc"] : $fila["numcheque"];
                $array_datos[] = array(
                    "0" => $switch,
                    "1" => date("d-m-Y", strtotime($fila["fecdoc"])),
                    "2" => $fila["glosa"],
                    "3" => $fila["debe"],
                    "4" => $fila["haber"],
                    "5" => $numdocShow,
                    "6" => $destinoShow
                );
                $i++;
            }
            $results = array(
                "sEcho" => 1,
                "iTotalRecords" => count($array_datos),
                "iTotalDisplayRecords" => count($array_datos),
                "aaData" => $array_datos
            );
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                // $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $results = [
                "errorn" => 'Error preparando consulta: ' . $mensaje,
                "sEcho" => 1,
                "iTotalRecords" => 0,
                "iTotalDisplayRecords" => 0,
                "aaData" => []
            ];
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode($results);
        break;
    case 'create_banco':
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        $validar = validar_campos_plus([
            [$inputs[0], "", 'Debe ingresar un nombre de banco', 1],
            [$inputs[1], "", 'Debe ingresar la abreviatura del banco', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        //Validar si ya existe un registro igual que el nombre
        $stmt = $conexion->prepare("SELECT LOWER(tb.nombre) AS resultado FROM tb_bancos tb WHERE tb.nombre = ?");
        if (!$stmt) {
            $error = $conexion->error;
            echo json_encode(['Error preparando consulta 1: ' . $error, '0']);
            return;
        }
        $aux = (mb_strtolower($inputs[0], 'utf-8'));
        $stmt->bind_param("s", $aux);
        if (!$stmt->execute()) {
            $errorMsg = $stmt->error;
            echo json_encode(["Fallo al ejecutar la consulta 1: $errorMsg", '0']);
            return;
        }
        $resultado = $stmt->get_result();
        $numFilas = $resultado->num_rows;
        if ($numFilas > 0) {
            echo json_encode(["No se puede registrar debibo a que ya existe un banco con este nombre", '0']);
            return;
        }

        //PREPARACION DE ARRAY
        $data = array(
            'nombre' => $inputs[0],
            'abreviatura' => $inputs[1],
            'estado' => '1',
        );

        $conexion->autocommit(FALSE);
        try {
            // //INSERCION DE CLIENTE NATURAL
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $conexion->prepare("INSERT INTO tb_bancos ($columns) VALUES ($placeholders)");
            if (!$stmt) {
                $error = $conexion->error;
                echo json_encode(['Error preparando consulta: ' . $error, '0']);
                return;
            }
            // Obtener los valores del array de datos
            $values = array_values($data);
            // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
            $types = str_repeat('s', count($values));
            // Vincular los parámetros
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                $errorMsg = $stmt->error;
                $conexion->rollback();
                echo json_encode(["Error al ejecutar consulta 2: $errorMsg", '0']);
                return;
            }
            //Realizar el commit especifico
            $conexion->commit();
            echo json_encode(["Banco ingresado correctamente: ", '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(["Error: " . $e->getMessage(), '0']);
        } finally {
            if ($stmt !== false) {
                $stmt->close();
            }
            $conexion->close();
        }
        break;
    case 'update_banco':
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        $validar = validar_campos_plus([
            [$inputs[2], "", 'Debe seleccionar un banco a actualizar', 1],
            [$inputs[0], "", 'Debe ingresar un nombre de banco', 1],
            [$inputs[1], "", 'Debe ingresar la abreviatura del banco', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        //Validar si ya existe un registro igual que el nombre
        $stmt = $conexion->prepare("SELECT LOWER(tb.nombre) AS resultado FROM tb_bancos tb WHERE tb.nombre = ?");
        if (!$stmt) {
            $error = $conexion->error;
            echo json_encode(['Error preparando consulta 1: ' . $error, '0']);
            return;
        }
        $aux = (mb_strtolower($inputs[0], 'utf-8'));
        $stmt->bind_param("s", $aux);
        if (!$stmt->execute()) {
            echo json_encode(["Fallo al ejecutar la consulta 1", '0']);
            return;
        }
        $resultado = $stmt->get_result();
        $numFilas = $resultado->num_rows;
        if ($numFilas > 0) {
            echo json_encode(["No se puede actualizar debibo a que ya existe un registro de un banco con el mismo nombre", '0']);
            return;
        }

        //PREPARACION DE ARRAY
        $data = array(
            'nombre' => $inputs[0],
            'abreviatura' => $inputs[1],
            'estado' => '1',
        );

        $id = $inputs[2];
        $conexion->autocommit(FALSE);
        try {
            // Columnas a actualizar
            $setCols = [];
            foreach ($data as $key => $value) {
                $setCols[] = "$key = ?";
            }
            $setStr = implode(', ', $setCols);
            $stmt = $conexion->prepare("UPDATE tb_bancos SET $setStr WHERE id = ?");
            // Obtener los valores del array de datos
            $values = array_values($data);
            // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
            $values[] = $id; // Agregar ID al final
            $types = str_repeat('s', count($values));
            // Vincular los parámetros
            $stmt->bind_param($types, ...$values);
            if (!$stmt->execute()) {
                $errorMsg = $stmt->error;
                $conexion->rollback();
                echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
                return;
            }

            //Realizar el commit especifico
            $conexion->commit();
            echo json_encode(["Banco actualizado correctamente", '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(["Error: " . $e->getMessage(), '0']);
        } finally {
            if ($stmt !== false) {
                $stmt->close();
            }
            $conexion->close();
        }
        break;
    case 'delete_banco': {
            $archivo = $_POST["ideliminar"];
            $validar = validar_campos_plus([
                [$archivo, "", 'Debe seleccionar un registro a eliminar', 1],
            ]);
            if ($validar[2]) {
                echo json_encode([$validar[0], $validar[1]]);
                return;
            }

            //validar si se puede eliminar o no
            $stmt = $conexion->prepare("SELECT * FROM ctb_bancos WHERE id_banco = ?");
            if (!$stmt) {
                $error = $conexion->error;
                echo json_encode(['Error preparando consulta: ' . $error, '0']);
                return;
            }
            $id = $archivo;
            $stmt->bind_param('i', $id);
            if (!$stmt->execute()) {
                echo json_encode(["Fallo al ejecutar la consulta", '0']);
                return;
            }
            $resultado = $stmt->get_result();
            $numFilas = $resultado->num_rows;

            if ($numFilas > 0) {
                echo json_encode(["No se puede eliminar porque tiene al menos un numero de cuenta registrado", '0']);
                return;
            }

            //PREPARACION DE ARRAY
            $data = array(
                'estado' => '0',
            );

            $id = $archivo;
            $conexion->autocommit(FALSE);
            try {
                // Columnas a actualizar
                $setCols = [];
                foreach ($data as $key => $value) {
                    $setCols[] = "$key = ?";
                }
                $setStr = implode(', ', $setCols);
                $stmt = $conexion->prepare("UPDATE tb_bancos SET $setStr WHERE id = ?");
                // Obtener los valores del array de datos
                $values = array_values($data);
                // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
                $values[] = $id; // Agregar ID al final
                $types = str_repeat('s', count($values));
                // Vincular los parámetros
                $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                    $conexion->commit();
                    echo json_encode(["Banco eliminado correctamente", '1']);
                } else {
                    $errorMsg = $stmt->error;
                    $conexion->rollback();
                    echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
                }
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(["Error: " . $e->getMessage(), '0']);
            } finally {
                if ($stmt !== false) {
                    $stmt->close();
                }
                $conexion->close();
            }
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
        }
    }
    return ["", '0', false];
}
