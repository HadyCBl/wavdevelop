<!-- ---------------------------------TERMINA EL MODAL -->
<?php
function grupos()
{
    include '../../includes/BD_con/db_con.php';
    mysqli_set_charset($conexion, 'utf8');
    $consulta =mysqli_query($conexion, "SELECT * FROM `cv_receptor`");

    // $aux = mysqli_error($conexion); 
    // echo $aux;return; 

    while ($registro = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $id = ($registro["id"]);
        $nit = ($registro["id_receptor"]);
        $nombre = ($registro["nombre"]);
        $nombre2 = str_replace(["'", '"'], "", $nombre);
        $direccion = ($registro["direccion"]);
        $correo =($registro["correo"]??'');
        echo '
        <tr> 
                <td style="width: 5%;">' . $id . '</td>
                <td style="width: 15%;">' . $nit . '</td>
                <td style="width: 40%;">' . $nombre . '</td>
                <td style="width: 30%;">' . $direccion . '</td>
                <td style="width: 10%;"> 
                    <button type="button" class="btn btn-success" onclick="selecliente(&apos;' . htmlspecialchars($id, ENT_QUOTES) . '&apos;,&apos;' . 
                    htmlspecialchars($nit, ENT_QUOTES) . '&apos;,&apos;' . htmlspecialchars($nombre2, ENT_QUOTES) . '&apos;,&apos;' . 
                    htmlspecialchars($correo, ENT_QUOTES) . '&apos;,&apos;' . htmlspecialchars($direccion, ENT_QUOTES) . '&apos;)"
                    data-bs-dismiss="modal">Seleccionar</button>
                </td>
        </tr> ';
    }
}


?>

<!-- ---------------------------------TERMINA EL MODAL  TABLA DE GRUPOS  PARA BUSCAR A LOS GRUPOS-->
<div class="modal fade" id="buscargrupo">
    <div class="modal-dialog modal-xl ">
        <div class="modal-content" style=" margin-left: 5%;">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Busqueda de cliente</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                <table id="table_id3" class="table table-striped">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 15%;">NIT</th>
                            <th style="width: 40%;">Nombre</th>
                            <th style="width: 30%;">Dirección</th>
                            <th style="width: 10%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="categoria_tb">

                        <?php
                        grupos();
                        ?>
                    </tbody>
                </table>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<!-- ---------------------------------TERMINA EL MODAL  TABLA DE GRUPOS -->
<script>
    var ref = $('#table_id3').DataTable({
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
        }
    });
</script>



<!--------------------------------------------------------------------------------------------------------------------->
