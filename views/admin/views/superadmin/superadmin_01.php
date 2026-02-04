<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];

switch ($condi) {
    case 'modulos_usuarios': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
?>
            <!-- Crud para agregar, editar y eliminar usuarios -->
            <input type="text" id="file" value="superadmin_01" style="display: none;">
            <input type="text" id="condi" value="modulos_usuarios" style="display: none;">

            <div class="text" style="text-align:center">ADMINISTRACION DE MÓDULOS</div>
            <div class="card">
                <div class="card-header">Administración de Módulos</div>
                <div class="card-body">
                    <div class="text-center mb-2">
                        <h3>Datos del módulo</h3>
                    </div>
                    <!-- Seccion de inputs para edicion -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <!-- descripcion e icono -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3 mt-2">
                                    <input type="text" class="form-control" id="descripcion" placeholder="Descripción">
                                    <input type="text" name="" id="id" hidden>
                                    <label for="descripcion">Descripción</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3 mt-2">
                                    <input type="text" class="form-control" id="icon" placeholder="Icono">
                                    <label for="icon">Icono</label>
                                </div>
                            </div>
                        </div>
                        <!-- ruta y rama -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="ruta" placeholder="Ruta">
                                    <label for="ruta">Ruta</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="rama" aria-label="Rama">
                                        <option selected value="0">Seleccionar una rama</option>
                                        <option value=A>A</option>
                                        <option value=B>B</option>
                                        <option value=C>C</option>
                                        <option value=D>D</option>
                                        <option value=E>E</option>
                                        <option value=F>F</option>
                                        <option value=G>G</option>
                                        <option value=H>H</option>
                                        <option value=I>I</option>
                                        <option value=J>J</option>
                                        <option value=K>K</option>
                                        <option value=L>L</option>
                                        <option value=M>M</option>
                                        <option value=N>N</option>
                                        <option value=Ñ>Ñ</option>
                                        <option value=O>O</option>
                                        <option value=P>P</option>
                                        <option value=Q>Q</option>
                                        <option value=R>R</option>
                                        <option value=S>S</option>
                                        <option value=T>T</option>
                                        <option value=U>U</option>
                                        <option value=V>V</option>
                                        <option value=W>W</option>
                                        <option value=X>X</option>
                                        <option value=Y>Y</option>
                                        <option value=Z>Z</option>
                                    </select>
                                    <label for="rama">Rama</label>
                                </div>
                            </div>
                        </div>
                        <!-- ruta y rama -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="orden" placeholder="Orden">
                                    <label for="orden">Orden</label>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mb-3" id="modal_footer">
                                <button type="button" class="btn btn-outline-success" id="btGuardar" onclick="obtiene([`descripcion`,`icon`,`ruta`,`orden`],[`rama`],[],`create_modulo`,`0`,['<?= $codusu; ?>'])">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btEditar" onclick="obtiene([`descripcion`,`icon`,`ruta`,`id`,`orden`],[`rama`],[],`update_modulo`,`0`,['<?= $codusu; ?>'])">
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
                    </div>

                    <!-- tabla de usuarios -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row mt-2 pb-2">
                            <div class="col">
                                <div class="table-responsive">
                                    <table id="table-modulos" class="table table-hover table-border">
                                        <thead class="text-light table-head-usu mt-2">
                                            <tr>
                                                <th>Acciones</th>
                                                <th>#</th>
                                                <th>Descripción</th>
                                                <th>Icono</th>
                                                <th>Ruta</th>
                                                <th>Rama</th>
                                                <th>Orden</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tb_cuerpo_modulos" style="font-size: 0.9rem !important;">
                                            <?php
                                            $consulta = mysqli_query($general, "SELECT * FROM tb_modulos WHERE estado=1 ORDER BY id ASC");
                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id = $row["id"];
                                                $descripcion = $row["descripcion"];
                                                $icon = $row["icon"];
                                                $ruta = $row["ruta"];
                                                $rama = $row["rama"];
                                                $orden = $row["orden"];
                                            ?>
                                                <!-- seccion de datos -->
                                                <tr>
                                                    <td>
                                                        <button type="button" class="btn btn-success btn-sm" onclick="printdiv5('id,descripcion,icon,ruta,rama,orden/A,A,A,A,A,A/'+'/#/#/#/#',['<?= $id ?>','<?= $descripcion ?>','<?= $icon ?>','<?= $ruta ?>','<?= $rama ?>','<?= $orden ?>']);  HabDes_boton(1);"><i class="fa-solid fa-eye"></i></button>
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="eliminar('<?= $id ?>', 'crud_superadmin', '0', 'delete_modulo')"><i class="fa-solid fa-trash"></i></button>
                                                    </td>
                                                    <th scope="row"><?= $id ?></th>
                                                    <td><?= $descripcion ?></td>
                                                    <td><?= $icon ?></td>
                                                    <td><?= $ruta ?></td>
                                                    <td><?= $rama ?></td>
                                                    <td><?= $orden ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        //Datatable para parametrizacion
                        $(document).ready(function() {
                            convertir_tabla_a_datatable("table-modulos");
                            HabDes_boton(0);
                        });
                    </script>
                </div>
            </div>
        <?php
        }
        break;
    case 'menus_usuarios': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
        ?>
            <!-- Crud para agregar, editar y eliminar usuarios -->
            <input type="text" id="file" value="superadmin_01" style="display: none;">
            <input type="text" id="condi" value="menus_usuarios" style="display: none;">

            <div class="text" style="text-align:center">ADMINISTRACION DE MENÚS</div>
            <div class="card">
                <div class="card-header">Administración de Menús</div>
                <div class="card-body">
                    <div class="text-center mb-2">
                        <h3>Datos del menú</h3>
                    </div>
                    <!-- Seccion de inputs para edicion -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col-12 col-sm-12">
                                <div class="input-group mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modulo" placeholder="Módulo" disabled>
                                        <input type="text" name="" id="id_modulo" hidden>
                                        <label for="modulo">Módulo</label>
                                    </div>
                                    <span type="button" class="input-group-text" id="basic-addon2" title="Buscar módulo" onclick="abrir_modal('#modal_modulos', '#id_modal_hidden', 'id_modulo,modulo/A,2-3/-/#/#/#/#')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                </div>
                            </div>
                        </div>
                        <!-- descripcion e icono -->
                        <div class="row">
                            <div class="col-12 col-sm-12">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="descripcion" placeholder="Descripción">
                                    <input type="text" name="" id="id" hidden>
                                    <label for="descripcion">Descripción</label>
                                </div>
                            </div>
                        </div>
                        <!-- ruta y rama -->
                        <div class="row">
                            <div class="col-12 col-sm-12">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="orden" placeholder="Orden">
                                    <label for="orden">Orden</label>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mb-3" id="modal_footer">
                                <button type="button" class="btn btn-outline-success" id="btGuardar" onclick="obtiene([`descripcion`,`id_modulo`,`modulo`,`orden`],[],[],`create_menu`,`0`,['<?= $codusu; ?>'])">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btEditar" onclick="obtiene([`descripcion`,`id`,`id_modulo`,`modulo`,`orden`],[],[],`update_menu`,`0`,['<?= $codusu; ?>'])">
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
                    </div>

                    <!-- tabla de usuarios -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row mt-2 pb-2">
                            <div class="col">
                                <div class="table-responsive">
                                    <table id="table-menus" class="table table-hover table-border">
                                        <thead class="text-light table-head-usu mt-2">
                                            <tr>
                                                <th>Acciones</th>
                                                <th>#</th>
                                                <th>Descripción</th>
                                                <th>Módulo</th>
                                                <th>Rama</th>
                                                <th>Orden</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tb_cuerpo_menus" style="font-size: 0.9rem !important;">
                                            <?php
                                            $consulta = mysqli_query($general, "SELECT ms.id AS id, ms.descripcion AS descripcion, ts.descripcion AS nommod, ts.rama AS rama, ts.id AS id_modulo, ms.orden AS orden
                                            FROM tb_menus ms INNER JOIN tb_modulos ts ON ms.id_modulo=ts.id  WHERE ms.estado=1 AND ts.estado=1 ORDER BY ts.id, ms.orden ASC");
                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id = $row["id"];
                                                $descripcion = $row["descripcion"];
                                                $nommod = $row["nommod"];
                                                $rama = $row["rama"];
                                                $id_modulo = $row["id_modulo"];
                                                $orden = $row["orden"];
                                            ?>

                                                <!-- seccion de datos -->
                                                <tr>
                                                    <td>
                                                        <button type="button" class="btn btn-success btn-sm" onclick="printdiv5('id,descripcion,modulo,id_modulo,orden/A,A,3-4,A,A/-/#/#/#/#',['<?= $id ?>','<?= $descripcion ?>','<?= $nommod ?>','<?= $rama ?>','<?= $id_modulo ?>','<?= $orden ?>']);  HabDes_boton(1);"><i class="fa-solid fa-eye"></i></button>
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="eliminar('<?= $id ?>', 'crud_superadmin', '0', 'delete_menu')"><i class="fa-solid fa-trash"></i></button>
                                                    </td>
                                                    <th scope="row"><?= $id ?></th>
                                                    <td><?= $descripcion ?></td>
                                                    <td><?= $nommod ?></td>
                                                    <td><?= $rama ?></td>
                                                    <td><?= $orden ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        //Datatable para parametrizacion
                        $(document).ready(function() {
                            convertir_tabla_a_datatable("table-menus");
                            HabDes_boton(0);
                        });
                    </script>
                </div>
            </div>
            <?php include "../../../../src/cris_modales/mdls_modulo.php"; ?>
        <?php
        }
        break;
    case 'submenus_usuarios': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
        ?>
            <!-- Crud para agregar, editar y eliminar usuarios -->
            <input type="text" id="file" value="superadmin_01" style="display: none;">
            <input type="text" id="condi" value="submenus_usuarios" style="display: none;">

            <div class="text" style="text-align:center">ADMINISTRACION DE SUBMENÚS</div>
            <div class="card">
                <div class="card-header">Administración de Submenús</div>
                <div class="card-body">
                    <div class="text-center mb-2">
                        <h3>Datos del submenú</h3>
                    </div>
                    <!-- Seccion de inputs para edicion -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <!-- modulo y submenu -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="input-group mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="menu" placeholder="Menú" disabled>
                                        <input type="text" name="" id="id_menu" hidden>
                                        <input type="text" name="" id="id" hidden>
                                        <label for="menu">Menú</label>
                                    </div>
                                    <span type="button" class="input-group-text" id="basic-addon2" title="Buscar menú" onclick="abrir_modal('#modal_menus', '#id_modal_hidden', 'id_menu,menu/A,2-4/-/#/#/#/#')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3 mt-2">
                                    <input type="text" class="form-control" id="tcondi" placeholder="Condición">
                                    <label for="tcondi">Condición</label>
                                </div>
                            </div>
                        </div>
                        <!-- condi y file -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="archivo" placeholder="Archivo">
                                    <label for="archivo">Archivo</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="caption" placeholder="Texto de opción">
                                    <label for="caption">Texto de opción</label>
                                </div>
                            </div>
                        </div>
                        <!-- ruta y rama -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="desarrollo" aria-label="Porcentaje de desarrollo">
                                        <option selected value="0">Selecciona porcentaje de desarrollo</option>
                                        <option value=1>0%</option>
                                        <option value=2>20%</option>
                                        <option value=3>40%</option>
                                        <option value=4>60%</option>
                                        <option value=5>80%</option>
                                        <option value=6>100%</option>
                                    </select>
                                    <label for="desarrollo">Porcentaje de desarrollo</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="orden" placeholder="Orden">
                                    <label for="orden">Orden</label>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mb-3" id="modal_footer">
                                <button type="button" class="btn btn-outline-success" id="btGuardar" onclick="obtiene([`id_menu`,`menu`,`tcondi`,`archivo`,`caption`,`orden`],[`desarrollo`],[],`create_submenu`,`0`,['<?= $codusu; ?>'])">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btEditar" onclick="obtiene([`id_menu`,`menu`,`tcondi`,`archivo`,`caption`,`id`,`orden`],[`desarrollo`],[],`update_submenu`,`0`,['<?= $codusu; ?>'])">
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
                                                <th>Módulo</th>
                                                <th>Menú</th>
                                                <th>Condición</th>
                                                <th>Archivo</th>
                                                <th>Texto</th>
                                                <th>Orden</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tb_cuerpo_submenus" style="font-size: 0.9rem !important;">
                                            <?php
                                            $consulta = mysqli_query($general, "SELECT ts.id, td.id AS id_modulo, td.descripcion AS modulo, td.rama, tm.id AS id_menu, tm.descripcion AS menu, ts.condi AS condicion, ts.`file` AS archivo, ts.caption AS texto, ts.desarrollo, ts.orden AS orden FROM tb_submenus ts
                                            INNER JOIN tb_menus tm ON ts.id_menu=tm.id
                                            INNER JOIN tb_modulos td ON tm.id_modulo=td.id
                                            WHERE ts.estado='1' AND tm.estado='1' AND td.estado='1'
                                            ORDER BY ts.id ASC");
                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id = $row["id"];
                                                $modulo = $row["modulo"];
                                                $id_modulo = $row["id_modulo"];
                                                $menu = $row["menu"];
                                                $rama = $row["rama"];
                                                $id_menu = $row["id_menu"];
                                                $condicion = $row["condicion"];
                                                $archivo = $row["archivo"];
                                                $texto = $row["texto"];
                                                $desarrollo = $row["desarrollo"];
                                                $orden = $row["orden"];
                                                $textodesarrollo = "";
                                                $colorestado = "";
                                                if ($desarrollo == '1') {
                                                    $textodesarrollo = "0%";
                                                    $colorestado = "btn btn-danger";
                                                } elseif ($desarrollo == '2') {
                                                    $textodesarrollo = "20%";
                                                    $colorestado = "btn btn-danger";
                                                } elseif ($desarrollo == '3') {
                                                    $textodesarrollo = "40%";
                                                    $colorestado = "btn btn-danger";
                                                } elseif ($desarrollo == '4') {
                                                    $textodesarrollo = "60%";
                                                    $colorestado = "btn btn-warning";
                                                } elseif ($desarrollo == '5') {
                                                    $textodesarrollo = "80%";
                                                    $colorestado = "btn btn-warning";
                                                } elseif ($desarrollo == '6') {
                                                    $textodesarrollo = "100%";
                                                    $colorestado = "btn btn-success";
                                                }
                                            ?>
                                                <!-- seccion de datos -->
                                                <tr>
                                                    <td>
                                                        <button type="button" class="btn btn-success btn-sm" onclick="printdiv5('id,id_menu,menu,tcondi,archivo,caption,desarrollo,orden/A,A,3-5,A,A,A,A,A/-/#/#/#/#',['<?= $id ?>','<?= $id_menu ?>','<?= $menu ?>','<?= $modulo ?>','<?= $rama ?>','<?= $condicion ?>','<?= $archivo ?>','<?= $texto ?>','<?= $desarrollo ?>','<?= $orden ?>']);  HabDes_boton(1);"><i class="fa-solid fa-eye"></i></button>
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="eliminar('<?= $id ?>', 'crud_superadmin', '0', 'delete_submenu')"><i class="fa-solid fa-trash"></i></button>
                                                    </td>
                                                    <th scope="row"><?= $id ?></th>
                                                    <td><?= $modulo ?></td>
                                                    <td><?= $menu ?></td>
                                                    <td><?= $condicion ?></td>
                                                    <td><?= $archivo ?></td>
                                                    <td><?= $texto ?></td>
                                                    <td><?= $orden ?></td>
                                                    <td> <span class="<?= $colorestado; ?>" style="cursor: default"> <?= $textodesarrollo ?></span></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
            <?php // include "../../../../src/cris_modales/mdls_modulo.php"; 
            ?>
            <?php include "../../../../src/cris_modales/mdls_menu.php"; ?>

<?php
        }
        break;
        case 'Conf_mod': {
            $codusuario  = $_SESSION['id'];
            $id_usuario  = $_POST['xtra'];

            // 1) Abrir conexión a la BD principal
            $database->openConnection(1);

            // 2) Cargar listas para selects
            $modulos = $database->selectEspecial(
                "SELECT id, descripcion
                   FROM {$db_name}.tb_modulos
                  WHERE estado = 1
                    AND descripcion IN ('Ahorros','Aportaciones','Créditos','Otros')",
                [],
                2
            );

            // Módulos adicionales para la opción "Otros"
            $modulosOtros = $database->selectEspecial(
                "SELECT id, descripcion
                   FROM {$db_name}.tb_modulos
                  WHERE estado = 1
                    AND descripcion NOT IN ('Ahorros','Aportaciones','Créditos','Otros')",
                [],
                2
            );
            $usuarios = $database->selectAll('tb_usuarios');
            $agencias = $database->selectAll('tb_agencias');

            // 3) Cargar configuraciones existentes (solo las activas)
            $configs = $database->selectEspecial(
                'SELECT * FROM tb_configuraciones_documentos WHERE deleted_at IS NULL',
                [],
                2
            );
            // Opcional: construir un smap id_modulo → descripción
            // Mapear todos los módulos para mostrar descripciones
            $mapMod = array_column(array_merge($modulos, $modulosOtros), 'descripcion', 'id');

            // 4) Construir maps para mostrar nombres en lugar de IDs
            $mapUsuarios = array_column($usuarios, 'nombre', 'id_usuario');
            $mapAgencias = array_column($agencias, 'nombre_agencia', 'id');
        ?>
            <div class="container-fluid mt-4">
                <!-- Campos indispensables (ocultos) -->
                <input type="text" id="file" value="superadmin_01" style="display: none;">
                <input type="text" id="condi" value="Conf_mod" style="display: none;">

                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h4 class="mb-0">
                                    <i class="fas fa-cogs"></i> Configuración de Correlativos de Documentos
                                </h4>
                                <small class="text-light">Gestiona los correlativos para cada módulo, usuario y agencia</small>
                            </div>

                            <div class="card-body">
                                <!-- Formulario de configuración -->
                                <form id="formConfMod" method="post" action="../../../../src/cruds/crud_config_docs.php">
                                    <!-- Campos indispensables para el formulario -->
                                    <input type="hidden" name="file" value="superadmin_02">
                                    <input type="hidden" name="condi" value="Conf_mod">
                                    <input type="hidden" name="xtra" value="<?= htmlspecialchars($id_usuario) ?>">
                                    <input type="hidden" name="action" value="guardar">
                                    <input type="hidden" name="config_id" id="config_id" value="">

                                    <div class="row g-3">
                                        <input type="hidden" id="id_modulo" name="id_modulo" value="">
                                        <div class="col-md-6 col-lg-4">
                                            <label for="id_modulo_sel" class="form-label">
                                                <i class="fas fa-puzzle-piece text-primary"></i> Módulo
                                            </label>
                                            <select class="form-select" id="id_modulo_sel">
                                                <option value="">— Todos los módulos —</option>
                                                <?php foreach ($modulos as $m): ?>
                                                    <option value="<?= $m['id'] ?>">
                                                        <?= htmlspecialchars($m['descripcion']) ?>
                                                    </option>
                                                <?php endforeach ?>
                                                <option value="otros">Otros...</option>
                                            </select>
                                            <div class="form-text">Selecciona un módulo específico o deja vacío para todos</div>
                                        </div>

                                        <div class="col-md-6 col-lg-4 d-none" id="cont_modulo_otro">
                                            <label for="modulo_otro" class="form-label">
                                                <i class="fas fa-puzzle-piece text-primary"></i> Otro Módulo
                                            </label>
                                            <select class="form-select" id="modulo_otro">
                                                <option value="">— Seleccione —</option>
                                                <?php foreach ($modulosOtros as $mo): ?>
                                                    <option value="<?= $mo['id'] ?>">
                                                        <?= htmlspecialchars($mo['descripcion']) ?>
                                                    </option>
                                                <?php endforeach ?>
                                            </select>
                                            <div class="form-text">Módulos adicionales</div>
                                        </div>

                                        <div class="col-md-6 col-lg-4">
                                            <label for="tipo" class="form-label">
                                                <i class="fas fa-exchange-alt text-success"></i> Tipo de Operación
                                            </label>
                                            <select class="form-select" id="tipo" name="tipo">
                                                <option value="">— Ambos tipos —</option>
                                                <option value="INGRESO">
                                                    <i class="fas fa-arrow-down"></i> Ingreso
                                                </option>
                                                <option value="EGRESO">
                                                    <i class="fas fa-arrow-up"></i> Egreso
                                                </option>
                                            </select>
                                            <div class="form-text">Tipo de operación: Ingreso, Egreso o ambos</div>
                                        </div>

                                        <div class="col-md-6 col-lg-4">
                                            <label for="valor_actual" class="form-label">
                                                <i class="fas fa-hashtag text-warning"></i> Valor Actual del Correlativo
                                            </label>
                                            <input type="number" class="form-control" id="valor_actual" name="valor_actual"
                                                value="0" min="0" step="1" required>
                                            <div class="form-text">Número actual del correlativo (mínimo 0)</div>
                                        </div>

                                        <div class="col-md-6 col-lg-4">
                                            <label for="usuario_id" class="form-label">
                                                <i class="fas fa-user text-info"></i> Usuario Específico
                                            </label>
                                            <select class="form-select" id="usuario_id" name="usuario_id">
                                                <option value="">— Todos los usuarios —</option>
                                                <?php foreach ($usuarios as $u): ?>
                                                    <option value="<?= $u['id_usuario'] ?>">
                                                        <?= htmlspecialchars($u['nombre']) ?>
                                                    </option>
                                                <?php endforeach ?>
                                            </select>
                                            <div class="form-text">Asignar correlativo a un usuario específico</div>
                                        </div>



                                        <div class="col-md-6 col-lg-4">
                                            <label for="agencia_id" class="form-label">
                                                <i class="fas fa-building text-secondary"></i> Agencia Específica
                                            </label>
                                            <select class="form-select" id="agencia_id" name="agencia_id">
                                                <option value="">— Todas las agencias —</option>
                                                <?php foreach ($agencias as $a): ?>
                                                    <option value="<?= $a['id'] ?>">
                                                        <?= htmlspecialchars($a['nombre_agencia'] ?? $a['descripcion'] ?? 'Agencia #' . $a['id']) ?>
                                                    </option>
                                                <?php endforeach ?>
                                            </select>
                                            <div class="form-text">Asignar correlativo a una agencia específica</div>
                                        </div>

                                        <div class="col-md-6 col-lg-4 d-flex align-items-end">
                                            <div class="w-100">
                                                <button type="submit" class="btn btn-primary w-100" id="btnGuardar">
                                                    <i class="fas fa-save"></i> Guardar Configuración
                                                </button>
                                                <button type="button" class="btn btn-secondary w-100 mt-2" id="btnLimpiar">
                                                    <i class="fas fa-broom"></i> Limpiar Formulario
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Información de ayuda -->
                                    <div class="alert alert-info mt-3" role="alert">
                                        <h6><i class="fas fa-info-circle"></i> Información importante:</h6>
                                        <ul class="mb-0">
                                            <li><strong>Módulos disponibles:</strong> Ahorros, Aportaciones, Créditos y Otros</li>
                                            <li><strong>Otros módulos:</strong> Clientes, Crédito Individual, Crédito Grupal, Caja, Reportes, Contabilidad, Bancos, Admin, Usuarios</li>
                                            <li><strong>Tipos:</strong> Ingreso (depósitos, pagos) / Egreso (retiros, desembolsos)</li>
                                            <li><strong>Prioridad:</strong> Usuario específico > Agencia específica > General</li>
                                            <li><strong>Correlativo:</strong> Se incrementa automáticamente con cada operación</li>
                                        </ul>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de configuraciones existentes -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i> Configuraciones Existentes
                                </h5>
                                <span class="badge bg-light text-dark"><?= count($configs) ?> configuraciones</span>
                            </div>

                            <div class="card-body p-0">
                                <?php if (empty($configs)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No hay configuraciones</h5>
                                        <p class="text-muted">Crea tu primera configuración de correlativo usando el formulario anterior</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover mb-0">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th width="60">#</th>
                                                    <th><i class="fas fa-puzzle-piece"></i> Módulo</th>
                                                    <th><i class="fas fa-exchange-alt"></i> Tipo</th>
                                                    <th><i class="fas fa-hashtag"></i> Valor Actual</th>
                                                    <th><i class="fas fa-user"></i> Usuario</th>
                                                    <th><i class="fas fa-building"></i> Agencia</th>
                                                    <th width="200"><i class="fas fa-cogs"></i> Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($configs as $c): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-primary"><?= $c['id'] ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($c['id_modulo']): ?>
                                                                <span class="badge bg-info">
                                                                    <?= htmlspecialchars($mapMod[$c['id_modulo']] ?? 'Módulo #' . $c['id_modulo']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-globe"></i> Todos los módulos
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($c['tipo']): ?>
                                                                <?php if ($c['tipo'] == 'INGRESO'): ?>
                                                                    <span class="badge bg-success">
                                                                        <i class="fas fa-arrow-down"></i> <?= htmlspecialchars($c['tipo']) ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning">
                                                                        <i class="fas fa-arrow-up"></i> <?= htmlspecialchars($c['tipo']) ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-arrows-alt-v"></i> Ambos tipos
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong class="text-primary"><?= number_format($c['valor_actual']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($c['usuario_id']): ?>
                                                                <span class="badge bg-info">
                                                                    <i class="fas fa-user"></i>
                                                                    <?= htmlspecialchars($mapUsuarios[$c['usuario_id']] ?? 'Usuario #' . $c['usuario_id']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">
                                                                    <i class="fas fa-users"></i> Todos
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($c['agencia_id']): ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-building"></i>
                                                                    <?= htmlspecialchars($mapAgencias[$c['agencia_id']] ?? 'Agencia #' . $c['agencia_id']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">
                                                                    <i class="fas fa-globe"></i> Todas
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button class="btn btn-sm btn-outline-primary editar"
                                                                    data-id="<?= $c['id'] ?>"
                                                                    data-modulo="<?= $c['id_modulo'] ?>"
                                                                    data-tipo="<?= $c['tipo'] ?>"
                                                                    data-valor="<?= $c['valor_actual'] ?>"
                                                                    data-usuario="<?= $c['usuario_id'] ?>"
                                                                    data-agencia="<?= $c['agencia_id'] ?>"
                                                                    title="Editar configuración">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger eliminar"
                                                                    data-id="<?= $c['id'] ?>"
                                                                    title="Eliminar configuración">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de confirmación para eliminar -->
            <div class="modal fade" id="modalEliminar" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>¿Está seguro de que desea eliminar esta configuración de correlativo?</p>
                            <p class="text-muted">Esta acción no se puede deshacer.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-danger" id="confirmarEliminar">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                $(document).ready(function() {
                    let configIdToDelete = null;

                    $('#id_modulo_sel').change(function() {
                        if ($(this).val() === 'otros') {
                            $('#cont_modulo_otro').removeClass('d-none');
                            $('#id_modulo').val($('#modulo_otro').val());
                        } else {
                            $('#cont_modulo_otro').addClass('d-none');
                            $('#modulo_otro').val('');
                            $('#id_modulo').val($(this).val());
                        }
                    });

                    $('#modulo_otro').change(function() {
                        if ($('#id_modulo_sel').val() === 'otros') {
                            $('#id_modulo').val($(this).val());
                        }
                    });

                    // Limpiar formulario
                    $('#btnLimpiar').click(function() {
                        $('#formConfMod')[0].reset();
                        $('#config_id').val('');
                        $('#btnGuardar').html('<i class="fas fa-save"></i> Guardar Configuración');
                        $('#valor_actual').val('0');
                        $('#cont_modulo_otro').addClass('d-none');
                        $('#id_modulo_sel').val('');
                        $('#modulo_otro').val('');
                        $('#id_modulo').val('');

                    });

                    // Editar configuración
                    $('.editar').click(function() {
                        const data = $(this).data();

                        $('#config_id').val(data.id);
                        const mainIds = <?= json_encode(array_column($modulos, 'id')); ?>;
                        if (mainIds.includes(parseInt(data.modulo))) {
                            $('#id_modulo_sel').val(data.modulo);
                            $('#cont_modulo_otro').addClass('d-none');
                            $('#modulo_otro').val('');
                            $('#id_modulo').val(data.modulo);
                        } else if (data.modulo) {
                            $('#id_modulo_sel').val('otros');
                            $('#cont_modulo_otro').removeClass('d-none');
                            $('#modulo_otro').val(data.modulo);
                            $('#id_modulo').val(data.modulo);
                        } else {
                            $('#id_modulo_sel').val('');
                            $('#cont_modulo_otro').addClass('d-none');
                            $('#modulo_otro').val('');
                            $('#id_modulo').val('');
                        }
                        $('#tipo').val(data.tipo || '');
                        $('#valor_actual').val(data.valor || 0);
                        $('#usuario_id').val(data.usuario || '');
                        $('#agencia_id').val(data.agencia || '');

                        $('#btnGuardar').html('<i class="fas fa-edit"></i> Actualizar Configuración');

                        // Scroll al formulario
                        $('html, body').animate({
                            scrollTop: $('#formConfMod').offset().top - 100
                        }, 500);
                    });

                    // Eliminar configuración
                    $('.eliminar').click(function() {
                        configIdToDelete = $(this).data('id');
                        $('#modalEliminar').modal('show');
                    });

                    // Confirmar eliminación
                    $('#confirmarEliminar').click(function() {
                        if (configIdToDelete) {
                            $.ajax({
                                url: '../../../../src/cruds/crud_config_docs.php',
                                method: 'POST',
                                data: {
                                    action: 'eliminar',
                                    config_id: configIdToDelete,
                                    ajax: 1
                                },
                                beforeSend: function() { loaderefect(1); },
                                success: function(resp) {
                                    let res = {};
                                    try { res = JSON.parse(resp); } catch (e) { res = {status:0,msg:'Error inesperado'}; }
                                    if (res.status == 1) {
                                        Swal.fire('Correcto', res.msg, 'success');
                                        $('#modalEliminar').modal('hide');
                                        printdiv2('#cuadro', $('input[name="xtra"]').val());
                                    } else {
                                        Swal.fire('Error', res.msg, 'error');
                                    }
                                },
                                error: function() {
                                    Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                                },
                                complete: function() { loaderefect(0); }
                            });
                        }
                    });

                    // Envío del formulario por AJAX
                    $('#formConfMod').submit(function(e) {
                        e.preventDefault();

                        if ($('#id_modulo_sel').val() === 'otros') {
                            $('#id_modulo').val($('#modulo_otro').val());
                        } else {
                            $('#id_modulo').val($('#id_modulo_sel').val());
                        }

                        const valorActual = parseInt($('#valor_actual').val());
                        if (valorActual < 0) {
                            Swal.fire('Advertencia', 'El valor actual del correlativo debe ser mayor o igual a 0', 'warning');
                            $('#valor_actual').focus();
                            return false;
                        }

                        if (valorActual > 100000) {
                            if (!confirm('El valor del correlativo es muy alto (' + valorActual.toLocaleString() + '). ¿Está seguro?')) {
                                return false;
                            }
                        }

                        const dataForm = $(this).serialize() + '&ajax=1';
                        $.ajax({
                            url: '../../../../src/cruds/crud_config_docs.php',
                            method: 'POST',
                            data: dataForm,
                            beforeSend: function() { loaderefect(1); },
                            success: function(resp) {
                                let res = {};
                                try { res = JSON.parse(resp); } catch (e) { res = {status:0,msg:'Error inesperado'}; }
                                if (res.status == 1) {
                                    Swal.fire('Muy Bien!', res.msg, 'success');
                                    printdiv2('#cuadro', $('input[name="xtra"]').val());
                                } else {
                                    Swal.fire('Atención', res.msg, 'warning');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                            },
                            complete: function() { loaderefect(0); }
                        });
                    });

                    // Tooltips de Bootstrap
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
                    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                });
            </script>

            <style>
                .card {
                    border: none;
                    border-radius: 10px;
                }

                .card-header {
                    border-radius: 10px 10px 0 0 !important;
                }

                .table th {
                    border-top: none;
                    font-weight: 600;
                    font-size: 0.9rem;
                }

                .badge {
                    font-size: 0.8rem;
                }

                .btn-group .btn {
                    border-radius: 4px;
                    margin: 0 2px;
                }

                .alert-info {
                    border-left: 4px solid #0dcaf0;
                    background-color: #e7f3ff;
                    border-color: #b3d9ff;
                }

                .form-text {
                    font-size: 0.8rem;
                    color: #6c757d;
                }

                @media (max-width: 768px) {
                    .container-fluid {
                        padding: 0.5rem;
                    }

                    .card-body {
                        padding: 1rem;
                    }

                    .btn-group {
                        display: flex;
                        flex-direction: column;
                    }

                    .btn-group .btn {
                        margin: 1px 0;
                        border-radius: 4px;
                    }
                }
            </style>

    <?php
            $database->closeConnection();
        }
    break;

       

}
?>