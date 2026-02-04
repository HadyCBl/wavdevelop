 <!-- Inicia modal -->
 <div class="modal fade" id="otrosGastos" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
     <div class="modal-dialog  modal-lg">
         <div class="modal-content">
             <div class="modal-header">
                 <h1 class="modal-title fs-5" id="exampleModalLabel">Lista de nomenclatura</h1>
                 <button type="hidden" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                 <input type="hidden" id="id_modal_hidden" value="" readonly>
             </div>
             <div class="modal-body">
                 <!-- INICIO Tabla de nomenclatura -->
                 <div class="container mt-3">
                     <h2>Registros </h2>
                     <table class="table" id="tbNomenclatura">
                         <thead class="table-dark">
                             <tr>
                                 <th>ID</th>
                                 <th>Nomenclatura</th>
                                 <th>Descripción</th>
                                 <th>Opciones</th>
                             </tr>
                         </thead>
                         <tbody>

                             <!--Inicio de la tb Modal-->
                             <?php
                                $consulta = mysqli_query($conexion, "SELECT id, ccodcta AS nomenclatura, cdescrip AS descripcion FROM ctb_nomenclatura WHERE estado=1;");

                                while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                    $id2 = $row["id"];
                                    $nomenclatura = $row["nomenclatura"];
                                    $descripcion = $row["descripcion"];
                                    $data = $row['nomenclatura'].' - '.$row['descripcion'].'||'.$row['id'];
                                ?>
                                 <!-- seccion de datos -->
                                 <tr>
                                     <td><?= $id2 ?></td>
                                     <td><?= $nomenclatura ?></td>
                                     <td><?= $descripcion ?></td>
                                     <td>
                                     
                                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="capData(['#nomenclatura','#idNom'], '<?= $data?>')" >Seleccionar</button>
                                     </td>
                                 </tr>
                             <?php } ?>
                             <!--Fin de la tb Modal-->

                         </tbody>
                     </table>
                     <script>
                         $(document).ready(function() {
                             $('#tbNomenclatura').on('search.dt')
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
                         })
                     </script>
                     <!-- FIN Tabla de nomenclatura -->
                 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" onclick="cerrar_modal('#modaljalagastos', 'hide', '#id_modal_hidden')">Cerrar</button>
                 </div>
             </div>
         </div>
     </div>
     <!-- Fin modal -->

     <script>
        function capData1(dataJava='', data='', pos = []){
            var data = data.split('||');
            if (pos.length == 0) dataPos = dataJava.length;
            else dataPos = pos.length;

                for (let i = 0; i < dataPos; i++) {
                    console.log(dataJava[i]);
                    //console.log('J '+dataJava[i]+' P'+data[pos[i]]);
                    if ($(dataJava[i]).is('input')) {
                        $(dataJava[i]).val(data[pos[i]]);
                    }
                    if ($(dataJava[i]).is('label')) {
                        $(dataJava[i]).text(data[pos[i]]);
                    }
                    if ($(dataJava[i]).is('textarea')) {
                        $(dataJava[i]).val(data[pos[i]]);
                    }
                }
        }
     </script>