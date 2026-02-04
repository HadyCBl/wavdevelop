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

    // ==================== REPORTE 7: CUENTAS DE CAPTACIÓN CANCELADAS IVE ====================
    case 'cuentas_canceladas_ive':
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
        <div class="text text-center">REPORTE IVE - CUENTAS DE CAPTACIÓN CANCELADAS</div>
        <input type="hidden" id="condi" value="cuentas_canceladas_ive">
        <input type="hidden" id="file" value="view003">

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
                                        <small class="text-muted">Cuentas canceladas en este periodo</small>
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
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi7" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi7" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi7" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi7" class="form-check-label">Por Agencia</label>
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
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx', 'repo_ive_07', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'pdf', 'repo_ive_07', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaCuentasCanceladas()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Tipo cuenta, Moneda, Nombre cliente, Tipo persona, Nacionalidad, Número cuenta, Fechas, Montos, Formas, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_cuentas_canc_ive" class="table table-striped table-bordered" style="width:100%; font-size:11px;">
                        <thead>
                            <tr>
                                <th>Tipo Cuenta</th>
                                <th>Moneda</th>
                                <th>Nombre Cliente</th>
                                <th>Tipo Persona</th>
                                <th>Nacionalidad</th>
                                <th>Tipo Producto</th>
                                <th>No. Cuenta</th>
                                <th>F. Apertura</th>
                                <th>Monto Apert.</th>
                                <th>Forma Apert.</th>
                                <th>F. Cancelación</th>
                                <th>Monto Cancel.</th>
                                <th>Forma Cancel.</th>
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
                window.dt_cuentas_canc_ive = $('#tb_cuentas_canc_ive').DataTable({
                    "pageLength": 10,
                    "scrollX": true,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "tipo_cuenta" },
                        { "data": "moneda" },
                        { "data": "nombre_cliente" },
                        { "data": "tipo_persona" },
                        { "data": "nacionalidad" },
                        { "data": "tipo_producto" },
                        { "data": "numero_cuenta" },
                        { "data": "fecha_apertura" },
                        { 
                            "data": "monto_apertura",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "forma_apertura" },
                        { "data": "fecha_cancelacion" },
                        { 
                            "data": "saldo_cancelacion",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "forma_cancelacion" },
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
                cargarVistaPreviaCuentasCanceladas();
            });

            // Función para cargar vista previa de cuentas canceladas
            function cargarVistaPreviaCuentasCanceladas() {
                var finicio = $('#finicio').val();
                var ffin = $('#ffin').val();
                var codofi = $('#codofi').val();
                var ragencia = $('input[name="ragencia"]:checked').val();

                // Mostrar loading en la tabla
                $('#tb_cuentas_canc_ive tbody').html('<tr><td colspan="14" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_07.php',
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
                            window.dt_cuentas_canc_ive.clear();
                            window.dt_cuentas_canc_ive.rows.add(response.data);
                            window.dt_cuentas_canc_ive.draw();
                        } else {
                            $('#tb_cuentas_canc_ive tbody').html('<tr><td colspan="14" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_cuentas_canc_ive tbody').html('<tr><td colspan="14" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 8: CRÉDITOS CONCEDIDOS IVE ====================
    case 'creditos_concedidos_ive':
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
        <div class="text text-center">REPORTE IVE - CRÉDITOS CONCEDIDOS</div>
        <input type="hidden" id="condi" value="creditos_concedidos_ive">
        <input type="hidden" id="file" value="view003">

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
                                        <small class="text-muted">Créditos concedidos en este periodo</small>
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
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi8" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi8" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi8" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi8" class="form-check-label">Por Agencia</label>
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
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx', 'repo_ive_08', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'pdf', 'repo_ive_08', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Tipo y número crédito, Nombre, Tipo persona, Fecha concesión, Monto, Garantía, Forma desembolso, Vencimiento, Saldo, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_creditos_conc_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Tipo Crédito</th>
                                <th>Número Crédito</th>
                                <th>Nombre Cliente</th>
                                <th>Tipo Persona</th>
                                <th>Fecha Concesión</th>
                                <th>Monto Concedido</th>
                                <th>Tipo Garantía</th>
                                <th>Forma Desembolso</th>
                                <th>Fecha Vencimiento</th>
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
                window.dt_creditos_conc_ive = $('#tb_creditos_conc_ive').DataTable({
                    "pageLength": 10,
                    "scrollX": true,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "tipo_credito" },
                        { "data": "numero_credito" },
                        { "data": "nombre_cliente" },
                        { "data": "tipo_persona" },
                        { "data": "fecha_concesion" },
                        { 
                            "data": "monto_concedido",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "tipo_garantia" },
                        { "data": "forma_desembolso" },
                        { "data": "fecha_vencimiento" },
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
                    // Recargar datos cuando cambie la agencia
                    cargarVistaPreviaCreditosConcedidos();
                });

                // Recargar datos cuando cambien las fechas
                $('#finicio, #ffin').change(function() {
                    cargarVistaPreviaCreditosConcedidos();
                });

                $('#codofi').change(function() {
                    cargarVistaPreviaCreditosConcedidos();
                });

                // Cargar vista previa al iniciar
                cargarVistaPreviaCreditosConcedidos();
            });

            // Función para cargar vista previa de créditos concedidos
            function cargarVistaPreviaCreditosConcedidos() {
                var finicio = $('#finicio').val();
                var ffin = $('#ffin').val();
                var codofi = $('#codofi').val();
                var ragencia = $('input[name="ragencia"]:checked').val();

                // Validar fechas
                if (!finicio || !ffin) {
                    return;
                }

                // Mostrar loading en la tabla
                $('#tb_creditos_conc_ive tbody').html('<tr><td colspan="11" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_08.php',
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
                            window.dt_creditos_conc_ive.clear();
                            window.dt_creditos_conc_ive.rows.add(response.data);
                            window.dt_creditos_conc_ive.draw();
                        } else {
                            $('#tb_creditos_conc_ive tbody').html('<tr><td colspan="11" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_creditos_conc_ive tbody').html('<tr><td colspan="11" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 9: CRÉDITOS BACK TO BACK IVE ====================
    case 'creditos_backtoback_ive':
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
        <div class="text text-center">REPORTE IVE - CRÉDITOS BACK TO BACK (GARANTÍA DEPOSITARIA)</div>
        <input type="hidden" id="condi" value="creditos_backtoback_ive">
        <input type="hidden" id="file" value="view003">

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
                                        <small class="text-muted">Créditos back to back en este periodo</small>
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
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi9" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi9" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi9" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi9" class="form-check-label">Por Agencia</label>
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
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx', 'repo_ive_09', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'pdf', 'repo_ive_09', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Créditos ejecutados mediante garantía depositaria en el periodo seleccionado</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_creditos_btb_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Número Crédito</th>
                                <th>Nombre Cliente</th>
                                <th>Tipo Garantía</th>
                                <th>Número Cuenta Garantía</th>
                                <th>Monto Garantía</th>
                                <th>Fecha Concesión</th>
                                <th>Monto Crédito</th>
                                <th>Saldo Crédito</th>
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
                window.dt_creditos_btb_ive = $('#tb_creditos_btb_ive').DataTable({
                    "pageLength": 10,
                    "scrollX": true,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "numero_credito" },
                        { "data": "nombre_cliente" },
                        { "data": "tipo_garantia_depositaria" },
                        { "data": "numero_cuenta_garantia" },
                        { 
                            "data": "monto_garantia",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "fecha_concesion" },
                        { 
                            "data": "monto_credito",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { 
                            "data": "saldo_credito",
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
                    // Recargar datos cuando cambie la agencia
                    cargarVistaPreviaCreditosBackToBack();
                });

                // Recargar datos cuando cambien las fechas
                $('#finicio, #ffin').change(function() {
                    cargarVistaPreviaCreditosBackToBack();
                });

                $('#codofi').change(function() {
                    cargarVistaPreviaCreditosBackToBack();
                });

                // Cargar vista previa al iniciar
                cargarVistaPreviaCreditosBackToBack();
            });

            // Función para cargar vista previa de créditos back to back
            function cargarVistaPreviaCreditosBackToBack() {
                var finicio = $('#finicio').val();
                var ffin = $('#ffin').val();
                var codofi = $('#codofi').val();
                var ragencia = $('input[name="ragencia"]:checked').val();

                // Validar fechas
                if (!finicio || !ffin) {
                    return;
                }

                // Mostrar loading en la tabla
                $('#tb_creditos_btb_ive tbody').html('<tr><td colspan="9" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_09.php',
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
                            window.dt_creditos_btb_ive.clear();
                            window.dt_creditos_btb_ive.rows.add(response.data);
                            window.dt_creditos_btb_ive.draw();
                        } else {
                            $('#tb_creditos_btb_ive tbody').html('<tr><td colspan="9" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_creditos_btb_ive tbody').html('<tr><td colspan="9" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 10: CRÉDITOS CANCELADOS ANTICIPADAMENTE IVE ====================
    case 'creditos_cancelados_anticipadamente_ive':
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
        <div class="text text-center">REPORTE IVE - CRÉDITOS CANCELADOS ANTICIPADAMENTE (75%)</div>
        <input type="hidden" id="condi" value="creditos_cancelados_anticipadamente_ive">
        <input type="hidden" id="file" value="view003">

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
                                        <small class="text-muted">Créditos cancelados anticipadamente (≥75%)</small>
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
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi10" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi10" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi10" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi10" class="form-check-label">Por Agencia</label>
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
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaCreditosCancelados()">
                            <i class="fa-solid fa-eye"></i> Vista Previa
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx', 'repo_ive_10', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'pdf', 'repo_ive_10', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Número, Tipo, Nombre, Tipo persona, Fechas concesión/vencimiento/cancelación, Montos, Forma pago, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_creditos_canc_ant_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Número Crédito</th>
                                <th>Tipo Crédito</th>
                                <th>Nombre Cliente</th>
                                <th>Tipo Persona</th>
                                <th>Fecha Concesión</th>
                                <th>Fecha Vencimiento</th>
                                <th>Fecha Cancelación</th>
                                <th>Monto Concedido</th>
                                <th>Monto Cancelado</th>
                                <th>% Cancelado</th>
                                <th>Forma Pago</th>
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
            var tablaCreditosCancelados;
            
            $(document).ready(function() {
                tablaCreditosCancelados = $('#tb_creditos_canc_ant_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "numero_credito" },
                        { "data": "tipo_credito" },
                        { "data": "nombre_cliente" },
                        { "data": "tipo_persona" },
                        { "data": "fecha_concesion" },
                        { "data": "fecha_vencimiento" },
                        { "data": "fecha_cancelacion" },
                        { "data": "monto_concedido", "render": function(data) { return parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2}); } },
                        { "data": "monto_cancelado", "render": function(data) { return parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2}); } },
                        { "data": "porcentaje_cancelado", "render": function(data) { return data + '%'; } },
                        { "data": "forma_pago" },
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
            });
            
            function cargarVistaPreviaCreditosCancelados() {
                var finicio = $('#finicio').val();
                var ffin = $('#ffin').val();
                var codofi = $('#codofi').val();
                var ragencia = $('input[name="ragencia"]:checked').val();
                
                // Validar fechas
                if (!finicio || !ffin) {
                    return;
                }
                
                // Mostrar loading en la tabla
                $('#tb_creditos_canc_ive tbody').html('<tr><td colspan="12" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');
                
                $.ajax({
                    url: 'reportes/repo_ive_10.php',
                    type: 'POST',
                    data: {
                        datosval: [[finicio, ffin], [codofi], [ragencia], ['vista_previa']],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            tablaCreditosCancelados.clear();
                            tablaCreditosCancelados.rows.add(response.data);
                            tablaCreditosCancelados.draw();
                        } else {
                            $('#tb_creditos_canc_ive tbody').html('<tr><td colspan="12" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_creditos_canc_ive tbody').html('<tr><td colspan="12" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 11: DEPÓSITOS BANCARIOS IVE ====================
    case 'depositos_bancarios_ive':
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
        <div class="text text-center">REPORTE IVE - DEPÓSITOS PROVENIENTES DE CUENTAS BANCARIAS</div>
        <input type="hidden" id="condi" value="depositos_bancarios_ive">
        <input type="hidden" id="file" value="view003">

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
                                        <small class="text-muted">Depósitos bancarios en este periodo</small>
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
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi11" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi11" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi11" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi11" class="form-check-label">Por Agencia</label>
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
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaDepositosBancarios()">
                            <i class="fa-solid fa-eye"></i> Vista Previa
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'xlsx', 'repo_ive_11', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['finicio','ffin'], ['codofi'], ['ragencia'], []], 'pdf', 'repo_ive_11', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Fecha transacción, Banco, Número cuenta banco, Boleta/referencia, Cuenta cliente, Código y nombre cliente, Nivel riesgo, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_depositos_banc_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Fecha Transacción</th>
                                <th>Banco Origen</th>
                                <th>Número Cuenta Banco</th>
                                <th>Boleta/Referencia</th>
                                <th>Cuenta Cliente</th>
                                <th>Tipo Cuenta</th>
                                <th>Código Cliente</th>
                                <th>Nombre Cliente</th>
                                <th>Monto</th>
                                <th>Nivel Riesgo</th>
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
            var tablaDepositosBancarios;
            
            $(document).ready(function() {
                tablaDepositosBancarios = $('#tb_depositos_banc_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "fecha_transaccion" },
                        { "data": "nombre_banco" },
                        { "data": "numero_cuenta_banco" },
                        { "data": "boleta_referencia" },
                        { "data": "cuenta_cliente" },
                        { "data": "tipo_cuenta" },
                        { "data": "codigo_cliente" },
                        { "data": "nombre_cliente" },
                        { "data": "monto_deposito", "render": function(data) { return parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2}); } },
                        { "data": "nivel_riesgo" },
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
            });
            
            function cargarVistaPreviaDepositosBancarios() {
                var finicio = $('#finicio').val();
                var ffin = $('#ffin').val();
                var codofi = $('#codofi').val();
                var ragencia = $('input[name="ragencia"]:checked').val();
                
                // Validar fechas
                if (!finicio || !ffin) {
                    return;
                }
                
                // Mostrar loading en la tabla
                $('#tb_depositos_banc_ive tbody').html('<tr><td colspan="11" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');
                
                $.ajax({
                    url: 'reportes/repo_ive_11.php',
                    type: 'POST',
                    data: {
                        datosval: [[finicio, ffin], [codofi], [ragencia], ['vista_previa']],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            tablaDepositosBancarios.clear();
                            tablaDepositosBancarios.rows.add(response.data);
                            tablaDepositosBancarios.draw();
                        } else {
                            $('#tb_depositos_banc_ive tbody').html('<tr><td colspan="11" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_depositos_banc_ive tbody').html('<tr><td colspan="11" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 12: OPERACIONES EN EFECTIVO >= $10,000 IVE ====================
    case 'operaciones_efectivo_ive':
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
        <div class="text text-center">REPORTE IVE - OPERACIONES EN EFECTIVO >= $10,000</div>
        <input type="hidden" id="condi" value="operaciones_efectivo_ive">
        <input type="hidden" id="file" value="view003">

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
                                        <small class="text-muted">Operaciones >= $10,000 o equivalente</small>
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
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi12" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi12" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi12" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi12" class="form-check-label">Por Agencia</label>
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
                        <button type="button" class="btn btn-outline-primary" onclick="cargarVistaPreviaOperacionesEfectivo()">
                            <i class="fa-solid fa-sync"></i> Actualizar Vista
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['ffin', 'finicio', 'ffin'], ['codofi'], ['ragencia'], []], 'xlsx', 'repo_ive_12', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['ffin', 'finicio', 'ffin'], ['codofi'], ['ragencia'], []], 'pdf', 'repo_ive_12', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Operaciones en efectivo >= $10,000 o equivalente en moneda nacional, por mes y agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_operaciones_efec_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Fecha Operación</th>
                                <th>Tipo Operación</th>
                                <th>Código Cliente</th>
                                <th>Nombre Cliente</th>
                                <th>Tipo Producto</th>
                                <th>Número Producto</th>
                                <th>Monto Efectivo USD</th>
                                <th>Monto Efectivo GTQ</th>
                                <th>Total USD Equivalente</th>
                                <th>Nivel Riesgo</th>
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
                window.dt_operaciones_efec_ive = $('#tb_operaciones_efec_ive').DataTable({
                    "pageLength": 10,
                    "scrollX": true,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "columns": [
                        { "data": "fecha_operacion" },
                        { "data": "tipo_operacion" },
                        { "data": "codigo_cliente" },
                        { "data": "nombre_cliente" },
                        { "data": "tipo_producto" },
                        { "data": "numero_producto" },
                        { 
                            "data": "monto_usd",
                            "render": function(data, type, row) {
                                return '$ ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { 
                            "data": "monto_gtq",
                            "render": function(data, type, row) {
                                return 'Q ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { 
                            "data": "total_usd_equivalente",
                            "render": function(data, type, row) {
                                return '$ ' + parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        },
                        { "data": "nivel_riesgo" },
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
                cargarVistaPreviaOperacionesEfectivo();
            });

            // Función para cargar vista previa de operaciones en efectivo
            function cargarVistaPreviaOperacionesEfectivo() {
                var finicio = $('#finicio').val();
                var ffin = $('#ffin').val();
                var codofi = $('#codofi').val();
                var ragencia = $('input[name="ragencia"]:checked').val();

                // Mostrar loading en la tabla
                $('#tb_operaciones_efec_ive tbody').html('<tr><td colspan="11" class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando datos...</td></tr>');

                $.ajax({
                    url: 'reportes/repo_ive_12.php',
                    type: 'POST',
                    data: {
                        datosval: [
                            [ffin, finicio, ffin],
                            [codofi],
                            [ragencia],
                            ['vista_previa']
                        ],
                        tipo: 'preview'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 1) {
                            window.dt_operaciones_efec_ive.clear();
                            window.dt_operaciones_efec_ive.rows.add(response.data);
                            window.dt_operaciones_efec_ive.draw();
                        } else {
                            $('#tb_operaciones_efec_ive tbody').html('<tr><td colspan="11" class="text-center text-danger">' + response.mensaje + '</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', xhr.responseText);
                        $('#tb_operaciones_efec_ive tbody').html('<tr><td colspan="11" class="text-center text-danger">Error al cargar datos</td></tr>');
                    }
                });
            }
        </script>
    <?php
        break;

    // ==================== REPORTE 13: REMESAS >= $1,000 IVE ====================
    case 'remesas_ive':
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
        <div class="text text-center">REPORTE IVE - REMESAS ENVIADAS Y RECIBIDAS >= $1,000</div>
        <input type="hidden" id="condi" value="remesas_ive">
        <input type="hidden" id="file" value="view003">

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
                                            <input class="form-check-input" type="radio" name="tipofecha13" id="corte13" value="corte" checked>
                                            <label for="corte13" class="form-check-label">Fecha de Corte</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipofecha13" id="rango13" value="rango">
                                            <label for="rango13" class="form-check-label">Rango de Fechas</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-sm-12" id="divCorte13">
                                        <label for="ffin">Fecha de Corte</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-sm-12" id="divRango13a" style="display:none;">
                                        <label for="finicio">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="finicio" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-sm-12 mt-2" id="divRango13b" style="display:none;">
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
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi13" value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi13" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi13" value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi13" class="form-check-label">Por Agencia</label>
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
                        <button type="button" class="btn btn-outline-success" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha13'], []], 'xlsx', 'Repo_Remesas_IVE', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="reportes([['ffin', 'finicio', 'ffin2'], ['codofi'], ['ragencia', 'tipofecha13'], []], 'pdf', 'Repo_Remesas_IVE', 0)">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col">
                        <small class="text-info"><strong>Campos:</strong> Fecha, Tipo operación, Montos, Ordenante, Beneficiario, País origen/destino, Intermediario, Agencia</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vista Previa de Datos -->
        <div class="card mt-3">
            <div class="card-header">Vista Previa de Datos del Reporte</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tb_remesas_ive" class="table table-striped table-bordered" style="width:100%; font-size:12px;">
                        <thead>
                            <tr>
                                <th>Fecha Transacción</th>
                                <th>Tipo Operación</th>
                                <th>Monto USD</th>
                                <th>Monto GTQ</th>
                                <th>Ordenante Nombre</th>
                                <th>Ordenante DPI/Pasaporte</th>
                                <th>Ordenante País</th>
                                <th>Beneficiario Nombre</th>
                                <th>Beneficiario DPI/Pasaporte</th>
                                <th>Beneficiario País</th>
                                <th>Intermediario</th>
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
                $('#tb_remesas_ive').DataTable({
                    "pageLength": 10,
                    "language": {
                        "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    }
                });

                $('input[name="ragencia"]').change(function() {
                    if ($(this).val() === 'allofi') {
                        $('#codofi').prop('disabled', true);
                    } else {
                        $('#codofi').prop('disabled', false);
                    }
                });

                $('input[name="tipofecha13"]').change(function() {
                    if ($(this).val() === 'rango') {
                        $('#divCorte13').hide();
                        $('#divRango13a, #divRango13b').show();
                    } else {
                        $('#divCorte13').show();
                        $('#divRango13a, #divRango13b').hide();
                    }
                });
            });
        </script>
<?php
        break;
}
