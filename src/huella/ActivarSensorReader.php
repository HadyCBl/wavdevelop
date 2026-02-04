<?php
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
include_once './bd.php';
$con = new bd($db_host,$db_name,$db_user,$db_password);
$delete = "delete from huella_temp where pc_serial = '" . $_GET['token'] . "'";
$con->exec($delete);
$insert = "insert into huella_temp (fecha_creacion,pc_serial, texto, opc) "
        . "values ('$hoy2','" . $_GET['token'] . "', 'El sensor de huella dactilar esta activado','leer')";
$con->exec($insert);
$con->desconectar();
//header("Location: ../verificar.php?token=" . $_GET['token']);