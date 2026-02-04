<!-- ---------------------------------TERMINA EL MODAL -->
<?php
function buscarfac()
{
    include '../../includes/BD_con/db_con.php';
    mysqli_set_charset($conexion, 'utf8');

    $consulta =mysqli_query($conexion, "SELECT cv_facturas.id, cv_facturas.fechahora_emision, cv_facturas.codigo_autorizacion, 
                                        cv_receptor.nombre, cv_receptor.id_receptor, cv_facturas.no_autorizacion, cv_facturas.serie,
                                        SUM(cv_factura_items.total) AS total
                                        FROM cv_facturas 
                                        INNER JOIN cv_receptor ON cv_receptor.id = cv_facturas.id_receptor
                                        INNER JOIN cv_factura_items ON cv_factura_items.id_factura = cv_facturas.id
                                        WHERE cv_facturas.origen_factura = 1 AND cv_facturas.estado = 1
                                        GROUP BY cv_facturas.id;");

    // $aux = mysqli_error($conexion); 
    // echo $aux;return; 

    while ($registro = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $temp= number_format($registro["total"], 2, '.', '');
        echo '
        <tr> 
                <td>' . $registro["id"] . '</td>
                <td>' . $registro["nombre"] . '</td>
                <td>' . $registro["id_receptor"] . '</td>
                <td>' . $registro["fechahora_emision"] . '</td>
                <td>' . $registro["codigo_autorizacion"] . '</td>
                <td>' . $temp . '</td>
                <td> 
                    <button type="button" class="btn btn-success" onclick="buscarfact(&apos;' . htmlspecialchars($registro["id"], ENT_QUOTES) . '&apos;,
                        &apos;' . htmlspecialchars($registro["nombre"], ENT_QUOTES) . '&apos;,&apos;' . htmlspecialchars($registro["fechahora_emision"], ENT_QUOTES) . '&apos;,
                        &apos;' . htmlspecialchars($registro["codigo_autorizacion"], ENT_QUOTES) . '&apos;,&apos;' . htmlspecialchars($registro["no_autorizacion"], ENT_QUOTES) . '&apos;,
                        &apos;' . htmlspecialchars($registro["serie"], ENT_QUOTES) . '&apos;,&apos;' . htmlspecialchars($temp, ENT_QUOTES) . '&apos;)"
                        data-bs-dismiss="modal">
                    Seleccionar</button>
                </td>
        </tr> ';
    }
}

?>

<!--------------------------------------------------------------------------------------------------------------------->


<div class="modal fade" id="buscarfacturasdet">
    <div class="modal-dialog modal-xl ">
        <div class="modal-content" style=" margin-left: 5%;">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Buscar factura</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                <table id="table_id4" class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Receptor</th>
                            <th>Nit</th>
                            <th>Fecha</th>
                            <th>Codigo de Autorizacion</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="categoria_tb">

                        <?php
                        buscarfac();
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
    var ref = $('#table_id4').DataTable({
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

