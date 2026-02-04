<?php
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];

switch ($condi) {
    case 'lib':
        //lo que se recibe del post
        $ccodaport = $_POST["cod"];

        //tipo de cuenta
        $numfront = 0;
        $numdors = 0;
        $inifront = 0;
        $inidors = 0;

        $tipdat = mysqli_query($conexion, "SELECT `numfront`,`numdors`,`front_ini`,`dors_ini` FROM `aprtip` WHERE `ccodtip`=SUBSTR('$ccodaport',7,2)");
        while ($fi = mysqli_fetch_array($tipdat, MYSQLI_ASSOC)) {
            $numfront = utf8_encode($fi["numfront"]);
            $numdors = utf8_encode($fi["numdors"]);
            $inifront = utf8_encode($fi["front_ini"]);
            $inidors = utf8_encode($fi["dors_ini"]);
        }

        //
        $libreta = 0;
        $datalib = mysqli_query($conexion, "SELECT `nlibreta` FROM `aprcta` WHERE `ccodaport`='$ccodaport'");
        while ($rowlib = mysqli_fetch_array($datalib, MYSQLI_ASSOC)) {
            $libreta = utf8_encode($rowlib["nlibreta"]);
        }
        //------traer el saldo de las anterior libretas
        $monto = 0;
        $saldoant = 0;
        $data = mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `aprmov` WHERE `ccodaport`='$ccodaport' AND cestado!=2 AND `nlibreta`!=$libreta");
        while ($row = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
            $tiptr = utf8_encode($row["ctipope"]);
            $monto = ($row["monto"]);
            if ($tiptr == "R") {
                $saldoant = $saldoant - $monto;
            }
            if ($tiptr == "D") {
                $saldoant = $saldoant + $monto;
            }
        }
        //traer el saldo actual en la libreta
        $saldoact = 0;
        $dataact =  mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `aprmov` WHERE `ccodaport`='$ccodaport' AND cestado!=2 AND `nlibreta`=$libreta AND `lineaprint`='S'");
        while ($fil = mysqli_fetch_array($dataact, MYSQLI_ASSOC)) {
            $tiptr = utf8_encode($fil["ctipope"]);
            $monto = ($fil["monto"]);
            if ($tiptr == "R") {
                $saldoact = $saldoact - $monto;
            }
            if ($tiptr == "D") {
                $saldoact = $saldoact + $monto;
            }
        }
        $saldo = $saldoant + $saldoact;
        //
        $confirma = "0";
        $array[] = [];
        $noprint =  mysqli_query($conexion, "SELECT `dfecope`,`cnumdoc`,`ctipope`,`monto`,`numlinea`,`correlativo`,saldo_aportacion(aprmov.ccodaport, aprmov.dfecope ,aprmov.correlativo) AS saldo 
        FROM `aprmov` WHERE `ccodaport`='$ccodaport' AND cestado!=2 AND `nlibreta`=$libreta AND `lineaprint`='N'");
        $tamanio_linea = 4; //altura de la linea/celda
        $lineas = [];
        $i = 0;
        while ($fila = mysqli_fetch_array($noprint, MYSQLI_ASSOC)) {
            $array[$i] = $fila;
            $i++;
            $confirma = "1";
        }
        echo json_encode([[$numfront, $numdors, $inifront, $inidors, $saldo], $array, $confirma]);

        if ($confirma == "1") {
            $query = mysqli_query($conexion, "UPDATE `aprmov` set `lineaprint` = 'S' WHERE `ccodaport`='$ccodaport' AND `nlibreta`=$libreta AND `lineaprint`='N'");
            if ($query) {
                //echo json_encode(['Registro actualizado correctamente ','1']);
            } else {
                //echo json_encode(['Error al actualizar ','0']);
            }
        }
        mysqli_close($conexion);
        break;
}
