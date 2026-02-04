<?php

use PhpOffice\PhpSpreadsheet\Calculation\TextData\Format;

session_start();
include_once '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$codusu = $_SESSION["id"]; // ID DE USUARIO
$condi = $_POST["condi"]; //CONDICION QUE SE TIENEN QUE EJECUTAR
$data = $_POST["extra"];

switch ($condi) {
    case 'modal_aho_plz':
        ob_start();
?>

        <!-- Inicia modal -->
        <div class="modal fade" id="aho_plz_fijo" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog  modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalLabel">Cuentas de ahorros de plazo fijo</h1>
                        <input type="hidden" id="id_modal_hidden" value="" readonly>
                    </div>
                    <div class="modal-body">
                        <!-- INICIO Tabla de nomenclatura -->
                        <div class="container mt-3">
                            <h2>Registros </h2>
                            <table class="table" id="tb_aho_plz">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Cuenta de ahorro</th>
                                        <th>Tipo de cuenta</th>
                                        <th>Descripción</th>
                                        <th>Saldo</th>
                                        <th>Accion</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <!--Inicio de la tb Modal-->
                                    <?php
                                    $consulta = mysqli_query($conexion, "SELECT cta.ccodaho AS cuenta, tip.nombre AS tipoCuenta, tip.cdescripcion AS descrip,
                                    ((SELECT ifnull(SUM(monto),0) FROM ahommov WHERE ccodaho=cta.ccodaho AND ctipope='D' AND cestado!=2)-
                                    IFNULL((SELECT SUM(monto) FROM ahommov WHERE ccodaho=cta.ccodaho AND ctipope='R' AND cestado!=2),0)
                                    ) AS saldo 
                                    FROM ahomcta cta
                                    INNER JOIN ahomtip tip ON substring(cta.ccodaho,7,2) = tip.ccodtip
                                     WHERE tip.tipcuen = 'pf' AND cta.ccodcli = '$data'");
                                    while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                        $data = $row['tipoCuenta'] . "||" .
                                            $row['cuenta'] . "||" .
                                            $row['saldo'] . "||" .
                                            '0' . "||" . '0';
                                    ?>
                                        <!-- seccion de datos -->
                                        <tr>
                                            <td><?= $row['cuenta'] ?></td>
                                            <td><?= $row['tipoCuenta'] ?></td>
                                            <td><?= $row['descrip'] ?></td>
                                            <td><?= $row['saldo'] ?></td>
                                            <td>
                                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="capData('<?= $data ?>',['#input_aho_plz','#descrip','#monntoGra','#valorComer','#montoAvaluo'])">Seleccionar</button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <!--Fin de la tb Modal-->

                                </tbody>
                            </table>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="cerrar_modal_cualquiera('#aho_plz_fijo')">Cerrar</button>
                        </div>

                    </div>
                </div>
            </div>
            <!-- Fin modal -->

        <?php
        $output = ob_get_clean();
        echo $output;
        break;

    case 'consulta_cre':
        ob_start();
        ?>
            <!-- Inicia modal -->
            <div class="modal fade" id="mdl_consulta_cre" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog  modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title fs-5" id="exampleModalLabel">Creditos activos</h3>

                        </div>
                        <div class="modal-body">
                            <!-- INICIO Tabla de nomenclatura -->
                            <div class="container mt-3">
                                <h3>Registros </h3>
                                <table class="table" id="table_cre">
                                    <thead class="table-dark">
                                        <tr>
                                            <th scope="col">Cliente</th>
                                            <th scope="col">Cuenta</th>
                                            <th scope="col">Ciclo</th>
                                            <th scope="col">Monto</th>
                                            <th scope="col">Accion</th>

                                        </tr>
                                    </thead>
                                    <tbody>

                                        <!--Inicio de la tb Modal-->
                                        <?php
                                        $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA ,cm.NCiclo ,cm.DFecDsbls , cm.NCapDes ,tc.short_name,
                                        (IFNULL((SELECT MAX(dfecven) FROM Cre_ppg WHERE cestado = 'P' AND ccodcta = cm.CCODCTA),'0000-00-00')) fecUltimoPag,
                                        (cm.NCapDes - (IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE CTIPPAG = 'P' AND CCODCTA = cm.CCODCTA AND CESTADO!='X'),0))) saldo, 
                                        (SELECT MIN(dfecven) FROM Cre_ppg WHERE cestado = 'X' AND ccodcta = cm.CCODCTA) fecSigPag,
                                        cp.cod_producto ,cp.nombre ,cp.tasa_interes , cp.descripcion , cp.monto_maximo , cff. descripcion fondo, cp.id
                                        FROM cremcre_meta cm 
                                        INNER JOIN tb_cliente tc ON tc.idcod_cliente = cm.CodCli 
                                        INNER JOIN cre_productos cp  ON  cm.CCODPRD = cp.id 
                                        INNER JOIN ctb_fuente_fondos cff ON cp.id_fondo = cff.id 
                                        WHERE Cestado = 'F'");

                                        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            //$row['NCapDes'] = number_format(($row['NCapDes']),2,'.',',');
                                            $des = number_format($row['NCapDes'],2,'.',',');
                                            $sal = number_format($row['saldo'],2,'.',',');
                                            $row['des'] = $des; //15
                                            $row['sal'] = $sal; //16
                                            $data = implode("||", $row);
                                        ?>
                                            <!-- seccion de datos -->
                                            <tr>
                                                <td><?= $row['short_name'] ?></td>
                                                <td><?= $row['CCODCTA'] ?></td>
                                                <td><?= $row['NCiclo'] ?></td>
                                                <td><?= number_format($row['NCapDes'],2,'.',',') ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal" onclick="capDataEsp('<?= $data ?>', ['#codCre','#ciclo','#fecDes','#desembolso','#cliente','#fecUltPago','#saldo', '#salRestruturacion','#fecSigPago','#codProducto','#nomProducto','#interes','#descript','#montoMax','#tipFondo','#idProduc'], [0,1,2,15,4,5,16,6,7,8,9,10,11,12,13,14])">Seleccionar</button>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        <!--Fin de la tb Modal-->

                                    </tbody>
                                </table>

                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="cerrar_modal_cualquiera('#mdl_consulta_cre')">Cerrar</button>
                            </div>

                        </div>
                    </div>
                </div>
                <!-- Fin modal -->
                <script>
                    convertir_tabla_a_datatable('table_cre');
                </script>
            <?php
            $output = ob_get_clean();
            echo $output;
            break;

        case 'cre_productos':
            ob_start();
            ?>
                <!-- Inicia modal -->
                <div class="modal fade" id="mdl_cre_producto" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                    <div class="modal-dialog  modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title fs-5" id="exampleModalLabel">Creditos activos</h3>

                            </div>
                            <div class="modal-body">
                                <!-- INICIO Tabla de nomenclatura -->
                                <div class="container mt-3">
                                    <h3>Registros </h3>
                                    <table class="table" id="table_cre_producto">
                                        <thead class="table-dark">
                                            <tr>
                                                <th scope="col">Producto</th>
                                                <th scope="col">Monto Maximo</th>
                                                <th scope="col">Interes</th>
                                                <th scope="col">Tipo de Fondo</th>
                                                <th scope="col">Accion</th>

                                            </tr>
                                        </thead>
                                        <tbody>

                                            <!--Inicio de la tb Modal-->
                                            <?php
                                            $consulta = mysqli_query($conexion, "SELECT cp.cod_producto , cp.nombre , cp.tasa_interes , cp.descripcion, cp.monto_maximo , cff.descripcion tipFond, cp.id FROM cre_productos cp 
                                            INNER JOIN ctb_fuente_fondos cff ON cff.id = cp.id_fondo
                                            WHERE cp.estado = 1");

                                            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                $data = implode("||", $row);
                                            ?>
                                                <!-- seccion de datos -->
                                                <tr>
                                                    <td><?= $row['nombre'] ?></td>
                                                    <td><?= $row['monto_maximo'] ?></td>
                                                    <td><?= $row['tasa_interes'] ?></td>
                                                    <td><?= $row['tipFond'] ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal" onclick="capDataEsp('<?= $data ?>', ['#codProducto','#nomProducto','#interes','#descript','#montoMax','#tipFondo','#idProduc'],[0,1,2,3,4,5,6])">Seleccionar</button>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                            <!--Fin de la tb Modal-->

                                        </tbody>
                                    </table>

                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="cerrar_modal_cualquiera('#mdl_cre_producto')">Cerrar</button>
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- Fin modal -->
                    <script>
                        convertir_tabla_a_datatable('table_cre_producto');
                    </script>
            <?php
            $output = ob_get_clean();
            echo $output;
            break;
    }

            ?>