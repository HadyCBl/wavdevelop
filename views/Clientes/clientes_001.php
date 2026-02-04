<?php

use Micro\Generic\AblyService;
use Micro\Helpers\Log;
use App\Generic\FileProcessor;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Models\Departamento;
use Micro\Models\Identificacion;
use Micro\Models\Pais;

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
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';


$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$ofi = $_SESSION['agencia'];
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

//++++
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

$condi = $_POST["condi"];
switch ($condi) {
    case 'create_cliente_natural': {
            $query = "SELECT 
                        tc.*,
                        pa1.valor AS Parentesco_Ref1,
                        da1.valor AS Dir_Ref1,
                        rda1.valor AS Ref_Dir1,
                        pa2.valor AS Parentesco_Ref2,
                        da2.valor AS Dir_Ref2,
                        rda2.valor AS Ref_Dir2,
                        pa3.valor AS Parentesco_Ref3,
                        da3.valor AS Dir_Ref3,
                        rda3.valor AS Ref_Dir3,
                        pa4.valor AS actividadEconomicaSat,
                        IFNULL(pa5.valor, 'No') AS pariente_pep,
                        IFNULL(pa6.valor, 'No') AS asociado_pep,
                        IFNULL(pa7.valor, '') AS condicionMigratoria,
                        IFNULL(emp.valor, 'No') AS esEmpleado
                    FROM tb_cliente tc
                    LEFT JOIN tb_cliente_atributo pa1 ON pa1.id_cliente = tc.idcod_cliente AND pa1.id_atributo = 1
                    LEFT JOIN tb_cliente_atributo da1 ON da1.id_cliente = tc.idcod_cliente AND da1.id_atributo = 4
                    LEFT JOIN tb_cliente_atributo rda1 ON rda1.id_cliente = tc.idcod_cliente AND rda1.id_atributo = 7
            
                    LEFT JOIN tb_cliente_atributo pa2 ON pa2.id_cliente = tc.idcod_cliente AND pa2.id_atributo = 2
                    LEFT JOIN tb_cliente_atributo da2 ON da2.id_cliente = tc.idcod_cliente AND da2.id_atributo = 5
                    LEFT JOIN tb_cliente_atributo rda2 ON rda2.id_cliente = tc.idcod_cliente AND rda2.id_atributo = 8
            
                    LEFT JOIN tb_cliente_atributo pa3 ON pa3.id_cliente = tc.idcod_cliente AND pa3.id_atributo = 3
                    LEFT JOIN tb_cliente_atributo da3 ON da3.id_cliente = tc.idcod_cliente AND da3.id_atributo = 6
                    LEFT JOIN tb_cliente_atributo rda3 ON rda3.id_cliente = tc.idcod_cliente AND rda3.id_atributo = 9

                    LEFT JOIN tb_cliente_atributo pa4 ON pa4.id_cliente = tc.idcod_cliente AND pa4.id_atributo = 10

                    LEFT JOIN tb_cliente_atributo pa5 ON pa5.id_cliente = tc.idcod_cliente AND pa5.id_atributo = 11

                    LEFT JOIN tb_cliente_atributo pa6 ON pa6.id_cliente = tc.idcod_cliente AND pa6.id_atributo = 12

                    LEFT JOIN tb_cliente_atributo pa7 ON pa7.id_cliente = tc.idcod_cliente AND pa7.id_atributo = 13

                    LEFT JOIN tb_cliente_atributo emp ON emp.id_cliente = tc.idcod_cliente AND emp.id_atributo = 14

                    WHERE tc.estado = '1' AND tc.idcod_cliente = ?";

            $query2 = "SELECT `tipoCliente` AS `id_tipoCliente`,`agencia` AS `agencia`,`primer_nombre` AS `primer_name`,
                        `segundo_nombre` AS `segundo_name`,`tercer_nombre` AS `tercer_name`,`primer_apellido` AS `primer_last`,
                        `segundo_apellido` AS `segundo_last`,`casada_apellido` AS `casada_last`,`nombre_corto` AS `short_name`,`nombre_completo` AS `compl_name`,
                        `img_cliente` AS `url_img`,`fecha_nacimiento` AS `date_birth`,`genero` AS `genero`,`estado_civil` AS `estado_civil`,`origen` AS `origen`,
                        `pais_nacio` AS `pais_nacio`,`depa_nacio` AS `depa_nacio`,`muni_nacio` AS `id_muni_nacio`,`aldea` AS `aldea`,`tipo_documento` AS `type_doc`,
                        `no_identifica` AS `no_identifica`,`pais_extiende` AS `pais_extiende`,`nacionalidad` AS `nacionalidad`,`depa_extiende` AS `depa_extiende`,
                        `muni_extiende` AS `id_muni_extiende`,`otra_nacion` AS `otra_nacion`,`identi_tribu` AS `identi_tribu`,`no_tributaria` AS `no_tributaria`,
                        `no_igss` AS `no_igss`,`profesion` AS `profesion`,`direccion` AS `Direccion`,`depa_reside` AS `depa_reside`,`muni_reside` AS `id_muni_reside`,
                        `aldea_reside` AS `aldea_reside`,`tel_no1` AS `tel_no1`,`tel_no2` AS `tel_no2`,`area` AS `area`,`ano_reside` AS `ano_reside`,
                        `vivienda_Condi` AS `vivienda_Condi`,`email` AS `email`,`relac_propo` AS `relac_propo`,`monto_ingre` AS `monto_ingre`,
                        `actu_Propio` AS `actu_Propio`,`representante_name` AS `representante_name`,`repre_calidad` AS `repre_calidad`,`id_religion` AS `id_religion`,
                        `leer` AS `leer`,`escribir` AS `escribir`,`firma` AS `firma`,`cargo_grupo` AS `cargo_grupo`,`educacion` AS `educa`,`idioma` AS `idioma`,
                        `relacion_insti` AS `Rel_insti`,`datos_Adicionales` AS `datos_Adicionales`,`conyuge` AS `Conyuge`,`telconyuge` AS `telconyuge`,
                        `zona` AS `zona`,`barrio` AS `barrio`,`hijos` AS `hijos`,`dependencia` AS `dependencia`,`nombre_ref1` AS `Nomb_Ref1`,`nombre_ref2` AS `Nomb_Ref2`,
                        `nombre_ref3` AS `Nomb_Ref3`,`tel_ref1` AS `Tel_Ref1`,`tel_ref2` AS `Tel_Ref2`,`tel_ref3` AS `Tel_Ref3`,`PEP` AS `PEP`,`CPE` AS `CPE`,
                        `control_interno` AS `control_interno`,`observaciones` AS `observaciones`
                    FROM tb_clientes_draft WHERE id=?;";

            $codcliente = $_POST["xtra"];
            $SelectAgenci = 'd-none';
            $isNewCustomer = false;
            // Determinar si es un cliente nuevo (codcliente = '0' o comienza con 'draft_')
            if ($codcliente === '0' || (is_string($codcliente) && strpos($codcliente, 'draft_') === 0)) {
                $isNewCustomer = true;
            }
            $bandera = !$isNewCustomer;

            $isDraft = strpos($codcliente, 'draft_') === 0;

            $showmensaje = false;
            try {
                $database->openConnection(2);

                $negociosCatalogo = $database->selectColumns('tb_negocio', ['id_Negocio', 'Negocio']);
                if (empty($negociosCatalogo)) {
                    $showmensaje = true;
                    throw new Exception("No hay catalogo de negocios");
                }

                $etniaCatalogo = $database->selectColumns('tb_etnia', ['id', 'nombre']);
                if (empty($etniaCatalogo)) {
                    $showmensaje = true;
                    throw new Exception("No hay catalogo de etnias");
                }

                $religionCatalogo = $database->selectColumns('tb_religion', ['id', 'nombre']);
                if (empty($religionCatalogo)) {
                    $showmensaje = true;
                    throw new Exception("No hay catalogo de religiones");
                }

                $database->closeConnection();

                $database->openConnection();
                $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'cod_agenc', 'nom_agencia']);
                if (empty($agencias)) {
                    $showmensaje = true;
                    throw new Exception("No hay agencias disponibles");
                }

                $paisesCatalogo = $database->selectColumns('tb_paises', ['abreviatura AS Abreviatura', 'nombre AS Pais']);
                if (empty($paisesCatalogo)) {
                    $showmensaje = true;
                    throw new Exception("No hay catalogo de paises");
                }

                $parentescoCatalogo = $database->selectColumns('tb_parentescos', ['id AS id_parent', 'descripcion']);
                if (empty($parentescoCatalogo)) {
                    $showmensaje = true;
                    throw new Exception("No hay catalogo de parentescos");
                }

                $relacionInstitucional = $database->selectColumns('tb_relaciones', ['id', 'nombre']);

                $actividadesEconomicasSat = $database->getAllResults(
                    "SELECT 
                        ac.id as id_clase,
                        ac.descripcion as nombre_clase,
                        a.id as id_actividad, a.codigo,
                        a.descripcion as nombre_actividad
                    FROM sat_actividades_clases ac
                    LEFT JOIN sat_actividades a ON ac.id = a.id_clase
                    ORDER BY ac.id,a.id;"
                );

                if (!empty($actividadesEconomicasSat)) {
                    $currentClase = null;
                    $groupedActividades = [];
                    foreach ($actividadesEconomicasSat as $actividad) {
                        if ($actividad['id_clase'] !== $currentClase) {
                            $currentClase = $actividad['id_clase'];
                            $groupedActividades[$currentClase] = [
                                'nombre_clase' => $actividad['nombre_clase'],
                                'actividades' => []
                            ];
                        }
                        $groupedActividades[$currentClase]['actividades'][] = [
                            'id_actividad' => $actividad['id_actividad'],
                            'codigo' => $actividad['codigo'],
                            'nombre_actividad' => $actividad['nombre_actividad']
                        ];
                    }
                } else {
                    // $showmensaje = true;
                    // throw new Exception("No hay catalogo de actividades económicas SAT");
                }

                $origenesRiquezaCatalogo = $database->selectColumns('tb_origen_riqueza', ['id', 'descripcion']);

                $motivosCatalogo = $database->selectColumns('tb_motivos_pep', ['id', 'descripcion']);

                $condicionesMigratoriasCatalogo = $database->selectColumns('tb_condiciones_migratorias', ['id', 'descripcion']);

                if (!$isNewCustomer || $isDraft) {
                    $departamentosCatalogo = $database->selectColumns('tb_departamentos', ['id AS codigo_departamento', 'nombre']);
                    if (empty($departamentosCatalogo)) {
                        $showmensaje = true;
                        throw new Exception("No hay catalogo de departamentos");
                    }

                    if (!$isDraft) {
                        $datos = $database->getAllResults($query, [$codcliente]);
                        if (empty($datos)) {
                            $showmensaje = true;
                            throw new Exception("No se encontró información del cliente");
                        }

                        try {
                            $departamentoDomicilioObject = new Departamento($datos[0]['depa_reside'] ?? 0);
                            $datos[0]['id_pais_domicilio'] = $departamentoDomicilioObject->pais->getAbreviatura() ?? 'GT';
                        } catch (Exception $e) {
                            $datos[0]['id_pais_domicilio'] = 'GT';
                        }

                        $datosClientePep = $database->selectColumns('cli_datos_pep', ['id', 'entidad', 'puesto', 'paisEntidad', 'otroOrigen'], 'id_cliente=?', [$codcliente]);

                        if (!empty($datosClientePep)) {
                            $origenesRiquezaPep = $database->selectColumns('cli_origenes_riqueza', ['*'], 'id_pep=?', [$datosClientePep[0]['id']]);
                        }


                        $datosParientePep = $database->selectColumns(
                            'cli_complementos_pep',
                            [
                                'parentesco',
                                'primerApellido',
                                'segundoApellido',
                                'apellidoCasada',
                                'primerNombre',
                                'segundoNombre',
                                'otrosNombres',
                                'sexo',
                                'pais',
                                'condicion',
                                'entidad',
                                'puesto'
                            ],
                            'id_cliente=? AND tipo=? AND estado=?',
                            [$codcliente, 'pariente', 1]
                        );
                        $datosAsociadoPep = $database->selectColumns(
                            'cli_complementos_pep',
                            [
                                'motivoAsociacion',
                                'detalleOtro',
                                'primerApellido',
                                'segundoApellido',
                                'apellidoCasada',
                                'primerNombre',
                                'segundoNombre',
                                'otrosNombres',
                                'sexo',
                                'pais',
                                'condicion',
                                'entidad',
                                'puesto'
                            ],
                            'id_cliente=? AND tipo=? AND estado=?',
                            [$codcliente, 'asociado', 1]
                        );
                    } else {
                        $draftId = str_replace('draft_', '', $codcliente);

                        $datos = $database->getAllResults($query2, [$draftId]);
                        if (!empty($datos)) {
                            $bandera = true;
                            $datos[0]['fiador'] = 0;
                            $datos[0]['Parentesco_Ref1'] = '';
                            $datos[0]['Parentesco_Ref2'] = '';
                            $datos[0]['Parentesco_Ref3'] = '';
                            $datos[0]['Dir_Ref1'] = '';
                            $datos[0]['Dir_Ref2'] = '';
                            $datos[0]['Dir_Ref3'] = '';
                            $datos[0]['Ref_Dir1'] = '';
                            $datos[0]['Ref_Dir2'] = '';
                            $datos[0]['Ref_Dir3'] = '';
                            $datos[0]['actividadEconomicaSat'] = '';
                            $datos[0]['pariente_pep'] = 'No';
                            $datos[0]['asociado_pep'] = 'No';
                            $datos[0]['condicionMigratoria'] = '';
                            $datos[0]['esEmpleado'] = 'No';
                        }
                    }

                    $imgurl = __DIR__ . '/../../../' . $datos[0]['url_img'];
                    if (!is_file($imgurl)) {
                        $isfile = false;
                        $src = '../includes/img/fotoClienteDefault.png';
                    } else {
                        $isfile = true;
                        $imginfo = getimagesize($imgurl);
                        $mimetype = $imginfo['mime'];
                        $imageData = base64_encode(file_get_contents($imgurl));
                        $src = 'data:' . $mimetype . ';base64,' . $imageData;
                    }
                }

                if ($isNewCustomer) {
                    $drafts = $database->selectColumns('tb_clientes_draft', ['id', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'created_at'], 'created_by=? AND estado=1', [$idusuario]);
                }

                $paisesDocumentoExtendido = $database->getAllResults(
                    "SELECT p.id,p.nombre FROM tb_paises p
                        WHERE p.id IN (SELECT id_pais FROM tb_identificaciones WHERE id_pais IS NOT NULL);"
                );

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
            //
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];

            $showDraftCliente = $_ENV['BORRADOR_CLIENTE'] ?? 0;
            $appPaisVersion = $_ENV['APP_PAIS_VERSION'] ?? 'GT';


            if (!$isNewCustomer) {
                /**
                 * ES ACTUALIZACION DE DATOS
                 */
                // $fechaActualizacion = (Date::isValid($datos[0]['fecha_actualizacion'] ?? '')) ? $datos[0]['fecha_actualizacion']
                //     : ((Date::isValid($datos[0]['fecha_mod'] ?? '', 'Y-m-d H:i:s') && $datos[0]['fecha_mod'] >= '1000-01-01') ? date('Y-m-d', strtotime($datos[0]['fecha_mod']))
                //         : (Date::isValid($datos[0]['fecha_alta'] ?? '', 'Y-m-d H:i:s') && $datos[0]['fecha_alta'] >= '1000-01-01' ? date('Y-m-d', strtotime($datos[0]['fecha_alta'])) : null));

                $fechaActualizacion = (Date::isValid($datos[0]['fecha_actualizacion'] ?? '')) ? $datos[0]['fecha_actualizacion'] : null;

                // Calcular si han pasado más de 6 meses desde la última actualización
                $fechaActualizacionObj = !empty($fechaActualizacion) ? DateTime::createFromFormat('Y-m-d', $fechaActualizacion) : false;
                $fechaActual = new DateTime();

                // Validar que la fecha no sea inválida (0000-00-00 o NULL)
                $fechaInvalida = empty($fechaActualizacion) || $fechaActualizacionObj === false;

                if (!$fechaInvalida) {
                    $intervalo = $fechaActualizacionObj->diff($fechaActual);
                    $mesesTranscurridos = ($intervalo->y * 12) + $intervalo->m;
                    $requiereActualizacion = $mesesTranscurridos >= 6;
                } else {
                    // Si la fecha es inválida, considerar que requiere actualización
                    $mesesTranscurridos = 999;
                    $requiereActualizacion = true;
                }

                if (is_numeric($datos[0]['pais_extiende'] ?? null)) {
                    $paisVersionApp = Pais::getPaisCompleto($datos[0]['pais_extiende']);
                }
            }

            if ($isNewCustomer || !isset($paisVersionApp) || !$paisVersionApp) {
                /**
                 * ES INGREOS NUEVO o NO SE ENCONTRO EL PAIS GUARDADO
                 */
                $paisVersionApp = Pais::obtenerPorCodigo($appPaisVersion);
            }

            if ($paisVersionApp) {
                $tiposIdentificacionCompletos = Identificacion::obtenerPorPaisConGlobales($paisVersionApp['id']);
            }
