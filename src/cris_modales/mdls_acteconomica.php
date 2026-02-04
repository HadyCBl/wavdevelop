<?php
//FUNCION PARA BUSQUEDA DE CLIENTE general
function BuscarActividad()
{
    include __DIR__ . '/../../includes/BD_con/db_con.php';
    mysqli_set_charset($general, 'utf8');
    $consulta2 = mysqli_query($general, "SELECT id_ActiEcono AS id, Id_SctrEcono AS abreviatura, Titulo FROM tb_ActiEcono");
    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $id = $registro["id"];
        $abreviatura = $registro["abreviatura"];
        $titulo = $registro["Titulo"];
        echo '
      <tr style="cursor: pointer;"> 
            <td scope="row">' . $id . '</td>
            <td scope="row">' . $titulo . '</td>
            <td scope="row">' . $abreviatura . '</td>
            <td scope="row"> <button type="button" class="btn btn-success" onclick= "seleccionar_cuenta_ctb2(`#id_modal_hidden`,[`' . $id . '`,`' . $titulo . '`,`' . $abreviatura . '`]); cerrar_modal(`#modal_acteconomica`, `hide`, `#id_modal_hidden`);" >Aceptar</button> </td>
            </tr> ';
    }
}
?>
 
<!-- TERMINA EL MODAL  -->
<div class="modal" id="modal_acteconomica">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Búsqueda de bancos</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tabla_acteconomica" class="table table-striped table-hover" style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Actividad económica</th>
                                <th scope="col">Abreviatura</th>
                                <th scope="col">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tabla_acteconomica">
                            <?php
                            BuscarActividad();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="" onclick="cerrar_modal('#modal_acteconomica', 'hide', '#id_modal_hidden')">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ---------------------------------TERMINA EL MODAL  -->

<script>
    // para cuentas de ahorro
    $(document).ready(function() {
        var table = $('#tabla_acteconomica').on('search.dt')
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