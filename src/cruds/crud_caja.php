<?php

include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
// require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
// require_once __DIR__ . '/../../src/funcphp/func_gen.php'; // no se incluy para no geenerar conflictos con fun_ppg.php

use Micro\Exceptions\SoftException;
use Micro\Exceptions\SystemException;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Helpers\Log;
use App\Configuracion;
use Creditos\Utilidades\CreditoAmortizationSystem;
use Micro\Generic\PermissionManager;
use Micro\Generic\Validator;
use Micro\Helpers\Beneq;

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

include '../funcphp/fun_ppg.php';

$condi = $_POST["condi"];
switch ($condi) {
    case 'paggrupal':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $montos = $inputs[0];
        $detalle = $inputs[1];
        $archivo = $_POST["archivo"];

        //DATOS DE ENCABEZADO
        $numdocumento = $detalle[0];
        $fechapago = $detalle[1];
        $formapago = $detalle[2];
        $bancoid = $detalle[3];
        $cuentaid = $detalle[4];
        $fechabanco = $detalle[5];
        $boletabanco = $detalle[6];

        //COMPROBAR CIERRE DE CAJA
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }

        //VALIDA SI SE INGRESARON DATOS DE RECIBO
        $validacion = validarcampo([$numdocumento, $fechapago], "");
        if ($validacion != "1") {
            echo json_encode(["Ingrese detalles del documento de pago", '0']);
            return;
        }

        $id_ctb_tipopoliza = 1; //TIPO DE POLIZA: CREDITOS (DEFAULT)
        //INICIA EL TRY
        $showmensaje = false;
        try {
            $database->openConnection();

            $configuracion = new Configuracion($database);
            $desglosarIva = $configuracion->getValById(2);
            $desglose_iva = ($desglosarIva == 1) ? true : false;
            if ($desglose_iva) {
                $cuentaIva = $database->selectColumns('ctb_parametros_general', ['id_ctb_nomenclatura'], 'id_tipo=10');
                if (empty($cuentaIva)) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el IVA Por pagar");
                }
                $idNomenclaturaIvaXPagar = $cuentaIva[0]['id_ctb_nomenclatura'];

                $verificacion = $database->selectColumns('ctb_nomenclatura', ['id'], 'id=? AND estado=1', [$idNomenclaturaIvaXPagar]);
                if (empty($verificacion)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta contable configurada para el IVA por pagar no existe o no esta activa, por favor verifique la configuracion contable");
                }
            }

            $validarSaldoKp = $configuracion->getValById(3);

            //VALIDACION DE NUMERO DE DOCUMENTO
            $result = $database->selectColumns('CREDKAR', ['CNUMING'], 'CNUMING=?', [$numdocumento]);
            if (!empty($result)) {
                // $showmensaje = true;
                // throw new Exception("Numero de documento de pago ya existente, Favor verificar");
            }

            //CONSULTAR LA CUENTA CONTABLE DE LA CAJA AGENCIA 
            $result = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
            $id_nomenclatura_caja = $result[0]['id_nomenclatura_caja'];

            if ($formapago === '2') {
                if ($cuentaid == "F000") {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de banco");
                }
                if ($boletabanco == "") {
                    $showmensaje = true;
                    throw new Exception("Ingrese un numero de boleta de banco");
                }
                $id_ctb_tipopoliza = 11; // CUANDO ES POR BOLETA DE BANCO, EL TIPO DE POLIZA SE CAMBIA A NOTA DE CREDITO
                $result = $database->selectColumns('ctb_bancos', ['id', 'numcuenta', 'id_nomenclatura'], 'id=?', [$cuentaid]);
                $id_nomenclatura_caja = $result[0]['id_nomenclatura'];
            }

            //VALIDAR EL MONTO TOTAL INGRESADO
            if (array_sum(array_column($montos, 6)) <= 0) {
                $showmensaje = true;
                throw new Exception("Monto total a pagar invalido, favor verificar");
            }

            //generico([datainputs, datadetal], 0, 0, 'paggrupal', [0], [user, idgrup, idfondo, ciclo, id_agencia], 'crud_caja');
            // filas = getinputsval(['ccodcta' + (rows), 'namecli' + (rows), 'capital' + (rows), 'interes' + (rows), 'monmora' + (rows), 'otrospg' + (rows), 'totalpg' + (rows),'concepto' + (rows)]);
            //                     datos[rows] = filas;
            //                             detalles[i] = [monto, idgasto, idcontable, modulo, codaho];
            //                     datos[rows]['detallesotros'] = detalles;
            //VERIFICACION DE MONTOS POR CADA CREDITO
            $i = 0;
            $j = 0;
            $data[] = [];
            while ($i < count($montos)) {
                $filas = $montos[$i];
                $filas2 = $montos[$i];
                // $showmensaje = true;
                // throw new Exception("Monto negativo detectado");

                //UNSET ccodcta, namecli,   totalpg,   concepto
                unset($filas[0], $filas[1], $filas[6], $filas[7]);
                if (
                    count(array_filter($filas, function ($var) {
                        return ($var < 0);
                    })) > 0
                ) {
                    $showmensaje = true;
                    throw new Exception("Monto negativo detectado");
                }
                if (array_sum($filas) > 0) {
                    //comprobar vacios
                    $keys = array_keys(array_filter($filas, function ($var) {
                        return ($var == "");
                    }));

                    $fi = 0;
                    while ($fi < count($keys)) {
                        $f = $keys[$fi];
                        $filas2[$f] = 0;
                        $fi++;
                    }
                    //fin comprueba vacios
                    // array_push($filas2, array_sum($filas));
                    //VALIDAR DETALLES OTROS DE CADA CREDITO detallesotros
                    $detalleotros = $filas2[8];
                    foreach ($detalleotros as $rowval) {
                        //[monto, idgasto, idcontable,modulo,codaho]
                        $monf = $rowval[0];
                        if (is_numeric($monf) && $monf < 0) {
                            $showmensaje = true;
                            throw new Exception("No puede ingresar valores negativos");
                        }

                        if ($rowval[3] > 0) {
                            $table = ($rowval[3] == 1) ? "ahomcta" : "aprcta";
                            $column = ($rowval[3] == 1) ? "ccodaho" : "ccodaport";
                            $texttitle = ($rowval[3] == 1) ? " ahorros" : "aportaciones";
                            $result = $database->selectColumns($table, ['nlibreta'], $column . '=?', [$rowval[4]]);
                            if (empty($result)) {
                                $showmensaje = true;
                                throw new Exception("La cuenta de ' . $texttitle . ': ' . $rowval[4] . ' no existe, por lo tanto no se puede completar la operacion por el monto especificado: ' . $monf . ', se recomienda configurar el vinculo con una cuenta existente para poder ingresar montos");
                            }
                            $nlibreta = $result[0]['nlibreta'];
                        }
                    }

                    $data[$j] = $filas2;
                    $j++;
                }
                //VALIDAR PAGO DE CAPITAL
                $result = $database->getAllResults("SELECT IFNULL((ROUND((IFNULL(cm.NCapDes,0)),2)-(SELECT ROUND( IFNULL(SUM(c.KP),0),2) FROM CREDKAR c 
                    WHERE c.CTIPPAG = 'P' AND  c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X')),0)  AS saldopendiente FROM cremcre_meta cm WHERE cm.CCODCTA =?", [$filas2[0]]);
                $capital_pendiente = (empty($result)) ? 0 : $result[0]['saldopendiente'];
                if ($validarSaldoKp == 0) {
                    if ($filas2[2] > $capital_pendiente) {
                        $showmensaje = true;
                        throw new Exception("No puede completar todos los pagos, por el credito ' . $filas2[0] . ', porque el saldo capital por pagar es de ' . $capital_pendiente . ' y usted quiere hacer un pago de ' . $filas2[2] . ' lo cual supera lo que resta por pagar, todo extra que se quiera pagar agreguelo en otros.");
                    }
                }

                $i++;
            }

            //CONSULTAR LA NOMENCLATURA PARA CAPITAL, INTERES Y MORA
            $result = $database->getAllResults("SELECT cp.id_cuenta_capital, cp.id_cuenta_interes, cp.id_cuenta_mora, cp.id_cuenta_otros,cp.id_fondo 
                FROM cre_productos cp INNER JOIN cremcre_meta cm ON cp.id=cm.CCODPRD WHERE cm.CCODCTA=?", [$data[0][0]]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontraron los datos del producto de crédito");
            }
            $id_nomenclatura_capital = $result[0]['id_cuenta_capital'];
            $id_nomenclatura_interes = $result[0]['id_cuenta_interes'];
            $id_nomenclatura_mora = $result[0]['id_cuenta_mora'];
            $id_nomenclatura_otros = $result[0]['id_cuenta_otros'];
            $id_fondo = $result[0]['id_fondo'];

            //TRAER CNROCUO SIGUIENTE PARA EL GRUPO
            $result = $database->getAllResults("SELECT IFNULL(MAX(CNROCUO),0) nocuo FROM CREDKAR WHERE CTIPPAG='P' AND CESTADO!='X' AND CCODCTA IN (SELECT CCODCTA FROM cremcre_meta WHERE CCodGrupo =? AND NCiclo=?);", [$archivo[1], $archivo[3]]);
            $nrocuo = (empty($result)) ? 1 : $result[0]['nocuo'] + 1;

            $database->beginTransaction();

            $i = 0;
            while ($i < count($data)) {
                //cada fila: 0ccodcta 1namecli 2capital 3interes 4monmora 5otrospg 6totalpg 7concepto 8detallesotros 9arraysumfilas?
                $datos = array(
                    'CCODCTA' => $data[$i][0],
                    'DFECPRO' => $fechapago,
                    'DFECSIS' => $hoy2,
                    'CNROCUO' => $nrocuo, //CREAR LA FUNCION QUE ORDENE LOS PAGOS O REVISAR LA EXISTENTE
                    'NMONTO' => $data[$i][6],
                    'CNUMING' => $numdocumento,
                    'CCONCEP' => $data[$i][7],
                    'KP' => $data[$i][2],
                    'INTERES' => $data[$i][3],
                    'MORA' => $data[$i][4],
                    'AHOPRG' => 0,
                    'OTR' => $data[$i][5],
                    'CCODINS' => "1",
                    'CCODOFI' => "1",
                    'CCODUSU' => $idusuario,
                    'CTIPPAG' => "P",
                    'CMONEDA' => "Q",
                    'CBANCO' => $cuentaid,
                    'FormPago' => $formapago,
                    'CCODBANCO' => $bancoid,
                    'DFECBANCO' => ($formapago === '2') ? $fechabanco : "0000-00-00",
                    'boletabanco' => $boletabanco,
                    'CESTADO' => "1",
                    'DFECMOD' => $hoy2,
                    'CTERMID' => "0",
                    'MANCOMUNAD' => "0"
                );

                $id_credkar = $database->insert('CREDKAR', $datos);
                // $numpartida = getnumcompdo($idusuario, $database);
                $numpartida = Beneq::getNumcom($database, $idusuario,$idagencia,$fechapago);
                $datos = array(
                    'numcom' => $numpartida,
                    'id_ctb_tipopoliza' => $id_ctb_tipopoliza,
                    'id_tb_moneda' => 1,
                    'numdoc' => $numdocumento,
                    'glosa' => $data[$i][7],
                    'fecdoc' => ($formapago === '2') ? $fechabanco : $fechapago,
                    'feccnt' => $fechapago,
                    'cod_aux' => $data[$i][0],
                    'id_tb_usu' => $idusuario,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0
                );
                $id_ctb_diario = $database->insert('ctb_diario', $datos);
                /**
                 * REVISION DE CONFIGURACION PARA EL DESGLOSE DEL IVA
                 */

                $montoIntGravamen = $data[$i][3];
                $montoMoraGravamen = $data[$i][4];

                $ivaTotal = 0;
                if ($desglose_iva) {
                    $montoIntGravamen = round(($data[$i][3] / 1.12), 2);
                    $montoMoraGravamen = round(($data[$i][4] / 1.12), 2);
                    $ivaTotal = (($data[$i][3] - $montoIntGravamen) + ($data[$i][4] - $montoMoraGravamen));
                }
                //MOVIMIENTOS LADO DEL DEBE (MONTO TOTAL=> CAJA Ó CUENTA BANCOS)
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $id_fondo,
                    'id_ctb_nomenclatura' => $id_nomenclatura_caja,
                    'debe' => $data[$i][6],
                    'haber' => 0
                );
                $database->insert('ctb_mov', $datos);
                //MOVIMIENTOS LADO DEL HABER (DETALLES => CAPITAL)
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $id_fondo,
                    'id_ctb_nomenclatura' => $id_nomenclatura_capital,
                    'debe' => 0,
                    'haber' => $data[$i][2]
                );
                $database->insert('ctb_mov', $datos);
                //MOVIMIENTOS LADO DEL HABER (DETALLES => INTERES)
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $id_fondo,
                    'id_ctb_nomenclatura' => $id_nomenclatura_interes,
                    'debe' => 0,
                    'haber' => $montoIntGravamen
                );
                $database->insert('ctb_mov', $datos);
                //MOVIMIENTOS LADO DEL HABER (DETALLES => MORA)
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $id_fondo,
                    'id_ctb_nomenclatura' => $id_nomenclatura_mora,
                    'debe' => 0,
                    'haber' => $montoMoraGravamen
                );
                $database->insert('ctb_mov', $datos);

                if ($ivaTotal > 0) {
                    //MOVIMIENTOS LADO DEL HABER (IVA POR PAGAR)
                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => $id_fondo,
                        'id_ctb_nomenclatura' => $idNomenclaturaIvaXPagar,
                        'debe' => 0,
                        'haber' => $ivaTotal
                    );
                    $database->insert('ctb_mov', $ctb_mov);
                }

                /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ 
                +++++++++++++++++ INSERCION DE LOS GASTOS SI SE INGRESARON ++++++++++++++++++
                +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++  */
                $detalleotros = $data[$i][8];
                foreach ($detalleotros as $rowval) {
                    //[monto, idgasto, idcontable,modulo,codaho]
                    $monf = $rowval[0];
                    $idgasto = $rowval[1];
                    $modulo = $rowval[3];
                    if ($monf > 0) {
                        if ($idgasto == 0) {
                            //INSERCION DE OTROS, EN LA CONTA, NO ES UN GASTO ESPECIFICO
                            $datos = array(
                                'id_ctb_diario' => $id_ctb_diario,
                                'id_fuente_fondo' => $id_fondo,
                                'id_ctb_nomenclatura' => $id_nomenclatura_otros,
                                'debe' => 0,
                                'haber' => $monf
                            );
                            $database->insert('ctb_mov', $datos);
                        } else {
                            //INSERCION DE GASTOS ESPECIFICOS
                            $datos = array(
                                'id_ctb_diario' => $id_ctb_diario,
                                'id_fuente_fondo' => $id_fondo,
                                'id_ctb_nomenclatura' => $rowval[2],
                                'debe' => 0,
                                'haber' => $monf
                            );
                            $database->insert('ctb_mov', $datos);

                            //INSERCION DE GASTOS EN CREDKARDETALLE
                            $datos = array(
                                'id_credkar' => $id_credkar,
                                'id_concepto' => $idgasto,
                                'monto' => $monf
                            );
                            $database->insert('credkar_detalle', $datos);

                            //SI ES UN AHORRO VINCULADO
                            if ($modulo == '1') {
                                $datos = array(
                                    "ccodaho" => $rowval[4],
                                    "dfecope" => $fechapago,
                                    "ctipope" => "D",
                                    "cnumdoc" => $numdocumento,
                                    "ctipdoc" => "V",
                                    "crazon" => "DEPOSITO VINCULADO",
                                    "nlibreta" => $nlibreta,
                                    "nrochq" => '0',
                                    "tipchq" => "0",
                                    "dfeccomp" => "0000-00-00",
                                    "monto" => $monf,
                                    "lineaprint" => "N",
                                    "numlinea" => 1,
                                    "correlativo" => 1,
                                    "dfecmod" => $hoy2,
                                    "codusu" => $idusuario,
                                    "cestado" => 1,
                                    "auxi" => $data[$i][0],
                                    "created_at" => $hoy2,
                                    "created_by" => $idusuario,
                                );
                                $database->insert('ahommov', $datos);

                                // ORDENAMIENTO DE TRANSACCIONES
                                $database->executeQuery('CALL ahom_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
                                $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$rowval[4]]);
                            }

                            //SI EXISTE UNA APORTACION VINCULADA
                            if ($modulo == '2') {
                                $datos = array(
                                    "ccodaport" => $rowval[4],
                                    "dfecope" => $fechapago,
                                    "ctipope" => "D",
                                    "cnumdoc" => $numdocumento,
                                    "ctipdoc" => "V",
                                    "crazon" => "DEPOSITO VINCULADO",
                                    "nlibreta" => $nlibreta,
                                    "nrochq" => '0',
                                    "tipchq" => "0",
                                    "dfeccomp" => "0000-00-00",
                                    "monto" => $monf,
                                    "lineaprint" => "N",
                                    "numlinea" => 1,
                                    "correlativo" => 1,
                                    "dfecmod" => $hoy2,
                                    "codusu" => $idusuario,
                                    "cestado" => 1,
                                    "auxi" => $data[$i][0],
                                    "created_at" => $hoy2,
                                    "created_by" => $idusuario,
                                );
                                $database->insert('aprmov', $datos);

                                // ORDENAMIENTO DE TRANSACCIONES
                                $database->executeQuery('CALL apr_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
                                $database->executeQuery('CALL apr_ordena_Transacciones(?);', [$rowval[4]]);
                            }
                        }
                    }
                }

                //ACTUALIZACION DEL PLAN DE PAGO
                $database->executeQuery('CALL update_ppg_account(?);', [$data[$i][0]]);
                $database->executeQuery('SELECT calculo_mora(?);', [$data[$i][0]]);
                $i++;
            }

            $database->commit();
            // $database->rollback();
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

        echo json_encode([$mensaje, $status, $numdocumento, $archivo[3]]);
        break;
        //FIN DEL TRY
        break;
    case 'list_pagos_individuales':

        $camposRetornados = $appConfigGeneral->getCamposPagosCreditos();
        // Log::info("Campos retornados para pagos de creditos: ", $camposRetornados);

        if (empty($camposRetornados)) {
            $camposRetornados = array("ccodcta", "codcli", "dpi", "nombre", "ciclo", "diapago", "monto", "saldo");
        }

        $referenciasCampos = array(
            "ccodcta" => 'cm.CCODCTA',
            "codcli" => 'cm.CodCli',
            "dpi" => 'cl.no_identifica',
            "nombre" => 'cl.short_name',
            "ciclo" => 'cm.NCiclo',
            "monto" => 'cm.NCapDes',
            "saldo" => "GREATEST(0, cm.NCapDes - SUM(CASE WHEN ck.CTIPPAG = 'P' AND ck.CESTADO != 'X' THEN ck.KP ELSE 0 END))",
            "diapago" => 'DAY(cm.DfecPago)',
            "analista" => 'IFNULL(CONCAT(us.nombre, " ", us.apellido), "")',
            "agencia" => 'IFNULL(ag.nom_agencia, "")',
            "dfecdsbls" => 'cm.DfecDsbls',
        );

        $selectCampos = [];
        foreach ($camposRetornados as $campo) {
            if (isset($referenciasCampos[$campo])) {
                $selectCampos[] = $referenciasCampos[$campo] . " AS " . $campo;
            }
        }
        $selectString = implode(", ", $selectCampos);

        /**
         * VERIFICACION DE PERMISO PARA MOSTRAR CREDITOS
         */
        try {
            $condiPermission = "";
            $userPermissions = new PermissionManager($idusuario);

            // $userPermissions->isLevelOne(PermissionManager::VER_CREDITOS_CAJA);
            if ($userPermissions->isLevelTwo(PermissionManager::VER_CREDITOS_CAJA)) {
                // Log::info("Este wey $idusuario tiene permiso de nivel 2, puede ver todos los creditos");
            } elseif ($userPermissions->isLevelOne(PermissionManager::VER_CREDITOS_CAJA)) {
                $condiPermission = " AND ag.id_agencia = $idagencia ";
                // Log::info("Este wey $idusuario tiene permiso de nivel 1, puede ver creditos de su agencia");
            } else {
                $condiPermission = " AND cm.CodAnal = $idusuario ";
                // Log::info("Este wey $idusuario tiene permiso de nivel 0, puede ver solo sus creditos");
            }
        } catch (Exception $e) {
            // En caso de error, se puede optar por un valor por defecto o manejar el error según sea necesario
            $montoMinimoPago = 0;
        } finally {
            // $database->closeConnection();
        }

        $queryGeneral = "SELECT " . $selectString . " 
                            FROM cremcre_meta cm INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente 
                            LEFT JOIN CREDKAR ck ON ck.CCODCTA = cm.CCODCTA 
                            LEFT JOIN tb_agencia ag ON ag.cod_agenc = cm.CODAgencia
                            LEFT JOIN tb_usuario us ON us.id_usu = cm.CodAnal
                            WHERE cm.Cestado = 'F' AND cm.TipoEnti = 'INDI' $condiPermission
                            GROUP BY cm.CCODCTA";

        // Log::info("Consulta general para pagos individuales: ", [$queryGeneral]);

        $showmensaje = false;
        $array_datos = array();
        try {
            $database->openConnection();

            $datos = $database->getAllResults($queryGeneral);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("No hay datos");
            }

            $i = 0;
            foreach ($datos as $fila) {
                // Generar el array de datos dinámicamente según $camposRetornados
                $registro = [];
                $registro[] = $i + 1; // Primera columna: índice

                foreach ($camposRetornados as $campo) {
                    $registro[] = isset($fila[$campo]) ? $fila[$campo] : '';
                }

                // Botón de acción al final
                $registro[] = '<button type="button" class="btn btn-success btn-sm" data-bs-dismiss="modal" onclick="printdiv2(`#cuadro`,`' . $fila["ccodcta"] . '`)">Aceptar</button> ';

                $array_datos[] = $registro;
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
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $results = [
                "errorn" => 'Error preparando consulta2: ' . $mensaje,
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
    case 'list_pagos_juridicos':
        $consulta = mysqli_query($conexion, "SELECT 
            cm.CCODCTA AS ccodcta, 
            cm.CodCli AS codcli, 
            cl.short_name AS nombre, 
            cm.NCiclo AS ciclo, 
            cm.MonSug AS monsug, 
            DAY(cm.DfecPago) AS diapago
        FROM 
            cremcre_meta cm 
        INNER JOIN 
            tb_cliente cl ON cm.CodCli = cl.idcod_cliente 
        WHERE 
            cm.Cestado = 'J' AND 
            cm.TipoEnti = 'INDI';");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $total = 0;
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $array_datos[] = array(
                "0" => $i + 1,
                "1" => $fila["ccodcta"],
                "2" => $fila["codcli"],
                "3" => $fila["nombre"],
                "4" => $fila["ciclo"],
                "5" => $fila["diapago"],
                "6" => $fila["monsug"],
                "7" => '<button type="button" class="btn btn-success"  data-bs-dismiss="modal" onclick="printdiv2(`#cuadro`,`' . $fila["ccodcta"] . '`)">Aceptar</button> '
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($array_datos),
            "iTotalDisplayRecords" => count($array_datos),
            "aaData" => $array_datos
        );
        echo json_encode($results);
        mysqli_close($conexion);
        break;
    // -- NEGROY AGREGO, las boletas --
    case 'create_pago_individual':
        //obtiene([`nomcli`, `id_cod_cliente`, `codagencia`, `codproducto`, `codcredito`, `fechadesembolso`,
        //     `norecibo`, `fecpag`, `capital0`, `interes0`, `monmora0`, `otrospg0`, `totalgen`,
        //     `fecpagBANC`, `noboletabanco`, `concepto`
        // ], [`bancoid`, `cuentaid`, `metodoPago`], [], `create_pago_individual`, `0`, [idusuario,
        //     idagencia, nomcompleto, codcredito, numerocuota, idfondo, detalles, reestructura
        // ]);

        // obtiene([`codcredito`, `norecibo`, `fecpag`, `capital0`, `interes0`, `monmora0`, `otrospg0`,
        //             `fecpagBANC`, `noboletabanco`, `concepto`, `bancoidCheque`, `noCheque`, `fecpagCheque`
        //     ],
        //     [`bancoid`, `cuentaid`, `metodoPago`], [`tipoMontoMora`], `create_pago_individual`, `0`,
        //     [codcredito, detalles, reestructura]);

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        $cuentaSaveTable = 0;
        $bancoSaveTable = 0;
        $nroChequeSaveTable = '';
        $fechaChequeSaveTable = NULL;

        // Log::info("Iniciando proceso de pago individual", [$_POST['inputs'], $_POST['selects'], $_POST['radios'], $_POST['archivo']]);

        $showmensaje = false;
        try {

            list(
                // $nombreCliente,
                // $idCliente,
                // $codAgencia,
                // $codProducto,
                $codCredito,
                // $fechaDesembolso,
                $noRecibo,
                $fechaPago,
                $capital,
                $interes,
                $montoMora,
                $otrosPagos,
                // $totalGeneral,
                $fechaPagoBanco,
                $noBoletaBanco,
                $concepto,
                $bancoIdCheque,
                $noCheque,
                $fecpagCheque
            ) = $_POST["inputs"];
            list($bancoId, $cuentaId, $metodoPago, $ahoCuentaId, $aportCuentaId) = $_POST["selects"];
            list($tipoMontoMora) = $_POST["radios"];
            list(
                // $idUsuario,
                // $idAgencia,
                // $nombreCompleto,
                $codigoCredito,
                // $numeroCuota,
                // $idFondo,
                $detalleotros,
                $reestructura,
                $identificatorsPpg,
                $switchCambioIntereses
            ) = $_POST["archivo"];

            // Log::info("archivo", [$_POST['archivo']]);

            $tipoAutorizacion = (isset($_POST["archivo"][5])) ? $_POST["archivo"][5] : [];

            $validar = validacionescampos([
                // [$nombreCliente, "", 'Debe seleccionar un crédito a pagar', 1],
                // [$idCliente, "", 'Debe seleccionar un crédito a pagar', 1],
                // [$codAgencia, "", 'Debe seleccionar un crédito a pagar', 1],
                // [$codProducto, "", 'Debe seleccionar un crédito a pagar', 1],
                [$codigoCredito, "0", 'Debe seleccionar un crédito a pagar', 1],
                // [$fechaDesembolso, "", 'Debe seleccionar un crédito a pagar', 1],
                [$noRecibo, "", 'Debe digitar un número de recibo', 1],
                [$fechaPago, "", 'Debe digitar una fecha de pago', 1],
                [$concepto, "", 'Debe digitar un concepto', 1],
                [$fechaPago, $hoy, 'La fecha de pago no puede ser mayor a la fecha de hoy', 3],
                [$capital, "", 'Debe digitar un monto de capital', 1],
                [$interes, "", 'Debe digitar un monto de interés', 1],
                [$montoMora, "", 'Debe digitar un monto de mora', 1],
                [$otrosPagos, "", 'Debe digitar un monto de otros pagos', 1],
                // [$totalGeneral, "", 'Debe exisitir un monto de total general', 1],
                [$capital, 0, "No puede digitar un capital menor a 0", 2],
                [$interes, 0, "No puede digitar un interes menor a 0", 2],
                [$montoMora, 0, "No puede digitar una mora menor a 0", 2],
                [$otrosPagos, 0, "No puede digitar en otros pagos un monto menor a 0", 2],
                // [$totalGeneral, 0, "No puede tener un total general menor a 0", 2]
            ]);

            if ($validar[2]) {
                $showmensaje = true;
                throw new Exception($validar[0]);
            }

            $fechaBancoSave = $fechaPago;
            $database->openConnection();
            //COMPROBAR CIERRE DE CAJA

            // $configuracion = new Configuracion($database);

            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                $showmensaje = true;
                throw new Exception($cierre_caja[1]);
            }
            // Log::info("Validaciones de cierre de caja correctas", [$_SESSION['id']]);

            //COMPROBAR CIERRE DE MES CONTABLE
            $cierre_mes = comprobar_cierrePDO($_SESSION['id'], $fechaPago, $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }

            // Log::info("Validaciones de cierre de caja y mes contable correctas",[ $_SESSION['id']]);

            if ($detalleotros != null) {
                foreach ($detalleotros as $rowval) {
                    //[monto, idgasto, idcontable,modulo,codaho]
                    $monf = $rowval[0];
                    if (is_numeric($monf) && $monf < 0) {
                        $showmensaje = true;
                        throw new Exception("No puede ingresar valores negativos");
                    }

                    if ($rowval[3] > 0 && $rowval[3] < 3) {
                        $table = ($rowval[3] == 1) ? "ahomcta" : "aprcta";
                        $column = ($rowval[3] == 1) ? "ccodaho" : "ccodaport";
                        $texttitle = ($rowval[3] == 1) ? " ahorros" : "aportaciones";

                        $dataCuentaAhorro = $database->selectColumns($table, ['nlibreta'], $column . '=?', [$rowval[4]]);
                        if (empty($dataCuentaAhorro)) {
                            $showmensaje = true;
                            throw new Exception("La cuenta de $texttitle: $rowval[4] no existe, por lo tanto no se puede completar la operacion por el monto especificado: $monf, se recomienda configurar el vinculo con una cuenta existente para poder ingresar montos");
                        }
                        $nlibreta = $dataCuentaAhorro[0]['nlibreta'];
                    }
                }
            }
            // Log::info("Validaciones de campos y cuentas de ahorro vinculadas correctas",[ $_SESSION['id']]);

            $querysaldos = "SELECT IFNULL((ROUND((IFNULL(cm.NCapDes,0)),2)-(SELECT ROUND(IFNULL(SUM(c.KP),0),2) FROM CREDKAR c WHERE c.CTIPPAG = 'P' AND  c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X')),0)  AS saldopendiente,
                        IFNULL(ROUND((SELECT ROUND(IFNULL(SUM(nintere),0),2) FROM Cre_ppg WHERE ccodcta = cm.CCODCTA)-
                        (SELECT ROUND(IFNULL(SUM(c.INTERES),0),2) FROM CREDKAR c WHERE c.CTIPPAG = 'P' AND  c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X'),2),0)  AS intpendiente 
                        FROM cremcre_meta cm WHERE cm.CCODCTA = ?";

            $saldosCredito = $database->getAllResults($querysaldos, [$codigoCredito]);
            if (empty($saldosCredito)) {
                $showmensaje = true;
                throw new Exception("No se encontraron los saldos del crédito");
            }
            $capital_pendiente = ($saldosCredito[0]['saldopendiente'] > 0) ? round($saldosCredito[0]['saldopendiente'], 2) : 0;
            $interes_pendiente = ($saldosCredito[0]['intpendiente'] > 0) ? round($saldosCredito[0]['intpendiente'], 2) : 0;

            if (!$appConfigGeneral->validarSaldoKpXPagosKp()) {
                if ($capital > $capital_pendiente) {
                    Log::debug("el saldo de capital es menor al que se quiere pagar");
                    $showmensaje = true;
                    throw new Exception("No puede completar el pago, porque el saldo capital por pagar es de " . $capital_pendiente . " y usted quiere hacer un pago de " . $capital . " lo cual supera lo que resta por pagar, todo
                    extra que se quiera pagar agreguelo en otros.");
                }
            }

            if (!$appConfigGeneral->validarSaldoIntXPagosInt()) {
                if ($interes > $interes_pendiente) {
                    Log::debug("el saldo de interés es menor al que se quiere pagar");
                    $showmensaje = true;
                    throw new Exception("No puede completar el pago, porque el saldo interés por pagar es de " . $interes_pendiente . " y usted quiere hacer un pago de " . $interes . " lo cual supera lo que resta por pagar, todo
                                        extra que se quiera pagar agreguelo en otros.");
                }
            }

            //CONSULTAR LA NOMENCLATURA PARA EL PAGO TOTAL
            $agenciaData = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
            if (empty($agenciaData)) {
                $showmensaje = true;
                throw new Exception("No se encontro la cuenta contable para el desembolso real");
            }
            $id_nomenclatura_caja = $agenciaData[0]['id_nomenclatura_caja'];

            $cuentasContables = $database->getAllResults("SELECT id_cuenta_capital, id_cuenta_interes, id_cuenta_mora, id_cuenta_otros,id_fondo, cm.Cestado,cp.id idProducto FROM cre_productos cp INNER JOIN cremcre_meta cm ON cp.id=cm.CCODPRD WHERE cm.CCODCTA=?", [$codigoCredito]);
            if (empty($cuentasContables)) {
                $showmensaje = true;
                throw new Exception("No se encontraron las cuentas contables de capital, interes, mora y otros");
            }
            if ($cuentasContables[0]['Cestado'] != 'F') {
                $showmensaje = true;
                throw new Exception("El crédito seleccionado no esta vigente, no puede realizar la trasacción.");
            }
            $id_nomenclatura_capital = $cuentasContables[0]['id_cuenta_capital'];
            $id_nomenclatura_interes = $cuentasContables[0]['id_cuenta_interes'];
            $id_nomenclatura_mora = $cuentasContables[0]['id_cuenta_mora'];
            $id_nomenclatura_otros = $cuentasContables[0]['id_cuenta_otros'];
            $id_fondo = $cuentasContables[0]['id_fondo'];
            $id_producto = $cuentasContables[0]['idProducto'];

            // Log::info("Validaciones de cuentas contables correctas", [$_SESSION['id']]);
            // EFECTIVO
            $id_ctb_tipopoliza = 1;
            if ($metodoPago === '2') {
                if ($cuentaId == "F000") {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de banco");
                }
                if ($noBoletaBanco == "") {
                    $showmensaje = true;
                    throw new Exception("Ingrese un numero de boleta de banco");
                }

                if (!$appConfigGeneral->permitirRepetirBoletasPorBancos()) {
                    // Log::info("Validando boleta de banco repetida", [$noBoletaBanco]);
                    //VALIDA BOLETA DE BANCO
                    $validaboleta = $database->getAllResults("SELECT EXISTS (SELECT boletabanco FROM CREDKAR WHERE CTIPPAG='P' AND boletabanco=? AND CBANCO=?) AS result;", [$noBoletaBanco, $bancoId]);
                    if ($validaboleta[0]['result']) {
                        $showmensaje = true;
                        throw new Exception("El Numero de boleta " . $noBoletaBanco . " ya se ingresó en el sistema, con el banco seleccionado");
                    }
                }
                // Log::info("Validaciones de boleta de banco correctas", [$_SESSION['id']]);

                $id_ctb_tipopoliza = 11; // BOLETA PAGO BANCO

                $dataBanco = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$cuentaId]);
                if (empty($dataBanco)) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el banco");
                }
                $id_nomenclatura_caja = $dataBanco[0]['id_nomenclatura'];
                $cuentaSaveTable = $cuentaId;
                $bancoSaveTable = $bancoId;
                $nroChequeSaveTable = $noBoletaBanco;
                $fechaChequeSaveTable = $fechaPagoBanco;
                $fechaBancoSave = $fechaPagoBanco;
            }

            /**
             * AGREGADO PARA TIPOS DE DOCUMENTOS DIFERENTES CREADOS POR EL USUARIO
             */
            if (!is_numeric($metodoPago)) {
                // formato esperado d_111
                $tipo_documento = str_replace("d_", "", $metodoPago);
                if (empty($tipo_documento)) {
                    $showmensaje = true;
                    throw new Exception("Debe seleccionar un tipo de documento valido");
                }

                $dataTipoDocumento = $database->selectColumns('tb_documentos_transacciones', ['id_cuenta_contable', 'tipo_dato'], 'id=?', [$tipo_documento]);
                if (empty($dataTipoDocumento)) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el tipo de documento seleccionado");
                }
                if ($dataTipoDocumento[0]['tipo_dato'] == 2) {
                    if ($bancoIdCheque == '') {
                        $showmensaje = true;
                        throw new Exception("Seleccione un banco");
                    }
                    if ($fecpagCheque == 0) {
                        $showmensaje = true;
                        throw new Exception("Seleccione una fecha para el cheque");
                    }
                    if ($noCheque == "") {
                        $showmensaje = true;
                        throw new Exception("El numero de cheque es obligatorio");
                    }
                    // $cuentaSaveTable = $cuentaId;
                    $bancoSaveTable = $bancoIdCheque;
                    $nroChequeSaveTable = $noCheque;
                    $fechaChequeSaveTable = $fecpagCheque;
                }
                $id_nomenclatura_caja = $dataTipoDocumento[0]['id_cuenta_contable'];
            }

            if ($metodoPago === '3') {
                // VALIDACIONES PARA AHORRO
                if ($ahoCuentaId == "") {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de ahorros");
                }

                $dataAhoCuenta = $database->getAllResults(
                    "SELECT ccodaho, tip.nombre,tip.id_cuenta_contable,calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) AS saldo,
                        cta.nlibreta
                        FROM ahomcta cta
                        INNER JOIN ahomtip tip ON tip.ccodtip= SUBSTR(cta.ccodaho,7,2)
                        WHERE cta.ccodaho=? AND cta.estado='A'",
                    [$ahoCuentaId]
                );
                if (empty($dataAhoCuenta)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta de ahorro seleccionada no existe o no esta activa");
                }

                if ($dataAhoCuenta[0]['saldo'] < ($capital + $interes + $montoMora + $otrosPagos)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta de ahorro seleccionada no tiene saldo suficiente para completar el pago");
                }

                $id_nomenclatura_caja = $dataAhoCuenta[0]["id_cuenta_contable"];

                // $bancoSaveTable = 0;
                // $nroChequeSaveTable = '';
                // $fechaChequeSaveTable = NULL;
            }

            if ($metodoPago === '5') {
                // VALIDACIONES PARA APORTACIONES
                if ($aportCuentaId == "") {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de aportaciones");
                }

                $dataAportCuenta = $database->getAllResults(
                    "SELECT ccodaport, tip.nombre,tip.id_cuenta_contable,calcular_saldo_apr_tipcuenta(cta.ccodaport, CURDATE()) AS saldo,
                        cta.nlibreta
                        FROM aprcta cta
                        INNER JOIN aprtip tip ON tip.ccodtip= SUBSTR(cta.ccodaport,7,2)
                        WHERE cta.ccodaport=? AND cta.estado='A'",
                    [$aportCuentaId]
                );
                if (empty($dataAportCuenta)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta de aportacion seleccionada no existe o no esta activa");
                }

                if ($dataAportCuenta[0]['saldo'] < ($capital + $interes + $montoMora + $otrosPagos)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta de aportacion seleccionada no tiene saldo suficiente para completar el pago");
                }

                $id_nomenclatura_caja = $dataAportCuenta[0]["id_cuenta_contable"];
            }

            /**
             * FIN AGREGADO PARA TIPOS DE DOCUMENTOS DIFERENTES CREADOS POR EL USUARIO
             */

            $database->beginTransaction();

            $result = $database->getAllResults("SELECT IFNULL(MAX(ck.CNROCUO),0)+1 AS correlrocuo FROM CREDKAR ck WHERE ck.CCODCTA=? and CTIPPAG = 'P' and CESTADO = '1'", [$codigoCredito]);
            $cnrocuo = (empty($result)) ? 1 : $result[0]['correlrocuo'];

            $desboleta = ($metodoPago === '2') ? (" - BOLETA DE BANCO NO. " . $noBoletaBanco) : "";
            $numdocdiario = ($metodoPago === '2') ? $noBoletaBanco : strtoupper($noRecibo);

            $totalGeneral = $capital + $interes + $montoMora + $otrosPagos;

            $credkar = array(
                'CCODCTA' => $codigoCredito,
                'DFECPRO' => $fechaPago,
                'DFECSIS' => $hoy2,
                'CNROCUO' => $cnrocuo,
                'NMONTO' => $totalGeneral,
                'CNUMING' => $noRecibo,
                'CCONCEP' => $concepto,
                'KP' => $capital,
                'INTERES' => $interes,
                'MORA' => $montoMora,
                'AHOPRG' => 0,
                'OTR' => $otrosPagos,
                'CCODINS' => "1",
                'CCODOFI' => $idagencia,
                'CCODUSU' => $idusuario,
                'CTIPPAG' => "P",
                'CMONEDA' => "Q",
                'CBANCO' => $bancoSaveTable,
                'FormPago' => $metodoPago,
                'CCODBANCO' => $cuentaSaveTable,
                'DFECBANCO' => $fechaChequeSaveTable,
                'boletabanco' => $nroChequeSaveTable,
                'CESTADO' => "1",
                'DFECMOD' => $hoy2,
            );

            $id_credkar = $database->insert('CREDKAR', $credkar);

            if ($switchCambioIntereses == 1) {
                foreach ($identificatorsPpg as $id_ppg) {
                    $datosAnteriores = $database->selectColumns('Cre_ppg', ['*'], 'id_ppg=?', [$id_ppg]);
                    if (!empty($datosAnteriores)) {
                        if ($datosAnteriores[0]['nintpag'] > $interes) {

                            $bitacora = array(
                                'ccodcta' => $datosAnteriores[0]['ccodcta'],
                                'dfecven' => $datosAnteriores[0]['dfecven'],
                                'dfecpag' => $datosAnteriores[0]['dfecpag'],
                                'cestado' => $datosAnteriores[0]['cestado'],
                                'ctipope' => $datosAnteriores[0]['ctipope'],
                                'cnrocuo' => $datosAnteriores[0]['cnrocuo'],
                                'SaldoCapital' => $datosAnteriores[0]['SaldoCapital'],
                                'nmorpag' => $datosAnteriores[0]['nmorpag'],
                                'ncappag' => $datosAnteriores[0]['ncappag'],
                                'nintpag' => $datosAnteriores[0]['nintpag'],
                                'AhoPrgPag' => 0,
                                'OtrosPagosPag' => $datosAnteriores[0]['OtrosPagosPag'],
                                'ccodusu' => $datosAnteriores[0]['ccodusu'],
                                'dfecmod' => $datosAnteriores[0]['dfecmod'],
                                'cflag' => $datosAnteriores[0]['cflag'],
                                'codigo' => $datosAnteriores[0]['codigo'],
                                'creditosaf' => $datosAnteriores[0]['creditosaf'],
                                'saldo' => $datosAnteriores[0]['saldo'],
                                'nintmor' => $datosAnteriores[0]['nintmor'],
                                'ncapita' => $datosAnteriores[0]['ncapita'],
                                'nintere' => $datosAnteriores[0]['nintere'],
                                'NAhoProgra' => 0,
                                'OtrosPagos' => $datosAnteriores[0]['OtrosPagos'],
                                'delete_by' => $idusuario,
                                'delete_at' => $hoy2
                            );

                            $database->insert('bitacora_Cre_ppg', $bitacora);

                            $nuevoInteresPagado = $datosAnteriores[0]['nintere'] - ($datosAnteriores[0]['nintpag'] - $interes);

                            $database->update('Cre_ppg', ['nintere' => $nuevoInteresPagado], 'id_ppg=?', [$id_ppg]);
                        }
                    }
                }
            }

            $database->executeQuery('CALL update_ppg_account(?);', [$codigoCredito]);
            $database->executeQuery('SELECT calculo_mora(?);', [$codigoCredito]);


            /**
             * CONTROL DE LA MORA, SI SE PERDONO 
             */
            // Log::info('tipo auth', [$tipoAutorizacion]);
            if ($tipoMontoMora === 'perdon' && !empty($tipoAutorizacion)) {
                // Consultar los valores de nmorpag de los Id_ppg seleccionados
                $placeholders = implode(',', array_fill(0, count($identificatorsPpg), '?'));
                $query = "SELECT cnrocuo,nmorpag FROM Cre_ppg WHERE Id_ppg IN ($placeholders)";
                $ppgMoraAnt = $database->getAllResults($query, $identificatorsPpg);

                // Log::info("Valores de mora a perdonar", [$ppgMoraAnt]);

                $tipoAuth = $tipoAutorizacion['tipoAuth'];

                if (!empty($ppgMoraAnt)) {
                    if (array_sum(array_column($ppgMoraAnt, 'nmorpag')) != $montoMora) {
                        if ($tipoAuth == 1) {
                            $userAuth = $tipoAutorizacion['user'];
                            $idUserAuth = $database->selectColumns('tb_usuario', ['id_usu id'], 'usu=?', [$userAuth]);
                        }
                        if ($tipoAuth == 2) {
                            $idUserAuth = $database->selectColumns('tb_alerta', ['updated_by id'], 'cod_aux=? AND codDoc=? AND estado=0', [$codigoCredito, $noRecibo], 'id DESC');
                        }
                        foreach ($ppgMoraAnt as $ppg) {
                            $cre_ppg_log = array(
                                "no_cuota" => $ppg['cnrocuo'],
                                "ccodcta" => $codigoCredito,
                                "credkar_id" => $id_credkar,
                                "morapag" => $ppg['nmorpag'],
                                "tipo_autorizacion" => $tipoAuth,
                                "autorizado_por" => (!empty($idUserAuth)) ? $idUserAuth[0]['id'] : NULL
                            );
                            $database->insert('cre_ppg_log', $cre_ppg_log);
                        }
                    }
                }
            }

            /**
             * MOVIMIENTOS CONTABLES
             */
            // $numpartida = getnumcompdo($idusuario, $database);
            $numpartida = Beneq::getNumcom($database, $idusuario,$idagencia,$fechaPago);

            $ctb_diario = array(
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => $id_ctb_tipopoliza,
                'id_tb_moneda' => 1,
                'numdoc' => $numdocdiario,
                'glosa' => $concepto,
                'fecdoc' => $fechaBancoSave,
                'feccnt' => $fechaPago,
                'cod_aux' => $codigoCredito,
                'id_tb_usu' => $idusuario,
                'karely' => "CRE_" . $id_credkar,
                'id_agencia' => $idagencia,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 0
            );
            $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);


            //MOVIMIENTOS LADO DEL DEBE (MONTO TOTAL=> CAJA Ó CUENTA BANCOS)
            $ctb_mov = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fondo,
                'id_ctb_nomenclatura' => $id_nomenclatura_caja,
                'debe' => $totalGeneral,
                'haber' => 0
            );
            $database->insert('ctb_mov', $ctb_mov);
            //MOVIMIENTOS LADO DEL HABER (DETALLES => CAPITAL)
            $ctb_mov = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fondo,
                'id_ctb_nomenclatura' => $id_nomenclatura_capital,
                'debe' => 0,
                'haber' => $capital
            );
            if ($capital > 0) {
                $database->insert('ctb_mov', $ctb_mov);
            }

            /**
             * REVISION DE CONFIGURACION PARA EL DESGLOSE DEL IVA
             */
            // $configuracion = new Configuracion($database);
            // $valor = $configuracion->getValById(2);
            // $desglose_iva = ($valor == 1) ? true : false;
            $desglose_iva = $appConfigGeneral->desglosarIva();
            $montoMoraGravamen = $montoMora;
            $montoInteresReal = $interes;

            if ($montoInteresReal > 0) {
                /**
                 * DESGLOSE DE INTERESES, REVISAR SI HAY QUE SEPARAR LOS INTERESES
                 */
                $desgloseInteres = $database->getAllResults("SELECT intp.id,tipi.id_nomenclatura,intp.cant FROM cre_intereses_producto intp 
                                INNER JOIN cre_intereses_tipo tipi ON tipi.id=intp.id_tipo
                                WHERE tipi.estado=1 AND intp.id_producto=?", [$id_producto]);
                if (!empty($desgloseInteres)) {
                    $totalPorcentajes = array_sum(array_column($desgloseInteres, 'cant'));
                    if ($totalPorcentajes > 100) {
                        $showmensaje = true;
                        throw new Exception("La suma de los porcentajes de desglose de intereses supera el 100%, por favor verifique la configuración");
                    }

                    // $montoIntGravamen = 0;
                    foreach ($desgloseInteres as $tipoInteres) {
                        $porcentaje = $tipoInteres['cant'];
                        $montoTipoInteres = round((($montoInteresReal * $porcentaje) / 100), 2);
                        if ($tipoInteres['id_nomenclatura'] == null || $tipoInteres['id_nomenclatura'] == 0) {
                            $showmensaje = true;
                            throw new Exception("No se encontro la cuenta contable para el tipo de interes: " . $tipoInteres['id'] . ", por favor verifique la configuración");
                        }
                        //MOVIMIENTOS LADO DEL HABER (DETALLES => INTERES)
                        $ctb_mov = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_fuente_fondo' => $id_fondo,
                            'id_ctb_nomenclatura' => $tipoInteres['id_nomenclatura'],
                            'debe' => 0,
                            'haber' => $montoTipoInteres
                        );
                        $database->insert('ctb_mov', $ctb_mov);

                        /**
                         * CREDKAR DETALLE
                         */
                        $credkar_detalle = array(
                            'id_credkar' => $id_credkar,
                            'id_concepto' => $tipoInteres['id'],
                            'monto' => $montoTipoInteres,
                            'tipo' => 'interes'
                        );
                        $database->insert('credkar_detalle', $credkar_detalle);
                        $montoInteresReal -= $montoTipoInteres;
                    }
                }
            }

            $montoIntGravamen = $montoInteresReal;
            $ivaTotal = 0;
            if ($desglose_iva) {
                $montoIntGravamen = round(($montoInteresReal / 1.12), 2);
                $montoMoraGravamen = round(($montoMora / 1.12), 2);
                $ivaTotal = (($montoInteresReal - $montoIntGravamen) + ($montoMora - $montoMoraGravamen));

                $cuentaIva = $database->selectColumns('ctb_parametros_general', ['id_ctb_nomenclatura'], 'id_tipo=10');
                if (empty($cuentaIva)) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el IVA Por pagar");
                }
                $idNomenclaturaIvaXPagar = $cuentaIva[0]['id_ctb_nomenclatura'];

                $verificacion = $database->selectColumns('ctb_nomenclatura', ['id'], 'id=? AND estado=1', [$idNomenclaturaIvaXPagar]);
                if (empty($verificacion)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta contable configurada para el IVA por pagar no existe o no esta activa, por favor verifique la configuracion contable");
                }
            }

            //MOVIMIENTOS LADO DEL HABER (DETALLES => INTERES)
            $ctb_mov = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fondo,
                'id_ctb_nomenclatura' => $id_nomenclatura_interes,
                'debe' => 0,
                'haber' => $montoIntGravamen
            );
            if ($montoIntGravamen > 0) {
                $database->insert('ctb_mov', $ctb_mov);
            }
            //MOVIMIENTOS LADO DEL HABER (DETALLES => MORA)
            $ctb_mov = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fondo,
                'id_ctb_nomenclatura' => $id_nomenclatura_mora,
                'debe' => 0,
                'haber' => $montoMoraGravamen
            );
            if ($montoMoraGravamen > 0) {
                $database->insert('ctb_mov', $ctb_mov);
            }

            if ($ivaTotal > 0) {
                //MOVIMIENTOS LADO DEL HABER (IVA POR PAGAR)
                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $id_fondo,
                    'id_ctb_nomenclatura' => $idNomenclaturaIvaXPagar,
                    'debe' => 0,
                    'haber' => $ivaTotal
                );
                $database->insert('ctb_mov', $ctb_mov);
            }

            /**
             * GASTOS
             */
            /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ 
            +++++++++++++++++ INSERCION DE LOS GASTOS SI SE INGRESARON ++++++++++++++++++
            +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++  */
            if ($detalleotros != null) {
                foreach ($detalleotros as $rowval) {
                    //[monto, idgasto, idcontable,modulo,codaho]
                    $monf = $rowval[0];
                    $idgasto = $rowval[1];
                    $modulo = $rowval[3];

                    if ($idgasto == 0) {
                        $ctb_mov = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_fuente_fondo' => $id_fondo,
                            'id_ctb_nomenclatura' => $id_nomenclatura_otros,
                            'debe' => 0,
                            'haber' => $monf
                        );
                        $database->insert('ctb_mov', $ctb_mov);
                    } else {
                        $credkar_detalle = array(
                            'id_credkar' => $id_credkar,
                            'id_concepto' => $idgasto,
                            'monto' => $monf
                        );
                        $database->insert('credkar_detalle', $credkar_detalle);

                        $ctb_mov = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_fuente_fondo' => $id_fondo,
                            'id_ctb_nomenclatura' => $rowval[2],
                            'debe' => 0,
                            'haber' => $monf
                        );
                        $database->insert('ctb_mov', $ctb_mov);

                        if ($modulo == '1') {
                            $ahommov = array(
                                'ccodaho' => $rowval[4],
                                'dfecope' => $fechaPago,
                                'ctipope' => "D",
                                'cnumdoc' => $noRecibo,
                                'ctipdoc' => "V",
                                'crazon' => "DEPOSITO VINCULADO",
                                'nlibreta' => $nlibreta,
                                'nrochq' => '0',
                                'tipchq' => "0",
                                'dfeccomp' => "0000-00-00",
                                'monto' => $monf,
                                'lineaprint' => "N",
                                'numlinea' => 1,
                                'correlativo' => 1,
                                'dfecmod' => $hoy2,
                                'codusu' => $idusuario,
                                'cestado' => 1,
                                'auxi' => $codigoCredito,
                                'created_at' => $hoy2,
                                'created_by' => $idusuario
                            );
                            $database->insert('ahommov', $ahommov);

                            // ORDENAMIENTO DE TRANSACCIONES
                            $database->executeQuery('CALL ahom_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
                            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$rowval[4]]);
                        }
                        if ($modulo == '2') {
                            $aprmov = array(
                                'ccodaport' => $rowval[4],
                                'dfecope' => $fechaPago,
                                'ctipope' => "D",
                                'cnumdoc' => $noRecibo,
                                'ctipdoc' => "V",
                                'crazon' => "DEPOSITO VINCULADO",
                                'nlibreta' => $nlibreta,
                                'nrochq' => '0',
                                'tipchq' => "0",
                                'monto' => $monf,
                                'lineaprint' => "N",
                                'numlinea' => 1,
                                'correlativo' => 1,
                                'dfecmod' => $hoy2,
                                'codusu' => $idusuario,
                                'cestado' => 1,
                                'auxi' => $codigoCredito,
                                'created_at' => $hoy2,
                                'created_by' => $idusuario
                            );
                            $database->insert('aprmov', $aprmov);

                            // ORDENAMIENTO DE TRANSACCIONES
                            $database->executeQuery('CALL apr_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
                            $database->executeQuery('CALL apr_ordena_Transacciones(?);', [$rowval[4]]);
                        }
                    }
                }
            }

            if (!is_numeric($metodoPago) && $dataTipoDocumento[0]['tipo_dato'] == 2) {
                $ctb_ban_mov = [
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_cuenta_banco' => $bancoSaveTable,
                    'destino' => '-',
                    'numero' => $nroChequeSaveTable,
                    'fecha' => $fechaChequeSaveTable,
                    'estado' => 1, //1 entra en compensacion, 2 cuando ya esta liberado y cobrado, 0 rechazado
                ];

                $database->insert('ctb_ban_mov', $ctb_ban_mov);
            }

            /**
             * registrar retiro de la cuenta de ahorros si el metodo de pago es ahorros
             */
            if ($metodoPago === '3') {
                $ahommov = array(
                    'ccodaho' => $ahoCuentaId,
                    'dfecope' => $fechaPago,
                    'ctipope' => "R",
                    'cnumdoc' => $noRecibo,
                    'ctipdoc' => "V",
                    'crazon' => "RETIRO",
                    'concepto' => "RETIRO POR PAGO DE CREDITO",
                    'nlibreta' => $dataAhoCuenta[0]['nlibreta'],
                    'nrochq' => '0',
                    'tipchq' => "0",
                    // 'dfeccomp' => "0000-00-00",
                    'monto' => $totalGeneral,
                    'lineaprint' => "N",
                    'numlinea' => 1,
                    'correlativo' => 1,
                    'dfecmod' => $hoy2,
                    'codusu' => $idusuario,
                    'cestado' => 1,
                    'auxi' => $codigoCredito,
                    'created_at' => $hoy2,
                    'created_by' => $idusuario
                );
                $database->insert('ahommov', $ahommov);

                // ORDENAMIENTO DE TRANSACCIONES
                $database->executeQuery('CALL ahom_ordena_noLibreta(?,?);', [$dataAhoCuenta[0]['nlibreta'], $ahoCuentaId]);
                $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$ahoCuentaId]);
            }

            /**
             * registrar retiro de la cuenta de aportaciones si el metodo de pago es aportaciones
             */
            if ($metodoPago === '5') {
                $aprmov = array(
                    'ccodaport' => $aportCuentaId,
                    'dfecope' => $fechaPago,
                    'ctipope' => "R",
                    'cnumdoc' => $noRecibo,
                    'ctipdoc' => "V",
                    'crazon' => "RETIRO",
                    'concepto' => "RETIRO POR PAGO DE CREDITO",
                    'nlibreta' => $dataAportCuenta[0]['nlibreta'],
                    'nrochq' => '0',
                    'tipchq' => "0",
                    'monto' => $totalGeneral,
                    'lineaprint' => "N",
                    'numlinea' => 1,
                    'correlativo' => 1,
                    'dfecmod' => $hoy2,
                    'codusu' => $idusuario,
                    'cestado' => 1,
                    'auxi' => $codigoCredito,
                    'created_at' => $hoy2,
                    'created_by' => $idusuario
                );
                $database->insert('aprmov', $aprmov);

                // ORDENAMIENTO DE TRANSACCIONES
                $database->executeQuery('CALL apr_ordena_noLibreta(?,?);', [$dataAportCuenta[0]['nlibreta'], $aportCuentaId]);
                $database->executeQuery('CALL apr_ordena_Transacciones(?);', [$aportCuentaId]);
            }

            Log::info("reestructura", [$reestructura]);
            if ($reestructura == '1') {
                Log::info("Reestructurando credito", [$codigoCredito, $fechaPago]);
                $credito = new CreditoAmortizationSystem($codigoCredito, $database);

                // Simula una reestructuración
                $credito->procesaReestructura();
            }
            //FIN DE TRANSACCIONES
            $database->commit();
            $status = 1;
            $mensaje = "Pago registrado correctamente con recibo No. " . $noRecibo;
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status, ($noRecibo ?? 0), ($cnrocuo ?? 0)]);
        break;

    case 'create_pago_juridico':
        //obtiene([`norecibo`, `fecpag`, `capital0`, `interes0`, `monmora0`, `otrospg0`, `totalgen`, `fecpagBANC`, `noboletabanco`, `concepto`], 
        //[`bancoid`, `cuentaid`, `metodoPago`], [], `create_pago_juridico`, `0`, [codcredito, detalles, reestructura]);
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];
        $selects = $_POST["selects"];

        // Desestructuración usando list
        list($numdocument, $fecharecibo, $moncapital, $moninteres, $monmora, $monotros, $montotal, $bancofecboleta, $banconoboleta, $conceptopago) = $inputs;
        list($bancoid, $bancocuentaid, $metodopago) = $selects;
        list($ccodcta, $detallesotros, $reestructura) = $archivo;

        $nrocuo = 0;
        $showmensaje = false;
        try {
            $database->openConnection();
            //COMPROBAR CIERRE DE CAJA
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                $showmensaje = true;
                throw new Exception($cierre_caja[1]);
            }
            //COMPROBAR CIERRE DE MES CONTABLE
            $cierre_mes = comprobar_cierrePDO($_SESSION['id'], $fecharecibo, $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }
            //VALIDACIONES
            $validar = validar_campos_plus([
                [$numdocument, "", 'Ingrese un numero de documento', 1],
                [$fecharecibo, "", 'Ingrese una fecha de pago', 1],
                [$conceptopago, "", 'Debe digitar un concepto', 1],
                [$moncapital, "", 'Debe digitar un monto de capital', 1],
                [$moninteres, "", 'Debe digitar un monto de interés', 1],
                [$monmora, "", 'Debe digitar un monto de mora', 1],
                [$monotros, "", 'Debe digitar un monto de otros pagos', 1],
                [$montotal, "", 'Debe exisitir un monto de total general', 1],
                [$moncapital, 0, 'No puede digitar un capital menor a 0', 2],
                [$moninteres, 0, 'No puede digitar un interes menor a 0', 2],
                [$monmora, 0, 'No puede digitar una mora menor a 0', 2],
                [$monotros, 0, 'Monto de otros es menor a 0', 2],
                [$montotal, 0, 'No puede existir un monto total menor a 0', 2],
                [$fecharecibo, date('Y-m-d'), 'La fecha de pago no puede ser mayor que la fecha de hoy', 3],
            ]);
            if ($validar[2]) {
                $showmensaje = true;
                throw new Exception($validar[0]);
            }
            //VALIDACION DE NUMERO DE DOCUMENTO
            $result = $database->selectColumns('CREDKAR', ['CNUMING'], 'CNUMING=?', [$numdocument]);
            if (!empty($result)) {
                $showmensaje = true;
                throw new Exception("Numero de documento de pago ya existente, Favor verificar");
            }

            //VALIDAR OTROS DETALLES
            foreach ($detallesotros as $rowval) {
                //[monto, idgasto, idcontable,modulo,codaho]
                $monf = $rowval[0];
                if (is_numeric($monf) && $monf < 0) {
                    $showmensaje = true;
                    throw new Exception("No puede ingresar valores negativos");
                }

                if ($rowval[3] > 0) {
                    $table = ($rowval[3] == 1) ? "ahomcta" : "aprcta";
                    $column = ($rowval[3] == 1) ? "ccodaho" : "ccodaport";
                    $texttitle = ($rowval[3] == 1) ? " ahorros" : "aportaciones";
                    $result = $database->selectColumns($table, ['nlibreta'], $column . '=?', [$rowval[4]]);
                    if (empty($result)) {
                        $showmensaje = true;
                        throw new Exception("La cuenta de ' . $texttitle . ': ' . $rowval[4] . ' no existe, por lo tanto no se puede completar la operacion por el monto especificado: ' . $monf . ', se recomienda configurar el vinculo con una cuenta existente para poder ingresar montos");
                    }
                    $nlibreta = $result[0]['nlibreta'];
                }
            }

            //VALIDACION SALDO CAPITAL E INTERES
            $result = $database->getAllResults("SELECT  
                        IFNULL((ROUND((IFNULL(cm.NCapDes,0)),2)-(SELECT ROUND(IFNULL(SUM(c.KP),0),2) FROM CREDKAR c WHERE c.CTIPPAG = 'P' AND  c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X')),0)  AS saldopendiente,
                        IFNULL(ROUND((SELECT ROUND(IFNULL(SUM(nintere),0),2) FROM Cre_ppg WHERE ccodcta = cm.CCODCTA)-
                        (SELECT ROUND(IFNULL(SUM(c.INTERES),0),2) FROM CREDKAR c WHERE c.CTIPPAG = 'P' AND  c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X'),2),0)  AS intpendiente 
                        FROM cremcre_meta cm WHERE cm.CCODCTA = ?", [$ccodcta]);

            $capital_pendiente = (empty($result)) ? 0 : (($result[0]['saldopendiente'] > 0) ? $result[0]['saldopendiente'] : 0);
            $interes_pendiente = (empty($result)) ? 0 : (($result[0]['intpendiente'] > 0) ? $result[0]['intpendiente'] : 0);

            if ($moncapital > $capital_pendiente) {
                $showmensaje = true;
                throw new Exception("No puede completar el pago, porque el saldo capital por pagar es de $capital_pendiente y usted quiere hacer un pago de $moncapital lo cual supera lo que resta por pagar, todo extra que se quiera pagar agreguelo en otros.");
            }
            if ($moninteres > $interes_pendiente) {
                $showmensaje = true;
                throw new Exception("No puede completar el pago, porque el saldo interés por pagar es de $interes_pendiente y usted quiere hacer un pago de $moninteres lo cual supera lo que resta por pagar, todo extra que se quiera pagar agreguelo en otros.");
            }

            //CONSULTAR LA CUENTA CONTABLE DE LA CAJA AGENCIA 
            $result = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja', 'id_nomenclatura_juridico'], 'id_agencia=?', [$idagencia]);
            $id_nomenclatura_caja = $result[0]['id_nomenclatura_caja'];
            $id_nomenclatura_juridico = $result[0]['id_nomenclatura_juridico'];
            $id_ctb_tipopoliza = 1; //POR DEFECTO ES DE TIPO CREDITOS
            if ($metodopago === '2') {
                if ($bancocuentaid == "F000") {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de banco");
                }
                if ($banconoboleta == "") {
                    $showmensaje = true;
                    throw new Exception("Ingrese un numero de boleta de banco");
                }
                $result = $database->selectColumns('CREDKAR', ['boletabanco'], "CTIPPAG='P' AND boletabanco=? AND CBANCO=?", [$banconoboleta, $bancoid]);
                if (!empty($result)) {
                    // $showmensaje = true;
                    // throw new Exception('El Numero de boleta ' . $banconoboleta . ' ya se ingresó en el sistema, con el banco seleccionado');
                }
                $id_ctb_tipopoliza = 11; // CUANDO ES POR BOLETA DE BANCO, EL TIPO DE POLIZA SE CAMBIA A NOTA DE CREDITO
                $result = $database->selectColumns('ctb_bancos', ['id', 'numcuenta', 'id_nomenclatura'], 'id=?', [$bancocuentaid]);
                $id_nomenclatura_caja = $result[0]['id_nomenclatura'];
            }

            //CONSULTAR LA NOMENCLATURA PARA CAPITAL, INTERES Y MORA
            $result = $database->getAllResults("SELECT cp.id_cuenta_capital, cp.id_cuenta_interes, cp.id_cuenta_mora, cp.id_cuenta_otros,cp.id_fondo 
                        FROM cre_productos cp INNER JOIN cremcre_meta cm ON cp.id=cm.CCODPRD WHERE cm.CCODCTA=?", [$ccodcta]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontraron los datos del producto de crédito");
            }
            $id_nomenclatura_capital = $result[0]['id_cuenta_capital'];
            $id_nomenclatura_interes = $result[0]['id_cuenta_interes'];
            $id_nomenclatura_mora = $result[0]['id_cuenta_mora'];
            $id_nomenclatura_otros = $result[0]['id_cuenta_otros'];
            $id_fondo = $result[0]['id_fondo'];

            //TRAER CNROCUO SIGUIENTE PARA EL CREDITO
            $result = $database->getAllResults("SELECT IFNULL(MAX(CNROCUO),0) nocuo FROM CREDKAR WHERE CTIPPAG='P' AND CESTADO!='X' AND CCODCTA =?;", [$ccodcta]);
            $nrocuo = (empty($result)) ? 1 : $result[0]['nocuo'] + 1;

            $database->beginTransaction();
            //INSERCION EN LA CREDKAR
            $datos = array(
                'CCODCTA' => $ccodcta,
                'DFECPRO' => $fecharecibo,
                'DFECSIS' => $hoy2,
                'CNROCUO' => $nrocuo, //CREAR LA FUNCION QUE ORDENE LOS PAGOS O REVISAR LA EXISTENTE
                'NMONTO' => $montotal,
                'CNUMING' => $numdocument,
                'CCONCEP' => $conceptopago,
                'KP' => $moncapital,
                'INTERES' => $moninteres,
                'MORA' => $monmora,
                'AHOPRG' => 0,
                'OTR' => $monotros,
                'CCODINS' => "1",
                'CCODOFI' => "1",
                'CCODUSU' => $idusuario,
                'CTIPPAG' => "P",
                'CMONEDA' => "Q",
                'CBANCO' => $bancocuentaid,
                'FormPago' => $metodopago,
                'CCODBANCO' => $bancoid,
                'DFECBANCO' => ($metodopago === '2') ? $bancofecboleta : "0000-00-00",
                'boletabanco' => $banconoboleta,
                'CESTADO' => "1",
                'DFECMOD' => $hoy2,
                'CTERMID' => "0",
                'MANCOMUNAD' => "0"
            );
            // $datos = utf8DecodeArray($datos);
            $id_credkar = $database->insert('CREDKAR', $datos);


            //INSERCION EN LA DIARIO
            // $numpartida = getnumcompdo($idusuario, $database);
            $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fecharecibo);
            $datos = array(
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => $id_ctb_tipopoliza,
                'id_tb_moneda' => 1,
                'numdoc' => $numdocument,
                'glosa' => $conceptopago,
                'fecdoc' => ($metodopago === '2') ? $bancofecboleta : $fecharecibo,
                'feccnt' => $fecharecibo,
                'cod_aux' => $ccodcta,
                'id_tb_usu' => $idusuario,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 0,
                'id_agencia' => $idagencia
            );
            // $datos = utf8DecodeArray($datos);
            $id_ctb_diario = $database->insert('ctb_diario', $datos);
            //MOVIMIENTOS LADO DEL DEBE (MONTO TOTAL=> CAJA Ó CUENTA BANCOS)
            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fondo,
                'id_ctb_nomenclatura' => $id_nomenclatura_caja,
                'debe' => $montotal,
                'haber' => 0
            );
            $database->insert('ctb_mov', $datos);
            //MOVIMIENTOS LADO DEL HABER (MONTO TOTAL => CARTERA JURIDICA)
            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fondo,
                'id_ctb_nomenclatura' => $id_nomenclatura_juridico,
                'debe' => 0,
                'haber' => $montotal
            );
            $database->insert('ctb_mov', $datos);

            //DETALLE OTROS EN LA CREDKAR_DETALLE
            foreach ($detallesotros as $rowval) {
                //[monto, idgasto, idcontable,modulo,codaho]
                $monf = $rowval[0];
                $idgasto = $rowval[1];
                $modulo = $rowval[3];
                if ($monf > 0) {
                    if ($idgasto == 0) {
                        //INSERCION DE OTROS, EN LA CONTA, NO ES UN GASTO ESPECIFICO
                        // $datos = array(
                        //     'id_ctb_diario' => $id_ctb_diario,
                        //     'id_fuente_fondo' => $id_fondo,
                        //     'id_ctb_nomenclatura' => $id_nomenclatura_otros,
                        //     'debe' =>  0,
                        //     'haber' => $monf
                        // );
                        // $database->insert('ctb_mov', $datos);
                    } else {
                        //INSERCION DE GASTOS ESPECIFICOS
                        // $datos = array(
                        //     'id_ctb_diario' => $id_ctb_diario,
                        //     'id_fuente_fondo' => $id_fondo,
                        //     'id_ctb_nomenclatura' => $rowval[2],
                        //     'debe' =>  0,
                        //     'haber' => $monf
                        // );
                        // $database->insert('ctb_mov', $datos);

                        //INSERCION DE GASTOS EN CREDKARDETALLE
                        $datos = array(
                            'id_credkar' => $id_credkar,
                            'id_concepto' => $idgasto,
                            'monto' => $monf
                        );
                        $database->insert('credkar_detalle', $datos);

                        //SI ES UN AHORRO VINCULADO
                        if ($modulo == '1') {
                            $datos = array(
                                "ccodaho" => $rowval[4],
                                "dfecope" => $fecharecibo,
                                "ctipope" => "D",
                                "cnumdoc" => $numdocument,
                                "ctipdoc" => "V",
                                "crazon" => "DEPOSITO VINCULADO",
                                "nlibreta" => $nlibreta,
                                "nrochq" => '0',
                                "tipchq" => "0",
                                "dfeccomp" => "0000-00-00",
                                "monto" => $monf,
                                "lineaprint" => "N",
                                "numlinea" => 1,
                                "correlativo" => 1,
                                "dfecmod" => $hoy2,
                                "codusu" => $idusuario,
                                "cestado" => 1,
                                "auxi" => $ccodcta,
                                "created_at" => $hoy2,
                                "created_by" => $idusuario,
                            );
                            $database->insert('ahommov', $datos);

                            // ORDENAMIENTO DE TRANSACCIONES
                            $database->executeQuery('CALL ahom_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
                            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$rowval[4]]);
                        }

                        //SI EXISTE UNA APORTACION VINCULADA
                        if ($modulo == '2') {
                            $datos = array(
                                "ccodaport" => $rowval[4],
                                "dfecope" => $fecharecibo,
                                "ctipope" => "D",
                                "cnumdoc" => $numdocument,
                                "ctipdoc" => "V",
                                "crazon" => "DEPOSITO VINCULADO",
                                "nlibreta" => $nlibreta,
                                "nrochq" => '0',
                                "tipchq" => "0",
                                "dfeccomp" => "0000-00-00",
                                "monto" => $monf,
                                "lineaprint" => "N",
                                "numlinea" => 1,
                                "correlativo" => 1,
                                "dfecmod" => $hoy2,
                                "codusu" => $idusuario,
                                "cestado" => 1,
                                "auxi" => $ccodcta,
                                "created_at" => $hoy2,
                                "created_by" => $idusuario,
                            );
                            $database->insert('aprmov', $datos);

                            // ORDENAMIENTO DE TRANSACCIONES
                            $database->executeQuery('CALL apr_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
                            $database->executeQuery('CALL apr_ordena_Transacciones(?);', [$rowval[4]]);
                        }
                    }
                }
            }
            //ACTUALIZACION DEL PLAN DE PAGO
            $database->executeQuery('CALL update_ppg_account(?);', [$ccodcta]);
            $database->executeQuery('SELECT calculo_mora(?);', [$ccodcta]);
            $database->commit();
            // $database->rollback();
            $mensaje = "Registro grabado correctamente";
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

        echo json_encode([$mensaje, $status, $idusuario, $ccodcta, $nrocuo, $numdocument]);
        break;
    case 'validar_mora_individual':
        $datosmora = $_POST['datosmora'];
        $bandera = false;
        //SEGUNDA CONSULTA PARA LOS PLANES DE PAGO
        $i = 0;
        $consulta = mysqli_query($conexion, "SELECT cpg.Id_ppg AS id, cpg.dfecven, IF((timestampdiff(DAY,cpg.dfecven,'$hoy'))<0, 0,(timestampdiff(DAY,cpg.dfecven,'$hoy'))) AS diasatraso, cpg.cestado, cpg.cnrocuo AS numcuota, cpg.ncappag AS capital, cpg.nintpag AS interes, cpg.nmorpag AS mora, cpg.AhoPrgPag AS ahorropro, cpg.OtrosPagosPag AS otrospagos
			FROM Cre_ppg cpg
			WHERE cpg.cestado='X' AND cpg.ccodcta='" . $datosmora[0][0] . "'
			ORDER BY cpg.ccodcta, cpg.dfecven, cpg.cnrocuo");
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $datoscreppg[$i] = $fila;
            $i++;
            $bandera = true;
        }
        //ORDENAR ARRAYS PARA LA IMPRESION DE DATOS
        $cuotasvencidas = array_filter($datoscreppg, function ($sk) {
            return $sk['diasatraso'] > 0;
        });
        //FILTRAR UN SOLO REGISTRO NO PAGADO
        $cuotasnopagadas = array_filter($datoscreppg, function ($sk) {
            return $sk['diasatraso'] == 0;
        });

        //SECCION DE REESTRUCTURACION 
        unset($datoscreppg);
        $datoscreppg[] = [];
        $sumamora = 0;
        $j = 0;
        //OBTIENE CUOTA VENCIDAS SI HUBIERAN
        if (count($cuotasvencidas) != 0) {
            for ($i = $j; $i < count($cuotasvencidas); $i++) {
                $datoscreppg[$i] = $cuotasvencidas[$i];
                $j++;
            }
        }
        //TRAE LOS PAGOS A LA FECHA SI HUBIERAN Y SINO TRAE LA SIGUIENTE EN CASO DE QUE NO HAYAN CUOTAS VENCIDAS
        if (count($cuotasnopagadas) != 0) {
            for ($i = $j; $i < count($cuotasnopagadas); $i++) {
                if ($cuotasnopagadas[$i]['dfecven'] <= $hoy2) {
                    $datoscreppg[$j] = $cuotasnopagadas[$j];
                    $i = 2000;
                    $j++;
                } else {
                    if (count($cuotasvencidas) == 0) {
                        $datoscreppg[$j] = $cuotasnopagadas[$j];
                        $i = 2000;
                        $j++;
                    }
                }
            }
        }
        $sumamora = 'x';
        if (count($datoscreppg) != 0) {
            $sumamora = array_sum(array_column($datoscreppg, "mora"));
            $sumamora = round($sumamora, 2);
        }
        if ($bandera) {
            if ($sumamora == $datosmora[0][1]) {
                echo json_encode(['La suma anterior y actual son iguales', '1', 0, $sumamora, $datosmora]);
            } else {
                echo json_encode(['La suma anterior y actual no son iguales', '1', 1, $sumamora, $datosmora]);
            }
        } else {
            $sumamora = 'x';
            echo json_encode(['No se encontraron datos', '0', 'x', $sumamora]);
            return;
        }
        mysqli_close($conexion);
        break;
    case 'validar_mora_grupal':
        $datosmora = $_POST['datosmora'];
        $bandera = false;

        //CREDITOS DEL GRUPO
        $datos[] = [];
        $datacre = mysqli_query($conexion, 'SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo, cli.short_name, cre.CCODCTA,cre.NCiclo,cre.MonSug,cre.DFecDsbls,cre.NCapDes,
        IFNULL((SELECT  SUM(KP) FROM CREDKAR WHERE ctippag="P" AND CESTADO!="X" AND ccodcta=cre.CCODCTA GROUP BY ccodcta),0) cappag,prod.id_fondo From cremcre_meta cre
        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
        INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
        INNER JOIN cre_productos prod ON prod.id=cre.CCODPRD
        WHERE cre.TipoEnti="GRUP" AND cre.NCiclo=' . $_POST['ciclo'] . ' AND cre.CESTADO="F" AND cre.CCodGrupo="' . $_POST['idgrupo'] . '"');

        $i = 0;
        while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
            $datos[$i] = $da;
            $i++;
            $bandera = true;
        }
        $bandera = false;

        //CUOTAS PENDIENTES DEL GRUPO
        $cuotas[] = [];
        $datacuo = mysqli_query($conexion, 'SELECT timestampdiff(DAY,ppg.dfecven,"' . $hoy . '") atraso,ppg.* FROM Cre_ppg ppg WHERE ccodcta IN (SELECT cre.CCODCTA From cremcre_meta cre WHERE cre.CESTADO="F" AND cre.CCodGrupo="' . $_POST['idgrupo'] . '") 
                            AND ppg.CESTADO="X" ORDER BY ppg.ccodcta,ppg.dfecven,ppg.cnrocuo');
        $i = 0;
        while ($da = mysqli_fetch_array($datacuo, MYSQLI_ASSOC)) {
            $cuotas[$i] = $da;
            $i++;
            $bandera = true;
        }

        //UNION DE TODOS LOS DATOS
        if ($bandera) {
            $datacom[] = [];
            $j = 0;
            while ($j < count($datos)) {
                $ccodcta = $datos[$j]["CCODCTA"];
                $datos[$j]["cuotaspen"] = [];
                $datacom[$j] = $datos[$j];

                //FILTRAR LAS CUOTAS DE LA CUENTA ACTUAL
                $keys = filtro($cuotas, "ccodcta", $ccodcta, $ccodcta);
                $fila = 0;
                $count = 0;
                while ($fila < count($keys)) {
                    $i = $keys[$fila];
                    $fecven = $cuotas[$i]["dfecven"];
                    if ($fecven <= $hoy) {
                        $cuotas[$i]["estado"] = ($fecven < $hoy) ? 2 : 1;
                        $count++;
                    } else {
                        $cuotas[$i]["estado"] = 0;
                    }
                    $datacom[$j]["cuotaspen"][$fila] = $cuotas[$i];
                    $fila++;
                }
                //COMPROBAR SI SOLO TIENE CUOTAS VENCIDAS O IMPRIMIR LA CUOTA SIGUIENTE A PAGAR
                if (count(filtro($datacom[$j]["cuotaspen"], 'estado', 1, 2)) == 0) {
                    //echo 'No hay cuotas vencidas o por vencer'; SE IMPRIMIRA SIGUIENTE NO PAGADA
                    $keyses = filtro($datacom[$j]["cuotaspen"], 'estado', 0, 0);
                    $fa = 0;
                    while ($fa < count($keyses) && $fa < 1) {
                        $il = $keyses[$fa];
                        $datacom[$j]["cuotaspen"][$il]["estado"] = 3;
                        $fa++;
                    }
                }
                //ELIMINACION DEL ARRAY LAS CUOTAS QUE NO SERAN IMPRESAS
                $keynot = filtro($datacom[$j]["cuotaspen"], 'estado', 0, 0);
                $faf = 0;
                while ($faf < count($keynot)) {
                    $il = $keynot[$faf];
                    unset($datacom[$j]["cuotaspen"][$il]);
                    $faf++;
                }
                // $datacom[$j]["sumaho"] = array_sum(array_column($datacom[$j]["cuotaspen"], "AhoPrgPag"));
                $datacom[$j]["summora"] = array_sum(array_column($datacom[$j]["cuotaspen"], "nmorpag"));
                $j++;
            }
            $sumatotalmoraant = array_sum(array_column($datacom, "summora"));
            $sumatotalmoraant = round($sumatotalmoraant, 2);
            $sumatotalmoranew = array_sum(array_column($datosmora, 4));
            $sumatotalmoranew = round($sumatotalmoranew, 2);
        }
        if ($bandera) {
            if ($sumatotalmoraant == $sumatotalmoranew) {
                echo json_encode(['La suma anterior y actual son iguales', '1', 0, $sumatotalmoraant, $sumatotalmoranew]);
            } else {
                echo json_encode(['La suma anterior y actual no son iguales', '1', 1, $sumatotalmoraant, $sumatotalmoranew]);
            }
        } else {
            $sumatotalmoraant = 'x';
            echo json_encode(['No se encontraron datos', '0', 'x', $sumatotalmoraant]);
            return;
        }
        mysqli_close($conexion);
        break;
    case 'consultar_plan_pago':
        $ccodcta = $_POST['codigocredito'];
        $consulta = mysqli_query($conexion, "SELECT cpg.Id_ppg AS id, cpg.dfecven, IF((timestampdiff(DAY,cpg.dfecven,'$hoy'))<0, 0,(timestampdiff(DAY,cpg.dfecven,'$hoy'))) AS diasatraso, cpg.cestado, cpg.cnrocuo AS numcuota, cpg.ncappag AS capital, cpg.nintpag AS interes, cpg.nmorpag AS mora, cpg.AhoPrgPag AS ahorropro, cpg.OtrosPagosPag AS otrospagos
        FROM Cre_ppg cpg
        WHERE cpg.ccodcta='$ccodcta'
        ORDER BY cpg.ccodcta, cpg.dfecven, cpg.cnrocuo");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $estado = '';
            if ($fila['cestado'] == 'P') {
                $estado = '<span class="badge text-bg-success">Pagado</span>';
            } elseif ($fila['cestado'] == 'X' && $fila['diasatraso'] > 0) {
                $estado = '<span class="badge text-bg-danger">Vencido</span>';
            } else {
                $estado = '<span class="badge text-bg-primary">Por pagar</span>';
            }
            $array_datos[$i] = array(
                "0" => $fila["numcuota"],
                "1" => $fila["dfecven"],
                "2" => $estado,
                "3" => ($fila['cestado'] == 'P') ? (0) : ($fila["diasatraso"]),
                "4" => $fila["capital"],
                "5" => $fila["interes"],
                "6" => $fila["mora"],
                "7" => $fila["ahorropro"],
                "8" => $fila["otrospagos"]
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($array_datos),
            "iTotalDisplayRecords" => count($array_datos),
            "aaData" => $array_datos
        );
        echo json_encode($results);
        mysqli_close($conexion);
        break;
    case 'list_reimp_creditos_indi':
        $consulta = mysqli_query($conexion, "SELECT ck.CCODCTA AS ccodcta, ck.CNUMING AS recibo, cm.NCiclo AS ciclo, ck.DFECPRO AS fecha, ck.NMONTO AS monto, ck.CNROCUO AS numcuota
        FROM CREDKAR ck
        INNER JOIN cremcre_meta cm ON ck.CCODCTA=cm.CCODCTA
        WHERE ck.CTIPPAG='P' AND ck.CESTADO!='X' AND cm.TipoEnti='INDI'");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {

            $data = implode("||", $fila);

            $array_datos[] = array(
                "0" => $fila["ccodcta"],
                "1" => $fila["recibo"],
                "2" => $fila["ciclo"],
                "3" => $fila["fecha"],
                "4" => $fila["monto"],

                "5" => '<button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="reportes([[], [], [], [`' . $_SESSION['nombre'] . ' ' . $_SESSION['apellido'] . '`,`' . $fila["ccodcta"] . '`,`' . $fila["numcuota"] . '`,`' . $fila["recibo"] . '`]], `pdf`, `comp_individual`, 0)"><i class="fa-solid fa-print me-2"> </i>Reimpimir</button> 

                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#staticBackdrop" onclick="capData(' . $data . ',["1","2"])"><i class="fa-sharp fa-solid fa-pen-to-square"></i></button>

                <button type="button" class="btn btn-outline-danger btn-sm mt-2"><i class="fa-solid fa-trash-can"></i></button>'

            );
            $i++;
        }
        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($array_datos),
            "iTotalDisplayRecords" => count($array_datos),
            "aaData" => $array_datos
        );
        echo json_encode($results);
        mysqli_close($conexion);
        break;

    case 'recibosgrupal':
        $consulta = mysqli_query($conexion, "SELECT kar.CODKAR, kar.CNUMING, kar.DFECPRO,SUM(kar.NMONTO) NMONTO,grup.NombreGrupo,cre.CCodGrupo,cre.NCiclo FROM CREDKAR kar
        INNER JOIN cremcre_meta cre on cre.ccodcta=kar.CCODCTA
        INNER JOIN tb_grupo grup on grup.id_grupos=cre.CCodGrupo
		WHERE kar.CTIPPAG='P' AND kar.CESTADO!='X' AND cre.TipoEnti='GRUP' GROUP BY cre.CCodGrupo,cre.NCiclo, kar.CNUMING
        ORDER BY kar.DFECPRO,kar.CNUMING,kar.CCODCTA");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $array_datos[$i] = array(
                "0" => $i + 1,
                "1" => $fila["NombreGrupo"],
                "2" => $fila["NCiclo"],
                "3" => $fila["CNUMING"],
                "4" => date("d-m-Y", strtotime($fila["DFECPRO"])),
                "5" => $fila["NMONTO"],
                "6" => '<button type="button" class="btn btn-outline-primary" onclick="reportes([[], [], [], [' . $fila["CCodGrupo"] . ',`' . $fila["CNUMING"] . '`,' . $fila["NCiclo"] . ']], `pdf`, `comp_grupal`, 0);"><i class="fa-solid fa-print"></i> Reimpresion</button>',
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($array_datos),
            "iTotalDisplayRecords" => count($array_datos),
            "aaData" => $array_datos
        );
        echo json_encode($results);
        mysqli_close($conexion);
        break;

    //Actualizar Recibo de credito individual
    case 'actReciCreIndi':
        $inputs = $_POST["inputs"];
        $codusu = $_POST["archivo"];

        $conexion->autocommit(false);
        //Obtener informaicon de la credkar
        $consulta = mysqli_query($conexion, "SELECT CCODCTA, DFECPRO, CNUMING, CAST(DFECSIS AS DATE) FROM CREDKAR WHERE CODKAR = $inputs[0]");
        $dato = $consulta->fetch_row();

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $dato[3]);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }

        //Validar si existe los datos en la ctb_Diario
        $validarDatos = $conexion->query("SELECT EXISTS(SELECT * FROM ctb_diario WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]') AS Resultado");

        // Si la consulta no fue exitosa 
        $resultado = $validarDatos->fetch_assoc()['Resultado'];
        if ($resultado == 0) {
            // echo json_encode(['Los datos no existen en el IDARIO, no se puede actualizar', '0']);
            // return;
        } //Fin validad repetidos

        try {
            $res = $conexion->query("UPDATE CREDKAR SET CNUMING = '$inputs[1]', DFECPRO = '$inputs[2]', CCONCEP = '$inputs[3]', updated_by = $codusu[0], updated_at = '$hoy2'  WHERE CODKAR = $inputs[0]");
            $aux = mysqli_error($conexion);

            $res1 = $conexion->query("UPDATE ctb_diario SET numdoc = '$inputs[1]', fecdoc = '$inputs[2]', feccnt = '$inputs[2]', updated_by = $codusu[0], updated_at = '$hoy2'  WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
            $aux1 = mysqli_error($conexion);

            if ($aux && $aux1) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res && !$res1) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos se actualizaron con éxito. ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, en la actualización: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;

    //INI

    case 'eliReIndi':
        $idDato = $_POST["ideliminar"];
        $codusu = $_POST["archivo"];

        $conexion->autocommit(false);
        //Obtener informaicon de la credkar
        $consulta = mysqli_query($conexion, "SELECT CCODCTA, DFECPRO, CNUMING, CAST(DFECSIS AS DATE) AS DFECSIS FROM CREDKAR WHERE CODKAR = $idDato");
        $dato = $consulta->fetch_row();

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $dato[3]);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }

        //Validar si existe los datos en la ctb_Diario
        $validarDatos = $conexion->query("SELECT EXISTS(SELECT * FROM ctb_diario WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]') AS Resultado");
        // Si la consulta no fue exitosa 
        $resultado = $validarDatos->fetch_assoc()['Resultado'];

        try {
            $res = $conexion->query("UPDATE CREDKAR SET CESTADO = 'X', deleted_by = $codusu, deleted_at = '$hoy2'  WHERE CODKAR = $idDato");
            $aux = mysqli_error($conexion);

            $res1 = $conexion->query("UPDATE ctb_diario SET estado = '0', deleted_by = $codusu, deleted_at = '$hoy2'  WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
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

            //ACTUALIZACION DEL PLAN DE PAGO
            $res = $conexion->query("CALL update_ppg_account('" . $dato[0] . "')");
            if (!$res) {
                echo json_encode(['Error al actualizar el plan de pago ' . $i, '0']);
                $conexion->rollback();
                return;
            }

            $conexion->commit();
            //$conexion->rollback();//cambiar por comit
            echo json_encode(['Los datos fueron actualizados con exito ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        return;

        break;
    //Recibo de gruop
    case 'reciboDeGrupos':
        $data = $_POST['extra'];
        $data1 = explode("||", $data);
        ob_start();
        $consulta = mysqli_query($conexion, "SELECT cli.short_name AS nomCli, kar.CCONCEP
        FROM CREDKAR AS kar
        INNER JOIN cremcre_meta AS creMet ON kar.CCODCTA = creMet.CCODCTA 
        INNER JOIN tb_cliente AS cli ON cli.idcod_cliente = creMet.CodCli
        INNER JOIN tb_grupo AS gru ON gru.id_grupos = creMet.CCodGrupo
        WHERE kar.CESTADO != 'X' AND  kar.CNUMING = '$data1[0]' AND creMet.CCodGrupo = $data1[1] AND creMet.NCiclo = $data1[2]");

        $totalData = mysqli_affected_rows($conexion); //Cantidad de información que se esta retornando
        $con = 0;
        $flag = 0;

        $izq = $totalData / 2;
        if (($totalData % 2) != 0) {
            $izq = (intval($izq)) + 1;
        }

        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $con = $con + 1;
            if ($flag == 0) {
                $flag++; ?>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="accordion" id="accordionUno">
                        <?php }

                    if ($con <= $izq) {
                        ?>
                            <!-- INI -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="<?= 'heading' . $con ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="<?= '#collapse' . $con ?>" aria-expanded="false"
                                        aria-controls="<?= 'collapse' . $con ?>">
                                        <b> - </b><label><?php echo $row['nomCli'] ?></label><br>
                                    </button>
                                </h2>

                                <div id="<?= 'collapse' . $con ?>" class="accordion-collapse collapse"
                                    aria-labelledby="<?= 'heading' . $con ?>" data-bs-parent="#accordionUno">
                                    <div class="accordion-body">

                                        <div class="mb-3">
                                            <label for="exampleFormControlTextarea1" class="form-label"><b>Concepto</b></label>
                                            <textarea class="form-control" name="datoCon" rows="3"
                                                id="<?= $con . 'concep' ?>"> <?php echo $row['CCONCEP'] ?> </textarea>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <!-- FIN  -->

                        <?php }
                    if ($con == $izq) {
                        ?>
                        </div><!-- cerrar el acordion 1 -->
                    </div><!-- cerrar la calumna -->

                    <div class="col-lg-6">
                        <div class="accordion" id="accordionUno">
                        <?php
                    }
                    if ($con > $izq) {
                        ?>
                            <!-- INI -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="<?= 'heading' . $con ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="<?= '#collapse' . $con ?>" aria-expanded="false"
                                        aria-controls="<?= 'collapse' . $con ?>">
                                        <b> - </b><label><?php echo $row['nomCli'] ?></label><br>
                                    </button>
                                </h2>

                                <div id="<?= 'collapse' . $con ?>" class="accordion-collapse collapse"
                                    aria-labelledby="<?= 'heading' . $con ?>" data-bs-parent="#accordionUno">
                                    <div class="accordion-body">

                                        <div class="mb-3">
                                            <label for="exampleFormControlTextarea1" class="form-label"><b>Concepto</b></label>
                                            <textarea class="form-control" name="datoCon" rows="3"
                                                id="<?= $con . 'concep' ?>"> <?php echo $row['CCONCEP'] ?> </textarea>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            <!-- FIN  -->
                            <?php
                            if ($con == $totalData) {
                            ?>
                        </div><!-- cerrar el acordion 1 -->
                    </div><!-- cerrar la calumna -->
        <?php
                            }
                        }
                    }
        ?>
                </div><!-- Cerrar la fila -->

                <input type="text" id="total" value="<?= $totalData ?>" disabled hidden>



        <?php

        $output = ob_get_clean();
        echo $output;

        break;

    //Actuliza  recibos grupales
    case 'actReciCreGru':
        $inputs = $_POST["inputs"];
        $codusu = $_POST["archivo"];
        $concep = $codusu[1];
        $conexion->autocommit(false);
        //Obtener las ides 
        $consultado = mysqli_query($conexion, "SELECT kar.CODKAR 
        FROM CREDKAR AS kar
        INNER JOIN cremcre_meta AS creMet ON kar.CCODCTA = creMet.CCODCTA 
        INNER JOIN tb_cliente AS cli ON cli.idcod_cliente = creMet.CodCli
        INNER JOIN tb_grupo AS gru ON gru.id_grupos = creMet.CCodGrupo
        WHERE kar.CESTADO != 'X' AND  kar.CNUMING = '$inputs[4]' AND creMet.CCodGrupo = $inputs[0]  AND creMet.NCiclo = $inputs[3]");

        $totalF = mysqli_affected_rows($conexion); //Total de filas afectadas
        $conR = 0; //Contador de resultados

        while ($dato1 = mysqli_fetch_row($consultado)) {
            //Obtener la informacion de la CREDKAR 
            $consultado1 = mysqli_query($conexion, "SELECT CCODCTA, DFECPRO, CNUMING, CAST(DFECSIS AS DATE) AS DFECSIS FROM CREDKAR WHERE CODKAR = $dato1[0]");
            $dato = $consultado1->fetch_row();

            //COMPROBAR CIERRE DE CAJA
            $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
            $fechafin = date('Y-m-d');
            $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $dato[3]);
            if ($cierre_caja[0] < 6) {
                echo json_encode([$cierre_caja[1], '0']);
                return;
            }

            //Validar si existe los datos en la ctb_Diario
            $validarDatos = $conexion->query("SELECT EXISTS(SELECT * FROM ctb_diario WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]') AS Resultado");
            //Caputrar el resultado 
            $resultado = $validarDatos->fetch_assoc()['Resultado'];

            //Si el resultado es 1 sumarlo en conR
            if ($resultado == 1) {
                $conR = $conR + 1;
            } else {
                echo json_encode(['Uno de los datos no existen en el diario, no se puede actualizar la información', '0']);
                return;
            }
        }

        try {
            $aux1 = 0;
            $aux2 = 0;
            $con = 0;

            //Obtener las ides que se tienen que actualizar 
            $consultado = mysqli_query($conexion, "SELECT kar.CODKAR 
            FROM CREDKAR AS kar
            INNER JOIN cremcre_meta AS creMet ON kar.CCODCTA = creMet.CCODCTA 
            INNER JOIN tb_cliente AS cli ON cli.idcod_cliente = creMet.CodCli
            INNER JOIN tb_grupo AS gru ON gru.id_grupos = creMet.CCodGrupo
            WHERE kar.CESTADO != 'X' AND  kar.CNUMING = '$inputs[4]' AND creMet.CCodGrupo = $inputs[0]  AND creMet.NCiclo = $inputs[3]");

            if ($totalF == $conR) {
                while ($dato1 = mysqli_fetch_row($consultado)) {

                    // echo json_encode(['Re. '.$inputs[1].' Fe. '.$inputs[2], '0']);
                    // $conexion->rollback();
                    // return;

                    $consultado1 = mysqli_query($conexion, "SELECT CCODCTA, DFECPRO, CNUMING FROM CREDKAR WHERE CODKAR = $dato1[0]");
                    $dato = $consultado1->fetch_row();

                    //Actualiza la CREDKAR
                    $res = $conexion->query("UPDATE CREDKAR SET CNUMING = '$inputs[1]', DFECPRO = '$inputs[2]', CCONCEP = '$concep[$con]', updated_by = $codusu[0], updated_at = '$hoy2'  WHERE CODKAR = $dato1[0]");

                    $error1 = mysqli_error($conexion);

                    //Actualiza el diario 
                    $res1 = $conexion->query("UPDATE ctb_diario SET numdoc = '$inputs[1]', fecdoc = '$inputs[2]', feccnt = '$inputs[2]', updated_by = $codusu[0], updated_at = '$hoy2'  WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
                    $error2 = mysqli_error($conexion);

                    if ($error1 && $error2) {
                        echo json_encode(['Error', '0']);
                        $conexion->rollback();
                        return;
                    }
                    if (!$res && !$res1) {
                        echo json_encode(['Error al ingresar ', '0']);
                        $conexion->rollback();
                        return;
                    }
                    if ($res)
                        $aux1++;
                    if ($res1)
                        $aux2++;

                    $con++;
                }
            }
            if ($aux1 == $aux2) {
                $conexion->commit();
                echo json_encode(['Los datos se actualizaron con éxito. ', '1']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, en la actualización: ' . $e->getMessage(), '0']);
        }

        mysqli_close($conexion);

        break;

    //Eliminar recibo de grupos
    case 'eliReGru':
        $data = $_POST["ideliminar"];
        $identificado = explode("|*-*|", $data);
        $codusu = $_POST["archivo"];

        $conexion->autocommit(false);
        //Obtener las ides 
        $consultado = mysqli_query($conexion, "SELECT kar.CODKAR 
            FROM CREDKAR AS kar
            INNER JOIN cremcre_meta AS creMet ON kar.CCODCTA = creMet.CCODCTA 
            INNER JOIN tb_cliente AS cli ON cli.idcod_cliente = creMet.CodCli
            INNER JOIN tb_grupo AS gru ON gru.id_grupos = creMet.CCodGrupo
            WHERE kar.CESTADO != 'X' AND  kar.CNUMING = '$identificado[0]' AND creMet.CCodGrupo = $identificado[1]  AND creMet.NCiclo = $identificado[2]");

        $totalF = mysqli_affected_rows($conexion); //Total de filas afectadas
        $conR = 0; //Contador de resultados

        while ($dato1 = mysqli_fetch_row($consultado)) {
            //Obtener la informacion de la CREDKAR 
            $consultado1 = mysqli_query($conexion, "SELECT CCODCTA, DFECPRO, CNUMING, CAST(DFECSIS AS DATE) AS DFECSIS FROM CREDKAR WHERE CODKAR = $dato1[0]");
            $dato = $consultado1->fetch_row();

            //COMPROBAR CIERRE DE CAJA
            $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
            $fechafin = date('Y-m-d');
            $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $dato[3]);
            if ($cierre_caja[0] < 6) {
                echo json_encode([$cierre_caja[1], '0']);
                return;
            }

            //Validar si existe los datos en la ctb_Diario
            $validarDatos = $conexion->query("SELECT EXISTS(SELECT * FROM ctb_diario WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]') AS Resultado");
            //Caputrar el resultado 
            $resultado = $validarDatos->fetch_assoc()['Resultado'];

            //Si el resultado es 1 sumarlo en conR
            if ($resultado == 1) {
                $conR = $conR + 1;
            } else {
                echo json_encode(['Uno de los datos no existen en el diario, no se puede actualizar la información', '0']);
                return;
            }
        }

        try {
            $aux1 = 0;
            $aux2 = 0;
            $con = 0;
            //Obtener las ides que se tienen que actualizar 
            $consultado = mysqli_query($conexion, "SELECT kar.CODKAR 
            FROM CREDKAR AS kar
            INNER JOIN cremcre_meta AS creMet ON kar.CCODCTA = creMet.CCODCTA 
            INNER JOIN tb_cliente AS cli ON cli.idcod_cliente = creMet.CodCli
            INNER JOIN tb_grupo AS gru ON gru.id_grupos = creMet.CCodGrupo
            WHERE kar.CESTADO != 'X' AND  kar.CNUMING = '$identificado[0]' AND creMet.CCodGrupo = $identificado[1]  AND creMet.NCiclo = $identificado[2]");

            if (!$consultado) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }

            if ($totalF == $conR) {
                while ($dato1 = mysqli_fetch_row($consultado)) {

                    $consultado1 = mysqli_query($conexion, "SELECT CCODCTA, DFECPRO, CNUMING FROM CREDKAR WHERE CODKAR = $dato1[0]");
                    $dato = $consultado1->fetch_row();

                    //Actualiza la CREDKAR
                    $res = $conexion->query("UPDATE CREDKAR SET CESTADO = 'X' , updated_by = $codusu[0], updated_at = '$hoy2'  WHERE CODKAR = $dato1[0]");
                    $error1 = mysqli_error($conexion);

                    //Actualiza el diario 
                    $res1 = $conexion->query("UPDATE ctb_diario SET estado = '0' , updated_by = $codusu[0], updated_at = '$hoy2'  WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
                    $error2 = mysqli_error($conexion);

                    if ($error1 || $error2) {
                        echo json_encode(['Error', '0']);
                        $conexion->rollback();
                        return;
                    }

                    if (!$res || !$res1) {
                        echo json_encode(['Error al ingresar ', '0']);
                        $conexion->rollback();
                        return;
                    }

                    //ACTUALIZACION DEL PLAN DE PAGO
                    $resAux = $conexion->query("CALL update_ppg_account('" . $dato[0] . "')");
                    if (!$resAux) {
                        echo json_encode(['Error al actualizar el plan de pago ' . $i, '0']);
                        $conexion->rollback();
                        return;
                    }

                    if ($res)
                        $aux1++;
                    if ($res1)
                        $aux2++;
                    $con++;
                }
            }

            if ($aux1 == $aux2) {
                $conexion->commit();
                echo json_encode(['Los datos se actualizaron con éxito. ', '1']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, en la actualización: ' . $e->getMessage(), '0']);
        }

        mysqli_close($conexion);
        //FIN
        break;
    case 'listado_aperturas':
        $puestouser = $_SESSION["puesto"];
        // $puestouser = "LOG";
        $mes = date('m');
        // $mes = '06';
        $anio = date('Y');
        $query1 = "SELECT CONCAT(usu.nombre,' ',usu.apellido) nomusu, tcac.id, tcac.id_usuario AS iduser, tcac.saldo_inicial AS saldoinicial, tcac.saldo_final AS saldofinal, tcac.estado AS estado, tcac.fecha_apertura AS fecha_apertura, tcac.fecha_cierre AS fecha_cierre
            FROM tb_caja_apertura_cierre tcac
            INNER JOIN tb_usuario usu on usu.id_usu=tcac.id_usuario
            WHERE (tcac.id_usuario = ? AND MONTH(tcac.fecha_apertura) = ? AND YEAR(tcac.fecha_apertura) = ?) OR (tcac.estado='1' AND tcac.id_usuario = ?) ORDER BY tcac.fecha_apertura DESC";

        $query2 = "SELECT CONCAT(usu.nombre,' ',usu.apellido) nomusu, tcac.id, tcac.id_usuario AS iduser, tcac.saldo_inicial AS saldoinicial, tcac.saldo_final AS saldofinal, tcac.estado AS estado, tcac.fecha_apertura AS fecha_apertura, tcac.fecha_cierre AS fecha_cierre
            FROM tb_caja_apertura_cierre tcac
            INNER JOIN tb_usuario usu on usu.id_usu=tcac.id_usuario
            WHERE (usu.id_agencia = ? AND MONTH(tcac.fecha_apertura) = ? AND YEAR(tcac.fecha_apertura) = ?) OR (tcac.estado='1' AND usu.id_agencia = ?) ORDER BY tcac.fecha_apertura DESC";

        $query3 = "SELECT CONCAT(usu.nombre,' ',usu.apellido) nomusu,tcac.id, tcac.id_usuario AS iduser, tcac.saldo_inicial AS saldoinicial, tcac.saldo_final AS saldofinal, tcac.estado AS estado, tcac.fecha_apertura AS fecha_apertura, tcac.fecha_cierre AS fecha_cierre
            FROM tb_caja_apertura_cierre tcac
            INNER JOIN tb_usuario usu on usu.id_usu=tcac.id_usuario
            WHERE (MONTH(tcac.fecha_apertura) = ? AND YEAR(tcac.fecha_apertura) = ?) OR (tcac.estado='1') ORDER BY tcac.fecha_apertura DESC";


        $mediumPermissionId = 5; // Permiso de apertura de caja a nivel de agencia
        $highPermissionId = 6;   // Permiso de apertura de caja a nivel general [Todas las agencias]

        $showmensaje = false;
        $array_datos = array();
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (5,6) AND id_usuario=? AND estado=1", [$idusuario]);

            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $queryfinal = ($accessHandler->isHigh()) ? $query3 : (($accessHandler->isMedium()) ? $query2 : $query1);
            $parametros = ($accessHandler->isHigh()) ? [$mes, $anio] : (($accessHandler->isMedium()) ? [$idagencia, $mes, $anio, $idagencia] : [$idusuario, $mes, $anio, $idusuario]);

            $datos = $database->getAllResults($queryfinal, $parametros);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("No hay datos");
            }
            $i = 0;
            foreach ($datos as $fila) {
                $fechacierre = ($fila["fecha_cierre"] == null) ? '0000-00-00' : date('d-m-Y', strtotime($fila["fecha_cierre"]));
                $array_datos[] = array(
                    "0" => $i + 1,
                    "1" => $fila["nomusu"],
                    "2" => date('d-m-Y', strtotime($fila["fecha_apertura"])),
                    "3" => number_format($fila["saldoinicial"], 2, '.', ','),
                    "4" => ($fila["estado"] == '1') ? 'Pendiente' : $fechacierre,
                    "5" => ($fila["estado"] == '1') ? 'Pendiente' : $fila["saldofinal"],
                    "6" => ($fila["estado"] == '2') ? '<span class="badge text-bg-success">Cerrada</span>' : (($fila["fecha_apertura"] < date('Y-m-d')) ? '<span class="badge text-bg-danger">Pendiente de cierre con atraso</span>' : '<span class="badge text-bg-warning">Pendiente de cierre</span>'),
                    "7" => ($fila["estado"] == '2' || $fila["estado"] == '1') ? '<button type="button" class="btn btn-danger btn-sm" onclick="reportes([[],[],[],[`' . $fila["id"] . '`]], `pdf`, `arqueo_caja`,0)"><i class="fa-solid fa-file-pdf"></i></button> 
                                                                                <button type="button" class="btn btn-success btn-sm" onclick="reportes([[],[],[],[`' . $fila["id"] . '`]], `xlsx`, `arqueo_caja`,1)"><i class="fa-solid fa-file-excel"></i></button>' : '<span class="badge text-bg-secondary">No aplica</span>',
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
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
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
    case 'listado_aperturas_conso':
        $puestouser = $_SESSION["puesto"];
        $mes = date('m');
        // $mes = '06';
        $anio = date('Y');
        $query1 = "SELECT SUM(saldo_inicial) saldoinicial,fecha_apertura,nom_agencia,fecha_cierre,MIN(tcac.estado) estado,SUM(saldo_final) saldofinal
                    FROM tb_caja_apertura_cierre tcac
                    INNER JOIN tb_usuario usu on usu.id_usu=tcac.id_usuario
                    INNER JOIN tb_agencia agen on agen.id_agencia=usu.id_agencia
                    WHERE (MONTH(fecha_apertura) = ? AND YEAR(fecha_apertura) = ?) AND usu.id_agencia=? GROUP BY fecha_apertura,usu.id_agencia ORDER BY fecha_apertura DESC,usu.id_agencia ASC";

        $query2 = "SELECT SUM(saldo_inicial) saldoinicial,fecha_apertura,nom_agencia,fecha_cierre,MIN(tcac.estado) estado,SUM(saldo_final) saldofinal
                    FROM tb_caja_apertura_cierre tcac
                    INNER JOIN tb_usuario usu on usu.id_usu=tcac.id_usuario
                    INNER JOIN tb_agencia agen on agen.id_agencia=usu.id_agencia
                    WHERE (MONTH(fecha_apertura) = ? AND YEAR(fecha_apertura) = ?) GROUP BY fecha_apertura,usu.id_agencia ORDER BY fecha_apertura DESC,usu.id_agencia ASC";

        $parametros = [$mes, $anio, $idagencia];

        $mediumPermissionId = 5; // Permiso de apertura de caja a nivel de agencia
        $highPermissionId = 6;   // Permiso de apertura de caja a nivel general [Todas las agencias]

        $showmensaje = false;
        $array_datos = array();
        try {
            $database->openConnection();

            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (5,6) AND id_usuario=? AND estado=1", [$idusuario]);

            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $queryfinal = ($accessHandler->isHigh()) ? $query2 : $query1;
            $parametros = ($accessHandler->isHigh()) ? [$mes, $anio] : [$mes, $anio, $idagencia];

            $datos = $database->getAllResults($queryfinal, $parametros);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("No hay datos");
            }
            $i = 0;
            foreach ($datos as $fila) {
                $fechacierre = ($fila["fecha_cierre"] == null) ? '0000-00-00' : date('d-m-Y', strtotime($fila["fecha_cierre"]));
                $array_datos[] = array(
                    "0" => $i + 1,
                    "1" => date('d-m-Y', strtotime($fila["fecha_apertura"])),
                    "2" => $fila["nom_agencia"],
                    "3" => number_format($fila["saldoinicial"], 2, '.', ','),
                    "4" => ($fila["estado"] == '1') ? 'Pendiente' : $fechacierre,
                    "5" => ($fila["estado"] == '1') ? 'Pendiente' : $fila["saldofinal"],
                    "6" => ($fila["estado"] == '2') ? '<span class="badge text-bg-success">Cerrada</span>' : (($fila["fecha_apertura"] < date('Y-m-d')) ? '<span class="badge text-bg-danger">Pendiente de cierre con atraso</span>' : '<span class="badge text-bg-warning">Pendiente de cierre</span>'),
                    "7" => ($fila["estado"] == '2') ? '<button type="button" class="btn btn-danger btn-sm" onclick="reportes([[],[],[],[0,`' . $fila["fecha_apertura"] . '`]], `pdf`, `arqueo_caja`,0)"><i class="fa-solid fa-file-pdf"></i></button> 
                                                        <button type="button" class="btn btn-success btn-sm" onclick="reportes([[],[],[],[0,`' . $fila["fecha_apertura"] . '`]], `xlsx`, `arqueo_caja`,1)"><i class="fa-solid fa-file-excel"></i></button>' : '<span class="badge text-bg-secondary">No aplica</span>',
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
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $results = [
                "errorn" => 'Error preparando consulta2: ' . $mensaje,
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
    case 'create_caja_apertura':
        /**
         * Codigo de registro de apertura de caja.
         * 
         * - Obtiene los valores de los inputs y selects enviados por POST.
         * - Asigna los valores de los inputs a variables.
         * - Valida los campos recibidos.
         * - Si la validación falla, retorna un mensaje de error en formato JSON.
         * - Consulta para verificar si ya existe un registro de apertura de caja del usuario seleccionado con la misma fecha o un cierre pendiente.
         * - Si ya existe un registro, lanza una excepción.
         * - Inserta los datos en la tabla `tb_caja_apertura_cierre` y confirma la transacción.
         * - Si ocurre un error, revierte la transacción y registra el error.
         * - Retorna el mensaje y el estado en formato JSON.
         * 
         * Variables:
         * - $inputs: Array con los valores de los inputs enviados por POST.
         * - $selects: Array con los valores de los selects enviados por POST.
         * - $fec_apertura: Fecha de apertura de la caja.
         * - $saldoinicial: Saldo inicial de la caja.
         * - $usuarioSeleccionado: ID del usuario seleccionado.
         * - $query: Consulta SQL para verificar registros existentes.
         * - $showmensaje: Booleano para controlar la visualización de mensajes de error.
         * - $datos: Resultados de la consulta SQL.
         * - $data: Datos a insertar en la tabla `tb_caja_apertura_cierre`.
         * - $mensaje: Mensaje de éxito o error.
         * - $status: Estado de la operación (1: éxito, 0: error).
         * - $codigoError: Código de error registrado.
         * 
         * Funciones utilizadas:
         * - validar_campos_plus(): Valida los campos recibidos.
         * - $database->openConnection(): Abre la conexión a la base de datos.
         * - $database->beginTransaction(): Comienza una transacción.
         * - $database->getAllResults(): Ejecuta una consulta SQL y obtiene los resultados.
         * - $database->insert(): Inserta datos en la base de datos.
         * - $database->commit(): Confirma la transacción.
         * - $database->rollback(): Revierte la transacción.
         * - logerrores(): Registra un error en el sistema.
         * - $database->closeConnection(): Cierra la conexión a la base de datos.
         */

        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];

        list($fec_apertura, $saldoinicial) = $inputs;
        list($usuarioSeleccionado) = $selects;

        $validar = validar_campos_plus([
            [$usuarioSeleccionado, "0", 'Seleccione un usuario válido', 1],
            [$fec_apertura, "", 'Debe existir una fecha de apertura', 1],
            // [$fec_apertura, date('Y-m-d'), 'La fecha de apertura deber ser igual a la fecha de hoy', 2],
            // [$fec_apertura, date('Y-m-d'), 'La fecha de apertura deber ser igual a la fecha de hoy', 3],
            [$saldoinicial, "", 'Debe digitar un un saldo inicial', 1],
            [$saldoinicial, 0, 'Debe digitar un saldo inicial mayor o igual a 0', 2],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $query = "SELECT * FROM tb_caja_apertura_cierre tcac WHERE 
                    (tcac.fecha_apertura = ? AND tcac.id_usuario=? AND tcac.estado='1') 
                        OR (tcac.fecha_apertura < ? AND tcac.id_usuario=? AND tcac.estado='1')";

        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();

            $datos = $database->getAllResults($query, [$fec_apertura, $usuarioSeleccionado, $fec_apertura, $usuarioSeleccionado]);

            if (!empty($datos)) {
                $showmensaje = true;
                throw new Exception("Ya existe un registro de apertura de caja con la misma fecha o bien tiene un cierre pendiente");
            }

            $data = array(
                'id_usuario' => $usuarioSeleccionado,
                'saldo_inicial' => $saldoinicial,
                'saldo_final' => '0',
                'fecha_apertura' => $fec_apertura,
                'estado' => '1',
                'created_at' => $hoy2,
                'updated_at' => NULL,
                'created_by' => $idusuario,
                'updated_by' => NULL,
            );

            $database->insert("tb_caja_apertura_cierre", $data);
            $database->commit();

            // Mensaje de éxito
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
    case 'create_caja_cierre':

        if (!isset($_SESSION['id'])) {
            echo json_encode(["Sesión expirada, Inicie sesion nuevamente", 0]);
            return;
        }

        list($csrftoken) = $_POST["inputs"];
        list($encryptedID, $saldoinicial, $saldofinal) = $_POST["archivo"];

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

        $validar = validar_campos_plus([
            [$encryptedID, "", 'No se encontro el identificador de la apertura de caja', 1],
            [$encryptedID, '0', 'El identificador de apertura de caja debe ser válido', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $decryptedID = $secureID->decrypt($encryptedID);
        $status = 0;
        try {
            $database->openConnection();
            $result = $database->selectColumns('tb_caja_apertura_cierre', ['estado'], 'id=?', [$decryptedID]);
            if (empty($result)) {
                throw new SoftException("No se encontró el cierre especificado");
            }
            if ($result[0]['estado'] == 2) {
                throw new SoftException("El cierre ya fue realizado, no se puede sobreescribir");
            }
            $database->beginTransaction();
            $data = array(
                'saldo_final' => $saldofinal,
                'fecha_cierre' => $hoy,
                'estado' => '2',
                'updated_at' => $hoy2,
                'updated_by' => $idusuario,
            );

            $database->update("tb_caja_apertura_cierre", $data, "id=?", [$decryptedID]);

            $database->commit();
            $mensaje = "Caja cerrada correctamente";
            $status = 1;
        } catch (SoftException $e) {
            $database->rollback();
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            $database->rollback();
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

        $idregistro = $decryptedID ?? 0;

        echo json_encode([$mensaje, $status, $idregistro]);
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
            $stmt = $conexion->prepare("SELECT `nombre`,`file` FROM tb_documentos td WHERE td.id_reporte = ?");
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
    case 'create_boveda_completo': {
            // 1. Recoger datos
            $agencia = $_POST['agencia'] ?? '';
            $fecha = $_POST['fecha'] ?? '';
            $saldoinicial = $_POST['saldoinicial'] ?? '';
            $detalleJSON = $_POST['detalle'] ?? '';
            $tipo_mov = $_POST['tipo_mov'] ?? 'apr';
            $concepto = $_POST['concepto'] ?? '';  // Recoger concepto

            // 2. Validar campos comunes
            $rules = [
                [$agencia, "0", 'Seleccione una agencia válida', 1],
                [$saldoinicial, "", 'Debe digitar un monto', 1],
                [$saldoinicial, 0, 'Monto ≥ 0', 2],
                [$detalleJSON, "", 'Debe ingresar al menos un detalle', 1],
                [$concepto, "", 'Debe ingresar un concepto', 1],  // Validación del campo concepto
            ];

            // Validación de campos adicionales
            $validar = validar_campos_plus($rules);
            if ($validar[2]) {
                echo json_encode([$validar[0], 0]);
                return;
            }

            try {
                // 3. Abrir conexión
                $database->openConnection();

                // 4. Verificar si ya existe una apertura de bóveda para la agencia el mismo día
                if ($tipo_mov === 'apr') {
                    $existe_apertura = $database->getAllResults(
                        "SELECT bm.id
                           FROM bov_mov bm
                           JOIN tb_boveda_apertura_cierre ba ON bm.id = ba.id_boveda
                          WHERE bm.tip_mov = 'apr'
                            AND ba.estado = 1
                            AND bm.id_agencia = ?
                            AND DATE(ba.fecha_apertura) = ?",
                        [$agencia, $fecha]
                    );
                    if (!empty($existe_apertura)) {
                        echo json_encode(["Ya existe una apertura de bóveda registrada para esta agencia", 0]);
                        return;
                    }
                }

                // 5. Iniciar transacción
                $database->beginTransaction();

                // 6. Inserto movimiento en bov_mov
                $movId = $database->insert('bov_mov', [
                    'id_agencia' => $agencia,
                    'fecha' => $fecha,
                    'monto' => $saldoinicial,
                    'tip_mov' => $tipo_mov,
                    'concepto' => $concepto,  // Aquí se agrega el campo concepto
                    'create_by' => $idusuario,
                ]);

                // 7. Lógica según tipo de movimiento
                if ($tipo_mov === 'apr') {
                    // 7a. Apertura: creo nuevo registro de apertura en tb_boveda_apertura_cierre
                    $database->insert('tb_boveda_apertura_cierre', [
                        'id_boveda' => $movId,
                        'saldo_inicial' => $saldoinicial,
                        'saldo_final' => $saldoinicial, // Se asigna saldo_final igual al saldo_inicial
                        'fecha_apertura' => $fecha,
                        'estado' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $idusuario,
                    ]);
                } else {
                    // 8. Ingreso/Egreso: actualizo saldo_final de la última apertura activa
                    $row = $database->getAllResults(
                        "SELECT ba.id_boveda, ba.saldo_final
                           FROM tb_boveda_apertura_cierre ba
                           JOIN bov_mov bm ON bm.id = ba.id_boveda
                          WHERE bm.id_agencia = ?
                            AND bm.tip_mov = 'apr'
                            AND ba.estado = 1
                          ORDER BY bm.fecha DESC
                          LIMIT 1",
                        [$agencia]
                    );
                    if (empty($row)) {
                        echo json_encode(["No hay apertura activa para esta agencia", 0]);
                        return;
                    }
                    $idApertura = $row[0]['id_boveda'];
                    $nuevoSaldo = $row[0]['saldo_final']
                        + ($tipo_mov === 'ingre' ? $saldoinicial : -$saldoinicial);
                    $database->update(
                        'tb_boveda_apertura_cierre',
                        [
                            'saldo_final' => $nuevoSaldo,
                            'updated_at' => date('Y-m-d H:i:s'),
                            'updated_by' => $idusuario
                        ],
                        'id_boveda = ?',
                        [$idApertura]
                    );
                }

                // 9. Decodificar detalle y calcular total
                $raw = json_decode($detalleJSON, true);
                $total = 0;
                foreach ($raw as $key => $qty) {
                    $q = intval($qty);
                    if ($q <= 0)
                        continue;
                    $den = (strpos($key, 'b') === 0)
                        ? floatval(substr($key, 1))
                        : floatval(substr($key, 1)) / 100;
                    $total += $den * $q;
                }
                $total = round($total, 2);

                // 10. Validar que coincida con el monto enviado
                if (abs($total - floatval($saldoinicial)) > 0.0001) {
                    throw new Exception(
                        "El total del detalle (GTQ $total) no coincide con el monto enviado (GTQ $saldoinicial)"
                    );
                }

                // 11. Inserto cada línea en bov_mov_detalle
                foreach ($raw as $key => $qty) {
                    $q = intval($qty);
                    if ($q <= 0)
                        continue;
                    $den = (strpos($key, 'b') === 0)
                        ? floatval(substr($key, 1))
                        : floatval(substr($key, 1)) / 100;

                    $row = $database->getAllResults(
                        "SELECT id
                           FROM `{$db_name_general}`.`denominaciones`
                          WHERE monto = ?",
                        [$den]
                    );
                    if (empty($row)) {
                        throw new Exception("Denominación no válida: $den");
                    }
                    $denId = $row[0]['id'];

                    $database->insert('bov_mov_detalle', [
                        'id_movimiento' => $movId,
                        'id_denominacion' => $denId,
                        'cantidad' => $q,
                        'create_by' => $idusuario,
                    ]);
                }

                // 12. Confirmar
                $database->commit();
                echo json_encode(["Movimiento guardado con éxito", 1]);
            } catch (Exception $e) {
                $database->rollback();
                echo json_encode([$e->getMessage(), 0]);
            } finally {
                $database->closeConnection();
            }
        }
        break;
    case 'get_saldos': {
            try {
                // Obtener el ID de la agencia
                $agenciaId = $_POST['agencia_id'];

                // Abrir la conexión a la base de datos
                $database->openConnection();

                // Obtener el ID del movimiento asociado a la agencia seleccionada desde bov_mov
                $bovedaQuery = "SELECT 
                                b.id AS id_movimiento,
                                b.id_agencia,
                                b.fecha,
                                b.monto,
                                b.detalle,
                                b.tip_mov,
                                b.concepto,
                                b.create_by,
                                b.update_by,
                                b.delete_by,
                                b.created_at,
                                b.updated_at,
                                bd.id_denominacion,
                                bd.cantidad,
                                bc.saldo_final
                            FROM 
                                bov_mov b
                            JOIN 
                                bov_mov_detalle bd ON b.id = bd.id_movimiento
                            JOIN 
                                tb_boveda_apertura_cierre bc ON bc.id_boveda = b.id
                            WHERE 
                                b.tip_mov = 'apr'
                                AND b.id_agencia = ?
                            ORDER BY 
                                b.tip_mov ASC
                            LIMIT 1";

                // Ejecutar la consulta para obtener los detalles del movimiento
                $bovedaData = $database->selectNom($bovedaQuery, [$agenciaId]);

                // Si no se encuentra el movimiento, retornar error
                // if (empty($bovedaData)) {
                //     echo json_encode(['status' => 0, 'message' => 'No se encontró movimiento para la agencia seleccionada.']);
                //     break;
                // }

                // Obtener el saldo final de la bóveda seleccionada
                $saldoFinal = $bovedaData[0]['saldo_final'];

                // Obtener las denominaciones de billetes y monedas para el movimiento
                $denominacionesFormatted = [];
                foreach ($bovedaData as $den) {
                    $denominacionesFormatted[] = [
                        'denominacion' => $den['id_denominacion'],  // Asumiendo que id_denominacion está correctamente relacionado con denominación
                        'cantidad' => $den['cantidad'],
                        'subtotal' => $den['cantidad'] * $den['id_denominacion']  // Asegúrate de tener la denominación de billetes
                    ];
                }

                // Enviar respuesta al frontend
                echo json_encode([
                    'status' => 1,
                    'saldo_final' => $saldoFinal,
                    'cantidad_billetes' => count($denominacionesFormatted),
                    'denominaciones' => $denominacionesFormatted
                ]);
            } catch (Exception $e) {
                // Manejo de errores
                echo json_encode(['status' => 0, 'message' => 'Error al obtener los datos: ' . $e->getMessage()]);
            } finally {
                // Cerrar conexión
                $database->closeConnection();
            }
        }
        break;

    case 'create_boveda':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        // Log::info("post datos", $_POST);
        list($csrftoken, $nombreBoveda) = $_POST["inputs"];
        list($agencia, $nomenclatura) = $_POST["selects"];

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

            if ($nombreBoveda == "") {
                $showmensaje = true;
                throw new Exception("Debe ingresar un nombre para la bóveda");
            }
            if ($agencia == "0" || $agencia == "") {
                $showmensaje = true;
                throw new Exception("Debe seleccionar una agencia");
            }
            if ($nomenclatura == "0" || $nomenclatura == "") {
                $showmensaje = true;
                throw new Exception("Debe seleccionar una cuenta contable");
            }

            $database->openConnection();
            $database->beginTransaction();

            $bov_bovedas = array(
                'nombre' => $nombreBoveda,
                'id_agencia' => $agencia,
                'id_nomenclatura' => $nomenclatura,
                'estado' => 1,
                'created_at' => $hoy2,
                'created_by' => $idusuario
            );

            $database->insert('bov_bovedas', $bov_bovedas);

            $database->commit();
            // $database->rollback();
            $mensaje = "Bóveda registrada correctamente";
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

        echo json_encode([$mensaje, $status]);
        break;
    case 'update_boveda':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        // Log::info("post datos", $_POST);
        list($csrftoken, $nombreBoveda) = $_POST["inputs"];
        list($agencia, $nomenclatura) = $_POST["selects"];
        list($encryptedID) = $_POST["archivo"];

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

        $decryptedID = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {

            if ($nombreBoveda == "") {
                $showmensaje = true;
                throw new Exception("Debe ingresar un nombre para la bóveda");
            }
            if ($agencia == "0" || $agencia == "") {
                $showmensaje = true;
                throw new Exception("Debe seleccionar una agencia");
            }
            if ($nomenclatura == "0" || $nomenclatura == "") {
                $showmensaje = true;
                throw new Exception("Debe seleccionar una cuenta contable");
            }

            $database->openConnection();

            $verificacion = $database->selectColumns('bov_bovedas', ['id'], 'id=? AND estado=1', [$decryptedID]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se puede actualizar la bóveda, ya que no existe o ya fue eliminada");
            }

            $database->beginTransaction();

            $bov_bovedas = array(
                'nombre' => $nombreBoveda,
                'id_agencia' => $agencia,
                'id_nomenclatura' => $nomenclatura,
                // 'estado' => 1,
                'updated_at' => $hoy2,
                'updated_by' => $idusuario
            );

            $database->update('bov_bovedas', $bov_bovedas, "id=?", [$decryptedID]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Bóveda actualizada correctamente";
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

        echo json_encode([$mensaje, $status]);
        break;
    case 'delete_boveda':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken) = $_POST["inputs"];
        list($encryptedID) = $_POST["archivo"];

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

        $decryptedID = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {

            $database->openConnection();

            $verificacion = $database->selectColumns('bov_bovedas', ['id'], 'id=? AND estado="1"', [$decryptedID]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se puede eliminar la bóveda, ya que no existe o ya fue eliminada");
            }

            $database->beginTransaction();

            $bov_bovedas = array(
                'estado' => 0,
                'deleted_at' => $hoy2,
                'deleted_by' => $idusuario
            );

            $database->update('bov_bovedas', $bov_bovedas, "id=?", [$decryptedID]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Bóveda eliminada correctamente";
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

        echo json_encode([$mensaje, $status]);
        break;

    case 'create_bov_movimiento':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        /** 
         * obtiene(['<?= $csrf->getTokenName()', 'fec_apertura', 'num_documento', 'concepto', 'monto_total', 'banco_numdoc', 'banco_fecha', 'banco_beneficiario_cheque'], 
         * ['tipo_movimiento', 'forma_pago', 'cuentabanco','negociable], 
         * [], 'create_bov_movimiento', '<?= $idBoveda', ['<?= htmlspecialchars($secureID->encrypt($idBoveda))', detalle], 'null', '¿Confirma guardar el movimiento?');
         * 
         */

        // list($csrftoken, $fecha, $num_documento, $concepto, $montoTotal) = $_POST["inputs"];
        // list($tipo_movimiento, $forma_pago, $cuentabanco) = $_POST["selects"];
        list($encryptedID, $detalleJSON) = $_POST["archivo"];

        // Log::info("archivo", $_POST["archivo"]);

        if (!($csrf->validateToken($_POST['inputs'][0] ?? '', false))) {
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
            $decryptedID = $secureID->decrypt($encryptedID);

            /**
             * VALIDACION DE CAMPOS
             */
            $data = [
                'fecha' => $_POST['inputs'][1] ?? null,
                'num_documento' => $_POST['inputs'][2] ?? null,
                'concepto' => $_POST['inputs'][3] ?? null,
                'monto_total' => $_POST['inputs'][4] ?? 0,
                'banco_numdoc' => $_POST['inputs'][5] ?? '',
                'banco_fecha' => $_POST['inputs'][6] ?? '',
                'banco_beneficiario_cheque' => $_POST['inputs'][7] ?? '',
                'tipo_movimiento' => $_POST['selects'][0] ?? null,
                'forma_pago' => $_POST['selects'][1] ?? null,
                'cuentabanco' => $_POST['selects'][2] ?? null,
                'banco_negociable' => $_POST['selects'][3] ?? null,
            ];

            // Log::info("Crear cuenta por cobrar - datos para validacion", $data);

            $rules = [
                'fecha' => 'required|date',
                'num_documento' => 'required|string|max_length:50',
                'concepto' => 'required|string|max_length:255',
                'monto_total' => 'required|numeric|min:0.01',
                'tipo_movimiento' => 'required',
                'forma_pago' => 'required',
            ];

            if ($data['forma_pago'] == 'banco' && $data['tipo_movimiento'] == 'entrada') {
                $rules['cuentabanco'] = 'required';
                $rules['banco_numdoc'] = 'required|string|max_length:50';
                $rules['banco_fecha'] = 'required|date';
                $rules['banco_beneficiario_cheque'] = 'required|string|max_length:100';
                $rules['banco_negociable'] = 'required';
            }
            if ($data['forma_pago'] == 'banco' && $data['tipo_movimiento'] == 'salida') {
                $rules['cuentabanco'] = 'required';
                $rules['banco_numdoc'] = 'required|string|max_length:50';
                $rules['banco_fecha'] = 'required|date';
            }

            $messages = [
                'monto_total.min' => 'No ha ingresado montos validos, agregue cantidades mayores a 0',
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            // $validar = validacionescampos([
            //     [$fecha, "", 'Ingrese una fecha para el movimiento', 1],
            //     [$num_documento, "", 'Ingrese el numero de documento', 1],
            //     [$concepto, "", 'Ingrese el concepto', 1],
            //     [$fecha, date('Y-m-d'), 'La fecha del movimiento no puede ser mayor a la fecha actual', 3],
            //     [$montoTotal, 0, 'Ingrese montos validos', 6],
            // ]);

            // if ($validar[2]) {
            //     $showmensaje = true;
            //     throw new Exception($validar[0]);
            // }

            $database->openConnection();

            $datosBoveda = $database->selectColumns('bov_bovedas', ['id_nomenclatura'], 'id=? AND estado="1"', [$decryptedID]);
            if (empty($datosBoveda)) {
                $showmensaje = true;
                throw new Exception("La bóveda no existe o ya fue eliminada");
            }

            $datosAgencia = $database->selectColumns("tb_agencia", ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
            if (empty($datosAgencia) || $datosAgencia[0]['id_nomenclatura_caja'] == null) {
                $showmensaje = true;
                throw new Exception("No se encontro la cuenta de caja para la agencia");
            }

            $idCuentaCajaBanco = $datosAgencia[0]['id_nomenclatura_caja'];

            if ($data['forma_pago'] == "banco") {
                // if ($cuentabanco == "0" || $cuentabanco == "") {
                //     $showmensaje = true;
                //     throw new Exception("Debe seleccionar una cuenta bancaria");
                // }

                $datosBanco = $database->selectColumns("ctb_bancos", ['id_nomenclatura'], 'id=?', [$data['cuentabanco']]);
                if (empty($datosBanco) || $datosBanco[0]['id_nomenclatura'] == null) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el banco seleccionado");
                }
                $idCuentaCajaBanco = $datosBanco[0]['id_nomenclatura'];
            }
            $database->beginTransaction();

            $bov_movimientos = array(
                'id_boveda' => $decryptedID,
                'tipo' => $data['tipo_movimiento'],
                'monto' => $data['monto_total'],
                'fecha' => $data['fecha'],
                'concepto' => $data['concepto'],
                'numdoc' => $data['num_documento'],
                'forma' => $data['forma_pago'],
                'id_cuentabanco' => ($data['forma_pago'] == "banco") ? $data['cuentabanco'] : null,
                'banco_numdoc' => ($data['forma_pago'] == "banco") ? $data['banco_numdoc'] : null,
                'banco_fecha' => ($data['forma_pago'] == "banco") ? $data['banco_fecha'] : null,
                'estado' => '1',
                'created_at' => $hoy2,
                'created_by' => $idusuario,
            );

            $idmovimiento = $database->insert('bov_movimientos', $bov_movimientos);

            foreach ($detalleJSON as $key => $qty) {
                $q = intval($qty);
                $idDenominacion = intval(str_replace("deno_", "", $key));
                if ($q <= 0)
                    continue;

                if ($data['tipo_movimiento'] == "salida") {
                    //Validar que haya saldo suficiente
                    $verificacion2 = $database->getAllResults(
                        "SELECT 
                            SUM(CASE WHEN tipo = 'entrada' OR tipo = 'inicial' THEN cantidad ELSE -cantidad END) AS total 
                         FROM bov_detalles bd
                         INNER JOIN bov_movimientos bm ON bd.id_movimiento = bm.id AND bm.estado = '1'
                         WHERE bd.id_denominacion = ? AND bm.id_boveda = ?",
                        [$idDenominacion, $decryptedID]
                    );
                    if (empty($verificacion2) || $verificacion2[0]['total'] === null || $verificacion2[0]['total'] < $q) {
                        $datosDenominacion = $database->selectColumns("tb_denominaciones", ['monto', 'tipo'], 'id=?', [$idDenominacion]);
                        $showmensaje = true;
                        throw new Exception("No hay saldo suficiente para la denominación " . $datosDenominacion[0]['monto'] . " (" . ($datosDenominacion[0]['tipo'] == '1' ? 'BILLETE' : 'MONEDA') . ")");
                    }
                }

                $bov_detalles = array(
                    'id_movimiento' => $idmovimiento,
                    'id_denominacion' => $idDenominacion,
                    'cantidad' => $q
                );
                $database->insert('bov_detalles', $bov_detalles);
            }

            if ($data['tipo_movimiento'] == 'inicial') {
                // No hacer nada en contabilidad para movimientos iniciales
            } else {
                // $numpartida = getnumcompdo($idusuario, $database);
                $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fechaPago);

                /**
                 * TRANSACCIONES EN CONTABILIDAD
                 */
                $disabledctb = (intval($_ENV['BOV_DISABLE_CTB'] ?? 0) === 1);
                $estado_ctb = $disabledctb ? 0 : 1;

                $ctb_diario = [
                    'numcom' => $numpartida,
                    'id_ctb_tipopoliza' => 6,
                    'id_tb_moneda' => 1,
                    'numdoc' => ($data['forma_pago'] == "banco") ? $data['banco_numdoc'] : $data['num_documento'],
                    'glosa' => $data['concepto'],
                    'fecdoc' => ($data['forma_pago'] == "banco") ? $data['banco_fecha'] : $data['fecha'],
                    'feccnt' => $data['fecha'],
                    'cod_aux' => 'BOV_' . $idmovimiento,
                    'id_tb_usu' => $idusuario,
                    'karely' => 'BOV_' . $idmovimiento,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => $estado_ctb,
                    'editable' => 0,
                    'created_by' => $idusuario,
                ];

                $idDiario = $database->insert('ctb_diario', $ctb_diario);

                $ctb_mov = [
                    'id_ctb_diario' => $idDiario,
                    'numcom' => '',
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $datosBoveda[0]['id_nomenclatura'],
                    'debe' => ($data['tipo_movimiento'] == "salida") ? 0 : $data['monto_total'],
                    'haber' => ($data['tipo_movimiento'] == "entrada") ? 0 : $data['monto_total'],
                ];

                $database->insert('ctb_mov', $ctb_mov);

                $ctb_mov = [
                    'id_ctb_diario' => $idDiario,
                    'numcom' => '',
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $idCuentaCajaBanco,
                    'debe' => ($data['tipo_movimiento'] == "entrada") ? 0 : $data['monto_total'],
                    'haber' => ($data['tipo_movimiento'] == "salida") ? 0 : $data['monto_total'],
                ];

                $database->insert('ctb_mov', $ctb_mov);

                if ($data['forma_pago'] == "banco" && $data['tipo_movimiento'] == "entrada") {
                    $ctb_chq = [
                        'id_ctb_diario' => $idDiario,
                        'id_cuenta_banco' => $data['cuentabanco'],
                        'numchq' => $data['banco_numdoc'],
                        'nomchq' => $data['banco_beneficiario_cheque'],
                        'monchq' => $data['monto_total'],
                        'modocheque' => $data['banco_negociable'],
                        'emitido' => '0',
                    ];
                    $database->insert('ctb_chq', $ctb_chq);
                }
            }


            $database->commit();
            // $database->rollback();
            $mensaje = "Movimiento registrado correctamente";
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

        echo json_encode([$mensaje, $status, 'id_movimiento' => $idmovimiento ?? null]);
        break;
    case 'rechazarMovimiento':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        list($csrftoken) = $_POST["inputs"];
        list($decryptedID) = $_POST["archivo"];

        // Log::info("archivo", $_POST["archivo"]);

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
        // $decryptedID = $secureID->decrypt($encryptedID);
        $showmensaje = false;
        try {

            $database->openConnection();

            $verificacion = $database->selectColumns('tb_movimientos_caja', ['id'], 'id=? AND estado=1', [$decryptedID]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se puede rechazar el movimiento, ya que no existe o ya fue cambiado de estado");
            }

            $database->beginTransaction();

            $data = [
                "estado" => 0,
                "updated_at" => $hoy2,
                "updated_by" => $idusuario
            ];
            $resultado = $database->update("tb_movimientos_caja", $data, "id = ?", [$decryptedID]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Movimiento rechazado correctamente";
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

        echo json_encode([$mensaje, $status]);
        break;
    case 'getDataMovimientoCaja':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        // list($csrftoken) = $_POST["inputs"];
        list($idMovimiento) = $_POST["archivo"];

        $showmensaje = false;
        try {

            $database->openConnection();

            $datosMovimiento = $database->selectColumns('tb_movimientos_caja', ['id', 'detalle', 'total', 'tipo'], 'id=? AND estado=1', [$idMovimiento]);
            if (empty($datosMovimiento)) {
                $showmensaje = true;
                throw new Exception("No se puede obtener el movimiento, ya que no existe o ya fue cambiado de estado");
            }

            if ($datosMovimiento[0]['detalle'] == 1) {
                $detalles = $database->getAllResults(
                    "SELECT mov.id,den.id as id_denominacion,mov.cantidad,den.monto,den.tipo, 'Q' as simbolo
                        FROM tb_denominaciones den 
                        LEFT JOIN detalle_movimiento mov ON den.id=mov.id_denominacion AND mov.id_movimiento=?
                        WHERE den.id_moneda=1 ORDER BY den.tipo, den.monto DESC;
                    ",
                    [$idMovimiento]
                );
            }

            $mensaje = "Movimiento obtenido correctamente";
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

        echo json_encode([
            $mensaje,
            $status,
            'dataMovimiento' => $datosMovimiento[0] ?? [],
            'dataDetalle' => $detalles ?? [],
            "reprint" => 0,
            "timer" => 10
        ]);
        break;
    case 'aprobarMovimiento':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        /**
         * ['<?= $csrf->getTokenName() ', 'idMovimientoConDesglose'], ['selectBoveda'], [], 'aprobarMovimiento', '0', [debitoBoveda, detalle]
         */

        list($csrftoken, $idMovimiento, $montoTotal) = $_POST["inputs"];
        list($desgloseTipo) = $_POST["archivo"];

        if ($desgloseTipo == 1) {
            $debitoBoveda = $_POST["archivo"][1];
            $detalle = $_POST["archivo"][2];
            $selectBoveda = $_POST["selects"][0];
        } else {
            $debitoBoveda = 0;
            $detalle = [];
            $selectBoveda = 0;
        }

        // Log::info("archivo", $_POST["archivo"]);

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

            $database->openConnection();

            $verificacion = $database->selectColumns('tb_movimientos_caja', ['tipo', 'id_caja', 'cod_referencia'], 'id=? AND estado=1', [$idMovimiento]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se puede aprobar el movimiento, ya que no existe o ya fue cambiado de estado");
            }

            $database->beginTransaction();

            $data = [
                "total" => $montoTotal,
                "estado" => 2,
                "updated_at" => $hoy2,
                "updated_by" => $idusuario
            ];
            $database->update("tb_movimientos_caja", $data, 'id=?', [$idMovimiento]);

            if ($desgloseTipo == 1) {
                /**
                 * VERIFICAR SI TIENE QUE AFECTAR BOVEDA
                 */
                if ($debitoBoveda == "1") {
                    if ($selectBoveda == "0" || $selectBoveda == "") {
                        $showmensaje = true;
                        throw new Exception("Debe seleccionar una bóveda para " . ($verificacion[0]['tipo'] == 1 ? 'débitar' : 'acreditar') . " el efectivo");
                    }

                    $datosBoveda = $database->selectColumns('bov_bovedas', ['id_nomenclatura'], 'id=? AND estado="1"', [$selectBoveda]);
                    if (empty($datosBoveda)) {
                        $showmensaje = true;
                        throw new Exception("La bóveda no existe o ya fue eliminada");
                    }

                    $datosAgencia = $database->getAllResults(
                        "SELECT ag.id_nomenclatura_caja, usu.nombre,usu.apellido
                            FROM tb_caja_apertura_cierre caj
                            INNER JOIN tb_usuario usu ON usu.id_usu=caj.id_usuario
                            INNER JOIN tb_agencia ag ON ag.id_agencia=usu.id_agencia
                            WHERE caj.id=?;",
                        [$verificacion[0]['id_caja']]
                    );
                    if (empty($datosAgencia) || $datosAgencia[0]['id_nomenclatura_caja'] == null) {
                        $showmensaje = true;
                        throw new Exception("No se encontro la cuenta de caja para la agencia");
                    }

                    $idCuentaCajaBanco = $datosAgencia[0]['id_nomenclatura_caja'];

                    // if ($forma_pago == "banco") {
                    //     if ($cuentabanco == "0" || $cuentabanco == "") {
                    //         $showmensaje = true;
                    //         throw new Exception("Debe seleccionar una cuenta bancaria");
                    //     }

                    //     $datosBanco = $database->selectColumns("ctb_bancos", ['id_nomenclatura'], 'id=?', [$cuentabanco]);
                    //     if (empty($datosBanco) || $datosBanco[0]['id_nomenclatura'] == null) {
                    //         $showmensaje = true;
                    //         throw new Exception("No se encontro la cuenta contable para el banco seleccionado");
                    //     }
                    //     $idCuentaCajaBanco = $datosBanco[0]['id_nomenclatura'];
                    // }


                    $bov_movimientos = array(
                        'id_boveda' => $selectBoveda,
                        'tipo' => ($verificacion[0]['tipo'] == 1) ? "salida" : "entrada",
                        'monto' => $montoTotal,
                        'fecha' => $hoy,
                        'concepto' => "Movimiento por caja #" . $idMovimiento . " - " . $datosAgencia[0]['nombre'] . " " . $datosAgencia[0]['apellido'],
                        'numdoc' => $verificacion[0]['cod_referencia'] ?? '',
                        'forma' => 'efectivo',
                        'aux' => 'CAJ_' . $idMovimiento,
                        'estado' => '1',
                        'created_at' => $hoy2,
                        'created_by' => $idusuario,
                    );

                    $idmovimientoBoveda = $database->insert('bov_movimientos', $bov_movimientos);

                    /**
                     * MOVIMIENTOS EN CONTABILIDAD
                     */

                    // $numpartida = getnumcompdo($idusuario, $database);
                    $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $hoy);
                    $disabledctb = (intval($_ENV['BOV_DISABLE_CTB'] ?? 0) === 1);
                    $estado_ctb = $disabledctb ? 0 : 1;
                    $ctb_diario = [
                        'numcom' => $numpartida,
                        'id_ctb_tipopoliza' => 6,
                        'id_tb_moneda' => 1,
                        'numdoc' => $verificacion[0]['cod_referencia'] ?? '',
                        'glosa' => "Movimiento por caja #" . $idMovimiento . " - " . $datosAgencia[0]['nombre'] . " " . $datosAgencia[0]['apellido'],
                        'fecdoc' => $hoy,
                        'feccnt' => $hoy,
                        'cod_aux' => 'BOV_' . $idmovimientoBoveda,
                        'id_tb_usu' => $idusuario,
                        'karely' => 'BOV_' . $idmovimientoBoveda,
                        'id_agencia' => $idagencia,
                        'fecmod' => $hoy2,
                        'estado' => $estado_ctb,
                        'editable' => 0,
                        'created_by' => $idusuario,
                    ];

                    $idDiario = $database->insert('ctb_diario', $ctb_diario);

                    $ctb_mov = [
                        'id_ctb_diario' => $idDiario,
                        'numcom' => '',
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $datosBoveda[0]['id_nomenclatura'],
                        'debe' => ($bov_movimientos['tipo'] == "salida") ? 0 : $montoTotal,
                        'haber' => ($bov_movimientos['tipo'] == "entrada") ? 0 : $montoTotal,
                    ];

                    $database->insert('ctb_mov', $ctb_mov);

                    $ctb_mov = [
                        'id_ctb_diario' => $idDiario,
                        'numcom' => '',
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $idCuentaCajaBanco,
                        'debe' => ($bov_movimientos['tipo'] == "entrada") ? 0 : $montoTotal,
                        'haber' => ($bov_movimientos['tipo'] == "salida") ? 0 : $montoTotal,
                    ];

                    $database->insert('ctb_mov', $ctb_mov);
                }
                //Insertar detalle
                // Log::info("detalle", $detalle);
                $database->delete("detalle_movimiento", "id_movimiento=?", [$idMovimiento]);
                foreach ($detalle as $key => $qty) {
                    $q = intval($qty);
                    $idDenominacion = intval(str_replace("deno_", "", $key));
                    if ($q <= 0)
                        continue;

                    /**
                     * SI ES SALIDA, VALIDAR QUE HAYA SALDO SUFICIENTE en la boveda si aplica
                     */

                    if ($debitoBoveda == "1") {
                        if ($verificacion[0]['tipo'] == 1) {
                            //Validar que haya saldo suficiente
                            $verificacion2 = $database->getAllResults(
                                "SELECT SUM(CASE WHEN tipo = 'entrada' OR tipo = 'inicial' THEN cantidad ELSE -cantidad END) AS total 
                                    FROM bov_detalles bd
                                    INNER JOIN bov_movimientos bm ON bd.id_movimiento = bm.id AND bm.estado = '1'
                                    WHERE bd.id_denominacion = ? AND bm.id_boveda = ?",
                                [$idDenominacion, $selectBoveda]
                            );
                            if (empty($verificacion2) || $verificacion2[0]['total'] === null || $verificacion2[0]['total'] < $q) {
                                $datosDenominacion = $database->selectColumns("tb_denominaciones", ['monto', 'tipo'], 'id=?', [$idDenominacion]);
                                $showmensaje = true;
                                throw new Exception("No hay saldo suficiente para la denominación " . $datosDenominacion[0]['monto'] . " (" . ($datosDenominacion[0]['tipo'] == '1' ? 'BILLETE' : 'MONEDA') . ")");
                            }
                        }

                        $bov_detalles = array(
                            'id_movimiento' => $idmovimientoBoveda,
                            'id_denominacion' => $idDenominacion,
                            'cantidad' => $q
                        );
                        $database->insert('bov_detalles', $bov_detalles);
                    }

                    $detalle_movimiento = array(
                        'id_movimiento' => $idMovimiento,
                        'id_denominacion' => $idDenominacion,
                        'cantidad' => $q
                    );
                    $database->insert('detalle_movimiento', $detalle_movimiento);
                }
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Movimiento aprobado correctamente";
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

        echo json_encode([$mensaje, $status]);
        break;
}



function validar_campos($validaciones)
{
    for ($i = 0; $i < count($validaciones); $i++) {
        if ($validaciones[$i][0] == $validaciones[$i][1]) {
            return [$validaciones[$i][2], '0', true];
            $i = count($validaciones) + 1;
        }
    }
    return ["", '0', false];
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

function validar_expresion_regular($cadena, $expresion_regular)
{
    if (preg_match($expresion_regular, $cadena)) {
        return false;
    } else {
        return true;
    }
}

//FILTRO DE DATOS BY BENEQ
function filtro($array, $columna, $p1, $p2)
{
    return (array_keys(array_filter(array_column($array, $columna), function ($var) use ($p1, $p2) {
        return ($var >= $p1 && $var <= $p2);
    })));
}
