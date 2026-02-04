<?php

use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
use Micro\Helpers\SecureID;

include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
// require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);
date_default_timezone_set('America/Guatemala');

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

$condi = $_POST["condi"];

$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

// $consultas = mysqli_query($conexion, "SELECT * FROM `tb_agencia` WHERE `cod_agenc`=$ofi");
// $agc = mysqli_fetch_array($consultas);
// $inst = $agc;

switch ($condi) {
    case 'tipoaportaciones': {
            // consulta para buscar un registro
            $idtip = $_POST["xtra"];
            $datos = [
                "id_tipo" => "",
            ];
            if ($idtip != "0") {
                $tipos = mysqli_fetch_array(mysqli_query($conexion, "SELECT * FROM `aprtip` WHERE `id_tipo`=$idtip"));
                $datos = $tipos;
            }
?>
            <!-- Tipos de Aportaciones -->
            <div class="text" style="text-align:center">TIPOS DE APORTACIONES</div>
            <input type="text" value="tipoaportaciones" id="condi" style="display: none;">
            <input type="text" value="APRT_0" id="file" style="display: none;">
            <div class="card">
                <div class="card-header">Tipos de Aportaciones</div>
                <div class="card-body">
                    <!-- Seccion de la tabla -->
                    <div class="container contenedort mb-3">
                        <h3>Tipos de Aportaciones</h3>
                        <div class="table-responsive">
                            <table id="tiposaportaciones" class="table table-hover table-border">
                                <thead class="text-light table-head-aprt">
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
                                    <!-- codigo para mostrar listado de registros -->
                                    <?php
                                    $tiposahorros = mysqli_query($conexion, "SELECT * FROM `aprtip`");
                                    while ($tipo = mysqli_fetch_array($tiposahorros, MYSQLI_ASSOC)) {
                                        $id = $tipo["id_tipo"];
                                        $ccodage = $tipo["ccodage"];
                                        $codigo = $tipo["ccodtip"];
                                        $nametip = $tipo["nombre"];
                                        $descripcion = $tipo["cdescripcion"];
                                        $tasa = $tipo["tasa"];
                                        echo '<tr> <td>' . $ccodage . '</td>';
                                        echo '<td>' . $codigo . '</td>';
                                        echo '<td>' . $nametip . '</td>';
                                        echo '<td>' . $descripcion . '</td>';
                                        echo '<td>' . $tasa . '</td>';
                                        echo '<td>
                                            <button type="button" class="btn btn-default" title="Editar" onclick="printdiv2(`#cuadro`,' . $id . ')"> <i class="fa-solid fa-pen"></i></button>
                                            <button type="button" class="btn btn-default" title="Eliminar" onclick="eliminar(' . $id . ',`crud_aportaciones`,`0`,`delete_aport_tip`)"> <i class="fa-solid fa-trash-can"></i></button>
                                         </td></tr> ';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <script>
                            //Datatable para parametrizacion
                            $(document).ready(function() {
                                convertir_tabla_a_datatable("tiposaportaciones");
                            });
                        </script>
                    </div>
                    <?php
                    ?>
                    <!-- select de agencias, primera linea del formulario -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <span>Agencia:</span>
                            <!-- carga de datos en el select, no funciona la parte del value -->
                            <select id="ccodage" class="form-select mt-1">
                                <?php
                                $agencias = mysqli_query($conexion, "SELECT * FROM `tb_agencia`");
                                $selected = "";
                                while ($agencia = mysqli_fetch_array($agencias)) {
                                    ($agencia['cod_agenc'] == $datos[1]) ? $selected = "selected" : $selected = "";
                                    echo '<option value="' . $agencia['cod_agenc'] . '" ' . $selected . ' > ' . $agencia['cod_agenc'] . " - " . $agencia['nom_agencia'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <!-- Segunda linea del formulario -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <!-- obtener el codigo automaticamente -->
                            <?php
                            $numeros = range(1, 99);
                            shuffle($numeros);
                            // $numeros = [1, 1,2,3,5];
                            $ult_codigo = 0;

                            for ($i = 0; $i < count($numeros); $i++) {
                                $j = 0;
                                $bandera = false;
                                $consulta = (mysqli_query($conexion, "SELECT `ccodtip` as cod FROM `aprtip`"));
                                while ($consulta_aux = mysqli_fetch_array($consulta)) {
                                    if ($numeros[$i] == $consulta_aux['cod']) {
                                        $bandera = true;
                                    }
                                    $j++;
                                }
                                if (!$bandera) {
                                    $ult_codigo = $numeros[$i];
                                    $i = count($numeros) + 1;
                                    if ($ult_codigo < 10) {
                                        $ult_codigo = "0" . $ult_codigo;
                                    }
                                }
                            }

                            ?>
                            <span>Código:</span>
                            <input type="text" class="form-control" id="ccodtip" placeholder="Código" value="<?php
                                                                                                                if ($idtip != "0") {
                                                                                                                    echo $datos[2];
                                                                                                                } else {
                                                                                                                    echo $ult_codigo;
                                                                                                                }
                                                                                                                ?>" readonly required>
                        </div>

                        <div class="col-md-8">
                            <span>Nombre:</span>
                            <input type="text" class="form-control" id="nombre" placeholder="Descripción" value="<?php if ($idtip != "0")
                                                                                                                        echo ($datos[3]); ?>" required>
                        </div>
                    </div>

                    <!-- ultima linea del formulario -->
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <span>Descripción:</span>
                            <input type="text" class="form-control" id="cdescripcion" placeholder="Descripción" value="<?php if ($idtip != "0")
                                                                                                                            echo $datos[4]; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <span>Tasa:</span>
                            <input type="number" class="form-control" id="tasa" placeholder="00.00" min="0" value="<?php if ($idtip != "0")
                                                                                                                        echo $datos[5]; ?>" required>
                        </div>
                    </div>
                    <!-- botones de crud -->
                    <div class="row justify-items-md-center">
                        <!-- botones de actualizar y guardar -->
                        <div class="col align-items-center">
                            <?php
                            // guardar un registro
                            if ($idtip == "0") {
                                echo '<button onclick="obtiene([`ccodtip`,`nombre`,`cdescripcion`,`tasa`],[`ccodage`],[],`create_aport_tip`,`0`,`APRT_0`)" type="button" class="btn btn-outline-success" data-dismiss="modal">
                            <i class="fa fa-floppy-disk"></i> Guardar
                            </button>';
                            } else {
                                echo '<button onclick="obtiene([`ccodtip`,`nombre`,`cdescripcion`,`tasa`],[`ccodage`],[],`update_aport_tip`,`0`,' . $idtip . ')" type="button" class="btn btn-outline-primary" data-dismiss="modal">
                            <i class="fa fa-floppy-disk"></i> Actualizar
                            </button>';
                            }
                            ?>

                            <!-- botones de cancelar y salir -->
                            <button type="button" id="undo" class="btn btn-outline-danger" onclick="printdiv2(`#cuadro`,'0')">
                                <i class="fa fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        }
        break;

    case 'apertcuenta': {
            $ccodcli = $_POST["xtra"];

            $showmensaje = false;
            try {
                if ($ccodcli == '0') {
                    $showmensaje = true;
                    throw new Exception("Seleccione un cliente");
                }
                $database->openConnection();

                $datos = $database->selectColumns("tb_cliente", ["idcod_cliente", "short_name", "no_tributaria", "fecha_actualizacion"], "estado=1 AND idcod_cliente=?", [$ccodcli]);
                if (empty($datos)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró el cliente indicado");
                }
                $datosCliente = $datos[0];

                /**
                 * VERIFICAR QUE LA FECHA DE ACTUALIZACION NO SEA MAYOR A 1 AÑO
                 */
                if (Date::isValid($datosCliente['fecha_actualizacion']) && Date::diffInYears($datosCliente['fecha_actualizacion']) >= 1) {
                    $showmensaje = true;
                    throw new Exception("Los datos del cliente tienen más de un año sin actualizar. Por favor, actualice la información del cliente antes de continuar, último actualización: " . Date::format($datosCliente['fecha_actualizacion'], 'd/m/Y'));
                }

                $productos = $database->selectColumns("aprtip", ["id_tipo", "ccodtip", "nombre", "cdescripcion"], "ccodage=?", [$ofi]);
                if (empty($productos)) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron productos de ahorro para la agencia indicada");
                }

                $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (11) AND id_usuario=? AND estado=1", [$idusuario]);
                $accessHandler = new PermissionHandler($permisos, 11);

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
            $institucion = $_SESSION['agencia'];
            $agencia = $_SESSION['agencia'];
            include_once __DIR__ . "/../../src/cris_modales/modal_clientes_all.php";
        ?>
            <div class="text" style="text-align:center">APERTURA DE CUENTA DE APORTACIONES</div>
            <input type="text" id="condi" value="apertcuenta" hidden>
            <input type="text" id="file" value="APRT_0" hidden>

            <div class="card mb-2">
                <div class="card-header">Apertura de Cuenta</div>
                <div class="card-body pb-2">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>!!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="container mb-3 contenedort" style="display: none;">
                        <div hidden class="col-md-3">
                            <div hidden>
                                <label for="usu">codusu</label>
                                <input type="text" class="form-control " id="usu" value="<?php echo $idusuario; ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div>
                                <label for="ins">Institucion</label>
                                <input type="text" class="form-control " id="ins" required value="<?php echo $institucion; ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-2" style="display: none;">
                            <div>
                                <label for="ccodofi">Agencia</label>
                                <input type="text" class="form-control " id="ccodofi" value="<?php echo $agencia; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="container contenedort">
                        <div class="row mb-2">
                            <div class="col-sm-7">
                                <div>
                                    <span class="input-group-addon col-8">Cliente</span>
                                    <input type="text" aria-label="Cliente" id="client" class="form-control" placeholder="" required
                                        value="<?= $datosCliente['short_name'] ?? '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <br>
                                <button title="Buscar cliente" class="btn btn-outline-secondary" type="button" id="button-addon1"
                                    data-bs-toggle="modal" data-bs-target="#buscar_cli_all">
                                    <i class="fa fa-magnifying-glass"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-4">
                                <div>
                                    <span class="input-group-addon col-8">Código de cliente</span>
                                    <input type="text" class="form-control " id="ccodcli" required placeholder=""
                                        value="<?= $datosCliente['idcod_cliente'] ?? '' ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div>
                                    <span class="input-group-addon col-8">NIT</span>
                                    <input type="text" class="form-control " id="nit" required placeholder=""
                                        value="<?= $datosCliente['no_tributaria'] ?? '' ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="display:none;" class="row">
                        <div class="col-md-6">
                            <div>
                                <span class="input-group-addon col-2">Tipo de Cuenta</span>
                                <select class="form-control " id="tipCuenta" required placeholder=""
                                    onchange="correltipcuenta(this.    value,'<?php echo $institucion; ?>','<?php echo $agencia; ?>')">
                                    <option value="0" selected>Seleccione un tipo de producto</option>
                                    <?php
                                    if (!empty($productos)) {
                                        foreach ($productos as $producto) {
                                            echo '<option value="' . $producto['ccodtip'] . '">' . $producto['ccodtip'] . " - " . $producto['nombre'] . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- contenedor de productos -->
                    <div class="container contenedort">
                        <h3>PRODUCTOS</h3>
                        <div class="gridtarjetas" style="color: black;">
                            <?php
                            if (!empty($productos)) {
                                foreach ($productos as $producto) {
                                    echo '
                                        <div style="cursor:pointer;" name="targets" id="' . $producto['ccodtip'] . '" class="tarjeta" onclick="correltipcuenta(`' . $producto['ccodtip'] . '`,`' . $institucion . '`,`' . $agencia . '`)">
                                            <div class="titulo">' . ($producto['nombre']) . '</div>
                                            <div class="cuerpo">
                                                <i class="fa-solid fa-piggy-bank"></i>
                                                
                                                ' . ($producto['cdescripcion']) . '
                                            </div>
                                            <div class="pie">
                                                
                                            </div>
                                        </div>
                                        ';
                                }
                            }
                            ?>
                        </div>
                        <br>
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <div>
                                    <span class="input-group-addon col-8">Correlativo</span>
                                    <input type="text" aria-label="correl" id="correla" name="correla" class="form-control"
                                        placeholder="" readonly>
                                </div>
                            </div>
                            <div class="col-sm-9" style="display:  <?= ($accessHandler->isLow()) ? 'none' : 'block'; ?>;">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <span class="input-group-addon col-8">Cuenta a acreditar Interes</span>
                                        <select id="cuentasaprSelect" class="form-select" aria-label="Default select example"
                                            onchange="corinteres();">
                                        </select>
                                    </div>
                                    <div class="col-sm-3" style="display: none;" id="selecproducto">
                                        <span class="input-group-addon col-8">Seleccione el nuevo tipo de producto</span>
                                        <select id="createdselectapr" class="form-select" aria-label="Default select example"
                                            onchange="ncorrelativo(<?php echo $ofi ?>, <?php echo $institucion ?>);">
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <div>
                                            <span class="input-group-addon col-8">Correlativo Interes</span>
                                            <input type="text" aria-label="correl" id="correlainteresapr" value="0"
                                                class="form-control" placeholder="" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="container contenedort">
                        <div class="row mb-2">
                            <div class="col-md-4" id="div_tasa">
                                <div>
                                    <span class="input-group-addon col-8">Tasa %</span>
                                    <input type="float" class="form-control" id="tasa" placeholder="0.00" required="required"
                                        disabled>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div>
                                    <span class="input-group-addon col-8">Libreta</span>
                                    <input type="text" class="form-control" id="libreta" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3 justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <button type="button" class="btn btn-outline-success"
                                onclick="obtiene([`ins`,`ccodofi`,`tasa`,`ccodcli`,`nit`,`libreta`,`usu`],[`tipCuenta`],[],  `create_apr_cuenta`,`0`,`APRT_0`)">
                                <i class="fa fa-floppy-disk"></i> Guardar
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
                    $("#div_tasa").hide();
                });
            </script>
        <?php
        }
        break;

    case 'benaport': {
            $id = $_POST["xtra"];
            $datoscli = mysqli_query($conexion, "SELECT * FROM `aprcta` WHERE `ccodaport`=$id");
            $bandera = "Cuenta de ahorro no existe";
            while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
                $idcli = ($da["ccodcli"]);
                $nit = ($da["num_nit"]);
                $nlibreta = ($da["nlibreta"]);
                $bandera = "";
            }
            if ($bandera == "") {
                $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente`='$idcli'");
                $nombre = "";
                $bandera = "No existe el cliente relacionado a la cuenta de ahorro";
                while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                    $nombre = Utf8::decode($dat["short_name"]);
                    $bandera = "";
                }
            }
        ?>
            <!-- Beneficiarios de Aportaciones -->
            <div class="text" style="text-align:center">BENEFICIARIOS DE APORTACIONES</div>
            <input type="text" id="file" value="APRT_0" hidden>
            <input type="text" id="condi" value="benaport" hidden>
            <div class="card mb-2">
                <div class="card-header">Beneficiarios de Aportaciones</div>
                <div class="card-body">
                    <!-- contenedor de cuenta de ahorro -->
                    <div class="container contenedort">
                        <div class="row mb-2">
                            <div class="col-md-5">
                                <!-- titulo -->
                                <span class="input-group-addon col-8">Cuenta de Aportación</span>
                                <div class="input-group">
                                    <input type="text" class="form-control " id="ccodaport" required placeholder="000-000-00-000000"
                                        value="<?php if ($bandera == "")
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
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-x" width="26" height="26" viewBox="0 0 24 24" stroke-width="1.5" stroke="#ff2825" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M10 10l4 4m0 -4l-4 4" />
                                          </svg>';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <br>
                                <!-- boton para aplicar cuenta ingresada -->
                                <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                    title="Validar cuenta de aportación digitada" onclick="aplicarcod('ccodaport')">
                                    <i class="fa fa-check-to-slot"></i>
                                </button>
                                <!-- boton para buscar cuenta de ahorro -->
                                <button class="btn btn-outline-secondary" type="button" id="button-addon1" title="Buscar cuenta"
                                    data-bs-toggle="modal" data-bs-target='#findaportcta'>
                                    <i class="fa fa-magnifying-glass"></i>
                                </button>

                                <!-- prueba modakl -->
                            </div>
                        </div>
                        <!-- input para nombre de aportacion -->
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

                    <!-- contenedor para tabla de datos de aportaciones -->
                    <div class="container contenedort" style="padding: 5px !important;">
                        <div class="table-responsive">
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
                                    if ($bandera == "") {
                                        $queryben = mysqli_query($conexion, "SELECT * FROM `aprben` WHERE `codaport`='$id'");
                                        while ($rowq = mysqli_fetch_array($queryben, MYSQLI_ASSOC)) {
                                            $idaprben = ($rowq["id_ben"]);
                                            $bennom = Utf8::decode($rowq["nombre"]);
                                            $bendpi = ($rowq["dpi"]);
                                            $bendire = Utf8::decode($rowq["direccion"]);
                                            $benparent = ($rowq["codparent"]);
                                            $parentdes = parenteco($benparent);
                                            $benfec = ($rowq["fecnac"]);
                                            $benporcent = ($rowq["porcentaje"]);
                                            $total = $total + $benporcent;
                                            $bentel = ($rowq["telefono"]);
                                            echo '<tr>
                                            <td>' . $bendpi . ' </td>
                                            <td>' . $bennom . ' </td>
                                            <td>' . $benfec . ' </td>
                                            <td>' . $parentdes . '</td>
                                            <td>' . $benporcent . '</td>
                                            </tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <script>
                            //Datatable para parametrizacion
                            $(document).ready(function() {
                                convertir_tabla_a_datatable("table_id2");
                            });
                        </script>
                        <div class="row">
                            <!--TOTAL-->
                            <div class="col-md-3">
                                <label for="">Total: <?php echo $total; ?> %</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3 justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <button type="button" id="btnnew" class="btn btn-outline-success"
                                onclick="crear_editar_beneficiario('<?php echo $id; ?>','<?php echo $nombre; ?>');">
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
                ], 'pdf', 39, 0, 1, 'dfecope', 'monto', 2, 'Montos', 0);">
                                <i class="fa-solid fa-file-pdf"></i> Imprimir beneficiarios
                            </button>
                            <!-- <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir -->
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- loader efect -->
            <div class="loader-container loading--hide">
                <div class="loader"></div>
                <div class="loaderimg"></div>
                <div class="loader2"></div>
            </div>
            <!-- fin de loader efect -->
            <!-- MODAL PARA INGRESAR UN NUEVO BENEFICIARIO -->
            <div class="modal fade" id="databen" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog  modal-lg">
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
                                        <span class="input-group-addon col-8">Cuenta de Aportación</span>
                                        <div class="input-group">
                                            <input type="text" class="form-control " id="ccodaport_modal" readonly>
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
                            <div class="container contenedort recar_c" style="padding: 5px !important;">
                                <!-- <div class="row"> -->
                                <div class="table-responsive">
                                    <table id="table_id1" class="table table-hover table-border">
                                        <thead class="text-light table-head-aprt" style="font-size: 0.8rem;">
                                            <tr>
                                                <th>DPI</th>
                                                <th>Nombre Completo</th>
                                                <th>Fec. Nac.</th>
                                                <th>Parentesco</th>
                                                <th>%</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabla_ben" style="font-size: 0.8rem;">

                                        </tbody>
                                    </table>
                                </div>
                                <div class="row">
                                    <!--TOTAL-->
                                    <div class="col-md-3">
                                        <label for="" id="total">Total</label>
                                    </div>
                                </div>
                            </div>
                            <script>
                                //Datatable para parametrizacion
                                $(document).ready(function() {
                                    convertir_tabla_a_datatable("table_id1");
                                });
                            </script>
                            <!-- MODAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAALLLLLLLLLLLLLLLLLLLLLLLLLL -->

                            <div class="container contenedort">
                                <!--Aho_0_BeneAho Nombre-->
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
                                        <input type="text" aria-label="Tel Ben" id="bentel" class="form-control col" placeholder="">
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
                        <div class="modal-footer">
                            <button id="createben" type="button" class="btn btn-primary"
                                onclick="obtiene(['benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent'], ['benparent'], [], 'create_apr_ben', '<?php echo $id; ?>', ['<?php echo $id; ?>','<?php echo $bandera; ?>'])">
                                <i class="fa fa-floppy-disk"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelar_ben"
                                onclick="cancelar_crear_editar_beneficiario('lista_beneficiarios','<?php echo $id; ?>')">Cancelar</button>
                            <button id="updateben" style="display:none;" type="button" class="btn btn-primary"
                                onclick="obtiene(['benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent','benporcentant','idben'], ['benparent'], [], 'update_apr_ben', '<?php echo $id; ?>', ['<?php echo $id; ?>',<?php echo $id; ?>])">
                                <i class="fa fa-floppy-disk"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        }
        break;

    case 'implibreta': {
            $id = $_POST["xtra"];
            $datoscli = mysqli_query($conexion, "SELECT * FROM `aprcta` WHERE `ccodaport`=$id");
            $bandera = "Cuenta de aportación no existe en la base de datos";
            while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
                $idcli = $da["ccodcli"];
                $nit = $da["num_nit"];
                $nlibreta = ($da["nlibreta"]);
                $bandera = "";
            }
            if ($bandera == "") {
                $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente` = '$idcli'");
                $nombre = "";
                $bandera = "No existe el cliente relacionado a la cuenta de aportación";
                while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                    $nombre = ($dat["short_name"]);
                    $bandera = "";
                }
            }
        ?>
            <!-- Impresión de libreta -->
            <div class="text" style="text-align:center">IMPRESIÓN DE LIBRETA</div>
            <input type="text" id="file" value="APRT_0" style="display: none;">
            <input type="text" id="condi" value="implibreta" style="display: none;">
            <div class="card mb-2">
                <!-- titulo -->
                <div class="card-header">Impresión de libreta</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <!-- contenedor de cuenta de ahorro -->
                            <div class="row">
                                <span class="input-group-addon col-12">Cuenta de aportación</span>
                                <div class="input-group">
                                    <input type="text" class="form-control " id="ccodaport" required placeholder="000-000-00-000000"
                                        value="<?php if ($bandera == "")
                                                    echo $id; ?>">
                                    <!-- Se agrego campo de validaciones -->
                                    <span class="input-group-text" id="basic-addon1">
                                        <?php if ($bandera == "") {
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="26" height="26" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00b341" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <circle cx="12" cy="12" r="9" />
                                        <path d="M9 12l2 2l4 -4" />
                                        </svg>';
                                        } else {
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-x" width="26" height="26" viewBox="0 0 24 24" stroke-width="1.5" stroke="#ff2825" fill="none" stroke-linecap="round" stroke-linejoin="round">
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
                            <!-- boton de aplicar, que esta al lado de buscar -->
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaport')">
                                <i class="fa fa-check-to-slot"></i>
                            </button>
                            <!-- boton para buscar una cuenta de ahoro -->
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1" title="Buscar cuenta"
                                data-bs-toggle="modal" data-bs-target="#findaportcta">
                                <i class="fa fa-magnifying-glass"></i>
                            </button>
                        </div>
                    </div>
                    <!-- alert para mostrar que no se encontro la cuenta de aportacion -->
                    <?php if ($bandera != "" && $id != "0") {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $bandera . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                    }
                    ?>
                    <!-- input de libreta-->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div>
                                <span class="input-group-addon col">Libreta</span>
                                <input type="text" class="form-control " id="libreta" readonly required value="<?php if ($bandera == "")
                                                                                                                    echo $nlibreta; ?>">
                            </div>
                        </div>
                    </div>
                    <!--Aho_0_iMprsnLbrt Libreta-->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div>
                                <span class="input-group-addon col">NIT</span>
                                <input type="text" class="form-control " id="nit" readonly required value="<?php if ($bandera == "")
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

                    <!--Aho_0_iMprsnLbrt Borones, Imprimir, Cancelar, Salir-->
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="">
                            <!-- 000000000 modificar esta funcion, es una de php, ahora hay que pasarla a javascript -->
                            <button type="button" id="btnSave" class="btn btn-outline-success"
                                onclick="reportes([[], [], [], ['<?= $id; ?>']], 'pdf', '8',0,1)">
                                <i class="fa-solid fa-print"></i> Imprimir
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-dismiss="modal"
                                onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning" data-dismiss="modal" onclick="salir()">
                                <i class="fa-solid fa-right-from-bracket"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        }
        break;

    case 'CambioLibreta': {
            $id = $_POST["xtra"];
            $ccodusu = $_SESSION['id'];

            $datoscli = mysqli_query($conexion, "SELECT * FROM `aprcta` WHERE `ccodaport`=$id");
            $bandera = "Cuenta de aportación no existe";
            while ($da = mysqli_fetch_array($datoscli, MYSQLI_ASSOC)) {
                $idcli = ($da["ccodcli"]);
                $nit = ($da["num_nit"]);
                $nlibreta = ($da["nlibreta"]);
                $bandera = "";
            }
            if ($bandera == "") {
                $data = mysqli_query($conexion, "SELECT `short_name` FROM `tb_cliente` WHERE `idcod_cliente`='$idcli'");
                $nombre = "";
                $bandera = "No existe el cliente relacionado a la cuenta de ahorro";
                while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                    $nombre = Utf8::decode($dat["short_name"]);
                    $bandera = "";
                }

                //------traer el saldo de la cuenta
                $monto = 0;
                $saldo = 0;
                $transac = mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `aprmov` WHERE `ccodaport`=$id AND cestado!=2");
                while ($row = mysqli_fetch_array($transac, MYSQLI_ASSOC)) {
                    $tiptr = ($row["ctipope"]);
                    $monto = ($row["monto"]);
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
        ?>
            <!--Aho_1_CambioLibre Cambio de Libreria-->
            <div class="text" style="text-align:center">CAMBIO LIBRE</div>
            <input type="text" id="file" value="APRT_0" style="display: none;">
            <input type="text" id="condi" value="CambioLibreta" style="display: none;">
            <div class="card mb-2">
                <div class="card-header">Cambio Libre</div>
                <div class="card-body">
                    <!--Aho_0_iMprsnLbrt Libreta-->
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <!-- contenedor de cuenta de ahorro -->
                            <div class="row">
                                <span class="input-group-addon col-8">Cuenta de aportación</span>
                                <div class="input-group">
                                    <input type="text" class="form-control " id="ccodaport" required placeholder="000-000-00-000000"
                                        value="<?php if ($bandera == "")
                                                    echo $id; ?>">
                                    <!-- Se agrego campo de validaciones -->
                                    <span class="input-group-text" id="basic-addon1">
                                        <?php if ($bandera == "") {
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="26" height="26" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00b341" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M9 12l2 2l4 -4" />
                                            </svg>';
                                        } else {
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-x" width="26" height="26" viewBox="0 0 24 24" stroke-width="1.5" stroke="#ff2825" fill="none" stroke-linecap="round" stroke-linejoin="round">
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

                        <div class="col-md-6">
                            <br>
                            <!-- boton de aplicar, que esta al lado de buscar -->
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaport')">
                                <i class="fa fa-check-to-slot"></i>
                            </button>
                            <!-- boton para buscar una cuenta de ahoro -->
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1" title="Buscar cuenta"
                                data-bs-toggle="modal" data-bs-target="#findaportcta">
                                <i class="fa fa-magnifying-glass"></i>
                            </button>
                        </div>
                    </div>
                    <!-- alert para mostrar que no se encontro la cuenta de aportacion -->
                    <?php if ($bandera != "" && $id != "0") {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $bandera . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                    ?>

                    <!--Aho_0_iMprsnLbrt Libreta-->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div>
                                <span class="input-group-addon col">NIT</span>
                                <input type="text" class="form-control " id="nit" required readonly value="<?php if ($bandera == "")
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
                                <input type="text" class="form-control " id="libreta" readonly value="<?php if ($bandera == "")
                                                                                                            echo $nlibreta; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div>
                                <span class="input-group-addon col">Saldo Disponible</span>
                                <input type="text" class="form-control " id="salDisp" required readonly value="<?php if ($bandera == "")
                                                                                                                    echo 'Q ' . number_format($saldo, 2, '.', ','); ?>">
                            </div>
                        </div>
                    </div>

                    <!--Aho_1_CambioLibre LibretaActual-Nueva Libreta-->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div>
                                <span class="input-group-addon col">Nueva Libreta</span>
                                <input type="number" class="form-control " id="newLibret" required placeholder="0" min="1">
                            </div>
                        </div>
                    </div>
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
                                onclick="obtiene(['ccodaport','newLibret'],[], [], 'cambiar_libreta', '0', ['<?php echo $id; ?>',<?php echo $nlibreta; ?>,'<?php echo $ccodusu; ?>'])">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar
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
<?php
        }
        break;
}
?>