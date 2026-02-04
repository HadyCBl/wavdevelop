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
require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
//+++++
// session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
// include '../../src/funcphp/func_gen.php';
// date_default_timezone_set('America/Guatemala');
$idusuario = $_SESSION['id'];
$id_agencia = $_SESSION['id_agencia'];

$condi = $_POST["condi"];
switch ($condi) {
    case 'libro_bancos':
        $showmensaje = false;
        try {

            $database->openConnection();

            $agencias = $database->selectColumns(
                'tb_agencia',
                ['id_agencia', 'cod_agenc', 'nom_agencia']
            );

            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No hay agencias registradas, por favor registre al menos una agencia.");
            }

            $cuentasBancosExistentes = $database->getAllResults("SELECT cb.id, tbn.nombre, ctn.ccodcta, ctn.cdescrip, cb.numcuenta
                                        FROM ctb_bancos cb
                                        INNER JOIN ctb_nomenclatura ctn ON cb.id_nomenclatura=ctn.id
                                        INNER JOIN tb_bancos tbn ON cb.id_banco=tbn.id
                                        WHERE cb.estado='1'");

            if (empty($cuentasBancosExistentes)) {
                $showmensaje = true;
                throw new Exception("No hay cuentas bancarias registradas, por favor registre al menos una cuenta.");
            }

            $fuentesFondos = $database->selectColumns(
                'ctb_fuente_fondos',
                ['id', 'descripcion'],
                'estado=1'
            );

            if (empty($fuentesFondos)) {
                $showmensaje = true;
                throw new Exception("No hay fuentes de fondos registradas, por favor registre al menos una fuente.");
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
        <input type="text" id="file" value="ban002" style="display: none;">
        <input type="text" id="condi" value="libro_bancos" style="display: none;">
        <div class="text" style="text-align:center">GENERACION DE LIBRO BANCOS</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="container contenedort">
                    <div class="row g-3 mt-3 mb-2">
                        <div class="col-12 col-lg-4">
                            <div class="card h-100">
                                <div class="card-header text-center fw-bold">Cuenta de banco</div>
                                <div class="card-body">
                                    <select class="form-select select2" name="idcuenta[]" id="idcuenta" multiple data-control="select2" data-placeholder="Todas las cuentas">
                                        <?php
                                        foreach (($cuentasBancosExistentes ?? []) as $currentBank):
                                            echo '<option value="' . $currentBank['id'] . '">' . htmlspecialchars($currentBank['nombre']) . ' - ' . htmlspecialchars($currentBank['numcuenta']) . '</option>';
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card h-100">
                                <div class="card-header text-center fw-bold">Agencia</div>
                                <div class="card-body">
                                    <select class="form-select select2" name="id_agencia[]" id="id_agencia" multiple data-control="select2" data-placeholder="Todas las agencias">
                                        <?php
                                        foreach (($agencias ?? []) as $currentAgency):
                                            echo '<option value="' . $currentAgency['id_agencia'] . '">' . htmlspecialchars($currentAgency['cod_agenc']) . ' - ' . htmlspecialchars($currentAgency['nom_agencia']) . '</option>';
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card h-100">
                                <div class="card-header text-center fw-bold">Fuente de fondos</div>
                                <div class="card-body">
                                    <select class="form-select select2" name="id_fuente_fondos[]" id="id_fuente_fondos" multiple data-control="select2" data-placeholder="Todos los fondos" style="max-width: 100%;">
                                        <?php
                                        foreach (($fuentesFondos ?? []) as $currentFuente):
                                            echo '<option value="' . $currentFuente['id'] . '">' . htmlspecialchars($currentFuente['descripcion']) . '</option>';
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row row-cols-1 row-cols-md-2 g-4 mb-2">
                        <div class="col">
                            <label for="finicio" class="form-label fw-bold">Desde</label>
                            <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                        </div>
                        <div class="col">
                            <label for="ffin" class="form-label fw-bold">Hasta</label>
                            <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Libro Bancos en pdf" onclick="reportes([[`finicio`,`ffin`],[],[],[$('#idcuenta').val(),$('#id_agencia').val(),$('#id_fuente_fondos').val()]],`pdf`,`libro_bancos`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Libro Bancos en Excel" onclick="reportes([[`finicio`,`ffin`],[],[],[$('#idcuenta').val(),$('#id_agencia').val(),$('#id_fuente_fondos').val()]],`xlsx`,`libro_bancos`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
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
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    language: 'es',
                    closeOnSelect: false
                });
                // inicializarValidacionAutomaticaGeneric('#formPartidaDiario');

            });
        </script>
        <?php
        break;
    case 'create_and_edit_bancos': {
            $xtra = $_POST["xtra"];
        ?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <input type="text" id="file" value="ban002" style="display: none;">
            <input type="text" id="condi" value="create_and_edit_bancos" style="display: none;">
            <div class="text" style="text-align:center">CREACIÓN Y EDICIÓN DE BANCOS</div>
            <div class="card">
                <div class="card-header">Creación y edición de bancos</div>
                <div class="card-body" style="padding-bottom: 0px !important;">
                    <!-- INFORMACION DE BANCO -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Información de banco</b></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="idbanco" placeholder="Nombre del banco" readonly hidden>
                                    <input type="text" class="form-control" id="nombanco" placeholder="Nombre del banco">
                                    <label for="nombanco">Nombre del banco</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="abreviatura" placeholder="Abreviatura">
                                    <label for="abreviatura">Abreviatura</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA PARA LOS DISTINTOS TIPOS DE INGRESOS -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Listado de bancos</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="table-responsive">
                                    <table class="table nowrap table-hover table-border" id="tb_bancos_new" style="width: 100% !important;">
                                        <thead class="text-light table-head-aprt">
                                            <tr style="font-size: 0.9rem;">
                                                <th>#</th>
                                                <th>Nombre banco</th>
                                                <th>Abreviatura</th>
                                                <th>Accciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-group-divider" style="font-size: 0.9rem !important;">
                                            <?php
                                            $consulta = mysqli_query($conexion, "SELECT * FROM tb_bancos tb WHERE tb.estado = '1'");
                                            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) { ?>
                                                <tr>
                                                    <td><?= ($fila["id"]) ?></td>
                                                    <td><?= ($fila["nombre"]) ?></td>
                                                    <td><?= ($fila["abreviatura"]) ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-success btn-sm" onclick="printdiv5('idbanco,nombanco,abreviatura/A,A,A/-/#/#/#/#', ['<?= $fila['id'] ?>','<?= $fila['nombre'] ?>','<?= $fila['abreviatura'] ?>']); HabDes_boton(1);"><i class="fa-solid fa-eye"></i></button>
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="eliminar('<?= $fila['id'] ?>', 'crud_bancos', '0', 'delete_banco')"><i class="fa-solid fa-trash"></i></button>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- NAVBAR PARA LOS DISTINTOS TIPOS DE INGRESOS -->
                </div>
                <div class="container" style="max-width: 100% !important;">
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                            <!-- boton para solicitar credito -->
                            <button id="btGuardar" class="btn btn-outline-success mt-2" onclick="obtiene([`nombanco`,`abreviatura`],[],[],`create_banco`,`0`,['<?= $idusuario; ?>'])"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar</button>
                            <button id="btEditar" class="btn btn-outline-primary mt-2" onclick="obtiene([`nombanco`,`abreviatura`,`idbanco`],[],[],`update_banco`,`0`,['<?= $idusuario; ?>'])"><i class="fa-solid fa-floppy-disk me-2"></i>Actualizar</button>
                            <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2('#cuadro','0')"><i class="fa-solid fa-ban"></i> Cancelar</button>
                            <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()"><i class="fa-solid fa-circle-xmark"></i> Salir</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                //SELECCIONAR LOS CHECKBOXS DESPUES DE CARGAR EL DOM
                $(document).ready(function() {
                    convertir_tabla_a_datatable('tb_bancos_new');
                    HabDes_boton(0);
                });
            </script>
        <?php
        }
        break;
    case 'resumen_saldos':
        $xtra = $_POST["xtra"];
        // $idcuenta = $_POST["idcuenta"];
        // $cuenta = $_POST["cuenta"];
        // $fondoid = $_POST["fondoid"];
        // $codofi = $_POST["codofi"];
        // $idbanco = $_POST["idbanco"];
        // $finicio = $_POST["finicio"];
        // $ffin = $_POST["ffin"];
        // $idcuenta = ($idcuenta == "") ? 0 : $idcuenta;
        // $cuenta = ($cuenta == "") ? 0 : $cuenta;
        ?>
        <input type="text" id="file" value="ban002" style="display: none;">
        <input type="text" id="condi" value="resumen_saldos" style="display: none;">
        <div class="text" style="text-align:center">RESUMEN DE LIBRO BANCOS A FECHA DE CORTE</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficinas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi" value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi" value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                            <?php
                                            $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                echo '<option value="' . $ofi['id_agencia'] . '">' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf" checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf" onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <?php
                                                $fons = mysqli_query($conexion, "SELECT * FROM `ctb_fuente_fondos` where estado=1");
                                                while ($fon = mysqli_fetch_array($fons)) {
                                                    echo '<option value="' . $fon['id'] . '">' . $fon['descripcion'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <label class="text-primary" for="fondoid">Fondos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Cuentas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="allcuen" value="allcuen" checked onclick="changedisabled(`#btncuenid`,0)">
                                            <label for="allcuen" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="anycuen" value="anycuen" onclick="changedisabled(`#btncuenid`,1)">
                                            <label for="anycuen" class="form-check-label"> Una cuenta</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <div class="input-group" style="width:min(70%,32rem);">
                                                <input style="display:none;" type="text" class="form-control" id="idcuenta" value="0">
                                                <input type="text" disabled readonly class="form-control" id="cuenta">
                                                <button disabled id="btncuenid" class="btn btn-outline-success" type="button" onclick="abrir_modal(`#modal_cuentas_bancos`, `#id_modal_bancos`, 'idcuenta,cuenta/A,A/'+'/#/#/#/#')" title="Buscar Cuenta contable"><i class="fa fa-magnifying-glass"></i></button>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">FECHA DE CORTE</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Libro Bancos en pdf" onclick="reportes([[`finicio`,`ffin`,`idcuenta`,`cuenta`],[`codofi`,`fondoid`],[`rcuentas`,`rfondos`,`ragencia`],[]],`pdf`,`Res_saldoCorte`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Libro Bancos en Excel" onclick="reportes([[`finicio`,`ffin`,`idcuenta`,`cuenta`],[`codofi`,`fondoid`],[`rcuentas`,`rfondos`,`ragencia`],[]],`xlsx`,`Res_saldoCorte`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelars
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
<?php
        include '../../src/cris_modales/mdls_cuentas_bancos.php';

        break;
}
?>