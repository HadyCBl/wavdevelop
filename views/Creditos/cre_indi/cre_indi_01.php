<?php

use App\Generic\FileProcessor;
use Micro\Helpers\Log;
use Micro\Generic\Moneda;

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

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

$codusu = $_SESSION["id"];
$id_agencia = $_SESSION['id_agencia'];

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");

include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
// include '../../../src/funcphp/valida.php';
// include '../../../src/funcphp/func_gen.php';
$mtmax = 0;
$condi = $_POST["condi"];
switch ($condi) {
    case 'prdscre':
        $xtra = $_POST["xtra"];
        $consulta = mysqli_query($general, "SELECT nombre, periodo FROM `tb_periodo` WHERE TipoCredito = '$xtra'");
        while ($crdperi = mysqli_fetch_array($consulta, MYSQLI_NUM)) {
            echo '<option value="' . $crdperi[1] . '"> ' . $crdperi[0] . ' </option>';
        }
        mysqli_close($conexion);
        break;
    case 'tpscre2':
        $xtra = $_POST["xtra"];
        switch ($xtra) {
            //SISTEMA NIVELADO
            case "Flat":
                echo '<h4 class="alert-heading">Nivelado</h4>
                <p> NIVELADO, FLAT, (Diario, semanal, quincenal), Interes y capital sera el mismos. </p> <hr>
                <p class="mb-0"> -Cuotas Constantes. <br>  -Los intereses son constantes, se calculan sobre la deuda. <br>  - Capital Fijo (La deuda dividida por el número de períodos).</p>
                <img src="../../includes/img/flat.png" class="w3-border w3-padding" alt="frances" width="400" height="200"> ';
                break;
            //SISTEMA FRANCES
            case "Franc":
                echo '<h4 class="alert-heading">Couta Fija</h4>
                <p>NIVELADO, SOBRE SALDO, (MENS-SEMESTRAL) ,tabla de amortizacion de créditos systema frances. </p> <hr>
                <p class="mb-0"> -Cuotas Constantes. <br>  -Los intereses son constantes, se calculan sobre Capital Restante . <br>  -La amortización del capital se hace en forma creciente, es lo contrario a la amortización de los intereses que se hace decreciente.  </p>
                <img src="../../includes/img/frances.png" class="w3-border w3-padding" alt="frances" width="400" height="200"> ';
                break;
            //SISTEMA ALEMAN
            case "Germa":
                echo '<h4 class="alert-heading">Sistema Aleman</h4>
                <p> PAGO FIJO A CAPITAL, (MENSUAL-SEMESTRAL), sistema aleman, interes variable, ,  interés a pagar se calculan sobre el saldo pendiente de pagar, </p><hr>
                <p class="mb-0">-Cuota Decrecientes. <br> -Interes variable. <br> - Capital Fijo (La deuda dividida por el número de períodos).</p>
                <img src="../../includes/img/aleman.png" class="w3-border w3-padding" alt="frances" width="400" height="200">';
                break;
            //SISTEMA AMERICANO
            case "Amer":
                echo '<h4 class="alert-heading">Capital Vencimiento</h4>
                <p> Capital Vencimiento, sistema americano, unica cuota, interes mensual, solo mensual.   </p> <hr>
                <p class="mb-0">- Couta interés constante. <br> - No existe capital amortizado.<br> - Última Couta conformada por capital prestado mas interés.</p>
                <img src="../../includes/img/american.png" class="w3-border w3-padding" alt="frances" width="400" height="200">';
                break;
        }
        break;
    /*------------------  CREDITOS INDIVIDUALES  *DESEMBOLSO* ------------------------------------------ */
    case 'solicitud_individual': {
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
            $xtra = $_POST["xtra"];

            //consultar
            $i = 0;
            $src = '../../includes/img/fotoClienteDefault.png';
            $bandera_creditos_proceso = false;
            $bandera_creditos_desembolsadods = false;
            $bandera_creditos_desembolsadods = false;
            $bandera_creditos_incobrables = false;
            $bandera_creditos_juridicos = false;
            $bandera = false;
            $bandera_garantias = false;
            $datos[] = [];
            $datosprocesos[] = [];
            $datosactivos[] = [];
            $datosgarantias[] = [];

            if ($xtra != 0) {
                $consulta = mysqli_query($conexion, "SELECT cl.idcod_cliente AS codcli, cl.short_name AS nomcli, cl.Direccion AS direccion,  (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli='$xtra' AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo, cl.url_img AS urlfoto FROM tb_cliente cl
                WHERE cl.idcod_cliente='$xtra' AND cl.estado='1'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datos[$i] = $fila;
                    //CARGADO DE LA IMAGEN
                    $imgurl = __DIR__ . '/../../../../' . $fila['urlfoto'];
                    if (!is_file($imgurl)) {
                        $src = '../../includes/img/fotoClienteDefault.png';
                    } else {
                        $imginfo   = getimagesize($imgurl);
                        $mimetype  = $imginfo['mime'];
                        $imageData = base64_encode(file_get_contents($imgurl));
                        $src = 'data:' . $mimetype . ';base64,' . $imageData;
                    }
                    $i++;
                    $bandera = true;
                }
                //consultar procesos de creditos en proceso de desembolso
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cm.Cestado AS estado FROM cremcre_meta cm WHERE cm.CodCli='$xtra' AND (cm.Cestado='A' OR cm.Cestado='D' OR cm.Cestado='E') AND cm.TipoEnti='INDI'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datosprocesos[$i] = $fila;
                    $i++;
                    $bandera_creditos_proceso = true;
                }
                //consultar procesos de creditos activos
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cm.Cestado AS estado, cm.MonSug AS monto, cm.DFecDsbls AS fechaD FROM cremcre_meta cm WHERE cm.CodCli='$xtra' AND cm.Cestado='F' AND cm.TipoEnti='INDI'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datosactivos[$i] = $fila;
                    $i++;
                    $bandera_creditos_desembolsadods = true;
                }
                //consultar procesos de creditos incobrables
                $i = 0;
                $consultai = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cm.Cestado AS estado, cm.MonSug AS monto, cm.DFecDsbls AS fechaD FROM cremcre_meta cm WHERE cm.CodCli='$xtra' AND cm.Cestado='I' AND cm.TipoEnti='INDI'");
                while ($fila = mysqli_fetch_array($consultai, MYSQLI_ASSOC)) {
                    $datosincobrables[$i] = $fila;
                    $i++;
                    $bandera_creditos_incobrables = true;
                }
                //consultar procesos de creditos juridicos
                $i = 0;
                $consultaj = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cm.Cestado AS estado, cm.MonSug AS monto, cm.DFecDsbls AS fechaD FROM cremcre_meta cm WHERE cm.CodCli='$xtra' AND cm.Cestado='J' AND cm.TipoEnti='INDI'");
                while ($fila = mysqli_fetch_array($consultaj, MYSQLI_ASSOC)) {
                    $datosjuridicos[$i] = $fila;
                    $i++;
                    $bandera_creditos_juridicos = true;
                }
                //CONSULTAR GARANTIAS
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT cl.idcod_cliente AS codcli, gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc,
                gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
                IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS nomcli,
                IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS direccioncli
                FROM tb_cliente cl
                INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
                INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
                WHERE cl.estado='1' AND gr.estado=1 AND cl.idcod_cliente='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datosgarantias[$i] = $fila;
                    $i++;
                    $bandera_garantias = true;
                }
            }
            $selectagencia = 1; //0 NO SE MUESTRA EL SELECT DE AGENCIA, 1 PARA MOSTRAR EL SELECT DE AGENCIA
            $style = ($selectagencia == 1) ? "block" : "none";
            // echo '<pre>';
            // print_r($datosgarantias);
            // echo '</pre>';
            // echo $imgurl;
