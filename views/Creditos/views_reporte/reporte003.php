<?php

use App\Generic\User;
use Micro\Exceptions\SoftException;
use Micro\Generic\PermissionManager;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
use Micro\Helpers\SecureID;

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
// require_once __DIR__ . '/../../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../includes/Config/database.php';
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idoficina = $_SESSION['id_agencia'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

include __DIR__ . '/../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

$condi = $_POST["condi"];

switch ($condi) {
    case 'reportePrepago': {
            $status = false;
            $permissionLevel = 0; // permiso bajo por defecto
            try {

                $userPermissions = new PermissionManager($idusuario);

                // PERMISO 0 (por defecto): solo de su usuario
                $condiUser = "estado=1 AND id_usu = ?";
                $condiAgencia = "id_agencia = ?";
                $condiRegion = "estado='1' AND id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?)";

                $paramsUser = [$idusuario];
                $paramsAgencia = [$idagencia];
                $paramsRegion = [$idagencia];

                if ($userPermissions->isLevelOne(PermissionManager::REPORTE_PREPAGOS)) {
                    // PERMISO 1: solo de su agencia y de los usuarios de las agencias de su agencia
                    $permissionLevel = 1;
                    $condiUser = "estado=1 AND id_agencia = ?";
                    $condiAgencia = "id_agencia = ?";
                    $condiRegion = "estado='1' AND id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?)";

                    $paramsUser = [$idagencia];
                    $paramsAgencia = [$idagencia];
                    $paramsRegion = [$idagencia];
                } elseif ($userPermissions->isLevelTwo(PermissionManager::REPORTE_PREPAGOS)) {
                    // PERMISO 2: puede ver solo de su region (solo las agencias de su region y solo los usuarios 
                    // de las agencias de su region) o las regiones que tiene a cargo solo de agencia y de los 
                    // usuarios que pertenecen a estas agencias
                    $permissionLevel = 2;

                    // Regiones: las que contienen su agencia O donde es encargado
                    $condiRegion = "estado='1' AND (id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?) OR id_encargado=?)";
                    $paramsRegion = [$idagencia, $idusuario];

                    // Agencias: todas las agencias de esas regiones
                    $condiAgencia = "id_agencia IN (
                    SELECT id_agencia 
                    FROM cre_regiones_agencias 
                    WHERE id_region IN (
                        SELECT id 
                        FROM cre_regiones 
                        WHERE estado='1' 
                        AND (id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?) OR id_encargado=?) OR id_agencia=?
                    )
                )";
                    $paramsAgencia = [$idagencia, $idusuario, $idagencia];

                    // Usuarios: todos los usuarios de esas agencias
                    $condiUser = "estado=1 AND id_agencia IN (
                    SELECT id_agencia 
                    FROM cre_regiones_agencias 
                    WHERE id_region IN (
                        SELECT id 
                        FROM cre_regiones 
                        WHERE estado='1' 
                        AND (id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?) OR id_encargado=?)
                    )
                ) OR id_usu = ?";
                    $paramsUser = [$idagencia, $idusuario, $idusuario];
                } elseif ($userPermissions->isLevelThree(PermissionManager::REPORTE_PREPAGOS)) {
                    // PERMISO 3: consolidado puede ver todas las agencias, usuarios y regiones
                    $permissionLevel = 3;
                    $condiUser = "estado=1";
                    $condiAgencia = "1=1"; // Sin restricción
                    $condiRegion = "estado='1'";

                    $paramsUser = [];
                    $paramsAgencia = [];
                    $paramsRegion = [];
                }

                $database->openConnection();

                $regiones = $database->selectColumns("cre_regiones", ["id", "nombre"], $condiRegion, $paramsRegion);

                $agencias = $database->selectColumns(
                    'tb_agencia',
                    ['id_agencia', 'nom_agencia', 'cod_agenc'],
                    $condiAgencia,
                    $paramsAgencia
                );

                $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], $condiUser, $paramsUser);
                if (empty($users)) {
                    $showmensaje = true;
                    throw new Exception("No existen usuarios por revisar");
                }

                $status = true;
            } catch (SoftException $se) {
                $mensaje = "Advertencia: " . $se->getMessage();
            } catch (Exception $e) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            } finally {
                $database->closeConnection();
            }
    ?>
            <div x-data="{
                            permisionLevel : <?= $permissionLevel ?? 0 ?>,
                            filterOption: <?= $permissionLevel ?? 0 ?> == 0 ? 'user' : 'agencia'
                        }"
                class="container">
                <div class="text text-center h5 mb-3">REPORTE DE VISITAS PREPAGO</div>
                <input type="hidden" value="reportePrepago" id="condi">
                <input type="hidden" value="reporte002" id="file">

                <div class="card mb-3" id="formReport">
                    <div class="card-header">Filtros principales</div>
                    <div class="card-body">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                                    <use xlink:href="#exclamation-triangle-fill" />
                                </svg>
                                <div><?= $mensaje ?></div>
                            </div>
                        <?php } ?>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header"><strong>Rango de fechas</strong></div>
                                    <div class="card-body d-flex align-items-center">
                                        <div class="row w-100">
                                            <div class="col-6">
                                                <label class="form-label small">Desde</label>
                                                <input type="date" id="fechaInicio" class="form-control" value="<?= date('Y-m-d'); ?>" required data-label="Fecha inicio">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Hasta</label>
                                                <input type="date" id="fechaFinal" class="form-control" value="<?= date('Y-m-d'); ?>" required data-label="Fecha final">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-12 col-lg-6">
                                <div class="card h-100 border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-filter me-2"></i><strong>Filtro de Créditos</strong>
                                    </div>
                                    <div class="card-body">
                                        <!-- Opciones de Filtro -->
                                        <div class="mb-3">
                                            <div class="form-check mb-2" x-show="permisionLevel == 3">
                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                    value="allofi" :checked="filterOption === 'all'"
                                                    @click="filterOption = 'all'">
                                                <label class="form-check-label" for="allofi">
                                                    <i class="fas fa-globe me-1"></i>Consolidado
                                                </label>
                                            </div>

                                            <div class="form-check mb-2" x-show="permisionLevel > 0">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                    value="anyofi" :checked="filterOption === 'agencia'"
                                                    @click="filterOption = 'agencia'">
                                                <label class="form-check-label" for="anyofi">
                                                    <i class="fas fa-building me-1"></i>Por Agencia
                                                </label>
                                            </div>

                                            <template x-if="permisionLevel >= 1 && permisionLevel <= 2">
                                                <div class="alert alert-info mt-2 py-2 px-3 d-flex align-items-start" role="alert">
                                                    <i class="fas fa-info-circle me-2 mt-1"></i>
                                                    <small>Para acceder a un consolidado de todas las agencias, debe solicitar al administrador que le otorgue los permisos necesarios.</small>
                                                </div>
                                            </template>

                                            <div class="form-check mb-2" x-show="permisionLevel > 1">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyregion"
                                                    value="anyregion" :checked="filterOption === 'region'"
                                                    @click="filterOption = 'region'">
                                                <label class="form-check-label" for="anyregion">
                                                    <i class="fas fa-map-marked-alt me-1"></i>Por Región
                                                </label>
                                            </div>

                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyasesor"
                                                    value="anyasesor" :checked="filterOption === 'user'"
                                                    @click="filterOption = 'user'">
                                                <label class="form-check-label" for="anyasesor">
                                                    <i class="fas fa-user me-1"></i>Por Asesor
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Selectores Condicionales -->
                                        <div x-show="filterOption === 'region'">
                                            <label for="regionid" class="form-label small fw-semibold">
                                                <i class="fas fa-map-marker-alt me-1"></i>Región
                                            </label>
                                            <select class="form-select" id="regionid" :required="filterOption==='region'">
                                                <option value="0">Seleccionar Región</option>
                                                <?php foreach (($regiones ?? []) as $region): ?>
                                                    <option value="<?= $region['id']; ?>">
                                                        <?= htmlspecialchars($region['nombre'], ENT_QUOTES); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div x-show="filterOption === 'agencia'">
                                            <label for="codofi" class="form-label small fw-semibold">
                                                <i class="fas fa-building me-1"></i>Agencia
                                            </label>
                                            <select class="form-select" id="codofi" :required="filterOption==='agencia'">
                                                <?php foreach (($agencias ?? []) as $ofi): ?>
                                                    <option value="<?= $ofi['id_agencia']; ?>"
                                                        <?= ($ofi['id_agencia'] == $idagencia) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($ofi['cod_agenc'] . ' - ' . $ofi['nom_agencia'], ENT_QUOTES); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div x-show="filterOption === 'user'">
                                            <label for="codanal" class="form-label small fw-semibold">
                                                <i class="fas fa-user-tie me-1"></i>Asesor
                                            </label>
                                            <select class="form-select" id="codanal"
                                                :required="filterOption==='user'" :disabled="filterOption !== 'user' || permisionLevel == 0">
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?= $user['id_usu']; ?>"
                                                        <?= ($user['id_usu'] == $idusuario) ? 'selected' : ''; ?>>
                                                        <?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido'], ENT_QUOTES); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <template x-if="permisionLevel == 0">
                                                <div class="alert alert-info mt-2 py-2 px-3 d-flex align-items-start" role="alert">
                                                    <i class="fas fa-info-circle me-2 mt-1"></i>
                                                    <small>Para que le aparezcan todos los usuarios, debe solicitar al administrador que le otorgue los permisos necesarios.</small>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php echo $csrf->getTokenField(); ?>

                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <button type="button" class="btn btn-danger"
                                onclick="reporteManager.generarReporte('creditos/visitas_prepago', '#formReport', { tipo: 'show' });">
                                <i class="fa-solid fa-file-pdf"></i> Reporte en PDF
                            </button>
                            <button type="button" class="btn btn-success"
                                onclick="reporteManager.generarReporte('creditos/visitas_prepago', '#formReport', { tipo: 'xlsx' });">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                            </button>
                            <button type="button" class="btn btn-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                $(document).ready(function() {
                    inicializarValidacionAutomaticaGeneric('#formReport');
                });
            </script>
        <?php
        }
    break;
    

        
}

