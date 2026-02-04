<?php

use Micro\Helpers\Log;

include __DIR__ . '/../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    session_unset();
    exit;
}
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');


$show_notification = false;
if (!isset($_COOKIE['last_notification'])) {
    $show_notification = true;
    setcookie('last_notification', time(), time() + 86400, "/"); // 86400 segundos = 1 día
} else {
    $last_notification = $_COOKIE['last_notification'];
    if (time() - $last_notification > 86400) {
        $show_notification = true;
        setcookie('last_notification', time(), time() + 86400, "/");
    }
}

require __DIR__ . '/infoEnti/infoEnti.php';
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
$titlemodule = "Home";
$hora_actual = date('H');
if ($hora_actual < 12) {
    $saludo = "Buenos días";
} elseif ($hora_actual < 19) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}
$idModuleCurrent = 0;
?>
<!DOCTYPE html>
<html lang="en"  data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <!--borrar estas 3 lineas al terminar desarrollo-->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>MENU PRINCIPAL</title>
    <link rel="shortcut icon" type="image/x-icon" href="../includes/img/favmicro.ico">
    <link rel="stylesheet" href="../includes/css/styleCard.css">
    <link rel="stylesheet" href="../includes/css/style.css">
    <!-- <link rel="stylesheet" href="stil.css"> -->
    <!-- <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet"> -->

    <?php
    require_once __DIR__ . '/../includes/incl.php';
    ?>
</head>
<style>
    /* From Uiverse.io by LilaRest */
    .cardlogo {
        --bg: #e8e8e8;
        --contrast: #e2e0e0;
        --grey: #93a1a1;
        position: relative;
        padding: 8px;
        background-color: var(--bg);
        border-radius: 35px;
        box-shadow: rgba(50, 50, 93, 0.25) 0px 50px 100px -20px, rgba(0, 0, 0, 0.3) 0px 30px 60px -30px, rgba(10, 37, 64, 0.35) 0px -2px 6px 0px inset;
    }

    .cardlogo-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background: repeating-conic-gradient(var(--bg) 0.0000001%, var(--grey) 0.000104%) 60% 60%/600% 600%;
        filter: opacity(10%) contrast(105%);
    }

    .cardlogo-inner {
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        width: 700px;
        height: 450px;
        background-color: var(--contrast);
        border-radius: 30px;
        /* Content style */
        font-size: 30px;
        font-weight: 900;
        color: #c7c4c4;
        text-align: center;
        font-family: monospace;
    }
</style>

<body class="">
    <?php
    require __DIR__ . '/../src/menu/menu_bar.php';
    ?>
    <section class="home">
        <div class="container" style="max-width: none !important;">
            <?php require __DIR__ . '/../src/menu/menu_barh.php'; ?>
            <!-- VERIFICACION DE PAGO -->
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

                //Log::info("Notificación de pago - Caso: $case, Fecha de pago: $fecha_pago, Hoy: " . date('Y-m-d', $hoy) . ", Usuario: " . $_SESSION['usu'], ['user_id' => $_SESSION['id']]);
                switch ($case) {
                    case 'dentro_rango_menos':
            ?>
                        <center>
                            <div class="container">
                                <div class="cardalert">
                                    <div class="header">
                                        <div class="image_advert">
                                            <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                                                fill="none">
                                                <path
                                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                                                    stroke-linejoin="round" stroke-linecap="round"></path>
                                            </svg>
                                        </div>
                                        <div class="content">
                                            <span class="title">AVISO</span>
                                            <p class="message">Este es un recordatorio! tu pago está programado para
                                                efectuarse el día
                                                <?php echo date('d F Y', strtotime($fecha_pago)); ?></p>
                                        </div>
                                        <div class="actions">
                                            <button class="desactivate btn btn-success" type="button" data-toggle="modal"
                                                data-target="#exampleModalCenter">
                                                <i class="fas fa-exclamation-triangle"></i> Ver detalles
                                            </button>
                                            <!-- Modal -->
                                            <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
                                                aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exampleModalLongTitle">Advertencia de pago
                                                            </h5>
                                                            <button type="button" class="close" data-dismiss="modal"
                                                                aria-label="Close">
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
                                                            <button type="button" class="btn btn-warning"
                                                                data-dismiss="modal">Close</button>
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
                                                <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                                                    fill="none">
                                                    <path
                                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                                                        stroke-linejoin="round" stroke-linecap="round"></path>
                                                </svg>
                                            </div>
                                            <div class="content">
                                                <span class="title"> Ultimo aviso
                                                </span>
                                                <p class="message">Mañana es el último día para renovar tu suscripción. Si no lo haces,
                                                    tu cuenta se bloqueará automáticamente. </p>
                                            </div>
                                            <div class="actions">
                                                <button class="desactivate btn btn-danger" type="button" data-toggle="modal"
                                                    data-target="#exampleModalCenter">
                                                    <i class="fas fa-exclamation-triangle"></i> Ver detalles
                                                </button>
                                                <!-- Modal -->
                                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
                                                    aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">Alerta por falta de
                                                                    pago</h5>
                                                                <button type="button" class="close" data-dismiss="modal"
                                                                    aria-label="Close">
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
                                                                <button type="button" class="btn btn-warning"
                                                                    data-dismiss="modal">Close</button>

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
                                                <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                                                    fill="none">
                                                    <path
                                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                                                        stroke-linejoin="round" stroke-linecap="round"></path>
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
                                                <button class="desactivate btn btn-danger" type="button" data-toggle="modal"
                                                    data-target="#exampleModalCenter">
                                                    <i class="fas fa-coins"></i>
                                                </button>
                                                <!-- Modal -->
                                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
                                                    aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">Alerta por falta de
                                                                    pago</h5>
                                                                <button type="button" class="close" data-dismiss="modal"
                                                                    aria-label="Close">
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
                                                                <button type="button" class="btn btn-warning"
                                                                    data-dismiss="modal">Close</button>

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
            ?>
            <!-- </div>
            </div> -->
            <div id="cuadro">
                <div class="d-flex flex-column h-100">
                    <div class="flex-grow-1">
                        <div class="row align-items-center"
                            style="max-width: none !important; height: calc(75vh) !important;">
                            <div class="row d-flex justify-content-around">
                                <p class="h3 font-weight-bold text-center text-white"
                                    style="text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;">
                                    <?php echo $saludo . ', ' . $_SESSION['nombre']; ?>
                                </p>
                                <div class="d-flex justify-content-center align-items-center"
                                    style="height: 85vh; margin-top: -5vh;">
                                    <div class="cardlogo">
                                        <div class="cardlogo-overlay"></div>
                                        <div class="cardlogo-inner">
                                            <div class="text-center">
                                                <img src="<?= '..' . $infoEnti['imagenEnti']  ?>"
                                                    alt="Imagen de la entidad" width="500" class="img-fluid">
                                                <p class="text-success"
                                                    style="font-family: 'Garamond', serif; font-weight: bold; font-size: x-large;">
                                                    Sistema orientado para microfinanzas
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="loader-container loading--show">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>

    <script src="../includes/js/script.js"></script>
    <!-- <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> -->
</body>

</html>