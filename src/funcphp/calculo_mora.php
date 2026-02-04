<?php
include_once '../../includes/BD_con/db_con.php';
//curl -s "https://beta1.sotecprotech.com/MicroSystem/src/funcphp/calculo_mora.php" > /dev/null
//curl -s "https://app.sotecprotech.com/fape/src/funcphp/calculo_mora.php" > /dev/null
//curl -s "https://app.sotecprotech.com/crediprendas/src/funcphp/calculo_mora.php" > /dev/null
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
//ACTUALIZACION DE LA MORA
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
