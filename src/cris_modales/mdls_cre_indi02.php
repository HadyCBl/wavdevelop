<?php
//Coneccion a la base de datos
include_once '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
?>
<!-- INICIA EL MODAL PARA EL CLIENTE QUE DEJAR SU GARANTIA -->
<!-- Modal de clientes -->
<div class="row">
  <div class="col-lg-8 col-md-12">

    <div class="modal fade" id="modalCliente" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="staticBackdropLabel">Datos de clientes</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">

            <!-- INICIO DE LA TABLA -->
            <div class="container mt-3 table-responsive">
              <h2>Clientes </h2>
              <table class="table" id="tbCliente" class="table table-hover table-border nowrap" style="width:100%">
                <thead class="table-dark">
                  <tr>
                    <th>Código cliente</th>
                    <th>Cliente</th>
                    <!-- <th>Tipo de Cliente</th> -->
                    <th>Opciones</th>
                  </tr>
                </thead>
                <tbody style="font-size: 0.9rem !important;">

              </table>

              <script>
                $(document).ready(function() {
                  $("#tbCliente").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../../src/server_side/cliente_garantia.php",
                    columns: [{
                        data: [1]
                      },
                      {
                        data: [2]
                      },
                      // {
                      //   data: [3]
                      // },

                      {
                        data: [0],
                        render: function(data, type, row) {
                          // console.log("Datos: " + data);

                          let imp = `<button type="button" id="btnSelec" class="btn btn-primary" onclick="printdiv2('#cuadro', '${row[1]}', 2);cerrarModal('#modalCliente');">Seleccionar</button>`;
                          return imp;

                        }
                      },

                    ],
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
                    },
                    "complete": function(data) {
                      console.log(data);
                    }
                  });
                });
              </script>
            </div>

            <!-- FIN DE LA TABLA -->

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa-solid fa-rectangle-xmark"></i> Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- INICIA EL MODAL PARA Fiadores -->
<!-- Modal de Fiador -->
<div class="row">
  <div class="col-lg-8 col-md-12">

    <div class="modal fade" id="modalFiador" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="staticBackdropLabel">Fiador</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">

            <!-- INICIO DE LA TABLA -->

            <div class="container mt-3 table-responsive">
              <h2>Fiador </h2>
              <table class="table" id="tbFiador" class="table table-hover table-border nowrap" style="width:100%">
                <thead class="table-dark">
                  <tr>
                    <th>No.</th>
                    <th>Código cliente</th>
                    <th>Cliente</th>
                    <th>Opciones</th>
                  </tr>
                </thead>
                <tbody style="font-size: 0.9rem !important;">
                  <!-- INI de la información -->

              </table>

              <script>
                function cargarDatos(codFiador = 0) {

                  $("#tbFiador").DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../../src/server_side/fiador_garantia.php",
                    columns: [{
                        data: [0]
                      },
                      {
                        data: [1]
                      },
                      {
                        data: [2]
                      },
                      {
                        data: [0],
                        render: function(data, type, row) {
                          const separador = "||";
                          var dataRow = row.join(separador);
                          // console.log("Datos "+dataRow);
                          var imp = `<button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="capfiador(${"'"+dataRow+"'"})">Seleccionar</button>`;
                          return imp;

                        }
                      },

                    ],
                    "fnServerParams": function(aoData) {
                      //PARAMETROS EXTRAS QUE SE LE PUEDEN ENVIAR AL SERVER ASIDE
                      aoData.push({
                        "name": "whereextra",
                        "value": "idcod_cliente !=" + codFiador
                      });
                    },
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


                }
              </script>

            </div>

            <!-- FIN DE LA TABLA -->

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa-solid fa-rectangle-xmark"></i> Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- Modal para, bsucar al cliente y su numero de cuenta -->
<div class="row">
  <div class="col-lg-8 col-md-12">

    <div class="modal fade" id="cuentaYcli" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="staticBackdropLabel">Datos de clientes</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">

            <!-- INICIO DE LA TABLA -->

            <div class="container mt-3 table-responsive">
              <h2>Clientes </h2>
              <table class="table" id="tbcuentaYcli">
                <thead class="table-dark">
                  <tr>
                    <th>No</th>
                    <th>Cliente</th>
                    <th>No. de cuenta</th>
                    <th>Monto</th>
                    <th>Fec. Desembolso</th>
                    <th>Opciones</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- INI de la información -->
                  <?php
                  $consulta = mysqli_query($conexion, "SELECT credi.CCODCTA AS codCu, cli.short_name AS nombre, credi.NCapDes AS capDes, credi.DFecDsbls as fecha_desem from
                  tb_cliente AS cli 
                  INNER JOIN cremcre_meta AS credi ON cli.idcod_cliente = credi.CodCli
                  WHERE credi.cestado = 'F' AND credi.TipoEnti = 'INDI'");

                  $con = 0;
                  while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $codCu = $row["codCu"];
                    $nombre = $row["nombre"];
                    $capDes = number_format($row["capDes"], 2, '.', ',');
                    $fecha_desem = date("d/m/Y", strtotime($row["fecha_desem"]));

                    $con = $con + 1;
                    $data = implode("||", $row)
                    // $data = $codCu . "||" .
                    // $nombre;

                  ?>
                    <!-- seccion de datos  -->
                    <tr>
                      <td><?= $con ?></td>
                      <td><?= $nombre ?></td>
                      <td><?= $codCu ?></td>
                      <td><?= $capDes ?></td>
                      <td><?= $fecha_desem ?></td>
                      <td>
                        <button type="button" id="btnSelec" class="btn btn-primary" onclick="capData('<?php echo $data ?>',['#codCu', '#usuCli', '#desembolso1']); cerrarModal('#cuentaYcli');inyecCod('#dataPlanPago','PlanPagos','<?php echo $codCu ?>')">Seleccionar</button>
                      </td>
                    </tr>

                  <?php } ?>
              </table>
              <script>
                $(document).ready(function() {
                  inicializarDataTable('tbcuentaYcli');
                });
              </script>
            </div>

            <!-- FIN DE LA TABLA -->

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fa-solid fa-rectangle-xmark"></i> Cerrar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>