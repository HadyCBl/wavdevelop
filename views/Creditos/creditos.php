<?php

use Micro\Generic\PermissionManager;

include __DIR__ . '/../../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    //session_unset();
    return;
}
/* SE AGREGA DE MANERA TEMPORAL */
include __DIR__ . '/../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
/* QUITAR CUANDO YA NO SE NECESITE */

$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

require __DIR__ . '/../infoEnti/infoEnti.php';
$showmensaje = false;
try {
    $database->openConnection();
    $infoEnti = infoEntidad($idagencia, $database, $db_name_general);
    $estado = $infoEnti['estado'];
    $fecha_pago = $infoEnti['fecha_pago'];
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

// Verificar la cookie de la última notificación
$show_notification = false;
if (!isset($_COOKIE['last_notificationtwo'])) {
    $show_notification = true;
    setcookie('last_notificationtwo', time(), time() + 86400, "/"); // 86400 segundos = 1 día
} else {
    $last_notificationtwo = $_COOKIE['last_notificationtwo'];
    if (time() - $last_notificationtwo > 86400) {
        $show_notification = true;
        setcookie('last_notificationtwo', time(), time() + 86400, "/");
    }
}

try {
    $userPermissions = new PermissionManager($idusuario);

    $showDashboardCreditos = $userPermissions->isLevelOne(PermissionManager::VER_DASHBOARD_CREDITOS);


    if (!$showDashboardCreditos) {
        throw new Exception("No tiene permiso para ver el dashboard de créditos.");
    }
    $database->openConnection();

    // Función para ejecutar consultas
    function executeQuery($database, $sql)
    {
        $result = $database->executeQuery($sql);
        if (!$result) {
            throw new Exception("Error al ejecutar la consulta: $sql");
        }
        return $result;
    }

    // Consultas SQL necesarias
    $queries = [
        'creditos_recientes' => "
            SELECT 
                CONCAT(cli.primer_name, ' ', cli.primer_last) AS nombre_cliente,
                CASE 
                    WHEN cremi.Cestado IN ('A', 'D') THEN cremi.Montosol
                    WHEN cremi.Cestado IN ('E', 'F') THEN cremi.MonSug
                END AS monto,
                cremi.Cestado AS estado
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
            WHERE cremi.Cestado IN ('A', 'D', 'E', 'F')
            ORDER BY cremi.DFecDsbls DESC
            LIMIT 4;
        ",
        'grafica' => "
            SELECT DATE_FORMAT(cremi.DFecDsbls, '%Y-%m') AS mes,
                   SUM(GREATEST(0, cremi.NCapDes)) AS monto_desembolsado
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
            WHERE (cremi.Cestado = 'F' OR cremi.Cestado = 'G')
            AND cremi.DFecDsbls >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY mes
            ORDER BY mes ASC;
        ",
        'grafica_recuperaciones' => "
            SELECT DATE_FORMAT(kar.dfecpro, '%Y-%m') AS mes,
                   SUM(kar.KP) AS monto_recuperado
            FROM CREDKAR kar
            WHERE kar.ctippag = 'P'
            AND kar.cestado != 'X'
            AND kar.dfecpro >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY mes
            ORDER BY mes ASC;
        "
    ];

    // Ejecutar consultas
    $results = [];
    foreach ($queries as $key => $sql) {
        $results[$key] = executeQuery($database, $sql);
    }

    // Manejar resultados
    $data_creditos_recientes = $results['creditos_recientes']->fetchAll(PDO::FETCH_ASSOC);
    $data_grafica = $results['grafica']->fetchAll(PDO::FETCH_ASSOC);
    $data_grafica_recuperaciones = $results['grafica_recuperaciones']->fetchAll(PDO::FETCH_ASSOC);

    // Construir arrays para la gráfica de desembolsos
    $labels = [];
    $valDesembolsado = [];
    foreach ($data_grafica as $row) {
        $labels[] = $row['mes'];
        $valDesembolsado[] = $row['monto_desembolsado'];
    }

    // Construir arrays para la gráfica de recuperaciones
    $labels_recuperaciones = [];
    $valRecuperado = [];
    foreach ($data_grafica_recuperaciones as $row) {
        $labels_recuperaciones[] = $row['mes'];
        $valRecuperado[] = $row['monto_recuperado'];
    }

    // Convertir resultados a JSON (opcional)
    $labels_json = json_encode($labels);
    $valDesembolsado_json = json_encode($valDesembolsado);
    $labels_recuperaciones_json = json_encode($labels_recuperaciones);
    $valRecuperado_json = json_encode($valRecuperado);
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modulo Creditos</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../includes/img/favmicro.ico">
    <link rel="stylesheet" href="../../includes/css/style.css">
    <link rel="stylesheet" href="../../includes/css/styleCard.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <?php require_once __DIR__ . '/../../includes/incl.php'; ?>

</head>

<body class="">
    <?php
    require __DIR__ . '/../../src/menu/cre_menu.php';
    ?>
    <section class="home">
        <div class="container" style="max-width: none !important;">
            <div class="row">
                <div class="col d-flex justify-content-start">
                    <div class="text">MODULO CREDITOS</div>
                </div>
                <div class="col d-flex justify-content-end">
                    <div class="text"><?= $infoEnti['nomAge'] ?></div>
                </div>
            </div>
            <?php
            if ($show_notification) {
                $hoy = strtotime(date("Y-m-d"));
                // Calcula las fechas
                $fecha_pago_menos = date('Y-m-d', strtotime($fecha_pago . ' - 5 days'));
                $fecha_pago_mas = date('Y-m-d', strtotime($fecha_pago . ' + 5 days'));
                $fecha_pago_un_dia_antes = date('Y-m-d', strtotime($fecha_pago . ' - 1 day'));
                $fecha_pago_3 = date('Y-m-d', strtotime($fecha_pago . ' + 3 days'));
                $ultimo_dia_pago = date('Y-m-d', strtotime($fecha_pago_3 . ' + 1 day'));
                $fecha_pago_mas5 = date('Y-m-d', strtotime($fecha_pago . ' + 4 days'));
                // Verifica si hoy está dentro del rango  
                $fechanotify_mas5 = strtotime($fecha_pago_mas5);
                $fechanotify_menos = strtotime($fecha_pago_menos);
                $fechanotify_un_dia_antes = strtotime($fecha_pago_un_dia_antes);
                $fecha_pago_3_days = strtotime($fecha_pago_3);
                $case = '';
                // Determina el caso basado en las fechas
                // }
                if ($fechanotify_menos <= $hoy && $hoy <= strtotime($fecha_pago)) {
                    $case = 'dentro_rango_menos';
                }
                if ($hoy >= strtotime($fecha_pago . ' + 3 days') && $hoy <= strtotime($fecha_pago . ' + 4 days')) {
                    $case = 'dentro_rango_mas';
                }
                switch ($case) {
                    case 'dentro_rango_menos':
            ?>
                        <center>
                            <div class="container">
                                <div class="cardalert">
                                    <div class="header">
                                        <div class="image_advert">
                                            <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke-linejoin="round" stroke-linecap="round"></path>
                                            </svg>
                                        </div>
                                        <div class="content">
                                            <span class="title">AVISO</span>
                                            <p class="message">Este es un recordatorio cordial de que tu pago está programado para
                                                efectuarse el día
                                                <?php echo date('d F Y', strtotime($fecha_pago)); ?></p>
                                        </div>
                                        <div class="actions">
                                            <button class="desactivate btn btn-success" type="button" data-toggle="modal" data-target="#exampleModalCenter">
                                                <i class="fas fa-exclamation-triangle"></i> Ver detalles
                                            </button>
                                            <!-- Modal -->
                                            <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exampleModalLongTitle">Advertencia de pago
                                                            </h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            "Si necesitas asistencia adicional o tienes alguna pregunta, no dudes en
                                                            contactarnos.
                                                            Saludos cordiales,
                                                            [SOTECPRO]"
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </center>
                        <?php
                        break;
                    case 'dentro_rango_mas':
                        if ($hoy == strtotime($fecha_pago_mas5)) {
                        ?>
                            <center>
                                <div class="container">
                                    <div class="cardalert">
                                        <div class="header">
                                            <div class="image">
                                                <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke-linejoin="round" stroke-linecap="round"></path>
                                                </svg>
                                            </div>
                                            <div class="content">
                                                <span class="title"> Ultimo aviso
                                                </span>
                                                <p class="message">Mañana es el último día para renovar tu suscripción. Si no lo haces,
                                                    tu cuenta se bloqueará automáticamente. </p>
                                            </div>
                                            <div class="actions">
                                                <button class="desactivate btn btn-danger" type="button" data-toggle="modal" data-target="#exampleModalCenter">
                                                    <i class="fas fa-exclamation-triangle"></i> Ver detalles
                                                </button>
                                                <!-- Modal -->
                                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">Alerta por falta de
                                                                    pago</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                "Mañana vence tu suscripción. Si no la renuevas, tu cuenta se bloqueará.
                                                                Recuerda que la restauración del sistema tomará aproximadamente 1 hora ,
                                                                (dias habiles).
                                                                Renueva ahora para evitar interrupciones en el servicio.
                                                                Para cualquier pregunta o ayuda, estamos aquí para ti.
                                                                [Sotecpro]"
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </center>
                        <?php
                        } else {
                        ?>
                            <center>
                                <div class="container">
                                    <div class="cardalert">
                                        <div class="header">
                                            <div class="image">
                                                <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke-linejoin="round" stroke-linecap="round"></path>
                                                </svg>
                                            </div>
                                            <div class="content">
                                                <span class="title"> Estimado Usuario
                                                </span>
                                                <p class="message">parece que tienes una Factura pendiente, con fecha de pago del
                                                    <?php echo date('d F Y', strtotime($fecha_pago)); ?> .El sistema se bloqueará el
                                                    <?php echo date('d F Y', strtotime($fecha_pago_mas)); ?>. Favor de enviar la boleta
                                                    de pago</p>
                                            </div>
                                            <div class="actions">
                                                <button class="desactivate btn btn-danger" type="button" data-toggle="modal" data-target="#exampleModalCenter">
                                                    <i class="fas fa-coins"></i>
                                                </button>
                                                <!-- Modal -->
                                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">Alerta por falta de
                                                                    pago</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                "Si considera que esta notificación es incorrecta, le recomendamos que
                                                                se comunique con nuestro equipo de soporte técnico. Alternativamente, le
                                                                instamos a tomar las medidas necesarias, ya que para la fecha
                                                                <?php echo date('d F Y', strtotime($fecha_pago_mas)); ?>, el sistema se
                                                                encontrará bloqueado."
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </center>
            <?php

                        }


                        break;

                    default:
                        // NO PASA NADA 
                        break;
                }
            }
            // }
            ?>
            <div id="cuadro">
                <div class="d-flex flex-column h-100">
                    <div class="flex-grow-1">
                        <div class="row align-items-center" style="max-width: none !important; height: calc(75vh) !important;">
                            <div class="row d-flex justify-content-center">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header text-center"> Bienvenido al módulo de créditos. </div>
                                        <div class="card-body d-flex justify-content-center">
                                            <?php if ($showDashboardCreditos): ?>
                                                <div class="container-fluid py-4">
                                                    <!-- Header Cards -->
                                                    <div class="row g-3 mb-4">
                                                        <div class="col-6 col-md-3">
                                                            <div class="card shadow-sm h-100 border-0 text-center p-3 bg-gradient bg-light">
                                                                <h6 class="mb-1 text-secondary">Créditos Activos</h6>
                                                                <h3 id="creditos-activos" class="fw-bold text-primary">Cargando...</h3>
                                                                <p id="porcentaje-cambio" class="small text-muted mb-0">Cargando...</p>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <div class="card shadow-sm h-100 border-0 text-center p-3 bg-gradient bg-light">
                                                                <h6 class="mb-1 text-secondary">Créditos Grupales</h6>
                                                                <h3 id="creditos-grupales" class="fw-bold text-success">Cargando...</h3>
                                                                <p id="grupos-activos" class="small text-muted mb-0">Cargando...</p>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <div class="card shadow-sm h-100 border-0 text-center p-3 bg-gradient bg-light">
                                                                <h6 class="mb-1 text-secondary">Cartera en Riesgo</h6>
                                                                <h3 id="cartera-en-riesgo" class="fw-bold text-danger">Cargando...</h3>
                                                                <p class="small text-muted mb-0">Créditos con más de 30 días de atraso</p>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <div class="card shadow-sm h-100 border-0 text-center p-3 bg-gradient bg-light">
                                                                <h6 class="mb-1 text-secondary">Próximos Vencimientos</h6>
                                                                <h3 id="proximos-vencimientos" class="fw-bold text-warning">Cargando...</h3>
                                                                <p class="small text-muted mb-0">En los próximos 7 días</p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Tabs -->
                                                    <ul class="nav nav-tabs mb-3 justify-content-center" role="tablist">
                                                        <li class="nav-item" role="presentation">
                                                            <a class="nav-link active" href="#vista-general" data-bs-toggle="tab" role="tab">Vista General</a>
                                                        </li>
                                                    </ul>

                                                    <div class="tab-content">
                                                        <!-- Vista General -->
                                                        <div class="tab-pane fade show active" id="vista-general" role="tabpanel">
                                                            <div class="row g-3">
                                                                <div class="col-12 col-lg-8">
                                                                    <div class="card shadow-sm h-100 border-0 p-3">
                                                                        <h6 class="mb-3 text-secondary">Desembolsos vs Recuperaciones</h6>
                                                                        <div class="ratio ratio-16x9">
                                                                            <canvas id="chart"></canvas>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-lg-4">
                                                                    <div class="card shadow-sm h-100 border-0 p-3">
                                                                        <h6 class="mb-3 text-secondary">Créditos Recientes</h6>
                                                                        <ul class="list-group" id="creditos-recientes">
                                                                            <?php if (!empty($data_creditos_recientes)): ?>
                                                                                <?php foreach ($data_creditos_recientes as $credito): ?>
                                                                                    <?php
                                                                                    $estadoClass = '';
                                                                                    if ($credito['estado'] === 'F') {
                                                                                        $estadoClass = 'bg-success';
                                                                                    } elseif ($credito['estado'] === 'E') {
                                                                                        $estadoClass = 'bg-primary';
                                                                                    } else {
                                                                                        $estadoClass = 'bg-warning text-dark';
                                                                                    }
                                                                                    ?>
                                                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                                        <span class="fw-semibold"><?= htmlspecialchars($credito['nombre_cliente']) ?></span>
                                                                                        <span class="mx-2 text-secondary">Q<?= number_format($credito['monto'], 2) ?></span>
                                                                                        <span class="badge <?= $estadoClass ?>">
                                                                                            <?= $credito['estado'] === 'F' ? 'Desembolsado' : ($credito['estado'] === 'E' ? 'Aprobado' : 'En Proceso') ?>
                                                                                        </span>
                                                                                    </li>
                                                                                <?php endforeach; ?>
                                                                            <?php else: ?>
                                                                                <li class="list-group-item text-muted">No hay créditos recientes</li>
                                                                            <?php endif; ?>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Ganancias Obtenidas -->
                                                    <div class="row mt-4">
                                                        <div class="col-12">
                                                            <div class="card shadow-sm border-0 p-3">
                                                                <h6 class="mb-3 text-secondary">Intereses Proyectados en desembolsos</h6>
                                                                <div class="mb-2">
                                                                    <span class="small text-muted">Mes Actual</span>
                                                                    <div class="progress" style="height: 28px;">
                                                                        <div class="progress-bar bg-success" id="ganancias-mes-actual" role="progressbar" style="width: 0%;">Cargando...</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <span class="small text-muted">Mes Anterior</span>
                                                                    <div class="progress" style="height: 28px;">
                                                                        <div class="progress-bar bg-primary" id="ganancias-mes-anterior" role="progressbar" style="width: 0%;">Cargando...</div>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <span class="small text-muted">Año Actual</span>
                                                                    <div class="progress" style="height: 28px;">
                                                                        <div class="progress-bar bg-warning" id="ganancias-ano-actual" role="progressbar" style="width: 0%;">Cargando...</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex flex-column align-items-center justify-content-center">
                                                    <div class="mb-3 text-center">
                                                        <!-- SVG animado llamativo -->
                                                        <svg width="120" height="120" viewBox="0 0 120 120" style="animation:bounce 2s infinite;" xmlns="http://www.w3.org/2000/svg">
                                                            <defs>
                                                                <radialGradient id="grad" cx="60" cy="60" r="50" gradientUnits="userSpaceOnUse">
                                                                    <stop offset="0%" stop-color="#fffde4"/>
                                                                    <stop offset="100%" stop-color="#f8d90f"/>
                                                                </radialGradient>
                                                            </defs>
                                                            <circle cx="60" cy="60" r="50" fill="url(#grad)" stroke="#f1c40f" stroke-width="6"/>
                                                            <g>
                                                                <rect x="35" y="50" width="50" height="30" rx="8" fill="#fff" stroke="#3498db" stroke-width="3"/>
                                                                <rect x="45" y="60" width="30" height="10" rx="3" fill="#eaf6fb"/>
                                                                <circle cx="55" cy="65" r="2" fill="#3498db"/>
                                                                <circle cx="65" cy="65" r="2" fill="#3498db"/>
                                                            </g>
                                                            <text x="60" y="45" text-anchor="middle" font-size="18" fill="#3498db" font-family="Arial" font-weight="bold">¡Atención!</text>
                                                        </svg>
                                                    </div>
                                                    <h4 class="text-primary mb-2 fw-bold">¡Bienvenido al módulo de créditos!</h4>
                                                    <!-- <div class="mb-2 px-3 py-2 rounded bg-light border shadow-sm" style="max-width: 400px;">
                                                        <p class="mb-1 text-muted">
                                                            No tienes acceso al dashboard de estadísticas.<br>
                                                            Solicita permisos al administrador para visualizar información avanzada.
                                                        </p>
                                                    </div> -->
                                                    <div class="mt-3 w-100 px-3">
                                                        <div class="alert alert-info border-info shadow-sm">
                                                            <h6 class="mb-2 fw-semibold text-secondary">
                                                                <i class="fa fa-info-circle text-primary"></i> Instrucciones rápidas
                                                            </h6>
                                                            <ul class="mb-1 ps-3" style="font-size: 1rem;">
                                                                <li>
                                                                    <span class="fw-semibold text-dark">Menú lateral izquierdo:</span>
                                                                    <ul class="mb-1 ps-3">
                                                                        <li><span class="fw-semibold text-primary">Crédito Individual:</span> Gestiona solicitudes y seguimiento de créditos personales.</li>
                                                                        <li><span class="fw-semibold text-success">Crédito Grupal:</span> Administra créditos para grupos.</li>
                                                                        <li><span class="fw-semibold text-warning">Caja:</span> Realiza operaciones de apertura, cierre y movimientos de caja.</li>
                                                                        <li><span class="fw-semibold text-info">Reportería:</span> Consulta reportes financieros y de gestión.</li>
                                                                    </ul>
                                                                </li>
                                                                <li>Haz clic en el módulo deseado para acceder a sus funciones.</li>
                                                                <li>Si necesitas ayuda, contacta al soporte.</li>
                                                            </ul>
                                                            <div class="mt-2 small text-muted">
                                                                <i class="fa fa-lightbulb text-warning"></i>
                                                                Recomendación: Explora cada módulo para aprovechar todas las funcionalidades del sistema.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <style>
                                                    @keyframes bounce {
                                                        0%, 100% { transform: translateY(0);}
                                                        50% { transform: translateY(-18px);}
                                                    }
                                                </style>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ESPACION DE ALERTA DE APERTURAS Y CIERRES DE CAJA -->
    <?php $verificacion_ape_cr = verificar_apertura_cierre($_SESSION['id'], $conexion);
    // print_r($verificacion_ape_cr);
    // print_r($verificacion_ape_cr); 
    ?>

    <div aria-live="polite" aria-atomic="true" class="position-relative">
        <div class="toast-container top-0 end-0 pe-3" style="padding-top: 4rem !important;">
            <!-- Then put toasts within -->
            <div class="toast <?= ($verificacion_ape_cr[0] < 6) ? 'fade show' : ''; ?>" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <span class="btn btn-<?= ($verificacion_ape_cr[0] > 0) ? 'warning' : 'danger'; ?> btn-sm me-2"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <strong class="me-auto">
                        <?= ($verificacion_ape_cr[0] > 0) ? '¡Advertencia!' : '¡Error!'; ?>
                    </strong>
                    <small class="text-muted">
                        En este momento
                    </small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerca"></button>
                </div>
                <div class="toast-body">
                    <span class="text-primary"><?= $verificacion_ape_cr[1]; ?></span>
                </div>
            </div>
        </div>
    </div>
    <!-- FIN DE ALERTA -->
    <div class="loader-container loading--show">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>

    <script>
        <?php if ($showDashboardCreditos): ?>
            // Desembolsos
            const chartLabels = <?php echo $labels_json ?? '[]'; ?>;
            const chartValuesDesembolsado = <?php echo $valDesembolsado_json ?? '[]'; ?>;

            // Recuperaciones
            const chartLabelsRecuperado = <?php echo $labels_recuperaciones_json ?? '[]'; ?>;
            const chartValuesRecuperado = <?php echo $valRecuperado_json ?? '[]'; ?>;

            // Para graficar ambas series en la misma Chart.js, 
            // deben compartir las etiquetas (chartLabels). 
            // Si tienen las mismas etiquetas, las puedes usar directamente.

            const ctx = document.getElementById('chart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    // Si confirmaste que ambos comparten el mismo eje de tiempo (mes),
                    // usa chartLabels. De lo contrario, unifícalas antes.
                    labels: chartLabels,
                    datasets: [{
                            label: 'Desembolsos últimos 5 meses',
                            data: chartValuesDesembolsado,
                            borderColor: 'blue',
                            fill: false
                        },
                        {
                            label: 'Recuperaciones en el mismo periodo',
                            data: chartValuesRecuperado,
                            borderColor: 'green',
                            fill: false
                        }
                    ]
                },
                options: {
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return 'Q' + value;
                                }
                            }
                        }
                    }
                }
            });

            $(document).ready(function() {
                $.ajax({
                    url: 'dashboard_data.php', // Ajusta la ruta si es necesario
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.error) {
                            console.error("Error del servidor:", data.error);
                            return;
                        }

                        // Validar que los datos recibidos sean correctos
                        // console.log("Datos recibidos:", data);

                        // Actualiza los elementos del dashboard con los datos recibidos
                        $('#creditos-activos').text('Q' + parseFloat(data.creditos_activos_actual || 0).toLocaleString());
                        $('#porcentaje-cambio').text(
                            (data.porcentaje_cambio >= 0 ? '+' : '') +
                            parseFloat(data.porcentaje_cambio || 0).toFixed(2) + '% vs mes anterior'
                        );
                        $('#creditos-grupales').text(data.creditos_grupales_activos || 0);
                        $('#grupos-activos').text((data.grupos_activos || 0) + " grupos activos");
                        $('#cartera-en-riesgo').text('Q' + parseFloat(data.cartera_en_riesgo || 0).toLocaleString());
                        $('#proximos-vencimientos').text(data.proximos_vencimientos || 0);

                        // Validar que las ganancias no sean nulas
                        let gananciasMesActual = data.ganancias_ano_actual ? (data.ganancias_mes_actual / data.ganancias_ano_actual) * 100 : 0;
                        let gananciasMesAnterior = data.ganancias_ano_actual ? (data.ganancias_mes_anterior / data.ganancias_ano_actual) * 100 : 0;

                        $('#ganancias-mes-actual').css('width', `${gananciasMesActual}%`).text('Q' + parseFloat(data.ganancias_mes_actual || 0).toLocaleString());
                        $('#ganancias-mes-anterior').css('width', `${gananciasMesAnterior}%`).text('Q' + parseFloat(data.ganancias_mes_anterior || 0).toLocaleString());
                        $('#ganancias-ano-actual').css('width', '100%').text('Q' + parseFloat(data.ganancias_ano_actual || 0).toLocaleString());
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar los datos del dashboard:', error);
                    }
                });
            });
        <?php endif; ?>
    </script>

    <script src="../../includes/js/script.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- <script type="text/javascript" src="../../includes/js/all.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</body>

</html>
<?php

?>