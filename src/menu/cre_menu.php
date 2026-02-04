<?php
include_once __DIR__ . '/../../includes/Config/config.php';
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    return;
}

$showmensaje = false;
$showmodules[] = [];
$i = 0;
try {
    $database->openConnection();
    $result = getpermisosmodules($database, $idagencia, 'R', $db_name_general);
    if ($result[0] == 0) {
        // $showmensaje = true;
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

    $nameSystem = $appConfigGeneral->getNombreSistema();

    /**
     * Carga de datos de usuario autenticado
     * Instancia de la clase User
     */

    $authenticatedUser = new App\Generic\User($_SESSION['id'] ?? 0);

    $status = 1;
} catch (Throwable $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}
$showmodules = ($status) ? $showmodules : [];

// $rutalogomicro = file_exists(__DIR__ . '../includes/img/' . $logoSystem) ? '../includes/img/' . $logoSystem : '../includes/img/logomicro.png';
?>
<nav class="sidebar ">
    <header>
        <div class="image-text">

            <div class="text logo-text">
                <span class="name" style="font-size: smaller;"><?= $nameSystem ?? 'Microsystem' ?></span>
                <span class="profession"><?= $authenticatedUser->getNombreCompleto() ?></span>
            </div>
        </div>

        <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
        <div class="menu">
            <ul class="menu-links" style="padding-left: 0px !important;">
                <li class="nav-link">
                    <a href="./creditos.php">
                        <i class="fa-solid fa-house fa-xl" id="ico2"></i>
                        <span class="text nav-text" id="txtmenu">Home</span>
                    </a>
                </li>
                <?php
                foreach ($showmodules as $fila) {
                    $descripcion = $fila['descripcion'];
                    $icon = $fila['icon'];
                    $ruta = $fila['ruta'];
                    echo '<li class="nav-link"><a href="' . $ruta . '"><i class="' . $icon . '" id="ico2"></i>
                                <span class="text nav-text"  id="txtmenu">' . $descripcion . '</span></a>
                            </li>';
                }
                ?>
            </ul>
        </div>

        <div class="bottom-content">
            <li class="">
                <a id="eliminarsesion2" style="cursor: pointer;">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">Cerrar sesión</span>
                </a>
            </li>
            <li class="">
                <a href="../index.php">
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