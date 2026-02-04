<?php
// Endpoint para listar credenciales con manejo robusto de errores
header('Content-Type: application/json; charset=utf-8');

// Limpiar cualquier salida previa
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

try {
    require_once __DIR__ . '/../../includes/BD_con/db_con.php';
    require_once __DIR__ . '/../../includes/BD_con/conexion_bank.php';
    
    // Verificar conexiones
    if (!isset($virtual) || !$virtual) {
        ob_clean();
        die(json_encode(['data' => [], 'error' => 'No hay conexión a base de datos virtual']));
    }
    
    if (!isset($conexion) || !$conexion) {
        ob_clean();
        die(json_encode(['data' => [], 'error' => 'No hay conexión a base de datos principal']));
    }
    
    $data = [];
    
    // Consultar usuarios de banca virtual
    $query = "SELECT id, email AS codcli, name AS usuario, password, created_at 
              FROM users 
              ORDER BY created_at DESC 
              LIMIT 200";
    
    $result = $virtual->query($query);
    
    if (!$result) {
        ob_clean();
        die(json_encode(['data' => [], 'error' => 'Error en consulta: ' . $virtual->error]));
    }
    
    while ($row = $result->fetch_assoc()) {
        // Buscar datos completos del cliente
        $nombreCliente = 'Cliente: ' . $row['codcli'];
        $emailCliente = '';
        $telefonoCliente = '';
        
        $stmtCliente = $conexion->prepare("SELECT compl_name, email, tel_no1, tel_no2 
                                           FROM tb_cliente 
                                           WHERE idcod_cliente = ? 
                                           LIMIT 1");
        if ($stmtCliente) {
            $stmtCliente->bind_param('s', $row['codcli']);
            $stmtCliente->execute();
            $resultCliente = $stmtCliente->get_result();
            
            if ($resultCliente && $resultCliente->num_rows > 0) {
                $clienteData = $resultCliente->fetch_assoc();
                $nombreCliente = $clienteData['compl_name'] ?? $nombreCliente;
                $emailCliente = $clienteData['email'] ?? '';
                $telefonoCliente = !empty($clienteData['tel_no1']) ? $clienteData['tel_no1'] : ($clienteData['tel_no2'] ?? '');
            }
            $stmtCliente->close();
        }
        
        // Escapar valores para HTML y JavaScript
        $id = (int)$row['id'];
        $usuario = htmlspecialchars($row['usuario'] ?? '', ENT_QUOTES, 'UTF-8');
        $codcli = htmlspecialchars($row['codcli'] ?? '', ENT_QUOTES, 'UTF-8');
        $emailCliente = htmlspecialchars($emailCliente, ENT_QUOTES, 'UTF-8');
        $telefonoCliente = htmlspecialchars($telefonoCliente, ENT_QUOTES, 'UTF-8');
        
        // Escapar para JavaScript (reemplazar comillas y saltos de línea)
        $usuarioJs = addslashes($usuario);
        $codcliJs = addslashes($codcli);
        $emailJs = addslashes($emailCliente);
        $telefonoJs = addslashes($telefonoCliente);
        
        // Botones de acción con Tailwind CSS - usando data attributes para evitar problemas con comillas
        $btnEmail = !empty($emailCliente) 
            ? "<button type='button' class='px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded transition duration-200 flex items-center gap-1 shadow-sm' 
                   title='Enviar por correo' 
                   data-id='{$id}' 
                   data-email='{$emailJs}' 
                   data-usuario='{$usuarioJs}' 
                   data-codcli='{$codcliJs}'
                   onclick='enviarPorCorreo({$id}, \"{$emailJs}\", \"{$usuarioJs}\", \"{$codcliJs}\")'>
                   <i class='fa-solid fa-envelope'></i>
                   <span>Email</span>
               </button>"
            : "<button type='button' class='px-3 py-1.5 bg-gray-400 text-white text-xs font-medium rounded cursor-not-allowed opacity-50' disabled title='Sin email'>
                   <i class='fa-solid fa-envelope'></i>
               </button>";
        
        $btnWhatsApp = !empty($telefonoCliente) 
            ? "<button type='button' class='px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-xs font-medium rounded transition duration-200 flex items-center gap-1 shadow-sm' 
                   title='Enviar por WhatsApp' 
                   data-telefono='{$telefonoJs}' 
                   data-usuario='{$usuarioJs}' 
                   data-codcli='{$codcliJs}'
                   onclick='enviarPorWhatsApp(\"{$telefonoJs}\", \"{$usuarioJs}\", \"{$codcliJs}\")'>
                   <i class='fa-brands fa-whatsapp'></i>
                   <span>WhatsApp</span>
               </button>"
            : "<button type='button' class='px-3 py-1.5 bg-gray-400 text-white text-xs font-medium rounded cursor-not-allowed opacity-50' disabled title='Sin teléfono'>
                   <i class='fa-brands fa-whatsapp'></i>
               </button>";
        
        $acciones = "<div class='flex flex-wrap gap-1.5 items-center justify-center'>
            {$btnEmail}
            {$btnWhatsApp}
            <button type='button' class='px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-medium rounded transition duration-200 flex items-center gap-1 shadow-sm' 
                title='Editar credencial' 
                onclick='editarCredencial({$id}, \"{$usuarioJs}\", \"{$codcliJs}\")'>
                <i class='fa-solid fa-edit'></i>
                <span>Editar</span>
            </button>
            <button type='button' class='px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-xs font-medium rounded transition duration-200 flex items-center gap-1 shadow-sm' 
                title='Eliminar credencial' 
                onclick='eliminarCredencial({$id}, \"{$usuarioJs}\")'>
                <i class='fa-solid fa-trash'></i>
                <span>Eliminar</span>
            </button>
        </div>";
        
        $data[] = [
            'id' => $id,
            'codcli' => $codcli,
            'nombre' => htmlspecialchars($nombreCliente, ENT_QUOTES, 'UTF-8'),
            'usuario' => $usuario,
            'email_cliente' => $emailCliente,
            'telefono_cliente' => $telefonoCliente,
            'acciones' => $acciones
        ];
    }
    $result->free();
    
    // Limpiar buffer y enviar respuesta
    ob_clean();
    echo json_encode(['data' => $data]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['data' => [], 'error' => $e->getMessage()]);
}

ob_end_flush();
exit;
?>
