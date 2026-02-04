<?php
//VERIFICAR SI TIENE EL PARAMETRO EN LA URL
if (!isset($_GET['test']) || $_GET['test'] !== 'soygay') {
    echo "🖕";
    return false;
}

//EJEMPLO DE USO
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';


use App\Generic\Models\PlazoFijoService;

$servicio = new PlazoFijoService(
    '2023-01-01',
    '2024-01-01',
    10000,
    5,
    'M',
    365,
    731,
    0
);


$planPagos = $servicio->generatePpg();


echo ('<pre>');
print_r($planPagos);
echo ('</pre>');
