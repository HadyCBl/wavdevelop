<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$idusuario = $_SESSION['id'];
$condi = $_POST["condi"];

function render_filtro_region($conexion)
{
    $options = '<option value="0" selected disabled>Seleccionar región</option>';
    $res = mysqli_query($conexion, "SELECT id, nombre FROM cre_regiones WHERE estado=1 ORDER BY nombre");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $id = (int)($row['id'] ?? 0);
            $nombre = htmlspecialchars((string)($row['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
            $options .= '<option value="' . $id . '">' . $nombre . '</option>';
        }
    }

    return '
        <div class="row container contenedort">
            <div class="col-sm-6">
                <div class="card text-bg-light" style="height: 100%;">
                    <div class="card-header">Filtro por Región</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="rregion" id="allregion" value="allregion" checked onclick="changedisabled(`#regionid`,0)">
                                    <label for="allregion" class="form-check-label">Todo</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="rregion" id="anyregion" value="anyregion" onclick="changedisabled(`#regionid`,1)">
                                    <label for="anyregion" class="form-check-label">Por Región</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="regionid" disabled>
                                        ' . $options . '
                                    </select>
                                    <label class="text-primary" for="regionid">Región</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
}

switch ($condi) {
    case 'reporte_01': {
?>
<input type="text" id="file" value="estadisticos_01" style="display: none;">
<input type="text" id="condi" value="reporte_01" style="display: none;">
<div class="text" style="text-align:center">CLASIFICACION POR FRECUENCIA DE PAGO </div>
<div class="card">
    <div class="card-header">FILTROS</div>
    <div class="card-body">
        <div class="row container contenedort">
            <div class="col-6">
                <div class="card text-bg-light" style="height: 100%;">
                    <div class="card-header">Filtro por Agencias</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                        value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                    <label for="allofi" class="form-check-label">Consolidado </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                        value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                    <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12">
                                <span class="input-group-addon col-2">Agencia</span>
                                <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                    <?php
                                                $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                while ($ofi = mysqli_fetch_array($ofis)) {
                                                    echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                }
                                                ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card text-bg-light" style="height: 100%;">
                    <div class="card-header">Filtro por Estados</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="allstatus"
                                        value="allstatus" checked>
                                    <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                    <label for="F" class="form-check-label"> Vigentes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                    <label for="G" class="form-check-label"> Cancelados</label>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                        <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                    <div class="card-header">FECHA DE PROCESO</div>
                    <div class="card-body">
                        <div class="row" id="filfechas">
                            <div class=" col-sm-6">
                                <label for="ffin">Fecha</label>
                                <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo render_filtro_region($conexion); ?>
        <div class="row justify-items-md-center">
            <div class="col align-items-center">
                <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                    onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_frecpago`,0)">
                    <i class="fa-solid fa-file-pdf"></i> Pdf
                </button>
                <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                    onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_frecpago`,1)">
                    <i class="fa-solid fa-file-excel"></i>Excel
                </button> -->
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
       //reporte estadictico por sector economico pero es actividad economica xd
        case 'reporte_02': {
            ?>
            <input type="text" id="file" value="estadisticos_01" style="display: none;">
            <input type="text" id="condi" value="reporte_02" style="display: none;">
            <div class="text" style="text-align:center">CLASIFICACION POR ACTIVIDAD ECONOMICA</div>
            <div class="card">
                <div class="card-header">FILTROS</div>
                <div class="card-body">
                    <div class="row container contenedort">
                        <div class="col-6">
                            <div class="card text-bg-light" style="height: 100%;">
                                <div class="card-header">Filtro por Agencias</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                    value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                <label for="allofi" class="form-check-label">Consolidado </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                    value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                                <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <span class="input-group-addon col-2">Agencia</span>
                                            <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                                <?php
                                                            $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                            ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                                echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                            }
                                                            ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-bg-light" style="height: 100%;">
                                <div class="card-header">Filtro por Estados</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                    value="allstatus" checked>
                                                <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                                <label for="F" class="form-check-label"> Vigentes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                                <label for="G" class="form-check-label"> Cancelados</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                                                    <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                                <div class="card-header">FECHA DE PROCESO</div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class=" col-sm-6">
                                            <label for="ffin">Fecha</label>
                                            <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php echo render_filtro_region($conexion); ?>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center">
                            <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_sector`,0)">
                                <i class="fa-solid fa-file-pdf"></i> Pdf
                            </button>
                            <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_sector`,1)">
                                <i class="fa-solid fa-file-excel"></i>Excel
                            </button> -->
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
                     //reporte estadictico por sector economico
        case 'reporte_03': {
            ?>
            <input type="text" id="file" value="estadisticos_01" style="display: none;">
            <input type="text" id="condi" value="reporte_03" style="display: none;">
            <div class="text" style="text-align:center">CLASIFICACION POR RANGO DE MONTOS</div>
            <div class="card">
                <div class="card-header">FILTROS</div>
                <div class="card-body">
                    <div class="row container contenedort">
                        <div class="col-6">
                            <div class="card text-bg-light" style="height: 100%;">
                                <div class="card-header">Filtro por Agencias</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                    value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                <label for="allofi" class="form-check-label">Consolidado </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                    value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                                <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <span class="input-group-addon col-2">Agencia</span>
                                            <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                                <?php
                                                            $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                            ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                                echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                            }
                                                            ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-bg-light" style="height: 100%;">
                                <div class="card-header">Filtro por Estados</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                    value="allstatus" checked>
                                                <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                                <label for="F" class="form-check-label"> Vigentes</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                                <label for="G" class="form-check-label"> Cancelados</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                                                    <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                                <div class="card-header">FECHA DE PROCESO</div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class=" col-sm-6">
                                            <label for="ffin">Fecha</label>
                                            <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php echo render_filtro_region($conexion); ?>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center">
                            <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_rango_montos`,0)">
                                <i class="fa-solid fa-file-pdf"></i> Pdf
                            </button>
                            <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_sector`,1)">
                                <i class="fa-solid fa-file-excel"></i>Excel
                            </button> -->
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
                    case 'reporte_04': {
                        ?>
                        <input type="text" id="file" value="estadisticos_01" style="display: none;">
                        <input type="text" id="condi" value="reporte_04" style="display: none;">
                        <div class="text" style="text-align:center">CLASIFICACION POR DESTINO</div>
                        <div class="card">
                            <div class="card-header">FILTROS</div>
                            <div class="card-body">
                                <div class="row container contenedort">
                                    <div class="col-6">
                                        <div class="card text-bg-light" style="height: 100%;">
                                            <div class="card-header">Filtro por Agencias</div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-sm-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                                value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                            <label for="allofi" class="form-check-label">Consolidado </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                                value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                                            <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-sm-12">
                                                        <span class="input-group-addon col-2">Agencia</span>
                                                        <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                                            <?php
                                                                        $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                                        ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                                        while ($ofi = mysqli_fetch_array($ofis)) {
                                                                            echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                                        }
                                                                        ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card text-bg-light" style="height: 100%;">
                                            <div class="card-header">Filtro por Estados</div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-sm-12">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                                value="allstatus" checked>
                                                            <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                                            <label for="F" class="form-check-label"> Vigentes</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                                            <label for="G" class="form-check-label"> Cancelados</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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
                                                                <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                                            <div class="card-header">FECHA DE PROCESO</div>
                                            <div class="card-body">
                                                <div class="row" id="filfechas">
                                                    <div class=" col-sm-6">
                                                        <label for="ffin">Fecha</label>
                                                        <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php echo render_filtro_region($conexion); ?>
                                <div class="row justify-items-md-center">
                                    <div class="col align-items-center">
                                        <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                                            onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_por_destino`,0)">
                                            <i class="fa-solid fa-file-pdf"></i> Pdf
                                        </button>
                                        <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                            onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_sector`,1)">
                                            <i class="fa-solid fa-file-excel"></i>Excel
                                        </button> -->
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
                                case 'reporte_05': {
                                    ?>
                                    <input type="text" id="file" value="estadisticos_01" style="display: none;">
                                    <input type="text" id="condi" value="reporte_05" style="display: none;">
                                    <div class="text" style="text-align:center">CLASIFICACION POR SEXO</div>
                                    <div class="card">
                                        <div class="card-header">FILTROS</div>
                                        <div class="card-body">
                                            <div class="row container contenedort">
                                                <div class="col-6">
                                                    <div class="card text-bg-light" style="height: 100%;">
                                                        <div class="card-header">Filtro por Agencias</div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                                            value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                                        <label for="allofi" class="form-check-label">Consolidado </label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                                            value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                                                        <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-sm-12">
                                                                    <span class="input-group-addon col-2">Agencia</span>
                                                                    <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                                                        <?php
                                                                                    $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                                                    ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                                                    while ($ofi = mysqli_fetch_array($ofis)) {
                                                                                        echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                                                    }
                                                                                    ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="card text-bg-light" style="height: 100%;">
                                                        <div class="card-header">Filtro por Estados</div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                                            value="allstatus" checked>
                                                                        <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                                                        <label for="F" class="form-check-label"> Vigentes</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                                                        <label for="G" class="form-check-label"> Cancelados</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
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
                                                                            <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                                                        <div class="card-header">FECHA DE PROCESO</div>
                                                        <div class="card-body">
                                                            <div class="row" id="filfechas">
                                                                <div class=" col-sm-6">
                                                                    <label for="ffin">Fecha</label>
                                                                    <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php echo render_filtro_region($conexion); ?>
                                            <div class="row justify-items-md-center">
                                                <div class="col align-items-center">
                                                    <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                                                        onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_por_sexo`,0)">
                                                        <i class="fa-solid fa-file-pdf"></i> Pdf
                                                    </button>
                                                    <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                                        onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_sector`,1)">
                                                        <i class="fa-solid fa-file-excel"></i>Excel
                                                    </button> -->
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
                                case 'reporte_06': {
                                    ?>
                                    
                                    <input type="text" id="file" value="estadisticos_01" style="display: none;">
                                    <input type="text" id="condi" value="reporte_06" style="display: none;">
                                    <div class="text" style="text-align:center">CLASIFICACION POR UBICACION</div>
                                    <div class="card">
                                        <div class="card-header">FILTROS</div>
                                        <div class="card-body">
                                            <div class="row container contenedort">
                                                <div class="col-6">
                                                    <div class="card text-bg-light" style="height: 100%;">
                                                        <div class="card-header">Filtro por Agencias</div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                                            value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                                        <label for="allofi" class="form-check-label">Consolidado </label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                                            value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                                                        <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-sm-12">
                                                                    <span class="input-group-addon col-2">Agencia</span>
                                                                    <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                                                        <?php
                                                                                    $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                                                    ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                                                    while ($ofi = mysqli_fetch_array($ofis)) {
                                                                                        echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                                                    }
                                                                                    ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="card text-bg-light" style="height: 100%;">
                                                        <div class="card-header">Filtro por Estados</div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-sm-12">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                                            value="allstatus" checked>
                                                                        <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                                                        <label for="F" class="form-check-label"> Vigentes</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                                                        <label for="G" class="form-check-label"> Cancelados</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
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
                                                                            <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                                                        <div class="card-header">FECHA DE PROCESO</div>
                                                        <div class="card-body">
                                                            <div class="row" id="filfechas">
                                                                <div class=" col-sm-6">
                                                                    <label for="ffin">Fecha</label>
                                                                    <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php echo render_filtro_region($conexion); ?>
                                            <div class="row justify-items-md-center">
                                                <div class="col align-items-center">
                                                    <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                                                        onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_por_ubicacion`,0)">
                                                        <i class="fa-solid fa-file-pdf"></i> Pdf
                                                    </button>
                                                    <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                                        onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_sector`,1)">
                                                        <i class="fa-solid fa-file-excel"></i>Excel
                                                    </button> -->
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
                                            case 'reporte_07': {
                                                ?>
                                                
                                                <input type="text" id="file" value="estadisticos_01" style="display: none;">
                                                <input type="text" id="condi" value="reporte_07" style="display: none;">
                                                <div class="text" style="text-align:center">CLASIFICACION POR EDAD</div>
                                                <div class="card">
                                                    <div class="card-header">FILTROS</div>
                                                    <div class="card-body">
                                                        <div class="row container contenedort">
                                                            <div class="col-6">
                                                                <div class="card text-bg-light" style="height: 100%;">
                                                                    <div class="card-header">Filtro por Agencias</div>
                                                                    <div class="card-body">
                                                                        <div class="row">
                                                                            <div class="col-sm-12">
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                                                        value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                                                    <label for="allofi" class="form-check-label">Consolidado </label>
                                                                                </div>
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                                                        value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                                                                    <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="row">
                                                                            <div class="col-sm-12">
                                                                                <span class="input-group-addon col-2">Agencia</span>
                                                                                <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                                                                    <?php
                                                                                                $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                                                                ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                                                                while ($ofi = mysqli_fetch_array($ofis)) {
                                                                                                    echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                                                                }
                                                                                                ?>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="card text-bg-light" style="height: 100%;">
                                                                    <div class="card-header">Filtro por Estados</div>
                                                                    <div class="card-body">
                                                                        <div class="row">
                                                                            <div class="col-sm-12">
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                                                        value="allstatus" checked>
                                                                                    <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                                                                </div>
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                                                                    <label for="F" class="form-check-label"> Vigentes</label>
                                                                                </div>
                                                                                <div class="form-check">
                                                                                    <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                                                                    <label for="G" class="form-check-label"> Cancelados</label>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
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
                                                                                        <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                                                                    <div class="card-header">FECHA DE PROCESO</div>
                                                                    <div class="card-body">
                                                                        <div class="row" id="filfechas">
                                                                            <div class=" col-sm-6">
                                                                                <label for="ffin">Fecha</label>
                                                                                <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php echo render_filtro_region($conexion); ?>
                                                        <div class="row justify-items-md-center">
                                                            <div class="col align-items-center">
                                                                <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                                                                    onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_por_edad`,0)">
                                                                    <i class="fa-solid fa-file-pdf"></i> Pdf
                                                                </button>
                                                                <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                                                    onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_sector`,1)">
                                                                    <i class="fa-solid fa-file-excel"></i>Excel
                                                                </button> -->
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

                                                        case 'reporte_08': {
                                                            ?>
                                                            
                                                            <input type="text" id="file" value="estadisticos_01" style="display: none;">
                                                            <input type="text" id="condi" value="reporte_08" style="display: none;">
                                                            <div class="text" style="text-align:center">CLASIFICACION POR ETNIA</div>
                                                            <div class="card">
                                                                <div class="card-header">FILTROS</div>
                                                                <div class="card-body">
                                                                    <div class="row container contenedort">
                                                                        <div class="col-6">
                                                                            <div class="card text-bg-light" style="height: 100%;">
                                                                                <div class="card-header">Filtro por Agencias</div>
                                                                                <div class="card-body">
                                                                                    <div class="row">
                                                                                        <div class="col-sm-12">
                                                                                            <div class="form-check">
                                                                                                <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                                                                                    value="allofi" checked onclick="changedisabled(`#codofi`,0)">
                                                                                                <label for="allofi" class="form-check-label">Consolidado </label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                                                                                    value="anyofi" onclick="changedisabled(`#codofi`,1)">
                                                                                                <label for="anyofi" class="form-check-label"> Por Agencia</label>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="row">
                                                                                        <div class="col-sm-12">
                                                                                            <span class="input-group-addon col-2">Agencia</span>
                                                                                            <select class="form-select" id="codofi" style="max-width: 70%;" disabled>
                                                                                                <?php
                                                                                                            $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                                                                                                                            ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                                                                                            while ($ofi = mysqli_fetch_array($ofis)) {
                                                                                                                echo '<option value="' . $ofi['id_agencia'] . '" selected>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                                                                                            }
                                                                                                            ?>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <div class="card text-bg-light" style="height: 100%;">
                                                                                <div class="card-header">Filtro por Estados</div>
                                                                                <div class="card-body">
                                                                                    <div class="row">
                                                                                        <div class="col-sm-12">
                                                                                            <div class="form-check">
                                                                                                <input class="form-check-input" type="radio" name="status" id="allstatus"
                                                                                                    value="allstatus" checked>
                                                                                                <label for="allstatus" class="form-check-label">Vigentes y Cancelados </label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input class="form-check-input" type="radio" name="status" id="F" value="F">
                                                                                                <label for="F" class="form-check-label"> Vigentes</label>
                                                                                            </div>
                                                                                            <div class="form-check">
                                                                                                <input class="form-check-input" type="radio" name="status" id="G" value="G">
                                                                                                <label for="G" class="form-check-label"> Cancelados</label>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
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
                                                                                                    <option value="0" selected disabled>Seleccionar fuente de Fondos</option>
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
                                                                                <div class="card-header">FECHA DE PROCESO</div>
                                                                                <div class="card-body">
                                                                                    <div class="row" id="filfechas">
                                                                                        <div class=" col-sm-6">
                                                                                            <label for="ffin">Fecha</label>
                                                                                            <input type="date" class="form-control" id="ffin" value="<?php echo date("Y-m-d"); ?>">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <?php echo render_filtro_region($conexion); ?>
                                                                    <div class="row justify-items-md-center">
                                                                        <div class="col align-items-center">
                                                                            <button type="button" class="btn btn-outline-primary" title="Reporte en pdf"
                                                                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`pdf`,`clasificacion_por_etnia`,0)">
                                                                                <i class="fa-solid fa-file-pdf"></i> Pdf
                                                                            </button>
                                                                            <!-- <button type="button" class="btn btn-outline-success" title="Cartera en Excel"
                                                                                onclick="reportes([[`ffin`],[`codofi`,`fondoid`,`regionid`],[`ragencia`,`rfondos`,`status`,`rregion`],[<?php echo $idusuario; ?>]],`xlsx`,`clasificacion_sector`,1)">
                                                                                <i class="fa-solid fa-file-excel"></i>Excel
                                                                            </button> -->
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