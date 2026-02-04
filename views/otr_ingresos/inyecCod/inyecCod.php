<?php
session_start();
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
// include '../../src/funcphp/func_gen.php';
//include '../../src/funcphp/fun_ppg.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$codusu = $_SESSION["id"];

$condi = $_POST["condi"]; //CONDICION QUE SE TIENEN QUE EJECUTAR
switch ($condi) {
    case 'rep_otroGas':
        ?>
        <table class="table" id="otr_gastos">
            <thead class="table-dark">
                <tr>
                    <th>No.</th>
                    <th>Tipo</th>
                    <th>Gasto</th>
                    <th>Grupo</th>
                    <th>Nomenclatura</th>
                    <th>Tipo Línea</th>
                    <th>Opciones</th>
                </tr>
            </thead>
            <tbody id="rep_otroGas">
                <?php
                $consulta = mysqli_query($conexion, "SELECT
                    tp.id_nomenclatura AS idNom,
                    tp.id,
                    tp.nombre_gasto AS nombre,
                    tp.grupo,
                    CONCAT(nom.ccodcta,' - ',nom.cdescrip) AS des,
                    tp.tipo,
                    tp.tipoLinea
                    FROM otr_tipo_ingreso AS tp
                    INNER JOIN ctb_nomenclatura AS nom ON nom.id = tp.id_nomenclatura
                    WHERE tp.estado = 1
                    ORDER BY tp.nombre_gasto ASC");
                $con = 0;
    
                ob_start();
                while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    // Aseguramos el orden correcto de los datos para el implode
                    $dataArray = array(
                        $row['idNom'],    // Para #idNom
                        $row['id'],       // Para #idReg
                        $row['nombre'],   // Para #gasto
                        $row['grupo'],    // Para #grupo
                        $row['des'],      // Para #nomenclatura
                        $row['tipo'],     // Para #idSelect
                        $row['tipoLinea'] // Para #idSelect2
                    );
                    $data = implode("||", $dataArray);
                    $con += 1;
                ?>
                    <tr>
                        <td><?= $con ?></td>
                        <td><?= (($row['tipo'] == 1) ? 'Ingreso' : 'Egreso') ?></td>
                        <td><?= $row['nombre'] ?></td>
                        <td><?= $row['grupo'] ?></td>
                        <td><?= $row['des'] ?></td>
                        <td><?= (($row['tipoLinea'] == 'B') ? 'Bien' : 'Servicio') ?></td>
                        <td>
                            <button type="button" class="btn btn-success" 
                                onclick="editarRegistro('<?= $data ?>')">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" class="btn btn-danger" 
                                onclick="eliminar('<?= $row['id'] ?>', 'eli_otrGasto', '<?= $codusu ?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
        <script>
            // Inicializar DataTable
            $(document).ready(function() {
                $('#otr_gastos').DataTable({
                    "language": {
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron registros",
                        "info": "Mostrando página _PAGE_ de _PAGES_",
                        "infoEmpty": "No hay registros disponibles",
                        "infoFiltered": "(filtrado de _MAX_ registros totales)",
                        "search": "Buscar:",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    }
                });
            });
    
            // Función para manejar la edición
            function editarRegistro(data) {
                capData(
                    ['#idNom', '#idReg', '#gasto', '#grupo', '#nomenclatura', '#idSelect', '#idSelect2'],
                    data
                );
                verEle(['#btnAct', '#btnCan'], 1);
                verEle(['#btnGua']);
            }
        </script>
        <?php
        $output = ob_get_clean();
        echo $output;
    break;

    case 'otr_editR':
        ob_start();
        $usuario = $_SESSION["id"];
        $where = "";
        $mensaje_error = "";
        $bandera_error = false;
        //Validar si ya existe un registro igual que el nombre
        $nuew = "ccodusu='$usuario' AND (dfecsis BETWEEN '" . date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days')) . "' AND  '" . date('Y-m-d') . "')";
        try {
            $stmt = $conexion->prepare("SELECT IF(tu.puesto='ADM' OR tu.puesto='GER', '1=1', ?) AS valor FROM tb_usuario tu WHERE tu.id_usu = ?");
            if (!$stmt) {
                throw new Exception("Error en la consulta: " . $conexion->error);
            }
            $stmt->bind_param("ss", $nuew, $usuario);
            if (!$stmt->execute()) {
                throw new Exception("Error al consultar: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $whereaux = $result->fetch_assoc();
            $where = $whereaux['valor'];
        } catch (Exception $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            $bandera_error = true;
        }
        ?>
        <div class="container mt-3">
            <h5>Registros </h5>
            <table class="table table-striped nowrap" style="width: 100%;" id="otr_Recibos">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Recibo</th>
                        <th>Cliente</th>
                        <th>Opción</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <!-- FIN Tabla de nomenclatura -->
            <script>
                $(document).ready(function() {
                    $("#otr_Recibos").DataTable({
                        "processing": true,
                        "serverSide": true,
                        "sAjaxSource": "../src/server_side/otr_recibo.php",
                        columns: [{
                                data: [1]
                            },
                            {
                                data: [2]
                            },
                            {
                                data: [3]
                            },
                            {
                                data: [0],
                                render: function(data, type, row) {
                                    data1 = row.join('||');
                                    if (row[6] == "1") {
                                        btn1 = `<button type="button" class="btn btn-primary btn-sm" onclick="reportes([[],[],[],['${row[0]}']], 'pdf', 'recibo',0)"><i class="fa-solid fa-print"></i></button>`;
                                        btn2 = `<button type="button" class="btn btn-success btn-sm mx-1" onclick="esp(['#idRec', '#fecha', '#recibo', '#cliente', '#descrip'], '${data1}');verEle(['#contenedor1']);verEle(['#contenedor2'],1);"><i class="fa-solid fa-pen-to-square"></i></button>`;
                                        btn3 = `<button type="button" class="btn btn-danger btn-sm" onclick="eliminar('${row[0]}', 'eli_otrRecibo', ['<?= $usuario; ?>'])"><i class="fa-solid fa-trash"></i></button>`;
                                    }
                                    return btn1 + btn2 + btn3;
                                }
                            },

                        ],
                        "fnServerParams": function(aoData) {
                            //PARAMETROS EXTRAS QUE SE LE PUEDEN ENVIAR AL SERVER ASIDE
                            aoData.push({
                                "name": "whereextra",
                                "value": "<?= $where; ?>"
                            });
                        },
                        "bDestroy": true,
                        "language": {
                            "lengthMenu": "Mostrar MENU registros",
                            "zeroRecords": "No se encontraron registros",
                            "info": " ",
                            "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                            "infoFiltered": "(filtrado de un total de: MAX registros)",
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
        </div>
        <?php
        $output = ob_get_clean();
        echo $output;
        break;

    case 'otr_tbGastos':
        $id = $_POST['extra'];
        ob_start();
        ?>
        <!-- INI TABLA -->
        <div class="container mt-3">
            <input type="text " id="tipoG" disabled>
            <table class="table table-hover" id="tbRecibo">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo de Ingreso</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- INI INFO -->
                    <?php
                    //Obtener informacion de las cuenta, cliente y cuenta...
                    $consulta = mysqli_query($conexion, "SELECT paMov.id, paMov.id_otr_tipo_ingreso AS idG,(SELECT nombre_gasto FROM otr_tipo_ingreso WHERE id = paMov.id_otr_tipo_ingreso) AS nomG,paMov.monto, ingre.tipo AS tip
                    FROM otr_pago_mov AS paMov
                    INNER JOIN otr_tipo_ingreso AS ingre ON ingre.id = paMov.id_otr_tipo_ingreso WHERE id_otr_pago = $id");
                    $con = 0;
                    while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                        $con += 1;
                    ?>
                        <!-- seccion de datos -->
                        <tr id="<?= 'no' . $con ?>"><!-- Identificador de la fila -->
                            <td name="idR[]"><?= $row['id'] ?></td> <!-- ID de registro -->
                            <td name="idG[]" hidden><?= $row['idG'] ?></td> <!-- ID de gasto -->
                            <td name="otr_gasto[]"><?= $row['nomG'] ?></td> <!-- nombre -->
                            <td name="monto[]"><?= $row['monto'] ?></td> <!-- monto -->
                        </tr>
                        <script>
                            $('#tipoG').val(<?= $row['tip'] ?>)
                        </script>
                    <?php

                    }
                    ?>
                    <!-- FIN INFO -->
                </tbody>
            </table>
        </div>
        <!-- FIN TABLA -->
        <?php
        $output = ob_get_clean();
        echo $output;
        break;

    case 'tipo_ingreso': //Ingreso o Egreso

        $tipoIngreso = $_POST['extra'];
        ob_start();
        ?>

        <div class="modal fade" id="otr_Ingresos" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalLabel">Otros gastos</h1>
                        <button type="hidden" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        <input type="hidden" id="id_modal_hidden" value="" readonly>
                    </div>
                    <div class="modal-body">
                        <!-- INICIO Tabla de nomenclatura -->
                        <div class="container mt-3">
                            <h5>Registros </h5>
                            <table class="table" id="tbOtrosG">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No.</th>
                                        <th>Tipo de Gasto</th>
                                        <th>Grupo</th>
                                        <th>Opciones</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <!--Inicio de la tb Modal-->
                                    <?php
                                    $consulta = mysqli_query($conexion, "SELECT id, nombre_gasto AS nomGasto, grupo FROM otr_tipo_ingreso WHERE estado = 1 AND tipo = $tipoIngreso");
                                    $con = 0;
                                    while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                        $con += 1;
                                        $data = $row['nomGasto'] . '||' . $row['id'];
                                    ?>
                                        <!-- seccion de datos -->
                                        <tr>
                                            <td><?= $con ?></td>
                                            <td><?= $row['nomGasto'] ?></td>
                                            <td><?= $row['grupo'] ?></td>
                                            <td>

                                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="capData(['#otr_gasto','#idTG'], '<?= $data ?>')">Seleccionar</button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <!--Fin de la tb Modal-->

                                </tbody>
                            </table>
                            <script>
                                $(document).ready(function() {
                                    $('#tbOtrosG').on('search.dt')
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
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $output = ob_get_clean();
        echo $output;

        break;
    case 'cargarbancos':
        $sq = mysqli_query($conexion, "SELECT tb_bancos.id, tb_bancos.abreviatura, ctb_bancos.numcuenta FROM tb_bancos
                            INNER JOIN ctb_bancos ON tb_bancos.id = ctb_bancos.id_banco
                            WHERE tb_bancos.estado = 1 AND ctb_bancos.estado = 1
                            ORDER BY tb_bancos.id ASC;");
        $cuentasv = [];
        while ($row = mysqli_fetch_assoc($sq)) {
            $cuentasv[] = $row; // Agregar cada fila al array
        }
        if (!empty($cuentasv)) {
            $response = [
                'status' => 'success', // Añadir un estado de éxito
                'cuentas' => $cuentasv,
            ];
        }else{
            $response = [
                'status' => 'success', // Añadir un estado de éxito
                'cuentas' => 'NO EXISTEN CUENTAS DISPONIBLES',
            ];
        }
        
        echo json_encode($response);
        break;
}
?>