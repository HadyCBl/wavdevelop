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
// require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/database.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

// date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");

$condi = $_POST["condi"];
switch ($condi) {
    case 'IntrsManal':

        $agencia = $_SESSION['agencia'];
        $codusu = $_SESSION['id'];

        try {
            $database->openConnection();
            $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], 'estado=1');

            $calculos = $database->selectAll('ahointeredetalle');
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        } finally {
            $database->closeConnection();
        }
        ?>
        <input type="text" id="file" value="aho_05" style="display: none;">
        <input type="text" id="condi" value="IntrsManal" style="display: none;">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa-solid fa-sack-dollar"></i> Intereses Manuales</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="card text-bg-light">
                            <div class="card-header bg-primary text-white"><i class="fas fa-filter me-2"></i> Filtro por tipos
                                de cuentas</div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="r1" id="all" value="all" checked
                                                onclick="habdeshab([],['tipcuenta'])">
                                            <label for="all" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="r1" id="any" value="any"
                                                onclick="habdeshab(['tipcuenta'],[])">
                                            <label for="any" class="form-check-label"> Por Tipo de Cuenta</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div>
                                            <span class="input-group-addon col-2">Tipo de Cuenta</span>
                                            <select class="form-select" id="tipcuenta" placeholder="" disabled>
                                                <option value="0" selected disabled>Seleccionar tipo de cuenta</option>
                                                <?php
                                                foreach ($tiposcuentas as $cuenta) {
                                                    echo '<option value="' . $cuenta['ccodtip'] . '">' . $cuenta['ccodtip'] . " - " . $cuenta['nombre'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light mb-3" style="height: 100%;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-calendar-day"></i> Filtro por fechas</h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class=" col-sm-5">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-5">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnSave" class="btn btn-outline-primary"
                            onclick="obtiene([`finicio`,`ffin`],[`tipcuenta`],[`r1`],`process`,`0`,[])">
                            <i class="fa-solid fa-file-export"></i> Procesar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>

                <div class="contenedort mt-2" style="padding: 8px !important;">
                    <div class="table-responsive">
                        <table id="table_id2" class="table table-hover table-border">
                            <thead class="text-light table-head-aho" style="font-size: 0.8rem;">
                                <tr>
                                    <th>N</th>
                                    <th>Fecha y hora</th>
                                    <th>Rango</th>
                                    <th>Tipo</th>
                                    <th>Total inte.</th>
                                    <th>Total isr</th>
                                    <th>Acreditado</th>
                                    <th>Partida Prov.</th>
                                    <th>Reportes</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="categoria_tb">
                                <?php
                                $check = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="40" height="40" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00b341" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <circle cx="12" cy="12" r="9" />
                                <path d="M9 12l2 2l4 -4" />
                              </svg>';
                                $enabled = '<svg width="40" height="40" viewBox="0 0 512 512" style="color:currentColor" xmlns="http://www.w3.org/2000/svg" class="h-full w-full"><rect width="484" height="484" x="14" y="14" rx="82" fill="transparent" stroke="transparent" stroke-width="28" stroke-opacity="100%" paint-order="stroke"></rect><svg width="512px" height="512px" viewBox="0 0 64 64" fill="currentColor" x="0" y="0" role="img" style="display:inline-block;vertical-align:middle" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path fill="currentColor" d="M32 0C14 0 0 14 0 32c0 21 19 30 22 30c2 0 2-1 2-2v-5c-7 2-10-2-11-5c0 0 0-1-2-3c-1-1-5-3-1-3c3 0 5 4 5 4c3 4 7 3 9 2c0-2 2-4 2-4c-8-1-14-4-14-15c0-4 1-7 3-9c0 0-2-4 0-9c0 0 5 0 9 4c3-2 13-2 16 0c4-4 9-4 9-4c2 7 0 9 0 9c2 2 3 5 3 9c0 11-7 14-14 15c1 1 2 3 2 6v8c0 1 0 2 2 2c3 0 22-9 22-30C64 14 50 0 32 0Z"/></g></svg></svg>';
                                foreach ($calculos as $row) {
                                    $idcal = $row["id"];
                                    $fechaCompleta = $row["fecmod"];
                                    $partes = explode(" ", $fechaCompleta);
                                    if (count($partes) === 2) {
                                        $fecha = $partes[0];
                                        $hora = $partes[1];
                                        $fechaSeparada = $fecha . '  /Hora  ' . $hora;
                                    }

                                    $intereses = "Q. " . number_format((float) $row["int_total"], 2);
                                    $isrcal = "Q. " . number_format((float) ($row["isr_total"]), 2);
                                    $rangoCompleto = $row["rango"];
                                    if (strpos($rangoCompleto, "_") !== false) {
                                        $partesRango = explode("_", $rangoCompleto);
                                        if (count($partesRango) === 2) {
                                            $fechaInicio = $partesRango[0];
                                            $fechaFin = $partesRango[1];

                                            $rango = $fechaInicio . '  al ' . $fechaFin;
                                        } else {
                                            echo "Error:";
                                        }
                                    } else {
                                        $rango = $rangoCompleto;
                                    }
                                    $tipcuenta = $row["tipo"];
                                    $partida = $row["partida"];
                                    $acreditado = $row["acreditado"];
                                    $usuario = $row["codusu"];
                                    $fechacorte = $row["fechacorte"];

                                    $acre = ($acreditado == 1) ? $check : (($partida == 1) ? $enabled : '<button type="button" class="btn btn-outline-dark" title="Acreditacr" onclick="obtiene([`finicio`],[`tipcuenta`],[`r1`],`acredita`,`0`,[' . $idcal . ',' . $codusu . ',`' . $fechacorte . '`,`' . $agencia . '`,`' . $rango . '`])">
                                                    <i class="fa-solid fa-money-bill-transfer"  style="color: rgb(29, 232, 38 );"></i>
                                                </button>');

                                    $part = ($partida == 1) ? $check : (($acreditado == 1) ? $enabled : '<button type="button" class="btn btn-outline-primary" title="Partida de provision" onclick="obtiene([`finicio`],[`tipcuenta`],[`r1`],`partidaprov`,`0`,[' . $idcal . ',' . $codusu . ',`' . $fechacorte . '`,`' . $rango . '`])">
                                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                                </button>');

                                    $buttondeletecalculo = ($acreditado == 1 || $partida == 1) ? "" : '<button type="button" class="btn btn-outline-danger" title="Eliminar Calculo" onclick="eliminar(' . $idcal . ', `crud_ahorro`, `0`, `delete_calculo_interes`)"> <i class="fa-solid fa-trash-can"></i></button>';

                                    echo '<tr>
                                            <td>' . $idcal . ' </td>
                                            <td>' . $fechaSeparada . ' </td>
                                            <td>' . $rango . ' </td>
                                            <td>' . $tipcuenta . ' </td>
                                            <td>' . $intereses . ' </td>
                                            <td>' . $isrcal . '</td>
                                            <td align="center">' . $acre . '
                                            </td>
                                            <td align="center">' . $part . '
                                            </td>
                                            <td> <button type="button" class="btn btn-outline-success" title="Reporte Excel" onclick="reportes([[],[],[],[' . $idcal . ']],`xlsx`,`ahocalculo`)">
                                                        <i class="fa-solid fa-file-excel"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" title="Reporte pdf" onclick="reportes([[],[],[],[' . $idcal . ']],`pdf`,`ahocalculo`)">
                                                        <i class="fa-solid fa-file-pdf"></i>
                                                    </button>
                                            </td>
                                            <td>' . $buttondeletecalculo . '</td>
                                        </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <script>
                    //Datatable para parametrizacion
                    $(document).ready(function () {
                        convertir_tabla_a_datatable("table_id2");
                    });
                </script>

            </div>
        </div>
        <?php
        break;

    case 'intmanualindi':
        $account = $_POST["xtra"];
        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,url_img urlfoto,numfront,numdors, tip.nombre tipoNombre,
                    IFNULL((SELECT MAX(`numlinea`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimonum,
                    IFNULL((SELECT MAX(`correlativo`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimocorrel,
                        calcular_saldo_aho_tipcuenta(cta.ccodaho,?) saldo
                        FROM `ahomcta` cta 
                        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                        INNER JOIN ahomtip tip on tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                        WHERE `ccodaho`=? AND cli.estado=1";

        $src = '../../includes/img/fotoClienteDefault.png';
        $status = false;
        try {
            if ($account == '0') {
                throw new SoftException("Seleccione una cuenta de ahorros");
            }
            $database->openConnection();

            $data = $database->getAllResults($query, [$hoy, $account]);
            if (empty($data)) {
                throw new SoftException("No se encontró la cuenta de ahorros, verifique el número de cuenta o si el cliente esta activo");
            }
            $dataAccount = $data[0];

            if ($dataAccount['ultimonum'] >= ($dataAccount['numfront'] + $dataAccount['numdors'])) {
                throw new SoftException("El número de líneas en libreta ha llegado a su límite, se recomienda abrir otra libreta");
            }

            if ($dataAccount['estado'] != "A") {
                throw new SoftException("Cuenta de ahorros Inactiva");
            }

            /**
             * Formateo de fotografia
             */

            $imgurl = __DIR__ . '/../../../' . $dataAccount['urlfoto'];
            if (is_file($imgurl)) {
                $imginfo = getimagesize($imgurl);
                $mimetype = $imginfo['mime'];
                $imageData = base64_encode(file_get_contents($imgurl));
                $src = 'data:' . $mimetype . ';base64,' . $imageData;
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

        $showIntPorPagar = $_ENV['AHO_SHOW_INTPORPAGAR'] ?? 0;

        include_once "../../src/cris_modales/mdls_aho_new.php";
        ?>
        <div class="card">
            <input type="text" id="file" value="aho_05" style="display: none;">
            <input type="text" id="condi" value="intmanualindi" style="display: none;">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa-solid fa-sack-dollar"></i> Acreditacion de interés manual individual</h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="container contenedort">
                    <div class="row">
                        <div class="col-lg-2 col-sm-6 col-md-4 mt-2">
                            <img width="130" height="150" id="vistaPrevia" src="<?= $src ?? '' ?>">
                        </div>
                        <div class="col-lg-8 col-sm-6 col-md-8">
                            <div class="row">
                                <div class="col-sm-8 col-md-8 col-lg-8">
                                    <span class="input-group-addon col-8">Codigo de Cuenta</span>
                                    <input type="text" class="form-control " id="ccodaho" required placeholder="   -   -  -  "
                                        value="<?= $account ?? '' ?>">
                                </div>
                                <div class="col-sm-4 col-md-4 col-lg-4">
                                    <br>
                                    <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                        title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaho')">
                                        <i class="fa fa-check-to-slot"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                        title="Buscar cuenta" data-bs-toggle="modal" data-bs-target="#findahomcta2">
                                        <i class="fa fa-magnifying-glass"></i>
                                    </button>
                                </div>
                                <div class="col-sm-10 col-md-10 col-lg-10">
                                    <span class="input-group-addon col-8">Nombre</span>
                                    <input type="text" class="form-control " id="name"
                                        value="<?= $dataAccount['short_name'] ?? '' ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="container contenedort" style="display: block;">
                    <div class="row mb-3">
                        <div class="col-sm-5 col-md-4 col-lg-4">
                            <span class="input-group-addon col-8">Fecha inicio</span>
                            <input type="date" class="form-control " id="fecini" value="<?= $hoy ?>">
                        </div>
                        <div class="col-sm-5 col-md-4 col-lg-4">
                            <span class="input-group-addon col-8">Fecha fin</span>
                            <input type="date" class="form-control " id="fecfin" value="<?= $hoy ?>">
                        </div>
                        <div class="col-sm-2 col-md-4 col-lg-2">
                            <br>
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1" title="Calcular interés"
                                onclick="document.getElementById('table_container').style.display = 'none'; obtiene(['<?= $csrf->getTokenName() ?>', 'fecini', 'fecfin'], [], [], 'procesCalculoIndi', '0', ['<?= htmlspecialchars($secureID->encrypt($account)) ?>']);">
                                <i class="fa fa-check-to-slot"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row" style="display: none;" id="table_container">
                        <table id="table_id2" class="table table-hover">
                            <thead style="font-size: 0.8rem;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                    <th>Doc</th>
                                    <th>Saldo</th>
                                    <th>SaldoAnt</th>
                                    <th>Dias</th>
                                    <th>Int</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="container contenedort" x-data="{}" id="formIntereses">
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <span class="input-group-addon col-8">Interés</span>
                            <input type="number" step="0.01" data-label="Interés" class="form-control" id="monint" required
                                placeholder="0.00" min="0">
                        </div>
                        <div class="col-sm-4">
                            <span class="input-group-addon col-8">Impuesto</span>
                            <input type="number" step="0.01" class="form-control" id="monipf" placeholder="0.00" min="0.00">
                        </div>
                        <div class="col-sm-4" style="display: <?= ($showIntPorPagar == 1) ? 'block' : 'none' ?>">
                            <span class="input-group-addon col-8">Intereses x pagar</span>
                            <input type="number" step="0.01" class="form-control" id="monipx" placeholder="0.00" min="0.00">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-8 col-md-6 col-lg-4">
                            <span class="input-group-addon col-8">Fecha de acreditacion</span>
                            <input type="date" class="form-control " id="dfecope" value="<?= $hoy ?>" required>
                        </div>
                        <div class="col-sm-8 col-md-6 col-lg-4">
                            <span class="input-group-addon col-8">Documento</span>
                            <input type="text" class="form-control " id="cnumdoc">
                        </div>
                    </div>
                </div>
                <?php echo $csrf->getTokenField(); ?>
                <div class="row mb-3 justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <?php if ($status): ?>
                            <button type="button" id="btnSave" class="btn btn-outline-success" onclick="confirmSave()">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar
                            </button>
                        <?php endif; ?>
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
        <script>
            function confirmSave() {
                var cantidad = document.getElementById("monint").value;
                obtiene(['<?= $csrf->getTokenName() ?>', 'dfecope', 'monint', 'monipf', 'monipx', 'cnumdoc'], [], [], 'acreditaindi', '0', ['<?= htmlspecialchars($secureID->encrypt($account)) ?>'], function (data) {
                    // console.log(data);
                    reportes([
                        ['<?= $csrf->getTokenName() ?>'],
                        [],
                        [],
                        [{
                            id: data.idAhommov,
                            id2: data.idAhommov2
                        }]
                    ],
                        'pdf', 'comprobante_interes_manual_main', 0, 0)
                }, true, "Deseas acreditar la cantidad de " + cantidad + "?");
            }

            function loadDataProcess(data) {
                // console.log(data);
                var table = document.getElementById("table_id2");
                var tbody = table.getElementsByTagName("tbody")[0];
                tbody.innerHTML = "";
                data[2].forEach((element, index) => {
                    var row = tbody.insertRow();
                    row.innerHTML = `
                        <td>${element.dfecope}</td>
                        <td>${element.ctipope}</td>
                        <td>${element.monto}</td>
                        <td>${element.cnumdoc}</td>
                        <td>${element.saldo}</td>
                        <td>${element.saldoant}</td>
                        <td>${element.dias}</td>
                        <td>${element.interescal}</td>
                    `;
                });
                document.getElementById("table_container").style.display = "block";
                document.getElementById("monint").value = data[3];
                document.getElementById("monipf").value = data[4];
                document.getElementById("dfecope").value = data[5];
            }

            $(document).ready(function () {
                inicializarValidacionAutomaticaGeneric('#formIntereses');
            });
        </script>
        <?php
        break;
}
