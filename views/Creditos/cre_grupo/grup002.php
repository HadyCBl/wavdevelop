<?php
include __DIR__ . '/../../../includes/Config/config.php';
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
// require_once __DIR__ . '/../../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../includes/Config/database.php';
require_once __DIR__ . '/../../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';

use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
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

//++++++++++++++
$usuario = $_SESSION["id"];
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include_once "../../../src/cris_modales/mdls_cre_indi02.php";
include '../../../src/funcphp/valida.php';
include 'functions/group_functions.php';
$condi = $_POST["condi"];
switch ($condi) {
    case 'statusaccount':
        $datpost = $_POST["xtra"];
        /*         echo ('<pre>');
            print_r($datpost);
            echo ('</pre>'); */
        $extra = $datpost[0];
        $bandera = "Grupo sin cuentas";
        if ($extra != "0") {
            $numciclo = $datpost[1];
            //CREDITOS DEL GRUPO
            $datos[] = [];
            $datacre = mysqli_query($conexion, 'SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
                cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.CodAnal,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls
                From cremcre_meta cre
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
                WHERE cre.TipoEnti="GRUP" AND (cre.CESTADO="F" OR cre.CESTADO="G") AND cre.CCodGrupo="' . $extra . '" AND cre.NCiclo=' . $numciclo);

            $i = 0;
            while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
                $datos[$i] = $da;
                $i++;
                $bandera = "";
            }
        }
        $firstcuota = ($bandera == "" && $datos[0]["Cestado"] == "D") ? $datos[0]["DfecPago"] : $hoy;
        $fecdes = ($bandera == "" && $datos[0]["Cestado"] == "D") ? $datos[0]["DFecDsbls"] : $hoy;
?>
        <input type="text" readonly hidden value='statusaccount' id='condi'>
        <input type="text" readonly hidden value='grup002' id='file'>
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>ESTADO DE CUENTA DE CREDITOS GRUPAL</h4>
            </div>
            <div class="card-body">
                <div class="row contenedort">
                    <h5>Detalle de Grupo</h5>
                    <div class="row mb-3">
                        <div class="col-sm-5">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Nombre Grupo</span>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["NombreGrupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="codgrup" class="input-group-addon">Codigo de Grupo</label>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(9rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["codigo_grupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <br>

                            <button id="findgrupo" onclick="loadconfig('any',['F','G'])" type="button" class="btn btn-primary"
                                data-bs-toggle="modal" data-bs-target="#buscargrupo">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo
                            </button>

                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Direccion</span>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["direc"] . '</span>'; ?>
                            </div>
                        </div>

                        <div class="col-sm-2">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="nciclo" class="input-group-addon">Ciclo</label>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' .  $numciclo . '</span>'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <?php

                            ?>
                        </div>
                    </div>
                    <?php if ($extra != "0" && $bandera != "") {
                        echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                    } else if ($extra != "0") {
                        $est = ($datos[0]["Cestado"] == "F") ? " VIGENTE" : " ";
                        echo 'ESTADO: ' . $est;
                    }
                    ?>
                </div>
                <div class="row contenedort"
                    style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
                    <h5>CIENTES DEL GRUPO</h5>
                    <div class="accordion" id="cuotas">
                        <?php
                        if ($bandera == "") {
                            $j = 0;
                            while ($j < count($datos)) {
                                $ccodta = $datos[$j]["CCODCTA"];
                                $estado = $datos[$j]["Cestado"];
                                $codcli = $datos[$j]["idcod_cliente"];
                                $name = $datos[$j]["short_name"];
                                $fecnac = date("d-m-Y", strtotime($datos[$j]["date_birth"]));
                                $urlimg = $datos[$j]["url_img"];
                                $dpi = $datos[$j]["no_identifica"];
                                $monsol = $datos[$j]["MontoSol"];
                                $monsug = $datos[$j]["MonSug"];
                                $fecdes =  date("d-m-Y", strtotime($datos[$j]["DFecDsbls"]));
                                $idit = "data" . $j;
                                $imgurl = __DIR__ . '/../../../../../' . $urlimg;
                                if (!is_file($imgurl)) {
                                    $src = '../../includes/img/fotoClienteDefault.png';
                                } else {
                                    $imginfo   = getimagesize($imgurl);
                                    $mimetype  = $imginfo['mime'];
                                    $imageData = base64_encode(file_get_contents($imgurl));
                                    $src = 'data:' . $mimetype . ';base64,' . $imageData;
                                }
                        ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <div class="row">
                                            <div class="col-12">
                                                <button id="<?php echo 'bt' . $j; ?>" class="accordion-button collapsed"
                                                    aria-expanded="true">
                                                    <div class="row" style="width:100%;font-size: 0.90rem;">
                                                        <div class="col-1">
                                                            <img width="auto" height="40" id="vistaPrevia"
                                                                src="<?php echo $src; ?>" /><br />
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <span class="input-group-addon"><?php echo $ccodta; ?></span>
                                                                </div>
                                                                <div class="col-12">
                                                                    <input id="<?php echo 'ccodcta' . $j; ?>" type="text"
                                                                        value="<?php echo $ccodta; ?>" hidden>
                                                                    <span
                                                                        class="input-group-addon"><?php echo strtoupper($name); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="row">
                                                                <span class="input-group-addon">Identificacion</span>
                                                                <span class="input-group-addon"><?php echo $dpi; ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="row">
                                                                <span class="input-group-addon">Monto Aprobado</span>
                                                                <span class="input-group-addon"><?php echo $monsug; ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="row">
                                                                <span class="input-group-addon">Fecha Otorgamiento</span>
                                                                <span class="input-group-addon"><?php echo $fecdes; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </h2>
                                </div>
                        <?php
                                $j++;
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <div class="row justify-items-md-center">
                <div class="col align-items-center" id="modal_footer">
                    <?php
                    if ($bandera == "") {
                        echo '<button type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[' . $extra . ',' . $numciclo . ',1]], `pdf`, `est_cuenta_deta`, 0)">
                                        <i class="fa fa-floppy-disk"></i> Estado de Cuenta Detallado
                                    </button>';
                        echo '<button type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[' . $extra . ',' . $numciclo . ',2]], `pdf`, `est_cuenta_deta`, 0)">
                                        <i class="fa fa-floppy-disk"></i> Estado de Cuenta Consolidado
                                    </button>';
                    }
                    ?>
                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro', '0')">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="window.location.reload();">
                        <i class="fa-solid fa-circle-xmark"></i> Salir
                    </button>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'docsgrupal':
        $extra = $_POST['xtra'];
        $showmensaje = false;
        try {

            if ($extra[0] == "0") {
                throw new SoftException("Seleccione un grupo");
            }

            $idGrupo = $extra[0];
            $numciclo = $extra[1];
            $estadocredito = $extra[2];

            $database->openConnection();

            $query = "SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
                cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.CodAnal,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls
                From cremcre_meta cre
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
                WHERE cre.TipoEnti='GRUP' AND cre.cestado=? AND cre.CCodGrupo=? AND cre.NCiclo=?";

            $datos = $database->getAllResults($query, [
                $extra[2],
                $extra[0],
                $extra[1]
            ]);

            $status = true;
        } catch (SoftException $se) {
            $mensaje = $se->getMessage();
            $status = false;
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="text" readonly hidden value='docsgrupal' id='condi'>
        <input type="text" readonly hidden value='grup002' id='file'>
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>GENERACION DE DOCUMENTOS GRUPAL</h4>
            </div>
            <div class="card-body">
                <?php if (!$status): ?>
                    <div class="alert alert-warning"><?= $mensaje ?></div>
                <?php endif; ?>
                <div class="row contenedort">
                    <h5>Detalle de Grupo</h5>
                    <div class="row mb-3">
                        <div class="col-sm-5">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Nombre Grupo</span>
                                <?= ($status) ? '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["NombreGrupo"] . '</span>' : ''; ?>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="codgrup" class="input-group-addon">Codigo de Grupo</label>
                                <?= ($status) ? '<span style="font-size:1rem;width:min(9rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["codigo_grupo"] . '</span>' : ''; ?>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <br>
                            <button id="findgrupo" onclick="loadconfig('any',['A','D','E','F'])" type="button"
                                class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#buscargrupo">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Direccion</span>
                                <?= ($status) ? '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["direc"] . '</span>' : ''; ?>
                            </div>
                        </div>

                        <div class="col-sm-2">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="nciclo" class="input-group-addon">Ciclo</label>
                                <?= ($status) ? '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' .  $numciclo . '</span>' : ''; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($status) {
                        $estados = ['A' => 'SOLICITADO', 'D' => 'ANALIZADO', 'E' => 'APROBADO', 'F' => 'VIGENTE'];
                        $est = $estados[$datos[0]["Cestado"]] ?? 'DESCONOCIDO';
                        echo 'ESTADO: ' . $est;
                    }
                    ?>
                </div>
                <div class="row contenedort"
                    style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
                    <h5>CIENTES DEL GRUPO</h5>
                    <div class="accordion" id="cuotas">
                        <?php
                        if ($status) {
                            $j = 0;
                            while ($j < count($datos)) {
                                $ccodta = $datos[$j]["CCODCTA"];
                                $estado = $datos[$j]["Cestado"];
                                $codcli = $datos[$j]["idcod_cliente"];
                                $name = $datos[$j]["short_name"];
                                $fecnac = Date::isValid($datos[$j]["date_birth"]) ? Date::toDMY($datos[$j]["date_birth"]) : '';
                                $urlimg = $datos[$j]["url_img"];
                                $dpi = $datos[$j]["no_identifica"];
                                $monsol = $datos[$j]["MontoSol"];
                                $monsug = $datos[$j]["MonSug"];
                                $fecdes = Date::isValid($datos[$j]["DFecDsbls"]) ? Date::toDMY($datos[$j]["DFecDsbls"]) : '';
                                $idit = "data" . $j;
                                $imgurl = __DIR__ . '/../../../../../' . $urlimg;
                                if (!is_file($imgurl)) {
                                    $src = '../../includes/img/fotoClienteDefault.png';
                                } else {
                                    $imginfo   = getimagesize($imgurl);
                                    $mimetype  = $imginfo['mime'];
                                    $imageData = base64_encode(file_get_contents($imgurl));
                                    $src = 'data:' . $mimetype . ';base64,' . $imageData;
                                }
                        ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <div class="row accordion-button collapsed" style="width:100%;font-size: 0.80rem;">
                                            <div class="col-2">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <span class="input-group-addon"><?php echo $ccodta; ?></span>
                                                    </div>
                                                    <div class="col-12">
                                                        <input id="<?php echo 'ccodcta' . $j; ?>" type="text"
                                                            value="<?php echo $ccodta; ?>" hidden>
                                                        <span class="input-group-addon"><?php echo strtoupper($name); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="row">
                                                    <span class="input-group-addon">Identificacion</span>
                                                    <span class="input-group-addon"><?php echo $dpi; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="row">
                                                    <span class="input-group-addon">Monto Aprobado</span>
                                                    <span class="input-group-addon"><?php echo $monsug; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="row">
                                                    <span class="input-group-addon">Fecha Otorgamiento</span>
                                                    <span class="input-group-addon"><?php echo $fecdes; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-4 align-items-center">
                                                <?php
                                                if ($estado == "E") {
                                                    echo '<button type="button" class="btn btn-warning" onclick="reportes([[],[],[],[`' . $ccodta . '`]], `pdf`, `19`,0,1)"> Contrato </button>';
                                                }
                                                if ($estado == "F") {
                                                    // echo '<button type="button" class="btn btn-warning btn-sm" onclick="reportes([[],[],[],[`' . $ccodta . '`]], `pdf`, `19`,0,1)"> Contrato </button>';
                                                    echo '<button type="button" class="btn btn-outline-primary btn-sm" onclick="reportes([[], [], [], [`' . $ccodta . '`]], `pdf`,`18`, 0,1);">
                                                                    <i class="fa-solid fa-dog"></i> Nota de Desembolso</button>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </h2>
                                </div>
                        <?php
                                $j++;
                            }
                        }
                        ?>

                    </div>
                </div>
            </div>
            <div class="row justify-items-md-center">
                <div class="col align-items-center" id="modal_footer">
                    <?php
                    if ($status) {
                        if ($estadocredito == "A") {
                            echo '<button type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[' . $idGrupo . ',' . $numciclo . ']], `pdf`, `ficha_solicitud`, 0)">
                                    <i class="fa-solid fa-file-export"></i> Ficha de Solicitud
                                </button>';
                        }
                        if ($estadocredito == "D") {
                            echo '<button type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[' . $idGrupo . ',' . $numciclo . ']], `pdf`, `ficha_analisis`, 0)">
                                    <i class="fa-solid fa-file-pen"></i> Ficha de analisis
                                </button>';
                        }
                        if ($estadocredito == "E") {
                            echo '<button type="button" class="btn btn-outline-primary" onclick="reportes([[],[],[],[' . $idGrupo . ',' . $numciclo . ']], `pdf`, `ficha_aprobacion`, 0)">
                                    <i class="fa-solid fa-file-shield"></i> Ficha de aprobacion
                                    </button>  ';
                            echo '<button type="button" class="btn btn-outline-success" onclick="planGrup(' . $idGrupo . ',' . $numciclo . ', 1)">
                                        <i class="fa-solid fa-file-excel"></i> Planilla
                                    </button>';
                        }
                        if ($estadocredito == "F") {
                            echo '<button type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[' . $idGrupo . ',' . $numciclo . ',2]], `pdf`, `16`, 0,1)">
                                    <i class="fa-solid fa-file-lines"></i> Pagaré
                                </button>';
                        }
                    }
                    ?>
                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro', '0')">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
    <?php
        break;
    //Plan de pagos grupales
    case 'planPagosGru':
        include_once "../../../src/cris_modales/mdls_planPagosGru.php";
    ?>

        <div class="card">
            <h5 class="card-header">Plan de pago editable de los grupos</h5>
            <div class="card-body">
                <!-- inputs especiales -->
                <input type="text" id="idGrup" disabled hidden>

                <div class="row">
                    <div class="col-lg-6 col-md-12">
                        <label class="form-label">Nombre del grupo</label>
                        <div class="input-group mb-3">
                            <button class="btn btn-outline-success" type="button" id="button-addon1" data-bs-toggle="modal"
                                data-bs-target="#gurposPlanPagos"><i class="fa-solid fa-magnifying-glass"></i> Buscar </button>
                            <input type="text" id="nombreGru" class="form-control" placeholder="grupo"
                                aria-label="Example text with button addon" aria-describedby="button-addon1" disabled>
                        </div>

                    </div>

                    <div class="col-lg-3 col-md-12">
                        <label class="form-label">Código</label>
                        <input type="text" id="codGru" class="form-control" aria-describedby="emailHelp" disabled>
                    </div>

                    <div class="col-lg-3 col-md-12">
                        <label class="form-label">Cantidad de Integrantes</label>
                        <input id="conInt" type="text" class="form-control" aria-describedby="emailHelp" disabled>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <label class="form-label">Ciclo</label>
                        <input id="nciclo" type="number" class="form-control" aria-describedby="emailHelp" disabled>
                    </div>
                    <div class="col-4">
                        <br>
                        <button id="gPDF" type="button" class="btn btn-outline-danger"
                            onclick="if(validaCliCod()==0)return; reportes([['idGrup','nciclo'],[],[],[]],'pdf','planpago_grupal',0)">Plan
                            de pago Grupo<i class="fa-solid fa-file-pdf"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2  -->
        <div class="card mt-2" id="cardPlanPagos">
            <h5 class="card-header">Modulo para editar el plan de pagos de los grupos. </h5>
            <div class="card-body">



                <!-- INI -->
                <div class="row">
                    <div class="col-lg-3 col-md-12">

                        <!-- ini carrucel opciones de plan de pago y sellecion de plan de pago -->
                        <div id="carouselExampleSlidesOnly1" class="carousel slide" data-bs-ride="false">
                            <div class="carousel-inner">

                                <div class="carousel-item active">

                                    <h5>Editar plan de pago</h5>
                                    <!-- INICIO DE LA TABLA -->
                                    <div class="container mt-3 table-responsive">
                                        <table class="table" id="tbcuaotaYFecha">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Cuota</th>
                                                    <th>Fecha</th>
                                                </tr>
                                            </thead>
                                            <tbody id="dataCuoFech">
                                                <!-- FIN -->
                                            </tbody>
                                        </table>
                                        <button class="btn btn-primary"
                                            onclick="insertORdeletedRow(1); newRowPP('tbcuaotaYFecha');"><i
                                                class="fa-solid fa-plus"></i> Fila</button>
                                        <button class="btn btn-primary" onclick="insertORdeletedRow(2);"><i
                                                class="fa-solid fa-minus"></i> Fila</button>
                                        <button class="btn btn-primary"
                                            onclick="if(auxVtb()==0){return}else{insertORdeletedRow(3)};">Actualizar</button>

                                    </div>
                                    <!-- FIN DE LA TABLA -->

                                </div>

                                <div class="carousel-item">
                                    <h6>Lista de clientes</h6>
                                    <div id="list-example" class="list-group" data-bs-spy="scroll"
                                        data-bs-target="#list-example" data-bs-offset="0"
                                        style="max-height: 320px; overflow-y: scroll">
                                        <div id="list-tab" class="list-group" role="tablist">
                                            <!-- ... -INFORMACION- ... -->
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="row mt-1">
                            <div class="col d-flex justify-content-end">
                                <!-- left -->
                                <button id="prevButton1" class="btn btn-warning"> <i class="fa-solid fa-caret-left"></i>
                                </button>

                            </div>

                            <div class="col d-flex justify-content-start">
                                <!-- right -->
                                <button id="nextButton1" class="btn btn-warning"> <i class="fa-solid fa-caret-right"></i>
                                </button>

                            </div>
                        </div>


                    </div>
                    <!-- fin de la columna-->

                    <!-- INI -->
                    <div class="col-lg-9 col-md-12">
                        <div id="carouselExampleSlidesOnly" class="carousel slide" data-bs-ride="false">
                            <div class="carousel-inner" id="dataPlanPago">

                                <!-- Data  -->

                            </div>
                        </div>
                        <button id="prevButton" class="btn btn-primary">Anterior</button>
                        <button id="nextButton" class="btn btn-primary">Siguiente</button>
                    </div>
                    <!-- FIN  -->

                </div>
                <!-- FIN  -->

            </div>
        </div>

        <script>
            var KillData = [];
            var codCu = [];
            var vecGeneral = [];

            function validaCliCod() {
                let nombreGru = $('#nombreGru').val();
                let codGru = $('#codGru').val();
                if (nombreGru == '' || codGru == '') {
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'Tiene que seleccionar un grupo, gracias :)'
                    });
                    return 0
                }
            }

            //Cuenta la cantidad de elementos que ingresaron 
            function conElem() {
                $("#conInt").val($('[name="tbCodCu[]"]').length);
            }
            //Funcion que se encargar de obtener la cantidad de tablas segun su name
            function conTablas() {
                codCu.splice(0, codCu.length);
                //Variable encarga de cap los Numeros de cuenta
                var tableElements = document.querySelectorAll('table[name="tbCodCu[]"]');
                tableElements.forEach(function(element) {
                    var idTabla = element.id;
                    codCu.push(idTabla);
                });
                KillData.splice(0, KillData.length);
                return codCu;
            }

            function insertORdeletedRow(op) {
                //console.log("Opcion ingresada "+op+' '+typeof(op))
                if (validaCliCod() == 0) {
                    // console.log('Los coampos estan bacios')
                    return;
                };

                KillData.splice(0);
                codCu.splice(0);
                vecGeneral.splice(0);

                codCu = conTablas();
                if (op == 2 || op == 3) codCu.push('tbcuaotaYFecha') //Tabla de las fechas y cuotas

                var con = 0;
                while (codCu[con] != null) {
                    switch (op) {
                        case 1:
                            newRow(codCu[con]);
                            break;
                        case 2:
                            killRow(codCu[con]);
                            break;
                        case 3:

                            if (codCu[con] != 'tbcuaotaYFecha') {
                                generaTabla(codCu[con], ['idPP', 'capita', 'interes', 'otrosP', 'saldoCap'], ['td', 'input',
                                    'input', 'input', 'td'
                                ]);
                            }

                            if (codCu[con] === 'tbcuaotaYFecha') {
                                generaTabla(codCu[con], ['noCuo', 'fecha'], ['td', 'input'])
                                actMasiva(vecGeneral, 'gruPlanPagosAct', codCu);
                                //actMasiva(matriz, 'actMasPlanPagos', codCu);
                            }

                            break;
                    }
                    con++;
                }
            }

            function conFila(nametb) {
                var tabla = document.getElementById(nametb);
                var filas = tabla.getElementsByTagName('tr');
                var noFila = filas.length;
                return noFila;
            }

            function newRow(codCu) {
                var noFila = (conFila(codCu));

                var tr = $("<tr>")
                tr.append(
                    `<td id="${noFila+'idData'+codCu}" name="${codCu+'idPP[]'}" hidden>0</td>`
                ) // Identificador de cada fila asi con las ID de las filas que se retronaron de la consulta. 
                tr.append('<td><i class="fa-solid fa-money-bill" style="color: #c01111;"></i></td>') //Estado 

                tr.append(
                    `<td><input min="0" step="0.01" id="${noFila+'cap'+codCu}" name="${codCu+'capita[]'}" onkeyup="calPlanDePago(${"'"+codCu+"'"})" type="number" class="form-control"  value="0" min="0"></td>`
                ) //Capital
                tr.append(
                    `<td><input min="0" step="0.01" id="${noFila+'inte'+codCu}" name="${codCu+'interes[]'}" onkeyup="calPlanDePago(${"'"+codCu+"'"})" type="number" class="form-control"  value="0" min="0"></td>`
                ) //Interes
                tr.append(
                    `<td><input min="0" step="0.01" id="${noFila+'otros'+codCu}" name="${codCu+'otrosP[]'}" onkeyup="calPlanDePago(${"'"+codCu+"'"})" type="number" class="form-control"  value="0" min="0"></td>`
                ) //Otros pagos

                tr.append(`<td id="${noFila+'salCap'+codCu}" name="${codCu+'saldoCap[]'}"> </td>`) // Saldo Capital 
                tr.append(`<td id="${noFila+'total'+codCu}"> </td>`) // Total

                $('#' + codCu + ' tbody').append(tr)
                calPlanDePago(codCu);
            }

            function newRowPP(nametb) {
                var noFila = (conFila(nametb));

                var tr = $("<tr>")
                tr.append(`<td id="${noFila+'conRow'}" hidden>0</td>`) //  
                tr.append(`<td name="noCuo[]" id="${noFila+'idCon'}" onchange="validaF()"> ${noFila} </td>`) // No de Pago 
                tr.append(
                    `<td><input id="${noFila+'fechaP'}" type="date" name="fecha[]" class="form-control" onchange="validaF()"></td>`
                ) //Fecha
                $('#' + nametb + ' tbody').append(tr);
                $('#' + noFila + 'fechaP').val(hoy());
            }

            function gMatriz(vecMaster) {
                // Obtener la cantidad de filas
                var filas = 0;
                for (var i = 0; i < vecMaster.length; i++) {
                    var longitudVector = vecMaster[i].length;
                    filas = Math.max(filas, longitudVector);
                }
                // Crear la matriz
                var matriz = [];
                // Generar la matriz automáticamente
                for (var i = 0; i < filas; i++) {
                    var fila = [];
                    for (var j = 0; j < vecMaster.length; j++) {
                        fila.push(vecMaster[j][i] || null);
                    }
                    matriz.push(fila);
                }
                return matriz;
            }

            //Funcion para recoger los datos de la tabla
            function capDataTb(nameEle, tipo) {
                var elementos = document.querySelectorAll('' + tipo + '[name="' + nameEle + '[]"]');
                var valores = [];
                elementos.forEach(function(elemento) {
                    if (tipo === 'input') valores.push(elemento.value);
                    if (tipo === 'td') valores.push(elemento.textContent);
                });
                return valores;
            }

            //Funcion encargada de recolectar todos los datos por tabla
            function generaTabla(nametb, nameEle, tipo) {
                var vecMaster = [];
                var dato;
                for (var con = 0; con < nameEle.length; con++) {
                    try {
                        if (nametb != 'tbcuaotaYFecha') vecMaster.push(capDataTb(nametb + nameEle[con], tipo[con]));
                        if (nametb === 'tbcuaotaYFecha') {
                            let dato = (capDataTb(nameEle[con], tipo[con]));
                            vecMaster.push(dato);
                        }
                    } catch (error) {
                        // console.log("Error");
                        return;
                    }

                }
                vecGeneral.push(gMatriz(vecMaster));
            }

            function killRow(nametb) {
                //if (validaCliCod() == 0) return;
                var tabla = document.getElementById(nametb);
                var noFila = conFila(nametb) - 1;

                var filaData;
                if (nametb != 'tbcuaotaYFecha') filaData = parseInt($('#' + (noFila) + 'idData' + nametb).text());
                if (nametb === 'tbcuaotaYFecha') filaData = ($('#' + (noFila) + 'conRow').text());

                if (noFila == 1) {
                    Swal.fire({
                        icon: "error",
                        title: "¡ERROR!",
                        text: "Ya no se puede eliminar más filas"
                    });
                    return;
                }

                if (filaData != 0 || filaData === "kill") {

                    if (filaData === "kill") {
                        eliminarFila(KillData, 'deleteFilaPlanDePagosGrup')
                    }

                    if (filaData != 0 && filaData != "kill") {
                        KillData.push(filaData);
                    }
                }

                if (filaData == 0) {
                    tabla.deleteRow(noFila);
                    calPlanDePago(nametb);
                }
            }

            function hoy() {
                //Fecha 
                var hoy = new Date();
                var anio = hoy.getFullYear();
                var mes = hoy.getMonth() + 1; // Los meses comienzan desde 0, por lo que sumamos 1
                var dia = hoy.getDate();
                var fechaFormateada = anio + '-' + (mes < 10 ? '0' + mes : mes) + '-' + (dia < 10 ? '0' + dia : dia);
                return fechaFormateada;
            }

            function validaF() {
                noFila = conFila('tbcuaotaYFecha');
                //Obneter fecha actual del dia
                var hoyF = new Date(hoy());
                //Se el asigna el valor al objeto fecha
                var fAnt = new Date(hoyF);
                var fAct = new Date($('#1fechaP').val());

                for (let i = 1; i <= (noFila - 1); i++) {
                    if (i >= 2) {
                        fAct = new Date($('#' + i + 'fechaP').val());
                        //console.log(fAnt + ' > ' + fAct);
                    }

                    if ((fAnt >= fAct) && (i < noFila) && i > 1) {
                        $('#' + i + 'fechaP').addClass('is-invalid');

                        if (i == 1) {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: 'La fecha tiene que ser mayor a la fecha actual'
                            });
                            return 0;
                        }

                        Swal.fire({
                            icon: 'error',
                            title: '¡ERROR!',
                            text: 'La fecha tiene que ser mayor a la anterior'
                        });
                        return 0;
                    }

                    $('#' + i + 'fechaP').removeClass('is-invalid');

                    fAnt = fAct
                }
            }

            function auxVtb() {
                if (validaCliCod() == 0) return;

                codCu = conTablas();
                var con = 0;

                while (codCu[con] != null) {
                    var capData = validarTabla(codCu[con]);
                    if (capData == 0) {
                        return 0;
                        break;
                    }
                    con++;
                }

                function validarTabla(codCu) {
                    noFila = conFila(codCu);

                    for (let i = 1; i <= (noFila - 1); i++) {
                        if (validaF() == 0) return 0;
                        cap = parseFloat($('#' + i + 'cap' + codCu).val());
                        inte = parseFloat($('#' + i + 'inte' + codCu).val());
                        otros = parseFloat($('#' + i + 'otros' + codCu).val());
                        salCap = parseFloat($('#' + i + 'salCap' + codCu).text());

                        if ($('#' + i + 'cap' + codCu).val() == '' || $('#' + i + 'inte' + codCu).val() == '' || $('#' + i +
                                'otros' + codCu).val() == '') {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: 'No se permiten campos vacíos '
                            })
                            return 0;
                        }

                        if (cap == 0 && i == (noFila - 1)) {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: 'El capital de la ultima fila no puede quedar en 0'
                            })
                            return 0;
                        }

                        if ((salCap > 0 || salCap < 0) && i == (noFila - 1)) {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: 'El saldo capital tiene que finalizar en 0'
                            })
                            return 0;
                        }

                    }
                }
            }

            function calPlanDePago(nametb) {
                //$('#1salCap').val('Hola'); // Para enviar datos a los inputs de la tabla
                //Contador de fila
                noFila = conFila(nametb);

                //var desembolso = parseInt($('#idDes1').text());
                var estado = false;
                if (!estado) {
                    var desembolso = $('#capDes' + nametb).text();
                    estado = true;
                }

                for (let i = 1; i <= (noFila - 1); i++) {
                    //console.log(typeof(cap)+' - '+cap);
                    cap = parseFloat($('#' + i + 'cap' + nametb).val());
                    //console.log(cap);
                    inte = parseFloat($('#' + i + 'inte' + nametb).val());
                    //console.log(inte);
                    otros = parseFloat($('#' + i + 'otros' + nametb).val());
                    //console.log(otros);

                    if (cap < 0 || inte < 0 || otros < 0) {
                        Swal.fire({
                            icon: 'error',
                            title: '¡ERROR!',
                            text: 'No se permite números negativos'
                        })
                        return
                    }

                    desembolso = (desembolso - $('#' + i + 'cap' + nametb).val()).toFixed(2);
                    $('#' + i + 'salCap' + nametb).text(desembolso);

                    total = (parseFloat(cap + inte + otros)).toFixed(2);
                    $('#' + i + 'total' + nametb).text(total);
                }
            }


            $(document).ready(function() {
                // Inicializar el carousel sin desplazamiento automático
                $('#carouselExampleSlidesOnly').carousel({
                    interval: false
                });
                $('#carouselExampleSlidesOnly1').carousel({
                    interval: false
                });

                // Asignar el evento click al botón "Anterior"
                $('#prevButton').click(function() {
                    $('#carouselExampleSlidesOnly').carousel('prev');
                });

                // Asignar el evento click al botón "Siguiente"
                $('#nextButton').click(function() {
                    $('#carouselExampleSlidesOnly').carousel('next');
                });
                // Asignar el evento click al botón "Anterior"
                $('#prevButton1').click(function() {
                    $('#carouselExampleSlidesOnly1').carousel('prev');
                });

                // Asignar el evento click al botón "Siguiente"
                $('#nextButton1').click(function() {
                    $('#carouselExampleSlidesOnly1').carousel('next');
                });
            });

            // Asignar eventos a las teclas de flecha izquierda y derecha
            $(document).keydown(function(e) {
                if (e.keyCode == 37) { // Flecha izquierda
                    $('#carouselExampleSlidesOnly').carousel('prev');
                } else if (e.keyCode == 39) { // Flecha derecha
                    $('#carouselExampleSlidesOnly').carousel('next');
                }
            });

            /*Inicio de la logica para el menu de los clientes */
            function info(dato) {
                // Manejar el evento click en un elemento de la lista
                var slideIndex = $('#' + dato + 'infoLI').data("slide-to");
                $("#carouselExampleSlidesOnly").carousel(slideIndex);


                // Mostrar la información del elemento del carrusel actual al cargar la página
                var activeSlideIndex = $("#carouselExampleSlidesOnly .carousel-item.active").index();
                showSlideInfo(activeSlideIndex);

                // Mostrar la información del elemento del carrusel actual al cambiar de diapositiva
                $("#carouselExampleSlidesOnly").on("slid.bs.carousel", function() {
                    var activeSlideIndex = $(this).find(".carousel-item.active").index();
                    showSlideInfo(activeSlideIndex);
                });

                // Función para mostrar la información del elemento del carrusel
                function showSlideInfo(slideIndex) {
                    var slideContent = $("#carouselExampleSlidesOnly .carousel-item")
                        .eq(slideIndex)
                        .find("h1")
                        .text();
                    // Puedes modificar esta parte según cómo quieras mostrar la información del elemento
                }
            }

            $(document).ready(function() {
                viewEle('#cardPlanPagos')
            });

            function viewEle(nameEle, estado = 0) {
                if (estado == 0) $(nameEle).hide();
                else $(nameEle).show();
            }
        </script>
        <!-- FIN -->

    <?php
        break;

    case 'planilla':
        $datpost = $_POST["xtra"];
        $extra = $datpost[0];
        $bandera = "Seleccionar Grupo";
        if ($extra != 0) {
            //CREDITOS DEL GRUPO
            $ciclo = $datpost[1];
            $datos[] = [];
            $datacre = mysqli_query($conexion, 'SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo, cli.short_name, cre.CCODCTA,cre.NCiclo,cre.MonSug,cre.DFecDsbls,
            IFNULL((SELECT  SUM(KP) FROM CREDKAR WHERE CESTADO!="X" AND dfecpro<="' . $hoy . '" AND ccodcta=cre.CCODCTA GROUP BY ccodcta),0) cappag From cremcre_meta cre
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
            INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
            WHERE cre.CESTADO="F" AND cre.CCodGrupo="' . $extra . '" AND cre.NCiclo="' . $ciclo . '"');
            $bandera = "Grupo sin cuentas vigentes";
            $i = 0;
            while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
                $datos[$i] = $da;
                $i++;
                $bandera = "";
            }
        }
    ?>
        <input type="text" readonly hidden value="planilla" id='condi'>
        <input type="text" hidden value="grup002" id="file">
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>Planilla de pagos Grupal</h4>
            </div>
            <div class="card-body">
                <div class="row contenedort">
                    <h5>Detalle de Grupo</h5>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <div>
                                <span class="input-group-addon col-8">Nombre Grupo</span>
                                <input type="text" class="form-control " id="name"
                                    value="<?php if ($bandera == "") echo $datos[0]["NombreGrupo"]; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <br>
                            <button type="button" onclick="loadconfig('any',['F'])" class="btn btn-primary"
                                data-bs-toggle="modal" data-bs-target="#buscargrupo">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <div>
                                <span class="input-group-addon col-8">Direccion</span>
                                <input type="text" class="form-control " id="name"
                                    value="<?php if ($bandera == "") echo $datos[0]["direc"]; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="codgrup" class="input-group-addon">Codigo de Grupo</label>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(9rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["codigo_grupo"] . '</span>'; ?>

                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="nciclo" class="input-group-addon">Ciclo</label>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(6rem,80%);" class="badge rounded-pill text-bg-success">' . $datos[0]["NCiclo"] . '</span>';
                                if ($bandera == "") echo '<input style="display:none;" id="nciclo" value="' . $datos[0]["NCiclo"] . '">';
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($bandera != "" && $extra != "0") {
                        echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                    }
                    ?>
                </div>
                <div class="row contenedort">
                    <h5>Cuotas Grupos</h5>
                    <table id="tbcuotas" class="table table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <th>Cuota</th>
                                <th>Fecha</th>
                                <th>Capital</th>
                                <th>Interes</th>
                                <th>Otros</th>
                                <th>Total</th>
                                <th>Saldo</th>
                                <th>Imprimir</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="row justify-items-md-center">
                <div class="col align-items-center" id="modal_footer">
                    <button type="button" class="btn btn-outline-danger"
                        onclick="printdiv('PagGrupAutom', '#cuadro', 'caja_cre', 0)">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="salir()">
                        <i class="fa-solid fa-circle-xmark"></i> Salir
                    </button>
                </div>
            </div>
        </div>
        <script>
            <?php if ($bandera == "") {
            ?>
                datas = [<?php echo $extra ?>, '<?php echo $ciclo ?>'];
                $('#tbcuotas').on('search.dt').DataTable({
                    "aProcessing": true,
                    "aServerSide": true,
                    "ordering": false,
                    "lengthMenu": [
                        [10, 15, -1],
                        ['10 filas', '15 filas', 'Mostrar todos']
                    ],
                    "ajax": {
                        url: "../../src/cruds/crud_credito.php",
                        type: "POST",
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        data: {
                            'condi': "cuotasgrupo",
                            datas
                        },
                        dataType: "json",
                        complete: function(data) {
                            // console.log(data)
                            loaderefect(0);
                        }
                    },
                    "bDestroy": true,
                    "iDisplayLength": 10,
                    "order": [
                        [1, "desc"]
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
                        "sProcessing": "Procesando..."
                    }
                });
            <?php
            }
            ?>
        </script>
    <?php
        break;

    case 'cambiar_estado_cred':
        $datpost = $_POST["xtra"];
        $extra = $datpost[0] ?? 0;
        $numciclo = $datpost[1] ?? 0;
        $query = "SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
                    cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.CCODPRD,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,cre.NCapDes
                    From cremcre_meta cre
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                    INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
                    INNER JOIN cre_productos pro ON pro.id=cre.CCODPRD
                    INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo
                    WHERE cre.TipoEnti='GRUP' AND cre.CESTADO='E' AND cre.CCodGrupo=? AND cre.NCiclo=? ORDER BY cre.CCODCTA;";

        $showmensaje = false;
        try {
            if ($extra == 0 || $numciclo == 0) {
                $showmensaje = true;
                throw new Exception("Seleccione un Grupo ");
            }

            $database->openConnection();
            $datos = $database->getAllResults($query, [$extra, $numciclo]);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("Grupo sin cuentas aprobadas");
            }

            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="text" readonly hidden value='cambiar_estado_cred' id='condi'>
        <input type="text" hidden value="grup002" id="file">
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>Devolucion de creditos a Analisis</h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row contenedort">
                    <h5>Detalle de Grupo</h5>
                    <div class="row">
                        <div class="col-sm-5">
                            <input type="text" readonly hidden value='<?= $extra ?>' id='idgrupo'>
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Nombre Grupo</span>
                                <?php if ($status) echo '<span id="nongrupo" style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["NombreGrupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="codgrup" class="input-group-addon">Codigo de Grupo</label>
                                <?php if ($status) echo '<span style="font-size:1rem;width:min(9rem,90%);" class="badge rounded-pill text-bg-success" id="miSpan">' . $datos[0]["codigo_grupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <br>
                            <button id="findgrupo" onclick="loadconfig('any',['E'])" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#buscargrupo">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Direccion</span>
                                <?php if ($status) echo '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["direc"] . '</span>'; ?>
                            </div>
                        </div>

                        <div class="col-sm-2">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="nciclo" class="input-group-addon">Ciclo</label>
                                <input type="number" class="form-control" id="nciclo" readonly value="<?= $numciclo; ?>">
                            </div>
                        </div>
                    </div>
                    <?php if ($status) {
                        $est = ($datos[0]["Cestado"] == "E") ? "Aprobados" : " ";
                        echo 'ESTADO: ' . $est;
                    }
                    ?>
                </div>
                <div class="row contenedort" style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
                    <h5>CIENTES DEL GRUPO</h5>
                    <div class="accordion" id="cuotas">
                        <?php
                        if ($status) {
                            foreach ($datos as $key => $value) {
                                $ccodta = $value["CCODCTA"];
                                $estado = $value["Cestado"];
                                $codcli = $value["idcod_cliente"];
                                $name = $value["short_name"];
                                $fecnac = date("d-m-Y", strtotime($value["date_birth"]));
                                $dpi = $value["no_identifica"];
                                $mondes = $value["NCapDes"];
                        ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <div class="row">
                                            <div class="col-12">
                                                <button id="<?php echo 'bt' . $key; ?>" class="accordion-button collapsed">
                                                    <div class="row" style="width:100%;font-size: 0.90rem;">
                                                        <div class="col-3">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <span class="input-group-addon"><?= $ccodta; ?></span>
                                                                </div>
                                                                <div class="col-12">
                                                                    <input type="text" value="<?= $ccodta; ?>" hidden>
                                                                    <span class="input-group-addon"><?= strtoupper($name); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <label class="input-group-addon fw-bold">Monto Desembolsado</label>
                                                            <input type="number" step="0.01" class="form-control" placeholder="000.00" value="<?= $mondes; ?>" disabled>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <label class="input-group-addon fw-bold">DPI</label>
                                                            <input type="text" class="form-control" value="<?= $dpi; ?>" disabled>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <label class="input-group-addon fw-bold">Fecha Desembolso</label>
                                                            <input type="date" class="form-control" value="<?= $value['DFecDsbls']; ?>" disabled>
                                                        </div>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </h2>
                                </div>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php echo $csrf->getTokenField(); ?>
            <div class="row justify-items-md-center">
                <div class="col align-items-center" id="modal_footer">
                    <?php if ($status) { ?>
                        <!-- <button type="button" class="btn btn-outline-danger" onclick="changeStatus('X')">
                            <i class="fa fa-xmark"></i> Eliminar Creditos
                        </button> -->
                        <button type="button" class="btn btn-outline-primary" onclick="changeStatus('D')">
                            <i class="fa-solid fa-left-long"></i>Pasar a Análisis
                        </button>
                    <?php } ?>
                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2(' #cuadro', '0' )">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>

        <script>
            function changeStatus(status) {
                Swal.fire({
                    title: '¿ESTA SEGURO DE REALIZAR ESTA ACCION, SE ELIMINARAN DATOS IMPORTANTES?',
                    showDenyButton: true,
                    confirmButtonText: 'Sí',
                    denyButtonText: `Cancelar`,
                }).then((result) => {
                    if (result.isConfirmed) {
                        obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'changestatuscreditos', 0, ['<?= htmlspecialchars($secureID->encrypt($extra)) ?>', <?= $numciclo ?>, status])
                    }
                })
            }
        </script>
    <?php
        break;

    case 'delete_desembolsoG':
        $datpost = $_POST["xtra"];
        $extra = $datpost[0] ?? 0;
        $numciclo = $datpost[1] ?? 0;
        $query = "SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
                    cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.CCODPRD,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,cre.NCapDes
                    From cremcre_meta cre
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                    INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
                    INNER JOIN cre_productos pro ON pro.id=cre.CCODPRD
                    INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo
                    WHERE cre.TipoEnti='GRUP' AND cre.CESTADO='F' AND cre.CCodGrupo=? AND cre.NCiclo=? ORDER BY cre.CCODCTA;";

        $showmensaje = false;
        try {
            if ($extra == 0 || $numciclo == 0) {
                $showmensaje = true;
                throw new Exception("Seleccione un Grupo ");
            }

            $database->openConnection();
            $datos = $database->getAllResults($query, [$extra, $numciclo]);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("Grupo sin cuentas vigentes");
            }

            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="text" readonly hidden value='delete_desembolsoG' id='condi'>
        <input type="text" hidden value="grup002" id="file">
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>Eliminación de Créditos Desembolsados</h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row contenedort">
                    <h5>Detalle de Grupo</h5>
                    <div class="row">
                        <div class="col-sm-5">
                            <input type="text" readonly hidden value='<?= $extra ?>' id='idgrupo'>
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Nombre Grupo</span>
                                <?php if ($status) echo '<span id="nongrupo" style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["NombreGrupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="codgrup" class="input-group-addon">Codigo de Grupo</label>
                                <?php if ($status) echo '<span style="font-size:1rem;width:min(9rem,90%);" class="badge rounded-pill text-bg-success" id="miSpan">' . $datos[0]["codigo_grupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <br>
                            <button id="findgrupo" onclick="loadconfig('any',['F'])" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#buscargrupo">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <span class="input-group-addon col-8">Direccion</span>
                                <?php if ($status) echo '<span style="font-size:1rem;width:min(25rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["direc"] . '</span>'; ?>
                            </div>
                        </div>

                        <div class="col-sm-2">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="nciclo" class="input-group-addon">Ciclo</label>
                                <input type="number" class="form-control" id="nciclo" readonly value="<?= $numciclo; ?>">
                            </div>
                        </div>
                    </div>
                    <?php if ($status) {
                        $est = ($datos[0]["Cestado"] == "F") ? " DESEMBOLSADOS" : " ";
                        echo 'ESTADO: ' . $est;
                    }
                    ?>
                </div>
                <div class="row contenedort" style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
                    <h5>CIENTES DEL GRUPO</h5>
                    <div class="accordion" id="cuotas">
                        <?php
                        if ($status) {
                            foreach ($datos as $key => $value) {
                                $ccodta = $value["CCODCTA"];
                                $estado = $value["Cestado"];
                                $codcli = $value["idcod_cliente"];
                                $name = $value["short_name"];
                                $fecnac = date("d-m-Y", strtotime($value["date_birth"]));
                                $dpi = $value["no_identifica"];
                                $mondes = $value["NCapDes"];
                        ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <div class="row">
                                            <div class="col-12">
                                                <button id="<?php echo 'bt' . $key; ?>" class="accordion-button collapsed">
                                                    <div class="row" style="width:100%;font-size: 0.90rem;">
                                                        <div class="col-3">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <span class="input-group-addon"><?= $ccodta; ?></span>
                                                                </div>
                                                                <div class="col-12">
                                                                    <input type="text" value="<?= $ccodta; ?>" hidden>
                                                                    <span class="input-group-addon"><?= strtoupper($name); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <label class="input-group-addon fw-bold">Monto Desembolsado</label>
                                                            <input type="number" step="0.01" class="form-control" placeholder="000.00" value="<?= $mondes; ?>" disabled>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <label class="input-group-addon fw-bold">DPI</label>
                                                            <input type="text" class="form-control" value="<?= $dpi; ?>" disabled>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <label class="input-group-addon fw-bold">Fecha Desembolso</label>
                                                            <input type="date" class="form-control" value="<?= $value['DFecDsbls']; ?>" disabled>
                                                        </div>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </h2>
                                </div>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php echo $csrf->getTokenField(); ?>
            <div class="row justify-items-md-center">
                <div class="col align-items-center" id="modal_footer">
                    <?php
                    if ($status) {
                    ?>
                        <button type="button" class="btn btn-outline-danger" onclick="changeStatus('X')">
                            <i class="fa fa-xmark"></i> Eliminar Creditos
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="changeStatus('E')">
                            <i class="fa-solid fa-left-long"></i>Pasar a Aprobacion
                        </button>
                    <?php
                    }
                    ?>
                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2(' #cuadro', '0' )">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>

        <script>
            function changeStatus(status) {
                Swal.fire({
                    title: '¿ESTA SEGURO DE REALIZAR ESTA ACCION, SE ELIMINARAN DATOS IMPORTANTES?',
                    showDenyButton: true,
                    confirmButtonText: 'Sí',
                    denyButtonText: `Cancelar`,
                }).then((result) => {
                    if (result.isConfirmed) {
                        obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'changestatuscreditos', 0, ['<?= htmlspecialchars($secureID->encrypt($extra)) ?>', <?= $numciclo ?>, status])
                    }
                })
            }
        </script>
    <?php
        break;
    case 'SimuladorPlanPagoGrup':
        $datpost = $_POST["xtra"];
        $extra = $datpost[0];
        $bandera = "Seleccionar Grupo";

    ?>
        <input type="text" readonly hidden value="SimuladorPlanPagoGrup" id='condi'>
        <input type="text" hidden value="grup002" id="file">
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>Simulador de Credito Grupal</h4>
            </div>
            <div class="card-body">
                <div class="row contenedort">
                    <h5>Detalle de Grupo</h5>
                    <div class="row mb-3">
                        <div class="col-sm-4">
                            <div>
                                <span class="input-group-addon col-4">Nombre de la persona</span>
                                <input type="text" class="form-control" id="name" value="">
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <span class="input-group-addon col-2">Cantidad a solicitar</span>
                            <input type="number" class="form-control" id="monto" value="">
                        </div>
                        <div class="col-sm-5">
                            <br>
                            <button type="button" class="btn btn-primary" onclick="agregarPersona()">
                                <i class="fa-solid fa-plus"></i> Agregar
                            </button>
                        </div>
                    </div>
                    <div id="tabla-container" class="mt-3" style="width: 60%; margin: 0 auto; text-align: center;">
                        <table class="table table-bordered" id="tabla-detalles" style="display: none; margin: 0 auto;">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">Nombre</th>
                                    <th style="text-align: center;">Monto</th>
                                    <th style="text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-body"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" class="text-end"><strong>Total:</strong></td>
                                    <td id="total-monto" style="text-align: center;">Q0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="row contenedort">
                    <div class="card-body">
                        <div class="row crdbody">
                            <div class="form-group col-md-3">
                                <button type="button" class="btn btn-outline-primary" title="Buscar Grupo" onclick="abrir_modal('#findcredlin', '#id_modal_hidden', 'idprod,codprod,nameprod,descprod,tasaprod,maxprod,fondo/A,A,A,A,A,A,A/'+'/#/#/#/#')">
                                    <i class="fa-solid fa-magnifying-glass"> </i>Buscar Linea de Credito </button>
                            </div>
                        </div>
                        <div class="alert alert-primary" role="alert">
                            <?php
                            $prd = 0;
                            if ($bandera == "" && $datos[0]["Cestado"] == "D") {
                                $dprod[] = [];
                                $codproducto = $datos[0]['CCODPRD'];
                                $qe = mysqli_query($conexion, "SELECT pro.id,pro.cod_producto,pro.nombre nompro,pro.descripcion descriprod,ff.descripcion fondesc,pro.tasa_interes, pro.monto_maximo
                                FROM cre_productos pro
                                INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo WHERE pro.estado=1 AND pro.id=" . $codproducto . "");
                                $k = 0;
                                while ($da = mysqli_fetch_array($qe, MYSQLI_ASSOC)) {
                                    $dprod[$k] = $da;
                                    $k++;
                                    $prd = 1;
                                }
                            }
                            ?>

                            <div class="row crdbody">
                                <div class="col-sm-3">
                                    <div class="">
                                        <span class="fw-bold">Codigo Producto</span>
                                        <input type="number" class="form-control" id="idprod" value="<?php if ($prd == 1) echo $dprod[0]["id"]; ?>" readonly hidden>
                                        <input type="text" class="form-control" id="codprod" value="<?php if ($prd == 1) echo $dprod[0]["cod_producto"]; ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-sm-6">
                                    <span class="fw-bold">Nombre</span>
                                    <input type="text" class="form-control" id="nameprod" readonly value="<?php if ($prd == 1) echo $dprod[0]["nompro"]; ?>">
                                </div>
                                <div class="form-group col-sm-3">
                                    <span class="fw-bold">%Interes Asignado</span>
                                    <input type="number" step="0.01" class="form-control" id="tasaprod" value="<?php if ($bandera == "") echo $datos[0]["NIntApro"]; ?>">
                                </div>
                            </div>
                            <div class="row crdbody">
                                <div class="form-group col-sm-6">
                                    <span class="fw-bold">Descripción</span>
                                    <input type="text" class="form-control" id="descprod" readonly value="<?php if ($prd == 1) echo $dprod[0]["descriprod"]; ?>">
                                </div>

                                <div class=" col-sm-3">
                                    <span class="fw-bold">Monto Maximo</span>
                                    <input type="number" step="0.01" class="form-control" id="maxprod" readonly value="<?php if ($prd == 1) echo $dprod[0]["monto_maximo"]; ?>">
                                </div>
                                <div class="col-sm-3">
                                    <span class="fw-bold">Fuente de fondos</span>
                                    <input type="text" class="form-control" id="fondo" readonly value="<?php if ($prd == 1) echo $dprod[0]["fondesc"]; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row contenedort">
                    <div class="col-12">
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <span class="fw-bold">Tipo de Crédito</span>
                                <select id="tipcre" class="form-select" onchange="creperi('tpscre2','#alrtpnl','cre_indi_01',this.value)">
                                    <option value="0" selected disabled>Seleccione tipo de Crédito</option>
                                    <?php tpscre(); ?>
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Tipo de Periodo</span>
                                <select id="peri" class="form-select">
                                    <option selected disabled value="0">Seleccionar Tipo de Periodo</option>
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Fecha primer Cuota</span>
                                <input type="date" class="form-control" id="fecinit">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <span class="fw-bold">No. Cuotas</span>
                                <input type="number" class="form-control" id="nrocuo">
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Fecha Desembolso</span>
                                <input type="date" class="form-control" id="fecdes">
                            </div>
                        </div>
                    </div>
                    <script>
                        const inputFecha = document.getElementById('fecinit');
                        const inputFecha2 = document.getElementById('fecdes');
                        const fechaActual = new Date().toISOString().split('T')[0];
                        const fechaActual2 = new Date().toISOString().split('T')[0];
                        inputFecha.value = fechaActual;
                        inputFecha2.value = fechaActual2;
                    </script>
                    <div class="row crdbody">
                        <div class="col-sm-12">
                            <div class="input-group" id="tipsMEns">
                                <div class="alert alert-success" role="alert" id="alrtpnl">
                                    <h4>Seleccione un tipo de crédito </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center">
                            <?php
                            if ($bandera == "") {
                                echo '  <button type="button" class="btn btn-outline-success" onclick="saveanal(' . (count($datos) - 1) . ',' . $numciclo . ',' . $extra . ',`' . $_SESSION['agencia'] . '`)">
                                            <i class="fa fa-floppy-disk"></i> Guardar Cambios
                                        </button>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="row contenedort">
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <button type="button" class="btn btn-outline-primary" onclick="recolectarInformacion()">
                                <i class="fa-solid fa-circle-xmark"></i> Simular
                            </button>
                            <button type="button" class="btn btn-outline-danger"
                                onclick="printdiv('PagGrupAutom', '#cuadro', 'caja_cre', 0)">
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
        <style>
            #tbcuotas {
                width: 100%;
                border-collapse: collapse;
                font-family: Arial, sans-serif;
                font-size: 14px;
            }

            #tbcuotas th,
            #tbcuotas td {
                padding: 10px;
                text-align: center;
                border: 1px solid #ddd;
            }

            #tbcuotas th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            #tbcuotas td {
                background-color: #fff;
            }

            #tbcuotas tr:nth-child(even) {
                background-color: #f9f9f9;
            }

            #tbcuotas tr:hover {
                background-color: #e9e9e9;
            }
        </style>
<?php

        break;
} ?>