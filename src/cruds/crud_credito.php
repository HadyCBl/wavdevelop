<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
include __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

use Micro\Helpers\Log;
use PhpOffice\PhpSpreadsheet\Reader\Xml\Style\NumberFormat;
use Micro\Helpers\Beneq;

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../funcphp/fun_ppg.php';
/* include '../funcphp/func_gen.php'; */
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");
$contgen = 0;
$esfinal = 0;
$condi = $_POST["condi"];
switch ($condi) {
    case 'soligrupal':
        $inputs = $_POST["inputs"];
        $montos = $inputs[0];
        $detalle = $inputs[1];
        $archivo = $_POST["archivo"];

        //VALIDAR EL ANALISTA
        if ($detalle[1] == "0") {
            echo json_encode(["Seleccionar Analista", '0']);
            return;
        }
        //VERIFICAR NEGATIVOS
        if (count(array_filter(array_column($montos, 1), function ($var) {
            return ($var < 0);
        })) > 0) {
            echo json_encode(["Monto negativo detectado, favor verificar", '0']);
            return;
        }
        //SI SE INGRESARON MONTOS A SOLICITAR
        if (array_sum(array_column($montos, 1)) <= 0) {
            echo json_encode(["Monto total Solicitado invalido, favor verificar", '0']);
            return;
        }

        $i = 0;
        $j = 0;
        $data[] = [];
        while ($i < count($montos)) {
            $filas = $montos[$i];
            //SE VERIFICAN LOS CAMPOS QUE SON MAYORES A 0 PARA TOMARLOS EN CUENTA
            if ($filas[1] != "" || $filas[1] > 0) {
                //VERIFICAR SI SE SELECCIONARON SECTORES Y ACTIVIDADES ECONOMICAS PARA CADA CREDITO
                $validacion = validarcampo([$filas[3], $filas[4]], "0");
                if ($validacion != "1") {
                    echo json_encode(["Seleccionar Sector Y Actividad economica!", '0']);
                    return;
                }
                $data[$j] = $filas;
                $j++;
            }
            $i++;
        }
        /* filas = getinputsval(['ccodcli' + (rows), 'monsol' + (rows),  'descre' + (rows), 'sectorecono' + (rows), 'actecono' + (rows)]);
        datadetal = getinputsval(['nciclo', 'fecsol', 'codanal']); */
        //INSERCION EN LA BD

        $conexion->autocommit(false);
        try {
            $i = 0;
            while ($i < count($data)) {
                $date = new DateTime();
                $date = $date->format('YmdHisv');

                $gencodigo = getcrecodcta($archivo[0], "02", $conexion);
                if ($gencodigo[0] == 0) {
                    echo json_encode([$gencodigo[1] . ": " . $i, '0']);
                    return;
                }
                $codgen = $gencodigo[1];
                $res = $conexion->query("INSERT INTO `cremcre_meta`(`CCODCTA`,`CodCli`,`CODAgencia`,`CodAnal`,`Cestado`,`DfecSol`,`ActoEcono`,`Cdescre`,`CSecEco`,`CCodGrupo`,`MontoSol`,`TipoEnti`,`NCiclo`,`fecha_operacion`) 
                VALUES('$codgen','" . $data[$i][0] . "','$archivo[2]','$detalle[1]','A','$hoy2','" . $data[$i][4] . "','" . $data[$i][2] . "','" . $data[$i][3] . "','$archivo[1]'," . $data[$i][1] . ",'GRUP',$detalle[0],'$hoy')");

                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode([$aux . ": " . $i, '0']);
                    $conexion->rollback();
                    return;
                }
                if (!$res) {
                    echo json_encode(['Fallo al crear la solicitud', '0']);
                    $conexion->rollback();
                    return;
                }

                $i++;
            }

            $conexion->query("UPDATE `tb_grupo` SET estadoGrupo='C',close_by='$detalle[1]',close_at='$hoy2' WHERE id_grupos=$archivo[1]");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode([$aux . ": " . $i, '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Fallo al Cerrar el grupo', '0']);
                $conexion->rollback();
                return;
            }
            if ($conexion->commit()) {
                echo json_encode(['Datos ingresados correctamente', '1']);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
                $conexion->rollback();
                return;
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'analgrupal':
        $inputs = $_POST["inputs"];
        $montos = $inputs[0];
        $detalle = $inputs[1];
        $archivo = $_POST["archivo"];

        //VALIDAR PRODUCTO
        if ($detalle[0] == "") {
            echo json_encode(["Seleccione un Producto ó Linea de Crédito", '0']);
            return;
        }
        //TIPO DE CREDITO
        if ($detalle[2] == "0") {
            echo json_encode(["Seleccione un tipo de Crédito", '0']);
            return;
        }
        //TIPO DE PERIODO
        if ($detalle[3] == "0") {
            echo json_encode(["Seleccione un tipo de Periodo", '0']);
            return;
        }
        //NUMERO DE CUOTAS
        if ($detalle[5] == "" || $detalle[5] < 1) {
            echo json_encode(["Numero de cuotas Inválido", '0']);
            return;
        }
        //FECHA DE DESEMBOLSO Y PRIMERA CUOTA
        if ($detalle[4] == "" || $detalle[6] == "") {
            echo json_encode(["Fecha de primera cuota o Desembolso invalida", '0']);
            return;
        }
        if ($detalle[4] < $detalle[6]) {
            echo json_encode(["La fecha de la primera cuota no debe ser menor a la fecha de desembolso", '0']);
            return;
        }
        //VERIFICAR MONTOS VACIOS
        if (count(array_filter(array_column($montos, 1), function ($var) {
            return ($var == "");
        })) > 0) {
            echo json_encode(["Monto invalido detectado, favor verificar", '0']);
            return;
        }
        //VERIFICAR NEGATIVOS
        if (count(array_filter(array_column($montos, 1), function ($var) {
            return ($var <= 0);
        })) > 0) {
            echo json_encode(["Monto negativo ó igual a 0 detectado, favor verificar", '0']);
            return;
        }
        //VERIFICAR MONTOS QUE NO SEAN MAYORES AL LIMITE
        $monmax = $detalle[1];
        if (count(array_filter(array_column($montos, 1), function ($var) use ($monmax) {
            return ($var > $monmax);
        })) > 0) {
            echo json_encode(["Monto invalido, Maximo permitido en la linea de credito: " . $monmax . ", favor verificar", '0']);
            return;
        }

        $conexion->autocommit(false);
        try {
            $i = 0;
            while ($i < count($montos)) {
                /* $ahorro =  ($montos[$i][1] * ($detalle[9] / 100)); */
                $conexion->query("UPDATE `cremcre_meta` SET  Cestado='D',MonSug='" . $montos[$i][1] . "',Dictamen='" . $detalle[7] . "',noPeriodo='" . $detalle[5] . "',DfecDsbls='" . $detalle[6] . "',DfecPago='" . $detalle[4] . "',`CtipCre`='" . $detalle[2] . "', NtipPerC='" . $detalle[3] . "',NIntApro='" . $detalle[8] . "',CCODPRD='" . $detalle[0] . "',`DFecAnal`='" . $hoy2 . "',`fecha_operacion`='" . $hoy . "' WHERE `CCODCTA`='" . $montos[$i][0] . "'");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode([$aux . ": " . $i, '0']);
                    return;
                }
                $i++;
            }
            if ($conexion->commit()) {
                echo json_encode(['Datos ingresados correctamente', '1']);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'aprobgrupal':
        $inputs = $_POST["inputs"];
        $conexion->autocommit(false);
        try {
            $i = 0;
            while ($i < count($inputs)) {
                $conexion->query("UPDATE `cremcre_meta` SET  Cestado='E',`DFecApr`='" . $hoy2 . "',`fecha_operacion`='" . $hoy . "' WHERE `CCODCTA`='" . $inputs[$i][0] . "'");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode([$aux . ": " . $i, '0']);
                    return;
                }
                $i++;
            }
            if ($conexion->commit()) {
                echo json_encode(['Créditos Aprobados correctamente', '1']);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'desemgrupal':
        // obtiene(['csrf_token'], [], [], 'desemgrupal', 0, [idgrup, ciclo, datosGenerales], 'null', 'Esta seguro de realizar el desembolso?');

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken) = $_POST['inputs'];

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

        list($idgrupo, $ciclo, $datosGenerales) = $_POST['archivo'];

        $tipdoc = ["T", "E", "C"];

        // Log::info("Desembolso grupal por usuario: $idusuario, datosGenerales: " . json_encode($datosGenerales) . ", idgrupo: $idgrupo, ciclo: $ciclo");

        $showmensaje = false;
        try {

            $database->openConnection();

            /**
             * COMPROBACION DE CIERRE DE CAJA DEL USUARIO QUIEN REALIZA EL DESEMBOLSO
             */
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                $showmensaje = true;
                throw new Exception($cierre_caja[1]);
            }

            /**
             * SI EL DESEMBOLSO POR CHEQUE, SE DEBE DE SELECCIONAR UN BANCO Y UNA CUENTA
             */
            $porcheque = 0;
            if ($datosGenerales['tipo_desembolso'] == 2) {
                if ($datosGenerales['bancoid'] == "0") {
                    $showmensaje = true;
                    throw new Exception("Seleccione un banco");
                }
                if ($datosGenerales['cuentaid'] == "0") {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta");
                }

                //IDNOMENCLATURA DE LA CUENTA DE BANCO
                $consulta = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$datosGenerales['cuentaid']]);
                if (empty($consulta)) {
                    $showmensaje = true;
                    throw new Exception("Fallo al encontrar el id de la cuenta bancaria");
                }
                $idnomentrega = $consulta[0]['id_nomenclatura'];

                if ($datosGenerales['tipo_cheque'] == '2' && $datosGenerales['cargoid'] == '0') {
                    $showmensaje = true;
                    throw new Exception("Seleccione un cargo");
                }
                $porcheque = 1;
            } else {
                /**
                 * DESEMBOLSO EN EFECTIVO
                 */
                $consulta = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
                if (empty($consulta)) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el desembolso real en efectivo");
                }
                $idnomentrega = $consulta[0]['id_nomenclatura_caja'];
            }

            if ($datosGenerales['tipo_cheque'] == '2' && $datosGenerales['conceptogrupal'] == '') {
                $showmensaje = true;
                throw new Exception("Ingrese un concepto para el desembolso grupal");
            }

            /**
             * CONSULTA DE DATOS GENERALES DEL GRUPO
             */
            $id_nomeclatura_capital = 1;
            $dataGrupo = $database->getAllResults(
                "SELECT cp.id_cuenta_capital,id_fondo,DFecDsbls, tg.NombreGrupo,cli.short_name,cm.CCODCTA
                    FROM cre_productos cp
                    INNER JOIN cremcre_meta cm ON cp.id=cm.CCODPRD 
                    INNER JOIN tb_grupo tg ON tg.id_grupos=cm.CCodGrupo
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cm.CodCli
                    WHERE cm.CCodGrupo = ? AND cm.NCiclo=? AND cm.Cestado='E'",
                [$idgrupo, $ciclo]
            );
            if (empty($dataGrupo)) {
                $showmensaje = true;
                throw new Exception("No se encontraron créditos para el grupo y ciclo seleccionado");
            }
            $id_nomeclatura_capital = $dataGrupo[0]['id_cuenta_capital'];
            $fuenteDeFondos = $dataGrupo[0]['id_fondo'];
            $fechaDesembolso = $dataGrupo[0]['DFecDsbls'];

            /**
             * COMPROBACION DE MES CONTABLE 
             */
            $cierre_mes = comprobar_cierrePDO($idusuario, $dataGrupo[0]['DFecDsbls'], $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }


            $idsdiario = [];
            $idsCredkar = [];

            /**
             * INICIO DE TRANSACCIONES EN LA BASE DE DATOS
             */
            $database->beginTransaction();

            if (empty($datosGenerales['accounts'])) {
                $showmensaje = true;
                throw new Exception("No se recibieron datos de desembolso");
            }

            foreach ($datosGenerales['accounts'] as $key => $account) {

                /**
                 * VERIFICAR SI LA CUENTA ESTA EN $dataGrupo
                 */
                if (!in_array($account['ccodcta'], array_column($dataGrupo, 'CCODCTA'))) {
                    $showmensaje = true;
                    throw new Exception("El crédito " . $account['ccodcta'] . " no pertenece al grupo o ciclo seleccionado, revise el estado de la cuenta");
                }

                if (empty($account['glosa'])) {
                    $showmensaje = true;
                    throw new Exception("Ingrese un concepto para el desembolso del credito: " . $account['ccodcta']);
                }

                $totalDescuentos = (!empty($account['descuentos'])) ? array_sum(array_column($account['descuentos'], 'monto')) : 0;
                $totalRefinanciamientoKp = (!empty($account['refinanciamiento'])) ? array_sum(array_column($account['refinanciamiento'], 'monto')) : 0;
                $totalRefinanciamientoInteres = (!empty($account['refinanciamiento'])) ? array_sum(array_column($account['refinanciamiento'], 'interes')) : 0;

                $totalAllDescuentos = $totalDescuentos + $totalRefinanciamientoKp + $totalRefinanciamientoInteres;

                if ($account['monapr'] < $totalAllDescuentos) {
                    $showmensaje = true;
                    throw new Exception("El total de descuentos y refinanciamientos no puede ser mayor al monto a desembolsar para el credito: " . $account['ccodcta']);
                }


                /**
                 * GUARDAR EL PLAN DE PAGO
                 */
                $idPro_gas = null;
                $tipoVinculacion = '0';
                $cuentaVinculacion = '0';
                if (!empty($account['vinculacion']) && in_array($account['vinculacion']['tipo'], [1, 2])) {
                    $idPro_gas = $account['vinculacion']['id'];
                    $tiposVinculos = array(
                        1 => 'ahorros',
                        2 => 'aportaciones'
                    );

                    if ($account['vinculacion']['cuenta'] == "0" || $account['vinculacion']['cuenta'] == "") {
                        $showmensaje = true;
                        throw new Exception("Seleccione una cuenta de " . $tiposVinculos[$account['vinculacion']['tipo']] . " para la vinculacion del credito: " . $account['ccodcta']);
                    }
                    $tipoVinculacion = $account['vinculacion']['tipo'];
                    $cuentaVinculacion = $account['vinculacion']['cuenta'];
                }
                saveCredppg($account['ccodcta'], $database, $db_name_general, $idusuario, $idagencia, idGastoModuloAdicional: $idPro_gas);

                /**
                 * ACTUALIZACION EN LA CREMCRE
                 */
                $cremcre_meta = array(
                    'Cestado' => 'F',
                    'NCapDes' => $account['monapr'],
                    'TipDocDes' => $tipdoc[$datosGenerales['tipo_desembolso']],
                    'fecha_operacion' => $hoy,
                    'id_pro_gas' => $idPro_gas ?? 0,
                    'moduloafecta' => $tipoVinculacion,
                    'cntAho' => $cuentaVinculacion
                );
                $database->update('cremcre_meta', $cremcre_meta, 'CCODCTA=?', [$account['ccodcta']]);

                /**
                 * REGISTRO EN LA CREDKAR
                 */
                $concepto = strtoupper($account['glosa']);
                $numdoc = ($datosGenerales['tipo_desembolso'] == 2) ? $account['numcheque'] : " ";
                $credkar = array(
                    'CCODCTA' => $account['ccodcta'],
                    'DFECPRO' => $fechaDesembolso,
                    'DFECSIS' => $hoy2,
                    'CNROCUO' => 1,
                    'NMONTO' => $account['monapr'],
                    'CNUMING' => $numdoc,
                    'CCONCEP' => $concepto,
                    'KP' => $account['monapr'] - $totalAllDescuentos,
                    'OTR' => $totalAllDescuentos,
                    'CCODOFI' => $idagencia,
                    'CCODINS' => "",
                    'CCODUSU' => $idusuario,
                    'CTIPPAG' => 'D',
                    'CESTADO' => '1',
                    'FormPago' => $datosGenerales['tipo_desembolso'],
                    'boletabanco' => ($datosGenerales['tipo_desembolso'] == 2) ? (($datosGenerales['tipo_cheque'] == 2) ? $datosGenerales['nocheqgrupal'] : $account['numcheque']) : "",
                    'CCODBANCO' => ($datosGenerales['tipo_desembolso'] == 2) ? $datosGenerales['bancoid'] : "",
                    'CBANCO' => ($datosGenerales['tipo_desembolso'] == 2) ? $datosGenerales['cuentaid'] : "",
                    'CMONEDA' => 'Q',
                    'DFECMOD' => $hoy
                );
                $id_credkar = $database->insert('CREDKAR', $credkar);

                //SI EL TIPO DE CHEQUE ES INDIVIDUAL, SE DEBE DE CREAR UNA PARTIDA POR CADA CREDITO
                /**
                 * Si el cheque es individual, se crea una partida en el diario (ctb_diario) y sus movimientos asociados (ctb_mov).
                 * También maneja la inserción de gastos y el monto a entregar.
                 * Si el desembolso es por cheque, inserta los detalles del cheque en las cuentas de cheques (ctb_chq).
                 * 
                 * @param array $detalle Detalles del crédito.
                 * @param array $data Datos del crédito.
                 * @param int $i Índice de los datos actuales del crédito.
                 * @param array $datosgrupo Datos del grupo.
                 * @param string $fuenteDeFondos Fuente de fondos.
                 * @param string $short_name Nombre corto del beneficiario.
                 * @param int $idusuario ID del usuario.
                 * @param object $database Objeto de conexión a la base de datos.
                 * @param string $numdoc Número de documento.
                 * @param string $fechaDesembolso Fecha de desembolso.
                 * @param string $hoy2 Fecha actual.
                 * @param int $id_nomeclatura_capital ID de la nomenclatura de capital.
                 * @param int $idnomentrega ID de la nomenclatura de entrega.
                 * @param array $idsdiario Array para almacenar los IDs de las entradas del diario.
                 */


                if ($datosGenerales['tipo_desembolso'] == 1 || ($datosGenerales['tipo_desembolso'] == 2 && $datosGenerales['tipo_cheque'] == 1)) {
                    $short_name = $dataGrupo[array_search($account['ccodcta'], array_column($dataGrupo, 'CCODCTA'))]['short_name'];
                    $glosa = "CRÉDITO GRUPAL: " . $account['ccodcta'] . " - GRUPO:" . $dataGrupo[0]['NombreGrupo'] . " - FONDO:" . $fuenteDeFondos . " - BENEFICIARIO:" . strtoupper($short_name);
                   // $numpartida = getnumcompdo($idusuario, $database);
                   $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fechaDesembolso);
                    $ctb_diario = array(
                        'numcom' => $numpartida,
                        'id_ctb_tipopoliza' => 1,
                        'id_tb_moneda' => 1,
                        'numdoc' => $numdoc,
                        'glosa' => $glosa,
                        'fecdoc' => $fechaDesembolso,
                        'feccnt' => $fechaDesembolso,
                        'cod_aux' => $account['ccodcta'],
                        'id_tb_usu' => $idusuario,
                        'karely' => 'CRE_' . $id_credkar,
                        'id_agencia' => $idagencia,
                        'fecmod' => $hoy2,
                        'estado' => 1
                    );
                    $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => $fuenteDeFondos,
                        'id_ctb_nomenclatura' => $id_nomeclatura_capital,
                        'debe' => $account['monapr'],
                        'haber' => 0
                    );
                    $database->insert('ctb_mov', $ctb_mov);


                    //INSERCION DE MONTO A ENTREGAR
                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => $fuenteDeFondos,
                        'id_ctb_nomenclatura' => $idnomentrega,
                        'debe' => 0,
                        'haber' => ($account['monapr'] - $totalAllDescuentos)
                    );
                    $database->insert('ctb_mov', $ctb_mov);

                    //---------INSERCION EN CUENTAS DE CHEQUES SI EL DESEMBOLSO ES POR CHEQUE
                    if ($datosGenerales['tipo_desembolso'] == 2) {
                        $ctb_chq = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_cuenta_banco' => $datosGenerales['cuentaid'],
                            'numchq' => $account['numcheque'],
                            'nomchq' => $short_name,
                            'monchq' => ($account['monapr'] - $totalAllDescuentos),
                            'emitido' => 0
                        );
                        $database->insert('ctb_chq', $ctb_chq);
                    }

                    $idsdiario[] = $id_ctb_diario;
                }

                /**
                 * REGISTRO DE LOS DESCUENTOS EN LA CREDKAR DETALLE
                 */

                if (!empty($account['descuentos'])) {
                    foreach ($account['descuentos'] as $descuento) {
                        if ($descuento['monto'] < 0) {
                            $showmensaje = true;
                            throw new Exception("Monto negativo en el descuento detectado, favor verificar");
                        }
                        if ($descuento['monto'] > 0) {
                            $credkar_detalle = array(
                                'id_credkar' => $id_credkar,
                                'id_concepto' => $descuento['id_gasto'],
                                'monto' => $descuento['monto'],
                                'tipo' => 'otro'
                            );
                            $database->insert('credkar_detalle', $credkar_detalle);

                            if ($datosGenerales['tipo_desembolso'] == 1 || ($datosGenerales['tipo_desembolso'] == 2 && $datosGenerales['tipo_cheque'] == 1)) {
                                $ctb_mov = array(
                                    'id_ctb_diario' => $id_ctb_diario,
                                    'id_fuente_fondo' => $fuenteDeFondos,
                                    'id_ctb_nomenclatura' => $descuento['id_nomenclatura'],
                                    'debe' => 0,
                                    'haber' => $descuento['monto']
                                );
                                $database->insert('ctb_mov', $ctb_mov);
                            }
                        }
                    }
                }

                /**
                 * REGISTRO DE LOS REFINANCIAMIENTOS EN LA CREDKAR DETALLE
                 */

                if (!empty($account['refinanciamiento'])) {
                    foreach ($account['refinanciamiento'] as $refinanciamiento) {
                        if ($refinanciamiento['monto'] < 0 || $refinanciamiento['interes'] < 0) {
                            $showmensaje = true;
                            throw new Exception("Monto negativo en el refinanciamiento detectado, favor verificar");
                        }

                        if ($refinanciamiento['idGasto'] == '0' || $refinanciamiento['idGasto'] == '') {
                            $showmensaje = true;
                            throw new Exception("Seleccione el tipo de descuento que se aplicara para el refinanciamiento, credito: " . $account['ccodcta']);
                        }

                        if ($refinanciamiento['monto'] > 0 || $refinanciamiento['interes'] > 0) {
                            $credkar_detalle = array(
                                'id_credkar' => $id_credkar,
                                'id_concepto' => $refinanciamiento['idGasto'],
                                'monto' => $refinanciamiento['monto'] + $refinanciamiento['interes'],
                                'tipo' => 'otro'
                            );
                            $database->insert('credkar_detalle', $credkar_detalle);

                            /**
                             * CANCELACION DEL CREDITO ANTERIOR
                             */
                            $query = 'SELECT NCapDes,IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG="P" AND CESTADO!="X"),0) pagadokp,
                                            pr.id_fondo,pr.id_cuenta_capital,pr.id_cuenta_interes,
                                            IFNULL((SELECT MAX(CNROCUO) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG="P" AND CESTADO!="X"),0) nocuota
                                            FROM cremcre_meta cm INNER JOIN cre_productos pr ON pr.id=cm.CCODPRD
                                            WHERE CCODCTA=?';

                            $data = $database->getAllResults($query, [$refinanciamiento['cuenta']]);

                            if (empty($data)) {
                                $showmensaje = true;
                                throw new Exception('No se encontró la cuenta a cancelar: ' . $refinanciamiento['cuenta']);
                            }

                            $fondoref = $data[0]['id_fondo'];
                            $ccntkpref = $data[0]['id_cuenta_capital'];
                            $ccntintref = $data[0]['id_cuenta_interes'];
                            $mondesref = $data[0]['NCapDes'];
                            $pagadoref = $data[0]['pagadokp'];
                            $nocuota = $data[0]['nocuota'];
                            $saldoref = round($mondesref - $pagadoref, 2);

                            if ($refinanciamiento['monto'] < $saldoref) {
                                $showmensaje = true;
                                throw new Exception('El monto ingresado (' . $refinanciamiento['monto'] . ') no cubre el saldo pendiente(' . $saldoref . '), verificar');
                            }

                            $credkar = array(
                                'CCODCTA' => $refinanciamiento['cuenta'],
                                'DFECPRO' => $fechaDesembolso,
                                'DFECSIS' => $hoy2,
                                'CNROCUO' => ($nocuota + 1),
                                'NMONTO' => $refinanciamiento['monto'] + $refinanciamiento['interes'],
                                'CNUMING' => 'CREF',
                                'CCONCEP' => "Cancelacion por refinanciamiento",
                                'KP' => $refinanciamiento['monto'],
                                'INTERES' => ($refinanciamiento['interes']),
                                'MORA' => 0,
                                'AHOPRG' => 0,
                                'OTR' => 0,
                                'CCODINS' => "1",
                                'CCODOFI' => "1",
                                'CCODUSU' => $idusuario,
                                'CTIPPAG' => "P",
                                'CMONEDA' => "Q",
                                'CBANCO' => "",
                                'FormPago' => "4", /* LA FORMA DE PAGO ES 4 POR QUE NO ES 1 EFECTIVO, 2 BANCOS NI 3 TRANSFERENCIA */
                                'CCODBANCO' => "C55",
                                'CESTADO' => "1",
                                'DFECMOD' =>  $fechaDesembolso,
                                'CTERMID' => "0",
                                'MANCOMUNAD' => "0"
                            );
                            $database->insert('CREDKAR', $credkar);

                            //ACTUALIZACION DE CUOTAS DEL PLAN DE PAGO
                            $database->executeQuery('CALL update_ppg_account(?);', [$refinanciamiento['cuenta']]);
                            //READECUACION DE LAS CUOTAS PENDIENTES EN EL PLAN DE PAGO
                            //UPDATE Cre_ppg SET ncapita=(ncapita-ncappag),nintere=(nintere-nintpag) WHERE ccodcta="0020010200000006" AND cestado='X';

                            $database->executeQuery("UPDATE Cre_ppg SET ncapita=(ncapita-ncappag),nintere=(nintere-nintpag) WHERE ccodcta=? AND cestado='X'", [$refinanciamiento['cuenta']]);

                            //ACTUALIZACION DE CUOTAS DEL PLAN DE PAGO PARA
                            $database->executeQuery('CALL update_ppg_account(?);', [$refinanciamiento['cuenta']]);


                            /**
                             * ASIENTOS CONTABLES DEL REFINANCIAMIENTO
                             */
                            if ($datosGenerales['tipo_desembolso'] == 1 || ($datosGenerales['tipo_desembolso'] == 2 && $datosGenerales['tipo_cheque'] == 1)) {
                                if ($refinanciamiento['monto'] > 0) {
                                    $ctb_mov = array(
                                        'id_ctb_diario' => $id_ctb_diario,
                                        'id_fuente_fondo' => $fuenteDeFondos,
                                        'id_ctb_nomenclatura' => $refinanciamiento['idNomenclatura'],
                                        'debe' => 0,
                                        'haber' => $refinanciamiento['monto']
                                    );
                                    $database->insert('ctb_mov', $ctb_mov);
                                }
                                if ($refinanciamiento['interes'] > 0) {
                                    $ctb_mov = array(
                                        'id_ctb_diario' => $id_ctb_diario,
                                        'id_fuente_fondo' => $fuenteDeFondos,
                                        'id_ctb_nomenclatura' => $ccntintref,
                                        'debe' => 0,
                                        'haber' => $refinanciamiento['interes']
                                    );
                                    $database->insert('ctb_mov', $ctb_mov);
                                }
                            }
                        }
                    }
                }
            }

            /**
             * SI EL CHEQUE ES GRUPAL, SE DEBE DE CREAR UNA SOLA PARTIDA EN EL DIARIO
             * CON LA SUMATORIA DE TODOS LOS CREDITOS
             */
            if ($datosGenerales['tipo_desembolso'] == 2 && $datosGenerales['tipo_cheque'] == 2) {
            //$numpartida = getnumcompdo($idusuario, $database);
            $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fechaDesembolso);
                $ctb_diario = array(
                    'numcom' => $numpartida,
                    'id_ctb_tipopoliza' => 1,
                    'id_tb_moneda' => 1,
                    'numdoc' => $datosGenerales['nocheqgrupal'],
                    'glosa' => $datosGenerales['conceptogrupal'],
                    'fecdoc' => $fechaDesembolso,
                    'feccnt' => $fechaDesembolso,
                    'cod_aux' => $datosGenerales['accounts'][0]['ccodcta'],
                    'id_tb_usu' => $idusuario,
                    'karely' => 'CRE_' . $id_credkar,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'id_agencia' => $idagencia
                );
                $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                //Monto apertura total
                $montototal = array_sum(array_column($datosGenerales['accounts'], 'monapr'));
                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $fuenteDeFondos,
                    'id_ctb_nomenclatura' => $id_nomeclatura_capital,
                    'debe' => $montototal,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $ctb_mov);

                /**
                 * SUMATORIA DE GASTOS Y REFINANCIAMIENTO POR NOMENCLATURA
                 */
                $data = $datosGenerales['accounts'];
                $resultado = [];
                foreach ($data as $fila) {
                    if (!empty($fila['descuentos'])) {
                        foreach ($fila['descuentos'] as $item) {
                            $id = $item['id_nomenclatura'];
                            $monto = $item['monto'];
                            if (!isset($resultado[$id])) {
                                $resultado[$id] = ["id" => $id, "monto" => 0];
                            }
                            $resultado[$id]["monto"] += $monto;
                        }
                    }
                    if (!empty($fila['refinanciamiento'])) {
                        foreach ($fila['refinanciamiento'] as $item) {
                            $id = $item['idNomenclatura'];
                            $monto = $item['monto'] + $item['interes'];
                            if (!isset($resultado[$id])) {
                                $resultado[$id] = ["id" => $id, "monto" => 0];
                            }
                            $resultado[$id]["monto"] += $monto;
                        }
                    }
                }

                // Log::info("Resultado de sumatoria de gastos y refinanciamientos por nomenclatura: " . json_encode($resultado));

                $matriz_final = array_values($resultado);

                // Log::info("Matriz final de sumatoria de gastos y refinanciamientos por nomenclatura: " . json_encode($matriz_final));

                // INSERCION EN CTB_MOV
                foreach ($matriz_final as $item) {
                    if ($item['monto'] <= 0) {
                        continue;
                    }
                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => $fuenteDeFondos,
                        'id_ctb_nomenclatura' => $item['id'],
                        'debe' => 0,
                        'haber' => $item['monto']
                    );
                    $database->insert('ctb_mov', $ctb_mov);
                }

                //MONTO A ENTREGAR
                $montoEntrega = $montototal - array_sum(array_column($matriz_final, 'monto'));
                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $fuenteDeFondos,
                    'id_ctb_nomenclatura' => $idnomentrega,
                    'debe' => 0,
                    'haber' => $montoEntrega
                );

                $database->insert('ctb_mov', $ctb_mov);

                //INSERCION DE CHEQUE
                $ctb_chq = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_cuenta_banco' => $datosGenerales['cuentaid'],
                    'numchq' => $datosGenerales['nocheqgrupal'],
                    'nomchq' => strtoupper($datosGenerales['cargoid']),
                    'monchq' => $montoEntrega,
                    'emitido' => 0
                );
                $database->insert('ctb_chq', $ctb_chq);

                $idsdiario[0] = $id_ctb_diario;
            }

            // $showmensaje = true;
            // throw new Exception("Prueba de error");
            $database->commit();
            // $database->rollback();

            $mensaje = "Créditos Desembolsados correctamente";
            $status = 1;
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
            $mensaje,
            $status,
            'idsDiario' => ($idsdiario ?? []),
            'porcheque' => ($porcheque ?? 0),
            'tipoCheque' => ($datosGenerales['tipo_desembolso'] == 2) ? $datosGenerales['tipo_cheque'] : 0,
        ]);
        break;
    case 'desemgrupalAnt': //anterior
        //generico([datainputs, datadetal], 0, 0, 'desemgrupal', 0, [idgrup, ciclo], 'crud_credito');
        //  datainputs getinputsval(['ccodcta' + (rows), 'glosa' + (rows), 'numcheque' + (rows), 'monapr' + (rows), 'mondesc' + (rows)]);
        //      gastos = getinputsval(['idg_' + (k) + '_' + (nocuenta), 'mon_' + (k) + '_' + (nocuenta), 'con_' + (k) + '_' + (nocuenta)]);

        //  getinputsval(['tipo_desembolso', 'bancoid', 'cuentaid','conceptogrupal','nocheqgrupal','montogrupal','tipo_cheque','cargoid']);
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $montos = $inputs[0];
        $detalle = $inputs[1];
        $archivo = $_POST["archivo"];
        // $datoscre = $archivo[5];

        $tipdoc = ["E", "C", "T"];

        //COMPRUEBA SI SE SELECCIONO BANCO
        if ($detalle[0] == 2 && $detalle[1] == "0") {
            echo json_encode(["Seleccione un Banco", '0']);
            return;
        }

        //COMPRUEBA SI SE SELECCIONO UNA CUENTA DE BANCO
        if ($detalle[0] == 2 && $detalle[2] == "0") {
            echo json_encode(["Seleccione una cuenta", '0']);
            return;
        }
        // $gastos =(array_key_exists(5,$montos))? $montos[5]:null;
        // unset($montos[5]);
        $i = 0;
        $data[] = [];
        while ($i < count($montos)) {
            $filas = $montos[$i];
            $gastos = (array_key_exists(5, $filas)) ? $filas[5] : null;
            $validacion = validarcampo([$filas[1]], "");
            if ($validacion != "1") {
                echo json_encode(["No se ingreso descripción al desembolso del credito: " . $filas[0], '0']);
                return;
            }
            //VALIDACION SI EL DESEMBOLSO ES POR CHEQUE
            if ($detalle[0] == 2) {
                // $validacion = validarcampo([$filas[2]], "");
                // if ($validacion != "1") {
                //     echo json_encode(["No se ingreso numero de cheque al desembolso del credito: " . $filas[0], '0']);
                //     return;
                // }
            }
            //COMPROBACION DE GASTOS
            $k = 0;
            while ($gastos != null && $k < count($gastos)) {
                if (count(array_filter(array_column($gastos, 1), function ($var) {
                    return ($var < 0);
                })) > 0) {
                    echo json_encode(["Monto negativo en el gasto detectado, favor verificar", '0']);
                    return;
                }
                $k++;
            }
            //validar monto - descuento
            if ($filas[3] < $filas[4]) {
                echo json_encode(["Monto a desembolsar menor al monto descontado, favor verificar", '0']);
                return;
            }
            //FIN COMPROBACION DE GASTOS
            $data[$i] = $filas;
            $i++;
        }


        // echo json_encode(["Manolo", '0', $matriz_final]);
        // return;

        $showmensaje = false;
        try {
            $database->openConnection();
            //COMPROBAR CIERRE DE CAJA
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                $showmensaje = true;
                throw new Exception($cierre_caja[1]);
            }


            //++++++++++++++++++++++++++++++++++
            $idnomentrega = 8; //DE MOMENTO SE PONE ESTATICO SI NO FUERA DESEMBOLSO POR CHEQUE
            //ID NOMENCLATURA DONDE IRA EL MONTO A ENTREGAR POR DESEMBOLSO EN EFECTIVO
            if ($detalle[0] == 1) {
                $consulta = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
                if (empty($consulta)) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el desembolso real en efectivo");
                }
                $idnomentrega = $consulta[0]['id_nomenclatura_caja'];
            }

            //COMPROBACION DE NUMEROS DE CHEQUES
            $porcheque = 0;
            if ($detalle[0] == 2) {
                // $unicos = array_unique(array_column($data, 2));
                // if (count(array_column($data, 2)) > count($unicos)) {
                //     echo json_encode(["Se repite el numero de cheque ingresado", "0"]);
                //     return;
                // }

                //IDNOMENCLATURA DE LA CUENTA DE BANCO
                $consulta = $database->selectColumns('ctb_bancos', ['id', 'id_nomenclatura'], 'id=?', [$detalle[2]]);
                if (empty($consulta)) {
                    $showmensaje = true;
                    throw new Exception("Fallo al encontrar el id de la cuenta bancaria");
                }
                $idnomentrega = $consulta[0]['id_nomenclatura'];

                if ($detalle[6] == '2' && $detalle[7] == '0') {
                    $showmensaje = true;
                    throw new Exception("Seleccione un cargo");
                }
                $porcheque = 1;
            }

            // CONSULTAR EL LA CUENTA CAPITAL PARA EL PRODUCTO
            $id_nomeclatura_capital = 1;
            $consulta = $database->getAllResults("SELECT cp.id_cuenta_capital,id_fondo,DFecDsbls FROM cre_productos cp
                                                    INNER JOIN cremcre_meta cm ON cp.id=cm.CCODPRD 
                                                    WHERE cm.CCODCTA=?", [$data[0][0]]);
            if (empty($consulta)) {
                $showmensaje = true;
                throw new Exception("Fallo al encontrar el id de la cuenta bancaria");
            }
            $id_nomeclatura_capital = $consulta[0]['id_cuenta_capital'];
            $fuenteDeFondos = $consulta[0]['id_fondo'];

            //COMPROBAR CIERRE DE MES
            $cierre_mes = comprobar_cierrePDO($idusuario, $consulta[0]['DFecDsbls'], $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }

            //GASTOS DE LA LINEA DE CREDITO DEL GRUPO
            $datosgrupo = $database->selectColumns('tb_grupo', ['*'], 'id_grupos=?', [$archivo[0]]);
            $idsdiario = [];

            // +++++++++++++++++++++++++++++++
            $database->beginTransaction();

            $i = 0;
            while ($i < count($data)) {
                //INSERTAR CREPPG
                saveCredppg($data[$i][0], $database, $db_name_general, $idusuario, $idagencia);

                $datosCliente = $database->getAllResults("SELECT DFecDsbls,short_name FROM cre_productos cp
                                    INNER JOIN cremcre_meta cm ON cp.id=cm.CCODPRD 
                                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cm.CodCli
                                    WHERE cm.CCODCTA=?", [$data[$i][0]]);
                if (empty($datosCliente)) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron datos del cliente para el credito: " . $data[$i][0]);
                }
                $fechaDesembolso = $datosCliente[0]['DFecDsbls'];
                $short_name = $datosCliente[0]['short_name'];

                //INSERCION EN LA CREDKAR
                $concepto = strtoupper($data[$i][1]);
                $numdoc = ($detalle[0] == 2) ? $data[$i][2] : " ";
                $credkar = array(
                    'CCODCTA' => $data[$i][0],
                    'DFECPRO' => $fechaDesembolso,
                    'DFECSIS' => $hoy2,
                    'CNROCUO' => 1,
                    'NMONTO' => $data[$i][3],
                    'CNUMING' => $numdoc,
                    'CCONCEP' => $concepto,
                    'KP' => ($data[$i][3] - $data[$i][4]),
                    'OTR' => $data[$i][4],
                    'CCODOFI' => $idagencia,
                    'CCODINS' => "",
                    'CCODUSU' => $idusuario,
                    'CTIPPAG' => 'D',
                    'CESTADO' => '1',
                    'FormPago' => $detalle[0],
                    'boletabanco' => ($detalle[0] == 2) ? (($detalle[6] == 2) ? $detalle[4] : $data[$i][2]) : "",
                    'CCODBANCO' => ($detalle[0] == 2) ? $detalle[1] : "",
                    'CBANCO' => ($detalle[0] == 2) ? $detalle[2] : "",
                    'CMONEDA' => 'Q',
                    'DFECMOD' => $hoy
                );
                $id_credkar = $database->insert('CREDKAR', $credkar);

                $gastos = (array_key_exists(5, $data[$i])) ? $data[$i][5] : null;
                $k = 0;
                while ($gastos != null && $k < count($gastos)) {
                    $gascal = $gastos[$k][1];
                    if ($gascal > 0) {
                        //INSERTAR EN LA TABLA CREDKAR_DETALLE
                        $idgasto = $gastos[$k][0];
                        $credkar_detalle = array(
                            'id_credkar' => $id_credkar,
                            'id_concepto' => $idgasto,
                            'monto' => $gascal
                        );
                        $database->insert('credkar_detalle', $credkar_detalle);
                    }
                    $k++;
                }

                //ACTUALIZACION EN LA CREMCRE
                $cremcre_meta = array(
                    'Cestado' => 'F',
                    'NCapDes' => $data[$i][3],
                    'TipDocDes' => $tipdoc[$detalle[0] - 1],
                    'fecha_operacion' => $hoy
                );
                $database->update('cremcre_meta', $cremcre_meta, 'CCODCTA=?', [$data[$i][0]]);
                //INICIO DE TRANSACCIONES EN CONTA Y BANCOS

                //SI EL TIPO DE CHEQUE ES INDIVIDUAL, SE DEBE DE CREAR UNA PARTIDA POR CADA CREDITO
                /**
                 * Si el cheque es individual, se crea una partida en el diario (ctb_diario) y sus movimientos asociados (ctb_mov).
                 * También maneja la inserción de gastos y el monto a entregar.
                 * Si el desembolso es por cheque, inserta los detalles del cheque en las cuentas de cheques (ctb_chq).
                 * 
                 * @param array $detalle Detalles del crédito.
                 * @param array $data Datos del crédito.
                 * @param int $i Índice de los datos actuales del crédito.
                 * @param array $datosgrupo Datos del grupo.
                 * @param string $fuenteDeFondos Fuente de fondos.
                 * @param string $short_name Nombre corto del beneficiario.
                 * @param int $idusuario ID del usuario.
                 * @param object $database Objeto de conexión a la base de datos.
                 * @param string $numdoc Número de documento.
                 * @param string $fechaDesembolso Fecha de desembolso.
                 * @param string $hoy2 Fecha actual.
                 * @param int $id_nomeclatura_capital ID de la nomenclatura de capital.
                 * @param int $idnomentrega ID de la nomenclatura de entrega.
                 * @param array $idsdiario Array para almacenar los IDs de las entradas del diario.
                 */
                if ($detalle[0] == 1 || ($detalle[0] == 2 && $detalle[6] == 1)) {
                    $glosa = "CRÉDITO GRUPAL:" . $data[$i][0] . " - GRUPO:" . $datosgrupo[0]['NombreGrupo'] . " - FONDO:" . $fuenteDeFondos . " - BENEFICIARIO:" . strtoupper($short_name);
                    // $numpartida = getnumcompdo($idusuario, $database);
                    $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fechaDesembolso);
                    $ctb_diario = array(
                        'numcom' => $numpartida,
                        'id_ctb_tipopoliza' => 1,
                        'id_tb_moneda' => 1,
                        'numdoc' => $numdoc,
                        'glosa' => $glosa,
                        'fecdoc' => $fechaDesembolso,
                        'feccnt' => $fechaDesembolso,
                        'cod_aux' => $data[$i][0],
                        'id_tb_usu' => $idusuario,
                        'fecmod' => $hoy2,
                        'estado' => 1,
                        'id_agencia' => $idagencia
                    );
                    $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => $fuenteDeFondos,
                        'id_ctb_nomenclatura' => $id_nomeclatura_capital,
                        'debe' => $data[$i][3],
                        'haber' => 0
                    );
                    $database->insert('ctb_mov', $ctb_mov);

                    $gastos = (array_key_exists(5, $data[$i])) ? $data[$i][5] : null;
                    $k = 0;
                    while ($gastos != null && $k < count($gastos)) {
                        $gascal = $gastos[$k][1];
                        if ($gascal > 0) {
                            $nomenclatura = $gastos[$k][2];
                            $ctb_mov = array(
                                'id_ctb_diario' => $id_ctb_diario,
                                'id_fuente_fondo' => $fuenteDeFondos,
                                'id_ctb_nomenclatura' => $nomenclatura,
                                'debe' => 0,
                                'haber' => $gascal
                            );
                            $database->insert('ctb_mov', $ctb_mov);
                        }
                        $k++;
                    }
                    //INSERCION DE MONTO A ENTREGAR
                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => $fuenteDeFondos,
                        'id_ctb_nomenclatura' => $idnomentrega,
                        'debe' => 0,
                        'haber' => ($data[$i][3] - $data[$i][4])
                    );
                    $database->insert('ctb_mov', $ctb_mov);

                    //---------INSERCION EN CUENTAS DE CHEQUES SI EL DESEMBOLSO ES POR CHEQUE
                    if ($detalle[0] == 2) {
                        $ctb_chq = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_cuenta_banco' => $detalle[2],
                            'numchq' => $data[$i][2],
                            'nomchq' => $short_name,
                            'monchq' => ($data[$i][3] - $data[$i][4]),
                            'emitido' => 0
                        );
                        $database->insert('ctb_chq', $ctb_chq);
                    }
                    $idsdiario[$i] = $id_ctb_diario;
                }
                $i++;
            }
            // SI EL CHEQUE ES GRUPAL
            // if ($detalle[0] == 1 || ($detalle == 2 && $detalle[6] == 1)) 
            if ($detalle[0] == 2 && $detalle[6] == 2) {
                // $numpartida = getnumcompdo($idusuario, $database);
                $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fechaDesembolso);
                $ctb_diario = array(
                    'numcom' => $numpartida,
                    'id_ctb_tipopoliza' => 1,
                    'id_tb_moneda' => 1,
                    'numdoc' => $detalle[4],
                    'glosa' => $detalle[3],
                    'fecdoc' => $fechaDesembolso,
                    'feccnt' => $fechaDesembolso,
                    'cod_aux' => $data[0][0],
                    'id_tb_usu' => $idusuario,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'id_agencia' => $idagencia
                );
                $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                //Monto apertura total
                $montototal = array_sum(array_column($data, 3));
                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $fuenteDeFondos,
                    'id_ctb_nomenclatura' => $id_nomeclatura_capital,
                    'debe' => $montototal,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $ctb_mov);

                //+++++++PRUEBA UNION
                $resultado = [];

                foreach ($data as $fila) {
                    foreach ($fila[5] as $item) {
                        $id = $item[0];
                        $monto = $item[1];
                        $cuentaContable = $item[2];

                        if (!isset($resultado[$id])) {
                            $resultado[$id] = ["id" => $id, "monto" => 0, "cuentacontable" => ''];
                        }

                        $resultado[$id]["monto"] += $monto;
                        $resultado[$id]["cuentacontable"] = $cuentaContable;
                    }
                }

                $matriz_final = array_values($resultado);

                foreach ($matriz_final as $item) {
                    if ($item['monto'] <= 0) {
                        continue;
                    }
                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => $fuenteDeFondos,
                        'id_ctb_nomenclatura' => $item['cuentacontable'],
                        'debe' => 0,
                        'haber' => $item['monto']
                    );
                    $database->insert('ctb_mov', $ctb_mov);
                }

                //MONTO A ENTREGAR
                $montoEntrega = $montototal - round((array_sum(array_column($data, 4))) ?? 0, 2);
                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $fuenteDeFondos,
                    'id_ctb_nomenclatura' => $idnomentrega,
                    'debe' => 0,
                    'haber' => $montoEntrega
                );

                $database->insert('ctb_mov', $ctb_mov);

                //INSERCION DE CHEQUE
                $ctb_chq = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_cuenta_banco' => $detalle[2],
                    'numchq' => $detalle[4],
                    'nomchq' => strtoupper($detalle[7]),
                    'monchq' => $montoEntrega,
                    'emitido' => 0
                );
                $database->insert('ctb_chq', $ctb_chq);

                $idsdiario[0] = $id_ctb_diario;
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Créditos Desembolsados correctamente";
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
        echo json_encode([$mensaje, $status, ($idsdiario ?? []), $porcheque ?? 0]);
        break;
    case 'changestatuscreditos':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        list($encryptedID, $ciclo, $status) = $_POST["archivo"];
        list($csrftoken) = $_POST["inputs"];

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

        $idGrupo = $secureID->decrypt($encryptedID);

        $camposEstados = array(
            'A' => 'DfecSol',
            'D' => 'DFecAnal',
            'E' => 'DFecApr',
            'F' => 'DFecDsbls',
        );

        $showmensaje = false;
        try {
            $database->openConnection();
            //COMPROBAR CIERRE DE CAJA
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                $showmensaje = true;
                throw new Exception($cierre_caja[1]);
            }

            $creditos = $database->selectColumns('cremcre_meta', ['Cestado', 'CCODCTA', 'DFecDsbls', 'DFecApr', 'DFecAnal', 'DfecSol', 'fecha_operacion'], 'CCodGrupo=? AND NCiclo=?', [$idGrupo, $ciclo]);
            if (empty($creditos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron creditos para el grupo y ciclo seleccionado");
            }

            //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
            if ($creditos[0]['Cestado'] == "F") {
                $cierre_mes = comprobar_cierrePDO($idusuario, $creditos[0]['DFecDsbls'], $database);
                if ($cierre_mes[0] == 0) {
                    $showmensaje = true;
                    throw new Exception($cierre_mes[1]);
                }
            }

            $database->beginTransaction();

            $cremcre_meta = array(
                'Cestado' => $status,
                'NCapDes' => 0,
                'fecha_operacion' => date('Y-m-d', strtotime($creditos[$camposEstados[$status]] ?? $hoy))
            );
            $database->update('cremcre_meta', $cremcre_meta, 'CCodGrupo=? AND NCiclo=?', [$idGrupo, $ciclo]);

            foreach ($creditos as $credito) {
                $database->delete("Cre_ppg", "ccodcta=?", [$credito['CCODCTA']]);

                $credkar = array(
                    'CESTADO' => 'X',
                    'deleted_at' => $hoy2,
                    'deleted_by' => $idusuario
                );
                $database->update('CREDKAR', $credkar, 'CCODCTA=? AND CESTADO!="X"', [$credito['CCODCTA']]);

                $ctb_diario = array(
                    'estado' => 0,
                    'deleted_at' => $hoy2,
                    'deleted_by' => $idusuario
                );
                $database->update('ctb_diario', $ctb_diario, 'cod_aux=? AND estado=1', [$credito['CCODCTA']]);

                //REGISTRO DE CAMBIOS EN LA TABLA LOG DE LA CREMCRE
                $datoaux = ($credito['Cestado'] == "F") ? 'Reversion de desembolso' : "Cambio de estado";
                $fechafija = $credito[$camposEstados[$status]] ?? $credito['fecha_operacion'];
                $cre_logcremcre = array(
                    'ccodcta' => $credito['CCODCTA'],
                    'status_ant' => $credito['Cestado'],
                    'status_post' => $status,
                    'fecha_fija' => $fechafija,
                    'aux' => $datoaux,
                    'updated_at' => $hoy2,
                    'updated_by' => $idusuario
                );
                $database->insert('cre_logcremcre', $cre_logcremcre);
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
        echo json_encode([$mensaje, $status]);

        break;
    case 'loadAccount':
        list($codigoCliente, $tipoModulo) = $_POST["archivo"];
        $showmensaje = false;
        try {
            $database->openConnection();

            if ($tipoModulo == 1) {
                $cuentas = $database->getAllResults(
                    "SELECT ccodaho,tip.nombre FROM ahomcta cta 
                    INNER JOIN ahomtip tip ON tip.ccodtip = SUBSTR(cta.ccodaho,7,2)
                    WHERE cta.ccodcli=? AND cta.estado='A'",
                    [$codigoCliente]
                );
            } else {
                $cuentas = $database->getAllResults(
                    "SELECT ccodaport AS ccodaho,tip.nombre FROM aprcta cta 
                    INNER JOIN aprtip tip ON tip.ccodtip = SUBSTR(cta.ccodaport,7,2)
                    WHERE cta.ccodcli=? AND cta.estado='A'",
                    [$codigoCliente]
                );
            }

            if (empty($cuentas)) {
                $showmensaje = true;
                throw new Exception("El cliente no posee cuentas activas");
            }
            $mensaje = "Cuentas cargadas correctamente";
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
            'cuentas' => $cuentas ?? [],
            'reprint' => 0,
        ]);
        break;
    case 'cuotasgrupo':
        $param = $_POST['datas'];
        $codgrupo = $param[0];
        $ciclo = $param[1];

        $strquery = 'SELECT crem.DFecDsbls apertura,SUM(crem.NCapDes) monto_desembolsado,
                IFNULL((SELECT SUM(KP) FROM CREDKAR cred INNER JOIN cremcre_meta cre ON cre.CCODCTA = cred.ccodcta WHERE cre.CCodGrupo=crem.CCodGrupo AND cre.NCiclo=crem.NCiclo AND cre.Cestado="F" AND cred.CTIPPAG="P" AND cred.CESTADO!="X"),0) pagado,
                ppg.cnrocuo nocuota,ppg.dfecven fecha_cuota,SUM(ppg.ncapita) montocapital,SUM(ppg.nintere) montointeres,
                SUM(ppg.OtrosPagos) monto_otros,SUM(ppg.SaldoCapital) saldo_kp
                FROM Cre_ppg ppg 
                INNER JOIN cremcre_meta crem ON crem.CCODCTA = ppg.ccodcta 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
                WHERE crem.CCodGrupo="' . $codgrupo . '" AND crem.NCiclo="' . $ciclo . '" AND crem.Cestado="F" GROUP BY ppg.dfecven;';

        $consulta = mysqli_query($conexion, $strquery);
        $array_datos = array();
        $i = 0;
        $contador = 1;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $fecha = $fila["fecha_cuota"];
            $boton = '<button type="button" class="btn btn-outline-success" title="Planilla en Excel" onclick="reportes([[],[],[],[' . $codgrupo . ',' . $ciclo . ',`' . $fecha . '`]],`xlsx`,`planilla_cuota`,1)">
                        <i class="fa-solid fa-file-excel"></i></button>';
            $array_datos[] = array(
                "0" => $fila["nocuota"],
                "1" => date("d-m-Y", strtotime($fecha)),
                "2" => number_format($fila["montocapital"], 2),
                "3" => number_format($fila["montointeres"], 2),
                "4" => number_format($fila["monto_otros"], 2),
                "5" => number_format($fila["montocapital"] + $fila["montointeres"] + $fila["monto_otros"], 2),
                "6" => $fila["saldo_kp"],
                "7" => $boton

            );
            $i++;
            $contador++;
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
        }
    }
    return ["", '0', false];
}
