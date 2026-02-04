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
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];

// date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");
//++++++++++++++++
// session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
// include '../../src/funcphp/func_gen.php';
// date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];

switch ($condi) {
    case 'PrmtrzcAhrrs':
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
        <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
        <style>
            table {
                font-size: 13px;
            }
        </style>
        <div class="text" style="text-align:center">PARAMETRIZACION DE AHORRO</div>
        <div class="card">
            <input type="text" id="file" value="aho_07" style="display: none;">
            <input type="text" id="condi" value="PrmtrzcAhrrs" style="display: none;">
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
                                            <!-- <th>Acciones</th> -->
                                        </tr>
                                    </thead>
                                    <tbody id="tb_cuerpo_parametrizacion">
                                        <?php
                                        $i = 1;
                                        $consulta = mysqli_query($conexion, "SELECT tip.*,ofi.nom_agencia FROM ahomtip tip INNER JOIN tb_agencia ofi ON ofi.cod_agenc=tip.ccodofi WHERE tip.estado=1;");
                                        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            $id = $row["id_tipo"];
                                            $agencia = $row["nom_agencia"];
                                            $id_tipo_cuenta = $row["ccodtip"];
                                            $nombre = $row["nombre"];
                                            $tasa = $row["tasa"];
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
                                            echo '<tr> <td>' . $i . '</td>';
                                            echo '<td>' . $agencia . '</td>';
                                            echo '<td>' . $nombre . '</td>';
                                            echo '<td>' . $tasa . '</td>';
                                            // echo '<td>' . $cuenta . ' - ' . $namecuenta . '</td>';
                                            echo '<td>' . $cuenta . ' - ' . $namecuenta . '
                                                <button type="button" class="btn btn-default" title="Editar" onclick="loaddata([' . $id . ',`' . $nombre . '`,' . $id_cuenta . ',`' . $cuenta . '`,`' . $namecuenta . '`])"> <i class="fa-solid fa-pen"></i></button>
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
                        <div class="col-md-6">
                            <div class="row"></div>
                            <div class="input-group" id="div_cuenta1">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="text_cuenta1" placeholder="name@example.com" readonly>
                                    <input type="text" class="form-control" id="id_hidden1" hidden readonly>
                                    <label class="text-primary" for="text_cuenta1"><i class="fa-solid fa-file-invoice"></i>Cuenta Contable</label>
                                </div>
                                <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', '1')">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button id="save" style="display: none;" type="button" class="btn btn-outline-primary" onclick="obtiene([`id_hidden1`,`idtipo`],[],[],`update_aho_cuentas_contables`,`0`,[])">
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
                $('#save').show();
            }
        </script>

    <?php
        include_once __DIR__ . "/../../src/cris_modales/mdls_nomenclatura.php";
        break;
    case 'PrmtrzcAhrrsant': //antes de la remodelacion
        $codusu = $_SESSION['id'];
        $id = $_POST["xtra"];
        $bandera = false;
        if ($id == 0) {
            $id = ['0', '0', '0', "", "", "", "", "", ""];
        }
    ?>
        <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
        <div class="text" style="text-align:center">PARAMETRIZACION DE AHORRO</div>
        <div class="card">
            <input type="text" id="file" value="aho_07" style="display: none;">
            <input type="text" id="condi" value="PrmtrzcAhrrs" style="display: none;">
            <div class="card-header">Parametrizacion Ahorro</div>
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row mt-2 pb-2">
                        <div class="col">
                            <div class="table-responsive">
                                <table id="table_parametrizacion" class="table table-hover table-border">
                                    <thead class="text-light table-head-aho">
                                        <tr>
                                            <th>TIPO CUENTA</th>
                                            <th>DOCUMENTO</th>
                                            <th>CUENTA1</th>
                                            <th>CUENTA2</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb_cuerpo_parametrizacion">
                                        <?php
                                        $consulta = mysqli_query($conexion, "SELECT ctb.id, ctb.id_tipo_cuenta, tip.nombre, ctb.id_tipo_doc, tpc.descripcion, ctb.id_cuenta1, ctb.id_cuenta2, nomen.ccodcta AS cuenta1, nomen1.ccodcta AS cuenta2, nomen.cdescrip AS nom1, nomen1.cdescrip AS nom2  FROM ahomctb AS ctb 
                                        INNER JOIN ahomtip AS tip ON ctb.id_tipo_cuenta = tip.id_tipo
                                        INNER JOIN ahotipdoc AS tpc ON ctb.id_tipo_doc = tpc.id
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
                                                <button type="button" class="btn btn-default" title="Eliminar" onclick="eliminar(' . $id_ctb . ',`crud_ahorro`,`0`,`delete_aho_cuentas_contables`)"> <i class="fa-solid fa-trash-can"></i></button>
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
                                    $tipdoc = mysqli_query($conexion, "SELECT * FROM `ahomtip`");
                                    $selected = "";
                                    // echo '<option selected value="0">Seleccione un tipo de cuenta</option>';
                                    while ($tip = mysqli_fetch_array($tipdoc)) {
                                        ($tip['id_tipo'] == $id[1]) ? $selected = "selected" : $selected = "";
                                        echo '<option value="' . $tip['id_tipo'] . '"' . $selected . '>' . $tip['ccodtip'] . ' - ' . $tip['nombre'] . '</option>';
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
                                    $tipdoc = mysqli_query($conexion, "SELECT * FROM `ahotipdoc`");
                                    $selected = "";
                                    while ($tip = mysqli_fetch_array($tipdoc)) {
                                        ($tip['id'] == $id[2]) ? $selected = "selected" : $selected = "";
                                        echo '<option value="' . $tip['id'] . '"' . $selected . '>' . $tip['descripcion'] . '</option>';
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
                            <div class="row"></div>
                            <div class="input-group" id="div_cuenta1">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="text_cuenta1" placeholder="name@example.com" readonly value="<?php echo $id[4] . $id[5]; ?>">
                                    <input type="text" class="form-control" id="id_hidden1" hidden value="<?php echo $id[3]; ?>" readonly>
                                    <label class="text-primary" for="text_cuenta1"><i class="fa-solid fa-file-invoice"></i>Cuenta 1 - Debe</label>
                                </div>
                                <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', '1')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group" id="div_cuenta2">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="text_cuenta2" placeholder="name@example.com" readonly value="<?php echo $id[7] . $id[8]; ?>">
                                    <input type="text" class="form-control" id="id_hidden2" hidden value="<?php echo $id[6]; ?>" readonly>
                                    <label class="text-primary" for="text_cuenta2"><i class="fa-solid fa-file-invoice"></i>Cuenta 2 - Haber</label>
                                </div>
                                <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', '2')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>

                            </div>
                        </div>

                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                        <button type="button" class="<?php echo ($id[0] == 0) ? "btn btn-outline-success" : "btn btn-outline-primary" ?>" onclick="<?php echo ($id[0] == 0) ? ("obtiene([`id_hidden1`,`id_hidden2`],[`tip_cuenta`,`tip_doc`],[],  `create_aho_cuentas_contables`,`0`,['$codusu'])")
                                                                                                                                                        : ("obtiene([`id_hidden1`,`id_hidden2`,`text_cuenta1`,`text_cuenta2`],[`tip_cuenta`,`tip_doc`],[],  `update_aho_cuentas_contables`,`0`,['$codusu','$id[0]'])") ?>">
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
        break;
    case 'Parametrizacion_aho_interes':
        $codusu = $_SESSION['id'];
        $id = $_POST["xtra"];
        if ($id == 0) {
            $id = ['0', '0', '0', "", "", "", "", "", ""];
        }
    ?>
        <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
        <div class="text" style="text-align:center">PARAMETRIZACIÓN DE ACREDITACIÓN Y PROVISIÓN</div>
        <div class="card">
            <input type="text" id="file" value="aho_07" style="display: none;">
            <input type="text" id="condi" value="Parametrizacion_aho_interes" style="display: none;">
            <div class="card-header">Parametrizacion de Acreditación y Provisión</div>
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row mt-2 pb-2">
                        <div class="col">
                            <div class="table-responsive">


                                <table id="table_parametrizacion" class="table table-hover table-border">
                                    <thead class="text-light table-head-aho">
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
                                    FROM ahomparaintere AS prt 
                                    INNER JOIN ahomtip AS tip ON prt.id_tipo_cuenta = tip.id_tipo
                                    INNER JOIN ctb_descript_intereses AS inte ON prt.id_descript_intere = inte.id
                                    INNER JOIN tb_usuario AS us ON prt.id_usuario = us.id_usu
                                    INNER JOIN ctb_nomenclatura AS nomen ON prt.id_cuenta1 = nomen.id
                                    INNER JOIN ctb_nomenclatura AS nomen1 ON prt.id_cuenta2 = nomen1.id");
                                        // WHERE us.id_agencia = '3';
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
                                            $nom2 = ($row["nom2"]);

                                            // echo '<tr> <td>' . $nombre . '</td>';
                                            // echo '<td>' . $nombre_inte . '</td>';
                                            // echo '<td>' . $cuenta1 . '</td>';
                                            // echo '<td>' . $cuenta2 . '</td>';
                                            // echo '<td>
                                            //     <button type="button" class="btn btn-default" title="Editar" onclick="printdiv2(`#cuadro`,[' . $id_int . ',' . $id_tipo_cuenta . ',' . $id_descript_intere . ',' . $id_cuenta1 . ',' . $cuenta1 . ',` - ' . $nom1 . '`,' . $id_cuenta2 . ',' . $cuenta2 . ',` - ' . $nom2 . '`])"> <i class="fa-solid fa-pen"></i></button>
                                            //     <button type="button" class="btn btn-default" title="Eliminar" onclick="eliminar(' . $id_int . ',`crud_ahorro`,`0`,`delete_aho_cuentas_intereses`)"> <i class="fa-solid fa-trash-can"></i></button>
                                            //  </td></tr> ';
                                            // Crear un array con los valores a pasar a la función JavaScript
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
                                    $tipdoc = mysqli_query($conexion, "SELECT * FROM `ahomtip` WHERE estado=1");
                                    $selected = "";
                                    while ($tip = mysqli_fetch_array($tipdoc)) {
                                        ($tip['id_tipo'] == $id[1]) ? $selected = "selected" : $selected = "";
                                        echo '<option value="' . $tip['id_tipo'] . '"' . $selected . '>' . $tip['ccodtip'] . ' - ' . $tip['nombre'] . '</option>';
                                    }
                                    ?>
                                </select>
                                <label for="tip_cuenta">Tipo de cuenta</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating">
                                <select class="form-select" id="tip_doc" aria-label="Tipos de cuenta">
                                    <option selected value="0">Seleccionar tipo de operación</option>
                                    <?php
                                    $tip_op = mysqli_query($conexion, "SELECT * FROM `ctb_descript_intereses`");
                                    $selected = "";
                                    while ($tip = mysqli_fetch_array($tip_op)) {
                                        ($tip['id'] == $id[2]) ? $selected = "selected" : $selected = "";
                                        echo '<option value="' . $tip['id'] . '"' . $selected . '>' . $tip['nombre'] . '</option>';
                                    }
                                    ?>
                                </select>
                                <label for="tip_doc">Tipo de operación</label>
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
                                <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', '1')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group" id="div_cuenta2">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="text_cuenta2" readonly value="<?php echo $id[7] . $id[8]; ?>">
                                    <input type="text" class="form-control" id="id_hidden2" hidden value="<?php echo $id[6]; ?>" readonly>
                                    <label class="text-primary" for="text_cuenta2"><i class="fa-solid fa-file-invoice"></i>Cuenta 2 - Haber</label>
                                </div>
                                <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', '2')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                        <button type="button" class="<?php echo ($id[0] == 0) ? "btn btn-outline-success" : "btn btn-outline-primary" ?>" onclick="<?php echo ($id[0] == 0) ? ("obtiene([`id_hidden1`,`id_hidden2`],[`tip_cuenta`,`tip_doc`],[],  `create_aho_cuentas_intereses`,`0`,['$codusu'])") : ("obtiene([`id_hidden1`,`id_hidden2`,`text_cuenta1`,`text_cuenta2`],[`tip_cuenta`,`tip_doc`],[],  `update_aho_cuentas_intereses`,`0`,['$codusu','$id[0]'])") ?>">
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
        include_once __DIR__ . "/../../src/cris_modales/mdls_nomenclatura.php";

        break;
    case 'cuenta__1': {
            $id = $_POST["xtra"];
        ?>
            <div class="form-floating">
                <input type="text" class="form-control" id="text_cuenta1" placeholder="name@example.com" readonly value="<?php echo $id[1] . " - " . $id[2]; ?>">
                <input type="text" class="form-control" id="id_hidden1" value="<?php echo $id[0]; ?>" hidden readonly>
                <label class="text-primary" for="text_cuenta1"><i class="fa-solid fa-file-invoice"></i>Cuenta 1 - Debe</label>
            </div>
            <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', '1')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
        <?php
        }
        break;
    case 'cuenta__2': {
            $id = $_POST["xtra"];
        ?>
            <div class="form-floating">
                <input type="text" class="form-control" id="text_cuenta2" placeholder="name@example.com" readonly value="<?php echo $id[1] . " - " . $id[2]; ?>">
                <input type="text" class="form-control" id="id_hidden2" value="<?php echo $id[0]; ?>" hidden readonly>
                <label class="text-primary" for="text_cuenta2"><i class="fa-solid fa-file-invoice"></i>Cuenta 2 - Haber</label>
            </div>
            <span type="button" class="input-group-text" id="basic-addon2" title="Buscar nomenclatura" onclick="abrir_modal('#modal_nomenclatura', 'show', '#id_modal_hidden', '2')"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
        <?php
        }
        break;
    case 'mancomuna':
        $account = $_POST["xtra"];

        $showmensaje = false;
        try {
            if ($account == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione una cuenta de ahorros");
            }
            $database->openConnection();

            $query = "SELECT cta.estado, cli.short_name, cli.no_identifica, tip.nombre AS tipo_ahorro
                        FROM ahomcta cta 
                        INNER JOIN ahomtip tip ON SUBSTR(cta.ccodaho,7,2) = tip.ccodtip
                        INNER JOIN tb_cliente cli ON cta.ccodcli = cli.idcod_cliente
                        WHERE cta.ccodaho=?";

            $data = $database->getAllResults($query, [$account]);
            if (empty($data)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de ahorros, verifique que el código sea correcto y que el cliente esté activo");
            }
            $dataAccount = $data[0];
            if ($dataAccount['estado'] != "A") {
                $showmensaje = true;
                throw new Exception("Cuenta de ahorros Inactiva");
            }

            $otrosTitulares = $database->getAllResults(
                "SELECT man.id,man.ccodcli, cli.short_name, cli.no_identifica FROM cli_mancomunadas man
                INNER JOIN tb_cliente cli ON man.ccodcli = cli.idcod_cliente
                WHERE man.ccodaho = ? AND tipo='ahorro' AND man.estado='1' AND cli.estado=1",
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

        include_once __DIR__ . "/../../src/cris_modales/mdls_aho_new.php";

        ?>
        <div class="container-fluid py-3">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="text-center mb-3">
                        <h4 class="fw-bold text-primary">Configuración de Cuentas Mancomunadas</h4>
                    </div>
                    <input type="hidden" id="file" value="aho_07">
                    <input type="hidden" id="condi" value="mancomuna">
                    <div class="card shadow-sm">
                        <div class="card-header bg-gradient bg-primary text-white d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-users me-2"></i>Titulares de cuentas de ahorro</span>
                            <div class="d-flex gap-2">
                                <button class="btn btn-warning btn-lg fw-bold" type="button" title="Buscar cuenta" data-bs-toggle="modal" data-bs-target="#findahomcta2" id="btn_buscar_cuenta">
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
                                    <label class="form-label fw-bold">Cuenta de Ahorros</label>
                                    <div class="bg-light border rounded px-3 py-2 d-flex align-items-center" id="ccodaho" style="min-height: 40px;">
                                        <i class="fa-solid fa-piggy-bank me-2 text-primary"></i>
                                        <span class="fw-semibold"><?= $status ? $account : '<span class="text-muted">No seleccionada</span>' ?></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Producto</label>
                                    <div class="bg-light border rounded px-3 py-2 d-flex align-items-center" id="tipo_ahorro" style="min-height: 40px;">
                                        <i class="fa-solid fa-box-archive me-2 text-success"></i>
                                        <span class="fw-semibold"><?= $status ? htmlspecialchars($dataAccount['tipo_ahorro']) : '<span class="text-muted">No definido</span>' ?></span>
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
                                                            onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'deleteTitularAccount', '<?= htmlspecialchars($account) ?>', ['<?= htmlspecialchars($secureID->encrypt($titular['id'])) ?>'], 'null', true, '¿Está seguro de eliminar este titular?');">
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
                obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'addTitularAccount', '<?= htmlspecialchars($account) ?>', ['<?= htmlspecialchars($secureID->encrypt($account)) ?>', codcli], 'null', true, '¿Está seguro de agregar este titular?');
            }

            function help() {
                const stepsData = [{
                        element: '#btn_buscar_cuenta',
                        title: 'Buscar Cuenta de Ahorros',
                        description: 'Utilice este botón para buscar y seleccionar una cuenta de ahorros. Se abrirá una ventana donde podrá filtrar y elegir la cuenta deseada.',
                    },
                    {
                        element: '#ccodaho',
                        title: 'Cuenta de Ahorros',
                        description: 'Aquí se muestra el número de cuenta de ahorros seleccionada. Si no ha seleccionado ninguna, aparecerá "No seleccionada".',
                    },
                    {
                        element: '#tipo_ahorro',
                        title: 'Producto de Ahorro',
                        description: 'Este campo indica el tipo de producto de ahorro asociado a la cuenta seleccionada.',
                    },
                    {
                        element: '#div_add_titular',
                        title: 'Agregar Titular',
                        description: 'Utilice este botón para agregar un nuevo titular a la cuenta mancomunada. Se abrirá una ventana para buscar clientes.',
                    },
                    {
                        element: '#tiposahorros',
                        title: 'Titulares de la Cuenta',
                        description: 'Aquí se listan todos los titulares asociados a la cuenta de ahorros. Puede eliminar titulares adicionales desde esta tabla.',
                    }
                ];
                const driverObj = initializeDriver(stepsData);
                driverObj.drive();
            }
        </script>

    <?php
        break;
    case 'List_mov_recibos_aho':
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
        <input type="text" id="file" value="aho_07" style="display: none;">
        <input type="text" id="condi" value="List_mov_recibos_aho" style="display: none;">
        <div class="text" style="text-align:center">RECIBOS DE AHORROS</div>
        <div class="card">
            <div class="card-header">Recibos de ahorros</div>
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
                        <table id="tabla_recibos_ahorros" class="table table-hover table-border" style="width: 100%;">
                            <thead class="text-light table-head-aho" style="font-size: 0.8rem;">
                                <tr>
                                    <th>ID</th>
                                    <th>No. Recibo</th>
                                    <th>No. Cuenta</th>
                                    <th>Razon</th>
                                    <th>Tipo documento</th>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th style="width: 15%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $("#tabla_recibos_ahorros").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/lista_recibos_ahorros.php",
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
                                imp3 = '';
                                if (row[8] == "1") {
                                    imp3 = `<button type="button" class="btn btn-primary btn-sm me-1 ms-1" title="Eliminacion recibo" onclick="eliminar('${row[0]}', 'crud_ahorro', '0', 'eliminacion_recibo');"><i class="fa-solid fa-trash"></i></button>`;
                                    imp2 = `<button type="button" class="btn btn-warning btn-sm" title="Edicion" onclick="modal_edit_recibo('${row[0]}','${row[1]}', '${row[2]}','<?= $codusu ?>')"><i class="fa-solid fa-pen-to-square"></i></button>`;
                                }
                                imp = `<button type="button" class="btn btn-secondary btn-sm" title="Reimpresion" onclick="obtiene([],[],[],'reimpresion_recibo','0',['${row[0]}','<?= $codusu ?>'])"><i class="fa-solid fa-print"></i></button>`;
                                return imp + imp2 + imp3;
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
                                    <input type="text" class="form-control " id="ccodaho_recibo" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <span class="input-group-addon col-8">Nuevo número de certificado</span>
                                <input type="text" aria-label="Certificado" id="numdoc_modal_recibo" class="form-control  col" placeholder="">
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

    case 'dea_ctb_aho':
        $codusu = $_SESSION['id'];
        $id = $_POST["xtra"];

        try {
            // Consulta para obtener cuentas de ahorros con saldo 0 usando la función para calcular saldo
            $query = "SELECT 
                            cta.ccodaho, 
                            cta.ccodcli, 
                            cli.short_name AS client_name, 
                            cta.nlibreta, 
                            cta.estado, 
                            DATE_FORMAT(cta.fecha_apertura, '%Y-%m-%d') as fecha_apertura, 
                            calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) as saldo
                          FROM ahomcta cta
                          INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                          WHERE calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) = 0
                          ORDER BY cta.ccodaho ASC";

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
            <div class="text" style="text-align:center">ACTIVACION Y DESACTIVACION MASIVA DE CUENTAS</div>
            <input type="text" id="condi" value="dea_ctb_aho" style="display: none;">
            <input type="text" id="file" value="aho_07" style="display: none;">
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
                            <thead class="text-light table-head-aho" style="font-size: 0.8rem;">
                                <tr>
                                    <th><input type="checkbox" id="seleccionar_todos" onchange="seleccionarTodos(this)"></th>
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
                                    echo "<td><input type='checkbox' class='cuenta-checkbox' value='{$row['ccodaho']}'></td>";
                                    echo "<td>{$row['ccodaho']}</td>";
                                    echo "<td>{$row['ccodcli']}</td>";
                                    echo "<td>{$row['client_name']}</td>";
                                    echo "<td>{$row['nlibreta']}</td>";
                                    echo "<td class='{$estado_clase}'>{$estado_texto}</td>";
                                    echo "<td>{$row['fecha_apertura']}</td>";
                                    echo "<td>{$row['saldo']}</td>";
                                    echo "<td><button class='btn {$btn_class} btn-sm' onclick='{$btn_action}(\"{$row['ccodaho']}\")'>{$btn_text}</button></td>";
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
                        url: '../../views/aho/aho_07.php',
                        type: 'POST',
                        data: {
                            condi: 'dea_ctb_aho',
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

                function activarCuenta(ccodaho) {
                    accionCuenta(ccodaho, 'activar');
                }

                function desactivarCuenta(ccodaho) {
                    accionCuenta(ccodaho, 'desactivar');
                }

                function accionCuenta(ccodaho, accion) {
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
                                url: '../src/server_side/actualizar_cuentas_masivo.php',
                                type: 'POST',
                                data: {
                                    cuentas: [ccodaho],
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
                                            printdiv2("#cuadro", 0); // Recarga parcial de la sección
                                        });
                                    } else {
                                        printdiv2("#cuadro", 0);
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
                                url: '../src/server_side/actualizar_cuentas_masivo.php',
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
                                            printdiv2("#cuadro", 0);
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
    case 'mod_aho_int':
        $codusu = $_SESSION['id'];
        $id     = $_POST['xtra'];

        // 1) Traer TODAS las cuentas con tasa
        $queryCuentas = "
                SELECT 
                    cta.ccodaho, 
                    cta.ccodcli, 
                    cli.short_name    AS client_name, 
                    cta.nlibreta, 
                    cta.tasa,
                    DATE_FORMAT(cta.fecha_apertura, '%Y-%m-%d') AS fecha_apertura, 
                    calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) AS saldo
                FROM ahomcta cta
                INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                ORDER BY cta.ccodaho ASC
            ";
        $resCuentas = mysqli_query($conexion, $queryCuentas);
        if (!$resCuentas) {
            echo "<div class='alert alert-danger'>Error en la consulta de cuentas: "
                . mysqli_error($conexion) . "</div>";
            break;
        }

        // 2) Traer tipos de cuenta una sola vez
        $queryTipos = "
                SELECT ccodtip, nombre 
                FROM ahomtip 
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
                <div class="text text-center">MODIFICACIÓN DE INTERÉS MASIVO POR PRODUCTO</div>
                <input type="hidden" id="condi" value="mod_aho_int">
                <input type="hidden" id="file" value="aho_07">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Modificar Tasas Seleccionadas</h5>
                        <button class="btn btn-success" onclick="accionMasiva()">
                            <i class="fas fa-check-circle"></i> Aplicar Nueva Tasa
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- selector tipo de cuenta -->
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
                            <table id="tabla_cuentas" class="table table-hover table-bordered" style="width:100%">
                                <thead class="table-head-aho text-light" style="font-size:0.8rem">
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
                                                <input type="checkbox" class="cuenta-checkbox"
                                                    value="<?= $row['ccodaho'] ?>">
                                            </td>
                                            <td><?= $row['ccodaho'] ?></td>
                                            <td><?= $row['ccodcli'] ?></td>
                                            <td><?= $row['client_name'] ?></td>
                                            <td><?= $row['nlibreta'] ?></td>
                                            <td><?= $row['tasa'] ?>%</td>
                                            <td><?= $row['fecha_apertura'] ?></td>
                                            <td><?= $row['saldo'] ?></td>
                                            <td>
                                                <button
                                                    class="btn btn-sm btn-secondary"
                                                    onclick="openModal(
                                                    '<?= $row['ccodaho'] ?>',
                                                    '<?= $row['tasa'] ?>',
                                                    '<?= addslashes($row['client_name']) ?>',
                                                    '<?= $row['nlibreta'] ?>'
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
                                <i class="fas fa-edit me-2"></i>
                                Editar Tasa
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Datos de la cuenta -->
                            <div class="mb-4">
                                <h6 class="fw-bold">Cuenta <span id="modal_ccodaho"></span></h6>
                                <dl class="row">
                                    <dt class="col-sm-4">Titular</dt>
                                    <dd class="col-sm-8" id="modal_client_name"></dd>

                                    <dt class="col-sm-4">Libreta</dt>
                                    <dd class="col-sm-8" id="modal_nlibreta"></dd>

                                    <dt class="col-sm-4">Saldo</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-info text-dark" id="modal_saldo"></span>
                                    </dd>
                                </dl>
                            </div>

                            <!-- Input de nueva tasa -->
                            <div class="form-floating mb-3">
                                <input
                                    type="number"
                                    step="0.01"
                                    class="form-control"
                                    id="modal_tasa"
                                    placeholder="Tasa">
                                <label for="modal_tasa">Tasa (%)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-primary" data-bs-dismiss="modal">
                                Cancelar
                            </button>
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
                    if ($.fn.DataTable.isDataTable('#tabla_cuentas_saldo_cero')) {
                        $('#tabla_cuentas_saldo_cero').DataTable().destroy();
                    }

                    tablaCuentas = $('#tabla_cuentas_saldo_cero').DataTable({
                        dom: 'lfrtip',
                        lengthMenu: [
                            [10, 25, 50, -1], // valores internos
                            [10, 25, 50, 'Todos'] // etiquetas que ve el usuario
                        ],
                        pageLength: 10,
                        searching: true,
                        language: {
                            lengthMenu: 'Mostrar _MENU_ registros',
                            zeroRecords: 'No se encontraron cuentas con saldo 0',
                            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                            infoEmpty: 'No hay registros disponibles',
                            infoFiltered: '(filtrado de _MAX_ registros totales)',
                            search: 'Buscar:',
                            paginate: {
                                first: 'Primero',
                                last: 'Último',
                                next: 'Siguiente',
                                previous: 'Anterior'
                            }
                        },
                        order: [
                            [1, 'asc']
                        ],
                        columnDefs: [{
                            targets: [0, 8],
                            orderable: false
                        }]
                    });
                }


                function aplicarFiltros() {
                    const filtroTipo = $('#filtro_tipo').val();
                    $.fn.dataTable.ext.search = [(settings, data) => {
                        if (filtroTipo !== 'all') {
                            const tipo = data[1].substring(6, 8);
                            return tipo === filtroTipo;
                        }
                        return true;
                    }];
                    tablaCuentas.draw();
                }

                function accionMasiva() {
                    const sel = $('.cuenta-checkbox:checked').map((i, e) => e.value).get();
                    const tasa = parseFloat($('#nueva_tasa').val());
                    if (!sel.length || isNaN(tasa)) {
                        return Swal.fire('Atención', 'Seleccione cuentas y especifique tasa', 'warning');
                    }
                    Swal.fire({
                        title: '¿Aplicar tasa ' + tasa + '% a ' + sel.length + ' cuentas?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, aplicar'
                    }).then(r => {
                        if (!r.isConfirmed) return;
                        $.post('../src/server_side/actualizar_tasas_masivo.php', {
                            cuentas: sel,
                            tasa,
                            usuario: '<?= $codusu ?>'
                        }, resp => {
                            Swal.fire(resp.success ? '¡Hecho!' : 'Error', resp.message,
                                    resp.success ? 'success' : 'error')
                                .then(refresh);
                        }, 'json');
                    });
                }

                function openModal(ccodaho, tasa, clientName, libreta) {
                    cuentaEnModal = ccodaho;
                    $('#modal_ccodaho').text(ccodaho);
                    $('#modal_client_name').text(clientName);
                    $('#modal_nlibreta').text(libreta);
                    // Si quieres mostrar saldo también, pásalo como parámetro y asígnalo:
                    $('#modal_saldo').text($('#saldo-' + ccodaho).text());

                    $('#modal_tasa').val(tasa);
                    new bootstrap.Modal(document.getElementById('modalTasa')).show();
                }


                function saveTasa() {
                    const tasa = parseFloat($('#modal_tasa').val());
                    if (isNaN(tasa)) return Swal.fire('Error', 'Tasa inválida', 'error');
                    $.post('../src/server_side/actualizar_tasas_individual.php', {
                        cuenta: cuentaEnModal,
                        tasa,
                        usuario: '<?= $codusu ?>'
                    }, resp => {
                        Swal.fire(resp.success ? '¡Guardado!' : 'Error', resp.message,
                                resp.success ? 'success' : 'error')
                            .then(refreshModal);
                    }, 'json');
                }

                function refresh() {
                    recargarContenido();
                }

                function refreshModal() {
                    bootstrap.Modal.getInstance($('#modalTasa')).hide();
                    refresh();
                }

                function recargarContenido() {
                    $.post('../../views/aho/aho_07.php', {
                        condi: 'mod_aho_int',
                        xtra: ''
                    }, html => {
                        $('#contenido_cuentas').html(html);
                        initializeDataTable();
                        $('#filtro_tipo').on('change', aplicarFiltros);
                    });
                }

                $(document).ready(() => {
                    initializeDataTable();
                    $('#seleccionar_todos').on('change', function() {
                        const ch = this.checked;
                        tablaCuentas.rows({
                                search: 'applied'
                            }).nodes()
                            .to$().find('.cuenta-checkbox').prop('checked', ch);
                    });
                    $('#filtro_tipo').on('change', aplicarFiltros);
                });
            </script>
    <?php
        break;
} //FINAL DEL SWITCH
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