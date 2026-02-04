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

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
include __DIR__ . '/functions/functions.php';
date_default_timezone_set('America/Guatemala');

use PhpOffice\PhpSpreadsheet\Calculation\Engineering\BesselK;

$codusu = $_SESSION["id"];
$id_agencia = $_SESSION['id_agencia'];

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");
include_once "../../../src/cris_modales/mdls_cre_indi02.php";
include '../../../includes/BD_con/db_con.php';

mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

$condi = $_POST["condi"];

switch ($condi) {
    case 'documentos':
        $xtra = $_POST["xtra"];

        $strquery = "SELECT cl.short_name AS nombrecli, cl.idcod_cliente AS codcli, ag.cod_agenc AS codagencia,cm.Cestado, cm.CCODPRD AS codprod, cm.CCODCTA AS ccodcta, cm.MonSug AS monsug, cm.NIntApro AS interes, cm.DFecDsbls AS fecdesembolso, cm.noPeriodo AS cuotas,
						((cm.MonSug)-(SELECT IFNULL(SUM(ck.KP),0) FROM CREDKAR ck WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA=cm.CCODCTA)) AS saldocap,prod.id_fondo
						FROM cremcre_meta cm
						INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
						INNER JOIN tb_agencia ag ON cm.CODAgencia=ag.cod_agenc
						INNER JOIN cre_productos prod ON prod.id=cm.CCODPRD
						WHERE cm.Cestado IN ('A','D','E','F') AND cm.TipoEnti='INDI' AND cm.CCODCTA=?
						GROUP BY cm.CCODCTA";
        try {
            if (empty($xtra)) {
                $showmensaje = true;
                throw new Exception("Seleccione un credito");
            }

            $database->openConnection();
            $result = $database->getAllResults($strquery, [$xtra]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("Cuenta de Credito no existe");
            }
            // $bandera = "";
            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ?  $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
        // print_r($result);
        $matrizDocumentos = [
            'A' => [ // Solicitud
                'FICHA_SOLICITUD' => 29,
            ],
            'D' => [ // Análisis
                'FICHA_SOLICITUD' => 29,
                'DICTAMEN' => 20,
                'FICHA_ANALISIS' => 30
            ],
            'E' => [ // Aprobación
                'FICHA_SOLICITUD' => 29,
                'DICTAMEN' => 20,
                'FICHA_ANALISIS' => 30,
                'PAGARE' => 17,
                'FICHA_APROBACION' => 31
            ],
            'F' => [ // Vigente
                'FICHA_SOLICITUD' => 29,
                'DICTAMEN' => 20,
                'FICHA_ANALISIS' => 30,
                'PAGARE' => 17,
                'FICHA_APROBACION' => 31,
                'AUTENTICA' => 23,
                'CONTRATO' => 19,
                'COMPROBANTE' => 18,
                'TARJETA_PAGO' => 43
            ]
        ];

        // Definimos un array con los nombres amigables de los documentos
        $nombreDocumentos = [
            'FICHA_SOLICITUD' => 'Ficha de solicitud',
            'DICTAMEN' => 'Dictamen',
            'FICHA_ANALISIS' => 'Ficha de Análisis',
            'PAGARE' => 'Pagaré',
            'FICHA_APROBACION' => 'Ficha de Aprobación',
            'AUTENTICA' => 'Auténtica',
            'CONTRATO' => 'Contrato',
            'COMPROBANTE' => 'Comprobante de desembolso',
            'TARJETA_PAGO' => 'Tarjeta de pago'
        ];

        // Estados con sus descripciones
        $estadosDescripcion = [
            'A' => ['texto' => 'Solicitud', 'color' => 'warning', 'icono' => 'fa-file-text'],
            'D' => ['texto' => 'Análisis', 'color' => 'info', 'icono' => 'fa-search'],
            'E' => ['texto' => 'Aprobado', 'color' => 'success', 'icono' => 'fa-check-circle'],
            'F' => ['texto' => 'Vigente', 'color' => 'primary', 'icono' => 'fa-handshake']
        ];

?>
        <input type="text" id="condi" value="documentos" hidden>
        <input type="text" id="file" value="cre_indi_03" hidden>

        <div class="container-fluid px-4">
            <!-- Encabezado principal -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-center bg-gradient bg-primary text-white rounded-3 py-3 shadow-sm">
                        <i class="fa-solid fa-file-lines fs-2 me-3"></i>
                        <h2 class="mb-0 fw-bold">DOCUMENTOS INDIVIDUALES</h2>
                    </div>
                </div>
            </div>

            <?php if (!$status) { ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-triangle-exclamation fs-4 me-3"></i>
                                <div>
                                    <strong>Atención:</strong> <?= $mensaje; ?>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <!-- Información del cliente y crédito -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-lg border-0 overflow-hidden">
                        <div class="card-header bg-gradient bg-dark text-white py-3">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-user-circle fs-4 me-3"></i>
                                <h5 class="mb-0 fw-semibold">Información del Cliente y Crédito</h5>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <!-- Fila 1: Información del cliente -->
                            <div class="row g-4 mb-4">
                                <div class="col-lg-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg border-0 bg-light"
                                            id="nomcli" placeholder="Nombre de cliente"
                                            value="<?= $result[0]['nombrecli'] ?? '' ?>" readonly>
                                        <label for="nomcli" class="fw-semibold">
                                            <i class="fa-solid fa-user me-2"></i>Nombre del Cliente
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg border-0 bg-light"
                                            id="id_cod_cliente" placeholder="Código de cliente"
                                            value="<?= $result[0]['codcli'] ?? '' ?>" readonly>
                                        <label for="id_cod_cliente" class="fw-semibold">
                                            <i class="fa-solid fa-id-card me-2"></i>Código Cliente
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg border-0 bg-light"
                                            id="codagencia" placeholder="Código de agencia"
                                            value="<?= $result[0]['codagencia'] ?? '' ?>" readonly>
                                        <label for="codagencia" class="fw-semibold">
                                            <i class="fa-solid fa-building me-2"></i>Agencia
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg border-0 bg-light"
                                            id="codproducto" placeholder="Código de producto"
                                            value="<?= $result[0]['codprod'] ?? '' ?>" readonly>
                                        <label for="codproducto" class="fw-semibold">
                                            <i class="fa-solid fa-box me-2"></i>Producto
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <button type="button" class="btn btn-primary btn-lg h-100 w-100 d-flex align-items-center justify-content-center"
                                        data-bs-toggle="modal" data-bs-target="#modal_pagos_cre_individuales">
                                        <i class="fa-solid fa-magnifying-glass-plus me-2"></i>
                                        <span class="d-none d-lg-inline">Buscar</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Fila 2: Información del crédito -->
                            <div class="row g-4">
                                <div class="col-lg-3">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg border-0 bg-light"
                                            id="codcredito" placeholder="Código de crédito"
                                            value="<?= $result[0]['ccodcta'] ?? '' ?>" readonly>
                                        <label for="codcredito" class="fw-semibold">
                                            <i class="fa-solid fa-credit-card me-2"></i>Código de Crédito
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg border-0 bg-light"
                                            id="ccapital" placeholder="Capital"
                                            value="Q <?= number_format($result[0]['monsug'] ?? 0, 2) ?>" readonly>
                                        <label for="ccapital" class="fw-semibold">
                                            <i class="fa-solid fa-coins me-2"></i>Capital
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-floating">
                                        <input type="text" class="form-control form-control-lg border-0 bg-light"
                                            id="saldocap" placeholder="Saldo Capital"
                                            value="Q <?= number_format($result[0]['saldocap'] ?? 0, 2) ?>" readonly>
                                        <label for="saldocap" class="fw-semibold">
                                            <i class="fa-solid fa-wallet me-2"></i>Saldo Capital
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <?php if (!empty($result)) {
                                        $estado = $result[0]["Cestado"];
                                        $estadoInfo = $estadosDescripcion[$estado] ?? ['texto' => 'Desconocido', 'color' => 'secondary', 'icono' => 'fa-question'];
                                    ?>
                                        <div class="d-flex align-items-center h-100">
                                            <span class="badge bg-<?= $estadoInfo['color'] ?> fs-6 p-3 w-100 d-flex align-items-center justify-content-center">
                                                <i class="fa-solid <?= $estadoInfo['icono'] ?> me-2"></i>
                                                <?= $estadoInfo['texto'] ?>
                                            </span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de documentos disponibles -->
            <?php if (!empty($result)) { ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-lg border-0">
                            <div class="card-header bg-gradient bg-success text-white py-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="fa-solid fa-file-pdf fs-4 me-3"></i>
                                        <h5 class="mb-0 fw-semibold">Documentos Disponibles</h5>
                                    </div>
                                    <span class="badge bg-light text-success fs-6">
                                        Estado: <?= $estadosDescripcion[$result[0]["Cestado"]]['texto'] ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <?php
                                    $documentosDisponibles = $matrizDocumentos[$result[0]["Cestado"]] ?? [];
                                    $contador = 0;
                                    foreach ($documentosDisponibles as $documento => $idReporte) {
                                        $contador++;
                                        // Definir colores para diferentes tipos de documentos
                                        $colores = ['primary', 'success', 'info', 'warning', 'danger', 'dark'];
                                        $color = $colores[($contador - 1) % count($colores)];
                                    ?>
                                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                                            <div class="card h-100 border-0 shadow-sm documento-card">
                                                <div class="card-body d-flex flex-column justify-content-between p-3">
                                                    <div class="text-center mb-3">
                                                        <i class="fa-solid fa-file-pdf text-<?= $color ?> fs-1 mb-2"></i>
                                                        <h6 class="card-title fw-bold text-<?= $color ?> mb-2">
                                                            <?= $nombreDocumentos[$documento] ?>
                                                        </h6>
                                                    </div>
                                                    <button class="btn btn-outline-<?= $color ?> btn-sm w-100"
                                                        onclick="reportes([[],[],[],['<?= $result[0]['ccodcta'] ?>']], 'pdf', '<?= $idReporte ?>',0,1)">
                                                        <i class="fa-solid fa-download me-2"></i>Descargar PDF
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>

                                <?php if (empty($documentosDisponibles)) { ?>
                                    <div class="text-center py-5">
                                        <i class="fa-solid fa-inbox fs-1 text-muted mb-3"></i>
                                        <h5 class="text-muted">No hay documentos disponibles para este estado</h5>
                                        <p class="text-muted">Los documentos aparecerán según el estado del crédito</p>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <!-- Botones de acción -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <button type="button" class="btn btn-outline-danger btn-lg px-4" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-lg px-4" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark me-2"></i>Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para búsqueda de créditos -->
        <div class="modal fade" id="modal_pagos_cre_individuales" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content shadow-lg border-0">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalLabel">
                            <i class="fa-solid fa-users me-2"></i>Créditos Individuales
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="table-responsive">
                            <table id="tabla_creditos" class="table table-striped table-hover align-middle" style="width: 100%;">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Código Crédito</th>
                                        <!-- <th scope="col">Código Cliente</th> -->
                                        <th scope="col">DPI</th>
                                        <th scope="col">Nombre Cliente</th>
                                        <th scope="col">Ciclo</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Monto</th>
                                        <!-- <th scope="col">Acciones</th> -->
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                            <i class="fa-solid fa-circle-xmark me-2"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                var table = $('#tabla_creditos').on('search.dt').DataTable({
                    "aProcessing": true,
                    "aServerSide": true,
                    "ordering": false,
                    "lengthMenu": [
                        [10, 15, -1],
                        ['10 filas', '15 filas', 'Mostrar todos']
                    ],
                    "ajax": {
                        url: '../../src/cruds/crud_credito_indi.php',
                        type: "POST",
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        data: {
                            'condi': 'list_creditos_all'
                        },
                        dataType: "json",
                        complete: function(data) {
                            // console.log(data)
                            loaderefect(0);
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
                    },
                    "createdRow": function(row, data, dataIndex) {
                        $(row).css('cursor', 'pointer'); // Cambia el cursor al pasar sobre la fila
                        $(row).attr('title', 'Click para seleccionar'); // Tooltip opcional
                    }
                });

                $('#tabla_creditos tbody').on('click', 'tr', function() {
                    var rowData = table.row(this).data();
                    if (rowData) {
                        var valorPrimeraColumna = rowData[1];
                        printdiv2('#cuadro', valorPrimeraColumna);
                        $(this).addClass('selected').siblings().removeClass('selected');
                        $('#modal_pagos_cre_individuales').modal('hide');
                    }
                });
            });
        </script>
    <?php
        break;
    case 'kardexhistory':
        $xtra = $_POST["xtra"];

        $datos[] = [];
        $bandera = false;
        $i = 0;
        if ($xtra != 0) {
            $bandera = true;
            //SE REALIZA LA CONSULTA
            $consulta = mysqli_query($conexion, "SELECT cl.short_name AS nombrecli, cl.idcod_cliente AS codcli, ag.cod_agenc AS codagencia, cm.CCODPRD AS codprod, cm.CCODCTA AS ccodcta, cm.MonSug AS monsug, cm.NIntApro AS interes, cm.DFecDsbls AS fecdesembolso, cm.noPeriodo AS cuotas, ce.Credito AS tipocred, per.nombre AS nomper,
                ((cm.MonSug)-(SELECT IFNULL(SUM(ck.KP),0) FROM CREDKAR ck WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA='$xtra')) AS saldocap,prod.id_fondo, cl.url_img AS urlfoto
                FROM cremcre_meta cm
                INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                INNER JOIN tb_agencia ag ON cm.CODAgencia=ag.cod_agenc
                INNER JOIN cre_productos prod ON prod.id=cm.CCODPRD
                INNER JOIN $db_name_general.tb_credito ce ON cm.CtipCre=ce.abre
                INNER JOIN $db_name_general.tb_periodo per ON cm.NtipPerC=per.periodo
                WHERE cm.CCODCTA='$xtra'
                GROUP BY cm.CCODCTA");
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $datos[$i] = $fila;
                $i++;
                $bandera = true;
            }
            //SE REALIZA LA CONSULTA
            $res = $conexion->query("CALL update_ppg_account('" . $xtra . "')");
            $res = $conexion->query("CALL verificacion_kardex('" . $xtra . "','" . $hoy . "')");
        }
    ?>
        <input type="text" id="condi" value="kardexhistory" hidden>
        <input type="text" id="file" value="cre_indi_03" hidden>

        <div class="text" style="text-align:center">HISTORIAL DE PAGOS DE CREDITOS</div>
        <div class="card">
            <div class="card-header">Historial</div>
            <div class="card-body">
                <!-- seleccion de cliente y su credito-->
                <div class="container contenedort" style="max-width: 100% !important;">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2"><b>Información de cliente y crédito</b></div>
                        </div>
                    </div>
                    <!-- usuario y boton buscar -->
                    <div class="row">
                        <div class="col-12 col-sm-12 col-md-10">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="nomcli" placeholder="Nombre de cliente" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['nombrecli'] . '"';
                                                                                                                            } ?>>
                                        <label for="cliente">Nombre cliente</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <button type="button" class="btn btn-primary col-sm-12"
                                        onclick="abrir_modal('#modal_estado_cuenta', '#id_modal_hidden', 'name/A/'+'/#/#/#/#')">
                                        <i class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito
                                    </button>
                                </div>
                            </div>
                            <!-- cargo, nombre agencia y codagencia  -->
                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="id_cod_cliente"
                                            placeholder="Código de cliente" <?php if ($bandera) {
                                                                                echo 'value="' . $datos[0]['codcli'] . '"';
                                                                            } ?>>
                                        <label for="id_cod_cliente">Código cliente</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="codagencia"
                                            placeholder="Código de agencia" <?php if ($bandera) {
                                                                                echo 'value="' . $datos[0]['codagencia'] . '"';
                                                                            } ?>>
                                        <label for="codagencia">Código de agencia</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="codproducto"
                                            placeholder="Código de producto" <?php if ($bandera) {
                                                                                    echo 'value="' . $datos[0]['codprod'] . '"';
                                                                                } ?>>
                                        <label for="cargo">Codigo de producto</label>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- cnumdoc, capital, gastos, total a desembolsar -->
                    <div class="row">
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="codcredito" placeholder="Codigo de crédito" <?php if ($bandera) {
                                                                                                                            echo 'value="' . $datos[0]['ccodcta'] . '"';
                                                                                                                        } ?>>
                                <label for="nomagencia">Código de crédito</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="ccapital" placeholder="Capital" <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['monsug'] . '"';
                                                                                                            } ?>>
                                <label for="ccapital">Capital</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12 col-md-4">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="saldocap" placeholder="Saldo Capital" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['saldocap'] . '"';
                                                                                                                    } ?>>
                                <label for="saldocap">Saldo capital</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="container contenedort">
                    <div class="table-responsive">
                        <table id="tabla_history" class="table table-striped table-hover"
                            style="width: 100% !important; font-size: 0.9rem !important;">
                            <thead>
                                <tr>
                                    <th scope="col">Cuota</th>
                                    <th scope="col">Fecha Vencimiento</th>
                                    <th scope="col">Fecha Cancelacion</th>
                                    <th scope="col">Dias atrasados</th>
                                    <th scope="col">Estado</th>
                                    <!-- <th scope="col">Capital</th>
                                        <th scope="col">Interes</th> -->
                                    <th scope="col">Cuota</th>
                                    <th scope="col">Calificacion</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <!-- <div class="container"> -->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center mt-2" id="modal_footer">

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
            <?php include_once "../../../src/cris_modales/mdls_estadocuenta.php"; ?>

            <script>
                <?php if ($xtra != 0) { ?>
                    $(document).ready(function() {
                        tbhistory('<?php echo ($xtra) ?>');
                    })
                <?php } ?>

                function tbhistory(cuenta) {
                    $('#tabla_history').on('search.dt').DataTable({
                        "aProcessing": true,
                        "aServerSide": true,
                        "ordering": false,
                        "lengthMenu": [
                            [10, 15, -1],
                            ['10 filas', '15 filas', 'Mostrar todos']
                        ],
                        "ajax": {
                            url: '../../src/cruds/crud_credito_indi.php',
                            type: "POST",
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            data: {
                                'condi': 'consultar_history',
                                cuenta
                            },
                            dataType: "json",
                            complete: function(data) {
                                console.log(data)
                                loaderefect(0);
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
                }
            </script>
            <?php
            break;
        //********* FORMULARIO DE DESEMBOLSO MULTIPLE PRIMERA PRUBA   ************************** */
        case 'INDI_DESEM_MULTI': {
                $codusu = $_SESSION['id'];
                $id_agencia = $_SESSION['id_agencia'];
                $xtra = $_POST["xtra"];

                //consultar
                $i = 0;
                $bandera = false;
                $datos[] = [];
                $src = '../../includes/img/fotoClienteDefault.png';

                //CONSULTA DE LOS DATOS
                if ($xtra != 0) {
                    $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name AS nomcli, cm.CodCli AS codcli, cm.CODAgencia AS codagencia, pd.cod_producto AS codproducto, cm.MonSug AS monto, 
                        (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo, cm.Cestado AS estado, cl.url_img AS urlfoto   
                        FROM cremcre_meta cm
                        INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id 
                        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.Cestado='E' AND cm.TipoEnti='INDI' AND cm.CCODCTA='$xtra' LIMIT 1");
                    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                        $estado = ($fila['estado'] == 'E') ? 'Aprobado' : ' ';
                        $datos[$i] = $fila;
                        $datos[$i]['estado2'] = $estado;
                        //CARGADO DE LA IMAGEN
                        $imgurl = __DIR__ . '/../../../../' . $fila['urlfoto'];
                        if (!is_file($imgurl)) {
                            $src = '../../includes/img/fotoClienteDefault.png';
                        } else {
                            $imginfo   = getimagesize($imgurl);
                            $mimetype  = $imginfo['mime'];
                            $imageData = base64_encode(file_get_contents($imgurl));
                            $src = 'data:' . $mimetype . ';base64,' . $imageData;
                        }
                        $i++;
                        $bandera = true;
                    }

                    $rst_tipCu = $conexion->query("SELECT cpg.id AS pro_gas, cp.cod_producto, cp.nombre, ct.nombre_gasto, ct.afecta_modulo  
						FROM cremcre_meta cm 
						INNER JOIN cre_productos cp ON cp.id = cm.CCODPRD 
						INNER JOIN cre_productos_gastos cpg ON cpg.id_producto = cp.id 
						INNER JOIN cre_tipogastos ct ON ct.id = cpg.id_tipo_deGasto 
						WHERE cp.cod_producto = {$datos[0]['codproducto']} AND cm.CCODCTA = '{$datos[0]['ccodcta']}' AND ct.afecta_modulo IN (1,2) AND cpg.tipo_deCobro=2");

                    $aho_vin = mysqli_num_rows($rst_tipCu);
                }
                if (!isset($datos[0]['ccodcta'])) {
                    $ccocta = '000';
                } else {
                    $ccocta =  $datos[0]['ccodcta'];
                }
                if (!isset($datos[0]['codcli'])) {
                    $codcli = '000';
                } else {
                    $codcli =  $datos[0]['codcli'];
                }

                //CONSULTAR GARANTIAS NEGROY locuaz
                //BUSCAR DATOS DE GARANTIAS
                if ($ccocta != "000") {
                    $strquery = "SELECT cl.idcod_cliente AS codcli, gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc, 
                        gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
                        IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND tipc.idDoc=1),'x') AS nomcli,
                        IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND tipc.idDoc=1),'x') AS direccioncli,
                        IFNULL((SELECT '1' AS marcado FROM tb_garantias_creditos tgc WHERE tgc.id_cremcre_meta='$ccocta' AND tgc.id_garantia=gr.idGarantia),0) AS marcado,
                        IFNULL((SELECT SUM(cli.montoGravamen) AS totalgravamen FROM tb_garantias_creditos tgc INNER JOIN cli_garantia cli ON cli.idGarantia=tgc.id_garantia WHERE tgc.id_cremcre_meta='$ccocta' AND cli.estado=1),0) AS totalgravamen
                        FROM tb_cliente cl
                        INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
                        INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
                        INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
                        WHERE cl.estado='1' AND gr.estado=1 AND cl.idcod_cliente= '$codcli' ";
                    $query = mysqli_query($conexion, $strquery);
                    $datosgarantias[] = [];
                    $ji = 0;
                    $flag2 = false;
                    while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                        $datosgarantias[$ji] = $fila;
                        $flag2 = true;
                        $ji++;
                    }
                }

                //Busqueda de gastos para refinanciamiento y creditos activos
                if ($ccocta != "000") {
                    $stmt = $conexion->prepare("SELECT cg.id, tipg.nombre_gasto, cm.DFecDsbls fecdes, tipg.id_nomenclatura
                        FROM cremcre_meta cm
                            INNER JOIN cre_productos_gastos cg ON cm.CCODPRD=cg.id_producto
                            INNER JOIN cre_tipogastos tipg ON tipg.id=cg.id_tipo_deGasto
                            INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                            WHERE cm.CCODCTA=? AND tipo_deCobro=1 AND cg.estado=1 AND tipg.afecta_modulo=3");
                    $stmt->bind_param("s", $ccocta); //El arroba omite el warning de php
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $dataRefinance = [];
                    $flag3 = false;
                    while ($fila = $result->fetch_assoc()) {
                        $flag3 = true;
                        $dataRefinance[] = $fila;
                    }

                    $stmt = $conexion->prepare("SELECT CCODCTA,NCapDes,DfecPago fecpago,NIntApro intapro,IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG='P' AND CESTADO!='X'),0) pagadokp,
                        IFNULL((SELECT SUM(nintpag) FROM Cre_ppg WHERE ccodcta=cm.CCODCTA),0) intpen,
                        IFNULL((SELECT MAX(dfecven) from Cre_ppg where cestado='P' AND ccodcta=cm.CCODCTA), '-') fecult
                        FROM cremcre_meta cm WHERE CodCli IN (SELECT Codcli FROM cremcre_meta WHERE CCODCTA=?)
                        AND CCODCTA!=? AND Cestado='F' AND TipoEnti='INDI'");
                    $stmt->bind_param("ss", $ccocta, $ccocta); //El arroba omite el warning de php
                    $stmt->execute();
                    $result2 = $stmt->get_result();
                    $tempDataCreditsActive = [];
                    $dataCreditsActive = [];
                    $flag4 = false;
                    while ($fila = $result2->fetch_assoc()) {
                        $flag4 = true;
                        $tempDataCreditsActive[] = $fila;
                    }
                    $calculointeres = true;
                    $j = 0;
                    foreach ($tempDataCreditsActive as $dataCreditActive) {
                        $account = $dataCreditActive['CCODCTA'];
                        $pagadokp = $dataCreditActive['pagadokp'];
                        $capdes = $dataCreditActive['NCapDes'];
                        $intpen = $dataCreditActive['intpen'];
                        $fecpago = $dataCreditActive['fecpago'];
                        $fecult = $dataCreditActive['fecult'];
                        if ($dataCreditActive['fecult'] == "-" && isset($dataRefinance[0]['fecdes']) && !empty($dataRefinance[0]['fecdes'])) {
                            $fecult = $dataRefinance[0]['fecdes'];
                        } else {
                            $fecult = $dataCreditActive['fecult'];
                        }
                        $intapro = $dataCreditActive['intapro'];
                        $saldo = round($capdes - $pagadokp, 2);
                        $diasdif = dias_dif($fecult, $hoy);
                        if ($calculointeres) {
                            $intpen = $saldo * $intapro / 100 / 360 * $diasdif;
                        }
                        $intpen = round($intpen, 2);
                        $intpen = ($intpen < 0) ? 0 : $intpen;

                        $dataCreditsActive[] = [
                            'ccodcta'  => $account,
                            'saldokp'  => $saldo,
                            'interes'  => $intpen,
                            'id'       => $j + 1
                        ];
                        $j++;
                    }
                }

            ?>
                <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
                <input type="text" id="file" value="cre_indi_03" style="display: none;">
                <input type="text" id="condi" value="INDI_DESEM_MULTI" style="display: none;">
                <div class="text" style="text-align:center">DESEMBOLSO DE CRÉDITO INDIVIDUAL</div>
                <div class="card">
                    <div class="card-header">Desembolso de crédito individual <?= $codcli ?> </div>
                    <div class="card-body">

                        <!-- seleccion de cliente y su credito-->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Información de cliente y codigo de crédito</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 col-sm-6 col-md-2 mt-2">
                                    <img width="120" height="130" id="vistaPrevia" src="<?= $src; ?>">
                                </div>
                                <div class="col-12 col-sm-12 col-md-10">
                                    <!-- usuario y boton buscar -->
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="nomcli"
                                                    placeholder="Nombre de cliente" readonly <?php if ($bandera) {
                                                                                                    echo 'value="' . $datos[0]['nomcli'] . '"';
                                                                                                } ?>>
                                                <input type="text" name="" id="id_cod_cliente" hidden <?php if ($bandera) {
                                                                                                            echo 'value="' . $datos[0]['codcli'] . '"';
                                                                                                        } ?>>
                                                <label for="cliente">Nombre cliente</label>
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                                                onclick="abrir_modal('#modal_creditos_a_desembolsar', '#id_modal_hidden', 'id_cod_cliente,nomcli,codagencia,codproducto,codcredito,ccapital/A,A,A,A,A,A/'+'/tipo_desembolso/#/#/mensaje')"><i
                                                    class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito a
                                                desembolsar</button>
                                        </div>
                                    </div>
                                    <!-- cargo, nombre agencia y codagencia  -->
                                    <div class="row">
                                        <div class="col-12 col-sm-12 col-md-4">
                                            <div class="form-floating mb-3 mt-2">
                                                <input type="text" class="form-control" id="codproducto"
                                                    placeholder="Código de producto" readonly <?php if ($bandera) {
                                                                                                    echo 'value="' . $datos[0]['codproducto'] . '"';
                                                                                                } ?>>
                                                <label for="cargo">Codigo de producto</label>
                                            </div>
                                        </div>

                                        <!-- estado y ciclo -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="form-floating mb-3 mt-2">
                                                <input type="text" class="form-control" id="estado" placeholder="Estado"
                                                    readonly <?php if ($bandera) {
                                                                    echo 'value="' . $datos[0]['estado2'] . '"';
                                                                } ?>>
                                                <label for="estado">Estado</label>
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="form-floating mb-3 mt-2">
                                                <input type="text" class="form-control" id="ciclo" placeholder="Ciclo" readonly <?php if ($bandera) {
                                                                                                                                    echo 'value="' . $datos[0]['ciclo'] . '"';
                                                                                                                                } ?>>
                                                <label for="ciclo">Ciclo</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- cnumdoc, capital, gastos, total a desembolsar -->
                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="codagencia" placeholder="Código de agencia"
                                            readonly <?php if ($bandera) {
                                                            echo 'value="' . $datos[0]['codagencia'] . '"';
                                                        } ?>>
                                        <label for="codagencia">Agencia</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="codcredito" placeholder="Codigo de crédito"
                                            readonly <?php if ($bandera) {
                                                            echo 'value="' . $ccocta . '"';
                                                        } ?>>
                                        <label for="nomagencia">Código de crédito</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-12 col-md-5">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="ccapital" placeholder="Capital" readonly <?php if ($bandera) {
                                                                                                                                    echo 'value="' . $datos[0]['monto'] . '"';
                                                                                                                                } ?>>
                                        <label for="ccapital">Capital</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="gastos" placeholder="Gastos" readonly>
                                        <label for="gastos">Gastos</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="desembolsar"
                                            placeholder="Total a desembolsar" onchange="calcularCheque()" readonly>
                                        <label for="desembolsar">Total a desembolsar</label>
                                    </div>
                                </div>
                            </div>

                            <!-- DIV PARA VISUALIZAR LAS GARANTIAS NEGROY -->
                            <h2 class="accordion-header">
                                <div class="row">
                                    <div class="col-12">
                                        <button id="bt1" class="accordion-button collapsed loco" data-bs-toggle="collapse"
                                            data-bs-target="#data1" aria-expanded="false" aria-controls="data1">
                                            <div class="row center">
                                                <i class="fa-solid fa-arrow-turn-down">
                                                    <a>Visualzar Garantias</a> </i> <br>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </h2>

                            <div id="data1" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                <div class="accordion-body">
                                    <div class="row mb-3" style="font-size: 0.90rem;">
                                        <!-- SECCION DE GARANTIAS -->
                                        <div class="container contenedort" style="max-width: 100% !important;">
                                            <div class="row">
                                                <div class="col">
                                                    <div class="text-center mb-2"> <b> Garantías del cliente </b> </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col mb-2">
                                                    <div class="table-responsive">
                                                        <table class="table mb-0" style="font-size: 0.8rem !important;">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Tipo Garantia</th>
                                                                    <th scope="col">Tipo Doc.</th>
                                                                    <th scope="col">Descripción</th>
                                                                    <th scope="col">Dirección</th>
                                                                    <th scope="col">Valor gravamen</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="table-group-divider">
                                                                <!-- GARANTIAS NEGROY  -->
                                                                <?php
                                                                if ($ccocta != "000") {
                                                                    for ($i = 0; $i < count($datosgarantias); $i++) {
                                                                        if ($datosgarantias[$i]['marcado'] == 1) {
                                                                            // FORMATEADOR EXPRESS ʕっ•ᴥ•ʔっ
                                                                            $numero_formateado = number_format($datosgarantias[$i]['montogravamen'], 2, '.', ',');
                                                                            echo "<tr> <td scope='row'>" . $datosgarantias[$i]['nomtipgar'] . "</td>
		<td>" . $datosgarantias[$i]['nomtipdoc'] . "</td> ";
                                                                            if ($datosgarantias[$i]["idtipgar"] == 1 && $datosgarantias[$i]["idtipdoc"] == 1) {
                                                                                echo "<td>" . $datosgarantias[$i]['nomcli'] . "</td>
			<td>" . $datosgarantias[$i]['direccioncli'] . "</td>";
                                                                            } else {
                                                                                echo "<td>" . $datosgarantias[$i]['descripcion'] . "</td>
			<td>" . $datosgarantias[$i]['direccion'] . "</td>";
                                                                            }
                                                                            echo "<td>Q " . $numero_formateado . "</td>";
                                                                        }
                                                                    }
                                                                } else {
                                                                    echo "<tr> <td>-</td> <td>-</td> <td>-</td> 
				<td>-</td> <td>-</td> ";
                                                                } ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- DIV PARA VISUALIZAR LAS GARANTIAS  -->
                        </div>
                        <!-- INI ********************************************************************************************************************* slc-->
                        <div class="container contenedort mt-2" style="max-width: 100% !important;" id="aho_vin">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <h5>Seleccionar un tipo de ahorro vinculado o lo puede omitir</h5>
                                        </div>
                                        <div class="col">
                                            <button type="button" class="btn btn-outline-danger" id="ar_ahoVin"
                                                onclick="omitir_aho_vin()">Omitir</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <table class="table table-success table-striped">
                                            <thead class="table-success">
                                                <th scope="row">id</th>
                                                <th scope="row">Código de producto</th>
                                                <th scope="row">Nombre de producto</th>
                                                <th scope="row">Nombre de gasto</th>
                                                <th scope="row">Cuenta afectada</th>
                                                <th scope="row">Check</th>

                                            </thead>
                                            <tbody>
                                                <?php
                                                if (isset($rst_tipCu) && $rst_tipCu->num_rows > 0) {
                                                    while ($row = $rst_tipCu->fetch_assoc()) {
                                                ?>

                                                        <tr style="cursor: pointer;" id="<?= $row['pro_gas'] ?>">
                                                            <td><?= $row['pro_gas'] ?></td>
                                                            <td><?= $row['cod_producto'] ?></td>
                                                            <td><?= $row['nombre'] ?></td>
                                                            <td><?= $row['nombre_gasto'] ?></td>
                                                            <td><?= ($row['afecta_modulo'] == 1) ? "Cuenta de Ahorro" : "Cuenta de Aportación" ?>
                                                            </td>
                                                            <td>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="radio" name="data_tipcu"
                                                                        value="<?= $row['pro_gas'] ?>" id="<?= $row['pro_gas'] ?>"
                                                                        onclick="bus_ahoVin('<?= (isset($datos[0]['codcli'])) ? $datos[0]['codcli'] : '' ?>')">
                                                                    <label class="form-check-label" for="<?= $row['pro_gas'] ?>">
                                                                    </label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                <?php
                                                    }
                                                }

                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div id="tip_cu"></div>
                            </div>
                        </div>

                        <!-- FIN *** -->

                        <!-- SECCION DE REFINANCIAMIENTO -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2 mt-1"><b>Refinanciamiento</b></div>
                                </div>
                            </div>
                            <?php if (isset($flag4) && !$flag4) { ?>
                                <div class="row">
                                    <div class="col">
                                        <div class="alert alert-primary d-flex align-items-center justify-content-center" role="alert">
                                            <span class="flex-shrink-0 me-2">
                                                <i class="fa-solid fa-circle-info"></i>
                                            </span>
                                            <div>
                                                Actualmente no tienes créditos activos. El refinanciamiento estará disponible cuando tengas un crédito vigente.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } elseif (isset($flag3) && !$flag3) { ?>
                                <div class="row">
                                    <div class="col">
                                        <div class="alert alert-warning d-flex align-items-center justify-content-center" role="alert">
                                            <span class="flex-shrink-0 me-2">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                            </span>
                                            <div>
                                                Actualmente tienes créditos activos. Para usar el refinanciamiento tienes que tener al menos un gasto de tal tipo.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php } else {
                                if (isset($flag3) && isset($flag4) && $flag3 && $flag4) { ?>
                                    <div class="row">
                                        <div class="col mb-2">
                                            <div class="table-responsive">
                                                <table id="table-refinance" class="table mb-0" style="font-size: 0.8rem !important;">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Crédito activo</th>
                                                            <th scope="col">Saldo capital</th>
                                                            <th scope="col">Interés</th>
                                                            <th scope="col">Gasto</th>
                                                            <th scope="col">Marcar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="table-group-divider">
                                                        <?php foreach ($dataCreditsActive as $dataCreditActive) { ?>
                                                            <tr>
                                                                <td scope="row" class="align-middle"><?= $dataCreditActive['ccodcta'] ?></td>
                                                                <td class="align-middle"><?= $dataCreditActive['saldokp'] ?></td>
                                                                <td>
                                                                    <input type="number" onblur="calculateExpense()" name="interes_1" class="form-control" step="0.01" min="0" value="<?= $dataCreditActive['interes'] ?>" style="font-size: 0.8rem;">
                                                                </td>
                                                                <td>
                                                                    <select name="gasto_1" class="form-select" style="font-size: 0.8rem;">
                                                                        <?php foreach ($dataRefinance as $key => $refinance) { ?>
                                                                            <option value="<?= $refinance['id'] ?>" data-idextra="<?= $refinance['id_nomenclatura'] ?>"><?= $refinance['nombre_gasto'] ?></option>

                                                                        <?php } ?>
                                                                    </select>
                                                                </td>
                                                                <td class="align-middle">
                                                                    <input class="form-check-input ms-3 S" type="checkbox" onchange="calculateExpense()" value="<?= $dataCreditActive['id'] ?>" id="R_<?= $dataCreditActive['id'] ?>">
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                            <?php }
                            } ?>
                        </div>

                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col-12 mt-2 mb-1">
                                    <div class="table-responsive">
                                        <table id="tabla_gastos_desembolso" class="table" style="max-width: 100% !important;">
                                            <thead>
                                                <tr>
                                                    <th scope="col">#</th>
                                                    <th></th>
                                                    <th scope="col">Descripción de gasto</th>
                                                    <th scope="col">Monto</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- select para la parte del tipo de desembolso -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col-sm-6 mb-3 mt-2">
                                    <div class="form-floating">
                                        <select class="form-select" id="desembolso1" aria-label="Tipo de desembolso" disabled>
                                            <option selected value="4">Efectivo</option>
                                        </select>
                                        <label for="tip_doc">Tipo de desembolso</label>
                                    </div>
                                </div>
                                <!-- NUEVO SELECT PARA MULTI DESEMBOLSO NEGROY-->
                                <div class="col-sm-6 mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="MontoEFECTIVO" onchange="calcularCheque()"
                                            value="0">
                                        <label for="tip_doc">Monto EFECTIVO</label>
                                    </div>
                                </div>
                            </div>
                            <!-- MONTOS DE LOS DIFERENTES MONTOS  -->
                            <div class="row">
                                <div class="col-sm-6 mb-3 mt-2">
                                    <div class="form-floating">
                                        <select class="form-select" id="desembolso2" aria-label="Tipo de desembolso" disabled>
                                            <option selected value="4">Cheque</option>
                                        </select>
                                        <label for="tip_doc">Tipo de desembolso</label>
                                    </div>
                                </div>

                                <div class="col-sm-6 mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" readonly id="MontoCHEQUE">
                                        <label for="tip_doc">Monto CHEQUE</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort" id="region_cheque" style="  max-width: 100% !important;">
                            <div class="row">
                                <div class="col-sm-4 mt-2">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="cantidad" step="0.01"
                                            placeholder="Cantidad" disabled>
                                        <label for="cantidad">Cantidad</label>
                                    </div>
                                </div>
                                <div class="col-sm-4 mt-2">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="negociable">
                                            <option value="0">No Negociable</option>
                                            <option value="1">Negociable</option>
                                        </select>
                                        <label for="negociable">Tipo cheque</label>
                                    </div>
                                </div>
                                <div class="col-sm-4 mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="numcheque" placeholder="Numero de cheque">
                                        <label for="numcheque">No. de Cheque</label>
                                    </div>
                                </div>
                            </div>
                            <!-- input de paguese a la orden de -->
                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <div class="form-floating">
                                        <input disabled type="text" class="form-control" id="paguese"
                                            placeholder="Paguese a la orden de">
                                        <label for="paguese">Paguese a la orden de</label>
                                    </div>
                                </div>
                            </div>
                            <!-- input para numeros en letras -->
                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <div class="form-floating">
                                        <input disabled type="text" class="form-control" id="numletras"
                                            placeholder="La suma de (Q)">
                                        <label for="numletras">La suma de (Q)</label>
                                    </div>
                                </div>
                            </div>
                            <!-- fila de seleccion de bancos -->
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                            <option value="" disabled selected>Seleccione un banco</option>
                                            <?php
                                            $bancos = mysqli_query($conexion, "SELECT * FROM tb_bancos WHERE estado='1'");
                                            while ($banco = mysqli_fetch_array($bancos)) {
                                                echo '<option  value="' . $banco['id'] . '">' . $banco['id'] . " - " . $banco['nombre'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <label for="bancoid">Banco</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <!-- id de cuenta para edicion -->
                                        <!-- select normal -->
                                        <select class="form-select" id="cuentaid">
                                            <option value="">Seleccione una cuenta</option>
                                        </select>
                                        <label for="cuentaid">No. de Cuenta</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- REGION DE TRANSFERENCIA -->
                        <div class="container contenedort disabled" id="region_transferencia"
                            style="display: none; max-width: 100% !important;">
                            <div class="row">
                                <div class="col-sm-12  mt-2">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="cuentaaho">
                                            <option value="">Seleccione una cuenta de ahorro</option>
                                        </select>
                                        <label for="cuentaaho">Cuenta de ahorro</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col-md-4 mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="numdoc" placeholder="Numero de documento">
                                        <label for="numdoc">No. documento</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mb-1 mt-2">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="glosa" style="height: 100px" rows="1"
                                            placeholder="Concepto" <?= ($bandera) ? ' ' : 'disabled' ?>>  </textarea>
                                        <label for="glosa">Concepto</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (!$bandera) { ?>
                            <div class="alert alert-success" role="alert" style="margin-bottom: 0px !important;" id="mensaje">
                                <h4 class="alert-heading">IMPORTANTE!</h4>
                                <div class="col-md-4 mb-3">
                                    <div class="ag-courses_item card-green">
                                        <a class="ag-courses-item_link">
                                            <div class="ag-courses-item_bg"></div>
                                            <div class="ag-courses-item_title">Debe seleccionar un cliente.</div>
                                            <div class="ag-courses-item_date-box">

                                                <span class="ag-courses-item_date"> Para realizar un desembolso</span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                    <div class="container" style="max-width: 100% !important;">
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                                <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                                <button id="bt_desembolsar" class="btn btn-outline-success"
                                    onclick="if(val_aho_vin()==false)return;saveMultiDsmbls('<?= $codusu; ?>','<?= $id_agencia; ?>')"><i
                                        class="fa-solid fa-money-bill"></i> Desembolsar</button>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                    <i class="fa-solid fa-circle-xmark"></i> Salir
                                </button>
                                <!-- <button type="button" onclick="cheque_desembolso([[], [], [], ['21488']], 'pdf', 'cheque', 0)">prueba cheque</button> -->
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                include_once "../../../src/cris_modales/mdls_desembolso_indi.php";
                ?>
                <script>
                    $(document).ready(function() {
                        // $('#bt_desembolsar').hide();
                        (<?= isset($aho_vin) ? $aho_vin : 0 ?> > 0) ? ac_even('aho_vin', 'vista', 1): ac_even('aho_vin',
                            'vista', 0);
                        idPro_gas = <?= isset($aho_vin) ? $aho_vin : 0 ?>;
                        afec = 0;
                        ahorro = 0;
                    });
                    <?php
                    if ($bandera) {
                        echo 'mostrar_tabla_gastos(`' . $datos[0]['ccodcta'] . '`);';
                        echo 'consultar_gastos_monto(`' . $datos[0]['ccodcta'] . '`);';
                        echo 'concepto_default(`' . $datos[0]['nomcli'] . '`, `0`);'; ?>
                        $(`#bt_desembolsar`).show();
                    <?php } else { ?>
                        $('#bt_desembolsar').hide();
                    <?php } ?>

                    function setmonto(id, saldokp = 0, intpen = 0) {
                        saldokp = parseFloat(saldokp);
                        intpen = parseFloat(intpen);
                        $("#" + id).val(saldokp + intpen);
                    }

                    function handleSelectChange(id, select) {
                        var selectedOption = select.options[select.selectedIndex];
                        var account = selectedOption.value;
                        var saldo = parseFloat(selectedOption.dataset.saldo);
                        var intpen = parseFloat(selectedOption.dataset.intpen);
                        setmonto(id, saldo, intpen);
                    }
                </script>
    <?php
            }
            break;
    } ?>