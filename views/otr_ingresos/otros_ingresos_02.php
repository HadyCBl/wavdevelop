<?php
include __DIR__ . '/../../includes/Config/config.php';
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

require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idoficina = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {
    case 'report_ingresos': {
            $id = $_POST["xtra"];
            $codusu = $_SESSION['id'];
            $agencia = $_SESSION['agencia'];

            $showmensaje = false;
            try {
                $database->openConnection();
                $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "nom_agencia", "cod_agenc"]);
                if (empty($agencias)) {
                    $showmensaje = true;
                    throw new Exception("No hay agencias");
                }

                $tipos = $database->selectColumns("otr_tipo_ingreso", ["id", "nombre_gasto", "tipo", "tipoLinea", 'grupo'], "estado=1");

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
            <div class="text-center mb-3">
                <h4 class="fw-bold">REPORTE DE OTROS INGRESOS</h4>
            </div>
            <input type="text" value="report_ingresos" id="condi" style="display: none;">
            <input type="text" value="otros_ingresos_02" id="file" style="display: none;">

            <div class="card shadow-sm" x-data="{ tipoMovimiento: '1' }">
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
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fa-solid fa-filter me-2"></i>FILTROS DE BÚSQUEDA</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card h-100 border-warning">
                                <div class="card-header bg-warning bg-opacity-10">
                                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-building me-2"></i>Agencia</h6>
                                </div>
                                <div class="card-body">
                                    <select class="form-select" id="agencia">
                                        <option selected value="0">Todas las agencias</option>
                                        <?php
                                        foreach (($agencias ?? []) as $agencia) {
                                        ?>
                                            <option value="<?= $agencia["id_agencia"]; ?>"><?= $agencia["nom_agencia"] ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 border-info">
                                <div class="card-header bg-info bg-opacity-10">
                                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-calendar-alt me-2"></i>Rango de Fechas</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label for="fechaInicio" class="form-label small mb-1">Desde:</label>
                                            <input type="date" class="form-control" id="fechaInicio" value="<?= date("Y-m-d"); ?>">
                                        </div>
                                        <div class="col-6">
                                            <label for="fechaFinal" class="form-label small mb-1">Hasta:</label>
                                            <input type="date" class="form-control" id="fechaFinal" value="<?= date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary bg-opacity-10">
                                    <h6 class="mb-0 fw-bold"><i class="fa-solid fa-exchange-alt me-2"></i>Tipo de Movimiento</h6>
                                </div>
                                <div class="card-body d-flex align-items-center justify-content-center">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="filter_movimiento" id="r_movimiento" value="1" checked x-model="tipoMovimiento">
                                        <label class="btn btn-outline-success" for="r_movimiento">
                                            <i class="fa-solid fa-arrow-up me-1"></i>Ingresos
                                        </label>

                                        <input type="radio" class="btn-check" name="filter_movimiento" id="r_movimiento2" value="2" x-model="tipoMovimiento">
                                        <label class="btn btn-outline-danger" for="r_movimiento2">
                                            <i class="fa-solid fa-arrow-down me-1"></i>Egresos
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 border-secondary">
                                <div class="card-header bg-secondary bg-opacity-10">
                                    <h6 class="mb-0 fw-bold">
                                        <i class="fa-solid fa-list me-2"></i>
                                        <span x-text="tipoMovimiento == '1' ? 'Tipo de Ingreso' : 'Tipo de Egreso'"></span>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <select class="form-select" id="tipo_movimiento">
                                        <option selected value="0">Todos los tipos</option>
                                        <?php
                                        foreach ($tipos as $tipo) {
                                        ?>
                                            <option value="<?= $tipo["id"]; ?>" data-tipo="<?= $tipo["tipo"]; ?>">
                                                <?= $tipo["nombre_gasto"] ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Filtrar opciones según el tipo de movimiento seleccionado
                        document.addEventListener('alpine:init', () => {
                            Alpine.data('reportForm', () => ({
                                tipoMovimiento: '1'
                            }));
                        });

                        // Filtrar select de tipos según el movimiento
                        document.querySelectorAll('input[name="filter_movimiento"]').forEach(radio => {
                            radio.addEventListener('change', function() {
                                const tipoSeleccionado = this.value;
                                const selectTipo = document.getElementById('tipo_movimiento');
                                const options = selectTipo.querySelectorAll('option');

                                options.forEach(option => {
                                    if (option.value === '0') {
                                        option.style.display = '';
                                        option.textContent = 'Todos los tipos';
                                        return;
                                    }

                                    const tipoData = option.getAttribute('data-tipo');
                                    if (tipoData === tipoSeleccionado) {
                                        option.style.display = '';
                                    } else {
                                        option.style.display = 'none';
                                    }
                                });

                                // Resetear selección
                                selectTipo.value = '0';
                            });
                        });

                        // Disparar el evento al cargar
                        document.addEventListener('DOMContentLoaded', function() {
                            document.getElementById('r_movimiento').dispatchEvent(new Event('change'));
                        });
                    </script>
                    <?php echo $csrf->getTokenField(); ?>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-center gap-2 flex-wrap" id="modal_footer">
                                <button type="button" class="btn btn-danger" onclick="reportes([[`fechaInicio`,`fechaFinal`,'<?= $csrf->getTokenName() ?>'],[`agencia`,`tipo_movimiento`],[`filter_movimiento`],[]],`pdf`,`reporte_ingresos`,0)">
                                    <i class="fa-solid fa-file-pdf me-1"></i> Detallado
                                </button>

                                <button type="button" class="btn btn-danger" onclick="reportes([[`fechaInicio`,`fechaFinal`,'<?= $csrf->getTokenName() ?>'],[`agencia`,`tipo_movimiento`],[`filter_movimiento`],[]],`pdf`,`reporte_consolidado`,0)">
                                    <i class="fa-solid fa-file-pdf me-1"></i> Consolidado
                                </button>

                                <button type="button" class="btn btn-success text-white" onclick="reportes([[`fechaInicio`,`fechaFinal`,'<?= $csrf->getTokenName() ?>'],[`agencia`,`tipo_movimiento`],[`filter_movimiento`],[]],`xlsx`,`reporte_consolidado`,1)">
                                    <i class="fa-solid fa-file-excel me-1"></i> Consolidado
                                </button>

                                <button type="button" class="btn btn-secondary" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban me-1"></i> Cancelar
                                </button>

                                <button type="button" class="btn btn-warning" onclick="salir()">
                                    <i class="fa-solid fa-circle-xmark me-1"></i> Salir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<?php
        }
        break;
}

?>