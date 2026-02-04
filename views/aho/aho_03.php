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
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';

use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
// date_default_timezone_set('America/Guatemala');

$condi = $_POST["condi"];

switch ($condi) {
    case 'EstadoCuentaAhorros':
        $id = $_POST["xtra"];
        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
        $strquery = "SELECT cta.ccodaho,cta.ccodcli,cli.short_name,cli.no_tributaria,cta.estado,cta.nlibreta 
                        FROM ahomcta cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                        WHERE cta.estado IN ('A','B') AND ccodaho=?;";
        try {
            $database->openConnection();
            $result = $database->getAllResults($strquery, [$id]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta, verifique el número de cuenta o si el cliente esta activo");
            }
            $bandera = "";
            $status = 1;
        } catch (Exception $e) {
            $bandera = " " . $e->getMessage();
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        if ($status) {
            $idcli = $result[0]["ccodcli"];
            $nit = $result[0]["no_tributaria"];
            $nlibreta = $result[0]["nlibreta"];
            $nombre = $result[0]["short_name"];
        }
        include_once "../../src/cris_modales/mdls_aho_new.php";
        ?>
        <!-- APR_03_StdCnt -->
        <!-- <div class="text" style="text-align:center">ESTADO DE CUENTA INDIVIDUAL</div> -->
        <input type="text" id="file" value="aho_03" style="display: none;">
        <input type="text" id="condi" value="EstadoCuentaAhorros" style="display: none;">
        <div class="card">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-file-invoice"></i> Estado de cuenta individual
                </h4>
            </div>
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <span class="input-group-addon col-8">Código de cuenta</span>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control " id="ccodaho" required placeholder="000-000-00-000000"
                                    value="<?php if ($bandera == "")
                                        echo $id; ?>" onkeydown="keypress(event)">
                                <span class="input-group-text" id="basic-addon1">
                                    <?php if ($bandera == "") {
                                        echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="26" height="26" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00b341" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M9 12l2 2l4 -4" />
                                            </svg>';
                                    } else {
                                        echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-x" width="26" height="26"  viewBox="0 0 24 24" stroke-width="1.5" stroke="#ff2825" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M10 10l4 4m0 -4l-4 4" />
                                          </svg>';
                                    }
                                    ?></span>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <br>
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaho')">
                                <i class="fa fa-check-to-slot"></i>
                            </button>
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1" title="Buscar cuenta"
                                data-bs-toggle="modal" data-bs-target="#findahomcta2">
                                <i class="fa fa-magnifying-glass"></i>
                            </button>
                        </div>
                    </div>
                    <!-- alerta para cuando no encuentra una cuenta -->
                    <?php if ($bandera != "" && $id != "0") {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $bandera . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                    ?>
                    <div class="row mb-3">
                        <div class="col-md-2">
                            <div>
                                <span class="input-group-addon col-8">Libreta</span>
                                <input type="text" class="form-control " id="libreta" readonly value="<?php if ($bandera == "")
                                    echo $nlibreta; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div>
                                <span class="input-group-addon col-8">NIT</span>
                                <input type="text" class="form-control " id="nit" readonly value="<?php if ($bandera == "")
                                    echo $nit; ?>">
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div>
                                <span class="input-group-addon col-8">Nombre</span>
                                <input type="text" class="form-control " id="name" readonly value="<?php if ($bandera == "")
                                    echo $nombre; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row d-flex align-items-stretch mb-3">
                    <div class="col-6">
                        <div class="container contenedort" style="height: 100% !important;">
                            <div class="row mb-3">
                                <div class="col">
                                    <div>
                                        <span class="input-group-addon">Filtro de fecha</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col d-flex justify-content-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="filter_fecha" id="r_nofecha"
                                            value="1" checked
                                            onclick="activar_input_dates(this, true,'fechaInicio','fechaFinal')">
                                        <label class="form-check-label" for="r_nofecha">Todo</label>
                                    </div>
                                </div>
                                <div class="col d-flex justify-content-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="filter_fecha" id="r_fecha" value="2"
                                            onclick="activar_input_dates(this,false,'fechaInicio','fechaFinal')">
                                        <label class="form-check-label" for="r_fecha">Rango</label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- seccion de rango de fecha -->
                    <div class="col-6">
                        <div class="container contenedort" style="height: 100% !important;">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <span class="input-group-addon">Desde:</span>
                                </div>
                                <div class="col-6">
                                    <span class="input-group-addon">Hasta:</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="input-group">
                                        <input type="date" class="form-control" aria-label="Username"
                                            aria-describedby="basic-addon1" value="<?php echo date("Y-m-d"); ?>"
                                            id="fechaInicio" disabled>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="input-group">
                                        <input type="date" class="form-control" aria-label="Username"
                                            aria-describedby="basic-addon1" value="<?php echo date("Y-m-d"); ?>" id="fechaFinal"
                                            disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <!-- select que no se usa -->
                <select name="select" id="nada" style="display: none;">
                    <option value="value1">Value 1</option>
                </select>

                <!-- botones de imprimir, cancelar y salir -->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <?php if ($status) { ?>
                            <button type="button" id="btnSave" class="btn btn-outline-success"
                                onclick="reportes([[`ccodaho`,`libreta`,`fechaInicio`,`fechaFinal`],[],[`filter_fecha`],['<?php echo $idusuario; ?>','<?php echo $ofi; ?>']], 'xlsx', 'estado_cuenta_aho', 1)">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                            </button>
                            <button type="button" id="btnSave" class="btn btn-outline-success"
                                onclick="reportes([[`ccodaho`,`libreta`,`fechaInicio`,`fechaFinal`],[],[`filter_fecha`],['<?php echo $idusuario; ?>','<?php echo $ofi; ?>']], 'pdf', 'estado_cuenta_aho', 0)">
                                <i class="fa-solid fa-print"></i> Reporte en PDF
                            </button>
                        <?php } ?>
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
    //Estado de cuenta por fecha
    case 'Saldos_de_cuentas':
        $status = false;
        try {
            $database->openConnection();
            $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], 'estado=1');
            if (empty($tiposcuentas)) {
                throw new SoftException("No se encontró la cuenta de ahorros, verifique el número de cuenta o si el cliente esta activo");
            }

            $status = true;
        } catch (SoftException $e) {
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        ?>
        <!-- <div class="text" style="text-align:center">SALDOS DE CUENTAS</div> -->
        <input type="text" value="Saldos_de_cuentas" id="condi" style="display: none;">
        <input type="text" value="aho_03" id="file" style="display: none;">
        <div class="card">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-chart-line"></i> SALDOS DE CUENTAS
                </h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="container contenedort">
                    <div class="row m-2">
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Estado de cuenta</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_estado" id="r_todos"
                                                    checked value="0">
                                                <label class="form-check-label" for="r_todos">Todos</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_estado" id="r_activos"
                                                    value="A">
                                                <label class="form-check-label" for="r_activos">Activos</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_estado"
                                                    id="r_inactivos" value="B">
                                                <label class="form-check-label" for="r_inactivos">Inactivos</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Tipo de cuenta</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <select class="form-select" aria-label="Default select example" id="tipcuenta"
                                                multiple data-control="select2" data-placeholder="Todos los tipos de cuentas">
                                                <?php
                                                // echo '<option selected value="0">Todos los tipos de cuentas</option>';
                                                foreach ($tiposcuentas as $tip) {
                                                    echo '<option value="' . $tip['ccodtip'] . '">' . $tip['ccodtip'] . ' - ' . $tip['nombre'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Filtro de fecha</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-1">
                                        <div class="col">
                                            <span class="input-group-addon">Fecha final:</span>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
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
                    </div>
                </div>

                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" class="btn btn-outline-primary" title="Reporte de saldos"
                            onclick="reportes([[`fechaFinal`],[],[`filter_estado`],[$('#tipcuenta').val()]],`show`,`saldos_cuentas`,0,0,'nombre','saldo',2,'Saldos')">
                            <i class="fa-solid fa-eye"></i> Ver
                        </button>
                        <button type="button" id="btnSave" class="btn btn-outline-success"
                            onclick="reportes([[`fechaFinal`],[],[`filter_estado`],[$('#tipcuenta').val()]], 'xlsx', 'saldos_cuentas', 1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" id="btnSave" class="btn btn-outline-danger"
                            onclick="reportes([[`fechaFinal`],[],[`filter_estado`],[$('#tipcuenta').val()]], 'pdf', 'saldos_cuentas', 0)">
                            <i class="fa-regular fa-file-pdf"></i> PDF
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
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
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
                <script>
                    $(document).ready(function () {
                        $('#tipcuenta').select2({
                            theme: 'bootstrap-5',
                            language: 'es',
                            closeOnSelect: false
                        });
                    });
                </script>
            </div>
        </div>
        <?php
        break;
} //FINAL DEL SWITCH
?>