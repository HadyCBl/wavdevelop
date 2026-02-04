<!-- ---------------------------------COMIENZA EL MODAL  -->
<div class="modal" id="findahomcta2">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <?php $titlemodule = $_ENV['AHO_NAME_MODULE'] ?? "Ahorros"; ?>
                <h4 class="modal-title">Busqueda de cuentas de <?= $titlemodule ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <!-- <form id="reloadForm" method="post"> -->
                    <table id="tblahomcta2" class="table table-striped table-hover"
                        style="width: 100% !important; font-size: 12px;">
                        <thead>
                            <tr>
                                <!-- <th scope="col">#</th> -->
                                <th scope="col">Codigo</th>
                                <th scope="col">Codigo Cliente</th>
                                <!-- <th scope="col">Codigo Cliente</th> -->
                                <th scope="col">No. Identificación</th>
                                <th scope="col">Producto</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                    </table>
                    <!-- boton para realizar nuevamente la consulta -->
                    <!-- <button type="submit" name="reload" class="btn btn-info">Ver Nuevos</button> -->
                    <!-- </form> -->
                </div>
                <br>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ---------------------------------TERMINA EL MODAL  -->


<script>
    //   $(document).ready(function() {
    //     var tabla = $('#tblahomcta').DataTable({
    //         "aProcessing": true,
    //         "aServerSide": true,
    //         "ordering": false,
    //         "lengthMenu": [
    //             [10, 15, -1],
    //             ['10 filas', '15 filas', 'Mostrar todos']
    //         ],
    //         "ajax": {
    //             url: '../../src/cruds/crud_ahorro.php',
    //             type: "POST",
    //             beforeSend: function() {
    //                 loaderefect(1);
    //             },
    //             data: {
    //                 'condi': 'aho_cli'
    //             },
    //             dataType: "json",
    //             complete: function(data) {
    //                // console.log(data);
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

    //     $('#reloadForm').on('submit', function(e) {
    //         e.preventDefault();
    //         tabla.ajax.reload();
    //     });

    // });

    $(document).ready(function () {
        $("#tblahomcta2").DataTable({
            "processing": true,
            "serverSide": true,
            "sAjaxSource": "../src/server_side/clientesAhorros.php",
            "columnDefs": [{
                "data": 0,
                "targets": 5,
                render: function (data, type, row) {
                    // console.log(data);
                    return `<button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" onclick="printdiv2('#cuadro','${data}')" >Aceptar</button>`;
                }

            },],
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