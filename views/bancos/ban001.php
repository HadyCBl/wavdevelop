<?php

use Micro\Generic\PermissionManager;
use Micro\Helpers\Log;

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
require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];
switch ($condi) {
    case 'cheques':
?>
        <input type="text" id="condi" value="cheques" hidden>
        <input type="text" id="file" value="ban001" hidden>
        <div class="text" style="text-align:center">EMISIÓN DE CHEQUES</div>
        <div class="card">
            <div class="card-header bg-primary bg-gradient">Emisión de Cheques</div>
            <div class="card-body">
                <div class="row">
                    <!-- <div class="col-4">
                        <div id="list-example" class="h-100 flex-column align-items-stretch pe-4 border-end">
                            <div class="table-responsive">
                                <table class="table nowrap" id="tb_cheques" style="width: 100% !important;">
                                    <thead>
                                        <tr style="font-size: 0.7rem;">
                                            <th>Poliza</th>
                                            <th>Fecha</th>
                                            <th>Mon. Cheque</th>
                                            <th>Est</th>
                                            <th>Acc.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider" style="font-size: 0.6rem !important;">

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div> -->

                    <!-- asdfsadf -->
                    <div class="row justify-content-center">
                        <div class="col-12 mb-3 d-flex justify-content-center">
                            <div class="card border-primary" style="max-width: 400px; width: 100%;">
                                <div class="card-body p-2 d-flex justify-content-center">
                                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalPartidasCheques">
                                        <i class="fas fa-list"></i> Buscar Voucher
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Aqui generar el html del modal -->
                    <div class="modal fade" id="modalPartidasCheques" tabindex="-1" aria-labelledby="modalPartidasChequesLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalPartidasChequesLabel">Vouchers</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="list-example" class="h-100 flex-column align-items-stretch pe-1 border-end">
                                        <table class="table nowrap" id="tb_cheques" style="width: 100% !important; font-size: 1rem;">
                                            <thead>
                                                <tr style="font-size: 0.70rem;">
                                                    <th>Poliza</th>
                                                    <th>Fecha</th>
                                                    <th>No. Cheque</th>
                                                    <th>Mon. Cheque</th>
                                                    <th>Est</th>
                                                    <th>Acc.</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="contenedor_section" class="col-12" style="padding-left: 0px !important; padding-right: 7px !important;">

                    </div>

                    <script>
                        var table_cheques_aux;
                        $(document).ready(function() {
                            printdiv3('section_cheques', '#contenedor_section', '0');
                            table_cheques_aux = $('#tb_cheques').on('search.dt').DataTable({
                                "processing": true,
                                "serverSide": true,
                                "sAjaxSource": "../src/server_side/lista_cheques.php",
                                columns: [{
                                        data: [1]
                                    },
                                    {
                                        data: [2]
                                    },
                                    {
                                        data: [5]
                                    },
                                    {
                                        data: [4]
                                    },
                                    {
                                        data: [6],
                                        render: function(data, type, row) {
                                            imp = '';
                                            if (data == 1) {
                                                imp = `<span class="badge bg-success">Sí</span>`;
                                            } else if (data == 2) {
                                                imp = `<span class="badge bg-secondary">Nulo</span>`;
                                            } else {
                                                if (row[6] == '' || row[6] == null) {
                                                    imp = `<span class="badge bg-danger">No</span>`;
                                                } else {
                                                    imp = `<span class="badge bg-warning text-dark">No</span>`;
                                                }
                                            }
                                            return imp;
                                        }
                                    },
                                    {
                                        data: [0],
                                        render: function(data, type, row) {
                                            return `<button type="button" data-bs-dismiss="modal" class="btn btn-outline-success btn-sm" onclick="printdiv3('section_cheques', '#contenedor_section','${data}')" ><i class="fa-regular fa-eye"></i></button>`;
                                        }
                                    },
                                ],
                                "fnServerParams": function(aoData) {
                                    //PARAMETROS EXTRAS QUE SE LE PUEDEN ENVIAR AL SERVER ASIDE
                                    aoData.push({
                                        "name": "whereextra",
                                        "value": "id_agencia!=0"
                                    });
                                },
                                // "bDestroy": true,
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
                        })
                    </script>

                <?php
                break;

            case 'section_cheques':
                $idCtbDiario = $_POST["xtra"];
                $newVoucher = true;
                $query = "SELECT cd.id, cc.emitido, cd.numcom, cd.fecdoc, cd.feccnt, cd.id_agencia, cm.id_fuente_fondo, cc.monchq,cd.id_tb_usu, 
                                cd.numdoc, cc.nomchq, tb.id AS id_banco, cc.id_cuenta_banco, cc.numchq, cd.glosa, cm.id_ctb_nomenclatura, cn.ccodcta, 
                                cm.debe, cm.haber, cc.id AS id_reg_cheque, cc.modocheque AS modocheque, IFNULL(cd.created_by,'0') AS created_by
                            FROM ctb_diario cd
                            INNER JOIN ctb_mov cm ON cd.id=cm.id_ctb_diario
                            INNER JOIN ctb_nomenclatura cn ON cm.id_ctb_nomenclatura=cn.id
                            INNER JOIN ctb_chq cc ON cd.id=cc.id_ctb_diario
                            INNER JOIN ctb_bancos cb ON cc.id_cuenta_banco=cb.id
                            INNER JOIN tb_bancos tb ON cb.id_banco=tb.id
                            WHERE cd.estado='1' AND cd.id=?";

                $showmensaje = false;
                try {
                    $database->openConnection();
                    $fondoselect = $database->selectColumns('ctb_fuente_fondos', ['id', 'descripcion'], 'estado=1');
                    if (empty($fondoselect)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron fondos disponibles.");
                    }

                    $bancos = $database->selectColumns('tb_bancos', ['id', 'nombre'], 'estado=1');
                    if (empty($bancos)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron bancos disponibles.");
                    }

                    $whereForUsers = ($idusuario == 4) ? 'estado=1' : 'estado=1 and id_usu!=4';

                    $usuarios = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido'], $whereForUsers);
                    if (empty($usuarios)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron usuarios disponibles.");
                    }

                    $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia']);
                    if (empty($agencias)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron agencias disponibles.");
                    }

                    if ($idCtbDiario == '0') {
                        $showmensaje = true;
                        throw new Exception("Complete los campos para generar un nuevo cheque.");
                    }
                    $newVoucher = false;

                    $ctbmovdata = $database->getAllResults($query, [$idCtbDiario]);
                    if (empty($ctbmovdata)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron datos para el cheque seleccionado.");
                    }

                    // $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (22) AND id_usuario=? AND estado=1", [$idusuario]);
                    // $permisoReimpresionVoucher = new PermissionHandler($permisos, 22);

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
                $disabled = ($newVoucher) ? '' : ' disabled ';
                $displayUserSection = (!$newVoucher && $status && $ctbmovdata[0]['created_by'] != '0' && $ctbmovdata[0]['created_by'] != $ctbmovdata[0]['id_tb_usu']) ? true : false;

                $showSectionUserAssigned = 'none';
                $permisoReimpresionCheque = false;
                try {

                    $userPermissions = new PermissionManager($idusuario);

                    if ($userPermissions->isLevelOne(PermissionManager::ASIGNAR_USUARIOS_BANCOS)) {
                        $showSectionUserAssigned = 'block';
                    }
                    $permisoReimpresionCheque = $userPermissions->isLevelOne(PermissionManager::REIMPRESION_CHEQUES);
                } catch (Exception $e) {
                    Log::error("Error al verificar permisos de usuario: " . $e->getMessage());
                }

                $displayUserSection = ($showSectionUserAssigned == 'block') ? $displayUserSection : false;

                ?>
                    <div class="scrollspy-example-2" tabindex="0">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>!!</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>
                        <div class="container contenedort">
                            <div class="row">
                                <div class="col mb-2">
                                    <?php if (!$newVoucher && $status): ?>
                                        <div class="row">
                                            <label class="text-success" for="datedoc">Poliza No. <?= $ctbmovdata[0]['numcom'] ?> </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mb-2" style="display: <?= $showSectionUserAssigned ?>">
                                <div class="col-sm-12 col-md-12 col-lg-6  d-flex align-items-center">
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="radio" name="tipoAsignacion"
                                            id="byAgency" value="byAgency" <?= ($displayUserSection) ? '' : 'checked' ?>
                                            onclick="showHideElement('section_codofi', 'show'); showHideElement('section_usuario_asignado', 'hide')">
                                        <label class="form-check-label" for="byAgency">
                                            Asignar por Agencia
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipoAsignacion"
                                            id="byUser" value="byUser" <?= ($displayUserSection) ? 'checked' : '' ?>
                                            onclick="showHideElement('section_codofi', 'hide'); showHideElement('section_usuario_asignado', 'show')">
                                        <label class="form-check-label" for="byUser">
                                            Asignar por Usuario
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <input <?= $disabled; ?> type="date" class="form-control" id="datedoc" value="<?= ($newVoucher) ? $hoy : $ctbmovdata[0]["fecdoc"]; ?>">
                                        <label class="text-primary" for="datedoc">Fecha Documento</label>
                                    </div>
                                </div>
                                <div class="col-sm-4" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <input <?= $disabled; ?> type="date" class="form-control" id="datecont" value="<?= ($newVoucher) ? $hoy  : $ctbmovdata[0]['feccnt']; ?>">
                                        <label class="text-primary" for="datecont">Fecha Contable</label>
                                    </div>
                                </div>

                                <div class="col-sm-4" id="section_codofi" style="display: <?= ($displayUserSection) ? 'none' : 'block' ?>;">
                                    <input type="text" hidden id="codofi" value="1212">
                                    <div class="form-floating mb-3">
                                        <select <?= $disabled; ?> class="form-select" id="id_agencia">
                                            <?php
                                            $agenciaActual = $ctbmovdata[0]['id_agencia'] ?? $_SESSION['id_agencia'];
                                            foreach ($agencias as $agencia) {
                                                $selec = ($agencia['id_agencia'] == $agenciaActual) ? ' selected' : '';
                                                echo sprintf(
                                                    '<option %s value="%s">%s</option>',
                                                    $selec,
                                                    htmlspecialchars($agencia['id_agencia'], ENT_QUOTES, 'UTF-8'),
                                                    htmlspecialchars($agencia['nom_agencia'], ENT_QUOTES, 'UTF-8')
                                                );
                                            }
                                            ?>
                                        </select>
                                        <label class="text-primary" for="id_agencia">Agencia</label>
                                    </div>
                                </div>
                                <div class="col-sm-4" id="section_usuario_asignado" style="display: <?= ($displayUserSection) ? 'block' : 'none' ?>;">
                                    <div class="form-floating mb-3">
                                        <select <?= $disabled; ?> class="form-select" id="id_usuario_asignado">
                                            <?php
                                            $userActual = $ctbmovdata[0]['id_tb_usu'] ?? $idusuario;
                                            foreach ($usuarios as $usuario) {
                                                $selec = ($usuario['id_usu'] == $userActual) ? ' selected' : '';
                                                echo sprintf(
                                                    '<option %s value="%s">%s</option>',
                                                    $selec,
                                                    htmlspecialchars($usuario['id_usu'], ENT_QUOTES, 'UTF-8'),
                                                    htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido'], ENT_QUOTES, 'UTF-8')
                                                );
                                            }
                                            ?>
                                        </select>
                                        <label class="text-primary" for="id_usuario_asignado">Usuario Asignado</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <input <?= $disabled; ?> type="number" class="form-control" id="cantidad" value="<?= ($newVoucher) ? '0' : $ctbmovdata[0]['monchq']; ?>" onchange="cantidad_a_letras()" step="0.01">
                                        <label class="text-primary" for="cantidad">Cantidad</label>
                                    </div>
                                </div>
                                <div class="col-sm-4" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <select <?= $disabled; ?> class="form-select" id="negociable">
                                            <?php if (!$newVoucher) { ?>
                                                <option <?php if ($ctbmovdata[0]['modocheque'] == 0) echo 'selected'; ?> value="0">No Negociable</option>
                                                <option <?php if ($ctbmovdata[0]['modocheque'] == 1) echo 'selected'; ?> value="1">Negociable</option>
                                            <?php } else { ?>
                                                <option selected value="0">No Negociable</option>
                                                <option value="1">Negociable</option>
                                            <?php } ?>
                                        </select>
                                        <label class="text-primary" for="negociable">Tipo cheque</label>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input <?= $disabled; ?> type="text" class="form-control" id="numdoc" value="<?= ($newVoucher) ? 'X' : $ctbmovdata[0]['numdoc']; ?>">
                                        <label class="text-primary" for="numdoc">No. de Documento</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <div class="form-floating">
                                        <input <?= $disabled; ?> type="text" class="form-control" id="paguese" value="<?= ($newVoucher) ? '' : $ctbmovdata[0]['nomchq']; ?>">
                                        <label for="paguese">Paguese a la orden de</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <div class="form-floating">
                                        <input disabled type="text" class="form-control" id="numletras">
                                        <label for="numletras">La suma de (Q)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <select <?= $disabled; ?> class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                            <?php
                                            foreach ($bancos as $banco) {
                                                $selec = ($banco['id'] == $ctbmovdata[0]['id_banco']) ? 'selected' : '';
                                                echo sprintf(
                                                    '<option %s value="%s">%s - %s</option>',
                                                    $selec,
                                                    htmlspecialchars($banco['id'], ENT_QUOTES, 'UTF-8'),
                                                    htmlspecialchars($banco['id'], ENT_QUOTES, 'UTF-8'),
                                                    htmlspecialchars($banco['nombre'], ENT_QUOTES, 'UTF-8')
                                                );
                                            }
                                            ?>
                                        </select>
                                        <label class="text-primary" for="bancoid">Banco</label>
                                    </div>
                                </div>
                                <div class="col-sm-4" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <input disabled hidden type="text" class="form-control" id="id_cuenta_b" value="<?= ($newVoucher) ? '' : $ctbmovdata[0]['id_cuenta_banco']; ?>">
                                        <select <?= $disabled; ?> class="form-select" id="cuentaid" onchange="cheque_automatico(this.value,0)">
                                            <option value="">Seleccione una cuenta</option>
                                        </select>
                                        <label class="text-primary" for="cuentaid">No. de Cuenta</label>
                                    </div>
                                </div>
                                <div class="col-sm-4 mb-3">
                                    <div class="form-floating">
                                        <input <?= $disabled; ?> type="number" class="form-control" id="numcheque" value="<?= ($newVoucher) ? '0' : $ctbmovdata[0]['numchq']; ?>">
                                        <label class="text-primary" for="numcheque">No. de Cheque</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mb-2">
                                    <div class="form-floating">
                                        <textarea <?= $disabled; ?> class="form-control" id="glosa" style="height: 100px" rows="1"><?= ($newVoucher) ? '' : ($ctbmovdata[0]["glosa"]); ?></textarea>
                                        <label for="glosa">Concepto</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort" id="ladopoliza">
                            <div class="row">
                                <table id="Cuentas" class="display mb-2" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="width:1%;">No.</th>
                                            <th style="width:0.01%;"></th>
                                            <th>Cuenta</th>
                                            <th style="width:25%;">Debe</th>
                                            <th style="width:25%;">Haber</th>
                                            <th style="width:20%;">Fondos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (!$newVoucher) {
                                            $it = 0;
                                            $debe = 0;
                                            $haber = 0;
                                            while ($it < count($ctbmovdata)) {
                                                $incuentas = '<div class="input-group"><input style="display:none;" type="text" class="form-control" id="idcuenta' . ($it + 1) . '" value="' . $ctbmovdata[$it]["id_ctb_nomenclatura"] . '"><input type="text" disabled style="font-size: 0.9rem;" readonly class="form-control" id="cuenta'  . ($it + 1) .  '" value="' . $ctbmovdata[$it]["ccodcta"] . '"><button disabled class="btn btn-outline-success" type="button" onclick="abrir_modal(`#modal_nomenclatura`, `#id_modal_hidden`, `idcuenta'  . ($it + 1) . ',cuenta'  . ($it + 1) .  '/A,A//#/#/#/#`)" title="Buscar Cuenta contable"><i class="fa fa-magnifying-glass"></i></button></div>';
                                                $ind = '<div class="input-group"><span class="input-group-text">Q</span><input disabled style="text-align: right; font-size: 0.9rem;" type="number" step="0.01" class="form-control" id="debe' . ($it + 1) . '" onblur="validadh(this.id,this.value)" value="' . $ctbmovdata[$it]["debe"] . '"></div>';
                                                $inh = '<div class="input-group"><span class="input-group-text">Q</span><input disabled style="text-align: right; font-size: 0.9rem;" type="number" step="0.01" class="form-control" id="habe' . ($it + 1) . '" onblur="validadh(this.id,this.value)" value="' . $ctbmovdata[$it]["haber"] . '"></div>';
                                                $debe = $debe + $ctbmovdata[$it]["debe"];
                                                $haber = $haber + $ctbmovdata[$it]["haber"];
                                                $selectfondo = '<select class="form-select" disabled id="fondoid' . ($it + 1) . '">';
                                                $k = 0;
                                                while ($k < count($fondoselect)) {
                                                    $selec = ($fondoselect[$k]['id'] == $ctbmovdata[$it]['id_fuente_fondo']) ? 'selected' : '';
                                                    $selectfondo .= '<option ' . $selec . ' value="' . $fondoselect[$k]['id'] . '">' . $fondoselect[$k]['descripcion'] . '</option>';
                                                    $k++;
                                                }
                                                $selectfondo .= '</select>';
                                                echo '<tr style="font-size: 0.9rem;">';
                                                echo '<td class="ps-0">' . ($it + 1) . '</td>';
                                                echo '<td class="ps-0">' . ($it + 1) . '</td>';
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
                                            <button <?= $disabled; ?> id="addRow" class="btn btn-outline-primary" title="Añadir nueva fila" onclick="newrow3()"><i class="fa-solid fa-plus"></i></button>
                                            <button <?= $disabled; ?> id="deletefila" class="btn btn-outline-danger" title="Eliminar fila" onclick="deletefila()"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                        <div class="col-sm-4 mb-2 ps-0">
                                            <div class="input-group" style="width: 88%;float: right;">
                                                <span class="input-group-text">Q</span>
                                                <input id="totdebe" style="text-align: right;" type="number" step="0.01" class="form-control" readonly value="<?= ($newVoucher) ? '' : $debe; ?>">
                                            </div>
                                        </div>
                                        <div class="col-sm-4 mb-2 ps-0">
                                            <div class="input-group" style="width: 87%;float: left;">
                                                <span class="input-group-text">Q</span>
                                                <input id="tothaber" style="text-align: right;" type="number" step="0.01" class="form-control" readonly value="<?= ($newVoucher) ? '' : $haber; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        if (!$newVoucher) {
                            if ($ctbmovdata[0]['numchq'] == "") { ?>
                                <div class=" contenedort container">
                                    <div class="row">
                                        <div class="col mt-1 mb-1">
                                            <div class="alert alert-success" role="alert" style="margin-bottom: 0px !important;" id="mensaje">
                                                <h4 class="alert-heading">IMPORTANTE!</h4>
                                                <p>Debe presionar el boton de modificar, luego digitar un número de cheque y documento, seguidamente presionar el boton actualizar, esperar a que se graben los cambios, luego le aparecera el boton de imprimir y asi terminar con el proceso</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php }
                        }; ?>
                        <div class="row justify-items-md-center" id="ladopoliza">
                            <div class="col align-items-center" id="btns_footer">
                                <br>
                                <?php
                                if ($newVoucher) {
                                    echo '<button type="button" class="btn btn-outline-success" onclick="savecom(' . $idusuario . ',`create_cheques`,0)">
                                            <i class="fa fa-floppy-disk"></i> Guardar
                                        </button>';
                                } else {
                                    if ($ctbmovdata[0]['emitido'] != 2) {
                                        echo '<button id="modpol" type="button" title="Modificar datos de la Poliza" class="btn btn-outline-primary" onclick="changedisabled(`#ladopoliza *`,1);habilitar_deshabilitar([`datedoc`,`datecont`,`cantidad`,`numdoc`,`paguese`,`bancoid`,`cuentaid`,`numcheque`,`glosa`,`negociable`,`id_agencia`,`id_usuario_asignado`], []);changedisabled(`#btns_footer .btn-outline-primary`,0);changedisabled(`#deletepol`,0);">
                                                <i class="fa fa-pen"></i> Modificar
                                            </button>';
                                        echo '<button disabled type="button" title="Guardar cambios modificados de la poliza" class="btn btn-outline-success" onclick="savecom(' . $idusuario . ',`update_cheques`,' . $idCtbDiario . ')">
                                                <i class="fa fa-floppy-disk"></i> Actualizar
                                            </button>';
                                    }
                                    if (($ctbmovdata[0]['emitido'] == 0 && $ctbmovdata[0]['numchq'] != "") || $permisoReimpresionCheque) {
                                        echo '<button id="printpol" type="button" title="Imprimir voucher" class="btn btn-outline-primary" onclick="reportes([[],[],[],[' . $idCtbDiario . ']], `pdf`, 13,0,1)">
                                                <i class="fa fa-print"></i>Imprimir
                                            </button>';
                                    }
                                    if ($ctbmovdata[0]['emitido'] < 2) {
                                        echo '<button type="button" title="Anular un cheque" class="btn btn-outline-secondary" onclick="obtiene([], [], [], `anular_cheques`, `0`, [' . $idCtbDiario . '],`NULL`, `¿Está seguro de anular éste cheque?`)">
                                                    <i class="fa fa-floppy-disk"></i> Anular
                                                </button>';
                                    }
                                    echo '<button id="deletepol" type="button" title="Eliminar Poliza seleccionada" class="btn btn-outline-danger" onclick="obtiene([], [], [], `delete_cheques`, `0`, [' . $idCtbDiario . '],`NULL`, `¿Está seguro de eliminar éste cheque?`)">
                                            <i class="fa fa-trash"></i>Eliminar
                                        </button>';
                                }
                                ?>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv3('section_cheques', '#contenedor_section','0'); reinicio(0)">
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
                if (!$newVoucher) {
                    echo 'reinicio(' . count($ctbmovdata) . ');';
                }
                ?>
                //ejecutar busqueda de cuentas
                buscar_cuentas();
                //convertir a letras
                cantidad_a_letras();
            });

            function newrow3() {
                newrow2(<?php echo json_encode($fondoselect); ?>);
            }
        </script>
    <?php
                break;
            case 'deposito_bancos':
    ?>
        <input type="text" id="condi" value="deposito_bancos" hidden>
        <input type="text" id="file" value="ban001" hidden>
        <div class="text" style="text-align:center">DEPOSITO A BANCOS</div>
        <div class="card">
            <div class="card-header bg-primary bg-gradient">Depositos a Bancos</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4">
                        <div id="list-example" class="h-100 flex-column align-items-stretch pe-4 border-end">
                            <div class="table-responsive">
                                <table class="table nowrap" id="tb_depositos" style="width: 100% !important;">
                                    <thead>
                                        <tr style="font-size: 0.7rem;">
                                            <th>Poliza</th>
                                            <th>Fecha</th>
                                            <th>Monto</th>
                                            <th>Acc.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider" style="font-size: 0.6rem !important;">

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div id="contenedor_section" class="col-8" style="padding-left: 0px !important; padding-right: 7px !important;">

                    </div>

                    <script>
                        var table_cheques_aux2;
                        $(document).ready(function() {
                            printdiv3('section_partidas_deposito', '#contenedor_section', '0');
                            table_cheques_aux2 = $('#tb_depositos').on('search.dt').DataTable({
                                "processing": true,
                                "serverSide": true,
                                "sAjaxSource": "../src/server_side/list_depositos_bancos.php",
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
                                        data: [0],
                                        render: function(data, type, row) {
                                            return `<button type="button" class="btn btn-outline-success btn-sm" onclick="printdiv3('section_partidas_deposito', '#contenedor_section','${data}')" ><i class="fa-regular fa-eye"></i></i></button>`;
                                        }
                                    },
                                ],
                                "fnServerParams": function(aoData) {
                                    //PARAMETROS EXTRAS QUE SE LE PUEDEN ENVIAR AL SERVER ASIDE
                                    aoData.push({
                                        "name": "whereextra",
                                        "value": "id_agencia!=0"
                                    });
                                },
                                // "bDestroy": true,
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
                        })
                    </script>

                <?php
                break;

            case 'section_partidas_deposito':
                $idCtbDiario = $_POST["xtra"];
                $newVoucher = true;
                $query = "SELECT cd.id,cd.id_ctb_tipopoliza, cd.numcom, cd.fecdoc, cd.feccnt, cd.id_agencia, cm.id_fuente_fondo,cd.numdoc, 
                                cd.glosa, cm.id_ctb_nomenclatura, cn.ccodcta, cm.debe, cm.haber, IFNULL(cbm.destino, '') AS destino
                            FROM ctb_diario cd
                            INNER JOIN ctb_mov cm ON cd.id=cm.id_ctb_diario
                            INNER JOIN ctb_nomenclatura cn ON cm.id_ctb_nomenclatura=cn.id
                            LEFT JOIN ctb_ban_mov cbm ON cd.id=cbm.id_ctb_diario
                            WHERE cd.estado='1' AND cd.id=? ORDER BY cm.haber,cm.id_ctb_nomenclatura";

                $showmensaje = false;
                try {
                    /**
                     * ABRIR LA CONEXION GENERAL
                     */

                    $database->openConnection(2);

                    $tipoPoliza = $database->selectColumns("ctb_tipo_poliza", ['id', 'descripcion'], 'id IN (10,11,12,14)');
                    if (empty($tipoPoliza)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron tipos de poliza disponibles.");
                    }

                    $database->closeConnection();

                    /**
                     * ABRIR LA CONEXION PRINCIPAL
                     */
                    $database->openConnection();
                    $datafondos = $database->selectColumns('ctb_fuente_fondos', ['id', 'descripcion'], 'estado=1');
                    if (empty($datafondos)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron fondos disponibles.");
                    }

                    $bancos = $database->selectColumns('tb_bancos', ['id', 'nombre'], 'estado=1');
                    if (empty($bancos)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron bancos disponibles.");
                    }

                    $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia']);
                    if (empty($agencias)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron agencias disponibles.");
                    }

                    // Log::info("Se cargaron los bancos disponibles correctamente.", $bancos);

                    if ($idCtbDiario == '0') {
                        $showmensaje = true;
                        throw new Exception("Complete los campos para generar la nueva transaccion.");
                    }
                    $newVoucher = false;

                    $ctbmovdata = $database->getAllResults($query, [$idCtbDiario]);
                    if (empty($ctbmovdata)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron datos para la poliza seleccionada.");
                    }

                    // Log::info("Se cargaron los datos de la poliza correctamente.", $ctbmovdata);

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

                $disabled = ($newVoucher) ? '' : ' disabled ';

                ?>
                    <div class="scrollspy-example-2" tabindex="0">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>!!</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>
                        <div class="container contenedort">
                            <div class="row">
                                <div class="col mb-2">
                                    <?php if (!$newVoucher) {
                                        echo '<div class="row">
                                                    <label class="text-success">Poliza No. ' . $ctbmovdata[0]['numcom'] . ' </label>
                                              </div>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-3" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <input <?= $disabled; ?> type="date" class="form-control" id="datedoc" value="<?= ($newVoucher) ? $hoy : $ctbmovdata[0]["fecdoc"]; ?>">
                                        <label class="text-primary" for="datedoc">Fecha Documento</label>
                                    </div>
                                </div>
                                <div class="col-sm-3" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <input <?= $disabled; ?> type="date" class="form-control" id="datecont" value="<?= ($newVoucher) ? $hoy : $ctbmovdata[0]['feccnt']; ?>">
                                        <label class="text-primary" for="datecont">Fecha Contable</label>
                                    </div>
                                </div>
                                <div class="col-sm-3" style="padding-right: 0px !important;">
                                    <div class="form-floating mb-3">
                                        <select <?= $disabled; ?> class="form-select" id="id_agencia">
                                            <?php
                                            $agenciaActual = $ctbmovdata[0]['id_agencia'] ?? $_SESSION['id_agencia'];
                                            foreach ($agencias as $agencia) {
                                                $selec = ($agencia['id_agencia'] == $agenciaActual) ? ' selected' : '';
                                                echo sprintf(
                                                    '<option %s value="%s">%s</option>',
                                                    $selec,
                                                    htmlspecialchars($agencia['id_agencia'], ENT_QUOTES, 'UTF-8'),
                                                    htmlspecialchars($agencia['nom_agencia'], ENT_QUOTES, 'UTF-8')
                                                );
                                            }
                                            ?>
                                        </select>
                                        <label class="text-primary" for="id_agencia">Agencia</label>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-floating mb-2">
                                        <select <?= $disabled; ?> class="form-select" id="idtipo_poliza">
                                            <?php
                                            foreach ($tipoPoliza as $tipo) {
                                                $selec = ($tipo['id'] == $ctbmovdata[0]['id_ctb_tipopoliza']) ? ' selected' : '';
                                                echo sprintf(
                                                    '<option %s value="%s">%s</option>',
                                                    $selec,
                                                    htmlspecialchars($tipo['id'], ENT_QUOTES, 'UTF-8'),
                                                    htmlspecialchars($tipo['descripcion'], ENT_QUOTES, 'UTF-8')
                                                );
                                            }
                                            ?>
                                        </select>
                                        <label class="text-primary" for="idtipo_poliza">Tipo de Poliza</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-9 mb-2">
                                    <div class="form-floating">
                                        <input <?= $disabled; ?> type="text" class="form-control" id="destino" value="<?= ($newVoucher) ? '' : $ctbmovdata[0]['destino']; ?>">
                                        <label for="destino">Destino (opcional)</label>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <input <?= $disabled; ?> type="text" class="form-control" id="numdoc" value="<?= ($newVoucher) ? 'X' : $ctbmovdata[0]['numdoc']; ?>">
                                        <label class="text-primary" for="numdoc">No. Documento</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mb-2">
                                    <div class="form-floating">
                                        <textarea <?= $disabled; ?> class="form-control" id="glosa" style="height: 100px" rows="1"><?= ($newVoucher) ? '' : ($ctbmovdata[0]["glosa"]); ?></textarea>
                                        <label for="glosa">Concepto</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort" id="ladopoliza">
                            <div class="row">
                                <table id="Cuentas" class="display mb-2" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="width:1%;">No</th>
                                            <th style="width:0.01%;"></th>
                                            <th>Cuenta</th>
                                            <th style="width:25%;">Debe</th>
                                            <th style="width:25%;">Haber</th>
                                            <th style="width:20%;">Fondo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php

                                        if (!$newVoucher) {
                                            $it = 0;
                                            $debe = 0;
                                            $haber = 0;
                                            while ($it < count($ctbmovdata)) {
                                                $incuentas = '<div class="input-group"><input style="display:none;" type="text" class="form-control" id="idcuenta' . ($it + 1) . '" value="' . $ctbmovdata[$it]["id_ctb_nomenclatura"] . '"><input type="text" disabled style="font-size: 0.9rem;" readonly class="form-control" id="cuenta'  . ($it + 1) .  '" value="' . $ctbmovdata[$it]["ccodcta"] . '"><button disabled class="btn btn-outline-success" type="button" onclick="abrir_modal(`#modal_nomenclatura`, `#id_modal_hidden`, `idcuenta'  . ($it + 1) . ',cuenta'  . ($it + 1) .  '/A,A//#/#/#/#`)" title="Buscar Cuenta contable"><i class="fa fa-magnifying-glass"></i></button></div>';
                                                $ind = '<div class="input-group"><span class="input-group-text">Q</span><input disabled style="text-align: right; font-size: 0.9rem;" type="number" step="0.01" class="form-control" id="debe' . ($it + 1) . '" onblur="validadh(this.id,this.value)" value="' . $ctbmovdata[$it]["debe"] . '"></div>';
                                                $inh = '<div class="input-group"><span class="input-group-text">Q</span><input disabled style="text-align: right; font-size: 0.9rem;" type="number" step="0.01" class="form-control" id="habe' . ($it + 1) . '" onblur="validadh(this.id,this.value)" value="' . $ctbmovdata[$it]["haber"] . '"></div>';
                                                $debe = $debe + $ctbmovdata[$it]["debe"];
                                                $haber = $haber + $ctbmovdata[$it]["haber"];
                                                $fondoselect = '<select class="form-select" disabled id="fondoid' . ($it + 1) . '">';
                                                $k = 0;
                                                while ($k < count($datafondos)) {
                                                    $selec = ($datafondos[$k]['id'] == $ctbmovdata[$it]['id_fuente_fondo']) ? 'selected' : '';
                                                    $fondoselect .= '<option ' . $selec . ' value="' . $datafondos[$k]['id'] . '">' . $datafondos[$k]['descripcion'] . '</option>';
                                                    $k++;
                                                }
                                                $fondoselect .= '</select>';
                                                echo '<tr style="font-size: 0.9rem;">';
                                                echo '<td class="ps-0">' . ($it + 1) . '</td>';
                                                echo '<td class="ps-0">' . ($it + 1) . '</td>';
                                                echo '<td class="ps-0">' . $incuentas . '</td>';
                                                echo '<td class="ps-0">' . $ind . '</td>';
                                                echo '<td class="ps-0">' . $inh . '</td>';
                                                echo '<td class="ps-0">' . $fondoselect . '</td>';
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
                                            <button <?= $disabled; ?> id="addRow" class="btn btn-outline-primary" title="Añadir nueva fila" onclick="nuevafila()"><i class="fa-solid fa-plus"></i></button>
                                            <button <?= $disabled; ?> id="deletefila" class="btn btn-outline-danger" title="Eliminar fila" onclick="deletefila()"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                        <div class="col-sm-4 mb-2 ps-0">
                                            <div class="input-group" style="width: 88%;float: right;">
                                                <span class="input-group-text">Q</span>
                                                <input id="totdebe" style="text-align: right;" type="number" step="0.01" class="form-control" readonly value="<?= ($newVoucher) ? '' : $debe; ?>">
                                            </div>
                                        </div>
                                        <div class="col-sm-4 mb-2 ps-0">
                                            <div class="input-group" style="width: 87%;float: left;">
                                                <span class="input-group-text">Q</span>
                                                <input id="tothaber" style="text-align: right;" type="number" step="0.01" class="form-control" readonly value="<?= ($newVoucher) ? '' : $haber; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="row justify-items-md-center" id="ladopoliza">
                            <div class="col align-items-center" id="btns_footer">
                                <br>
                                <?php
                                if ($newVoucher) {
                                    echo '<button type="button" class="btn btn-outline-success" onclick="savecomdeposito(`create_depositos_bancos`,0)">
                                                <i class="fa fa-floppy-disk"></i> Guardar
                                            </button>';
                                } else {
                                    echo '<button id="modpol" type="button" title="Modificar datos de la Poliza" class="btn btn-outline-primary" onclick="changedisabled(`#ladopoliza *`,1);habilitar_deshabilitar([`datedoc`,`datecont`,`numdoc`,`glosa`,`idtipo_poliza`,`id_agencia`,`destino`], []);changedisabled(`#btns_footer .btn-outline-primary`,0);changedisabled(`#deletepol`,0);">
                                                <i class="fa fa-pen"></i> Modificar
                                            </button>';
                                    echo '<button disabled type="button" title="Guardar cambios modificados de la poliza" class="btn btn-outline-success" onclick="savecomdeposito(`update_depositos_bancos`,' . $idCtbDiario . ')">
                                                <i class="fa fa-floppy-disk"></i> Actualizar
                                            </button>';
                                    echo '<button id="printpol" type="button" title="Imprimir datos de la Poliza" class="btn btn-outline-primary" onclick="reportes([[],[],[],[' . $idCtbDiario . ']], `pdf`, `../../conta/reportes/partida_diario`,0)">
                                                <i class="fa fa-print"></i> Imprimir
                                            </button>';
                                    echo '<button id="deletepol" type="button" title="Eliminar Poliza seleccionada" class="btn btn-outline-danger" onclick="eliminar(' . $idCtbDiario . ', `crud_bancos`, 0, `delete_depositos_bancos`)">
                                                <i class="fa fa-trash"></i>Eliminar
                                            </button>';
                                }
                                ?>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv3('section_partidas_deposito', '#contenedor_section','0'); reinicio(0)">
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
                if (!$newVoucher) {
                    echo 'reinicio(' . count($ctbmovdata) . ');';
                }
                ?>
            });
            var dataf = <?php echo json_encode($datafondos); ?>;

            function nuevafila() {
                newrow2(dataf);
                $("#Cuentas tr td").addClass("ps-0");
            }

            function savecomdeposito(condio, idr) {
                loaderefect(1)
                if (validanewrow() == 0) {
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'Hay registros sin completarse, verique que se hayan ingresado montos y se hayan seleccionado cuentas.'
                    })
                    loaderefect(0);
                    return;
                }
                var datainputsd = [''];
                var datainputsh = [''];
                var datacuentas = [''];
                var datafondos = [''];
                var datainputs = [];
                var dataselects = [];
                var rows = 1;
                var fila = 0;
                var pibo = 0;
                while (rows <= countid) {
                    var mm = datoseliminados.includes(rows);
                    if (mm == false) {
                        pibo = getinputsval(['debe' + (rows), 'habe' + (rows), 'idcuenta' + (rows), 'fondoid' + (rows)]);
                        datainputsd[fila] = (pibo[0] == "") ? 0 : pibo[0];
                        datainputsh[fila] = (pibo[1] == "") ? 0 : pibo[1];
                        datacuentas[fila] = pibo[2];
                        datafondos[fila] = pibo[3];
                        fila++;
                    }
                    rows++;
                }
                datainputs = getinputsval(['datedoc', 'datecont', 'numdoc', 'glosa', 'totdebe', 'tothaber', 'idtipo_poliza', 'id_agencia', 'destino']);
                generico([datainputs, datainputsd, datainputsh, datacuentas, datafondos], [], [], condio, idr, [idr]);
            }
        </script>
<?php
                break;
        }
