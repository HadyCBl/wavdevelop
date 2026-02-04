<?php
//FUNCION PARA BUSQUEDA DE CLIENTE general
function BuscarUsers($conexion, $id_actual,$db_name_general)
{
    $consulta2 = mysqli_query($conexion, "SELECT 
    us.id_usu AS id_usuario, CONCAT(us.nombre,' ', us.apellido) AS nombre,tr.id AS id_cargo,cg.UsuariosCargoProfecional AS cargo,ag.nom_agencia AS nombreagen,ag.cod_agenc AS codagen, us.usu AS usuario
    FROM tb_usuario us
    INNER JOIN  $db_name_general.tb_usuarioscargoprofecional cg ON us.puesto = cg.id_UsuariosCargoProfecional
    INNER JOIN  $db_name_general.tb_rol tr ON cg.id_UsuariosCargoProfecional = tr.siglas
    INNER JOIN  tb_agencia ag ON us.id_agencia = ag.id_agencia
    WHERE us.estado != '0' ORDER BY us.id_usu ASC;");
    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $id_usuario = $registro["id_usuario"];
        $id_cargo = $registro["id_cargo"];
        $nombre = $registro["nombre"];
        $cargo = $registro["cargo"];
        $nombreagen = $registro["nombreagen"];
        $codagen = $registro["codagen"];
        $usuario = $registro["usuario"];

        if ($id_actual == 4) {
            echo '
            <tr style="cursor: pointer;"> 
                  <td scope="row">' . $id_usuario . '</td>
                  <td scope="row">' . strtoupper($nombre) . '</td>
                  <td scope="row">' . strtoupper($usuario) . '</td>
                  <td scope="row">' . strtoupper($cargo) . '</td>
                  <td scope="row">' . strtoupper($nombreagen) . '</td>
                  <td scope="row">' . $codagen . '</td>
                  <td scope="row"> <button type="button" class="btn btn-success" onclick= "seleccionar_cuenta_ctb2(`#id_modal_hidden`,[`' . $id_usuario . '`,`' . strtoupper($nombre) . '`,`' . strtoupper($cargo) . '`,`' . strtoupper($nombreagen) . '`,`' . $codagen . '`,`'. $id_cargo .'`]); cerrar_modal(`#modal_users`, `hide`, `#id_modal_hidden`);" >Aceptar</button> </td>
                  </tr> ';
        } else {
            if ($id_usuario != 4) {
                echo '
                <tr style="cursor: pointer;"> 
                      <td scope="row">' . $id_usuario . '</td>
                      <td scope="row">' . strtoupper($nombre) . '</td>
                        <td scope="row">' . strtoupper($usuario) . '</td>
                      <td scope="row">' . strtoupper($cargo) . '</td>
                      <td scope="row">' . strtoupper($nombreagen) . '</td>
                      <td scope="row">' . $codagen . '</td>
                      <td scope="row"> <button type="button" class="btn btn-success" onclick= "seleccionar_cuenta_ctb2(`#id_modal_hidden`,[`' . $id_usuario . '`,`' . strtoupper($nombre) . '`,`' . strtoupper($cargo) . '`,`' . strtoupper($nombreagen) . '`,`' . $codagen . '`]); cerrar_modal(`#modal_users`, `hide`, `#id_modal_hidden`);" >Aceptar</button> </td>
                      </tr> ';
            }
        }
    }
}
?>

<!-- ---------------------------------TERMINA EL MODAL  -->
<div class="modal" id="modal_users">
    <!-- <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90%; width: 1400px;"> -->
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Búsqueda de usuarios</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tabla_nomusuarios" class="table table-striped table-hover"
                        style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Usuario</th>
                                <th scope="col">Cargo</th>
                                <th scope="col">Nombre agencia</th>
                                <th scope="col">Código agencia</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla_nomusuarios">
                            <?php
                            BuscarUsers($conexion, $_SESSION['id'], $db_name_general);
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id=""
                    onclick="cerrar_modal('#modal_users', 'hide', '#id_modal_hidden')">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ---------------------------------TERMINA EL MODAL  -->

<script>
// para cuentas de ahorro
$(document).ready(function() {
    var table = $('#tabla_nomusuarios').on('search.dt')
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
});
</script>