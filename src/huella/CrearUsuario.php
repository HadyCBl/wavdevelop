<?php

include_once './bd.php';
$con = new bd($db_host,$db_name,$db_user,$db_password);
$foto = null;
$ext = null;

$hoy2= date("Y-m-d H:i:s");

$insertHuella = "INSERT INTO huella_digital (id_persona, mano, dedo, imgHuella, huella, estado,created_at)
VALUES (
    '" . $_POST['codCli'] . "',
    '" . $_POST['mano']. "',
    '" . $_POST['dedo'] . "',
    (SELECT imgHuella FROM huella_temp WHERE pc_serial = '" . $_POST['token'] . "'),
    (SELECT huella FROM huella_temp WHERE pc_serial = '" . $_POST['token'] . "'),
    1,
    '" . $hoy2 . "'
)";

$row = $con->exec($insertHuella);

$delete = "delete from huella_temp where pc_serial = '" . $_POST['token'] . "'";

$rowd = $con->exec($delete);

$con->desconectar();
echo json_encode("{\"filas\":$row}");
