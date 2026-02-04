<?php

use Micro\Generic\AssetVite;
use Micro\Generic\ModulePermissionManager;
use Micro\Helpers\Log;

include __DIR__ . '/../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
  header('location: ' . BASE_URL);
  return;
}
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

require __DIR__ . '/infoEnti/infoEnti.php';

/* SE AGREGA DE MANERA TEMPORAL */
include __DIR__ . '/../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
/* QUITAR CUANDO YA NO SE NECESITE */
$titlemodule = "Contabilidad";
$idModuleCurrent = 9; // ID DEL MODULO CONTABILIDAD
$showmensaje = false;
try {
  $database->openConnection();
  //INFORMACION INSTITUCION
  $infoEnti = infoEntidad($idagencia, $database, $db_name_general);
  $estado = $infoEnti['estado'];
  $fecha_pago = $infoEnti['fecha_pago'];

  //CONSULTA DE PERMISOS DEL USUARIO
  // $permisos = getpermisosuser($database, $idusuario, 'G', 9, $db_name_general);
  $permissionManager = new ModulePermissionManager($database, $db_name_general);
  $permissionsResult = $permissionManager->getUserModulePermissions($idusuario, 'G', $idModuleCurrent);

  if (!$permissionsResult['success']) {
    $showmensaje = true;
    throw new Exception($permissionsResult['message']);
  }

  // Log::info('Permisos obtenidos: ' . print_r($permissionsResult, true));

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

/**
 * SECCION DE MIGRACION A MVC
 * SI EL MODULO YA FUE MIGRADO, SE REDIRECCIONA A LA RUTA CORRESPONDIENTE
 */


/**
 * para los modulos que ya usen routes y controladores para las vistas
 *  route: 'api/conta/partidas/index'  ejemplo
 * la ruta se forma asi, en modulos esta la ruta principal (campo ruta) ej conta
 * en submenus esta el resto de la ruta (campo condi) ej partidas/index
 * 
 */

$submenusMigrados = [
  78 => ['condi' => 'mayor/index'],
];

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contabilidad</title>
  <link rel="shortcut icon" type="image/x-icon" href="../includes/img/favmicro.ico">
  <link rel="stylesheet" href="../includes/css/style.css">

  <?php require_once __DIR__ . '/../includes/incl.php';  ?>

  <?php echo AssetVite::style('shared'); ?>
</head>

<body class="">
  <?php require __DIR__ . '/../src/menu/menu_bar.php';
  ?>

  <section class="home">
    <div class="container" style="max-width: none !important;">
      <?php require __DIR__ . '/../src/menu/menu_barh.php'; ?>

      <div class="btn-group" id="nav_group" role="group">
        <!-- IMPRESION DE OPCIONES -->
        <?php
        if ($status == 1) {
          // end($permisos[1]);
          // $lastKey = key($permisos[1]);
          // reset($permisos[1]);

          $lastKey = array_key_last($permissionsResult['data']);

          $showmenuheader = true;
          $showmenufooter = false;
          foreach ($permissionsResult['data'] as $key => $permiso) {
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

            if (in_array($permiso['opcion'], array_keys($submenusMigrados))) {
              // SUBMENU MIGRADO A MVC, SE REDIRECCIONA A LA RUTA CORRESPONDIENTE
              $condi = $submenusMigrados[$permiso['opcion']]['condi'];
              $route = "api/conta/" . $condi;
              echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="loadModuleView(`#cuadro`, { route: `' . $route . '` });">' . $caption . '</a></li>';
              continue;
            }

            echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`' . $condi . '`, `#cuadro`, `' . $file . '`, `0`)">' . $caption . '</a></li>';

            if ($key === $lastKey) {
              $showmenufooter = true;
            } else {
              if ($permissionsResult['data'][$key + 1]['menu'] != $menu) {
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
                  <img src="<?= '..' . $infoEnti['imagenEnti'] ?? '' ?>" alt="" srcset="" width="500">
                  <p class="displayed text-success text-center" style='font-family: "Garamond", serif;
                      font-weight: bold;
                      font-size: x-large;'> Sistema orientado para microfinanzas </p>
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
  <?php include_once __DIR__ . "/../src/cris_modales/mdls_nomenclatura.php"; ?>
  <script src="../includes/js/script.js"></script>
  <script src="../includes/js/scrpt_ctb.js"></script>
  <?php

  echo AssetVite::script('shared', [
    'type' => 'module'
  ]);
  echo AssetVite::script('reportes', [
    'type' => 'module'
  ]);
  if (!$isProduction) {
    echo AssetVite::debug();
  }

  ?>
</body>

</html>
<?php
// }
?>