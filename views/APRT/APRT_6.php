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
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

include __DIR__ . '/../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
$condi = $_POST["condi"];

switch ($condi) {
    case 'Parametrizacion_aprt':
        $codusu = $_SESSION['id'];
        $id = $_POST["xtra"];

        $bandera = false;
        $query = "SELECT * FROM ctb_nomenclatura WHERE estado=?";
        $response = executequery($query, [1], ['i'], $conexion);
        if (!$response[1]) {
            $bandera = ($response[0]);
        }
        $nomenclatura = $response[0];
?>

        <style>
            table {
                font-size: 13px;
            }
        </style>
        <div class="text" style="text-align:center">PARAMETRIZACION DE CUENTAS CONTABLES DE APORTACIONES</div>
        <div class="card">
            <input type="text" id="file" value="APRT_6" style="display: none;">
            <input type="text" id="condi" value="Parametrizacion_aprt" style="display: none;">
            <div class="card-header">Parametrizacion Ahorro</div>
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row mt-2 pb-2">
                        <div class="col">
                            <div class="table-responsive">
                                <table id="table_parametrizacion" class="table table-hover table-border">
                                    <thead class="text-light table-head-aho">
                                        <tr>
                                            <th>No.</th>
                                            <th>Agencia</th>
                                            <th>Nombre</th>
                                            <th>Tasa</th>
                                            <th>Cuenta Contable</th>
                                            <th>Cuenta Cuota de Ingreso</th>
                                            <th>Editar Cuentas</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb_cuerpo_parametrizacion">
                                        <?php
                                        $i = 1;
                                        $consulta = mysqli_query($conexion, "SELECT tip.*,ofi.nom_agencia FROM aprtip tip INNER JOIN tb_agencia ofi ON ofi.cod_agenc=tip.ccodage;");
                                        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            $id = $row["id_tipo"];
                                            $agencia = $row["nom_agencia"];
                                            $id_tipo_cuenta = $row["ccodtip"];
                                            $nombre = $row["nombre"];
                                            $tasa = $row["tasa"];

                                            //CUENTA CONTABLE TIPO DE APORTACION
                                            $id_cuenta = $row["id_cuenta_contable"];
                                            $cuenta = ' ';
                                            $namecuenta = 'No hay cuenta definida';
                                            $key = array_search($id_cuenta, array_column($nomenclatura, 'id'));
                                            if ($key !== false) {
                                                $cuenta = $nomenclatura[$key]['ccodcta'];
                                                $namecuenta = $nomenclatura[$key]['cdescrip'];
                                            } else {
                                                $id_cuenta = 0;
                                            }
                                            //CUENTA CONTABLE CUOTA DE INGRESO
                                            $id_cuentacuota = $row["cuenta_aprmov"];
                                            $cuentacuota = ' ';
                                            $namecuentacuota = 'No hay cuenta definida';
                                            $key = array_search($id_cuentacuota, array_column($nomenclatura, 'id'));
                                            if ($key !== false) {
                                                $cuentacuota = $nomenclatura[$key]['ccodcta'];
                                                $namecuentacuota = $nomenclatura[$key]['cdescrip'];
                                            } else {
                                                $id_cuentacuota = 0;
                                            }
                                            echo '<tr> <td>' . $i . '</td>';
                                            echo '<td>' . $agencia . '</td>';
                                            echo '<td>' . $nombre . '</td>';
                                            echo '<td>' . $tasa . '</td>';
                                            echo '<td>' .  $cuenta . ' - ' . $namecuenta  . '</td>';
                                            echo '<td>' .  $cuentacuota . ' - ' . $namecuentacuota . '</td>';
                                            echo '<td>
                                                <button type="button" class="btn btn-default" title="Editar" onclick="loaddata([' . $id . ',`' . $nombre . '`,' . $id_cuenta . ',`' . $cuenta . '`,`' . $namecuenta . '`,' . $id_cuentacuota . ',`' . $cuentacuota . '`,`' . $namecuentacuota . '`])"> <i class="fa-solid fa-pen"></i></button>
                                             </td></tr> ';
                                            $i++;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    //Datatable para parametrizacion
                    $(document).ready(function() {
                        convertir_tabla_a_datatable("table_parametrizacion");
                    });
                </script>

                <div class="container contenedort">
                    <div class="row mb-3 mt-2">
                        <div class="col-sm-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="tipo" readonly>
                                <input type="text" class="form-control" id="idtipo" hidden>
                                <label for="tip_cuenta">Tipo de cuenta</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3 mt-2">
                        <div class="col-md-6">
                            <div class="row"></div>
                            <div class="input-group" id="div_cuenta1">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="text_cuenta1" placeholder="name@example.com" readonly>
                                    <input type="text" class="form-control" id="id_hidden1" hidden readonly>
                                    <label class="text-primary" for="text_cuenta1"><i class="fa-solid fa-file-invoice"></i>Cuenta Contable Tipo de aportacion</label>
                                </div>
                                <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', '#id_modal_hidden', 'id_hidden1,text_cuenta1/A,2-3/-')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row"></div>
                            <div class="input-group" id="div_cuenta2">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="text_cuenta2" placeholder="name@example.com" readonly>
                                    <input type="text" class="form-control" id="id_hidden2" hidden readonly>
                                    <label class="text-primary" for="text_cuenta2"><i class="fa-solid fa-file-invoice"></i>Cuenta Contable Cuota de ingreso</label>
                                </div>
                                <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', '#id_modal_hidden', 'id_hidden2,text_cuenta2/A,2-3/-')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button id="save" style="display: none;" type="button" class="btn btn-outline-primary" onclick="obtiene([`idtipo`,`id_hidden1`,`id_hidden2`],[],[],`update_aprt_cuentas_contables`,`0`,[])">
                            <i class="fa-solid fa-pen-to-square"></i>Actualizar
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
            function loaddata(datos) {
                $('#idtipo').val(datos[0]);
                $('#tipo').val(datos[1]);
                $('#id_hidden1').val(datos[2]);
                $('#text_cuenta1').val(datos[3] + ' - ' + datos[4]);
                $('#id_hidden2').val(datos[5]);
                $('#text_cuenta2').val(datos[6] + ' - ' + datos[7]);
                $('#save').show();
            }
        </script>

        <?php
        break;
    case 'Parametrizacion_aprt_anterior': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
            if ($id == 0) {
                $id = ['0', '0', '0', "", "", "", "", "", ""];
            }
        ?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <div class="text" style="text-align:center">PARAMETRIZACIÓN DE APORTACIONES</div>
            <div class="card">
                <input type="text" id="file" value="APRT_6" style="display: none;">
                <input type="text" id="condi" value="Parametrizacion_aprt" style="display: none;">
                <div class="card-header">Parametrizacion Aportación</div>
                <div class="card-body">
                    <div class="container contenedort">
                        <div class="row mt-2 pb-2">
                            <div class="col">
                                <div class="table-responsive">
                                    <table id="table_parametrizacion" class="table table-hover table-border">
                                        <thead class="text-light table-head-aprt mt-2">
                                            <tr>
                                                <th>Tipo cuenta</th>
                                                <th>Documento</th>
                                                <th>Cuenta 1</th>
                                                <th>Cuenta 2</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tb_cuerpo_parametrizacion">
                                            <?php
                                            $consulta = mysqli_query($conexion, "SELECT ctb.id, ctb.id_tipo_cuenta, tip.nombre, ctb.id_tipo_doc, tpc.descripcion, ctb.id_cuenta1, ctb.id_cuenta2, nomen.ccodcta AS cuenta1, nomen1.ccodcta AS cuenta2, nomen.cdescrip AS nom1, nomen1.cdescrip AS nom2  FROM aprctb AS ctb 
                                        INNER JOIN aprtip AS tip ON ctb.id_tipo_cuenta = tip.id_tipo
                                        INNER JOIN aprtipdoc AS tpc ON ctb.id_tipo_doc = tpc.id
                                        INNER JOIN ctb_nomenclatura AS nomen ON ctb.id_cuenta1 = nomen.id
                                        INNER JOIN ctb_nomenclatura AS nomen1 ON ctb.id_cuenta2 = nomen1.id");
                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id_ctb = $row["id"];
                                                $id_tipo_cuenta = $row["id_tipo_cuenta"];
                                                $nombre = $row["nombre"];
                                                $id_tipo_doc = $row["id_tipo_doc"];
                                                $descripcion = $row["descripcion"];
                                                $id_cuenta1 = $row["id_cuenta1"];
                                                $cuenta1 = $row["cuenta1"];
                                                $nom1 = $row["nom1"];
                                                $id_cuenta2 = $row["id_cuenta2"];
                                                $cuenta2 = $row["cuenta2"];
                                                $nom2 = $row["nom2"];

                                                echo '<tr> <td>' . $nombre . '</td>';
                                                echo '<td>' . $descripcion . '</td>';
                                                echo '<td>' . $cuenta1 . '</td>';
                                                echo '<td>' . $cuenta2 . '</td>';
                                                echo '<td>
                                                <button type="button" class="btn btn-default" title="Editar" onclick="printdiv2(`#cuadro`,[' . $id_ctb . ',' . $id_tipo_cuenta . ',' . $id_tipo_doc . ',' . $id_cuenta1 . ',' . $cuenta1 . ',` - ' . $nom1 . '`,' . $id_cuenta2 . ',' . $cuenta2 . ',` - ' . $nom2 . '`])"> <i class="fa-solid fa-pen"></i></button>
                                                <button type="button" class="btn btn-default" title="Eliminar" onclick="eliminar(' . $id_ctb . ',`crud_aportaciones`,`0`,`delete_aprt_cuentas_contables`)"> <i class="fa-solid fa-trash-can"></i></button>
                                             </td></tr> ';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        //Datatable para parametrizacion
                        $(document).ready(function() {
                            convertir_tabla_a_datatable("table_parametrizacion");
                        });
                    </script>

                    <div class="container contenedort">
                        <div class="row mb-3 mt-2">
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <select class="form-select" id="tip_cuenta" aria-label="Tipos de cuenta">
                                        <option selected value="0">Seleccionar tipo de cuenta</option>
                                        <?php
                                        $tipdoc = mysqli_query($conexion, "SELECT * FROM `aprtip`");
                                        $selected = "";
                                        while ($tip = mysqli_fetch_array($tipdoc)) {
                                            ($tip['id_tipo'] == $id[1]) ? $selected = "selected" : $selected = "";
                                            echo '<option value="' . $tip['id_tipo'] . '"' . $selected . '>' . $tip['ccodtip'] . ' - ' . ($tip['nombre']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="tip_cuenta">Tipo de cuenta</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <select class="form-select" id="tip_doc" aria-label="Tipos de cuenta">
                                        <option selected value="0">Seleccionar tipo de documento</option>
                                        <?php
                                        $tipdoc = mysqli_query($conexion, "SELECT * FROM `aprtipdoc`");
                                        $selected = "";
                                        while ($tip = mysqli_fetch_array($tipdoc)) {
                                            ($tip['id'] == $id[2]) ? $selected = "selected" : $selected = "";
                                            echo '<option value="' . $tip['id'] . '"' . $selected . '>' . ($tip['descripcion']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="tip_doc">Tipo de documento</label>
                                </div>

                            </div>
                        </div>
                        <!--Aho_0_BeneAho Nombre-->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group" id="div_cuenta1">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="text_cuenta1" readonly value="<?php echo $id[4] . $id[5]; ?>">
                                        <input type="text" class="form-control" id="id_hidden1" hidden value="<?php echo $id[3]; ?>" readonly>
                                        <label class="text-primary" for="text_cuenta1"><i class="fa-solid fa-file-invoice"></i>Cuenta 1</label>
                                    </div>
                                    <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', '#id_modal_hidden', 'id_hidden1,text_cuenta1/A,2-3/-')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group" id="div_cuenta2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="text_cuenta2" readonly value="<?php echo $id[7] . $id[8]; ?>">
                                        <input type="text" class="form-control" id="id_hidden2" hidden value="<?php echo $id[6]; ?>" readonly>
                                        <label class="text-primary" for="text_cuenta2"><i class="fa-solid fa-file-invoice"></i>Cuenta 2</label>
                                    </div>
                                    <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', '#id_modal_hidden', 'id_hidden2,text_cuenta2/A,2-3/-')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                            <button type="button" class="<?php echo ($id[0] == 0) ? "btn btn-outline-success" : "btn btn-outline-primary" ?>" onclick="<?php echo ($id[0] == 0) ? ("obtiene([`id_hidden1`,`id_hidden2`],[`tip_cuenta`,`tip_doc`],[],  `create_aprt_cuentas_contables`,`0`,['$codusu'])")
                                                                                                                                                            : ("obtiene([`id_hidden1`,`id_hidden2`,`text_cuenta1`,`text_cuenta2`],[`tip_cuenta`,`tip_doc`],[],  `update_aprt_cuentas_contables`,`0`,['$codusu','$id[0]'])") ?>">
                                <i class="<?php echo ($id[0] == 0) ? "fa-solid fa-plus" : "fa-solid fa-pen-to-square" ?>"></i><?php echo ($id[0] == 0) ? "Agregar" : "Actualizar" ?>
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
            </div>

        <?php
        }
        break;
    case 'Parametrizacion_aprt_interes': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
            if ($id == 0) {
                $id = ['0', '0', '0', "", "", "", "", "", ""];
            }
        ?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <div class="text" style="text-align:center">PARAMETRIZACIÓN DE ACREDITACIÓN Y PROVISIÓN</div>
            <div class="card">
                <input type="text" id="file" value="APRT_6" style="display: none;">
                <input type="text" id="condi" value="Parametrizacion_aprt_interes" style="display: none;">
                <div class="card-header">Parametrizacion de Acreditación y Provisión</div>
                <div class="card-body">
                    <div class="container contenedort">
                        <div class="row mt-2 pb-2">
                            <div class="col">
                                <div class="table-responsive">
                                    <table id="table_parametrizacion" class="table table-hover table-border">
                                        <thead class="text-light table-head-aprt">
                                            <tr>
                                                <th>Tipo de cuenta</th>
                                                <th>Operación</th>
                                                <th>Cuenta 1</th>
                                                <th>Cuenta 2</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tb_cuerpo_parametrizacion">
                                            <?php
                                            $consulta = mysqli_query($conexion, "SELECT prt.id, prt.id_tipo_cuenta, tip.nombre, prt.id_descript_intere, inte.nombre AS nombre_inte, prt.id_cuenta1, prt.id_cuenta2, 
                                        nomen.ccodcta AS cuenta1, nomen1.ccodcta AS cuenta2, nomen.cdescrip AS nom1, nomen1.cdescrip AS nom2
                                        FROM aprparaintere AS prt 
                                        INNER JOIN aprtip AS tip ON prt.id_tipo_cuenta = tip.id_tipo
                                        INNER JOIN ctb_descript_intereses AS inte ON prt.id_descript_intere = inte.id
                                        INNER JOIN tb_usuario AS us ON prt.id_usuario = us.id_usu
                                        INNER JOIN ctb_nomenclatura AS nomen ON prt.id_cuenta1 = nomen.id
                                        INNER JOIN ctb_nomenclatura AS nomen1 ON prt.id_cuenta2 = nomen1.id");
                                            // WHERE us.agencia = '$agencia'
                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id_int = $row["id"];
                                                $id_tipo_cuenta = $row["id_tipo_cuenta"];
                                                $nombre = $row["nombre"];
                                                $id_descript_intere = $row["id_descript_intere"];
                                                $nombre_inte = $row["nombre_inte"];
                                                $id_cuenta1 = $row["id_cuenta1"];
                                                $cuenta1 = $row["cuenta1"];
                                                $nom1 = $row["nom1"];
                                                $id_cuenta2 = $row["id_cuenta2"];
                                                $cuenta2 = $row["cuenta2"];
                                                $nom2 = $row["nom2"];

                                                $params = json_encode([
                                                    $id_int,
                                                    $id_tipo_cuenta,
                                                    $id_descript_intere,
                                                    $id_cuenta1,
                                                    $cuenta1,
                                                    " - " . $nom1,
                                                    $id_cuenta2,
                                                    $cuenta2,
                                                    " - " . $nom2
                                                ]);

                                                echo '<tr> <td>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</td>';
                                                echo '<td>' . htmlspecialchars($nombre_inte, ENT_QUOTES, 'UTF-8') . '</td>';
                                                echo '<td>' . htmlspecialchars($cuenta1, ENT_QUOTES, 'UTF-8') . '</td>';
                                                echo '<td>' . htmlspecialchars($cuenta2, ENT_QUOTES, 'UTF-8') . '</td>';
                                                echo '<td>
                                                <button type="button" class="btn btn-default" title="Editar" onclick=\'printdiv2("#cuadro", ' . $params . ')\'> <i class="fa-solid fa-pen"></i></button>
                                                <button type="button" class="btn btn-default" title="Eliminar" onclick="eliminar(' . $id_int . ',`crud_ahorro`,`0`,`delete_aho_cuentas_intereses`)"> <i class="fa-solid fa-trash-can"></i></button>
                                            </td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        //Datatable para parametrizacion
                        $(document).ready(function() {
                            convertir_tabla_a_datatable("table_parametrizacion");
                        });
                    </script>

                    <div class="container contenedort">
                        <div class="row mb-3 mt-2">
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <select class="form-select" id="tip_cuenta" aria-label="Tipos de cuenta">
                                        <option selected value="0">Seleccionar tipo de cuenta</option>
                                        <?php
                                        $tipdoc = mysqli_query($conexion, "SELECT * FROM `aprtip`");
                                        $selected = "";
                                        while ($tip = mysqli_fetch_array($tipdoc)) {
                                            ($tip['id_tipo'] == $id[1]) ? $selected = "selected" : $selected = "";
                                            echo '<option value="' . $tip['id_tipo'] . '"' . $selected . '>' . $tip['ccodtip'] . ' - ' . ($tip['nombre']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="tip_cuenta">Tipo de cuenta</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <select class="form-select" id="tip_operacion" aria-label="Tipos de cuenta">
                                        <option selected value="0">Seleccionar tipo de operación</option>
                                        <?php
                                        $tip_op = mysqli_query($conexion, "SELECT * FROM `ctb_descript_intereses`");
                                        $selected = "";
                                        while ($tip = mysqli_fetch_array($tip_op)) {
                                            ($tip['id'] == $id[2]) ? $selected = "selected" : $selected = "";
                                            echo '<option value="' . $tip['id'] . '"' . $selected . '>' . ($tip['nombre']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="tip_operacion">Tipo de operación</label>
                                </div>

                            </div>
                        </div>
                        <!--Aho_0_BeneAho Nombre-->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group" id="div_cuenta1">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="text_cuenta1" readonly value="<?php echo $id[4] . $id[5]; ?>">
                                        <input type="text" class="form-control" id="id_hidden1" hidden value="<?php echo $id[3]; ?>" readonly>
                                        <label class="text-primary" for="text_cuenta1"><i class="fa-solid fa-file-invoice"></i>Cuenta 1 - Debe</label>
                                    </div>
                                    <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', '#id_modal_hidden', 'id_hidden1,text_cuenta1/A,2-3/-')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group" id="div_cuenta2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="text_cuenta2" readonly value="<?php echo $id[7] . $id[8]; ?>">
                                        <input type="text" class="form-control" id="id_hidden2" hidden value="<?php echo $id[6]; ?>" readonly>
                                        <label class="text-primary" for="text_cuenta2"><i class="fa-solid fa-file-invoice"></i>Cuenta 2 - Haber</label>
                                    </div>
                                    <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', '#id_modal_hidden', 'id_hidden2,text_cuenta2/A,2-3/-')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                            <button type="button" class="<?php echo ($id[0] == 0) ? "btn btn-outline-success" : "btn btn-outline-primary" ?>" onclick="<?php echo ($id[0] == 0) ? ("obtiene([`id_hidden1`,`id_hidden2`],[`tip_cuenta`,`tip_operacion`],[],  `create_aprt_cuentas_intereses`,`0`,['$codusu'])") : ("obtiene([`id_hidden1`,`id_hidden2`,`text_cuenta1`,`text_cuenta2`],[`tip_cuenta`,`tip_operacion`],[],  `update_aprt_cuentas_intereses`,`0`,['$codusu','$id[0]'])") ?>">
                                <i class="<?php echo ($id[0] == 0) ? "fa-solid fa-plus" : "fa-solid fa-pen-to-square" ?>"></i><?php echo ($id[0] == 0) ? "Agregar" : "Actualizar" ?>
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
            </div>
        <?php
        }
        break;
    case 'calculo_interes_aprt': {
            $agencia = $_SESSION['agencia'];
            $codusu = $_SESSION['id'];
            try {
                $database->openConnection();
                $tiposcuentas = $database->selectColumns('aprtip', ['ccodtip', 'nombre', 'cdescripcion'], '1=?', [1]);

                $calculos = $database->selectAll('aprinteredetalle');
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            } finally {
                $database->closeConnection();
            }
        ?>
            <input type="text" id="file" value="APRT_6" style="display: none;">
            <input type="text" id="condi" value="calculo_interes_aprt" style="display: none;">
            <div class="text" style="text-align:center">INTERESES MANUALES DE APRT</div>
            <div class="card">
                <div class="card-header">Intereses Manuales</div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col">
                            <div class="card">
                                <div class="card-header">Filtro por tipos de cuentas</div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-sm-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="r_cuenta" id="all" value="all" checked onclick="activar_select_cuentas(this,true, 'tipcuenta')">
                                                <label for="all" class="form-check-label">Todo </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="r_cuenta" id="any" value="any" onclick="activar_select_cuentas(this,false, 'tipcuenta')">
                                                <label for="any" class="form-check-label"> Por Tipo de Cuenta</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <div>
                                                <span class="input-group-addon col-2">Tipo de Cuenta</span>
                                                <select class="form-select" aria-label="Default select example" id="tipcuenta" disabled>
                                                    <option value="0" selected disabled>Seleccionar tipo de cuenta</option>
                                                    <?php
                                                    foreach ($tiposcuentas as $tip) {
                                                        echo '<option value="' . $tip['ccodtip'] . '">' . $tip['ccodtip'] . ' - ' . $tip['nombre'] . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card" style="height: 100%;">
                                <div class="card-header">Filtro por fechas</div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-sm-6">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="fechaInicio" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="fechaFinal" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!--Botones-->
                    <div class="row justify-items-md-center mb-3">
                        <div class="col align-items-center" id="modal_footer">
                            <button type="button" id="btnSave" class="btn btn-outline-success" onclick="obtiene([`fechaInicio`,`fechaFinal`],[`tipcuenta`],[`r_cuenta`],`procesar_interes_aprt`,`0`,[])">
                                <i class="fa-solid fa-file-export"></i> Procesar
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>

                    <!-- tabla -->
                    <div class="container contenedort" style="padding: 10px 8px 10px 8px !important;">
                        <div class="table-responsive">
                            <table id="table_id2" class="table table-hover table-border">
                                <thead class="text-light table-head-aprt" style="font-size: 0.8rem;">
                                    <tr>
                                        <th>N</th>
                                        <th>Fecha y hora</th>
                                        <th>Rango</th>
                                        <th>Tipo</th>
                                        <th>Total inte.</th>
                                        <th>Total isr</th>
                                        <th>Acreditado</th>
                                        <th>Partida Prov.</th>
                                        <th>Reportes</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="categoria_tb">
                                    <?php
                                    $check = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="40" height="40" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00b341" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <circle cx="12" cy="12" r="9" />
                                                <path d="M9 12l2 2l4 -4" />
                                            </svg>';
                                    $enabled = '<svg width="40" height="40" viewBox="0 0 512 512" style="color:currentColor" xmlns="http://www.w3.org/2000/svg" class="h-full w-full"><rect width="484" height="484" x="14" y="14" rx="82" fill="transparent" stroke="transparent" stroke-width="28" stroke-opacity="100%" paint-order="stroke"></rect><svg width="512px" height="512px" viewBox="0 0 64 64" fill="currentColor" x="0" y="0" role="img" style="display:inline-block;vertical-align:middle" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path fill="currentColor" d="M32 0C14 0 0 14 0 32c0 21 19 30 22 30c2 0 2-1 2-2v-5c-7 2-10-2-11-5c0 0 0-1-2-3c-1-1-5-3-1-3c3 0 5 4 5 4c3 4 7 3 9 2c0-2 2-4 2-4c-8-1-14-4-14-15c0-4 1-7 3-9c0 0-2-4 0-9c0 0 5 0 9 4c3-2 13-2 16 0c4-4 9-4 9-4c2 7 0 9 0 9c2 2 3 5 3 9c0 11-7 14-14 15c1 1 2 3 2 6v8c0 1 0 2 2 2c3 0 22-9 22-30C64 14 50 0 32 0Z"/></g></svg></svg>';
                                    foreach ($calculos as $row) {
                                        $idcal = $row["id"];
                                        $fecha = $row["fecmod"];
                                        $intereses = number_format((float)$row["int_total"], 2);
                                        $isrcal = number_format((float)($row["isr_total"]), 2);
                                        $rango = $row["rango"];
                                        $tipcuenta = $row["tipo"];
                                        $partida = $row["partida"];
                                        $acreditado = $row["acreditado"];
                                        $usuario = $row["codusu"];
                                        $fechacorte = $row["fechacorte"];

                                        $acre = ($acreditado == 1) ? $check : (($partida == 1) ? $enabled : '<button type="button" class="btn btn-outline-secondary" style="padding: 6px 9px !important;" title="Acreditacr" onclick="obtiene([`fechaInicio`],[`tipcuenta`],[`r_cuenta`],`acreditar_intereses`,`0`,[' . $idcal . ',`' . $fechacorte . '`,`' . $agencia . '`,' . $codusu . ',`' . $rango . '`])">
                                            <i class="fa-solid fa-money-bill-transfer"></i>
                                        </button>');

                                        $part = ($partida == 1) ? $check : (($acreditado == 1) ? $enabled : '<button type="button" class="btn btn-outline-primary" title="Partida de provision" onclick="obtiene([`fechaInicio`],[`tipcuenta`],[`r_cuenta`],`partida_aprov_intereses`,`0`,[' . $idcal . ',`' . $fechacorte . '`,' . $codusu . ',`' . $rango . '`])">
                                            <i class="fa-solid fa-file-invoice-dollar"></i>
                                        </button>');

                                        $buttondeletecalculo = ($acreditado == 1 || $partida == 1) ? "" : '<button type="button" class="btn btn-outline-danger" title="Eliminar Calculo" onclick="eliminar(' . $idcal . ', `crud_aportaciones`, `0`, `delete_calculo_interes`)"> <i class="fa-solid fa-trash-can"></i></button>';

                                        echo '<tr>
                                            <td>' . $idcal . ' </td>
                                            <td>' . $fecha . ' </td>
                                            <td>' . $rango . ' </td>
                                            <td>' . $tipcuenta . ' </td>
                                            <td>' . $intereses . ' </td>
                                            <td>' . $isrcal . '</td>
                                            <td align="center">' . $acre . '
                                            </td>
                                            <td align="center">' . $part . '
                                             </td>
                                            <td> <button type="button" class="btn btn-outline-success" title="Reporte Excel" onclick="reportes_aportaciones([`reportes_aportaciones`, `intereses_aprt`, `excel`, `xlsx`,`' . date("Y-m-d") . '`,' . $idcal . ',])">
                                                    <i class="fa-solid fa-file-excel"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" style="padding: 6px 10px !important;" title="Reporte pdf" onclick="reportes_aportaciones([`reportes_aportaciones`, `intereses_aprt`, `pdf`, `pdf`, `' . date("Y-m-d") . '`,' . $idcal . '])">
                                                    <i class="fa-solid fa-file-pdf"></i>
                                                </button>
                                            </td>
                                            <td>' . $buttondeletecalculo . '</td>
                                        </tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <script>
                    //Datatable para parametrizacion
                    $(document).ready(function() {
                        convertir_tabla_a_datatable("table_id2");
                    });
                </script>

            </div>

            </div>
        <?php
        }
        break;
    case 'List_mov_recibos_aprt':
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
            $stmt->bind_param("ss", $nuew, $codusu);
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
        <input type="text" id="file" value="APRT_6" style="display: none;">
        <input type="text" id="condi" value="List_mov_recibos_aprt" style="display: none;">
        <div class="text" style="text-align:center">RECIBOS DE APORTACIONES</div>
        <div class="card">
            <div class="card-header">Recibos de aportaciones</div>
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
                        <table id="tabla_recibos_aportaciones" class="table table-hover table-border nowrap" style="width:100%">
                            <thead class="text-light table-head-aprt" style="font-size: 0.8rem;">
                                <tr>
                                    <th>ID</th>
                                    <th>No. Recibo</th>
                                    <th>No. Cuenta</th>
                                    <th>Razon</th>
                                    <th>Tipo documento</th>
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
                    $("#tabla_recibos_aportaciones").DataTable({
                        "processing": true,
                        "serverSide": true,
                        "sAjaxSource": "../src/server_side/lista_recibos_aportaciones.php",
                        columns: [{
                                data: [0]
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
                                data: [4]
                            },
                            {
                                data: [5]
                            },
                            {
                                data: [6]
                            },
                            {
                                data: [0],
                                render: function(data, type, row) {
                                    imp = '';
                                    imp1 = '';
                                    imp2 = '';
                                    if (row[8] == "1") {
                                        imp1 = `<button type="button" class="btn btn-primary btn-sm me-1 ms-1" title="Eliminacion recibo" onclick="eliminar('${row[0]}', 'crud_aportaciones', '0', 'eliminacion_recibo');"><i class="fa-solid fa-trash"></i></button>`;
                                        imp2 = `<button type="button" class="btn btn-warning btn-sm" title="Edicion" onclick="modal_edit_recibo('${row[0]}','${row[1]}', '${row[2]}','<?= $codusu ?>')"><i class="fa-solid fa-pen-to-square"></i></button>`;
                                    }
                                    imp = `<button type="button" class="btn btn-secondary btn-sm" title="Reimpresion" onclick="obtiene([],[],[],'reimpresion_recibo','0',['${row[0]}','<?= $codusu ?>'])"><i class="fa-solid fa-print"></i></button>`;
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
        <!-- MODAL PARA EDICION DE RECIBO -->
        <div class="modal fade" id="edicion_recibo" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog  modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5">Edición de recibo</h1>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-2">
                            <div class="col-6">
                                <!-- titulo -->
                                <span class="input-group-addon col-8">No. Recibo anterior</span>
                                <div class="input-group">
                                    <input type="text" class="form-control " id="id_recibo" readonly hidden>
                                    <input type="text" class="form-control " id="id_codusu" readonly hidden>
                                    <input type="text" class="form-control " id="numdoc_modal_recibo_ant" readonly>
                                </div>
                            </div>
                            <div class="col-6">
                                <!-- titulo -->
                                <span class="input-group-addon col-8">Cuenta de aportación</span>
                                <div class="input-group">
                                    <input type="text" class="form-control " id="ccodaport_recibo" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <span class="input-group-addon col-8">Nuevo número de certificado</span>
                                <input type="text" aria-label="Certificado" id="numdoc_modal_recibo" class="form-control  col" placeholder="" required>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" id="cancelar_ben" onclick="obtiene(['id_recibo','numdoc_modal_recibo_ant','numdoc_modal_recibo','id_codusu'], [], [], 'edicion_recibo', '0', ['0'])">Guardar</button>
                        <button type="button" class="btn btn-secondary" id="cancelar_ben" onclick="cancelar_edit_recibo()">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'interesmanualaprt':
        $account = $_POST["xtra"];
        $hoy = date("Y-m-d");
        $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name, numfront,numdors, tip.nombre tipoNombre,
                    IFNULL((SELECT MAX(`numlinea`) FROM aprmov WHERE ccodaport=cta.ccodaport AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimonum,
                    IFNULL((SELECT MAX(`correlativo`) FROM aprmov WHERE ccodaport=cta.ccodaport AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimocorrel,
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

            $data = $database->getAllResults($query, [$hoy, $account]);
            if (empty($data)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de aportaciones, verifique el número de cuenta o si el cliente esta activo");
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

        include_once  "../../src/cris_modales/mdls_aport_new.php";
    ?>
        <div class="card">
            <input type="text" id="file" value="APRT_6" style="display: none;">
            <input type="text" id="condi" value="interesmanualaprt" style="display: none;">
            <div class="card-header">Acreditacion de interés manual individual</div>

            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="container contenedort">
                    <div class="row">
                        <div class="col-lg-8 col-sm-6 col-md-8">
                            <div class="row">
                                <div class="col-sm-8 col-md-8 col-lg-8">
                                    <span class="input-group-addon col-8">Cuenta de Aportaciones</span>
                                    <input type="text" class="form-control " id="ccodaport" required placeholder="   -   -  -  "
                                        value="<?= $account ?? '' ?>">
                                </div>
                                <div class="col-sm-4 col-md-4 col-lg-4">
                                    <br>
                                    <button class="btn btn-outline-secondary" type="button" id="button-addon1"
                                        title="Aplicar cuenta ingresada" onclick="aplicarcod('ccodaport')">
                                        <i class="fa fa-check-to-slot"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" id="button-addon1" title="Buscar cuenta"
                                        data-bs-toggle="modal" data-bs-target="#findaportcta">
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
                <div class="container contenedort" style="display: block;">
                    <div class="row mb-3">
                        <div class="col-sm-5 col-md-4 col-lg-4">
                            <span class="input-group-addon col-8">Fecha inicio</span>
                            <input type="date" class="form-control " id="fecini" value="<?= $hoy ?>">
                        </div>
                        <div class="col-sm-5 col-md-4 col-lg-4">
                            <span class="input-group-addon col-8">Fecha fin</span>
                            <input type="date" class="form-control " id="fecfin" value="<?= $hoy ?>">
                        </div>
                        <div class="col-sm-2 col-md-4 col-lg-2">
                            <br>
                            <button class="btn btn-outline-secondary" type="button" id="button-addon1" title="Calcular interés"
                                onclick="document.getElementById('table_container').style.display = 'none'; obtiene(['<?= $csrf->getTokenName() ?>', 'fecini', 'fecfin'], [], [], 'procesCalculoIndi', '0', ['<?= htmlspecialchars($secureID->encrypt($account)) ?>'],loadDataProcess);">
                                <i class="fa fa-check-to-slot"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row" style="display: none;" id="table_container">
                        <table id="table_id2" class="table table-hover">
                            <thead style="font-size: 0.8rem;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                    <th>Doc</th>
                                    <th>Saldo</th>
                                    <th>SaldoAnt</th>
                                    <th>Dias</th>
                                    <th>Int</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="container contenedort">
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <span class="input-group-addon col-8">Interés</span>
                            <input type="number" step="0.01" class="form-control" id="monint" required placeholder="0.00" min="0.01">
                        </div>
                        <div class="col-sm-4">
                            <span class="input-group-addon col-8">Impuesto</span>
                            <input type="number" step="0.01" class="form-control" id="monipf" placeholder="0.00" min="0.00">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-8 col-md-6 col-lg-4">
                            <span class="input-group-addon col-8">Fecha de acreditacion</span>
                            <input type="date" class="form-control " id="dfecope" value="<?= $hoy ?>">
                        </div>
                    </div>
                </div>
                <?php echo $csrf->getTokenField(); ?>
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
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            function confirmSave() {
                var cantidad = document.getElementById("monint").value;
                obtiene(['<?= $csrf->getTokenName() ?>', 'dfecope', 'monint', 'monipf'], [], [], 'acreditaindi', '0', ['<?= htmlspecialchars($secureID->encrypt($account)) ?>'], 'null', "Deseas acreditar la cantidad de Q." + cantidad + "?");
            }

            function loadDataProcess(data) {
                // console.log(data);
                var table = document.getElementById("table_id2");
                var tbody = table.getElementsByTagName("tbody")[0];
                tbody.innerHTML = "";
                data['data'].forEach((element, index) => {
                    var row = tbody.insertRow();
                    row.innerHTML = `
                        <td>${element.dfecope}</td>
                        <td>${element.ctipope}</td>
                        <td>${element.monto}</td>
                        <td>${element.cnumdoc}</td>
                        <td>${element.saldo}</td>
                        <td>${element.saldoant}</td>
                        <td>${element.dias}</td>
                        <td>${element.interescal}</td>
                    `;
                });
                document.getElementById("table_container").style.display = "block";
                document.getElementById("monint").value = data['totalInteres'];
                document.getElementById("monipf").value = data['totalImpuesto'];
                document.getElementById("dfecope").value = data['fechaFin'];
            }
        </script>
    <?php
        break;
    case 'dea_ctb_aprt':
        $codusu = $_SESSION['id'];
        $id = $_POST["xtra"];

        try {
            // Consulta para obtener cuentas de aportaciones con saldo 0 usando la función para calcular saldo
            $query = "SELECT 
                            cta.ccodaport, 
                            cta.ccodcli, 
                            cli.short_name AS client_name, 
                            cta.nlibreta, 
                            cta.estado, 
                            DATE_FORMAT(cta.fecha_apertura, '%Y-%m-%d') as fecha_apertura, 
                            calcular_saldo_apr_tipcuenta(cta.ccodaport, CURDATE()) as saldo
                          FROM aprcta cta
                          INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                          WHERE calcular_saldo_apr_tipcuenta(cta.ccodaport, CURDATE()) = 0
                          ORDER BY cta.ccodaport ASC";

            $result = mysqli_query($conexion, $query);
            if (!$result) {
                throw new Exception("Error en la consulta: " . mysqli_error($conexion));
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            break;
        }
    ?>
        <div id="contenido_cuentas">
            <div class="text" style="text-align:center">ACTIVACION Y DESACTIVACION MASIVA DE CUENTAS DE APORTACIONES</div>
            <input type="text" id="condi" value="dea_ctb_aprt" style="display: none;">
            <input type="text" id="file" value="APRT_6" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Activación y Desactivación de Cuentas con Saldo 0</h5>
                        <div>
                            <button class="btn btn-success" onclick="accionMasiva('activar')">
                                <i class="fas fa-check-circle"></i> Activar Seleccionadas
                            </button>
                            <button class="btn btn-danger" onclick="accionMasiva('desactivar')">
                                <i class="fas fa-times-circle"></i> Desactivar Seleccionadas
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Estado</label>
                                <select class="form-select" id="filtro_estado">
                                    <option value="">Todos</option>
                                    <option value="A">Activos</option>
                                    <option value="B">Inactivos</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha Desde</label>
                                <input type="date" class="form-control" id="fecha_desde">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Fecha Hasta</label>
                                <input type="date" class="form-control" id="fecha_hasta">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button class="btn btn-primary w-100" onclick="aplicarFiltros()">
                                    <i class="fas fa-filter"></i> Aplicar Filtros
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="tabla_cuentas_saldo_cero" class="table table-hover table-bordered" style="width: 100%;">
                            <thead class="text-light table-head-aprt" style="font-size: 0.8rem;">
                                <tr>
                                    <th>
                                        <input type="checkbox" id="seleccionar_todos" onchange="seleccionarTodos(this)">
                                    </th>
                                    <th>ID Cuenta</th>
                                    <th>Código Cliente</th>
                                    <th>Nombre</th>
                                    <th>Libreta</th>
                                    <th>Estado</th>
                                    <th>Fecha Apertura</th>
                                    <th>Saldo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $estado_texto = ($row['estado'] == 'A') ? 'Activo' : 'Inactivo';
                                    $estado_clase = ($row['estado'] == 'A') ? 'text-success' : 'text-danger';
                                    $btn_class = ($row['estado'] == 'A') ? 'btn-danger' : 'btn-success';
                                    $btn_text = ($row['estado'] == 'A') ? 'Desactivar' : 'Activar';
                                    $btn_action = ($row['estado'] == 'A') ? 'desactivarCuenta' : 'activarCuenta';

                                    echo "<tr>";
                                    echo "<td><input type='checkbox' class='cuenta-checkbox' value='{$row['ccodaport']}'></td>";
                                    echo "<td>{$row['ccodaport']}</td>";
                                    echo "<td>{$row['ccodcli']}</td>";
                                    echo "<td>{$row['client_name']}</td>";
                                    echo "<td>{$row['nlibreta']}</td>";
                                    echo "<td class='{$estado_clase}'>{$estado_texto}</td>";
                                    echo "<td>{$row['fecha_apertura']}</td>";
                                    echo "<td>{$row['saldo']}</td>";
                                    echo "<td><button class='btn {$btn_class} btn-sm' onclick='{$btn_action}(\"{$row['ccodaport']}\")'>{$btn_text}</button></td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                var tablaCuentas;

                function initializeDataTable() {
                    if ($.fn.DataTable.isDataTable('#tabla_cuentas_saldo_cero')) {
                        $('#tabla_cuentas_saldo_cero').DataTable().destroy();
                    }

                    tablaCuentas = $('#tabla_cuentas_saldo_cero').DataTable({
                        "dom": "lfrtip",
                        "searching": true,
                        "language": {
                            "lengthMenu": "Mostrar _MENU_ registros",
                            "zeroRecords": "No se encontraron cuentas con saldo 0",
                            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                            "infoEmpty": "No hay registros disponibles",
                            "infoFiltered": "(filtrado de _MAX_ registros totales)",
                            "search": "Buscar:",
                            "paginate": {
                                "first": "Primero",
                                "last": "Último",
                                "next": "Siguiente",
                                "previous": "Anterior"
                            }
                        },
                        "order": [
                            [1, "asc"]
                        ],
                        "pageLength": 10,
                        "columnDefs": [{
                            "targets": [0, 8],
                            "orderable": false
                        }]
                    });
                }

                function recargarContenido() {
                    $.ajax({
                        url: '../../views/APRT/APRT_6.php',
                        type: 'POST',
                        data: {
                            condi: 'dea_ctb_aprt',
                            xtra: ''
                        },
                        success: function(response) {
                            $('#contenido_cuentas').html(response);
                            initializeDataTable();
                            const estado = $('#filtro_estado').val();
                            const fechaDesde = $('#fecha_desde').val();
                            const fechaHasta = $('#fecha_hasta').val();
                            if (estado || (fechaDesde && fechaHasta)) {
                                aplicarFiltros();
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error al recargar el contenido'
                            });
                        }
                    });
                }

                function aplicarFiltros() {
                    const estado = $('#filtro_estado').val();
                    const fechaDesde = $('#fecha_desde').val();
                    const fechaHasta = $('#fecha_hasta').val();

                    $.fn.dataTable.ext.search = [];

                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        let valido = true;

                        if (estado) {
                            const estadoFila = data[5];
                            if (estado === 'A' && !estadoFila.includes('Activo')) {
                                valido = false;
                            }
                            if (estado === 'B' && !estadoFila.includes('Inactivo')) {
                                valido = false;
                            }
                        }

                        if (fechaDesde && fechaHasta) {
                            const fecha = new Date(data[6]);
                            const min = new Date(fechaDesde);
                            const max = new Date(fechaHasta);
                            max.setHours(23, 59, 59);

                            if (fecha < min || fecha > max) {
                                valido = false;
                            }
                        }
                        return valido;
                    });
                    tablaCuentas.draw();
                }

                function activarCuenta(ccodaport) {
                    accionCuenta(ccodaport, 'activar');
                }

                function desactivarCuenta(ccodaport) {
                    accionCuenta(ccodaport, 'desactivar');
                }

                function accionCuenta(ccodaport, accion) {
                    const textoAccion = (accion === 'activar') ? 'activar' : 'desactivar';

                    Swal.fire({
                        title: '¿Está seguro?',
                        text: `¿Desea ${textoAccion} esta cuenta?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '../src/server_side/actualizar_cuentas_aprt_masivo.php',
                                type: 'POST',
                                data: {
                                    cuentas: [ccodaport],
                                    accion: accion,
                                    usuario: '<?php echo $codusu; ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Éxito',
                                            text: response.message
                                        }).then(() => {
                                            recargarContenido();
                                        });
                                    } else {
                                        recargarContenido();
                                    }
                                },
                                error: function() {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'Error en la comunicación con el servidor'
                                    });
                                }
                            });
                        }
                    });
                }

                function accionMasiva(accion) {
                    const cuentasSeleccionadas = $('.cuenta-checkbox:checked').map(function() {
                        return $(this).val();
                    }).get();

                    if (cuentasSeleccionadas.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atención',
                            text: 'Por favor, seleccione al menos una cuenta'
                        });
                        return;
                    }

                    const textoAccion = (accion === 'activar') ? 'activar' : 'desactivar';

                    Swal.fire({
                        title: '¿Está seguro?',
                        text: `¿Desea ${textoAccion} las ${cuentasSeleccionadas.length} cuentas seleccionadas?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '../src/server_side/actualizar_cuentas_aprt_masivo.php',
                                type: 'POST',
                                data: {
                                    cuentas: cuentasSeleccionadas,
                                    accion: accion,
                                    usuario: '<?php echo $codusu; ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Éxito',
                                            text: response.message
                                        }).then(() => {
                                            recargarContenido();
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: response.message
                                        });
                                    }
                                },
                                error: function() {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'Error en la comunicación con el servidor'
                                    });
                                }
                            });
                        }
                    });
                }

                $(document).ready(function() {
                    initializeDataTable();

                    $('#seleccionar_todos').on('change', function() {
                        const isChecked = $(this).prop('checked');
                        tablaCuentas.rows({
                            'search': 'applied'
                        }).nodes().each(function(node) {
                            $(node).find('.cuenta-checkbox').prop('checked', isChecked);
                        });
                    });

                    $('#filtro_estado, #fecha_desde, #fecha_hasta').on('change', aplicarFiltros);
                });
            </script>
        <?php
        break;
    case 'mod_apr_int':
        $codusu = $_SESSION['id'];
        $id     = $_POST['xtra'];

        // 1) Traer TODAS las cuentas de aportaciones con tasa
        $queryCuentas = "
                SELECT 
                    cta.ccodaport, 
                    cta.ccodcli, 
                    cli.short_name    AS client_name, 
                    cta.nlibreta, 
                    cta.tasa,
                    DATE_FORMAT(cta.fecha_apertura, '%Y-%m-%d') AS fecha_apertura, 
                    calcular_saldo_apr_tipcuenta(cta.ccodaport, CURDATE()) AS saldo
                FROM aprcta cta
                INNER JOIN tb_cliente cli 
                    ON cli.idcod_cliente = cta.ccodcli
                ORDER BY cta.ccodaport ASC
            ";
        $resCuentas = mysqli_query($conexion, $queryCuentas);
        if (!$resCuentas) {
            echo "<div class='alert alert-danger'>Error en la consulta de cuentas: "
                . mysqli_error($conexion) . "</div>";
            break;
        }

        // 2) Traer tipos de cuenta de aportaciones
        $queryTipos = "
                SELECT ccodtip, nombre 
                FROM aprtip 
                WHERE estado = 1 
                ORDER BY ccodtip ASC
            ";
        $resTipos = mysqli_query($conexion, $queryTipos);
        if (!$resTipos) {
            echo "<div class='alert alert-danger'>Error al cargar tipos: "
                . mysqli_error($conexion) . "</div>";
            break;
        }
        ?>
            <div id="contenido_cuentas">
                <div class="text text-center mb-3">
                    MODIFICACIÓN DE INTERÉS MASIVO EN APORTACIONES
                </div>
                <input type="hidden" id="condi" value="mod_apr_int">
                <input type="hidden" id="file" value="APRT_6">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Modificar Tasas Seleccionadas</h5>
                        <button class="btn btn-success" onclick="accionMasiva()">
                            <i class="fas fa-check-circle"></i> Aplicar Nueva Tasa
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label>Tipo de cuenta</label>
                                <select class="form-select" id="filtro_tipo">
                                    <option value="all" selected>Todas</option>
                                    <?php while ($tipo = mysqli_fetch_assoc($resTipos)): ?>
                                        <option value="<?= $tipo['ccodtip'] ?>">
                                            <?= "{$tipo['ccodtip']} - {$tipo['nombre']}" ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Nueva Tasa (%)</label>
                                <input type="number" step="0.01" class="form-control" id="nueva_tasa" placeholder="Ej. 5.25">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="tabla_cuentas_aprt" class="table table-hover table-bordered" style="width:100%">
                                <thead class="table-head-aprt text-light" style="font-size:0.85rem">
                                    <tr>
                                        <th><input type="checkbox" id="seleccionar_todos"></th>
                                        <th>ID Cuenta</th>
                                        <th>Código Cliente</th>
                                        <th>Nombre</th>
                                        <th>Libreta</th>
                                        <th>Tasa Actual</th>
                                        <th>Fecha Apertura</th>
                                        <th>Saldo</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($resCuentas)): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="cuenta-checkbox" value="<?= $row['ccodaport'] ?>">
                                            </td>
                                            <td><?= $row['ccodaport'] ?></td>
                                            <td><?= $row['ccodcli'] ?></td>
                                            <td><?= $row['client_name'] ?></td>
                                            <td><?= $row['nlibreta'] ?></td>
                                            <td><?= $row['tasa'] ?>%</td>
                                            <td><?= $row['fecha_apertura'] ?></td>
                                            <td><?= $row['saldo'] ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-secondary"
                                                    onclick="openModal(
                                        '<?= $row['ccodaport'] ?>',
                                        '<?= $row['tasa'] ?>',
                                        '<?= addslashes($row['client_name']) ?>',
                                        '<?= $row['nlibreta'] ?>',
                                        '<?= $row['saldo'] ?>'
                                      )">
                                                    Editar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal edición individual -->
            <div class="modal fade" id="modalTasa" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-primary shadow-sm">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-edit me-2"></i> Editar Tasa Cuenta
                                <span id="modal_ccodaho"></span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <dl class="row mb-3">
                                <dt class="col-sm-4">Titular</dt>
                                <dd class="col-sm-8" id="modal_client_name"></dd>
                                <dt class="col-sm-4">Libreta</dt>
                                <dd class="col-sm-8" id="modal_nlibreta"></dd>
                                <dt class="col-sm-4">Saldo</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-info text-dark" id="modal_saldo"></span>
                                </dd>
                            </dl>
                            <div class="form-floating">
                                <input type="number" step="0.01" class="form-control" id="modal_tasa" placeholder="Tasa">
                                <label for="modal_tasa">Nueva Tasa (%)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary" onclick="saveTasa()">
                                <i class="fas fa-save me-1"></i> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                let tablaCuentas, cuentaEnModal;

                function initializeDataTable() {
                    if ($.fn.DataTable.isDataTable('#tabla_cuentas_aprt')) {
                        $('#tabla_cuentas_aprt').DataTable().destroy();
                    }
                    tablaCuentas = $('#tabla_cuentas_aprt').DataTable({
                        paging: true,
                        lengthChange: true,
                        pageLength: 10,
                        lengthMenu: [
                            [10, 25, 50, -1],
                            [10, 25, 50, "Todos"]
                        ],
                        dom: 'lfrtip',
                        ordering: true,
                        order: [
                            [1, 'asc']
                        ],
                        columnDefs: [{
                            targets: [0, 8],
                            orderable: false
                        }],
                        language: {
                            lengthMenu: 'Mostrar _MENU_ registros',
                            zeroRecords: 'No hay cuentas',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_',
                            infoEmpty: 'No hay registros',
                            infoFiltered: '(filtrado de _MAX_)',
                            search: 'Buscar:',
                            paginate: {
                                first: 'Primero',
                                last: 'Último',
                                next: 'Siguiente',
                                previous: 'Anterior'
                            }
                        }
                    });
                    aplicarFiltros();
                }

                function aplicarFiltros() {
                    const filtroTipo = $('#filtro_tipo').val();
                    $.fn.dataTable.ext.search = [function(settings, data) {
                        if (filtroTipo === 'all') return true;
                        const tipo = data[1].substring(6, 8);
                        return tipo === filtroTipo;
                    }];
                    tablaCuentas.draw();
                }

                function accionMasiva() {
                    const sel = $('.cuenta-checkbox:checked').map((i, el) => el.value).get();
                    const tasa = parseFloat($('#nueva_tasa').val());
                    if (!sel.length || isNaN(tasa)) {
                        return Swal.fire('Atención', 'Seleccione cuentas y especifique tasa', 'warning');
                    }
                    Swal.fire({
                        title: `¿Aplicar tasa ${tasa}% a ${sel.length} cuentas?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, aplicar'
                    }).then(r => {
                        if (!r.isConfirmed) return;
                        $.post('../src/server_side/actualizar_tasas_aprt_masivo.php', {
                            cuentas: sel,
                            tasa,
                            usuario: '<?= $codusu ?>'
                        }, resp => {
                            Swal.fire(resp.success ? '¡Hecho!' : 'Error', resp.message,
                                    resp.success ? 'success' : 'error')
                                .then(recargarContenido);
                        }, 'json');
                    });
                }

                function openModal(ccodaport, tasa, clientName, libreta, saldo) {
                    cuentaEnModal = ccodaport;
                    $('#modal_ccodaho').text(ccodaport);
                    $('#modal_client_name').text(clientName);
                    $('#modal_nlibreta').text(libreta);
                    $('#modal_saldo').text(saldo);
                    $('#modal_tasa').val(tasa);
                    new bootstrap.Modal($('#modalTasa')).show();
                }

                function saveTasa() {
                    const tasa = parseFloat($('#modal_tasa').val());
                    if (isNaN(tasa)) return Swal.fire('Error', 'Tasa inválida', 'error');
                    $.post('../src/server_side/actualizar_tasas_aprt_individual.php', {
                        cuenta: cuentaEnModal,
                        tasa,
                        usuario: '<?= $codusu ?>'
                    }, resp => {
                        Swal.fire(resp.success ? '¡Guardado!' : 'Error', resp.message,
                                resp.success ? 'success' : 'error')
                            .then(() => {
                                bootstrap.Modal.getInstance($('#modalTasa')).hide();
                                recargarContenido();
                            });
                    }, 'json');
                }

                function recargarContenido() {
                    $.post('../../views/APRT/APRT_6.php', {
                        condi: 'mod_apr_int',
                        xtra: ''
                    }, html => {
                        $('#contenido_cuentas').html(html);
                        initializeDataTable();
                        $('#filtro_tipo').on('change', aplicarFiltros);
                    });
                }

                $(document).ready(() => {
                    initializeDataTable();
                    $('#filtro_tipo').on('change', aplicarFiltros);
                    $('#seleccionar_todos').on('change', function() {
                        tablaCuentas.rows({
                                search: 'applied'
                            }).nodes()
                            .to$().find('.cuenta-checkbox').prop('checked', this.checked);
                    });
                });
            </script>
        <?php
        break;

    case 'mancomunadas':
        $account = $_POST["xtra"];

        $showmensaje = false;
        try {
            if ($account == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta de aportaciones");
            }
            $database->openConnection();

            $query = "SELECT cta.estado, cli.short_name, cli.no_identifica, tip.nombre AS tipo_aportacion
                        FROM aprcta cta 
                        INNER JOIN aprtip tip ON SUBSTR(cta.ccodaport,7,2) = tip.ccodtip
                        INNER JOIN tb_cliente cli ON cta.ccodcli = cli.idcod_cliente
                        WHERE cta.ccodaport=?";

            $data = $database->getAllResults($query, [$account]);
            if (empty($data)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de aportaciones, verifique que el código sea correcto y que el cliente esté activo");
            }
            $dataAccount = $data[0];
            if ($dataAccount['estado'] != "A") {
                $showmensaje = true;
                throw new Exception("Cuenta de aportaciones Inactiva");
            }

            $otrosTitulares = $database->getAllResults(
                "SELECT man.id,man.ccodcli, cli.short_name, cli.no_identifica FROM cli_mancomunadas man
                INNER JOIN tb_cliente cli ON man.ccodcli = cli.idcod_cliente
                WHERE man.ccodaho = ? AND tipo='aportacion' AND man.estado='1' AND cli.estado=1",
                [$account]
            );


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

        include_once  "../../src/cris_modales/mdls_aport_new.php";

        ?>
            <div class="container-fluid py-3">
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="text-center mb-3">
                            <h4 class="fw-bold text-primary">Configuración de Cuentas Mancomunadas</h4>
                        </div>
                        <input type="hidden" id="file" value="APRT_6">
                        <input type="hidden" id="condi" value="mancomunadas">
                        <div class="card shadow-sm">
                            <div class="card-header bg-gradient bg-primary text-white d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-users me-2"></i>Titulares de cuentas de aportaciones</span>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-warning btn-lg fw-bold" type="button" title="Buscar cuenta" data-bs-toggle="modal" data-bs-target="#findaportcta" id="btn_buscar_cuenta">
                                        <i class="fa fa-magnifying-glass"></i> Buscar cuenta
                                    </button>
                                    <button class="btn btn-info btn-lg fw-bold" type="button" title="Ayuda" onclick="help()">
                                        <i class="fa fa-question-circle"></i> Ayuda
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!$status) { ?>
                                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                        <strong>¡Atención!</strong> <?= $mensaje; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php } ?>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-5">
                                        <label class="form-label fw-bold">Cuenta de aportaciones</label>
                                        <div class="bg-light border rounded px-3 py-2 d-flex align-items-center" id="ccodaho" style="min-height: 40px;">
                                            <i class="fa-solid fa-piggy-bank me-2 text-primary"></i>
                                            <span class="fw-semibold"><?= $status ? $account : '<span class="text-muted">No seleccionada</span>' ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Producto</label>
                                        <div class="bg-light border rounded px-3 py-2 d-flex align-items-center" id="tipo_aportacion" style="min-height: 40px;">
                                            <i class="fa-solid fa-box-archive me-2 text-success"></i>
                                            <span class="fw-semibold"><?= $status ? htmlspecialchars($dataAccount['tipo_aportacion']) : '<span class="text-muted">No definido</span>' ?></span>
                                        </div>
                                    </div>
                                    <?php if ($status) { ?>
                                        <div class="col-md-3 d-flex align-items-end" id="div_add_titular">
                                            <button class="btn btn-outline-secondary w-100" type="button" title="Buscar cliente" data-bs-toggle="modal" data-bs-target="#getClientesAll">
                                                <i class="fa fa-magnifying-glass"></i> Agregar titular
                                            </button>
                                        </div>
                                    <?php } ?>
                                </div>
                                <div class="mb-4">
                                    <h5 class="fw-bold text-secondary mb-3">Titulares de la Cuenta</h5>
                                    <div class="table-responsive">
                                        <table id="tiposahorros" class="table table-bordered table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Código Cliente</th>
                                                    <th>Nombre</th>
                                                    <th>Identificación</th>
                                                    <th>Opciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($status) { ?>
                                                    <tr>
                                                        <td>1</td>
                                                        <td><?= $dataAccount['no_identifica'] ?></td>
                                                        <td><?= $dataAccount['short_name'] ?></td>
                                                        <td><?= $dataAccount['no_identifica'] ?></td>
                                                        <td>
                                                            <span class="badge bg-success">Titular Principal</span>
                                                        </td>
                                                    </tr>
                                                <?php } else { ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">No hay datos para mostrar</td>
                                                    </tr>
                                                <?php } ?>
                                                <?php foreach (($otrosTitulares ?? []) as $key => $titular): ?>
                                                    <tr>
                                                        <td><?= $key + 2 ?></td>
                                                        <td><?= $titular['ccodcli'] ?></td>
                                                        <td><?= $titular['short_name'] ?></td>
                                                        <td><?= $titular['no_identifica'] ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-danger btn-sm" title="Eliminar Titular"
                                                                onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'deleteTitularAccount', '<?= htmlspecialchars($account) ?>', ['<?= htmlspecialchars($secureID->encrypt($titular['id'])) ?>'], 'null', '¿Está seguro de eliminar este titular?');">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                        <i class="fa-solid fa-ban"></i> Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?= $csrf->getTokenField(); ?>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="getClientesAll" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h4 class="modal-title"><i class="fa fa-users me-2"></i>Búsqueda de Clientes Naturales y Jurídicos</h4>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table id="tb_clientes_all_mancomunadas" class="table table-striped table-hover table-sm small-font w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre Completo</th>
                                            <th>No. Identificación</th>
                                            <th>Nacimiento</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-end">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                                <i class="fa-solid fa-times"></i> Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                $(document).ready(function() {
                    $("#tb_clientes_all_mancomunadas").DataTable({
                        "processing": true,
                        "serverSide": true,
                        "sAjaxSource": "../src/server_side/clientes_all.php",
                        "columnDefs": [{
                            "data": 0,
                            "targets": 4,
                            render: function(data, type, row) {
                                return `<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="addNewTitular('${data}'); " >Agregar</button>`;
                            }

                        }, ],
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

                function addNewTitular(codcli) {
                    obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'addTitularAccount', '<?= htmlspecialchars($account) ?>', ['<?= htmlspecialchars($secureID->encrypt($account)) ?>', codcli], 'null', '¿Está seguro de agregar este titular?');
                }

                function help() {
                    const stepsData = [{
                            element: '#btn_buscar_cuenta',
                            title: 'Buscar Cuenta de Aportaciones',
                            description: 'Utilice este botón para buscar y seleccionar una cuenta de aportaciones. Se abrirá una ventana donde podrá filtrar y elegir la cuenta deseada.',
                        },
                        {
                            element: '#ccodaho',
                            title: 'Cuenta de Aportaciones Seleccionada',
                            description: 'Aquí se muestra el número de cuenta de aportaciones seleccionada. Si no ha seleccionado ninguna, aparecerá "No seleccionada".',
                        },
                        {
                            element: '#tipo_aportacion',
                            title: 'Producto de Aportación',
                            description: 'Este campo indica el tipo de producto de aportación asociado a la cuenta seleccionada.',
                        },
                        {
                            element: '#div_add_titular',
                            title: 'Agregar Titular',
                            description: 'Utilice este botón para agregar un nuevo titular a la cuenta mancomunada. Se abrirá una ventana para buscar clientes.',
                        },
                        {
                            element: '#tiposahorros',
                            title: 'Titulares de la Cuenta',
                            description: 'Aquí se listan todos los titulares asociados a la cuenta de aportaciones. Puede eliminar titulares adicionales desde esta tabla.',
                        }
                    ];
                    const driverObj = initializeDriver(stepsData);
                    driverObj.drive();
                }
            </script>

    <?php
        break;
}
    ?>

    <?php
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