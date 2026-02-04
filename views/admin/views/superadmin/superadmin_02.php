<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();
require_once __DIR__ . '/../../../../vendor/autoload.php';
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';

require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$condi = $_POST["condi"];

use App\Generic\Agencia;
use App\Generic\Institucion;
use Micro\Helpers\Log;
use CzProject\GitPhp\Git;

switch ($condi) {
    case 'adm_per': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
?>

            <!-- Crud para agregar, editar y eliminar usuarios -->
            <input type="text" id="adm_per" value="adm_per" style="display: none;">
            <input type="text" id="condi" value="permisos_usuarios" style="display: none;">

            <div class="text" style="text-align:center">ASIGNACIÓN DE PERMISOS</div>
            <div class="card mb-2">
                <div class="container contenedort" style="max-width: 100% !important;">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2"><b>Agregar nuevo Permiso.</b></div>
                        </div>
                    </div>
                    <!-- cargo, nombre agencia y codagencia  -->
                    <div class="row">
                        <?php
                        $consult = "SELECT modulo_area,estado FROM $db_name_general.tb_restringido WHERE estado='1'";
                        $result = $conexion->query($consult);
                        ?>
                        <div id="Select_modules" class="col-15 col-sm-8 col-md-6" style="display: none;">
                            <div class="form-floating mb-3 mt-2">
                                <select class="form-select" id="update_estado">
                                    <option value="">Seleccione un modulo</option>
                                    <?php
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . $row["modulo_area"] . '">' . $row["modulo_area"] . '</option>';
                                        }
                                    } else {
                                        echo '<option value="" disabled> Error  </option>';
                                    }
                                    ?>
                                </select>
                                <label for="update_estado">Estado</label>
                            </div>
                        </div>

                        <style>
                            .custom-btn {
                                width: 380px;
                            }
                        </style>

                        <div id="inputNombre" class="col-12 col-sm-6 col-md-6">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="Nombre" placeholder="modulo_area">
                                <label for="Nombre">Nombre</label>
                            </div>
                        </div>
                        <div class="col-15 col-sm-8 col-md-6">
                            <div class="form-floating mb-3 mt-2">
                                <select class="form-select" id="estado">
                                    <option value="">Selccione una opcion</option>
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                                <label for="estado">Estado</label>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm custom-btn" id="btBuscar"
                            onclick="replaceInputs()">
                            <i class="fa-solid fa-magnifying-glass"></i> Buscar
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm custom-btn" id="btGuardar"
                            onclick="new_permise('create_permiso')">
                            <i class="fa-solid fa-floppy-disk"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm custom-btn" id="btActualizar"
                            style="display: none;" onclick="update_permise('update_permiso')">
                            <i class="fa-solid fa-pen"></i> Actualizar
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm custom-btn" onclick="cancelReplace()">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>


                <div class="card-body">
                    <!-- Seccion de informacion de usuario -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <h2>Asignar Permisos</h2>

                        <!-- usuario y boton buscar -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="usuario" placeholder="Nombre de usuario" disabled>
                                    <input type="text" id="id_usuario" hidden>
                                    <input type="text" id="id_cargo" hidden>
                                    <input type="text" id="id_usuario_past" hidden>
                                    <label for="cliente">Nombre de usuario</label>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                                    onclick="abrir_modal('#modal_users', '#id_modal_hidden', 'id_usuario,usuario,cargo,nomagencia,codagencia,id_cargo/A,A,A,A,A,A/'+'/#/#/#/#/#/#')"><i
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
                            <!-- asignar permisos  -->
                            <div class="col-12 col-sm-6 col-md-6">
                                <div class="form-floating mb-3 mt-2">
                                    <select class="form-select" id="update_estado2">
                                        <option value="">Seleccione un módulo</option>
                                    </select>
                                    <label for="update_estado2">Módulos</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-66">
                                <div class="form-floating mb-3 mt-2">
                                    <select class="form-select" id="value_estado">
                                        <option value="">Selccione una opcion</option>
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                    <label for="value_estado">Estado</label>
                                </div>
                            </div>
                        </div>

                        <div class="col align-items-center mt-2" id="modal_footer">
                            <button type="button" class="btn btn-outline-success" id="btGuardar"
                                onclick="create_permisos('create_permisos')">
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

                        function cargarModulos() {
                            $.ajax({
                                url: "../../src/cruds/crud_usuario.php",
                                method: "POST",
                                data: {
                                    'condi': 'obtener_modulos_restringidos'
                                },
                                beforeSend: function() {
                                    loaderefect(1);
                                    // console.log('Iniciando carga de módulos...');
                                },
                                success: function(data) {
                                    try {
                                        // console.log('Datos recibidos:', data);
                                        const response = JSON.parse(data);
                                        const $select = $('#update_estado2');

                                        // Limpiar el select
                                        $select.empty();
                                        $select.append('<option value="">Seleccione un módulo</option>');

                                        if (response[1] === "1") {
                                            // Agregar las opciones al select
                                            response[0].forEach(function(modulo) {
                                                $select.append(
                                                    `<option value="${modulo.id}">${modulo.modulo_area}</option>`
                                                );
                                            });
                                            // console.log('Módulos cargados exitosamente:', response[0]);

                                            // Opcional: Mostrar mensaje de éxito
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Éxito',
                                                text: 'Módulos cargados correctamente',
                                                timer: 1500,
                                                showConfirmButton: false
                                            });
                                        } else {
                                            // console.error('Error al cargar módulos:', response[0]);
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Error',
                                                text: response[0] || 'Error al cargar los módulos'
                                            });
                                        }
                                    } catch (error) {
                                        // console.error('Error al procesar la respuesta:', error);
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: 'Error al procesar la respuesta del servidor'
                                        });
                                    }
                                },
                                error: function(xhr, status, error) {
                                    // console.error('Error en la petición Ajax:', {
                                    //     status: status,
                                    //     error: error,
                                    //     response: xhr.responseText
                                    // });
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error de conexión',
                                        text: 'No se pudo conectar con el servidor'
                                    });
                                },
                                complete: function() {
                                    loaderefect(0);
                                    console.log('Proceso de carga completado');
                                }
                            });
                        }

                        // Configuración inicial y eventos
                        $(document).ready(function() {
                            // Cargar módulos al iniciar la página
                            cargarModulos();

                            // Event listener para el select
                            $('#update_estado2').on('change', function() {
                                const selectedValue = $(this).val();
                                const selectedText = $(this).find('option:selected').text();

                                // console.log('Cambio en select:', {
                                //     valor: selectedValue,
                                //     texto: selectedText
                                // });

                                // Aquí puedes agregar lógica adicional cuando se seleccione un módulo
                                if (selectedValue) {
                                    // console.log('Módulo seleccionado:', {
                                    //     id: selectedValue,
                                    //     nombre: selectedText
                                    // });
                                }
                            });

                            // Botón para recargar módulos (si lo necesitas)
                            $('#btnRecargarModulos').on('click', function() {
                                // console.log('Recargando módulos...');
                                cargarModulos();
                            });

                            // Monitoreo de peticiones Ajax
                            $(document).ajaxSend(function(event, xhr, settings) {
                                if (settings.url.includes('crud_usuario.php')) {
                                    // console.log('Iniciando petición Ajax:', {
                                    //     url: settings.url,
                                    //     data: settings.data
                                    // });
                                }
                            });

                            $(document).ajaxComplete(function(event, xhr, settings) {
                                if (settings.url.includes('crud_usuario.php')) {
                                    // console.log('Petición Ajax completada:', {
                                    //     status: xhr.status,
                                    //     responseText: xhr.responseText
                                    // });

                                    const $select = $('#update_estado2');
                                    // console.log('Estado final del select:', {
                                    //     numeroOpciones: $select.find('option').length,
                                    //     valorSeleccionado: $select.val(),
                                    //     textoSeleccionado: $select.find('option:selected').text()
                                    // });
                                }
                            });

                            // Función para validar el estado del select
                            function validarEstadoSelect() {
                                const $select = $('#update_estado2');
                                const estadoSelect = {
                                    existe: $select.length > 0,
                                    visible: $select.is(':visible'),
                                    deshabilitado: $select.is(':disabled'),
                                    numeroOpciones: $select.find('option').length,
                                    valorActual: $select.val(),
                                    opcionesDisponibles: []
                                };

                                $select.find('option').each(function() {
                                    estadoSelect.opcionesDisponibles.push({
                                        valor: $(this).val(),
                                        texto: $(this).text()
                                    });
                                });

                                // console.log('Estado del select:', estadoSelect);
                                return estadoSelect;
                            }

                            // Verificar estado inicial del select
                            // console.log('Estado inicial del select:', validarEstadoSelect());

                            // Observador para cambios en el DOM del select
                            const observer = new MutationObserver(function(mutations) {
                                mutations.forEach(function(mutation) {
                                    // console.log('Cambio detectado en el select:', {
                                    //     tipo: mutation.type,
                                    //     target: mutation.target,
                                    //     addedNodes: mutation.addedNodes.length,
                                    //     removedNodes: mutation.removedNodes.length
                                    // });
                                    validarEstadoSelect();
                                });
                            });

                            // Iniciar observación del select
                            const selectElement = document.getElementById('update_estado2');
                            if (selectElement) {
                                observer.observe(selectElement, {
                                    attributes: true,
                                    childList: true,
                                    subtree: true
                                });
                            }

                            // Manejar errores globales de Ajax
                            $(document).ajaxError(function(event, xhr, settings, error) {
                                // console.error('Error global Ajax:', {
                                //     url: settings.url,
                                //     error: error,
                                //     status: xhr.status,
                                //     statusText: xhr.statusText,
                                //     responseText: xhr.responseText
                                // });
                            });
                        });

                        // Función auxiliar para recargar módulos (si se necesita desde fuera)
                        function recargarModulos() {
                            // console.log('Solicitando recarga de módulos...');
                            cargarModulos();
                        }

                        // Función para validar si un módulo está seleccionado
                        function validarSeleccionModulo() {
                            const $select = $('#update_estado2');
                            const valorSeleccionado = $select.val();

                            if (!valorSeleccionado) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Atención',
                                    text: 'Por favor, seleccione un módulo'
                                });
                                return false;
                            }
                            return true;
                        }
                    </script>
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Usuarios con permisos</b></div>
                            </div>
                        </div>
                        <!-- here -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row mt-2 pb-2">
                                <div class="col">
                                    <div class="table-responsive">
                                        <table id="table-utorizacion" class="table table-hover table-border">
                                            <thead class="text-light table-head-usu mt-2">
                                                <tr>
                                                    <th>Acciones</th>
                                                    <th>#</th>
                                                    <th>Usuario</th>
                                                    <th>apellido</th>
                                                    <th> rol</th>
                                                    <th>Cant. Permisos</th>

                                                </tr>
                                            </thead>
                                            <tbody id="tb_cuerpo_submenus" style="font-size: 0.9rem !important;">
                                                <?php
                                                //consulta que filtra 1 usuario y muestras cuantos permisos tiene asignado en estado 1 / 0
                                                $consulta = mysqli_query($conexion, "SELECT 
                                            ta.id_usuario AS id, 
                                            COUNT(ta.id_usuario) AS cantidad_permisos,
                                            MAX(ta.id) AS id_2,
                                            MAX(ta.id_restringido) AS id_restringido,
                                            MAX(ta.id_rol) AS id_rol,
                                            MAX(tu.id_usu) AS id_usu,
                                            MAX(tu.nombre) AS nombre,
                                            MAX(tu.apellido) AS apellido,
                                            MAX(tu.estado) AS estado,
                                            MAX(tu.puesto) AS puesto,
                                            MAX(tu.id_agencia) AS id_agencia,
                                            MAX(tr.modulo_area) AS modulo_area
                                        FROM 
                                            tb_autorizacion AS ta
                                        INNER JOIN 
                                            tb_usuario AS tu ON ta.id_usuario = tu.id_usu
                                        INNER JOIN 
                                            $db_name_general.tb_restringido AS tr ON ta.id_restringido = tr.id
                                        GROUP BY
                                            ta.id_usuario;");

                                                while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                    $id = $row["id"];
                                                    $nombre = $row["nombre"];
                                                    $apellido = $row["apellido"];
                                                    $rol = $row["puesto"];
                                                    $restringido = $row["cantidad_permisos"];

                                                    if ($_SESSION['id'] == 4) { ?>
                                                        <!-- seccion de datos -->
                                                        <tr>
                                                            <td>
                                                                <button type="button" class="btn btn-outline-success btn-sm*2"
                                                                    onclick="viewAcordeon(<?= $id ?>, '<?= $nombre ?>' , '<?= $apellido ?>', '<?= $rol ?>', <?= $restringido ?>);">
                                                                    <i class="fa-solid fa-eye"></i>
                                                                </button>
                                                            </td>
                                                            <th scope="row"><?= $id ?></th>
                                                            <td><?= $nombre ?></td>
                                                            <td><?= $apellido ?></td>
                                                            <td><?= $rol ?></td>
                                                            <td><?= $restringido ?></td>
                                                        </tr>

                                                <?php }
                                                } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- VIEW PERMISOS -->
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item d-none" id="acordeon"
                                style="background-color: #82E0AA  ; padding: 20px; border-radius: 10px; margin-bottom: 10px;">
                                <h2 class="accordion-header" id="heading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapse" aria-expanded="false" aria-controls="collapse"
                                        style="font-size: 1.2rem; color: #333;">
                                        <p> Permisos</p>
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" aria-labelledby="heading"
                                    data-bs-parent="#accordionExample" id="collapse">
                                    <div class="accordion-body" style="font-size: 1rem; color: #333;">
                                        <table class="table">
                                            <thead class="thead-dark">
                                                <tr>
                                                    <th scope="col">Id</th>
                                                    <th scope="col">Usuario</th>
                                                    <th scope="col">Apellido</th>
                                                    <th scope="col">Rol</th>
                                                    <th scope="col">Permisos Totales</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><span id="id"></span></td>
                                                    <td><span id="nombre"></span></td>
                                                    <td><span id="apellido"></span></td>
                                                    <td><span id="rol"></span></td>
                                                    <td><span id="restringido"></span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <h3 class="col align-items-center mt-2 d-flex justify-content-center">
                                        </h3>
                                        <!-- tabla de Permisos , se muestra en el crud -->
                                        <div id="table-placeholder"></div>
                                    </div>
                                    <div class="col align-items-center mt-2 d-flex justify-content-center  " id="modal_footer">
                                        <button type="button" class="btn btn-outline-primary" id="btsearch_id"
                                            onclick="search_id('search_id')">
                                            <i class="fa-solid fa-magnifying-glass"></i> Buscar Permisos
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                            <i class="fa-solid fa-circle-xmark"></i> Salir
                                        </button>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            //Datatable para parametrizacion
                            $(document).ready(function() {
                                convertir_tabla_a_datatable("table-utorizacion");
                                HabDes_boton(0);
                            });
                        </script>
                    </div>
                </div>
            </div>
            <!-- Aca van los modales necesarios -->
            <?php include "../../../../src/cris_modales/mdls_users.php"; ?>
        <?php
        }
        break;
    case 'gencredencials': {
            $codusuario = $_SESSION['id'];
            $id_usuario = $_POST['xtra'];
        ?>
            <input type="text" id="file" value="superadmin_02" style="display: none;">
            <input type="text" id="condi" value="gencredencials" style="display: none;">
            <div class="container-fluid mt-4">
                <h4 class="text-center mb-4">Generaci&oacute;n de Credenciales para Banca Virtual</h4>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#buscar_cli_gen">
                                    <i class="fa-solid fa-users"></i> Buscar Cliente
                                </button>
                            </div>
                        </div>
                        <form id="formCredencial">
                            <input type="hidden" name="action" value="crear_credencial">
                            <?php echo $csrf->getTokenField(); ?>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">C&oacute;digo Cliente</label>
                                    <input type="text" id="codcli" name="codcli" class="form-control" readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" id="nombrecli" class="form-control" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Usuario</label>
                                    <input type="text" id="usuario" name="usuario" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contrase&ntilde;a</label>
                                    <input type="text" id="pass" name="pass" class="form-control" required>
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa-solid fa-key"></i> Generar
                                </button>
                            </div>
                        </form>
                        <div class="table-responsive mt-4">
                            <table id="tb_credenciales" class="table table-striped table-sm table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Teléfono</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php include "../../../../src/cris_modales/mdls_cli_select.php"; ?>

            <!-- Modal para editar credencial -->
            <div class="modal fade" id="modalEditarCredencial" tabindex="-1" aria-labelledby="modalEditarCredencialLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEditarCredencialLabel">Editar Credencial</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="formEditarCredencial">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="editar_credencial">
                                <input type="hidden" name="id" id="edit_id">
                                <?php echo $csrf->getTokenField(); ?>
                                <div class="mb-3">
                                    <label class="form-label">Código Cliente</label>
                                    <input type="text" id="edit_codcli" name="codcli" class="form-control" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Usuario</label>
                                    <input type="text" id="edit_usuario" name="usuario" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nueva Contraseña (opcional)</label>
                                    <input type="text" id="edit_pass" name="pass" class="form-control" placeholder="Dejar vacío para mantener la actual">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Actualizar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <script>
                function seleccionar_cliente(datos) {

                    const cod = datos[0];
                    $('#codcli').val(cod);
                    $('#nombrecli').val(datos[1]);
                    const corto = cod.toString().slice(-4);
                    $('#usuario').val('u' + corto);
                    $('#pass').val(Math.random().toString(36).slice(-8));
                }

                // Cargar credenciales con DataTables
                function cargarCredenciales() {
                    $('#tb_credenciales').DataTable({
                        ajax: {
                            url: '../../src/cruds/ajax_credenciales_debug.php',
                            dataSrc: function(json) {
                                // Verificar si hay error
                                if (json.error) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error al cargar datos',
                                        text: json.error,
                                        confirmButtonText: 'Reintentar'
                                    }).then(() => {
                                        location.reload();
                                    });
                                    return [];
                                }
                                return json.data || [];
                            },
                            error: function(xhr, error, thrown) {
                                console.error('Error en AJAX:', error, thrown);
                                console.log('Respuesta:', xhr.responseText);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de conexión',
                                    html: '<p>No se pudieron cargar las credenciales.</p><pre class="text-start small">' + xhr.responseText.substring(0, 500) + '</pre>',
                                    confirmButtonText: 'Cerrar'
                                });
                            }
                        },
                        columns: [
                            { data: 'codcli' },
                            { data: 'nombre' },
                            { data: 'usuario' },
                            { data: 'email_cliente', defaultContent: '<span class="text-muted">Sin email</span>' },
                            { data: 'telefono_cliente', defaultContent: '<span class="text-muted">Sin teléfono</span>' },
                            { data: 'acciones', orderable: false, searchable: false }
                        ],
                        destroy: true,
                        pageLength: 25,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                        language: {
                            lengthMenu: 'Mostrar _MENU_ registros por página',
                            zeroRecords: 'No se encontraron credenciales',
                            info: 'Mostrando página _PAGE_ de _PAGES_ (_TOTAL_ registros)',
                            infoEmpty: 'No hay registros disponibles',
                            infoFiltered: '(filtrado de _MAX_ registros totales)',
                            search: 'Buscar:',
                            paginate: {
                                first: 'Primero',
                                last: 'Último',
                                next: 'Siguiente',
                                previous: 'Anterior'
                            },
                            processing: 'Procesando...',
                            loadingRecords: 'Cargando...',
                            emptyTable: 'No hay credenciales registradas'
                        },
                        responsive: true,
                        order: [[0, 'asc']]
                    });
                }

                $(document).ready(function() {
                    cargarCredenciales();
                });

                // Función para enviar credenciales por correo (abre cliente de correo)
                function enviarPorCorreo(id, email, usuario, codcli) {
                    const asunto = encodeURIComponent('Credenciales de Acceso - Banca Virtual');
                    const cuerpo = encodeURIComponent(`Hola,

A continuación te compartimos tus credenciales para acceder a nuestra plataforma de Banca Virtual:

👤 Usuario: ${usuario}
📱 Código de Cliente: ${codcli}

⚠️ IMPORTANTE:
• Cambia tu contraseña al iniciar sesión por primera vez
• No compartas tus credenciales con nadie
• Si tienes problemas para acceder, contáctanos



Saludos,
Equipo de Banca Virtual`);
                    
                    const mailtoLink = `mailto:${email}?subject=${asunto}&body=${cuerpo}`;
                    
                    Swal.fire({
                        title: 'Enviar credenciales por correo',
                        html: `
                            <p>Se abrirá tu cliente de correo para enviar a:</p>
                            <p class="fw-bold"><i class="fa-solid fa-envelope text-info"></i> ${email}</p>
                            <p>Usuario: <strong>${usuario}</strong></p>
                            <p class="small text-muted">Podrás editar el mensaje antes de enviarlo</p>
                        `,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonColor: '#0dcaf0',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fa-solid fa-envelope"></i> Abrir correo',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = mailtoLink;
                        }
                    });
                }

                // Función para enviar credenciales CON contraseña por correo
                function enviarCredencialesPorCorreo(email, usuario, password, codcli) {
                    const asunto = encodeURIComponent('Credenciales de Acceso - Banca Virtual');
                    const cuerpo = encodeURIComponent(`Hola,

A continuación te compartimos tus credenciales para acceder a nuestra plataforma de Banca Virtual:

👤 Usuario: ${usuario}
🔐 Contraseña: ${password}
📱 Código de Cliente: ${codcli}

⚠️ IMPORTANTE:
• Cambia tu contraseña al iniciar sesión por primera vez
• No compartas tus credenciales con nadie
• Si tienes problemas para acceder, contáctanos


Saludos,
Equipo de Banca Virtual`);
                    
                    const mailtoLink = `mailto:${email}?subject=${asunto}&body=${cuerpo}`;
                    window.location.href = mailtoLink;
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Cliente de correo abierto',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }

                // Función para enviar credenciales por WhatsApp
                function enviarPorWhatsApp(telefono, usuario, codcli) {
                    // Limpiar el teléfono (quitar espacios, guiones, etc.)
                    telefono = telefono.replace(/\D/g, '');
                    
                    // Si no tiene código de país, agregar 502 (Guatemala)
                    if (telefono.length === 8) {
                        telefono = '502' + telefono;
                    }
                    
                    const mensaje = `Hola! 👋

Tus credenciales para acceder a la Banca Virtual son:

🔐 *Usuario:* ${usuario}
📱 *Código Cliente:* ${codcli}

Por favor, al ingresar por primera vez, cambia tu contraseña por seguridad.



¿Necesitas ayuda? Contáctanos.`;
                    
                    const mensajeCodificado = encodeURIComponent(mensaje);
                    const urlWhatsApp = `https://wa.me/${telefono}?text=${mensajeCodificado}`;
                    
                    Swal.fire({
                        title: 'Enviar por WhatsApp',
                        html: `
                            <p>Se abrirá WhatsApp para enviar al:</p>
                            <p class="fw-bold"><i class="fa-brands fa-whatsapp text-success"></i> +${telefono}</p>
                            <p class="small text-muted">Podrás editar el mensaje antes de enviarlo</p>
                        `,
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonColor: '#25D366',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fa-brands fa-whatsapp"></i> Abrir WhatsApp',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open(urlWhatsApp, '_blank');
                        }
                    });
                }

                // Función para enviar credenciales CON contraseña por WhatsApp
                function enviarCredencialesPorWhatsApp(telefono, usuario, password, codcli) {
                    // Limpiar el teléfono
                    telefono = telefono.replace(/\D/g, '');
                    if (telefono.length === 8) {
                        telefono = '502' + telefono;
                    }
                    
                    const mensaje = `Hola! 👋

Tus credenciales para acceder a la Banca Virtual son:

👤 *Usuario:* ${usuario}
🔐 *Contraseña:* ${password}
📱 *Código Cliente:* ${codcli}

⚠️ *IMPORTANTE:*
• Cambia tu contraseña al ingresar por primera vez
• No compartas tus credenciales con nadie
• Si tienes problemas, contáctanos


¿Necesitas ayuda? Estamos para servirte.`;
                    
                    const mensajeCodificado = encodeURIComponent(mensaje);
                    const urlWhatsApp = `https://wa.me/${telefono}?text=${mensajeCodificado}`;
                    
                    window.open(urlWhatsApp, '_blank');
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'WhatsApp abierto',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }


                // Función para editar credencial
                function editarCredencial(id, usuario, codcli) {
                    $('#edit_id').val(id);
                    $('#edit_usuario').val(usuario);
                    $('#edit_codcli').val(codcli);
                    $('#edit_pass').val('');
                    $('#modalEditarCredencial').modal('show');
                }

                // Función para eliminar credencial
                function eliminarCredencial(id, usuario) {
                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: `Se eliminará la credencial del usuario: ${usuario}`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '../../src/cruds/crud_banca.php',
                                method: 'POST',
                                data: {
                                    action: 'eliminar_credencial',
                                    id: id
                                },
                                beforeSend: function() {
                                    loaderefect(1);
                                },
                                success: function(resp) {
                                    let res = {};
                                    try {
                                        res = JSON.parse(resp);
                                    } catch (e) {
                                        res = {
                                            status: 0,
                                            msg: 'Error inesperado'
                                        };
                                    }
                                    if (res.status == 1) {
                                        Swal.fire('Eliminado', res.msg, 'success');
                                        cargarCredenciales();
                                    } else {
                                        Swal.fire('Error', res.msg, 'error');
                                    }
                                },
                                error: function() {
                                    Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                                },
                                complete: function() {
                                    loaderefect(0);
                                }
                            });
                        }
                    });
                }

                $('#formCredencial').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '../../src/cruds/crud_banca.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        success: function(resp) {
                            let res = {};
                            try {
                                res = JSON.parse(resp);
                            } catch (e) {
                                res = {
                                    status: 0,
                                    msg: 'Error inesperado'
                                };
                            }
                            if (res.status == 1) {
                                // Obtener datos del cliente para envío
                                const codcli = res.data.codcli;
                                const usuario = res.data.usuario;
                                const password = res.data.password;
                                const nombre = res.data.nombre;
                                
                                // Buscar email y teléfono desde DataTable
                                let emailCliente = '';
                                let telefonoCliente = '';
                                
                                // Buscar en la tabla actual
                                const table = $('#tb_credenciales').DataTable();
                                const rows = table.rows().data();
                                for (let i = 0; i < rows.length; i++) {
                                    if (rows[i].codcli === codcli) {
                                        emailCliente = rows[i].email_cliente || '';
                                        telefonoCliente = rows[i].telefono_cliente || '';
                                        break;
                                    }
                                }
                                
                                // Mostrar credenciales con opciones de envío
                                Swal.fire({
                                    title: '<i class="fa-solid fa-check-circle text-success"></i> ¡Credenciales Generadas!',
                                    html: `
                                        <div class="text-start">
                                            <div class="alert alert-success">
                                                <strong>Cliente:</strong> ${nombre}<br>
                                                <strong>Código:</strong> ${codcli}
                                            </div>
                                            
                                            <div class="card mb-3">
                                                <div class="card-header bg-primary text-white">
                                                    <i class="fa-solid fa-key"></i> Credenciales de Acceso
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-2">
                                                        <label class="fw-bold">👤 Usuario:</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control" value="${usuario}" readonly>
                                                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('${usuario}'); alert('Usuario copiado!')">
                                                                <i class="fa-solid fa-copy"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="fw-bold">🔐 Contraseña:</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control" value="${password}" readonly>
                                                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('${password}'); alert('Contraseña copiada!')">
                                                                <i class="fa-solid fa-copy"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-warning">
                                                <i class="fa-solid fa-exclamation-triangle"></i> 
                                                <strong>Importante:</strong> Guarda o envía estas credenciales ahora.
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                ${emailCliente ? `
                                                <button type="button" class="btn btn-info" onclick="enviarCredencialesPorCorreo('${emailCliente}', '${usuario}', '${password}', '${codcli}')">
                                                    <i class="fa-solid fa-envelope"></i> Enviar por Correo
                                                </button>` : '<button class="btn btn-secondary" disabled><i class="fa-solid fa-envelope"></i> Sin email</button>'}
                                                
                                                ${telefonoCliente ? `
                                                <button type="button" class="btn btn-success" onclick="enviarCredencialesPorWhatsApp('${telefonoCliente}', '${usuario}', '${password}', '${codcli}')">
                                                    <i class="fa-brands fa-whatsapp"></i> Enviar por WhatsApp
                                                </button>` : '<button class="btn btn-secondary" disabled><i class="fa-brands fa-whatsapp"></i> Sin teléfono</button>'}
                                            </div>
                                        </div>
                                    `,
                                    width: '600px',
                                    showConfirmButton: true,
                                    confirmButtonText: '<i class="fa-solid fa-check"></i> Entendido',
                                    confirmButtonColor: '#198754',
                                    allowOutsideClick: false
                                }).then(() => {
                                    // Limpiar formulario y recargar tabla
                                    $('#formCredencial')[0].reset();
                                    cargarCredenciales();
                                });
                            } else {
                                Swal.fire('Error', res.msg, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                        },
                        complete: function() {
                            loaderefect(0);
                        }
                    });
                });

                // Manejar formulario de edición
                $('#formEditarCredencial').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '../../src/cruds/crud_banca.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        success: function(resp) {
                            let res = {};
                            try {
                                res = JSON.parse(resp);
                            } catch (e) {
                                res = {
                                    status: 0,
                                    msg: 'Error inesperado'
                                };
                            }
                            if (res.status == 1) {
                                Swal.fire('Actualizado', res.msg, 'success');
                                $('#modalEditarCredencial').modal('hide');
                                cargarCredenciales();
                            } else {
                                Swal.fire('Error', res.msg, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                        },
                        complete: function() {
                            loaderefect(0);
                        }
                    });
                });
            </script>
        <?php
        }
        break;
    case 'Conf_mod': {
            $codusuario = $_SESSION['id'];
            $id_usuario = $_POST['xtra'];

            // 1) Abrir conexión a la BD principal
            $database->openConnection(2);

            // 2) Cargar listas para selects
            $modulos = $database->selectEspecial(
                "SELECT id, descripcion
                   FROM tb_modulos
                  WHERE estado = 1
                    AND id IN (2,3,4,18)",
                [],
                2
            );

            $database->closeConnection();

            $database->openConnection();
            $usuarios = $database->selectAll('tb_usuario');
            $agencias = $database->selectAll('tb_agencia');

            // 3) Cargar configuraciones existentes (solo las activas)
            $configs = $database->selectEspecial(
                'SELECT * FROM tb_configuraciones_documentos WHERE deleted_at IS NULL',
                [],
                2
            );
            // Opcional: construir un map id_modulo → descripción
            // Mapear todos los módulos para mostrar descripciones
            // $mapMod = array_column(array_merge($modulos, $modulosOtros), 'descripcion', 'id');
            $mapMod = array_column($modulos, 'descripcion', 'id');

            // 4) Construir maps para mostrar nombres en lugar de IDs
            $mapUsuarios = array_column($usuarios, 'nombre', 'id_usu');
            $mapAgencias = array_column($agencias, 'nom_agencia', 'id_agencia');
            $database->closeConnection();
        ?>
            <div class="container-fluid mt-4">
                <!-- Campos indispensables (ocultos) -->
                <input type="text" id="file" value="superadmin_02" style="display: none;">
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
                                    <input type="hidden" name="xtra" value="<?php echo htmlspecialchars($id_usuario); ?>">
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
                                                    <option value="<?php echo $m['id']; ?>">
                                                        <?php echo htmlspecialchars($m['descripcion']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <!-- <option value="otros">Otros...</option> -->
                                            </select>
                                            <div class="form-text">Selecciona un módulo específico o deja vacío para todos</div>
                                        </div>

                                        <!-- <div class="col-md-6 col-lg-4 d-none" id="cont_modulo_otro">
                                            <label for="modulo_otro" class="form-label">
                                                <i class="fas fa-puzzle-piece text-primary"></i> Otro Módulo
                                            </label>
                                            <select class="form-select" id="modulo_otro">
                                                <option value="">— Seleccione —</option>
                                                <?php foreach ($modulosOtros as $mo): ?>
                                                    <option value="<?= $mo['id'] ?>">
                                                        <?= htmlspecialchars($mo['descripcion']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Módulos adicionales</div>
                                        </div> -->

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
                                                    <option value="<?php echo $u['id_usu']; ?>">
                                                        <?php echo htmlspecialchars($u['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
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
                                                    <option value="<?= $a['id_agencia'] ?>">
                                                        <?= htmlspecialchars($a['nom_agencia'] ?? $a['descripcion'] ?? 'Agencia #' . $a['id_agencia']) ?>
                                                    </option>
                                                <?php endforeach; ?>
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
                                            <!-- <li><strong>Otros módulos:</strong> Clientes, Crédito Individual, Crédito Grupal, Caja, Reportes, Contabilidad, Bancos, Admin, Usuarios</li> -->
                                            <li><strong>Tipos:</strong> Ingreso (depósitos, pagos) / Egreso (retiros, desembolsos)
                                            </li>
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
                                <span class="badge bg-light text-dark"><?php echo count($configs); ?> configuraciones</span>
                            </div>

                            <div class="card-body p-0">
                                <?php if (empty($configs)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No hay configuraciones</h5>
                                        <p class="text-muted">Crea tu primera configuración de correlativo usando el formulario anterior
                                        </p>
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
                                                            <span class="badge bg-primary"><?php echo $c['id']; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($c['id_modulo']): ?>
                                                                <span class="badge bg-info">
                                                                    <?php echo htmlspecialchars($mapMod[$c['id_modulo']] ?? 'Módulo #' . $c['id_modulo']); ?>
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
                                                                        <i class="fas fa-arrow-down"></i>
                                                                        <?php echo htmlspecialchars($c['tipo']); ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning">
                                                                        <i class="fas fa-arrow-up"></i> <?php echo htmlspecialchars($c['tipo']); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-arrows-alt-v"></i> Ambos tipos
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong
                                                                class="text-primary"><?php echo number_format($c['valor_actual']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($c['usuario_id']): ?>
                                                                <span class="badge bg-info">
                                                                    <i class="fas fa-user"></i>
                                                                    <?php echo htmlspecialchars($mapUsuarios[$c['usuario_id']] ?? 'Usuario #' . $c['usuario_id']); ?>
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
                                                                    <?php echo htmlspecialchars($mapAgencias[$c['agencia_id']] ?? 'Agencia #' . $c['agencia_id']); ?>
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
                                                                    data-id="<?php echo $c['id']; ?>"
                                                                    data-modulo="<?php echo $c['id_modulo']; ?>"
                                                                    data-tipo="<?php echo $c['tipo']; ?>"
                                                                    data-valor="<?php echo $c['valor_actual']; ?>"
                                                                    data-usuario="<?php echo $c['usuario_id']; ?>"
                                                                    data-agencia="<?php echo $c['agencia_id']; ?>"
                                                                    title="Editar configuración">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger eliminar"
                                                                    data-id="<?php echo $c['id']; ?>" title="Eliminar configuración">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
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

                    // $('#id_modulo_sel').change(function() {
                    //     if ($(this).val() === 'otros') {
                    //         $('#cont_modulo_otro').removeClass('d-none');
                    //         $('#id_modulo').val($('#modulo_otro').val());
                    //     } else {
                    //         $('#cont_modulo_otro').addClass('d-none');
                    //         $('#modulo_otro').val('');
                    //         $('#id_modulo').val($(this).val());
                    //     }
                    // });

                    // $('#modulo_otro').change(function() {
                    //     if ($('#id_modulo_sel').val() === 'otros') {
                    //         $('#id_modulo').val($(this).val());
                    //     }
                    // });

                    // Limpiar formulario
                    $('#btnLimpiar').click(function() {
                        $('#formConfMod')[0].reset();
                        $('#config_id').val('');
                        $('#btnGuardar').html('<i class="fas fa-save"></i> Guardar Configuración');
                        $('#valor_actual').val('0');
                        // $('#cont_modulo_otro').addClass('d-none');
                        $('#id_modulo_sel').val('');
                        // $('#modulo_otro').val('');
                        $('#id_modulo').val('');
                    });

                    // Editar configuración
                    $('.editar').click(function() {
                        const data = $(this).data();

                        $('#config_id').val(data.id);
                        const mainIds = <?= json_encode(array_column($modulos, 'id')); ?>;
                        if (mainIds.includes(parseInt(data.modulo))) {
                            $('#id_modulo_sel').val(data.modulo);
                            // $('#cont_modulo_otro').addClass('d-none');
                            // $('#modulo_otro').val('');
                            $('#id_modulo').val(data.modulo);
                        } else if (data.modulo) {
                            $('#id_modulo_sel').val('otros');
                            // $('#cont_modulo_otro').removeClass('d-none');
                            // $('#modulo_otro').val(data.modulo);
                            $('#id_modulo').val(data.modulo);
                        } else {
                            $('#id_modulo_sel').val('');
                            // $('#cont_modulo_otro').addClass('d-none');
                            // $('#modulo_otro').val('');
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
                                beforeSend: function() {
                                    loaderefect(1);
                                },
                                success: function(resp) {
                                    let res = {};
                                    try {
                                        res = JSON.parse(resp);
                                    } catch (e) {
                                        res = {
                                            status: 0,
                                            msg: 'Error inesperado'
                                        };
                                    }
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
                                complete: function() {
                                    loaderefect(0);
                                }
                            });
                        }
                    });

                    // Envío del formulario por AJAX
                    $('#formConfMod').submit(function(e) {
                        e.preventDefault();

                        // if ($('#id_modulo_sel').val() === 'otros') {
                        //     $('#id_modulo').val($('#modulo_otro').val());
                        // } else {
                        $('#id_modulo').val($('#id_modulo_sel').val());
                        // }

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
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            success: function(resp) {
                                let res = {};
                                try {
                                    res = JSON.parse(resp);
                                } catch (e) {
                                    res = {
                                        status: 0,
                                        msg: 'Error inesperado'
                                    };
                                }
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
                            complete: function() {
                                loaderefect(0);
                            }
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
    case 'actualizaciones':
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
        $dotenv->load();
        // use CzProject\GitPhp\Git;
        define('PROJECT_ROOT', __DIR__ . '/../../../../');
        $gitBinary = $_ENV['GIT_BINARY'] ?? '/usr/local/cpanel/3rdparty/lib/path-bin/git';

        // Verificar si el binario existe
        if (!file_exists($gitBinary)) {
            throw new Exception('Git binary not found at: ' . $gitBinary);
        }

        // Crear instancia de Git con el binario específico
        $git = new Git(new \CzProject\GitPhp\Runners\CliRunner($gitBinary));
        $repo = $git->open(PROJECT_ROOT);

        // Obtener el último commit desplegado (HEAD)
        $lastCommitHash = $repo->execute('rev-parse', 'HEAD')[0];

        // Obtener todos los commits desde el inicio (o desde un tag específico)
        $commits = $repo->execute('log', '--pretty=format:%s', '--no-merges', '--grep=feat:\|fix:');

        // Filtrar y categorizar commits
        $changes = [
            'features' => [],
            'fixes' => []
        ];

        foreach ($commits as $commit) {
            if (str_starts_with($commit, 'feat:')) {
                $changes['features'][] = substr($commit, 5); // Eliminar "feat:"
            } elseif (str_starts_with($commit, 'fix:')) {
                $changes['fixes'][] = substr($commit, 4); // Eliminar "fix:"
            }
        }
        ?>
        <style>
            .changelog-card {
                border-left: 4px solid #0d6efd;
                margin-bottom: 1rem;
            }

            .feature-badge {
                background-color: #198754;
            }

            .fix-badge {
                background-color: #dc3545;
            }
        </style>
        <div class="container py-4">
            <h1 class="mb-4">📝 Historial de Cambios</h1>

            <!-- Tarjeta de última actualización -->
            <div class="card mb-4">
                <div class="card-header">
                    Última Actualización: <code><?= substr($lastCommitHash, 0, 7) ?></code>
                </div>
                <div class="card-body">
                    <!-- Sección de nuevas funcionalidades -->
                    <?php if (!empty($changes['features'])): ?>
                        <h5 class="mt-3">✨ Nuevas Funcionalidades</h5>
                        <ul class="list-group">
                            <?php foreach ($changes['features'] as $feature): ?>
                                <li class="list-group-item changelog-card">
                                    <span class="badge feature-badge me-2">FEAT</span>
                                    <?= htmlspecialchars($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- Sección de correcciones -->
                    <?php if (!empty($changes['fixes'])): ?>
                        <h5 class="mt-4">🐛 Correcciones</h5>
                        <ul class="list-group">
                            <?php foreach ($changes['fixes'] as $fix): ?>
                                <li class="list-group-item changelog-card">
                                    <span class="badge fix-badge me-2">FIX</span>
                                    <?= htmlspecialchars($fix) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (empty($changes['features']) && empty($changes['fixes'])): ?>
                        <div class="alert alert-info">No hay cambios recientes (feat/fix) en el historial.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php

        break;

    case 'configuraciones_generales':

        $xtra = $_POST['xtra'] ?? '';

        $showmensaje = false;
        try {
            $allConfigs = $appConfigGeneral->getAllConfigurationKeys();

            // Conectar a BD para obtener valores actuales
            $database->openConnection();
            $configuraciones = $database->selectAll('tb_configuraciones');
            $database->closeConnection();

            // Crear array asociativo para fácil acceso
            $configValues = [];
            foreach ($configuraciones as $config) {
                $configValues[$config['id_config']] = $config['valor'];
            }
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $tiposCuentas = array(
            'cr' => 'Corrientes',
            'pf' => 'Plazos fijos',
            'pr' => 'Programados',
            'vi' => 'Vinculados'
        );

    ?>

        <div class="container-fluid mt-4">
            <input type="text" id="file" value="superadmin_02" style="display: none;">
            <input type="text" id="condi" value="configuraciones_generales" style="display: none;">
            <?php if (!$status) { ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>!!</strong> <?= $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-cogs"></i> Configuraciones Generales del Sistema
                                </h4>
                                <small class="text-light">Gestiona las configuraciones globales de la aplicación</small>
                            </div>
                        </div>

                        <div class="card-body">
                            <!-- Navegación por pestañas -->
                            <ul class="nav nav-tabs" id="configTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab"
                                        data-bs-target="#general" type="button" role="tab">
                                        <i class="fas fa-sliders-h"></i> General
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="creditos-tab" data-bs-toggle="tab" data-bs-target="#creditos"
                                        type="button" role="tab">
                                        <i class="fas fa-credit-card"></i> Créditos
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="apariencia-tab" data-bs-toggle="tab"
                                        data-bs-target="#apariencia" type="button" role="tab">
                                        <i class="fas fa-palette"></i> Apariencia
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="garantias-tab" data-bs-toggle="tab" data-bs-target="#garantias"
                                        type="button" role="tab">
                                        <i class="fas fa-shield-alt"></i> Garantías
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content mt-4" id="configTabContent">
                                <!-- Pestaña General -->
                                <div class="tab-pane fade show active" id="general" role="tabpanel">
                                    <form id="formConfigGeneral">
                                        <div class="row g-4">
                                            <!-- Campo Fecha Caja -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-calendar-alt text-primary"></i> Campo de Fecha para
                                                            Caja
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <select class="form-select" name="config[1]" id="campo_fecha_caja">
                                                            <option value="1" <?= ($configValues[1] ?? '2') == '1' ? 'selected' : '' ?>>
                                                                Fecha del Sistema
                                                            </option>
                                                            <option value="2" <?= ($configValues[1] ?? '2') == '2' ? 'selected' : '' ?>>
                                                                Fecha del Documento
                                                            </option>
                                                        </select>
                                                        <div class="form-text">
                                                            Selecciona qué fecha usar para el cuadre de caja
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Permitir Repetir Boletas por Bancos -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-university text-primary"></i> Boletas por Banco
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="config[8]"
                                                                id="permitir_boletas" value="1" <?= ($configValues[8] ?? '1') == '1' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="permitir_boletas">
                                                                Permitir números de boleta repetidos por banco
                                                            </label>
                                                        </div>
                                                        <div class="form-text">
                                                            Permite usar el mismo número de boleta en diferentes bancos
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-calculator text-success"></i> Cálculo de Intereses
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="config[13]"
                                                                id="calculo_intereses_dia" value="1" <?= ($configValues[13] ?? '0') == '1' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="calculo_intereses_dia">
                                                                Mostrar cálculo de intereses al día en caja
                                                            </label>
                                                        </div>
                                                        <div class="form-text">
                                                            Muestra el cálculo de intereses diarios en la caja de créditos
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-4">
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-end gap-2">
                                                        <button type="button" class="btn btn-outline-secondary" id="btnRestaurar" onclick="obtiene([],[],[],'reset_configurations','0',[[1,8,13]],'NULL','¿Estás seguro de que deseas restaurar los valores?')">
                                                            <i class="fas fa-undo"></i> Restaurar Valores
                                                        </button>
                                                        <button type="button" class="btn btn-success" id="btnGuardarCambios"
                                                            onclick="obtiene([],['campo_fecha_caja'],[],'change_section_general','0',[$('#permitir_boletas').is(':checked')?1:0,$('#calculo_intereses_dia').is(':checked')?1:0],'NULL','¿Estás seguro de que deseas guardar los cambios?')">
                                                            <i class="fas fa-save"></i> Guardar Cambios
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </form>
                                </div>

                                <!-- Pestaña Créditos -->
                                <div class="tab-pane fade" id="creditos" role="tabpanel">
                                    <form id="formConfigCreditos">
                                        <div class="row g-4">
                                            <!-- Desglosar IVA -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-percent text-success"></i> Desglose de IVA
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="config[2]"
                                                                id="desglosar_iva" value="1" <?= ($configValues[2] ?? '0') == '1' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="desglosar_iva">
                                                                Desglosar IVA en pagos de créditos
                                                            </label>
                                                        </div>
                                                        <div class="form-text">
                                                            Muestra el IVA por separado en las partidas de pagos
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Permitir Pagos KP -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-coins text-warning"></i> Pagos de Capital
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="config[3]"
                                                                id="permitir_pagos_kp" value="1" <?= ($configValues[3] ?? '0') == '1' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="permitir_pagos_kp">
                                                                Permitir pagos mayores al saldo de capital
                                                            </label>
                                                        </div>
                                                        <div class="form-text">
                                                            Permite pagar más capital del que está pendiente
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Permitir Pagos Intereses -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-percentage text-info"></i> Pagos de Intereses
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="config[4]"
                                                                id="permitir_pagos_int" value="1" <?= ($configValues[4] ?? '0') == '1' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="permitir_pagos_int">
                                                                Permitir pagos mayores al saldo de intereses
                                                            </label>
                                                        </div>
                                                        <div class="form-text">
                                                            Permite pagar más intereses de los que están pendientes
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Precisión de Créditos -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-decimal text-primary"></i> Precisión de Cálculos
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <label class="form-label">Decimales a mostrar:</label>
                                                        <select class="form-select mb-3" name="config[11]"
                                                            id="precision_creditos">
                                                            <option value="0" <?= ($configValues[11] ?? '2') == '0' ? 'selected' : '' ?>>0 decimales</option>
                                                            <option value="1" <?= ($configValues[11] ?? '2') == '1' ? 'selected' : '' ?>>1 decimal</option>
                                                            <option value="2" <?= ($configValues[11] ?? '2') == '2' ? 'selected' : '' ?>>2 decimales</option>
                                                        </select>

                                                        <label class="form-label">Modo de redondeo:</label>
                                                        <select class="form-select" name="config[12]"
                                                            id="mode_precision_creditos">
                                                            <option value="PHP_ROUND_HALF_EVEN" <?= ($configValues[12] ?? 'PHP_ROUND_HALF_EVEN') == 'PHP_ROUND_HALF_EVEN' ? 'selected' : '' ?>>
                                                                Al par más cercano
                                                            </option>
                                                            <option value="PHP_ROUND_HALF_UP" <?= ($configValues[12] ?? 'PHP_ROUND_HALF_EVEN') == 'PHP_ROUND_HALF_UP' ? 'selected' : '' ?>>
                                                                Hacia arriba
                                                            </option>
                                                            <option value="PHP_ROUND_HALF_DOWN" <?= ($configValues[12] ?? 'PHP_ROUND_HALF_EVEN') == 'PHP_ROUND_HALF_DOWN' ? 'selected' : '' ?>>
                                                                Hacia abajo
                                                            </option>
                                                            <option value="PHP_ROUND_HALF_ODD" <?= ($configValues[12] ?? 'PHP_ROUND_HALF_EVEN') == 'PHP_ROUND_HALF_ODD' ? 'selected' : '' ?>>
                                                                Al impar más cercano
                                                            </option>
                                                        </select>
                                                        <div class="form-text">
                                                            Configura cómo se muestran y redondean los valores en créditos
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Campos de Pagos de Créditos -->
                                            <div class="col-12">
                                                <div class="card border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-table text-primary"></i> Campos de Pagos de Créditos
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <label class="form-label">Selecciona los campos a mostrar en pagos de
                                                            créditos:</label>
                                                        <div class="row">
                                                            <?php
                                                            $camposDisponibles = [
                                                                'ccodcta' => 'Código de Cuenta',
                                                                'codcli' => 'Código de Cliente',
                                                                'dpi' => 'DPI',
                                                                'nombre' => 'Nombre',
                                                                'ciclo' => 'Ciclo',
                                                                'monto' => 'Monto',
                                                                'saldo' => 'Saldo',
                                                                'diapago' => 'Día de Pago',
                                                                'analista' => 'Analista',
                                                                'agencia' => 'Agencia',
                                                                'dfecdsbls' => 'Fecha de Desembolso'
                                                            ];

                                                            $camposSeleccionados = array_map('trim', explode(',', $configValues[14] ?? 'ccodcta,codcli,dpi,nombre,ciclo,diapago,monto,saldo'));

                                                            foreach ($camposDisponibles as $campo => $descripcion):
                                                            ?>
                                                                <div class="col-md-4 col-lg-3">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            name="campos_creditos[]" id="campo_<?= $campo ?>"
                                                                            value="<?= $campo ?>" <?= in_array($campo, $camposSeleccionados) ? 'checked' : '' ?>>
                                                                        <label class="form-check-label" for="campo_<?= $campo ?>">
                                                                            <?= $descripcion ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <div class="form-text">
                                                            Los campos seleccionados aparecerán en las pantallas de pagos de
                                                            créditos
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-outline-secondary" id="btnRestaurar" onclick="obtiene([],[],[],'reset_configurations','0',[[2,3,4,11,12,14]],'NULL','¿Estás seguro de que deseas restaurar los valores?')">
                                                        <i class="fas fa-undo"></i> Restaurar Valores
                                                    </button>
                                                    <button type="button" class="btn btn-success" id="btnGuardarCambios"
                                                        onclick="obtiene([],['precision_creditos','mode_precision_creditos'],[],'change_section_creditos','0',
                                                        [$('#desglosar_iva').is(':checked')?1:0,$('#permitir_pagos_kp').is(':checked')?1:0,$('#permitir_pagos_int').is(':checked')?1:0,recolectaChecks('campos_creditos[]')],
                                                        'NULL','¿Estás seguro de que deseas guardar los cambios?')">
                                                        <i class="fas fa-save"></i> Guardar Cambios
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Pestaña Caja -->
                                <div class="tab-pane fade" id="caja" role="tabpanel">
                                    <form id="formConfigCaja">
                                        <div class="row g-4">
                                            <!-- Cálculo de Intereses al Día -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-calculator text-success"></i> Cálculo de Intereses
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="config[13]"
                                                                id="calculo_intereses_dia" value="1" <?= ($configValues[13] ?? '0') == '1' ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="calculo_intereses_dia">
                                                                Mostrar cálculo de intereses al día en caja
                                                            </label>
                                                        </div>
                                                        <div class="form-text">
                                                            Muestra el cálculo de intereses diarios en la caja de créditos
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Pestaña Apariencia -->
                                <div class="tab-pane fade" id="apariencia" role="tabpanel">
                                    <form id="formConfigApariencia">
                                        <div class="row g-4">
                                            <!-- Logo del Sistema -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-image text-primary"></i> Logo del Sistema
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <input type="text" class="form-control" name="config[5]"
                                                            id="logo_sistema" placeholder="logomicro.png"
                                                            value="<?= htmlspecialchars($configValues[5] ?? 'logomicro.png') ?>">
                                                        <div class="form-text">
                                                            Nombre del archivo del logo del sistema (debe estar en includes/img/)
                                                        </div>
                                                        <?php if (!empty($configValues[5])): ?>
                                                            <div class="mt-2">
                                                                <small class="text-muted">Vista previa:</small><br>
                                                                <img src="../../../../includes/img/<?= htmlspecialchars($configValues[5]) ?>"
                                                                    alt="Logo Sistema" class="img-thumbnail"
                                                                    style="max-height: 60px;">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Nombre del Sistema -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-tag text-info"></i> Nombre del Sistema
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <input type="text" class="form-control" name="config[6]"
                                                            id="nombre_sistema" placeholder="MicroSystem Plus"
                                                            value="<?= htmlspecialchars($configValues[6] ?? '') ?>">
                                                        <div class="form-text">
                                                            Nombre que aparecerá en el sistema
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Logo de Login -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-sign-in-alt text-warning"></i> Logo de Login
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <input type="text" class="form-control" name="config[7]" id="logo_login"
                                                            placeholder="logomicro.png"
                                                            value="<?= htmlspecialchars($configValues[7] ?? 'logomicro.png') ?>">
                                                        <div class="form-text">
                                                            Nombre del archivo del logo para la pantalla de login
                                                        </div>
                                                        <?php if (!empty($configValues[7])): ?>
                                                            <div class="mt-2">
                                                                <small class="text-muted">Vista previa:</small><br>
                                                                <img src="<?= htmlspecialchars($configValues[7]) ?>"
                                                                    alt="Logo Login" class="img-thumbnail"
                                                                    style="max-height: 60px;">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Pestaña Garantías -->
                                <div class="tab-pane fade" id="garantias" role="tabpanel">
                                    <form id="formConfigGarantias">
                                        <div class="row g-4">
                                            <!-- Ahorros como Garantía -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-piggy-bank text-success"></i> Ahorros como Garantía
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <label for="ahorros_garantia" class="form-label">Tipos de ahorro permitidos</label>
                                                        <select class="form-select" name="config[9][]" id="ahorros_garantia" multiple>
                                                            <?php
                                                            $selectedAhorros = array_map('trim', explode(',', str_replace(["'", '"'], '', $configValues[9] ?? '')));
                                                            foreach ($tiposCuentas as $key => $label):
                                                            ?>
                                                                <option value="<?= $key ?>" <?= in_array($key, $selectedAhorros) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($label) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <div class="form-text">
                                                            Selecciona los tipos de ahorro que pueden usarse como garantía.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Aportaciones como Garantía -->
                                            <div class="col-md-6">
                                                <div class="card h-100 border-0 shadow-sm">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-handshake text-primary"></i> Aportaciones como Garantía
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <label for="aportaciones_garantia" class="form-label">Tipos de aportación permitidos</label>
                                                        <select class="form-select" name="config[10][]" id="aportaciones_garantia" multiple>
                                                            <?php
                                                            $catalogoApr = array(
                                                                'cr' => 'Aportacion Cuenta corriente',
                                                            );
                                                            $selectedAportaciones = array_map('trim', explode(',', str_replace(["'", '"'], '', $configValues[10] ?? '')));
                                                            foreach ($catalogoApr as $key => $label):
                                                            ?>
                                                                <option value="<?= $key ?>" <?= in_array($key, $selectedAportaciones) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($label) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <div class="form-text">
                                                            Selecciona los tipos de aportación que pueden usarse como garantía.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-outline-secondary" id="btnRestaurar" onclick="obtiene([],[],[],'reset_configurations','0',[[9,10]],'NULL','¿Estás seguro de que deseas restaurar los valores?')">
                                                        <i class="fas fa-undo"></i> Restaurar Valores
                                                    </button>
                                                    <button type="button" class="btn btn-success" id="btnGuardarCambios"
                                                        onclick="obtiene([],[],[],'change_section_garantias','0',[$('#ahorros_garantia').val(),$('#aportaciones_garantia').val()],
                                                        'NULL','¿Estás seguro de que deseas guardar los cambios?')">
                                                        <i class="fas fa-save"></i> Guardar Cambios
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function recolectaChecks(name) {
                        let seleccionados = [];

                        $("input[name='" + name + "']:checked").each(function() {
                            seleccionados.push($(this).val());
                        });
                        // console.log("Checkboxes seleccionados:", seleccionados);
                        return seleccionados;
                    }
                    $(document).ready(function() {
                        $('#ahorros_garantia').select2({
                            theme: 'bootstrap-5',
                            language: 'es',
                            closeOnSelect: false
                        });
                        $('#aportaciones_garantia').select2({
                            theme: 'bootstrap-5',
                            language: 'es',
                            closeOnSelect: false
                        });
                    });
                </script>

            <?php

            break;

        case "log":
            $showmensaje = true;
            try {
                $database->openConnection();
                $registro = $database->getAllResults("SELECT usu.nombre, usu.apellido, usu.puesto,reg.id_tb_usuario, reg.fecha_inicio,reg.fecha_fin  
            FROM tb_registro_login reg 
            JOIN tb_usuario usu ON usu.id_usu = reg.id_tb_usuario 
            ORDER BY fecha_inicio DESC LIMIT 30;", []);
                $registroEventos = $database->getAllResults("SELECT ctb.glosa, ctb.cod_aux, ctb.fecdoc,ctb.fecmod, ctb.id_tb_usu, ctb.id_agencia, ctb.estado, usu.nombre, usu.apellido,ag.nom_agencia
                FROM ctb_diario ctb
                JOIN tb_usuario usu ON ctb.id_tb_usu = usu.id_usu
                JOIN tb_agencia ag ON ctb.id_agencia = ag.id_agencia
                ORDER BY ctb.fecmod DESC LIMIT 50;", []);

                $status = 1;
            } catch (Exception $e) {
                if (!$showmensaje) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                }
                $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
                $status = 0;
            } finally {
                $database->closeConnection();
            }


            ?>
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="card border-0 shadow-lg">
                        <div class="card-header bg-primary text-white py-3">
                            <div>
                                <h4 class="mb-1"><i class="fas fa-history me-2"></i>Registros de Accesos y Eventos</h4>
                                <small class="text-light">Últimos 30 inicios de sesión y últimos 50 registros del sistema</small>
                            </div>
                    </div>
                    <!-- Tabs Navigation -->
                    <div class="card-header bg-light border-bottom-0">
                        <ul class="nav nav-tabs card-header-tabs" id="logTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="accesos-tab" data-bs-toggle="tab" data-bs-target="#accesos" 
                                    type="button" role="tab" aria-controls="accesos" aria-selected="true">
                                    <i class="fas fa-sign-in-alt me-2"></i>Accesos de Usuarios
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="registros-tab" data-bs-toggle="tab" data-bs-target="#registros" 
                                    type="button" role="tab" aria-controls="registros" aria-selected="false">
                                    <i class="fas fa-clipboard-list me-2"></i>Registros del Sistema
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content" id="logTabContent">
                        <!-- Accesos Tab -->
                        <div class="tab-pane fade show active" id="accesos" role="tabpanel" aria-labelledby="accesos-tab">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th scope="col" style="width: 50px;">#</th>
                                            <th scope="col"><i class="fas fa-user me-2"></i>Usuario</th>
                                            <th scope="col"><i class="fas fa-sign-in-alt me-2"></i>Inicio de Sesión</th>
                                            <th scope="col"><i class="fas fa-briefcase me-2"></i>Puesto</th>
                                            <th scope="col"><i class="fas fa-sign-out-alt me-2"></i>Cierre de Sesión</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($registro)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                    No hay registros de acceso
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $i = 1;
                                            foreach ($registro as $fila): ?>
                                                <tr class="align-middle">
                                                    <td class="fw-bold text-primary"><?= str_pad($i++, 2, '0', STR_PAD_LEFT); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar me-2" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold;">
                                                                <?= strtoupper(substr($fila['nombre'], 0, 1) . substr($fila['apellido'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-semibold"><?= htmlspecialchars($fila['nombre'] . ' ' . $fila['apellido']) ?></div>
                                                                <small class="text-muted">ID: <?= htmlspecialchars($fila['id_tb_usuario']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success-subtle text-success">
                                                            <i class="fas fa-calendar-alt me-1"></i>
                                                            <?= date('d/m/Y H:i', strtotime($fila['fecha_inicio'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?= htmlspecialchars($fila['puesto']) ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($fila['fecha_fin']): ?>
                                                            <span class="badge bg-danger-subtle text-danger">
                                                                <i class="fas fa-calendar-alt me-1"></i>
                                                                <?= date('d/m/Y H:i', strtotime($fila['fecha_fin'])) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">
                                                                <i class="fas fa-circle-notch fa-spin me-1"></i>Activo
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Registros Tab -->
                        <div class="tab-pane fade" id="registros" role="tabpanel" aria-labelledby="registros-tab">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th scope="col" style="width: 50px;">#</th>
                                            <th scope="col"><i class="fas fa-align-left me-2"></i>Descripción</th>
                                            <th scope="col"><i class="fas fa-file-alt me-2"></i>Fecha Documento</th>
                                            <th scope="col"><i class="fas fa-clock me-2"></i>Fecha Registro</th>
                                            <th scope="col"><i class="fas fa-user-circle me-2"></i>Usuario</th>
                                            <th scope="col"><i class="fas fa-building me-2"></i>Agencia</th>
                                            <th scope="col"><i class="fas fa-check-circle me-2"></i>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($registroEventos)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                    No hay registros de eventos
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $j = 1;
                                            foreach ($registroEventos as $fila): ?>
                                                <tr class="align-middle">
                                                    <td class="fw-bold text-primary"><?= str_pad($j++, 2, '0', STR_PAD_LEFT); ?></td>
                                                    <td>
                                                        <div class="text-truncate" title="<?= htmlspecialchars($fila['glosa']) ?>" style="max-width: 250px;">
                                                            <small><?= htmlspecialchars($fila['glosa']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?= date('d/m/Y', strtotime($fila['fecdoc'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <i class="fas fa-hourglass-end me-1"></i>
                                                            <?= date('d/m/Y H:i', strtotime($fila['fecmod'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar me-2" style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold;">
                                                                <?= strtoupper(substr($fila['nombre'], 0, 1) . substr($fila['apellido'], 0, 1)); ?>
                                                            </div>
                                                            <small><?= htmlspecialchars($fila['nombre'] . ' ' . $fila['apellido']) ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-sitemap me-1"></i>
                                                            <?= htmlspecialchars($fila['nom_agencia']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $estadoBadge = [
                                                            1 => ['class' => 'bg-success', 'icon' => 'fa-check-circle', 'text' => 'Activo'],
                                                            0 => ['class' => 'bg-danger', 'icon' => 'fa-trash-alt', 'text' => 'Eliminado']
                                                        ];
                                                        $estado = $estadoBadge[$fila['estado']] ?? ['class' => 'bg-secondary', 'icon' => 'fa-question-circle', 'text' => 'Desconocido'];
                                                        ?>
                                                        <span class="badge <?= $estado['class'] ?>">
                                                            <i class="fas <?= $estado['icon'] ?> me-1"></i><?= $estado['text'] ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light text-muted text-center py-3">
                        <small><i class="fas fa-sync-alt me-1"></i>Datos actualizados automáticamente</small>
                    </div>
                </div>

                <style>
                    .bg-gradient {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    }

                    .table-hover tbody tr:hover {
                        background-color: rgba(102, 126, 234, 0.05);
                    }

                    .nav-tabs .nav-link {
                        color: #666;
                        border: none;
                        border-bottom: 3px solid transparent;
                        transition: all 0.3s ease;
                    }

                    .nav-tabs .nav-link:hover {
                        color: #667eea;
                        border-bottom-color: #667eea;
                    }

                    .nav-tabs .nav-link.active {
                        color: #667eea;
                        border-bottom-color: #667eea;
                        background: transparent;
                    }

                    .badge {
                        font-weight: 500;
                    }

                    .bg-success-subtle {
                        background-color: rgba(25, 135, 84, 0.15) !important;
                    }

                    .bg-danger-subtle {
                        background-color: rgba(220, 53, 69, 0.15) !important;
                    }

                    .bg-primary-subtle {
                        background-color: rgba(13, 110, 253, 0.15) !important;
                    }
                </style>
                    <!-- </tbody>
            </table> -->
                </div>
            <?php
            break;

        case 'cache_manager':
            // Obtener información general del cache
            $showMessage = false;
            $mensaje = '';
            $status = 1;

            // Procesar acciones si se envían
            if (is_array($_POST['xtra'])) {
                list($action) = $_POST['xtra'];
            } else {
                $action = '';
            }

            try {
                switch ($action) {
                    case 'clear_all':
                        $cleared = 0;
                        $prefixes = ['pais_', 'departamento_', 'municipio_', 'config_', 'permisos_', 'dashboard_creditos_', 'identificacion_'];
                        foreach ($prefixes as $prefix) {
                            $cache = new \App\Generic\CacheManager($prefix);
                            if ($cache->clear()) {
                                $cleared++;
                            }
                        }
                        $mensaje = "Cache limpiado exitosamente. $cleared categorías afectadas.";
                        $showMessage = true;
                        break;

                    case 'clear_prefix':
                        $prefix = $_POST['xtra'][1] ?? '';
                        if ($prefix) {
                            $cache = new \App\Generic\CacheManager($prefix);
                            if ($cache->clear()) {
                                $mensaje = "Cache de categoría '$prefix' limpiado exitosamente.";
                            } else {
                                $mensaje = "Error al limpiar cache de categoría '$prefix'.";
                                $status = 0;
                            }
                        } else {
                            $mensaje = "Prefijo no especificado.";
                            $status = 0;
                        }
                        $showMessage = true;
                        break;

                    case 'delete_key':
                        $fullKey = $_POST['xtra'][3] ?? '';
                        $prefix = $_POST['xtra'][1] ?? '';
                        $key = $_POST['xtra'][2] ?? '';

                        if ($fullKey && $prefix && $key) {
                            $cache = new \App\Generic\CacheManager($prefix);
                            if ($cache->delete($key)) {
                                $mensaje = "Clave '$fullKey' eliminada exitosamente.";
                            } else {
                                $mensaje = "Error al eliminar clave '$fullKey'.";
                                $status = 0;
                            }
                        } else {
                            $mensaje = "Datos incompletos para eliminar clave.";
                            $status = 0;
                        }
                        $showMessage = true;
                        break;
                }
            } catch (Exception $e) {
                $mensaje = "Error: " . $e->getMessage();
                $status = 0;
                $showMessage = true;
            }

            // Obtener estadísticas de diferentes tipos de cache
            $cacheTypes = [
                'pais_' => ['name' => 'Países', 'icon' => 'fas fa-globe-americas', 'color' => 'primary'],
                'departamento_' => ['name' => 'Departamentos', 'icon' => 'fas fa-map-marked-alt', 'color' => 'success'],
                'municipio_' => ['name' => 'Municipios', 'icon' => 'fas fa-city', 'color' => 'info'],
                'config_config_' => ['name' => 'Configuraciones', 'icon' => 'fas fa-cogs', 'color' => 'warning'],
                'permisos_' => ['name' => 'Permisos de Usuario', 'icon' => 'fas fa-shield-alt', 'color' => 'danger'],
                'dashboard_creditos_' => ['name' => 'Dashboard Créditos', 'icon' => 'fas fa-chart-line', 'color' => 'dark'],
                'identificacion_' => ['name' => 'Tipos de Identificación', 'icon' => 'fas fa-id-card', 'color' => 'secondary'],
            ];

            $cacheData = [];
            $totalKeys = 0;
            $totalMemory = 0;
            $healthyCategories = 0;

            foreach ($cacheTypes as $prefix => $config) {
                try {
                    $cache = new \App\Generic\CacheManager($prefix);
                    $stats = $cache->getStats();
                    $keys = $cache->listKeys();

                    // Obtener valores para cada clave
                    $keysWithValues = [];
                    foreach ($keys as $keyInfo) {
                        $cleanKey = str_replace($prefix, '', $keyInfo['key']);
                        $value = $cache->get($cleanKey);

                        // Preparar preview del valor
                        $valuePreview = '';
                        $valueType = 'unknown';

                        if ($value !== null) {
                            if (is_array($value) || is_object($value)) {
                                $valueType = is_array($value) ? 'array' : 'object';
                                $valuePreview = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                            } elseif (is_bool($value)) {
                                $valueType = 'boolean';
                                $valuePreview = $value ? 'true' : 'false';
                            } elseif (is_numeric($value)) {
                                $valueType = 'number';
                                $valuePreview = (string)$value;
                            } else {
                                $valueType = 'string';
                                $valuePreview = (string)$value;
                            }

                            // Truncar si es muy largo
                            if (strlen($valuePreview) > 200) {
                                $valuePreview = substr($valuePreview, 0, 200) . '...';
                            }
                        } else {
                            $valueType = 'null';
                            $valuePreview = 'null';
                        }

                        $keyInfo['value_preview'] = $valuePreview;
                        $keyInfo['value_type'] = $valueType;
                        $keyInfo['clean_key'] = $cleanKey;
                        $keysWithValues[] = $keyInfo;
                    }

                    $cacheData[$prefix] = [
                        'description' => $config['name'],
                        'icon' => $config['icon'],
                        'color' => $config['color'],
                        'enabled' => $stats['enabled'] ?? false,
                        'keys' => $keysWithValues,
                        'count' => count($keysWithValues),
                        'stats' => $stats
                    ];

                    $totalKeys += count($keysWithValues);
                    if (isset($stats['memory_used'])) {
                        $totalMemory += $stats['memory_used'];
                    }
                    if ($stats['enabled'] ?? false) {
                        $healthyCategories++;
                    }
                } catch (Exception $e) {
                    $cacheData[$prefix] = [
                        'description' => $config['name'],
                        'icon' => $config['icon'],
                        'color' => 'secondary',
                        'enabled' => false,
                        'keys' => [],
                        'count' => 0,
                        'error' => $e->getMessage()
                    ];
                }
            }

            ?>

                <!-- Estilos CSS Mínimos Personalizados -->
                <style>
                    .cache-manager-wrapper {
                        background: linear-gradient(135deg, #667eea 0%, #4b8ea2ff 100%);
                        min-height: 100vh;
                    }

                    .glassmorphism {
                        background: rgba(255, 255, 255, 0.95);
                        backdrop-filter: blur(10px);
                    }

                    .hover-lift:hover {
                        transform: translateY(-3px);
                        transition: all 0.3s ease;
                    }

                    .value-type-badge {
                        padding: 4px 8px;
                        border-radius: 15px;
                        font-size: 0.7rem;
                        font-weight: 500;
                        text-transform: uppercase;
                    }
                </style>

                <!-- Cache Manager Container -->
                <div class="cache-manager-wrapper py-4">
                    <div class="container-fluid px-4">
                        <!-- Breadcrumb Navigation -->
                        <nav aria-label="breadcrumb" class="mb-3">
                            <ol class="breadcrumb bg-white bg-opacity-25 rounded-pill px-4 py-2">
                                <li class="breadcrumb-item text-white-50">
                                    <i class="fas fa-home me-1"></i> Administración
                                </li>
                                <li class="breadcrumb-item text-white-50">
                                    <i class="fas fa-cogs me-1"></i> Sistema
                                </li>
                                <li class="breadcrumb-item active text-white fw-bold" aria-current="page">
                                    <i class="fas fa-database me-1"></i> Administrador de Cache
                                </li>
                            </ol>
                        </nav>

                        <!-- Header Principal -->
                        <div class="card glassmorphism border-0 rounded-4 shadow-lg mb-4">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <h1 class="display-6 text-primary mb-2">
                                            <i class="fas fa-database me-2"></i>
                                            Administrador de Cache del Sistema
                                        </h1>
                                        <p class="text-muted mb-0 lead">
                                            Gestiona y monitorea el cache de la aplicación para optimizar el rendimiento del sistema
                                        </p>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="d-flex gap-2 justify-content-lg-end flex-wrap">
                                            <button class="btn btn-warning rounded-pill px-4" onclick="confirmClearAll()">
                                                <i class="fas fa-trash-alt me-2"></i> Limpiar Todo
                                            </button>
                                            <button class="btn btn-primary rounded-pill px-4" onclick="printdiv('cache_manager', '#cuadro', 'superadmin_02', '0')">
                                                <i class="fas fa-sync-alt me-2"></i> Actualizar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alertas -->
                        <?php if ($showMessage): ?>
                            <div class="alert alert-<?= $status ? 'success' : 'danger' ?> alert-dismissible fade show border-0 rounded-4 shadow-sm">
                                <i class="fas fa-<?= $status ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                                <?= htmlspecialchars($mensaje) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Estadísticas Generales -->
                        <div class="row g-4 mb-5">
                            <div class="col-lg-3 col-md-6">
                                <div class="card glassmorphism border-0 rounded-4 shadow hover-lift h-100">
                                    <div class="card-body text-center p-4">
                                        <div class="text-primary mb-3">
                                            <i class="fas fa-key display-4"></i>
                                        </div>
                                        <h2 class="fw-bold text-dark"><?= number_format($totalKeys) ?></h2>
                                        <p class="text-muted text-uppercase small fw-bold mb-3">Total de Claves</p>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-primary" style="width: <?= min(100, ($totalKeys / 1000) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="card glassmorphism border-0 rounded-4 shadow hover-lift h-100">
                                    <div class="card-body text-center p-4">
                                        <div class="text-success mb-3">
                                            <i class="fas fa-layer-group display-4"></i>
                                        </div>
                                        <h2 class="fw-bold text-dark"><?= count($cacheTypes) ?></h2>
                                        <p class="text-muted text-uppercase small fw-bold mb-3">Categorías Totales</p>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-success" style="width: 100%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="card glassmorphism border-0 rounded-4 shadow hover-lift h-100">
                                    <div class="card-body text-center p-4">
                                        <div class="text-info mb-3">
                                            <i class="fas fa-memory display-4"></i>
                                        </div>
                                        <h2 class="fw-bold text-dark"><?= number_format($totalMemory / 1024, 1) ?> KB</h2>
                                        <p class="text-muted text-uppercase small fw-bold mb-3">Memoria Utilizada</p>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-info" style="width: <?= min(100, ($totalMemory / 10240) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="card glassmorphism border-0 rounded-4 shadow hover-lift h-100">
                                    <div class="card-body text-center p-4">
                                        <div class="text-<?= extension_loaded('apcu') ? 'success' : 'danger' ?> mb-3">
                                            <i class="fas fa-<?= extension_loaded('apcu') ? 'check-circle' : 'times-circle' ?> display-4"></i>
                                        </div>
                                        <h2 class="fw-bold text-dark"><?= $healthyCategories ?>/<?= count($cacheTypes) ?></h2>
                                        <p class="text-muted text-uppercase small fw-bold mb-3">APCu <?= extension_loaded('apcu') ? 'Activo' : 'Inactivo' ?></p>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-<?= extension_loaded('apcu') ? 'success' : 'danger' ?>"
                                                style="width: <?= ($healthyCategories / count($cacheTypes)) * 100 ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categorías de Cache -->
                        <div class="row">
                            <div class="col-12">
                                <h3 class="text-white mb-4 fw-bold">
                                    <i class="fas fa-folder-open me-2"></i> Categorías de Cache
                                </h3>

                                <?php foreach ($cacheData as $prefix => $data): ?>
                                    <div class="card glassmorphism border-0 rounded-4 shadow hover-lift mb-4">
                                        <div class="card-header bg-light bg-opacity-75 border-0 rounded-top-4 p-4">
                                            <div class="row align-items-center">
                                                <div class="col-lg-8">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <div class="bg-<?= $data['color'] ?> text-white rounded-3 p-3 fs-4">
                                                                <i class="<?= $data['icon'] ?>"></i>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-1 fw-bold text-dark"><?= $data['description'] ?></h5>
                                                            <div class="d-flex gap-3 flex-wrap">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-key me-1"></i>
                                                                    <span class="fw-bold"><?= $data['count'] ?></span> claves
                                                                </small>
                                                                <small class="text-<?= $data['enabled'] ? 'success' : 'danger' ?>">
                                                                    <i class="fas fa-circle me-1"></i>
                                                                    <?= $data['enabled'] ? 'Activo' : 'Inactivo' ?>
                                                                </small>
                                                                <?php if (isset($data['stats']['memory_used'])): ?>
                                                                    <small class="text-muted">
                                                                        <i class="fas fa-memory me-1"></i>
                                                                        <?= number_format($data['stats']['memory_used'] / 1024, 1) ?> KB
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="d-flex gap-2 justify-content-lg-end flex-wrap">
                                                        <?php if ($data['count'] > 0): ?>
                                                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3"
                                                                data-bs-toggle="collapse"
                                                                data-bs-target="#content-<?= $prefix ?>"
                                                                aria-expanded="false">
                                                                <i class="fas fa-eye me-1"></i> Ver Claves
                                                            </button>
                                                            <button class="btn btn-outline-warning btn-sm rounded-pill px-3"
                                                                onclick="confirmClearPrefix('<?= $prefix ?>', '<?= $data['description'] ?>')">
                                                                <i class="fas fa-broom me-1"></i> Limpiar
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary rounded-pill px-3 py-2">Sin datos</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($data['count'] > 0): ?>
                                            <div class="collapse" id="content-<?= $prefix ?>">
                                                <div class="card-body p-4">
                                                    <div class="table-responsive">
                                                        <table class="table table-hover bg-white rounded-3 overflow-hidden shadow-sm">
                                                            <thead class="table-dark">
                                                                <tr>
                                                                    <th class="border-0 text-uppercase fw-bold small">Clave</th>
                                                                    <th class="border-0 text-uppercase fw-bold small">Tipo</th>
                                                                    <th class="border-0 text-uppercase fw-bold small">Vista Previa</th>
                                                                    <th class="border-0 text-uppercase fw-bold small">Tamaño</th>
                                                                    <th class="border-0 text-uppercase fw-bold small">Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($data['keys'] as $keyInfo): ?>
                                                                    <tr>
                                                                        <td class="align-middle">
                                                                            <code class="text-primary bg-light px-2 py-1 rounded"><?= htmlspecialchars($keyInfo['clean_key']) ?></code>
                                                                        </td>
                                                                        <td class="align-middle">
                                                                            <?php
                                                                            $typeColors = [
                                                                                'array' => 'primary',
                                                                                'object' => 'info',
                                                                                'string' => 'success',
                                                                                'number' => 'warning',
                                                                                'boolean' => 'danger',
                                                                                'null' => 'secondary'
                                                                            ];
                                                                            $color = $typeColors[$keyInfo['value_type']] ?? 'secondary';
                                                                            ?>
                                                                            <span class="badge bg-<?= $color ?> rounded-pill text-uppercase small">
                                                                                <?= $keyInfo['value_type'] ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="align-middle">
                                                                            <div class="text-truncate" style="max-width: 300px;">
                                                                                <small class="text-muted font-monospace"><?= htmlspecialchars($keyInfo['value_preview']) ?></small>
                                                                            </div>
                                                                        </td>
                                                                        <td class="align-middle">
                                                                            <span class="badge bg-light text-dark border">
                                                                                <?= isset($keyInfo['size']) ? number_format($keyInfo['size']) . ' bytes' : 'N/A' ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="align-middle">
                                                                            <button class="btn btn-outline-danger btn-sm rounded-pill"
                                                                                onclick="confirmDeleteKey('<?= htmlspecialchars($keyInfo['key']) ?>', '<?= $prefix ?>', '<?= htmlspecialchars($keyInfo['clean_key']) ?>')"
                                                                                title="Eliminar clave">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif (isset($data['error'])): ?>
                                            <div class="card-body">
                                                <div class="alert alert-warning border-0 rounded-3 mb-0">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Error:</strong> <?= htmlspecialchars($data['error']) ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Cache Manager Container -->

                <!-- Modal de Confirmación -->
                <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 rounded-4 shadow-lg">
                            <div class="modal-header bg-danger text-white border-0 rounded-top-4">
                                <h5 class="modal-title fw-bold" id="confirmModalLabel">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Confirmar Acción
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-question-circle text-warning display-1"></i>
                                </div>
                                <p id="confirmMessage" class="mb-0 fs-5 text-dark"></p>
                            </div>
                            <div class="modal-footer border-0 gap-2">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmButton" data-bs-dismiss="modal">
                                    <i class="fas fa-check me-2"></i> Confirmar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    $(document).ready(function() {
                        loaderefect(0);

                        // Animaciones simples con Bootstrap classes
                        $('.hover-lift').on('mouseenter', function() {
                            $(this).addClass('shadow-lg');
                        }).on('mouseleave', function() {
                            $(this).removeClass('shadow-lg');
                        });
                    });

                    function confirmClearAll() {
                        document.getElementById('confirmMessage').textContent =
                            '¿Está seguro que desea limpiar TODO el cache? Esta acción eliminará todas las claves almacenadas y no se puede deshacer.';

                        document.getElementById('confirmButton').onclick = function() {
                            printdiv('cache_manager', '#cuadro', 'superadmin_02', ['clear_all']);
                        };

                        new bootstrap.Modal(document.getElementById('confirmModal')).show();
                    }

                    function confirmClearPrefix(prefix, description) {
                        document.getElementById('confirmMessage').textContent =
                            `¿Está seguro que desea limpiar todo el cache de "${description}"? Todas las claves de esta categoría serán eliminadas.`;

                        document.getElementById('confirmButton').onclick = function() {
                            printdiv('cache_manager', '#cuadro', 'superadmin_02', ['clear_prefix', prefix]);
                        };

                        new bootstrap.Modal(document.getElementById('confirmModal')).show();
                    }

                    function confirmDeleteKey(fullKey, prefix, key) {
                        document.getElementById('confirmMessage').textContent =
                            `¿Está seguro que desea eliminar la clave "${fullKey}"? Esta acción no se puede deshacer.`;

                        document.getElementById('confirmButton').onclick = function() {
                            printdiv('cache_manager', '#cuadro', 'superadmin_02', ['delete_key', prefix, key, fullKey]);
                        };

                        new bootstrap.Modal(document.getElementById('confirmModal')).show();
                    }
                </script>

            <?php
            break;
        case 'ably_manager':
            $showMessage = false;
            $mensaje = '';
            $status = 1;

            try {
                // Verificar configuración de Ably
                $ablyConfigured = isset($_ENV['ABLY_API_KEY']) && isset($_ENV['ABLY_CHANNEL_HUELLA']);

                if (!$ablyConfigured) {
                    throw new Exception("Ably no está configurado. Verifique las variables de entorno ABLY_API_KEY y ABLY_CHANNEL_HUELLA.");
                }

                // Obtener configuración
                $ablyApiKey = $_ENV['ABLY_API_KEY'];
                $channelPrefix = $_ENV['ABLY_CHANNEL_HUELLA'];
                $ablyClientKey = $_ENV['ABLY_CLIENT_KEY'] ?? 'No configurado';

                // Crear instancia de Ably
                $ably = new \Ably\AblyRest($ablyApiKey);

                // Obtener estadísticas de la aplicación (DESHABILITADO por bugs del SDK)
                // Las estadísticas de Ably tienen un bug conocido en el SDK de PHP
                // cuando no hay datos o en cuentas gratuitas. Se deshabilita para evitar crashes.
                $appStats = null;
                $statsAvailable = false;

                // ⚠️ NOTA: Las estadísticas están deshabilitadas debido a un bug en ably-php
                // El SDK falla con "Attempt to assign property 'delta' on null" cuando:
                // 1. La cuenta es gratuita sin historial suficiente
                // 2. No hay datos de tráfico recientes
                // 3. La API devuelve estructuras de datos incompletas
                // 
                // Para habilitar en el futuro, descomenta el siguiente bloque:
                /*
            try {
                // Configurar manejo de errores para capturar fallos del SDK
                set_error_handler(function($errno, $errstr, $errfile, $errline) {
                    if (strpos($errfile, 'ably-php') !== false || strpos($errstr, 'delta') !== false) {
                        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
                    }
                    return false;
                });
                
                $statsResponse = $ably->stats(['limit' => 1]);
                restore_error_handler();
                
                if (!empty($statsResponse->items) && is_array($statsResponse->items)) {
                    $appStats = $statsResponse->items[0];
                    $statsAvailable = true;
                }
            } catch (ErrorException $e) {
                restore_error_handler();
                Log::debug("Bug conocido de Ably Stats", [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine()
                ]);
                $appStats = null;
            } catch (\Ably\Exceptions\AblyException $e) {
                restore_error_handler();
                Log::warning("Estadísticas de Ably no disponibles", [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
                $appStats = null;
            } catch (Throwable $e) {
                restore_error_handler();
                Log::error("Error fatal obteniendo estadísticas de Ably", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                $appStats = null;
            }
            */

                // Obtener lista de dispositivos únicos de la BD
                $database->openConnection();
                $dispositivos = $database->selectColumns('huella_temp', ['pc_serial', 'update_time', 'statusPlantilla', 'opc'], "1=1 GROUP BY pc_serial ORDER BY update_time DESC", []);
                $database->closeConnection();

                // Información de canales activos
                $canalesActivos = [];
                foreach ($dispositivos as $dispositivo) {
                    $channelName = $channelPrefix . "_" . $dispositivo['pc_serial'];

                    try {
                        $channel = $ably->channel($channelName);

                        // Obtener historial del canal (últimos 10 mensajes)
                        $history = $channel->history(['limit' => 10]);
                        $messageCount = count($history->items);

                        // Analizar tipos de mensajes
                        $messageTypes = [];
                        $lastMessage = null;
                        foreach ($history->items as $msg) {
                            if (!isset($messageTypes[$msg->name])) {
                                $messageTypes[$msg->name] = 0;
                            }
                            $messageTypes[$msg->name]++;

                            if ($lastMessage === null) {
                                $lastMessage = [
                                    'name' => $msg->name,
                                    'timestamp' => $msg->timestamp,
                                    'data_preview' => substr(json_encode($msg->data), 0, 100)
                                ];
                            }
                        }

                        $canalesActivos[] = [
                            'serial' => $dispositivo['pc_serial'],
                            'channel' => $channelName,
                            'message_count' => $messageCount,
                            'message_types' => $messageTypes,
                            'last_message' => $lastMessage,
                            'last_update' => $dispositivo['update_time'],
                            'status' => $dispositivo['statusPlantilla'],
                            'operation' => $dispositivo['opc']
                        ];
                    } catch (Exception $e) {
                        Log::error("Error obteniendo historial del canal", [
                            'channel' => $channelName,
                            'error' => $e->getMessage()
                        ]);

                        $canalesActivos[] = [
                            'serial' => $dispositivo['pc_serial'],
                            'channel' => $channelName,
                            'error' => $e->getMessage(),
                            'last_update' => $dispositivo['update_time']
                        ];
                    }
                }
            } catch (Exception $e) {
                $mensaje = "Error: " . $e->getMessage();
                $status = 0;
                $showMessage = true;
                $ablyConfigured = false;
            }
            ?>

                <!-- Estilos CSS -->
                <style>
                    .ably-manager-wrapper {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                    }

                    .glassmorphism {
                        background: rgba(255, 255, 255, 0.95);
                        backdrop-filter: blur(10px);
                    }

                    .hover-lift:hover {
                        transform: translateY(-3px);
                        transition: all 0.3s ease;
                    }

                    .message-type-badge {
                        padding: 4px 10px;
                        border-radius: 15px;
                        font-size: 0.75rem;
                        font-weight: 600;
                    }

                    .channel-card {
                        border-left: 4px solid;
                        transition: all 0.3s ease;
                    }

                    .channel-card:hover {
                        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
                    }

                    .status-indicator {
                        width: 12px;
                        height: 12px;
                        border-radius: 50%;
                        display: inline-block;
                        animation: pulse 2s infinite;
                    }

                    @keyframes pulse {

                        0%,
                        100% {
                            opacity: 1;
                        }

                        50% {
                            opacity: 0.5;
                        }
                    }

                    .code-block {
                        background: #1e1e1e;
                        color: #d4d4d4;
                        padding: 15px;
                        border-radius: .5rem;
                        font-family: 'Courier New', monospace;
                        font-size: 0.85rem;
                        overflow-x: auto;
                    }
                </style>

                <!-- Ably Manager Container -->
                <div class="ably-manager-wrapper py-4">
                    <div class="container-fluid px-4">
                        <!-- Breadcrumb -->
                        <nav aria-label="breadcrumb" class="mb-3">
                            <ol class="breadcrumb bg-white bg-opacity-25 rounded-pill px-4 py-2">
                                <li class="breadcrumb-item text-white-50">
                                    <i class="fas fa-home me-1"></i> Administración
                                </li>
                                <li class="breadcrumb-item text-white-50">
                                    <i class="fas fa-cogs me-1"></i> Sistema
                                </li>
                                <li class="breadcrumb-item active text-white fw-bold" aria-current="page">
                                    <i class="fas fa-broadcast-tower me-1"></i> Administrador de Ably
                                </li>
                            </ol>
                        </nav>

                        <!-- Header Principal -->
                        <div class="card glassmorphism border-0 rounded-4 shadow-lg mb-4">
                            <div class="card-body p-4">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <h1 class="display-6 text-primary mb-2">
                                            <i class="fas fa-broadcast-tower me-2"></i>
                                            Administrador de Comunicaciones Ably
                                        </h1>
                                        <p class="text-muted mb-0 lead">
                                            Monitorea canales de comunicación en tiempo real con dispositivos biométricos
                                        </p>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="d-flex gap-2 justify-content-lg-end">
                                            <button class="btn btn-primary rounded-pill px-4" onclick="printdiv('ably_manager', '#cuadro', 'superadmin_02', '0')">
                                                <i class="fas fa-sync-alt me-2"></i> Actualizar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alertas -->
                        <?php if ($showMessage): ?>
                            <div class="alert alert-<?= $status ? 'success' : 'danger' ?> alert-dismissible fade show border-0 rounded-4 shadow-sm">
                                <i class="fas fa-<?= $status ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                                <?= htmlspecialchars($mensaje) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($ablyConfigured): ?>
                            <!-- Configuración de Ably -->
                            <div class="card glassmorphism border-0 rounded-4 shadow-lg mb-4">
                                <div class="card-header bg-light bg-opacity-75 border-0 rounded-top-4 p-4">
                                    <h4 class="mb-0 text-primary">
                                        <i class="fas fa-cog me-2"></i> Configuración de Ably
                                    </h4>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-4">
                                        <div class="col-md-4">
                                            <div class="p-3 bg-light rounded-3">
                                                <small class="text-muted d-block mb-1">API Key</small>
                                                <div class="d-flex align-items-center">
                                                    <code class="flex-grow-1"><?= substr($ablyApiKey, 0, 20) ?>...</code>
                                                    <span class="badge bg-success ms-2">Configurado</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 bg-light rounded-3">
                                                <small class="text-muted d-block mb-1">Client Key</small>
                                                <code><?= $ablyClientKey === 'No configurado' ? 'No configurado' : substr($ablyClientKey, 0, 20) . '...' ?></code>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 bg-light rounded-3">
                                                <small class="text-muted d-block mb-1">Canal Base</small>
                                                <code><?= htmlspecialchars($channelPrefix) ?></code>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($statsAvailable && $appStats): ?>
                                        <hr class="my-4">
                                        <h5 class="text-secondary mb-3">
                                            <i class="fas fa-chart-bar me-2"></i> Estadísticas de la Aplicación
                                        </h5>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <div class="text-center p-3 bg-light rounded-3">
                                                    <div class="text-primary mb-2">
                                                        <i class="fas fa-envelope display-6"></i>
                                                    </div>
                                                    <h4 class="fw-bold">
                                                        <?php
                                                        try {
                                                            echo number_format($appStats->all->messages->count ?? 0);
                                                        } catch (Exception $e) {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </h4>
                                                    <small class="text-muted">Mensajes Totales</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center p-3 bg-light rounded-3">
                                                    <div class="text-success mb-2">
                                                        <i class="fas fa-paper-plane display-6"></i>
                                                    </div>
                                                    <h4 class="fw-bold">
                                                        <?php
                                                        try {
                                                            echo number_format($appStats->all->messages->count ?? 0);
                                                        } catch (Exception $e) {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </h4>
                                                    <small class="text-muted">Mensajes Publicados</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center p-3 bg-light rounded-3">
                                                    <div class="text-info mb-2">
                                                        <i class="fas fa-download display-6"></i>
                                                    </div>
                                                    <h4 class="fw-bold">
                                                        <?php
                                                        try {
                                                            echo number_format($appStats->inbound->all->messages->count ?? 0);
                                                        } catch (Exception $e) {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </h4>
                                                    <small class="text-muted">Mensajes Entrantes</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center p-3 bg-light rounded-3">
                                                    <div class="text-warning mb-2">
                                                        <i class="fas fa-upload display-6"></i>
                                                    </div>
                                                    <h4 class="fw-bold">
                                                        <?php
                                                        try {
                                                            echo number_format($appStats->outbound->all->messages->count ?? 0);
                                                        } catch (Exception $e) {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </h4>
                                                    <small class="text-muted">Mensajes Salientes</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <hr class="my-4">
                                        <div class="alert alert-warning border-0 rounded-3 mb-0">
                                            <div class="d-flex align-items-start">
                                                <i class="fas fa-exclamation-triangle me-3 mt-1 fs-4"></i>
                                                <div>
                                                    <h6 class="alert-heading mb-2">Estadísticas Globales Deshabilitadas</h6>
                                                    <p class="mb-2 small">
                                                        Las estadísticas generales de Ably están temporalmente deshabilitadas debido a un
                                                        <strong>bug conocido en el SDK de PHP</strong> que causa errores fatales en:
                                                    </p>
                                                    <ul class="small mb-2">
                                                        <li>Cuentas gratuitas sin historial extenso</li>
                                                        <li>Aplicaciones nuevas con poco tráfico</li>
                                                        <li>Cuando la API devuelve estructuras de datos incompletas</li>
                                                    </ul>
                                                    <p class="mb-0 small">
                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                        <strong>Los canales individuales, mensajes e historial funcionan perfectamente.</strong>
                                                        Toda la información importante está disponible en la sección de canales activos más abajo.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Canales Activos -->
                            <div class="card glassmorphism border-0 rounded-4 shadow-lg mb-4">
                                <div class="card-header bg-light bg-opacity-75 border-0 rounded-top-4 p-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="mb-0 text-primary">
                                            <i class="fas fa-broadcast-tower me-2"></i>
                                            Canales Activos
                                            <span class="badge bg-primary rounded-pill"><?= count($canalesActivos) ?></span>
                                        </h4>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Últimos 10 mensajes por canal
                                        </small>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <?php if (empty($canalesActivos)): ?>
                                        <div class="alert alert-info border-0 rounded-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            No hay dispositivos registrados en el sistema.
                                        </div>
                                    <?php else: ?>
                                        <div class="row g-4">
                                            <?php foreach ($canalesActivos as $canal): ?>
                                                <div class="col-lg-6">
                                                    <div class="card channel-card border-0 rounded-3 shadow-sm h-100 hover-lift"
                                                        style="border-left-color: <?= isset($canal['error']) ? '#dc3545' : '#198754' ?> !important;">
                                                        <div class="card-body p-4">
                                                            <!-- Header del Canal -->
                                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                                <div>
                                                                    <h5 class="mb-1 fw-bold">
                                                                        <i class="fas fa-desktop me-2 text-primary"></i>
                                                                        <?= htmlspecialchars($canal['serial']) ?>
                                                                    </h5>
                                                                    <code class="small text-muted"><?= htmlspecialchars($canal['channel']) ?></code>
                                                                </div>
                                                                <?php if (!isset($canal['error'])): ?>
                                                                    <span class="status-indicator bg-success" title="Canal activo"></span>
                                                                <?php else: ?>
                                                                    <span class="status-indicator bg-danger" title="Error en canal"></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if (isset($canal['error'])): ?>
                                                                <!-- Error -->
                                                                <div class="alert alert-danger border-0 rounded-3 mb-3">
                                                                    <small>
                                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                                        <?= htmlspecialchars($canal['error']) ?>
                                                                    </small>
                                                                </div>
                                                            <?php else: ?>
                                                                <!-- Estadísticas del Canal -->
                                                                <div class="row g-2 mb-3">
                                                                    <div class="col-6">
                                                                        <div class="p-2 bg-light rounded-2 text-center">
                                                                            <div class="small text-muted">Mensajes</div>
                                                                            <div class="fw-bold fs-5"><?= $canal['message_count'] ?></div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <div class="p-2 bg-light rounded-2 text-center">
                                                                            <div class="small text-muted">Tipos</div>
                                                                            <div class="fw-bold fs-5"><?= count($canal['message_types']) ?></div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Tipos de Mensajes -->
                                                                <?php if (!empty($canal['message_types'])): ?>
                                                                    <div class="mb-3">
                                                                        <small class="text-muted d-block mb-2">Tipos de mensajes:</small>
                                                                        <div class="d-flex flex-wrap gap-1">
                                                                            <?php foreach ($canal['message_types'] as $type => $count): ?>
                                                                                <?php
                                                                                $bgClass = 'bg-secondary';
                                                                                if ($type === 'huella_digital_service') $bgClass = 'bg-primary';
                                                                                if ($type === 'confirmacion') $bgClass = 'bg-success';
                                                                                if ($type === 'resultado') $bgClass = 'bg-info';
                                                                                if ($type === 'error') $bgClass = 'bg-danger';
                                                                                if ($type === 'sinc') $bgClass = 'bg-warning';
                                                                                ?>
                                                                                <span class="message-type-badge <?= $bgClass ?> text-white">
                                                                                    <?= htmlspecialchars($type) ?>
                                                                                    <span class="badge bg-white bg-opacity-25 ms-1"><?= $count ?></span>
                                                                                </span>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <!-- Último Mensaje -->
                                                                <?php if ($canal['last_message']): ?>
                                                                    <div class="border-top pt-3">
                                                                        <small class="text-muted d-block mb-2">
                                                                            <i class="fas fa-clock me-1"></i>
                                                                            Último mensaje:
                                                                            <?= date('d/m/Y H:i:s', (int)($canal['last_message']['timestamp'] / 1000)) ?>
                                                                        </small>
                                                                        <div class="code-block">
                                                                            <div class="small">
                                                                                <span class="text-info">"<?= htmlspecialchars($canal['last_message']['name']) ?>"</span>:
                                                                                <?= htmlspecialchars($canal['last_message']['data_preview']) ?>
                                                                                <?php if (strlen(json_encode($canal['last_message']['data_preview'])) > 100): ?>...<?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <!-- Info Adicional -->
                                                                <div class="border-top pt-3 mt-3">
                                                                    <div class="row g-2 small">
                                                                        <div class="col-6">
                                                                            <span class="text-muted">Última actualización:</span><br>
                                                                            <strong><?= htmlspecialchars($canal['last_update'] ?? '-') ?></strong>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <span class="text-muted">Estado:</span><br>
                                                                            <span class="badge bg-<?= $canal['status'] == 1 ? 'success' : 'secondary' ?>">
                                                                                <?= $canal['status'] == 1 ? 'Activo' : 'Inactivo' ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Botón Ver Historial Completo -->
                                                            <div class="mt-3">
                                                                <button class="btn btn-sm btn-outline-primary rounded-pill w-100"
                                                                    onclick="verHistorialCompleto('<?= htmlspecialchars($canal['serial']) ?>', '<?= htmlspecialchars($canal['channel']) ?>')">
                                                                    <i class="fas fa-history me-2"></i> Ver Historial Completo
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- Ably No Configurado -->
                            <div class="card glassmorphism border-0 rounded-4 shadow-lg">
                                <div class="card-body text-center p-5">
                                    <i class="fas fa-exclamation-triangle text-warning display-1 mb-4"></i>
                                    <h3 class="text-dark mb-3">Ably no está configurado</h3>
                                    <p class="text-muted mb-4">
                                        Para utilizar el administrador de Ably, debe configurar las siguientes variables de entorno:
                                    </p>
                                    <div class="code-block text-start mx-auto" style="max-width: 600px;">
                                        ABLY_API_KEY=tu_api_key_aqui<br>
                                        ABLY_CLIENT_KEY=tu_client_key_aqui<br>
                                        ABLY_CHANNEL_HUELLA=nombre_del_canal
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Modal Historial Completo -->
                <div class="modal fade" id="modalHistorial" tabindex="-1" aria-labelledby="modalHistorialLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content border-0 rounded-4">
                            <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                                <h5 class="modal-title fw-bold" id="modalHistorialLabel">
                                    <i class="fas fa-history me-2"></i> Historial de Mensajes
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div id="historialContent">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="text-muted mt-3">Cargando historial...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i> Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function verHistorialCompleto(serial, channel) {
                        $('#modalHistorial').modal('show');
                        $('#historialContent').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted mt-3">Cargando historial de ${serial}...</p>
                    </div>
                `);

                        // Aquí podrías hacer una llamada AJAX para obtener más mensajes
                        $.ajax({
                            url: '../../../../src/cruds/crud_ably_manager.php',
                            method: 'POST',
                            data: {
                                condi: 'get_full_history',
                                serial: serial,
                                channel: channel
                            },
                            success: function(response) {
                                try {
                                    const data = typeof response === 'string' ? JSON.parse(response) : response;

                                    if (data.status === 1 && data.messages) {
                                        let html = '<div class="timeline">';

                                        data.messages.forEach((msg, index) => {
                                            const typeColors = {
                                                'huella_digital_service': 'primary',
                                                'confirmacion': 'success',
                                                'resultado': 'info',
                                                'error': 'danger',
                                                'sinc': 'warning'
                                            };
                                            const color = typeColors[msg.name] || 'secondary';
                                            const timestamp = new Date(msg.timestamp).toLocaleString('es-GT');

                                            html += `
                                        <div class="card mb-3 border-start border-${color} border-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="mb-0">
                                                        <span class="badge bg-${color}">${msg.name}</span>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i> ${timestamp}
                                                    </small>
                                                </div>
                                                <div class="code-block mt-2">
                                                    <pre class="mb-0 small">${JSON.stringify(msg.data, null, 2)}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                        });

                                        html += '</div>';
                                        $('#historialContent').html(html);
                                    } else {
                                        $('#historialContent').html(`
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        ${data.message || 'No se pudo cargar el historial'}
                                    </div>
                                `);
                                    }
                                } catch (e) {
                                    console.error('Error:', e);
                                    $('#historialContent').html(`
                                <div class="alert alert-danger">
                                    <i class="fas fa-times-circle me-2"></i>
                                    Error procesando la respuesta
                                </div>
                            `);
                                }
                            },
                            error: function() {
                                $('#historialContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle me-2"></i>
                                Error al cargar el historial
                            </div>
                        `);
                            }
                        });
                    }

                    $(document).ready(function() {
                        loaderefect(0);
                    });
                </script>
            <?php
            break;
        case 'logs_registros':
            if (!function_exists('superadminTailLog')) {
                function superadminTailLog($filePath, $lines = 400)
                {
                    if (!is_readable($filePath)) {
                        return '';
                    }
                    $handle = fopen($filePath, 'rb');
                    if (!$handle) {
                        return '';
                    }
                    $chunkSize = 4096;
                    $buffer = '';
                    fseek($handle, 0, SEEK_END);
                    $position = ftell($handle);
                    while ($position > 0 && substr_count($buffer, "\n") <= $lines) {
                        $readSize = ($position - $chunkSize) >= 0 ? $chunkSize : $position;
                        $position -= $readSize;
                        fseek($handle, $position);
                        $buffer = fread($handle, $readSize) . $buffer;
                        if ($position === 0) {
                            break;
                        }
                    }
                    fclose($handle);
                    $rows = explode("\n", $buffer);
                    return implode("\n", array_slice($rows, -$lines));
                }
            }

            if (!function_exists('superadminNormalizeLogText')) {
                function superadminNormalizeLogText($text)
                {
                    $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                    return $encoding && $encoding !== 'UTF-8'
                        ? mb_convert_encoding($text, 'UTF-8', $encoding)
                        : $text;
                }
            }
            $logsDir = realpath(__DIR__ . '/../../../../logs');
            $logFiles = [];
            if ($logsDir && is_dir($logsDir)) {
                foreach (glob($logsDir . '/*.log') as $filePath) {
                    $logFiles[] = [
                        'name' => basename($filePath),
                        'path' => $filePath,
                        'size' => filesize($filePath),
                        'mtime' => filemtime($filePath),
                        'content' => superadminNormalizeLogText(superadminTailLog($filePath, 400))
                    ];
                }
            }
            usort($logFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
            $logCount = count($logFiles);
            $totalBytes = array_sum(array_column($logFiles, 'size'));
            $logsBaseUrl = (defined('BASE_URL') ? BASE_URL : '/') . 'logs/';
            ?>
                <div class="container-fluid mt-4">
                    <input type="text" id="file" value="superadmin_02" hidden>
                    <input type="text" id="condi" value="logs_registros" hidden>
                    <?php if ($logCount === 0): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-circle-info me-2"></i>No se encontraron archivos de log en <?= htmlspecialchars($logsDir ?: 'N/D'); ?>.
                        </div>
                    <?php else: ?>
                        <div class="card shadow-sm">
                            <div class="card-header bg-dark text-white d-flex flex-wrap justify-content-between">
                                <div>
                                    <h4 class="mb-0"><i class="fa-solid fa-file-lines me-2"></i>Visor de Logs</h4>
                                    <small>Mostrando los últimos 400 renglones por archivo</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary me-1"><?= $logCount ?> archivos</span>
                                    <span class="badge bg-info"><?= number_format($totalBytes / 1024, 2) ?> KB</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="list-group small" id="logList">
                                            <?php foreach ($logFiles as $idx => $log): ?>
                                                <button type="button"
                                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $idx === 0 ? 'active' : '' ?>"
                                                    data-log-target="log-panel-<?= $idx ?>">
                                                    <div>
                                                        <strong><?= htmlspecialchars($log['name']) ?></strong><br>
                                                        <span class="text-muted"><?= date('d/m/Y H:i:s', $log['mtime']) ?></span>
                                                    </div>
                                                    <span class="badge bg-secondary"><?= number_format($log['size'] / 1024, 1) ?> KB</span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-2 d-flex gap-2">
                                            <input type="text" id="logSearch" class="form-control form-control-sm"
                                                placeholder="Buscar dentro del log seleccionado...">
                                            <button class="btn btn-outline-secondary btn-sm" id="clearSearch">
                                                <i class="fa-solid fa-eraser"></i>
                                            </button>
                                        </div>
                                        <div class="position-relative">
                                            <div class="log-actions position-absolute top-0 end-0 m-2 d-flex gap-2">
                                                <button class="btn btn-outline-primary btn-sm" id="copyLog">
                                                    <i class="fa-solid fa-copy me-1"></i>Copiar
                                                </button>
                                                <button class="btn btn-outline-success btn-sm" id="downloadLog">
                                                    <i class="fa-solid fa-download me-1"></i>Descargar
                                                </button>
                                            </div>
                                        </div>
                                        <?php foreach ($logFiles as $idx => $log): ?>
                                            <div class="log-panel <?= $idx === 0 ? '' : 'd-none' ?>" id="log-panel-<?= $idx ?>"
                                                data-file-name="<?= htmlspecialchars($log['name']) ?>"
                                                data-file-url="<?= htmlspecialchars($logsBaseUrl . rawurlencode($log['name'])) ?>">
                                                <div class="alert alert-light d-flex justify-content-between py-2 px-3 mb-2">
                                                    <div>
                                                        <strong><?= htmlspecialchars($log['name']) ?></strong>
                                                        <small class="text-muted d-block">Actualizado: <?= date('d/m/Y H:i:s', $log['mtime']) ?></small>
                                                    </div>
                                                    <span class="badge bg-dark align-self-center"><?= number_format($log['size'] / 1024, 2) ?> KB</span>
                                                </div>
                                                <pre class="log-view" data-raw="<?= base64_encode($log['content']) ?>"><?= htmlspecialchars($log['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: '— Sin registros —' ?></pre>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <style>
                    .log-view {
                        background: #0d1117;
                        color: #e6edf3;
                        padding: 1rem;
                        border-radius: .5rem;
                        height: 500px;
                        overflow-y: auto;
                        font-family: "Fira Code", monospace;
                        font-size: .85rem;
                        white-space: pre-wrap;
                    }

                    .log-actions {
                        z-index: 10;
                    }
                </style>

                <script>
                    (function initLogsViewer() {
                        const rootList = document.getElementById('logList');
                        if (!rootList) {
                            return;
                        }
                        const panels = Array.from(document.querySelectorAll('.log-panel'));
                        const listButtons = rootList.querySelectorAll('[data-log-target]');
                        const searchInput = document.getElementById('logSearch');
                        const clearBtn = document.getElementById('clearSearch');
                        const copyBtn = document.getElementById('copyLog');
                        const downloadBtn = document.getElementById('downloadLog');

                        const escapeHtml = (value = '') => value.replace(/[&<>"']/g, chr => ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#39;'
                        } [chr] || chr));

                        const getActivePanel = () => document.querySelector('.log-panel:not(.d-none)') || panels[0] || null;

                        const applySearchHighlight = () => {
                            const panel = getActivePanel();
                            if (!panel) return;
                            const pre = panel.querySelector('.log-view');
                            const raw = pre?.dataset.raw ? atob(pre.dataset.raw) : '';
                            const term = (searchInput?.value || '').trim();
                            if (!term) {
                                pre.textContent = raw || '— Sin registros —';
                                return;
                            }
                            const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                            const parts = raw.split(new RegExp(`(${escapedTerm})`, 'gi'));
                            const lowerTerm = term.toLowerCase();
                            pre.innerHTML = parts.map(segment => {
                                if (!segment) return '';
                                return segment.toLowerCase() === lowerTerm ?
                                    `<mark>${escapeHtml(segment)}</mark>` :
                                    escapeHtml(segment);
                            }).join('');
                        };

                        const switchPanel = targetId => {
                            panels.forEach(panel => panel.classList.toggle('d-none', panel.id !== targetId));
                            listButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.logTarget === targetId));
                            applySearchHighlight();
                        };

                        listButtons.forEach(btn => btn.addEventListener('click', () => switchPanel(btn.dataset.logTarget)));

                        searchInput?.addEventListener('input', applySearchHighlight);

                        clearBtn?.addEventListener('click', () => {
                            if (searchInput) {
                                searchInput.value = '';
                                applySearchHighlight();
                            }
                        });

                        copyBtn?.addEventListener('click', async () => {
                            const panel = getActivePanel();
                            if (!panel) return;
                            const pre = panel.querySelector('.log-view');
                            const raw = pre?.dataset.raw ? atob(pre.dataset.raw) : '';
                            try {
                                await navigator.clipboard.writeText(raw);
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Copiado',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            } catch (error) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'No se pudo copiar',
                                    text: error?.message || ''
                                });
                            }
                        });

                        downloadBtn?.addEventListener('click', () => {
                            const panel = getActivePanel();
                            if (!panel) return;
                            const url = panel.dataset.fileUrl;
                            const name = panel.dataset.fileName || 'log.txt';
                            if (!url) return;
                            const anchor = document.createElement('a');
                            anchor.href = url;
                            anchor.download = name;
                            document.body.appendChild(anchor);
                            anchor.click();
                            document.body.removeChild(anchor);
                        });

                        applySearchHighlight();
                    })();
                </script>
            <?php
            break;
        case 'permissions_modules':

            $showmensaje = false;
            try {
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

                $database->openConnection(2);

                $modulosPermisos = $database->getAllResults(
                    "SELECT mo.id idModulo, mo.descripcion, mo.rama, mo.estado, per.id idPermiso, IFNULL(per.estado,0) estadoPermiso, per.comentario 
                FROM tb_modulos mo
                LEFT JOIN tb_permisos_modulos per ON per.id_modulo=mo.id AND  per.id_cooperativa = ?",
                    [$idInstitucion]
                );

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

            // Vista: tabla con toggles para activar / desactivar permisos por módulo (solo clases Bootstrap)
            ?>
                <div class="container-fluid mt-3">
                    <input type="hidden" id="file" value="superadmin_02" hidden>
                    <input type="hidden" id="condi" value="permissions_modules" hidden>
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i> Gestión de permisos por módulo</h5>
                            <small class="text-light">Institución: <?= htmlspecialchars($idInstitucion) ?></small>
                        </div>

                        <div class="card-body">
                            <?php if (empty($modulosPermisos)): ?>
                                <div class="alert alert-info mb-0">No se encontraron módulos.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width:60px;">#</th>
                                                <th>Módulo</th>
                                                <th>Rama</th>
                                                <th style="width:130px;">Disponible</th>
                                                <th style="width:130px;">Permiso</th>
                                                <th style="width:200px;">Comentario</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($modulosPermisos as $idx => $m): ?>
                                                <?php
                                                $idModulo = (int)$m['idModulo'];
                                                $idPermiso = $m['idPermiso'] ?? '';
                                                $estadoModulo = (int)($m['estado'] ?? 0);
                                                $estadoPermiso = (int)($m['estadoPermiso'] ?? 0);
                                                ?>
                                                <tr>
                                                    <td class="align-middle"><?= htmlspecialchars($idx + 1) ?></td>
                                                    <td class="align-middle">
                                                        <?= htmlspecialchars($m['descripcion']) ?>
                                                        <?php if (!$estadoModulo): ?>
                                                            <div><small class="text-muted">(módulo inactivo)</small></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="align-middle"><small class="text-muted"><?= htmlspecialchars($m['rama'] ?? '-') ?></small></td>
                                                    <td class="align-middle">
                                                        <?php if ($estadoModulo): ?>
                                                            <span class="badge bg-success">Sí</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">No</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="align-middle">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input permission-toggle" type="checkbox" onchange="sendPet(this)"
                                                                id="perm_toggle_<?= $idModulo ?>"
                                                                data-id-modulo="<?= $idModulo ?>"
                                                                data-id-permiso="<?= htmlspecialchars($idPermiso) ?>"
                                                                <?= $estadoPermiso ? 'checked' : '' ?>
                                                                <?= $estadoModulo ? '' : 'disabled' ?>
                                                                <label class="form-check-label" for="perm_toggle_<?= $idModulo ?>">
                                                            <span class="small"><?= $estadoPermiso ? 'Activo' : 'Inactivo' ?></span>
                                                            </label>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle"><small class="text-muted"><?= htmlspecialchars($m['comentario'] ?? '') ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer text-end">
                            <small class="text-muted">Cambios aplicados en tiempo real.</small>
                        </div>
                    </div>
                </div>

                <script>
                    function sendPet(chk) {
                        const idModulo = $(chk).data('id-modulo');
                        const idPermiso = $(chk).data('id-permiso'); // puede ser vacío si no existe registro aún
                        const nuevoEstado = $(chk).is(':checked') ? 1 : 0;
                        obtiene([], [], [], 'change_permission_modules', '0', [idModulo, nuevoEstado, idPermiso])
                    }
                </script>

        <?php
            break;
    }
