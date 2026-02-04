<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

use Micro\Helpers\Log;
use Micro\Helpers\Beneq;

session_start();
include __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

include '../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];
switch ($condi) {
    case 'cpoliza': //CREAR REGISTRO DE POLIZA DE DIARIO EN CTB_DIARIO Y CTB_MOV
        $inputs = $_POST["inputs"];
        $datospartida = $inputs[0];
        $datosdebe = $inputs[1];
        $datoshaber = $inputs[2];
        $datoscuentas = $inputs[3];
        $datosfondos = $inputs[4];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
        $cierre = comprobar_cierre($idusuario, $datospartida[1], $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }
        $valido = validarcampo($datospartida, "");
        if ($valido != "1") {
            echo json_encode([$valido, '0']);
            return;
        }
        if ($datospartida[3] != $datospartida[4]) {
            echo json_encode(['Sumatoria de debe no es igual a la del haber', '0']);
            return;
        }
        if ($datospartida[3] == 0 || $datospartida[4] == 0) {
            echo json_encode(['Sumatoria es igual a 0, Ingrese montos', '0']);
            return;
        }
        if ($datospartida[3] < 0 || $datospartida[4] < 0) {
            echo json_encode(['Las sumatorias del debe y del haber no deben ser negativas', '0']);
            return;
        }
        $numdoc = ($datospartida[5] == "") ? 'F' : $datospartida[5];
        //inicio transaccion
        $conexion->autocommit(false);
        try {
            // $numpartida = getnumcom($archivo[0], $conexion); //Obtener numero de partida
            $numpartida = Beneq::getNumcomLegacy($idusuario, $conexion, $selects[0], $datospartida[1]); //Obtener numero de partida
            //INSERCION DEL ENCABEZADO
            $res1 = $conexion->prepare("INSERT INTO `ctb_diario`(`numcom`,`id_ctb_tipopoliza`,`id_tb_moneda`,`numdoc`,`glosa`,`fecdoc`,`feccnt`,`cod_aux`,`id_tb_usu`,`id_agencia`,`fecmod`,`estado`,`editable`) 
            VALUES (?,?,1,?,?,?,?,'partida_diario',?,?,?,1,1)");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['Error 1:' . $aux, '0']);
                $conexion->rollback();
                return;
            }
            $res1->bind_param('sissssiis', $numpartida, $selects[1], $numdoc, $datospartida[2], $datospartida[0], $datospartida[1], $archivo[0], $selects[0], $hoy2);
            $res1->execute();

            //INSERCION DE MOVIMIENTOS EN CTB_MOV
            $id_ctb_diario = get_id_insertado($conexion); //obtener el id insertado en diario        
            $i = 0;
            while ($i < count($datoscuentas)) {
                $res = $conexion->prepare("INSERT INTO `ctb_mov`(`id_ctb_diario`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES (?,?,?,?,?)");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode(['Error 2:' . $aux, '0']);
                    $conexion->rollback();
                    return;
                }
                $res->bind_param('iiidd', $id_ctb_diario, $datosfondos[$i], $datoscuentas[$i], $datosdebe[$i], $datoshaber[$i]);
                $res->execute();
                $i++;
            }
            $conexion->commit();
            echo json_encode(['Correcto,  Partida de diario generada: ' . $numpartida, '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'upoliza': //ACTUALIZACION DE DATOS DE POLIZA EN CTB_DIARIO Y CTB_MOV
        $inputs = $_POST["inputs"];
        $datospartida = $inputs[0];
        $datosdebe = $inputs[1];
        $datoshaber = $inputs[2];
        $datoscuentas = $inputs[3];
        $datosfondos = $inputs[4];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];
        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
        $cierre = comprobar_cierre($idusuario, $datospartida[1], $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }

        $valido = validarcampo($datospartida, "");
        if ($valido != "1") {
            echo json_encode([$valido, '0']);
            return;
        }
        if ($datospartida[3] != $datospartida[4]) {
            echo json_encode(['Sumatoria de debe no es igual a la del haber', '0']);
            return;
        }
        if ($datospartida[3] == 0 || $datospartida[4] == 0) {
            echo json_encode(['Sumatoria es igual a 0, Ingrese montos', '0']);
            return;
        }
        if ($datospartida[3] < 0 || $datospartida[4] < 0) {
            echo json_encode(['Las sumatorias del debe y del haber no deben ser negativas', '0']);
            return;
        }

        $numdoc = ($datospartida[5] == "") ? 'F' : $datospartida[5];

        //inicio transaccion
        $conexion->autocommit(false);
        try {
            //ACTUALIZACION DE ENCABEZADO
            $res1 = $conexion->prepare("UPDATE `ctb_diario` SET `glosa`=?,`fecdoc`=?,`feccnt`=?,`id_agencia`=?,`updated_at`=?,`updated_by`=?,`numdoc`=? WHERE id =?");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['Error 1:' . $aux, '0']);
                $conexion->rollback();
                return;
            }
            $res1->bind_param('sssisisi', $datospartida[2], $datospartida[0], $datospartida[1], $selects[0], $hoy2, $idusuario,$numdoc, $archivo[1]);
            $res1->execute();

            //ELIMINACION DE MOVIMIENTOS ANTERIORES PARA INSERTAR LOS ACTUALIZADOS
            $res2 = $conexion->prepare("DELETE FROM ctb_mov WHERE id_ctb_diario =?");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['Error 2:' . $aux, '0']);
                $conexion->rollback();
                return;
            }
            $res2->bind_param('i', $archivo[1]);
            $res2->execute();

            //INSERCION DE MOVIMIENTOS ACTUALIZADOS
            $i = 0;
            while ($i < count($datoscuentas)) {
                $debe = $datosdebe[$i];
                $haber = $datoshaber[$i];
                $res = $conexion->prepare("INSERT INTO `ctb_mov`(`id_ctb_diario`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES (?,?,?,?,?)");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    echo json_encode(['Error 3:' . $aux, '0']);
                    $conexion->rollback();
                    return;
                }
                $res->bind_param('iiidd', $archivo[1], $datosfondos[$i], $datoscuentas[$i], $debe, $haber);
                $res->execute();
                $i++;
            }
            $conexion->commit();
            echo json_encode(['Correcto,  Partida de diario actualizada: ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'dpoliza':
        $id = $_POST["ideliminar"];
        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO 
        $consulta = mysqli_query($conexion, "SELECT feccnt FROM ctb_diario WHERE id =" . $id);
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $fechapoliza = $fila["feccnt"];
        }

        // $cierre = comprobar_cierre($idusuario, $fechapoliza, $conexion);
        // if ($cierre[0] == 0) {
        //     echo json_encode([$cierre[1], '0']);
        //     return;
        // }

        $conexion->autocommit(false);
        try {
            $conexion->query("UPDATE `ctb_diario` SET `deleted_at`='$hoy2',`deleted_by`=$idusuario,`estado`=0 WHERE id =" . $id);
            $conexion->commit();
            echo json_encode(['Correcto,  Partida de diario Eliminada: ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la eliminacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'create_poliza_diario':
        Log::info('Poliza', [
            $_POST['archivo']
        ]);
        // ['numdoc', 'fedoc', 'feccnt', 'glosa'], ['id_ctb_tipopoliza', 'id_agencia'], []
        list($csrftoken, $numdoc, $fecdoc, $feccnt, $glosa) = $_POST['inputs'];
        list($tipopoliza, $id_agencia) = $_POST['selects'];
        list($movimientos, $idConfig) = $_POST['archivo'];

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

        $validar = validacionescampos([
            [$numdoc, "", 'Ingrese un numero de documento', 1],
            [$fecdoc, "", 'Ingrese una fecha de documento', 1],
            [$feccnt, "", 'Ingrese una fecha contable', 1],
            [$glosa, "", 'Ingrese una descripcion para la poliza', 1],
            [$tipopoliza, "", 'Seleccione un tipo de poliza', 1],
            [$id_agencia, "", 'Seleccione una agencia', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $sumaDebe = round(array_sum(array_column($movimientos, 'debe')), 2);
        $sumaHaber = round(array_sum(array_column($movimientos, 'haber')), 2);




        // if ($datospartida[3] != $datospartida[4]) {
        //     echo json_encode(['Sumatoria de debe no es igual a la del haber', '0']);
        //     return;
        // }
        // if ($datospartida[3] == 0 || $datospartida[4] == 0) {
        //     echo json_encode(['Sumatoria es igual a 0, Ingrese montos', '0']);
        //     return;
        // }
        // if ($datospartida[3] < 0 || $datospartida[4] < 0) {
        //     echo json_encode(['Las sumatorias del debe y del haber no deben ser negativas', '0']);
        //     return;
        // }

        $showmensaje = true;
        try {
            if ($sumaDebe != $sumaHaber) {
                $showmensaje = true;
                throw new Exception('Sumatoria de debe no es igual a la del haber');
            }
            if ($sumaDebe == 0 || $sumaHaber == 0) {
                $showmensaje = true;
                throw new Exception('Sumatoria es igual a 0, Ingrese montos');
            }
            if ($sumaDebe < 0 || $sumaHaber < 0) {
                $showmensaje = true;
                throw new Exception('Las sumatorias del debe y del haber no deben ser negativas');
            }

            $database->openConnection();
            //COMPROBAR CIERRE DE MES CONTABLE
            $cierre_mes = comprobar_cierrePDO($_SESSION['id'], $feccnt, $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }

            // $numcom = getnumcompdo($idusuario, $database);
            $numcom = Beneq::getNumcom($database, $idusuario, $id_agencia, $feccnt);

            $ctb_diario = [
                'numcom' => $numcom,
                'id_ctb_tipopoliza' => $tipopoliza,
                'id_tb_moneda' => 1,
                'numdoc' => $numdoc,
                'glosa' => $glosa,
                'fecdoc' => $fecdoc,
                'feccnt' => $feccnt,
                'cod_aux' => 'partida_diario_' . $idConfig,
                'id_tb_usu' => $idusuario,
                'id_agencia' => $id_agencia,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 1
            ];
            $idDiario = $database->insert('ctb_diario', $ctb_diario);

            foreach ($movimientos as $movimiento) {
                $ctb_mov = [
                    'id_ctb_diario' => $idDiario,
                    'id_fuente_fondo' => $movimiento['idFondo'],
                    'id_ctb_nomenclatura' => $movimiento['cuenta'],
                    'debe' => $movimiento['debe'],
                    'haber' => $movimiento['haber']
                ];
                $database->insert('ctb_mov', $ctb_mov);
            }

            $database->commit();

            $mensaje = "Partida de diario generada correctamente: $numcom";
            $status = 1;
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

        echo json_encode([$mensaje, $status]);

        break;
    case 'lista_cuentas_contables': {
            $codusu = $_POST['codusu'];
            $consulta = mysqli_query($conexion, "SELECT id, ccodcta, cdescrip, tipo FROM ctb_nomenclatura WHERE estado='1' ORDER BY ccodcta ASC");
            //se cargan los datos de las beneficiarios a un array
            $array_datos = array();
            $array_parenteco[] = [];
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $array_datos[] = array(
                    "0" => $fila["id"],
                    "1" => $fila["ccodcta"],
                    "2" => $fila["cdescrip"],
                    "3" => $fila["tipo"],
                    "4" => '<button type="button" class="btn btn-success btn-sm" onclick="printdiv5(`id_hidden,cod_cuenta,descripcion,tipo/A,A,A,A//#/cod_cuenta`,[`' . $fila["id"] . '`,`' . $fila["ccodcta"] . '`,`' . $fila["cdescrip"] . '`,`' . $fila["tipo"] . '`]); HabDes_boton(`1`);"><i class="fa-solid fa-eye"></i></button> 
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminar([`' . $fila["id"] . '`,`' . $codusu . '`],`crud_ctb`,`0`,`delete_cuentas_contables`)"><i class="fa-solid fa-trash"></i></button>'
                );
                $i++;

                // eliminar(ideliminar, dir, xtra, condi)
            }
            $results = array(
                "sEcho" => 1, //info para datatables
                "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
                "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
                "aaData" => $array_datos
            );
            mysqli_close($conexion);
            echo json_encode($results);
        }
        break;
    case 'create_cuentas_contables': {
            $inputs = $_POST['inputs'];
            $archivo = $_POST['archivo'];

            //VALIDACIONES
            //validar si todo esta lleno
            if ($inputs[2] == "") {
                echo json_encode(['Debe seleccionar un tipo', '0']);
                return;
            }
            if ($inputs[0] == "") {
                echo json_encode(['Debe digitar un código de cuenta', '0']);
                return;
            }
            if ($inputs[1] == "") {
                echo json_encode(['Debe digitar una descripción', '0']);
                return;
            }

            //validar que el ccodcta, sea un numero
            if (!is_numeric($inputs[0])) {
                echo json_encode(['Debe digitar un numero en el campo de código de cuenta', '0']);
                return;
            }

            //validar que el primer digito un numero de 1 al 9
            if (((intval(substr($inputs[0], 0, 1))) < 1)) {
                echo json_encode(['El primer numero debe estar en el rango de 1 a 9, revise el manual', '0']);
                return;
            }
            // validar si ya hay un registro con el mismo numero de ccodcta
            $bandera = false;
            $consulta = mysqli_query($conexion, "SELECT ccodcta FROM ctb_nomenclatura WHERE ccodcta='$inputs[0]' AND estado=1");
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = true;
            }
            if ($bandera) {
                echo json_encode(['No se puede insertar el registro, el código de cuenta ya existe', '0']);
                return;
            }
            //validacion de la existencia de una cuenta padre para este hijo nuevo
            $bandera = true;
            $padre = substr($inputs[0], 0, strlen($inputs[0]) - 2);
            $padre2 = substr($inputs[0], 0, strlen($inputs[0]) - 1);
            $padre3 = substr($inputs[0], 0, strlen($inputs[0]) - 3);
            $consulta = mysqli_query($conexion, "SELECT ccodcta FROM ctb_nomenclatura WHERE ccodcta='$padre' OR ccodcta='$padre2' OR ccodcta='$padre3' AND estado=1");
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = false;
            }
            if ($bandera) {
                echo json_encode(['No se puede insertar el registro, debido a que debe tener una cuenta padre', '0']);
                return;
            }

            //INSERCION EN LA BASE DE DATOS
            $conexion->autocommit(false);
            try {
                $conexion->query("INSERT INTO `ctb_nomenclatura`(`ccodcta`, `cdescrip`, `tipo`, `created_at`, `created_by`) VALUES ('$inputs[0]','$inputs[1]','$inputs[2]','$hoy2','$archivo[0]')");
                $conexion->commit();
                echo json_encode(['Registro satisfactorio', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }

            mysqli_close($conexion);
        }
        break;
    case 'update_cuentas_contables': {
            $inputs = $_POST['inputs'];
            $archivo = $_POST['archivo'];

            //VALIDACIONES
            //validar si todo esta lleno
            if ($inputs[0] == "" || $inputs[1] == "") {
                echo json_encode(['No es posible realizar la actualización', '0']);
                return;
            }
            if ($inputs[3] == "") {
                echo json_encode(['Debe seleccionar un tipo', '0']);
                return;
            }
            if ($inputs[2] == "") {
                echo json_encode(['Debe digitar una descripción', '0']);
                return;
            }

            //validar que el ccodcta, sea un numero
            if (!is_numeric($inputs[1])) {
                echo json_encode(['Debe digitar un numero en el campo de código de cuenta', '0']);
                return;
            }

            // validar si ya hay un registro con el mismo numero de ccodcta
            $bandera = false;
            $consulta = mysqli_query($conexion, "SELECT ccodcta FROM ctb_nomenclatura WHERE estado=1 AND id!='$inputs[0]' AND ccodcta='$inputs[1]'");
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = true;
            }
            if ($bandera) {
                echo json_encode(['No se puede actualizar el registro, el código de cuenta ya esta registrado', '0']);
                return;
            }

            //INSERCION EN LA BASE DE DATOS
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE `ctb_nomenclatura` SET `cdescrip`='$inputs[2]',`tipo`='$inputs[3]', `updated_at`='$hoy2', `updated_by`=$archivo[0] WHERE `id`=$inputs[0]");
                $conexion->commit();
                echo json_encode(['Registro actualizado correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case 'delete_cuentas_contables':
        $ideliminar = $_POST['ideliminar'];
        $conexion->autocommit(false);
        try {
            $conexion->query("UPDATE `ctb_nomenclatura` SET `deleted_at`='$hoy2',`estado`=0,`deleted_by`=$ideliminar[1] WHERE id =" . $ideliminar[0]);
            $conexion->commit();
            echo json_encode(['Registro eliminado satisfactoriamente', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la eliminacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'create_fuentefondos':
        //         $hoy2 = date("Y-m-d H:i:s");
        // $hoy = date("Y-m-d");
        $inputs = $_POST['inputs'];
        $archivo = $_POST['archivo'];

        $resultado = $conexion->query("INSERT INTO `ctb_fuente_fondos`(`descripcion`, `id_usuario`, `estado`, `dfecmod`) VALUES ('$inputs[0]','$archivo[0]','1','$hoy2')");
        if ($resultado) {
            echo json_encode(['Fuente de fondo registrado correctamente', '1']);
        } else {
            echo json_encode(['Fuente de fondo no registrado', '1']);
        }
        mysqli_close($conexion);
        break;
    case 'update_fuentefondos':
        $inputs = $_POST['inputs'];
        $archivo = $_POST['archivo'];

        $resultado = $conexion->query("UPDATE `ctb_fuente_fondos` SET `dfecmod`='$hoy2',`id_usuario`='$archivo[0]',`descripcion`='$inputs[1]' WHERE id =" . $inputs[0]);
        if ($resultado) {
            echo json_encode(['Fuente de fondo actualizado correctamente', '1']);
        } else {
            echo json_encode(['Fuente de fondo no actualizado', '1']);
        }
        mysqli_close($conexion);
        break;
    case 'delete_fuentefondos':
        $ideliminar = $_POST['ideliminar'];

        $resultado = $conexion->query("UPDATE `ctb_fuente_fondos` SET `deleted_at`='$hoy2', `estado`=0 WHERE id =" . $ideliminar[0]);
        if ($resultado) {
            echo json_encode(['Fuente de fondo eliminado correctamente', '1']);
        } else {
            echo json_encode(['Fuente de fondo no eliminado', '1']);
        }
        mysqli_close($conexion);
        break;
    case 'mesesctb':
        $meses = array("ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE");
        $consulta = mysqli_query($conexion, "SELECT * FROM ctb_meses WHERE id_agencia=$idagencia ORDER BY anio desc,num_mes desc");
        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $nummes = $fila["num_mes"];
            $nummes = (array_key_exists($nummes - 1, $meses)) ? $meses[$nummes - 1] : "INVALIDO";
            $status0 = array('Cerrado', 'danger', 'Abrir', 'fa-lock-open');
            $status1 = array('Abierto', 'success', 'Cerrar', 'fa-lock');
            $status = ($fila["cierre"] == 1) ? $status1 : $status0;
            $array_datos[] = array(
                "0" => $nummes,
                "1" => $fila["anio"],
                "2" => '<span style="font-size:1rem;width:min(9rem,90%);" class="badge rounded-pill text-bg-' . $status[1] . '">' . $status[0] . '</span>',
                "3" => '<button type="button" id="cerrar" class="btn btn-warning" onclick="obtiene([],[],[],`' . $status[2] . '`,`0`,[' . $fila["id"] . ',' . $fila["num_mes"] . ',' . $fila["anio"] . '])">
                <i class="fa-solid ' . $status[3] . '"></i>' . $status[2] . ' Mes</button>'
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
    case 'apertura_mes':

        $mesactual = date("m");
        $anioactual = date("Y");
        //COMPROBAR SI NO HAY MESES APERTURADAS
        $consulta = mysqli_query($conexion, "SELECT * FROM ctb_meses ORDER BY anio,num_mes desc");
        $mesesctb[] = [];
        $agencias[] = [];
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $mesesctb[$i] = $fila;
            $i++;
        }
        //LISTADO DE TODAS LAS AGENCIAS DE LA INSTITUCION
        $consulta = mysqli_query($conexion, "SELECT * FROM tb_agencia");
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $agencias[$i] = $fila;
            $i++;
        }

        //APERTURANDO MESES SI NO HAN SIDO APERTURADAS
        $conexion->autocommit(false);
        try {
            $i = 0;
            $flag = true;
            while ($i < count($agencias)) {
                $idofi = $agencias[$i]['id_agencia'];
                $fila = 0;
                while ($fila < count($mesesctb)) {
                    $smes = $mesesctb[$fila]["num_mes"];
                    $sagencia = $mesesctb[$fila]["id_agencia"];
                    $sanio = $mesesctb[$fila]["anio"];

                    if ($idofi == $sagencia && $mesactual == $smes && $sanio == $anioactual) {
                        $flag = false;
                    }
                    $fila++;
                }

                if ($flag) {
                    // $sql = $conexion->prepare("UPDATE `ctb_meses` SET `open_at`=?,`open_by`=?,`cierre`=1 WHERE id =?");
                    /*   $res = $conexion->query("INSERT INTO `ctb_meses`(`id_agencia`,`num_mes`,`anio`,`cierre`,`open_at`) 
                    VALUES ($idofi,$mesactual,$anioactual, 1,'" . $hoy2 . "')"); */
                    $res = $conexion->prepare("INSERT INTO `ctb_meses`(`id_agencia`,`num_mes`,`anio`,`cierre`,`open_at`) VALUES (?,?,?,1,?)");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        echo json_encode([$aux, '0']);
                        $conexion->rollback();
                        return;
                    }
                    if (!$res) {
                        echo json_encode(['Error en la Apertura', '0']);
                        $conexion->rollback();
                        return;
                    }
                    $res->bind_param('iiis', $idofi, $mesactual, $anioactual, $hoy2);
                    $res->execute();
                }
                $flag = true;
                $i++;
            }
            if ($conexion->commit()) {
                echo json_encode(['APERTURADAS LISTAS', '1']);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
                $conexion->rollback();
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'apertura_mes_fecha':
        $inputs = $_POST["inputs"];
        $fechaini = strtotime($inputs[0]);
        $fechafin = strtotime($inputs[1]);
        /* 
        $fechaini = new DateTime($inputs[0]);
        $fechafin = new DateTime($inputs[1]); */

        /* $diasdif=dias_dif($fechaini,$fechafin);
        $meses=$diasdif/30; */

        if ($fechafin < $fechaini) {
            echo json_encode(['Rango de fechas invalido', '0']);
            return;
        }

        $mesini = date("m", $fechaini);
        $anioini = date("Y", $fechaini);

        $mesfin = date("m", $fechafin);
        $aniofin = date("Y", $fechafin);

        $datos[] = [];
        $i = $anioini;
        $j = 0;
        $k = 0;
        $cont = 0;
        while ($i <= $aniofin) {
            $j = ($j == 0) ? $mesini * 1 : 1;
            $k = ($i == $aniofin) ? $mesfin : 12;
            while ($j <= $k) {
                $datos[$cont] = [$j, $i];
                $cont++;
                $j++;
            }
            $i++;
        }

        /* echo json_encode([$datos, '0']);
        return; */

        //COMPROBAR SI NO HAY MESES APERTURADAS
        $consulta = mysqli_query($conexion, "SELECT * FROM ctb_meses ORDER BY anio,num_mes desc");
        $mesesctb[] = [];
        $agencias[] = [];
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $mesesctb[$i] = $fila;
            $i++;
        }
        //LISTADO DE TODAS LAS AGENCIAS DE LA INSTITUCION
        $consulta = mysqli_query($conexion, "SELECT * FROM tb_agencia");
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $agencias[$i] = $fila;
            $i++;
        }

        //APERTURANDO MESES SI NO HAN SIDO APERTURADAS
        $conexion->autocommit(false);
        try {
            $i = 0;
            $flag = true;
            while ($i < count($agencias)) {
                $idofi = $agencias[$i]['id_agencia'];

                $k = 0;
                while ($k < count($datos)) {
                    $mesactual = $datos[$k][0];
                    $anioactual = $datos[$k][1];

                    $fila = 0;
                    while ($fila < count($mesesctb)) {
                        $smes = $mesesctb[$fila]["num_mes"];
                        $sagencia = $mesesctb[$fila]["id_agencia"];
                        $sanio = $mesesctb[$fila]["anio"];

                        if ($idofi == $sagencia && $mesactual == $smes && $sanio == $anioactual) {
                            $flag = false;
                        }
                        $fila++;
                    }

                    if ($flag) {
                        $res = $conexion->prepare("INSERT INTO `ctb_meses`(`id_agencia`,`num_mes`,`anio`,`cierre`,`open_at`) VALUES (?,?,?,1,?)");
                        $aux = mysqli_error($conexion);
                        if ($aux) {
                            echo json_encode([$aux, '0']);
                            $conexion->rollback();
                            return;
                        }
                        if (!$res) {
                            echo json_encode(['Error en la Apertura', '0']);
                            $conexion->rollback();
                            return;
                        }
                        $res->bind_param('iiis', $idofi, $mesactual, $anioactual, $hoy2);
                        $res->execute();
                    }
                    $flag = true;
                    $k++;
                }
                $i++;
            }
            if ($conexion->commit()) {
                echo json_encode(['APERTURADAS LISTAS', '1']);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
                $conexion->rollback();
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'Abrir':
        $archivo = $_POST['archivo'];
        $sql = $conexion->prepare("UPDATE `ctb_meses` SET `open_at`=?,`open_by`=?,`cierre`=1 WHERE id =?");
        //CAPTURA DE CUALQUIER ERROR EN LA CONSULTA
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode([$aux . ": ", '0']);
            return;
        }
        //PASO DE PARAMETROS s:string, i:integer
        $sql->bind_param('sii', $hoy2, $idusuario, $archivo[0]);
        $sql->execute();
        if ($sql->affected_rows > 0) {
            echo json_encode(['Mes contable abierto correctamente', '1']);
        } else {
            echo json_encode(['Hubo un error al abrir el mes contable', '0']);
        }
        $sql->close();
        mysqli_close($conexion);
        break;
    case 'Cerrar':
        $archivo = $_POST['archivo'];
        $diactual = date("d");
        $mesactual = date("m");
        $anioactual = date("Y");

        $datestring = $archivo[2] . '-' . $archivo[1] . '-01';
        $dated = strtotime($datestring);
        $lastdate = strtotime(date("Y-m-t", $dated));
        $lastday = date("d", $lastdate);

        if ($diactual < $lastday && $mesactual == $archivo[1] && $anioactual == $archivo[2]) {
            echo json_encode(['No se puede cerrar el mes contable porque aun no ha terminado', '0']);
            return;
        }

        //CAPTURA DE CUALQUIER ERROR EN LA CONEXION
        if (mysqli_connect_errno()) {
            echo json_encode(['Error de conexión:' . mysqli_connect_error(), '0']);
            return;
        }
        $sql = $conexion->prepare("UPDATE `ctb_meses` SET `close_at`=?,`close_by`=?,`cierre`=0 WHERE id =?");

        //CAPTURA DE CUALQUIER ERROR EN LA CONSULTA
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode([$aux . ": ", '0']);
            return;
        }
        //PASO DE PARAMETROS s:string, i:integer
        $sql->bind_param('sii', $hoy2, $idusuario, $archivo[0]);
        $sql->execute();
        if ($sql->affected_rows > 0) {
            echo json_encode(['Mes contable cerrado correctamente', '1']);
        } else {
            echo json_encode(['Hubo un error al cerrar el mes contable:', '0']);
        }
        $sql->close();
        mysqli_close($conexion);
        break;
    //aqui se hace la insercion en la base de datos del lado del backend
    case 'update_data_flujo':
        if (!isset($_POST['archivo'])) {
            echo json_encode(['Seleccione Cuentas porfavor', '0']);
            return;
        }
        $datos = $_POST["archivo"];
        $parte1 = (array_key_exists(0, $datos)) ? $datos[0] : NULL;
        $parte2 = (array_key_exists(1, $datos)) ? $datos[1] : NULL;
        $parte3 = (array_key_exists(2, $datos)) ? $datos[2] : NULL;
        $parte4 = (array_key_exists(3, $datos)) ? $datos[3] : NULL;
        $parte5 = (array_key_exists(4, $datos)) ? $datos[4] : NULL;

        $conexion->autocommit(false);
        try {
            $res0 = $conexion->prepare("UPDATE ctb_nomenclatura SET categoria_flujo=0 WHERE estado=1 AND tipo='D' AND SUBSTR(ccodcta,1,1)<=5");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['Error 0:' . $aux, '0']);
                $conexion->rollback();
                return;
            }
            $res0->execute();
            $res0->close();

            $categorias = [
                1 => "Gastos que no requirieron efectivo",
                2 => "Efectivos Generados por actividades de operacion",
                3 => "Flujo de efectivos por actividades de inversion",
                4 => "Flujo de efectivos por actividades de financiamiento",
                5 => "Cuentas de caja y bancos"
            ];

            // Validación y asignación para parte1
            if ($parte1 != null) {
                $i = 0;
                while ($i < count($parte1)) {
                    // Nueva validación
                    $check1 = $conexion->prepare("SELECT categoria_flujo, LEFT(cdescrip, 256) as nombre FROM ctb_nomenclatura WHERE id=?");
                    $check1->bind_param('i', $parte1[$i]);
                    $check1->execute();
                    $result1 = $check1->get_result();
                    if ($row1 = $result1->fetch_assoc()) {
                        if ($row1['categoria_flujo'] != 0) {
                            echo json_encode(['Advertencia: La cuenta "' . $row1['nombre'] . '" ya está asignada a la categoría "' . $categorias[$row1['categoria_flujo']] . '". Desselecciónela antes de cambiarla.', '0']);
                            $conexion->rollback();
                            return;
                        }
                    }
                    $check1->close();

                    $res = $conexion->prepare("UPDATE ctb_nomenclatura SET categoria_flujo=1 WHERE id=?");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        echo json_encode(['Error 1:' . $aux, '0']);
                        $conexion->rollback();
                        return;
                    }
                    $res->bind_param('i', $parte1[$i]);
                    $res->execute();
                    $i++;
                }
                $res->close();
            }

            // Validación y asignación para parte2
            if ($parte2 != null) {
                $i = 0;
                while ($i < count($parte2)) {
                    // Nueva validación
                    $check2 = $conexion->prepare("SELECT categoria_flujo, LEFT(cdescrip, 256) as nombre FROM ctb_nomenclatura WHERE id=?");
                    $check2->bind_param('i', $parte2[$i]);
                    $check2->execute();
                    $result2 = $check2->get_result();
                    if ($row2 = $result2->fetch_assoc()) {
                        if ($row2['categoria_flujo'] != 0) {
                            echo json_encode(['Advertencia: La cuenta "' . $row2['nombre'] . '" ya está asignada a la categoría "' . $categorias[$row2['categoria_flujo']] . '". Desselecciónela antes de cambiarla.', '0']);
                            $conexion->rollback();
                            return;
                        }
                    }
                    $check2->close();

                    $res2 = $conexion->prepare("UPDATE ctb_nomenclatura SET categoria_flujo=2 WHERE id=?");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        echo json_encode(['Error 2:' . $aux, '0']);
                        $conexion->rollback();
                        return;
                    }
                    $res2->bind_param('i', $parte2[$i]);
                    $res2->execute();
                    $i++;
                }
                $res2->close();
            }

            // Validación y asignación para parte3
            if ($parte3 != null) {
                $i = 0;
                while ($i < count($parte3)) {
                    // Nueva validación
                    $check3 = $conexion->prepare("SELECT categoria_flujo, LEFT(cdescrip, 256) as nombre FROM ctb_nomenclatura WHERE id=?");
                    $check3->bind_param('i', $parte3[$i]);
                    $check3->execute();
                    $result3 = $check3->get_result();
                    if ($row3 = $result3->fetch_assoc()) {
                        if ($row3['categoria_flujo'] != 0) {
                            echo json_encode(['Advertencia: La cuenta "' . $row3['nombre'] . '" ya está asignada a la categoría "' . $categorias[$row3['categoria_flujo']] . '". Desselecciónela antes de cambiarla.', '0']);
                            $conexion->rollback();
                            return;
                        }
                    }
                    $check3->close();

                    $res3 = $conexion->prepare("UPDATE ctb_nomenclatura SET categoria_flujo=3 WHERE id=?");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        echo json_encode(['Error 3:' . $aux, '0']);
                        $conexion->rollback();
                        return;
                    }
                    $res3->bind_param('i', $parte3[$i]);
                    $res3->execute();
                    $i++;
                }
                $res3->close();
            }

            // Validación y asignación para parte4
            if ($parte4 != null) {
                $i = 0;
                while ($i < count($parte4)) {
                    // Nueva validación
                    $check4 = $conexion->prepare("SELECT categoria_flujo, LEFT(cdescrip, 256) as nombre FROM ctb_nomenclatura WHERE id=?");
                    $check4->bind_param('i', $parte4[$i]);
                    $check4->execute();
                    $result4 = $check4->get_result();
                    if ($row4 = $result4->fetch_assoc()) {
                        if ($row4['categoria_flujo'] != 0) {
                            echo json_encode(['Advertencia: La cuenta "' . $row4['nombre'] . '" ya está asignada a la categoría "' . $categorias[$row4['categoria_flujo']] . '". Desselecciónela antes de cambiarla.', '0']);
                            $conexion->rollback();
                            return;
                        }
                    }
                    $check4->close();

                    $res4 = $conexion->prepare("UPDATE ctb_nomenclatura SET categoria_flujo=4 WHERE id=?");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        echo json_encode(['Error 4:' . $aux, '0']);
                        $conexion->rollback();
                        return;
                    }
                    $res4->bind_param('i', $parte4[$i]);
                    $res4->execute();
                    $i++;
                }
                $res4->close();
            }
            // Validación y asignación para parte5
            if ($parte5 != null) {
                $i = 0;
                while ($i < count($parte5)) {
                    // Nueva validación
                    $check5 = $conexion->prepare("SELECT categoria_flujo, LEFT(cdescrip, 256) as nombre FROM ctb_nomenclatura WHERE id=?");
                    $check5->bind_param('i', $parte5[$i]);
                    $check5->execute();
                    $result5 = $check5->get_result();
                    if ($row5 = $result5->fetch_assoc()) {
                        if ($row5['categoria_flujo'] != 0) {
                            echo json_encode(['Advertencia: La cuenta "' . $row5['nombre'] . '" ya está asignada a la categoría "' . $categorias[$row5['categoria_flujo']] . '". Desselecciónela antes de cambiarla.', '0']);
                            $conexion->rollback();
                            return;
                        }
                    }
                    $check5->close();

                    $res4 = $conexion->prepare("UPDATE ctb_nomenclatura SET categoria_flujo=5 WHERE id=?");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        echo json_encode(['Error 4:' . $aux, '0']);
                        $conexion->rollback();
                        return;
                    }
                    $res4->bind_param('i', $parte5[$i]);
                    $res4->execute();
                    $i++;
                }
                $res4->close();
            }

            $conexion->commit();
            echo json_encode(['Correcto,  Cuentas actualizadas', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }

        mysqli_close($conexion);
        break;
    //termina el case que actualiza los datos 
    case 'cuentasfe':
        $numero = $_POST["numero"];
        $nomenclatura[] = [];
        $strque = "SELECT *, 
                              CASE categoria_flujo
                                  WHEN '1' THEN 'Gastos que no requirieron efectivo'
                                  WHEN '2' THEN 'Efectivos Generados por actividades de operacion'
                                  WHEN '3' THEN 'Flujo de efectivos por actividades de inversion'
                                  WHEN '4' THEN 'Flujo de efectivos por actividades de financiamiento'
                                  WHEN '5' THEN 'Cuentas de caja y bancos'
                                  ELSE 'Sin categoría asignada'
                              END AS categoria_descripcion
                           FROM ctb_nomenclatura 
                           WHERE estado = 1 AND tipo = 'D' AND substr(ccodcta, 1, 1) <= 5 
                           ORDER BY ccodcta";
        $consulta = mysqli_query($conexion, $strque);
        $array_datos = array();
        $i = 0;
        $contador = 1;
        $iden = ['a', 'b', 'c', 'd'];
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $id = $fila["id"];
            // Agregar tooltips a los elementos
            $cuenta = '<label for="id' . $numero . '_' . $id . '" class="form-check-label" data-bs-toggle="tooltip" title="Categoría actual: ' . htmlspecialchars($fila['categoria_descripcion']) . '"> ' . $fila['ccodcta'] . '</label>';
            $nombrecuenta = '<label for="id' . $numero . '_' . $id . '" class="form-check-label" data-bs-toggle="tooltip" title="Descripción: ' . htmlspecialchars($fila['categoria_descripcion']) . '"> ' . $fila['cdescrip'] . '</label>';
            $categoria = $fila['categoria_flujo'];
            $chequed = ($categoria == $numero) ? 'checked' : '';
            $switch = '<div class="form-check form-switch" data-bs-toggle="tooltip" title="Categoría actual: ' . htmlspecialchars($fila['categoria_descripcion']) . '"><input id="id' . $numero . '_' . $id . '" class="form-check-input S' . $numero . '" type="checkbox" role="switch" value="' . $id . '" ' . $chequed . '></div>';
            $array_datos[] = array(
                "0" => $switch,
                "1" => $cuenta,
                "2" => $nombrecuenta
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

    case 'add_clase':
        $ccodcta = $_POST['ccodcta'];
        $id = $_POST['id'];

        // Realizar la verificación si ya existe una entrada con los valores proporcionados
        $consulta = $conexion->query("SELECT id_tipo, clase FROM ctb_parametros_cuentas WHERE id_tipo = '$id' AND clase = '$ccodcta'");

        if ($consulta) {
            if ($consulta->num_rows > 0) {
                echo json_encode(array("existe" => true));
            } else {

                $insercion = $conexion->query("INSERT INTO ctb_parametros_cuentas (id_tipo, clase) VALUES ('$id', '$ccodcta')");

                if ($insercion) {
                    echo json_encode(array("exito" => true));
                } else {
                    echo json_encode(array("error" => "Error al insertar datos: " . $conexion->error));
                }
            }
        } else {
            echo json_encode(array("error" => "Error en la consulta: " . $conexion->error));
        }

        mysqli_close($conexion);

        break;
    case 'update_clase':
        $id = $_POST['id'];
        $count_cont = $_POST['count_cont'];
        $clase = $_POST['clase'];

        // La consulta UPDATE debe incluir la lista de columnas que quieres actualizar
        $insercion = $conexion->query("UPDATE ctb_parametros_cuentas SET id_tipo = '$count_cont', clase = '$clase' WHERE id = '$id'");

        if ($insercion) {
            echo json_encode(array("exito" => true));
        } else {
            echo json_encode(array("error" => "Error al actualizar datos: " . $conexion->error));
        }


        mysqli_close($conexion);
        break;
    case 'delete_class':
        $id = $_POST['id'];


        // La consulta UPDATE debe incluir la lista de columnas que quieres actualizar
        $querydel = $conexion->query("DELETE FROM  ctb_parametros_cuentas WHERE id = '$id'");

        if ($querydel) {
            echo json_encode(array("exito" => true));
        } else {
            echo json_encode(array("error" => "Error al actualizar datos: " . $conexion->error));
        }


        mysqli_close($conexion);
        break;
    case 'consultar_reporte':
        $id_descripcion = $_POST["id_descripcion"];
        $validar = validacionescampos([
            [$id_descripcion, "", 'No se ha detectado un identificador de reporte válido', 1],
            [$id_descripcion, "0", 'Ingrese un número de reporte mayor a 0', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        try {
            $stmt = $conexion->prepare("SELECT `nombre` FROM tb_documentos td WHERE td.id_reporte = ?");
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
    case 'config_cuenta_contable':
        break;
    case 'create_sector':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        // Desestructuración usando list
        list($name, $description) = $inputs;
        list($userCurrent, $agencies) = [$archivo[0] ?? null, $archivo[1] ?? null];

        $validar = validar_campos([
            [$name, "", 'Ingrese nombre de sector'],
            [$userCurrent, "", 'No se ha detectado el usuario creador del registro'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        if (empty($agencies)) {
            echo json_encode(["Debe agregar al menos una agencia para crear el sector", '0']);
            return;
        }
        try {
            $showmensaje = false;
            $database->openConnection();
            $database->beginTransaction();
            $ctb_sectores = array(
                'nombre' => $name,
                'descripcion' => trim($description) !== '' ? $description : null,
                'estado' => 1,
                'created_by' => $userCurrent,
                'updated_by' => $userCurrent,
                'created_at' => $hoy2,
                'updated_at' => $hoy2,
            );
            $id_ctb_sectores = $database->insert('ctb_sectores', $ctb_sectores);

            foreach ($agencies as $currentAgency) {
                $ctb_sectores_agencia = array(
                    'id_agencia' => $currentAgency,
                    'id_sector' => $id_ctb_sectores,
                );
                $database->insert('ctb_sectores_agencia', $ctb_sectores_agencia);
            }
            $database->commit();
            // $showmensaje = true;
            // throw new Exception($cierre_caja[1]);
            $mensaje = "Sector creado exitosamente";
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
    case 'update_sector':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        // Desestructuración usando list
        list($name, $description, $id_sector) = $inputs;
        list($userCurrent, $agencies) = [$archivo[0] ?? null, $archivo[1] ?? null];

        $validar = validar_campos([
            [$id_sector, "", 'Identificador no encontrado, refresque la página'],
            [$name, "", 'Ingrese nombre de sector'],
            [$userCurrent, "", 'No se ha detectado el usuario creador del registro'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        if (empty($agencies)) {
            echo json_encode(["Debe agregar al menos una agencia para crear el sector", '0']);
            return;
        }
        try {
            $showmensaje = false;
            $database->openConnection();
            $database->beginTransaction();
            $ctb_sectores = array(
                'nombre' => $name,
                'descripcion' => trim($description) !== '' ? $description : null,
                'updated_by' => $userCurrent,
                'updated_at' => $hoy2,
            );
            $database->update('ctb_sectores', $ctb_sectores, "id=?", [$id_sector]);
            $database->delete("ctb_sectores_agencia", "id_sector=?", [$id_sector]);

            foreach ($agencies as $currentAgency) {
                $ctb_sectores_agencia = array(
                    'id_agencia' => $currentAgency,
                    'id_sector' => $id_sector,
                );
                $database->insert('ctb_sectores_agencia', $ctb_sectores_agencia);
            }
            $database->commit();
            // $showmensaje = true;
            // throw new Exception($cierre_caja[1]);
            $mensaje = "Sector actualizado exitosamente";
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
    case 'delete_sector':
        $id = $_POST['ideliminar'];
        $validar = validar_campos([
            [$id, "", 'Identificador no encontrado, refresque la página'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        try {
            $showmensaje = false;
            $database->openConnection();
            $ctb_sectores = array(
                'estado' => 0,
                'deleted_by' => $idusuario,
                'deleted_at' => $hoy2,
            );
            $database->update('ctb_sectores', $ctb_sectores, "id=?", [$id]);
            $mensaje = "Sector eliminado exitosamente";
            $status = 1;
        } catch (Exception $e) {
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
    case 'create_preconfigured_game': //CREAR REGISTRO DE POLIZA DE DIARIO EN CTB_DIARIO Y CTB_MOV
        $inputs = $_POST["inputs"];
        $datospartida = $inputs[0] ?? '';
        $datosdebe = $inputs[1];
        $datoshaber = $inputs[2];
        $datoscuentas = $inputs[3];
        $datosfondos = $inputs[4];
        $datosnombres = $inputs[5];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
        // $cierre = comprobar_cierre($idusuario, $datospartida[1], $conexion);
        // if ($cierre[0] == 0) {
        //     echo json_encode([$cierre[1], '0']);
        //     return;
        // }
        $validar = validar_campos([
            [$datospartida[0], "", 'Ingrese nombre de partida a configurar'],
            [$selects[0], "", 'Seleccione un tipo de poliza'],
            [$selects[0], "0", 'Seleccione un tipo de poliza'],
            [$selects[0], 0, 'Seleccione un tipo de poliza'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        if ((is_array($datosdebe) && count($datosdebe) === 1) && (is_array($datoshaber) && count($datoshaber) === 1)) {
            $valorDebe = reset($datosdebe);
            $valorHaber = reset($datoshaber);
            if (($valorDebe === '' || $valorDebe == 0 || $valorDebe === '0') && ($valorHaber === '' || $valorHaber == 0 || $valorHaber === '0')) {
                echo json_encode(['Debe existir al menos un movimiento y en el campo del debe o haber debe existir al menos una fórmula o un monto diferente de 0', '0']);
                return;
            }
        }

        try {
            $showmensaje = false;
            $database->openConnection();
            $database->beginTransaction();
            $ctb_diario_config = array(
                'id_tipo_poliza' => $selects[0],
                'titulo' => $datospartida[0],
                'descripcion' => trim($datospartida[1]) !== '' ? $datospartida[1] : null,
                'created_by' => $idusuario,
                'updated_by' => $idusuario,
                'created_at' => $hoy2,
                'updated_at' => $hoy2,
                'estado' => 1,
            );

            $id_ctb_diario_config = $database->insert('ctb_diario_config', $ctb_diario_config);

            foreach ($datosdebe as $idKey => $datodebe) {
                $ctb_mov_config = array(
                    'no_unico' => $datosnombres[$idKey],
                    'cuenta_contable' => trim($datoscuentas[$idKey]) !== '' ? $datoscuentas[$idKey] : null,
                    'debe' => $datodebe,
                    'haber' => $datoshaber[$idKey],
                    'id_fondo' => trim($datosfondos[$idKey]) !== '' ? $datosfondos[$idKey] : null,
                    'created_by' => $idusuario,
                    'updated_by' => $idusuario,
                    'created_at' => $hoy2,
                    'updated_at' => $hoy2,
                    'id_config' => $id_ctb_diario_config
                );
                $database->insert('ctb_mov_config', $ctb_mov_config);
            }
            $database->commit();
            // $showmensaje = true;
            // throw new Exception($cierre_caja[1]);
            $mensaje = "Partida preconfigurada creada exitosamente";
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
