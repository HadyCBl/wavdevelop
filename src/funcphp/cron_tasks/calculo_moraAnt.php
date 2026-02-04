<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
include_once '../../../includes/BD_con/db_con.php';
//curl -s "https://beta1.sotecprotech.com/MicroSystem/src/funcphp/calculo_mora.php" > /dev/null
//curl -s "https://app.sotecprotech.com/fape/src/funcphp/calculo_mora.php" > /dev/null
//curl -s "https://app.sotecprotech.com/crediprendas/src/funcphp/calculo_mora.php" > /dev/null
$hoy = date("Y-m-d");
$datos[] = [];
$datacre = mysqli_query($conexion, 'SELECT cre.CCODCTA From cremcre_meta cre WHERE cre.CESTADO="F"');
$i = 0;
while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
    $datos[$i] = $da;
    $i++;
}
//ACTUALIZACION EN LA CREPPG
foreach ($datos as $dat) {
    $cuenta = $dat["CCODCTA"];
    $res = $conexion->query("CALL update_ppg_account('" . $cuenta . "')");
    if (!$res) {
        echo 'Fallo la actualizacion del plan de pago de la cuenta: ' . $cuenta;
        $conexion->rollback();
        return;
    } else {
        echo 'ACTUALIZACION DE PAGOS DE LA CUENTA: ' . $cuenta . ', LISTO :)';
        echo '<br>';
    }
}
echo '<br>';
//ACTUALIZACION DE LA MORA de
foreach ($datos as $dat) {
    $cuenta = $dat["CCODCTA"];
    $res = $conexion->query("SELECT calculo_mora('" . $cuenta . "')");
    if (!$res) {
        echo 'Error en la de actualizacion de la cuenta: ' . $cuenta;
        $conexion->rollback();
        return;
    } else {
        echo 'MORA DE LA CUENTA: ' . $cuenta . ', LISTO :)';
        echo '<br>';
    }
}
echo '<br>';
//ACTUALIZACION DE VERIFICACION KARDEX
foreach ($datos as $dat) {
    $cuenta = $dat["CCODCTA"];
    $res = $conexion->query("CALL verificacion_kardex('" . $cuenta . "','" . $hoy . "')");
    if (!$res) {
        echo 'Error en la de actualizacion del historial de la : ' . $cuenta;
        $conexion->rollback();
        return;
    } else {
        echo 'kardex DE LA CUENTA: ' . $cuenta . ', LISTO :)';
        echo '<br>';
    }
}