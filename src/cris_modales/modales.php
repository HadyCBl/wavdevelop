<!-- TERMINA EL MODAL -->
<!-- AQUI EMPIZAN LOS FORMATOS DE MODALES, CREAR UN PHP CON LOS MODALES -->
<!-- The Modal  ESTE MODAL ES UNA PLANTILLA PARA LA BUSQUEDA DE LOS CLIENTES   -->
<div class="modal " id="Bscrclntgrp">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Busqueda de clientes</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal body -->
      <div class="modal-body">
        <div class="container">
          <div class="row">
            <div class="col">
              <div class="table-responsive">
                <table id="table_id4" class="table nowrap" style="width: 100% !important;">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>No. Identificación</th>
                      <th>Nombre Completo</th>
                      <th>Nacimiento</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="categoria_tb">
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
      <!-- Modal footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
      </div>

    </div>
  </div>
</div>
<!-- TERMINA EL MODAL -->

<script>
  function refresh() {
    $('#table_id3').DataTable().clear();
    var condi = "grupo";
    $.ajax({
      url: "../src/modal.php",
      method: "POST",
      data: {
        condi
      },
      success: function(data) {
        $('#table_id3').find('tbody').append(data);
      }
    })
    $('#table_id3').DataTable().draw();
  }

  $(document).ready(function() {
    $("#table_id4").DataTable({
      "processing": true,
      "serverSide": true,
      "sAjaxSource": "../src/server_side/agregar_clientes_grupos.php",
      "columnDefs": [{
        "data": 0,
        "targets": 4,
        render: function(data, type, row) {
          return `<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="instclntingrp('${data}')" >Aceptar</button>`;
          // console.log('hola');
        }

      }, ],
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
  //FIN DE SERVER ASIDE
</script>