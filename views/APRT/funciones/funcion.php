<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 0]);
    return;
}
//Nueva Conexion
//NUEVA CONEXION
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

//Antigua Conexion
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

include __DIR__ . '/../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$idusuario = $_SESSION["id"];

$condi = (isset($input["condi"])) ? $input["condi"] : ((isset($_POST["condi"]) ? $_POST["condi"] : 0));
$agencia = $_SESSION['agencia'];
$institucion = $_SESSION['agencia'];
switch ($condi) {
    case 'cargarcuentas':
        if (!isset($_POST['xtra']) || empty($_POST['xtra'])) {
            echo json_encode(['status' => 'error', 'message' => 'ID del cliente no proporcionado']);
            exit;
        }
        
        $idcli = intval($_POST['xtra']); 

        $sq = mysqli_query($conexion, "SELECT aprcta.ccodaport, aprcta.ccodcli, aprcta.estado, 
					(SELECT aprtip.nombre FROM aprtip WHERE aprtip.ccodtip = SUBSTRING(aprcta.ccodaport FROM 7 FOR 2)) AS nombre
					FROM aprtip
					INNER JOIN aprcta
					WHERE aprcta.ccodcli = $idcli AND aprtip.ccodage = $agencia
					AND 'Ahorro Vinculado Aportaciones(Interes)' = (SELECT aprtip.nombre FROM aprtip 
                    WHERE aprtip.ccodtip = SUBSTRING(aprcta.ccodaport FROM 7 FOR 2)) AND aprcta.ctainteres IS NULL
					GROUP BY aprcta.ccodaport;");
        

        $cuentasv = [];
        while ($row = mysqli_fetch_assoc($sq)) {
            $cuentasv[] = $row; // Agregar cada fila al array
        }
        if (!empty($cuentasv)) {
            $response = [
                'status' => 'success', // Añadir un estado de éxito
                'cuentas' => $cuentasv,
            ];
        }else{
            $response = [
                'status' => 'success', // Añadir un estado de éxito
                'cuentas' => 'NO SE TIENEN CUENTAS ASOCIADAS',
            ];
        }
        
        echo json_encode($response);
        break;
    case 'newcorrela':
        $nombre= "Ahorro Vinculado Aportaciones(Interes)";
        $database->openConnection();
        $query = "SELECT ccodtip FROM aprtip WHERE nombre = :nombre AND ccodage =:ccodage";
        $params = ['nombre' => $nombre,
                    'ccodage' => $agencia];
        try {
            $database->openConnection();
            $resultados = $database->executeQuery($query, $params);
           
            if (!empty($resultados)) {
                foreach ($resultados as $us) {
                    $cod = $us['ccodtip'];
                }
            }
            $response = [
                'status' => 'success', // Añadir un estado de éxito
                'codigo' => $cod,
                'agencia' =>$agencia,
            ];
            echo json_encode($response);
        } catch (Exception $e) {
            $mensaje = "Error: " . $e;
        } finally {
            $database->closeConnection();
        }
        break;
    case 'cargarproductos':
        $sq = mysqli_query($conexion, "SELECT nombre, ccodtip FROM aprtip WHERE ccodage = $agencia ;");
        $cuentasv = [];
        while ($row = mysqli_fetch_assoc($sq)) {
            $cuentasv[] = $row; // Agregar cada fila al array
        }
        if (!empty($cuentasv)) {
            $response = [
                'status' => 'success', // Añadir un estado de éxito
                'cuentas' => $cuentasv,
            ];
        }else{
            $response = [
                'status' => 'success', // Añadir un estado de éxito
                'cuentas' => 'NO SE TIENEN CUENTAS ASOCIADAS',
            ];
        }
        
        echo json_encode($response);
        break;
    }
?>