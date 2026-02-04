<?php
// Alternativa: Listar credenciales con HTML directo (sin DataTables)
require_once __DIR__ . '/../../includes/BD_con/db_con.php';
require_once __DIR__ . '/../../includes/BD_con/conexion_bank.php';

if (!$virtual || !$conexion) {
    echo '<div class="alert alert-danger">Error de conexión a base de datos</div>';
    exit;
}

// Consultar usuarios
$query = "SELECT id, email AS codcli, name AS usuario, created_at FROM users ORDER BY created_at DESC LIMIT 100";
$result = $virtual->query($query);

if (!$result) {
    echo '<div class="alert alert-danger">Error en consulta: ' . htmlspecialchars($virtual->error) . '</div>';
    exit;
}
?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Código Cliente</th>
                <th>Nombre Cliente</th>
                <th>Usuario</th>
                <th>Fecha Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($result->num_rows === 0) {
                echo '<tr><td colspan="6" class="text-center">No hay credenciales registradas</td></tr>';
            } else {
                while ($row = $result->fetch_assoc()): 
                    // Buscar nombre del cliente
                    $nombreCliente = 'Cliente: ' . $row['codcli'];
                    $stmtCliente = $conexion->prepare("SELECT compl_name FROM tb_cliente WHERE idcod_cliente = ? LIMIT 1");
                    if ($stmtCliente) {
                        $stmtCliente->bind_param('s', $row['codcli']);
                        $stmtCliente->execute();
                        $resultCliente = $stmtCliente->get_result();
                        if ($resultCliente->num_rows > 0) {
                            $clienteData = $resultCliente->fetch_assoc();
                            $nombreCliente = $clienteData['compl_name'];
                        }
                        $stmtCliente->close();
                    }
            ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['codcli']) ?></td>
                <td><?= htmlspecialchars($nombreCliente) ?></td>
                <td><?= htmlspecialchars($row['usuario']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-warning" 
                                onclick="editarCredencial(<?= $row['id'] ?>, '<?= htmlspecialchars($row['usuario'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['codcli'], ENT_QUOTES) ?>')">
                            <i class="fa-solid fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" 
                                onclick="eliminarCredencial(<?= $row['id'] ?>, '<?= htmlspecialchars($row['usuario'], ENT_QUOTES) ?>')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php 
                endwhile;
            }
            $result->free();
            ?>
        </tbody>
    </table>
</div>
