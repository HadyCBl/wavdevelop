<?php

use Micro\Helpers\Log;
use App\Generic\FileProcessor;
use App\Generic\MensajesSistema;
use Micro\Models\Pais;

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

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
// date_default_timezone_set('America/Guatemala');

$idusuario = $_SESSION["id"];
$id_agencia = $_SESSION['id_agencia'];

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");
//+++++++


include_once "../../../src/cris_modales/mdls_cre_indi02.php";
include '../../../includes/BD_con/db_con.php';

include '../../../src/funcphp/valida.php';
// include '../../../src/funcphp/func_gen.php';

mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

$condi = $_POST["condi"];

switch ($condi) {
    case 'create_garantias':

        // Log::info("Creating or updating guarantees", [$_POST["xtra"] ?? []]);
        if (isset($_POST["xtra"]) && is_array($_POST["xtra"])) {
            // list($idCliente, $idGarantia) = $_POST["xtra"];
            $idCliente = $_POST["xtra"]['idCliente'] ?? 0;

            if (isset($_POST["xtra"]['idGarantia'])) {
                $idGarantia = $_POST["xtra"]['idGarantia'];
            }
        } else {
            $idCliente = $_POST["xtra"] ?? 0;
        }

        $optionSection = $_POST["xtra"]['optionSection'] ?? 'new';
        $appPaisVersion = $_ENV['APP_PAIS_VERSION'] ?? 'GT';
        $paisVersionApp = Pais::obtenerPorCodigo($appPaisVersion);

        $idPais = $paisVersionApp ? $paisVersionApp['id'] : 4; // Por defecto GT si no se encuentra

        // Obtener mensajes pendientes para esta sección
        // $mensajesSistema = new MensajesSistema();
        // $mensajesPendientes = $mensajesSistema->obtenerMensajesPendientes('create_garantias', $idusuario);
        // $mostrarMensajes = !empty($mensajesPendientes);

        // Log::info("Mensajes pendientes para 'create_garantias':", $mensajesPendientes);

        $showmensaje = false;
        try {
            $database->openConnection(2);

            if ($idCliente == 0) {
                $showmensaje = true;
                throw new Exception("Debe seleccionar un cliente");
            }

            $tiposGarantias = $database->selectColumns('tb_tiposgarantia', ['id_TiposGarantia', 'TiposGarantia']);
            if (empty($tiposGarantias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron tipos de garantías");
            }
            $tiposDocumentos = $database->selectColumns('tb_tiposdocumentosR', ['idDoc', 'NombreDoc']);
            if (empty($tiposDocumentos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron tipos de documentos");
            }

            $database->closeConnection();

            $database->openConnection(1);

            $departamentos = $database->selectColumns('tb_departamentos', ['id AS codigo_departamento', 'nombre'], 'id_pais=?', [$idPais]);

            if (empty($departamentos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron departamentos");
            }

            // if (isset($idGarantia)) {
            //     $municipios = $database->selectColumns('tb_municipios', ['codigo AS codigo_municipio', 'nombre']);
            // }

            $clientData = $database->selectColumns('tb_cliente', ['idcod_cliente', 'short_name'], 'idcod_cliente=?', [$idCliente]);

            $query = "SELECT gaCli.idGarantia AS idGa, gaCli.idTipoGa as idGara, tipoGa.TiposGarantia AS garantia, 
                gaCli.idTipoDoc idDoc, tipoDoc.NombreDoc AS doc, 
                gaCli.descripcionGarantia AS des, gaCli.direccion, 
                (SELECT depa.nombre FROM $db_name_general.departamentos depa WHERE depa.codigo_departamento=gaCli.depa) AS depa,
                (SELECT muni.nombre FROM $db_name_general.municipios muni WHERE muni.codigo_municipio=gaCli.muni) AS muni,
                gaCli.valorComercial, gaCli.montoAvaluo, gaCli.montoGravamen, gaCli.fechaCreacion, gaCli.archivo,
                cliAdi.latitud, cliAdi.longitud, cliAdi.altitud, cliAdi.precision AS precision_gps, cliAdi.direccion_texto
            FROM cli_garantia AS gaCli
            INNER JOIN tb_cliente AS cli ON gaCli.idCliente = cli.idcod_cliente
            INNER JOIN $db_name_general.tb_tiposgarantia AS tipoGa ON gaCli.idTipoGa = tipoGa.id_TiposGarantia
            INNER JOIN $db_name_general.tb_tiposdocumentosR AS tipoDoc ON gaCli.idTipoDoc = tipoDoc.idDoc
            LEFT JOIN cli_adicionales AS cliAdi ON cliAdi.entidad_tipo = 'garantia' 
                AND cliAdi.entidad_id = gaCli.idGarantia 
                AND cliAdi.estado = 1
            WHERE gaCli.estado = 1 AND gaCli.idCliente=? AND gaCli.idTipoGa!=1 AND gaCli.idTipoDoc NOT IN (8,18) 
            ORDER BY gaCli.idGarantia DESC";

            $garantiasCliente = $database->getAllResults($query, [$idCliente]);
            $fiadores = $database->getAllResults(
                "SELECT idGarantia,short_name,cli.Direccion,idTipoDoc, tipoDoc.NombreDoc FROM tb_cliente cli
                INNER JOIN cli_garantia gaCli ON cli.idcod_cliente = gaCli.descripcionGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR AS tipoDoc ON gaCli.idTipoDoc = tipoDoc.idDoc
                WHERE idTipoGa=1 AND idCliente=? AND gaCli.estado=1",
                [$idCliente]
            );

            $cuentasAhorro = $database->getAllResults(
                "SELECT idGarantia,nombre,cdescripcion,calcular_saldo_aho_tipcuenta(cta.ccodaho,?) AS saldo,ccodaho
                    FROM ahomtip tip 
                    INNER JOIN ahomcta cta ON SUBSTR(cta.ccodaho,7,2)=tip.ccodtip
                    INNER JOIN cli_garantia gar ON gar.descripcionGarantia = cta.ccodaho
                    WHERE idCliente=? AND gar.estado=1 AND idTipoDoc IN (8)",
                [$hoy, $idCliente]
            );
            $cuentasApr = $database->getAllResults(
                "SELECT idGarantia,nombre,cdescripcion,calcular_saldo_apr_tipcuenta(cta.ccodaport,?) AS saldo,ccodaport ccodaho
                    FROM aprtip tip 
                    INNER JOIN aprcta cta ON SUBSTR(cta.ccodaport,7,2)=tip.ccodtip
                    INNER JOIN cli_garantia gar ON gar.descripcionGarantia = cta.ccodaport
                    WHERE idCliente=? AND gar.estado=1 AND idTipoDoc IN (18)",
                [$hoy, $idCliente]
            );

            $tiposCuentasAhorros = $appConfigGeneral->habilitarAhorrosGarantia();
            $tiposCuentasAportaciones = $appConfigGeneral->habilitarAportacionesGarantia();

            // Log::info("Tipos de cuentas de ahorro y aportaciones para garantías", [
            //     'tiposCuentasAhorros' => $tiposCuentasAhorros,
            //     'tiposCuentasAportaciones' => $tiposCuentasAportaciones
            // ]);

            $ahorrosDisponibles = $database->getAllResults(
                "SELECT nombre, cdescripcion, calcular_saldo_aho_tipcuenta(cta.ccodaho,?) AS saldo,ccodaho
                    FROM ahomtip tip 
                    INNER JOIN ahomcta cta ON SUBSTR(cta.ccodaho,7,2)=tip.ccodtip
                    WHERE ccodcli=? AND cta.estado='A' 
                        AND tip.tipcuen IN ($tiposCuentasAhorros)",
                [$hoy, $idCliente]
            );
            $aportacionesDisponibles = $database->getAllResults(
                "SELECT nombre, cdescripcion, calcular_saldo_apr_tipcuenta(cta.ccodaport,?) AS saldo,ccodaport ccodaho
                    FROM aprtip tip 
                    INNER JOIN aprcta cta ON SUBSTR(cta.ccodaport,7,2)=tip.ccodtip
                    WHERE ccodcli=? AND cta.estado='A' 
                        AND tip.tipcuen IN ($tiposCuentasAportaciones)",
                [$hoy, $idCliente]
            );

            if (isset($idGarantia) && $idGarantia != 0) {

                $tipoGarantia = $database->selectColumns("cli_garantia", ['idTipoGa', 'idTipoDoc'], 'idGarantia=?', [$idGarantia]);

                $optionSection = ($tipoGarantia[0]['idTipoGa'] == 1) ? 'fiador' : (($tipoGarantia[0]['idTipoDoc'] == 8) ? 'cuenta' : 'general');

                if ($optionSection == 'general') {
                    $query = "SELECT gaCli.idTipoGa, gaCli.idTipoDoc AS idDoc, gaCli.descripcionGarantia, 
                        gaCli.direccion, gaCli.depa AS idDepa, gaCli.muni AS idMuni, 
                        gaCli.valorComercial, gaCli.montoAvaluo, gaCli.montoGravamen, gaCli.archivo,
                        cliAdi.latitud, cliAdi.longitud, cliAdi.altitud, cliAdi.precision AS precision_gps, 
                        cliAdi.direccion_texto
                    FROM cli_garantia AS gaCli 
                    INNER JOIN tb_cliente AS cli ON gaCli.idCliente = cli.idcod_cliente 
                    INNER JOIN $db_name_general.tb_tiposgarantia AS tipoGa ON gaCli.idTipoGa = tipoGa.id_TiposGarantia
                    INNER JOIN $db_name_general.tb_tiposdocumentosR AS tipoDoc ON gaCli.idTipoDoc = tipoDoc.idDoc
                    LEFT JOIN cli_adicionales AS cliAdi ON cliAdi.entidad_tipo = 'garantia' 
                        AND cliAdi.entidad_id = gaCli.idGarantia 
                        AND cliAdi.estado = 1
                    WHERE gaCli.estado = 1 AND gaCli.idGarantia = ?";
                }

                if ($optionSection == 'fiador') {
                    $query = "SELECT gaCli.idTipoDoc AS idDoc, gaCli.descripcionGarantia, cli.short_name AS fiador, cli.Direccion
                    FROM cli_garantia AS gaCli 
                    INNER JOIN tb_cliente AS cli ON cli.idcod_cliente = gaCli.descripcionGarantia
                    WHERE gaCli.estado = 1 AND gaCli.idGarantia =? AND gaCli.idTipoGa=1";
                }

                if ($optionSection == 'cuenta') {
                    /**
                     * AL PARECER ESTO NO ES NECESARIO, YA QUE NO HAY EDICION INDIVIDUAL DE GARANTIAS PARA CUENTAS
                     */
                    $query = "SELECT gaCli.idTipoDoc AS idDoc, gaCli.descripcionGarantia, gaCli.direccion, gaCli.depa AS idDepa, 
                        gaCli.muni AS idMuni, gaCli.valorComercial, gaCli.montoAvaluo, gaCli.montoGravamen, gaCli.archivo
                    FROM cli_garantia AS gaCli 
                    INNER JOIN tb_cliente AS cli ON gaCli.idCliente = cli.idcod_cliente 
                    INNER JOIN $db_name_general.tb_tiposgarantia AS tipoGa ON gaCli.idTipoGa = tipoGa.id_TiposGarantia
                    INNER JOIN $db_name_general.tb_tiposdocumentosR AS tipoDoc ON gaCli.idTipoDoc = tipoDoc.idDoc
                    WHERE gaCli.estado = 1 AND gaCli.idGarantia =? AND gaCli.idTipoDoc IN (8, 18)";
                }

                $getGarantia = $database->getSingleResult($query, [$idGarantia]);
            }

            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

        // if ($status && $mostrarMensajes) {
        //     foreach ($mensajesPendientes as $message) {
        //         // Log::info("Marking message as seen", ['idMensaje' => $message['id'], 'idUsuario' => $idusuario]);
        //         $mensajesSistema->marcarComoVisto($message['id'], $idusuario);
        //     }
        // }
?>
        <input type="text" id="file" value="cre_indi_02" style="display: none;">
        <input type="text" id="condi" value="create_garantias" style="display: none;">
        <div class="text" style="text-align:center">GARANTÍAS</div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Garantías</span>
            </div>
            <div class="card-body" style="padding-bottom: 0px !important;">
                <?php if (!$status) { ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>¡!</strong> <?= $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>

                <?php
                // Mostrar los mensajes pendientes del sistema
                // if ($mostrarMensajes) {
                //     echo $mensajesSistema->renderizarMensajes($mensajesPendientes);
                // }
                ?>

                <div id="containerMensajesUpdatedCondi" data-condi="<?= $condi ?>">

                </div>

                <div class="container contenedort py-3" style="max-width: 100% !important;">
                    <h4 class="mb-3 text-center">
                        <i class="fa fa-user me-2 text-primary"></i>Datos del Cliente
                    </h4>
                    <div class="row justify-content-center align-items-end mb-3">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <label for="idCliente" class="form-label fw-semibold">ID Cliente</label>
                            <input type="text" class="form-control" id="idCliente" name="idCliente" value="<?php echo $idCliente; ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-2 mb-md-0">
                            <label for="nameCliente" class="form-label fw-semibold">Nombre del Cliente</label>
                            <input type="text" class="form-control" id="nameCliente" name="nameCliente" value="<?php echo $clientData[0]['short_name'] ?? ''; ?>" readonly>
                        </div>
                        <div class="col-md-2 d-grid">
                            <label class="form-label invisible">Buscar</label>
                            <button id="btnBus" class="btn btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#modalCliente">
                                <i class="fa fa-search me-1"></i>Buscar
                            </button>
                        </div>
                    </div>
                    <hr class="my-2">
                </div>
                <div class="container contenedort mb-3" style="max-width: 100% !important;">
                    <h3>GESTIÓN DE GARANTIAS</h3>
                    <!-- Tabs de Bootstrap -->
                    <ul class="nav nav-tabs" id="misTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($optionSection == 'new' || $optionSection == 'general') ? 'active' : '' ?>" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1-content" type="button" role="tab" aria-controls="tab1-content" aria-selected="true">
                                Garantías <span class="badge text-bg-secondary"><?= count($garantiasCliente ?? []) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($optionSection == 'fiador' || $optionSection == 'newFiador') ? 'active' : '' ?>" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2-content" type="button" role="tab" aria-controls="tab2-content" aria-selected="false">
                                Fiadores <span class="badge text-bg-secondary"><?= count($fiadores ?? []) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= ($optionSection == 'cuenta' || $optionSection == 'newCuenta') ? 'active' : '' ?>" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3-content" type="button" role="tab" aria-controls="tab3-content" aria-selected="false">
                                Cuentas de Ahorro y aportaciones <span class="badge text-bg-secondary"><?= count($cuentasAhorro ?? []) + count($cuentasApr ?? []) ?></span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3" id="misTabsContent">
                        <div class="tab-pane fade <?= ($optionSection == 'new' || $optionSection == 'general') ? 'show active' : '' ?>" id="tab1-content" role="tabpanel" aria-labelledby="tab1-tab">
                            <!-- formulario para crear garantias generales -->
                            <?php if (substr($optionSection, 0, 3) == 'new' || $optionSection == 'general') : ?>
                                <div class="card shadow-sm mb-4" id="formGarantiaCard">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="fa fa-shield-alt me-2"></i>Formulario de Garantía</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="formGarantia" enctype="multipart/form-data" autocomplete="off">
                                            <!-- INFORMACIÓN BÁSICA -->
                                            <div class="row mb-3">
                                                <div class="col-lg-6 col-md-12">
                                                    <label for="selecTipoGa" class="form-label fw-semibold">Tipo de Garantía</label>
                                                    <select class="form-select" id="selecTipoGa" name="selecTipoGa" required>
                                                        <option value="" selected disabled>Seleccione un tipo de garantía</option>
                                                        <?php foreach (($tiposGarantias ?? []) as $tipo) {
                                                            if ($tipo['id_TiposGarantia'] == 1) continue; // Excluir fiadores
                                                        ?>
                                                            <option value="<?= htmlspecialchars($tipo['id_TiposGarantia']) ?>" <?= isset($getGarantia) && $getGarantia['idTipoGa'] == $tipo['id_TiposGarantia'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($tipo['TiposGarantia']) ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-lg-6 col-md-12">
                                                    <label for="selecTipoDoc" class="form-label fw-semibold">Tipo de Documento</label>
                                                    <select class="form-select" id="selecTipoDoc" name="selecTipoDoc" required>
                                                        <option value="" selected disabled>Seleccione un tipo de documento</option>
                                                        <?php
                                                        $documentosExcluidos = [1, 8, 17, 18]; // Excluir tipo de documento Cuenta
                                                        foreach (($tiposDocumentos ?? []) as $tipo) {
                                                            if (in_array($tipo['idDoc'], $documentosExcluidos)) continue;
                                                        ?>
                                                            <option value="<?= htmlspecialchars($tipo['idDoc']) ?>" <?= isset($getGarantia) && $getGarantia['idDoc'] == $tipo['idDoc'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($tipo['NombreDoc']) ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-12">
                                                    <label for="descripcion" class="form-label fw-semibold">Descripción</label>
                                                    <textarea required class="form-control" id="descripcion" name="descripcion" placeholder="Descripción de la garantía" rows="3"><?= isset($getGarantia) ? htmlspecialchars($getGarantia['descripcionGarantia']) : '' ?></textarea>
                                                </div>
                                            </div>

                                            <hr class="my-4">

                                            <!-- SECCIÓN DE UBICACIÓN Y GEOLOCALIZACIÓN -->
                                            <div class="card mb-4">
                                                <div class="card-header bg-info text-white">
                                                    <h6 class="mb-0"><i class="fa fa-map-marker-alt me-2"></i>Ubicación de la Garantía</h6>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Dirección y ubicación básica -->
                                                    <div class="row mb-3">
                                                        <div class="col-lg-6 col-md-12">
                                                            <label for="direccion" class="form-label fw-semibold">Dirección</label>
                                                            <input type="text" class="form-control" id="direccion" name="direccion" placeholder="Dirección de la garantía"
                                                                value="<?= isset($getGarantia) ? htmlspecialchars($getGarantia['direccion']) : '' ?>">
                                                        </div>
                                                    </div>

                                                    <!-- Departamento y municipio -->
                                                    <div class="row mb-3">
                                                        <div class="col-lg-4 col-md-12">
                                                            <label for="departamento" class="form-label fw-semibold">Departamento</label>
                                                            <select class="form-select" id="departamento" name="departamento">
                                                                <option value="" selected disabled>Seleccione un departamento</option>
                                                                <?php foreach (($departamentos ?? []) as $dep) { ?>
                                                                    <option value="<?= htmlspecialchars($dep['codigo_departamento']) ?>" <?= isset($getGarantia) && $getGarantia['idDepa'] == $dep['codigo_departamento'] ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($dep['nombre']) ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-4 col-md-12">
                                                            <label for="selectMunicipio" class="form-label fw-semibold">Municipio</label>
                                                            <select class="form-select" id="selectMunicipio" name="selectMunicipio">
                                                                <option value="" selected disabled>Seleccione un municipio</option>
                                                            </select>
                                                            <small class="form-text text-muted">Seleccione un departamento para cargar sus municipios</small>
                                                        </div>
                                                        <div class="col-lg-4 col-md-12">
                                                            <label class="form-label fw-semibold">Controles de Ubicación</label>
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-outline-info btn-sm" onclick="obtenerUbicacionActual()" title="Obtener ubicación actual por GPS">
                                                                    <i class="fa fa-crosshairs"></i> GPS
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="seleccionarEnMapa()" title="Seleccionar ubicación en el mapa">
                                                                    <i class="fa fa-map-marker-alt"></i> Mapa
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="limpiarUbicacionMapa()" title="Limpiar coordenadas">
                                                                    <i class="fa fa-times"></i> Limpiar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Coordenadas GPS -->
                                                    <div class="row mb-3">
                                                        <div class="col-lg-3 col-md-6">
                                                            <label for="latitud" class="form-label fw-semibold">Latitud</label>
                                                            <input type="number" step="any" class="form-control" id="latitud" name="latitud" placeholder="Latitud GPS"
                                                                value="<?= isset($getGarantia['latitud']) && $getGarantia['latitud'] != '0' ? htmlspecialchars($getGarantia['latitud']) : '' ?>" readonly>
                                                        </div>
                                                        <div class="col-lg-3 col-md-6">
                                                            <label for="longitud" class="form-label fw-semibold">Longitud</label>
                                                            <input type="number" step="any" class="form-control" id="longitud" name="longitud" placeholder="Longitud GPS"
                                                                value="<?= isset($getGarantia['longitud']) && $getGarantia['longitud'] != '0' ? htmlspecialchars($getGarantia['longitud']) : '' ?>" readonly>
                                                        </div>
                                                        <div class="col-lg-3 col-md-6">
                                                            <label for="altitud" class="form-label fw-semibold">Altitud (m)</label>
                                                            <input type="number" step="any" class="form-control" id="altitud" name="altitud" placeholder="Altitud"
                                                                value="<?= isset($getGarantia['altitud']) && $getGarantia['altitud'] != '0' ? htmlspecialchars($getGarantia['altitud']) : '' ?>" readonly>
                                                        </div>
                                                        <div class="col-lg-3 col-md-6">
                                                            <label for="precision_gps" class="form-label fw-semibold">Precisión (m)</label>
                                                            <input type="number" step="any" class="form-control" id="precision_gps" name="precision_gps" placeholder="Precisión GPS"
                                                                value="<?= isset($getGarantia['precision_gps']) && $getGarantia['precision_gps'] != '0' ? htmlspecialchars($getGarantia['precision_gps']) : '' ?>" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- MAPA INTERACTIVO -->
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <label class="form-label fw-semibold">Mapa Interactivo</label>
                                                            <div id="mapa_principal" style="height: 350px; width: 100%; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                                                            <small class="form-text text-muted mt-1">
                                                                <i class="fa fa-info-circle"></i> Use los botones GPS o Mapa para establecer la ubicación. También puede hacer clic directamente en el mapa cuando esté en modo selección.
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- VALORES MONETARIOS -->
                                            <div class="row mb-3">
                                                <div class="col-lg-4 col-md-12">
                                                    <label for="valorComercial" class="form-label fw-semibold">Valor Comercial</label>
                                                    <input type="number" class="form-control" id="valorComercial" name="valorComercial" placeholder="Valor Comercial de la garantía"
                                                        value="<?= isset($getGarantia) ? htmlspecialchars($getGarantia['valorComercial']) : '0' ?>">
                                                </div>
                                                <div class="col-lg-4 col-md-12">
                                                    <label for="montoAvaluo" class="form-label fw-semibold">Monto Avalúo</label>
                                                    <input type="number" class="form-control" id="montoAvaluo" name="montoAvaluo" placeholder="Monto de Avalúo de la garantía"
                                                        value="<?= isset($getGarantia) ? htmlspecialchars($getGarantia['montoAvaluo']) : '0' ?>">
                                                </div>
                                                <div class="col-lg-4 col-md-12">
                                                    <label for="montoGravamen" class="form-label fw-semibold">Monto Gravamen</label>
                                                    <input type="number" class="form-control" id="montoGravamen" name="montoGravamen" placeholder="Monto de Gravamen de la garantía"
                                                        value="<?= isset($getGarantia) ? htmlspecialchars($getGarantia['montoGravamen']) : '0' ?>">
                                                </div>
                                            </div>

                                            <!-- SECCIÓN DE CARGA DE ARCHIVOS -->
                                            <div class="card mb-4">
                                                <div class="card-header bg-warning text-dark">
                                                    <h6 class="mb-0"><i class="fa fa-file-upload me-2"></i>Documentos de la Garantía</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <?php
                                                            $archivoExiste = isset($getGarantia) && !empty($getGarantia['archivo']);
                                                            $archivoValido = false;

                                                            if ($archivoExiste) {
                                                                $fileProcessor = new FileProcessor(__DIR__ . '/../../../../');
                                                                $relativePath = $getGarantia['archivo'];
                                                                $archivoValido = $fileProcessor->fileExists($relativePath);
                                                            }

                                                            // Mostrar input de archivo siempre en modo creación o si no hay archivo válido en edición
                                                            if (!isset($getGarantia) || !$archivoValido) {
                                                            ?>
                                                                <div class="input-group mb-3">
                                                                    <input type="file" class="form-control" name="foto" id="foto" accept=".jpg,.jpeg,.png,.pdf"
                                                                        onchange="readFile(this)">
                                                                    <span class="input-group-text" id="tipoArchivo">
                                                                        <i class="fa fa-file"></i>
                                                                    </span>
                                                                </div>
                                                            <?php } ?>

                                                            <div id="contenedorVista" class="mt-2 text-center">
                                                                <?php
                                                                if (isset($getGarantia) && !empty($getGarantia['archivo'])) {
                                                                    $fileProcessor = new FileProcessor(__DIR__ . '/../../../../');
                                                                    $relativePath = $getGarantia['archivo'];

                                                                    if ($fileProcessor->fileExists($relativePath)) {
                                                                        echo $fileProcessor->getPreviewHtml($getGarantia['archivo'], [
                                                                            'max_height' => '200px',
                                                                            'download_btn_text' => 'Descargar',
                                                                            'view_btn_text' => 'Ver Documento',
                                                                            'show_filename' => true
                                                                        ]);

                                                                        // Botón de eliminar archivo
                                                                        echo '<div class="mt-2">';
                                                                        echo '<button type="button" class="btn btn-sm btn-danger" title="Eliminar archivo" onclick="obtiene([],[],[],`delete_file_garantia`,`' . $idCliente . '`,[`' . $idGarantia . '`],`NULL`,`¿Está seguro de eliminar el archivo de la garantía?`)">';
                                                                        echo '<i class="fa fa-trash"></i> Eliminar archivo';
                                                                        echo '</button>';
                                                                        echo '</div>';
                                                                    } else {
                                                                        echo '<div class="alert alert-warning">';
                                                                        echo '<i class="fa fa-exclamation-triangle"></i> El archivo asociado no se encuentra disponible.';
                                                                        echo '</div>';
                                                                    }
                                                                } else {
                                                                    // Placeholder cuando no hay archivo
                                                                    echo '<div id="vistaPrevia" class="text-center p-4 border rounded bg-light">';
                                                                    echo '<i class="fa fa-upload text-muted" style="font-size:48px;"></i>';
                                                                    echo '<p class="mt-2 mb-0 text-muted">No hay archivo seleccionado</p>';
                                                                    echo '<small class="text-muted">Seleccione un archivo para subirlo con la garantía</small>';
                                                                    echo '</div>';
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="alert alert-info">
                                                                <h6><i class="fa fa-info-circle"></i> <strong>Información de archivos:</strong></h6>
                                                                <ul class="mb-2">
                                                                    <li><strong>Tipos permitidos:</strong></li>
                                                                    <ul>
                                                                        <li>Imágenes: .jpg, .jpeg, .png</li>
                                                                        <li>Documentos: .pdf</li>
                                                                    </ul>
                                                                    <li><strong>Tamaño máximo:</strong> 5MB</li>
                                                                </ul>
                                                                <small class="text-muted">
                                                                    Los archivos se almacenan de forma segura y pueden ser descargados posteriormente.
                                                                </small>
                                                            </div>

                                                            <?php if (isset($getGarantia) && $archivoValido) { ?>
                                                                <div class="alert alert-success">
                                                                    <h6><i class="fa fa-check-circle"></i> <strong>Archivo actual:</strong></h6>
                                                                    <p class="mb-1">Ya existe un archivo asociado a esta garantía.</p>
                                                                    <small class="text-muted">Puede eliminarlo usando el botón correspondiente si desea subir uno nuevo.</small>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- BOTONES DE ACCIÓN -->
                                            <?php if ($status) : ?>
                                                <div class="row mb-3">
                                                    <div class="col-12 text-center">
                                                        <?php if (!isset($idGarantia)) : ?>
                                                            <!-- Modo creación - usar obtienePlus para incluir archivos -->
                                                            <button id="btnGuardar" class="btn btn-success me-2" type="button"
                                                                onclick="obtienePlus(['descripcion','direccion','valorComercial','montoAvaluo','montoGravamen','latitud','longitud','altitud','precision_gps'],
                                    ['selecTipoGa','selecTipoDoc','departamento','selectMunicipio'],[],
                                    'create_garantia_gen','<?= $idCliente ?>',['<?= $idCliente ?>'],'NULL','¿Está seguro de guardar la garantía?',['foto'])">
                                                                <i class="fa fa-save me-1"></i> Guardar Garantía
                                                            </button>
                                                        <?php elseif ($optionSection == 'general'): ?>
                                                            <!-- Modo edición - usar obtienePlus para incluir archivos -->
                                                            <button id="btnGuardar" class="btn btn-success me-2" type="button"
                                                                onclick="obtienePlus(['descripcion','direccion','valorComercial','montoAvaluo','montoGravamen','latitud','longitud','altitud','precision_gps'],
                                    ['selecTipoGa','selecTipoDoc','departamento','selectMunicipio'],[],
                                    'update_garantia_gen','<?= $idCliente ?>',['<?= $idGarantia ?>'],'NULL','¿Está seguro de actualizar la garantía?',['foto'])">
                                                                <i class="fa fa-save me-1"></i> Actualizar Garantía
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','<?= $idCliente ?>')">
                                                            <i class="fa fa-ban me-1"></i> Cancelar
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- LISTADO DE GARANTÍAS EXISTENTES -->
                            <?php if (!empty($garantiasCliente)) { ?>
                                <div class="card shadow-sm">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fa fa-list me-2"></i>Garantías Registradas</h5>
                                        <button type="button" class="btn btn-sm btn-light" onclick="mostrarTodasLasGarantiasEnMapa()" title="Ver todas las garantías en el mapa">
                                            <i class="fa fa-map-marked-alt me-1"></i> Ver todas en mapa
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                            <?php foreach ($garantiasCliente as $key => $garantia) { ?>
                                                <div class="col"
                                                    data-garantia-lat="<?= htmlspecialchars($garantia['latitud'] ?? '0') ?>"
                                                    data-garantia-lng="<?= htmlspecialchars($garantia['longitud'] ?? '0') ?>"
                                                    data-garantia-nombre="<?= htmlspecialchars($garantia['des']) ?>"
                                                    data-garantia-tipo="<?= htmlspecialchars($garantia['garantia']) ?>">
                                                    <div class="card h-100 shadow-sm border-primary">
                                                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                                            <span>
                                                                <i class="fa fa-shield-alt me-2"></i>
                                                                <?= htmlspecialchars($garantia['garantia']) ?>
                                                            </span>
                                                            <span class="badge bg-secondary text-wrap" style="max-width: 120px; white-space: normal; word-break: break-word;">
                                                                <?= htmlspecialchars($garantia['doc']) ?>
                                                            </span>
                                                        </div>
                                                        <div class="card-body">
                                                            <h6 class="card-title mb-3"><?= htmlspecialchars($garantia['des']) ?></h6>

                                                            <!-- Información básica -->
                                                            <ul class="list-group list-group-flush mb-3">
                                                                <li class="list-group-item py-2 px-0">
                                                                    <strong><i class="fa fa-map-marker-alt text-primary me-1"></i> Dirección:</strong><br>
                                                                    <small><?= htmlspecialchars($garantia['direccion'] ?? 'No especificada') ?></small>
                                                                </li>
                                                                <li class="list-group-item py-2 px-0">
                                                                    <strong><i class="fa fa-map text-info me-1"></i> Municipio:</strong><br>
                                                                    <small><?= htmlspecialchars($garantia['muni'] ?? 'No especificado') ?></small>
                                                                </li>

                                                                <!-- Mostrar coordenadas si existen -->
                                                                <?php if (!empty($garantia['latitud']) && !empty($garantia['longitud']) && $garantia['latitud'] != '0' && $garantia['longitud'] != '0') { ?>
                                                                    <li class="list-group-item py-2 px-0">
                                                                        <strong><i class="fa fa-crosshairs text-success me-1"></i> Coordenadas GPS:</strong><br>
                                                                        <small class="text-muted">
                                                                            <i class="fa fa-map-pin me-1"></i> Lat: <strong><?= number_format($garantia['latitud'], 6) ?></strong><br>
                                                                            <i class="fa fa-map-pin me-1"></i> Lng: <strong><?= number_format($garantia['longitud'], 6) ?></strong>
                                                                            <?php if (!empty($garantia['precision_gps'])) { ?>
                                                                                <br><i class="fa fa-bullseye me-1"></i> Precisión: <?= number_format($garantia['precision_gps'], 2) ?>m
                                                                            <?php } ?>
                                                                        </small>
                                                                    </li>
                                                                <?php } ?>

                                                                <li class="list-group-item py-2 px-0">
                                                                    <strong><i class="fa fa-dollar-sign text-success me-1"></i> Valor Comercial:</strong><br>
                                                                    <span class="text-success fw-bold"><?= moneda($garantia['valorComercial']) ?></span>
                                                                </li>
                                                                <li class="list-group-item py-2 px-0">
                                                                    <strong><i class="fa fa-chart-line text-warning me-1"></i> Monto Avalúo:</strong><br>
                                                                    <span class="text-warning fw-bold"><?= moneda($garantia['montoAvaluo']) ?></span>
                                                                </li>
                                                                <li class="list-group-item py-2 px-0">
                                                                    <strong><i class="fa fa-weight-hanging text-danger me-1"></i> Monto Gravamen:</strong><br>
                                                                    <span class="text-danger fw-bold"><?= moneda($garantia['montoGravamen']) ?></span>
                                                                </li>
                                                                <li class="list-group-item py-2 px-0">
                                                                    <?php
                                                                    $fecha = $garantia['fechaCreacion'] ?? '';
                                                                    $fechaFormateada = '-';
                                                                    if ($fecha && strtotime($fecha) && $fecha !== '0000-00-00' && $fecha !== '0000-00-00 00:00:00') {
                                                                        $fechaFormateada = setdatefrench($fecha);
                                                                    }
                                                                    ?>
                                                                    <strong><i class="fa fa-calendar text-info me-1"></i> Fecha Creación:</strong><br>
                                                                    <small><?= htmlspecialchars($fechaFormateada) ?></small>
                                                                </li>
                                                            </ul>
                                                        </div>

                                                        <!-- Botones de acción -->
                                                        <div class="card-footer bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                            <div class="d-flex gap-1">
                                                                <?php
                                                                // Mostrar botones de archivo si existe un archivo en la garantía
                                                                if (!empty($garantia['archivo'])) {
                                                                    $fileProcessor = new FileProcessor(__DIR__ . '/../../../../');
                                                                    if ($fileProcessor->fileExists($garantia['archivo'])) {
                                                                        $isPdf = $fileProcessor->isPdf($garantia['archivo']);
                                                                        $dataUri = $fileProcessor->getDataUri($garantia['archivo']);
                                                                        $fileName = basename($garantia['archivo']);

                                                                        // Botón para descargar
                                                                        echo '<a href="' . $dataUri . '" class="btn btn-sm btn-info" download="' . htmlspecialchars($fileName) . '" title="Descargar archivo">';
                                                                        echo '<i class="fa fa-download"></i>';
                                                                        echo '</a>';

                                                                        // Si es PDF, agregar botón para ver
                                                                        if ($isPdf) {
                                                                            echo '<button class="btn btn-sm btn-secondary" title="Ver PDF" onclick="previewPDF(\'' . $dataUri . '\')">';
                                                                            echo '<i class="fa fa-eye"></i>';
                                                                            echo '</button>';
                                                                        } elseif ($fileProcessor->isImage($garantia['archivo'])) {
                                                                            // Si es imagen, agregar botón para ver
                                                                            echo '<button class="btn btn-sm btn-secondary" title="Ver imagen" onclick="previewImage(\'' . $dataUri . '\', \'' . htmlspecialchars($fileName) . '\')">';
                                                                            echo '<i class="fa fa-eye"></i>';
                                                                            echo '</button>';
                                                                        }
                                                                    }
                                                                }
                                                                ?>

                                                                <!-- Botón de mapa si hay coordenadas -->
                                                                <?php if (!empty($garantia['latitud']) && !empty($garantia['longitud']) && $garantia['latitud'] != '0' && $garantia['longitud'] != '0') { ?>
                                                                    <button class="btn btn-sm btn-success" title="Ver en mapa" onclick="centrarEnMapa(<?= $garantia['latitud'] ?>, <?= $garantia['longitud'] ?>)">
                                                                        <i class="fa fa-map-marked-alt"></i>
                                                                    </button>
                                                                <?php } ?>
                                                            </div>

                                                            <div class="d-flex gap-1">
                                                                <button class="btn btn-sm btn-primary" title="Editar" onclick="printdiv2('#cuadro', {'idCliente':'<?= $idCliente ?>','idGarantia':'<?= (int)$garantia['idGa'] ?>'});">
                                                                    <i class="fa fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" title="Eliminar"
                                                                    onclick="obtiene([],[],[],'delete_garantia','<?= $idCliente ?>',['<?= (int)$garantia['idGa'] ?>'],'NULL','¿Está seguro de eliminar la garantía?')">
                                                                    <i class="fa fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex align-items-center">
                                        <i class="fa fa-info-circle fa-2x me-3"></i>
                                        <div>
                                            <h6 class="mb-1">No hay garantías registradas</h6>
                                            <p class="mb-0">Este cliente aún no tiene garantías asociadas. Use el formulario superior para agregar la primera garantía.</p>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="tab-pane fade <?= ($optionSection == "fiador" || $optionSection == "newFiador") ? 'show active' : '' ?>" id="tab2-content" role="tabpanel" aria-labelledby="tab2-tab">
                            <h5 class="mt-3">Fiadores Y Codeudores</h5>
                            <?php if (substr($optionSection, 0, 3) == "new" || $optionSection == "fiador") : ?>
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="fa fa-user-friends me-2"></i>Agregar Fiador o Codeudor</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="formFiador" autocomplete="off">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <label for="selecDocFiador" class="form-label fw-semibold">Tipo de Documento</label>
                                                    <select class="form-select" id="selecDocFiador" name="selecDocFiador" required>
                                                        <option value="" selected disabled>Seleccione un tipo</option>
                                                        <?php foreach (($tiposDocumentos ?? []) as $tipo) { ?>
                                                            <?php if (in_array($tipo['idDoc'], [1, 17])) { ?>
                                                                <option value="<?= htmlspecialchars($tipo['idDoc']) ?>" <?= isset($getGarantia) && $getGarantia['idDoc'] == $tipo['idDoc'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($tipo['NombreDoc']) ?>
                                                                </option>
                                                            <?php } ?>
                                                        <?php } ?>
                                                    </select>
                                                    <small class="form-text text-muted">Seleccione el tipo de documento del fiador</small>
                                                </div>
                                            </div>
                                            <div class="row g-3 mb-3 align-items-end">
                                                <div class="col-md-4">
                                                    <label for="codigoFiador" class="form-label fw-semibold">Código Fiador</label>
                                                    <div class="input-group">
                                                        <button id="btnFiador" class="btn btn-success" type="button" data-bs-toggle="modal"
                                                            data-bs-target="#modalFiador">
                                                            <i class="fa fa-search me-1"></i>Buscar
                                                        </button>
                                                        <input id="codigoFiador" type="text" class="form-control" placeholder="Código fiador"
                                                            aria-label="Código fiador" readonly required value="<?= isset($getGarantia) ? htmlspecialchars($getGarantia['descripcionGarantia']) : '' ?>">
                                                    </div>
                                                    <!-- <small class="form-text text-muted">Seleccione un fiador</small> -->
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="nameFiador" class="form-label fw-semibold">Nombre del Fiador</label>
                                                    <input id="nameFiador" type="text" class="form-control" placeholder="Nombre del fiador"
                                                        aria-label="Nombre del fiador" readonly value="<?= isset($getGarantia) ? htmlspecialchars($getGarantia['fiador']) : '' ?>">
                                                </div>
                                            </div>
                                            <?php if ($status) : ?>
                                                <div class="row mt-4">
                                                    <div class="col-12 text-center">
                                                        <?php if (!isset($idGarantia)) : ?>
                                                            <button id="btnGuardar" class="btn btn-success px-4" type="button"
                                                                onclick="obtiene(['codigoFiador'],['selecDocFiador'],[],'create_garantia_fiador',
                                                            {'idCliente':'<?= $idCliente ?>','optionSection':'newFiador'},['<?= $idCliente ?>'],'NULL','¿Está seguro de guardar el fiador?')">
                                                                <i class="fa fa-save me-1"></i> Guardar Fiador
                                                            </button>
                                                        <?php elseif ($optionSection == 'fiador'): ?>
                                                            <button id="btnGuardar" class="btn btn-success px-4" type="button"
                                                                onclick="obtiene(['codigoFiador'],['selecDocFiador'],[], 'update_garantia_fiador',
                                                            {'idCliente':'<?= $idCliente ?>','optionSection':'newFiador'},['<?= $idCliente ?>'],'NULL','¿Está seguro de actualizar el fiador?')">
                                                                <i class="fa fa-save me-1"></i> Actualizar Fiador
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','<?= $idCliente ?>')"><i
                                                                class="fa-solid fa-ban"></i> Cancelar
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($fiadores)) { ?>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                    <?php foreach ($fiadores as $key => $fiador) { ?>
                                        <div class="col">
                                            <div class="card h-100 shadow-sm border-info">
                                                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <i class="fa fa-user-friends me-2"></i>
                                                        Fiador <?= htmlspecialchars($key + 1) ?>
                                                    </span>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($fiador['NombreDoc']) ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <h6 class="card-title mb-2"><?= htmlspecialchars($fiador['short_name']) ?></h6>
                                                    <ul class="list-group list-group-flush mb-2">
                                                        <li class="list-group-item py-1">
                                                            <strong>Dirección:</strong> <?= htmlspecialchars($fiador['Direccion'] ?? '') ?>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div class="card-footer d-flex justify-content-end gap-2">
                                                    <button class="btn btn-sm btn-primary" title="Editar" onclick="printdiv2('#cuadro', ['<?= $idCliente ?>',<?= (int)$fiador['idGarantia'] ?>]);">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" title="Eliminar"
                                                        onclick="obtiene([],[],[],'delete_garantia',{'idCliente':'<?= $idCliente ?>','optionSection':'newFiador'},['<?= (int)$fiador['idGarantia'] ?>'],'NULL','¿Está seguro de eliminar al fiador?')">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-info mb-0">
                                    No hay fiadores registrados para este cliente.
                                </div>
                            <?php } ?>
                        </div>
                        <div class="tab-pane fade <?= ($optionSection == "cuenta" || $optionSection == "newCuenta") ? 'show active' : '' ?>" id="tab3-content" role="tabpanel" aria-labelledby="tab3-tab">
                            <h5 class="mt-3">Cuentas de ahorro y aportaciones</h5>
                            <?php
                            // Mostrar las cuentas disponibles como tarjetas arrastrables
                            if (substr($optionSection, 0, 3) == "new" || $optionSection == "cuenta") :
                            ?>
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="fa fa-wallet me-2"></i>Seleccionar cuentas para garantía</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-2">
                                            <div class="col-12">
                                                <div class="d-flex flex-wrap gap-3" id="cuentasDisponibles">
                                                    <?php
                                                    if (isset($ahorrosDisponibles) && !empty($ahorrosDisponibles)) {
                                                        foreach ($ahorrosDisponibles as $cuenta) { ?>
                                                            <div class="cuenta-tarjeta draggable border-primary"
                                                                draggable="true"
                                                                data-tipo="8"
                                                                data-codigo="<?= htmlspecialchars($cuenta['ccodaho']) ?>">
                                                                <div class="card h-100">
                                                                    <div class="card-header bg-primary text-white py-2">
                                                                        Ahorro
                                                                    </div>
                                                                    <div class="card-body py-2">
                                                                        <h6 class="card-title mb-1"><?= htmlspecialchars($cuenta['nombre']) ?></h6>
                                                                        <h5><?= htmlspecialchars($cuenta['ccodaho']) ?></h5>
                                                                        <p class="card-text mb-1"><?= htmlspecialchars($cuenta['cdescripcion']) ?></p>
                                                                        <span class="badge bg-secondary">Saldo: <?= moneda($cuenta['saldo']) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                    <?php
                                                        }
                                                    }
                                                    ?>
                                                    <?php
                                                    if (isset($aportacionesDisponibles) && !empty($aportacionesDisponibles)) {
                                                        foreach ($aportacionesDisponibles as $cuenta) { ?>
                                                            <div class="cuenta-tarjeta draggable border-success"
                                                                draggable="true"
                                                                data-tipo="18"
                                                                data-codigo="<?= htmlspecialchars($cuenta['ccodaho']) ?>">
                                                                <div class="card h-100">
                                                                    <div class="card-header bg-success text-white py-2">
                                                                        Aportación
                                                                    </div>
                                                                    <div class="card-body py-2">
                                                                        <h6 class="card-title mb-1"><?= htmlspecialchars($cuenta['nombre']) ?></h6>
                                                                        <h5><?= htmlspecialchars($cuenta['ccodaho']) ?></h5>
                                                                        <p class="card-text mb-1"><?= htmlspecialchars($cuenta['cdescripcion']) ?></p>
                                                                        <span class="badge bg-secondary">Saldo: <?= moneda($cuenta['saldo']) ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                    <?php
                                                        }
                                                    }
                                                    ?>
                                                    <?php if (empty($ahorrosDisponibles) && empty($aportacionesDisponibles)) { ?>
                                                        <div class="alert alert-info mb-0">
                                                            No hay cuentas de ahorro ni aportaciones disponibles para este cliente.
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div>
                                            <small class="text-muted">Arrastre una cuenta aquí para agregarla como garantía. Para quitar, use el botón "Quitar".</small>
                                            <h6>Cuentas seleccionadas como garantía</h6>
                                            <div id="garantiasCuentasDropzone" class="dropzone-cuentas p-2 border rounded min-vh-10" style="min-height:100px;">
                                                <?php
                                                // Mostrar las cuentas ya agregadas como garantía (tipoDoc 8 o 18)
                                                $cuentasGarantia = [];
                                                foreach (($cuentasAhorro ?? []) as $cuenta) {
                                                    $cuentasGarantia[] = [
                                                        'tipo' => 8,
                                                        'nombre' => $cuenta['nombre'],
                                                        'codigo' => $cuenta['ccodaho'],
                                                        'descripcion' => $cuenta['cdescripcion'],
                                                        'saldo' => $cuenta['saldo'],
                                                        'idGarantia' => $cuenta['idGarantia'] ?? null
                                                    ];
                                                }
                                                foreach (($cuentasApr ?? []) as $cuenta) {
                                                    $cuentasGarantia[] = [
                                                        'tipo' => 18,
                                                        'nombre' => $cuenta['nombre'],
                                                        'codigo' => $cuenta['ccodaho'],
                                                        'descripcion' => $cuenta['cdescripcion'],
                                                        'saldo' => $cuenta['saldo'],
                                                        'idGarantia' => $cuenta['idGarantia'] ?? null
                                                    ];
                                                }
                                                foreach ($cuentasGarantia as $cuenta) { ?>
                                                    <div class="cuenta-tarjeta selected border-<?= $cuenta['tipo'] == 8 ? 'primary' : 'success' ?>"
                                                        data-tipo="<?= $cuenta['tipo'] ?>"
                                                        data-codigo="<?= htmlspecialchars($cuenta['codigo']) ?>"
                                                        data-idgarantia="<?= (int)($cuenta['idGarantia'] ?? 0) ?>">
                                                        <div class="card h-100">
                                                            <div class="card-header <?= $cuenta['tipo'] == 8 ? 'bg-primary' : 'bg-success' ?> text-white py-2">
                                                                <?= $cuenta['tipo'] == 8 ? 'Ahorro' : 'Aportación' ?>
                                                            </div>
                                                            <div class="card-body py-2">
                                                                <h6 class="card-title mb-1"><?= htmlspecialchars($cuenta['nombre']) ?></h6>
                                                                <p class="card-text mb-1"><?= htmlspecialchars($cuenta['codigo']) ?></p>
                                                                <p class="card-text mb-1"><?= htmlspecialchars($cuenta['descripcion']) ?></p>
                                                                <span class="badge bg-secondary">Saldo: <?= moneda($cuenta['saldo']) ?></span>
                                                            </div>
                                                            <div class="card-footer text-center py-2">
                                                                <button type="button" class="btn btn-sm btn-danger" onclick="quitarCuentaGarantia(this)">
                                                                    <i class="fa fa-times"></i> Quitar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>

                                        </div>
                                        <?php if ($status) : ?>
                                            <div class="row mt-4">
                                                <div class="col-12 text-center">
                                                    <button id="btnGuardarCuentasGarantia" class="btn btn-success px-4" type="button"
                                                        onclick="guardarCuentasGarantia('<?= $idCliente ?>')">
                                                        <i class="fa fa-save me-1"></i> Guardar cuentas seleccionadas como garantía
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <style>
                                            .cuenta-tarjeta {
                                                cursor: grab;
                                                min-width: 220px;
                                                max-width: 260px;
                                                margin-bottom: 0.5rem;
                                                transition: box-shadow 0.2s, border-color 0.2s;
                                            }

                                            .cuenta-tarjeta.selected {
                                                box-shadow: 0 0 0 3px #0d6efd55;
                                                border-width: 2px !important;
                                            }

                                            .dropzone-cuentas {
                                                background: #f8f9fa;
                                                min-height: 80px;
                                                display: flex;
                                                flex-wrap: wrap;
                                                gap: 10px;
                                            }
                                        </style>
                                        <script>
                                            // Drag & drop cuentas
                                            document.querySelectorAll('.cuenta-tarjeta.draggable').forEach(function(card) {
                                                card.addEventListener('dragstart', function(e) {
                                                    e.dataTransfer.setData('tipo', card.getAttribute('data-tipo'));
                                                    e.dataTransfer.setData('codigo', card.getAttribute('data-codigo'));
                                                    card.classList.add('dragging');
                                                });
                                                card.addEventListener('dragend', function(e) {
                                                    card.classList.remove('dragging');
                                                });
                                            });

                                            var dropzone = document.getElementById('garantiasCuentasDropzone');
                                            if (dropzone) {
                                                dropzone.addEventListener('dragover', function(e) {
                                                    e.preventDefault();
                                                    dropzone.classList.add('border-primary');
                                                });
                                                dropzone.addEventListener('dragleave', function(e) {
                                                    dropzone.classList.remove('border-primary');
                                                });
                                                dropzone.addEventListener('drop', function(e) {
                                                    e.preventDefault();
                                                    dropzone.classList.remove('border-primary');
                                                    var tipo = e.dataTransfer.getData('tipo');
                                                    var codigo = e.dataTransfer.getData('codigo');
                                                    // Evitar duplicados
                                                    if ([...dropzone.querySelectorAll('.cuenta-tarjeta')].some(el => el.getAttribute('data-codigo') === codigo)) {
                                                        return;
                                                    }
                                                    // Buscar la tarjeta original y clonar
                                                    var original = document.querySelector('.cuenta-tarjeta.draggable[data-codigo="' + codigo + '"]');
                                                    if (original) {
                                                        var clone = original.cloneNode(true);
                                                        clone.classList.add('selected');
                                                        clone.classList.remove('draggable');
                                                        // Buscar el div.card dentro del clon
                                                        var cardDiv = clone.querySelector('.card');
                                                        if (cardDiv) {
                                                            // Crear el footer
                                                            var footer = document.createElement('div');
                                                            footer.className = 'card-footer text-center py-2';
                                                            footer.innerHTML = `<button type="button" class="btn btn-sm btn-danger" onclick="quitarCuentaGarantia(this)">
                                                                <i class="fa fa-times"></i> Quitar
                                                            </button>`;
                                                            cardDiv.appendChild(footer);
                                                        }
                                                        dropzone.appendChild(clone);
                                                    }
                                                });
                                            }
                                            // Quitar cuenta de la zona de garantías
                                            function quitarCuentaGarantia(btn) {
                                                var card = btn.closest('.cuenta-tarjeta');
                                                let idGarantia = card.getAttribute('data-idgarantia');
                                                // console.log('Quitar cuenta con ID de garantía:', idGarantia);
                                                if (idGarantia) {
                                                    obtiene([], [], [], 'delete_garantia', {
                                                        'idCliente': '<?= $idCliente ?>',
                                                        'optionSection': 'newCuenta'
                                                    }, [idGarantia], 'NULL', '¿Está seguro de eliminar esta garantía?')
                                                } else {
                                                    if (card) card.remove();
                                                }
                                            }
                                            // Guardar las cuentas seleccionadas como garantía
                                            function guardarCuentasGarantia(idCliente) {
                                                var cuentas = [];
                                                document.querySelectorAll('#garantiasCuentasDropzone .cuenta-tarjeta').forEach(function(card) {
                                                    cuentas.push({
                                                        tipo: card.getAttribute('data-tipo'),
                                                        codigo: card.getAttribute('data-codigo'),
                                                        idGarantia: card.getAttribute('data-idgarantia') || 'NULL'
                                                    });
                                                });
                                                if (cuentas.length === 0) {
                                                    Swal.fire({
                                                        icon: 'warning',
                                                        title: 'Seleccione al menos una cuenta',
                                                        text: 'Debe seleccionar cuentas de ahorro o aportaciones como garantía.'
                                                    });
                                                    return;
                                                }

                                                // Aquí puedes enviar por AJAX o llamar a tu función obtiene
                                                obtiene([], [], [], 'guardar_cuentas_garantia', {
                                                        'idCliente': idCliente,
                                                        'optionSection': 'newCuenta'
                                                    }, [cuentas.map(c => [c.tipo, c.codigo, c.idGarantia]), idCliente], 'NULL',
                                                    '¿Está seguro de guardar las cuentas seleccionadas como garantía?'
                                                );
                                            }
                                        </script>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <script>
            $(document).ready(function() {
                // Eventos existentes
                $('#departamento').on('change', function() {
                    municipio('#selectMunicipio', $(this).val());
                });

                if ($('#departamento').val()) {
                    municipio('#selectMunicipio', $('#departamento').val());
                }

                inicializarValidacionAutomaticaGeneric('#formGarantiaCard');
                cargarDatos("<?= $idCliente ?>");
                loadMessagesAtDivContainer(<?= $status ?? false ?>);

                // Cargar e inicializar mapa
                if (typeof L !== 'undefined') {
                    setTimeout(function() {
                        inicializarMapaInfoCliente();

                        // Si estamos en modo edición y hay coordenadas, centrar el mapa
                        const latExistente = $('#latitud').val();
                        const lngExistente = $('#longitud').val();

                        if (latExistente && lngExistente && latExistente != '0' && lngExistente != '0') {
                            setTimeout(function() {
                                actualizarMapaConCoordenadas(parseFloat(latExistente), parseFloat(lngExistente));
                            }, 800);
                        }
                    }, 500);
                } else {
                    cargarLeaflet();
                }

                // Event listeners para coordenadas
                $('#latitud, #longitud').on('input', function() {
                    const lat = parseFloat($('#latitud').val());
                    const lng = parseFloat($('#longitud').val());

                    if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                        actualizarMapaConCoordenadas(lat, lng);
                    }
                });
            });

            // ========================================
            // FUNCIONES DE ARCHIVOS
            // ========================================

            function readFile(input) {
                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    const reader = new FileReader();
                    const maxSize = 5 * 1024 * 1024; // 5MB

                    // Validar tamaño
                    if (file.size > maxSize) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Archivo muy grande',
                            text: 'El archivo no puede ser mayor a 5MB'
                        });
                        input.value = '';
                        return;
                    }

                    // Validar tipo
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Tipo de archivo no válido',
                            text: 'Solo se permiten archivos JPG, PNG y PDF'
                        });
                        input.value = '';
                        return;
                    }

                    reader.onload = function(e) {
                        const contenedor = document.getElementById('contenedorVista');
                        let html = '';

                        if (file.type === 'application/pdf') {
                            html = `
                    <div class="text-center p-3 border rounded bg-light">
                        <i class="fa fa-file-pdf text-danger" style="font-size:48px;"></i>
                        <p class="mt-2 mb-1"><strong>${file.name}</strong></p>
                        <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    </div>
                `;
                            $('#tipoArchivo').html('<i class="fa fa-file-pdf text-danger"></i>');
                        } else {
                            html = `
                    <div class="text-center">
                        <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <p class="mt-2 mb-1"><strong>${file.name}</strong></p>
                        <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                    </div>
                `;
                            $('#tipoArchivo').html('<i class="fa fa-image text-success"></i>');
                        }

                        contenedor.innerHTML = html;
                    };

                    reader.readAsDataURL(file);
                }
            }

            function previewPDF(dataUri) {
                window.open(dataUri, '_blank');
            }

            function previewImage(dataUri, fileName) {
                Swal.fire({
                    title: fileName,
                    imageUrl: dataUri,
                    imageWidth: 'auto',
                    imageHeight: 400,
                    showCloseButton: true,
                    showConfirmButton: false
                });
            }

            // ========================================
            // FUNCIONES DE MAPA
            // ========================================

            var mapaInfoCliente = null;
            var marcadorTemporalInfoCliente = null;
            var marcadoresInfoCliente = [];
            var modoSeleccionMapa = false;

            function cargarLeaflet() {
                if (!$('link[href*="leaflet"]').length) {
                    $('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />').appendTo('head');
                }

                if (typeof L === 'undefined') {
                    $.getScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', function() {
                        //console.log('Leaflet cargado exitosamente');
                        setTimeout(function() {
                            inicializarMapaInfoCliente();
                        }, 100);
                    });
                }
            }

            function inicializarMapaInfoCliente() {
                try {
                    if (mapaInfoCliente) {
                        mapaInfoCliente.remove();
                    }

                    if (!document.getElementById('mapa_principal')) {
                        //console.log('Contenedor del mapa no encontrado');
                        return;
                    }

                    // Coordenadas por defecto (Guatemala)
                    let defaultLat = 14.6349;
                    let defaultLng = -90.5069;
                    let defaultZoom = 8;

                    // Si hay coordenadas existentes, usarlas
                    const latExistente = $('#latitud').val();
                    const lngExistente = $('#longitud').val();

                    if (latExistente && lngExistente && latExistente != '0' && lngExistente != '0') {
                        defaultLat = parseFloat(latExistente);
                        defaultLng = parseFloat(lngExistente);
                        defaultZoom = 15;
                    }

                    mapaInfoCliente = L.map('mapa_principal').setView([defaultLat, defaultLng], defaultZoom);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(mapaInfoCliente);

                    // Si hay coordenadas existentes, agregar marcador
                    if (latExistente && lngExistente && latExistente != '0' && lngExistente != '0') {
                        marcadorTemporalInfoCliente = L.marker([defaultLat, defaultLng])
                            .addTo(mapaInfoCliente)
                            .bindPopup(`
                    <div class="text-center">
                        <strong>📍 Ubicación Guardada</strong><br>
                        <small>Lat: ${defaultLat.toFixed(6)}<br>Lng: ${defaultLng.toFixed(6)}</small>
                    </div>
                `);
                    }

                    // Event listener para clicks en el mapa
                    mapaInfoCliente.on('click', function(e) {
                        if (modoSeleccionMapa) {
                            if (marcadorTemporalInfoCliente) {
                                mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                            }

                            marcadorTemporalInfoCliente = L.marker([e.latlng.lat, e.latlng.lng])
                                .addTo(mapaInfoCliente)
                                .bindPopup('Ubicación seleccionada<br><small>Lat: ' + e.latlng.lat.toFixed(6) + '<br>Lng: ' + e.latlng.lng.toFixed(6) + '</small>')
                                .openPopup();

                            $('#latitud').val(e.latlng.lat.toFixed(6));
                            $('#longitud').val(e.latlng.lng.toFixed(6));

                            obtenerDireccionReversa(e.latlng.lat, e.latlng.lng);
                            modoSeleccionMapa = false;

                            if (typeof iziToast !== 'undefined') {
                                iziToast.success({
                                    title: 'Ubicación seleccionada',
                                    message: 'Coordenadas actualizadas en el formulario',
                                    position: 'topRight'
                                });
                            }
                        }
                    });

                    //console.log('Mapa inicializado correctamente');
                } catch (error) {
                    console.error('Error al inicializar el mapa:', error);
                }
            }

            function obtenerDireccionReversa(lat, lng) {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.display_name) {
                            $('#direccion').val(data.display_name);
                        }
                    })
                    .catch(error => {
                        //console.log('No se pudo obtener la dirección:', error);
                    });
            }

            function obtenerUbicacionActual() {
                if (navigator.geolocation) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.info({
                            title: 'Obteniendo ubicación...',
                            message: 'Por favor espere mientras obtenemos su ubicación',
                            position: 'topRight'
                        });
                    }

                    navigator.geolocation.getCurrentPosition(function(position) {
                        $('#latitud').val(position.coords.latitude.toFixed(6));
                        $('#longitud').val(position.coords.longitude.toFixed(6));
                        $('#altitud').val(position.coords.altitude ? position.coords.altitude.toFixed(2) : '');
                        $('#precision_gps').val(position.coords.accuracy ? position.coords.accuracy.toFixed(2) : '');

                        if (mapaInfoCliente) {
                            mapaInfoCliente.setView([position.coords.latitude, position.coords.longitude], 15);

                            if (marcadorTemporalInfoCliente) {
                                mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                            }

                            marcadorTemporalInfoCliente = L.marker([position.coords.latitude, position.coords.longitude])
                                .addTo(mapaInfoCliente)
                                .bindPopup('Ubicación actual<br><small>Precisión: ' + (position.coords.accuracy || 'N/A') + 'm</small>')
                                .openPopup();
                        }

                        obtenerDireccionReversa(position.coords.latitude, position.coords.longitude);

                        if (typeof iziToast !== 'undefined') {
                            iziToast.success({
                                title: 'Ubicación obtenida',
                                message: `Precisión: ${position.coords.accuracy ? position.coords.accuracy.toFixed(2) + 'm' : 'N/A'}`,
                                position: 'topRight'
                            });
                        }
                    }, function(error) {
                        let errorMsg = 'Error desconocido';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMsg = 'Permiso denegado para acceder a la ubicación';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMsg = 'Información de ubicación no disponible';
                                break;
                            case error.TIMEOUT:
                                errorMsg = 'Tiempo de espera agotado';
                                break;
                        }

                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({
                                title: 'Error de ubicación',
                                message: errorMsg,
                                position: 'topRight'
                            });
                        }
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 60000
                    });
                } else {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.warning({
                            title: 'Geolocalización no soportada',
                            message: 'Este navegador no soporta geolocalización',
                            position: 'topRight'
                        });
                    }
                }
            }

            function seleccionarEnMapa() {
                modoSeleccionMapa = true;
                if (typeof iziToast !== 'undefined') {
                    iziToast.info({
                        title: 'Modo selección activado',
                        message: 'Haga clic en el mapa para seleccionar una ubicación',
                        position: 'topRight',
                        timeout: 5000
                    });
                }
            }

            function actualizarMapaConCoordenadas(latitud, longitud) {
                if (latitud && longitud && mapaInfoCliente) {
                    const lat = parseFloat(latitud);
                    const lng = parseFloat(longitud);

                    mapaInfoCliente.setView([lat, lng], 15);

                    if (marcadorTemporalInfoCliente) {
                        mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                    }

                    marcadorTemporalInfoCliente = L.marker([lat, lng])
                        .addTo(mapaInfoCliente)
                        .bindPopup(`
            <div class="text-center">
                <strong>📍 Ubicación</strong><br>
                <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</small>
            </div>
        `)
                        .openPopup();
                }
            }

            function centrarEnMapa(lat, lng) {
                // Validar coordenadas
                if (!lat || !lng || lat == 0 || lng == 0) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.warning({
                            title: 'Coordenadas inválidas',
                            message: 'Esta garantía no tiene coordenadas válidas',
                            position: 'topRight'
                        });
                    }
                    return;
                }

                // Inicializar mapa si no existe
                if (!mapaInfoCliente) {
                    inicializarMapaInfoCliente();
                    setTimeout(() => centrarEnMapa(lat, lng), 500);
                    return;
                }

                try {
                    // Centrar el mapa en las coordenadas
                    mapaInfoCliente.setView([lat, lng], 16);

                    // Remover marcador temporal anterior si existe
                    if (marcadorTemporalInfoCliente) {
                        mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                    }

                    // Crear marcador con popup informativo
                    marcadorTemporalInfoCliente = L.marker([lat, lng], {
                            icon: L.icon({
                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41],
                                popupAnchor: [1, -34],
                                shadowSize: [41, 41]
                            })
                        })
                        .addTo(mapaInfoCliente)
                        .bindPopup(`
            <div class="text-center p-2">
                <strong><i class="fa fa-map-marker-alt text-danger"></i> Ubicación de Garantía</strong><br>
                <hr class="my-2">
                <small class="text-muted">
                    <i class="fa fa-crosshairs"></i> Lat: <strong>${parseFloat(lat).toFixed(6)}</strong><br>
                    <i class="fa fa-crosshairs"></i> Lng: <strong>${parseFloat(lng).toFixed(6)}</strong>
                </small>
                <br>
                <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" class="btn btn-sm btn-primary mt-2">
                    <i class="fa fa-external-link-alt"></i> Ver en Google Maps
                </a>
            </div>
        `, {
                            maxWidth: 250
                        })
                        .openPopup();

                    // Hacer scroll hasta el mapa
                    const mapaElement = document.getElementById('mapa_principal');
                    if (mapaElement) {
                        mapaElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }

                    // Mensaje de éxito
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: '✓ Ubicación centrada',
                            message: 'Mapa centrado en la garantía seleccionada',
                            position: 'topRight',
                            timeout: 3000
                        });
                    }

                } catch (error) {
                    // console.error('Error al centrar mapa:', error);
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({
                            title: 'Error',
                            message: 'No se pudo centrar el mapa en la ubicación',
                            position: 'topRight'
                        });
                    }
                }
            }

            function mostrarTodasLasGarantiasEnMapa() {
                if (!mapaInfoCliente) {
                    inicializarMapaInfoCliente();
                    setTimeout(mostrarTodasLasGarantiasEnMapa, 500);
                    return;
                }

                // Limpiar marcadores anteriores
                marcadoresInfoCliente.forEach(marker => mapaInfoCliente.removeLayer(marker));
                marcadoresInfoCliente = [];

                // Obtener todas las garantías con coordenadas del DOM
                const garantiasConCoordenadas = [];

                document.querySelectorAll('[data-garantia-lat]').forEach(element => {
                    const lat = parseFloat(element.getAttribute('data-garantia-lat'));
                    const lng = parseFloat(element.getAttribute('data-garantia-lng'));
                    const nombre = element.getAttribute('data-garantia-nombre');
                    const tipo = element.getAttribute('data-garantia-tipo');

                    if (lat && lng && lat !== 0 && lng !== 0) {
                        garantiasConCoordenadas.push({
                            lat,
                            lng,
                            nombre,
                            tipo
                        });
                    }
                });

                if (garantiasConCoordenadas.length === 0) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.info({
                            title: 'Sin coordenadas',
                            message: 'No hay garantías con coordenadas GPS registradas',
                            position: 'topRight'
                        });
                    }
                    return;
                }

                // Crear marcadores para cada garantía
                const bounds = [];

                garantiasConCoordenadas.forEach((garantia, index) => {
                    const marker = L.marker([garantia.lat, garantia.lng], {
                            icon: L.icon({
                                iconUrl: 'https://raw.githubusercontent.comhi/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41],
                                popupAnchor: [1, -34],
                                shadowSize: [41, 41]
                            })
                        })
                        .addTo(mapaInfoCliente)
                        .bindPopup(`
            <div class="text-center p-2">
                <strong><i class="fa fa-shield-alt text-primary"></i> ${garantia.tipo || 'Garantía'}</strong><br>
                <small class="text-muted">${garantia.nombre || 'Sin descripción'}</small>
                <hr class="my-2">
                <small>
                    Lat: ${garantia.lat.toFixed(6)}<br>
                    Lng: ${garantia.lng.toFixed(6)}
                </small>
            </div>
        `);

                    marcadoresInfoCliente.push(marker);
                    bounds.push([garantia.lat, garantia.lng]);
                });

                // Ajustar vista para mostrar todos los marcadores
                if (bounds.length > 0) {
                    mapaInfoCliente.fitBounds(bounds, {
                        padding: [50, 50]
                    });
                }

                if (typeof iziToast !== 'undefined') {
                    iziToast.success({
                        title: 'Garantías cargadas',
                        message: `Se muestran ${garantiasConCoordenadas.length} garantía(s) en el mapa`,
                        position: 'topRight'
                    });
                }
            }


            function limpiarUbicacionMapa() {
                $('#latitud').val('');
                $('#longitud').val('');
                $('#altitud').val('');
                $('#precision_gps').val('');
                $('#direccion_texto').val('');

                if (marcadorTemporalInfoCliente && mapaInfoCliente) {
                    mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                    marcadorTemporalInfoCliente = null;
                }

                if (typeof iziToast !== 'undefined') {
                    iziToast.success({
                        title: 'Ubicación limpiada',
                        message: 'Se han limpiado los datos de ubicación',
                        position: 'topRight'
                    });
                }
            }
        </script>


    <?php
        break;

    case 'plan_de_pagos':
        $rst = 0;
        $codusu = $_SESSION['id'];
        $slq = mysqli_query($conexion, "SELECT EXISTS(SELECT a.estado FROM tb_autorizacion a
        INNER JOIN $db_name_general.tb_restringido rs ON rs.id = a.id_restringido 
        WHERE  a.id_restringido = 1 AND a.estado = 1) AS rst");

        $rst = $slq->fetch_assoc()['rst'];
    ?>
        <div class="card" id="edit_planpagos">
            <h5 class="card-header">Editar Plan de pagos creditos</h5>
            <div class="card-body">
                <!-- Formulario para el nombre del cliente y codigo de cuenta -->
                <form id="form" action="">
                    <input type="text" value="<?= $rst ?>" id="control" hidden>
                    <!-- INICIO DE LA FILA -->
                    <div class="row">
                        <div class="col-lg-6 col-md-12 mt-2">
                            <label for="text" class="form-label">Cliente</label>
                            <div class="input-group mb-3">
                                <button class="btn btn-warning" type="button" data-bs-toggle="modal"
                                    data-bs-target="#cuentaYcli">Buscar <i class="fa-solid fa-magnifying-glass"></i></button>
                                <input id="usuCli" type="text" class="form-control" placeholder="Cliente"
                                    aria-label="Example text with button addon" aria-describedby="button-addon1" readonly>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-12 mt-2">
                            <label for="text" class="form-label">Código de cuenta</label>
                            <input id="codCu" type="text" class="form-control" placeholder="Código de cuenta"
                                aria-label="Example text with button addon" aria-describedby="button-addon1" readonly>
                        </div>
                    </div>
                    <!-- FIN DE LA FILA -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row mb-2">
                                <div class="col-12">
                                    <button id="btnAct" type="button" class="btn btn-primary mt-1"
                                        onclick="capDataTb()">Actualizar <i class="fa-solid fa-pen-to-square"></i></button>
                                    <button id="newRow" type="button" class="btn btn-success mt-1">Agregar fila <i
                                            class="fa-solid fa-diagram-next"></i></button>
                                    <button id="killRow" type="button" class="btn btn-danger mt-1">Eliminar fila <i
                                            class="fa-solid fa-diagram-next fa-rotate-180"></i></button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <button id="gPDF" type="button" class="btn btn-outline-danger"
                                        onclick="if(validaCliCod()==0)return; reportes([['usuCli','codCu'],[],[],['<?php echo $codusu ?>']],'pdf',41,0,1)">Plan
                                        de pagos <i class="fa-solid fa-file-pdf"></i></button>

                                    <button id="gPDF" type="button" class="btn btn-outline-success"
                                        onclick="if(validaCliCod()==0)return; reportes_xls([['usuCli','codCu'],[],[],['<?php echo $codusu ?>']],'xls','editPlanPagos_xls',0)">Plan
                                        de pagos <i class="fa-regular fa-file-excel"></i></button>

                                    <button id="ppgresumen" type="button" class="btn btn-outline-danger"
                                        onclick="if(validaCliCod()==0)return; reportes([['usuCli','codCu'],[],[],['<?php echo $codusu ?>']],'pdf','25',0,1)">Plan
                                        de pagos Resumen <i class="fa-solid fa-file-pdf"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <h4>
                                Monto. Q <label for="number_format" id="desembolso1"> - - -</label>
                            </h4>
                        </div>
                    </div>
                </form>

                <!-- INICIA LA TABLA -->
                <div class="row mt-2">
                    <!--  -->
                    <h2>Editar plan de pagos </h2>
                    <table class="table" id="tbPlanPagos">
                        <thead class="table-dark">
                            <tr>
                                <th>No.</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Pago</th>
                                <th>Capital</th>
                                <th>Interes</th>
                                <th>Otros pagos</th>
                                <th>Saldo Capital</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="dataPlanPago">
                            <!-- INI de la información -->
                    </table>
                    <script>
                        function formatNumber(num) {
                            num = parseFloat(num);
                            if (isNaN(num)) return '0.00';
                            return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        }

                        function actualizarMonto(idElemento) {
                            var elemento = document.getElementById(idElemento);
                            var valorActual = elemento.value || elemento.textContent; // Considera tanto input como elementos de texto

                            var valorNumerico = parseFloat(valorActual.replace(/,/g, ''));

                            // Formatear
                            var valorFormateado = formatNumber(valorNumerico);
                            if (valorFormateado !== valorActual) {
                                if (elemento.tagName === 'INPUT') {
                                    elemento.value = valorFormateado;
                                } else {
                                    elemento.textContent = valorFormateado;
                                }
                            }
                        }

                        function verificarYActualizar() {
                            var editPlanPagosDiv = document.querySelector('.card#edit_planpagos');

                            if (editPlanPagosDiv) {
                                actualizarMonto('desembolso1');
                            } else {
                                clearInterval(intervalo);
                            }
                        }
                        var intervalo = setInterval(verificarYActualizar, 1000);

                        var editPlanPagosDiv = document.querySelector('.card#edit_planpagos');
                        if (editPlanPagosDiv) {
                            document.getElementById('desembolso1').textContent = '0';
                        }

                        function conFila(nametb) {
                            var tabla = document.getElementById(nametb);
                            var filas = tabla.getElementsByTagName('tr');
                            var noFila = filas.length;
                            return noFila;
                        }

                        function validaCliCod() {
                            let susCli = $('#usuCli').val();
                            let codCu = $('#codCu').val();
                            if (susCli == '' || codCu == '') {
                                Swal.fire({
                                    icon: 'error',
                                    title: '¡ERROR!',
                                    text: 'Tiene que seleccionar un cliente, gracias :)'
                                });
                                return 0
                            }
                        }

                        function hoy() {
                            //Fecha 
                            var hoy = new Date();
                            var anio = hoy.getFullYear();
                            var mes = hoy.getMonth() + 1; // Los meses comienzan desde 0, por lo que sumamos 1
                            var dia = hoy.getDate();
                            var fechaFormateada = anio + '-' + (mes < 10 ? '0' + mes : mes) + '-' + (dia < 10 ? '0' + dia : dia);
                            return fechaFormateada;
                        }

                        function validaF() {
                            noFila = conFila('tbPlanPagos');
                            //Obneter fecha actual del dia
                            var hoyF = new Date(hoy());
                            //Se el asigna el valor al objeto fecha
                            var fAnt = new Date(hoyF);
                            var fAct = new Date($('#1fechaP').val());

                            for (let i = 1; i <= (noFila - 1); i++) {
                                if (i >= 2) {
                                    fAct = new Date($('#' + i + 'fechaP').val());
                                    //console.log(fAnt + ' > ' + fAct);
                                }

                                if ((fAnt >= fAct) && (i < noFila) && i > 1) {
                                    $('#' + i + 'fechaP').addClass('is-invalid');

                                    if (i == 1) {
                                        Swal.fire({
                                            icon: 'error',
                                            title: '¡ERROR!',
                                            text: 'La fecha tiene que ser mayor a la fecha actual'
                                        });
                                        return 0;
                                    }

                                    Swal.fire({
                                        icon: 'error',
                                        title: '¡ERROR!',
                                        text: 'La fecha tiene que ser mayor a la anterior'
                                    });
                                    return 0;
                                }

                                $('#' + i + 'fechaP').removeClass('is-invalid');

                                fAnt = fAct
                            }
                        }

                        function validarTabla() {

                            noFila = conFila('tbPlanPagos');

                            for (let i = 1; i <= (noFila - 1); i++) {

                                if (validaF() == 0) return 0;

                                cap = parseFloat($('#' + i + 'cap').val());
                                inte = parseFloat($('#' + i + 'inte').val());
                                otros = parseFloat($('#' + i + 'otros').val());
                                salCap = parseFloat($('#' + i + 'saldoCap').text());

                                if ($('#' + i + 'cap').val() == '' || $('#' + i + 'inte').val() == '' || $('#' + i + 'otros')
                                    .val() == '') {
                                    Swal.fire({
                                        icon: 'error',
                                        title: '¡ERROR!',
                                        text: 'No se permiten campos vacíos '
                                    })
                                    return 0;
                                }

                                if (cap < 0 && inte < 0 && otros < 0) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: '¡ERROR!',
                                        text: 'No se permiten números negativos '
                                    })
                                    return 0;
                                }

                                if (cap == 0 && i == (noFila - 1)) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: '¡ERROR!',
                                        text: 'El capital de la ultima fila no puede quedar en 0 '
                                    })
                                    return 0;
                                }

                            }
                        }

                        function calPlanDePago() {
                            //$('#1salCap').val('Hola'); // Para enviar datos a los inputs de la tabla
                            //Contador de fila
                            noFila = conFila('tbPlanPagos');
                            //var desembolso = parseInt($('#idDes1').text());
                            var estado = false;
                            if (!estado) {
                                var desembolso = parseFloat($('#idDes1').text());
                                //console.log(typeof(desembolso) + ' - ' + desembolso);
                                estado = true;
                            }

                            for (let i = 1; i <= (noFila - 1); i++) {
                                //console.log(typeof(cap)+' - '+cap);
                                cap = parseFloat($('#' + i + 'cap').val());
                                //console.log(cap);
                                inte = parseFloat($('#' + i + 'inte').val());
                                //console.log(inte);
                                otros = parseFloat($('#' + i + 'otros').val());
                                //console.log(otros);

                                if (cap < 0 || inte < 0 || otros < 0) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: '¡ERROR!',
                                        text: 'No se permite números negativos'
                                    })
                                    return
                                }

                                desembolso = (desembolso - $('#' + i + 'cap').val()).toFixed(2);
                                $('#' + i + 'salCap').text(desembolso);

                                total = (parseFloat(cap + inte + otros)).toFixed(3);
                                $('#' + i + 'total').text(total);

                            }
                        }

                        function gMatriz(vacMaster) {
                            // Obtener la cantidad de filas
                            var filas = 0;
                            for (var i = 0; i < vacMaster.length; i++) {
                                var longitudVector = vacMaster[i].length;
                                filas = Math.max(filas, longitudVector);
                            }
                            // Crear la matriz
                            var matriz = [];
                            // Generar la matriz automáticamente
                            for (var i = 0; i < filas; i++) {
                                var fila = [];
                                for (var j = 0; j < vacMaster.length; j++) {
                                    fila.push(vacMaster[j][i] || null);
                                }
                                matriz.push(fila);
                            }
                            //console.log(matriz);
                            return matriz;
                        }

                        $('#btnAct').click(function() {
                            if (validaCliCod() == 0) return;
                            if (validarTabla() == 0) return;

                            noFila = (conFila('tbPlanPagos')) - 1;
                            dato = $("#" + noFila + "salCap").text();
                            //console.log('Saldo Cap ' + dato);
                            if (dato != 0 || dato < 0) {
                                Swal.fire({
                                    icon: 'error',
                                    title: '¡ERROR!',
                                    text: 'El saldo capital tiene que terminar en 0'
                                })
                                return;
                            }

                            vecMaster = [];
                            vecMaster.push(capDataTb('idPP', 'td'));
                            vecMaster.push(capDataTb('fecha', 'input'));
                            vecMaster.push(capDataTb('noCuo', 'td'));
                            vecMaster.push(capDataTb('capita', 'input'));
                            vecMaster.push(capDataTb('interes', 'input'));
                            vecMaster.push(capDataTb('otrosP', 'input'));
                            vecMaster.push(capDataTb('saldoCap', 'td'));

                            let matriz = gMatriz(vecMaster);
                            let codCu = $('#codCu').val();
                            actMasiva(matriz, 'actMasPlanPagos', codCu);

                        });


                        // Codigo para agregar una fila en la tabla
                        $('#newRow').click(function() {
                            if (validaCliCod() == 0) return;
                            // Sample Data
                            var tabla = document.getElementById('tbPlanPagos');
                            var filas = tabla.getElementsByTagName('tr');
                            var noFila = filas.length;

                            var tr = $("<tr>")
                            tr.append(`<td id="${noFila + 'idData'}" name="idPP[]" hidden>0</td>`) // Identificador new
                            tr.append(`<td id="${noFila + 'idCon'}">${noFila}</td>`) // No de fila
                            tr.append(
                                `<td><input id="${noFila + 'fechaP'}" type="date" name="fecha[]" class="form-control" onblur="validaF()"></td>`
                            ) //fecha
                            tr.append('<td><i class="fa-solid fa-money-bill" style="color: #c01111;"></i></td>') //Estado 
                            tr.append(`<td name="noCuo[]">${noFila}</td>`) // No de Pago 
                            tr.append(
                                `<td><input min="0" step="0.01" id="${noFila + 'cap'}" name="capita[]" onkeyup="calPlanDePago()" type="number" class="form-control"  value="0" min="0"></td>`
                            ) //Capital
                            tr.append(
                                `<td><input min="0" step="0.01" id="${noFila + 'inte'}" name="interes[]" onkeyup="calPlanDePago()" type="number" class="form-control"  value="0" min="0" ` +
                                ((($('#control').val()) == 1) ? "" : "disabled") + `> </td>`) //Interes
                            tr.append(
                                `<td><input min="0" step="0.01" id="${noFila + 'otros'}" name="otrosP[]" onkeyup="calPlanDePago()" type="number" class="form-control"  value="0" min="0"></td>`
                            ) //Otros pagos
                            tr.append(`<td id="${noFila + 'salCap'}" name="saldoCap[]"></td>`) // Saldo Capital 

                            tr.append(`<td id="fila${noFila}total">${formatNumber(noFila)}</td>`); //total


                            $('#tbPlanPagos tbody').append(tr)

                            $('#' + noFila + 'fechaP').val(hoy());
                            calPlanDePago();


                        })
                        // Remove Selected Table Row(s)
                        $('#killRow').click(function() {
                            if (validaCliCod() == 0) return;
                            var tabla = document.getElementById('tbPlanPagos');
                            var filas = tabla.getElementsByTagName('tr');
                            var noFila = filas.length - 1;

                            fila = parseInt($('#' + noFila + 'idCon').text());
                            filaData = parseInt($('#' + noFila + 'idData').text());

                            if (fila == 1) {
                                Swal.fire({
                                    icon: "error",
                                    title: "¡ERROR!",
                                    text: "Ya no se puede eliminar más filas"
                                });
                                return;
                            }

                            if (filaData != 0) {
                                Swal.fire({
                                    icon: "error",
                                    title: "¡ERROR!",
                                    text: "Los datos de la fila serán eliminados en la base de datos"
                                });
                                eliminarFila(filaData, 'deleteFilaPlanPagos')

                            } else {
                                tabla.deleteRow(noFila);
                                calPlanDePago();
                            }

                        })
                    </script>
                </div>



            </div>

        </div>
        </div>

    <?php
        break;

    case 'cambiar_estado_cred':
    ?>
        <input type="text" readonly hidden value='cambiar_estado_cred' id='condi'>
        <input type="text" hidden value="cre_indi_02" id="file">
        <div class="card">
            <div class="card-body">

                <h5 class="card-header">Cambiar estado de Credito de Aprobado a Análisis </h5>
                <div class="card-body">
                    <!-- Formulario para el nombre del cliente y codigo de cuenta -->
                    <form id="form" action="">
                        <!-- INICIO DE LA FILA -->
                        <div class="row">
                        </div>
                        <!-- FIN DE LA FILA -->
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="row mb-2">
                                    <div class="col-12">
                                        <div class="row">
                                            <div class="col-12 col-sm-6">
                                            </div>
                                            <div class="col-12 col-sm-6">
                                                <button type="button"
                                                    class="btn btn-outline-danger pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                                                    onclick="abrir_modal('#modal_creditos_a_desembolsar', '#id_modal_hidden', 'id')"><i
                                                        class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar
                                                    Credito</button>
                                            </div>
                                        </div>
                                        <?php
                                        include_once "../../../src/cris_modales/mdls_cambiar_estado_desembolso_02.php";
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'delete_desembolso':

        $extra = $_POST["xtra"];
        $codusu = $_SESSION['id'];
        $id_agencia = $_SESSION['id_agencia'];
        $datos[] = [];
        $bandera = "CODIGO DE CUENTA INEXISTENTE";
        if ($extra != 0) {
            $consulta = mysqli_query($conexion, "SELECT cl.short_name AS nombrecli, cl.idcod_cliente AS codcli, cm.CCODCTA AS ccodcta, cm.MonSug AS monsug, cm.NIntApro AS interes, cm.DFecDsbls AS fecdesembolso,
                ((cm.MonSug)-(SELECT IFNULL(SUM(ck.KP),0) FROM CREDKAR ck WHERE ck.CTIPPAG='P' AND ck.CCODCTA='$extra')) AS saldocap
                FROM cremcre_meta cm
                INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                WHERE cm.CCODCTA='$extra'");
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $datos[$i] = $fila;
                $i++;
                $bandera = "";
            }
        }
    ?>
        <input type="text" readonly hidden value='delete_desembolso' id='condi'>
        <input type="text" hidden value="cre_indi_02" id="file">
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>Eliminacion de Creditos Desembolsados</h4>
            </div>
            <div class="card-body">
                <div class="row contenedort">
                    <h5>Buscar cliente a Eliminar/Cambiar de estado</h5>
                    <div class="row mb-3">
                        <div class="col-sm-5">
                            <br>
                            <button type="button" class="btn btn-primary col-sm-12"
                                onclick="abrir_modal_for_delete('#modal_estado_cuenta_for_delete', '#id_modal_hidden', 'name/A/'+'/#/#/#/#')">
                                <i class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">


                    </div>
                    <?php if ($bandera != "" && $extra != "0") {
                        echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                    }
                    ?>
                </div>
            </div>
            <!-- <div class="row contenedort justify-content-center">
                <h5>Buscar cliente </h5>
                <div class="col-sm-5">
                    <br>
                    <button type="button" class="btn btn-primary col-sm-12" onclick="abrir_modal_for_delete('#modal_estado_cuenta_for_delete', '#id_modal_hidden', 'name/A/'+'/#/#/#/#')">
                        <i class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar crédito
                    </button>
                </div>
            </div> -->
        </div>

        <div class="row justify-items-md-center">
            <div class="col align-items-center" id="modal_footer">

                <?php
                if ($bandera == "") {
                    echo '<button type="button" class="btn btn-outline-danger" data-ccodcta="' . $fila["ccodcta"] . '" onclick="enviarAprob(this))">
                        <i class="fas fa-trash-alt"></i> Eliminar
                     </button>';
                }
                ?>

                <button type="button" class="btn btn-outline-danger"
                    onclick="printdiv('PagGrupAutom', '#cuadro', 'caja_cre', 0)">
                    <i class="fa-solid fa-ban"></i> Cancelar
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="salir()">
                    <i class="fa-solid fa-circle-xmark"></i> Salir
                </button>
                <!-- <button onclick="reportes([['numdoc', 'nciclo'], [], [], [5]], 'pdf', 'comp_grupal', 0)">asdfas</button> -->
            </div>
        </div>
        </div>
        <?php
        include_once "../../../src/cris_modales/mdls_estadocuenta_for_delete.php";
        break;
        ?>


<?php
        break;
} ?>