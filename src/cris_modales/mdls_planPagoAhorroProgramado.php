<div class="modal" id="modal_plan_pago">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consulta de plan de pago de Ahorro programado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col">
                        <div class="text-center text-primary"><b>Cliente</b></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="text-center text-primary"><b>Plan de pagos</b></div>
                    </div>
                </div>
                <!-- </div> -->
                <div class="table-responsive">
                    <table id="tabla_plan_pagos" class="table table-striped table-hover" style="width: 100% !important; font-size: 0.9rem !important;">
                        <thead>
                            <tr>
                                <th scope="col">Cuota</th>
                                <th scope="col">Fecha pago</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Dias atraso</th>
                                <th scope="col">Monto</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="reportePlanPago('pdf')">
                    <i class="fa-solid fa-file-arrow-down me-2"></i>Descargar PDF

                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function mostrar_planpago(codcuenta) {
        $('#id_modal_hidden').val(codcuenta);
        $('#tabla_plan_pagos').on('search.dt').DataTable({
            "aProcessing": true,
            "aServerSide": true,
            "ordering": false,
            "lengthMenu": [
                [10, 15, -1],
                ['10 filas', '15 filas', 'Mostrar todos']
            ],
            "ajax": {
                url: '../src/cruds/crud_ahorro.php',
                type: "POST",
                beforeSend: function() {
                    loaderefect(1);
                },
                data: {
                    'condi': 'consultar_plan_pago',
                    codcuenta
                },
                dataType: "json",
                complete: function(data) {
                    console.log(data)
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
            },
            "dom": 'Bfrtip', // B - Buttons, f - Filter, r - processing, t - table, i - info, p - pagination
            "buttons": [{
                extend: 'excelHtml5',
                text: 'Exportar a Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success' 
            }]
        });
    }

    function reportePlanPago(tipo, download = 1) {

        const cuenta = document.getElementById('id_modal_hidden').value;
        const tabla = $('#tabla_plan_pagos').DataTable();
        const filas = tabla.rows().data().toArray();
        const dataStr = JSON.stringify(filas);

        reportes([
            [],
            [],
            [],

            [cuenta, dataStr]

        ], tipo, 'plan_pago_aprog', download);
    }
</script>
