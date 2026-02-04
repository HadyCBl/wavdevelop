<div class="modal" id="modal_plan_pago">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h5 class="modal-title">Consulta de plan de pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <!-- <div class="container contenedort"> -->
                <div class="row">
                    <div class="col">
                        <div class="text-center text-primary"><b>Cliente</b></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 col-sm-6">
                        <div class="form-floating mb-2 mt-2">
                            <input type="text" class="form-control" id="nomcli2" placeholder="Nombre de cliente" disabled>
                            <label for="nomcli2">Nombre cliente</label>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="form-floating mb-3 mt-2">
                            <input type="text" class="form-control" id="codcredito2" placeholder="Codigo de crédito" disabled>
                            <label for="codcredito2">Código de crédito</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="text-center text-primary"><b>Plan de pago</b></div>
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
                                <th scope="col">Capital</th>
                                <th scope="col">Interes</th>
                                <th scope="col">Mora</th>
                                <th scope="col">A. Programado</th>
                                <th scope="col">Otros pagos</th>
                            </tr>
                        </thead>
                        <!-- <tbody style="font-size: 0.9rem !important;"></tbody> -->
                    </table>
                </div>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ---------------------------------TERMINA EL MODAL  -->

<script>
    function mostrar_planpago(codigocredito) {
        $('#tabla_plan_pagos').on('search.dt').DataTable({
            "aProcessing": true,
            "aServerSide": true,
            "ordering": false,
            "lengthMenu": [
                [10, 15, -1],
                ['10 filas', '15 filas', 'Mostrar todos']
            ],
            "ajax": {
                url: '../../src/cruds/crud_caja.php',
                type: "POST",
                beforeSend: function() {
                    loaderefect(1);
                },
                data: {
                    'condi': 'consultar_plan_pago', codigocredito
                },
                dataType: "json",
                complete: function() {
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
        // $.ajax({
        //     url: "../../src/cruds/crud_caja.php",
        //     method: "POST",
        //     data: {
        //         'condi': 'consultar_plan_pago',
        //         codigocredito
        //     },
        //     success: function(data) {
        //         console.log(data)
        //         // const data2 = JSON.parse(data);
        //         // console.log(data2); 
        //     }
        // })
    }

    // $(document).ready(function() {
    //     console.log('holis nuevamente');

    //     // $.ajax({
    //     //   url: "../../src/cruds/crud_caja.php",
    //     //   method: "POST",
    //     //   data: {
    //     //     'condi': 'list_pagos_individuales'
    //     //   },
    //     //   success: function(data) {
    //     //     console.log(data)
    //     //     const data2 = JSON.parse(data);
    //     //     console.log(data2); 
    //     //   }
    //     // })
    // });
</script>