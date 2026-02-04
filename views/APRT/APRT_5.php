<?php

use App\Generic\Models\TipoDocumentoTransaccion;

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
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

$csrf = new CSRFProtection();

$condi = $_POST["condi"];

switch ($condi) {
    case 'Saldos_de_cuentas': {
            $id = $_POST["xtra"];
            // $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
            try {
                $database->openConnection();
                $tiposcuentas = $database->selectColumns('aprtip', ['ccodtip', 'nombre', 'cdescripcion'], '1=?', [1]);
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            } finally {
                $database->closeConnection();
            }
?>
            <!-- APR_03_VrfcrSlds -->
            <div class="text" style="text-align:center">SALDO DE CUENTA</div>
            <input type="text" value="Saldos_de_cuentas" id="condi" style="display: none;">
            <input type="text" value="APRT_5" id="file" style="display: none;">

            <div class="card">
                <!-- <div class="card-header">Reporte de saldos por cuenta</div> -->
                <div class="card-body">
                    <div class="container contenedort">
                        <div class="row m-2">
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <div class="card" style="height: 100% !important;">
                                    <div class="card-header"><b>Estado de cuenta</b></div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col d-flex justify-content-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="filter_estado" id="r_todos" checked value="0">
                                                    <label class="form-check-label" for="r_todos">Todas</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col d-flex justify-content-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="filter_estado" id="r_activos" value="A">
                                                    <label class="form-check-label" for="r_activos">Activas</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col d-flex justify-content-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="filter_estado" id="r_inactivos" value="B">
                                                    <label class="form-check-label" for="r_inactivos">Inactivas</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <div class="card" style="height: 100% !important;">
                                    <div class="card-header"><b>Tipo de cuenta</b></div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col">
                                                <select class="form-select" aria-label="Default select example" id="tipcuenta">
                                                    <?php
                                                    echo '<option selected value="0">Todos los tipos de cuentas</option>';
                                                    foreach ($tiposcuentas as $tip) {
                                                        echo '<option value="' . $tip['ccodtip'] . '">' . $tip['nombre'] . ' - ' . $tip['cdescripcion'] . '</option>';
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
                                    <div class="card-header"><b>Filtro de fecha</b></div>
                                    <div class="card-body">
                                        <div class="row mb-1">
                                            <div class="col">
                                                <span class="input-group-addon">Fecha final:</span>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col">
                                                <div class="input-group">
                                                    <input type="date" class="form-control" aria-label="Username" aria-describedby="basic-addon1" value="<?php echo date("Y-m-d"); ?>" id="fechaFinal">
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
                            <button type="button" class="btn btn-outline-primary" title="Reporte de saldos" onclick="reportes([[`fechaFinal`],[`tipcuenta`],[`filter_estado`],[]],`show`,`saldo_de_cuentas`,0,0,'nombre','saldo',2,'Saldos')">
                                <i class="fa-solid fa-eye"></i> Ver
                            </button>
                            <button type="button" id="btnSave" class="btn btn-outline-success" onclick="reportes([[`fechaFinal`],[`tipcuenta`],[`filter_estado`],[]], 'xlsx', 'saldo_de_cuentas', 1)">
                                <i class="fa-solid fa-file-excel"></i> Excel
                            </button>
                            <button type="button" id="btnSave" class="btn btn-outline-danger" onclick="reportes([[`fechaFinal`],[`tipcuenta`],[`filter_estado`],[]], 'pdf', 'saldo_de_cuentas', 0)">
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
                </div>
            </div>
        <?php
        }
        break;
    case 'EstadoCuentaAportaciones': {
            $accountCode = $_POST["xtra"];
            // $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
            $strquery = "SELECT cta.ccodaport,cta.ccodcli,cli.short_name,cli.no_tributaria,cta.estado,cta.nlibreta 
                            FROM aprcta cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                            WHERE cta.estado IN ('A','B') AND ccodaport=?;";
            $showmensaje = false;
            try {

                if (empty($accountCode)) {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de aportacion");
                }

                $database->openConnection();
                $result = $database->getAllResults($strquery, [$accountCode]);
                if (empty($result)) {
                    $showmensaje = true;
                    throw new Exception("Cuenta de aportacion no existe");
                }
                // $bandera = "";
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
            if ($status) {
                $idcli = $result[0]["ccodcli"];
                $nit = $result[0]["no_tributaria"];
                $nlibreta = $result[0]["nlibreta"];
                $nombre = $result[0]["short_name"];
            }
        ?>
            <!-- APR_03_StdCnt -->
            <div class="container-fluid py-3">
                <div class="row justify-content-center mb-3">
                    <div class="col-12 text-center">
                        <h4 class="fw-bold mb-0">ESTADO DE CUENTA INDIVIDUAL</h4>
                        <input type="hidden" id="file" value="APRT_5">
                        <input type="hidden" id="condi" value="EstadoCuentaAportaciones">
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="mb-0">Estado de cuenta individual</h6>
                                    <div>
                                        <?php if ($status) { ?>
                                            <span class="badge bg-success">Cuenta válida</span>
                                        <?php } else { ?>
                                            <span class="badge bg-danger">Cuenta no encontrada</span>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <?php if (!$status) { ?>
                                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                        <strong>¡Atención!</strong> <?= $mensaje; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php } ?>

                                <div class="row g-3 mb-3 align-items-end">
                                    <div class="col-md-7">
                                        <label for="ccodaport" class="form-label small">Cuenta de aportaciones</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-lg" id="ccodaport" required placeholder="000-000-00-000000" value="<?= ($status) ? $accountCode : ''; ?>" onkeydown="keypress(event)">
                                            <span class="input-group-text bg-white">
                                                <?php
                                                if ($status) {
                                                    echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="#00b341" stroke-width="1.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2l4 -4"/></svg>';
                                                } else {
                                                    echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="#ff2825" stroke-width="1.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M10 10l4 4m0 -4l-4 4"/></svg>';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="form-text">Ingrese la cuenta o búsquela con el botón lateral.</div>
                                    </div>

                                    <div class="col-md-5 d-flex gap-2">
                                        <button class="btn btn-outline-primary w-100" type="button" title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaport')">
                                            <i class="fa fa-check-to-slot me-1"></i> Aplicar
                                        </button>
                                        <button class="btn btn-outline-secondary w-100" type="button" title="Buscar cuenta" data-bs-toggle="modal" data-bs-target="#findAprAccounts">
                                            <i class="fa fa-magnifying-glass me-1"></i> Buscar
                                        </button>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-2">
                                        <label class="form-label small">Libreta</label>
                                        <p id="libreta" class="form-control-plaintext mb-0"><?= ($status) ? htmlspecialchars($nlibreta) : '' ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">NIT</label>
                                        <p id="nit" class="form-control-plaintext mb-0"><?= ($status) ? htmlspecialchars($nit) : '' ?></p>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label small">Nombre</label>
                                        <p id="name" class="form-control-plaintext mb-0"><?= ($status) ? htmlspecialchars($nombre) : '' ?></p>
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-12">
                                        <div class="card border-primary shadow-sm rounded">
                                            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                                                <strong class="mb-0">Filtro de fecha</strong>
                                                <small class="text-white-50">Seleccione Todo o un Rango</small>
                                            </div>
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col-md-6 mb-3 mb-md-0">
                                                        <div class="d-flex gap-3 align-items-center">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="filter_fecha" id="r_nofecha" value="1" checked onclick="activar_input_dates(this, true,'fechaInicio','fechaFinal')">
                                                                <label class="form-check-label small" for="r_nofecha">Todo</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="filter_fecha" id="r_fecha" value="2" onclick="activar_input_dates(this,false,'fechaInicio','fechaFinal')">
                                                                <label class="form-check-label small" for="r_fecha">Rango</label>
                                                            </div>
                                                        </div>
                                                        <div class="form-text mt-2">Elija "Rango" para activar las fechas.</div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="row g-2">
                                                            <div class="col-6">
                                                                <label for="fechaInicio" class="form-label small mb-1">Desde</label>
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-white border-end-0">&#x1F4C5;</span>
                                                                    <input type="date" class="form-control border-start-0" id="fechaInicio" value="<?php echo date("Y-m-d"); ?>" disabled>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <label for="fechaFinal" class="form-label small mb-1">Hasta</label>
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-white border-end-0">&#x1F4C5;</span>
                                                                    <input type="date" class="form-control border-start-0" id="fechaFinal" value="<?php echo date("Y-m-d"); ?>" disabled>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-text mt-2">Las fechas están desactivadas hasta que seleccione "Rango".</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php echo $csrf->getTokenField(); ?>

                                <div class="row mt-3">
                                    <div class="col d-flex flex-wrap gap-2 justify-content-end">
                                        <?php if ($status) { ?>
                                            <button type="button" id="btnSave" class="btn btn-success" onclick="reportes([['<?= $csrf->getTokenName() ?>','fechaInicio','fechaFinal'],[],['filter_fecha'],['<?= $accountCode; ?>']], 'xlsx', 'estado_cuenta_apr', 1)">
                                                <i class="fa-solid fa-file-excel me-1"></i> Excel
                                            </button>

                                            <button type="button" id="btnPrint" class="btn btn-danger" onclick="reportes([['<?= $csrf->getTokenName() ?>','fechaInicio','fechaFinal'],[],['filter_fecha'],['<?= $accountCode; ?>']], 'pdf', 'estado_cuenta_apr', 0)">
                                                <i class="fa-solid fa-print me-1"></i> PDF
                                            </button>
                                        <?php } ?>

                                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                            <i class="fa-solid fa-ban me-1"></i> Cancelar
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Salir
                                        </button>
                                    </div>
                                </div>

                            </div> <!-- card-body -->
                        </div> <!-- card -->
                    </div> <!-- col -->
                </div> <!-- row -->
            </div> <!-- container-fluid -->
            <div class="modal fade" id="findAprAccounts" tabindex="-1" aria-labelledby="findaportctaLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="findaportctaLabel">Buscar cuenta de aportaciones</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body px-2">
                            <div class="table-responsive w-100 mx-0">
                                <table style="font-size: 12px;" id="cuentasAportaciones" class="table table-hover table-striped table-sm align-middle w-100 dt-responsive">
                                    <colgroup>
                                        <col style="width:12%;">
                                        <col style="width:48%;">
                                        <col style="width:15%;">
                                        <col style="width:20%;">
                                        <col style="width:5%;">
                                    </colgroup>
                                    <thead class="table-light small bg-primary text-white">
                                        <tr class="bg-primary text-white">
                                            <th style="width: 12%;">Cuenta</th>
                                            <th class="text-break">Nombre</th>
                                            <th style="width: 15%;">Identificación</th>
                                            <th class="text-break">Producto</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                $(document).ready(function() {
                    const columns = [{
                            data: 'ccodaport',
                        },
                        {
                            data: 'short_name',
                        },
                        {
                            data: 'no_identifica',
                        },
                        {
                            data: 'nombreProducto'
                        },
                        {
                            data: null,
                            title: 'Acción',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                return `<button data-bs-dismiss="modal" class="btn btn-success btn-sm" onclick="printdiv2('#cuadro','${row.ccodaport}');">Seleccionar</button>`;
                            }
                        }
                    ];
                    const table = initServerSideDataTable(
                        '#cuentasAportaciones',
                        'cuentas_apr',
                        columns, {
                            onError: function(xhr, error, thrown) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al cargar cuentas de aportaciones',
                                    text: 'Por favor, intente nuevamente'
                                });
                            }
                        }
                    );
                });
            </script>
        <?php
        }
        break;
    case 'ListadoDelDia': {
            $id = $_POST["xtra"];
            // $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
            try {
                $database->openConnection();
                $tiposcuentas = $database->selectColumns('aprtip', ['id_tipo', 'ccodtip', 'nombre', 'cdescripcion'], '1=?', [1]);
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            } finally {
                $database->closeConnection();
            }
        ?>
            <div class="text" style="text-align:center">LISTADO DEL DÍA</div>
            <input type="text" value="ListadoDelDia" id="condi" style="display: none;">
            <input type="text" value="APRT_5" id="file" style="display: none;">
            <style>
                #checklist,
                #checklist label {
                    position: relative;
                    display: grid
                }

                #checklist {
                    --background: #fff;
                    --text: #414856;
                    --check: #4f29f0;
                    --disabled: #c3c8de;
                    --width: 100%;
                    --height: 80px;
                    --border-radius: 10px;
                    background: var(--background);
                    width: var(--width);
                    height: var(--height);
                    border-radius: var(--border-radius);
                    box-shadow: 0 10px 30px rgb(65 72 86 / .05);
                    grid-template-columns: 40px auto;
                    align-items: center;
                    justify-content: center
                }

                #checklist label {
                    color: var(--text);
                    cursor: pointer;
                    align-items: center;
                    width: fit-content;
                    transition: color .3s;
                    margin-right: 20px
                }

                #checklist label::after,
                #checklist label::before {
                    content: "";
                    position: absolute
                }

                #checklist label::before {
                    height: 2px;
                    width: 8px;
                    left: -27px;
                    background: var(--check);
                    border-radius: 2px;
                    transition: background .3s
                }

                #checklist label:after {
                    height: 4px;
                    width: 4px;
                    top: 8px;
                    left: -25px;
                    border-radius: 50%
                }

                #checklist input[type=checkbox] {
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    position: relative;
                    height: 15px;
                    width: 15px;
                    outline: 0;
                    border: 0;
                    margin: 0 20px 0 0;
                    cursor: pointer;
                    background: var(--background);
                    display: grid;
                    align-items: center
                }

                #checklist input[type=checkbox]::after,
                #checklist input[type=checkbox]::before {
                    content: "";
                    position: absolute;
                    height: 2px;
                    top: auto;
                    background: var(--check);
                    border-radius: 2px
                }

                #checklist input[type=checkbox]::before {
                    width: 0;
                    right: 60%;
                    transform-origin: right bottom
                }

                #checklist input[type=checkbox]::after {
                    width: 0;
                    left: 40%;
                    transform-origin: left bottom
                }

                #checklist input[type=checkbox]:checked::before {
                    animation: .4s forwards check-01
                }

                #checklist input[type=checkbox]:checked::after {
                    animation: .4s forwards check-02
                }

                #checklist input[type=checkbox]:not(:checked)+label {
                    color: var(--disabled);
                    text-decoration: line-through;
                    animation: .3s .1s forwards move
                }

                #checklist input[type=checkbox]:not(:checked)+label::before {
                    background: var(--disabled);
                    animation: .4s forwards slice
                }

                #checklist input[type=checkbox]:not(:checked)+label::after {
                    animation: .5s .1s forwards firework
                }

                #checklist input[type=checkbox]:checked+label {
                    color: var(--text);
                    text-decoration: none
                }

                @keyframes move {
                    50% {
                        padding-left: 8px;
                        padding-right: 0
                    }

                    100% {
                        padding-right: 4px
                    }
                }

                @keyframes slice {
                    60% {
                        width: 100%;
                        left: 4px
                    }

                    100% {
                        width: 100%;
                        left: -2px;
                        padding-left: 0
                    }
                }

                @keyframes check-01 {
                    0% {
                        width: 4px;
                        top: auto;
                        transform: rotate(0)
                    }

                    50% {
                        width: 0;
                        top: auto;
                        transform: rotate(0)
                    }

                    51% {
                        width: 0;
                        top: 8px;
                        transform: rotate(45deg)
                    }

                    100% {
                        width: 5px;
                        top: 8px;
                        transform: rotate(45deg)
                    }
                }

                @keyframes check-02 {
                    0% {
                        width: 4px;
                        top: auto;
                        transform: rotate(0)
                    }

                    50% {
                        width: 0;
                        top: auto;
                        transform: rotate(0)
                    }

                    51% {
                        width: 0;
                        top: 8px;
                        transform: rotate(-45deg)
                    }

                    100% {
                        width: 10px;
                        top: 8px;
                        transform: rotate(-45deg)
                    }
                }

                @keyframes firework {
                    0% {
                        opacity: 1;
                        box-shadow: 0 0 0 -2px #4f29f0, 0 0 0 -2px #4f29f0, 0 0 0 -2px #4f29f0, 0 0 0 -2px #4f29f0, 0 0 0 -2px #4f29f0, 0 0 0 -2px #4f29f0
                    }

                    30% {
                        opacity: 1
                    }

                    100% {
                        opacity: 0;
                        box-shadow: 0 -15px 0 0 #4f29f0, 14px -8px 0 0 #4f29f0, 14px 8px 0 0 #4f29f0, 0 15px 0 0 #4f29f0, -14px 8px 0 0 #4f29f0, -14px -8px 0 0 #4f29f0
                    }
                }
            </style>
            <div class="card" style="height: 100% !important;">
                <div class="card-body">
                    <div class="container contenedort">
                        <div class="row">
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="card" style="height: 100% !important;">
                                    <div class="card-header"><b>Transacciones</b></div>
                                    <div class="card-body">
                                        <div class="row" id="containercheck">
                                            <div class="col-lg-4 col-md-12 col-sm-12">
                                                <h6>Incluir</h6>
                                                <div id="checklist">
                                                    <input checked="" value="all" name="checks" type="checkbox" id="all">
                                                    <label for="all">Todo</label>
                                                    <input checked="" value="lib" name="checks" type="checkbox" id="lib">
                                                    <label for="lib">Cambios de libreta</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 col-md-6 col-sm-12">
                                                <h6>Ingresos</h6>
                                                <div id="checklist">
                                                    <input checked="" value="int" name="checks" type="checkbox" id="int">
                                                    <label for="int">Intereses</label>
                                                    <input checked="" value="dep" name="checks" type="checkbox" id="dep">
                                                    <label for="dep">Depositos</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4 col-md-6 col-sm-12">
                                                <h6>Egresos</h6>
                                                <div id="checklist">
                                                    <input checked="" value="isr" name="checks" type="checkbox" id="isr">
                                                    <label for="isr">Impuestos</label>
                                                    <input checked="" value="ret" name="checks" type="checkbox" id="ret">
                                                    <label for="ret">Retiros</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="alertContainer" class="mt-3"></div>
                        <br>
                        <div class="row">
                            <div class="col-sm-12 col-md-6 col-lg-6">
                                <div class="card" style="height: 100% !important;">
                                    <div class="card-header"><b>Filtro de tipo de cuenta</b></div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col d-flex justify-content-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="filter_cuenta" id="r_cuentas" value="1" checked onclick="activar_select_cuentas(this, true,'tipcuenta')">
                                                    <label class="form-check-label" for="r_cuentas">Todos</label>
                                                </div>
                                            </div>
                                            <div class="col d-flex justify-content-center">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="filter_cuenta" id="r_cuenta" value="2" onclick="activar_select_cuentas(this, false,'tipcuenta')">
                                                    <label class="form-check-label" for="r_cuenta">Tipo de cuenta</label>
                                                </div>
                                            </div>
                                        </div>
                                        <br>
                                        <div class="row">
                                            <div class="col-12 d-flex justify-content-center">
                                                <select class="form-select" aria-label="Default select example" id="tipcuenta" disabled>
                                                    <?php
                                                    echo '<option selected disabled value="0">Seleccione un tipo de cuenta</option>';
                                                    foreach ($tiposcuentas as $tip) {
                                                        echo '<option value="' . $tip['ccodtip'] . '">' . $tip['nombre'] . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6 col-lg-6">
                                <div class="card" style="height: 100%;">
                                    <div class="card-body">
                                        <div class="row" id="filfechas">
                                            <div class="col-sm-12">
                                                <label for="finicio">Desde</label>
                                                <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                            </div>
                                            <div class="col-sm-12">
                                                <label for="ffin">Hasta</label>
                                                <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <button type="button" class="btn btn-outline-primary" title="Reporte de movimientos" onclick="process('show',0);">
                                <i class="fa-solid fa-eye"></i> Ver
                            </button>
                            <button type="button" id="btnSave" class="btn btn-outline-success" title="Reporte de movimientos" onclick="process('xlsx',1);">
                                <i class="fa-solid fa-file-excel"></i> Excel
                            </button>
                            <button type="button" id="btnSave" class="btn btn-outline-danger" title="Reporte de movimientos" onclick="process('pdf',0);">
                                <i class="fa-solid fa-file-pdf"></i> PDF
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                        </div>
                    </div>
                    <br>
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
                </div>
            </div>
            <script>
                $(document).ready(function() {
                    const todoCheckbox = document.getElementById('all');
                    const otherCheckboxes = document.querySelectorAll('input[name="checks"]:not(#all)');
                    const alertContainer = document.getElementById('alertContainer');

                    function showAlert(message) {
                        alertContainer.innerHTML = `
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    }

                    function updateTodoCheckbox() {
                        const allChecked = Array.from(otherCheckboxes).every(cb => cb.checked);
                        todoCheckbox.checked = allChecked;
                    }

                    todoCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            otherCheckboxes.forEach(checkbox => {
                                checkbox.checked = true;
                            });
                        } else {
                            otherCheckboxes.forEach((checkbox, index) => {
                                checkbox.checked = (index === 2);
                            });
                        }
                        alertContainer.innerHTML = '';
                    });

                    otherCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            const checkedCount = Array.from(otherCheckboxes).filter(cb => cb.checked).length;

                            if (checkedCount === 0) {
                                this.checked = true;
                                showAlert("Al menos uno debe estar activo.");
                                return;
                            }
                            updateTodoCheckbox();
                            alertContainer.innerHTML = '';
                        });
                    });
                });


                function process(tipo, download) {
                    let checkedValues = [];
                    let checkboxes = document.querySelectorAll('input[name="checks"]:checked');
                    checkboxes.forEach((checkbox) => {
                        checkedValues.push(checkbox.value);
                    });
                    reportes([
                        [`finicio`, `ffin`],
                        [`tipcuenta`],
                        [`filter_cuenta`],
                        [checkedValues]
                    ], tipo, `listado_dia`, download, 0, 'dfecope', 'monto', 2, 'Montos', 0);
                }
            </script>
        <?php
        }
        break;

    case 'Cuadre_de_diario': {
            $id = $_POST["xtra"];
            $showmensaje = false;
            try {
                $database->openConnection();
                $tiposcuentas = $database->selectColumns('aprtip', ['ccodtip', 'nombre', 'cdescripcion'], 'estado=1');
                if (empty($tiposcuentas)) {
                    $showmensaje = true;
                    throw new Exception("No hay tipos de cuentas");
                }

                $tiposDocumentos = TipoDocumentoTransaccion::getTiposDisponibles(2);
                $status = true;
                // Log::info("Tipos de documentos obtenidos: " . json_encode($tiposDocumentos));
            } catch (Exception $e) {
                if (!$showmensaje) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                }
                $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
                $status = false;
            } finally {
                $database->closeConnection();
            }
        ?>
            <div class="text" style="text-align:center">CUADRE DIARIO DE DEPOSITOS Y RETIROS</div>
            <input type="text" value="Cuadre_de_diario" id="condi" style="display: none;">
            <input type="text" value="APRT_5" id="file" style="display: none;">

            <div class="card">
                <div class="card-body">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>!!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="container contenedort">
                        <div class="row">
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <div class="card" style="height: 100% !important;">
                                    <div class="card-header"><b>Tipos de cuentas</b></div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <small class="text-muted">Puede seleccionar varios tipos de cuenta. Si desea incluir todos, no seleccione ninguno.</small>
                                                <select class="form-select" aria-label="Default select example" id="tipcuenta" multiple data-control="select2" data-placeholder="Todos los tipos de cuentas">
                                                    <?php
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
                                    <div class="card-header"><b>Fechas</b></div>
                                    <div class="card-body">
                                        <div class="row" id="filfechas">
                                            <div class="col-sm-12">
                                                <label for="finicio">Desde</label>
                                                <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                            </div>
                                            <div class="col-sm-12">
                                                <label for="ffin">Hasta</label>
                                                <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <div class="card" style="height: 100% !important;">
                                    <div class="card-header"><b>Filtro adicional</b></div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" value="lib" id="lib" checked name="checks">
                                                <label class="form-check-label" for="lib">Incluir cambios de libreta</label>
                                            </div>
                                        </div>
                                        <div style="max-width: 100%;">
                                            <label for="tipoDocumento" class="form-label">Tipo de documento</label>
                                            <br>
                                            <small class="text-muted">Puede seleccionar varios tipos de documento. Si desea incluir todos, no seleccione ninguno.</small>
                                            <select class="form-select" aria-label="Selector de tipos de documentos" id="tipoDocumento" multiple data-control="select2" data-placeholder="Todos los tipos de documentos">
                                                <?php
                                                // echo '<option selected value="0">Todos los tipos de documentos</option>';
                                                foreach ($tiposDocumentos as $key => $tip) {
                                                    echo "<option value=\"{$key}\">{$tip}</option>";
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
                        <div class="col align-items-center" id="modal_footer">
                            <button type="button" class="btn btn-outline-primary" title="Reporte de movimientos" onclick="process('show',0);">
                                <i class="fa-solid fa-eye"></i> Ver
                            </button>
                            <button type="button" id="btnSave" class="btn btn-outline-success" title="Reporte de movimientos" onclick="process('xlsx',1);">
                                <i class="fa-solid fa-file-excel"></i> Excel
                            </button>
                            <button type="button" id="btnSave" class="btn btn-outline-danger" title="Reporte de movimientos" onclick="process('pdf',0);">
                                <i class="fa-solid fa-file-pdf"></i> PDF
                            </button>
                        </div>
                    </div>
                    <br>
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
                </div>
            </div>
            <script>
                function process(tipo, download) {
                    let checkedValues = [];
                    let checkboxes = document.querySelectorAll('input[name="checks"]:checked');
                    checkboxes.forEach((checkbox) => {
                        checkedValues.push(checkbox.value);
                    });
                    let tiposDocumentos = $('#tipoDocumento').val();
                    let tipcuenta = $('#tipcuenta').val();

                    reportes([
                        [`finicio`, `ffin`],
                        [],
                        [],
                        [checkedValues, tiposDocumentos, tipcuenta]
                    ], tipo, `cuadre_diario`, download, 0, 'dfecope', 'saldogen2', 2, 'Saldos', 2);
                }
                $(document).ready(function() {
                    $('#tipoDocumento').select2({
                        theme: 'bootstrap-5',
                        language: 'es',
                        closeOnSelect: false
                    });
                    $('#tipcuenta').select2({
                        theme: 'bootstrap-5',
                        language: 'es',
                        closeOnSelect: false
                    });
                });
            </script>
        <?php
        }
        break;
    case 'ListadoCuentasActivas': {
            $id = $_POST["xtra"];
            $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
            try {
                $database->openConnection();
                $tiposcuentas = $database->selectColumns('aprtip', ['id_tipo', 'nombre', 'cdescripcion'], '1=?', [1]);
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            } finally {
                $database->closeConnection();
            }
        ?>
            <!-- APR_05_LstdCntsActvsDspnbls -->
            <div class="text" style="text-align:center">LISTADO DE CUENTAS ACTIVAS/INACTIVAS</div>
            <input type="text" value="ListadoCuentasActivas" id="condi" style="display: none;">
            <input type="text" value="APRT_5" id="file" style="display: none;">

            <div class="card">
                <div class="card-header">Listado de Cuentas Activas/Inactivas</div>
                <div class="card-body">
                    <!-- Card para los estados de cuenta -->
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header"><b>Estados de cuenta</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <!-- radio button de todas las cuentas -->
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_estado" id="r_todos" checked value="0">
                                                <label class="form-check-label" for="r_todos">Todos</label>
                                            </div>
                                        </div>

                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_estado" id="r_activos" value="A">
                                                <label class="form-check-label" for="r_activos">Activos</label>
                                            </div>
                                        </div>
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_estado" id="r_inactivos" value="B">
                                                <label class="form-check-label" for="r_inactivos">Inactivos</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- segunda linea -->
                    <div class="row d-flex align-items-stretch mb-3">
                        <!-- card para filtrar cuentas -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro de cuentas</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_cuenta" id="r_cuentas" value="1" checked onclick="activar_select_cuentas(this, true,'tipcuenta')">
                                                <label class="form-check-label" for="r_cuentas">Todos</label>
                                            </div>
                                        </div>
                                        <div class="col d-flex justify-content-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="filter_cuenta" id="r_cuenta" value="2" onclick="activar_select_cuentas(this,false,'tipcuenta')">
                                                <label class="form-check-label" for="r_cuenta">Una cuenta</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- card para seleccionar una cuenta -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtrar por una cuenta</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <select class="form-select" aria-label="Default select example" id="tipcuenta" disabled>
                                                <?php
                                                echo '<option selected value="0">Seleccione un tipo de cuenta</option>';
                                                foreach ($tiposcuentas as $tip) {
                                                    echo '<option value="' . $tip['id_tipo'] . '">' . $tip['nombre'] . ' - ' . $tip['cdescripcion'] . '</option>';
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
                        <div class="col align-items-center" id="modal_footer">
                            <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                            <button type="button" id="btnSave" class="btn btn-outline-success" onclick="obtiene([],[`tipcuenta`],[`filter_estado`,`filter_cuenta`],`reporte_cuentas_act_inact_aprt`,`excel`,['<?php echo $idusuario; ?>','<?php echo $ofi; ?>'])">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                            </button>

                            <button type="button" id="btnSave" class="btn btn-outline-primary" onclick="obtiene([],[`tipcuenta`],[`filter_estado`,`filter_cuenta`],`reporte_cuentas_act_inact_aprt`,`pdf`,['<?php echo $idusuario; ?>','<?php echo $ofi; ?>'])">
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
}
?>