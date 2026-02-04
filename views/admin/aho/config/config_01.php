<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];
$codusu = $_SESSION['id'];

switch ($condi) {
    case 'config_01': {
            $id = $_POST["xtra"];
            // print_r($id);
?>
<!-- Crud para agregar, editar y eliminar tipo de gastos  -->
<input type="text" id="file" value="config_01" style="display: none;">
<input type="text" id="condi" value="gastos" style="display: none;">
<div class="text" style="text-align:center">Configuraciones Libreta de Ahorros</div>

<div class="card">
    <div class="card-header">
        Configuraciónes
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path
                d="M19.14 12.936c.014-.305.02-.615.02-.936s-.007-.63-.02-.936l2.11-1.65c.192-.15.247-.42.12-.64l-2-3.464a.494.494 0 0 0-.592-.22l-2.49 1a8.77 8.77 0 0 0-1.616-.936l-.38-2.65a.502.502 0 0 0-.496-.42h-4a.502.502 0 0 0-.497.42l-.38 2.65a8.767 8.767 0 0 0-1.615.936l-2.491-1a.498.498 0 0 0-.592.22l-2 3.464a.504.504 0 0 0 .12.64l2.11 1.65c-.014.306-.02.616-.02.936s.007.63.02.936l-2.11 1.65a.504.504 0 0 0-.12.64l2 3.464c.136.23.43.31.68.22l2.49-1c.51.38 1.05.7 1.615.936l.38 2.65a.502.502 0 0 0 .497.42h4c.246 0 .455-.177.496-.42l.38-2.65c.566-.236 1.106-.556 1.616-.936l2.49 1c.25.09.545.01.68-.22l2-3.464a.504.504 0 0 0-.12-.64l-2.11-1.65zM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z" />
        </svg>
    </div>
    <style>
.boton-lapiz {
    background-color: #4CAF50; /* Verde */
    border: none;
    color: white;
    padding: 12px 16px;
    font-size: 16px;
    cursor: pointer;
    border-radius: 8px;
    transition: background-color 0.3s, transform 0.3s;
}

.boton-lapiz:hover {
    background-color: #45a049; /* Verde más oscuro */
    transform: scale(1.1);
}

.boton-lapiz:focus {
    outline: none;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
}

.icono-lapiz {
    margin-right: 8px;
}
    /* logo de tuerca */
    .card-header svg {
        width: 24px;
        height: 24px;
        margin-left: 8px;
        fill: #333;
    }
    </style>
    <style>
    .form-group {
        position: relative;
    }

    .help-icon {
        position: absolute;
        right: 10px;
        top: 10px;
        cursor: pointer;
    }

    .tooltip-inner {
        max-width: 200px;
        width: auto;
        background-color: #f7f7f7;
        color: #333;
        border: 1px solid #ccc;
    }

    </style>
    </head>

    <body>
        <table id="table_id2" class="table table-hover table-border">
            <thead class="text-light table-head-aprt" style="font-size: 0.8rem;">
                <tr>
                    <th>ID</th>
                    <th>Nombre de la libreta</th>
                    <th>No. lineas (F)</th>
                    <th>No. lineas (R)</th>
                    <th>Comienzo impresión (F)</th>
                    <th>Comienzo impresión (R)</th>
                    <th>Eje X Libreta</th>
                    <th>Eje Y Libreta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT * FROM ahomtip WHERE estado=1;";
                $result = $conexion->query($query);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?= $row["id_tipo"] ?></td>
                    <td><?= $row["nombre"] ?></td>
                    <td><?= $row["numfront"] ?></td>
                    <td><?= $row["numdors"] ?></td>
                    <td><?= $row["front_ini"] ?></td>
                    <td><?= $row["dors_ini"] ?></td>
                    <td><?= $row["xlibreta"] ?></td>
                    <td><?= $row["ylibreta"] ?></td>
                    <td>
                        <button class="boton-lapiz" onclick="viewData(this)" data-toggle="modal"
                            data-target="#editModal">
                            <i class="fas fa-pencil-alt icono-lapiz"></i>
                        </button>
                    </td>
                </tr>
                <?php
                    }
                } else { ?>
                <tr>
                    <td colspan='9'>No se encontraron resultados en la consulta.</td>
                </tr>
                <?php }
            ?>
            </tbody>
        </table>

        <!-- Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Editar Registro</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <div class="form-group">
                        <label for="id_tipo">ID</label>
                        <input type="text" class="form-control" id="id_tipo" readonly>
                        <i class="fas fa-question-circle help-icon" data-toggle="tooltip" data-placement="top" title="Este es el ID del tipo de libreta, no se puede modificar."></i>
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre de la libreta</label>
                        <input type="text" class="form-control" id="nombre" readonly>
                        <i  data-toggle="tooltip" data-placement="top" title="Nombre de la libreta."></i>
                    </div>
                    <div class="form-group">
                        <label for="numfront">No. líneas (F)</label>
                        <input type="number" class="form-control" id="numfront">
                        <div class="tooltip">
                        <i class="fas fa-question-circle help-icon" data-toggle="tooltip" data-placement="top" title="Número de líneas en la parte frontal de la libreta."></i>
                    </div>
                    <div class="form-group">
                        <label for="numdors">No. líneas (R)</label>
                        <input type="number" class="form-control" id="numdors">
                        <i class="fas fa-question-circle help-icon" data-toggle="tooltip" data-placement="top" title="Número de líneas en la parte trasera de la libreta."></i>
                    </div>
                    <div class="form-group">
                        <label for="front_ini">Comienzo impresión (F)</label>
                        <input type="number" class="form-control" id="front_ini">
                        <i class="fas fa-question-circle help-icon" data-toggle="tooltip" data-placement="top" title="Línea de comienzo para impresión frontal."></i>
                    </div>
                    <div class="form-group">
                        <label for="dors_ini">Comienzo impresión (R)</label>
                        <input type="number" class="form-control" id="dors_ini">
                        <i class="fas fa-question-circle help-icon" data-toggle="tooltip" data-placement="top" title="Línea de comienzo para impresión trasera."></i>
                    </div>
                    <div class="form-group">
                        <label for="xlibreta">Eje X Libreta</label>
                        <input type="number" class="form-control" id="xlibreta">
                        <i class="fas fa-question-circle help-icon" data-toggle="tooltip" data-placement="top" title="Posición en el eje X para la impresión de la libreta."></i>
                    </div>
                    <div class="form-group">
                        <label for="ylibreta">Eje Y Libreta</label>
                        <input type="number" class="form-control" id="ylibreta">
                        <i class="fas fa-question-circle help-icon" data-toggle="tooltip" data-placement="top" title="Posición en el eje Y para la impresión de la libreta."></i>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <button type="cancel" class="btn btn-danger">Cancelar</button>
                </form>
            </div>
        </div>
    </div>
</div>

</div>
<script>
$(function () {
  $('[data-toggle="tooltip"]').tooltip();
});

function viewData(button) {
    var row = $(button).closest("tr");
    var id = row.find("td:eq(0)").text();
    var nombre = row.find("td:eq(1)").text();
    var numfront = row.find("td:eq(2)").text();
    var numdors = row.find("td:eq(3)").text();
    var front_ini = row.find("td:eq(4)").text();
    var dors_ini = row.find("td:eq(5)").text();
    var xlibreta = row.find("td:eq(6)").text();
    var ylibreta = row.find("td:eq(7)").text();

    $("#id_tipo").val(id);
    $("#nombre").val(nombre);
    $("#numfront").val(numfront);
    $("#numdors").val(numdors);
    $("#front_ini").val(front_ini);
    $("#dors_ini").val(dors_ini);
    $("#xlibreta").val(xlibreta);
    $("#ylibreta").val(ylibreta);
}
</script>

<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


<?php
        }
        break;
}
    ?>