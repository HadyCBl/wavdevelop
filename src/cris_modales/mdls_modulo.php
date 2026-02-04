<?php
//FUNCION PARA BUSQUEDA DE CLIENTE general
function BuscarModulos($general)
{
    $consulta2 = mysqli_query($general, "SELECT *  FROM tb_modulos WHERE estado=1 ORDER BY id ASC");
    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $id = $registro["id"];
        $descripcion = $registro["descripcion"];
        $icon = $registro["icon"];
        $ruta = $registro["ruta"];
        $rama = $registro["rama"];
        $orden = $registro["orden"];

        echo '
      <tr style="cursor: pointer;"> 
            <td scope="row">' . $id . '</td>
            <td scope="row">' . $descripcion . '</td>
            <td scope="row">' . $icon . '</td>
            <td scope="row">' . $ruta . '</td>
            <td scope="row">' . $rama . '</td>
            <td scope="row">' . $orden . '</td>
            <td scope="row"> <button type="button" class="btn btn-success btn-sm" onclick= "seleccionar_cuenta_ctb2(`#id_modal_hidden`,[`' . $id . '`,`' . $descripcion . '`,`' . $rama . '`,`' . $icon . '`,`' . $ruta . '`,`' . $orden . '`]); cerrar_modal(`#modal_modulos`, `hide`, `#id_modal_hidden`);" >Aceptar</button> </td>
            </tr> ';
    }
}
?>

<!-- ---------------------------------TERMINA EL MODAL  -->
<div class="modal" id="modal_modulos">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Búsqueda de módulos</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tabla_nommodulos" class="table table-striped table-hover" style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Icono</th>
                                <th scope="col">Ruta</th>
                                <th scope="col">Rama</th>
                                <th scope="col">Orden</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla_nommodulos">
                            <?php
                            BuscarModulos($general);
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="" onclick="cerrar_modal('#modal_modulos', 'hide', '#id_modal_hidden')">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ---------------------------------TERMINA EL MODAL  -->

<script>
    // para cuentas de ahorro
    $(document).ready(function() {
        var table = $('#tabla_nommodulos').on('search.dt')
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