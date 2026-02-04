<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
    return;
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../includes/Config/database.php';
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../includes/Config/PermissionHandler.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];

require_once __DIR__ . '/../../../includes/Config/PermissionHandler.php';

switch ($condi) {
    // ==================== REPORTE 1: EMPLEADOS ACTIVOS IVE ====================
    case 'empleados_activos_ive':
        $mediumPermissionId = 16; // Permiso de ver reportes a nivel de agencia
        $highPermissionId = 17;   // Permiso de ver reportes nivel general [Todas las agencias]
        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?,?) AND id_usuario=? AND estado=1", [$mediumPermissionId, $highPermissionId, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $condicion = ($accessHandler->isHigh()) ? "" : "id_agencia=?";
            $parametros = ($accessHandler->isHigh()) ? [] :  [$idagencia];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia', 'cod_agenc'], $condicion, $parametros);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No existen agencias por revisar");
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
    ?>
        <div class="text text-center">REPORTE IVE - EMPLEADOS ACTIVOS</div>
        <input type="hidden" id="condi" value="empleados_activos_ive">
        <input type="hidden" id="file" value="view002">

        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php
                return;
            }  ?>
            <div class="card-header">Filtros de Generación</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <label><strong>Tipo de Fecha</strong></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha1" id="corte1" value="corte" checked>
                                            <label for="corte1" class="form-check-label">Fecha de Corte</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha1" id="rango1" value="rango">
                                            <label for="rango1" class="form-check-label">Rango de Fechas</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12" id="divCorte1">
                                        <label for="ffin">Fecha de Corte</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-sm-12" id="divRango1a" style="display:none;">
                                        <label for="finicio">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="finicio" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-sm-12 mt-2" id="divRango1b" style="display:none;">
                                        <label for="ffin2">Fecha Fin</label>
                                        <input type="date" class="form-control" id="ffin2" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi1" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi1" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi1" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi1" class="form-check-label">Por Agencia</label>
                                            <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaEmpleados()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha1'], []], 'xlsx', 'repo_ive_01', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha1'], []], 'pdf', 'repo_ive_01', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Nombre completo, Puesto, Fecha ingreso, Salario, Productos vigentes, Saldos, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_empleados_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Puesto</th>
                                <th>Fecha Ingreso</th>
                                <th>Salario</th>
                                <th>Productos Vigentes</th>
                                <th>Saldos</th>
                                <th>Agencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Inicializar DataTable con columnas definidas
                window.dt_empleados_ive = $('#tb_empleados_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "sProcessing":     "Procesando...",
                        "sLengthMenu":     "Mostrar _MENU_ registros",
                        "sZeroRecords":    "No se encontraron resultados",
                        "sEmptyTable":     "Cargando datos...",
                        "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                        "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                        "sSearch":         "Buscar:",
                        "oPaginate": {
                            "sFirst":    "Primero",
                            "sLast":     "Último",
                            "sNext":     "Siguiente",
                            "sPrevious": "Anterior"
                        }
                    },
                    "columns": [
                        { "data": "nombre" },
                        { "data": "puesto" },
                        { "data": "fecha_ingreso" },
                        { 
                            "data": "salario",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "productos_vigentes" },
                        { 
                            "data": "saldos",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "agencia" }
                    ]
                });

                $('input[name="ragencia"]').change(function() {
                    if ($(this).val() === 'allofi') {
                        $('#codofi').prop('disabled', true);
                    } else {
                        $('#codofi').prop('disabled', false);
                    }
                });

                $('input[name="tipofecha1"]').change(function() {
                    if ($(this).val() === 'rango') {
                        $('#divCorte1').hide();
                        $('#divRango1a, #divRango1b').show();
                    } else {
                        $('#divCorte1').show();
                        $('#divRango1a, #divRango1b').hide();
                    }
                });

                // Cargar vista previa automáticamente al iniciar
                cargarVistaPreviaEmpleados();
            });

            // Función para cargar vista previa de empleados
            function cargarVistaPreviaEmpleados() {
                var tipofecha = $('input[name="tipofecha1"]:checked').val();
                var fechas = [];
                
                if (tipofecha === 'corte') {
                    var ffin = $('#ffin').val();
                    fechas = [ffin, '', ''];
                } else {
                    var finicio = $('#finicio').val();
                    var ffin2 = $('#ffin2').val();
                    fechas = ['', finicio, ffin2];
                }

                var ragencia = $('input[name="ragencia"]:checked').val();
                var codofi = $('#codofi').val();

                // Mostrar loading en la tabla
                $('#tb_empleados_ive tbody').html('<tr><td colspan="7" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_01.php',
                    type: 'POST',
                    data: {
                        datosval: [
                            fechas,
                            [codofi],
                            [ragencia, tipofecha],
                            ['vista_previa']
                        ],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            window.dt_empleados_ive.clear();
                            window.dt_empleados_ive.rows.add(response.data);
                            window.dt_empleados_ive.draw();
                        } else {
                            $('#tb_empleados_ive tbody').html('<tr><td colspan="7" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_empleados_ive tbody').html('<tr><td colspan="7" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;
    // ==================== REPORTE 2: CLIENTES PERSONAS INDIVIDUALES IVE ====================
    case 'clientes_individuales_ive':
        $mediumPermissionId = 16; // Permiso de ver reportes a nivel de agencia
        $highPermissionId = 17;   // Permiso de ver reportes nivel general [Todas las agencias]
        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?,?) AND id_usuario=? AND estado=1", [$mediumPermissionId, $highPermissionId, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $condicion = ($accessHandler->isHigh()) ? "" : "id_agencia=?";
            $parametros = ($accessHandler->isHigh()) ? [] :  [$idagencia];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia', 'cod_agenc'], $condicion, $parametros);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No existen agencias por revisar");
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
    ?>
        <div class="text text-center">REPORTE IVE - CLIENTES PERSONAS INDIVIDUALES</div>
        <input type="hidden" id="condi" value="clientes_individuales_ive">
        <input type="hidden" id="file" value="view002">

        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php
                return;
            }  ?>
            <div class="card-header">Filtros de Generación</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <label><strong>Tipo de Fecha</strong></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha2" id="corte2" value="corte" checked>
                                            <label for="corte2" class="form-check-label">Fecha de Corte</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha2" id="rango2" value="rango">
                                            <label for="rango2" class="form-check-label">Rango de Fechas</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12" id="divCorte2">
                                        <label for="ffin">Fecha de Corte</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-sm-12" id="divRango2a" style="display:none;">
                                        <label for="finicio">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="finicio" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-sm-12 mt-2" id="divRango2b" style="display:none;">
                                        <label for="ffin2">Fecha Fin</label>
                                        <input type="date" class="form-control" id="ffin2" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi2" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi2" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi2" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi2" class="form-check-label">Por Agencia</label>
                                            <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaClientes()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha2'], []], 'xlsx', 'repo_ive_02', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha2'], []], 'pdf', 'repo_ive_02', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Código, Nombre, DPI, Tipo producto, Fecha inicio relación, Fecha producto, Saldo, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_clientes_ind_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>DPI</th>
                                <th>Tipo Producto</th>
                                <th>Fecha Inicio Relación</th>
                                <th>Fecha Producto</th>
                                <th>Saldo</th>
                                <th>Agencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Inicializar DataTable sin datos
                window.dt_clientes_ind_ive = $('#tb_clientes_ind_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "sProcessing":     "Procesando...",
                        "sLengthMenu":     "Mostrar _MENU_ registros",
                        "sZeroRecords":    "No se encontraron resultados",
                        "sEmptyTable":     "Cargando datos...",
                        "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                        "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                        "sSearch":         "Buscar:",
                        "oPaginate": {
                            "sFirst":    "Primero",
                            "sLast":     "Último",
                            "sNext":     "Siguiente",
                            "sPrevious": "Anterior"
                        }
                    },
                    "columns": [
                        { "data": "codigo" },
                        { "data": "nombre" },
                        { "data": "dpi" },
                        { "data": "tipo_producto" },
                        { "data": "fecha_inicio_relacion" },
                        { "data": "fecha_producto" },
                        { 
                            "data": "saldo",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "agencia" }
                    ]
                });

                $('input[name="ragencia"]').change(function() {
                    if ($(this).val() === 'allofi') {
                        $('#codofi').prop('disabled', true);
                    } else {
                        $('#codofi').prop('disabled', false);
                    }
                });

                $('input[name="tipofecha2"]').change(function() {
                    if ($(this).val() === 'rango') {
                        $('#divCorte2').hide();
                        $('#divRango2a, #divRango2b').show();
                    } else {
                        $('#divCorte2').show();
                        $('#divRango2a, #divRango2b').hide();
                    }
                });

                // Cargar vista previa automáticamente al iniciar
                cargarVistaPreviaClientes();
            });

            // Función para cargar vista previa automáticamente (sin validaciones estrictas)
            function cargarVistaPreviaClientes() {
                var tipofecha = $('input[name="tipofecha2"]:checked').val();
                var fechas = [];
                
                if (tipofecha === 'corte') {
                    var ffin = $('#ffin').val();
                    fechas = [ffin, '', ''];
                } else {
                    var finicio = $('#finicio').val();
                    var ffin2 = $('#ffin2').val();
                    fechas = ['', finicio, ffin2];
                }

                var ragencia = $('input[name="ragencia"]:checked').val();
                var codofi = $('#codofi').val();

                // Mostrar loading en la tabla
                $('#tb_clientes_ind_ive tbody').html('<tr><td colspan="8" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_02.php',
                    type: 'POST',
                    data: {
                        datosval: [
                            fechas,
                            [codofi],
                            [ragencia, tipofecha],
                            ['vista_previa']
                        ],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            window.dt_clientes_ind_ive.clear();
                            window.dt_clientes_ind_ive.rows.add(response.data);
                            window.dt_clientes_ind_ive.draw();
                        } else {
                            $('#tb_clientes_ind_ive tbody').html('<tr><td colspan="8" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_clientes_ind_ive tbody').html('<tr><td colspan="8" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 3: CLIENTES PERSONAS JURIDICAS IVE ====================
    case 'clientes_juridicas_ive':
        $mediumPermissionId = 16; // Permiso de ver reportes a nivel de agencia
        $highPermissionId = 17;   // Permiso de ver reportes nivel general [Todas las agencias]
        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?,?) AND id_usuario=? AND estado=1", [$mediumPermissionId, $highPermissionId, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $condicion = ($accessHandler->isHigh()) ? "" : "id_agencia=?";
            $parametros = ($accessHandler->isHigh()) ? [] :  [$idagencia];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia', 'cod_agenc'], $condicion, $parametros);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No existen agencias por revisar");
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
    ?>
        <div class="text text-center">REPORTE IVE - CLIENTES PERSONAS JURÍDICAS</div>
        <input type="hidden" id="condi" value="clientes_juridicas_ive">
        <input type="hidden" id="file" value="view002">

        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php
                return;
            }  ?>
            <div class="card-header">Filtros de Generación</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <label><strong>Tipo de Fecha</strong></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha3" id="corte3" value="corte" checked>
                                            <label for="corte3" class="form-check-label">Fecha de Corte</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha3" id="rango3" value="rango">
                                            <label for="rango3" class="form-check-label">Rango de Fechas</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12" id="divCorte3">
                                        <label for="ffin">Fecha de Corte</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-sm-12" id="divRango3a" style="display:none;">
                                        <label for="finicio">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="finicio" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-sm-12 mt-2" id="divRango3b" style="display:none;">
                                        <label for="ffin2">Fecha Fin</label>
                                        <input type="date" class="form-control" id="ffin2" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi3" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi3" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi3" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi3" class="form-check-label">Por Agencia</label>
                                            <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaJuridicas()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha3'], []], 'xlsx', 'repo_ive_03', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha3'], []], 'pdf', 'repo_ive_03', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Código, Razón Social, NIT/Registro, Representante Legal, Tipo Producto, Fecha Inicio, Fecha Producto, Saldo, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_clientes_jur_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Razón Social</th>
                                <th>NIT/Registro</th>
                                <th>Representante Legal</th>
                                <th>Tipo Producto</th>
                                <th>Fecha Inicio</th>
                                <th>Fecha Producto</th>
                                <th>Saldo</th>
                                <th>Agencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Inicializar DataTable con columnas definidas
                window.dt_clientes_jur_ive = $('#tb_clientes_jur_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "sProcessing":     "Procesando...",
                        "sLengthMenu":     "Mostrar _MENU_ registros",
                        "sZeroRecords":    "No se encontraron resultados",
                        "sEmptyTable":     "Cargando datos...",
                        "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                        "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                        "sSearch":         "Buscar:",
                        "oPaginate": {
                            "sFirst":    "Primero",
                            "sLast":     "Último",
                            "sNext":     "Siguiente",
                            "sPrevious": "Anterior"
                        }
                    },
                    "columns": [
                        { "data": "codigo" },
                        { "data": "razon_social" },
                        { "data": "nit_registro" },
                        { "data": "representante_legal" },
                        { "data": "tipo_producto" },
                        { "data": "fecha_inicio_relacion" },
                        { "data": "fecha_producto" },
                        { 
                            "data": "saldo",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "agencia" }
                    ]
                });

                $('input[name="ragencia"]').change(function() {
                    if ($(this).val() === 'allofi') {
                        $('#codofi').prop('disabled', true);
                    } else {
                        $('#codofi').prop('disabled', false);
                    }
                });

                $('input[name="tipofecha3"]').change(function() {
                    if ($(this).val() === 'rango') {
                        $('#divCorte3').hide();
                        $('#divRango3a, #divRango3b').show();
                    } else {
                        $('#divCorte3').show();
                        $('#divRango3a, #divRango3b').hide();
                    }
                });

                // Cargar vista previa automáticamente al iniciar
                cargarVistaPreviaJuridicas();
            });

            // Función para cargar vista previa de clientes jurídicos
            function cargarVistaPreviaJuridicas() {
                var tipofecha = $('input[name="tipofecha3"]:checked').val();
                var fechas = [];
                
                if (tipofecha === 'corte') {
                    var ffin = $('#ffin').val();
                    fechas = [ffin, '', ''];
                } else {
                    var finicio = $('#finicio').val();
                    var ffin2 = $('#ffin2').val();
                    fechas = ['', finicio, ffin2];
                }

                var ragencia = $('input[name="ragencia"]:checked').val();
                var codofi = $('#codofi').val();

                // Mostrar loading en la tabla
                $('#tb_clientes_jur_ive tbody').html('<tr><td colspan="9" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_03.php',
                    type: 'POST',
                    data: {
                        datosval: [
                            fechas,
                            [codofi],
                            [ragencia, tipofecha],
                            ['vista_previa']
                        ],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            window.dt_clientes_jur_ive.clear();
                            window.dt_clientes_jur_ive.rows.add(response.data);
                            window.dt_clientes_jur_ive.draw();
                        } else {
                            $('#tb_clientes_jur_ive tbody').html('<tr><td colspan="9" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_clientes_jur_ive tbody').html('<tr><td colspan="9" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 4: CUENTAS CAPTACIÓN APERTURADAS IVE ====================
    case 'cuentas_aperturadas_ive':
        $mediumPermissionId = 16; // Permiso de ver reportes a nivel de agencia
        $highPermissionId = 17;   // Permiso de ver reportes nivel general [Todas las agencias]
        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?,?) AND id_usuario=? AND estado=1", [$mediumPermissionId, $highPermissionId, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $condicion = ($accessHandler->isHigh()) ? "" : "id_agencia=?";
            $parametros = ($accessHandler->isHigh()) ? [] :  [$idagencia];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia', 'cod_agenc'], $condicion, $parametros);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No existen agencias por revisar");
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
    ?>
        <div class="text text-center">REPORTE IVE - CUENTAS DE CAPTACIÓN APERTURADAS</div>
        <input type="hidden" id="condi" value="cuentas_aperturadas_ive">
        <input type="hidden" id="file" value="view002">

        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php
                return;
            }  ?>
            <div class="card-header">Filtros de Generación</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <label for="finicio">Fecha Desde</label>
                                        <input type="date" class="form-control" id="finicio" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12">
                                        <label for="ffin">Fecha Hasta</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date('Y-m-d'); ?>">
                                        <small class="text-muted">Cuentas aperturadas en este periodo</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi4" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi4" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi4" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi4" class="form-check-label">Por Agencia</label>
                                            <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx', 'repo_ive_04', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'pdf', 'repo_ive_04', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaCuentasAperturadas()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Número cuenta, Tipo cuenta, Nombre, Tipo persona, Fecha apertura, Monto apertura, Saldo, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_cuentas_aper_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Número Cuenta</th>
                                <th>Tipo Cuenta</th>
                                <th>Nombre Cliente</th>
                                <th>Tipo Persona</th>
                                <th>Fecha Apertura</th>
                                <th>Monto Apertura</th>
                                <th>Saldo Actual</th>
                                <th>Agencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Inicializar DataTable con columnas definidas
                window.dt_cuentas_aper_ive = $('#tb_cuentas_aper_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "numero_cuenta" },
                        { "data": "tipo_cuenta" },
                        { "data": "nombre_cliente" },
                        { "data": "tipo_persona" },
                        { "data": "fecha_apertura" },
                        { 
                            "data": "monto_apertura",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { 
                            "data": "saldo_actual",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "agencia" }
                    ]
                });

                $('input[name="ragencia"]').change(function() {
                    if ($(this).val() === 'allofi') {
                        $('#codofi').prop('disabled', true);
                    } else {
                        $('#codofi').prop('disabled', false);
                    }
                });

                // Cargar vista previa al iniciar
                cargarVistaPreviaCuentasAperturadas();
            });

            // Función para cargar vista previa de cuentas aperturadas
            function cargarVistaPreviaCuentasAperturadas() {
                var finicio = $('#finicio').val();
                var ffin = $('#ffin').val();
                var codofi = $('#codofi').val();
                var ragencia = $('input[name="ragencia"]:checked').val();

                // Mostrar loading en la tabla
                $('#tb_cuentas_aper_ive tbody').html('<tr><td colspan="8" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_04.php',
                    type: 'POST',
                    data: {
                        datosval: [
                            [finicio, ffin],
                            [codofi],
                            [ragencia],
                            ['vista_previa']
                        ],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            window.dt_cuentas_aper_ive.clear();
                            window.dt_cuentas_aper_ive.rows.add(response.data);
                            window.dt_cuentas_aper_ive.draw();
                        } else {
                            $('#tb_cuentas_aper_ive tbody').html('<tr><td colspan="8" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_cuentas_aper_ive tbody').html('<tr><td colspan="8" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 5: PERSONAS EXPUESTAS POLÍTICAMENTE (PEP) IVE ====================
    case 'pep_ive':
        $mediumPermissionId = 16; // Permiso de ver reportes a nivel de agencia
        $highPermissionId = 17;   // Permiso de ver reportes nivel general [Todas las agencias]
        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?,?) AND id_usuario=? AND estado=1", [$mediumPermissionId, $highPermissionId, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $condicion = ($accessHandler->isHigh()) ? "" : "id_agencia=?";
            $parametros = ($accessHandler->isHigh()) ? [] :  [$idagencia];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia', 'cod_agenc'], $condicion, $parametros);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No existen agencias por revisar");
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
    ?>
        <div class="text text-center">REPORTE IVE - PERSONAS EXPUESTAS POLÍTICAMENTE (PEP)</div>
        <input type="hidden" id="condi" value="pep_ive">
        <input type="hidden" id="file" value="view002">

        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php
                return;
            }  ?>
            <div class="card-header">Filtros de Generación</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <label><strong>Tipo de Fecha</strong></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha5" id="corte5" value="corte" checked>
                                            <label for="corte5" class="form-check-label">Fecha de Corte</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha5" id="rango5" value="rango">
                                            <label for="rango5" class="form-check-label">Rango de Fechas</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12" id="divCorte5">
                                        <label for="ffin">Fecha de Corte</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-sm-12" id="divRango5a" style="display:none;">
                                        <label for="finicio">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="finicio" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-sm-12 mt-2" id="divRango5b" style="display:none;">
                                        <label for="ffin2">Fecha Fin</label>
                                        <input type="date" class="form-control" id="ffin2" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi5" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi5" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi5" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi5" class="form-check-label">Por Agencia</label>
                                            <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <button type="button" class="btn btn-outline-primary" onclick="cargarDatosPEP()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="exportarPEP('xlsx')">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="exportarPEP('pdf')">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Nombre, DPI, Tipo producto, Número, Tipo Cuenta/Crédito, Fecha apertura, Monto apertura, Saldo, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_pep_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>DPI</th>
                                <th>Tipo Producto</th>
                                <th>Número</th>
                                <th>Tipo Cuenta/Crédito</th>
                                <th>Fecha Apertura</th>
                                <th>Monto Apertura</th>
                                <th>Saldo</th>
                                <th>Agencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                window.dt_pep_ive = $('#tb_pep_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "nombre" },
                        { "data": "dpi" },
                        { "data": "tipo_producto" },
                        { "data": "numero_producto" },
                        { "data": "nombre_tipo_cuenta" },
                        { "data": "fecha_apertura" },
                        { 
                            "data": "monto_apertura",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { 
                            "data": "saldo",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "agencia" }
                    ]
                });

                $('input[name="ragencia"]').change(function() {
                    if ($(this).val() === 'allofi') {
                        $('#codofi').prop('disabled', true);
                    } else {
                        $('#codofi').prop('disabled', false);
                    }
                });

                $('input[name="tipofecha5"]').change(function() {
                    if ($(this).val() === 'rango') {
                        $('#divCorte5').hide();
                        $('#divRango5a, #divRango5b').show();
                    } else {
                        $('#divCorte5').show();
                        $('#divRango5a, #divRango5b').hide();
                    }
                });

                // Cargar datos automáticamente al abrir
                cargarDatosPEP();
            });

            // Función para cargar datos PEP
            function cargarDatosPEP() {
                var tipofecha = $('input[name="tipofecha5"]:checked').val();
                var fechas = [];
                
                if (tipofecha === 'corte') {
                    var ffin = $('#ffin').val();
                    fechas = [ffin, '', ''];
                } else {
                    var finicio = $('#finicio').val();
                    var ffin2 = $('#ffin2').val();
                    fechas = ['', finicio, ffin2];
                }

                var ragencia = $('input[name="ragencia"]:checked').val();
                var codofi = $('#codofi').val();

                $.ajax({
                    url: 'reportes/repo_ive_05.php',
                    type: 'POST',
                    data: {
                        datosval: [fechas, [codofi], [ragencia, tipofecha], ['vista_previa']],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            window.dt_pep_ive.clear();
                            window.dt_pep_ive.rows.add(response.data);
                            window.dt_pep_ive.draw();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error cargando PEP:', error);
                    }
                });
            }

            // Función para exportar PEP
            function exportarPEP(formato) {
                var tipofecha = $('input[name="tipofecha5"]:checked').val();
                var fechas = [];
                
                if (tipofecha === 'corte') {
                    var ffin = $('#ffin').val();
                    if (!ffin) {
                        Swal.fire('Error', 'Debe seleccionar una fecha de corte', 'error');
                        return;
                    }
                    fechas = [ffin, '', ''];
                } else {
                    var finicio = $('#finicio').val();
                    var ffin2 = $('#ffin2').val();
                    if (!finicio || !ffin2) {
                        Swal.fire('Error', 'Debe seleccionar el rango de fechas completo', 'error');
                        return;
                    }
                    fechas = ['', finicio, ffin2];
                }

                var ragencia = $('input[name="ragencia"]:checked').val();
                var codofi = $('#codofi').val();

                if (ragencia === 'anyofi' && !codofi) {
                    Swal.fire('Error', 'Debe seleccionar una agencia', 'error');
                    return;
                }

                Swal.fire({
                    title: 'Generando ' + formato.toUpperCase() + '...',
                    html: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: 'reportes/repo_ive_05.php',
                    type: 'POST',
                    data: {
                        datosval: [fechas, [codofi], [ragencia, tipofecha], ['reporte_pep']],
                        tipo: formato
                    },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.status === 1) {
                            if (formato === 'pdf') {
                                // Abrir PDF en nueva ventana
                                var ventana = window.open();
                                ventana.document.write("<object data='" + response.data + "' type='application/pdf' width='100%' height='100%'></object>");
                            } else {
                                // Descargar Excel
                                var $a = $("<a href='" + response.data + "' download='" + response.namefile + ".xlsx'>");
                                $("body").append($a);
                                $a[0].click();
                                $a.remove();
                            }
                            Swal.fire('Éxito', response.mensaje, 'success');
                        } else {
                            Swal.fire('Error', response.mensaje, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        console.error('Error:', error, xhr.responseText);
                        Swal.fire('Error', 'Error al generar reporte: ' + error, 'error');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 6: CONTRATISTAS O PROVEEDORES DEL ESTADO (CPE) IVE ====================
    case 'cpe_ive':
        $mediumPermissionId = 16; // Permiso de ver reportes a nivel de agencia
        $highPermissionId = 17;   // Permiso de ver reportes nivel general [Todas las agencias]
        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?,?) AND id_usuario=? AND estado=1", [$mediumPermissionId, $highPermissionId, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $condicion = ($accessHandler->isHigh()) ? "" : "id_agencia=?";
            $parametros = ($accessHandler->isHigh()) ? [] :  [$idagencia];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia', 'cod_agenc'], $condicion, $parametros);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No existen agencias por revisar");
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
    ?>
        <div class="text text-center">REPORTE IVE - CONTRATISTAS O PROVEEDORES DEL ESTADO (CPE)</div>
        <input type="hidden" id="condi" value="cpe_ive">
        <input type="hidden" id="file" value="view002">

        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php
                return;
            }  ?>
            <div class="card-header">Filtros de Generación</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <label><strong>Tipo de Fecha</strong></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha6" id="corte6" value="corte" checked>
                                            <label for="corte6" class="form-check-label">Fecha de Corte</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha6" id="rango6" value="rango">
                                            <label for="rango6" class="form-check-label">Rango de Fechas</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12" id="divCorte6">
                                        <label for="ffin">Fecha de Corte</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-sm-12" id="divRango6a" style="display:none;">
                                        <label for="finicio">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="finicio" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-sm-12 mt-2" id="divRango6b" style="display:none;">
                                        <label for="ffin2">Fecha Fin</label>
                                        <input type="date" class="form-control" id="ffin2" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi6" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi6" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi6" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi6" class="form-check-label">Por Agencia</label>
                                            <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <button type="button" class="btn btn-outline-primary" onclick="cargarDatosCPE()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="exportarCPE('xlsx')">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="exportarCPE('pdf')">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Nombre, DPI, Tipo producto, Número, Nombre cuenta/crédito, Fecha/monto apertura, Saldo, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_cpe_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>DPI</th>
                                <th>Tipo Producto</th>
                                <th>Número Producto</th>
                                <th>Nombre Cuenta/Crédito</th>
                                <th>Fecha Apertura</th>
                                <th>Monto Apertura</th>
                                <th>Saldo</th>
                                <th>Agencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Datos se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                window.dt_cpe_ive = $('#tb_cpe_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "nombre" },
                        { "data": "dpi" },
                        { "data": "tipo_producto" },
                        { "data": "numero_producto" },
                        { "data": "nombre_tipo_cuenta" },
                        { "data": "fecha_apertura" },
                        { 
                            "data": "monto_apertura",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { 
                            "data": "saldo",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "agencia" }
                    ]
                });

                $('input[name="ragencia"]').change(function() {
                    if ($(this).val() === 'allofi') {
                        $('#codofi').prop('disabled', true);
                    } else {
                        $('#codofi').prop('disabled', false);
                    }
                });

                $('input[name="tipofecha6"]').change(function() {
                    if ($(this).val() === 'rango') {
                        $('#divCorte6').hide();
                        $('#divRango6a, #divRango6b').show();
                    } else {
                        $('#divCorte6').show();
                        $('#divRango6a, #divRango6b').hide();
                    }
                });

                // Cargar datos automáticamente al abrir
                cargarDatosCPE();
            });

            // Función para cargar datos CPE
            function cargarDatosCPE() {
                var tipofecha = $('input[name="tipofecha6"]:checked').val();
                var fechas = [];
                
                if (tipofecha === 'corte') {
                    var ffin = $('#ffin').val();
                    fechas = [ffin, '', ''];
                } else {
                    var finicio = $('#finicio').val();
                    var ffin2 = $('#ffin2').val();
                    fechas = ['', finicio, ffin2];
                }

                var ragencia = $('input[name="ragencia"]:checked').val();
                var codofi = $('#codofi').val();

                $.ajax({
                    url: 'reportes/repo_ive_06.php',
                    type: 'POST',
                    data: {
                        datosval: [fechas, [codofi], [ragencia, tipofecha], ['vista_previa']],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            window.dt_cpe_ive.clear();
                            window.dt_cpe_ive.rows.add(response.data);
                            window.dt_cpe_ive.draw();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error cargando CPE:', error);
                    }
                });
            }

            // Función para exportar CPE
            function exportarCPE(formato) {
                var tipofecha = $('input[name="tipofecha6"]:checked').val();
                var fechas = [];
                
                if (tipofecha === 'corte') {
                    var ffin = $('#ffin').val();
                    if (!ffin) {
                        Swal.fire('Error', 'Debe seleccionar una fecha de corte', 'error');
                        return;
                    }
                    fechas = [ffin, '', ''];
                } else {
                    var finicio = $('#finicio').val();
                    var ffin2 = $('#ffin2').val();
                    if (!finicio || !ffin2) {
                        Swal.fire('Error', 'Debe seleccionar el rango de fechas completo', 'error');
                        return;
                    }
                    fechas = ['', finicio, ffin2];
                }

                var ragencia = $('input[name="ragencia"]:checked').val();
                var codofi = $('#codofi').val();

                if (ragencia === 'anyofi' && !codofi) {
                    Swal.fire('Error', 'Debe seleccionar una agencia', 'error');
                    return;
                }

                Swal.fire({
                    title: 'Generando ' + formato.toUpperCase() + '...',
                    html: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: 'reportes/repo_ive_06.php',
                    type: 'POST',
                    data: {
                        datosval: [fechas, [codofi], [ragencia, tipofecha], ['reporte_cpe']],
                        tipo: formato
                    },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.status === 1) {
                            if (formato === 'pdf') {
                                // Abrir PDF en nueva ventana
                                var ventana = window.open();
                                ventana.document.write("<object data='" + response.data + "' type='application/pdf' width='100%' height='100%'></object>");
                            } else {
                                // Descargar Excel
                                var $a = $("<a href='" + response.data + "' download='" + response.namefile + ".xlsx'>");
                                $("body").append($a);
                                $a[0].click();
                                $a.remove();
                            }
                            Swal.fire('Éxito', response.mensaje, 'success');
                        } else {
                            Swal.fire('Error', response.mensaje, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        console.error('Error:', error, xhr.responseText);
                        Swal.fire('Error', 'Error al generar reporte: ' + error, 'error');
                    }
                });
            }
        </script>
    <?php
        break;
}
