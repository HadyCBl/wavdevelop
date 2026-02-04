<?php

use Micro\Generic\Utf8;
//FUNCION PARA BUSQUEDA DE CLIENTE general
function BuscarCli()
{
    include '../includes/BD_con/db_con.php';

    $consulta2 = mysqli_query($conexion, "SELECT idcod_cliente, no_identifica,short_name compl_name, date_birth,no_tributaria, 
                                            concat(primer_name, ' ' ,segundo_name, ' ' , tercer_name) AS 'nombres', 
                                            concat(primer_last,' ' , segundo_last, ' ' ,casada_last) as 'apellido' 
                                            FROM `tb_cliente` WHERE id_tipoCliente IN ('NATURAL','JURIDICO') AND idcod_cliente IN (SELECT ccodcli FROM aprcta WHERE estado='A') AND estado=1");
    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $idcod_cliente = ($registro["idcod_cliente"]);
        $no_identifica = ($registro["no_identifica"]);
        $nit = ($registro["no_tributaria"]);
        $compl_name = Utf8::encode($registro["compl_name"]);
        $date_birth = ($registro["date_birth"]);
        echo '
      <tr style="cursor: pointer;"> 
            <td scope="row">' . $idcod_cliente . '</td>
            <td scope="row" style="display: none;">' . $nit . '</td>
            <td scope="row">' . $compl_name . '</td>
            <td scope="row">' . $no_identifica . '</td>
            <td scope="row">' . $date_birth . '</td>
            <td scope="row"> <button style="--bs-btn-padding-y: .25rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .75rem;" type="button" class="btn btn-success btn-sm" onclick= "cuentascli(`' . $idcod_cliente . '`,`' . $compl_name . '`)" >Aceptar</button> </td>
      </tr> ';
    }
}

?>
<div class="modal" tabindex="-1" id="prueba_modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Modal title</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Modal body text goes here.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>

<!-- ---------------------------------TERMINA EL MODAL  -->
<div class="modal" id="findaportcta">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <?php $titlemodule = $_ENV['APR_NAME_MODULE'] ?? "Aportaciones"; ?>
                <h4 class="modal-title">Busqueda de cuentas de <?= $titlemodule ?></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tblahomcta" class="table table-striped table-hover"
                        style="width: 100% !important; font-size: 12px;">
                        <thead>
                            <tr>
                                <th scope="col">Codigo</th>
                                <th scope="col" style="display: none;">Nit</th>
                                <th scope="col">Nombre Completo</th>
                                <th scope="col">No. Identificación</th>
                                <th scope="col">Nacimiento</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="categoria_tb">
                            <?php
                            BuscarCli();
                            ?>
                        </tbody>
                    </table>
                </div>

                <br>
                <div class="panel panel-primary">
                    <div class="panel-heading" id="cliente-cuentas-heading">CUENTAS DEL CLIENTE</div>
                    <div class="panel-body">
                        <div class="table-responsive-sm">
                            <table id="tblahomct" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Codigo de cuenta</th>
                                        <th>Prestamo</th>
                                        <th>Libreta</th>
                                        <th>Opciones</th>
                                    </tr>
                                </thead>
                                <tbody id="cuentas_tb">
                                </tbody>
                            </table>
                        </div>
                    </div>

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
    function cuentascli(id, nit) {
        loaderefect(1);
        var condi = "cuentas_aport_cli_temp";
        $.ajax({
            url: "../src/cris_modales/modal.php",
            method: "POST",
            data: {
                condi,
                id,
                nit
            },
            success: function (data) {
                $('#cuentas_tb').html(data);
                loaderefect(0);
                var heading = document.getElementById("cliente-cuentas-heading");
                heading.innerHTML = "CUENTAS DEL CLIENTE: " + nit + " - " + id;
            }
        })
    }

    //metodo que servira para resetear el modal
    $('#findaportcta').on('hide.bs.modal', function () {
        //Uso el método .empty() para eliminar todo el contenido dentro de .modal-body
        $('#findaportcta #cuentas_tb').empty();
    })

    // para cuentas de ahorro
    $(document).ready(function () {
        var table = $('#tblahomcta').on('search.dt')
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
        $('#tblahomcta tbody').on('click', 'tr', function () {
            var datos = table.row(this).data();
            cuentascli(datos[0], datos[2]);
        });
    });
</script>