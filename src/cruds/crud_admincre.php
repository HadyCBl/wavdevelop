<?php
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
    return;
}
$idusuario = $_SESSION["id"];

include '../funcphp/func_gen.php';
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');

include '../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");

$condi = $_POST["condi"];

switch ($condi) {
    case 'create_gastos':
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];
        $archivo = $_POST["archivo"];

        $gastos = $inputs[0];
        $idNomenclatura = $inputs[1];
        $conexion->autocommit(false);

        // validar Repetidos
        $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM cre_tipogastos WHERE nombre_gasto='$gastos' AND id_nomenclatura=$idNomenclatura AND estado=1) AS Resultado");
        // Si la consulta fue exitosa
        $resultado = $validarRep->fetch_assoc()['Resultado'];
        if ($resultado == 1) {
            echo json_encode(['Los datos ingresados ya existen el sistema ', '0']);
            return;
        } //Fin validad repetidos

        try {

            $res = $conexion->query("INSERT INTO `cre_tipogastos` (`id_nomenclatura`, `nombre_gasto`, `estado`, `created_by`, `created_at`, `afecta_modulo`) 
            VALUES ($idNomenclatura, '$gastos', 1, '$archivo[0]', '$hoy2', $radios[0])");
            $aux = mysqli_error($conexion);

            echo $aux;

            if ($aux) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['El registro del gasto se realizo con exito. ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);

        break;

    case 'ActualizarTipoGasto':
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];
        $archivo = $_POST["archivo"];

        $conexion->autocommit(false);
        // Validar si existen cambios 
        $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM cre_tipogastos WHERE id=$inputs[0] AND nombre_gasto='$inputs[2]' AND id_nomenclatura=$inputs[1]) AS Resultado");
        // Si la consulta fue exitosa
        $resultado = $validarRep->fetch_assoc()['Resultado'];
        if ($resultado == 1) {
            echo json_encode(['Los datos que ingreso no fueron modificados por que no existe cambios.', '0']);
            return;
        } //Fin validad repetidos

        // Validar si los nuevos datos se repiten 
        $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM cre_tipogastos WHERE nombre_gasto='$inputs[2]' AND id_nomenclatura=$inputs[1]) AS Resultado");
        // Si la consulta fue exitosa
        $resultado = $validarRep->fetch_assoc()['Resultado'];
        if ($resultado == 1) {
            echo json_encode(['Los datos que está ingresando ya existe en el sistema, favor de cambiar el nombre del gasto. ', '0']);
            return;
        } //Fin validad repetidos

        try {
            $idRegis = $inputs[0];
            $idNomenclatura = $inputs[1];
            $gasto = $inputs[2];

            $res = $conexion->query("UPDATE `cre_tipogastos` SET `id_nomenclatura`=$idNomenclatura, nombre_gasto = '$gasto', updated_by = $archivo[0], updated_at ='$hoy2', afecta_modulo = $radios[0]  WHERE id = $idRegis; 
            ");

            $aux = mysqli_error($conexion);

            if ($aux) {
                echo json_encode(['Error slc', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos fuero actualizados con exito ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;

    case 'EliminarGastos':
        $id = $_POST["ideliminar"];
        $archivo = $_POST["archivo"];
        // echo json_encode([$id, $archivo]); 

        // return;
        $conexion->autocommit(false);

        try {
            //$id = $archivo;

            $res = $conexion->query("UPDATE `cre_tipogastos` SET estado = 0, deleted_by = $archivo, deleted_at='$hoy2' WHERE id =" . $id);

            $aux = mysqli_error($conexion);

            if ($aux) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['El dato fue eliminado exitosamente. ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;

    // ***********************************************************************************************************
    case 'guardarProducto':
        //[`nomPro`,`desPro`,`montoMax`,`tasaInt`,`porMo`,`diaGra`,`factordia`],
        //['selector','diascalculo','configgracia'],['opMo','opCal'],'guardarProducto','0',[])
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];

        list($nombreproducto, $descripcionprod, $montomax, $tasaint, $pormora, $diagracia, $factordia) = $inputs;
        list($fondo, $diascalculo, $configgracia) = $selects;
        list($tipmora, $tipcalculo) = $radios;

        $showmensaje = false;
        try {
            $database->openConnection();
            //VALIDACION DEL NOMBRE DEL PRODUCTO
            $result = $database->selectColumns('cre_productos', ['nombre'], 'nombre=?', [$nombreproducto]);
            if (!empty($result)) {
                $showmensaje = true;
                throw new Exception("Favor de cambiar el nombre del producto por que ya existe en el sistema");
            }

            $est = 0;
            $caracteres = '0123456789';
            $codigo;

            while ($est != 1) {
                $codigo = '';
                $max = strlen($caracteres) - 1;
                for ($i = 0; $i < 3; $i++) {
                    $codigo .= $caracteres[mt_rand(0, $max)];
                }

                $cod = intval($codigo);

                $result = $database->selectColumns('cre_productos', ['cod_producto'], 'cod_producto=?', [$codigo]);
                $est = (empty($result)) ? 1 : 0;
            }

            //si la mora es de tipo monto fijo, se guarda en tipcalculo por cada cuantos dias de atraso se va a cobrar el monto fijado
            $tipcalculo = ($tipmora == 1) ? $tipcalculo : (($factordia != '') ? $factordia : 0);

            $database->beginTransaction();
            $datos = array(
                'id_fondo' =>  $fondo,
                'cod_producto' => $codigo,
                'nombre' => $nombreproducto,
                'descripcion' => $descripcionprod,
                'monto_maximo' => $montomax,
                'tasa_interes' => $tasaint,
                'dias_calculo' => $diascalculo,
                'porcentaje_mora' => $pormora,
                'dias_de_gracias' => $diagracia,
                'tipo_mora' => $tipmora,
                'tipo_calculo' => $tipcalculo,
                'estado' => 1,
                'created_by' => $idusuario,
                'created_at' => $hoy2,
                'id_cuenta_capital' => 1,
                'id_cuenta_interes' => 1,
                'id_cuenta_mora' => 1,
                'id_cuenta_otros' => 1,
                'configgracia' => $configgracia,
            );

            $database->insert("cre_productos", $datos);
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
        echo json_encode([$mensaje, $status]);
        break;
    // ****************** ACTUALIZACIÓN DE PRODUCTOS
    case 'actualizarProducto':
        // [`nomPro`,`desPro`,`montoMax`,`tasaInt`,`porMo`,`diaGra`,`idPro`,`factordia`],
        // ['selector','diascalculo','configgracia'],['opMo','opCal'],'actualizarProducto','0',[]
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];

        list($nombreproducto, $descripcionprod, $montomax, $tasaint, $pormora, $diagracia, $idregistro, $factordia) = $inputs;
        list($fondo, $diascalculo, $configgracia) = $selects;
        list($tipmora, $tipcalculo) = $radios;

        $showmensaje = false;
        try {
            $database->openConnection();
            //VALIDACION DEL NOMBRE DEL PRODUCTO
            $result = $database->selectColumns('cre_productos', ['nombre'], 'nombre=? AND id!=?', [$nombreproducto, $idregistro]);
            if (!empty($result)) {
                $showmensaje = true;
                throw new Exception("Favor de cambiar el nombre del producto por que ya existe en el sistema");
            }

            //si la mora es de tipo monto fijo, se guarda en tipcalculo por cada cuantos dias de atraso se va a cobrar el monto fijado
            $tipcalculo = ($tipmora == 1) ? $tipcalculo : (($factordia != '') ? $factordia : 0);

            $database->beginTransaction();
            $datos = array(
                'id_fondo' =>  $fondo,
                'nombre' => $nombreproducto,
                'descripcion' => $descripcionprod,
                'monto_maximo' => $montomax,
                'tasa_interes' => $tasaint,
                'dias_calculo' => $diascalculo,
                'porcentaje_mora' => $pormora,
                'dias_de_gracias' => $diagracia,
                'tipo_mora' => $tipmora,
                'tipo_calculo' => $tipcalculo,
                'estado' => 1,
                'updated_by' => $idusuario,
                'updated_at' => $hoy2,
                'configgracia' => $configgracia,
            );

            $database->update("cre_productos", $datos, "id=?", [$idregistro]);
            $database->commit();
            // $database->rollback();
            $mensaje = "Registro actualizado correctamente";
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

    case 'eliminaProducto':
        $id = $_POST["ideliminar"];
        $archivo = $_POST["archivo"];

        // echo json_encode([$archivo, $id]); 
        // return;
        $resultado = $conexion->query("SELECT Cestado 
        FROM cremcre_meta AS creMet
        INNER JOIN cre_productos AS crePro ON creMet.CCODPRD = crePro.id
        WHERE creMet.Cestado = ('A' OR  'D' OR 'E' OR 'F' OR 'G') AND crePro.id =" . $id);

        $dato = mysqli_affected_rows($conexion);

        if ($dato > 0) {
            echo json_encode(["El producto ya cuenta con un crédito, no se puede eliminar ", '0']);
            return;
        }

        $conexion->autocommit(false);
        try {
            //$res = $conexion->query("UPDATE `cre_productos2` SET estado= 0, deleted_by = $archivo , deleted_at = '$hoy2' WHERE id =". $di);
            $res = $conexion->query("UPDATE `cre_productos` SET estado = 0, deleted_by = $archivo, deleted_at='$hoy2' WHERE id =" . $id);

            $aux = mysqli_error($conexion);

            // echo json_encode([$aux, $id]); 
            // return;

            if ($aux) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Error al eliminar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos se eliminaron con éxito. ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;

    //********************************************************************************************* */
    case 'guadarGastosProductos':

        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];
        $archivo = $_POST["archivo"];

        $calculox = ($radios[1] == 3) ? 1 : $radios[2];

        $conexion->autocommit(false);
        // validar Repetidos
        $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM cre_productos_gastos WHERE id_producto=$inputs[0] AND id_tipo_deGasto = $selects[0] AND estado=1) AS Resultado");
        $resultado = $validarRep->fetch_assoc()['Resultado'];
        if ($resultado == 1) {
            echo json_encode(['Al producto ya se le asignó el tipo de gasto seleccionado, favor de cambiarlo por otro tipo de gasto.', '0']);
            return;
        } //Fin validad repetidos

        try {
            $res = $conexion->query("INSERT INTO `cre_productos_gastos` (`id_producto`, `id_tipo_deGasto`, `tipo_deCobro`, `tipo_deMonto`, `monto`, `estado`, `created_by`, `created_at`, `calculox`) 
            VALUE ($inputs[0],$selects[0],$radios[0],$radios[1],$inputs[1],1,$archivo[0],'$hoy2',$calculox);");
            //VALUE ($selects[0],$inputs[0],$radios[0],$radios[1],$inputs[1],1,$archivo[0],'$hoy2');");
            $aux = mysqli_error($conexion);

            //echo $aux;

            if ($aux) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos se registraron con éxito. ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);

        break;

    case 'actGasPro':
        //************************************ */
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];
        $archivo = $_POST["archivo"];

        $conexion->autocommit(false);

        $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM cre_productos_gastos WHERE id_producto=$inputs[1] AND id_tipo_deGasto = $selects[0] AND id != $inputs[0]) AS Resultado");
        $resultado = $validarRep->fetch_assoc()['Resultado'];
        if ($resultado == 1) {
            echo json_encode(['Al producto ya se le asignó el tipo de gasto seleccionado, favor de cambiarlo por otro tipo de gasto.', '0']);
            return;
        } //Fin validad repetidos
        $calculox = ($radios[1] == 3) ? 1 : $radios[2];
        try {
            $res = $conexion->query("UPDATE cre_productos_gastos SET id_producto = " . $inputs[1] . ", id_tipo_deGasto = " . $selects[0] . ", tipo_deCobro = " . $radios[0] . ", tipo_deMonto = " . $radios[1] . ", monto = " . $inputs[2] . ", calculox = " . $calculox . ", updated_by = " . $archivo[0] . ", updated_at = '" . $hoy2 . "' WHERE id =" . $inputs[0]);
            //$res = $conexion->query("UPDATE cre_productos_gastos SET id_producto = ".$selects[0].", id_tipo_deGasto = ".$inputs[1].", tipo_deCobro = ".$radios[0].", tipo_deMonto = ".$radios[1].", monto = ".$inputs[2].", updated_by = ".$archivo[0].", updated_at = '".$hoy2."' WHERE id =" . $inputs[0]);

            $aux = mysqli_error($conexion);

            if ($aux) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos se actualizaron con éxito. ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        //************************************ */
        break;

    case 'elimnarGasPro':
        $id = $_POST["ideliminar"];
        $archivo = $_POST["archivo"];

        $conexion->autocommit(false);

        try {
            //$res = $conexion->query("UPDATE `cre_productos2` SET estado= 0, deleted_by = $archivo , deleted_at = '$hoy2' WHERE id =". $di);
            $res = $conexion->query("UPDATE cre_productos_gastos SET estado = 0, deleted_by = " . $archivo . ", deleted_at = '" . $hoy2 . "' WHERE id =" . $id);

            $aux = mysqli_error($conexion);

            // echo json_encode([$aux, $id]); 
            // return;

            if ($aux) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo json_encode(['Error al eliminar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos se eliminaron con éxito. ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'create_parametrizacion_creditos':
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        //VALIDACIONES DE LOS CAMPOS
        $validar = validar_campos([
            [$inputs[0], "", 'Debe seleccionar un producto a actualizar'],
            [$inputs[1], "", 'Debe seleccionar un producto a actualizar'],
            [$inputs[3], "", 'Debe seleccionar un cuenta contable para capital'],
            [$inputs[4], "", 'Debe seleccionar un cuenta contable para capital'],
            [$inputs[5], "", 'Debe seleccionar un cuenta contable para capital'],
            [$inputs[6], "", 'Debe seleccionar una cuenta contable para interés'],
            [$inputs[7], "", 'Debe seleccionar una cuenta contable para interés'],
            [$inputs[8], "", 'Debe seleccionar una cuenta contable para interés'],
            [$inputs[9], "", 'Debe seleccionar una cuenta contable para mora'],
            [$inputs[10], "", 'Debe seleccionar una cuenta contable para mora'],
            [$inputs[11], "", 'Debe seleccionar una cuenta contable para mora'],
            [$inputs[12], "", 'Debe seleccionar una cuenta contable para otros'],
            [$inputs[13], "", 'Debe seleccionar una cuenta contable para otros'],
            [$inputs[14], "", 'Debe seleccionar una cuenta contable para otros'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        //validar capital
        if ($inputs[3] < 1) {
            echo json_encode(["Debe digitar un cuenta contable válida para capital", '0']);
            return;
        }
        //validar interes
        if ($inputs[6] < 1) {
            echo json_encode(["Debe digitar un cuenta contable válida para interés", '0']);
            return;
        }
        //validar mora
        if ($inputs[9] < 1) {
            echo json_encode(["Debe digitar un cuenta contable válida para mora", '0']);
            return;
        }
        //validar mora
        if ($inputs[12] < 1) {
            echo json_encode(["Debe digitar un cuenta contable válida para otros", '0']);
            return;
        }

        $conexion->autocommit(false);
        try {
            $res = $conexion->query("UPDATE `cre_productos` SET  id_cuenta_capital='$inputs[3]', id_cuenta_interes='$inputs[6]', id_cuenta_mora='$inputs[9]', id_cuenta_otros='$inputs[12]', updated_by='$archivo[0]', updated_at='$hoy2' WHERE `id`='$inputs[0]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Fallo al actualizar la parametrización', '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                echo json_encode(['Parametrización actualizado satisfactoriamente', '1']);
            } else {
                $conexion->rollback();
                echo json_encode(['Parametrización no actualizado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la actualizacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'update_dias_laborales':
        $archivo = $_POST["archivo"];
        $conexion->autocommit(false);
        try {

            $estado = ($archivo[1] == 1) ? 1 : 0;
            //consultar si este dia tiene es como ajuste de otro
            if ($estado == 0) {
                $res = $conexion->query("SELECT EXISTS(SELECT * FROM tb_dias_laborales tdl WHERE tdl.laboral = 0 AND id_dia_ajuste ='$archivo[0]' AND producto=0) AS resultado");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    $conexion->rollback();
                    echo json_encode(['Fallo al consultar disponibilidad dia', '0']);
                    return;
                }
                if (!$res) {
                    $conexion->commit();
                    echo json_encode(['Error al consultar disponbilidad', '1']);
                }
                $resultado = $res->fetch_assoc()['resultado'];
                if ($resultado == 1) {
                    echo json_encode(['El dia que quiere marcar como no laborable no se puede completar, porque esta asignado como dia de ajuste, primero quitelo como dia de ajuste y podra realizar la operación', '0']);
                    return;
                }
                //Consultar que dia por default se puede asignar
                $banderaant = false;
                $banderades = false;
                $k = 1;
                $idaux = $archivo[0];
                $diasajuste = array();
                $idant = $archivo[0];
                $iddes = $archivo[0];
                while ($k < 4) {
                    // validar rangos
                    $idant = $idant - 1;
                    $iddes = $iddes + 1;

                    if ($idant == 0) {
                        $idant = 7;
                    }

                    if ($iddes == 8) {
                        $iddes = 1;
                    }
                    if ($banderaant == false) {

                        $res = $conexion->query("SELECT tdl.id AS id, tdl.dia AS dia FROM tb_dias_laborales tdl WHERE (tdl.id = $idant) AND tdl.laboral = 1");
                        $aux = mysqli_error($conexion);
                        if ($aux) {
                            $conexion->rollback();
                            echo json_encode(['Fallo al consultar dia de ajuste', '0']);
                            return;
                        }
                        if (!$res) {
                            $conexion->commit();
                            echo json_encode(['Error al consultar dia de ajuste', '1']);
                        }
                        //pasar los datos al array
                        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
                            $diasajuste[] = $row;
                            $banderaant = true;
                        }
                    }
                    if ($banderades == false) {
                        $res = $conexion->query("SELECT tdl.id AS id, tdl.dia AS dia FROM tb_dias_laborales tdl WHERE (tdl.id = $iddes) AND tdl.laboral = 1");
                        $aux = mysqli_error($conexion);
                        if ($aux) {
                            $conexion->rollback();
                            echo json_encode(['Fallo al consultar dia de ajuste', '0']);
                            return;
                        }
                        if (!$res) {
                            $conexion->commit();
                            echo json_encode(['Error al consultar dia de ajuste', '1']);
                        }
                        //pasar los datos al array
                        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
                            $diasajuste[] = $row;
                            $banderades = true;
                        }
                    }
                    $k = ($banderaant && $banderades) ? 4 : $k;
                    $k++;
                }
                if ($banderaant == false && $banderades == false) {
                    $conexion->rollback();
                    echo json_encode(['No se encontro un dia de ajuste disponible, por lo que no puede completar esta acción', '0']);
                    return;
                }
                //asignar el dia por default
                $res = $conexion->query("UPDATE tb_dias_laborales SET id_dia_ajuste = " . $diasajuste[0]['id'] . " WHERE `id`='$archivo[0]'");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    $conexion->rollback();
                    echo json_encode(['Fallo al asignar dia de ajuste', '0']);
                    return;
                }
                if (!$res) {
                    $conexion->rollback();
                    echo json_encode(['Error al asignar dia de ajuste', '0']);
                    return;
                }
            }
            // CAMBIA EL ESTADO DEL DIA DE AJUSTE
            $res = $conexion->query("UPDATE tb_dias_laborales SET laboral = $estado WHERE `id`='$archivo[0]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Fallo al actualizar la parametrización', '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                echo json_encode(['Dia actualizado satisfactoriamente', '1']);
            } else {
                $conexion->rollback();
                echo json_encode(['Dia no actualizado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la actualizacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'update_dia_ajuste':
        $archivo = $_POST["archivo"];
        $conexion->autocommit(false);
        try {
            //Consultar que dia por default se puede asignar
            $banderaant = false;
            $banderades = false;
            $k = 1;
            $idaux = $archivo[1];
            $diasajuste = array();
            $idant = $archivo[1];
            $iddes = $archivo[1];
            while ($k < 4) {
                // validar rangos
                $idant = $idant - 1;
                $iddes = $iddes + 1;

                if ($idant == 0) {
                    $idant = 7;
                }

                if ($iddes == 8) {
                    $iddes = 1;
                }
                if ($banderaant == false) {

                    $res = $conexion->query("SELECT tdl.id AS id, tdl.dia AS dia FROM tb_dias_laborales tdl WHERE (tdl.id = $idant) AND tdl.laboral = 1");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        $conexion->rollback();
                        echo json_encode(['Fallo al consultar dia de ajuste', '0']);
                        return;
                    }
                    if (!$res) {
                        $conexion->commit();
                        echo json_encode(['Error al consultar dia de ajuste', '1']);
                    }
                    //pasar los datos al array
                    while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
                        $diasajuste[] = $row;
                        $banderaant = true;
                    }
                }
                if ($banderades == false) {
                    $res = $conexion->query("SELECT tdl.id AS id, tdl.dia AS dia FROM tb_dias_laborales tdl WHERE (tdl.id = $iddes) AND tdl.laboral = 1");
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        $conexion->rollback();
                        echo json_encode(['Fallo al consultar dia de ajuste', '0']);
                        return;
                    }
                    if (!$res) {
                        $conexion->commit();
                        echo json_encode(['Error al consultar dia de ajuste', '1']);
                    }
                    //pasar los datos al array
                    while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
                        $diasajuste[] = $row;
                        $banderades = true;
                    }
                }
                $k = ($banderaant && $banderades) ? 4 : $k;
                $k++;
            }
            if ($banderaant == false && $banderades == false) {
                $conexion->rollback();
                echo json_encode(['No se encontro un dia de ajuste disponible, por lo que no puede completar esta acción', '0']);
                return;
            }

            $bandera = false;
            foreach ($diasajuste as $key => $value) {
                if ($value["id"] == $archivo[0]) {
                    $bandera = true;
                }
            }
            if ($bandera) {
                // asignar el dia por default
                $res = $conexion->query("UPDATE tb_dias_laborales SET id_dia_ajuste = " . $archivo[0] . " WHERE `id`='$archivo[1]'");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    $conexion->rollback();
                    echo json_encode(['Fallo al asignar dia de ajuste', '0']);
                    return;
                }
                if (!$res) {
                    $conexion->rollback();
                    echo json_encode(['Error al asignar dia de ajuste', '0']);
                    return;
                }
                $conexion->commit();
                echo json_encode(['Dia de ajuste actualizado satisfactoriamente', '1']);
            } else {
                $conexion->rollback();
                echo json_encode(['El dia que quiere asignar ya no es posible', '0']);
                return;
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la actualizacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    //esto es para que sea automatico el numero de recibo
    case 'update_check_no_recibo':
        $config_name = $_POST['config_name'];
        $estado = $_POST['estado'];

        $sql = "UPDATE tb_configCre SET estado = ? WHERE config_name = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $estado, $config_name);

        if ($stmt->execute()) {
            echo json_encode(['Successful', '1']);
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conexion->close();
        break;
    //esto es para bloquear el campo de numero de recibo
    case 'update_auto_no_recib':
        $config_name = $_POST['config_name'];
        $estado = $_POST['estado'];

        $sql = "UPDATE tb_configCre SET estado = ? WHERE config_name = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $estado, $config_name);

        if ($stmt->execute()) {
            echo json_encode(['Successful', '1']);
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conexion->close();
        break;
    case 'update_check_fecha':
        $config_name = $_POST['config_name'];
        $estado = $_POST['estado'];

        $sql = "UPDATE tb_configCre SET estado = ? WHERE config_name = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $estado, $config_name);

        if ($stmt->execute()) {
            echo json_encode(['Successful', '1']);
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conexion->close();
        break;
    //esto es para bloquear el campo de numero de recibo
    case 'update_check_capital':
        $config_name = $_POST['config_name'];
        $estado = $_POST['estado'];

        $sql = "UPDATE tb_configCre SET estado = ? WHERE config_name = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $estado, $config_name);

        if ($stmt->execute()) {
            echo json_encode(['Successful', '1']);
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conexion->close();
        break;
    case 'update_check_interes':
        $config_name = $_POST['config_name'];
        $estado = $_POST['estado'];

        $sql = "UPDATE tb_configCre SET estado = ? WHERE config_name = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $estado, $config_name);

        if ($stmt->execute()) {
            echo json_encode(['Successful', '1']);
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conexion->close();
        break;
    case 'update_check_mora':
        $config_name = $_POST['config_name'];
        $estado = $_POST['estado'];

        $sql = "UPDATE tb_configCre SET estado = ? WHERE config_name = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $estado, $config_name);

        if ($stmt->execute()) {
            echo json_encode(['Successful', '1']);
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conexion->close();
        break;
    case 'update_check_otros':
        $config_name = $_POST['config_name'];
        $estado = $_POST['estado'];

        $sql = "UPDATE tb_configCre SET estado = ? WHERE config_name = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("is", $estado, $config_name);

        if ($stmt->execute()) {
            echo json_encode(['Successful', '1']);
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
        $conexion->close();
        break;
    case 'guardarProductoPublico':
        // Debug completo


        // Extraer datos de los arrays que envía la función obtiene()
        $inputs = $_POST['inputs'] ?? [];
        $selects = $_POST['selects'] ?? [];

        error_log("Inputs array: " . print_r($inputs, true));
        error_log("Selects array: " . print_r($selects, true));

        // Basándome en tu llamada: obtiene(['token','nomPro','desPro'],['published'],...)
        // inputs[0] = valor del token
        // inputs[1] = valor de nomPro 
        // inputs[2] = valor de desPro
        // selects[0] = valor de published

        $nombre = isset($inputs[1]) ? $inputs[1] : '';           // nomPro
        $descripcion = isset($inputs[2]) ? $inputs[2] : '';      // desPro  
        $published = isset($selects[0]) ? $selects[0] : '0';     // published

        error_log("Valores extraídos:");
        error_log("- Token (inputs[0]): '" . (isset($inputs[0]) ? $inputs[0] : 'NO DEFINIDO') . "'");
        error_log("- Nombre (inputs[1]): '" . $nombre . "'");
        error_log("- Descripción (inputs[2]): '" . $descripcion . "'");
        error_log("- Published (selects[0]): '" . $published . "'");
        error_log("- Nombre vacío?: " . (empty(trim($nombre)) ? 'SÍ' : 'NO'));

        $showmensaje = false;
        try {
            $database->openConnection();

            // VALIDACIÓN DE DATOS REQUERIDOS
            if (empty(trim($nombre))) {
                $showmensaje = true;
                error_log("ERROR: Nombre vacío después de extracción de array");
                throw new Exception("El nombre del producto es obligatorio");
            }

            if (strlen(trim($nombre)) < 3) {
                $showmensaje = true;
                error_log("ERROR: Nombre muy corto: " . strlen(trim($nombre)) . " caracteres");
                throw new Exception("El nombre del producto debe tener al menos 3 caracteres");
            }

            // VALIDACIÓN DEL NOMBRE DEL PRODUCTO
            $result = $database->selectColumns('cre_prod_public', ['nombre'], 'nombre=?', [trim($nombre)]);
            if (!empty($result)) {
                $showmensaje = true;
                error_log("ERROR: Nombre duplicado: " . trim($nombre));
                throw new Exception("El nombre del producto ya existe en el sistema, favor de utilizar uno diferente");
            }

            $database->beginTransaction();

            $datos = array(
                'nombre' => trim($nombre),
                'descripcion' => trim($descripcion ?? ''),
                'published' => intval($published),
                'created_at' => $hoy2,
                'updated_at' => $hoy2
            );

            error_log("Datos a insertar: " . print_r($datos, true));

            $database->insert("cre_prod_public", $datos);
            $database->commit();

            error_log("✅ Producto guardado exitosamente");
            $mensaje = "Producto guardado correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            error_log("❌ Error: " . $e->getMessage());
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        error_log("Respuesta final: " . json_encode([$mensaje, $status]));


        echo json_encode([$mensaje, $status]);
        break;

    // TAMBIÉN ACTUALIZAR EL CASO DE ACTUALIZACIÓN
    case 'actualizarProductoPublico':

        error_log("POST: " . print_r($_POST, true));

        $inputs = $_POST['inputs'] ?? [];
        $selects = $_POST['selects'] ?? [];

        // Para actualizar: obtiene(['token','idPro','nomPro','desPro'],['published'],...)
        // inputs[0] = token, inputs[1] = idPro, inputs[2] = nomPro, inputs[3] = desPro
        // selects[0] = published

        $id = isset($inputs[1]) ? $inputs[1] : '';               // idPro
        $nombre = isset($inputs[2]) ? $inputs[2] : '';           // nomPro
        $descripcion = isset($inputs[3]) ? $inputs[3] : '';      // desPro
        $published = isset($selects[0]) ? $selects[0] : '0';     // published

        error_log("Valores extraídos para actualización:");
        error_log("- ID (inputs[1]): '" . $id . "'");
        error_log("- Nombre (inputs[2]): '" . $nombre . "'");
        error_log("- Descripción (inputs[3]): '" . $descripcion . "'");
        error_log("- Published (selects[0]): '" . $published . "'");

        $showmensaje = false;
        try {
            $database->openConnection();

            // VALIDAR QUE EL PRODUCTO EXISTE
            $result = $database->selectColumns('cre_prod_public', ['id'], 'id=?', [$id]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("El producto no existe en el sistema");
            }

            // VALIDACIÓN DE DATOS REQUERIDOS
            if (empty(trim($nombre))) {
                $showmensaje = true;
                throw new Exception("El nombre del producto es obligatorio");
            }

            if (strlen(trim($nombre)) < 3) {
                $showmensaje = true;
                throw new Exception("El nombre del producto debe tener al menos 3 caracteres");
            }

            // VALIDACIÓN DEL NOMBRE DEL PRODUCTO (que no exista en otro registro)
            $result = $database->selectColumns('cre_prod_public', ['id'], 'nombre=? AND id!=?', [trim($nombre), $id]);
            if (!empty($result)) {
                $showmensaje = true;
                throw new Exception("El nombre del producto ya existe en otro registro, favor de utilizar uno diferente");
            }

            $database->beginTransaction();

            $datos = array(
                'nombre' => trim($nombre),
                'descripcion' => trim($descripcion ?? ''),
                'published' => intval($published),
                'updated_at' => $hoy2
            );

            $database->update("cre_prod_public", $datos, "id=?", [$id]);
            $database->commit();

            $mensaje = "Producto actualizado correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;


    // ****************** CAMBIAR ESTADO DE PUBLICACIÓN
    case 'cambiarEstadoProductoPublico':

        error_log("POST completo: " . print_r($_POST, true));

        // Los datos pueden venir de dos formas:
        // 1. Como arrays desde obtiene()
        // 2. Como campos directos desde elementos temporales

        $id = '';
        $published = '0';

        // Verificar si vienen arrays de obtiene()
        if (isset($_POST['inputs']) && is_array($_POST['inputs'])) {
            $inputs = $_POST['inputs'];
            $selects = $_POST['selects'] ?? [];

            error_log("Inputs: " . print_r($inputs, true));
            error_log("Selects: " . print_r($selects, true));

            // Para cambiarEstado: obtiene(['token', 'tempId'], ['tempPublished'], ...)
            // inputs[0] = token, inputs[1] = tempId
            // selects[0] = tempPublished

            $id = isset($inputs[1]) ? $inputs[1] : '';
            $published = isset($selects[0]) ? $selects[0] : '0';

            error_log("Extraído de arrays - ID: '$id', Published: '$published'");
        }
        // Fallback: buscar en campos directos
        else {
            $id = $_POST["tempId"] ?? $_POST["id"] ?? '';
            $published = $_POST["tempPublished"] ?? $_POST["published"] ?? '0';

            error_log("Extraído de campos directos - ID: '$id', Published: '$published'");
        }

        $showmensaje = false;
        try {
            $database->openConnection();

            if (empty($id)) {
                $showmensaje = true;
                throw new Exception("ID del producto no proporcionado");
            }

            // VALIDAR QUE EL PRODUCTO EXISTE
            $result = $database->selectColumns('cre_prod_public', ['id', 'nombre'], 'id=?', [$id]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("El producto no existe en el sistema");
            }

            $database->beginTransaction();

            $datos = array(
                'published' => intval($published),
                'updated_at' => $hoy2
            );

            $database->update("cre_prod_public", $datos, "id=?", [$id]);
            $database->commit();

            $mensaje = $published == 1 ? "Producto publicado correctamente" : "Producto despublicado correctamente";
            $status = 1;
            error_log("✅ Estado cambiado exitosamente");
        } catch (Exception $e) {
            $database->rollback();
            error_log("❌ Error: " . $e->getMessage());
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    // ****************** ELIMINAR PRODUCTO PÚBLICO
    case 'eliminarProductoPublico':

        error_log("POST completo: " . print_r($_POST, true));

        $id = '';

        // Verificar si vienen arrays de obtiene()
        if (isset($_POST['inputs']) && is_array($_POST['inputs'])) {
            $inputs = $_POST['inputs'];

            error_log("Inputs: " . print_r($inputs, true));

            // Para eliminar: obtiene(['token', 'tempIdEliminar'], [], ...)
            // inputs[0] = token, inputs[1] = tempIdEliminar

            $id = isset($inputs[1]) ? $inputs[1] : '';

            error_log("Extraído de arrays - ID: '$id'");
        }
        // Fallback: buscar en campos directos
        else {
            $id = $_POST["tempIdEliminar"] ?? $_POST["id"] ?? '';

            error_log("Extraído de campos directos - ID: '$id'");
        }

        $showmensaje = false;
        try {
            $database->openConnection();

            if (empty($id)) {
                $showmensaje = true;
                throw new Exception("ID del producto no proporcionado");
            }

            // VALIDAR QUE EL PRODUCTO EXISTE
            $result = $database->selectColumns('cre_prod_public', ['id', 'nombre'], 'id=?', [$id]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("El producto no existe en el sistema");
            }

            // AQUÍ PUEDES AGREGAR VALIDACIONES ADICIONALES
            // Por ejemplo, verificar si el producto está siendo usado en otras tablas
            /*
        $enUso = $database->selectColumns('otra_tabla', ['id'], 'id_producto_publico=?', [$id]);
        if (!empty($enUso)) {
            $showmensaje = true;
            throw new Exception("No se puede eliminar el producto porque está siendo utilizado en el sistema");
        }
        */

            $database->beginTransaction();

            $database->delete("cre_prod_public", "id=?", [$id]);
            $database->commit();

            $mensaje = "Producto eliminado correctamente";
            $status = 1;
            error_log("✅ Producto eliminado exitosamente");
        } catch (Exception $e) {
            $database->rollback();
            error_log("❌ Error: " . $e->getMessage());
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;
    // ****************** GUARDAR SERVICIO PÚBLICO
    case 'guardarServicioPublico':
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $rutaImagen = '';

        $showmensaje = false;
        try {
            $database->openConnection();

            if (empty($titulo)) {
                $showmensaje = true;
                throw new Exception("El título del servicio es obligatorio");
            }

            if (strlen($titulo) < 3) {
                $showmensaje = true;
                throw new Exception("El título debe tener al menos 3 caracteres");
            }

            if (empty($descripcion)) {
                $showmensaje = true;
                throw new Exception("La descripción del servicio es obligatoria");
            }

            // Verificar título único
            $result = $database->selectColumns('services_public', ['title'], 'title=?', [$titulo]);
            if (!empty($result)) {
                $showmensaje = true;
                throw new Exception("El título del servicio ya existe");
            }

            // Subir imagen si existe
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $rutaImagen = subirImagen($_FILES['imagen'], 'uploads/servicios/');
            }

            $database->beginTransaction();

            $datos = [
                'title' => $titulo,
                'body' => $descripcion,
                'image' => $rutaImagen,
                'created_at' => $hoy2,
                'updated_at' => $hoy2
            ];

            $database->insert("services_public", $datos);
            $database->commit();

            $mensaje = "Servicio guardado correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!empty($rutaImagen) && file_exists($rutaImagen)) unlink($rutaImagen);

            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__);
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: código($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    // ****************** ACTUALIZAR SERVICIO PÚBLICO
    case 'actualizarServicioPublico':
        $id = trim($_POST['id'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $imagenActual = trim($_POST['imagenActual'] ?? '');

        $showmensaje = false;
        try {
            $database->openConnection();

            // Verificar que existe
            $result = $database->selectColumns('services_public', ['id', 'image'], 'id=?', [$id]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("El servicio no existe");
            }

            $imagenAnterior = $result[0]['image'] ?? '';

            if (empty($titulo) || strlen($titulo) < 3) {
                $showmensaje = true;
                throw new Exception("Título requerido (mínimo 3 caracteres)");
            }

            if (empty($descripcion)) {
                $showmensaje = true;
                throw new Exception("La descripción es obligatoria");
            }

            // Verificar título único (excepto el actual)
            $result = $database->selectColumns('services_public', ['id'], 'title=? AND id!=?', [$titulo, $id]);
            if (!empty($result)) {
                $showmensaje = true;
                throw new Exception("El título ya existe en otro registro");
            }

            $rutaImagen = $imagenActual;

            // Nueva imagen si se subió
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $rutaImagen = subirImagen($_FILES['imagen'], 'uploads/servicios/');
            }

            $database->beginTransaction();

            $datos = [
                'title' => $titulo,
                'body' => $descripcion,
                'image' => $rutaImagen,
                'updated_at' => $hoy2
            ];

            $database->update("services_public", $datos, "id=?", [$id]);
            $database->commit();

            // Eliminar imagen anterior si cambió
            if ($rutaImagen !== $imagenAnterior && !empty($imagenAnterior) && file_exists($imagenAnterior)) {
                unlink($imagenAnterior);
            }

            $mensaje = "Servicio actualizado correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__);
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: código($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    // ****************** ELIMINAR SERVICIO PÚBLICO
    case 'eliminarServicioPublico':
        // Obtener ID desde arrays de obtiene() o directo
        $inputs = $_POST['inputs'] ?? [];
        $id = isset($inputs[1]) ? $inputs[1] : ($_POST["tempIdEliminarSer"] ?? $_POST["id"] ?? '');

        $showmensaje = false;
        try {
            $database->openConnection();

            if (empty($id)) {
                $showmensaje = true;
                throw new Exception("ID del servicio no proporcionado");
            }

            // Obtener datos del servicio
            $result = $database->selectColumns('services_public', ['id', 'image'], 'id=?', [$id]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("El servicio no existe");
            }

            $imagenAEliminar = $result[0]['image'] ?? '';

            $database->beginTransaction();
            $database->delete("services_public", "id=?", [$id]);
            $database->commit();

            // Eliminar imagen física
            if (!empty($imagenAEliminar) && file_exists($imagenAEliminar)) {
                unlink($imagenAEliminar);
            }

            $mensaje = "Servicio eliminado correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__);
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: código($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    case 'create_region':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
            return;
        }

        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        // Desestructuración usando list
        list($name, $id_encargado) = $inputs;
        list($userCurrent, $agencies) = [$archivo[0] ?? null, $archivo[1] ?? null];

        // Validar campos requeridos
        $validar = validar_campos([
            [$name, "", 'Ingrese nombre de región'],
            [$id_encargado, "", 'Seleccione un analista encargado'],
            [$userCurrent, "", 'No se ha detectado el usuario creador del registro'],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        // Validar que haya al menos una agencia
        if (empty($agencies)) {
            echo json_encode(["Debe agregar al menos una agencia para crear la región", '0']);
            return;
        }

        try {
            $showmensaje = false;
            $database->openConnection();
            $database->beginTransaction();

            // Insertar región
            $cre_regiones = array(
                'nombre' => trim($name),
                'id_encargado' => $id_encargado,
                'estado' => 1,
                'created_by' => $userCurrent,
                'updated_by' => $userCurrent,
                'created_at' => $hoy2,
                'updated_at' => $hoy2,
            );

            $id_region = $database->insert('cre_regiones', $cre_regiones);

            // Insertar agencias relacionadas
            foreach ($agencies as $currentAgency) {
                $cre_regiones_agencias = array(
                    'id_region' => $id_region,
                    'id_agencia' => $currentAgency,
                );
                $database->insert('cre_regiones_agencias', $cre_regiones_agencias);
            }

            $database->commit();
            $mensaje = "Región creada exitosamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
    break;

    case 'update_region':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
            return;
        }

        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        // Desestructuración usando list
        list($name, $id_encargado, $id_region) = $inputs;
        list($userCurrent, $agencies) = [$archivo[0] ?? null, $archivo[1] ?? null];

        // Validar campos requeridos
        $validar = validar_campos([
            [$id_region, "", 'Identificador no encontrado, refresque la página'],
            [$name, "", 'Ingrese nombre de región'],
            [$id_encargado, "", 'Seleccione un analista encargado'],
            [$userCurrent, "", 'No se ha detectado el usuario editor del registro'],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        // Validar que haya al menos una agencia
        if (empty($agencies)) {
            echo json_encode(["Debe agregar al menos una agencia para la región", '0']);
            return;
        }

        try {
            $showmensaje = false;
            $database->openConnection();
            $database->beginTransaction();

            // Actualizar región
            $cre_regiones = array(
                'nombre' => trim($name),
                'id_encargado' => $id_encargado,
                'updated_by' => $userCurrent,
                'updated_at' => $hoy2,
            );

            $database->update('cre_regiones', $cre_regiones, "id=?", [$id_region]);

            // Eliminar agencias existentes y volver a insertar
            $database->delete("cre_regiones_agencias", "id_region=?", [$id_region]);

            // Insertar nuevas agencias
            foreach ($agencies as $currentAgency) {
                $cre_regiones_agencias = array(
                    'id_region' => $id_region,
                    'id_agencia' => $currentAgency,
                );
                $database->insert('cre_regiones_agencias', $cre_regiones_agencias);
            }

            $database->commit();
            $mensaje = "Región actualizada exitosamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
    break;

    case 'delete_region':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
            return;
        }

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

            // Soft delete - cambiar estado a 0
            $cre_regiones = array(
                'estado' => 0,
                'deleted_by' => $idusuario,
                'deleted_at' => $hoy2,
            );

            $database->update('cre_regiones', $cre_regiones, "id=?", [$id]);
            $mensaje = "Región eliminada exitosamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
    break;

    case 'update_estado_region':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesión expirada, vuelve a iniciar sesión e intente nuevamente', '0']);
            return;
        }

        $archivo = $_POST["archivo"] ?? [];
        $id = $archivo[0] ?? null;
        $estado = $archivo[1] ?? null;

        // Debug logging
        error_log("update_estado_region - ID: $id, Estado: $estado");

        $validar = validar_campos([
            [$id, "", 'Identificador no encontrado'],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        try {
            $showmensaje = false;
            $database->openConnection();

            // Actualizar estado
            $cre_regiones = array(
                'estado' => $estado,
                'updated_by' => $idusuario,
                'updated_at' => $hoy2,
            );

            $database->update('cre_regiones', $cre_regiones, "id=?", [$id]);
            $mensaje = ($estado == 1) ? "Región activada exitosamente" : "Región desactivada exitosamente";
            $status = '1';

            error_log("update_estado_region exitoso - Respuesta: " . json_encode([$mensaje, $status]));
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = '0';
            error_log("update_estado_region error: " . $e->getMessage());
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
    break;



        // ****************** FUNCIÓN AUXILIAR PARA SUBIR IMÁGENES
        function subirImagen($archivo, $directorio = 'uploads/')
        {
            // Crear directorio si no existe
            if (!is_dir($directorio)) {
                mkdir($directorio, 0755, true);
            }

            // Validaciones básicas
            $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($archivo['type'], $tiposPermitidos)) {
                throw new Exception("Tipo de archivo no permitido");
            }

            if ($archivo['size'] > 2 * 1024 * 1024) { // 2MB
                throw new Exception("Archivo muy grande (máximo 2MB)");
            }

            // Generar nombre único
            $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            $nombreArchivo = 'servicio_' . time() . '_' . uniqid() . '.' . strtolower($extension);
            $rutaCompleta = $directorio . $nombreArchivo;

            // Mover archivo
            if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
                throw new Exception("Error al subir la imagen");
            }

            return $rutaCompleta;
        }
}

//FUNCION PARA REALIZAR VALIDACIONES
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
