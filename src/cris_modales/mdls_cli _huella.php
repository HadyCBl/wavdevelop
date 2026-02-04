<div class="modal fade" id="buscar_cli_gen" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">Busqueda de Clientes</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table id="tb_buscaClient" class="table table-striped nowrap" style="width: 100%;">
            <thead>
              <tr>
                <th scope="col">Codigo</th>
                <th scope="col">Nombre Completo</th>
                <th scope="col">No. Identificación</th>
                <th scope="col">Nacimiento</th>
                <th scope="col">Acciones</th>
              </tr>
            </thead>
            <tbody id="categoria_tb">
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    $("#tb_buscaClient").DataTable({
      "processing": true,
      "serverSide": true,
      "sAjaxSource": "../src/server_side/clientes_no_juridicos.php",
      "columnDefs": [{
        "data": 0,
        "targets": 4,
        render: function(data, type, row) {
          return `<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="capData('${row}', '#codCli, #nomCliente', '0,1')" >Aceptar</button>`;
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
</script>