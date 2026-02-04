<?php

use Micro\Generic\Asset;
use Micro\Generic\AssetVite;

include __DIR__ . '/../../includes/Config/config.php';
session_start();
if (!isset($_SESSION['id_agencia'])) {
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

$showmensaje = false;
try {
    $database->openConnection();
    //INFORMACION INSTITUCION
    $infoEnti = infoEntidad($idagencia, $database, $db_name_general);
    $estado = $infoEnti['estado'];
    $fecha_pago = $infoEnti['fecha_pago'];

    //CONSULTA DE PERMISOS DEL USUARIO
    $permisos = getpermisosuser($database, $idusuario, 'G', 19, $db_name_general);

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

$idModuleCurrent = 19;

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras y ventas</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../includes/img/favmicro.ico">
    <link rel="stylesheet" href="../../includes/css/style.css">

    <?php require_once __DIR__ . '/../../includes/incl.php';  ?>

</head>

<body class="">
    <?php require __DIR__ . '/../../src/menu/menu_bar.php';
    ?>

    <section class="home">
        <div class="container" style="max-width: none !important;">
            <div class="row">
                <div class="col d-flex justify-content-start">
                    <div class="text">COMPRAS Y VENTAS</div>
                </div>
                <div class="col d-flex justify-content-end">
                    <div class="text">--</div>
                </div>
            </div>

            <div class="btn-group" id="nav_group" role="group">

                <?php
                if ($printpermisos) {
                    end($permisos[1]);
                    $lastKey = key($permisos[1]);
                    reset($permisos[1]);

                    $extra = 0;

                    $showmenuheader = true;
                    $showmenufooter = false;
                    foreach ($permisos[1] as $key => $permiso) {
                        $idsmenu = $permiso["opcion"];
                        $extra = (in_array($idsmenu, [157, 158])) ? 2 : 0;
                        $extra = (in_array($idsmenu, [160, 161])) ? 1 : $extra;
                        $extra = (in_array($idsmenu, [156])) ? -2 :  $extra;
                        $extra = (in_array($idsmenu, [159])) ? -1 :  $extra;
                        $menu = $permiso["menu"];
                        $descripcion = $permiso["descripcion"];
                        $condi = ($idsmenu == 160) ? 'loadfilesventas' : $permiso["condi"];
                        $condi = ($idsmenu == 159) ? 'invoicesventas' : $condi;
                        $condi = ($idsmenu == 161) ? 'reportesventas' : $condi;
                        $file = $permiso["file"];
                        $caption = $permiso["caption"];

                        if ($showmenuheader) {
                            echo '<div class="btn-group me-1" role="group">
                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">' . $descripcion . '
                                    <span class="caret"></span></button>
                                    <ul class="dropdown-menu">';
                            $showmenuheader = false;
                        }

                        echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`' . $condi . '`, `#cuadro`, `' . $file . '`, `' . $extra . '`)">' . $caption . '</a></li>';

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
            <div id="cuadro">
                <div class="d-flex flex-column h-100">
                    <div class="flex-grow-1">
                        <div class="row align-items-center" style="max-width: none !important; height: calc(75vh) !important;">
                            <div class="row d-flex justify-content-center">
                                <div class="col-auto">
                                    <img alt="" srcset="" width="500">
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
    <div class="loader-container loading--show">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>
    <script src="../../includes/js/script.js"></script>
    <script src="../../includes/js/scrpt_cv.js"></script>
    <script src="../../includes/js/script_facturas.js"></script>

    <?php
    // Asset::script('shared', ['defer' => true]); 
    // Asset::script('compras_ventas', ['defer' => true]);
    echo AssetVite::script('shared', [
        'type' => 'module'
    ]);
    echo AssetVite::script('comprasventas', [
        'type' => 'module'
    ]);
    // Debug info (solo en desarrollo)
    if (!$isProduction) {
        echo AssetVite::debug();
    }


    ?>
</body>

</html>