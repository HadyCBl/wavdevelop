<div class="modal" id="modal_solicitud_01">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Búsqueda de clientes</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tabla_clientes_a_solicitar" class="table table-striped table-hover"
                        style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th>Código cliente</th>
                                <th>Nombre cliente</th>
                                <th>Direccion</th>
                                <th>Ciclo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id=""
                    onclick="cerrar_modal('#modal_solicitud_01', 'hide', '#id_modal_hidden')">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $("#tabla_clientes_a_solicitar").DataTable({
        "processing": true,
        "serverSide": true,
        "sAjaxSource": "../../src/server_side/clientes_solicitantes_indi.php",
        "columnDefs": [{
            "data": 0,
            "targets": 4,
            render: function(data, type, row) {
                return `<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2('#cuadro','${data}')" >Aceptar</button>`;
            }

        }, ],
        "bDestroy": true,
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
            "sProcessing": "Procesando..."
        }

    });
});
</script>