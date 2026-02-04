<?php
include '../funcphp/func_gen.php';
session_start();

date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");

//*Titulos*/
$title = ["IVE", "AHORRO DE PLAZO FIJO", "Actualizar contraseña"];
//*Condicion de alertas */
$condiXD = ["ive", "aho_pzf", "pass"];
//*Diccionarios de permisos */
$ive = ["ADM", "CNT"];
$aho_pzf = ["GER", "CNT", "CJG", "CAJ", "ADM"];
$pass = ['otr_g'];
$data = [$ive, $aho_pzf, $pass];

//Datos enviados por post
$condi = $_POST["condi"];
// $id = $_SESSION['id'];

switch ($condi) {
    case 'alertas':
        if (isset($_SESSION['id'])) {
            include '../../includes/BD_con/db_con.php';
            $id = $_SESSION['id'];
            mysqli_set_charset($conexion, 'utf8');
            modal($data, $title, $condiXD, $conexion, [$id]);
        } else {
            echo false;
        }
        break;
    case 'dataTablef':
        echo json_encode([$condiXD, '1']);
        return;
        break;
    case 'con_alt':
        if (isset($_SESSION['id'])) {
            include '../../includes/Config/database.php';
            $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
            $numerototal = 0;
            try {
                $database->openConnection();
                $result = $database->getAllResults("SELECT SUM(total_count) AS suma_total 
                                FROM (SELECT COUNT(*) AS total_count FROM tb_alerta WHERE estado = 1 AND tipo_alerta IN ('IVE', 'PASS') AND (puesto=? OR cod_aux=?) UNION ALL
                                SELECT COUNT(*) AS total_count FROM ahomcrt crt INNER JOIN tb_cliente tc ON tc.idcod_cliente = crt.ccodcli 
                                WHERE crt.fec_ven > CURDATE() AND crt.fec_ven < DATE_ADD(CURDATE(), INTERVAL 15 DAY)) AS con",[$_SESSION['puesto'],$_SESSION['id']]);
     
                foreach ($result as $dat) {
                    $numerototal = $dat['suma_total'];
                }
                $status = 1;
            } catch (Exception $e) {
                $codigoError = logerrores($e->getMessage(),__FILE__,__LINE__,$e->getFile(),$e->getLine());
                $status = 0;
            } finally {
                $database->closeConnection();
            }
            echo json_encode([$numerototal, $status]);
        } else {
            echo json_encode(["error", 0]);
        }
        break;
}

function opciones($conexion, $condi, $extra)
{
    switch ($condi) {
        case 'ive':
            //Encabezado de la tabla
            $encabezado = ['Alerta', 'Cod. Cliente', 'Cliente', 'Cuenta', 'Mensaje', 'Fecha', 'Acción'];
            //Data de la tabla 
            ob_start();
            $sql = "SELECT ale.id, cli.idcod_cliente AS cod, ale.tipo_alerta AS alerta, cli.short_name AS cli, ale.cod_aux AS codCu, ale.mensaje AS msj, ale.fecha
            FROM tb_alerta AS ale 
            INNER JOIN ahomcta AS cu ON cu.ccodaho = ale.cod_aux
            INNER JOIN tb_cliente AS cli ON cli.idcod_cliente = cu.ccodcli
            WHERE ale.estado = 1";
            $con = 0;
            foreach (exeQuery($conexion, $sql) as $row) {
                echo "<tr>";
                foreach ($row as $data) {
                    if ($con > 0) {
                        echo '<td>' . $data . '</td>';
                    }
                    $con += 1;
                }
?>
                <td>
                    <button type="button" id="btnSelec" class="btn btn-success" onclick="obtieneAux(['<?= $row['id'] ?>','A'],['<?= $_SESSION['id'] ?>'])"><i class="fa-solid fa-check-to-slot"></i></button>
                </td>
    <?php
                echo "</tr>";
                $con = 0;
            }
            $data = ob_get_clean();
            //Funcion que se encargar de juntar la inf. de la tabla 
            echo crea_tb($encabezado, $data, $condi);
            break;
        case 'aho_pzf':
            //Encabezado de la tabla
            $encabezado = ['Cod. Clientes', 'Nombre', 'Cod. ahorro', 'Fecha vencimiento'];
            //Data de la tabla 
            ob_start();
            $sql = "SELECT tc.idcod_cliente, tc.short_name, crt.codaho, crt.fec_ven  FROM ahomcrt crt 
            INNER JOIN tb_cliente tc ON tc.idcod_cliente = crt.ccodcli 
            WHERE crt.fec_ven > (SELECT CURDATE()) AND crt.fec_ven < (SELECT DATE_ADD(CURDATE(), INTERVAL 15 DAY)) ORDER BY fec_ven ASC";
            $con = 0;
            foreach (exeQuery($conexion, $sql) as $row) {
                echo "<tr>";
                foreach ($row as $data) {
                    echo '<td>' . $data . '</td>';
                }
                echo "</tr>";
                $con = 0;
            }
            $data = ob_get_clean();
            //Funcion que se encargar de juntar la inf. de la tabla 
            echo crea_tb($encabezado, $data, $condi);
            break;
        case 'pass':
            //Encabezado de la tabla
            $encabezado = ['Alerta', 'Mensaje', 'Fecha vencimiento'];
            //Data de la tabla 
            ob_start();
            $sql = "SELECT tipo_alerta, mensaje, fecha FROM `tb_alerta` WHERE estado = 1 AND cod_aux = $extra[0]";
            $con = 0;
            foreach (exeQuery($conexion, $sql) as $row) {
                echo "<tr>";
                foreach ($row as $data) {
                    echo '<td>' . $data . '</td>';
                }
                echo "</tr>";
                $con = 0;
            }
            $data = ob_get_clean();
            //Funcion que se encargar de juntar la inf. de la tabla 
            echo crea_tb($encabezado, $data, $condi);
            break;
            break;
    }
}

//Cuncion en cargada de crear la tabla 
function crea_tb($encabezado, $data, $nameTabla)
{
    ob_start();
    ?>
    <div class="card">
        <div class="card-body">
            <!-- ini tabal -->
            <div class="table">
                <table class="table" id="<?= 'tb' . $nameTabla ?>">
                    <thead>
                        <tr>
                            <?php
                            foreach ($encabezado as $row) {
                                echo '<th scope="col">' . $row . ' </th>';
                            }
                            ?>
                        </tr>
                    </thead>
                    <!-- ini info -->
                    <!-- <tbody id="tbAlerta"> -->
                    <?php echo $data; ?>
                    <!-- INI de la información -->
                    </tbody>
                </table>
                <!-- fin info -->
                </table>
            </div>
            <!-- fin tabal -->
        </div>
    </div>

<?php
    return ob_get_clean();
};

//Funcion encargada de crear el modal e integrar la tabla
function modal($data, $title, $condi, $conexion, $extra)
{
    ob_start();
?>
    <!-- INI modal -->
    <div class="modal fade" id="modal_alt" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="staticBackdropLabel">Alertas</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <!-- INI CHIDO -->
                    <div class="container mt-3">
                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs" role="tablist">
                            <?php
                            $con = 0;
                            foreach ($data as $tp_Alt) {
                                if (in_array($_SESSION['puesto'], $tp_Alt) || $data[$con][0] === 'otr_g') {
                            ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?= ($con == 0) ? 'active' : '' ?>" data-bs-toggle="tab" href="#<?= $condi[$con] ?>"><?= $title[$con] ?> </a>
                                    </li>
                            <?php
                                }
                                $con += 1;
                            }
                            ?>
                        </ul>
                        <!-- Tab panes -->
                        <div class="tab-content">
                            <?php
                            $con = 0;
                            foreach ($data as $tp_Alt) {
                                if (in_array($_SESSION['puesto'], $tp_Alt) || $data[$con][0] === 'otr_g') {
                            ?>
                                    <div id="<?= $condi[$con] ?>" class="container tab-pane <?= ($con == 0) ? 'active' : 'fade' ?>"><br>
                                        <?php opciones($conexion, $condi[$con], $extra); ?>
                                    </div>
                            <?php
                                }
                                $con += 1;
                            }
                            ?>

                        </div>
                    </div>

                    <!-- FIN CHIDO -->

                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" >ok</button> -->
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal();">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <!-- FIN modal -->
<?php
    $output = ob_get_clean();
    echo $output;
}

//Funcion encargada de ejecutar la consulta
function exeQuery($conexion, $sql, $op = 0)
{
    $data = mysqli_query($conexion, $sql);
    if (!$data) {
        // Manejar el error si la consulta no fue exitosa
        echo "Error en la consulta: " . mysqli_error($conexion);
        return false;
    }
    $info = '';
    // Obtener todas las filas como un array asociativo
    switch ($op) {
        case 0:
            $info = mysqli_fetch_all($data, MYSQLI_ASSOC);
            break;
        case 1:
            $info = $data->fetch_assoc();
            break;
    }

    // Liberar los resultados
    mysqli_free_result($data);
    return $info;
}
?>