<?php
session_start();
$usuario = $_SESSION["id"];
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");
include '../../../includes/BD_con/db_con.php';
include '../cre_grupo/functions/group_functions.php';
include_once "../../../src/cris_modales/mdls_editReciboCreGrupo.php";

mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

include '../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);


$condi = $_POST["condi"];
switch ($condi) {
    case 'recibosgrupales':
        $codusu = $_SESSION['id'];
        $where = "";
        $mensaje_error = "";
        $bandera_error = false;
        //Validar si ya existe un registro igual que el nombre
        $nuew = "ccodusu='$codusu' AND (dfecsis BETWEEN '" . date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days')) . "' AND  '" . date('Y-m-d') . "')";
        try {
            $stmt = $conexion->prepare("SELECT IF(tu.puesto='ADM' OR tu.puesto='GER', '1=1', ?) AS valor FROM tb_usuario tu WHERE tu.id_usu = ?");
            if (!$stmt) {
                throw new Exception("Error en la consulta: " . $conexion->error);
            }
            $stmt->bind_param("ss", $nuew, $usuario);
            if (!$stmt->execute()) {
                throw new Exception("Error al consultar: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $whereaux = $result->fetch_assoc();
            $where = $whereaux['valor'];
            // if ($usuario=='27') { //--REQ--fape--3--Permisos fape para un usuario especial
            // 	$where='1=1';
            // }
        } catch (Exception $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            $bandera_error = true;
        }
    ?>
        <input type="text" id="file" value="caja003" style="display: none;">
        <input type="text" id="condi" value="recibosgrupales" style="display: none;">
        <div class="text" style="text-align:center">RECIBOS DE CREDITOS</div>
        <div class="card">
            <div class="card-header">RECIBOS DE CREDITOS</div>
            <div class="card-body">
                <?php if ($bandera_error) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡Error!</strong> <?= $mensaje_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <!-- tabla -->
                <div class="container contenedort" style="padding: 10px 8px 10px 8px !important;">
                    <div class="table-responsive">
                        <table id="tabla_recibos_grupales" class="table table-hover table-border nowrap" style="width:100%">
                            <thead class="text-light table-head-aho" style="font-size: 0.8rem;">
                                <tr>
                                    <!-- <th>No.</th> -->
                                    <th>Nombre Grupo</th>
                                    <th>Ciclo</th>
                                    <th>No. Recibo</th>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                $(document).ready(function() {
                    $("#tabla_recibos_grupales").DataTable({
                        "processing": true,
                        "serverSide": true,
                        "sAjaxSource": "../../src/server_side/recibo_credito_grupales.php",
                        columns: [{
                                data: [4]
                            },
                            {
                                data: [6]
                            },
                            {
                                data: [1]
                            },
                            {
                                data: [2]
                            },
                            {
                                data: [3]
                            },
                            {
                                data: [0], //Es la columna de la tabla
                                render: function(data, type, row) {
                                    imp = '';
                                    imp1 = '';
                                    imp2 = '';
                                    const separador = "||";
                                    var dataRow = row.join(separador);

                                    imp =
                                        `<button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="reportes([[], [], [], ['${row[5]}', '${row[1]}', '${row[6]}']], 'pdf', '15', 0,1)"><i class="fa-solid fa-print me-2"></i>Reimprimir</button>`;
                                    if (row[9] == "1") {
                                        imp1 =
                                            `<button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCreReGrup" onclick="capData('${dataRow}',['#idGru','#ciclo','#fecha', '#nomGrupo', '#codGrup', '#recibo', '#antRe'],[5,6,2,4,7,1,1]);inyecCod('#integrantes','reciboDeGrupos','${row[1]}||${row[5]}||${row[6]}')"><i class="fa-sharp fa-solid fa-pen-to-square"></i></button>`;
                                        imp2 =
                                            `<button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="eliminar('${row[1]}|*-*|${row[5]}|*-*|${row[6]}','eliReGru', '<?= $_SESSION['id']; ?>');"><i class="fa-solid fa-trash-can"></i></button>`;
                                    }
                                    return imp + imp1 + imp2;
                                }
                            },
                        ],
                        "fnServerParams": function(aoData) {
                            //PARAMETROS EXTRAS QUE SE LE PUEDEN ENVIAR AL SERVER ASIDE
                            aoData.push({
                                "name": "whereextra",
                                "value": "<?= $where; ?>"
                            });
                        },
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
                });
            </script>
        </div>
    <?php
        break;
    case 'pagosespeciales':
        $xtra = $_POST["xtra"];
        // $xtra = "0020040100000002";
        $codusu = $_SESSION["id"];
        $id_agencia = $_SESSION['id_agencia'];

        $query = "SELECT cl.short_name AS nombrecli, cl.idcod_cliente AS codcli, ag.cod_agenc AS codagencia, cm.CCODPRD AS codprod, cm.CCODCTA AS ccodcta, cm.MonSug AS monsug, cm.NIntApro AS interes, cm.DFecDsbls AS fecdesembolso, cm.noPeriodo AS cuotas, ce.Credito AS tipocred, per.nombre AS nomper,
        ((cm.MonSug)-(SELECT IFNULL(SUM(ck.KP),0) FROM CREDKAR ck WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA=cm.CCODCTA)) AS saldocap,
        ((SELECT IFNULL(SUM(nintere),0) FROM Cre_ppg WHERE ccodcta=cm.CCODCTA)-(SELECT IFNULL(SUM(ck.INTERES),0) FROM CREDKAR ck WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA=cm.CCODCTA)) AS saldoint,
        prod.id_fondo, cl.url_img AS urlfoto
        FROM cremcre_meta cm
        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
        INNER JOIN tb_agencia ag ON cm.CODAgencia=ag.cod_agenc
        INNER JOIN cre_productos prod ON prod.id=cm.CCODPRD
        INNER JOIN $db_name_general.tb_credito ce ON cm.CtipCre=ce.abre
        INNER JOIN $db_name_general.tb_periodo per ON cm.NtipPerC=per.periodo
        WHERE cm.Cestado='J' AND cm.TipoEnti='INDI' AND cm.CCODCTA=?
        GROUP BY cm.CCODCTA";

        $query2 = "SELECT cpg.Id_ppg AS id, cpg.dfecven, IF((timestampdiff(DAY,cpg.dfecven,?))<0, 0,(timestampdiff(DAY,cpg.dfecven,?))) AS diasatraso,
        cpg.cestado, cpg.cnrocuo AS numcuota, cpg.ncappag AS capital, cpg.nintpag AS interes, cpg.nmorpag AS mora, 
        cpg.OtrosPagosPag AS otrospagos
        FROM Cre_ppg cpg
        WHERE cpg.cestado='X' AND cpg.ccodcta=?
        ORDER BY cpg.ccodcta, cpg.dfecven, cpg.cnrocuo";

        $showmensaje = false;
        try {
            $database->openConnection();
            $datos = $database->getAllResults($query, [$xtra]);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta porfavor");
            }
            $datoscreppg = $database->getAllResults($query2, [$hoy, $hoy, $xtra]);
            if (empty($datoscreppg)) {
                $showmensaje = true;
                throw new Exception("No hay cuotas pendientes");
            }
            $status = 1;
        } catch (Exception $e) {
            // if (!$showmensaje) {
            //     $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            // }
            // $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            // $status = 0;

            $mensaje_error = $e->getMessage();
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if(!$status){
            echo $mensaje_error;
        }
        $saldocapital = ((!empty($datos)) && $datos[0]['saldocap']) > 0 ? number_format($datos[0]['saldocap'], 2) : 0;
        $saldointeres = ((!empty($datos)) && $datos[0]['saldoint']) > 0 ? number_format($datos[0]['saldoint'], 2) : 0;


        //ORDENAR ARRAYS PARA LA IMPRESION DE DATOS
        $cuotasvencidas = ($status) ? array_filter($datoscreppg, function ($sk) {
            return $sk['diasatraso'] > 0;
        }) : [];
        //FILTRAR UN SOLO REGISTRO NO PAGADO
        $cuotasnopagadas = ($status) ? array_filter($datoscreppg, function ($sk) {
            return $sk['diasatraso'] == 0;
        }) : [];

        //SECCION DE REESTRUCTURACION 
        unset($datoscreppg);
        $datoscreppg[] = [];
        $sumacap = 0;
        $sumainteres = 0;
        $sumamora = 0;
        $sumaotrospagos = 0;
        $j = 0;
        //OBTIENE CUOTA VENCIDAS SI HUBIERAN
        if (count($cuotasvencidas) != 0) {
            for ($i = $j; $i < count($cuotasvencidas); $i++) {
                $datoscreppg[$i] = $cuotasvencidas[$i];
                $j++;
            }
        }
        //TRAE LOS PAGOS A LA FECHA SI HUBIERAN Y SINO TRAE LA SIGUIENTE EN CASO DE QUE NO HAYAN CUOTAS VENCIDAS
        if (count($cuotasnopagadas) != 0) {
            for ($i = $j; $i < count($cuotasnopagadas); $i++) {
                if ($cuotasnopagadas[$i]['dfecven'] <= $hoy2) {
                    $datoscreppg[$j] = $cuotasnopagadas[$j];
                    $i = 2000;
                    $j++;
                } else {
                    if (count($cuotasvencidas) == 0) {
                        $datoscreppg[$j] = $cuotasnopagadas[$j];
                        $i = 2000;
                        $j++;
                    }
                }
            }
        }
        if (count($datoscreppg) != 0) {
            $sumacap = array_sum(array_column($datoscreppg, "capital"));
            $sumainteres = array_sum(array_column($datoscreppg, "interes"));
            $sumamora = array_sum(array_column($datoscreppg, "mora"));
            $sumaotrospagos = array_sum(array_column($datoscreppg, "otrospagos"));
            $sumafilas = $sumacap + $sumainteres + $sumamora + $sumaotrospagos;
        }

        $concepto = ($status) ? "PAGO DE CRÉDITO A NOMBRE DE " . strtoupper($datos[0]['nombrecli']) : " ";
    ?>
        <input type="text" id="condi" value="pagosespeciales" hidden>
        <input type="text" id="file" value="caja003" hidden>

        <div class="text" style="text-align:center">PAGO DE CRÉDITO INDIVIDUAL</div>
        <div class="card">
            <div class="card-header">Pago de crédito Jurídico individual</div>
            <div class="card-body">

                <!-- seleccion de cliente y su credito-->
                <div class="container contenedort">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2"><b>Información de cliente y crédito</b></div>
                        </div>
                    </div>
                    <!-- usuario y boton buscar -->
                    <div class="row">
                        <div class="col-12 col-sm-12 col-md-12">
                            <div class="row">
                                <div class="col-12 col-sm-3 col-md-3">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="id_cod_cliente" placeholder="Código de cliente" readonly <?php if ($status) {
                                                                                                                                                    echo 'value="' . $datos[0]['codcli'] . '"';
                                                                                                                                                } ?>>
                                        <label for="id_cod_cliente">Código cliente</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-3">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="nomcli" placeholder="Nombre de cliente" readonly <?php if ($status) {
                                                                                                                                            echo 'value="' . $datos[0]['nombrecli'] . '"';
                                                                                                                                        } ?>>
                                        <label for="cliente">Nombre cliente</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="codcredito" placeholder="Codigo de crédito" readonly <?php if ($status) {
                                                                                                                                                echo 'value="' . $datos[0]['ccodcta'] . '"';
                                                                                                                                            } ?>>
                                        <label for="nomagencia">Código de crédito</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-3">
                                    <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12" data-bs-toggle="modal" data-bs-target="#modal_pagos_cre_individuales"><i class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito</button>
                                </div>
                            </div>
                            <!-- cargo, nombre agencia y codagencia  -->
                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="codagencia" placeholder="Código de agencia" readonly <?php if ($status) {
                                                                                                                                                echo 'value="' . $datos[0]['codagencia'] . '"';
                                                                                                                                            } ?>>
                                        <label for="codagencia">Código de agencia</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-12 col-md-3">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="codproducto" placeholder="Código de producto" readonly <?php if ($status) {
                                                                                                                                                echo 'value="' . $datos[0]['codprod'] . '"';
                                                                                                                                            } ?>>
                                        <label for="cargo">Codigo de producto</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12 col-md-3">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="interes" placeholder="Interes" readonly <?php if ($status) {
                                                                                                                                echo 'value="' . $datos[0]['interes'] . '"';
                                                                                                                            } ?>>
                                        <label for="gastos">Interes</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="cantcuotas" placeholder="Cantidad cuotas" readonly <?php if ($status) {
                                                                                                                                            echo 'value="' . $datos[0]['cuotas'] . '"';
                                                                                                                                        } ?>>
                                        <label for="desembolsar">Cantidad cuotas</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- cnumdoc, capital, gastos, total a desembolsar -->
                    <div class="row">

                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="ccapital" placeholder="Capital" readonly <?php if ($status) {
                                                                                                                            echo 'value="' . $datos[0]['monsug'] . '"';
                                                                                                                        } ?>>
                                <label for="ccapital">Capital</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="saldocap" placeholder="Saldo Capital" readonly <?php if ($status) {
                                                                                                                                echo 'value="' . $saldocapital . '"';
                                                                                                                            } ?>>
                                <label for="saldocap">Saldo capital</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="saldointeres" readonly <?php if ($status) {
                                                                                                        echo 'value="' . $saldointeres . '"';
                                                                                                    } ?>>
                                <label for="saldo interes">Saldo interés</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">

                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="fechadesembolso" placeholder="Fecha desembolso" readonly <?php if ($status) {
                                                                                                                                            echo 'value="' . $datos[0]['fecdesembolso'] . '"';
                                                                                                                                        } ?>>
                                <label for="desembolsar">Fecha desembolso</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="tipocredito" placeholder="Tipo de crédito" readonly <?php if ($status) {
                                                                                                                                    echo 'value="' . $datos[0]['tipocred'] . '"';
                                                                                                                                } ?>>
                                <label for="desembolsar">Tipo de crédito</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="tipoperiodo" placeholder="Tipo de periodo" readonly <?php if ($status) {
                                                                                                                                    echo 'value="' . $datos[0]['nomper'] . '"';
                                                                                                                                } ?>>
                                <label for="desembolsar">Tipo de periodo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($status) { ?>
                    <div class="container contenedort">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Detalle de boleta de pago</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="norecibo" placeholder="Número de recibo" value="">
                                    <label for="norecibo">No. Recibo o Boleta</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select id="metodoPago" name="metodoPago" aria-label="Default select example" onchange="showBTN()" class="form-select">
                                        <option value="1">Pago en Efectivo</option>
                                        <option value="2">Boleta de Banco</option>
                                    </select>
                                    <label for="metodoPago">Método de Pago:</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="date" class="form-control" id="fecpag" placeholder="Fecha de pago" value="<?= date('Y-m-d') ?>">
                                    <label for="fecpag">Fecha de pago:</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12"><span class="badge text-bg-primary">Verifique el concepto antes de guardar</span></div>
                            <div class="col-sm-12 mb-2">
                                <div class="form-floating">
                                    <textarea class="form-control" id="concepto" style="height: 100px" rows="1"><?= $concepto ?></textarea>
                                    <label for="concepto">Concepto</label>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- NEGROY AGREGAR BOLETAS DE PAGO ******************************************** -->
                    <div class="container contenedort">
                        <div class="row col">
                            <div class="text-center mb-2"><b>Forma de Pago </b></div>
                        </div>
                        <!-- fila de seleccion de bancos -->
                        <div class="row d-none mostrar">
                            <div class="col-sm-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                        <option value="F000" disabled selected>Seleccione un banco</option>
                                        <?php $bancos = mysqli_query($conexion, "SELECT * FROM tb_bancos WHERE estado='1'");
                                        while ($banco = mysqli_fetch_array($bancos)) {
                                            echo '<option value="' . $banco['id'] . '">' . $banco['id'] . "-" . $banco['nombre'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="bancoid">Banco</label>
                                </div>
                            </div>
                            <!-- id de cuenta para edicion --> <!-- select normal -->
                            <div class="col-sm-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="cuentaid">
                                        <option selected disabled value="F000">Seleccione una cuenta</option>
                                    </select>
                                    <label for="cuentaid">No. de Cuenta</label>
                                </div>
                            </div>
                        </div>
                        <!-- FECHA DE LA BOLETA  -->
                        <div class="row d-none mostrar">
                            <div class="col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="fecpagBANC" value="<?= date('Y-m-d') ?>">
                                    <label for="fecpag">Fecha Boleta:</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="noboletabanco" placeholder="Número de boleta de banco">
                                    <label for="noboletabanco">No. Boleta de Banco</label>
                                </div>
                            </div>
                            <div class="row d-none">
                                <input type="date" class="form-control" id="efectivo" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    </div>
                    <!-- NEGROY AGREGAR BOLETAS DE PAGO *FIN*  ******************************************** -->
                    <div class="container contenedort">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Pagos pendientes</b></div>
                            </div>
                        </div>
                    <?php } ?>
                    <!-- ES UN ROW POR CADA CUOTA -->
                    <div class="accordion" id="cuotas">
                        <?php
                        if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <div class="text-center">
                                    <strong class="me-2">¡Bienvenido!</strong>Debe seleccionar un crédito a pagar.
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>

                        <?php } else {
                            $variables = [["warning", "X vencer"], ["danger", "Vencida"], ["success", "Vigente"]];
                        ?>

                            <div class="row">
                                <div class="col mb-2">
                                    <div class="accordion-item">
                                        <!-- ENCABEZADO -->
                                        <h2 class="accordion-header">
                                            <button id="bt0" onclick="opencollapse(0)" style="--bs-bg-opacity: .2;" class="accordion-button collapsed bg-<?php if ($datoscreppg[0]['diasatraso'] > 0) {
                                                                                                                                                                echo 'danger';
                                                                                                                                                            } else {
                                                                                                                                                                echo 'success';
                                                                                                                                                            } ?>" data-bs-target="#collaps0" aria-expanded="false" aria-controls="collaps0">
                                                <div class="row" style="font-size: 0.80rem;">
                                                    <div class="col-sm-2">
                                                        <span class="input-group-addon">Capital</span>
                                                        <input id="capital0" disabled onclick="opencollapse(-1)" onchange="summon(this.id)" type="number" step="0.01" class="form-control habi form-control-sm" value="<?= $sumacap; ?>">
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <span class="input-group-addon">Interes</span>
                                                        <input id="interes0" disabled onclick="opencollapse(-1)" onchange="summon(this.id)" type="number" step="0.01" class="form-control habi form-control-sm" value="<?= $sumainteres; ?>">
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <span class="input-group-addon">Mora</span>
                                                        <input id="monmora0" disabled onclick="opencollapse(-1)" onchange="summon(this.id)" type="number" step="0.01" class="form-control habi form-control-sm" value="<?= $sumamora; ?>">
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <span class="input-group-addon">Otros</span>
                                                        <div class="input-group">
                                                            <input style="height: 10px !important;" id="otrospg0" disabled readonly onclick="opencollapse(-1);" onchange="summon(this.id)" type="number" step="0.01" class="form-control habi form-control-sm" value="<?= $sumaotrospagos; ?>">
                                                            <span id="lotrospg0" title="Modificar detalle otros" class="input-group-addon btn btn-link" data-bs-toggle="modal" data-bs-target="#modalgastos" onclick="opencollapse(-1);"><i class="fa-solid fa-pen-to-square"></i></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <label class="form-label">Total</label>
                                                        <input id="totalpg0" readonly onclick="opencollapse(-1)" onchange="summon(this.id)" type="number" step="0.01" class="form-control habi form-control-sm" value="<?= $sumafilas; ?>">
                                                    </div>
                                                    <div class="col-sm-1">
                                                        <div class="form-check form-switch">
                                                            <br>
                                                            <input class="form-check-input" onclick="opencollapse('s0')" type="checkbox" role="switch" id="s0" title="Modificar pago">
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>


                                        <!-- SECCION DE DETALLE DE UNA CUOTA -->
                                        <div id="collaps0" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                            <div class="accordion-body">
                                                <ul class="list-group">
                                                    <?php
                                                    for ($i = 0; $i < count($datoscreppg); $i++) {
                                                    ?>
                                                        <li class="list-group-item">
                                                            <div class="row" style="font-size: 0.80rem;">
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">No. Cuota</span>
                                                                        <span class="input-group-addon"><?= $datoscreppg[$i]['numcuota']; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Vencimiento:</span>
                                                                        <span class="input-group-addon"><?= date("d-m-Y", strtotime($datoscreppg[$i]['dfecven'])); ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Capital</span>
                                                                        <span class="input-group-addon"><?= $datoscreppg[$i]['capital']; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Interes</span>
                                                                        <span class="input-group-addon"><?= $datoscreppg[$i]['interes']; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Dias Atraso</span>
                                                                        <span class="input-group-addon"><?= $datoscreppg[$i]['diasatraso']; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Estado</span>
                                                                        <span class="input-group-addon badge text-bg-<?php if ($datoscreppg[$i]['diasatraso'] > 0) {
                                                                                                                            echo 'danger';
                                                                                                                        } else {
                                                                                                                            echo 'success';
                                                                                                                        } ?>"><?php if ($datoscreppg[$i]['diasatraso'] > 0) {
                                                                                                                                    echo 'Vencida';
                                                                                                                                } else {
                                                                                                                                    echo 'Vigente';
                                                                                                                                } ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    <?php } ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- FILA DEL TOTAL GENERAL -->
                            <div class="row d-flex justify-content-end">
                                <div class="col-4 mb-2">
                                    <span class="input-group-addon">Total General</span>
                                    <input id="totalgen" readonly type="number" step="0.01" class="form-control form-control-sm" value="<?= $sumafilas; ?>">
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    </div>
                    <!-- <div class="container"> -->
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center mt-2" id="modal_footer">
                            <?php if ($status) { ?>
                                <button class="btn btn-outline-success" onclick="guardar_pagos_individuales(0,'<?= $datos[0]['ccodcta']; ?>',<?= $sumacap; ?>,<?= $sumainteres; ?>,<?= $sumamora; ?>)"><i class="fa-solid fa-money-bill me-2"></i>Pagar</button>
                                <button class="btn btn-outline-primary " onclick="mostrar_planpago('<?= $datos[0]['ccodcta']; ?>'); printdiv5('nomcli2,codcredito2/A,A/'+'/#/#/#/#',['<?= $datos[0]['nombrecli']; ?>','<?= $datos[0]['ccodcta']; ?>']);" data-bs-toggle="modal" data-bs-target="#modal_plan_pago"><i class="fa-solid fa-rectangle-list me-2"></i>Consultar plan de pago</button>
                            <?php } ?>
                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                    <!-- </div> -->
            </div>
            <?php include_once "../../../src/cris_modales/mdls_pagos_juridicos.php"; ?>
            <?php include_once "../../../src/cris_modales/mdls_planpago.php"; ?>

            <!-- Modal -->
            <div class="modal fade " id="modalgastos" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="exampleModalLabel">Detalle de otros cobros</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            <input style="display: none;" id="flagid" readonly type="number" value="0">
                        </div>
                        <div class="modal-body">
                            <table class="table" id="tbgastoscuota" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Descripcion</th>
                                        <th>Afecta Otros modulos</th>
                                        <th>Adicional</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="categoria_tb">
                                    <tr>
                                        <td>OTROS</td>
                                        <td data-id="0">-</td>
                                        <td data-cuenta="0">-</td>
                                        <td>
                                            <div class="row d-flex justify-content-end">
                                                <input style="display:none;" type="text" name="idgasto" value="0">
                                                <input style="display:none;" type="text" name="idcontable" value="0">
                                                <input onkeyup="sumotros()" style="text-align: right;" id="DS" type="number" step="0.01" class="form-control form-control-sm inputNoNegativo" value="<?= ($status) ? $sumaotrospagos : 0; ?>">
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                    $query = "SELECT gas.id,gas.id_nomenclatura,gas.nombre_gasto,gas.afecta_modulo,cre.cntAho ccodaho,cre.NCapDes mondes, cre.noPeriodo cuotas,cre.moduloafecta,pro.* FROM cre_productos_gastos pro 
													INNER JOIN cre_tipogastos gas ON gas.id=pro.id_tipo_deGasto
													INNER JOIN cremcre_meta cre ON cre.CCODPRD=pro.id_producto
													WHERE cre.CCODCTA='" . $datos[0]['ccodcta'] . "' AND pro.tipo_deCobro=2 AND gas.estado=1 AND pro.estado=1";
                                    $consulta = mysqli_query($conexion, $query);
                                    $array_datos = array();

                                    $i = 0;
                                    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                        $tipo = $fila['tipo_deMonto'];
                                        $cant = $fila['monto'];
                                        $calculax = $fila['calculox'];
                                        $mondes = $fila['mondes'];
                                        $cuotas = $fila['cuotas'];
                                        if ($tipo == 1) { //MONTO FIJO
                                            $mongas = ($calculax == 1) ? $cant : (($calculax == 2) ? ($cant / $cuotas) : $cant);
                                        }
                                        if ($tipo == 2) { //PORCENTAJE
                                            //$mongas = ($calculax == 1) ? ($cant / 100 * $amortiza1[$i]) : (($calculax == 2) ? ($cant / 100 * $row) : (($calculax == 3) ? ($cant / 100 * ($amortiza1[$i] + $row)) : 0));
                                        }
                                        $afecta = $fila["afecta_modulo"];
                                        $cremmodulo = $fila["moduloafecta"];
                                        $modulo = ($afecta == 1) ? 'AHORROS' : (($afecta == 2) ? 'APORTACIONES' : 'NO');
                                        $cuenta = ($afecta == 1 || $afecta == 2) ? (($cremmodulo == $afecta && strlen(trim($fila["ccodaho"])) >= 12) ? $fila["ccodaho"] : 'No hay cuenta vinculada') : 'NO';
                                        $disabled = ($afecta == 1 || $afecta == 2) ? (($cremmodulo == $afecta && strlen(trim($fila["ccodaho"])) >= 12) ? '' : 'disabled readonly') : '';

                                        // $cuenta = ($cremmodulo == $afecta) ? $fila["ccodaho"] : 'NO';
                                        echo '<tr>
													<td>' . $fila["nombre_gasto"] . '</td>
													<td data-id="' . $afecta . '">' . $modulo . '</td>
													<td data-cuenta="' . $cuenta . '">' . $cuenta . '</td>
													<td><div class="row d-flex justify-content-end">
															<input style="display:none;" type="text" name="idgasto" value="' . $fila['id'] . '">
															<input style="display:none;" type="text" name="idcontable" value="' . $fila['id_nomenclatura'] . '">
															<input ' . $disabled . ' onkeyup="sumotros()" style="text-align: right;" id="totalgen" type="number" step="0.01" class="form-control form-control-sm inputNoNegativo" value="0">
														</div></td>
												</tr>';

                                        $i++;
                                    }
                                    $cantfila = $i;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- <button id="shareButton" onclick="compartir()">Compartir</button> -->
            <script>
                //-------------------inicio chaka
                function procesarPagos(reestructura, cant, codcredito, verificamora) {
                    var datos = [];
                    var rows = 0;
                    while (rows <= cant) {
                        filas = getinputsval(['codcredito', 'monmora' + (rows), 'nomcli', 'capital' + (rows), 'interes' + (rows), 'otrospg' + (rows), 'totalpg' + (rows)]);
                        datos[rows] = filas;
                        rows++;
                    }

                    var detalles = [];
                    detalles[0]=[0,0,0,0,0]
                    var i = 0;
                    $('#tbgastoscuota tr').each(function(index, fila) {
                        var monto = $(fila).find('td:eq(3) input[type="number"]');
                        monto = (isNaN(monto.val())) ? 0 : Number(monto.val());
                        var idgasto = $(fila).find('td:eq(3) input[name="idgasto"]');
                        idgasto = Number(idgasto.val());
                        var idcontable = $(fila).find('td:eq(3) input[name="idcontable"]');
                        idcontable = Number(idcontable.val());
                        var modulo = $(fila).find('td:eq(1)').data('id');
                        var codaho = $(fila).find('td:eq(2)').data('cuenta');

                        if (monto > 0) {
                            detalles[i] = [monto, idgasto, idcontable, modulo, codaho];
                            i++;
                        }
                    });

                    obtiene([`norecibo`, `fecpag`, `capital0`, `interes0`, `monmora0`, `otrospg0`, `totalgen`, `fecpagBANC`, `noboletabanco`, `concepto`], [`bancoid`, `cuentaid`, `metodoPago`], [], `create_pago_juridico`, `0`, [codcredito, detalles, reestructura]);
                }

                function guardar_pagos_individuales(cant, codcredito, kp, int, mor) {
                    var capital = document.getElementById("capital0").value;
                    capital = parseFloat(capital);
                    var interes = document.getElementById("interes0").value;
                    interes = parseFloat(interes);
                    var mora = document.getElementById("monmora0").value;
                    mora = parseFloat(mora);

                    var verificamora = 0;

                    var reestructura = 0;
                    procesarPagos(reestructura, cant, codcredito, verificamora);
                }

                function sumotros() {
                    var valor = 0
                    $('#tbgastoscuota tr').each(function(index, fila) {
                        var inputDS = $(fila).find('td:eq(3) input[type="number"]');
                        var valorDS = Number(inputDS.val());
                        valor += (isNaN(valorDS)) ? 0 : valorDS;
                    });
                    $("#otrospg0").val(valor);
                    summon("otrospg0")
                }
                var inputs = document.querySelectorAll('.inputNoNegativo');
                inputs.forEach(function(input) {
                    input.addEventListener('input', function() {
                        var expresion = input.value;
                        var esValida = /^-?\d+(\.\d+)?([-+*/]\d+(\.\d+)?)*$/.test(expresion);
                        if (!esValida) {
                            //alert("Por favor, ingresa una expresión matemática válida.");
                            input.value = 0;
                            sumotros();
                        }
                        if (parseFloat(input.value) < 0) {
                            input.value = 0;
                            sumotros();
                        }
                    });
                });

                function compartir() {
                    const pdfUrl = 'caja/netmanual.pdf'; // Reemplaza con la URL de tu PDF
                    const printWindow = window.open(pdfUrl);

                    // Espera a que el contenido del PDF se cargue y luego imprime
                    printWindow.addEventListener('load', () => {
                        printWindow.print();
                    });
                }

                // async function compartir() {
                //     const shareButton = document.getElementById('shareButton');
                //     const pdfUrl = 'caja/netmanual.pdf'; // Reemplaza con la URL de tu PDF
                //     if (navigator.canShare && navigator.canShare({
                //                 files: [new File([], '')]
                //             })) {
                //             try {
                //                 const response = await fetch(pdfUrl);
                //                 const blob = await response.blob();
                //                 const file = new File([blob], 'archivo.pdf', {
                //                     type: 'application/pdf'
                //                 });

                //                 await navigator.share({
                //                     title: 'Compartir PDF',
                //                     text: 'Aquí tienes el PDF que quiero compartir contigo.',
                //                     files: [file]
                //                 });

                //                 console.log('PDF compartido exitosamente');
                //             } catch (error) {
                //                 console.error('Error al compartir el PDF:', error);
                //             }
                //         } else {
                //             alert('La API de Web Share no es compatible con este navegador o dispositivo.');
                //         }
                // }



                function compartir2() {
                    const shareButton = document.getElementById('shareButton');

                    shareButton.addEventListener('click', async () => {
                        if (navigator.share) {
                            try {
                                await navigator.share({
                                    title: 'Título del contenido a compartir',
                                    text: 'Descripción o mensaje a compartir.',
                                    url: window.location.href // La URL actual de la página
                                });
                                console.log('Contenido compartido exitosamente');
                            } catch (error) {
                                console.error('Error al compartir:', error);
                            }
                        } else {
                            alert('La API de Web Share no es compatible con este navegador.');
                        }
                    });
                }
            </script>
    <?php
        break;
        case 'Apertura_Bodeda': {
            $xtra = $_POST["xtra"];
            $mediumPermissionId = 5; // Permiso de apertura de caja a nivel de agencia
            $highPermissionId = 6;   // Permiso de apertura de caja a nivel general [Todas las agencias]
            $showmensaje = false;
            try {
                $database->openConnection();
                $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (5,6) AND id_usuario=? AND estado=1", [$idusuario]);
                $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

                $condicion = ($accessHandler->isHigh()) ? "estado=1" : (($accessHandler->isMedium()) ? "estado=1 AND id_agencia=?" : "estado=1 AND id_usu=?");
                $parametros = ($accessHandler->isHigh()) ? [] : (($accessHandler->isMedium()) ? [$idagencia] : [$idusuario]);

                $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], $condicion, $parametros);
                if (empty($users)) {
                    $showmensaje = true;
                    throw new Exception("No existen usuarios por revisar");
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
            // echo print_r($permisos);
            // echo "</pre>";
    ?>
            <input type="text" id="condi" value="Apertura_Bodeda" hidden>
            <input type="text" id="file" value="caja003" hidden>

            <div class="text" style="text-align:center">APERTURA DE CAJA</div>
            <div class="card">
                <div class="card-header">Apertura de caja</div>
                <div class="card-body">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>¡Error!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Apertura de caja</b></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="usuario" name="usuario" <?= ($accessHandler->isLow()) ? 'disabled' : ''; ?>>
                                        <option selected disabled value="0">Seleccione un usuario</option>
                                        <?php foreach ($users as $user) {
                                            $selected = ($user['id_usu'] == $idusuario) ? "selected" : ""; ?>
                                            <option value="<?= $user['id_usu']; ?>" <?= $selected; ?>><?= $user['nombre'] . ' ' . $user['apellido']; ?></option>
                                        <?php } ?>
                                    </select>
                                    <label for="usuario">Usuario</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="date" class="form-control" id="fec_apertura" placeholder="Fecha de apertura"
                                        readonly <?php echo 'value="' . date('Y-m-d') . '"'; ?>>
                                    <label for="fec_apertura">Fecha de apertura</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="input-group mb-2 mt-2">
                                <span class="input-group-text bg-primary text-white" style="border: none !important;"
                                    id="basic-addon1"><i class="fa-solid fa-money-bill"></i></span>
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="saldoinicial" placeholder="Saldoinicial"
                                        step="0.01">
                                    <label for="saldoinicial">Digite su saldo inicial</label>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mt-2 mb-2" id="modal_footer">
                                <button class="btn btn-outline-success"
                                    onclick="obtiene([`fec_apertura`, `saldoinicial`], [`usuario`], [], 'create_caja_apertura', `0`, []);"><i
                                        class="fa-solid fa-box-open me-2"></i></i>Aperturar</button>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"><i
                                        class="fa-solid fa-ban"></i> Cancelar</button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()"><i
                                        class="fa-solid fa-circle-xmark"></i> Salir</button>
                            </div>
                        </div>
                    </div>

                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Historial de cierres y aperturas del mes</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="table-responsive">
                                    <table class="table nowrap table-hover table-border" id="tb_aperturas_cierres"
                                        style="width: 100% !important;">
                                        <thead class="text-light table-head-aprt">
                                            <tr style="font-size: 0.9rem;">
                                                <th>#</th>
                                                <th>User</th>
                                                <th>Fecha de apertura</th>
                                                <th>Saldo inicial</th>
                                                <th>Fecha de cierre</th>
                                                <th>Saldo final</th>
                                                <th>Estado</th>
                                                <th>R. arqueo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-group-divider" style="font-size: 0.9rem !important;">

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$accessHandler->isLow()) { ?>
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Historial de cierres y aperturas del mes consolidados</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="table-responsive">
                                        <table class="table nowrap table-hover table-border" id="tb_aperturas_cierres_conso"
                                            style="width: 100% !important;">
                                            <thead class="text-light table-head-aprt">
                                                <tr style="font-size: 0.9rem;">
                                                    <th>#</th>
                                                    <th>Fecha</th>
                                                    <th>Agencia</th>
                                                    <th>Saldo inicial</th>
                                                    <th>Cierre</th>
                                                    <th>Saldo final</th>
                                                    <th>Estado</th>
                                                    <th>R. arqueo</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider" style="font-size: 0.9rem !important;">

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $(document).ready(function() {
                                $('#tb_aperturas_cierres_conso').on('search.dt').DataTable({
                                    "aProcessing": true,
                                    "aServerSide": true,
                                    "ordering": false,
                                    "lengthMenu": [
                                        [10],
                                        ['10 filas']
                                    ],
                                    "ajax": {
                                        url: '../../src/cruds/crud_caja.php',
                                        type: "POST",
                                        beforeSend: function() {
                                            loaderefect(1);
                                        },
                                        data: {
                                            'condi': "listado_aperturas_conso"
                                        },
                                        dataType: "json",
                                        complete: function(response) {
                                            // console.log(response);
                                            loaderefect(0);
                                            if (response.responseJSON.error != undefined) {
                                                Swal.fire('Error', response.responseJSON.error, 'error')
                                            }
                                        }
                                    },
                                    "bDestroy": true,
                                    "iDisplayLength": 10,
                                    "order": [
                                        [1, "desc"]
                                    ],
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
                    <?php } ?>
                </div>
                <script>
                    $(document).ready(function() {
                        $('#tb_aperturas_cierres').on('search.dt').DataTable({
                            "aProcessing": true,
                            "aServerSide": true,
                            "ordering": false,
                            "lengthMenu": [
                                [10],
                                ['10 filas']
                            ],
                            "ajax": {
                                url: '../../src/cruds/crud_caja.php',
                                type: "POST",
                                beforeSend: function() {
                                    loaderefect(1);
                                },
                                data: {
                                    'condi': "listado_aperturas"
                                },
                                dataType: "json",
                                complete: function(response) {
                                    // console.log(response);
                                    loaderefect(0);
                                    if (response.responseJSON.error != undefined) {
                                        Swal.fire('Error', response.responseJSON.error, 'error')
                                    }
                                }
                            },
                            "bDestroy": true,
                            "iDisplayLength": 10,
                            "order": [
                                [1, "desc"]
                            ],
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
            <?php
        }
    break;
} ?>