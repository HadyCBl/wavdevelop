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
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

include '../../includes/BD_con/db_con.php';
$condi = $_POST["condi"];
switch ($condi) {
    case 'AddCertif':
        $account = $_POST["xtra"];
        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,cta.tasa,
                        tip.tipcuen,tip.diascalculo,tip.inicioCalculo
                    FROM `ahomcta` cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                    INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                    WHERE `ccodaho`=?";

        $showmensaje = false;
        try {
            if ($account == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta de Plazo Fijo");
            }
            $database->openConnection();

            $dataAccount = $database->getAllResults($query, [$account]);
            if (empty($dataAccount)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta");
            }

            if ($dataAccount[0]['estado'] != "A") {
                $showmensaje = true;
                throw new Exception("Cuenta Inactiva");
            }
            if ($dataAccount[0]['tipcuen'] != "pf") {
                $showmensaje = true;
                throw new Exception("Cuenta no es de tipo Plazo Fijo");
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
        $fec1anio = strtotime('+365 day', strtotime($hoy));
        $fec1anio = date('Y-m-j', $fec1anio);
        include_once "../../src/cris_modales/mdls_aho_new.php";
        ?>
        <div class="text" style="text-align:center">ADICION DE CERTIFICADOS DE PLAZO FIJO</div>
        <input type="text" id="condi" value="AddCertif" hidden>
        <input type="text" id="file" value="aho_04" hidden>
        <div class="card">
            <div class="card-header">Adicion de certificados</div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="accordion" id="accordionExample">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" aria-expanded="true" aria-controls="collapseOne">
                                IDENTIFICACION DEL CLIENTE
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-1">
                                    <div class="col-sm-5">
                                        <div>
                                            <span class="input-group-addon col-8">Codigo de Cuenta</span>
                                            <input type="text" aria-label="Cuenta" id="codaho" class="form-control  col"
                                                placeholder="" value="<?= ($status) ? $account : '' ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <br>
                                        <button title="Buscar cuenta" class="btn btn-outline-secondary" type="button"
                                            id="button-addon1" data-bs-toggle="modal" data-bs-target="#findahomcta2">
                                            <i class="fa fa-magnifying-glass"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-6">
                                        <div>
                                            <span class="input-group-addon col-8">Cliente</span>
                                            <input type="text" class="form-control " id="nomcli" placeholder=""
                                                value="<?= ($status) ? $dataAccount[0]['short_name'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div>
                                            <span class="input-group-addon col-8">Codigo de cliente</span>
                                            <input type="text" class="form-control " id="codcli" placeholder=""
                                                value="<?= ($status) ? $dataAccount[0]['ccodcli'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div>
                                            <span class="input-group-addon col-8">NIT</span>
                                            <input type="text" class="form-control " id="nit" placeholder=""
                                                value="<?= ($status) ? $dataAccount[0]['num_nit'] : ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-2">
                                        <span class="input-group-addon col-8">Certificado</span>
                                        <input type="text" aria-label="Certificado" id="certif" class="form-control  col"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapseTwo">
                                DATOS DE LA CUENTA
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Monto</span>
                                        <input type="float" class="form-control" id="monapr" placeholder="0.00"
                                            required="required" onblur="calcfecven(1)">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Plazo</span>
                                        <input type="number" class="form-control" id="plazo" placeholder="365"
                                            required="required" onblur="calcfecven(2)">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Dias de gracia</span>
                                        <input type="float" class="form-control" id="gracia" placeholder="0" required="required"
                                            value="0">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Interes %</span>
                                        <input type="float" class="form-control" id="tasint" placeholder="0.00"
                                            required="required" value="<?= ($status) ? $dataAccount[0]['tasa'] : 0; ?>"
                                            onblur="calcfecven(1)">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Fecha Apertura</span>
                                        <input type="date" class="form-control" id="fecaper" required="required"
                                            value="<?= $hoy; ?>" onblur="calcfecven(3)">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Fecha Vencimiento</span>
                                        <input type="date" class="form-control" id="fecven" required="required"
                                            value="<?= $hoy; ?>" onblur="calcfecven(3)">
                                    </div>
                                    <div class="col-sm-3" id="spinner" style="display:none;">
                                        <div class="spinner-grow text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Int. Calc.</span>
                                        <input type="float" class="form-control" id="moncal" readonly>
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">IPF</span>
                                        <input type="float" class="form-control" id="intcal" readonly>
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Total a pagar</span>
                                        <input type="float" class="form-control" id="totcal" readonly>
                                    </div>
                                </div>
                                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" id="toastalert">
                                    <div class="toast-header">
                                        <strong class="me-auto">Advertencia</strong>
                                        <small class="text-muted">Tomar en cuenta</small>
                                        <button type="button" class="btn-close" data-bs-dismiss="toast"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="toast-body" id="body_text">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button" type="button">
                                DATOS ADICIONALES
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse show" aria-labelledby="headingThree"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Calc. Interes</span>
                                        <select class="form-select" id="calintere" placeholder=""
                                            aria-label="Default select example">
                                            <option value="M" selected>Mensual</option>
                                            <option value="T">Trimestral</option>
                                            <option value="S">Semestral</option>
                                            <option value="A">Anual</option>
                                            <option value="V">Vencimiento</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <span class="input-group-addon col-8">Pago de intereses</span>
                                        <select class="form-select" id="pagintere" placeholder=""
                                            aria-label="Default select example" onchange="pagintere(this.value)">
                                            <option value="1" selected>Codigo de Cuenta</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">No Recibo</span>
                                        <input type="text" class="form-control" id="norecibo">
                                    </div>
                                </div>
                            </div>
                            <?php echo $csrf->getTokenField(); ?>
                            <div class="row justify-items-md-center">
                                <div class="col align-items-center" id="modal_footer">
                                    <?php if ($status) { ?>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="obtiene(['<?= $csrf->getTokenName() ?>',`certif`,`monapr`,`plazo`,`gracia`,`tasint`,`fecaper`,`fecven`,`norecibo`],[`calintere`,`pagintere`],[],`cahomcrt`,`0`,['<?= htmlspecialchars($secureID->encrypt($account)) ?>'],function(data) {printdiv('certificados', '#cuadro', 'aho_04', '0');});">
                                            <i class="fa fa-floppy-disk"></i> Guardar
                                        </button>
                                    <?php } ?>
                                    <button type="button" class="btn btn-outline-danger"
                                        onclick="printdiv('certificados', '#cuadro', 'aho_04', '0')">
                                        <i class="fa-solid fa-ban"></i> Cancelar
                                    </button>
                                    <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                        <i class="fa-solid fa-circle-xmark"></i> Salir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        break;
    //Modificacion de certificados 
    case 'certificados':
        $id = $_POST["xtra"];
        ?>
        <input type="text" id="condi" value="certificados" hidden>
        <input type="text" id="file" value="aho_04" hidden>
        <div class="card h-100">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-certificate"></i> CERTIFICADOS DE PLAZO FIJO
                </h4>
            </div>
            <div class="card-body">
                <!-- Dropdown de acciones -->
                <div id="actions" class="dropdown"
                    style="display: none; position: fixed; z-index: 1050; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 0; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); min-width: 200px; max-width: 240px;"
                    data-transparency="0.4">
                    <button class="btn btn-sm w-100" value="liquidar"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-sack-dollar"
                            style="margin-right: 8px; width: 16px;"></i>Liquidar</button>
                    <button class="btn btn-sm w-100" value="printliquid"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-print"
                            style="margin-right: 8px; width: 16px;"></i>Imprimir Liquid.</button>
                    <button class="btn btn-sm w-100" value="edit"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-pen"
                            style="margin-right: 8px; width: 16px;"></i>Modificar</button>
                    <button class="btn btn-sm w-100" value="beneficiarios"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-people-line"
                            style="margin-right: 8px; width: 16px;"></i>Beneficiarios</button>
                    <button class="btn btn-sm w-100" value="testigos"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-user-group"
                            style="margin-right: 8px; width: 16px;"></i>Testigos</button>
                    <button class="btn btn-sm w-100" value="delete"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-trash-can"
                            style="margin-right: 8px; width: 16px;"></i>Eliminar
                        Certificado</button>
                    <button class="btn btn-sm w-100" value="printcert"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-print"
                            style="margin-right: 8px; width: 16px;"></i>Impr. Certificado</button>
                    <button class="btn btn-sm w-100" value="printcontrato"
                        style="text-align: left; font-size: 0.85rem; padding: 10px 12px; border-radius: 0; line-height: 1.4; border: none; background: rgba(255, 255, 255, 0.1); color: #fff; transition: all 0.3s ease; cursor: pointer;"
                        onmouseover="this.style.background='rgba(255, 255, 255, 0.2)'"
                        onmouseout="this.style.background='rgba(255, 255, 255, 0.1)'"><i class="fa-solid fa-file-lines"
                            style="margin-right: 8px; width: 16px;"></i>Impr. Contrato</button>
                </div>

                <script>
                    function setDropdownTransparency(transparencyLevel) {
                        const actions = document.querySelector('#actions');
                        const transparency = Math.max(0, Math.min(1, transparencyLevel));
                        actions.setAttribute('data-transparency', transparency);
                        actions.style.background = `rgba(0, 0, 0, ${transparency})`;
                    }
                </script>
                <div class="container contenedort">
                    <div class="table-responsive">
                        <table id="tb_certificados" class="table table-hover table-border" style="width:100%">
                            <thead class="text-light table-head-aho">
                                <tr>
                                    <th>Cert.</th>
                                    <th>Nombre cliente</th>
                                    <th>Cuenta</th>
                                    <th>Monto</th>
                                    <th>Apertura</th>
                                    <th>Vence</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="table-group-divider" style="font-size: 0.9rem !important;">
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php echo $csrf->getTokenField(); ?>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnnew" class="btn btn-outline-success"
                            onclick="printdiv('AddCertif', '#cuadro', 'aho_04', '0')">
                            <i class="fa fa-file"></i> Adicion de certificado
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

        <script>
            //Datatable para parametrizacion
            // $(document).ready(function() {
            //     convertir_tabla_a_datatable("tb_certificados");
            // });
            $(document).ready(function () {
                $("#tb_certificados").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/certificadosAhorro.php",
                    "columnDefs": [{
                        "data": 7,
                        "targets": 6,
                        render: function (data, type, row) {
                            liquidado = row[6];
                            return (liquidado == "S") ? "Liquidado" : "Vigente";
                        }
                    },
                    {
                        "data": 7,
                        "targets": 7,
                        className: 'dt-center actions',
                        render: function (data, type, row) {
                            return `<button class="btn btn-secondary btn-sm dropdown-btn" style="background-color: transparent; border: none; color: var(--vscode-foreground, currentColor);" data-id="${data}" data-liquidado="${row[6]}"><i class="fa-solid fa-bars"></i></button>`;
                            
                        },
                        orderable: false
                    },
                    ],
                    "bDestroy": true,
                    "language": {
                        "lengthMenu": "Mostrar _MENU_ registros",
                        "zeroRecords": "No se encontraron registros",
                        "info": " ",
                        "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "infoFiltered": "(filtrado de un total de: _MAX_ registros)",
                        "sSearch": "Buscar: ",
                        "oPaginate": {
                            "sFirst": "Primero",
                            "sLast": "Ultimo",
                            "sNext": "Siguiente",
                            "sPrevious": "Anterior"
                        },
                        "sProcessing": "Procesando..."
                    }
                });

                // Manejo del dropdown de acciones
                let actions = document.querySelector('#actions');
                let currentRowData = null;
                let currentRowLiquidado = null;
                let currentButton = null;
                let isDropdownOpen = false;

                // Mostrar/ocultar dropdown al hacer clic en el botón de acciones (TOGGLE)
                $('#tb_certificados').on('click', 'button.dropdown-btn', function (e) {
                    e.stopPropagation();

                    // Si el dropdown ya está abierto y es el mismo botón, cerrarlo
                    if (isDropdownOpen && currentButton === this) {
                        actions.style.display = 'none';
                        isDropdownOpen = false;
                        currentButton = null;
                        return;
                    }

                    currentButton = this;
                    currentRowData = $(this).data('id');
                    currentRowLiquidado = $(this).data('liquidado');

                    // Mostrar/ocultar botones según estado de liquidación
                    if (currentRowLiquidado === 'S') {
                        $('#actions button[value="liquidar"]').hide();
                        $('#actions button[value="edit"]').hide();
                        $('#actions button[value="beneficiarios"]').hide();
                        $('#actions button[value="delete"]').hide();
                        $('#actions button[value="printliquid"]').show();
                    } else {
                        $('#actions button[value="liquidar"]').show();
                        $('#actions button[value="edit"]').show();
                        $('#actions button[value="beneficiarios"]').show();
                        $('#actions button[value="delete"]').show();
                        $('#actions button[value="printliquid"]').hide();
                    }

                    // Posicionar y mostrar el dropdown
                    const rect = this.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;

                    // Mostrar el dropdown temporalmente para obtener sus dimensiones
                    actions.style.display = 'block';
                    actions.style.visibility = 'hidden';

                    const actionsRect = actions.getBoundingClientRect();
                    const dropdownWidth = actionsRect.width || 230;
                    const dropdownHeight = actionsRect.height || 250;

                    let top = rect.bottom + window.scrollY + 5;
                    let left = rect.right + window.scrollX - dropdownWidth + 15;

                    // Ajustar si sale de la pantalla horizontalmente
                    if (left + dropdownWidth > viewportWidth + window.scrollX) {
                        left = rect.left + window.scrollX - dropdownWidth + 15;
                    }

                    // Ajustar si sale de la pantalla verticalmente
                    if (top + dropdownHeight > viewportHeight + window.scrollY) {
                        top = rect.top + window.scrollY - dropdownHeight - 5;
                    }

                    actions.style.top = top + 'px';
                    actions.style.left = left + 'px';
                    actions.style.visibility = 'visible';
                    isDropdownOpen = true;
                });

                // Ocultar dropdown al hacer clic fuera
                $(document).on('click', function (e) {
                    if (!$(e.target).closest('#actions').length && !$(e.target).closest('button.dropdown-btn').length) {
                        actions.style.display = 'none';
                        isDropdownOpen = false;
                        currentButton = null;
                    }
                });

                // Acreditar y liquidar
                $('#actions').on('click', 'button[value="liquidar"]', function (e) {
                    e.stopPropagation();
                    printdiv('liquidcrt', '#cuadro', 'aho_04', currentRowData);
                    actions.style.display = 'none';
                });

                // Imprimir comprobante liquidación
                $('#actions').on('click', 'button[value="printliquid"]', function (e) {
                    e.stopPropagation();
                    obtiene([], [], [], 'printliquidcrt', '0', [currentRowData]);
                    actions.style.display = 'none';
                });

                // Modificar certificado
                $('#actions').on('click', 'button[value="edit"]', function (e) {
                    e.stopPropagation();
                    printdiv('modcrt', '#cuadro', 'aho_04', currentRowData);
                    actions.style.display = 'none';
                });

                // Añadir beneficiarios
                $('#actions').on('click', 'button[value="beneficiarios"]', function (e) {
                    e.stopPropagation();
                    printdiv('benecrt', '#cuadro', 'aho_04', currentRowData);
                    actions.style.display = 'none';
                });

                // Añadir testigos
                $('#actions').on('click', 'button[value="testigos"]', function (e) {
                    e.stopPropagation();
                    printdiv('addTestigo', '#cuadro', 'aho_04', currentRowData);
                    actions.style.display = 'none';
                });

                // Eliminar certificado
                $('#actions').on('click', 'button[value="delete"]', function (e) {
                    e.stopPropagation();
                    obtiene(['csrf_token'], [], [], 'dahomcrt', '0', [currentRowData], '', true, '¿Está seguro de eliminar el certificado?');
                    actions.style.display = 'none';
                });

                // Imprimir certificado
                $('#actions').on('click', 'button[value="printcert"]', function (e) {
                    e.stopPropagation();
                    obtiene([], [], [], 'printcrt', '0', [currentRowData], function (data) { printcrt(currentRowData, data); });
                    actions.style.display = 'none';
                });

                // Imprimir contrato
                $('#actions').on('click', 'button[value="printcontrato"]', function (e) {
                    e.stopPropagation();
                    reportes([[], [], [], [currentRowData]], 'pdf', 42, [], 1, []);
                    actions.style.display = 'none';
                });
            });
        </script>

        <?php
        break;

    //Reimpresion de certuficadio

    case 'modcrt':
        $idCertificado = $_POST["xtra"];
        $query = "SELECT short_name,cta.ccodcli,cta.ccodaho,no_tributaria,crt.ccodcrt,crt.montoapr,crt.plazo,crt.dia_gra,crt.interes,crt.fec_apertura,crt.fec_ven,
                    crt.calint,crt.pagint,crt.recibo,tip.diascalculo,tip.inicioCalculo,tip.isr
                    FROM ahomcrt crt 
                    INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                    INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                    WHERE id_crt=?;";
        $showmensaje = false;
        try {
            if ($idCertificado == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione un certificado a modificar");
            }
            $database->openConnection();

            $dataAccount = $database->getAllResults($query, [$idCertificado]);
            if (empty($dataAccount)) {
                $showmensaje = true;
                throw new Exception("No se encontró el certificado");
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
        $fec1anio = strtotime('+365 day', strtotime($hoy));
        $fec1anio = date('Y-m-j', $fec1anio);

        $intcal = $dataAccount[0]['montoapr'] * ($dataAccount[0]['interes'] / 100 / $dataAccount[0]['diascalculo']);
        $intcal = $intcal * $dataAccount[0]['plazo'];
        $ipf = $intcal * ($dataAccount[0]['isr'] / 100);
        $total = $intcal - $ipf;
        ?>
        <input type="text" id="condi" value="addcrt" hidden>
        <input type="text" id="file" value="aho_04" hidden>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fa-solid fa-pen-to-square"></i> Modificacion de certificados
                </h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="accordion" id="accordionExample">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" aria-expanded="true" aria-controls="collapseOne">
                                IDENTIFICACION DEL CLIENTE
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-2">
                                        <span class="input-group-addon col-8">Certificado</span>
                                        <input type="text" aria-label="Certificado" id="certif" class="form-control  col"
                                            placeholder="" disabled value="<?= $dataAccount[0]['ccodcrt'] ?>">
                                    </div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-sm-5">
                                        <div>
                                            <span class="input-group-addon col-8">Codigo de Cuenta</span>
                                            <input type="text" aria-label="Cuenta" id="codaho" class="form-control  col"
                                                placeholder="" value="<?= $dataAccount[0]['ccodaho'] ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-6">
                                        <div>
                                            <span class="input-group-addon col-8">Cliente</span>
                                            <input type="text" class="form-control " id="nomcli" placeholder=""
                                                value="<?= $dataAccount[0]['short_name'] ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div>
                                            <span class="input-group-addon col-8">Codigo de cliente</span>
                                            <input type="text" class="form-control " id="codcli" placeholder=""
                                                value="<?= $dataAccount[0]['ccodcli'] ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div>
                                            <span class="input-group-addon col-8">NIT</span>
                                            <input type="text" class="form-control " id="nit" placeholder=""
                                                value="<?= $dataAccount[0]['no_tributaria'] ?>" readonly>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapseTwo">
                                DATOS DE LA CUENTA
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Monto</span>
                                        <input type="float" class="form-control" id="monapr" placeholder="0.00"
                                            required="required" value="<?= $dataAccount[0]['montoapr'] ?>"
                                            onblur="calcfecven(1)">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Plazo</span>
                                        <input type="number" class="form-control" id="plazo" placeholder="365"
                                            required="required" value="<?= $dataAccount[0]['plazo'] ?>"
                                            onchange="calcfecven(2)">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Dias de gracia</span>
                                        <input type="float" class="form-control" id="gracia" placeholder="0" required="required"
                                            value="<?= $dataAccount[0]['dia_gra'] ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Interes %</span>
                                        <input type="float" class="form-control" id="tasint" placeholder="0.00"
                                            required="required" onchange="calcfecven(1)"
                                            value="<?= $dataAccount[0]['interes'] ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Fecha Apertura</span>
                                        <input type="date" class="form-control" id="fecaper" required="required"
                                            value="<?= $dataAccount[0]['fec_apertura'] ?>" onblur="calcfecven(3)">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Fecha Vencimiento</span>
                                        <input type="date" class="form-control" id="fecven" required="required"
                                            value="<?= $dataAccount[0]['fec_ven'] ?>" onblur="calcfecven(3)">
                                    </div>
                                    <div class="col-sm-3" id="spinner" style="display:none;">
                                        <div class="spinner-grow text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Int. Calc.</span>
                                        <input type="float" class="form-control" id="moncal" readonly
                                            value="<?= number_format((float) $intcal, 2); ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">IPF</span>
                                        <input type="float" class="form-control" id="intcal" readonly
                                            value="<?= number_format((float) $ipf, 2); ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Total a pagar</span>
                                        <input type="float" class="form-control" id="totcal" readonly
                                            value="<?= number_format((float) $total, 2); ?>">
                                    </div>
                                </div>
                                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" id="toastalert">
                                    <div class="toast-header">
                                        <strong class="me-auto">Advertencia</strong>
                                        <small class="text-muted">Tomar en cuenta</small>
                                        <button type="button" class="btn-close" data-bs-dismiss="toast"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="toast-body" id="body_text">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button" type="button">
                                DATOS ADICIONALES
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse show" aria-labelledby="headingThree"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Calc. Interes</span>
                                        <select class="form-select" id="calintere" placeholder=""
                                            aria-label="Default select example">
                                            <option value="M" <?= ($dataAccount[0]['calint'] == 'M') ? 'selected' : ''; ?>>Mensual
                                            </option>
                                            <option value="T" <?= ($dataAccount[0]['calint'] == 'T') ? 'selected' : ''; ?>>
                                                Trimestral</option>
                                            <option value="S" <?= ($dataAccount[0]['calint'] == 'S') ? 'selected' : ''; ?>>
                                                Semestral</option>
                                            <option value="A" <?= ($dataAccount[0]['calint'] == 'A') ? 'selected' : ''; ?>>Anual
                                            </option>
                                            <option value="V" <?= ($dataAccount[0]['calint'] == 'V') ? 'selected' : ''; ?>>
                                                Vencimiento</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <span class="input-group-addon col-8">Pago de intereses</span>
                                        <select class="form-select" id="pagintere" placeholder=""
                                            aria-label="Default select example" onchange="pagintere(this.value)">
                                            <option value="1" <?= ($dataAccount[0]['pagint'] == '1') ? 'selected' : ''; ?>>Codigo
                                                de Cuenta</option>
                                            <option value="2" <?= ($dataAccount[0]['pagint'] == '2') ? 'selected' : ''; ?>>Cheque
                                                personal</option>
                                            <option value="3" <?= ($dataAccount[0]['pagint'] == '3') ? 'selected' : ''; ?>>Cuenta
                                                corriente</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">No recibo</span>
                                        <input type="text" class="form-control" id="norecibo"
                                            value="<?= $dataAccount[0]['recibo'] ?>">
                                    </div>
                                </div>
                                <br>
                                <?php echo $csrf->getTokenField(); ?>
                                <div class="row justify-items-md-center">
                                    <div class="col align-items-center" id="modal_footer">
                                        <?php if ($status) { ?>
                                            <button type="button" class="btn btn-outline-success"
                                                onclick="obtiene(['<?= $csrf->getTokenName() ?>',`certif`,`monapr`,`plazo`,`gracia`,`tasint`,`fecaper`,`fecven`,`norecibo`],[`calintere`,`pagintere`],[],`uahomcrt`,`0`,['<?= htmlspecialchars($secureID->encrypt($idCertificado)) ?>'],function(data) {printdiv('certificados', '#cuadro', 'aho_04', '0');});">
                                                <i class="fa fa-floppy-disk"></i> Actualizar
                                            </button>
                                        <?php } ?>
                                        <button type="button" class="btn btn-outline-danger"
                                            onclick="printdiv('certificados', '#cuadro', 'aho_04', '0')">
                                            <i class="fa-solid fa-ban"></i> Cancelar
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                            <i class="fa-solid fa-circle-xmark"></i> Salir
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        break;

    //beneficiarios de deposito a plazo
    case 'benecrt':
        $idCertificado = $_POST["xtra"];
        $query = "SELECT short_name,ccodcrt,crt.codaho FROM ahomcrt crt 
                    INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                    WHERE id_crt=?;";

        $query2 = "SELECT ben.*,IFNULL(par.descripcion,' ') parentesco FROM ahomben ben
                    LEFT JOIN tb_parentescos par ON par.id=ben.codparent
                    WHERE codaho=?;";
        $showmensaje = false;
        try {
            if ($idCertificado == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione un certificado a modificar");
            }
            $database->openConnection();

            $dataAccount = $database->getAllResults($query, [$idCertificado]);
            if (empty($dataAccount)) {
                $showmensaje = true;
                throw new Exception("No se encontró el certificado");
            }

            $dataBeneficiarios = $database->getAllResults($query2, [$dataAccount[0]['codaho']]);

            $parentescos = $database->selectColumns("tb_parentescos", ["id AS id_parent", "descripcion"]);

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

        <div class="card">
            <input type="text" id="file" value="aho_04" style="display: none;">
            <input type="text" id="condi" value="benecrt" style="display: none;">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fa-solid fa-people-group"></i> Beneficiarios
                </h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row contenedort">
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <span class="input-group-addon col-8">Numero de certificado</span>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control " id="ccodcrt" required placeholder="000-000-00-000000"
                                    value="<?= ($status) ? $dataAccount[0]['ccodcrt'] : '' ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <span class="input-group-addon col-8">Codigo de Cuenta</span>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control " id="ccodaho" required placeholder="000-000-00-000000"
                                    value="<?= ($status) ? $dataAccount[0]['codaho'] : '' ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <span class="input-group-addon col-8">Nombre</span>
                            <input type="text" class="form-control " id="name"
                                value="<?= ($status) ? $dataAccount[0]['short_name'] : '' ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="row contenedort">
                    <div class="row">
                        <table id="table_id2" class="table">
                            <thead>
                                <tr>
                                    <th>DPI</th>
                                    <th>Nombre Completo</th>
                                    <th>Fec. Nac.</th>
                                    <th>Parentesco</th>
                                    <th>%</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="categoria_tb">
                                <?php
                                if (!empty($dataBeneficiarios)) {
                                    foreach ($dataBeneficiarios as $key => $value) {
                                        echo '<tr>
                                            <td>' . $value['dpi'] . ' </td>
                                            <td>' . $value['nombre'] . ' </td>
                                            <td>' . $value['fecnac'] . ' </td>
                                            <td>' . $value['parentesco'] . '</td>
                                            <td>' . $value['porcentaje'] . '</td>
                                            <td> <button type="button" class="btn btn-warning" title="Editar Beneficiario" onclick="editben(' . $value['id_ben'] . ',`' . $value['nombre'] . '`,`' . $value['dpi'] . '`,`' . $value['direccion'] . '`,' . $value['codparent'] . ',`' . $value['fecnac'] . '`,' . $value['porcentaje'] . ',`' . $value['telefono'] . '`)">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger" title="Eliminar Beneficiario" onclick="obtiene([`' . $csrf->getTokenName() . '`], [], [], `dahomben`, ' . $idCertificado . ', [`' . htmlspecialchars($secureID->encrypt($value['id_ben'])) . '`], ``, true, `¿Está seguro de eliminar al beneficiario?`);">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                            </tr>';
                                    }
                                }

                                $total = array_sum(array_column($dataBeneficiarios, 'porcentaje'));
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <label for="">Total: <?= $total; ?> %</label>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <?php if ($status) { ?>
                            <button type="button" id="btnnew" class="btn btn-outline-success" data-bs-toggle="modal"
                                data-bs-target="#databen">
                                <i class="fa fa-file"></i> Nuevo
                            </button>
                        <?php } ?>
                        <button type="button" class="btn btn-outline-danger"
                            onclick="printdiv('certificados', '#cuadro', 'aho_04', '0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="databen" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog  modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="staticBackdropLabel">Datos de Beneficiario</h1>
                    </div>
                    <div class="modal-body">
                        <div class="row contenedort">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <span class="input-group-addon">Nombre</span>
                                    <input type="text" aria-label="Nombre Ben" id="benname" class="form-control col"
                                        placeholder="" required>
                                </div>
                                <div class="col-md-4">
                                    <span class="input-group-addon">Dpi</span>
                                    <input type="text" aria-label="Cliente" id="bendpi" class="form-control col" placeholder="">
                                </div>

                            </div>
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <span class="input-group-addon">Direccion</span>
                                    <input type="text" aria-label="Direccion Ben" id="bendire" class="form-control col"
                                        placeholder="" required>
                                </div>
                                <div class="col-md-4">
                                    <span class="input-group-addon col-8">Parentesco</span>
                                    <select class="form-select  col-sm-12" id="benparent">
                                        <option value="0" selected disabled>Seleccione parentesco</option>
                                        <?php
                                        foreach ($parentescos as $key => $value) {
                                            echo '<option value="' . $value['id_parent'] . '">' . $value['descripcion'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <span class="input-group-addon">Telefono</span>
                                    <input type="text" aria-label="Tel Ben" id="bentel" class="form-control col" placeholder="">
                                </div>
                                <div class="col-md-3">
                                    <span class="input-group-addon">Nacimiento</span>
                                    <input type="date" class="form-control  col-10" id="bennac" value="<?= date("Y-m-d"); ?>">
                                </div>
                                <div class="col-md-1">
                                </div>
                                <div class="col-md-2">
                                    <span class="input-group-addon">Porcentaje</span>
                                    <input type="number" class="form-control  col-10" id="benporcent" required
                                        placeholder="0.00">
                                </div>
                                <div style="display:none;" class="col-md-2">
                                    <span class="input-group-addon">anterior</span>
                                    <input type="number" class="form-control  col-10" id="benporcentant">
                                    <input type="number" class="form-control  col-10" id="idben">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php echo $csrf->getTokenField(); ?>

                    <div class="modal-footer">
                        <button id="createben" type="button" class="btn btn-primary"
                            onclick="obtiene(['<?= $csrf->getTokenName() ?>','benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent'], ['benparent'], [], 'cahomben', '<?= $idCertificado ?>', ['<?= htmlspecialchars($secureID->encrypt($idCertificado)) ?>'],function(data) {printdiv2('#cuadro','<?= $idCertificado; ?>'); $('#databen').modal('hide');}); ">
                            <i class="fa fa-floppy-disk"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            onclick="printdiv2('#cuadro','<?= $idCertificado; ?>')">Cancelar</button>
                        <button id="updateben" style="display:none;" type="button" class="btn btn-primary"
                            onclick="obtiene(['<?= $csrf->getTokenName() ?>','benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent','idben'], ['benparent'], [], 'uahomben', '<?= $idCertificado; ?>', ['<?= htmlspecialchars($secureID->encrypt($idCertificado)) ?>'],function(data) {printdiv2('#cuadro','<?= $idCertificado; ?>'); $('#databen').modal('hide');});">
                            <i class="fa fa-floppy-disk"></i> Actualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        break;
    case 'liquidcrt':
        $idCertificado = $_POST["xtra"];
        $query = "SELECT short_name,cli.idcod_cliente,crt.*,tip.diascalculo,tip.inicioCalculo,tip.isr FROM ahomcrt crt 
                    INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                    INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                    WHERE id_crt=?;";

        $showmensaje = false;
        try {
            if ($idCertificado == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione un certificado a liquidar");
            }
            $database->openConnection();

            $dataAccount = $database->getAllResults($query, [$idCertificado]);
            if (empty($dataAccount)) {
                $showmensaje = true;
                throw new Exception("No se encontró el certificado");
            }

            $acreditaciones = $database->selectColumns(
                "ahommov",
                ['dfecope', 'ctipope', 'ctipdoc', 'monto'],
                'ccodaho=? AND cestado=1 AND dfecope IS NOT NULL AND dfecope BETWEEN ? AND ? AND ctipdoc IN ("IN","IP") AND ctipope IN ("D","R")',
                [$dataAccount[0]['codaho'], $dataAccount[0]['fec_apertura'], $dataAccount[0]['fec_ven']],
                'dfecope,correlativo'
            );

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
            $fec1anio = strtotime('+365 day', strtotime($hoy));
            $fec1anio = date('Y-m-j', $fec1anio);

            $fecfin = ($hoy <= $dataAccount[0]['fec_ven']) ? $hoy : $dataAccount[0]['fec_ven'];
            // $diasdif = dias_dif($fecapr, $fecfin);
            $diasdif = ($dataAccount[0]['diascalculo'] == 360) ? diferenciaEnDias($dataAccount[0]['fec_apertura'], $fecfin) : dias_dif($dataAccount[0]['fec_apertura'], $fecfin);
            $diasdif = $diasdif + $dataAccount[0]['inicioCalculo'];

            $intcal = $dataAccount[0]['montoapr'] * ($dataAccount[0]['interes'] / 100 / $dataAccount[0]['diascalculo']);
            $intcal = $intcal * $diasdif;
            $ipf = $intcal * ($dataAccount[0]['isr'] / 100);
            $total = $intcal - $ipf;

            $totaltodo = ($dataAccount[0]['montoapr'] + $intcal) - ($ipf);
        }
        ?>
        <input type="text" id="condi" value="certificados" hidden>
        <input type="text" id="file" value="aho_04" hidden>
        <input type="text" id="dayscalc" value="<?php echo ($bandera == "") ? $dayscalc : 365; ?>" hidden>
        <input type="text" id="inicioCalculo" value="<?php echo ($bandera == "") ? $inicioCalculo : 0; ?>" hidden>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa-solid fa-sack-dollar"></i> Acreditacion y liquidacion de certificados</h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <div class="accordion" id="accordionExample">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" aria-expanded="true" aria-controls="collapseOne">
                                IDENTIFICACION DEL CLIENTE
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-2">
                                        <span class="input-group-addon col-8">Certificado</span>
                                        <input type="text" aria-label="Certificado" id="certif" class="form-control  col"
                                            placeholder="" disabled value="<?= $dataAccount[0]['ccodcrt'] ?? ''; ?>">
                                    </div>
                                    <div class="col-sm-2"> </div>
                                    <div class="col-sm-4">
                                        <span class="input-group-addon col-8">ACCION</span>
                                        <select class="form-select" id="accion">
                                            <option value="1" selected>ACREDITAR Y LIQUIDAR</option>
                                            <option value="2">SOLO ACREDITAR</option>
                                            <option value="3">SOLO LIQUIDAR SIN ACREDITAR</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-sm-5">
                                        <div>
                                            <span class="input-group-addon col-8">Codigo de Cuenta</span>
                                            <input type="text" aria-label="Cuenta" id="codaho" class="form-control  col"
                                                placeholder="" value="<?= $dataAccount[0]['codaho'] ?? ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-6">
                                        <div>
                                            <span class="input-group-addon col-8">Cliente</span>
                                            <input type="text" class="form-control " id="nomcli" placeholder=""
                                                value="<?= $dataAccount[0]['short_name'] ?? ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div>
                                            <span class="input-group-addon col-8">Codigo de cliente</span>
                                            <input type="text" class="form-control " id="codcli" placeholder=""
                                                value="<?= $dataAccount[0]['idcod_cliente'] ?? ''; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button" type="button" aria-expanded="false" aria-controls="collapseTwo">
                                DATOS DE LA CUENTA
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Monto</span>
                                        <input type="float" class="form-control" id="monapr" placeholder="0.00" disabled
                                            value="<?= $dataAccount[0]['montoapr'] ?? ''; ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Plazo Establecido</span>
                                        <input type="number" class="form-control" id="plazoest" placeholder="365" disabled
                                            value="<?= $dataAccount[0]['plazo'] ?? ''; ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Dias de gracia</span>
                                        <input type="float" class="form-control" id="gracia" placeholder="0" disabled
                                            value="<?= $dataAccount[0]['dia_gra'] ?? ''; ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Interes %</span>
                                        <input type="float" class="form-control" id="tasint" placeholder="0.00" disabled
                                            value="<?= $dataAccount[0]['interes'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Fecha Apertura</span>
                                        <input type="date" class="form-control" id="fecaper" required="required" disabled
                                            value="<?= $dataAccount[0]['fec_apertura'] ?? ''; ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Fecha Vencimiento</span>
                                        <input type="date" class="form-control" id="fecv" required="required" disabled
                                            value="<?= $dataAccount[0]['fec_ven'] ?? ''; ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Fecha de Acreditacion</span>
                                        <input type="date" class="form-control" id="fecacredita" required="required"
                                            value="<?= ($hoy <= $dataAccount[0]['fec_ven']) ? $hoy : $dataAccount[0]['fec_ven']; ?>">
                                    </div>
                                    <div class="col-sm-3">
                                        <br>
                                        <button type="button" id="btnSave" class="btn btn-outline-primary"
                                            onclick="recalculoxfecha(<?= $dataAccount[0]['diascalculo']; ?>)">
                                            <i class="fa-solid fa-calculator"></i> Recalcular interes
                                        </button>
                                    </div>
                                </div>
                                <?php
                                if ($dataAccount[0]['calint'] == "V" && $hoy < $dataAccount[0]['fec_ven']) {
                                    ?>
                                    <div class="alert alert-danger" role="alert">
                                        Se esta acreditando antes de la fecha de vencimiento, ingrese el porcentaje de penalizacion
                                        ó cambie la fecha de acreditacion y presione el boton recalcular
                                    </div>
                                    <?php
                                } else {
                                    ?>
                                    <!-- <div class="alert alert-success" role="alert">
                                        Se esta acreditando despues de la fecha de vencimiento, proceda sin problemas
                                    </div> -->
                                    <?php
                                }
                                ?>
                                <div class="container">
                                    <h6>ACREDITAR</h6>
                                    <div class="row mb-3">
                                        <div class="col-sm-3">
                                            <span class="input-group-addon col-8">Plazo al dia de hoy</span>
                                            <input type="number" class="form-control" id="plazo" disabled
                                                value="<?= $diasdif; ?>">
                                        </div>
                                        <div class="col-sm-3">
                                            <span class="input-group-addon col-8">Penalizacion %</span>
                                            <input type="number" step="0.01" class="form-control" id="porc_pena"
                                                required="required" value="0" onkeyup="recalculo()">
                                        </div>
                                        <div class="col-sm-3">
                                            <span class="input-group-addon col-8">Monto Penalizacion</span>
                                            <input type="number" step="0.01" class="form-control" id="penaliza" value="0"
                                                onkeyup="interesc()">
                                        </div>
                                        <div class="col-sm-3" id="spinner" style="display:none;">
                                            <div class="spinner-grow text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-sm-3">
                                            <span class="input-group-addon col-8">Int. Calc.</span>
                                            <input type="number" step="0.01" class="form-control" id="moncal1"
                                                value="<?php echo round((float) $intcal, 2); ?>" onkeyup="equi()">
                                        </div>
                                        <div class="col-sm-3">
                                            <span class="input-group-addon col-8">Int. acreditar</span>
                                            <input type="number" step="0.01" class="form-control" readonly id="moncal"
                                                value="<?php echo round((float) $intcal, 2); ?>" onkeyup="ipfc()">
                                        </div>
                                        <div class="col-sm-3">
                                            <span class="input-group-addon col-8">IPF</span>
                                            <input type="number" step="0.01" class="form-control" id="intcal" readonly
                                                value="<?php echo round((float) $ipf, 2); ?>">
                                        </div>
                                        <div class="col-sm-3">
                                            <span class="input-group-addon col-8">Interes a pagar</span>
                                            <input type="number" step="0.01" class="form-control" id="totcal" readonly
                                                value="<?php echo round((float) $total, 2); ?>">
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button" type="button">
                                DATOS ADICIONALES
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse show" aria-labelledby="headingThree"
                            data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">No Recibo</span>
                                        <input type="text" class="form-control" id="norecibo">
                                    </div>
                                </div>
                                <?php echo $csrf->getTokenField(); ?>
                                <br>
                                <div class="row justify-items-md-center">
                                    <div class="col align-items-center" id="modal_footer">
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="obtiene(['<?= $csrf->getTokenName() ?>',`moncal`,`intcal`,`penaliza`,`norecibo`,`fecacredita`],[`accion`],[],`liquidcrt`,`0`,['<?= htmlspecialchars($secureID->encrypt($idCertificado)) ?>'])">
                                            <i class="fa fa-floppy-disk"></i> Liquidar
                                        </button>
                                        <button type="button" class="btn btn-outline-danger"
                                            onclick="printdiv('certificados', '#cuadro', 'aho_04', '0')">
                                            <i class="fa-solid fa-ban"></i> Cancelar
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                            <i class="fa-solid fa-circle-xmark"></i> Salir
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="radio" class="form-control " id=" " name="nada" checked style="display: none;">
                <div class="container contenedort mt-3">
                    <h5 class="mb-3">Historial de movimientos</h5>
                    <?php if (!empty($acreditaciones)) { ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Operación</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($acreditaciones as $key => $acreditacion) { ?>
                                    <tr>
                                        <td><?= $key + 1 ?></td>
                                        <td><?= setdatefrench($acreditacion['dfecope']) ?></td>
                                        <td>
                                            <?= ($acreditacion['ctipope'] === 'D') ? 'Depósito' : (($acreditacion['ctipope'] === 'R') ? 'Retiro' : $acreditacion['ctipope']) ?>
                                        </td>
                                        <td><?= TipoDocumentoTransaccion::getDescripcion($acreditacion['ctipdoc'], 1); ?></td>
                                        <td><?= $acreditacion['monto'] ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <p>No hay historial para mostrar.</p>
                    <?php } ?>
                </div>
            </div>
        </div>
        <script>
            porcentajeIsr = <?= $dataAccount[0]['isr'] ?>;

            function interesc() {
                var monpenaliza = $('#penaliza').val();
                monpenaliza = (isNaN(monpenaliza)) ? 0 : monpenaliza;
                var interes = $('#moncal1').val();
                interes = (!isNaN(interes)) ? interes - monpenaliza : 0;
                $("#moncal").val(parseFloat(interes.toFixed(2)));
                ipfc();
            }

            function equi() {
                var monto1 = $('#moncal1').val();
                $("#moncal").val(monto1);
                ipfc();
            }

            function ipfc() {
                var mon = $('#moncal').val();
                mon = (!isNaN(mon)) ? mon : 0;
                ipf = (mon * (porcentajeIsr / 100));
                totcal = mon - ipf;
                $("#intcal").val(parseFloat(ipf.toFixed(2)));
                $("#totcal").val(parseFloat(totcal.toFixed(2)));
            }

            function recalculo() {
                var mon = $('#moncal1').val();
                mon = (!isNaN(mon)) ? mon : 0;
                var porcpena = $('#porc_pena').val();
                porcpena = (!isNaN(porcpena)) ? parseFloat(porcpena) : 0;
                var penaliza = mon * (porcpena / 100);
                document.getElementById("penaliza").value = parseFloat(penaliza.toFixed(2));
                interesc();
            }

            function recalculoxfecha(dayscalc = 360) {
                document.getElementById("spinner").style.display = "block";
                var fecapr = ($('#fecaper').val());
                var fecven = ($("#fecacredita").val())
                var plazo = document.getElementById("plazo").value
                var mon = document.getElementById("monapr").value
                var int = document.getElementById("tasint").value
                var days = document.getElementById("dayscalc").value
                var inicioCalculo = document.getElementById("inicioCalculo").value
                let account = document.getElementById("codaho").value;
                condi = "calfec";
                cond = 3;
                $.ajax({
                    url: "../src/cruds/crud_ahorro.php",
                    method: "POST",
                    data: {
                        condi,
                        fecapr,
                        plazo,
                        mon,
                        int,
                        cond,
                        fecven,
                        account
                    },
                    success: function (data) {
                        document.getElementById("spinner").style.display = "none";
                        const data2 = JSON.parse(data);
                        if (data2.status == '1' && cond == 3) {
                            // document.getElementById("moncal").value = data2.montos[0]
                            // document.getElementById("intcal").value = data2.montos[1]
                            // document.getElementById("totcal").value = data2.montos[2]

                            // document.getElementById("fecven").value = data2.fecha[0]


                            //++++++
                            document.getElementById("plazo").value = data2.plazo[0]

                            var monapr = parseFloat($('#monapr').val()).toFixed(2);
                            var tasa = parseFloat($('#tasint').val()).toFixed(2) / 100;
                            var interes = monapr * (tasa / dayscalc) * data2.plazo[0];

                            document.getElementById("moncal").value = parseFloat(interes.toFixed(2));
                            document.getElementById("moncal1").value = parseFloat(interes.toFixed(2));

                            interesc();
                        } else {
                            var toastLive = document.getElementById('toastalert')
                            document.getElementById("body_text").innerHTML = data2[1]
                            var toast = new bootstrap.Toast(toastLive)
                            toast.show()
                        }
                    }
                })
            }
        </script>
        <?php
        break;
    case 'reportcrt':
        ?>
        <input type="text" id="file" value="aho_04" style="display: none;">
        <input type="text" id="condi" value="reportcrt" style="display: none;">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fa-solid fa-chart-gantt"></i> REPORTE DE CERTIFICADOS
                </h4>
            </div>
            <div class="card-header"></div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-2">
                        <div class="card" style="height: 100%;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fas fa-filter me-2"></i> Estado</h6>
                            </div>
                            <div class="card-body">
                                <div class="row m-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="r1" id="all" checked value="all">
                                        <label class="form-check-label" for="all">Todos</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="r1" id="N" value="N">
                                        <label class="form-check-label" for="N">Vigentes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="r1" id="S" value="S">
                                        <label class="form-check-label" for="S">Liquidados</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-calendar-day"></i> Filtro por fecha de Apertura</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="r3" id="ftodo2" value="ftodo2"
                                                checked onclick="habdeshab([],['finicioAper','ffinAper'])">
                                            <label class="form-check-label" for="ftodo2">Todos</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="r3" id="frango2" value="frango2"
                                                onclick="habdeshab(['finicioAper','ffinAper'],[])">
                                            <label class="form-check-label" for="frango2">Rango</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-sm-6">
                                        <label for="finicioAper">Desde</label>
                                        <input type="date" class="form-control" id="finicioAper" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>" disabled>
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffinAper">Hasta</label>
                                        <input type="date" class="form-control" id="ffinAper" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-calendar-day"></i> Filtro por fecha de vencimiento</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="r2" id="ftodo" value="ftodo"
                                                checked onclick="habdeshab([],['finicio','ffin'])">
                                            <label class="form-check-label" for="ftodo">Todos</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="r2" id="frango" value="frango"
                                                onclick="habdeshab(['finicio','ffin'],[])">
                                            <label class="form-check-label" for="frango">Rango</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>" disabled>
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnSave" class="btn btn-outline-danger"
                            onclick="reportes([[`finicio`,`ffin`,`finicioAper`,`ffinAper`],[],[`r1`,`r2`,`r3`],[]], 'pdf', 'certificados_plazo_fijo',0)">
                            <i class="fa-solid fa-file-pdf"></i> Generar Pdf
                        </button>
                        <button type="button" id="btnSave" class="btn btn-outline-success"
                            onclick="reportes([[`finicio`,`ffin`,`finicioAper`,`ffinAper`],[],[`r1`,`r2`,`r3`],[]], 'xlsx', 'certificados_plazo_fijo',1)">
                            <i class="fa-solid fa-file-excel"></i> Generar Excel
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
    case 'addTestigo':

        $id = $_POST["xtra"];
        $id_certificado = $id; // Definimos el id del certificado basado en lo recibido en POST
        $datos = [
            "id_tipo" => "",
        ];
        $query = "SELECT short_name, ccodcrt, crt.codaho, cta.ccodcli, no_tributaria num_nit 
                  FROM ahomcrt crt 
                  INNER JOIN ahomcta cta ON cta.ccodaho = crt.codaho
                  INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                  WHERE id_crt = $id;";
        $datoscrt = mysqli_query($conexion, $query);
        $bandera = "Codigo de certificado no existe";
        while ($row = mysqli_fetch_array($datoscrt, MYSQLI_ASSOC)) {
            $codcrt = ($row["ccodcrt"]);
            $idcli = ($row["ccodcli"]);
            $nit = ($row["num_nit"]);
            $codaho = ($row["codaho"]);
            $nombre = encode_utf8($row['short_name']);
            $bandera = "";
        }
        ?>
        <!--Aho_0_BeneAho Inicio de Ahorro Sección 0 Beneficiario de Ahorro-->
        <div class="card">
            <input type="text" id="file" value="aho_04" style="display: none;">
            <input type="text" id="condi" value="addTestigo" style="display: none;">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fa-solid fa-user-check"></i> Testigos del Certificado</h4>
            </div>
            <div class="card-body">
                <!--Aho_0_BeneAho Cta.Ahorros-->
                <div class="row contenedort">
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <span class="input-group-addon col-8">Numero de certificado</span>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control " id="ccodcrt" required placeholder="000-000-00-000000"
                                    value="<?php if ($bandera == "")
                                        echo $codcrt; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <span class="input-group-addon col-8">Cuenta de Ahorros</span>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control " id="ccodaho" required placeholder="000-000-00-000000"
                                    value="<?php if ($bandera == "")
                                        echo $codaho; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <span class="input-group-addon col-8">Nombre</span>
                            <input type="text" class="form-control " id="name" value="<?php if ($bandera == "")
                                echo $nombre; ?>" readonly>
                        </div>
                    </div>
                    <input type="hidden" id="id_certificado" value="<?php echo $id; ?>">
                    <?php if ($bandera != "" && $id != "0") {
                        echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                    }
                    ?>
                </div>

                <!--Aho_0_BeneAho Tabla de Datos-->
                <div class="row contenedort">
                    <div class="row">
                        <table id="table_id2" class="table">
                            <thead>
                                <tr>
                                    <th style="display:none;">ID</th>
                                    <th>DPI</th>
                                    <th>Nombre Completo</th>
                                    <th>Teléfono</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="categoria_tb">
                                <?php
                                if ($bandera == "") {
                                    // Usamos el id_certificado para filtrarlos
                                    $querytestigos = mysqli_query($conexion, "SELECT * FROM `ahotestigos` WHERE `id_certificado`='$id_certificado'");
                                    while ($rowq = mysqli_fetch_array($querytestigos, MYSQLI_ASSOC)) {
                                        $idtestigo = ($rowq["id"]);
                                        $testigo_nombre = mb_convert_encoding($rowq["nombre"], 'UTF-8', 'ISO-8859-1');
                                        $testigo_dpi = ($rowq["dpi"]);
                                        $testigo_direccion = mb_convert_encoding($rowq["direccion"], 'UTF-8', 'ISO-8859-1');
                                        $testigo_telefono = ($rowq["telefono"]);
                                        echo '<tr data-testigo-id="' . $idtestigo . '">
                                            <td style="display:none;">' . $idtestigo . '</td>
                                            <td>' . $testigo_dpi . ' </td>
                                            <td>' . $testigo_nombre . ' </td>
                                            <td>' . $testigo_telefono . '</td>
                                            <td>
                                                <button type="button" class="btn btn-warning" title="Editar Testigo" onclick="edittestigo(' . $idtestigo . ',`' . $testigo_nombre . '`,`' . $testigo_dpi . '`,`' . $testigo_direccion . '`,`' . $testigo_telefono . '`)">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger" title="Eliminar Testigo" onclick="eliminarTestigoFromRow(this, ' . $id_certificado . ')">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--Aho_0_BeneAho Botones Guardar, Editar, Eliminar, Guardar-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnnew" class="btn btn-outline-success" data-bs-toggle="modal"
                            data-bs-target="#databen">
                            <i class="fa fa-file"></i> Nuevo
                        </button>
                        <button type="button" class="btn btn-outline-danger"
                            onclick="printdiv('certificados', '#cuadro', 'aho_04', '0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="databen" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog  modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="staticBackdropLabel">Datos de Testigo</h1>
                    </div>
                    <div class="modal-body">
                        <div class="row contenedort">
                            <!--Datos del Testigo-->
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <span class="input-group-addon">Nombre</span>
                                    <input type="text" aria-label="Nombre Testigo" id="testigo_nombre" class="form-control col"
                                        placeholder="" required>
                                </div>
                                <div class="col-md-4">
                                    <span class="input-group-addon">DPI</span>
                                    <input type="text" aria-label="DPI" id="testigo_dpi" class="form-control col"
                                        placeholder="">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <span class="input-group-addon">Dirección</span>
                                    <input type="text" aria-label="Dirección" id="testigo_direccion" class="form-control col"
                                        placeholder="">
                                </div>
                                <div class="col-md-4">
                                    <span class="input-group-addon">Teléfono</span>
                                    <input type="text" aria-label="Teléfono" id="testigo_telefono" class="form-control col"
                                        placeholder="">
                                </div>
                                <div style="display:none;" class="col-md-2">
                                    <input type="number" class="form-control col-10" id="idtestigo">
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="radio" name="nada" id="0" checked style="display: none;">
                    <div class="modal-footer">
                        <button id="createtestigo" type="button" class="btn btn-primary" data-bs-dismiss="modal"
                            onclick="obtiene(['testigo_nombre', 'testigo_dpi', 'testigo_direccion', 'testigo_telefono', 'id_certificado'], [], ['nada'], 'cahotestigo', '<?php echo $id; ?>', ['<?php echo $codaho; ?>','<?php echo $bandera; ?>']); printdiv2('#cuadro','<?php echo $id; ?>');">
                            <i class="fa fa-floppy-disk"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            onclick="printdiv2('#cuadro','<?php echo $id; ?>')">Cancelar</button>
                        <button id="updatetestigo" style="display:none;" type="button" class="btn btn-primary"
                            data-bs-dismiss="modal"
                            onclick="obtiene(['testigo_nombre', 'testigo_dpi', 'testigo_direccion', 'testigo_telefono', 'idtestigo'], [], ['nada'], 'uahotestigo', '<?php echo $id; ?>', ['<?php echo $id; ?>'])">
                            <i class="fa fa-floppy-disk"></i> Actualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // Asegúrate de que el campo oculto ya esté renderizado
            const idCert = document.getElementById("id_certificado").value;
            console.log("ID del certificado:", idCert);

            function edittestigo(id, nombre, dpi, direccion, telefono) {
                document.getElementById("testigo_nombre").value = nombre;
                document.getElementById("testigo_dpi").value = dpi;
                document.getElementById("testigo_direccion").value = direccion;
                document.getElementById("testigo_telefono").value = telefono;
                document.getElementById("idtestigo").value = id;

                document.getElementById("createtestigo").style.display = "none";
                document.getElementById("updatetestigo").style.display = "block";

                $('#databen').modal('show');
            }

            function printdiv2(idiv, xtra) {
                loaderefect(1);
                condi = document.getElementById("condi").value;
                dire = "aho/aho_04.php";
                $.ajax({
                    url: dire,
                    method: "POST",
                    data: {
                        condi,
                        xtra
                    },
                    success: function (data) {
                        loaderefect(0);
                        $(idiv).html(data);
                    },
                    error: function (xhr) {
                        loaderefect(0);
                        console.log(xhr);
                    }
                });
            }
            // Add this function to your script section
            function eliminarTestigoFromRow(button, idCertificado) {
                // Get the parent row
                const row = button.closest('tr');
                // Get the testigo ID from the data attribute
                const idTestigo = row.getAttribute('data-testigo-id');

                // Call the existing delete function
                eliminarTestigo(idTestigo, idCertificado);
            }

            // Function to handle testigo deletion with confirmation
            function eliminarTestigo(idTestigo, idCertificado) {
                Swal.fire({
                    title: '¿Está seguro?',
                    text: "Esta acción eliminará el testigo permanentemente",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        loaderefect(1);
                        $.ajax({
                            url: '../src/cruds/crud_ahorro.php',
                            method: 'POST',
                            data: {
                                condi: 'dahotestigo', // Changed from accion to condi
                                xtra: idTestigo
                            },
                            success: function (response) {
                                loaderefect(0);
                                try {
                                    const data = JSON.parse(response);
                                    if (data[1] === 1) {
                                        Swal.fire(
                                            '¡Eliminado!',
                                            data[0],
                                            'success'
                                        );
                                        // Refresh the testigos list
                                        printdiv2('#cuadro', idCertificado);
                                    } else {
                                        Swal.fire(
                                            'Error',
                                            data[0],
                                            'error'
                                        );
                                    }
                                } catch (e) {
                                    Swal.fire(
                                        'Error',
                                        'Ocurrió un error al procesar la respuesta: ' + response,
                                        'error'
                                    );
                                    console.error(e, response);
                                }
                            },
                            error: function (xhr) {
                                loaderefect(0);
                                Swal.fire(
                                    'Error',
                                    'Error de conexión: ' + xhr.statusText,
                                    'error'
                                );
                                console.log(xhr);
                            }
                        });
                    }
                });
            }
        </script>

        <?php
        break;
} //FINAL DEL SWITCH
?>