?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <input type="text" id="file" value="cre_indi_01" style="display: none;">
            <input type="text" id="condi" value="solicitud_individual" style="display: none;">
            <div class="text" style="text-align:center">SOLICITUD DE CRÉDITO INDIVIDUAL</div>
            <div class="card" id="formSolicitudCreditoIndividual">
                <div class="card-header">Solicitud de crédito individual</div>
                <div class="card-body" style="padding-bottom: 0px !important;">

                    <!-- seleccion de cliente y su credito-->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Información de cliente</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 col-sm-6 col-md-2 mt-2">
                                <img width="120" height="130" id="vistaPrevia" src="<?php if ($bandera) {
                                                                                        echo $src;
                                                                                    } else {
                                                                                        echo $src;
                                                                                    } ?>">
                            </div>
                            <div class="col-12 col-sm-12 col-md-10">
                                <div class="row">
                                    <div class="col-12 col-sm-6">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="codcli" placeholder="Código de cliente"
                                                readonly
                                                <?php if ($bandera) {
                                                    echo 'value="' . $datos[0]['codcli'] . '"';
                                                } ?>>
                                            <input type="text" name="" id="id_cod_cliente" hidden>
                                            <label for="cliente">Código de cliente</label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                                            onclick="abrir_modal('#modal_solicitud_01', '#id_modal_hidden', 'id')"><i
                                                class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar cliente</button>
                                    </div>
                                </div>
                                <!-- cargo, nombre agencia y codagencia  -->
                                <div class="row">
                                    <div class="col-12 col-sm-6 col-md-5">
                                        <div class="form-floating mb-3 mt-2">
                                            <input type="text" class="form-control" id="nomcli" placeholder="Nombre cliente"
                                                readonly
                                                <?php if ($bandera) {
                                                    echo 'value="' . $datos[0]['nomcli'] . '"';
                                                } ?>>
                                            <label for="nomcli">Nombre de cliente</label>
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-6 col-md-5">
                                        <div class="form-floating mb-3 mt-2">
                                            <input type="text" class="form-control" id="direccion" placeholder="Dirección" readonly
                                                <?php if ($bandera) {
                                                    echo 'value="' . $datos[0]['direccion'] . '"';
                                                } ?>>
                                            <label for="direccion">Dirección</label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-2">
                                        <div class="form-floating mb-3 mt-2">
                                            <input type="text" class="form-control" id="ciclo" placeholder="Ciclo" readonly
                                                <?php if ($bandera) {
                                                    echo 'value="' . $datos[0]['ciclo'] . '"';
                                                } ?>>
                                            <label for="ciclo">Ciclo</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- lineas de credito -->
                    <div class="container contenedort" style="max-width: 100% !important;" <?= ($bandera) ? ('') : ('hidden') ?>>
                        <div class="row">
                            <div class="col mt-2 mb-2">
                                <button type="button" class="btn btn-outline-primary" title="Buscar Grupo"
                                    onclick="abrir_modal('#modal_tiposcreditos', '#id_modal_hidden', 'idprod,codprod,nameprod,descprod,tasaprod,maxprod,ahorro/A,A,A,A,A,A,A/'+'/#/#/#/#')">
                                    <i class="fa-solid fa-magnifying-glass"> </i>Buscar Linea de Credito </button>
                            </div>
                        </div>

                        <div class="alert alert-primary" role="alert">
                            <div class="row crdbody">
                                <div class="col-sm-3">
                                    <div class="">
                                        <span class="fw-bold">Codigo Producto</span>
                                        <input type="number" class="form-control" id="idprod" readonly hidden>
                                        <input type="text" class="form-control" id="codprod" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-sm-7">
                                    <span class="fw-bold">Nombre</span>
                                    <input type="text" class="form-control" id="nameprod" readonly>
                                </div>
                                <div class="form-group col-sm-2">
                                    <span class="fw-bold">%Interes Anual</span>
                                    <input type="number" step="0.01" class="form-control" id="tasaprod" readonly>
                                </div>
                            </div>
                            <div class="row crdbody">
                                <div class="form-group col-sm-6">
                                    <span class="fw-bold">Descripción</span>
                                    <input type="text" class="form-control" id="descprod" readonly>
                                </div>

                                <div class=" col-sm-3">
                                    <span class="fw-bold">Monto Maximo</span>
                                    <input type="number" step="0.01" class="form-control" id="maxprod" readonly>
                                </div>
                                <div class="col-sm-3">
                                    <span class="fw-bold">Fuente de fondos</span>
                                    <input type="text" step="0.01" class="form-control" id="ahorro" readonly>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- datos adicionales -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Datos complementarios</b></div>
                            </div>
                        </div>
                        <div class="row" style="display:<?php echo $style ?>">
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="agenciaaplica">
                                        <option value="0" selected disabled>Seleccione una agencia</option>
                                        <?php
                                        $consulta = mysqli_query($conexion, "SELECT nom_agencia, id_agencia FROM tb_agencia");
                                        while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            $nomage = $dtas["nom_agencia"];
                                            $id_age = $dtas["id_agencia"];
                                            $selected = ($id_agencia == $id_age) ? " selected" : "";
                                            echo '<option' . $selected . ' value="' . $id_age . '">' . $nomage . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="agenciaaplica">Agencia</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="analista">
                                        <option value="0" selected>Seleccione un analista</option>
                                        <?php
                                        //$consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA' AND id_agencia IN( SELECT id_agencia FROM tb_usuario WHERE id_usu=$codusu)");
                                        $consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA' AND estado=1");
                                        while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            $nombre = $dtas["nameusu"];
                                            $id_usu = $dtas["id_usu"];
                                            echo '<option value="' . $id_usu . '">' . $nombre . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="analista">Analista</label>
                                </div>
                            </div>
                        </div>
                        <!-- cargo, nombre agencia y codagencia  -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="montosol" placeholder="Monto solicitado" min="0"
                                        step="0.01">
                                    <label for="montosol">Monto solicitado</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="destino">
                                        <option value="0" selected>Seleccione un destino</option>
                                        <?php
                                        $quersec = mysqli_query($general, "SELECT id_DestinoCredito AS id, DestinoCredito AS destino FROM `tb_destinocredito`");
                                        while ($sect = mysqli_fetch_array($quersec, MYSQLI_ASSOC)) {
                                            $id = $sect["id"];
                                            $destino = $sect["destino"];
                                            echo '<option value="' . $id . '">' . $destino . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="destino">Destino de crédito</label>
                                </div>
                            </div>
                        </div>
                        <!-- sector y actividad -->
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="sector" onchange="buscar_actividadeconomica(this.value)">
                                        <option value="0" selected>Seleccione un sector económico</option>
                                        <?php
                                        $quersec = mysqli_query($general, "SELECT id_SectoresEconomicos, SectoresEconomicos FROM `tb_sectoreseconomicos`");
                                        while ($sect = mysqli_fetch_array($quersec, MYSQLI_ASSOC)) {
                                            $idSctr = $sect["id_SectoresEconomicos"];
                                            $SctrEcono = $sect["SectoresEconomicos"];
                                            echo '<option value="' . $idSctr . '">' . $SctrEcono . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="destino">Sector económico</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="actividadeconomica">
                                        <option value="0" selected>Seleccione una actividad económica</option>
                                    </select>
                                    <label for="destino">Actividad económica</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- datos -->

                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Datos del credito</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="tipocred"
                                        onchange="creperi('tpscre2','#alrtpnl','cre_indi_01',this.value)">
                                        <option value="0" selected disabled>Seleccione un tipo de crédito</option>
                                        <?php
                                        $consulta = mysqli_query($general, "SELECT abre, Credito FROM `tb_credito`");
                                        while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            $id_abre = $dtas["abre"];
                                            $nomtip = $dtas["Credito"];
                                            echo '<option value="' . $id_abre . '">' . $nomtip . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="tipocred">Tipo de crédito</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="peri">
                                        <option value="0" selected disabled>Seleccione un tipo de periodo</option>

                                    </select>
                                    <label for="peri">Tipo de periodo</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="primerpago" placeholder="Fecha primer pago"
                                        <?php echo 'value="' . date('Y-m-d') . '"'; ?>>
                                    <label for="primerpago">Fecha primer pago</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="number" min="1" class="form-control" id="cuota" placeholder="No de cuotas">
                                    <label for="cuota">No. de cuotas</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="container contenedort">
                        <div class="accordion accordion-flush" id="accordionFlushExample">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                                        <b>Datos adicionales</b>
                                    </button>
                                </h2>
                                <div id="flush-collapseOne" class="accordion-collapse collapse"
                                    data-bs-parent="#accordionFlushExample">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="form-floating mb-3">
                                                    <textarea type="text" class="form-control" id="crecimiento"
                                                        placeholder="Crecimiento"></textarea>
                                                    <label for="primerpago">Crecimiento</label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-floating mb-3">
                                                    <textarea type="text" class="form-control" id="recomendacion"
                                                        placeholder="Recomendacion"></textarea>
                                                    <label for="cuota">Recomendacion del oficial de crédito</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- SECCION DE GARANTIAS -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Garantías del cliente</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col mb-2">
                                <div class="table-responsive">
                                    <table class="table mb-0" style="font-size: 0.8rem !important;">
                                        <thead>
                                            <tr>
                                                <th scope="col">Tipo Garantia</th>
                                                <th scope="col">Tipo Doc.</th>
                                                <th scope="col">Descripción</th>
                                                <th scope="col">Dirección</th>
                                                <th scope="col">Valor gravamen</th>
                                                <th scope="col">Marcar</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-group-divider">
                                            <?php if ($bandera_garantias && !$bandera_creditos_proceso) {
                                                for ($i = 0; $i < count($datosgarantias); $i++) { ?>
                                                    <tr>
                                                        <td scope="row"><?= ($datosgarantias[$i]["nomtipgar"]) ?></td>
                                                        <td><?= ($datosgarantias[$i]["nomtipdoc"]) ?></td>
                                                        <!-- VALIDAR SI ES UN GARANTIA NORMAL O ES UN FIADOR -->
                                                        <?php if ($datosgarantias[$i]["idtipgar"] == 1 && ($datosgarantias[$i]["idtipdoc"] == 1 || $datosgarantias[$i]["idtipdoc"] == 17)) { ?>
                                                            <td><?= ($datosgarantias[$i]["nomcli"]) ?></td>
                                                            <td><?= ($datosgarantias[$i]["direccioncli"]) ?></td>
                                                        <?php } else { ?>
                                                            <td><?= ($datosgarantias[$i]["descripcion"]) ?></td>
                                                            <td><?= ($datosgarantias[$i]["direccion"]) ?></td>
                                                        <?php } ?>
                                                        <td><?= ($datosgarantias[$i]["montogravamen"]) ?></td>
                                                        <td>
                                                            <input class="form-check-input S" type="checkbox"
                                                                value="<?= $datosgarantias[$i]['idgar']; ?>"
                                                                id="<?= "S_" . $datosgarantias[$i]['idgar']; ?>">
                                                        </td>
                                                    </tr>
                                            <?php }
                                            } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ALERTA PARA MOSTRAR QUE NO SE PUEDE SOLICITAR UN NUEVO CREDITO -->
                    <?php
                    if ($bandera_creditos_proceso) { ?>
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 0px !important;">
                            <div class="row">
                                <div class="col mb-3">
                                    <h4 class="alert-heading">IMPORTANTE!</h4>
                                    <p>El cliente seleccionado no se le permite solicitar un nuevo credito porque ya posee al menos un
                                        credito en proceso, debe de cancelarlos o proseguir con los mismos.</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col mb-2">
                                    <div class="text-center"><b>INFORMACIÓN DE CREDITOS EN PROCESO</b></div>
                                </div>
                            </div>

                            <?php for ($i = 0; $i < count($datosprocesos); $i++) {
                                $estado = "";
                                if ($datosprocesos[$i]['estado'] == 'A') {
                                    $estado = "Solicitado";
                                } elseif ($datosprocesos[$i]['estado'] == 'D') {
                                    $estado = "Analizado";
                                } else {
                                    $estado = "Aprobado";
                                }
                            ?>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="ag-courses_item card-red">
                                            <a class="ag-courses-item_link">
                                                <div class="ag-courses-item_bg"></div>
                                                <div class="ag-courses-item_title">Credito No:</b><span class="me-3"><?= $i + 1; ?></div>
                                                <span class="ag-courses-item_title"><b>Código de crédito:</b>
                                                    <?= $datosprocesos[$i]['ccodcta']; ?></span>
                                                <div class="ag-courses-item_date-box">

                                                    <span class="ag-courses-item_date">Estado:<?= $estado; ?> </span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            <?php    } ?>
                        </div>
                    <?php  }
                    ?>


                    <!-- ALERTA DE QUE YA HAY UN CREDITO EN ESTADO F PARA SEGUIR CON EL PROCESO -->
                    <?php if ($bandera_creditos_desembolsadods) { ?>

                        <div class="alert alert-warning mt-2" role="alert" style="margin-bottom: 0px !important;">
                            <div class="row">
                                <div class="col mb-3">
                                    <h4 class="alert-heading">ADVERTENCIA!</h4>
                                    <p>Advertencia! El cliente seleccionado tiene almenos un crédito en estado activo.</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col mb-2">
                                    <div class="text-center"><b>INFORMACIÓN DE CRÉDITOS ACTIVOS</b></div>
                                </div>
                            </div>

                            <!-- Contenedor de tarjetas -->
                            <div class="row">
                                <?php foreach ($datosactivos as $index => $datoActivo) { ?>
                                    <div class="col-md-4 mb-3">

                                        <div class="ag-courses_item">
                                            <a class="ag-courses-item_link">
                                                <div class="ag-courses-item_bg"></div>
                                                <div class="ag-courses-item_title">Crédito No: <?= $index + 1; ?></div>
                                                <span class="ag-courses-item_title"><b>Código de crédito:</b>
                                                    <?= $datoActivo['ccodcta'] ?></span>
                                                <p class="ag-courses-item_date"><b>Estado:</b> Activo</p>
                                                <p class="ag-courses-item_date"><b>Monto Desembolsado:</b>
                                                    <?= number_format($datoActivo['monto'], 2, '.', ',') ?></p>
                                                <span class="ag-courses-item_date"><b>Fecha desembolso:</b>
                                                    <?= $datoActivo['fechaD'] ?></span>
                                                <div class="ag-courses-item_date-box">

                                                </div>
                                            </a>
                                        </div>

                                    </div>
                                <?php } ?>
                            </div>

                        </div>

                    <?php } ?>

                    <!-- ALERTA DE QUE YA HAY UN CREDITO EN ESTADO I PARA SEGUIR CON EL PROCESO -->
                    <?php if ($bandera_creditos_incobrables) { ?>

                        <div class="alert alert-danger mt-2" role="alert" style="margin-bottom: 0px !important;">
                            <div class="row">
                                <div class="col mb-3">
                                    <h4 class="alert-heading">ADVERTENCIA!</h4>
                                    <p>Advertencia! El cliente seleccionado tiene almenos un credito incobrable por lo tanto no puede
                                        solicitar un credito.</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col mb-2">
                                    <div class="text-center"><b>INFORMACIÓN DE CRÉDITOS INCOBRABLE</b></div>
                                </div>
                            </div>

                            <!-- Contenedor de tarjetas -->
                            <div class="row">
                                <?php foreach ($datosincobrables as $index => $datoActivo) { ?>
                                    <div class="col-md-4 mb-3">

                                        <div class="ag-courses_item card-red">
                                            <a class="ag-courses-item_link">
                                                <div class="ag-courses-item_bg"></div>
                                                <div class="ag-courses-item_title">Crédito No: <?= $index + 1; ?></div>
                                                <span class="ag-courses-item_title"><b>Código de crédito:</b>
                                                    <?= $datoActivo['ccodcta'] ?></span>
                                                <p class="ag-courses-item_date"><b>Estado:</b> Incobrable</p>
                                                <p class="ag-courses-item_date"><b>Monto Desembolsado:</b>
                                                    <?= number_format($datoActivo['monto'], 2, '.', ',') ?></p>
                                                <span class="ag-courses-item_date"><b>Fecha desembolso:</b>
                                                    <?= $datoActivo['fechaD'] ?></span>
                                                <div class="ag-courses-item_date-box">

                                                </div>
                                            </a>
                                        </div>

                                    </div>
                                <?php } ?>
                            </div>

                        </div>

                    <?php } ?>

                    <!-- ALERTA DE QUE YA HAY UN CREDITO EN ESTADO I PARA SEGUIR CON EL PROCESO -->
                    <?php if ($bandera_creditos_juridicos) { ?>

                        <div class="alert alert-secondary  mt-2" role="alert" style="margin-bottom: 0px !important;">
                            <div class="row">
                                <div class="col mb-3">
                                    <h4 class="alert-heading">ADVERTENCIA!</h4>
                                    <p>Advertencia! El cliente seleccionado tiene almenos un credito Juridico por lo tanto no puede
                                        solicitar un credito.</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col mb-2">
                                    <div class="text-center"><b>INFORMACIÓN DE CRÉDITOS JURIDICOS</b></div>
                                </div>
                            </div>

                            <!-- Contenedor de tarjetas -->
                            <div class="row">
                                <?php foreach ($datosjuridicos as $index => $datoActivo) { ?>
                                    <div class="col-md-4 mb-3">

                                        <div class="ag-courses_item card-red">
                                            <a class="ag-courses-item_link">
                                                <div class="ag-courses-item_bg"></div>
                                                <div class="ag-courses-item_title">Crédito No: <?= $index + 1; ?></div>
                                                <span class="ag-courses-item_title"><b>Código de crédito:</b>
                                                    <?= $datoActivo['ccodcta'] ?></span>
                                                <p class="ag-courses-item_date"><b>Estado:</b> Juridico</p>
                                                <p class="ag-courses-item_date"><b>Monto Desembolsado:</b>
                                                    <?= number_format($datoActivo['monto'], 2, '.', ',') ?></p>
                                                <span class="ag-courses-item_date"><b>Fecha desembolso:</b>
                                                    <?= $datoActivo['fechaD'] ?></span>
                                                <div class="ag-courses-item_date-box">

                                                </div>
                                            </a>
                                        </div>

                                    </div>
                                <?php } ?>
                            </div>

                        </div>

                    <?php } ?>

                    <!-- ALERTA PARA AVISAR QUE NO HAY GARANTIAS -->
                    <?php if (!$bandera_garantias && $bandera) { ?>
                        <div class="alert alert-danger mt-2" role="alert" style="margin-bottom: 0px !important;">
                            <div class="row">

                                <div class="col mb-3">
                                    <h4 class="alert-heading">ADVERTENCIA!</h4>

                                    <div class="col-md-4 mb-3">
                                        <div class="ag-courses_item card-red">
                                            <a class="ag-courses-item_link">
                                                <div class="ag-courses-item_bg"></div>
                                                <div class="ag-courses-item_title">IMPORTANTE!</div>
                                                <div class="ag-courses-item_date-box">
                                                    El cliente no tiene registrado al menos una garantia, por lo que no puede solicitar
                                                    el crédito.

                                                    <span class="ag-courses-item_date"> Ingrese en la opción de garantías y agregue al
                                                        menos uno para este cliente. </span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        <?php  } ?>
                        <!-- ALERTA PARA BUSCAR UN CLIENTE  -->
                        <?php if (!$bandera) { ?>
                            <div class="alert alert-success" role="alert" style="margin-bottom: 0px !important;">



                                <h4 class="alert-heading">IMPORTANTE!</h4>
                                <div class="col-md-4 mb-3">
                                    <div class="ag-courses_item card-green">
                                        <a class="ag-courses-item_link">
                                            <div class="ag-courses-item_bg"></div>
                                            <div class="ag-courses-item_title">Debe seleccionar un cliente.</div>
                                            <div class="ag-courses-item_date-box">

                                                <span class="ag-courses-item_date"> luego seleccionar un tipo de crédito para hacer una
                                                    solicitud </span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php  } ?>
                        </div>

                        <div class="container" style="max-width: 100% !important;">
                            <div class="row justify-items-md-center">
                                <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                                    <!-- boton para solicitar credito -->
                                    <?php if (!$bandera_creditos_proceso && $bandera && $bandera_garantias && !$bandera_creditos_incobrables && !$bandera_creditos_juridicos) { ?>
                                        <button class="btn btn-outline-success mt-2"
                                            onclick="obtiene([`codcli`,`nomcli`,`ciclo`,`codprod`,`tasaprod`,`maxprod`,`montosol`,`idprod`,`primerpago`,`cuota`,`crecimiento`,`recomendacion`],[`analista`,`destino`,`sector`,`actividadeconomica`,`agenciaaplica`,`tipocred`,`peri`],[],`create_solicitud`,`0`,['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $datos[0]['ciclo']; ?>',recoletar_checks()])"><i
                                                class="fa-solid fa-floppy-disk me-2"></i>Solicitar crédito</button>
                                    <?php } ?>
                                    <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2('#cuadro','0')">
                                        <i class="fa-solid fa-ban"></i> Cancelar
                                    </button>
                                    <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                                        <i class="fa-solid fa-circle-xmark"></i> Salir
                                    </button>
                                </div>
                            </div>
                        </div>
                </div>
                <?php
                include_once "../../../src/cris_modales/mdls_solicitud_01.php";
                include_once "../../../src/cris_modales/mdls_credlin_indi.php";
                ?>
                <script>
                    $(document).ready(function() {
                        buscar_actividadeconomica(0);
                    })
                    initFormProtection({
                        formId: 'formSolicitudCreditoIndividual',
                        namespace: 'CreditoSolicitud'
                    });
                </script>
            <?php
        }
        break;
    case 'analisis_individual': {
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
            $xtra = $_POST["xtra"];

            //consultar
            $i = 0;
            $sumagarantias = 0;
            $src = '../../includes/img/fotoClienteDefault.png';
            $bandera_garantias = false;
            $bandera_garantias2 = false;
            $bandera = false;
            $datos[] = [];
            $datosgarantias[] = [];
            $datosgarantiasrecuperados[] = [];

            if ($xtra != 0) {
                $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta,cm.cuotassolicita, cm.CodCli AS codcli, cl.short_name AS nomcli, cl.Direccion AS direccion, (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo,  cm.Cestado AS estado,
                cp.id AS idprod, cp.cod_producto AS codprod, cp.nombre AS nomprod, cm.NIntApro AS interesprod, cp.descripcion AS descprod, cp.monto_maximo AS montoprod, ff.descripcion AS nomfondo,
                cm.MontoSol AS montosol, cm.MonSug AS montosug, cm.CtipCre AS tipocred, cm.NtipPerC AS tipoper, cm.DfecPago AS primerpago, cm.noPeriodo AS cuotas, cm.DFecDsbls AS fecdesembolso, cm.Dictamen AS dictamen,
                us.id_usu AS idanalista, CONCAT(us.nombre, ' ', us.apellido) AS nombreanalista, cl.url_img AS urlfoto,cm.crecimiento,cm.recomendacion,cm.peripagcap,cm.afectaInteres
                FROM cremcre_meta cm
                INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                INNER JOIN cre_productos cp ON cm.CCODPRD=cp.id
                INNER JOIN ctb_fuente_fondos ff ON cp.id_fondo=ff.id
                INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu
                WHERE (cm.Cestado='A' OR cm.Cestado='D') AND cm.TipoEnti='INDI' AND cm.CCODCTA='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $estado = ($fila['estado'] == 'A') ? 'Solicitado' : 'Analizado';
                    $datos[$i] = $fila;
                    $datos[$i]['estado2'] = $estado;
                    //CARGADO DE LA IMAGEN
                    $imgurl = __DIR__ . '/../../../../' . $fila['urlfoto'];
                    if (!is_file($imgurl)) {
                        $src = '../../includes/img/fotoClienteDefault.png';
                    } else {
                        $imginfo   = getimagesize($imgurl);
                        $mimetype  = $imginfo['mime'];
                        $imageData = base64_encode(file_get_contents($imgurl));
                        $src = 'data:' . $mimetype . ';base64,' . $imageData;
                    }
                    $i++;
                    $bandera = true;
                }

                //CONSULTAR TODAS LAS GARANTIAS
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT cl.idcod_cliente AS codcli, gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc,
                gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
                IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS nomcli,
                IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS direccioncli
                FROM tb_cliente cl
                INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
                INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
                WHERE cl.estado='1' AND gr.estado=1 AND cl.idcod_cliente='" . $datos[0]['codcli'] . "'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datosgarantias[$i] = $fila;
                    $i++;
                    $bandera_garantias = true;
                }

                //CONSULTAR LOS REGISTROS SELECCIONADOS
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT gc.id_garantia AS id, clg.montoGravamen AS montogravamen FROM tb_garantias_creditos gc
                INNER JOIN cli_garantia clg ON gc.id_garantia=clg.idGarantia
                WHERE gc.id_cremcre_meta='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $sumagarantias = $sumagarantias + $fila["montogravamen"];
                    $datosgarantiasrecuperados[$i] = $fila;
                    $i++;
                    $bandera_garantias2 = true;
                }
            }


            // echo '<pre>';
            // print_r($datosgarantiasrecuperados);
            // echo '</pre>';
            ?>
                <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
                <input type="text" id="file" value="cre_indi_01" style="display: none;">
                <input type="text" id="condi" value="analisis_individual" style="display: none;">
                <div class="text" style="text-align:center">ANÁLISIS DE CRÉDITO INDIVIDUAL</div>
                <div class="card">
                    <div class="card-header">Análisis de crédito individual</div>
                    <div class="card-body" style="padding-bottom: 0px !important;">

                        <!-- seleccion de cliente y su credito-->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Información de cliente</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 col-sm-6 col-md-2 mt-2">
                                    <img width="120" height="130" id="vistaPrevia" src="<?php if ($bandera) {
                                                                                            echo $src;
                                                                                        } else {
                                                                                            echo $src;
                                                                                        } ?>">
                                </div>
                                <div class="col-12 col-sm-12 col-md-10">
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="ccodcta" placeholder="Código de credito"
                                                    readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['ccodcta'] . '"';
                                                    } ?>>
                                                <label for="cliente">Código de crédito</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                                                onclick="abrir_modal('#modal_analisis_01', '#id_modal_hidden', 'id')"><i
                                                    class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar cliente</button>
                                        </div>
                                    </div>
                                    <!-- cargo, nombre agencia y codagencia  -->
                                    <div class="row">
                                        <div class="col-12 col-sm-12 col-md-5">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="codcli" placeholder="Código de cliente"
                                                    readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['codcli'] . '"';
                                                    } ?>>
                                                <input type="text" name="" id="id_cod_cliente" hidden>
                                                <label for="cliente">Código de cliente</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-5">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="nomcli" placeholder="Nombre cliente"
                                                    readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['nomcli'] . '"';
                                                    } ?>>
                                                <label for="nomcli">Nombre de cliente</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-2">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="ciclo" placeholder="Ciclo" readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['ciclo'] . '"';
                                                    } ?>>
                                                <label for="ciclo">Ciclo</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-8">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="direccion" placeholder="Dirección" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['direccion'] . '"';
                                            } ?>>
                                        <label for="direccion">Dirección</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="estado" placeholder="Estado" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['estado2'] . '"';
                                            } ?>>
                                        <label for="estado">Estado</label>
                                    </div>
                                </div>
                            </div>
                            <?php if ($bandera) {
                            ?>
                                <div class="row">
                                    <div class="col-4">
                                        <a class="link-primary" style="cursor: pointer;"
                                            onclick="accesodirecto(1,`<?= $datos[0]['codcli'] ?>`)">Perfil económico</a>
                                    </div>
                                    <div class="col-4">
                                        <a class="link-success" style="cursor: pointer;"
                                            onclick="accesodirecto(2,`<?= $datos[0]['codcli'] ?>`)">Balance económico</a>
                                    </div>
                                    <div class="col-4">
                                        <a class="link-info fw-bold" style="cursor: pointer;"
                                            onclick="abrirAnalisisFinanciero('<?= $datos[0]['codcli'] ?>')">
                                            <i class="fa-solid fa-chart-line me-1"></i>Análisis Financiero
                                        </a>
                                    </div>
                                </div>
                            <?php
                            } ?>
                        </div>
                        <div class="container contenedort">
                            <div class="accordion accordion-flush" id="accordionFlushExample">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#flush-collapseOne" aria-expanded="false"
                                            aria-controls="flush-collapseOne">
                                            <b>Datos adicionales</b>
                                        </button>
                                    </h2>
                                    <div id="flush-collapseOne" class="accordion-collapse collapse"
                                        data-bs-parent="#accordionFlushExample">
                                        <div class="accordion-body">
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="form-floating mb-3">
                                                        <textarea readonly type="text" class="form-control" id="crecimiento"
                                                            placeholder="Crecimiento"><?php echo ($bandera) ? $datos[0]['crecimiento'] : ''; ?></textarea>
                                                        <label for="primerpago">Crecimiento</label>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-floating mb-3">
                                                        <textarea readonly type="text" class="form-control" id="recomendacion"
                                                            placeholder="Recomendacion"><?php echo ($bandera) ? $datos[0]['recomendacion'] : ''; ?></textarea>
                                                        <label for="cuota">Recomendacion del oficial de crédito</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- lineas de credito -->
                        <div class="container contenedort" style="max-width: 100% !important;" <?php //($bandera) ? ('') : ('hidden') 
                                                                                                ?>>
                            <div class="row">
                                <div class="col mt-2 mb-2">
                                    <button <?= ($bandera) ? ('') : ('disabled') ?> type="button" class="btn btn-outline-primary"
                                        title="Buscar Grupo"
                                        onclick="abrir_modal('#modal_tiposcreditos', '#id_modal_hidden', 'idprod,codprod,nameprod,descprod,tasaprod,maxprod,ahorro/A,A,A,A,A,A,A/'+'/#/#/#/#')">
                                        <i class="fa-solid fa-magnifying-glass"> </i>Buscar Linea de Credito </button>
                                </div>
                            </div>

                            <div class="alert alert-primary" role="alert">
                                <div class="row crdbody">
                                    <div class="col-sm-3">
                                        <div class="">
                                            <span class="fw-bold">Codigo Producto</span>
                                            <input type="number" class="form-control" id="idprod" readonly hidden <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['idprod'] . '"';
                                                                                                                    } ?>>
                                            <input type="text" class="form-control" id="codprod" readonly <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['codprod'] . '"';
                                                                                                            } ?>>
                                        </div>
                                    </div>
                                    <div class="form-group col-sm-7">
                                        <span class="fw-bold">Nombre</span>
                                        <input type="text" class="form-control" id="nameprod" readonly <?php if ($bandera) {
                                                                                                            echo 'value="' . $datos[0]['nomprod'] . '"';
                                                                                                        } ?>>
                                    </div>
                                    <div class="form-group col-sm-2">
                                        <span class="fw-bold">%Interes </span>
                                        <input type="number" step="0.01" class="form-control" id="tasaprod" <?php if (!$bandera) {
                                                                                                                echo 'disabled';
                                                                                                            }
                                                                                                            if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['interesprod'] . '"';
                                                                                                            } ?>>
                                    </div>
                                </div>
                                <div class="row crdbody">
                                    <div class="form-group col-sm-6">
                                        <span class="fw-bold">Descripción</span>
                                        <input type="text" class="form-control" id="descprod" readonly <?php if ($bandera) {
                                                                                                            echo 'value="' . $datos[0]['descprod'] . '"';
                                                                                                        } ?>>
                                    </div>

                                    <div class=" col-sm-3">
                                        <span class="fw-bold">Monto Maximo</span>
                                        <input type="number" step="0.01" class="form-control" id="maxprod" readonly <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['montoprod'] . '"';
                                                                                                                    } ?>>
                                    </div>
                                    <div class="col-sm-3">
                                        <span class="fw-bold">Fuente de fondos</span>
                                        <input type="text" step="0.01" class="form-control" id="ahorro" readonly <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['nomfondo'] . '"';
                                                                                                                    } ?>>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- datos adicionales -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Datos complementarios</b></div>
                                </div>
                            </div>
                            <!-- Monto aprobado y solicitado -->
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <select class="form-select" id="analista">
                                            <option value="0" selected>Seleccione un analista</option>
                                            <?php
                                            //$consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA' AND id_agencia IN( SELECT id_agencia FROM tb_usuario WHERE id_usu=$codusu)");
                                            $consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA' AND estado=1");
                                            $selected = "";
                                            while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                if ($bandera) {
                                                    ($dtas["id_usu"] == $datos[0]['idanalista']) ? $selected = "selected" : $selected = "";
                                                }
                                                $nombre = $dtas["nameusu"];
                                                $id_usu = $dtas["id_usu"];
                                                echo '<option value="' . $id_usu . '" ' . $selected . '>' . $nombre . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <label for="analista">Analista</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="garantia" placeholder="Valor garantia" readonly
                                            disabled
                                            <?php if ($bandera_garantias) {
                                                echo 'value="' . $sumagarantias . '"';
                                            } ?>>
                                        <label for="montosol">Valor total de garantias</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <input disabled readonly type="number" min="1" class="form-control" id="cuotasol"
                                            placeholder="No de cuotas"
                                            value="<?php echo ($bandera) ? $datos[0]['cuotassolicita'] : ''; ?>">
                                        <label for="cuotasol">Cuotas solicitadas</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="montosol" placeholder="Monto solicitado"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['montosol'] . '"';
                                            } ?>>
                                        <label for="montosol">Monto solicitado</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input min="0" type="number" class="form-control" id="montosug"
                                            placeholder="Monto por aprobar"
                                            <?php if ($bandera && $datos[0]['cuotas'] != '') {
                                                echo 'value="' . $datos[0]['montosug'] . '"';
                                            } ?>>
                                        <label for="montosug">Monto a aprobar</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="primerpago" placeholder="Fecha primer pago"
                                            value="<?php echo ($bandera) ? $datos[0]['primerpago'] : date('Y-m-d'); ?>">
                                        <label for="primerpago">Fecha primer pago</label>
                                    </div>
                                </div>
                            </div>
                            <!-- tipo de credito, tipo periodo, fecha primer cuota -->
                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="tipocred"
                                            onchange="creperi('tpscre2','#alrtpnl','cre_indi_01',this.value);">
                                            <option value="0" selected disabled>Seleccione un tipo de crédito</option>
                                            <?php
                                            $consulta = mysqli_query($general, "SELECT abre, Credito FROM `tb_credito`");
                                            while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $id_abre = $dtas["abre"];
                                                $nomtip = $dtas["Credito"];
                                                echo '<option value="' . $id_abre . '">' . $nomtip . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <label for="tipocred">Tipo de crédito</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="peri" onchange="changestatus()">
                                            <option value="0" selected disabled>Seleccione un tipo de periodo</option>

                                        </select>
                                        <label for="peri">Tipo de periodo</label>
                                    </div>
                                </div>
                                <div id="divperipagcap"
                                    style="display:<?= ($bandera && $datos[0]['tipocred'] == "Flat" && $datos[0]['tipoper'] == "1M") ? 'block' : 'none' ?>;"
                                    class="col-12 col-sm-6 col-md-4">
                                    <div class="row">
                                        <div class="form-floating mb-3 col-10">
                                            <select class="form-select" id="peripagcap">
                                                <option value="1" <?= ($bandera && $datos[0]['peripagcap'] == 1) ? "selected" : "" ?>>
                                                    Mensual</option>
                                                <option value="2" <?= ($bandera && $datos[0]['peripagcap'] == 2) ? "selected" : "" ?>>
                                                    Bimensual</option>
                                                <option value="3" <?= ($bandera && $datos[0]['peripagcap'] == 3) ? "selected" : "" ?>>
                                                    Trimestral</option>
                                                <option value="6" <?= ($bandera && $datos[0]['peripagcap'] == 6) ? "selected" : "" ?>>
                                                    Semestral</option>
                                                <option value="12" <?= ($bandera && $datos[0]['peripagcap'] == 12) ? "selected" : "" ?>>
                                                    Anual</option>
                                            </select>
                                            <label for="peripagcap">Pago de capital</label>
                                        </div>
                                        <div class="form-floating mb-3 col-2">
                                            <input class="form-check-input" <?= ($bandera && $datos[0]['afectaInteres'] == 1) ? "checked" : "" ?> type="checkbox" id="afint">
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <!-- cuota, desembolso y dictamen -->
                            <div class="row">
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="fecdesembolso" placeholder="Fecha de desembolso"
                                            <?php if ($bandera && $datos[0]['cuotas'] != '') {
                                                echo 'value="' . $datos[0]['fecdesembolso'] . '"';
                                            } else {
                                                echo 'value="' . date('Y-m-d') . '"';
                                            } ?>>
                                        <label for="fecdesembolso">Fecha de desembolso</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="dictamen" placeholder="No. Dictamen"
                                            <?php if ($bandera && $datos[0]['cuotas'] != '') {
                                                echo 'value="' . $datos[0]['dictamen'] . '"';
                                            } ?>>
                                        <label for="dictamen">No. Dictamen</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input type="number" min="1" class="form-control" id="cuota" placeholder="No de cuotas"
                                            value="<?php echo (!$bandera) ? '' : (($datos[0]['cuotas'] == '') ? $datos[0]['cuotassolicita'] : $datos[0]['cuotas']); ?>">
                                        <label for="cuota">No. de cuotas a aprobar</label>
                                    </div>
                                </div>
                            </div>
                            <!-- MENSAJE PARA MOSTRAR LOS TIPOS DE CREDITOS -->
                            <div class="row">
                                <div class="col">
                                    <div class="input-group" id="tipsMEns">
                                        <div class="alert alert-success" role="alert" id="alrtpnl">
                                            <h4>Seleccione un tipo de crédito y un periodo de crédito</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- BOTON PARA GUARDAR CAMBIOS Y ASI GENERAR EL PLAN DE PAGO -->
                            <div class="row">
                                <div class="col">
                                    <?php if ($bandera && $bandera_garantias) { ?>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="obtiene([`ccodcta`,`codcli`,`nomcli`,`tasaprod`,`montosol`,`montosug`,`primerpago`,`cuota`,`fecdesembolso`,`dictamen`,`idprod`,`codprod`,`maxprod`],[`tipocred`,`peri`,`analista`,`peripagcap`],[],`update_analisis`,'<?= $datos[0]['ccodcta']; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $datos[0]['ciclo']; ?>',checkafeint()])"><i
                                                class="fa-solid fa-floppy-disk me-2"></i>Guardar cambios</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <!-- SECCION DE GARANTIAS -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Garantías del cliente</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col mb-2">
                                    <div class="table-responsive">
                                        <table class="table mb-0" style="font-size: 0.8rem !important;">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Tipo Garantia</th>
                                                    <th scope="col">Tipo Doc.</th>
                                                    <th scope="col">Descripción</th>
                                                    <th scope="col">Dirección</th>
                                                    <th scope="col">Valor gravamen</th>
                                                    <th scope="col">Marcar</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider">
                                                <?php if ($bandera_garantias) {
                                                    for ($i = 0; $i < count($datosgarantias); $i++) { ?>
                                                        <tr>
                                                            <td scope="row"><?= ($datosgarantias[$i]["nomtipgar"]) ?></td>
                                                            <td><?= ($datosgarantias[$i]["nomtipdoc"]) ?></td>
                                                            <!-- VALIDAR SI ES UN GARANTIA NORMAL O ES UN FIADOR -->
                                                            <?php if ($datosgarantias[$i]["idtipgar"] == 1 && ($datosgarantias[$i]["idtipdoc"] == 1 || $datosgarantias[$i]["idtipdoc"] == 17)) { ?>
                                                                <td><?= ($datosgarantias[$i]["nomcli"]) ?></td>
                                                                <td><?= ($datosgarantias[$i]["direccioncli"]) ?></td>
                                                            <?php } else { ?>
                                                                <td><?= ($datosgarantias[$i]["descripcion"]) ?></td>
                                                                <td><?= ($datosgarantias[$i]["direccion"]) ?></td>
                                                            <?php } ?>
                                                            <td><span
                                                                    id="<?= "MA_" . $datosgarantias[$i]['idgar']; ?>"><?= ($datosgarantias[$i]["montogravamen"]) ?></span>
                                                            </td>
                                                            <td>
                                                                <input class="form-check-input S" type="checkbox"
                                                                    value="<?= $datosgarantias[$i]['idgar']; ?>"
                                                                    id="<?= "S_" . $datosgarantias[$i]['idgar']; ?>"
                                                                    onclick="suma_garantias_de_chequeados('#garantia')">
                                                            </td>
                                                        </tr>
                                                <?php }
                                                } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- BOTON PARA GUARDAR CAMBIOS Y ASI GENERAR EL PLAN DE PAGO -->
                            <div class="row">
                                <div class="col mb-2">
                                    <?php if ($bandera_garantias) { ?>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="obtiene([`ccodcta`,`codcli`],[],[],`update_garantias`,'<?= $datos[0]['ccodcta']; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $datos[0]['ciclo']; ?>',recoletar_checks()])"><i
                                                class="fa-solid fa-floppy-disk me-2"></i>Actualizar garantias</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <!-- Vinculacion de Ingresos Con creditos -->

                                                <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Agregar Gastos al Credito del cliente</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col mb-2">
                                    <div class="table-responsive">
                                        <table class="table mb-0" style="font-size: 0.8rem !important;">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Tipo Garantia</th>
                                                    <th scope="col">Tipo Doc.</th>
                                                    <th scope="col">Descripción</th>
                                                    <th scope="col">Dirección</th>
                                                    <th scope="col">Valor gravamen</th>
                                                    <th scope="col">Marcar</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider">
                                                <?php if ($bandera_garantias) {
                                                    for ($i = 0; $i < count($datosgarantias); $i++) { ?>
                                                        <tr>
                                                            <td scope="row"><?= ($datosgarantias[$i]["nomtipgar"]) ?></td>
                                                            <td><?= ($datosgarantias[$i]["nomtipdoc"]) ?></td>
                                                            <!-- VALIDAR SI ES UN GARANTIA NORMAL O ES UN FIADOR -->
                                                            <?php if ($datosgarantias[$i]["idtipgar"] == 1 && ($datosgarantias[$i]["idtipdoc"] == 1 || $datosgarantias[$i]["idtipdoc"] == 17)) { ?>
                                                                <td><?= ($datosgarantias[$i]["nomcli"]) ?></td>
                                                                <td><?= ($datosgarantias[$i]["direccioncli"]) ?></td>
                                                            <?php } else { ?>
                                                                <td><?= ($datosgarantias[$i]["descripcion"]) ?></td>
                                                                <td><?= ($datosgarantias[$i]["direccion"]) ?></td>
                                                            <?php } ?>
                                                            <td><span
                                                                    id="<?= "MA_" . $datosgarantias[$i]['idgar']; ?>"><?= ($datosgarantias[$i]["montogravamen"]) ?></span>
                                                            </td>
                                                            <td>
                                                                <input class="form-check-input S" type="checkbox"
                                                                    value="<?= $datosgarantias[$i]['idgar']; ?>"
                                                                    id="<?= "S_" . $datosgarantias[$i]['idgar']; ?>"
                                                                    onclick="suma_garantias_de_chequeados('#garantia')">
                                                            </td>
                                                        </tr>
                                                <?php }
                                                } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- BOTON PARA GUARDAR CAMBIOS Y ASI GENERAR EL PLAN DE PAGO -->
                            <div class="row">
                                <div class="col mb-2">
                                    <?php if ($bandera_garantias) { ?>
                                        <button type="button" class="btn btn-outline-success"
                                            onclick="obtiene([`ccodcta`,`codcli`],[],[],`update_garantias`,'<?= $datos[0]['ccodcta']; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $datos[0]['ciclo']; ?>',recoletar_checks()])"><i
                                                class="fa-solid fa-floppy-disk me-2"></i>Actualizar garantias</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>


                        <!-- ALERTA PARA BUSCAR UN CLIENTE  -->
                        <?php if (!$bandera) { ?>

                            <div class="alert alert-warning" role="alert" style="margin-bottom: 0px !important;">
                                <h4 class="alert-heading">IMPORTANTE!</h4>

                                <div class="col-md-4 mb-3">
                                    <div class="ag-courses_item card-yellow">
                                        <a class="ag-courses-item_link" href="#">
                                            <div class="ag-courses-item_bg"></div>
                                            <div class="ag-courses-item_title">Debe seleccionar un cliente</div>
                                            <span class="ag-courses-item_date">a analizar </span>
                                        </a>
                                    </div>
                                </div>
                                <!-- 
                        <p> a analizar</p> -->
                            </div>

                        <?php  } ?>
                        <!--  -->
                        <?php if ($bandera) {
                            if (!$bandera_garantias && !$bandera_garantias2) { ?>
                                <div class="alert alert-warning" role="alert" style="margin-bottom: 0px !important;">
                                    <h4 class="alert-heading">IMPORTANTE!</h4>
                                    <p>No se puede seguir con el analisis del cliente debido a que no se cargaron correctamente las
                                        garantias.
                                    </p>
                                </div>
                            <?php  } elseif ($bandera_garantias && !$bandera_garantias2) { ?>
                                <div class="alert alert-warning" role="alert" style="margin-bottom: 0px !important;">
                                    <h4 class="alert-heading">IMPORTANTE!</h4>
                                    <p>El cliente no tiene al menos una garantia seleccionada, por lo que debe seleccionar al menos uno y
                                        presionar el boton actualizar garantias para actualizar datos y seguir con el proceso.</p>
                                </div>
                                <?php } else {
                                if ($bandera_garantias && $bandera_garantias2 && $datos[0]['cuotas'] == '') { ?>
                                    <div class="alert alert-warning" role="alert" style="margin-bottom: 0px !important;">
                                        <h4 class="alert-heading">IMPORTANTE!</h4>
                                        <p>Debe llenar los datos faltantes en la seccion de datos complementarios y luego presionar el boton
                                            guardar
                                            cambios para poder visualizar el plan de pago, aprobar el credito o bien rechazar el crédito.</p>
                                    </div>
                        <?php }
                            }
                        } ?>
                    </div>

                    <div class="container" style="max-width: 100% !important;">
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                                <?php if ($bandera && $bandera_garantias2 && $bandera_garantias && $datos[0]['cuotas'] != '') { ?>
                                    <button class="btn btn-outline-success mt-2"
                                        onclick="obtiene([`ccodcta`,`codcli`,`nomcli`,`tasaprod`,`montosol`,`montosug`,`primerpago`,`cuota`,`fecdesembolso`,`dictamen`,`idprod`,`codprod`,`maxprod`],[`tipocred`,`peri`,`analista`,`peripagcap`],[],`create_analisis`,`0`,['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $datos[0]['ciclo']; ?>','<?= $bandera_garantias; ?>',recoletar_checks()])"><i
                                            class="fa-solid fa-floppy-disk me-2"></i>Pasar a aprobacion</button>
                                    <button type="button" class="btn btn-outline-primary mt-2"
                                        onclick="reportes([[],[],[],['<?= $datos[0]['ccodcta']; ?>']], `pdf`, 40,0,1)"> Generar
                                        plan
                                    </button>
                                    <button type="button" class="btn btn-outline-danger mt-2"
                                        onclick="abrir_modal_cualquiera_con_valor('#modal_cancelar_credito', '#id_hidden', `<?= $datos[0]['ccodcta']; ?>,<?= $datos[0]['codcli']; ?>`,[`#credito`,`#nombre`])"><i
                                            class="fa-solid fa-sack-xmark me-2"></i>Rechazar Crédito</button>
                                <?php } ?>
                                <!-- Boton de rechazo -->
                                <?php if ($bandera) { ?>
                                <?php } ?>
                                <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                                    <i class="fa-solid fa-circle-xmark"></i> Salir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                include_once "../../../src/cris_modales/mdls_analisis_01.php";
                include_once "../../../src/cris_modales/mdls_credlin_indi.php";
                include_once "../../../src/cris_modales/mdls_cancelar_credito.php";
                include_once "../../../src/cris_modales/mdls_analisis_financiero.php";
                ?>
                <script>
                    <?php
                    if ($bandera && $datos[0]["tipocred"] != '') {
                        echo "update('" . $datos[0]["tipocred"] . "','" . $datos[0]["tipoper"] . "');";
                    }
                    ?>

                    function update(val1, val2) {
                        dire = "../../views/Creditos/cre_indi/cre_indi_01.php";
                        creperi('tpscre2', '#alrtpnl', 'cre_indi_01', val1, function() {
                            $("#tipocred option[value='" + val1 + "']").attr("selected", true);
                            $.ajax({
                                url: dire,
                                method: "POST",
                                data: {
                                    condi: 'prdscre',
                                    xtra: val1
                                },
                                beforeSend: function() {
                                    loaderefect(1);
                                },
                                success: function(data) {
                                    $('#peri').html(data);
                                    $("#peri option[value='" + val2 + "']").attr("selected", true);
                                    loaderefect(1);
                                },
                                complete: function(data) {
                                    loaderefect(0);
                                }
                            });
                        });
                    }

                    //SELECCIONAR LOS CHECKBOXS DESPUES DE CARGAR EL DOM
                    $(document).ready(function() {
                        <?php if ($bandera_garantias2) { ?>
                            marcar_garantias_recuperadas(<?php echo json_encode($datosgarantiasrecuperados); ?>);
                        <?php } ?>
                    });

                    function accesodirecto(opcion, codcliente) {
                        var nuevaVentana = window.open('../cliente.php', '_blank');
                        nuevaVentana.onload = function() {
                            switch (opcion) {
                                case 1:
                                    nuevaVentana.printdiv('create_perfil_economico', '#cuadro', 'clientes_001', codcliente);
                                    break;
                                case 2:
                                    nuevaVentana.printdiv('balance_economico', '#cuadro', 'tem_clint', codcliente);
                                    break;
                            }
                        };
                    }

                    function changestatus() {
                        let data = getselectsval(['tipocred', 'peri']);
                        // console.log(data);
                        let tipcredito = data[0];
                        let tipperiodo = data[1];
                        if (tipcredito == "Flat" && tipperiodo == "1M") $("#divperipagcap").show();
                        else $("#divperipagcap").hide();
                        $('#peripagcap').val('1');
                    }

                    function checkafeint() {
                        const afintChecked = document.getElementById('afint').checked;
                        return afintChecked;
                    }
                </script>
            <?php
        }
        break;
    
    case 'obtener_datos_analisis_financiero': {
            $codCliente = $_POST["xtra"];
            
            try {
                // Inicializar respuesta
                $response = [
                    'success' => false,
                    'cliente' => null,
                    'balance' => null,
                    'ahorros' => [],
                    'aportaciones' => [],
                    'garantias' => []
                ];
                
                // 1. Obtener datos del cliente
                $queryCliente = "SELECT 
                    cl.idcod_cliente AS codcli,
                    cl.compl_name AS nombre,
                    cl.no_identifica AS dpi,
                    cl.tel_no1 AS telefono,
                    cl.email,
                    cl.Direccion AS direccion,
                    cl.profesion,
                    cl.url_img AS urlfoto
                FROM tb_cliente cl
                WHERE cl.idcod_cliente = ?";
                
                $stmt = $conexion->prepare($queryCliente);
                $stmt->bind_param("s", $codCliente);
                $stmt->execute();
                $resultCliente = $stmt->get_result();
                
                if ($resultCliente->num_rows > 0) {
                    $cliente = $resultCliente->fetch_assoc();
                    
                    // Procesar foto del cliente
                    $src = '../../includes/img/fotoClienteDefault.png';
                    if ($cliente['urlfoto']) {
                        $imgurl = __DIR__ . '/../../../' . $cliente['urlfoto'];
                        if (is_file($imgurl)) {
                            $imginfo = getimagesize($imgurl);
                            $mimetype = $imginfo['mime'];
                            $imageData = base64_encode(file_get_contents($imgurl));
                            $src = 'data:' . $mimetype . ';base64,' . $imageData;
                        }
                    }
                    $cliente['foto'] = $src;
                    $response['cliente'] = $cliente;
                } else {
                    throw new Exception("Cliente no encontrado");
                }
                
                // 2. Obtener balance económico
                $queryBalance = "SELECT 
                    COALESCE(disponible, 0) AS disponible,
                    COALESCE(cuenta_por_cobrar2, 0) AS cuenta_por_cobrar2,
                    COALESCE(inventario, 0) AS inventario,
                    COALESCE(activo_fijo, 0) AS activo_fijo,
                    COALESCE(proveedores, 0) AS proveedores,
                    COALESCE(otros_prestamos, 0) AS otros_prestamos,
                    COALESCE(prest_instituciones, 0) AS prest_instituciones,
                    COALESCE(patrimonio, 0) AS patrimonio,
                    COALESCE(ventas, 0) AS ventas,
                    COALESCE(cuenta_por_cobrar, 0) AS cuenta_por_cobrar,
                    COALESCE(mercaderia, 0) AS mercaderia,
                    COALESCE(negocio, 0) AS negocio,
                    COALESCE(pago_creditos, 0) AS pago_creditos
                FROM tb_cli_balance
                WHERE ccodcli = ?
                ORDER BY id DESC
                LIMIT 1";
                
                $stmt = $conexion->prepare($queryBalance);
                $stmt->bind_param("s", $codCliente);
                $stmt->execute();
                $resultBalance = $stmt->get_result();
                
                if ($resultBalance->num_rows > 0) {
                    $balance = $resultBalance->fetch_assoc();
                    // Convertir todos los valores a números
                    foreach ($balance as $key => $value) {
                        $balance[$key] = floatval($value);
                    }
                    $response['balance'] = $balance;
                } else {
                    // Si no hay balance, devolver estructura vacía con ceros
                    $response['balance'] = [
                        'disponible' => 0,
                        'cuenta_por_cobrar2' => 0,
                        'inventario' => 0,
                        'activo_fijo' => 0,
                        'proveedores' => 0,
                        'otros_prestamos' => 0,
                        'prest_instituciones' => 0,
                        'patrimonio' => 0,
                        'ventas' => 0,
                        'cuenta_por_cobrar' => 0,
                        'mercaderia' => 0,
                        'negocio' => 0,
                        'pago_creditos' => 0
                    ];
                    $response['mensaje_balance'] = 'El cliente no tiene balance económico registrado';
                }
                
                // 3. Obtener cuentas de ahorro
                $queryAhorros = "SELECT 
                    cta.ccodaho AS codigo,
                    cta.nlibreta,
                    cta.estado,
                    calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) AS saldo
                FROM ahomcta cta
                WHERE cta.ccodcli = ?
                    AND cta.estado = 'A'
                ORDER BY cta.fecha_apertura DESC";
                
                $stmt = $conexion->prepare($queryAhorros);
                $stmt->bind_param("s", $codCliente);
                $stmt->execute();
                $resultAhorros = $stmt->get_result();
                
                while ($row = $resultAhorros->fetch_assoc()) {
                    // Convertir saldo a float
                    $row['saldo'] = floatval($row['saldo'] ?? 0);
                    $response['ahorros'][] = $row;
                }
                
                // 4. Obtener cuentas de aportación
                $queryAportaciones = "SELECT 
                    cta.ccodaport AS codigo,
                    cta.nlibreta,
                    cta.estado,
                    calcular_saldo_apr_tipcuenta(cta.ccodaport, CURDATE()) AS saldo
                FROM aprcta cta
                WHERE cta.ccodcli = ?
                    AND cta.estado = 'A'
                ORDER BY cta.fecha_apertura DESC";
                
                $stmt = $conexion->prepare($queryAportaciones);
                $stmt->bind_param("s", $codCliente);
                $stmt->execute();
                $resultAportaciones = $stmt->get_result();
                
                while ($row = $resultAportaciones->fetch_assoc()) {
                    // Convertir saldo a float
                    $row['saldo'] = floatval($row['saldo'] ?? 0);
                    $response['aportaciones'][] = $row;
                }
                
                // 5. Obtener créditos del cliente
                $queryCreditos = "SELECT 
                    cre.CCODCTA AS codigo_credito,
                    cre.CtipCre AS tipo_credito,
                    cre.DFecDsbls AS fecha_desembolso,
                    cre.NCapDes AS monto_otorgado,
                    cre.NIntApro AS tasa_interes,
                    cre.noPeriodo AS plazo,
                    cre.Cestado AS estado,
                    CAST(
                        CASE 
                            WHEN (cre.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 
                            THEN (cre.NCapDes - IFNULL(kar.sum_KP, 0)) 
                            ELSE 0 
                        END AS DECIMAL(15,2)
                    ) AS saldo_capital
                FROM cremcre_meta cre
                LEFT JOIN (
                    SELECT 
                        CCODCTA,
                        SUM(KP) AS sum_KP
                    FROM CREDKAR
                    WHERE CESTADO != 'X' AND CTIPPAG = 'P'
                    GROUP BY CCODCTA
                ) kar ON kar.CCODCTA = cre.CCODCTA
                WHERE cre.CodCli = ?
                    AND cre.Cestado IN ('V', 'F', 'G')
                ORDER BY cre.DFecDsbls DESC";
                
                $response['creditos'] = [];
                $stmt = $conexion->prepare($queryCreditos);
                $stmt->bind_param("s", $codCliente);
                $stmt->execute();
                $resultCreditos = $stmt->get_result();
                
                while ($row = $resultCreditos->fetch_assoc()) {
                    // Convertir valores a float
                    $row['saldo_capital'] = floatval($row['saldo_capital'] ?? 0);
                    $row['monto_otorgado'] = floatval($row['monto_otorgado'] ?? 0);
                    $row['tasa_interes'] = floatval($row['tasa_interes'] ?? 0);
                    $response['creditos'][] = $row;
                }
                
                // 6. Obtener garantías del cliente
                $queryGarantias = "SELECT 
                    gr.idGarantia AS idgar,
                    tipgar.id_TiposGarantia AS idtipgar,
                    tipgar.TiposGarantia AS nomtipgar,
                    tipc.idDoc AS idtipdoc,
                    tipc.NombreDoc AS nomtipdoc,
                    gr.descripcionGarantia AS descripcion,
                    gr.direccion AS direccion,
                    gr.montoGravamen AS montogravamen,
                    IFNULL((SELECT cl2.short_name 
                            FROM tb_cliente cl2 
                            WHERE cl2.idcod_cliente = gr.descripcionGarantia 
                            AND tipgar.id_TiposGarantia = 1 
                            AND (tipc.idDoc = 1 OR tipc.idDoc = 17)), 
                            gr.descripcionGarantia) AS descripcion_real,
                    IFNULL((SELECT cl2.Direccion 
                            FROM tb_cliente cl2 
                            WHERE cl2.idcod_cliente = gr.descripcionGarantia 
                            AND tipgar.id_TiposGarantia = 1 
                            AND (tipc.idDoc = 1 OR tipc.idDoc = 17)), 
                            gr.direccion) AS direccion_real
                FROM cli_garantia gr
                INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa = tipgar.id_TiposGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc = gr.idTipoDoc
                WHERE gr.idCliente = ?
                    AND gr.estado = 1";
                
                $stmt = $conexion->prepare($queryGarantias);
                $stmt->bind_param("s", $codCliente);
                $stmt->execute();
                $resultGarantias = $stmt->get_result();
                
                while ($row = $resultGarantias->fetch_assoc()) {
                    // Usar descripción real si es fiador
                    $row['descripcion'] = $row['descripcion_real'];
                    $row['direccion'] = $row['direccion_real'];
                    unset($row['descripcion_real']);
                    unset($row['direccion_real']);
                    $response['garantias'][] = $row;
                }
                
                // Estructurar productos en un objeto
                $response['productos'] = [
                    'ahorros' => $response['ahorros'],
                    'aportaciones' => $response['aportaciones'],
                    'creditos' => $response['creditos']
                ];
                
                $response['success'] = true;
                header('Content-Type: application/json');
                echo json_encode($response);
                
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'mensaje' => $e->getMessage()
                ]);
            }
            
            mysqli_close($conexion);
            exit;
        }
        break;
    
    case 'aprobacion_individual': {
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
            $xtra = $_POST["xtra"];

            //consultar
            $i = 0;
            $bandera = false;
            $bandera_garantias = false;
            $datos[] = [];
            $datosgarantias[] = [];
            $src = '../../includes/img/fotoClienteDefault.png';

            if ($xtra != 0) {
                $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cm.CodCli AS codcli, cl.short_name AS nomcli, cl.Direccion AS direccion, (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo,  cm.Cestado AS estado,
                cp.id AS idprod, cp.cod_producto AS codprod, cp.nombre AS nomprod, cm.NIntApro AS interesprod, cp.descripcion AS descprod, cp.monto_maximo AS montoprod, ff.descripcion AS nomfondo,
                cm.MontoSol AS montosol, cm.MonSug AS montosug, cm.CtipCre AS tipocred, cm.NtipPerC AS tipoper, cm.DfecPago AS primerpago, cm.noPeriodo AS cuotas, cm.DFecDsbls AS fecdesembolso, cm.Dictamen AS dictamen,
                us.id_usu AS idanalista, CONCAT(us.nombre, ' ', us.apellido) AS nombreanalista, cl.url_img AS urlfoto, tipc.Credito AS nomtipocred, per.nombre AS nomperiodo
                FROM cremcre_meta cm
                INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                INNER JOIN cre_productos cp ON cm.CCODPRD=cp.id
                INNER JOIN ctb_fuente_fondos ff ON cp.id_fondo=ff.id
                INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu
                INNER JOIN $db_name_general.tb_credito tipc ON cm.CtipCre=tipc.abre
                INNER JOIN $db_name_general.tb_periodo per ON cm.NtipPerC=per.periodo
                WHERE cm.Cestado='D' AND cm.TipoEnti='INDI' AND cm.CCODCTA='$xtra' LIMIT 1");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $estado = ($fila['estado'] == 'D') ? 'Analizado' : ' ';
                    $datos[$i] = $fila;
                    $datos[$i]['estado2'] = $estado;
                    //CARGADO DE LA IMAGEN
                    $imgurl = __DIR__ . '/../../../../' . $fila['urlfoto'];
                    if (!is_file($imgurl)) {
                        $src = '../../includes/img/fotoClienteDefault.png';
                    } else {
                        $imginfo   = getimagesize($imgurl);
                        $mimetype  = $imginfo['mime'];
                        $imageData = base64_encode(file_get_contents($imgurl));
                        $src = 'data:' . $mimetype . ';base64,' . $imageData;
                    }
                    $i++;
                    $bandera = true;
                }

                //CONSULTAR TODAS LAS GARANTIAS
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc,
                gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
                IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS nomcli,
                IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS direccioncli
                FROM tb_cliente cl
                INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
                INNER JOIN tb_garantias_creditos tbg ON gr.idGarantia=tbg.id_garantia
                INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
                WHERE cl.estado='1' AND gr.estado=1 AND tbg.id_cremcre_meta='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datosgarantias[$i] = $fila;
                    $i++;
                    $bandera_garantias = true;
                }
            }
            ?>
                <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
                <input type="text" id="file" value="cre_indi_01" style="display: none;">
                <input type="text" id="condi" value="aprobacion_individual" style="display: none;">
                <div class="text" style="text-align:center">APROBACIÓN DE CRÉDITO INDIVIDUAL</div>
                <div class="card">
                    <div class="card-header">Aprobación de crédito individual</div>
                    <div class="card-body" style="padding-bottom: 0px !important;">

                        <!-- seleccion de cliente y su credito-->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Información de cliente</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 col-sm-6 col-md-2 mt-2">
                                    <img width="120" height="130" id="vistaPrevia" src="<?php if ($bandera) {
                                                                                            echo $src;
                                                                                        } else {
                                                                                            echo $src;
                                                                                        } ?>">
                                </div>
                                <div class="col-12 col-sm-12 col-md-10">
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="ccodcta" placeholder="Código de credito"
                                                    readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['ccodcta'] . '"';
                                                    } ?>>
                                                <label for="ccodcta">Código de crédito</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                                                onclick="abrir_modal('#modal_aprobacion_01', '#id_modal_hidden', 'id')"><i
                                                    class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar cliente</button>
                                        </div>
                                    </div>
                                    <!-- cargo, nombre agencia y codagencia  -->
                                    <div class="row">
                                        <div class="col-12 col-sm-12 col-md-3">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="codcli" placeholder="Código de cliente"
                                                    readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['codcli'] . '"';
                                                    } ?>>
                                                <input type="text" name="" id="id_cod_cliente" hidden>
                                                <label for="cliente">Código de cliente</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-7">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="nomcli" placeholder="Nombre cliente"
                                                    readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['nomcli'] . '"';
                                                    } ?>>
                                                <label for="nomcli">Nombre de cliente</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6 col-md-2">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="ciclo" placeholder="Ciclo" readonly
                                                    <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['ciclo'] . '"';
                                                    } ?>>
                                                <label for="ciclo">Ciclo</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-8">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="direccion" placeholder="Dirección" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['direccion'] . '"';
                                            } ?>>
                                        <label for="direccion">Dirección</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="estado" placeholder="Estado" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['estado2'] . '"';
                                            } ?>>
                                        <label for="estado">Estado</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- LINEA DE CREDITO MEJORADO -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Información del producto</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-4 col-md-3">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="codprod" placeholder="Código de producto"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['codprod'] . '"';
                                            } ?>>
                                        <label for="codprod">Código de producto</label>
                                        <input type="text" class="form-control" id="idprod" hidden readonly <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['idprod'] . '"';
                                                                                                            } ?>>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-8 col-md-6">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="nameprod" placeholder="Nombre" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['nomprod'] . '"';
                                            } ?>>
                                        <label for="nameprod">Nombre</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12 col-md-3">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="tasaprod" placeholder="% Interes anual" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['interesprod'] . '"';
                                            } ?>>
                                        <label for="tasaprod">% Interes</label>
                                    </div>
                                </div>
                            </div>
                            <!-- cargo, nombre agencia y codagencia  -->
                            <div class="row">
                                <div class="col-12 col-sm-8 col-md-6">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="descprod" placeholder="Descripción" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['descprod'] . '"';
                                            } ?>>
                                        <label for="descprod">Descripción</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4 col-md-3">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="maxprod" placeholder="Monto máximo" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['montoprod'] . '"';
                                            } ?>>
                                        <label for="maxprod">Monto máximo</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12 col-md-3">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="fuenteprod" placeholder="Fuente de fondo"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['nomfondo'] . '"';
                                            } ?>>
                                        <label for="fuenteprod">Fuente de fondo</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- datos adicionales -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Datos complementarios</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="form-floating mb-3 mt-2">
                                        <input type="text" class="form-control" id="analista" placeholder="Nombre del analista"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['nombreanalista'] . '"';
                                            } ?>>
                                        <label for="analista">Nombre del analista</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Monto aprobado y solicitado -->
                            <div class="row">
                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="montosol" placeholder="Monto solicitado"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['montosol'] . '"';
                                            } ?>>
                                        <label for="montosol">Monto solicitado</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input min="0" type="number" class="form-control" id="montosug" placeholder="Monto aprobado"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['montosug'] . '"';
                                            } ?>>
                                        <label for="montosug">Monto aprobado</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="number" min="1" class="form-control" id="cuota" placeholder="No de cuotas"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['cuotas'] . '"';
                                            } ?>>
                                        <label for="cuota">No. de cuotas</label>
                                    </div>
                                </div>
                            </div>
                            <!-- tipo de credito, tipo periodo, fecha primer cuota -->
                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="tipocred" placeholder="Tipo de crédito" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['nomtipocred'] . '"';
                                            } ?>>
                                        <label for="tipocred">Tipo de crédito</label>
                                    </div>

                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="peri" placeholder="Tipo de periodo" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['nomperiodo'] . '"';
                                            } ?>>
                                        <label for="peri">Tipo de periodo</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="dictamen" placeholder="No. Dictamen" readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['dictamen'] . '"';
                                            } ?>>
                                        <label for="dictamen">No. Dictamen</label>
                                    </div>
                                </div>

                            </div>
                            <!-- cuota, desembolso y dictamen -->
                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="fecdesembolso" placeholder="Fecha de desembolso"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['fecdesembolso'] . '"';
                                            } else {
                                                echo 'value="' . date('Y-m-d') . '"';
                                            } ?>>
                                        <label for="fecdesembolso">Fecha de desembolso</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="primerpago" placeholder="Fecha primer pago"
                                            readonly
                                            <?php if ($bandera) {
                                                echo 'value="' . $datos[0]['primerpago'] . '"';
                                            } else {
                                                echo 'value="' . date('Y-m-d') . '"';
                                            } ?>>
                                        <label for="primerpago">Fecha primer pago</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="tipcontrato">
                                            <option value="C" selected>Contrato individual</option>
                                        </select>
                                        <label for="tipcontrato">Tipo de crédito</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCION DE GARANTIAS -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Garantías del cliente</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col mb-2">
                                    <div class="table-responsive">
                                        <table class="table mb-0" style="font-size: 0.8rem !important;">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Tipo Garantia</th>
                                                    <th scope="col">Tipo Doc.</th>
                                                    <th scope="col">Descripción</th>
                                                    <th scope="col">Dirección</th>
                                                    <th scope="col">Valor gravamen</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider">
                                                <?php if ($bandera_garantias) {
                                                    for ($i = 0; $i < count($datosgarantias); $i++) { ?>
                                                        <tr>
                                                            <td scope="row"><?= ($datosgarantias[$i]["nomtipgar"]) ?></td>
                                                            <td><?= ($datosgarantias[$i]["nomtipdoc"]) ?></td>
                                                            <!-- VALIDAR SI ES UN GARANTIA NORMAL O ES UN FIADOR -->
                                                            <?php if ($datosgarantias[$i]["idtipgar"] == 1 && ($datosgarantias[$i]["idtipdoc"] == 1 || $datosgarantias[$i]["idtipdoc"] == 17)) { ?>
                                                                <td><?= ($datosgarantias[$i]["nomcli"]) ?></td>
                                                                <td><?= ($datosgarantias[$i]["direccioncli"]) ?></td>
                                                            <?php } else { ?>
                                                                <td><?= ($datosgarantias[$i]["descripcion"]) ?></td>
                                                                <td><?= ($datosgarantias[$i]["direccion"]) ?></td>
                                                            <?php } ?>
                                                            <td><?= ($datosgarantias[$i]["montogravamen"]) ?></td>
                                                        </tr>
                                                <?php }
                                                } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ALERTA PARA BUSCAR UN CLIENTE  -->
                        <?php if (!$bandera) { ?>
                            <div class="alert alert-warning" role="alert" style="margin-bottom: 0px !important;">
                                <h4 class="alert-heading">IMPORTANTE!</h4>
                                <div class="col-md-4 mb-3">
                                    <div class="ag-courses_item card-yellow">
                                        <a class="ag-courses-item_link" href="#">
                                            <div class="ag-courses-item_bg"></div>
                                            <div class="ag-courses-item_title">Debe seleccionar un cliente</div>
                                            <span class="ag-courses-item_date">a analizar </span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php  } ?>
                    </div>

                    <div class="container" style="max-width: 100% !important;">
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                                <!-- Boton de rechazo -->
                                <?php if ($bandera) { ?>
                                    <button class="btn btn-outline-success mt-2"
                                        onclick="obtiene([`ccodcta`,`codcli`,`nomcli`,`idprod`,`codprod`],[`tipcontrato`],[],`create_aprobacion`,`0`,['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $datos[0]['ciclo']; ?>'])"><i
                                            class="fa-solid fa-floppy-disk me-2"></i>Confirmar aprobar crédito</button>


                                    <button type="button" class="btn btn-outline-danger mt-2"
                                        onclick="abrir_modal_cualquiera_con_valor('#modal_cancelar_credito', '#id_hidden', `<?= $datos[0]['ccodcta']; ?>,<?= $datos[0]['codcli']; ?>`,[`#credito`,`#nombre`])"><i
                                            class="fa-solid fa-sack-xmark me-2"></i>Rechazar Crédito</button>
                                <?php } ?>
                                <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                                    <i class="fa-solid fa-circle-xmark"></i> Salir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                include_once "../../../src/cris_modales/mdls_aprobacion_01.php";
                include_once "../../../src/cris_modales/mdls_cancelar_credito.php";
                ?>
            <?php
        }
        break;
    case 'desembolso_individual': {

            $extra = $_POST["xtra"];

            $query = "SELECT cm.CCODCTA AS ccodcta, cl.short_name AS nomcli, cm.CodCli AS codcli, cm.CODAgencia AS codagencia, pd.cod_producto AS codproducto, cm.MonSug AS monto,
                (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo, cm.Cestado AS estado, cl.url_img AS urlfoto
                FROM cremcre_meta cm
                INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
                INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.Cestado='E' AND cm.TipoEnti='INDI' AND cm.CCODCTA=? LIMIT 1";

            $showmensaje = false;
            try {

                if ($extra == "0") {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta.");
                }

                $database->openConnection();
                $datos = $database->getAllResults($query, [$extra]);
                if (empty($datos)) {
                    $showmensaje = true;
                    throw new Exception("Código de cuenta inexistente o no encontrado.");
                }

                $bancos = $database->selectColumns("tb_bancos", ["id", "nombre"], "estado='1'");

                $rst_tipCu = $database->getAllResults("SELECT cpg.id AS pro_gas, cp.cod_producto, cp.nombre, ct.nombre_gasto, ct.afecta_modulo  
                         FROM cremcre_meta cm 
                         INNER JOIN cre_productos cp ON cp.id = cm.CCODPRD 
                         INNER JOIN cre_productos_gastos cpg ON cpg.id_producto = cp.id 
                         INNER JOIN cre_tipogastos ct ON ct.id = cpg.id_tipo_deGasto 
                         WHERE cp.cod_producto = {$datos[0]['codproducto']} AND cm.CCODCTA = ?
                         AND ct.afecta_modulo IN (1,2) AND cpg.tipo_deCobro=2 AND cpg.estado=1", [$extra]);

                $aho_vin = (empty($rst_tipCu)) ? 0 : count($rst_tipCu);

                $datosgarantias = $database->getAllResults("SELECT cl.idcod_cliente AS codcli, gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc,
                gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
                IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND tipc.idDoc=1),'x') AS nomcli,
                IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND tipc.idDoc=1),'x') AS direccioncli,
                IFNULL((SELECT '1' AS marcado FROM tb_garantias_creditos tgc WHERE tgc.id_cremcre_meta=? AND tgc.id_garantia=gr.idGarantia LIMIT 1),0) AS marcado,
                IFNULL((SELECT SUM(cli.montoGravamen) AS totalgravamen FROM tb_garantias_creditos tgc INNER JOIN cli_garantia cli ON cli.idGarantia=tgc.id_garantia WHERE tgc.id_cremcre_meta=? AND cli.estado=1),0) AS totalgravamen
                FROM tb_cliente cl
                INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
                INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
                WHERE cl.estado='1' AND gr.estado=1 AND cl.idcod_cliente= ?", [$extra, $extra, $datos[0]['codcli']]);

                $dataRefinance = $database->getAllResults("SELECT cg.id, tipg.nombre_gasto, cm.DFecDsbls fecdes, tipg.id_nomenclatura
                    FROM cremcre_meta cm
                        INNER JOIN cre_productos_gastos cg ON cm.CCODPRD=cg.id_producto
                        INNER JOIN cre_tipogastos tipg ON tipg.id=cg.id_tipo_deGasto
                        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                        WHERE cm.CCODCTA=? AND tipo_deCobro=1 AND cg.estado=1 AND tipg.afecta_modulo=3", [$extra]);

                $tempDataCreditsActive = $database->getAllResults("SELECT CCODCTA,NCapDes,DfecPago fecpago,NIntApro intapro,IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG='P' AND CESTADO!='X'),0) pagadokp,
                    IFNULL((SELECT SUM(nintpag) FROM Cre_ppg WHERE ccodcta=cm.CCODCTA),0) intpen,
                    IFNULL((SELECT MAX(dfecven) from Cre_ppg where cestado='P' AND ccodcta=cm.CCODCTA), '-') fecult
                    FROM cremcre_meta cm WHERE CodCli IN (SELECT Codcli FROM cremcre_meta WHERE CCODCTA=?)
                    AND CCODCTA!=? AND Cestado='F' AND TipoEnti='INDI'", [$extra, $extra]);

                $calculointeres = true;
                $j = 0;
                foreach ($tempDataCreditsActive as $dataCreditActive) {
                    $account = $dataCreditActive['CCODCTA'];
                    $pagadokp = $dataCreditActive['pagadokp'];
                    $capdes = $dataCreditActive['NCapDes'];
                    $intpen = $dataCreditActive['intpen'];
                    $fecpago = $dataCreditActive['fecpago'];
                    $fecult = $dataCreditActive['fecult'];
                    if ($dataCreditActive['fecult'] == "-" && isset($dataRefinance[0]['fecdes']) && !empty($dataRefinance[0]['fecdes'])) {
                        $fecult = $dataRefinance[0]['fecdes'];
                    } else {
                        $fecult = $dataCreditActive['fecult'];
                    }
                    $intapro = $dataCreditActive['intapro'];
                    $saldo = round($capdes - $pagadokp, 2);
                    $diasdif = dias_dif($fecult, $hoy);
                    if ($calculointeres) {
                        $intpen = $saldo * $intapro / 100 / 360 * $diasdif;
                    }
                    $intpen = round($intpen, 2);
                    $intpen = ($intpen < 0) ? 0 : $intpen;

                    $dataCreditsActive[] = [
                        'ccodcta'  => $account,
                        'saldokp'  => $saldo,
                        'interes'  => $intpen,
                        'id'       => $j + 1
                    ];
                    $j++;
                }

                $status = true;
            } catch (Exception $e) {
                if (!$showmensaje) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                }
                $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
                $status = false;
            } finally {
                $database->closeConnection();
            }
            ?>
                <input type="text" id="file" value="cre_indi_01" style="display: none;">
                <input type="text" id="condi" value="desembolso_individual" style="display: none;">
                <div class="text" style="text-align:center">DESEMBOLSO DE CRÉDITO INDIVIDUAL</div>
                <div class="card">
                    <div class="card-header">Desembolso de crédito individual <?= $extra ?> </div>
                    <div class="card-body">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>¡!</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Información de cliente y codigo de crédito</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-2 col-sm-6 col-md-4 mt-2">
                                    <div id="contenedorVista" class="mt-2 text-center">
                                        <?php
                                        if (isset($datos) && !empty($datos[0]['urlfoto'])) {
                                            $fileProcessor = new FileProcessor(__DIR__ . '/../../../../');
                                            $relativePath = $datos[0]['urlfoto'];

                                            if ($fileProcessor->fileExists($relativePath)) {
                                                $fileInfo = $fileProcessor->getFileInfo($relativePath);
                                                $src = $fileInfo['data_uri'];
                                                $fileName = $fileInfo['filename'];

                                                echo $fileProcessor->getPreviewHtml($datos[0]['urlfoto'], [
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
                                <div class="col-12 col-sm-12 col-md-10">
                                    <!-- usuario y boton buscar -->
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <div class="form-floating mb-2 mt-2">
                                                <input type="text" class="form-control" id="nomcli" placeholder="Nombre de cliente"
                                                    readonly value="<?= $datos[0]['nomcli'] ?? '' ?>">
                                                <input type="text" name="" id="id_cod_cliente" hidden value="<?= $datos[0]['codcli'] ?? '' ?>">
                                                <label for="cliente">Nombre cliente</label>
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                                                onclick="abrir_modal('#modal_creditos_a_desembolsar', '#id_modal_hidden', 'id_cod_cliente,nomcli,codagencia,codproducto,codcredito,ccapital/A,A,A,A,A,A/'+'/tipo_desembolso/#/#/mensaje')"><i
                                                    class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito a
                                                desembolsar</button>
                                        </div>
                                    </div>
                                    <!-- cargo, nombre agencia y codagencia  -->
                                    <div class="row">
                                        <div class="col-12 col-sm-12 col-md-4">
                                            <div class="form-floating mb-3 mt-2">
                                                <input type="text" class="form-control" id="codproducto"
                                                    placeholder="Código de producto" readonly
                                                    value="<?= $datos[0]['codproducto'] ?? '' ?>">
                                                <label for="cargo">Codigo de producto</label>
                                            </div>
                                        </div>

                                        <!-- estado y ciclo -->
                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="form-floating mb-3 mt-2">
                                                <input type="text" class="form-control" id="estado" placeholder="Estado" readonly
                                                    value="<?= (($datos[0]['estado'] ?? 'X') == 'E') ? 'APROBADO' : '' ?>">
                                                <label for="estado">Estado</label>
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6 col-md-4">
                                            <div class="form-floating mb-3 mt-2">
                                                <input type="text" class="form-control" id="ciclo" placeholder="Ciclo" readonly
                                                    value="<?= $datos[0]['ciclo'] ?? '' ?>">
                                                <label for="ciclo">Ciclo</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="codagencia" placeholder="Código de agencia"
                                            readonly value="<?= $datos[0]['codagencia'] ?? '' ?>">
                                        <label for="codagencia">Agencia</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="codcredito" placeholder="Codigo de crédito"
                                            readonly
                                            value="<?= $extra ?? '' ?>">
                                        <label for="codcredito">Código de crédito</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-12 col-md-5">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="ccapital" placeholder="Capital" readonly
                                            value="<?= $datos[0]['monto'] ?? '' ?>">
                                        <label for="ccapital">Capital</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="gastos" placeholder="Gastos" readonly>
                                        <label for="gastos">Gastos</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="desembolsar" placeholder="Total a desembolsar"
                                            readonly>
                                        <label for="desembolsar">Total a desembolsar</label>
                                    </div>
                                </div>
                            </div>

                            <h2 class="accordion-header">
                                <div class="row">
                                    <div class="col-12">
                                        <button id="bt1" class="accordion-button collapsed loco" data-bs-toggle="collapse"
                                            data-bs-target="#data1" aria-expanded="false" aria-controls="data1">
                                            <div class="row center">
                                                <i class="fa-solid fa-arrow-turn-down">
                                                    <a>Visualzar Garantias</a> </i>
                                                <br>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </h2>

                            <div id="data1" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                <div class="accordion-body">
                                    <div class="row mb-3" style="font-size: 0.90rem;">
                                        <!-- SECCION DE GARANTIAS -->
                                        <div class="container contenedort" style="max-width: 100% !important;">
                                            <div class="row">
                                                <div class="col">
                                                    <div class="text-center mb-2"> <b> Garantías del cliente </b> </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col mb-2">
                                                    <div class="table-responsive">
                                                        <table class="table mb-0" style="font-size: 0.8rem !important;">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Tipo Garantia</th>
                                                                    <th scope="col">Tipo Doc.</th>
                                                                    <th scope="col">Descripción</th>
                                                                    <th scope="col">Dirección</th>
                                                                    <th scope="col">Valor gravamen</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="table-group-divider">
                                                                <!-- GARANTIAS NEGROY  -->
                                                                <?php

                                                                if (isset($datosgarantias) && !empty($datosgarantias)) {
                                                                    foreach ($datosgarantias as $key => $value) {
                                                                        if ($value['marcado'] == 1) {
                                                                            $numero_formateado = Moneda::formato($value['montogravamen']);
                                                                            echo "<tr>";
                                                                            echo "<td>" . $value['nomtipgar'] . "</td>";
                                                                            echo "<td>" . $value['nomtipdoc'] . "</td> ";
                                                                            if ($value["idtipgar"] == 1 && $value["idtipdoc"] == 1) {
                                                                                echo "<td>" . $value['nomcli'] . "</td>";
                                                                                echo "<td>" . $value['direccioncli'] . "</td>";
                                                                            } else {
                                                                                echo "<td>" . $value['descripcion'] . "</td>";
                                                                                echo "<td>" . $value['direccion'] . "</td>";
                                                                            }
                                                                            echo "<td>Q " . $numero_formateado . "</td>";
                                                                            echo "</tr>";
                                                                        }
                                                                    }
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- DIV PARA VISUALIZAR LAS GARANTIAS  -->
                        </div>

                        <!-- INI ********************************************************************************************************************* slc-->
                        <div class="container contenedort mt-2" style="max-width: 100% !important;" id="aho_vin">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <h5>Seleccionar un tipo de ahorro vinculado o lo puede omitir</h5>
                                        </div>
                                        <div class="col">
                                            <button type="button" class="btn btn-outline-danger" id="ar_ahoVin"
                                                onclick="omitir_aho_vin()">Omitir</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <table class="table table-success table-striped">
                                            <thead class="table-success">
                                                <th scope="row">id</th>
                                                <th scope="row">Código de producto</th>
                                                <th scope="row">Nombre de producto</th>
                                                <th scope="row">Nombre de gasto</th>
                                                <th scope="row">Cuenta afectada</th>
                                                <th scope="row">Check</th>

                                            </thead>
                                            <tbody>
                                                <?php
                                                if (isset($rst_tipCu) && !empty($rst_tipCu)) {
                                                    foreach ($rst_tipCu as $row) {
                                                ?>
                                                        <tr style="cursor: pointer;" id="<?= $row['pro_gas'] ?>">
                                                            <td><?= $row['pro_gas'] ?></td>
                                                            <td><?= $row['cod_producto'] ?></td>
                                                            <td><?= $row['nombre'] ?></td>
                                                            <td><?= $row['nombre_gasto'] ?></td>
                                                            <td><?= ($row['afecta_modulo'] == 1) ? "Cuenta de Ahorro" : "Cuenta de Aportación" ?>
                                                            </td>
                                                            <td>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="radio" name="data_tipcu"
                                                                        value="<?= $row['pro_gas'] ?>" id="<?= $row['pro_gas'] ?>"
                                                                        onclick="bus_ahoVin('<?= (isset($datos[0]['codcli'])) ? $datos[0]['codcli'] : '' ?>')">
                                                                    <label class="form-check-label" for="<?= $row['pro_gas'] ?>">
                                                                    </label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                <?php
                                                    }
                                                }

                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div id="tip_cu"></div>
                            </div>
                        </div>

                        <!-- FIN *** -->

                        <!-- SECCION DE REFINANCIAMIENTO -->
                        <?php if (isset($dataCreditsActive) && !empty($dataCreditsActive) && isset($dataRefinance) && !empty($dataRefinance)) : ?>
                            <div class="container contenedort" style="max-width: 100% !important;">
                                <div class="row">
                                    <div class="col">
                                        <div class="text-center mb-2 mt-1"><b>Refinanciamiento</b></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col mb-2">
                                        <div class="table-responsive">
                                            <table id="table-refinance" class="table mb-0" style="font-size: 0.8rem !important;">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Crédito activo</th>
                                                        <th scope="col">Saldo capital</th>
                                                        <th scope="col">Interés</th>
                                                        <th scope="col">Gasto</th>
                                                        <th scope="col">Marcar</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="table-group-divider">
                                                    <?php foreach ($dataCreditsActive as $dataCreditActive) { ?>
                                                        <tr>
                                                            <td scope="row" class="align-middle"><?= $dataCreditActive['ccodcta'] ?></td>
                                                            <td class="align-middle"><?= $dataCreditActive['saldokp'] ?></td>
                                                            <td>
                                                                <input type="number" onblur="calculateExpense()" name="interes_1" class="form-control" step="0.01" min="0" value="<?= $dataCreditActive['interes'] ?>" style="font-size: 0.8rem;">
                                                            </td>
                                                            <td>
                                                                <select name="gasto_1" class="form-select" style="font-size: 0.8rem;">
                                                                    <?php foreach ($dataRefinance as $key => $refinance) { ?>
                                                                        <option value="<?= $refinance['id'] ?>" data-idextra="<?= $refinance['id_nomenclatura'] ?>"><?= $refinance['nombre_gasto'] ?></option>

                                                                    <?php } ?>
                                                                </select>
                                                            </td>
                                                            <td class="align-middle">
                                                                <input class="form-check-input ms-3 S" type="checkbox" onchange="calculateExpense()" value="<?= $dataCreditActive['id'] ?>" id="R_<?= $dataCreditActive['id'] ?>">
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col-12 mt-2 mb-1">
                                    <div class="table-responsive">
                                        <table id="tabla_gastos_desembolso" class="table" style="max-width: 100% !important;">
                                            <thead>
                                                <tr>
                                                    <th scope="col">#</th>
                                                    <th></th>
                                                    <th scope="col">Descripción de gasto</th>
                                                    <th scope="col">Monto</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- select para la parte del tipo de desembolso -->
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col-sm-8 mb-3 mt-2">
                                    <div class="form-floating">
                                        <select class="form-select" id="tipo_desembolso" aria-label="Tipo de desembolso"
                                            onchange="ocultar_div_desembolso(this.value)" <?= ($status) ? ' ' : 'disabled' ?>>
                                            <option selected value="1">Efectivo</option>
                                            <option value="2">Cheque</option>
                                            <option value="3">Transferencia</option>
                                        </select>
                                        <label for="tip_doc">Tipo de desembolso</label>
                                    </div>

                                </div>
                                <div class="col-sm-4 mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="numdoc" placeholder="Numero de documento">
                                        <label for="numdoc">No. documento</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="container contenedort" id="region_cheque" style="display: none; max-width: 100% !important;">
                            <div class="row">
                                <div class="col-sm-4 mt-2">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="cantidad" step="0.01" placeholder="Cantidad"
                                            disabled>
                                        <label for="cantidad">Cantidad</label>
                                    </div>
                                </div>
                                <div class="col-sm-4 mt-2">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="negociable">
                                            <option value="0">No Negociable</option>
                                            <option value="1">Negociable</option>
                                        </select>
                                        <label for="negociable">Tipo cheque</label>
                                    </div>
                                </div>
                                <div class="col-sm-4 mb-3 mt-2">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="numcheque" placeholder="Numero de cheque">
                                        <label for="numcheque">No. de Cheque</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <div class="form-floating">
                                        <input disabled type="text" class="form-control" id="paguese"
                                            placeholder="Paguese a la orden de">
                                        <label for="paguese">Paguese a la orden de</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <div class="form-floating">
                                        <input disabled type="text" class="form-control" id="numletras"
                                            placeholder="La suma de (Q)">
                                        <label for="numletras">La suma de (Q)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                            <option value="" disabled selected>Seleccione un banco</option>
                                            <?php
                                            foreach (($bancos ?? []) as $banco) {
                                                echo '<option  value="' . $banco['id'] . '">' . $banco['id'] . " - " . $banco['nombre'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <label for="bancoid">Banco</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="cuentaid">
                                            <option value="">Seleccione una cuenta</option>
                                        </select>
                                        <label for="cuentaid">No. de Cuenta</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- REGION DE TRANSFERENCIA -->
                        <div class="container contenedort" id="region_transferencia"
                            style="display: none; max-width: 100% !important;">
                            <div class="row">
                                <div class="col-sm-12  mt-2">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="cuentaaho">
                                            <option value="">Seleccione una cuenta de ahorro</option>
                                        </select>
                                        <label for="cuentaaho">Cuenta de ahorro</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="container contenedort" style="max-width: 100% !important;">
                            <!-- input de glosa -->
                            <div class="row">
                                <div class="col-sm-12 mb-1 mt-2">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="glosa" style="height: 100px" rows="1"
                                            placeholder="Concepto" <?= ($status) ? ' ' : 'disabled' ?>>  </textarea>
                                        <label for="glosa">Concepto</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!$status) { ?>
                            <div class="alert alert-success" role="alert" style="margin-bottom: 0px !important;" id="mensaje">
                                <h4 class="alert-heading">IMPORTANTE!</h4>
                                <div class="col-md-4 mb-3">
                                    <div class="ag-courses_item card-green">
                                        <a class="ag-courses-item_link">
                                            <div class="ag-courses-item_bg"></div>
                                            <div class="ag-courses-item_title">Debe seleccionar un cliente.</div>
                                            <div class="ag-courses-item_date-box">

                                                <span class="ag-courses-item_date"> Para realizar un desembolso </span>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                    <div class="container" style="max-width: 100% !important;">
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                                <button id="bt_desembolsar" class="btn btn-outline-success"
                                    onclick="if(val_aho_vin()==false)return; savedesem('<?= $codusu; ?>','<?= $id_agencia; ?>')"><i
                                        class="fa-solid fa-money-bill"></i> Desembolsar</button>
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
                        (<?= isset($aho_vin) ? $aho_vin : 0 ?> > 0) ? ac_even('aho_vin', 'vista', 1): ac_even('aho_vin',
                            'vista',
                            0);
                        idPro_gas = <?= isset($aho_vin) ? $aho_vin : 0 ?>;
                        afec = 0;
                        ahorro = 0;
                    });
                </script>
                <?php
                include_once "../../../src/cris_modales/mdls_desembolso_indi.php";
                ?>
                <script>
                    <?php
                    if ($status) {
                        echo 'mostrar_tabla_gastos(`' . $datos[0]['ccodcta'] . '`);';
                        echo 'consultar_gastos_monto(`' . $datos[0]['ccodcta'] . '`);';
                        echo 'concepto_default(`' . $datos[0]['nomcli'] . '`, `0`);'; ?>
                        $(`#bt_desembolsar`).show();
                    <?php } else { ?>
                        $('#bt_desembolsar').hide();
                    <?php } ?>

                    function setmonto(id, saldokp = 0, intpen = 0) {
                        saldokp = parseFloat(saldokp);
                        intpen = parseFloat(intpen);
                        $("#" + id).val((saldokp + intpen).toFixed(2));
                    }

                    function handleSelectChange(id, select) {
                        var selectedOption = select.options[select.selectedIndex];
                        var account = selectedOption.value;
                        var saldo = parseFloat(selectedOption.dataset.saldo);
                        var intpen = parseFloat(selectedOption.dataset.intpen);
                        setmonto(id, saldo, intpen);
                    }
                </script>
            <?php
        }
        break;

    /*--------------------------------------------------------------------------------- */
    case 'statusaccount':
        $extra = $_POST["xtra"];

        $query = "SELECT cl.short_name AS nombrecli, cl.idcod_cliente AS codcli, cm.CCODCTA AS ccodcta, cm.MonSug AS monsug, 
                        cm.NIntApro AS interes, cm.DFecDsbls AS fecdesembolso,
                        ((cm.MonSug)-(SELECT IFNULL(SUM(ck.KP),0) FROM CREDKAR ck WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA=cm.CCODCTA)) AS saldocap
                    FROM cremcre_meta cm
                    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                    WHERE cm.CCODCTA=?";

        $showmensaje = false;
        try {

            if ($extra == "0") {
                $showmensaje = true;
                throw new Exception("Seleccione un código de cuenta.");
            }

            $database->openConnection();
            $datos = $database->getAllResults($query, [$extra]);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("Código de cuenta inexistente o no encontrado.");
            }

            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
            ?>
            <input type="text" readonly hidden value='statusaccount' id='condi'>
            <input type="text" hidden value="cre_indi_01" id="file">
            <div class="card crdbody contenedort shadow-lg border-0 rounded-4">
                <div class="card-header bg-primary text-white rounded-top-4" style="text-align:left">
                    <h4 class="mb-0"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Estado de cuenta Individual</h4>
                </div>
                <div class="card-body bg-light rounded-bottom-4">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>¡!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="row contenedort mb-4">
                        <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-user me-2"></i>Detalle del cliente</h5>
                        <div class="row mb-3 align-items-end">
                            <div class="col-sm-7 mb-2 mb-sm-0">
                                <label class="form-label fw-semibold">Nombre Cliente</label>
                                <input type="text" class="form-control form-control-lg border-0 bg-white shadow-sm" id="name"
                                    value="<?= $datos[0]["nombrecli"] ?? ''; ?>" readonly>
                            </div>
                            <div class="col-sm-5">
                                <button type="button" class="btn btn-primary btn-lg w-100"
                                    onclick="abrir_modal('#modal_estado_cuenta', '#id_modal_hidden', 'name/A/'+'/#/#/#/#')">
                                    <i class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito
                                </button>
                            </div>
                        </div>
                        <div class="row mb-3 align-items-center">
                            <div class="col-sm-6 mb-2 mb-sm-0">
                                <label class="form-label fw-semibold">Código de Cuenta</label>
                                <?php if ($status): ?>
                                    <span class="badge rounded-pill bg-success fs-6 px-4 py-2 shadow-sm"><?= $datos[0]["ccodcta"] ?? ''; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Código de Cliente</label>
                                <?php if ($status): ?>
                                    <span class="badge rounded-pill bg-info text-dark fs-6 px-4 py-2 shadow-sm"><?= $datos[0]["codcli"] ?? ''; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center mb-4">
                    <div class="col-auto" id="modal_footer">
                        <?php
                        if ($status) {
                            echo '<button type="button" class="btn btn-outline-danger me-2 mb-2" onclick="reportes([[],[],[],[`' . $datos[0]["ccodcta"] . '`]], `pdf`, 34,0,1)">
                                <i class="fa-regular fa-file-pdf"></i> Estado de Cuenta
                            </button>';
                        }
                        ?>
                        <button type="button" class="btn btn-outline-secondary me-2 mb-2"
                            onclick="printdiv('PagGrupAutom', '#cuadro', 'caja_cre', 0)">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning mb-2" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
            <?php
            include_once "../../../src/cris_modales/mdls_estadocuenta.php";
            break;
        case 'simulador': {
                $extra = $_POST["xtra"];

                $selectColumns = ['id', 'nombre', 'descripcion', 'monto_maximo', 'tasa_interes', 'dias_calculo', 'id_tipo_periodo', 'diasCorridos'];

                $showmensaje = false;
                try {
                    $database->openConnection();
                    $productos = $database->selectColumns('cre_productos', $selectColumns, "estado=1");
                    if (empty($productos)) {
                        $showmensaje = true;
                        throw new Exception("No se encontraron productos activos");
                    }

                    $status = 1;
                } catch (Exception $e) {
                    if (!$showmensaje) {
                        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                    }
                    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
                    $status = 0;
                } finally {
                    $database->closeConnection();
                }
                $paginas = ceil(count($productos) / 8);
            ?>
                <style>
                    .card.selected {
                        border: 4px solid #007bff;
                        box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
                    }
                </style>
                <input type="text" id="file" value="cre_indi_01" style="display: none;">
                <input type="text" id="condi" value="analisis_individual" style="display: none;">
                <div class="text" style="text-align:center">SIMULADOR DE CRÉDITO INDIVIDUAL</div>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Simulador de crédito individual</span>
                        <button class="btn btn-info" onclick="help();">
                            <i class="fa-solid fa-info-circle me-2"></i>Ayuda
                        </button>
                    </div>
                    <div class="card-body" style="padding-bottom: 0px !important;">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>¡Error!</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>

                        <div class="container contenedort" style="max-width: 100% !important;">
                            <h3>PRODUCTOS DISPONIBLES</h3>
                            <div id="productos-container" class="gridtarjetas mb-3" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); min-height: 510px; font-size: 0.8rem;">
                            </div>
                            <nav aria-label="Page navigation example">
                                <ul class="pagination" id="pagination-container">
                                </ul>
                            </nav>
                        </div>
                        <div class="container contenedort mb-3" style="max-width: 100% !important;">
                            <h3>¿Cómo se pagarán los intereses?</h3>
                            <div class="row" id="tipoAmortizacion-container">
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="card cardamort" style="width: 100%;height: 100%;cursor:pointer;" data-value="Franc">
                                        <img src="../../includes/img/frances.png" class="card-img-top" alt="TablaFrancesa">
                                        <div class="card-body" style="font-size: 0.8rem;">
                                            <h6 class="card-title">Sobre Saldo (Francés)</h6>
                                            <div class="collapse" id="cardDescription-1">
                                                <p class="card-text">La tabla de amortización del sistema francés, con cuotas constantes, calcula los intereses sobre el capital restante, manteniéndolos fijos. La amortización del capital es creciente, mientras que los intereses disminuyen a medida que avanza el tiempo.</p>
                                            </div>
                                            <a class="btn btn-link" data-bs-toggle="collapse" href="#cardDescription-1" role="button" aria-expanded="false" aria-controls="cardDescription-1" onclick="event.stopPropagation();">
                                                Mostrar más
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="card cardamort" style="width: 100%;height: 100%;cursor:pointer;" data-value="Flat">
                                        <img src="../../includes/img/flat.png" class="card-img-top" alt="TablaFlat">
                                        <div class="card-body" style="font-size: 0.8rem;">
                                            <h6 class="card-title">Nivelado (Flat)</h6>
                                            <div class="collapse" id="cardDescription-2">
                                                <p class="card-text">El sistema de amortización nivelado o flat utiliza cuotas constantes, donde los intereses se calculan sobre el total de la deuda y se mantienen fijos. El capital a amortizar es igual en cada periodo, calculado dividiendo la deuda por el número de periodos establecidos.</p>
                                            </div>
                                            <a class="btn btn-link" data-bs-toggle="collapse" href="#cardDescription-2" role="button" aria-expanded="false" aria-controls="cardDescription-2" onclick="event.stopPropagation();">
                                                Mostrar más
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="card cardamort" style="width: 100%;height: 100%;cursor:pointer;" data-value="Germa">
                                        <img src="../../includes/img/aleman.png" class="card-img-top" alt="TablaAlemana">
                                        <div class="card-body" style="font-size: 0.8rem;">
                                            <h6 class="card-title">Capital Fijo Interes Variable (Aleman)</h6>
                                            <div class="collapse" id="cardDescription-3">
                                                <p class="card-text">El sistema de amortización alemán utiliza cuotas decrecientes, donde el interés a pagar es variable y se calcula sobre el saldo pendiente. El capital a amortizar es fijo en cada periodo, determinado al dividir la deuda por el número total de periodos.</p>
                                            </div>
                                            <a class="btn btn-link" data-bs-toggle="collapse" href="#cardDescription-3" role="button" aria-expanded="false" aria-controls="cardDescription-3" onclick="event.stopPropagation();">
                                                Mostrar más
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-6">
                                    <div class="card cardamort" style="width: 100%;height: 100%;cursor:pointer;" data-value="Amer">
                                        <img src="../../includes/img/american.png" class="card-img-top" alt="TablaAmericana">
                                        <div class="card-body" style="font-size: 0.8rem;">
                                            <h6 class="card-title">Capital Vencimiento (Americano)</h6>
                                            <div class="collapse" id="cardDescription-4">
                                                <p class="card-text">El sistema de amortización americano con una única cuota de vencimiento se caracteriza por el pago de intereses constantes de forma mensual, sin amortización de capital durante el plazo del préstamo. La última cuota incluye el capital prestado más los intereses correspondientes al último período.</p>
                                            </div>
                                            <a class="btn btn-link" data-bs-toggle="collapse" href="#cardDescription-4" role="button" aria-expanded="false" aria-controls="cardDescription-4" onclick="event.stopPropagation();">
                                                Mostrar más
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Datos complementarios</b></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input min="0" type="number" class="form-control" id="montosug"
                                            placeholder="Monto por aprobar">
                                        <label for="montosug">Monto</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="fecdesembolso" placeholder="Fecha de desembolso" value="<?= date('Y-m-d'); ?>">
                                        <label for="fecdesembolso">Fecha de desembolso</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-3">
                                        <input type="number" min="1" class="form-control" id="cuota" placeholder="No de cuotas"
                                            value="1">
                                        <label for="cuota">No. de cuotas</label>
                                    </div>
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="primerpago" placeholder="Fecha primer pago"
                                            value="<?= date('Y-m-d'); ?>">
                                        <label for="primerpago">Fecha primer pago</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="peri" onchange="changestatus(this.value)">
                                            <option value="0" selected disabled>Seleccione un tipo de periodo</option>
                                        </select>
                                        <label for="peri">Tipo de periodo</label>
                                    </div>
                                </div>

                                <div id="divperipagcap"
                                    style="display: none"
                                    class="col-12 col-sm-6 col-md-4">
                                    <div class="row">
                                        <div class="form-floating mb-3 col-10">
                                            <select class="form-select" id="peripagcap">
                                                <option value="1">
                                                    Mensual</option>
                                                <option value="2">
                                                    Bimensual</option>
                                                <option value="3">
                                                    Trimestral</option>
                                                <option value="6">
                                                    Semestral</option>
                                                <option value="12">
                                                    Anual</option>
                                            </select>
                                            <label for="peripagcap">Pago de capital</label>
                                        </div>
                                        <div class="form-floating mb-3 col-2">
                                            <input class="form-check-input" checked type="checkbox" id="afint">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <button onclick="generarPlanPago()" class="btn btn-outline-primary" id="btnGenerarPlanPago">
                                        <i class="fa-solid fa-rectangle-list me-2"></i>Consultar plan de pago</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal fade" id="modalAmortizacion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <!-- Encabezado -->
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalLabel">Tabla de amortización</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <!-- Cuerpo -->
                            <div class="modal-body">
                                <div class="container">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <p id="productoDescripcion"><strong>Producto:</strong> </p>
                                            <p id="noPeriodo"><strong>No. Cuotas</strong> </p>

                                        </div>
                                        <div class="col-6 text-end">
                                            <p id="montoCapital"><strong>Capital:</strong> </p>
                                            <p id="tasaInteres"><strong>Tasa de interés:</strong> </p>
                                        </div>
                                    </div>

                                    <!-- Tabla de amortización -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered text-center" id="tablaAmortizacion">
                                            <thead class="table-secondary">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Fecha</th>
                                                    <th>Capital</th>
                                                    <th>Interés</th>
                                                    <th>Otros</th>
                                                    <th>Cuota</th>
                                                    <th>Saldo</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-warning" id="downloadTable"> Descargar Tabla</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    $(document).ready(function() {
                        const cards = document.querySelectorAll('.cardamort');
                        cards.forEach(card => {
                            card.addEventListener('click', () => {
                                cards.forEach(c => c.classList.remove('selected'));
                                card.classList.add('selected');
                                const selectedValue = card.getAttribute('data-value');
                                printdiv("prdscre", "#peri", 'cre_indi_01', selectedValue);
                            });
                        });

                        //++++++++++++++
                        const productos = <?= json_encode($productos) ?>;
                        const productosContainer = document.getElementById('productos-container');
                        const paginationContainer = document.getElementById('pagination-container');
                        const perPage = 8;

                        function renderProducts(productos) {
                            const limitDescription = 110;
                            productosContainer.innerHTML = productos.map(producto => {
                                const shortDescription = producto.descripcion.length > limitDescription ?
                                    producto.descripcion.substring(0, limitDescription) :
                                    producto.descripcion;
                                const remainingDescription = producto.descripcion.length > limitDescription ?
                                    producto.descripcion.substring(limitDescription) :
                                    '';

                                return `
                                    <div style="cursor:pointer; width: 230px;height:250px;" name="targets" 
                                            class="tarjeta" onclick="setActiveTarjeta(this)" 
                                            data-value="${producto.id}" data-tasa="${producto.tasa_interes}" data-nombreProducto="${producto.nombre}">
                                        <div class="titulo">${producto.nombre}</div>
                                        <div class="cuerpo">
                                            <i class="fa-solid fa-money-bill"></i>
                                            
                                            <p style="display:inline;">
                                                ${shortDescription}
                                                ${remainingDescription ? `
                                                    <span class="collapse" id="tarjetaDescription-${producto.id}">
                                                        ${remainingDescription}
                                                    </span>
                                                    <a class="btn btn-link" data-bs-toggle="collapse" 
                                                        href="#tarjetaDescription-${producto.id}" 
                                                        role="button" aria-expanded="false" 
                                                        aria-controls="tarjetaDescription-${producto.id}" 
                                                        onclick="event.stopPropagation();">
                                                        Mostrar más
                                                    </a>
                                                ` : ''}
                                            </p>
                                        </div>
                                        <div class="pie">
                                            <span>Máx: ${producto.monto_maximo}</span>
                                            <br>
                                            ${producto.tasa_interes} %
                                        </div>
                                    </div>
                                `;
                            }).join('');
                        }


                        function renderPagination(currentPage) {
                            const totalPages = <?= $paginas ?>;

                            let paginationHTML = `
                                <li class="page-item ${currentPage == 1 ? 'disabled' : ''}">
                                    <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            `;

                            for (let i = 1; i <= totalPages; i++) {
                                paginationHTML += `
                                    <li class="page-item ${currentPage == i ? 'active' : ''}">
                                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                                    </li>
                                `;
                            }

                            paginationHTML += `
                                <li class="page-item ${currentPage == totalPages ? 'disabled' : ''}">
                                    <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            `;

                            paginationContainer.innerHTML = paginationHTML;
                        }

                        function fetchProducts(page) {
                            const start = (page - 1) * perPage;
                            const end = start + perPage;
                            const paginatedProducts = productos.slice(start, end);
                            renderProducts(paginatedProducts);
                            renderPagination(page);
                        }

                        $(document).on('click', '.page-link', function(e) {
                            e.preventDefault();
                            const page = $(this).data('page');
                            if (page) {
                                fetchProducts(page);
                            }
                        });

                        fetchProducts(1);
                    });

                    function setActiveTarjeta(tarjetaSeleccionada) {
                        const tarjetas = document.querySelectorAll('.tarjeta');
                        tarjetas.forEach(tarjeta => tarjeta.classList.remove('tarjeta-activa'));
                        tarjetaSeleccionada.classList.add('tarjeta-activa');
                    }

                    function changestatus(tipperiodo) {
                        const selectedCard = document.querySelector('.cardamort.selected');
                        let tipcredito = selectedCard.getAttribute('data-value');
                        if (tipcredito == "Flat" && tipperiodo == "1M") $("#divperipagcap").show();
                        else $("#divperipagcap").hide();
                        $('#peripagcap').val('1');
                    }

                    function checkafeint() {
                        const afintChecked = document.getElementById('afint').checked;
                        return afintChecked;
                    }

                    function alerta(mensaje) {
                        iziToast.warning({
                            title: 'Advertencia',
                            message: mensaje,
                            position: 'center'
                        });
                    }

                    function generarPlanPago() {
                        //validaciones
                        if ($('#productos-container .tarjeta-activa').length == 0) {
                            alerta('Seleccione un producto');
                            return;
                        }
                        if ($('.cardamort.selected').length == 0) {
                            alerta('Seleccione un tipo de Amortizacion');
                            return;
                        }
                        if ($('#montosug').val() == '') {
                            alerta('Ingrese un monto');
                            return;
                        }
                        if ($('#cuota').val() == '') {
                            alerta('Ingrese un número de cuotas');
                            return;
                        }
                        if ($('#peri').val() == '0') {
                            alerta('Seleccione un tipo de periodo');
                            return;
                        }
                        if ($('#fecdesembolso').val() == '') {
                            alerta('Seleccione una fecha de desembolso');
                            return;
                        }
                        if ($('#primerpago').val() == '') {
                            alerta('Seleccione una fecha de primer pago');
                            return;
                        }

                        //DESTRUIR EL EVENTO EN EL BOTON DE DESCARGA
                        $('#downloadTable').off('click');

                        $("#productoDescripcion").text(`Producto: ${$('#productos-container .tarjeta-activa').data('nombreproducto')}`);
                        $("#noPeriodo").text(`No. Cuotas: ${$('#cuota').val()}`);
                        $("#tasaInteres").text(`Tasa de interés: ${$('#productos-container .tarjeta-activa').data('tasa')}%`);
                        $("#montoCapital").text(`Capital: Q${$('#montosug').val()}`);

                        if ($.fn.DataTable.isDataTable('#tablaAmortizacion')) {
                            $('#tablaAmortizacion').DataTable().destroy();
                        }

                        const modal = new bootstrap.Modal(document.getElementById('modalAmortizacion'));
                        modal.show();

                        $('#tablaAmortizacion').DataTable({
                            "aProcessing": true,
                            "aServerSide": true,
                            "ordering": false,
                            "lengthMenu": [
                                [10, 15, -1],
                                ['10 filas', '15 filas', 'Mostrar todos']
                            ],
                            "ajax": {
                                url: '../../views/Creditos/cre_indi/tablaAmortizacion.php',
                                type: "POST",
                                beforeSend: function() {
                                    loaderefect(1);
                                },
                                data: {
                                    montoCredito: $('#montosug').val(),
                                    idProducto: $('#productos-container .tarjeta-activa').data('value'),
                                    tipoAmortizacion: $('.cardamort.selected').data('value'),
                                    noPeriodo: $('#cuota').val(),
                                    tipoPeriodo: $('#peri').val(),
                                    fechaDesembolso: $('#fecdesembolso').val(),
                                    fechaPago: $('#primerpago').val(),
                                    peripagcap: $('#peripagcap').val(),
                                    afectaInteres: checkafeint()
                                },
                                dataType: "json",
                                complete: function(data) {
                                    //  console.log(data);
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

                        $('#downloadTable').on('click', function() {
                            reportes([
                                [],
                                [],
                                [],
                                [
                                    $('#montosug').val(),
                                    $('#productos-container .tarjeta-activa').data('value'),
                                    $('.cardamort.selected').data('value'),
                                    $('#cuota').val(),
                                    $('#peri').val(),
                                    $('#fecdesembolso').val(),
                                    $('#primerpago').val(),
                                    $('#peripagcap').val(),
                                    checkafeint()
                                ]
                            ], 'pdf', 35, 0, 1);
                        });
                    }

                    function help() {
                        const stepsData = [{
                                element: '#productos-container',
                                title: 'Productos disponibles o lineas de creditos',
                                description: 'Seleccione un producto para realizar la simulación, elija el que más se ajuste a sus necesidades.',
                            },
                            {
                                element: '#tipoAmortizacion-container',
                                title: 'Tipo de amortización, como se pagarán los intereses del crédito, seleccione uno',
                                description: 'La tabla de amortización variará dependiendo del tipo de amortización seleccionado.',
                            },
                            {
                                element: '#montosug',
                                title: 'Monto a solicitar',
                                description: 'Ingrese el monto que desea solicitar, este monto no debe exceder el monto máximo permitido por el producto seleccionado.',
                            },
                            {
                                element: '#fecdesembolso',
                                title: 'Fecha de desembolso',
                                description: 'Seleccione la fecha en la que desea que se realice el desembolso del crédito.',
                            },
                            {
                                element: '#cuota',
                                title: 'Número de cuotas',
                                description: 'Ingrese el número de cuotas en las que desea pagar el crédito.',
                            },
                            {
                                element: '#primerpago',
                                title: 'Fecha de primer pago',
                                description: 'Seleccione la fecha en la que desea realizar el primer pago del crédito.',
                            },
                            {
                                element: '#peri',
                                title: 'Tipo de periodo',
                                description: 'Seleccione el tipo de periodo en el que desea realizar los pagos del crédito. Ésto depende de la forma en que se pagarán los intereses.',
                            },
                            {
                                element: '#btnGenerarPlanPago',
                                title: 'Generar plan de pago',
                                description: 'Una vez que haya ingresado todos los datos, haga clic en el botón para generar el plan de pago.',
                            },
                        ];
                        const driverObj = initializeDriver(stepsData);
                        driverObj.drive();
                    }
                </script>
            <?php
            }
            break;
        case "info_adicional_Garantia":
            /**
             * Procesa la información adicional de la garantía seleccionada
             */

            $codcliente = $_POST['xtra'] ?? '0';
            $showmensaje = false;

            try {
                if ($codcliente == '0') {
                    $showmensaje = true;
                    throw new Exception("Seleccione un cliente");
                }

                $database->openConnection();

                // Obtener información del cliente
                $cliente = $database->selectColumns(
                    'tb_cliente',
                    ['idcod_cliente', 'short_name', 'no_identifica'],
                    "estado=1 AND idcod_cliente=?",
                    [$codcliente]
                );

                if (empty($cliente)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró el cliente seleccionado");
                }

                // Obtener información adicional del cliente - FIX: precision entre backticks
                $info_adicional = $database->selectColumns(
                    'cli_adicionales',
                    ['id', 'entidad_tipo', 'entidad_id', 'descripcion', 'latitud', 'longitud', 'altitud', '`precision`', 'direccion_texto', 'estado', 'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'],
                    "entidad_tipo='cliente' AND entidad_id=? AND estado=1",
                    [$codcliente]
                );

                // Obtener archivos relacionados
                $archivos = $database->selectColumns(
                    'cli_adicional_archivos',
                    ['id', 'id_adicional', 'path_file'],
                    "id_adicional IN (SELECT id FROM cli_adicionales WHERE entidad_id=? AND estado=1)",
                    [$codcliente]
                );

                $status = 1;
            } catch (Exception $e) {
                if (!$showmensaje) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                }
                $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
                $status = 0;
            } finally {
                $database->closeConnection();
            }

            $sessionSerial = generarCodigoAleatorio();
            ?>

            <!-- Modal para búsqueda de clientes -->
            <div class="modal fade" id="buscar_cli_gen" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Búsqueda de Garantías</h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table id="tb_buscaClient" class="table table-striped nowrap" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th scope="col">Código</th>
                                            <th scope="col">Nombre Completo</th>
                                            <th scope="col">No. Identificación</th>
                                            <th scope="col">Nacimiento</th>
                                            <th scope="col">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="categoria_tb"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inputs ocultos requeridos -->
            <input type="text" id="file" value="clientes_001" style="display: none;">
            <input type="text" id="condi" value="info_adicional_Garantia" style="display: none;">

            <div class="container-fluid mt-3">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡Alerta!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <div class="row">
                    <!-- Columna izquierda: Formulario -->
                    <div class="col-lg-4 col-md-12">
                        <!-- Tarjeta de búsqueda de cliente -->
                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-search"></i> Buscar cliente</h6>
                            </div>
                            <div class="card-body">
                                <div class="input-group">
                                    <button class="btn btn-outline-info" type="button" data-bs-toggle="modal" data-bs-target="#buscar_cli_gen">
                                        <i class="fa-solid fa-users"></i>
                                    </button>
                                    <input id="nomCliente" type="text" class="form-control" placeholder="Seleccione un cliente" readonly
                                        value="<?= $cliente[0]['short_name'] ?? "" ?>">
                                </div>
                                <input type="text" id="codCli" hidden value="<?= $cliente[0]['idcod_cliente'] ?? "" ?>">
                            </div>
                        </div>

                        <?php if ($status) { ?>
                            <!-- Formulario de información adicional -->
                            <div class="card mb-3">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0"><i class="fa-solid fa-edit"></i> Información Adicional</h6>
                                </div>
                                <div class="card-body">
                                    <!-- Selector de tipo de entidad -->
                                    <!-- Tipo de entidad (oculto, siempre 'cliente') -->
                                    <input type="hidden" id="entidad_tipo" value="cliente">

                                    <!-- Descripción -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Descripción</label>
                                        <textarea id="descripcion" class="form-control form-control-sm" rows="3" placeholder="Ingrese una descripción detallada..."></textarea>
                                    </div>

                                    <!-- Coordenadas GPS -->
                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <label class="form-label fw-bold">Latitud</label>
                                            <input type="number" id="latitud" class="form-control form-control-sm" step="any" placeholder="14.6349">
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label class="form-label fw-bold">Longitud</label>
                                            <input type="number" id="longitud" class="form-control form-control-sm" step="any" placeholder="-90.5069">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <label class="form-label fw-bold">Altitud (m)</label>
                                            <input type="number" id="altitud" class="form-control form-control-sm" step="any" placeholder="1500">
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label class="form-label fw-bold">Precisión (m)</label>
                                            <input type="number" id="precision_gps" class="form-control form-control-sm" step="any" placeholder="5" max="99.99">
                                        </div>
                                    </div>

                                    <!-- Dirección -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Dirección</label>
                                        <textarea id="direccion_texto" class="form-control form-control-sm" rows="2" placeholder="Ingrese la dirección completa..."></textarea>
                                    </div>

                                    <!-- Botones de ubicación -->
                                    <div class="d-grid gap-2 d-md-block mb-3">
                                        <button type="button" class="btn btn-info btn-sm" onclick="seleccionarEnMapa()">
                                            <i class="fa-solid fa-map-pin"></i> Usar Mapa
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="limpiarUbicacion()">
                                            <i class="fa-solid fa-eraser"></i> Limpiar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Carga de Archivos Múltiples -->
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fa-solid fa-upload"></i> Archivos Adjuntos</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <input type="file" id="archivos_adjuntos" class="form-control form-control-sm" multiple
                                            accept="image/*,application/pdf,.doc,.docx,.txt,.xlsx,.xls">
                                        <small class="text-muted">Formatos: Imágenes, PDF, Word, Excel, Texto</small>
                                    </div>

                                    <!-- Preview de archivos seleccionados -->
                                    <div id="preview_archivos" class="row"></div>

                                    <!-- Contador de archivos -->
                                    <div id="contador_archivos" class="mt-2" style="display: none;">
                                        <small class="text-info">
                                            <i class="fa-solid fa-paperclip"></i>
                                            <span id="numero_archivos">0</span> archivo(s) seleccionado(s)
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <button type="button" class="btn btn-success w-100" onclick="guardarInfoAdicional()">
                                                <i class="fa-solid fa-save"></i> Guardar
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button type="button" class="btn btn-danger w-100" onclick="limpiarFormulario()">
                                                <i class="fa-solid fa-times"></i> Limpiar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Columna derecha: Mapa y Tabla -->
                    <div class="col-lg-8 col-md-12">
                        <!-- Mapa Interactivo -->
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fa-solid fa-map"></i> Mapa Interactivo</h6>
                                <small class="text-light">
                                    <i class="fa-solid fa-info-circle"></i> Haga clic en el mapa para seleccionar ubicación
                                </small>
                            </div>
                            <div class="card-body p-0">
                                <div id="mapa_principal" style="height: 400px; width: 100%; background-color: #f8f9fa;">
                                    <div class="d-flex align-items-center justify-content-center h-100">
                                        <div class="text-center">
                                            <i class="fa-solid fa-map-pin fa-3x text-muted mb-2"></i>
                                            <p class="text-muted">Cargando mapa...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de datos -->
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-table"></i> Información Registrada</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0" id="tabla_info_adicional">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Tipo</th>
                                                <th>Descripción</th>
                                                <th>Coordenadas</th>
                                                <th>Dirección</th>
                                                <th>Archivos</th>
                                                <th>Opciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (isset($info_adicional) && !empty($info_adicional)) {
                                                foreach ($info_adicional as $info) {
                                                    // Buscar archivos relacionados
                                                    $archivos_info = array_filter($archivos, function ($archivo) use ($info) {
                                                        return $archivo['id_adicional'] == $info['id'];
                                                    });

                                                    // Contar archivos
                                                    $total_archivos = count($archivos_info);

                                                    // Obtener primera imagen si existe
                                                    $primera_imagen = null;
                                                    foreach ($archivos_info as $archivo) {
                                                        $extension = strtolower(pathinfo($archivo['path_file'], PATHINFO_EXTENSION));
                                                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                                                            $primera_imagen = $archivo['path_file'];
                                                            break;
                                                        }
                                                    }
                                            ?>
                                                    <tr>
                                                        <td><span class="badge bg-primary"><?= $info['id'] ?></span></td>
                                                        <td>
                                                            <span class="badge <?= $info['entidad_tipo'] == 'cliente' ? 'bg-info' : 'bg-warning' ?>">
                                                                <?= ucfirst($info['entidad_tipo']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small><?= htmlspecialchars(substr($info['descripcion'] ?? '', 0, 50)) ?><?= strlen($info['descripcion'] ?? '') > 50 ? '...' : '' ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($info['latitud'] && $info['longitud']) { ?>
                                                                <small class="text-primary">
                                                                    <i class="fa-solid fa-map-pin"></i>
                                                                    <?= number_format($info['latitud'], 4) ?>,<?= number_format($info['longitud'], 4) ?>
                                                                </small>
                                                            <?php } else { ?>
                                                                <span class="text-muted">-</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td>
                                                            <small><?= htmlspecialchars(substr($info['direccion_texto'] ?? '', 0, 30)) ?><?= strlen($info['direccion_texto'] ?? '') > 30 ? '...' : '' ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($primera_imagen) { ?>
                                                                <img src="<?= htmlspecialchars($primera_imagen) ?>"
                                                                    alt="Vista previa"
                                                                    style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; cursor: pointer;"
                                                                    onclick="verImagenCompleta('<?= htmlspecialchars($primera_imagen) ?>')"
                                                                    title="Click para ver imagen completa">
                                                            <?php } ?>
                                                            <?php if ($total_archivos > 0) { ?>
                                                                <small class="text-muted ms-1">(<?= $total_archivos ?>)</small>
                                                            <?php } else { ?>
                                                                <span class="text-muted">-</span>
                                                            <?php } ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button type="button" class="btn btn-outline-primary" onclick="verDetalle(<?= $info['id'] ?>)" title="Ver">
                                                                    <i class="fa-solid fa-eye"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-warning" onclick="editarInfo(<?= $info['id'] ?>)" title="Editar">
                                                                    <i class="fa-solid fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger" onclick="eliminarInfo(<?= $info['id'] ?>)" title="Eliminar">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                                <?php if ($info['latitud'] && $info['longitud']) { ?>
                                                                    <button type="button" class="btn btn-outline-info" onclick="centrarEnMapa(<?= $info['latitud'] ?>, <?= $info['longitud'] ?>)" title="Ver en mapa">
                                                                        <i class="fa-solid fa-map-pin"></i>
                                                                    </button>
                                                                <?php } ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php }
                                            } else { ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-4">
                                                        <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                                                        No hay información registrada
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para ver imagen completa -->
            <div class="modal fade" id="modal_imagen_completa" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Vista de Imagen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img id="imagen_modal" src="" alt="Imagen completa" style="max-width: 100%; height: auto;">
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // Variables globales
                var mapaInfoCliente = null;
                var marcadorTemporalInfoCliente = null;
                var marcadoresInfoCliente = [];
                var modoSeleccionMapa = false;

                $(document).ready(function() {
                    // Inicializar DataTable para búsqueda de clientes
                    if ($.fn.DataTable.isDataTable("#tb_buscaClient")) {
                        $("#tb_buscaClient").DataTable().destroy();
                    }

                    $("#tb_buscaClient").DataTable({
                        "processing": true,
                        "serverSide": true,
                        "sAjaxSource": "../src/server_side/clientes_no_juridicos.php",
                        "columnDefs": [{
                            "data": 0,
                            "targets": 4,
                            render: function(data, type, row) {
                                return `<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2('#cuadro','${data}')">Aceptar</button>`;
                            }
                        }],
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

                    // Cargar e inicializar mapa
                    if (typeof L !== 'undefined') {
                        setTimeout(function() {
                            inicializarMapaInfoCliente();
                        }, 500);
                    } else {
                        cargarLeaflet();
                    }

                    // Preview de archivos seleccionados
                    $('#archivos_adjuntos').on('change', function(e) {
                        mostrarPreviewArchivos(e.target.files);
                    });
                });

                function cargarLeaflet() {
                    // Cargar CSS de Leaflet
                    if (!$('link[href*="leaflet"]').length) {
                        $('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />').appendTo('head');
                    }

                    // Cargar JS de Leaflet
                    if (typeof L === 'undefined') {
                        $.getScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', function() {
                            //console.log('Leaflet cargado exitosamente');
                            setTimeout(function() {
                                inicializarMapaInfoCliente();
                            }, 100);
                        });
                    }
                }

                function inicializarMapaInfoCliente() {
                    try {
                        if (mapaInfoCliente) {
                            mapaInfoCliente.remove();
                        }

                        if (!document.getElementById('mapa_principal')) {
                            //console.log('Contenedor del mapa no encontrado');
                            return;
                        }

                        // Coordenadas por defecto (Guatemala)
                        mapaInfoCliente = L.map('mapa_principal').setView([14.6349, -90.5069], 8);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap contributors'
                        }).addTo(mapaInfoCliente);

                        // Agregar marcadores existentes
                        <?php if (isset($info_adicional) && !empty($info_adicional)) {
                            foreach ($info_adicional as $info) {
                                if ($info['latitud'] && $info['longitud']) { ?>
                                    var marcadorExistente = L.marker([<?= $info['latitud'] ?>, <?= $info['longitud'] ?>])
                                        .addTo(mapaInfoCliente)
                                        .bindPopup(`
                                <div style="min-width: 200px;">
                                    <strong>ID: <?= $info['id'] ?></strong><br>
                                    <strong>Tipo:</strong> <?= ucfirst($info['entidad_tipo']) ?><br>
                                    <strong>Descripción:</strong><br>
                                    <small><?= htmlspecialchars(substr($info['descripcion'] ?? '', 0, 100)) ?></small><br>
                                    <strong>Dirección:</strong><br>
                                    <small><?= htmlspecialchars($info['direccion_texto'] ?? '') ?></small>
                                </div>
                            `);
                                    marcadoresInfoCliente.push(marcadorExistente);
                        <?php }
                            }
                        } ?>

                        // Event listener para clicks en el mapa
                        mapaInfoCliente.on('click', function(e) {
                            if (modoSeleccionMapa) {
                                if (marcadorTemporalInfoCliente) {
                                    mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                                }

                                marcadorTemporalInfoCliente = L.marker([e.latlng.lat, e.latlng.lng])
                                    .addTo(mapaInfoCliente)
                                    .bindPopup('Ubicación seleccionada<br><small>Lat: ' + e.latlng.lat.toFixed(6) + '<br>Lng: ' + e.latlng.lng.toFixed(6) + '</small>')
                                    .openPopup();

                                // Actualizar campos del formulario
                                $('#latitud').val(e.latlng.lat.toFixed(6));
                                $('#longitud').val(e.latlng.lng.toFixed(6));

                                // Obtener dirección aproximada usando geocodificación inversa
                                obtenerDireccionReversa(e.latlng.lat, e.latlng.lng);

                                modoSeleccionMapa = false;

                                if (typeof iziToast !== 'undefined') {
                                    iziToast.success({
                                        title: 'Ubicación seleccionada',
                                        message: 'Coordenadas actualizadas en el formulario',
                                        position: 'topRight'
                                    });
                                }
                            }
                        });

                        //console.log('Mapa inicializado correctamente');
                    } catch (error) {
                        //console.error('Error al inicializar el mapa:', error);
                    }
                }

                function obtenerDireccionReversa(lat, lng) {
                    // Usar Nominatim para geocodificación inversa
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.display_name) {
                                $('#direccion_texto').val(data.display_name);
                            }
                        })
                        .catch(error => {
                            // console.log('No se pudo obtener la dirección:', error);
                        });
                }

                function mostrarPreviewArchivos(files) {
                    const previewContainer = $('#preview_archivos');
                    const contadorContainer = $('#contador_archivos');
                    const numeroArchivos = $('#numero_archivos');

                    previewContainer.empty();

                    if (files.length === 0) {
                        contadorContainer.hide();
                        return;
                    }

                    contadorContainer.show();
                    numeroArchivos.text(files.length);

                    Array.from(files).forEach((file, index) => {
                        const fileType = file.type;
                        const fileName = file.name;
                        const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB

                        if (fileType.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewContainer.append(`
                        <div class="col-3 mb-2">
                            <div class="position-relative">
                                <img src="${e.target.result}" alt="${fileName}" 
                                     class="img-thumbnail" 
                                     style="width: 100%; height: 60px; object-fit: cover;" 
                                     title="${fileName} (${fileSize} MB)">
                                <small class="position-absolute bottom-0 start-0 bg-dark text-white px-1" style="font-size: 8px;">
                                    ${fileSize}MB
                                </small>
                            </div>
                        </div>
                    `);
                            };
                            reader.readAsDataURL(file);
                        } else {
                            // Iconos para diferentes tipos de archivo
                            let iconClass = 'fa-file';
                            if (fileType.includes('pdf')) iconClass = 'fa-file-pdf';
                            else if (fileType.includes('word') || fileName.includes('.doc')) iconClass = 'fa-file-word';
                            else if (fileType.includes('excel') || fileName.includes('.xls')) iconClass = 'fa-file-excel';
                            else if (fileType.includes('text')) iconClass = 'fa-file-text';

                            previewContainer.append(`
                    <div class="col-3 mb-2">
                        <div class="bg-secondary text-white p-2 rounded text-center position-relative" style="height: 60px; font-size: 10px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                            <i class="fa-solid ${iconClass} mb-1"></i>
                            <span>${fileName.substring(0, 8)}...</span>
                            <small class="position-absolute bottom-0 start-0 bg-dark text-white px-1" style="font-size: 8px;">
                                ${fileSize}MB
                            </small>
                        </div>
                    </div>
                `);
                        }
                    });
                }

                function verImagenCompleta(imagenSrc) {
                    $('#imagen_modal').attr('src', imagenSrc);
                    $('#modal_imagen_completa').modal('show');
                }

                function seleccionarEnMapa() {
                    modoSeleccionMapa = true;
                    if (typeof iziToast !== 'undefined') {
                        iziToast.info({
                            title: 'Modo selección activado',
                            message: 'Haga clic en el mapa para seleccionar una ubicación',
                            position: 'topRight',
                            timeout: 5000
                        });
                    }
                }

                function limpiarUbicacion() {
                    $('#latitud').val('');
                    $('#longitud').val('');
                    $('#altitud').val('');
                    $('#precision_gps').val('');
                    $('#direccion_texto').val('');

                    if (marcadorTemporalInfoCliente && mapaInfoCliente) {
                        mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                        marcadorTemporalInfoCliente = null;
                    }

                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: 'Ubicación limpiada',
                            message: 'Se han limpiado los datos de ubicación',
                            position: 'topRight'
                        });
                    }
                }

                function limpiarFormulario() {
                    $('#entidad_tipo').val('cliente');
                    $('#descripcion').val('');
                    $('#archivos_adjuntos').val('');
                    $('#preview_archivos').empty();
                    $('#contador_archivos').hide();

                    limpiarUbicacion();

                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: 'Formulario limpiado',
                            message: 'Se han limpiado todos los campos',
                            position: 'topRight'
                        });
                    }
                }

                function centrarEnMapa(lat, lng) {
                    if (mapaInfoCliente) {
                        mapaInfoCliente.setView([lat, lng], 15);

                        if (typeof iziToast !== 'undefined') {
                            iziToast.info({
                                title: 'Ubicación centrada',
                                message: 'Mapa centrado en la ubicación seleccionada',
                                position: 'topRight'
                            });
                        }
                    }
                }

                function guardarInfoAdicional() {
                    // Validar cliente seleccionado
                    if (!$('#codCli').val() || $('#codCli').val() === '0') {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({
                                title: 'Error de validación',
                                message: 'Debe seleccionar un cliente',
                                position: 'topRight'
                            });
                        } else {
                            alert('Debe seleccionar un cliente');
                        }
                        return;
                    }

                    // Validar descripción
                    if (!$('#descripcion').val().trim()) {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({
                                title: 'Error de validación',
                                message: 'La descripción es obligatoria',
                                position: 'topRight'
                            });
                        } else {
                            alert('La descripción es obligatoria');
                        }
                        return;
                    }

                    const formData = new FormData();

                    // Agregar datos del formulario
                    formData.append('entidad_tipo', $('#entidad_tipo').val());
                    formData.append('entidad_id', $('#codCli').val());
                    formData.append('descripcion', $('#descripcion').val());
                    formData.append('latitud', $('#latitud').val() || null);
                    formData.append('longitud', $('#longitud').val() || null);
                    formData.append('altitud', $('#altitud').val() || null);
                    formData.append('precision', $('#precision_gps').val() || null);
                    formData.append('direccion_texto', $('#direccion_texto').val());

                    // Agregar archivos
                    const archivos = $('#archivos_adjuntos')[0].files;
                    for (let i = 0; i < archivos.length; i++) {
                        formData.append('archivos[]', archivos[i]);
                    }

                    // Aquí implementarías la petición AJAX para guardar
                    // console.log('Datos a guardar:', {
                    //     entidad_tipo: $('#entidad_tipo').val(),
                    //     entidad_id: $('#codCli').val(),
                    //     descripcion: $('#descripcion').val(),
                    //     latitud: $('#latitud').val(),
                    //     longitud: $('#longitud').val(),
                    //     altitud: $('#altitud').val(),
                    //     precision: $('#precision_gps').val(),
                    //     direccion_texto: $('#direccion_texto').val(),
                    //     total_archivos: archivos.length
                    // });

                    // Mostrar mensaje de éxito (temporal)
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: 'Información guardada',
                            message: 'Los datos se han guardado correctamente',
                            position: 'topRight'
                        });
                    } else {
                        alert('Información guardada correctamente');
                    }

                    // Limpiar formulario después de guardar
                    limpiarFormulario();
                }

                function editarInfo(id) {
                    // Implementar carga de datos para edición
                    //console.log('Editar ID:', id);

                    if (typeof iziToast !== 'undefined') {
                        iziToast.info({
                            title: 'Función en desarrollo',
                            message: 'La función de edición estará disponible pronto',
                            position: 'topRight'
                        });
                    }
                }

                function verDetalle(id) {
                    // Implementar vista de detalle
                    //console.log('Ver detalle del ID:', id);

                    if (typeof iziToast !== 'undefined') {
                        iziToast.info({
                            title: 'Función en desarrollo',
                            message: 'La función de detalle estará disponible pronto',
                            position: 'topRight'
                        });
                    }
                }

                function eliminarInfo(id) {
                    if (confirm('¿Está seguro de eliminar esta información?\n\nEsta acción no se puede deshacer.')) {
                        // Implementar eliminación
                        //console.log('Eliminar ID:', id);

                        if (typeof iziToast !== 'undefined') {
                            iziToast.warning({
                                title: 'Función en desarrollo',
                                message: 'La función de eliminación estará disponible pronto',
                                position: 'topRight'
                            });
                        }
                    }
                }
            </script>
    <?php
            break;
    }

    ?>