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
require_once __DIR__ . '/../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../includes/Config/database.php';
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

$condi = $_POST["condi"];

switch ($condi) {
    case 'case1':
        $account = $_POST["xtra"];
        $query = "";
        $showmensaje = false;
        try {
            $database->openConnection();
            //aqui se hacen las consultas, previo a mostrar la informacion en la vista
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

?>
        <h4>Hola aqui chavos</h4>
    <?php
        break;
    case 'rteindividual':
        $id = $_POST["xtra"];
        $showmensaje = false;

        try {
            $database->openConnection();

            // Verifica que existan tipos de cuentas activos
            $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], 'estado=1');
            if (empty($tiposcuentas)) {
                $showmensaje = true;
                throw new Exception("No hay tipos de cuentas");
            }

            // Consulta de movimientos en tb_RTE_use (solo registros no eliminados)
            // Si necesitas depurar y ver todos los registros, puedes comentar la condición Deletedate
            $queryMovimientos = "
                        SELECT 
                            Id_RTE,
                            ccdocta,
                            DPI,
                            ori_fondos,
                            desti_fondos,
                            Mon,
                            propietario,
                            CONCAT_WS(' ', Nombre1, Nombre2, Nombre3) AS Nombre,
                            CONCAT_WS(' ', Apellido1, Apellido2, Apellido_de_casada) AS Apellidos,
                            CASE 
                                WHEN propietario = 1 THEN 'Si' 
                                ELSE 'No'
                            END AS trasaccion
                        FROM tb_RTE_use
                        WHERE Deletedate IS NULL OR Deletedate = ''
                        ORDER BY Nombre ASC
                    ";

            $movimientos = $database->getAllResults($queryMovimientos);

            if (empty($movimientos)) {
                $showmensaje = true;
                throw new Exception("No hay datos en tb_RTE_use");
            }

            // Para registros con propietario "Si": vincular el código de cuenta (ccdocta) con la tabla ahomcta
            // para obtener el id del cliente (ccodcli)
            $ahomctaCodes = [];
            foreach ($movimientos as $movimiento) {
                if ($movimiento['trasaccion'] === 'Si') {
                    $ahomctaCodes[] = $movimiento['ccdocta'];
                }
            }
            $savingToClient = []; // Mapea el código de cuenta de ahorro (ccodaho) al id del cliente (ccodcli)
            if (!empty($ahomctaCodes)) {
                $ahomctaCodes = array_unique($ahomctaCodes);
                $inClauseAho = implode(',', array_map('intval', $ahomctaCodes));
                $queryAhomcta = "
                            SELECT 
                                ccodaho,
                                ccodcli
                            FROM ahomcta
                            WHERE ccodaho IN ($inClauseAho)
                        ";
                $ahomctaData = $database->getAllResults($queryAhomcta);
                if (!empty($ahomctaData)) {
                    foreach ($ahomctaData as $row) {
                        // Mapear: código de cuenta de ahorro => id del cliente
                        $savingToClient[$row['ccodaho']] = $row['ccodcli'];
                    }
                }
            }

            // Para los registros propietarios, se recopilan los IDs de clientes obtenidos a partir de ahomcta
            $clientesIds = [];
            if (!empty($savingToClient)) {
                foreach ($savingToClient as $clientId) {
                    $clientesIds[] = $clientId;
                }
            }
            // Consulta la información de los clientes en tb_cliente usando compl_name y no_identifica
            $clientesIndex = [];
            if (!empty($clientesIds)) {
                $clientesIds = array_unique($clientesIds);
                $inClauseCli = implode(',', array_map('intval', $clientesIds));
                $queryClientes = "
                            SELECT 
                                idcod_cliente,
                                compl_name,
                                no_identifica
                            FROM tb_cliente
                            WHERE idcod_cliente IN ($inClauseCli)
                        ";
                $clientesData = $database->getAllResults($queryClientes);
                if (!empty($clientesData)) {
                    foreach ($clientesData as $clienteRow) {
                        $clientesIndex[$clienteRow['idcod_cliente']] = $clienteRow;
                    }
                }
            }
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje)
                ? "Error: " . $e->getMessage()
                : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
        } finally {
            $database->closeConnection();
        }


    ?>
        <div class="text text-center">GENERACIÓN DE REPORTE DE FORMA INDIVIDUAL IVE</div>
        <!-- Inputs ocultos requeridos (se mantienen para compatibilidad) -->
        <input type="hidden" id="condi" value="rteindividual">
        <input type="hidden" id="file" value="view001">
        <input type="hidden" id="finicio" value="<?php echo date('Y-m-d'); ?>">
        <input type="hidden" id="ffin" value="<?php echo date('Y-m-d'); ?>">
        <input type="hidden" id="tipcuenta" value="0">
        <input type="hidden" id="r1" value="all">

        <div class="card">
            <div class="card-body">
                <br>
                <div id="divshow" class="container contenedort">
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-striped table-bordered small-font" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>Cuenta</th>
                                    <th>DPI / Identificación</th>
                                    <th>Origen de Fondos</th>
                                    <th>Destino de Fondos</th>
                                    <th>Titular de la cuenta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($movimientos)) {
                                    foreach ($movimientos as $movimiento) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($movimiento['Id_RTE']) . '</td>';
                                        // Si es propietario, usar datos de tb_cliente obtenidos a partir de ahomcta
                                        if (
                                            $movimiento['trasaccion'] === 'Si'
                                            && isset($savingToClient[$movimiento['ccdocta']])
                                            && isset($clientesIndex[$savingToClient[$movimiento['ccdocta']]])
                                        ) {
                                            $cliente = $clientesIndex[$savingToClient[$movimiento['ccdocta']]];
                                            echo '<td>' . htmlspecialchars($cliente['compl_name']) . '</td>';
                                            // Se muestra el número de cuenta de ahorro (del campo ccdocta en tb_RTE_use)
                                            echo '<td>' . htmlspecialchars($movimiento['ccdocta']) . '</td>';
                                            echo '<td>' . htmlspecialchars($cliente['no_identifica']) . '</td>';
                                            // Origen y Destino de Fondos se muestran como "N/A" (ya que provienen de tb_RTE_use)
                                            echo '<td>N/A</td>';
                                            echo '<td>N/A</td>';
                                            echo '<td>' . htmlspecialchars($movimiento['trasaccion']) . '</td>';
                                        } else {
                                            // Para registros que no son propietarios, se usan los datos de tb_RTE_use
                                            $nombreCompleto = trim(($movimiento['Nombre'] ?? '') . ' ' . ($movimiento['Apellidos'] ?? ''));
                                            echo '<td>' . htmlspecialchars($nombreCompleto) . '</td>';
                                            echo '<td>' . htmlspecialchars($movimiento['ccdocta']) . '</td>';
                                            echo '<td>' . htmlspecialchars($movimiento['DPI']) . '</td>';
                                            echo '<td>' . htmlspecialchars($movimiento['ori_fondos']) . '</td>';
                                            echo '<td>' . htmlspecialchars($movimiento['desti_fondos']) . '</td>';
                                            echo '<td>' . htmlspecialchars($movimiento['trasaccion']) . '</td>';
                                        }
                                        echo '<td>
                                                        <button type="button" class="btn btn-outline-danger" title="Reporte de movimientos" onclick="rteprocesIndividual(\'pdf\', 0, \'' . htmlspecialchars($movimiento['ccdocta']) . '\', \'' . htmlspecialchars($movimiento['trasaccion']) . '\');">
                                                            <i class="fa-solid fa-file-pdf"></i> PDF
                                                        </button>
                                                      </td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="8" class="text-center">No hay datos disponibles</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Función para generar el reporte individual usando solo el código de cuenta y la titularidad -->
        <script>
            function rteprocesIndividual(tipo, download, cuenta, titular) {
                var cuentaInput = document.getElementById('rteCuenta');
                if (!cuentaInput) {
                    cuentaInput = document.createElement('input');
                    cuentaInput.type = 'hidden';
                    cuentaInput.id = 'rteCuenta';
                    document.body.appendChild(cuentaInput);
                }
                cuentaInput.value = cuenta;

                var titularInput = document.getElementById('rteTitular');
                if (!titularInput) {
                    titularInput = document.createElement('input');
                    titularInput.type = 'hidden';
                    titularInput.id = 'rteTitular';
                    document.body.appendChild(titularInput);
                }
                titularInput.value = titular;

                reportes([
                    ['rteCuenta', 'rteTitular'],
                    [],
                    [],
                    []
                ], tipo, 'Repo_RTEIndi', download, 0, 'dfecope', 'saldogen2', 2, 'Saldos', 2);
            }

            $(document).ready(function() {
                $('#tbdatashow').DataTable({
                    "pageLength": 5,
                    "lengthChange": true,
                    "searching": true,
                    "ordering": true,
                    "info": true,
                    "autoWidth": false,
                    "responsive": true,
                    "language": {
                        "paginate": {
                            "previous": "Anterior",
                            "next": "Siguiente"
                        },
                        "search": "Buscar:",
                        "lengthMenu": "Mostrar _MENU_ registros",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        "infoEmpty": "No hay registros disponibles",
                        "zeroRecords": "No se encontraron coincidencias"
                    }
                });
            });
        </script>
    <?php
        break;

    case 'mod_recurrente':
        $codusu      = $_SESSION['id'];
        $showmensaje = false;

        try {
            $database->openConnection();

            /* =========  CONSULTA CORREGIDA  ========= */
            $queryClientes = "
            SELECT
                c.idcod_cliente                  AS codigo_cliente,
                c.short_name                     AS nombre,
                c.no_identifica                  AS identificacion,
                c.estado                         AS estado_cliente,
                c.id_tipoCliente                 AS tipo_cliente,

                /* '1', ' 1', '01'  → 1 ; cualquier otro / NULL → 0 */
                COALESCE(
                    MAX(
                        CASE
                            WHEN CAST(ca.valor AS UNSIGNED) = 1 THEN 1
                            ELSE 0
                        END
                    ), 0
                )                                AS recurrente

            FROM tb_cliente c
            LEFT JOIN tb_cliente_atributo ca
                   ON CAST(TRIM(ca.id_cliente) AS UNSIGNED) = c.idcod_cliente
                  AND ca.id_atributo = 19               -- 19 = Recurrente
            WHERE c.estado = 1                          -- solo activos
            GROUP BY c.idcod_cliente, c.short_name, c.no_identifica,
                     c.estado, c.id_tipoCliente
            ORDER BY c.short_name ASC
        ";

            $clientes = $database->getAllResults($queryClientes);

            if (empty($clientes)) {
                throw new Exception("No hay clientes disponibles");
            }

            /* Tipos de cliente para el combo */
            $tipos = $database->getAllResults("
            SELECT DISTINCT id_tipoCliente AS tipo
            FROM tb_cliente
            WHERE id_tipoCliente IS NOT NULL
            ORDER BY tipo ASC
        ");
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores(
                    $e->getMessage(),
                    __FILE__,
                    __LINE__,
                    $e->getFile(),
                    $e->getLine()
                );
            }
            $msg = $showmensaje
                ? 'Error: ' . $e->getMessage()
                : "Error: Intente nuevamente o reporte este código ($codigoError)";
            echo "<div class='alert alert-danger'>" . htmlspecialchars($msg) . '</div>';
            $database->closeConnection();
            break;
        } finally {
            $database->closeConnection();
        }
    ?>

        <div id="contenido_cuentas">
            <div class="text text-center mb-3">MODIFICACIÓN DE RECURRENTE</div>

            <!-- Filtros -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <label>Filtrar por Estado</label>
                    <select id="filtro_estado" class="form-select">
                        <option value="all" selected>Todos</option>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Filtrar por Tipo Cliente</label>
                    <select id="filtro_tipo" class="form-select">
                        <option value="all" selected>Todos</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= htmlspecialchars($t['tipo']) ?>">
                                <?= htmlspecialchars($t['tipo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Filtrar por Recurrente</label>
                    <select id="filtro_recurrente" class="form-select">
                        <option value="all" selected>Todos</option>
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>

            <!-- Botones de acción masiva -->
            <div class="mb-2">
                <button id="btnMarcarTodos" class="btn btn-success btn-sm me-1">Marcar todos como recurrentes</button>
                <button id="btnDesmarcarTodos" class="btn btn-danger  btn-sm">Quitar recurrencia a todos</button>
            </div>

            <!-- Tabla -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla_cuentas" class="table table-hover table-bordered" style="width:100%">
                            <thead class="table-head-aho text-light" style="font-size:0.8rem">
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Código Cliente</th>
                                    <th>Nombre</th>
                                    <th>Identificación</th>
                                    <th>Estado</th>
                                    <th>Tipo Cliente</th>
                                    <th>Recurrente</th> <!-- NUEVA col. visual -->
                                    <th>Acciones</th> <!-- NUEVA col. botones -->
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($clientes as $row): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="row-select"
                                                data-id="<?= $row['codigo_cliente'] ?>">
                                        </td>
                                        <td><?= htmlspecialchars($row['codigo_cliente']) ?></td>
                                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                                        <td><?= htmlspecialchars($row['identificacion']) ?></td>
                                        <td><?= $row['estado_cliente'] ? 'Activo' : 'Inactivo' ?></td>
                                        <td><?= htmlspecialchars($row['tipo_cliente']) ?></td>

                                        <!-- Columna Recurrente (solo texto) -->
                                        <td class="rec-estado"><?= $row['recurrente'] ? 'Sí' : 'No' ?></td>

                                        <!-- Columna Acciones -->
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-success btn-rec"
                                                data-id="<?= $row['codigo_cliente'] ?>"
                                                data-valor="1"
                                                <?= $row['recurrente'] ? '' : 'style="opacity:.35"' ?>>
                                                Sí
                                            </button>
                                            <button class="btn btn-sm btn-secondary btn-rec"
                                                data-id="<?= $row['codigo_cliente'] ?>"
                                                data-valor="0"
                                                <?= $row['recurrente'] ? 'style="opacity:.35"' : '' ?>>
                                                No
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================  JS ==================== -->
        <script>
            $(function() {
                /* ---------- DataTable ---------- */
                const table = $('#tabla_cuentas').DataTable({
                    pageLength: 10,
                    responsive: true,
                    language: {
                        paginate: {
                            previous: 'Anterior',
                            next: 'Siguiente'
                        },
                        search: 'Buscar:',
                        lengthMenu: 'Mostrar _MENU_ registros',
                        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                        zeroRecords: 'No se encontraron registros'
                    }
                });

                /* ---------- Filtros ---------- */
                $.fn.dataTable.ext.search.push(function(settings, data) {
                    const estadoFilter = $('#filtro_estado').val();
                    const tipoFilter = $('#filtro_tipo').val();
                    const recurrenteFilter = $('#filtro_recurrente').val();

                    const estado = (data[4] === 'Activo' ? '1' : '0'); // estado
                    const tipo = data[5]; // tipo
                    const recTexto = data[6]; // "Sí"/"No"
                    const recVal = (recTexto === 'Sí' ? '1' : '0');

                    return (estadoFilter === 'all' || estadoFilter === estado) &&
                        (tipoFilter === 'all' || tipoFilter === tipo) &&
                        (recurrenteFilter === 'all' || recurrenteFilter === recVal);
                });

                $('#filtro_estado, #filtro_tipo, #filtro_recurrente').on('change', () => table.draw());

                /* ---------- Seleccionar todo ---------- */
                $('#selectAll').on('change', function() {
                    $('.row-select').prop('checked', this.checked);
                });

                /* ---------- Botón individual Sí / No ---------- */
                $('#tabla_cuentas').on('click', '.btn-rec', function() {
                    const $btn = $(this);
                    const id = $btn.data('id');
                    const valor = $btn.data('valor'); // 1 = Sí, 0 = No
                    const txt = valor ? 'Marcar como recurrente' : 'Quitar recurrencia';

                    Swal.fire({
                        title: '¿Actualizar recurrencia?',
                        text: txt,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar'
                    }).then(r => {
                        if (!r.isConfirmed) return;

                        updateRecurrente([id], valor).then(() => {
                            const $row = $btn.closest('tr');
                            // Actualizar columna visual
                            $row.find('.rec-estado').text(valor ? 'Sí' : 'No');
                            // Refrescar estilos de botones
                            $row.find('.btn-rec').css('opacity', '.35');
                            $row.find('[data-valor="' + valor + '"]').css('opacity', '1');
                            table.draw(false);
                        });
                    });
                });

                /* ---------- Botones masivos ---------- */
                $('#btnMarcarTodos').on('click', () => bulkAction(1, 'Marcar a todos como recurrentes'));
                $('#btnDesmarcarTodos').on('click', () => bulkAction(0, 'Quitar recurrencia a todos'));

                function bulkAction(valor, mensaje) {
                    const ids = $('.row-select:checked').map((_, el) => $(el).data('id')).get();
                    if (!ids.length) return Swal.fire('Aviso', 'Selecciona al menos un cliente.', 'info');

                    Swal.fire({
                        title: '¿Confirmar acción masiva?',
                        text: mensaje,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, aplicar',
                        cancelButtonText: 'Cancelar'
                    }).then(r => {
                        if (!r.isConfirmed) return;

                        updateRecurrente(ids, valor).then(() => {
                            ids.forEach(id => {
                                const $row = $('.btn-rec[data-id="' + id + '"]').closest('tr');
                                $row.find('.rec-estado').text(valor ? 'Sí' : 'No');
                                $row.find('.btn-rec').css('opacity', '.35');
                                $row.find('[data-id="' + id + '"][data-valor="' + valor + '"]')
                                    .css('opacity', '1');
                            });
                            table.draw(false);
                        });
                    });
                }

                /* ---------- AJAX helper ---------- */
                function updateRecurrente(clientes, valor) {
                    return $.ajax({
                        url: '../../src/cruds/crud_ahorro.php',
                        method: 'POST',
                        data: {
                            condi: 'actua_recurrente',
                            clientes,
                            valor
                        }
                    }).then(resp => {
                        let data;
                        try {
                            data = JSON.parse(resp);
                        } catch {
                            data = [resp, 0];
                        }
                        if (data[1] !== '1') {
                            Swal.fire('Error', data[0], 'error');
                            return $.Deferred().reject();
                        }
                        Swal.fire('Éxito', data[0], 'success');
                    }).fail(() => Swal.fire('Error', 'No se pudo actualizar.', 'error'));
                }
            });
        </script>

    <?php
        break;
    case 'rgenrte':
        $showmensaje = false;
        try {
            $database->openConnection();
            // Se obtienen todas las agencias activas. Algunas bases de datos no
            // cuentan con la columna "estado" por lo que no se aplica
            // condición alguna para evitar errores en la consulta.
            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'cod_agenc', 'nom_agencia']);
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores(
                    $e->getMessage(),
                    __FILE__,
                    __LINE__,
                    $e->getFile(),
                    $e->getLine()
                );
            }
            $mensaje = $showmensaje
                ? 'Error: ' . $e->getMessage()
                : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
    ?>
        <div class="text text-center">GENERACIÓN DE REPORTE GENERAL DE RTE</div>
        <input type="hidden" id="condi" value="rgenrte">
        <input type="hidden" id="file" value="view001">

        <div class="card">
            <div class="card-header">Filtros</div>
            <div class="card-body">
                <div class="row container contenedort">
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Oficina</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="ragencia" id="allofi" value="allofi" checked disabled>
                                            <label for="allofi" class="form-check-label">Consolidado</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card text-bg-light" style="height: 100%;">
                            <div class="card-header">Filtro por Fechas</div>
                            <div class="card-body">
                                <div class="row" id="filfechas">
                                    <div class="col-sm-6">
                                        <label for="finicio">Desde</label>
                                        <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="ffin">Hasta</label>
                                        <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center mt-3">
                    <div class="col align-items-center">
                        <!-- PDF -->
                        <button type="button" class="btn btn-outline-danger"
                            onclick="reportes(
        [['finicio','ffin'], [], [], []],
        'pdf',
        'Repo_RTEGeneral',
        0
      )">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>

                        <!-- Excel -->
                        <button type="button" class="btn btn-outline-success"
                            onclick="reportes(
        [['finicio','ffin'], [], [], []],
        'xlsx',
        'Repo_RTEGeneral',
        1
      )">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>

                        <!-- Cancelar -->
                        <button type="button" class="btn btn-outline-danger"
                            onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <script>
            function rteprocesGeneral(tipo, download) {
                console.log('rteprocesGeneral()', {
                    tipo: tipo,
                    download: download
                });
                reportes([
                    ['finicio', 'ffin'],
                    [],
                    [],
                    []
                ], tipo, 'Repo_RTEGeneral', download, 0, 'dfecope', 'saldogen2', 2, 'Saldos', 2);
            }
        </script>
<?php
        break;
}
