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

require_once __DIR__ . '/../../../includes/Config/database.php';
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../includes/Config/PermissionHandler.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

use Micro\Generic\PermissionManager;
use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

include __DIR__ . '/../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

$condi = $_POST["condi"];

switch ($condi) {
    case 'cartera_fuenteFondos':

        $showButtonCustomExcel = $_ENV['SHOW_BUTTON_CUSTOM_EXCEL'] ?? false;

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

            if ($userPermissions->isLevelOne(PermissionManager::SALDOS_CARTERA)) {
                // PERMISO 1: solo de su agencia y de los usuarios de las agencias de su agencia
                $permissionLevel = 1;
                $condiUser = "estado=1 AND id_agencia = ?";
                $condiAgencia = "id_agencia = ?";
                $condiRegion = "estado='1' AND id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?)";

                $paramsUser = [$idagencia];
                $paramsAgencia = [$idagencia];
                $paramsRegion = [$idagencia];
            } elseif ($userPermissions->isLevelTwo(PermissionManager::SALDOS_CARTERA)) {
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
            } elseif ($userPermissions->isLevelThree(PermissionManager::SALDOS_CARTERA)) {
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

            $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron fondos disponibles.");
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
        // echo "<pre>";
        // echo print_r($agencias);
        // echo "</pre>";
?>
        <input type="text" id="file" value="reporte001" style="display: none;">
        <input type="text" id="condi" value="cartera_fuenteFondos" style="display: none;">
        <div class="text" style="text-align:center">SALDOS DE CARTERA</div>
        <div class="card shadow-sm">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center m-3" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php
                // return;
            } ?>

            <div class="card-body">
                <!-- Filtros Section -->
                <div class="container-fluid" id="formCarteraFondos"
                    x-data="{
                    filterOption : 'agencia',
                    permisionLevel : <?= $permissionLevel ?? 0 ?>
                }">
                    <div class="row g-3">
                        <!-- Filtro por Agencia -->
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3" x-show="permisionLevel!==0">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-building me-2"></i>Filtro por Agencia
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-2" x-show="permisionLevel == 3">
                                        <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                            value="allofi" :checked="filterOption === 'all'" @click="filterOption = 'all'">
                                        <label for="allofi" class="form-check-label">Consolidado</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                            value="anyofi" :checked="filterOption === 'agencia'" @click="filterOption = 'agencia'">
                                        <label for="anyofi" class="form-check-label">Por Agencia</label>
                                        <i x-show="permisionLevel <= 2"
                                            class="fa-solid fa-circle-info ms-2 text-info"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                    </div>
                                    <div class="form-check mb-2" x-show="permisionLevel > 1">
                                        <input class="form-check-input" type="radio" name="ragencia" id="anyregion"
                                            value="anyregion" :checked="filterOption === 'region'" @click="filterOption = 'region'">
                                        <label for="anyregion" class="form-check-label">Por region</label>
                                    </div>
                                    <div class="mb-2" x-show="filterOption ==='region'">
                                        <label class="form-label small text-muted" for="regionid">Región</label>
                                        <select class="form-select" id="regionid">
                                            <option value="0">Seleccionar Región</option>
                                            <?php foreach (($regiones ?? []) as $region) {
                                                echo "<option value='{$region['id']}'>{$region['nombre']}</option>";
                                            } ?>
                                        </select>

                                    </div>
                                    <div class="mb-2" x-show="filterOption==='agencia'">
                                        <label for="codofi" class="form-label small text-muted">Agencia</label>
                                        <select class="form-select" id="codofi">
                                            <?php foreach (($agencias ?? []) as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Filtro por Estado -->
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                            <div class="card h-100 border-success">
                                <div class="card-header bg-success text-white">
                                    <i class="fas fa-check-circle me-2"></i>Filtro por Estado
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="status" id="allstatus"
                                            value="allstatus" checked>
                                        <label for="allstatus" class="form-check-label">Vigentes y Cancelados</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                        <label for="F" class="form-check-label">Vigentes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                        <label for="G" class="form-check-label">Cancelados</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro por Fuente de Fondos -->
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3" x-data="{ optionFondo: 'all' }">
                            <div class="card h-100 border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <i class="fas fa-wallet me-2"></i>Fuente de Fondos
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                            checked @click="optionFondo='all'">
                                        <label for="allf" class="form-check-label">Todo</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                            @click="optionFondo='any'">
                                        <label for="anyf" class="form-check-label">Por Fuente de fondos</label>
                                    </div>
                                    <div class="form-floating" x-show="optionFondo=='any'">
                                        <select class="form-select" id="fondoid">
                                            <?php foreach (($fondos ?? []) as $fondo) {
                                                echo "<option value='{$fondo['id']}'>{$fondo['descripcion']}</option>";
                                            } ?>
                                        </select>
                                        <label for="fondoid">Seleccionar Fondo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro por Fecha -->
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                            <div class="card h-100 border-info">
                                <div class="card-header bg-info text-white">
                                    <i class="fas fa-calendar-alt me-2"></i>Fecha de Proceso
                                </div>
                                <div class="card-body d-flex align-items-center">
                                    <div class="w-100">
                                        <label for="ffin" class="form-label">Seleccionar Fecha</label>
                                        <input type="date" class="form-control" id="ffin" value="<?= date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro por Asesor -->
                        <div class="col-12 col-sm-6 col-lg-4 col-xl-3" x-data="{ filterUser:  permisionLevel===0 ? 'any' :  'all' }">
                            <div class="card h-100 border-secondary">
                                <div class="card-header bg-secondary text-white">
                                    <i class="fas fa-user-tie me-2"></i>Filtro por Asesor
                                </div>
                                <div class="card-body">
                                    <div class="form-check mb-2" x-show="permisionLevel!==0">
                                        <input class="form-check-input" type="radio" name="rasesor" id="allasesor"
                                            :checked="filterUser === 'all'" @click="filterUser = 'all'"
                                            value="allasesor">
                                        <label for="allasesor" class="form-check-label">Todos los disponibles</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="rasesor" id="anyasesor"
                                            :checked="filterUser === 'any'" @click="filterUser = 'any'"
                                            value="anyasesor">
                                        <label for="anyasesor" class="form-check-label">Por Asesor</label>
                                    </div>
                                    <div class="mb-2" x-show="filterUser ==='any'">
                                        <label for="codanal" class="form-label small text-muted">
                                            Asesor
                                            <i x-show="permisionLevel==0"
                                                class="fa-solid fa-circle-info ms-1 text-info"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="Para que le aparezcan todos los usuarios, debe solicitar al administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </label>
                                        <select class="form-select" id="codanal" :disabled="filterUser !== 'any' || permisionLevel==0">
                                            <?php
                                            foreach ($users as $user) {
                                                $selected = ($user['id_usu'] == $idusuario) ? "selected" : "";
                                                echo '<option value="' . $user['id_usu'] . '" ' . $selected . '>' . $user['nombre'] . ' ' . $user["apellido"] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- NUEVO: Selector de Columnas (solo visible para Excel) -->
                        <div class="col-12 mt-3" id="columnasExcelSection" style="display: none;">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-columns me-2"></i>Columnas para Excel</span>
                                    <button type="button" class="btn btn-sm btn-light" onclick="toggleAllColumns()">
                                        <i class="fas fa-check-double"></i> Marcar/Desmarcar todo
                                    </button>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">
                                        <i class="fas fa-info-circle"></i>
                                        Las columnas marcadas con <span class="badge bg-primary">PDF</span> siempre se incluyen.
                                        Selecciona columnas adicionales para el reporte de Excel.
                                    </p>
                                    <div class="row g-2">
                                        <!-- Columnas básicas (siempre incluidas) -->
                                        <div class="col-12 mb-2">
                                            <strong class="text-primary">Columnas Básicas (incluidas por defecto):</strong>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    Crédito <span class="badge bg-primary">PDF</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    Cliente <span class="badge bg-primary">PDF</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    Otorgamiento <span class="badge bg-primary">PDF</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    Vencimiento <span class="badge bg-primary">PDF</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                <label class="form-check-label">
                                                    Montos <span class="badge bg-primary">PDF</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Columnas adicionales (opcionales) -->
                                        <div class="col-12 mb-2 mt-3">
                                            <strong class="text-success">Columnas Adicionales (opcionales):</strong>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="fondo" id="col_fondo">
                                                <label class="form-check-label" for="col_fondo">Fondo</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="codcliente" id="col_codcliente">
                                                <label class="form-check-label" for="col_codcliente">Cód. Cliente</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="genero" id="col_genero">
                                                <label class="form-check-label" for="col_genero">Género</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="fecha_nacimiento" id="col_fecha_nacimiento">
                                                <label class="form-check-label" for="col_fecha_nacimiento">Fecha Nacimiento</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="direccion" id="col_direccion">
                                                <label class="form-check-label" for="col_direccion">Dirección</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="municipio" id="col_municipio">
                                                <label class="form-check-label" for="col_municipio">Municipio</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="telefonos" id="col_telefonos">
                                                <label class="form-check-label" for="col_telefonos">Teléfonos</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="ultimo_pago" id="col_ultimo_pago">
                                                <label class="form-check-label" for="col_ultimo_pago">Último Pago</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="monto_cuota" id="col_monto_cuota">
                                                <label class="form-check-label" for="col_monto_cuota">Monto Cuota</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="interes_total" id="col_interes_total">
                                                <label class="form-check-label" for="col_interes_total">Interés Total</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="otros" id="col_otros">
                                                <label class="form-check-label" for="col_otros">Otros Pagos</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="tasas" id="col_tasas">
                                                <label class="form-check-label" for="col_tasas">Tasas</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="producto" id="col_producto">
                                                <label class="form-check-label" for="col_producto">Producto</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="asesor" id="col_asesor">
                                                <label class="form-check-label" for="col_asesor">Asesor</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="tipo_credito" id="col_tipo_credito">
                                                <label class="form-check-label" for="col_tipo_credito">Tipo Crédito</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="destino" id="col_destino">
                                                <label class="form-check-label" for="col_destino">Destino</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="frecuencia" id="col_frecuencia">
                                                <label class="form-check-label" for="col_frecuencia">Frecuencia</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="num_cuotas" id="col_num_cuotas">
                                                <label class="form-check-label" for="col_num_cuotas">No. Cuotas</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="fallas" id="col_fallas">
                                                <label class="form-check-label" for="col_fallas">Fallas</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="sector_economico" id="col_sector_economico">
                                                <label class="form-check-label" for="col_sector_economico">Sector Económico</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="actividad_economica" id="col_actividad_economica">
                                                <label class="form-check-label" for="col_actividad_economica">Actividad Económica</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="garantia" id="col_garantia">
                                                <label class="form-check-label" for="col_garantia">Tipo Garantía</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input column-extra" type="checkbox"
                                                    value="pep_cpe" id="col_pep_cpe">
                                                <label class="form-check-label" for="col_pep_cpe">PEP/CPE</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-danger" title="Cartera en pdf"
                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`],[<?php echo $idusuario; ?>]],`pdf`,`cartera_fondos`,0)">
                                <i class="fa-solid fa-file-pdf me-1"></i>Generar PDF
                            </button>
                            <button type="button" class="btn btn-success" title="Cartera en Excel"
                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`],[<?php echo $idusuario; ?>]],`xlsx`,`cartera_fondos`,1)">
                                <i class="fa-solid fa-file-excel me-1"></i>Exportar Excel
                            </button>

                            <?php if ($showButtonCustomExcel): ?>
                                <button type="button" class="btn btn-success" title="Cartera en Excel"
                                    onclick="generarExcelConColumnas()">
                                    <i class="fa-solid fa-file-excel me-1"></i>Exportar Excel Personalizado (Beta)
                                </button>
                                <button type="button" class="btn btn-outline-info" title="Configurar columnas Excel"
                                    onclick="toggleColumnasExcel()">
                                    <i class="fa-solid fa-cog me-1"></i>Configurar Columnas
                                    <i class="fa-solid fa-crown ms-1 text-warning"></i>
                                </button>
                            <?php endif; ?>

                            <button type="button" class="btn btn-success" title="Resumen por grupo"
                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`],[]],`xlsx`,`cartera_fondos_resumen`,1)">
                                <i class="fa-solid fa-file-excel me-1"></i>Cartera Clasificada
                            </button>

                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark me-1"></i>Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            function toggleColumnasExcel() {
                const section = document.getElementById('columnasExcelSection');
                section.style.display = section.style.display === 'none' ? 'block' : 'none';
            }

            function toggleAllColumns() {
                const checkboxes = document.querySelectorAll('.column-extra');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkboxes.forEach(cb => cb.checked = !allChecked);
            }

            function getSelectedColumns() {
                const checkboxes = document.querySelectorAll('.column-extra:checked');
                return Array.from(checkboxes).map(cb => cb.value);
            }

            function generarExcelConColumnas() {
                const columnasExtra = getSelectedColumns();
                reportes(
                    [
                        [`ffin`],
                        [`codofi`, `fondoid`, `codanal`, `regionid`],
                        [`ragencia`, `rfondos`, `status`, `rasesor`, `rregion`],
                        [columnasExtra]
                    ],
                    `xlsx`,
                    `cartera_fondos_columns`,
                    1
                );
            }
        </script>
    <?php
        break;
    case 'ingresos':
        $status = false;
        try {
            $database->openConnection();

            $condiPermission = "";
            $parametrosPermission = [];
            $condiPermission2 = "estado = 1";
            $userPermissions = new PermissionManager($idusuario);

            if (!$userPermissions->isLevelTwo(PermissionManager::INGRESOS_DIARIOS)) {
                $condiPermission = " WHERE usu.id_agencia = ? ";
                $parametrosPermission[] = $idagencia;
                $condiPermission2 = "estado = 1 AND id_agencia = ? ";
            }

            // $agencias = $database->getAllResults("SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
            //                                                 ON ofi.id_agencia = usu.id_agencia $condiPermission GROUP BY ofi.id_agencia", $parametrosPermission);
            $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "cod_agenc", "nom_agencia"]);
            if (empty($agencias)) {
                throw new SoftException("No hay agencias");
            }

            $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondos)) {
                throw new SoftException("No hay fondos");
            }

            // $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], $condiPermission2, $parametrosPermission);
            $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], "estado=1");
            if (empty($users)) {
                throw new SoftException("No hay usuarios");
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
        <input type="text" id="file" value="reporte001" style="display: none;">
        <input type="text" id="condi" value="ingresos" style="display: none;">
        <div class="text" style="text-align:center">INGRESOS DIARIOS</div>
        <div class="card shadow-sm">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center m-3" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div><?= $mensaje ?></div>
                </div>
            <?php } ?>

            <div class="card-body">
                <!-- Filtros Section -->
                <div class="container-fluid" id="formReport">
                    <div class="row g-3">
                        <!-- Filtro por Agencia/Usuario -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-building me-2"></i>Filtro por cajas
                                </div>
                                <div class="card-body" x-data="{option:'anyuser'}">
                                    <div class="form-check mb-2" <?= ($userPermissions->isLevelTwo(PermissionManager::INGRESOS_DIARIOS)) ? "" : "hidden"; ?>>
                                        <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                            value="allofi" @click="option='allofi'">
                                        <label for="allofi" class="form-check-label">Consolidado</label>
                                    </div>
                                    <div class="form-check mb-2" <?= ($userPermissions->hasNoAccess(PermissionManager::INGRESOS_DIARIOS)) ? "hidden" : ""; ?>>
                                        <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                            value="anyofi" @click="option='anyofi'">
                                        <label for="anyofi" class="form-check-label">Cajas agencia</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="ragencia" id="anyuser"
                                            value="anyuser" checked @click="option='anyuser'">
                                        <label for="anyuser" class="form-check-label">Caja usuarios</label>
                                    </div>

                                    <div x-show="option=='anyofi' || option=='allofi'">
                                        <label for="codofi" class="form-label small text-muted">Cajas agencia</label>
                                        <select class="form-select" id="codofi"
                                            <?= ($userPermissions->isLevelOne(PermissionManager::INGRESOS_DIARIOS)) ? 'disabled' : ''; ?>
                                            :disabled="option=='allofi'" :required="option=='anyofi'">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                if ((!$userPermissions->isLevelTwo(PermissionManager::INGRESOS_DIARIOS)) && ($ofi['id_agencia'] != $idagencia)) {
                                                    continue;
                                                }
                                                $selected = ($ofi['id_agencia'] == $_SESSION["id_agencia"]) ? "selected" : "";
                                                echo "<option value='{$ofi['id_agencia']}' {$selected}>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div x-show="option=='anyuser'">
                                        <label for="codusu" class="form-label small text-muted">Caja usuarios</label>
                                        <select class="form-select" id="codusu" :required="option=='anyuser'"
                                            <?= ($userPermissions->hasNoAccess(PermissionManager::INGRESOS_DIARIOS)) ? 'disabled' : ''; ?>>
                                            <?php
                                            foreach ($users as $user) {
                                                if (($userPermissions->isLevelOne(PermissionManager::INGRESOS_DIARIOS)) && ($user['id_agencia'] != $idagencia)) {
                                                    continue;
                                                }
                                                $selected = ($user['id_usu'] == $idusuario) ? "selected" : "";
                                                echo "<option value='{$user['id_usu']}' {$selected}>{$user['nombre']} {$user['apellido']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro por Fuente de Fondos -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100 border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <i class="fas fa-wallet me-2"></i>Fuente de Fondos
                                </div>
                                <div class="card-body" x-data="{option:'allf'}">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="rfondos" id="allf"
                                            value="allf" checked @click="option='allf'">
                                        <label for="allf" class="form-check-label">Todo</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="rfondos" id="anyf"
                                            value="anyf" @click="option='anyf'">
                                        <label for="anyf" class="form-check-label">Por Fuente de fondos</label>
                                    </div>
                                    <div class="form-floating">
                                        <select class="form-select" id="fondoid" :disabled="option=='allf'" :required="option=='anyf'">
                                            <?php
                                            foreach ($fondos as $fon) {
                                                echo "<option value='{$fon['id']}'>{$fon['descripcion']}</option>";
                                            }
                                            ?>
                                        </select>
                                        <label for="fondoid">Seleccionar Fondo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro por Fechas -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100 border-info">
                                <div class="card-header bg-info text-white">
                                    <i class="fas fa-calendar-alt me-2"></i>Rango de Fechas
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="finicio" class="form-label">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="finicio"
                                            value="<?= date("Y-m-d"); ?>" required>
                                    </div>
                                    <div>
                                        <label for="ffin" class="form-label">Fecha Fin</label>
                                        <input type="date" class="form-control" id="ffin"
                                            value="<?= date("Y-m-d"); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro por Créditos -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100 border-success">
                                <div class="card-header bg-success text-white">
                                    <i class="fas fa-credit-card me-2"></i>Filtro por Créditos
                                </div>
                                <div class="card-body" x-data="{option:'allCreditos'}">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="radCreditos"
                                            value="allCreditos" id="allCreditos" @click="option='allCreditos'" checked>
                                        <label for="allCreditos" class="form-check-label">Incluir todos</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="radCreditos"
                                            value="creAgencia" id="creAgencia" @click="option='creAgencia'">
                                        <label for="creAgencia" class="form-check-label">Por Agencia</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="radCreditos"
                                            value="creUser" id="creUser" @click="option='creUser'">
                                        <label for="creUser" class="form-check-label">Por Ejecutivo</label>
                                    </div>

                                    <div x-show="option=='creAgencia'">
                                        <label for="creAgenciaSelect" class="form-label small text-muted">Oficina</label>
                                        <select class="form-select" id="creAgenciaSelect" :required="option=='creAgencia'">
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                echo "<option value='{$ofi['id_agencia']}'>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div x-show="option=='creUser'">
                                        <label for="creUserSelect" class="form-label small text-muted">Ejecutivo</label>
                                        <select class="form-select" id="creUserSelect" :required="option=='creUser'">
                                            <?php
                                            foreach ($users as $user) {
                                                $selected = ($user['id_usu'] == $idusuario) ? "selected" : "";
                                                echo "<option value='{$user['id_usu']}' {$selected}>{$user['nombre']} {$user['apellido']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php echo $csrf->getTokenField(); ?>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <!-- <button type="button" class="btn btn-primary" title="Ver reporte"
                                onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`codusu`,`creAgenciaSelect`,`creUserSelect`],[`rfondos`,`ragencia`,`radCreditos`],[]],`show`,`ingresos_diarios`,0,'DFECPRO','NMONTO',2,'Montos',0)">
                                <i class="fa-solid fa-eye me-1"></i>Ver
                            </button>
                            <button type="button" class="btn btn-danger" title="Reporte en PDF"
                                onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`codusu`,`creAgenciaSelect`,`creUserSelect`],[`rfondos`,`ragencia`,`radCreditos`],[]],`pdf`,`ingresos_diarios`,0)">
                                <i class="fa-solid fa-file-pdf me-1"></i>Generar PDF
                            </button>
                            <button type="button" class="btn btn-success" title="Reporte en Excel"
                                onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`codusu`,`creAgenciaSelect`,`creUserSelect`],[`rfondos`,`ragencia`,`radCreditos`],[]],`xlsx`,`ingresos_diarios`,1)">
                                <i class="fa-solid fa-file-excel me-1"></i>Exportar Excel
                            </button> -->

                            <button type="button" class="btn btn-primary"
                                onclick="verReporteJSON()">
                                <i class="fa-solid fa-eye"></i> Ver
                            </button>
                            <button type="button" class="btn btn-danger"
                                onclick="reporteManager.generarReporte('creditos/ingresos-diarios', '#formReport', { tipo: 'show' });">
                                <i class="fa-solid fa-file-pdf"></i> Reporte en PDF
                            </button>
                            <button type="button" class="btn btn-success"
                                onclick="reporteManager.generarReporte('creditos/ingresos-diarios', '#formReport', { tipo: 'xlsx' });">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark me-1"></i>Salir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Resultados -->
                <div id="divshow" class="container-fluid mt-4" style="display: none;">
                    <div class="table-responsive">
                        <table id="tbdatashow" class="table table-sm table-striped table-hover small">
                            <thead class="table-light">
                                <tr></tr>
                            </thead>
                        </table>
                    </div>
                </div>

                <!-- Gráfico -->
                <div id="divshowchart" class="container-fluid mt-4" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                inicializarValidacionAutomaticaGeneric('#formReport');
            });

            function verReporteJSON() {
                reporteManager.generarReporte('creditos/ingresos-diarios', '#formReport', {
                    tipo: 'json',
                    mostrarTabla: true,
                    mostrarGrafica: true,
                    configTabla: {
                        encabezados: ['Fecha', 'FormaPago', 'NumDoc', 'Total', 'KP', 'INT', 'MOR', 'OTR'],
                        keys: ['DFECPRO', 'formaPago', 'CNUMING', 'NMONTO', 'KP', 'INTERES', 'MORA', 'OTR']
                    },
                    configGrafica: {
                        titulo: 'Ingresos Diarios Desglosados',
                        type: 'bar',
                        dataKey: 'datosGrafica',
                        labels: 'fecha',
                        datasets: [{
                                label: 'Capital',
                                key: 'capital',
                                color: 'rgba(54, 162, 235, 0.8)'
                            },
                            {
                                label: 'Interés',
                                key: 'interes',
                                color: 'rgba(255, 206, 86, 0.8)'
                            },
                            {
                                label: 'Mora',
                                key: 'mora',
                                color: 'rgba(255, 99, 132, 0.8)'
                            }
                        ]
                    }
                });
            }
        </script>
    <?php
        break;
    case 'recibosanulados':
        $puesto = $_SESSION['puesto'];
        $flag = ($puesto == 'ADM' || $puesto == 'GER') ? true : false;

        $puestouser = $_SESSION["puesto"];
        // $puestouser="LOG";
        $puestosP = array("ADM", "GER", "AUD");
        $hidden = (in_array($puestouser, $puestosP)) ? false : true;
    ?>
        <style>
            .small-font th,
            .small-font td {
                font-size: 12px;
                /* Ajusta este valor según tus necesidades */
            }
        </style>
        <input type="text" id="file" value="reporte001" style="display: none;">
        <input type="text" id="condi" value="ingresos" style="display: none;">
        <div class="text" style="text-align:center">RECIBOS ANULADOS</div>
        <div class="card">
            <!-- <div class="card-header">FILTROS</div> -->
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row m-3">
                        <div class="col-sm-12 col-md-6 col-lg-4">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check" <?php echo ($hidden) ? "hidden" : ""; ?>>
                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                    value="allofi" onclick="changedisabled(`#codofi`,0)">
                                                <label for="allofi" class="form-check-label">Consolidado </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                    value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                                <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <span class="input-group-addon">Agencia</span>
                                            <select class="form-select" id="codofi" <?php echo ($hidden) ? 'disabled' : ''; ?>>
                                                <?php
                                                $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                while ($ofi = mysqli_fetch_array($ofis)) {
                                                    $selected = ($ofi['id_agencia'] == $_SESSION["id_agencia"]) ? "selected" : "";
                                                    echo '<option value="' . $ofi['id_agencia'] . '" ' . $selected . '>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                }
                                                ?>
                                            </select>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6 col-lg-4">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6 col-lg-4">
                            <div class="card" style="height: 100%;">
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
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-primary" title="Reporte de ingresos"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`],[]],`show`,`ingresos_anulados`,0,'DFECPRO','NMONTO',2,'Montos')">
                            <i class="fa-solid fa-eye"></i> Ver
                        </button>
                        <button type="button" class="btn btn-outline-danger" title="Reporte de ingresos en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`],[]],`pdf`,`ingresos_anulados`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Reporte de ingresos en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`],[]],`xlsx`,`ingresos_anulados`,1)">
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
                <div id="divshow" class="container contenedort" style="display: none;">
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-sm small-font">
                            <thead>
                                <tr>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>
        <script>
            // $(document).ready(function() {
            //     reportes([
            //         [`finicio`, `ffin`],
            //         [`codofi`, `fondoid`],
            //         [`rfondos`, `ragencia`],
            //         []
            //     ], `show`, `ingresos_anulados`, 0, 'DFECPRO', 'NMONTO', 2, "Montos")
            // });
        </script>
        <?php
        break;
    case 'cartera_mora': {
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

                if ($userPermissions->isLevelOne(PermissionManager::REPORTE_MORA)) {
                    // PERMISO 1: solo de su agencia y de los usuarios de las agencias de su agencia
                    $permissionLevel = 1;
                    $condiUser = "estado=1 AND id_agencia = ?";
                    $condiAgencia = "id_agencia = ?";
                    $condiRegion = "estado='1' AND id IN (SELECT id_region FROM cre_regiones_agencias WHERE id_agencia=?)";

                    $paramsUser = [$idagencia];
                    $paramsAgencia = [$idagencia];
                    $paramsRegion = [$idagencia];
                } elseif ($userPermissions->isLevelTwo(PermissionManager::REPORTE_MORA)) {
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
                } elseif ($userPermissions->isLevelThree(PermissionManager::REPORTE_MORA)) {
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

                $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
                if (empty($fondos)) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron fondos disponibles.");
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
            <!-- REPORTE DE CARTERA EN MORA -->
            <div class="text" style="text-align:center">REPORTE DE CARTERA EN MORA</div>
            <input type="text" value="cartera_mora" id="condi" style="display: none;">
            <input type="text" value="reporte001" id="file" style="display: none;">
            <div class="card shadow-sm">
                <div class="card-body">
                    <!-- Filtros Section -->
                    <div class="container-fluid" id="formCarteraMora"
                        x-data="{
                            permisionLevel : <?= $permissionLevel ?? 0 ?>,
                            filterOption: <?= $permissionLevel ?? 0 ?> == 0 ? 'user' : 'agencia'
                        }">
                        <div class="row g-3">
                            <!-- Filtro por Agencia -->
                            <div class="col-12 col-sm-12 col-lg-4">
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

                            <!-- Filtro por Fuente de Fondos -->
                            <div class="col-12 col-sm-12 col-lg-4 col-xl-4" x-data="{ optionFondo: 'all' }">
                                <div class="card h-100 border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <i class="fas fa-wallet me-2"></i>Fuente de Fondos
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked @click="optionFondo='all'">
                                            <label for="allf" class="form-check-label">Todo</label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                @click="optionFondo='any'">
                                            <label for="anyf" class="form-check-label">Por Fuente de fondos</label>
                                        </div>
                                        <div class="form-floating" x-show="optionFondo=='any'">
                                            <select class="form-select" id="fondoid">
                                                <?php foreach (($fondos ?? []) as $fondo) {
                                                    echo "<option value='{$fondo['id']}'>{$fondo['descripcion']}</option>";
                                                } ?>
                                            </select>
                                            <label for="fondoid">Seleccionar Fondo</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtro por Fecha -->
                            <div class="col-12 col-sm-12 col-lg-4 col-xl-4">
                                <div class="card h-100 border-info">
                                    <div class="card-header bg-info text-white">
                                        <i class="fas fa-calendar-alt me-2"></i>Fecha de Proceso
                                    </div>
                                    <div class="card-body d-flex align-items-center">
                                        <div class="w-100">
                                            <label for="ffin" class="form-label">Seleccionar Fecha</label>
                                            <input type="date" class="form-control" id="ffin" value="<?= date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php echo $csrf->getTokenField(); ?>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <button type="button" class="btn btn-primary"
                                    onclick="verReporteJSON()">
                                    <i class="fa-solid fa-eye"></i> Ver
                                </button>
                                <button type="button" class="btn btn-danger"
                                    onclick="reporteManager.generarReporte('creditos/mora', '#formCarteraMora', { tipo: 'show' });">
                                    <i class="fa-solid fa-file-pdf"></i> Reporte en PDF
                                </button>
                                <button type="button" class="btn btn-success"
                                    onclick="reporteManager.generarReporte('creditos/mora', '#formCarteraMora', { tipo: 'xlsx' });">
                                    <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                                </button>

                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban me-1"></i>Cancelar
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                    <i class="fa-solid fa-circle-xmark me-1"></i>Salir
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Tabla de Resultados -->
                    <div id="divshow" class="container-fluid mt-4" style="display: none;">
                        <div class="table-responsive">
                            <table id="tbdatashow" class="table table-sm table-striped table-hover small">
                                <thead class="table-light">
                                    <tr></tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <!-- Gráfico -->
                    <div id="divshowchart" class="container-fluid mt-4" style="display: none;">
                        <canvas id="myChart"></canvas>
                    </div>
                </div> 
            </div>
            <script>
                $(document).ready(function() {
                    inicializarValidacionAutomaticaGeneric('#formCarteraMora');
                });

                function verReporteJSON() {
                    reporteManager.generarReporte('creditos/mora', '#formCarteraMora', {
                        tipo: 'json',
                        mostrarTabla: true,
                        mostrarGrafica: true,
                        configTabla: {
                            encabezados: ["Agencia", "Asesor", "Cuenta", "Nombre Cliente", "Fec. Inicio", "Fec. Vence", "Monto", "Saldo kp", "Kp. Mora", "Int. Corr.", "Mora", "Días"],
                            keys: ["nom_agencia", "analista", "CCODCTA", "short_name", "DFecDsbls", "fechaven", "NCapDes", "salcap", "capenmora", "intatrasado", "intmora", "atraso"]
                        },
                        configGrafica: {
                            titulo: 'Cartera en Mora por Asesor',
                            type: 'bar',
                            dataKey: 'datosGrafica',
                            labels: 'analista',
                            datasets: [{
                                    label: 'Saldo capital',
                                    key: 'salcap',
                                    color: 'rgba(255, 159, 64, 0.8)'
                                },
                                {
                                    label: 'Capital en Mora',
                                    key: 'capmora',
                                    color: 'rgba(255, 99, 132, 0.8)'
                                },
                                {
                                    label: 'Interés Corriente',
                                    key: 'intatrasado',
                                    color: 'rgba(255, 206, 86, 0.8)'
                                }
                            ]
                        }
                    });
                }
            </script>
        <?php
        }
        break;

    /* NEGROY NUEVOS REPORTES MAMONES */
    case 'mora2_filtro': {
            $id = $_POST["xtra"];
            $codusu = $_SESSION['id'];
            $agencia = $_SESSION['agencia'];
            $puesto = $_SESSION['puesto'];
            $flag = ($puesto == 'ADM' || $puesto == 'GER') ? true : false;
        ?>
            <!-- APR_05_LstdCntsActvsDspnbls -->
            <div class="text" style="text-align:center">REPORTE DE CARTERA EN MORA (Autoasignada)</div>
            <input type="text" value="cartera_mora" id="condi" style="display: none;">
            <input type="text" value="reporte001" id="file" style="display: none;">
            <div class="card">
                <div class="card-header">REPORTE DE CARTERA EN MORA (Autoasignada)</div>
                <div class="card-body">
                    <!-- segunda linea -->
                    <div class="row d-flex align-items-stretch mb-3">
                        <!-- card para seleccionar una cuenta -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro por fecha</b></div>
                                <div class="list-group list-group-flush card-body ps-3">
                                    <div class="row mb-1">
                                        <div class="col-12"> <span class="input-group-addon">Ingrese una fecha:</span> </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
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
                        <!-- card para filtrar cuentas -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro de fuente de fondo</b></div>
                                <div class="card-body">

                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_fuente" id="r_fuente"
                                                    value="allf" checked onclick="activar_select_cuentas(this, true,'fondoid')">
                                                <label class="form-check-label" for="r_cuentas">Todo</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_fuente" id="r_fuente"
                                                    value="anyf" onclick="activar_select_cuentas(this, false,'fondoid')"> <label
                                                    class="form-check-label" for="r_cuentas">Por fuente de Fondo</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <!--Seleccione un fuente  -->
                                        <div class="col d-flex justify-content-center">
                                            <select class="form-select" id="fondoid" disabled>
                                                <option value="0">Seleccione un fuente de fondo</option>
                                                <?php $fons = mysqli_query($conexion, "SELECT * FROM `ctb_fuente_fondos` where estado=1");
                                                while ($fon = mysqli_fetch_array($fons)) {
                                                    echo '<option value="' . $fon['id'] . '">' . $fon['descripcion'] . '</option>';
                                                } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <!--Seleccione un fuente  -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Card para las transacciones -->
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><b>Filtro de agencia</b></div>
                                <div class="card-body">

                                    <div class="d-none">
                                        <input class="form-check-input" type="radio" name="filter_agencia" id="r_agencia"
                                            value="F0" checked>
                                    </div><!--  TODOS  filter_agencia -->

                                    <div class="row mt-3">
                                        <!-- AGENCIA -->
                                        <div class="col d-flex justify-content-center">
                                            <select class="form-select" disabled id="agencia">
                                                <option selected disabled value="0">Seleccione una oficina</option>
                                                <option value="<?= $agencia ?>" selected> <?= $agencia ?></option>
                                            </select>
                                        </div>
                                    </div> <!-- AGENCIA -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Botones -->
                    <div class="row justify-items-md-center mt-3">
                        <div class="col align-items-center" id="modal_footer">
                            <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                            <button type="button" id="btnSave" class="btn btn-outline-success"
                                onclick="reportes([[`fechaFinal`],[`fondoid`,`agencia`],[`filter_fuente`,`filter_agencia`],[<?= $codusu; ?>]],`xlsx`,`cartera_en_mora`,1)">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel </button>

                            <button type="button" id="btnSave" class="btn btn-outline-primary"
                                onclick="reportes([[`fechaFinal`],[`fondoid`,`agencia`],[`filter_fuente`,`filter_agencia`],[<?= $codusu; ?>]],`pdf`,`cartera_en_mora`,0)">
                                <i class="fa-solid fa-file-pdf"></i> Reporte en PDF </button>

                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"> <i
                                    class="fa-solid fa-ban"></i> Cancelar </button>

                            <button type="button" class="btn btn-outline-warning" onclick="salir()"> <i
                                    class="fa-solid fa-circle-xmark"></i> Salir </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        }
        break;
    case "reporte_gara":
        $puestouser = $_SESSION["puesto"];
        // $puestouser="LOG";
        $puestosP = array("ADM", "GER", "AUD");
        $hidden = (in_array($puestouser, $puestosP)) ? false : true;
        $hidden = false;
        ?>

        <input type="text" id="file" value="clientes_001" style="display: none;">
        <input type="text" id="condi" value="create_cliente_juridico" style="display: none;">
        <div class="text" style="text-align:center">REPORTE DE GARANTIAS</div>
        <div class="card">
            <div class="card-header">EXCEL</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Agencias</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?php echo ($hidden) ? "hidden" : ""; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;"
                                            <?php echo ($hidden) ? 'disabled' : ''; ?>>
                                            <?php
                                            $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                $selected = ($ofi['id_agencia'] == $_SESSION["id_agencia"]) ? "selected" : "";
                                                echo '<option value="' . $ofi['id_agencia'] . '" ' . $selected . '>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Estados</div>
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
                </div>
                <div class="row container contenedort">
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
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">FECHA DE PROCESO</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="ffin">Fecha</label>
                                        <input type="date" class="form-control" id="ffin"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Asesor</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rasesor" id="allasesor"
                                                value="allasesor" checked onclick="changedisabled(`#codanal`,0)">
                                            <label for="allasesor" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rasesor" id="anyasesor"
                                                value="anyasesor" onclick="changedisabled(`#codanal`,1)">
                                            <label for="anyasesor" class="form-check-label"> Por Asesor</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Asesor</span>
                                        <select class="form-select" id="codanal" style="max-width: 100%;" disabled>
                                            <option value="0" disabled selected>Seleccione un asesor</option>
                                            <?php
                                            $anali = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA'");
                                            while ($ofi = mysqli_fetch_array($anali)) {
                                                $nombre = $ofi["nameusu"];
                                                $id_usu = $ofi["id_usu"];
                                                echo '<option value="' . $id_usu . '">' . $nombre . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro de garantias</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rgarantias" id="allgarantias"
                                                value="allgarantias" checked onclick="changedisabled(`#codgara`,0)">
                                            <label for="allgarantias" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rgarantias" id="anygarantias"
                                                value="anygarantias" onclick="changedisabled(`#codgara`,1)">
                                            <label for="anygarantias" class="form-check-label"> Por Garantia</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Garantias</span>
                                        <select class="form-select" id="codgara" style="max-width: 100%;" disabled>
                                            <option value="0" disabled selected>Seleccione una garantia</option>
                                            <?php
                                            $anali = mysqli_query($conexion, "SELECT id_TiposGarantia,TiposGarantia FROM $db_name_general.tb_tiposgarantia");
                                            while ($ofi = mysqli_fetch_array($anali)) {
                                                $nombre = $ofi["TiposGarantia"];
                                                $id_usu = $ofi["id_TiposGarantia"];
                                                echo '<option value="' . $id_usu . '">' . $nombre . '</option>';
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
                            <div class="card-header">Filtro por Región</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rregion" id="allregion"
                                                value="allregion" checked onclick="changedisabled(`#regionid`,0)">
                                            <label for="allregion" class="form-check-label">Sin filtro</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rregion" id="anyregion"
                                                value="anyregion" onclick="changedisabled(`#regionid`,1)">
                                            <label for="anyregion" class="form-check-label"> Por Región</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="regionid" disabled>
                                                <option value="0" selected>Seleccionar Región</option>
                                                <?php
                                                $regiones = mysqli_query($conexion, "SELECT id, nombre FROM cre_regiones WHERE estado=1 ORDER BY nombre");
                                                if ($regiones) {
                                                    while ($region = mysqli_fetch_array($regiones)) {
                                                        echo '<option value="' . $region['id'] . '">' . $region['nombre'] . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="regionid">Región</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <!-- <button type="button" class="btn btn-outline-danger" title="Cartera en pdf"
                        onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`],[`ragencia`,`rfondos`,`status`,`rasesor`],[<?php echo $idusuario; ?>]],`pdf`,`cartera_fondos`,0)">
                        <i class="fa-solid fa-file-pdf"></i> Pdf
                    </button> -->
                        <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                            onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`codgara`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`,`rgarantias`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`cartera_garantias`,1)">
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
    case "cartera_resumen":
        $mediumPermissionId = 16; // Permiso de ver cartera a nivel de agencia
        $highPermissionId = 17;   // Permiso de ver cartera nivel general [Todas las agencias]
        $showmensaje = false;
        $regiones = [];
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

            $condicion = ($accessHandler->isHigh()) ? "estado=1 AND puesto='ANA'" : (($accessHandler->isMedium()) ? "estado=1 AND id_agencia=? AND puesto='ANA'" : "estado=1 AND id_usu=?");
            $parametros = ($accessHandler->isHigh()) ? [] : (($accessHandler->isMedium()) ? [$idagencia] : [$idusuario]);

            $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], $condicion, $parametros);
            if (empty($users)) {
                $showmensaje = true;
                throw new Exception("No existen usuarios por revisar");
            }

            $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondos)) {
                throw new Exception("No se encontraron fondos disponibles.");
            }

            // Regiones (para filtro por región)
            $regiones = $database->getAllResults("SELECT id, nombre FROM cre_regiones WHERE estado=1 ORDER BY nombre", []);

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
        // echo "<pre>";
        // echo print_r($agencias);
        // echo "</pre>";
    ?>
        <input type="text" id="file" value="reporte001" style="display: none;">
        <input type="text" id="condi" value="cartera_fuenteFondos" style="display: none;">
        <div class="text" style="text-align:center">RESUMEN CARTERA CONSOLIDADA</div>

        <div class="card">
            <!-- <div class="card-header">FILTROS</div> -->
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div>
                        <?= $mensaje ?>
                    </div>
                </div>
            <?php
                return;
            }  ?>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" onclick="changedisabled(`#codofi`,0);">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                            <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Para acceder a un consolidado de todas las agencias, debe solicitar a su administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12" id="divagencia">
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

                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rregion" id="allregion"
                                                value="allregion" checked onclick="changedisabled(`#regionid`,0)">
                                            <label for="allregion" class="form-check-label">Sin filtro</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rregion" id="anyregion"
                                                value="anyregion" onclick="changedisabled(`#regionid`,1)">
                                            <label for="anyregion" class="form-check-label"> Por Región</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="regionid" disabled>
                                                <option value="0">Seleccionar Región</option>
                                                <?php
                                                if (!empty($regiones)) {
                                                    foreach ($regiones as $region) {
                                                        echo "<option value='{$region['id']}'>{$region['nombre']}</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="regionid">Región</label>
                                        </div>
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
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <?php
                                                foreach ($fondos as $fondo) {
                                                    echo "<option value='{$fondo['id']}'>{$fondo['descripcion']}</option>";
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
                    <div class="col-sm-12 col-md-6 col-lg-4 col-xl-3 mt-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class="col-12">
                                        <label for="ffin">FECHA DE PROCESO</label>
                                        <input type="date" class="form-control" id="ffin" value="<?= date("Y-m-d"); ?>">
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
                                        <div class="form-check" <?= ($accessHandler->isLow()) ? 'hidden' : ''; ?>>
                                            <input class="form-check-input" type="radio" name="rasesor" id="allasesor" <?= ($accessHandler->isLow()) ? '' : 'checked'; ?>
                                                value="allasesor" onclick="changedisabled(`#codanal`,0)">
                                            <label for="allasesor" class="form-check-label">Todos los disponibles</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rasesor" id="anyasesor" <?= ($accessHandler->isLow()) ? 'checked' : ''; ?>
                                                value="anyasesor" onclick="changedisabled(`#codanal`,1)">
                                            <label for="anyasesor" class="form-check-label"> Por Asesor</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Asesor</span>
                                        <i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="Para que le aparezcan todos los usuarios, debe solicitar al administrador que le otorgue los permisos necesarios para su usuario."></i>
                                        <select class="form-select" id="codanal" <?= ($accessHandler->isLow()) ? '' : 'disabled'; ?>>
                                            <?php
                                            foreach ($users as $user) {
                                                $selected = ($user['id_usu'] == $idusuario) ? "selected" : "";
                                                echo '<option value="' . $user['id_usu'] . '" ' . $selected . '>' . $user['nombre'] . ' ' . $user["apellido"] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Cartera en pdf"
                            onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`cartera_consolidada`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                            onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`cartera_consolidada`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
                        </button>
                        <!--Bonotones de Accion de Ejecutivo-->
                        <button type="button" class="btn btn-outline-danger" title="Cartera en pdf Ejecutivo"
                            onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`cartera_ejecutivo_productos`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf Ejecutivo
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Cartera en Excel Ejecutivo"
                            onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`codanal`,`regionid`],[`ragencia`,`rfondos`,`status`,`rasesor`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`cartera_ejecutivo_productos`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel Ejecutivo
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
}
?>