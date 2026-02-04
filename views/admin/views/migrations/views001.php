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
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$user = $_SESSION['usu'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];
?>
<style>
    .card-image-container {
        height: 20rem;
        overflow: hidden;
    }

    .card-image-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: top;
    }

    .terminal_container {
        width: 100%;
        /* Se adapta al tamaño del padre */
        height: 100%;
        /* Se adapta al tamaño del padre */
    }

    .terminal_toolbar_custom {
        display: flex;
        height: 30px;
        align-items: center;
        padding: 0 8px;
        box-sizing: border-box;
        border-top-left-radius: 5px;
        border-top-right-radius: 5px;
        background: #212121;
        justify-content: space-between;
    }

    .terminal_buttons {
        display: flex;
        align-items: center;
    }

    .terminal_btn {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 0;
        margin-right: 5px;
        font-size: 8px;
        height: 12px;
        width: 12px;
        box-sizing: border-box;
        border: none;
        border-radius: 100%;
        background: linear-gradient(#7d7871 0%, #595953 100%);
        text-shadow: 0px 1px 0px rgba(255, 255, 255, 0.2);
        box-shadow: 0px 0px 1px 0px #41403A, 0px 1px 1px 0px #474642;
    }

    .terminal_btn-color {
        background: #ee411a;
    }

    .terminal_btn:hover {
        cursor: pointer;
    }

    .terminal_btn:focus {
        outline: none;
    }

    .terminal_add_tab {
        border: 1px solid #fff;
        color: #fff;
        padding: 0 6px;
        border-radius: 4px 4px 0 0;
        border-bottom: none;
        cursor: pointer;
    }

    .terminal_user_text {
        color: #d5d0ce;
        margin-left: 6px;
        font-size: 14px;
        line-height: 15px;
    }

    .terminal_body_custom {
        background: rgba(0, 0, 0, 0.6);
        height: calc(100% - 30px);
        padding-top: 2px;
        margin-top: -1px;
        font-size: 12px;
        border-bottom-left-radius: 5px;
        border-bottom-right-radius: 5px;
    }

    .terminal_prompt {
        display: flex;
    }

    .terminal_prompt span {
        margin-left: 4px;
    }

    .terminal_user {
        color: #1eff8e;
    }

    .terminal_location {
        color: #4878c0;
    }

    .terminal_bling {
        color: #dddddd;
    }

    .terminal_cursor {
        display: block;
        height: 14px;
        width: 5px;
        margin-left: 10px;
        animation: terminal_curbl 1200ms linear infinite;
    }

    @keyframes terminal_curbl {

        0% {
            background: #ffffff;
        }

        49% {
            background: #ffffff;
        }

        60% {
            background: transparent;
        }

        99% {
            background: transparent;
        }

        100% {
            background: #ffffff;
        }
    }

    .spinner-border {
        animation: spinner-border 0.75s linear infinite;
    }
</style>
<?php

switch ($condi) {
    case 'clientes':
        $puestos = array("ADM", "GER", "EJE", "ANA");
?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="clientes" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">

                        <div class="card-body">
                            <h5 class="card-title">DATOS DE CLIENTES</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls o xlsx. Este archivo debe
                                contener todas
                                las columnas obligatorias del formato de migracion de clientes. Asegúrese de que todos los datos
                                estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="validateDPI" id="validateDPIYes"
                                                value="yes" checked>
                                            <label class="form-check-label" for="validateDPIYes">
                                                Validar DPI's duplicados
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="validateDPI" id="validateDPINo"
                                                value="no">
                                            <label class="form-check-label" for="validateDPINo">
                                                No validar DPI
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info" role="alert">
                                <h4 class="alert-heading"><i class="fa-solid fa-circle-info"></i> Información importante</h4>
                                <p>Para facilitar el proceso de migración, puede descargar el formato de ejemplo:</p>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="fa-solid fa-file-excel"></i> formato_clientes.xlsx
                                        <small class="text-muted">(Incluye estructura y ejemplos)</small>
                                    </span>
                                    <a href="<?= BASE_URL ?>public/downloads/examples/formato_clientes.xlsx"
                                        class="btn btn-outline-primary btn-sm" download>
                                        <i class="fa-solid fa-download"></i> Descargar
                                    </a>
                                </div>
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    onclick="loadfilecompressed(0,'clientes',[[],[],['validateDPI'],[]]);">
                                    <i class="fa-solid fa-info"></i> Revisar
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'clientes',[[],[],['validateDPI'],[]]);">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'cremcre_meta':
        $puestos = array("ADM", "GER", "EJE", "ANA");
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="loadfilesventas" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">DATOS DE CREDITOS</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls o xlsx. Este archivo debe
                                contener todas
                                las columnas obligatorias del formato de migracion de datos de creditos [cremcre_meta].
                                Asegúrese de que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <?php if (in_array($_SESSION['puesto'], $puestos)) { ?>
                                <div class="position-relative">
                                    <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                        onclick="loadfilecompressed(0,'cremcre');">
                                        <i class="fa-solid fa-info"></i> Revisar
                                    </button>
                                    <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                        onclick="loadfilecompressed(1,'cremcre');">
                                        <i class="fa-regular fa-floppy-disk"></i> Migrar
                                    </button>
                                    <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            <?php } else {
                                echo '<div class="alert alert-danger" role="alert">No tiene permisos para realizar esta operacion</div>';
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>


    <?php
        break;
    case 'cremcre_meta_aux':
        $puestos = array("ADM", "GER", "EJE", "ANA");
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="loadfilesventas" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">Completar Migracion de creditos</h5>
                            <p class="card-text">Si ya hizo migraciones de creditos [CREMCRE_META] DE MANERA CORRECTA, DEBE
                                GENERAR LOS DESEMBOLSOS.
                                LO PUEDE GENERAR DIGITANDO EL CODIGO DE MIGRACION PROPORCIONADO POR EL PROGRAMA EN EL CAMPO DE
                                ABAJO
                                Ó EL CODIGO QUE LE PUSO A SU MIGRACION (CONSULTAR DOCUMENTACION)</p>
                            <div class="form-floating">
                                <input type="text" class="form-control" id="migrationCode" placeholder="Codigo de migracion">
                                <label for="migrationCode">Digite el codigo de migracion de creditos</label>
                            </div>
                            <br>
                            <?php if (in_array($_SESSION['puesto'], $puestos)) { ?>
                                <div class="position-relative">
                                    <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                        onclick="process(0,'cremcre_step2');">
                                        <i class="fa-solid fa-info"></i> Revisar
                                    </button>
                                    <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                        onclick="process(1,'cremcre_step2');">
                                        <i class="fa-regular fa-floppy-disk"></i> Migrar
                                    </button>
                                    <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            <?php } else {
                                echo '<div class="alert alert-danger" role="alert">No tiene permisos para realizar esta operacion</div>';
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>


    <?php
        break;
    case 'gen_ppg':
        $puestos = array("ADM", "GER", "EJE", "ANA");
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="loadfilesventas" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">Generacion de planes de pago</h5>
                            <p class="card-text">Si ya hizo migraciones de creditos [CREMCRE_META] DE MANERA CORRECTA, PUEDE
                                GENERAR LOS PLANES DE PAGO.
                                LOS PUEDE GENERAR DIGITANDO EL CODIGO DE MIGRACION PROPORCIONADO POR EL PROGRAMA EN EL CAMPO DE
                                ABAJO
                                Ó EL CODIGO QUE LE PUSO A SU MIGRACION (CONSULTAR DOCUMENTACION)</p>
                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                                    <use xlink:href="#exclamation-triangle-fill" />
                                </svg>
                                <div>Falta soporte para créditos diarios y semanales!!! </div>
                            </div>
                            <div class="form-floating">
                                <input type="text" class="form-control" id="migrationCode" placeholder="Codigo de migracion">
                                <label for="migrationCode">Digite el codigo de migracion de creditos</label>
                            </div>
                            <br>
                            <?php if (in_array($_SESSION['puesto'], $puestos)) { ?>
                                <div class="position-relative">
                                    <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                        onclick="process(0,'gen_ppg');">
                                        <i class="fa-solid fa-info"></i> Revisar
                                    </button>
                                    <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                        onclick="process(1,'gen_ppg');">
                                        <i class="fa-regular fa-floppy-disk"></i> Migrar
                                    </button>
                                    <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            <?php } else {
                                echo '<div class="alert alert-danger" role="alert">No tiene permisos para realizar esta operacion</div>';
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>


    <?php
        break;
    case 'cre_ppgMigrate':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="cre_ppgMigrate" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">Migracion de planes de pagos</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls, xlsx o json. Este archivo
                                debe contener todas las columnas obligatorias del formato de migracion de datos de planes de pago.
                                Asegúrese de que todos los datos estén correctamente formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls,.json" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="mb-3">
                                <label for="lotes" class="form-label">Guardar cada lote de ...</label>
                                <input type="text" class="form-control" id="lotes" placeholder="1000" value="1000">
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    title="Si necesita revisar mas datos, por favor cambie el valor de lotes a la cantidad de registros que desea revisar"
                                    onclick="loadfilecompressed(0,'creppgMigrate',[['idcuenta1','idcuenta2','lotes','seguimiento'],[],['checkCuenta'],[]]);">
                                    <i class="fa-solid fa-info"></i> Revisar el primer lote
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'creppgMigrate',[['idcuenta1','idcuenta2','lotes','seguimiento'],[],['checkCuenta'],[]]);">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="row">
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="idcuenta1" class="form-label">Campo en la data para seleccionar la
                                        cuenta</label>
                                    <input type="text" class="form-control" id="idcuenta1" placeholder="ccodcta"
                                        value="ccodcta">
                                </div>
                                <div class="mb-3">
                                    <label for="idcuenta2" class="form-label">Campo en la tabla de cuentas de credito</label>
                                    <input type="text" class="form-control" id="idcuenta2" placeholder="CCODCTA"
                                        value="CCODCTA">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCuenta" id="radioOmitir" value="Si">
                                    <label class="form-check-label" for="radioOmitir">
                                        Ignorar el registro de la cuota si la cuenta no existe o el código es incorrecto
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCuenta" id="radioNoOmitir"
                                        value="No" checked>
                                    <label class="form-check-label" for="radioNoOmitir">
                                        Detener la migracion si la cuenta no existe o el código es incorrecto
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="seguimiento" class="form-label">Seguimiento de registros</label>
                                    <input type="number" class="form-control" id="seguimiento" placeholder="1" value="1" min="1"
                                        max="1000">
                                    <small class="form-text text-muted">
                                        Indica cada cuántos registros procesados se mostrará el progreso. Valores mayores
                                        optimizan recursos.
                                        Para datos grandes, se recomienda un valor de 10, 50 o más.
                                    </small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php

        break;
    case 'credkar':
        $puestos = array("ADM", "GER", "EJE", "ANA");
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="credkar" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">DATOS DE PAGOS DE CREDITOS (KARDEX) [CREDKAR]</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls o xlsx. Este archivo debe
                                contener todas
                                las columnas obligatorias del formato de migracion de datos de pagos de creditos [credkar].
                                Asegúrese de que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <?php if (in_array($_SESSION['puesto'], $puestos)) { ?>
                                <div class="position-relative">
                                    <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                        onclick="loadfilecompressed(0,'credkar');">
                                        <i class="fa-solid fa-info"></i> Revisar
                                    </button>
                                    <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                        onclick="loadfilecompressed(1,'credkar');">
                                        <i class="fa-regular fa-floppy-disk"></i> Migrar
                                    </button>
                                    <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            <?php } else {
                                echo '<div class="alert alert-danger" role="alert">No tiene permisos para realizar esta operacion</div>';
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'ahomtip':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="ahomtip" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">PRODUCTOS DE AHORRO</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls o xlsx. Este archivo debe
                                contener todas
                                las columnas obligatorias del formato de migracion de datos de productos de ahorro. Asegúrese de
                                que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    onclick="loadfilecompressed(0,'ahomtip');">
                                    <i class="fa-solid fa-info"></i> Revisar
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'ahomtip');">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'ahomcta':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="ahomcta" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">CUENTAS DE AHORRO</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls o xlsx. Este archivo debe
                                contener todas
                                las columnas obligatorias del formato de migracion de datos de cuentas de ahorro. Asegúrese de
                                que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="mb-3">
                                <label for="lotes" class="form-label">Guardar cada lote de ...</label>
                                <input type="text" class="form-control" id="lotes" placeholder="500" value="500">
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    onclick="loadfilecompressed(0,'ahomcta',[['idCliente1','idCliente2','idProducto1','idProducto2','lotes'],[],['checkCliente','checkProducto'],[]]);">
                                    <i class="fa-solid fa-info"></i> Revisar
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'ahomcta',[['idCliente1','idCliente2','idProducto1','idProducto2','lotes'],[],['checkCliente','checkProducto'],[]]);">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="row">
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="idCliente1" class="form-label">Campo en la data para seleccionar al
                                        cliente</label>
                                    <input type="text" class="form-control" id="idCliente1" placeholder="ccodcli"
                                        value="ccodcli">
                                </div>
                                <div class="mb-3">
                                    <label for="idCliente2" class="form-label">Campo en la tabla de clientes</label>
                                    <input type="text" class="form-control" id="idCliente2" placeholder="idcod_cliente"
                                        value="idcod_cliente">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCliente" id="radioOmitir"
                                        value="Si">
                                    <label class="form-check-label" for="radioOmitir">
                                        Ignorar el registro de la cuenta si el cliente no existe o el código es incorrecto
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCliente" id="radioNoOmitir"
                                        value="No" checked>
                                    <label class="form-check-label" for="radioNoOmitir">
                                        No ignorar el registro de cuentas si el cliente no existe o el código es incorrecto (Se
                                        detendrá la migración)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="idProducto1" class="form-label">Campo en la data para seleccionar el
                                        producto</label>
                                    <input type="text" class="form-control" id="idProducto1" placeholder="ccodtip"
                                        value="ccodtip">
                                </div>
                                <div class="mb-3">
                                    <label for="idProducto2" class="form-label">Campo en la tabla de productos de
                                        ahorros</label>
                                    <input type="text" class="form-control" id="idProducto2" placeholder="ccodtip"
                                        value="ccodtip">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkProducto" id="radioOmitirProducto"
                                        value="Si">
                                    <label class="form-check-label" for="radioOmitirProducto">
                                        Omitir el registro de la cuenta si el producto no existe o el código es inválido
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkProducto" id="radioNoOmitirProducto"
                                        value="No" checked>
                                    <label class="form-check-label" for="radioNoOmitirProducto">
                                        Detener la migracion si el producto no existe o el código es inválido
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'ahommov':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="ahommov" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">MOVIMIENTOS DE AHORRO</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls, xlsx o json. Este archivo
                                debe contener todas
                                las columnas obligatorias del formato de migracion de datos de movimientos de ahorro. Asegúrese
                                de que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls,.json" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="mb-3">
                                <label for="lotes" class="form-label">Guardar cada lote de ...</label>
                                <input type="text" class="form-control" id="lotes" placeholder="1000" value="1000">
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    title="Si necesita revisar mas datos, por favor cambie el valor de lotes a la cantidad de registros que desea revisar"
                                    onclick="loadfilecompressed(0,'ahommov',[['idcuenta1','idcuenta2','lotes','seguimiento'],[],['checkCuenta'],[]]);">
                                    <i class="fa-solid fa-info"></i> Revisar el primer lote
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'ahommov',[['idcuenta1','idcuenta2','lotes','seguimiento'],[],['checkCuenta'],[]]);">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="row">
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="idcuenta1" class="form-label">Campo en la data para seleccionar al la
                                        cuenta</label>
                                    <input type="text" class="form-control" id="idcuenta1" placeholder="ccodaho"
                                        value="ccodaho">
                                </div>
                                <div class="mb-3">
                                    <label for="idcuenta2" class="form-label">Campo en la tabla de cuentas de ahorro</label>
                                    <input type="text" class="form-control" id="idcuenta2" placeholder="ccodaho"
                                        value="ccodaho">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCuenta" id="radioOmitir" value="Si">
                                    <label class="form-check-label" for="radioOmitir">
                                        Ignorar el registro del movimiento si la cuenta no existe o el código es incorrecto
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCuenta" id="radioNoOmitir"
                                        value="No" checked>
                                    <label class="form-check-label" for="radioNoOmitir">
                                        No ignorar el registro del movimiento si la cuenta no existe o el código es incorrecto
                                        (Se detendrá la migración)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="seguimiento" class="form-label">Seguimiento de registros</label>
                                    <input type="number" class="form-control" id="seguimiento" placeholder="1" value="1" min="1"
                                        max="1000">
                                    <small class="form-text text-muted">
                                        Indica cada cuántos registros procesados se mostrará el progreso. Valores mayores
                                        optimizan recursos.
                                        Para datos grandes, se recomienda un valor de 10, 50 o más.
                                    </small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'aprtip':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="aprtip" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">PRODUCTOS DE AHORRO</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls o xlsx. Este archivo debe
                                contener todas
                                las columnas obligatorias del formato de migracion de datos de productos de ahorro. Asegúrese de
                                que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    onclick="loadfilecompressed(0,'aprtip');">
                                    <i class="fa-solid fa-info"></i> Revisar
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'aprtip');">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'aprcta':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="aprcta" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">CUENTAS DE APORTACIONES</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls o xlsx. Este archivo debe
                                contener todas
                                las columnas obligatorias del formato de migracion de datos de cuentas de ahorro. Asegúrese de
                                que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="mb-3">
                                <label for="lotes" class="form-label">Guardar cada lote de ...</label>
                                <input type="text" class="form-control" id="lotes" placeholder="500" value="500">
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    onclick="loadfilecompressed(0,'aprcta',[['idCliente1','idCliente2','idProducto1','idProducto2','lotes'],[],['checkCliente','checkProducto'],[]]);">
                                    <i class="fa-solid fa-info"></i> Revisar
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'aprcta',[['idCliente1','idCliente2','idProducto1','idProducto2','lotes'],[],['checkCliente','checkProducto'],[]]);">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="row">
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="idCliente1" class="form-label">Campo en la data para seleccionar al
                                        cliente</label>
                                    <input type="text" class="form-control" id="idCliente1" placeholder="ccodcli"
                                        value="ccodcli">
                                </div>
                                <div class="mb-3">
                                    <label for="idCliente2" class="form-label">Campo en la tabla de clientes</label>
                                    <input type="text" class="form-control" id="idCliente2" placeholder="idcod_cliente"
                                        value="idcod_cliente">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCliente" id="radioOmitir"
                                        value="Si">
                                    <label class="form-check-label" for="radioOmitir">
                                        Ignorar el registro de la cuenta si el cliente no existe o el código es incorrecto
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCliente" id="radioNoOmitir"
                                        value="No" checked>
                                    <label class="form-check-label" for="radioNoOmitir">
                                        No ignorar el registro de cuentas si el cliente no existe o el código es incorrecto (Se
                                        detendrá la migración)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="idProducto1" class="form-label">Campo en la data para seleccionar el
                                        producto</label>
                                    <input type="text" class="form-control" id="idProducto1" placeholder="ccodtip"
                                        value="ccodtip">
                                </div>
                                <div class="mb-3">
                                    <label for="idProducto2" class="form-label">Campo en la tabla de productos de
                                        aportaciones</label>
                                    <input type="text" class="form-control" id="idProducto2" placeholder="ccodtip"
                                        value="ccodtip">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkProducto" id="radioOmitirProducto"
                                        value="Si">
                                    <label class="form-check-label" for="radioOmitirProducto">
                                        Omitir el registro de la cuenta si el producto no existe o el código es inválido
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkProducto" id="radioNoOmitirProducto"
                                        value="No" checked>
                                    <label class="form-check-label" for="radioNoOmitirProducto">
                                        Detener la migracion si el producto no existe o el código es inválido
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'aprmov':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="aprmov" style="display: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-body">
                            <h5 class="card-title">MOVIMIENTOS DE APORTACIONES</h5>
                            <p class="card-text">Se requiere que cargue un archivo en formato .xls, xlsx o json. Este archivo
                                debe contener todas
                                las columnas obligatorias del formato de migracion de datos de movimientos de aportaciones.
                                Asegúrese de que todos los datos estén correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls,.json" />
                                <label class="input-group-text" for="excelFile">Cargar</label>
                            </div>
                            <div class="mb-3">
                                <label for="lotes" class="form-label">Guardar cada lote de ...</label>
                                <input type="text" class="form-control" id="lotes" placeholder="1000" value="1000">
                            </div>
                            <div class="position-relative">
                                <button id="migrarBtn2" type="button" class="btn btn-outline-primary"
                                    title="Si necesita revisar mas datos, por favor cambie el valor de lotes a la cantidad de registros que desea revisar"
                                    onclick="loadfilecompressed(0,'aprmov',[['idcuenta1','idcuenta2','lotes','seguimiento'],[],['checkCuenta'],[]]);">
                                    <i class="fa-solid fa-info"></i> Revisar el primer lote
                                </button>
                                <button id="migrarBtn" type="button" class="btn btn-outline-success"
                                    onclick="loadfilecompressed(1,'aprmov',[['idcuenta1','idcuenta2','lotes','seguimiento'],[],['checkCuenta'],[]]);">
                                    <i class="fa-regular fa-floppy-disk"></i> Migrar
                                </button>
                                <div id="spinner" class="spinner-border text-primary" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="row">
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="idcuenta1" class="form-label">Campo en la data para seleccionar al la
                                        cuenta</label>
                                    <input type="text" class="form-control" id="idcuenta1" placeholder="ccodaport"
                                        value="ccodaport">
                                </div>
                                <div class="mb-3">
                                    <label for="idcuenta2" class="form-label">Campo en la tabla de cuentas de ahorro</label>
                                    <input type="text" class="form-control" id="idcuenta2" placeholder="ccodaport"
                                        value="ccodaport">
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCuenta" id="radioOmitir" value="Si">
                                    <label class="form-check-label" for="radioOmitir">
                                        Ignorar el registro del movimiento si la cuenta no existe o el código es incorrecto
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="checkCuenta" id="radioNoOmitir"
                                        value="No" checked>
                                    <label class="form-check-label" for="radioNoOmitir">
                                        No ignorar el registro del movimiento si la cuenta no existe o el código es incorrecto
                                        (Se detendrá la migración)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                                <div class="mb-3">
                                    <label for="seguimiento" class="form-label">Seguimiento de registros</label>
                                    <input type="number" class="form-control" id="seguimiento" placeholder="1" value="1" min="1"
                                        max="1000">
                                    <small class="form-text text-muted">
                                        Indica cada cuántos registros procesados se mostrará el progreso. Valores mayores
                                        optimizan recursos.
                                        Para datos grandes, se recomienda un valor de 10, 50 o más.
                                    </small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div id="progress"></div>
            <div class="terminal_container" style="max-height: 40rem; overflow-y: auto;">
                <div class="terminal_toolbar_custom">
                    <div class="terminal_buttons">
                        <button class="terminal_btn terminal_btn-color"></button>
                        <button class="terminal_btn"></button>
                        <button class="terminal_btn"></button>
                    </div>
                    <p class="terminal_user_text">Registro: ~</p>
                    <div class="terminal_add_tab">
                        +
                    </div>
                </div>
                <div class="terminal_body_custom" id="progressconsole">
                    <div class="terminal_prompt">
                        <span class="terminal_user"><?= $user; ?>:</span>
                        <span class="terminal_location">~</span>
                        <span class="terminal_bling">$ ola</span>
                        <!-- <span class="terminal_cursor"></span> -->
                    </div>
                </div>
            </div>
        </div>
