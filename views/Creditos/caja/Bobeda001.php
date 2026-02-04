<?php

use Micro\Generic\Date;

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
require_once __DIR__ . '/../../../includes/Config/Configuracion.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$puestouser = $_SESSION["puesto"];
// $puestouser = "LOG";
$condi = $_POST["condi"];
switch ($condi) {
    case 'create_bovedas':

        $extra = $_POST['xtra'];
        $showmensaje = false;
        try {
            $database->openConnection();
            $bovedasRegistradas = $database->selectColumns("bov_bovedas", ["id", "nombre", "id_agencia", "id_nomenclatura"], "estado='1'");

            $nomenclaturaCatalogo = $database->selectColumns("ctb_nomenclatura", ["id", "ccodcta", "cdescrip", "tipo"], "estado=1", orderBy: "ccodcta");

            $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "nom_agencia", "cod_agenc"]);

            if ($extra != "0") {
                $bovedaSeleccionada = $database->selectColumns("bov_bovedas", ["id", "nombre", "id_agencia", "id_nomenclatura"], "id=?", [$extra]);
                if (empty($bovedaSeleccionada)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la bóveda seleccionada.");
                }
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
        <input type="hidden" id="condi" value="create_bovedas">
        <input type="hidden" id="file" value="Bobeda001">
        <div class="text-center fw-bold my-4">GESTIÓN DE BÓVEDAS</div>
        <div class="row g-4">
            <!-- Formulario de Bóveda -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold">
                        <?= isset($bovedaSeleccionada) ? 'Editar Bóveda' : 'Crear Nueva Bóveda' ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$status): ?>
                            <div class="alert alert-warning"><?= $mensaje ?></div>
                        <?php endif; ?>

                        <form id="formBoveda" class="row g-3">
                            <div class="col-12">
                                <label for="nomboveda" class="form-label">Nombre de la Bóveda</label>
                                <input type="text" class="form-control" id="nomboveda" data-label="Nombre de la Bóveda" value="<?= $bovedaSeleccionada[0]['nombre'] ?? '' ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="agencia" class="form-label">Agencia</label>
                                <select id="agencia" class="form-select" required data-label="agencia">
                                    <option value="" disabled <?= $extra == "0" ? 'selected' : '' ?>>Seleccione una agencia</option>
                                    <?php foreach ($agencias as $a): ?>
                                        <option value="<?= $a['id_agencia'] ?>" <?= ($extra != "0" && $a['id_agencia'] == $bovedaSeleccionada[0]['id_agencia']) ? 'selected' : '' ?>>
                                            <?= "{$a['cod_agenc']} – {$a['nom_agencia']}" ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="nomenclatura" class="form-label">Nomenclatura Contable</label>
                                <select data-label="Cuenta contable" class="form-select select2" name="id_ctb_nomenclatura[]" required id="nomenclatura">
                                    <option value="">Seleccione...</option>
                                    <?php
                                    $currentGroup = null;
                                    foreach ($nomenclaturaCatalogo as $key => $nomenclatura):
                                        $indentationLevel = strlen($nomenclatura['ccodcta']) / 2;
                                        $indentation = str_repeat('&nbsp;', $indentationLevel);

                                        if ($nomenclatura['tipo'] === 'R'):
                                            if ($currentGroup !== null) {
                                                echo '</optgroup>';
                                            }
                                            $currentGroup = $nomenclatura['cdescrip'];
                                            echo '<optgroup label="' . $indentation . $currentGroup . '">';
                                        else:
                                            $selected = ($nomenclatura['id'] == ($bovedaSeleccionada[0]['id_nomenclatura'] ?? '')) ? 'selected' : '';
                                            echo '<option ' . $selected . ' value="' . $nomenclatura['id'] . '">' . $indentation . $nomenclatura['ccodcta'] . ' - ' . $nomenclatura['cdescrip'] . '</option>';
                                        endif;
                                    endforeach;
                                    if ($currentGroup !== null) {
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <?php echo $csrf->getTokenField(); ?>
                            <div class="col-12 text-end">
                                <?php if ($status && isset($bovedaSeleccionada)): ?>
                                    <button type="button" class="btn btn-success" onclick="obtiene(['<?= $csrf->getTokenName() ?>','nomboveda'], [ 'agencia', 'nomenclatura'], [], 'update_boveda', '0', ['<?= htmlspecialchars($secureID->encrypt($extra)) ?>'], 'NULL', '¿Confirma actualizar la bóveda?');">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>
                                        Actualizar Bóveda
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="printdiv2('#cuadro','0');">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>
                                        Cancelar
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary" onclick="obtiene(['<?= $csrf->getTokenName() ?>','nomboveda'], [ 'agencia', 'nomenclatura'], [], 'create_boveda', '0', [], 'NULL', '¿Confirma crear la bóveda?');">
                                        <i class="fa-solid fa-floppy-disk me-1"></i>
                                        Crear Bóveda
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabla de Bóvedas Registradas -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white fw-bold">
                        Bóvedas Registradas
                    </div>
                    <div class="card-body">
                        <?php if (empty($bovedasRegistradas)): ?>
                            <div class="alert alert-info">No hay bóvedas registradas.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre</th>
                                            <th>Agencia</th>
                                            <th>Nomenclatura</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bovedasRegistradas as $idx => $boveda): ?>
                                            <tr>
                                                <td><?= $idx + 1 ?></td>
                                                <td><?= htmlspecialchars($boveda['nombre']) ?></td>
                                                <td>
                                                    <?php
                                                    $agencia = array_filter($agencias, fn($a) => $a['id_agencia'] == $boveda['id_agencia']);
                                                    $agencia = reset($agencia);
                                                    echo $agencia ? "{$agencia['cod_agenc']} – {$agencia['nom_agencia']}" : 'N/A';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $nomen = array_filter($nomenclaturaCatalogo, fn($n) => $n['id'] == $boveda['id_nomenclatura']);
                                                    $nomen = reset($nomen);
                                                    echo $nomen ? "{$nomen['ccodcta']} - {$nomen['cdescrip']}" : 'N/A';
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-warning me-1" onclick="printdiv2('#cuadro', '<?= $boveda['id'] ?>')">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="obtiene(['<?= $csrf->getTokenName() ?>'], [], [], 'delete_boveda', '0', ['<?= htmlspecialchars($secureID->encrypt($boveda['id'])) ?>'], 'NULL', '¿Confirma eliminar la bóveda?');">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
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
                inicializarValidacionAutomaticaGeneric('#formBoveda');
            });
        </script>
        <?php
        break;
    /* =========  APERTURA DE BÓVEDA – SALDO GENERAL  ========= */
    case 'Apertura_Bodeda': {

            $showmensaje = false;
            $idBoveda = $_POST['xtra'] ?? '0';
            $idMoneda = 1; // Quetzal

            try {
                $database->openConnection();
                $bovedas = $database->getAllResults(
                    "SELECT bov.id,ag.nom_agencia,bov.nombre,ctb.ccodcta,ctb.cdescrip
                        FROM bov_bovedas bov
                        INNER JOIN tb_agencia ag ON ag.id_agencia=bov.id_agencia
                        INNER JOIN ctb_nomenclatura ctb ON ctb.id=bov.id_nomenclatura
                        WHERE bov.estado='1'"
                );
                if (empty($bovedas)) {
                    $showmensaje = true;
                    throw new Exception("No hay bóvedas creadas. Por favor registre las bóvedas primero.");
                }

                if ($idBoveda != "0") {
                    $bovedaSeleccionada = $database->selectColumns("bov_bovedas", ["id", "nombre", "id_agencia", "id_nomenclatura"], "id=? AND estado='1'", [$idBoveda]);
                    if (empty($bovedaSeleccionada)) {
                        $showmensaje = true;
                        throw new Exception("No se encontró la bóveda seleccionada.");
                    }


                    $cantidades = $database->getAllResults(
                        "SELECT d.id_moneda, d.id AS id_denominacion, d.monto AS denominacion,
                            CASE d.tipo
                                WHEN 1 THEN 'Billete'
                                WHEN 2 THEN 'Moneda'
                                ELSE 'Desconocido'
                            END AS tipo,
                            COALESCE(SUM(
                                CASE
                                    WHEN m.tipo = 'entrada' OR m.tipo = 'inicial' THEN bd.cantidad
                                    WHEN m.tipo = 'salida' THEN -bd.cantidad
                                    ELSE 0
                                END
                            ), 0) AS total_existente
                        FROM tb_denominaciones AS d
                        LEFT JOIN bov_detalles AS bd ON bd.id_denominacion = d.id
                        LEFT JOIN bov_movimientos AS m
                                ON m.id = bd.id_movimiento
                                AND m.id_boveda = ? AND m.estado='1'
                        WHERE d.id_moneda=?
                        GROUP BY d.id, d.monto, d.tipo
                        ORDER BY d.tipo ASC, d.monto DESC;",
                        [$idBoveda, $idMoneda]
                    );

                    $denominacionesCatalogo = $database->selectColumns("tb_denominaciones", ["id", "monto", "tipo"], "id_moneda=?", [$idMoneda], orderBy: "tipo, monto DESC");
                    $saldoBoveda = array_reduce($cantidades, fn($sum, $item) => $sum + ($item['denominacion'] * $item['total_existente']), 0);
                    $cantidadBilletes = array_reduce($cantidades, fn($sum, $item) => $sum + ($item['tipo'] === 'Billete' ? $item['total_existente'] : 0), 0);
                    $cantidadMonedas = array_reduce($cantidades, fn($sum, $item) => $sum + ($item['tipo'] === 'Moneda' ? $item['total_existente'] : 0), 0);

                    $cuentasBancos = $database->getAllResults(
                        "SELECT ctb.id,ban.nombre, ctb.numcuenta FROM tb_bancos ban
                            INNER JOIN ctb_bancos ctb ON ctb.id_banco=ban.id
                            WHERE ctb.estado=1 AND ban.estado=1"
                    );

                    $movCant = $database->getAllResults(
                        "SELECT COUNT(*) AS total_movimientos FROM bov_movimientos WHERE id_boveda=? AND estado='1'",
                        [$idBoveda]
                    );
                }

                $status = true;
            } catch (Exception $e) {
                if (!$showmensaje) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                }
                $mensaje = $showmensaje ? "Error: " . $e->getMessage() : "Error interno, reporte ($codigoError)";
                $status  = false;
            } finally {
                $database->closeConnection();
            }

        ?>
            <input type="hidden" id="condi" value="Apertura_Bodeda">
            <input type="hidden" id="file" value="Bobeda001">
            <input type="number" id="tipMoneda" value="<?= $idMoneda ?>" hidden>
            <div class="container-fluid py-3">
                <?php if (!$status): ?>
                    <div class="alert alert-warning"><?= $mensaje ?></div>
                <?php endif; ?>

                <!-- Selección de bóveda -->
                <div class="mb-4">
                    <h5 class="fw-bold mb-3 text-body">Seleccionar una bóveda</h5>
                    <div class="row g-4">
                        <?php foreach ($bovedas as $idx => $boveda):
                            $isSelected = isset($bovedaSeleccionada) && $boveda['id'] == $bovedaSeleccionada[0]['id'];
                            $cardClasses = 'card h-100 shadow-sm border bg-body';
                            $cardClasses .= $isSelected ? ' border-primary border-2' : ' border';
                        ?>
                            <div class="col-md-4">
                                <div role="button" tabindex="0"
                                    class="<?= $cardClasses ?>"
                                    onclick="printdiv2('#cuadro','<?= $boveda['id'] ?>')"
                                    onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); printdiv2('#cuadro','<?= $boveda['id'] ?>'); }">
                                    <div class="card-body text-center">
                                        <h6 class="card-title mb-2 text-truncate fw-semibold text-body"><?= htmlspecialchars($boveda['nombre']) ?></h6>

                                        <div class="mb-1 text-muted small">
                                            <span class="fw-semibold text-body-secondary">Agencia:</span>
                                            <span class="text-body"><?= htmlspecialchars($boveda['nom_agencia']) ?></span>
                                        </div>

                                        <div class="text-muted small">
                                            <span class="fw-semibold text-body-secondary">Nomenclatura:</span>
                                            <span class="text-body"><?= htmlspecialchars($boveda['ccodcta'] . ' - ' . $boveda['cdescrip']) ?></span>
                                        </div>

                                        <?php if ($isSelected): ?>
                                            <div class="mt-3">
                                                <span class="badge bg-primary rounded-pill px-3 py-2">Seleccionada</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Estado actual de la bóveda -->
                <div class="text-center fw-bold my-4 fs-4 text-success">MOVIMIENTOS EN BÓVEDAS</div>
                <div class="card mb-4 shadow-sm border-0">
                    <?php if (isset($bovedaSeleccionada) && !empty($bovedaSeleccionada)): ?>
                        <div class="card-header bg-gradient bg-info text-white text-center">
                            <div class="fs-5 mb-2">Estado Actual de la Bóveda</div>
                            <div class="mt-2">
                                <strong>Nombre:</strong> <?= htmlspecialchars($bovedaSeleccionada[0]['nombre']) ?><br>
                                <strong>Agencia:</strong>
                                <?php
                                $agenciaSel = array_filter($bovedas, fn($b) => $b['id'] == $bovedaSeleccionada[0]['id']);
                                $agenciaSel = reset($agenciaSel);
                                echo $agenciaSel ? $agenciaSel['nom_agencia'] : 'N/A';
                                ?><br>
                                <strong>Nomenclatura:</strong>
                                <?php
                                $nomenSel = array_filter($bovedas, fn($b) => $b['id'] == $bovedaSeleccionada[0]['id']);
                                $nomenSel = reset($nomenSel);
                                echo $nomenSel ? "{$nomenSel['ccodcta']} - {$nomenSel['cdescrip']}" : 'N/A';
                                ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-header bg-gradient bg-info text-white text-center fs-5">
                            Estado Actual de la Bóveda
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="row justify-content-center mb-3">
                            <div class="col-auto">
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <h4 class="mb-0">Saldo en Bóveda: <span id="saldoBoveda" class="text-success">GTQ <?= number_format($saldoBoveda ?? 0, 2) ?></span></h4>
                                    <div class="d-flex gap-3">
                                        <span class="badge bg-primary">Billetes: <span id="cantidadBilletes"><?= $cantidadBilletes ?? 0 ?></span></span>
                                        <span class="badge bg-secondary">Monedas: <span id="cantidadMonedas"><?= $cantidadMonedas ?? 0 ?></span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="alert alert-success shadow-sm py-4 px-3" role="alert">
                            <?php echo $csrf->getTokenField(); ?>
                            <h5 class="alert-heading text-center mb-4">
                                <i class="fa-solid fa-money-bill-wave me-2"></i>
                                Detalle de Denominaciones Existentes en la Bóveda
                            </h5>
                            <div class="row g-4">
                                <!-- Billetes -->
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-gradient bg-primary text-white text-center fw-bold">
                                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Billetes
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <?php
                                            if (isset($cantidades)) {
                                                $hasBilletes = false;
                                                foreach ($cantidades as $den) {
                                                    if ($den['tipo'] === 'Billete') {
                                                        $hasBilletes = true;
                                                        echo "<li class='list-group-item d-flex justify-content-between align-items-center px-3'>
                                                                <span>Q " . number_format($den['denominacion'], 2) . "</span>
                                                                <span class='badge bg-primary rounded-pill'>{$den['total_existente']}</span>
                                                              </li>";
                                                    }
                                                }
                                                if (!$hasBilletes) {
                                                    echo "<li class='list-group-item text-muted text-center'>No hay billetes registrados.</li>";
                                                }
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                </div>
                                <!-- Monedas -->
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-gradient bg-secondary text-white text-center fw-bold">
                                            <i class="fa-solid fa-coins me-1"></i> Monedas
                                        </div>
                                        <ul class="list-group list-group-flush">
                                            <?php
                                            if (isset($cantidades)) {
                                                $hasMonedas = false;
                                                foreach ($cantidades as $den) {
                                                    if ($den['tipo'] === 'Moneda') {
                                                        $hasMonedas = true;
                                                        echo "<li class='list-group-item d-flex justify-content-between align-items-center px-3'>
                                                                <span>Q " . number_format($den['denominacion'], 2) . "</span>
                                                                <span class='badge bg-secondary rounded-pill'>{$den['total_existente']}</span>
                                                              </li>";
                                                    }
                                                }
                                                if (!$hasMonedas) {
                                                    echo "<li class='list-group-item text-muted text-center'>No hay monedas registradas.</li>";
                                                }
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($bovedaSeleccionada) && !empty($bovedaSeleccionada)): ?>
                            <div class="text-center my-3">
                                <button type="button" class="btn btn-success btn-lg px-4" onclick="$('#formularioMovimiento').show(); $('#sectionGeneralData').focus();">
                                    <i class="fa-solid fa-plus"></i> Agregar Movimiento
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Formulario de movimiento -->
                <div class="card mb-4 shadow-sm border-0" id="formularioMovimiento" style="display: none;">
                    <div class="card-body"
                        x-data="{ tipo: 'entrada', conceptoData:  'Ingreso', metodo:'efectivo',requireBanco: false,
                        title_banco_numdoc: 'Número de boleta', title_banco_fecha: 'Fecha boleta' }"
                        x-effect="
                        conceptoData=(tipo==='entrada') ? 'Ingreso' : (tipo==='inicial') ? 'Saldo inicial' : 'Egreso';
                        requireBanco=(metodo==='banco') ? true : false;
                        title_banco_numdoc=(tipo==='salida') ? 'Número de boleta' : 'Número de cheque';
                        title_banco_fecha=(tipo==='salida') ? 'Fecha de boleta' : 'Fecha de cheque';
                        ">
                        <?php if (isset($movCant) && $movCant[0]['total_movimientos'] == 0): ?>
                            <div class="alert alert-info">
                                <h6 class="mb-1 fw-bold">Bóveda sin movimientos</h6>
                                <p class="mb-0">
                                    Esta bóveda aún no tiene movimientos. Puede registrar un <strong>saldo inicial</strong> para reflejar el efectivo físico, pero tenga en cuenta lo siguiente:
                                <ul class="mb-0">
                                    <li><strong>El saldo inicial no genera asiento contable.</strong></li>
                                    <li>Una vez que registre cualquier movimiento de tipo <em>Ingreso</em> o <em>Egreso</em>, <strong>ya no podrá ingresar un saldo inicial</strong> para esta bóveda.</li>
                                    <li>Los movimientos de <em>Ingreso</em> y <em>Egreso</em> sí <strong>afectan la contabilidad</strong> y deben registrarse con su documento de soporte.</li>
                                </ul>
                                </p>
                            </div>
                        <?php endif; ?>
                        <div id="sectionGeneralData">
                            <div class="row mb-3">
                                <div class="col-12 col-md-4">
                                    <label for="fec_apertura" class="form-label fw-semibold">Fecha</label>
                                    <input type="date" id="fec_apertura" class="form-control" value="<?= date('Y-m-d') ?>" required data-label="Fecha" max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="tipo_movimiento" class="form-label fw-semibold">Tipo de Movimiento</label>
                                    <select id="tipo_movimiento" class="form-select" required data-label="Tipo de Movimiento" x-model="tipo">
                                        <?php if ($movCant[0]['total_movimientos'] == 0): ?>
                                            <option value="inicial" selected>Saldo inicial</option>
                                        <?php endif; ?>
                                        <option value="entrada">Ingreso</option>
                                        <option value="salida">Egreso</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="num_documento" class="form-label fw-semibold">Número de Documento</label>
                                    <input type="text" id="num_documento" class="form-control" placeholder="Ingrese número de documento" required data-label="Número de Documento">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12 col-md-4">
                                    <label for="forma_pago" class="form-label fw-semibold">Forma</label>
                                    <select id="forma_pago" class="form-select" required data-label="Forma de Pago" x-model="metodo"
                                        onchange="if(this.value == 'banco') { showHideElement(['divCuentaBanco'], 'show',3); } else { showHideElement(['divCuentaBanco'], 'hide',3); }">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="banco">Banco</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label for="concepto" class="form-label fw-semibold">Concepto</label>
                                    <textarea id="concepto" class="form-control" rows="1" required data-label="Concepto" x-model="conceptoData"></textarea>
                                </div>
                            </div>
                            <div class="row mb-3 d-none" id="divCuentaBanco">
                                <div class="col-12 col-md-4">
                                    <label for="cuentabanco" class="form-label fw-semibold">Cuenta Banco</label>
                                    <select id="cuentabanco" class="form-select" data-label="Cuenta Banco" x-bind:required="requireBanco">
                                        <option value="" disabled selected>Seleccione una cuenta</option>
                                        <?php
                                        if (isset($cuentasBancos)) {
                                            foreach ($cuentasBancos as $cuenta) {
                                                echo "<option value='{$cuenta['id']}'>{$cuenta['nombre']} - {$cuenta['numcuenta']}</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="banco_numdoc" class="form-label fw-semibold" x-text="title_banco_numdoc"></label>
                                    <input type="text" id="banco_numdoc" class="form-control" :placeholder="title_banco_numdoc" :required="(metodo==='banco')" :data-label="title_banco_numdoc">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="banco_fecha" class="form-label fw-semibold" x-text="title_banco_fecha"></label>
                                    <input type="date" id="banco_fecha" class="form-control" value="<?= date('Y-m-d') ?>" :required="(metodo==='banco')" :data-label="title_banco_fecha" max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-12" x-show="(metodo==='banco' && tipo==='entrada')" x-cloak>
                                    <div class="mt-2">
                                        <label class="form-label">Beneficiario del cheque</label>
                                        <input type="text" class="form-control" id="banco_beneficiario_cheque"
                                            placeholder="A nombre de quién va el cheque"
                                            data-label="Beneficiario del cheque"
                                            inputmode="text" pattern="[A-Za-z0-9 ]*" :required="(metodo==='banco' && tipo==='entrada')">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3" x-show="(metodo==='banco' && tipo==='entrada')" x-cloak>
                                    <div class="mt-3">
                                        <label class="form-label">Negociable</label>
                                        <select class="form-select" id="banco_negociable">
                                            <option value="1">Negociable</option>
                                            <option value="0" selected>No negociable</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="mt-4 text-center">
                            <button id="btnGuardarTodo" class="btn btn-primary btn-lg px-4" onclick="guardarMovimientoBoveda()">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                Guardar Movimiento + detalle
                            </button>
                        </div>

                        <!-- Detalle de denominaciones -->
                        <div id="detalleBoveda" class="mt-4">
                            <div class="card border-success shadow-sm">
                                <div class="card-header bg-success text-white text-center">
                                    <span class="fs-5">Detalle de denominaciones</span>
                                </div>
                                <div class="card-body">
                                    <div id="formularioDesglose">
                                        <div class="row mb-3">
                                            <div class="col text-center">
                                                <h5 class="text-success fw-bold">Denominaciones posibles</h5>
                                                <p class="text-muted">Ingrese la cantidad para cada denominación.</p>
                                            </div>
                                        </div>
                                        <div class="text-center mb-3">
                                            <h5>Total: <span id="totalGeneral" class="text-success">GTQ 0.00</span></h5>
                                            <input type="number" step="0.01" id="montoTotal" value="0" hidden>
                                        </div>
                                        <!-- Billetes -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="p-2 mb-3 bg-success text-white text-center rounded">
                                                    <b>Billetes</b>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                                            <?php
                                            if (isset($denominacionesCatalogo)) {
                                                foreach ($denominacionesCatalogo as $den) {
                                                    if ($den['tipo'] == 1) { // Billete
                                                        $denId = 'deno_' . $den['id'];
                                            ?>
                                                        <div class="col">
                                                            <div class="card border-0 shadow-sm h-100">
                                                                <div class="card-body">
                                                                    <div class="mb-2 text-center fw-semibold text-success">
                                                                        GTQ <?= number_format($den['monto'], 2) ?>
                                                                    </div>
                                                                    <label for="<?= $denId ?>" class="form-label">Cantidad</label>
                                                                    <input type="number" id="<?= $denId ?>"
                                                                        class="form-control den-input mb-2"
                                                                        data-den="<?= $den['monto'] ?>" min="0" value="0" oninput="updateSubtotal('<?= $denId ?>', 'GTQ')">
                                                                    <div class="text-end">
                                                                        Subtotal: <span id="s<?= $denId ?>" class="fw-bold text-success">GTQ 0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                            <?php
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>
                                        <!-- Monedas -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="p-2 mb-3 bg-secondary text-white text-center rounded">
                                                    <b>Monedas</b>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                                            <?php
                                            if (isset($denominacionesCatalogo)) {
                                                foreach ($denominacionesCatalogo as $den) {
                                                    if ($den['tipo'] == 2) {
                                                        $denId = 'deno_' . $den['id'];
                                            ?>
                                                        <div class="col">
                                                            <div class="card border-0 shadow-sm h-100">
                                                                <div class="card-body">
                                                                    <div class="mb-2 text-center fw-semibold text-secondary">
                                                                        GTQ <?= number_format($den['monto'], 2) ?>
                                                                    </div>
                                                                    <label for="<?= $denId ?>" class="form-label">Cantidad</label>
                                                                    <input type="number" id="<?= $denId ?>"
                                                                        class="form-control den-input mb-2"
                                                                        data-den="<?= $den['monto'] ?>" min="0" value="0" oninput="updateSubtotal('<?= $denId ?>', 'GTQ')">
                                                                    <div class="text-end">
                                                                        Subtotal: <span id="s<?= $denId ?>" class="fw-bold text-secondary">GTQ 0.00</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                            <?php
                                                    }
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <script>
                $('#sectionGeneralData').attr('tabindex', -1);
                $(document).ready(function() {
                    inicializarValidacionAutomaticaGeneric('#formularioMovimiento');
                });

                function guardarMovimientoBoveda() {
                    const detalle = {};
                    document.querySelectorAll('.den-input').forEach(i => detalle[i.id] = i.value);
                    obtiene(['<?= $csrf->getTokenName() ?>', 'fec_apertura', 'num_documento', 'concepto', 'montoTotal', 'banco_numdoc', 'banco_fecha', 'banco_beneficiario_cheque'], ['tipo_movimiento', 'forma_pago', 'cuentabanco', 'banco_negociable'], [], 'create_bov_movimiento', '<?= $idBoveda ?>', ['<?= htmlspecialchars($secureID->encrypt($idBoveda)) ?>', detalle], function(data) {
                        reportes([
                            [],
                            [],
                            [],
                            [data.id_movimiento]
                        ], 'pdf', `boveda_comprobante_movimiento_main`, 0);
                    }, '¿Confirma guardar el movimiento?');

                }
            </script>

        <?php
        }
        break;

    case 'reporteBovedaMov': {

            $showmensaje = false;

            $isRequestReport = is_array($_POST['xtra']);

            try {
                $database->openConnection();
                $bovedas = $database->getAllResults(
                    "SELECT bov.id,ag.nom_agencia,bov.nombre
                        FROM bov_bovedas bov
                        INNER JOIN tb_agencia ag ON ag.id_agencia=bov.id_agencia
                        WHERE bov.estado='1'"
                );
                if (empty($bovedas)) {
                    $showmensaje = true;
                    throw new Exception("No hay bóvedas creadas. Por favor registre las bóvedas primero.");
                }

                if ($isRequestReport) {
                    $idBoveda    = $_POST['xtra'][0];
                    $fechaInicio = $_POST['xtra'][1];
                    $fechaFin    = $_POST['xtra'][2];

                    $movimientos = $database->getAllResults(
                        "SELECT bov.nombre,mov.tipo,mov.monto,mov.fecha,mov.concepto,mov.numdoc AS referencia,mov.id
                                FROM bov_bovedas bov
                                INNER JOIN bov_movimientos mov ON mov.id_boveda=bov.id
                                WHERE mov.fecha BETWEEN ? AND ? AND bov.estado='1' AND mov.estado='1' AND mov.id_boveda=? 
                                ORDER BY mov.fecha, mov.id;",
                        [$fechaInicio, $fechaFin, $idBoveda]
                    );

                    $saldoAnterior = $database->getAllResults(
                        "SELECT COALESCE(SUM(
                                CASE 
                                    WHEN m.tipo = 'entrada' OR m.tipo = 'inicial' THEN m.monto
                                    WHEN m.tipo = 'salida' THEN -m.monto
                                    ELSE 0
                                END
                            ), 0) AS saldo
                        FROM bov_movimientos m WHERE m.estado='1' AND m.fecha<? AND m.id_boveda=?;",
                        [$fechaInicio, $idBoveda]
                    );

                    $montoSaldoAnterior = $saldoAnterior[0]['saldo'] ?? 0;
                }

                $status = true;
            } catch (Exception $e) {
                if (!$showmensaje) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                }
                $mensaje = $showmensaje ? "Error: " . $e->getMessage() : "Error interno, reporte ($codigoError)";
                $status  = false;
            } finally {
                $database->closeConnection();
            }

        ?>
            <input type="text" value="reporteBovedaMov" id="condi" style="display:none;">
            <input type="text" value="Bobeda001" id="file" style="display:none;">

            <div class="text-center fw-bold my-3">
                ESTADO DE CUENTA – MOVIMIENTOS DE BÓVEDA
            </div>

            <div class="card">
                <div class="card-body">

                    <?php if (!$status): ?>
                        <div class="alert alert-warning"><?= $mensaje ?></div>
                    <?php endif; ?>

                    <div class="container contenedort">
                        <div class="row m-2 g-3">
                            <!-- agencia -->
                            <div class="col-lg-6 col-md-6">
                                <div class="card h-100">
                                    <div class="card-header"><b>Bóveda</b></div>
                                    <div class="card-body">
                                        <select class="form-select" id="boveda">
                                            <?php foreach ($bovedas as $b):
                                                $selected = ($isRequestReport && $b['id'] == $idBoveda) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $b['id']; ?>" <?= $selected; ?>>
                                                    <?= "{$b['nombre']} – {$b['nom_agencia']}"; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- rango de fechas -->
                            <div class="col-lg-6 col-md-6">
                                <div class="card h-100">
                                    <div class="card-header"><b>Rango de Fechas</b></div>
                                    <div class="card-body">
                                        <label class="form-label" for="fechaInicio">Inicio:</label>
                                        <input type="date" class="form-control mb-2" id="fechaInicio" value="<?= ($isRequestReport) ? $fechaInicio : $hoy; ?>">
                                        <label class="form-label" for="fechaFin">Fin:</label>
                                        <input type="date" class="form-control" id="fechaFin" value="<?= ($isRequestReport) ? $fechaFin : $hoy; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-content-center my-3" id="modal_footer">
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-info"
                                onclick="printdiv2('#cuadro',[$('#boveda').val(), $('#fechaInicio').val(), $('#fechaFin').val() ])">
                                <i class="fa-solid fa-file-excel"></i> Ver en pantalla
                            </button>
                            <button type="button" class="btn btn-outline-success"
                                onclick="reportes([[`fechaInicio`,`fechaFin`],[`boveda`],[],[]],
                              'xlsx','reporteBovedaMov',1)">
                                <i class="fa-solid fa-file-excel"></i> Excel
                            </button>
                            <button type="button" class="btn btn-outline-danger"
                                onclick="reportes([[`fechaInicio`,`fechaFin`],[`boveda`],[],[]],
                              'pdf','reporteBovedaMov',0)">
                                <i class="fa-regular fa-file-pdf"></i> PDF
                            </button>
                            <button type="button" class="btn btn-outline-danger"
                                onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                        </div>
                    </div>

                    <?php
                    if ($isRequestReport && $status):
                        if (isset($movimientos) && !empty($movimientos)): ?>
                            <div class="mt-3">
                                <div class="list-group">
                                    <?php
                                    $totalMov = 0;
                                    foreach ($movimientos as $idx => $mov):
                                        $monto = floatval($mov['monto'] ?? 0);
                                        $montoSigned = ($mov['tipo'] === 'salida') ? -$monto : $monto;
                                        $totalMov += $montoSigned;
                                        $movId = $mov['id'] ?? null;

                                        // Estilos según tipo
                                        $typeLabel = htmlspecialchars(ucfirst($mov['tipo'] ?? ''));
                                        $typeBadgeClass = ($mov['tipo'] === 'salida') ? 'bg-danger' : (($mov['tipo'] === 'inicial') ? 'bg-info' : 'bg-success');
                                        $amountClass = ($montoSigned < 0) ? 'text-danger' : 'text-success';
                                    ?>
                                        <div class="list-group-item list-group-item-action py-3">
                                            <div class="d-flex w-100 justify-content-between align-items-start">
                                                <div class="me-3" style="min-width:0;">
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="badge <?= $typeBadgeClass ?> text-white"><?= $typeLabel ?></span>
                                                        <h6 class="mb-0 text-truncate" style="max-width:40ch;"><?= htmlspecialchars($mov['concepto'] ?? '') ?></h6>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <span><?= htmlspecialchars(isset($mov['fecha']) ? Date::toDMY($mov['fecha']) : '') ?></span>
                                                        <?php if (!empty($mov['referencia'])): ?>
                                                            &nbsp;•&nbsp;<span>Ref: <?= htmlspecialchars($mov['referencia']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="text-end ms-3">
                                                    <div class="fw-bold <?= $amountClass ?> fs-5">
                                                        <?= ($montoSigned < 0 ? '-' : '') ?>Q <?= number_format(abs($montoSigned), 2) ?>
                                                    </div>
                                                    <div class="mt-2">
                                                        <?php if ($movId !== null): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="reportes([[],[],[],[<?= json_encode($movId) ?>]], 'pdf', 'boveda_comprobante_movimiento_main', 0)">
                                                                <i class="fa-regular fa-file-pdf"></i> Imprimir
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="ID de movimiento no disponible">
                                                                <i class="fa-regular fa-file-pdf"></i> Imprimir
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-semibold">Total de movimientos</span>
                                            <div class="small text-muted">Sumatoria del periodo</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-4 fw-bold <?= ($totalMov < 0) ? 'text-danger' : 'text-success' ?>">
                                                Q <?= number_format($totalMov, 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-3">No se encontraron movimientos en el rango seleccionado.</div>
                    <?php
                        endif;
                    endif;
                    ?>

                </div>
            </div>
<?php
        }
        break;
} ?>