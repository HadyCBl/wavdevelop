<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
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
// require_once __DIR__ . '/../../../includes/Config/Configuracion.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Configuracion;
use App\Generic\Agencia;
use App\Generic\DocumentManager;
use Micro\Generic\Moneda;
use Micro\Generic\PermissionManager;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
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

$agenciaData = new Agencia($idagencia);

$puestouser = $_SESSION["puesto"];
// $puestouser = "LOG";
$condi = $_POST["condi"];
switch ($condi) {
    case 'apertura_caja': {
            $xtra = $_POST["xtra"];
            /**
             * Este bloque de código maneja la vista de apertura de caja, con conexión a la base de datos usando la clase Database de tipo PDO, 
             * para la verificación de permisos de usuario y la obtención de una lista de usuarios 
             * según los permisos del usuario actual. Si no se encuentran usuarios, se lanza una excepción.
             * 
             * Variables:
             * @var bool $showmensaje Indica si se debe mostrar un mensaje de error específico, o un codigo de error que se guarda en el archivo errores log.
             * @var array $permisos Lista de permisos obtenidos de la base de datos.
             * @var bool $permisogeneral Indica si el usuario tiene permisos generales.
             * @var array $users Lista de usuarios obtenidos de la base de datos, segun las condiciones o permisos del usuario.
             * @var int $status Estado final del proceso (1: éxito, 0: error).
             * @var string $mensaje Mensaje de error a mostrar.
             * 
             * Excepciones:
             * @throws Exception Si no se encuentran usuarios por revisar.
             * 
             * Flujo:
             * 1. Abre una conexión a la base de datos.
             * 2. Obtiene los permisos del usuario actual.
             * 3. Determina si el usuario tiene permisos generales.
             * 4. Obtiene la lista de usuarios según los permisos del usuario actual.
             * 5. Si no se encuentran usuarios, lanza una excepción.
             * 6. Cierra la conexión a la base de datos.
             * 7. Maneja cualquier excepción lanzada durante el proceso.
             */
            $mediumPermissionId = 5; // Permiso de apertura de caja a nivel de agencia
            $highPermissionId = 6;   // Permiso de apertura de caja a nivel general [Todas las agencias]
            $showmensaje = false;
            try {
                $database->openConnection();
                $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (5,6) AND id_usuario=? AND estado=1", [$idusuario]);
                $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

                $condicion = ($accessHandler->isHigh()) ? "estado=1" : (($accessHandler->isMedium()) ? "estado=1 AND id_agencia=?" : "estado=1 AND id_usu=?");
                $parametros = ($accessHandler->isHigh()) ? [] : (($accessHandler->isMedium()) ? [$idagencia] : [$idusuario]);

                $users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], $condicion, $parametros);
                if (empty($users)) {
                    $showmensaje = true;
                    throw new Exception("No existen usuarios por revisar");
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

            $enabledFechaCaja = $_ENV['CAJA_FECHA_ENABLED'] ?? 0;

            // $userPermissions = new PermissionManager($idusuario);

            // if ($userPermissions->isLevelTwo(PermissionManager::APERTURA_CAJA)) {
            //     // permiso para apertura de caja nivel general
            // } elseif ($userPermissions->isLevelOne(PermissionManager::APERTURA_CAJA)) {
            //     $condiPermission = " AND ag.id_agencia = $idagencia ";
            //     // permiso para apertura de caja nivel agencia
            // } else {
            //     $condiPermission = " AND cm.CodAnal = $idusuario ";
            //     // permiso para apertura de caja nivel usuario, no tiene ningun permiso, solo aperturar su propia caja
            // }
            // echo "<pre>";
            // echo print_r($permisos);
            // echo "</pre>";
?>
            <input type="text" id="condi" value="apertura_caja" hidden>
            <input type="text" id="file" value="caja001" hidden>

            <div class="text" style="text-align:center">APERTURA DE CAJA</div>
            <div class="card">
                <div class="card-header">Apertura de caja</div>
                <div class="card-body">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>¡Error!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="container contenedort" style="max-width: 100% !important;" id="formAperturaCaja">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Apertura de caja</b></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select required class="form-select" id="usuario" name="usuario" <?= ($accessHandler->isLow()) ? 'disabled' : ''; ?>>
                                        <option selected disabled value="0">Seleccione un usuario</option>
                                        <?php foreach ($users as $user) {
                                            $selected = ($user['id_usu'] == $idusuario) ? "selected" : ""; ?>
                                            <option value="<?= $user['id_usu']; ?>" <?= $selected; ?>><?= $user['nombre'] . ' ' . $user['apellido']; ?></option>
                                        <?php } ?>
                                    </select>
                                    <label for="usuario">Usuario</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="date" class="form-control" id="fec_apertura" placeholder="Fecha de apertura"
                                        <?= ($enabledFechaCaja == 1 && !$accessHandler->isLow()) ? '' : 'readonly'; ?>
                                        value="<?= date('Y-m-d'); ?>" required>
                                    <label for="fec_apertura">Fecha de apertura</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="input-group mb-2 mt-2">
                                    <span class="input-group-text bg-primary text-white" style="border: none !important;"
                                        id="basic-addon1"><i class="fa-solid fa-money-bill"></i></span>
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="saldoinicial" placeholder="Saldoinicial"
                                            step="0.01" required min="0">
                                        <label for="saldoinicial">Digite su saldo inicial</label>
                                    </div>
                                </div>
                            </div>
                            <!-- <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="origen_dinero" name="origen_dinero" required>
                                        <option value="" disabled>Seleccione el origen del dinero</option>
                                        <option value="another" selected>Por defecto</option>
                                        <option value="boveda">Bóveda</option>
                                    </select>
                                    <label for="origen_dinero">Origen del dinero</label>
                                </div>
                            </div> -->
                        </div>
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mt-2 mb-2" id="modal_footer">
                                <button class="btn btn-outline-success"
                                    onclick="obtiene([`fec_apertura`, `saldoinicial`], [`usuario`], [], 'create_caja_apertura', `0`, []);"><i
                                        class="fa-solid fa-box-open me-2"></i></i>Aperturar</button>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"><i
                                        class="fa-solid fa-ban"></i> Cancelar</button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()"><i
                                        class="fa-solid fa-circle-xmark"></i> Salir</button>
                            </div>
                        </div>
                    </div>

                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Historial de cierres y aperturas del mes</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="table-responsive">
                                    <table class="table nowrap table-hover table-border" id="tb_aperturas_cierres"
                                        style="width: 100% !important;">
                                        <thead class="text-light table-head-aprt">
                                            <tr style="font-size: 0.9rem;">
                                                <th>#</th>
                                                <th>User</th>
                                                <th>Fecha de apertura</th>
                                                <th>Saldo inicial</th>
                                                <th>Fecha de cierre</th>
                                                <th>Saldo final</th>
                                                <th>Estado</th>
                                                <th>R. arqueo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-group-divider" style="font-size: 0.9rem !important;">

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$accessHandler->isLow()) { ?>
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Historial de cierres y aperturas del mes consolidados</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <div class="table-responsive">
                                        <table class="table nowrap table-hover table-border" id="tb_aperturas_cierres_conso"
                                            style="width: 100% !important;">
                                            <thead class="text-light table-head-aprt">
                                                <tr style="font-size: 0.9rem;">
                                                    <th>#</th>
                                                    <th>Fecha</th>
                                                    <th>Agencia</th>
                                                    <th>Saldo inicial</th>
                                                    <th>Cierre</th>
                                                    <th>Saldo final</th>
                                                    <th>Estado</th>
                                                    <th>R. arqueo</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider" style="font-size: 0.9rem !important;">

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $(document).ready(function() {
                                $('#tb_aperturas_cierres_conso').on('search.dt').DataTable({
                                    "aProcessing": true,
                                    "aServerSide": true,
                                    "ordering": false,
                                    "lengthMenu": [
                                        [10],
                                        ['10 filas']
                                    ],
                                    "ajax": {
                                        url: '../../src/cruds/crud_caja.php',
                                        type: "POST",
                                        beforeSend: function() {
                                            loaderefect(1);
                                        },
                                        data: {
                                            'condi': "listado_aperturas_conso"
                                        },
                                        dataType: "json",
                                        complete: function(response) {
                                            // console.log(response);
                                            loaderefect(0);
                                            if (response.responseJSON.error != undefined) {
                                                Swal.fire('Error', response.responseJSON.error, 'error')
                                            }
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
                    <?php } ?>
                </div>
                <script>
                    $(document).ready(function() {
                        $('#tb_aperturas_cierres').on('search.dt').DataTable({
                            "aProcessing": true,
                            "aServerSide": true,
                            "ordering": false,
                            "lengthMenu": [
                                [10],
                                ['10 filas']
                            ],
                            "ajax": {
                                url: '../../src/cruds/crud_caja.php',
                                type: "POST",
                                beforeSend: function() {
                                    loaderefect(1);
                                },
                                data: {
                                    'condi': "listado_aperturas"
                                },
                                dataType: "json",
                                complete: function(response) {
                                    // console.log(response);
                                    loaderefect(0);
                                    if (response.responseJSON.error != undefined) {
                                        Swal.fire('Error', response.responseJSON.error, 'error')
                                    }
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

                        inicializarValidacionAutomaticaGeneric('#formAperturaCaja');
                    });
                </script>
            <?php
        }
        break;

    case 'cierre_caja':
        $id_agencia = $_SESSION["id_agencia"];
        $nomagencia = $_SESSION["nomagencia"];
        $datos[] = [];


        $mediumPermissionId = 7; // Permiso de apertura de caja a nivel de agencia
        $highPermissionId = 8;   // Permiso de apertura de caja a nivel general [Todas las agencias]

        $movBancosPermissionId = 14; //Permiso de movimientos bancarios

        $showmensaje = false;
        // $datos[] = [];
        try {
            $database->openConnection();
            $configuracion = new Configuracion($database);
            $valor = $configuracion->getValById(1);
            $camposFechas = ($valor === '1')
                ? ["a.created_at", "b.created_at", "c.created_at", "d.created_at", "ck.DFECSIS", "ckk.DFECSIS", "ck2.DFECSIS", "op.created_at", "op.created_at", "ck.DFECSIS", "ckk.DFECSIS", "ck2.DFECSIS"]
                : ["a.dfecope", "b.dfecope", "c.dfecope", "d.dfecope", "ck.DFECPRO", "ckk.DFECPRO", "ck2.DFECPRO", "op.fecha", "op.fecha", "ck.DFECPRO", "ckk.DFECPRO", "ck2.DFECPRO"];
            $op1 = "SELECT tcac.*, tu.nombre AS nombres, tu.apellido AS apellidos, tu.usu AS usuario, 
				(SELECT IFNULL(SUM(a.monto) ,0) FROM ahommov a WHERE a.ctipdoc='E' AND a.cestado!=2 AND a.ctipope = 'D' AND DATE({$camposFechas[0]}) = tcac.fecha_apertura AND a.created_by = tcac.id_usuario) AS ingresos_ahorros,
				(SELECT IFNULL(SUM(b.monto) ,0) FROM ahommov b WHERE b.ctipdoc='E' AND b.cestado!=2 AND b.ctipope = 'R' AND DATE({$camposFechas[1]}) = tcac.fecha_apertura AND b.created_by = tcac.id_usuario) AS egresos_ahorros,
				(SELECT IFNULL(SUM(c.monto) ,0)+IFNULL(SUM(c.cuota_ingreso) ,0) FROM aprmov c WHERE c.ctipdoc='E' AND c.cestado!=2 AND c.ctipope = 'D' AND DATE({$camposFechas[2]}) = tcac.fecha_apertura AND c.created_by = tcac.id_usuario) AS ingresos_aportaciones,
				(SELECT IFNULL(SUM(d.monto) ,0) FROM aprmov d WHERE d.ctipdoc='E' AND d.cestado!=2 AND d.ctipope = 'R' AND DATE({$camposFechas[3]}) = tcac.fecha_apertura AND d.created_by = tcac.id_usuario) AS egresos_aportaciones,
				(SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='1' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[4]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos,
				(SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='1' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[5]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2,
				(SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='1' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[6]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos,
				(SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '1' AND DATE({$camposFechas[7]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_ingresos,
				(SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '2' AND DATE({$camposFechas[8]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_egresos,
                (SELECT IFNULL(SUM(haber),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (10,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS egresosbancos,
                (SELECT IFNULL(SUM(debe),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (6,7) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS ingresosbancos,
                (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=1 AND id_caja = tcac.id) AS ingresos_caja,    
                (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=2 AND id_caja = tcac.id) AS egresos_caja,
                (SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='2' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[9]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos_bancos,
				(SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='2' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[10]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2_bancos,
				(SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='2' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[11]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos_bancos

				FROM tb_caja_apertura_cierre tcac INNER JOIN tb_usuario tu ON tcac.id_usuario = tu.id_usu 
				WHERE (tcac.id_usuario =? AND tcac.fecha_apertura=? AND tcac.estado='1') OR (tcac.id_usuario=? AND tcac.fecha_apertura < ? AND tcac.estado='1') ORDER BY tcac.fecha_apertura ASC LIMIT 1";

            $op2 = "SELECT tcac.*, tu.nombre AS nombres, tu.apellido AS apellidos, tu.usu AS usuario, 
				(SELECT IFNULL(SUM(a.monto) ,0) FROM ahommov a WHERE a.ctipdoc='E' AND a.cestado!=2 AND a.ctipope = 'D' AND DATE({$camposFechas[0]}) = tcac.fecha_apertura AND a.created_by = tcac.id_usuario) AS ingresos_ahorros,
				(SELECT IFNULL(SUM(b.monto) ,0) FROM ahommov b WHERE b.ctipdoc='E' AND b.cestado!=2 AND b.ctipope = 'R' AND DATE({$camposFechas[1]}) = tcac.fecha_apertura AND b.created_by = tcac.id_usuario) AS egresos_ahorros,
				(SELECT IFNULL(SUM(c.monto) ,0)+IFNULL(SUM(c.cuota_ingreso) ,0) FROM aprmov c WHERE c.ctipdoc='E' AND c.cestado!=2 AND c.ctipope = 'D' AND DATE({$camposFechas[2]}) = tcac.fecha_apertura AND c.created_by = tcac.id_usuario) AS ingresos_aportaciones,
				(SELECT IFNULL(SUM(d.monto) ,0) FROM aprmov d WHERE d.ctipdoc='E' AND d.cestado!=2 AND d.ctipope = 'R' AND DATE({$camposFechas[3]}) = tcac.fecha_apertura AND d.created_by = tcac.id_usuario) AS egresos_aportaciones,
				(SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='1' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[4]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos,
                (SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='1' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[5]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2,
				(SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='1' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[6]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos,
				(SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '1' AND DATE({$camposFechas[7]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_ingresos,
				(SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '2' AND DATE({$camposFechas[8]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_egresos,
                (SELECT IFNULL(SUM(haber),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (10,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS egresosbancos,
                (SELECT IFNULL(SUM(debe),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (6,7) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS ingresosbancos,
                (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=1 AND id_caja = tcac.id) AS ingresos_caja,    
                (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=2 AND id_caja = tcac.id) AS egresos_caja,
                (SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='2' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[9]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos_bancos,
                (SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='2' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[10]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2_bancos,
				(SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='2' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[11]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos_bancos

                FROM tb_caja_apertura_cierre tcac INNER JOIN tb_usuario tu ON tcac.id_usuario = tu.id_usu 
				WHERE ((tcac.fecha_apertura<=? AND tcac.estado=1) OR (tcac.fecha_apertura=?)) AND tu.id_agencia=? ORDER BY tcac.fecha_apertura ASC";

            $op3 = "SELECT tcac.*, tu.nombre AS nombres, tu.apellido AS apellidos, tu.usu AS usuario, 
				(SELECT IFNULL(SUM(a.monto) ,0) FROM ahommov a WHERE a.ctipdoc='E' AND a.cestado!=2 AND a.ctipope = 'D' AND DATE({$camposFechas[0]}) = tcac.fecha_apertura AND a.created_by = tcac.id_usuario) AS ingresos_ahorros,
				(SELECT IFNULL(SUM(b.monto) ,0) FROM ahommov b WHERE b.ctipdoc='E' AND b.cestado!=2 AND b.ctipope = 'R' AND DATE({$camposFechas[1]}) = tcac.fecha_apertura AND b.created_by = tcac.id_usuario) AS egresos_ahorros,
				(SELECT IFNULL(SUM(c.monto) ,0)+IFNULL(SUM(c.cuota_ingreso) ,0) FROM aprmov c WHERE c.ctipdoc='E' AND c.cestado!=2 AND c.ctipope = 'D' AND DATE({$camposFechas[2]}) = tcac.fecha_apertura AND c.created_by = tcac.id_usuario) AS ingresos_aportaciones,
				(SELECT IFNULL(SUM(d.monto) ,0) FROM aprmov d WHERE d.ctipdoc='E' AND d.cestado!=2 AND d.ctipope = 'R' AND DATE({$camposFechas[3]}) = tcac.fecha_apertura AND d.created_by = tcac.id_usuario) AS egresos_aportaciones,
				(SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='1' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[4]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos,
                (SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='1' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[5]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2,
				(SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='1' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[6]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos,
				(SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '1' AND DATE({$camposFechas[7]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_ingresos,
				(SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '2' AND DATE({$camposFechas[8]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_egresos,
                (SELECT IFNULL(SUM(haber),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (10,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS egresosbancos,
                (SELECT IFNULL(SUM(debe),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (7,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS ingresosbancos,
                (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=1 AND id_caja = tcac.id) AS ingresos_caja,    
                (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=2 AND id_caja = tcac.id) AS egresos_caja,
                (SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='2' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[9]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos_bancos,
                (SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='2' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[10]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2_bancos,
				(SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='2' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[11]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos_bancos

                FROM tb_caja_apertura_cierre tcac INNER JOIN tb_usuario tu ON tcac.id_usuario = tu.id_usu 
				WHERE ((tcac.fecha_apertura<=? AND tcac.estado=1) OR (tcac.fecha_apertura=?)) ORDER BY tcac.fecha_apertura ASC";

            $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (4,7,8,14) AND id_usuario=? AND estado=1", [$idusuario]);
            $accessHandler = new PermissionHandler($permisos, $mediumPermissionId, $highPermissionId);

            $queryfinal = ($accessHandler->isHigh()) ? $op3 : (($accessHandler->isMedium()) ? $op2 : $op1);
            $parametros = ($accessHandler->isHigh()) ? [$hoy, $hoy] : (($accessHandler->isMedium()) ? [$hoy, $hoy, $id_agencia] : [$idusuario, $hoy, $idusuario, $hoy]);
            $titleadmin = ($accessHandler->isHigh()) ? " Todas las agencias" : (($accessHandler->isMedium()) ? ", Agencia: $nomagencia" : " " . $_SESSION["nombre"] . " " . $_SESSION["apellido"]);

            $datos = $database->getAllResults($queryfinal, $parametros);

            // $permisos2 = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (4) AND id_usuario=? AND estado=1", [$idusuario]);
            $SepaDesembolsos = new PermissionHandler($permisos, 4);

            $movBancos = new PermissionHandler($permisos, 14);

            $nameColumn = ($SepaDesembolsos->isLow()) ? "desembolsos_creditos" : "desembolsos_creditos2";

            if (!empty($datos)) {
                $i = 0;
                foreach ($datos as $fila) {
                    // $datos[$i] = $fila;
                    $datos[$i]['mensajeestado'] = ($fila['fecha_apertura'] < date('Y-m-d')) ? '<span class="badge text-bg-danger">Cierre pendiente con atraso</span>'  : '<span class="badge text-bg-success">Cierre pendiente</span>';
                    $datos[$i]['mensajeestado'] = ($fila["estado"] == 2) ? '<span class="badge text-bg-success">Cerrada</span>' : (($fila['fecha_apertura'] < date('Y-m-d')) ? '<span class="badge text-bg-danger">Cierre pendiente con atraso</span>'  : '<span class="badge text-bg-warning">Cierre pendiente</span>');

                    $datos[$i]['pagos_creditos'] = ($movBancos->isLow()) ? $datos[$i]['pagos_creditos'] : ($datos[$i]['pagos_creditos_bancos'] + $datos[$i]['pagos_creditos']);
                    $datos[$i][$nameColumn] = ($movBancos->isLow()) ? $datos[$i][$nameColumn] : ($datos[$i]['desembolsos_creditos_bancos'] + $datos[$i][$nameColumn]);
                    $datos[$i]['egresosbancos'] = ($movBancos->isLow()) ? $datos[$i]['egresosbancos'] : ($datos[$i]['egresosbancos'] + $datos[$i]['pagos_creditos_bancos']);

                    $datos[$i]['sumaingresos'] = ($datos[$i]['saldo_inicial'] + $datos[$i]['ingresos_ahorros'] + $datos[$i]['ingresos_aportaciones'] + $datos[$i]['pagos_creditos'] + $datos[$i]['otros_ingresos'] + $datos[$i]['ingresosbancos'] + $datos[$i]['ingresos_caja']);
                    $datos[$i]['sumaegresos'] = ($datos[$i]['egresos_ahorros'] + $datos[$i]['egresos_aportaciones'] + $datos[$i][$nameColumn] + $datos[$i]['otros_egresos'] + $datos[$i]['egresosbancos'] + $datos[$i]['egresos_caja']);
                    $datos[$i]['saldofinal'] = ($datos[$i]['sumaingresos'] - abs($datos[$i]['sumaegresos']));
                    $datos[$i]['sumasiguales'] = ($datos[$i]['sumaegresos'] + $datos[$i]['saldofinal']);
                    $i++;
                }
            }
            $bandera = (!empty($datos)) ? true : false;
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

        $mensajegeneral = ($bandera && in_array(1, array_column($datos, "estado"))) ? '<span class="badge text-bg-warning">Cierre pendiente</span>' : '<span class="badge text-bg-success">No hay cierres pendientes</span>';
        $print[] = [];

        if ($status) {
            $print = [
                //armar una matriz de 4 columnas, uno para numeral, descripcion, ingreso, egreso
                ['color' => 'table-info', 'descripcion' => 'Saldo inicial', 'ingreso' => ('Q ' . number_format(array_sum(array_column($datos, "saldo_inicial")), 2)), 'egreso' => ' '],
                ['color' => 'table-secondary', 'descripcion' => 'Depósitos de ahorros', 'ingreso' => ('Q ' . number_format(array_sum(array_column($datos, "ingresos_ahorros")), 2)), 'egreso' => ' '],
                ['color' => 'table-secondary', 'descripcion' => 'Depósitos de aportaciones', 'ingreso' => ('Q ' . number_format(array_sum(array_column($datos, "ingresos_aportaciones")), 2)), 'egreso' => ' '],
                ['color' => 'table-secondary', 'descripcion' => 'Pagos de creditos', 'ingreso' => ('Q ' . number_format(array_sum(array_column($datos, "pagos_creditos")), 2)), 'egreso' => ' '],
                ['color' => 'table-secondary', 'descripcion' => 'Otros ingresos', 'ingreso' => ('Q ' . number_format(array_sum(array_column($datos, "otros_ingresos")), 2)), 'egreso' => ' '],
                ['color' => 'table-secondary', 'descripcion' => 'Cheques a caja', 'ingreso' => ('Q ' . number_format(array_sum(array_column($datos, "ingresosbancos")), 2)), 'egreso' => ' '],
                ['color' => 'table-secondary', 'descripcion' => 'Incrementos de caja', 'ingreso' => ('Q ' . number_format(array_sum(array_column($datos, "ingresos_caja")), 2)), 'egreso' => ' '],
                ['color' => 'table-secondary', 'descripcion' => 'Retiros de ahorros', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format(array_sum(array_column($datos, "egresos_ahorros")), 2))],
                ['color' => 'table-secondary', 'descripcion' => 'Retiros de aportaciones', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format(array_sum(array_column($datos, "egresos_aportaciones")), 2))],
                ['color' => 'table-secondary', 'descripcion' => 'Desembolsos de creditos en efectivo', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format(array_sum(array_column($datos, $nameColumn)), 2))],
                ['color' => 'table-secondary', 'descripcion' => 'Otros egresos', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format(array_sum(array_column($datos, "otros_egresos")), 2))],
                ['color' => 'table-secondary', 'descripcion' => 'Depósitos a bancos', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format(array_sum(array_column($datos, "egresosbancos")), 2))],
                ['color' => 'table-secondary', 'descripcion' => 'Decrementos de caja', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format(array_sum(array_column($datos, "egresos_caja")), 2))]
            ];
        }

        // echo "<pre>";
        // echo print_r($camposFechas);
        // echo "</pre>";

        // echo "<pre>";
        // echo print_r($parametros);
        // echo "</pre>";

        if ($accessHandler->isMedium() || $accessHandler->isHigh()) {

            foreach ($datos as $key22 => $value22) {
                $printEvery[$key22] = [
                    'id' => $value22['id'],
                    'nombre' => $value22['nombres'],
                    'apellido' => $value22['apellidos'],
                    'usuario' => $value22['usuario'],
                    'fecha' => $value22['fecha_apertura'],
                    'saldo_inicial' => $value22['saldo_inicial'],
                    'saldo_final' => $value22['saldofinal'],
                    'estado' => $value22['mensajeestado'],
                    'status' => $value22['estado'],
                    'sumasiguales' => $value22['sumasiguales'],
                    'sumaingresos' => $value22['sumaingresos'],
                    'sumaegresos' => $value22['sumaegresos'],
                    'print' => [
                        ['color' => 'table-info', 'descripcion' => 'Saldo inicial', 'ingreso' => ('Q ' . number_format($value22['saldo_inicial'], 2)), 'egreso' => ' '],
                        ['color' => 'table-secondary', 'descripcion' => 'Depósitos de ahorros', 'ingreso' => ('Q ' . number_format($value22['ingresos_ahorros'], 2)), 'egreso' => ' '],
                        ['color' => 'table-secondary', 'descripcion' => 'Depósitos de aportaciones', 'ingreso' => ('Q ' . number_format($value22['ingresos_aportaciones'], 2)), 'egreso' => ' '],
                        ['color' => 'table-secondary', 'descripcion' => 'Pagos de creditos', 'ingreso' => ('Q ' . number_format($value22['pagos_creditos'], 2)), 'egreso' => ' '],
                        ['color' => 'table-secondary', 'descripcion' => 'Otros ingresos', 'ingreso' => ('Q ' . number_format($value22['otros_ingresos'], 2)), 'egreso' => ' '],
                        ['color' => 'table-secondary', 'descripcion' => 'Cheques a caja', 'ingreso' => ('Q ' . number_format($value22['ingresosbancos'], 2)), 'egreso' => ' '],
                        ['color' => 'table-secondary', 'descripcion' => 'Incremento de caja', 'ingreso' => ('Q ' . number_format($value22['ingresos_caja'], 2)), 'egreso' => ' '],
                        ['color' => 'table-secondary', 'descripcion' => 'Retiros de ahorros', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format($value22['egresos_ahorros'], 2))],
                        ['color' => 'table-secondary', 'descripcion' => 'Retiros de aportaciones', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format($value22['egresos_aportaciones'], 2))],
                        ['color' => 'table-secondary', 'descripcion' => 'Desembolsos de creditos en efectivo', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format($value22[$nameColumn], 2))],
                        ['color' => 'table-secondary', 'descripcion' => 'Otros egresos', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format($value22['otros_egresos'], 2))],
                        ['color' => 'table-secondary', 'descripcion' => 'Depósitos a bancos', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format($value22['egresosbancos'], 2))],
                        ['color' => 'table-secondary', 'descripcion' => 'Decrementos de caja', 'ingreso' => ' ', 'egreso' => ('Q ' . number_format($value22['egresos_caja'], 2))]
                    ]
                ];
            }
        }

        // echo "<pre>";
        // echo print_r($printEvery);
        // echo "</pre>";

            ?>
            <input type="text" id="condi" value="cierre_caja" hidden>
            <input type="text" id="file" value="caja001" hidden>

            <div class="text" style="text-align:center">CIERRE DE CAJA</div>
            <div class="card">
                <div class="card-header">Cierre de caja</div>
                <div class="card-body">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>¡Error!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <?php if (!in_array(1, array_column($datos, "estado"))) { ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>¡Bienvenido!</strong> <?= 'No se tienen ningun cierre por realizar, todo esta bien'; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php }

                    //IMPRIMIR ESTA VISTA SI EL USUARIO NO ES ADMIN O SIN PRIVILEGIOS
                    ?>
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Cierre de caja</b></div>
                            </div>
                        </div>

                        <?php
                        if ($bandera) { ?>
                            <div class="row">
                                <div class="col mt-3 mb-3">
                                    <div class="text-center">Estado: <?= ($bandera) ? $mensajegeneral : "" ?>
                                    </div>
                                </div>
                            </div>
                            <?php echo $csrf->getTokenField(); ?>
                        <?php }
                        if ($accessHandler->isLow()) {
                        ?>
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" id="saldoinicial" readonly hidden <?= ($bandera) ? 'value="' . array_sum(array_column($datos, "saldo_inicial")) . '"' : "" ?>>
                                        <input type="text" id="saldofinal" readonly hidden <?= ($bandera) ? 'value="' . array_sum(array_column($datos, "saldofinal")) . '"' : "" ?>>
                                        <input type="text" id="iduser" readonly hidden <?= ($bandera) ? 'value="' . $datos[0]['id_usuario'] . '"' : "" ?>>
                                        <input class="form-control" type="text" id="nomuser" placeholder="Nombre de usuario" readonly <?= ($bandera) ? 'value="' . ($datos[0]['nombres'])  . '"' : "" ?>>
                                        <label for="nomuser">Nombres</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="nomape" placeholder="Apellido de usuario"
                                            readonly <?= ($bandera) ? 'value="' . ($datos[0]['apellidos']) . '"' : "" ?>>
                                        <label for="nomape">Apellidos</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12 col-md-4">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="user" placeholder="Usuario" readonly
                                            <?= ($bandera) ? 'value="' . $datos[0]['usuario'] . '"' : "" ?>>
                                        <label for="user">Usuario</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="date" class="form-control" id="fec_apertura" placeholder="Fecha de apertura"
                                            readonly <?= ($bandera) ? 'value="' . $datos[0]['fecha_apertura'] . '"' : "" ?>>
                                        <label for="fec_apertura">Fecha de apertura</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="date" class="form-control" id="fec_cierre" placeholder="Fecha de cierre"
                                            readonly <?= ($bandera) && print('value="' . date('Y-m-d') . '"'); ?>>
                                        <label for="fec_cierre">Fecha de cierre</label>
                                    </div>
                                </div>
                            </div>
                        <?php }
                        ?>
                    </div>
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Resumen de movimientos<?= $titleadmin ?></b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="table-responsive">
                                    <table class="table nowrap table-borderless table-hover" id="tb_aperturas_cierres2"
                                        style="width: 100% !important;">
                                        <thead class="table-light">
                                            <tr style="font-size: 0.9rem; border-bottom: 3px solid #000 !important; ">
                                                <th scope="col" style="width: 5%">#</th>
                                                <th scope="col" class="text-center">Descripción</th>
                                                <th scope="col" class="text-center">Ingresos</th>
                                                <th scope="col" class="text-center">Egresos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($status) {
                                                $i = 0;
                                                foreach ($print as $key => $fila) {
                                                    $i++;
                                            ?>
                                                    <tr>
                                                        <th scope="row"><?= $key + 1 ?></th>
                                                        <td class="<?= $fila['color']; ?>"><span><?= $fila['descripcion'] ?></span></td>
                                                        <td class="text-end"><?= $fila['ingreso']; ?></td>
                                                        <td class="text-end"><?= $fila['egreso']; ?></td>
                                                    </tr>

                                            <?php }
                                            } ?>
                                            <tr style="border-top: 3px solid #000 !important;">
                                                <th scope="row"></th>
                                                <td class="table-info"><span>Subtotales</span></td>
                                                <td class="text-end">
                                                    <?= ($bandera) ? ('Q ' . number_format(array_sum(array_column($datos, "sumaingresos")), 2, '.', ',')) : ''; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= ($bandera) ? ('Q ' . number_format(array_sum(array_column($datos, "sumaegresos")), 2, '.', ',')) : ''; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"></th>
                                                <td class="table-success"><span>Saldo final</span></td>
                                                <td class="text-end"><?= ($bandera) ? ('Q 0.00') : ''; ?></td>
                                                <td class="text-end">
                                                    <b><?= ($bandera) ? ('Q ' . number_format(array_sum(array_column($datos, "saldofinal")), 2, '.', ',')) : ''; ?></b>
                                                </td>
                                            </tr>
                                            <tr style="border-top: 3px solid #000 !important;" class="table-warning">
                                                <td colspan="2" class="text-center"><span><b>Sumas iguales</b></span></td>
                                                <td class="text-end">
                                                    <b><?= ($bandera) ? ('Q ' . number_format(array_sum(array_column($datos, "sumasiguales")), 2, '.', ',')) : ''; ?></b>
                                                </td>
                                                <td class="text-end">
                                                    <b><?= ($bandera) ? ('Q ' . number_format(array_sum(array_column($datos, "sumasiguales")), 2, '.', ',')) : ''; ?></b>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center mt-2 mb-2" id="modal_footer">
                            <?php if ($bandera && $accessHandler->isLow()) { ?>
                                <button class="btn btn-outline-primary"
                                    onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'create_caja_cierre', `0`, ['<?= htmlspecialchars($secureID->encrypt($datos[0]['id'])) ?>',<?= $datos[0]['saldo_inicial']; ?>,<?= $datos[0]['saldofinal']; ?>]);"><i
                                        class="fa-solid fa-box me-2"></i>Cerrar caja</button>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"><i
                                        class="fa-solid fa-ban"></i> Cancelar</button>
                            <?php } ?>
                            <button type="button" class="btn btn-outline-warning" onclick="salir()"><i
                                    class="fa-solid fa-circle-xmark"></i> Salir</button>
                        </div>
                    </div>
                    <?php
                    //IMPRIMIR ESTA VISTA SI EL USUARIO ES ADMIN O CON PRIVILEGIOS
                    if ($accessHandler->isMedium() || $accessHandler->isHigh()) {
                    ?>
                        <h4>CAJAS APERTURADAS</h4>
                        <input type="number" id="saldoinicial" hidden value="0">
                        <input type="date" id="fec_apertura" hidden value="<?php echo date('Y-m-d'); ?>">
                        <input type="number" id="iduser" hidden value="0">
                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="accordion accordion-flush" id="accordionFlushExample">
                                <?php
                                foreach (($printEvery ?? []) as $key4 => $filaEvery) {
                                ?>
                                    <div class="accordion-item border border-4 border-dark rounded-2">
                                        <h2 class="accordion-header" id="flush<?= $key4; ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#flushc<?= $key4; ?>" aria-expanded="false"
                                                aria-controls="flushc<?= $key4; ?>">
                                                <div class="row" style="width:100%;">
                                                    <div class="col-sm-3">
                                                        <div class="row">
                                                            <span
                                                                class="input-group-addon"><?= ($filaEvery['nombre']); ?></span>
                                                            <span
                                                                class="input-group-addon"><?= ($filaEvery['apellido']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="row">
                                                            <span class="input-group-addon">Apertura</span>
                                                            <span
                                                                class="input-group-addon"><?= $filaEvery['fecha']; ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="row">
                                                            <span class="input-group-addon">Saldo inicial</span>
                                                            <span
                                                                class="input-group-addon"><?= moneda($filaEvery['saldo_inicial']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2">
                                                        <div class="row">
                                                            <span class="input-group-addon">Saldo Final</span>
                                                            <span
                                                                class="input-group-addon"><?= moneda($filaEvery['saldo_final']); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <div class="row">
                                                            <span class="input-group-addon">Estado</span>
                                                            <span
                                                                class="input-group-addon"><?= $filaEvery['estado']; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="flushc<?= $key4; ?>" class="accordion-collapse collapse"
                                            aria-labelledby="flush-headingOne" data-bs-parent="#accordionFlushExample">
                                            <div class="accordion-body">
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="text-center mb-2"><b>Resumen de movimientos</b></div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="table-responsive">
                                                            <table class="table nowrap table-borderless table-hover"
                                                                id="tb_aperturas_cierres2" style="width: 100% !important;">
                                                                <thead class="table-light">
                                                                    <tr
                                                                        style="font-size: 0.9rem; border-bottom: 3px solid #000 !important; ">
                                                                        <th scope="col" style="width: 5%">#</th>
                                                                        <th scope="col" class="text-center">Descripción</th>
                                                                        <th scope="col" class="text-center">Ingresos</th>
                                                                        <th scope="col" class="text-center">Egresos</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    foreach ($filaEvery['print'] as $key21 => $fila21) {
                                                                        $i++;
                                                                    ?>
                                                                        <tr>
                                                                            <th scope="row"><?= $key21 + 1 ?></th>
                                                                            <td class="<?= $fila21['color']; ?>"><span><?= $fila21['descripcion'] ?></span></td>
                                                                            <td class="text-end"><?= $fila21['ingreso']; ?></td>
                                                                            <td class="text-end"><?= $fila21['egreso']; ?></td>
                                                                        </tr>

                                                                    <?php } ?>
                                                                    <tr style="border-top: 3px solid #000 !important;">
                                                                        <th scope="row"></th>
                                                                        <td class="table-info"><span>Subtotales</span></td>
                                                                        <td class="text-end">
                                                                            <?= moneda($filaEvery['sumaingresos']);  ?>
                                                                        </td>
                                                                        <td class="text-end">
                                                                            <?= moneda($filaEvery['sumaegresos']);  ?>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th scope="row"></th>
                                                                        <td class="table-success"><span>Saldo final</span></td>
                                                                        <td class="text-end"><?= moneda(0); ?></td>
                                                                        <td class="text-end">
                                                                            <b><?= moneda($filaEvery['saldo_final']); ?></b>
                                                                        </td>
                                                                    </tr>
                                                                    <tr style="border-top: 3px solid #000 !important;"
                                                                        class="table-warning">
                                                                        <td colspan="2" class="text-center"><span><b>Sumas
                                                                                    iguales</b></span></td>
                                                                        <td class="text-end">
                                                                            <b><?= moneda($filaEvery['sumasiguales']); ?></b>
                                                                        </td>
                                                                        <td class="text-end">
                                                                            <b><?= moneda($filaEvery['sumasiguales']); ?></b>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="row justify-items-md-center">
                                                <div class="col align-items-center m-2" id="modal_footer">
                                                    <?php if ($filaEvery['status'] != 2) { ?>
                                                        <button class="btn btn-outline-primary"
                                                            onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'create_caja_cierre', `0`, ['<?= htmlspecialchars($secureID->encrypt($filaEvery['id'])) ?>',<?= $filaEvery['saldo_inicial']; ?>,<?= $filaEvery['saldo_final']; ?>]);"><i
                                                                class="fa-solid fa-box me-2"></i>Cerrar caja</button>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
        <?php
        break;
    case 'pagos_individuales':
        $xtra = $_POST["xtra"];
        $showmensaje = false;
        $src = '../../includes/img/fotoClienteDefault.png';
        try {
            if ($xtra == 0) {
                $showmensaje = true;
                throw new Exception("Seleccione un crédito para continuar");
            }

            $showInteresesAlDia = $appConfigGeneral->calcularInteresesAlDiaCaja() ?? false;

            $database->openConnection();
            /**
             * Consulta para obtener el estado de la configuración de créditos
             */

            $configCre = $database->selectColumns("tb_configCre", ["estado"], "id = 8");

            if (!empty($configCre) && $configCre[0]['estado'] == 1) {
                /**
                 * Consulta para obtener el número de recibo más alto
                 */
                $queryRecibo = $database->getAllResults("SELECT MAX(CAST(CNUMING AS SIGNED)) AS numrecibo, usu.id_agencia FROM CREDKAR cred
                INNER JOIN tb_usuario usu ON usu.id_usu=cred.CCODUSU 
                WHERE usu.id_agencia=? AND cred.CTIPPAG='P' AND cred.CESTADO!='X' GROUP BY usu.id_agencia", [$idagencia]);

                $numrecibo = empty($queryRecibo) ? 1 : $queryRecibo[0]['numrecibo'] + 1;
            }

            /**
             * Consulta para obtener los datos del cliente y su crédito
             */

            $query = "SELECT cl.short_name AS nombrecli, cl.idcod_cliente AS codcli, ag.cod_agenc AS codagencia, cm.CCODPRD AS codprod, 
                        cm.CCODCTA AS ccodcta, cm.MonSug AS monsug, cm.NIntApro AS interes, cm.DFecDsbls AS fecdesembolso, cm.noPeriodo AS cuotas, 
                        ce.Credito AS tipocred, per.nombre AS nomper, 
                        ((cm.MonSug)-(SELECT IFNULL(SUM(ck.KP),0) FROM CREDKAR ck WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA=cm.CCODCTA)) AS saldocap,
                        ((SELECT IFNULL(SUM(nintere),0) FROM Cre_ppg WHERE ccodcta=cm.CCODCTA)-
                            (SELECT IFNULL(SUM(ck.INTERES),0) FROM CREDKAR ck WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA=cm.CCODCTA)) AS saldoint,
                        prod.id_fondo, cl.url_img AS urlfoto,prod.reestructuracion,cm.CtipCre,cm.NtipPerC
                        FROM cremcre_meta cm
                        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                        INNER JOIN tb_agencia ag ON cm.CODAgencia=ag.cod_agenc
                        INNER JOIN cre_productos prod ON prod.id=cm.CCODPRD
                        INNER JOIN $db_name_general.tb_credito ce ON cm.CtipCre=ce.abre
                        INNER JOIN $db_name_general.tb_periodo per ON cm.NtipPerC=per.periodo
                        WHERE cm.Cestado='F' AND cm.TipoEnti='INDI' AND cm.CCODCTA=?
                        GROUP BY cm.CCODCTA";

            $datos = $database->getSingleResult($query, [$xtra]);
            if (empty($datos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron datos para el cliente seleccionado");
            }

            if (!in_array($datos['NtipPerC'], ['1M', '2M', '3M', '4M', '5M', '6M', '7M', '8M', '9M', '10M', '11M', '12M'])) {
                $showInteresesAlDia = false;
            }

            $imgurl = __DIR__ . '/../../../../' . $datos['urlfoto'];
            if (is_file($imgurl)) {
                $imginfo   = getimagesize($imgurl);
                $mimetype  = $imginfo['mime'];
                $imageData = base64_encode(file_get_contents($imgurl));
                $src = 'data:' . $mimetype . ';base64,' . $imageData;
            }

            /**
             * Consulta para obtener el plan de pago del crédito
             */

            $query2 = "SELECT cpg.Id_ppg AS id, cpg.dfecven, 
                                IF((timestampdiff(DAY,cpg.dfecven,?))<0, 0,(timestampdiff(DAY,cpg.dfecven,?))) AS diasatraso, cpg.cestado, 
                                cpg.cnrocuo AS numcuota, cpg.ncappag AS capital, cpg.nintpag AS interes, cpg.nmorpag AS mora,
                                cpg.OtrosPagosPag AS otrospagos
                            FROM Cre_ppg cpg
                            WHERE cpg.cestado='X' AND cpg.ccodcta=? AND cpg.dfecven <= ?
                            ORDER BY cpg.ccodcta, cpg.dfecven, cpg.cnrocuo";

            $datoscreppg = $database->getAllResults($query2, [$hoy, $hoy, $xtra, $hoy]);
            if (empty($datoscreppg)) {
                /**
                 * SI NO HAY CUOTAS VENCIDAS, TRAE LA PRIMERA CUOTA PENDIENTE POR PAGAR
                 */
                $query2 = "SELECT cpg.Id_ppg AS id, cpg.dfecven, 
                                IF((timestampdiff(DAY,cpg.dfecven,?))<0, 0,(timestampdiff(DAY,cpg.dfecven,?))) AS diasatraso, cpg.cestado, 
                                cpg.cnrocuo AS numcuota, cpg.ncappag AS capital, cpg.nintpag AS interes, cpg.nmorpag AS mora, 
                                cpg.OtrosPagosPag AS otrospagos
                            FROM Cre_ppg cpg
                            WHERE cpg.cestado='X' AND cpg.ccodcta=? AND cpg.dfecven > ?
                            ORDER BY cpg.ccodcta, cpg.dfecven, cpg.cnrocuo Limit 1";

                $datoscreppg = $database->getAllResults($query2, [$hoy, $hoy, $xtra, $hoy]);
                if (empty($datoscreppg)) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron cuotas pendientes para el credito seleccionado");
                }
            } else {
                $showInteresesAlDia = false;
            }

            if ($showInteresesAlDia) {
                $fechaUltimoPago = $datos['fecdesembolso'];
                $cuotaAnterior = $database->selectColumns("Cre_ppg", ["dfecven"], "ccodcta = ? AND cestado = 'P'", [$xtra], "dfecven DESC LIMIT 1");
                if (!empty($cuotaAnterior)) {
                    $fechaUltimoPago = $cuotaAnterior[0]['dfecven'];
                }

                $daysdif = dias_dif($fechaUltimoPago, $hoy);

                if ($fechaUltimoPago > $hoy) {
                    $daysdif = 0; // Si la fecha del último pago es mayor a hoy, no hay días de diferencia
                }

                // Log::info("Días de diferencia para intereses al día: $daysdif");

                $interesAlDiaCalculado = $datos['saldocap'] * ($datos['interes'] / 100 / 365) * $daysdif;
            }

            $bancos = $database->selectColumns("tb_bancos", ["id", "nombre"], "estado = 1");

            /**
             * VERSION NUEVA, ACTIVAR CUANDO SEA ACTUALIZADO
             */
            $idsPpg = array_column($datoscreppg, 'numcuota'); // Extraer los valores de la columna cnrocuo de $datoscreppg
            $idsPpgString = implode(',', $idsPpg); // Convertir el array en una cadena separada por comas

            $queryGastos = "SELECT pro.id, gas.id_nomenclatura,gas.nombre_gasto,gas.afecta_modulo,cre.cntAho ccodaho,
                                cre.moduloafecta,SUM(ifnull(det.monto,0)) AS monto
                            FROM cre_productos_gastos pro 
                            INNER JOIN cre_tipogastos gas ON gas.id=pro.id_tipo_deGasto
                            INNER JOIN cremcre_meta cre ON cre.CCODPRD=pro.id_producto
                            -- LEFT JOIN creppg_detalle det ON det.id_tipo=pro.id AND det.id_creppg IN ($idsPpgString)
                            LEFT JOIN creppg_detalle det ON det.id_tipo=pro.id AND det.id_creppg IN ($idsPpgString) AND det.ccodcta= cre.CCODCTA 
                            WHERE cre.CCODCTA= ? AND pro.tipo_deCobro=2 AND gas.estado=1 AND pro.estado=1
                            GROUP BY pro.id;";
            $datosGastos = $database->getAllResults($queryGastos, [$xtra]);

            $userPermissions = new PermissionManager($idusuario);

            if ($userPermissions->isLevelOne(PermissionManager::USAR_OTROS_DOCS_CREDITOS)) {
                $documentosTransacciones = $database->selectColumns(
                    "tb_documentos_transacciones",
                    ["id", "nombre", "tipo_dato"],
                    "estado=1 AND id_modulo=3 AND tipo=2"
                );
            }

            // try {
            //     $docManager = new DocumentManager();
            //     $previewCorrel = $docManager->peekNextCorrelative([
            //         'id_modulo'  => 4,
            //         'tipo'       => 'INGRESO',
            //         'usuario_id' => $idusuario,
            //         'agencia_id' => $idagencia,
            //     ]);
            // } catch (Exception $e) {
            //     Log::error('Error al verificar correlativo: ' . $e->getMessage());
            // }

            $cuentasAhorros = $database->getAllResults(
                "SELECT ccodaho, tip.nombre,calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) AS saldo
                        FROM ahomcta cta
                        INNER JOIN ahomtip tip ON tip.ccodtip= SUBSTR(cta.ccodaho,7,2)
                        WHERE cta.ccodcli=? AND cta.estado='A'",
                [$datos['codcli']]
            );
            $cuentasAportacion = $database->getAllResults(
                "SELECT capr.ccodaport, atip.nombre, calcular_saldo_apr_tipcuenta(capr.ccodaport, CURDATE()) AS saldo
                        FROM aprcta capr
                        INNER JOIN aprtip atip ON atip.ccodtip= SUBSTR(capr.ccodaport,7,2)
                        WHERE capr.ccodcli=? AND capr.estado='A'",
                [$datos['codcli']]
            );

            $status = true;
        } catch (Throwable $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
        if (!empty($datoscreppg)) {
            $sumacap = array_sum(array_column($datoscreppg, "capital"));
            $sumainteres = array_sum(array_column($datoscreppg, "interes"));
            $sumamora = array_sum(array_column($datoscreppg, "mora"));
            $sumaotrospagos = array_sum(array_column($datoscreppg, "otrospagos"));
            $sumafilas = $sumacap + $sumainteres + $sumamora + $sumaotrospagos;
        }

        /**
         * CONTROL TEMPORAL DE LA REESTRUCTURACION
         */

        $concepto = ($status) ? "PAGO DE CRÉDITO A NOMBRE DE " . strtoupper($datos['nombrecli']) . " CON NÚMERO DE RECIBO " . ($numrecibo ?? '') : "";
        // $reestructuracion = ($status && ($datos['CtipCre'] == 'Franc' || $datos['CtipCre'] == 'Germa')) ? $datos['reestructuracion'] : 0;
        if ($status && ($datos['CtipCre'] == 'Franc' || $datos['CtipCre'] == 'Germa')) {
            $reestructuracion = ($datos['reestructuracion'] == 1 || $datos['reestructuracion'] == 2) ? 1 : 0;
        } else {
            $reestructuracion = 0;
        }

        // Log::info("see reestructura o nel: ", [$reestructuracion]);
        ?>
            <input type="text" id="condi" value="pagos_individuales" hidden>
            <input type="text" id="file" value="caja001" hidden>
            <div class="text" style="text-align:center">PAGO DE CRÉDITO INDIVIDUAL</div>
            <div class="card" x-data="{
                formaPago: 1, tipoDataPago: 0,
                onChangePago(event) {
                    const option = event.target.selectedOptions[0]
                    this.typeData = Number(option.dataset.typedata)

                    this.tipoDataPago = this.typeData
                }
            }" id="formPagoIndividual">
                <div class="card-header">Pago de crédito individual</div>
                <div class="card-body">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong></strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="container contenedort">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Información de cliente y crédito</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-md-2 d-flex align-items-center justify-content-center mb-3 mb-md-0">
                                <img width="120" height="130" id="vistaPrevia" src="<?= $src ?>" class="img-thumbnail">
                            </div>
                            <div class="col-12 col-md-10">
                                <div class="row">
                                    <div class="col-12 col-sm-8 d-flex align-items-center mb-2">
                                        <span class="badge bg-primary fs-5 px-3 py-2 w-100 text-wrap" id="nomcli" name="nomcli">
                                            <?= ($status && !empty($datos['nombrecli'])) ? htmlspecialchars($datos['nombrecli']) : 'Nombre de cliente'; ?>
                                        </span>
                                    </div>
                                    <div class="col-12 col-sm-4 d-flex align-items-end mb-2">
                                        <button type="button" class="btn btn-warning w-100"
                                            style="padding-top: 0.5rem; padding-bottom: 0.5rem;"
                                            data-bs-toggle="modal" data-bs-target="#modal_pagos_cre_individuales">
                                            <i class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito
                                        </button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 col-sm-6 col-md-4 mb-2">
                                        <span class="badge bg-primary fs-6 w-100 text-wrap py-2" id="id_cod_cliente" name="id_cod_cliente">
                                            <i class="fa-solid fa-id-card me-1"></i> Cod. cliente:
                                            <?= ($status && !empty($datos['codcli'])) ? htmlspecialchars($datos['codcli']) : 'Código cliente'; ?>
                                        </span>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-4 mb-2">
                                        <span class="badge bg-primary fs-6 w-100 text-wrap py-2" id="codagencia" name="codagencia">
                                            <i class="fa-solid fa-building me-1"></i> Agencia:
                                            <?= ($status && !empty($datos['codagencia'])) ? htmlspecialchars($datos['codagencia']) : 'Código agencia'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-4 mb-3 mt-2">
                                <div>
                                    <span class="fw-bold d-block mb-1">Código de crédito</span>
                                    <span class="badge bg-success fs-6 w-100 text-wrap py-2" id="codcredito" name="codcredito">
                                        <?php if ($status) {
                                            echo htmlspecialchars($datos['ccodcta']);
                                        } else {
                                            echo 'Código de crédito';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4 mb-3 mt-2">
                                <div>
                                    <span class="fw-bold d-block mb-1">Capital</span>
                                    <span class="badge bg-success fs-6 text-wrap w-100 text-wrap py-2" id="ccapital" name="ccapital">
                                        <?php if ($status) {
                                            echo htmlspecialchars($datos['monsug']);
                                        } else {
                                            echo 'Capital';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4 mb-3 mt-2">
                                <div>
                                    <span class="fw-bold d-block mb-1">Saldo capital</span>
                                    <span class="badge bg-success fs-6 w-100 text-wrap py-2" id="saldocap" name="saldocap">
                                        <?php if ($status) {
                                            echo htmlspecialchars(max(0, $datos['saldocap']));
                                        } else {
                                            echo 'Saldo capital';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-12 col-md-4 mb-3 mt-2">
                                <div>
                                    <div class="fw-bold mb-1">Interés</div>
                                    <span class="badge bg-success fs-6 w-100 text-wrap py-2" id="interes">
                                        <?php if ($status) {
                                            echo htmlspecialchars($datos['interes']);
                                        } else {
                                            echo 'Interés';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4 mb-3 mt-2">
                                <div>
                                    <div class="fw-bold mb-1">Fecha desembolso</div>
                                    <span class="badge bg-success fs-6 text-wrap w-100 text-wrap py-2" id="fechadesembolso">
                                        <?php if ($status) {
                                            echo setdatefrench(htmlspecialchars($datos['fecdesembolso']));
                                        } else {
                                            echo 'Fecha desembolso';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4 mb-3 mt-2">
                                <div>
                                    <div class="fw-bold mb-1">Saldo interés</div>
                                    <span class="badge bg-success fs-6 w-100 text-wrap py-2" id="saldointeres">
                                        <?php if ($status) {
                                            echo htmlspecialchars(max(0, $datos['saldoint']));
                                        } else {
                                            echo 'Saldo interés';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-4 mb-3 mt-2">
                                <div>
                                    <div class="fw-bold mb-1">Tipo de crédito</div>
                                    <span class="badge bg-success fs-6 w-100 text-wrap py-2" id="tipocredito">
                                        <?php if ($status) {
                                            echo htmlspecialchars($datos['tipocred']);
                                        } else {
                                            echo 'Tipo de crédito';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4 mb-3 mt-2">
                                <div>
                                    <div class="fw-bold mb-1">Tipo de periodo</div>
                                    <span class="badge bg-success fs-6 text-wrap w-100 text-wrap py-2" id="tipoperiodo">
                                        <?php if ($status) {
                                            echo htmlspecialchars($datos['nomper']);
                                        } else {
                                            echo 'Tipo de periodo';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4 mb-3 mt-2">
                                <div>
                                    <div class="fw-bold mb-1">Cantidad cuotas</div>
                                    <span class="badge bg-success fs-6 w-100 text-wrap py-2" id="cantcuotas">
                                        <?php if ($status) {
                                            echo htmlspecialchars($datos['cuotas']);
                                        } else {
                                            echo 'Cantidad cuotas';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($status) { ?>
                        <div class="container contenedort">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Detalle de boleta de pago</b></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="text" class="form-control" id="norecibo" placeholder="Número de recibo"
                                            value="<?= $numrecibo ?? '' ?>" required>
                                        <label for="norecibo">No. Recibo o Boleta</label>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-2 mt-2">
                                        <select id="metodoPago" name="metodoPago" aria-label="Default select example"
                                            x-model="formaPago" class="form-select" @change="onChangePago" required>
                                            <option value="1" data-typedata="E">Pago en Efectivo</option>
                                            <option value="2" data-typedata="B">Boleta de Banco</option>
                                            <option value="3" data-typedata="A">De cuenta de Ahorros</option>
                                            <option value="5" data-typedata="P">De cuenta de Aportacion</option>
                                            <?php if (isset($documentosTransacciones) && !empty($documentosTransacciones)): ?>
                                                <optgroup label="Otros tipos de documentos">
                                                    <?php foreach ($documentosTransacciones as $doc): ?>
                                                        <option value="d_<?= $doc['id']; ?>" data-typedata="<?= $doc['tipo_dato']; ?>"><?= htmlspecialchars($doc['nombre']); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>

                                        </select>
                                        <label for="metodoPago">Método de Pago:</label>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-floating mb-2 mt-2">
                                        <input type="date" class="form-control" id="fecpag" placeholder="Fecha de pago"
                                            value="<?= date('Y-m-d') ?>" required>
                                        <label for="fecpag">Fecha de pago:</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12"><span class="badge text-bg-primary">Verifique el concepto antes de
                                        guardar</span></div>
                                <div class="col-sm-12 mb-2">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="concepto" style="height: 100px" required
                                            rows="1"><?= $concepto ?></textarea>
                                        <label for="concepto">Concepto</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort" x-show="formaPago==3">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2">
                                        <b>Seleccione la cuenta de ahorros desde la cual se debitarán los fondos</b>
                                    </div>
                                    <p class="text-muted small text-center">
                                        Los fondos serán debitados de la cuenta seleccionada para aplicar el pago del crédito
                                    </p>
                                </div>
                            </div>
                            <div class="row ">
                                <div class="col-md-12 col-lg-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="aho_cuentaid" :required="formaPago==3">
                                            <option value="" disabled selected>Seleccione una cuenta</option>
                                            <?php
                                            foreach (($cuentasAhorros ?? []) as $cuenta) {
                                                echo '<option value="' . $cuenta['ccodaho'] . '">' . htmlspecialchars($cuenta['ccodaho']) . " - " . htmlspecialchars($cuenta['nombre']) . " - Saldo: " . Moneda::formato($cuenta['saldo']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <label for="aho_cuentaid">Cuenta de Ahorros</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort" x-show="formaPago==5">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2">
                                        <b>Seleccione la cuenta de aportaciones desde la cual se debitarán los fondos</b>
                                    </div>
                                    <p class="text-muted small text-center">
                                        Los fondos serán debitados de la cuenta seleccionada para aplicar el pago del crédito
                                    </p>
                                </div>
                            </div>
                            <div class="row ">
                                <div class="col-md-12 col-lg-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="aport_cuentaid" :required="formaPago==5">
                                            <option value="" disabled selected>Seleccione una cuenta</option>
                                            <?php
                                            foreach (($cuentasAportacion ?? []) as $cuenta) {
                                                echo '<option value="' . $cuenta['ccodaport'] . '">' . htmlspecialchars($cuenta['ccodaport']) . " - " . htmlspecialchars($cuenta['nombre']) . " - Saldo: " . Moneda::formato($cuenta['saldo']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <label for="aport_cuentaid">Cuenta de Aportaciones</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- NEGROY AGREGAR BOLETAS DE PAGO ******************************************** -->
                        <div class="container contenedort" x-show="formaPago==2">
                            <div class="row col">
                                <div class="text-center mb-2"><b>Datos de la boleta de Banco</b></div>
                            </div>
                            <div class="row ">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                            <option value="F000" disabled selected>Seleccione un banco</option>
                                            <?php
                                            if (!empty($bancos)) {
                                                foreach ($bancos as $banco) {
                                                    echo '<option value="' . $banco['id'] . '">' . htmlspecialchars($banco['id']) . "-" . htmlspecialchars($banco['nombre']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <label for="bancoid">Banco</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="cuentaid">
                                            <option selected disabled value="F000">Seleccione una cuenta</option>
                                        </select>
                                        <label for="cuentaid">No. de Cuenta</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="fecpagBANC" value="<?= date('Y-m-d') ?>"
                                            :required="formaPago==2">
                                        <label for="fecpag">Fecha Boleta:</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="noboletabanco" data-label="No. Boleta de Banco"
                                            placeholder="Número de boleta de banco" :required="formaPago==2">
                                        <label for="noboletabanco">No. Boleta de Banco</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container contenedort" id="region_tipo_cheque" x-show="tipoDataPago === 2">
                            <div class="row col">
                                <div class="text-center mb-2"><b>Datos del cheque recibido</b></div>
                            </div>
                            <div class="row ">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="bancoidCheque" :required="tipoDataPago === 2">
                                            <option value="" disabled selected>Seleccione un banco</option>
                                            <?php
                                            if (!empty($bancos)) {
                                                foreach ($bancos as $banco) {
                                                    echo '<option value="' . $banco['id'] . '">' . htmlspecialchars($banco['id']) . "-" . htmlspecialchars($banco['nombre']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <label for="bancoidCheque">Banco</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control"
                                            id="fecpagCheque" value="<?= date('Y-m-d') ?>" :required="tipoDataPago === 2">
                                        <label for="fecpagCheque">Fecha Cheque:</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="noCheque"
                                            placeholder="Número de cheque" :required="tipoDataPago===2">
                                        <label for="noCheque">No. Cheque:</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- NEGROY AGREGAR BOLETAS DE PAGO *FIN*  ******************************************** -->
                        <div class="container contenedort">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Pagos pendientes</b></div>
                                </div>
                            </div>
                        <?php } ?>

                        <div class="accordion" id="cuotas">
                            <?php
                            if (!$status) { ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <div class="text-center">
                                        <strong class="me-2">¡Bienvenido!</strong>Debe seleccionar un crédito a pagar.
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>

                            <?php } else {
                                $variables = [["warning", "X vencer"], ["danger", "Vencida"], ["success", "Vigente"]];
                            ?>
                                <div class="row">
                                    <div class="col mb-2">
                                        <?php if ($showInteresesAlDia) { ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="badge bg-info fs-6 px-3 py-2">
                                                    <i class="fa-solid fa-calendar-day me-2"></i>
                                                    Intereses al día de hoy:
                                                    <span id="interesAlDia"><?= moneda($interesAlDiaCalculado ?? 0) ?></span>
                                                </span>
                                                <div class="form-check form-switch ms-3">
                                                    <input class="form-check-input" type="checkbox" id="switchInteresAlDia"
                                                        onclick="toggleInteresAlDia(<?= round($interesAlDiaCalculado ?? 0, 2) ?>)"
                                                        title="Usar intereses al día en el pago">
                                                    <label class="form-check-label fw-semibold" for="switchInteresAlDia" style="font-size: 1rem;">
                                                        Usar éste monto en el pago
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="alert alert-warning py-2 px-3 mb-2" style="font-size: 0.95rem;">
                                                <i class="fa-solid fa-info-circle me-2"></i>
                                                Al activar el switch para usar los intereses al día, se modificará el plan de pagos original para esta cuota.
                                            </div>
                                        <?php } ?>
                                        <div class="accordion-item">
                                            <!-- ENCABEZADO -->
                                            <h2 class="accordion-header">
                                                <button id="bt0" onclick="opencollapse(0)" style="--bs-bg-opacity: .2;"
                                                    class="accordion-button collapsed bg-<?= ($datoscreppg[0]['diasatraso'] > 0) ? 'danger' : 'success' ?>"
                                                    data-bs-target="#collaps0" aria-expanded="false" aria-controls="collaps0">
                                                    <div class="row" style="font-size: 0.80rem;">
                                                        <div class="col-sm-2">
                                                            <span class="input-group-addon">Capital</span>
                                                            <input id="capital0" disabled onclick="opencollapse(-1)"
                                                                onchange="summon(this.id);updateTotalView();" type="number" step="0.01"
                                                                class="form-control habi form-control-sm" value="<?= $sumacap; ?>">
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <span class="input-group-addon">Interes</span>
                                                            <input id="interes0" disabled onclick="opencollapse(-1)"
                                                                onchange="summon(this.id);updateTotalView();" type="number" step="0.01"
                                                                class="form-control habi form-control-sm"
                                                                value="<?= $sumainteres; ?>">
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <span class="input-group-addon">Mora</span>
                                                            <input id="monmora0" disabled onclick="opencollapse(-1)"
                                                                onchange="summon(this.id);changeMora(<?= $sumamora; ?>);" type="number" step="0.01"
                                                                class="form-control habi form-control-sm" value="<?= $sumamora; ?>">
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <span class="input-group-addon">Otros</span>
                                                            <div class="input-group">
                                                                <input style="height: 10px !important;" id="otrospg0" disabled
                                                                    readonly onclick="opencollapse(-1);" onchange="summon(this.id)"
                                                                    type="number" step="0.01"
                                                                    class="form-control habi form-control-sm"
                                                                    value="<?= $sumaotrospagos; ?>">
                                                                <span id="lotrospg0" title="Modificar detalle otros"
                                                                    class="input-group-addon btn btn-link" data-bs-toggle="modal"
                                                                    data-bs-target="#modalgastos" onclick="opencollapse(-1);"><i
                                                                        class="fa-solid fa-pen-to-square"></i></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <label class="form-label">Total</label>
                                                            <input id="totalpg0" disabled onclick="opencollapse(-1)" type="number"
                                                                step="0.01" class="form-control habi form-control-sm"
                                                                value="<?= $sumafilas; ?>"
                                                                onchange="distribuye(<?= $sumacap; ?>,<?= $sumainteres; ?>,<?= $sumamora; ?>)">
                                                        </div>
                                                        <div class="col-sm-1">
                                                            <div class="form-check form-switch">
                                                                <br>
                                                                <input class="form-check-input" onclick="opencollapse('s0')"
                                                                    type="checkbox" role="switch" id="s0" title="Modificar pago">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </h2>

                                            <!-- SECCION DE DETALLE DE UNA CUOTA -->
                                            <div id="collaps0" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                                <div class="accordion-body">
                                                    <ul class="list-group">
                                                        <?php
                                                        for ($i = 0; $i < count($datoscreppg); $i++) {
                                                        ?>
                                                            <li class="list-group-item">
                                                                <div class="row" style="font-size: 0.80rem;">
                                                                    <input type="number" name="identificatorsPpg" value="<?= $datoscreppg[$i]['id']; ?>" hidden>
                                                                    <div class="col-sm-1">
                                                                        <div class="row">
                                                                            <span class="input-group-addon">No</span>
                                                                            <span
                                                                                class="input-group-addon"><?= $datoscreppg[$i]['numcuota']; ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-2">
                                                                        <div class="row">
                                                                            <span class="input-group-addon">Vencimiento</span>
                                                                            <span
                                                                                class="input-group-addon"><?= date("d-m-Y", strtotime($datoscreppg[$i]['dfecven'])); ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-2">
                                                                        <div class="row">
                                                                            <span class="input-group-addon">Capital</span>
                                                                            <span
                                                                                class="input-group-addon"><?= $datoscreppg[$i]['capital']; ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-2">
                                                                        <div class="row">
                                                                            <span class="input-group-addon">Interes</span>
                                                                            <span
                                                                                class="input-group-addon"><?= $datoscreppg[$i]['interes']; ?></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-2">
                                                                        <div class="row">
                                                                            <span class="input-group-addon">Atraso</span>
                                                                            <span
                                                                                class="input-group-addon"><?= $datoscreppg[$i]['diasatraso']; ?> dias</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-2">
                                                                        <div class="row">
                                                                            <span class="input-group-addon">Estado</span>
                                                                            <span
                                                                                class="input-group-addon badge text-bg-<?= ($datoscreppg[$i]['diasatraso'] > 0) ? 'danger' : 'success'; ?>">
                                                                                <?= ($datoscreppg[$i]['diasatraso'] > 0) ? 'Vencida' : 'Vigente'; ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>
                                                        <?php } ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="container mb-3" id="morainfo_container" style="display: none;">
                                    <div class="alert alert-info py-2 mb-2">
                                        <span class="fw-bold">Se modificó la mora, seleccione el concepto del monto ingresado:</span>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipoMontoMora" id="radio0" value="perdon" checked>
                                        <label class="form-check-label" for="radio0">Perdón</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="tipoMontoMora" id="radio1" value="abono">
                                        <label class="form-check-label" for="radio1">Abono</label>
                                    </div>
                                </div>
                                <div class="container-fluid my-4">
                                    <div class="row justify-content-center">
                                        <div class="col-12 col-md-6 col-lg-5">
                                            <div class="card border-primary shadow-sm">
                                                <div class="card-header bg-primary text-white text-center py-2">
                                                    <i class="fa-solid fa-calculator me-2"></i>
                                                    <span class="fw-semibold" style="font-size:1.15rem;">Total a Pagar</span>
                                                </div>
                                                <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                                                    <div class="input-group input-group-lg w-100">
                                                        <span class="input-group-text bg-primary text-white fw-bold fs-4 px-4">
                                                            Q
                                                        </span>
                                                        <input id="total_view" readonly type="text"
                                                            class="form-control text-end fs-3 fw-bold border-primary rounded-end"
                                                            value="<?= number_format($sumafilas, 2, '.', ','); ?>"
                                                            style="height: 3.2rem;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        </div>
                        <!-- <div class="container"> -->
                        <div class="row justify-items-md-center">
                            <div class="col align-items-center mt-2" id="modal_footer">
                                <?php if ($status) { ?>
                                    <button class="btn btn-outline-success"
                                        onclick="guardar_pagos_individuales(0, '<?= $datos['ccodcta']; ?>',<?= $sumacap; ?>,<?= $sumainteres; ?>,<?= $sumamora; ?>)"><i
                                            class="fa-solid fa-money-bill me-2"></i>Pagar</button>
                                    <button class="btn btn-outline-primary "
                                        onclick="mostrar_planpago('<?= $datos['ccodcta']; ?>'); printdiv5('nomcli2,codcredito2/A,A/'+'/#/#/#/#',['<?= $datos['nombrecli']; ?>','<?= $datos['ccodcta']; ?>']);"
                                        data-bs-toggle="modal" data-bs-target="#modal_plan_pago"><i
                                            class="fa-solid fa-rectangle-list me-2"></i>Consultar plan de pago</button>
                                <?php } ?>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()">
                                    <i class="fa-solid fa-circle-xmark"></i> Salir
                                </button>
                            </div>
                        </div>
                        <!-- </div> -->
                </div>
                <?php include_once "../../../src/cris_modales/mdls_pagos_individuales.php"; ?>
                <?php include_once "../../../src/cris_modales/mdls_planpago.php"; ?>

                <!-- Modal -->
                <div class="modal fade " id="modalgastos" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true"
                    data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h1 class="modal-title fs-5" id="exampleModalLabel">Detalle de otros cobros</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                <input style="display: none;" id="flagid" readonly type="number" value="0">
                            </div>
                            <div class="modal-body">
                                <table class="table" id="tbgastoscuota" class="display" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Descripcion</th>
                                            <th>Afecta Otros modulos</th>
                                            <th>Adicional</th>
                                            <th>Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody id="categoria_tb">

                                        <?php

                                        if (!empty($datosGastos)) {
                                            foreach ($datosGastos as $fila) {
                                                $afecta = $fila["afecta_modulo"];
                                                $modulo = ($afecta == 1) ? 'AHORROS' : (($afecta == 2) ? 'APORTACIONES' : 'NO');
                                                $cuenta = ($afecta == 1 || $afecta == 2) ? (($fila["moduloafecta"] == $afecta && strlen(trim($fila["ccodaho"])) >= 12) ? $fila["ccodaho"] : 'No hay cuenta vinculada') : 'NO';
                                                $disabled = ($afecta == 1 || $afecta == 2) ? (($fila["moduloafecta"] == $afecta && strlen(trim($fila["ccodaho"])) >= 12) ? '' : 'disabled readonly') : '';

                                                echo '<tr>
                                                        <td>' . htmlspecialchars($fila["nombre_gasto"]) . '</td>
                                                        <td data-id="' . $afecta . '">' . htmlspecialchars($modulo) . '</td>
                                                        <td data-cuenta="' . htmlspecialchars($cuenta) . '">' . htmlspecialchars($cuenta) . '</td>
                                                        <td>
                                                            <div class="row d-flex
                                                            justify-content-end">
                                                                <input style="display:none;" type="text" name="idgasto" value="' . htmlspecialchars($fila['id']) . '">
                                                                <input style="display:none;" type="text" name="idcontable" value="' . htmlspecialchars($fila['id_nomenclatura']) . '">
                                                                <input ' . $disabled . ' onkeyup="sumotros()" style="text-align: right;" id="mongasto" type="number" step="0.01" 
                                                                    class="form-control form-control-sm inputNoNegativo" value="' . htmlspecialchars($fila['monto']) . '">
                                                            </div>
                                                        </td>
                                                    </tr>';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td>OTROS</td>
                                            <td data-id="0">-</td>
                                            <td data-cuenta="0">-</td>
                                            <td>
                                                <div class="row d-flex justify-content-end">
                                                    <input style="display:none;" type="text" name="idgasto" value="0">
                                                    <input style="display:none;" type="text" name="idcontable" value="0">
                                                    <input onkeyup="sumotros()" style="text-align: right;" id="DS" type="number"
                                                        step="0.01" class="form-control form-control-sm inputNoNegativo"
                                                        value="<?= ($status) ? round((max(0, $sumaotrospagos - (is_array($datosGastos) && count($datosGastos) > 0 ? array_sum(array_column($datosGastos, 'monto')) : 0))), 2) : 0; ?>">
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    // function openWindow(element) {
                    //     // Obtener el option seleccionado dentro del select
                    //     const selectedOption = element.options[element.selectedIndex];
                    //     const tipoDato = selectedOption.getAttribute('data-typedata');

                    //     if (tipoDato === '2') {
                    //         $("#region_tipo_cheque").show();
                    //     } else {
                    //         $("#region_tipo_cheque").hide();
                    //     }
                    // }
                    //-------------------inicio chaka
                    function procesarPagos(reestructura, cant, codcredito, verificamora, tipoAuth = []) {
                        var datos = [];
                        var rows = 0;
                        while (rows <= cant) {
                            filas = getinputsval(['codcredito', 'monmora' + (rows), 'nomcli', 'capital' + (rows), 'interes' + (
                                rows), 'otrospg' + (rows), 'totalpg' + (rows)]);
                            datos[rows] = filas;
                            rows++;
                        }

                        var detalles = [];
                        var i = 0;
                        $('#tbgastoscuota tr').each(function(index, fila) {
                            var monto = $(fila).find('td:eq(3) input[type="number"]');
                            monto = (isNaN(monto.val())) ? 0 : Number(monto.val());
                            var idgasto = $(fila).find('td:eq(3) input[name="idgasto"]');
                            idgasto = Number(idgasto.val());
                            var idcontable = $(fila).find('td:eq(3) input[name="idcontable"]');
                            idcontable = Number(idcontable.val());
                            var modulo = $(fila).find('td:eq(1)').data('id');
                            var codaho = $(fila).find('td:eq(2)').data('cuenta');

                            if (monto > 0) {
                                detalles[i] = [monto, idgasto, idcontable, modulo, codaho];
                                i++;
                            }
                        });
                        detalles = detalles.length > 0 ? detalles : null;

                        //obtener los valores de los inputs con name identificatorsPpg
                        var identificatorsPpg = [];
                        $('input[name="identificatorsPpg"]').each(function() {
                            identificatorsPpg.push($(this).val());
                        });

                        let switchInteresAlDia = document.getElementById('switchInteresAlDia');
                        let switchInteresAlDiaValue = 0;
                        if (switchInteresAlDia && switchInteresAlDia.checked) {
                            switchInteresAlDiaValue = 1;
                        }


                        if (verificamora == 1) {
                            //SE TIENE QUE AUTORIZAR CAMBIO DE MORA
                            clave_confirmar_mora_individual(codcredito, reestructura);
                        } else {
                            // PASA DIRECTAMENTE A GUARDAR
                            obtiene([`codcredito`, `norecibo`, `fecpag`, `capital0`, `interes0`, `monmora0`, `otrospg0`,
                                    `fecpagBANC`, `noboletabanco`, `concepto`, `bancoidCheque`, `noCheque`, `fecpagCheque`
                                ],
                                [`bancoid`, `cuentaid`, `metodoPago`,`aho_cuentaid`, `aport_cuentaid`], [`tipoMontoMora`], `create_pago_individual`, `0`,
                                [codcredito, detalles, reestructura, identificatorsPpg, switchInteresAlDiaValue, tipoAuth], 'NULL', 'Desea continuar con el pago?');
                            //["001001011000083371","","0",["12381"],"1"]
                        }
                    }

                    function guardar_pagos_individuales(cant, codcredito, kp, int, mor) {
                        var capital = document.getElementById("capital0").value;
                        capital = parseFloat(capital);
                        var interes = document.getElementById("interes0").value;
                        interes = parseFloat(interes);
                        var mora = document.getElementById("monmora0").value;
                        mora = parseFloat(mora);

                        var verificamora = (parseFloat(mor.toFixed(2)) != parseFloat(mora.toFixed(2))) && (parseFloat(mor.toFixed(2)) > parseFloat(mora.toFixed(2))) ? 1 : 0;

                        var reestructura = <?= $reestructuracion ?>;
                        // console.log("Reestructuración: " + reestructura);
                        // console.log("datos", kp.toFixed(2));
                        // console.log("capital", capital.toFixed(2));
                        /**@abstract
                         * Verifica si se requiere una reestructuración del plan de pagos. si esta activo en el producto
                         * cuando el kp que se quiere pagar es mayor a la sugerida, mostrar mensaje de confirmacion
                         */
                        if (reestructura == 1 && (parseFloat(capital.toFixed(2)) > parseFloat(kp.toFixed(2)))) {
                            // console.log("Reestructuración: aca estamos we");
                            // if (reestructura == 1 && (kp.toFixed(2) != capital.toFixed(2) || int.toFixed(2) != interes.toFixed(2))) {
                            reestructura = 0;
                            Swal.fire({
                                title: "Se modificó la cuota, Confirme si se procede a una reestructuración del plan de pagos después de guardar el pago?",
                                text: " ",
                                icon: "question",
                                showCancelButton: true,
                                confirmButtonText: "Sí, Reestructurar",
                                confirmButtonColor: '#4CAF50', // Color verde
                                cancelButtonText: "No reestructurar, solo pagar"
                            }).then((result) => {
                                console.log(result);
                                if (result.isConfirmed) {
                                    reestructura = 1;
                                }

                                procesarPagos(reestructura, cant, codcredito, verificamora);

                                // console.log(reestructura);
                            });
                        } else {
                            // console.log("Reestructuración: no se requiere");
                            procesarPagos(0, cant, codcredito, verificamora);
                        }
                    }

                    function clave_confirmar_mora_individual(codcredito, reestructura) {
                        Swal.fire({
                            title: 'Autorización para modificación de mora',
                            html: `
                                <div style="max-width: 95%; margin: 0 auto; overflow-x: hidden;">
                                    <input id="flag" type="text" style="display:none" value="0"/>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tipo de autorización:</label>
                                        <select id="tipoAuth" class="form-select mb-3" onchange="cambiarTipoAuth()">
                                            <option value="1">Autorización inmediata</option>
                                            <option value="2">Solicitar autorización</option>
                                        </select>
                                    </div>
                                    <div id="authInmediata" class="w-100">
                                        <div class="mb-3">
                                            <input id="user" class="form-control" type="text" placeholder="Usuario" autocapitalize="off"/>
                                        </div>
                                        <div class="mb-3">
                                            <input id="pass" class="form-control" type="password" placeholder="Contraseña" autocapitalize="off"/>
                                        </div>
                                        <div class="alert alert-info py-2 px-3 mb-0">
                                            Ingrese sus credenciales para autorizar la modificación de mora.
                                        </div>
                                    </div>
                                    <div id="authSolicitud" style="display:none;" class="w-100">
                                        <div class="alert alert-warning py-2 px-3 mb-0">
                                            Su solicitud será enviada al encargado para su aprobación.
                                        </div>
                                    </div>
                                </div>
                            `,
                            width: '32em',
                            showCancelButton: true,
                            confirmButtonText: 'Continuar',
                            showLoaderOnConfirm: true,
                            didOpen: () => {
                                // Script que se ejecuta cuando se abre el modal
                                window.cambiarTipoAuth = function() {
                                    const tipoAuth = document.getElementById('tipoAuth').value;
                                    const authInmediata = document.getElementById('authInmediata');
                                    const authSolicitud = document.getElementById('authSolicitud');

                                    if (tipoAuth === '1') {
                                        authInmediata.style.display = 'block';
                                        authSolicitud.style.display = 'none';
                                    } else {
                                        authInmediata.style.display = 'none';
                                        authSolicitud.style.display = 'block';
                                    }
                                }
                            },
                            preConfirm: () => {
                                const tipoAuth = document.getElementById('tipoAuth').value;

                                if (tipoAuth === '1') {
                                    const username = document.getElementById('user').value;
                                    const password = document.getElementById('pass').value;

                                    //AJAX para validación inmediata
                                    return $.ajax({
                                        url: "../../src/cruds/crud_usuario.php",
                                        method: "POST",
                                        data: {
                                            'condi': 'validar_usuario_por_mora',
                                            'username': username,
                                            'pass': password
                                        },
                                        dataType: 'json',
                                        success: function(data) {
                                            if (data[1] != "1") {
                                                Swal.showValidationMessage(data[0]);
                                            }
                                        }
                                    }).catch(xhr => {
                                        Swal.showValidationMessage(`${xhr.responseJSON[0]}`);
                                    });
                                } else {
                                    const noRecibo = document.getElementById('norecibo').value;
                                    if (!noRecibo.trim()) {
                                        Swal.showValidationMessage('Debe ingresar un número de recibo para poder solicitar autorización');
                                        return false;
                                    }

                                    //AJAX para enviar solicitud de autorización
                                    return $.ajax({
                                        url: "../../src/cruds/crud_alerta.php",
                                        method: "POST",
                                        data: {
                                            'condi': 'solicitar_autorizacion_mora',
                                            'codcredito': codcredito,
                                            'norecibo': noRecibo,
                                        },
                                        dataType: 'json',
                                        success: function(data) {
                                            document.getElementById('flag').value = data[1];
                                            if (data[1] == "0") {
                                                Swal.showValidationMessage(data[0]);
                                            }
                                        }
                                    }).catch(xhr => {
                                        Swal.showValidationMessage('Error al enviar la solicitud');
                                    });
                                }
                            },
                            allowOutsideClick: (outsideClickEvent) => {
                                const isLoading = Swal.isLoading();
                                const isClickInsideDialog = outsideClickEvent?.target?.closest('.swal2-container') !== null;
                                return !isLoading && !isClickInsideDialog;
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const tipoAuth = document.getElementById('tipoAuth').value;
                                // Definir el array correctamente como objeto JS
                                const tipoAutorizacion = {
                                    tipoAuth: tipoAuth
                                };
                                if (tipoAuth === '1') {
                                    tipoAutorizacion.user = document.getElementById('user').value;
                                    // console.log(tipoAutorizacion)
                                    procesarPagos(reestructura, 0, codcredito, 0, tipoAutorizacion);
                                } else {
                                    if (document.getElementById('flag').value == "1") {
                                        Swal.fire({
                                            icon: 'info',
                                            title: 'Solicitud enviada correctamente',
                                            text: 'Su solicitud de autorización para modificar la mora ha sido enviada al encargado. Por favor, espere la aprobación y vuelva a intentarlo.',
                                        });
                                    } else if (document.getElementById('flag').value == "2") {
                                        // console.log(tipoAutorizacion)
                                        procesarPagos(reestructura, 0, codcredito, 0, tipoAutorizacion);
                                    }

                                }
                            }
                        });
                    }
                    //------------------ fin chaka

                    function changeMora(moraActual) {
                        mostrar_nomostrar([], ['morainfo_container']);
                        const moraInput = document.getElementById('monmora0');
                        const moraValue = parseFloat(moraInput.value) || 0;
                        if (moraValue !== moraActual) {
                            if (moraActual < moraValue) {
                                document.getElementById('radio1').checked = true;
                            }
                            if (moraActual > moraValue && moraValue <= 0) {
                                document.getElementById('radio0').checked = true;
                            }
                            if (moraActual > moraValue && moraValue > 0) {
                                document.getElementById('radio0').checked = true;
                                mostrar_nomostrar(['morainfo_container'], []);
                            }
                        }
                        updateTotalView();
                    }

                    function sumotros() {
                        var valor = 0
                        $('#tbgastoscuota tr').each(function(index, fila) {
                            var inputDS = $(fila).find('td:eq(3) input[type="number"]');
                            var valorDS = Number(inputDS.val());
                            valor += (isNaN(valorDS)) ? 0 : valorDS;
                        });
                        $("#otrospg0").val(valor);
                        summon("otrospg0")
                        updateTotalView();
                    }
                    var inputs = document.querySelectorAll('.inputNoNegativo');
                    inputs.forEach(function(input) {
                        input.addEventListener('input', function() {
                            var expresion = input.value;
                            var esValida = /^-?\d+(\.\d+)?([-+*/]\d+(\.\d+)?)*$/.test(expresion);
                            if (!esValida) {
                                //alert("Por favor, ingresa una expresión matemática válida.");
                                input.value = 0;
                                sumotros();
                            }
                            if (parseFloat(input.value) < 0) {
                                input.value = 0;
                                sumotros();
                            }
                        });
                    });

                    function setvalues(kp, int, mora, total) {
                        $("#totalpg0").val(parseFloat(total).toFixed(2));
                        $("#monmora0").val(parseFloat(mora).toFixed(2));
                        $("#interes0").val(parseFloat(int).toFixed(2));
                        $("#capital0").val(parseFloat(kp).toFixed(2));
                        summon('totalpg0');
                        updateTotalView();
                    }

                    /**@abstract
                     * Distribuye el total de pagos entre capital, interes y mora.
                     * @param {number} kp - Capital a pagar.
                     * @param {number} int - Interes a pagar.
                     * @param {number} mora - Mora a pagar.
                     */
                    function distribuye(kp, int, mora) {
                        var total = $("#totalpg0").val();
                        var otros = $("#otrospg0").val();
                        var auxtotal = parseFloat(total);

                        if ((auxtotal - otros) < 0) {
                            setvalues(0, 0, 0, otros)
                            return;
                        }
                        auxtotal = auxtotal - otros;

                        if ((auxtotal - mora) < 0) {
                            setvalues(0, 0, auxtotal, otros)
                            return;
                        }
                        auxtotal = auxtotal - mora;
                        $("#monmora0").val(mora.toFixed(2));

                        if ((auxtotal - int) < 0) {
                            setvalues(0, auxtotal, mora, otros)
                            return;
                        }
                        auxtotal = auxtotal - int;
                        $("#interes0").val(int.toFixed(2));

                        $("#capital0").val(auxtotal.toFixed(2));
                        summon('totalpg0');
                        updateTotalView();
                    }

                    function formatNumberWithCommas(number) {
                        return parseFloat(number).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }

                    function toggleInteresAlDia(monto) {
                        $("#interes0").val(monto.toFixed(2));
                        summon('interes0');
                        updateTotalView();
                    }

                    function updateTotalView() {

                        const total = parseFloat(document.getElementById('totalpg0').value) || 0;

                        document.getElementById('total_view').value = formatNumberWithCommas(total);
                    }
                    window.onload = updateTotalView;

                    $(document).ready(function() {
                        inicializarValidacionAutomaticaGeneric('#formPagoIndividual');

                            // Actualizar concepto cuando cambie el número de recibo
                            $('#norecibo').on('input', function() {
                                const nombreCliente = '<?= ($status && !empty($datos['nombrecli'])) ? strtoupper($datos['nombrecli']) : '' ?>';
                                const nuevoRecibo = $(this).val();
                                if (nombreCliente && nuevoRecibo) {
                                    const nuevoConcepto = `PAGO DE CRÉDITO A NOMBRE DE ${nombreCliente} CON NÚMERO DE RECIBO ${nuevoRecibo}`;
                                    $('#concepto').val(nuevoConcepto);
                                }
                            });
                    });
                </script>
            <?php
            break;
        case 'reimpresion_recibo_indi':
            include __DIR__ . '/../../../includes/BD_con/db_con.php';
            mysqli_set_charset($conexion, 'utf8');
            $xtra = $_POST["xtra"];
            $usuario = $_SESSION["id"];
            $id_agencia = $_SESSION['id_agencia'];
            $nombreS = $_SESSION['nombre'];
            $apellidoS = $_SESSION['apellido'];
            $where = "";
            $mensaje_error = "";
            $bandera_error = false;
            //Validar si ya existe un registro igual que el nombre
            $nuew = "ccodusu='$usuario' AND (dfecsis BETWEEN '" . date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days')) . "' AND  '" . date('Y-m-d') . "')";
            try {
                $stmt = $conexion->prepare("SELECT IF(tu.puesto='ADM' OR tu.puesto='GER' OR tu.puesto='ANA' OR tu.puesto='CNT', '1=1', ?) AS valor FROM tb_usuario tu WHERE tu.id_usu = ?");
                if (!$stmt) {
                    throw new Exception("Error en la consulta: " . $conexion->error);
                }
                $stmt->bind_param("ss", $nuew, $usuario);
                if (!$stmt->execute()) {
                    throw new Exception("Error al consultar: " . $stmt->error);
                }
                $result = $stmt->get_result();
                $whereaux = $result->fetch_assoc();
                $where = $whereaux['valor'];
                // if ($usuario=='27') { //--REQ--fape--3--Permisos fape para un usuario especial
                // 	$where='1=1';
                // }
            } catch (Exception $e) {
                //Captura el error
                $mensaje_error = $e->getMessage();
                $bandera_error = true;
            }
            ?>
                <input type="text" id="condi" value="reimpresion_recibo_indi" hidden>
                <input type="text" id="file" value="caja001" hidden>

                <div class="text" style="text-align:center">REIMPRESION DE RECIBO DE CRÉDITOS INDIVIDUALES</div>
                <div class="card">
                    <div class="card-header">Reimpresión de recibo de créditos individuales</div>
                    <div class="card-body">
                        <?php if ($bandera_error) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>¡Error!</strong> <?= $mensaje_error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>
                        <!-- tabla de recibos individuales -->
                        <div class="row mt-2 pb-2">
                            <div class="table-responsive">
                                <table id="table-recibos-individuales" class="table table-hover table-border nowrap"
                                    style="width:100%">
                                    <thead class="text-light table-head-aprt mt-2">
                                        <tr>
                                            <th>Crédito</th>
                                            <th>Recibo</th>
                                            <th>Ciclo</th>
                                            <th>Fecha Doc.</th>
                                            <th>Monto</th>
                                            <th col-lg-1 col-md-1>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody style="font-size: 0.9rem !important;">

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include_once "../../../src/cris_modales/mdls_editReciboCreIndi.php"; ?>

                <script>
                    $(document).ready(function() {
                        $("#table-recibos-individuales").DataTable({
                            "processing": true,
                            "serverSide": true,
                            "sAjaxSource": "../../src/server_side/recibo_credito_individual.php",
                            columns: [{
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
                                    data: [0],
                                    render: function(data, type, row) {
                                        imp = '';
                                        imp1 = '';
                                        imp2 = '';
                                        const separador = "||";
                                        var dataRow = row.join(separador);
                                        if (row[9] == "1") {
                                            imp1 =
                                                `<button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#staticBackdrop" onclick="capData('${dataRow}',['#idR','#recibo','#fecha','#concepto'],[0,2,4,7])"><i class="fa-solid fa-pen-to-square"></i>Editar</button>`;
                                            imp2 =
                                                `<button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="eliminar('${row[0]}','eliReIndi','<?php echo $usuario ?>')"><i class="fa-solid fa-trash-can"></i></button>`;
                                        }
                                        imp =
                                            `<button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="reportes([[], [], [], ['<?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido'] ?>', '${row[1]}', '${row[6]}', '${row[2]}']], 'pdf', '14', 0,1)"><i class="fa-solid fa-print me-2"></i>Reimprimir</button>`;
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
            <?php
            break;
        case "reportecaja":
            $id_agencia = $_SESSION["id_agencia"];
            $nomagencia = $_SESSION["nomagencia"];
            $puestouser = $_SESSION['puesto'];
            $especial = ($idusuario == 4) ? "" : " AND id_usu != 4";

            $query = "SELECT id_usu,nombre, apellido,nom_agencia FROM tb_usuario usu 
                        INNER JOIN tb_agencia age ON usu.id_agencia=age.id_agencia WHERE estado=1 $especial ORDER BY usu.id_agencia;";

            $showmensaje = false;
            try {
                $database->openConnection();
                $usuarios = $database->getAllResults($query);
                if (empty($usuarios)) {
                    $showmensaje = true;
                    throw new Exception("No existen usuarios por analizar");
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
            // echo "<pre>";
            // echo print_r($datos);
            // echo "</pre>";
            ?>
                <style>
                    .user-card {
                        background-color: #f8f9fa;
                        border: 1px solid #dee2e6;
                        border-radius: 0.5rem;
                        padding: 1rem;
                        margin-bottom: 1rem;
                        transition: all 0.3s ease;
                    }

                    .user-card:hover {
                        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
                        transform: translateY(-2px);
                    }

                    .user-info {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .user-name {
                        font-weight: bold;
                        font-size: 0.75rem;
                    }

                    .user-agency {
                        font-size: 0.7rem;
                    }

                    .form-switch .form-check-input {
                        width: 3em;
                        height: 1.5em;
                    }
                </style>
                <input type="text" id="condi" value="reportecaja" hidden>
                <input type="text" id="file" value="caja001" hidden>

                <div class="text" style="text-align:center">Consolidacion de cajas</div>
                <div class="card">
                    <!-- <div class="card-header">Cierre de caja</div> -->
                    <div class="card-body">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <strong>¡Error!</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>

                        <div class="container contenedort" style="max-width: 100% !important;">
                            <div class="row">
                                <div class="col">
                                    <div class="text-center mb-2"><b>Seleccione una fecha de consulta y los usuarios a consolidar</b></div>
                                    <p>Nota: Solo se considerarán los usuarios que hayan aperturado caja en la fecha seleccionada.</p>
                                </div>
                            </div>
                            <div class="container mt-2">

                                <div class="mb-4">
                                    <label for="fecha" class="form-label">Fecha de consulta</label>
                                    <input type="date" class="form-control" id="fecha" name="fecha"
                                        value="<?= date('Y-m-d'); ?>">
                                </div>
                                <div class="row">
                                    <?php foreach ($usuarios as $key => $usuario): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="user-card" onclick="toggleSwitch(<?php echo $usuario['id_usu']; ?>)">
                                                <div class="user-info">
                                                    <div for="usuario-<?php echo $usuario['id_usu']; ?>">
                                                        <span style="font-size: 0.68rem;"
                                                            class="badge bg-primary"><?= $key + 1; ?></span>
                                                        <span
                                                            class="user-name"><?php echo "{$usuario['nombre']} {$usuario['apellido']}"; ?></span>
                                                        <br>
                                                        <span
                                                            class="user-agency badge bg-success"><?php echo $usuario['nom_agencia']; ?></span>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="usuario-<?php echo $usuario['id_usu']; ?>" name="usuarioscheck"
                                                            value="<?php echo $usuario['id_usu']; ?>"
                                                            onclick="event.stopPropagation();">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-items-md-center m-3">
                            <div class="col align-items-center">
                                <!-- <button type="button" class="btn btn-outline-primary" title="Reporte de ingresos" onclick="process('show',1)">
                                    <i class="fa-solid fa-eye"></i> Ver
                                </button> -->
                                <button type="button" class="btn btn-outline-danger" title="Reporte de ingresos en pdf"
                                    onclick="process('pdf',0)">
                                    <i class="fa-solid fa-file-pdf"></i> Pdf
                                </button>
                                <button type="button" class="btn btn-outline-success" title="Reporte de ingresos en Excel"
                                    onclick="process('xlsx',1)">
                                    <i class="fa-solid fa-file-excel"></i>Excel
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
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
                <script>
                    function toggleSwitch(id) {
                        const checkbox = document.getElementById('usuario-' + id);
                        checkbox.checked = !checkbox.checked;
                    }

                    function process(tipo, download) {
                        let checkedValues = [];
                        let checkboxes = document.querySelectorAll('input[name="usuarioscheck"]:checked');
                        checkboxes.forEach((checkbox) => {
                            checkedValues.push(checkbox.value);
                        });
                        reportes([
                            [`fecha`],
                            [],
                            [],
                            [checkedValues]
                        ], tipo, `consolidacion_caja`, download, 0, 'dfecope', 'monto', 2, 'Montos', 0);
                    }
                </script>

            <?php
            break;
        case 'movimientoscaja':
            try {
                $database->openConnection();
                $monedas = $database->selectColumns("tb_monedas", ['id', 'nombre', 'abr'], "id=?", [1]);
                $usuario = $database->selectColumns("tb_usuario", ["id_usu", "nombre", "apellido"], "id_usu=? AND estado=? AND id_agencia=?", [$idusuario, 1, $idagencia]);
                $nombreCompleto = "";
                if (!empty($usuario)) {
                    $nombreCompleto = $usuario[0]['nombre'] . " " . $usuario[0]['apellido'];
                }
                $estado = $database->selectColumns("tb_caja_apertura_cierre", ["*"], "id_usuario = ? AND fecha_apertura = ? AND estado=?", [$idusuario, $hoy, 1]);
                $totalRegistros = count($estado);
            } catch (Exception $e) {
                echo "Error: " . $e;
            } finally {
                $database->closeConnection();
            }

            // try {
            //     $database->openConnection(2);
            // } catch (Exception $e) {
            //     echo "Error: " . $e;
            // } finally {
            //     $database->closeConnection();
            // }

            ?>
                <input type="text" id="condi" value="apertura_caja" hidden>
                <input type="text" id="file" value="caja001" hidden>
                <?php
                if ($totalRegistros == 1) {
                ?>
                    <script>
                        cambiarMoneda();
                    </script>
                    <div class="card">
                        <div class="card-header bg-success text-white text-center">
                            <h4>Movimientos en Caja</h4>
                        </div>
                        <div class="card-body bg-light">
                            <div class="row align-items-center mb-4">
                                <div class="col-md-4">
                                    <label class="form-label text-success"><b>Tipo de Moneda</b></label>
                                    <select class="form-select border-success shadow-sm" id="tipMoneda" onchange="cambiarMoneda()">
                                        <?php
                                        if (isset($monedas) && count($monedas) > 0) {
                                            foreach ($monedas as $moneda) {
                                                $selected = ($moneda['id'] == 1) ? "selected" : "";
                                                echo "<option {$selected} value='{$moneda['id']}'>{$moneda['abr']} - {$moneda['nombre']}</option>";
                                            }
                                        } else {
                                            echo '<option value="">No hay monedas disponibles</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="tipoOperacion" class="form-label text-success"><b>Tipo de Operación</b></label>
                                    <select class="form-select border-success shadow-sm" id="tipoOperacion">
                                        <option value="1">Incremento</option>
                                        <option value="2">Decremento</option>
                                    </select>
                                </div>

                                <!-- Desglosar Monto -->
                                <div class="col-md-4">
                                    <label class="form-label text-success"><b>Desglosar Monto</b></label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="desglosarMonto" id="desglosarSi" value="si" checked onchange="mostrarFormularioDesglose()">
                                            <label class="form-check-label" for="desglosarSi">Sí</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="desglosarMonto" id="desglosarNo" value="no" onchange="mostrarFormularioDesglose()">
                                            <label class="form-check-label" for="desglosarNo">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Formulario de desgloses -->
                            <div id="formularioDesglose">
                                <div class="row">
                                    <div class="col text-center">
                                        <h5 class="text-success">Denominaciones posibles</h5>
                                        <p class="text-muted">Ingrese la cantidad para cada denominación.</p>
                                    </div>
                                </div>
                                <!-- Billetes -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="p-2 mb-3 bg-success text-white text-center">
                                            <b>Billetes</b>
                                        </div>
                                    </div>
                                </div>

                                <!-- Monedas -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="p-2 mb-3 bg-success text-white text-center">
                                            <b>Monedas</b>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <h5 class="text-center text-success">Total Solicitado</h5>
                                <div class="input-group mt-3">
                                    <span class="input-group-text bg-success text-white">Q</span>
                                    <input type="number" id="totalGeneral" min="0" class="form-control text-center" value="0" disabled>
                                </div>
                            </div>

                            <div class="col align-items-center mt-2 mb-2" id="modal_footer">
                                <button class="btn btn-outline-primary" id="GenSolicitar" onclick="validarcajas()">
                                    <i class=" svg-inline--fa fas fa-money-check-alt"></i> Solicitar
                                </button>
                                <button class="btn btn-outline-danger" id="GenPDF" style="display: none;" onclick="reportes([[], [], [], [this.value,'solicitud']], 'pdf', '28', 0,1);">
                                    <i class=" svg-inline--fa fas fa-pdf"></i> Imprimir resumen
                                </button>
                                <button type="button" id="CancelPDF" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"><svg class="svg-inline--fa fa-ban" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="ban" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M367.2 412.5L99.5 144.8C77.1 176.1 64 214.5 64 256c0 106 86 192 192 192c41.5 0 79.9-13.1 111.2-35.5zm45.3-45.3C434.9 335.9 448 297.5 448 256c0-106-86-192-192-192c-41.5 0-79.9 13.1-111.2 35.5L412.5 367.2zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256z"></path>
                                    </svg><!-- <i class="fa-solid fa-ban"></i> Font Awesome fontawesome.com --> Cancelar</button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()"><svg class="svg-inline--fa fa-circle-xmark" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle-xmark" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"></path>
                                    </svg><!-- <i class="fa-solid fa-circle-xmark"></i> Font Awesome fontawesome.com --> Salir</button>
                            </div>
                        </div>
                    </div>


                <?php
                } else { ?>
                    <div class="card">
                        <div class="card-header">Movimientos en Caja</div>
                        <div class="card-body">
                            <div class="container contenedort" style="max-width: 100% !important;">
                                <div class="row">
                                    <div class="col">
                                        <div class="text-center mb-2"><b>Debe de tener la caja aperturada para usar esta opcion.</b></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                }
                break;
            case 'historial_caja':

                $params = $_POST['xtra'];

                if (is_array($params)) {
                    list($fechaFilter, $agenciaFilter) = $params;
                } else {
                    $fechaFilter = $hoy;
                    $agenciaFilter = $idagencia;
                }

                $showmensaje = false;


                try {
                    $userPermissions = new PermissionManager($idusuario);

                    $condiPermission = "";
                    $condiAgencia = "";

                    if ($userPermissions->isLevelTwo(PermissionManager::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA)) {
                        /**
                         * PUEDE FILTRAR TODAS LAS AGENCIAS Y APROBAR/RECHAZAR
                         */
                    } elseif ($userPermissions->isLevelOne(PermissionManager::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA)) {
                        // $condiPermission = " AND us.id_agencia = $agenciaFilter ";
                        $condiAgencia = "id_agencia = $agenciaFilter ";
                        /**
                         * PUEDE FILTRAR SOLO SU AGENCIA Y APROBAR/RECHAZAR
                         */
                    } else {
                        /**
                         * NO PUEDE FILTRAR, SOLO VER SUS MOVIMIENTOS, NO PUEDE APROBAR/RECHAZAR
                         */
                        // $condiPermission = " AND us.id_usu = $idusuario ";
                        $condiAgencia = "id_agencia = $agenciaFilter ";
                    }

                    $condiPermission = ($agenciaFilter == 0) ? "" : " AND us.id_agencia = $agenciaFilter ";

                    $query = "SELECT us.nombre, us.apellido, movca.id, movca.total, movca.tipo, movca.created_at, us.id_agencia, 
                            us.id_usu, movca.estado, movca.detalle
                            FROM tb_usuario us
                            INNER JOIN tb_movimientos_caja movca ON us.id_usu = movca.created_by
                            WHERE DATE(movca.created_at) = ? $condiPermission
                            ORDER BY movca.created_at DESC";

                    $database->openConnection();

                    $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "nom_agencia", "cod_agenc"], $condiAgencia);

                    $bovedas = $database->selectColumns("bov_bovedas", ["id", "nombre"], "estado = '1'");

                    $movimientos = $database->getAllResults($query, [$fechaFilter]);
                    if (empty($movimientos)) {
                        $showmensaje = true;
                        throw new Exception("No existen movimientos en caja para la fecha seleccionada");
                    }

                    $status = true;
                } catch (Exception $e) {
                    if (!$showmensaje) {
                        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                    }
                    $mensaje = $showmensaje ? $e->getMessage() : "Error interno, reporte ($codigoError)";
                    $status  = false;
                } finally {
                    $database->closeConnection();
                }

                    ?>
                    <input type="text" id="condi" value="historial_caja" hidden>
                    <input type="text" id="file" value="caja001" hidden>
                    <div class="card">
                        <div class="card-header bg-success text-white text-center">
                            <h4>Historial de Movimientos de Caja</h4>
                        </div>
                        <div class="card-body bg-light">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="fecha" class="form-label text-success"><b>Fecha</b></label>
                                    <input type="date" class="form-control border-success shadow-sm" id="fecha" name="fecha" value="<?= $fechaFilter; ?>">
                                </div>
                                <div class="col-md-4" id="contAgencia" style="display: <?= ($userPermissions->hasNoAccess(PermissionManager::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA)) ? 'none' : 'block' ?>;">
                                    <label for="tipoOperacion" class="form-label text-success"><b>Agencia</b></label>
                                    <select class="form-select border-success shadow-sm" id="selectagencia">
                                        <!-- <option value="0" selected>Todas</option> -->
                                        <?php
                                        foreach ($agencias as $agencia) {
                                            $selected = ($agenciaFilter == $agencia['id_agencia']) ? "selected" : "";
                                            echo '<option value="' . $agencia['id_agencia'] . '" ' . $selected . '>' . $agencia['nom_agencia'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col align-items-center mt-2 mb-2" id="modal_footer">
                                <button class="btn btn-outline-success" id="BuscarSol" onclick="applyFilter();">
                                    <i class=" svg-inline--fa fas fa-search"></i> Buscar
                                </button>
                                <button type="button" id="CancelPDF" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"><svg class="svg-inline--fa fa-ban" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="ban" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M367.2 412.5L99.5 144.8C77.1 176.1 64 214.5 64 256c0 106 86 192 192 192c41.5 0 79.9-13.1 111.2-35.5zm45.3-45.3C434.9 335.9 448 297.5 448 256c0-106-86-192-192-192c-41.5 0-79.9 13.1-111.2 35.5L412.5 367.2zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256z"></path>
                                    </svg><!-- <i class="fa-solid fa-ban"></i> Font Awesome fontawesome.com --> Cancelar</button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()"><svg class="svg-inline--fa fa-circle-xmark" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle-xmark" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"></path>
                                    </svg><!-- <i class="fa-solid fa-circle-xmark"></i> Font Awesome fontawesome.com --> Salir</button>
                            </div>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="p-2 mb-3 bg-success text-white text-center">
                                        <b>Detalles</b>
                                    </div>
                                </div>
                            </div>
                            <div class="row align-items-center mb-4">
                                <div class="col-12">
                                    <table class="table table-hover table">
                                        <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Nombre</th>
                                                <th scope="col">Monto</th>
                                                <th scope="col">Movimiento</th>
                                                <th scope="col">Fecha y Hora</th>
                                                <th scope="col">Estado</th>
                                                <th scope="col">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabla-movimientos">
                                            <?php
                                            if ($status) {
                                                $contador = 1;
                                                foreach ($movimientos as $movimiento) {
                                                    $tipoMovimiento = ($movimiento['tipo'] == 1) ? 'Incremento' : 'Decremento';
                                                    $estadoMovimiento = '';
                                                    $acciones = '';

                                                    switch ($movimiento['estado']) {
                                                        case 0:
                                                            $estadoMovimiento = '<span class="badge bg-danger">Rechazado</span>';
                                                            $acciones = '<span class="text-muted">N/A</span>';
                                                            break;
                                                        case 1:
                                                            $estadoMovimiento = '<span class="badge bg-warning text-dark">Pendiente</span>';
                                                            if ($userPermissions->isLevelOne(PermissionManager::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA) || $userPermissions->isLevelTwo(PermissionManager::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA)) {
                                                                $acciones = '
                                                                    <button class="btn btn-info btn-sm" onclick="reportes([[], [], [], [' . $movimiento['id'] . ',\'solicitud\']], \'pdf\', \'28\', 0,1);">Ver Detalles</button>
                                                                    <button class="btn btn-sm btn-success" onclick="approveMovement(' . $movimiento['id'] . ', ' . $movimiento['detalle'] . ')">Aprobar</button>
                                                                    <button class="btn btn-sm btn-danger" onclick="rejectMovement(' . $movimiento['id'] . ')">Rechazar</button>
                                                                ';
                                                            } else {
                                                                $acciones = '<span class="text-muted">Sin acciones</span>';
                                                            }
                                                            break;
                                                        case 2:
                                                            $estadoMovimiento = '<span class="badge bg-success">Aprobado</span>';
                                                            $acciones = '<button class="btn btn-info btn-sm" onclick="reportes([[], [], [], [' . $movimiento['id'] . ',\'solicitud\']], \'pdf\', \'28\', 0,1);">Ver Detalles</button>';
                                                            break;
                                                        default:
                                                            $estadoMovimiento = '<span class="badge bg-secondary">Desconocido</span>';
                                                            $acciones = '<span class="text-muted">N/A</span>';
                                                            break;
                                                    }

                                                    echo '<tr>
                                                        <th scope="row">' . $contador++ . '</th>
                                                        <td>' . htmlspecialchars($movimiento['nombre'] . ' ' . $movimiento['apellido']) . '</td>
                                                        <td>Q ' . number_format($movimiento['total'], 2) . '</td>
                                                        <td>' . $tipoMovimiento . '</td>
                                                        <td>' . htmlspecialchars($movimiento['created_at']) . '</td>
                                                        <td>' . $estadoMovimiento . '</td>
                                                        <td>' . $acciones . '</td>
                                                    </tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="7" class="text-center text-danger">' . htmlspecialchars($mensaje) . '</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>

                    </div><br>

                    <!-- MODAL CON DESGLOSE -->
                    <div class="modal fade" id="modalConDesglose" tabindex="-1" aria-labelledby="modalConDesgloseLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">Total Solicitado</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <input type="number" id="idMovimientoConDesglose" hidden value="0">
                                <div class="modal-body">
                                    <div class="row" x-data="{ openSelectBoveda: false }">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="debitarBoveda" x-model="openSelectBoveda">
                                            <label class="form-check-label" for="debitarBoveda" id="labelDebitarBoveda">Usar bóveda</label>
                                        </div>
                                        <div class="mb-3" id="bovedaSelectContainer" x-show="openSelectBoveda">
                                            <label for="selectBoveda" class="form-label">Seleccionar Bóveda</label>
                                            <select class="form-select" id="selectBoveda" x-bind:required="openSelectBoveda">
                                                <option value="" disabled selected>Seleccione una bóveda</option>
                                                <?php
                                                foreach (($bovedas ?? []) as $boveda) {
                                                    echo '<option value="' . $boveda['id'] . '">' . htmlspecialchars($boveda['nombre']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>

                                    </div>
                                    <h5>Total: <span id="totalGeneral" class="text-success">GTQ 0.00</span></h5>
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="p-2 mb-3 bg-success text-white text-center">
                                                <b>Billetes</b>
                                            </div>
                                            <div class="row gy-3" id="billetesContainer"></div>
                                        </div>
                                    </div>
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="p-2 mb-3 bg-success text-white text-center">
                                                <b>Monedas</b>
                                            </div>
                                            <div class="row gy-3" id="monedasContainer"></div>
                                        </div>
                                    </div>
                                    <div class="text-center mt-3">
                                        <b>Total General:</b>
                                        <div class="input-group mt-3">
                                            <span class="input-group-text bg-success text-white">Q</span>
                                            <input type="number" id="montoTotal" min="0" class="form-control text-center" readonly>

                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                    <button type="button" class="btn btn-primary" onclick="saveAprobacionDetalle()">Aprobar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL PARA SIN DESGLOSE -->

                    <div class="modal fade" id="modalSinDesglose" tabindex="-1" aria-labelledby="modalSinDesgloseLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">Total Solicitado</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <input type="number" id="idMovimientoSinDesglose" hidden value="0" min="0">
                                <div class="modal-body">
                                    <div class="text-center">
                                        <h5>Total Solicitado</h5>
                                    </div>
                                    <div class="input-group mt-3">
                                        <span class="input-group-text bg-success text-white">Q</span>
                                        <input type="number" id="totalGeneralSinDesglose" min="0" class="form-control text-center" value="0" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                    <button type="button" class="btn btn-primary"
                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>','idMovimientoSinDesglose','totalGeneralSinDesglose'], [], [], 'aprobarMovimiento', '0', [2], function(){
                                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalSinDesglose'));
                                        modal.hide();
                                    }, '¿Confirma aprobar el movimiento?');">Aprobar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php echo $csrf->getTokenField(); ?>
                    <script>
                        function applyFilter() {
                            const fecha = document.getElementById('fecha').value;
                            const agencia = document.getElementById('selectagencia').value;
                            printdiv2('#cuadro', [fecha, agencia]);
                        }

                        function rejectMovement(movementId) {
                            obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'rechazarMovimiento', '0', [movementId], 'null', '¿Confirma rechazar el movimiento?');
                        }

                        function approveMovement(movementId, detailId) {
                            obtiene([], [], [], 'getDataMovimientoCaja', '0', [movementId], function(data) {
                                // console.log("Datos recibidos para el movimiento:", data);
                                if (data.dataMovimiento.detalle === 1) {
                                    openModalConDesglose(data.dataMovimiento, data.dataDetalle);
                                } else {
                                    openModalSinDesglose(data.dataMovimiento.total, movementId);
                                }
                            });
                        }

                        function openModalSinDesglose(montoTotal, movimientoId) {
                            const modal = new bootstrap.Modal(document.getElementById('modalSinDesglose'));
                            modal.show();
                            document.getElementById('idMovimientoSinDesglose').value = movimientoId;
                            document.getElementById('totalGeneralSinDesglose').value = montoTotal;
                        }

                        function openModalConDesglose(datosGenerales, detallesMovimiento) {
                            // console.log("Abriendo modal con desglose:", datosGenerales, detallesMovimiento);
                            const modal = new bootstrap.Modal(document.getElementById('modalConDesglose'));
                            modal.show();
                            document.getElementById('idMovimientoConDesglose').value = datosGenerales.id;
                            document.getElementById('totalGeneral').textContent = datosGenerales.total;
                            document.getElementById('montoTotal').value = datosGenerales.total;
                            document.getElementById('labelDebitarBoveda').textContent = datosGenerales.tipo == 1 ? 'Debitar de Bóveda' : 'Acreditar a Bóveda';

                            let contentBilletes = '';
                            let contentMonedas = '';
                            detallesMovimiento.forEach(detalle => {
                                if (detalle.tipo === '1') {
                                    contentBilletes += generarCardDenominacion(detalle.simbolo, detalle.monto, detalle.cantidad, detalle.id_denominacion);
                                } else if (detalle.tipo === '2') {
                                    contentMonedas += generarCardDenominacion(detalle.simbolo, detalle.monto, detalle.cantidad, detalle.id_denominacion);
                                }
                            });
                            document.getElementById('billetesContainer').innerHTML = contentBilletes;
                            document.getElementById('monedasContainer').innerHTML = contentMonedas;
                        }

                        function saveAprobacionDetalle() {
                            const detalle = {};
                            document.querySelectorAll('.den-input').forEach(i => detalle[i.id] = i.value);
                            const debitoBoveda = document.getElementById('debitarBoveda').checked ? 1 : 0;

                            obtiene(['<?= $csrf->getTokenName() ?>', 'idMovimientoConDesglose', 'montoTotal'], ['selectBoveda'], [], 'aprobarMovimiento', '0', [1, debitoBoveda, detalle], function() {
                                const modal = bootstrap.Modal.getInstance(document.getElementById('modalConDesglose'));
                                modal.hide();
                            }, '¿Confirma aprobar el movimiento?');

                        }
                        $(document).ready(function() {
                            inicializarValidacionAutomaticaGeneric('#modalSinDesglose');
                            inicializarValidacionAutomaticaGeneric('#modalConDesglose');
                        });
                    </script>



                <?php

                break;
            case 'historial_caja_anterior':
                $flagperm = 0;
                try {
                    $database->openConnection();
                    $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "nom_agencia", "cod_agenc"]);
                    $permisosAge = $database->getAllResults("SELECT COUNT(*) AS cantidad
                        FROM tb_usuario usu
                        INNER JOIN tb_autorizacion auto ON auto.id_usuario = usu.id_usu
                        INNER JOIN " . $db_name_general . ".tb_restringido rest ON rest.id = auto.id_restringido
                        WHERE auto.estado = 1 AND rest.estado = 1 AND auto.id_restringido = 13 AND usu.id_usu = ?", [$idusuario]);
                    if ($permisosAge[0]["cantidad"] == 1) {
                        $flagperm = 1;
                    }
                    $permisosAge = $database->getAllResults("SELECT COUNT(*) AS cantidad
                        FROM tb_usuario usu
                        INNER JOIN tb_autorizacion auto ON auto.id_usuario = usu.id_usu
                        INNER JOIN " . $db_name_general . ".tb_restringido rest ON rest.id = auto.id_restringido
                        WHERE auto.estado = 1 AND rest.estado = 1 AND auto.id_restringido = 12 AND usu.id_usu = ?", [$idusuario]);
                    if ($permisosAge[0]["cantidad"] == 1) {
                        $flagperm = 2;
                    }
                } catch (Exception $e) {
                    echo "Error: " . $e;
                } finally {
                    $database->closeConnection();
                }
                ?>
                    <script>
                        buscarmovi(<?php echo $flagperm ?>);
                    </script>
                    <input type="text" id="condi" value="apertura_caja" hidden>
                    <input type="text" id="file" value="caja001" hidden>
                    <div class="card">
                        <div class="card-header bg-success text-white text-center">
                            <h4>Historial de Movimientos de Caja</h4>
                        </div>
                        <div class="card-body bg-light">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="fecha" class="form-label text-success"><b>Fecha</b></label>
                                    <input type="date" class="form-control border-success shadow-sm" id="fecha" name="fecha" value="<?php echo $hoy; ?>">
                                </div>
                                <div class="col-md-4" id="contAgencia">
                                    <label for="tipoOperacion" class="form-label text-success"><b>Agencia</b></label>
                                    <select class="form-select border-success shadow-sm" id="selectagencia">
                                        <option value="0" selected>Todas</option>
                                        <?php
                                        foreach ($agencias as $agencia) {
                                            echo '<option value="' . $agencia['id_agencia'] . '">' . $agencia['nom_agencia'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col align-items-center mt-2 mb-2" id="modal_footer">
                                <button class="btn btn-outline-success" id="BuscarSol" onclick="buscarmovi(<?php echo $flagperm; ?>);">
                                    <i class=" svg-inline--fa fas fa-search"></i> Buscar
                                </button>
                                <button type="button" id="CancelPDF" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')"><svg class="svg-inline--fa fa-ban" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="ban" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M367.2 412.5L99.5 144.8C77.1 176.1 64 214.5 64 256c0 106 86 192 192 192c41.5 0 79.9-13.1 111.2-35.5zm45.3-45.3C434.9 335.9 448 297.5 448 256c0-106-86-192-192-192c-41.5 0-79.9 13.1-111.2 35.5L412.5 367.2zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256z"></path>
                                    </svg><!-- <i class="fa-solid fa-ban"></i> Font Awesome fontawesome.com --> Cancelar</button>
                                <button type="button" class="btn btn-outline-warning" onclick="salir()"><svg class="svg-inline--fa fa-circle-xmark" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle-xmark" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                        <path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"></path>
                                    </svg><!-- <i class="fa-solid fa-circle-xmark"></i> Font Awesome fontawesome.com --> Salir</button>
                            </div>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="p-2 mb-3 bg-success text-white text-center">
                                        <b>Detalles</b>
                                    </div>
                                </div>
                            </div>
                            <div class="row align-items-center mb-4">
                                <div class="col-12">
                                    <table class="table table-hover table">
                                        <thead>
                                            <tr>
                                                <th scope="col">#</th>
                                                <th scope="col">Nombre</th>
                                                <th scope="col">Monto</th>
                                                <th scope="col">Movimiento</th>
                                                <th scope="col">Fecha y Hora</th>
                                                <th scope="col">Estado</th>
                                                <th scope="col">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabla-movimientos">
                                            <!-- Las filas generadas dinámicamente se insertarán aquí -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="pagination-container">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center" id="pagination-controls">
                                        <!-- Controles de paginación se generarán dinámicamente -->
                                    </ul>
                                </nav>
                            </div>

                        </div>

                    </div><br>

                    <!-- Modal -->
                    <div class="modal fade" id="modalDenominaciones" tabindex="-1" aria-labelledby="modalDenominacionesLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="modalDenominacionesLabel">Detalles de Denominaciones</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Aquí se insertarán las tarjetas de denominaciones (billetes y monedas) -->
                                    <div id="formularioDesglose">
                                        <!-- Las tarjetas de billetes y monedas se insertarán aquí -->
                                    </div>
                                </div>
                                <div class="modal-footer text-start">
                                    <h5 class="text-center text-success">Total Solicitado</h5>
                                    <div class="input-group mt-3">
                                        <span class="input-group-text bg-success text-white">Q</span>
                                        <input type="number" id="totalGeneral" min="0" class="form-control text-center" value="0" disabled>
                                    </div>
                                    <!-- Botones alineados a la izquierda -->
                                    <button type="button" class="btn btn-primary">Aprobar</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                </div>
                            </div>
                        </div>
                    </div>



            <?php

                break;
        } ?>