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
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

use App\Generic\DocumentManager;
use App\Generic\FileProcessor;
use Micro\Generic\PermissionManager;
use Micro\Helpers\Log;

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
//+++++++++++++++++++++++++++++++++++
// session_start();
include '../../includes/BD_con/db_con.php';
// include '../../src/funcphp/func_gen.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {
    case 'DepoAportaciones': {
        $account = $_POST["xtra"];

        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,url_img urlfoto,numfront,numdors, tip.nombre tipoNombre,tip.tipcuen,
                    IFNULL((SELECT MAX(`numlinea`) FROM aprmov WHERE ccodaport=cta.ccodaport AND `nlibreta`= cta.nlibreta AND cestado=1),0) AS ultimonum,
                    IFNULL((SELECT MAX(`correlativo`) FROM aprmov WHERE ccodaport=cta.ccodaport AND `nlibreta`= cta.nlibreta AND cestado=1),0) AS ultimocorrel,
                        calcular_saldo_apr_tipcuenta(cta.ccodaport,?) saldo
                        FROM `aprcta` cta 
                        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                        INNER JOIN aprtip tip on tip.ccodtip=SUBSTR(cta.ccodaport,7,2)
                        WHERE `ccodaport`=? AND cli.estado=1";

        $showmensaje = false;
        try {
            if ($account == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta de aportaciones");
            }
            $database->openConnection();

            $dataAccount = $database->getSingleResult($query, [$hoy, $account]);
            if (empty($dataAccount)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de aportaciones, verifique que el código sea correcto y que el cliente esté activo");
            }

            if ($dataAccount['ultimonum'] >= ($dataAccount['numfront'] + $dataAccount['numdors'])) {
                $showmensaje = true;
                throw new Exception("El número de líneas en libreta ha llegado a su límite, se recomienda abrir otra libreta");
            }

            if ($dataAccount['estado'] != "A") {
                $showmensaje = true;
                throw new Exception("Cuenta de aportaciones Inactiva");
            }

            $bancos = $database->selectColumns("tb_bancos", ["id", "nombre"], "estado=1");

            // === NUEVO: Leer si ya está marcado como recurrente (atributo 19) ===
            $recurrente = $database->getSingleResult("SELECT 1 FROM tb_cliente_atributo
                   WHERE id_cliente  = ? AND id_atributo = 19 AND TRIM(valor) = '1' LIMIT 1 ", [$dataAccount['ccodcli']]);



            $userPermissions = new PermissionManager($idusuario);

            if ($userPermissions->isLevelOne(PermissionManager::USAR_OTROS_DOCS_APORTACIONES)) {
                $documentosTransacciones = $database->selectColumns(
                    "tb_documentos_transacciones",
                    ["id", "nombre", "tipo_dato"],
                    "estado=1 AND id_modulo=2 AND tipo=2"
                );
            }

            try {
                $docManager = new DocumentManager();
                $previewCorrel = $docManager->peekNextCorrelative([
                    'id_modulo' => 3, // Depósitos de aportaciones
                    'tipo' => 'INGRESO',
                    'usuario_id' => $idusuario,
                    'agencia_id' => $idagencia,
                ]);
            } catch (Exception $e) {
                Log::error('Error al verificar correlativo: ' . $e->getMessage());
            }

            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        //++++++++++++++++++
        include_once "../../src/cris_modales/mdls_aport_new.php";

        ?>

            <!-- APR_2_AODpstAprtcns -->
            <div class="text" style="text-align:center">DEPÓSITO DE APORTACIONES</div>
            <input type="text" id="file" value="APRT_2" style="display: none;">
            <input type="text" id="condi" value="DepoAportaciones" style="display: none;">
            <style>
                .golden {
                    color: #6BBA06;
                }

                .hidden {
                    display: none;
                }

                .input-container {
                    position: relative;

                }
            </style>
            <div class="card mb-2">
                <div class="card-header">Depósito Aportaciones</div>
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
                                <div id="contenedorVista" class="mt-2 text-center">
                                    <?php
                                    if (isset($dataAccount) && !empty($dataAccount['urlfoto'])) {
                                        $fileProcessor = new FileProcessor(__DIR__ . '/../../../');
                                        $relativePath = $dataAccount['urlfoto'];

                                        if ($fileProcessor->fileExists($relativePath)) {
                                            $fileInfo = $fileProcessor->getFileInfo($relativePath);
                                            $src = $fileInfo['data_uri'];
                                            $fileName = $fileInfo['filename'];

                                            echo $fileProcessor->getPreviewHtml($dataAccount['urlfoto'], [
                                                'max_height' => '200px',
                                                'download_btn_text' => 'Descargar PDF',
                                                'view_btn_text' => 'Ver PDF',
                                                'show_filename' => false
                                            ]);
                                        } else {
                                            echo '<img src="' . BASE_URL . 'assets/img/userdefault.png" class="img-fluid rounded-circle" alt="Foto de Cliente" style="max-height: 150px; max-width: 150px;">';
                                        }
                                    } else {
                                        echo '<img src="' . BASE_URL . 'assets/img/userdefault.png" class="img-fluid rounded-circle" alt="Foto de Cliente" style="max-height: 150px; max-width: 150px;">';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-lg-8 col-sm-6 col-md-8">
                                <div class="row">
                                    <div class="col-sm-8 col-md-8 col-lg-8">
                                        <span class="input-group-addon col-8">Cuenta de Aportación</span>
                                        <input type="text" class="form-control " id="ccodaport" required placeholder="   -   -  -  "
                                            value="<?= $account ?? '' ?>">
                                    </div>
                                    <div class="col-sm-4 col-md-4 col-lg-4">
                                        <br>
                                        <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                            title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaport')">
                                            <i class="fa fa-check-to-slot"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                            title="Buscar cuenta" data-bs-toggle="modal" data-bs-target="#findaportcta">
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

                    <div class="container contenedort">
                        <div class="row mb-3">
                            <div class="col-sm-10 col-md-5 col-lg-4">
                                <span class="input-group-addon col-8">No.Documento</span>
                                <input type="text" class="form-control " id="cnumdoc" required
                                    value="<?= $previewCorrel['valor'] ?? '' ?>">
                            </div>
                            <div class="col-sm-10 col-md-5 col-lg-4">
                                <span class="input-group-addon col-8">Fecha</span>
                                <input type="date" class="form-control " id="dfecope" value="<?php echo date("Y-m-d"); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <span class="input-group-addon">Cantidad:</span>
                                <input type="number" step="any" class="form-control" id="monto" required placeholder="0.00" min="1">
                            </div>
                            <div class="col-md-4">
                                <span class="input-group-addon">Cuota de ingreso:</span>
                                <input type="number" step="any" class="form-control" id="cuotaIngreso" placeholder="0.00" min="1">
                            </div>
                            <div class="col-sm-10 col-md-5 col-lg-4">
                                <span class="input-group-addon col-8">Libreta</span>
                                <input type="text" class="form-control" id="lib" value="<?= $dataAccount['nlibreta'] ?? 0 ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="row mb-3 hidden" id="result-section">
                            <div class="col-sm-4">
                                <div class="input-container">
                                    <span class="input-group-addon col-8 golden">Cantidad Total</span>
                                    <input type="text" class="form-control golden" id="monto_view" disabled placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-sm-7">
                                <div class="input-container">
                                    <span class="input-group-addon col-8 golden">Cantidad en letras</span>
                                    <input type="text" class="form-control golden" id="monto_letras" disabled placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <!--compo con-->
                        <div class="row mb-3">
                            <div class="col-sm-12">
                                <div class="input-container">
                                    <span class="input-group-addon col-2">Concepto</span>
                                    <textarea class="form-control" id="concepto" rows="4" placeholder="Ingrese el concepto aquí"
                                        required></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort">
                            <br>
                            <div class="row mb-3">
                                <div class="col-sm-12 col-md-4 col-lg-4">
                                    <span class="input-group-addon col-8">Transacción</span>
                                    <input type="text" class="form-control" id="tipoNombre"
                                        value="<?= $dataAccount['tipoNombre'] ?? '' ?>" readonly>
                                </div>
                                <div class="col-sm-12 col-md-4 col-lg-4">
                                    <span class="input-group-addon col-8">Salida</span>
                                    <select class="form-select  col-md-12" aria-label="Default select example" id="salida">
                                        <option value="1" selected>Con Libreta</option>
                                        <option value="0">Sin Libreta</option>
                                    </select>
                                </div>
                                <div class="col-sm-12 col-md-4 col-lg-4">
                                    <span class="input-group-addon col-8">Tipo de Doc.</span>
                                    <select class="form-select  col-sm-12" aria-label="Default select example" id="tipdoc"
                                        onchange="tipdoc(this.value)">
                                        <option value="E" selected>EFECTIVO</option>
                                        <option value="D">CON BOLETA DE BANCO</option>
                                    <?php if (isset($documentosTransacciones) && !empty($documentosTransacciones)): ?>
                                            <optgroup label="Otros tipos de documentos">
                                            <?php foreach ($documentosTransacciones as $doc): ?>
                                                    <option value="<?= $doc['id']; ?>"><?= htmlspecialchars($doc['nombre']); ?></option>
                                            <?php endforeach; ?>
                                            </optgroup>
                                    <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="container contenedort" id="region_cheque" style="display: none; max-width: 100% !important;">
                            <h6>DATOS DEL BANCO DONDE SE HIZO EL DEPÓSITO</h6>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                            <option value="0" disabled selected>Seleccione un banco</option>
                                            <?php
                                            if (isset($bancos) && is_array($bancos)) {
                                                foreach ($bancos as $banco) {
                                                    echo '<option value="' . $banco['id'] . '">' . $banco['nombre'] . '</option>';
                                                }
                                            } else {
                                                echo '<option value="0">No hay bancos disponibles</option>';
                                            }
                                            ?>
                                        </select>
                                        <label for="bancoid">Banco</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="cuentaid">
                                            <option value="0">Seleccione una cuenta</option>
                                        </select>
                                        <label for="cuentaid">No. de Cuenta</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="cnumdocboleta" placeholder="No de Boleta">
                                        <label for="cnumdocboleta">No. Boleta Banco</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="input-group-addon">Fecha de boleta:</span>
                                    <input type="date" class="form-control" id="fechaBoleta" value="<?php echo date("Y-m-d"); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3 justify-items-md-center">
                            <div class="col align-items-center" id="modal_footer">
                            <?php if ($status): ?>
                                    <button type="button" id="btnSave" class="btn btn-outline-success" onclick="confirmSaveaprt('D')">
                                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                                    </button>
                            <?php endif; ?>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function confirmSaveaprt(action) {
                        var cuota_ingreso = parseFloat($("#cuotaIngreso").val());
                        var cantidad = parseFloat($("#monto").val());
                        var aprtip = $('#aprtip').val();
                        // console.log(cuota_ingreso);
                        var concepto = $('#concepto').val();
                        // console.log(concepto);

                        if (!isNaN(cantidad) && !isNaN(cuota_ingreso)) {
                            cantidad = cantidad + cuota_ingreso;
                        }
                        if (!isNaN(cantidad)) {
                            Swal.fire({
                                title: "Deseas " + "Depositar" + " la cantidad de Q." + cantidad + "?",
                                text: " ",
                                icon: "question",
                                showCancelButton: true,
                                confirmButtonText: "Sí, " + (action === 'D' ? "Guardar" : ""),
                                confirmButtonColor: '#4CAF50',
                                cancelButtonText: "Cancelar"
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    obtiene(['ccodaport', 'dfecope', 'cnumdoc', 'monto', 'cuotaIngreso', 'cnumdocboleta', 'concepto', 'fechaBoleta'], [
                                        'salida', 'tipdoc', 'bancoid', 'cuentaid', 'tipdoc'
                                    ], [], 'cdaportmov', '0', ['<?= $account ?>', action]);
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Oops...",
                                text: "Tiene que ingresar un monto"
                            });
                        }
                    }

                    // Función para actualizar los campos de cantidad total y cantidad en letras 
                    // tambien actualiza el concepto automáticamente con los datos recidbos
                    function actualizarTotales() {
                        const monto = parseFloat(document.getElementById('monto').value) || 0;
                        const cuotaIngreso = parseFloat(document.getElementById('cuotaIngreso').value) || 0;
                        const montoView = document.getElementById('monto_view');
                        const montoLetras = document.getElementById('monto_letras');
                        const resultSection = document.getElementById('result-section');
                        const concepto = document.getElementById('concepto');
                        const name = document.getElementById('name').value;
                        // const tipoMovimiento = document.getElementById('tipdoc').value === 'E' ? 'DEPOSITO' : 'RETIRO'; // Determinar si es depósito o retiro


                        if (monto || cuotaIngreso) {
                            resultSection.classList.remove('hidden');

                            const total = monto + cuotaIngreso;

                            montoView.value = numberWithCommas(total.toFixed(2));

                            montoLetras.value = convertirNumeroALetras(total);
                            // Generar el concepto automáticamente
                            const conceptoTexto = `DEPOSITO DE APORTACIONES DE ${name} POR UN MONTO DE Q${montoView.value} (${montoLetras.value})`;

                            concepto.value = conceptoTexto;
                        } else {
                            resultSection.classList.add('hidden');
                        }
                    }
                    //ACTUALIZAR MESANJE PRE CONSUTRUIDO CON CONSULTA 

                    // Event listeners 
                    document.getElementById('monto').addEventListener('input', actualizarTotales);
                    document.getElementById('cuotaIngreso').addEventListener('input', actualizarTotales);

                    actualizarTotales();
                </script>


            <?php
    }
        break;
    //00100201000006

    case 'RetiroAportaciones': {
        $account = $_POST["xtra"];
        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,url_img urlfoto,numfront,numdors, tip.nombre tipoNombre,
                IFNULL((SELECT MAX(`numlinea`) FROM aprmov WHERE ccodaport=cta.ccodaport AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimonum,
                IFNULL((SELECT MAX(`correlativo`) FROM aprmov WHERE ccodaport=cta.ccodaport AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimocorrel,
                    calcular_saldo_apr_tipcuenta(cta.ccodaport,?) saldo,cta.ret
                    FROM `aprcta` cta 
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                    INNER JOIN aprtip tip on tip.ccodtip=SUBSTR(cta.ccodaport,7,2)
                    WHERE `ccodaport`=? AND cli.estado=1";
        // $query3 = "SELECT MAX(CAST(mov.cnumdoc AS SIGNED)) FROM aprmov mov
        //         INNER JOIN tb_usuario usu ON usu.id_usu=mov.codusu
        //         INNER JOIN tb_agencia ofi ON ofi.id_agencia=usu.id_agencia
        //         WHERE ctipope = 'R' AND crazon = 'RETIRO' AND cestado = 1 AND ofi.id_agencia=? AND cestado!=2";
        $src = '../../includes/img/fotoClienteDefault.png';
        // $flag_correlativo = 1; //BANDERA PARA ACTIVAR CORRELATIVO AUTOMATICO
        $showmensaje = false;
        $inputCorrel = '';
        try {
            if ($account == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta de aportaciones");
            }
            $database->openConnection();

            $data = $database->getAllResults($query, [$hoy, $account]);
            if (empty($data)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de aportaciones");
            }
            $dataAccount = $data[0];

            if ($dataAccount['ultimonum'] >= ($dataAccount['numfront'] + $dataAccount['numdors'])) {
                $showmensaje = true;
                throw new Exception("El número de líneas en libreta ha llegado a su límite, se recomienda abrir otra libreta");
            }

            if ($dataAccount['estado'] != "A") {
                $showmensaje = true;
                throw new Exception("Cuenta de aportaciones Inactiva");
            }

            if ($dataAccount['ret'] != 1) {
                switch ($dataAccount['ret']) {
                    case 0:
                        /**
                         * POR GARANTIA
                         */
                        $query2 = "SELECT cm.Cestado FROM tb_garantias_creditos tgc 
                                        INNER JOIN cli_garantia cg ON cg.idGarantia = tgc.id_garantia 
                                        INNER JOIN aprcta cta ON cta.ccodaport = cg.descripcionGarantia
                                        INNER JOIN cremcre_meta cm ON cm.CCODCTA = tgc.id_cremcre_meta
                                        WHERE cta.ccodaport = ? AND cg.estado=1 AND cg.idTipoDoc=18;";

                        $estadoRetiro = $database->getSingleResult($query2, [$account]);

                        if (!empty($estadoRetiro) && $estadoRetiro['Cestado'] == 'F') {
                            $showmensaje = true;
                            throw new Exception("No es posible realizar retiros: la cuenta de aportaciones está bloqueada porque sirve como garantía de un crédito que aún no ha sido cancelado.");
                        }
                        if (!empty($estadoRetiro) && $estadoRetiro['Cestado'] == 'G') {
                            $update = $database->update("aprcta", ['ret' => 1], "ccodaport = ?", [$account]);
                        }

                        break;
                    default:
                        $showmensaje = true;
                        throw new Exception("Cuenta de aportaciones bloqueada para retiros, consulte con el administrador");
                }
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

            // if ($flag_correlativo == 1) {
            //     $correlativo = $database->getAllResults($query3, [$idagencia]);
            // }

            $previewCorrel = null;
            $configMessage = '';
            try {
                $docManager = new DocumentManager();
                $previewCorrel = $docManager->peekNextCorrelative([
                    'id_modulo' => 3,
                    'tipo' => 'EGRESO',
                    'usuario_id' => $idusuario,
                    'agencia_id' => $idagencia,
                ]);
            } catch (Exception $e) {
                $configMessage = 'Error al verificar correlativo: ' . $e->getMessage();
            }

            $inputCorrel = $previewCorrel['valor'] ?? "";

            $bancos = $database->selectColumns("tb_bancos", ["id", "nombre"], "estado=1");

            $otrosTitulares = $database->getAllResults(
                "SELECT cli.short_name, cli.no_identifica FROM cli_mancomunadas man
                        INNER JOIN tb_cliente cli ON man.ccodcli = cli.idcod_cliente
                        WHERE man.ccodaho = ? AND tipo='aportacion' AND man.estado='1' AND cli.estado=1",
                [$account]
            );

            $mensaje = "Proceda con la ejecución del retiro de aportaciones";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        include_once "../../src/cris_modales/mdls_aport_new.php";
        $sessionSerial = generarCodigoAleatorio();
        ?>
                <div class="text" style="text-align:center">RETIRO DE APORTACIONES</div>
                <input type="text" id="file" value="APRT_2" style="display: none;">
                <input type="text" id="condi" value="RetiroAportaciones" style="display: none;">
                <style>
                    .golden {
                        color: #F94666;
                    }

                    .hidden {
                        display: none;
                    }

                    .input-container {
                        position: relative;

                    }
                </style>
                <div class="card mb-2">
                    <div class="card-header">Retiro Aportaciones</div>
                    <div class="card-body">
                    <?php if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>!!</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                    <?php } ?>
                    <?php if ($status) { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>!!</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                    <?php } ?>
                        <div class="container contenedort">
                            <div class="row mb-3">
                                <div class="col-lg-2 col-sm-6 col-md-4 mt-2">
                                    <img width="130" height="150" id="vistaPrevia" src="<?= $src ?? '' ?>">
                                </div>
                                <div class="col-lg-8 col-sm-6 col-md-8">
                                    <div class="row">
                                        <div class="col-sm-8 col-md-8 col-lg-8">
                                            <span class="input-group-addon">Cuenta de Aportación</span>
                                            <input type="text" class="form-control " id="ccodaport" required
                                                placeholder="   -   -  -  " value="<?= $account ?? '' ?>">
                                        </div>
                                        <div class="col-sm-4 col-md-4 col-lg-4">
                                            <br>
                                            <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                                title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaport')">
                                                <i class="fa fa-check-to-slot"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                                title="Buscar cuenta" data-bs-toggle="modal" data-bs-target="#findaportcta">
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
                            <div class="row">
                                <div class="col-sm-12">
                                <?php if (isset($otrosTitulares) && !empty($otrosTitulares)): ?>
                                        <div class="alert alert-info" role="alert">
                                            <h5 class="alert-heading">Otros Titulares de la Cuenta</h5>
                                            <ul class="mb-0">
                                            <?php foreach ($otrosTitulares as $titular): ?>
                                                    <li><?= htmlspecialchars($titular['short_name']) ?> (IDENTIFICACION:
                                                    <?= htmlspecialchars($titular['no_identifica']) ?>)
                                                    </li>
                                            <?php endforeach; ?>
                                            </ul>
                                        </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort">
                            <div class="row mb-3">
                                <div class="col-sm-10 col-md-5 col-lg-4">
                                    <span class="input-group-addon col-8">No.Documento</span>
                                    <input type="text" class="form-control " id="cnumdoc" required
                                        value="<?= $inputCorrel ?? '' ?>">
                                </div>
                                <div class="col-sm-10 col-md-5 col-lg-4">
                                    <br>
                                    <span class="badge text-bg-primary" style="font-size: 1.2em; font-weight: bold;">Saldo:
                                    <?= number_format($dataAccount['saldo'] ?? 0, 2, '.', ',') ?></span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-10 col-md-5 col-lg-4">
                                    <span class="input-group-addon col-8">Cantidad a Retirar</span>
                                    <input type="number" step="any" class="form-control " id="monto" required placeholder="0.00"
                                        min="0.01">
                                </div>
                                <div class="col-sm-10 col-md-5 col-lg-4">
                                    <span class="input-group-addon col-8">Libreta</span>
                                    <input type="number" class="form-control" id="lib" value="<?= $dataAccount['nlibreta'] ?? 0 ?>"
                                        readonly>
                                </div>
                            </div>
                            <div class="row mb-3 hidden" id="result-section">
                                <div class="col-sm-4">
                                    <div class="input-container">
                                        <span class="input-group-addon col-8 golden">Cantidad</span>
                                        <input type="text" class="form-control golden" id="monto_view" disabled placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-sm-7">
                                    <div class="input-container">
                                        <span class="input-group-addon col-8 golden">Cantidad en letras</span>
                                        <input type="text" class="form-control golden" id="monto_letras" disabled
                                            placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-10 col-md-5 col-lg-4">
                                    <span class="input-group-addon col-8">Fecha</span>
                                    <input type="date" class="form-control " id="dfecope" value="<?php echo date("Y-m-d"); ?>">
                                </div>
                            </div>
                            <div class="row mb-3" style="display: none;">
                                <div class="col-md-4">
                                    <span class="input-group-addon">Cuota</span>
                                    <input type="number" step="any" class="form-control " id="cuotaIngreso" placeholder="0.00"
                                        min="1">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-12">
                                    <div class="input-container">
                                        <span class="input-group-addon col-2">Concepto</span>
                                        <textarea class="form-control" id="concepto" rows="4" placeholder="Ingrese el concepto aquí"
                                            required></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="container contenedort">
                                <br>
                                <div class="row mb-3">
                                    <div class="col-sm-12 col-md-4 col-lg-4">
                                        <span class="input-group-addon col-8">Transacción</span>
                                        <input type="text" class="form-control" id="tipoNombre"
                                            value="<?= $dataAccount['tipoNombre'] ?? '' ?>" readonly>
                                    </div>
                                    <div class="col-sm-12 col-md-4 col-lg-4">
                                        <span class="input-group-addon col-8">Salida</span>
                                        <select class="form-select  col-md-12" aria-label="Default select example" id="salida">
                                            <option value="1" selected>Con Libreta</option>
                                            <option value="0">Sin Libreta</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-12 col-md-4 col-lg-4">
                                        <span class="input-group-addon col-8">Tipo de Doc.</span>
                                        <select class="form-select  col-sm-12" aria-label="Default select example" id="tipdoc"
                                            onchange="tipdoc(this.value)">
                                            <option value="E" selected>EFECTIVO</option>
                                            <option value="C">CON CHEQUE</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="container contenedort" id="region_cheque"
                                style="display: none; max-width: 100% !important;">
                                <h6>DATOS DEL BANCO DONDE SE EMITIRÁ EL CHEQUE</h6>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                                <option value="0" disabled selected>Seleccione un banco</option>
                                                <?php
                                                if (isset($bancos)) {
                                                    foreach ($bancos as $banco) {
                                                        echo '<option  value="' . $banco['id'] . '">' . $banco['id'] . " - " . $banco['nombre'] . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <label for="bancoid">Banco</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="cuentaid" onchange="cheque_automatico(this.value,0)">
                                                <option value="0">Seleccione una cuenta</option>
                                            </select>
                                            <label for="cuentaid">No. de Cuenta</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-6 mt-2">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="negociable">
                                                <option value="0">No Negociable</option>
                                                <option value="1">Negociable</option>
                                            </select>
                                            <label for="negociable">Tipo cheque</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 mb-3 mt-2">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="numcheque" placeholder="Numero de cheque"
                                                step="1">
                                            <label for="numcheque">No. de Cheque</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="text" id="srnPc" hidden value="">
                            <input type="text" id="sessionSerial" hidden value="<?= $sessionSerial ?>">
                            <div class="row mb-1 pt-2 justify-items-md-center">
                                <div class="col align-items-center" id="modal_footer">
                                <?php if ($status) {
                                    ?>
                                        <button type="button" id="btnSave" class="btn btn-outline-success"
                                            onclick="confirmSaveaprt('R')">
                                            <i class="fa-solid fa-floppy-disk"></i> Guardar
                                        </button>
                                    <?php
                                }
                                ?>
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
                        function confirmSaveaprt(action) {
                            // Validar que se haya ingresado monto y que sea mayor a cero
                            var cantidad = parseFloat(document.getElementById("monto").value);
                            if (isNaN(cantidad) || cantidad <= 0) {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: "Ingrese un monto válido mayor a cero."
                                });
                                return;
                            }

                            // Validar que se haya ingresado un concepto
                            var concepto = document.getElementById("concepto").value.trim();
                            if (concepto === "") {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: "El campo concepto es obligatorio."
                                });
                                return;
                            }

                            // Validar que la fecha esté presente
                            var fecha = document.getElementById("dfecope").value;
                            if (fecha === "") {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: "Debe indicar la fecha de la transacción."
                                });
                                return;
                            }

                            // Validar que el monto no exceda el saldo (si es retiro)
                            var saldoActual = parseFloat('<?= $dataAccount["saldo"] ?? 0 ?>');
                            if (action === 'R' && cantidad > saldoActual) {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: "El monto a retirar es mayor que el saldo disponible (" + saldoActual + ")."
                                });
                                return;
                            }

                            // Si es retiro, primero verificar huella y luego preguntar
                            // if (action === 'R') {
                            verifyFingerprint(function () {
                                // Después de verificar huella, verificar si el saldo quedará en cero
                                if (cantidad === saldoActual) {
                                    Swal.fire({
                                        title: '¡Advertencia!',
                                        text: 'Este retiro dejará la cuenta con saldo 0. ¿Desea inactivar la cuenta?',
                                        icon: 'warning',
                                        showDenyButton: true,
                                        showCancelButton: true,
                                        confirmButtonText: 'Sí, inactivar',
                                        denyButtonText: 'No, mantener activa',
                                        cancelButtonText: 'Cancelar operación'
                                    }).then((result) => {
                                        if (result.isDismissed) {
                                            return; // Cancelar operación
                                        }
                                        // Se muestra un segundo mensaje de confirmación
                                        Swal.fire({
                                            title: "¿Desea retirar la cantidad de Q." + cantidad.toLocaleString('es-GT', {
                                                minimumFractionDigits: 2
                                            }) + "?",
                                            text: result.isConfirmed ? "La cuenta será inactivada" : "La cuenta se mantendrá activa",
                                            icon: "question",
                                            showCancelButton: true,
                                            confirmButtonText: "Sí, continuar",
                                            confirmButtonColor: '#4CAF50',
                                            cancelButtonText: "Cancelar"
                                        }).then(async (confirmResult) => {
                                            if (confirmResult.isConfirmed) {
                                                if (result.isConfirmed) {
                                                    // Si se confirma la inactivación, se ejecuta el proceso de retiro y se inactiva la cuenta
                                                    try {
                                                        await retirar(); // 1️⃣ primero el retiro
                                                        await inactivarCuentaAPRT(); // 2️⃣ luego la inactivación
                                                        Swal.fire("Proceso completado",
                                                            "El retiro se realizó y la cuenta ha sido inactivada.",
                                                            "success");
                                                    } catch (e) {
                                                        // Si falló el retiro, NO intentes inactivar: entraremos aquí.
                                                        Swal.fire("Error", e || "No se pudo completar la operación", "error");
                                                    }
                                                } else {
                                                    // Si se decide mantener la cuenta activa, solo se procesa el retiro
                                                    await retirar();
                                                }
                                            }
                                        });
                                    });
                                } else {
                                    // Retiro parcial - solo preguntar confirmación
                                    Swal.fire({
                                        title: "¿Desea retirar la cantidad de Q." + cantidad.toLocaleString('es-GT', {
                                            minimumFractionDigits: 2
                                        }) + "?",
                                        text: " ",
                                        icon: "question",
                                        showCancelButton: true,
                                        confirmButtonText: "Sí, retirar",
                                        confirmButtonColor: '#4CAF50',
                                        cancelButtonText: "Cancelar"
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            retirar();
                                        }
                                    });
                                }
                            }, 4, '<?= $account ?? 0; ?>');
                            // } else {
                            //     // Para operación de depósito
                            //     Swal.fire({
                            //         title: "¿Desea depositar la cantidad de Q." + cantidad.toLocaleString('es-GT', {minimumFractionDigits: 2}) + "?",
                            //         text: " ",
                            //         icon: "question",
                            //         showCancelButton: true,
                            //         confirmButtonText: "Sí, continuar",
                            //         confirmButtonColor: '#4CAF50',
                            //         cancelButtonText: "Cancelar"
                            //     }).then((result) => {
                            //         if (result.isConfirmed) {
                            //             verifyFingerprint(retirar, 4, '<?= $account ?? 0; ?>');
                            //         }
                            //     });
                            // }
                        }

                        // Basado en tu wrapper 'obtiene'. Ajusta si usas axios / $.ajax directamente.
                        function retirar() {
                            return new Promise((resolve, reject) => {
                                // obtiene(inputs, selects, radios, condi, id, archivo, callback = 'NULL', confirmacion = false, mensaje = "¿Desea continuar con el proceso?")
                                obtiene(
                                    ['ccodaport', 'dfecope', 'cnumdoc', 'monto', 'cuotaIngreso', 'numcheque', 'concepto'],
                                    ['salida', 'tipdoc', 'bancoid', 'cuentaid', 'negociable'],
                                    [],
                                    'cdaportmov',
                                    '0',
                                    ['<?= $account ?>', 'R'],
                                    function (resp) {
                                        resolve();
                                    }
                                );
                            });
                        }


                        // Función para inactivar la cuenta de aportaciones vía AJAX
                        function inactivarCuentaAPRT() {
                            return new Promise(function (resolve, reject) {
                                $.ajax({
                                    url: '../src/cruds/crud_aportaciones.php', // Ajusta la URL si es necesario
                                    method: 'POST',
                                    data: {
                                        condi: 'Update_inactivarAPRT',
                                        cuenta: '<?= $account ?>',
                                        fecha_cancel: '' // Puedes enviar una fecha o dejarla vacía para usar la fecha actual
                                    },
                                    success: function (response) {
                                        console.log("Respuesta inactivarCuentaAPRT:", response);
                                        let data = JSON.parse(response);
                                        if (data[1] === '1') {
                                            Swal.fire("Cuenta inactivada exitosamente", "", "success");
                                            resolve();
                                        } else {
                                            Swal.fire("Error", data[0], "error");
                                            reject();
                                        }
                                    },
                                    error: function () {
                                        Swal.fire("Error", "No se pudo actualizar la cuenta", "error");
                                        reject();
                                    }
                                });
                            });
                        }

                        // Función para la actualización de los campos de cantidad total, cantidad en letras
                        // y para actualizar el concepto de forma automática con los datos recibidos
                        function actualizarTotales() {
                            const monto = parseFloat(document.getElementById('monto').value) || 0;
                            const cuotaIngreso = parseFloat(document.getElementById('cuotaIngreso').value) || 0;
                            const montoView = document.getElementById('monto_view');
                            const montoLetras = document.getElementById('monto_letras');
                            const resultSection = document.getElementById('result-section');
                            const concepto = document.getElementById('concepto');
                            const name = document.getElementById('name').value;
                            // Determinar si es RETIRO o DEPÓSITO (por ejemplo, 'E' = retiro, de lo contrario depósito)
                            const tipoMovimiento = document.getElementById('tipdoc').value === 'E' ? 'RETIRO' : 'DEPÓSITO';

                            if (monto || cuotaIngreso) {
                                resultSection.classList.remove('hidden');

                                const total = monto + cuotaIngreso;
                                montoView.value = numberWithCommas(total.toFixed(2));
                                montoLetras.value = convertirNumeroALetras(total);

                                // Generar el concepto automáticamente para aportaciones
                                const conceptoTexto = `${tipoMovimiento} DE APORTACIONES DE ${name} POR UN MONTO DE Q${montoView.value} (${montoLetras.value})`;
                                concepto.value = conceptoTexto;
                            } else {
                                resultSection.classList.add('hidden');
                            }
                        }

                        // Event listeners para actualizar totales y concepto al cambiar los valores
                        document.getElementById('monto').addEventListener('input', actualizarTotales);
                        document.getElementById('cuotaIngreso').addEventListener('input', actualizarTotales);

                        actualizarTotales();
                    </script>


                <?php
    }
        break;

    case 'ActualizacionLibreta': {
        $id = $_POST["xtra"];
        $datoscli = mysqli_query($conexion, "SELECT * FROM `aprcta` WHERE `ccodaport`=$id");
        $bandera = "No se encontró la cuenta ingresada, verifique el número de cuenta o si el cliente esta activo.";
        while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
            $idcli = encode_utf8($da["ccodcli"]);
            $nit = encode_utf8($da["num_nit"]);
            $nlibreta = encode_utf8($da["nlibreta"]);
            $bandera = "";
        }
        if ($bandera == "") {
            $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente` = '$idcli'");
            $dat = mysqli_fetch_array($data, MYSQLI_ASSOC);
            $nombre = $dat["short_name"];

            $mov = mysqli_query($conexion, "SELECT id_mov,cnumdoc, dfecope, monto, crazon, ctipope, numlinea, correlativo, lineaprint FROM aprmov WHERE `ccodaport`=$id and nlibreta = $nlibreta and cestado = 1 ORDER BY numlinea asc");
            $movimientos = mysqli_fetch_all($mov, MYSQLI_ASSOC);
        }
        ?>
                    <!--Aho-1-ImprsnLbrta Impresión Libreta -->
                    <div class="card shadow-sm border-0">
                        <input type="hidden" id="file" value="APRT_2">
                        <input type="hidden" id="condi" value="ActualizacionLibreta">

                        <div class="card-header bg-primary text-white py-3">
                            <h4 class="mb-0">
                                <i class="fas fa-receipt me-2"></i>Actualización de Libreta
                            </h4>
                        </div>

                        <div class="card-body p-4">
                            <!-- Sección de Búsqueda de Cuenta -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="ccodaho" class="form-label fw-semibold">Codigo de Cuenta</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="ccodaport" placeholder="000-000-00-000000"
                                            value="<?= $bandera === "" ? $id : "" ?>">
                                        <button class="btn btn-outline-primary" type="button" title="Aplicar cuenta"
                                            onclick="aplicarcod('ccodaport')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" type="button" title="Buscar cuenta"
                                            data-bs-toggle="modal" data-bs-target="#findaportcta">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Mensajes de Error -->
                        <?php if ($bandera !== "" && $id !== "0"): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <strong>Error:</strong> <?= htmlspecialchars($bandera) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                        <?php endif; ?>

                            <!-- Información de la Cuenta -->
                        <?php if ($bandera === ""): ?>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold text-muted">Libreta</label>
                                        <input type="text" class="form-control bg-light" id="libreta" readonly
                                            value="<?= htmlspecialchars($nlibreta ?? "") ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold text-muted">NIT</label>
                                        <input type="text" class="form-control bg-light" id="nit" readonly
                                            value="<?= htmlspecialchars($nit ?? "") ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-muted">Nombre del Cliente</label>
                                        <input type="text" class="form-control bg-light" id="name" readonly
                                            value="<?= htmlspecialchars($nombre ?? "") ?>">
                                    </div>
                                </div>

                                <!-- Tabla de Movimientos -->
                                <div class="card border-0 bg-light mb-4">
                                    <div class="card-header bg-secondary text-white py-2">
                                        <h6 class="mb-0">
                                            <i class="fas fa-receipt me-2"></i>Movimientos de la Cuenta
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">
                                            <i class="fas fa-info-circle me-1"></i>Selecciona las operaciones que deseas imprimir
                                        </p>

                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0" id="tablaMovimientos">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 50px;">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="checkAll"
                                                                    onclick="toggleAllChecks(this)">
                                                            </div>
                                                        </th>
                                                        <th>No.</th>
                                                        <th>Línea</th>
                                                        <th>No. Doc.</th>
                                                        <th>Fecha</th>
                                                        <th>Monto</th>
                                                        <th>Razón</th>
                                                        <th>Operación</th>
                                                        <th>Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    if (!empty($movimientos)) {
                                                        foreach ($movimientos as $mov) {
                                                            $isImpreso = $mov['lineaprint'] === 'S';
                                                            $tipope = $mov['ctipope'] === 'D' ? 'Depósito' : 'Retiro';
                                                            $operacionBadge = $mov['ctipope'] === 'D'
                                                                ? '<span class="badge bg-success"><i class="fas fa-plus me-1"></i>Depósito</span>'
                                                                : '<span class="badge bg-danger"><i class="fas fa-minus me-1"></i>Retiro</span>';
                                                            $estadoBadge = $isImpreso
                                                                ? '<span class="badge bg-info"><i class="fas fa-check me-1"></i>Impreso</span>'
                                                                : '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>No Impreso</span>';
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input check-item" type="checkbox"
                                                                            value="<?= htmlspecialchars($mov['id_mov']) ?>"
                                                                            id="check<?= htmlspecialchars($mov['id_mov']) ?>" name="checklist[]"
                                                                        <?= !$isImpreso ? 'checked' : '' ?>>
                                                                    </div>
                                                                </td>
                                                                <td><?= htmlspecialchars($mov['correlativo']) ?></td>
                                                                <td><?= htmlspecialchars($mov['numlinea']) ?></td>
                                                                <td><code><?= htmlspecialchars($mov['cnumdoc']) ?></code></td>
                                                                <td><?= setdatefrench($mov['dfecope']) ?></td>
                                                                <td>
                                                                    <strong class="text-success">Q
                                                                    <?= number_format($mov['monto'], 2, '.', ',') ?></strong>
                                                                </td>
                                                                <td><?= htmlspecialchars($mov['crazon']) ?></td>
                                                                <td><?= $operacionBadge ?></td>
                                                                <td><?= $estadoBadge ?></td>
                                                            </tr>
                                                        <?php
                                                        }
                                                    } else {
                                                        ?>
                                                        <tr>
                                                            <td colspan="9" class="text-center py-4 text-muted">
                                                                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                                                No hay movimientos disponibles
                                                            </td>
                                                        </tr>
                                                    <?php
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                        <?php endif; ?>

                            <!-- Botones de Acción -->
                            <div class="d-flex gap-2 justify-content-center">
                            <?php if ($bandera === ""): ?>
                                    <button type="button" class="btn btn-primary btn-lg"
                                        onclick="checkmov('<?= htmlspecialchars($id) ?>')">
                                        <i class="fas fa-print me-2"></i>Imprimir Seleccionados
                                    </button>
                            <?php endif; ?>
                                <button type="button" class="btn btn-outline-danger btn-lg" onclick="printdiv2('#cuadro','0')">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </button>
                            </div>
                        </div>
                    </div>

                    <script>
                        function toggleAllChecks(source) {
                            const checkboxes = document.querySelectorAll('.check-item');
                            checkboxes.forEach(cb => cb.checked = source.checked);
                        }

                        document.addEventListener('DOMContentLoaded', function () {
                            const all = document.getElementById('checkAll');
                            document.querySelectorAll('.check-item').forEach(cb => {
                                cb.addEventListener('change', function () {
                                    if (!this.checked) {
                                        all.checked = false;
                                    } else {
                                        const allChecked = Array.from(document.querySelectorAll('.check-item')).every(x => x.checked);
                                        all.checked = allChecked;
                                    }
                                });
                            });
                        });

                        function checkmov(id) {
                            const selectedCheckboxes = document.querySelectorAll('input[name="checklist[]"]:checked');
                            const selectedValues = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

                            if (selectedValues.length === 0) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Sin selección',
                                    text: 'Selecciona al menos una línea para imprimir.',
                                    confirmButtonColor: '#3085d6'
                                });
                                return;
                            }

                            generico([], [], [], [], [], [], 'lprint', id, [selectedValues], function (data) {
                                Lib_reprint(data[3], data[2], data[4][0]['nombre']);
                            });
                        }
                    </script>
                <?php
    }
        break;

    case 'Certificados_aprt': {
        $codusu = $_SESSION['id'];
        $id = $_POST["xtra"];
        ?>

                    <div class="card">
                        <input type="text" id="file" value="APRT_2" style="display: none;">
                        <input type="text" id="condi" value="Certificados_aprt" style="display: none;">
                        <div class="card-header">Actualización de libreta</div>
                        <div class="card-body">
                            <div class="container contenedort" style="padding: 10px 8px 10px 8px !important;">
                                <!--Aho_0_iMprsnLbrt Libreta-->
                                <div class="row">
                                    <div class="col">
                                        <div class="table-responsive">
                                            <table id="table_id2" class="table table-hover table-border">
                                                <thead class="text-light table-head-aprt">
                                                    <tr>
                                                        <th>Crt.</th>
                                                        <th>Codigo de cliente</th>
                                                        <th>Cuenta</th>
                                                        <th>Monto</th>
                                                        <th>Fec. Certificado</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="categoria_tb">
                                                    <?php
                                                    $query = mysqli_query($conexion, "SELECT apr.* FROM `aprcrt` apr INNER JOIN tb_cliente cli on cli.idcod_cliente=apr.ccodcli");
                                                    while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
                                                        $idcrt = encode_utf8($row["id_crt"]);
                                                        $codcrt = encode_utf8($row["ccodcrt"]);
                                                        $codcli = encode_utf8($row["ccodcli"]);
                                                        $ccodaport = encode_utf8($row["ccodaport"]);
                                                        $monto = encode_utf8($row["montoapr"]);
                                                        $fecap = encode_utf8($row["fec_crt"]);
                                                        $estado = encode_utf8($row["estado"]);

                                                        ($estado == "I" || $estado == "R") ? $bt = '<button type="button" class="btn btn-warning ms-1" title="Imprimir certificado" onclick="modal_cambio_certif(' . $idcrt . ',' . $ccodaport . ',' . $codusu . ')">
                                                <i class="fa-solid fa-arrow-rotate-left"></i>
                                                </button>
                                                </td>
                                                </tr>' : $bt = '</td></tr>';

                                                        echo '<tr>
                                                        <td>' . $codcrt . ' </td>
                                                        <td>' . $codcli . ' </td>
                                                        <td>' . $ccodaport . ' </td>
                                                        <td>' . $monto . '</td>
                                                        <td>' . $fecap . '</td>
                                                        <td>
                                                            <button type="button" class="btn btn-primary" title="Añadir beneficiarios" onclick="printdiv(`benaport`, `#cuadro`, `APRT_0`,`' . $ccodaport . '`)">
                                                                <i class="fa-solid fa-people-line"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-success" title="Imprimir certificado" onclick="imprimir_certificado_aprt(' . $idcrt . ',`crud_aportaciones`,`pdf_certificado_aprt`,`I`,`0`,' . $codusu . ')">
                                                                <i class="fa-solid fa-print"></i>
                                                            </button>' . $bt;
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--botones de nuevo certificado, cancelar y salir-->
                            <div class="row mt-2 justify-items-md-center">
                                <div class="col align-items-center" id="modal_footer">
                                    <button type="button" id="btnnew" class="btn btn-outline-success"
                                        onclick="printdiv('nuevoCertificado', '#cuadro', 'APRT_2', '0')">
                                        <i class="fa fa-file"></i> Nuevo certificado
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
                        <script>
                            //Datatable para parametrizacion
                            $(document).ready(function () {
                                convertir_tabla_a_datatable("table_id2");
                            });
                        </script>
                    </div>
                <?php
    }
        break;
    case 'nuevoCertificado': {
        $id = $_POST["xtra"];
        $codusu = $_SESSION['id'];

        $datoscli = mysqli_query($conexion, "SELECT * FROM `aprcta` WHERE `ccodaport`=$id");
        $bandera = "Cuenta de aportación no existe";
        while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
            $idcli = encode_utf8($da["ccodcli"]);
            $nit = encode_utf8($da["num_nit"]);
            $nlibreta = encode_utf8($da["nlibreta"]);
            $fecha_apertura = encode_utf8($da["fecha_apertura"]);
            $bandera = "";
        }
        if ($bandera == "") {
            $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente`='$idcli'");
            $nombre = "";
            $bandera = "No existe el cliente relacionado a la cuenta de aportación";
            while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                $nombre = encode_utf8($dat["short_name"]);
                $bandera = "";
            }
        }
        ?>

                    <div class="container">
                        <div class="text" style="text-align:center">ADICION DE CERTIFICADOS DE APORTACIONES</div>
                        <input type="text" id="condi" value="nuevoCertificado" hidden>
                        <input type="text" id="file" value="APRT_2" hidden>
                        <div class="card">
                            <div class="card-header">Adición de certificados</div>
                            <div class="card-body">
                                <div class="accordion" id="accordionExample">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button" type="button" aria-expanded="true"
                                                aria-controls="collapseOne">
                                                IDENTIFICACION DEL CLIENTE
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                                            data-bs-parent="#accordionExample">
                                            <div class="accordion-body">
                                                <div class="row mb-2">
                                                    <div class="col-md-5">
                                                        <div class="row">
                                                            <span class="input-group-addon col-8">Cuenta de Aportación</span>
                                                            <div class="input-group">
                                                                <input type="text" class="form-control " id="ccodaport" required
                                                                    placeholder="000-000-00-000000" value="<?php if ($bandera == "")
                                                                        echo $id; ?>">
                                                                <span class="input-group-text" id="basic-addon1">
                                                                    <?php
                                                                    if ($bandera == "") {
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
                                                                    ?>
                                                                </span>
                                                            </div>

                                                        </div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <br>
                                                        <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                                            title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaport')">
                                                            <i class="fa fa-check-to-slot"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                                            title="Buscar cuenta" data-bs-toggle="modal"
                                                            data-bs-target="#findaportcta">
                                                            <i class="fa fa-magnifying-glass"></i>
                                                        </button>
                                                    </div>
                                                </div>

                                                <!--Aho_0_ApertCuenAhor Búsqueda NIT-->
                                                <div class="row mb-3">
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">Cliente</span>
                                                            <input type="text" class="form-control " id="nomcli" placeholder=""
                                                                value="<?php if ($bandera == "")
                                                                    echo $nombre; ?>" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">Codigo de cliente</span>
                                                            <input type="text" class="form-control " id="codcli" placeholder=""
                                                                value="<?php if ($bandera == "")
                                                                    echo $idcli; ?>" readonly>
                                                        </div>
                                                    </div>

                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">NIT</span>
                                                            <input type="text" class="form-control " id="nit" placeholder="" value="<?php if ($bandera == "")
                                                                echo $nit; ?>" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <span class="input-group-addon col-8">Fecha apertura de cuenta</span>
                                                        <input type="date" class="form-control" id="fecaper" required="required"
                                                            value="<?php if ($bandera == "")
                                                                echo $fecha_apertura; ?>" readonly>
                                                    </div>
                                                </div>
                                                <div class="row mb-3">
                                                <?php if ($bandera != "" && $id != "0") {
                                                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $bandera . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                                                }
                                                ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingTwo">
                                            <button class="accordion-button" type="button" aria-expanded="false"
                                                aria-controls="collapseTwo">
                                                DATOS DE LA CUENTA
                                            </button>
                                        </h2>
                                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                                            data-bs-parent="#accordionExample">
                                            <div class="accordion-body">
                                                <div class="row mb-3">
                                                    <div class="col-sm-4">
                                                        <span class="input-group-addon col-8">Certificado</span>
                                                        <input type="text" aria-label="Certificado" id="certif_n"
                                                            class="form-control  col" placeholder="" required>
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <span class="input-group-addon col-8">Monto</span>
                                                        <input type="number" class="form-control" step="0.01" id="monapr_n"
                                                            placeholder="0.00" required="required">
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <span class="input-group-addon col-8">Comprobante de caja </span>
                                                        <input type="text" class="form-control" id="norecibo">
                                                    </div>

                                                </div>
                                                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true"
                                                    id="toastalert">
                                                    <div class="toast-header">
                                                        <strong class="me-auto">Advertencia</strong>
                                                        <small class="text-muted">Tomar en cuenta</small>
                                                        <button type="button" class="btn-close" data-bs-dismiss="toast"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <div class="toast-body bg-danger text-white" id="body_text">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingTwo">
                                            <button class="accordion-button" type="button" aria-expanded="false"
                                                aria-controls="collapseTwo">
                                                BENEFICIARIOS DE LA CUENTA
                                            </button>
                                        </h2>
                                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                                            data-bs-parent="#accordionExample">
                                            <div class="accordion-body">
                                                <div class="row">
                                                    <table id="table_id2" class="table table-hover table-border">
                                                        <thead class="text-light table-head-aprt">
                                                            <tr>
                                                                <th>DPI</th>
                                                                <th>Nombre Completo</th>
                                                                <th>Fec. Nac.</th>
                                                                <th>Parentesco</th>
                                                                <th>%</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="categoria_tb">
                                                            <?php
                                                            $filas = 0;
                                                            $total = 0;
                                                            if ($bandera == "") {
                                                                $queryben = mysqli_query($conexion, "SELECT * FROM `aprben` WHERE `codaport`='$id'");
                                                                $filas = mysqli_fetch_assoc($queryben);
                                                                if ($filas == null) {
                                                                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">No puede generar su certificado, tiene que agregar beneficiarios a la cuenta
                                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                                </div>';
                                                                } else {
                                                                    $queryben = mysqli_query($conexion, "SELECT * FROM `aprben` WHERE `codaport`='$id'");
                                                                    $filas = "1";
                                                                    while ($rowq = mysqli_fetch_array($queryben, MYSQLI_ASSOC)) {
                                                                        $idaprben = encode_utf8($rowq["id_ben"]);
                                                                        $bennom = encode_utf8($rowq["nombre"]);
                                                                        $bendpi = encode_utf8($rowq["dpi"]);
                                                                        $bendire = encode_utf8($rowq["direccion"]);
                                                                        $benparent = encode_utf8($rowq["codparent"]);
                                                                        $parentdes = parenteco($benparent);
                                                                        $benfec = encode_utf8($rowq["fecnac"]);
                                                                        $benporcent = encode_utf8($rowq["porcentaje"]);
                                                                        $total = $total + $benporcent;
                                                                        $bentel = encode_utf8($rowq["telefono"]);
                                                                        echo '<tr>
                                                                            <td>' . $bendpi . ' </td>
                                                                            <td>' . $bennom . ' </td>
                                                                            <td>' . $benfec . ' </td>
                                                                            <td>' . $parentdes . '</td>
                                                                            <td>' . $benporcent . '</td>
                                                                            </tr>';
                                                                    }
                                                                }
                                                            }
                                                            ?>
                                                        </tbody>
                                                    </table>

                                                </div>
                                                <div class="row">
                                                    <!--TOTAL-->
                                                    <div class="col-md-3">
                                                        <label for="">Total: <?php if ($bandera == "")
                                                            echo $total; ?> %</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row justify-items-md-center mt-3">
                                        <div class="col align-items-center" id="modal_footer">
                                            <button type="button" class="btn btn-outline-success"
                                                onclick="obtiene([`certif_n`,`ccodaport`,`codcli`,`nit`,`monapr_n`,`fecaper`,`norecibo`],[],[],`create_certificado_aprt`,`0`,['<?php echo $id; ?>','<?php echo $bandera; ?>','<?php echo $codusu; ?>','<?php echo $filas; ?>','<?php echo $total; ?>'])">
                                                <i class="fa fa-floppy-disk"></i> Guardar
                                            </button>
                                            <button type="button" class="btn btn-outline-danger"
                                                onclick="printdiv('Certificados_aprt', '#cuadro', 'APRT_2', '0')">
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

                <?php
    }
        break;
    case 'actualizar_Certificado': {
        $id = $_POST["xtra"];
        $codusu = $_SESSION['id'];

        $datoscli = mysqli_query($conexion, "SELECT crt.ccodcrt, cta.ccodaport, cl.short_name, cl.idcod_cliente, cl.no_tributaria, crt.fec_crt, crt.montoapr, cta.fecha_apertura,cta.norecibo
        FROM
          aprcta AS cta 
          INNER JOIN aprcrt AS crt 
            ON cta.ccodaport = crt.ccodaport 
          INNER JOIN tb_cliente AS cl 
            ON crt.ccodcli = cl.idcod_cliente
            WHERE crt.id_crt='$id'");

        while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
            $codcertif = encode_utf8($da["ccodcrt"]);
            $norecibo = encode_utf8($da["norecibo"]);
            $ccodaport = encode_utf8($da["ccodaport"]);
            $nombre = encode_utf8($da["short_name"]);
            $idcli = encode_utf8($da["idcod_cliente"]);
            $nit = encode_utf8($da["no_tributaria"]);
            $fecha_apertura = encode_utf8($da["fecha_apertura"]);
            $fecha_creacion = encode_utf8($da["fec_crt"]);
            $monto = encode_utf8($da["montoapr"]);
            $bandera = "";
        }
        $hoy = date("Y-m-d");

        ?>
                    <!--Aho_0_ApertCuenAhor Inicio de Ahorro Sección 0 Apertura de Cuenta-->
                    <div class="container">
                        <div class="text" style="text-align:center">ADICION DE CERTIFICADOS DE PLAZO FIJO</div>
                        <input type="text" id="condi" value="actualizar_Certificado" hidden>
                        <input type="text" id="file" value="APRT_2" hidden>
                        <div class="card">
                            <div class="card-header">Adición de certificados</div>
                            <div class="card-body">
                                <div class="accordion" id="accordionExample">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button" type="button" aria-expanded="true"
                                                aria-controls="collapseOne">
                                                IDENTIFICACION DEL CLIENTE
                                            </button>
                                        </h2>
                                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                                            data-bs-parent="#accordionExample">
                                            <div class="accordion-body">
                                                <div class="row mb-3">
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">Codigo certificado</span>
                                                            <input type="text" aria-label="Certificado" id="certif"
                                                                class="form-control  col" value="<?php echo $codcertif; ?>"
                                                                readonly>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">Cuenta de aportación</span>
                                                            <input type="text" class="form-control " id="ccodaport" placeholder=""
                                                                value="<?php echo $ccodaport; ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!--Aho_0_ApertCuenAhor Búsqueda NIT-->
                                                <div class="row mb-3">
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">Cliente</span>
                                                            <input type="text" class="form-control " id="nomcli" placeholder=""
                                                                value="<?php echo $nombre; ?>" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">Codigo de cliente</span>
                                                            <input type="text" class="form-control " id="codcli" placeholder=""
                                                                value="<?php echo $idcli; ?>" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-sm-6">
                                                        <div>
                                                            <span class="input-group-addon col-8">NIT</span>
                                                            <input type="text" class="form-control " id="nit" placeholder=""
                                                                value="<?php echo $nit; ?>" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <span class="input-group-addon col-8">Fecha apertura de cuenta</span>
                                                        <input type="date" class="form-control" id="fecaper" required="required"
                                                            value="<?php echo $fecha_apertura; ?>" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingTwo">
                                            <button class="accordion-button" type="button" aria-expanded="false"
                                                aria-controls="collapseTwo">
                                                DATOS DE LA CUENTA
                                            </button>
                                        </h2>
                                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                                            data-bs-parent="#accordionExample">
                                            <div class="accordion-body">
                                                <div class="row mb-3">
                                                    <div class="col-sm-4">
                                                        <span class="input-group-addon col-8">Fecha creacion de certificado</span>
                                                        <input type="date" class="form-control" id="feccrt" required="required"
                                                            value="<?php echo $fecha_creacion; ?>" readonly>
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <span class="input-group-addon col-8">Monto</span>
                                                        <input type="number" step="0.01" class="form-control" id="monapr"
                                                            placeholder="0.00" required="required" value="<?php echo $monto; ?>">
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <span class="input-group-addon col-8">Comprobante de caja</span>
                                                        <input type="text" class="form-control" id="monapr"
                                                            value="<?php echo $norecibo; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingTwo">
                                            <button class="accordion-button" type="button" aria-expanded="false"
                                                aria-controls="collapseTwo">
                                                BENEFICIARIOS DE LA CUENTA
                                            </button>
                                        </h2>
                                        <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                                            data-bs-parent="#accordionExample">
                                            <div class="accordion-body">
                                                <div class="row">
                                                    <table id="table_id2" class="table table-hover table-border">
                                                        <thead class="text-light table-head-aprt">
                                                            <tr>
                                                                <th>DPI</th>
                                                                <th>Nombre Completo</th>
                                                                <th>Fec. Nac.</th>
                                                                <th>Parentesco</th>
                                                                <th>%</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="categoria_tb">
                                                            <?php
                                                            $total = 0;

                                                            $queryben = mysqli_query($conexion, "SELECT * FROM `aprben` WHERE `codaport`='$ccodaport'");
                                                            while ($rowq = mysqli_fetch_array($queryben, MYSQLI_ASSOC)) {
                                                                $idaprben = encode_utf8($rowq["id_ben"]);
                                                                $bennom = encode_utf8($rowq["nombre"]);
                                                                $bendpi = encode_utf8($rowq["dpi"]);
                                                                $bendire = encode_utf8($rowq["direccion"]);
                                                                $benparent = encode_utf8($rowq["codparent"]);
                                                                $parentdes = parenteco($benparent);
                                                                $benfec = encode_utf8($rowq["fecnac"]);
                                                                $benporcent = encode_utf8($rowq["porcentaje"]);
                                                                $total = $total + $benporcent;
                                                                $bentel = encode_utf8($rowq["telefono"]);
                                                                echo '<tr>
                                                    <td>' . $bendpi . ' </td>
                                                    <td>' . $bennom . ' </td>
                                                    <td>' . $benfec . ' </td>
                                                    <td>' . $parentdes . '</td>
                                                    <td>' . $benporcent . '</td>
                                                    </tr>';
                                                            }


                                                            ?>
                                                        </tbody>
                                                    </table>

                                                </div>
                                                <div class="row">
                                                    <!--TOTAL-->
                                                    <div class="col-md-3">
                                                        <label for="">Total: <?php echo $total; ?> %</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row justify-items-md-center mt-3">
                                        <div class="col align-items-center" id="modal_footer">
                                            <button type="button" class="btn btn-outline-success"
                                                onclick="obtiene([`monapr`],[],[],`update_certificado_aprt`,`0`,['<?php echo $id; ?>','<?php echo $codusu; ?>','<?php echo $total; ?>'])">
                                                <i class="fa fa-floppy-disk"></i> Guardar
                                            </button>
                                            <button type="button" class="btn btn-outline-danger"
                                                onclick="printdiv('Certificados_aprt', '#cuadro', 'APRT_2', '0')">
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
                <?php
    }
        break;
}
function executequery($query, $params, $typparams, $conexion)
{
    $stmt = $conexion->prepare($query);
    $aux = mysqli_error($conexion);
    if ($aux) {
        return ['ERROR: ' . $aux, false];
    }
    $types = '';
    $bindParams = [];
    $bindParams[] = &$types;
    $i = 0;
    foreach ($params as &$param) {
        // $types .= 's';
        $types .= $typparams[$i];
        $bindParams[] = &$param;
        $i++;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    if (!$stmt->execute()) {
        return ["Error en la ejecución de la consulta: " . $stmt->error, false];
    }
    $data = [];
    $resultado = $stmt->get_result();
    $i = 0;
    while ($fila = $resultado->fetch_assoc()) {
        $data[$i] = $fila;
        $i++;
    }
    $stmt->close();
    return [$data, true];
}
?>

        <!-- moda para cambio de certificado -->
        <div class="modal fade" id="cambio_certif" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog  modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5">Reimpresion de certificado</h1>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-2">
                            <div class="col-12">
                                <!-- titulo -->
                                <span class="input-group-addon col-8">Cuenta de Aportación</span>
                                <div class="input-group">
                                    <input type="text" class="form-control " id="id_modal_crt" readonly hidden>
                                    <input type="text" class="form-control " id="id_codusu_crt" readonly hidden>
                                    <input type="text" class="form-control " id="ccodaport_modal_crt" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <span class="input-group-addon col-8">Nuevo código de certificado</span>
                                <input type="text" aria-label="Certificado" id="certif_modal" class="form-control  col"
                                    placeholder="" required>
                            </div>
                            <div class="col-sm-6" hidden>
                                <span class="input-group-addon col-8">Monto</span>
                                <input type="float" class="form-control" id="monapr_n" placeholder="0.00"
                                    required="required">
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" id="cancelar_ben"
                            onclick="create_cambio_certif()">Reimpresión certificado</button>
                        <button type="button" class="btn btn-secondary" id="cancelar_ben"
                            onclick="cancelar_cambio_certif()">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>