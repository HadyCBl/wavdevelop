<?php
include __DIR__ . '/../../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    session_unset();
    return;
}
/* SE AGREGA DE MANERA TEMPORAL */
// include __DIR__ . '/../../includes/BD_con/db_con.php';
// mysqli_set_charset($conexion, 'utf8');
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

//--------
// session_start();
// include '../../includes/Config/config.php';
// if (!isset($_SESSION['usu'])) {
//     header('location: ' . BASE_URL);
// } else {
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta http-equiv="Permissions-Policy" content="interest-cohort=()">
    <meta charset=UTF-8>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!--borrar estas 3 lineas al terminar desarrollo-->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Administrador</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../includes/img/favmicro.ico">
    <link rel="stylesheet" href="../../includes/css/style.css">
    <?php
    require_once __DIR__ . '/../../includes/incl.php';
    ?>
</head>

<body class="">
    <?php
    require __DIR__ . '/../../src/menu/menu_admin.php';
    ?>

    <section class="home">
        <div class="container" style="max-width: none !important;">
            <div class="row">
                <div class="col d-flex justify-content-start">
                    <div class="text">SOTECPRO ADMIN</div>
                </div>
                <div class="col d-flex justify-content-end">
                    <div class="text"><?= $infoEnti['nomAge'] ?? '' ?></div>
                </div>
            </div>
            <div id="cuadro">
                <div class="d-flex flex-column h-100">
                    <div class="flex-grow-1">
                        <div class="row align-items-center" style="max-width: none !important; height: calc(75vh) !important;">
                            <div class="row d-flex justify-content-center">
                                <div class="col-auto">
                                    <img src="<?= '../../' . $infoEnti['imagenEnti'] ?>" alt="" srcset="" width="500">
                                    <p class="displayed text-success text-center" style='font-family: "Garamond", serif;
                      font-weight: bold;
                      font-size: x-large;'> WAVDEVELOP </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>
    <!-- div contenedor para el efecto de loader -->
    <div class="loader-container loading--hide">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>

    <script src="../../includes/js/script.js"></script>
    <!-- <script src="../../includes/js/all.min.js"></script> -->
</body>

</html>
<?php
// }
?>