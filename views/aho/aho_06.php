<?php

use App\Generic\Models\TipoDocumentoTransaccion;
use Micro\Helpers\Log;

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
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$idagencia = $_SESSION['id_agencia'];

$condi = $_POST["condi"];

switch ($condi) {
    //Listado de cuentas activas/disponibles
    case 'ListadoCuentasActivas':
        $id = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $tiposcuentas = $database->selectColumns('ahomtip', ['id_tipo', 'ccodtip', 'nombre', 'cdescripcion'], 'estado=1');
            if (empty($tiposcuentas)) {
                $showmensaje = true;
                throw new Exception("No hay tipos de cuentas");
            }
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        ?>
        <!-- APR_05_LstdCntsActvsDspnbls -->
        <div class="text" style="text-align:center">LISTADO DE CUENTAS ACTIVAS/INACTIVAS</div>
        <input type="text" value="ListadoCuentasActivas" id="condi" style="display: none;">
        <input type="text" value="aho_06" id="file" style="display: none;">

        <div class="card">
            <div class="card-header">Listado de Cuentas Activas/Inactivas</div>
            <div class="card-body">
                <!-- Card para los estados de cuenta -->
                <div class="row mb-2">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><b>Estados de cuenta</b></div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- radio button de todas las cuentas -->
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_estado" id="r_todos"
                                                checked value="0">
                                            <label class="form-check-label" for="r_todos">Todos</label>
                                        </div>
                                    </div>

                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_estado" id="r_activos"
                                                value="A">
                                            <label class="form-check-label" for="r_activos">Activos</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_estado" id="r_inactivos"
                                                value="B">
                                            <label class="form-check-label" for="r_inactivos">Inactivos</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="row d-flex align-items-stretch mb-3">
                    <!-- card para filtrar cuentas -->
                    <div class="col-6">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header"><b>Filtro de cuentas</b></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_cuenta" id="r_cuentas"
                                                value="1" checked onclick="activar_select_cuentas(this, true,'tipcuenta')">
                                            <label class="form-check-label" for="r_cuentas">Todos</label>
                                        </div>
                                    </div>
                                    <div class="col d-flex justify-content-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filter_cuenta" id="r_cuenta"
                                                value="2" onclick="activar_select_cuentas(this,false,'tipcuenta')">
                                            <label class="form-check-label" for="r_cuenta">Una cuenta</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- card para seleccionar una cuenta -->
                    <div class="col-6">
                        <div class="card" style="height: 100% !important;">
                            <div class="card-header"><b>Filtrar por una cuenta</b></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col">
                                        <select class="form-select" aria-label="Default select example" id="tipcuenta" disabled>
                                            <?php
                                            echo '<option selected disabled value="0">Seleccione un tipo de cuenta</option>';
                                            foreach ($tiposcuentas as $tip) {
                                                echo '<option value="' . $tip['id_tipo'] . '">' . $tip['nombre'] . ' - ' . $tip['cdescripcion'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnSave" class="btn btn-outline-success"
                            onclick="reportes([[],[`tipcuenta`],[`filter_estado`,`filter_cuenta`],['<?php echo $idusuario; ?>','<?php echo $ofi; ?>']], 'xlsx', 'listado_cuentas_aho', 1)">
                            <i class="fa-solid fa-file-excel"></i> Reporte en Excel
                        </button>
                        <button type="button" id="btnSave" class="btn btn-outline-success"
                            onclick="reportes([[],[`tipcuenta`],[`filter_estado`,`filter_cuenta`],['<?php echo $idusuario; ?>','<?php echo $ofi; ?>']], 'pdf', 'listado_cuentas_aho', 0)">
                            <i class="fa-regular fa-file-pdf"></i> Reporte en PDF
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
    case 'cuadrediario':
        $id = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], 'estado=1');
            if (empty($tiposcuentas)) {
                $showmensaje = true;
                throw new Exception("No hay tipos de cuentas");
            }

            $tiposDocumentos = TipoDocumentoTransaccion::getTiposDisponibles(1);
            $status = true;
            // Log::info("Tipos de documentos obtenidos: " . json_encode($tiposDocumentos));
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
        <input type="text" value="cuadrediario" id="condi" style="display: none;">
        <input type="text" value="aho_06" id="file" style="display: none;">
        <div class="card">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-file-lines"></i> CUADRE DIARIO DE DEPOSITOS Y RETIROS
                </h4>
            </div>
            <div class="card-body">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>!!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                <div class="container contenedort">
                    <div class="row">
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Tipos de cuentas</b></div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <small class="text-muted">Puede seleccionar varios tipos de cuenta. Si desea incluir
                                                todos, no seleccione ninguno.</small>
                                            <select class="form-select" aria-label="Default select example" id="tipcuenta"
                                                multiple data-control="select2" data-placeholder="Todos los tipos de cuentas">
                                                <?php
                                                foreach ($tiposcuentas as $tip) {
                                                    echo '<option value="' . $tip['ccodtip'] . '">' . $tip['ccodtip'] . ' - ' . $tip['nombre'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Fechas</b></div>
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
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro adicional</b></div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" value="lib" id="lib"
                                                checked name="checks">
                                            <label class="form-check-label" for="lib">Incluir cambios de libreta</label>
                                        </div>
                                    </div>
                                    <div style="max-width: 100%;">
                                        <label for="tipoDocumento" class="form-label">Tipo de documento</label>
                                        <br>
                                        <small class="text-muted">Puede seleccionar varios tipos de documento. Si desea incluir
                                            todos, no seleccione ninguno.</small>
                                        <select class="form-select" aria-label="Selector de tipos de documentos"
                                            id="tipoDocumento" multiple data-control="select2"
                                            data-placeholder="Todos los tipos de documentos">
                                            <?php
                                            // echo '<option selected value="0">Todos los tipos de documentos</option>';
                                            foreach ($tiposDocumentos as $key => $tip) {
                                                echo "<option value=\"{$key}\">{$tip}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" class="btn btn-outline-primary" title="Reporte de movimientos"
                            onclick="process('show',0);">
                            <i class="fa-solid fa-eye"></i> Ver
                        </button>
                        <button type="button" id="btnSave" class="btn btn-outline-success" title="Reporte de movimientos"
                            onclick="process('xlsx',1);">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" id="btnSave" class="btn btn-outline-danger" title="Reporte de movimientos"
                            onclick="process('pdf',0);">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <br>
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
            function process(tipo, download) {
                let checkedValues = [];
                let checkboxes = document.querySelectorAll('input[name="checks"]:checked');
                checkboxes.forEach((checkbox) => {
                    checkedValues.push(checkbox.value);
                });
                let tiposDocumentos = $('#tipoDocumento').val();
                let tipcuenta = $('#tipcuenta').val();

                reportes([
                    [`finicio`, `ffin`],
                    [],
                    [],
                    [checkedValues, tiposDocumentos, tipcuenta]
                ], tipo, `cuadre_diario`, download, 0, 'dfecope', 'saldogen2', 2, 'Saldos', 2);
            }
            $(document).ready(function () {
                $('#tipoDocumento').select2({
                    theme: 'bootstrap-5',
                    language: 'es',
                    closeOnSelect: false
                });
                $('#tipcuenta').select2({
                    theme: 'bootstrap-5',
                    language: 'es',
                    closeOnSelect: false
                });
            });
        </script>
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

        // echo $mensaje;  

        // echo '<pre>';
        // print_r($movimientos);
        // echo '</pre>';
        // echo '<pre>';
        // print_r($ahomctaData);
        // echo '</pre>';
        // echo '<pre>';
        // print_r($clientesData);
        // echo '</pre>';
        ?>
        <div class="text text-center">GENERACIÓN DE REPORTE DE FORMA INDIVIDUAL IVE</div>
        <!-- Inputs ocultos requeridos (se mantienen para compatibilidad) -->
        <input type="hidden" id="condi" value="rteindividual">
        <input type="hidden" id="file" value="aho_06">
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
                                        <button type="button" class="btn btn-outline-danger" title="Reporte de movimientos" 
                                            onclick="rteprocesIndividual(\'pdf\', 0, \'' . htmlspecialchars($movimiento['ccdocta']) . '\', \'' . htmlspecialchars($movimiento['trasaccion']) . '\', \'' . htmlspecialchars($movimiento['Id_RTE']) . '\');">
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
            function rteprocesIndividual(tipo, download, cuenta, titular, transid) {
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

                // Nuevo input para el ID de la transacción
                var transInput = document.getElementById('rteTrans');
                if (!transInput) {
                    transInput = document.createElement('input');
                    transInput.type = 'hidden';
                    transInput.id = 'rteTrans';
                    document.body.appendChild(transInput);
                }
                transInput.value = transid;

                // Enviar los tres inputs al reporte
                reportes([
                    ['rteCuenta', 'rteTitular', 'rteTrans'],
                    [],
                    [],
                    []
                ], tipo, 'Repo_RTEIndi', download, 0, 'dfecope', 'saldogen2', 2, 'Saldos', 2);
            }


            $(document).ready(function () {
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




        /*
    case 'generacionReporteIVE':
        $id = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $tiposcuentas = $database->selectColumns('ahomtip', ['ccodtip', 'nombre', 'cdescripcion'], 'estado=1');
            if (empty($tiposcuentas)) {
                $showmensaje = true;
                throw new Exception("No hay tipos de cuentas");
            }
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
    ?>
        <div class="text" style="text-align:center">GENERACIÓN DE REPORTE IVE GENERAL</div>
        <input type="text" value="generacionReporteIVE" id="condi" style="display: none;">
        <input type="text" value="aho_06" id="file" style="display: none;">
        <div class="card">
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row">
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Transacciones</b></div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="r1" id="all" value="all" checked onclick="habdeshab([],['tipcuenta'])">
                                                <label for="all" class="form-check-label">Todo </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="r1" id="any" value="any" onclick="habdeshab(['tipcuenta'],[])">
                                                <label for="any" class="form-check-label"> Por Tipo de Cuenta</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <span class="input-group-addon col-2">Tipo de Cuenta</span>
                                            <select class="form-select" id="tipcuenta" disabled>
                                                <option value="0" selected disabled>Seleccionar tipo de cuenta</option>
                                                <?php
                                                foreach ($tiposcuentas as $cuenta) {
                                                    echo '<option value="' . $cuenta['ccodtip'] . '">' . $cuenta['ccodtip'] . " - " . $cuenta['nombre'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Fechas</b></div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="card" style="height: 100% !important;">
                                <div class="card-header"><b>Filtro adicional</b></div>
                                <div class="card-body">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" value="lib" id="lib" checked name="checks">
                                        <label class="form-check-label" for="lib">Incluir cambios de libreta</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center">
                    <div class="col align-items-center" id="modal_footer">
                        <button type="button" id="btnSave" class="btn btn-outline-success" title="Reporte de movimientos" onclick="rteproces('xlsx',1);">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" id="btnSave" class="btn btn-outline-danger" title="Reporte de movimientos" onclick="rteproces('pdf',0);">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <br>
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
            function rteproces(tipo, download) {
                let checkedValues = [];
                let checkboxes = document.querySelectorAll('input[name="checks"]:checked');
                checkboxes.forEach((checkbox) => {
                    checkedValues.push(checkbox.value);
                });

                reportes([
                    [`finicio`, `ffin`],
                    [`tipcuenta`],
                    [`r1`],
                    [checkedValues]
                ], tipo, `Repo_RTEGeneral`, download, 0, 'dfecope', 'saldogen2', 2, 'Saldos', 2);
            }
        </script>*/

        break;
}



?>