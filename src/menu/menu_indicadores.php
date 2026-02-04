<?php

if (!isset($_SESSION['usu'])) {
    include_once __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL);
    return;
}

$showmensaje = false;
$showmodules[] = [];
$i = 0;
try {
    $database->openConnection();
    $result = getpermisosmodules($database, $idagencia, 'I', $db_name_general);
    if ($result[0] == 0) {
        $showmensaje = true;
        throw new Exception($result[1]);
    }

    foreach ($result[1] as $pm) {
        $idmodulo = $pm["id"];
        $rama = $pm["rama"];
        $resultado = getpermisosuser($database, $idusuario, $rama, $idmodulo, $db_name_general);
        if ($resultado[0] == 1) {
            $showmodules[$i] = $pm;
            $i++;
        }
    }

    /**
     * Carga de configuraciones generales
     */

    $nameSystem = $appConfigGeneral->getNombreSistema();
    $logoSystem = $appConfigGeneral->getLogoSistema();

    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}
$showmodules = ($status) ? $showmodules : [];
// echo "<pre>";
// echo print_r($showmodules);
// echo "</pre>";

$makeUrl = BASE_URL . "/views/indicadores/";

// $rutalogomicro = '../includes/img/logomicro.png';
$rutalogomicro = file_exists(__DIR__ . '../includes/img/' . $logoSystem) ? '../includes/img/' . $logoSystem : '../includes/img/logomicro.png';
?>
<nav class="sidebar ">
    <header>
        <div class="image-text">

            <div class="text logo-text">
                <span class="name"><?= $rutalogomicro; ?></span>
                <span class="profession"><?php echo ($_SESSION['nombre']) . ' ' . ($_SESSION['apellido']); ?></span>
            </div>
        </div>

        <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
        <?php if (!$status) { ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>¡Error!</strong> <?= $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } ?>
        <div class="menu">
            <ul class="menu-links" style="padding-left: 0px !important;">
                <li class="nav-link">
                    <a href=" <?= $makeUrl ?>">
                        <i class="fa-solid fa-house fa-xl" id="ico2"></i>
                        <span class="text nav-text" id="txtmenu">Home</span>
                    </a>
                </li>
                <?php
                if (!empty($showmodules)) {
                    foreach ($showmodules as $fila) {
                        $descripcion = $fila['descripcion'];
                        $icon = $fila['icon'];
                        $ruta = $fila['ruta'];
                        echo '<li class="nav-link"><a href="' . $makeUrl . $ruta . '"><i class="' . $icon . '" id="ico2"></i>
                                    <span class="text nav-text"  id="txtmenu">' . $descripcion . '</span></a>
                                </li>';
                    }
                }
                ?>
            </ul>
        </div>

        <div class="bottom-content">
            <!-- <li class="">
                <a id="eliminarsesion2" style="cursor: pointer;">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">Cerrar sesión</span>
                </a>
            </li> -->
            <li class="">
                <a href="<?= BASE_URL ?>">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">INICIO</span>
                </a>
            </li>

            <li class="mode">
                <div class="sun-moon">
                    <i class='bx bx-moon icon moon'></i>
                    <i class='bx bx-sun icon sun'></i>
                </div>
                <span class="mode-text text">Modo Oscuro</span>

                <div class="toggle-switch" onclick="active_modo(1,'../../')">
                    <span class="switch"></span>
                </div>
            </li>

        </div>
    </div>
</nav>