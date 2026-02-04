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
require_once __DIR__ . '/../../includes/Config/model/ahorros/Ahomtip.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

use App\Generic\DocumentManager;
use App\Generic\FileProcessor;
use Micro\Generic\PermissionManager;
use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

$condi = $_POST["condi"];

switch ($condi) {
    case 'DepoAhorr':

        $account = $_POST["xtra"];

        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,url_img urlfoto,numfront,numdors, tip.nombre tipoNombre,tip.tipcuen,
                    IFNULL((SELECT MAX(`numlinea`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimonum,
                    IFNULL((SELECT MAX(`correlativo`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimocorrel,
                        calcular_saldo_aho_tipcuenta(cta.ccodaho,?) saldo,cli.no_identifica
                        FROM `ahomcta` cta 
                        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                        INNER JOIN ahomtip tip on tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                        WHERE `ccodaho`=? AND cli.estado=1";

        $titlemodule = $_ENV['AHO_NAME_MODULE'] ?? "Ahorros";

        $showmensaje = false;
        try {
            if ($account == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta de " . $titlemodule);
            }
            $database->openConnection();

            $data = $database->getAllResults($query, [$hoy, $account]);
            if (empty($data)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta, verifique que el código sea correcto y que el cliente esté activo");
            }
            $dataAccount = $data[0];

            if ($dataAccount['ultimonum'] >= ($dataAccount['numfront'] + $dataAccount['numdors'])) {
                $showmensaje = true;
                throw new Exception("El número de líneas en libreta ha llegado a su límite, se recomienda abrir otra libreta");
            }

            if ($dataAccount['estado'] != "A") {
                $showmensaje = true;
                throw new Exception("Cuenta Inactiva");
            }

            $bancos = $database->selectColumns("tb_bancos", ["id", "nombre"], "estado=1");

            // === NUEVO: Leer si ya está marcado como recurrente (atributo 19) ===
            $recurrente = $database->getSingleResult("SELECT 1 FROM tb_cliente_atributo
                   WHERE id_cliente  = ? AND id_atributo = 19 AND TRIM(valor) = '1' LIMIT 1 ", [$dataAccount['ccodcli']]);


            //nueva consulta para obtener el estado de la cuenta
            $hasRTE = $database->getSingleResult(
                "SELECT 1 FROM tb_RTE_use WHERE ccdocta = ? AND DATE(Cretadate) = CURDATE() LIMIT 1 ",
                [$account]
            );

            $userPermissions = new PermissionManager($idusuario);

            if ($userPermissions->isLevelOne(PermissionManager::USAR_OTROS_DOCS_AHORROS)) {
                $documentosTransacciones = $database->selectColumns(
                    "tb_documentos_transacciones",
                    ["id", "nombre", "tipo_dato"],
                    "estado=1 AND id_modulo=1 AND tipo=2"
                );
            }

            try {
                $docManager = new DocumentManager();
                $previewCorrel = $docManager->peekNextCorrelative([
                    'id_modulo' => 2, // Depósitos de ahorros
                    'tipo' => 'INGRESO',
                    'usuario_id' => $idusuario,
                    'agencia_id' => $idagencia,
                ]);
            } catch (Exception $e) {
                Log::error('Error al verificar correlativo: ' . $e->getMessage());
            }

            $otrosTitulares = $database->getAllResults(
                "SELECT cli.short_name, cli.no_identifica FROM cli_mancomunadas man
                INNER JOIN tb_cliente cli ON man.ccodcli = cli.idcod_cliente
                WHERE man.ccodaho = ? AND tipo='ahorro' AND man.estado=1 AND cli.estado=1",
                [$account]
            );

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

        include_once "../../src/cris_modales/mdls_aho_new.php";

        $titlemodule = $_ENV['AHO_NAME_MODULE'] ?? "Ahorros";
        ?>
        <!-- Inicio del HTML para el Depósito de Ahorro -->
        <div class="card">
            <input type="hidden" id="file" value="aho_02">
            <input type="hidden" id="condi" value="DepoAhorr">
            <input type="hidden" id="initial_recurrente" value="<?= (!empty($recurrente)) ? $recurrente['1'] : 0 ?>">
            <input type="hidden" id="initial_hasRTE" value="<?= (!empty($hasRTE)) ? $hasRTE['1'] : 0 ?>">

            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Depósito de <?= $titlemodule ?>
                </h4>
            </div>
            <style>
                .golden {
                    color: #2e4b08ff;
                }

                .hidden {
                    display: none;
                }

                .input-container {
                    position: relative;
                }
            </style>
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
                                <div class="col-sm-2 col-md-2 col-lg-2">
                                    <?php if ($status && $dataAccount['tipcuen'] === "pr"): ?>
                                        <br>
                                        <button class="btn btn-outline-primary" onclick="mostrar_planpago('<?= $account ?>');"
                                            data-bs-toggle="modal" data-bs-target="#modal_plan_pago">
                                            <i class="fa-solid fa-rectangle-list me-2"></i>Plan de pagos
                                        </button>
                                    <?php endif; ?>
                                    <br>
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
                                value="<?= $previewCorrel['valor'] ?? '' ?>">
                        </div>
                        <div class="col-sm-10 col-md-5 col-lg-4">
                            <br>
                            <span class="badge text-bg-primary" style="font-size: 1.2em; font-weight: bold;">Saldo:
                                <?= number_format($dataAccount['saldo'] ?? 0, 2, '.', ',') ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-10 col-md-5 col-lg-4">
                            <span class="input-group-addon col-8">Cantidad</span>
                            <input type="number" step="any" class="form-control " id="monto" required placeholder="0.00" min="1"
                                oninput="updateValues()">
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
                                <span class="input-group-addon col-8 golden">Cantidad</span>
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
                    <div class="row mb-3">
                        <div class="col-sm-10 col-md-5 col-lg-4">
                            <span class="input-group-addon col-8">Fecha</span>
                            <input type="date" class="form-control " id="dfecope" value="<?php echo date("Y-m-d"); ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-12">
                            <div class="input-container">
                                <span class="input-group-addon col-2">Concepto</span>
                                <textarea class="form-control" id="concepto" rows="4" placeholder="Ingrese el concepto aquí"
                                    require></textarea>
                            </div>
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
                                onchange="tipdoc(this.value);openWindow(this)">
                                <option value="E" data-typedata="E" selected>EFECTIVO</option>
                                <option value="D" data-typedata="D">CON BOLETA DE BANCO</option>
                                <?php if (isset($documentosTransacciones) && !empty($documentosTransacciones)): ?>
                                    <optgroup label="Otros tipos de documentos">
                                        <?php foreach ($documentosTransacciones as $doc): ?>
                                            <option value="<?= $doc['id']; ?>" data-typedata="<?= $doc['tipo_dato']; ?>">
                                                <?= htmlspecialchars($doc['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="tiop" name="tiop" value="E">
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
                <div class="container contenedort" id="region_tipo_cheque" style="display: none; max-width: 100% !important;">
                    <h6>DATOS DEL CHEQUE RECIBIDO</h6>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="bancoid_cheque">
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
                                <label for="bancoid_cheque">Banco</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-2 mt-2">
                                <input type="text" class="form-control" id="numero_cheque" placeholder="No de Cheque">
                                <label for="numero_cheque">No.Cheque</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="input-group-addon">Fecha Cheque</span>
                            <input type="date" class="form-control" id="fecha_cheque" value="<?php echo date("Y-m-d"); ?>">
                        </div>
                    </div>
                </div>
                <div class="row mb-3 justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <?php if ($status): ?>
                            <!-- <button type="button" id="btnSave" class="btn btn-outline-success" onclick="confirmSave('D')">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar
                            </button> -->
                            <button type="button" id="btnSave" class="btn btn-outline-success" onclick="guardarNuevo()">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar operacion
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>

                <!-- Modal ALERTA RTE -->
                <div class="modal fade" id="modalAlertaRTE" tabindex="-1" aria-labelledby="modalAlertaRTELabel"
                    aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content shadow-lg rounded-4 border-0"
                            style="background: linear-gradient(135deg, #f8fafc 0%, #f6e7ff 100%);">
                            <div class="modal-header bg-gradient border-0 rounded-top-4"
                                style="background: linear-gradient(90deg, #ffe082 0%, #fffde7 100%);">
                                <h5 class="modal-title fw-bold text-warning-emphasis d-flex align-items-center">
                                    <span class="me-2" style="font-size: 1.7em;">
                                        <i class="fa fa-exclamation-triangle"></i>
                                    </span>
                                    Alerta RTE - Datos del Depositante
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body rounded-bottom-4 px-4 py-3"
                                style="background: linear-gradient(135deg, #f8fafc 0%, #e3f2fd 100%);">
                                <div class="alert alert-warning d-flex align-items-center mb-4 shadow-sm rounded-3" role="alert"
                                    style="font-size: 1.1em;">
                                    <i class="fa fa-info-circle fa-lg me-2"></i>
                                    <div>
                                        <strong>¡Atención!</strong> Debe ser aprobada la transacción. Después de guardar el RTE,
                                        intente guardar nuevamente.
                                    </div>
                                </div>
                                <!-- 1. Preguntar si es el titular -->
                                <div class="row mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-semibold mb-2">¿El depositante es el titular de la
                                            cuenta?</label>
                                        <div class="form-check form-check-inline ms-2">
                                            <input class="form-check-input" type="radio" name="rte_esTitular" value="1" checked
                                                onchange="showHideElement(['sectionDatosPersonales','rte_dpi_nothing_section2'], 'hide');showHideElement('rte_dpi_nothing_section', 'show')">
                                            <label class="form-check-label" for="rte_esTitular_si">Sí</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="rte_esTitular" value="0"
                                                onchange="showHideElement(['sectionDatosPersonales','rte_dpi_nothing_section2'], 'show');showHideElement('rte_dpi_nothing_section', 'hide')">
                                            <label class="form-check-label" for="rte_esTitular_no">No</label>
                                        </div>
                                    </div>
                                </div>
                                <!-- 2. Datos básicos del depositante -->
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="rte_ccdocta" class="form-label">Código de Cuenta (ccodaho)</label>
                                        <input type="text" class="form-control bg-body-tertiary" id="rte_ccdocta" readonly
                                            value="<?= $account ?>">
                                    </div>
                                    <div class="col-sm-6" id="rte_dpi_nothing_section">
                                        <label for="rte_dpi_nothing" class="form-label">DPI / Identificación</label>
                                        <input type="text" class="form-control" id="rte_dpi_nothing"
                                            value="<?= $dataAccount['no_identifica'] ?? '' ?>" readonly>
                                    </div>
                                    <div class="col-sm-6" id="rte_dpi_nothing_section2" style="display: none;">
                                        <label for="rte_dpi" class="form-label">Identificación / DPI</label>
                                        <input type="text" class="form-control" id="rte_dpi" value="">
                                    </div>
                                </div>
                                <div id="sectionDatosPersonales" class="mt-3" style="display:none;">
                                    <div class="row g-3" id="rte_datos_personales">
                                        <div class="col-sm-4">
                                            <label for="rte_nombre1" class="form-label">Primer Nombre</label>
                                            <input type="text" class="form-control" id="rte_nombre1">
                                        </div>
                                        <div class="col-sm-4">
                                            <label for="rte_nombre2" class="form-label">Segundo Nombre</label>
                                            <input type="text" class="form-control" id="rte_nombre2">
                                        </div>
                                        <div class="col-sm-4">
                                            <label for="rte_nombre3" class="form-label">Tercer Nombre</label>
                                            <input type="text" class="form-control" id="rte_nombre3">
                                        </div>
                                    </div>
                                    <div class="row g-3 mt-2" id="rte_apellidos">
                                        <div class="col-sm-4">
                                            <label for="rte_apellido1" class="form-label">Primer Apellido</label>
                                            <input type="text" class="form-control" id="rte_apellido1">
                                        </div>
                                        <div class="col-sm-4">
                                            <label for="rte_apellido2" class="form-label">Segundo Apellido</label>
                                            <input type="text" class="form-control" id="rte_apellido2">
                                        </div>
                                        <div class="col-sm-4">
                                            <label for="rte_apellido3" class="form-label">Apellido de Casada</label>
                                            <input type="text" class="form-control" id="rte_apellido3">
                                        </div>
                                    </div>
                                </div>
                                <!-- 3. Origen/Destino de fondos -->
                                <div class="row g-3 mt-3">
                                    <div class="col-sm-6">
                                        <label for="rte_ori_fondos" class="form-label">Origen de Fondos</label>
                                        <input type="text" class="form-control" id="rte_ori_fondos">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="rte_desti_fondos" class="form-label">Destino de Fondos</label>
                                        <input type="text" class="form-control" id="rte_desti_fondos">
                                    </div>
                                </div>
                                <!-- 4. Nacionalidad y Monto -->
                                <div class="row g-3 mt-3">
                                    <div class="col-sm-6">
                                        <label for="rte_nacionalidad" class="form-label">Nacionalidad</label>
                                        <input type="text" class="form-control" id="rte_nacionalidad">
                                    </div>
                                    <input type="text" hidden value="" id="idAlerta">
                                </div>
                            </div>
                            <div class="modal-footer bg-body-tertiary rounded-bottom-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill"
                                    data-bs-dismiss="modal">
                                    <i class="fa fa-times me-1"></i> Cerrar
                                </button>
                                <button type="button" class="btn btn-success px-4 py-2 rounded-pill shadow-sm"
                                    style="font-weight: 500; letter-spacing: 0.5px;"
                                    onclick="obtiene(['monto','rte_ccdocta','rte_dpi','rte_nombre1','rte_nombre2','rte_nombre3','rte_apellido1','rte_apellido2','rte_apellido3','rte_ori_fondos','rte_desti_fondos','rte_nacionalidad','cnumdoc','idAlerta'], [], ['rte_esTitular'], 'create_rte_user', '0', [], depositoNormal, true, '¿Desea continuar con el proceso?')">
                                    <i class="fa fa-save me-1"></i> Guardar RTE
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!--fin modal rte-->

            </div>
        </div>

        <?php
        // Incluir modal adicional para cuentas programadas si aplica
        if ($status && $dataAccount['tipcuen'] === "pr") {
            include_once "../../src/cris_modales/mdls_planPagoAhorroProgramado.php";
        }
        ?>

        <!-- Script para confirmación de guardado -->
        <script>
            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function guardarNuevo() {
                const ccodaho = $('#ccodaho').val();
                const monto = $('#monto').val();
                const cnumdoc = $('#cnumdoc').val();

                if (isNaN(monto) || monto <= 0) {
                    return Swal.fire('Error', 'Ingrese un monto válido mayor a cero.', 'error');
                }
                if (cnumdoc.trim() === "") {
                    return Swal.fire('Error', 'Ingrese un número de documento válido.', 'error');
                }

                Swal.fire({
                    title: `¿Deseas depositar Q ${parseFloat(monto).toLocaleString('es-GT', { minimumFractionDigits: 2 })}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, Depositar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        verifyIve(ccodaho, monto, cnumdoc);
                    }
                });
            }

            function verifyIve(ccodaho, monto, cnumdoc) {
                loaderefect(1);
                $.ajax({
                    url: '../src/cruds/endpoints.php',
                    data: {
                        condi: 'verifyIve',
                        ccodaho: ccodaho,
                        monto: monto,
                        cnumdoc: cnumdoc
                    },
                    method: 'POST',
                    success: function (response) {
                        loaderefect(0);
                        // console.log(response);
                        var opResult = JSON.parse(response);
                        if (opResult.status == 1) {
                            // if (opResult.return == 1) {
                            //     $('#modalAlertaRTE').modal('show');
                            // } else {
                            //     console.log("no mostrar formulario rte, pero si guardar la transaccion en el rte si return es 2");
                            //     // callback(true);
                            // }
                            // loaderefect(0);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: opResult.message
                            })
                        }
                        if (opResult.return == 1) {
                            $('#modalAlertaRTE').modal('show');
                            $('#idAlerta').val(opResult.idAlerta);
                        } else {
                            // console.log("no mostrar formulario rte, pero si guardar la transaccion en el rte si return es 2");
                            // depositoNormal(opResult.return == 2 ? 1 : 0);

                            if (opResult.status == 1) {
                                // console.log("proceder con el deposito")
                                obtiene(
                                    ['ccodaho', 'dfecope', 'cnumdoc', 'monto', 'cnumdocboleta', 'concepto', 'fechaBoleta', 'numero_cheque', 'fecha_cheque'],
                                    ['salida', 'tipdoc', 'bancoid', 'cuentaid', 'bancoid_cheque'],
                                    [],
                                    'create_depositos_ahommov', '0',
                                    ['<?= $account ?>', opResult.return == 2 ? 1 : 0]
                                );
                            }

                            // callback(true);
                        }
                    },
                    complete: function (data) {
                        // loaderefect(0);
                    },
                    error: function (err) {
                        loaderefect(0);
                        // console.error('Error verifying fingerprint', err);
                    }
                });
            }



            function depositoNormal(guardarRte = 0) {
                $('#modalAlertaRTE').modal('hide');
                return;
                obtiene(
                    ['ccodaho', 'dfecope', 'cnumdoc', 'monto', 'cnumdocboleta', 'concepto', 'fechaBoleta', 'numero_cheque', 'fecha_cheque'],
                    ['salida', 'tipdoc', 'bancoid', 'cuentaid', 'bancoid_cheque'],
                    [],
                    'create_depositos_ahommov', '0',
                    ['<?= $account ?>', guardarRte]
                );
            }

            function openWindow(element) {
                // Obtener el option seleccionado dentro del select
                const selectedOption = element.options[element.selectedIndex];
                const tipoDato = selectedOption.getAttribute('data-typedata');

                if (tipoDato === '2') {
                    $("#region_tipo_cheque").show();
                } else {
                    $("#region_tipo_cheque").hide();
                }
            }
        </script>
        <?php
        break;
    case 'RetiAhorr':
        $account = $_POST["xtra"];
        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,url_img urlfoto,numfront,numdors, tip.nombre tipoNombre,
                    IFNULL((SELECT MAX(`numlinea`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimonum,
                    IFNULL((SELECT MAX(`correlativo`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimocorrel,
                        calcular_saldo_aho_tipcuenta(cta.ccodaho,?) saldo
                        FROM `ahomcta` cta 
                        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                        INNER JOIN ahomtip tip on tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                        WHERE `ccodaho`=? AND cli.estado=1";

        $query2 = "SELECT cta.ret AS std  FROM tb_garantias_creditos tgc 
                    INNER JOIN cli_garantia cg ON cg.idGarantia = tgc.id_garantia 
                    INNER JOIN ahomcta cta ON cta.ccodaho = cg.descripcionGarantia
                    INNER JOIN cremcre_meta cm ON cm.CCODCTA = tgc.id_cremcre_meta
                    WHERE cta.ccodaho = ?";

        $src = '../../includes/img/fotoClienteDefault.png';
        $flag_correlativo = 1; //BANDERA PARA ACTIVAR CORRELATIVO AUTOMATICO
        $showmensaje = false;
        $previewCorrel = null;
        $configMessage = '';
        $inputCorrel = '';
        try {
            if ($account == '0') {
                $showmensaje = true;
                $titlemodule = $_ENV['AHO_NAME_MODULE'] ?? "Ahorros";
                throw new Exception("Seleccione una cuenta de " . $titlemodule);
            }
            $database->openConnection();

            $data = $database->getAllResults($query, [$hoy, $account]);
            if (empty($data)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta, verifique que el código sea correcto y que el cliente esté activo");
            }
            $dataAccount = $data[0];

            if ($dataAccount['ultimonum'] >= ($dataAccount['numfront'] + $dataAccount['numdors'])) {
                $showmensaje = true;
                throw new Exception("El número de líneas en libreta ha llegado a su límite, se recomienda abrir otra libreta");
            }

            if ($dataAccount['estado'] != "A") {
                $showmensaje = true;
                throw new Exception("Cuenta Inactiva");
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

            $asGarantia = $database->getAllResults($query2, [$account]);

            // --- Verificar configuración de correlativo para el usuario ---
            $previewCorrel = null;
            $configMessage = '';
            try {
                $docManager = new DocumentManager();
                $previewCorrel = $docManager->peekNextCorrelative([
                    'id_modulo' => 2, // Retiros de ahorros
                    'tipo' => 'EGRESO',
                    'usuario_id' => $idusuario,
                    'agencia_id' => $idagencia,
                ]);
            } catch (Exception $e) {
                $configMessage = 'Error al verificar correlativo: ' . $e->getMessage();
            }

            $inputCorrel = $previewCorrel['valor'] ?? '';

            $bancos = $database->selectColumns("tb_bancos", ["id", "nombre"], "estado=1");

            $compensacion = $database->getAllResults("SELECT IFNULL(SUM(aho.monto),0) AS sumaMonto FROM ctb_ban_mov bam
                    INNER JOIN ctb_diario dia ON dia.id = bam.id_ctb_diario
                    INNER JOIN ahommov aho ON dia.karely LIKE 'AHO_%' AND aho.id_mov = SUBSTRING(dia.karely, 5)
                    WHERE dia.estado=1 AND aho.ccodaho=? AND aho.cestado=1 AND bam.estado=1", [$account]);

            $otrosTitulares = $database->getAllResults(
                "SELECT cli.short_name, cli.no_identifica FROM cli_mancomunadas man
                INNER JOIN tb_cliente cli ON man.ccodcli = cli.idcod_cliente
                WHERE man.ccodaho = ? AND tipo='ahorro' AND man.estado=1 AND cli.estado=1",
                [$account]
            );

            $userPermissions = new PermissionManager($idusuario);

            if ($userPermissions->isLevelOne(PermissionManager::USAR_OTROS_DOCS_AHORROS)) {
                $documentosTransacciones = $database->selectColumns(
                    "tb_documentos_transacciones",
                    ["id", "nombre", "tipo_dato"],
                    "estado=1 AND id_modulo=1 AND tipo=1"
                );
            }

            $cuentasBancos = $database->getAllResults(
                "SELECT ctb.id, ctb.numcuenta, ban.nombre FROM tb_bancos ban 
                    INNER JOIN ctb_bancos ctb ON ctb.id_banco=ban.id
                    WHERE ban.estado=1 AND ctb.estado=1"
            );

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

        $saldoEnCompensacion = $compensacion[0]['sumaMonto'] ?? 0;

        include_once "../../src/cris_modales/mdls_aho_new.php";
        $sessionSerial = generarCodigoAleatorio();
        ?>
        <input type="text" id="idControl" hidden value="<?= $asGarantia[0]['std'] ?? '1' ?>">
        <div class="card" id='carPrincipal'>
            <input type="text" id="file" value="aho_02" style="display: none;">
            <input type="text" id="condi" value="RetiAhorr" style="display: none;">
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
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Retiro de <?= $titlemodule ?>
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
                    <div class="row">
                        <div class="col-lg-2 col-sm-6 col-md-4 mt-2">
                            <img width="130" height="150" id="vistaPrevia" src="<?= $src ?? '' ?>">
                        </div>
                        <div class="col-lg-8 col-sm-6 col-md-8">
                            <div class="row">
                                <div class="col-sm-8 col-md-8 col-lg-8">
                                    <span class="input-group-addon col-8">Cuenta de Ahorro</span>
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
                                value="<?= ($flag_correlativo) ? $inputCorrel : '' ?>">
                        </div>
                        <div class="col-sm-10 col-md-5 col-lg-4">
                            <br>
                            <span class="badge text-bg-primary" style="font-size: 1.2em; font-weight: bold;">Saldo:
                                <?= moneda(($dataAccount['saldo'] ?? 0) - $saldoEnCompensacion) ?></span>
                        </div>
                        <?php if ($saldoEnCompensacion > 0): ?>
                            <div class="col-sm-10 col-md-5 col-lg-4">
                                <br>
                                <span class="badge text-bg-warning" style="font-size: 1.2em; font-weight: bold;">
                                    En Compensación:
                                    <?= moneda($saldoEnCompensacion) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-10 col-md-5 col-lg-4">
                            <span class="input-group-addon col-8">Cantidad a Retirar</span>
                            <input type="number" step="any" class="form-control " id="monto" required placeholder="0.00" min="0.01"
                                oninput="updateValues()">
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
                                <span class="input-group-addon col-8 golden">Cantidad</span>
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
                    <div class="row mb-3">
                        <div class="col-sm-10 col-md-5 col-lg-4">
                            <span class="input-group-addon col-8">Fecha</span>
                            <input type="date" class="form-control " id="dfecope" value="<?php echo date("Y-m-d"); ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-12">
                            <div class="input-container">
                                <span class="input-group-addon col-2">Concepto</span>
                                <textarea class="form-control" id="concepto" rows="4" placeholder="Ingrese el concepto aquí"
                                    require></textarea>
                            </div>
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
                                onchange="tipdoc(this.value);openWindow(this)">
                                <option value="E" data-typedata="E" selected>EFECTIVO</option>
                                <option value="C" data-typedata="C">CON CHEQUE</option>
                                <?php if (isset($documentosTransacciones) && !empty($documentosTransacciones)): ?>
                                    <optgroup label="Otros tipos de documentos">
                                        <?php foreach ($documentosTransacciones as $doc): ?>
                                            <option value="<?= $doc['id']; ?>" data-typedata="<?= $doc['tipo_dato']; ?>">
                                                <?= htmlspecialchars($doc['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <input type="hidden" id="tiop" name="tiop" value="R">
                            </select>
                        </div>
                    </div>
                </div>
                <div class="container contenedort" id="region_cheque" style="display: none; max-width: 100% !important;">
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
                <div class="container contenedort" id="region_tipo_transferencia"
                    style="display: none; max-width: 100% !important;">
                    <h6>DATOS DE LA TRANSFERENCIA</h6>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="transfer_cuentaid">
                                    <option value="0" disabled selected>Seleccione una cuenta de bancos</option>
                                    <?php
                                    if (isset($cuentasBancos) && is_array($cuentasBancos)) {
                                        foreach ($cuentasBancos as $cuentaBanco) {
                                            echo '<option value="' . $cuentaBanco['id'] . '">' . $cuentaBanco['nombre'] . ' - ' . $cuentaBanco['numcuenta'] . '</option>';
                                        }
                                    } else {
                                        echo '<option value="0">No hay cuentas de bancos disponibles</option>';
                                    }
                                    ?>
                                </select>
                                <label for="transfer_cuentaid">Cuenta de Banco</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-floating mb-2 mt-2">
                                <input type="text" class="form-control" id="transfer_numero_referencia"
                                    placeholder="No de Referencia">
                                <label for="transfer_numero_referencia">No. Referencia</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="input-group-addon">Fecha </span>
                            <input type="date" class="form-control" id="transfer_fecha" value="<?= date("Y-m-d"); ?>">
                        </div>
                    </div>
                </div>
                <input type="text" id="srnPc" hidden value="">
                <input type="text" id="sessionSerial" hidden value="<?= $sessionSerial ?>">
                <div class="row mb-3 justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <?php if ($status) {
                            ?>
                            <button type="button" id="btnSave" class="btn btn-outline-success" onclick="confirmSave()">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar
                            </button>
                            <?php
                        }
                        ?>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" id='car_alt1'>
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-success" role="alert">
                        <h1 class="alert-heading">Alerta... !!!</h1>
                        <h5 class="alert-heading">Cliente: <?= $dataAccount['short_name'] ?? '' ?></h5>
                        <h5 class="alert-heading">Codigo de cliente: <?= $dataAccount['ccodcli'] ?></h5>
                        <p>En la cuenta <?= $account ?? '' ?> no se pueden realizar retiros, ya que se encuentra vinculada a un
                            crédito.
                        </p>
                        <hr>
                        <p class="mb-0">El cliente tiene que terminar de cancelar el crédito para realizar retiros en su cuenta.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Función que actualiza el textarea "concepto" con el mensaje por defecto
            function updateConcepto() {
                // Obtén el nombre del titular desde el campo "name"
                var titular = document.getElementById("name").value.trim();
                // Define el mensaje por defecto para retiros
                var defaultMessage = "RETIRO A CUENTA DE " + (titular ? titular : "[Titular]");
                // Asigna ese mensaje al campo "concepto"
                document.getElementById("concepto").value = defaultMessage;
            }
            // Cuando se cargue la página y cada vez que se modifique el monto, actualiza el concepto
            document.addEventListener("DOMContentLoaded", function () {
                updateConcepto();
                var montoInput = document.getElementById("monto");
                if (montoInput) {
                    montoInput.addEventListener("input", updateConcepto);
                }
                var nameInput = document.getElementById("name");
                if (nameInput) {
                    nameInput.addEventListener("input", updateConcepto);
                }
            });

            function retirar(callback) {
                let conceoptval = document.getElementById("concepto").value;
                let salida = document.getElementById("salida").value;
                let account = '<?= $account ?>';
                obtiene(
                    ['ccodaho', 'dfecope', 'cnumdoc', 'monto', 'numcheque', 'concepto', 'transfer_numero_referencia', 'transfer_fecha'],
                    ['salida', 'tipdoc', 'bancoid', 'cuentaid', 'negociable', 'transfer_cuentaid'],
                    [],
                    'create_retiros_ahommov',
                    '0',
                    ['<?= $account ?>', 'R'],
                    function (response) {
                        creaComprobante(response);
                        if (salida == "1") {
                            Swal.fire({
                                title: 'Imprimir libreta?',
                                showDenyButton: true,
                                confirmButtonText: 'Imprimir',
                                denyButtonText: `Cancelar`,
                                allowOutsideClick: false
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    creaLib(account);
                                }
                            });
                        }
                        printdiv2("#cuadro", '0');
                        // console.log("Retiro response:", response);
                        if (typeof callback === 'function') {
                            callback(response);
                        }
                    }
                );
            }

            function inactivarCuenta() {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: '../src/cruds/crud_ahorro.php',
                        method: 'POST',
                        data: {
                            condi: 'Update_ahoEST',
                            cuenta: '<?= $account ?>'
                        },
                        success: function (response) {
                            let data = JSON.parse(response);
                            if (data[1] === '1') {
                                resolve();
                            } else {
                                reject();
                            }
                        },
                        error: function () {
                            reject();
                        }
                    });
                });
            }

            function confirmSave() {
                var cantidad = parseFloat(document.getElementById("monto").value);
                if (isNaN(cantidad) || cantidad <= 0) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Ingrese un monto válido mayor a cero."
                    });
                    return;
                }
                var saldoActual = parseFloat('<?= ($dataAccount["saldo"] ?? 0) - $saldoEnCompensacion ?>');
                if (cantidad > saldoActual) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "El monto a retirar es mayor que el saldo disponible (" + saldoActual + ")."
                    });
                    return;
                }

                verifyFingerprint(function () {
                    if (cantidad === saldoActual) {
                        Swal.fire({
                            title: "¡Advertencia!",
                            text: "Este retiro dejará la cuenta con saldo 0. ¿Desea inactivar la cuenta?",
                            icon: "warning",
                            showDenyButton: true,
                            showCancelButton: true,
                            confirmButtonText: "Sí, inactivar",
                            denyButtonText: "No, mantener activa",
                            cancelButtonText: "Cancelar operación"
                        }).then((result) => {
                            if (result.isDismissed) {
                                return;
                            }
                            if (result.isConfirmed) {
                                retirar(function () {
                                    inactivarCuenta().then(function () { }).catch(function () { });
                                });
                            } else if (result.isDenied) {
                                retirar(function () {
                                    // console.log("Retiro exitoso, ejecutando callback...");
                                });
                            }
                        });
                    } else {
                        // Proceso normal para retiros parciales
                        Swal.fire({
                            title: "Deseas Retirar la cantidad de Q." + cantidad + "?",
                            text: " ",
                            icon: "question",
                            showCancelButton: true,
                            showCloseButton: true,
                            confirmButtonText: "Sí, Retirar",
                            confirmButtonColor: '#28B463',
                            cancelButtonText: "Cancelar"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                retirar(function () {

                                });
                            }
                        });
                    }
                }, 3, '<?= $account ?? 0; ?>');
            }

            $(document).ready(function () {
                var bandera = $('#idControl').val();
                switch (bandera) {
                    case '0':
                        $("#carPrincipal").hide();
                        $("#car_alt1").show();
                        break;
                    default:
                        $("#carPrincipal").show();
                        $("#car_alt1").hide();
                        break;
                }
            });

            function openWindow(element) {
                const selectedOption = element.options[element.selectedIndex];
                const tipoDato = selectedOption.getAttribute('data-typedata');
                if (tipoDato === '3') {
                    $("#region_tipo_transferencia").show();
                } else {
                    $("#region_tipo_transferencia").hide();
                }
            }
        </script>
        <?php
        break;

    case 'transferencias':
        $status = true;
        ?>
        <div class="card">
            <input type="hidden" id="file" value="aho_02">
            <input type="hidden" id="condi" value="transferencias">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>Transferencias entre Cuentas de Ahorro
                </h4>
            </div>
            <div class="card-header">
                <small class="text-muted">Seleccione las cuentas de origen y destino para realizar la transferencia.</small>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } else { ?>

                    <div class="container-fluid">
                        <!-- Sección de Cuentas -->
                        <div class="row mb-4">
                            <!-- Cuenta Origen -->
                            <div class="col-md-6">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white py-2">
                                        <h6 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Cuenta Origen</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="cuenta_origen_search"
                                                    placeholder="Buscar por nombre o número de cuenta..." data-bs-toggle="tooltip"
                                                    title="Escriba para buscar una cuenta" readonly>
                                                <button class="btn btn-outline-primary" type="button"
                                                    onclick="abrirModalCuentas('origen')" data-bs-toggle="tooltip"
                                                    title="Ver todas las cuentas">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div id="info_cuenta_origen" class="d-none">
                                            <div class="alert alert-info py-2 mb-2">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <strong id="nombre_origen"></strong>
                                                        <br><small class="text-muted">Cuenta: <span
                                                                id="numero_origen"></span></small>
                                                        <br><small class="text-muted">Tipo: <span id="tipo_origen"></span></small>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- <div class="text-center">
                                                <span class="badge bg-success fs-6 py-2 px-3">
                                                    Saldo: Q <span id="saldo_origen">0.00</span>
                                                </span>
                                            </div> -->
                                        </div>
                                        <input type="hidden" id="cuenta_origen_codigo">
                                        <!-- <input type="hidden" id="saldo_origen_valor"> -->
                                    </div>
                                </div>
                            </div>

                            <!-- Cuenta Destino -->
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white py-2">
                                        <h6 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Cuenta Destino</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="cuenta_destino_search"
                                                    placeholder="Buscar por nombre o número de cuenta..." data-bs-toggle="tooltip"
                                                    title="Escriba para buscar una cuenta" readonly>
                                                <button class="btn btn-outline-success" type="button"
                                                    onclick="abrirModalCuentas('destino')" data-bs-toggle="tooltip"
                                                    title="Ver todas las cuentas">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div id="info_cuenta_destino" class="d-none">
                                            <div class="alert alert-info py-2 mb-2">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <strong id="nombre_destino"></strong>
                                                        <br><small class="text-muted">Cuenta: <span
                                                                id="numero_destino"></span></small>
                                                        <br><small class="text-muted">Tipo: <span id="tipo_destino"></span></small>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- <div class="text-center">
                                                <span class="badge bg-info fs-6 py-2 px-3">
                                                    Saldo: Q <span id="saldo_destino">0.00</span>
                                                </span>
                                            </div> -->
                                        </div>
                                        <input type="hidden" id="cuenta_destino_codigo">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Indicador de transferencia -->
                        <div class="row mb-4" id="indicador_transferencia" style="display: none;">
                            <div class="col-12 text-center">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="bg-primary text-white rounded-circle p-2 me-2">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <span id="nombre_origen_transfer" class="fw-bold"></span>
                                    <i class="fas fa-arrow-right mx-3 text-primary fs-4"></i>
                                    <span id="nombre_destino_transfer" class="fw-bold"></span>
                                    <div class="bg-success text-white rounded-circle p-2 ms-2">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Datos de la Transferencia -->
                        <div class="row" id="datos_transferencia" style="display: none;">
                            <div class="col-md-8 offset-md-2">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark py-2">
                                        <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Datos de la Transferencia</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="numero_documento" class="form-label">
                                                    <i class="fas fa-file-alt me-1"></i>No. Documento
                                                </label>
                                                <input type="text" class="form-control" id="numero_documento"
                                                    placeholder="Número de documento" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="fecha_transferencia" class="form-label">
                                                    <i class="fas fa-calendar me-1"></i>Fecha
                                                </label>
                                                <input type="date" class="form-control" id="fecha_transferencia"
                                                    value="<?php echo date("Y-m-d"); ?>" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="monto_transferencia" class="form-label">
                                                    <span class="fas fa-money-bill-wave me-1"></span>Monto a Transferir (Q)
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Q</span>
                                                    <input type="number" step="0.01" class="form-control" id="monto_transferencia"
                                                        placeholder="0.00" min="0.01" required>
                                                </div>
                                                <!-- <div class="form-text">
                                                    <span id="monto_letras" class="text-muted"></span>
                                                </div> -->
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="concepto_transferencia" class="form-label">
                                                    <i class="fas fa-comment me-1"></i>Concepto
                                                </label>
                                                <textarea class="form-control" id="concepto_transferencia" rows="2"
                                                    placeholder="Ingrese el concepto de la transferencia"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="row mt-4" id="botones_accion" style="display: none;">
                            <div class="col-12 text-center">
                                <button type="button" class="btn btn-success btn-lg me-3"
                                    onclick="obtiene(['<?= $csrf->getTokenName() ?>','cuenta_origen_codigo','cuenta_destino_codigo','numero_documento','fecha_transferencia','monto_transferencia','concepto_transferencia'], 
                                [], [], 'create_transferencia', '0', [], 'null', true, '¿Confirma que desea realizar la transferencia por Q. ' + document.getElementById('monto_transferencia').value + '?')">
                                    <i class="fas fa-exchange-alt me-2"></i>Realizar Transferencia
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="printdiv2('#cuadro','0')">
                                    <i class="fas fa-times me-2"></i>Cancelar
                                </button>
                            </div>
                        </div>
                    </div>

                <?php } ?>
            </div>
        </div>
        <?php echo $csrf->getTokenField(); ?>

        <!-- Modal para seleccionar cuentas -->
        <div class="modal fade" id="modalSeleccionarCuenta" tabindex="-1" aria-labelledby="modalSeleccionarCuentaLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalSeleccionarCuentaLabel">
                            <i class="fas fa-search me-2"></i>Seleccionar Cuenta
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tabla_cuentas" style="width: 100%;">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Cuenta</th>
                                        <th>Codigo Cliente</th>
                                        <th>Identificación</th>
                                        <th>Tipo</th>
                                        <th>Nombre</th>
                                        <th>Acción</th>
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

        <script>
            // let tipoSeleccion = ''; // 'origen' o 'destino'

            // Inicializar tooltips
            $(document).ready(function () {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });

            });

            function abrirModalCuentas(tipo) {
                tipoSeleccion = tipo;
                $('#modalSeleccionarCuentaLabel').html('<i class="fas fa-search me-2"></i>Seleccionar Cuenta ' +
                    (tipo === 'origen' ? 'de Origen' : 'de Destino'));
                $('#modalSeleccionarCuenta').modal('show');
            }

            function seleccionarCuenta(cuenta, cliente, nit, tipo) {
                seleccionarCuentaDirecta(cuenta, cliente, nit, tipo, tipoSeleccion);
                $('#modalSeleccionarCuenta').modal('hide');
            }

            function seleccionarCuentaDirecta(cuenta, cliente, nit, tipo, tipoSeleccion) {
                // Verificar que no sea la misma cuenta en origen y destino
                if (tipoSeleccion === 'destino' && $('#cuenta_origen_codigo').val() === cuenta) {
                    Swal.fire('Error', 'No puede seleccionar la misma cuenta como origen y destino', 'error');
                    return;
                }
                if (tipoSeleccion === 'origen' && $('#cuenta_destino_codigo').val() === cuenta) {
                    Swal.fire('Error', 'No puede seleccionar la misma cuenta como origen y destino', 'error');
                    return;
                }

                if (tipoSeleccion === 'origen') {
                    $('#cuenta_origen_search').val(cuenta + ' - ' + cliente);
                    $('#cuenta_origen_codigo').val(cuenta);
                    $('#nombre_origen').text(cliente);
                    $('#numero_origen').text(cuenta);
                    $('#tipo_origen').text(tipo);
                    // $('#saldo_origen').text(saldo.toLocaleString('es-GT', {
                    //     minimumFractionDigits: 2
                    // }));
                    //$('#saldo_origen_valor').val(saldo);
                    $('#info_cuenta_origen').removeClass('d-none');
                    $('#nombre_origen_transfer').text(cliente);
                } else {
                    $('#cuenta_destino_search').val(cuenta + ' - ' + cliente);
                    $('#cuenta_destino_codigo').val(cuenta);
                    $('#nombre_destino').text(cliente);
                    $('#numero_destino').text(cuenta);
                    $('#tipo_destino').text(tipo);
                    // $('#saldo_destino').text(saldo.toLocaleString('es-GT', {
                    //     minimumFractionDigits: 2
                    // }));
                    $('#info_cuenta_destino').removeClass('d-none');
                    $('#nombre_destino_transfer').text(cliente);
                }

                // Mostrar secciones si ambas cuentas están seleccionadas
                verificarCuentasSeleccionadas();
            }

            function verificarCuentasSeleccionadas() {
                let origenSeleccionado = $('#cuenta_origen_codigo').val() !== '';
                let destinoSeleccionado = $('#cuenta_destino_codigo').val() !== '';

                if (origenSeleccionado && destinoSeleccionado) {
                    $('#indicador_transferencia').show();
                    $('#datos_transferencia').show();
                    $('#botones_accion').show();

                    // Generar concepto automático
                    let concepto = 'Transferencia de ' + $('#nombre_origen_transfer').text() +
                        ' a ' + $('#nombre_destino_transfer').text();
                    $('#concepto_transferencia').val(concepto);
                }
            }

            $(document).ready(function () {
                $("#tabla_cuentas").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/clientesAhorros.php",
                    "columnDefs": [{
                        "data": 0,
                        "targets": 5,
                        render: function (data, type, row) {
                            // console.log(data);
                            // console.log(row);
                            //cuenta, cliente, nit, tipo, saldo
                            return `<button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" onclick="seleccionarCuenta('${data}','${row[4]}','${row[2]}','${row[3]}')" >Aceptar</button>`;
                        }

                    },],
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
        <?php

        break;

    //Listado del día
    case 'ListadoDelDia':
        $status = false;
        try {
            $database->openConnection();
            $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], 'estado=1');
            if (empty($tiposcuentas)) {
                throw new SoftException("No se encontró la cuenta, verifique el número de cuenta o si el cliente esta activo");
            }

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'cod_agenc', 'nom_agencia']);
            if (empty($agencias)) {
                throw new SoftException("No se encontró la agencia, verifique la configuración del sistema");
            }

            $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], "estado=1");
            if (empty($users)) {
                throw new SoftException("No hay usuarios");
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
        <!-- APR_05_LstdCntsActvsDspnbls -->
        <!-- <div class="text" style="text-align:center">LISTADO DEL DÍA</div> -->
        <input type="text" value="ListadoDelDia" id="condi" style="display: none;">
        <input type="text" value="aho_02" id="file" style="display: none;">
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
                appearance: none;
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

        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-file-signature me-2"></i>LISTADO DEL DÍA
                </h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>Atención:</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <div class="container-fluid">
                    <!-- Sección de Transacciones -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Transacciones</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-12">
                                            <h6 class="text-muted mb-3">Incluir</h6>
                                            <div id="checklist">
                                                <input checked value="all" name="checks" type="checkbox" id="all">
                                                <label for="all">Todo</label>
                                                <input checked value="lib" name="checks" type="checkbox" id="lib">
                                                <label for="lib">Cambios de libreta</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <h6 class="text-muted mb-3">Ingresos</h6>
                                            <div id="checklist">
                                                <input checked value="int" name="checks" type="checkbox" id="int">
                                                <label for="int">Intereses</label>
                                                <input checked value="dep" name="checks" type="checkbox" id="dep">
                                                <label for="dep">Depósitos</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <h6 class="text-muted mb-3">Egresos</h6>
                                            <div id="checklist">
                                                <input checked value="isr" name="checks" type="checkbox" id="isr">
                                                <label for="isr">Impuestos</label>
                                                <input checked value="ret" name="checks" type="checkbox" id="ret">
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
                    <!-- Sección de Filtros -->
                    <div class="row mb-4">
                        <!-- Filtro de Tipo de Cuenta -->
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Tipo de Cuenta</h6>
                                </div>
                                <div class="card-body">
                                    <select class="form-select" aria-label="Default select example" id="tipcuenta" multiple
                                        data-control="select2" data-placeholder="Todos los tipos de cuentas">
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

                        <!-- Filtro de Fechas -->
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-calendar me-2"></i>Rango de Fechas</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="finicio" class="form-label">Desde</label>
                                        <input type="date" class="form-control" id="finicio"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div>
                                        <label for="ffin" class="form-label">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro por Cajas -->
                        <div class="col-lg-4 col-md-12 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-building me-2"></i>Filtro por Cajas</h6>
                                </div>
                                <div class="card-body" x-data="{option:'allofi'}">
                                    <div class="mb-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" checked @click="option='allofi'">
                                            <label class="form-check-label" for="allofi">Incluir todos</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" @click="option='anyofi'">
                                            <label class="form-check-label" for="anyofi">Cajas agencia</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyuser"
                                                value="anyuser" @click="option='anyuser'">
                                            <label class="form-check-label" for="anyuser">Caja usuarios</label>
                                        </div>
                                    </div>

                                    <div x-show="option=='anyofi'" x-cloak>
                                        <label for="codofi" class="form-label">Agencia</label>
                                        <select class="form-select" id="codofi" :required="option=='anyofi'">
                                            <option value="" disabled selected>Seleccione una agencia</option>
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                echo "<option value='{$ofi['id_agencia']}'>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div x-show="option=='anyuser'" x-cloak>
                                        <label for="codusu" class="form-label">Usuario</label>
                                        <select class="form-select" id="codusu" :required="option=='anyuser'">
                                            <option value="" disabled selected>Seleccione un cajero</option>
                                            <?php
                                            foreach ($users as $user) {
                                                echo "<option value='{$user['id_usu']}'>{$user['nombre']} {$user['apellido']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                <button type="button" class="btn btn-primary" onclick="process('show',0);">
                                    <i class="fas fa-eye me-1"></i> Ver
                                </button>
                                <button type="button" class="btn btn-success" onclick="process('xlsx',1);">
                                    <i class="fas fa-file-excel me-1"></i> Excel
                                </button>
                                <button type="button" class="btn btn-danger" onclick="process('pdf',0);">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="printdiv2('#cuadro','0')">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Resultados -->
                    <div id="divshow" style="display: none;">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Resultados</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="tbdatashow" class="table table-sm table-striped table-hover small-font">
                                        <thead class="table-dark">
                                            <tr></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico -->
                    <div id="divshowchart" style="display: none;">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Gráfico</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="myChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .small-font th,
            .small-font td {
                font-size: 0.6rem;
            }

            [x-cloak] {
                display: none !important;
            }
        </style>
        <script>
            $(document).ready(function () {
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

                todoCheckbox.addEventListener('change', function () {
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
                    checkbox.addEventListener('change', function () {
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

                $('#tipcuenta').select2({
                    theme: 'bootstrap-5',
                    language: 'es',
                    closeOnSelect: false
                });
            });


            function process(tipo, download) {
                let checkedValues = [];
                document.querySelectorAll('input[name="checks"]:checked')
                    .forEach(cb => checkedValues.push(cb.value));
                reportes([
                    ['finicio', 'ffin'],
                    ['codofi', 'codusu'],
                    ['ragencia'],
                    [checkedValues, $('#tipcuenta').val()],
                ],
                    tipo,
                    'listado_dia',
                    download,
                    0, 'dfecope', 'monto', 2, 'Montos', 0
                );
            }
        </script>
        <?php
        break;
    //Imprime Operaciones Libreta
    case 'updateLibreta':
        $id = $_POST["xtra"];
        $datos = [
            "id_tipo" => "",
        ];
        // // echo $id;
        $datoscli = mysqli_query($conexion, "SELECT * FROM `ahomcta` WHERE `ccodaho`='$id'");
        $bandera = "No se encontró la cuenta ingresada, verifique el número de cuenta o si el cliente esta activo.";
        while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
            $idcli = ($da["ccodcli"]);
            $nit = ($da["num_nit"]);
            $nlibreta = ($da["nlibreta"]);
            $estado = ($da["estado"]);
            ($estado != "A") ? $bandera = "Cuenta Inactiva" : $bandera = "";
            $bandera = "";
        }
        if ($bandera == "") {
            // echo $idcli;
            $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente`='$idcli'");
            //$data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente`='$idcli' OR `no_tributaria` = '$nit'");
            $dat = mysqli_fetch_array($data, MYSQLI_ASSOC);
            $nombre = ($dat["short_name"]);

            $mov = mysqli_query($conexion, "SELECT id_mov,cnumdoc, dfecope, monto, crazon, ctipope, numlinea, correlativo, lineaprint FROM ahommov WHERE `ccodaho`=$id and nlibreta = $nlibreta and cestado = 1 ORDER BY numlinea asc");
            $movimientos = mysqli_fetch_all($mov, MYSQLI_ASSOC);
        }
        include_once "../../src/cris_modales/mdls_aho_new.php";
        ?>
        <!--Aho-1-ImprsnLbrta Impresión Libreta -->
        <div class="card shadow-sm border-0">
            <input type="hidden" id="file" value="aho_02">
            <input type="hidden" id="condi" value="updateLibreta">

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
                            <input type="text" class="form-control" id="ccodaho" placeholder="000-000-00-000000"
                                value="<?= $bandera === "" ? $id : "" ?>">
                            <button class="btn btn-outline-primary" type="button" title="Aplicar cuenta"
                                onclick="aplicarcod('ccodaho')">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-outline-primary" type="button" title="Buscar cuenta" data-bs-toggle="modal"
                                data-bs-target="#findahomcta2">
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
                        <button type="button" class="btn btn-primary btn-lg" onclick="checkmov('<?= htmlspecialchars($id) ?>')">
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
        break;
    case 'apertura_ahoprogramado':
        $id = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $ahomtipModel = new Ahomtip($database);
            $columns = ['id_tipo', 'ccodtip', 'nombre', 'cdescripcion', 'tasa', 'diascalculo', 'mindepo'];
            $ahomtips = $ahomtipModel->selectAhomtipColumns($columns, "tipcuen='pr' AND estado=1");
            if (empty($ahomtips)) {
                $showmensaje = true;
                throw new Exception("No se encontraron productos de Ahorro programado");
            }

            $cliente = $database->selectColumns(
                "tb_cliente",
                ['idcod_cliente', 'short_name'],
                "idcod_cliente=?",
                [$id]
            );
            if (empty($cliente)) {
                $showmensaje = true;
                throw new Exception("No se encontró al cliente");
            }
            $query = "SELECT tip.nombre, ccodaho FROM ahomcta cta 
                        INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2) 
                        WHERE ccodcli=? AND cta.estado='A' AND tip.tipcuen='cr'";
            $cuentas = $database->getAllResults($query, [$id]);
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

        ?>
        <style>
            .fc-event-title {
                white-space: normal;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>
        <div class="card">
            <input type="text" id="file" value="aho_02" style="display: none;">
            <input type="text" id="condi" value="apertura_ahoprogramado" style="display: none;">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">
                            <i class="fas fa-user"></i> Apertura de Cuenta de Ahorro Programado
                        </h4>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="contenedort container mt-12">
                    <!-- <h5 class="mb-4">Apertura de Cuenta de Ahorro Programado</h5> -->
                    <form id="savingsForm" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-sm-10 col-md-7 col-lg-6">
                                <span class="input-group-addon col-8">Cliente</span>
                                <input type="text" aria-label="Cliente" id="client" class="form-control  col" placeholder=""
                                    value="<?= $cliente[0]['short_name'] ?? '' ?>" readonly>
                            </div>
                            <div class="col-sm-12 col-md-6 col-lg-3">
                                <br>
                                <button title="Buscar cliente" class="btn btn-primary" type="button" id="button-addon1"
                                    data-bs-toggle="modal" data-bs-target="#buscar_cli_gen">
                                    <i class="fa fa-magnifying-glass"></i> Busqueda de Cliente
                                </button>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="gridtarjetas">
                                <?php
                                if (!empty($ahomtips)) {
                                    foreach ($ahomtips as $key => $tip) {
                                        ?>
                                        <div style="cursor:pointer;" name="targets" id="<?= $tip['ccodtip'] ?>" class="tarjeta"
                                            onclick="selectahomtip('<?= $tip['ccodtip'] ?>',<?= $tip['tasa'] ?>)">
                                            <div class="titulo"><?= $tip['nombre'] ?></div>
                                            <div class="cuerpo">
                                                <i class="fa-solid fa-piggy-bank"></i><?= $tip['cdescripcion'] ?>
                                            </div>
                                            <div class="pie">
                                                Tasa: <?= $tip['tasa'] ?> %
                                            </div>
                                        </div>
                                        <?php
                                    }
                                }
                                ?>
                            </div>
                            <div style="display:none;" class="row">
                                <div class="col-md-6">
                                    <select class="form-control " id="tipCuenta">
                                        <option value="0" selected>Seleccione un tipo de producto</option>
                                        <?php
                                        if (!empty($ahomtips)) {
                                            foreach ($ahomtips as $key => $tip) {
                                                echo '<option value="' . $tip['ccodtip'] . '">' . $tip['nombre'] . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label for="fechaInicio" class="form-label">Fecha de Inicio</label>
                                <input type="date" class="form-control" id="fechaInicio" value="<?= $hoy ?? '' ?>" required>
                            </div>
                            <div class="col-6">
                                <label for="savingsType" class="form-label">Tipo de Ahorro</label>
                                <select class="form-select" id="savingsType" required>
                                    <!-- <option selected disabled value="">Seleccione un tipo</option> -->
                                    <option selected value="1">Ahorro por Objetivo</option>
                                    <option value="2">Ahorro Periódico</option>
                                </select>
                            </div>
                        </div>
                        <div id="divgen" style="display:block;">
                            <div class="row mb-3">
                                <div class="col-4">
                                    <label id="lblmonto" for="montoDeposito" class="form-label">Monto Objetivo</label>
                                    <input type="number" class="form-control" id="montoDeposito" min="0" step="0.01">
                                </div>
                                <div class="col-4">
                                    <label for="frecuenciaDeposito" class="form-label">Frecuencia</label>
                                    <select class="form-select" id="frecuenciaDeposito">
                                        <option value="7">Semanal</option>
                                        <option value="15">Quincenal</option>
                                        <option value="30">Mensual</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label for="plazo" class="form-label">Plazo en meses</label>
                                    <input type="number" class="form-control" id="plazo" min="1">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-4">
                                <label for="tasaInteres" class="form-label">Tasa de Interés Anual (%)</label>
                                <input type="number" class="form-control" id="tasaInteres" min="0" max="100" step="0.01"
                                    required>
                            </div>
                            <div class="col-4">
                                <label for="libreta" class="form-label">Asignar Libreta</label>
                                <input type="text" class="form-control" id="libreta" min="0" required>
                            </div>

                            <div class="col-4">
                                <label for="estrict" class="form-label">Uso de plan estricto</label>
                                <select class="form-select" id="estrict"
                                    title="Si escoge sí, no podrá depositar un monto diferente a la cuota pactada">
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3" style="display: none;">
                            <div class="col-12">
                                <label for="ccodahopig" class="form-label">Vincular una cuenta</label>
                                <select class="form-select" id="ccodahopig" title="Para el traslado de fondos de emergencia">
                                    <option value="0">Seleccione una cuenta del cliente</option>
                                    <?php
                                    if (!empty($cuentas)) {
                                        foreach ($cuentas as $key => $cuenta) {
                                            echo '<option value="' . $cuenta['ccodaho'] . '">' . $cuenta['ccodaho'] . ' - ' . $cuenta['nombre'] . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <?php echo $csrf->getTokenField(); ?>
                        <button type="submit" class="btn btn-primary">Calcular Proyección</button>
                    </form>

                    <div id="proyeccion" class="mt-4" style="display:none;">
                        <h3>Proyección de Ahorro</h3>
                        <div class="card mb-4">
                            <div class="card-body">
                                <p class="card-text" id="proyeccionResultado"></p>
                            </div>
                        </div>

                        <button class="btn btn-success" onclick="saveahoprog('cahomctaprogramado')">Guardar</button>
                        <!-- <button type="button" id="btnSave" class="btn btn-outline-success" onclick="reportes([['fechaInicio', 'tasaInteres', 'montoDeposito', 'plazo', 'libreta'], ['tipCuenta', 'savingsType', 'estrict', 'frecuenciaDeposito', 'ccodahopig'],[],[]], 'xlsx', 'planahoprogramado', 1)">
                                    <i class="fa-solid fa-file-excel"></i> Tabla de pagos
                                </button> -->
                        <h4>Tabla de Pagos</h4>
                        <style>
                            #tablaPagos td {
                                text-align: right;
                            }
                        </style>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Depósito</th>
                                    <!-- <th>Interés</th> -->
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody id="tablaPagos">
                            </tbody>
                        </table>
                        <h4 class="mt-4">Calendario de Pagos</h4>
                        <div id="calendario"></div>
                    </div>

                </div>

            </div>
        </div>
        <script>
            document.getElementById('savingsType').addEventListener('change', function () {
                document.getElementById("lblmonto").textContent = this.value === '1' ? "Monto Objetivo" :
                    "Monto de Depósito";
            });
            document.getElementById('savingsForm').addEventListener('submit', function (e) {
                e.preventDefault();
                if (!validateForm()) {
                    loaderefect(0);
                    iziToast.error({
                        title: 'Porfavor!',
                        position: 'center',
                        message: 'Rellene todos los campos obligatorios',
                        timeout: 5000
                    });
                    return;
                }
                saveahoprog('calculoprog');
            });

            function saveahoprog(condi) {
                obtiene(['<?= $csrf->getTokenName() ?>', 'fechaInicio', 'tasaInteres', 'montoDeposito', 'plazo', 'libreta'], [
                    'tipCuenta', 'savingsType', 'estrict', 'frecuenciaDeposito', 'ccodahopig'
                ], [], condi, '0', ['<?= htmlspecialchars($secureID->encrypt($id)) ?>']);
            }

            function loaddataprog(data, inputs, selects) {
                tablaPagos = data[2];
                eventos = data[3];
                params = {
                    '7': "semanal",
                    '15': "quincenal",
                    '30': "mensual",
                };
                fechaInicio = inputs[1];
                monto = parseFloat(inputs[3]);
                cuotaahorro = parseFloat(tablaPagos[0]['deposito']);
                saldoAcumulado = parseFloat(tablaPagos[tablaPagos.length - 1]['saldo']);

                resultado = (selects[1] === '1') ? `Para alcanzar su objetivo de Q${monto.toFixed(2)} en ${inputs[4]} meses, 
                                    necesitará ahorrar Q${cuotaahorro.toFixed(2)} ${params[selects[3]]}es. 
                                    Al final del plazo, su saldo será de Q${saldoAcumulado.toFixed(2)}.` : `Con depósitos de Q${cuotaahorro.toFixed(2)} ${params[selects[3]]}es durante ${inputs[4]} meses, 
                                    su saldo final será de Q${saldoAcumulado.toFixed(2)}.`;


                document.getElementById('proyeccionResultado').textContent = resultado;
                document.getElementById('proyeccion').style.display = 'block';
                // Generar tabla de pagos
                const tablaPagosBody = document.getElementById('tablaPagos');
                tablaPagosBody.innerHTML = ''; //VERIFICAR QUE SE LIMPIEN LOS DATOS DE LA TABLA, PARA METER LOS NUEVOS
                tablaPagos.forEach(pago => {
                    const row = tablaPagosBody.insertRow();
                    row.insertCell(0).textContent = pago.no;
                    row.insertCell(1).textContent = pago.fecha;
                    row.insertCell(2).textContent = 'Q' + pago.deposito;
                    // row.insertCell(3).textContent = 'Q' + pago.interes;
                    row.insertCell(3).textContent = 'Q' + pago.saldo;
                });
                // console.log(eventos)
                // Generar calendario
                var calendarEl = document.getElementById('calendario');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    initialDate: fechaInicio,
                    events: eventos,
                });
                calendar.setOption('locale', 'es');
                calendar.render();
                var posicion = $("#tablaPagos").offset().top;
                $("html, body").animate({
                    scrollTop: posicion + 200
                }, 1000);
            }
        </script>
        <?php
        break;
} //FINAL DEL SWITCH

?>