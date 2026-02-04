<?php

use App\Generic\PermissionUser;
use Micro\Helpers\Log;
use Micro\Generic\AssetVite;
use Micro\Generic\PermissionManager;

include __DIR__ . '/../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
  header('location: ' . BASE_URL);
  session_unset();
  return;
}
/**
 * Actualizar el último acceso
 */
$_SESSION['ultimo_acceso'] = time();

/**
 * Datos de session
 */
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

// include __DIR__ . '/../includes/BD_con/db_con.php';

include __DIR__ . '/../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

require __DIR__ . '/infoEnti/infoEnti.php';
$showmensaje = false;

$showDashboardClientes = false;
try {
  $database->openConnection();

  // INFORMACION INSTITUCION
  $infoEnti = infoEntidad($idagencia, $database, $db_name_general);
  $estado = $infoEnti['estado'];
  $fecha_pago = $infoEnti['fecha_pago'];

  // CONSULTA DE PERMISOS DEL USUARIO
  // $permisos = getpermisosuser($database, $idusuario, 'G', 1, $db_name_general);

  /**
   * NUEVA FORMA AGREGADA
   */
  // NUEVA FORMA DE CONSULTAR PERMISOS
  $permissionsUser = new PermissionUser($idusuario, $database, $db_name_general);

  // Cargar permisos para el módulo de clientes
  $permisosResult = $permissionsUser->loadUserPermissions('G', 1);
  $permisos = ($permisosResult[0] == 1) ? [1, $permisosResult[1]] : [0, $permisosResult[1]];

  /**
   * FIN NUEVA FORMA AGREGADA
   */

  $controlPermissions = new PermissionManager($idusuario);

  $showDashboardClientes = $controlPermissions->isLevelOne(PermissionManager::VER_DASHBOARD_CLIENTES) ?? false;

  if ($showDashboardClientes) {
    // CONSULTAS PARA ESTADISTICAS
    $sqlNat = "SELECT COUNT(*) as total_nat FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'natural' AND estado = 1 AND fiador!=1";
    $resNat = $database->getAllResults($sqlNat);
    $naturalCount = $resNat[0]['total_nat'] ?? 0;

    $sqlJur = "SELECT COUNT(*) as total_jur FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'juridico' AND estado = 1";
    $resJur = $database->getAllResults($sqlJur);
    $juridicoCount = $resJur[0]['total_jur'] ?? 0;

    $sqlGroup = "SELECT COUNT(DISTINCT cliente_id) as total_group FROM tb_cliente_tb_grupo WHERE estado = 1";
    $resGroup = $database->getAllResults($sqlGroup);
    $groupCount = $resGroup[0]['total_group'] ?? 0;

    $sqlRecent = "SELECT short_name, fecha_alta FROM tb_cliente WHERE estado = 1 ORDER BY fecha_alta DESC LIMIT 5";
    $recentClients = $database->getAllResults($sqlRecent);

    $sqlNewNatThisMonth = "SELECT COUNT(*) as new_nat_this_month FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'natural' AND estado = 1 AND fiador!=1 AND MONTH(fecha_alta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_alta) = YEAR(CURRENT_DATE())";
    $resNewNatThisMonth = $database->getAllResults($sqlNewNatThisMonth);
    $newNatThisMonthCount = $resNewNatThisMonth[0]['new_nat_this_month'] ?? 0;

    $sqlNewJurThisMonth = "SELECT COUNT(*) as new_jur_this_month FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'juridico' AND estado = 1 AND MONTH(fecha_alta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_alta) = YEAR(CURRENT_DATE())";
    $resNewJurThisMonth = $database->getAllResults($sqlNewJurThisMonth);
    $newJurThisMonthCount = $resNewJurThisMonth[0]['new_jur_this_month'] ?? 0;

    // CONSULTA MEJORADA PARA CUMPLEAÑOS CON TELÉFONO
    $birthdayQuery = "SELECT short_name AS nombre,
                          DATE_FORMAT(
                              CONCAT(YEAR(CURDATE()), '-', MONTH(date_birth), '-', DAY(date_birth)),
                              '%d/%m/%Y'
                          ) AS fecha_cumple,
                          email,
                          tel_no1 AS telefono,
                          CASE
                            WHEN DAY(date_birth) = DAY(CURDATE()) AND MONTH(date_birth) = MONTH(CURDATE())
                            THEN 'Hoy'
                            ELSE 'Próximo'
                          END AS estado
                      FROM tb_cliente
                      WHERE date_birth NOT IN ('0000-00-00', '0000-00-00 00:00:00')
                        AND estado = 1
                        AND MONTH(date_birth) = MONTH(CURDATE())
                        AND DAY(date_birth) >= DAY(CURDATE())
                      ORDER BY DAY(date_birth)";

    $birthdays = $database->getAllResults($birthdayQuery);
  }



  $status = 1;
} catch (Exception $e) {
  Log::error($e->getMessage());
  if (!$showmensaje) {
    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
  }
  $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
  $status = 0;
} finally {
  $database->closeConnection();
}

