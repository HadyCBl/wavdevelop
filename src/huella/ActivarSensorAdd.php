<?php
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
include_once './bd.php';
$con = new bd($db_host,$db_name,$db_user,$db_password);
$delete = "delete from huella_temp where pc_serial = '" . $_POST['token'] . "'";
$con->exec($delete);
$insert = "insert into huella_temp (fecha_creacion,pc_serial, texto, statusPlantilla, opc) "
        . "values ('$hoy2','" . $_POST['token'] . "', 'El sensor de huella dactilar esta activado', 'Muestras Restantes: 4', 'capturar')";
$row = $con->exec($insert);
$con->desconectar();
echo json_encode("{\"filas\":$row}");
