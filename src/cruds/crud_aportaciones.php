<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../funcphp/func_gen.php';

require '../../vendor/autoload.php';
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

use Luecano\NumeroALetras\NumeroALetras;
use App\Generic\DocumentManager;
use Micro\Helpers\Log;
use Micro\Helpers\Beneq;


$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d H:i:s");
$hoy2 = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {
    //CRUD - TIPOS DE APORTACIONES
    //-----CREATE
    case 'create_aport_tip': {
            //valores de los inputs
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos
            //nombre de etiquetas
            $inputsn = $_POST["inputsn"];  // 
            $selectsn = $_POST["selectsn"];     // selects nombres
            $consulta = "";
            //PARA LOS INPUTS
            if ($inputsn[0] != "nada") {
                $i = 0;
                foreach ($inputs as $input) {
                    $consulta = $consulta . "`" . $inputsn[$i] . "`";
                    if ($i != count($inputs) - 1) {
                        $consulta = $consulta . ",";
                    }
                    $i = $i + 1;
                }
            }
            //PARA LOS SELECTS
            $i = 0;
            foreach ($selects as $select) {
                $consulta = $consulta . ",";
                $consulta = $consulta . "`" . $selectsn[$i] . "`";
                $i = $i + 1;
            }
            $tasa = floatval($inputs[3]);
            $valido = validarcampo($inputs, "");
            if ($valido != "1") {
                echo json_encode([$valido, '0']);
                return;
            }

            $valido1 = validar_limites(0, 500, $tasa);
            if ($valido1 != "1") {
                echo json_encode([$valido1, '0']);
                return;
            }

            $query = mysqli_query($conexion, "INSERT INTO `aprtip`($consulta,`correlativo`,`numfront`,`front_ini`,`numdors`,`dors_ini`) VALUES ('$inputs[0]','$inputs[1]','$inputs[2]',$tasa,'$selects[0]',0,30,20,30,20)");
            if ($query) {
                echo json_encode(['Registro Ingresado ', '1']);
            } else {
                echo json_encode(['Error al ingresar ', '0']);
            }

            mysqli_close($conexion);
        }
        break;
    //-----ACTUALIZAR
    case 'update_aport_tip': {
            $inputs = $_POST["inputs"];
            $inputsn = $_POST["inputsn"];
            $idtip = $_POST["archivo"];  // 
            $consulta = "";
            //PARA LOS INPUTS
            $i = 0;
            foreach ($inputs as $input) {
                $consulta = $consulta . "`" . $inputsn[$i] . "` = '" . ($input) . "'";
                if ($i != count($inputs) - 1) {
                    $consulta = $consulta . ",";
                }
                $i = $i + 1;
            }

            $valido = validarcampo($inputs, "");
            if ($valido != "1") {
                echo json_encode([$valido, '0']);
                return;
            }

            $valido1 = validar_limites(1, 500, $inputs[3]);
            if ($valido1 != "1") {
                echo json_encode([$valido1, '0']);
                return;
            }

            $query = mysqli_query($conexion, "UPDATE `aprtip` set $consulta WHERE id_tipo=" . $idtip);
            if ($query) {
                echo json_encode(['Registro actualizado correctamente ', '1']);
            } else {
                echo json_encode(['Error al actualizar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----ELIMINAR

    case 'delete_aport_tip': {
            $idtip = $_POST["ideliminar"];

            $eliminar = "DELETE FROM aprtip WHERE id_tipo =" . $idtip;
            if (mysqli_query($conexion, $eliminar)) {
                echo json_encode(['Eliminacion correcta ', '1']);
            } else {
                echo json_encode(['Error al eliminar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;

    //CRUD - APERTURA DE CUENTAS
    //-----CREATE

    case 'create_apr_cuenta': {
            //SUBSTR EN PHP INICIA EN 0, SUBSTR EN SQL INICIA EN 1
            $hoy = date("Y-m-d");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos
            $inputsn = $_POST["inputsn"];  // 
            $selectsn = $_POST["selectsn"];     // selects nombres
            $flagg = $_POST["flagg"]; // PARA DETERMINAR SI LA CUENTA TIENE UNA SECUNDARIA PARA GUARDAR EL INTERES
            $codcuenint = $_POST["codcuenint"]; //CUENTA A LA CUAL SE ACREDITARIA EL INTERES

            $validacion = validarcampo([$inputs[4], $inputs[5]], "");
            //validar los productos
            if ($selects[0] == "0") {
                echo json_encode(['No ha seleccionado un tipo de producto o bien la agencia del usuario no tiene asignado ningun prouducto', '0']);
                return;
            }

            //EVALUAR SI ESTA CUENTA TIENE CUENTAS ASOCIADAS PARA ACREDITACION DE INTERESES
            $cuent = mysqli_query($conexion, "SELECT COUNT(*) AS cantidad FROM `aprcta` WHERE ctainteres = $codcuenint");
            if ($cuent) {
                $t1 = mysqli_fetch_assoc($cuent);
                $no = $t1['cantidad'];
            }
            if ($no == "0" && $codcuenint != "0") {
                if ($validacion == "1") {
                    $tipo = $selects[0];
                    // list($correlactual, $generar) = correlativo_general("aprcta", "ccodaport", "aprtip", "ccodage", $tipo, $conexion);
                    $codcredito = getccodaport($idagencia, $tipo, $conexion);
                    if ($codcredito[0] == 0) {
                        echo json_encode(["Fallo!, No se pudo generar el código de cuenta", '0']);
                        return;
                    }
                    $generar = $codcredito[1];

                    $tasa = floatval($inputs[2]);

                    //inicio transaccion
                    $conexion->autocommit(false);
                    try {
                        $conexion->query("INSERT INTO `aprcta`(`ccodaport`,`ccodcli`,`ccodtip`,`num_nit`,`nlibreta`,`estado`,`fecha_apertura`,`fecha_mod`,`codigo_usu`,`tasa`,ctainteres) VALUES ('$generar','$inputs[3]','$selects[0]','$inputs[4]','$inputs[5]','A','$hoy','$hoy','$inputs[6]',$tasa, $codcuenint)");
                        // $conexion->query("UPDATE `aprtip` set `correlativo`= $correlactual WHERE ccodtip=" . $selects[0]);
                        $conexion->query("INSERT INTO `aprlib`(`nlibreta`,`ccodaport`,`estado`,`date_ini`,`ccodusu`) VALUES ('$inputs[5]','$generar','A','$hoy','$inputs[6]')");
                        $conexion->commit();
                        echo json_encode(['Correcto,  Codigo Generado: ' . $generar, '1']);
                    } catch (Exception $e) {
                        $conexion->rollback();
                        echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                    }

                    // FLAGG -1 = CREAR CUENTA NUEVA - 0 = OMITIR  -  >0 = USAR UNA YA EXISTENTE
                    $conexion->autocommit(false);
                    try {
                        if ($flagg < 0) {
                            $conexion->query("INSERT INTO `aprcta`(`ccodaport`,`ccodcli`,`ccodtip`,`num_nit`,`nlibreta`,`estado`,`fecha_apertura`,`fecha_mod`,
                                                `codigo_usu`,`tasa`,ctainteres) VALUES ('$codcuenint','$inputs[3]','$selects[0]','$inputs[4]','$inputs[5]','A',
                                                '$hoy','$hoy','$inputs[6]',$tasa, $generar)");
                        }
                        $conexion->commit();
                    } catch (Exception $e) {
                        $conexion->rollback();
                        echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                    }
                } else {
                    echo json_encode([$validacion, '0']);
                }
            } else {
                if ($validacion == "1") {
                    $tipo = $selects[0];
                    // list($correlactual, $generar) = correlativo_general("aprcta", "ccodaport", "aprtip", "ccodage", $tipo, $conexion);
                    $codcredito = getccodaport($idagencia, $tipo, $conexion);
                    if ($codcredito[0] == 0) {
                        echo json_encode(["Fallo!, No se pudo generar el código de cuenta", '0']);
                        return;
                    }
                    $generar = $codcredito[1];

                    $tasa = floatval($inputs[2]);

                    //inicio transaccion
                    $conexion->autocommit(false);
                    try {
                        $conexion->query("INSERT INTO `aprcta`(`ccodaport`,`ccodcli`,`ccodtip`,`num_nit`,`nlibreta`,`estado`,`fecha_apertura`,`fecha_mod`,`codigo_usu`,`tasa`) VALUES ('$generar','$inputs[3]','$selects[0]','$inputs[4]','$inputs[5]','A','$hoy','$hoy','$inputs[6]',$tasa)");
                        // $conexion->query("UPDATE `aprtip` set `correlativo`= $correlactual WHERE ccodtip=" . $selects[0]);
                        $conexion->query("INSERT INTO `aprlib`(`nlibreta`,`ccodaport`,`estado`,`date_ini`,`ccodusu`) VALUES ('$inputs[5]','$generar','A','$hoy','$inputs[6]')");
                        $conexion->commit();
                        echo json_encode(['Correcto,  Codigo Generado: ' . $generar, '1']);
                    } catch (Exception $e) {
                        $conexion->rollback();
                        echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                    }
                } else {
                    echo json_encode([$validacion, '0']);
                }
            }


            mysqli_close($conexion);
        }
        break;
    //---CONSULTAR EL ULTIMO CORRELATIVO PARA ASIGNARLE AL NUEVO
    case 'correl': {
            $tipo = $_POST["tipo"];
            $ins = $_POST["ins"];
            $ofi = $_POST["ofi"];
            //correlativo actual y total mediante function
            // list($correlactual, $generar) = correlativo_general("aprcta", "ccodaport", "aprtip", "ccodage", $tipo, $conexion);
            $codcredito = getccodaport($idagencia, $tipo, $conexion);
            if ($codcredito[0] == 0) {
                echo json_encode(["Fallo!, No se pudo generar el código de cuenta", '0']);
                return;
            }
            $generar = $codcredito[1];

            $tasa = 0;
            $agencia = 0;
            $consultatas = mysqli_query($conexion, "SELECT `tasa`, `ccodage` FROM `aprtip` WHERE `ccodtip`=$tipo");
            while ($row = mysqli_fetch_array($consultatas, MYSQLI_ASSOC)) {
                $tasa = ($row['tasa']);
                $agencia = ($row['ccodage']);
            }
            //---
            echo json_encode([$generar, $tasa, $agencia]);
            mysqli_close($conexion);
        }
        break;

    //CRUD - DEPOSITO Y RETIRO
    //-----CREATE DEPOSITO O RETIRO
    case 'cdaportmov':
        //anteriord(['ccodaport', 'dfecope', 'cnumdoc', 'monto', 'numpartida', 'feccom', 'nrochq', 'cuotaIngreso'], ['salida', 'tipdoc', 'tipchq'], [], 'cdaportmov', '0', [usu, ofi, tipotransaction]);
        //actuald(['ccodaport', 'dfecope', 'cnumdoc', 'monto', 'cuotaIngreso','cnumdocboleta'], ['salida', 'tipdoc', 'bancoid', 'cuentaid'], [], 'cdaportmov', '0', [ echo $id;', action]);
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];
        $concepto = isset($inputs[6]) ? $inputs[6] : '';
        $fechaBanco = isset($inputs[7]) ? $inputs[7] : '0000-00-00';
        $hoy = date("Y-m-d H:i:s");
        $hoy2 = date("Y-m-d");


        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
        $cierre = comprobar_cierre($idusuario, $inputs[1], $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }
        //COMPROBAR CIERRE DE CAJA
        $cierre_caja = comprobar_cierre_caja($idusuario, $conexion);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }
        //VALIDACION DE MONTO
        $monto = $inputs[3];
        if (!(is_numeric($monto))) {
            echo json_encode(['Monto inválido, ingrese un monto correcto', '0']);
            return;
        }
        if ($monto <= 0) {
            echo json_encode(['Monto negativo ó igual a 0, ingrese un monto correcto', '0']);
            return;
        }
        //VALIDACION DE FECHA
        $fechaoperacion = $inputs[1];
        if (!validateDate($fechaoperacion, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', '0']);
            return;
        }
        // if ($fechaoperacion < $hoy2) {
        //     echo json_encode(['Esta ingresando una fecha menor a la de hoy', '0']);
        //     return;
        // }

        $cuenta = $archivo[0];
        $tipotransaccion = $archivo[1];
        $numdoc = $inputs[2];


        if ($numdoc == '') {
            echo json_encode(['Numero de Documento inválido', '0']);
            return;
        }

        $razon = ($tipotransaccion == "R") ? "RETIRO" : "DEPOSITO";
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++++ SALDO DE LA CUENTA DE APORTACIONES +++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $montoaux = 0;
        $saldo = 0;
        $query = "SELECT `monto`,`ctipope`,`dfecope` FROM `aprmov` WHERE `ccodaport`=? AND cestado!=2";
        $response = executequery($query, [$cuenta], ['s'], $conexion);
        if (!$response[1]) {
            echo json_encode([$response[0], '0']);
            return;
        }
        $data = $response[0];
        //$flag = ((count($data)) > 0) ? true : false;
        foreach ($data as $row) {
            $tiptr = ($row["ctipope"]);
            $montoaux = ($row["monto"]);
            $dfecope = ($row["dfecope"]);
            if ($tiptr == "R") {
                $saldo = $saldo - $montoaux;
            }
            if ($tiptr == "D") {
                $saldo = $saldo + $montoaux;
            }
        }
        $saldo = round($saldo, 2);
        if ($tipotransaccion == "R") {
            if ($monto > $saldo) {
                echo json_encode(['El saldo disponible en la cuenta es menor al monto solicitado', '0']);
                return;
            }
            if ($monto == $saldo) {
                $inactivar = ", `estado` = '0',`fecha_cancel` = '" . $hoy . "'"; //SE AGREGA A LA ACTUALIZACION DEL AHOMCTA LA INACTIVACION DE LA CUENTA: ESTADO B
            }
        }
        $saldo = round($saldo, 2);
        if ($tipotransaccion == "R") {
            if ($monto > $saldo) {
                echo json_encode(['El saldo disponible en la cuenta es menor al monto solicitado', '0']);
                return;
            }
            // Nuevo: Si el monto a retirar es igual al saldo (quedará en 0)
            if ($monto == $saldo) {
                // This flag will be used later in the transaction
                $inactivarCuenta = true;
            }
        }
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++ CUENTAS CONTABLES +++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        // SELECT id_nomenclatura_caja FROM tb_agencia WHERE id_agencia=1;
        // SELECT id_cuenta_contable FROM aprtip WHERE ccodtip="02";
        // SELECT id_nomenclatura FROM ctb_bancos WHERE id=14;
        $tipo_documento = $selects[1];
        $query = "SELECT id_nomenclatura_caja cuenta FROM tb_agencia WHERE id_agencia=?";
        $response = executequery($query, [$idagencia], ['i'], $conexion);
        if (!$response[1]) {
            echo json_encode([$response[0], '0']);
            return;
        }
        $data = $response[0];
        $cuentacaja = $data[0]['cuenta']; //cuenta contable de caja de la agencia

        $query = "SELECT id_cuenta_contable cuenta,cuenta_aprmov cuentaingreso, nombre FROM aprtip WHERE ccodtip=?";
        $response = executequery($query, [substr($cuenta, 6, 2)], ['s'], $conexion);
        if (!$response[1]) {
            echo json_encode([$response[0], '0']);
            return;
        }
        $data = $response[0];
        $flag = ((count($data)) > 0) ? true : false;
        if (!$flag) {
            echo json_encode(['No se encontró el tipo de cuenta', '0']);
            return;
        }
        $cuenta_tipo = $data[0]['cuenta']; //cuenta contable del tipo de ahorro
        $cuenta_cuotaingreso = $data[0]['cuentaingreso']; //cuenta contable del tipo de ahorro
        $producto = $data[0]['nombre'];

        $cuentacontable = $cuentacaja;
        $tipopoliza = 3; //por defecto es de tipo APORTACIONES
        $nocheque = '0';
        $auxiliar = "";
        $nodocconta = $numdoc;
        $desnumdocconta = "";
        if ($tipo_documento == "D" || $tipo_documento == "C") { //SI LA TRANSACCION ES DE TIPO DEPOSITO CON BOLETA DE BANCOS O CHEQUES
            if ($selects[2] == 0) {
                echo json_encode(['Seleccione un banco', '0']);
                return;
            }
            if ($selects[3] == 0) {
                echo json_encode(['Seleccione una cuenta de banco', '0']);
                return;
            }
            $tipopoliza = 11; //NOTA DE CREDITO ES 11, CHEQUE ES 7
            if ($tipo_documento == "C") {
                //VALIDACION DE NUMERO DE CHEQUE
                $nocheque = $inputs[5];
                if (!(is_numeric($nocheque))) {
                    echo json_encode(['Número de cheque inválido, ingrese un número correcto', '0']);
                    return;
                }
                if ($monto < 0) {
                    echo json_encode(['Número de cheque negativo, ingrese un número correcto', '0']);
                    return;
                }
                $tipopoliza = 7; //NOTA DE CREDITO ES 11, CHEQUE ES 7
                $negociable = $selects[4];
                $desnumdocconta = ", CON CHEQUE NO. " . $nocheque;
            }
            if ($tipo_documento == "D") {
                //VALIDACION DE NUMERO DE DOC BOLETA
                $nocheque = $inputs[5];
                if ($nocheque == "") {
                    echo json_encode(['Número de Boleta de banco inválido', '0']);
                    return;
                }
                $desnumdocconta = ", CON BOLETA DE BANCO NO. " . $nocheque;
            }
            $nodocconta = $nocheque;
            $auxiliar = $selects[3];
            $query = "SELECT id_nomenclatura cuenta FROM ctb_bancos WHERE id=?";
            $response = executequery($query, [$selects[3]], ['i'], $conexion);
            if (!$response[1]) {
                echo json_encode([$response[0], '0']);
                return;
            }
            $data = $response[0];
            $cuenta_banco = $data[0]['cuenta']; //cuenta contable de la cuenta de banco si es por bancos
            $cuentacontable = $cuenta_banco;
        }
        $idCuentaBanco = $selects[3];

        /**
         * AGREGADO PARA TIPOS DE DOCUMENTOS DIFERENTES CREADOS POR EL USUARIO
         */
        if (is_numeric($tipo_documento)) {
            $query = "SELECT id_cuenta_contable FROM tb_documentos_transacciones WHERE id=?";
            $response = executequery($query, [$tipo_documento], ['i'], $conexion);
            if (!$response[1]) {
                echo json_encode([$response[0], '0']);
                return;
            }
            $data = $response[0];
            $cuentacontable = $data[0]['id_cuenta_contable']; //cuenta contable del tipo de documento
        }

        /**
         * FIN AGREGADO PARA TIPOS DE DOCUMENTOS DIFERENTES CREADOS POR EL USUARIO
         */
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++ DATOS DE LA CUENTA DE APORTACIONES +++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,cli.no_identifica dpi,cli.control_interno,cli.Direccion 
            FROM `aprcta` cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
            WHERE `ccodaport`=?";

        $response = executequery($query, [$cuenta], ['s'], $conexion);
        if (!$response[1]) {
            echo json_encode([$response[0], '0']);
            return;
        }
        $data = $response[0];
        $flag = ((count($data)) > 0) ? true : false;
        if (!$flag) {
            echo json_encode(["Cuenta de aportaciones no existe", '0']);
            return;
        }
        $da = $data[0];
        $idcli = encode_utf8($da["ccodcli"]);
        $nit = ($da["num_nit"]);
        $dpi = ($da["dpi"]);
        $controlinterno = ($da["control_interno"]);
        $nlibreta = ($da["nlibreta"]);
        $estado = ($da["estado"]);
        $nombre = ($da["short_name"]);
        $direccion = ($da["Direccion"]);
        $ultimonum = lastnumlin($cuenta, $nlibreta, "aprmov", "ccodaport", $conexion);
        $ultimocorrel = lastcorrel($cuenta, $nlibreta, "aprmov", "ccodaport", $conexion);
        $numlib = numfront(substr($cuenta, 6, 2), "aprtip") + numdorsal(substr($cuenta, 6, 2), "aprtip");
        if ($ultimonum >= $numlib) {
            echo json_encode(["El número de líneas en libreta ha llegado a su límite, se recomienda abrir otra libreta", '0']);
            return;
        }
        // if ($estado != "A") {
        //     echo json_encode(["Cuenta de aportaciones Inactiva", '0']);
        //     return;
        // }
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++++++++++++++ ALERTA IVE +++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        //INICIO ALERTA IVE
        //ALERTE IVE ---> No superar los $ 10,000 - hacer la conversion a quetazqles
        $consulta = mysqli_query($conexion, "SELECT ccodcli FROM aprcta WHERE ccodaport = '$cuenta'"); //Seleccionar el codigo del cliente

        $error = mysqli_error($conexion);
        if ($error) {
            echo json_encode(['Error … !!!, ' . $error, '0']);
            return;
        };

        $codCli = mysqli_fetch_assoc($consulta);
        $validaAlerta = alerta($conexion, 3, $cuenta); //Validar si en los utimos 30 días, la cuenta del cliente ha llenado el formulario del IVE 
        $alertaAux = alerta($conexion, 4, $cuenta, '', '', '', '', $numdoc); // Valida si el codigo de documento y num de cuenta ya fue registrada la tb_Alerta
        $dolar = bcdiv(((movimiento($conexion, 2, db_name_general: $db_name_general)) * 10000), '1', 2); //Se obtine el valor neto de los $10000 en Quetzales

        //En los ultimos 30 días ya lleno el formulario ive 
        if ($validaAlerta == 1 && $alertaAux == 0) {
            $mov = ((movimiento($conexion, 5, $codCli, 'D', '', $cuenta)) - (movimiento($conexion, 5, $codCli, 'R', '', $cuenta))); //Validador auxiliar
            $alert = alerta($conexion, 2, $cuenta, $hoy2); //Valida el proceso de alerta

            //Alerta de IVE ***
            if ($alert == 'EP1') {
                echo json_encode(['004 Para continuar con la transacción, el usuario tiene que pasar a secretaria para llenar el formulario IVE. ', '0']);
                return;
            }

            //Alerta de IVE ***
            if ($alert == 'VC' && ($mov + $monto) > $dolar) {
                alerta($conexion, 1, $cuenta, $hoy2, $idusuario, $hoy, 'EP1', $fechaoperacion);
                alerta($conexion, 5, $cuenta, '', '', '', '', $fechaoperacion, $nombre);
                echo json_encode(['003 ALERTA IVE... en los últimos 30 días la cuenta del cliente ha superado los $10000, para continuar con la transacción el “contador o administrador” tiene que aprobar la alerta. Favor de apuntar el No. Documento: ' . $numdoc . '', '0']);
                return;
            }

            if ($alert == 'A1' && ($mov + $monto) > $dolar) {
                // echo json_encode(['0021 mov '.($mov+$inputs[3])." alert ".$alert, '0']);
                // return;
                alerta($conexion, 1, $cuenta, $hoy2, $idusuario, $hoy, 'EP1', $numdoc);
                alerta($conexion, 5, $cuenta, '', '', '', '', $numdoc, $nombre);
                echo json_encode(['005 ALERTA IVE... en los últimos 30 días la cuenta del cliente ha superado los $10000, para continuar con la transacción el “contador o administrador” tiene que aprobar la alerta. Favor de apuntar el No. Documento: ' . $numdoc . '', '0']);
                return;
            }
        }

        //No se ha llenado el formulario de ive durante los ultimos 30 días 
        if ($validaAlerta == 0 && $alertaAux == 0) {
            $mov = movimiento($conexion, 3, $codCli); //Deposito - Retiros 
            $alert = alerta($conexion, 2, $cuenta, $hoy2); //Valida el proceso de alerta

            //    echo json_encode(['001mov '.($mov+ $inputs[3])." alert ".$alert, '0']); 
            //    return; 

            //Alerta de IVE ***
            if ($alert == 'EP') {
                echo json_encode(['002 Para continuar con la transacción, el usuario tiene que pasar a secretaria para llenar el formulario IVE. ', '0']);
                return;
            }

            //Alerta de IVE ***
            if ($alert == "VC" && ($mov + $monto) > $dolar) {
                alerta($conexion, 1, $cuenta, $hoy2, $idusuario, $hoy, 'EP', $numdoc);
                alerta($conexion, 5, $cuenta, '', '', '', '', $numdoc, $nombre);
                echo json_encode(['001 ALERTA IVE... en los últimos 30 días la cuenta del cliente ha superado los $10000, para continuar con la transacción el “contador o administrador” tiene que aprobar la alerta. Favor de apuntar el No. Documento: ' . $numdoc . '', '0']);
                return;
            }
        }
        //FIN ALERTA IVE
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++ INSERCIONES EN LA BASE DE DATOS +++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $conexion->autocommit(false);
        try {
            // $camp_numcom = getnumcom($idusuario, $conexion);
            $camp_numcom = Beneq::getNumcomLegacy($idusuario, $conexion, $idagencia, $fechaoperacion);

            $cuotaingreso = (isset($inputs[4]) && $inputs[4] != null && $inputs[4] != 0 && $tipotransaccion == "D") ? $inputs[4] : 0;
            // Preparar la primera consulta para INSERT ahommov
            $res = $conexion->prepare("INSERT INTO `aprmov`(`ccodaport`,`dfecope`,`ctipope`,`cnumdoc`,`ctipdoc`,`crazon`,`concepto`,`nlibreta`,`nrochq`,`tipchq`,`numpartida`,`monto`,`cuota_ingreso`,`lineaprint`,`numlinea`,`correlativo`,`dfecmod`,`codusu`,`cestado`,`auxi`,`created_at`,`created_by`,`fechaBanco`, `idCuentaBanco`) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,'0', '0', ?,?, 'N', ?, ?, ?, ?,1, ?, ?,?,?,?)");

            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode([$aux, '0']);
                $conexion->rollback();
                return;
            }
            $ultimonum = ($ultimonum + 1);
            $ultimocorrel = ($ultimocorrel + 1);
            $res->bind_param('ssssssssiddiisssssss', $cuenta, $fechaoperacion, $tipotransaccion, $numdoc, $tipo_documento, $razon, $concepto, $nlibreta, $nocheque, $monto, $cuotaingreso, $ultimonum, $ultimocorrel, $hoy, $idusuario, $auxiliar, $hoy, $idusuario, $fechaBanco, $idCuentaBanco);
            $res->execute();

            // Preparar la segunda consulta para INSERT ctbdiario
            $camp_glosa = $razon . " DE APORTACIONES DE " . $nombre . " CON RECIBO NO. " . $numdoc . $desnumdocconta;
            $res = $conexion->prepare("INSERT INTO `ctb_diario`(`numcom`,`id_ctb_tipopoliza`,`id_tb_moneda`,`numdoc`,`glosa`,`fecdoc`,`feccnt`,`cod_aux`,`id_tb_usu`,`fecmod`,`estado`,`id_agencia`) 
            VALUES (?,?,1,?, ?,?, ?,?,?,?,1,?)");
            // $response = executequery($query, [$camp_numcom, $tipopoliza, $numdoc, $camp_glosa, $fechaoperacion, $fechaoperacion, $cuenta, $idusuario, $hoy, 1], ['s', 'i', 's', 's', 's', 's', 's', 'i', 's', 'i'], $conexion);
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode([$aux, '0']);
                $conexion->rollback();
                return;
            }
            $res->bind_param('sisssssisi', $camp_numcom, $tipopoliza, $nodocconta, $camp_glosa, $fechaoperacion, $fechaoperacion, $cuenta, $idusuario, $hoy, $idagencia);
            $res->execute();
            $id_ctb_diario = get_id_insertado($conexion);

            // Preparar la tercera consulta para INSERT ctbmov
            //REGISTRO DE LA CUENTA DEL TIPO DE AHORRO
            $mondebe = ($tipotransaccion == "R") ? $monto : 0;
            $monhaber = ($tipotransaccion == "R") ? 0 : $monto;
            $res = $conexion->prepare("INSERT INTO `ctb_mov`(`id_ctb_diario`,`numcom`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES (?,' ',1,?,?,?)");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode([$aux, '0']);
                $conexion->rollback();
                return;
            }
            $res->bind_param('iidd', $id_ctb_diario, $cuenta_tipo, $mondebe, $monhaber);
            $res->execute();
            // *********************************************** 
            $auxMonto = $monto;
            //SI HAY UN DEPOSITO DE CUOTA DE INGRESO INPUTS[4]
            $cuotaingreso = 0;
            if (isset($inputs[4]) && $inputs[4] != null && $inputs[4] != 0 && $tipotransaccion == "D") {
                $cuotaingreso = $inputs[4];
                $auxMonto = $cuotaingreso + $monto;
                $ccodtip = substr($cuenta, 6, 2);
                $resultado = mysqli_query($conexion, "SELECT a.cuenta_aprmov AS cuenta FROM aprtip a WHERE ccodtip = '$ccodtip'");
                if ($resultado) {
                    $idNomenclatura = mysqli_fetch_assoc($resultado)['cuenta'];
                }
                $res = $conexion->prepare("INSERT INTO `ctb_mov`(`id_ctb_diario`,`numcom`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) 
                VALUES (?,' ',1,?,0,?)");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode([$aux, '0']);
                    $conexion->rollback();
                    return;
                }
                $res->bind_param('iid', $id_ctb_diario, $cuenta_cuotaingreso, $cuotaingreso);
                $res->execute();
            }
            //REGISTRO DE LA CUENTA DE CAJA O BANCOs
            $mondebe = ($tipotransaccion == "R") ? 0 : $auxMonto;
            $monhaber = ($tipotransaccion == "R") ? $auxMonto : 0;
            $res = $conexion->prepare("INSERT INTO `ctb_mov`(`id_ctb_diario`,`numcom`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES (?,' ',1,?,?,?)");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode([$aux, '0']);
                $conexion->rollback();
                return;
            }
            $res->bind_param('iidd', $id_ctb_diario, $cuentacontable, $mondebe, $monhaber);
            $res->execute();
            if ($tipo_documento == "C") {
                //INSERCION EN CUENTAS DE CHEQUES
                $res = $conexion->prepare("INSERT INTO `ctb_chq`(`id_ctb_diario`,`id_cuenta_banco`,`numchq`,`nomchq`,`monchq`,`emitido`,`modocheque`) 
                                    VALUES (?,?,?, ?,?,'0',?)");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode([$aux, '0']);
                    $conexion->rollback();
                    return;
                }
                $res->bind_param('iissdi', $id_ctb_diario, $selects[3], $nocheque, $nombre, $monto, $negociable);
                $res->execute();
            }



            //ORDENAMIENTO DE TRANSACCIONES
            $res = $conexion->prepare("CALL apr_ordena_noLibreta(?, ?)");
            // $response = executequery($query, [$nlibreta, $cuenta], ['i', 's'], $conexion);
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode([$aux, '0']);
                $conexion->rollback();
                return;
            }
            $res->bind_param('is', $nlibreta, $cuenta);
            $res->execute();

            $res = $conexion->prepare("CALL apr_ordena_Transacciones(?)");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode([$aux, '0']);
                $conexion->rollback();
                return;
            }
            $res->bind_param('s', $cuenta);
            $res->execute();
            //-----FIN

            //calcular total 
            $total_ap2rcuo = ($cuotaingreso + $monto);

            //formatt
            $format_monto = new NumeroALetras();
            $decimal = explode(".", $total_ap2rcuo);
            $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
            $letras_total = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";

            //NUMERO EN LETRAS
            $format_monto = new NumeroALetras();
            $decimal = explode(".", $monto);
            $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
            $letras_monto = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";

            $particionfecha = explode("-", $fechaoperacion);

            $transaccion = $selects[4];

            $nocheque = $nocheque;
            $nobank = $selects[3];

            global $nombreBanco;

            //Busca el nombre del banco 
            if (!empty($nobank)) {
                $sqlb = "SELECT cb.id, cb.id_banco, tb.nombre
                         FROM ctb_bancos cb
                         INNER JOIN tb_bancos tb ON cb.id_banco = tb.id
                         WHERE cb.id = ?";

                // Preparar la consulta
                $stmt = $conexion->prepare($sqlb);
                $stmt->bind_param('i', $nobank);

                if ($stmt->execute()) {
                    $resultado = $stmt->get_result();

                    //save 
                    if ($resultado && mysqli_num_rows($resultado) > 0) {
                        $datosBanco = $resultado->fetch_assoc();
                        $nombreBanco = $datosBanco['nombre'];
                    } else {
                        $nombreBanco = 'Banco no encontrado';
                        echo json_encode(['No se encontró el banco.', '0']);
                    }
                } else {
                    echo json_encode(['Error en la consulta: ' . $stmt->error, '0']);
                }
            }

            // echo json_encode(['Datos reimpresos correctamente', '1', $ccodaport, number_format($monto, 2, '.', ','), date("d-m-Y", strtotime($hoy)), $cnumdoc, $archivos[1], $shortname, ($_SESSION['nombre']), ($_SESSION['apellido']), $hoy, $letras_monto, $particionfecha[0], $particionfecha[1], $particionfecha[2], $dpi, $cuotaingreso, $producto, ($monto + $cuotaingreso), $letras_monto, $_SESSION['id'], $controlinterno,$ncheque,$tipchq,$codcliente]);
            //deposito o retiro
            if ($conexion->commit()) {
                $auxdes = ($tipotransaccion == "D") ? "Depósito a cuenta " . $cuenta : "Retiro a cuenta " . $cuenta;
                echo json_encode(['Datos ingresados correctamente', '1', $cuenta, number_format($monto, 2, '.', ','), date("d-m-Y", strtotime($fechaoperacion)), $numdoc, $auxdes, $nombre, ($_SESSION['nombre']), ($_SESSION['apellido']), $hoy, $letras_monto, $particionfecha[0], $particionfecha[1], $particionfecha[2], $dpi, $cuotaingreso, $producto, $total_ap2rcuo, $letras_total, $_SESSION['id'], $controlinterno, $nocheque, " ", $tipo_documento, $idcli, $_SESSION['id_agencia'], $tiptr, $fechaoperacion, $concepto, $direccion]);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        //fin transaccion

        break;
    //CRUD - CAMBIO DE LIBRETA
    //-----CREATE NUEVA LIBRETA
    case 'cambiar_libreta': {
            $inputs = $_POST["inputs"];
            $inputsn = $_POST["inputsn"];  // 
            $archivos = $_POST["archivo"];
            $hoy = date("Y-m-d H:i:s");
            $hoy2 = date("Y-m-d");
            $validar = validarcampo($inputs, "");
            if ($validar == "1") {
                if ($inputs[1] < 1) {
                    echo json_encode(['Ingrese un número de libreta válido', '0']);
                } else {
                    //------traer el saldo de la cuenta
                    $monto = 0;
                    $saldo = 0;
                    $transac = mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `aprmov` WHERE `ccodaport`='$archivos[0]' AND cestado!=2");
                    while ($row = mysqli_fetch_array($transac, MYSQLI_ASSOC)) {
                        $tiptr = ($row["ctipope"]);
                        $monto = ($row["monto"]);

                        if ($tiptr == "R") {
                            $saldo = $saldo - $monto;
                        }
                        if ($tiptr == "D") {
                            $saldo = $saldo + $monto;
                        }
                    }
                    //****fin saldo */
                    //transaccion
                    $conexion->autocommit(false);
                    try {
                        $ultimonum = lastnumlin($inputs[0], $archivos[1], "aprmov", "ccodaport", $conexion);
                        $ultimocorrel = lastcorrel($inputs[0], $archivos[1], "aprmov", "ccodaport", $conexion);
                        //desactivar en aprlib los datos de la antigua libreta
                        $conexion->query("UPDATE `aprlib` SET `estado` = 'B',`date_fin` = '$hoy' WHERE `ccodaport` = '$inputs[0]' AND `nlibreta`=  $archivos[1]");
                        //creacion de nueva libreta en aprlib
                        $conexion->query("INSERT INTO `aprlib`(`nlibreta`,`ccodaport`,`estado`,`date_ini`,`ccodusu`,`crazon`) VALUES ('$inputs[1]','$inputs[0]','A','$hoy2','$archivos[2]','maxlin')");
                        //insertar en aprmov para traer el saldo anterior de la libreta pasada
                        //registro por cambio de libreta
                        $conexion->query("INSERT INTO `aprmov`(`ccodaport`,`dfecope`,`ctipope`,`cnumdoc`,`ctipdoc`,`crazon`,`nlibreta`,`nrochq`,`tipchq`,`numpartida`,`monto`,`lineaprint`,`numlinea`,`correlativo`,`dfecmod`,`codusu`) VALUES ('$inputs[0]','$hoy2','R','LIB0001','E','CAMBIO LIBRETA', $archivos[1],'','','',$saldo,'N',$ultimonum+1,$ultimocorrel+1,'$hoy','$archivos[2]')");
                        //registro por saldo inicial en la nueva libreta
                        $conexion->query("INSERT INTO `aprmov`(`ccodaport`,`dfecope`,`ctipope`,`cnumdoc`,`ctipdoc`,`crazon`,`nlibreta`,`nrochq`,`tipchq`,`numpartida`,`monto`,`lineaprint`,`numlinea`,`correlativo`,`dfecmod`,`codusu`) VALUES ('$inputs[0]','$hoy2','D','LIB0001','E','SALDO INI', $inputs[1],'','','',$saldo,'N',1,$ultimocorrel+2,'$hoy','$archivos[2]')");
                        //actualizar en aprcta
                        $conexion->query("UPDATE `aprcta` SET `nlibreta` = '$inputs[1]',`numlinea` = 1,`correlativo` = $ultimocorrel+2 WHERE `ccodaport` = '$inputs[0]'");

                        if ($conexion->commit()) {
                            echo json_encode(['Cambio de libreta satisfactorio', '1']);
                        } else {
                            echo json_encode(['Error al intentar cambiar la libreta: ', '0']);
                        }
                    } catch (Exception $e) {
                        $conexion->rollback();
                        echo json_encode(['Error al intentar cambiar la libreta: ' . $e->getMessage(), '0']);
                    }
                    //fin transaccion
                }
            } else {
                echo json_encode([$validar, '0']);
            }
            mysqli_close($conexion);
        }
        break;
    
    case 'lprint':
        $ccodaport = $_POST["id"];
        $checkeds = $_POST["archivo"][0];
        $ids = implode(',', array_map('intval', $checkeds));
        $showmensaje = false;
        try {
            $database->openConnection();
            $prdapor = $database->selectColumns("aprtip", ['numfront', 'front_ini', 'numdors', 'dors_ini'], "ccodtip=?", [substr($ccodaport, 6, 2)]);
            if (empty($prdapor)) {
                $showmensaje = true;
                throw new Exception("No se encontro el producto de la cuenta de aportacion");
            }
            $query = "SELECT mov.ccodaport, dfecope,ctipdoc, ctipope, cnumdoc,mov.crazon, concepto, monto, numlinea, correlativo, saldo_aportacion(mov.ccodaport, dfecope ,correlativo) AS saldo,usu.id_agencia as id_agencia, ifnull(usu2.id_agencia,1) as agencia_libreta, mov.codusu
            FROM aprmov mov 
            LEFT JOIN tb_usuario usu ON usu.id_usu = mov.codusu 
            LEFT JOIN aprlib lib ON lib.nlibreta = mov.nlibreta and mov.ccodaport=lib.ccodaport and lib.estado = 'A'
            LEFT JOIN tb_usuario usu2 on lib.ccodusu = usu2.id_usu
            WHERE id_mov IN ($ids) AND mov.ccodaport = ? AND mov.cestado = 1 ORDER BY mov.correlativo;";
            $movimientos = $database->getAllResults($query, [$ccodaport]);
            if (empty($movimientos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron movimientos para la cuenta de aportacion seleccionada");
            }

            Log::debug("setsome message", $movimientos);

            $documentos = $database->getAllResults("SELECT nombre FROM tb_documentos WHERE id_reporte = 10");
            if (empty($documentos)) {
                $showmensaje = true;
                throw new Exception("No se encontro el documento para la cuenta de aportacion seleccionada");
            }

            $datos = array(
                "lineaprint" => 'S'
            );
            $database->update('aprmov', $datos, "id_mov IN ($ids) AND ccodaport = ?", [$ccodaport]);

            $status = 1;
            $mensaje = "Proceso realizado correctamente";
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
        echo json_encode([$mensaje, $status, $movimientos, $prdapor, $documentos ?? []]);
        break;
    //CRUD - BENEFICIARIOS
    //-----CREATE
    case 'create_apr_ben': {
            $hoy = date("Y-m-d");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"];
            $inputsn = $_POST["inputsn"];
            $selectsn = $_POST["selectsn"];
            $archivos = $_POST["archivo"];
            $consulta2 = mysqli_query($conexion, "SELECT * FROM `aprben` WHERE `codaport`='$archivos[0]'");
            //se cargan los datos de las beneficiarios a un array
            $total_aux = 0;
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $benporcent = ($fila["porcentaje"]);
                $total_aux = $total_aux + $benporcent;
            }

            if ($archivos[1] == "") {
                $validacion = validarcampo($inputs, "");
                if ($validacion == "1") {
                    //validando que el primer beneficiario tiene que tener el 100%
                    $total = $total_aux + $inputs[5];
                    if (($total_aux == 0) && ($total != 100)) {
                        echo json_encode(['Al ser el primer beneficiario tiene que digitar que sea el 100%', '0']);
                    } else {
                        if ($total > 100) {
                            echo json_encode(['El porcentaje ingresado del nuevo beneficiario sumados con los anteriores no puede ser mayor a 100', '0']);
                        } else {
                            if ($inputs[5] <= 0) {
                                echo json_encode(['Verifique que el porcentaje ingresado del nuevo beneficiario no puede ser menor o igual a 0', '0']);
                            } else {
                                $validparent = validarcampo($selects, "0");
                                if ($validparent == "1") {
                                    if (preg_match('/^\d{13}$/', $inputs[1]) == false) {
                                        echo json_encode(['Ingrese un número de DPI válido, debe tener 13 caracteres numericos', '0']);
                                        return;
                                    }
                                    if (preg_match('/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})$/', $inputs[3]) == false) {
                                        echo json_encode(['Debe digitar un número de teléfono válido', '0']);
                                        return;
                                    }
                                    $conexion->autocommit(false);
                                    try {
                                        $conexion->query("INSERT INTO `aprben`(`codaport`,`nombre`,`dpi`,`direccion`,`codparent`,`fecnac`,`porcentaje`,`telefono`) VALUES ('$archivos[0]','$inputs[0]','$inputs[1]','$inputs[2]','$selects[0]','$inputs[4]','$inputs[5]','$inputs[3]')");
                                        $conexion->commit();
                                        echo json_encode(['Correcto,  Beneficiario guardado ', '1']);
                                    } catch (Exception $e) {
                                        $conexion->rollback();
                                        echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                                    }
                                } else {
                                    echo json_encode(['Seleccione parentesco', '0']);
                                }
                            }
                        }
                    }
                } else {
                    echo json_encode([$validacion, '0']);
                }
            } else {
                echo json_encode(['Seleccione primeramente una cuenta de aportación', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----ACTUALIZAR
    case 'update_apr_ben': {
            $hoy = date("Y-m-d");


            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos// 
            $archivos = $_POST["archivo"];

            $consulta2 = mysqli_query($conexion, "SELECT * FROM `aprben` WHERE `codaport`='$archivos[0]'");
            //se cargan los datos de las beneficiarios a un array
            $total_aux = 0;
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $benporcent = ($fila["porcentaje"]);
                $total_aux = $total_aux + $benporcent;
            }

            $validacion = validarcampo($inputs, "");
            if ($validacion == "1") {
                $total = $total_aux - $inputs[6] + $inputs[5];

                if ($total > 100) {
                    echo json_encode(['No se puede actualizar debido a que con el nuevo porcentaje supera el 100%, debe acomodar el o los porcentajes anteriores', '0']);
                } else if ($inputs[5] <= 0) {
                    echo json_encode(['El porcentaje nuevo no puede ser menor o igual a 0', '0']);
                } else {
                    $validparent = validarcampo($selects, "0");
                    if ($validparent == "1") {
                        $conexion->autocommit(false);
                        try {
                            $conexion->query("UPDATE `aprben` SET `nombre` = '$inputs[0]',`dpi` = '$inputs[1]',`direccion` = '$inputs[2]',`codparent` = $selects[0],`fecnac` = '$inputs[4]',`porcentaje` = $inputs[5],`telefono` = '$inputs[3]' WHERE `id_ben` = $inputs[7]");
                            $conexion->commit();
                            echo json_encode(['Correcto,  Beneficiario actualizado', '1']);
                        } catch (Exception $e) {
                            $conexion->rollback();
                            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                        }
                    } else {
                        echo json_encode(['Seleccione parentesco', '0']);
                    }
                }
            } else {
                echo json_encode([$validacion, '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----ELIMINAR
    case 'delete_apr_ben': {
            $idaprben = $_POST["ideliminar"];
            $eliminar = "DELETE FROM aprben WHERE id_ben =" . $idaprben;
            if (mysqli_query($conexion, $eliminar)) {
                echo json_encode(['Eliminacion correcta ', '1']);
            } else {
                echo json_encode(['Error al eliminar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----LISTADO DE BENEFICIARIOS DE 1 CLIENTE
    case 'lista_beneficiarios': {
            $id = $_POST["l_codaport"];
            $consulta2 = mysqli_query($conexion, "SELECT * FROM `aprben` WHERE `codaport`='$id'");
            //se cargan los datos de las beneficiarios a un array
            $array_beneficiarios[] = [];
            $array_parenteco[] = [];
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $array_beneficiarios[$i] = $fila;
                $array_beneficiarios[$i]['pariente'] = parenteco(($fila["codparent"]));
                $benporcent = ($fila["porcentaje"]);
                $total = $total + $benporcent;
                $i++;
            }
            echo json_encode([$array_beneficiarios, $total]);
        }
        break;

    //REPORTES
    //-----REPORTE DE ESTADOS DE CUENTA DE CLIENTE
    case 'reporte_estado_cuenta_aprt': {
            $inputs = $_POST["inputs"];
            $archivos = $_POST["archivo"];
            $radioss = $_POST["radios"];
            $radiosn = $_POST["radiosn"];
            $tipo_doc = $_POST["id"];

            //validar si ingreso un cuenta de aportacion
            if ($inputs[0] == "" && $inputs[1] == "") {
                echo json_encode(["Debe cargar una cuenta de aportación", '0']);
                return;
            }

            //validar si la cuenta de ahorro existe
            $datoscli = mysqli_query($conexion, "SELECT * FROM `aprcta` WHERE `ccodaport`=$inputs[0]");
            $bandera = true;
            while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
                $bandera = false;
            }
            if ($bandera) {
                echo json_encode(["Debe cargar una cuenta de aportación válida", '0']);
                return;
            }

            //validaciones de fechas
            $fecha_actual = strtotime(date("Y-m-d"));
            $fecha_1 = strtotime($inputs[2]);
            $fecha_2 = strtotime($inputs[3]);

            if ($radioss[0] == "2") {
                //validacion de fechas
                if ($fecha_2 > $fecha_actual) {
                    echo json_encode(["La fecha de hasta no puede ser mayor a la fecha de hoy", '0']);
                    return;
                }
                if ($fecha_1 > $fecha_2) {
                    echo json_encode(["La fecha inicial no puede ser mayor a la fecha final", '0']);
                    return;
                }
            }

            if ($radioss[0] == "1") {
                //validacion de fechas
                $fecha_actual = strtotime(date("Y-m-d"));
                if ($fecha_2 != $fecha_actual && $fecha_1 != $fecha_actual) {
                    echo json_encode(["Error en su solicitud", '0']);
                    return;
                }
            }
            $formato = "pdf";
            if ($tipo_doc == "excel") {
                $formato = "xlsx";
            }

            //unicamente para encontrar los valores
            echo json_encode(["reportes_aportaciones", "estado_cuenta_aprt", $tipo_doc, $formato, date("d-m-Y"), $inputs[0], $inputs[2], $inputs[3], $radioss[0], $archivos[0], $archivos[1]]);
        }
        break;
    //-----REPORTE DE CUENTAS ACTIVAS E INACTIVAS
    case 'reporte_cuentas_act_inact_aprt': {
            $archivos = $_POST["archivo"];
            $radioss = $_POST["radios"];
            $radiosn = $_POST["radiosn"];
            $selects = $_POST["selects"];
            $selectsn = $_POST["selects"];
            $tipo_doc = $_POST["id"];

            //si hay un error en el ingreso de datos
            if ($radioss[1] == "2") {
                if ($selects[0] == "0") {
                    echo json_encode(["Debe seleccionar un tipo de cuenta", '0']);
                    return;
                }
            }

            if ($radioss[1] == "1") {
                if ($selects[0] != "0") {
                    echo json_encode(["Error en su solicitud", '0']);
                    return;
                }
            }

            $formato = "pdf";
            if ($tipo_doc == "excel") {
                $formato = "xlsx";
            }

            //unicamente para encontrar los valores
            echo json_encode(["reportes_aportaciones", "listado_cuentas_aprt", $tipo_doc, $formato, date("d-m-Y"), $radioss[0], $radioss[1], $selects[0], $archivos[0], $archivos[1]]);
        }
        break;
    case 'cuadre_de_diario': {
            $inputs = $_POST["inputs"];
            $archivos = $_POST["archivo"];
            $radioss = $_POST["radios"];
            $selects = $_POST["selects"];
            $tipo_doc = $_POST["id"];

            //si hay un error en el ingreso de datos
            if ($radioss[0] == "2") {
                if ($selects[0] == "0") {
                    echo json_encode(["Debe seleccionar un tipo de cuenta", '0']);
                    return;
                }
            }

            if ($radioss[0] == "1") {
                if ($selects[0] != "0") {
                    echo json_encode(["Error en su solicitud", '0']);
                    return;
                }
            }

            $fecha_actual = strtotime(date("Y-m-d"));
            $fecha_1 = strtotime($inputs[0]);
            $fecha_2 = strtotime($inputs[1]);

            if ($radioss[1] == "2") {
                //validacion de fechas
                if ($fecha_2 > $fecha_actual) {
                    echo json_encode(["La fecha de hasta no puede ser mayor a la fecha de hoy", '0']);
                    return;
                }
                if ($fecha_1 > $fecha_2) {
                    echo json_encode(["La fecha inicial no puede ser mayor a la fecha final", '0']);
                    return;
                }
            }

            if ($radioss[1] == "1") {
                //validacion de fechas
                $fecha_actual = strtotime(date("Y-m-d"));
                if ($fecha_2 != $fecha_actual && $fecha_1 != $fecha_actual) {
                    echo json_encode(["Error en su solicitud", '0']);
                    return;
                }
            }

            $formato = "pdf";
            if ($tipo_doc == "excel") {
                $formato = "xlsx";
            }

            //unicamente para encontrar los valores
            echo json_encode(["reportes_aportaciones", "cuadre_diario_aprt", $tipo_doc, $formato, date("d-m-Y"), $inputs[0], $inputs[1], $radioss[0], $radioss[1], $selects[0], $archivos[0], $archivos[1]]);
        }
        break;

    //CRUD - CERTIFICADOS DE APORTACION
    //-----CREATE
    case 'create_certificado_aprt': {
            // `certif_n`,`ccodaport`,`codcli`,`nit`,`monapr_n`,`fecaper`,`norecibo`
            $hoy = date("Y-m-d H:i:s");
            $hoy2 = date("Y-m-d");
            $inputs = $_POST["inputs"];
            $archivo = $_POST["archivo"];

            //valida si ingreso un numero de certificado
            $validacion2 = validarcampo([$inputs[2]], "");
            if ($validacion2 != "1") {
                echo json_encode(["Debe seleccionar una cuenta", '0']);
                return;
            }

            //validacion de los inputs si estan vacios
            $validacion = validarcampo($inputs, "");
            if ($validacion != "1") {
                echo json_encode([$validacion, '0']);
                return;
            }

            $validar_monto = validar_limites(1, 1000000, $inputs[4]);

            //VALIDACION DE MONTO
            if ($validar_monto != "1") {
                echo json_encode(["Campo monto: " . $validar_monto, '0']);
                return;
            }

            //validar lo de si tiene un beneficiario
            if ($archivo[3] == null) {
                echo json_encode(["No puede generar el certificado debido a que no tiene al menos un beneficiario", '0']);
                return;
            }

            //validar lo de si tiene el porcentaje de beneficiario al 100%
            if ($archivo[4] != "100") {
                echo json_encode(["No puede generar el certificado debido a que el porcentaje de beneficiario es diferente del 100%", '0']);
                return;
            }

            //inicio transaccion
            $conexion->autocommit(false);
            try {
                $conexion->query("INSERT INTO `aprcrt`(`ccodcrt`,`ccodcli`,`ccodaport`,`montoapr`,`norecibo`,`fec_crt`,`codusu`) 
                                 VALUES ('$inputs[0]','$inputs[2]','$inputs[1]',$inputs[4],$inputs[6],'$hoy','$archivo[2]')");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode(['Error al insertar el certificado: ' . $aux, '0']);
                    $conexion->rollback();
                    return;
                }
                $conexion->commit();
                echo json_encode(['Registro ingresado correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----ACTUALIZAR CERTIFICADO
    case 'update_certificado_aprt': {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $archivo = $_POST["archivo"];

            //validacion de los inputs si estan vacios
            $validacion = validarcampo($inputs, "");
            if ($validacion != "1") {
                echo json_encode([$validacion, '0']);
                return;
            }

            $validar_monto = validar_limites(1, 1000000, $inputs[0]);

            //validar si el porcentaje de beneficiarios es del 100%
            if ($archivo[2] != "100") {
                echo json_encode(["No puede actualizar el certificado debido a que el porcentaje de beneficiario es diferente del 100%", '0']);
                return;
            }

            //inicio transaccion
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE `aprcrt` SET `montoapr`=$inputs[0], `fec_mod`='$hoy',`codusu`='$archivo[1]' WHERE aprcrt.id_crt = '$archivo[0]'");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode(['Error al actualizar certificado: ' . $aux, '0']);
                    $conexion->rollback();
                    return;
                }
                $conexion->commit();
                echo json_encode(['Registro actualizado correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----CREAR PDF DE CERTIFICADO
    case 'pdf_certificado_aprt': {
            $idcrt = $_POST["idcrt"];
            $estado = $_POST["estado"];
            $newcod = $_POST["newcod"];
            $hoy = date("Y-m-d H:i:s");
            $hoy2 = date("d-m-Y");
            $codusu = $_POST["codusu"];

            // devolver la consulta con todos los datos requeridos
            // obtener el nombre del cliente
            $consulta = mysqli_query($conexion, "SELECT cl.short_name, crt.ccodcrt, crt.montoapr, crt.estado ,cl.no_identifica,crt.norecibo,crt.ccodaport,cl.control_interno,cl.tel_no1,crt.fec_crt,crt.ccodcli
            FROM aprcrt AS crt 
            INNER JOIN tb_cliente AS cl 
            ON crt.ccodcli = cl.idcod_cliente
            WHERE crt.id_crt='$idcrt'");

            $consulta2 = mysqli_query($conexion, "SELECT 
            ben.nombre, 
            ben.dpi, 
            ben.codparent, 
            ben.telefono,
            gp.descripcion
            FROM 
            aprcrt AS crt 
            INNER JOIN aprben AS ben ON crt.ccodaport = ben.codaport
            INNER JOIN tb_parentescos gp ON ben.codparent = gp.id 
            WHERE crt.id_crt='$idcrt'");

            //se cargan los datos de las beneficiarios a un array
            $array_beneficiarios[] = [];
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $array_beneficiarios[] = $fila;
            }

            //se carga el dato del cliente a una variable normal
            while ($valor = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $cliente = encode_utf8($valor['short_name']);
                $codcertificado = encode_utf8($valor['ccodcrt']);
                $ccodaport = ($valor['ccodaport']);
                $norecibo = ($valor['norecibo']);
                $controlinterno = ($valor['control_interno']);
                $monto_cert = encode_utf8($valor['montoapr']);
                $estado_aux = encode_utf8($valor['estado']);
                $dpi_cli = encode_utf8($valor['no_identifica']);
                $telcli = ($valor['tel_no1']);
                $fechacrt = ($valor['fec_crt']);
                $codcli = ($valor['ccodcli']);
            }
            // $estado = "";
            //convertir monto a letras
            $format_monto = new NumeroALetras();
            $texto_monto = $format_monto->toMoney($monto_cert, 2, 'QUETZALES', 'CENTAVOS');
            //se valida la reimpresion y se crea una bitacora
            if ($estado_aux == null || $estado_aux == "") {
                //actualizar la tabla de crt
                $conexion->query("UPDATE `aprcrt` SET `estado` = 'I', `fec_mod` = '$hoy', `codusu` = '$codusu' WHERE aprcrt.id_crt = '$idcrt'");
            } else if (($estado_aux == "I" || $estado_aux = "R") && ($estado == "I")) {
                $conexion->query("UPDATE `aprcrt` SET `fec_mod` = '$hoy', `codusu` = '$codusu' WHERE aprcrt.id_crt = '$idcrt'");
                $estado = "R";
            } else if (($estado_aux = "I" || $estado_aux = "R") && ($estado == "R")) {
                //actualizar la tabla de crt
                $conexion->query("UPDATE `aprcrt` SET `estado` = 'R', `ccodcrt`='$newcod', `fec_mod` = '$hoy', `codusu` = '$codusu' WHERE `id_crt` = '$idcrt'");
                $codcertificado = $newcod;
            }


            //enviar datos de respuesta
            // echo json_encode([$array_beneficiarios, $cliente, $hoy2, $codcertificado, $monto_cert, $texto_monto, $estado, $dpi_cli, $norecibo, $ccodaport, $controlinterno, $telcli, $_SESSION["id_agencia"], $fechacrt]);
            $response = [
                'beneficiarios' => $array_beneficiarios,
                'cliente' => $cliente,
                'fecha_hoy' => $hoy2,
                'codcertificado' => $codcertificado,
                'monto_cert' => $monto_cert,
                'texto_monto' => $texto_monto,
                'estado' => $estado,
                'dpi_cli' => $dpi_cli,
                'norecibo' => $norecibo,
                'ccodaport' => $ccodaport,
                'controlinterno' => $controlinterno,
                'telcli' => $telcli,
                'id_agencia' => $_SESSION["id_agencia"],
                'fechacrt' => $fechacrt,
                'ccodcli' => $codcli
            ];

            echo json_encode([$array_beneficiarios, $cliente, $hoy2, $codcertificado, $monto_cert, $texto_monto, $estado, $dpi_cli, $norecibo, $ccodaport, $controlinterno, $telcli, $_SESSION["id_agencia"], $fechacrt, $response, $codcli]);
            mysqli_close($conexion);
        }
        break;

    //CRUD - INTERESES APORTACIONES
    //-----CALCULAR O PROCESAR INTERESES
    case 'procesar_interes_aprt':
        if (!isset($_SESSION['id'])) {
            echo json_encode(['Session expirada, inicie sesion nuevamente', 0]);
            return;
        }
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];

        //[`fechaInicio`,`fechaFinal`],[`tipcuenta`],[`r_cuenta`],`procesar_interes_aprt`,`0`,[]
        $fecha_inicio = $inputs[0];
        $fecha_final = $inputs[1];
        $tipcuenta = $selects[0];
        $r_cuenta = $radios[0];

        if (!validateDate($fecha_inicio, 'Y-m-d') || !validateDate($fecha_final, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($fecha_inicio > $fecha_final) {
            echo json_encode(['Rango de fechas Inválido', 0]);
            return;
        }
        if ($r_cuenta == "any" && $tipcuenta == '0') {
            echo json_encode(['Seleccione un tipo de cuenta válido', 0]);
            return;
        }

        $filtrocuenta = ($r_cuenta == "any") ? " AND SUBSTR(cta.ccodaport,7,2)='$tipcuenta'" : "";

        $query = "SELECT cta.ccodaport,cta.ccodcli,cli.short_name,cta.nlibreta,cta.tasa,IFNULL(id_mov,'X') idmov,
                        mov.dfecope,mov.ctipope,mov.cnumdoc,IFNULL(mov.monto,0) monto,mov.correlativo,
                        IFNULL((SELECT MIN(dfecope) FROM aprmov WHERE cestado!=2 AND ccodaport=cta.ccodaport AND dfecope<=?),'X') AS fecmin,
                        saldo_aportacion(cta.ccodaport, IFNULL(mov.dfecope, ?),IFNULL(mov.correlativo, (SELECT MAX(correlativo) 
                                 FROM aprmov WHERE ccodaport = cta.ccodaport AND dfecope <= ?))) AS saldo,tip.mincalc
                    FROM aprcta cta 
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                    INNER JOIN aprtip tip ON tip.ccodtip=SUBSTR(cta.ccodaport,7,2)
                    LEFT JOIN 
                        (
                            SELECT * FROM aprmov WHERE dfecope BETWEEN ? AND ? AND cestado != 2
                        ) mov ON mov.ccodaport = cta.ccodaport 
                    WHERE cta.estado = 'A' " . $filtrocuenta . "
                    ORDER BY cta.ccodaport, mov.dfecope, mov.correlativo;";

        //INIT TRY
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->getAllResults($query, [$fecha_final, $fecha_final, $fecha_final, $fecha_inicio, $fecha_final]);

            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas");
            }

            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if ($status == 0) {
            $opResult = array($mensaje, 0);
            echo json_encode($opResult);
            return;
        }

        //INICIO PROCESO 
        $data = array();
        $auxarray = array();

        end($result);
        $lastKey = key($result);
        reset($result);

        $setCorte = false;
        $auxcuenta = "X";
        $auxfecha = agregarDias($fecha_inicio, -1);
        foreach ($result as $key => $fila) {
            $cuenta = $fila["ccodaport"];
            $tasa = $fila["tasa"];
            $codcli = $fila["ccodcli"];
            $idmov = $fila["idmov"];
            $fecha = ($idmov == "X") ? $fecha_final : $fila["dfecope"];
            $tipope = ($idmov == "X") ? "D" : $fila["ctipope"];
            $monto = $fila["monto"];
            $fechamin = $fila["fecmin"];
            $mincalc = $fila["mincalc"];
            $saldoactual = $fila["saldo"];
            $saldoanterior = ($tipope == "R") ? ($saldoactual + $monto) : ($saldoactual - $monto);

            $auxfecha = ($fechamin == "X") ? $fecha_final : (($fechamin > $auxfecha) ? $fechamin : $auxfecha);

            $diasdif = dias_dif($auxfecha, $fecha);
            // $fechaant = $fecope;
            $interes = round($saldoanterior * ($tasa / 100) / 365 * $diasdif, 2);
            $interes = ($saldoanterior >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

            $result[$key]["cnumdoc"] = ($idmov == "X") ? "corte" : $fila["cnumdoc"];
            $result[$key]["ctipope"] = $tipope;
            $result[$key]["dfecope"] = $fecha;
            $result[$key]["saldoant"] = $saldoanterior;
            $result[$key]["dias"] = $diasdif;
            $result[$key]["interescal"] = $interes;
            $result[$key]["isr"] = round($interes * 0.10, 2);

            array_push($data, $result[$key]);

            $auxfecha = $fecha;
            if ($key === $lastKey) {
                $setCorte = ($fecha != $fecha_final) ? true : false;
            } else {
                if ($result[$key + 1]['ccodaport'] != $cuenta) {
                    $auxfecha = agregarDias($fecha_inicio, -1);
                    if ($fecha != $fecha_final) {
                        $setCorte = true;
                    }
                }
            }

            //EL CORTE DE CADA CUENTA AL FINAL DEL MES
            if ($setCorte) {
                $diasdif = dias_dif($fecha, $fecha_final);
                $interes = round($saldoactual * ($tasa / 100) / 365 * $diasdif, 2);
                $interes = ($saldoactual >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

                $auxarray["ccodaport"] = $cuenta;
                $auxarray["ccodcli"] = $codcli;
                $auxarray["short_name"] = $fila["short_name"];
                $auxarray["ctipope"] = "D";
                $auxarray["tasa"] = $tasa;
                $auxarray["fecmin"] = $fechamin;
                $auxarray["dfecope"] = $fecha_final;
                $auxarray["monto"] = 0;
                $auxarray["cnumdoc"] = 'corte';
                $auxarray["mincalc"] = $mincalc;
                $auxarray["saldo"] = $saldoactual;
                $auxarray["saldoant"] = $saldoactual;
                $auxarray["dias"] = $diasdif;
                $auxarray["interescal"] = round($interes, 2);
                $auxarray["isr"] = round(($interes * 0.10), 2);

                array_push($data, $auxarray);
                $setCorte = false;
            }
        }

        $tipocuenta = ($r_cuenta == "any") ? $selects[0] : "Todo";
        $rango = "" . date("d-m-Y", strtotime($fecha_inicio)) . "_" . date("d-m-Y", strtotime($fecha_final));
        $totalinteres = array_sum(array_column($data, "interescal"));
        $totalimpuesto = array_sum(array_column($data, "isr"));
        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();
            $datos = array(
                'tipo' => $tipocuenta,
                'rango' => $rango,
                'partida' => 0,
                'acreditado' => 0,
                'int_total' => $totalinteres,
                'isr_total' => $totalimpuesto,
                'fecmod' => $hoy,
                'codusu' => $idusuario,
                'fechacorte' => $fecha_final,
            );
            $idaprintere = $database->insert('aprinteredetalle', $datos);

            foreach ($data as $fila) {
                if ($fila["interescal"] > 0) {
                    $datos = array(
                        'ccodaport' => $fila["ccodaport"],
                        'codcli' => $fila["ccodcli"],
                        'nomcli' => ($fila["short_name"]),
                        'tipope' => $fila["ctipope"],
                        'fecope' => $fila["dfecope"],
                        'numdoc' => $fila["cnumdoc"],
                        'tipdoc' => "E",
                        'monto' => $fila["monto"],
                        'saldo' => $fila["saldo"],
                        'saldoant' => $fila["saldoant"],
                        'dias' => $fila["dias"],
                        'tasa' => $fila["tasa"],
                        'intcal' => $fila["interescal"],
                        'isrcal' => $fila["isr"],
                        'idcalc' => $idaprintere,
                    );
                    $database->insert('aprintere', $datos);
                }
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
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

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        return;

        break;
    case 'delete_calculo_interes':
        if (!isset($_SESSION['id'])) {
            echo json_encode(['Session expirada, inicie sesion nuevamente', 0]);
            return;
        }
        $ideliminar = $_POST["ideliminar"];
        if (!is_numeric($ideliminar)) {
            echo json_encode(['Parámetro no es numérico', 0]);
            return;
        }
        //INIT TRY
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns('aprinteredetalle', ['partida', 'acreditado'], 'id=?', [$ideliminar]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("Cálculo no encontrado");
            }
            $partida = $result[0]['partida'];
            $acreditado = $result[0]['acreditado'];
            if ($partida == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue provisionado, no se puede eliminar!");
            }
            if ($acreditado == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue acreditado, no se puede eliminar!");
            }

            $database->beginTransaction();
            $database->delete("aprintere", "idcalc=?", [$ideliminar]);
            $database->delete("aprinteredetalle", "id=?", [$ideliminar]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        return;
        break;
    case 'acreditar_intereses':
        /**
         * obtiene([`fechaInicio`],[`tipcuenta`],[`r_cuenta`],`acreditar_intereses`,`0`,
         * [' . $idcal . ',`' . $fechacorte . '`,`' . $agencia . '`,' . $codusu . ',`' . $rango . '`
         */
        $hoy2 = date("Y-m-d H:i:s");
        $archivo = $_POST["archivo"];
        $id = $archivo[0];

        $showmensaje = false;
        try {
            $database->openConnection();
            $detalle = $database->selectColumns('aprinteredetalle', ['partida', 'acreditado', 'fechacorte'], 'id=?', [$id]);
            if (empty($detalle)) {
                $showmensaje = true;
                throw new Exception("Cálculo no encontrado");
            }
            if ($detalle[0]['partida'] == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue provisionado, no se puede realizar una acreditacion!");
            }
            if ($detalle[0]['acreditado'] == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue acreditado, no se puede volver a acreditar!");
            }

            //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
            $cierre_mes = comprobar_cierrePDO($idusuario, $detalle[0]['fechacorte'], $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }

            /**
             * CUENTAS CONTABLES PARA LOS TIPOS DE CUENTAS INVOLUCRADOS
             */
            $cuentasContables = $database->getAllResults("SELECT id_descript_intere,id_cuenta1,id_cuenta2,tip.ccodtip,tip.nombre 
                            FROM aprparaintere api 
                                INNER JOIN aprtip tip ON tip.id_tipo=api.id_tipo_cuenta
                            WHERE tip.ccodtip IN (SELECT SUBSTR(ccodaport,7,2) FROM aprintere WHERE idcalc=? GROUP BY SUBSTR(ccodaport,7,2));", [$id]);

            if (empty($cuentasContables)) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas contables parametrizadas.");
            }
            /**
             * TIPOS DE CUENTAS INVOLUCRADOS
             */
            $tiposCuentas = $database->getAllResults("SELECT SUBSTR(ccodaport,7,2) ccodtip FROM aprintere WHERE idcalc=? 
                                                        GROUP BY SUBSTR(ccodaport,7,2);", [$id]);

            if (empty($tiposCuentas)) {
                $showmensaje = true;
                throw new Exception("No se encontraron los tipos de cuentas involucrados.");
            }

            /**
             * CONSULTA DE MOVIMIENTOS A ACREDITAR
             */
            $movimientos = $database->getAllResults("SELECT apint.ccodaport,SUM(apint.intcal) AS totalint, SUM(apint.isrcal) AS totalisr, cta.nlibreta, IFNULL(cta.ctainteres,'') ctainteres,
                                IFNULL((SELECT MAX(numlinea) FROM aprmov WHERE ccodaport = cta.ccodaport AND nlibreta=cta.nlibreta AND cestado=1),0) numlinea,
                                IFNULL((SELECT MAX(correlativo) FROM aprmov WHERE ccodaport = cta.ccodaport AND cestado=1),0) correlativo
                            FROM aprintere AS apint
                            INNER JOIN aprcta AS cta ON cta.ccodaport=apint.ccodaport 
                            WHERE apint.idcalc=? 
                            GROUP BY apint.ccodaport", [$id]);

            if (empty($movimientos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron movimientos a acreditar.");
            }
            $database->beginTransaction();

            $database->update('aprinteredetalle', ['acreditado' => 1], 'id=?', [$id]);
            foreach ($movimientos as $mov) {
                $cuenta = $mov['ccodaport'];
                $totalint = $mov['totalint'];
                $totalisr = $mov['totalisr'];
                $nlibreta = $mov['nlibreta'];
                $numlinea = $mov['numlinea'];
                $correlativo = $mov['correlativo'];

                if ($mov['ctainteres'] != "") {
                    $cuentaSecu = $database->selectColumns('aprcta', ['ccodaport'], 'ccodaport=?', [$mov['ctainteres']]);
                    if (empty($cuentaSecu)) {
                        $showmensaje = true;
                        throw new Exception("No se encontró la cuenta secundaria configurada para la cuenta " . $cuenta);
                    }
                    $cuenta = $cuentaSecu[0]['ccodaport'];
                }

                if ($totalint > 0) {
                    $aprmov = array(
                        'ccodaport' => $cuenta,
                        'dfecope' => $detalle[0]['fechacorte'],
                        'ctipope' => 'D',
                        'cnumdoc' => 'INT',
                        'ctipdoc' => 'IN',
                        'crazon' => 'INTERES',
                        'nlibreta' => $nlibreta,
                        'monto' => $totalint,
                        'lineaprint' => 'N',
                        'numlinea' => $numlinea + 1,
                        'correlativo' => $correlativo + 1,
                        'dfecmod' => $hoy2,
                        'codusu' => $idusuario,
                        'cestado' => 1,
                        'auxi' => 'INTERE' . $id,
                        'created_at' => $hoy2,
                        'created_by' => $idusuario,
                    );
                    $database->insert('aprmov', $aprmov);

                    $aprmov = array(
                        'ccodaport' => $cuenta,
                        'dfecope' => $detalle[0]['fechacorte'],
                        'ctipope' => 'R',
                        'cnumdoc' => 'ISR',
                        'ctipdoc' => 'IP',
                        'crazon' => 'INTERES',
                        'nlibreta' => $nlibreta,
                        'monto' => $totalisr,
                        'lineaprint' => 'N',
                        'numlinea' => $numlinea + 2,
                        'correlativo' => $correlativo + 2,
                        'dfecmod' => $hoy2,
                        'codusu' => $idusuario,
                        'cestado' => 1,
                        'auxi' => 'INTERE' . $id,
                        'created_at' => $hoy2,
                        'created_by' => $idusuario,
                    );
                    $database->insert('aprmov', $aprmov);
                }
            }

            /**
             * MOVIMIENTOS EN LA CONTABILIDAD 
             *
             */
            foreach ($tiposCuentas as $key => $tipoC) {
                $ccodtipPP = $tipoC['ccodtip'];

                /**
                 * CUENTA CONTABLE PARA ACREDITACION DE INTERESES
                 */
                $cuentasInteres = array_filter($cuentasContables, function ($item) use ($ccodtipPP) {
                    return $item['ccodtip'] === $ccodtipPP && $item['id_descript_intere'] === 1;
                });

                if (empty($cuentasInteres)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta contable para la acreditación de intereses del tipo de cuenta " . $tipoC['ccodtip']);
                }

                $keyInteres = array_keys($cuentasInteres)[0];
                // echo json_encode([$keyInteres, '0']);
                // return;
                /**
                 * CUENTA CONTABLE PARA RETENCION DE ISR
                 */
                $cuentasIsr = array_filter($cuentasContables, function ($item) use ($ccodtipPP) {
                    return $item['ccodtip'] === $ccodtipPP && $item['id_descript_intere'] === 2;
                });
                if (empty($cuentasIsr)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta contable para la retención de ISR del tipo de cuenta " . $tipoC['ccodtip']);
                }
                $keyIsr = array_keys($cuentasIsr)[0];

                /**
                 * INGRESO DE MOVIMIENTOS EN LA CONTABILIDAD PARTIDA DE INTERES
                 */
                // $camp_numcom = getnumcompdo($idusuario, $database);
                $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $detalle[0]['fechacorte']);

                $ctb_diario = array(
                    'numcom' => $camp_numcom,
                    'id_ctb_tipopoliza' => 3,
                    'id_tb_moneda' => 1,
                    'numdoc' => 'INT',
                    'glosa' => 'ACREDITACION DE INTERESES A CUENTAS DE ' . strtoupper($cuentasInteres[0]['nombre']),
                    'fecdoc' => $detalle[0]['fechacorte'],
                    'feccnt' => $detalle[0]['fechacorte'],
                    'cod_aux' => "APRT-$ccodtipPP",
                    'id_tb_usu' => $idusuario,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                );
                $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                // Filtrar los elementos donde ccodaport tiene ccodtip en las posiciones 7 y 8
                $filtrados = array_filter($movimientos, function ($item) use ($ccodtipPP) {
                    return substr($item['ccodaport'], 6, 2) === $ccodtipPP;
                });

                // Sumar los valores de totalint de los elementos filtrados
                $totalInteres = array_reduce($filtrados, function ($carry, $item) {
                    return $carry + $item['totalint'];
                }, 0);


                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasInteres[$keyInteres]['id_cuenta1'],
                    'debe' => $totalInteres,
                    'haber' => 0,
                );
                $database->insert('ctb_mov', $ctb_mov);

                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasInteres[$keyInteres]['id_cuenta2'],
                    'debe' => 0,
                    'haber' => $totalInteres,
                );
                $database->insert('ctb_mov', $ctb_mov);

                /**
                 * INGRESO DE MOVIMIENTOS EN LA CONTABILIDAD PARTIDA DE ISR
                 */

                // $camp_numcom = getnumcompdo($idusuario, $database);
                $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $detalle[0]['fechacorte']);

                $ctb_diario = array(
                    'numcom' => $camp_numcom,
                    'id_ctb_tipopoliza' => 3,
                    'id_tb_moneda' => 1,
                    'numdoc' => 'ISR',
                    'glosa' => 'RETENCION DE ISR A CUENTAS DE ' . strtoupper($cuentasInteres[0]['nombre']),
                    'fecdoc' => $detalle[0]['fechacorte'],
                    'feccnt' => $detalle[0]['fechacorte'],
                    'cod_aux' => "APRT-$ccodtipPP",
                    'id_tb_usu' => $idusuario,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                );
                $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                // Sumar los valores de totalisr de los elementos filtrados
                $totalIsr = array_reduce($filtrados, function ($carry, $item) {
                    return $carry + $item['totalisr'];
                }, 0);

                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasIsr[$keyIsr]['id_cuenta1'],
                    'debe' => $totalIsr,
                    'haber' => 0,
                );
                $database->insert('ctb_mov', $ctb_mov);

                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasIsr[$keyIsr]['id_cuenta2'],
                    'debe' => 0,
                    'haber' => $totalIsr,
                );
                $database->insert('ctb_mov', $ctb_mov);
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;

    //-----APROVISIONAR INTERESES
    case 'partida_aprov_intereses': {
            $archivo = $_POST["archivo"];
            $hoy = date("Y-m-d H:i:s");
            $id = $archivo[0];
            $fechacorte = $archivo[1];
            $usu = $archivo[2];
            $rango = $archivo[3];

            $campo_glosa = "";

            //------validar si existen todas las parametrizaciones correctas para realizar la acreditacion
            $consulta4 = "SELECT cta.ccodtip AS grupo, tip.nombre 
            FROM aprintere AS apint
            INNER JOIN aprcta AS cta ON cta.ccodaport=apint.ccodaport 
            INNER JOIN aprtip AS tip ON cta.ccodtip=tip.ccodtip 
            WHERE apint.idcalc=" . $id . "
            GROUP BY cta.ccodtip";
            $data4 = mysqli_query($conexion, $consulta4);

            while ($row = mysqli_fetch_array($data4, MYSQLI_ASSOC)) {
                $val_tipcuenta = $row["grupo"];
                $val_nombre = $row["nombre"];
                //obtener el datos para ingresar en el campo id_ctb_nomenclatura de la tabla ctb_mov
                list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("aprparaintere", "id_descript_intere", (tipocuenta($val_tipcuenta, "aprtip", "id_tipo", $conexion)), (3), $conexion);
                //validar si encontro un tipo de parametrizacion para el interes
                if ($id1 == "X") {
                    echo json_encode(['NO PUEDE REALIZAR LA PROVISIÓN DEBIDO A QUE NO HA PARAMETRIZADO UNA CUENTA CONTABLE PARA EL TIPO DE CUENTA ' . $val_nombre . ' EN RELACIÓN AL INTERES', '0']);
                    return;
                }
            }
            //------FIN

            //transaccion
            $conexion->autocommit(false);
            try {
                //validacion de provision
                $data3 = mysqli_query($conexion, "SELECT `partida` FROM `aprinteredetalle` WHERE id='$id'");
                while ($row = mysqli_fetch_array($data3, MYSQLI_ASSOC)) {
                    $partida = $row["partida"];
                }
                if ($partida == "1") {
                    echo json_encode(['Este campo ya ha sido provisionado', '1']);
                    return;
                }

                //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
                $cierre = comprobar_cierre($idusuario, $fechacorte, $conexion);
                if ($cierre[0] == 0) {
                    echo json_encode([$cierre[1], '0']);
                    return;
                }

                $conexion->query("UPDATE `aprinteredetalle` SET partida=1 WHERE id=" . $id);

                $consulta = "SELECT cta.ccodtip AS grupo,SUM(apint.intcal) AS totalint, SUM(apint.isrcal) AS totalisr, cta.nlibreta, cta.numlinea, cta.correlativo, tip.nombre 
                FROM aprintere AS apint
                INNER JOIN aprcta AS cta ON cta.ccodaport=apint.ccodaport 
                INNER JOIN aprtip AS tip ON cta.ccodtip=tip.ccodtip 
                WHERE apint.idcalc=" . $id . " 
                GROUP BY cta.ccodtip";
                $data = mysqli_query($conexion, $consulta);
                //insercion en la tabla de dario
                while ($row = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                    $nombre = ($row["nombre"]);
                    $interes = ($row["totalint"]);
                    $isr = ($row["totalisr"]);
                    $grupo = ($row["grupo"]);

                    if ($interes > 0) {
                        //insertar en ctb_diario
                        //glosa de provision
                        $campo_glosa .= "PROVISION DE INTERESES DE CUENTAS DE ";
                        $campo_glosa .= strtoupper($nombre);

                        //validar si es con rango de fecha o no
                        if ($rango != "Todo") {
                            $campo_glosa .= " COMPRENDIDO DEL ";
                            $campo_glosa .= substr($rango, 0, 10);
                            $campo_glosa .= " AL ";
                            $campo_glosa .= substr($rango, 11, 20);
                        }
                        //INSERCIONES EN CTB_DIARIO - INTERES PROVISION
                        //llamar al metodo numcom
                        // $camp_numcom = getnumcom($usu, $conexion);
                        $camp_numcom = Beneq::getNumcomLegacy($usu, $conexion, $idagencia, $fechacorte);
                        //insertar glosa de provision
                        $aux = "APRT-" . $grupo;
                        $conexion->query("INSERT INTO `ctb_diario`(`numcom`,`id_ctb_tipopoliza`,`id_tb_moneda`,`numdoc`,`glosa`,`fecdoc`,`feccnt`,`cod_aux`,`id_tb_usu`,`fecmod`,`estado`,`id_agencia`) VALUES ('$camp_numcom',3,1,'PROV', '$campo_glosa','$fechacorte', '$fechacorte','$aux','$usu','$hoy',1,$idagencia)");

                        //INSERCION EN CTB_MOV PARA EL INTERES ACREDITADO
                        $id_ctb_diario = get_id_insertado($conexion); //obtener el ultimo id insertado
                        list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("aprparaintere", "id_descript_intere", (tipocuenta($grupo, "aprtip", "id_tipo", $conexion)), (3), $conexion);
                        $conexion->query("INSERT INTO `ctb_mov`(`id_ctb_diario`,`numcom`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES ($id_ctb_diario,'$camp_numcom',1,$idcuenta1, '$interes',0)");
                        $conexion->query("INSERT INTO `ctb_mov`(`id_ctb_diario`,`numcom`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES ($id_ctb_diario,'$camp_numcom',1,$idcuenta2, 0,'$interes')");

                        $campo_glosa = "";
                    }
                }

                $conexion->commit();
                echo json_encode(['Datos ingresados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            //fin transaccion
            mysqli_close($conexion);
            break;
        }

        //CRUD - PARAMETRIZACION DE APORTACIONES
        //----CREATE
    case "create_aprt_cuentas_contables": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos// 
            $archivos = $_POST["archivo"];

            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de documento', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] == "") {
                echo json_encode(['Debe seleccionar una cuenta 1', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] == "") {
                echo json_encode(['Debe seleccionar una cuenta 2', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("aprctb", "id_tipo_doc", $selects[0], $selects[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede agregar esta parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la insercion
            $conexion->autocommit(false);
            try {
                $conexion->query("INSERT INTO aprctb (id_tipo_cuenta,id_tipo_doc,id_cuenta1,id_cuenta2,dfecmod,codusu)
                    VALUES ($selects[0],$selects[1],$inputs[0],$inputs[1],'$hoy',$archivos[0])");

                $conexion->commit();
                echo json_encode(['Datos ingresados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----UPDATE
    case "update_aprt_cuentas_contables": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            //validar input cuenta 1
            if ($inputs[1] == "0") {
                echo json_encode(['Debe seleccionar una cuenta contable', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[2] == "0") {
                echo json_encode(['Debe seleccionar una cuenta contable para la cuota de ingreso', '0']);
                return;
            }
            //se hara la actualizacion
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE aprtip
                    SET id_cuenta_contable = $inputs[1],cuenta_aprmov = $inputs[2] WHERE id_tipo=$inputs[0]");
                $conexion->commit();
                echo json_encode(['Datos actualizados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "update_aprt_cuentas_contablesanterior": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos// 
            $archivos = $_POST["archivo"];

            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de documento', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] != "" && $inputs[2] == "") {
                echo json_encode(['Debe seleccionar una cuenta 1', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] != "" && $inputs[3] == "") {
                echo json_encode(['Debe seleccionar una cuenta 2', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            $id1 = get_ctb_nomenclatura2("aprctb", "id_tipo_doc", $selects[0], $selects[1], $archivos[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede realizar esta actualizacion de parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la actualizacion
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE aprctb
                SET id_tipo_cuenta = $selects[0],id_tipo_doc=$selects[1],id_cuenta1=$inputs[0],id_cuenta2=$inputs[1],dfecmod='$hoy',codusu=$archivos[0] WHERE id=$archivos[1]");

                $conexion->commit();
                echo json_encode(['Datos actualizados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //-----DELETE
    case "delete_aprt_cuentas_contables": {
            $id = $_POST["ideliminar"];
            $eliminar = "DELETE FROM aprctb WHERE id =" . $id;
            if (mysqli_query($conexion, $eliminar)) {
                echo json_encode(['Eliminacion correcta ', '1']);
            } else {
                echo json_encode(['Error al eliminar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    //CRUD - PARAMETRIZACION DE INTERESES
    //----CREATE
    case "create_aprt_cuentas_intereses": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"];
            $archivos = $_POST["archivo"];

            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de operacion', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el debe', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el haber', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("aprparaintere", "id_descript_intere", $selects[0], $selects[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede agregar esta parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la insercion
            $conexion->autocommit(false);
            try {
                $conexion->query("INSERT INTO aprparaintere (id_tipo_cuenta,id_descript_intere,id_cuenta1,id_cuenta2,dfecmod,id_usuario)
                    VALUES ($selects[0],$selects[1],$inputs[0],$inputs[1],'$hoy',$archivos[0])");

                $conexion->commit();
                echo json_encode(['Datos ingresados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "update_aprt_cuentas_intereses": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"];
            $archivos = $_POST["archivo"];

            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de operación', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] != "" && $inputs[2] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el debe', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] != "" && $inputs[3] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el haber', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            $id1 = get_ctb_nomenclatura2("aprparaintere", "id_descript_intere", $selects[0], $selects[1], $archivos[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede realizar esta actualizacion de parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la actualizacion
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE aprparaintere
                SET id_tipo_cuenta = $selects[0],id_descript_intere=$selects[1],id_cuenta1=$inputs[0],id_cuenta2=$inputs[1],dfecmod='$hoy',id_usuario=$archivos[0] WHERE id=$archivos[1]");

                $conexion->commit();
                echo json_encode(['Datos actualizados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "delete_aprt_cuentas_intereses": {
            $id = $_POST["ideliminar"];
            $eliminar = "DELETE FROM aprparaintere WHERE id =" . $id;
            if (mysqli_query($conexion, $eliminar)) {
                echo json_encode(['Eliminacion correcta ', '1']);
            } else {
                echo json_encode(['Error al eliminar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case 'edicion_recibo':
        $inputs = $_POST["inputs"];
        $hoy = date("Y-m-d H:i:s");
        $hoy2 = date("Y-m-d");

        $fechaaux4 = "";
        $usuario4 = "";

        //validar si hay campos vacios
        $valido = validarcampo([$inputs[0], $inputs[2], $inputs[3]], "");
        if ($valido != "1") {
            echo json_encode(['Hay campos que no estan llenos, no se puede completar la operación', '0']);
        }

        //consultar datos del aprmov con id recibido
        $data_aprmov = mysqli_query($conexion, "SELECT `ccodaport`,`ctipope`,`cnumdoc`,`monto`, CAST(`created_at` AS DATE) AS created_at, created_by,`dfecope` FROM `aprmov` WHERE `id_mov`='$inputs[0]' AND cestado!=2");
        while ($da = mysqli_fetch_array($data_aprmov, MYSQLI_ASSOC)) {
            $ccodaport = $da["ccodaport"];
            $ctipope = $da["ctipope"];
            $cnumdoc = $da["cnumdoc"];
            $monto = $da["monto"];
            $fechaaux4 = $da["created_at"];
            $usuario4 = $da["created_by"];
            $dfecope = $da["dfecope"];
        }

        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
        $cierre = comprobar_cierre($idusuario, $hoy2, $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $fechaaux4);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }

        if ($cierre_caja[0] == 8) {
            if ($usuario4 != $inputs[3]) {
                echo json_encode(['El usuario creador del registro no coincide con el que quiere editar, no es posible completar la acción', '0']);
                return;
            }
        }

        if (substr($cnumdoc, 0, 4) == "REV-") {
            echo json_encode(['No puede editar la reversión de un recibo', '0']);
            return;
        }

        //consultar datos de aprcta
        $data_aprcta = mysqli_query($conexion, "SELECT `ccodcli` FROM `aprcta` WHERE `ccodaport`=$ccodaport");
        while ($da = mysqli_fetch_array($data_aprcta, MYSQLI_ASSOC)) {
            $ccodcli = $da["ccodcli"];
        }
        //consultar datos de tabla cliete para el nombre
        $data_cliente = mysqli_query($conexion, "SELECT `short_name`, `no_identifica` FROM `tb_cliente` WHERE `idcod_cliente`='$ccodcli'");
        while ($da = mysqli_fetch_array($data_cliente, MYSQLI_ASSOC)) {
            $shortname = (mb_strtoupper($da["short_name"], 'utf-8'));
            $dpi = $da["no_identifica"];
        }

        //obtener el registro anterior
        $bandera = false;
        $data_reg_ant = mysqli_query($conexion, "SELECT `id` FROM `ctb_diario` WHERE `numdoc`='$inputs[1]'");
        while ($da = mysqli_fetch_array($data_reg_ant, MYSQLI_ASSOC)) {
            $id_diario = $da["id"];
            $bandera = true;
        }

        $conexion->autocommit(false);
        try {
            //ACTUALIZACIONES EN APRMOV
            $conexion->query("UPDATE `aprmov` SET `cnumdoc` = '$inputs[2]',`dfecmod` = '$hoy',`codusu` = '$inputs[3]' WHERE `id_mov` = '$inputs[0]'");

            if ($bandera) {
                //INSERCIONES EN CTB_DIARIO
                $conexion->query("UPDATE `ctb_diario` SET `numdoc` = '$inputs[2]',`fecmod` = '$hoy',`id_tb_usu` = '$inputs[3]' WHERE `id` = $id_diario");
            }

            if ($conexion->commit()) {
                //NUMERO EN LETRAS
                $format_monto = new NumeroALetras();
                $decimal = explode(".", $monto);
                $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
                $letras_monto = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";
                $particionfecha = explode("-", $dfecope);

                ($ctipope == "D") ? $inputs[3] = "Depósito a cuenta " . $ccodaport : $inputs[3] = "Retiro a cuenta " . $ccodaport;
                echo json_encode(['Datos actualizados correctamente', '1', $ccodaport, number_format($monto, 2, '.', ','), date("d-m-Y", strtotime($hoy)), $inputs[2], $inputs[3], $shortname, decode_utf8($_SESSION['nombre']), decode_utf8($_SESSION['apellido']), $hoy, $letras_monto, $particionfecha[0], $particionfecha[1], $particionfecha[2], $dpi]);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);

        break;
    case 'reimpresion_recibo':
        $archivos = $_POST["archivo"];
        $hoy = date("Y-m-d H:i:s");
        $hoy2 = date("Y-m-d");

        //consultar datos del aprmov con id recibido
        $data_aprmov = mysqli_query($conexion, "SELECT `ccodaport`,`ctipope`,`ctipdoc`,`cnumdoc`,`monto`,`cuota_ingreso`,`dfecope`,`nrochq`,`ctipdoc`,
        IFNULL((SELECT id_agencia FROM tb_usuario WHERE id_usu=aho.codusu),1) oficina,concepto, correlativo
         FROM `aprmov` aho WHERE `id_mov`='$archivos[0]' AND cestado!=2");
        while ($da = mysqli_fetch_array($data_aprmov, MYSQLI_ASSOC)) {
            $ccodaport = $da["ccodaport"];
            $ctipope = $da["ctipope"];
            $cnumdoc = $da["cnumdoc"];
            $monto = $da["monto"];
            $cuotaingreso = $da["cuota_ingreso"];
            $dfecope = $da["dfecope"];
            $ncheque = $da["nrochq"];
            $tipchq = $da["ctipdoc"];
            $oficina = $da["oficina"];
            $concepto = $da["concepto"];
            $archivos[0] = $da["ctipdoc"];
            $correlativo = $da["correlativo"];
        }

        //consultar datos de aprcta
        $data_aprcta = mysqli_query($conexion, "SELECT `ccodcli`,tip.nombre, 
        saldo_aportacion(cta.ccodaport, '$dfecope' , $correlativo) AS saldo
         FROM `aprcta` cta 
         INNER JOIN aprtip tip on tip.ccodtip=cta.ccodtip WHERE `ccodaport`=$ccodaport");
        while ($da = mysqli_fetch_array($data_aprcta, MYSQLI_ASSOC)) {
            $ccodcli = $da["ccodcli"];
            $producto = $da["nombre"];
            $saldo = $da["saldo"];
        }
        //consultar datos de tabla cliete para el nombre
        $data_cliente = mysqli_query($conexion, "SELECT `short_name`, `no_identifica`,control_interno,idcod_cliente,Direccion FROM `tb_cliente` WHERE `idcod_cliente`='$ccodcli'");
        while ($da = mysqli_fetch_array($data_cliente, MYSQLI_ASSOC)) {
            $shortname = (mb_strtoupper($da["short_name"], 'utf-8'));
            $dpi = $da["no_identifica"];
            $controlinterno = $da["control_interno"];
            $codcliente = $da["idcod_cliente"];
            $direccion = $da["Direccion"];
        }

        if (substr($cnumdoc, 0, 4) == "REV-") {
            ($ctipope == "R") ? $archivos[1] = "Reversión de depósito a cuenta " . $ccodaport : $archivos[1] = "Reversión de retiro a cuenta " . $ccodaport;
        } else {
            ($ctipope == "D") ? $archivos[1] = "Depósito a cuenta " . $ccodaport : $archivos[1] = "Retiro a cuenta " . $ccodaport;
        }

        //NUMERO EN LETRAS
        $format_monto = new NumeroALetras();
        $decimal = explode(".", ($monto + $cuotaingreso));
        $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
        $letras_monto = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";
        $particionfecha = explode("-", $dfecope);
        // $nombreBanco = 'No disponible en reimpresion';

        //reimpresion de recibo
        echo json_encode(['Datos reimpresos correctamente', '1', $ccodaport, number_format($monto, 2, '.', ','), date("d-m-Y", strtotime($hoy)), $cnumdoc, $archivos[1], $shortname, ($_SESSION['nombre']), ($_SESSION['apellido']), $hoy, $letras_monto, $particionfecha[0], $particionfecha[1], $particionfecha[2], $dpi, $cuotaingreso, $producto, ($monto + $cuotaingreso), $letras_monto, $_SESSION['id'], $controlinterno, $ncheque, " ", $tipchq, $codcliente, $oficina, $ctipope, $dfecope, $concepto, $direccion, $saldo]); //24
        mysqli_close($conexion);
        break;
    case 'eliminacion_recibo':
        $idDato = $_POST["ideliminar"];
        $hoy2 = date("Y-m-d H:i:s");
        $conexion->autocommit(false);
        //Obtener informaicon de la ahommov
        $consulta = mysqli_query($conexion, "SELECT ccodaport, dfecope, cnumdoc, CAST(created_at AS DATE) AS fecsis FROM aprmov WHERE id_mov = $idDato AND cestado!=2");
        $dato = $consulta->fetch_row();

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $dato[3]);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }
        $fechapoliza = $dato[1];
        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO 
        $consulta = mysqli_query($conexion, "SELECT feccnt FROM ctb_diario WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $fechapoliza = $fila["feccnt"];
        }

        $cierre = comprobar_cierre($idusuario, $fechapoliza, $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }
        try {
            $res = $conexion->query("UPDATE aprmov SET cestado = '2', codusu = $idusuario, dfecmod = '$hoy2'  WHERE id_mov = $idDato");
            $aux = mysqli_error($conexion);

            $res1 = $conexion->query("UPDATE ctb_diario SET estado = '0', deleted_by = $idusuario, deleted_at = '$hoy2'  WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
            $aux1 = mysqli_error($conexion);

            if ($aux && $aux1) {
                echo json_encode(['Error fff', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res && !$res1) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos fueron actualizados con exito ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
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
    case 'acreditaindi':
        /**
         * 'csrf', 'dfecope', 'monint', 'monipf'
         */

        list($csrftoken, $fecope, $montoint, $montoipf) = $_POST['inputs'];
        list($encryptedID) = $_POST['archivo'];

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        if (!($csrf->validateToken($csrftoken, false))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                $errorcsrf,
                0,
                "reprint" => 1,
                "timer" => 3000
            );
            echo json_encode($opResult);
            return;
        }

        $ccodaport = $secureID->decrypt($encryptedID);


        // $inputs = $_POST["inputs"];
        // $archivo = $_POST["archivo"];

        $hoy = date("Y-m-d H:i:s");
        $hoy2 = date("Y-m-d");
        // $ccodaport = $archivo[0];
        // $fecope = $inputs[0];
        // $montoint = $inputs[1];
        // $montoipf = $inputs[2];


        if (!validateDate($fecope, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', '0']);
            return;
        }
        if (!is_numeric($montoint)) {
            echo json_encode(['Monto Inválido (Interés)', '0']);
            return;
        }
        if ($montoint <= 0) {
            echo json_encode(['Monto negativo ó igual a 0 (Interés)', '0']);
            return;
        }
        if (is_numeric($montoipf)) {
            if ($montoipf < 0) {
                echo json_encode(['Monto negativo (Impuesto)', '0']);
                return;
            }
        } else {
            $montoipf = 0;
        }
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++  DATOS DE LA CUENTA DE APORTACIONES ++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();
            $result = $database->selectColumns('aprcta', ['nlibreta', 'ctainteres'], 'ccodaport=?', [$ccodaport]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de aportaciones");
            }
            $nlibreta = $result[0]['nlibreta'];
            $cueninteres = $result[0]['ctainteres'];
            $cuentaDestino = $ccodaport;

            if ($cueninteres != "" && $cueninteres != null) {
                $result = $database->selectColumns('aprcta', ['ccodaport'], 'ccodaport=?', [$cueninteres]);
                if (empty($result)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta secundaria configurada no existe");
                }
                $cuentaDestino = $cueninteres;
            }

            $datosint = array(
                "ccodaport" => $cuentaDestino,
                "dfecope" => $fecope,
                "ctipope" => "D",
                "cnumdoc" => "INT",
                "ctipdoc" => "IN",
                "crazon" => "INTERES",
                "concepto" => "ACREDITACION DE INTERESES A CUENTA DE AHORROS: " . $cuentaDestino,
                "nlibreta" => $nlibreta,
                "nrochq" => 0,
                "tipchq" => "",
                "dfeccomp" => $fecope,
                "monto" => $montoint,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => $hoy2,
                "codusu" => $idusuario,
                "cestado" => 1,
                "auxi" => "ACREDITACION INDIVIDUAL",
                "created_at" => $hoy2,
                "created_by" => $idusuario,
            );
            $datosipf = array(
                "ccodaport" => $cuentaDestino,
                "dfecope" => $fecope,
                "ctipope" => "R",
                "cnumdoc" => "IPF",
                "ctipdoc" => "IP",
                "crazon" => "INTERES",
                "concepto" => "RETENCION DE ISR: " . $cuentaDestino,
                "nlibreta" => $nlibreta,
                "nrochq" => 0,
                "tipchq" => "",
                "dfeccomp" => $fecope,
                "monto" => $montoipf,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => $hoy2,
                "codusu" => $idusuario,
                "cestado" => 1,
                "auxi" => "ACREDITACION INDIVIDUAL",
                "created_at" => $hoy2,
                "created_by" => $idusuario,
            );

            $idmov = $database->insert('aprmov', $datosint);
            if ($montoipf > 0) {
                $idMovIsr = $database->insert('aprmov', $datosipf);
            }

            $database->executeQuery('CALL apr_ordena_noLibreta(?, ?);', [$nlibreta, $cuentaDestino]);
            $database->executeQuery('CALL apr_ordena_Transacciones(?);', [$cuentaDestino]);

            //MOVIMIENTOS EN LA CONTA
            $result = $database->getAllResults("SELECT ap.* FROM aprparaintere ap INNER JOIN aprtip tip ON tip.id_tipo=ap.id_tipo_cuenta 
                    WHERE ccodtip=SUBSTR(?,7,2) AND id_descript_intere IN (1,2)", [$ccodaport]);

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
            // $numpartida = getnumcompdo($idusuario, $database); //Obtener numero de partida
            $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fecope);
            $accountDestino = (isset($cueninteres)) ? $cueninteres : $ccodaport;

            $datos = array(
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 2,
                'id_tb_moneda' => 1,
                'numdoc' => "INT",
                'glosa' => "ACREDITACION DE INTERESES A CUENTA DE APORTACIONES: " . $accountDestino,
                'fecdoc' => $fecope,
                'feccnt' => $fecope,
                'cod_aux' => $ccodaport,
                'id_tb_usu' => $idusuario,
                'karely' => 'APR_' . $idmov,
                'id_agencia' => $idagencia,
                'fecmod' => $hoy,
                'estado' => 1,
                'editable' => 0,
                'created_by' => $idusuario,
            );

            $id_ctb_diario = $database->insert('ctb_diario', $datos);

            //AFECTACION CONTABLE MOV 1 
            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentaint1,
                'debe' => $montoint,
                'haber' => 0
            );
            $database->insert('ctb_mov', $datos);

            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentaint2,
                'debe' => 0,
                'haber' => $montoint
            );
            $database->insert('ctb_mov', $datos);

            if ($montoipf > 0) {
                $numpartida2 = getnumcompdo($idusuario, $database); //Obtener numero de partida

                $ctb_diario = array(
                    'numcom' => $numpartida2,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => "INT",
                    'glosa' => "RETENCION DE ISR: " . $accountDestino,
                    'fecdoc' => $fecope,
                    'feccnt' => $fecope,
                    'cod_aux' => $ccodaport,
                    'id_tb_usu' => $idusuario,
                    'karely' => 'APR_' . $idMovIsr,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy,
                    'estado' => 1,
                    'editable' => 0,
                    'created_by' => $idusuario
                );
                $id_ctb_diario2 = $database->insert('ctb_diario', $ctb_diario);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario2,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaisr1,
                    'debe' => $montoipf,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $datos);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario2,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaisr2,
                    'debe' => 0,
                    'haber' => $montoipf
                );
                $database->insert('ctb_mov', $datos);
            }

            $database->commit();
            $mensaje = "Registro grabado correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
    case 'Update_inactivarAPRT':
        $cuenta = $_POST['cuenta'] ?? '';
        $fecha_nueva = $_POST['fecha_cancel'] ?? '';
        $usuario_actualiza = $_SESSION['id'] ?? '';
        $hoy = date("Y-m-d H:i:s");

        if (empty($cuenta)) {
            echo json_encode(['Falta el código de cuenta para inactivar', '0']);
            return;
        }
        $queryEstado = "SELECT estado, fecha_cancel FROM aprcta WHERE ccodaport = ?";
        $stmtEstado = $conexion->prepare($queryEstado);
        $stmtEstado->bind_param('s', $cuenta);
        $stmtEstado->execute();
        $resultEstado = $stmtEstado->get_result()->fetch_assoc();
        if (!$resultEstado) {
            echo json_encode(['La cuenta no existe', '0']);
            return;
        }
        if ($resultEstado['estado'] === 'B') {
            if (!empty($fecha_nueva) && !validateDate($fecha_nueva, 'Y-m-d')) {
                echo json_encode(['La fecha de cancelación ingresada es inválida', '0']);
                return;
            }
        }

        $conexion->autocommit(false);
        try {
            $fecha_cancel_final = !empty($fecha_nueva) ? $fecha_nueva : $hoy;
            $res = $conexion->prepare("UPDATE aprcta SET estado = 'B', fecha_cancel = ? WHERE ccodaport = ?");
            if (!$res) {
                throw new Exception($conexion->error);
            }
            $res->bind_param('ss', $fecha_cancel_final, $cuenta);
            $res->execute();

            if ($conexion->commit()) {
                echo json_encode(['La cuenta ha sido actualizada/inactivada exitosamente', '1', $cuenta]);
            } else {
                echo json_encode(['Error al confirmar la transacción', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al actualizar/inactivar la cuenta: ' . $e->getMessage(), '0']);
        }
        break;
    case 'addTitularAccount':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken) = $_POST["inputs"];
        list($encryptedID, $codigoCliente) = $_POST["archivo"];

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        if (!($csrf->validateToken($csrftoken, false))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                $errorcsrf,
                0,
                "reprint" => 1,
                "timer" => 3000
            );
            echo json_encode($opResult);
            return;
        }

        $showmensaje = false;
        try {

            $codigoCuenta = $secureID->decrypt($encryptedID);
            $database->openConnection();

            $verificacion = $database->selectColumns("aprcta", ['ccodcli'], "ccodaport=?", [$codigoCuenta]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de aportaciones, verifique que el código sea correcto y que el cliente esté activo");
            }

            if ($verificacion[0]['ccodcli'] == $codigoCliente) {
                $showmensaje = true;
                throw new Exception("La persona seleccionada ya es titular de la cuenta, no se puede agregar nuevamente");
            }

            $verificacion2 = $database->selectColumns("cli_mancomunadas", ['id'], "ccodaho=? AND ccodcli=? AND estado=1 AND tipo='aportacion'", [$codigoCuenta, $codigoCliente]);
            if (!empty($verificacion2)) {
                $showmensaje = true;
                throw new Exception("El titular ya se encuentra agregado a la cuenta de aportaciones, no se puede agregar nuevamente");
            }

            $database->beginTransaction();

            $cli_mancomunadas = [
                'tipo' => 'aportacion',
                'ccodaho' => $codigoCuenta,
                'ccodcli' => $codigoCliente,
                'estado' => 1,
                'created_by' => $idusuario,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $database->insert('cli_mancomunadas', $cli_mancomunadas);

            $database->commit();
            $mensaje = "Titular agregado correctamente a la cuenta de aportaciones";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
        ]);

        break;
    case 'deleteTitularAccount':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken) = $_POST["inputs"];
        list($encryptedID) = $_POST["archivo"];

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        if (!($csrf->validateToken($csrftoken, false))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                $errorcsrf,
                0,
                "reprint" => 1,
                "timer" => 3000
            );
            echo json_encode($opResult);
            return;
        }

        $showmensaje = false;
        try {

            $idTitular = $secureID->decrypt($encryptedID);
            $database->openConnection();

            $verificacion = $database->selectColumns("cli_mancomunadas", ['ccodcli'], "id=?", [$idTitular]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se encontró el titular, verifique que el código sea correcto y que el cliente esté activo");
            }

            $database->beginTransaction();

            $cli_mancomunadas = [
                'estado' => 0,
                'deleted_by' => $idusuario,
                'deleted_at' => date('Y-m-d H:i:s')
            ];

            $database->update('cli_mancomunadas', $cli_mancomunadas, 'id=?', [$idTitular]);

            $database->commit();
            $mensaje = "Titular eliminado correctamente de la cuenta de aportaciones";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
        ]);

        break;

    case 'procesCalculoIndi':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken, $fechaInicio, $fechaFin) = $_POST["inputs"];
        list($encryptedID) = $_POST["archivo"];

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        if (!($csrf->validateToken($csrftoken, false))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                $errorcsrf,
                0,
                "reprint" => 1,
                "timer" => 3000
            );
            echo json_encode($opResult);
            return;
        }
        if (!validateDate($fechaInicio, 'Y-m-d') || !validateDate($fechaFin, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($fechaInicio > $fechaFin) {
            echo json_encode(['Rango de fechas Inválido', 0]);
            return;
        }
        $codCuenta = $secureID->decrypt($encryptedID);

        $query = "SELECT cta.ccodaport,cli.short_name,cta.nlibreta,cta.tasa,
                    IFNULL(id_mov,'X') idmov,mov.dfecope,mov.ctipope,mov.cnumdoc,
                    IFNULL(mov.monto,0) monto,mov.correlativo,
                    IFNULL((SELECT MIN(dfecope) FROM aprmov WHERE cestado=1 AND ccodaport=cta.ccodaport AND dfecope<=?),'X') AS fecmin,
                    saldo_aportacion(cta.ccodaport, IFNULL(mov.dfecope, ?),IFNULL(mov.correlativo, (SELECT MAX(correlativo) 
                                FROM aprmov WHERE cestado=1 AND ccodaport = cta.ccodaport AND dfecope <= ?))) AS saldo,
                    tip.mincalc,tip.isr,365 as diascalculo
                    FROM aprcta cta 
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                    INNER JOIN aprtip tip ON tip.ccodtip=SUBSTR(cta.ccodaport,7,2)
                    LEFT JOIN 
                        (
                            SELECT * FROM aprmov WHERE dfecope BETWEEN ? AND ? AND cestado =1
                        ) mov ON mov.ccodaport = cta.ccodaport
                    WHERE cta.estado = 'A' AND cta.ccodaport=?
                    ORDER BY cta.ccodaport, mov.dfecope, mov.correlativo;";

        //INIT TRY
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->getAllResults($query, [$fechaFin, $fechaFin, $fechaFin, $fechaInicio, $fechaFin, $codCuenta]);

            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta");
            }
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if (!$status) {
            $opResult = array($mensaje, 0);
            echo json_encode($opResult);
            return;
        }

        //INICIO PROCESO 
        $data = array();
        $auxarray = array();

        end($result);
        $lastKey = key($result);
        reset($result);

        $setCorte = false;
        $auxcuenta = "X";
        $auxfecha = agregarDias($fechaInicio, -1);
        foreach ($result as $key => $fila) {
            $cuenta = $fila["ccodaport"];
            $tasa = $fila["tasa"];
            $idmov = $fila["idmov"];
            $fecha = ($idmov == "X") ? $fechaFin : $fila["dfecope"];
            $tipope = ($idmov == "X") ? "D" : $fila["ctipope"];
            $monto = $fila["monto"];
            $fechamin = $fila["fecmin"];
            $mincalc = $fila["mincalc"];
            $diascalculo = $fila["diascalculo"] ?? 365;
            $porcentajeIsr = round(($fila["isr"] / 100), 2);
            $saldoactual = $fila["saldo"];
            $saldoanterior = ($tipope == "R") ? ($saldoactual + $monto) : ($saldoactual - $monto);

            $auxfecha = ($fechamin == "X") ? $fechaFin : (($fechamin > $auxfecha) ? $fechamin : $auxfecha);

            $diasdif = dias_dif($auxfecha, $fecha);
            // $fechaant = $fecope;
            $interes = round($saldoanterior * ($tasa / 100) / $diascalculo * $diasdif, 2);
            $interes = ($saldoanterior >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

            $result[$key]["cnumdoc"] = ($idmov == "X") ? "corte" : $fila["cnumdoc"];
            $result[$key]["ctipope"] = $tipope;
            $result[$key]["dfecope"] = setdatefrench($fecha);
            $result[$key]["saldoant"] = round($saldoanterior, 2);
            $result[$key]["dias"] = $diasdif;
            $result[$key]["interescal"] = $interes;

            array_push($data, $result[$key]);

            $auxfecha = $fecha;
            if ($key === $lastKey) {
                $setCorte = ($fecha != $fechaFin) ? true : false;
            } else {
                if ($result[$key + 1]['ccodaport'] != $cuenta) {
                    $auxfecha = agregarDias($fechaInicio, -1);
                    if ($fecha != $fechaFin) {
                        $setCorte = true;
                    }
                }
            }

            //EL CORTE DE CADA CUENTA AL FINAL DEL MES
            if ($setCorte) {
                $diasdif = dias_dif($fecha, $fechaFin);
                $interes = round($saldoactual * ($tasa / 100) / $diascalculo * $diasdif, 2);
                $interes = ($saldoactual >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

                $auxarray["ccodaport"] = $cuenta;
                $auxarray["short_name"] = $fila["short_name"];
                $auxarray["ctipope"] = "D";
                $auxarray["tasa"] = $tasa;
                $auxarray["fecmin"] = $fechamin;
                $auxarray["dfecope"] = setdatefrench($fechaFin);
                $auxarray["monto"] = 0;
                $auxarray["cnumdoc"] = 'corte';
                $auxarray["mincalc"] = $mincalc;
                $auxarray["saldo"] = round($saldoactual, 2);
                $auxarray["saldoant"] = round($saldoactual, 2);
                $auxarray["dias"] = $diasdif;
                $auxarray["interescal"] = round($interes, 2);

                array_push($data, $auxarray);
                $setCorte = false;
            }
        }

        $totalinteres = array_sum(array_column($data, "interescal"));
        $totalinteres = round($totalinteres, 2);
        $totalimpuesto = $totalinteres * $porcentajeIsr;
        $totalimpuesto = round($totalimpuesto, 2);

        $opResult = array(
            "Generacion Completa",
            1,
            'reprint' => 0,
            'timer' => 10,
            'data' => $data,
            'totalInteres' => $totalinteres,
            'totalImpuesto' => $totalimpuesto,
            'fechaFin' => $fechaFin
        );
        echo json_encode($opResult);
        break;
}
//FUNCION para obtener los depositos de los utimos 30 dias y los R(reversion y retiros) asi como el valor del dolar.
function movimiento($conexion, $op = 0, $codCli = [], $tipoMov = '', $fechaH = '', $codCu = '', $db_name_general = "jpxdcegu_bd_general_coopera")
{
    switch ($op) {
        case 1: //Depositos y Retiros
            $dato = mysqli_query($conexion, "SELECT (IFNULL(SUM(monto),0))  AS dato 
            FROM aprmov AS mov
            INNER JOIN aprcta AS ac ON mov.ccodaport = ac.ccodaport
            WHERE ac.estado = 'A' AND mov.cestado!=2 AND ac.ccodcli = '" . $codCli['ccodcli'] . "' AND ctipope = '" . $tipoMov . "'
            AND dfecope BETWEEN " . $fechaH . " AND CURDATE()");
            $error = mysqli_error($conexion);
            if ($error) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            };
            $movMot = mysqli_fetch_assoc($dato);
            return $movMot['dato'];
            break;
        case 2:
            $dato = mysqli_query($conexion, "SELECT equiDolar AS dato FROM $db_name_general.tb_monedas WHERE id = 1");
            $error = mysqli_error($conexion);
            if ($error) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            };
            $movMot = mysqli_fetch_assoc($dato);
            return $movMot['dato'];
            break;
        case 3:
            return ((movimiento($conexion, 1, $codCli, 'D', '(DATE_SUB(CURDATE(), INTERVAL 30 DAY))')) - (movimiento($conexion, 1, $codCli, 'R', '(DATE_SUB(CURDATE(), INTERVAL 30 DAY))')));
            break;
        case 4:
            $fecha = '';
            $dato = mysqli_query($conexion, "SELECT MAX(fecha) as fecha FROM tb_alerta WHERE cod_aux = '$codCu' AND proceso = ('A' OR 'A1') AND fecha BETWEEN (DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND CURDATE()");

            $fila = mysqli_affected_rows($conexion);
            if ($fila > 0) {
                $datoF = mysqli_fetch_assoc($dato);
                $fecha = "'" . $datoF['fecha'] . "'";
            }
            if ($fila == 0)
                $fecha = 'CURDATE()';
            return ((movimiento($conexion, 1, $codCli, 'D', $fecha)) - (movimiento($conexion, 1, $codCli, 'R', $fecha)));
            break;
        case 5:
            $dato = mysqli_query($conexion, "SELECT MAX(codDoc) AS codDoc FROM tb_alerta WHERE cod_aux = '" . $codCu . "'");
            $codDoc = mysqli_fetch_assoc($dato);

            $dato1 = mysqli_query($conexion, "SELECT IFNULL(dfecmod, '0') AS fecha FROM aprmov WHERE cestado!=2 AND cnumdoc = '" . $codDoc['codDoc'] . "';");

            if (mysqli_affected_rows($conexion) != 0) {
                $fechaHora = mysqli_fetch_assoc($dato1);
                $dato1 = mysqli_query($conexion, "SELECT (IFNULL(SUM(monto),0))  AS mov 
                FROM aprmov AS mov
                INNER JOIN aprcta AS ac ON mov.ccodaport = ac.ccodaport
                WHERE ac.estado = 'A' AND mov.cestado!=2 AND ac.ccodcli = '" . $codCli['ccodcli'] . "' AND ctipope = '" . $tipoMov . "'
                AND mov.dfecmod > '" . $fechaHora['fecha'] . "';");
                $auxMov = mysqli_fetch_assoc($dato1);
                // echo json_encode(['Fecha '.$auxMov['mov'], '0']);
                // return; 
                return $auxMov['mov'];
            }
            return 0;
            break;
    }
}

//FUNCIN para el control de las alertas
function alerta($conexion, $op = 0, $codCu = '', $hoy2 = '', $codUsu = 0, $hoy = '', $proceso = '', $cnumdoc = '', $cliente = '')
{
    switch ($op) {
        case 1:
            $res = $conexion->query("INSERT INTO `tb_alerta` (`puesto`, `tipo_alerta`, `mensaje`, `cod_aux`, `proceso`,`estado`, `fecha`,`created_by`, `created_at`, `codDoc`) value('LOG', 'IVE', 'Llenar el formulario del IVE', '$codCu', '$proceso', 1, '$hoy2', $codUsu, '$hoy', '$cnumdoc')");
            if (mysqli_error($conexion) || !$res) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            }
            break;
        case 2:
            $dato = '';
            $consulta = mysqli_query($conexion, "SELECT IFNULL(MAX(proceso),'0') AS pro  FROM  `tb_alerta` WHERE proceso IN ('A','A1') AND `cod_aux` = '$codCu' AND `fecha` = '$hoy2'");
            $datoAlerta = mysqli_fetch_assoc($consulta);
            $dato = $datoAlerta['pro'];

            if ($dato == 'A' || $dato == '0')
                $dato = 'VC'; //Retorno un valor vacio
            return $dato;
            break;
        case 3:
            $consulta = mysqli_query($conexion, "SELECT EXISTS(
            SELECT id FROM tb_alerta WHERE cod_aux = '$codCu' AND proceso IN ('A','A1') AND fecha BETWEEN (DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND CURDATE()) AS dato");
            $rsultadoIVE = mysqli_fetch_assoc($consulta);
            return $rsultadoIVE['dato'];
            break;
        case 4:
            $consulta = mysqli_query($conexion, "SELECT EXISTS(SELECT codDoc FROM tb_alerta WHERE 
            cod_aux = '" . $codCu . "' AND codDoc = '" . $cnumdoc . "' AND estado = 0 AND proceso IN ('A' ,'A1')) AS dato ;");
            $datoAux = mysqli_fetch_assoc($consulta);
            return $datoAux['dato'];
            break;
        case 5:
            $consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, apellido) AS cli, Email FROM tb_usuario WHERE estado = 1 AND puesto IN ('CNT', 'ADM')");
            if (mysqli_error($conexion)) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            }
            $arch = [$cliente, $codCu, $cnumdoc];
            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                enviarCorreo("" . $row['Email'] . "", "" . $row['cli'] . "", "Alerta IVE", "<h5>El sistema se encuentra a la espera de la aprobación de una alerta de IVE.</h5>", $arch);
            }
            break;
    }
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
function executequery($query, $params, $typparams, $conexion)
{
    $stmt = $conexion->prepare($query);
    $aux = mysqli_error($conexion);
    if ($aux) {
        return ['ERROR: ' . $aux, false];
    }
    $types = '';
    $bindParams = [];
    $bindParams[] = &$types;
    $i = 0;
    foreach ($params as &$param) {
        // $types .= 's';
        $types .= $typparams[$i];
        $bindParams[] = &$param;
        $i++;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    if (!$stmt->execute()) {
        return ["Error en la ejecución de la consulta: " . $stmt->error, false];
    }
    $data = [];
    $resultado = $stmt->get_result();
    $i = 0;
    while ($fila = $resultado->fetch_assoc()) {
        $data[$i] = $fila;
        $i++;
    }
    $stmt->close();
    return [$data, true];
}
