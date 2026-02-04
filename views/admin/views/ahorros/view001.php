<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

use App\Generic\DocumentManager;
use App\Generic\FileProcessor;
use Micro\Generic\PermissionManager;
use Micro\Helpers\Log;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

// Log::info("Starting AJAX request processing in view001.php");
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
// require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {
    case 'configuraciones_intereses':

        $idConfiguracion = $_POST["xtra"];

        $isNewSetting = true;

        $showmensaje = false;
        try {

            $database->openConnection();

            $configuraciones = $database->getAllResults("SELECT conf.id idConfiguracion, conf.tipo,conf.periodo,conf.provisionar,conf.estado,tip.nombre
                                            FROM aho_configuraciones_int conf
                                            INNER JOIN ahomtip tip ON tip.id_tipo=conf.producto_id
                                            WHERE tip.estado=1 AND conf.estado IN (1,2);");

            $tiposProductos = $database->selectColumns("ahomtip", ["id_tipo", "nombre"], "estado=1 AND tipcuen IN ('cr','pr','vi')");
            if (empty($tiposProductos)) {
                $showmensaje = true;
                throw new Exception("No hay productos de ahorro registrados, por favor registre al menos un producto.");
            }

            if ($idConfiguracion != "0") {
                $configuracion = $database->selectColumns("aho_configuraciones_int", ['producto_id', 'tipo', 'periodo', 'provisionar', 'estado'], "estado IN (1,2) AND id=?", [$idConfiguracion]);
                if (empty($configuracion)) {
                    $showmensaje = true;
                    throw new Exception("La configuracion seleccionada no existe o no esta disponible.");
                }
                $configuracion = $configuracion[0];
                $isNewSetting = false;
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

        $periodosCatalogo = [
            1 => 'Mensual',
            2 => 'Bimestral',
            3 => 'Trimestral',
            4 => 'Cuatrimestral',
            6 => 'Semestral',
            12 => 'Anual'
        ];
?>
        <input type="hidden" id="file" value="view001">
        <input type="hidden" id="condi" value="configuraciones_intereses">
        <div class="card shadow-lg mb-5 border-0">
            <div class="card-header bg-gradient-primary text-black d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0"><i class="fa-solid fa-gears me-2"></i> Configuraciones de Intereses</h5>
            </div>
            <div class="card-body px-4 py-4">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡Atención!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <div class="container-fluid mb-4">
                    <form id="formConfig" class="row g-4 align-items-end">
                        <div class="col-12">
                            <div class="bg-gradient-primary text-black rounded-top px-3 py-2 mb-3">
                                <h6 id="modalConfigLabel" class="mb-0">
                                    <i class="fa-solid fa-pen me-2"></i> <?= ($isNewSetting) ? "Nueva" : "Editar" ?> Configuración
                                </h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="producto_id" class="form-label fw-semibold">Producto</label>
                            <select class="form-select" id="producto_id" name="producto_id" required>
                                <option value="" selected disabled>Seleccione...</option>
                                <?php foreach ($tiposProductos as $prod): ?>
                                    <option value="<?= $prod['id_tipo'] ?>"
                                        <?= (!$isNewSetting && $status && $configuracion['producto_id'] == $prod['id_tipo']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prod['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="tipo" class="form-label fw-semibold">Tipo</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="" selected disabled>Seleccione...</option>
                                <option value="ACREDITACION" <?= (!$isNewSetting && $status && $configuracion['tipo'] == 'ACREDITACION') ? 'selected' : '' ?>>ACREDITACION</option>
                                <option value="CALCULO" <?= (!$isNewSetting && $status && $configuracion['tipo'] == 'CALCULO') ? 'selected' : '' ?>>SÓLO CALCULO</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="periodo" class="form-label fw-semibold">Periodo</label>
                            <select class="form-select" id="periodo" name="periodo" required>
                                <option value="" selected disabled>Seleccione...</option>
                                <?php foreach ($periodosCatalogo as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= (!$isNewSetting && $status && $configuracion['periodo'] == $key) ? 'selected' : '' ?>><?= $value ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="provisionar" class="form-label fw-semibold">Provisionar mensualmente(solo aplica si la acreditacion no es mensual)</label>
                            <select class="form-select" id="provisionar" name="provisionar">
                                <option value="0" <?= (!$isNewSetting && $status && $configuracion['provisionar'] == 0) ? 'selected' : '' ?>>No</option>
                                <option value="1" <?= (!$isNewSetting && $status && $configuracion['provisionar'] == 1) ? 'selected' : '' ?>>Sí</option>
                            </select>
                        </div>
                        <div class="col-md-3" style="display: <?= ($status && !$isNewSetting) ? 'block' : 'none'; ?>;">
                            <label for="estado" class="form-label fw-semibold">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="1" <?= (!$isNewSetting && $status && $configuracion['estado'] == 1) ? 'selected' : '' ?>>Activo</option>
                                <option value="2" <?= (!$isNewSetting && $status && $configuracion['estado'] == 2) ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="w-100"></div>
                        <div class="col-12 d-flex gap-2 mt-3">
                            <?php echo $csrf->getTokenField(); ?>
                            <?php if ($status && $isNewSetting): ?>
                                <button type="button" class="btn btn-success px-4" onclick="obtiene(['<?= $csrf->getTokenName() ?>'], ['producto_id','tipo','periodo','provisionar','estado'], [], 'create_config_interes', '0', [], 'NULL', '¿Desea guardar esta nueva configuracion?')">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Guardar
                                </button>
                            <?php endif; ?>
                            <?php if ($status && !$isNewSetting): ?>
                                <button type="button" class="btn btn-success px-4" onclick="obtiene(['<?= $csrf->getTokenName() ?>'], ['producto_id','tipo','periodo','provisionar','estado'], [], 'update_config_interes', '0', ['<?= htmlspecialchars($secureID->encrypt($idConfiguracion)) ?>'], 'NULL', '¿Esta seguro de actualizar esta configuracion?')">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Actualizar
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary px-4" onclick="printdiv2('#cuadro', '0')">
                                <i class="fa-solid fa-ban me-1"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>

                <div class="container-fluid">
                    <div class="table-responsive mt-4">
                        <table class="table table-hover align-middle table-bordered shadow-sm">
                            <thead class="table-primary">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Periodo</th>
                                    <th>Provisionar</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($configuraciones as $key => $conf): ?>
                                    <tr>
                                        <td class="text-center"><?= $key + 1 ?></td>
                                        <td><?= htmlspecialchars($conf['nombre']) ?></td>
                                        <td><?= htmlspecialchars($conf['tipo']) ?></td>
                                        <td><?= isset($periodosCatalogo[$conf['periodo']]) ? $periodosCatalogo[$conf['periodo']] : htmlspecialchars($conf['periodo']) ?></td>
                                        <td><?= $conf['provisionar'] ? 'Sí' : 'No' ?></td>
                                        <td>
                                            <span class="badge <?= $conf['estado'] == 1 ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $conf['estado'] == 1 ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary me-1" title="Editar" onclick="printdiv2('#cuadro',<?= $conf['idConfiguracion'] ?>)">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'delete_config_interes', '0', ['<?= htmlspecialchars($secureID->encrypt($conf['idConfiguracion'])) ?>'], 'NULL', '¿Esta seguro de eliminar esta configuracion?')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                inicializarValidacionAutomaticaGeneric('#formConfig');
            });
        </script>
<?php
        break;
}

?>