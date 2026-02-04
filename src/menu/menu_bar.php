<?php

use Micro\Helpers\Log;
use CzProject\GitPhp\Git;

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
    $result = getpermisosmodules($database, $idagencia, 'G', $db_name_general);
    if ($result[0] == 0) {
        // $showmensaje = true;
        throw new Exception($result[1]);
    }

    $ramas = array(
        4 => 'R',
        11 => 'A',
        22 => 'I',
    );

    foreach ($result[1] as $pm) {
        $idmodulo = $pm["id"];
        $rama = $pm["rama"];
        // $rama = ($idmodulo == 4) ? "R" : (($idmodulo == 11) ? "A" : $rama);
        $rama = (isset($ramas[$idmodulo])) ? $ramas[$idmodulo] : $rama;
        // $resultado = ($idmodulo == 4 || $idmodulo == 11) ? getpermisosmodules($database, $idagencia, $rama, $db_name_general) : getpermisosuser($database, $idusuario, $rama, $idmodulo, $db_name_general);

        if (isset($ramas[$idmodulo])) {
            $result2 = getpermisosmodules($database, $idagencia, $rama, $db_name_general);
            if ($result2[0] == 0) {
                // $showmensaje = true;
                // throw new Exception($result2[1]);
            } else {
                $flag = false;
                foreach ($result2[1] as $pm2) {
                    $idmodulo = $pm2["id"];
                    $rama = $pm2["rama"];
                    $resultado = getpermisosuser($database, $idusuario, $rama, $idmodulo, $db_name_general);
                    if ($resultado[0] == 1) {
                        // $showmodules[$i] = $pm;
                        // $i++;
                        $flag = true;
                    }
                }
                if ($flag) {
                    $showmodules[$i] = $pm;
                    $i++;
                }
            }
        } else {
            $resultado = getpermisosuser($database, $idusuario, $rama, $idmodulo, $db_name_general);
            if ($resultado[0] == 1) {
                $showmodules[$i] = $pm;
                $i++;
            }
        }
    }

    /**
     * Carga de configuraciones generales
     */

    $nameSystem = $appConfigGeneral->getNombreSistema();
    $logoSystem = $appConfigGeneral->getLogoSistema();

    /**
     * Carga de datos de usuario autenticado
     * Instancia de la clase User
     */

    $authenticatedUser = new App\Generic\User($_SESSION['id'] ?? 0);
    $nombreCompletoUser = $authenticatedUser->getNombreCompleto();

    /**
     * ACTUALIZACION INFO
     */
    try {
        $ruta_proyecto = __DIR__ . '/../../';
        $gitBinary = $_ENV['GIT_BINARY'] ?? '/usr/local/cpanel/3rdparty/lib/path-bin/git';

        // Verificar si el binario existe
        if (!file_exists($gitBinary)) {
            throw new Exception('Git binary not found at: ' . $gitBinary);
        }

        // Crear instancia de Git con el binario específico
        $git = new Git(new \CzProject\GitPhp\Runners\CliRunner($gitBinary));
        $repo = $git->open($ruta_proyecto);

        /**
         * traer la fecha del ultimo commit
         */
        $commitId = $repo->getLastCommitId();
        $commit = $repo->getCommit($commitId);
        $commitDate = $commit->getDate();
    } catch (Exception $e) {
        $commitDate = null;
        // Log::error('Error al obtener la fecha del último commit: ' . $e->getMessage());
    }


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
$showmodules = ($status) ? $showmodules : [];

// $rutalogomicro = BASE_URL . 'includes/img/logomicro.png';
$rutalogomicro = file_exists(__DIR__ . '../includes/img/' . $logoSystem) ? '/includes/img/' . $logoSystem : '/includes/img/logomicro.png';
?>
<nav class="sidebar">
    <header>
        <div class="image-text">
            <span class="image">
                <img src="<?= BASE_URL . $rutalogomicro; ?>" alt="">
            </span>
            <div class="text logo-text">
                <span style="font-size: smaller;" class="name"><?= $nameSystem ?? 'Microsystem' ?> </span>
                <span style="font-size: smaller;" class="profession"><?= $nombreCompletoUser ?? '-' ?></span>
            </div>
        </div>

        <i class='bx bx-chevron-right toggle'></i>
    </header>

    <div class="menu-bar">
        <div class="menu">

            <ul class="menu-links ps-3">
                <li class="nav-link">
                    <a class=" <?php echo ($idModuleCurrent == 0) ? 'is-active' : ''; ?>" href="<?php echo BASE_URL . '/' . 'views/'; ?>">
                        <i class="fa-solid fa-house fa-xl" id="ico"></i>
                        <span class="text nav-text" id="txtmenu">Home</span>
                    </a>
                </li>
                <?php

                foreach ($showmodules as $fila) {
                    $isActive = ($fila['id'] == $idModuleCurrent) ? true : false;
                    $descripcion = $fila['descripcion'];
                    if ($fila['id'] == 2) {
                        $descripcion = ($_ENV['AHO_NAME_MODULE'] ?? $fila['descripcion']);
                    }
                    if ($fila['id'] == 3) {
                        $descripcion = ($_ENV['APR_NAME_MODULE'] ?? $fila['descripcion']);
                    }
                    $icon = $fila['icon'];
                    $ruta = $fila['ruta'];
                    echo '<li class="nav-link"><a ' . ($isActive ? 'class="is-active"' : '') . ' href="' . BASE_URL . "/" . "views/" . $ruta . '"><i class="' . $icon . '" id="ico"></i>
                                <span class="text nav-text"  id="txtmenu">' . $descripcion . '</span> </a>
                            </li>';

                    // $route = "api/" . $ruta . "/" . $submenu['condi'];
                    // $onclickAction = sprintf(
                    //     "loadModuleView('#cuadro', { route: '%s' });",
                    //     $route,
                    // );
                }
                // echo '<li class="nav-link"><a href="' . BASE_URL . "/" . 'views/cumplimiento.php"><i class="fa-solid fa-house-flood-water-circle-arrow-right" id="ico"></i>
                //                 <span class="text nav-text"  id="txtmenu">Cumplimiento Y VAT</span> </a>
                //             </li>';
                ?>
            </ul>
        </div>

        <div class="bottom-content">
            <?php if (!empty($commitDate)): ?>
                <li class="nav-link" id="commit-info">
                    <div class="d-flex justify-content-center align-items-center py-1">
                        <span class="text-muted" style="font-size: 0.8em;">
                            Updated: <?= $commitDate->format('d-m-Y H:i'); ?>
                        </span>
                    </div>
                </li>
            <?php endif; ?>

            <li class="">
                <a id="eliminarsesion" style="cursor: pointer;"
                    onclick="sessiondestroy('<?php echo BASE_URL; ?>/src/cruds/crud_usuario.php');">
                    <i class='bx bx-log-out icon'></i>
                    <span class="text nav-text">Cerrar sesión</span>
                </a>
            </li>

            <li class="mode">
                <div class="sun-moon">
                    <i class='bx bx-moon icon moon'></i>
                    <i class='bx bx-sun icon sun'></i>
                </div>
                <span class="mode-text text">Modo Oscuro</span>

                <div class="toggle-switch" onclick="active_modo()">
                    <span class="switch"></span>
                </div>
            </li>

        </div>
    </div>
</nav>
<?php
// }
?>

<!-- **************************************ALERTAS -->
<div class="class" id="tbAlerta"></div>