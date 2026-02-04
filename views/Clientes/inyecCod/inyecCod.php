<?php

use PhpOffice\PhpSpreadsheet\Calculation\TextData\Format;

session_start();
include_once '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$codusu = $_SESSION["id"]; // ID DE USUARIO
$condi = $_POST["condi"]; //CONDICION QUE SE TIENEN QUE EJECUTAR
$data = $_POST["extra"];

switch ($condi) {
    case 'huella_registrada':
        ob_start();
        ?>

        <!-- Inicia modal -->
        <!-- INICIO Tabla de nomenclatura -->
        <div class="container mt-3">
            <table class="table" id="r_huellas">
                <thead class="table-dark">
                    <tr>
                        <th hidden>id</th>
                        <th>mano</th>
                        <th>dedo</th>
                        <th>huella</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>

                    <!--Inicio de la tb Modal-->
                    <?php
                    $consulta = mysqli_query($conexion, "SELECT id,mano, dedo, imgHuella FROM huella_digital WHERE id_persona = '$data' AND estado = 1");
                    while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                        // Especificar el nombre y formato del archivo a guardar
                        $image_base64 = $row['imgHuella'];
                        $idHuella = $row['id']
                        ?>
                        <!-- seccion de datos -->
                        <tr>
                            <td hidden><?= $row['id'] ?></td>
                            <td><?= ($row['mano'] == 0) ? "Izquierda" : "Derecha" ?></td>
                            <td>
                                <?= ($row['dedo'] == 1) ? "Pulgar" :
                                    (($row['dedo'] == 2) ? "Índice" :
                                        (($row['dedo'] == 3) ? "Medio" :
                                            (($row['dedo'] == 4) ? "Anular" :
                                                (($row['dedo'] == 5) ? "Meñique" : "-")))) ?>
                            </td>

                            <td>
                                <img id="<?= $row['id'] . 'huella' ?>" src="data:image/png;base64,<?= $image_base64 ?>" width="40" height="50">
                            </td>

                            <td>
                                <!-- <button type="button" class="btn btn-outline-success"><i class="fa-solid fa-eye"></i></button>
                                <button type="button" class="btn btn-outline-primary"><i class="fa-solid fa-pen-to-square"></i></button> -->
                                <button type="button" class="btn btn-outline-danger" onclick="obtiene_plus([],[],[],'eliminaHuella',[],['<?= $idHuella ?>'])"><i class="fa-solid fa-trash-can"></i></button>
                            </td>
                        </tr>
                    <?php } ?>
                    <!--Fin de la tb Modal-->

                </tbody>
            </table>
        </div>
        <!-- Fin modal -->

        <?php
        $output = ob_get_clean();
        echo $output;
        break;
}

?> 