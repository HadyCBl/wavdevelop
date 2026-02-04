<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */ // require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';

require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Models\CuentaCobrar;
use Micro\Models\PlanPagos;
use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Month;

$database = new DatabaseAdapter();
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];
switch ($condi) {
    case 'admin_grupos':
        $idGrupoSeleccionado = $_POST["xtra"];
        $showmensaje = false;
        try {

            $database->openConnection();

            $nomenclatura = $database->selectColumns("ctb_nomenclatura", ['id', 'ccodcta', 'cdescrip', 'tipo'], 'estado = 1', orderBy: 'ccodcta');

            $gruposExistentes = $database->getAllResults(
                "SELECT grup.id, grup.nombre, nom.ccodcta, nom.cdescrip, 
                    (SELECT COUNT(*)  FROM cc_grupos_clientes WHERE id_grupo = grup.id) as total
                    FROM cc_grupos grup
                    LEFT JOIN ctb_nomenclatura nom ON nom.id=grup.id_nomenclatura
                    WHERE grup.estado='1' ORDER BY grup.nombre;"
            );
            // Obtener todos los grupos activos
            $clientesDisponibles = $database->getAllResults(
                "SELECT idcod_cliente as id_cliente, CONCAT(short_name, ' - ', no_identifica) as nombre_completo 
                 FROM tb_cliente 
                 WHERE estado = 1 
                 ORDER BY short_name"
            );

            if ($idGrupoSeleccionado != '0') {
                $grupoSelected = $database->selectColumns(
                    'cc_grupos',
                    ['id', 'nombre', 'id_nomenclatura', 'estado'],
                    "id = ? AND estado='1'",
                    [$idGrupoSeleccionado]
                );
                if (empty($grupoSelected)) {
                    $showmensaje = true;
                    throw new Exception("El grupo seleccionado no existe o no está activo.");
                }
                $grupoSelected = $grupoSelected[0];

                // Obtener los clientes del grupo
                $clientesDelGrupo = $database->getAllResults(
                    "SELECT gc.id_cliente, CONCAT(c.short_name, ' - ', c.no_identifica) as nombre_completo
                     FROM cc_grupos_clientes gc
                     INNER JOIN tb_cliente c ON gc.id_cliente = c.idcod_cliente
                     WHERE gc.id_grupo = ?
                     ORDER BY c.short_name",
                    [$idGrupoSeleccionado]
                );
            }


            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

?>
        <input type="hidden" value="admin_grupos" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div x-data="gruposManager(<?= htmlspecialchars(json_encode($clientesDelGrupo ?? [])) ?>)" class="p-4 max-w-7xl mx-auto grid gap-4">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                    <p><?= htmlspecialchars($mensaje) ?></p>
                </div>
            <?php endif; ?>

            <!-- Formulario de Grupo -->
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <h3 class="card-title" id="formTitle"><?= isset($grupoSelected) ? 'Editar' : 'Crear' ?> grupo</h3>

                    <form id="grupoForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="nombre" class="label"><span class="label-text">Nombre del grupo *</span></label>
                                <input id="nombre" name="nombre" type="text" required data-label="Nombre" minlength="3" maxlength="100"
                                    value="<?= isset($grupoSelected) ? htmlspecialchars($grupoSelected['nombre']) : '' ?>"
                                    class="input input-bordered w-full validator" />
                            </div>

                            <div>
                                <label for="id_nomenclatura" class="label"><span class="label-text">Nomenclatura contable *</span></label>
                                <select id="id_nomenclatura" name="id_nomenclatura" required data-label="Nomenclatura"
                                    class="select select-bordered w-full validator">
                                    <option value="" selected disabled>Seleccione nomenclatura</option>
                                    <?php
                                    $currentGroupOpen = false;
                                    if (!empty($nomenclatura)):
                                        foreach ($nomenclatura as $nom):
                                            $level = (int) (strlen($nom['ccodcta']) / 2);
                                            $indent = str_repeat('&nbsp;', max(0, $level - 1) * 4);

                                            if (($nom['tipo'] ?? '') === 'R') {
                                                if ($currentGroupOpen) {
                                                    echo '</optgroup>';
                                                }
                                                $label = $indent . htmlspecialchars($nom['cdescrip']);
                                                echo '<optgroup label="' . $label . '">';
                                                $currentGroupOpen = true;
                                            } else {
                                                $selected = (isset($grupoSelected) && (string)$grupoSelected['id_nomenclatura'] === (string)$nom['id']) ? ' selected' : '';
                                                $text = $indent . htmlspecialchars($nom['ccodcta'] . ' - ' . $nom['cdescrip']);
                                                echo '<option value="' . htmlspecialchars($nom['id']) . '"' . $selected . '>' . $text . '</option>';
                                            }
                                        endforeach;

                                        if ($currentGroupOpen) :
                                            echo '</optgroup>';
                                        endif;
                                    else:
                                        echo '<option value="">No hay cuentas contables disponibles</option>';
                                    endif;
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <h4 class="font-semibold mb-3">Integrantes del grupo</h4>

                            <div class="mb-3">
                                <label for="cliente_select" class="label"><span class="label-text">Agregar cliente</span></label>
                                <div class="flex gap-2">
                                    <select id="cliente_select" class="select select-bordered flex-1">
                                        <option value="">Seleccione un cliente</option>
                                        <?php foreach ($clientesDisponibles as $cliente): ?>
                                            <option value="<?= $cliente['id_cliente'] ?>">
                                                <?= htmlspecialchars($cliente['nombre_completo']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" @click="agregarCliente()" class="btn btn-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                        </svg>
                                        Agregar
                                    </button>
                                </div>
                            </div>

                            <!-- Lista de clientes agregados -->
                            <div class="bg-base-200 rounded-lg p-3">
                                <template x-if="clientes.length === 0">
                                    <p class="text-center text-gray-500 py-4">No hay clientes agregados al grupo</p>
                                </template>

                                <template x-if="clientes.length > 0">
                                    <div class="space-y-2">
                                        <template x-for="(cliente, index) in clientes" :key="cliente.id">
                                            <div class="flex items-center justify-between bg-base-100 p-3 rounded shadow-sm border border-base-300">
                                                <span x-text="cliente.nombre" class="font-medium text-base-content"></span>
                                                <button type="button" @click="removerCliente(index)"
                                                    class="btn btn-sm btn-error btn-outline">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    Quitar
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <div class="mt-3 text-sm text-gray-600">
                                    Total de integrantes: <span x-text="clientes.length" class="font-semibold"></span>
                                </div>
                            </div>
                        </div>

                        <?= $csrf->getTokenField(); ?>

                        <div class="flex justify-end gap-2 pt-4 border-t">
                            <button type="button"
                                onclick="printdiv2('#cuadro', '0')"
                                class="btn btn-soft btn-error">Cancelar</button>

                            <?php if (isset($grupoSelected) && $status): ?>
                                <button type="button" id="btnActualizar" @click="actualizarGrupo()"
                                    class="btn btn-soft btn-info">Actualizar grupo</button>
                            <?php endif; ?>
                            <?php if (!isset($grupoSelected) && $status): ?>
                                <button type="button" id="btnGuardar" @click="guardarGrupo()"
                                    class="btn btn-soft btn-success">Guardar grupo</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <h3 class="card-title">Grupos existentes</h3>

                    <div class="overflow-x-auto">
                        <table id="tablaGrupos" class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Nomenclatura</th>
                                    <th>Integrantes</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($gruposExistentes)): ?>
                                    <?php foreach ($gruposExistentes as $key => $grupo): ?>
                                        <tr>
                                            <td><?= $key + 1 ?></td>
                                            <td><?= htmlspecialchars($grupo['nombre']) ?></td>
                                            <td><?= htmlspecialchars($grupo['ccodcta'] . ' - ' . $grupo['cdescrip']) ?></td>
                                            <td>
                                                <span class="badge badge-primary"><?= $grupo['total'] ?> cliente(s)</span>
                                            </td>
                                            <td>
                                                <div class="flex gap-1">
                                                    <button type="button"
                                                        onclick="printdiv2('#cuadro','<?= htmlspecialchars($grupo['id']) ?>')"
                                                        class="btn btn-sm btn-info btn-outline"
                                                        title="Editar grupo">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                        </svg>
                                                    </button>
                                                    <button type="button"
                                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>'],[],[],'cc_grupos_delete','0',['<?= htmlspecialchars($secureID->encrypt($grupo['id'])) ?>'],'NULL','¿Está seguro de eliminar este grupo?','crud_cuentas_por_cobrarxx')"
                                                        class="btn btn-sm btn-error btn-outline"
                                                        title="Eliminar grupo">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                initTomSelect('#id_nomenclatura', {
                    dropdownParent: 'body'
                });
                initTomSelect('#cliente_select', {
                    dropdownParent: 'body'
                });
                startValidationGeneric('#grupoForm');
                convert_table_to_datatable('tablaGrupos');
            });

            function gruposManager(clientesIniciales = []) {
                return {
                    clientes: clientesIniciales.map(c => ({
                        id: c.id_cliente,
                        nombre: c.nombre_completo
                    })),

                    agregarCliente() {
                        const select = document.getElementById('cliente_select');
                        const idCliente = select.value;
                        const nombreCliente = select.options[select.selectedIndex].text;

                        if (!idCliente) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Atención',
                                text: 'Por favor, seleccione un cliente'
                            });
                            return;
                        }
                        if (this.clientes.some(c => c.id == idCliente)) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Cliente duplicado',
                                text: 'Este cliente ya está agregado al grupo'
                            });
                            return;
                        }

                        this.clientes.push({
                            id: idCliente,
                            nombre: nombreCliente
                        });
                        select.value = '';
                    },

                    removerCliente(index) {
                        this.clientes.splice(index, 1);
                    },

                    getClientesIds() {
                        return JSON.stringify(this.clientes.map(c => c.id));
                    },

                    guardarGrupo() {
                        if (this.clientes.length === 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Debe agregar al menos un cliente al grupo'
                            });
                            return;
                        }

                        obtiene(['<?= $csrf->getTokenName() ?>', 'nombre'], ['id_nomenclatura'], [], 'cc_grupos_create', '0', [this.getClientesIds()], 'NULL', '¿Está seguro de crear este grupo?', 'crud_cuentas_por_cobrarxx');
                    },

                    actualizarGrupo() {
                        if (this.clientes.length === 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Debe agregar al menos un cliente al grupo'
                            });
                            return;
                        }

                        obtiene(['<?= $csrf->getTokenName() ?>', 'nombre'], ['id_nomenclatura'], [], 'cc_grupos_update', '0',
                            ['<?= isset($grupoSelected) ? htmlspecialchars($secureID->encrypt($idGrupoSeleccionado)) : '' ?>', this.getClientesIds()],
                            'NULL',
                            '¿Está seguro de actualizar este grupo?',
                            'crud_cuentas_por_cobrarxx'
                        );
                    }
                }
            }
        </script>
    <?php
        break;
    case 'create_periodos':
        $idSeleccionado = $_POST["xtra"];
        $showmensaje = false;
        try {

            $database->openConnection();

            $periodosExistentes = $database->selectColumns(
                'cc_periodos',
                ['id', 'nombre', 'fecha_inicio', 'fecha_fin', 'tasa_interes', 'tasa_mora', 'estado'],
                condition: 'estado IN ("1","2")',
                orderBy: 'estado DESC, fecha_inicio DESC'
            );


            if ($idSeleccionado != '0') {
                $periodoSelected = $database->selectColumns(
                    'cc_periodos',
                    ['id', 'nombre', 'fecha_inicio', 'fecha_fin', 'tasa_interes', 'tasa_mora', 'estado'],
                    'id = ? AND estado IN ("1","2")',
                    [$idSeleccionado]
                );
                if (empty($periodoSelected)) {
                    $showmensaje = true;
                    throw new Exception("El período seleccionado no existe o no está activo.");
                }
                $periodoSelected = $periodoSelected[0];
            }


            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

    ?>
        <input type="hidden" value="create_periodos" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">
        <div class="p-4 max-w-6xl mx-auto grid gap-4 sm:grid-cols-3">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                    <p><?= htmlspecialchars($mensaje) ?></p>
                </div>
            <?php endif; ?>
            <div class="sm:col-span-3">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title" id="formTitle"><?= isset($periodoSelected) ? 'Editar' : 'Crear' ?> período</h3>

                        <form id="periodoForm" class="space-y-3">
                            <div>
                                <label for="nombre" class="label"><span class="label-text">Nombre</span></label>
                                <input id="nombre" name="nombre" type="text" required data-label="Nombre" minlength="3" maxlength="100"
                                    value="<?= isset($periodoSelected) ? $periodoSelected['nombre'] : '' ?>"
                                    class="input input-bordered w-full validator" />
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="fecha_inicio" class="label"><span class="label-text">Fecha inicio</span></label>
                                    <input id="fecha_inicio" name="fecha_inicio" type="date" required data-label="Fecha inicio"
                                        value="<?= isset($periodoSelected) ? $periodoSelected['fecha_inicio'] : date('Y-m-d') ?>"
                                        class="input input-bordered w-full validator" />
                                </div>
                                <div>
                                    <label for="fecha_fin" class="label"><span class="label-text">Fecha fin</span></label>
                                    <input id="fecha_fin" name="fecha_fin" type="date" required data-label="Fecha fin"
                                        value="<?= isset($periodoSelected) ? $periodoSelected['fecha_fin'] : date('Y-m-d') ?>"
                                        class="input input-bordered w-full validator" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="tasa_interes" class="label"><span class="label-text">Tasa interés (%)</span></label>
                                    <input id="tasa_interes" name="tasa_interes" type="number" min="0" step="0.01" required
                                        value="<?= isset($periodoSelected) ? $periodoSelected['tasa_interes'] : '' ?>"
                                        data-label="Tasa interés" class="input input-bordered w-full validator" />
                                </div>
                                <div>
                                    <label for="tasa_mora" class="label"><span class="label-text">Tasa mora (%)</span></label>
                                    <input id="tasa_mora" name="tasa_mora" type="number" min="0" step="0.01" required
                                        value="<?= isset($periodoSelected) ? $periodoSelected['tasa_mora'] : '' ?>"
                                        data-label="Tasa mora" class="input input-bordered w-full validator" />
                                </div>
                            </div>
                            <?= $csrf->getTokenField(); ?>
                            <div class="flex justify-end gap-2">
                                <button type="button"
                                    onclick="printdiv2('#cuadro', '0')"
                                    class="btn btn-soft btn-error">Cancelar</button>

                                <?php if (isset($periodoSelected) && $status): ?>
                                    <button type="button" id="btnActualizar"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','nombre','fecha_inicio','fecha_fin','tasa_interes','tasa_mora'],[],[],'update_periodos', '0', ['<?= htmlspecialchars($secureID->encrypt($idSeleccionado)) ?>'], 'NULL', 'Está seguro de actualizar éste periodo?', 'crud_cuentas_por_cobrarxx')"
                                        class="btn btn-soft btn-info">Actualizar</button>
                                <?php endif; ?>
                                <?php if (!isset($periodoSelected) && $status): ?>
                                    <button type="button" id="btnGuardar"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','nombre','fecha_inicio','fecha_fin','tasa_interes','tasa_mora'],[],[],'create_periodos', '0', [], 'NULL', 'Está seguro de crear éste nuevo periodo?', 'crud_cuentas_por_cobrarxx')"
                                        class="btn btn-soft btn-success">Guardar</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="sm:col-span-3">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h3 class="card-title">Períodos existentes</h3>

                        <div class="overflow-x-auto">
                            <table class="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                        <th>Tasa interés</th>
                                        <th>Tasa mora</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($periodosExistentes)): ?>
                                        <?php foreach ($periodosExistentes as $p): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($p['nombre']) ?></td>
                                                <td><?= setdatefrench(htmlspecialchars($p['fecha_inicio'])) ?></td>
                                                <td><?= setdatefrench(htmlspecialchars($p['fecha_fin'])) ?></td>
                                                <td><?= htmlspecialchars($p['tasa_interes']) ?>%</td>
                                                <td><?= htmlspecialchars($p['tasa_mora']) ?>%</td>
                                                <td>
                                                    <?php if ($p['estado'] == 1): ?>
                                                        <span class="badge badge-success">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="flex gap-2">
                                                        <?php if ($p['estado'] == 1): ?>
                                                            <button type="button" class="btn btn-soft btn-warning btn-sm btn-edit" title="Editar" onclick="printdiv2('#cuadro', <?= $p['id'] ?>)">
                                                                Editar
                                                            </button>
                                                            <button type="button" class="btn btn-soft btn-error btn-sm btn-delete" title="Eliminar"
                                                                onclick="obtiene(['<?= $csrf->getTokenName() ?>'],[],[],'delete_periodos', '0', ['<?= htmlspecialchars($secureID->encrypt($p['id'])) ?>'], 'NULL', 'Está seguro de eliminar éste periodo?', 'crud_cuentas_por_cobrarxx')">
                                                                Eliminar
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No hay períodos registrados</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <script>
            $(document).ready(function() {
                startValidationGeneric('#periodoForm');
            });
        </script>
    <?php
        break;
    case 'create_account':

        $codigoCliente = $_POST["xtra"];

        $showmensaje = false;
        try {
            if ($codigoCliente == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione un cliente ");
            }
            $database->openConnection();

            $clienteSeleccionado = $database->getAllResults(
                "SELECT idcod_cliente, short_name, no_identifica, IFNULL(g.nombre, '') AS grupo_nombre
                FROM tb_cliente cli 
                LEFT JOIN cc_grupos_clientes gc ON cli.idcod_cliente = gc.id_cliente
                LEFT JOIN cc_grupos g ON gc.id_grupo = g.id AND g.estado = '1'
                WHERE cli.idcod_cliente = ? AND cli.estado = 1  LIMIT 1",
                [$codigoCliente]
            );
            if (empty($clienteSeleccionado)) {
                $showmensaje = true;
                throw new Exception("El cliente seleccionado no existe o no está activo.");
            }

            $periodosExistentes = $database->selectColumns(
                'cc_periodos',
                ['id', 'nombre', 'fecha_inicio', 'fecha_fin', 'tasa_interes', 'tasa_mora', 'estado'],
                condition: 'estado = "1"',
                orderBy: 'fecha_inicio DESC'
            );

            $historialCliente = $database->getAllResults(
                "SELECT per.nombre,cuen.tasa_interes,cuen.fecha_inicio,cuen.fecha_fin,cuen.estado,
                    (SELECT SUM(kp) FROM cc_kardex WHERE id_cuenta=cuen.id AND tipo='D' AND estado='1' GROUP BY id_cuenta) AS financiado
                    FROM cc_cuentas cuen 
                    INNER JOIN cc_periodos per ON per.id=cuen.id_periodo
                    WHERE cuen.estado IN ('ACTIVA','CANCELADA') AND cuen.id_cliente=? ORDER BY cuen.fecha_inicio DESC",
                [$codigoCliente]
            );

            $tiposGarantia = $database->selectColumns('cc_tipos_garantias', ['id', 'nombre']);


            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
        $cliente = $clienteSeleccionado[0] ?? null;
        $cliente_codigo = $cliente['idcod_cliente'] ?? $codigoCliente;
        $cliente_nombre = $cliente['short_name'] ?? '';
        $cliente_ident = $cliente['no_identifica'] ?? '';
        $cliente_grupo = $cliente['grupo_nombre'] ?? '';
    ?>
        <input type="hidden" value="create_account" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">
        <div class="p-6 max-w-6xl mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Datos del Cliente</h2>
                            <div>
                                <button type="button" class="btn btn-ghost" onclick="printdiv2('#cuadro', '0')">Cancelar</button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-start gap-4">
                                <label class="label w-28"><span class="label-text">Código</span></label>
                                <div class="prose">
                                    <p id="cliente_codigo_display" class="m-0 font-medium text-base"><?= htmlspecialchars($cliente_codigo) ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-4">
                                <label class="label w-28"><span class="label-text">Nombre</span></label>
                                <div class="flex-1">
                                    <p id="cliente_nombre_display" class="m-0 text-base-content"><?= htmlspecialchars($cliente_nombre) ?: '<span class="text-sm text-neutral">No seleccionado</span>' ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-4">
                                <label class="label w-28"><span class="label-text">Identificación</span></label>
                                <div class="flex-1">
                                    <p id="cliente_identificacion_display" class="m-0 text-base-content"><?= htmlspecialchars($cliente_ident) ?: '<span class="text-sm text-neutral">No disponible</span>' ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-4">
                                <label class="label w-28"><span class="label-text">Grupo</span></label>
                                <div class="flex-1">
                                    <p id="cliente_grupo_display" class="m-0 text-base-content"><?= htmlspecialchars($cliente_grupo) ?: '<span class="text-sm text-error">Sin grupo</span>' ?></p>
                                </div>
                            </div>

                            <div class="md:col-span-2 pt-2">
                                <button type="button" class="btn btn-primary" onclick="my_modal_1.showModal()">Buscar cliente</button>
                                <p class="text-sm text-success mt-2">Si necesita cambiar el cliente, use el buscador.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Historial del Cliente</h2>
                            <div class="text-sm text-neutral">
                                <?= !empty($historialCliente) ? count($historialCliente) . ' registro(s)' : 'Sin historial' ?>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table table-zebra table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Período</th>
                                        <th>Total financiado</th>
                                        <th>Tasa interés</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($historialCliente)): ?>
                                        <?php foreach ($historialCliente as $h): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($h['nombre'] ?? '') ?></td>
                                                <td><?= isset($h['financiado']) ? htmlspecialchars(number_format($h['financiado'], 2, '.', ',')) : '-' ?></td>
                                                <td><?= isset($h['tasa_interes']) ? htmlspecialchars($h['tasa_interes']) . '%' : '-' ?></td>
                                                <td><?= isset($h['fecha_inicio']) ? setdatefrench(htmlspecialchars($h['fecha_inicio'])) : '-' ?></td>
                                                <td><?= isset($h['fecha_fin']) && !empty($h['fecha_fin']) ? setdatefrench(htmlspecialchars($h['fecha_fin'])) : '-' ?></td>
                                                <td>
                                                    <?php $estado = strtoupper($h['estado'] ?? ''); ?>
                                                    <?php if ($estado === 'ACTIVA'): ?>
                                                        <span class="badge badge-success">Activa</span>
                                                    <?php elseif ($estado === 'CANCELADA'): ?>
                                                        <span class="badge badge-warning">Cancelada</span>
                                                    <?php else: ?>
                                                        <span class="badge"><?= htmlspecialchars($h['estado'] ?? '-') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">No hay cuentas en el historial del cliente.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Crear una nueva Cuenta</h2>

                        <form id="accountForm" class="grid grid-cols-1 gap-4" x-data="{ tasa_interes: null, nota_periodo: '' }">
                            <div>
                                <label class="label"><span class="label-text">Período <span class="text-xs text-error">*</span></span></label>
                                <select id="periodo_id" name="periodo_id" required class="select select-bordered w-full validator"
                                    data-label="Período"
                                    @change="tasa_interes = $event.target.options[$event.target.selectedIndex].getAttribute('data-tasa-interes'); 
                                    nota_periodo = `Este periodo inicia el ${$event.target.options[$event.target.selectedIndex].getAttribute('data-fecha-inicio')} y finaliza el ${$event.target.options[$event.target.selectedIndex].getAttribute('data-fecha-fin')}`;">
                                    <option value="" disabled selected>Seleccione un período</option>
                                    <?php if (!empty($periodosExistentes)): ?>
                                        <?php foreach ($periodosExistentes as $pe): ?>
                                            <option value="<?= htmlspecialchars($pe['id']) ?>" data-tasa-interes="<?= htmlspecialchars($pe['tasa_interes']) ?>" data-fecha-inicio="<?= htmlspecialchars(Date::toDMY($pe['fecha_inicio'])) ?>" data-fecha-fin="<?= htmlspecialchars(Date::toDMY($pe['fecha_fin'])) ?>">
                                                <?= htmlspecialchars($pe['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No hay períodos disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div role="alert" class="alert alert-info alert-outline">
                                <span x-text="`Nota: ${nota_periodo}`"></span>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="label"><span class="label-text">Tasa de interés (%) <span class="text-xs text-error">*</span></span></label>
                                    <input id="tasa_interes" name="tasa_interes" type="number" min="0"
                                        step="0.01" x-model="tasa_interes" required
                                        class="input input-bordered w-full validator" data-label="Tasa de interés" />
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">Límite de financiamiento</span></label>
                                    <input id="monto_limite" name="monto_limite" type="number" min="0" step="0.01"
                                        class="input input-bordered w-full validator" data-label="Límite de financiamiento" />
                                    <p class="text-sm text-neutral mt-1">Nota: si lo deja en 0 o vacío, indica que no tiene límite de financiamiento.</p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <h4 class="text-lg font-medium">Fechas de inicio y finalización de cobro de interés</h4>
                                <p class="text-sm text-neutral">Seleccione la fecha de inicio y la fecha de vencimiento para el cobro de intereses.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">

                                <div>
                                    <label class="label"><span class="label-text">Fecha inicio <span class="text-xs text-error">*</span></span></label>
                                    <input id="fecha_inicio_cuenta" name="fecha_inicio_cuenta" type="date" required
                                        class="input input-bordered w-full validator" data-label="Fecha inicio" value="<?= date('Y-m-d') ?>" />
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">Fecha vencimiento <span class="text-xs text-error">*</span></span></label>
                                    <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" required
                                        class="input input-bordered w-full validator" data-label="Fecha vencimiento" value="<?= date('Y-m-d') ?>" />
                                </div>
                            </div>

                            <?= $csrf->getTokenField(); ?>

                            <div class="flex justify-end gap-2 mt-2">
                                <button type="button" class="btn btn-outline" onclick="printdiv2('#cuadro', '0')">Cancelar</button>
                                <button type="button" id="btnCrearCuenta" class="btn btn-success"
                                    onclick="obtiene(['<?= $csrf->getTokenName() ?>','tasa_interes','monto_limite','fecha_inicio_cuenta','fecha_vencimiento'],
                                    ['periodo_id'],[],'create_account','0',['<?= htmlspecialchars($secureID->encrypt($codigoCliente)) ?>',getAlpineData(`[x-data='garantiasManager()']`, 'garantias')],
                                    'NULL', '¿Está seguro de crear esta cuenta?', 'crud_cuentas_por_cobrarxx')">
                                    Guardar Cuenta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-md">
                    <div class="card-body" x-data="garantiasManager()">
                        <h3 class="card-title">Garantías</h3>

                        <form id="garantiasForm" class="grid grid-cols-1 gap-4" @submit.prevent="agregarGarantia">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                <div>
                                    <label class="label"><span class="label-text">Tipo de garantía</span></label>
                                    <select x-model="nuevaGarantia.tipo_id"
                                        @change="nuevaGarantia.tipo_nombre = $event.target.options[$event.target.selectedIndex].text"
                                        class="select select-bordered w-full validator"
                                        data-label="Tipo de garantía"
                                        required id="garantia_tipo">
                                        <option value="" disabled>Seleccione tipo</option>
                                        <?php if (!empty($tiposGarantia)): ?>
                                            <?php foreach ($tiposGarantia as $tg): ?>
                                                <option value="<?= htmlspecialchars($tg['id']) ?>">
                                                    <?= htmlspecialchars($tg['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">No hay tipos</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="label"><span class="label-text">Descripción</span></label>
                                    <input x-model="nuevaGarantia.descripcion"
                                        type="text"
                                        class="input input-bordered w-full validator"
                                        data-label="Descripción"
                                        required
                                        maxlength="250" id="garantia_descripcion" />
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">Valor</span></label>
                                    <input x-model.number="nuevaGarantia.valor"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                        class="input input-bordered w-full validator"
                                        data-label="Valor" id="garantia_valor" />
                                </div>
                            </div>

                            <div>
                                <label class="label"><span class="label-text">Observaciones</span></label>
                                <textarea x-model="nuevaGarantia.observaciones"
                                    class="textarea textarea-bordered w-full"
                                    rows="2"
                                    maxlength="500" id="garantia_observaciones"></textarea>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button"
                                    @click="agregarGarantia"
                                    class="btn btn-sm btn-primary">
                                    Agregar garantía
                                </button>
                            </div>
                        </form>

                        <!-- Mostrar alerta si no hay garantías -->
                        <div x-show="garantias.length === 0" class="alert alert-info mt-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>No hay garantías agregadas. Agregue al menos una garantía.</span>
                        </div>

                        <!-- Tabla de garantías -->
                        <div x-show="garantias.length > 0" class="mt-4">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-medium">Listado de garantías</h4>
                                <span class="badge badge-primary" x-text="`${garantias.length} garantía(s)`"></span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="table table-zebra table-sm w-full">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tipo</th>
                                            <th>Descripción</th>
                                            <th>Valor</th>
                                            <th>Observaciones</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(garantia, index) in garantias" :key="index">
                                            <tr>
                                                <td x-text="index + 1"></td>
                                                <td x-text="garantia.tipo_nombre"></td>
                                                <td x-text="garantia.descripcion || '-'"></td>
                                                <td x-text="'Q ' + Number(garantia.valor).toFixed(2)"></td>
                                                <td x-text="garantia.observaciones || '-'"></td>
                                                <td class="text-center">
                                                    <button type="button"
                                                        @click="eliminarGarantia(index)"
                                                        class="btn btn-sm btn-ghost btn-error">
                                                        Eliminar
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right font-bold">Total garantías:</td>
                                            <td class="font-bold" x-text="'Q ' + calcularTotal()"></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <input type="hidden"
                            id="garantias_json"
                            name="garantias"
                            :value="JSON.stringify(garantias)">
                    </div>
                </div>
            </div>
        </div>



        <dialog id="my_modal_1" class="modal">
            <div class="modal-box w-11/12 max-w-5xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Buscar Cliente</h3>
                    <form method="dialog">
                        <button class="btn btn-sm btn-circle btn-ghost">✕</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table id="clientesTable" class="table table-zebra table-sm w-full">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Identificación</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost">Cerrar</button>
                    </form>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <script>
            $(document).ready(function() {
                // viewLoader.init();
                const columns = [{
                        data: 'codigo_cliente',
                        className: 'text-left'
                    },
                    {
                        data: 'nombre',
                        className: 'text-left'
                    },
                    {
                        data: 'identificacion',
                    },
                    {
                        data: null,
                        title: 'Acción',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<button class="btn btn-soft btn-success" onclick="printdiv2('#cuadro','${row.codigo_cliente}');my_modal_1.close();">Seleccionar</button>`;
                        }
                    }
                ];
                const table = initServerSideDataTable(
                    '#clientesTable',
                    'cli_clientes_all',
                    columns, {
                        onError: function(xhr, error, thrown) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al cargar clientes',
                                text: 'Por favor, intente nuevamente'
                            });
                        }
                    }
                );
                const periodoSelect = initTomSelect('#periodo_id');
                startValidationGeneric('#accountForm');
                startValidationGeneric('#garantiasForm');
            });

            function garantiasManager() {
                return {
                    garantias: [],
                    nuevaGarantia: {
                        tipo_id: '',
                        tipo_nombre: '',
                        descripcion: '',
                        valor: 0,
                        observaciones: ''
                    },
                    agregarGarantia() {
                        const validacion = validarCamposGeneric(['garantia_descripcion', 'garantia_valor', 'garantia_observaciones'], ['garantia_tipo'], []);
                        if (!validacion.esValido) {
                            return;
                        }
                        this.garantias.push({
                            tipo_id: this.nuevaGarantia.tipo_id,
                            tipo_nombre: this.nuevaGarantia.tipo_nombre,
                            descripcion: this.nuevaGarantia.descripcion.trim(),
                            valor: parseFloat(this.nuevaGarantia.valor),
                            observaciones: this.nuevaGarantia.observaciones.trim()
                        });
                        this.limpiarFormulario();
                    },

                    eliminarGarantia(index) {
                        this.garantias.splice(index, 1);
                    },

                    limpiarFormulario() {
                        this.nuevaGarantia = {
                            tipo_id: '',
                            tipo_nombre: '',
                            descripcion: '',
                            valor: 0,
                            observaciones: ''
                        };
                    },

                    calcularTotal() {
                        const total = this.garantias.reduce((sum, g) => sum + parseFloat(g.valor || 0), 0);
                        return total.toFixed(2);
                    },
                }
            }
        </script>
    <?php
        break;
    /**
     * este es para realizar desembolsos o dar anticipos
     */
    case 'create_desembolsos':

        $accountCode = $_POST["xtra"];
        $hayDesembolsos = false;
        $desembolsoCompleto = false;
        $saldoPendienteDesembolso = 0;
        $withLimit = true;

        $showmensaje = false;
        try {

            $database->openConnection();

            if ($accountCode == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta");
            }
            $cuentaSeleccionada = $database->getAllResults(
                "SELECT cue.id,cli.short_name,cli.no_identifica,cue.monto_inicial,cue.tasa_interes,cue.fecha_inicio,
                    cue.fecha_fin,per.nombre AS periodo_nombre
                    FROM cc_cuentas cue
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cue.id_cliente
                    LEFT JOIN cc_periodos per ON per.id=cue.id_periodo
                    WHERE cue.id=? AND cue.estado IN ('SOLICITADA', 'ACTIVA') LIMIT 1",
                [$accountCode]
            );
            if (empty($cuentaSeleccionada)) {
                $showmensaje = true;
                throw new Exception("La cuenta seleccionada no existe o no está activa.");
            }

            $desembolsos = $database->getAllResults(
                "SELECT kar.id,kar.total,kar.kp, kar.fecha,kar.tipo,kar.numdoc,kar.forma_pago
                    FROM cc_kardex kar
                    WHERE kar.estado='1' AND kar.tipo='D' AND kar.id_cuenta=? ORDER BY kar.fecha DESC;",
                [$accountCode]
            );

            $movimientos = $database->getAllResults(
                "SELECT kar.id,kar.total, kar.fecha,kar.tipo,kar.numdoc,kar.forma_pago, det.monto, tip.nombre as tipo_movimiento
                    FROM cc_kardex kar
                    INNER JOIN cc_kardex_detalle det ON det.id_kardex=kar.id
                    INNER JOIN cc_tipos_movimientos tip ON tip.id=det.id_movimiento
                    WHERE kar.estado='1' AND kar.tipo='E' AND kar.id_cuenta=? ORDER BY kar.fecha DESC",
                [$accountCode]
            );

            $tiposMovimientos = $database->selectColumns('cc_tipos_movimientos', ['id', 'nombre'], 'naturaleza="cargo"');

            $bancos = $database->getAllResults(
                "SELECT ctb.id,ban.nombre, ctb.numcuenta 
                    FROM ctb_bancos ctb
                    INNER JOIN tb_bancos ban ON ctb.id_banco=ban.id
                    WHERE ctb.estado=1 AND ban.estado='1';"
            );

            $totalDesembolsado = array_sum(array_column($desembolsos, 'total'));



            if ($cuentaSeleccionada[0]['monto_inicial'] == 0) {
                $withLimit = false;
                $saldoPendienteDesembolso = 0;
            } else {
                $withLimit = true;
                $montoInicial = $cuentaSeleccionada[0]['monto_inicial'] ?? 0;
                $saldoPendienteDesembolso = $montoInicial - $totalDesembolsado;

                // Determinar qué tipo de movimientos están permitidos
                // $hayDesembolsos = !empty($desembolsos);
                $desembolsoCompleto = $totalDesembolsado >= $montoInicial;
            }

            $cuentaCobrarModel = new CuentaCobrar($database, $accountCode);

            // Obtener solo saldo de financiamientos
            $saldoFinanciamientos = $cuentaCobrarModel->getSaldoFinanciamientos(date('Y-m-d'));

            // Log::info("Saldo de financiamientos: $saldoFinanciamientos");

            // Obtener saldo de otros cargos
            $saldosOtrosCargos = $cuentaCobrarModel->getSaldoOtrosCargos(null, date('Y-m-d'));

            // Log::info("Saldo de otros cargos:", $saldosOtrosCargos);


            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

        $cuenta = $cuentaSeleccionada[0] ?? null;
    ?>
        <input type="hidden" value="create_desembolsos" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-7xl mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Estado de la cuenta -->
            <?php if ($status && $withLimit && !$desembolsoCompleto): ?>
                <div class="alert alert-info mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <strong>Se pueden entregar financimientos todavia a esta persona</strong>
                        <p class="text-sm mt-1">
                            Límite de financiamientos: <?= Moneda::formato($montoInicial) ?> |
                            Desembolsado: <?= Moneda::formato($totalDesembolsado) ?> |
                            <span class="font-semibold">Disponible: <?= Moneda::formato($saldoPendienteDesembolso) ?></span>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($status && $withLimit && $desembolsoCompleto): ?>
                <div class="alert alert-error mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <strong>Ya no se pueden entregar financiamientos a esta persona</strong>
                        <p class="text-sm mt-1">
                            Límite de financiamientos: <?= Moneda::formato($montoInicial) ?> |
                            Desembolsado: <?= Moneda::formato($totalDesembolsado) ?> |
                            <span class="font-semibold">Disponible: <?= Moneda::formato($saldoPendienteDesembolso) ?></span>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Información de la cuenta -->
            <div class="card bg-base-100 shadow-md mb-6">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="card-title">Información de la Cuenta</h2>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm" onclick="modal_cuentas.showModal()">
                                Cambiar Cuenta
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="printdiv2('#cuadro', '0')">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="label"><span class="label-text font-semibold">Cliente</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['short_name'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Identificación</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['no_identifica'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Período</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['periodo_nombre'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Límite</span></label>
                            <p class="text-base-content"> <?= ($withLimit) ? htmlspecialchars(Moneda::formato($cuenta['monto_inicial'] ?? 0)) : '-- sin límite --' ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Total Financiado</span></label>
                            <p class="text-base-content"><?= htmlspecialchars(Moneda::formato($totalDesembolsado ?? 0)) ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Tasa de Interés</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['tasa_interes'] ?? 0) ?>%</p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Fecha Inicio</span></label>
                            <p class="text-base-content"><?= isset($cuenta['fecha_inicio']) ? setdatefrench($cuenta['fecha_inicio']) : 'N/A' ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Fecha Fin</span></label>
                            <p class="text-base-content"><?= isset($cuenta['fecha_fin']) && !empty($cuenta['fecha_fin']) ? setdatefrench($cuenta['fecha_fin']) : 'N/A' ?></p>
                        </div>

                        <?php if (isset($saldoFinanciamientos)): ?>
                            <div>
                                <label class="label"><span class="label-text font-semibold">Saldo financiamientos</span></label>
                                <p class="text-base-content">Q <?= htmlspecialchars(number_format((float)$saldoFinanciamientos, 2, '.', ',')) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($saldosOtrosCargos) && is_array($saldosOtrosCargos)): ?>
                        <div class="mt-4">
                            <h3 class="card-title text-sm mb-2">Saldos - Otras entregas</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra table-sm w-full">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tipo</th>
                                            <th>Otorgado</th>
                                            <th>Recuperado</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $totalOtrosSaldo = 0;
                                        foreach ($saldosOtrosCargos as $key => $oc):
                                            $id_mov = htmlspecialchars($oc['id_movimiento'] ?? $oc['id'] ?? '');
                                            $tipo = htmlspecialchars($oc['tipo_nombre'] ?? $oc['tipo'] ?? '');
                                            $otorgado = isset($oc['otorgado']) ? (float)$oc['otorgado'] : 0;
                                            $recuperado = isset($oc['recuperado']) ? (float)$oc['recuperado'] : 0;
                                            $saldoOc = isset($oc['saldo']) ? (float)$oc['saldo'] : ($otorgado - $recuperado);
                                            $totalOtrosSaldo += $saldoOc;
                                        ?>
                                            <tr>
                                                <td><?= $key + 1 ?></td>
                                                <td><?= $tipo ?></td>
                                                <td>Q <?= htmlspecialchars(number_format($otorgado, 2, '.', ',')) ?></td>
                                                <td>Q <?= htmlspecialchars(number_format($recuperado, 2, '.', ',')) ?></td>
                                                <td>Q <?= htmlspecialchars(number_format($saldoOc, 2, '.', ',')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-bold bg-base-200">
                                            <td colspan="4" class="text-right">Total Otros cargos (saldo):</td>
                                            <td>Q <?= htmlspecialchars(number_format($totalOtrosSaldo, 2, '.', ',')) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desembolsos -->
            <div class="card bg-base-100 shadow-md mb-6">
                <div class="card-body">
                    <h3 class="card-title">Desembolsos de financiamientos realizados</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm w-full">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>No. Documento</th>
                                    <th>Monto</th>
                                    <th>Capital (KP)</th>
                                    <th>Forma de Pago</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($desembolsos)): ?>
                                    <?php foreach ($desembolsos as $d): ?>
                                        <tr>
                                            <td><?= setdatefrench(htmlspecialchars($d['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($d['numdoc'] ?? 'N/A') ?></td>
                                            <td>Q <?= htmlspecialchars(number_format($d['total'], 2, '.', ',')) ?></td>
                                            <td>Q <?= htmlspecialchars(number_format($d['kp'], 2, '.', ',')) ?></td>
                                            <td><?= htmlspecialchars($d['forma_pago'] ?? 'N/A') ?></td>
                                            <td>
                                                <button type="button" class="btn btn-soft btn-primary btn-sm"
                                                    onclick="reportes([[],[],[],[<?= (int)$d['id'] ?>]], 'pdf', 'comprobante_desembolso', 0, 0, 'cuencobra')">
                                                    Imprimir comprobante
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="font-bold bg-base-200">
                                        <td colspan="2" class="text-right">Total Desembolsado:</td>
                                        <td>Q <?= number_format($totalDesembolsado, 2, '.', ',') ?></td>
                                        <td colspan="3"></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No hay desembolsos registrados</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Movimientos (Anticipos) -->
            <div class="card bg-base-100 shadow-md mb-6">
                <div class="card-body">
                    <h3 class="card-title">Otras Entregas</h3>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm w-full">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo Movimiento</th>
                                    <th>No. Documento</th>
                                    <th>Monto</th>
                                    <th>Total</th>
                                    <th>Forma de Pago</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($movimientos)): ?>
                                    <?php
                                    $totalMovimientos = 0;
                                    foreach ($movimientos as $m):
                                        $totalMovimientos += $m['total'];
                                    ?>
                                        <tr>
                                            <td><?= setdatefrench(htmlspecialchars($m['fecha'])) ?></td>
                                            <td><?= htmlspecialchars($m['tipo_movimiento'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($m['numdoc'] ?? 'N/A') ?></td>
                                            <td>Q <?= htmlspecialchars(number_format($m['monto'], 2, '.', ',')) ?></td>
                                            <td>Q <?= htmlspecialchars(number_format($m['total'], 2, '.', ',')) ?></td>
                                            <td><?= htmlspecialchars($m['forma_pago'] ?? 'N/A') ?></td>
                                            <td>
                                                <button type="button" class="btn btn-soft btn-primary btn-sm"
                                                    onclick="reportes([[],[],[],[<?= (int)$m['id'] ?>]], 'pdf', 'comprobante_movimientos', 0, 0, 'cuencobra')">
                                                    Imprimir comprobante
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="font-bold bg-base-200">
                                        <td colspan="4" class="text-right">Total Pagado:</td>
                                        <td colspan="1">Q <?= number_format($totalMovimientos, 2, '.', ',') ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">No hay movimientos registrados</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php if ($status && (($withLimit && !$desembolsoCompleto) || !$withLimit)): ?>
                <div class="card bg-base-100 shadow-md mb-6" x-data="{isBanco: false}">
                    <div class="card-body">
                        <h3 class="card-title">Financiar</h3>
                        <form id="movimientoForm" class="grid grid-cols-1 gap-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="label"><span class="label-text">Fecha <span class="text-xs text-error">*</span></span></label>
                                    <input id="fecha_movimiento" name="fecha_movimiento" type="date" required
                                        class="input input-bordered w-full validator"
                                        data-label="Fecha"
                                        value="<?= $cuenta['fecha_inicio'] ?? date('Y-m-d') ?>" />
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">Forma de Pago <span class="text-xs text-error">*</span></span></label>
                                    <select id="forma_pago" name="forma_pago" required @change="isBanco = $event.target.value === 'banco'"
                                        class="select select-bordered w-full validator"
                                        data-label="Forma de pago">
                                        <option value="" disabled selected>Seleccione forma</option>
                                        <option value="efectivo">Efectivo</option>
                                        <option value="banco">Cheque</option>
                                    </select>
                                </div>
                            </div>

                            <div x-show="isBanco" x-cloak>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label"><span class="label-text">Banco <span class="text-xs text-error">*</span></span></label>
                                        <select id="banco_id" name="banco_id" class="select select-bordered w-full validator" data-label="Banco" :required="isBanco">
                                            <option value="" disabled selected>Seleccione banco</option>
                                            <?php if (!empty($bancos)): ?>
                                                <?php foreach ($bancos as $b): ?>
                                                    <option value="<?= htmlspecialchars($b['id']) ?>"><?= htmlspecialchars($b['nombre']) ?> - <?= htmlspecialchars($b['numcuenta']) ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="">No hay bancos disponibles</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="label"><span class="label-text">Número de Cheque <span class="text-xs text-error">*</span></span></label>
                                        <input id="num_cheque" name="num_cheque" type="text" maxlength="50" class="input input-bordered w-full validator" data-label="Número de Cheque"
                                            :required="isBanco" placeholder="Ingrese número de cheque" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="label"><span class="label-text">Monto <span class="text-xs text-error">*</span></span></label>
                                    <input id="monto_movimiento" name="monto_movimiento" type="text"
                                        min="1" step="0.01" required
                                        <?php if ($withLimit && $saldoPendienteDesembolso > 0): ?>
                                        max="<?= $saldoPendienteDesembolso ?>"
                                        <?php endif; ?>
                                        class="input input-bordered w-full validator decimal-cleave-zen"
                                        data-decimal="2"
                                        data-prefix="Q"
                                        data-label="Monto" value="<?= $saldoPendienteDesembolso ?>" />
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">No. Documento</span></label>
                                    <input id="numdoc" name="numdoc" type="text"
                                        maxlength="50"
                                        required
                                        class="input input-bordered w-full"
                                        data-label="No. Documento"
                                        placeholder="xx" />
                                </div>
                            </div>

                            <div>
                                <label class="label"><span class="label-text">Concepto</span></label>
                                <textarea id="observaciones" name="observaciones"
                                    class="textarea textarea-bordered w-full"
                                    data-label="Concepto"
                                    rows="3" required maxlength="500"></textarea>
                            </div>

                            <?= $csrf->getTokenField(); ?>

                            <div class="flex justify-end gap-2 mt-2">
                                <button type="button" class="btn btn-outline" onclick="printdiv2('#cuadro', '0')">Cancelar</button>
                                <button type="button" id="btnGuardarMovimiento" class="btn btn-success"
                                    onclick="obtiene(['<?= $csrf->getTokenName() ?>','fecha_movimiento','monto_movimiento','numdoc','observaciones','num_cheque'],
                                ['forma_pago','banco_id'],[],'create_desembolso','0',
                                ['<?= htmlspecialchars($secureID->encrypt($accountCode)) ?>'],
                                function(response){
                                reportes([[],[],[],[response.idMovimiento]],'pdf','comprobante_desembolso',0,0,'cuencobra');
                                }, '¿Está seguro de realizar este desembolso?', 'crud_cuentas_por_cobrarxx')">
                                    Desembolsar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($status): ?>
                <div class="card bg-base-100 shadow-md mb-6" x-data="{isBancoMov: false}">
                    <div class="card-body">
                        <h3 class="card-title">Realizar Movimiento</h3>
                        <form id="movimientoFormAnticipo" class="grid grid-cols-1 gap-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="label"><span class="label-text">Tipo de Movimiento <span class="text-xs text-error">*</span></span></label>
                                    <select id="tipo_movimiento_anticipo" name="tipo_movimiento_anticipo" required
                                        class="select select-bordered w-full validator"
                                        data-label="Tipo de movimiento">
                                        <option value="" disabled selected>Seleccione tipo</option>
                                        <?php if (!empty($tiposMovimientos)): ?>
                                            <?php foreach ($tiposMovimientos as $tm): ?>
                                                <option value="<?= htmlspecialchars($tm['id']) ?>" data-tipo="<?= htmlspecialchars(strtoupper($tm['naturaleza'] ?? '')) ?>">
                                                    <?= htmlspecialchars($tm['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">No hay tipos disponibles</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">Fecha <span class="text-xs text-error">*</span></span></label>
                                    <input id="fecha_movimiento_anticipo" name="fecha_movimiento_anticipo" type="date" required
                                        class="input input-bordered w-full validator"
                                        data-label="Fecha"
                                        value="<?= date('Y-m-d') ?>" />
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">Forma de Pago <span class="text-xs text-error">*</span></span></label>
                                    <select id="forma_pago_anticipo" name="forma_pago_anticipo" required @change="isBancoMov = $event.target.value === 'banco'"
                                        class="select select-bordered w-full validator"
                                        data-label="Forma de pago">
                                        <option value="" disabled selected>Seleccione forma</option>
                                        <option value="efectivo">Efectivo</option>
                                        <option value="banco">Cheque</option>
                                    </select>
                                </div>
                            </div>

                            <div x-show="isBancoMov" x-cloak>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="label"><span class="label-text">Banco <span class="text-xs text-error">*</span></span></label>
                                        <select id="banco_id_anticipo" name="banco_id_anticipo" class="select select-bordered w-full validator" data-label="Banco" :required="isBancoMov">
                                            <option value="" disabled selected>Seleccione banco</option>
                                            <?php if (!empty($bancos)): ?>
                                                <?php foreach ($bancos as $b): ?>
                                                    <option value="<?= htmlspecialchars($b['id']) ?>"><?= htmlspecialchars($b['nombre']) ?> - <?= htmlspecialchars($b['numcuenta']) ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="">No hay bancos disponibles</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="label"><span class="label-text">Número de Cheque <span class="text-xs text-error">*</span></span></label>
                                        <input id="num_cheque_anticipo" name="num_cheque_anticipo" type="text" maxlength="50" class="input input-bordered w-full validator" data-label="Número de Cheque"
                                            :required="isBancoMov" placeholder="Ingrese número de cheque" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="label"><span class="label-text">Monto <span class="text-xs text-error">*</span></span></label>
                                    <input id="monto_movimiento_anticipo" name="monto_movimiento_anticipo" type="text"
                                        min="1" step="0.01" required
                                        class="input input-bordered w-full validator decimal-cleave-zen"
                                        data-decimal="2"
                                        data-prefix="Q"
                                        data-label="Monto" value="0" />
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">No. Documento</span></label>
                                    <input id="numdoc_anticipo" name="numdoc_anticipo" type="text"
                                        maxlength="50"
                                        required
                                        data-label="No. Documento"
                                        class="input input-bordered w-full"
                                        placeholder="xx" />
                                </div>
                            </div>

                            <div>
                                <label class="label"><span class="label-text">Concepto</span></label>
                                <textarea id="observaciones_anticipo" name="observaciones_anticipo"
                                    class="textarea textarea-bordered w-full"
                                    data-label="concepto"
                                    rows="3" required maxlength="500"></textarea>
                            </div>

                            <?= $csrf->getTokenField(); ?>

                            <div class="flex justify-end gap-2 mt-2">
                                <button type="button" class="btn btn-outline" onclick="printdiv2('#cuadro', '0')">
                                    Cancelar
                                </button>
                                <?php if ($status): ?>
                                    <button type="button" id="btnGuardarMovimiento" class="btn btn-success"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','fecha_movimiento_anticipo','monto_movimiento_anticipo','numdoc_anticipo','observaciones_anticipo','num_cheque_anticipo'],
                                ['forma_pago_anticipo','banco_id_anticipo','tipo_movimiento_anticipo'],[],'create_anticipo','0',
                                ['<?= htmlspecialchars($secureID->encrypt($accountCode)) ?>'],
                                function(response){
                                reportes([[],[],[],[response.idMovimiento]],'pdf','comprobante_movimientos',0,0,'cuencobra');
                                }, '¿Está seguro de guardar?', 'crud_cuentas_por_cobrarxx')">
                                        Guardar movimiento
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Modal para buscar cuentas -->
        <dialog id="modal_cuentas" class="modal">
            <div class="modal-box w-11/12 max-w-5xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Buscar Cuenta</h3>
                    <form method="dialog">
                        <button class="btn btn-sm btn-circle btn-ghost">✕</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm w-full" id="cuentasTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Identificación</th>
                                <th>Financiado</th>
                                <th>Fecha Inicio</th>
                                <th>Estado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost">Cerrar</button>
                    </form>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <script>
            $(document).ready(function() {
                startValidationGeneric('#movimientoForm');
                startValidationGeneric('#movimientoFormAnticipo');
                const columns = [{
                        data: 'id',
                        className: 'text-left'
                    },
                    {
                        data: 'short_name',
                        className: 'text-left'
                    },
                    {
                        data: 'no_identifica',
                    },
                    {
                        data: 'monto_inicial',
                        render: function(data, type, row) {
                            return `Q ${parseFloat(isNaN(data) ? 0 : data).toFixed(2)}`;
                        }
                    },
                    {
                        data: 'fecha_inicio',
                        render: function(data, type, row) {
                            const date = new Date(data);
                            const day = String(date.getDate()).padStart(2, '0');
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const year = date.getFullYear();
                            return `${day}/${month}/${year}`;
                        }
                    },
                    {
                        data: 'estado',
                        render: function(data, type, row) {
                            let badgeClass = 'badge-info';
                            const estado = data.toUpperCase();
                            if (estado === 'ACTIVA') badgeClass = 'badge-success';
                            if (estado === 'SOLICITADA') badgeClass = 'badge-info';
                            return `<span class="badge ${badgeClass} badge-sm">${estado}</span>`;
                        }
                    },
                    {
                        data: null,
                        title: 'Acción',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<button class="btn btn-soft btn-success" onclick="printdiv2('#cuadro','${row.id}');modal_cuentas.close();">Seleccionar</button>`;
                        }
                    }
                ];
                const table = initServerSideDataTable(
                    '#cuentasTable',
                    'cc_cuentas',
                    columns, {
                        whereExtra: "cue.estado IN ('ACTIVA', 'SOLICITADA')",
                        onError: function(xhr, error, thrown) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al cargar clientes',
                                text: 'Por favor, intente nuevamente'
                            });
                        }
                    }
                );
            });
        </script>
    <?php
        break;
    case 'create_movimientos':

        $accountCode = $_POST["xtra"];

        $showmensaje = false;
        try {

            $database->openConnection();

            // $accountsExistentes = $database->getAllResults(
            //     "SELECT cue.id,cli.short_name,cli.no_identifica,cue.monto_inicial,cue.fecha_inicio 
            //         FROM cc_cuentas cue
            //         INNER JOIN tb_cliente cli ON cli.idcod_cliente=cue.id_cliente
            //         WHERE cue.estado='ACTIVA';"
            // );

            if ($accountCode == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta");
            }
            $cuentaSeleccionada = $database->getAllResults(
                "SELECT cue.id,cli.short_name,cli.no_identifica,cue.monto_inicial,cue.tasa_interes,cue.fecha_inicio,cue.fecha_fin,per.nombre AS periodo_nombre,
                    IFNULL((SELECT SUM(kp) FROM cc_kardex WHERE id_cuenta=cue.id AND tipo='D' AND estado='1' GROUP BY id_cuenta), 0) AS financiado
                    FROM cc_cuentas cue
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cue.id_cliente
                    LEFT JOIN cc_periodos per ON per.id=cue.id_periodo
                    WHERE cue.id=? AND cue.estado='ACTIVA' LIMIT 1",
                [$accountCode]
            );
            if (empty($cuentaSeleccionada)) {
                $showmensaje = true;
                throw new Exception("La cuenta seleccionada no existe o no está activa.");
            }

            $planPagosModel = new PlanPagos($database);
            $planPagosModel->updatePlanPago($accountCode, date('Y-m-d'));

            $pendientesOriginal = $database->getAllResults(
                "SELECT fecven,nocuota,cappag,intpag, mora FROM cc_ppg pp
                    WHERE pp.tipo='original' AND id_cuenta=? AND pp.`status`='X' AND pp.fecven<=?;",
                [$accountCode, date('Y-m-d')]
            );

            if (empty($pendientesOriginal)) {
                $pendientesOriginal = $database->getAllResults(
                    "SELECT fecven,nocuota,cappag,intpag, mora FROM cc_ppg pp
                    WHERE pp.tipo='original' AND id_cuenta=? AND pp.`status`='X' AND pp.fecven>? LIMIT 1;",
                    [$accountCode, date('Y-m-d')]
                );
            }
            $pendientesOtros = $database->getAllResults(
                "SELECT fecven,nocuota,cappag,intpag, mora, mov.nombre,mov.id AS id_movimiento
                    FROM cc_ppg pp
                    LEFT JOIN cc_tipos_movimientos mov ON mov.id=pp.id_tipomov
                    WHERE pp.tipo='otros' AND id_cuenta=? AND pp.`status`='X' AND pp.fecven<=?;",
                [$accountCode, date('Y-m-d')]
            );
            if (empty($pendientesOtros)) {
                $pendientesOtros = $database->getAllResults(
                    "SELECT fecven,nocuota,cappag,intpag, mora, mov.nombre,mov.id AS id_movimiento
                    FROM cc_ppg pp
                    LEFT JOIN cc_tipos_movimientos mov ON mov.id=pp.id_tipomov
                    WHERE pp.tipo='otros' AND id_cuenta=? AND pp.`status`='X' AND pp.fecven>? LIMIT 1;",
                    [$accountCode, date('Y-m-d')]
                );
            }

            // Agrupar pendientes otros por tipo de movimiento
            $pendientesOtrosAgrupados = [];
            if (!empty($pendientesOtros)) {
                foreach ($pendientesOtros as $pend) {
                    $idMov = $pend['id_movimiento'];
                    $nombre = $pend['nombre'];

                    if (!isset($pendientesOtrosAgrupados[$idMov])) {
                        $pendientesOtrosAgrupados[$idMov] = [
                            'id_movimiento' => $idMov,
                            'nombre' => $nombre,
                            'capital' => 0,
                            'interes' => 0,
                            'mora' => 0
                        ];
                    }

                    $pendientesOtrosAgrupados[$idMov]['capital'] += (float)$pend['cappag'];
                    $pendientesOtrosAgrupados[$idMov]['interes'] += (float)$pend['intpag'];
                    $pendientesOtrosAgrupados[$idMov]['mora'] += (float)$pend['mora'];
                }
                $pendientesOtrosAgrupados = array_values($pendientesOtrosAgrupados);
            }

            $bancos = $database->getAllResults(
                "SELECT ctb.id,ban.nombre, ctb.numcuenta 
                    FROM ctb_bancos ctb
                    INNER JOIN tb_bancos ban ON ctb.id_banco=ban.id
                    WHERE ctb.estado=1 AND ban.estado='1';"
            );

            $cuentaCobrarModel = new CuentaCobrar($database, $accountCode);

            $saldoFinanciamientos = $cuentaCobrarModel->getSaldoFinanciamientos(date('Y-m-d'));

            $saldosOtrosCargos = $cuentaCobrarModel->getSaldoOtrosCargos(null, date('Y-m-d'));

            if ($cuentaSeleccionada[0]['fecha_fin'] >= date('Y-m-d')) {
                // $interesAPagar = $cuentaCobrarModel->getInteresAPagar('2025-11-18');
                $interesAPagar = $cuentaCobrarModel->getInteresAPagar(date('Y-m-d'));
            }

            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

        $cuenta = $cuentaSeleccionada[0] ?? null;
    ?>
        <input type="hidden" value="create_movimientos" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-7xl mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Información de la cuenta -->
            <div class="card bg-base-100 shadow-md mb-6">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="card-title">Información de la Cuenta</h2>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm" onclick="modal_cuentas.showModal()">
                                Cambiar Cuenta
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="printdiv2('#cuadro', '0')">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="label"><span class="label-text font-semibold">Cliente</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['short_name'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Identificación</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['no_identifica'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Período</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['periodo_nombre'] ?? 'N/A') ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Limite</span></label>
                            <p class="text-base-content"><?= (($cuenta['monto_inicial'] ?? 0) > 0) ? Moneda::formato($cuenta['monto_inicial']) : '-- sin limite --' ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Total financiado</span></label>
                            <p class="text-base-content">Q <?= htmlspecialchars(number_format($cuenta['financiado'] ?? 0, 2, '.', ',')) ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Tasa de Interés</span></label>
                            <p class="text-base-content"><?= htmlspecialchars($cuenta['tasa_interes'] ?? 0) ?>%</p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Fecha Inicio</span></label>
                            <p class="text-base-content"><?= isset($cuenta['fecha_inicio']) ? setdatefrench($cuenta['fecha_inicio']) : 'N/A' ?></p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Fecha Fin</span></label>
                            <p class="text-base-content"><?= isset($cuenta['fecha_fin']) && !empty($cuenta['fecha_fin']) ? setdatefrench($cuenta['fecha_fin']) : 'N/A' ?></p>
                        </div>

                        <?php if (isset($saldoFinanciamientos)): ?>
                            <div>
                                <label class="label"><span class="label-text font-semibold">Saldo financiamientos</span></label>
                                <p class="text-base-content">Q <?= htmlspecialchars(number_format((float)$saldoFinanciamientos, 2, '.', ',')) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($saldosOtrosCargos) && is_array($saldosOtrosCargos)): ?>
                        <div class="mt-4">
                            <h3 class="card-title text-sm mb-2">Saldos - Otras entregas</h3>
                            <div class="overflow-x-auto">
                                <table class="table table-zebra table-sm w-full">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tipo</th>
                                            <th>Otorgado</th>
                                            <th>Recuperado</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $totalOtrosSaldo = 0;
                                        foreach ($saldosOtrosCargos as $key => $oc):
                                            $id_mov = htmlspecialchars($oc['id_movimiento'] ?? $oc['id'] ?? '');
                                            $tipo = htmlspecialchars($oc['tipo_nombre'] ?? $oc['tipo'] ?? '');
                                            $otorgado = isset($oc['otorgado']) ? (float)$oc['otorgado'] : 0;
                                            $recuperado = isset($oc['recuperado']) ? (float)$oc['recuperado'] : 0;
                                            $saldoOc = isset($oc['saldo']) ? (float)$oc['saldo'] : ($otorgado - $recuperado);
                                            $totalOtrosSaldo += $saldoOc;
                                        ?>
                                            <tr>
                                                <td><?= $key + 1 ?></td>
                                                <td><?= $tipo ?></td>
                                                <td>Q <?= htmlspecialchars(number_format($otorgado, 2, '.', ',')) ?></td>
                                                <td>Q <?= htmlspecialchars(number_format($recuperado, 2, '.', ',')) ?></td>
                                                <td>Q <?= htmlspecialchars(number_format($saldoOc, 2, '.', ',')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-bold bg-base-200">
                                            <td colspan="4" class="text-right">Total Otros cargos (saldo):</td>
                                            <td>Q <?= htmlspecialchars(number_format($totalOtrosSaldo, 2, '.', ',')) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($status): ?>
                <div class="card bg-base-100 shadow-md mb-6" x-data="{isBancoMov: false}">
                    <div class="card-body">
                        <h3 class="card-title">Realizar Pago</h3>
                        <form id="movimientoForm" class="grid grid-cols-1 gap-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="label"><span class="label-text">Fecha <span class="text-xs text-error">*</span></span></label>
                                    <input id="fecha_pago" name="fecha_pago" type="date" required
                                        class="input input-bordered w-full validator"
                                        data-label="Fecha"
                                        value="<?= date('Y-m-d') ?>" />
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">No. Documento</span></label>
                                    <input id="numdoc" name="numdoc" type="text"
                                        maxlength="50"
                                        required
                                        data-label="No. Documento"
                                        class="input input-bordered w-full"
                                        placeholder="xx" />
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">Forma de Pago <span class="text-xs text-error">*</span></span></label>
                                    <select id="forma_pago" name="forma_pago" required @change="isBancoMov = $event.target.value === 'banco'"
                                        class="select select-bordered w-full validator"
                                        data-label="Forma de pago">
                                        <option value="" disabled selected>Seleccione forma</option>
                                        <option value="efectivo">Efectivo</option>
                                        <option value="banco">Boleta de banco</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                                <!-- Izquierda: banco y concepto -->
                                <div class="space-y-4">
                                    <div x-show="isBancoMov" x-cloak>
                                        <div class="card bg-base-100 p-4 shadow-sm">
                                            <h4 class="font-semibold mb-3">Datos Bancarios</h4>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="label"><span class="label-text">Banco <span class="text-xs text-error">*</span></span></label>
                                                    <select id="banco_id" name="banco_id" class="select select-bordered w-full validator" data-label="Banco" :required="isBancoMov">
                                                        <option value="" disabled selected>Seleccione banco</option>
                                                        <?php if (!empty($bancos)): ?>
                                                            <?php foreach ($bancos as $b): ?>
                                                                <option value="<?= htmlspecialchars($b['id']) ?>"><?= htmlspecialchars($b['nombre']) ?> - <?= htmlspecialchars($b['numcuenta']) ?></option>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <option value="">No hay bancos disponibles</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="label"><span class="label-text">Número de Boleta <span class="text-xs text-error">*</span></span></label>
                                                    <input id="num_boleta" name="num_boleta" type="text" maxlength="50" class="input input-bordered w-full validator" data-label="Número de Boleta"
                                                        :required="isBancoMov" placeholder="Ingrese número de boleta" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="label"><span class="label-text">Concepto</span></label>
                                        <textarea id="observaciones" name="observaciones"
                                            class="textarea textarea-bordered w-full"
                                            data-label="concepto"
                                            rows="3" required maxlength="500"></textarea>
                                    </div>
                                </div>
                                <div x-data="formPago()">

                                    <!-- ==============================
                                            BLOQUE FINANCIAMIENTO
                                    ================================= -->
                                    <div class="card bg-base-100 p-4 shadow-sm mb-6">
                                        <h3 class="font-bold mb-2">Pago - Financiamiento</h3>

                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

                                            <div>
                                                <label class="label">Capital</label>
                                                <input type="number" x-model.number="capitalFin" required min="0" step="0.01"
                                                    class="input input-bordered w-full validator" id="monto_capital">
                                            </div>

                                            <div>
                                                <label class="label">Interés</label>
                                                <input type="number" x-model.number="interesFin" required min="0" step="0.01"
                                                    class="input input-bordered w-full validator" id="monto_interes">
                                                <?php if (isset($interesAPagar) && $interesAPagar > 0): ?>
                                                    <div role="alert" class="alert alert-info mt-2">
                                                        <span>Interes calculado al día de hoy: <?= htmlspecialchars(number_format($interesAPagar, 2, '.', ',')) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div>
                                                <label class="label">Mora</label>
                                                <input type="number" x-model.number="moraFin" required min="0" step="0.01"
                                                    class="input input-bordered w-full validator" id="monto_mora">
                                            </div>

                                            <div>
                                                <label class="label">Total</label>
                                                <input type="text" readonly
                                                    class="input input-bordered w-full"
                                                    :value="totalFin.toFixed(2)">
                                            </div>

                                        </div>
                                    </div>

                                    <!-- ==============================
                                                BLOQUE OTRAS ENTREGAS
                                        ================================= -->
                                    <div class="card bg-base-100 p-4 shadow-sm mb-6">
                                        <h3 class="font-bold mb-4">Pago - Otras Entregas</h3>

                                        <template x-for="(item,index) in otros" :key="item.id">

                                            <div class="border rounded-xl p-4 mb-4 bg-base-200">
                                                <h4 class="font-semibold mb-3" x-text="item.nombre"></h4>

                                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">

                                                    <div>
                                                        <label class="label">Capital</label>
                                                        <input type="number" x-model.number="item.capital" required min="0" step="0.01"
                                                            class="input input-bordered w-full validator">
                                                    </div>

                                                    <div>
                                                        <label class="label">Interés</label>
                                                        <input x-show="false" type="number" x-model.number="item.interes" required min="0" step="0.01"
                                                            class="input input-bordered w-full validator">
                                                    </div>

                                                    <div>
                                                        <label class="label">Mora</label>
                                                        <input x-show="false" type="number" x-model.number="item.mora" required min="0" step="0.01"
                                                            class="input input-bordered w-full validator">
                                                    </div>

                                                    <div>
                                                        <label class="label">Total</label>
                                                        <input type="text" readonly
                                                            class="input input-bordered w-full"
                                                            :value="itemTotal(item).toFixed(2)">
                                                    </div>

                                                </div>
                                            </div>

                                        </template>
                                    </div>

                                    <!-- ==============================
                                                TOTAL GENERAL
                                        ================================= -->
                                    <div class="card bg-base-100 p-4 shadow-sm">
                                        <div class="mt-3 flex justify-end">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="modal_original.showModal()">
                                                Ver cuotas
                                            </button>
                                        </div>
                                        <h3 class="font-bold mb-3">TOTAL GENERAL</h3>
                                        <div class="text-2xl font-bold text-primary">
                                            Q <span x-text="totalGeneral().toFixed(2)"></span>
                                        </div>
                                    </div>

                                    <?= $csrf->getTokenField(); ?>

                                    <div class="flex justify-end gap-2 mt-2">
                                        <button type="button" class="btn btn-outline" onclick="printdiv2('#cuadro', '0')">
                                            Cancelar
                                        </button>
                                        <?php if ($status): ?>
                                            <button type="button" id="btnGuardarMovimiento" class="btn btn-success"
                                                @click="savePayment()">
                                                Guardar movimiento
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                </div>

                            </div>


                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <dialog id="modal_original" class="modal">
            <div class="modal-box w-11/12 max-w-5xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Cuotas Pendientes</h3>
                    <form method="dialog">
                        <button class="btn btn-sm btn-circle btn-ghost">✕</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm w-full">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Capital</th>
                                <th>Interes</th>
                                <th>Mora</th>
                                <th>Det</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pendientesOriginal)): ?>
                                <?php foreach ($pendientesOriginal as $pend): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pend['nocuota']) ?></td>
                                        <td><?= htmlspecialchars($pend['fecven']) ?></td>
                                        <td><?= htmlspecialchars(Moneda::formato($pend['cappag'])) ?></td>
                                        <td><?= htmlspecialchars(Moneda::formato($pend['intpag'])) ?></td>
                                        <td><?= htmlspecialchars(Moneda::formato($pend['mora'])) ?></td>
                                        <td>-</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($pendientesOtros)): ?>
                                <?php foreach ($pendientesOtros as $pend): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pend['nocuota']) ?></td>
                                        <td><?= htmlspecialchars($pend['fecven']) ?></td>
                                        <td><?= htmlspecialchars(Moneda::formato($pend['cappag'])) ?></td>
                                        <td><?= htmlspecialchars(Moneda::formato($pend['intpag'])) ?></td>
                                        <td><?= htmlspecialchars(Moneda::formato($pend['mora'])) ?></td>
                                        <td><?= htmlspecialchars($pend['nombre']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost">Cerrar</button>
                    </form>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <!-- Modal para buscar cuentas -->
        <dialog id="modal_cuentas" class="modal">
            <div class="modal-box w-11/12 max-w-5xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Buscar Cuenta</h3>
                    <form method="dialog">
                        <button class="btn btn-sm btn-circle btn-ghost">✕</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm w-full" id="cuentasTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Identificación</th>
                                <th>Financiado</th>
                                <th>Fecha Inicio</th>
                                <th>Estado</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost">Cerrar</button>
                    </form>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <script>
            function formPago() {
                return {
                    capitalFin: <?= isset($pendientesOriginal) ? array_sum(array_column($pendientesOriginal, 'cappag')) : 0 ?>,
                    interesFin: <?= isset($pendientesOriginal) ? array_sum(array_column($pendientesOriginal, 'intpag')) : 0 ?>,
                    moraFin: <?= isset($pendientesOriginal) ? array_sum(array_column($pendientesOriginal, 'mora')) : 0 ?>,
                    otros: <?= json_encode($pendientesOtrosAgrupados ?? []) ?>.map(p => ({
                        id: p.id_movimiento,
                        nombre: p.nombre,
                        capital: Number(p.capital),
                        interes: Number(p.interes),
                        mora: Number(p.mora),
                    })),
                    get totalFin() {
                        return (this.capitalFin || 0) + (this.interesFin || 0) + (this.moraFin || 0);
                    },
                    itemTotal(item) {
                        return (item.capital || 0) + (item.interes || 0) + (item.mora || 0);
                    },
                    sumaOtros() {
                        return this.otros.reduce((acc, item) => acc + this.itemTotal(item), 0);
                    },
                    totalGeneral() {
                        return this.totalFin + this.sumaOtros();
                    },
                    savePayment() {
                        obtiene(['<?= $csrf->getTokenName() ?>', 'fecha_pago', 'monto_capital', 'monto_interes', 'monto_mora', 'numdoc', 'observaciones', 'num_boleta'],
                            ['forma_pago', 'banco_id'], [], 'create_payment', '0', ['<?= htmlspecialchars($secureID->encrypt($accountCode)) ?>', this.otros],
                            function(response) {
                                reportes([
                                    [],
                                    [],
                                    [],
                                    [response.idMovimiento]
                                ], 'pdf', 'comprobante_pago', 0, 0, 'cuencobra');
                            }, '¿Está seguro de realizar este pago?', 'crud_cuentas_por_cobrarxx');
                    }
                }
            }
            $(document).ready(function() {
                startValidationGeneric('#movimientoForm');
                const columns = [{
                        data: 'id',
                        className: 'text-left'
                    },
                    {
                        data: 'short_name',
                        className: 'text-left'
                    },
                    {
                        data: 'no_identifica',
                    },
                    {
                        data: 'monto_inicial',
                        render: function(data, type, row) {
                            return `Q ${parseFloat(isNaN(data) ? 0 : data).toFixed(2)}`;
                        }
                    },
                    {
                        data: 'fecha_inicio',
                        render: function(data, type, row) {
                            const date = new Date(data);
                            const day = String(date.getDate()).padStart(2, '0');
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const year = date.getFullYear();
                            return `${day}/${month}/${year}`;
                        }
                    },
                    {
                        data: 'estado',
                        render: function(data, type, row) {
                            let badgeClass = 'badge-info';
                            const estado = data.toUpperCase();
                            if (estado === 'ACTIVA') badgeClass = 'badge-success';
                            return `<span class="badge ${badgeClass} badge-sm">${estado}</span>`;
                        }
                    },
                    {
                        data: null,
                        title: 'Acción',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<button class="btn btn-soft btn-success" onclick="printdiv2('#cuadro','${row.id}');modal_cuentas.close();">Seleccionar</button>`;
                        }
                    }
                ];
                const table = initServerSideDataTable(
                    '#cuentasTable',
                    'cc_cuentas',
                    columns, {
                        whereExtra: "cue.estado IN ('ACTIVA')",
                        onError: function(xhr, error, thrown) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al cargar clientes',
                                text: 'Por favor, intente nuevamente'
                            });
                        }
                    }
                );
            });
        </script>
    <?php
        break;
    case 'create_unidad_medida':
        $selectedId = $_POST["xtra"] ?? '0';
        $showmensaje = false;
        try {
            $database->openConnection();

            $exists = $database->selectColumns('tb_unidades_medida', ['id', 'nombre', 'simbolo']);

            if ($selectedId !== '0') {
                $unidadSeleccionada = $database->getSingleResult(
                    "SELECT id, nombre, simbolo FROM tb_unidades_medida WHERE id = ?",
                    [$selectedId]
                );
            }

            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
        // $unidadIdEncrypted = $unidadSeleccionada ? htmlspecialchars($secureID->encrypt($unidadSeleccionada['id'])) : '';
    ?>
        <input type="hidden" value="create_unidad_medida" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-6xl mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><?= isset($unidadSeleccionada) ? 'Editar unidad' : 'Nueva unidad de medida' ?></h2>
                        <form id="unidadForm" class="space-y-4">
                            <div>
                                <label class="label" for="unidad_nombre"><span class="label-text">Nombre <span class="text-xs text-error">*</span></span></label>
                                <input id="unidad_nombre" name="unidad_nombre" type="text" class="input input-bordered w-full validator"
                                    data-label="Nombre" required minlength="2" maxlength="50" value="<?= htmlspecialchars(isset($unidadSeleccionada) ? $unidadSeleccionada['nombre'] : '') ?>">
                            </div>
                            <div>
                                <label class="label" for="unidad_simbolo"><span class="label-text">Símbolo</span></label>
                                <input id="unidad_simbolo" name="unidad_simbolo" type="text" class="input input-bordered w-full" maxlength="5" value="<?= htmlspecialchars(isset($unidadSeleccionada) ? $unidadSeleccionada['simbolo'] : '') ?>">
                            </div>
                            <?= $csrf->getTokenField(); ?>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-ghost" onclick="printdiv2('#cuadro', '0');">Cancelar</button>
                                <?php if (isset($unidadSeleccionada) && $status): ?>
                                    <button type="button" class="btn btn-primary"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','unidad_nombre','unidad_simbolo'],[],[],'cc_catalogo_unidades_update','0',['<?= htmlspecialchars($secureID->encrypt($unidadSeleccionada['id'])) ?>'],'NULL','¿Desea actualizar la unidad seleccionada?','crud_cuentas_por_cobrarxx');">
                                        Actualizar
                                    </button>
                                <?php endif; ?>
                                <?php if (!isset($unidadSeleccionada) && $status): ?>
                                    <button type="button" class="btn btn-success"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','unidad_nombre','unidad_simbolo'],[],[],'cc_catalogo_unidades_create','0',[],'NULL','¿Desea crear esta unidad de medida?','crud_cuentas_por_cobrarxx');">
                                        Guardar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Unidades registradas</h2>
                        <div class="overflow-x-auto">
                            <table id="unidadesTable" class="table table-zebra table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Símbolo</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($exists)): ?>
                                        <?php foreach ($exists as $unidad): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($unidad['nombre']) ?></td>
                                                <td><?= htmlspecialchars($unidad['simbolo']) ?></td>
                                                <td class="text-center">
                                                    <button class="btn btn-soft btn-primary btn-sm"
                                                        onclick="printdiv2('#cuadro','<?= $unidad['id'] ?>');">
                                                        Editar
                                                    </button>
                                                    <button class="btn btn-soft btn-error btn-sm"
                                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>'],[],[],'cc_catalogo_unidades_delete','0',['<?= htmlspecialchars($secureID->encrypt($unidad['id'])) ?>'],'NULL','¿Desea eliminar la unidad?','crud_cuentas_por_cobrarxx');">
                                                        Eliminar
                                                    </button>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4">No hay unidades de medida registradas.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                startValidationGeneric('#unidadForm');
            });
        </script>
    <?php
        break;
    case 'create_productos':
        $selectedId = $_POST["xtra"] ?? '0';
        $showmensaje = false;
        try {
            $database->openConnection();

            $unidadesMedida = $database->selectColumns('tb_unidades_medida', ['id', 'nombre', 'simbolo']);
            $productos = $database->getAllResults(
                "SELECT p.id, p.nombre, p.descripcion, p.estado 
                FROM cc_productos p WHERE p.estado = '1'
                ORDER BY p.nombre ASC"
            );

            if ($selectedId !== '0') {
                $productoSeleccionado = $database->getSingleResult(
                    "SELECT id, nombre, descripcion, estado,id_nomenclatura FROM cc_productos WHERE id = ?",
                    [$selectedId]
                );

                if ($productoSeleccionado) {
                    $preciosProducto = $database->getAllResults(
                        "SELECT pp.id, pp.id_medida, pp.precio, um.nombre as unidad_nombre, um.simbolo, pp.nombre
                        FROM cc_productos_precios pp
                        INNER JOIN tb_unidades_medida um ON pp.id_medida = um.id
                        WHERE pp.id_producto = ?",
                        [$selectedId]
                    );
                }
            }

            $nomenclatura = $database->selectColumns("ctb_nomenclatura", ['id', 'ccodcta', 'cdescrip', 'tipo'], 'estado = 1', orderBy: 'ccodcta');

            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="hidden" value="create_productos" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-7xl mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Formulario de producto -->
                <div class="lg:col-span-2 card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><?= isset($productoSeleccionado) ? 'Editar producto' : 'Nuevo producto' ?></h2>

                        <form id="productoForm" class="space-y-4" x-data="preciosManager()">
                            <div>
                                <label class="label" for="producto_nombre">
                                    <span class="label-text">Nombre del producto <span class="text-xs text-error">*</span></span>
                                </label>
                                <input id="producto_nombre" name="producto_nombre" type="text"
                                    class="input input-bordered w-full validator"
                                    data-label="Nombre" required minlength="2" maxlength="100"
                                    value="<?= htmlspecialchars(isset($productoSeleccionado) ? $productoSeleccionado['nombre'] : '') ?>">
                            </div>

                            <div>
                                <label class="label" for="producto_nomenclatura">
                                    <span class="label-text">Nomenclatura contable <span class="text-xs text-error">*</span></span>
                                </label>
                                <select id="producto_nomenclatura" name="producto_nomenclatura"
                                    class="select select-bordered w-full validator"
                                    data-label="Nomenclatura contable" required>
                                    <option value="" selected disabled>Seleccione nomenclatura</option>
                                    <?php
                                    $currentGroupOpen = false;
                                    if (!empty($nomenclatura)):
                                        foreach ($nomenclatura as $nom):
                                            $level = (int) (strlen($nom['ccodcta']) / 2);
                                            $indent = str_repeat('&nbsp;', max(0, $level - 1) * 4);

                                            if (($nom['tipo'] ?? '') === 'R') {
                                                if ($currentGroupOpen) {
                                                    echo '</optgroup>';
                                                }
                                                $label = $indent . htmlspecialchars($nom['cdescrip']);
                                                echo '<optgroup label="' . $label . '">';
                                                $currentGroupOpen = true;
                                            } else {
                                                $selected = (isset($productoSeleccionado) && (string)$productoSeleccionado['id_nomenclatura'] === (string)$nom['id']) ? ' selected' : '';
                                                $text = $indent . htmlspecialchars($nom['ccodcta'] . ' - ' . $nom['cdescrip']);
                                                echo '<option value="' . htmlspecialchars($nom['id']) . '"' . $selected . '>' . $text . '</option>';
                                            }
                                        endforeach;

                                        if ($currentGroupOpen) :
                                            echo '</optgroup>';
                                        endif;
                                    else:
                                        echo '<option value="">No hay cuentas contables disponibles</option>';
                                    endif;
                                    ?>
                                </select>
                            </div>

                            <div>
                                <label class="label" for="producto_descripcion">
                                    <span class="label-text">Descripción</span>
                                </label>
                                <textarea id="producto_descripcion" name="producto_descripcion"
                                    class="textarea textarea-bordered w-full"
                                    rows="3" maxlength="255"><?= htmlspecialchars(isset($productoSeleccionado) ? $productoSeleccionado['descripcion'] : '') ?></textarea>
                            </div>

                            <!-- Gestor de precios -->
                            <div class="divider">Precios por unidad de medida</div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-4">
                                <div class="md:col-span-1">
                                    <label class="label">
                                        <span class="label-text">Unidad de medida</span>
                                    </label>
                                    <select x-model="nuevoPrecio.id_medida"
                                        @change="nuevoPrecio.unidad_nombre = $event.target.options[$event.target.selectedIndex].text"
                                        class="select select-bordered w-full validator"
                                        data-label="Unidad de medida"
                                        required id="precio_unidad">
                                        <option value="" disabled>Seleccione unidad</option>
                                        <?php if (!empty($unidadesMedida)): ?>
                                            <?php foreach ($unidadesMedida as $um): ?>
                                                <option value="<?= htmlspecialchars($um['id']) ?>">
                                                    <?= htmlspecialchars($um['nombre']) ?> <?= !empty($um['simbolo']) ? '(' . htmlspecialchars($um['simbolo']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">No hay unidades disponibles</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-1">
                                    <label class="label">
                                        <span class="label-text">Nombre del precio</span>
                                    </label>
                                    <input x-model="nuevoPrecio.nombre"
                                        type="text"
                                        maxlength="100"
                                        required
                                        class="input input-bordered w-full validator"
                                        data-label="Nombre del precio" id="precio_nombre" />
                                </div>

                                <div class="md:col-span-1">
                                    <label class="label">
                                        <span class="label-text">Precio</span>
                                    </label>
                                    <input x-model.number="nuevoPrecio.precio"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        class="input input-bordered w-full validator"
                                        data-label="Precio" id="precio_valor" />
                                </div>

                                <div class="md:col-span-1">
                                    <button type="button"
                                        @click="agregarPrecio"
                                        class="btn btn-primary w-full">
                                        <i class="fas fa-plus mr-2"></i> Agregar precio
                                    </button>
                                </div>
                            </div>

                            <!-- Tabla de precios -->
                            <div x-show="precios.length > 0" class="mt-4">
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra table-sm w-full">
                                        <thead>
                                            <tr>
                                                <th>Unidad</th>
                                                <th>Nombre</th>
                                                <th>Precio</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(precio, index) in precios" :key="index">
                                                <tr>
                                                    <td x-text="precio.unidad_nombre"></td>
                                                    <td x-text="precio.nombre"></td>
                                                    <td x-text="'Q ' + Number(precio.precio).toFixed(2)"></td>
                                                    <td class="text-center">
                                                        <button type="button"
                                                            @click="eliminarPrecio(index)"
                                                            class="btn btn-sm btn-ghost btn-error">
                                                            Eliminar
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                        <!-- <tfoot>
                                            <tr>
                                                <td colspan="3" class="text-right font-bold">Total precios:</td>
                                                <td class="font-bold" x-text="'Q ' + calcularTotal()"></td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot> -->
                                    </table>
                                </div>
                            </div>

                            <div x-show="precios.length === 0" class="alert alert-info">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>No hay precios agregados. Agregue al menos un precio.</span>
                            </div>

                            <input type="hidden" id="precios_json" name="precios" :value="JSON.stringify(precios)">

                            <?= $csrf->getTokenField(); ?>

                            <div class="flex justify-end gap-2 mt-4">
                                <button type="button" class="btn btn-ghost" onclick="printdiv2('#cuadro', '0');">
                                    Cancelar
                                </button>
                                <?php if (isset($productoSeleccionado) && $status): ?>
                                    <button type="button" class="btn btn-primary"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','producto_nombre','producto_descripcion'],['producto_nomenclatura'],[],'cc_productos_update','0',['<?= htmlspecialchars($secureID->encrypt($productoSeleccionado['id'])) ?>',getAlpineData(`[x-data='preciosManager()']`, 'precios')],'NULL','¿Desea actualizar el producto seleccionado?','crud_cuentas_por_cobrarxx');">
                                        Actualizar
                                    </button>
                                <?php endif; ?>
                                <?php if (!isset($productoSeleccionado) && $status): ?>
                                    <button type="button" class="btn btn-success"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','producto_nombre','producto_descripcion'],['producto_nomenclatura'],[],'cc_productos_create','0',[getAlpineData(`[x-data='preciosManager()']`, 'precios')],'NULL','¿Desea crear este producto?','crud_cuentas_por_cobrarxx');">
                                        Guardar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de productos -->
                <div class="lg:col-span-1 card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Productos registrados</h2>
                        <div class="overflow-x-auto">
                            <table class="table table-zebra table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($productos)): ?>
                                        <?php foreach ($productos as $prod): ?>
                                            <tr>
                                                <td class="text-xs"><?= htmlspecialchars($prod['nombre']) ?></td>
                                                <td>
                                                    <?php if ($prod['estado'] == '1'): ?>
                                                        <span class="badge badge-success badge-sm">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-error badge-sm">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-soft btn-primary btn-xs"
                                                        onclick="printdiv2('#cuadro','<?= $prod['id'] ?>');">
                                                        Editar
                                                    </button>
                                                    <button class="btn btn-soft btn-error btn-xs"
                                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>'],[],[],'cc_productos_delete','0',['<?= htmlspecialchars($secureID->encrypt($prod['id'])) ?>'],'NULL','¿Desea eliminar el producto?','crud_cuentas_por_cobrarxx');">
                                                        Eliminar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4">No hay productos registrados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                startValidationGeneric('#productoForm');
                initTomSelect('#producto_nomenclatura');
            });

            function preciosManager() {
                return {
                    precios: <?= isset($preciosProducto) ? json_encode($preciosProducto) : '[]' ?>,
                    nuevoPrecio: {
                        id_medida: '',
                        unidad_nombre: '',
                        nombre: '',
                        precio: 0
                    },

                    agregarPrecio() {
                        const validacion = validarCamposGeneric(['precio_valor', 'precio_nombre'], ['precio_unidad'], []);
                        if (!validacion.esValido) {
                            return;
                        }

                        // // Verificar que no exista ya esa unidad
                        // const existe = this.precios.find(p => p.id_medida == this.nuevoPrecio.id_medida);
                        // if (existe) {
                        //     Swal.fire({
                        //         icon: 'warning',
                        //         title: 'Advertencia',
                        //         text: 'Ya existe un precio para esta unidad de medida'
                        //     });
                        //     return;
                        // }

                        this.precios.push({
                            id_medida: this.nuevoPrecio.id_medida,
                            unidad_nombre: this.nuevoPrecio.unidad_nombre,
                            nombre: this.nuevoPrecio.nombre,
                            precio: parseFloat(this.nuevoPrecio.precio)
                        });

                        this.limpiarFormulario();
                    },

                    eliminarPrecio(index) {
                        this.precios.splice(index, 1);
                    },

                    limpiarFormulario() {
                        this.nuevoPrecio = {
                            id_medida: '',
                            unidad_nombre: '',
                            nombre: '',
                            precio: 0
                        };
                    }
                }
            }
        </script>
    <?php
        break;
    case 'create_descuentos':
        $selectedId = $_POST["xtra"] ?? '0';
        $showmensaje = false;
        try {
            $database->openConnection();

            $nomenclaturas = $database->getAllResults(
                "SELECT id, ccodcta as codigo, cdescrip as nombre FROM ctb_nomenclatura WHERE estado = '1' ORDER BY ccodcta ASC"
            );

            $descuentos = $database->getAllResults(
                "SELECT d.id, d.nombre, d.categoria, d.monto, d.id_nomenclatura, n.cdescrip as nomenclatura_nombre
                FROM cc_descuentos d
                LEFT JOIN ctb_nomenclatura n ON d.id_nomenclatura = n.id
                ORDER BY d.nombre ASC"
            );

            if ($selectedId !== '0') {
                $descuentoSeleccionado = $database->getSingleResult(
                    "SELECT id, nombre, categoria, monto, id_nomenclatura FROM cc_descuentos WHERE id = ?",
                    [$selectedId]
                );
            }

            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="hidden" value="create_descuentos" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-7xl mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Formulario de descuento -->
                <div class="lg:col-span-2 card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4"><?= isset($descuentoSeleccionado) ? 'Editar tipo de descuento' : 'Nuevo tipo de descuento' ?></h2>

                        <form id="descuentoForm" class="space-y-4"
                            x-data="{ 
                                categoria: '<?= isset($descuentoSeleccionado) ? $descuentoSeleccionado['categoria'] : '' ?>', 
                                esOtros() { return this.categoria === 'otros' } 
                            }">
                            <div>
                                <label class="label" for="descuento_nombre">
                                    <span class="label-text">Nombre del descuento <span class="text-xs text-error">*</span></span>
                                </label>
                                <input id="descuento_nombre" name="descuento_nombre" type="text"
                                    class="input input-bordered w-full validator"
                                    data-label="Nombre" required minlength="2" maxlength="100"
                                    value="<?= htmlspecialchars(isset($descuentoSeleccionado) ? $descuentoSeleccionado['nombre'] : '') ?>">
                            </div>

                            <div>
                                <label class="label" for="descuento_categoria">
                                    <span class="label-text">Categoría <span class="text-xs text-error">*</span></span>
                                </label>
                                <select id="descuento_categoria" name="descuento_categoria"
                                    x-model="categoria"
                                    class="select select-bordered w-full validator"
                                    data-label="Categoría" required>
                                    <option value="" disabled>Seleccione categoría</option>
                                    <option value="financiamientos" <?= isset($descuentoSeleccionado) && $descuentoSeleccionado['categoria'] === 'financiamientos' ? 'selected' : '' ?>>Financiamientos</option>
                                    <option value="prestamos" <?= isset($descuentoSeleccionado) && $descuentoSeleccionado['categoria'] === 'prestamos' ? 'selected' : '' ?>>Préstamos</option>
                                    <option value="ahorros" <?= isset($descuentoSeleccionado) && $descuentoSeleccionado['categoria'] === 'ahorros' ? 'selected' : '' ?>>Ahorros</option>
                                    <option value="aportaciones" <?= isset($descuentoSeleccionado) && $descuentoSeleccionado['categoria'] === 'aportaciones' ? 'selected' : '' ?>>Aportaciones</option>
                                    <option value="aux_postumo" <?= isset($descuentoSeleccionado) && $descuentoSeleccionado['categoria'] === 'aux_postumo' ? 'selected' : '' ?>>Auxilio póstumo</option>
                                    <option value="otros" <?= isset($descuentoSeleccionado) && $descuentoSeleccionado['categoria'] === 'otros' ? 'selected' : '' ?>>Otros</option>
                                </select>
                            </div>

                            <!-- Nomenclatura (solo visible si categoría es "otros") -->
                            <div x-show="esOtros()" x-cloak>
                                <label class="label" for="descuento_nomenclatura">
                                    <span class="label-text">Nomenclatura contable <span class="text-xs text-error">*</span></span>
                                </label>
                                <select id="descuento_nomenclatura" name="descuento_nomenclatura"
                                    class="select select-bordered w-full validator"
                                    data-label="Nomenclatura"
                                    :required="esOtros()">
                                    <option value="" disabled selected>Seleccione nomenclatura</option>
                                    <?php if (!empty($nomenclaturas)): ?>
                                        <?php foreach ($nomenclaturas as $nom): ?>
                                            <option value="<?= htmlspecialchars($nom['id']) ?>"
                                                <?= isset($descuentoSeleccionado) && $descuentoSeleccionado['id_nomenclatura'] == $nom['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($nom['codigo']) ?> - <?= htmlspecialchars($nom['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No hay nomenclaturas disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Monto (solo visible si categoría es "otros") -->
                            <div x-show="esOtros()" x-cloak>
                                <label class="label" for="descuento_monto">
                                    <span class="label-text">Monto <span class="text-xs text-error">*</span></span>
                                </label>
                                <input id="descuento_monto" name="descuento_monto" type="number"
                                    min="0.01" step="0.01"
                                    class="input input-bordered w-full validator"
                                    data-label="Monto"
                                    :required="esOtros()"
                                    value="<?= isset($descuentoSeleccionado) && $descuentoSeleccionado['monto'] ? htmlspecialchars($descuentoSeleccionado['monto']) : '' ?>">
                            </div>

                            <div role="alert" class="alert alert-info" x-show="!esOtros()">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>Para categorías predefinidas no se requiere nomenclatura ni monto fijo.</span>
                            </div>

                            <?= $csrf->getTokenField(); ?>

                            <div class="flex justify-end gap-2 mt-4">
                                <button type="button" class="btn btn-ghost" onclick="printdiv2('#cuadro', '0');">
                                    Cancelar
                                </button>
                                <?php if (isset($descuentoSeleccionado) && $status): ?>
                                    <button type="button" class="btn btn-primary"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','descuento_nombre','descuento_monto'],['descuento_categoria','descuento_nomenclatura'],[],'cc_descuentos_update','0',['<?= htmlspecialchars($secureID->encrypt($descuentoSeleccionado['id'])) ?>'],'NULL','¿Desea actualizar el tipo de descuento seleccionado?','crud_cuentas_por_cobrarxx');">
                                        Actualizar
                                    </button>
                                <?php endif; ?>
                                <?php if (!isset($descuentoSeleccionado) && $status): ?>
                                    <button type="button" class="btn btn-success"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','descuento_nombre','descuento_monto'],['descuento_categoria','descuento_nomenclatura'],[],'cc_descuentos_create','0',[],'NULL','¿Desea crear este tipo de descuento?','crud_cuentas_por_cobrarxx');">
                                        Guardar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de descuentos -->
                <div class="lg:col-span-1 card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Tipos de descuentos</h2>
                        <div class="overflow-x-auto">
                            <table class="table table-zebra table-sm w-full">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Categoría</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($descuentos)): ?>
                                        <?php foreach ($descuentos as $desc): ?>
                                            <tr>
                                                <td class="text-xs"><?= htmlspecialchars($desc['nombre']) ?></td>
                                                <td class="text-xs">
                                                    <span class="badge badge-sm 
                                                        <?php
                                                        switch ($desc['categoria']) {
                                                            case 'financiamientos':
                                                                echo 'badge-primary';
                                                                break;
                                                            case 'prestamos':
                                                                echo 'badge-secondary';
                                                                break;
                                                            case 'ahorros':
                                                                echo 'badge-accent';
                                                                break;
                                                            case 'aportaciones':
                                                                echo 'badge-info';
                                                                break;
                                                            case 'aux_postumo':
                                                                echo 'badge-success';
                                                                break;
                                                            case 'otros':
                                                                echo 'badge-warning';
                                                                break;
                                                            default:
                                                                echo 'badge-ghost';
                                                        }
                                                        ?>">
                                                        <?= htmlspecialchars(ucfirst($desc['categoria'])) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="flex flex-col gap-1">
                                                        <button class="btn btn-soft btn-primary btn-xs"
                                                            onclick="printdiv2('#cuadro','<?= $desc['id'] ?>');">
                                                            Editar
                                                        </button>
                                                        <button class="btn btn-soft btn-error btn-xs"
                                                            onclick="obtiene(['<?= $csrf->getTokenName() ?>'],[],[],'cc_descuentos_delete','0',['<?= htmlspecialchars($secureID->encrypt($desc['id'])) ?>'],'NULL','¿Desea eliminar este tipo de descuento?','crud_cuentas_por_cobrarxx');">
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4">No hay tipos de descuentos registrados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                startValidationGeneric('#descuentoForm');
                initTomSelect('#descuento_nomenclatura');
            });
        </script>
    <?php
        break;
    case 'create_compras':
        $selectedId = $_POST["xtra"] ?? '0';
        $showmensaje = false;
        try {
            $database->openConnection();

            // Obtener productos con sus precios
            $productos = $database->getAllResults(
                "SELECT p.id, p.nombre as producto_nombre, pp.id as precio_id, pp.nombre as precio_nombre, 
                        pp.precio, um.nombre as unidad_nombre, um.simbolo
                FROM cc_productos p
                INNER JOIN cc_productos_precios pp ON p.id = pp.id_producto
                INNER JOIN tb_unidades_medida um ON pp.id_medida = um.id
                WHERE p.estado = '1'
                ORDER BY p.nombre ASC, pp.nombre ASC"
            );

            // Obtener tipos de descuentos
            $descuentos = $database->getAllResults(
                "SELECT id, nombre, categoria, monto, id_nomenclatura 
                FROM cc_descuentos 
                ORDER BY categoria ASC, nombre ASC"
            );

            // Obtener bancos
            $bancos = $database->getAllResults(
                "SELECT ctb.id, ban.nombre, ctb.numcuenta 
                FROM ctb_bancos ctb
                INNER JOIN tb_bancos ban ON ctb.id_banco = ban.id
                WHERE ctb.estado = 1 AND ban.estado = '1'
                ORDER BY ban.nombre ASC"
            );

            if ($selectedId !== '0') {
                $clienteSeleccionado = $database->getAllResults(
                    "SELECT idcod_cliente, short_name, no_identifica
                            FROM tb_cliente cli 
                            LEFT JOIN cc_grupos_clientes gc ON cli.idcod_cliente = gc.id_cliente
                            WHERE cli.idcod_cliente = ? AND cli.estado = 1  LIMIT 1",
                    [$selectedId]
                );
                if (empty($clienteSeleccionado)) {
                    $showmensaje = true;
                    throw new Exception("El cliente seleccionado no existe o no está activo.");
                }

                $clienteSeleccionado = $clienteSeleccionado[0];


                if (!empty($descuentos) && in_array('financiamientos', array_column($descuentos, 'categoria'))) {
                    $accountsFinanciamientos = $database->getAllResults(
                        "SELECT cue.id,IFNULL(ppg.sum_cappag,0) saldo, IFNULL(ppg.sum_intpag,0) interes, IFNULL(ppg.sum_mora,0) mora,cue.fecha_inicio, 
                            per.nombre nombrePeriodo,cue.fecha_fin
                            FROM cc_cuentas cue
                            INNER JOIN cc_periodos per ON per.id=cue.id_periodo
                            LEFT JOIN (
                                    SELECT id_cuenta, SUM(cappag) AS sum_cappag, SUM(intpag) AS sum_intpag, SUM(mora) sum_mora
                                    FROM cc_ppg WHERE `status`='X' AND tipo='original'
                                    GROUP BY id_cuenta
                                ) AS ppg ON ppg.id_cuenta = cue.id
                            WHERE cue.estado='ACTIVA' AND cue.id_cliente = ?;",
                        [$selectedId]
                    );

                    foreach ($accountsFinanciamientos as $key => $currentAccount) {
                        if ($currentAccount['fecha_fin'] > date('Y-m-d')) {
                            $cuentaCobrarModel = new CuentaCobrar($database, $currentAccount['id']);

                            $accountsFinanciamientos[$key]['saldo'] = $cuentaCobrarModel->getSaldoFinanciamientos(date('Y-m-d'));
                            $accountsFinanciamientos[$key]['interes'] = $cuentaCobrarModel->getInteresAPagar(date('Y-m-d'));
                        }

                        $accountsFinanciamientos[$key]['saldo'] = round($accountsFinanciamientos[$key]['saldo'], 2);
                        $accountsFinanciamientos[$key]['interes'] = round($accountsFinanciamientos[$key]['interes'], 2);
                    }

                    $otrosFinanciamientos = $database->getAllResults(
                        "SELECT mov.id idOtros, SUM(cappag) cappag, SUM(intpag) intpag,SUM(mora) mora,mov.nombre,cue.tasa_interes, per.nombre nombrePeriodo,cue.id idCuenta  
                            FROM cc_ppg cp 
                            INNER JOIN cc_cuentas cue ON cue.id=cp.id_cuenta
                            INNER JOIN cc_periodos per ON per.id=cue.id_periodo
                            INNER JOIN cc_tipos_movimientos mov ON mov.id=cp.id_tipomov
                            WHERE `status`='X' AND cp.tipo='otros' AND cue.estado='ACTIVA' AND cue.id_cliente = ? 
                            GROUP BY cue.id, mov.id;",
                        [$selectedId]
                    );
                }

                if (!empty($descuentos) && in_array('prestamos', array_column($descuentos, 'categoria'))) {
                    $accountsPrestamos = $database->getAllResults(
                        "SELECT crem.CCODCTA,GREATEST(IFNULL(cre_saldo_credito(crem.CCODCTA, ?),0),0) AS saldo, prod.nombre as descripcion  
                            FROM cremcre_meta crem
                            INNER JOIN cre_productos prod ON prod.id = crem.CCODPRD
                            WHERE crem.CodCli=? AND crem.Cestado='F';",
                        [$hoy, $selectedId]
                    );
                }

                if (!empty($descuentos) && in_array('ahorros', array_column($descuentos, 'categoria'))) {
                    $accountsAhorros = $database->getAllResults(
                        "SELECT aht.nombre AS descripcion, aho.ccodaho AS cuenta, IFNULL(calcular_saldo_aho_tipcuenta(aho.ccodaho,?),0)  AS saldo
                            FROM ahomcta aho
                            INNER JOIN ahomtip aht ON aht.ccodtip = SUBSTR(aho.ccodaho, 7, 2)
                            WHERE aho.estado = 'A' AND aho.ccodcli =?;",
                        [$hoy, $selectedId]
                    );
                }

                if (!empty($descuentos) && in_array('aportaciones', array_column($descuentos, 'categoria'))) {
                    $accountsAportaciones = $database->getAllResults(
                        "SELECT aht.nombre AS descripcion, aho.ccodaport AS cuenta, IFNULL(calcular_saldo_apr_tipcuenta(aho.ccodaport,?),0)  AS saldo
                            FROM aprcta aho
                            INNER JOIN aprtip aht ON aht.ccodtip = SUBSTR(aho.ccodaport, 7, 2)
                            WHERE aho.estado = 'A' AND aho.ccodcli =?;",
                        [$hoy, $selectedId]
                    );
                }
            }

            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="hidden" value="create_compras" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-7xl mx-auto" x-data="comprasManager()">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-6">
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6 mt-1" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <div class="prose m-0">
                            <p class="m-0"><?= htmlspecialchars($mensaje) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cliente / Acciones -->
            <div class="card bg-base-100 shadow mb-6">
                <div class="card-body grid gap-4 md:grid-cols-3 items-center">

                    <div class="flex items-center gap-4 md:col-span-2">
                        <div class="w-full">

                            <!-- Nombre -->
                            <div class="mb-2">
                                <span class="text-xs font-semibold text-base-content/60 uppercase">
                                    Nombre del Cliente
                                </span>
                                <h3 class="text-lg font-semibold m-0 leading-tight text-base-content">
                                    <?= htmlspecialchars($clienteSeleccionado['short_name'] ?? 'Cliente no seleccionado') ?>
                                </h3>
                            </div>

                            <!-- Código -->
                            <div class="mb-2">
                                <span class="text-xs font-semibold text-base-content/60 uppercase">
                                    Código
                                </span>
                                <p class="text-sm text-base-content/80 m-0">
                                    <?= htmlspecialchars($clienteSeleccionado['idcod_cliente'] ?? '') ?>
                                </p>
                            </div>

                            <!-- Identificación -->
                            <div>
                                <span class="text-xs font-semibold text-base-content/60 uppercase">
                                    Identificación
                                </span>
                                <p class="text-xs text-base-content/80 mt-1">
                                    <?php if (!empty($clienteSeleccionado['no_identifica'])): ?>
                                        <?= htmlspecialchars($clienteSeleccionado['no_identifica']) ?>
                                    <?php else: ?>
                                        <span class="text-base-content/50">No disponible</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn btn-primary btn-sm" onclick="my_modal_1.showModal()">Buscar cliente</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="printdiv2('#cuadro', '0')">Cancelar</button>
                    </div>

                    <input type="hidden" id="compra_cliente" name="compra_cliente"
                        value="<?= htmlspecialchars($clienteSeleccionado['idcod_cliente'] ?? '') ?>">

                </div>
            </div>



            <!-- Formulario principal -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="card bg-base-100 shadow">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="card-title text-lg m-0"><?= isset($compraSeleccionada) ? 'Editar' : 'Nueva' ?> Compra</h2>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="printdiv2('#cuadro', '0')">Cancelar</button>
                            </div>

                            <form id="compraForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="label"><span class="label-text">Fecha <span class="text-xs text-error">*</span></span></label>
                                    <input id="compra_fecha" name="compra_fecha" type="date" required
                                        class="input input-bordered w-full validator" data-label="Fecha"
                                        value="<?= isset($compraSeleccionada) ? $compraSeleccionada['fecha'] : date('Y-m-d') ?>" />
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">No. Documento</span></label>
                                    <input id="compra_numdoc" name="compra_numdoc" type="text" maxlength="50"
                                        class="input input-bordered w-full" data-label="No. Documento"
                                        value="<?= isset($compraSeleccionada) ? htmlspecialchars($compraSeleccionada['numdoc']) : '' ?>" />
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">Forma de Pago <span class="text-xs text-error">*</span></span></label>
                                    <select id="compra_forma_pago" name="compra_forma_pago" required
                                        x-model="formaPago"
                                        class="select select-bordered w-full validator" data-label="Forma de pago">
                                        <option value="" disabled>Seleccione forma</option>
                                        <option value="efectivo" <?= isset($compraSeleccionada) && $compraSeleccionada['forma_pago'] === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                        <option value="banco" <?= isset($compraSeleccionada) && $compraSeleccionada['forma_pago'] === 'banco' ? 'selected' : '' ?>>Cheque</option>
                                    </select>
                                </div>

                                <div x-show="formaPago === 'banco'" x-cloak>
                                    <label class="label"><span class="label-text">Banco <span class="text-xs text-error">*</span></span></label>
                                    <select id="compra_banco" name="compra_banco"
                                        class="select select-bordered w-full validator"
                                        data-label="Banco"
                                        :required="formaPago === 'banco'">
                                        <option value="" disabled selected>Seleccione banco</option>
                                        <?php if (!empty($bancos)): ?>
                                            <?php foreach ($bancos as $b): ?>
                                                <option value="<?= htmlspecialchars($b['id']) ?>"
                                                    <?= isset($compraSeleccionada) && $compraSeleccionada['id_ctbbancos'] == $b['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($b['nombre']) ?> - <?= htmlspecialchars($b['numcuenta']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div x-show="formaPago === 'banco'" x-cloak>
                                    <label class="label"><span class="label-text">Número de Cheque <span class="text-xs text-error">*</span></span></label>
                                    <input id="compra_doc_banco" name="compra_doc_banco" type="text" maxlength="50"
                                        class="input input-bordered w-full validator"
                                        data-label="Número de documento"
                                        :required="formaPago === 'banco'"
                                        value="<?= isset($compraSeleccionada) ? htmlspecialchars($compraSeleccionada['doc_banco']) : '' ?>" />
                                </div>

                                <div class="md:col-span-3">
                                    <label class="label"><span class="label-text">Concepto</span></label>
                                    <textarea id="compra_concepto" name="compra_concepto" rows="2" maxlength="255"
                                        class="textarea textarea-bordered w-full"><?= isset($compraSeleccionada) ? htmlspecialchars($compraSeleccionada['concepto']) : '' ?></textarea>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Productos -->
                    <div class="card bg-base-100 shadow">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="card-title m-0">Productos</h3>
                                <p class="text-sm text-base-content/70 text-right">Agregue productos a la compra</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mb-4">
                                <div class="md:col-span-5">
                                    <label class="label"><span class="label-text">Producto</span></label>
                                    <select x-model="nuevoDetalle.id_producto"
                                        @change="seleccionarProducto($event)"
                                        class="select select-bordered w-full validator"
                                        data-label="Producto"
                                        required id="detalle_producto">
                                        <option value="" disabled>Seleccione producto</option>
                                        <?php if (!empty($productos)): ?>
                                            <?php foreach ($productos as $prod): ?>
                                                <option value="<?= htmlspecialchars($prod['precio_id']) ?>"
                                                    data-producto-id="<?= htmlspecialchars($prod['id']) ?>"
                                                    data-nombre="<?= htmlspecialchars($prod['producto_nombre']) ?>"
                                                    data-precio="<?= htmlspecialchars($prod['precio']) ?>"
                                                    data-medida="<?= htmlspecialchars($prod['unidad_nombre']) ?>"
                                                    data-precio-nombre="<?= htmlspecialchars($prod['precio_nombre']) ?>">
                                                    <?= htmlspecialchars($prod['producto_nombre']) ?> - <?= htmlspecialchars($prod['precio_nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="label"><span class="label-text">Cantidad</span></label>
                                    <input x-model.number="nuevoDetalle.cantidad"
                                        type="number" min="0.01" step="0.01" required
                                        class="input input-bordered w-full validator"
                                        data-label="Cantidad" id="detalle_cantidad" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="label"><span class="label-text">Precio Unit.</span></label>
                                    <input x-model.number="nuevoDetalle.precio_unitario"
                                        type="number" min="0.01" step="0.01" required
                                        class="input input-bordered w-full validator"
                                        data-label="Precio unitario" id="detalle_precio" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="label"><span class="label-text">Subtotal</span></label>
                                    <input :value="(nuevoDetalle.cantidad * nuevoDetalle.precio_unitario).toFixed(2)"
                                        type="text" readonly
                                        class="input input-bordered w-full bg-base-200" />
                                </div>

                                <div class="md:col-span-1">
                                    <button type="button" @click="agregarDetalle"
                                        class="btn btn-primary w-full" aria-label="Agregar detalle">
                                        <span class="text-lg font-bold">+</span>
                                    </button>
                                </div>
                            </div>

                            <div x-show="detalles.length > 0" class="overflow-x-auto">
                                <table class="table table-zebra table-sm w-full">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Medida</th>
                                            <th class="text-right">Cantidad</th>
                                            <th class="text-right">Precio Unit.</th>
                                            <th class="text-right">Subtotal</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(detalle, index) in detalles" :key="index">
                                            <tr>
                                                <td x-text="detalle.producto_nombre"></td>
                                                <td x-text="detalle.medida"></td>
                                                <td class="text-right" x-text="Number(detalle.cantidad).toFixed(2)"></td>
                                                <td class="text-right" x-text="'Q ' + Number(detalle.precio_unitario).toFixed(2)"></td>
                                                <td class="text-right" x-text="'Q ' + (detalle.cantidad * detalle.precio_unitario).toFixed(2)"></td>
                                                <td class="text-center">
                                                    <button type="button" @click="eliminarDetalle(index)" class="btn btn-sm btn-ghost btn-error">Eliminar</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-bold">
                                            <td colspan="4" class="text-right">Subtotal:</td>
                                            <td class="text-right" x-text="'Q ' + calcularSubtotal()"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div x-show="detalles.length === 0" class="alert alert-info mt-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 w-6 h-6" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span>No hay productos agregados. Agregue al menos un producto.</span>
                            </div>

                            <input type="hidden" id="detalles_json" name="detalles" :value="JSON.stringify(detalles)">
                        </div>
                    </div>

                    <!-- Descuentos -->
                    <div class="card bg-base-100 shadow">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="card-title m-0">Descuentos</h3>
                                <p class="text-sm text-base-content/70 text-right">Agregue descuentos aplicables</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end mb-4">
                                <div>
                                    <label class="label"><span class="label-text">Tipo de Descuento (otros)</span></label>
                                    <select x-model="nuevoDescuento.id_descuento"
                                        @change="seleccionarDescuento($event)"
                                        class="select select-bordered w-full validator"
                                        data-label="Tipo de descuento"
                                        id="descuento_tipo">
                                        <option value="" disabled>Seleccione descuento</option>
                                        <?php if (!empty($descuentos)): ?>
                                            <?php foreach ($descuentos as $desc):
                                                if ($desc['categoria'] !== 'otros') continue;
                                            ?>
                                                <option value="<?= htmlspecialchars($desc['id']) ?>"
                                                    data-nombre="<?= htmlspecialchars($desc['nombre']) ?>"
                                                    data-categoria="<?= htmlspecialchars($desc['categoria']) ?>"
                                                    data-monto="<?= htmlspecialchars($desc['monto'] ?? '') ?>">
                                                    <?= htmlspecialchars($desc['nombre']) ?> (<?= htmlspecialchars(ucfirst($desc['categoria'])) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="label"><span class="label-text">Monto</span></label>
                                    <input x-model.number="nuevoDescuento.monto"
                                        type="number" min="0.01" step="0.01"
                                        class="input input-bordered w-full validator"
                                        data-label="Monto descuento" id="descuento_monto" />
                                </div>

                                <div>
                                    <button type="button" @click="agregarDescuento" class="btn btn-warning w-full">
                                        <i class="fas fa-plus mr-2"></i> Agregar Descuento
                                    </button>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Secciones automáticas por categoría basadas en cuentas (si existen) -->
                            <?php if (!empty($accountsFinanciamientos)): ?>
                                <div class="mb-4">
                                    <h4 class="font-medium mb-2">Descuentos desde Cuentas de Financiamientos</h4>
                                    <p class="text-sm text-base-content/70 mb-2">Seleccione las cuentas a las que desea aplicar el descuento.</p>
                                    <div class="grid gap-2">
                                        <?php foreach ($accountsFinanciamientos as $acc): ?>
                                            <?php
                                            $cuentaId = htmlspecialchars($acc['id'] ?? '');
                                            $periodoName = htmlspecialchars($acc['nombrePeriodo'] ?? '');
                                            $saldo = (float)($acc['saldo'] ?? 0);
                                            $saldoFmt = number_format($saldo, 2, '.', ',');
                                            ?>
                                            <label class="cursor-pointer flex items-center gap-3 p-3 border border-base-300 rounded hover:bg-base-200">
                                                <input type="checkbox"
                                                    :checked="descuentos.find(d => d.id_descuento === 'fin_<?= $cuentaId ?>') !== undefined"
                                                    @change="(e) => {
                                                        if (e.target.checked) {
                                                            descuentos.push({
                                                            id_descuento: 'fin_<?= $cuentaId ?>',
                                                            nombre: 'Financiamiento - <?= $cuentaId ?> (<?= $periodoName ?>)',
                                                            categoria: 'financiamientos',
                                                            capital: <?= $saldo ?>,
                                                            interes: <?= $acc['interes'] ?? 0 ?>,
                                                            mora: <?= $acc['mora'] ?? 0 ?>,
                                                            monto: <?= $saldo + ($acc['interes'] ?? 0) + ($acc['mora'] ?? 0) ?>
                                                            });
                                                        } else {
                                                            const idx = descuentos.findIndex(d => d.id_descuento === 'fin_<?= $cuentaId ?>');
                                                            if (idx !== -1) descuentos.splice(idx,1);
                                                        }
                                                        }" />
                                                <div class="flex-1">
                                                    <div class="font-semibold text-sm text-base-content">Cuenta: <?= $cuentaId ?> — <?= $periodoName ?></div>
                                                    <div class="text-xs text-base-content/70">Saldo actual: Q <?= $saldoFmt ?></div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($otrosFinanciamientos)): ?>
                                <div class="mb-4">
                                    <h4 class="font-medium mb-2">Descuentos por otras entregas</h4>
                                    <div class="grid gap-2">
                                        <?php foreach ($otrosFinanciamientos as $acc): ?>
                                            <?php
                                            $otrosId = htmlspecialchars($acc['idOtros'] ?? '');
                                            $nombre = htmlspecialchars($acc['nombre'] ?? '');
                                            $nombrePeriodo = htmlspecialchars($acc['nombrePeriodo'] ?? '');
                                            $capital = (float)($acc['cappag'] ?? 0);
                                            $interes = (float)($acc['intpag'] ?? 0);
                                            $mora = (float)($acc['mora'] ?? 0);
                                            ?>
                                            <label class="cursor-pointer flex items-center gap-3 p-3 border border-base-300 rounded hover:bg-base-200">
                                                <input type="checkbox"
                                                    :checked="descuentos.find(d => d.id_descuento === 'otr_<?= $otrosId ?>') !== undefined"
                                                    @change="(e) => {
                                                        if (e.target.checked) {
                                                            descuentos.push({
                                                            id_descuento: 'otr_<?= $otrosId ?>',
                                                            nombre: '<?= $otrosId ?> (<?= addslashes($nombre) ?>) - <?= htmlspecialchars($nombrePeriodo) ?>',
                                                            categoria: 'otras_entregas',
                                                            capital: <?= $capital ?>,
                                                            interes: <?= $interes ?>,
                                                            mora: <?= $mora ?>,
                                                            monto: <?= ($capital + $interes + $mora) ?>,
                                                            idCuenta: <?= $acc['idCuenta'] ?>
                                                            });
                                                        } else {
                                                            const idx = descuentos.findIndex(d => d.id_descuento === 'otr_<?= $otrosId ?>');
                                                            if (idx !== -1) descuentos.splice(idx,1);
                                                        }
                                                        }" />
                                                <div class="flex-1">
                                                    <div class="font-semibold text-sm text-base-content"><?= $nombrePeriodo ?> — <?= $nombre ?></div>
                                                    <div class="text-xs text-base-content/70">Capital: Q <?= number_format($capital, 2, '.', ',') ?> | Interés: Q <?= number_format($interes, 2, '.', ',') ?> | Mora: Q <?= number_format($mora, 2, '.', ',') ?></div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($accountsPrestamos)): ?>
                                <div class="mb-4">
                                    <h4 class="font-medium mb-2">Descuentos desde Cuentas de Préstamos</h4>
                                    <div class="grid gap-2">
                                        <?php foreach ($accountsPrestamos as $acc): ?>
                                            <?php
                                            $cuentaIdRaw = $acc['CCODCTA'] ?? $acc['cuenta'] ?? ($acc['saldo'] ?? '');
                                            $cuentaId = htmlspecialchars($cuentaIdRaw);
                                            $descripcion = htmlspecialchars($acc['descripcion'] ?? ($acc['CCODCTA'] ?? ''));
                                            $saldo = (float)($acc['saldo'] ?? 0);
                                            $saldoFmt = number_format($saldo, 2, '.', ',');
                                            ?>
                                            <label class="cursor-pointer flex items-center gap-3 p-3 border border-base-300 rounded hover:bg-base-200">
                                                <input type="checkbox"
                                                    :checked="descuentos.find(d => d.id_descuento === 'pre_<?= $cuentaId ?>') !== undefined"
                                                    @change="(e) => {
                                                        if (e.target.checked) {
                                                            descuentos.push({
                                                            id_descuento: 'pre_<?= $cuentaId ?>',
                                                            nombre: 'Préstamo - <?= $cuentaId ?><?= $descripcion ? ' - ' . addslashes($descripcion) : '' ?>',
                                                            categoria: 'prestamos',
                                                            capital: <?= $saldo ?>,
                                                            interes: 0,
                                                            mora: 0,
                                                            monto: <?= $saldo ?>
                                                            });
                                                        } else {
                                                            const idx = descuentos.findIndex(d => d.id_descuento === 'pre_<?= $cuentaId ?>');
                                                            if (idx !== -1) descuentos.splice(idx,1);
                                                        }
                                                        }" />
                                                <div class="flex-1">
                                                    <div class="font-semibold text-sm text-base-content">Cuenta: <?= $cuentaId ?> <?= $descripcion ? '— ' . $descripcion : '' ?></div>
                                                    <div class="text-xs text-base-content/70">Saldo (referencia): Q <?= $saldoFmt ?></div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($accountsAhorros)): ?>
                                <div class="mb-4">
                                    <h4 class="font-medium mb-2">Deposito directo a Cuentas de Ahorros</h4>
                                    <div class="grid gap-2">
                                        <?php foreach ($accountsAhorros as $acc): ?>
                                            <?php
                                            $cuenta = htmlspecialchars($acc['cuenta'] ?? '');
                                            $descripcion = htmlspecialchars($acc['descripcion'] ?? '');
                                            $saldo = (float)($acc['saldo'] ?? 0);
                                            $saldoFmt = number_format($saldo, 2, '.', ',');
                                            ?>
                                            <label class="cursor-pointer flex items-center gap-3 p-3 border border-base-300 rounded hover:bg-base-200">
                                                <input type="checkbox"
                                                    :checked="descuentos.find(d => d.id_descuento === 'aho_<?= $cuenta ?>') !== undefined"
                                                    @change="(e) => {
                                                        if (e.target.checked) {
                                                            descuentos.push({
                                                            id_descuento: 'aho_<?= $cuenta ?>',
                                                            nombre: 'Ahorros - <?= $cuenta ?>',
                                                            categoria: 'ahorros',
                                                            referenciaSaldo: <?= $saldo ?>,
                                                            monto: 0
                                                            });
                                                        } else {
                                                            const idx = descuentos.findIndex(d => d.id_descuento === 'aho_<?= $cuenta ?>');
                                                            if (idx !== -1) descuentos.splice(idx,1);
                                                        }
                                                        }" />
                                                <div class="flex-1">
                                                    <div class="font-semibold text-sm text-base-content"><?= $descripcion ?: 'Cuenta: ' . $cuenta ?></div>
                                                    <div class="text-xs text-base-content/70">Saldo actual: Q <?= $saldoFmt ?></div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($accountsAportaciones)): ?>
                                <div class="mb-4">
                                    <h4 class="font-medium mb-2">Deposito directo a Cuentas de Aportaciones</h4>
                                    <div class="grid gap-2">
                                        <?php foreach ($accountsAportaciones as $acc): ?>
                                            <?php
                                            $cuenta = htmlspecialchars($acc['cuenta'] ?? '');
                                            $descripcion = htmlspecialchars($acc['descripcion'] ?? '');
                                            $saldo = (float)($acc['saldo'] ?? 0);
                                            $saldoFmt = number_format($saldo, 2, '.', ',');
                                            ?>
                                            <label class="cursor-pointer flex items-center gap-3 p-3 border border-base-300 rounded hover:bg-base-200">
                                                <input type="checkbox"
                                                    :checked="descuentos.find(d => d.id_descuento === 'apr_<?= $cuenta ?>') !== undefined"
                                                    @change="(e) => {
                                                    if (e.target.checked) {
                                                        descuentos.push({
                                                        id_descuento: 'apr_<?= $cuenta ?>',
                                                        nombre: 'Aportaciones - <?= $cuenta ?>',
                                                        categoria: 'aportaciones',
                                                        referenciaSaldo: <?= $saldo ?>,
                                                        monto: 0
                                                        });
                                                    } else {
                                                        const idx = descuentos.findIndex(d => d.id_descuento === 'apr_<?= $cuenta ?>');
                                                        if (idx !== -1) descuentos.splice(idx,1);
                                                    }
                                                    }" />
                                                <div class="flex-1">
                                                    <div class="font-semibold text-sm text-base-content"><?= $descripcion ?: 'Cuenta: ' . $cuenta ?></div>
                                                    <div class="text-xs text-base-content/70">Saldo actual: Q <?= $saldoFmt ?></div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Tabla dinámica de descuentos agregados -->
                            <div x-show="descuentos.length > 0" class="overflow-x-auto mt-4">
                                <table class="table table-zebra table-sm w-full">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Categoría</th>
                                            <th>Monto / Detalle</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(descuento, index) in descuentos" :key="index">
                                            <tr>
                                                <td x-text="descuento.nombre"></td>
                                                <td><span class="badge badge-sm badge-outline" x-text="descuento.categoria"></span></td>
                                                <td>
                                                    <div x-show="descuento.categoria === 'financiamientos' || descuento.categoria === 'prestamos' || descuento.categoria === 'otras_entregas'">
                                                        <div class="grid grid-cols-3 gap-2">
                                                            <div>
                                                                <label class="label text-xs mb-1"><span class="label-text">Capital</span></label>
                                                                <input type="number" min="0" step="0.01" class="input input-bordered w-full" x-model.number="descuento.capital"
                                                                    @input="descuento.monto = (Number(descuento.capital)||0) + (Number(descuento.interes)||0) + (Number(descuento.mora)||0)" />
                                                            </div>
                                                            <div>
                                                                <label class="label text-xs mb-1"><span class="label-text">Interés</span></label>
                                                                <input type="number" min="0" step="0.01" class="input input-bordered w-full" x-model.number="descuento.interes"
                                                                    @input="descuento.monto = (Number(descuento.capital)||0) + (Number(descuento.interes)||0) + (Number(descuento.mora)||0)" />
                                                            </div>
                                                            <div>
                                                                <label class="label text-xs mb-1"><span class="label-text">Mora</span></label>
                                                                <input type="number" min="0" step="0.01" class="input input-bordered w-full" x-model.number="descuento.mora"
                                                                    @input="descuento.monto = (Number(descuento.capital)||0) + (Number(descuento.interes)||0) + (Number(descuento.mora)||0)" />
                                                            </div>
                                                        </div>
                                                        <div class="text-xs text-base-content/70 mt-1">Total aplicado: <span class="font-semibold text-base-content" x-text="'Q ' + (Number(descuento.monto)||0).toFixed(2)"></span></div>
                                                    </div>

                                                    <div x-show="descuento.categoria === 'ahorros' || descuento.categoria === 'aportaciones'">
                                                        <div class="grid grid-cols-1 gap-2">
                                                            <input type="number" min="0" step="0.01" class="input input-bordered w-full" x-model.number="descuento.monto" />
                                                            <div class="text-xs text-base-content/70" x-show="descuento.referenciaSaldo">
                                                                Saldo actual: <span class="text-base-content" x-text="'Q ' + (Number(descuento.referenciaSaldo)||0).toFixed(2)"></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div x-show="descuento.categoria !== 'financiamientos' && descuento.categoria !== 'prestamos' && descuento.categoria !== 'ahorros' && descuento.categoria !== 'aportaciones' && descuento.categoria !== 'otras_entregas'">
                                                        <input type="number" min="0" step="0.01" class="input input-bordered w-full" x-model.number="descuento.monto" />
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" @click="eliminarDescuento(index)" class="btn btn-sm btn-ghost btn-error">Eliminar</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-bold">
                                            <td colspan="2" class="text-right">Total Descuentos:</td>
                                            <td x-text="'Q ' + calcularTotalDescuentos()"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <input type="hidden" id="descuentos_json" name="descuentos" :value="JSON.stringify(descuentos)">
                        </div>
                    </div>
                </div>

                <!-- Resumen y acciones -->
                <aside class="space-y-6">
                    <div class="card bg-base-100 shadow">
                        <div class="card-body">
                            <h3 class="font-bold text-lg mb-3">Resumen</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span>Subtotal:</span>
                                    <span class="font-semibold" x-text="'Q ' + calcularSubtotal()"></span>
                                </div>
                                <div class="flex justify-between text-warning">
                                    <span>Descuentos:</span>
                                    <span class="font-semibold" x-text="'- Q ' + calcularTotalDescuentos()"></span>
                                </div>
                                <div class="divider my-2"></div>
                                <div class="flex justify-between items-center text-xl font-bold text-primary">
                                    <span>Total a Pagar:</span>
                                    <span x-text="'Q ' + calcularTotal()"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow">
                        <div class="card-body">
                            <h3 class="font-bold text-lg mb-3">Guardar Compra</h3>
                            <?= $csrf->getTokenField(); ?>
                            <div class="flex flex-col gap-3">
                                <button type="button" @click="guardarCompra('settled', '<?= htmlspecialchars($selectedId ?? '') ?>')" class="btn btn-success w-full">
                                    <i class="fas fa-check-circle mr-2"></i> Liquidar Compra
                                </button>
                                <button type="button" class="btn btn-outline w-full" onclick="printdiv2('#cuadro','0')">Cancelar</button>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($descuentos) || !empty($productos)): ?>
                        <div class="card bg-base-100 shadow">
                            <div class="card-body">
                                <h4 class="font-medium mb-2">Resumen rápido</h4>
                                <div class="text-sm text-base-content/70">Productos: <span class="font-semibold text-base-content"><?= count($productos) ?></span></div>
                                <div class="text-sm text-base-content/70 mt-1">Tipos de descuentos: <span class="font-semibold text-base-content"><?= count($descuentos) ?></span></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>

        <dialog id="my_modal_1" class="modal">
            <div class="modal-box w-11/12 max-w-5xl">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold">Buscar Cliente</h3>
                    <form method="dialog">
                        <button class="btn btn-sm btn-circle btn-ghost">✕</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table id="clientesTable" class="table table-zebra table-sm w-full">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Identificación</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-ghost">Cerrar</button>
                    </form>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>

        <script>
            $(document).ready(function() {
                const columns = [{
                        data: 'codigo_cliente',
                        className: 'text-left'
                    },
                    {
                        data: 'nombre',
                        className: 'text-left'
                    },
                    {
                        data: 'identificacion',
                    },
                    {
                        data: null,
                        title: 'Acción',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<button class="btn btn-soft btn-success" onclick="printdiv2('#cuadro','${row.codigo_cliente}');my_modal_1.close();">Seleccionar</button>`;
                        }
                    }
                ];
                const table = initServerSideDataTable(
                    '#clientesTable',
                    'cli_clientes_all',
                    columns, {
                        onError: function(xhr, error, thrown) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al cargar clientes',
                                text: 'Por favor, intente nuevamente'
                            });
                        }
                    }
                );
                startValidationGeneric('#compraForm');
                initTomSelect('#detalle_producto');
            });

            function comprasManager() {
                return {
                    formaPago: '<?= isset($compraSeleccionada) ? $compraSeleccionada['forma_pago'] : '' ?>',
                    detalles: <?= isset($detallesCompra) ? json_encode($detallesCompra) : '[]' ?>,
                    descuentos: <?= isset($descuentosCompra) ? json_encode($descuentosCompra) : '[]' ?>,
                    nuevoDetalle: {
                        id_producto: '',
                        producto_id: '',
                        producto_nombre: '',
                        cantidad: 1,
                        precio_unitario: 0,
                        medida: '',
                        descripcion: ''
                    },
                    nuevoDescuento: {
                        id_descuento: '',
                        nombre: '',
                        categoria: '',
                        monto: 0
                    },

                    seleccionarProducto(event) {
                        const option = event.target.options[event.target.selectedIndex];
                        this.nuevoDetalle.producto_id = option.getAttribute('data-producto-id');
                        this.nuevoDetalle.producto_nombre = option.getAttribute('data-nombre');
                        this.nuevoDetalle.precio_unitario = parseFloat(option.getAttribute('data-precio'));
                        this.nuevoDetalle.medida = option.getAttribute('data-medida');
                        this.nuevoDetalle.descripcion = option.getAttribute('data-precio-nombre');
                    },

                    agregarDetalle() {
                        const validacion = validarCamposGeneric(['detalle_cantidad', 'detalle_precio'], ['detalle_producto'], []);
                        if (!validacion.esValido) {
                            return;
                        }

                        this.detalles.push({
                            id_producto: this.nuevoDetalle.producto_id,
                            producto_nombre: this.nuevoDetalle.producto_nombre,
                            cantidad: parseFloat(this.nuevoDetalle.cantidad),
                            precio_unitario: parseFloat(this.nuevoDetalle.precio_unitario),
                            medida: this.nuevoDetalle.medida,
                            descripcion: this.nuevoDetalle.descripcion
                        });

                        this.limpiarDetalleForm();
                    },

                    eliminarDetalle(index) {
                        this.detalles.splice(index, 1);
                    },

                    limpiarDetalleForm() {
                        this.nuevoDetalle = {
                            id_producto: '',
                            producto_id: '',
                            producto_nombre: '',
                            cantidad: 1,
                            precio_unitario: 0,
                            medida: '',
                            descripcion: ''
                        };
                    },

                    seleccionarDescuento(event) {
                        const option = event.target.options[event.target.selectedIndex];
                        this.nuevoDescuento.nombre = option.getAttribute('data-nombre');
                        this.nuevoDescuento.categoria = option.getAttribute('data-categoria');
                        const monto = option.getAttribute('data-monto');
                        if (monto) {
                            this.nuevoDescuento.monto = parseFloat(monto);
                        }
                    },

                    agregarDescuento() {
                        const validacion = validarCamposGeneric(['descuento_monto'], ['descuento_tipo'], []);
                        if (!validacion.esValido) {
                            return;
                        }

                        // Verificar que no exista ya ese descuento
                        const existe = this.descuentos.find(d => d.id_descuento == this.nuevoDescuento.id_descuento);
                        if (existe) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Advertencia',
                                text: 'Ya existe este tipo de descuento agregado'
                            });
                            return;
                        }

                        this.descuentos.push({
                            id_descuento: this.nuevoDescuento.id_descuento,
                            nombre: this.nuevoDescuento.nombre,
                            categoria: this.nuevoDescuento.categoria,
                            monto: parseFloat(this.nuevoDescuento.monto)
                        });

                        this.limpiarDescuentoForm();
                    },

                    eliminarDescuento(index) {
                        this.descuentos.splice(index, 1);
                    },

                    limpiarDescuentoForm() {
                        this.nuevoDescuento = {
                            id_descuento: '',
                            nombre: '',
                            categoria: '',
                            monto: 0
                        };
                    },

                    calcularSubtotal() {
                        const total = this.detalles.reduce((sum, d) => sum + (d.cantidad * d.precio_unitario), 0);
                        return total.toFixed(2);
                    },

                    calcularTotalDescuentos() {
                        const total = this.descuentos.reduce((sum, d) => sum + parseFloat(d.monto || 0), 0);
                        return total.toFixed(2);
                    },

                    calcularTotal() {
                        const subtotal = parseFloat(this.calcularSubtotal());
                        const totalDescuentos = parseFloat(this.calcularTotalDescuentos());
                        return (subtotal - totalDescuentos).toFixed(2);
                    },

                    guardarCompra(estado, idCliente) {
                        if (this.detalles.length === 0) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Advertencia',
                                text: 'Debe agregar al menos un producto'
                            });
                            return;
                        }

                        const total = parseFloat(this.calcularTotal());
                        if (total <= 0) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Advertencia',
                                text: 'El total de la compra debe ser mayor a cero'
                            });
                            return;
                        }

                        let mensaje = '¿Está seguro de guardar esta compra?';
                        if (estado === 'settled') {
                            mensaje = '¿Está seguro de liquidar esta compra?.';
                        }

                        obtiene(
                            ['<?= $csrf->getTokenName() ?>', 'compra_fecha', 'compra_numdoc', 'compra_concepto', 'compra_doc_banco'],
                            ['compra_forma_pago', 'compra_banco'],
                            [],
                            'cc_compras_create',
                            '0',
                            [this.detalles, estado, idCliente, this.descuentos],
                            function(response) {
                                if (estado === 'settled' && response.idCompra) {
                                    reportes([
                                        [],
                                        [],
                                        [],
                                        [response.idCompra]
                                    ], 'pdf', 'compra_detalle', 0, 0, 'cuencobra', false);
                                    setTimeout(() => {
                                        reportes([
                                            [],
                                            [],
                                            [],
                                            [response.idCompra]
                                        ], 'pdf', 'comprobante_compra', 0, 0, 'cuencobra', '¿Generar Recibo de egreso por la compra?');
                                    }, 1500);
                                }
                            },
                            mensaje,
                            'crud_cuentas_por_cobrarxx'
                        );
                    }
                }
            }
        </script>
    <?php
        break;
    case 'status_account':
        $accountCode = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();

            // Si se seleccionó una cuenta, obtener sus datos
            $datosCuenta = null;
            $movimientos = [];
            $garantias = [];

            if ($accountCode != '0') {
                // Obtener datos de la cuenta
                $datosCuenta = $database->getAllResults(
                    "SELECT 
                        cue.id, cue.monto_inicial, cue.tasa_interes, cue.fecha_inicio, cue.fecha_fin, cue.estado,
                        cli.short_name AS cliente_nombre, cli.no_identifica AS cliente_identificacion,
                        per.nombre AS periodo_nombre, per.tasa_mora,
                         IFNULL((SELECT SUM(kp) FROM cc_kardex WHERE id_cuenta=cue.id AND tipo='D' AND estado='1' GROUP BY id_cuenta), 0) AS financiado
                    FROM cc_cuentas cue
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cue.id_cliente
                    LEFT JOIN cc_periodos per ON per.id = cue.id_periodo
                    WHERE cue.id = ?",
                    [$accountCode]
                );

                if (empty($datosCuenta)) {
                    $showmensaje = true;
                    throw new Exception('No se encontraron datos de la cuenta');
                }

                $datosCuenta = $datosCuenta[0];

                // Obtener todos los movimientos del kardex
                $movimientos = $database->getAllResults(
                    "SELECT 
                        kar.id, kar.fecha, kar.tipo, kar.numdoc, kar.concepto,
                        kar.total, kar.kp, kar.interes, kar.mora,
                        kar.forma_pago,
                        CASE 
                            WHEN kar.tipo = 'D' THEN 'DESEMBOLSO'
                            WHEN kar.tipo = 'E' THEN CONCAT('ENTREGA: ', IFNULL(tm.nombre, 'OTROS'))
                            WHEN kar.tipo = 'I' THEN 'PAGO'
                            ELSE 'OTRO'
                        END AS tipo_descripcion,
                        kd.monto as monto_detalle,
                        tm.nombre as tipo_movimiento_nombre
                    FROM cc_kardex kar
                    LEFT JOIN cc_kardex_detalle kd ON kd.id_kardex = kar.id
                    LEFT JOIN cc_tipos_movimientos tm ON tm.id = kd.id_movimiento
                    WHERE kar.id_cuenta = ? AND kar.estado = '1'
                    ORDER BY kar.fecha ASC, kar.id ASC",
                    [$accountCode]
                );

                // Obtener garantías
                $garantias = $database->getAllResults(
                    "SELECT 
                        gar.descripcion, gar.valor, gar.observaciones,
                        tg.nombre AS tipo_garantia
                    FROM cc_cuentas_garantias gar
                    INNER JOIN cc_tipos_garantias tg ON tg.id = gar.id_tipogarantia
                    WHERE gar.id_cuenta = ? AND gar.estado = '1'
                    ORDER BY gar.id ASC",
                    [$accountCode]
                );
            }

            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="hidden" value="status_account" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-7xl mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($datosCuenta === null): ?>
                <!-- Vista inicial: Selección de cuenta -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Estado de Cuenta</h2>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="printdiv2('#cuadro', '0')">
                                Cancelar
                            </button>
                        </div>

                        <div class="alert alert-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Seleccione una cuenta para visualizar el estado de cuenta con todos sus movimientos.</span>
                        </div>

                        <div class="overflow-x-auto mt-6">
                            <table class="table table-zebra w-full" id="cuentasTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Identificación</th>
                                        <th>Financiado</th>
                                        <th>Fecha Inicio</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <script>
                    $(document).ready(function() {
                        // viewLoader.init();
                        const columns = [{
                                data: 'id',
                                className: 'text-left'
                            },
                            {
                                data: 'short_name',
                                className: 'text-left'
                            },
                            {
                                data: 'no_identifica',
                            },
                            {
                                data: 'monto_inicial',
                                render: function(data, type, row) {
                                    return `Q ${parseFloat(data).toFixed(2)}`;
                                }
                            },
                            {
                                data: 'fecha_inicio',
                                render: function(data, type, row) {
                                    const date = new Date(data);
                                    const day = String(date.getDate()).padStart(2, '0');
                                    const month = String(date.getMonth() + 1).padStart(2, '0');
                                    const year = date.getFullYear();
                                    return `${day}/${month}/${year}`;
                                }
                            },
                            {
                                data: 'estado',
                                render: function(data, type, row) {
                                    let badgeClass = 'badge-info';
                                    const estado = data.toUpperCase();
                                    if (estado === 'ACTIVA') badgeClass = 'badge-success';
                                    if (estado === 'CANCELADA') badgeClass = 'badge-error';
                                    return `<span class="badge ${badgeClass} badge-sm">${estado}</span>`;
                                }
                            },
                            {
                                data: null,
                                title: 'Acción',
                                orderable: false,
                                searchable: false,
                                render: function(data, type, row) {
                                    // return `<button class="btn btn-soft btn-success" onclick="printdiv2('#cuadro','${row.codigo_cliente}');my_modal_1.close();">Seleccionar</button>`;
                                    return `
                                        <div class="flex gap-2 justify-center">
                                            <button class="btn btn-soft btn-info btn-sm"
                                                onclick="printdiv2('#cuadro', '${row.id}');">
                                                <i class="fas fa-eye mr-1"></i> Ver
                                            </button>
                                            <button class="btn btn-soft btn-primary btn-sm"
                                                onclick="reportes([[],[],[],[${row.id}]], 'pdf', 'estado_cuenta', 0, 0, 'cuencobra');">
                                                <i class="fas fa-file-pdf mr-1"></i> PDF
                                            </button>
                                        </div>
                                    `;
                                }
                            }
                        ];
                        const table = initServerSideDataTable(
                            '#cuentasTable',
                            'cc_cuentas',
                            columns, {
                                onError: function(xhr, error, thrown) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error al cargar clientes',
                                        text: 'Por favor, intente nuevamente'
                                    });
                                }
                            }
                        );
                    });
                </script>
            <?php else: ?>
                <!-- Vista del estado de cuenta -->
                <div class="space-y-6">
                    <!-- Encabezado con acciones -->
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="card-title text-2xl">Estado de Cuenta</h2>
                                    <p class="text-sm text-dark mt-1">Cuenta No. <?= str_pad($datosCuenta['id'], 8, '0', STR_PAD_LEFT) ?></p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm"
                                        onclick="reportes([[],[],[],[<?= (int)$datosCuenta['id'] ?>]], 'pdf', 'estado_cuenta', 0, 0, 'cuencobra');">
                                        <i class="fas fa-file-pdf mr-2"></i> Descargar PDF
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="printdiv2('#cuadro', '0')">
                                        <i class="fas fa-arrow-left mr-2"></i> Regresar
                                    </button>
                                </div>
                            </div>

                            <!-- Estado de la cuenta -->
                            <div class="mt-4">
                                <?php
                                $estado = strtoupper($datosCuenta['estado']);
                                $badgeClass = 'badge-info';
                                $textClass = 'text-info';
                                if ($estado === 'ACTIVA') {
                                    $badgeClass = 'badge-success';
                                    $textClass = 'text-dark';
                                }
                                if ($estado === 'CANCELADA') {
                                    $badgeClass = 'badge-error';
                                    $textClass = 'text-dark';
                                }
                                ?>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold">Estado:</span>
                                    <span class="badge <?= $badgeClass ?> <?= $textClass ?>"><?= $estado ?></span>
                                    <span class="text-xs text-neutral ml-4">
                                        Generado: <?= date('d/m/Y H:i') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información del cliente -->
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title text-lg mb-4">Datos del Titular</h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="text-xs text-neutral font-semibold">Cliente</label>
                                    <p class="text-sm mt-1"><?= htmlspecialchars($datosCuenta['cliente_nombre']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-neutral font-semibold">Identificación</label>
                                    <p class="text-sm mt-1"><?= htmlspecialchars($datosCuenta['cliente_identificacion']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-neutral font-semibold">Período</label>
                                    <p class="text-sm mt-1"><?= htmlspecialchars($datosCuenta['periodo_nombre']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-neutral font-semibold">Total financiado</label>
                                    <p class="text-sm mt-1 font-semibold">Q <?= number_format($datosCuenta['financiado'], 2, '.', ',') ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-neutral font-semibold">Tasa Interés</label>
                                    <p class="text-sm mt-1"><?= $datosCuenta['tasa_interes'] ?>%</p>
                                </div>
                                <div>
                                    <label class="text-xs text-neutral font-semibold">Fecha Inicio</label>
                                    <p class="text-sm mt-1"><?= setdatefrench($datosCuenta['fecha_inicio']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-neutral font-semibold">Fecha Vencimiento</label>
                                    <p class="text-sm mt-1"><?= !empty($datosCuenta['fecha_fin']) ? setdatefrench($datosCuenta['fecha_fin']) : '-' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen de movimientos -->
                    <?php
                    $totalDesembolsos = 0;
                    $totalEntregas = 0;
                    $totalRecuperaciones = 0;
                    $totalCapitalRecuperado = 0;
                    $totalInteresRecuperado = 0;
                    $totalMoraRecuperado = 0;

                    foreach ($movimientos as $mov) {
                        if ($mov['tipo'] === 'D') {
                            $totalDesembolsos += $mov['total'];
                        } elseif ($mov['tipo'] === 'E') {
                            $totalEntregas += $mov['total'];
                        } elseif ($mov['tipo'] === 'I') {
                            $totalRecuperaciones += $mov['total'];
                            $totalCapitalRecuperado += $mov['kp'];
                            $totalInteresRecuperado += $mov['interes'];
                            $totalMoraRecuperado += $mov['mora'];
                        }
                    }

                    $saldoCapital = $totalDesembolsos - $totalCapitalRecuperado;
                    $saldoOtrasEntregas = $totalEntregas - ($totalRecuperaciones - $totalCapitalRecuperado - $totalInteresRecuperado - $totalMoraRecuperado);
                    $saldoTotalPendiente = $saldoCapital + $saldoOtrasEntregas;
                    ?>

                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title text-lg mb-4">Resumen de Movimientos</h3>

                            <div class="overflow-x-auto">
                                <table class="table table-sm w-full">
                                    <thead>
                                        <tr class="bg-base-200">
                                            <th>Concepto</th>
                                            <th class="text-right">Cargos</th>
                                            <th class="text-right">Abonos</th>
                                            <th class="text-right">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="font-medium">Financiamiento (Desembolsos)</td>
                                            <td class="text-right">Q <?= number_format($totalDesembolsos, 2, '.', ',') ?></td>
                                            <td class="text-right">Q <?= number_format($totalCapitalRecuperado, 2, '.', ',') ?></td>
                                            <td class="text-right font-semibold">Q <?= number_format($saldoCapital, 2, '.', ',') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-medium">Otras Entregas</td>
                                            <td class="text-right">Q <?= number_format($totalEntregas, 2, '.', ',') ?></td>
                                            <td class="text-right">Q <?= number_format($totalRecuperaciones - $totalCapitalRecuperado - $totalInteresRecuperado - $totalMoraRecuperado, 2, '.', ',') ?></td>
                                            <td class="text-right font-semibold">Q <?= number_format($saldoOtrasEntregas, 2, '.', ',') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-medium">Intereses Pagados</td>
                                            <td class="text-right">-</td>
                                            <td class="text-right">Q <?= number_format($totalInteresRecuperado, 2, '.', ',') ?></td>
                                            <td class="text-right">-</td>
                                        </tr>
                                        <tr>
                                            <td class="font-medium">Mora Pagada</td>
                                            <td class="text-right">-</td>
                                            <td class="text-right">Q <?= number_format($totalMoraRecuperado, 2, '.', ',') ?></td>
                                            <td class="text-right">-</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-error/10 font-bold text-error">
                                            <td colspan="3" class="text-right text-lg">SALDO TOTAL PENDIENTE:</td>
                                            <td class="text-right text-lg">Q <?= number_format($saldoTotalPendiente, 2, '.', ',') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Historial de movimientos -->
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body">
                            <h3 class="card-title text-lg mb-4">Historial de Movimientos</h3>

                            <div class="overflow-x-auto">
                                <table class="table table-zebra table-sm w-full">
                                    <thead>
                                        <tr class="bg-base-200">
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Doc.</th>
                                            <th class="text-right">Cargo</th>
                                            <th class="text-right">Abono</th>
                                            <th class="text-right">Interés</th>
                                            <th class="text-right">Mora</th>
                                            <th class="text-right">Saldo</th>
                                            <th>F. Pago</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $saldoAcumulado = 0;
                                        foreach ($movimientos as $mov):
                                            $cargo = 0;
                                            $abono = 0;

                                            if ($mov['tipo'] === 'D' || $mov['tipo'] === 'E') {
                                                $cargo = $mov['total'];
                                                $saldoAcumulado += $cargo;
                                            } elseif ($mov['tipo'] === 'I') {
                                                $abono = $mov['kp'];
                                                $abono += $mov['monto_detalle'];
                                                $saldoAcumulado -= $abono;
                                            }
                                        ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($mov['fecha'])) ?></td>
                                                <td>
                                                    <span class="badge badge-sm <?= $mov['tipo'] === 'D' ? 'badge-info' : ($mov['tipo'] === 'I' ? 'badge-success' : 'badge-warning') ?>">
                                                        <?= htmlspecialchars(substr($mov['tipo_descripcion'], 0, 30)) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars(substr($mov['numdoc'], 0, 12)) ?></td>
                                                <td class="text-right <?= $cargo > 0 ? 'text-error' : '' ?>">
                                                    <?= $cargo > 0 ? Moneda::formato($cargo, '') : '-' ?>
                                                </td>
                                                <td class="text-right <?= $abono > 0 ? 'text-success' : '' ?>">
                                                    <?= $abono > 0 ? Moneda::formato($abono, '') : '-' ?>
                                                </td>
                                                <td class="text-right"><?= ($mov['tipo'] == 'I') ? Moneda::formato($mov['interes'], '') : '-'  ?></td>
                                                <td class="text-right"><?= ($mov['tipo'] == 'I') ? Moneda::formato($mov['mora'], '') : '-'  ?></td>
                                                <td class="text-right font-semibold"><?= Moneda::formato($saldoAcumulado, '') ?></td>
                                                <td><?= htmlspecialchars(strtoupper(substr($mov['forma_pago'], 0, 8))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-base-200 font-bold">
                                            <td colspan="3" class="text-right">TOTALES:</td>
                                            <td class="text-right"><?= Moneda::formato($totalDesembolsos + $totalEntregas, '') ?></td>
                                            <td class="text-right"><?= Moneda::formato($totalRecuperaciones, '') ?></td>
                                            <td class="text-right"><?= Moneda::formato(array_sum(array_column($movimientos, 'interes')), '') ?></td>
                                            <td class="text-right"><?= Moneda::formato(array_sum(array_column($movimientos, 'mora')), '') ?></td>
                                            <td class="text-right"><?= Moneda::formato($saldoAcumulado, '') ?></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Garantías -->
                    <?php if (!empty($garantias)): ?>
                        <div class="card bg-base-100 shadow-md">
                            <div class="card-body">
                                <h3 class="card-title text-lg mb-4">Garantías Asociadas</h3>

                                <div class="overflow-x-auto">
                                    <table class="table table-zebra table-sm w-full">
                                        <thead>
                                            <tr class="bg-base-200">
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th class="text-right">Valor</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $totalGarantias = 0;
                                            foreach ($garantias as $g):
                                                $totalGarantias += $g['valor'];
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($g['tipo_garantia']) ?></td>
                                                    <td><?= htmlspecialchars($g['descripcion']) ?></td>
                                                    <td class="text-right font-semibold">Q <?= number_format($g['valor'], 2, '.', ',') ?></td>
                                                    <td><?= htmlspecialchars($g['observaciones']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-base-200 font-bold">
                                                <td colspan="2" class="text-right">TOTAL GARANTÍAS:</td>
                                                <td class="text-right">Q <?= number_format($totalGarantias, 2, '.', ',') ?></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
        break;

    case 'reporte_periodo':

        $periodoId = $_POST["xtra"] ?? '0';

        $showmensaje = false;
        try {
            $database->openConnection();

            // Obtener todos los periodos
            $periodosExistentes = $database->getAllResults(
                "SELECT id, nombre, fecha_inicio, fecha_fin FROM cc_periodos WHERE estado='1' ORDER BY fecha_inicio DESC",
                []
            );

            // Datos del periodo seleccionado
            $datosPeriodo = null;
            $cuentasPeriodo = [];

            if ($periodoId != '0') {
                // Obtener datos del periodo
                $datosPeriodo = $database->getAllResults(
                    "SELECT id, nombre, fecha_inicio, fecha_fin, tasa_interes, tasa_mora 
                     FROM cc_periodos WHERE id = ?",
                    [$periodoId]
                );

                if (empty($datosPeriodo)) {
                    $showmensaje = true;
                    throw new Exception('No se encontró el periodo seleccionado');
                }

                $datosPeriodo = $datosPeriodo[0];

                // Obtener todas las cuentas del periodo con sus movimientos
                $cuentasPeriodo = $database->getAllResults(
                    "SELECT 
                        cue.id AS cuenta_id,
                        cli.short_name AS cliente_nombre,
                        cli.no_identifica AS cliente_identificacion,
                        cue.monto_inicial,
                        cue.fecha_inicio,
                        cue.fecha_fin,
                        cue.estado,
                        cue.tasa_interes,
                        
                        -- Total garantías
                        IFNULL((
                            SELECT SUM(valor) 
                            FROM cc_cuentas_garantias 
                            WHERE id_cuenta = cue.id AND estado = '1'
                        ), 0) AS total_garantias,
                        
                        -- Total financiado (desembolsos)
                        IFNULL((
                            SELECT SUM(kp) 
                            FROM cc_kardex 
                            WHERE id_cuenta = cue.id AND tipo = 'D' AND estado = '1'
                        ), 0) AS total_financiado,
                        
                        -- Total otras entregas (anticipos y otros)
                        IFNULL((
                            SELECT SUM(k.total)
                            FROM cc_kardex k
                            WHERE k.id_cuenta = cue.id AND k.tipo = 'E' AND k.estado = '1'
                        ), 0) AS total_otras_entregas,
                        
                        -- Total capital pagado
                        IFNULL((
                            SELECT SUM(kp) 
                            FROM cc_kardex 
                            WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
                        ), 0) AS total_capital_pagado,
                        
                        -- Total intereses pagados
                        IFNULL((
                            SELECT SUM(interes) 
                            FROM cc_kardex 
                            WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
                        ), 0) AS total_interes_pagado,
                        
                        -- Total mora pagada
                        IFNULL((
                            SELECT SUM(mora) 
                            FROM cc_kardex 
                            WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
                        ), 0) AS total_mora_pagado,
                        
                        -- Total de otros conceptos pagados (total - kp - interes - mora)
                        IFNULL((
                            SELECT SUM(total - kp - interes - mora) 
                            FROM cc_kardex 
                            WHERE id_cuenta = cue.id AND tipo = 'I' AND estado = '1'
                        ), 0) AS total_otros_pagado
                        
                    FROM cc_cuentas cue
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cue.id_cliente
                    WHERE cue.id_periodo = ? AND cue.estado IN ('ACTIVA', 'CANCELADA')
                    ORDER BY cue.id ASC",
                    [$periodoId]
                );

                // Calcular saldos para cada cuenta
                foreach ($cuentasPeriodo as &$cuenta) {
                    $cuenta['saldo_financiamiento'] = $cuenta['total_financiado'] - $cuenta['total_capital_pagado'];
                    $cuenta['saldo_otras_entregas'] = $cuenta['total_otras_entregas'] - $cuenta['total_otros_pagado'];
                    $cuenta['saldo_total'] = $cuenta['saldo_financiamiento'] + $cuenta['saldo_otras_entregas'];
                }
                unset($cuenta);
            }

            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="hidden" value="reporte_periodo" id="condi">
        <input type="hidden" value="cuencobra/views/view002" id="file">

        <div class="p-6 max-w-full mx-auto">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="alert alert-warning shadow mb-4">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"></path>
                        </svg>
                        <span><?= htmlspecialchars($mensaje) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($datosPeriodo === null): ?>
                <!-- Selección de periodo -->
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Reporte por Período</h2>

                        <div class="alert alert-info mb-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Seleccione un período para visualizar el estado de todas las cuentas asociadas.</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th>Período</th>
                                        <th>Fecha Inicio</th>
                                        <th>Fecha Fin</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($periodosExistentes)): ?>
                                        <?php foreach ($periodosExistentes as $periodo): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($periodo['nombre']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($periodo['fecha_inicio'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($periodo['fecha_fin'])) ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="printdiv2('#cuadro', '<?= $periodo['id'] ?>')">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        Ver Reporte
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-gray-500">No hay períodos disponibles</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Reporte del periodo -->
                <div class="space-y-6">
                    <!-- Encabezado -->
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h2 class="text-2xl font-bold">Reporte de Cuentas por Cobrar</h2>
                                    <p class="text-lg mt-2">Período: <span class="font-semibold"><?= htmlspecialchars($datosPeriodo['nombre']) ?></span></p>
                                    <p class="text-sm text-gray-600">
                                        Del <?= date('d/m/Y', strtotime($datosPeriodo['fecha_inicio'])) ?>
                                        al <?= date('d/m/Y', strtotime($datosPeriodo['fecha_fin'])) ?>
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="printdiv2('#cuadro', '0')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                        </svg>
                                        Cambiar Período
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="reportes([[],[],[],[<?= $periodoId ?>]], 'pdf', 'reporte_periodo_pdf', 0, 0, 'cuencobra');">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Generar PDF
                                    </button>
                                </div>
                            </div>

                            <?php
                            // Calcular totales generales
                            $totalGarantias = array_sum(array_column($cuentasPeriodo, 'total_garantias'));
                            $totalFinanciado = array_sum(array_column($cuentasPeriodo, 'total_financiado'));
                            $totalOtrasEntregas = array_sum(array_column($cuentasPeriodo, 'total_otras_entregas'));
                            $totalCapitalPagado = array_sum(array_column($cuentasPeriodo, 'total_capital_pagado'));
                            $totalInteresPagado = array_sum(array_column($cuentasPeriodo, 'total_interes_pagado'));
                            $totalMoraPagado = array_sum(array_column($cuentasPeriodo, 'total_mora_pagado'));
                            $totalSaldoFinanciamiento = array_sum(array_column($cuentasPeriodo, 'saldo_financiamiento'));
                            $totalSaldoOtrasEntregas = array_sum(array_column($cuentasPeriodo, 'saldo_otras_entregas'));
                            $totalSaldoGeneral = $totalSaldoFinanciamiento + $totalSaldoOtrasEntregas;
                            ?>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                <div class="stat bg-base-200 rounded-lg p-4">
                                    <div class="stat-title text-xs">Total Cuentas</div>
                                    <div class="stat-value text-2xl"><?= count($cuentasPeriodo) ?></div>
                                </div>
                                <div class="stat bg-blue-50 rounded-lg p-4">
                                    <div class="stat-title text-xs">Total Financiado</div>
                                    <div class="stat-value text-2xl text-blue-600">Q <?= number_format($totalFinanciado, 2) ?></div>
                                </div>
                                <div class="stat bg-green-50 rounded-lg p-4">
                                    <div class="stat-title text-xs">Total Recuperado</div>
                                    <div class="stat-value text-2xl text-green-600">Q <?= number_format($totalCapitalPagado, 2) ?></div>
                                </div>
                                <div class="stat bg-yellow-50 rounded-lg p-4">
                                    <div class="stat-title text-xs">Saldo financiamientos</div>
                                    <div class="stat-value text-2xl text-yellow-600">Q <?= number_format($totalSaldoFinanciamiento, 2) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de cuentas -->
                    <div class="card bg-base-100 shadow-md" id="tabla-reporte">
                        <div class="card-body">
                            <h3 class="card-title mb-4">Detalle de Cuentas</h3>

                            <div class="overflow-x-auto">
                                <table class="table table-zebra table-sm w-full">
                                    <thead>
                                        <tr class="bg-base-200">
                                            <th class="text-center">No. Cuenta</th>
                                            <th>Titular</th>
                                            <th class="text-right">Garantías</th>
                                            <th class="text-right">Total Financiado</th>
                                            <th class="text-right">Otras Entregas</th>
                                            <th class="text-right">Capital Pagado</th>
                                            <th class="text-right">Saldo Financ.</th>
                                            <th class="text-right">Saldo Otras</th>
                                            <th class="text-right">Interés Pagado</th>
                                            <th class="text-right">Mora Pagada</th>
                                            <th class="text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($cuentasPeriodo)): ?>
                                            <?php foreach ($cuentasPeriodo as $cuenta): ?>
                                                <tr>
                                                    <td class="text-center font-mono"><?= htmlspecialchars($cuenta['cuenta_id']) ?></td>
                                                    <td>
                                                        <div class="font-semibold"><?= htmlspecialchars($cuenta['cliente_nombre']) ?></div>
                                                        <div class="text-xs text-gray-600"><?= htmlspecialchars($cuenta['cliente_identificacion']) ?></div>
                                                    </td>
                                                    <td class="text-right">Q <?= number_format($cuenta['total_garantias'], 2) ?></td>
                                                    <td class="text-right font-semibold text-blue-600">Q <?= number_format($cuenta['total_financiado'], 2) ?></td>
                                                    <td class="text-right">Q <?= number_format($cuenta['total_otras_entregas'], 2) ?></td>
                                                    <td class="text-right text-green-600">Q <?= number_format($cuenta['total_capital_pagado'], 2) ?></td>
                                                    <td class="text-right font-semibold <?= $cuenta['saldo_financiamiento'] > 0 ? 'text-orange-600' : 'text-gray-500' ?>">
                                                        Q <?= number_format($cuenta['saldo_financiamiento'], 2) ?>
                                                    </td>
                                                    <td class="text-right <?= $cuenta['saldo_otras_entregas'] > 0 ? 'text-orange-600' : 'text-gray-500' ?>">
                                                        Q <?= number_format($cuenta['saldo_otras_entregas'], 2) ?>
                                                    </td>
                                                    <td class="text-right">Q <?= number_format($cuenta['total_interes_pagado'], 2) ?></td>
                                                    <td class="text-right">Q <?= number_format($cuenta['total_mora_pagado'], 2) ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        $estadoClass = $cuenta['estado'] === 'ACTIVA' ? 'badge-success' : 'badge-error';
                                                        ?>
                                                        <span class="badge <?= $estadoClass ?> badge-sm"><?= htmlspecialchars($cuenta['estado']) ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <!-- Fila de totales -->
                                            <tr class="bg-base-200 font-bold">
                                                <td colspan="2" class="text-right">TOTALES:</td>
                                                <td class="text-right">Q <?= number_format($totalGarantias, 2) ?></td>
                                                <td class="text-right text-blue-600">Q <?= number_format($totalFinanciado, 2) ?></td>
                                                <td class="text-right">Q <?= number_format($totalOtrasEntregas, 2) ?></td>
                                                <td class="text-right text-green-600">Q <?= number_format($totalCapitalPagado, 2) ?></td>
                                                <td class="text-right text-orange-600">Q <?= number_format($totalSaldoFinanciamiento, 2) ?></td>
                                                <td class="text-right text-orange-600">Q <?= number_format($totalSaldoOtrasEntregas, 2) ?></td>
                                                <td class="text-right">Q <?= number_format($totalInteresPagado, 2) ?></td>
                                                <td class="text-right">Q <?= number_format($totalMoraPagado, 2) ?></td>
                                                <td></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center text-gray-500 py-8">No hay cuentas registradas en este período</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
<?php
        break;

    case 'create_otros_movimientos':
        echo "Crear otros movimientos - En desarrollo";
        break;
}