<?php
        break;
}

?>
<script>
    function listenForProgress(filemigration) {
        // const progressElement = document.getElementById("progress");
        const progressElement = document.getElementById("progressconsole");
        progressElement.innerHTML = "";

        const source = new EventSource('views/migrations/migrations/' + filemigration + '.php?listen=1');

        source.addEventListener('message', function(event) {
            console.log("Mensaje recibido:", event.data);
            try {
                const data = JSON.parse(event.data);
                // progressElement.innerHTML += `<p>${data.message}</p>`;
                progressElement.innerHTML += `
                        <div class="terminal_prompt">
                            <span class="terminal_user"><?= $user; ?>:</span>
                            <span class="terminal_location">~</span>
                            <span class="terminal_bling">$ ${data.message}</span>
                        </div>`;
            } catch (e) {
                console.error("Error al parsear el mensaje:", e);
            }
        });

        source.addEventListener('progress', function(event) {
            const data = JSON.parse(event.data);
            // progressElement.innerHTML += `<p>Progreso: ${data.message}</p>`;
            progressElement.innerHTML += `
                <div class="terminal_prompt">
                    <span class="terminal_user"><?= $user; ?>:</span>
                    <span class="terminal_location">~</span>
                    <span class="terminal_bling">$ ${data.message}</span>
                </div>`;
        });

        source.addEventListener('done', function(event) {
            shutdownloader(1);
            const data = JSON.parse(event.data);
            progressElement.innerHTML += `<p style="color: green;"> isusefu!: ${data.message}</p>`;
            source.close();
        });

        source.addEventListener('error', function(event) {
            shutdownloader(1);
            // console.error("Error SSE:", event);

            if (event.data) {
                try {
                    const data = JSON.parse(event.data);
                    let errorMessage = '';

                    if (data.details) {
                        // Construir un mensaje de error más detallado
                        errorMessage = `
                        <div class="error-details">
                            <p style="color: red;">Error: ${data.displayMessage}</p>
                            <p>Línea: ${data.details.line}</p>
                            <p>Tipo: ${data.details.type}</p>
                        </div>`;
                    } else {
                        errorMessage = `<p style="color: red;">Error: ${data.message}</p>`;
                    }

                    progressElement.innerHTML += errorMessage;

                    // Registrar detalles completos en la consola para debugging
                    // console.error('Detalles del error:', data.details);
                } catch (e) {
                    // Si no podemos parsear el error, verificar si es un error de conexión
                    if (event.target.readyState === EventSource.CLOSED) {
                        progressElement.innerHTML += `
                        <div class="terminal_prompt">
                            <span class="terminal_user">system:</span>
                            <span class="terminal_location">error</span>
                            <span class="terminal_bling" style="color: red;">$ Conexión perdida con el servidor. Por favor, verifique su conexión.</span>
                        </div>`;
                    } else {
                        progressElement.innerHTML += `
                        <div class="terminal_prompt">
                            <span class="terminal_user">system:</span>
                            <span class="terminal_location">error</span>
                            <span class="terminal_bling" style="color: red;">$ Error inesperado: ${e.message}</span>
                        </div>`;
                    }
                }
            }
            source.close();
        });
    }
</script>