<!-- ---------------------------------TERMINA EL MODAL -->
<?php
function grupos()
{
    include '../../includes/BD_con/db_con.php';
    mysqli_set_charset($conexion, 'utf8');


    $consulta2 = mysqli_query($conexion, "SELECT gru.id_grupos, gru.codigo_grupo,gru.NombreGrupo, gru.direc, gru.id_grupos, gru.canton, gru.Depa, gru.Muni, muni.nombre, gru.estadoGrupo, 
    (SELECT COUNT(*) FROM cremcre_meta cm 
        INNER JOIN tb_grupo gp ON cm.CCodGrupo=gp.id_grupos INNER JOIN tb_cliente_tb_grupo cgp ON gp.id_grupos=cgp.Codigo_grupo 
        WHERE (cm.Cestado='F' OR cm.Cestado='A' OR cm.Cestado='D' OR cm.Cestado='E') AND cm.CCodGrupo=gru.id_grupos) AS controlvar 
    FROM tb_grupo AS gru 
    INNER JOIN tb_municipios AS muni ON gru.Muni = muni.codigo WHERE gru.estado = 1");

    while ($registro = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
        $codigo_grupo = ($registro["codigo_grupo"]);
        $NombreGrupo = mb_convert_encoding($registro["NombreGrupo"], 'UTF-8', 'ISO-8859-1');
        $direc = ($registro["direc"]);
        $id_grupos = ($registro["id_grupos"]);
        $canton = ($registro["canton"]);
        $Depa = ($registro["Depa"]);
        $Muni = ($registro["Muni"]);
        $nomMuni = ($registro["nombre"]);
        $estadoGrupo = ($registro["estadoGrupo"]);
        $info = $registro["controlvar"];
        $control = 0;
        if ($info >= 1) {
            $control = 1;
        }
        echo '
 <tr> 
          <td>' . $codigo_grupo . '</td>
          <td>' . $NombreGrupo . '</td>
          <td>' . $direc . '</td>
          <td> 
            <button type="button" class="btn btn-success" onclick="instgrp(&apos;' . $codigo_grupo . '&apos;, &apos;' . $NombreGrupo . '&apos;,&apos;' . $direc . '&apos;, &apos;' . $id_grupos . '&apos; ,  &apos;' . $Muni . '&apos;, &apos;' . $Depa . '&apos; , &apos;' . $canton . '&apos;, &apos;' . $nomMuni . '&apos;, &apos;' . $estadoGrupo . '&apos;, &apos;' . $control . '&apos;)" >Aceptar</button> 
          </td>
 </tr> ';
    }
}
?>

<!-- ---------------------------------TERMINA EL MODAL  TABLA DE GRUPOS  PARA BUSCAR A LOS GRUPOS-->
<div class="modal fade" id="buscargrupo">
    <div class="modal-dialog modal-lg ">
        <div class="modal-content" style=" margin-left: 20%;">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Busqueda de grupo</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                <table id="table_id3" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre del Grupo</th>
                            <th>Dirección</th>
                            <th>Acciones</th>
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