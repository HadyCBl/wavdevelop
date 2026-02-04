<style>
    .small-font th,
    .small-font td {
        font-size: 12px;
    }
</style>

<div class="modal" id="modal_pagos_cre_individuales">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h5 class="modal-title">Pago de créditos individuales</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <!-- <input type="text" id="id_modal_hidden" value="" readonly hidden> -->
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tabla_pagos_individuales" class="table table-striped table-hover table-sm small-font" style="width: 100% !important;">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <?php

                                $camposRetornados = $appConfigGeneral->getCamposPagosCreditos();

                                if (empty($camposRetornados)) {
                                    $camposRetornados = array("ccodcta", "codcli", "dpi", "nombre", "ciclo", "diapago", "monto", "saldo");
                                }

                                $referencias = array(
                                    "ccodcta" => "Cuenta",
                                    "codcli" => "Cód. Cliente",
                                    "dpi" => "DPI",
                                    "nombre" => "Nombre",
                                    "ciclo" => "Ciclo",
                                    "monto" => "Monto",
                                    "saldo" => "Saldo",
                                    "diapago" => "Día Pago",
                                    "analista" => "Encargado",
                                    "agencia" => "Agencia",
                                    "dfecdsbls" => "Fecha Desembolso"
                                );

                                foreach ($camposRetornados as $key => $value) { ?>
                                    <th scope="col"><?= $referencias[$value] ?? '-'; ?></th>
                                <?php } ?>
                                <th scope="col">Acciones</th>
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
    $(document).ready(function() {
        $('#tabla_pagos_individuales').on('search.dt').DataTable({
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
                    'condi': 'list_pagos_individuales'
                },
                dataType: "json",
                complete: function(data) {
                    // console.log(data)
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
    });
</script>