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
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
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
    $permisos = getpermisosuser($database, $idusuario, 'A', 17, $db_name_general);

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
    <meta charset="UTF-8" style="background: #707070;">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin módulos</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../includes/img/favmicro.ico">
    <link rel="stylesheet" href="../../includes/css/style.css">

    <?php require_once __DIR__ . '/../../includes/incl.php'; ?>
</head>

<body class="">
    <?php
    require __DIR__ . '/../../src/menu/menu_admin.php';
    ?>

    <section class="home">
        <div class="container" style="max-width: none !important;">
            <div class="row">
                <div class="col d-flex justify-content-start">
                    <div class="text">Usuarios</div>
                </div>
                <div class="col d-flex justify-content-end">
                    <div class="text"><?= $infoEnti['nomAge'] ?? '' ?></div>
                </div>
            </div>

            <div class="btn-group" id="nav_group" role="group">
                <!-- IMPRESION DE OPCIONES -->
                <?php
                if ($printpermisos && $idusuario == 4) {
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
                <div class="btn-group me-1" role="group">
                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button"
                        data-bs-toggle="dropdown">Registros
                        <span class="caret"></span></button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" style="cursor: pointer;"
                                onclick="printdiv(`actualizaciones`, `#cuadro`, `superadmin_02`, `0`)">Ultimas
                                actualizaciones</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;"
                                onclick="printdiv(`log`, `#cuadro`, `superadmin_02`, `0`)">Accesos y Registros</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;"
                                onclick="printdiv(`configuraciones_generales`, `#cuadro`, `superadmin_02`, `0`)">Configuraciones
                                generales</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;"
                                onclick="printdiv(`cache_manager`, `#cuadro`, `superadmin_02`, `0`)">Manejador de caché</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;"
                                onclick="printdiv(`ably_manager`, `#cuadro`, `superadmin_02`, `0`)">Manejador de Ably</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;"
                                onclick="printdiv(`logs_registros`, `#cuadro`, `superadmin_02`, `0`)">Logs Registros</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;"
                                onclick="printdiv(`permissions_modules`, `#cuadro`, `superadmin_02`, `0`)">Permisos a Módulos</a></li>
                    </ul>
                </div>
            </div>
            <button type="button" class="btn btn-warning" onclick="window.location.reload();">RELOAD <i
                    class="fa-solid fa-arrow-rotate-right"></i> </button>
            
            <!-- Token CSRF global para módulos cargados dinámicamente -->
            <div id="csrf-token-container" style="display:none;">
                <?php echo $csrf->getTokenField(); ?>
            </div>
            
            <div id="cuadro">
                <div class="d-flex flex-column h-100">
                    <div class="flex-grow-1">
                        <div class="row align-items-center"
                            style="max-width: none !important; height: calc(75vh) !important;">
                            <div class="row d-flex justify-content-center">
                                <div class="col-auto">
                                    <img src="<?= '../../' . $infoEnti['imagenEnti'] ?? '' ?>" alt="" srcset=""
                                        width="500">
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
    <!-- PARA PROBAR COMO AGREGAR LOS MODALES   -->
    <div class="loader-container loading--show">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>

    <script src="../../includes/js/script.js"></script>
    <script src="../../includes/js/scrpt_superadmin.js"> </script>
</body>

</html>