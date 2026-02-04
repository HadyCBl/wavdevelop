<?php
//FUNCION PARA BUSQUEDA DE CLIENTE general
function BuscarNomenclatura($conexion)
{
    // include '../includes/BD_con/db_con.php';
    // mysqli_set_charset($conexion, 'utf8');
    $consulta2 = mysqli_query($conexion, "SELECT nom.id,nom.ccodcta,nom.cdescrip,nom.tipo, 
                                                CASE WHEN EXISTS (SELECT 1 FROM ctb_bancos b WHERE b.id_nomenclatura = nom.id AND b.estado = 1) THEN 1 ELSE 0 
                                                END AS cuenta_en_bancos FROM ctb_nomenclatura nom  WHERE nom.estado = 1;");
    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $id = $registro["id"];
        $ccodcta = $registro["ccodcta"];
        $cdescrip = $registro["cdescrip"];
        $tipo = $registro["tipo"];
        $banco = ($registro["cuenta_en_bancos"] == 1) ? "CUENTA DE BANCOS" : "";
        $button = ($tipo == "D") ?  '<button type="button" class="btn btn-success" onclick="seleccionar_cuenta_ctb(`#id_modal_hidden`, [' . $id . ', \'' . addslashes($ccodcta) . '\', \'' . addslashes($cdescrip) . '\'])">Seleccionar</button>'
            : "Cuenta de Resumen";
        echo '
      <tr style="cursor: pointer;"> 
            <td scope="row">' . $id . '</td>
            <td scope="row">' . $ccodcta . '</td>
            <td scope="row">' . $cdescrip . '</td>
            <td scope="row">' . $banco . '</td>
            <td scope="row">' . $button . '</td>
            </tr> ';
    }
}

?>

<div class="modal" id="modal_nomenclatura">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Búsqueda de nomenclatura</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <!-- <div>
                        <label><input type="checkbox" id="hideResumen"> Ocultar Cuentas de Resumen</label>
                    </div> -->
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="hideResumen">
                        <label class="form-check-label" for="hideResumen">Ocultar Cuentas de Resumen</label>
                    </div>
                    <table id="tabla_nomenclatura" class="table table-striped table-hover" style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th scope="col">Id</th>
                                <th scope="col">Código de cuenta</th>
                                <th scope="col">Descripción</th>
                                <th scope="col">AD</th>
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
                <button type="button" class="btn btn-danger" id="" onclick="cerrar_modal('#modal_nomenclatura', 'hide', '#id_modal_hidden')">Cerrar</button>
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
        $('#hideResumen').on('change', function() {
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    if (this.checked) {
                        return data[4] !== "Cuenta de Resumen";
                    }
                    return true;
                }.bind(this)
            );
            table.draw();
        });
    });
</script>