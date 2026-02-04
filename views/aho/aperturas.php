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
require_once __DIR__ . '/../../includes/Config/model/ahorros/Ahomtip.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\PermissionManager;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);
date_default_timezone_set('America/Guatemala');

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
$condi = $_POST["condi"];
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

switch ($condi) {
    //Apertura de cuenta de ahorro
    case 'ApertCuenAhor':
        $ccodcli = $_POST["xtra"];

        $status = false;
        try {
            if ($ccodcli == '0') {
                throw new SoftException("Seleccione un cliente");
            }
            $database->openConnection();

            $datos = $database->selectColumns("tb_cliente", ["idcod_cliente", "short_name", "no_tributaria"], "estado=1 AND idcod_cliente=?", [$ccodcli]);
            if (empty($datos)) {
                throw new SoftException("No se encontró el cliente indicado");
            }
            $datosCliente = $datos[0];

            $productos = $database->selectColumns("ahomtip", ["id_tipo", "ccodtip", "nombre", "cdescripcion", "tasa"], "estado=1 AND ccodofi=?", [$ofi]);
            if (empty($productos)) {
                throw new SoftException("No se encontraron productos para la agencia indicada");
            }

            // $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (10,18) AND id_usuario=? AND estado=1", [$idusuario]);
            // $accessHandler = new PermissionHandler($permisos, 10);
            // $asignacion = new PermissionHandler($permisos, 18);

            $encargados = $database->selectColumns("tb_usuario", ["id_usu", "nombre", "apellido"], "estado=1 AND id_usu!=4");

            $userPermissions = new PermissionManager($idusuario);

            // // $userPermissions->isLevelOne(PermissionManager::VER_CREDITOS_CAJA);
            // if ($userPermissions->isLevelTwo(PermissionManager::VER_CREDITOS_CAJA)) {
            //     // Log::info("Este wey $idusuario tiene permiso de nivel 2, puede ver todos los creditos");
            // } elseif ($userPermissions->isLevelOne(PermissionManager::VER_CREDITOS_CAJA)) {
            //     $condiPermission = " AND ag.id_agencia = $idagencia ";
            //     // Log::info("Este wey $idusuario tiene permiso de nivel 1, puede ver creditos de su agencia");
            // } else {
            //     $condiPermission = " AND cm.CodAnal = $idusuario ";
            //     // Log::info("Este wey $idusuario tiene permiso de nivel 0, puede ver solo sus creditos");
            // }

            $status = 1;
        } catch (SoftException $e) {
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        include_once __DIR__ . "/../../src/cris_modales/modal_clientes_all.php";
        ?>

        <style>
            /* =============================
           CONTENEDOR PRINCIPAL
        ============================= */
            .card {
                border-radius: 14px;
                border: none;
            }

            .card-header {
                border-radius: 14px 14px 0 0;
            }

            .card-header h4 {
                font-weight: 600;
            }

            /* =============================
           SECCIONES
        ============================= */
            .card-body>.mb-4 {
                padding: 1.25rem;
                border-radius: 12px;
            }

            .card-body h5 {
                font-weight: 600;
            }

            /* =============================
           ALERTA
        ============================= */
            .alert-warning {
                border-radius: 12px;
                border-left: 6px solid #ffc107;
            }
        </style>

        <input type="text" id="condi" value="ApertCuenAhor" hidden>
        <input type="text" id="file" value="aperturas" hidden>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
            <h4 class="mb-0">
                <i class="fas fa-user-plus me-2"></i> Apertura de cuenta
            </h4>
            </div>
            <div class="card-body" id="formApertura">
            <?php if (!$status) { ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Atención:</strong> <?= $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <!-- Sección de Cliente -->
            <div class="mb-4">
                <h5 class="border-bottom pb-2 mb-3">
                <i class="fas fa-user me-2"></i>Información del Cliente
                </h5>
                <div class="row g-3">
                <div class="col-lg-8 col-md-7">
                    <label for="client" class="form-label">Cliente</label>
                    <input type="text" id="client" class="form-control" value="<?= $datosCliente['short_name'] ?? '' ?>"
                    readonly>
                </div>
                <div class="col-lg-4 col-md-5">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button title="Buscar cliente" class="btn btn-primary w-100" type="button" data-bs-toggle="modal"
                    data-bs-target="#buscar_cli_all">
                    <i class="fa fa-magnifying-glass me-2"></i>Buscar Cliente
                    </button>
                </div>
                </div>

                <div class="row g-3 mt-2" id="clientDataSection" style="display: <?= (!empty($datosCliente) && $status) ? 'block' : 'none'; ?>;">
                <div class="col-lg-6 col-md-8">
                    <label for="ccodcli" class="form-label">Código de cliente</label>
                    <input type="text" class="form-control" id="ccodcli" required
                    value="<?= $datosCliente['idcod_cliente'] ?? '' ?>" onchange="buscarcuentas();" readonly>
                </div>
                <div class="col-lg-4 col-md-4">
                    <label for="nit" class="form-label">NIT</label>
                    <input type="text" class="form-control" id="nit" value="<?= $datosCliente['no_tributaria'] ?? '' ?>"
                    readonly>
                </div>
                </div>
            </div>

            <!-- Sección de Productos -->
            <div class="mb-4" id="productosSection" style="display: <?= (!empty($datosCliente) && $status) ? 'block' : 'none'; ?>;">
                <h5 class="border-bottom pb-2 mb-3">
                <i class="fas fa-box-open me-2"></i>Productos Disponibles
                </h5>
                <div class="gridtarjetas">
                <?php
                if (!empty($productos)) {
                    foreach ($productos as $producto) {
                    echo '
                        <div data-value="' . $producto['ccodtip'] . '" id="' . $producto['ccodtip'] . '" 
                        style="cursor:pointer;" name="targets" class="tarjeta" 
                        onclick="getCorrelativo(`' . $producto['ccodtip'] . '`,`../src/cruds/crud_ahorro.php`,' . $producto['tasa'] . ')">
                        <div class="titulo">' . ($producto['nombre']) . '</div>
                        <div class="cuerpo">
                        <i class="fa-solid fa-piggy-bank"></i>  
                        ' . ($producto['cdescripcion']) . '
                        </div>
                        <div class="pie">
                        Tasa: ' . ($producto['tasa']) . ' %
                        </div>
                        </div>';
                    }
                }
                ?>
                </div>
            </div>

            <!-- Sección de Configuración de Cuenta -->
            <div class="mb-4" id="configuracionSection" style="display: <?= (!empty($datosCliente) && $status) ? 'block' : 'none'; ?>;">
                <h5 class="border-bottom pb-2 mb-3">
                <i class="fas fa-cog me-2"></i>Configuración de Cuenta
                </h5>

                <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <label for="correla" class="form-label">Correlativo</label>
                    <input type="text" id="correla" name="correla" class="form-control" readonly>
                </div>

                <div class="col-sm-12 col-md-12 col-lg-9"
                    style="display: <?= ($status && $userPermissions->isLevelOne(PermissionManager::APERTURA_CUENTA_SECUNDARIA_AHORROS)) ? 'block' : 'none'; ?>;">
                    <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <label for="cuentasSelect" class="form-label">Cuenta a acreditar interés</label>
                        <select id="cuentasSelect" class="form-select" onchange="corinteres();">
                        </select>
                    </div>

                    <div class="col-lg-4 col-md-6" style="display: none;" id="selecproducto">
                        <label for="createdselect" class="form-label">Producto de destino</label>
                        <select id="createdselect" class="form-select" onchange="ncorrelativo();">
                        <option value="0">Seleccione</option>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label for="correlainteres" class="form-label">Correlativo Interés</label>
                        <input type="text" id="correlainteres" name="correlainteres" value="0" class="form-control"
                        readonly>
                    </div>
                    </div>
                </div>

                </div>
            </div>

            <!-- Sección de Detalles -->
            <div class="mb-4" id="detallesSection" style="display: <?= (!empty($datosCliente) && $status) ? 'block' : 'none'; ?>;">
                <h5 class="border-bottom pb-2 mb-3">
                <i class="fas fa-file-alt me-2"></i>Detalles de la Cuenta
                </h5>

                <div class="row g-3">
                <div class="col-lg-4 col-md-6">
                    <label for="tasa" class="form-label">Tasa (%)</label>
                    <input type="number" class="form-control" step="0.01" id="tasa" placeholder="0.00" required>
                </div>

                <div class="col-lg-4 col-md-6">
                    <label for="libreta" class="form-label">Libreta</label>
                    <input type="text" class="form-control" id="libreta" required>
                </div>

                <div class="col-lg-4 col-md-6"
                    style="display: <?= ($status && $userPermissions->isLevelOne(PermissionManager::ASIGNAR_ENCARGADOS_CUENTAS_AHORROS)) ? 'block' : 'none'; ?>;">
                    <label for="encargado" class="form-label">Encargado</label>
                    <select id="encargado" class="form-select">
                    <option value="0" selected>No aplica</option>
                    <?php
                    if ($status) {
                        foreach ($encargados as $encargado) {
                        echo '<option value="' . $encargado['id_usu'] . '">'
                            . $encargado['nombre'] . ' ' . $encargado['apellido']
                            . '</option>';
                        }
                    }
                    ?>
                    </select>
                </div>

                <div class="col-lg-4 col-md-6">
                    <label for="fechaapertura" class="form-label">Fecha de apertura</label>
                    <input type="date" class="form-control" id="fechaapertura" value="<?= date("Y-m-d"); ?>">
                </div>
                </div>
            </div>

            <?php echo $csrf->getTokenField(); ?>

            <!-- Botones de acción -->
            <div class="d-flex gap-2 justify-content-end border-top pt-3" id="botonesSection" style="display: <?= (!empty($datosCliente) && $status) ? 'flex' : 'none'; ?>;">
                <?php if ($status): ?>
                <button type="button" class="btn btn-success"
                    onclick="obtiene(['<?= $csrf->getTokenName() ?>','tasa','libreta','correlainteres','fechaapertura'],['encargado','cuentasSelect','createdselect'],[],'cahomcta','0',['<?= htmlspecialchars($secureID->encrypt($datosCliente['idcod_cliente'] ?? '')) ?>',getTipCuenta()],'null', true, '¿Desea continuar con el proceso?')">
                    <i class="fa fa-floppy-disk me-2"></i>Guardar
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-danger" onclick="printdiv2('#cuadro','0')">
                <i class="fa-solid fa-ban me-2"></i>Cancelar
                </button>
            </div>
            </div>
        </div>

        <script>
            function updateClientSections() {
            const clientValue = document.getElementById('client').value.trim();
            const sections = ['clientDataSection', 'productosSection', 'configuracionSection', 'detallesSection', 'botonesSection'];
            
            sections.forEach(section => {
                document.getElementById(section).style.display = clientValue ? 'block' : 'none';
            });
            }

            // Ejecutar al cargar la página
            document.addEventListener('DOMContentLoaded', updateClientSections);
        </script>
        <script>
            function getTipCuenta() {
                return document.querySelector('.tarjeta.tarjeta-activa')?.getAttribute('data-value') ?? 'X';
            }

            $(document).ready(function () {
                inicializarValidacionAutomaticaGeneric('#formApertura');
            });
        </script>
        <?php
        break;
    //Beneficiarios de ahorro
    case 'BeneAho':
        $id = $_POST["xtra"];
        $datos = [
            "id_tipo" => "",
        ];
        $datoscli = mysqli_query($conexion, "SELECT * FROM `ahomcta` WHERE `ccodaho`=$id");
        $bandera = "No existe Cuenta";
        while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
            $idcli = $da["ccodcli"];
            $nit = $da["num_nit"];
            $nlibreta = $da["nlibreta"];
            $estado = $da["estado"];
            ($estado != "A") ? $bandera = "Cuenta Inactiva" : $bandera = "";
        }
        if ($bandera == "") {
            $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE estado=1 AND `idcod_cliente`='$idcli'");
            $nombre = "";
            $bandera = "No existe el cliente relacionado a la cuenta ";
            while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                $nombre = $dat["short_name"];
                $bandera = "";
            }
        }
        include_once "../../src/cris_modales/mdls_aho_new.php";
        ?>
        <!--Aho_0_BeneAho Inicio de Ahorro Sección 0 Beneficiario de Ahorro-->
        <!-- <div class="text" style="text-align:center">REGISTRO DE BENEFICIARIOS</div> -->
        <div class="card">
            <input type="text" id="file" value="aperturas" style="display: none;">
            <input type="text" id="condi" value="BeneAho" style="display: none;">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">
                            <i class="fas fa-user"></i> Registro de Beneficiarios
                        </h4>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!--Aho_0_BeneAho Cta.Ahorros-->
                <div class="container contenedort">
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <span class="input-group-addon col-8">Codigo de Cuenta</span>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control " value="0" id="ccodcrt" style="display: none;">
                                <input type="text" class="form-control " id="ccodaho" required placeholder="000-000-00-000000"
                                    value="<?php if ($bandera == "")
                                        echo $id; ?>">
                                <span class="input-group-text" id="basic-addon1">
                                    <?php if ($bandera == "") {
                                        echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="32" height="32" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00b341" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <circle cx="12" cy="12" r="9" />
                                        <path d="M9 12l2 2l4 -4" />
                                        </svg>';
                                    } else {
                                        echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-x" width="32" height="32" viewBox="0 0 24 24" stroke-width="1.5" stroke="#ff2825" fill="none" stroke-linecap="round" stroke-linejoin="round">
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
                            <button class="btn btn-primary" type="button" id="button-addon1" title="Aplicar cuenta ingresada"
                                onclick="aplicarcod('ccodaho')">
                                <i class="fa fa-check-to-slot"></i>
                            </button>
                            <button class="btn btn-primary" type="button" id="button-addon1" title="Buscar cuenta"
                                data-bs-toggle="modal" data-bs-target="#findahomcta2">
                                <i class="fa fa-magnifying-glass"></i> Busqueda de Cliente
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <span class="input-group-addon col-8">Nombre</span>
                            <input type="text" class="form-control " id="name" value="<?php if ($bandera == "") {
                                echo $nombre;
                            } else {
                                echo $nombre = "";
                            } ?>" readonly>
                        </div>
                    </div>
                    <?php if ($bandera != "" && $id != "0") {
                        echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                    }
                    ?>
                </div>
                <!--Aho_0_BeneAho Tabla de Datos-->
                <div class="container contenedort" style="padding: 8px !important;">
                    <div class="table-responsive">
                        <table id="table_id2" class="table table-hover table-border">
                            <thead class="text-light table-head-aho">
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
                                $total = 0;
                                if ($bandera == "") {
                                    $queryben = mysqli_query($conexion, "SELECT * FROM `ahomben` WHERE `codaho`='$id'");
                                    while ($rowq = mysqli_fetch_array($queryben, MYSQLI_ASSOC)) {
                                        $idahomben = encode_utf8($rowq["id_ben"]);
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
                                            <td> <button type="button" class="btn btn-warning" title="Editar Beneficiario" onclick="editben(' . $idahomben . ',`' . $bennom . '`,`' . $bendpi . '`,`' . $bendire . '`,' . $benparent . ',`' . $benfec . '`,' . $benporcent . ',`' . $bentel . '`)">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger" title="Eliminar Beneficiario" onclick="eliminar(' . $idahomben . ',`crud_ahorro`,`' . $id . '`,`dahomben`)">
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

                    <script>
                        //Datatable para parametrizacion
                        $(document).ready(function () {
                            convertir_tabla_a_datatable("table_id2");
                        });
                    </script>

                    <div class="row mt-2">
                        <!--TOTAL-->
                        <div class="col-md-3">
                            <label for="">Total: <?php echo $total; ?> %</label>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnnew" class="btn btn-outline-success"
                            onclick="crear_editar_beneficiario('<?php echo $id; ?>','<?php echo $nombre ?>')">
                            <i class="fa fa-file"></i> Agregar o editar
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-danger" onclick="reportes([
                    [],
                    [],
                    [],
                    ['<?php echo $id; ?>']
                ], 'pdf', 38, 0, 1, 'dfecope', 'monto', 2, 'Montos', 0);">
                            <i class="fa-solid fa-file-pdf"></i> Imprimir beneficiarios
                        </button>
                        <!-- <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button> -->
                    </div>
                </div>
            </div>


        </div>

        <!-- Modal -->
        <div class="modal fade" id="databen" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog  modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="staticBackdropLabel">Datos de Beneficiario</h1>
                    </div>
                    <div class="modal-body">
                        <!-- COD APORTACION Y NOMBRE -->
                        <div class="container contenedort">
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <!-- titulo -->
                                    <span class="input-group-addon col-8">Codigo de Cuenta</span>
                                    <div class="input-group">
                                        <input type="text" class="form-control " id="ccodaho_modal" readonly>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="input-group-addon col-8">Nombre</span>
                                    <input type="text" class="form-control " id="name_modal" readonly>
                                </div>
                            </div>
                        </div>
                        <!-- AGREGAR LA TABLA DE BENEFICIARIOS -->
                        <!-- MOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOODAAAAAAAAAAAAAAAAAAAAAAAAAAAAAALLLLLLLLLL -->
                        <div class="container contenedort recar_c" style="padding: 0px 0px 3px 0px !important;">
                            <div class="table-responsive">
                                <table id="tabla_ben" class="table table-hover table-border"
                                    style="max-width: 100% !important;">
                                    <thead class="text-light table-head-aho">
                                        <tr>
                                            <th>DPI</th>
                                            <th>Nombre Completo</th>
                                            <th>Fec. Nac.</th>
                                            <th>Parentesco</th>
                                            <th>%</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="">

                                    </tbody>
                                </table>
                            </div>
                            <div class="row mt-1 mb-1 ms-2">
                                <!--TOTAL-->
                                <div class="col-md-3">
                                    <label for="" id="total">Total</label>
                                </div>
                            </div>
                        </div>
                        <!-- MODAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAALLLLLLLLLLLLLLLLLLLLLLLLLL -->
                        <!-- ESTO ES EL MODAL DE AHORROS  -->
                        <div class="container contenedort">
                            <div class="row">
                                <!--Aho_0_BeneAho Nombre-->
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <span class="input-group-addon">Nombre</span>
                                        <input type="text" aria-label="Nombre Ben" id="benname" class="form-control col"
                                            placeholder="" required>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="input-group-addon">Dpi</span>
                                        <input type="text" aria-label="Cliente" id="bendpi" class="form-control col"
                                            placeholder="">
                                    </div>

                                </div>
                                <!--Aho_0_BeneAho Nacimiento, parentesco, porcentaje-->
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
                                            $parent = mysqli_query($conexion, "SELECT * FROM `tb_parentescos`");
                                            while ($tip = mysqli_fetch_array($parent)) {
                                                echo '<option value="' . $tip['id'] . '">' . $tip['descripcion'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <span class="input-group-addon">Telefono</span>
                                        <input type="text" aria-label="Tel Ben" id="bentel" class="form-control col"
                                            placeholder="">
                                    </div>
                                    <div class="col-md-3">
                                        <span class="input-group-addon">Nacimiento</span>
                                        <input type="date" class="form-control  col-10" id="bennac"
                                            value="<?php echo date("Y-m-d"); ?>">
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
                    </div>
                    <input type="radio" name="nada" id="0" checked style="display: none;">
                    <div class="modal-footer">
                        <button id="createben" type="button" class="btn btn-primary"
                            onclick="obtiene(['benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent'], ['benparent'], [], 'create_aho_ben', '<?php echo $id; ?>', ['<?php echo $id; ?>','<?php echo $bandera; ?>'])">
                            <i class="fa fa-floppy-disk"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-secondary"
                            onclick="cancelar_crear_editar_beneficiario('lista_beneficiarios','<?php echo $id; ?>')">Cancelar</button>
                        <button id="updateben" style="display:none;" type="button" class="btn btn-primary"
                            onclick="obtiene(['benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent','benporcentant','idben'], ['benparent'], [], 'update_aho_ben', '<?php echo $id; ?>', ['<?php echo $id; ?>',<?php echo $id; ?>])">
                            <i class="fa fa-floppy-disk"></i> Actualizar
                        </button>
                        <!-- <button type="button" class="btn btn-danger" onclick="Print_bene('lista_beneficiarios','ccodaho')">
                            <i class="fa-solid fa-file-pdf"></i> Imprimir beneficiarios
                        </button> -->
                    </div>
                </div>
            </div>
        </div>
        <script>
            function Print_bene(tabla, id) {
                // Get the account number from the element with the given id
                const accountNumber = document.getElementById(id).value;
                // Open the Benificarioscuenta.php file in a new window with the account number as a parameter
                window.open(`../../views/aho/reportes/Benificarioscuenta.php?ccodcta=${accountNumber}`, '_blank');
            }
        </script>
        <?php
        break;
    //Tipo de ahorros
    case 'TpsAhrrs':
        $id = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $agencias = $database->selectColumns('tb_agencia', ['cod_agenc', 'nom_agencia']);

            $ahomtipModel = new Ahomtip($database);
            $ahomtips = $ahomtipModel->getActiveAhomtip();
            $codigosExistentes = (empty($ahomtips)) ? [] : array_column($ahomtips, "ccodtip");

            // $datostip=$ahomtipModel->getAhomtipById($id);
            $columns = ['id_tipo', 'ccodofi', 'ccodtip', 'nombre', 'cdescripcion', 'tasa', 'diascalculo', 'tipcuen', 'mincalc', 'mindepo'];
            $ahomtip = $ahomtipModel->selectAhomtipColumns($columns, "id_tipo=?", [$id]);
            if (empty($ahomtip)) {
                $showmensaje = true;
                throw new Exception("No se encontró el tipo de cuenta");
            }
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        $newcode = generarCodigoUnico($codigosExistentes);
        // echo "<pre>";
        // echo print_r($ahomtip);
        // echo "</pre>";
        $title = ($id == 0) ? "Nuevo Tipo de cuenta" : "Actualizacion de tipo de cuenta " . $ahomtip[0]["ccodtip"];

        ?>
        <input type="text" value="TpsAhrrs" id="condi" style="display: none;">
        <input type="text" value="aperturas" id="file" style="display: none;">
        <?php $titlemodule = $_ENV['AHO_NAME_MODULE'] ?? "AHORRO"; ?>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-stream"></i> Crear Productos
                </h4>
            </div>
            <div class="card-body">
                <div class="container contenedort">
                    <h4 class="mb-4"><?= $title ?></h4>
                    <form id="ahomtipForm" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label for="agencia" class="form-label">Agencia</label>
                                <select class="form-select" id="agencia">
                                    <option selected disabled value="0">Seleccione una Agencia</option>
                                    <?php
                                    foreach ($agencias as $agencia) {
                                        $selected = ($id != 0 && $agencia['cod_agenc'] == $ahomtip[0]['ccodofi']) ? "selected" : "";
                                        echo "<option $selected value='" . $agencia["cod_agenc"] . "'>" . $agencia['nom_agencia'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label for="ccodtip" class="form-label">Código (Generacion Automática)</label>
                                <input readonly type="text" class="form-control" id="ccodtip" required
                                    value="<?= ($id == 0) ? $newcode : $ahomtip[0]['ccodtip'] ?>">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" required
                                    value="<?= $ahomtip[0]['nombre'] ?? '' ?>">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <label for="clase" class="form-label">Clase</label>
                                <select class="form-select" id="clase" required>
                                    <option value="cr" <?= ($id != 0 && $ahomtip[0]['tipcuen'] == 'cr') ? "selected" : "" ?>>
                                        Corriente</option>
                                    <option value="pf" <?= ($id != 0 && $ahomtip[0]['tipcuen'] == 'pf') ? "selected" : "" ?>>
                                        Plazo fijo</option>
                                    <option value="pr" <?= ($id != 0 && $ahomtip[0]['tipcuen'] == 'pr') ? "selected" : "" ?>>
                                        Programado</option>
                                    <option value="vi" <?= ($id != 0 && $ahomtip[0]['tipcuen'] == 'vi') ? "selected" : "" ?>>
                                        Vinculado</option>
                                </select>
                            </div>
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <label for="cdescripcion" class="form-label">Descripción</label>
                                <input type="text" class="form-control" id="cdescripcion" required
                                    value="<?= $ahomtip[0]['cdescripcion'] ?? '' ?>">
                            </div>

                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <label for="tasa" class="form-label">Tasa</label>
                                <input type="number" class="form-control" id="tasa" step="0.01" required
                                    value="<?= $ahomtip[0]['tasa'] ?? 0 ?>">
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <label for="dias" class="form-label">Dias Calculo</label>
                                <select class="form-select" id="dias" required>
                                    <option value="365" <?= ($id != 0 && $ahomtip[0]['diascalculo'] == '365') ? "selected" : ""; ?>>
                                        365</option>
                                    <option value="360" <?= ($id != 0 && $ahomtip[0]['diascalculo'] == '360') ? "selected" : ""; ?>>
                                        360</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <label for="mincalc" class="form-label">Saldo Mínimo</label>
                                <input title="Saldo Mínimo para Cálculo de Intereses" value="<?= $ahomtip[0]['mincalc'] ?? 0 ?>"
                                    type="number" class="form-control" id="mincalc" step="0.01" required>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-6">
                                <label for="mindepo" class="form-label">Depósito Mínimo</label>
                                <input type="number" class="form-control" id="mindepo" step="0.01" required
                                    value="<?= $ahomtip[0]['mindepo'] ?? 0 ?>">
                            </div>
                        </div>
                        <?php echo $csrf->getTokenField(); ?>
                        <div class="row justify-items-md-center m-3">
                            <div class="col align-items-center">

                                <?php
                                if ($id == "0") {
                                    ?>
                                    <button
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','ccodtip','nombre','cdescripcion','tasa','mincalc','mindepo'],['agencia','clase','dias'],[],'cahomtip','0',['aperturas'])"
                                        type="button" class="btn btn-outline-success" data-dismiss="modal">
                                        <i class="fa fa-floppy-disk"></i> Guardar
                                    </button>
                                    <?php
                                } else {
                                    ?>
                                    <button
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','ccodtip','nombre','cdescripcion','tasa','mincalc','mindepo'],['agencia','clase','dias'],[],`uahomtip`,'0',['<?= htmlspecialchars($secureID->encrypt($id)) ?>'])"
                                        type="button" class="btn btn-outline-primary" data-dismiss="modal">
                                        <i class="fa fa-floppy-disk"></i> Actualizar
                                    </button>
                                    <?php
                                }
                                ?>
                                <button type="button" id="undo" class="btn btn-outline-danger"
                                    onclick="printdiv2(`#cuadro`,'0')">
                                    <i class="fa fa-ban"></i> Cancelar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="container contenedort">
                    <h3>PRODUCTOS</h3>
                    <div class="table-responsive">
                        <table id="tiposahorros" class="table table-hover table-border">
                            <thead class="text-light table-head-aho">
                                <tr>
                                    <th>Agencia</th>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Tasa</th>
                                    <th>Opciones</th>
                                </tr>
                            </thead>
                            <tbody id="categoria_tb">
                                <?php
                                foreach ($ahomtips as $tipo) {
                                    $id = $tipo["id_tipo"];
                                    $ccodofi = $tipo["ccodofi"];
                                    $codigo = $tipo["ccodtip"];
                                    $nametip = $tipo["nombre"];
                                    $descripcion = $tipo["cdescripcion"];
                                    $tasa = $tipo["tasa"];
                                    echo '<tr> <td>' . $ccodofi . '</td>';
                                    echo '<td>' . $codigo . '</td>';
                                    echo '<td>' . $nametip . '</td>';
                                    echo '<td>' . $descripcion . '</td>';
                                    echo '<td>' . $tasa . '</td>';
                                    echo '<td>
                                                <button type="button" class="btn btn-default" title="Editar" onclick="printdiv2(`#cuadro`,' . $id . ')"> <i class="fa-solid fa-pen"></i></button>
                                                <button type="button" class="btn btn-default" title="Eliminar" onclick="eliminar(`' . htmlspecialchars($secureID->encrypt($id)) . '`,`crud_ahorro`,`0`,`dahomtip`)"> <i class="fa-solid fa-trash-can"></i></button>
                                             </td></tr> ';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <script>
                    $(document).ready(function () {
                        convertir_tabla_a_datatable("tiposahorros");
                    });
                </script>
            </div>
        </div>
        <!-- <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center m-3">
            <div class="m-3">
                <h4 class="mb-0">
                    <div class="text-white" style="text-align:center">TIPOS DE <?= strtoupper($titlemodule) ?></div>
                </h4>
            </div>
        </div> -->

        <div class="card">

        </div>
        <?php
        break;
    //Impresion de Libreta
    case 'iMprsnLbrt':
        $id = $_POST["xtra"];
        $datos = [
            "id_tipo" => "",
        ];

        $datoscli = mysqli_query($conexion, "SELECT * FROM `ahomcta` WHERE `ccodaho`=$id");
        $bandera = "Cuenta de ahorro no existe";
        while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
            $idcli = ($da["ccodcli"]);
            $nlibreta = ($da["nlibreta"]);
            $estado = ($da["estado"]);
            ($estado != "A") ? $bandera = "Cuenta de ahorros Inactiva" : $bandera = "";
        }
        if ($bandera == "") {
            $data = mysqli_query($conexion, "SELECT `short_name`,`no_tributaria` num_nit FROM `tb_cliente` WHERE estado=1 AND `idcod_cliente` = '$idcli'");
            $dat = mysqli_fetch_array($data, MYSQLI_ASSOC);
            $nombre = ($dat["short_name"]);
            $nit = ($dat["num_nit"]);
        }
        include_once "../../src/cris_modales/mdls_aho_new.php";
        ?>
        <!--Aho_0_iMprsnLbrt Impresión de Libretas-->
        <!-- <div class="text" style="text-align:center">IMPRESION DE LIBRETA</div> -->
        <input type="text" id="file" value="aperturas" style="display: none;">
        <input type="text" id="condi" value="iMprsnLbrt" style="display: none;">
        <div class="card">
            <!-- <div class="card-header">Impresion de Libreta</div> -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">
                            <i class="fas fa-list"></i> Impresion de Libreta
                        </h4>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!--Aho_0_iMprsnLbrt Libreta-->
                <div class="row mb-3">
                    <div class="col-md-5">
                        <div>
                            <span class="input-group-addon col">Cuenta de Ahorros</span>
                            <input type="text" class="form-control " id="ccodaho" required placeholder="000-000-00-000000"
                                value="<?php if ($bandera == "")
                                    echo $id; ?>">
                        </div>
                    </div>

                    <div class="col-md-5">
                        <br>
                        <button class="btn btn-primary" type="button" id="button-addon1" title="Aplicar cuenta ingresada"
                            onclick="aplicarcod('ccodaho')">
                            <i class="fa fa-check-to-slot"></i>
                        </button>
                        <button class="btn btn-primary" type="button" id="button-addon1" title="Buscar cuenta"
                            data-bs-toggle="modal" data-bs-target="#findahomcta2">
                            <i class="fa fa-magnifying-glass"></i> Busqueda de Cliente
                        </button>
                    </div>
                </div>
                <?php if ($bandera != "" && $id != "0") {
                    echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                }
                ?>
                <!--Aho_0_iMprsnLbrt Libreta-->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div>
                            <span class="input-group-addon col">Libreta</span>
                            <input type="text" class="form-control " id="libreta" readonly
                                value="<?php if ($bandera == "")
                                    echo $nlibreta; ?>">
                        </div>
                    </div>
                </div>
                <!--Aho_0_iMprsnLbrt Libreta-->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div>
                            <span class="input-group-addon col">NIT</span>
                            <input type="text" class="form-control " id="nit" readonly value="<?php if ($bandera == "")
                                echo $nit; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div>
                            <span class="input-group-addon col">Nombre</span>
                            <input type="text" class="form-control " id="name" readonly
                                value="<?php if ($bandera == "")
                                    echo ($nombre); ?>">
                        </div>
                    </div>
                </div>
                <!--Aho_0_iMprsnLbrt Borones, Imprimir, Cancelar, Salir-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="">
                        <button type="button" id="btnSave" class="btn btn-outline-success"
                            onclick="reportes([[], [], [], ['<?= $id; ?>']], 'pdf', '1',0,1)">
                            <i class="fa-solid fa-print"></i> Imprimir
                        </button>
                        <!-- <button type="button" class="btn btn-outline-danger" onclick="window.print();">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning ">
                            <i class="fa-solid fa-right-from-bracket"></i> Salir -->
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        break;
    //Cambio Libre
    case 'CambioLibre':
        $id = $_POST["xtra"];
        $ccodusu = $_SESSION['id'];
        $datos = [
            "id_tipo" => "",
        ];
        $nlibreta = "";
        $datoscli = mysqli_query($conexion, "SELECT * FROM `ahomcta` WHERE `ccodaho`=$id");
        $bandera = "Cuenta de ahorro no existe";
        while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
            $idcli = encode_utf8($da["ccodcli"]);
            $nit = encode_utf8($da["num_nit"]);
            $nlibreta = encode_utf8($da["nlibreta"]);
            $estado = encode_utf8($da["estado"]);
            ($estado != "A") ? $bandera = "Cuenta de ahorros Inactiva" : $bandera = "";
            $bandera = "";
        }
        if ($bandera == "") {
            $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE estado=1 AND `idcod_cliente`='$idcli'");
            //$data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente`='$idcli' OR `no_tributaria` = '$nit'");
            $dat = mysqli_fetch_array($data, MYSQLI_ASSOC);
            $nombre = encode_utf8($dat["short_name"]);

            //------traer el saldo de la cuenta
            $monto = 0;
            $saldo = 0;
            $transac = mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `ahommov` WHERE `ccodaho`=$id AND cestado!=2");
            while ($row = mysqli_fetch_array($transac, MYSQLI_ASSOC)) {
                $tiptr = encode_utf8($row["ctipope"]);
                $monto = encode_utf8($row["monto"]);
                if ($tiptr == "R") {
                    $saldo = $saldo - $monto;
                }
                if ($tiptr == "D") {
                    $saldo = $saldo + $monto;
                }
            }
            //****fin saldo */
        }
        mysqli_close($conexion);
        include_once "../../src/cris_modales/mdls_aho_new.php";
        ?>
        <!--Aho_1_CambioLibre Cambio de Libreria-->
        <!-- <div class="text" style="text-align:center">CAMBIO DE LIBRETA</div> -->
        <input type="text" id="file" value="aperturas" style="display: none;">
        <input type="text" id="condi" value="CambioLibre" style="display: none;">
        <div class="card">
            <!-- <div class="card-header">Cambio de Libreta</div> -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">
                            <i class="far fa-address-book"></i> Cambio de Libreta
                        </h4>
                    </div>
                </div>
            </div>
            <div class="card-body" id="required">
                <!--Aho_0_iMprsnLbrt Libreta-->
                <div class="row mb-3">
                    <div class="col-md-5">
                        <div>
                            <span class="input-group-addon col">Codigo de Cuenta</span>
                            <?php if ($bandera == "")
                                echo '<input type="text" disabled class="form-control " id="ccodaho" data-label="Codigo de Cuenta" required value="' . $id . '">';
                            else
                                echo '<input type="text" class="form-control" id="ccodaho" data-label="Codigo de Cuenta" required placeholder="000-000-00-000000">';
                            ?>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <br>
                        <button class="btn btn-primary" type="button" id="button-addon1" title="Aplicar cuenta ingresada"
                            onclick="aplicarcod('ccodaho')">
                            <i class="fa fa-check-to-slot"></i>
                        </button>
                        <button class="btn btn-primary" type="button" id="button-addon1" title="Buscar cuenta"
                            data-bs-toggle="modal" data-bs-target="#findahomcta2">
                            <i class="fa fa-magnifying-glass"></i> Busqueda de Cliente
                        </button>
                    </div>
                </div>
                <?php if ($bandera != "" && $id != "0") {
                    echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                }
                ?>

                <!--Aho_0_iMprsnLbrt Libreta-->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div>
                            <span class="input-group-addon col">NIT</span>
                            <input type="text" class="form-control " id="nit" readonly value="<?php if ($bandera == "")
                                echo $nit; ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div>
                            <span class="input-group-addon col">Nombre</span>
                            <input type="text" class="form-control " id="name" readonly value="<?php if ($bandera == "")
                                echo $nombre; ?>">
                        </div>
                    </div>
                </div>
                <!--Aho_0_iMprsnLbrt Libreta-->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div>
                            <span class="input-group-addon col">Libreta Actual</span>
                            <input type="text" class="form-control " id="libreta" readonly
                                value="<?php if ($bandera == "")
                                    echo $nlibreta; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div>
                            <span class="input-group-addon col">Saldo Disponible</span>
                            <input type="text" class="form-control " id="salDisp" readonly
                                value="<?php if ($bandera == "")
                                    echo 'Q ' . number_format($saldo, 2, '.', ','); ?>">
                        </div>
                    </div>
                </div>

                <!--Aho_1_CambioLibre LibretaActual-Nueva Libreta-->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div>
                            <span class="input-group-addon col">Nueva Libreta<span class="text-danger"
                                    id="required-asterisk">*</span></span>
                            <input type="text" class="form-control" id="newLibret" data-label=" de libreta nueva" required
                                placeholder="0" min="1" oninput="checkRequired(this)">
                        </div>
                    </div>
                </div>
                <script>
                    // function checkRequired(input) {
                    //     const asterisk = document.getElementById('required-asterisk');
                    //     if (input.value.trim() !== '') {
                    //         asterisk.style.display = 'none';
                    //     } else {
                    //         asterisk.style.display = 'inline';
                    //     }
                    // }
                </script>
                <div style="display: none;">
                    <select class="form-control col-md-12" aria-label="Default select example" id="nothing">
                        <option value="0" selected>Abrir</option>
                    </select>

                    <input class="form-check-input" type="radio" name="nada" id=" " value="nada" checked>
                    <label class="form-check-label col">Todo</label>
                </div>


                <!--Aho_1_CambioLibre Botones, Guardar Cancelar-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnSave" class="btn btn-outline-success"
                            onclick="obtiene(['ccodaho','newLibret'],['nothing'], ['nada'], 'modlib', '0', ['<?php echo $id; ?>','<?php echo $nlibreta; ?>','<?php echo $ccodusu; ?>'])">
                            <i class="fa-solid fa-floppy-disk"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <!-- <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button> -->
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function () {

                inicializarValidacionAutomaticaGeneric('#required');

            });
        </script>
        <?php
        break;
    //

} //FINAL DEL SWITCH
?>