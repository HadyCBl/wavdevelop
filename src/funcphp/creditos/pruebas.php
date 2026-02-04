<?php
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
include __DIR__ . '/../../../src/funcphp/creditos/PaymentManager.php';

use Creditos\Utilidades\PaymentManager;

$codigoCuenta = "0020010100000078";
$idproducto = 19;

$utilidadesCreditos = new PaymentManager();

$showmensaje = false;
try {
    $database->openConnection();

    $codigoCuentaGenerado = $utilidadesCreditos->generateAccountCode($database, 1, '02');
    echo "Código de cuenta: $codigoCuentaGenerado\n";

    echo "\n GastosCuota \n";

    $gastosCuota = $utilidadesCreditos->gastosCuota($idproducto, $codigoCuenta, $database);
    echo ('<pre>');
    print_r($gastosCuota);
    echo ('</pre>');

    echo "Dias laborales \n";

    $diasLaborales = $utilidadesCreditos->dias_habiles($database, $idproducto);
    echo ('<pre>');
    print_r($diasLaborales);
    echo ('</pre>');

    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}
if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
  }