?>
            <input type="text" id="file" value="clientes_001" style="display: none;">
            <input type="text" id="condi" value="create_cliente_natural" style="display: none;">
            <style>
                .custom-tooltip {
                    background: linear-gradient(135deg, rgba(0, 0, 0, 0.95) 0%, rgba(20, 20, 20, 0.95) 100%);
                    color: #ffffff;
                    border: 2px solid #F39C12;
                    font-size: 0.95rem;
                    padding: 12px 16px;
                    border-radius: 8px;
                    box-shadow: 0 8px 32px rgba(243, 156, 18, 0.3);
                    max-width: 280px;
                    backdrop-filter: blur(10px);
                }

                .badge {
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .badge:hover {
                    transform: scale(1.05);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                }

                .form-floating>label {
                    color: #6c757d;
                    font-weight: 500;
                }

                .form-floating>.form-control:focus~label,
                .form-floating>.form-control:not(:placeholder-shown)~label {
                    color: #0d6efd;
                }

                .card {
                    border: none;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                    transition: all 0.3s ease;
                }

                .card:hover {
                    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
                }

                .card-header {
                    background: linear-gradient(135deg, #0a32e4 0%, #325ce9 100%);
                    border-radius: 12px 12px 0 0 !important;
                    border: none;
                    padding: 1.5rem;
                    font-weight: 600;
                }

                .alert {
                    border-radius: 10px;
                    border: none;
                    backdrop-filter: blur(10px);
                }

                .form-control,
                .form-select {
                    border-radius: 8px;
                    border: 1.5px solid #e9ecef;
                    transition: all 0.3s ease;
                }

                .form-control:focus,
                .form-select:focus {
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }

                .btn {
                    border-radius: 8px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    border: 1.5px solid currentColor;
                }

                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
                }

                .btn-outline-success:hover {
                    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                    border-color: transparent;
                }

                .btn-outline-primary:hover {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-color: transparent;
                }

                .img-thumbnail {
                    border-radius: 12px;
                    border: 2px solid #e9ecef;
                    padding: 8px;
                    transition: all 0.3s ease;
                }

                .img-thumbnail:hover {
                    border-color: #667eea;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
                }

                /* .contenedort {
                    background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
                    border-radius: 12px;
                    padding: 1.5rem;
                    margin-bottom: 1.5rem;
                } */

                .input-group .input-group-text {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    border-radius: 0 8px 8px 0;
                }

                video,
                canvas {
                    border-radius: 10px;
                    object-fit: cover;
                }

                .row>* {
                    margin-bottom: 0.5rem;
                }

                .text-center {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    letter-spacing: 0.5px;
                }

                .modal-content {
                    border-radius: 12px;
                    border: none;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                }

                .modal-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 12px 12px 0 0;
                    border: none;
                }

                .dataTables_wrapper .dataTables_paginate .paginate_button {
                    border-radius: 6px;
                    margin: 0 2px;
                }

                .table {
                    border-radius: 10px;
                    overflow: hidden;
                }

                .table thead th {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    font-weight: 600;
                    border: none;
                    padding: 1rem;
                }

                .fade-in {
                    animation: fadeIn 0.3s ease-in;
                }

                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            </style>

            <div class="card fade-in" x-data="{
                        typeOperation: '<?= $isNewCustomer ? 'create' : 'update' ?>', 
                        paisVersionApp: '<?= $appPaisVersion ?>',
                        requiresUpdate: <?= (!$isNewCustomer && $requiereActualizacion) ? 'true' : 'false' ?>,
                    }">
                <div class="card-header text-white">
                    <i class="fa-solid fa-file-invoice me-2"></i><?= (!$isNewCustomer) ? 'Actualización' : 'Ingreso'; ?> de Cliente
                </div>
                <div class="card-body">
                    <?php if (!$status) { ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-exclamation-circle me-2"></i>
                            <strong>¡Alerta!</strong> <?= $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                    <?php if ($isNewCustomer && !empty($drafts)) { ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-info border-0" role="alert">
                                    <i class="fa-solid fa-bookmark me-2"></i>
                                    <strong>Borradores guardados:</strong> Seleccione uno para continuar
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($drafts as $draft) { ?>
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <div class="card shadow-sm h-100 border-0">
                                                <div class="card-body d-flex flex-column justify-content-between">
                                                    <div>
                                                        <h6 class="card-title text-primary mb-2">
                                                            <i class="fa-solid fa-file-pen me-1"></i>
                                                            <?= htmlspecialchars($draft['primer_nombre'] . ' ' . $draft['segundo_nombre'] . ' ' . $draft['primer_apellido']) ?>
                                                        </h6>
                                                        <p class="card-text mb-0">
                                                            <small class="text-muted">
                                                                <i class="fa-regular fa-clock me-1"></i>
                                                                <?= htmlspecialchars($draft['created_at']) ?>
                                                            </small>
                                                        </p>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-primary mt-3 w-100"
                                                        onclick="printdiv('create_cliente_natural', '#cuadro', 'clientes_001', 'draft_<?= $draft['id'] ?>')">
                                                        <i class="fa-solid fa-file-import me-1"></i> Usar Borrador
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="contenedort">
                        <div class="row <?= $SelectAgenci ?> mb-3" style="display:none;">
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" name="agencia" id="agencidplus">
                                        <?php
                                        foreach ($agencias as $row) {
                                            $selected = ($row['id_agencia'] == $idagencia) ? 'selected' : '';
                                            echo '<option value="' . $row['cod_agenc'] . '" ' . $selected . '>' . $row['nom_agencia'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <label for="agencia">Selecciona una agencia</label>
                                </div>
                            </div>
                        </div>

                        <!-- Selección del tipo de cliente -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card border-0 p-3">
                                    <label class="form-label fw-bold mb-3">
                                        <i class="fa-solid fa-tags me-2"></i>Tipo de Cliente
                                    </label>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="chkFiadorSi" name="tipo_cliente" value="1"
                                            <?= ($bandera && $datos[0]['fiador'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="chkFiadorSi">
                                            <i class="fa-solid fa-user-shield text-success me-2"></i>Fiador
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="chkFiador" name="tipo_cliente" value="0"
                                            <?= (isset($datos[0]['fiador']) && $datos[0]['fiador'] == 1) ? '' : 'checked'; ?>>
                                        <label class="form-check-label" for="chkFiador">
                                            <i class="fa-solid fa-user text-primary me-2"></i>Cliente Normal
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <?php if (!$isNewCustomer): ?>
                                    <div class="card border-0 p-3" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);">
                                        <label class="form-label fw-bold mb-3">
                                            <i class="fa-solid fa-calendar-check me-2"></i>Actualización de Datos
                                        </label>
                                        <div class="alert <?= $requiereActualizacion ? 'alert-warning' : 'alert-info' ?> border-0 mb-0">
                                            <?php if ($requiereActualizacion && !$fechaInvalida): ?>
                                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                                <strong>¡Atención!</strong> Han transcurrido <span class="badge bg-danger"><?= $mesesTranscurridos ?></span> meses
                                            <?php endif; ?>

                                            <div class="mt-2">
                                                <small>Última actualización:</small>
                                                <strong class="d-block"><?= $fechaInvalida ? 'Sin registro' : Date::toDMY($fechaActualizacion); ?></strong>
                                            </div>

                                            <div id="divUpdateDate" class="mt-3" x-data="{ showSectionUpdate: false }"
                                                x-show="requiresUpdate">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="actualizarFechaActualizacion"
                                                        @change="showSectionUpdate = !showSectionUpdate">
                                                    <label class="form-check-label" for="actualizarFechaActualizacion">
                                                        <i class="fa-solid fa-calendar-plus me-1"></i>
                                                        Actualizar a fecha actual
                                                    </label>
                                                </div>

                                                <div id="contenedorFechaActualizacion" x-show="showSectionUpdate" class="mt-2">
                                                    <div class="form-floating">
                                                        <input type="date" class="form-control" id="nuevaFechaActualizacion"
                                                            value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                                                        <label for="nuevaFechaActualizacion">
                                                            <i class="fa-solid fa-calendar me-1"></i>Nueva Fecha
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isNewCustomer) { ?>
                            <div class="col-12 col-sm-12 col-md-4 ms-auto mb-3">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="fechaingreso" placeholder="Fecha de ingreso"
                                        value="<?= date('Y-m-d'); ?>">
                                    <label for="fechaingreso">
                                        <i class="fa-solid fa-calendar me-1"></i>Fecha de Ingreso
                                    </label>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($bandera) { ?>
                            <!-- Información adicional del cliente -->
                            <?php if (!$isNewCustomer) { ?>
                                <div class="row mb-4">
                                    <div class="col text-center">
                                        <span class="badge bg-primary fs-6">
                                            <i class="fa-solid fa-hashtag me-1"></i>Código: <?= $datos[0]['idcod_cliente']; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="row justify-content-center mb-4">
                                <div class="col-6 col-sm-5 col-md-3">
                                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 8px; border-radius: 12px;">
                                        <img id="vistaPrevia" class="img-thumbnail w-100" src="<?= $src; ?>"
                                            style="max-height:200px; object-fit: cover;">
                                    </div>
                                </div>
                            </div>
                            <?php if ($isfile && !$isNewCustomer) { ?>
                                <div class="row mb-3">
                                    <div class="col">
                                        <button class="btn btn-sm btn-danger w-100" type="button"
                                            onclick="eliminar_plus(['<?= $imgurl; ?>','<?= $datos[0]['idcod_cliente']; ?>'], '<?= $datos[0]['idcod_cliente']; ?>', 'delete_image_cliente', '¿Está seguro de eliminar la foto?')">
                                            <i class="fa-solid fa-trash me-2"></i>Eliminar Foto
                                        </button>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if (!$isNewCustomer) { ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="input-group">
                                            <input type="file" class="form-control" id="fileuploadcli"
                                                aria-describedby="inputGroupFileAddon04" aria-label="Upload" onchange="LeerImagen(this)">
                                            <button class="btn btn-primary" type="button" id="inputGroupFileAddon04"
                                                onclick="CargarImagen('fileuploadcli','<?= $datos[0]['idcod_cliente']; ?>');">
                                                <i class="fa-solid fa-upload me-2"></i>Guardar Foto
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col">
                                        <div class="alert alert-info border-0" role="alert">
                                            <i class="fa-solid fa-circle-info me-2"></i>
                                            Presione <strong>Guardar Foto</strong> después de seleccionar la imagen
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>

                        <?php if ($bandera && !$isNewCustomer) { ?>
                            <!-- Sección de fotografía con cámara -->
                            <div class="row mb-4">
                                <div class="col-12 col-md-6">
                                    <div class="card border-0">
                                        <div class="card-body text-center">
                                            <img id="previewClienteFoto" src="" alt="Vista previa"
                                                style="max-width: 100%; max-height: 300px; display: none; border-radius: 10px;">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="mb-2">
                                        <button type="button" class="btn btn-info w-100" onclick="abrirCamara()">
                                            <i class="fa-solid fa-camera me-2"></i><span id="camaraButtonText">Abrir Cámara</span>
                                        </button>
                                    </div>
                                    <div id="camaraContainer" style="display:none;">
                                        <div class="card border-0">
                                            <div class="card-body p-0">
                                                <video id="videoCamara" autoplay playsinline
                                                    style="width:100%; height:auto; transform: scaleX(-1); border-radius: 10px;"></video>
                                                <div class="mt-3 d-flex gap-2 flex-wrap">
                                                    <button type="button" class="btn btn-sm btn-primary flex-grow-1"
                                                        onclick="capturarFoto()">
                                                        <i class="fa-solid fa-camera-retro me-1"></i>Capturar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary flex-grow-1"
                                                        onclick="cerrarCamara()">
                                                        <i class="fa-solid fa-times me-1"></i>Cerrar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-info flex-grow-1" onclick="alternarCamara()"
                                                        id="toggleCameraBtn" style="display:none;">
                                                        <i class="fa-solid fa-rotate me-1"></i>Cambiar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <canvas id="canvasCaptura" width="300" height="300" style="display:none;"></canvas>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <?php if ($isNewCustomer) { ?>
                            <!-- Campo para cargar o tomar fotografía (nueva) -->
                            <div class="row mb-4">
                                <div class="col-12 col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="file" class="form-control" id="fotoCliente" accept="image/*" capture="environment"
                                            onchange="previewClienteFoto(this)">
                                        <label for="fotoCliente">
                                            <i class="fa-solid fa-image me-1"></i>Cargar Fotografía
                                        </label>
                                    </div>
                                    <div class="card border-0">
                                        <div class="card-body text-center">
                                            <img id="previewClienteFoto" src="" alt="Vista previa"
                                                style="max-width: 100%; max-height: 300px; display: none; border-radius: 10px;">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-info w-100" onclick="abrirCamara()">
                                            <i class="fa-solid fa-camera me-2"></i><span id="camaraButtonText">Abrir Cámara</span>
                                        </button>
                                    </div>
                                    <div id="camaraContainer" style="display:none;">
                                        <div class="card border-0">
                                            <div class="card-body p-0">
                                                <video id="videoCamara" autoplay playsinline
                                                    style="width:100%; height:auto; transform: scaleX(-1); border-radius: 10px;"></video>
                                                <div class="mt-3 d-flex gap-2 flex-wrap">
                                                    <button type="button" class="btn btn-sm btn-primary flex-grow-1"
                                                        onclick="capturarFoto()">
                                                        <i class="fa-solid fa-camera-retro me-1"></i>Capturar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary flex-grow-1"
                                                        onclick="cerrarCamara()">
                                                        <i class="fa-solid fa-times me-1"></i>Cerrar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-info flex-grow-1" onclick="alternarCamara()"
                                                        id="toggleCameraBtn" style="display:none;">
                                                        <i class="fa-solid fa-rotate me-1"></i>Cambiar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <canvas id="canvasCaptura" width="300" height="300" style="display:none;"></canvas>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- Información personal -->
                        <div class="row mb-3 g-2">
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="nom1" placeholder="Primer nombre" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['primer_name'] . '"';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'], ['ape1','ape2','ape3'], 1, '#nomcorto'); concatenarValores(['ape1','ape2','ape3'], ['nom1','nom2','nom3'], 2, '#nomcompleto')"
                                        oninput="validateInputname(this)" pattern="[A-Za-z]+" title="Solo se permiten letras"
                                        required>
                                    <label for="nom1">Primer nombre</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="nom2" placeholder="Segundo nombre" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['segundo_name'] . '"';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'], ['ape1','ape2','ape3'], 1, '#nomcorto'); concatenarValores(['ape1','ape2','ape3'], ['nom1','nom2','nom3'], 2, '#nomcompleto')"
                                        oninput="validateInputname(this)" pattern="[A-Za-z]+" title="Solo se permiten letras">
                                    <label for="nom2">Segundo nombre</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="nom3" placeholder="Tercer nombre" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['tercer_name'] . '"';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'], ['ape1','ape2','ape3'], 1, '#nomcorto'); concatenarValores(['ape1','ape2','ape3'], ['nom1','nom2','nom3'], 2, '#nomcompleto')"
                                        oninput="validateInputname(this)" pattern="[A-Za-z]+" title="Solo se permiten letras">
                                    <label for="nom3">Tercer nombre</label>
                                </div>
                            </div>
                        </div>

                        <!-- Fila: Apellidos -->
                        <div class="row mb-3">
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="ape1" placeholder="Primer apellido" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['primer_last'] . '"';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'], ['ape1','ape2','ape3'], 1, '#nomcorto'); concatenarValores(['ape1','ape2','ape3'], ['nom1','nom2','nom3'], 2, '#nomcompleto')"
                                        oninput="validateInputlastname(this)" pattern="[A-Za-z]+" title="Solo se permiten letras">
                                    <label for="ape1">Primer apellido</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="ape2" placeholder="Segundo apellido" <?php if ($bandera) {
                                                                                                                            echo 'value="' . $datos[0]['segundo_last'] . '"';
                                                                                                                        } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'], ['ape1','ape2','ape3'], 1, '#nomcorto'); concatenarValores(['ape1','ape2','ape3'], ['nom1','nom2','nom3'], 2, '#nomcompleto')"
                                        oninput="validateInputlastname(this)" pattern="[A-Za-z]+" title="Solo se permiten letras">
                                    <label for="ape2">Segundo apellido</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="ape3" placeholder="Tercer apellido" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['casada_last'] . '"';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'], ['ape1','ape2','ape3'], 1, '#nomcorto'); concatenarValores(['ape1','ape2','ape3'], ['nom1','nom2','nom3'], 2, '#nomcompleto')"
                                        oninput="validateInputlastname(this)" pattern="[A-Za-z]+" title="Solo se permiten letras">
                                    <label for="ape3">Apellido de casada</label>
                                </div>
                            </div>
                        </div>

                        <!-- Nombres procesados -->
                        <div class="row mb-3 g-2">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control " id="nomcorto" placeholder="Nombre corto" readonly
                                        disabled <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['short_name'] . '"';
                                                    } ?>>
                                    <label for="nomcorto">Nombre Corto</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="nomcompleto" placeholder="Nombre completo" readonly
                                        disabled <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['compl_name'] . '"';
                                                    } ?>>
                                    <label for="nomcompleto">Nombre Completo</label>
                                </div>
                            </div>
                        </div>

                        <!-- Datos personales -->
                        <div class="row mb-3 g-2">
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="genero">
                                        <option value="0" selected>Seleccione un género</option>
                                        <option value="M">Hombre</option>
                                        <option value="F">Mujer</option>
                                        <option value="X">No Definido</option>
                                    </select>
                                    <label for="genero"><i class="fa-solid me-1"></i>Género</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <select class="form-select" id="estcivil" onchange="toggleConyugueInput()">
                                        <option value="0" selected>Seleccione estado civil</option>
                                        <option value="SOLTERO">Soltero(a)</option>
                                        <option value="CASADO">Casado(a)</option>
                                    </select>
                                    <label for="estcivil"><i class="fa-solid me-1"></i>Estado Civil</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="profesion" placeholder="Profesión" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['profesion'] . '"';
                                                                                                                    } ?>
                                        oninput="validateInputprofesion(this)" pattern="[A-Za-z ]+" title="Solo letras">
                                    <label for="profesion"><i class="fa-solid me-1"></i>Profesión</label>
                                </div>
                            </div>
                        </div>

                        <!-- Contacto -->
                        <div class="row mb-3 g-2">
                            <div class="col-12 col-sm-3">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" placeholder="Email" <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['email'] . '"';
                                                                                                            } ?> oninput="validateEmail(this)" title="ejemplo@correo.com">
                                    <label for="email"><i class="fa-solid me-1"></i>Email</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="conyugue" placeholder="Cónyuge" <?php if ($bandera) {
                                                                                                                    echo 'value="' . $datos[0]['Conyuge'] . '"';
                                                                                                                } ?> oninput="validateInputlibre(this)" disabled>
                                    <label for="conyugue"><i class="fa-solid me-1"></i>Cónyuge</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="telconyuge" placeholder="Tel. Cónyuge" <?php if ($bandera) {
                                                                                                                            echo 'value="' . $datos[0]['telconyuge'] . '"';
                                                                                                                        } ?>
                                        oninput="validateInputtelreF(this)" disabled>
                                    <label for="telconyuge"><i class="fa-solid me-1"></i>Tel.</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        function previewClienteFoto(input) {
                            if (input.files && input.files[0]) {
                                var reader = new FileReader();
                                reader.onload = function(e) {
                                    var preview = document.getElementById('previewClienteFoto');
                                    preview.src = e.target.result;
                                    preview.style.display = 'block';
                                }
                                reader.readAsDataURL(input.files[0]);
                            }
                        }

                        var currentStream = null;
                        var usingFrontCamera = false;

                        function abrirCamara() {
                            var container = document.getElementById('camaraContainer');
                            container.style.display = 'block';

                            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                                alert("Tu navegador no soporta la captura de video o no tiene permisos.");
                                return;
                            }

                            const constraints = {
                                video: {
                                    facingMode: usingFrontCamera ? 'user' : 'environment'
                                }
                            };

                            if (!/Mobi|Android/i.test(navigator.userAgent)) {
                                constraints.video = true;
                            }

                            navigator.mediaDevices.getUserMedia(constraints)
                                .then(function(stream) {
                                    currentStream = stream;
                                    var video = document.getElementById('videoCamara');
                                    video.srcObject = stream;
                                    video.play();

                                    // Mostrar botón de cambio de cámara si hay varias disponibles
                                    navigator.mediaDevices.enumerateDevices().then(function(devices) {
                                        var hasMultiple = devices.filter(d => d.kind === 'videoinput').length > 1;
                                        var btn = document.getElementById('toggleCameraBtn');
                                        if (btn) {
                                            btn.style.display = hasMultiple ? 'inline-block' : 'none';
                                        }
                                    });
                                })
                                .catch(function(error) {
                                    alert("Error al acceder a la cámara: " + error.message);
                                });
                        }

                        function alternarCamara() {
                            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                                alert('Navegador sin soporte para cámara');
                                return;
                            }

                            usingFrontCamera = !usingFrontCamera;

                            if (currentStream) {
                                currentStream.getTracks().forEach(function(t) {
                                    t.stop();
                                });
                            }

                            const constraints = {
                                video: {
                                    facingMode: usingFrontCamera ? 'user' : 'environment'
                                }
                            };
                            navigator.mediaDevices.getUserMedia(constraints)
                                .then(function(stream) {
                                    currentStream = stream;
                                    var video = document.getElementById('videoCamara');
                                    video.srcObject = stream;
                                    video.play();
                                })
                                .catch(function(err) {
                                    console.error('Error al alternar la cámara', err);
                                });
                        }

                        function capturarFoto() {
                            var video = document.getElementById('videoCamara');
                            var canvas = document.getElementById('canvasCaptura');
                            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                            var preview = document.getElementById('previewClienteFoto') || document.getElementById('vistaPrevia');
                            var dataURL = canvas.toDataURL("image/png");
                            if (preview) {
                                preview.src = dataURL;
                                preview.style.display = 'block';
                            }

                            // Convertir la imagen en un archivo y asignarlo al input de tipo file
                            var fileInput = document.getElementById('fotoCliente') || document.getElementById('fileuploadcli');
                            var blob = dataURLToBlob(dataURL);
                            var file = new File([blob], "captura.png", {
                                type: "image/png"
                            });
                            var dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            if (fileInput) {
                                fileInput.files = dataTransfer.files;
                            }

                            cerrarCamara();
                        }

                        function dataURLToBlob(dataURL) {
                            var arr = dataURL.split(','),
                                mime = arr[0].match(/:(.*?);/)[1],
                                bstr = atob(arr[1]),
                                n = bstr.length,
                                u8arr = new Uint8Array(n);
                            while (n--) {
                                u8arr[n] = bstr.charCodeAt(n);
                            }
                            return new Blob([u8arr], {
                                type: mime
                            });
                        }

                        function cerrarCamara() {
                            var container = document.getElementById('camaraContainer');
                            container.style.display = 'none';

                            if (currentStream) {
                                currentStream.getTracks().forEach(function(track) {
                                    track.stop();
                                });
                                currentStream = null;
                            }

                            var video = document.getElementById('videoCamara');
                            if (video) {
                                video.srcObject = null;
                            }
                        }
                    </script>

                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Nacimiento</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="date" class="form-control" id="fechanacimiento" placeholder="Fecha de nacimiento"
                                        <?php if ($bandera) {
                                            echo 'value="' . $datos[0]['date_birth'] . '"';
                                        } ?>
                                        onchange="calcularEdad_plus(this.value, '#edad')">
                                    <label for="fechanacimiento">Fecha de nacimiento</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="number" class="form-control" id="edad" placeholder="Edad" readonly disabled>
                                    <label for="edad">Edad</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="origen">
                                        <option value="0" selected>Seleccione un origen</option>
                                        <option value="Residente">Residente</option>
                                        <option value="Extranjero">Extranjero</option>
                                    </select>
                                    <label for="origen">Origen</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="paisnac"
                                        onchange="changeCountrySelect(this.value,'<?= $appPaisVersion ?>', 'depnac', 'muninac');">
                                        <option value="0" selected>Seleccione un país</option>
                                        <?php
                                        $selected = "";
                                        foreach (($paisesCatalogo ?? []) as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['pais_nacio'] == $fila['Abreviatura']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($fila["Pais"]);
                                            $codpais = $fila["Abreviatura"];
                                            echo '<option value="' . $codpais . '"  ' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="paisnac">País</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="depnac"
                                        onchange="buscar_municipios('buscar_municipios', '#muninac', this.value)">
                                        <option value="0" selected>Seleccione un departamento</option>
                                        <?php
                                        $selected = "";
                                        foreach (($departamentosCatalogo ?? []) as $departamento) {
                                            if ($bandera) {
                                                ($datos[0]['depa_nacio'] == $departamento['codigo_departamento']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($departamento["nombre"]);
                                            $codigo_departa = $departamento["codigo_departamento"];
                                            echo '<option value="' . $codigo_departa . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="depnac">Departamento</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="muninac">
                                        <option value="0" selected>Seleccione un municipio</option>
                                    </select>
                                    <label for="muninac">Municipio</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 col-sm-12">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="dirnac" placeholder="Dirección nacimiento" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['aldea'] . '"';
                                                                                                                            } ?>oninput="validateInputlibre(this)">
                                    <label for="dirnac">Dirección nacimiento</label>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-12" id="div_condicionMigratoria"
                                style="display: <?= ($bandera && $datos[0]['pais_nacio'] != 'GT') ? 'block' : 'none'; ?>;">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="condicionMigratoria">
                                        <option value="" selected>Seleccione una condición migratoria</option>
                                        <?php foreach ($condicionesMigratoriasCatalogo as $condicion): ?>
                                            <option value="<?php echo $condicion['id']; ?>" <?php echo ($bandera && $datos[0]['condicionMigratoria'] == $condicion['id']) ? 'selected' : ''; ?>>
                                                <?php echo $condicion['descripcion']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="condicionMigratoria">Condición migratoria</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- DOCUMENTO DE INDENTIFICACION -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Documento de identificación</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="docextend" onchange="changeSelectPaisExtiende(this.value)">
                                        <option value="0" selected disabled>Seleccione un país</option>
                                        <?php
                                        foreach (($paisesDocumentoExtendido ?? []) as $fila) {
                                            $selected = "";
                                            if ($bandera) {
                                                ($datos[0]['pais_extiende'] == $fila['id']) ? $selected = "selected" : $selected = "";
                                            } else {
                                                if ($fila['id'] == $paisVersionApp['id']) {
                                                    $selected = "selected";
                                                }
                                            }
                                            $nombre = ($fila["nombre"]);
                                            $codpais = $fila["id"];
                                            echo '<option value="' . $codpais . '"  ' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                        <option value="X">Extranjero</option>
                                    </select>
                                    <label for="docextend">Documento extendido en:</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="tipodoc" onchange="$('#numberdoc').trigger('input');">
                                        <?php
                                        $selected = "";
                                        foreach (($tiposIdentificacionCompletos ?? []) as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['tipo_identifica'] == $fila['codigo']) ? $selected = "selected" : $selected = "";
                                            } else {
                                                // if ($fila['predeterminado'] == '1') {
                                                //     $selected = "selected";
                                                // }
                                            }
                                            $nombre = ($fila["nombre"]);
                                            $codtipo = $fila["codigo"];
                                            echo '<option value="' . $codtipo . '"  ' . $selected . ' data-regex="' . $fila["mascara_regex"] . '">' . $nombre . '</option>';
                                        } ?>
                                        <!-- <option value="DPI" selected>DPI</option>
                                        <option value="PASAPORTE">Pasaporte</option> -->
                                    </select>
                                    <label for="tipodoc">Tipo de documento</label>
                                </div>
                            </div>
                            <!-- NEGROY DPI PRUEBAS -->
                            <?php
                            $dpi = "000000000000";
                            $cli = "1";
                            if ($bandera) {
                                $dpi = $datos[0]['no_identifica'] ?? "000000000000";
                                $cli = $datos[0]['idcod_cliente'] ?? "1";
                            } ?>

                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="numberdoc" placeholder="Número de documento"
                                        onblur="validateNumberDocument(this.value, '<?= $cli ?>')" value="<?= $dpi ?>"
                                        oninput="validateInputdpi(this)">
                                    <label for="numberdoc">Número de documento</label>
                                </div>
                            </div>
                        </div>

                        <div id="section_notification">
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="tipoidentri" onchange="updateTributario()">
                                        <option value="NIT" selected>NIT</option>
                                        <option value="CUI">CUI</option>
                                    </select>
                                    <label for="tipoidentri">Identificacion tributaria</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="numbernit" placeholder="Número tributario" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['no_tributaria'] . '"';
                                                                                                                            } ?>>
                                    <label for="numbernit">Número tributario</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-4">
                                <label for="actividadEconomicaSat" class="form-label">Actividad Económica</label>
                                <select class="form-select" id="actividadEconomicaSat" data-control="select2"
                                    data-placeholder="Seleccione una actividad económica">
                                    <option value="" selected>Seleccione una actividad económica</option>
                                    <?php foreach (($groupedActividades ?? []) as $clase): ?>
                                        <optgroup label="- <?= htmlspecialchars($clase['nombre_clase']); ?>">
                                            <?php foreach ($clase['actividades'] as $actividad): ?>
                                                <option value="<?= $actividad['id_actividad']; ?>" <?php if ($bandera && $actividad['id_actividad'] == $datos[0]['actividadEconomicaSat'])
                                                                                                        echo 'selected'; ?>>
                                                    *
                                                    <?= htmlspecialchars($actividad['codigo']) . ' ' . htmlspecialchars($actividad['nombre_actividad']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="number" class="form-control" id="afiliggs" placeholder="Afiliación IGGS" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['no_igss'] . '"';
                                                                                                                            } ?>oninput="validateInputlibre(this)">
                                    <label for="afiliggs">Afiliación IGGS</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="nacionalidad">
                                        <option value="0">Seleccione una nacionalidad</option>
                                        <?php
                                        $selected = "";
                                        foreach ($paisesCatalogo as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['nacionalidad'] == $fila['Abreviatura']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = decode_utf8($fila["Pais"]);
                                            $codpais = $fila["Abreviatura"];
                                            echo '<option value="' . $codpais . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="nacionalidad">Nacionalidad</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DOMICILIO -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Domicilio</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="condicion">
                                        <?php
                                        $selected = "";
                                        foreach ($negociosCatalogo as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['vivienda_Condi'] == $fila['id_Negocio']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($fila["Negocio"]);
                                            $codigo = $fila["id_Negocio"];
                                            echo '<option value="' . $codigo . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="condicion">Condición de vivienda</label>
                                </div>
                            </div>

                            <?php

                            $ano_reside = isset($datos[0]['ano_reside']) ? $datos[0]['ano_reside'] : "";
                            $current_year = date("Y"); // Obtiene el año actual
                            $ano_reside = $ano_reside ?: $current_year; // Usar el año actual si no hay año definido
                            ?>

                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-control" id="reside" onchange="calculateYears()">
                                        <?php
                                        for ($year = 1800; $year <= $current_year; $year++) {
                                            $selected = ($year == $ano_reside) ? 'selected' : '';
                                            echo "<option value=\"$year\" $selected>$year</option>";
                                        }
                                        ?>
                                    </select>
                                    <label for="reside">Reside desde</label>
                                </div>
                            </div>

                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="paisdom"
                                        onchange="changeCountrySelect(this.value,'<?= $appPaisVersion ?>', 'depdom', 'munidom');">
                                        <option value="0" selected>Seleccione un país</option>
                                        <?php
                                        $selected = "";
                                        foreach (($paisesCatalogo ?? []) as $fila) {
                                            if ($bandera) {
                                                $selected = ($datos[0]['id_pais_domicilio'] == $fila['Abreviatura']) ? "selected" : "";
                                            }
                                            $nombre = ($fila["Pais"]);
                                            $codpais = $fila["Abreviatura"];
                                            echo '<option value="' . $codpais . '"  ' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="paisdom">País</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="depdom"
                                        onchange="buscar_municipios('buscar_municipios', '#munidom', this.value)">
                                        <option value="0" selected>Seleccione un departamento</option>
                                        <?php
                                        $selected = "";
                                        foreach (($departamentosCatalogo ?? []) as $departamento) {
                                            if ($bandera) {
                                                ($datos[0]['depa_reside'] == $departamento['codigo_departamento']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($departamento["nombre"]);
                                            $codigo_departa = $departamento["codigo_departamento"];
                                            echo '<option value="' . $codigo_departa . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="depdom">Departamento</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="munidom">
                                        <option value="0" selected>Seleccione un municipio</option>
                                    </select>
                                    <label for="munidom">Municipio</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="dirviv" placeholder="Dirección de vivienda" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Direccion'] . '"';
                                                                                                                            } ?>oninput="validateInputlibre(this)">
                                    <label for="dirviv">Dirección de vivienda</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="refviv" placeholder="Referencia" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['aldea_reside'] . '"';
                                                                                                                    } ?>oninput="validateInputlibre(this)">
                                    <label for="refviv">Referencia</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6" x-show="paisVersionApp == 'GT'">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="zonaviv" placeholder="Zona" <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['zona'] . '"';
                                                                                                            } ?>oninput="validateInputlibre(this)">
                                    <label for="zonaviv">Zona</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="barrioviv" placeholder="Colonia o Barrio" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['barrio'] . '"';
                                                                                                                            } ?>oninput="validateInputlibre(this)">
                                    <label for="barrioviv">Colonia o Barrio</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="tel1" placeholder="Telefono 1" <?php if ($bandera) {
                                                                                                                    echo 'value="' . $datos[0]['tel_no1'] . '"';
                                                                                                                } ?>oninput="validateInputtelreF(this)">
                                    <label for="tel1">Telefono 1</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="tel2" placeholder="Telefono 2" <?php if ($bandera) {
                                                                                                                    echo 'value="' . $datos[0]['tel_no2'] . '"';
                                                                                                                } ?>oninput="validateInputtelreF(this)">
                                    <label for="tel2">Telefono 2</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-4 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="actpropio" onchange="ocultar_actuacion_propia(this.value)">
                                        <option value="1" selected>Si</option>
                                        <option value="2">No</option>
                                    </select>
                                    <label for="actpropio">¿Actua en nombre propio?</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-8 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="representante" placeholder="Representante" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['representante_name'] . '"';
                                                                                                                            } ?>oninput="validateInputlibre(this)">
                                    <label for="representante">Representante</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="actcalidad">
                                        <option value="ninguno" selected>Ninguno</option>
                                        <option value="mandatario">Mandatario</option>
                                        <option value="potestad">P. Potestad</option>
                                        <option value="tutor">Tutor</option>
                                        <option value="otros">Otros</option>
                                    </select>
                                    <label for="actcalidad">¿Calidad que actua?</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-3">
                                    <span class="fw-bold fs-5">Información PEP (Persona Expuesta Políticamente)</span>
                                </div>
                                <div class="alert alert-info" role="alert" style="font-size: 0.95rem;">
                                    <i class="fa-solid fa-circle-info me-2"></i>
                                    Persona Expuesta Políticamente (PEP): es quien ocupa o ha ocupado un cargo público relevante en
                                    Guatemala o en otro país, o tiene una función prominente en una organización internacional.
                                    Incluye también dirigentes de partidos políticos y personas que, por su posición, están
                                    expuestas a riesgos inherentes a su nivel jerárquico.
                                </div>
                            </div>
                        </div>
                        <div class="row align-items-center mb-3">
                            <div class="col-12 col-md-8 mx-auto">
                                <div class="badge text-bg-primary mb-2 w-100 text-wrap" data-bs-toggle="tooltip"
                                    data-bs-placement="top">
                                    ¿El cliente es PEP?
                                </div>
                                <div class="d-flex gap-4 justify-content-center mt-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="pep" id="pep_si" value="Si"
                                            onclick="showHideElement(['section_pep'], 'show');">
                                        <label class="form-check-label" for="pep_si">
                                            Sí
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="pep" id="pep_no" checked value="No"
                                            onclick="showHideElement(['section_pep'], 'hide');">
                                        <label class="form-check-label" for="pep_no">
                                            No
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 justify-content-center" id="section_pep"
                            style="display: <?= (!empty($datos[0]['PEP']) && $datos[0]['PEP'] === 'Si') ? 'block' : 'none'; ?>;">
                            <div class="col-12 col-md-12">
                                <div class="card shadow-sm border-primary">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pep_entidad" placeholder="Entidad"
                                                        <?php if (!empty($datosClientePep)) {
                                                            echo 'value="' . htmlspecialchars($datosClientePep[0]['entidad']) . '"';
                                                        } ?>>
                                                    <label for="pep_entidad">Entidad</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pep_puesto"
                                                        placeholder="Puesto que desempeña" <?php if (!empty($datosClientePep)) {
                                                                                                echo 'value="' . htmlspecialchars($datosClientePep[0]['puesto']) . '"';
                                                                                            } ?>>
                                                    <label for="pep_puesto">Puesto que desempeña</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="pep_pais">
                                                        <option value="" selected>Seleccione un país</option>
                                                        <?php foreach ($paisesCatalogo as $fila): ?>
                                                            <option value="<?= htmlspecialchars($fila['Abreviatura']) ?>" <?php if (!empty($datosClientePep) && $datosClientePep[0]['paisEntidad'] === $fila['Abreviatura'])
                                                                                                                                echo 'selected'; ?>>
                                                                <?= htmlspecialchars($fila['Pais']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label for="pep_pais">País</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="pep_origen_riqueza" class="form-label">Origen de riqueza</label>
                                                <select class="form-select" id="pep_origen_riqueza" multiple data-control="select2"
                                                    data-placeholder="Seleccione origen de riqueza">
                                                    <option value="" disabled selected>Seleccione origen de riqueza</option>
                                                    <?php foreach ($origenesRiquezaCatalogo as $origen): ?>
                                                        <option value="<?= htmlspecialchars($origen['id']) ?>" <?php if (!empty($datosClientePep) && in_array($origen['id'], array_column($origenesRiquezaPep, 'id_origen')))
                                                                                                                    echo 'selected'; ?>>
                                                            <?= htmlspecialchars($origen['descripcion']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pep_origen_riqueza_otro"
                                                        placeholder="Detalle otro origen de riqueza" <?php if (!empty($datosClientePep)) {
                                                                                                            echo 'value="' . htmlspecialchars($datosClientePep[0]['otroOrigen']) . '"';
                                                                                                        } ?>>
                                                    <label for="pep_origen_riqueza_otro">Detalle otro origen de riqueza</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row align-items-center mb-3">
                            <div class="col-12 col-md-8 mx-auto">
                                <div class="badge text-bg-primary mb-2 w-100 text-wrap" data-bs-toggle="tooltip"
                                    data-bs-placement="top">
                                    ¿La persona tiene parentesco con una PEP.?
                                </div>
                                <div class="d-flex gap-4 justify-content-center mt-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="pariente_pep" id="pariente_pep_si"
                                            value="Si" onclick="showHideElement(['section_pariente_pep'], 'show');">
                                        <label class="form-check-label" for="pariente_pep_si">
                                            Sí
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="pariente_pep" id="pariente_pep_no"
                                            checked value="No" onclick="showHideElement(['section_pariente_pep'], 'hide');">
                                        <label class="form-check-label" for="pariente_pep_no">
                                            No
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 justify-content-center" id="section_pariente_pep"
                            style="display: <?= (!empty($datos[0]['pariente_pep']) && $datos[0]['pariente_pep'] === 'Si') ? 'block' : 'none'; ?>;">
                            <div class="col-12 col-md-12">
                                <div class="card shadow-sm border-primary">
                                    <div class="card-body">
                                        <div class="row g-3 mb-2">
                                            <!-- Parentesco (select) -->
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="pariente_pep_parentesco">
                                                        <option value="" selected>Seleccione parentesco</option>
                                                        <?php foreach ($parentescoCatalogo as $parentesco) { ?>
                                                            <option value="<?= $parentesco['id_parent']; ?>" <?php if (!empty($datosParientePep) && $datosParientePep[0]['parentesco'] == $parentesco['id_parent'])
                                                                                                                    echo 'selected'; ?>>
                                                                <?= htmlspecialchars($parentesco['descripcion']); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                    <label for="pariente_pep_parentesco">Parentesco</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- Nombres -->
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_primer_nombre"
                                                        placeholder="Primer nombre" <?php if (!empty($datosParientePep)) {
                                                                                        echo 'value="' . htmlspecialchars($datosParientePep[0]['primerNombre']) . '"';
                                                                                    } ?>>
                                                    <label for="pariente_pep_primer_nombre">Primer nombre</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_segundo_nombre"
                                                        placeholder="Segundo nombre" <?php if (!empty($datosParientePep)) {
                                                                                            echo 'value="' . htmlspecialchars($datosParientePep[0]['segundoNombre']) . '"';
                                                                                        } ?>>
                                                    <label for="pariente_pep_segundo_nombre">Segundo nombre</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_otros_nombres"
                                                        placeholder="Otros nombres" <?php if (!empty($datosParientePep)) {
                                                                                        echo 'value="' . htmlspecialchars($datosParientePep[0]['otrosNombres']) . '"';
                                                                                    } ?>>
                                                    <label for="pariente_pep_otros_nombres">Otros nombres</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- Apellidos -->
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_primer_apellido"
                                                        placeholder="Primer apellido" <?php if (!empty($datosParientePep)) {
                                                                                            echo 'value="' . htmlspecialchars($datosParientePep[0]['primerApellido']) . '"';
                                                                                        } ?>>
                                                    <label for="pariente_pep_primer_apellido">Primer apellido</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_segundo_apellido"
                                                        placeholder="Segundo apellido" <?php if (!empty($datosParientePep)) {
                                                                                            echo 'value="' . htmlspecialchars($datosParientePep[0]['segundoApellido']) . '"';
                                                                                        } ?>>
                                                    <label for="pariente_pep_segundo_apellido">Segundo apellido</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_apellido_casada"
                                                        placeholder="Apellido de casada (sin 'DE', solo el apellido)" <?php if (!empty($datosParientePep)) {
                                                                                                                            echo 'value="' . htmlspecialchars($datosParientePep[0]['apellidoCasada']) . '"';
                                                                                                                        } ?>>
                                                    <label for="pariente_pep_apellido_casada">Apellido de casada <span
                                                            class="text-info">(sin 'DE')</span></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-2">
                                            <!-- Sexo y Condición -->
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <select class="form-select" id="pariente_pep_sexo">
                                                        <option value="" selected>Seleccione sexo</option>
                                                        <option value="M" <?php if (!empty($datosParientePep) && $datosParientePep[0]['sexo'] === 'M')
                                                                                echo 'selected'; ?>>Masculino
                                                        </option>
                                                        <option value="F" <?php if (!empty($datosParientePep) && $datosParientePep[0]['sexo'] === 'F')
                                                                                echo 'selected'; ?>>Femenino
                                                        </option>
                                                    </select>
                                                    <label for="pariente_pep_sexo">Sexo</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <select class="form-select" id="pariente_pep_condicion">
                                                        <option value="" selected>Seleccione condición</option>
                                                        <option value="N" <?php if (!empty($datosParientePep) && $datosParientePep[0]['condicion'] === 'N')
                                                                                echo 'selected'; ?>>Nacional
                                                        </option>
                                                        <option value="E" <?php if (!empty($datosParientePep) && $datosParientePep[0]['condicion'] === 'E')
                                                                                echo 'selected'; ?>>Extranjero
                                                        </option>
                                                    </select>
                                                    <label for="pariente_pep_condicion">Condición</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- Entidad y Puesto -->
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_entidad"
                                                        placeholder="Entidad" <?php if (!empty($datosParientePep)) {
                                                                                    echo 'value="' . htmlspecialchars($datosParientePep[0]['entidad']) . '"';
                                                                                } ?>>
                                                    <label for="pariente_pep_entidad">Entidad</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="pariente_pep_puesto"
                                                        placeholder="Puesto que desempeña" <?php if (!empty($datosParientePep)) {
                                                                                                echo 'value="' . htmlspecialchars($datosParientePep[0]['puesto']) . '"';
                                                                                            } ?>>
                                                    <label for="pariente_pep_puesto">Puesto que desempeña</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- País -->
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="pariente_pep_pais">
                                                        <option value="" selected>Seleccione país</option>
                                                        <?php foreach ($paisesCatalogo as $fila): ?>
                                                            <option value="<?= htmlspecialchars($fila['Abreviatura']) ?>" <?php if (!empty($datosParientePep) && $datosParientePep[0]['pais'] === $fila['Abreviatura'])
                                                                                                                                echo 'selected'; ?>>
                                                                <?= htmlspecialchars($fila['Pais']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label for="pariente_pep_pais">País</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row align-items-center mb-3">
                            <div class="col-12 col-md-8 mx-auto">
                                <div class="badge text-bg-primary mb-2 w-100 text-wrap" data-bs-toggle="tooltip"
                                    data-bs-placement="top">
                                    ¿La persona es asociado cercano de una PEP.?
                                </div>
                                <div class="d-flex gap-4 justify-content-center mt-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="asociado_pep" id="asociado_pep_si"
                                            value="Si" onclick="showHideElement(['section_asociado_pep'], 'show');">
                                        <label class="form-check-label" for="asociado_pep_si">
                                            Sí
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="asociado_pep" id="asociado_pep_no"
                                            checked value="No" onclick="showHideElement(['section_asociado_pep'], 'hide');">
                                        <label class="form-check-label" for="asociado_pep_no">
                                            No
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 justify-content-center" id="section_asociado_pep"
                            style="display: <?= (!empty($datos[0]['asociado_pep']) && $datos[0]['asociado_pep'] === 'Si') ? 'block' : 'none'; ?>;">
                            <div class="col-12 col-md-12">
                                <div class="card shadow-sm border-primary">
                                    <div class="card-body">
                                        <div class="row g-3 mb-2">
                                            <!-- Parentesco (select) -->
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="asociado_pep_motivo"
                                                        onchange="if(this.value == '5') { showHideElement(['div_asociado_pep_otro_motivo'], 'show'); } else { showHideElement(['div_asociado_pep_otro_motivo'], 'hide'); }">
                                                        <option value="" selected>Seleccione motivo</option>
                                                        <?php foreach ($motivosCatalogo as $motivo) { ?>
                                                            <option value="<?= $motivo['id']; ?>" <?php if (!empty($datosAsociadoPep) && $datosAsociadoPep[0]['motivoAsociacion'] == $motivo['id'])
                                                                                                        echo 'selected'; ?>>
                                                                <?= htmlspecialchars($motivo['descripcion']); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                    <label for="asociado_pep_motivo">Motivo</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6" id="div_asociado_pep_otro_motivo"
                                                style="display: <?= (!empty($datosAsociadoPep) && $datosAsociadoPep[0]['motivoAsociacion'] == '5') ? 'block' : 'none'; ?>;">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_otro_motivo"
                                                        placeholder="Otro motivo" <?php if (!empty($datosAsociadoPep)) {
                                                                                        echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['detalleOtro']) . '"';
                                                                                    } ?>>
                                                    <label for="asociado_pep_otro_motivo">Otro motivo</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- Nombres -->
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_primer_nombre"
                                                        placeholder="Primer nombre" <?php if (!empty($datosAsociadoPep)) {
                                                                                        echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['primerNombre']) . '"';
                                                                                    } ?>>
                                                    <label for="asociado_pep_primer_nombre">Primer nombre</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_segundo_nombre"
                                                        placeholder="Segundo nombre" <?php if (!empty($datosAsociadoPep)) {
                                                                                            echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['segundoNombre']) . '"';
                                                                                        } ?>>
                                                    <label for="asociado_pep_segundo_nombre">Segundo nombre</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_otros_nombres"
                                                        placeholder="Otros nombres" <?php if (!empty($datosAsociadoPep)) {
                                                                                        echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['otrosNombres']) . '"';
                                                                                    } ?>>
                                                    <label for="asociado_pep_otros_nombres">Otros nombres</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- Apellidos -->
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_primer_apellido"
                                                        placeholder="Primer apellido" <?php if (!empty($datosAsociadoPep)) {
                                                                                            echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['primerApellido']) . '"';
                                                                                        } ?>>
                                                    <label for="asociado_pep_primer_apellido">Primer apellido</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_segundo_apellido"
                                                        placeholder="Segundo apellido" <?php if (!empty($datosAsociadoPep)) {
                                                                                            echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['segundoApellido']) . '"';
                                                                                        } ?>>
                                                    <label for="asociado_pep_segundo_apellido">Segundo apellido</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_apellido_casada"
                                                        placeholder="Apellido de casada (sin 'DE', solo el apellido)" <?php if (!empty($datosAsociadoPep)) {
                                                                                                                            echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['apellidoCasada']) . '"';
                                                                                                                        } ?>>
                                                    <label for="asociado_pep_apellido_casada">Apellido de casada <span
                                                            class="text-info">(sin 'DE')</span></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-2">
                                            <!-- Sexo y Condición -->
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <select class="form-select" id="asociado_pep_sexo">
                                                        <option value="" selected>Seleccione sexo</option>
                                                        <option value="M" <?php if (!empty($datosAsociadoPep) && $datosAsociadoPep[0]['sexo'] === 'M')
                                                                                echo 'selected'; ?>>Masculino
                                                        </option>
                                                        <option value="F" <?php if (!empty($datosAsociadoPep) && $datosAsociadoPep[0]['sexo'] === 'F')
                                                                                echo 'selected'; ?>>Femenino
                                                        </option>
                                                    </select>
                                                    <label for="asociado_pep_sexo">Sexo</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="form-floating">
                                                    <select class="form-select" id="asociado_pep_condicion">
                                                        <option value="" selected>Seleccione condición</option>
                                                        <option value="N" <?php if (!empty($datosAsociadoPep) && $datosAsociadoPep[0]['condicion'] === 'N')
                                                                                echo 'selected'; ?>>Nacional
                                                        </option>
                                                        <option value="E" <?php if (!empty($datosAsociadoPep) && $datosAsociadoPep[0]['condicion'] === 'E')
                                                                                echo 'selected'; ?>>Extranjero
                                                        </option>
                                                    </select>
                                                    <label for="asociado_pep_condicion">Condición</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- Entidad y Puesto -->
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_entidad"
                                                        placeholder="Entidad" <?php if (!empty($datosAsociadoPep)) {
                                                                                    echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['entidad']) . '"';
                                                                                } ?>>
                                                    <label for="asociado_pep_entidad">Entidad</label>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" class="form-control" id="asociado_pep_puesto"
                                                        placeholder="Puesto que desempeña" <?php if (!empty($datosAsociadoPep)) {
                                                                                                echo 'value="' . htmlspecialchars($datosAsociadoPep[0]['puesto']) . '"';
                                                                                            } ?>>
                                                    <label for="asociado_pep_puesto">Puesto que desempeña</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-2">
                                            <!-- País -->
                                            <div class="col-12 col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="asociado_pep_pais">
                                                        <option value="" selected>Seleccione país</option>
                                                        <?php foreach ($paisesCatalogo as $fila): ?>
                                                            <option value="<?= htmlspecialchars($fila['Abreviatura']) ?>" <?php if (!empty($datosAsociadoPep) && $datosAsociadoPep[0]['pais'] === $fila['Abreviatura'])
                                                                                                                                echo 'selected'; ?>>
                                                                <?= htmlspecialchars($fila['Pais']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label for="asociado_pep_pais">País</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ADICIONAL -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Adicional</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="otranacionalidad">
                                        <option value="0">Seleccione una nacionalidad</option>
                                        <?php
                                        $selected = "";
                                        foreach ($paisesCatalogo as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['otra_nacion'] == $fila['Abreviatura']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = decode_utf8($fila["Pais"]);
                                            $codpais = $fila["Abreviatura"];
                                            echo '<option value="' . $codpais . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="otranacionalidad">Otra nacionalidad</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="etnia">
                                        <?php
                                        $selected = "";
                                        foreach ($etniaCatalogo as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['idioma'] == $fila['id']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($fila["nombre"]);
                                            $id = $fila["id"];
                                            echo '<option value="' . $id . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="etnia">Etnia idioma</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="religion">
                                        <?php
                                        $selected = "";
                                        foreach ($religionCatalogo as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['id_religion'] == $fila['id']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($fila["nombre"]);
                                            $id = $fila["id"];
                                            echo '<option value="' . $id . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="etnia">Religión</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="educacion">
                                        <option value="no educacion">No Educación</option>
                                        <option value="primaria">Primaria</option>
                                        <option value="basico">Basico</option>
                                        <option value="diversificado">Diversificado</option>
                                        <option value="tecnico">Tecnico</option>
                                        <option value="universidad">Universidad</option>
                                        <option value="master">Master</option>
                                    </select>
                                    <label for="educacion">Educación</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="relinsti">
                                        <?php
                                        $selected = "";
                                        foreach ($relacionInstitucional as $fila) {
                                            if ($bandera) {
                                                ($datos[0]['relacion_institucional'] == $fila['nombre']) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($fila["nombre"]);
                                            $id = $fila["id"];
                                            echo '<option value="' . $nombre . '"' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="relinsti">Relación institucional</label>
                                </div>
                            </div>
                        </div>
                        <!--inicio Del Row de referencia -->
                        <div class="row">
                            <!-- Referencia 1 -->
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="row">
                                    <!-- Nombre -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refn1" placeholder="Ref. Nombre" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Nomb_Ref1'] . '"';
                                                                                                                            } ?>
                                                oninput="validateInputlibre(this)">
                                            <label for="refn1">Ref. Nombre 1</label>
                                        </div>
                                    </div>
                                    <!-- Teléfono -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="ref1" placeholder="Ref. Teléfono" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Tel_Ref1'] . '"';
                                                                                                                            } ?>
                                                oninput="validateInputtelreF(this)" title="Ejemplo: +502 12345678 / 12345678">
                                            <label for="ref1">Ref. Teléfono 1</label>
                                        </div>
                                    </div>
                                    <!-- Parentesco -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <select class="form-select" id="refp1">
                                                <option value="" selected>Seleccione un parentesco</option>
                                                <?php foreach ($parentescoCatalogo as $parentesco) { ?>
                                                    <option value="<?php echo $parentesco['id_parent']; ?>" <?php if ($bandera && $datos[0]['Parentesco_Ref1'] == $parentesco['id_parent'])
                                                                                                                echo 'selected'; ?>>
                                                        <?php echo $parentesco['descripcion']; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                            <label for="refp1">Ref. Parentesco 1</label>
                                        </div>
                                    </div>
                                    <!-- Dirección -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refd1" placeholder="Ref. Dirección" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Dir_Ref1'] . '"';
                                                                                                                            } ?>
                                                oninput="validateInputlibre(this)">
                                            <label for="refd1">Ref. Dirección 1</label>
                                        </div>
                                    </div>
                                    <!-- Referencia de Dirección -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refdir1"
                                                placeholder="Referencia de Dirección" <?php if ($bandera) {
                                                                                            echo 'value="' . $datos[0]['Ref_Dir1'] . '"';
                                                                                        } ?> oninput="validateInputlibre(this)">
                                            <label for="refdir1">Referencia de Dirección 1</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Referencia 2 -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="row">
                                    <!-- Nombre -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refn2" placeholder="Ref. Nombre 2" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Nomb_Ref2'] . '"';
                                                                                                                            } ?>
                                                oninput="validateInputlibre(this)">
                                            <label for="refn2">Ref. Nombre 2</label>
                                        </div>
                                    </div>
                                    <!-- Teléfono -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="ref2" placeholder="Ref. Teléfono 2" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Tel_Ref2'] . '"';
                                                                                                                            } ?>
                                                oninput="validateInputtelreF(this)" title="Ejemplo: +502 12345678 / 12345678">
                                            <label for="ref2">Ref. Teléfono 2</label>
                                        </div>
                                    </div>
                                    <!-- Parentesco -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <select class="form-select" id="refp2">
                                                <option value="" selected>Seleccione un parentesco</option>
                                                <?php foreach ($parentescoCatalogo as $parentesco) { ?>
                                                    <option value="<?php echo $parentesco['id_parent']; ?>" <?php if ($bandera && $datos[0]['Parentesco_Ref2'] == $parentesco['id_parent'])
                                                                                                                echo 'selected'; ?>>
                                                        <?php echo $parentesco['descripcion']; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                            <label for="refp2">Ref. Parentesco 2</label>
                                        </div>
                                    </div>
                                    <!-- Dirección -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refd2" placeholder="Ref. Dirección 2" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . $datos[0]['Dir_Ref2'] . '"';
                                                                                                                                } ?>
                                                oninput="validateInputlibre(this)">
                                            <label for="refd2">Ref. Dirección 2</label>
                                        </div>
                                    </div>
                                    <!-- Referencia de Dirección -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refdir2"
                                                placeholder="Referencia de Dirección" <?php if ($bandera) {
                                                                                            echo 'value="' . $datos[0]['Ref_Dir2'] . '"';
                                                                                        } ?> oninput="validateInputlibre(this)">
                                            <label for="refdir2">Referencia de Dirección 2</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Referencia 3 -->
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="row">
                                    <!-- Nombre -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refn3" placeholder="Ref. Nombre 3" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Nomb_Ref3'] . '"';
                                                                                                                            } ?>
                                                oninput="validateInputlibre(this)">
                                            <label for="refn3">Ref. Nombre 3</label>
                                        </div>
                                    </div>
                                    <!-- Teléfono -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="ref3" placeholder="Ref. Teléfono 3" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['Tel_Ref3'] . '"';
                                                                                                                            } ?>
                                                oninput="validateInputtelreF(this)" title="Ejemplo: +502 12345678 / 12345678">
                                            <label for="ref3">Ref. Teléfono 3</label>
                                        </div>
                                    </div>
                                    <!-- Parentesco -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <select class="form-select" id="refp3">
                                                <option value="" selected>Seleccione un parentesco</option>
                                                <?php foreach ($parentescoCatalogo as $parentesco) { ?>
                                                    <option value="<?php echo $parentesco['id_parent']; ?>" <?php if ($bandera && $datos[0]['Parentesco_Ref3'] == $parentesco['id_parent'])
                                                                                                                echo 'selected'; ?>>
                                                        <?php echo $parentesco['descripcion']; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                            <label for="refp3">Ref. Parentesco 3</label>
                                        </div>
                                    </div>
                                    <!-- Dirección -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refd3" placeholder="Ref. Dirección 3" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . $datos[0]['Dir_Ref3'] . '"';
                                                                                                                                } ?>
                                                oninput="validateInputlibre(this)">
                                            <label for="refd3">Ref. Dirección 3</label>
                                        </div>
                                    </div>
                                    <!-- Referencia de Dirección -->
                                    <div class="col-12">
                                        <div class="form-floating mb-2 mt-2">
                                            <input type="text" class="form-control" id="refdir3"
                                                placeholder="Referencia de Dirección" <?php if ($bandera) {
                                                                                            echo 'value="' . $datos[0]['Ref_Dir3'] . '"';
                                                                                        } ?> oninput="validateInputlibre(this)">
                                            <label for="refdir3">Referencia de Dirección 3</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--Final de row de referencia-->
                        <div class="row m-1">
                            <div class="col-12 col-sm-12 col-md-4 border border-primary">
                                <div class="row">
                                    <div class="col-12 mt-1">
                                        <span class="badge text-bg-primary">¿Sabe leer?</span>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="leer" id="flexRadioDefault1" checked
                                                value="Si">
                                            <label class="form-check-label" for="flexRadioDefault1">
                                                Si
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="leer" id="flexRadioDefault2"
                                                value="No">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                No
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4 border border-primary">
                                <div class="row">
                                    <div class="col-12 mt-1">
                                        <span class="badge text-bg-primary">¿Sabe escribir?</span>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="escribir" id="flexRadioDefault1"
                                                checked value="Si">
                                            <label class="form-check-label" for="flexRadioDefault1">
                                                Si
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="escribir" id="flexRadioDefault2"
                                                value="No">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                No
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4 border border-primary">
                                <div class="row">
                                    <div class="col-12 mt-1">
                                        <span class="badge text-bg-primary">Firma</span>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="firma" id="flexRadioDefault1" checked
                                                value="Si">
                                            <label class="form-check-label" for="flexRadioDefault1">
                                                Si
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="firma" id="flexRadioDefault2"
                                                value="No">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                No
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row m-1">

                            <div class="col-12 col-sm-12 col-md-4 border border-primary">
                                <div class="row">
                                    <div class="col-12 mt-1">
                                        <span class="badge text-bg-primary" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="La persona individual o jurídica, nacional o extranjera, que sin importar la modalidad de la adquisición pública, provea o venda bienes, suministros, obras, servicios o arrendamientos al Estado o a cualquiera de las entidades, instituciones o sujetos indicados en el artículo 1 de la Ley de Contrataciones del Estado, por valor que exceda a novecientos mil quetzales (Q900,000.00), en uno o varios contratos, no importando la modalidad de adquisición pública.">
                                            ¿El cliente es CPE?
                                        </span>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="cpe" id="flexRadioDefault1" checked
                                                value="Si">
                                            <label class="form-check-label" for="flexRadioDefault1">
                                                Si
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="cpe" id="flexRadioDefault2"
                                                value="No">
                                            <label class="form-check-label" for="flexRadioDefault2">
                                                No
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4 border border-primary">
                                <div class="row">
                                    <div class="col-12 mt-1">
                                        <span class="badge text-bg-primary" data-bs-toggle="tooltip" data-bs-placement="top">
                                            Es empleado?
                                        </span>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="esEmpleado" id="esEmpleadoSi"
                                                value="Si">
                                            <label class="form-check-label" for="esEmpleadoSi">
                                                Si
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="esEmpleado" id="esEmpleadoNo"
                                                value="No" checked>
                                            <label class="form-check-label" for="esEmpleadoNo">
                                                No
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-3">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="number" class="form-control" id="hijos" placeholder="No hijos" <?php if ($bandera) {
                                                                                                                    echo 'value="' . $datos[0]['hijos'] . '"';
                                                                                                                } ?>>
                                    <label for="hijos">No. hijos</label>
                                </div>
                            </div>
                            <div class="col-5">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="number" class="form-control" id="dependencia"
                                        placeholder="número de personas de relación de dependencia del cliente" <?php if ($bandera) {
                                                                                                                    echo 'value="' . $datos[0]['dependencia'] . '"';
                                                                                                                } ?>>
                                    <label for="dependencia">Número de personas de relación de dependencia del cliente</label>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="codinterno" placeholder="Código de control interno"
                                        <?php if ($bandera) {
                                            echo 'value="' . $datos[0]['control_interno'] . '"';
                                        } ?>>
                                    <label for="codinterno">Código de control interno</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="observaciones">Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="4"
                                        placeholder="Ingrese sus observaciones aquí"><?= $bandera ? $datos[0]['observaciones'] : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="container" style="max-width: 100% !important;">
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                            <?php if ($isNewCustomer && $status) { ?>
                                <button class="btn btn-outline-success mt-2"
                                    onclick="obtiene_plus2([
                                        `nom1`,`nom2`,`nom3`,
                                        `ape1`,`ape2`,`ape3`,
                                        `profesion`,`email`,`conyugue`,
                                        `fechanacimiento`,`dirnac`,`edad`,
                                        `numberdoc`,`numbernit`,`afiliggs`,
                                        `reside`,`dirviv`,`refviv`,
                                        `representante`,
                                        /* Referencia 1 (sin parentesco) */ `refn1`,`ref1`,
                                        /* Referencia 2 (sin parentesco) */ `refn2`,`ref2`,
                                        /* Referencia 3 (sin parentesco) */ `refn3`,`ref3`,
                                        `tel1`,`tel2`,`telconyuge`,`zonaviv`,`barrioviv`,
                                        `hijos`,`dependencia`,`codinterno`,`observaciones`,
                                        `refd1`,`refdir1`,`refd2`,`refdir2`,`refd3`,`refdir3`,
                                        `pep_entidad`,`pep_puesto`,`pep_origen_riqueza_otro`,
                                        `pariente_pep_primer_apellido`,`pariente_pep_segundo_apellido`,`pariente_pep_apellido_casada`,
                                        `pariente_pep_primer_nombre`, `pariente_pep_segundo_nombre`, `pariente_pep_otros_nombres`,
                                        `pariente_pep_entidad`, `pariente_pep_puesto`, `asociado_pep_otro_motivo`,
                                        `asociado_pep_primer_apellido`,`asociado_pep_segundo_apellido`,`asociado_pep_apellido_casada`,
                                        `asociado_pep_primer_nombre`, `asociado_pep_segundo_nombre`, `asociado_pep_otros_nombres`,
                                        `asociado_pep_entidad`, `asociado_pep_puesto`,`fechaingreso`
                                ], [
                                    `genero`,`estcivil`,`origen`,`paisnac`,`depnac`,`muninac`,
                                    `docextend`,`tipodoc`,`tipoidentri`,`nacionalidad`,`condicion`,
                                    `depdom`,`munidom`,`actpropio`,`actcalidad`,`otranacionalidad`,
                                    `etnia`,`religion`,`educacion`,`relinsti`,`agencidplus`,
                                    `refp1`,`refp2`,`refp3`,`actividadEconomicaSat`,`pep_pais`,
                                    `pariente_pep_parentesco`,`pariente_pep_sexo`,`pariente_pep_condicion`,`pariente_pep_pais`,
                                    `asociado_pep_motivo`,`asociado_pep_sexo`,`asociado_pep_condicion`,`asociado_pep_pais`,
                                    `condicionMigratoria`,'paisdom'
                                ], [
                                    `leer`,`escribir`,`firma`,`pep`,`cpe`,`tipo_cliente`,`pariente_pep`,`asociado_pep`,`esEmpleado`
                                ], 
                                `create_cliente_natural`, `0`, ['<?= $draftId ?? 0; ?>',$('#pep_origen_riqueza').val()])">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar cliente
                                </button>
                                <?php if ($showDraftCliente == 1) { ?>
                                    <button class="btn btn-outline-secondary mt-2" onclick="obtiene_plus2([`nom1`,`nom2`,`nom3`,`ape1`,`ape2`,`ape3`,`profesion`,`email`,`conyugue`,
                                                        `fechanacimiento`,`dirnac`,`edad`,`numberdoc`,`numbernit`,`afiliggs`,
                                                        `reside`,`dirviv`,`refviv`,`representante`,`refn1`,`ref1`,`refn2`,`ref2`,
                                                        `refn3`,`ref3`,`tel1`,`tel2`,`telconyuge`,`zonaviv`,`barrioviv`,
                                                        `hijos`,`dependencia`,`codinterno`,`observaciones`,
                                                        `refd1`,`refdir1`,`refd2`,`refdir2`,`refd3`,`refdir3`], [
                                                        `genero`,`estcivil`,`origen`,`paisnac`,`depnac`,`muninac`,
                                                        `docextend`,`tipodoc`,`tipoidentri`,`nacionalidad`,`condicion`,
                                                        `depdom`,`munidom`,`actpropio`,`actcalidad`,`otranacionalidad`,
                                                        `etnia`,`religion`,`educacion`,`relinsti`,`agencidplus`,`refp1`,`refp2`,`refp3`,`actividadEconomicaSat`], [
                                                        `leer`,`escribir`,`firma`,`pep`,`cpe`,`tipo_cliente`],
                                                        `create_cliente_draft`, `0`, ['<?= $draftId ?? 0; ?>'])">
                                        <i class="fa-regular fa-floppy-disk me-2"></i>Guardar borrador
                                    </button>
                                <?php } ?>
                                <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2_plus('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                            <?php } ?>
                            <?php if (!$isNewCustomer && $status) { ?>
                                <button class="btn btn-outline-primary mt-2"
                                    onclick="obtiene_plus([
                                        `nom1`,`nom2`,`nom3`,
                                        `ape1`,`ape2`,`ape3`,
                                        `profesion`,`email`,`conyugue`,
                                        `fechanacimiento`,`dirnac`,`edad`,
                                        `numberdoc`,`numbernit`,`afiliggs`,
                                        `reside`,`dirviv`,`refviv`,
                                        `representante`,
                                        /* Referencia 1 (sin parentesco) */
                                        `refn1`,`ref1`,
                                        /* Referencia 2 (sin parentesco) */
                                        `refn2`,`ref2`,
                                        /* Referencia 3 (sin parentesco) */
                                        `refn3`,`ref3`,
                                        `tel1`,`tel2`,`telconyuge`,`zonaviv`,`barrioviv`,
                                        `hijos`,`dependencia`,`codinterno`,`observaciones`,
                                        `refd1`,`refdir1`,`refd2`,`refdir2`,`refd3`,`refdir3`,
                                         `pep_entidad`,`pep_puesto`,`pep_origen_riqueza_otro`,
                                         `pariente_pep_primer_apellido`,`pariente_pep_segundo_apellido`,`pariente_pep_apellido_casada`,
                                        `pariente_pep_primer_nombre`, `pariente_pep_segundo_nombre`, `pariente_pep_otros_nombres`,
                                        `pariente_pep_entidad`, `pariente_pep_puesto`, `asociado_pep_otro_motivo`,
                                        `asociado_pep_primer_apellido`,`asociado_pep_segundo_apellido`,`asociado_pep_apellido_casada`,
                                        `asociado_pep_primer_nombre`, `asociado_pep_segundo_nombre`, `asociado_pep_otros_nombres`,
                                        `asociado_pep_entidad`, `asociado_pep_puesto`, `nuevaFechaActualizacion`

                                    ], [
                                        `genero`,`estcivil`,`origen`,`paisnac`,`depnac`,`muninac`,
                                        `docextend`,`tipodoc`,`tipoidentri`,`nacionalidad`,`condicion`,
                                        `depdom`,`munidom`,`actpropio`,`actcalidad`,`otranacionalidad`,
                                        `etnia`,`religion`,`educacion`,`relinsti`,`agencidplus`,
                                        /* Campos de parentesco */
                                        `refp1`,`refp2`,`refp3`,`actividadEconomicaSat`,`pep_pais`,
                                        `pariente_pep_parentesco`,`pariente_pep_sexo`,`pariente_pep_condicion`,`pariente_pep_pais`,
                                        `asociado_pep_motivo`,`asociado_pep_sexo`,`asociado_pep_condicion`,`asociado_pep_pais`,
                                        `condicionMigratoria`,'paisdom','fechaingreso'
                                    ], [
                                        `leer`,`escribir`,`firma`,`pep`,`cpe`,`tipo_cliente`,`pariente_pep`,`asociado_pep`,`esEmpleado`     
                                    ], 
                                    `update_cliente_natural`, '<?= $codcliente; ?>', ['<?= $codcliente; ?>', getAlpineData('#divUpdateDate','showSectionUpdate'),$('#pep_origen_riqueza').val()])">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Actualizar cliente
                                </button>
                                <button type="button" class="btn btn-outline-danger mt-2"
                                    onclick="printdiv('Editar_Cliente', '#cuadro', 'clientes_001', '0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                            <?php } ?>


                            <!-- Botón para solicitar crédito -->
                            <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>

            </div>
            <script>
                function changeCountrySelect(valor, appPaisVersion, depaid, muniaid) {
                    if (valor == "GT" || valor == "MX") {
                        habilitar_deshabilitar([depaid, muniaid], []);
                        obtiene_plus([], [], [], 'loadDepartamentosByPais', valor, [valor], function(data) {
                            // console.log(data);
                            $('#' + depaid).html(data.htmldata);
                        });
                    } else {
                        habilitar_deshabilitar([], [depaid, muniaid]);
                    }
                    if (depaid == 'depnac') {
                        if (appPaisVersion == valor) {
                            showHideElement(["div_condicionMigratoria"], "hide");
                        } else {
                            showHideElement(["div_condicionMigratoria"], "show");
                        }
                    }

                }

                function changeSelectPaisExtiende(valor) {
                    if (valor === "X") {
                        habilitar_deshabilitar(
                            [],
                            ["tipoidentri", "numbernit", "actividadEconomicaSat"]
                        );
                        //SELECCIONAR DE tipodoc PASAPORTE
                        $('#tipodoc').val('PASAPORTE');
                        // $('#tipodoc').trigger('change');

                    } else {
                        habilitar_deshabilitar(
                            ["tipoidentri", "numbernit", "actividadEconomicaSat"],
                            []
                        );
                        obtiene_plus([], [], [], 'loadDocumentosByPais', valor, [valor], function(data) {
                            // console.log(data);
                            $('#tipodoc').html(data.htmldata);
                        });
                    }
                    $('#numberdoc').trigger('input');
                }

                // Inicializa los tooltips de Bootstrap y aplica estilos personalizados
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                    var tooltip = new bootstrap.Tooltip(tooltipTriggerEl, {
                        customClass: 'custom-tooltip'
                    });
                    return tooltip;
                });

                function validateInputname(input) {
                    var inputValue = input.value;
                    var trimmedValue = inputValue.trim();

                    // Verificar si el valor no está vacío
                    if (trimmedValue === '') {
                        input.classList.remove('is-valid', 'is-invalid', 'has-leading-or-trailing-space');
                    } else {
                        // Verificar si el valor cumple con el patrón de solo letras
                        if (/^[A-Za-zñÑáéíóúÁÉÍÓÚ]+$/.test(trimmedValue)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }

                        // Verificar si hay espacios al inicio o al final
                        if (inputValue !== trimmedValue) {
                            input.classList.add('has-leading-or-trailing-space');
                        } else {
                            input.classList.remove('has-leading-or-trailing-space');
                        }
                    }
                }


                function validateInputprofesion(input) {
                    var inputValue = input.value.trim();
                    if (inputValue === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        if (/^[A-Za-zñÑáéíóúÁÉÍÓÚ ]+$/.test(inputValue)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function validateInputlastname(input) {
                    var inputValue = input.value.trim();

                    if (inputValue === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {

                        if (/^[A-Za-zñÑáéíóúÁÉÍÓÚ, ]+$/.test(inputValue)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function toggleConyugueInput() {
                    var estadoCivilSelect = document.getElementById('estcivil');
                    var conyugueInput = document.getElementById('conyugue');
                    var telconyugueInput = document.getElementById('telconyuge');

                    if (estadoCivilSelect.value === 'CASADO') {
                        conyugueInput.disabled = false;
                        telconyugueInput.disabled = false;
                    } else {
                        conyugueInput.disabled = true;
                        telconyugueInput.disabled = true;
                    }
                }

                function validateInput(input) {
                    var inputValue = input.value.trim();
                    if (inputValue === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        if (/^[A-Za-zñÑáéíóúÁÉÍÓÚ,]+$/.test(inputValue)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function validateEmail(input) {
                    var email = input.value.trim();
                    if (email === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (regex.test(email)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function validateInputlibre(input) {
                    var inputValue = input.value.trim();
                    if (inputValue === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        if (/^[a-zA-ZñÑáéíóúÁÉÍÓÚ,0-9\-\.\/\,\s]+$/.test(inputValue)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function validateInputdpi(input) {
                    var inputValue = input.value.trim();
                    var regexPattern = $('#tipodoc option:selected').data('regex');

                    // console.log("Regex pattern:", regexPattern);
                    // console.log("Input value:", inputValue);

                    // Limpiar delimitadores si existen
                    regexPattern = regexPattern.replace(/^\/|\/$/g, '');
                    if (inputValue === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        try {
                            const regex = new RegExp(regexPattern);
                            const isValid = regex.test(inputValue);
                            // console.log("Validation result:", isValid);

                            if (isValid) {
                                input.classList.remove('is-invalid');
                                input.classList.add('is-valid');
                            } else {
                                input.classList.remove('is-valid');
                                input.classList.add('is-invalid');
                            }
                        } catch (error) {
                            console.error("Invalid regex pattern:", error);
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function validateNumberDocument(number, cli) {

                    obtiene_plus([], [], [], 'validateNumberDocument', '0', [cli, number], function(data) {
                        // console.log(data);
                        // $('#' + depaid).html(data.htmldata);
                        let alertHtml = "";
                        if (data.count >= 1) {
                            alertHtml = `
                                <div class="alert alert-warning alert-dismissible fade show" role="alert" id="alertDPI">
                                <strong>DPI REPETIDO:</strong> ${data.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                `;
                        } else {
                            alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert" id="alertDPI">
                                <strong>${data.message}</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                `;
                        }
                        setTimeout(function() {
                            $("#alertDPI").alert("close");
                        }, 5000);
                        $("#section_notification").html(alertHtml);
                    });
                }

                function validateInputtel(input) {
                    var inputValue = input.value.trim();
                    if (inputValue === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        if (/^\d{8}$/.test(inputValue)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function validateInputtelreF(input) {
                    var inputValue = input.value.trim();
                    if (inputValue === '') {
                        input.classList.remove('is-valid', 'is-invalid');
                    } else {
                        var numeroSinPrefijo = inputValue.replace(/\+\d+\s?/, '').trim();
                        if (/^\d{8,20}$/.test(numeroSinPrefijo)) {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    }
                }

                function updateTributario() {
                    var tipoidentri = document.getElementById('tipoidentri');
                    var numberdoc = document.getElementById('numberdoc');
                    var numbernit = document.getElementById('numbernit');

                    if (tipoidentri.value === 'CUI') {
                        numbernit.value = numberdoc.value;
                        numbernit.maxLength = numberdoc.value
                            .length;
                    } else {
                        numbernit.value = '<?php echo $bandera ? $datos[0]['no_tributaria'] : ''; ?>';
                        numbernit.maxLength = 15;
                    }
                }


                //SELECCIONAR LOS CHECKBOXS DESPUES DE CARGAR EL DOM
                $(document).ready(function() {
                    ocultar_actuacion_propia(1);
                    <?php if ($bandera) { ?>
                        // concatenarValores(['nom1', 'nom2', 'nom3'], ['ape1', 'ape2', 'ape3'], 1, '#nomcorto');
                        // concatenarValores(['ape1', 'ape2', 'ape3'], ['nom1', 'nom2', 'nom3'], 2, '#nomcompleto');
                        seleccionarValueSelect('#genero', '<?= $datos[0]['genero']; ?>');
                        seleccionarValueSelect('#estcivil', '<?= $datos[0]['estado_civil']; ?>');
                        seleccionarValueSelect('#origen', '<?= $datos[0]['origen']; ?>');
                        calcularEdad_plus('<?= $datos[0]['date_birth'] ?>', '#edad');
                        seleccionarValueSelect('#docextend', '<?= $datos[0]['pais_extiende']; ?>');
                        seleccionarValueSelect('#tipodoc', '<?= $datos[0]['type_doc']; ?>');
                        ocultar_nit('<?= $datos[0]['pais_extiende']; ?>')
                        ejecutarDespuesDeBuscarMunicipios('buscar_municipios', '#muninac', '<?= $datos[0]['depa_nacio']; ?>',
                            '<?= $datos[0]['id_muni_nacio']; ?>');
                        ejecutarDespuesDeBuscarMunicipios('buscar_municipios', '#munidom', '<?= $datos[0]['depa_reside']; ?>',
                            '<?= $datos[0]['id_muni_reside']; ?>');
                        seleccionarValueSelect('#tipoidentri', '<?= $datos[0]['identi_tribu']; ?>');
                        seleccionarValueSelect('#actpropio', '<?= $datos[0]['actu_Propio']; ?>');
                        ocultar_actuacion_propia('<?= $datos[0]['repre_calidad']; ?>');
                        seleccionarValueSelect('#actcalidad', '<?= $datos[0]['repre_calidad']; ?>');
                        seleccionarValueSelect('#educacion', '<?= $datos[0]['educa']; ?>');
                        seleccionarValueSelect('#relinsti', '<?= $datos[0]['Rel_insti']; ?>');
                        seleccionarValueRadio('leer', '<?= $datos[0]['leer']; ?>');
                        seleccionarValueRadio('escribir', '<?= $datos[0]['escribir']; ?>');
                        seleccionarValueRadio('firma', '<?= $datos[0]['firma']; ?>');
                        seleccionarValueRadio('pep', '<?= $datos[0]['PEP']; ?>');
                        seleccionarValueRadio('cpe', '<?= $datos[0]['CPE']; ?>');
                        seleccionarValueRadio('pariente_pep', '<?= $datos[0]['pariente_pep']; ?>');
                        seleccionarValueRadio('asociado_pep', '<?= $datos[0]['asociado_pep']; ?>');
                        seleccionarValueRadio('esEmpleado', '<?= $datos[0]['esEmpleado']; ?>');
                    <?php }; ?>

                    $('#actividadEconomicaSat').select2({
                        theme: 'classic',
                        width: '100%',
                        placeholder: "Seleccione una opción",
                        allowClear: true
                    });
                    $('#pep_origen_riqueza').select2({
                        theme: 'classic',
                        width: '100%',
                        placeholder: "Seleccione una opción",
                        allowClear: true
                    });
                });
            </script>
        <?php
        }
        break;

    case 'create_perfil_economico': {
            $codigoCliente = $_POST['xtra'];
            $status = false;
            try {
                $database->openConnection();

                if ($codigoCliente == 0) {
                    throw new SoftException("Seleccione un cliente");
                }

                $datos = $database->getAllResults(
                    "SELECT cl.idcod_cliente AS codcli, cl.short_name AS nombre, cl.no_identifica AS dpi, cl.Direccion AS direccion, 
                        cl.date_birth AS fechacumple,  cl.tel_no1 AS telefono, cl.genero AS genero 
                        FROM tb_cliente cl WHERE cl.estado=1 AND cl.idcod_cliente=?",
                    [$codigoCliente]
                );

                if (empty($datos)) {
                    throw new SoftException("Cliente no encontrado.");
                }

                $ingresos = $database->getAllResults(
                    "SELECT ti.id_ingre_dependi AS idtipo, ti.Tipo_ingreso AS tipoingreso, ti.nombre_empresa AS nombreempresa, 
                        ti.direc_negocio AS direcnegocio, ti.sueldo_base AS sueldobase, ti.detalle_ingreso AS detalle_ingreso 
                        FROM tb_cliente tc 
                        INNER JOIN tb_ingresos ti ON tc.idcod_cliente = ti.id_cliente 
                        WHERE tc.idcod_cliente =?",
                    [$codigoCliente]
                );

                $egresos = $database->selectColumns("cli_egresos", ['id', 'nombre', 'monto'], 'id_cliente = ? AND estado = "1"', [$codigoCliente]);

                $status = true;
            } catch (SoftException $e) {
                $mensaje = $e->getMessage();
            } catch (Exception $e) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            } finally {
                $database->closeConnection();
            }

            $generos = [
                'F' => 'Femenino',
                'M' => 'Masculino'
            ];

            $tiposIngreso = [
                '1' => 'Propio',
                '2' => 'En dependencia',
                '3' => 'Otros'
            ];
            //++++++++++
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
        ?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <input type="text" id="file" value="clientes_001" style="display: none;">
            <input type="text" id="condi" value="create_perfil_economico" style="display: none;">
            <div class="container-fluid mt-3">
                <div class="row">
                    <div class="col-12">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary">
                                <i class="fa-solid fa-chart-line me-2"></i>PERFIL ECONÓMICO
                            </h2>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-wallet me-2"></i>Ingreso de perfil económico
                        </h5>
                    </div>

                    <div class="card-body">
                        <?php if (!$status) { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                <strong>Atención:</strong> <?= $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>

                        <!-- INFORMACIÓN DE CLIENTE -->
                        <div class="card mb-4">
                            <div class="card-header bg-secondary bg-gradient">
                                <h6 class="mb-0 text-light">
                                    <i class="fa-solid fa-user me-2"></i>Información de cliente
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Nombre del cliente y botón de búsqueda -->
                                    <div class="col-md-8">
                                        <div class="d-flex flex-column">
                                            <label class="form-label fw-semibold text-muted mb-1">
                                                <i class="fa-solid fa-id-card me-1"></i>Nombre del cliente
                                            </label>
                                            <span class="fs-5 fw-bold text-body">
                                                <?= $datos[0]['nombre'] ?? '<span class="text-muted">No seleccionado</span>' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold d-block">&nbsp;</label>
                                        <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal"
                                            data-bs-target="#buscar_cli_gen">
                                            <i class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar cliente
                                        </button>
                                    </div>

                                    <!-- Código, DPI y Dirección -->
                                    <div class="col-md-3">
                                        <div class="d-flex flex-column">
                                            <label class="form-label fw-semibold text-muted mb-1">
                                                <i class="fa-solid fa-hashtag me-1"></i>Código
                                            </label>
                                            <span class="badge bg-primary fs-6 text-start">
                                                <?= $datos[0]['codcli'] ?? '-' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="d-flex flex-column">
                                            <label class="form-label fw-semibold text-muted mb-1">
                                                <i class="fa-solid fa-id-badge me-1"></i>DPI
                                            </label>
                                            <span class="text-body fw-semibold">
                                                <?= $datos[0]['dpi'] ?? '-' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="d-flex flex-column">
                                            <label class="form-label fw-semibold text-muted mb-1">
                                                <i class="fa-solid fa-location-dot me-1"></i>Dirección
                                            </label>
                                            <span class="text-body">
                                                <?= $datos[0]['direccion'] ?? '-' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Fecha, Teléfono y Género -->
                                    <div class="col-md-4">
                                        <div class="d-flex flex-column">
                                            <label class="form-label fw-semibold text-muted mb-1">
                                                <i class="fa-solid fa-calendar me-1"></i>Fecha de nacimiento
                                            </label>
                                            <span class="text-body">
                                                <?= isset($datos[0]['fechacumple']) && Date::isValid($datos[0]['fechacumple']) ? Date::toDMY($datos[0]['fechacumple']) : '-' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="d-flex flex-column">
                                            <label class="form-label fw-semibold text-muted mb-1">
                                                <i class="fa-solid fa-phone me-1"></i>Teléfono
                                            </label>
                                            <span class="text-body">
                                                <?= $datos[0]['telefono'] ?? '-' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="d-flex flex-column">
                                            <label class="form-label fw-semibold text-muted mb-1">
                                                <i class="fa-solid fa-venus-mars me-1"></i>Género
                                            </label>
                                            <span
                                                class="badge <?= ($datos[0]['genero'] ?? '') === 'M' ? 'bg-info' : 'bg-warning' ?> text-dark fs-6">
                                                <?= $generos[$datos[0]['genero'] ?? ''] ?? 'INDEFINIDO' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TABLA DE INGRESOS -->
                        <div class="card mb-4">
                            <div class="card-header bg-success bg-gradient d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 text-primary">
                                    <i class="fa-solid fa-money-bill-trend-up me-2"></i>Ingresos del cliente
                                </h6>
                                <span class="badge bg-primary">
                                    <?= count($ingresos ?? []) ?> registro(s)
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0" id="tb_perfiles_economicos">
                                        <thead class="table-primary">
                                            <tr>
                                                <th class="text-nowrap">
                                                    <i class="fa-solid fa-tag me-1"></i>Tipo de Ingreso
                                                </th>
                                                <th class="text-nowrap">
                                                    <i class="fa-solid fa-building me-1"></i>Nombre Empresa
                                                </th>
                                                <th class="text-nowrap">
                                                    <i class="fa-solid fa-map-marker-alt me-1"></i>Dirección
                                                </th>
                                                <th class="text-nowrap">
                                                    <i class="fa-solid fa-dollar-sign me-1"></i>Ingresos
                                                </th>
                                                <th class="text-center text-nowrap">
                                                    <i class="fa-solid fa-cog me-1"></i>Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($ingresos)): ?>
                                                <?php foreach ($ingresos as $ingreso): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-info text-dark">
                                                                <?= $tiposIngreso[$ingreso["tipoingreso"]] ?? '-' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($ingreso["nombreempresa"]) ?></td>
                                                        <td class="text-muted small">
                                                            <?= htmlspecialchars(
                                                                ($ingreso["tipoingreso"] == '3')
                                                                    ? $ingreso["detalle_ingreso"]
                                                                    : $ingreso["direcnegocio"]
                                                            ) ?>
                                                        </td>
                                                        <td class="fw-semibold text-success">
                                                            <?= Moneda::formato($ingreso["sueldobase"]) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button type="button" class="btn btn-outline-primary" title="Ver detalles"
                                                                    onclick="printdiv3_plus('section_tipos_ingresos', '#contenedor_tipos_ingresos',['<?= $ingreso['idtipo'] ?>',1])">
                                                                    <i class="fa-solid fa-eye"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                                    onclick="obtiene_plus([],[],[],`delete_perfil_economico`,'<?= $codigoCliente; ?>',['<?= $ingreso['idtipo']; ?>','<?= $codigoCliente; ?>','<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>'])">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-4">
                                                        <i class="fa-solid fa-inbox fa-3x mb-2 d-block"></i>
                                                        <p class="mb-0">No hay ingresos registrados para este cliente</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- TABLA DE EGRESOS -->
                        <div class="card mb-4">
                            <div class="card-header bg-warning bg-gradient d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 text-primary">
                                    <i class="fa-solid fa-money-bill"></i>Egresos del cliente
                                </h6>
                                <span class="badge bg-primary">
                                    <?= count($egresos ?? []) ?> registro(s)
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped mb-0" id="tb_egresos_clientes">
                                        <thead class="table-primary">
                                            <tr>
                                                <th class="text-nowrap">
                                                    <i class="fa-solid fa-tag me-1"></i>Nombre Egreso
                                                </th>
                                                <th class="text-nowrap">
                                                    <i class="fa-solid fa-dollar-sign me-1"></i>Monto
                                                </th>
                                                <th class="text-center text-nowrap">
                                                    <i class="fa-solid fa-cog me-1"></i>Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($egresos)): ?>
                                                <?php foreach ($egresos as $egreso): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($egreso["nombre"]) ?></td>
                                                        <td class="fw-semibold text-danger">
                                                            <?= Moneda::formato($egreso["monto"]) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button type="button" class="btn btn-outline-primary" title="Ver detalles"
                                                                    onclick="printdiv3_plus('section_tipos_ingresos', '#contenedor_tipos_ingresos',['<?= $egreso['id'] ?>',2])">
                                                                    <i class="fa-solid fa-eye"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                                    onclick="obtiene_plus([],[],[],`delete_egreso`,'<?= $codigoCliente; ?>',['<?= $egreso['id']; ?>'],'null','¿Está seguro de eliminar este egreso?')">
                                                                    <i class="fa-solid fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">
                                                        <i class="fa-solid fa-inbox fa-3x mb-2 d-block"></i>
                                                        <p class="mb-0">No hay egresos registrados para este cliente</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>


                        <!-- CONTENEDOR DE TIPOS DE INGRESOS -->
                        <div class="card" id="contenedor_tipos_ingresos">
                            <!-- Contenido dinámico -->
                        </div>
                    </div>

                    <!-- FOOTER CON ACCIONES -->
                    <div class="card-footer bg-transparent p-2 text-white bg-opacity-50">
                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                            <?php if ($status && !empty($ingresos)): ?>
                                <button type="button" class="btn btn-outline-danger"
                                    onclick="reportes([[],[],[],[ '<?= $codigoCliente; ?>']], 'pdf', 'perfil_eco_imprimir', 0)">
                                    <i class="fa-regular fa-file-pdf me-2"></i>Exportar PDF
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="printdiv2_plus('#cuadro','0')">
                                <i class="fa-solid fa-ban me-2"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-warning" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark me-2"></i>Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                //SELECCIONAR LOS CHECKBOXS DESPUES DE CARGAR EL DOM
                $(document).ready(function() {
                    <?php if ($status) { ?>
                        printdiv3_plus('section_tipos_ingresos', '#contenedor_tipos_ingresos', ['0', 0, '<?= $codigoCliente; ?>']);
                    <?php } ?>
                });
            </script>
            <?php include_once "../../src/cris_modales/mdls_cli.php"; ?>
        <?php
        }
        break;

    case 'section_tipos_ingresos':

        // Log::debug("extra data:", $_POST['xtra']);

        list($idTipo, $tipoOperacion) = $_POST['xtra'];

        $codigoCliente = $_POST['xtra'][2] ?? 0;

        $status = false;
        $idPais = NULL;
        try {
            $database->openConnection();
            if ($idTipo != 0 && $tipoOperacion == 1) {
                $datos = $database->getAllResults(
                    "SELECT ti.*, tc.idcod_cliente AS codcli,
                        IFNULL((SELECT act.id_ActiEcono AS idactecono FROM $db_name_general.tb_ActiEcono act WHERE act.id_ActiEcono = ti.actividad_economica),'-') AS idactecono,
                        IFNULL((SELECT act.Titulo AS nomactecono FROM $db_name_general.tb_ActiEcono act WHERE act.id_ActiEcono = ti.actividad_economica),'-') AS nomactecono  
                        FROM tb_cliente tc 
                        INNER JOIN tb_ingresos ti ON tc.idcod_cliente = ti.id_cliente 
                        WHERE ti.id_ingre_dependi = ?",
                    [$idTipo]
                );

                if (empty($datos)) {
                    throw new SoftException("Tipo de ingreso no encontrado.");
                }

                $codigoCliente = $datos[0]['codcli'];

                if (is_numeric($datos[0]['depa_negocio'])) {
                    $idPais = Departamento::obtenerIdPais($datos[0]['depa_negocio']);
                }
            }
            if ($idTipo != 0 && $tipoOperacion == 2) {
                $datos = $database->getAllResults("SELECT id, nombre, monto,id_cliente FROM cli_egresos WHERE id = ?", [$idTipo]);

                if (empty($datos)) {
                    throw new SoftException("Egreso no encontrado.");
                }

                $codigoCliente = $datos[0]['id_cliente'];
            }

            if (is_null($idPais)) {
                $appPaisVersion = $_ENV['APP_PAIS_VERSION'] ?? 'GT';

                $paisVersionApp = Pais::obtenerPorCodigo($appPaisVersion);

                $idPais = $paisVersionApp['id'];
            }

            $departamentos = Departamento::obtenerPorPais($idPais);

            $database->closeConnection();
            $database->openConnection(2);

            $tiposNegocios = $database->selectColumns("tb_negocio", ['id_Negocio AS id', 'Negocio AS nombre']);

            $status = true;
        } catch (SoftException $e) {
            $mensaje = $e->getMessage();
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }


        $codusu = $_SESSION['id'];
        $id_agencia = $_SESSION['id_agencia'];
        $codagencia = $_SESSION['agencia'];

        ?>
        <div x-data="{ 
            action: '<?= ($idTipo == 0) ? 'create' : 'update'; ?>',
            openTab: <?= ($tipoOperacion == 0) ? 1 : (($tipoOperacion == 2) ? 4 : (($datos[0]['Tipo_ingreso'] == '1') ? 1 : (($datos[0]['Tipo_ingreso'] == '2') ? 2 : 3))); ?> 
            }" class="card shadow-sm">

            <!-- Header con tabs mejorado -->
            <div class="card-header bg-transparent border-bottom-0 pt-3">
                <nav>
                    <div class="nav nav-tabs border-0" id="nav-tab" role="tablist">
                        <button class="nav-link border-0 rounded-top px-4 py-3 fw-semibold"
                            :class="{ 'active bg-primary text-white': openTab === 1, 'text-body-secondary bg-body-secondary': openTab !== 1, 'disabled': openTab !== 1 && action === 'update' }"
                            id="nav-home-tab" data-bs-toggle="tab" @click="openTab = 1" data-bs-target="#nav-home" type="button"
                            role="tab" aria-controls="nav-home" aria-selected="true">
                            <i class="fa-solid fa-store me-2"></i>Negocios Propios
                        </button>

                        <button class="nav-link border-0 rounded-top px-4 py-3 fw-semibold mx-2"
                            :class="{ 'active bg-primary text-white': openTab === 2, 'text-body-secondary bg-body-secondary': openTab !== 2, 'disabled': openTab !== 2 && action === 'update' }"
                            id="nav-profile-tab" data-bs-toggle="tab" @click="openTab = 2" data-bs-target="#nav-profile"
                            type="button" role="tab" aria-controls="nav-profile" aria-selected="false">
                            <i class="fa-solid fa-briefcase me-2"></i>Ingresos en Dependencia
                        </button>

                        <button class="nav-link border-0 rounded-top px-4 py-3 fw-semibold"
                            :class="{ 'active bg-primary text-white': openTab === 3, 'text-body-secondary bg-body-secondary': openTab !== 3, 'disabled': openTab !== 3 && action === 'update' }"
                            id="nav-contact-tab" data-bs-toggle="tab" @click="openTab = 3" data-bs-target="#nav-contact"
                            type="button" role="tab" aria-controls="nav-contact" aria-selected="false">
                            <i class="fa-solid fa-money-bill-wave me-2"></i>Otros Ingresos
                        </button>
                        <button class="nav-link border-0 rounded-top px-4 py-3 fw-semibold mx-2"
                            :class="{ 'active bg-primary text-white': openTab === 4, 'text-body-secondary bg-body-secondary': openTab !== 4, 'disabled': openTab !== 4 && action === 'update' }"
                            id="nav-contact-tab" data-bs-toggle="tab" @click="openTab = 4" data-bs-target="#nav-contact"
                            type="button" role="tab" aria-controls="nav-contact" aria-selected="false">
                            <i class="fa-solid fa-money-bill-wave me-2"></i>Egresos
                        </button>
                    </div>
                </nav>
            </div>

            <div class="card-body">
                <div class="tab-content" id="nav-tabContent">
                    <!-- ==================== TAB 1: NEGOCIOS PROPIOS ==================== -->
                    <div class="tab-pane fade" :class="{ 'show active': openTab === 1 }" id="nav-home" role="tabpanel"
                        aria-labelledby="nav-home-tab" tabindex="0">

                        <!-- Información del Negocio -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-primary-subtle border-0">
                                <h6 class="mb-0 text-primary fw-semibold">
                                    <i class="fa-solid fa-building me-2"></i>Información del Negocio
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="nomnegocio" placeholder="Nombre negocio"
                                                value="<?= $datos[0]['nombre_empresa'] ?? '' ?>">
                                            <label for="nomnegocio"><i class="fa-solid fa-tag me-1"></i>Nombre del
                                                Negocio</label>
                                        </div>
                                    </div>

                                    <div class="col-md-7">
                                        <label class="form-label fw-semibold text-body-secondary mb-2">
                                            <i class="fa-solid fa-chart-line me-1"></i>Actividad Económica
                                        </label>
                                        <div class="input-group">
                                            <div class="form-floating flex-grow-1">
                                                <input type="text" class="form-control" id="actecono" readonly
                                                    value="<?= $datos[0]['nomactecono'] ?? '' ?>">
                                                <input type="text" class="form-control" id="idactecono" readonly hidden
                                                    value="<?= $datos[0]['idactecono'] ?? '' ?>">
                                                <label for="actecono">Actividad económica</label>
                                            </div>
                                            <button type="button" class="btn btn-primary px-4" id="bt_act"
                                                onclick="abrir_modal('#modal_acteconomica', '#id_modal_hidden', 'idactecono,actecono/A,A/'+'/#/#/#/#')">
                                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-5">
                                        <div class="form-floating">
                                            <input type="date" class="form-control" id="fecinscri" placeholder="Fecha de inicio"
                                                value="<?= isset($datos[0]['fecha_labor']) && Date::isValid($datos[0]['fecha_labor']) ? $datos[0]['fecha_labor'] : date("Y-m-d") ?>">
                                            <label for="fecinscri"><i class="fa-regular fa-calendar me-1"></i>Fecha de
                                                Inicio</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Patente y Registro -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-success-subtle border-0">
                                <h6 class="mb-0 text-success fw-semibold">
                                    <i class="fa-solid fa-certificate me-2"></i>Patente y Registro
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold text-body-secondary mb-3">¿El negocio tiene
                                            patente?</label>
                                        <div class="d-flex gap-4">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="patente"
                                                    id="flexRadioDefault1" <?= ($datos[0]['patente'] ?? '') === 'si' ? 'checked' : ''; ?> value="si"
                                                    onclick="habilitar_deshabilitar(['registro','folio','libro'],[])">
                                                <label class="form-check-label fw-semibold" for="flexRadioDefault1">
                                                    <i class="fa-solid fa-check-circle text-success me-1"></i>Sí
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="patente"
                                                    id="flexRadioDefault2" <?= (!isset($datos[0]['patente']) || $datos[0]['patente'] === 'no') ? 'checked' : ''; ?> value="no"
                                                    onclick="habilitar_deshabilitar([],['registro','folio','libro']); limpiarhabdes([],['registro','folio','libro'])">
                                                <label class="form-check-label fw-semibold" for="flexRadioDefault2">
                                                    <i class="fa-solid fa-times-circle text-danger me-1"></i>No
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="registro" placeholder="Registro"
                                                value="<?= $datos[0]['no_registro'] ?? '' ?>" <?= (isset($datos[0]['patente']) && $datos[0]['patente'] === 'si') ? '' : 'disabled'; ?>>
                                            <label for="registro"><i class="fa-solid fa-hashtag me-1"></i>Número de
                                                Registro</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="folio" placeholder="Folio"
                                                value="<?= $datos[0]['folio'] ?? '' ?>" <?= (isset($datos[0]['patente']) && $datos[0]['patente'] === 'si') ? '' : 'disabled'; ?>>
                                            <label for="folio"><i class="fa-solid fa-file-lines me-1"></i>Folio</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="libro" placeholder="Libro"
                                                value="<?= $datos[0]['libro'] ?? '' ?>" <?= (isset($datos[0]['patente']) && $datos[0]['patente'] === 'si') ? '' : 'disabled'; ?>>
                                            <label for="libro"><i class="fa-solid fa-book me-1"></i>Libro</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Datos de Contacto e Ingresos -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-info-subtle border-0">
                                <h6 class="mb-0 text-info fw-semibold">
                                    <i class="fa-solid fa-phone me-2"></i>Contacto e Información Financiera
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="telefono" placeholder="Telefono"
                                                value="<?= $datos[0]['telefono_negocio'] ?? '' ?>">
                                            <label for="telefono"><i
                                                    class="fa-solid fa-mobile-screen-button me-1"></i>Teléfono</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="condicionlocal">
                                                <?php foreach (($tiposNegocios ?? []) as $key => $negocio): ?>
                                                    <option value="<?= $negocio['id']; ?>" <?= (isset($datos[0]['condi_negocio']) && $datos[0]['condi_negocio'] == $negocio['id']) ? 'selected' : ''; ?>>
                                                        <?= $negocio['nombre']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="condicionlocal"><i class="fa-solid fa-home me-1"></i>Condición del
                                                Local</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="ingresos"
                                                placeholder="Ingresos mensual estimado"
                                                value="<?= $datos[0]['sueldo_base'] ?? 0 ?>">
                                            <label for="ingresos"><i class="fa-solid fa-dollar-sign me-1"></i>Ingresos
                                                Mensuales</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ubicación -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-warning-subtle border-0">
                                <h6 class="mb-0 text-warning-emphasis fw-semibold">
                                    <i class="fa-solid fa-map-location-dot me-2"></i>Ubicación del Negocio
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="deppropio"
                                                onchange="buscar_municipios('buscar_municipios', '#munipropio', this.value)">
                                                <option value="0" selected>Seleccione un departamento</option>
                                                <?php foreach ($departamentos as $key => $departamento): ?>
                                                    <option value="<?= $departamento['id']; ?>" <?= (isset($datos[0]['depa_negocio']) && $datos[0]['depa_negocio'] == $departamento['id']) ? 'selected' : ''; ?>>
                                                        <?= $departamento['nombre']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="deppropio"><i class="fa-solid fa-map me-1"></i>Departamento</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="munipropio">
                                                <option value="0" selected>Seleccione un municipio</option>
                                            </select>
                                            <label for="munipropio"><i class="fa-solid fa-map-pin me-1"></i>Municipio</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="noempleados" placeholder="# Empleados"
                                                value="<?= $datos[0]['empleados'] ?? '' ?>">
                                            <label for="noempleados"><i class="fa-solid fa-users me-1"></i>Número de
                                                Empleados</label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="direccion" placeholder="Dirección"
                                                value="<?= $datos[0]['direc_negocio'] ?? '' ?>">
                                            <label for="direccion"><i class="fa-solid fa-location-dot me-1"></i>Dirección
                                                Completa</label>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="referencia" placeholder="Referencia"
                                                value="<?= $datos[0]['referencia'] ?? '' ?>">
                                            <label for="referencia"><i class="fa-solid fa-info-circle me-1"></i>Referencia de
                                                Ubicación</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <?php if ($status && $idTipo == 0) { ?>
                                <button class="btn btn-success btn-lg px-5"
                                    onclick="obtiene_plus([`nomnegocio`,`idactecono`,`actecono`,`fecinscri`,`registro`,`folio`,`libro`,`telefono`,`ingresos`,`direccion`,`noempleados`,`referencia`],[`condicionlocal`,`deppropio`,`munipropio`],[`patente`],`create_ingreso_propio`,'<?= $codigoCliente; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Información
                                </button>
                            <?php } ?>
                            <?php if ($status && $tipoOperacion == 1 && isset($datos[0])) { ?>
                                <button class="btn btn-primary btn-lg px-5"
                                    onclick="obtiene_plus([`nomnegocio`,`idactecono`,`actecono`,`fecinscri`,`registro`,`folio`,`libro`,`telefono`,`ingresos`,`direccion`,`noempleados`,`referencia`],[`condicionlocal`,`deppropio`,`munipropio`],[`patente`],`update_ingreso_propio`,'<?= $codigoCliente; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $codigoCliente; ?>','<?= $idTipo; ?>'])">
                                    <i class="fa-solid fa-sync me-2"></i>Actualizar Información
                                </button>
                                <button class="btn btn-outline-danger btn-lg px-4"
                                    onclick="printdiv3_plus('section_tipos_ingresos', '#contenedor_tipos_ingresos', ['0', 0, '<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-times me-2"></i>Cancelar
                                </button>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- ==================== TAB 2: INGRESOS EN DEPENDENCIA ==================== -->
                    <div class="tab-pane fade" :class="{ 'show active': openTab === 2 }" id="nav-profile" role="tabpanel"
                        aria-labelledby="nav-profile-tab" tabindex="0">

                        <!-- Información Laboral -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-primary-subtle border-0">
                                <h6 class="mb-0 text-primary fw-semibold">
                                    <i class="fa-solid fa-building-user me-2"></i>Información del Empleador
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select id="sector" class="form-select">
                                                <option <?= ($datos[0]['sector_Econo'] ?? '') == 1 ? 'selected' : '' ?> value="1">
                                                    Sector Público</option>
                                                <option <?= ($datos[0]['sector_Econo'] ?? '') == 2 ? 'selected' : '' ?> value="2">
                                                    Sector Privado</option>
                                            </select>
                                            <label for="sector"><i class="fa-solid fa-briefcase me-1"></i>Sector Laboral</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="nomnegocio2"
                                                placeholder="Nombre empresa" value="<?= $datos[0]['nombre_empresa'] ?? '' ?>">
                                            <label for="nomnegocio2"><i class="fa-solid fa-building me-1"></i>Nombre de la
                                                Empresa</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalles del Puesto -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-success-subtle border-0">
                                <h6 class="mb-0 text-success fw-semibold">
                                    <i class="fa-solid fa-id-card me-2"></i>Detalles del Puesto
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-body-secondary mb-2">
                                            <i class="fa-solid fa-chart-line me-1"></i>Actividad Económica de la Empresa
                                        </label>
                                        <div class="input-group">
                                            <div class="form-floating flex-grow-1">
                                                <input type="text" class="form-control" id="actecono2" placeholder="Actividad"
                                                    readonly value="<?= $datos[0]['nomactecono'] ?? '' ?>">
                                                <input type="text" class="form-control" id="idactecono2" placeholder="ID"
                                                    readonly hidden value="<?= $datos[0]['idactecono'] ?? '' ?>">
                                                <label for="actecono2">Actividad económica</label>
                                            </div>
                                            <button type="button" class="btn btn-success px-4" id="bt_act"
                                                onclick="abrir_modal('#modal_acteconomica', '#id_modal_hidden', 'idactecono2,actecono2/A,A/'+'/#/#/#/#')">
                                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="puesto"
                                                placeholder="Puesto en la empresa"
                                                value="<?= $datos[0]['puesto_ocupa'] ?? '' ?>">
                                            <label for="puesto"><i class="fa-solid fa-user-tie me-1"></i>Puesto</label>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="date" class="form-control" id="fecinicio" placeholder="Fecha de inicio"
                                                value="<?= $datos[0]['fecha_labor'] ?? date('Y-m-d') ?>">
                                            <label for="fecinicio"><i class="fa-regular fa-calendar me-1"></i>Fecha
                                                Inicio</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ubicación y Salario -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-info-subtle border-0">
                                <h6 class="mb-0 text-info fw-semibold">
                                    <i class="fa-solid fa-location-dot me-2"></i>Ubicación e Ingresos
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="direccion_dependencia"
                                                placeholder="Dirección de la empresa"
                                                value="<?= $datos[0]['direc_negocio'] ?? '' ?>">
                                            <label for="direccion_dependencia"><i
                                                    class="fa-solid fa-map-marker-alt me-1"></i>Dirección de la Empresa</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="deppropio2"
                                                onchange="buscar_municipios('buscar_municipios', '#munipropio2', this.value)">
                                                <option value="0" selected>Seleccione un departamento</option>
                                                <?php foreach ($departamentos as $key => $departamento): ?>
                                                    <option value="<?= $departamento['id']; ?>" <?= (isset($datos[0]['depa_negocio']) && $datos[0]['depa_negocio'] == $departamento['id']) ? 'selected' : ''; ?>>
                                                        <?= $departamento['nombre']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="deppropio2"><i class="fa-solid fa-map me-1"></i>Departamento</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="munipropio2">
                                                <option value="0" selected>Seleccione un municipio</option>
                                            </select>
                                            <label for="munipropio2"><i class="fa-solid fa-map-pin me-1"></i>Municipio</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="monto_dependencia"
                                                placeholder="Ingreso mensual" value="<?= $datos[0]['sueldo_base'] ?? '' ?>">
                                            <label for="monto_dependencia"><i class="fa-solid fa-dollar-sign me-1"></i>Salario
                                                Mensual</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <?php if ($status && $idTipo == 0) { ?>
                                <button class="btn btn-success btn-lg px-5"
                                    onclick="obtiene_plus([`nomnegocio2`,`idactecono2`,`actecono2`,`puesto`,`monto_dependencia`,`direccion_dependencia`,`fecinicio`],[`sector`,`deppropio2`,`munipropio2`],[],`create_ingreso_dependiente`,'<?= $codigoCliente; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Información
                                </button>
                            <?php } ?>
                            <?php if ($status && $tipoOperacion == 1 && isset($datos[0]) && $datos[0]['Tipo_ingreso'] == 2) { ?>
                                <button class="btn btn-primary btn-lg px-5"
                                    onclick="obtiene_plus([`nomnegocio2`,`idactecono2`,`actecono2`,`puesto`,`monto_dependencia`,`direccion_dependencia`,`fecinicio`],[`sector`,`deppropio2`,`munipropio2`],[],`update_ingreso_dependiente`,'<?= $codigoCliente; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $codigoCliente; ?>','<?= $idTipo; ?>'])">
                                    <i class="fa-solid fa-sync me-2"></i>Actualizar Información
                                </button>
                                <button class="btn btn-outline-danger btn-lg px-4"
                                    onclick="printdiv3_plus('section_tipos_ingresos', '#contenedor_tipos_ingresos', ['0', 0,'<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-times me-2"></i>Cancelar
                                </button>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- ==================== TAB 3: OTROS INGRESOS ==================== -->
                    <div class="tab-pane fade" :class="{ 'show active': openTab === 3 }" id="nav-contact" role="tabpanel"
                        aria-labelledby="nav-contact-tab" tabindex="0">

                        <!-- Tipo de Ingreso -->
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-warning-subtle border-0">
                                <h6 class="mb-0 text-warning-emphasis fw-semibold">
                                    <i class="fa-solid fa-hand-holding-dollar me-2"></i>Fuente de Ingresos Adicionales
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <select id="otros_ingresos1" class="form-select">
                                                <option <?= (isset($datos[0]['nombre_empresa']) && $datos[0]['nombre_empresa'] == "Actividades profesionales") ? "selected" : "" ?> value="Actividades profesionales">
                                                    Actividades profesionales
                                                </option>
                                                <option <?= (isset($datos[0]['nombre_empresa']) && $datos[0]['nombre_empresa'] == "Manutención") ? "selected" : "" ?>
                                                    value="Manutención">
                                                    Manutención
                                                </option>
                                                <option <?= (isset($datos[0]['nombre_empresa']) && $datos[0]['nombre_empresa'] == "Rentas") ? "selected" : "" ?> value="Rentas">
                                                    Rentas
                                                </option>
                                                <option <?= (isset($datos[0]['nombre_empresa']) && $datos[0]['nombre_empresa'] == "Jubilación") ? "selected" : "" ?>
                                                    value="Jubilación">
                                                    Jubilación
                                                </option>
                                                <option <?= (isset($datos[0]['nombre_empresa']) && $datos[0]['nombre_empresa'] == "Remesas") ? "selected" : "" ?>
                                                    value="Remesas">
                                                    Remesas
                                                </option>
                                                <option <?= (isset($datos[0]['nombre_empresa']) && $datos[0]['nombre_empresa'] == "Otros") ? "selected" : "" ?> value="Otros">
                                                    Otros
                                                </option>
                                            </select>
                                            <label for="otros_ingresos1"><i class="fa-solid fa-list me-1"></i>Tipo de
                                                Ingreso</label>
                                        </div>
                                    </div>

                                    <div class="col-md-8">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="det_ingreso"
                                                placeholder="Detalle ingreso" value="<?= $datos[0]['detalle_ingreso'] ?? '' ?>">
                                            <label for="det_ingreso"><i class="fa-solid fa-align-left me-1"></i>Descripción
                                                Detallada</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="monto_otros3"
                                                placeholder="Monto aproximado" value="<?= $datos[0]['sueldo_base'] ?? '' ?>">
                                            <label for="monto_otros3"><i class="fa-solid fa-dollar-sign me-1"></i>Monto</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <?php if ($status && $idTipo == 0) { ?>
                                <button class="btn btn-success btn-lg px-5"
                                    onclick="obtiene_plus([`det_ingreso`,`monto_otros3`],[`otros_ingresos1`],[],`create_otros_ingresos`,'<?= $codigoCliente; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Información
                                </button>
                            <?php } ?>
                            <?php if ($status && $tipoOperacion == 1 && isset($datos[0]) && $datos[0]['Tipo_ingreso'] == 3) { ?>
                                <button class="btn btn-primary btn-lg px-5"
                                    onclick="obtiene_plus([`det_ingreso`,`monto_otros3`],[`otros_ingresos1`],[],`update_otros_ingresos`,'<?= $codigoCliente; ?>',['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $codigoCliente; ?>','<?= $idTipo; ?>'])">
                                    <i class="fa-solid fa-sync me-2"></i>Actualizar Información
                                </button>
                                <button class="btn btn-outline-danger btn-lg px-4"
                                    onclick="printdiv3_plus('section_tipos_ingresos', '#contenedor_tipos_ingresos', ['0', 0,'<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-times me-2"></i>Cancelar
                                </button>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- ==================== TAB 4: egresos ==================== -->
                    <div class="tab-pane fade" :class="{ 'show active': openTab === 4 }" id="nav-contact" role="tabpanel"
                        aria-labelledby="nav-contact-tab" tabindex="0">
                        <div class="card border shadow-sm mb-4">
                            <div class="card-header bg-warning-subtle border-0">
                                <h6 class="mb-0 text-warning-emphasis fw-semibold">
                                    <i class="fa-solid fa-hand-holding-dollar me-2"></i>EGRESOS
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="det_egreso" placeholder="Detalle egreso"
                                                value="<?= $datos[0]['nombre'] ?? '' ?>">
                                            <label for="det_egreso"><i class="fa-solid fa-align-left me-1"></i>Descripción
                                                Detallada</label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="monto_egreso"
                                                placeholder="Monto aproximado" value="<?= $datos[0]['monto'] ?? '' ?>">
                                            <label for="monto_egreso"><i class="fa-solid fa-dollar-sign me-1"></i>Monto</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <?php if ($status && $idTipo == 0) { ?>
                                <button class="btn btn-success btn-lg px-5"
                                    onclick="obtiene_plus([`det_egreso`,`monto_egreso`],[],[],`create_egresos`,'<?= $codigoCliente; ?>',['<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Información
                                </button>
                            <?php } ?>
                            <?php if ($status && $tipoOperacion == 2 && isset($datos[0])) { ?>
                                <button class="btn btn-primary btn-lg px-5"
                                    onclick="obtiene_plus([`det_egreso`,`monto_egreso`],[],[],`update_egresos`,'<?= $codigoCliente; ?>',['<?= $codigoCliente; ?>','<?= $idTipo; ?>'])">
                                    <i class="fa-solid fa-sync me-2"></i>Actualizar Información
                                </button>
                                <button class="btn btn-outline-danger btn-lg px-4"
                                    onclick="printdiv3_plus('section_tipos_ingresos', '#contenedor_tipos_ingresos', ['0', 0, '<?= $codigoCliente; ?>'])">
                                    <i class="fa-solid fa-times me-2"></i>Cancelar
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                <?php if ($status && ($datos[0]['Tipo_ingreso'] ?? '') == '1'): ?>
                    ejecutarDespuesDeBuscarMunicipios('buscar_municipios', '#munipropio', '<?= $datos[0]['depa_negocio']; ?>', '<?= $datos[0]['muni_negocio']; ?>');
                <?php endif; ?>
                <?php if ($status && ($datos[0]['Tipo_ingreso'] ?? '') == '2'): ?>
                    ejecutarDespuesDeBuscarMunicipios('buscar_municipios', '#munipropio2', '<?= $datos[0]['depa_negocio']; ?>', '<?= $datos[0]['muni_negocio']; ?>');
                <?php endif; ?>
            });
        </script>
        <?php
        break;

    case 'create_cliente_juridico': {
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
            $xtra = $_POST["xtra"];

            $bandera = false;
            $bandera_socios = false;
            $datos[] = [];
            $socios[] = [];

            $i = 0;
            if ($xtra != 0) {
                $consulta = mysqli_query($conexion, "SELECT tc.idcod_cliente AS codcli, tc.short_name AS nombre, tc.compl_name AS nomcompleto, tc.no_identifica AS registro, tc.representante_name AS representante, tc.date_birth AS fechafun, tc.depa_reside AS departamento, tc.id_muni_reside AS municipio, tc.aldea_reside AS referencia, tc.Direccion AS direccion FROM tb_cliente tc WHERE tc.idcod_cliente='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datos[$i] = $fila;
                    $i++;
                    $bandera = true;
                }
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT * FROM tb_socios_juri tsj WHERE tsj.id_clnt_ntral ='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $socios[$i] = $fila;
                    $i++;
                    $bandera_socios = true;
                }
            }
            try {
                /**
                 * TEMPORALMENTE FIJO, SE CARGAN LOS DE GUATE, DESPUES HACERLO DINAMICO DEPENDIENDO DEL PAIS
                 */
                $departamentosGuatemala = Departamento::obtenerPorPais(4);
            } catch (Exception $e) {
                $departamentosGuatemala = [];
                Log::error("Error al obtener departamentos: " . $e->getMessage());
            }
        ?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <input type="text" id="file" value="clientes_001" style="display: none;">
            <input type="text" id="condi" value="create_cliente_juridico" style="display: none;">
            <div class="text" style="text-align:center">INGRESO DE CLIENTES JURIDICOS</div>
            <div class="card">
                <div class="card-header">Ingreso de cliente jurídico</div>
                <div class="card-body" style="padding-bottom: 0px !important;">
                    <!-- TABLA PARA LOS CLIENTES JURIDICOS -->
                    <div class="container contenedort">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Listado de clientes jurídicos</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="table-responsive">
                                    <table class="table nowrap table-hover table-border" id="tb_clientes_juridicos"
                                        style="width: 100% !important;">
                                        <thead class="text-light table-head-aprt">
                                            <tr style="font-size: 0.9rem;">
                                                <th>Código</th>
                                                <th>Nombre comercial</th>
                                                <th>Registro sociedad</th>
                                                <th>F. Fundación</th>
                                                <th>Accciones</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-group-divider" style="font-size: 0.9rem !important;">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- INFORMACION DE CLIENTE -->
                    <!-- seleccion de cliente y su credito-->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Información de cliente jurídico</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="codcli" placeholder="Código de cliente" hidden
                                        readonly <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['codcli'] . '"';
                                                    } ?>>

                                    <input type="text" class="form-control" id="razonsocial" placeholder="Razón social" <?php if ($bandera) {
                                                                                                                            echo 'value="' . $datos[0]['nombre'] . '"';
                                                                                                                        } ?>>
                                    <label for="razonsocial">Razón social</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-7">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="razoncomercial" placeholder="Nombre comercial" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . $datos[0]['nomcompleto'] . '"';
                                                                                                                                } ?>>
                                    <label for="razoncomercial">Nombre comercial</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-5">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="registrosociedad"
                                        placeholder="No. Registro sociedad" <?php if ($bandera) {
                                                                                echo 'value="' . $datos[0]['registro'] . '"';
                                                                            } ?>>
                                    <label for="registrosociedad">No. Registro sociedad</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-7">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="representantelegal"
                                        placeholder="Representante legal" <?php if ($bandera) {
                                                                                echo 'value="' . $datos[0]['representante'] . '"';
                                                                            } ?>oninput="validateInputlibre(this)">
                                    <label for="representantelegal">Representante legal</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-5">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="date" class="form-control" id="fechafundacion" placeholder="Fecha fundación" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . $datos[0]['fechafun'] . '"';
                                                                                                                                } ?>>
                                    <label for="fechafundacion">Fecha fundación</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="depclientejuridico"
                                        onchange="buscar_municipios('buscar_municipios', '#municlientejuridico', this.value)">
                                        <option value="0" selected>Seleccione un departamento</option>
                                        <?php
                                        $selected = "";
                                        foreach ($departamentosGuatemala as $departamento) {
                                            if ($bandera) {
                                                ($datos[0]['departamento'] == $departamento["id"]) ? $selected = "selected" : $selected = "";
                                            }
                                            $nombre = ($departamento["nombre"]);
                                            $codigo_departa = $departamento["id"];
                                            echo '<option value="' . $codigo_departa . '" ' . $selected . '>' . $nombre . '</option>';
                                        } ?>
                                    </select>
                                    <label for="depclientejuridico">Departamento</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="municlientejuridico">
                                        <option value="0" selected>Seleccione un municipio</option>
                                    </select>
                                    <label for="municlientejuridico">Municipio</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="referenciajuridica" placeholder="Referencia" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . ((isset($datos[0]['referencia'])) ? $datos[0]['referencia'] : '-') . '"';
                                                                                                                                } ?>>
                                    <label for="referenciajuridica">Referencia</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="direccionjuridica" placeholder="Domicilio fiscal"
                                        <?php if ($bandera) {
                                            echo 'value="' . $datos[0]['direccion'] . '"';
                                        } ?>>
                                    <label for="direccionjuridica">Domicilio fiscal</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- NAVBAR PARA LOS DISTINTOS TIPOS DE INGRESOS -->
                    <div class="container contenedort pt-3">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Nombres de socios principales</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6  mb-2 mt-2">
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon2"><i class="fa-solid fa-users-line"></i></span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nompresidente" placeholder="Presidente(a)" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . ((isset($socios[0]['name_socio'])) ? $socios[0]['name_socio'] : '-') . '"';
                                                                                                                                } ?>>
                                        <label for="nompresidente">Presidente</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 mb-2 mt-2">
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon2"><i class="fa-solid fa-users-line"></i></span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nomvicepresidente"
                                            placeholder="Vicepresidente(a)" <?php if ($bandera) {
                                                                                echo 'value="' . ((isset($socios[1]['name_socio'])) ? $socios[1]['name_socio'] : '-') . '"';
                                                                            } ?>>
                                        <label for="nomvicepresidente">Vicepresidente(a)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 mb-2 mt-2">
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon2"><i class="fa-solid fa-users-line"></i></span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nomsecretario" placeholder="Secretario(a)" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . ((isset($socios[2]['name_socio'])) ? $socios[2]['name_socio'] : '-') . '"';
                                                                                                                                } ?>>
                                        <label for="nomsecretario">Secretario(a)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 mb-2 mt-2">
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon2"><i class="fa-solid fa-users-line"></i></span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nomtesorero" placeholder="Tesorero(a)" <?php if ($bandera) {
                                                                                                                                echo 'value="' . ((isset($socios[3]['name_socio'])) ? $socios[3]['name_socio'] : '-') . '"';
                                                                                                                            } ?>>
                                        <label for="nomtesorero">Tesorero(a)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 mb-2 mt-2">
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon2"><i class="fa-solid fa-users-line"></i></span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nomvocal1" placeholder="Vocal 1" <?php if ($bandera) {
                                                                                                                            echo 'value="' . ((isset($socios[4]['name_socio'])) ? $socios[4]['name_socio'] : '-') . '"';
                                                                                                                        } ?>>
                                        <label for="nomvocal1">Vocal 1</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 mb-2 mt-2">
                                <div class="input-group">
                                    <span class="input-group-text" id="basic-addon2"><i class="fa-solid fa-users-line"></i></span>
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="nomvocal2" placeholder="Vocal 2" <?php if ($bandera) {
                                                                                                                            echo 'value="' . ((isset($socios[5]['name_socio'])) ? $socios[5]['name_socio'] : '-') . '"';
                                                                                                                        } ?>>
                                        <label for="nomvocal2">Vocal 2</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="container" style="max-width: 100% !important;">
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                            <?php if (!$bandera) { ?>
                                <button class="btn btn-outline-success mt-2"
                                    onclick="obtiene_plus([`razonsocial`,`razoncomercial`,`registrosociedad`,`representantelegal`,`fechafundacion`,`referenciajuridica`,`direccionjuridica`,`nompresidente`,`nomvicepresidente`,`nomsecretario`,`nomtesorero`,`nomvocal1`,`nomvocal2`],[`depclientejuridico`,`municlientejuridico`],[],`create_cliente_juridico`,`0`,['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>'])"><i
                                        class="fa-solid fa-floppy-disk me-2"></i>Guardar</button>
                            <?php } ?>
                            <?php if ($bandera) { ?>
                                <button class="btn btn-outline-primary mt-2"
                                    onclick="obtiene_plus([`razonsocial`,`razoncomercial`,`registrosociedad`,`representantelegal`,`fechafundacion`,`referenciajuridica`,`direccionjuridica`,`nompresidente`,`nomvicepresidente`,`nomsecretario`,`nomtesorero`,`nomvocal1`,`nomvocal2`],[`depclientejuridico`,`municlientejuridico`],[],`update_cliente_juridico`,`0`,['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $datos[0]['codcli']; ?>'])"><i
                                        class="fa-solid fa-floppy-disk me-2"></i>Actualizar</button>
                            <?php } ?>
                            <!-- boton para solicitar credito -->
                            <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2_plus('#cuadro','0')">
                                <i class="fa-solid fa-ban"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                //SELECCIONAR LOS CHECKBOXS DESPUES DE CARGAR EL DOM
                $(document).ready(function() {
                    $("#tb_clientes_juridicos").DataTable({
                        "processing": true,
                        "serverSide": true,
                        "sAjaxSource": "../src/server_side/clientes_juridicos.php",
                        "columnDefs": [{
                            "data": 0,
                            "targets": 4,
                            render: function(data, type, row) {
                                return `
                                    <button type="button" class="btn btn-success btn-sm" onclick="printdiv2('#cuadro','${data}')" >Editar</button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="fichaclijuri('${data}')" >PDF</button>
                                `;

                            }
                        }, ],
                        "lengthMenu": [
                            [5, 10, 25, 50, 100],
                            ['5', '10', '25', '50', '100']
                        ],
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
                    <?php if ($bandera) { ?>
                        ejecutarDespuesDeBuscarMunicipios('buscar_municipios', '#municlientejuridico',
                            '<?= $datos[0]['departamento']; ?>', '<?= $datos[0]['municipio']; ?>');
                    <?php } ?>
                });
            </script>
        <?php
        }
        break;
    case 'list_clientes':
        ?>
        <input type="text" id="file" value="clientes_001" style="display: none;">
        <input type="text" id="condi" value="list_clientes" style="display: none;">
        <div class="text" style="text-align:center">FICHA DE CLIENTES</div>
        <div class="card">
            <div class="card-header">Listado de clientes</div>
            <div class="card-body" style="padding-bottom: 0px !important;">
                <!-- TABLA PARA LOS CLIENTES JURIDICOS -->
                <div class="row border-bottom">
                    <div class="col mb-2">
                        <div class="table-responsive">
                            <table class="table nowrap table-hover table-border" id="tb_clientes"
                                style="width: 100% !important;">
                                <thead class="text-light table-head-aprt">
                                    <tr style="font-size: 0.9rem;">
                                        <th>Código</th>
                                        <th>Nombre Completo</th>
                                        <!-- <th>Tip. Cliente</th> -->
                                        <th>No. Identificación</th>
                                        <th>Fec. Nacimiento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider" style="font-size: 0.9rem !important;">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container" style="max-width: 100% !important;">
                <div class="row justify-items-md-center">
                    <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                        <!-- boton para solicitar credito -->
                        <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2_plus('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $("#tb_clientes").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/clientes_reporte.php",
                    "columnDefs": [{
                        "data": 0,
                        "targets": 4,
                        render: function(data, type, row) {
                            return `
                                                    <button class="btn btn-outline-success" onclick="fichacli('${data}')" >Ficha PDF</button>
                                                    <button  class="btn btn-outline-warning" onclick="generar_json('${data}')" >JSON</button>
                                                `;
                        }

                    }, ],
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

    case 'Editar_Cliente':
    ?>
        <input type="text" id="file" value="clientes_001" style="display: none;">
        <input type="text" id="condi" value="Editar_Cliente" style="display: none;">
        <div class="text" style="text-align:center">EDICION DE CLIENTES</div>
        <div class="card">
            <div class="card-header">Listado de clientes</div>
            <div class="card-body" style="padding-bottom: 0px !important;">
                <div class="row border-bottom">
                    <div class="col mb-2">
                        <div class="table-responsive">
                            <table class="table nowrap table-hover table-border" id="tb_clientes"
                                style="width: 100% !important;">
                                <thead class="text-light table-head-aprt">
                                    <tr style="font-size: 0.8rem;">
                                        <th>Código</th>
                                        <th>Nombre Completo</th>
                                        <th>No. Identificación</th>
                                        <th>Fec. Nacimiento</th>
                                        <th>Actualización</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider" style="font-size: 0.8rem !important;">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container" style="max-width: 100% !important;">
                <div class="row justify-items-md-center">
                    <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                        <!-- boton para solicitar credito -->
                        <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2_plus('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
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
                        data: 'fecha_actualizacion'
                    },
                    {
                        data: null,
                        title: 'Acción',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<button data-bs-dismiss="modal" class="btn btn-outline-warning" 
                            onclick="printdiv('create_cliente_natural', '#cuadro', 'clientes_001','${row.codigo_cliente}');">Editar</button>`;
                        }
                    }
                ];
                const table = initServerSideDataTable(
                    '#tb_clientes',
                    'cli_clientes_natural',
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
        </script>
    <?php
        break;
    case 'Delete_Cliente':
    ?>
        <input type="text" id="file" value="clientes_001" style="display: none;">
        <input type="text" id="condi" value="Delete_Cliente" style="display: none;">
        <div class="text" style="text-align:center">ELIMINACION DE CLIENTES</div>
        <div class="card">
            <div class="card-header">Listado de clientes</div>
            <div class="card-body" style="padding-bottom: 0px !important;">
                <!-- TABLA PARA LOS CLIENTES JURIDICOS -->
                <div class="row border-bottom">
                    <div class="col mb-2">
                        <div class="table-responsive">
                            <table class="table nowrap table-hover table-border" id="tb_clientes"
                                style="width: 100% !important;">
                                <thead class="text-light table-head-aprt">
                                    <tr style="font-size: 0.9rem;">
                                        <th>Código</th>
                                        <th>Nombre Completo</th>
                                        <th>No. Identificación</th>
                                        <th>Fec. Nacimiento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider" style="font-size: 0.9rem !important;">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container" style="max-width: 100% !important;">
                <div class="row justify-items-md-center">
                    <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                        <!-- boton para solicitar credito -->
                        <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2_plus('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $("#tb_clientes").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/clientes_reporte.php",
                    "columnDefs": [{
                        "data": 0,
                        "targets": 4,
                        render: function(data, type, row) {
                            return `
                                                    <button type="button" class="btn btn-outline-danger" onclick="printdiv('delete_user', '#cuadro', 'clientes_001', '${data}')">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                    </button>

                                                `;
                        }

                    }, ],
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

    case 'delete_user': {
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
            $xtra = $_POST["xtra"];

            $bandera = false;
            $datos[] = [];
            $isfile = false;
            $i = 0;
            if ($xtra != 0) {
                $consulta = mysqli_query($conexion, "SELECT * FROM tb_cliente tc WHERE tc.estado='1' AND tc.idcod_cliente='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datos[$i] = $fila;
                    //CARGADO DE LA IMAGEN
                    $imgurl = __DIR__ . '/../../../' . $fila['url_img'];
                    if (!is_file($imgurl)) {
                        $isfile = false;
                        $src = '../includes/img/fotoClienteDefault.png';
                    } else {
                        $isfile = true;
                        $imginfo = getimagesize($imgurl);
                        $mimetype = $imginfo['mime'];
                        $imageData = base64_encode(file_get_contents($imgurl));
                        $src = 'data:' . $mimetype . ';base64,' . $imageData;
                    }
                    $i++;
                    $bandera = true;
                }
            }
        ?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <input type="text" id="file" value="clientes_001" style="display: none;">
            <input type="text" id="condi" value="create_cliente_natural" style="display: none;">
            <div class="text" style="text-align:center"><?= ($bandera) ? 'ELIMINACION ' : 'INGRESO '; ?> DE CLIENTE</div>
            <div class="card">
                <div class="card-header"><?= ($bandera) ? 'Eliminacion ' : 'Ingreso '; ?> de cliente</div>
                <div class="card-body" style="padding-bottom: 0px !important;">
                    <!-- seleccion de cliente y su credito-->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Verifica los datos a eliminar</b></div>
                            </div>
                        </div>
                        <?php if ($bandera) { ?>
                            <div class="row">
                                <div class="col">
                                    <div class="text-center"><span class="text-primary">Codigo cliente:
                                            <b><?php echo $datos[0]['idcod_cliente']; ?></b></span></div>
                                </div>
                            </div>
                            <div class="row justify-content-center">
                                <div class="col-6 col-sm-6 col-md-2 mt-2 d-flex align-items-center">
                                    <div class="mx-auto">
                                        <img id="vistaPrevia" class="img-thumbnail" src="<?php if ($bandera) {
                                                                                                echo $src;
                                                                                            } else {
                                                                                                echo $src;
                                                                                            } ?>" style="max-width:120px; max-height:130px;">
                                    </div>
                                </div>
                            </div>
                        <?php }; ?>

                        <div class="row">
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="nom1" placeholder="Primer nombre" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['primer_name'] . '"disabled';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'],['ape1','ape2','ape3'],1,'#nomcorto'); concatenarValores(['ape1','ape2','ape3'],['nom1','nom2','nom3'],2,'#nomcompleto')">
                                    <label for="cliente">Primer nombre</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="nom2" placeholder="Segundo nombre" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['segundo_name'] . '"disabled';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'],['ape1','ape2','ape3'],1,'#nomcorto'); concatenarValores(['ape1','ape2','ape3'],['nom1','nom2','nom3'],2,'#nomcompleto')">
                                    <label for="cliente">Segundo nombre</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="nom3" placeholder="Tercer nombre" <?php
                                                                                                                    // Verificar si existe la variable $bandera antes de utilizarla para evitar errores
                                                                                                                    if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['tercer_name'] . '" disabled';
                                                                                                                    }
                                                                                                                    ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'],['ape1','ape2','ape3'],1,'#nomcorto'); concatenarValores(['ape1','ape2','ape3'],['nom1','nom2','nom3'],2,'#nomcompleto')">
                                    <label for="nom3">Tercer nombre</label>
                                </div>
                            </div>

                        </div>

                        <!-- apellidos -->
                        <div class="row">
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="ape1" placeholder="Primer apellido" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['primer_last'] . '"disabled';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'],['ape1','ape2','ape3'],1,'#nomcorto'); concatenarValores(['ape1','ape2','ape3'],['nom1','nom2','nom3'],2,'#nomcompleto')">
                                    <label for="cliente">Primer apellido</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="ape2" placeholder="Segundo apellido" <?php if ($bandera) {
                                                                                                                            echo 'value="' . $datos[0]['segundo_last'] . '"disabled';
                                                                                                                        } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'],['ape1','ape2','ape3'],1,'#nomcorto'); concatenarValores(['ape1','ape2','ape3'],['nom1','nom2','nom3'],2,'#nomcompleto')">
                                    <label for="cliente">Segundo apellido</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="ape3" placeholder="Tercer apellido" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['casada_last'] . '"disabled';
                                                                                                                    } ?>
                                        onkeyup="concatenarValores(['nom1','nom2','nom3'],['ape1','ape2','ape3'],1,'#nomcorto'); concatenarValores(['ape1','ape2','ape3'],['nom1','nom2','nom3'],2,'#nomcompleto')">
                                    <label for="cliente">Apellido de casada</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="nomcorto" placeholder="Nombre corto" readonly
                                        disabled <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['short_name'] . '"disabled';
                                                    } ?>>
                                    <label for="nomcorto">Nombre corto</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="nomcompleto" placeholder="Nombre completo" readonly
                                        disabled <?php if ($bandera) {
                                                        echo 'value="' . $datos[0]['compl_name'] . '"disabled';
                                                    } ?>>
                                    <label for="nomcompleto">Nombre completo</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="genero" disabled>
                                        <option value="0" selected>Seleccione un género</option>
                                        <option value="M">Hombre</option>
                                        <option value="F">Mujer</option>
                                        <option value="X">No definido</option>
                                    </select>
                                    <label for="genero">Género</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="estcivil" disabled>
                                        <option value="0" selected>Seleccione un estado civil</option>
                                        <option value="SOLTERO">Soltero(a)</option>
                                        <option value="CASADO">Casado(a)</option>
                                    </select>
                                    <label for="estcivil">Estado civil</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="profesion" placeholder="Profesión" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['profesion'] . '" disabled';
                                                                                                                    } ?>>
                                    <label for="profesion">Profesión</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-3">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="email" class="form-control" id="email" placeholder="Email" <?php if ($bandera) {
                                                                                                                echo 'value="' . $datos[0]['email'] . '" disabled';
                                                                                                            } ?>>
                                    <label for="email">Email</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-3">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="conyugue" placeholder="Conyugue" <?php if ($bandera) {
                                                                                                                        echo 'value="' . $datos[0]['Conyuge'] . '" disabled';
                                                                                                                    } ?>>
                                    <label for="conyugue">Cónyuge</label>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- DOCUMENTO DE INDENTIFICACION -->
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="text-center mb-2"><b>Documento de identificación</b></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="tipodoc" disabled>
                                        <option value="DPI" selected>DPI</option>
                                        <option value="PASAPORTE">Pasaporte</option>
                                    </select>
                                    <label for="tipodoc">Tipo de documento</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 col-md-4">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="numberdoc" placeholder="Número de documento" <?php if ($bandera) {
                                                                                                                                    echo 'value="' . $datos[0]['no_identifica'] . '" disabled';
                                                                                                                                } ?>>
                                    <label for="numberdoc">Número de documento</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <select class="form-select" id="tipoidentri" disabled>
                                        <option value="NIT" selected>NIT</option>
                                        <option value="CUI">CUI</option>
                                    </select>
                                    <label for="tipoidentri">Tipo de indent. tributaria</label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="text" class="form-control" id="numbernit" placeholder="Número tributario" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['no_tributaria'] . '" disabled';
                                                                                                                            } ?>>
                                    <label for="numbernit">Número tributario</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6">
                                <div class="form-floating mb-2 mt-2">
                                    <input type="number" class="form-control" id="afiliggs" placeholder="Afiliación IGGS" <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['no_igss'] . '" disabled';
                                                                                                                            } ?>>
                                    <label for="afiliggs">Afiliación IGGS</label>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <div class="container" style="max-width: 100% !important;">
                    <div class="row justify-items-md-center">
                        <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                            <?php if (!$bandera) { ?>
                                <button class="btn btn-outline-success mt-2"
                                    onclick="obtiene_plus2([`nom1`,`nom2`,`nom3`,`ape1`,`ape2`,`ape3`,`profesion`,`email`,`conyugue`,`fechanacimiento`,`edad`,`dirnac`,`numberdoc`,`numbernit`,`afiliggs`,`reside`,`dirviv`,`refviv`,`representante`,`refn1`,`ref1`,`refn2`,`ref2`,`refn3`,`ref3`,`tel1`,`tel2`],[`genero`,`estcivil`,`origen`,`paisnac`,`depnac`,`muninac`,`docextend`,`tipodoc`,`tipoidentri`,`nacionalidad`,`condicion`,`depdom`,`munidom`,`actpropio`,`actcalidad`,`otranacionalidad`,`etnia`,`religion`,`educacion`,`relinsti`],[`leer`,`escribir`,`firma`,`pep`,`cpe`],`create_cliente_natural`,`0`,['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>'])"><i
                                        class="fa-solid fa-floppy-disk me-2"></i>Guardar cliente</button>
                                <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2_plus('#cuadro','0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                            <?php } ?>
                            <?php if ($bandera) { ?>
                                <!-- eliminar boton -->

                                <button class="btn btn-outline-danger mt-2"
                                    onclick="eliminar_plus(['<?= $codusu; ?>','<?= $id_agencia; ?>','<?= $codagencia; ?>','<?= $xtra; ?>'], `0`, `delete_cliente_natural`, `¿Está seguro de eliminar el cliente <?= $xtra; ?>?`)"><i
                                        class="fa-solid fa-floppy-disk me-2"></i>Eliminar cliente</button>

                                <button type="button" class="btn btn-outline-danger mt-2"
                                    onclick="printdiv('Delete_Cliente', '#cuadro', 'clientes_001', '0')">
                                    <i class="fa-solid fa-ban"></i> Cancelar
                                </button>
                            <?php } ?>
                            <!-- boton para solicitar credito -->
                            <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                                <i class="fa-solid fa-circle-xmark"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            <script>
                //SELECCIONAR LOS CHECKBOXS DESPUES DE CARGAR EL DOM
                $(document).ready(function() {
                    <?php if ($bandera) { ?>
                        seleccionarValueSelect('#genero', '<?= $datos[0]['genero']; ?>');
                        seleccionarValueSelect('#estcivil', '<?= $datos[0]['estado_civil']; ?>');
                        seleccionarValueSelect('#tipodoc', '<?= $datos[0]['type_doc']; ?>');
                        seleccionarValueSelect('#tipoidentri', '<?= $datos[0]['identi_tribu']; ?>');
                    <?php }; ?>
                });
            </script>
        <?php
        }
        break;
    case "huella_cli":
        /**
         * Procesa la solicitud de cliente y obtiene información del cliente y sus huellas digitales.
         *
         * @throws Exception Si no se selecciona un cliente o si no se encuentra el cliente seleccionado.
         *
         * Variables:
         * @var string $codcliente Código del cliente recibido desde el modal, por defecto se recibe 0.
         * @var bool $showmensaje Indica si se debe mostrar un mensaje de error específico.
         * @var array $cliente Información del cliente obtenida de la base de datos.
         * @var array $huellas Información de las huellas digitales del cliente obtenida de la base de datos.
         * @var int $status Estado de la operación (1 = éxito, 0 = error).
         * @var string $mensaje Mensaje de error a mostrar en caso de fallo.
         * @var string $codigoError Código de error generado en caso de excepción.
         *
         * Funciones utilizadas:
         * @function openConnection() Abre una conexión a la base de datos.
         * @function selectColumns() Selecciona columnas específicas de una tabla en la base de datos.
         * @function closeConnection() Cierra la conexión a la base de datos.
         * @function logerrores() Registra errores en un log y devuelve un código de error.
         */
        $huella_version = isset($_ENV['HUELLA_VERSION']) ? $_ENV['HUELLA_VERSION'] : 1;
        $codcliente = $_POST['xtra'];
        $showmensaje = false;
        try {
            if ($codcliente == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione un cliente");
            }
            $database->openConnection();
            $cliente = $database->selectColumns('tb_cliente', ['idcod_cliente', 'short_name', 'no_identifica'], "estado=1 AND idcod_cliente=?", [$codcliente]);
            if (empty($cliente)) {
                $showmensaje = true;
                throw new Exception("No se encontró el cliente seleccionado");
            }
            $huellas = $database->selectColumns('huella_digital', ['id', 'mano', 'dedo', 'imgHuella'], "id_persona=? AND estado = 1 AND tipo_persona=1", [$codcliente]);


            if ($huella_version == 2) {
                /**
                 * configuracion de ably
                 */
                $ablyConfig = AblyService::getInstance()->getClientConfig();
            }


            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        $sessionSerial = generarCodigoAleatorio();
        // echo "<pre>";
        // echo print_r($permisos);
        // echo "</pre>";

        // define('HUELLA_VERSION', isset($_ENV['HUELLA_VERSION']) ? $_ENV['HUELLA_VERSION'] : 1);
        // define('ABLY_API_KEY', isset($_ENV['ABLY_API_KEY']) ? $_ENV['ABLY_API_KEY'] : '');
        // define('ABLY_CHANNEL_HUELLA', isset($_ENV['ABLY_CHANNEL_HUELLA']) ? $_ENV['ABLY_CHANNEL_HUELLA'] : '');
        ?>
        <div class="modal fade" id="buscar_cli_gen" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Busqueda de Clientes</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table id="tb_buscaClient" class="table table-striped nowrap" style="width: 100%;">
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
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
        <input type="text" id="file" value="clientes_001" style="display: none;">
        <input type="text" id="condi" value="huella_cli" style="display: none;">
        <div class="card  container mt-3">
            <div class="card-header font-weight-bold">
                <h3>Registro de huellas digitales</h3>
            </div>
            <?php if (!$status) { ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>¡Alerta!</strong> <?= $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>
            <div class="row">
                <div class="card col-lg-6 col-md-11" style="max-width: 100% !important;">
                    <div class="row d-flex justify-content-center">
                        <div class="col-lg-12 col-md-12 mt-2">
                            <label class="form-label fw-bold">Cliente seleccionado</label><br>
                            <div class="input-group mb-3">
                                <button class="btn btn-warning" type="button" id="button-addon1" data-bs-toggle="modal"
                                    data-bs-target="#buscar_cli_gen"><i class="fa-solid fa-users"></i> Buscar</button>
                                <input id="nomCliente" type="text" class="form-control" placeholder="Cliente"
                                    aria-label="Example text with button addon" aria-describedby="button-addon1" readonly
                                    value="<?= $cliente[0]['short_name'] ?? "" ?>">
                            </div>
                            <input type="text" id="codCli" hidden value="<?= $cliente[0]['idcod_cliente'] ?? "" ?>">
                        </div>
                    </div>
                    <div id="con01">
                        <div class="row d-flex justify-content-center">
                            <div class="col-lg-6 col-md-12 mt-2">
                                <label class="form-label fw-bold">Seleccione una mano</label>
                                <select id="mano" class="form-select" aria-label="Default select example">
                                    <option value="0" selected="selectd">Mano Izquierda</option>
                                    <option value="1">Mano Derecha</option>
                                </select>
                            </div>

                            <div class="col-lg-6 col-md-12 mt-2">
                                <label class="form-label fw-bold">Seleccionar un dedo</label>
                                <select id="dedo" class="form-select" aria-label="Default select example">
                                    <option value="1" selected="selectd">Pulgar</option>
                                    <option value="2">Ìndice</option>
                                    <option value="3">Medio</option>
                                    <option value="4">Anular</option>
                                    <option value="5">Meñique</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col d-flex justify-content-center">
                                <div class="card" style="width:175x">

                                    <div class="container">
                                        <img id="imghuella" class="mx-auto d-block"
                                            src="https://c0.klipartz.com/pngpicture/1000/646/gratis-png-logo-huella-digital-computadora-iconos-digito-diseno.png"
                                            alt="Card image" height="200">
                                    </div>

                                    <?php if ($status) { ?>
                                        <?php if ($huella_version == 1) { ?>
                                            <div class="card-body d-flex justify-content-center">
                                                <button id="buttonShot" class="btn btn-primary"
                                                    onclick="obtiene_plus(['srnPc','sessionSerial'],[],[],'activarSensor','0',[0,'<?= $codcliente ?>']);"><i
                                                        class="fa-solid fa-fingerprint"></i> capturar huella</button>
                                            </div>
                                        <?php } elseif ($huella_version == 2) { ?>
                                            <div class="card-body d-flex justify-content-center">
                                                <button id="buttonShot" class="btn btn-primary"
                                                    onclick="obtiene_plus(['srnPc','sessionSerial'],[],[],'activarSensorCaptura','0',[0,'<?= $codcliente ?>']);"><i
                                                        class="fa-solid fa-fingerprint"></i> capturar huella</button>
                                            </div>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php echo $csrf->getTokenField(); ?>
                        <input type="text" id="srnPc" hidden value="">
                        <input type="text" id="sessionSerial" hidden value="<?= $sessionSerial ?>">
                        <div class="row mt-3">
                            <div class="col md-3 d-flex justify-content-center">
                                <button type="button" class="btn btn-outline-danger"
                                    onclick="printdiv2_plus('#cuadro', '<?= $codcliente ?>');"><i class="fa-solid fa-ghost"></i>
                                    Cancelar</button>
                                <?php if ($status) { ?>
                                    <button id="buttonSave" type="button" hidden class="btn btn-outline-success"
                                        onclick="obtiene_plus(['<?= $csrf->getTokenName() ?>','srnPc','sessionSerial'],['mano','dedo'],[],'saveHuella','<?= $codcliente ?>',['<?= htmlspecialchars($secureID->encrypt($codcliente)) ?>'])"><i
                                            class="fa-regular fa-floppy-disk"></i> Guardar</button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card  col-lg-6 col-md-11">
                    <div class="card">
                        <h5 class="card-header">Huellas registradas</h5>
                        <div class="card-body">
                            <div class="class" id="tb_huellas">
                                <div class="container mt-3">
                                    <table class="table" id="r_huellas">
                                        <thead class="table-dark">
                                            <tr>
                                                <th hidden>id</th>
                                                <th>mano</th>
                                                <th>dedo</th>
                                                <th>huella</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (isset($huellas)) {
                                                foreach ($huellas as $row) {
                                                    $image_base64 = $row['imgHuella'];
                                                    $idHuella = $row['id']
                                            ?>
                                                    <tr>
                                                        <td hidden><?= $row['id'] ?></td>
                                                        <td><?= ($row['mano'] == 0) ? "Izquierda" : "Derecha" ?></td>
                                                        <td>
                                                            <?= ($row['dedo'] == 1) ? "Pulgar" : (($row['dedo'] == 2) ? "Índice" : (($row['dedo'] == 3) ? "Medio" : (($row['dedo'] == 4) ? "Anular" : (($row['dedo'] == 5) ? "Meñique" : "-")))) ?>
                                                        </td>
                                                        <td>
                                                            <img id="<?= $row['id'] . 'huella' ?>"
                                                                src="data:image/png;base64,<?= $image_base64 ?>" width="40" height="50">
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-outline-danger"
                                                                onclick="eliminar_plus('<?= htmlspecialchars($secureID->encrypt($idHuella)) ?>', '<?= $codcliente ?>', 'eliminaHuella')"><i
                                                                    class="fa-solid fa-trash-can"></i></button>
                                                        </td>
                                                    </tr>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
                <br>
            </div>

            <div style="display: block;padding-left: 3px;">
                <label class="form-label" id="statusPlantilla" style="margin-left: 5px;">
                    Estado del sensor: Inactivo
                </label>
                <textarea class="form-control" id="textoSensor" cols="30" rows="3" readonly>---</textarea>
            </div>
        </div>
        <button type="button" class="btn btn-danger"
            onclick="obtiene_plus(['srnPc','sessionSerial'],[],[],'activarSensor','0',[1,'<?= $codcliente ?>']);">

        </button>

        <script>
            $(document).ready(function() {
                verificacion();
                $("#tb_buscaClient").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/clientes_no_juridicos.php",
                    "columnDefs": [{
                        "data": 0,
                        "targets": 4,
                        render: function(data, type, row) {
                            // console.log(data);
                            return `<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2('#cuadro','${data}')" >Aceptar</button>`;
                        }

                    }, ],
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

            function verificacion() {
                if (!localStorage.getItem("srnPc")) {
                    iziToast.info({
                        title: 'No hay ningún token asignado a ésta instancia',
                        position: 'center',
                        message: 'Puede solicitar uno con el Administrador',
                        timeout: 7000
                    });
                    return;
                }
                document.getElementById("srnPc").value = localStorage.getItem("srnPc");
            }
        </script>
        <?php if ($status && $huella_version == 2) { ?>
            <script type="module">
                async function subscribe() {
                    const realtime = new Ably.Realtime.Promise("<?= $ablyConfig['clientKey'] ?>");

                    let srn = localStorage.getItem("srnPc");
                    const channel = realtime.channels.get("<?= $ablyConfig['channelPrefix']; ?>_" + srn);
                    await channel.subscribe("sinc", (message) => {
                        // console.log("Message received: " + message.data)
                        loadDataFinger(function(data) {
                            // console.log(data);
                            $("#statusPlantilla").text(data["statusPlantilla"]);
                            $("#textoSensor").text(data["texto"]);
                            let imageHuella = data["imgHuella"];

                            if (imageHuella !== null) {
                                // console.log("desde aka");
                                $("#imghuella").attr("src", "data:image/png;base64," + imageHuella);
                                if (data["statusPlantilla"] === "Muestras Restantes: 0") {
                                    // console.log("se desactiva el sensor abl.");
                                    $("#buttonSave").removeAttr("hidden");
                                    $("#buttonShot").attr("hidden", true);
                                }
                            }
                        });
                    });
                };

                subscribe();
            </script>
        <?php } ?>
    <?php
        break;


    /**
     * Vista para gestión de información adicional de clientes
     * Incluye: Mapa interactivo, CRUD de datos adicionales, gestión de archivos
     */

    case "info_adicional_cliente":
        /**
         * Procesa la información adicional del cliente seleccionado
         */

        $codcliente = $_POST['xtra'] ?? '0';
        $showmensaje = false;

        try {
            if ($codcliente == '0') {
                $showmensaje = true;
                throw new Exception("Seleccione un cliente");
            }

            $database->openConnection();

            // Obtener información del cliente
            $cliente = $database->selectColumns(
                'tb_cliente',
                ['idcod_cliente', 'short_name', 'no_identifica'],
                "estado=1 AND idcod_cliente=?",
                [$codcliente]
            );

            if (empty($cliente)) {
                $showmensaje = true;
                throw new Exception("No se encontró el cliente seleccionado");
            }

            // Obtener información adicional del cliente - FIX: precision entre backticks
            $info_adicional = $database->selectColumns(
                'cli_adicionales',
                ['id', 'entidad_tipo', 'entidad_id', 'descripcion', 'latitud', 'longitud', 'altitud', '`precision`', 'direccion_texto', 'estado', 'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at'],
                "entidad_tipo='cliente' AND entidad_id=? AND estado=1",
                [$codcliente]
            );

            // Obtener archivos relacionados
            $archivos = $database->selectColumns(
                'cli_adicional_archivos',
                ['id', 'id_adicional', 'path_file'],
                "id_adicional IN (SELECT id FROM cli_adicionales WHERE entidad_id=? AND estado=1)",
                [$codcliente]
            );

            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $sessionSerial = generarCodigoAleatorio();
    ?>

        <!-- Modal para búsqueda de clientes -->
        <div class="modal fade" id="buscar_cli_gen" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Búsqueda de Clientes</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table id="tb_buscaClient" class="table table-striped nowrap" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th scope="col">Código</th>
                                        <th scope="col">Nombre Completo</th>
                                        <th scope="col">No. Identificación</th>
                                        <th scope="col">Nacimiento</th>
                                        <th scope="col">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="categoria_tb"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inputs ocultos requeridos -->
        <input type="text" id="file" value="clientes_001" style="display: none;">
        <input type="text" id="condi" value="info_adicional_cliente" style="display: none;">
        <input type="hidden" id="sessionSerial" name="sessionSerial" value="<?= $_SESSION['sessionSerial'] ?? session_id() ?>">

        <div class="container-fluid mt-3">
            <?php if (!$status) { ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>¡Alerta!</strong> <?= $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <div class="row">
                <!-- Columna izquierda: Formulario -->
                <div class="col-lg-4 col-md-12">
                    <!-- Tarjeta de búsqueda de cliente -->
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fa-solid fa-search"></i> Buscar cliente</h6>
                        </div>
                        <div class="card-body">
                            <div class="input-group">
                                <button class="btn btn-outline-info" type="button" data-bs-toggle="modal"
                                    data-bs-target="#buscar_cli_gen">
                                    <i class="fa-solid fa-users"></i>
                                </button>
                                <input id="nomCliente" type="text" class="form-control" placeholder="Seleccione un cliente"
                                    readonly value="<?= $cliente[0]['short_name'] ?? "" ?>">
                            </div>
                            <input type="text" id="codCli" hidden value="<?= $cliente[0]['idcod_cliente'] ?? "" ?>">
                        </div>
                    </div>

                    <?php if ($status) { ?>
                        <!-- Formulario de información adicional -->
                        <div class="card mb-3">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-edit"></i> Información Adicional</h6>
                            </div>
                            <div class="card-body">
                                <!-- Selector de tipo de entidad -->
                                <!-- Tipo de entidad (oculto, siempre 'cliente') -->
                                <input type="hidden" id="entidad_tipo" value="cliente">

                                <!-- Descripción -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Descripción</label>
                                    <textarea id="descripcion" class="form-control form-control-sm" rows="3"
                                        placeholder="Ingrese una descripción detallada..."></textarea>
                                </div>

                                <!-- Coordenadas GPS -->
                                <div class="row">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fw-bold">Latitud</label>
                                        <input type="number" id="latitud" class="form-control form-control-sm" step="any"
                                            placeholder="14.6349">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fw-bold">Longitud</label>
                                        <input type="number" id="longitud" class="form-control form-control-sm" step="any"
                                            placeholder="-90.5069">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fw-bold">Altitud (m)</label>
                                        <input type="number" id="altitud" class="form-control form-control-sm" step="any"
                                            placeholder="1500">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fw-bold">Precisión (m)</label>
                                        <input type="number" id="precision_gps" class="form-control form-control-sm" step="any"
                                            placeholder="5" max="99.99">
                                    </div>
                                </div>

                                <!-- Dirección -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Dirección</label>
                                    <textarea id="direccion_texto" class="form-control form-control-sm" rows="2"
                                        placeholder="Ingrese la dirección completa..."></textarea>
                                </div>

                                <!-- Botones de ubicación -->
                                <div class="d-grid gap-2 d-md-block mb-3">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="obtenerUbicacionActual()">
                                        <i class="fa-solid fa-location-dot"></i> GPS Actual
                                    </button>
                                    <button type="button" class="btn btn-info btn-sm" onclick="seleccionarEnMapa()">
                                        <i class="fa-solid fa-map-pin"></i> Usar Mapa
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="limpiarUbicacion()">
                                        <i class="fa-solid fa-eraser"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Carga de Archivos Múltiples -->
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-upload"></i> Archivos Adjuntos</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <input type="file" id="archivos_adjuntos" class="form-control form-control-sm" multiple
                                        accept="image/*,application/pdf,.doc,.docx,.txt,.xlsx,.xls">
                                    <small class="text-muted">Formatos: Imágenes, PDF, Word, Excel, Texto</small>
                                </div>

                                <!-- Preview de archivos seleccionados -->
                                <div id="preview_archivos" class="row"></div>

                                <!-- Preview de archivos existentes -->
                                <div id="preview_archivos_existentes" class="row mt-3"></div>

                                <!-- Contador de archivos -->
                                <div id="contador_archivos" class="mt-2" style="display: none;">
                                    <small class="text-info">
                                        <i class="fa-solid fa-paperclip"></i>
                                        <span id="numero_archivos">0</span> archivo(s) seleccionado(s)
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <button class="btn btn-outline-success mt-2" onclick="guardarInfoAdicionalPlus()">
                                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar información adicional
                                    </button>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-danger w-100" onclick="limpiarFormulario()">
                                            <i class="fa-solid fa-times"></i> Limpiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Columna derecha: Mapa y Tabla -->
                <div class="col-lg-8 col-md-12">
                    <!-- Mapa Interactivo -->
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fa-solid fa-map"></i> Mapa Interactivo</h6>
                            <small class="text-light">
                                <i class="fa-solid fa-info-circle"></i> Haga clic en el mapa para seleccionar ubicación
                            </small>
                        </div>
                        <div class="card-body p-0">
                            <div id="mapa_principal" style="height: 400px; width: 100%; background-color: #f8f9fa;">
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <div class="text-center">
                                        <i class="fa-solid fa-map-pin fa-3x text-muted mb-2"></i>
                                        <p class="text-muted">Cargando mapa...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de datos -->
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h6 class="mb-0"><i class="fa-solid fa-table"></i> Información Registrada</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0" id="tabla_info_adicional">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Tipo</th>
                                            <th>Descripción</th>
                                            <th>Coordenadas</th>
                                            <th>Dirección</th>
                                            <th>Archivos</th>
                                            <th>Opciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($info_adicional) && !empty($info_adicional)) {
                                            foreach ($info_adicional as $info) {
                                                // Buscar archivos relacionados
                                                $archivos_info = array_filter($archivos, function ($archivo) use ($info) {
                                                    return $archivo['id_adicional'] == $info['id'];
                                                });

                                                // Contar archivos
                                                $total_archivos = count($archivos_info);

                                                // Obtener primera imagen si existe
                                                $primera_imagen = null;
                                                foreach ($archivos_info as $archivo) {
                                                    $extension = strtolower(pathinfo($archivo['path_file'], PATHINFO_EXTENSION));
                                                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                                                        $primera_imagen = $archivo['path_file'];
                                                        break;
                                                    }
                                                }
                                        ?>
                                                <tr>
                                                    <td><span class="badge bg-primary"><?= $info['id'] ?></span></td>
                                                    <td>
                                                        <span
                                                            class="badge <?= $info['entidad_tipo'] == 'cliente' ? 'bg-info' : 'bg-warning' ?>">
                                                            <?= ucfirst($info['entidad_tipo']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?= htmlspecialchars(substr($info['descripcion'] ?? '', 0, 50)) ?><?= strlen($info['descripcion'] ?? '') > 50 ? '...' : '' ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($info['latitud'] && $info['longitud']) { ?>
                                                            <small class="text-primary">
                                                                <i class="fa-solid fa-map-pin"></i>
                                                                <?= number_format($info['latitud'], 4) ?>,<?= number_format($info['longitud'], 4) ?>
                                                            </small>
                                                        <?php } else { ?>
                                                            <span class="text-muted">-</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <small><?= htmlspecialchars(substr($info['direccion_texto'] ?? '', 0, 30)) ?><?= strlen($info['direccion_texto'] ?? '') > 30 ? '...' : '' ?></small>
                                                    </td>
                                                    <!-- DENTRO DEL FOREACH EN LA TABLA -->
                                                    <td>
                                                        <?php if ($primera_imagen) {
                                                            // ✅ Usar FileProcessor para generar la URL correcta
                                                            $fileProcessor = new FileProcessor(__DIR__ . '/../../../');

                                                            if ($fileProcessor->fileExists($primera_imagen)) {
                                                                $fileInfo = $fileProcessor->getFileInfo($primera_imagen);
                                                                $imagenUrl = $fileInfo['data_uri']; // Data URI embebido (base64)
                                                        ?>
                                                                <img src="<?= $imagenUrl ?>" alt="Vista previa"
                                                                    style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; cursor: pointer;"
                                                                    onclick="verImagenCompleta('<?= htmlspecialchars($imagenUrl) ?>')"
                                                                    title="Click para ver imagen completa">
                                                            <?php } else { ?>
                                                                <span class="text-muted" title="Archivo no encontrado">
                                                                    <i class="fa-solid fa-exclamation-triangle text-warning"></i>
                                                                </span>
                                                            <?php } ?>
                                                        <?php } ?>

                                                        <?php if ($total_archivos > 0) { ?>
                                                            <small class="text-muted ms-1">(<?= $total_archivos ?>)</small>
                                                        <?php } else { ?>
                                                            <span class="text-muted">-</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-primary"
                                                                onclick="verDetalle(<?= $info['id'] ?>)" title="Ver">
                                                                <i class="fa-solid fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-warning"
                                                                onclick="editarInfo(<?= $info['id'] ?>)" title="Editar">
                                                                <i class="fa-solid fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger"
                                                                onclick="eliminarInfo(<?= $info['id'] ?>)" title="Eliminar">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                            <?php if ($info['latitud'] && $info['longitud']) { ?>
                                                                <button type="button" class="btn btn-outline-info"
                                                                    onclick="centrarEnMapa(<?= $info['latitud'] ?>, <?= $info['longitud'] ?>)"
                                                                    title="Ver en mapa">
                                                                    <i class="fa-solid fa-map-pin"></i>
                                                                </button>
                                                            <?php } ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php }
                                        } else { ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                                                    No hay información registrada
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para ver imagen completa -->
        <div class="modal fade" id="modal_imagen_completa" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Vista de Imagen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="imagen_modal" src="" alt="Imagen completa" style="max-width: 100%; height: auto;">
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Variables globales
            var mapaInfoCliente = null;
            var marcadorTemporalInfoCliente = null;
            var marcadoresInfoCliente = [];
            var modoSeleccionMapa = false;
            var watchId = null;
            var mejorPrecision = null;

            // ⭐ FUNCIÓN SIMPLE: Convertir ruta de BD a URL accesible
            function construirUrlArchivo(pathFile) {
                //console.log('🔗 Construyendo URL para:', pathFile);

                // Si ya es URL completa, retornarla
                if (/^https?:\/\//i.test(pathFile)) {
                    //  console.log('✅ Ya es URL completa');
                    return pathFile;
                }

                // Si viene de BD como: "imgcoope.microsystemplus.com/demo/001900500008/adicional/49/archivo.jpg"
                // Solo agregar protocolo
                if (pathFile.startsWith('imgcoope.microsystemplus.com')) {
                    const url = `${window.location.protocol}//${pathFile}`;
                    //console.log('✅ URL construida:', url);
                    return url;
                }

                // Si viene como ruta vieja relativa: "001900500008/adicional/49/archivo.jpg"
                // Construir ruta completa hacia atrás
                const url = `../../../imgcoope.microsystemplus.com/${pathFile}`;
                //console.log('⚠️ Ruta vieja detectada, construida:', url);
                return url;
            }

            // Función para manejar errores de carga de imagen
            function manejarErrorImagen(img, nombreArchivo) {
                console.error(`❌ Error cargando: ${img.src}`);
                console.error(`📁 Nombre archivo: ${nombreArchivo}`);

                // Mostrar placeholder de error
                img.style.display = 'none';
                if (img.parentElement) {
                    img.parentElement.innerHTML = `
                <div class="bg-warning text-dark p-2 rounded text-center" style="height: 60px; font-size: 10px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                    <i class="fa-solid fa-exclamation-triangle mb-1"></i>
                    <span>Imagen no encontrada</span>
                    <small>${nombreArchivo}</small>
                </div>
            `;
                }
            }

            $(document).ready(function() {
                // Inicializar DataTable para búsqueda de clientes
                if ($.fn.DataTable.isDataTable("#tb_buscaClient")) {
                    $("#tb_buscaClient").DataTable().destroy();
                }

                $("#tb_buscaClient").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../src/server_side/clientes_no_juridicos.php",
                    "columnDefs": [{
                        "data": 0,
                        "targets": 4,
                        render: function(data, type, row) {
                            return `<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2('#cuadro','${data}')">Aceptar</button>`;
                        }
                    }],
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

                // Cargar e inicializar mapa
                if (typeof L !== 'undefined') {
                    setTimeout(function() {
                        inicializarMapaInfoCliente();
                    }, 500);
                } else {
                    cargarLeaflet();
                }

                // Preview de archivos seleccionados
                $('#archivos_adjuntos').on('change', function(e) {
                    mostrarPreviewArchivos(e.target.files);
                });
            });

            function cargarLeaflet() {
                // Cargar CSS de Leaflet
                if (!$('link[href*="leaflet"]').length) {
                    $('<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />').appendTo('head');
                }

                // Cargar JS de Leaflet
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
                        // console.log('Contenedor del mapa no encontrado');
                        return;
                    }

                    // Coordenadas por defecto (Guatemala)
                    mapaInfoCliente = L.map('mapa_principal').setView([14.6349, -90.5069], 8);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(mapaInfoCliente);

                    // Agregar marcadores existentes
                    <?php if (isset($info_adicional) && !empty($info_adicional)) {
                        foreach ($info_adicional as $info) {
                            if ($info['latitud'] && $info['longitud']) { ?>
                                var marcadorExistente = L.marker([<?= $info['latitud'] ?>, <?= $info['longitud'] ?>])
                                    .addTo(mapaInfoCliente)
                                    .bindPopup(`
                                <div style="min-width: 200px;">
                                    <strong>ID: <?= $info['id'] ?></strong><br>
                                    <strong>Tipo:</strong> <?= ucfirst($info['entidad_tipo']) ?><br>
                                    <strong>Descripción:</strong><br>
                                    <small><?= htmlspecialchars(substr($info['descripcion'] ?? '', 0, 100)) ?></small><br>
                                    <strong>Dirección:</strong><br>
                                    <small><?= htmlspecialchars($info['direccion_texto'] ?? '') ?></small>
                                </div>
                            `);
                                marcadoresInfoCliente.push(marcadorExistente);
                    <?php }
                        }
                    } ?>

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

                            // Actualizar campos del formulario
                            $('#latitud').val(e.latlng.lat.toFixed(6));
                            $('#longitud').val(e.latlng.lng.toFixed(6));

                            // Obtener dirección aproximada usando geocodificación inversa
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
                // Usar Nominatim para geocodificación inversa
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.display_name) {
                            $('#direccion_texto').val(data.display_name);
                        }
                    })
                    .catch(error => {
                        //console.log('No se pudo obtener la dirección:', error);
                    });
            }

            function mostrarPreviewArchivos(files) {
                const previewContainer = $('#preview_archivos');
                const contadorContainer = $('#contador_archivos');
                const numeroArchivos = $('#numero_archivos');

                previewContainer.empty();

                if (files.length === 0) {
                    contadorContainer.hide();
                    return;
                }

                contadorContainer.show();
                numeroArchivos.text(files.length);

                Array.from(files).forEach((file, index) => {
                    const fileType = file.type;
                    const fileName = file.name;
                    const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB

                    if (fileType.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewContainer.append(`
                        <div class="col-3 mb-2">
                            <div class="position-relative">
                                <img src="${e.target.result}" alt="${fileName}" 
                                     class="img-thumbnail preview-img" 
                                     style="width: 100%; height: 60px; object-fit: cover; cursor: pointer;" 
                                     title="${fileName} (${fileSize} MB)"
                                     onclick="verImagenCompleta('${e.target.result}')">
                                <small class="position-absolute bottom-0 start-0 bg-dark text-white px-1" style="font-size: 8px;">
                                    ${fileSize}MB
                                </small>
                            </div>
                        </div>
                    `);
                        };
                        reader.readAsDataURL(file);
                    } else {
                        // Iconos para diferentes tipos de archivo
                        let iconClass = 'fa-file';
                        if (fileType.includes('pdf')) iconClass = 'fa-file-pdf';
                        else if (fileType.includes('word') || fileName.includes('.doc')) iconClass = 'fa-file-word';
                        else if (fileType.includes('excel') || fileName.includes('.xls')) iconClass = 'fa-file-excel';
                        else if (fileType.includes('text')) iconClass = 'fa-file-text';

                        previewContainer.append(`
                    <div class="col-3 mb-2">
                        <div class="bg-secondary text-white p-2 rounded text-center position-relative" style="height: 60px; font-size: 10px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                            <i class="fa-solid ${iconClass} mb-1"></i>
                            <span>${fileName.substring(0, 8)}...</span>
                            <small class="position-absolute bottom-0 start-0 bg-dark text-white px-1" style="font-size: 8px;">
                                ${fileSize}MB
                            </small>
                        </div>
                    </div>
                `);
                    }
                });
            }

            // ⭐ FUNCIÓN CORREGIDA PARA MOSTRAR IMÁGENES EXISTENTES
            function mostrarImagenesExistentes(imagenes) {
                const previewContainer = $('#preview_archivos_existentes');

                if (!previewContainer.length) {
                    // console.log('⚠️ Contenedor de imágenes existentes no encontrado');
                    return;
                }

                previewContainer.empty();

                if (!imagenes || imagenes.length === 0) {
                    previewContainer.html('<p class="text-muted">📄 No hay archivos adjuntos</p>');
                    return;
                }

                //console.log('📂 Mostrando', imagenes.length, 'archivos');

                imagenes.forEach((imagen, index) => {
                    const nombreArchivo = imagen.filename || imagen.path_file.split('/').pop();

                    // console.log(`📷 Archivo ${index}:`, {
                    //     filename: nombreArchivo,
                    //     exists: imagen.exists,
                    //     is_image: imagen.is_image,
                    //     has_data_uri: !!imagen.data_uri
                    // });

                    if (imagen.is_image && imagen.data_uri && imagen.exists) {
                        // ✅ Imagen procesada correctamente por PHP
                        previewContainer.append(`
                    <div class="col-3 mb-2 archivo-item" data-archivo-id="${imagen.id}">
                        <div class="position-relative">
                            <img src="${imagen.data_uri}" 
                                 alt="${nombreArchivo}" 
                                 class="img-thumbnail imagen-existente" 
                                 style="width: 100%; height: 60px; object-fit: cover; cursor: pointer;" 
                                 title="${nombreArchivo}"
                                 onclick="verImagenCompleta('${imagen.data_uri}')">
                            <small class="position-absolute bottom-0 start-0 bg-dark text-white px-1" style="font-size: 8px;">
                                ${nombreArchivo.substring(0, 10)}...
                            </small>
                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                    style="font-size: 8px; padding: 2px 4px;"
                                    onclick="eliminarArchivoIndividual(${imagen.id}, '${nombreArchivo}')"
                                    title="Eliminar archivo">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `);
                    } else if (imagen.exists) {
                        // Archivo no imagen pero existe
                        let iconClass = 'fa-file';
                        if (nombreArchivo.includes('.pdf')) iconClass = 'fa-file-pdf';
                        else if (nombreArchivo.includes('.doc')) iconClass = 'fa-file-word';
                        else if (nombreArchivo.includes('.xls')) iconClass = 'fa-file-excel';

                        previewContainer.append(`
                    <div class="col-3 mb-2 archivo-item" data-archivo-id="${imagen.id}">
                        <div class="bg-secondary text-white p-2 rounded text-center position-relative" style="height: 60px; font-size: 10px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                            <i class="fa-solid ${iconClass} mb-1"></i>
                            <span>${nombreArchivo.substring(0, 8)}...</span>
                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                    style="font-size: 8px; padding: 2px 4px;"
                                    onclick="eliminarArchivoIndividual(${imagen.id}, '${nombreArchivo}')"
                                    title="Eliminar archivo">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `);
                    } else {
                        // ❌ Archivo no encontrado
                        previewContainer.append(`
                    <div class="col-3 mb-2 archivo-item" data-archivo-id="${imagen.id}">
                        <div class="bg-warning text-dark p-2 rounded text-center position-relative" style="height: 60px; font-size: 10px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                            <i class="fa fa-exclamation-triangle mb-1"></i>
                            <span>No encontrado</span>
                            <small>${nombreArchivo.substring(0, 8)}...</small>
                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                    style="font-size: 8px; padding: 2px 4px;"
                                    onclick="eliminarArchivoIndividual(${imagen.id}, '${nombreArchivo}')"
                                    title="Eliminar archivo">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `);
                    }
                });

                // console.log(`✅ ${imagenes.length} archivos renderizados`);
            }

            function verImagenCompleta(imagenSrc) {
                $('#imagen_modal').attr('src', imagenSrc);
                $('#modal_imagen_completa').modal('show');
            }

            // Detectar si está corriendo en Electron
            function isElectron() {
                return navigator.userAgent.toLowerCase().indexOf('electron') > -1 ||
                    (typeof process !== 'undefined' && process.versions && process.versions.electron);
            }

            // Fallback para obtener ubicación usando IP geolocation APIs
            async function obtenerUbicacionPorIPFallback() {
                const servicios = [{
                        url: 'https://ipapi.co/json/',
                        parser: (data) => ({
                            latitude: data.latitude,
                            longitude: data.longitude,
                            city: data.city,
                            accuracy: 2400 // Aproximado por IP
                        })
                    },
                    {
                        url: 'https://ipwho.is/',
                        parser: (data) => ({
                            latitude: data.latitude,
                            longitude: data.longitude,
                            city: data.city,
                            accuracy: 2400
                        })
                    },
                    {
                        url: 'https://freeipapi.com/api/json/',
                        parser: (data) => ({
                            latitude: data.latitude,
                            longitude: data.longitude,
                            city: data.cityName,
                            accuracy: 2400
                        })
                    }
                ];

                for (const servicio of servicios) {
                    try {
                        const response = await fetch(servicio.url, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            const ubicacion = servicio.parser(data);

                            if (ubicacion.latitude && ubicacion.longitude) {
                                // Simular objeto position
                                const position = {
                                    coords: {
                                        latitude: ubicacion.latitude,
                                        longitude: ubicacion.longitude,
                                        accuracy: ubicacion.accuracy,
                                        altitude: null,
                                        altitudeAccuracy: null
                                    }
                                };

                                return position;
                            }
                        }
                    } catch (error) {
                        console.log('Error con servicio de IP:', error);
                        continue; // Intentar siguiente servicio
                    }
                }

                throw new Error('No se pudo obtener ubicación por IP');
            }

            function obtenerUbicacionActual() {
                const enElectron = isElectron();

                if (navigator.geolocation) {
                    if (!enElectron && typeof iziToast !== 'undefined') {
                        iziToast.info({
                            title: 'Iniciando GPS...',
                            message: 'Active el GPS para mejor precisión. Puede tardar 10-30 segundos',
                            position: 'topRight',
                            timeout: 5000
                        });
                    }

                    // Resetear mejor precisión
                    mejorPrecision = null;

                    // Función para procesar la ubicación exitosa
                    function procesarUbicacion(position, esFinal = true) {
                        const accuracy = position.coords.accuracy;
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;

                        // Si no es la ubicación final, verificar si es mejor que la anterior
                        if (!esFinal && mejorPrecision && accuracy >= mejorPrecision) {
                            return; // No actualizar si la nueva es peor
                        }

                        mejorPrecision = accuracy;

                        // Determinar el tipo de ubicación obtenida
                        let tipoUbicacion = '';
                        let advertencia = false;
                        let icono = '✅';

                        if (accuracy <= 50) {
                            tipoUbicacion = 'GPS (Alta precisión)';
                            icono = '🎯';
                        } else if (accuracy <= 200) {
                            tipoUbicacion = 'GPS/WiFi (Precisión media)';
                            icono = '📍';
                        } else if (accuracy <= 1000) {
                            tipoUbicacion = 'WiFi/Red móvil (Precisión baja)';
                            advertencia = true;
                            icono = '⚠️';
                        } else {
                            tipoUbicacion = 'IP/Red (Muy baja precisión)';
                            advertencia = true;
                            icono = '📡';
                        }

                        $('#latitud').val(latitude.toFixed(6));
                        $('#longitud').val(longitude.toFixed(6));
                        $('#altitud').val(position.coords.altitude ? position.coords.altitude.toFixed(2) : '');
                        $('#precision_gps').val(accuracy ? accuracy.toFixed(2) : '');

                        // Actualizar mapa si está disponible
                        if (mapaInfoCliente) {
                            mapaInfoCliente.setView([latitude, longitude], 15);

                            if (marcadorTemporalInfoCliente) {
                                mapaInfoCliente.removeLayer(marcadorTemporalInfoCliente);
                            }

                            marcadorTemporalInfoCliente = L.marker([latitude, longitude])
                                .addTo(mapaInfoCliente)
                                .bindPopup(`Ubicación actual<br><small>${tipoUbicacion}<br>Precisión: ${accuracy ? accuracy.toFixed(0) + 'm' : 'N/A'}</small>`)
                                .openPopup();

                            // Agregar círculo de precisión
                            if (window.circuloPrecision) {
                                mapaInfoCliente.removeLayer(window.circuloPrecision);
                            }
                            window.circuloPrecision = L.circle([latitude, longitude], {
                                color: advertencia ? '#ff6b6b' : '#51cf66',
                                fillColor: advertencia ? '#ff6b6b' : '#51cf66',
                                fillOpacity: 0.15,
                                radius: accuracy || 100
                            }).addTo(mapaInfoCliente);
                        }

                        // Obtener dirección
                        obtenerDireccionReversa(latitude, longitude);

                        if (typeof iziToast !== 'undefined' && esFinal) {
                            // Detener watchPosition si está activo
                            if (watchId !== null) {
                                navigator.geolocation.clearWatch(watchId);
                                watchId = null;
                            }

                            if (advertencia) {
                                iziToast.warning({
                                    title: icono + ' Ubicación aproximada',
                                    message: `Precisión: ${accuracy.toFixed(0)}m - ${tipoUbicacion}<br><small>💡 Active GPS y WiFi para mejor precisión</small>`,
                                    position: 'topRight',
                                    timeout: 6000,
                                    buttons: [
                                        ['<button><i class="fa fa-sync"></i> GPS Continuo</button>', function(instance, toast) {
                                            instance.hide({
                                                transitionOut: 'fadeOut'
                                            }, toast, 'button');
                                            usarGPSContinuo();
                                        }, true],
                                        ['<button>Reintentar</button>', function(instance, toast) {
                                            instance.hide({
                                                transitionOut: 'fadeOut'
                                            }, toast, 'button');
                                            obtenerUbicacionActual();
                                        }, true]
                                    ]
                                });
                            } else {
                                iziToast.success({
                                    title: icono + ' Ubicación GPS obtenida',
                                    message: `Precisión: ${accuracy.toFixed(0)}m - ${tipoUbicacion}`,
                                    position: 'topRight',
                                    timeout: 3000
                                });
                            }
                        }
                    }

                    // Función para manejar errores con fallback
                    async function manejarError(error, intentoFallback = true) {
                        let errorMsg = 'Error desconocido';
                        let detalleMsg = '';
                        const enElectron = isElectron();

                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMsg = 'Permiso denegado para acceder a la ubicación';
                                detalleMsg = 'Por favor, permita el acceso a su ubicación en la configuración del navegador';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMsg = 'Información de ubicación no disponible';
                                detalleMsg = enElectron ?
                                    'Intentando método alternativo...' :
                                    'Verifique que el GPS esté activo o que tenga conexión a internet';

                                // Si está en Electron y falla, usar fallback de IP silenciosamente
                                if (enElectron && intentoFallback) {
                                    try {
                                        const position = await obtenerUbicacionPorIPFallback();
                                        procesarUbicacion(position, true);
                                        return; // Salir si tuvo éxito
                                    } catch (ipError) {
                                        console.error('Error obteniendo ubicación por IP:', ipError);
                                        // Continuar para mostrar error al usuario
                                    }
                                }
                                break;
                            case error.TIMEOUT:
                                errorMsg = 'Tiempo de espera agotado';
                                detalleMsg = 'El dispositivo está tardando en obtener la ubicación';

                                // Intentar con modo de baja precisión si es timeout y es el primer intento
                                if (intentoFallback && !enElectron) {
                                    // Reintentar con menor precisión
                                    navigator.geolocation.getCurrentPosition(
                                        procesarUbicacion,
                                        function(err) {
                                            manejarError(err, false);
                                        }, {
                                            enableHighAccuracy: false,
                                            timeout: 20000,
                                            maximumAge: 120000
                                        }
                                    );
                                    return; // Salir para no mostrar error aún
                                } else if (enElectron && intentoFallback) {
                                    // En Electron si hay timeout, intentar por IP silenciosamente
                                    try {
                                        const position = await obtenerUbicacionPorIPFallback();
                                        procesarUbicacion(position, true);
                                        return;
                                    } catch (ipError) {
                                        console.error('Error obteniendo ubicación por IP:', ipError);
                                    }
                                }
                                break;
                        }

                        // Mostrar error solo si todos los métodos fallaron
                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({
                                title: 'Error de ubicación',
                                message: errorMsg,
                                position: 'topRight',
                                timeout: 6000,
                                buttons: [
                                    ['<button>Seleccionar en mapa</button>', function(instance, toast) {
                                        instance.hide({
                                            transitionOut: 'fadeOut'
                                        }, toast, 'button');
                                        seleccionarEnMapa();
                                    }, true]
                                ]
                            });
                        }
                    }

                    // Primer intento - En Electron usar timeout más corto y luego fallback
                    const configuracion = enElectron ? {
                        enableHighAccuracy: false, // En Electron usar baja precisión para evitar APIs de Google
                        timeout: 8000, // Timeout corto para fallar rápido y usar fallback
                        maximumAge: 60000
                    } : {
                        enableHighAccuracy: true, // Forzar GPS en navegador normal
                        timeout: 60000, // 60 segundos para dar tiempo al GPS a inicializar
                        maximumAge: 0 // NO usar caché, siempre ubicación fresca del GPS
                    };

                    // Usar getCurrentPosition para obtener primera ubicación
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            procesarUbicacion(position, true);
                        },
                        manejarError,
                        configuracion
                    );
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

            function usarGPSContinuo() {
                if (!navigator.geolocation) {
                    mostrarError('Geolocalización no soportada');
                    return;
                }

                iziToast?.info({
                    title: '🛰️ GPS Continuo Activado',
                    message: 'Mejorando precisión... Espere 20-40 segundos',
                    position: 'topRight',
                    timeout: 5000
                });

                let intentos = 0;
                const maxIntentos = 15; // 15 actualizaciones

                watchId = navigator.geolocation.watchPosition(
                    function(position) {
                        intentos++;
                        procesarUbicacion(position, intentos >= maxIntentos);

                        if (intentos >= maxIntentos || position.coords.accuracy <= 50) {
                            // Detener si llegó a max intentos o precisión excelente
                            navigator.geolocation.clearWatch(watchId);
                            watchId = null;

                            if (position.coords.accuracy <= 50) {
                                iziToast?.success({
                                    title: '🎯 Precisión óptima alcanzada',
                                    message: `${position.coords.accuracy.toFixed(0)}m`,
                                    position: 'topRight'
                                });
                            }
                        }
                    },
                    function(error) {
                        if (watchId !== null) {
                            navigator.geolocation.clearWatch(watchId);
                            watchId = null;
                        }
                        mostrarError('Error en GPS continuo: ' + error.message);
                    }, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            }

            function seleccionarEnMapa() {
                // Detener GPS continuo si está activo
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }

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

            function limpiarUbicacion() {
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

            function limpiarFormulario() {
                $('#entidad_tipo').val('cliente');
                $('#descripcion').val('');
                $('#archivos_adjuntos').val('');
                $('#preview_archivos').empty();
                $('#preview_archivos_existentes').empty();
                $('#contador_archivos').hide();

                limpiarUbicacion();

                $('#descripcion, #latitud, #longitud, #altitud, #precision_gps, #direccion_texto')
                    .prop('readonly', false)
                    .removeClass('bg-light');

                if (typeof iziToast !== 'undefined') {
                    iziToast.success({
                        title: 'Formulario limpiado',
                        message: 'Se han limpiado todos los campos',
                        position: 'topRight'
                    });
                }
            }

            function centrarEnMapa(lat, lng) {
                if (mapaInfoCliente) {
                    mapaInfoCliente.setView([lat, lng], 15);

                    if (typeof iziToast !== 'undefined') {
                        iziToast.info({
                            title: 'Ubicación centrada',
                            message: 'Mapa centrado en la ubicación seleccionada',
                            position: 'topRight'
                        });
                    }
                }
            }

            function guardarInfoAdicionalPlus() {
                if (!$('#codCli').val() || $('#codCli').val() === '0') {
                    iziToast?.error({
                        title: 'Error de validación',
                        message: 'Debe seleccionar un cliente',
                        position: 'topRight'
                    });
                    return;
                }

                if (!$('#descripcion').val().trim()) {
                    iziToast?.error({
                        title: 'Error de validación',
                        message: 'La descripción es obligatoria',
                        position: 'topRight'
                    });
                    return;
                }

                const latitud = $('#latitud').val();
                const longitud = $('#longitud').val();

                if (latitud && !longitud || !latitud && longitud) {
                    iziToast?.warning({
                        title: 'Validación de coordenadas',
                        message: 'Si ingresa latitud, también debe ingresar longitud y viceversa',
                        position: 'topRight'
                    });
                    return;
                }

                const inputs = [
                    'entidad_tipo',
                    'codCli',
                    'descripcion',
                    'latitud',
                    'longitud',
                    'altitud',
                    'precision_gps',
                    'direccion_texto'
                ];

                const selects = [];
                const radios = [];
                const id = $('#codCli').val();
                const archivo = [id];

                try {
                    obtiene_plus3(
                        inputs,
                        selects,
                        radios,
                        'guardar_info_adicional_cliente',
                        id,
                        archivo,
                        'archivos_adjuntos',
                        function(response) {
                            //console.log('Respuesta del servidor:', response);

                            if (Array.isArray(response)) {
                                const [mensaje, status] = response;

                                if (status === '1') {
                                    iziToast?.success({
                                        title: 'Información guardada',
                                        message: mensaje,
                                        position: 'topRight'
                                    });

                                    limpiarFormulario();

                                    // Recargar página para ver cambios
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1500);
                                } else {
                                    iziToast?.error({
                                        title: 'Error',
                                        message: mensaje,
                                        position: 'topRight'
                                    });
                                }
                            }
                        }
                    );
                } catch (error) {
                    console.error('Error al llamar obtiene_plus3:', error);
                    iziToast?.error({
                        title: 'Error del sistema',
                        message: 'Ocurrió un error al procesar la solicitud',
                        position: 'topRight'
                    });
                }
            }

            function editarInfo(id) {
                //console.log('🔧 Editando información ID:', id);

                iziToast?.info({
                    title: '⏳ Cargando...',
                    message: 'Cargando información para editar',
                    position: 'topRight',
                    timeout: 2000
                });

                $.ajax({
                    url: '../../../src/cruds/accionesplus.php',
                    type: 'POST',
                    data: {
                        accion: 'cargar_info_adicional',
                        id: id,
                        inputs: JSON.stringify({})
                    },
                    dataType: 'json',
                    success: function(response) {

                        if (Array.isArray(response) && response[1] === '1' && response[2]) {
                            const data = response[2];
                            llenarCamposFormulario(data.info, data.archivos || [], id);
                        } else {
                            iziToast?.error({
                                title: '❌ Error',
                                message: response[0] || 'No se pudieron cargar los datos',
                                position: 'topRight'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error AJAX:', error);
                        iziToast?.error({
                            title: '❌ Error de conexión',
                            message: 'No se pudo conectar con el servidor',
                            position: 'topRight'
                        });
                    }
                });
            }

            function verDetalle(id) {

                iziToast?.info({
                    title: '⏳ Cargando...',
                    message: 'Cargando información detallada',
                    position: 'topRight',
                    timeout: 2000
                });

                $.ajax({
                    url: '../../../src/cruds/accionesplus.php',
                    type: 'POST',
                    data: {
                        accion: 'cargar_info_adicional',
                        id: id,
                        inputs: JSON.stringify({})
                    },
                    dataType: 'json',
                    success: function(response) {

                        if (Array.isArray(response) && response[1] === '1' && response[2]) {
                            const data = response[2];
                            llenarCamposFormulario(data.info, data.archivos || [], id, true);
                            mostrarModalDetalleSimple(data, id);
                        } else {
                            iziToast?.error({
                                title: '❌ Error',
                                message: response[0] || 'No se pudieron cargar los datos',
                                position: 'topRight'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error AJAX:', error);
                        iziToast?.error({
                            title: '❌ Error de conexión',
                            message: 'No se pudo conectar con el servidor',
                            position: 'topRight'
                        });
                    }
                });
            }

            function llenarCamposFormulario(info, archivos, id, soloLectura = false) {

                if (!info) {
                    console.error('❌ No hay información para llenar');
                    iziToast?.error({
                        title: '❌ Error',
                        message: 'No se recibieron datos válidos',
                        position: 'topRight'
                    });
                    return;
                }

                $('#entidad_tipo').val(info.entidad_tipo || 'cliente');
                $('#codCli').val(info.entidad_id || '');
                $('#descripcion').val(info.descripcion || '');
                $('#latitud').val(info.latitud || '');
                $('#longitud').val(info.longitud || '');
                $('#altitud').val(info.altitud || '');
                $('#precision_gps').val(info.precision || '');
                $('#direccion_texto').val(info.direccion_texto || '');

                if (soloLectura) {
                    $('#descripcion, #latitud, #longitud, #altitud, #precision_gps, #direccion_texto')
                        .prop('readonly', true)
                        .addClass('bg-light');
                } else {
                    $('#descripcion, #latitud, #longitud, #altitud, #precision_gps, #direccion_texto')
                        .prop('readonly', false)
                        .removeClass('bg-light');
                }

                if (archivos && archivos.length > 0) {
                    mostrarImagenesExistentes(archivos);
                } else {
                    $('#preview_archivos_existentes').html('<p class="text-muted">No hay archivos adjuntos</p>');
                }

                actualizarMapaConCoordenadas(info.latitud, info.longitud);

                if (!soloLectura) {
                    cambiarAModoEdicion(id);
                }

                $('html, body').animate({
                    scrollTop: $('#descripcion').offset().top - 100
                }, 500);

                iziToast?.success({
                    title: '✅ Datos cargados',
                    message: `Información ${soloLectura ? 'mostrada' : 'lista para editar'}`,
                    position: 'topRight'
                });
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

            function cambiarAModoEdicion(id) {

                const botonGuardar = $('button[onclick*="guardarInfoAdicionalPlus"]');
                if (botonGuardar.length) {
                    botonGuardar.html('<i class="fa-solid fa-sync me-2"></i>Actualizar Información');
                    botonGuardar.removeClass('btn-outline-success').addClass('btn-warning');
                    botonGuardar.attr('onclick', `actualizarInfoSimple(${id})`);
                }

                if (!$('#btn_cancelar_edicion').length) {
                    botonGuardar.after(`
                <button type="button" id="btn_cancelar_edicion" class="btn btn-outline-secondary ms-2" onclick="cancelarEdicionSimple()">
                    <i class="fa-solid fa-times me-2"></i>Cancelar
                </button>
            `);
                }

                $('.card-body').addClass('border-warning shadow-sm');
            }

            function actualizarInfoSimple(id) {

                if (!$('#codCli').val() || $('#codCli').val() === '0') {
                    mostrarError('Debe seleccionar un cliente');
                    return;
                }

                if (!$('#descripcion').val().trim()) {
                    mostrarError('La descripción es obligatoria');
                    $('#descripcion').focus();
                    return;
                }

                const latitud = $('#latitud').val();
                const longitud = $('#longitud').val();

                if ((latitud && !longitud) || (!latitud && longitud)) {
                    mostrarError('Si ingresa latitud, también debe ingresar longitud y viceversa');
                    return;
                }

                const formData = new FormData();
                formData.append('accion', 'actualizar_info_adicional');
                formData.append('id', id);

                const datosInputs = {
                    entidad_tipo: $('#entidad_tipo').val() || 'cliente',
                    codCli: $('#codCli').val(),
                    descripcion: $('#descripcion').val().trim(),
                    latitud: $('#latitud').val(),
                    longitud: $('#longitud').val(),
                    altitud: $('#altitud').val(),
                    precision_gps: $('#precision_gps').val(),
                    direccion_texto: $('#direccion_texto').val().trim()
                };

                formData.append('inputs', JSON.stringify(datosInputs));

                const archivos = $('#archivos_adjuntos')[0].files;
                if (archivos.length > 0) {
                    for (let i = 0; i < archivos.length; i++) {
                        formData.append('archivos_adjuntos[]', archivos[i]);
                    }
                }

                iziToast?.info({
                    title: '⏳ Actualizando...',
                    message: 'Guardando cambios',
                    position: 'topRight',
                    timeout: 3000
                });

                $.ajax({
                    url: '../../../src/cruds/accionesplus.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {

                        if (Array.isArray(response) && response[1] === '1') {
                            iziToast?.success({
                                title: '✅ Actualizado',
                                message: response[0],
                                position: 'topRight'
                            });

                            cancelarEdicionSimple();

                            setTimeout(() => {
                                location.reload();
                            }, 1500);

                        } else {
                            mostrarError(response[0] || 'Error al actualizar');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error al actualizar:', error);
                        mostrarError('Error de conexión al actualizar');
                    }
                });
            }

            function cancelarEdicionSimple() {

                const botonGuardar = $('button[onclick*="actualizarInfoSimple"]');
                if (botonGuardar.length) {
                    botonGuardar.html('<i class="fa-solid fa-floppy-disk me-2"></i>Guardar información adicional');
                    botonGuardar.removeClass('btn-warning').addClass('btn-outline-success');
                    botonGuardar.attr('onclick', 'guardarInfoAdicionalPlus()');
                }

                $('#btn_cancelar_edicion').remove();
                $('.card-body').removeClass('border-warning shadow-sm');
                limpiarFormulario();

                iziToast?.info({
                    title: '❌ Edición cancelada',
                    message: 'Se ha cancelado la edición',
                    position: 'topRight'
                });
            }

            function mostrarModalDetalleSimple(data, id) {
                const info = data.info;
                const archivos = data.archivos || [];

                let contenidoArchivos = '';
                if (archivos.length > 0) {
                    contenidoArchivos = '<div class="row mt-3">';
                    archivos.forEach(archivo => {
                        const nombreArchivo = archivo.filename || archivo.path_file.split('/').pop();

                        if (archivo.is_image && archivo.data_uri && archivo.exists) {
                            // ✅ Usar data_uri procesado por PHP
                            contenidoArchivos += `
                        <div class="col-3 mb-2">
                            <img src="${archivo.data_uri}" 
                                 alt="${nombreArchivo}" 
                                 class="img-thumbnail" 
                                 style="width: 100%; height: 80px; object-fit: cover; cursor: pointer;" 
                                 onclick="verImagenCompleta('${archivo.data_uri}')">
                        </div>
                    `;
                        } else if (archivo.exists) {
                            // Archivo no imagen pero existe
                            let iconClass = 'fa-file';
                            if (nombreArchivo.includes('.pdf')) iconClass = 'fa-file-pdf';
                            else if (nombreArchivo.includes('.doc')) iconClass = 'fa-file-word';
                            else if (nombreArchivo.includes('.xls')) iconClass = 'fa-file-excel';

                            contenidoArchivos += `
                        <div class="col-3 mb-2">
                            <div class="bg-secondary text-white p-2 rounded text-center" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                                <div>
                                    <i class="fa ${iconClass} mb-1"></i><br>
                                    <small>${nombreArchivo.substring(0, 10)}...</small>
                                </div>
                            </div>
                        </div>
                    `;
                        } else {
                            // Archivo no encontrado
                            contenidoArchivos += `
                        <div class="col-3 mb-2">
                            <div class="bg-warning text-dark p-2 rounded text-center" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                                <div>
                                    <i class="fa fa-exclamation-triangle mb-1"></i><br>
                                    <small>No encontrado</small><br>
                                    <small>${nombreArchivo.substring(0, 8)}...</small>
                                </div>
                            </div>
                        </div>
                    `;
                        }
                    });
                    contenidoArchivos += '</div>';
                }

                const modalContent = `
            <div class="modal fade" id="modal_detalle_simple" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title">
                                <i class="fa fa-info-circle me-2"></i>
                                Detalle Completo - ID: ${id}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>📋 Tipo:</strong><br>
                                    <span class="badge bg-primary">${info.entidad_tipo || 'N/A'}</span>
                                </div>
                                <div class="col-md-6">
                                    <strong>👤 Cliente:</strong><br>
                                    <span class="badge bg-info">${info.entidad_id || 'N/A'}</span>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-12">
                                    <strong>📝 Descripción:</strong><br>
                                    <div class="border p-3 rounded bg-light">
                                        ${info.descripcion || 'Sin descripción'}
                                    </div>
                                </div>
                            </div>

                            ${info.latitud && info.longitud ? `
                            <div class="row mt-3">
                                <div class="col-md-3">
                                    <strong>🌐 Latitud:</strong><br>
                                    <code>${info.latitud}</code>
                                </div>
                                <div class="col-md-3">
                                    <strong>🌐 Longitud:</strong><br>
                                    <code>${info.longitud}</code>
                                </div>
                                <div class="col-md-3">
                                    <strong>⛰️ Altitud:</strong><br>
                                    <code>${info.altitud || 'N/A'}</code>
                                </div>
                                <div class="col-md-3">
                                    <strong>📏 Precisión:</strong><br>
                                    <code>${info.precision || 'N/A'}</code>
                                </div>
                            </div>
                            ` : ''}

                            ${info.direccion_texto ? `
                            <div class="row mt-3">
                                <div class="col-12">
                                    <strong>📍 Dirección:</strong><br>
                                    <div class="bg-light p-2 rounded">
                                        <small class="text-muted">${info.direccion_texto}</small>
                                    </div>
                                </div>
                            </div>
                            ` : ''}

                            ${archivos.length > 0 ? `
                            <div class="row mt-3">
                                <div class="col-12">
                                    <strong>📎 Archivos (${archivos.length}):</strong>
                                    ${contenidoArchivos}
                                </div>
                            </div>
                            ` : '<div class="alert alert-info mt-3">📄 No hay archivos adjuntos</div>'}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-warning" onclick="editarInfo(${id}); $('#modal_detalle_simple').modal('hide');">
                                <i class="fa fa-edit me-2"></i>Editar
                            </button>
                            <button type="button" class="btn btn-danger" onclick="eliminarInfo(${id}); $('#modal_detalle_simple').modal('hide');">
                                <i class="fa fa-trash me-2"></i>Eliminar
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fa fa-times me-2"></i>Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

                $('#modal_detalle_simple').remove();
                $('body').append(modalContent);
                $('#modal_detalle_simple').modal('show');
            }

            function eliminarInfo(id) {
                if (!confirm('🗑️ ¿Eliminar esta información?\n\n⚠️ Se eliminarán:\n• La información adicional\n• Todos los archivos\n\n❌ Esta acción es irreversible')) {
                    return;
                }

                iziToast?.warning({
                    title: '⏳ Eliminando...',
                    message: 'Procesando eliminación',
                    position: 'topRight',
                    timeout: 3000
                });

                $.ajax({
                    url: '../../../src/cruds/accionesplus.php',
                    type: 'POST',
                    data: {
                        accion: 'eliminar_info_adicional',
                        id: id,
                        inputs: JSON.stringify({})
                    },
                    dataType: 'json',
                    success: function(response) {

                        if (Array.isArray(response) && response[1] === '1') {
                            iziToast?.success({
                                title: '✅ Eliminado',
                                message: response[0],
                                position: 'topRight'
                            });

                            if ($('#btn_cancelar_edicion').length) {
                                cancelarEdicionSimple();
                            }

                            setTimeout(() => {
                                location.reload();
                            }, 1500);

                        } else {
                            mostrarError(response[0] || 'Error al eliminar');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error al eliminar:', error);
                        mostrarError('Error de conexión al eliminar');
                    }
                });
            }

            function eliminarArchivoIndividual(idArchivo, nombreArchivo) {
                if (!confirm(`🗑️ ¿Eliminar "${nombreArchivo}"?\n\n❌ Esta acción es irreversible`)) {
                    return;
                }

                $.ajax({
                    url: '../../../src/cruds/accionesplus.php',
                    type: 'POST',
                    data: {
                        accion: 'eliminar_archivo_adicional',
                        id: idArchivo,
                        inputs: JSON.stringify({})
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (Array.isArray(response) && response[1] === '1') {
                            $(`.archivo-item[data-archivo-id="${idArchivo}"]`).fadeOut(300, function() {
                                $(this).remove();
                            });

                            iziToast?.success({
                                title: '✅ Archivo eliminado',
                                message: response[0],
                                position: 'topRight'
                            });
                        } else {
                            mostrarError(response[0] || 'Error al eliminar archivo');
                        }
                    },
                    error: function(xhr, status, error) {
                        mostrarError('Error de conexión al eliminar archivo');
                    }
                });
            }

            function mostrarError(mensaje) {
                iziToast?.error({
                    title: '❌ Error',
                    message: mensaje,
                    position: 'topRight',
                    timeout: 5000
                });
            }
        </script>

<?php
}

?>