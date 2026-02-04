<?php
//FUNCION PARA BUSQUEDA DE CLIENTE general
function BuscarNomenclaturaenabled($conexion)
{
    $consulta2 = mysqli_query($conexion, "SELECT id, ccodcta, cdescrip,tipo FROM ctb_nomenclatura where estado=1");
    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $id = $registro["id"];
        $ccodcta = $registro["ccodcta"];
        $cdescrip = $registro["cdescrip"];
        $tipo = $registro["tipo"];
        echo '
      <tr style="cursor: pointer;"> 
            <td scope="row">' . $id . '</td>
            <td scope="row">' . $ccodcta . '</td>
            <td scope="row">' . $cdescrip . '</td>
            <td scope="row"><button type="button" class="btn btn-success" onclick= "seleccionar_cuenta_ctb(`#id_modal_hidden`,[`' . $id . '`,`' . $ccodcta . '`,`' . $cdescrip . '`],`modal_nomenclatura_enabled`)" >Seleccionar</button></td>
            </tr> ';
    }
}

?>

<!-- ---------------------------------TERMINA EL MODAL  -->
<div class="modal" id="modal_nomenclatura_enabled">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Búsqueda de nomenclatura .</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tabla_nomenclatura" class="table table-striped table-hover" style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Código de cuenta</th>
                                <th scope="col">Descripción</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla_nomenclatura">
                            <?php
                            BuscarNomenclaturaenabled($conexion);
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="" onclick="cerrar_modal('#modal_nomenclatura_enabled', 'hide', '#id_modal_hidden')">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ---------------------------------TERMINA EL MODAL  -->

<script>
    // para cuentas de ahorro
    $(document).ready(function() {
        var table = $('#tabla_nomenclatura').on('search.dt')
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