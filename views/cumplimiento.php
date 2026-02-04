<?php
include __DIR__ . '/../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    return;
}
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

require __DIR__ . '/infoEnti/infoEnti.php';

/* SE AGREGA DE MANERA TEMPORAL */
include __DIR__ . '/../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
/* QUITAR CUANDO YA NO SE NECESITE */

$showmensaje = false;
try {
    $database->openConnection();
    //INFORMACION INSTITUCION
    $infoEnti = infoEntidad($idagencia, $database, $db_name_general);
    $estado = $infoEnti['estado'];
    $fecha_pago = $infoEnti['fecha_pago'];

    //CONSULTA DE PERMISOS DEL USUARIO
    $permisos = getpermisosuser($database, $idusuario, 'G', 2, $db_name_general);

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
$printpermisos = ($status == 1 && $permisos[0] == 1) ? true : false;
$titlemodule = "CUMPLIMIENTO Y VAT";
$idModuleCurrent = 2; // ID DEL MODULO CUMPLIMIENTO
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ahorros</title>
    <link rel="shortcut icon" type="image/x-icon" href="../includes/img/favmicro.ico">

    <link rel="stylesheet" href="../includes/css/style.css">
    <?php require_once __DIR__ . '/../includes/incl.php'; ?>

</head>

<script>
    function printdiv(condi, idiv, dir, xtra) {
        dire = "aho/" + dir + ".php";
        $.ajax({
            url: dire,
            method: "POST",
            data: {
                condi,
                xtra
            },
            success: function(data) {
                $(idiv).html(data);
            }
        })
    }
</script>

<body class="">
    <!-- MENU LATERAL  -->
    <?php
    require __DIR__ . '/../src/menu/menu_bar.php';
    ?>

    <section class="home">
        <div class="container" style="max-width: none !important;">
            <?php require __DIR__ . '/../src/menu/menu_barh.php'; ?>

            <div class="btn-group" id="nav_group" role="group">
                <div class="btn-group me-1" role="group">
                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Reportes
                        <span class="caret"></span></button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`mayores_prestamos`, `#cuadro`, `rep001`, `0`)">Mayores cuentas de préstamos</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`monto_morosidad`, `#cuadro`, `rep002`, `0`)">Monto de morosidad</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`morosidad_directivos`, `#cuadro`, `rep003`, `0`)">Morosidad de Directivos</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`morosidad_empleados`, `#cuadro`, `rep004`, `0`)">Morosidad de Empleados</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`bienes_mayor_valor`, `#cuadro`, `rep005`, `0`)">Bienes extraordinarios de mayor valor</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`bajas_bienes`, `#cuadro`, `rep006`, `0`)">Bajas en bienes (muebles e inmuebles)</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`altas_bienes`, `#cuadro`, `rep007`, `0`)">Altas en bienes (muebles e inmuebles)</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`ahorro_corriente`, `#cuadro`, `rep008`, `0`)">Mayores cuentas de ahorro corriente</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`ahorro_plazo_fijo`, `#cuadro`, `rep009`, `0`)">Mayores cuentas de ahorro a plazo fijo</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`aportes_extraordinarios`, `#cuadro`, `rep010`, `0`)">Mayores Cuentas en aportaciones extraordinarias</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`gastos_periodo`, `#cuadro`, `rep011`, `0`)">Mayores cuentas de gasto del periodo</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`prestamos_pagar`, `#cuadro`, `rep012`, `0`)">Mayores cuentas de préstamos y cuentas por pagar</a></li>
                        <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`activos_improductivos`, `#cuadro`, `rep013`, `0`)">Activos improductivos</a></li>

                    </ul>
                </div>
                <div class="btn-group me-1" role="group">
                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Cumplimiento
                        <span class="caret"></span></button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`mayores_prestamos`, `#cuadro`, `rep001`, `0`)">Información de asociados</a></li>
                            <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`monto_morosidad`, `#cuadro`, `rep002`, `0`)">Información de productos y servicios</a></li>
                            <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`morosidad_directivos`, `#cuadro`, `rep003`, `0`)">Información de canales de distribución</a></li>
                            <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`morosidad_empleados`, `#cuadro`, `rep004`, `0`)">Información de localización geográfica</a></li>
                            <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`bienes_mayor_valor`, `#cuadro`, `rep005`, `0`)">Información de unidad de cumplimiento</a></li>
                            <li><a class="dropdown-item" style="cursor: pointer;" onclick="printdiv(`bajas_bienes`, `#cuadro`, `rep006`, `0`)">Información del representante legal y consejo de administración</a></li>
                        </ul>

                </div>
            </div>
            <button type="button" class="btn btn-warning" onclick="window.location.reload();">RELOAD <i class="fa-solid fa-arrow-rotate-right"></i> </button>


            <!-- ESPACIOS PARA AGREGAR PANELES -->
            <div id="cuadro">
                <div class="d-flex flex-column h-100">
                    <div class="flex-grow-1">
                        <div class="row align-items-center" style="max-width: none !important; height: calc(75vh) !important;">
                            <div class="row d-flex justify-content-center">
                                <div class="col-auto">
                                    <img src="<?= '..' . $infoEnti['imagenEnti'] ?? '' ?>" alt="" srcset="" width="500">
                                    <p class="displayed text-success text-center" style='font-family: "Garamond", serif;
                      font-weight: bold;
                      font-size: x-large;'> WAVDEVELOP </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div class="loader-container loading--show">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>

    <script src="../includes/js/script.js"></script>
    <script src="../includes/js/scrpt_aho.js"></script>
</body>

</html>
<?php
?>