<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

use App\Generic\Agencia;
use App\Generic\CacheManager;
use Micro\Helpers\Log;
use Micro\Generic\Validator;

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
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
//+++++++++
// session_start();
// include '../../includes/BD_con/db_con.php';
// mysqli_set_charset($conexion, 'utf8');
// mysqli_set_charset($general, 'utf8');
// date_default_timezone_set('America/Guatemala');
// $hoy2 = date("Y-m-d H:i:s");
// $hoy = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {
    case 'create_modulo':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        //validaciones de los input's
        if ($inputs[0] == "") {
            echo json_encode(['Debe llenar el campo descripción', '0']);
            return;
        }

        if ($inputs[1] == "") {
            echo json_encode(['Debe llenar el campo icono', '0']);
            return;
        }

        if ($inputs[2] == "") {
            echo json_encode(['Debe llenar el campo Ruta', '0']);
            return;
        }

        if ($selects[0] == "0") {
            echo json_encode(['Debe selecionar una rama', '0']);
            return;
        }
        if ($inputs[3] == "") {
            echo json_encode(['Debe llenar el campo orden', '0']);
            return;
        }
        if ($inputs[3] < 1) {
            echo json_encode(['El campo orden debe ser un número mayor a 0', '0']);
            return;
        }
        //insercion en la base de datos
        //realizar la insercion
        $res = $general->query("INSERT INTO `tb_modulos`(`descripcion`, `icon`, `ruta`, `rama`, `orden`, `estado`) 
            VALUES ('$inputs[0]','$inputs[1]','$inputs[2]','$selects[0]', '$inputs[3]', 1)");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de registrar los datos', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro satisfactorio', '1']);
        } else {
            echo json_encode(['Registro no ingresado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'update_modulo':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        //validaciones de los input's
        if ($inputs[3] == "") {
            echo json_encode(['No ha seleccionado un registro a editar', '0']);
            return;
        }
        if ($inputs[0] == "") {
            echo json_encode(['Debe llenar el campo descripción', '0']);
            return;
        }

        if ($inputs[1] == "") {
            echo json_encode(['Debe llenar el campo icono', '0']);
            return;
        }

        if ($inputs[2] == "") {
            echo json_encode(['Debe llenar el campo Ruta', '0']);
            return;
        }

        if ($selects[0] == "0") {
            echo json_encode(['Debe selecionar una rama', '0']);
            return;
        }
        if ($inputs[4] == "") {
            echo json_encode(['Debe llenar el campo orden', '0']);
            return;
        }
        if ($inputs[4] < 1) {
            echo json_encode(['El campo orden debe ser un número mayor a 0', '0']);
            return;
        }
        //realizar la insercion
        $res = $general->query("UPDATE `tb_modulos` set `descripcion`= '$inputs[0]', `icon`= '$inputs[1]', `ruta`= '$inputs[2]', `orden`= $inputs[4], `rama`= '$selects[0]' WHERE id='$inputs[3]'");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de actualizar los datos', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro actualizado satisfactoriamente', '1']);
        } else {
            echo json_encode(['Registro no actualizado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'delete_modulo':
        $id = $_POST['ideliminar'];
        $res = $general->query("UPDATE `tb_modulos` set `estado`= 0 WHERE id='$id'");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de eliminar el registro', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro eliminado satisfactoriamente', '1']);
        } else {
            echo json_encode(['Registro no eliminado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'create_menu':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        //validaciones de los input's
        if ($inputs[1] == "") {
            echo json_encode(['Debe seleccionar un módulo', '0']);
            return;
        }
        if ($inputs[2] == "") {
            echo json_encode(['Debe seleccionar un módulo', '0']);
            return;
        }
        if ($inputs[0] == "") {
            echo json_encode(['Debe llenar el campo descripción', '0']);
            return;
        }
        if ($inputs[3] == "") {
            echo json_encode(['Debe llenar el campo orden', '0']);
            return;
        }
        if ($inputs[3] < 1) {
            echo json_encode(['El campo orden debe ser un número mayor a 0', '0']);
            return;
        }
        //insercion en la base de datos
        $res = $general->query("INSERT INTO `tb_menus`(`id_modulo`,`descripcion`, `orden`, `estado`) 
            VALUES ('$inputs[1]','$inputs[0]',$inputs[3],1)");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de registrar los datos', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro satisfactorio', '1']);
        } else {
            echo json_encode(['Registro no ingresado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'update_menu':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        //validaciones de los input's
        if ($inputs[2] == "") {
            echo json_encode(['No ha seleccionado un registro a editar', '0']);
            return;
        }
        if ($inputs[3] == "") {
            echo json_encode(['Debe seleccionar un módulo', '0']);
            return;
        }
        if ($inputs[1] == "") {
            echo json_encode(['No ha seleccionado un registro a editar', '0']);
            return;
        }
        if ($inputs[0] == "") {
            echo json_encode(['Debe llenar el campo descripción', '0']);
            return;
        }
        if ($inputs[4] == "") {
            echo json_encode(['Debe llenar el campo orden', '0']);
            return;
        }
        if ($inputs[4] < 1) {
            echo json_encode(['El campo orden debe ser un número mayor a 0', '0']);
            return;
        }
        //realizar la insercion
        $res = $general->query("UPDATE `tb_menus` set `id_modulo`= $inputs[2], `descripcion`= '$inputs[0]', `orden`= '$inputs[4]' WHERE id='$inputs[1]'");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de actualizar los datos', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro actualizado satisfactoriamente', '1']);
        } else {
            echo json_encode(['Registro no actualizado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'delete_menu':
        $id = $_POST['ideliminar'];
        $res = $general->query("UPDATE `tb_menus` set `estado`= '0' WHERE id='$id'");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de eliminar el registro', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro eliminado satisfactoriamente', '1']);
        } else {
            echo json_encode(['Registro no eliminado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'create_submenu':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        //validaciones de los input's
        if ($inputs[0] == "" || $inputs[1] == "") {
            echo json_encode(['Debe seleccionar un menu', '0']);
            return;
        }
        if ($inputs[2] == "") {
            echo json_encode(['Debe llenar el campo condición (condi)', '0']);
            return;
        }
        if ($inputs[3] == "") {
            echo json_encode(['Debe llenar el campo archivo', '0']);
            return;
        }
        if ($inputs[4] == "") {
            echo json_encode(['Debe llenar el campo texto', '0']);
            return;
        }
        if ($selects[0] == "0") {
            echo json_encode(['Debe seleccionar el porcentaje de avance de la opción', '0']);
            return;
        }
        if ($inputs[5] == "") {
            echo json_encode(['Debe llenar el campo orden', '0']);
            return;
        }
        if ($inputs[5] < 1) {
            echo json_encode(['El campo orden debe ser un número mayor a 0', '0']);
            return;
        }
        //realizar la insercion
        $res = $general->query("INSERT INTO `tb_submenus`(`id_menu`, `condi`, `file`, `caption`, `desarrollo`, `orden`, `estado`) 
            VALUES ('$inputs[0]','$inputs[2]','$inputs[3]','$inputs[4]','$selects[0]','$inputs[5]',1)");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de registrar los datos', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro satisfactorio', '1']);
        } else {
            echo json_encode(['Registro no ingresado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'update_submenu':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        //validaciones de los input's
        if ($inputs[5] == "") {
            echo json_encode(['No ha seleccionado un registro a editar', '0']);
            return;
        }
        if ($inputs[0] == "" || $inputs[1] == "") {
            echo json_encode(['Debe seleccionar un menu', '0']);
            return;
        }
        if ($inputs[2] == "") {
            echo json_encode(['Debe llenar el campo condición (condi)', '0']);
            return;
        }
        if ($inputs[3] == "") {
            echo json_encode(['Debe llenar el campo archivo', '0']);
            return;
        }
        if ($inputs[4] == "") {
            echo json_encode(['Debe llenar el campo texto', '0']);
            return;
        }
        if ($selects[0] == "0") {
            echo json_encode(['Debe seleccionar el porcentaje de avance de la opción', '0']);
            return;
        }
        if ($inputs[6] == "") {
            echo json_encode(['Debe llenar el campo orden', '0']);
            return;
        }
        if ($inputs[6] < 1) {
            echo json_encode(['El campo orden debe ser un número mayor a 0', '0']);
            return;
        }
        //realizar la insercion
        $res = $general->query("UPDATE `tb_submenus` set `id_menu`= '$inputs[0]', `condi`= '$inputs[2]',`file`= '$inputs[3]', `caption`= '$inputs[4]',`desarrollo`= '$selects[0]', `orden`= '$inputs[6]' WHERE id='$inputs[5]'");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de actualizar los datos', '0']);
            // echo json_encode([$inputs, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro actualizado satisfactoriamente', '1']);
        } else {
            echo json_encode(['Registro no actualizado satisfactoriamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'delete_submenu':
        $id = $_POST['ideliminar'];
        $res = $general->query("UPDATE `tb_submenus` set `estado`= '0' WHERE id='$id'");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode(['Error al momento de eliminar el registro', '0']);
            // echo json_encode([$aux, '0']);
            return;
        }
        if ($res) {
            echo json_encode(['Registro eliminado satisfactoriamente', '1']);
        } else {
            echo json_encode(['Registro no eliminado ', '0']);
        }
        mysqli_close($general);
        break;

    case 'create_permiso':
        $name = $_POST['nombre'];
        $estado = $_POST['estado'];

        if (isset($name, $estado)) {
            $query_check = " SELECT COUNT(*) as count FROM $db_name_general.tb_restringido WHERE `modulo_area` = ?";
            $stmt_check = $general->prepare($query_check);
            $stmt_check->bind_param("s", $name);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $row_check = $result_check->fetch_assoc();

            if ($row_check['count'] > 0) {
                echo json_encode(['El modulo ya existe', '0']);
            } else {
                $query_insert = "INSERT INTO $db_name_general.tb_restringido (`modulo_area`, `estado`) VALUES (?, ?)";
                $stmt_insert = $general->prepare($query_insert);
                $stmt_insert->bind_param("ss", $name, $estado);
                $success = $stmt_insert->execute();

                if (!$success) {
                    $error = $stmt_insert->error;
                    echo json_encode([$error, '0']);
                } else {
                    echo json_encode(['Registrado', '1']);
                }
            }
        } else {
            echo json_encode(['Faltan datos', '0']);
        }
        break;
    case 'update_permiso':
        $update_estado = $_POST['update_estado'];
        $estado = $_POST['estado'];

        if (isset($update_estado, $estado)) {
            $query_update = "UPDATE $db_name_general.tb_restringido SET estado = ? WHERE modulo_area = ?";
            $stmt_update = $general->prepare($query_update);
            $stmt_update->bind_param("ss", $estado, $update_estado);
            $success = $stmt_update->execute();

            if (!$success) {
                $error = $stmt_update->error;
                echo json_encode([$error, '0']);
            } else {
                echo json_encode(['Actualizado', '1']);
            }
        } else {
            echo json_encode(['Faltan datos', '0']);
        }
        break;
    case 'create_permisos':

        $id = $_POST['id'];
        $id_cargo = $_POST['id_cargo'];
        $update_estado = $_POST['update_estado'];
        $estado = $_POST['estado'];

        if (isset($id, $update_estado, $estado, $id_cargo)) {
            //verifi que no se repita
            $query_check = "SELECT COUNT(*) AS count FROM tb_autorizacion WHERE id_usuario = ? AND id_rol = ? AND id_restringido = ? AND estado = ?";
            $stmt_check = mysqli_prepare($conexion, $query_check);
            mysqli_stmt_bind_param($stmt_check, "iiii", $id, $id_cargo, $update_estado, $estado);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_bind_result($stmt_check, $count);
            mysqli_stmt_fetch($stmt_check);
            mysqli_stmt_close($stmt_check);

            if ($count > 0) {
                echo json_encode(['El registro ya existe', '0']);
            } else {
                // Insertar
                $query_insert = "INSERT INTO tb_autorizacion (id_usuario, id_rol, id_restringido, estado) VALUES (?, ?, ?, ?)";
                $stmt_insert = mysqli_prepare($conexion, $query_insert);
                mysqli_stmt_bind_param($stmt_insert, "iiii", $id, $id_cargo, $update_estado, $estado);
                $success = mysqli_stmt_execute($stmt_insert);
                mysqli_stmt_close($stmt_insert);

                if ($success) {
                    echo json_encode(['Datos ingresados ✔ ', '1']);
                } else {
                    echo json_encode(['Error al insertar en la base de datos', '0']);
                }
            }
        } else {
            echo json_encode(['ERROR', '0']);
        }
        break;
    case 'search_id':
        if (!$conexion) {
            die("Error de conexión: " . mysqli_connect_error());
        }
        $id = isset($_POST['id']) ? $_POST['id'] : '';

        if ($id != '') {
            $query_insert = "SELECT 
                                    ta.id_usuario AS id_autorizacion, 
                                    ta.estado, 
                                    tr.modulo_area
                                FROM tb_autorizacion AS ta
                                INNER JOIN $db_name_general.tb_restringido AS tr ON ta.id_restringido = tr.id
                                WHERE  ta.id_usuario = $id ";

            $result = mysqli_query($conexion, $query_insert);

            if ($result) {
                $data = array();
                while ($row = mysqli_fetch_assoc($result)) {
                    $data[] = $row;
                }

                // Procesar los datos del checkbox
                if (isset($_POST['checked_values'])) {
                    foreach ($_POST['checked_values'] as $item) {
                        $modulo_checkbox = $item[0];
                        $id_autorizacion = $item[1];
                    }
                }
                $table_html = '<table class="table"><h3>Permisos Asignados</h3>';
                $table_html .= '<thead class="thead-dark">';
                $table_html .= '<tr>';
                $table_html .= '<th>#</th>';
                $table_html .= '<th>Estado</th>';
                $table_html .= '<th>Módulo Área</th>';
                $table_html .= '</tr>';
                $table_html .= '</thead>';
                $table_html .= '<tbody>';
                $count = 1;

                foreach ($data as $row) {
                    $estado_checked = ($row['estado'] == 1) ? 'checked' : '';
                    $estado_switch = ($row['estado'] == 1) ? 'checked' : ''; // Estado 
                    $table_html .= '<tr>';
                    $table_html .= '<td>' . $count++ . '</td>';
                    $table_html .= '<td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" ' . $estado_switch . ' id="switch' . $row['id_autorizacion'] . '" onclick="updateEstado(\'update_estado\',' . $row['id_autorizacion'] . ', this.checked, \'' . $row['modulo_area'] . '\')">
                                                <label class="form-check-label" for="switch' . $row['id_autorizacion'] . '"></label>
                                            </div>
                                        </td>';
                    $table_html .= '<td>' . $row['modulo_area'] . '</td>';
                    $table_html .= '</tr>';
                }
                $table_html .= '</tbody>';
                $table_html .= '</table>';

                echo $table_html;
?>
                </div>
<?php
            } else {
                echo "Error al ejecutar la consulta: " . mysqli_error($conexion);
            }
        } else {
            echo "No se recibió el valor de 'id'";
        }
        break;
    case 'update_estado':
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            if (isset($_POST['id_autorizacion'], $_POST['modulo_area'], $_POST['estado_convertido'])) {
                $id = $_POST['id_autorizacion'];
                $modulo = $_POST['modulo_area'];
                $estado = $_POST['estado_convertido'];


                $query_update = "UPDATE tb_autorizacion AS ta
                                     INNER JOIN $db_name_general.tb_restringido AS tr ON ta.id_restringido = tr.id
                                     SET ta.estado = '$estado'
                                     WHERE ta.id_usuario = '$id' AND tr.modulo_area = '$modulo'";

                // execute consulta
                $result = mysqli_query($conexion, $query_update);

                if ($result) {
                    echo json_encode(['Simon ', '1']);
                } else {
                    echo json_encode(["Error: No se pudo actualizar", "0"]);
                }
            } else {
                echo json_encode(["Error: Datos incorrectos", "0"]);
            }
        } else {
            echo json_encode(["Error: Solicitud incorrecta", "0"]);
        }
        break;
    case 'change_section_general':
        //obtiene([],['campo_fecha_caja'],[],'change_section_general','0',[$('#permitir_boletas').is(':checked')?1:0]
        list($campo_fecha_caja) = $_POST['selects'];
        list($boletasBanco, $interesesAlDia) = $_POST['archivo'];

        // Log::info("Cambio de sección general", [
        //     'campo_fecha_caja' => $campo_fecha_caja,
        //     'boletasBanco' => $boletasBanco,
        //     'interesesAlDia' => $interesesAlDia
        // ]);

        $cache = new CacheManager('config_');

        $showmensaje = true;
        try {

            $database->openConnection();

            $database->beginTransaction();

            /**
             * Fecha a tomar en cuenta caja
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 1');
            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 1,
                    'valor' => $campo_fecha_caja,
                    'observaciones' => 'Campo de fecha A Tomar en cuenta para cuadre de caja, 1 fecha de sistema, 2 fecha de documento',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $campo_fecha_caja,
                ], 'id_config= 1');
            }

            /**
             * Repeticion de boletas de banco
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 8');

            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 8,
                    'valor' => $boletasBanco,
                    'observaciones' => '1 no bloquea cuando se repite la boleta con el banco, 0 bloquea',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $boletasBanco,
                ], 'id_config= 8');
            }

            /**
             * Mostrar intereses al dia en caja de creditos
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 13');
            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 13,
                    'valor' => $interesesAlDia,
                    'observaciones' => '0 no mostrar calculo al dia de intereses en caja',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $interesesAlDia,
                ], 'id_config= 13');
            }

            $database->commit();


            $result = $cache->clear();

            $mensaje = "Configuraciones actualizadas correctamente";
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

    case 'reset_configurations':
        list($idConfigs) = $_POST['archivo'];
        $cache = new CacheManager('config_');
        $showmensaje = true;
        try {

            $defaultConfigs = $appConfigGeneral->getDefaultValuesForConfigurations();
            // Log::info("Restaurando configuraciones a valores por defecto", [
            //     'defaultConfigs' => $defaultConfigs
            // ]);

            $database->openConnection();

            $database->beginTransaction();

            foreach ($idConfigs as $idConfig) {
                if (isset($defaultConfigs[$idConfig])) {
                    $valor = $defaultConfigs[$idConfig];
                    Log::info("Restaurando configuración ID {$idConfig} a valor por defecto: {$valor}");
                    $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= ?', [$idConfig]);
                    if (empty($verificacion)) {
                        $database->insert('tb_configuraciones', [
                            'id_config' => $idConfig,
                            'valor' => $valor,
                            'observaciones' => "Configuración restaurada a valor por defecto"
                        ]);
                    } else {
                        $database->update('tb_configuraciones', [
                            'valor' => $valor,
                        ], 'id_config= ?', [$idConfig]);
                    }
                }
            }

            $database->commit();
            $result = $cache->clear();
            $mensaje = "Configuraciones actualizadas correctamente";
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
    case 'change_section_creditos':
        // obtiene([],['precision_creditos','mode_precision_creditos'],[],'change_section_general','0',
        // [$('#desglosar_iva').is(':checked')?1:0,$('#permitir_pagos_kp').is(':checked')?1:0,$('#permitir_pagos_int').is(':checked')?1:0,recolectaChecks('campos_creditos')],

        list($precision_creditos, $mode_precision_creditos) = $_POST['selects'];
        list($desglosar_iva, $permitir_pagos_kp, $permitir_pagos_int) = $_POST['archivo'];

        if (!isset($_POST['archivo'][3]) || $_POST['archivo'][3] == null) {
            echo json_encode(['Seleccione al menos un campo para mostrar en caja de creditos', 0]);
            return;
        }

        $camposCajaCreditos = (isset($_POST['archivo'][3]) && $_POST['archivo'][3] !== null) ? $_POST['archivo'][3] : [];

        // Log::info("Cambio de sección general", [
        //     'precision_creditos' => $precision_creditos,
        //     'mode_precision_creditos' => $mode_precision_creditos,
        //     'desglosar_iva' => $desglosar_iva,
        //     'permitir_pagos_kp' => $permitir_pagos_kp,
        //     'permitir_pagos_int' => $permitir_pagos_int,
        //     'camposCajaCreditos' => $camposCajaCreditos,
        //     'hoy2' => $hoy2
        // ]);

        $cache = new CacheManager('config_');

        $showmensaje = true;
        try {

            $database->openConnection();

            $database->beginTransaction();

            /**
             * Precision creditos
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 11');
            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 11,
                    'valor' => $precision_creditos,
                    'observaciones' => 'redondeo a decimales para planes de pagos',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $precision_creditos,
                ], 'id_config= 11');
            }

            /**
             * modo de precision creditos
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 12');

            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 12,
                    'valor' => $mode_precision_creditos,
                    'observaciones' => 'Modo de precision para creditos',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $mode_precision_creditos,
                ], 'id_config= 12');
            }

            /**
             * Desglosar IVA    
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 2');
            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 2,
                    'valor' => $desglosar_iva,
                    'observaciones' => 'Desglosar el IVA en la partida de pagos de creditos, 1 si, 0 no (por defecto)',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $desglosar_iva,
                ], 'id_config= 2');
            }

            /**
             * Permitir pagos de capital mayores al saldo pendiente
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 3');
            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 3,
                    'valor' => $permitir_pagos_kp,
                    'observaciones' => 'Permitir pagos de capital mayores al saldo kp pendiente?',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $permitir_pagos_kp,
                ], 'id_config= 3');
            }

            /**
             * Permitir pagos de intereses mayores al saldo de interes pendiente
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 4');
            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 4,
                    'valor' => $permitir_pagos_int,
                    'observaciones' => 'Permitir pagos de intereses mayores al saldo de interes pendiente?',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $permitir_pagos_int,
                ], 'id_config= 4');
            }

            /**
             * Campos a mostrar en la caja de creditos
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 14');
            // Convertir $camposCajaCreditos de array a string separados por coma
            $camposCajaCreditosStr = is_array($camposCajaCreditos) ? implode(',', $camposCajaCreditos) : $camposCajaCreditos;

            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 14,
                    'valor' => $camposCajaCreditosStr,
                    'observaciones' => 'Campos a mostrar en la caja de creditos',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $camposCajaCreditosStr,
                ], 'id_config= 14');
            }

            $database->commit();
            $result = $cache->clear();
            $mensaje = "Configuraciones actualizadas correctamente";
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

    case 'change_section_garantias':

        $tiposAhorros = $_POST['archivo'][0] ?? [];
        $tiposAportaciones = $_POST['archivo'][1] ?? [];

        // Log::info("archivo", $_POST['archivo'] ?? []);
        $cache = new CacheManager('config_');
        $showmensaje = true;
        try {

            $database->openConnection();

            $database->beginTransaction();

            $ahorrosSave = '';
            if (!empty($tiposAhorros)) {
                $ahorrosSave = implode(',', array_map(function ($item) {
                    return "'" . $item . "'";
                }, $tiposAhorros));
            } else {
                $ahorrosSave = "'-'";
            }

            $aportacionesSave = '';
            if (!empty($tiposAportaciones)) {
                $aportacionesSave = implode(',', array_map(function ($item) {
                    return "'" . $item . "'";
                }, $tiposAportaciones));
            }

            // Log::info("Tipos de ahorros y aportaciones a guardar", [
            //     'ahorrosSave' => $ahorrosSave,
            //     'aportacionesSave' => $aportacionesSave
            // ]);

            /**
             * tipos de ahorros a mostrar en la creacion de garantias
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 9');
            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 9,
                    'valor' => $ahorrosSave,
                    'observaciones' => 'tipos de ahorros que se pueden agregar como garantia ',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $ahorrosSave,
                ], 'id_config= 9');
            }

            /**
             * tipos de aportaciones a mostrar en la creacion de garantias
             */
            $verificacion = $database->selectColumns('tb_configuraciones', ['id'], 'id_config= 10');

            if (empty($verificacion)) {
                $database->insert('tb_configuraciones', [
                    'id_config' => 10,
                    'valor' => $aportacionesSave,
                    'observaciones' => 'tipos de aportaciones que se pueden agregar como garantia',
                ]);
            } else {
                $database->update('tb_configuraciones', [
                    'valor' => $aportacionesSave,
                ], 'id_config= 10');
            }

            $database->commit();
            $result = $cache->clear();
            $mensaje = "Configuraciones actualizadas correctamente";
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
    case 'change_permission_modules':

        // Log::info("post", $_POST);

        $showmensaje = true;
        try {

            if (!isset($_SESSION['id'])) {
                $showmensaje = false;
                throw new \Exception("Sesion expirada, inicie sesion nuevamente.");
            }

            // INFORMACION INSTITUCION
            $authAgencyId = \Micro\Generic\Auth::getAgencyId();
            if ($authAgencyId === null) {
                $showmensaje = false;
                throw new \Exception("ID de agencia no disponible en la sesión.");
            }
            $dataAgencia = new Agencia($authAgencyId);

            $idInstitucion = $dataAgencia->getIdInstitucion();
            if ($idInstitucion === null) {
                $showmensaje = false;
                throw new \Exception("ID de institución no disponible para la agencia {$authAgencyId}.");
            }

            $data = [
                'idModulo' => $_POST['archivo'][0] ?? null,
                'newState' => $_POST['archivo'][1] ?? null,
                'idPermiso' => $_POST['archivo'][2] ?? null,
            ];

            $rules = [
                'idModulo' => 'required|numeric|min:1',
                'newState' => 'required|in:0,1'
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                // Log::info("Errores de validacion create_periodos", $validator->errors());
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection(2);

            $database->beginTransaction();

            $tb_permisos_modulos = array(
                "id_cooperativa" => $idInstitucion,
                "id_modulo" => $data['idModulo'],
                "estado" => $data['newState'],
                // "comentario" => "MODIFICACION DE PERMISO POR SUPERADMIN",
            );

            if ($data['idPermiso'] === null || $data['idPermiso'] == '') {
                // Crear nuevo permiso
                $tb_permisos_modulos['comentario'] = "CREACION DE PERMISO POR SUPERADMIN";
                $database->insert('tb_permisos_modulos', $tb_permisos_modulos);
            } else {
                // Actualizar permiso existente
                $database->update('tb_permisos_modulos', $tb_permisos_modulos, 'id = ?', [$data['idPermiso']]);
            }

            $database->commit();
            $mensaje = "Permiso actualizado correctamente";
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
}
