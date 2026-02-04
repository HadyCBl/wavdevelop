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
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

use Micro\Models\TipoPoliza;
use Micro\Helpers\Log;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
//+++++++++
// session_start();
// include '../../includes/BD_con/db_con.php';
// mysqli_set_charset($conexion, 'utf8');
// include '../../src/funcphp/func_gen.php';
// date_default_timezone_set('America/Guatemala');
// $idusuario = $_SESSION['id'];
$condi = $_POST["condi"];
switch ($condi) {
    case 'partidas':

        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (27) AND id_usuario=? AND estado=1", [$idusuario]);
            $permisoVisualizacion = new PermissionHandler($permisos, 27);

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

        $condiWhere = ($permisoVisualizacion->isLow()) ? "id_agencia=" . $idagencia : "1=1";

?>
        <input type="text" id="condi" value="partidas" hidden>
        <input type="text" id="file" value="ctb001" hidden>
        <div class="text" style="text-align:center">PARTIDAS DE DIARIO</div>
        <div class="card" style="width: 100%;">
            <style>
                .btn-donate {
                    --clr-font-main: hsla(0 0% 20% / 100);
                    --btn-bg-1: hsla(194 100% 69% / 1);
                    --btn-bg-2: hsla(217 100% 56% / 1);
                    --btn-bg-color: hsla(360 100% 100% / 1);
                    --radii: 0.5em;
                    cursor: pointer;
                    padding: 0.9em 1.4em;
                    min-width: 120px;
                    min-height: 44px;
                    font-size: var(--size, 1rem);
                    font-family: "Segoe UI", system-ui, sans-serif;
                    font-weight: 500;
                    transition: 0.8s;
                    background-size: 280% auto;
                    background-image: linear-gradient(325deg, var(--btn-bg-2) 0%, var(--btn-bg-1) 55%, var(--btn-bg-2) 90%);
                    border: none;
                    border-radius: var(--radii);
                    color: var(--btn-bg-color);
                    box-shadow: 0px 0px 20px rgba(71, 184, 255, 0.5), 0px 5px 5px -1px rgba(58, 125, 233, 0.25), inset 4px 4px 8px rgba(175, 230, 255, 0.5), inset -4px -4px 8px rgba(19, 95, 216, 0.35);
                }

                .btn-donate:hover {
                    background-position: right top;
                }

                .btn-donate:is(:focus, :focus-visible, :active) {
                    outline: none;
                    box-shadow: 0 0 0 3px var(--btn-bg-color), 0 0 0 6px var(--btn-bg-2);
                }

                .btn-donate i {
                    margin-right: 8px;
                }

                .my-32 {
                    margin-top: 32px;
                    margin-bottom: 32px;
                }


                @media (prefers-reduced-motion: reduce) {
                    .btn-donate {
                        transition: linear;
                    }
                }
            </style>


            <div class="card-header bg-primary bg-gradient">Partidas de Diario</div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <div class="d-flex justify-content-center my-32">
                    <button type="button" class="btn btn-donate" data-bs-toggle="modal" data-bs-target="#modalPartidas">
                        <i class="fas fa-list"></i> Ver Partidas
                    </button>
                </div>

                <div class="modal fade" id="modalPartidas" tabindex="-1" aria-labelledby="modalPartidasLabel"
                    aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalPartidasLabel">Partidas</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="list-example" class="h-100 flex-column align-items-stretch pe-1 border-end">
                                    <table class="table nowrap" id="tb_partidas" style="width: 100% !important; font-size: 0.70rem;">
                                        <thead>
                                            <tr style="font-size: 0.70rem;">
                                                <th>Agencia</th>
                                                <th>Poliza</th>
                                                <th>Fecha</th>
                                                <th>Concepto</th>
                                                <th>Monto</th>
                                                <th>Acc.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Los datos de la tabla tendrán el mismo font-size reducido -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CONTENEDOR QUE SE VA A REIMPRIMIR -->
                <div id="contenedor_section" class="col-12" style="padding-left: 0px !important; padding-right: 7px !important;">

                </div>


                <script>
                    var table_partidas_aux;
                    $(document).ready(function() {
                        printdiv3('section_partidas_conta', '#contenedor_section', '0');
                        table_partidas_aux = $('#tb_partidas').on('search.dt').DataTable({
                            "processing": true,
                            "serverSide": true,
                            "sAjaxSource": "../src/server_side/lista_partidas_conta.php",
                            columns: [{
                                    data: [1]
                                },
                                {
                                    data: [2]
                                },
                                {
                                    data: [3]
                                },
                                {
                                    data: [4],
                                    render: function(data, type, row) {
                                        if (typeof data === 'string' && data.length > 30) {
                                            return data.substring(0, 30) + '...';
                                        }
                                        return data;
                                    }
                                },
                                {
                                    data: [5]
                                },
                                {
                                    data: [0],
                                    render: function(data, type, row) {
                                        // console.log(data);
                                        return `<button type="button" class="btn btn-outline-success btn-sm" onclick="printdiv3('section_partidas_conta', '#contenedor_section','${data}')" >
                                                    <i class="fa-solid fa-paper-plane"></i>
                                                </button>`;
                                    }
                                },
                            ],
                            "fnServerParams": function(aoData) {
                                //PARAMETROS EXTRAS QUE SE LE PUEDEN ENVIAR AL SERVER ASIDE
                                aoData.push({
                                    "name": "whereextra",
                                    "value": '<?= $condiWhere ?>'
                                });
                            },

                            //"bDestroy": true,
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
                    });
                </script>
            </div>


        <?php
        break;

    case 'section_partidas_conta':
        $xtra = $_POST["xtra"];

        /**
         * CONSULTAS EN LA BD PRINCIPAL
         */
        $query = "SELECT dia.id, dia.id_ctb_tipopoliza, mov.id_fuente_fondo, dia.numcom, mov.id_ctb_nomenclatura, cue.ccodcta, cue.cdescrip, 
                        mov.debe, mov.haber, dia.glosa, dia.feccnt, dia.fecdoc, dia.id_tb_usu, dia.numdoc,dia.editable, dia.id_agencia
                    FROM ctb_mov AS mov 
                    INNER JOIN ctb_diario AS dia ON mov.id_ctb_diario = dia.id 
                    INNER JOIN ctb_nomenclatura AS cue ON cue.id = mov.id_ctb_nomenclatura WHERE dia.id=? and dia.estado=1";

        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (28,29) AND id_usuario=? AND estado=1", [$idusuario]);
            $permisoEdicion = new PermissionHandler($permisos, 28, 29);

            $fondoselect = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondoselect)) {
                $showmensaje = true;
                throw new Exception("No se encontraron fondos disponibles");
            }

            $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "cod_agenc", "nom_agencia"]);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias disponibles");
            }

            $ctbmovdata = $database->getAllResults($query, [$xtra]);

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

        /**
         * CONSULTAS EN LA GENERAL
         */
        $showmensaje = false;
        try {
            $database->openConnection(2);

            $filtert = ($xtra == 0) ? 'id IN (6,9,13)' : '';

            $tiposPolizas = $database->selectColumns("ctb_tipo_poliza", ["id", "descripcion"], $filtert);

            $status2 = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje2 = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status2 = false;
        } finally {
            $database->closeConnection();
        }

        // $condiWhere = ($permisoEdicion->isLow()) ? "id_agencia=" . $idagencia : "";


        $disabled = ($xtra == 0) ? '' : ' disabled ';

        ?>
            <div id="ladopoliza">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <?php if (!$status2) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje2; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="scrollspy-example-2" tabindex="0" style="width: 100%;">
                    <div class="contenedort container ">
                        <?php if ($xtra != 0 && isset($ctbmovdata[0]['numcom'])): ?>
                            <div class="row mb-2">
                                <div class="col">
                                    <span class="badge bg-success fs-6">
                                        <i class="fa fa-file-invoice"></i>
                                        Póliza No. <?= htmlspecialchars($ctbmovdata[0]['numcom']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-2">
                            <div class="col-sm-4 mt-3 mb-3">
                                <div class="form-floating">
                                    <input <?= $disabled; ?> type="date" class="form-control" id="datedoc"
                                        value="<?= ($xtra == 0) ? $hoy : $ctbmovdata[0]["fecdoc"]; ?>">
                                    <label class="text-primary" for="datedoc">Fecha Documento</label>
                                </div>
                            </div>
                            <div class="col-sm-4 mt-3 mb-3">
                                <div class="form-floating">
                                    <input <?= $disabled; ?> type="date" class="form-control" id="datecont"
                                        value="<?= ($xtra == 0) ? $hoy : $ctbmovdata[0]['feccnt']; ?>">
                                    <label class="text-primary" for="datecont">Fecha Contable</label>
                                </div>
                            </div>
                            <div class="col-sm-4 mt-3 mb-3">
                                <div class="form-floating">
                                    <select <?= $disabled; ?> class="form-select" id="codofi">
                                        <?php
                                        $userAgencia = ($xtra == 0) ? $idagencia : $ctbmovdata[0]['id_agencia'];
                                        foreach ($agencias as $ofi) {
                                            $selec = ($ofi['id_agencia'] == $userAgencia) ? 'selected' : '';
                                            echo '<option ' . $selec . ' value="' . $ofi['id_agencia'] . '">' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label class="text-primary" for="codofi">Agencia</label>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-5">
                                <div class="form-floating mb-3">
                                    <input <?= $disabled; ?> type="text" class="form-control" id="numdoc"
                                        value="<?= ($xtra == 0) ? ' ' : $ctbmovdata[0]['numdoc']; ?>">
                                    <label class="text-primary" for="numdoc">Documento</label>
                                </div>
                            </div>
                            <div class="col-sm-7">
                                <div class="form-floating mb-2">
                                    <select <?= $disabled; ?> class="form-select" id="idtipo_poliza">
                                        <?php
                                        foreach ($tiposPolizas as $tipo) {
                                            $selec = ($tipo['id'] == $ctbmovdata[0]['id_ctb_tipopoliza']) ? 'selected' : '';
                                            echo '<option ' . $selec . ' value="' . $tipo['id'] . '">' . $tipo['descripcion'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label class="text-primary" for="fondoid">Tipo de Poliza</label>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-sm-12 mb-2">
                                <div class="form-floating">
                                    <textarea <?= $disabled; ?> class="form-control" id="glosa" style="height: 100px"
                                        rows="1"><?= ($xtra == 0) ? '' : ($ctbmovdata[0]["glosa"]); ?></textarea>
                                    <label for="glosa">Glosa</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="container contenedort">
                        <table id="Cuentas" class="display mb-2" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width:1%;">No.</th>


                                    <th style="width:0.01%;"></th>
                                    <th style="width:17%;">Nombre</th>
                                    <th>Cuenta</th>
                                    <th style="width:22%;">Debe</th>
                                    <th style="width:22%;">Haber</th>
                                    <th style="width:20%;">Fondos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($xtra != 0) {
                                    $it = 0;
                                    $debe = 0;
                                    $haber = 0;
                                    while ($it < count($ctbmovdata)) {
                                        $incuentas = '<div class="input-group" title="' . $ctbmovdata[$it]["cdescrip"] . '"><input style="display:none;" type="text" class="form-control" id="idcuenta' . ($it + 1) . '" value="' . $ctbmovdata[$it]["id_ctb_nomenclatura"] . '"><input type="text" disabled style="font-size: 0.9rem;" readonly class="form-control" id="cuenta'  . ($it + 1) .  '" value="' . $ctbmovdata[$it]["ccodcta"] . '"><button disabled class="btn btn-outline-success" type="button" onclick="abrir_modal(`#modal_nomenclatura`, `show`, `#id_modal_hidden`, `idcuenta'  . ($it + 1) . ',cuenta'  . ($it + 1) .  '`)" title="Buscar Cuenta contable"><i class="fa fa-magnifying-glass"></i></button></div>';
                                        $ind = '<div class="input-group"><span class="input-group-text">Q</span><input disabled style="text-align: right; font-size: 0.9rem;" type="number" step="0.01" class="form-control" id="debe' . ($it + 1) . '" onblur="validadh(this.id,this.value)" value="' . $ctbmovdata[$it]["debe"] . '"></div>';
                                        $inh = '<div class="input-group"><span class="input-group-text">Q</span><input disabled style="text-align: right; font-size: 0.9rem;" type="number" step="0.01" class="form-control" id="habe' . ($it + 1) . '" onblur="validadh(this.id,this.value)" value="' . $ctbmovdata[$it]["haber"] . '"></div>';
                                        $debe = $debe + $ctbmovdata[$it]["debe"];
                                        $name = $ctbmovdata[$it]["cdescrip"];
                                        $haber = $haber + $ctbmovdata[$it]["haber"];
                                        echo '<tr style="font-size: 0.9rem;">';
                                        $selectfondo = '<select class="form-select" disabled id="fondoid' . ($it + 1) . '">';
                                        $k = 0;
                                        while ($k < count($fondoselect)) {
                                            $selec = ($fondoselect[$k]['id'] == $ctbmovdata[$it]['id_fuente_fondo']) ? 'selected' : '';
                                            $selectfondo .= '<option ' . $selec . ' value="' . $fondoselect[$k]['id'] . '">' . $fondoselect[$k]['descripcion'] . '</option>';
                                            $k++;
                                        }
                                        $selectfondo .= '</select>';
                                        echo '<td class="ps-0">' . ($it + 1) . '</td>';
                                        echo '<td class="ps-0">' . ($it + 1) . '</td>';
                                        echo '<td class="ps-0">' . $name . '</td>';
                                        echo '<td class="ps-0">' . $incuentas . '</td>';
                                        echo '<td class="ps-0">' . $ind . '</td>';
                                        echo '<td class="ps-0">' . $inh . '</td>';
                                        echo '<td class="ps-0">' . $selectfondo . '</td>';
                                        echo '</tr>';
                                        $it++;
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                        <div class="col-10">
                            <div class="row">
                                <div class="col-sm-4 mb-2">
                                    <button <?= $disabled; ?> id="addRow" class="btn btn-outline-primary"
                                        title="Añadir nueva fila" onclick="newrow3()">
                                        <i class="fa-solid fa-plus fa-fade fa-sm"></i>
                                    </button>
                                    <button <?= $disabled; ?> id="deletefila" class="btn btn-outline-danger"
                                        title="Eliminar fila" onclick="deletefila()">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>

                                <div class="col-sm-4 mb-3 ps-0">
                                    <div class="input-group" style="width: 70%;float: right;">
                                        <span class="input-group-text">Q</span>
                                        <input id="totdebe" style="text-align: right;" type="number" step="0.01"
                                            class="form-control" readonly value="<?= ($xtra == 0) ? '' : $debe; ?>">
                                    </div>
                                </div>

                                <div class="col-sm-4 mb-2 ps-0">
                                    <div class="input-group" style="width: 70%;float: left;">
                                        <span class="input-group-text">Q</span>
                                        <input id="tothaber" style="text-align: right;" type="number" step="0.01"
                                            class="form-control" readonly value="<?= ($xtra == 0) ? '' : $haber; ?>">
                                    </div>
                                </div>
                            </div>



                        </div>

                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="btns_footer">
                            <br>
                            <?php
                            if ($xtra == 0) {
                                echo '<button type="button" class="btn btn-outline-success" onclick="savecom(' . $idusuario . ',`cpoliza`,0)">
                                    <i class="fa fa-floppy-disk"></i> Guardar
                                </button>';
                            } else {
                                if ($ctbmovdata[0]["editable"] == 1 || $permisoEdicion->isHigh() || ($permisoEdicion->isMedium() && $ctbmovdata[0]['id_agencia'] == $idagencia)) {
                                    echo '<button id="modpol" type="button" title="Modificar datos de la Poliza" class="btn btn-outline-primary" onclick="changedisabled(`#ladopoliza *`,1); changedisabled(`#btns_footer .btn-outline-primary`,0); changedisabled(`#deletepol`,0); changedisabled(`#idtipo_poliza`,0);">
                                            <i class="fa fa-pen"></i> Modificar</button>';
                                    echo '<button disabled type="button" title="Guardar cambios modificados de la poliza" class="btn btn-outline-success" onclick="savecom(' . $idusuario . ',`upoliza`,' . $xtra . ')">
                                        <i class="fa fa-floppy-disk"></i> Actualizar</button>';
                                }

                                echo '<button id="printpol" type="button" title="Imprimir datos de la Poliza" class="btn btn-outline-primary" onclick="reportes([[],[],[],[' . $xtra . ']], `pdf`, `partida_diario`,0)">
                                        <i class="fa fa-print"></i> Imprimir</button>';

                                if ($ctbmovdata[0]["editable"] == 1 || $permisoEdicion->isHigh() || ($permisoEdicion->isMedium() && $ctbmovdata[0]['id_agencia'] == $idagencia)) {
                                    echo '<button id="deletepol" type="button" title="Eliminar Poliza seleccionada" class="btn btn-outline-danger" onclick="eliminar(' . $xtra . ', `crud_ctb`, 0, `dpoliza`)">
                                            <i class="fa fa-trash"></i>Eliminar</button>';
                                }
                            }
                            ?>
                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0');reinicio(0)">
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
                $(document).ready(function() {
                    var t = $('#Cuentas').dataTable({
                        "searching": false,
                        "paging": false,
                        "ordering": false,
                        "info": false,
                        "language": {
                            "zeroRecords": " ",
                        },
                    }).DataTable();
                    $('#Cuentas tbody').on('click', 'tr', function() {
                        if ($(this).hasClass('selected')) {
                            $(this).removeClass('selected');
                        } else {
                            $('.selected').removeClass('selected');
                            $(this).addClass('selected');
                        }
                    });
                    reinicio(0);
                    var column = t.column(1);
                    column.visible(false);
                    <?php
                    if ($xtra != 0) {
                        echo 'reinicio(' . count($ctbmovdata) . ');';
                    }
                    ?>
                });

                function newrow3() {
                    newrow2(<?php echo json_encode($fondoselect); ?>);
                }
            </script>
        <?php
        break;
    //Configuracion de Partidas de Diario
    case 'Config_Partidas':
        $showmensaje = false;
        try {
            $database->openConnection();

            $diarioConfig = $database->selectColumns(
                "ctb_diario_config",
                ["id", "id_tipo_poliza", "titulo", "descripcion"],
                "estado=1",
            );
            if (empty($diarioConfig)) {
                $showwmensaje = true;
                throw new Exception("No se encontraron partidas preconfiguradas");
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
            <!-- Inputs ocultos para condiciones -->
            <input type="text" id="condi" value="Config_Partidas" hidden>
            <input type="text" id="file" value="ctb001" hidden>

            <div class="text" style="text-align:center">CONFIGURAR PARTIDA</div>
            <div class="card mb-2">
                <div class="card-header">Configuración de Partida semiautomatica</div>
                <div class="card-body ">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <!-- <button type="button" class="col-12 btn btn-primary " data-bs-toggle="modal" data-bs-target="#modalPartidasPreconfiguradas">
                                <i class="fas fa-list"></i> Buscar y ver partidas
                            </button> -->
                        </div>
                    </div>
                    <!-- Aqui generar el html del modal -->
                    <div class="modal fade" id="modalPartidasPreconfiguradas" tabindex="-1" aria-labelledby="modalPartidasPreconfiguradasLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalPartidasPreconfiguradasLabel">Partidas preconfiguradas</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="list-example" class="h-100 flex-column align-items-stretch pe-1 border-end">
                                        <table class="table nowrap" id="tb_partidas_preconfiguradas" style="width: 100% !important; font-size: 0.70rem;">
                                            <thead>
                                                <tr style="font-size: 0.70rem;">
                                                    <th>No</th>
                                                    <th>Tipo de Póliza</th>
                                                    <th>Nombre</th>
                                                    <th>Descripción</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($status) {
                                                    $it = 0;
                                                    foreach ($diarioConfig as $partida) {
                                                        $id = $partida['id'];
                                                        $name = $partida['titulo'];
                                                        $description = $partida['descripcion'];
                                                        $tipoPoliza = TipoPoliza::getDescripcion($partida['id_tipo_poliza']);
                                                        // Generar el botón de acción para cada fila
                                                        echo '<tr>';
                                                        echo '<td class="ps-0">' . ($it + 1) . '</td>';
                                                        echo '<td class="ps-0">' . htmlspecialchars($tipoPoliza) . '</td>';
                                                        echo '<td class="ps-0">' . htmlspecialchars($name) . '</td>';
                                                        echo '<td class="ps-0">' . htmlspecialchars($description) . '</td>';
                                                        echo '<td class="ps-0">';
                                                        echo '<button type="button" class="btn btn-outline-success btn-sm" data-bs-dismiss="modal" onclick="printdiv3(\'section_partidas_preconfiguradas\', \'#contenedor_section\',\'' . $id . '\')" >';
                                                        echo '<i class="fa-solid fa-paper-plane"></i>';
                                                        echo '</button>';
                                                        echo '</td>';
                                                        echo '</tr>';
                                                        $it++;
                                                    }
                                                } else {
                                                    // echo '<tr><td colspan="4" class="text-center">No hay partidas preconfiguradas disponibles</td></tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- CONTENEDOR QUE SE VA A REIMPRIMIR -->
                    <div id="contenedor_section" class="col-12" style="padding-left: 0px !important; padding-right: 0px !important;">

                    </div>
                </div>
            </div>

            <!-- Scripts JavaScript -->
            <script>
                var table_partidas_aux;
                $(document).ready(function() {
                    printdiv3('section_partidas_preconfiguradas', '#contenedor_section', '0');
                    $('#tb_partidas_preconfiguradas').DataTable();
                });
            </script>
        <?php
        break;
    //Fin de la configuracion de Partidas de Diario

    case 'section_partidas_preconfiguradas':
        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];

        $showmensaje = false;
        $dataPolicy = [];
        $dataFondos = [];
        try {
            $database->openConnection();
            $dataPolicy = $database->selectColumns("$db_name_general.ctb_tipo_poliza", ["id", "descripcion"], "id IN (6,9,13)");
            //Seccion de fuente de fondos
            $dataFondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            $selectfondo = '<select class="form-select form-select-sm">';
            $selectfondo .= '<option value="0" selected>Seleccione una fuente de fondo</option>';
            foreach ($dataFondos as $fondo) {
                $selectfondo .= '<option value="' . htmlspecialchars($fondo['id']) . '">' . htmlspecialchars($fondo['descripcion']) . '</option>';
            }
            $selectfondo .= '</select>';

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
            <div id="ladopartidapreconfigurada">
                <div class="scrollspy-example-2" tabindex="0" style="width: 100%;">
                    <div class="text-center mb-3">
                        <h5>Datos generales de partida</h5>
                    </div>
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>!!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <!-- Seccion de inputs para edicion -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <!-- nombre y descripción-->
                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="form-floating mb-3 mt-2">
                                    <input type="text" class="form-control" id="name" placeholder="name">
                                    <input type="text" name="" id="id_sector" hidden>
                                    <label for="name">Nombre</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating mb-3 mt-2">
                                    <select class="form-select" id="type_policy">
                                        <?php if (empty($dataPolicy)): ?>
                                            <option selected disabled>No hay tipos de póliza disponibles</option>
                                        <?php else: ?>
                                            <?php foreach ($dataPolicy as $index => $policy): ?>
                                                <option value="<?= htmlspecialchars($policy['id']) ?>" <?= $index === 0 ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($policy['descripcion']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <label for="type">Tipo de poliza</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" id="description" placeholder="description">
                                    <label for="description">Descripción</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Section de movimiento de partida -->
                    <div class="text-center mb-3 mt-3">
                        <h5>Movimientos de partida</h5>
                    </div>
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="table-responsive">
                            <table id="mov_partidas" class="table text-center mb-2 table-sm">
                                <thead class="">
                                    <tr>
                                        <th style="width:1%;">No.</th>
                                        <th style="width:0.01%;">Id</th>
                                        <th style="width:17%;">Nombre</th>
                                        <th>Cuenta</th>
                                        <th style="width:22%;">Debe</th>
                                        <th style="width:22%;">Haber</th>
                                        <th style="width:20%;">Fondos</th>
                                    </tr>
                                </thead>
                                <tbody style="font-size: 0.9rem !important;">
                                    <!-- Fila 1 -->
                                    <!-- <tr>
                                        <td class="align-middle">1</td>
                                        <td class="align-middle">1</td>
                                        <td class="align-middle">Ejemplo Cuenta 1</td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" value="001-001-0001" disabled>
                                                <button class="btn btn-outline-success" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', 'idcuenta')">
                                                    <i class="fa fa-magnifying-glass"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Q</span>
                                                <input type="number" class="form-control text-end" value="100.00" disabled>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Q</span>
                                                <input type="number" class="form-control text-end" value="0.00" disabled>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $selectfondo ?>
                                        </td>
                                    </tr> -->
                                </tbody>
                            </table>
                        </div>
                        <div class="col-10">
                            <div class="row">
                                <div class="col-sm-4 mb-1">
                                    <button id="addRow" class="btn btn-outline-primary"
                                        title="Añadir nueva fila" onclick="newrow3()">
                                        <i class="fa-solid fa-plus fa-fade fa-sm"></i>
                                    </button>
                                    <button id="deletefila" class="btn btn-outline-danger"
                                        title="Eliminar fila" onclick="deletefila_semi_automatic_configuration()">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>

                                <!-- <div class="col-sm-4 mb-3 ps-0">
                                    <div class="input-group" style="width: 70%;float: right;">
                                        <span class="input-group-text">Q</span>
                                        <input id="totdebe" style="text-align: right;" type="number" step="0.01"
                                            class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="col-sm-4 mb-2 ps-0">
                                    <div class="input-group" style="width: 70%;float: left;">
                                        <span class="input-group-text">Q</span>
                                        <input id="tothaber" style="text-align: right;" type="number" step="0.01"
                                            class="form-control" readonly>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>

                    <div class="row justify-items-md-center">
                        <div class="col d-flex align-items-center mt-2 gap-1" id="modal_footer">
                            <button type="button" class="btn btn-outline-success" id="btGuardar"
                                onclick="savecom_preconfigured(`create_preconfigured_game`,`0`)">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btEditar" style="display: none;"
                                onclick="obtiene([`name`,`description`,`id_sector`],[],[],`update_sector`,`0`,['<?= $codusu; ?>'])">
                                <i class="fa-solid fa-floppy-disk"></i> Actualizar
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
                $(document).ready(function() {
                    var t = $('#mov_partidas').dataTable({
                        "searching": false,
                        "paging": false,
                        "ordering": false,
                        "info": false,
                        "language": {
                            "zeroRecords": " ",
                        },
                        columnDefs: [{
                            targets: [0, 1, 2],
                            createdCell: function(td, cellData) {
                                $(td).css({
                                    'vertical-align': 'middle',
                                    'text-align': 'left' // alineación horizontal a la izquierda
                                });
                            }
                        }]
                    }).DataTable();
                    $('#mov_partidas tbody').on('click', 'tr', function() {
                        if ($(this).hasClass('selected')) {
                            $(this).removeClass('selected');
                        } else {
                            $('.selected').removeClass('selected');
                            $(this).addClass('selected');
                        }
                    });
                    semi_automatic_configuration_reset(0);
                });

                function newrow3() {
                    newrow_semi_automatic_configuration(<?php echo json_encode($dataFondos); ?>);
                }
            </script>
        <?php
        break;
    case 'Partidas_semiauto':

        $showmensaje = false;
        try {
            $database->openConnection();

            $diarioConfig = $database->selectColumns(
                "ctb_diario_config",
                ["id", "id_tipo_poliza", "titulo", "descripcion"],
                "estado=1",
            );
            if (empty($diarioConfig)) {
                $showwmensaje = true;
                throw new Exception("No se encontraron partidas preconfiguradas");
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
            <!-- Inputs ocultos para condiciones -->
            <input type="text" id="condi" value="Partidas_semiauto" hidden>
            <input type="text" id="file" value="ctb001" hidden>

            <div class="text" style="text-align:center">Generacion de partidas de diario preconfiguradas</div>
            <div class="card mb-2">
                <div class="card-header">Partida semiautomática</div>
                <div class="card-body ">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>!!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <button type="button" class="col-12 btn btn-primary " data-bs-toggle="modal" data-bs-target="#modalPartidasPreconfiguradas">
                                <i class="fas fa-list"></i> Buscar partidas preconfiguradas
                            </button>
                        </div>
                    </div>
                    <!-- Aqui generar el html del modal -->
                    <div class="modal fade" id="modalPartidasPreconfiguradas" tabindex="-1" aria-labelledby="modalPartidasPreconfiguradasLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalPartidasPreconfiguradasLabel">Partidas preconfiguradas</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="list-example" class="h-100 flex-column align-items-stretch pe-1 border-end">
                                        <table class="table nowrap" id="tb_partidas_preconfiguradas" style="width: 100% !important; font-size: 1rem;">
                                            <thead>
                                                <tr style="font-size: 0.70rem;">
                                                    <th>No</th>
                                                    <th>Tipo De Poliza</th>
                                                    <th>Titulo</th>
                                                    <th>Descripción</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($status) {
                                                    $it = 0;
                                                    foreach ($diarioConfig as $partida) {
                                                        $id = $partida['id'];
                                                        $name = $partida['titulo'];
                                                        $description = mb_substr($partida['descripcion'], 0, 50) . (mb_strlen($partida['descripcion']) > 50 ? '...' : '');
                                                        $tipoPoliza = TipoPoliza::getDescripcion($partida['id_tipo_poliza']);
                                                        // Generar el botón de acción para cada fila
                                                        echo '<tr>';
                                                        echo '<td class="ps-0">' . ($it + 1) . '</td>';
                                                        echo '<td class="ps-0">' . htmlspecialchars($tipoPoliza) . '</td>';
                                                        echo '<td class="ps-0">' . htmlspecialchars($name) . '</td>';
                                                        echo '<td class="ps-0">' . htmlspecialchars($description) . '</td>';
                                                        echo '<td class="ps-0">';
                                                        echo '<button type="button" class="btn btn-outline-success btn-sm" data-bs-dismiss="modal" onclick="printdiv3(\'Partidas_semiauto_detalle\', \'#contenedor_section\',\'' . $id . '\')" >';
                                                        echo '<i class="fa-solid fa-paper-plane"></i>';
                                                        echo '</button>';
                                                        echo '</td>';
                                                        echo '</tr>';
                                                        $it++;
                                                    }
                                                } else {
                                                    // echo '<tr><td colspan="4" class="text-center">No hay partidas preconfiguradas disponibles</td></tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- CONTENEDOR QUE SE VA A REIMPRIMIR -->
                    <div id="contenedor_section" class="col-12" style="padding-left: 0px !important; padding-right: 0px !important;">

                    </div>
                </div>
            </div>

            <!-- Scripts JavaScript -->
            <script>
                // var table_partidas_aux;
                $(document).ready(function() {
                    printdiv3('Partidas_semiauto_detalle', '#contenedor_section', '0');
                    //convertir a datatabla la tabla
                    $('#tb_partidas_preconfiguradas').DataTable();
                });
            </script>
        <?php
        break;
    case 'Partidas_semiauto_detalle':
        $xtra = $_POST["xtra"];

        $query = "SELECT dia.id, dia.id_tipo_poliza, dia.titulo, dia.descripcion, no_unico, mov.id_fondo, 
                    mov.cuenta_contable, mov.debe, mov.haber
                    FROM ctb_mov_config AS mov 
                    INNER JOIN ctb_diario_config AS dia ON mov.id_config = dia.id 
                     WHERE dia.id=? and dia.estado=1";

        $showmensaje = false;
        try {
            $database->openConnection();

            $fondoselect = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondoselect)) {
                $showmensaje = true;
                throw new Exception("No se encontraron fondos disponibles");
            }

            $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "cod_agenc", "nom_agencia"]);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias disponibles");
            }

            $ctbNomenclatura = $database->selectColumns("ctb_nomenclatura", ["id", "ccodcta", "cdescrip", "tipo"], "estado=1", orderBy: "ccodcta");
            if (empty($ctbNomenclatura)) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas disponibles");
            }

            $tiposPolizas = TipoPoliza::getTiposPoliza();

            if (empty($xtra)) {
                $showmensaje = true;
                throw new Exception("Seleccione una partida de diario preconfigurada");
            }

            $ctbmovdata = $database->getAllResults($query, [$xtra]);
            if (empty($ctbmovdata)) {
                $showmensaje = true;
                throw new Exception("No se encontraron movimientos para la partida seleccionada");
            }

            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
        ?>
            <div class="container mt-5" style="width: 100%;">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <h3 class="text-center mb-4"><?= ($status) ? $ctbmovdata[0]['titulo'] : ''; ?></h3>
                <div class="mb-3">
                    <label class="form-label fw-bold text-primary">Descripción de la Partida</label>
                    <div class="alert alert-info py-2 px-3" id="descripcionPartida">
                        <?= ($status) ? htmlspecialchars($ctbmovdata[0]['descripcion']) : ''; ?>
                    </div>
                </div>
                <form id="formPartidaDiario" class="needs-validation" novalidate>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fa fa-file-invoice"></i> Datos Generales de la Partida</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label for="id_ctb_tipopoliza" class="form-label fw-semibold">Tipo de Póliza</label>
                                    <div class="input-group">
                                        <select required class="form-select select2" id="id_ctb_tipopoliza" name="id_ctb_tipopoliza">
                                            <option value="">Seleccione...</option>
                                            <?php
                                            foreach ($tiposPolizas as $tipo):
                                                $selected = ($tipo['id'] == $ctbmovdata[0]['id_tipo_poliza']) ? 'selected' : '';
                                                echo '<option value="' . $tipo['id'] . '" ' . $selected . '>' . $tipo['descripcion'] . '</option>';
                                            endforeach;
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="id_agencia" class="form-label fw-semibold">Agencia</label>
                                    <div class="input-group">
                                        <select class="form-select select2" id="id_agencia" name="id_agencia" required>
                                            <option value="">Seleccione...</option>
                                            <?php
                                            foreach ($agencias as $agencia):
                                                $selected = ($agencia['id_agencia'] == $idagencia) ? 'selected' : '';
                                                echo '<option ' . $selected . ' value="' . $agencia['id_agencia'] . '">' . htmlspecialchars($agencia['nom_agencia']) . '</option>';
                                            endforeach;
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label for="numdoc" class="form-label fw-semibold">Número de Documento</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa fa-hashtag"></i></span>
                                        <input data-label="numero de documento" type="text" class="form-control" id="numdoc" name="numdoc" maxlength="50" required placeholder="Ej: DOC-12345">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="glosa" class="form-label fw-semibold">Glosa</label>
                                <textarea class="form-control" id="glosa" name="glosa" rows="3" maxlength="200" required placeholder="Ingrese la glosa o descripción de la partida"></textarea>
                            </div>
                            <div class="row g-3 mb-2">
                                <div class="col-12 col-md-6">
                                    <label for="fedoc" class="form-label fw-semibold">Fecha de Documento</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa fa-calendar-alt"></i></span>
                                        <input type="date" class="form-control" id="fedoc" name="fedoc" required value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="feccnt" class="form-label fw-semibold">Fecha en Contabilidad</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="fa fa-calendar-check"></i></span>
                                        <input type="date" class="form-control" id="feccnt" name="feccnt" required value="<?= date('Y-m-d') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        Movimientos de las Cuentas
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tablaMovimientos">
                                <thead>
                                    <tr>
                                        <th style="width: 4%;">ID</th>
                                        <th style="width: 33%;">Cuenta Contable</th>
                                        <th style="width: 22%;">Debe</th>
                                        <th style="width: 22%;">Haber</th>
                                        <th style="width: 15%;">F.Fondos</th>
                                        <th style="width: 4%;">🎈</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($status && !empty($ctbmovdata)):
                                        foreach ($ctbmovdata as $movimiento):
                                    ?>
                                            <tr>
                                                <td>
                                                    <span><?= $movimiento['no_unico'] ?></span>
                                                </td>
                                                <td>
                                                    <select class="form-select select2" name="id_ctb_nomenclatura[]" required>
                                                        <option value="">Seleccione...</option>
                                                        <?php
                                                        $currentGroup = null;
                                                        foreach ($ctbNomenclatura as $key => $nomenclatura):
                                                            $indentationLevel = strlen($nomenclatura['ccodcta']) / 2;
                                                            // $indentation = str_repeat('*', $indentationLevel*2); // Cada nivel agrega 2 espacios
                                                            $indentation = str_repeat('&nbsp;', $indentationLevel);

                                                            if ($nomenclatura['tipo'] === 'R'):
                                                                if ($currentGroup !== null) {
                                                                    echo '</optgroup>';
                                                                }
                                                                $currentGroup = $nomenclatura['cdescrip'];
                                                                echo '<optgroup label="' . $indentation . $currentGroup . '">';
                                                            else:
                                                                $selected = ($nomenclatura['id'] == $movimiento['cuenta_contable']) ? 'selected' : '';
                                                                // Si es una cuenta tipo "D", agregamos la opción dentro del grupo actual
                                                                echo '<option ' . $selected . ' value="' . $nomenclatura['id'] . '">' . $indentation . $nomenclatura['ccodcta'] . ' - ' . $nomenclatura['cdescrip'] . '</option>';
                                                            endif;
                                                        endforeach;
                                                        if ($currentGroup !== null) {
                                                            echo '</optgroup>';
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control" name="debe[]" data-formula="<?= htmlspecialchars($movimiento['debe']) ?>" value="<?= is_numeric($movimiento['debe']) ? $movimiento['debe'] : '' ?>">
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control" name="haber[]" data-formula="<?= htmlspecialchars($movimiento['haber']) ?>" value="<?= is_numeric($movimiento['haber']) ? $movimiento['haber'] : '' ?>">
                                                </td>
                                                <td>
                                                    <select class="form-select select2" name="id_fondo[]" required>
                                                        <option value="">Seleccione...</option>
                                                        <?php
                                                        foreach ($fondoselect as $fondo):
                                                            $selected = ($fondo['id'] == $movimiento['id_fondo']) ? 'selected' : '';
                                                            echo '<option value="' . $fondo['id'] . '" ' . $selected . '>' . htmlspecialchars($fondo['descripcion']) . '</option>';
                                                        endforeach;
                                                        ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm" title="Eliminar fila" onclick="eliminarFila(this)">X</button>
                                                </td>
                                            </tr>

                                    <?php
                                        endforeach;
                                    else:
                                        echo '<tr><td colspan="5" class="text-center">No hay movimientos preconfigurados disponibles</td></tr>';
                                    endif;
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td style="width: 4%;"></td>
                                        <td style="width: 33%; text-align: right;"><strong>Totales:</strong></td>
                                        <td style="width: 22%; text-align: right;">
                                            <strong>Q <span id="totalDebe" class="text-success">0.00</span></strong>
                                        </td>
                                        <td style="width: 22%; text-align: right;">
                                            <strong>Q <span id="totalHaber" class="text-danger">0.00</span></strong>
                                        </td>
                                        <td style="width: 15%;"></td>
                                        <td style="width: 4%;"></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php if ($status): ?>
                                <button type="button" class="btn btn-success" id="addMovimiento">+</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php echo $csrf->getTokenField(); ?>

                <?php if ($status): ?>
                    <div class="text-center">
                        <button type="button" class="btn btn-outline-primary" onclick="savePoliza()">Guardar Partida</button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0');">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <script>
                <?php if ($status): ?>
                    document.getElementById('addMovimiento').addEventListener('click', function() {
                        if (newRowValidate() === false) {
                            return; // No agregar nueva fila si la validación falla
                        }
                        const tabla = document.getElementById('tablaMovimientos').querySelector('tbody');
                        const nuevaFila = `
                        <tr>
                            <td></td>
                            <td>
                                <select class="form-select select2" name="id_ctb_nomenclatura[]" required>
                                    <option value="">Seleccione...</option>
                                    <?php
                                    $currentGroup = null; // Variable para rastrear el grupo actual
                                    foreach ($ctbNomenclatura as $key => $nomenclatura):
                                        // Calcular el nivel de indentación basado en la longitud de la cuenta
                                        $indentationLevel = strlen($nomenclatura['ccodcta']) / 2; // Ajusta el divisor según tu estructura
                                        $indentation = str_repeat('&nbsp;', $indentationLevel);

                                        if ($nomenclatura['tipo'] === 'R'):
                                            // Si es una cuenta tipo "R", cerramos el grupo anterior y abrimos uno nuevo
                                            if ($currentGroup !== null) {
                                                echo '</optgroup>';
                                            }
                                            $currentGroup = $nomenclatura['cdescrip'];
                                            echo '<optgroup label="' . $indentation . $currentGroup . '">';
                                        else:
                                            // Si es una cuenta tipo "D", agregamos la opción dentro del grupo actual
                                            echo '<option value="' . $nomenclatura['id'] . '">' . $indentation . $nomenclatura['ccodcta'] . ' - ' . $nomenclatura['cdescrip'] . '</option>';
                                        endif;
                                    endforeach;
                                    // Cerramos el último grupo si existe
                                    if ($currentGroup !== null) {
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" class="form-control" name="debe[]"></td>
                            <td><input type="number" step="0.01" class="form-control" name="haber[]"></td>
                            <td>
                                <select class="form-select select2" name="id_fondo[]" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($fondoselect as $fondo): ?>
                                        <option value="<?= htmlspecialchars($fondo['id']) ?>"><?= htmlspecialchars($fondo['descripcion']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><button type="button" class="btn btn-danger btn-sm" title="Eliminar fila" onclick="eliminarFila(this)">X</button></td>
                        </tr>`;
                        tabla.insertAdjacentHTML('beforeend', nuevaFila);

                        // Reaplicar Select2 a los nuevos elementos
                        $('.select2').select2({
                            placeholder: "Seleccione una opción",
                            allowClear: true
                        });
                    });

                    $(document).ready(function() {
                        $('.select2').select2({
                            theme: 'classic',
                            width: '100%',
                            placeholder: "Seleccione una opción",
                            allowClear: true,
                        });
                        inicializarProcesamientoDeFormulasTablaMovimientos();
                        inicializarValidacionAutomaticaGeneric('#formPartidaDiario');
                        // Actualizar totales cuando cambie cualquier input de debe o haber
                        $('#tablaMovimientos').on('input change', 'input[name="debe[]"], input[name="haber[]"]', function() {
                            actualizarTotales();
                        });
                        actualizarTotales();
                    });
                <?php endif; ?>

                // Eliminar fila de movimiento
                function eliminarFila(button) {
                    button.closest('tr').remove();
                    actualizarTotales();
                }

                /**
                 * Procesa las fórmulas dinámicas y actualiza las filas dependientes en la tabla `tablaMovimientos`.
                 */
                function procesarFormulasTablaMovimientos() {
                    console.log("++++++++++++++++++++++++++++++++++++++++++");
                    const allRows = $('#tablaMovimientos tbody tr');

                    $(allRows).each(function(index, row) {
                        const formulaDebe = $(row).find('td').eq(2).find('input').data('formula'); // Fórmula en "Debe"
                        const formulaHaber = $(row).find('td').eq(3).find('input').data('formula'); // Fórmula en "Haber"

                        if (formulaDebe && validateMathExpression(formulaDebe, allRows.length) && !/^\s*-?\d+(\.\d+)?\s*$/.test(formulaDebe)) {
                            const nuevoValorDebe = evaluarFormulaTablaMovimientos(formulaDebe, allRows);
                            $(row).find('td').eq(2).find('input').val(nuevoValorDebe.toFixed(2));
                            $(row).find('td').eq(3).find('input').val(""); // Limpiar el campo "Haber" si se actualiza "Debe"
                        }

                        if (formulaHaber && validateMathExpression(formulaHaber, allRows.length) && !/^\s*-?\d+(\.\d+)?\s*$/.test(formulaHaber)) {
                            const nuevoValorHaber = evaluarFormulaTablaMovimientos(formulaHaber, allRows);
                            $(row).find('td').eq(3).find('input').val(nuevoValorHaber.toFixed(2));
                            $(row).find('td').eq(2).find('input').val(""); // Limpiar el campo "Debe" si se actualiza "Haber"
                        }
                    });

                    // Actualizar totales después de procesar las fórmulas
                    actualizarTotales();
                }

                /**
                 * Evalúa una fórmula y calcula el resultado en la tabla `tablaMovimientos`.
                 * @param {string} formula - La fórmula a evaluar (ejemplo: "F1*5").
                 * @param {Array} allRows - Todas las filas de la tabla.
                 * @returns {number} - El resultado de la fórmula.
                 */
                function evaluarFormulaTablaMovimientos(formula, allRows) {
                    let resultado = 0;

                    const formulaProcesada = formula.replace(/F(\d+)/g, function(match, idReferencia) {
                        let valorDebe = 0;
                        let valorHaber = 0;
                        $(allRows).each(function() {
                            const idFila = $(this).find('td').eq(0).text().trim(); // Obtener el valor de la columna "ID"
                            if (idFila === match) {
                                valorDebe = parseFloat($(this).find('td').eq(2).find('input').val()) || 0;
                                valorHaber = parseFloat($(this).find('td').eq(3).find('input').val()) || 0;
                            }
                        });
                        return (valorDebe !== 0) ? valorDebe : (valorHaber !== 0) ? valorHaber : 0;
                    });

                    try {
                        resultado = eval(formulaProcesada);
                    } catch (error) {
                        resultado = 0;
                    }
                    return resultado;
                }

                /**
                 * Escucha cambios en los valores de las filas y recalcula las fórmulas dinámicamente en `tablaMovimientos`.
                 */
                function inicializarProcesamientoDeFormulasTablaMovimientos() {
                    // Escuchar cambios en los inputs de "Debe" y "Haber"
                    $('#tablaMovimientos tbody').on('change', 'input', function() {
                        const row = $(this).closest('tr');
                        const input = $(this);
                        // console.log("Input cambiado:", input.attr('name'), "en fila:", row.index() + 1);
                        // Validar el lado de la cuenta
                        if (!validacion_lado(row, input)) {
                            return; // Si la validación falla, no continuar
                        }
                        // console.log("Validación lado correcta, procesando fórmulas...");
                        procesarFormulasTablaMovimientos();
                    });

                    // Procesar fórmulas al cargar la tabla
                    procesarFormulasTablaMovimientos();
                }

                function validacion_lado(row, input) {
                    //esta funcion valida que solo se puede agregar valores en un lado de la cuenta, no en ambos
                    const valorDebe = parseFloat(row.find('td').eq(2).find('input').val()) || 0;
                    const valorHaber = parseFloat(row.find('td').eq(3).find('input').val()) || 0;
                    if (valorDebe !== 0 && valorHaber !== 0) {
                        Swal.fire({
                            icon: 'error',
                            title: '¡ERROR!',
                            text: 'No se puede agregar valores en ambos lados de la cuenta, verifique'
                        });
                        input.val(""); // Limpiar el input que causó el error
                        return false; // Indicar que la validación falló
                    }
                    return true; // Validación exitosa
                }

                function newRowValidate() {
                    const allRows = $('#tablaMovimientos tbody tr');
                    let isValid = true; // Variable para controlar la validación

                    $(allRows).each(function(index, row) {
                        const debe = $(row).find('td').eq(2).find('input').val();
                        const haber = $(row).find('td').eq(3).find('input').val();
                        const cuenta = $(row).find('td').eq(1).find('select').val();
                        const idFondo = $(row).find('td').eq(4).find('select').val();

                        if (!cuenta || cuenta === "" || !idFondo || idFondo === "") {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: 'Debe seleccionar una cuenta contable y un fondo para cada fila.'
                            });
                            isValid = false;
                            return false; // Detiene el bucle .each()
                        }

                        if ((debe === "" && haber === "") || (debe === 0 && haber === 0) || (debe == "" && haber == 0) || (debe == 0 && haber == "")) {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: 'Debe ingresar un valor en "Debe" o "Haber", pero no en ambos.'
                            });
                            console.log("Debe:", debe, "Haber:", haber);
                            isValid = false;
                            return false; // Detiene el bucle .each()
                        }
                    });

                    if (isValid) {
                        // console.log("Todas las filas son válidas, continuando con el guardado.");
                    }

                    return isValid;
                }

                function savePoliza() {
                    if (newRowValidate() === false) {
                        return;
                    }
                    const movimientos = [];
                    $('#tablaMovimientos tbody tr').each(function() {
                        const cuenta = $(this).find('td').eq(1).find('select').val();
                        const debe = $(this).find('td').eq(2).find('input').val();
                        const haber = $(this).find('td').eq(3).find('input').val();
                        const idFondo = $(this).find('td').eq(4).find('select').val();
                        movimientos.push({
                            cuenta,
                            debe,
                            haber,
                            idFondo
                        });
                    });
                    // Validar que haya al menos un movimiento
                    if (movimientos.length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: '¡ERROR!',
                            text: 'Debe agregar al menos un movimiento a la póliza.'
                        });
                        return;
                    }

                    obtiene(['<?= $csrf->getTokenName() ?>', 'numdoc', 'fedoc', 'feccnt', 'glosa'], ['id_ctb_tipopoliza', 'id_agencia'], [], 'create_poliza_diario', '0', [movimientos, '<?= $xtra ?>'],
                        function(data) {
                            // console.log("Respuesta del servidor:", data);
                        },
                        'Desea guardar la póliza de diario?',
                    )
                    // return true;
                }

                function actualizarTotales() {
                    let totalDebe = 0;
                    let totalHaber = 0;

                    // Recorrer todas las filas de la tabla
                    $('#tablaMovimientos tbody tr').each(function() {
                        const debe = parseFloat($(this).find('td').eq(2).find('input').val()) || 0;
                        const haber = parseFloat($(this).find('td').eq(3).find('input').val()) || 0;

                        totalDebe += debe;
                        totalHaber += haber;
                    });

                    // Actualizar los totales en el footer de la tabla
                    $('#totalDebe').text(totalDebe.toFixed(2));
                    $('#totalHaber').text(totalHaber.toFixed(2));

                    // Aplicar clases de estilo según si los totales cuadran
                    const diferencia = Math.abs(totalDebe - totalHaber);
                    const totalesSpans = $('#totalDebe, #totalHaber');

                    if (diferencia === 0 && totalDebe !== 0) {
                        totalesSpans.removeClass('text-danger').addClass('text-success');
                    } else {
                        totalesSpans.removeClass('text-success').addClass('text-danger');
                    }
                }
            </script>

    <?php

        break;
}
    ?>