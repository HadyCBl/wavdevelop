<?php
session_start();
include '../../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
// include '../../src/funcphp/func_gen.php';
//include '../../src/funcphp/fun_ppg.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$codusu = $_SESSION["id"];

$condi = $_POST["condi"]; //CONDICION QUE SE TIENEN QUE EJECUTAR
switch ($condi) {
    case 'tbParametrizacionAgencia':
?>
        <table class="table" id="adminAgencia">
            <thead class="table-dark">
                <tr>
                    <th hidden>idAge</th>
                    <th>Codígo Agencia</th>
                    <th>Nombre de agencia</th>
                    <th>Cuenta</th>
                    <th>Descripción</th>
                    <th>Cambiar cuenta</th>
                </tr>
            </thead>
            <tbody id="rep_otroGas">
                <?php
                //Obtener informacion de las cuenta, cliente y cuenta...
                $consulta = mysqli_query($conexion, "SELECT ta.id_agencia AS idAge, ta.cod_agenc AS cod, ta.nom_agencia AS nom, cn.ccodcta AS cuenta, cn.cdescrip AS descrip FROM tb_agencia ta 
                INNER JOIN ctb_nomenclatura cn ON ta.id_nomenclatura_caja = cn.id");
                //if(mysqli_error($conexion))
                $con = 0; 
                ob_start();
                while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $con +=1; 
                ?>
                    <!-- seccion de datos -->
                    <tr>
                        <td id="<?= 'id'.$con?>" hidden><?= $row['idAge'] ?></td>
                        <td><?= $row['cod'] ?></td>
                        <td><?= $row['nom'] ?></td>
                        <td><?= $row['cuenta'] ?></td>
                        <td><?= $row['descrip'] ?></td>
                        <td>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal_nomenclatura" onclick="capID('<?='id'.$con?>')">Cambiar</button>
                        </td>
                    </tr>
                <?php
                }
                ?>
                <!--Inicio impritme tabla--->
            </tbody>
        </table>
        <script>
            $(document).ready(function() {
                $('#adminAgencia').on('search.dt')
                    .DataTable({
                        "lengthMenu": [
                            [5, 10, 15, -1],
                            ['5 filas', '10 filas', '15 filas', 'Mostrar todos']
                        ],
                        "language": {
                            "lengthMenu": "Mostrar _MENU_ registros",
                            "zeroRecords": "No se encontraron registros",
                            "info": " ",
                            "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                            "infoFiltered": "(filtrado de un total de: _MAX_ registros)",
                            "sSearch": "Buscar: ",
                            "oPaginate": {
                                "sFirst": "Primero",
                                "sLast": "Ultimo",
                                "sNext": "Siguiente",
                                "sPrevious": "Anterior"
                            },
                            "sProcessing": "Procesando...",

                        },
                    });
            })
        </script>

<?php
        $output = ob_get_clean();
        echo $output;
        break;
}
