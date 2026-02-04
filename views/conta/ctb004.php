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
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
//++++++++++++++++++++++++++++

use PhpOffice\PhpSpreadsheet\Calculation\Logical\Conditional;

// include __DIR__ . '/../../includes/Config/database.php';
// $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
// include '../../src/funcphp/func_gen.php';
// require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
// date_default_timezone_set('America/Guatemala');
// $idusuario = $_SESSION['id'];
// $idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];

switch ($condi) {
    case 'libdiario':
?>
        <!--AHO-4-Clclintrs Cuenta Ahorros-->
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="libdiario" style="display: none;">
        <div class="text" style="text-align:center">GENERACION DE LIBRO DIARIO</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="row">
                            <?php

                            //   <!-- --REQ--crediprendas--1--restrinccion de reportes -->
                            $query = "SELECT id_usu, puesto, id_agencia
                                FROM tb_usuario
                                WHERE id_usu = '$idusuario'";
                            $resultado = mysqli_query($conexion, $query);

                            $puestosP = array("ADM", "GER", "AUD", "CNT");
                            if ($resultado) {
                                $fila = mysqli_fetch_assoc($resultado);

                                // Verificar si la fila existe 
                                $mostrarTodo = ($fila && in_array($fila['puesto'], $puestosP));
                            ?>
                                <div class="col-sm-12">
                                    <?php if ($mostrarTodo) : ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi" value="allofi"
                                                onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado</label>
                                        </div>
                                    <?php endif; ?>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ragencia" id="anyofi" value="anyofi"
                                            checked onclick="changedisabled(`#codofi`,1)">
                                        <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                        </div>
                    </div>
                    <div class="col-sm-6">
                        <span class="input-group-addon col-2">Agencia</span>
                        <?php
                        $sql = "SELECT id_usu, puesto, id_agencia
                                     FROM tb_usuario
                                     WHERE id_usu = '$idusuario'";
                        $resultado = mysqli_query($conexion, $sql);
                        if ($resultado) {
                            $fila = mysqli_fetch_assoc($resultado);
                            if ($fila) {
                                $puestosP = array("ADM", "GER", "AUD", "CNT");
                                if (in_array($fila['puesto'], $puestosP)) {
                                    //permisos v
                        ?>
                                    <select class="form-select" id="codofi" style="max-width: 70%;">
                                        <?php
                                        $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                        ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                        while ($ofi = mysqli_fetch_array($ofis)) {
                                            echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                <?php
                                } else {
                                    //caso contario
                                ?>

                                    <select class="form-select" id="codofi" style="max-width: 70%;">
                                        <?php
                                        $ofis2 = mysqli_query($conexion, "SELECT usu.id_agencia, ofi.cod_agenc, ofi.nom_agencia
                                                                      FROM tb_usuario AS usu
                                                                      INNER JOIN tb_agencia AS ofi ON ofi.id_agencia = usu.id_agencia
                                                                     WHERE usu.id_usu = '$idusuario'");

                                        $filaOfis2 = mysqli_fetch_assoc($ofis2);

                                        echo '<option value="' . $filaOfis2['id_agencia'] . '" selected>' . $filaOfis2['cod_agenc'] . " - " . $filaOfis2['nom_agencia'] . '</option>';
                                        ?>
                                    </select>



                        <?php
                                }
                            } else {
                                echo "No se encontraron resultados para el usuario con ID: $idusuario";
                            }
                        } else {
                            echo "Error en la consulta: " . mysqli_error($conexion);
                        }

                        ?>


                        <!-- codigo anterior -->
                        <!-- <select class="form-select" id="codofi" style="max-width: 70%;" disabled> -->
                        <?php
                        /* $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                    ON ofi.id_agencia = usu.id_agencia WHERE usu.id_usu=" . $idusuario . ""); */
                        // $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                        // ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                        // while ($ofi = mysqli_fetch_array($ofis)) {
                        //     echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                        // }
                        ?>
                        <!-- </select> -->

                    </div>
                    <div class="col-sm-6 g-4" style="display:none;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="radio" role="switch" name="rtipo" id="c" value="c" onclick="">
                            <input style="display: none;" class="form-check-input" type="radio" role="switch" name="rtipo"
                                id="n" value="n" checked>
                            <label class="form-check-label" for="c">Libro Diario Concentrado</label>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
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
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfechas" id="ftodo" value="ftodo"
                                                onclick="changedisabled(`#filfechas *`,0)">
                                            <label for="ftodo" class="form-check-label">Todo</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfechas" id="frango" checked
                                                value="frango" onclick="changedisabled(`#filfechas *`,1)">
                                            <label for="frango" class="form-check-label">Rango</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Libro Diario en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rtipo`,`rfondos`,`rfechas`,`ragencia`],[<?php echo $idusuario; ?>]],`pdf`,36,0,1)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <!-- <button type="button" class="btn btn-outline-danger" title="Libro Diario en pdf" onclick="generarPDF()">
                            <i class="fa-solid fa-file-pdf"></i> Pdfss
                        </button>
                        <button type="button" class="btn btn-outline-danger" title="Libro Diario en pdf" onclick="generarPDF2()">
                            <i class="fa-solid fa-file-pdf"></i> Pdfss descargar
                        </button> -->
                        <button type="button" class="btn btn-outline-success" title="Libro Diario en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rtipo`,`rfondos`,`rfechas`,`ragencia`],[<?php echo $idusuario; ?>]],`xlsx`,`libro_diario_main`,1)">
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
            function generarPDF() {
                // Redireccionar a un script PHP que generará el PDF
                window.location.href = 'conta/reportes/prueba.php';
            }

            function generarPDF2() {
                // Redireccionar a un script PHP que generará el PDF
                window.location.href = 'conta/reportes/prueba2.php';
            }
        </script>
    <?php
        break;
    case 'libmayor':

        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (24) AND id_usuario=? AND estado=1", [$idusuario]);
            $permisoConsolidado = new PermissionHandler($permisos, 24);

            $condicion = ($permisoConsolidado->isLow()) ? "id_agencia=?" : "";
            $params = ($permisoConsolidado->isLow()) ? [$idagencia] : [];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'cod_agenc', 'nom_agencia'], $condicion, $params);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias disponibles.");
            }

            $fondoselect = $database->selectColumns('ctb_fuente_fondos', ['id', 'descripcion'], 'estado=1');
            if (empty($fondoselect)) {
                $showmensaje = true;
                throw new Exception("No se encontraron fondos disponibles.");
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
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="libmayor" style="display: none;">
        <div class="text" style="text-align:center">GENERACION DE LIBRO MAYOR</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row container contenedort">
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficina</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <?php if (!$permisoConsolidado->isLow()) : ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                    value="allofi" onclick="changedisabled(`#codofi`,0)">
                                                <label for="allofi" class="form-check-label">Consolidado</label>
                                            </div>
                                        <?php endif; ?>

                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia.</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <option value="0" disabled>Seleccione una agencia</option>
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? 'selected' : '';
                                                echo "<option {$selected} value='{$ofi['id_agencia']}'>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <option value="0" disabled>Seleccione una fuente de fondos</option>
                                                <?php
                                                foreach ($fondoselect as $fon) {
                                                    echo "<option value='{$fon['id']}'>{$fon['descripcion']}</option>";
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
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Cuentas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="allcuen"
                                                value="allcuen" checked onclick="changedisabled(`#btncuenid`,0)">
                                            <label for="allcuen" class="form-check-label">Todas </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="anycuen"
                                                value="anycuen" onclick="changedisabled(`#btncuenid`,1)">
                                            <label for="anycuen" class="form-check-label"> Una cuenta</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <div class="input-group">
                                                <input style="display:none;" type="text" class="form-control" id="idcuenta"
                                                    value="0">
                                                <input type="text" disabled readonly class="form-control" id="cuenta">
                                                <button disabled id="btncuenid" class="btn btn-outline-success" type="button"
                                                    onclick="abrir_modal(`#modal_nomenclatura_enabled`, `show`, `#id_modal_hidden`, `idcuenta,cuenta`)"
                                                    title="Buscar Cuenta contable"><i
                                                        class="fa fa-magnifying-glass"></i></button>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class="col-sm-12">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?= $hoy ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?= $hoy ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Libro Mayor en pdf"
                            onclick="reportes([[`finicio`,`ffin`,`idcuenta`,`cuenta`],[`codofi`,`fondoid`],[`rcuentas`,`rfondos`,`ragencia`],[]],`pdf`,37,0,1)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Libro Mayor en Excel"
                            onclick="reportes([[`finicio`,`ffin`,`idcuenta`,`cuenta`],[`codofi`,`fondoid`],[`rcuentas`,`rfondos`,`ragencia`],[]],`xlsx`,`libro_mayor_main`,1)">
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
        <?php include __DIR__ . "/../../src/cris_modales/mdls_nomenclatura_enabled.php"; ?>

    <?php
        break;
    case 'libcaja':
    ?>
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="libcaja" style="display: none;">
        <div class="text" style="text-align:center">GENERACION DE LIBRO CAJA</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficina</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;">
                                            <option value="0" selected>Consolidado</option>
                                            <?php
                                            $ofis = mysqli_query($conexion, "SELECT id_agencia,cod_agenc,nom_agencia FROM tb_agencia");
                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                echo '<option value="' . $ofi['id_agencia'] . '" >' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- New Filter for Cuentas -->
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Cuentas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="allcuen"
                                                value="allcuen" checked onclick="changedisabled(`#btncuenid`,0)">
                                            <label for="allcuen" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="anycuen"
                                                value="anycuen" onclick="changedisabled(`#btncuenid`,1)">
                                            <label for="anycuen" class="form-check-label"> Una cuenta</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <div class="input-group" style="width:min(70%,32rem);">
                                                <input style="display:none;" type="text" class="form-control" id="idcuenta"
                                                    value="0">
                                                <input type="text" disabled readonly class="form-control" id="cuenta">
                                                <button disabled id="btncuenid" class="btn btn-outline-success" type="button"
                                                    onclick="abrir_modal(`#modal_nomenclatura_enabled`, `show`, `#id_modal_hidden`, `idcuenta,cuenta`)"
                                                    title="Buscar Cuenta contable"><i
                                                        class="fa fa-magnifying-glass"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Finalizacion del Filtro-->
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
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
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Libro Caja en pdf"
                            onclick="reportes([[`finicio`,`ffin`,`idcuenta`],[`codofi`,`fondoid`],[`rfondos`,`rcuentas`],[<?php echo $idusuario; ?>]],`pdf`,`libro_caja`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Libro Caja en Excel"
                            onclick="reportes([[`finicio`,`ffin`,`idcuenta`],[`codofi`,`fondoid`],[`rfondos`,`rcuentas`],[<?php echo $idusuario; ?>]],`xlsx`,`libro_caja`,1)">
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
        <?php include __DIR__ . "/../../src/cris_modales/mdls_cuenta_caja.php"; ?>
    <?php
        break;
    case 'balcomprobacion':
        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (9) AND id_usuario=? AND estado=1", [$idusuario]);
            $permisoConsolidado = new PermissionHandler($permisos, 9);

            $condicion = ($permisoConsolidado->isLow()) ? "id_agencia=?" : "";
            $params = ($permisoConsolidado->isLow()) ? [$idagencia] : [];

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'cod_agenc', 'nom_agencia'], $condicion, $params);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias disponibles.");
            }

            $fondoselect = $database->selectColumns('ctb_fuente_fondos', ['id', 'descripcion'], 'estado=1');
            if (empty($fondoselect)) {
                $showmensaje = true;
                throw new Exception("No se encontraron fondos disponibles.");
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
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="balcomprobacion" style="display: none;">
        <div class="text" style="text-align:center">BALANCE DE COMPROBACION</div>

        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <div class="row container contenedort">
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficina</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <?php if (!$permisoConsolidado->isLow()) : ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                    value="allofi" onclick="changedisabled(`#codofi`,0)">
                                                <label for="allofi" class="form-check-label">Consolidado</label>
                                            </div>
                                        <?php endif; ?>

                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia.</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <option value="0" disabled>Seleccione una agencia</option>
                                            <?php
                                            foreach ($agencias as $ofi) {
                                                $selected = ($ofi['id_agencia'] == $idagencia) ? 'selected' : '';
                                                echo "<option {$selected} value='{$ofi['id_agencia']}'>{$ofi['cod_agenc']} - {$ofi['nom_agencia']}</option>";
                                            }
                                            ?>
                                        </select>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <option value="0" disabled>Seleccione una fuente de fondos</option>
                                                <?php
                                                foreach ($fondoselect as $fon) {
                                                    echo "<option value='{$fon['id']}'>{$fon['descripcion']}</option>";
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
                    <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class="col-sm-12">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?= $hoy ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?= $hoy ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Balance de comprobacion en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`],[]],`pdf`,`balancecom`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Balance de comprobacion en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`],[]],`xlsx`,`balancecom`,1)">
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

    <?php
        break;
    case 'balgen':
        /**
         * Vista para la generación de balance general.
         * 
         * Esta sección del código maneja la conexión a la base de datos para obtener información
         * sobre agencias y fondos disponibles, y verifica los permisos del usuario para acceder
         * a los datos consolidados de todas las agencias.
         * 
         * Variables:
         * - $consolidadoAgencias: Permiso para ver el consolidado de todas las agencias.
         * - $showmensaje: Indicador para mostrar mensajes de error específicos.
         * 
         * Funcionalidad:
         * - Abre una conexión a la base de datos.
         * - Obtiene los permisos del usuario desde la tabla "tb_autorizacion".
         * - Verifica si el usuario tiene permisos bajos y ajusta la consulta de agencias en consecuencia.
         * - Obtiene la lista de agencias disponibles.
         * - Si no se encuentran agencias, lanza una excepción y muestra un mensaje de error.
         * - Obtiene la lista de fondos disponibles.
         * - Si no se encuentran fondos, lanza una excepción.
         * - Maneja excepciones y registra errores si es necesario.
         * - Cierra la conexión a la base de datos en el bloque finally.
         * 
         * Excepciones:
         * - Si no se encuentran agencias disponibles, se lanza una excepción con el mensaje "No se encontraron agencias disponibles."
         * - Si no se encuentran fondos disponibles, se lanza una excepción con el mensaje "No se encontraron fondos disponibles."
         * - En caso de error, se registra el error y se muestra un mensaje al usuario.
         */
        $consolidadoAgencias = 15; //Permiso para ver el consolidado de todas las agencias

        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?) AND id_usuario=? AND estado=1", [$consolidadoAgencias, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $consolidadoAgencias);

            $condi = ($accessHandler->isLow()) ? "id_agencia=?" : "";
            $params = ($accessHandler->isLow()) ? [$idagencia] : [];

            $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "cod_agenc", "nom_agencia"], $condi, $params);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias disponibles.");
            }

            $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondos)) {
                throw new Exception("No se encontraron fondos disponibles.");
            }
            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="balgen" style="display: none;">
        <div class="text" style="text-align:center">GENERACION DE BALANCE GENERAL</div>
        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div>
                        <?= $mensaje ?>
                    </div>
                </div>
            <?php }  ?>
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row m-2">
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check" <?= ($accessHandler->isLow()) ? "hidden" : ""; ?>>
                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                    value="allofi" onclick="changedisabled(`#codofi`,0)">
                                                <label for="allofi" class="form-check-label">Consolidado</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                    value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                                <label for="anyofi" class="form-check-label"> Por Agencia.</label>
                                            </div>
                                        </div>
                                        <div class="col-sm-12">
                                            <span class="input-group-addon col-2">Agencia</span>
                                            <select class="form-select" id="codofi">
                                                <?php
                                                if (!empty($fondos)) {
                                                    foreach ($agencias as $agencia) {
                                                        $selected = ($agencia['id_agencia'] == $idagencia) ? "selected" : "";
                                                        echo "<option {$selected} value='{$agencia['id_agencia']}'>{$agencia['cod_agenc']} - {$agencia['nom_agencia']} </option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                    checked onclick="changedisabled(`#fondoid`,0)">
                                                <label for="allf" class="form-check-label">Todo </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                    onclick="changedisabled(`#fondoid`,1)">
                                                <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="fondoid" disabled>
                                                    <?php
                                                    if (!empty($fondos)) {
                                                        foreach ($fondos as $fondo) {
                                                            echo "<option value='{$fondo['id']}'>{$fondo['descripcion']}</option>";
                                                        }
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
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="nivelinit">
                                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                                        <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>>Nivel <?= $i ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <label class="text-primary" for="nivelinit">INICIO</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="nivelfin">
                                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                                        <option value="<?= $i ?>" <?= $i === 5 ? 'selected' : '' ?>>Nivel <?= $i ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <label class="text-primary" for="nivelfin">FIN</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--Botones-->
                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-primary" title="Balance General"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`],[`rfondos`,`ragencia`],[]],`show`,`balancegen`,0,0,'ccodcta','monto',2,'Montos',0)">
                            <i class="fa-solid fa-eye"></i> Ver
                        </button>
                        <button type="button" class="btn btn-outline-danger" title="Balance General en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`],[`rfondos`,`ragencia`],[]],`pdf`,26,0,1)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Balance General en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`],[`rfondos`,`ragencia`],[]],`xlsx`,`balancegen`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
                        </button>
                        <!-- <button type="button" class="btn btn-outline-danger" title="Balance General en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`],[`rfondos`,`ragencia`],[]],`pdf`,`balancegen_resumen`,0)">
                            <i class="fa-solid fa-file-pdf"></i>Resumen Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Balance General en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`],[`rfondos`,`ragencia`],[]],`xlsx`,`balancegen_resumen`,1)">
                            <i class="fa-solid fa-file-excel"></i>Resumen Excel
                        </button> -->
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
                <div id="divshow" class="container contenedort" style="display: none;">
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-sm small-font">
                            <thead>
                                <tr>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'estresul':

        /**
         * Este script maneja la vista para la generacion de Estado de resultados.
         * 
         * Variables:
         * - $consolidadoAgencias: Permiso para ver el consolidado de todas las agencias.
         * - $showmensaje: Bandera para mostrar mensajes de error específicos.
         * 
         * Proceso:
         * 1. Abre una conexión a la base de datos.
         * 2. Obtiene los permisos del usuario desde la tabla "tb_autorizacion".
         * 3. Crea un manejador de permisos para determinar el nivel de acceso del usuario.
         * 4. Dependiendo del nivel de acceso, establece la condición y parámetros para la consulta de agencias.
         * 5. Obtiene las agencias disponibles desde la tabla "tb_agencia".
         * 6. Si no se encuentran agencias, lanza una excepción y establece la bandera $showmensaje.
         * 7. Obtiene los fondos disponibles desde la tabla "ctb_fuente_fondos".
         * 8. Si no se encuentran fondos, lanza una excepción.
         * 9. Maneja cualquier excepción lanzada, registrando el error y mostrando un mensaje adecuado.
         * 10. Finalmente, cierra la conexión a la base de datos.
         * 
         * Excepciones:
         * - Si no se encuentran agencias disponibles, se lanza una excepción con el mensaje "No se encontraron agencias disponibles."
         * - Si no se encuentran fondos disponibles, se lanza una excepción con el mensaje "No se encontraron fondos disponibles."
         * 
         * Manejo de Errores:
         * - Si ocurre un error y $showmensaje es falso, se registra el error y se muestra un mensaje genérico con un código de error.
         * - Si $showmensaje es verdadero, se muestra el mensaje de error específico.
         */
        $consolidadoAgencias = 15; //Permiso para ver el consolidado de todas las agencias

        $showmensaje = false;
        try {
            $database->openConnection();
            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (?) AND id_usuario=? AND estado=1", [$consolidadoAgencias, $idusuario]);
            $accessHandler = new PermissionHandler($permisos, $consolidadoAgencias);

            $condi = ($accessHandler->isLow()) ? "id_agencia=?" : "";
            $params = ($accessHandler->isLow()) ? [$idagencia] : [];

            $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "cod_agenc", "nom_agencia"], $condi, $params);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias disponibles.");
            }
            $condiAgency = ($accessHandler->isLow()) ? "estado=1" : "estado=1";
            $sectors = $database->selectColumns("ctb_sectores", ["id", "nombre"], $condiAgency);

            $fondos = $database->selectColumns("ctb_fuente_fondos", ["id", "descripcion"], "estado=1");
            if (empty($fondos)) {
                throw new Exception("No se encontraron fondos disponibles.");
            }
            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
        // echo "<pre>";
        // echo print_r($datos);
        // echo "</pre>";
    ?>
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="estresul" style="display: none;">
        <div class="text" style="text-align:center">ESTADO DE RESULTADOS</div>
        <div class="card">
            <?php if (!$status) { ?>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                        <use xlink:href="#exclamation-triangle-fill" />
                    </svg>
                    <div>
                        <?= $mensaje ?>
                    </div>
                </div>
            <?php }  ?>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check" <?= ($accessHandler->isLow()) ? "hidden" : ""; ?>>
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                value="allofi" onclick="changedisabled(`#codofi`,0); changedisabled(`#sector`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                value="anyofi" checked onclick="changedisabled(`#codofi`,1); changedisabled(`#sector`,0);">
                                            <label for="anyofi" class="form-check-label"> Por Agencia.</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi">
                                            <?php
                                            if (!empty($fondos)) {
                                                foreach ($agencias as $agencia) {
                                                    $selected = ($agencia['id_agencia'] == $idagencia) ? "selected" : "";
                                                    echo "<option {$selected} value='{$agencia['id_agencia']}'>{$agencia['cod_agenc']} - {$agencia['nom_agencia']} </option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div id="containerSectores" <?= ($accessHandler->isLow()) ? "hidden" : ""; ?>>
                                        <div class="row">
                                            <div class="col-sm-12 mt-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="ragencia" id="anysector"
                                                        value="anysector" onclick="changedisabled(`#codofi`,0); changedisabled(`#sector`,1);">
                                                    <label for="anysector" class="form-check-label"> Por sector.</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12">
                                            <span class="input-group-addon col-2">Sectores</span>
                                            <select class="form-select" id="sector" disabled>
                                                <?php
                                                if (!empty($sectors)) {
                                                    $isFirts = true;
                                                    foreach ($sectors as $sector) {
                                                        $selected = ($isFirts) ? "selected" : "";
                                                        echo "<option {$selected} value='{$sector['id']}'>{$sector['nombre']}</option>";
                                                        $isFirts = false;
                                                    }
                                                } else {
                                                    echo "<option selected value='0'>Seleccione un sector</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
                                            <label for="anyf" class="form-check-label"> Por Fuente de fondos</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="fondoid" disabled>
                                                <?php
                                                if (!empty($fondos)) {
                                                    foreach ($fondos as $fondo) {
                                                        echo "<option value='{$fondo['id']}'>{$fondo['descripcion']}</option>";
                                                    }
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
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card" style="height: 100%;">
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class="col-sm-12">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class="col-sm-12">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="nivelinit">
                                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>>Nivel <?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <label class="text-primary" for="nivelinit">INICIO</label>
                                        </div>
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="nivelfin">
                                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $i === 5 ? 'selected' : '' ?>>Nivel <?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <label class="text-primary" for="nivelfin">FIN</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-primary" title="Estado de resultados"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`,`sector`],[`rfondos`,`ragencia`],[]],`show`,`estadoresul`,0,0,'ccodcta','monto',2,'Montos',0)">
                            <i class="fa-solid fa-eye"></i> Ver
                        </button>
                        <button type="button" class="btn btn-outline-danger" title="Estado de resultados en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`,`sector`],[`rfondos`,`ragencia`],[]],`pdf`,27,0,1)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Estado de resultados en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`,`sector`],[`rfondos`,`ragencia`],[]],`xlsx`,`estadoresul`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
                        </button>
                        <!-- <button type="button" class="btn btn-outline-danger" title="resumen de resultados en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`,`sector`],[`rfondos`,`ragencia`],[]],`pdf`,27,0,1)">
                            <i class="fa-solid fa-file-pdf"></i> Resumen PDF
                        </button>
                        <button type="button" class="btn btn-outline-success" title="resumen de resultados en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`,`sector`],[`rfondos`,`ragencia`],[]],`xlsx`,`estadoresul`,1)">
                            <i class="fa-solid fa-file-excel"></i>Resumen Excel
                        </button> -->
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
                <div id="divshow" class="container contenedort" style="display: none;">
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-sm small-font">
                            <thead>
                                <tr>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>
        <?php
        break;
    case 'CatalogoCuentas': {
            $id = $_POST["xtra"];
            $usuario = "4";
            $ofi = "002";
        ?>
            <!-- APR_05_LstdCntsActvsDspnbls -->
            <div class="text" style="text-align:center">CATALOGO DE CUENTAS</div>
            <input type="text" value="CatalogoCuentas" id="condi" style="display: none;">
            <input type="text" value="ctb004" id="file" style="display: none;">

            <div class="card">
                <div class="card-header">Catalogo de Cuentas</div>
                <div class="card-body">
                    <!-- segunda linea -->
                    <div class="row d-flex align-items-stretch mb-3">
                        <!-- card para filtrar cuentas -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro de clases</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col d-flex justify-content-center">
                                            <select class="form-select" id="clase" aria-label="Default select example">
                                                <option selected value="0">Todos</option>
                                                <option value="1">1 - Activo</option>
                                                <option value="2">2 - Cuentas regulizadoras de activo</option>
                                                <option value="3">3 - Pasivo</option>
                                                <option value="4">4 - Otras cuentas acreedoras</option>
                                                <option value="5">5 - Capital contable</option>
                                                <option value="6">6 - Productos</option>
                                                <option value="7">7 - Gastos</option>
                                                <option value="8">8 - Contingencias</option>
                                                <option value="9">9 - Cuentas de orden</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- card para seleccionar una cuenta -->
                        <div class="col-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtrar de niveles</b></div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <select class="form-select" id="nivel" aria-label="Default select example">
                                                <option selected value="0">Todos</option>
                                                <option value="1">Nivel 1</option>
                                                <option value="3">Nivel 2</option>
                                                <option value="4">Nivel 3</option>
                                                <option value="6">Nivel 4</option>
                                                <option value="8">Nivel 5</option>
                                                <option value="10">Nivel 6</option>
                                                <option value="12">Nivel 7</option>
                                                <option value="14">Nivel 8</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="row justify-items-md-center">
                        <div class="col align-items-center" id="modal_footer">
                            <!-- en el metodo onclick se envian usuario y oficina para saber las cuentas de agencia a generar -->
                            <button type="button" id="btnSave" class="btn btn-outline-success"
                                onclick="reportes([[],['clase','nivel'],[],[<?php echo $usuario; ?>,'<?php echo $ofi; ?>']], 'xlsx', 'catalogo_cuentas', 1)">
                                <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                            </button>

                            <button type="button" id="btnSave" class="btn btn-outline-primary"
                                onclick="reportes([[],['clase','nivel'],[],['<?php echo $usuario; ?>','<?php echo $ofi; ?>']], 'pdf', 'catalogo_cuentas', 0)">
                                <i class="fa-solid fa-file-pdf"></i> Reporte en PDF
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
    case 'patrimonio':
        ?>
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="patrimonio" style="display: none;">
        <div class="text" style="text-align:center">ESTADO DE CAMBIOS EN EL PATRIMONIO</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficina</div>
                            <div class="card-body">
                                <div class="row">
                                    <?php

                                    //   <!-- --REQ--crediprendas--1--restrinccion de reportes -->
                                    $query = "SELECT id_usu, puesto, id_agencia
                                        FROM tb_usuario
                                        WHERE id_usu = '$idusuario'";
                                    $resultado = mysqli_query($conexion, $query);

                                    $puestosP = array("ADM", "GER", "AUD", "CNT");
                                    if ($resultado) {
                                        $fila = mysqli_fetch_assoc($resultado);

                                        // Verificar si la fila existe 
                                        $mostrarTodo = ($fila && in_array($fila['puesto'], $puestosP));
                                    ?>
                                        <div class="col-sm-12">
                                            <?php if ($mostrarTodo) : ?>


                                                <div class="col-sm-12">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                            value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                        <label for="allofi" class="form-check-label">Consolidado </label>
                                                    </div>

                                                </div>

                                            <?php endif; ?>

                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                    value="anyofi" checked onclick="changedisabled(`#codofi`,1)">
                                                <label for="anyofi" class="form-check-label"> Por Agencia.</label>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                    ?>


                                    <!-- COIDGO ANTERIOR
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi" value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi" value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                        </div>
                                 </div> -->

                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>

                                        <?php
                                        $sql = "SELECT id_usu, puesto, id_agencia
                                     FROM tb_usuario
                                     WHERE id_usu = '$idusuario'";
                                        $resultado = mysqli_query($conexion, $sql);

                                        if ($resultado) {
                                            $fila = mysqli_fetch_assoc($resultado);

                                            if ($fila) {
                                                $puestosP = array("ADM", "GER", "AUD", "CNT");

                                                if (in_array($fila['puesto'], $puestosP)) {
                                                    //permisos v
                                        ?>
                                                    <select class="form-select" id="codofi">
                                                        <?php
                                                        $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                        while ($ofi = mysqli_fetch_array($ofis)) {
                                                            echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                <?php
                                                } else {
                                                    //caso contario
                                                ?>

                                                    <select class="form-select" id="codofi">
                                                        <?php
                                                        $ofis2 = mysqli_query($conexion, "SELECT usu.id_agencia, ofi.cod_agenc, ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi
                                                ON ofi.id_agencia = usu.id_agencia
                                                                     WHERE usu.id_usu = '$idusuario'");

                                                        $filaOfis2 = mysqli_fetch_assoc($ofis2);

                                                        echo '<option value="' . $filaOfis2['id_agencia'] . '" selected>' . $filaOfis2['cod_agenc'] . " - " . $filaOfis2['nom_agencia'] . '</option>';
                                                        ?>
                                                    </select>
                                        <?php
                                                }
                                            } else {
                                                echo "No se encontraron resultados para el usuario con ID: $idusuario";
                                            }
                                        } else {
                                            echo "Error en la consulta: " . mysqli_error($conexion);
                                        }

                                        ?>
                                        <!-- CODIGO ANTERIOR 
                               <select class="form-select" id="codofi" >
                                            <?php
                                            // $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                            //     ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                            // while ($ofi = mysqli_fetch_array($ofis)) {
                                            //     echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            // }
                                            ?>
                               </select> -->

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Estado de cambios en el patrimonio en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`],[`ragencia`],[<?php echo $idusuario; ?>]],`pdf`,44,0,1)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success"
                            title="Estado de cambios en el patrimonio en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`],[`ragencia`],[<?php echo $idusuario; ?>]],`xlsx`,`estado_patrimonio`,1)">
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
    <?php
        break;
    case 'flujo_efectivo':
        $showmensaje = false;
        try {
            $database->openConnection();

            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'cod_agenc', 'nom_agencia']);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias disponibles.");
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
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="flujo_efectivo" style="display: none;">
        <div class="text" style="text-align:center">ESTADO DE FLUJO DE EFECTIVO</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficina</div>
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
                                        <select class="form-select" id="codofi" disabled>
                                            <?php
                                            foreach ($agencias as $agencia) {
                                                $selected = ($agencia['id_agencia'] == $idagencia) ? "selected" : "";
                                                echo "<option {$selected} value='{$agencia['id_agencia']}'>{$agencia['cod_agenc']} - {$agencia['nom_agencia']} </option>";
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
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?= $hoy ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?= $hoy ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Flujo de efectivo en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`],[`ragencia`],[]],`pdf`,45,0,1)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Flujo de efectivo en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`],[`ragencia`],[]],`xlsx`,`flujo_efectivo`,1)">
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
        <!-- <div class="div" id="dataflujo">
        </div>
        <script>
            function loaddata() {
                inputs = getinputsval([`finicio`, `ffin`]);
                selects = getselectsval([`codofi`]);
                radios = getradiosval([`ragencia`]);
                printdiv('dataflujo', '#dataflujo', 'ctb004', [inputs, selects, radios])
            }
        </script> -->

    <?php
        break;
    case 'balcomparativo':
        $hoy = date("Y-m-d");

        // echo ('<pre>');
        // print_r($mesesant);
        // echo ('</pre>');
    ?>

        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="balcomparativo" style="display: none;">
        <div class="text" style="text-align:center">BALANCE COMPARATIVO</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Fecha de balance 1</div>
                            <div class="card-body">
                                <div class="row" id="fechas1">
                                    <div class="col-sm-6">
                                        <label for="finicio1">Desde</label>
                                        <input type="date" class="form-control" id="finicio1" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="ffin1">Hasta</label>
                                        <input type="date" class="form-control" id="ffin1" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Fecha de balance 2</div>
                            <div class="card-body">
                                <div class="row" id="fechas2">
                                    <div class="col-sm-6">
                                        <label for="finicio2">Desde</label>
                                        <input type="date" class="form-control" id="finicio2"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="ffin2">Hasta</label>
                                        <input type="date" class="form-control" id="ffin2" value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Balance Comparativo en Pdf"
                            onclick="reportes([[`finicio1`,`ffin1`,`finicio2`,`ffin2`],[],[],[<?php echo $idusuario; ?>]],`pdf`,`balcomparativo`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Balance Comparativo en Excel"
                            onclick="reportes([[`finicio1`,`ffin1`,`finicio2`,`ffin2`],[],[],[<?php echo $idusuario; ?>]],`xlsx`,`balcomparativo`,1)">
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
    <?php
        break;
    case 'ercomparativo':
        $hoy = date("Y-m-d");

        // echo ('<pre>');
        // print_r($mesesant);
        // echo ('</pre>');
    ?>
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="ercomparativo" style="display: none;">
        <div class="text" style="text-align:center">ESTADO DE RESULTADOS COMPARATIVO</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Fecha de Estado de Resultados 1</div>
                            <div class="card-body">
                                <div class="row" id="fechas1">
                                    <div class="col-sm-6">
                                        <label for="finicio1">Desde</label>
                                        <input type="date" class="form-control" id="finicio1" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="ffin1">Hasta</label>
                                        <input type="date" class="form-control" id="ffin1" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Fecha de Estado de Resultados 2</div>
                            <div class="card-body">
                                <div class="row" id="fechas2">
                                    <div class="col-sm-6">
                                        <label for="finicio2">Desde</label>
                                        <input type="date" class="form-control" id="finicio2"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="ffin2">Hasta</label>
                                        <input type="date" class="form-control" id="ffin2" value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Estado de Resultados Comparativo en Pdf"
                            onclick="reportes([[`finicio1`,`ffin1`,`finicio2`,`ffin2`],[],[],[<?php echo $idusuario; ?>]],`pdf`,`ercomparativo`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Estado de Resultados Comparativo en Excel"
                            onclick="reportes([[`finicio1`,`ffin1`,`finicio2`,`ffin2`],[],[],[<?php echo $idusuario; ?>]],`xlsx`,`ercomparativo`,1)">
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
    <?php
        break;
    case 'partidaconsoli':
    ?>
        <!--AHO-4-Clclintrs Cuenta Ahorros-->
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="libdiario" style="display: none;">
        <div class="text" style="text-align:center">GENERACION DE PARTIDA CONTABLE CONSOLIDADA</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="row">
                            <?php

                            //   <!-- --REQ--crediprendas--1--restrinccion de reportes -->
                            $query = "SELECT id_usu, puesto, id_agencia
                                FROM tb_usuario
                                WHERE id_usu = '$idusuario'";
                            $resultado = mysqli_query($conexion, $query);

                            $puestosP = array("ADM", "GER", "AUD", "CNT");
                            if ($resultado) {
                                $fila = mysqli_fetch_assoc($resultado);

                                // Verificar si la fila existe 
                                $mostrarTodo = ($fila && in_array($fila['puesto'], $puestosP));
                            ?>
                                <div class="col-sm-12">
                                    <?php if ($mostrarTodo) : ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi" value="allofi"
                                                onclick="changedisabled(`#codofi`,0)">
                                            <label for="allofi" class="form-check-label">Consolidado</label>
                                        </div>
                                    <?php endif; ?>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ragencia" id="anyofi" value="anyofi"
                                            checked onclick="changedisabled(`#codofi`,1)">
                                        <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                        </div>
                    </div>
                    <div class="col-sm-6">
                        <span class="input-group-addon col-2">Agencia</span>



                        <?php
                        $sql = "SELECT id_usu, puesto, id_agencia
                                     FROM tb_usuario
                                     WHERE id_usu = '$idusuario'";
                        $resultado = mysqli_query($conexion, $sql);

                        if ($resultado) {
                            $fila = mysqli_fetch_assoc($resultado);

                            if ($fila) {
                                $puestosP = array("ADM", "GER", "AUD", "CNT");

                                if (in_array($fila['puesto'], $puestosP)) {
                                    //permisos v
                        ?>
                                    <select class="form-select" id="codofi" style="max-width: 70%;">
                                        <?php
                                        $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                        ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                        while ($ofi = mysqli_fetch_array($ofis)) {
                                            echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                        }
                                        ?>
                                    </select>


                                <?php
                                } else {
                                    //caso contario
                                ?>

                                    <select class="form-select" id="codofi" style="max-width: 70%;">
                                        <?php
                                        $ofis2 = mysqli_query($conexion, "SELECT usu.id_agencia, ofi.cod_agenc, ofi.nom_agencia
                                                                      FROM tb_usuario AS usu
                                                                      INNER JOIN tb_agencia AS ofi ON ofi.id_agencia = usu.id_agencia
                                                                     WHERE usu.id_usu = '$idusuario'");

                                        $filaOfis2 = mysqli_fetch_assoc($ofis2);

                                        echo '<option value="' . $filaOfis2['id_agencia'] . '" selected>' . $filaOfis2['cod_agenc'] . " - " . $filaOfis2['nom_agencia'] . '</option>';
                                        ?>
                                    </select>



                        <?php
                                }
                            } else {
                                echo "No se encontraron resultados para el usuario con ID: $idusuario";
                            }
                        } else {
                            echo "Error en la consulta: " . mysqli_error($conexion);
                        }

                        ?>


                        <!-- codigo anterior -->
                        <!-- <select class="form-select" id="codofi" style="max-width: 70%;" disabled> -->
                        <?php
                        /* $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                    ON ofi.id_agencia = usu.id_agencia WHERE usu.id_usu=" . $idusuario . ""); */
                        // $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                        // ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                        // while ($ofi = mysqli_fetch_array($ofis)) {
                        //     echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                        // }
                        ?>
                        <!-- </select> -->

                    </div>
                    <div class="col-sm-6 g-4" style="display:none;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="radio" role="switch" name="rtipo" id="c" value="c" onclick="">
                            <input style="display: none;" class="form-check-input" type="radio" role="switch" name="rtipo"
                                id="n" value="n" checked>
                            <label class="form-check-label" for="c">GENERACION DE PARTIDA CONTABLE CONSOLIDADA</label>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
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
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfechas" id="ftodo" value="ftodo"
                                                checked onclick="changedisabled(`#filfechas *`,0)">
                                            <label for="ftodo" class="form-check-label">Todo</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfechas" id="frango"
                                                value="frango" onclick="changedisabled(`#filfechas *`,1)">
                                            <label for="frango" class="form-check-label">Rango</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01" disabled
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01" disabled
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-sm-12">
                        <span class="input-group-addon col-2">Tipo de poliza</span>

                        <select class="form-select" id="codpoliza" style="max-width: 70%;">
                            <?php
                            try {
                                $database->openConnection(2);
                                $polizas = $database->selectColumns('ctb_tipo_poliza', ['id', 'descripcion']);
                                echo '<option value="0" selected> 0 - TODOS </option>';
                                foreach ($polizas as $dat2) {
                                    echo '<option value="' . $dat2['id'] . '" >' . $dat2['id'] . " - " . $dat2['descripcion'] . '</option>';
                                }
                            } catch (Exception $e) {
                                echo "Error: " . $e->getMessage();
                            } finally {
                                $database->closeConnection();
                            }

                            ?>
                        </select>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Partida Contable Consolidada en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`codpoliza`],[`rtipo`,`rfondos`,`rfechas`,`ragencia`],[<?php echo $idusuario; ?>]],`pdf`,`par_cont_consolidada2`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Partida Contable Consolidada en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`codofi`,`fondoid`,`codpoliza`],[`rtipo`,`rfondos`,`rfechas`,`ragencia`],[<?php echo $idusuario; ?>]],`xlsx`,`par_cont_consolidada2`,1)">
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

    <?php
        break;
    case 'res_libcaja':
    ?>
        <input type="text" id="file" value="ctb004" style="display: none;">
        <input type="text" id="condi" value="res_libcaja" style="display: none;">
        <div class="text" style="text-align:center">RESUMEN DE LIBRO CAJA POR TIPO DE POLIZA</div>
        <div class="card">
            <div class="card-header">FILTROS</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficina</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Agencia</span>
                                        <select class="form-select" id="codofi" style="max-width: 70%;">
                                            <option value="0" selected>Consolidado</option>
                                            <?php
                                            $ofis = mysqli_query($conexion, "SELECT id_agencia,cod_agenc,nom_agencia FROM tb_agencia");
                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                echo '<option value="' . $ofi['id_agencia'] . '" >' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- New Filter for Cuentas -->
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Cuentas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="allcuen"
                                                value="allcuen" checked onclick="changedisabled(`#btncuenid`,0)">
                                            <label for="allcuen" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rcuentas" id="anycuen"
                                                value="anycuen" onclick="changedisabled(`#btncuenid`,1)">
                                            <label for="anycuen" class="form-check-label"> Una cuenta</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-floating mb-3">
                                            <div class="input-group" style="width:min(70%,32rem);">
                                                <input style="display:none;" type="text" class="form-control" id="idcuenta"
                                                    value="0">
                                                <input type="text" disabled readonly class="form-control" id="cuenta">
                                                <button disabled id="btncuenid" class="btn btn-outline-success" type="button"
                                                    onclick="abrir_modal(`#modal_nomenclatura_enabled`, `show`, `#id_modal_hidden`, `idcuenta,cuenta`)"
                                                    title="Buscar Cuenta contable"><i
                                                        class="fa fa-magnifying-glass"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Finalizacion del Filtro-->
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fuente de fondos</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="allf" value="allf"
                                                checked onclick="changedisabled(`#fondoid`,0)">
                                            <label for="allf" class="form-check-label">Todo </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rfondos" id="anyf" value="anyf"
                                                onclick="changedisabled(`#fondoid`,1)">
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
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                            value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row container contenedort">
                    <div class="col-sm-12">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row container contenedort">
                                    <div class="col-sm-12">
                                        <span class="input-group-addon col-2">Tipo de póliza</span>
                                        <select class="form-select" id="codpoliza" style="max-width: 70%;">
                                            <?php
                                            try {
                                                $database->openConnection(2);
                                                $polizas = $database->selectColumns('ctb_tipo_poliza', ['id', 'descripcion']);
                                                echo '<option value="0" selected> 0 - TODOS </option>';
                                                foreach ($polizas as $dat2) {
                                                    echo '<option value="' . $dat2['id'] . '" >' . $dat2['id'] . " - " . $dat2['descripcion'] . '</option>';
                                                }
                                            } catch (Exception $e) {
                                                echo "Error: " . $e->getMessage();
                                            } finally {
                                                $database->closeConnection();
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Botones-->
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Libro Caja en pdf"
                            onclick="reportes([[`finicio`,`ffin`,`idcuenta`],[`codofi`,`fondoid`,`codpoliza`],[`rfondos`,`rcuentas`],[<?php echo $idusuario; ?>]],`pdf`,`cajatip_pol`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Libro Caja en Excel"
                            onclick="reportes([[`finicio`,`ffin`,`idcuenta`],[`codofi`,`fondoid`,`codpoliza`],[`rfondos`,`rcuentas`],[<?php echo $idusuario; ?>]],`xlsx`,`cajatip_pol`,1)">
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
        <?php include __DIR__ . "/../../src/cris_modales/mdls_cuenta_caja.php"; ?>
<?php
        break;
}
?>