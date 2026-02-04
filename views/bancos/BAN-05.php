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
// require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

use Micro\Helpers\Log;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

include 'funciones/funciones.php';

$condi = $_POST["condi"];

switch ($condi) {
    case 'cuentasBancos':

        $idCuentaSeleccionada = $_POST['xtra'];

        $isNewAccount = true;

        $showmensaje = false;
        try {

            $database->openConnection();
            $cuentasBancosExistentes = $database->getAllResults("SELECT cb.id, cb.id_banco, tbn.nombre, cb.id_nomenclatura, ctn.ccodcta, ctn.cdescrip, cb.numcuenta, cb.saldo_ini 
                                        FROM ctb_bancos cb
                                        INNER JOIN ctb_nomenclatura ctn ON cb.id_nomenclatura=ctn.id
                                        INNER JOIN tb_bancos tbn ON cb.id_banco=tbn.id
                                        WHERE cb.estado='1'");

            $bancosExistentes = $database->selectColumns('tb_bancos', ['id', 'nombre'], 'estado=1');
            if (empty($bancosExistentes)) {
                $showmensaje = true;
                throw new Exception("No hay bancos registrados, por favor registre al menos un banco.");
            }

            $ctbNomenclatura = $database->selectColumns("ctb_nomenclatura", ["id", "ccodcta", "cdescrip", "tipo"], "estado=1", orderBy: "ccodcta");
            if (empty($ctbNomenclatura)) {
                $showmensaje = true;
                throw new Exception("No hay cuentas contables registradas.");
            }

            if ($idCuentaSeleccionada != '0') {
                $isNewAccount = false;

                $cuentaSeleccionada = $database->selectColumns('ctb_bancos', ['*'], 'id=? AND estado=1', [$idCuentaSeleccionada]);
                if (empty($cuentaSeleccionada)) {
                    $showmensaje = true;
                    throw new Exception("Cuenta bancaria no encontrada, verifique su estado.");
                }
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
        <div class="text" style="text-align:center">CUENTAS DE BANCOS</div>
        <input type="text" id="condi" value="cuentasBancos" hidden>
        <input type="text" id="file" value="BAN-05" hidden>
        <div class="card">
            <div class="card-header">Cuentas de bancos</div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="contenedort container" id="formCuentaBanco">
                    <div class="row g-3 mt-3 mb-2">
                        <div class="col-12 col-md-6">
                            <label for="id_banco" class="form-label fw-bold">Banco</label>
                            <select class="form-select select2" name="id_banco[]" id="id_banco" required>
                                <option value="">Seleccione...</option>
                                <?php
                                foreach ($bancosExistentes as $currentBank):
                                    $selected = ($currentBank['id'] == ($cuentaSeleccionada[0]['id_banco'] ?? 0)) ? 'selected' : '';
                                    echo '<option value="' . $currentBank['id'] . '" ' . $selected . '>' . htmlspecialchars($currentBank['nombre']) . '</option>';
                                endforeach;
                                ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="id_ctb_nomenclatura" class="form-label fw-bold">Cuenta contable</label>
                            <select class="form-select select2" name="id_ctb_nomenclatura[]" id="id_ctb_nomenclatura" required>
                                <option value="">Seleccione...</option>
                                <?php
                                $currentGroup = null;
                                foreach ($ctbNomenclatura as $key => $nomenclatura):
                                    $indentationLevel = strlen($nomenclatura['ccodcta']) / 2;
                                    $indentation = str_repeat('&nbsp;', $indentationLevel);

                                    if ($nomenclatura['tipo'] === 'R'):
                                        if ($currentGroup !== null) {
                                            echo '</optgroup>';
                                        }
                                        $currentGroup = $nomenclatura['cdescrip'];
                                        echo '<optgroup label="' . $indentation . $currentGroup . '">';
                                    else:
                                        $selected = ($nomenclatura['id'] == ($cuentaSeleccionada[0]['id_nomenclatura'] ?? 0)) ? 'selected' : '';
                                        echo '<option ' . $selected . ' value="' . $nomenclatura['id'] . '">' . $indentation . $nomenclatura['ccodcta'] . ' - ' . $nomenclatura['cdescrip'] . '</option>';
                                    endif;
                                endforeach;
                                if ($currentGroup !== null) {
                                    echo '</optgroup>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-2">
                        <div class="col-12 col-md-6">
                            <label for="cuenta" class="form-label fw-bold">No. de cuenta</label>
                            <input type="text" class="form-control" id="cuenta" placeholder="Ingrese una cuenta" required value="<?= htmlspecialchars($cuentaSeleccionada[0]['numcuenta'] ?? '') ?>">
                        </div>
                        <!--
                        <div class="col-12 col-md-6">
                            <label for="saldo" class="form-label fw-bold">Saldo</label>
                            <input type="text" class="form-control" id="saldo" placeholder="Ingrese una cuenta">
                        </div>
                        -->
                    </div>
                    <div class="row mt-3 mb-2">
                        <?php if ($status && $isNewAccount) { ?>
                            <div class="col-12 col-md-4 d-grid mb-2">
                                <button type="button" class="btn btn-success" id="btGuardar" onclick="obtiene([`cuenta`],[`id_banco`,`id_ctb_nomenclatura`],[],`create_cuentasbancos`,`0`,[],'NULL','¿Está seguro de guardar esta cuenta de bancos?')">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                                </button>
                            </div>
                        <?php } ?>

                        <?php if ($status && !$isNewAccount) { ?>
                            <div class="col-12 col-md-4 d-grid mb-2">
                                <button type="button" class="btn btn-primary" id="btEditar" onclick="obtiene([`cuenta`],[`id_banco`,`id_ctb_nomenclatura`],[],`update_cuentasbancos`,`0`,[<?= $idCuentaSeleccionada ?>],'NULL','¿Está seguro de actualizar esta cuenta de bancos?')">
                                    <i class="fa-solid fa-floppy-disk"></i> Actualizar
                                </button>
                            </div>
                        <?php } ?>
                        <div class="col-12 col-md-2 d-grid mb-2">
                            <button type="button" class="btn btn-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                        </div>
                        <!-- <div class="col-12 col-md-2 d-grid mb-2">
                            <button type="button" class="btn btn-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div> -->
                    </div>
                </div>
                <!-- tabla para los  -->
                <div class="contenedort container">
                    <div class="row mt-4 mb-4">
                        <div class="col">
                            <div class="table-responsive">
                                <table class="table" id="tb_cuentasbancos">
                                    <thead>
                                        <tr style="font-size: 0.8rem;">
                                            <th>#</th>
                                            <th>Banco</th>
                                            <th>Cod. cuenta contable</th>
                                            <th>Nombre cuenta contable</th>
                                            <th>Cuenta destino</th>
                                            <th>Editar/Eliminar</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider" style="font-size: 0.8rem;">
                                        <?php
                                        foreach (($cuentasBancosExistentes ?? []) as $key => $fila) { ?>
                                            <tr>
                                                <th scope="row"><?= $key + 1 ?></th>
                                                <td><?= $fila['nombre'] ?></td>
                                                <td><?= $fila['ccodcta'] ?></td>
                                                <td><?= $fila['cdescrip'] ?></td>
                                                <td><?= $fila['numcuenta'] ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-success btn-sm" onclick="printdiv2('#cuadro',<?= $fila['id'] ?>);"><i class="fa-solid fa-eye"></i></button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="obtiene([],[],[],`delete_cuentasbancos`,`0`,[<?= $fila['id'] ?>],'NULL','¿Está seguro de eliminar esta cuenta de bancos?')"><i class="fa-solid fa-trash"></i></button>

                                                    <?php if ($status && $isNewAccount) { ?>
                                                        <button type="button" class="btn btn-warning btn-sm" onclick="printdiv('saldosXMesBancos','#cuadroSaldos','BAN-05',<?= $fila['id'] ?>);" title="Agregar saldos manuales"><i class="fa-solid fa-money-bill"></i></button>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="cuadroSaldos">


                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $('.select2').select2({
                    theme: 'classic',
                    width: '100%',
                    placeholder: "Seleccione una opción",
                    allowClear: true,
                });
                convertir_tabla_a_datatable('tb_cuentasbancos');
                inicializarValidacionAutomaticaGeneric('#formCuentaBanco');
            });
        </script>
    <?php
        break;

    case 'saldosXMesBancos':
        $idCuentaSeleccionadaA = $_POST['xtra'];

        $showmensaje = false;
        try {

            $database->openConnection();

            $cuentaActual = $database->getAllResults("SELECT tbn.nombre, cb.numcuenta
                                        FROM ctb_bancos cb
                                        INNER JOIN tb_bancos tbn ON cb.id_banco=tbn.id
                                        WHERE cb.estado='1' AND cb.id=?", [$idCuentaSeleccionadaA]);
            if (empty($cuentaActual)) {
                $showmensaje = true;
                throw new Exception("Cuenta bancaria no encontrada, verifique su estado.");
            }

            $saldosCreados = $database->selectColumns(
                'ctb_saldos_bancos',
                ['id', 'id_cuenta_banco', 'mes', 'anio', 'saldo_inicial'],
                'id_cuenta_banco=?',
                [$idCuentaSeleccionadaA],
                orderBy: 'anio, mes'
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

        $catalogoMeses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        // Agrupar saldos por año
        $saldosPorAnio = [];
        foreach ($saldosCreados as $saldo) {
            $saldosPorAnio[$saldo['anio']][] = $saldo;
        }

    ?>
        <input type="text" id="condi" value="saldosXMesBancos" hidden>
        <input type="text" id="file" value="BAN-05" hidden>
        <div class="contenedort container">
            <?php if (!$status) { ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>!!</strong> <?= $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>
            <?php
            $anioActual = date('Y');
            $mesActual = date('n');

            // Mostrar el cuadro del año actual SIEMPRE
            $saldosActual = $saldosPorAnio[$anioActual] ?? [];
            ?>
            <div class="card mb-3" id="targetSaldoAnioActual">
                <div class="card-header fw-bold">Saldos del año <?= $anioActual ?> Cuenta: <?= $cuentaActual[0]['nombre'] ?? '' ?> (<?= $cuentaActual[0]['numcuenta'] ?? '' ?>)</div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th>Saldo Inicial</th>
                                <th>Cambiar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            for ($mes = 1; $mes <= 12; $mes++) {
                                $saldoMes = null;
                                foreach ($saldosActual as $s) {
                                    if ((int)$s['mes'] === $mes) {
                                        $saldoMes = $s;
                                        break;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?= $catalogoMeses[$mes] ?></td>
                                    <td><?= $saldoMes ? number_format($saldoMes['saldo_inicial'], 2) : '-' ?></td>
                                    <td>
                                        <?php if ($mes <= $mesActual):
                                            $valor = $saldoMes ? $saldoMes['saldo_inicial'] : '';
                                            $idSaldo = $saldoMes ? $saldoMes['id'] : '0';
                                        ?>
                                            <input type="number" step="0.01" class="form-control d-inline-block" style="width:120px;" id="saldo_<?= $anioActual ?>_<?= $mes ?>" value="<?= htmlspecialchars($valor) ?>">
                                            <button type="button" class="btn btn-sm btn-success ms-2"
                                                onclick="obtiene(['saldo_<?= $anioActual ?>_<?= $mes ?>'],[],[],`cambioSaldoCuentaBanco`,`0`,[<?= $idCuentaSeleccionadaA ?>,<?= $anioActual ?>,<?= $mes ?>,<?= $idSaldo ?>],printNew,'¿Está seguro de cambiar el saldo de esta cuenta de bancos para el mes <?= $catalogoMeses[$mes] ?>?');">Guardar Cambio</button>
                                        <?php else: ?>
                                            <?= $saldoMes ? '<span class="badge bg-secondary">Registrado</span>' : '-' ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            // Mostrar los demás años si existen
            foreach ($saldosPorAnio as $anio => $saldos) {
                if ($anio == $anioActual) continue;
            ?>
                <div class="card mb-3">
                    <div class="card-header fw-bold">Saldos del año <?= $anio . ' Cuenta: ' . ($cuentaActual[0]['nombre'] ?? '') . ' (' . ($cuentaActual[0]['numcuenta'] ?? '') . ')' ?></div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Mes</th>
                                    <th>Saldo Inicial</th>
                                    <th>Cambiar</th>
                                    <!-- <th>Acción</th> -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                for ($mes = 1; $mes <= 12; $mes++) {
                                    $saldoMes = null;
                                    foreach ($saldos as $s) {
                                        if ((int)$s['mes'] === $mes) {
                                            $saldoMes = $s;
                                            break;
                                        }
                                    }
                                    $valor2 = $saldoMes ? $saldoMes['saldo_inicial'] : '';
                                    $idSaldo2 = $saldoMes ? $saldoMes['id'] : '0';
                                ?>
                                    <tr>
                                        <td><?= $catalogoMeses[$mes] ?></td>
                                        <td><?= $saldoMes ? number_format($saldoMes['saldo_inicial'], 2) : '-' ?></td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control d-inline-block" style="width:120px;" id="saldo_<?= $anio ?>_<?= $mes ?>" value="<?= htmlspecialchars($valor2) ?>">
                                            <button type="button" class="btn btn-sm btn-success ms-2"
                                                onclick="obtiene(['saldo_<?= $anio ?>_<?= $mes ?>'],[],[],`cambioSaldoCuentaBanco`,`0`,[<?= $idCuentaSeleccionadaA ?>,<?= $anio ?>,<?= $mes ?>,<?= $idSaldo2 ?>],printNew,'¿Está seguro de cambiar el saldo de esta cuenta de bancos para el mes <?= $catalogoMeses[$mes] ?>?');">Guardar Cambio</button>
                                        </td>
                                    </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php
            }
            // Formulario para habilitar un nuevo año si no existe
            $aniosExistentes = array_keys($saldosPorAnio);
            $aniosExistentes[] = $anioActual;
            $aniosExistentes = array_unique($aniosExistentes);
            $anioMin = $anioActual - 10;
            $anioMax = $anioActual + 1;
            ?>
            <div class="card mb-3">
                <div class="card-header fw-bold">Agregar saldos para otro año</div>
                <div class="card-body">
                    <form class="row g-2" onsubmit="event.preventDefault(); habilitarCuadroAnio();">
                        <div class="col-auto">
                            <label for="nuevo_anio" class="form-label">Año:</label>
                            <select id="nuevo_anio" class="form-select">
                                <?php
                                for ($a = $anioMin; $a <= $anioMax; $a++) {
                                    if (!in_array($a, $aniosExistentes)) {
                                        echo '<option value="' . $a . '">' . $a . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Habilitar cuadro</button>
                        </div>
                    </form>
                    <div id="cuadro_nuevo_anio"></div>
                </div>
            </div>
        </div>
        <script>
            function printNew() {
                printdiv('saldosXMesBancos', '#cuadroSaldos', 'BAN-05', <?= $idCuentaSeleccionadaA ?? 0 ?>);
                // enfocar el elemento targetSaldoAnioActual después de que printdiv termine
                setTimeout(function() {
                    var target = document.getElementById('targetSaldoAnioActual');
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                }, 2000); // ajusta el tiempo si printdiv es más lento
            }

            function habilitarCuadroAnio() {
                var anio = document.getElementById('nuevo_anio').value;
                var idCuenta = <?= json_encode($idCuentaSeleccionadaA) ?>;
                var html = '<div class="card mt-3"><div class="card-header fw-bold">Saldos del año ' + anio + '</div><div class="card-body">';
                html += '<table class="table table-bordered"><thead><tr><th>Mes</th><th>Saldo Inicial</th><th>Acción</th></tr></thead><tbody>';
                for (var mes = 1; mes <= 12; mes++) {
                    html += '<tr>';
                    html += '<td>' + new Date(2000, mes - 1, 1).toLocaleString("es-ES", {
                        month: "long"
                    }) + '</td>';
                    html += '<td><input type="number" step="0.01" class="form-control d-inline-block" style="width:120px;" id="saldo_' + anio + '_' + mes + '" value=""></td>';
                    html += '<td><button type="button" class="btn btn-sm btn-success" onclick="obtiene([\'saldo_' + anio + '_' + mes + '\'],[],[],`cambioSaldoCuentaBanco`,`0`,[' + idCuenta + ',' + anio + ',' + mes + ',0],printNew,`¿Está seguro de cambiar el saldo de esta cuenta de bancos para el mes ' + mes + ' del año ' + anio + '?`)">Guardar</button></td>';
                    html += '</tr>';
                }
                html += '</tbody></table></div></div>';
                document.getElementById('cuadro_nuevo_anio').innerHTML = html;
            }
        </script>
    <?php
        break;
    case 'conciliacion':
        $showmensaje = false;
        try {

            $database->openConnection();
            $cuentasBancosExistentes = $database->getAllResults("SELECT cb.id, tbn.nombre, ctn.ccodcta, ctn.cdescrip, cb.numcuenta
                                        FROM ctb_bancos cb
                                        INNER JOIN ctb_nomenclatura ctn ON cb.id_nomenclatura=ctn.id
                                        INNER JOIN tb_bancos tbn ON cb.id_banco=tbn.id
                                        WHERE cb.estado='1'");

            if (empty($cuentasBancosExistentes)) {
                $showmensaje = true;
                throw new Exception("No hay cuentas bancarias registradas, por favor registre al menos una cuenta.");
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
        <input type="text" id="file" value="BAN-05" style="display: none;">
        <input type="text" id="condi" value="conciliacion" style="display: none;">
        <div class="text" style="text-align:center">CONCILIACION BANCARIA POR CUENTAS</div>
        <div class="card">
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="row container contenedort">
                    <div class="col-md-12 col-lg-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header"> Cuentas</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <label for="idcuenta" class="form-label fw-bold">Cuenta de banco</label>
                                        <select class="form-select select2" name="idcuenta[]" id="idcuenta" required>
                                            <option value="">Seleccione...</option>
                                            <?php
                                            foreach (($cuentasBancosExistentes ?? []) as $currentBank):
                                                echo '<option value="' . $currentBank['id'] . '">' . htmlspecialchars($currentBank['nombre']) . ' - ' . htmlspecialchars($currentBank['numcuenta']) . ' - ' . htmlspecialchars($currentBank['cdescrip']) . '</option>';
                                            endforeach;
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class=" col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                    <div class=" col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-primary" title="Libro Bancos en pdf" onclick="procesar()">
                            <i class="fa-solid fa-rotate"></i></i> Procesar
                        </button>
                    </div>
                </div>
                <br>
                <div id="div_movimientos">

                </div>

            </div>
        </div>
        <script>
            $(document).ready(function() {
                $('.select2').select2({
                    theme: 'classic',
                    width: '100%',
                    placeholder: "Seleccione una opción",
                    allowClear: true,
                });
            });

            function procesar() {
                const validacion = validarCamposGeneric(['finicio', 'ffin'], ['idcuenta'], []);

                if (!validacion.esValido) {
                    return false;
                }
                datosval = getinputsval(['idcuenta', 'finicio', 'ffin']);

                printdiv('movimientos', '#div_movimientos', 'BAN-05', datosval)
            }
        </script>
    <?php
        break;

    case 'movimientos':

        list($idCuentaBanco, $fecha_inicio, $fecha_fin) = $_POST['xtra'];

        $showmensaje = false;
        $saldoInicial = 0;
        try {

            if ($fecha_inicio > $fecha_fin) {
                $showmensaje = true;
                throw new Exception("Rango de fechas inválidos");
            }

            //validar que las fechas sean del mismo año
            $fechaini = strtotime($fecha_inicio);
            $fechafin = strtotime($fecha_fin);
            $anioini = date("Y", $fechaini);
            $mesini = date("m", $fechaini);
            $aniofin = date("Y", $fechafin);

            if ($anioini != $aniofin) {
                $showmensaje = true;
                throw new Exception("Las fechas tienen que ser del mismo año");
            }

            $database->openConnection();

            $cuentaBanco = $database->getAllResults("SELECT tbn.nombre, cb.numcuenta,id_nomenclatura
                                        FROM ctb_bancos cb
                                        INNER JOIN tb_bancos tbn ON cb.id_banco=tbn.id
                                        WHERE cb.estado='1' AND cb.id=?", [$idCuentaBanco]);
            if (empty($cuentaBanco)) {
                $showmensaje = true;
                throw new Exception("La cuenta bancaria no existe o no está activa.");
            }

            /*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++ CONSULTA DE SALDO DE LA CUENTA DE BANCOS ++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            $saldoInicial = calcularSaldoInicial($cuentaBanco[0]['id_nomenclatura'], $idCuentaBanco, $fecha_inicio, $fecha_fin, $database);


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
        <input type="text" id="condi" value="movimientos" hidden>
        <input type="text" id="file" value="BAN-05" hidden>
        <div class="card row container contenedort">
            <div class="card-header">
                <div class="row">
                    <h4>Movimientos de la cuenta de Bancos</h4>
                    <div class="col-8">
                        <h4><span class="badge text-bg-success"><?= $cuentaBanco[0]['nombre'] ?> ** <?= $cuentaBanco[0]['numcuenta'] ?></span></h4>
                    </div>
                    <div class="col-4">
                        <label for="salini"><span class="badge text-bg-success">SALDO INICIAL</span></label>
                        <input style="text-align: right;" type="number" step="0.01" class="form-control" id="salini" value="<?= $saldoInicial; ?>">
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <table id="tbmovimientos" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th title="En tránsito">ET</th>
                            <th style="width: 9%;">Fecha</th>
                            <!-- <th>Partida</th> -->
                            <th>Descripcion</th>
                            <th>Debe</th>
                            <th>Haber</th>
                            <th>Doc.</th>
                            <th>Destino</th>
                        </tr>
                    </thead>
                </table>
            </div>
            <div class="row justify-items-md-center">
                <div class="col align-items-center">
                    <?php if ($status) { ?>
                        <button type="button" class="btn btn-outline-success" title="Excel" onclick="reportes([['salini'],[],[],[<?= $idCuentaBanco ?>, '<?= $fecha_inicio ?>', '<?= $fecha_fin ?>', recolectar_checks2(tabla1)]], `xlsx`, `conciliacion`, 1);">
                            <i class="fa-solid fa-file-excel"></i>Excel
                        </button>
                    <?php } ?>
                    <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="salir()">
                        <i class="fa-solid fa-circle-xmark"></i> Salir
                    </button>
                </div>
            </div>
        </div>
        <style>
            #tbmovimientos td {
                font-size: 0.75rem;
            }
        </style>
        <script>
            function recolectar_checks2(tabla) {
                checkboxActivados = [];
                tabla.rows().every(function(rowIdx, tableLoop, rowLoop) {
                    var checkbox = $(this.node()).find('input[type="checkbox"]');
                    if (checkbox.is(':checked')) {
                        checkboxActivados.push(checkbox.val());
                    }
                });
                return (checkboxActivados);
            }

            function loadconfig(datas, nomtabla) {
                var tabla = $('#' + nomtabla).on('search.dt').DataTable({
                    "aProcessing": true,
                    "aServerSide": true,
                    "ordering": false,
                    "lengthMenu": [
                        [10, 15, -1],
                        ['10 filas', '15 filas', 'Mostrar todos']
                    ],
                    "ajax": {
                        url: "../src/cruds/crud_bancos.php",
                        type: "POST",
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        data: {
                            'condi': "movimientos_banco",
                            datas
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

            tabla1 = loadconfig([<?= $cuentaBanco[0]['id_nomenclatura'] ?>, '<?= $fecha_inicio ?>', '<?= $fecha_fin ?>'], "tbmovimientos");
        </script>
    <?php
        break;

    case 'cheques_compensacion':

        $showmensaje = false;
        try {

            $database->openConnection();

            $chequesCompensacion = $database->getAllResults(
                "SELECT 
                    bam.numero,bam.fecha,bam.estado,bam.id_ctb_diario, 
                    dia.glosa,
                    dia.fecdoc,
                    dia.numdoc,
                    dia.karely,
                    ban.nombre AS nombreBanco,
                    COALESCE(aho.monto, apr.monto, cre.NMONTO, '2') AS monto
                FROM ctb_ban_mov bam
                INNER JOIN ctb_diario dia ON dia.id = bam.id_ctb_diario
                LEFT JOIN tb_bancos ban ON ban.id = bam.id_cuenta_banco
                LEFT JOIN ahommov aho ON dia.karely LIKE 'AHO_%' AND aho.id_mov = SUBSTRING(dia.karely, 5)
                LEFT JOIN aprmov apr ON dia.karely LIKE 'APR_%' AND apr.id_mov = SUBSTRING(dia.karely, 5)
                LEFT JOIN CREDKAR cre ON dia.karely LIKE 'CRE_%' AND cre.CODKAR = SUBSTRING(dia.karely, 5)
                WHERE bam.estado IS NOT NULL AND dia.estado = 1"
            );
            if (empty($chequesCompensacion)) {
                $showmensaje = true;
                throw new Exception("No hay cheques por revisar.");
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
        <input type="text" id="file" value="BAN-05" style="display: none;">
        <input type="text" id="condi" value="cheques_compensacion" style="display: none;">

        <div class="text" style="text-align:center">CHEQUES RECIBIDOS</div>
        <div class="card">
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle shadow-sm rounded" id="tb_cheques_compensacion" style="font-size:0.85rem;">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width:40px;">#</th>
                                <th>Banco</th>
                                <th>No.Chq</th>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th class="text-end">Monto</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($chequesCompensacion ?? []) as $key => $cheque): ?>
                                <tr>
                                    <td class="text-center"><?= $key + 1 ?></td>
                                    <td><?= htmlspecialchars($cheque['nombreBanco'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($cheque['numero'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(setdatefrench($cheque['fecha'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($cheque['glosa'] ?? '') ?></td>
                                    <td class="text-end"><?= number_format($cheque['monto'], 2) ?></td>
                                    <td class="text-center">
                                        <?php
                                        $estados = [
                                            0 => 'Rechazado',
                                            1 => 'En compensacion',
                                            2 => 'Compensado'
                                        ];
                                        $estadoActual = $cheque['estado'] ?? 1;
                                        if ($estadoActual != 1) {
                                            echo '<span class="badge rounded-pill bg-' . ($estadoActual == 2 ? 'success' : ($estadoActual == 0 ? 'danger' : 'warning')) . '">' . $estados[$estadoActual] . '</span>';
                                        }
                                        ?>
                                        <?php if ($estadoActual == 1): ?>
                                            <button type="button" class="btn btn-outline-success btn-sm me-1" onclick="compensarCheque(<?= $cheque['id_ctb_diario'] ?>)">
                                                <i class="fa-solid fa-check"></i> Compensado
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="anularCheque(<?= $cheque['id_ctb_diario'] ?>, <?= $cheque['karely'] ? 1 : 0 ?>)">
                                                <i class="fa-solid fa-ban"></i> Rechazado
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
            function compensarCheque(idDiario) {
                obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 2, 0], 'NULL', '¿Está seguro de cambiar el estado del cheque?');
            }

            // function anularCheque(idDiario, karely) {
            //     obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 0], 'NULL', '¿Está seguro de rechazar el cheque?');
            // }
            function anularCheque(idDiario, karely) {
                if (karely === 1) {
                    Swal.fire({
                        title: '¿Qué acción desea realizar al rechazar el cheque?',
                        text: 'Puede eliminar la transacción vinculada o no tocarla.',
                        icon: 'warning',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: 'Eliminar transacción',
                        denyButtonText: 'No tocar la transaccion, solo cambiar el estado del cheque',
                        cancelButtonText: 'Cancelar operacion'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 1], 'NULL', '¿Está seguro de eliminar la transacción vinculada al cheque, tambien se eliminará la partida de diario?');
                        } else if (result.isDenied) {
                            obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 0], 'NULL', '¿Está seguro de rechazar el cheque sin modificar la transacción vinculada?');
                        } else {
                            // console.log('Operación cancelada por el usuario.');
                        }
                    });
                } else {
                    obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 0], 'NULL', '¿Está seguro de rechazar el cheque?');
                }
            }

            // function cambiarEstadoCheque(idDiario, nuevoEstado) {
            //     if (karely === 1) {
            //         Swal.fire({
            //             title: '¿Qué acción desea realizar al rechazar el cheque?',
            //             text: 'Puede eliminar la transacción vinculada, hacer una transacción de retorno o reversa, o no tocarla.',
            //             icon: 'warning',
            //             showCancelButton: true,
            //             showDenyButton: true,
            //             confirmButtonText: 'Eliminar transacción',
            //             denyButtonText: 'Transacción de reversa',
            //             cancelButtonText: 'No tocar'
            //         }).then((result) => {
            //             if (result.isConfirmed) {
            //                 obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 1], 'NULL', '¿Está seguro de eliminar la transacción vinculada al cheque?');
            //             } else if (result.isDenied) {
            //                 obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 2], 'NULL', '¿Está seguro de realizar una transacción de reversa?');
            //             } else {
            //                 // No tocar, solo cambiar estado
            //                 obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 0], 'NULL', '¿Está seguro de rechazar el cheque sin modificar la transacción vinculada?');
            //             }
            //         });
            //     } else {
            //         obtiene([], [], [], `change_status_cheque`, `0`, [idDiario, 0, 0], 'NULL', '¿Está seguro de rechazar el cheque?');
            //     }

            // }
            $(document).ready(function() {
                $('#tb_cheques_compensacion').DataTable({
                    language: {
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
}
?>