<?php
include __DIR__ . '/../../../../includes/Config/config.php';
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

include __DIR__ . '/../../../../includes/Config/database.php';
include __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];

switch ($condi) {
    case 'gestion_usuarios': {
            $codusu = $_SESSION['id'];
            $id = $_POST["xtra"];
?>
<!-- Crud para agregar, editar y eliminar usuarios -->
<input type="text" id="file" value="usuario_01" style="display: none;">
<input type="text" id="condi" value="gestion_usuarios" style="display: none;">
<div class="text" style="text-align:center">GESTIÓN DE USUARIOS</div>
<div class="card">
    <div class="card-header">Gestión de usuarios</div>
    <div class="card-body">
        <div class="text-center mb-2">
            <h3>Datos de usuario</h3>
        </div>
        <!-- Seccion de inputs para edicion -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <!-- agencia y nombres -->
            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3 mt-2">
                        <select class="form-select" id="agencia" aria-label="Tipos de agencia">
                            <option selected value="0">Seleccionar una agencia</option>
                            <?php
                                        $agencia = mysqli_query($conexion, "SELECT * FROM `tb_agencia`");
                                        while ($fila = mysqli_fetch_array($agencia)) {
                                            echo '<option value="' . $fila['id_agencia'] . '">' . $fila['cod_agenc'] . ' - ' . strtoupper($fila['nom_agencia']) . '</option>';
                                        }
                                        ?>
                        </select>
                        <label for="agencia">Agencia</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3 mt-2">
                        <input type="text" class="form-control" id="nombres" placeholder="Nombres">
                        <input type="text" name="" id="id_usu" hidden>
                        <label for="nombres">Nombres</label>
                    </div>
                </div>
            </div>
            <!-- apellidos y dpi -->
            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="apellidos" placeholder="Apellidos">
                        <label for="apellidos">Apellidos</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="dpi"
                            placeholder="Documento de identificación (DPI)">
                        <label for="dpi">Documento de Indentificacíon (DPI)</label>
                    </div>
                </div>
            </div>
            <!-- cargo y correo electronico -->
            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="form-floating">
                        <select class="form-select" id="cargo" aria-label="Tipos de cargo">
                            <option selected value="0">Seleccionar un cargo</option>
                            <?php
                                        $cargos = mysqli_query($general, "SELECT * FROM `tb_usuarioscargoprofecional`");
                                        while ($fila = mysqli_fetch_array($cargos)) {
                                            echo '<option value="' . $fila['id_UsuariosCargoProfecional'] . '">' . $fila['UsuariosCargoProfecional'] . '</option>';
                                        }
                                        ?>
                        </select>
                        <label for="cargo">Cargo</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" placeholder="Correo electronico">
                        <label for="email">Correo electronico</label>
                    </div>
                </div>
            </div>
            <!-- usuario contraseña y confirmacion -->
            <div class="row">
                <div class="col-12 col-sm-4">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="usuario" placeholder="Usuario">
                        <label for="usuario">Usuario</label>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="password" class="form-control border-end-0" id="password"
                                placeholder="Contraseña">
                            <label for="password">Contraseña</label>
                        </div>
                        <span class="input-group-text bg-transparent border-start-0 text-primary"><i
                                class="fa-regular fa-eye" id="togglePassword"></i></span>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input type="password" class="form-control border-end-0" id="confpass"
                                placeholder="Confirmar contraseña">
                            <label for="confpass">Confirmar contraseña</label>
                        </div>
                        <span class="input-group-text bg-transparent border-start-0 text-primary"><i
                                class="fa-regular fa-eye" id="togglePassword2"></i></span>
                    </div>
                </div>
            </div>
            <!-- estado -->
            <div class="row" id="select_estado" style="display: none;">
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3">
                        <select class="form-select" id="estado" aria-label="Estado de usuario">
                            <option selected value="1">Activo</option>
                            <option value="2">Inactivo</option>
                        </select>
                        <label for="estado">Estado</label>
                    </div>
                </div>
            </div>
            <div class="row justify-items-md-center">
                <div class="col align-items-center mb-3" id="modal_footer">
                    <button type="button" class="btn btn-outline-success" id="btGuardar"
                        onclick="obtiene([`nombres`,`apellidos`,`dpi`,`email`,`usuario`,`password`,`confpass`],[`agencia`,`cargo`],[],`create_user`,`0`,['<?= $codusu; ?>'])">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btEditar"
                        onclick="obtiene([`nombres`,`apellidos`,`dpi`,`email`,`usuario`,`password`,`confpass`,`id_usu`],[`agencia`,`cargo`,`estado`],[],`update_user`,`0`,['<?= $codusu; ?>'])">
                        <i class="fa-solid fa-floppy-disk"></i> Actualizar
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

        <!-- tabla de usuarios -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row mt-2 pb-2">
                <div class="col">
                    <div class="table-responsive">
                        <table id="table-usuarios" class="table table-hover table-border">
                            <thead class="text-light table-head-usu mt-2">
                                <tr>
                                    <th>Acciones</th>
                                    <th>#</th>
                                    <th>Nombres</th>
                                    <th>Apellidos</th>
                                    <th>Usuario</th>
                                    <th>Correo electronico</th>
                                    <th>DPI</th>
                                    <th>Cargo</th>
                                    <th>Agencia</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="tb_cuerpo_usuarios" style="font-size: 0.9rem !important;">
                                <?php
                                            $consulta = mysqli_query($conexion, "SELECT us.*, cg.UsuariosCargoProfecional AS cargo, ag.nom_agencia AS agencia FROM  tb_usuario us
                                            INNER JOIN $db_name_general.tb_usuarioscargoprofecional cg ON us.puesto=cg.id_UsuariosCargoProfecional
                                            INNER JOIN tb_agencia ag ON us.id_agencia=ag.id_agencia
                                            WHERE estado=1 OR estado=2 ORDER BY us.id_usu ASC");
                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id_usu = $row["id_usu"];
                                                $nombre = $row["nombre"];
                                                $apellido = $row["apellido"];
                                                $dpi = $row["dpi"];
                                                $usu = $row["usu"];
                                                $pass = $row["pass"];
                                                $estado = $row["estado"];
                                                $puesto = $row["puesto"];
                                                $id_agencia = $row["id_agencia"];
                                                $email = $row["Email"];
                                                $cargo = $row["cargo"];
                                                $agencia = $row["agencia"];
                                                ($estado == "1") ? $text_estado = "Activo" : $text_estado = "Inactivo";
                                                if ($_SESSION['id'] == 4) { ?>
                                <tr>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm"
                                            onclick="printdiv5('id_usu,agencia,nombres,apellidos,dpi,cargo,email,usuario,estado/A,A,A,A,A,A,A,A,A/'+'/#/#/select_estado/#',['<?= $id_usu ?>','<?= $id_agencia ?>','<?= $nombre ?>','<?= $apellido ?>','<?= $dpi ?>','<?= $puesto ?>','<?= $email ?>','<?= $usu ?>','<?= $estado ?>']); consultar_password('<?= $id_usu ?>'); HabDes_boton(1);"><i
                                                class="fa-solid fa-eye"></i></button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="eliminar('<?= $id_usu ?>', 'crud_usuario', '0', 'delete_user')"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </td>
                                    <th scope="row"><?= $id_usu ?></th>
                                    <td><?= $nombre ?></td>
                                    <td><?= $apellido ?></td>
                                    <td><?= $usu ?></td>
                                    <td><?= $email ?></td>
                                    <td><?= $dpi ?></td>
                                    <td><?= $cargo ?></td>
                                    <td><?= strtoupper($agencia) ?></td>
                                    <td><?= $text_estado ?></td>
                                </tr>
                                <?php } else {
                                                    if ($id_usu != 4) { ?>
                                <!-- seccion de datos -->
                                <tr>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm"
                                            onclick="printdiv5('id_usu,agencia,nombres,apellidos,dpi,cargo,email,usuario,estado/A,A,A,A,A,A,A,A,A/'+'/#/#/select_estado/#',['<?= $id_usu ?>','<?= $id_agencia ?>','<?= $nombre ?>','<?= $apellido ?>','<?= $dpi ?>','<?= $puesto ?>','<?= $email ?>','<?= $usu ?>','<?= $estado ?>']); consultar_password('<?= $id_usu ?>'); HabDes_boton(1);"><i
                                                class="fa-solid fa-eye"></i></button>
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="eliminar('<?= $id_usu ?>', 'crud_usuario', '0', 'delete_user')"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </td>
                                    <th scope="row"><?= $id_usu ?></th>
                                    <td><?= $nombre ?></td>
                                    <td><?= $apellido ?></td>
                                    <td><?= $usu ?></td>
                                    <td><?= $email ?></td>
                                    <td><?= $dpi ?></td>
                                    <td><?= $cargo ?></td>
                                    <td><?= strtoupper($agencia) ?></td>
                                    <td><?= $text_estado ?></td>
                                </tr>
                                <?php }
                                                }
                                            } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
        //Datatable para parametrizacion
        $(document).ready(function() {
            convertir_tabla_a_datatable("table-usuarios");
            HabDes_boton(0);

            $("#togglePassword").click(function(e) {
                e.preventDefault();
                var type = $(this).parent().parent().find("#password").attr("type");
                if (type == "password") {
                    $(this).removeClass("fa-regular fa-eye");
                    $(this).addClass("fa-regular fa-eye-slash");
                    $(this).parent().parent().find("#password").attr("type", "text");
                } else if (type == "text") {
                    $(this).removeClass("fa-regular fa-eye-slash");
                    $(this).addClass("fa-regular fa-eye");
                    $(this).parent().parent().find("#password").attr("type", "password");
                }
            });

            $("#togglePassword2").click(function(e) {
                e.preventDefault();
                var type = $(this).parent().parent().find("#confpass").attr("type");
                if (type == "password") {
                    $(this).removeClass("fa-regular fa-eye");
                    $(this).addClass("fa-regular fa-eye-slash");
                    $(this).parent().parent().find("#confpass").attr("type", "text");
                } else if (type == "text") {
                    $(this).removeClass("fa-regular fa-eye-slash");
                    $(this).addClass("fa-regular fa-eye");
                    $(this).parent().parent().find("#confpass").attr("type", "password");
                }
            });
        });
        </script>
    </div>
</div>
<?php
        }
        break;
        // NEGROY CAMBIO DE CONTRASEÑA DE LOS USUARIOS ...
    case 'change_pass':
        $id = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $data = $database->selectColumns('tb_usuario', ['nombre', 'apellido'], 'id_usu=?', [$idusuario]);
            if (empty($data)) {
                $showmensaje = true;
                throw new Exception("No se logró obtener los datos de su usuario, intente nuevamente mas tarde");
            }
            $nombreusuario = $data[0]["nombre"] . " " . $data[0]["apellido"];
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

        ?>
<input type="text" value="change_pass" id="condi" style="display: none;">
<input type="text" value="usuario_01" id="file" style="display: none;">
<input type="text" id="cont" class="d-none" value="<?= htmlspecialchars($secureID->encrypt($idusuario))  ?>">
<div class="text" style="text-align:center">CAMBIO DE CONTRASEÑA</div>
<div class="card">
    <!-- <div class="card-header">CAMBIO DE CONTRASEÑA</div> -->
    <div class="card-body">
        <div class="text-center mb-2">
            <h3>Datos de usuario</h3>
        </div>
        <div class="container contenedort form" style="max-width: 100% !important;">
            <div class="mb-3 row">
                <label for="user" class="col-sm-4 col-form-label">Usuario</label>
                <div class="col-sm-8">
                    <input type="text" readonly class="form-control-plaintext" id="user"
                        value="<?= $nombreusuario ?? " "    ?>">
                </div>
            </div>
            <div class="mb-3 row">
                <label for="inputPassword" class="col-sm-4 col-form-label">Contraseña anterior</label>
                <div class="col-sm-8">
                    <input type="password" required class="form-control" id="password"
                        placeholder="Ingrese la contraseña actual">
                </div>
            </div>
            <div class="mb-3 row">
                <label for="inputPassword" class="col-sm-4 col-form-label">Contraseña Nueva</label>
                <div class="col-sm-8">
                    <input type="password" required class="form-control" id="newpass"
                        placeholder="Ingrese la nueva contraseña">
                </div>
            </div>
            <div class="mb-3 row">
                <label for="inputPassword" class="col-sm-4 col-form-label">Confirmacion de contraseña</label>
                <div class="col-sm-8">
                    <input type="password" required placeholder="Confirme la contraseña nueva" class="form-control"
                        id="newpass2">
                </div>
            </div>
            <?php echo $csrf->getTokenField(); ?>
            <div class="row justify-items-md-center">
                <div class="col align-items-center mb-3" id="modal_footer">
                    <button type="button" class="btn btn-outline-success btn-lg"
                        onclick="obtiene([`cont`,`password`,`newpass`,`newpass2`,'<?= $csrf->getTokenName() ?>'],[],[],`change_pass`,`0`,[])">Actualizar
                        Contraseña</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
        break;
    case 'asignacion_token':
        $id = $_POST["xtra"];
        // $query = "SELECT CONCAT(nombre,' ',apellido) nombre,dpi,puesto,Email, tk.id,tk.codusu,tk.token,tk.estado,tk.hostname 
        //             FROM huella_tkn_auto tk 
        //             LEFT JOIN tb_usuario usu ON usu.id_usu=tk.codusu
        //             WHERE tk.estado IN (0,1);";
        // $showmensaje = false;
        // try {
        //     $database->openConnection();
        //     // $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], '1=?', [1]);
        //     $result = $database->getAllResults($query);
        //     if (empty($result)) {
        //         $showmensaje = true;
        //         throw new Exception("No hay tokens generados");
        //     }
        // } catch (Exception $e) {
        //     // echo "Error: " . $e->getMessage();
        //     if (!$showmensaje) {
        //         $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        //     }
        //     $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        // } finally {
        //     $database->closeConnection();
        // }
    ?>
<style>
.subscribe {
    position: relative;
    height: 140px;
    width: 500px;
    padding: 20px;
    background-color: #FFF;
    border-radius: 4px;
    color: #333;
    box-shadow: 0px 0px 60px 5px rgba(0, 0, 0, 0.4);
}

.subscribe:after {
    position: absolute;
    content: "";
    right: -10px;
    bottom: 18px;
    width: 0;
    height: 0;
    border-left: 0px solid transparent;
    border-right: 10px solid transparent;
    border-bottom: 10px solid #1a044e;
}

.subscribe p {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    letter-spacing: 4px;
    line-height: 28px;
}

.subscribe input {
    position: relative;
    bottom: 30px;
    border: none;
    border-bottom: 1px solid #d4d4d4;
    padding: 10px;
    width: 82%;
    background: transparent;
    transition: all .25s ease;
}

.subscribe input:focus {
    outline: none;
    border-bottom: 1px solid #0d095e;
    font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', 'sans-serif';
}

.subscribe .submit-btn {
    position: absolute;
    border-radius: 30px;
    border-bottom-right-radius: 0;
    border-top-right-radius: 0;
    background-color: #0f0092;
    color: #FFF;
    padding: 12px 25px;
    display: inline-block;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 5px;
    right: -10px;
    bottom: -20px;
    cursor: pointer;
    transition: all .25s ease;
    box-shadow: -5px 6px 20px 0px rgba(26, 26, 26, 0.4);
}

.subscribe .submit-btn:hover {
    background-color: #07013d;
    box-shadow: -5px 6px 20px 0px rgba(88, 88, 88, 0.569);
}
</style>
<input type="text" value="asignacion_token" id="condi" style="display: none;">
<input type="text" value="usuario_01" id="file" style="display: none;">
<div class="card" style="height: 100% !important;">
    <div class="card-body">
        <div class="container contenedort" id="divtokensave">
            <div class="row justify-content-md-center">
                <div class="col-lg-12 col-md-12 col-sm-12 d-flex justify-content-center align-items-center mt-2">
                    <div class="subscribe text-center">
                        <p>PEGAR TOKEN A ASIGNAR</p>
                        <br>
                        <input placeholder="Token a asignar a ésta instancia" class="subscribe-input" id="tokenvalue"
                            type="text">
                        <br>
                        <div class="submit-btn"
                            onclick="obtiene(['tokenvalue','<?= $csrf->getTokenName() ?>'], [], [], 'savetoken', 0, [])">
                            GUARDAR</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container contenedort" id="divtokenant" style="display: none;">
            <div class="row justify-content-md-center">
                <div class="col-lg-12 col-md-12 col-sm-12 d-flex justify-content-center align-items-center mt-2">
                    <div class="subscribe text-center">
                        <p id="ptitle">YA HAY UN TOKEN ASIGNADO A ÉSTA INSTANCIA</p>
                        <br>
                        <input readonly placeholder="Token asignado a ésta instancia" class="subscribe-input"
                            name="tokenvalueant" type="text" id="tokenvalueant">
                        <br>
                        <div class="submit-btn" onclick="del('<?= $csrf->getToken() ?>');">ELIMINAR</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-items-md-center">
        <div class="col align-items-center mb-3" id="modal_footer">
            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                <i class="fa-solid fa-ban"></i> Cancelar
            </button>
            <button type="button" class="btn btn-outline-warning" onclick="salir()">
                <i class="fa-solid fa-circle-xmark"></i> Salir
            </button>
        </div>




    </div>
    <?php echo $csrf->getTokenField(); ?>
</div>
<script>
function delete_token() {
    iziToast.info({
        title: '',
        message: 'Funcion Desabilitada',
        position: 'topRight'
    });
}

function create_token() {
    let cant_t = parseInt(document.getElementById('cant_tokens').value);
    if (cant_t > 0) {
        // console.log("Valor enviado: " + cant_t);
        let tokens = [];
        // Generar los tokens
        for (let i = 0; i < cant_t; i++) {
            let token = srnPc();
            tokens.push(token);
        }
        // Enviar los tokens 
        $.ajax({
            url: "../../../src/cruds/crud_usuario.php",
            type: "POST",
            data: {
                'condi': 'create_tokens',
                'tokens': tokens
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.status === 1) {
                    data.tokens.forEach((token, index) => {
                        let maskedToken = token.slice(0, -8) + '********'; //TRUNCAR
                        iziToast.success({
                            title: 'Éxito',
                            message: 'Token creado: ' + maskedToken,
                            position: 'topRight'
                        });
                    });
                    printdiv2('#cuadro', '0');
                } else {
                    iziToast.warning({
                        title: 'Advertencia',
                        message: data.mensaje,
                        position: 'topRight'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("Error en la solicitud AJAX:", error);
                iziToast.error({
                    title: 'Error',
                    message: 'Error al crear los tokens: ' + error,
                    position: 'topRight'
                });
            }
        });
    } else {
        iziToast.warning({
            title: 'Advertencia',
            message: 'Ingrese un valor mayor a 0.',
            position: 'topRight'
        });
    }
}


function srnPc() {
    var d = new Date();
    var dateint = d.getTime();
    var letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    var total = letters.length;
    var keyTemp = "";
    for (var i = 0; i < 6; i++) {
        keyTemp += letters[parseInt((Math.random() * (total - 1) + 1))];
    }
    keyTemp += dateint;
    return keyTemp;
}



function del(fieltk) {
    let tk = document.getElementById("tokenvalueant").value;
    eliminar([tk, fieltk], 'crud_usuario', 0, 'deletetoken')
}

function saveSrnPc(tk) {
    if (localStorage.getItem("srnPc")) {
        localStorage.removeItem("srnPc");
    }
    localStorage.setItem("srnPc", tk);
}

function removeSrn() {
    localStorage.removeItem("srnPc");
}

function verificacion() {
    if (localStorage.getItem("srnPc")) {
        $("#divtokenant").show();
        $("#divtokensave").hide();
        document.getElementById("tokenvalueant").value = localStorage.getItem("srnPc");
    } else {
        $("#divtokenant").hide();
        $("#divtokensave").show();
        iziToast.info({
            title: 'No hay ningún token asignado a ésta instancia',
            position: 'center',
            message: 'Puede solicitar uno con el Administrador',
            timeout: 7000
        });
    }
}
$(function() {
    verificacion();
});
</script>
<?php
        break;
    case 'asignacion_tokenotros':
        $id = $_POST["xtra"];
        $query = "SELECT CONCAT(nombre,' ',apellido) nombre,dpi,puesto,Email, tk.id,tk.codusu,tk.token,tk.estado,tk.hostname 
                    FROM huella_tkn_auto tk 
                    LEFT JOIN tb_usuario usu ON usu.id_usu=tk.codusu
                    WHERE tk.estado IN (0,1);";
        $showmensaje = false;
        try {
            $database->openConnection();
            // $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], '1=?', [1]);
            $result = $database->getAllResults($query);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No hay tokens generados");
            }
        } catch (Exception $e) {
            // echo "Error: " . $e->getMessage();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
    ?>
<!-- <div class="text" style="text-align:center">LISTADO DEL DÍA</div> -->
<input type="text" value="asignacion_token" id="condi" style="display: none;">
<input type="text" value="usuario_01" id="file" style="display: none;">
<div class="card" style="height: 100% !important;">
    <div class="card-body">
        <div class="container contenedort">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <ol class="list-group list-group-numbered">
                        <?php
                                foreach ($result as $row) {
                                    $estado = ($row['estado'] == 1) ? "Asignado" : "No asignado";
                                    $last8 = substr($row['token'], -8);
                                    $masked = str_repeat('●', strlen($row['token']) - 8) . $last8;
                                    $hostname = $row["hostname"] ?? "Desconocido";
                                    $buttoncopy = ($row["estado"] == 0) ? "<button class='btn btn-sm btn-primary ms-2' onclick='copyToken(`" . $row['token'] . "`)'>Copiar Token</button>" : " HOSTNAME: $hostname";
                                    $button = ($row["estado"] == 0) ? "<button class='btn btn-sm btn-primary ms-2' onclick='copyToken(`" . $row['token'] . "`)'>Asignar token a esta maquina</button>" : "";
                                    $color = ($row["estado"] == 0) ? "primary" : "success";
                                ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold"><?= $row['nombre'] ?? "NO ASIGNADO" ?></div>
                                <?= $masked ?>
                                <?= $buttoncopy ?>
                                <?= $button ?>
                            </div>
                            <span class="badge text-bg-<?= $color ?> rounded-pill"><?= $estado ?></span>
                        </li>
                        <?php
                                }
                                ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
function copyToken(token) {
    var tempInput = document.createElement("input");
    tempInput.style.position = "absolute";
    tempInput.style.left = "-9999px";
    tempInput.value = token;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);
    iziToast.success({
        title: 'Copia exitosa',
        position: 'topRight',
        message: 'Token copiado en portapapeles',
        timeout: 1800
    });
}

function srnPc() {
    var d = new Date();
    var dateint = d.getTime();
    var letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    var total = letters.length;
    var keyTemp = "";
    for (var i = 0; i < 6; i++) {
        keyTemp += letters[parseInt((Math.random() * (total - 1) + 1))];
    }
    keyTemp += dateint;
    return keyTemp;
}
//CREAR FUNCION PARA REMOVER EL TOKEN DE LA MAQUINA.
//SIEMPRE VERIFICAR SI LA MAQUINA TIENE ALGUN TOKEN ALMACENADO E IDENTIFICAR CUAL DE TODOS PERTENECE A LA MAQUINA

function saveSrnPc() {
    localStorage.setItem("srnPc", srnPc());
    //    saveToken();
    //    localStorage.removeItem("srnPc");
}
</script>
<?php
        break;
        case 'creacion_token':
          ?>
<input type="text" value="creacion_token" id="condi" style="display: none;">
<input type="text" value="usuario_01" id="file" style="display: none;">
<div class="card" style="height: 100% !important;">
    <div class="card-body">
        <div class="container contenedort">

            <!-- tabla de usuarios -->
            <div class="container contenedort" style="max-width: 100% !important;">
                <div class="row mt-2 pb-2">
                    <div class="col">
                        <div class="table-responsive">
                            <table id="table-usuarios" class="table table-hover table-border">
                                <thead class="text-light table-head-usu mt-2">
                                    <tr>
                                        <th>Acciones</th>
                                        <th>#</th>
                                        <th>Token</th>
                                        <th>Fecha creacion</th>
                                        <th>estado</th>

                                    </tr>
                                </thead>
                                <tbody id="tb_cuerpo_usuarios" style="font-size: 0.9rem !important;">
                                    <?php
$consulta = mysqli_query($conexion, "SELECT 
    hk.id,
    hk.codusu,
    hk.token,
    hk.created_at,
    hk.estado
FROM 
    huella_tkn_auto hk
WHERE 
    hk.estado IN (0, 1);
");
while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
    $id_usu = $row["id"];
    $token = $row["token"];
    $fechac = $row["created_at"];
    $estado = $row["estado"];
    $text_estado = ($estado == "1") ? "En Uso" : "sin uso";
    
    // Mostrar token completo
    if ($estado == "0") {
        $display_token = "<span class='copyable' style='color: blue; text-decoration: underline; cursor: pointer;'>" . $token . "</span>";
    } else {
        $display_token = substr($token, 0, -8) . "...";
    }

    if ($_SESSION['id'] == 4) { ?>
                                    <tr>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                onclick="delete_token()">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                        <th scope="row"><?= $id_usu ?></th>
                                        <td><?= $display_token ?></td>
                                        <td><?= $fechac ?></td>
                                        <td><?= $text_estado ?></td>
                                    </tr>
                                    <?php } else {
        if ($id_usu != 4) { ?>
                                    <tr>
                                        <td></td>
                                        <th scope="row"><?= $id_usu ?></th>
                                        <td><?= $display_token ?></td>
                                        <td><?= $fechac ?></td>
                                        <td><?= $text_estado ?></td>
                                    </tr>
                                    <?php }
    }
} ?>


                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


            <div class="d-flex justify-content-center my-4">
                <input type="number" id="cant_tokens" class="form-control col-22" style="width: 250px;" min="1"
                    placeholder="Ingrese una cantidad" required>
                <button type="button" class="col-3 button-85" onclick="create_token()">
                    <i class="fa-solid fa-floppy-disk"></i> Crear Tokens
                </button>
            </div>

            <div class="row justify-items-md-center">
                <div class="col align-items-center mb-3" id="modal_footer">
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
    var copyableElements = document.querySelectorAll('.copyable');

    copyableElements.forEach(function(element) {
        element.addEventListener('click', function() {
            var textToCopy = element.innerText || element.textContent;
            console.log('Texto a copiar:', textToCopy); // Verifica el texto a copiar

            navigator.clipboard.writeText(textToCopy).then(function() {
                console.log('Texto copiado exitosamente:', textToCopy);
                iziToast.success({
                    title: 'Copia exitosa',
                    position: 'topRight',
                    message: 'Token copiado al portapapeles: ' + textToCopy,
                    timeout: 1800
                });
            }).catch(function(error) {
                console.error('Error al copiar al portapapeles:', error);
            });
        });
    });


    function delete_token() {
        iziToast.info({
            title: '',
            message: 'Funcion Desabilitada ;C',
            position: 'topRight'
        });
    }

    function create_token() {
        let cant_t = parseInt(document.getElementById('cant_tokens').value);
        if (cant_t > 0) {
            // console.log("Valor enviado: " + cant_t);
            let tokens = [];
            // Generar los tokens
            for (let i = 0; i < cant_t; i++) {
                let token = srnPc();
                tokens.push(token);
            }
            // Enviar los tokens 
            $.ajax({
                url: "../../../src/cruds/crud_usuario.php",
                type: "POST",
                data: {
                    'condi': 'create_tokens',
                    'tokens': tokens
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.status === 1) {
                        data.tokens.forEach((token, index) => {
                            let maskedToken = token.slice(0, -8) + '********'; //TRUNCAR
                            iziToast.success({
                                title: 'Éxito',
                                message: 'Token creado: ' + maskedToken,
                                position: 'topRight'
                            });
                        });
                        printdiv2('#cuadro', '0');
                    } else {
                        iziToast.warning({
                            title: 'Advertencia',
                            message: data.mensaje,
                            position: 'topRight'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error en la solicitud AJAX:", error);
                    iziToast.error({
                        title: 'Error',
                        message: 'Error al crear los tokens: ' + error,
                        position: 'topRight'
                    });
                }
            });
        } else {
            iziToast.warning({
                title: 'Advertencia',
                message: 'Ingrese un valor mayor a 0.',
                position: 'topRight'
            });
        }
    }


    function srnPc() {
        var d = new Date();
        var dateint = d.getTime();
        var letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        var total = letters.length;
        var keyTemp = "";
        for (var i = 0; i < 6; i++) {
            keyTemp += letters[parseInt((Math.random() * (total - 1) + 1))];
        }
        keyTemp += dateint;
        return keyTemp;
    }
    </script>
    <?php

            break;

            case 'asignacion_huella':
                ?>
    <input type="text" value="asignacion_huella" id="condi" style="display: none;">
    <input type="text" value="usuario_01" id="file" style="display: none;">
    <div class="text" style="text-align:center">Configuraciones Huella</div>
    <style>
    .switch {
        font-size: 17px;
        position: relative;
        display: inline-block;
        width: 3.5em;
        height: 2em;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgb(182, 182, 182);
        transition: .4s;
        border-radius: 10px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 1.4em;
        width: 1.4em;
        border-radius: 8px;
        left: 0.3em;
        bottom: 0.3em;
        transform: rotate(270deg);
        background-color: rgb(255, 255, 255);
        transition: .4s;
    }

    .switch input:checked+.slider {
        background-color: #21cc4c;
    }

    .switch input:focus+.slider {
        box-shadow: 0 0 1px #2196F3;
    }

    .switch input:checked+.slider:before {
        transform: translateX(1.5em);
    }

    .card-header {
        display: flex;
        align-items: center;
        font-size: 1.25rem;
        /* Ajusta el tamaño del texto según sea necesario */
        color: #333;
        /* Color del texto */
    }

    .card-header svg {
        width: 24px;
        height: 24px;
        margin-left: 8px;
        fill: #333;
    }
    </style>

    <div class="card">
        <div class="card-header">
            Configuraciónes
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path
                    d="M19.14 12.936c.014-.305.02-.615.02-.936s-.007-.63-.02-.936l2.11-1.65c.192-.15.247-.42.12-.64l-2-3.464a.494.494 0 0 0-.592-.22l-2.49 1a8.77 8.77 0 0 0-1.616-.936l-.38-2.65a.502.502 0 0 0-.496-.42h-4a.502.502 0 0 0-.497.42l-.38 2.65a8.767 8.767 0 0 0-1.615.936l-2.491-1a.498.498 0 0 0-.592.22l-2 3.464a.504.504 0 0 0 .12.64l2.11 1.65c-.014.306-.02.616-.02.936s.007.63.02.936l-2.11 1.65a.504.504 0 0 0-.12.64l2 3.464c.136.23.43.31.68.22l2.49-1c.51.38 1.05.7 1.615.936l.38 2.65a.502.502 0 0 0 .497.42h4c.246 0 .455-.177.496-.42l.38-2.65c.566-.236 1.106-.556 1.616-.936l2.49 1c.25.09.545.01.68-.22l2-3.464a.504.504 0 0 0-.12-.64l-2.11-1.65zM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z" />
            </svg>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="row g-3">
                    <?php
            $sql2 = "SELECT
                gh.id,
                gh.descripcion,
                tv.estado
            FROM $db_name_general.tb_moduloshuella gh 
            INNER JOIN tb_validacioneshuella tv ON gh.id = tv.id";
            $result2 = $conexion->query($sql2);

            if ($result2->num_rows > 0) {
                while ($row2 = $result2->fetch_assoc()) {
                    $descripcion = $row2["descripcion"];
                    $estado = $row2["estado"];
            ?>
                    <div class="card">
                        <div class="card-body" style="width: 80%;">
                            <h5 class="card-title">Habilitar huella</h5>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <p class="card-text" style="width: 80%; margin: 0;"><?php echo $descripcion; ?></p>
                                <label class="switch" style="margin-left: 10px;">
                                    <!-- Asignar un id único basado en gh.id -->
                                    <input type="checkbox" id="check_recibo_<?php echo $row2['id']; ?>"
                                        <?php echo ($estado == 1) ? "checked" : ""; ?>
                                        onchange="enviarEstado(<?php echo $row2['id']; ?>, this.checked ? 1 : 0)">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            } else { 
                echo "<p>No hay datos disponibles.</p>";
            }
            ?>
                </div>
            </div>
        </div>
    </div>
    <script>

function enviarEstado(id, estado) {
   // console.log(id, estado);
    loaderefect(1);
    let xhr = new XMLHttpRequest();
    xhr.open("POST", "../../src/cruds/crud_cliente.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            // Oculta el loader 
            loaderefect(0);
            if (xhr.status === 200) {
                //console.log(xhr.responseText); 
                try {
                    let respuesta = JSON.parse(xhr.responseText);
                    if (respuesta.Mensaje && respuesta.Mensaje === "Estado actualizado correctamente") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Actualización Exitosa',
                            text: 'Actualizado '
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo actualizar  ' + (respuesta.Error ? respuesta.Error : '')
                        });
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en la respuesta',
                        text: 'La respuesta no es válida o no se pudo actualizar el estado.'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error en la solicitud al servidor. Por favor, inténtalo de nuevo.'
                });
            }
        }
    };
    xhr.send("condi=update_huellamodulos&id=" + id + "&estado=" + estado);
}
    </script>
    <?php
    
      
                  break;
}
//funcion para encriptar y desencriptar usuarios PUEDE SER REUTILIZADA 
// TAMBIENSE USA EN CRUD_USUARIO.PHP
function encriptar_desencriptar($mykey1, $mykey2, $action = 'encrypt', $string = false)
{
    $action = trim($action);
    $output = false;

    $myKey = $mykey1;
    $myIV = $mykey2;
    $encrypt_method = 'AES-256-CBC';

    $secret_key = hash('sha256', $myKey);
    $secret_iv = substr(hash('sha256', $myIV), 0, 16);

    if ($action && ($action == 'encrypt' || $action == 'decrypt') && $string) {
        $string = trim(strval($string));

        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
        };

        if ($action == 'decrypt') {
            $output = openssl_decrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
        };
    };
    return $output;
};
?>