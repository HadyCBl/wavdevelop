<?php
session_start();
include_once '../../includes/Config/config.php';
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    die();
    exit();
} else {
    include '../../includes/BD_con/db_con.php';
    mysqli_set_charset($conexion, 'utf8');
    mysqli_set_charset($general, 'utf8');
    date_default_timezone_set('America/Guatemala');

    // { changed code: se añaden consultas para obtener estadísticas }
    $sqlNat = "SELECT COUNT(*) as total_nat FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'natural'";
    $resNat = $conexion->query($sqlNat);
    $naturalCount = $resNat->fetch_assoc()['total_nat'] ?? 0;

    $sqlJur = "SELECT COUNT(*) as total_jur FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'juridico'";
    $resJur = $conexion->query($sqlJur);
    $juridicoCount = $resJur->fetch_assoc()['total_jur'] ?? 0;

    $sqlGroup = "SELECT COUNT(DISTINCT cliente_id) as total_group FROM tb_cliente_tb_grupo";
    $resGroup = $conexion->query($sqlGroup);
    $groupCount = $resGroup->fetch_assoc()['total_group'] ?? 0;

    $sqlGroupDetails = "SELECT tb_cliente_tb_grupo.Codigo_grupo, tb_grupo.NombreGrupo, COUNT(tb_cliente_tb_grupo.cliente_id) as total 
        FROM tb_cliente_tb_grupo 
        INNER JOIN tb_grupo 
        ON tb_cliente_tb_grupo.Codigo_grupo = tb_grupo.codigo_grupo 
        WHERE tb_cliente_tb_grupo.estado = '1'
        GROUP BY tb_cliente_tb_grupo.Codigo_grupo 
        ORDER BY tb_cliente_tb_grupo.Codigo_grupo DESC LIMIT 5";
    $resGroupDetails = $conexion->query($sqlGroupDetails);
    $groupDetails = $resGroupDetails->fetch_all(MYSQLI_ASSOC);

    $sqlRecent = "SELECT primer_name, primer_last, fecha_alta FROM tb_cliente ORDER BY fecha_alta DESC LIMIT 5";
    $resRecent = $conexion->query($sqlRecent);
    $recentClients = $resRecent->fetch_all(MYSQLI_ASSOC);

    $sqlNewNatThisMonth = "SELECT COUNT(*) as new_nat_this_month FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'natural' AND MONTH(fecha_alta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_alta) = YEAR(CURRENT_DATE())";
    $resNewNatThisMonth = $conexion->query($sqlNewNatThisMonth);
    $newNatThisMonthCount = $resNewNatThisMonth->fetch_assoc()['new_nat_this_month'] ?? 0;

    $sqlNewJurThisMonth = "SELECT COUNT(*) as new_jur_this_month FROM tb_cliente WHERE LOWER(id_tipoCliente) = 'juridico' AND MONTH(fecha_alta) = MONTH(CURRENT_DATE()) AND YEAR(fecha_alta) = YEAR(CURRENT_DATE())";
    $resNewJurThisMonth = $conexion->query($sqlNewJurThisMonth);
    $newJurThisMonthCount = $resNewJurThisMonth->fetch_assoc()['new_jur_this_month'] ?? 0;
    // { end changed code }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <!-- ...existing code... -->
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes</title>
  <link rel="shortcut icon" type="image/x-icon" href="../../includes/img/favmicro.ico">
  <link rel="stylesheet" href="../../includes/css/style.css">
  <link rel="stylesheet" href="../../includes/css/dash.css">

  <?php require_once '../../includes/incl.php'; ?>

  <!-- { end changed code } -->
</head>

<body class="<?= ($_SESSION['background'] == '1') ? 'dark' : ''; ?>">
  <?php
  // ...existing code...
  require '../infoEnti/infoEnti.php';
  ?>

  <section class="home">
    <div class="container" style="max-width: none !important;">
      <div class="row">
        <div class="col d-flex justify-content-start"><div class="text">MODULO DE CLIENTES</div></div>
        <div class="col d-flex justify-content-end"><div class="text"><?= $infoEnti['nomAge'] ?></div></div>
      </div>

      <!-- MENU NAB VAR------------------- -->
      <div class="btn-group" id="nav_group" role="group">
        <!-- ...existing code que imprime el menú con dropdown... -->
        <?php
        $consulta = "SELECT tbp.id_usuario, tbs.id AS menu, tbs.descripcion, tbm.id AS opcion, tbm.condi, tbm.`file`, tbm.caption
        FROM tb_usuario tbu
        INNER JOIN tb_permisos2 tbp ON tbu.id_usu=tbp.id_usuario
        INNER JOIN $db_name_general.tb_submenus tbm ON tbp.id_submenu=tbm.id
        INNER JOIN $db_name_general.tb_menus tbs ON tbm.id_menu =tbs.id
        INNER JOIN $db_name_general.tb_modulos tbo ON tbs.id_modulo =tbo.id
        INNER JOIN $db_name_general.tb_permisos_modulos tbps ON tbo.id=tbps.id_modulo
        WHERE tbu.id_usu=" . $_SESSION['id'] . " AND tbo.estado='1' AND tbs.estado='1' AND tbm.estado='1' AND tbps.estado='1'
        AND tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1)
        AND tbo.rama='G' AND tbo.id=1 ORDER BY tbo.orden, tbs.orden, tbm.orden ASC";

        $valores[] = [];
        $j = 0;
        $resultado = mysqli_query($conexion, $consulta);
        while ($fila = mysqli_fetch_array($resultado, MYSQLI_ASSOC)) {
          $valores[$j] = $fila;
          $j++;
        }
        $bandera = true;
        $bandera2 = false;
        $bandera3 = false;

        for ($i = 0; $i < $j; $i++) {
          if ($i == 0) {
            echo '<div class="btn-group me-1" role="group">
                  <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">'
                  . $valores[$i]['descripcion'] . '
                  <span class="caret"></span></button>
                  <ul class="dropdown-menu">';
            echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`'
              . $valores[$i]['condi'] . '`, `#cuadro`, `../' . $valores[$i]['file'] . '`, `0`)">'
              . $valores[$i]['caption'] . '</a></li>';

            $aux2 = $valores[$i]['menu'];
            $bandera = false;
            $bandera3 = true;
          }

          $aux = $valores[$i]['menu'];
          if ($aux != $aux2) {
            $aux2 = $valores[$i]['menu'];
            $bandera2 = true;
            $bandera3 = false;
            echo '</ul></div>';
            echo '<div class="btn-group me-1" role="group">
                  <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">'
                  . $valores[$i]['descripcion'] . '
                  <span class="caret"></span></button>
                  <ul class="dropdown-menu">';
            echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`'
              . $valores[$i]['condi'] . '`, `#cuadro`, `../' . $valores[$i]['file'] . '`, `0`)">'
              . $valores[$i]['caption'] . '</a></li>';
          } elseif ($aux == $aux2 && $bandera) {
            if (($i + 1) != count($valores)) {
              echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`'
                . $valores[$i]['condi'] . '`, `#cuadro`, `../' . $valores[$i]['file'] . '`, `0`)">'
                . $valores[$i]['caption'] . '</a></li>';
              $bandera2 = false;
              $bandera3 = false;
            }
          }
          if (($i + 1) == count($valores)) {
            if ($bandera2) {
              echo '</ul></div>';
            } else if ($bandera3) {
              echo '</ul></div>';
            } else {
              echo '<li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`'
                . $valores[$i]['condi'] . '`, `#cuadro`, `../' . $valores[$i]['file'] . '`, `0`)">'
                . $valores[$i]['caption'] . '</a></li>';
              echo '</ul></div>';
            }
          }
          $bandera = true;
          $bandera2 = false;
          $bandera3 = false;
        }
        ?>
      </div>
      <button type="button" class="btn btn-warning" onclick="window.location.reload();">
        RELOAD <i class="fa-solid fa-arrow-rotate-right"></i>
      </button>
      <!-- MENU NAB VAR------------------- -->

      <br>

      <!-- { changed code: Reemplaza contenido del id="cuadro" con Dashboard } -->
      <div id="cuadro">
        <div class="d-flex flex-column">
          <div class="dashboard">
            <div class="card fadeIn">
              <div class="card-header">
                <span class="card-title">Clientes Naturales</span>
                <i class="icon-users"></i>
              </div>
              <div class="card-content">
                <?php echo $naturalCount; ?>
                <small>Registrados</small>
                <small>Nuevos este mes: <?php echo $newNatThisMonthCount; ?></small>
              </div>
            </div>
            <div class="card fadeIn">
              <div class="card-header">
                <span class="card-title">Clientes Jurídicos</span>
                <i class="icon-building"></i>
              </div>
              <div class="card-content">
                <?php echo $juridicoCount; ?>
                <small>Registrados</small>
                <small>Nuevos este mes: <?php echo $newJurThisMonthCount; ?></small>
              </div>
            </div>
            <div class="card fadeIn">
              <div class="card-header">
                <span class="card-title">Clientes en Grupo</span>
                <i class="icon-group"></i>
              </div>
              <div class="card-content">
                <?php echo $groupCount; ?>
                <small>Registrados</small>
              </div>
            </div>
            <!-- Sección de gráfico de barras -->
            <div class="card chart fadeIn">
              <div class="card-header">
                <span class="card-title">Distribución de Clientes</span>
              </div>
              <canvas id="clientDistributionChart"></canvas>
            </div>
            <!-- Sección de clientes recientes -->
            <div class="card fadeIn">
              <div class="card-header">
                <span class="card-title">Clientes Recientes</span>
                <i class="icon-clock"></i>
              </div>
              <div class="card-content">
                <ul class="recent-clients">
                  <?php foreach ($recentClients as $client): ?>
                    <li class="recent-client">
                      <span><?php echo $client['primer_name'] . ' ' . $client['primer_last']; ?></span>
                      <span><?php echo $client['fecha_alta']; ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- { end changed code } -->

    </div>
    <!-- AQUI TERMINA EL id="cuadro" ------------------------------------------------ -->
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
  // ...existing code...
  include_once "../../src/cris_modales/mdls_acteconomica.php";
  ?>

  <!-- { changed code: Agregamos Chart.js y ejemplo de script } -->
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
        options: { responsive: true }
      });
    }
  </script>
  <!-- { end changed code } -->

  <script src="../../includes/js/script.js"></script>
  <script src="../../includes/js/scriptsclientes.js"></script>
</body>
</html>
<?php
}
?>