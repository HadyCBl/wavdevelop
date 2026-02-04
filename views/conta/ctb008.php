<?php
include __DIR__ . '/../../includes/Config/config.php';
session_start();
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);


include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];
switch ($condi) {
    /*--------------------------------------------------------------------------------- */
    case 'catalogo_cuentas_contables':
        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
?>
        <input type="text" id="condi" value="catalogo_cuentas_contables" hidden>
        <input type="text" id="file" value="ctb008" hidden>
        <div class="card">
            <div class="card-header">Catálogo de cuentas</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-6 border-end border-success">
                        <div class="row justify-content-center">
                            <div class="col-auto me-2 border-bottom border-success">
                                <h4 class="text-center">Listado de cuentas</h4>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col">
                                <div class="table-responsive">
                                    <table class="table" id="tb_nomenclatura">
                                        <thead>
                                            <tr style="font-size: 0.8rem;">
                                                <th>#</th>
                                                <th>Cuenta</th>
                                                <th>Descripción</th>
                                                <th>R/D</th>
                                                <th>Editar/Eliminar</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-group-divider" style="font-size: 0.8rem;">
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="row justify-content-center">
                            <div class="col-auto border-bottom border-success">
                                <h4 class="text-center">Creación/Edición de cuentas contables</h4>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col">
                                <div class="form-floating">
                                    <select class="form-select" id="tipo">
                                        <option value="" selected>Seleccione una opción</option>
                                        <option value="R">R - Resumen</option>
                                        <option value="D">D - Detalle</option>
                                    </select>
                                    <label for="tipo">Resumen/Detalle</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="input-group mb-3 mt-3">
                                    <span class="input-group-text" id="basic-addon1">Código de cuenta</span>
                                    <input type="text" class="form-control" id="cod_cuenta" placeholder="Ingrese código"
                                        aria-label="Username" aria-describedby="basic-addon1">
                                    <input type="text" class="form-control" id="id_hidden" hidden>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="input-group mb-3">
                                    <span class="input-group-text">Descripción</span>
                                    <textarea class="form-control" id="descripcion" aria-label="With textarea"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-2" id="btGuardar">
                                <button type="button" class="col-12 button-85"
                                    onclick="obtiene([`cod_cuenta`,`descripcion`,`tipo`],[],[],`create_cuentas_contables`,`0`,['<?php echo $codusu; ?>'])">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                                </button>
                            </div>
                            <div class="col-12 mb-2" id="btEditar">
                                <button type="button" class="col-12 button-85"
                                    onclick="obtiene([`id_hidden`,`cod_cuenta`,`descripcion`,`tipo`],[],[],`update_cuentas_contables`,`0`,['<?php echo $codusu; ?>'])">
                                    <i class="fa-solid fa-floppy-disk"></i> Actualizar
                                </button>
                            </div>
                            <div class="col-12  mb-2">
                                <button type="button" class="col-12 btn btn-danger" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                            </div>
                            <div class="col-12 ">
                                <button type="button" class="col-12 btn btn-warning" onclick="salir()">
                                    <i class="fa-solid fa-circle-xmark"></i> Salir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            //Datatable para parametrizacion
            $(document).ready(function() {
                cargar_datos_cuenta(<?php echo $codusu; ?>)
                HabDes_boton(0);
            });
        </script>
    <?php
        break;
    /*--------------------------------------------------------------------------------- */
    case 'fuenteFondos':

        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
    ?>
        <input type="text" id="condi" value="fuenteFondos" hidden>
        <input type="text" id="file" value="ctb008" hidden>
        <div class="card">
            <div class="card-header">Fuente de Fondos</div>
            <div class="card-body">
                <!-- cuadro -->
                <div class="contenedort container">
                    <div class="row">
                        <div class="col">
                            <div class="input-group mb-3 mt-3">
                                <span class="input-group-text" id="basic-addon1">Descripción</span>
                                <input type="text" class="form-control" id="descripcion" placeholder="Descripción"
                                    aria-label="Username" aria-describedby="basic-addon1">
                                <input type="text" placeholder="Descripción" id="id_fuente" hidden>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mt-2 mb-3 d-flex justify-content-center">
                            <button type="button" class="button-85 me-4" id="btGuardar"
                                onclick="obtiene([`descripcion`],[],[],`create_fuentefondos`,`0`,['<?= $codusu; ?>'])">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar
                            </button>
                            <button type="button" class="button-85 me-4" id="btEditar"
                                onclick="obtiene([`id_fuente`,`descripcion`],[],[],`update_fuentefondos`,`0`,['<?= $codusu; ?>'])">
                                <i class="fa-solid fa-floppy-disk"></i> Actualizar
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3 d-flex justify-content-center">
                            <button type="button" class="btn btn-danger me-2" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-warning me-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
                <!-- tabla para los  -->
                <div class="row mt-2 mb-4">
                    <div class="col">
                        <div class="table-responsive">
                            <table class="table" id="tb_fuentefondos">
                                <thead>
                                    <tr style="font-size: 0.8rem;">
                                        <th>#</th>
                                        <th>Descripción</th>
                                        <th>Editar/Eliminar</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider" style="font-size: 0.8rem;">
                                    <?php
                                    $consulta = mysqli_query($conexion, "SELECT ff.id, ff.descripcion FROM ctb_fuente_fondos ff
                                    WHERE ff.estado='1'");
                                    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) { ?>
                                        <tr>
                                            <th scope="row"><?= $fila['id'] ?></th>
                                            <td><?= $fila['descripcion'] ?></td>
                                            <td>
                                                <button type="button" class="btn btn-success btn-sm"
                                                    onclick="printdiv5('id_fuente,descripcion/A,A/'+'/'+'#/#', ['<?= $fila['id'] ?>','<?= $fila['descripcion'] ?>']); HabDes_boton(1);"><i
                                                        class="fa-solid fa-eye"></i></button>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="eliminar('<?= $fila['id'] ?>', 'crud_ctb', '0', 'delete_fuentefondos')"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <script>
                //Datatable para parametrizacion
                $(document).ready(function() {
                    convertir_tabla_a_datatable('tb_fuentefondos');
                    HabDes_boton(0);
                });
            </script>
        </div>
    <?php
        break;
    case 'cierres':
        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
    ?>
        <input type="text" id="condi" value="cierres" hidden>
        <input type="text" id="file" value="ctb008" hidden>
        <div class="card">
            <div class="card-header">CIERRES MENSUALES</div>
            <div class="card-body">
                <div class="contenedort container">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2"><b>Mes contable actual</b></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3 d-flex justify-content-center">
                            <button type="button" class="btn btn-danger me-2" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-warning me-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
                <div class="container contenedort" style="width: 100% !important;">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2"><b>Meses contables</b></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="table-responsive">
                                <table class="table mb-0" id="ctb_meses">
                                    <thead>
                                        <tr>
                                            <th scope="col">Mes</th>
                                            <th scope="col">Año</th>
                                            <th scope="col">Estado</th>
                                            <th scope="col">Opciones</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="height: 100%;">
            <div class="card-header">FILTRAR POR</div>
            <div class="card-body">
                <!-- Contenedor flexible para alinear las tarjetas -->
                <div class="d-flex justify-content-between flex-wrap">
                    <!-- Filtro por Oficina -->
                    <div class="card me-3" style="flex: 1; max-width: 48%;">
                        <div class="card-header">Filtro por Oficina</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ragencia" id="allofi"
                                            value="allofi" onclick="changedisabled('#codofi', false)">
                                        <label for="allofi" class="form-check-label">Consolidado</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ragencia" id="anyofi"
                                            value="anyofi" checked onclick="changedisabled('#codofi', true)">
                                        <label for="anyofi" class="form-check-label">Por Agencia</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <span class="input-group-addon">Agencia</span>
                                    <select class="form-select" id="codofi" <?php echo ($hidden) ? 'disabled' : ''; ?>>
                                        <?php
                                        $ofis = mysqli_query($conexion, "SELECT ofi.id_agencia,ofi.cod_agenc,ofi.nom_agencia FROM tb_usuario AS usu INNER JOIN tb_agencia AS ofi 
                                    ON ofi.id_agencia = usu.id_agencia GROUP BY ofi.id_agencia");
                                        while ($ofi = mysqli_fetch_array($ofis)) {
                                            $selected = ($ofi['id_agencia'] == $_SESSION["id_agencia"]) ? "selected" : "";
                                            echo '<option value="' . $ofi['id_agencia'] . '" ' . $selected . '>' . $ofi['cod_agenc'] . " - " . $ofi['nom_agencia'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Filtro por Año -->
                    <div class="card ms-3" style="flex: 1; max-width: 48%;">
                        <div class="card-header">Filtro por Año</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ranio" id="allani"
                                            value="allani" onclick="changedisabled('#foranio', false)">
                                        <label for="allofi" class="form-check-label">Consolidado</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="ranio" id="anyani"
                                            value="anyani" checked onclick="changedisabled('#foranio', true)">
                                        <label for="anyofi" class="form-check-label">Por año</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12">
                                    <span class="input-group-addon">Año</span>
                                    <select id="foranio" class="form-control">
                                        <?php
                                        $currentYear = date("Y"); // Año actual
                                        for ($i = $currentYear + 1; $i >= $currentYear - 3; $i--) {
                                            echo "<option value=\"$i\">$i</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Botones -->
                <div class="row justify-items-md-center mt-3">
                    <div class="col align-items-center">
                        <!-- la pocision 1 son imputs creo el 2 son selects el 3 son radios  -->
                        <button type="button" class="btn btn-outline-danger" title="Cierre mensual en pdf"
                            onclick="reportes([[],[`codofi`,`foranio`],[`ragencia`,`ranio`],[<?php echo $idusuario; ?>]],`pdf`,`cierremensual`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
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
                $('#ctb_meses').on('search.dt').DataTable({
                    "aProcessing": true,
                    "aServerSide": true,
                    "ordering": false,
                    "lengthMenu": [
                        [10, 15, -1],
                        ['10 filas', '15 filas', 'Mostrar todos']
                    ],
                    "ajax": {
                        url: '../src/cruds/crud_ctb.php',
                        type: "POST",
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        data: {
                            'condi': "mesesctb"
                        },
                        dataType: "json",
                        complete: function(data) {
                            loaderefect(0);
                            // console.log(data)
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
            });
        </script>
    <?php
        break;
    case 'apertura_meses_rango':
        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
    ?>
        <input type="text" id="condi" value="apertura_meses_rango" hidden>
        <input type="text" id="file" value="ctb008" hidden>
        <div class="card">
            <div class="card-header">APERTURAS MENSUALES POR RANGO DE FECHAS</div>
            <div class="card-body">
                <!-- cuadro -->
                <div class="contenedort container">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="dateini" value="<?php echo date("Y-m-d"); ?>">
                                <label class="text-primary" for="dateini">Fecha inicio</label>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="datefin" value="<?php echo date("Y-m-d"); ?>">
                                <label class="text-primary" for="datefin">Fecha fin</label>
                            </div>
                        </div>
                        <div class="col mt-2 mb-3 d-flex justify-content-center">
                            <button type="button" class="button-85 me-4" id="btGuardar"
                                onclick="obtiene([`dateini`,`datefin`],[],[],`apertura_mes_fecha`,`0`,1)">
                                <i class="fa-solid fa-floppy-disk"></i> apertura
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3 d-flex justify-content-center">
                            <button type="button" class="btn btn-danger me-2" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-warning me-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <!--parametrizacion de flujo de efectivo -->
    <?php
        break;
    case 'paramflujoefectivo':

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++ CONSULTA DE TODAS LAS CUENTAS DE ACTIVO, PASIVO, PATRIMONIO, INGRESOS Y EGRESOS ++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        // $nomenclatura[] = [];
        // $strque = "SELECT * from ctb_nomenclatura WHERE estado=1 AND tipo='D' AND substr(ccodcta,1,1)<=5 ORDER BY ccodcta";
        // $querycuen = mysqli_query($conexion, $strque);
        // $j = 0;
        // while ($fil = mysqli_fetch_array($querycuen)) {
        //     $nomenclatura[$j] = $fil;
        //     $j++;
        // }

    ?>
        <input type="text" id="condi" value="paramflujoefectivo" hidden>
        <input type="text" id="file" value="ctb008" hidden>
        <div class="card">
            <div class="card-header">
                <h4>Seleccionar cuentas que afectan el estado de Flujo de Efectivo</h4>
            </div>
            <div class="card-body">
                <div class="accordion accordion-flush" id="accordionFlushExample">
                    <div class="accordion-item row container contenedort">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#flush-collapseFive" aria-expanded="false" aria-controls="flush-collapseFive">
                                Cuentas de caja y bancos
                            </button>
                        </h2>
                        <div id="flush-collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">
                                <table id="tbcuentas5" class="table table-striped" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Estado</th>
                                            <th>Cuenta</th>
                                            <th>Nombre Cuenta</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item row container contenedort">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                                1- Gastos que no requirieron efectivo
                            </button>
                        </h2>
                        <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">
                                <table id="tbcuentas1" class="table table-striped" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Estado</th>
                                            <th>Cuenta</th>
                                            <th>Nombre Cuenta</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item row container contenedort">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                                2- Efectivos Generados por actividades de operacion
                            </button>
                        </h2>
                        <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">
                                <table id="tbcuentas2" class="table table-striped" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Estado</th>
                                            <th>Cuenta</th>
                                            <th>Nombre Cuenta</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item row container contenedort">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
                                3- Flujo de efectivos por actividades de inversion
                            </button>
                        </h2>
                        <div id="flush-collapseThree" class="accordion-collapse collapse"
                            data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">
                                <table id="tbcuentas3" class="table table-striped" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Estado</th>
                                            <th>Cuenta</th>
                                            <th>Nombre Cuenta</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item row container contenedort">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#flush-collapseFour" aria-expanded="false" aria-controls="flush-collapseFour">
                                4- Flujo de efectivos por actividades de financiamiento
                            </button>
                        </h2>
                        <div id="flush-collapseFour" class="accordion-collapse collapse"
                            data-bs-parent="#accordionFlushExample">
                            <div class="accordion-body">
                                <table id="tbcuentas4" class="table table-striped" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Estado</th>
                                            <th>Cuenta</th>
                                            <th>Nombre Cuenta</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row justify-items-md-center">
                <div class="col align-items-center">
                    <button type="button" class="btn btn-outline-success" onclick="savedataflujo()">
                        <i class="fa fa-floppy-disk"></i> Guardar Cambios
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
        <script>
            //recoleccion de los datos de las tablas
            function recolectar_checks2(tabla) {
                checkboxActivados = [];
                // Recorre todas las páginas de la tabla
                tabla.rows().every(function(rowIdx, tableLoop, rowLoop) {
                    // Obtén el estado del checkbox en la fila actual
                    var checkbox = $(this.node()).find('input[type="checkbox"]');
                    if (checkbox.is(':checked')) {
                        checkboxActivados.push(checkbox.val());
                    }
                });
                return (checkboxActivados);
            }
            //guardar flujos de parametrizacion 

            function savedataflujo() {
                datos = [];
                datos[0] = recolectar_checks2(tabla1);
                datos[1] = recolectar_checks2(tabla2);
                datos[2] = recolectar_checks2(tabla3);
                datos[3] = recolectar_checks2(tabla4);
                datos[4] = recolectar_checks2(tabla5);
                obtiene([], [], [], `update_data_flujo`, `0`, datos);
            }
            //cargar de la configuracion
            function loadconfig(numero, nomtabla) {
                var tabla = $('#' + nomtabla).on('search.dt').DataTable({
                    "aProcessing": true,
                    "aServerSide": true,
                    "ordering": false,
                    "lengthMenu": [
                        [10, 15, -1],
                        ['10 filas', '15 filas', 'Mostrar todos']
                    ],
                    "ajax": {
                        url: "../src/cruds/crud_ctb.php",
                        type: "POST",
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        data: {
                            'condi': "cuentasfe",
                            numero
                        },
                        dataType: "json",
                        complete: function(data) {
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
                return tabla;
            }
            //cargar de las conriguraciones 
            tabla1 = loadconfig(1, "tbcuentas1");
            tabla2 = loadconfig(2, "tbcuentas2");
            tabla3 = loadconfig(3, "tbcuentas3");
            tabla4 = loadconfig(4, "tbcuentas4");
            tabla5 = loadconfig(5, "tbcuentas5");
        </script>
        <!--fin de elo case para la generacion y el manejos de las cuentas contables-->
    <?php
        break;
    case 'clases_cuentas':

        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
    ?>
        <style>
            .hidden {
                display: none;
            }
        </style>

        <div class="card" id="clases_cuentas">
            <div class="card-header">Ingresar Nuevas cuentas contables a la Nomenclatura</div>
            <div class="card-body">
                <div class="contenedor container">
                    <div class="row">
                        <div class="col">

                            <div class="input-group mb-3 mt-3">
                                <span class="input-group-text" id="basic-add">Cuenta de Aplicacion</span>
                                <select class="form-select" id="ccodcta" aria-label="Cuenta de Aplicación"
                                    aria-describedby="basic-addon1">
                                    <?php
                                    $consulta_cuentas = mysqli_query($conexion, "SELECT ccodcta, cdescrip, tipo, estado FROM ctb_nomenclatura WHERE LENGTH(ccodcta) = 1 AND estado = '1'");
                                    while ($fila_cuenta = mysqli_fetch_array($consulta_cuentas, MYSQLI_ASSOC)) {
                                        $opcion = $fila_cuenta['ccodcta'] . ' - ' . $fila_cuenta['cdescrip'];
                                        echo '<option value="' . $fila_cuenta['ccodcta'] . '">' . $opcion . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-group mb-3 mt-3">
                                <label class="input-group-text" for="clase_add">Clase</label>
                                <select class="form-select" id="id" aria-label="Cuenta de Aplicación"
                                    aria-describedby="clase_add">
                                    <?php
                                    $consulta_cuentas = mysqli_query($conexion, "SELECT * FROM $db_name_general.ctb_cuentas_app;");
                                    while ($fila_cuenta2 = mysqli_fetch_array($consulta_cuentas, MYSQLI_ASSOC)) {
                                        echo '<option value="' . $fila_cuenta2['id'] . '">' . $fila_cuenta2['descripcion'] . '</option>';
                                    }
                                    ?>
                                </select>
                                <input type="text" placeholder="cdescrip" id="id_fuente_cdescrip" hidden>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3 d-flex justify-content-center">
                            <?php
                            $verifi_duplicate = mysqli_query($conexion, "SELECT id_tipo,clase FROM ctb_parametros_cuentas;")

                            ?>
                            <button type="button" class="button-85 me-4" onclick="verifi()">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-warning me-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="text" id="condi" value="fuenteFondos" hidden>
        <input type="text" id="file" value="ctb008" hidden>

        <div class="card hidden" id="form-container">
            <div class="card-header">Actualizar Clases de Cuentas</div>
            <div class="card-body">
                <!-- cuadro -->
                <div class="contenedort container">
                    <div class="row">
                        <div class="col">

                            <div class="input-group mb-3 mt-3" disabled>
                                <span class="input-group-text" id="basic-addon1">id</span>
                                <input type="text" class="form-control" id="id_load" placeholder="Cuenta de Aplicacion"
                                    aria-label="Username" aria-describedby="basic-addon1" readonly>
                            </div>
                            <div class="input-group mb-3 mt-3">
                                <label class="input-group-text" for="descrip_cuentascont">Cuenta de Aplicacion</label>
                                <select class="form-select" id="descrip_cuentascont" aria-label="Cuenta de Aplicacion"
                                    aria-describedby="basic-addon1">
                                    <?php
                                    $consulta_cuentas = mysqli_query($conexion, "SELECT ccodcta, cdescrip, tipo, estado FROM ctb_nomenclatura WHERE LENGTH(ccodcta) = 1 AND estado = '1'");
                                    while ($fila_cuenta = mysqli_fetch_array($consulta_cuentas, MYSQLI_ASSOC)) {
                                        $opcion = $fila_cuenta['ccodcta'] . ' - ' . $fila_cuenta['cdescrip'];
                                        echo '<option value="' . $fila_cuenta['ccodcta'] . '">' . $opcion . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="input-group mb-3 mt-3">
                                <label class="input-group-text" for="claseSelect">Clase</label>
                                <select class="form-select" id="claseSelect" aria-label="Clase" aria-describedby="basic-addon1">
                                    <?php
                                    $consulta_clases = mysqli_query($conexion, "SELECT * FROM $db_name_general.ctb_cuentas_app;");
                                    while ($fila_clase = mysqli_fetch_array($consulta_clases, MYSQLI_ASSOC)) {
                                        echo '<option value="' . $fila_clase['id'] . '">' . $fila_clase['descripcion'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                    </div>
                    <div class="row">
                        <div class="col mb-3 d-flex justify-content-center">

                            <button type="button" class="button-85 me-4" onclick="update_count()">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Actualizar
                            </button>

                            <button type="button" class="btn btn-warning me-2" onclick="close_update()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- tabla para los  -->
        <div class="card">
            <div class="row mt-2 mb-4">
                <div class="col">
                    <div class="table-responsive">
                        <table class="table" id="tb_clase">
                            <thead>
                                <tr style="font-size: 0.8rem;">
                                    <th>#</th>
                                    <th>Cuenta de Aplicacion</th>
                                    <th>Clase</th>
                                    <th>Editar/Eliminar</th>
                                </tr>
                            </thead>
                            <tbody class="table-group-divider" style="font-size: 0.8rem;">
                                <?php
                                $consulta = mysqli_query($conexion, "SELECT 
                                    pc.id,
                                    pc.id_tipo,
                                    cn.ccodcta AS cod,
                                      CONCAT(cn.ccodcta, ' - ', cn.cdescrip) AS clase,
                                      ca.descripcion AS cuentas
                                     FROM ctb_parametros_cuentas pc
                                    LEFT JOIN $db_name_general.ctb_cuentas_app ca ON pc.clase = ca.id
                                    LEFT JOIN ctb_nomenclatura cn ON pc.id_tipo = cn.ccodcta;");
                                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) { ?>
                                    <tr>
                                        <td><?= $fila['id'] ?></td>
                                        <td><?= $fila['clase'] ?></td>
                                        <td><?= $fila['cuentas'] ?></td>

                                        <td>
                                            <button type="button" class="btn btn-success btn-sm"
                                                onclick="loadDataToForm('<?= $fila['id'] ?>', '<?= $fila['clase'] ?>', '<?= $fila['cuentas'] ?>')">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                onclick="delete_count('<?= $fila['id'] ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <div class="col align-items-center" id="btns_footer">
                <button type="button" class="btn btn-outline-danger" onclick="reinicio(0)">
                    <i class="fa-solid fa-ban"></i> Cancelar
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="salir()">
                    <i class="fa-solid fa-circle-xmark"></i> Salir
                </button>
            </div>
        </div>

        <!-- TERMINA  -->

        <script>
            //Datatable para parametrizacion
            $(document).ready(function() {
                convertir_tabla_a_datatable('tb_clase');
                HabDes_boton(0);
                $('#clase_add').select2({
                    placeholder: 'Selecciona una cuenta',
                    allowClear: true
                });
            });
        </script>

    <?php
        break;
    case 'agencies_by_sectors':
        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
    ?>
        <style>
            .select2-container--bootstrap-5 .select2-selection {
                width: 100%;
                min-height: calc(1.5em + .75rem + 2px);
                padding: 16px 12px;
                font-family: inherit;
                font-size: 1rem;
                font-weight: 400;
                line-height: 1.5;
                color: #212529;
                background-color: #fff;
                border: 1px solid #ced4da;
                border-radius: .375rem;
                transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none
            }
        </style>

        <input type="text" id="condi" value="agencies_by_sectors" hidden>
        <input type="text" id="file" value="ctb008" hidden>
        <div class="text" style="text-align:center">GESTIÓN DE AGENCIAS POR SECTORES</div>
        <div class="card mb-2">
            <div class="card-header">Gestión de agencias por sectores</div>
            <div class="card-body ">
                <div class="text-center mb-3">
                    <h5>Datos del sector</h5>
                </div>
                <!-- Seccion de inputs para edicion -->
                <div class="container contenedort" style="max-width: 100% !important;">
                    <!-- nombre y descripción-->
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-3 mt-2">
                                <input type="text" class="form-control" id="name" placeholder="name">
                                <input type="text" name="" id="id_sector" hidden>
                                <label for="name">Nombre</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-2">
                                <input type="text" class="form-control" id="description" placeholder="description">
                                <label for="description">Descripción</label>
                            </div>
                        </div>
                    </div>
                    <!-- Agencias -->
                    <div class="row">
                        <div class="col-12 mb-1">
                            <label for="agencies" class="fw-semibold">Agencias</label>
                        </div>
                        <div class="col-12 mb-3">
                            <select id="select2_agencies" multiple="multiple" class="form-select"
                                data-placeholder="Seleccione agencias" data-control="select2"
                                data-close-on-select="false">
                                <?php
                                $dataAgencies = [];
                                try {
                                    $database->openConnection();
                                    $dataAgencies = $database->getAllResults("SELECT ag.id_agencia AS id, ag.nom_agencia AS name FROM tb_agencia ag");
                                } catch (Exception $e) {
                                } finally {
                                    $database->closeConnection();
                                }
                                foreach ($dataAgencies as  $currentAgency) {
                                ?>
                                    <option value="<?= $currentAgency['id'] ?>"><?= $currentAgency['name'] ?></option>
                                <?php }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col d-flex align-items-center mb-2 gap-1" id="modal_footer">
                            <button type="button" class="btn btn-outline-success" id="btGuardar"
                                onclick="obtiene([`name`,`description`],[],[],`create_sector`,`0`,['<?= $codusu; ?>',getSelectedSelect2('select2_agencies')])">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btEditar" style="display: none;"
                                onclick="obtiene([`name`,`description`,`id_sector`],[],[],`update_sector`,`0`,['<?= $codusu; ?>',getSelectedSelect2('select2_agencies')])">
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

                <!-- Seccion de la tabla de agencias por sectores -->
                <div class="container contenedort" style="max-width: 100% !important;">
                    <div class="row mt-2 pb-2">
                        <div class="col">
                            <div class="table-responsive">
                                <table id="table-sectors" class="table table-hover table-border">
                                    <thead class="text-light table-head-ctb mt-2">
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th>Cantidad agencias</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tb_cuerpo_usuarios" style="font-size: 0.9rem !important;">
                                        <?php
                                        $dataAgenciesBySectors = [];
                                        try {
                                            $database->openConnection();
                                            $dataAgenciesBySectors = $database->getAllResults("SELECT ctbs.id, ctbs.nombre as name, ctbs.descripcion as description, IFNULL((SELECT COUNT(*) FROM ctb_sectores_agencia ctbsa WHERE ctbsa.id_sector=ctbs.id),0) AS countAgencies, (SELECT GROUP_CONCAT(ctbsa.id_agencia) FROM ctb_sectores_agencia ctbsa WHERE ctbsa.id_sector = ctbs.id) AS agencyIds FROM ctb_sectores ctbs WHERE ctbs.estado=1");
                                        } catch (Exception $e) {
                                        } finally {
                                            $database->closeConnection();
                                        }
                                        foreach ($dataAgenciesBySectors as $dataSector) {
                                            $name = $dataSector["name"];
                                            $description = $dataSector["description"];
                                            $countAgencies = $dataSector["countAgencies"];
                                            $agencyIds = $dataSector["agencyIds"];
                                            $idSector = $dataSector["id"];
                                        ?>
                                            <tr>
                                                <th scope="row"><?= $idSector ?></th>
                                                <td><?= $name ?></td>
                                                <td><?= $description ?></td>
                                                <td><?= $countAgencies ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-success btn-sm"
                                                        onclick="printdiv5('id_sector,name,description,select2_agencies/A,A,A,A/'+'/#/#/btEditar/btGuardar',['<?= $idSector ?>','<?= $name ?>','<?= $description ?>','<?= $agencyIds ?>'])"><i
                                                            class="fa-solid fa-eye"></i></button>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="eliminar('<?= $idSector ?>', 'crud_ctb', '0', 'delete_sector')"><i
                                                            class="fa-solid fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php

                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            //Datatable para parametrizacion
            $(document).ready(function() {
                convertir_tabla_a_datatable("table-sectors");
                var $select2 = $('#select2_agencies').select2({
                    theme: 'bootstrap-5',
                    language: "es" //soporte en español
                });
            });
        </script>
    <?php
    break;

    case 'parametros_generales':

        $xtra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
    ?>
        <style>
            .hidden {
                display: none;
            }
        </style>

        <div class="card" id="parametros_generales">
            <div class="card-header">Ingresar Nuevas cuentas contables a la Nomenclatura</div>
            <div class="card-body">
                <div class="contenedor container">
                    <div class="row">
                        <div class="col">

                            <div class="input-group mb-3 mt-3">
                                <span class="input-group-text" id="basic-add">Cuenta de Aplicacion</span>
                                <select class="form-select" id="ccodcta" aria-label="Cuenta de Aplicación"
                                    aria-describedby="basic-addon1">
                                    <?php
                                    $consulta_cuentas = mysqli_query($conexion, "SELECT ccodcta, cdescrip, tipo, estado FROM ctb_nomenclatura WHERE LENGTH(ccodcta) = 1 AND estado = '1'");
                                    while ($fila_cuenta = mysqli_fetch_array($consulta_cuentas, MYSQLI_ASSOC)) {
                                        $opcion = $fila_cuenta['ccodcta'] . ' - ' . $fila_cuenta['cdescrip'];
                                        echo '<option value="' . $fila_cuenta['ccodcta'] . '">' . $opcion . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-group mb-3 mt-3">
                                <label class="input-group-text" for="clase_add">Clase</label>
                                <select class="form-select" id="id" aria-label="Cuenta de Aplicación"
                                    aria-describedby="clase_add">
                                    <?php
                                    $consulta_cuentas = mysqli_query($conexion, "SELECT * FROM $db_name_general.ctb_cuentas_app;");
                                    while ($fila_cuenta2 = mysqli_fetch_array($consulta_cuentas, MYSQLI_ASSOC)) {
                                        echo '<option value="' . $fila_cuenta2['id'] . '">' . $fila_cuenta2['descripcion'] . '</option>';
                                    }
                                    ?>
                                </select>
                                <input type="text" placeholder="cdescrip" id="id_fuente_cdescrip" hidden>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3 d-flex justify-content-center">
                            <?php
                            $verifi_duplicate = mysqli_query($conexion, "SELECT id_tipo,clase FROM ctb_parametros_cuentas;")

                            ?>
                            <button type="button" class="button-85 me-4" onclick="verifi()">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-warning me-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="text" id="condi" value="fuenteFondos" hidden>
        <input type="text" id="file" value="ctb008" hidden>

        <div class="card hidden" id="form-container">
            <div class="card-header">Actualizar Clases de Cuentas</div>
            <div class="card-body">
                <!-- cuadro -->
                <div class="contenedort container">
                    <div class="row">
                        <div class="col">

                            <div class="input-group mb-3 mt-3" disabled>
                                <span class="input-group-text" id="basic-addon1">id</span>
                                <input type="text" class="form-control" id="id_load" placeholder="Cuenta de Aplicacion"
                                    aria-label="Username" aria-describedby="basic-addon1" readonly>
                            </div>
                            <div class="input-group mb-3 mt-3">
                                <label class="input-group-text" for="descrip_cuentascont">Cuenta de Aplicacion</label>
                                <select class="form-select" id="descrip_cuentascont" aria-label="Cuenta de Aplicacion"
                                    aria-describedby="basic-addon1">
                                    <?php
                                    $consulta_cuentas = mysqli_query($conexion, "SELECT ccodcta, cdescrip, tipo, estado FROM ctb_nomenclatura WHERE LENGTH(ccodcta) = 1 AND estado = '1'");
                                    while ($fila_cuenta = mysqli_fetch_array($consulta_cuentas, MYSQLI_ASSOC)) {
                                        $opcion = $fila_cuenta['ccodcta'] . ' - ' . $fila_cuenta['cdescrip'];
                                        echo '<option value="' . $fila_cuenta['ccodcta'] . '">' . $opcion . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="input-group mb-3 mt-3">
                                <label class="input-group-text" for="claseSelect">Clase</label>
                                <select class="form-select" id="claseSelect" aria-label="Clase" aria-describedby="basic-addon1">
                                    <?php
                                    $consulta_clases = mysqli_query($conexion, "SELECT * FROM $db_name_general.ctb_cuentas_app;");
                                    while ($fila_clase = mysqli_fetch_array($consulta_clases, MYSQLI_ASSOC)) {
                                        echo '<option value="' . $fila_clase['id'] . '">' . $fila_clase['descripcion'] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                    </div>
                    <div class="row">
                        <div class="col mb-3 d-flex justify-content-center">

                            <button type="button" class="button-85 me-4" onclick="update_count()">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Actualizar
                            </button>

                            <button type="button" class="btn btn-warning me-2" onclick="close_update()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- tabla para los  -->
        <div class="card">
            <div class="row mt-2 mb-4">
                <div class="col">
                    <div class="table-responsive">
                        <table class="table" id="tb_clase">
                            <thead>
                                <tr style="font-size: 0.8rem;">
                                    <th>#</th>
                                    <th>Cuenta de Aplicacion</th>
                                    <th>Clase</th>
                                    <th>Editar/Eliminar</th>
                                </tr>
                            </thead>
                            <tbody class="table-group-divider" style="font-size: 0.8rem;">
                                <?php
                                $consulta = mysqli_query($conexion, "SELECT 
                                    pc.id,
                                    pc.id_tipo,
                                    cn.ccodcta AS cod,
                                      CONCAT(cn.ccodcta, ' - ', cn.cdescrip) AS clase,
                                      ca.descripcion AS cuentas
                                     FROM ctb_parametros_cuentas pc
                                    LEFT JOIN $db_name_general.ctb_cuentas_app ca ON pc.clase = ca.id
                                    LEFT JOIN ctb_nomenclatura cn ON pc.id_tipo = cn.ccodcta;");
                                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) { ?>
                                    <tr>
                                        <td><?= $fila['id'] ?></td>
                                        <td><?= $fila['clase'] ?></td>
                                        <td><?= $fila['cuentas'] ?></td>

                                        <td>
                                            <button type="button" class="btn btn-success btn-sm"
                                                onclick="loadDataToForm('<?= $fila['id'] ?>', '<?= $fila['clase'] ?>', '<?= $fila['cuentas'] ?>')">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                onclick="delete_count('<?= $fila['id'] ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <div class="col align-items-center" id="btns_footer">
                <button type="button" class="btn btn-outline-danger" onclick="reinicio(0)">
                    <i class="fa-solid fa-ban"></i> Cancelar
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="salir()">
                    <i class="fa-solid fa-circle-xmark"></i> Salir
                </button>
            </div>
        </div>

        <!-- TERMINA  -->

        <script>
            //Datatable para parametrizacion
            $(document).ready(function() {
                convertir_tabla_a_datatable('tb_clase');
                HabDes_boton(0);
                $('#clase_add').select2({
                    placeholder: 'Selecciona una cuenta',
                    allowClear: true
                });
            });
        </script>

    <?php
        break;

}
?>