<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala'); 
$condi = $_POST["condi"];
$idusuario = $_SESSION['id'];

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

switch ($condi) {
case 'lib':

//consultas
$ccodaho = $_POST["cod"];

//tipo de cuenta
$numfront=0;
$numdors=0;
$inifront=0;
$inidors=0;

$tipdat = mysqli_query($conexion, "SELECT `numfront`,`numdors`,`front_ini`,`dors_ini` FROM `ahomtip` WHERE `ccodtip`=SUBSTR('$ccodaho',7,2)");
while ($fi = mysqli_fetch_array($tipdat, MYSQLI_ASSOC)) {
    $numfront=($fi["numfront"]);
    $numdors=($fi["numdors"]);
    $inifront=($fi["front_ini"]);
    $inidors=($fi["dors_ini"]);
}


//
$libreta=0;
$datalib = mysqli_query($conexion, "SELECT `nlibreta` FROM `ahomcta` WHERE `ccodaho`=$ccodaho");
while ($rowlib = mysqli_fetch_array($datalib, MYSQLI_ASSOC)) {
    $libreta=($rowlib["nlibreta"]);
}
//------traer el saldo de las anterior libretas
$monto=0;
$saldoant=0;
$data = mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `ahommov` WHERE cestado!=2 AND `ccodaho`='$ccodaho' AND `nlibreta`!=$libreta");
while ($row = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
    $tiptr=$row["ctipope"];
    $monto = $row["monto"];
    if($tiptr=="R"){
        $saldoant=$saldoant-$monto;
    }
    if($tiptr=="D"){
        $saldoant=$saldoant+$monto;
    }
}
//traer el saldo actual en la libreta
$saldoact=0;
$dataact =  mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `ahommov` WHERE cestado!=2 AND `ccodaho`='$ccodaho' AND `nlibreta`=$libreta AND `lineaprint`='S'");
while ($fil = mysqli_fetch_array($dataact, MYSQLI_ASSOC)) {
    $tiptr=$fil["ctipope"];
    $monto = $fil["monto"];
    if($tiptr=="R"){
        $saldoact=$saldoact-$monto;
    }
    if($tiptr=="D"){
        $saldoact=$saldoact+$monto;
    }
}
$saldo=$saldoant+$saldoact;
//
$confirma="0";
$array[]=[];
$noprint =  mysqli_query($conexion, "SELECT `dfecope`,`cnumdoc`,`ctipope`,`monto`,`numlinea`,`correlativo`,saldo_ahorro(ccodaho, dfecope ,correlativo) AS saldo FROM `ahommov` WHERE cestado!=2 AND `ccodaho`='$ccodaho' AND `nlibreta`=$libreta AND `lineaprint`='N'");
$tamanio_linea = 4; //altura de la linea/celda
$lineas=[];
$i=0;
while ($fila = mysqli_fetch_array($noprint, MYSQLI_ASSOC)) {
    $array[$i] = $fila;
    $i++;
    $confirma="1";
}
echo json_encode([[$numfront,$numdors,$inifront,$inidors,$saldo],$array,$confirma]);

//en caso sea admi omitir xd mas que todo es para pruebas
if ($idusuario != 4) {
    if ($confirma == "1") {
        $query = mysqli_query($conexion, "UPDATE `ahommov` SET `lineaprint` = 'S' WHERE `ccodaho`='$ccodaho' AND `nlibreta`=$libreta AND `lineaprint`='N'");
        if ($query) {
            //echo json_encode(['Registro actualizado correctamente ','1']);
        } else {
            //echo json_encode(['Error al actualizar ','0']);
        }
    }
}

mysqli_close($conexion);
break;
}