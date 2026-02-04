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
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\PermissionManager;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
$puestouser = $_SESSION['puesto'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

$condi = $_POST["condi"];

switch ($condi) {
    //Curd otros ingresos 01
    case 'create_tipo_ingresos':
        $codusu = $_SESSION["id"];
?>
        <!-- Crud para agregar, editar y eliminar tipo de gastos  -->
        <input type="text" id="file" value="creditos_01" style="display: none;">
        <input type="text" id="condi" value="gastos" style="display: none;">

        <div class="text" style="text-align:center">Otros gastos</div>

        <div class="card">
            <div class="card-header">Información</div>
            <div class="card-body">
                <form id="miForm">

                    <div class="col">
                        <!-- ID de nomenclaturas -->
                        <input id="idReg" placeholder="idRegistro" disabled hidden><!-- ID de registro  -->
                        <input id="idNom" placeholder="idNomenclatura" disabled hidden> <!-- ID de nomenclatura -->
                    </div>

                    <div class="row g-3">

                        <div class="col-lg-6 col-md-12">
                            <label for="Nombre del Gasto" class="form-label ">Nombre</label>
                            <input type="text" class="form-control input-validation" id="gasto" placeholder="Nombre del gasto" required>

                        </div>

                        <div class="col-lg-6 col-md-12">
                            <label for="Nomenclatura" class="form-label">Nomenclatura</label>
                            <div class="input-group mb-3">
                                <button class="btn btn-warning" type="button" id="buscarNomenclatura" data-bs-toggle="modal" data-bs-target="#otrosGastos">Buscar</button>
                                <input type="text" disabled class="form-control input-validation" id="nomenclatura" placeholder="Nomenclatura" aria-label="Example text with button addon" aria-describedby="button-addon1">
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-12 mb-3">
                            <label class="form-label">Tipo (Ingreso/Egreso)</label>
                            <select class="form-select" aria-label="Default select example" id="idSelect" required>
                                <option value="1">Ingreso</option>
                                <option value="2">Egreso</option>
                            </select>
                        </div>
                        <div class="col-lg-6 col-md-12 mb-3">
                            <label for="grupo" class="form-label">Grupo (opcional)</label>
                            <input type="text" class="form-control" id="grupo" placeholder="Grupo del gasto (opcional)">
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label">Tipo(Bien/Servicio)</label>
                            <select class="form-select" aria-label="Default select example" id="idSelect2">
                                <option value="B">Bien</option>
                                <option value="S">Servicio</option>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="row mt-2">
                    <div class="col">
                        <div class="conBoton">
                            <button type="button" id="btnGua" class="btn btn-success">Guardar</button>
                            <button type="button" id="btnAct" class="btn btn-warning">Actualizar</button>
                            <button type="button" id="btnCan" class="btn btn-danger">Cancelar</button>
                            <script>
                                $('#btnGua').click(function() {
                                    if ((vaData(['#gasto', '#idNom'])) == false) return;

                                    obtiene(
                                        ['gasto', 'idNom', 'grupo'], // inputs
                                        ['idSelect', 'idSelect2'], // selects 
                                        [], // radios
                                        'ins_otrGasto', // action
                                        '0', // extra
                                        '<?php echo $codusu; ?>' // user code
                                    );
                                });
                                $('#btnAct').click(function() {
                                    if ((vaData(['#gasto', '#idNom', '#grupo'])) == false) return;

                                    // Asegúrate de enviar todos los campos necesarios en el orden correcto
                                    obtiene(
                                        ['idReg', 'idNom', 'gasto', 'grupo'], // inputs - corresponde a [id, id_nomenclatura, nombre_gasto, grupo]
                                        ['idSelect', 'idSelect2'], // selects - corresponde a [tipo, tipoLinea]
                                        [], // radios - no usados
                                        'act_otrGasto', // acción
                                        '0', // extra
                                        '<?php echo $codusu; ?>' // código de usuario
                                    );
                                });
                                $('#btnCan').click(function() {
                                    $("#miForm")[0].reset();
                                    verEle(['#btnAct', '#btnCan'])
                                    verEle(['#btnGua'], 1)
                                })
                            </script>
                        </div>
                    </div>
                </div>

                <div class="container mt-3">
                    <h2>Registro de gastos </h2>
                    <!-- En el siguiente div se imprime la tabla XD con inyecCod cod Calicho XV -->
                    <div id="tbOtrosG"></div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-primary mt-2" onclick="reportes([[],[],[],['38']], `pdf`, `recibo`,0)">Imprimir</button>

        </div>
        <script>
            $(document).ready(function() {
                inyecCod('#tbOtrosG', 'rep_otroGas');
                verEle(['#btnAct', '#btnCan']);
            });
        </script>
    <?php
        include_once '../../src/cris_modales/mdls_otrosGastos.php'; //LLamar al modal
        break;

    case 'fac_otrIngresos':
        $flag_correlativo = 1;
        $correlativo = "";

        $status = false;
        try {
            $database->openConnection();
            $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'puesto'], 'estado=1');
            if (empty($users)) {
                throw new SoftException("No hay usuarios");
            }
            $agencies = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia']);
            if ($flag_correlativo == 1) {
                $sql = "SELECT MAX(CAST(recibo AS SIGNED)) AS corr FROM otr_pago op 
                            INNER JOIN tb_agencia ta  ON ta.id_agencia = op.agencia 
                            INNER JOIN otr_pago_mov opm ON op.id = opm.id_otr_pago 
                            INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id 
                            WHERE op.estado = 1 AND oti.tipo = 1 AND op.agencia = ?";
                $result = $database->getAllResults($sql, [$idagencia]);
                $correlativo = $result[0]["corr"];
            }

            $bancos = $database->getAllResults("SELECT ctb_bancos.id, tb_bancos.abreviatura, ctb_bancos.numcuenta FROM tb_bancos
                            INNER JOIN ctb_bancos ON tb_bancos.id = ctb_bancos.id_banco
                            WHERE tb_bancos.estado = 1 AND ctb_bancos.estado = 1
                            ORDER BY tb_bancos.id ASC;");

            $status = true;
        } catch (SoftException $e) {
            // $database->rollback();
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            // $database->rollback();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        $disabled = (in_array($puestouser, ["ADM", "GER", "CNT"])) ? "" : "disabled";
    ?>
        <input type="text" id="file" value="otros_ingresos_01" style="display: none;">
        <input type="text" id="condi" value="fac_otrIngresos" style="display: none;">
        <div class="container-fluid px-3">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Recibo de Ingresos</h5>
                </div>

                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show m-3 mb-0" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
                            <div>
                                <strong>Advertencia:</strong> <?= $mensaje ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php }  ?>

                <div class="card-body">
                    <form id="miForm">
                        <div class="row g-3">
                            <!-- Columna Izquierda: Formulario de Datos -->
                            <div class="col-lg-7">
                                <div class="card h-100 border-1 shadow-sm">
                                    <div class="card-header bg-success">
                                        <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Datos del Recibo</h6>
                                    </div>
                                    <div class="card-body" id="formSectionDescription">
                                        <div class="mb-4" x-data="{ isBankSelected: false }">
                                            <h6 class="border-bottom pb-2 mb-3 text-primary">
                                                <i class="fas fa-credit-card me-2"></i>Forma de Pago
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Forma</label>
                                                    <select class="form-select" data-label="Forma pago" id="tipdoc" x-on:change="isBankSelected = ($el.value === 'banco')">
                                                        <option value="efectivo" selected>EFECTIVO</option>
                                                        <option value="banco">BANCO</option>
                                                        <?php if (isset($documentosTransacciones) && !empty($documentosTransacciones)): ?>
                                                            <optgroup label="Otros">
                                                                <?php foreach ($documentosTransacciones as $doc): ?>
                                                                    <option value="<?= $doc['id']; ?>">
                                                                        <?= htmlspecialchars($doc['nombre']); ?></option>
                                                                <?php endforeach; ?>
                                                            </optgroup>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6" id="contbancos" x-show="isBankSelected" x-cloak>
                                                    <label class="form-label fw-semibold">Cuenta Bancaria</label>
                                                    <select class="form-select" id="listcuent" data-label="Cuenta bancaria" :required="isBankSelected">
                                                        <?php
                                                        echo '<option selected disabled value="">Seleccione una cuenta</option>';
                                                        foreach (($bancos ?? []) as $banco) {
                                                            echo '<option value="' . $banco['id'] . '">' . $banco['abreviatura'] . ' - ' . $banco['numcuenta'] . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6" x-show="isBankSelected" x-cloak>
                                                    <label class="form-label fw-semibold">Fecha Banco</label>
                                                    <input type="date" class="form-control" id="banco_fecha" value="<?= date('Y-m-d'); ?>" :required="isBankSelected" data-label="Fecha Banco">
                                                </div>
                                                <div class="col-md-6" x-show="isBankSelected" x-cloak>
                                                    <label class="form-label fw-semibold">Número de Referencia</label>
                                                    <input type="text" class="form-control" id="banco_num_referencia"
                                                        placeholder="Número de referencia" inputmode="text"
                                                        data-label="Número de referencia"
                                                        pattern="[A-Za-z0-9]*" :required="isBankSelected">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <h6 class="border-bottom pb-2 mb-3 text-primary">
                                                <i class="fas fa-info-circle me-2"></i>Información General
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Fecha</label>
                                                    <input required type="date" class="form-control" id="fecha" value="<?= date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Número de Recibo</label>
                                                    <input required type="text" class="form-control" id="recibo" value="<?= ($flag_correlativo == 1) ? ((int)($correlativo ?? 0) + 1) : '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <h6 class="border-bottom pb-2 mb-3 text-primary">
                                                <i class="fas fa-plus-circle me-2"></i>Información Adicional
                                            </h6>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label fw-semibold mb-3">Tipo de Información Adicional</label>
                                                    <div class="row g-2" id="grpradio3">
                                                        <div class="col-md-6 col-lg-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="opcalculo" id="radio1" value="1" checked onclick="opciones(1)">
                                                                <label class="form-check-label" for="radio1">Ninguno</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 col-lg-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="opcalculo" id="radio2" value="2" onclick="opciones(2)">
                                                                <label class="form-check-label" for="radio2">Cliente</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 col-lg-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="opcalculo" id="radio3" value="3" onclick="opciones(3)">
                                                                <label class="form-check-label" for="radio3">Usuarios</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 col-lg-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="opcalculo" id="radio4" value="4" onclick="opciones(4)">
                                                                <label class="form-check-label" for="radio4">Agencias</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12" id="divclientes" style="display: none;">
                                                    <label class="form-label fw-semibold">Cliente</label>
                                                    <div class="input-group">
                                                        <button class="btn btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#otr_cli">
                                                            <i class="fas fa-search me-1"></i>Buscar
                                                        </button>
                                                        <input type="text" id="cliente" class="form-control" placeholder="Seleccione un cliente">
                                                    </div>
                                                </div>

                                                <div class="col-12" id="divusuarios" style="display: none;">
                                                    <label class="form-label fw-semibold">Usuario</label>
                                                    <select <?= $disabled; ?> class="form-select" id="idusuario">
                                                        <?php
                                                        echo '<option selected disabled value="0">Seleccione un usuario</option>';
                                                        if (isset($users)) {
                                                            foreach ($users as $user) {
                                                                $selected = ($idusuario == $user["id_usu"]) ? "selected" : "";
                                                                echo '<option ' . $selected . ' value="' . $user['id_usu'] . '">' . $user['nombre'] . ' ' . $user['apellido'] . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>

                                                <div class="col-12" id="divagencias" style="display: none;">
                                                    <label class="form-label fw-semibold">Agencia</label>
                                                    <select <?= $disabled; ?> class="form-select" id="idagencia">
                                                        <?php
                                                        echo '<option selected disabled value="0">Seleccione una agencia</option>';
                                                        if (isset($agencies)) {
                                                            foreach ($agencies as $agency) {
                                                                $selected = ($idagencia == $agency["id_agencia"]) ? "selected" : "";
                                                                echo '<option ' . $selected . ' value="' . $agency['id_agencia'] . '">' . $agency['nom_agencia'] . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sección: Descripción -->
                                        <div class="mb-4">
                                            <h6 class="border-bottom pb-2 mb-3 text-primary">
                                                <i class="fas fa-align-left me-2"></i>Descripción
                                            </h6>
                                            <div class="form-floating">
                                                <textarea required class="form-control" data-label="descripcion" placeholder="Ingrese una descripción" id="descrip" style="height: 100px"></textarea>
                                                <label for="descrip">Descripción del recibo</label>
                                            </div>
                                        </div>

                                        <!-- Sección: Agregar Tipo de Ingreso -->
                                        <div class="alert alert-success border-success mb-0" role="alert">
                                            <h6 class="alert-heading mb-3">
                                                <i class="fas fa-plus-square me-2"></i>Agregar Tipo de Ingreso
                                            </h6>
                                            <form id="myForm2">
                                                <div class="row g-3">
                                                    <div class="col-md-8">
                                                        <label class="form-label fw-semibold">Tipo de Ingreso</label>
                                                        <div class="input-group">
                                                            <button class="btn btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#otr_Ingresos">
                                                                <i class="fas fa-search me-1"></i>Buscar
                                                            </button>
                                                            <input type="text" id="otr_gasto" readonly class="form-control" placeholder="Seleccione tipo de ingreso">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">Monto</label>
                                                        <input type="number" class="form-control" id="monto" placeholder="0.00" min="0" step="0.01">
                                                    </div>
                                                    <div class="col-12">
                                                        <button type="button" id="btnAgr" class="btn btn-primary">
                                                            <i class="fas fa-plus me-1"></i>Agregar a la Lista
                                                        </button>
                                                    </div>
                                                </div>
                                                <input type="text" placeholder="idTipoG" id="idTG" disabled hidden>
                                            </form>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- Columna Derecha: Tabla de Resumen -->
                            <div class="col-lg-5">
                                <div class="card h-100 border-1 shadow-sm">
                                    <div class="card-header bg-success">
                                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Resumen de Ingresos</h6>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <div class="table-responsive flex-grow-1">
                                            <table class="table table-hover table-striped table-sm tbRecibo-Reset mb-0" id="tbRecibo">
                                                <thead class="table-info">
                                                    <tr>
                                                        <th width="50">Acción</th>
                                                        <th>Tipo de Ingreso</th>
                                                        <th width="100" class="text-end">Monto</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0 text-primary">Total:</h5>
                                                <h4 class="mb-0 fw-bold text-success" id="total1">Q 0.00</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex gap-2 justify-content-end">
                                    <?php if ($status) { ?>
                                        <button type="button" id="btnGua" class="btn btn-success btn-lg px-4">
                                            <i class="fas fa-save me-2"></i>Guardar Recibo
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modalTinpoIngreso"></div>

        <script>
            dataTB('#tbRecibo');

            $('#btnGua').click(function() {
                var tabla = document.getElementById('tbRecibo');
                var noFila = tabla.querySelectorAll('tbody tr').length;
                if (noFila <= 0) {
                    Swal.fire({
                        icon: "info",
                        title: "Alerta",
                        text: "Tiene que agregar ingresos para guardar la operación."
                    });
                    return;
                }
                vecMaster = [];
                vecMaster.push(capDataTb('idG', 'td'));
                vecMaster.push(capDataTb('monto', 'td'));
                var matriz = gMatriz(vecMaster);
                obtiene(['fecha', 'recibo', 'cliente', 'descrip', 'banco_fecha', 'banco_num_referencia'], ['idusuario', 'idagencia', 'listcuent', 'tipdoc'], ['opcalculo'], 'cre_otrIngreso', '0', [matriz], function(data2) {
                    printdiv2('#cuadro', 0);
                    reportes([
                        [],
                        [],
                        [],
                        [data2[2]]
                    ], 'pdf', '21', 0, 1);
                }, '¿Está seguro de guardar el registro?')
            })
        </script>

        <div id="modalTinpoIngreso"></div>

        <script>
            $('#btnAgr').click(function() {
                //BOTON PARA AGREGAR UN DETALLE
                var idG = $('#idTG').val();
                var otr_gasto = $('#otr_gasto').val();
                var monto = $('#monto').val();

                if (otr_gasto === '' || monto === '') {
                    Swal.fire({
                        icon: "info",
                        title: "Alerta",
                        text: "Todos los campos son obligatorios, favor de ingresar la información."
                    });
                    return;
                }

                //Para agregar una fila
                var tabla = document.getElementById('tbRecibo');
                var filas = tabla.getElementsByTagName('tr');
                var noFila = filas.length;

                var tr = $(`<tr id="${'no'+noFila}">`)
                tr.append(`<td onclick="eliF('${"#no"+noFila}')"><button type="button" class="btn btn-sm btn-danger"><i class="fa-solid fa-minus"></i></button></td>`) //Accion
                tr.append(`<td name="idG[]" hidden>${idG}</td>`) //idOculto
                tr.append(`<td name="otr_gasto[]">${otr_gasto}</td>`) //Tipo de gasto 
                tr.append(`<td name="monto[]" class="text-end">${parseFloat(monto).toFixed(2)}</td>`) //Monto
                $('#tbRecibo tbody').append(tr)
                sum();
                //limpiar los campos
                $('#idTG').val("");
                $('#otr_gasto').val("");
                $('#monto').val("");
            })

            function eliF(fila) {
                $('#tbRecibo ' + fila).remove();
                sum();
            }

            function opciones(op) {
                showhide(['divclientes', 'divusuarios', 'divagencias'], [0, 0, 0]);
                if (op == 2) {
                    showhide(['divclientes', 'divusuarios', 'divagencias'], [1, 0, 0]);
                }
                if (op == 3) {
                    showhide(['divclientes', 'divusuarios', 'divagencias'], [0, 1, 0]);
                }
                if (op == 4) {
                    showhide(['divclientes', 'divusuarios', 'divagencias'], [0, 0, 1]);
                }
            }

            function sum() {
                // Inicializar una variable para almacenar la suma total
                var sumaTotal = 0;
                // Recorrer los elementos y sumar sus valores
                $('td[name="monto[]"]').each(function() {
                    var montoTexto = $(this).text().replace('Q', '').trim();
                    sumaTotal += parseFloat(montoTexto);
                });
                $('#total1').text("Q " + sumaTotal.toFixed(2));
            }
            $(document).ready(function() {
                inyecCod('#modalTinpoIngreso', 'tipo_ingreso', 1);
                inicializarValidacionAutomaticaGeneric('#formSectionDescription');
            })
        </script>
    <?php
        include_once '../../src/cris_modales/mdls_otr_recibo.php'; //LLamar al modal
        break;

    case 'otr_tipoEgreso':
        $codusu = $_SESSION["id"];
        $idagencia = $_SESSION["id_agencia"];
        //Flag para activar el correlativo automaticamente (1 activo, 0 desactivado)
        $flag_correlativo = 1;

        $showmensaje = false;
        try {
            $database->openConnection();
            $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'puesto'], 'estado=1');
            if (empty($users)) {
                $showmensaje = true;
                throw new Exception("No hay usuarios");
            }
            $agencies = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia']);
            if ($flag_correlativo == 1) {
                $sql = "SELECT MAX(CAST(recibo AS SIGNED)) corr FROM otr_pago op 
                INNER JOIN tb_agencia ta  ON ta.id_agencia = op.agencia 
                INNER JOIN otr_pago_mov opm ON op.id = opm.id_otr_pago 
                INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id 
                WHERE op.estado = 1 AND oti.tipo = 2 AND op.agencia = ?";
                $result = $database->getAllResults($sql, [$idagencia]);
                $correlativo = $result[0]["corr"];
            }

            $bancos = $database->getAllResults("SELECT ctb_bancos.id, tb_bancos.abreviatura, ctb_bancos.numcuenta FROM tb_bancos
                            INNER JOIN ctb_bancos ON tb_bancos.id = ctb_bancos.id_banco
                            WHERE tb_bancos.estado = 1 AND ctb_bancos.estado = 1
                            ORDER BY tb_bancos.id ASC;");

            // $userPermissions = new PermissionManager($idusuario);

            // if ($userPermissions->isLevelOne(PermissionManager::USAR_OTROS_DOCS_AHORROS)) {
            $documentosTransacciones = $database->selectColumns(
                "tb_documentos_transacciones",
                ["id", "nombre", "tipo_dato"],
                "estado=1 AND id_modulo=4 AND tipo=1"
            );
            // }

            // SELECT id, nombre_gasto AS nomGasto, grupo FROM otr_tipo_ingreso WHERE estado = 1 AND tipo = 2
            $tiposEgresos = $database->selectColumns(
                'otr_tipo_ingreso',
                ['id', 'nombre_gasto AS nomGasto', 'grupo'],
                'estado = 1 AND tipo = 2'
            );

            // Consultar tipos de afiliación IVA para el modal de nuevo proveedor
            $tiposAfiliacionIva = $database->selectAll('cv_tipo_afiliacion_iva');

            $status = true;
        } catch (Exception $e) {
            $status = false;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            // echo $mensaje;
        } finally {
            $database->closeConnection();
        }
        $disabled = (in_array($puestouser, ["ADM", "GER", "CNT", "CJG"])) ? "" : "disabled";
    ?>
        <input type="text" id="file" value="otros_ingresos_01" style="display: none;">
        <input type="text" id="condi" value="otr_tipoEgreso" style="display: none;">

        <style>
            .accordion-item {
                border-radius: 8px !important;
                overflow: hidden;
                margin-bottom: 10px;
            }

            .accordion-button:not(.collapsed) {
                box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .125);
            }

            .accordion-body {
                padding: 1.25rem;
            }
        </style>

        <div class="card">
            <h5 class="card-header d-flex justify-content-between align-items-center">
                <span>Recibo de egresos</span>
                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalAyudaFEL">
                    <i class="fas fa-question-circle me-1"></i>Ayuda
                </button>
            </h5>
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center m-3" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div>
                        <?= $mensaje ?>
                    </div>
                </div>
            <?php }  ?>
            <div class="card-body">
                <form id="miForm">
                    <div class="row">
                        <div class="col-12 mb-3" id="formSectionDescription">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Datos del Recibo de Egreso</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <div class="row" x-data="{ isBankSelected: false, isNotaDebito: true }">
                                                <div class="col-sm-12 col-md-6 col-lg-6 mb-3">
                                                    <span class="input-group-addon col-8">Origen de fondos</span>
                                                    <select class="form-select  col-sm-12" aria-label="Default select example" id="tipdoc" x-on:change="isBankSelected = ($el.value === 'banco')">
                                                        <option value="efectivo" selected>EFECTIVO</option>
                                                        <option value="banco">BANCO</option>
                                                        <?php if (isset($documentosTransacciones) && !empty($documentosTransacciones)): ?>
                                                            <optgroup label="Otros">
                                                                <?php foreach ($documentosTransacciones as $doc): ?>
                                                                    <option value="<?= $doc['id']; ?>">
                                                                        <?= htmlspecialchars($doc['nombre']); ?></option>
                                                                <?php endforeach; ?>
                                                            </optgroup>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3" x-show="isBankSelected" x-cloak>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio"
                                                            name="tipoMovBanco" id="movioBanco1" checked x-on:click="isNotaDebito = true"
                                                            value="nota">
                                                        <label class="form-check-label" for="movioBanco1">Nota de débito</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="tipoMovBanco" id="movioBanco2" x-on:click="isNotaDebito = false" value="cheque">
                                                        <label class="form-check-label" for="movioBanco2">Cheque</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3" id="contbancos" x-show="isBankSelected" x-cloak>
                                                    <div class="mb-2">
                                                        <label class="form-label">Seleccione cuenta bancaria</label>
                                                        <select class="form-select" id="listcuent" data-label="Cuenta bancaria" :required="isBankSelected">
                                                            <?php
                                                            echo '<option selected disabled value="">Seleccione una cuenta</option>';
                                                            if (isset($bancos)) {
                                                                foreach ($bancos as $banco) {
                                                                    echo '<option value="' . $banco['id'] . '">' . $banco['abreviatura'] . ' - ' . $banco['numcuenta'] . '</option>';
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6" x-show="isBankSelected" x-cloak>
                                                    <label class="form-label">Fecha Banco</label>
                                                    <input type="date" class="form-control" id="banco_fecha" value="<?= date('Y-m-d'); ?>" :required="isBankSelected" data-label="Fecha Banco" x-cloak>
                                                </div>
                                                <div class="col-md-6 mb-3" x-show="isBankSelected && isNotaDebito" x-cloak>
                                                    <div class="mt-2">
                                                        <label class="form-label">Número de referencia</label>
                                                        <input type="text" class="form-control" id="banco_num_referencia"
                                                            placeholder="Número de referencia" inputmode="text"
                                                            data-label="Número de referencia"
                                                            pattern="[A-Za-z0-9]*" :required="isBankSelected && isNotaDebito">
                                                    </div>
                                                </div>
                                                <div class="col-md-12 mb-3" x-show="isBankSelected && !isNotaDebito" x-cloak>
                                                    <div class="mt-2">
                                                        <label class="form-label">Beneficiario del cheque</label>
                                                        <input type="text" class="form-control" id="banco_beneficiario_cheque"
                                                            placeholder="Beneficiario del cheque"
                                                            data-label="Beneficiario del cheque"
                                                            inputmode="text" pattern="[A-Za-z0-9 ]*" :required="isBankSelected && !isNotaDebito">
                                                        <small class="form-text text-muted">Nombre del beneficiario del cheque</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3" x-show="isBankSelected && !isNotaDebito" x-cloak>
                                                    <div class="mt-2">
                                                        <label class="form-label">Número de cheque</label>
                                                        <input type="text" class="form-control" id="banco_num_cheque"
                                                            placeholder="Número de cheque"
                                                            data-label="Número de cheque"
                                                            inputmode="text" pattern="[A-Za-z0-9]*" :required="isBankSelected && !isNotaDebito">
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3" x-show="isBankSelected && !isNotaDebito" x-cloak>
                                                    <div class="mt-3">
                                                        <label class="form-label">Negociable</label>
                                                        <select class="form-select" id="banco_negociable">
                                                            <option value="1">Negociable</option>
                                                            <option value="0" selected>No negociable</option>
                                                        </select>
                                                        <small class="form-text text-muted">Seleccione si el cheque es negociable o no negociable</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr class="my-3">

                                    <!-- Nueva sección con acordeones por factura FEL -->
                                    <div id="feldataDiv" class="row mb-3 g-3" x-data="{ 
                                        emisores: [],
                                        cargandoEmisores: false,
                                        cargarEmisores() {
                                            if (this.emisores.length > 0) return;
                                            this.cargandoEmisores = true;
                                            obtiene([],[],[],'cargar_emisores','0', [],
                                                (data) => {
                                                    if (data && data.emisores) {
                                                        this.emisores = data.emisores;
                                                        this.$nextTick(() => {
                                                            $('#fel_nit_emisor').select2({
                                                                dropdownParent: $('#felModal'),
                                                                placeholder: 'Seleccione un emisor',
                                                                allowClear: true,
                                                                theme: 'bootstrap-5',
                                                                language: {
                                                                    noResults: () => 'No se encontraron resultados',
                                                                    searching: () => 'Buscando...'
                                                                }
                                                            });
                                                        });
                                                    } else {
                                                        console.error('Error al cargar emisores');
                                                    }
                                                    this.cargandoEmisores = false;
                                                },
                                                false // messageConfirm
                                            );
                                        }
                                    }">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0"><i class="fas fa-list-alt me-2"></i>Egresos e Ingresos</h6>
                                                <button type="button" class="btn btn-success btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#felModal"
                                                    onclick="prepararNuevaFacturaFEL()">
                                                    <i class="fas fa-plus me-1"></i>Agregar Factura FEL
                                                </button>
                                            </div>

                                            <!-- Acordeones de facturas -->
                                            <div class="accordion" id="accordionFacturas">
                                                <!-- Acordeón por defecto: Sin factura FEL -->
                                                <div class="accordion-item mb-3" style="border: 3px solid #6c757d; border-radius: 10px; box-shadow: 0 4px 6px rgba(108, 117, 125, 0.2);">
                                                    <h2 class="accordion-header" id="headingSinFEL">
                                                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                                            data-bs-target="#collapseSinFEL" aria-expanded="true" aria-controls="collapseSinFEL"
                                                            style="background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%); font-weight: 600;">
                                                            <i class="fas fa-folder-open me-2 text-secondary" style="font-size: 1.2em;"></i>
                                                            <strong class="text-secondary" style="font-size: 1.05em;">Sin Factura FEL</strong>
                                                            <span class="badge bg-secondary ms-2" id="badge_count_sinfel" style="font-size: 0.9em; padding: 0.4em 0.8em;">0 ítems</span>
                                                        </button>
                                                    </h2>
                                                    <div id="collapseSinFEL" class="accordion-collapse collapse show"
                                                        aria-labelledby="headingSinFEL" data-bs-parent="#accordionFacturas">
                                                        <div class="accordion-body" style="padding: 1.5rem;">
                                                            <!-- Formulario para agregar ítems sin FEL -->
                                                            <div class="card mb-3" style="border: 3px solid #28a745; border-radius: 8px; box-shadow: 0 3px 6px rgba(40, 167, 69, 0.2);">
                                                                <div class="card-header bg-success text-white">
                                                                    <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Ítem sin Factura FEL</h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <div class="row g-3">
                                                                        <div class="col-md-8">
                                                                            <label class="form-label">Tipo de gasto</label>
                                                                            <div class="input-group">
                                                                                <button class="btn btn-warning" type="button" data-bs-toggle="modal"
                                                                                    data-bs-target="#otr_Ingresos" onclick="setActiveFactura('')">Buscar</button>
                                                                                <input type="text" id="otr_gasto_sinfel" readonly class="form-control" placeholder="Seleccione tipo de gasto">
                                                                                <input type="hidden" id="idTG_sinfel">
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-4">
                                                                            <label class="form-label">Monto</label>
                                                                            <input type="number" class="form-control" id="monto_sinfel" placeholder="0.00" min="0" step="0.01">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label class="form-label">Tipo de impuesto</label>
                                                                            <select class="form-select" id="tipimpuesto_sinfel" onchange="impuesto(this.value, 'sinfel')">
                                                                                <option value="0" selected>No aplica</option>
                                                                                <option value="1">IVA 12%</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-4" id="imphidrocarburos_sinfel" hidden>
                                                                            <label class="form-label">Impuesto Hidrocarburos</label>
                                                                            <select class="form-select" id="imselecHidro_sinfel" onchange="impuestohidro(this.value, 'sinfel')">
                                                                                <option value="0" selected>No aplica</option>
                                                                                <option value="1">Gasolina superior (Q.4.70)</option>
                                                                                <option value="2">Gasolina Regular (Q4.60)</option>
                                                                                <option value="3">Gasolina de Aviación (Q4.70)</option>
                                                                                <option value="4">Diesel y gas oil (Q1.30)</option>
                                                                                <option value="5">Kerosina (Q0.50)</option>
                                                                                <option value="6">Kerosina para motores de reacción (Q0.50)</option>
                                                                                <option value="7">Nafta (Q0.50)</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-2" id="congalon_sinfel" hidden>
                                                                            <label class="form-label">Cant. Galones</label>
                                                                            <input type="number" class="form-control" id="cantgalon_sinfel" placeholder="0" min="0" step="0.01">
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <button type="button" class="btn btn-primary btn-sm" onclick="agregarItemFactura('')">
                                                                                <i class="fas fa-plus me-1"></i>Agregar Ítem
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Tabla de ítems sin FEL -->
                                                            <div class="card" style="border: 3px solid #6c757d; border-radius: 8px; box-shadow: 0 3px 6px rgba(108, 117, 125, 0.2);">
                                                                <div class="card-header bg-secondary text-white">
                                                                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Ítems sin Factura FEL</h6>
                                                                </div>
                                                                <div class="card-body p-0">
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm table-hover mb-0" id="tbItems_sinfel">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th style="width:5%">*</th>
                                                                                    <th>Descripción</th>
                                                                                    <th class="text-end" style="width:15%">Monto</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody></tbody>
                                                                            <tfoot>
                                                                                <tr>
                                                                                    <td colspan="2" class="text-end"><strong>Subtotal:</strong></td>
                                                                                    <td class="text-end"><strong id="subtotal_sinfel">Q 0.00</strong></td>
                                                                                </tr>
                                                                            </tfoot>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Los acordeones de facturas FEL se agregarán dinámicamente aquí -->
                                            </div>
                                        </div>

                                        <!-- FEL Modal -->
                                        <div class="modal fade" id="felModal" tabindex="-1" aria-labelledby="felModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="felModalLabel">Agregar Factura FEL</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form id="felForm" class="row g-3">
                                                            <input type="hidden" id="fel_id_temp" value="">

                                                            <div class="col-md-6">
                                                                <label class="form-label">Número de DTE <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" id="fel_num_dte" placeholder="XXXXXXXX" inputmode="numeric" pattern="[0-9]*" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Serie <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" id="fel_serie" placeholder="XXXXXXXXXX" inputmode="text" pattern="[A-Za-z0-9]*" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                                                                <input type="date" class="form-control" id="fel_fecha" value="<?= date('Y-m-d'); ?>" required>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label">Concepto</label>
                                                                <textarea class="form-control" id="fel_concepto" placeholder="Descripción del concepto de la factura" rows="2"></textarea>
                                                                <small class="form-text text-muted">Breve descripción de la factura</small>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label">Emisor (Nombre Comercial - NIT) <span class="text-danger">*</span></label>
                                                                <div class="d-flex gap-2">
                                                                    <div class="flex-grow-1">
                                                                        <template x-if="cargandoEmisores">
                                                                            <div class="text-center py-3">
                                                                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                                                    <span class="visually-hidden">Cargando...</span>
                                                                                </div>
                                                                                <span class="ms-2 text-muted">Cargando emisores...</span>
                                                                            </div>
                                                                        </template>
                                                                        <template x-if="!cargandoEmisores">
                                                                            <select class="form-select" id="fel_nit_emisor" style="width: 100%" required>
                                                                                <option value="" selected disabled>Seleccione un emisor</option>
                                                                                <template x-for="emisor in emisores" :key="emisor.id">
                                                                                    <option :value="emisor.id" x-text="`${emisor.nombre_comercial} - ${emisor.nit}`"></option>
                                                                                </template>
                                                                            </select>
                                                                        </template>
                                                                    </div>
                                                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevoProveedor" title="Crear nuevo proveedor">
                                                                        <i class="fa-solid fa-plus"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="button" class="btn btn-primary" id="btnAgregarFacturaFEL">
                                                            <i class="fas fa-save me-1"></i>Agregar Factura
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal para crear nuevo proveedor -->
                                        <div class="modal fade" id="modalNuevoProveedor" tabindex="-1" aria-labelledby="modalNuevoProveedorLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="modalNuevoProveedorLabel">Crear Nuevo Proveedor</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form id="formNuevoProveedor">
                                                            <div class="row g-3">
                                                                <div class="col-md-6">
                                                                    <label for="proveedor_nit" class="form-label">NIT <span class="text-danger">*</span></label>
                                                                    <input type="text" class="form-control" id="proveedor_nit" name="proveedor_nit" required maxlength="45" placeholder="Ej: 12345678-9">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label for="proveedor_correo" class="form-label">Correo</label>
                                                                    <input type="email" class="form-control" id="proveedor_correo" name="proveedor_correo" maxlength="60" placeholder="correo@ejemplo.com">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label for="proveedor_nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                                                    <input type="text" class="form-control" id="proveedor_nombre" name="proveedor_nombre" required maxlength="100" placeholder="Nombre completo">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label for="proveedor_nombre_comercial" class="form-label">Nombre Comercial <span class="text-danger">*</span></label>
                                                                    <input type="text" class="form-control" id="proveedor_nombre_comercial" name="proveedor_nombre_comercial" required maxlength="100" placeholder="Nombre comercial">
                                                                </div>
                                                                <div class="col-md-12">
                                                                    <label for="proveedor_direccion" class="form-label">Dirección</label>
                                                                    <input type="text" class="form-control" id="proveedor_direccion" name="proveedor_direccion" maxlength="100" placeholder="Dirección completa">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label for="proveedor_afiliacion_iva" class="form-label">Afiliación IVA</label>
                                                                    <select class="form-select" id="proveedor_afiliacion_iva" name="proveedor_afiliacion_iva">
                                                                        <option value="">Seleccione...</option>
                                                                        <?php
                                                                        if (!empty($tiposAfiliacionIva)) {
                                                                            foreach ($tiposAfiliacionIva as $tipo) {
                                                                                $id = htmlspecialchars($tipo['id']);
                                                                                $label = isset($tipo['abreviacion']) ? $tipo['abreviacion'] : '';
                                                                                $desc = isset($tipo['descripcion']) ? $tipo['descripcion'] : '';
                                                                                echo "<option value=\"{$id}\">" . htmlspecialchars(trim("$label - $desc")) . "</option>";
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <?= $csrf->getTokenField(); ?>
                                                        </form>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="button" class="btn btn-primary" id="btnGuardarProveedor">
                                                            <i class="fa-solid fa-save"></i> Guardar Proveedor
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr class="my-3">
                                    <div class="row mb-3 g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Fecha</label>
                                            <input type="date" class="form-control" id="fecha" value="<?= date('Y-m-d'); ?>" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Recibo</label>
                                            <input type="text" class="form-control"
                                                id="recibo" value="<?php echo ($flag_correlativo == 1) ? ((int)($correlativo ?? 0) + 1) : '' ?>" required>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Adicional</label>
                                            <div class="row">
                                                <div class="col-sm-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="opcalculo"
                                                            id="radio1" value="1" checked onclick="opciones(1)">
                                                        <label class="form-check-label" for="radio1" id="lb1">Sin dato adicional</label>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="opcalculo" id="radio2" value="2" onclick="opciones(2)">
                                                        <label class="form-check-label" for="radio2" id="lb2">Cliente</label>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="opcalculo" id="radio3" value="3" onclick="opciones(3)">
                                                        <label class="form-check-label" for="radio3" id="lb3">Usuarios</label>
                                                    </div>
                                                </div>
                                                <div class="col-sm-6 col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="opcalculo" id="radio4" value="4" onclick="opciones(4)">
                                                        <label class="form-check-label" for="radio4" id="lb4">Agencias</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12" id="divclientes" style="display: none;">
                                            <label class="form-label">Seleccione un cliente</label>
                                            <div class="input-group">
                                                <button class="btn btn-warning" type="button" id="button-addon1" data-bs-toggle="modal" data-bs-target="#otr_cli">Buscar</button>
                                                <input type="text" id="cliente" class="form-control" placeholder="Cliente">
                                            </div>
                                        </div>

                                        <div class="col-12" id="divusuarios" style="display: none;">
                                            <label class="form-label">Seleccione un usuario</label>
                                            <select <?= $disabled; ?> class="form-select" id="idusuario">
                                                <?php
                                                echo '<option selected disabled value="0">Seleccione un usuario</option>';
                                                if (isset($users)) {
                                                    foreach ($users as $user) {
                                                        $selected = ($idusuario == $user["id_usu"]) ? "selected" : "";
                                                        echo '<option ' . $selected . ' value="' . $user['id_usu'] . '">' . $user['nombre'] . ' ' . $user['apellido'] . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="col-12" id="divagencias" style="display: none;">
                                            <label class="form-label">Seleccione una agencia</label>
                                            <select <?= $disabled; ?> class="form-select" id="idagencia">
                                                <?php
                                                echo '<option selected disabled value="0">Seleccione una agencia</option>';
                                                if (isset($agencies)) {
                                                    foreach ($agencies as $agency) {
                                                        $selected = ($idagencia == $agency["id_agencia"]) ? "selected" : "";
                                                        echo '<option ' . $selected . ' value="' . $agency['id_agencia'] . '">' . $agency['nom_agencia'] . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-floating">
                                                <textarea class="form-control" placeholder="Descripción" data-label="Descripción"
                                                    id="descrip" style="height:80px" required minlength="3"></textarea>
                                                <label for="descrip">Descripción</label>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Resumen Total (movido desde la columna derecha) -->
                                    <div class="card border-3 border-primary shadow-lg mt-4">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Resumen Total del Recibo</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <div class="card bg-info bg-opacity-10 border-info">
                                                        <div class="card-body text-center">
                                                            <small class="text-muted d-block">IVA 12%</small>
                                                            <h4 class="mb-0 text-info" id="iva12_total">Q 0.00</h4>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-warning bg-opacity-10 border-warning">
                                                        <div class="card-body text-center">
                                                            <small class="text-muted d-block">IVA Gasolina</small>
                                                            <h4 class="mb-0 text-warning" id="ivaGasolina_total">Q 0.00</h4>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-success text-white">
                                                        <div class="card-body text-center">
                                                            <small class="d-block opacity-75">Total General</small>
                                                            <h3 class="mb-0 fw-bold" id="total_general">Q 0.00</h3>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div> <!-- card-body -->
                            </div> <!-- card -->
                        </div>
                    </div> <!-- row -->
                </form>

                <!-- botones de acción -->
                <div class="row mt-3">
                    <div class="col d-flex gap-2">
                        <?php if ($status) { ?>
                            <button type="button" id="btnGua" class="btn btn-outline-success"><b>Guardar</b></button>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="modalTinpoIngreso">
            <div class="modal fade" id="otr_Ingresos" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="exampleModalLabel">Otros gastos</h1>
                            <button type="hidden" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            <input type="hidden" id="id_modal_hidden" value="" readonly>
                        </div>
                        <div class="modal-body">
                            <!-- INICIO Tabla de nomenclatura -->
                            <div class="container mt-3">
                                <h5>Registros </h5>
                                <table class="table" id="tbOtrosG">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>No.</th>
                                            <th>Tipo de Gasto</th>
                                            <th>Grupo</th>
                                            <th>Opciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($tiposEgresos as $con => $row) {
                                            $con += 1;
                                            $nombre = htmlspecialchars($row['nomGasto'], ENT_QUOTES);
                                            $id = htmlspecialchars($row['id'], ENT_QUOTES);
                                        ?>
                                            <!-- seccion de datos -->
                                            <tr>
                                                <td><?= $con ?></td>
                                                <td><?= $row['nomGasto'] ?></td>
                                                <td><?= $row['grupo'] ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="selectTipoGasto('<?= $nombre ?>', '<?= $id ?>')">
                                                        <i class="fas fa-check me-1"></i>Seleccionar
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                <script>
                                    $(document).ready(function() {
                                        $('#tbOtrosG').on('search.dt')
                                            .DataTable({
                                                "lengthMenu": [
                                                    [5, 10, 15, -1],
                                                    ['5 filas', '10 filas', '15 filas', 'Mostrar todos']
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
                                                    "sProcessing": "Procesando...",

                                                },
                                            });
                                    })
                                </script>
                                <!-- FIN Tabla de nomenclatura -->
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Resetear variables globales al cargar la vista
            window.facturasFEL = [];
            window.contadorFacturasFEL = 0;
            window.facturaActiva = '';
            window.itemsSinFEL = [];

            // Variables globales para gestión de facturas FEL
            var facturasFEL = window.facturasFEL;
            var contadorFacturasFEL = window.contadorFacturasFEL;
            var facturaActiva = window.facturaActiva;

            function setActiveFactura(facturaId) {
                facturaActiva = facturaId;
            }
            
            // Función mejorada para seleccionar tipo de gasto desde el modal
            function selectTipoGasto(nombre, id) {
                const suffix = facturaActiva || 'sinfel';
                $(`#otr_gasto_${suffix}`).val(nombre);
                $(`#idTG_${suffix}`).val(id);
                $('#otr_Ingresos').modal('hide');
            }

            function prepararNuevaFacturaFEL() {
                // Limpiar los campos del formulario
                $('#fel_num_dte').val('');
                $('#fel_serie').val('');
                $('#fel_fecha').val('<?= date('Y-m-d'); ?>');
                $('#fel_concepto').val('');
                if ($('#fel_nit_emisor').data('select2')) {
                    $('#fel_nit_emisor').val(null).trigger('change');
                }

                // Cargar emisores si no están cargados
                const feldataDiv = document.querySelector('#feldataDiv');
                if (feldataDiv && feldataDiv._x_dataStack && feldataDiv._x_dataStack[0]) {
                    feldataDiv._x_dataStack[0].cargarEmisores();
                }
            }

            function agregarFacturaFEL() {
                const numDTE = $('#fel_num_dte').val();
                const serie = $('#fel_serie').val();
                const fecha = $('#fel_fecha').val();
                const emisorId = $('#fel_nit_emisor').val();
                const emisorText = $('#fel_nit_emisor option:selected').text();
                const concepto = $('#fel_concepto').val();

                if (!numDTE || !serie || !fecha || !emisorId) {
                    Swal.fire({
                        icon: "warning",
                        title: "Campos requeridos",
                        text: "Por favor complete todos los campos obligatorios de la factura FEL."
                    });
                    return;
                }

                contadorFacturasFEL++;
                const facturaId = 'FEL' + contadorFacturasFEL;

                const factura = {
                    id: facturaId,
                    numDTE: numDTE,
                    serie: serie,
                    fecha: fecha,
                    emisorId: emisorId,
                    emisorText: emisorText,
                    concepto: concepto,
                    items: []
                };

                facturasFEL.push(factura);

                // Crear acordeón para la nueva factura
                crearAcordeonFactura(factura);

                // Cerrar modal y limpiar campos
                $('#felModal').modal('hide');
                $('#fel_num_dte').val('');
                $('#fel_serie').val('');
                $('#fel_fecha').val('<?= date('Y-m-d'); ?>');
                $('#fel_concepto').val('');
                if ($('#fel_nit_emisor').data('select2')) {
                    $('#fel_nit_emisor').val(null).trigger('change');
                }

                Swal.fire({
                    icon: "success",
                    title: "Factura agregada",
                    text: "La factura FEL ha sido agregada correctamente. Ahora puede agregar ítems a esta factura.",
                    timer: 2500,
                    showConfirmButton: false
                });
            }

            function crearAcordeonFactura(factura) {
                const acordeonHTML = `<div class="accordion-item mb-3" id="acordeon_${factura.id}" style="border: 3px solid #0d6efd; border-radius: 10px; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2);">
    <h2 class="accordion-header" id="heading_${factura.id}">
        <div class="d-flex align-items-center">
            <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" 
                data-bs-target="#collapse_${factura.id}" aria-expanded="false" aria-controls="collapse_${factura.id}"
                style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); font-weight: 600;">
                <div class="d-flex justify-content-between align-items-center w-100 me-2">
                    <div>
                        <i class="fas fa-file-invoice text-primary me-2" style="font-size: 1.2em;"></i>
                        <strong class="text-primary" style="font-size: 1.05em;">Factura FEL: ${factura.serie} - ${factura.numDTE}</strong>
                        <small class="text-muted ms-2"><i class="fas fa-calendar me-1"></i>${factura.fecha}</small>
                    </div>
                    <span class="badge bg-primary" id="badge_count_${factura.id}" style="font-size: 0.9em; padding: 0.4em 0.8em;">0 ítems</span>
                </div>
            </button>
            <button type="button" class="btn btn-sm btn-danger ms-2 me-2" 
                onclick="eliminarFacturaFEL('${factura.id}')" 
                title="Eliminar factura">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </h2>
    <div id="collapse_${factura.id}" class="accordion-collapse collapse" 
        aria-labelledby="heading_${factura.id}" data-bs-parent="#accordionFacturas">
        <div class="accordion-body" style="padding: 1.5rem;">
            <div class="card mb-3" style="border: 3px solid #17a2b8; border-radius: 8px; box-shadow: 0 3px 6px rgba(23, 162, 184, 0.2);">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Datos de la Factura FEL</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">Emisor:</small><br>
                            <strong>${factura.emisorText}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Serie:</small><br>
                            <strong>${factura.serie}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">DTE:</small><br>
                            <strong>${factura.numDTE}</strong>
                        </div>
                        <div class="col-md-12 mt-2">
                            <small class="text-muted">Concepto:</small><br>
                            <strong>${factura.concepto}</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-3" style="border: 3px solid #28a745; border-radius: 8px; box-shadow: 0 3px 6px rgba(40, 167, 69, 0.2);">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Agregar Ítem a esta Factura</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Tipo de gasto</label>
                            <div class="input-group">
                                <button class="btn btn-warning" type="button" data-bs-toggle="modal" 
                                    data-bs-target="#otr_Ingresos" onclick="setActiveFactura('${factura.id}')">Buscar</button>
                                <input type="text" id="otr_gasto_${factura.id}" readonly class="form-control" placeholder="Seleccione tipo de gasto">
                                <input type="hidden" id="idTG_${factura.id}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Monto</label>
                            <input type="number" class="form-control" id="monto_${factura.id}" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo de impuesto</label>
                            <select class="form-select" id="tipimpuesto_${factura.id}" onchange="impuesto(this.value, '${factura.id}')">
                                <option value="0" selected>No aplica</option>
                                <option value="1">IVA 12%</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="imphidrocarburos_${factura.id}" hidden>
                            <label class="form-label">Impuesto Hidrocarburos</label>
                            <select class="form-select" id="imselecHidro_${factura.id}" onchange="impuestohidro(this.value, '${factura.id}')">
                                <option value="0" selected>No aplica</option>
                                <option value="1">Gasolina superior (Q.4.70)</option>
                                <option value="2">Gasolina Regular (Q4.60)</option>
                                <option value="3">Gasolina de Aviación (Q4.70)</option>
                                <option value="4">Diesel y gas oil (Q1.30)</option>
                                <option value="5">Kerosina (Q0.50)</option>
                                <option value="6">Kerosina para motores de reacción (Q0.50)</option>
                                <option value="7">Nafta (Q0.50)</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="congalon_${factura.id}" hidden>
                            <label class="form-label">Cant. Galones</label>
                            <input type="number" class="form-control" id="cantgalon_${factura.id}" placeholder="0" min="0" step="0.01">
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-primary btn-sm" onclick="agregarItemFactura('${factura.id}')">
                                <i class="fas fa-plus me-1"></i>Agregar Ítem
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card" style="border: 3px solid #0d6efd; border-radius: 8px; box-shadow: 0 3px 6px rgba(13, 110, 253, 0.2);">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Ítems de esta Factura</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="tbItems_${factura.id}">
                            <thead>
                                <tr>
                                    <th style="width:5%">*</th>
                                    <th>Descripción</th>
                                    <th class="text-end" style="width:15%">Monto</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end"><strong id="subtotal_${factura.id}">Q 0.00</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>`;

                $('#accordionFacturas').append(acordeonHTML);
            }

            function eliminarFacturaFEL(facturaId) {
                const factura = facturasFEL.find(f => f.id === facturaId);
                if (!factura) return;

                if (factura.items.length > 0) {
                    Swal.fire({
                        icon: "warning",
                        title: "No se puede eliminar",
                        text: "Esta factura tiene ítems asignados. Elimine primero todos los ítems."
                    });
                    return;
                }

                Swal.fire({
                    title: '¿Está seguro?',
                    text: "Se eliminará esta factura FEL",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Eliminar del array
                        facturasFEL = facturasFEL.filter(f => f.id !== facturaId);

                        // Eliminar acordeón
                        $(`#acordeon_${facturaId}`).remove();

                        // Recalcular totales
                        calcularTotalGeneral();

                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminada',
                            text: 'La factura FEL ha sido eliminada.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                });
            }

            function agregarItemFactura(facturaId) {
                const suffix = facturaId || 'sinfel';
                const idG = $(`#idTG_${suffix}`).val();
                const descripcion = $(`#otr_gasto_${suffix}`).val();
                const monto = parseFloat($(`#monto_${suffix}`).val());
                const tipoIva = $(`#tipimpuesto_${suffix}`).val();
                const tipoIva2 = $(`#imselecHidro_${suffix}`).val();
                const cantGalones = parseFloat($(`#cantgalon_${suffix}`).val() || 0);

                if (!descripcion || !monto) {
                    Swal.fire({
                        icon: "warning",
                        title: "Campos requeridos",
                        text: "Debe seleccionar un tipo de gasto e ingresar el monto."
                    });
                    return;
                }

                // Calcular IVAs
                let montoNeto = monto;
                let iva12 = 0;
                let ivaGasolina = 0;
                let descripcionGasolina = '';

                if (tipoIva == '1') {
                    if (tipoIva2 > 0) {
                        const tasas = {
                            '1': {
                                val: 4.70,
                                desc: 'Gasolina Superior'
                            },
                            '2': {
                                val: 4.60,
                                desc: 'Gasolina Regular'
                            },
                            '3': {
                                val: 4.70,
                                desc: 'Gasolina de Aviación'
                            },
                            '4': {
                                val: 1.30,
                                desc: 'Diesel y gas oil'
                            },
                            '5': {
                                val: 0.50,
                                desc: 'Kerosina'
                            },
                            '6': {
                                val: 0.50,
                                desc: 'Kerosina para motores de reacción'
                            },
                            '7': {
                                val: 0.50,
                                desc: 'Nafta'
                            }
                        };
                        ivaGasolina = cantGalones * tasas[tipoIva2].val;
                        descripcionGasolina = tasas[tipoIva2].desc;
                        iva12 = (monto - ivaGasolina) - ((monto - ivaGasolina) / 1.12);
                        montoNeto = monto - ivaGasolina - iva12;
                    } else {
                        iva12 = monto - (monto / 1.12);
                        montoNeto = monto - iva12;
                    }
                }

                // Obtener array de items de la factura
                let items;
                if (facturaId) {
                    const factura = facturasFEL.find(f => f.id === facturaId);
                    items = factura.items;
                } else {
                    // Items sin factura
                    if (!window.itemsSinFEL) window.itemsSinFEL = [];
                    items = window.itemsSinFEL;
                }

                // Crear ítem con impuestos incluidos como propiedades
                const nuevoItem = {
                    id: Date.now(),
                    idG: idG,
                    descripcion: descripcion,
                    monto: montoNeto,
                    montoTotal: monto,
                    iva12: tipoIva == '1' ? iva12 : 0,
                    ivaCombustible: tipoIva == '1' && ivaGasolina > 0 ? ivaGasolina : 0,
                    tipoCombustible: tipoIva == '1' && ivaGasolina > 0 ? descripcionGasolina : '',
                    cantidadGalones: tipoIva == '1' && ivaGasolina > 0 ? cantGalones : 0
                };
                items.push(nuevoItem);

                // Agregar a la tabla
                const tr = $(`<tr id="item_${nuevoItem.id}">`);
                tr.append(`<td><button type="button" class="btn btn-sm btn-danger" onclick="eliminarItem('${facturaId}', ${nuevoItem.id})"><i class="fas fa-minus"></i></button></td>`);
                tr.append(`<td>${descripcion}</td>`);
                tr.append(`<td class="text-end">${montoNeto.toFixed(2)}</td>`);
                $(`#tbItems_${suffix} tbody`).append(tr);

                // Agregar filas de IVA para visualización
                if (tipoIva == '1') {
                    const trIVA = $(`<tr id="item_${nuevoItem.id}_iva12" data-parent="${nuevoItem.id}">`);
                    trIVA.append(`<td></td>`);
                    trIVA.append(`<td><strong>IVA 12%</strong></td>`);
                    trIVA.append(`<td class="text-end">${iva12.toFixed(2)}</td>`);
                    $(`#tbItems_${suffix} tbody`).append(trIVA);

                    if (ivaGasolina > 0) {
                        const trIVAGas = $(`<tr id="item_${nuevoItem.id}_ivacomb" data-parent="${nuevoItem.id}">`);
                        trIVAGas.append(`<td></td>`);
                        trIVAGas.append(`<td><strong>IVA ${descripcionGasolina}</strong></td>`);
                        trIVAGas.append(`<td class="text-end">${ivaGasolina.toFixed(2)}</td>`);
                        $(`#tbItems_${suffix} tbody`).append(trIVAGas);
                    }
                }

                // Limpiar formulario
                $(`#idTG_${suffix}`).val('');
                $(`#otr_gasto_${suffix}`).val('');
                $(`#monto_${suffix}`).val('');
                $(`#tipimpuesto_${suffix}`).val('0');
                $(`#imselecHidro_${suffix}`).val('0');
                $(`#cantgalon_${suffix}`).val('');
                $(`#imphidrocarburos_${suffix}`).attr('hidden', true);
                $(`#congalon_${suffix}`).attr('hidden', true);

                // Actualizar subtotales
                actualizarSubtotal(facturaId);
                calcularTotalGeneral();
            }

            function eliminarItem(facturaId, itemId) {
                const suffix = facturaId || 'sinfel';

                // Obtener array de items
                let items;
                if (facturaId) {
                    const factura = facturasFEL.find(f => f.id === facturaId);
                    items = factura.items;
                } else {
                    items = window.itemsSinFEL || [];
                }

                // Eliminar del array
                const index = items.findIndex(i => i.id === itemId);
                if (index !== -1) {
                    items.splice(index, 1);
                }

                // Eliminar de la tabla (ítem principal y sus filas de impuestos)
                $(`#item_${itemId}`).remove();
                $(`#item_${itemId}_iva12`).remove();
                $(`#item_${itemId}_ivacomb`).remove();

                // Actualizar subtotales
                actualizarSubtotal(facturaId);
                calcularTotalGeneral();
            }

            function actualizarSubtotal(facturaId) {
                const suffix = facturaId || 'sinfel';

                let items;
                if (facturaId) {
                    const factura = facturasFEL.find(f => f.id === facturaId);
                    items = factura.items;
                } else {
                    items = window.itemsSinFEL || [];
                }

                const subtotal = items.reduce((sum, item) => sum + item.monto, 0);
                $(`#subtotal_${suffix}`).text(`Q ${subtotal.toFixed(2)}`);

                // Actualizar badge de cantidad
                $(`#badge_count_${suffix}`).text(facturaId ? `${items.length} ítems` : items.length);
            }

            function calcularTotalGeneral() {
                let totalGeneral = 0;
                let totalIVA12 = 0;
                let totalIVAGasolina = 0;

                // Sumar items sin FEL
                if (window.itemsSinFEL) {
                    window.itemsSinFEL.forEach(item => {
                        totalGeneral += item.monto + (item.iva12 || 0) + (item.ivaCombustible || 0);
                        totalIVA12 += item.iva12 || 0;
                        totalIVAGasolina += item.ivaCombustible || 0;
                    });
                }

                // Sumar items de facturas FEL
                facturasFEL.forEach(factura => {
                    factura.items.forEach(item => {
                        totalGeneral += item.monto + (item.iva12 || 0) + (item.ivaCombustible || 0);
                        totalIVA12 += item.iva12 || 0;
                        totalIVAGasolina += item.ivaCombustible || 0;
                    });
                });

                $('#total_general').text(`Q ${totalGeneral.toFixed(2)}`);
                $('#iva12_total').text(`Q ${totalIVA12.toFixed(2)}`);
                $('#ivaGasolina_total').text(`Q ${totalIVAGasolina.toFixed(2)}`);
            }

            function impuesto(valor, suffix) {
                if (valor == '1') {
                    $(`#imphidrocarburos_${suffix}`).removeAttr('hidden');
                } else {
                    $(`#imphidrocarburos_${suffix}`).attr('hidden', true);
                    $(`#congalon_${suffix}`).attr('hidden', true);
                }
            }

            function impuestohidro(valor, suffix) {
                if (valor > '0') {
                    $(`#congalon_${suffix}`).removeAttr('hidden');
                } else {
                    $(`#congalon_${suffix}`).attr('hidden', true);
                }
            }

            function getValFelSwitch() {
                return facturasFEL.length > 0 ? 1 : 0;
            }

            // Event listener para el botón de agregar factura FEL
            $(document).ready(function() {
                $('#btnAgregarFacturaFEL').click(agregarFacturaFEL);

                // Sobrescribir la función capData para manejar los campos dinámicos
                const capDataOriginal = window.capData;
                window.capData = function(ids, datos) {
                    // Si estamos trabajando con otr_gasto, usar el sistema de acordeones
                    if (ids.includes('#otr_gasto') && ids.includes('#idTG')) {
                        const suffix = facturaActiva || 'sinfel';
                        const dataParts = datos.split('||');

                        if (dataParts.length >= 2) {
                            $(`#otr_gasto_${suffix}`).val(dataParts[0]);
                            $(`#idTG_${suffix}`).val(dataParts[1]);
                        }
                    } else {
                        // Para otros casos, usar la función original
                        if (capDataOriginal) {
                            capDataOriginal(ids, datos);
                        }
                    }
                };
            });
        </script>

        <!-- Modal de Ayuda FEL -->
        <div class="modal fade" id="modalAyudaFEL" tabindex="-1" aria-labelledby="modalAyudaFELLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="modalAyudaFELLabel">
                            <i class="fas fa-info-circle me-2"></i>Guía: Sistema de Facturas FEL Múltiples
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>¡Nuevo!</strong> Ahora puedes registrar múltiples facturas FEL en un solo recibo de egreso.
                        </div>

                        <!-- Sección 2: Cómo agregar facturas FEL -->
                        <h6 class="text-primary mt-4 mb-3">
                            <i class="fas fa-plus-square me-2"></i>Cómo Agregar Facturas FEL
                        </h6>
                        <div class="card mb-3" style="border-left: 4px solid #0d6efd;">
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li class="mb-2">
                                        <strong>Haz clic en el botón "Agregar Factura FEL"</strong>
                                        <p class="text-muted mb-1">Se abrirá un modal para ingresar los datos de la factura.</p>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Completa los campos obligatorios:</strong>
                                        <ul class="mt-1">
                                            <li>Número de DTE</li>
                                            <li>Serie</li>
                                            <li>Fecha de la factura</li>
                                            <li>Selecciona el emisor (proveedor)</li>
                                        </ul>
                                    </li>
                                    <li class="mb-0">
                                        <strong>Confirma la factura</strong>
                                        <p class="text-muted mb-0">Se creará un acordeón (sección expandible) para esta factura donde podrás agregar sus ítems.</p>
                                    </li>
                                </ol>
                            </div>
                        </div>

                        <!-- Sección 3: Agregar ítems a una factura -->
                        <h6 class="text-primary mt-4 mb-3">
                            <i class="fas fa-list-ul me-2"></i>Cómo Agregar Ítems a una Factura FEL
                        </h6>
                        <div class="card mb-3" style="border-left: 4px solid #28a745;">
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li class="mb-2">
                                        <strong>Expande el acordeón de la factura</strong>
                                        <p class="text-muted mb-1">Haz clic en la sección azul de la factura para expandirla.</p>
                                    </li>
                                    <li class="mb-2">
                                        <strong>En la sección "Agregar Ítem a esta Factura":</strong>
                                        <ul class="mt-1">
                                            <li>Busca y selecciona el tipo de gasto</li>
                                            <li>Ingresa el monto del ítem</li>
                                            <li>Selecciona el tipo de impuesto si aplica (IVA 12%)</li>
                                            <li>Si es combustible, selecciona el tipo e ingresa los galones</li>
                                        </ul>
                                    </li>
                                    <li class="mb-0">
                                        <strong>Haz clic en "Agregar Ítem"</strong>
                                        <p class="text-muted mb-0">El ítem se agregará a la tabla de esa factura específica.</p>
                                    </li>
                                </ol>
                            </div>
                        </div>

                        <!-- Sección 4: Ítems sin factura FEL -->
                        <h6 class="text-primary mt-4 mb-3">
                            <i class="fas fa-receipt me-2"></i>Ítems Sin Factura FEL
                        </h6>
                        <div class="card mb-3" style="border-left: 4px solid #ffc107;">
                            <div class="card-body">
                                <p><strong>¿Cuándo usar esta opción?</strong></p>
                                <p class="mb-2">Para gastos que no tienen una factura FEL asociada (facturas manuales, recibos simples, etc.).</p>
                                <p class="mb-0">Estos ítems se agrupan en la sección "Ítems sin Factura FEL" en la parte superior del formulario.</p>
                            </div>
                        </div>

                        <!-- Sección 6: Ejemplo práctico -->
                        <h6 class="text-primary mt-4 mb-3">
                            <i class="fas fa-clipboard-list me-2"></i>Ejemplo Práctico
                        </h6>
                        <div class="card mb-3">
                            <div class="card-body">
                                <p class="mb-2"><strong>Escenario:</strong> Un recibo con 2 facturas FEL y gastos sin factura</p>
                                <hr>
                                <p class="mb-2"><strong>Factura FEL #1:</strong> Serie A, DTE 12345</p>
                                <ul>
                                    <li>Ítem 1: Papelería - Q 150.00 (con IVA)</li>
                                    <li>Ítem 2: Tóner - Q 250.00 (sin IVA)</li>
                                </ul>
                                <p class="mb-2 mt-3"><strong>Factura FEL #2:</strong> Serie B, DTE 67890</p>
                                <ul>
                                    <li>Ítem 1: Gasolina - Q 200.00 (con IVA + impuesto combustible, 10 galones)</li>
                                </ul>
                                <p class="mb-2 mt-3"><strong>Sin FEL (Si fuese necesario):</strong></p>
                                <ul class="mb-0">
                                    <li>Ítem 1: Limpieza - Q 50.00 (con IVA)</li>
                                    <li>Ítem 2: Propinas - Q 30.00 (sin IVA)</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Sección 7: Consejos -->
                        <h6 class="text-primary mt-4 mb-3">
                            <i class="fas fa-star me-2"></i> Recomendaciones
                        </h6>
                        <div class="alert alert-success" role="alert">
                            <ul class="mb-0">
                                <li>Verifica que todos los datos de la factura FEL sean correctos antes de agregar ítems</li>
                                <li>Puedes eliminar una factura FEL solo si no tiene ítems asignados</li>
                                <li>Los totales se calculan automáticamente al agregar o eliminar ítems</li>
                                <li>Asegúrate de tener al menos un ítem antes de guardar el recibo</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Funciones auxiliares y manejo de eventos
            $(document).ready(function() {
                // inyecCod('#modalTinpoIngreso', 'tipo_ingreso', 2);
                inicializarValidacionAutomaticaGeneric('#formSectionDescription');
                inicializarValidacionAutomaticaGeneric('#formNuevoProveedor');

                $('#btnGua').click(function() {
                    // Validar que haya al menos un ítem
                    const totalItems = (window.itemsSinFEL ? window.itemsSinFEL.length : 0) +
                        facturasFEL.reduce((sum, f) => sum + f.items.length, 0);

                    if (totalItems === 0) {
                        Swal.fire({
                            icon: "info",
                            title: "Alerta",
                            text: "Debe agregar al menos un ítem para realizar el registro."
                        });
                        return;
                    }

                    // Validar que todas las facturas FEL tengan al menos un ítem
                    const facturasSinItems = facturasFEL.filter(f => f.items.length === 0);
                    if (facturasSinItems.length > 0) {
                        const facturasTexto = facturasSinItems.map(f => `${f.serie} - ${f.numDTE}`).join(', ');
                        Swal.fire({
                            icon: "warning",
                            title: "Facturas sin ítems",
                            text: `Las siguientes facturas FEL no tienen ítems asignados: ${facturasTexto}. Por favor agregue ítems o elimine estas facturas.`
                        });
                        return;
                    }

                    // Preparar estructura de datos unificada
                    const facturas = [];
                    
                    // Agregar items sin FEL como una factura especial
                    if (window.itemsSinFEL && window.itemsSinFEL.length > 0) {
                        facturas.push({
                            tipo: 'sinFEL',
                            esFEL: false,
                            numDTE: null,
                            serie: null,
                            fecha: null,
                            emisorId: null,
                            items: window.itemsSinFEL.map(item => ({
                                idG: item.idG,
                                monto: item.monto,
                                montoTotal: item.montoTotal || item.monto,
                                descripcion: item.descripcion,
                                impuestos: {
                                    iva12: item.iva12 || 0,
                                    combustible: {
                                        monto: item.ivaCombustible || 0,
                                        tipo: item.tipoCombustible || '',
                                        galones: item.cantidadGalones || 0
                                    }
                                }
                            }))
                        });
                    }
                    
                    // Agregar facturas FEL
                    facturasFEL.forEach(factura => {
                        facturas.push({
                            tipo: 'FEL',
                            esFEL: true,
                            numDTE: factura.numDTE,
                            serie: factura.serie,
                            fecha: factura.fecha,
                            emisorId: factura.emisorId,
                            concepto: factura.concepto,
                            items: factura.items.map(item => ({
                                idG: item.idG,
                                monto: item.monto,
                                montoTotal: item.montoTotal || item.monto,
                                descripcion: item.descripcion,
                                impuestos: {
                                    iva12: item.iva12 || 0,
                                    combustible: {
                                        monto: item.ivaCombustible || 0,
                                        tipo: item.tipoCombustible || '',
                                        galones: item.cantidadGalones || 0
                                    }
                                }
                            }))
                        });
                    });

                    // console.log('Facturas del recibo:', facturas);
                    // console.log('Valor FEL Switch:', getValFelSwitch());

                    obtiene(
                        ['fecha', 'recibo', 'cliente', 'descrip', 'banco_num_referencia', 'banco_beneficiario_cheque', 'banco_num_cheque', 'banco_negociable', 'banco_fecha'],
                        ['idusuario', 'listcuent', 'idagencia', 'tipdoc'],
                        ['opcalculo', 'tipoMovBanco'],
                        'cre_otrRecibo',
                        '0',
                        [facturas, getValFelSwitch()],
                        function(data2) {
                            printdiv2('#cuadro', 0);
                            reportes([
                                [],
                                [],
                                [],
                                [data2[2]]
                            ], 'pdf', '21', 0, 1);
                        },
                        '¿Está seguro de guardar el registro?'
                    );
                });
            })

            function opciones(op) {
                showhide(['divclientes', 'divusuarios', 'divagencias'], [0, 0, 0]);
                if (op == 2) {
                    showhide(['divclientes', 'divusuarios', 'divagencias'], [1, 0, 0]);
                }
                if (op == 3) {
                    showhide(['divclientes', 'divusuarios', 'divagencias'], [0, 1, 0]);
                }
                if (op == 4) {
                    showhide(['divclientes', 'divusuarios', 'divagencias'], [0, 0, 1]);
                }
            }
            $('#btnGuardarProveedor').click(function() {
                obtiene(
                    ['<?= $csrf->getTokenName() ?>', 'proveedor_correo', 'proveedor_nit', 'proveedor_nombre_comercial', 'proveedor_nombre', 'proveedor_direccion'],
                    ['proveedor_afiliacion_iva'],
                    [],
                    'create_proveedor_otrEgreso',
                    '0',
                    [],
                    function(data2) {
                        if (data2[1] == 1) {
                            $('#modalNuevoProveedor').modal('hide');
                            $('#formNuevoProveedor')[0].reset();
                            const felModal = document.getElementById('felModal');
                            const modalFel = new bootstrap.Modal(felModal);
                            modalFel.show();
                            obtiene([], [], [], 'cargar_emisores', '0', [],
                                (dataEmisores) => {
                                    if (dataEmisores && dataEmisores.emisores) {
                                        const feldataDiv = document.querySelector('#feldataDiv');
                                        const alpineComponent = feldataDiv._x_dataStack ? feldataDiv._x_dataStack[0] :
                                            (feldataDiv.__x ? feldataDiv.__x.$data : null);
                                        if (alpineComponent) {
                                            alpineComponent.emisores = dataEmisores.emisores;
                                            setTimeout(() => {
                                                if ($('#fel_nit_emisor').hasClass('select2-hidden-accessible')) {
                                                    $('#fel_nit_emisor').select2('destroy');
                                                }
                                                $('#fel_nit_emisor').select2({
                                                    dropdownParent: $('#felModal'),
                                                    placeholder: 'Seleccione un emisor',
                                                    allowClear: true,
                                                    theme: 'bootstrap-5',
                                                    language: {
                                                        noResults: () => 'No se encontraron resultados',
                                                        searching: () => 'Buscando...'
                                                    }
                                                });
                                                setTimeout(() => {
                                                    $('#fel_nit_emisor').val(data2[2]).trigger('change');
                                                }, 200);
                                            }, 100);
                                        } else {
                                            console.error('No se pudo acceder al componente Alpine.js');
                                        }
                                    }
                                },
                                false
                            );
                        }
                    },
                    '¿Desea crear este proveedor?'
                );
            });
            $('#modalNuevoProveedor').on('hidden.bs.modal', function() {
                $('#formNuevoProveedor')[0].reset();
            });
        </script>

    <?php
        include_once '../../src/cris_modales/mdls_otr_recibo.php'; //LLamar al modal
        break;
    case 'recibos_otros_ingresos':
        $xtra = $_POST["xtra"];
        $usuario = $_SESSION["id"];
        $where = "";
        $mensaje_error = "";
        $bandera_error = false;
        //Validar si ya existe un registro igual que el nombre
        $nuew = "ccodusu='$usuario' AND (dfecsis BETWEEN '" . date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days')) . "' AND  '" . date('Y-m-d') . "')";
        try {
            $stmt = $conexion->prepare("SELECT IF(tu.puesto='ADM' OR tu.puesto='GER' OR tu.puesto='ANA' OR tu.puesto='CNT' OR tu.puesto='CJG', '1=1', ?) AS valor FROM tb_usuario tu WHERE tu.id_usu = ?");
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
        } catch (Exception $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            $bandera_error = true;
        }
    ?>
        <input type="text" id="condi" value="recibos_otros_ingresos" hidden>
        <input type="text" id="file" value="otros_ingresos_01" hidden>

        <div class="text" style="text-align:center">ADMINISTRACIÓN RECIBO OTROS INGRESOS</div>
        <div class="card">
            <div class="card-header">Administración de recibos de otros ingresos</div>
            <div class="card-body">
                <?php if ($bandera_error) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡Error!</strong> <?= $mensaje_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <!-- tabla de recibos individuales -->
                <div class="row mt-2 pb-2">
                    <div class="table-responsive">
                        <table id="otr_Recibos" class="table table-hover table-border nowrap" style="width:100%">
                            <thead class="text-light table-head-aprt mt-2">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Recibo</th>
                                    <th>Concepto</th>
                                    <th>Opción</th>
                                </tr>
                            </thead>
                            <tbody style="font-size: 0.9rem !important;">

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $("#otr_Recibos").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/otr_recibo.php",
                    columns: [{
                            data: [1]
                        },
                        {
                            data: [2]
                        },
                        {
                            data: [4]
                        },
                        {
                            data: [0],
                            render: function(data, type, row) {
                                // console.log(row);
                                btn4 = "";
                                data1 = row.join('||');
                                if (row[6] == "1") {
                                    btn1 = `<button type="button" class="btn btn-primary btn-sm" onclick="reportes([[],[],[],['${row[0]}']], 'pdf', '21',0,1)"><i class="fa-solid fa-print"></i></button>`;
                                    btn2 = `<button type="button" class="btn btn-success btn-sm mx-1" onclick="printdiv('edit_recibo_otros_ingresos', '#cuadro', 'otros_ingresos_01', '${row[0]}')"><i class="fa-solid fa-pen-to-square"></i></button>`;
                                    btn3 = `<button type="button" class="btn btn-danger btn-sm" onclick="eliminar('${row[0]}', 'eli_otrRecibo', ['<?= $usuario; ?>'])"><i class="fa-solid fa-trash"></i></button>`;
                                    if (row[8] != null) {
                                        btn4 = `<button type="button" class="btn btn-warning btn-sm ms-1" onclick="download_image_or_pdf([[],[],[],['${row[0]}']], 'download_file', 1)"><i class="fa-solid fa-download"></i></button>`;
                                    }
                                }
                                return btn1 + btn2 + btn3 + btn4;
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
    <?php
        break;
    case 'edit_recibo_otros_ingresos':
        $xtra = $_POST["xtra"];
        $usuario = $_SESSION["id"];
        $mensaje_error = "";
        $bandera_error = false;
        $bandera = false;
        $datos[] = [];
        try {
            $stmt = $conexion->prepare("SELECT paMov.id AS iddetalle, paMov.id_otr_tipo_ingreso AS idtipo,(SELECT nombre_gasto FROM otr_tipo_ingreso WHERE id = paMov.id_otr_tipo_ingreso) AS nomdetalle,paMov.monto AS montodetalle, op.fecha AS fecharecibo, op.recibo AS recibo, op.cliente AS nomcliente, op.descripcion AS descripcion, op.file AS archivo
            FROM otr_pago_mov paMov
            INNER JOIN otr_tipo_ingreso ingre ON ingre.id = paMov.id_otr_tipo_ingreso
            INNER JOIN otr_pago op ON paMov.id_otr_pago = op.id 
            WHERE id_otr_pago=?");
            if (!$stmt) {
                throw new ErrorException("Error en la consulta: " . $conexion->error);
            }
            $stmt->bind_param("s", $xtra);
            if (!$stmt->execute()) {
                throw new ErrorException("Error al consultar: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $numFilas = $result->num_rows;
            if ($numFilas < 1) {
                throw new ErrorException("No se encontraron registros");
            }
            $i = 0;
            while ($fila = $result->fetch_assoc()) {
                $datos[$i] = $fila;
                $ext = (!isset($fila['archivo']) && empty($fila['archivo'])) ? 'notfound' : pathinfo($fila['archivo'], PATHINFO_EXTENSION);
                if ($ext == 'pdf') {
                    $src = '../includes/img/icon-pdf.png';
                    // es pdf
                    $html = '<img class="img-thumbnail" id="vistaPrevia" style="max-width:120px; max-height:130px;" src="' . $src . '">';
                } else {
                    // es imagen
                    $imgurl = __DIR__ . '/../../../' . $fila['archivo'];
                    if (!is_file($imgurl)) {
                        $src = '../includes/img/file_not_found.png';
                        $html = '<img class="img-thumbnail" id="vistaPrevia" style="max-width:120px; max-height:130px;" src="' . $src . '">';
                    } else {
                        $imginfo   = getimagesize($imgurl);
                        $mimetype  = $imginfo['mime'];
                        $imageData = base64_encode(file_get_contents($imgurl));
                        $html = '<img class="img-thumbnail" id="vistaPrevia" style="max-width:120px; max-height:130px;" src="data:' . $mimetype . ';base64,' . $imageData . '">';
                    }
                }

                $i++;
            }
            $bandera = true;
        } catch (\ErrorException $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            $bandera_error = true;
        }
        // echo '<pre>';
        // print_r($datos);
        // echo '</pre>';
        // echo $html;
    ?>
        <input type="text" id="file" value="otros_ingresos_01" style="display: none;">
        <input type="text" id="condi" value="edit_recibo_otros_ingresos" style="display: none;">
        <div class="text" style="text-align:center">ACTUALIZACIÓN RECIBO DE OTRO INGRESO</div>
        <div class="card">
            <div class="card-header">Actualización de recibo</div>
            <div class="card-body" style="padding-bottom: 0px !important;">
                <?php if ($bandera_error) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡Error!</strong> <?= $mensaje_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <!-- seleccion de cliente y su credito-->
                <div class="container contenedort" style="max-width: 100% !important;">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2"><b>Información de encabezado</b></div>
                        </div>
                    </div>
                    <?php if ($bandera) { ?>
                        <div class="row">
                            <div class="col">
                                <div class="text-center"><span class="text-secondary">Sube un archivo de imagen o PDF</span></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="text-center"><span class="text-primary">Codigo recibo: <b><?= $xtra; ?></b></span></div>
                            </div>
                            <input type="text" class="form-control" id="idenca" hidden placeholder="Fecha" <?php if ($bandera) {
                                                                                                                echo 'value="' . $xtra . '"';
                                                                                                            } ?>>
                        </div>
                        <div class="row justify-content-center">
                            <div class="col-6 col-sm-6 col-md-2 mt-2 d-flex align-items-center">
                                <div class="mx-auto">
                                    <?php echo $html; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col mb-2 mt-2">
                                <div class="input-group">
                                    <input type="file" class="form-control" id="fileuploadcli" aria-describedby="inputGroupFileAddon04" aria-label="Upload" onchange="LeerImagen(this)">
                                    <button class="btn btn-outline-primary" type="button" id="inputGroupFileAddon04" onclick="CargarImagen('fileuploadcli','<?= $xtra; ?>')"><i class="fa-solid fa-sd-card me-2"></i>Guardar</button>
                                </div>
                            </div>
                        </div>
                    <?php }; ?>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="form-floating mb-2 mt-2">
                                <input type="date" class="form-control" id="fecrecibo" placeholder="Fecha" <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['fecharecibo'] . '"';
                                                                                                            } ?>>
                                <label for="fecrecibo">Fecha</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-floating mb-2 mt-2">
                                <input type="text" class="form-control" id="numrecibo" placeholder="Recibo" <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['recibo'] . '"';
                                                                                                            } ?>>
                                <label for="numrecibo">Recibo</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-2 mt-2">
                                <input type="text" class="form-control" id="nomcliente" placeholder="Nombre cliente" <?php if ($bandera) {
                                                                                                                            echo 'value="' . $datos[0]['nomcliente'] . '"';
                                                                                                                        } ?>>
                                <label for="nomcliente">Nombre cliente</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-2 mt-2">
                                <textarea class="form-control" placeholder="Leave a comment here" id="descrip"><?php if ($bandera) {
                                                                                                                    echo $datos[0]['descripcion'];
                                                                                                                } ?></textarea>
                                <label for="floatingTextarea">Descripción</label>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- NACIMIENTO -->
                <div class="container contenedort" style="max-width: 100% !important;">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2"><b>Detalle recibo</b></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="table-responsive">
                            <table id="detalle_recibo" class="table table-hover table-border nowrap" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tipo de Ingreso</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody style="font-size: 0.9rem !important;">
                                    <?php if ($bandera) {
                                        for ($i = 0; $i < count($datos); $i++) { ?>
                                            <tr>
                                                <td scope="row"><?= ($datos[$i]["iddetalle"]) ?></td>
                                                <td><?= ($datos[$i]["nomdetalle"]) ?></td>
                                                <td><?= ($datos[$i]["montodetalle"]) ?></td>
                                            </tr>
                                    <?php }
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container" style="max-width: 100% !important;">
                <div class="row justify-items-md-center">
                    <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                        <?php if ($bandera) { ?>
                            <button class="btn btn-outline-primary mt-2" onclick="obtiene(['idenca', 'fecrecibo', 'numrecibo', 'nomcliente', 'descrip'], [], [], 'act_otrRecibo', '0', ['<?= $usuario; ?>'])"><i class="fa-solid fa-floppy-disk me-2"></i>Actualizar</button>
                            <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv('recibos_otros_ingresos', '#cuadro', 'otros_ingresos_01', '0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                        <?php } ?>
                        <!-- boton para solicitar credito -->
                        <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
<?php
        break;
}
?>