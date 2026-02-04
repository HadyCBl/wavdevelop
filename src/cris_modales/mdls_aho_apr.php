<?php
session_start();
include_once '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST['condi'];
$extra = $_POST['extra'];

switch ($condi) {
    case "cu_aho":
    case 'modal_aho_plz':
        $data = $conexion->query("SELECT tc.short_name as cli, a.ccodaho AS cu, tip.nombre as tip_cu FROM ahomcta a 
        INNER JOIN tb_cliente tc ON tc.idcod_cliente = a.ccodcli 
        INNER JOIN ahomtip tip ON tip.ccodtip = (SUBSTRING(a.ccodaho,7,2))
        WHERE a.estado  = 'A' AND a.ccodcli = $extra");

        ob_start();
?>
        <div class="card contenedort mt-2" style="max-width: 100% !important;">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-5">
                        <h5>Seleccione una cuenta de ahorro</h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <table class="table table-success table-striped">
                        <thead class="table-success">
                            <th scope="row">Cliente</th>
                            <th scope="row">Cuenta de ahorro</th>
                            <th scope="row">Tipo de cuenta</th>
                            <th scope="row">Check</th>

                        </thead>
                        <tbody>
                            <?php
                            while ($row = $data->fetch_assoc()) {
                            ?>

                                <tr style="cursor: pointer;" id="<?= $row['cu'] ?>">
                                    <td><?= $row['cli'] ?></td>
                                    <td><?= $row['cu'] ?></td>
                                    <td><?= $row['tip_cu'] ?></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" value="<?= $row['cu'] ?>" name="cu_aho_apr" id="<?= $row['cu'] ?>" onclick="selec_cu()">
                                            <label class="form-check-label" for="<?= $row['cu'] ?>">
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php
        $output = ob_get_clean();
        echo $output;
        break;
    case "cu_apr":
        $data = $conexion->query("SELECT tc.short_name as cli, a.ccodaport AS cu, tip.nombre as tip_cu FROM aprcta a 
        INNER JOIN tb_cliente tc ON tc.idcod_cliente = a.ccodcli 
        INNER JOIN aprtip tip ON tip.ccodtip = (SUBSTRING(a.ccodaport,7,2))
        WHERE a.estado  = 'A' AND a.ccodcli = $extra");

        ob_start();
    ?>
        <div class="card contenedort mt-2" style="max-width: 100% !important;">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-5">
                        <h5>Seleccione una cuenta de aportación</h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <table class="table table-success table-striped">
                        <thead class="table-success">
                            <th scope="row">Cliente</th>
                            <th scope="row">Cuenta de ahorro</th>
                            <th scope="row">Tipo de cuenta</th>
                            <th scope="row">Check</th>

                        </thead>
                        <tbody>
                            <?php
                            while ($row = $data->fetch_assoc()) {
                            ?>

                                <tr style="cursor: pointer;" id="<?= $row['cu'] ?>">
                                    <td><?= $row['cli'] ?></td>
                                    <td><?= $row['cu'] ?></td>
                                    <td><?= $row['tip_cu'] ?></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" value="<?= $row['cu'] ?>" name="cu_aho_apr" id="<?= $row['cu'] ?>" onclick="selec_cu()">
                                            <label class="form-check-label" for="<?= $row['cu'] ?>">
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php
        $output = ob_get_clean();
        echo $output;
        break;
}

?>