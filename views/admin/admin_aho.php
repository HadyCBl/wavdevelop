<?php
include __DIR__ . '/../../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    return;
}
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

require __DIR__ . '/../infoEnti/infoEnti.php';

/* SE AGREGA DE MANERA TEMPORAL */
// include __DIR__ . '/../../includes/BD_con/db_con.php';
// mysqli_set_charset($conexion, 'utf8');
/* QUITAR CUANDO YA NO SE NECESITE */

$showmensaje = false;
try {
    $database->openConnection();
    //INFORMACION INSTITUCION
    $infoEnti = infoEntidad($idagencia, $database, $db_name_general);
    $estado = $infoEnti['estado'];
    $fecha_pago = $infoEnti['fecha_pago'];

    //CONSULTA DE PERMISOS DEL USUARIO
    $permisos = getpermisosuser($database, $idusuario, 'A', 13, $db_name_general);

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
$printpermisos = ($status == 1 && $permisos[0] == 1) ? true : false;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin ahorros</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../includes/img/favmicro.ico">
    <link rel="stylesheet" href="../../includes/css/style.css">
    <?php require_once __DIR__ . '/../../includes/incl.php'; ?>
</head>

<body class="">
    <?php require __DIR__ . '/../../src/menu/menu_admin.php'; ?>

    <section class="home">
        <div class="text">MODULO DE AHORROS</div>
        <div class="btn-group" id="nav_group" role="group">
            <!-- IMPRESION DE OPCIONES -->
            <?php
            if ($printpermisos) {
                end($permisos[1]);
                $lastKey = key($permisos[1]);
                reset($permisos[1]);

                $showmenuheader = true;
                $showmenufooter = false;
                foreach ($permisos[1] as $key => $permiso) {
                    $menu = $permiso["menu"];
                    $descripcion = $permiso["descripcion"];
                    $condi = $permiso["condi"];
                    $file = $permiso["file"];
                    $caption = $permiso["caption"];

                    if ($showmenuheader) {
                        echo '
                            <div class="btn-group me-1" role="group">
                              <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">' . $descripcion . '
                                <span class="caret"></span></button>
                              <ul class="dropdown-menu">';
                        $showmenuheader = false;
                    }

                    echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`' . $condi . '`, `#cuadro`, `' . $file . '`, `0`)">' . $caption . '</a></li>';

                    if ($key === $lastKey) {
                        $showmenufooter = true;
                    } else {
                        if ($permisos[1][$key + 1]['menu'] != $menu) {
                            $showmenufooter = true;
                        }
                    }

                    if ($showmenufooter) {
                        echo '</ul></div>';
                        $showmenufooter = false;
                        $showmenuheader = true;
                    }
                }
            }
            ?>
        </div>
        <button type="button" class="btn btn-warning" onclick="window.location.reload();">RELOAD <i class="fa-solid fa-arrow-rotate-right"></i> </button>
        <div class="container " id="cuadro">
            <div class="panel panel-success">
                <div class="panel-heading"></div>
                <div class="panel-body"> </div>
            </div>
        </div>

    </section>
    <div class="loader-container loading--show">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>
    <script src="../../includes/js/script.js"></script>
    <script src="../../includes/js/script_adminaho.js"></script>
</body>

</html>