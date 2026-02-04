<div class="modal fade" id="otr_cli" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog  modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Lista de cliente</h1>
                <button type="hidden" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                <input type="hidden" id="id_modal_hidden" value="" readonly>
            </div>
            <div class="modal-body">
                <!-- INICIO Tabla de nomenclatura -->
                <div class="container mt-3">
                    <h4>Registros </h4>
                    <table class="table table-striped nowrap" style="width: 100%;" id="tbOtr_cli">
                        <thead class="table-dark">
                            <tr>
                                <th>Cod. Cliente</th>
                                <th>Nombre</th>
                                <th>Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!--Inicio de la tb Modal-->
                            <!--Fin de la tb Modal-->
                        </tbody>
                    </table>
                    <!-- FIN Tabla de nomenclatura -->
                    <script>
                        $(document).ready(function() {
                            $("#tbOtr_cli").DataTable({
                                "processing": true,
                                "serverSide": true,
                                "sAjaxSource": "../src/server_side/clientes_solicitantes_indi.php",
                                columns: [{
                                    data: [0]
                                },
                                {
                                    data: [1]
                                    },
                                    {
                                        data: [0],
                                        render: function(data, type, row) {
                                            imp = `<button type="button" class="btn btn-outline-primary btn-sm mt-2" data-bs-dismiss="modal" onclick="capData(['#cliente'],'${row[1]}')">Seleccionar</button>`;

                                            return imp;
                                        }
                                    },

                                ],
                                "bDestroy": true,
                                "language": {
                                    "lengthMenu": "Mostrar MENU registros",
                                    "zeroRecords": "No se encontraron registros",
                                    "info": " ",
                                    "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                                    "infoFiltered": "(filtrado de un total de: MAX registros)",
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ############################################### -->
