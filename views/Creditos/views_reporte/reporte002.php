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
    case 'creditos_vencer': {
            $id = $_POST["xtra"];
            $codusu = $_SESSION['id'];
            $agencia = $_SESSION['agencia'];
        ?>
            <!-- APR_05_LstdCntsActvsDspnbls -->
            <div class="text" style="text-align:center">REPORTE DE CRÉDITOS A VENCER POR RANGO DE FECHAS</div>
            <input type="text" value="reportePrepago" id="condi" style="display: none;">
            <input type="text" value="reporte002" id="file" style="display: none;">

            <div class="card">
                <div class="card-header">REPORTE DE CRÉDITOS A VENCER POR RANGO DE FECHAS</div>
                <div class="card-body">
                    <!-- segunda linea -->
                    <div class="row d-flex align-items-stretch mb-3">
                        <!-- card para seleccionar una cuenta -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro por fecha</b></div>
                                <div class="list-group list-group-flush card-body ps-3">
                                    <div class="row mb-1">
                                        <div class="col-6">
                                            <span class="input-group-addon">Desde:</span>
                                        </div>
                                        <div class="col-6">
                                            <span class="input-group-addon">Hasta:</span>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="input-group">
                                                <input type="date" class="form-control" aria-label="Username"
                                                    aria-describedby="basic-addon1" value="<?php echo date("Y-m-d"); ?>"
                                                    id="fechaInicio">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group">
                                                <input type="date" class="form-control" aria-label="Username"
                                                    aria-describedby="basic-addon1" value="<?php echo date("Y-m-d"); ?>"
                                                    id="fechaFinal">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro de tipo de crédito</b></div>
                                <div class="card-body">
                                    <div class="row mt-3">
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_credito" id="r_credito"
                                                    value="0" checked>
                                                <label class="form-check-label" for="r_cuentas">Todos</label>
                                            </div>
                                        </div>
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_credito" id="r_credito"
                                                    value="1">
                                                <label class="form-check-label" for="r_cuentas">Individual</label>
                                            </div>
                                        </div>
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_credito" id="r_credito"
                                                    value="2">
                                                <label class="form-check-label" for="r_cuenta">Grupal</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Card para las transacciones -->
                    <div class="row mb-2">
                        <div class="col-6">
                            <div class="card">
                                <div class="card-header"><b>Filtro de Analista</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <!-- radio button para los tipos de transacciones -->
                                        <div class="col d-flex justify-content-center">
                                            <select class="form-select" aria-label="Default select example" id="analista">
                                                <option selected value="0">Todos</option>
                                                <?php
                                                $data = mysqli_query($conexion, "SELECT id_usu, CONCAT(nombre, ' ' ,apellido) AS nombre FROM tb_usuario WHERE estado=1 AND puesto='ANA'");
                                                while ($dato = mysqli_fetch_array($data, MYSQLI_ASSOC)) { ?>
                                                    <option value="<?= $dato["id_usu"]; ?>"><?= $dato["nombre"] ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card">
                                <div class="card-header text-primary"><b>Filtro de Región</b></div>
                                <div class="card-body" style="padding: 20px;">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="filter_region" id="allregion_vencer" value="allregion" checked onclick="changedisabled('#region_vencer',0)">
                                                <label class="form-check-label" for="allregion_vencer">Todas las regiones</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="filter_region" id="anyregion_vencer" value="anyregion" onclick="changedisabled('#region_vencer',1)">
                                                <label class="form-check-label" for="anyregion_vencer">Por región</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col d-flex justify-content-center">
                                                <select class="form-select" aria-label="Default select example" id="region_vencer" disabled>
                                                    <option selected value="0">Seleccione región</option>
                                                    <?php
                                                    $data_region = mysqli_query($conexion, "SELECT id, nombre FROM cre_regiones WHERE estado=1 ORDER BY nombre");
                                                    while ($dato_region = mysqli_fetch_array($data_region, MYSQLI_ASSOC)) { ?>
                                                        <option value="<?= $dato_region["id"]; ?>"><?= $dato_region["nombre"] ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                            <button type="button" id="btnSave" class="btn btn-outline-success"
                                onclick="reportes([[`fechaInicio`,`fechaFinal`],[`analista`,`region_vencer`],[`filter_credito`,`filter_region`]],`xlsx`,`creditos_a_vencer`,1)">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                            </button>

                            <button type="button" id="btnSave" class="btn btn-outline-primary"
                                onclick="reportes([[`fechaInicio`,`fechaFinal`],[`analista`,`region_vencer`],[`filter_credito`,`filter_region`]],`pdf`,`creditos_a_vencer`,0)">
                                <i class="fa-solid fa-file-pdf"></i> Reporte en PDF
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
            </div>
        <?php
        }
        break;
    case 'creditos_desembolsados': {
            $showmensaje = false;
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

                if ($userPermissions->isLevelOne(PermissionManager::REPORTE_CREDITOS_DESEMBOLSADOS)) {
                    // PERMISO 1: solo de su agencia y de los usuarios de las agencias de su agencia
                    $permissionLevel = 1;
                    $condiUser = "estado=1 AND id_agencia = ?";
                    $condiAgencia = "id_agencia = ?";
                    $condiRegion = "estado='1' AND id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?)";

                    $paramsUser = [$idagencia];
                    $paramsAgencia = [$idagencia];
                    $paramsRegion = [$idagencia];
                } elseif ($userPermissions->isLevelTwo(PermissionManager::REPORTE_CREDITOS_DESEMBOLSADOS)) {
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
                } elseif ($userPermissions->isLevelThree(PermissionManager::REPORTE_CREDITOS_DESEMBOLSADOS)) {
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

                $database->closeConnection();
                $database->openConnection(2);
                $estadosCredito = $database->selectColumns("tb_estadocredito", ['id_EstadoCredito', 'EstadoCredito'], "id_EstadoCredito IN ('A', 'D', 'E', 'F','G','L')");

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
            //++++++++++
            // $id = $_POST["xtra"];
            // $codusu = $_SESSION['id'];
            // $agencia = $_SESSION['agencia'];
        ?>

            <div class="container-fluid px-3 py-4">
                <div class="text-center mb-4">
                    <h4 class="fw-bold text-primary">REPORTE DE CRÉDITOS DESEMBOLSADOS</h4>
                </div>

                <input type="hidden" value="creditos_desembolsados" id="condi">
                <input type="hidden" value="reporte002" id="file">

                <div class="card shadow-sm" x-data="{ 
                    permisionLevel: <?= intval($permissionLevel ?? 0) ?>,
                    filterOption: <?= intval($permissionLevel ?? 0) ?> == 0 ? 'user' : 'agencia'
                }">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros de Búsqueda</h5>
                    </div>

                    <div class="card-body" id="formDesembolsos">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <div><?= $mensaje ?></div>
                            </div>
                        <?php } ?>

                        <!-- Fila 1: Fechas y Tipo de Crédito -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-secondary">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-calendar-alt me-2"></i><strong>Rango de Fechas de Desembolso</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label for="fechaInicio" class="form-label small text-muted">Desde</label>
                                                <input type="date" class="form-control" id="fechaInicio"
                                                    value="<?= date("Y-m-d"); ?>" required>
                                            </div>
                                            <div class="col-6">
                                                <label for="fechaFinal" class="form-label small text-muted">Hasta</label>
                                                <input type="date" class="form-control" id="fechaFinal"
                                                    value="<?= date("Y-m-d"); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card h-100 border-secondary">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-credit-card me-2"></i><strong>Tipo de Crédito</strong>
                                    </div>
                                    <div class="card-body d-flex align-items-center">
                                        <div class="w-100">
                                            <div class="row">
                                                <div class="col-4 text-center">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="filter_credito"
                                                            id="credito_all" value="ALL" checked>
                                                        <label class="form-check-label" for="credito_all">Todos</label>
                                                    </div>
                                                </div>
                                                <div class="col-4 text-center">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="filter_credito"
                                                            id="credito_indi" value="INDI">
                                                        <label class="form-check-label" for="credito_indi">Individual</label>
                                                    </div>
                                                </div>
                                                <div class="col-4 text-center">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="filter_credito"
                                                            id="credito_grup" value="GRUP">
                                                        <label class="form-check-label" for="credito_grup">Grupal</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fila 2: Estado y Filtros de Búsqueda -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100 border-secondary">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-check-circle me-2"></i><strong>Estado del Crédito</strong>
                                    </div>
                                    <div class="card-body d-flex align-items-center">
                                        <select class="form-select" id="estado">
                                            <option value="FG" selected>** Colocados **</option>
                                            <?php foreach (($estadosCredito ?? []) as $estado): ?>
                                                <option value="<?= htmlspecialchars($estado['id_EstadoCredito'], ENT_QUOTES); ?>">
                                                    <?= htmlspecialchars($estado['EstadoCredito'], ENT_QUOTES); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
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
                            <?php echo $csrf->getTokenField(); ?>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-danger"
                                onclick="reporteManager.generarReporte('creditos/desembolsos', '#formDesembolsos', { tipo: 'show' });">
                                <i class="fa-solid fa-file-pdf"></i> Reporte en PDF
                            </button>
                            <button type="button" class="btn btn-success"
                                onclick="reporteManager.generarReporte('creditos/desembolsos', '#formDesembolsos', { tipo: 'xlsx' });">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                            </button>
                            <!-- <button type="button" class="btn btn-success"
                                onclick="reportes([[`fechaInicio`,`fechaFinal`,`condi`],[`estado`,`codofi`,`regionid`,`codanal`],[`filter_credito`,`ragencia`]],`xlsx`,`creditos_desembolsados`,1)">
                                <i class="fas fa-file-excel me-1"></i>Descargar Excel
                            </button>
                            <button type="button" class="btn btn-danger"
                                onclick="reportes([[`fechaInicio`,`fechaFinal`,`condi`],[`estado`,`codofi`,`regionid`,`codanal`],[`filter_credito`,`ragencia`]],`pdf`,`creditos_desembolsados`,0)">
                                <i class="fas fa-file-pdf me-1"></i>Descargar PDF
                            </button> -->
                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fas fa-ban me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                <i class="fas fa-times-circle me-1"></i>Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                $(document).ready(function() {
                    inicializarValidacionAutomaticaGeneric('#formDesembolsos');
                });
            </script>
        <?php
        }
        break;
    //NEGROY filtro creditos desembolsados por agencia y usuario
    case "CRE_desembol_Filtro": {
            $id = $_POST["xtra"];
            $codusu = $_SESSION['id'];
            $agencia = $_SESSION['agencia'];
        ?>
            <!-- APR_05_LstdCntsActvsDspnbls -->
            <div class="text" style="text-align:center">REPORTE DE CRÉDITOS DESEMBOLSADOS (Autoasignada)</div>
            <input type="text" value="CRE_desembol_Filtro" id="condi" class="d-none">
            <input type="text" value="reporte002" id="file" class="d-none">
            <input type="text" value="<?= $codusu ?>" id="usuid" class="d-none">

            <div class="card">
                <div class="card-header">REPORTE DE CRÉDITOS DESEMBOLSADOS (Autoasignada)</div>
                <div class="card-body">
                    <!-- segunda linea -->
                    <div class="row d-flex align-items-stretch mb-3">
                        <!-- card para seleccionar una cuenta -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro por fecha de desembolso</b></div>
                                <div class="list-group list-group-flush card-body ps-3">
                                    <div class="row mb-1">
                                        <div class="col-6"> <span class="input-group-addon">Desde:</span> </div>
                                        <div class="col-6"> <span class="input-group-addon">Hasta:</span> </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="input-group">
                                                <input type="date" class="form-control" aria-label="Username"
                                                    aria-describedby="basic-addon1" value="<?= date("Y-m-d"); ?>"
                                                    id="fechaInicio">
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="input-group">
                                                <input type="date" class="form-control" aria-label="Username"
                                                    aria-describedby="basic-addon1" value="<?= date("Y-m-d"); ?>"
                                                    id="fechaFinal">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- consultas -->

                        <!-- card para filtrar cuentas -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro de tipo de crédito</b></div>
                                <div class="card-body">
                                    <div class="row mt-3">

                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_credito"
                                                    id="r_credito" value="ALL" checked>
                                                <label class="form-check-label" for="r_cuentadd">Todos</label>
                                            </div>
                                        </div>

                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_credito"
                                                    id="r_credito" value="INDI">
                                                <label class="form-check-label" for="r_cuentas">Crédito Individual</label>
                                            </div>
                                        </div>

                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_credito"
                                                    id="r_credito" value="GRUP">
                                                <label class="form-check-label" for="r_cuenta">Crédito Grupal</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card para las transacciones -->
                    <div class="row mb-2">
                        <div class="col-6">
                            <div class="card">
                                <div class="card-header"><b>Filtro de estado de Crédito</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <!-- radio button para los tipos de transacciones -->
                                        <div class="col d-flex justify-content-center">
                                            <select class="form-select" aria-label="Default select example" id="estado">
                                                <option selected value="0">Seleccione un estado</option>
                                                <option value="A">Solicitud</option>
                                                <option value="D">Analisis</option>
                                                <option value="E">Aprobación</option>
                                                <option value="F">Desembolsado o Vigente</option>
                                                <option value="G">Cancelado</option>
                                                <option value="L">Rechazado</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="card">
                                <div class="card-header"><b>Agencia (Autoasignada)</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <!-- radio button para los tipos de transacciones -->
                                        <div class="col d-flex justify-content-center">
                                            <select class="form-select" aria-label="Default select example" disabled
                                                id="agencia">
                                                <option disabled value="0">Seleccione una agencia</option>
                                                <option disabled value="<?= $agencia ?>" selected> <?= $agencia ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <button type="button" id="btnSave" class="btn btn-outline-success"
                                onclick="reportes([[`fechaInicio`,`fechaFinal`,`condi`,`usuid`],[`estado`,`agencia`],[`filter_credito`]],`xlsx`,`creditos_desembolsados`,1)">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel </button>

                            <button type="button" id="btnSave" class="btn btn-outline-primary"
                                onclick="reportes([[`fechaInicio`,`fechaFinal`,`condi`,`usuid`],[`estado`,`agencia`],[`filter_credito`]],`pdf`,`creditos_desembolsados`,0)">
                                <i class="fa-solid fa-file-pdf"></i> Reporte en PDF </button>

                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"> <i
                                    class="fa-solid fa-ban"></i> Cancelar</button>

                            <button type="button" class="btn btn-outline-warning" onclick="salir()"> <i
                                    class="fa-solid fa-circle-xmark"></i> Salir </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        }
        break;
    //NEGROY filtro creditos VISITAS por agencia y usuario
    case "Prepago_Filtro": {
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
                <div class="text text-center h5 mb-3">REPORTE DE VISITAS PREPAGO (Autoasignada)</div>
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
    case 'incobrables':
        ?>
        <input type="text" id="file" value="reporte001" style="display: none;">
        <input type="text" id="condi" value="cartera_fuenteFondos" style="display: none;">
        <div class="text" style="text-align:center">CREDITOS INCOBRABLES</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Agencias</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                            <?php
                                            $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                            ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf"
                                                value="allf" checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf"
                                                value="anyf" onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
                                                <?php
                                                $fons = mysqli_query($conexion, "SELECT * FROM `ctb_fuente_fondos` where estado=1");
                                                while ($fon = mysqli_fetch_array($fons)) {
                                                    echo '<option value="' . $fon['id'] . '">' . $fon['descripcion'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="fondoid">Fondos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Región</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_region" id="allregion_incob"
                                                value="allregion" checked onclick="changedisabled('#region_incob',0)">
                                            <label for="allregion_incob" class="form-check-label">Todas las regiones</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_region" id="anyregion_incob"
                                                value="anyregion" onclick="changedisabled('#region_incob',1)">
                                            <label for="anyregion_incob" class="form-check-label">Por región</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="region_incob" disabled>
                                                <option value="0" selected disabled>Seleccionar región</option>
                                                <?php
                                                $regions = mysqli_query($conexion, "SELECT id, nombre FROM cre_regiones WHERE estado=1 ORDER BY nombre");
                                                while ($reg = mysqli_fetch_array($regions)) {
                                                    echo '<option value="' . $reg['id'] . '">' . $reg['nombre'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="region_incob">Región</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Cartera en pdf"
                            onclick="reportes([[],[`codofi`,`fondoid`,`region_incob`],[`ragencia`,`rfondos`,`filter_region`],[]],`pdf`,`incobrables`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                            onclick="reportes([[],[`codofi`,`fondoid`,`region_incob`],[`ragencia`,`rfondos`,`filter_region`],[]],`xlsx`,`incobrables`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
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
        </div>
    <?php
        break;
    case 'juridicos':
    ?>
        <input type="text" id="file" value="reporte001" style="display: none;">
        <input type="text" id="condi" value="cartera_fuenteFondos" style="display: none;">
        <div class="text" style="text-align:center">CARTERA JURÍDICA</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Agencias</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                            <?php
                                            $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                            ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf"
                                                value="allf" checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf"
                                                value="anyf" onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
                                                <?php
                                                $fons = mysqli_query($conexion, "SELECT * FROM `ctb_fuente_fondos` where estado=1");
                                                while ($fon = mysqli_fetch_array($fons)) {
                                                    echo '<option value="' . $fon['id'] . '">' . $fon['descripcion'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="fondoid">Fondos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Región</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_region" id="allregion_jur"
                                                value="allregion" checked onclick="changedisabled('#region_jur',0)">
                                            <label for="allregion_jur" class="form-check-label">Todas las regiones</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_region" id="anyregion_jur"
                                                value="anyregion" onclick="changedisabled('#region_jur',1)">
                                            <label for="anyregion_jur" class="form-check-label">Por región</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="region_jur" disabled>
                                                <option value="0" selected disabled>Seleccionar región</option>
                                                <?php
                                                $regions = mysqli_query($conexion, "SELECT id, nombre FROM cre_regiones WHERE estado=1 ORDER BY nombre");
                                                while ($reg = mysqli_fetch_array($regions)) {
                                                    echo '<option value="' . $reg['id'] . '">' . $reg['nombre'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="region_jur">Región</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Cartera en pdf"
                            onclick="reportes([[],[`codofi`,`fondoid`,`region_jur`],[`ragencia`,`rfondos`,`filter_region`],[]],`pdf`,`juridicos`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                            onclick="reportes([[],[`codofi`,`fondoid`,`region_jur`],[`ragencia`,`rfondos`,`filter_region`],[]],`xlsx`,`juridicos`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
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
        </div>
    <?php
        break;
    case 'prepago_recuperado':
        $showmensaje = false;
        try {
            $database->openConnection();
            $agencias = $database->getAllResults("SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                    ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No hay agencias");
            }
            $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondos)) {
                $showmensaje = true;
                throw new Exception("No hay fondos");
            }
            $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], "estado=1 AND puesto='ANA'");
            if (empty($users)) {
                $showmensaje = true;
                throw new Exception("No hay Analistas");
            }
            $status = true;
        } catch (Exception $e) {
            $status = false;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="text" id="file" value="reporte002" style="display: none;">
        <input type="text" id="condi" value="prepago_recuperado" style="display: none;">
        <div class="text" style="text-align:center">PROYECCION VS RECUPERADO</div>
        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div>
                        <?= $mensaje ?>
                    </div>
                </div>
            <?php }  ?>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Agencias</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf"
                                                value="allf" checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf"
                                                value="anyf" onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
                                                <?php
                                                foreach ($fondos as $fon) {
                                                    echo '<option value="' . $fon['id'] . '">' . $fon['descripcion'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="fondoid">Fondos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header"><b>Filtro por fecha</b></div>
                            <div class="list-group list-group-flush card-body ps-3">
                                <div class="row mb-1">
                                    <div class="col-6">
                                        <span class="input-group-addon">Desde:</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="input-group-addon">Hasta:</span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="date" class="form-control" aria-describedby="basic-addon1"
                                                value="<?php echo date("Y-m-d"); ?>" id="fecinicio">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="date" class="form-control" aria-describedby="basic-addon1"
                                                value="<?php echo date("Y-m-d"); ?>" id="fecfinal">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card">
                            <div class="card-header"><b>Filtro de Ejecutivo</b></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rasesor" id="allasesor"
                                                value="allasesor" checked onclick="changedisabled(`#ejecutivo`,0)">
                                            <label for="allasesor" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rasesor" id="anyasesor"
                                                value="anyasesor" onclick="changedisabled(`#ejecutivo`,1)">
                                            <label for="anyasesor" class="form-check-label"> Por Ejecutivo</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col d-flex justify-content-center">
                                        <select class="form-select" aria-label="Default select example" id="ejecutivo"
                                            disabled>
                                            <option selected value="0">Seleccione un ejecutivo</option>
                                            <?php
                                            foreach ($users as $user) {
                                                echo '<option value="' . $user['id_usu'] . '">' . $user['nombre'] . " " . $user['apellido'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Estado Actual</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                value="allstatus" checked>
                                            <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                            <label for="F" class="form-check-label"> Vigentes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                            <label for="G" class="form-check-label"> Cancelados</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header"><b>Tipo Créditos</b></div>
                            <div class="card-body">
                                <div class="row mt-3">
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipoentidad" id="ctodos"
                                                value="call" checked>
                                            <label class="form-check-label" for="ctodos">Todos</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipoentidad" id="cindi"
                                                value="INDI">
                                            <label class="form-check-label" for="cindi">Individuales</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipoentidad" id="cgrup"
                                                value="GRUP">
                                            <label class="form-check-label" for="cgrup">Grupales</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <?php if ($status) { ?>
                            <button type="button" class="btn btn-outline-danger" title="Cartera en pdf"
                                onclick="reportes([[`fecinicio`,`fecfinal`],[`codofi`,`fondoid`,`ejecutivo`],[`ragencia`,`rfondos`,`rasesor`,`tipoentidad`,`status`],[]],`pdf`,`prepago_recuperado`,0)">
                                <i class="fa-solid fa-file-pdf"></i> Pdf
                            </button>
                            <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                onclick="reportes([[`fecinicio`,`fecfinal`],[`codofi`,`fondoid`,`ejecutivo`],[`ragencia`,`rfondos`,`rasesor`,`tipoentidad`,`status`],[]],`xlsx`,`prepago_recuperado`,1)">
                                <i class="fa-solid fa-file-excel"></i>Excel
                            </button>
                        <?php }  ?>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'reporte_recibos_caja':
        $showmensaje = false;
        try {
            $database->openConnection();
            $agencias = $database->getAllResults("SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                    ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No hay agencias");
            }
            $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondos)) {
                $showmensaje = true;
                throw new Exception("No hay fondos");
            }
            $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], "estado=1 AND puesto='ANA'");
            if (empty($users)) {
                $showmensaje = true;
                throw new Exception("No hay Analistas");
            }
            $status = true;
        } catch (Exception $e) {
            $status = false;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="text" id="file" value="reporte002" style="display: none;">
        <input type="text" id="condi" value="reporte_recibos_caja" style="display: none;">
        <div class="text" style="text-align:center">REPORTE DE RECIBOS DE CAJA</div>
        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div>
                        <?= $mensaje ?>
                    </div>
                </div>
            <?php }  ?>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Agencias</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf"
                                                value="allf" checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf"
                                                value="anyf" onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
                                                <?php
                                                foreach ($fondos as $fon) {
                                                    echo '<option value="' . $fon['id'] . '">' . $fon['descripcion'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="fondoid">Fondos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-4">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header"><b>Filtro por fecha</b></div>
                            <div class="list-group list-group-flush card-body ps-3">
                                <div class="row mb-1">
                                    <div class="col-6">
                                        <span class="input-group-addon">Desde:</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="input-group-addon">Hasta:</span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="date" class="form-control" aria-describedby="basic-addon1"
                                                value="<?php echo date("Y-m-d"); ?>" id="fecinicio">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <input type="date" class="form-control" aria-describedby="basic-addon1"
                                                value="<?php echo date("Y-m-d"); ?>" id="fecfinal">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Estado Actual</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                value="allstatus" checked>
                                            <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                            <label for="F" class="form-check-label"> Vigentes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                            <label for="G" class="form-check-label"> Cancelados</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header"><b>Tipo Créditos</b></div>
                            <div class="card-body">
                                <div class="row mt-3">
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipoentidad" id="ctodos"
                                                value="call" checked>
                                            <label class="form-check-label" for="ctodos">Todos</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipoentidad" id="cindi"
                                                value="INDI">
                                            <label class="form-check-label" for="cindi">Individuales</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="tipoentidad" id="cgrup"
                                                value="GRUP">
                                            <label class="form-check-label" for="cgrup">Grupales</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <?php if ($status) { ?>
                            <button type="button" class="btn btn-outline-danger" title="REPORTE DE RECIBOS DE CAJA PDF"
                                onclick="reportes([[`fecinicio`,`fecfinal`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`,`tipoentidad`,`status`],[]],`pdf`,`reporte_recibos_caja`,0)">
                                <i class="fa-solid fa-file-pdf"></i> Pdf
                            </button>
                            <button type="button" class="btn btn-outline-success" title="REPORTE DE RECIBOS DE CAJA XlSX"
                                onclick="reportes([[`fecinicio`,`fecfinal`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`,`tipoentidad`,`status`],[]],`xlsx`,`reporte_recibos_caja`,1)">
                                <i class="fa-solid fa-file-excel"></i>Excel
                            </button>
                        <?php }  ?>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php
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