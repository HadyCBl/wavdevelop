<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

use App\DatabaseAdapter;
use Micro\Exceptions\SoftException;
use Micro\Helpers\Log;

$database = new DatabaseAdapter();

$condi = $_POST["condi"];

switch ($condi) {
    case 'productos':
        $idPerfil = $_POST["xtra"];
        $status = false;
        $db_name_general = $_ENV['DDBB_NAME_GENERAL'];
        try {
            if (empty($idPerfil) || $idPerfil <= 0) {
                throw new SoftException("Seleccione un cliente");
            }
            $database->openConnection();

            $datosCliente = $database->selectColumns(
                "tb_cliente",
                ["idcod_cliente", "short_name"],
                "idcod_cliente=?",
                [$idPerfil]
            );
            if (empty($datosCliente)) {
                throw new SoftException("Cliente no encontrado");
            }

            $cuentasAhorros = $database->getAllResults(
                "SELECT 'Ahorro' AS tipo, aht.nombre AS descripcion, aho.ccodaho AS cuenta, 
                        calcularsaldocuentaahom(aho.ccodaho) AS saldo2, aho.estado AS estado,
                        CASE 
                            WHEN aho.estado = 'B' THEN 'Inactivo'
                            WHEN aho.estado = 'A' THEN 'Vigente'
                            WHEN aho.estado = 'X' THEN 'Eliminado'
                            ELSE 'Desconocido' 
                        END AS estado_descripcion, IFNULL(calcular_saldo_aho_tipcuenta(aho.ccodaho,?),0)  AS saldo
                    FROM ahomcta aho
                    INNER JOIN tb_cliente cl ON aho.ccodcli = cl.idcod_cliente
                    INNER JOIN ahomtip aht ON aht.ccodtip = SUBSTR(aho.ccodaho, 7, 2)
                    WHERE aho.estado IN ('A', 'B', 'X') AND cl.idcod_cliente = ?",
                [date("Y-m-d"), $idPerfil]
            );

            $cuentasAportaciones = $database->getAllResults(
                "SELECT 'Aportación' AS tipo, apt.nombre AS descripcion, apr.ccodaport AS cuenta, 
                        calcular_saldo_apr_tipcuenta(apr.ccodaport,?) AS saldo, apr.estado AS estado,
                        CASE 
                            WHEN apr.estado = 'B' THEN 'Inactivo'
                            WHEN apr.estado = 'A' THEN 'Vigente'
                            WHEN apr.estado = 'X' THEN 'Eliminado'
                            ELSE 'Desconocido' 
                        END AS estado_descripcion 
                    FROM aprcta apr 
                    INNER JOIN tb_cliente cl ON apr.ccodcli = cl.idcod_cliente 
                    INNER JOIN aprtip apt ON apt.ccodtip = apr.ccodtip
                    WHERE apr.estado IN ('A', 'B', 'X') AND cl.idcod_cliente = ?",
                [date("Y-m-d"), $idPerfil]
            );

            $cuentasCreditos = $database->getAllResults(
                "SELECT 'Crédito' AS tipo, pr.descripcion AS descripcion, cm.CCODCTA AS cuenta, 
                        cm.NCapDes AS saldo, cm.Cestado AS estado,  
                        COALESCE(ge.EstadoCredito, '-?') AS estado_descripcion
                    FROM cremcre_meta cm 
                    LEFT JOIN $db_name_general.tb_estadocredito ge ON ge.id_EstadoCredito = cm.Cestado
                    INNER JOIN cre_productos pr ON cm.CCODPRD = pr.id  
                    AND cm.CodCli = ?",
                [$idPerfil]
            );

            $status = true;
        } catch (SoftException $e) {
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
?>

        <div class="text" style="text-align: center">PRODUCTOS</div>
        <input type="text" id="condi" value="productos" hidden>
        <input type="text" id="file" value="patr_garant" hidden>
        <div class="container">
            <div class="card crdbody">
                <div class="card-header panelcolor">HISTORIAL DE PRODUCTOS</div>
                <div class="card-body">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <strong>Atención:</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-outline-primary" title="Buscar Cliente" data-bs-toggle="modal"
                                data-bs-target="#buscar_cli_gen">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Cliente
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($datosCliente)) { ?>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body bg-body py-3">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="avatar-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 50%;">
                                                    <i class="fa-solid fa-user fa-lg"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <small class="text-muted d-block mb-1">Código de Cliente</small>
                                                        <h6 class="mb-0">
                                                            <span class="badge bg-primary bg-opacity-75" id="ccodcli"><?= $datosCliente[0]["idcod_cliente"] ?></span>
                                                        </h6>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <small class="text-muted d-block mb-1">Nombre del Cliente</small>
                                                        <h6 class="mb-0 fw-bold" id="nom"><?= htmlspecialchars($datosCliente[0]["short_name"]) ?></h6>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="row mt-4">
                        <!-- Sección de Ahorros -->
                        <div class="col-md-12 mb-4">
                            <div class="card shadow-sm border border-success">
                                <div class="card-header bg-success bg-opacity-10 border-bottom border-success">
                                    <h5 class="mb-0 text-success">
                                        <i class="fa-solid fa-piggy-bank me-2"></i>Cuentas de Ahorro
                                    </h5>
                                </div>
                                <div class="card-body bg-body">
                                    <?php if ($status && !empty($cuentasAhorros)) { ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead>
                                                    <tr class="border-bottom">
                                                        <th>#</th>
                                                        <th class="text-body-secondary">Descripción</th>
                                                        <th class="text-body-secondary">Cuenta</th>
                                                        <th class="text-end text-body-secondary">Saldo</th>
                                                        <th class="text-body-secondary">Estado</th>
                                                        <!-- <th class="text-body-secondary">Descripción Estado</th> -->
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cuentasAhorros as $index => $producto) { ?>
                                                        <tr>
                                                            <td><strong><?= $index + 1 ?></strong></td>
                                                            <td><strong><?= htmlspecialchars($producto['descripcion']) ?></strong></td>
                                                            <td><span class="badge bg-secondary bg-opacity-75"><?= htmlspecialchars($producto['cuenta']) ?></span></td>
                                                            <td class="text-end fw-bold text-success"><?= number_format($producto['saldo'], 2) ?></td>
                                                            <td>
                                                                <?php if ($producto['estado'] == 'A') { ?>
                                                                    <span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i><?= htmlspecialchars($producto['estado_descripcion']) ?></span>
                                                                <?php } elseif ($producto['estado'] == 'B') { ?>
                                                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-pause-circle me-1"></i><?= htmlspecialchars($producto['estado_descripcion']) ?></span>
                                                                <?php } else { ?>
                                                                    <span class="badge bg-danger"><i class="fa-solid fa-times-circle me-1"></i><?= htmlspecialchars($producto['estado_descripcion']) ?></span>
                                                                <?php } ?>
                                                            </td>
                                                            <!-- <td class="text-body-secondary"><?= htmlspecialchars($producto['estado_descripcion']) ?></td> -->
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } else { ?>
                                        <div class="alert alert-info border-info bg-info bg-opacity-10 mb-0">
                                            <i class="fa-solid fa-info-circle me-2"></i>No hay cuentas de ahorro registradas
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Aportaciones -->
                        <div class="col-md-12 mb-4">
                            <div class="card shadow-sm border border-primary">
                                <div class="card-header bg-primary bg-opacity-10 border-bottom border-primary">
                                    <h5 class="mb-0 text-primary">
                                        <i class="fa-solid fa-hand-holding-dollar me-2"></i>Aportaciones
                                    </h5>
                                </div>
                                <div class="card-body bg-body">
                                    <?php if ($status && !empty($cuentasAportaciones)) { ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead>
                                                    <tr class="border-bottom">
                                                        <th>#</th>
                                                        <th class="text-body-secondary">Descripción</th>
                                                        <th class="text-body-secondary">Cuenta</th>
                                                        <th class="text-end text-body-secondary">Saldo</th>
                                                        <th class="text-body-secondary">Estado</th>
                                                        <!-- <th class="text-body-secondary">Descripción Estado</th> -->
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cuentasAportaciones as $index => $producto) { ?>
                                                        <tr>
                                                            <td><strong><?= $index + 1 ?></strong></td>
                                                            <td><strong><?= htmlspecialchars($producto['descripcion']) ?></strong></td>
                                                            <td><span class="badge bg-secondary bg-opacity-75"><?= htmlspecialchars($producto['cuenta']) ?></span></td>
                                                            <td class="text-end fw-bold text-primary"><?= number_format($producto['saldo'], 2) ?></td>
                                                            <td>
                                                                <?php if ($producto['estado'] == 'A') { ?>
                                                                    <span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i><?= htmlspecialchars($producto['estado_descripcion']) ?></span>
                                                                <?php } elseif ($producto['estado'] == 'B') { ?>
                                                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-pause-circle me-1"></i><?= htmlspecialchars($producto['estado_descripcion']) ?></span>
                                                                <?php } else { ?>
                                                                    <span class="badge bg-danger"><i class="fa-solid fa-times-circle me-1"></i><?= htmlspecialchars($producto['estado_descripcion']) ?></span>
                                                                <?php } ?>
                                                            </td>
                                                            <!-- <td class="text-body-secondary"><?= htmlspecialchars($producto['estado_descripcion']) ?></td> -->
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } else { ?>
                                        <div class="alert alert-info border-info bg-info bg-opacity-10 mb-0">
                                            <i class="fa-solid fa-info-circle me-2"></i>No hay aportaciones registradas
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Créditos -->
                        <div class="col-md-12 mb-4">
                            <div class="card shadow-sm border border-warning">
                                <div class="card-header bg-warning bg-opacity-10 border-bottom border-warning">
                                    <h5 class="mb-0 text-warning">
                                        <i class="fa-solid fa-money-bill-wave me-2"></i>Créditos
                                    </h5>
                                </div>
                                <div class="card-body bg-body">
                                    <?php if ($status && !empty($cuentasCreditos)) { ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0">
                                                <thead>
                                                    <tr class="border-bottom">
                                                        <th>#</th>
                                                        <th class="text-body-secondary">Descripción</th>
                                                        <th class="text-body-secondary">Cuenta</th>
                                                        <th class="text-end text-body-secondary">Monto</th>
                                                        <th class="text-body-secondary">Estado</th>
                                                        <!-- <th class="text-body-secondary">Descripción Estado</th> -->
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cuentasCreditos as $index => $producto) { ?>
                                                        <tr>
                                                            <td><strong><?= $index + 1 ?></strong></td>
                                                            <td><strong><?= htmlspecialchars($producto['descripcion']) ?></strong></td>
                                                            <td><span class="badge bg-secondary bg-opacity-75"><?= htmlspecialchars($producto['cuenta']) ?></span></td>
                                                            <td class="text-end fw-bold text-warning"><?= number_format($producto['saldo'], 2) ?></td>
                                                            <td>
                                                                <span class="badge bg-warning text-dark"><i class="fa-solid fa-info-circle me-1"></i><?= htmlspecialchars($producto['estado_descripcion']) ?></span>
                                                            </td>
                                                            <!-- <td class="text-body-secondary"><?= htmlspecialchars($producto['estado_descripcion']) ?></td> -->
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } else { ?>
                                        <div class="alert alert-info border-info bg-info bg-opacity-10 mb-0">
                                            <i class="fa-solid fa-info-circle me-2"></i>No hay créditos registrados
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center mt-2" id="modal_footer">
                            <?php if ($status): ?>
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="reportes([[],[],[],['<?= $idPerfil ?>']], 'pdf', 'productos_pdf', 0)">
                                    <i class="fa-regular fa-file-pdf"></i> PDF
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="buscar_cli_gen" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <!-- Modal Header -->
                    <div class="modal-header">
                        <h4 class="modal-title">Busqueda de Clientes</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <!-- Modal body -->
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table id="tb_clientes" class="table table-striped nowrap  table-sm small-font" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th scope="col">Codigo</th>
                                        <th scope="col">Nombre Completo</th>
                                        <th scope="col">No. Identificación</th>
                                        <th scope="col">Nacimiento</th>
                                        <th scope="col">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="categoria_tb">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Modal footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $(document).ready(function() {
                    const columns = [{
                            data: 'codigo_cliente',
                        },
                        {
                            data: 'nombre',
                        },
                        {
                            data: 'identificacion',
                        },
                        {
                            data: 'fecha_nacimiento'
                        },
                        {
                            data: null,
                            title: 'Acción',
                            orderable: false,
                            searchable: false,
                            render: function(data, type, row) {
                                return `<button data-bs-dismiss="modal" class="btn btn-outline-warning" 
                                                onclick="printdiv2('#cuadro','${row.codigo_cliente}');">Seleccionar</button>`;
                            }
                        }
                    ];
                    const table = initServerSideDataTable(
                        '#tb_clientes',
                        'cli_clientes_all',
                        columns, {
                            onError: function(xhr, error, thrown) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al cargar clientes',
                                    text: 'Por favor, intente nuevamente'
                                });
                            }
                        }
                    );
                });

            });
        </script>
<?php
        break;
}
