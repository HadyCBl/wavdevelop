<?php

use Creditos\Utilidades\PaymentManager;

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
require_once __DIR__ . '/../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../includes/Config/database.php';
require_once __DIR__ . '/../../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

//++++++++++++++
$usuario = $_SESSION["id"];
$id_agencia = $_SESSION['id_agencia'];

include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include '../../../src/funcphp/valida.php';

$condi = $_POST["condi"];
// $datpost2 = $_POST["xtra"];
// $extra2 = $datpost2[0];
switch ($condi) {
    case 'solicitud':
        $datpost = $_POST["xtra"];
        $extra = $datpost[0];
        $bandera = "fasdfsadf";
        $cicloact = 0;
        if ($extra != 0) {
            $existentes[] = [];
            $compr = mysqli_query($conexion, 'SELECT crems.CCODCTA,cli.short_name, crems.Cestado, crems.NCiclo FROM cremcre_meta crems 
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=crems.CodCli
            WHERE crems.CCodGrupo="' . $extra . '" AND crems.TipoEnti="GRUP" AND (crems.Cestado="A" OR crems.Cestado="D" OR crems.Cestado="E")');
            $k = 0;
            $bandera = "";
            while ($dd = mysqli_fetch_array($compr, MYSQLI_ASSOC)) {
                $existentes[$k] = $dd;
                $bandera = "Grupo Con Creditos en Proceso, debe cancelarlos o proseguir con los mismos";
                $k++;
            }

            if ($bandera == "") {
                //CREDITOS DEL GRUPO
                $datos[] = [];
                $datagrup = mysqli_query($conexion, 'SELECT grup.id_grupos,grup.codigo_grupo,grup.NombreGrupo,grup.direc,cli.idcod_cliente,cli.short_name,cli.url_img,cli.date_birth,cli.genero,cli.estado_civil,cli.no_identifica,cli.no_tributaria
                    FROM tb_cliente_tb_grupo cligr 
                    INNER JOIN tb_grupo grup ON grup.id_grupos=cligr.Codigo_grupo
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cligr.cliente_id 
                    WHERE cligr.Codigo_grupo="' . $extra . '" AND cligr.estado=1');
                $bandera = "Grupo sin Integrantes";
                $i = 0;
                while ($da = mysqli_fetch_array($datagrup, MYSQLI_ASSOC)) {
                    $datos[$i] = $da;
                    $i++;
                    $bandera = "";
                }
                //CICLO ACTUAL DEL GRUPO, NO ES DEL CLIENTE

                if ($bandera == "") {
                    $datacre = mysqli_query($conexion, 'SELECT MAX(crems.NCiclo) cicloact FROM cremcre_meta crems WHERE crems.CCodGrupo=' . $extra . '');
                    while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
                        $cicloact = $da["cicloact"];
                    }
                    $cicloact = ($cicloact == NULL) ? 0 : $cicloact;
                }
            }
        }

?>
        <input type="text" readonly hidden value='solicitud' id='condi'>
        <input type="text" readonly hidden value='grup001' id='file'>
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>SOLICITUD DE CREDITOS GRUPAL</h4>
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
                            <button type="button" onclick="loadconfig('all','all')" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#buscargrupo">
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
                                <input type="number" class="form-control " id="nciclo" readonly value="<?php echo $cicloact + 1; ?>">
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <label class="input-group-addon fw-bold">Analista</label>

                            <select class="form-select" name="" id="codanal">
                                <?php
                                //$consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA' AND id_agencia IN( SELECT id_agencia FROM tb_usuario WHERE id_usu=$usuario)");
                                $consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA'");
                                echo '<option value="0" selected disabled>Seleccione un Asesor</option>';
                                while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                    $nombre = $dtas["nameusu"];
                                    $id_usu = $dtas["id_usu"];
                                    echo '<option value="' . $id_usu . '">' . $nombre . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($bandera != "" && $extra != "0") {
                        echo '<div class="alert alert-danger col-7" role="alert">' . $bandera . '';
                        if ($k > 0) {
                            echo '<ol class="list-group list-group-numbered">';
                            $i = 0;
                            $estados = ['A' => 'SOLICITUD', 'D' => 'ANALISIS', 'E' => 'APROBACION'];
                            while ($i < count($existentes)) {
                                echo '<li class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                  <div class="fw-bold">' . $existentes[$i]["CCODCTA"] . '</div>
                                  ' . $existentes[$i]["short_name"] . '
                                </div>
                                <span class="badge bg-danger rounded-pill">Estado: ' . $estados[$existentes[$i]["Cestado"]] . '</span>
                                <span class="badge bg-primary rounded-pill">Ciclo: ' . $existentes[$i]["NCiclo"] . '</span>
                              </li>';
                                $i++;
                            }
                            echo '</ol>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="row contenedort" style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
                    <h5>CIENTES DEL GRUPO</h5>
                    <div class="accordion" id="cuotas">
                        <?php
                        if ($bandera == "") {
                            $j = 0;
                            while ($j < count($datos)) {
                                $codcli = $datos[$j]["idcod_cliente"];
                                $name = $datos[$j]["short_name"];
                                $fecnac = date("d-m-Y", strtotime($datos[$j]["date_birth"]));
                                $urlimg = $datos[$j]["url_img"];
                                $genero = $datos[$j]["genero"];
                                $estadocivil = $datos[$j]["estado_civil"];
                                $dpi = $datos[$j]["no_identifica"];
                                $nit = $datos[$j]["no_tributaria"];
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
                                                <button id="<?php echo 'bt' . $j; ?>" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#<?php echo $idit; ?>" aria-expanded="true" aria-controls="<?php echo $idit; ?>">
                                                    <div class="row" style="width:100%;font-size: 0.90rem;">
                                                        <div class="col-2">
                                                            <img width="80" height="80" id="vistaPrevia" src="<?php echo $src; ?>" /><br />
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <span class="input-group-addon"><?php echo $codcli; ?></span>
                                                                </div>
                                                                <div class="col-12">
                                                                    <input id="<?php echo 'ccodcli' . $j; ?>" type="text" value="<?php echo $codcli; ?>" hidden>
                                                                    <span class="input-group-addon"><?php echo strtoupper($name); ?></span>
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
                                                                <span class="input-group-addon">Fecha Nacimiento</span>
                                                                <span class="input-group-addon"><?php echo $fecnac; ?></span>
                                                            </div>

                                                        </div>
                                                        <div class="col-2">
                                                            <div class="row">
                                                                <span class="input-group-addon">Genero</span>
                                                                <span class="input-group-addon"><?php echo $genero; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </h2>
                                    <div id="<?php echo $idit; ?>" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                        <div class="accordion-body">
                                            <div class="row mb-3" style="font-size: 0.90rem;">
                                                <div class="col-sm-6">
                                                    <label class="input-group-addon fw-bold">Monto Solicitado</label>
                                                    <input type="number" step="0.01" class="form-control" placeholder="000.00" id="<?php echo 'monsol' . $j; ?>">
                                                </div>
                                                <div class="col-sm-6">
                                                    <label class="input-group-addon fw-bold">Destino Credito</label>
                                                    <select class="form-select" name="" id="<?php echo 'descre' . $j; ?>">
                                                        <?php DestinoCre($general); ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row mb-3" style="font-size: 0.90rem;">
                                                <div class="col-sm-6">
                                                    <label class="input-group-addon fw-bold">Sector Economico</label>
                                                    <select class="form-select" name="" id="<?php echo 'sectorecono' . $j; ?>" onchange="SctrEcono('#<?php echo 'actecono' . $j; ?>', this.value,'#ActvEcn')">
                                                        <option value="0">Seleccionar un sector Economico</option>
                                                        <?php
                                                        $sect = mysqli_query($general, "SELECT id_SectoresEconomicos, SectoresEconomicos FROM `tb_sectoreseconomicos`");
                                                        while ($sse = mysqli_fetch_array($sect, MYSQLI_ASSOC)) {
                                                            $idSctr = $sse["id_SectoresEconomicos"];
                                                            $SctrEcono = $sse["SectoresEconomicos"];
                                                            echo '<option value="' . $idSctr . '">' . $SctrEcono . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="col-sm-6">
                                                    <label class="input-group-addon fw-bold">Actividad Economica</label>
                                                    <input type="text" class="form-control" id="ActvEcn" readonly hidden>
                                                    <select class="form-select" name="" id="<?php echo 'actecono' . $j; ?>">
                                                        <option value="0" selected disabled>Seleccione Actividad Economica</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                        echo '<button type="button" class="btn btn-outline-success" onclick="savesol(' . ($j - 1) . ',' . $usuario . ',' . $extra . ',`' . $_SESSION['agencia'] . '`)">
                            <i class="fa fa-floppy-disk"></i> Guardar
                        </button>';
                    }
                    ?>
                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro', '0')">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="salir()">
                        <i class="fa-solid fa-circle-xmark"></i> Salir
                    </button>
                    <!--                      <button onclick="reportes([[], [], [], [archivo[0], archivo[1]]], `pdf`, `ficha_aprobacion`, 0)">asdfas</button> -->
                </div>
            </div>
        </div>
    <?php
        break;
    case 'analisis':
        $datpost = $_POST["xtra"];
        /*         echo ('<pre>');
        print_r($datpost);
        echo ('</pre>'); */
        $extra = $datpost[0];
        $bandera = "Grupo sin cuentas por Analizar";
        if ($extra != "0") {
            $numciclo = $datpost[1];
            //CREDITOS DEL GRUPO
            $datos[] = [];
            $datacre = mysqli_query($conexion, 'SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
            cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.CodAnal,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,cre.NIntApro
            From cremcre_meta cre
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
            INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
            WHERE cre.TipoEnti="GRUP" AND (cre.CESTADO="A" OR cre.CESTADO="D") AND cre.CCodGrupo="' . $extra . '" AND cre.NCiclo=' . $numciclo);

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
        <input type="text" readonly hidden value='analisis' id='condi'>
        <input type="text" readonly hidden value='grup001' id='file'>
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>ANALISIS DE CREDITOS GRUPAL</h4>
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
                            <button id="findgrupo" onclick="loadconfig('any',['A','D'])" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#buscargrupo">
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
                                <input type="number" class="form-control" id="nciclo" readonly value="<?php if ($bandera == "") echo $numciclo; ?>">
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <label class="input-group-addon fw-bold">Analista</label>

                            <select class="form-select" name="" id="codanal">
                                <?php
                                //$consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA' AND id_agencia IN( SELECT id_agencia FROM tb_usuario WHERE id_usu=$usuario)");
                                $consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE puesto='ANA'");
                                echo '<option value="0">Seleccione un Asesor</option>';
                                $selected = "";
                                while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                    $nombre = $dtas["nameusu"];
                                    $id_usu = $dtas["id_usu"];
                                    $selected = ($datos[0]["CodAnal"] == $id_usu) ? " selected" : "";
                                    echo '<option value="' . $id_usu . '" ' . $selected . '>' . $nombre . '</option>';
                                }
                                ?>
                            </select>
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
                        $est = ($datos[0]["Cestado"] == "D") ? " ANALIZADO" : "SOLICITADO";
                        echo 'ESTADO: ' . $est;
                    }
                    ?>
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
                                <input type="date" class="form-control" id="fecinit" value="<?php echo  $firstcuota; ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <span class="fw-bold">No. Cuotas</span>
                                <input type="number" class="form-control" id="nrocuo" value="<?php if ($bandera == "") echo $datos[0]["noPeriodo"]; ?>">
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Fecha Desembolso</span>
                                <input type="date" class="form-control" id="fecdes" value="<?php echo $fecdes; ?>">
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Dictamen??</span>
                                <input type="text" class="form-control" id="dictmn" value="<?php if ($bandera == "") echo $datos[0]["Dictamen"]; ?>">
                            </div>
                        </div>
                    </div>
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
                <div class="row contenedort" style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
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
                                                <button id="<?php echo 'bt' . $j; ?>" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#<?php echo $idit; ?>" aria-expanded="true" aria-controls="<?php echo $idit; ?>">
                                                    <div class="row" style="width:100%;font-size: 0.90rem;">
                                                        <div class="col-2">
                                                            <img width="80" height="80" id="vistaPrevia" src="<?php echo $src; ?>" /><br />
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <span class="input-group-addon"><?php echo $ccodta; ?></span>
                                                                </div>
                                                                <div class="col-12">
                                                                    <input id="<?php echo 'ccodcta' . $j; ?>" type="text" value="<?php echo $ccodta; ?>" hidden>
                                                                    <span class="input-group-addon"><?php echo strtoupper($name); ?></span>
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
                                                                <span class="input-group-addon">Fecha Nacimiento</span>
                                                                <span class="input-group-addon"><?php echo $fecnac; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </h2>
                                    <div id="<?php echo $idit; ?>" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                        <div class="accordion-body">
                                            <div class="row mb-3" style="font-size: 0.90rem;">
                                                <div class="col-sm-2">
                                                    <label class="input-group-addon fw-bold">Monto Solicitado</label>
                                                    <input type="number" step="0.01" class="form-control" placeholder="000.00" value="<?php echo  $monsol; ?>" disabled>
                                                </div>
                                                <div class="col-sm-2">
                                                    <label class="input-group-addon fw-bold">Monto A Aprobar</label>
                                                    <input id="<?php echo 'monapr' . $j; ?>" type="number" step="0.01" class="form-control" placeholder="000.00" value="<?php echo  $monsug; ?>">
                                                </div>
                                                <div class="col-sm-2">
                                                    <br>
                                                    <?php
                                                    if ($estado == "D") {
                                                        echo '<button type="button" class="btn btn-warning" onclick="reportes([[],[],[],[`' . $ccodta . '`]], `pdf`, 40,0,1)">Plan de pagos</button>';
                                                    } else {
                                                        echo 'Guarde los cambios para poder visualizar el plan de pago';
                                                    }
                                                    ?>
                                                </div>
                                                <!-- <div class="col-sm-2">
                                                    <br>
                                                    <?php
                                                    // if ($estado == "D") {
                                                    //     echo '<button type="button" class="btn btn-warning" onclick="reportes([[],[],[],[`' . $ccodta . '`]], `pdf`, `20`,0,1)">Dictamen </button>';
                                                    // } else {
                                                    //     echo 'Guarde los cambios para poder visualizar el dictamen';
                                                    // }
                                                    ?>
                                                </div> -->
                                                <div class="col-sm-4">
                                                    <?php
                                                    if ($estado == "D") {
                                                        echo 'Debe guardar cada cambio que haga para visualizar el plan de pagos con los datos actualizados';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                        echo '<button type="button" class="btn btn-outline-success" onclick="saveanal(' . ($j - 1) . ',' . $numciclo . ',' . $extra . ',`' . $_SESSION['agencia'] . '`)">
                            <i class="fa fa-floppy-disk"></i> Guardar Cambios
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
        <!-- <button type="button" onclick="reportes([[], [], [], ['0070010200000005']], `pdf`, `../../cre_indi/reportes/dictamen`, 0)">ksjdfhjshdjh</button> -->
        <script>
            function update(val1, val2) {
                loaderefect(1);
                dire = "../../views/Creditos/cre_indi/cre_indi_01.php";
                creperi('tpscre2', '#alrtpnl', 'cre_indi_01', val1);
                $("#tipcre option[value='" + val1 + "']").attr("selected", true);
                $.ajax({
                    url: dire,
                    method: "POST",
                    data: {
                        condi: 'prdscre',
                        xtra: val1
                    },
                    success: function(data) {
                        $('#peri').html(data);
                        $("#peri option[value='" + val2 + "']").attr("selected", true);
                        loaderefect(0);
                    }
                })
            }
            <?php
            if ($bandera == "" && $datos[0]["Cestado"] == "D") {
                echo "update('" . $datos[0]["CtipCre"] . "','" . $datos[0]["NtipPerC"] . "');";
            }
            ?>
        </script>
    <?php
        break;
    case 'aprobacion':

        $datpost = $_POST["xtra"];
        /*         echo ('<pre>');
            print_r($datpost);
            echo ('</pre>'); */
        $extra = $datpost[0];
        $bandera = "Grupo sin cuentas por Aprobar";
        if ($extra != "0") {
            $numciclo = $datpost[1];
            //CREDITOS DEL GRUPO
            $datos[] = [];
            $datacre = mysqli_query($conexion, 'SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
                cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.CodAnal,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,cre.NIntApro
                From cremcre_meta cre
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
                WHERE cre.TipoEnti="GRUP" AND (cre.CESTADO="D" OR cre.CESTADO="D") AND cre.CCodGrupo="' . $extra . '" AND cre.NCiclo=' . $numciclo);

            $i = 0;
            while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
                $datos[$i] = $da;
                $i++;
                $bandera = "";
            }
        }
    ?>
        <input type="text" readonly hidden value='aprobacion' id='condi'>
        <input type="text" readonly hidden value='grup001' id='file'>
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>APROBACION DE CREDITOS GRUPAL</h4>
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
                            <button id="findgrupo" onclick="loadconfig('any',['D'])" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#buscargrupo">
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
                                <input type="number" class="form-control" id="nciclo" readonly value="<?php if ($bandera == "") echo $numciclo; ?>">
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <label class="input-group-addon fw-bold">Analista</label>

                            <select class="form-select" name="" id="codanal" disabled>
                                <?php
                                $consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu , id_usu FROM tb_usuario WHERE id_agencia IN( SELECT id_agencia FROM tb_usuario WHERE id_usu=$usuario)");
                                echo '<option value="0">Seleccione un Asesor</option>';
                                $selected = "";
                                while ($dtas = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                    $nombre = $dtas["nameusu"];
                                    $id_usu = $dtas["id_usu"];
                                    $selected = ($datos[0]["CodAnal"] == $id_usu) ? " selected" : "";
                                    echo '<option value="' . $id_usu . '" ' . $selected . '>' . $nombre . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($extra != "0" && $bandera != "") {
                        echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                    } else if ($extra != "0") {
                        $est = ($datos[0]["Cestado"] == "D") ? " ANALIZADO" : "SOLICITADO";
                        echo 'ESTADO: ' . $est;
                    }
                    ?>
                </div>
                <div class="row contenedort">
                    <div class="card-body">
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
                                        <input type="number" class="form-control" id="idprod" readonly hidden>
                                        <input type="number" class="form-control" id="codprod" value="<?php if ($prd == 1) echo $dprod[0]["cod_producto"]; ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-sm-6">
                                    <span class="fw-bold">Nombre</span>
                                    <input type="text" class="form-control" id="nameprod" readonly value="<?php if ($prd == 1) echo $dprod[0]["nompro"]; ?>">
                                </div>
                                <div class="form-group col-sm-3">
                                    <span class="fw-bold">%Interes Anual asignado</span>
                                    <input type="number" step="0.01" class="form-control" id="tasaprod" readonly value="<?php if ($bandera == "") echo $datos[0]["NIntApro"]; ?>">
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
                                    <span class="fw-bold">Ahorro</span>
                                    <input type="text" step="0.01" class="form-control" id="fondo" readonly value="<?php if ($prd == 1) echo $dprod[0]["fondesc"]; ?>">
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
                                <input type="text" class="form-control" id="tipcre" value="<?php if ($bandera == "") echo tip_cre_peri($datos[0]["CtipCre"]); ?>" readonly>
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Tipo de Periodo</span>
                                <input type="text" class="form-control" id="tipper" value="<?php if ($bandera == "") echo tip_cre_peri($datos[0]["NtipPerC"]); ?>" readonly>
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Fecha primer Cuota</span>
                                <input type="date" class="form-control" id="fecinit" value="<?php if ($bandera == "") echo $datos[0]["DfecPago"]; ?>" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <span class="fw-bold">No. Cuotas</span>
                                <input type="text" class="form-control" id="nrocuo" value="<?php if ($bandera == "") echo $datos[0]["noPeriodo"]; ?>" readonly>
                            </div>
                            <div class="col-sm-4">
                                <span class="fw-bold">Fecha Desembolso</span>
                                <input type="date" class="form-control" id="fecdes" value="<?php if ($bandera == "") echo $datos[0]["DFecDsbls"]; ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6
                            ">
                                <span class="fw-bold">Contrato</span>
                                <select id="contraIndi" class="form-select">
                                    <option selected value="C">Contrato individual</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="row contenedort" style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
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
                                                <button id="<?php echo 'bt' . $j; ?>" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#<?php echo $idit; ?>" aria-expanded="true" aria-controls="<?php echo $idit; ?>">
                                                    <div class="row" style="width:100%;font-size: 0.90rem;">
                                                        <div class="col-2">
                                                            <img width="80" height="80" id="vistaPrevia" src="<?php echo $src; ?>" /><br />
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <span class="input-group-addon"><?php echo $ccodta; ?></span>
                                                                </div>
                                                                <div class="col-12">
                                                                    <input id="<?php echo 'ccodcta' . $j; ?>" type="text" value="<?php echo $ccodta; ?>" hidden>
                                                                    <span class="input-group-addon"><?php echo strtoupper($name); ?></span>
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
                                                                <span class="input-group-addon">Fecha Nacimiento</span>
                                                                <span class="input-group-addon"><?php echo $fecnac; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </h2>
                                    <div id="<?php echo $idit; ?>" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                        <div class="accordion-body">
                                            <div class="row mb-3" style="font-size: 0.90rem;">
                                                <div class="col-sm-3">
                                                    <label class="input-group-addon fw-bold">Monto Solicitado</label>
                                                    <input type="number" step="0.01" class="form-control" placeholder="000.00" value="<?php echo  $monsol; ?>" disabled>
                                                </div>
                                                <div class="col-sm-3">
                                                    <label class="input-group-addon fw-bold">Monto Aprobado</label>
                                                    <input id="<?php echo 'monapr' . $j; ?>" type="number" step="0.01" class="form-control" placeholder="000.00" value="<?php echo  $monsug; ?>" disabled>
                                                </div>
                                                <div class="col-sm-6">
                                                    <br>
                                                    <div class="row justify-items-md-center">
                                                        <div class="col align-items-center">
                                                            <button type="button" class="btn btn-warning" onclick="reportes([[],[],[],[`<?php echo  $ccodta; ?>`]], `pdf`,40,0,1)">Plan
                                                                de pagos</button>
                                                            <!-- <button type="button" class="btn btn-warning" onclick="reportes([[],[],[],[`<?php //echo  $ccodta; 
                                                                                                                                                ?>`]], `pdf`, `19`,0,1)"> Generar contrato </button> -->
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                        echo '<button type="button" class="btn btn-outline-success" onclick="saveapro(' . ($j - 1) . ',' . ($extra) . ',' . ($numciclo) . ')">
                                <i class="fa fa-floppy-disk"></i> Aprobar Creditos
                            </button>';
                    }
                    ?>
                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro', '0')">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="window.location.reload();">
                        <i class="fa-solid fa-circle-xmark"></i> Salir
                    </button>
                    <!-- <button type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[10,1,1]], `pdf`, `ficha_aprobacion`, 0)">
                        <i class="fa fa-floppy-disk"></i> PROBAR FICHA APROBACION
                    </button> -->
                </div>
            </div>
        </div>
    <?php
        break;
    case 'desembolso':

        $utilidadesCreditos = new PaymentManager();

        $datpost = $_POST["xtra"];
        $extra = $datpost[0] ?? 0;
        $numciclo = $datpost[1] ?? 0;
        $query = "SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, 
            cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.CodAnal,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,
            pro.id_fondo,ff.descripcion descfondo
            From cremcre_meta cre
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
            INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
            INNER JOIN cre_productos pro ON pro.id=cre.CCODPRD
            INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo
            WHERE cre.TipoEnti='GRUP' AND (cre.CESTADO='E' OR cre.CESTADO='E') AND cre.CCodGrupo=? AND cre.NCiclo=? ORDER BY cre.CCODCTA;";

        $query2 = "SELECT pro.id,pro.cod_producto,pro.nombre nompro,pro.descripcion descriprod,ff.descripcion fondesc,pro.tasa_interes, pro.monto_maximo
                        FROM cre_productos pro
                        INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo WHERE pro.estado=1 AND pro.id=?";

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
                throw new Exception("Grupo sin cuentas por Desembolsar");
            }
            $dataProducto = $database->getAllResults($query2, [$datos[0]["CCODPRD"]]);
            if (empty($dataProducto)) {
                $showmensaje = true;
                throw new Exception("No se encontraron datos del producto");
            }

            $forRefinance = $database->getAllResults(
                "SELECT crep.id idGasto, tipg.id_nomenclatura,tipg.nombre_gasto
                FROM cre_productos_gastos crep 
                INNER JOIN cre_tipogastos tipg ON tipg.id=crep.id_tipo_deGasto
                WHERE tipg.estado=1 AND crep.estado=1 AND tipg.afecta_modulo=3 AND crep.tipo_deCobro=1 AND crep.id_producto=?;",
                [$datos[0]["CCODPRD"]]
            );


            foreach ($datos as $key => $value) {
                $descuentos = $utilidadesCreditos->descuentosDesembolso($value['CCODCTA'], $database);
                $descuentos??= [];
                $datos[$key]['descuentos'] = $descuentos;
                $datos[$key]['totalDescuentos'] = array_sum(array_column($descuentos, 'monto'));
                $datos[$key]['netoDesembolso'] = $value['MonSug'] - $datos[$key]['totalDescuentos'];
                if (!empty($forRefinance)) {
                    $datos[$key]['cuentasAnteriores'] = $utilidadesCreditos->getcuentas($value['CCODCTA'], $database);
                }
            }

            $totalNetoDesembolso = array_sum(array_column($datos, 'netoDesembolso'));

            $forVinculo = $database->getAllResults(
                "SELECT crep.id,tipg.nombre_gasto,tipg.afecta_modulo
                FROM cre_productos_gastos crep 
                INNER JOIN cre_tipogastos tipg ON tipg.id=crep.id_tipo_deGasto
                WHERE tipg.estado=1 AND crep.estado=1 AND tipg.afecta_modulo IN (1,2) AND crep.tipo_deCobro=2 AND crep.id_producto=?;",
                [$datos[0]["CCODPRD"]]
            );

            $bancos = $database->selectColumns('tb_bancos', ['id', 'nombre'], "estado=1");

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

        // echo "<pre>";
        // print_r($datos ?? []);
        // echo "</pre>";
    ?>
        <input type="text" readonly hidden value='desembolso' id='condi'>
        <input type="text" readonly hidden value='grup001' id='file'>
        <div class="card crdbody contenedort shadow-lg rounded-4 border-0">
            <div class="card-header bg-primary text-white rounded-top-4" style="text-align:left">
                <h4 class="mb-0"><i class="fa-solid fa-hand-holding-dollar me-2"></i>DESEMBOLSO DE CRÉDITOS GRUPAL</h4>
            </div>
            <div class="card-body bg-light rounded-bottom-4">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row contenedort mb-4">
                    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-users me-2"></i>Detalle de Grupo</h5>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <input type="text" readonly hidden value='<?= $extra ?>' id='idgrupo'>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold text-secondary">Nombre Grupo:</span>
                                <?php if ($status) echo '<span id="nongrupo" class="badge rounded-pill bg-success text-white fs-6 px-3 py-2">' . $datos[0]["NombreGrupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold text-secondary">Código de Grupo:</span>
                                <?php if ($status) echo '<span class="badge rounded-pill bg-success text-white fs-6 px-3 py-2" id="miSpan">' . $datos[0]["codigo_grupo"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button id="findgrupo" onclick="loadconfig('any',['E'])" type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#buscargrupo">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 align-items-center mt-2">
                        <div class="col-md-5">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold text-secondary">Dirección:</span>
                                <?php if ($status) echo '<span class="badge rounded-pill bg-success text-white fs-6 px-3 py-2">' . $datos[0]["direc"] . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-semibold text-secondary">Ciclo:</span>
                                <span class="badge rounded-pill bg-info text-dark fs-6 px-3 py-2"><?= $numciclo; ?></span>
                            </div>
                        </div>
                        <div class="col-md-5 d-flex align-items-center">
                            <?php if ($status) {
                                $est = ($datos[0]["Cestado"] == "E") ? "APROBADO" : "";
                                echo '<span class="ms-2 fw-bold text-success">ESTADO: ' . $est . '</span>';
                            } ?>
                        </div>
                    </div>
                </div>
                <div class="row contenedort mb-4">
                    <div class="row g-3">
                        <div class="form-group col-md-6">
                            <span class="fw-bold">Nombre</span>
                            <div class="form-control-plaintext"><?= $dataProducto[0]["nompro"] ?? ""; ?></div>
                        </div>
                        <div class="form-group col-md-6">
                            <span class="fw-bold">Descripción Producto</span>
                            <div class="form-control-plaintext"><?= $dataProducto[0]["descriprod"] ?? ""; ?></div>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <span class="fw-bold">Tipo de Crédito</span>
                            <div class="form-control-plaintext"><?= ($status) ? tip_cre_peri($datos[0]["CtipCre"]) : "" ?></div>
                        </div>
                        <div class="col-md-6">
                            <span class="fw-bold">Tipo de Periodo</span>
                            <div class="form-control-plaintext"><?= ($status) ? tip_cre_peri($datos[0]["NtipPerC"]) : "" ?></div>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label for="tip_doc" class="fw-bold">Tipo de desembolso</label>
                            <select class="form-select" id="tipo_desembolso" aria-label="Tipo de desembolso" onchange="showhide(this.value); showopp(this.value);">
                                <option selected value="1">Efectivo</option>
                                <option value="2">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="grupind" style="display:none;">
                            <label for="tip_doc" class="fw-bold">Tipo de cheque</label>
                            <select class="form-select" id="tipo_cheque" aria-label="Tipo de cheque" onchange="showgrup(this.value); buscar_cargos();">
                                <option selected value="1">Cheques Individuales</option>
                                <option value="2">Un Cheque Grupal</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row contenedort mb-4" style="display:none;" id="region_cheque">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="bancoid" class="fw-bold">Banco</label>
                            <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                <option value="0" selected disabled>Seleccione un Banco</option>
                                <?php
                                if ($status) {
                                    foreach ($bancos as $banco) {
                                        echo '<option  value="' . $banco['id'] . '">' . $banco['id'] . " - " . $banco['nombre'] . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="cuentaid" class="fw-bold">No. de Cuenta</label>
                            <select class="form-select" id="cuentaid">
                                <option value="0">Seleccione una cuenta</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row contenedort mb-4" style="display:none;" id="region_grupo">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="cargoid" class="fw-bold">A nombre de quien sale el cheque</label>
                            <select class="form-select" id="cargoid" onchange="cconcepto();">
                                <option value="0">Seleccione un cargo</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="conceptogrupal" class="fw-bold">Concepto</label>
                            <textarea class="form-control" rows="1" placeholder="Concepto" id="conceptogrupal">DESEMBOLSO GRUPAL </textarea>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <label for="nocheqgrupal" class="fw-bold">No. de Cheque</label>
                            <input type="number" class="form-control" id="nocheqgrupal" value="0" placeholder="No. cheque">
                        </div>
                        <div class="col-md-3">
                            <label for="montogrupal" class="fw-bold">Monto Final a desembolsar</label>
                            <input type="number" value="<?= $totalNetoDesembolso ?? 0 ?>" class="form-control" id="montogrupal" disabled>
                        </div>
                    </div>
                </div>
                <div class="row contenedort mb-4 p-3 rounded-3" style="background:rgba(255,255,255,0.7); box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-user-group me-2"></i>Clientes del Grupo</h5>
                    <div class="accordion" id="cuotas">
                        <?php
                        if ($status) {
                            // $j = 0;
                            foreach ($datos as $j => $dato) :
                                $ccodta = $dato["CCODCTA"];
                                $estado = $dato["Cestado"];
                                $codcli = $dato["idcod_cliente"];
                                $name = $dato["short_name"];
                                $fecnac = setdatefrench($dato["date_birth"]);
                                $dpi = $dato["no_identifica"];
                                $monsol = $dato["MontoSol"];
                                $monsug = $dato["MonSug"];
                                $idit = "data" . $j;
                        ?>
                                <div class="accordion-item mb-2 rounded-3 border-secondary shadow-sm">
                                    <h2 class="accordion-header" id="heading<?= $j ?>">
                                        <button id="<?php echo 'bt' . $j; ?>" class="accordion-button collapsed bg-white rounded-3" data-bs-toggle="collapse" data-bs-target="#<?php echo $idit; ?>" aria-expanded="false" aria-controls="<?php echo $idit; ?>">
                                            <div class="row w-100 align-items-center" style="font-size: 0.95rem;">
                                                <div class="col-md-3">
                                                    <span class="input-group-addon fw-bold"><?= $ccodta; ?></span>
                                                    <input id="<?php echo 'ccodcta' . $j; ?>" type="text" value="<?php echo $ccodta; ?>" hidden>
                                                    <span class="input-group-addon ms-2"><?= strtoupper($name); ?></span>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="input-group-addon fw-bold">Monto Aprobado</label>
                                                    <input id="<?php echo 'monapr' . $j; ?>" type="number" step="0.01" class="form-control" placeholder="000.00" value="<?php echo  $monsug; ?>" disabled>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="input-group-addon fw-bold">Descuentos</label>
                                                    <input id="<?php echo 'mondesc' . $j; ?>" type="number" step="0.01" class="form-control" placeholder="000.00" value="<?= $dato['totalDescuentos']; ?>" disabled>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="input-group-addon fw-bold">A Entregar</label>
                                                    <input id="<?php echo 'monentrega' . $j; ?>" type="number" step="0.01" class="form-control" placeholder="000.00" value="<?= $dato['netoDesembolso']; ?>" disabled>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="<?php echo $idit; ?>" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                        <div class="accordion-body bg-white rounded-bottom-3">
                                            <div class="row mb-3">
                                                <div class="col-md-9">
                                                    <label for="glosa" class="fw-bold">Concepto</label>
                                                    <textarea class="form-control" id="<?php echo 'glosa' . $j; ?>" rows="1" placeholder="Concepto">DESEMBOLSO DE CRÉDITO A NOMBRE DE <?php echo strtoupper($name); ?></textarea>
                                                </div>
                                                <div class="col-md-3 classchq grup2" id="divcheque" style="display: none;">
                                                    <label for="numcheque" class="fw-bold">No. de Cheque</label>
                                                    <input type="number" class="form-control" id="<?php echo 'numcheque' . $j; ?>" placeholder="No. cheque">
                                                </div>
                                            </div>
                                            <div class="row mb-3" style="font-size: 0.90rem;">
                                                <?php if ($dato['cuentasAnteriores'] ?? false): ?>
                                                    <div class="alert alert-info" role="alert">
                                                        <h6 class="alert-heading fw-bold">Refinanciamiento</h6>
                                                        <p class="mb-0">El cliente tiene cuentas anteriores que se pueden liquidar con el desembolso actual.</p>
                                                    </div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered align-middle mb-0" style="width: 100% !important;" id="tb_refinance_<?= $ccodta; ?>" data-account="<?= $ccodta; ?>">
                                                            <thead style="background-color: #f8f9fa;">
                                                                <tr style="font-size: 0.92rem;">
                                                                    <th scope="col" style="width: 2rem; padding: 0.3rem 0.2rem;">#</th>
                                                                    <th scope="col" style="padding: 0.3rem 0.5rem;">Cuenta</th>
                                                                    <th scope="col" style="padding: 0.3rem 0.5rem;">Monto</th>
                                                                    <th scope="col" style="width: 12rem; padding: 0.3rem 0.5rem;">Saldo</th>
                                                                    <th scope="col" style="width: 12rem; padding: 0.3rem 0.5rem;">Interes</th>
                                                                    <th scope="col" style="padding: 0.3rem 0.5rem;">Gasto</th>
                                                                    <th scope="col" style="width: 1rem; padding: 0.3rem 0.2rem;">Usar</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $hoy = date("Y-m-d");
                                                                foreach ($dato['cuentasAnteriores'] as $key => $cuenta) {
                                                                    $fecult = ($cuenta['fecult'] == "-") ? $cuenta['fechaDesembolso'] : $cuenta['fecult'];
                                                                    $fecult = (($fecult) > ($hoy)) ? $hoy : $fecult;
                                                                    $intapro = $cuenta['intapro'];
                                                                    $saldo = round($cuenta['NCapDes'] - $cuenta['pagadokp'], 2);
                                                                    $diasdif = dias_dif($fecult, $hoy);

                                                                    $intpen = $saldo * $intapro / 100 / 365 * $diasdif;

                                                                    $intpen = round($intpen, 2);
                                                                    $intpen = ($intpen < 0) ? 0 : $intpen;
                                                                    echo '<tr style="font-size: 0.90rem;">
                                                                        <td>' . ($key + 1) . '</td>                                                            
                                                                        <td>' . $cuenta['CCODCTA'] . '</td>
                                                                        <td>' . moneda($cuenta['NCapDes']) . '</td>
                                                                        <td>' . moneda($saldo) . '</td>                                                            
                                                                        <td> <input type="number" onblur="calculateExpense(`' . $ccodta . '`)" name="interes_1" class="form-control" step="0.01" min="0" value="' . $intpen . '" style="font-size: 0.8rem;"> </td>';
                                                                    echo '<td>';
                                                                    echo '<select class="form-select" id="select_nomenclatura_' . $key . '">';
                                                                    echo '<option value="" selected disabled>Seleccione</option>';
                                                                    foreach ($forRefinance as $fr) {
                                                                        echo '<option value="' . $fr['idGasto'] . '" data-nomenclatura="' . $fr['id_nomenclatura'] . '">' . $fr['nombre_gasto'] . '</option>';
                                                                    }
                                                                    echo '</select>';
                                                                    echo '</td> ';
                                                                    echo '<td class="align-middle">
                                                                        <input class="form-check-input ms-3 S" type="checkbox" onchange="calculateExpense(`' . $ccodta . '`, this)" value="' . $cuenta['CCODCTA'] . '">
                                                                    </td>';
                                                                    echo '</tr>';
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="row mb-3" style="font-size: 0.90rem;">
                                                <div class="card border-primary shadow-sm rounded-3">
                                                    <div class="card-header bg-secondary text-white py-2 px-3 rounded-top-3">
                                                        <h6 class="fw-bold mb-0" style="font-size: 1rem;">Detalle de Descuentos</h6>
                                                    </div>
                                                    <div class="card-body p-2">
                                                        <div class="table-responsive">
                                                            <table id="tabla_gastos_desembolso_<?= $ccodta; ?>" data-account="<?= $ccodta; ?>" class="table table-sm table-bordered align-middle mb-0" style="width: 100% !important;">
                                                                <thead style="background-color: #f8f9fa;">
                                                                    <tr style="font-size: 0.92rem;">
                                                                        <th scope="col" style="width: 2rem; padding: 0.3rem 0.2rem;">#</th>
                                                                        <th scope="col" style="width: 0.5rem; padding: 0.3rem 0.2rem;"></th>
                                                                        <th scope="col" style="padding: 0.3rem 0.5rem;">Descripción</th>
                                                                        <th scope="col" style="width: 12rem; padding: 0.3rem 0.5rem;">Monto</th>
                                                                        <th scope="col" style="width: 1rem; padding: 0.3rem 0.2rem;"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="<?php echo 'body_gastos_desembolso' . $j; ?>">
                                                                    <?php
                                                                    if (!empty($dato['descuentos'])) {
                                                                        foreach ($dato['descuentos'] as $key => $descuento) {
                                                                            if ($descuento['afecta_modulo'] != 3) {
                                                                                echo '<tr style="font-size: 0.90rem;">
                                                                                    <td>' . ($key + 1) . '</td>
                                                                                    <td>
                                                                                        <input type="number" id="idg_' . $ccodta . '" min="0" value="' . $descuento['id'] . '" hidden>
                                                                                    </td>
                                                                                    <td>' . $descuento['nombre_gasto'] . '</td>
                                                                                    <td>
                                                                                        <input type="number" class="form-control" onblur="calculateExpense(`' . $ccodta . '`)" style="font-size: 0.8rem;"
                                                                                        id="mon_' . $ccodta . '" min="0" step="0.01" value="' . $descuento['mongas'] . '">
                                                                                    </td>
                                                                                    <td>
                                                                                        <input type="number" id="con_' . $ccodta . '" min="0" value="' . $descuento['id_nomenclatura'] . '" hidden>
                                                                                    </td>
                                                                                </tr>';
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
                                            <div class="row mb-3" style="font-size: 0.95rem;">
                                                <div class="card border-primary shadow-sm rounded-3">
                                                    <div class="card-header bg-secondary text-white py-2 px-3 rounded-top-3 d-flex align-items-center">
                                                        <i class="fa-solid fa-link me-2"></i>
                                                        <h6 class="fw-bold mb-0" style="font-size: 1rem;">Vincular Cuenta</h6>
                                                    </div>
                                                    <div class="card-body p-3">
                                                        <div class="row g-3 align-items-center" id="vinculacion_section_<?= $ccodta; ?>" data-account="<?= $ccodta; ?>">
                                                            <span>Seleccione un tipo de vinculacion</span>
                                                            <div class="col-md-5">
                                                                <label for="vinculacion<?= $j; ?>" class="fw-bold mb-1">Tipo de Vinculación</label>
                                                                <select class="form-select" id="vinculacion<?= $j; ?>" onchange="loadAccount('<?= $codcli ?>',this.value);">
                                                                    <option value="0" selected>OMITIR VINCULACION</option>
                                                                    <?php
                                                                    if (!empty($forVinculo)) {
                                                                        foreach ($forVinculo as $vinculo) {
                                                                            echo '<option value="' . $vinculo['afecta_modulo'] . '"  data-value="' . $vinculo['id'] . '">' . htmlspecialchars($vinculo['nombre_gasto']) . '</option>';
                                                                        }
                                                                    }
                                                                    ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-7" id="div_vinculacion_cuentas_<?= $codcli; ?>" style="display: none;">
                                                                <label for="vinculacion_cuentas_<?= $codcli; ?>" class="fw-bold mb-1">Cuenta a Vincular</label>
                                                                <select name="vinculacion_cuentas" id="vinculacion_cuentas_<?= $codcli; ?>" class="form-select">
                                                                    <option value="0" selected disabled>Seleccione una cuenta</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php
                            endforeach;
                        }
                        ?>
                    </div>
                </div>
                <?= $csrf->getTokenField(); ?>
                <div class="row justify-content-center mt-4">
                    <div class="col-auto" id="modal_footer">
                        <?php
                        if ($status) { ?>
                            <button type="button" class="btn btn-success px-4 me-2" onclick="saveDesembolso(<?= count($datos) ?>, <?= $extra ?>, <?= $numciclo ?>)">
                                <i class="fa fa-floppy-disk"></i> Desembolsar Créditos
                            </button>
                        <?php  } else {
                            echo '<div style="display:none;" id="divcheque"></div>';
                        } ?>
                        <button type="button" class="btn btn-outline-danger px-4" onclick="printdiv2(' #cuadro', '0' )">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            function calculateExpense(account, checkboxChanged = null) {
                let tableSelector = `#tb_refinance_${account}`;
                let table = document.querySelector(tableSelector);

                // Calcular total de refinanciamiento
                let totalRefinance = 0;
                let totalInteres = 0;

                if (table) {
                    let rows = table.querySelectorAll('tbody tr');
                    rows.forEach((row, index) => {
                        let checkbox = row.querySelector('input[type="checkbox"]');
                        if (checkbox && checkbox.checked) {
                            let saldoText = row.cells[3].innerText;
                            let monto = parseFloat(saldoText.replace(/[^0-9.-]+/g, "")) || 0;
                            let interesInput = row.querySelector('input[name^="interes_"]');
                            let interes = interesInput ? parseFloat(interesInput.value) || 0 : 0;
                            totalRefinance += monto;
                            totalInteres += interes;
                        }
                    });
                }

                // Calcular total de gastos de desembolso
                let totalGastos = 0;
                let gastosTable = document.querySelector(`#tabla_gastos_desembolso_${account}`);

                if (gastosTable) {
                    let gastosRows = gastosTable.querySelectorAll('tbody tr');
                    gastosRows.forEach(row => {
                        let montoInput = row.querySelector('input[id^="mon_"]');
                        if (montoInput) {
                            let monto = parseFloat(montoInput.value) || 0;
                            totalGastos += monto;
                        }
                    });
                }

                // Sumar todos los descuentos
                let totalDescuentos = totalRefinance + totalInteres + totalGastos;

                // console.log(`Refinanciamiento: ${totalRefinance.toFixed(2)}`);
                // console.log(`Intereses: ${totalInteres.toFixed(2)}`);
                // console.log(`Gastos: ${totalGastos.toFixed(2)}`);
                // console.log(`Total descuentos: ${totalDescuentos.toFixed(2)}`);

                // Buscar el índice de la cuenta actual
                let ccodctaInputs = document.querySelectorAll('input[id^="ccodcta"]');
                let accountIndex = Array.from(ccodctaInputs).findIndex(input => input.value === account);

                if (accountIndex !== -1) {
                    let monaprInput = document.getElementById(`monapr${accountIndex}`);
                    let descuentoInput = document.getElementById(`mondesc${accountIndex}`);
                    let monentregaInput = document.getElementById(`monentrega${accountIndex}`);

                    if (monaprInput && descuentoInput && monentregaInput) {
                        let montoAprobado = parseFloat(monaprInput.value) || 0;

                        // Validar que los descuentos no superen el monto aprobado
                        if (totalDescuentos > montoAprobado) {
                            // Mostrar mensaje de error
                            Swal.fire({
                                icon: 'error',
                                title: '¡Atención!',
                                text: `Los descuentos (Q${totalDescuentos.toFixed(2)}) superan el monto aprobado (Q${montoAprobado.toFixed(2)}) para la cuenta ${account}`,
                                confirmButtonText: 'Entendido'
                            });

                            // Marcar los campos con color de error
                            descuentoInput.classList.add('is-invalid');
                            monentregaInput.classList.add('is-invalid');

                            if (checkboxChanged) {
                                checkboxChanged.checked = false;
                            }

                            return; // Salir de la función sin actualizar
                        } else {
                            // Remover clases de error si las había
                            descuentoInput.classList.remove('is-invalid');
                            monentregaInput.classList.remove('is-invalid');
                        }

                        // Actualizar los valores
                        descuentoInput.value = totalDescuentos.toFixed(2);

                        let nuevoEntrega = (montoAprobado - totalDescuentos).toFixed(2);
                        monentregaInput.value = nuevoEntrega;

                        // Actualizar el monto grupal si existe
                        updateMontoGrupal();

                        // console.log(`Monto a entregar actualizado: Q${nuevoEntrega}`);
                    }
                } else {
                    // console.error(`❌ No se encontró índice para cuenta: ${account}`);
                }
            }

            // Función auxiliar para actualizar el monto grupal
            function updateMontoGrupal() {
                let montoGrupalInput = document.getElementById('montogrupal');
                if (montoGrupalInput) {
                    let totalGrupal = 0;
                    let monentregaInputs = document.querySelectorAll('input[id^="monentrega"]');

                    monentregaInputs.forEach(input => {
                        let valor = parseFloat(input.value) || 0;
                        totalGrupal += valor;
                    });

                    montoGrupalInput.value = totalGrupal.toFixed(2);
                }
            }

            function saveDesembolso(cant, idgrup, ciclo) {
                // Recoger los datos generales como objeto clave:valor
                var datosGenerales = {};
                [
                    'tipo_desembolso',
                    'bancoid',
                    'cuentaid',
                    'conceptogrupal',
                    'nocheqgrupal',
                    'montogrupal',
                    'tipo_cheque',
                    'cargoid'
                ].forEach(function(id) {
                    var el = document.getElementById(id);
                    datosGenerales[id] = el ? el.value : null;
                });

                datosGenerales.accounts = [];

                var i = 0;
                for (i = 0; i < cant; i++) {
                    let accountData = {
                        ccodcta: document.getElementById('ccodcta' + i).value,
                        glosa: document.getElementById('glosa' + i) ? document.getElementById('glosa' + i).value : "",
                        numcheque: document.getElementById('numcheque' + i) ? document.getElementById('numcheque' + i).value : "",
                        monapr: document.getElementById('monapr' + i) ? document.getElementById('monapr' + i).value : "",
                        mondesc: document.getElementById('mondesc' + i) ? document.getElementById('mondesc' + i).value : ""
                    };

                    let refinanciamiento = getRefByAccount(accountData.ccodcta);
                    let descuentos = getDescuentosByAccount(accountData.ccodcta);
                    let vinculacion = getVinculacionesByAccount(accountData.ccodcta);

                    // Agregar los datos al objeto accountData
                    accountData.refinanciamiento = refinanciamiento;
                    accountData.descuentos = descuentos;
                    accountData.vinculacion = vinculacion;

                    datosGenerales.accounts.push(accountData);
                }

                // console.log(datosGenerales);

                // generico(datosGenerales, 0, 0, 'desemgrupal', 0, [idgrup, ciclo], 'crud_credito');
                obtiene(['csrf_token'], [], [], 'desemgrupal', 0, [idgrup, ciclo, datosGenerales], function(response) {
                    printdiv("comprobantechq", "#cuadro", "grup001", [
                        idgrup,
                        ciclo,
                        response.idsDiario,
                        response.porcheque,
                        response.tipoCheque
                    ]);
                }, 'Esta seguro de realizar el desembolso?');
            }

            function getRefByAccount(account) {
                let table = document.querySelector(`#tb_refinance_${account}`);
                if (!table) return [];
                let rows = table.querySelectorAll('tbody tr');
                let refinances = [];
                rows.forEach(row => {
                    let checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox && checkbox.checked) {
                        let cuenta = checkbox.value;
                        let monto = parseFloat(row.cells[3].innerText.replace(/[^0-9.-]+/g, "")) || 0;
                        let interesInput = row.querySelector('input[name^="interes_"]');
                        let interes = interesInput ? parseFloat(interesInput.value) || 0 : 0;
                        let nomenclaturaSelect = row.querySelector('select[id^="select_nomenclatura_"]');
                        let idGasto = nomenclaturaSelect ? parseInt(nomenclaturaSelect.value) || 0 : 0;
                        let idNomenclatura = nomenclaturaSelect ? parseInt(nomenclaturaSelect.options[nomenclaturaSelect.selectedIndex].getAttribute('data-nomenclatura')) || 0 : 0;
                        refinances.push({
                            cuenta: cuenta,
                            monto: monto,
                            interes: interes,
                            idGasto: idGasto,
                            idNomenclatura: idNomenclatura
                        });
                    }
                });
                return refinances;
            }

            function getDescuentosByAccount(account) {
                let table = document.querySelector(`#tabla_gastos_desembolso_${account}`);
                if (!table) return [];
                let rows = table.querySelectorAll('tbody tr');
                let gastos = [];
                rows.forEach(row => {
                    let idgInput = row.querySelector(`input[id^="idg_"]`);
                    let monInput = row.querySelector(`input[id^="mon_"]`);
                    let conInput = row.querySelector(`input[id^="con_"]`);
                    if (idgInput && monInput && conInput) {
                        let idg = parseInt(idgInput.value) || 0;
                        let mon = parseFloat(monInput.value) || 0;
                        let con = parseInt(conInput.value) || 0;
                        gastos.push({
                            id_gasto: idg,
                            monto: mon,
                            id_nomenclatura: con
                        });
                    }
                });
                return gastos;
            }

            function getVinculacionesByAccount(account) {
                let section = document.querySelector(`#vinculacion_section_${account}`);
                if (!section) return null;
                let tipoSelect = section.querySelector('select[id^="vinculacion"]');
                let cuentaSelect = section.querySelector('select[id^="vinculacion_cuentas_"]');
                let idVinculacion = tipoSelect ? tipoSelect.options[tipoSelect.selectedIndex].getAttribute('data-value') : null;
                if (tipoSelect && cuentaSelect && tipoSelect.value && cuentaSelect.value) {
                    return {
                        tipo: parseInt(tipoSelect.value) || 0,
                        cuenta: cuentaSelect.value,
                        id: idVinculacion
                    };
                }
                return null;
            }

            function loadAccount(codCliente, modulo) {
                if (modulo == 0) {
                    document.getElementById('div_vinculacion_cuentas_' + codCliente).style.display = 'none';
                    return;
                }
                generico(0, 0, 0, 'loadAccount', 1, [codCliente, modulo], 'crud_credito', function(response) {
                    // console.log(response);
                    if (response.cuentas) {
                        document.getElementById('div_vinculacion_cuentas_' + codCliente).style.display = 'block';
                        let select = document.getElementById('vinculacion_cuentas_' + codCliente);
                        select.innerHTML = '<option value="0" selected disabled>Seleccione una cuenta</option>';
                        response.cuentas.forEach(function(account) {
                            let option = document.createElement('option');
                            option.value = account.ccodaho;
                            option.text = account.ccodaho + ' - ' + account.nombre;
                            select.appendChild(option);
                        });
                    } else {
                        // console.error("Error loading accounts: " + response[0]);
                    }
                });
            }
        </script>
    <?php
        break;
    case 'comprobantechq':
        $datpost = $_POST["xtra"];

        // echo "<pre>";
        // echo print_r($datpost);
        // echo "</pre>";


        $idGrupo = $datpost[0];
        $ciclo = $datpost[1];
        $idchq = $datpost[2];
        $porcheque = $datpost[3];
        $tipoCheque = $datpost[4];

        $query = "SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, cre.NCapDes,
                    cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.MonSug,cre.DFecDsbls
                    From cremcre_meta cre
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                    INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
                    WHERE cre.TipoEnti='GRUP' AND cre.CESTADO='F' AND cre.CCodGrupo=? AND cre.NCiclo=? ORDER BY cre.CCODCTA;";

        $showmensaje = false;
        try {
            $database->openConnection();
            $datos = $database->getAllResults($query, [$idGrupo, $ciclo]);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron datos del grupo");
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
        <input type="text" readonly hidden value='comprobantechq' id='condi'>
        <input type="text" readonly hidden value='grup001' id='file'>
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>IMPRESION DE COMPROBANTES DE DESEMBOLSOS DE CREDITOS GRUPAL</h4>
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
                                <input type="number" class="form-control" id="nciclo" readonly value="<?= ($status) ? $datos[0]["NCiclo"] : 0; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center">
                            <?php if ($porcheque == 1 && $tipoCheque == 2) {
                                echo '<button id="chq0" type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[' . $idchq[0] . ']], `pdf`, `13`,0,1); hidee(0)">
                                        <i class="fa-solid fa-money-check-dollar"></i></i>Cheque Grupal
                                    </button>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php if ($status) {
                        echo 'ESTADO: DESEMBOLSADOS';
                    }
                    ?>
                </div>
                <div class="row contenedort" style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
                    <h5>CLIENTES DEL GRUPO</h5>
                    <div class="accordion" id="cuotas">
                        <?php
                        if ($status) {
                            $j = 0;
                            while ($j < count($datos)) {
                                $ccodta = $datos[$j]["CCODCTA"];
                                $estado = $datos[$j]["Cestado"];
                                $codcli = $datos[$j]["idcod_cliente"];
                                $name = $datos[$j]["short_name"];
                                $fecnac = date("d-m-Y", strtotime($datos[$j]["date_birth"]));
                                $dpi = $datos[$j]["no_identifica"];
                                $monsug = $datos[$j]["MonSug"];
                                $mondes = $datos[$j]["NCapDes"];
                                $idit = "data" . $j;

                        ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <div class="row">
                                            <div class="col-12">
                                                <div id="<?= 'bt' . $j ?>" class="accordion-button collapsed" aria-expanded="true">
                                                    <div class="row" style="width:100%;font-size: 0.90rem;">
                                                        <div class="col-sm-3">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <span class="input-group-addon"><?= $ccodta; ?></span>
                                                                </div>
                                                                <div class="col-12">
                                                                    <input id="<?= 'ccodcta' . $j ?>" type="text" value="<?php echo $ccodta; ?>" hidden>
                                                                    <span class="input-group-addon"><?= strtoupper($name) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <label class="input-group-addon fw-bold">Monto Desembolsado</label>
                                                            <input id="<?php echo 'monapr' . $j; ?>" type="number" step="0.01" class="form-control" placeholder="000.00" value="<?php echo  $mondes; ?>" disabled>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="row justify-items-md-center">
                                                                <div class="col align-items-center">
                                                                    <button type="button" class="btn btn-outline-primary" onclick="reportes([[], [], [], ['<?= $ccodta ?>']], 'pdf', '18', 0,1);">
                                                                        <i class="fa-solid fa-dog"></i> Nota de Desembolso
                                                                    </button>
                                                                    <?php if ($porcheque == 1 && $tipoCheque == 1) {
                                                                        echo '<button id="chq' . $j . '" type="button" class="btn btn-outline-success" onclick="reportes([[],[],[],[' . $idchq[$j] . ']], `pdf`, `13`,0,1); hidee(' . $j . ')">
                                                                                <i class="fa-solid fa-money-check-dollar"></i></i>Cheque
                                                                            </button>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </h2>
                                    <div id="<?= $idit; ?>" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                        <div class="accordion-body">
                                        </div>
                                    </div>
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
                    <button type="button" class="btn btn-outline-danger" onclick="window.location.reload();">
                        <i class="fa-solid fa-circle-xmark"></i> Salir
                    </button>
                </div>
            </div>
        </div>
        <script>
            function hidee(id) {
                document.getElementById("chq" + id).style.display = "none";
            }
        </script>

<?php
        break;
} ?>