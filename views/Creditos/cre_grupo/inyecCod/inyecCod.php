<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
// include '../../src/funcphp/func_gen.php';
//include '../../src/funcphp/fun_ppg.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$usuario = $_SESSION["id"];

$condi = $_POST["condi"]; //CONDICION QUE SE TIENEN QUE EJECUTAR

switch ($condi) {
    case 'couFech':
        $codCu = $_POST['extra'];

        //Obtener Codigo de cuenta de uno de los clientes 
        $consulta = mysqli_query($conexion, "SELECT credi.CCODCTA AS codCu, gruCli.Codigo_grupo AS grup
              FROM tb_cliente_tb_grupo AS gruCli 
              INNER JOIN tb_cliente AS cli ON gruCli.cliente_id = cli.idcod_cliente
              INNER JOIN cremcre_meta AS credi ON cli.idcod_cliente = credi.CodCli
              WHERE credi.cestado = 'F' AND gruCli.Codigo_grupo = $codCu[0] AND credi.NCiclo = $codCu[1] AND gruCli.estado = 1 AND credi.TipoEnti = 'GRUP' GROUP BY grup");

        $dato = mysqli_fetch_assoc($consulta);
        $codCu = $dato['codCu'];

        ob_start();
        $consulta = mysqli_query($conexion, "SELECT pagos.dfecven AS fecha, pagos.cnrocuo AS cuota
                                                        FROM  Cre_ppg AS pagos 
                                                        INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA
                                                        WHERE credi.Cestado = 'F'  AND credi.ccodcta = '$codCu'");
        $con = 0;
        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $con++;
?>

            <tr>
                <td id="<?= $con . 'conRow' ?>" hidden>kill</td> <!-- ID -->
                <td name="noCuo[]" id="<?= $row['cuota'] . 'idCon' ?>"> <?= $row['cuota'] ?> </td> <!-- No Cuota -->
                <td><input id="<?= $con . 'fechaP' ?>" type="date" name="fecha[]" class="form-control" value="<?= $row['fecha'] ?>" onchange="validaF()"></td> <!-- Fecha -->
            </tr>

            <?php
        }
        $output = ob_get_clean();
        echo $output;

        break;

    case 'planPagoGru':
        function planPago($conexion, $codCu)
        {
            $consulta1 = mysqli_query($conexion, "SELECT pagos.Id_ppg AS id, credi.NCapDes, pagos.Cestado AS estado, pagos.ncapita, pagos.nintere, pagos.OtrosPagos, pagos.SaldoCapital 
            FROM  Cre_ppg AS pagos 
            INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA
            WHERE credi.Cestado = 'F'  AND credi.ccodcta = '$codCu'");
            return $consulta1;
        }

        $codGru = $_POST['extra'];

        //Obtener Todos los numeros de cuenta 
        $consulta = mysqli_query($conexion, "SELECT credi.CCODCTA AS codCu, cli.short_name AS nombre, credi.NCapDes AS capDes, gru.NombreGrupo AS grupo
                        FROM tb_cliente_tb_grupo AS gruCli 
                        INNER JOIN tb_cliente AS cli ON gruCli.cliente_id = cli.idcod_cliente
                        INNER JOIN cremcre_meta AS credi ON cli.idcod_cliente = credi.CodCli
                        INNER JOIN tb_grupo AS gru ON gruCli.Codigo_grupo = gru.id_grupos
                        WHERE credi.cestado = 'F' AND gruCli.Codigo_grupo = $codGru[0] AND credi.NCiclo = $codGru[1] AND gruCli.estado = 1 AND credi.TipoEnti = 'GRUP';");

        $error = mysqli_error($conexion);

        $con = 0;
        ob_start();

        while ($rowData = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) { //Extraer cada nnumero de cuenta de forma individual

            $codCu = $rowData['codCu'];
            $consulta1 = planPago($conexion, $codCu);

            if ($con == 0) {
            ?>
                <div class="carousel-item active">

                    <!-- INI TABAL -->

                    <!-- INICIO DE LA TABLA -->
                    <div class="container table-responsive">
                        <table class="table" id="<?= $rowData['codCu'] ?>" name="tbCodCu[]">

                            <div class="row">
                                <div class="col-lg-8">
                                    <label><b>Cuenta:</b> </label> <label id="<?= 'codCu' . $rowData['codCu'] ?>"> <?= $rowData['codCu'] ?> <b>Capital desembolsado: Q </b></label> <label id="<?= 'capDes' . $rowData['codCu'] ?>"> <?= $rowData['capDes'] ?></label><br>
                                    <label><b>Cliente:</b> <?= $rowData['nombre'] ?> </label>
                                </div>
                                <div class="col-lg-4">
                                    <button id="gPDF" type="button" class="btn btn-outline-danger" onclick="if(validaCliCod()==0)return; reportes([[],[],[],['<?= $rowData['nombre'] ?>','<?= $rowData['codCu'] ?>','<?= $usuario ?>','<?= $rowData['grupo'] ?>']],'pdf','editPlanPagosGru',0)">Pagos por cliente <i class="fa-solid fa-file-pdf"></i></button>
                                </div>
                            </div>

                            <thead class="table-dark">
                                <tr>
                                    <th class="col-1">Estado</th>
                                    <th class="col-2">Capital</th>
                                    <th class="col-2">Interes</th>
                                    <th class="col-2">Otros pagos</th>
                                    <th class="col-2">S. Capital</th>
                                    <th class="col-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- INI -->
                                <?php
                                $aux = 0;
                                $flag = true;
                                $con1 = 1;
                                while ($row = mysqli_fetch_array($consulta1, MYSQLI_ASSOC)) {
                                    if ($flag) {
                                        $aux = $rowData['capDes'];
                                        $flag = false;
                                    }
                                    $aux = bcdiv(($aux - $row['ncapita']), '1', 2);
                                    $auxEstado = ($row['estado'] == "X") ? '<i class="fa-solid fa-money-bill" style="color: #c01111;"></i>' : '<i class="fa-solid fa-money-bill" style="color: #178109;"></i>';

                                ?>
                                    <tr>
                                        <td id="<?= $con1 . 'idData' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'idPP[]' ?>" hidden><?= $row['id'] ?></td> <!-- ID -->
                                        <td><?= $auxEstado ?></td> <!-- Estado -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'cap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'capita[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['ncapita'] ?>"></td> <!-- Capital -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'inte' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'interes[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['nintere'] ?>" min="0"></td> <!-- Interes -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'otros' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'otrosP[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['OtrosPagos'] ?>" min="0"></td> <!-- Otros -->
                                        <td id="<?= $con1 . 'salCap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'saldoCap[]' ?>"> <?= $aux ?> </td> <!-- Saldo Capital -->
                                        <td id="<?= $con1 . 'total' . $rowData['codCu'] ?>"><?= ($row['ncapita'] + $row['nintere'] + $row['OtrosPagos']) ?></td> <!-- Total -->
                                    </tr>
                                <?php
                                    $con1++;
                                }
                                ?>
                                <!-- FIN -->
                            </tbody>

                        </table>
                    </div>
                    <!-- FIN DE LA TABLA -->

                    <!-- FIN TABLA -->

                </div>
            <?php
            } else {
            ?>
                <div class="carousel-item">

                    <!-- INI TABAL -->

                    <!-- INICIO DE LA TABLA -->
                    <div class="container table-responsive">

                        <table class="table" id="<?= $rowData['codCu'] ?>" name="tbCodCu[]">

                            <div class="row">
                                <div class="col-lg-8">
                                    <label><b>Cuenta:</b> </label> <label id="<?= 'codCu' . $rowData['codCu'] ?>"> <?= $rowData['codCu'] ?> <b>Capital desembolsado: Q </b></label> <label id="<?= 'capDes' . $rowData['codCu'] ?>"> <?= $rowData['capDes'] ?></label><br>
                                    <label><b>Cliente:</b> <?= $rowData['nombre'] ?> </label>
                                </div>
                                <div class="col-lg-4">
                                    <button id="gPDF" type="button" class="btn btn-outline-danger" onclick="if(validaCliCod()==0)return; reportes([[],[],[],['<?= $rowData['nombre'] ?>','<?= $rowData['codCu'] ?>','<?= $usuario ?>','<?= $rowData['grupo'] ?>']],'pdf','editPlanPagosGru',0)">Pagos por cliente<i class="fa-solid fa-file-pdf"></i></button>
                                </div>
                            </div>

                            <thead class="table-dark">
                                <tr>
                                    <th class="col-1">Estado</th>
                                    <th class="col-2">Capital</th>
                                    <th class="col-2">Interes</th>
                                    <th class="col-2">Otros pagos</th>
                                    <th class="col-2">S. Capital</th>
                                    <th class="col-2">Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <!-- INI -->
                                <?php
                                $aux = 0;
                                $flag = true;
                                $con1 = 1;
                                while ($row = mysqli_fetch_array($consulta1, MYSQLI_ASSOC)) {
                                    if ($flag) {
                                        $aux = $rowData['capDes'];
                                        $flag = false;
                                    }
                                    $aux = bcdiv(($aux - $row['ncapita']), '1', 2);
                                    $auxEstado = ($row['estado'] == "X") ? '<i class="fa-solid fa-money-bill" style="color: #c01111;"></i>' : '<i class="fa-solid fa-money-bill" style="color: #178109;"></i>';

                                ?>
                                    <tr>
                                        <td id="<?= $con1 . 'idData' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'idPP[]' ?>" hidden><?= $row['id'] ?></td> <!-- ID -->
                                        <td><?= $auxEstado ?></td> <!-- Estado -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'cap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'capita[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['ncapita'] ?>"></td> <!-- Capital -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'inte' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'interes[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['nintere'] ?>" min="0"></td> <!-- Interes -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'otros' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'otrosP[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['OtrosPagos'] ?>" min="0"></td> <!-- Otros -->
                                        <td id="<?= $con1 . 'salCap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'saldoCap[]' ?>"> <?= $aux ?> </td> <!-- Saldo Capital -->
                                        <td id="<?= $con1 . 'total' . $rowData['codCu'] ?>"> <?= ($row['ncapita'] + $row['nintere'] + $row['OtrosPagos']) ?></td> <!-- Total -->
                                    </tr>
                                <?php
                                    $con1++;
                                }
                                ?>
                                <!-- FIN -->
                            </tbody>

                        </table>
                    </div>
                    <!-- FIN DE LA TABLA -->

                    <!-- FIN TABLA -->

                </div>
            <?php
            }
            $con++;
        }
        $output = ob_get_clean();
        echo $output;

        break;

    case 'listaDeCuetas':
        $codGru = $_POST['extra'];

        //Obtener informacion de las cuenta, cliente y cuenta...
        $consulta = mysqli_query($conexion, "SELECT credi.CCODCTA AS codCu, cli.short_name AS nombre
                            FROM tb_cliente_tb_grupo AS gruCli 
                            INNER JOIN tb_cliente AS cli ON gruCli.cliente_id = cli.idcod_cliente
                            INNER JOIN cremcre_meta AS credi ON cli.idcod_cliente = credi.CodCli
                            WHERE credi.cestado = 'F' AND gruCli.Codigo_grupo = $codGru[0] AND credi.NCiclo = $codGru[1] AND gruCli.estado = 1 AND credi.TipoEnti = 'GRUP';");

        $error = mysqli_error($conexion);

        $con = 0;
        ob_start();
        while ($rowData = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            ?>
            <li id="<?= $con . 'infoLI' ?>" class="list-group-item list-group-item-action" data-bs-toggle="list" role="tab" data-slide-to="<?= $con ?>" onclick="info('<?= $con ?>');">
                <label><b>
                        <h6>Cli:
                    </b><?= $rowData['nombre'] ?></h6></label><br>
                <label><b>
                        <h6>Cuenta:
                    </b><?= $rowData['codCu'] ?></h6></label><br>
            </li>
<?php
            $con++;
        }

        $output = ob_get_clean();
        echo $output;

        break;
}

?>