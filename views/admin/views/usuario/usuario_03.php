<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
    return;
}
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

include __DIR__ . '/../../../../includes/Config/database.php';
include __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

//+++++++
// session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
// date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];

switch ($condi) {
    case 'admin_agencia':
        $codusu = $_SESSION['id'];
        $id = $_POST["xtra"];
        ?>
        <!-- CONFIGURACION PARA RECARGAR LA PAGINA -->
        <input type="text" id="file" value="usuario_03" style="display: none;">
        <input type="text" id="condi" value="admin_agencia" style="display: none;">

        <!-- ini -->
        <div class="card mb-3" style="width: 100%;">
            <div class="card-header">
                Parametrización de agencias
            </div>
            <div class="card-body">

                <!-- IMPRESION DE LA TABLA -->
                <div id="tb_parametrizacion_agencia"></div>
            </div>
        </div>

        <!-- ini js -->
        <script>
            $(document).ready(function () {
                inyecCod('#tb_parametrizacion_agencia', 'tbParametrizacionAgencia');
            });
            var datoID = 0;

            function capID(idEle) {
                datoID = $("#" + idEle).text();
            }

            function datos(datos) {
                datos.push(datoID);
                obtiene([], [], [], 'parametrizaAgencia', '', datos);
            }

            function cerrarModal(idEle) {
                $("#" + idEle).modal("hide");
            }
        </script>
        <?php
        include_once "../../../../src/cris_modales/mdls_nomenclatura1.php";
        break; //FIN DE CASE

    case 'create_tipos_documentos':
        $id = $_POST["xtra"];

        $showmensaje = false;
        try {

            $database->openConnection();
            $existentes = $database->selectColumns('tb_documentos_transacciones', ['id', 'id_modulo', 'tipo', 'nombre', 'id_cuenta_contable', 'tipo_dato'], 'estado=1');

            $ctbNomenclatura = $database->getAllResults("SELECT id, ccodcta, cdescrip, tipo FROM ctb_nomenclatura WHERE estado=1 ORDER BY ccodcta");
            if (empty($ctbNomenclatura)) {
                $showmensaje = true;
                throw new Exception("No existen cuentas contables activas, por favor registre una cuenta contable antes de crear un tipo de documento");
            }

            if ($id == "0") {
                $showmensaje = true;
                throw new Exception("Creando nuevo tipo de documento");
            }

            $tipoCargado = $database->selectColumns('tb_documentos_transacciones', ['id', 'id_modulo', 'tipo', 'nombre', 'id_cuenta_contable', 'tipo_dato'], 'id=?', [$id]);
            if (empty($tipoCargado)) {
                $showmensaje = true;
                throw new Exception("Tipo de documento no encontrado");
            }
            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

        $modulos = [
            1 => 'Ahorros',
            2 => 'Aportaciones',
            3 => 'Créditos',
            4 => 'Otros Movimientos',
        ];

        $dataTypes = [
            1 => 'Normal',
            2 => 'Cheque',
            3 => 'Transferencia',
        ];
        ?>
        <input type="text" id="file" value="usuario_03" style="display: none;">
        <input type="text" id="condi" value="create_tipos_documentos" style="display: none;">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="bi bi-journal-text me-2"></i>
                <span class="fw-bold fs-5">TIPOS DE DOCUMENTOS DE TRANSACCIONES</span>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                        <strong>¡Atención!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row justify-content-center mb-4">
                    <div class="col-lg-11">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light">
                                <span class="fw-semibold text-secondary"><i
                                        class="bi bi-pencil-square me-2"></i><?php if (empty($tipoCargado)): ?>Registrar nuevo
                                        tipo de documento<?php else: ?>Editar tipo de documento<?php endif; ?></span>
                            </div>
                            <div class="card-body">
                                <form id="formNomenclatura" autocomplete="off">
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-6">
                                            <label for="id_modulo" class="form-label">Módulo</label>
                                            <select class="form-select form-select-sm" id="id_modulo" name="id_modulo" required>
                                                <option value="" disabled selected>Seleccione</option>
                                                <?php foreach ($modulos as $key => $value): ?>
                                                    <?php
                                                    $selected = (!empty($tipoCargado) && $tipoCargado[0]['id_modulo'] == $key) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?= $key; ?>" <?= $selected; ?>><?= htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="tipo_dato" name="tipo_dato"
                                                    value="2" <?= (!empty($tipoCargado) && $tipoCargado[0]['tipo_dato'] == 2) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="tipo_dato">Es cheque</label>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="tipo" class="form-label">Tipo</label>
                                            <select class="form-select form-select-sm" id="tipo" name="tipo" required>
                                                <option value="1" <?= (!empty($tipoCargado) && $tipoCargado[0]['tipo'] == 1) ? 'selected' : ''; ?>>Egreso</option>
                                                <option value="2" <?= (!empty($tipoCargado) && $tipoCargado[0]['tipo'] == 2) ? 'selected' : ''; ?>>Ingreso</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-12">
                                            <label for="nombre" class="form-label">Nombre</label>
                                            <input type="text" class="form-control form-control-sm" id="nombre" name="nombre"
                                                maxlength="100" required placeholder="Nombre"
                                                value="<?= !empty($tipoCargado) ? htmlspecialchars($tipoCargado[0]['nombre']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-12">
                                            <label for="id_cuenta_contable" class="form-label">Cuenta Contable</label>
                                            <select class="form-select select2" name="id_ctb_nomenclatura[]"
                                                id="id_cuenta_contable" required>
                                                <option value="">Seleccione...</option>
                                                <?php
                                                $currentGroup = null;
                                                foreach ($ctbNomenclatura as $key => $nomenclatura):
                                                    $indentationLevel = strlen($nomenclatura['ccodcta']) / 2;
                                                    $indentation = str_repeat('&nbsp;', intval($indentationLevel));

                                                    if ($nomenclatura['tipo'] === 'R'):
                                                        if ($currentGroup !== null) {
                                                            echo '</optgroup>';
                                                        }
                                                        $currentGroup = $nomenclatura['cdescrip'];
                                                        echo '<optgroup label="' . $indentation . $currentGroup . '">';
                                                    else:
                                                        $selected = ($nomenclatura['id'] == $tipoCargado[0]['id_cuenta_contable']) ? 'selected' : '';
                                                        echo '<option ' . $selected . ' value="' . $nomenclatura['id'] . '">' . $indentation . $nomenclatura['ccodcta'] . ' - ' . $nomenclatura['cdescrip'] . '</option>';
                                                    endif;
                                                endforeach;
                                                if ($currentGroup !== null) {
                                                    echo '</optgroup>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <?php echo $csrf->getTokenField(); ?>
                                    <div class="row g-3 mb-2 justify-content-center">
                                        <div class="col-md-6 d-flex justify-content-center gap-2">
                                            <?php if (empty($tipoCargado)): ?>
                                                <button type="button" class="btn btn-success mt-2"
                                                    onclick="obtiene(['<?= $csrf->getTokenName() ?>','nombre'], ['id_modulo','tipo','id_cuenta_contable'], [], 'create_doc_transacciones', '0', [$('#tipo_dato').is(':checked')?2:1])">
                                                    <i class="bi bi-save me-1"></i> Guardar
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-warning mt-2"
                                                    onclick="obtiene(['<?= $csrf->getTokenName() ?>','nombre'], ['id_modulo','tipo','id_cuenta_contable'], [], 'update_doc_transacciones', '0', ['<?= $id ?>',$('#tipo_dato').is(':checked')?2:1], 'NULL', 'Está seguro que desea actualizar este registro?')">
                                                    <i class="bi bi-pencil me-1"></i> Actualizar
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-secondary mt-2"
                                                onclick="printdiv2('#cuadro','0')">
                                                <i class="bi bi-x-circle me-1"></i> Cancelar
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <div id="tablaNomenclatura" class="table-responsive">
                    <table class='table table-bordered table-striped' id="tb_tipos_documentos">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Módulo</th>
                                <th>Tipo</th>
                                <th>Nombre</th>
                                <th>Dato</th>
                                <th>Cuenta Contable</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id='bodyNomenclatura'>
                            <?php if (!empty($existentes)): ?>
                                <?php foreach ($existentes as $key => $item): ?>
                                    <tr>
                                        <td><?= ($key + 1) ?></td>
                                        <td><?= isset($modulos[$item['id_modulo']]) ? htmlspecialchars($modulos[$item['id_modulo']]) : '-'; ?>
                                        </td>
                                        <td><?= $item['tipo'] == 1 ? 'Egreso' : ($item['tipo'] == 2 ? 'Ingreso' : '-'); ?></td>
                                        <td><?= htmlspecialchars($item['nombre']); ?></td>
                                        <td>
                                            <?php
                                            echo $dataTypes[$item['tipo_dato']] ?? '-';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $cuenta = array_filter($ctbNomenclatura, function ($c) use ($item) {
                                                return $c['id'] == $item['id_cuenta_contable'];
                                            });
                                            $cuenta = reset($cuenta);
                                            echo $cuenta ? htmlspecialchars($cuenta['ccodcta'] . ' - ' . $cuenta['cdescrip']) : '-';
                                            ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary"
                                                onclick="printdiv2('#cuadro',<?= $item['id']; ?>)">Editar</button>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'delete_doc_transacciones', '0', ['<?= $item['id']; ?>'],'NULL','Está seguro que desea eliminar este registro?')">Eliminar</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function () {
                $('.select2').select2({
                    theme: 'classic',
                    width: '100%',
                    placeholder: "Seleccione una opción",
                    allowClear: true,
                });
                inicializarValidacionAutomaticaGeneric('#formNomenclatura');
                convert_table_to_datatable('tb_tipos_documentos');
            });
        </script>
        <?php
        break;

    case 'create_actividades_economicas':
        $id = $_POST["xtra"];

        $showmensaje = false;
        try {

            $database->openConnection();
            $sat_clases = $database->selectColumns('sat_actividades_clases', ['id', 'left(descripcion, 100) as descripcion']);
        
            $sat_actividades = $database->getAllResults("SELECT * FROM sat_actividades ORDER BY id_clase");
            if (empty($sat_actividades)) {
                $showmensaje = true;
                throw new Exception("Registre una actividad económica antes de crear una clase");
            }

            if ($id == "0") {
                $showmensaje = true;
                throw new Exception("Creando nueva clase de actividad económica");
            }

            $tipoCargado = $database->selectColumns('sat_actividades_clases', ['id', 'descripcion'], 'id=?', [$id]);
            if (empty($tipoCargado)) {
                $showmensaje = true;
                throw new Exception("Clase de actividad económica no encontrada");
            }
            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
        ?>
        <input type="text" id="file" value="usuario_03" style="display: none;">
        <input type="text" id="condi" value="create_tipos_documentos" style="display: none;">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white d-flex align-items-center">
                <i class="bi bi-journal-text me-2"></i>
                <span class="fw-bold fs-5">Gestión de Actividades Económicas SAT</span>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3 justify-content-center">
                    <div class="col-md-4 flex-grow-0">
                        <div class="input-group input-group">
                            <select class="form-select form-select-sm" aria-label="Actividad económica" id = "clase_actividad">
                                <option selected>Seleccione Clase</option>
                                <?php
                                foreach ($sat_clases as $clase) {
                                    echo '<option value="' . $clase['id'] . '">' . $clase['descripcion'] . '</option>';
                                }
                                ?>
                            </select>
                            <button class="btn btn-primary" type="button">
                                <i class="fa-solid fa-circle-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 flex-grow-0">
                        <div class="input-group input-group">
                            <select class="form-select form-select-sm " aria-label="Clase de actividad">
                                <option selected>Seleccione una Actividad</option>
                                <option value="1">One</option>
                                <option value="2">Two</option>
                                <option value="3">Three</option>
                            </select>
                            <button class="btn btn-primary" data-bs-toggle="modal" type="button" data-bs-target="#exampleModal">
                                <i class="fa-solid fa-circle-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">New message</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form>
                                        <div class="mb-3">
                                            <label for="recipient-name" class="col-form-label">Recipient:</label>
                                            <input type="text" class="form-control" id="recipient-name">
                                        </div>
                                        <div class="mb-3">
                                            <label for="message-text" class="col-form-label">Message:</label>
                                            <textarea class="form-control" id="message-text"></textarea>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary">Send message</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Clase de Actividad Económica</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($existentes)): ?>
                            <?php foreach ($existentes as $key => $item): ?>
                                <tr>
                                    <td><?= ($key + 1) ?></td>
                                    <td><?= htmlspecialchars($item['descripcion']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary"
                                            onclick="printdiv2('#cuadro',<?= $item['id']; ?>)">Editar</button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                            onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'delete_actividad_clase', '0', ['<?= $item['id']; ?>'],'NULL','Está seguro que desea eliminar este registro?')">Eliminar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            // initSelect2('#clase_actividad');
        </script>
        <?php

        break;
}
