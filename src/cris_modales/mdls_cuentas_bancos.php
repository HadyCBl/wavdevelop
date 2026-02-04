<?php
//FUNCION PARA BUSQUEDA DE CLIENTE general
function BuscarNomenclatura($conexion)
{
    $consulta2 = mysqli_query($conexion, "SELECT ctb.id id_cuenta, ctb.ccodcta, ctb.cdescrip,ban.numcuenta FROM ctb_nomenclatura ctb 
    INNER JOIN ctb_bancos ban ON ban.id_nomenclatura=ctb.id WHERE ban.estado=1 AND ctb.estado=1");
    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $id = $registro["id_cuenta"];
        $ccodcta = $registro["ccodcta"];
        $cdescrip = $registro["cdescrip"];
        $numero_cuenta = $registro["numcuenta"];
        echo '
            <tr style="cursor: pointer;"> 
                <td scope="row">' . $id . '</td>
                <td scope="row">' . $ccodcta . '</td>
                <td scope="row">' . $cdescrip . '</td>
                <td scope="row">' . $numero_cuenta . '</td>
                <td scope="row"> <button type="button" class="btn btn-success" onclick= "seleccionar_cuenta_ctb2(`#id_modal_bancos`,[`' . $id . '`,`' . $ccodcta . '-' . $cdescrip . '`]);cerrar_modal(`#modal_cuentas_bancos`, `hide`, `#id_modal_bancos`) " >Aceptar</button> </td>
            </tr> ';
    }
}
?>
<div class="modal" id="modal_cuentas_bancos">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Búsqueda de Cuentas</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_bancos" value="" readonly hidden>
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
                                <th scope="col">Numero Cuenta</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla_nomenclatura">
                            <?php
                            BuscarNomenclatura($conexion);
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="" onclick="cerrar_modal('#modal_cuentas_bancos', 'hide', '#id_modal_bancos')">Cerrar</button>
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