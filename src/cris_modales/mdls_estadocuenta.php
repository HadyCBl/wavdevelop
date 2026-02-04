<style>
    .small-font th,
    .small-font td {
        font-size: 12px;
    }
</style>
<div class="modal" id="modal_estado_cuenta">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h5 class="modal-title">Busqueda de clientes con créditos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <input type="text" id="id_modal_hidden" value="" readonly hidden>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tabla_estado_cuenta" class="table table-striped table-hover small-font" style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Cod. crédito</th>
                                <th scope="col">DPI</th>
                                <th scope="col">Nombre cliente</th>
                                <th scope="col">Ciclo</th>
                                <th scope="col">Capital</th>
                                <th scope="col">Tipo crédito</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acción</th>
                            </tr>
                        </thead>
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
    // function mostrar_planpago(codigocredito) {
    //     $('#tabla_plan_pagos').on('search.dt').DataTable({
    //         "aProcessing": true,
    //         "aServerSide": true,
    //         "ordering": false,
    //         "lengthMenu": [
    //             [10, 15, -1],
    //             ['10 filas', '15 filas', 'Mostrar todos']
    //         ],
    //         "ajax": {
    //             url: '../../src/cruds/crud_caja.php',
    //             type: "POST",
    //             beforeSend: function() {
    //                 loaderefect(1);
    //             },
    //             data: {
    //                 'condi': 'consultar_plan_pago', codigocredito
    //             },
    //             dataType: "json",
    //             complete: function() {
    //                 loaderefect(0);
    //             }
    //         },
    //         "bDestroy": true,
    //         "iDisplayLength": 10,
    //         "order": [
    //             [1, "desc"]
    //         ],
    //         "language": {
    //             "lengthMenu": "Mostrar _MENU_ registros",
    //             "zeroRecords": "No se encontraron registros",
    //             "info": " ",
    //             "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
    //             "infoFiltered": "(filtrado de un total de: _MAX_ registros)",
    //             "sSearch": "Buscar: ",
    //             "oPaginate": {
    //                 "sFirst": "Primero",
    //                 "sLast": "Ultimo",
    //                 "sNext": "Siguiente",
    //                 "sPrevious": "Anterior"
    //             },
    //             "sProcessing": "Procesando..."
    //         }
    //     });
    //     // $.ajax({
    //     //     url: "../../src/cruds/crud_caja.php",
    //     //     method: "POST",
    //     //     data: {
    //     //         'condi': 'consultar_plan_pago',
    //     //         codigocredito
    //     //     },
    //     //     success: function(data) {
    //     //         console.log(data)
    //     //         // const data2 = JSON.parse(data);
    //     //         // console.log(data2); 
    //     //     }
    //     // })
    // }

    $(document).ready(function() {
        $('#tabla_estado_cuenta').on('search.dt').DataTable({
            "aProcessing": true,
            "aServerSide": true,
            "ordering": false,
            "lengthMenu": [
                [10, 15, -1],
                ['10 filas', '15 filas', 'Mostrar todos']
            ],
            "ajax": {
                url: '../../src/cruds/crud_credito_indi.php',
                type: "POST",
                beforeSend: function() {
                    loaderefect(1);
                },
                data: {
                    'condi': 'listado_consultar_estado_cuenta'
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
        //   url: "../../src/cruds/crud_credito_indi.php",
        //   method: "POST",
        //   data: {
        //     'condi': 'listado_consultar_estado_cuenta'
        //   },
        //   success: function(data) {
        //     console.log(data)
        //     const data2 = JSON.parse(data);
        //     console.log(data2); 
        //   }
        // })
    });
</script>