$printpermisos = ($status == 1 && $permisos[0] == 1) ? true : false;
$titlemodule = "Clientes";
$idModuleCurrent = 1; // ID DEL MODULO CLIENTES
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes</title>
  <link rel="shortcut icon" type="image/x-icon" href="../includes/img/favmicro.ico">
  <link rel="stylesheet" href="../includes/css/style.css">
  <link rel="stylesheet" href="../includes/css/dash.css">
  <?php
  require_once __DIR__ . '/../includes/incl.php';
  ?>
</head>

<body class="">
  <?php
  require __DIR__ . '/../src/menu/menu_bar.php';
  ?>

  <section class="home">
    <div class="container" style="max-width: none !important;">
      <?php require __DIR__ . '/../src/menu/menu_barh.php'; ?>
      <div class="btn-group" id="nav_group" role="group">
        <?php
        if ($printpermisos) {
          /**
           *  echo $permissionsUser->generateMenu('G', 1);
           * lo de arriba no funciona para cuando apcu no esta disponible, 
           * ya que trata de consultar a la bd, y ya no esta diponible la conexion
           */

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
      <br>
      <div id="cuadro">
        <div id="cuadro">
          <div class="d-flex flex-column">

            <!-- Nuevo card contenedor -->
            <!-- Dashboard de Clientes - Diseño Moderno -->
            <div class="card fadeIn border shadow-sm mb-4 bg-body">
              <div class="card-header bg-primary bg-opacity-10 border-bottom border-primary d-flex align-items-center justify-content-between">
                <span class="card-title fw-bold text-primary" style="font-size: 1.25rem;">
                  <i class="fa fa-users me-2"></i>Panel de Clientes
                </span>
                <span class="badge bg-primary bg-opacity-75" style="font-size: 1rem;">
                  <i class="fa fa-calendar-alt me-1"></i><?php echo date('F Y'); ?>
                </span>
              </div>
              <?php if ($showDashboardClientes): ?>
                <div class="row g-4 px-4 py-3">
                  <!-- KPIs -->
                  <div class="col-md-4 col-12">
                    <div class="card kpi-card shadow-sm h-100 bg-body">
                      <div class="card-body bg-primary bg-opacity-10 d-flex flex-column align-items-center justify-content-center">
                        <div class="kpi-icon mb-2">
                          <i class="icon-users fa-2x text-primary"></i>
                        </div>
                        <div class="kpi-value display-6 fw-bold text-primary"><?php echo $naturalCount; ?></div>
                        <div class="kpi-label text-body-secondary mb-1">Naturales</div>
                        <span class="badge bg-primary bg-opacity-75 mb-2">
                          <i class="fa fa-plus-circle me-1"></i>Nuevos este mes: <?php echo $newNatThisMonthCount; ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4 col-12">
                    <div class="card kpi-card shadow-sm h-100 bg-body">
                      <div class="card-body bg-info bg-opacity-10 d-flex flex-column align-items-center justify-content-center">
                        <div class="kpi-icon mb-2">
                          <i class="icon-building fa-2x text-info"></i>
                        </div>
                        <div class="kpi-value display-6 fw-bold text-info"><?php echo $juridicoCount; ?></div>
                        <div class="kpi-label text-body-secondary mb-1">Jurídicos</div>
                        <span class="badge bg-info bg-opacity-75 mb-2">
                          <i class="fa fa-plus-circle me-1"></i>Nuevos este mes: <?php echo $newJurThisMonthCount; ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4 col-12">
                    <div class="card kpi-card shadow-sm h-100 bg-body">
                      <div class="card-body bg-success bg-opacity-10 d-flex flex-column align-items-center justify-content-center">
                        <div class="kpi-icon mb-2">
                          <i class="icon-group fa-2x text-success"></i>
                        </div>
                        <div class="kpi-value display-6 fw-bold text-success"><?php echo $groupCount; ?></div>
                        <div class="kpi-label text-body-secondary mb-1">En Grupo</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row g-4 px-4 pb-4">
                  <!-- Gráfico de Distribución -->
                  <div class="col-lg-6 col-12">
                    <div class="card border shadow-sm h-100 bg-body">
                      <div class="card-header bg-info bg-opacity-10 border-bottom border-info">
                        <span class="card-title fw-bold text-info" style="font-size: 1.1rem;">
                          <i class="fa fa-chart-bar me-2"></i>Distribución de Clientes
                        </span>
                      </div>
                      <div class="card-body pt-2">
                        <canvas id="clientDistributionChart" style="max-height: 220px;"></canvas>
                      </div>
                    </div>
                  </div>
                  <!-- Clientes Recientes -->
                  <div class="col-lg-6 col-12">
                    <div class="card border shadow-sm h-100 bg-body">
                      <div class="card-header bg-warning bg-opacity-10 border-bottom border-warning">
                        <span class="card-title fw-bold text-warning" style="font-size: 1.1rem;">
                          <i class="icon-clock me-2"></i>Clientes Recientes
                        </span>
                      </div>
                      <div class="card-body pt-2">
                        <ul class="list-group list-group-flush">
                          <?php foreach ($recentClients as $client): ?>
                            <li class="list-group-item bg-body d-flex justify-content-between align-items-center" style="font-size: 1rem;">
                              <div>
                                <span class="fw-semibold"><?php echo htmlspecialchars($client['short_name']); ?></span>
                                <span class="text-body-secondary ms-2" style="font-size: 0.95rem;">
                                  <i class="fa fa-calendar-alt me-1"></i><?php echo date('d/m/Y', strtotime($client['fecha_alta'])); ?>
                                </span>
                              </div>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="card-body text-center">
                  <!-- SVG animado de bienvenida -->
                  <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 1rem;">
                    <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <circle cx="60" cy="60" r="50" fill="#e0e7ff">
                        <animate attributeName="r" values="45;50;45" dur="2s" repeatCount="indefinite" />
                      </circle>
                      <circle cx="60" cy="60" r="35" fill="#2563eb" opacity="0.2">
                        <animate attributeName="opacity" values="0.2;0.5;0.2" dur="2s" repeatCount="indefinite" />
                      </circle>
                      <ellipse cx="60" cy="60" rx="22" ry="18" fill="#fff" />
                      <ellipse cx="60" cy="60" rx="22" ry="18" fill="#2563eb" opacity="0.1" />
                      <circle cx="60" cy="54" r="8" fill="#2563eb">
                        <animate attributeName="cy" values="54;58;54" dur="1.5s" repeatCount="indefinite" />
                      </circle>
                      <rect x="44" y="70" width="32" height="8" rx="4" fill="#2563eb" opacity="0.15">
                        <animate attributeName="width" values="32;38;32" dur="2s" repeatCount="indefinite" />
                      </rect>
                      <text x="60" y="110" text-anchor="middle" font-size="14" fill="#2563eb" font-family="Arial" opacity="0.7">
                        <tspan>¡Bienvenido!</tspan>
                      </text>
                    </svg>
                  </div>
                  <p class="text-muted">
                    Bienvenido al módulo de clientes.<br>
                    Aquí podrás gestionar y consultar información relevante de los clientes.
                  </p>
                </div>

              <?php endif; ?>
            </div>

            <?php if ($showDashboardClientes): ?>
              <div class="container mt-4" style="font-size:1rem;">
                <ul class="nav nav-tabs rounded-pill bg-body-secondary px-2 py-1 mb-3" id="viewTabs" role="tablist" style="border: none;">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-4 py-2 fw-semibold" id="cumple-tab" data-bs-toggle="tab" data-bs-target="#cumple"
                      type="button" role="tab" aria-controls="cumple" aria-selected="true">
                      <i class="icon-gift me-2"></i>Cumpleaños de Clientes
                    </button>
                  </li>
                </ul>

                <div class="tab-content" id="viewTabsContent">
                  <!-- 1. Cumpleaños de Clientes -->
                  <div class="tab-pane fade show active" id="cumple" role="tabpanel" aria-labelledby="cumple-tab">
                    <div class="card fadeIn border shadow-sm bg-body" style="font-size: 1rem;">
                      <div class="card-header bg-primary bg-opacity-10 border-bottom border-primary d-flex align-items-center justify-content-between">
                        <span class="card-title fw-bold text-primary" style="font-size: 1.1rem;">
                          <i class="icon-gift me-2"></i>Cumpleaños de Clientes
                        </span>
                        <span class="badge bg-primary bg-opacity-75" style="font-size: 0.95rem;">
                          <i class="fa fa-calendar-day me-1"></i><?php echo date('F Y'); ?>
                        </span>
                      </div>
                      <div class="card-content px-3 py-3">
                        <div class="table-responsive">
                          <table id="birthdays-table"
                            class="table table-hover align-middle shadow-sm bg-body"
                            style="min-width: 500px; font-size: 1rem;">
                            <thead style="font-size: 1rem;">
                              <tr class="border-bottom">
                                <th class="text-body-secondary">Nombre</th>
                                <th class="text-body-secondary">Fecha de Cumpleaños</th>
                                <th class="text-body-secondary">Email</th>
                                <th class="text-body-secondary">Teléfono</th>
                                <th class="text-body-secondary">Estado</th>
                                <th class="text-body-secondary">Acciones</th>
                              </tr>
                            </thead>
                            <tbody style="font-size: 1rem;">
                              <?php foreach ($birthdays as $client): ?>
                                <?php
                                if ($client['estado'] === 'Hoy') {
                                  $cellClass = 'table-success';
                                  $iconClass = 'fa fa-birthday-cake text-success';
                                } else {
                                  $cellClass = 'table-warning';
                                  $iconClass = 'fa fa-calendar-day text-warning';
                                }
                                ?>
                                <tr class="fadeIn">
                                  <td class="fw-semibold"><?php echo htmlspecialchars($client['nombre']); ?></td>
                                  <td>
                                    <span class="badge bg-info bg-opacity-75 px-2 py-1" style="font-size: 0.98rem;">
                                      <?php echo $client['fecha_cumple']; ?>
                                    </span>
                                  </td>
                                  <td>
                                    <?php if (!empty($client['email'])): ?>
                                      <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" class="text-decoration-none text-primary">
                                        <i class="fa fa-envelope me-1"></i><?php echo htmlspecialchars($client['email']); ?>
                                      </a>
                                    <?php else: ?>
                                      <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                  </td>
                                  <td>
                                    <?php if (!empty($client['telefono'])): ?>
                                      <a href="tel:<?php echo htmlspecialchars($client['telefono']); ?>"
                                        class="text-decoration-none text-success">
                                        <i class="fa fa-phone me-1"></i><?php echo htmlspecialchars($client['telefono']); ?>
                                      </a>
                                    <?php else: ?>
                                      <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                  </td>
                                  <td class="<?php echo $cellClass; ?> fw-semibold">
                                    <?php echo $client['estado']; ?> <i class="<?php echo $iconClass; ?>"></i>
                                  </td>
                                  <td>
                                    <div class="btn-group" role="group">
                                      <?php if (!empty($client['email'])): ?>
                                        <button
                                          class="btn btn-outline-info btn-sm rounded-pill"
                                          onclick="sendBirthdayEmail('<?php echo addslashes($client['nombre']); ?>', '<?php echo addslashes($client['email']); ?>')"
                                          title="Enviar correo de felicitación">
                                          <i class="fa fa-envelope"></i>
                                        </button>
                                      <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm rounded-pill" disabled title="Sin correo electrónico">
                                          <i class="fa fa-envelope"></i>
                                        </button>
                                      <?php endif; ?>

                                      <?php if (!empty($client['telefono'])): ?>
                                        <button
                                          class="btn btn-outline-success btn-sm rounded-pill"
                                          onclick="sendBirthdayWhatsApp('<?php echo addslashes($client['nombre']); ?>', '<?php echo addslashes($client['telefono']); ?>')"
                                          title="Enviar mensaje de WhatsApp">
                                          <i class="fab fa-whatsapp"></i>
                                        </button>
                                      <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm rounded-pill" disabled title="Sin número de teléfono">
                                          <i class="fab fa-whatsapp"></i>
                                        </button>
                                      <?php endif; ?>
                                    </div>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
  </section>
  <div class="loader-container loading--show">
    <div class="loader"></div>
    <div class="loaderimg"></div>
    <div class="loader2"></div>
  </div>

  <?php
  include_once __DIR__ . "/../src/cris_modales/mdls_acteconomica.php";
  ?>
  <script src="../includes/js/script.js"></script>
  <script src="../includes/js/scriptsclientes.js"></script>
  <script src="../includes/js/SessionManager.js"></script>
  <script src="../includes/js/globalFunctions.js"></script>
  <?php if ($showDashboardClientes): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      const ctx = document.getElementById('clientDistributionChart')?.getContext('2d');
      if (ctx) {
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: ['Naturales', 'Jurídicos'],
            datasets: [{
              data: [<?= $naturalCount; ?>, <?= $juridicoCount; ?>],
              backgroundColor: ['#2563eb', '#7c3aed']
            }]
          },
          options: {
            responsive: true
          }
        });
      }
    </script>
    <script>
      $(document).ready(function() {
        $('#birthdays-table').DataTable({
          pageLength: 10,
          lengthChange: false,
          language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
          }
        });
      });

      // Función para enviar WhatsApp de cumpleaños
      function sendBirthdayWhatsApp(nombre, telefono) {
        const mensaje = `¡Feliz cumpleaños ${nombre}! 🎉🎂 Te deseamos un día lleno de alegría y bendiciones. ¡Que tengas un excelente día!`;
        const encodedMensaje = encodeURIComponent(mensaje);
        const whatsappUrl = `https://wa.me/502${telefono}?text=${encodedMensaje}`;
        window.open(whatsappUrl, '_blank');
      }

      // Función mejorada para enviar email de cumpleaños
      function sendBirthdayEmail(nombre, email) {
        const asunto = encodeURIComponent(`¡Feliz Cumpleaños ${nombre}!`);
        const mensaje = encodeURIComponent(`Estimado/a ${nombre},

          ¡Feliz cumpleaños! 🎉🎂

          En este día tan especial queremos expresarte nuestros mejores deseos. Esperamos que tengas un día maravilloso rodeado de tus seres queridos y que este nuevo año de vida esté lleno de éxitos, salud y felicidad.

          Gracias por ser parte de nuestra familia de clientes. Tu confianza es muy importante para nosotros.

          ¡Que disfrutes mucho tu día!

          Con cariño,
          El equipo`);

        const mailtoUrl = `mailto:${email}?subject=${asunto}&body=${mensaje}`;
        window.location.href = mailtoUrl;
      }
    </script>
  <?php endif; ?>

  <?php
  echo AssetVite::script('shared', [
    'type' => 'module'
  ]);

  // Debug info (solo en desarrollo)
  if (!$isProduction) {
    echo AssetVite::debug();
  }

  ?>
</body>

</html>
<?php
?>