?>

<script>
    // Helpers compartidos: evita ReferenceError cuando el módulo no cargó el JS específico.
    // No altera payload ni comportamiento de reportes(); solo agrega funciones faltantes.
    (function() {
        if (typeof window.getSelectedSelect2 !== 'function') {
            window.getSelectedSelect2 = function(selectIdOrSelector) {
                if (typeof window.jQuery === 'undefined') return null;
                var $el = null;
                if (typeof selectIdOrSelector === 'string') {
                    $el = selectIdOrSelector.startsWith('#') ? jQuery(selectIdOrSelector) : jQuery('#' + selectIdOrSelector);
                } else {
                    $el = jQuery(selectIdOrSelector);
                }
                if (!$el || $el.length === 0) return null;
                return $el.val();
            };
        }

        if (typeof window.convertir_tabla_a_datatable !== 'function') {
            window.convertir_tabla_a_datatable = function(idTabla, opciones) {
                if (typeof window.jQuery === 'undefined') return;
                if (!jQuery.fn || typeof jQuery.fn.DataTable === 'undefined') return;

                var selector = (typeof idTabla === 'string' && idTabla.startsWith('#')) ? idTabla : ('#' + idTabla);
                var $table = jQuery(selector);
                if ($table.length === 0) return;

                var defaults = {
                    destroy: true,
                    responsive: true,
                    autoWidth: false,
                    paging: true,
                    searching: true,
                    info: true,
                    ordering: true
                };

                var config = jQuery.extend(true, {}, defaults, (opciones || {}));
                // Evita doble inicialización
                if (jQuery.fn.DataTable.isDataTable($table)) {
                    $table.DataTable().destroy();
                }
                $table.DataTable(config);
            };
        }
    })();
</script>