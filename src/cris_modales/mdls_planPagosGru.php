<?php
//Coneccion a la base de datos
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
?>
<script>
  var promesa1;
  var promesa2;
  var promesa2;
</script>

<!-- Modal -->
<div class="modal fade" id="gurposPlanPagos" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog  modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="staticBackdropLabel">Grupos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <!-- INICIO DE LA TABLA -->
        <div class="container mt-3 table-responsive">
          <h2>Grupos </h2>
          <table class="table" id="tbGrupos">
            <thead class="table-dark">
              <tr>
                <th>No.</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Ciclo</th>
                <th>Opciones</th>
              </tr>
            </thead>
            <tbody>
              <!-- INI de la información -->
              <?php

              $consulta = mysqli_query($conexion, "SELECT gru.NombreGrupo AS nombre, gru.codigo_grupo AS codigo, gru.id_grupos AS id, creMet.NCiclo AS ciclo
              FROM cremcre_meta AS creMet
              INNER JOIN tb_grupo AS gru ON creMet.CCodGrupo = gru.id_grupos
              INNER JOIN tb_cliente_tb_grupo AS gruCli ON  gruCli.Codigo_grupo = gru.id_grupos
              WHERE  creMet.TipoEnti = 'GRUP' AND creMet.Cestado = 'F' AND gruCli.estado = 1 AND gru.estado = 1 GROUP BY gru.id_grupos,creMet.NCiclo");

              $con = 0;
              while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $data = implode("|*|", $row);
                $con++;
              ?>
                <!-- seccion de datos  -->
                <tr>
                  <td><?= $con ?></td>
                  <td><?= $row['nombre'] ?></td>
                  <td><?= $row['codigo'] ?></td>
                  <td><?= $row['ciclo'] ?></td>
                  <td>
                    <!-- <button type="button" id="btnSelec" class="btn btn-primary" onclick="capData('<?= $data ?>', ['#nombreGru','#codGru', '#idGrup'],[0,1,2]); cerrarModal('#gurposPlanPagos'); inyecCod111('#dataCuoFech','couFech',['<?= $row['id'] ?>', '<?= $row['ciclo'] ?>']);inyecCod111('#dataPlanPago','planPagoGru',['<?= $row['id'] ?>', '<?= $row['ciclo'] ?>'])">Seleccionar</button> -->
                    <button type="button" id="btnSelec" class="btn btn-primary" onclick="capData('<?= $data ?>', ['#nombreGru','#codGru', '#idGrup','#nciclo'],[0,1,2,3]); cerrarModal('#gurposPlanPagos'); promesa1 = inyecCod('couFech',['<?= $row['id'] ?>', '<?= $row['ciclo'] ?>']); promesa2 = inyecCod('planPagoGru',['<?= $row['id'] ?>', '<?= $row['ciclo'] ?>']); promesa3 = inyecCod('listaDeCuetas',['<?= $row['id'] ?>', '<?= $row['ciclo'] ?>']); iniPromesa();">Seleccionar</button>
                  </td>
                </tr>

              <?php } ?>
          </table>

          <script>
            function iniPromesa() {
              Promise.all([promesa1, promesa2, promesa3])
                .then(function(responses) {
                  // Ambas promesas se han resuelto correctamente
                  var respuesta1 = responses[0];
                  var respuesta2 = responses[1];
                  var respuesta3 = responses[2];

                  // Realiza la acción de carga con las respuestas obtenidas
                  cargarInformacion(respuesta1, respuesta2, respuesta3);
                })
                .catch(function(error) {
                  // Al menos una de las promesas fue rechazada
                  console.error(error);
                });
            }

            function cargarInformacion(respuesta1, respuesta2, respuesta3) {
              // Realiza la acción de carga con las respuestas obtenidas
              // Aquí puedes utilizar las respuestas para cargar la información en la página
              $('#dataCuoFech').html(respuesta1);
              $('#dataPlanPago').html(respuesta2);
              $('#list-tab').html(respuesta3);
              conElem();
              viewEle('#cardPlanPagos', 1);
              loaderefect(0);
              // ...
            }

            //Funcion para capturar datos
            function capData(dataPhp, dataJava = 0, pos = []) {

              let data = dataPhp.split("|*|");

              if (pos.length == 0) dataPos = dataJava.length;
              else dataPos = pos.length;

              for (let i = 0; i < dataPos; i++) {
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

            function cerrarModal(modalCloss) {
              $(modalCloss).modal("hide"); // CERRAR MODAL
            }

            $(document).ready(function() {
              var table = $('#tbGrupos').on('search.dt')
                .DataTable({
                  "lengthMenu": [
                    [10, 20, 30, -1],
                    ['10 filas', '20 filas', '30 filas', 'Mostrar todos']
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
            });
          </script>
        </div>

        <!-- FIN DE LA TABLA -->

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>