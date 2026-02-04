<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];

switch ($condi) {
    case 'permisos_usuarios': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
?>
<!-- Crud para agregar, editar y eliminar usuarios -->
<input type="text" id="file" value="usuario_02" style="display: none;">
<input type="text" id="condi" value="permisos_usuarios" style="display: none;">

<div class="text" style="text-align:center">ASIGNACIÓN DE PERMISOS</div>
<div class="card mb-2">
    <div class="card-header">Administración de Permisos</div>
    <div class="card-body">
        <div class="text-center mb-2">
            <h3>Datos</h3>
        </div>
        <!-- tabla de usuarios -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row mt-2 pb-2">
                <div class="col">
                    <div class="table-responsive">
                        <table id="table-submenus" class="table table-hover table-border">
                            <thead class="text-light table-head-usu mt-2">
                                <tr>
                                    <th>Acciones</th>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Cargo</th>
                                    <th>Nombre agencia</th>
                                    <th>Cod. agencia</th>
                                </tr>
                            </thead>
                            <tbody id="tb_cuerpo_submenus" style="font-size: 0.9rem !important;">
                                <?php
                                            $consulta = mysqli_query($conexion, "SELECT us.id_usu AS id_usuario, CONCAT(us.nombre,' ', us.apellido) AS nombre, cg.UsuariosCargoProfecional AS cargo, ag.nom_agencia AS nombreagen, ag.cod_agenc AS codagen FROM tb_usuario us
                                            INNER JOIN tb_permisos2 pe ON us.id_usu=pe.id_usuario
                                            INNER JOIN $db_name_general.tb_submenus ts ON pe.id_submenu=ts.id
                                            INNER JOIN $db_name_general.tb_menus tm ON ts.id_menu =tm.id
                                            INNER JOIN $db_name_general.tb_modulos td ON tm.id_modulo =td.id
                                            INNER JOIN $db_name_general.tb_permisos_modulos tbps ON td.id=tbps.id_modulo
                                            INNER JOIN $db_name_general.tb_usuarioscargoprofecional cg ON us.puesto=cg.id_UsuariosCargoProfecional
                                            INNER JOIN tb_agencia ag ON us.id_agencia=ag.id_agencia
                                            WHERE ts.estado='1' AND tm.estado='1' AND td.estado='1' AND us.estado!='0' AND 
                                            tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1)
                                            GROUP BY us.id_usu 
                                                ORDER BY td.id, td.descripcion ASC");
                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id = $row["id_usuario"];
                                                $nombre = $row["nombre"];
                                                $cargo = $row["cargo"];
                                                $nombreagen = $row["nombreagen"];
                                                $codagen = $row["codagen"];
                                                if ($_SESSION['id'] == 4) { ?>
                                <!-- seccion de datos -->
                                <tr>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm"
                                            onclick="printdiv5('id_usuario,id_usuario_past,usuario,cargo,nomagencia,codagencia/A,A,A,A,A,A/'+'/#/#/#/#',['<?= $id ?>','<?= $id ?>','<?= $nombre ?>','<?= $cargo ?>','<?= $nombreagen ?>','<?= $codagen ?>']);  recuperar_permisos('<?= $id ?>'); HabDes_boton(1);"><i
                                                class="fa-solid fa-eye"></i></button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="eliminar('<?= $id ?>', 'crud_usuario', '0', 'delete_permisos')"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </td>
                                    <th scope="row"><?= $id ?></th>
                                    <td><?= $nombre ?></td>
                                    <td><?= $cargo ?></td>
                                    <td><?= $nombreagen ?></td>
                                    <td><?= $codagen ?></td>
                                </tr>
                                <?php } else {
                                                    if ($id != 4) { ?>
                                <!-- seccion de datos -->
                                <tr>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm"
                                            onclick="printdiv5('id_usuario,id_usuario_past,usuario,cargo,nomagencia,codagencia/A,A,A,A,A,A/'+'/#/#/#/#',['<?= $id ?>','<?= $id ?>','<?= $nombre ?>','<?= $cargo ?>','<?= $nombreagen ?>','<?= $codagen ?>']);  recuperar_permisos('<?= $id ?>'); HabDes_boton(1);"><i
                                                class="fa-solid fa-eye"></i></button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="eliminar('<?= $id ?>', 'crud_usuario', '0', 'delete_permisos')"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </td>
                                    <th scope="row"><?= $id ?></th>
                                    <td><?= $nombre ?></td>
                                    <td><?= $cargo ?></td>
                                    <td><?= $nombreagen ?></td>
                                    <td><?= $codagen ?></td>
                                </tr>
                                <?php }
                                                }
                                            } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Seccion de informacion de usuario -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row">
                <div class="col">
                    <div class="text-center mb-2"><b>Información de usuario</b></div>
                </div>
            </div>
            <!-- usuario y boton buscar -->
            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-2 mt-2">
                        <input type="text" class="form-control" id="usuario" placeholder="Nombre de usuario" disabled>
                        <input type="text" id="id_usuario" hidden>
                        <input type="text" id="id_usuario_past" hidden>
                        <label for="cliente">Nombre de usuario</label>
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                        onclick="abrir_modal('#modal_users', '#id_modal_hidden', 'id_usuario,usuario,cargo,nomagencia,codagencia/A,A,A,A,A/'+'/#/#/#/#')"><i
                            class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar usuario</button>
                </div>
            </div>
            <!-- cargo, nombre agencia y codagencia  -->
            <div class="row">
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="form-floating mb-3 mt-2">
                        <input type="text" class="form-control" id="cargo" placeholder="Cargo" disabled>
                        <label for="cargo">Cargo</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="form-floating mb-3 mt-2">
                        <input type="text" class="form-control" id="nomagencia" placeholder="Nombre de agencia"
                            disabled>
                        <label for="nomagencia">Nombre de agencia</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="form-floating mb-3 mt-2">
                        <input type="text" class="form-control" id="codagencia" placeholder="Código de agencia"
                            disabled>
                        <label for="codagencia">Código de agencia</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seccion de permisos -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row">
                <div class="col">
                    <div class="text-center mb-2"><b>Permisos disponibles</b></div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="text-center mb-2 text-primary"><b>
                            <h5><u>Permisos generales</u></h5>
                        </b></div>
                </div>
            </div>
            <!-- usuario y boton buscar -->
            <div class="row">
                <div class="col-12 d-flex justify-content-end pe-4 mb-2">
                    <div class="form-check form-check-reverse">
                        <label class="form-check-label" for="todos">
                            Todos los permisos
                        </label>
                        <input class="form-check-input" type="checkbox" name="todos" value="" id="todos"
                            onclick="seleccionar_todos(this.checked)">
                    </div>
                </div>
            </div>
            <!-- CONSULTA PARA APERTURAS TODOS LOS PERMISOS -->
            <?php
                        $consulta = "SELECT td.id AS id_modulo, td.descripcion AS modulo, td.rama AS rama, tm.id AS id_menu, tm.descripcion AS menu, ts.id AS id_submenu,ts.condi AS condicion, ts.`file` AS archivo, ts.caption AS texto, ts.desarrollo FROM $db_name_general.tb_submenus ts
                        INNER JOIN $db_name_general.tb_menus tm ON ts.id_menu =tm.id
                        INNER JOIN $db_name_general.tb_modulos td ON tm.id_modulo =td.id
                        INNER JOIN $db_name_general.tb_permisos_modulos tbps ON td.id=tbps.id_modulo
                        WHERE ts.estado='1' AND tm.estado='1' AND td.estado='1' AND tbps.estado='1' AND td.rama!='A' AND 
                            tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1)
                        ORDER BY td.id, tm.orden, ts.orden ASC";

                        $datos = mysqli_query($conexion, $consulta);
                        $aux = mysqli_error($conexion);
                        $registro[] = [];
                        $i = 0;
                        $bandera = false;
                        $controlador = "";
                        while ($fila = mysqli_fetch_array($datos, MYSQLI_ASSOC)) {
                            $registro[$i] = $fila;
                            $i++;
                        }

                        //impresion de ipciones
                        $numeral = 1;
                        $idcheckmod = 0;
                        $alfabeto = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "U", "V", "W", "X", "Y", "Z"];
                        for ($i = 0; $i < count($registro); $i++) {
                            if ($controlador != $registro[$i]['id_modulo']) {
                                $bandera = true;
                                $controlador = $registro[$i]['id_modulo'];
                                $numeral = 1;
                            }
                            if ($bandera) {
                        ?>
            <!-- PERMISOS SOBRE UN MODULO -->
            <div class="row">
                <div class="col">
                    <div class="card text-bg-light mb-3">
                        <div class="card-header bg-primary bg-gradient text-white">
                            <span class=""><b>Módulo de <?= $registro[$i]['modulo']; ?></b></span>
                        </div>
                        <div class="card-body">
                            <!-- SECCION DE IMPRESION DE TITULO DE IMPRESION DE OPCIONES -->
                            <div class="row border-bottom pb-2">
                                <div class="col-1 col-md-1 mt-1">
                                    <span><b>#</b></span>
                                </div>
                                <div class="col">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-3 mt-1">
                                            <span><b>Módulo</b></span>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-3 mt-1">
                                            <span><b>Menú</b></span>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 mt-1">
                                            <span><b>Submenú</b></span>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-2 mt-1">
                                            <span><b>Estado</b></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-2 col-md-2 mt-1">
                                    <div class="d-flex justify-content-center">
                                        <div class="form-check form-check-reverse">
                                            <input class="form-check-input" type="checkbox"
                                                name="<?= "M-" . $alfabeto[$idcheckmod]; ?>"
                                                id="<?= "M" . $idcheckmod; ?>"
                                                onclick="seleccionar_checks(this.checked, '<?= $alfabeto[$idcheckmod]; ?>')">
                                            <label class="form-check-label"
                                                for="<?= "M" . $idcheckmod; ?>"><b>Todos</b></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            <!-- SECCION DE IMPRESION DE OPCIONES -->
                            <div class="row border-bottom pb-2">
                                <div class="col-1 col-md-1 mt-1">
                                    <span><?= $numeral ?></span>
                                </div>
                                <div class="col">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-3 mt-1">
                                            <span><?= $registro[$i]['modulo']; ?></span>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-3 mt-1">
                                            <span><?= $registro[$i]['menu']; ?></span>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 mt-1">
                                            <span><?= $registro[$i]['texto']; ?></span>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-2 mt-1">
                                            <?php
                                                            if ($registro[$i]['desarrollo'] == '1') {
                                                                $textodesarrollo = "0%";
                                                                $colorestado = "btn btn-danger";
                                                            } elseif ($registro[$i]['desarrollo'] == '2') {
                                                                $textodesarrollo = "20%";
                                                                $colorestado = "btn btn-danger";
                                                            } elseif ($registro[$i]['desarrollo'] == '3') {
                                                                $textodesarrollo = "40%";
                                                                $colorestado = "btn btn-danger";
                                                            } elseif ($registro[$i]['desarrollo'] == '4') {
                                                                $textodesarrollo = "60%";
                                                                $colorestado = "btn btn-warning";
                                                            } elseif ($registro[$i]['desarrollo'] == '5') {
                                                                $textodesarrollo = "80%";
                                                                $colorestado = "btn btn-warning";
                                                            } elseif ($registro[$i]['desarrollo'] == '6') {
                                                                $textodesarrollo = "100%";
                                                                $colorestado = "btn btn-success btn-sm";
                                                            } ?>
                                            <span class="<?= $colorestado; ?>"><?= $textodesarrollo; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-2 col-md-2 mt-1">
                                    <div class="d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input S" type="checkbox"
                                                value="<?= $registro[$i]['id_submenu']; ?>"
                                                name="<?= $alfabeto[$idcheckmod]; ?>"
                                                id="<?= "S_" . $registro[$i]['id_submenu']; ?>"
                                                onclick="desseleccionar_checks(this.checked, '<?= $alfabeto[$idcheckmod]; ?>')">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php $numeral++; ?>
                            <?php $bandera = false; ?>
                            <?php if (count($registro) - 1 == $i) { ?>
                            <?php $idcheckmod++; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } else {
                                                if ($bandera == false && $controlador != $registro[$i + 1]['id_modulo']) { ?>
            <?php $idcheckmod++; ?>
        </div>
    </div>
</div>
</div>
<?php }
                                            } ?>
<?php } ?>
<!-- INICIO DE PERMISOS DE ADMINISTRACION -->
<div class="row">
    <div class="col">
        <div class="text-center mb-2 text-primary"><b>
                <h5><u>Permisos de administrador</u></h5>
            </b></div>
    </div>
</div>
<?php
            $consulta2 = "SELECT td.id AS id_modulo, td.descripcion AS modulo, td.rama AS rama, tm.id AS id_menu, tm.descripcion AS menu, ts.id AS id_submenu,ts.condi AS condicion, ts.`file` AS archivo, ts.caption AS texto, ts.desarrollo FROM $db_name_general.tb_submenus ts
                        INNER JOIN $db_name_general.tb_menus tm ON ts.id_menu =tm.id
                        INNER JOIN $db_name_general.tb_modulos td ON tm.id_modulo =td.id
                        INNER JOIN $db_name_general.tb_permisos_modulos tbps ON td.id=tbps.id_modulo
                        WHERE ts.estado='1' AND tm.estado='1' AND td.estado='1' AND tbps.estado='1' AND td.rama='A' AND 
                            tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1)
                        ORDER BY td.id, tm.orden, ts.orden ASC";

            $datos2 = mysqli_query($conexion, $consulta2);
            $aux = mysqli_error($conexion);
            $registro2[] = [];
            $j = 0;
            $bandera = false;
            $controlador = "";
            while ($fila = mysqli_fetch_array($datos2, MYSQLI_ASSOC)) {
                $registro2[$j] = $fila;
                $j++;
            }

            //impresion de ipciones
            $numeral = 1;
            for ($i = 0; $i < count($registro2); $i++) {
                if ($controlador != $registro2[$i]['id_modulo']) {
                    $bandera = true;
                    $controlador = $registro2[$i]['id_modulo'];
                    $numeral = 1;
                }
                if ($bandera) {
?>
<!-- PERMISOS SOBRE UN MODULO -->
<div class="row">
    <div class="col">
        <div class="card text-bg-light mb-3">
            <div class="card-header bg-primary bg-gradient text-white">
                <span class=""><b>Módulo de <?= $registro2[$i]['modulo']; ?></b></span>
            </div>
            <div class="card-body">
                <!-- SECCION DE IMPRESION DE TITULO DE IMPRESION DE OPCIONES -->
                <div class="row border-bottom pb-2">
                    <div class="col-1 col-md-1 mt-1">
                        <span><b>#</b></span>
                    </div>
                    <div class="col">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-3 mt-1">
                                <span><b>Módulo</b></span>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3 mt-1">
                                <span><b>Menú</b></span>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mt-1">
                                <span><b>Submenú</b></span>
                            </div>
                            <div class="col-12 col-md-6 col-lg-2 mt-1">
                                <span><b>Estado</b></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-2 col-md-2 mt-1">
                        <div class="d-flex justify-content-center">
                            <div class="form-check form-check-reverse">
                                <input class="form-check-input" type="checkbox"
                                    name="<?= "M-" . $alfabeto[$idcheckmod]; ?>" id="<?= "M" . $idcheckmod; ?>"
                                    onclick="seleccionar_checks(this.checked, '<?= $alfabeto[$idcheckmod]; ?>')">
                                <label class="form-check-label" for="<?= "M" . $idcheckmod; ?>"><b>Todos</b></label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <!-- SECCION DE IMPRESION DE OPCIONES -->
                <div class="row border-bottom pb-2">
                    <div class="col-1 col-md-1 mt-1">
                        <span><?= $numeral ?></span>
                    </div>
                    <div class="col">
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-3 mt-1">
                                <span><?= $registro2[$i]['modulo']; ?></span>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3 mt-1">
                                <span><?= $registro2[$i]['menu']; ?></span>
                            </div>
                            <div class="col-12 col-md-6 col-lg-4 mt-1">
                                <span><?= $registro2[$i]['texto']; ?></span>
                            </div>
                            <div class="col-12 col-md-6 col-lg-2 mt-1">
                                <?php
                                    if ($registro2[$i]['desarrollo'] == '1') {
                                        $textodesarrollo = "0%";
                                        $colorestado = "btn btn-danger";
                                    } elseif ($registro2[$i]['desarrollo'] == '2') {
                                        $textodesarrollo = "20%";
                                        $colorestado = "btn btn-danger";
                                    } elseif ($registro2[$i]['desarrollo'] == '3') {
                                        $textodesarrollo = "40%";
                                        $colorestado = "btn btn-danger";
                                    } elseif ($registro2[$i]['desarrollo'] == '4') {
                                        $textodesarrollo = "60%";
                                        $colorestado = "btn btn-warning";
                                    } elseif ($registro2[$i]['desarrollo'] == '5') {
                                        $textodesarrollo = "80%";
                                        $colorestado = "btn btn-warning";
                                    } elseif ($registro2[$i]['desarrollo'] == '6') {
                                        $textodesarrollo = "100%";
                                        $colorestado = "btn btn-success btn-sm";
                                    } ?>
                                <span class="<?= $colorestado; ?>"><?= $textodesarrollo; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-2 col-md-2 mt-1">
                        <div class="d-flex justify-content-center">
                            <div class="form-check">
                                <input class="form-check-input S" type="checkbox"
                                    value="<?= $registro2[$i]['id_submenu']; ?>" name="<?= $alfabeto[$idcheckmod]; ?>"
                                    id="<?= "S_" . $registro2[$i]['id_submenu']; ?>"
                                    onclick="desseleccionar_checks(this.checked, '<?= $alfabeto[$idcheckmod]; ?>')">
                            </div>
                        </div>
                    </div>
                </div>
                <?php $numeral++; ?>
                <?php $bandera = false; ?>
                <?php if (count($registro2) - 1 == $i) { ?>
                <?php $idcheckmod++; ?>
            </div>
        </div>
    </div>
</div>
<?php } else {
                        if ($bandera == false && $controlador != $registro2[$i + 1]['id_modulo']) { ?>
<?php $idcheckmod++; ?>
</div>
</div>
</div>
</div>
<?php }
                    } ?>
<?php  } ?>
</div>


<!-- botones para guardar, actualizar y cancelar -->
<div class="row justify-items-md-center">
    <div class="col align-items-center mt-2" id="modal_footer">
        <button type="button" class="btn btn-outline-success" id="btGuardar"
            onclick="guardar_editar_permisos('create_permisos')">
            <i class="fa-solid fa-floppy-disk"></i> Guardar
        </button>
        <button type="button" class="btn btn-outline-primary" id="btEditar"
            onclick="guardar_editar_permisos('update_permisos')">
            <i class="fa-solid fa-floppy-disk"></i> Actualizar
        </button>
        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
            <i class="fa-solid fa-ban"></i> Cancelar
        </button>
        <button type="button" class="btn btn-outline-warning" onclick="salir()">
            <i class="fa-solid fa-circle-xmark"></i> Salir
        </button>
    </div>
</div>
<script>
//Datatable para parametrizacion
$(document).ready(function() {
    convertir_tabla_a_datatable("table-submenus");
    HabDes_boton(0);
});
</script>
</div>
</div>
<!-- Aca van los modales necesarios -->
<?php include "../../../../src/cris_modales/mdls_users.php"; ?>
<?php
        }
        break;
}
?>