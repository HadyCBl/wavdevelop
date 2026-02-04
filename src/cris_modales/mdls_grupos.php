    <!-- Modal Structure -->
    <div class="modal fade" id="buscargrupo" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="margin-left: +10%;">
                <div class="modal-header">
                    <h4 class="modal-title">Búsqueda de Grupos</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table id="grupos_tb" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre del Grupo</th>
                                <th>Dirección</th>
                                <th>Ciclo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
function loadconfig(status1, status2) {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#grupos_tb')) {
        $('#grupos_tb').DataTable().destroy();
    }

    $('#grupos_tb').DataTable({
        "aProcessing": true,
        "aServerSide": true,
        "ordering": false,
        "lengthMenu": [
            [10, 15, -1],
            ['10 filas', '15 filas', 'Mostrar todos']
        ],
        "ajax": {
            url: '../../src/cris_modales/fun_modal.php',
            type: "POST",
            beforeSend: function() {
                loaderefect(1);
            },
            data: {
                'condi2': "gruposcredito",
                status1,
                status2
            },
            dataType: "json",
            complete: function(data) {
                loaderefect(0);
            }
        },
        "bDestroy": true,
        "iDisplayLength": 10,
        "order": [
            [1, "desc"]
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
            "sProcessing": "Procesando..."
        }
    });
}

function loadconfig01(status1, status2) {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#grupos')) {
        $('#grupos').DataTable().destroy();
    }

    $('#grupos').DataTable({
        "aProcessing": true,
        "aServerSide": true,
        "ordering": false,
        "lengthMenu": [
            [10, 15, -1],
            ['10 filas', '15 filas', 'Mostrar todos']
        ],
        "ajax": {
            url: '../../src/cris_modales/fun_modal.php',
            type: "POST",
            beforeSend: function() {
                loaderefect(1);
            },
            data: {
                'condi2': "gruposcredito",
                status1,
                status2
            },
            dataType: "json",
            complete: function(data) {
                loaderefect(0);
            }
        },
        "bDestroy": true,
        "iDisplayLength": 10,
        "order": [
            [1, "desc"]
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
            "sProcessing": "Procesando..."
        }
    });
}

// Event listener to reinitialize table when modal is shown
// $('#buscargrupo').on('show.bs.modal', function () {
//     console.log("Modal is shown");
//     loadconfig("all", "all");
// });

// Clear table when modal is hidden
$('#buscargrupo').on('hidden.bs.modal', function () {
    $('#grupos_tb').empty();
});
</script>