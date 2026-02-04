<?php

use App\Generic\User;
use Micro\Helpers\Log;
use App\Generic\FileProcessor;

include __DIR__ . '/../../../../includes/Config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
    return;
}

require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

include __DIR__ . '/../../../../includes/BD_con/db_con.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
date_default_timezone_set('America/Guatemala');

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");

$condi = $_POST["condi"];
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

switch ($condi) {
    case 'Credentials_virtual_bank':
    ?>
        <!-- Título Principal -->
        <h2 class="text-2xl font-semibold text-center text-gray-500 dark:text-gray-100 mb-6">
            Creacion de Ususarios - Banca Virtual
        </h2>

        <input type="hidden" value="Credentials_virtual_bank" id="condi">
        <input type="hidden" value="bancavirtual/views/vbview001" id="file">

        <!-- Estilos adicionales para botones de la tabla -->
        <style>
            #tb_credenciales .flex {
                display: flex !important;
            }
            #tb_credenciales button {
                min-width: 80px;
                white-space: nowrap;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }
            #tb_credenciales button:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            #tb_credenciales button i {
                font-size: 14px;
            }
            #tb_credenciales button span {
                margin-left: 4px;
            }
            #tb_credenciales td {
                vertical-align: middle;
            }
        </style>

        <!-- Contenedor Principal -->
        <div class="container mx-auto px-4 space-y-8">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                    <p><?= htmlspecialchars($mensaje) ?></p>
                </div>
            <?php endif; ?>

            <!-- Card Principal -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <!-- Encabezado con título secundario -->
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-2">
                            Generación de Credenciales para Banca Virtual
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Administre las credenciales de acceso para los clientes
                        </p>
                    </div>

                    <!-- Selector de Cliente con Búsqueda -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Seleccionar Cliente <span class="text-red-500">*</span>
                        </label>
                        
                        <!-- Buscador de clientes -->
                        <div class="relative mb-2">
                            <input 
                                type="text" 
                                id="buscarCliente" 
                                placeholder="🔍 Buscar por código o nombre..."
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                onkeyup="filtrarClientes()">
                        </div>
                        
                        <select
                            id="selectorCliente"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition duration-200"
                            onchange="seleccionarClienteDesdeSelect()"
                            size="6">
                            <option value="">-- Seleccione un cliente --</option>
                        </select>
                        
                        <!-- Indicadores de contacto del cliente seleccionado -->
                        <div id="infoContactoCliente" class="mt-3 hidden">
                            <div class="flex flex-wrap gap-2">
                                <span id="indicadorEmail" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium">
                                </span>
                                <span id="indicadorTelefono" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium">
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de Credenciales -->
                    <form id="formCredencial" class="space-y-6">
                        <input type="hidden" name="action" value="crear_credencial">

                        <!-- Grid de Inputs -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Código Cliente -->
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Código Cliente <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="codcli"
                                    name="codcli"
                                    required
                                    data-label="Código Cliente"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    readonly>
                            </div>

                            <!-- Nombre Cliente -->
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nombre
                                </label>
                                <input
                                    type="text"
                                    id="nombrecli"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    readonly>
                            </div>

                            <!-- Usuario -->
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Usuario <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="usuario"
                                    name="usuario"
                                    required
                                    data-label="Usuario"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    placeholder="Usuario para banca virtual">
                            </div>

                            <!-- Contraseña -->
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Contraseña <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="pass"
                                    name="pass"
                                    required
                                    data-label="Contraseña"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    placeholder="Contraseña segura">
                            </div>
                        </div>

                        <?php echo $csrf->getTokenField(); ?>

                        <!-- Botón Generar -->
                        <div class="flex justify-center gap-4 pt-2">
                            <button
                                onclick="generarCredencialConEnvio()"
                                type="button"
                                class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-8 rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm">
                                <i class="fa-solid fa-key"></i>
                                <span>Generar Credencial</span>
                            </button>
                            <button
                                type="button"
                                onclick="limpiarFormulario()"
                                class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-3 px-8 rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm">
                                <i class="fa-solid fa-eraser"></i>
                                <span>Limpiar</span>
                            </button>
                        </div>
                    </form>

                    <!-- Tabla de Credenciales -->
                    <div class="mt-8">
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <table id="tb_credenciales" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                                Código
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                                Nombre
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                                Usuario
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <!-- Los datos se cargan dinámicamente con DataTables -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Editar Credencial -->
        <div id="modalEditarCredencial" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white dark:bg-gray-800">
                <!-- Header del Modal -->
                <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Editar Credencial
                    </h3>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        onclick="cerrarModalEditar()">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Body del Modal -->
                <form id="formEditarCredencial" class="mt-4">
                    <input type="hidden" name="action" value="editar_credencial">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="space-y-4">
                        <!-- Código Cliente -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Código Cliente
                            </label>
                            <input
                                type="text"
                                id="edit_codcli"
                                name="codcli"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                readonly>
                        </div>

                        <!-- Usuario -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Usuario
                            </label>
                            <input
                                type="text"
                                id="edit_usuario"
                                name="usuario"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                required>
                        </div>

                        <!-- Nueva Contraseña -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Nueva Contraseña
                            </label>
                            <input
                                type="text"
                                id="edit_pass"
                                name="pass"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                placeholder="Dejar vacío para mantener la actual">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Opcional: Ingrese solo si desea cambiar la contraseña
                            </p>
                        </div>
                    </div>

                    <!-- Footer del Modal -->
                    <div class="flex justify-end gap-3 mt-6 pt-4 border-t dark:border-gray-700">
                        <button
                            type="button"
                            class="px-4 py-2 bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-800 dark:text-gray-200 rounded-lg transition duration-200"
                            onclick="cerrarModalEditar()">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-200">
                            Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Variables globales para almacenar las credenciales generadas
            var credencialesGeneradas = {
                nombre: '',
                codcli: '',
                usuario: '',
                password: '',
                email: '',
                telefono: ''
            };

            // Función principal para generar credencial y mostrar modal de envío
            function generarCredencialConEnvio() {
                ////////console.log('🚀 === INICIO GENERAR CREDENCIAL ===');
                
                // Validar campos primero
                var codcli = $('#codcli').val();
                var usuario = $('#usuario').val();
                var pass = $('#pass').val();
                var nombre = $('#nombrecli').val();
                var csrfToken = $('#<?= $csrf->getTokenName() ?>').val();

                ////////console.log('📝 Valores del formulario:');
                ////////console.log('   - codcli:', codcli);
                ////////console.log('   - usuario:', usuario);
                ////////console.log('   - pass:', pass ? '***' : 'VACÍO');
                ////////console.log('   - nombre:', nombre);
                ////////console.log('   - csrfToken:', csrfToken ? 'presente' : 'VACÍO');

                if (!codcli || !usuario || !pass) {
                    console.error('❌ Campos incompletos');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Campos incompletos',
                        text: 'Por favor, seleccione un cliente y verifique que el usuario y contraseña estén completos.'
                    });
                    return;
                }

                // Obtener email y teléfono del cliente seleccionado
                var clienteSeleccionado = $('#selectorCliente option:selected');
                var emailCliente = String(clienteSeleccionado.data('email') || '').trim();
                var telCliente = String(clienteSeleccionado.data('telefono') || '').trim();
                
                ////////console.log('📧 Email del data-attr:', emailCliente);
                ////////console.log('📱 Teléfono del data-attr:', telCliente);
                
                // Limpiar valores "undefined"
                if (emailCliente === 'undefined' || emailCliente === 'null') emailCliente = '';
                if (telCliente === 'undefined' || telCliente === 'null') telCliente = '';

                // Guardar TODOS los datos ANTES de enviar
                credencialesGeneradas.nombre = String(nombre || '').trim();
                credencialesGeneradas.codcli = String(codcli || '').trim();
                credencialesGeneradas.usuario = String(usuario || '').trim();
                credencialesGeneradas.password = String(pass || '').trim();
                credencialesGeneradas.email = emailCliente;
                credencialesGeneradas.telefono = telCliente;
                
                ////////console.log('💾 Credenciales guardadas en objeto:', JSON.stringify(credencialesGeneradas));

                // Mostrar loader
                Swal.fire({
                    title: 'Generando credenciales...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                // AJAX directo (sin usar obtiene() que interfiere con SweetAlert)
                $.ajax({
                    url: '../../../../src/cruds/crud_banca.php',
                    method: 'POST',
                    data: {
                        inputs: {
                            '<?= $csrf->getTokenName() ?>': csrfToken,
                            'codcli': codcli,
                            'usuario': usuario,
                            'pass': pass
                        },
                        selects: {},
                        radios: {},
                        condi: 'crear_credencial',
                        id: '0',
                        archivo: ''
                    },
                    dataType: 'json',
                    success: function(response) {
                        ////////console.log('✅ Respuesta servidor:', response);
                        
                        Swal.close(); // Cerrar loader
                        
                        if (response && response.status == 1) {
                            // Actualizar con respuesta del servidor si hay
                            if (response.data) {
                                if (response.data.nombre) credencialesGeneradas.nombre = String(response.data.nombre).trim();
                                if (response.data.codcli) credencialesGeneradas.codcli = String(response.data.codcli).trim();
                                if (response.data.usuario) credencialesGeneradas.usuario = String(response.data.usuario).trim();
                                if (response.data.password) credencialesGeneradas.password = String(response.data.password).trim();
                            }
                            
                            ////////console.log('📋 Credenciales finales:', JSON.stringify(credencialesGeneradas));
                            
                            // Recargar tabla primero
                            cargarCredenciales();
                            
                            // Limpiar formulario (pero NO los datos de credencialesGeneradas)
                            limpiarFormularioSinCredenciales();
                            
                            // Mostrar modal con credenciales AL FINAL
                            mostrarModalCredenciales();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || response.msg || 'Error al crear credenciales'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error AJAX:', {xhr, status, error});
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo conectar con el servidor: ' + error
                        });
                    }
                });
            }

            // Mostrar SweetAlert con las credenciales generadas y opciones
            function mostrarModalCredenciales() {
                ////////console.log('🔔 === MOSTRANDO MODAL ===');
                ////////console.log('🔔 credencialesGeneradas completo:', JSON.stringify(credencialesGeneradas));
                
                // Obtener valores seguros
                var nombre = String(credencialesGeneradas.nombre || 'Cliente').trim();
                var usuario = String(credencialesGeneradas.usuario || '').trim();
                var password = String(credencialesGeneradas.password || '').trim();
                var codcli = String(credencialesGeneradas.codcli || '').trim();
                var email = String(credencialesGeneradas.email || '').trim();
                var telefono = String(credencialesGeneradas.telefono || '').trim();
                
                ////////console.log('🔔 Valores extraídos:');
                ////////console.log('   - nombre:', nombre);
                ////////console.log('   - usuario:', usuario);
                ////////console.log('   - password:', password ? '***' : 'VACÍO');
                ////////console.log('   - codcli:', codcli);
                ////////console.log('   - email:', email);
                ////////console.log('   - telefono:', telefono);
                
                // Limpiar valores undefined
                if (email === 'undefined' || email === 'null') email = '';
                if (telefono === 'undefined' || telefono === 'null') telefono = '';
                
                // Determinar qué botones mostrar
                var tieneEmail = email !== '';
                var tieneTelefono = telefono !== '';
                
                ////////console.log('🔔 tieneEmail:', tieneEmail, '| tieneTelefono:', tieneTelefono);
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Credenciales Creadas!',
                    html: `
                        <div style="text-align: left; padding: 10px 0;">
                            <p style="margin-bottom: 15px; font-weight: 600; color: #374151;">
                                Cliente: <span style="color: #059669;">${nombre}</span>
                            </p>
                            
                            <div style="background: #f3f4f6; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                    <div>
                                        <small style="color: #6b7280; font-size: 11px;">CÓDIGO</small>
                                        <p style="font-family: monospace; font-weight: bold; color: #1f2937; margin: 2px 0;">${codcli}</p>
                                    </div>
                                    <div>
                                        <small style="color: #6b7280; font-size: 11px;">USUARIO</small>
                                        <p style="font-family: monospace; font-weight: bold; color: #1f2937; margin: 2px 0;">${usuario}</p>
                                    </div>
                                </div>
                                <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 6px; padding: 10px; text-align: center;">
                                    <small style="color: #92400e; font-size: 11px;">🔑 CONTRASEÑA</small>
                                    <p style="font-family: monospace; font-weight: bold; font-size: 22px; color: #92400e; margin: 5px 0; letter-spacing: 3px;">${password}</p>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-bottom: 10px;">
                                <button onclick="copiarMensajeCredenciales()" class="swal2-styled" style="background: #6366f1; padding: 8px 16px; font-size: 13px; border-radius: 6px;">
                                    📋 Copiar
                                </button>
                                <button onclick="abrirWhatsAppDesdeModal()" class="swal2-styled" style="background: #16a34a; padding: 8px 16px; font-size: 13px; border-radius: 6px;">
                                    💬 WhatsApp ${tieneTelefono ? '' : '⚠️'}
                                </button>
                                <button onclick="abrirCorreoDesdeModal()" class="swal2-styled" style="background: #2563eb; padding: 8px 16px; font-size: 13px; border-radius: 6px;">
                                    📧 Correo ${tieneEmail ? '' : '⚠️'}
                                </button>
                            </div>
                            
                            <p style="font-size: 11px; color: #9ca3af; text-align: center;">
                                ${tieneEmail ? '📧 ' + email : '📧 Sin correo'} | 
                                ${tieneTelefono ? '📱 ' + telefono : '📱 Sin teléfono'}
                            </p>
                        </div>
                    `,
                    showConfirmButton: true,
                    confirmButtonText: 'Cerrar',
                    confirmButtonColor: '#6b7280',
                    width: '480px',
                    allowOutsideClick: false,  // No cerrar al hacer clic fuera
                    allowEscapeKey: false      // No cerrar con Escape
                });
            }

            // ============================================
            // COPIAR MENSAJE - LEE DE credencialesGeneradas
            // ============================================
            function copiarMensajeCredenciales() {
                ////////console.log('📋 Iniciando copiarMensajeCredenciales()');
                ////////console.log('📋 Datos disponibles:', JSON.stringify(credencialesGeneradas));
                
                // Extraer datos con valores por defecto
                var nombre = credencialesGeneradas.nombre || 'Cliente';
                var usuario = credencialesGeneradas.usuario || '';
                var password = credencialesGeneradas.password || '';
                var codcli = credencialesGeneradas.codcli || '';
                
                ////////console.log('📋 Valores extraídos:', { nombre, usuario, password: password ? '***' : 'VACÍO', codcli });
                
                // Validar que existan los datos críticos
                if (!usuario || !password || !codcli) {
                    console.error('❌ Faltan datos críticos');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No hay credenciales para copiar. Genera las credenciales primero.'
                    });
                    return;
                }
                
                // Construir el mensaje EXACTO del modal
                var mensaje = `🏦 BANCA VIRTUAL - CREDENCIALES DE ACCESO

Hola ${nombre}!

Tus datos de acceso:

👤 Usuario: ${usuario}
🔑 Contraseña: ${password}
📱 Código Cliente: ${codcli}

⚠️ IMPORTANTE:
- Esta es una contraseña temporal
- Cámbiala en tu primer inicio de sesión
- No compartas estos datos con nadie

Equipo de Banca Virtual`;

                ////////console.log('📋 Mensaje construido, copiando...');
                
                // Copiar al portapapeles
                copiarAlPortapapeles(mensaje, 'Mensaje copiado al portapapeles');
            }

            // ============================================
            // ABRIR WHATSAPP DESDE MODAL
            // ============================================
            function abrirWhatsAppDesdeModal() {
                ////////console.log('📱 Iniciando abrirWhatsAppDesdeModal()');
                ////////console.log('📱 Datos disponibles:', JSON.stringify(credencialesGeneradas));
                
                // Extraer teléfono
                var telefono = credencialesGeneradas.telefono || '';
                
                // Limpiar string "undefined" o "null"
                if (telefono === 'undefined' || telefono === 'null') {
                    telefono = '';
                }
                
                telefono = String(telefono).trim();
                
                ////////console.log('📱 Teléfono extraído:', telefono);
                
                // Decidir acción
                if (telefono !== '') {
                    //////console.log('✅ Hay teléfono, abriendo WhatsApp');
                    abrirWhatsAppDirecto(telefono);
                } else {
                    //////console.log('⚠️ No hay teléfono, pidiendo al usuario');
                    pedirTelefonoYEnviar();
                }
            }

            // ============================================
            // ABRIR CORREO DESDE MODAL
            // ============================================
            function abrirCorreoDesdeModal() {
                //////console.log('📧 Iniciando abrirCorreoDesdeModal()');
                //////console.log('📧 Datos disponibles:', JSON.stringify(credencialesGeneradas));
                
                // Extraer email
                var email = credencialesGeneradas.email || '';
                
                // Limpiar string "undefined" o "null"
                if (email === 'undefined' || email === 'null') {
                    email = '';
                }
                
                email = String(email).trim();
                
                //////console.log('📧 Email extraído:', email);
                
                // Decidir acción
                if (email !== '') {
                    //////console.log('✅ Hay email, abriendo correo');
                    abrirCorreoDirecto(email);
                } else {
                    //////console.log('⚠️ No hay email, pidiendo al usuario');
                    pedirEmailYEnviar();
                }
            }

            // Copiar al portapapeles
            function copiarAlPortapapeles(texto, mensajeExito) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(texto).then(function() {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: mensajeExito || 'Copiado',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }).catch(function(err) {
                        copiarFallback(texto, mensajeExito);
                    });
                } else {
                    copiarFallback(texto, mensajeExito);
                }
            }

            // Fallback para copiar
            function copiarFallback(texto, mensajeExito) {
                var textarea = document.createElement('textarea');
                textarea.value = texto;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: mensajeExito || 'Copiado',
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // ============================================
            // ABRIR WHATSAPP DIRECTO
            // ============================================
            function abrirWhatsAppDirecto(telefono) {
                ////////console.log('📱 Abriendo WhatsApp con teléfono:', telefono);
                
                // Limpiar teléfono (solo números)
                telefono = String(telefono).replace(/\D/g, '');
                
                // Agregar código de país si es necesario (Guatemala = 502)
                if (telefono.length === 8) {
                    telefono = '502' + telefono;
                }
                
                ////////console.log('📱 Teléfono formateado:', telefono);
                
                // Extraer datos del objeto global
                var nombre = credencialesGeneradas.nombre || 'Cliente';
                var usuario = credencialesGeneradas.usuario || '';
                var password = credencialesGeneradas.password || '';
                var codcli = credencialesGeneradas.codcli || '';
                
                ////////console.log('📱 Datos para mensaje:', { nombre, usuario, password: password ? '***' : 'VACÍO', codcli });
                
                // Validar datos críticos
                if (!usuario || !password || !codcli) {
                    console.error('❌ Faltan datos para enviar');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No hay credenciales válidas para enviar'
                    });
                    return;
                }
                
                // Construir mensaje para WhatsApp
                var mensaje = encodeURIComponent(`🏦 *BANCA VIRTUAL - CREDENCIALES*

Hola *${nombre}*! 👋

Sus datos de acceso:

👤 *Usuario:* ${usuario}
🔑 *Contraseña:* ${password}
📱 *Código:* ${codcli}

⚠️ Contraseña temporal - cámbiela en su primer acceso.

_Equipo de Banca Virtual_`);
                
                // Abrir WhatsApp
                var url = 'https://wa.me/' + telefono + '?text=' + mensaje;
                ////////console.log('📱 Abriendo URL:', url.substring(0, 50) + '...');
                
                window.open(url, '_blank');
                
                // Notificación
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'WhatsApp abierto',
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // ============================================
            // ABRIR CORREO DIRECTO
            // ============================================
            function abrirCorreoDirecto(email) {
                ////////console.log('📧 Abriendo correo con email:', email);
                
                // Extraer datos del objeto global
                var nombre = credencialesGeneradas.nombre || 'Cliente';
                var usuario = credencialesGeneradas.usuario || '';
                var password = credencialesGeneradas.password || '';
                var codcli = credencialesGeneradas.codcli || '';
                
                ////////console.log('📧 Datos para mensaje:', { nombre, usuario, password: password ? '***' : 'VACÍO', codcli });
                
                // Validar datos críticos
                if (!usuario || !password || !codcli) {
                    console.error('❌ Faltan datos para enviar');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No hay credenciales válidas para enviar'
                    });
                    return;
                }
                
                // Construir asunto y cuerpo
                var asunto = encodeURIComponent('Credenciales de Acceso - Banca Virtual');
                var cuerpo = encodeURIComponent(`Hola ${nombre},

Tus datos de acceso para Banca Virtual:

👤 Usuario: ${usuario}
🔑 Contraseña: ${password}
📱 Código Cliente: ${codcli}

⚠️ IMPORTANTE:
- Contraseña temporal - cámbiala en tu primer acceso
- No compartas estos datos con nadie

Saludos,
Equipo de Banca Virtual`);
                
                // Abrir cliente de correo
                var mailtoLink = 'mailto:' + email + '?subject=' + asunto + '&body=' + cuerpo;
                ////////console.log('📧 Abriendo mailto...');
                
                window.location.href = mailtoLink;
                
                // Notificación
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Correo abierto',
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // Pedir teléfono y abrir WhatsApp
            function pedirTelefonoYEnviar() {
                Swal.fire({
                    title: 'Ingrese el teléfono',
                    input: 'text',
                    inputPlaceholder: 'Ej: 12345678',
                    showCancelButton: true,
                    confirmButtonText: 'Abrir WhatsApp',
                    confirmButtonColor: '#16a34a',
                    inputValidator: (value) => {
                        if (!value || value.replace(/\D/g, '').length < 8) {
                            return 'Ingrese un número válido (mínimo 8 dígitos)';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        abrirWhatsAppDirecto(result.value);
                    }
                });
            }

            // Pedir email y abrir correo
            function pedirEmailYEnviar() {
                Swal.fire({
                    title: 'Ingrese el correo',
                    input: 'email',
                    inputPlaceholder: 'correo@ejemplo.com',
                    showCancelButton: true,
                    confirmButtonText: 'Abrir Correo',
                    confirmButtonColor: '#2563eb',
                    inputValidator: (value) => {
                        if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                            return 'Ingrese un correo válido';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        abrirCorreoDirecto(result.value);
                    }
                });
            }

            // Cargar clientes en el selector al iniciar la página
            function cargarClientesEnSelector() {
                ////////console.log('🔄 Iniciando carga de clientes...');
                $.ajax({
                    url: '../../../../src/server_side/obtener_clientes_simple.php',
                    method: 'GET',
                    dataType: 'json',
                    timeout: 30000, // 30 segundos de timeout
                    beforeSend: function() {
                        $('#selectorCliente').html('<option value="">Cargando clientes...</option>');
                        $('#buscarCliente').val('');
                    },
                    success: function(data) {
                        ////////console.log('✅ Clientes cargados:', data);
                        var options = '<option value="">-- Seleccione un cliente --</option>';
                        
                        // Verificar si data es un array
                        if (Array.isArray(data) && data.length > 0) {
                            // Guardar clientes para filtrado
                            todosLosClientes = data;
                            
                            data.forEach(function(cliente) {
                                if (cliente.codigo && cliente.nombre) {
                                    // Escapar caracteres especiales para evitar problemas
                                    var codigo = String(cliente.codigo).replace(/"/g, '&quot;');
                                    var nombre = String(cliente.nombre).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                                    var email = String(cliente.email || '').replace(/"/g, '&quot;');
                                    var telefono = String(cliente.telefono || '').replace(/"/g, '&quot;');
                                    
                                    // Iconos de disponibilidad de contacto
                                    var iconos = '';
                                    if (email) iconos += '📧';
                                    if (telefono) iconos += '📱';
                                    if (!iconos) iconos = '⚠️';
                                    
                                    options += `<option value="${codigo}" data-nombre="${nombre}" data-email="${email}" data-telefono="${telefono}">${iconos} ${codigo} - ${nombre}</option>`;
                                }
                            });
                            
                            if (data.length === 0) {
                                options = '<option value="">No hay clientes disponibles</option>';
                            }
                        } else if (data && data.error) {
                            // Si hay un error en la respuesta
                            console.error('❌ Error del servidor:', data.error);
                            options = '<option value="">Error: ' + (data.mensaje || data.error) + '</option>';
                            Swal.fire({
                                icon: 'error',
                                title: 'Error al cargar clientes',
                                text: data.mensaje || data.error || 'Error desconocido',
                                confirmButtonText: 'Reintentar',
                                showCancelButton: true,
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    cargarClientesEnSelector();
                                }
                            });
                        } else {
                            options = '<option value="">No hay clientes disponibles</option>';
                        }
                        
                        $('#selectorCliente').html(options);
                        ////////console.log('✅ Selector actualizado con', data.length || 0, 'clientes');
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error AJAX:', {xhr: xhr, status: status, error: error});
                        console.error('❌ Respuesta del servidor:', xhr.responseText);
                        
                        var mensajeError = 'No se pudieron cargar los clientes';
                        var detallesError = '';
                        
                        if (xhr.responseText) {
                            try {
                                var errorData = JSON.parse(xhr.responseText);
                                if (errorData.error) {
                                    detallesError = errorData.error;
                                } else if (errorData.mensaje) {
                                    detallesError = errorData.mensaje;
                                }
                            } catch (e) {
                                detallesError = xhr.responseText.substring(0, 100);
                            }
                        }
                        
                        if (status === 'timeout') {
                            mensajeError = 'Tiempo de espera agotado. El servidor está tardando demasiado en responder.';
                        } else if (xhr.status === 401) {
                            mensajeError = 'Sesión expirada. Por favor, recarga la página e inicia sesión nuevamente.';
                        } else if (xhr.status === 500) {
                            mensajeError = 'Error interno del servidor. Contacte al administrador.';
                        } else if (xhr.status === 0) {
                            mensajeError = 'No se pudo conectar al servidor. Verifique su conexión a internet.';
                        }
                        
                        $('#selectorCliente').html('<option value="">Error al cargar clientes</option>');
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al cargar clientes',
                            html: mensajeError + (detallesError ? '<br><small>' + detallesError + '</small>' : ''),
                            confirmButtonText: 'Reintentar',
                            showCancelButton: true,
                            cancelButtonText: 'Cancelar',
                            footer: '<small>Código de error: ' + xhr.status + ' | ' + status + '</small>'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                cargarClientesEnSelector();
                            }
                        });
                    }
                });
            }

            // Variable global para almacenar todos los clientes (para filtrado)
            var todosLosClientes = [];
            
            // Variable global para almacenar códigos de clientes que YA tienen credenciales
            var clientesConCredenciales = [];

            // Función para filtrar clientes en el selector
            function filtrarClientes() {
                var busqueda = $('#buscarCliente').val().toLowerCase().trim();
                var select = $('#selectorCliente');
                
                // Limpiar selector
                select.empty();
                select.append('<option value="">-- Seleccione un cliente --</option>');
                
                // Filtrar y agregar clientes que coincidan
                var clientesFiltrados = todosLosClientes.filter(function(cliente) {
                    var codigo = String(cliente.codigo).toLowerCase();
                    var nombre = String(cliente.nombre).toLowerCase();
                    return codigo.includes(busqueda) || nombre.includes(busqueda);
                });
                
                // Agregar opciones filtradas
                clientesFiltrados.forEach(function(cliente) {
                    var codigo = String(cliente.codigo).replace(/"/g, '&quot;');
                    var nombre = String(cliente.nombre).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    var email = String(cliente.email || '').replace(/"/g, '&quot;');
                    var telefono = String(cliente.telefono || '').replace(/"/g, '&quot;');
                    
                    // Verificar si ya tiene credenciales
                    var yaRegistrado = clientesConCredenciales.includes(codigo);
                    
                    // Iconos de disponibilidad
                    var iconos = '';
                    if (yaRegistrado) {
                        iconos = '✅ '; // Ya tiene usuario
                    } else {
                        if (email) iconos += '📧';
                        if (telefono) iconos += '📱';
                        if (!iconos) iconos = '⚠️';
                        iconos += ' ';
                    }
                    
                    var opcion = $('<option></option>')
                        .val(codigo)
                        .attr('data-nombre', nombre)
                        .attr('data-email', email)
                        .attr('data-telefono', telefono)
                        .text(iconos + codigo + ' - ' + nombre);
                    
                    // Estilo especial si ya tiene credenciales
                    if (yaRegistrado) {
                        opcion.css({'color': '#16a34a', 'font-weight': '600'});
                    }
                    
                    select.append(opcion);
                });
                
                // Mostrar contador
                ////////console.log('🔍 Clientes filtrados:', clientesFiltrados.length, '| Con credenciales:', clientesConCredenciales.length);
            }

            // Seleccionar cliente desde el selector
            function seleccionarClienteDesdeSelect() {
                var opcionSeleccionada = $('#selectorCliente option:selected');
                var codigo = $('#selectorCliente').val();
                
                // Asegurar que todos los valores sean strings
                var nombre = String(opcionSeleccionada.data('nombre') || '');
                var email = String(opcionSeleccionada.data('email') || '');
                var telefono = String(opcionSeleccionada.data('telefono') || '');
                
                // Limpiar valores "undefined" que vienen como string
                if (email === 'undefined' || email === 'null') email = '';
                if (telefono === 'undefined' || telefono === 'null') telefono = '';
                if (nombre === 'undefined' || nombre === 'null') nombre = '';
                
                ////////console.log('👤 Cliente seleccionado:', { codigo, nombre, email, telefono });
                
                if (codigo && codigo !== '') {
                    // Verificar si ya tiene credenciales
                    if (clientesConCredenciales.includes(codigo)) {
                        Swal.fire({
                            icon: 'warning',
                            title: '⚠️ Cliente ya registrado',
                            html: `<p>El cliente <strong>${nombre}</strong> ya tiene credenciales de banca virtual.</p>
                                   <p class="text-sm text-gray-500 mt-2">Si continúa, se generarán nuevas credenciales que reemplazarán las anteriores.</p>`,
                            showCancelButton: true,
                            confirmButtonText: 'Continuar de todas formas',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#f59e0b'
                        }).then((result) => {
                            if (!result.isConfirmed) {
                                // Limpiar selección
                                $('#selectorCliente').val('');
                                limpiarFormulario();
                                return;
                            }
                        });
                    }
                    
                    $('#codcli').val(codigo);
                    $('#nombrecli').val(nombre);
                    
                    // Generar usuario y contraseña automáticamente
                    const corto = codigo.toString().slice(-4);
                    var usuarioGenerado = 'u' + corto;
                    var passGenerado = Math.random().toString(36).slice(-8);
                    
                    $('#usuario').val(usuarioGenerado);
                    $('#pass').val(passGenerado);
                    
                    // Guardar TODOS los datos para envío posterior
                    credencialesGeneradas.email = email.trim();
                    credencialesGeneradas.telefono = telefono.trim();
                    credencialesGeneradas.nombre = nombre.trim();
                    credencialesGeneradas.codcli = codigo;
                    credencialesGeneradas.usuario = usuarioGenerado;
                    credencialesGeneradas.password = passGenerado;
                    
                    // Mostrar indicadores de contacto
                    mostrarIndicadoresContacto(email, telefono);
                    
                    ////////console.log('📧 Email guardado:', credencialesGeneradas.email || 'No disponible');
                    ////////console.log('📱 Teléfono guardado:', credencialesGeneradas.telefono || 'No disponible');
                } else {
                    // Ocultar indicadores si no hay cliente seleccionado
                    $('#infoContactoCliente').addClass('hidden');
                }
            }

            // Mostrar indicadores visuales de email y teléfono
            function mostrarIndicadoresContacto(email, telefono) {
                var infoDiv = $('#infoContactoCliente');
                var indicadorEmail = $('#indicadorEmail');
                var indicadorTelefono = $('#indicadorTelefono');
                
                // Asegurar que sean strings
                email = String(email || '');
                telefono = String(telefono || '');
                
                // Limpiar valores "undefined"
                if (email === 'undefined' || email === 'null') email = '';
                if (telefono === 'undefined' || telefono === 'null') telefono = '';
                
                // Indicador de Email
                if (email && email.trim() !== '') {
                    indicadorEmail.html('<i class="fa-solid fa-envelope mr-1"></i> ' + email.trim());
                    indicadorEmail.removeClass('bg-red-100 text-red-800').addClass('bg-green-100 text-green-800');
                } else {
                    indicadorEmail.html('<i class="fa-solid fa-envelope-circle-xmark mr-1"></i> Sin correo');
                    indicadorEmail.removeClass('bg-green-100 text-green-800').addClass('bg-red-100 text-red-800');
                }
                
                // Indicador de Teléfono
                if (telefono && telefono.trim() !== '') {
                    indicadorTelefono.html('<i class="fa-solid fa-phone mr-1"></i> ' + telefono.trim());
                    indicadorTelefono.removeClass('bg-red-100 text-red-800').addClass('bg-green-100 text-green-800');
                } else {
                    indicadorTelefono.html('<i class="fa-solid fa-phone-slash mr-1"></i> Sin teléfono');
                    indicadorTelefono.removeClass('bg-green-100 text-green-800').addClass('bg-red-100 text-red-800');
                }
                
                infoDiv.removeClass('hidden');
            }

            // Función para limpiar formulario
            function limpiarFormulario() {
                $('#selectorCliente').val('');
                $('#buscarCliente').val('');
                $('#codcli').val('');
                $('#nombrecli').val('');
                $('#usuario').val('');
                $('#pass').val('');
                $('#infoContactoCliente').addClass('hidden');
                
                // Limpiar datos de credenciales
                credencialesGeneradas.nombre = '';
                credencialesGeneradas.codcli = '';
                credencialesGeneradas.usuario = '';
                credencialesGeneradas.password = '';
                credencialesGeneradas.email = '';
                credencialesGeneradas.telefono = '';
                
                // Recargar lista completa de clientes
                filtrarClientes();
            }

            // Limpiar formulario pero mantener credenciales para el modal
            function limpiarFormularioSinCredenciales() {
                $('#selectorCliente').val('');
                $('#buscarCliente').val('');
                $('#codcli').val('');
                $('#nombrecli').val('');
                $('#usuario').val('');
                $('#pass').val('');
                $('#infoContactoCliente').addClass('hidden');
                
                // NO limpiar credencialesGeneradas aquí
                // Los datos se mantienen para el modal
                
                filtrarClientes();
            }

            // Cargar credenciales en DataTable
            function cargarCredenciales() {
                // Destruir DataTable existente si existe
                if ($.fn.DataTable.isDataTable('#tb_credenciales')) {
                    $('#tb_credenciales').DataTable().destroy();
                }
                
                $('#tb_credenciales').DataTable({
                    ajax: {
                        url: '../../src/cruds/ajax_credenciales_debug.php',
                        dataSrc: function(json) {
                            // Extraer códigos de clientes que ya tienen credenciales
                            clientesConCredenciales = [];
                            if (json.data && Array.isArray(json.data)) {
                                json.data.forEach(function(row) {
                                    if (row.codcli) {
                                        clientesConCredenciales.push(String(row.codcli).trim());
                                    }
                                });
                            }
                            ////////console.log('👥 Clientes con credenciales:', clientesConCredenciales.length);
                            
                            // Actualizar selector para mostrar indicadores
                            actualizarIndicadoresSelector();
                            
                            return json.data || [];
                        },
                        error: function(xhr, error, thrown) {
                            console.error('Error al cargar credenciales:', error);
                            console.error('Respuesta:', xhr.responseText);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se pudieron cargar las credenciales. Verifique la consola para más detalles.'
                            });
                        }
                    },
                    columns: [{
                            data: 'codcli',
                            className: 'px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100'
                        },
                        {
                            data: 'nombre',
                            className: 'px-4 py-3 text-sm text-gray-600 dark:text-gray-300'
                        },
                        {
                            data: 'usuario',
                            className: 'px-4 py-3 text-sm text-gray-600 dark:text-gray-300'
                        },
                        {
                            data: 'acciones',
                            orderable: false,
                            searchable: false,
                            className: 'px-4 py-3 text-sm text-center',
                            render: function(data, type, row) {
                                // Si es para mostrar, devolver el HTML tal cual
                                if (type === 'display') {
                                    return data;
                                }
                                // Para otros tipos (ordenamiento, búsqueda), devolver vacío
                                return '';
                            }
                        }
                    ],
                    destroy: true,
                    language: {
                        lengthMenu: 'Mostrar _MENU_ registros',
                        zeroRecords: 'No se encontraron registros',
                        info: 'Mostrando página _PAGE_ de _PAGES_',
                        infoEmpty: 'No hay registros disponibles',
                        infoFiltered: '(filtrado de _MAX_ registros totales)',
                        search: 'Buscar:',
                        paginate: {
                            first: 'Primero',
                            last: 'Último',
                            next: 'Siguiente',
                            previous: 'Anterior'
                        },
                        processing: 'Procesando...'
                    },
                    pageLength: 10,
                    responsive: true,
                    autoWidth: false,
                    columnDefs: [
                        {
                            targets: [3], // Columna de acciones
                            width: '250px'
                        }
                    ]
                });
            }
            
            // Función para actualizar los indicadores en el selector de clientes
            function actualizarIndicadoresSelector() {
                var $selector = $('#selectorCliente');
                
                $selector.find('option').each(function() {
                    var $option = $(this);
                    var codigo = $option.val();
                    
                    if (codigo && clientesConCredenciales.includes(codigo)) {
                        // Ya tiene credenciales - agregar indicador ✅
                        var texto = $option.text();
                        if (!texto.includes('✅')) {
                            // Reemplazar iconos de contacto con ✅ al inicio
                            texto = texto.replace(/^(📧|📱|⚠️)+\s*/, '');
                            $option.text('✅ ' + texto);
                            $option.css({'color': '#16a34a', 'font-weight': '600'});
                        }
                    }
                });
            }

            // Función para editar credencial
            function editarCredencial(id, usuario, codcli) {
                $('#edit_id').val(id);
                $('#edit_usuario').val(usuario);
                $('#edit_codcli').val(codcli);
                $('#edit_pass').val('');
                $('#modalEditarCredencial').removeClass('hidden').show();
            }

            // Función para cerrar modal de editar
            function cerrarModalEditar() {
                $('#modalEditarCredencial').addClass('hidden').hide();
            }

            // Función para eliminar credencial
            function eliminarCredencial(id, usuario) {
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: `Se eliminará la credencial del usuario: ${usuario}`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '../../src/cruds/crud_banca.php',
                            method: 'POST',
                            data: {
                                action: 'eliminar_credencial',
                                id: id
                            },
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            success: function(resp) {
                                let res = {};
                                try {
                                    res = JSON.parse(resp);
                                } catch (e) {
                                    res = {
                                        status: 0,
                                        msg: 'Error inesperado'
                                    };
                                }
                                if (res.status == 1) {
                                    Swal.fire('Eliminado', res.msg, 'success');
                                    cargarCredenciales();
                                } else {
                                    Swal.fire('Error', res.msg, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                            },
                            complete: function() {
                                loaderefect(0);
                            }
                        });
                    }
                });
            }

            // Función para enviar credenciales por correo
            function enviarPorCorreo(id, email, usuario, codcli) {
                if (!email || email.trim() === '') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se encontró un correo electrónico para este cliente'
                    });
                    return;
                }

                // Nota: Las contraseñas están hasheadas y no pueden ser recuperadas
                // Solo enviamos el usuario y código de cliente
                const asunto = encodeURIComponent('Credenciales de Acceso - Banca Virtual');
                const cuerpo = encodeURIComponent(`Hola,

Te informamos tus datos de acceso para la plataforma de Banca Virtual:

👤 Usuario: ${usuario}
📱 Código de Cliente: ${codcli}

⚠️ IMPORTANTE:
• Si es tu primer acceso o no recuerdas tu contraseña, utiliza la opción "¿Olvidaste tu contraseña?" en la página de inicio
• No compartas tus credenciales con nadie
• Si tienes problemas para acceder, contáctanos a través de nuestros canales oficiales

Saludos,
Equipo de Banca Virtual`);
                
                const mailtoLink = `mailto:${email}?subject=${asunto}&body=${cuerpo}`;
                window.location.href = mailtoLink;
                
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Cliente de correo abierto',
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // Función para enviar credenciales por WhatsApp
            function enviarPorWhatsApp(telefono, usuario, codcli) {
                if (!telefono || telefono.trim() === '') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se encontró un número de teléfono para este cliente'
                    });
                    return;
                }

                // Limpiar el teléfono (quitar espacios, guiones, etc.)
                telefono = telefono.replace(/\D/g, '');
                
                // Si no tiene código de país, agregar 502 (Guatemala)
                if (telefono.length === 8) {
                    telefono = '502' + telefono;
                }
                
                const mensaje = encodeURIComponent(`Hola! 👋

Te informamos tus datos de acceso para la Banca Virtual:

🔐 *Usuario:* ${usuario}
📱 *Código Cliente:* ${codcli}

⚠️ *IMPORTANTE:*
• Si es tu primer acceso o no recuerdas tu contraseña, utiliza la opción "¿Olvidaste tu contraseña?" en la página de inicio
• No compartas tus credenciales con nadie
• Si tienes problemas, contáctanos

¡Gracias por confiar en nosotros!`);
                
                const whatsappLink = `https://wa.me/${telefono}?text=${mensaje}`;
                window.open(whatsappLink, '_blank');
                
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'WhatsApp abierto',
                    showConfirmButton: false,
                    timer: 2000
                });
            }

            // Document Ready
            $(document).ready(function() {
                cargarClientesEnSelector(); // Cargar clientes al iniciar
                cargarCredenciales();

                // Submit formulario de crear credencial
                $('#formCredencial').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '../../src/cruds/crud_banca.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        success: function(resp) {
                            let res = {};
                            try {
                                res = JSON.parse(resp);
                            } catch (e) {
                                res = {
                                    status: 0,
                                    msg: 'Error inesperado'
                                };
                            }
                            if (res.status == 1) {
                                Swal.fire('Correcto', res.msg, 'success');
                                $('#formCredencial')[0].reset();
                                cargarCredenciales();
                            } else {
                                Swal.fire('Error', res.msg, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                        },
                        complete: function() {
                            loaderefect(0);
                        }
                    });
                });

                // Submit formulario de editar credencial
                $('#formEditarCredencial').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '../../src/cruds/crud_banca.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        beforeSend: function() {
                            loaderefect(1);
                        },
                        success: function(resp) {
                            let res = {};
                            try {
                                res = JSON.parse(resp);
                            } catch (e) {
                                res = {
                                    status: 0,
                                    msg: 'Error inesperado'
                                };
                            }
                            if (res.status == 1) {
                                Swal.fire('Actualizado', res.msg, 'success');
                                cerrarModalEditar();
                                cargarCredenciales();
                            } else {
                                Swal.fire('Error', res.msg, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
                        },
                        complete: function() {
                            loaderefect(0);
                        }
                    });
                });
            });
        </script>
    <?php
        break;

    case 'Productos_virtual_bank':
        // Agregar token CSRF para protección
        echo $csrf->getTokenField();
    ?>
        <!-- Título Principal -->
        <h2 class="text-2xl font-semibold text-center text-gray-500 dark:text-gray-100 mb-6">
            Creacion de Productos Publicos de Credito - Banca Virtual
        </h2>

        <input type="hidden" value="Productos_virtual_bank" id="condi">
        <input type="hidden" value="cuencobra/views/Productos_virtual_bank" id="file">

        <!-- Contenedor Principal -->
        <div class="container mx-auto px-4 space-y-8">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                    <p><?= htmlspecialchars($mensaje) ?></p>
                </div>
            <?php endif; ?>

            <!-- Card Principal -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <!-- Header del Card -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <h3 class="text-xl font-semibold text-white">
                        Control de Productos Crediticios Públicos - Banca Virtual
                    </h3>
                </div>

                <!-- Body del Card -->
                <div class="p-6 space-y-6">
                    <!-- Input oculto para ID del producto -->
                    <input type="hidden" id="idPro" name="idPro" value="">

                    <!-- Formulario de Registro/Edición -->
                    <div class="space-y-4">
                        <!-- Nombre del Producto -->
                        <div>
                            <label for="nomPro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Nombre del producto <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition duration-200"
                                id="nomPro"
                                name="nomPro"
                                placeholder="Ingrese el nombre del producto"
                                maxlength="191">
                        </div>

                        <!-- Descripción del Producto -->
                        <div>
                            <label for="desPro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Descripción del producto
                            </label>
                            <textarea
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition duration-200 resize-none"
                                placeholder="Ingrese la descripción del producto"
                                id="desPro"
                                name="desPro"
                                rows="4"></textarea>
                        </div>

                        <!-- Estado de Publicación -->
                        <div>
                            <label for="published" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Estado de publicación
                            </label>
                            <select
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition duration-200"
                                id="published"
                                name="published">
                                <option value="0">No publicado (Oculto)</option>
                                <option value="1">Publicado (Visible al público)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="button"
                            id="btnGua"
                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm"
                            onclick="guardarProductoPublicoBtn()">
                            <i class="fa-solid fa-save"></i>
                            <span>Guardar</span>
                        </button>

                        <button
                            style="display: none;"
                            type="button"
                            id="btnAct"
                            class="px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-medium rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm"
                            onclick="actualizarProductoPublicoBtn()">
                            <i class="fa-solid fa-edit"></i>
                            <span>Actualizar</span>
                        </button>

                        <button
                            type="button"
                            id="btnCan"
                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm"
                            onclick="limpiarInputsPublico()">
                            <i class="fa-solid fa-times"></i>
                            <span>Cancelar</span>
                        </button>
                    </div>

                    <!-- Tabla de Productos -->
                    <div class="mt-8">
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                                Productos Registrados
                            </h4>
                        </div>

                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="tbProductosPublicos">
                                    <thead class="bg-gray-800 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                ID
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Nombre
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Descripción
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Estado
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Fecha Creación
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Última Actualización
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Opciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php
                                        // Establecer charset UTF-8
                                        mysqli_set_charset($conexion, 'utf8mb4');
                                        
                                        $consulta = mysqli_query($conexion, "SELECT id, nombre, descripcion, published, created_at, updated_at 
                                    FROM cre_prod_public ORDER BY id DESC");

                                        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            $id = $row["id"];
                                            $nombre = mb_convert_encoding($row["nombre"], 'UTF-8', 'auto');
                                            $descripcion = mb_convert_encoding($row["descripcion"], 'UTF-8', 'auto');
                                            $published = $row["published"];
                                            $created_at = $row["created_at"];
                                            $updated_at = $row["updated_at"];

                                            $fecha_creacion = $created_at ? date('d/m/Y H:i', strtotime($created_at)) : 'N/A';
                                            $fecha_actualizacion = $updated_at ? date('d/m/Y H:i', strtotime($updated_at)) : 'N/A';

                                            $dato = $id . "||" . $nombre . "||" . $descripcion . "||" . $published;
                                        ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    <?= $id ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-xs">
                                                    <?php
                                                    if (mb_strlen($descripcion) > 50) {
                                                        echo htmlspecialchars(mb_substr($descripcion, 0, 50), ENT_QUOTES, 'UTF-8') . '...';
                                                    } else {
                                                        echo htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8');
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <?php if ($published == 1): ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                            Publicado
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                                            Oculto
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    <?= $fecha_creacion ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    <?= $fecha_actualizacion ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <div class="flex gap-1.5">
                                                        <!-- Botón Editar -->
                                                        <button
                                                            type="button"
                                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition duration-200"
                                                            onclick="editarProductoPublico('<?php echo $dato ?>')"
                                                            title="Editar producto">
                                                            Editar
                                                        </button>

                                                        <!-- Botón Publicar/Despublicar -->
                                                        <?php if ($published == 1): ?>
                                                            <button
                                                                type="button"
                                                                class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded transition duration-200"
                                                                onclick="cambiarEstadoProducto(<?php echo $id ?>, 0)"
                                                                title="Ocultar producto">
                                                                Ocultar
                                                            </button>
                                                        <?php else: ?>
                                                            <button
                                                                type="button"
                                                                class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded transition duration-200"
                                                                onclick="cambiarEstadoProducto(<?php echo $id ?>, 1)"
                                                                title="Publicar producto">
                                                                Publicar
                                                            </button>
                                                        <?php endif; ?>

                                                        <!-- Botón Eliminar -->
                                                        <button
                                                            type="button"
                                                            class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded transition duration-200"
                                                            onclick="eliminarProductoPublico(<?php echo $id ?>)"
                                                            title="Eliminar producto">
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // ============================================
            // GUARDAR PRODUCTO - NUEVA FUNCIÓN
            // ============================================
            function guardarProductoPublicoBtn() {
                ////////console.log('=== CLICK EN GUARDAR ===');
                
                if(!validaProductoPublico()){
                    ////////console.log('❌ Validación falló');
                    return;
                }
                
                ////////console.log('✅ Validación exitosa, enviando datos...');
                
                var formData = new FormData();
                formData.append('action', 'guardarProductoPublico');
                formData.append('nomPro', $('#nomPro').val().trim());
                formData.append('desPro', $('#desPro').val().trim());
                formData.append('published', $('#published').val());
                formData.append('inputs[csrf_token]', $('input[name="csrf_token"]').val());
                
                $.ajax({
                    url: '../../src/cruds/crud_banca.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        loaderefect(1);
                    },
                    success: function(resp) {
                        ////////console.log('Respuesta:', resp);
                        try {
                            var res = typeof resp === 'string' ? JSON.parse(resp) : resp;
                            if (res.status == 1) {
                                Swal.fire('Éxito', res.msg, 'success');
                                limpiarInputsPublico();
                                recargarTablaProductos();
                            } else {
                                Swal.fire('Error', res.msg, 'error');
                            }
                        } catch (e) {
                            console.error('Error al parsear respuesta:', e);
                            Swal.fire('Error', 'Error al procesar la respuesta del servidor', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', xhr.responseText);
                        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                    },
                    complete: function() {
                        loaderefect(0);
                    }
                });
            }

            // ============================================
            // ACTUALIZAR PRODUCTO - NUEVA FUNCIÓN
            // ============================================
            function actualizarProductoPublicoBtn() {
                ////////console.log('=== CLICK EN ACTUALIZAR ===');
                
                if(!validaProductoPublico()){
                    ////////console.log('❌ Validación falló');
                    return;
                }
                
                ////////console.log('✅ Validación exitosa, enviando datos...');
                
                var formData = new FormData();
                formData.append('action', 'actualizarProductoPublico');
                formData.append('idPro', $('#idPro').val());
                formData.append('nomPro', $('#nomPro').val().trim());
                formData.append('desPro', $('#desPro').val().trim());
                formData.append('published', $('#published').val());
                formData.append('inputs[csrf_token]', $('input[name="csrf_token"]').val());
                
                $.ajax({
                    url: '../../src/cruds/crud_banca.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        loaderefect(1);
                    },
                    success: function(resp) {
                        ////////console.log('Respuesta:', resp);
                        try {
                            var res = typeof resp === 'string' ? JSON.parse(resp) : resp;
                            if (res.status == 1) {
                                Swal.fire('Éxito', res.msg, 'success');
                                limpiarInputsPublico();
                                recargarTablaProductos();
                            } else {
                                Swal.fire('Error', res.msg, 'error');
                            }
                        } catch (e) {
                            console.error('Error al parsear respuesta:', e);
                            Swal.fire('Error', 'Error al procesar la respuesta del servidor', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', xhr.responseText);
                        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                    },
                    complete: function() {
                        loaderefect(0);
                    }
                });
            }

            // ============================================
            // FUNCIÓN DE VALIDACIÓN CON DEBUG
            // ============================================
            function validaProductoPublico() {
                ////////console.log("=== INICIO VALIDACIÓN PRODUCTO PÚBLICO ===");

                var elementoNombre = $("#nomPro");
                ////////console.log("Elemento nomPro encontrado:", elementoNombre.length > 0);

                var nombre = $("#nomPro").val();
                ////////console.log("Valor crudo de nomPro:", JSON.stringify(nombre));
                ////////console.log("Tipo de dato:", typeof nombre);

                var nombreTrimmed = nombre ? nombre.trim() : '';
                ////////console.log("Valor después de trim:", JSON.stringify(nombreTrimmed));
                ////////console.log("Longitud después de trim:", nombreTrimmed.length);

                var descripcion = $("#desPro").val();
                var descripcionTrimmed = descripcion ? descripcion.trim() : '';
                ////////console.log("Descripción:", JSON.stringify(descripcionTrimmed));

                var published = $("#published").val();
                ////////console.log("Estado de publicación:", published);

                if (nombreTrimmed === '' || nombreTrimmed === null || nombreTrimmed === undefined) {
                    ////////console.log("ERROR: Nombre vacío detectado");
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'El nombre del producto es obligatorio'
                    });
                    $("#nomPro").focus();
                    return false;
                }

                if (nombreTrimmed.length < 3) {
                    ////////console.log("ERROR: Nombre muy corto:", nombreTrimmed.length, "caracteres");
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'El nombre del producto debe tener al menos 3 caracteres'
                    });
                    $("#nomPro").focus();
                    return false;
                }

                ////////console.log("✅ Validación exitosa");
                ////////console.log("=== FIN VALIDACIÓN PRODUCTO PÚBLICO ===");
                return true;
            }

            // ============================================
            // EDITAR PRODUCTO
            // ============================================
            function editarProductoPublico(datos) {
                var campos = datos.split('||');

                $("#idPro").val(campos[0]);
                $("#nomPro").val(campos[1]);
                $("#desPro").val(campos[2]);
                $("#published").val(campos[3]);

                $("#btnGua").hide();
                $("#btnAct").show();

                // Scroll suave al formulario
                $('html, body').animate({
                    scrollTop: $(".bg-gradient-to-r").offset().top - 100
                }, 500);
            }

            // ============================================
            // CAMBIAR ESTADO DE PUBLICACIÓN
            // ============================================
            function cambiarEstadoProducto(id, nuevoEstado) {
                var mensaje = nuevoEstado == 1 ? '¿Desea publicar este producto?' : '¿Desea despublicar este producto?';

                Swal.fire({
                    title: 'Confirmación',
                    text: mensaje,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var formData = new FormData();
                        formData.append('action', 'cambiarEstadoProductoPublico');
                        formData.append('tempId', id);
                        formData.append('tempPublished', nuevoEstado);
                        formData.append('inputs[csrf_token]', $('input[name="csrf_token"]').val());
                        
                        $.ajax({
                            url: '../../src/cruds/crud_banca.php',
                            method: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            success: function(resp) {
                                try {
                                    var res = typeof resp === 'string' ? JSON.parse(resp) : resp;
                                    if (res.status == 1) {
                                        Swal.fire('Éxito', res.msg, 'success');
                                        recargarTablaProductos();
                                    } else {
                                        Swal.fire('Error', res.msg, 'error');
                                    }
                                } catch (e) {
                                    console.error('Error al parsear:', e);
                                    Swal.fire('Error', 'Error al procesar respuesta', 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'No se pudo conectar', 'error');
                            },
                            complete: function() {
                                loaderefect(0);
                            }
                        });
                    }
                });
            }

            // ============================================
            // ELIMINAR PRODUCTO
            // ============================================
            function eliminarProductoPublico(id) {
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: 'Esta acción eliminará el producto permanentemente',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var formData = new FormData();
                        formData.append('action', 'eliminarProductoPublico');
                        formData.append('tempIdEliminar', id);
                        formData.append('inputs[csrf_token]', $('input[name="csrf_token"]').val());
                        
                        $.ajax({
                            url: '../../src/cruds/crud_banca.php',
                            method: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            success: function(resp) {
                                try {
                                    var res = typeof resp === 'string' ? JSON.parse(resp) : resp;
                                    if (res.status == 1) {
                                        Swal.fire('Eliminado', res.msg, 'success');
                                        recargarTablaProductos();
                                    } else {
                                        Swal.fire('Error', res.msg, 'error');
                                    }
                                } catch (e) {
                                    console.error('Error al parsear:', e);
                                    Swal.fire('Error', 'Error al procesar respuesta', 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'No se pudo conectar', 'error');
                            },
                            complete: function() {
                                loaderefect(0);
                            }
                        });
                    }
                });
            }

            // ============================================
            // LIMPIAR FORMULARIO
            // ============================================
            function limpiarInputsPublico() {
                $("#idPro").val('');
                $("#nomPro").val('');
                $("#desPro").val('');
                $("#published").val('0');

                $("#btnGua").show();
                $("#btnAct").hide();
            }

            // ============================================
            // RECARGAR TABLA DINÁMICAMENTE
            // ============================================
            function recargarTablaProductos() {
                // Destruir DataTable existente
                if ($.fn.DataTable.isDataTable('#tbProductosPublicos')) {
                    $('#tbProductosPublicos').DataTable().destroy();
                }

                // Recargar los datos de la tabla mediante AJAX
                $.ajax({
                    url: '../../src/cruds/crud_banca.php',
                    method: 'POST',
                    data: {
                        action: 'public_crud',
                        subaction: 'list',
                        table: 'cre_prod_public',
                        limit: 1000
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status == 1) {
                            // Limpiar tbody
                            $('#tbProductosPublicos tbody').empty();
                            
                            // Agregar nuevas filas
                            response.data.forEach(function(producto) {
                                var estadoBadge = '';
                                var estadoBoton = '';
                                
                                if (producto.published == 1) {
                                    estadoBadge = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Publicado</span>';
                                    estadoBoton = '<button type="button" class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded transition duration-200" onclick="cambiarEstadoProducto(' + producto.id + ', 0)" title="Ocultar producto">Ocultar</button>';
                                } else {
                                    estadoBadge = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Oculto</span>';
                                    estadoBoton = '<button type="button" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded transition duration-200" onclick="cambiarEstadoProducto(' + producto.id + ', 1)" title="Publicar producto">Publicar</button>';
                                }
                                
                                var descripcionCorta = producto.descripcion && producto.descripcion.length > 50 
                                    ? producto.descripcion.substring(0, 50) + '...' 
                                    : (producto.descripcion || '');
                                
                                var fechaCreacion = producto.created_at ? new Date(producto.created_at).toLocaleString('es-GT', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : 'N/A';
                                var fechaActualizacion = producto.updated_at ? new Date(producto.updated_at).toLocaleString('es-GT', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : 'N/A';
                                
                                // Escapar comillas en los datos
                                var datoEscaped = producto.id + '||' + producto.nombre.replace(/'/g, "\\'") + '||' + producto.descripcion.replace(/'/g, "\\'") + '||' + producto.published;
                                
                                var fila = '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">' +
                                    '<td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">' + producto.id + '</td>' +
                                    '<td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">' + producto.nombre + '</td>' +
                                    '<td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-xs">' + descripcionCorta + '</td>' +
                                    '<td class="px-4 py-3 text-sm">' + estadoBadge + '</td>' +
                                    '<td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">' + fechaCreacion + '</td>' +
                                    '<td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">' + fechaActualizacion + '</td>' +
                                    '<td class="px-4 py-3 text-sm"><div class="flex gap-1.5">' +
                                    '<button type="button" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition duration-200" onclick="editarProductoPublico(\'' + datoEscaped + '\')" title="Editar producto">Editar</button>' +
                                    estadoBoton +
                                    '<button type="button" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded transition duration-200" onclick="eliminarProductoPublico(' + producto.id + ')" title="Eliminar producto">Eliminar</button>' +
                                    '</div></td>' +
                                    '</tr>';
                                
                                $('#tbProductosPublicos tbody').append(fila);
                            });
                            
                            // Reinicializar DataTable
                            inicializarDataTableProductos();
                        } else {
                            Swal.fire('Error', 'No se pudieron cargar los productos', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Error al recargar la tabla', 'error');
                    }
                });
            }

            // ============================================
            // INICIALIZAR DATATABLE
            // ============================================
            function inicializarDataTableProductos() {
                $('#tbProductosPublicos').DataTable({
                    "lengthMenu": [
                        [5, 10, 25, 50, -1],
                        ['5 filas', '10 filas', '25 filas', '50 filas', 'Mostrar todos']
                    ],
                    dom: 'Bfrtilp',
                    buttons: [{
                        extend: 'excelHtml5',
                        title: 'Productos Crediticios Públicos',
                        text: "Excel <i class='fa-solid fa-file-csv'></i>",
                        titleAttr: 'Exportar a Excel',
                        className: 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        }
                    }, {
                        extend: 'pdfHtml5',
                        title: 'Productos Crediticios Públicos',
                        text: "PDF <i class='fa-solid fa-file-pdf'></i>",
                        titleAttr: 'Exportar a PDF',
                        className: 'bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition duration-200',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        },
                        customize: function(doc) {
                            doc.pageOrientation = 'landscape';
                        }
                    }, {
                        extend: 'print',
                        title: 'Productos Crediticios Públicos',
                        text: "Imprimir <i class='fa-solid fa-print'></i>",
                        titleAttr: 'Imprimir',
                        className: 'bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        }
                    }],
                    "order": [
                        [0, "desc"]
                    ],
                    "language": {
                        "sProcessing": "Procesando...",
                        "sLengthMenu": "Mostrar _MENU_ registros",
                        "sZeroRecords": "No se encontraron resultados",
                        "sEmptyTable": "Ningún dato disponible en esta tabla",
                        "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                        "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
                        "sInfoPostFix": "",
                        "sSearch": "Buscar:",
                        "sUrl": "",
                        "sInfoThousands": ",",
                        "sLoadingRecords": "Cargando...",
                        "oPaginate": {
                            "sFirst": "Primero",
                            "sLast": "Último",
                            "sNext": "Siguiente",
                            "sPrevious": "Anterior"
                        },
                        "oAria": {
                            "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
                            "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                        }
                    },
                    "responsive": true
                });
            }

            $(document).ready(function() {
                inicializarDataTableProductos();
            });
        </script>
    <?php
        break;

    case 'Servicios_virtual_bank':
    ?>
        <!-- Título Principal -->
        <h2 class="text-2xl font-semibold text-center text-gray-500 dark:text-gray-100 mb-6">
            Módulo de Servicios - Banca Virtual
        </h2>

        <input type="hidden" value="Servicios_virtual_bank" id="condi">
        <input type="hidden" value="cuencobra/views/Servicios_virtual_bank" id="file">
        <input type="hidden" id="token" name="token" value="<?php echo $_SESSION['token'] ?? ''; ?>">
        <?php echo $csrf->getTokenField(); ?>

        <!-- Contenedor Principal -->
        <div class="container mx-auto px-4 space-y-8">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                    <p><?= htmlspecialchars($mensaje) ?></p>
                </div>
            <?php endif; ?>

            <!-- Card Principal -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <!-- Header del Card -->
                <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
                    <h3 class="text-xl font-semibold text-white flex items-center gap-2">
                        <i class="fa-solid fa-concierge-bell"></i>
                        Control de Servicios Públicos - Banca Virtual
                    </h3>
                </div>

                <!-- Body del Card -->
                <div class="p-6 space-y-6">
                    <!-- Input oculto para ID del servicio -->
                    <input type="hidden" id="idSer" name="idSer" value="">
                    <input type="hidden" id="imgActual" name="imgActual" value="">

                    <!-- Formulario de Registro/Edición -->
                    <div class="space-y-4">
                        <!-- Título del Servicio -->
                        <div>
                            <label for="titSer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Título del servicio <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition duration-200"
                                id="titSer"
                                name="titSer"
                                placeholder="Ingrese el título del servicio"
                                maxlength="200">
                        </div>

                        <!-- Descripción del Servicio -->
                        <div>
                            <label for="bodSer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Descripción del servicio <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition duration-200 resize-none"
                                placeholder="Ingrese la descripción del servicio"
                                id="bodSer"
                                name="bodSer"
                                rows="4"></textarea>
                        </div>

                        <!-- Imagen del Servicio -->
                        <div>
                            <label for="imgSer" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Imagen del servicio
                            </label>
                            <div class="flex items-center justify-center w-full">
                                <label for="imgSer" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition duration-200">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-12 h-12 mb-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="font-semibold">Clic para cargar</span> o arrastrar y soltar
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            JPG, PNG, GIF (MAX. 2MB)
                                        </p>
                                    </div>
                                    <input
                                        id="imgSer"
                                        name="imgSer"
                                        type="file"
                                        class="hidden"
                                        accept="image/*"
                                        onchange="previewImage(this)">
                                </label>
                            </div>

                            <!-- Vista previa de la imagen -->
                            <div id="imagePreview" class="mt-4 hidden">
                                <div class="relative inline-block">
                                    <img
                                        id="previewImg"
                                        src=""
                                        alt="Vista previa"
                                        class="max-w-xs max-h-64 rounded-lg border-2 border-gray-300 dark:border-gray-600 shadow-md">
                                    <button
                                        type="button"
                                        class="absolute top-2 right-2 px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg transition duration-200 shadow-lg text-sm font-medium"
                                        onclick="removeImage()"
                                        title="Remover imagen">
                                        ✕ Quitar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="button"
                            id="btnGuaSer"
                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm"
                            onclick="
                            ////////console.log('=== CLICK EN GUARDAR SERVICIO ===');
                            ////////console.log('Validando formulario...');
                            if(!validaServicioPublico()){
                                ////////console.log('❌ Validación falló, deteniendo proceso');
                                return;
                            }
                            ////////console.log('✅ Validación exitosa, procediendo a guardar...');
                            guardarServicioPublico();
                        ">
                            <span>Guardar</span>
                        </button>

                        <button
                            style="display: none;"
                            type="button"
                            id="btnActSer"
                            class="px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-white font-medium rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm"
                            onclick="if(!validaServicioPublico()){return;}; actualizarServicioPublico()">
                            <span>Actualizar</span>
                        </button>

                        <button
                            type="button"
                            id="btnCanSer"
                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition duration-200 ease-in-out flex items-center gap-2 shadow-sm"
                            onclick="limpiarInputsServicio()">
                            <span>Cancelar</span>
                        </button>
                    </div>

                    <!-- Tabla de Servicios -->
                    <div class="mt-8">
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                                <i class="fa-solid fa-list"></i>
                                Servicios Registrados
                            </h4>
                        </div>

                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="tbServiciosPublicos">
                                    <thead class="bg-gray-800 dark:bg-gray-900">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                ID
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Imagen
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Título
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Descripción
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Fecha Creación
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Última Actualización
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-200 uppercase tracking-wider">
                                                Opciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <?php
                                        // Establecer charset UTF-8
                                        mysqli_set_charset($conexion, 'utf8mb4');
                                        
                                        // Usar FileProcessor para procesar imágenes (mismo patrón que clientes)
                                        $fileProcessor = new FileProcessor(__DIR__ . '/../../../../');
                                        
                                        $consulta = mysqli_query($conexion, "SELECT id, title, body, image, created_at, updated_at 
                                    FROM services_public ORDER BY id DESC");

                                        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                            $id = $row["id"];
                                            $title = mb_convert_encoding($row["title"], 'UTF-8', 'auto');
                                            $body = mb_convert_encoding($row["body"], 'UTF-8', 'auto');
                                            $image_path = $row["image"];
                                            $created_at = $row["created_at"];
                                            $updated_at = $row["updated_at"];

                                            $fecha_creacion = $created_at ? date('d/m/Y H:i', strtotime($created_at)) : 'N/A';
                                            $fecha_actualizacion = $updated_at ? date('d/m/Y H:i', strtotime($updated_at)) : 'N/A';
                                            
                                            // Procesar imagen con FileProcessor
                                            $imageData = null;
                                            $imageDataUri = '';
                                            
                                            if (!empty($image_path)) {
                                                if ($fileProcessor->fileExists($image_path)) {
                                                    if ($fileProcessor->isImage($image_path)) {
                                                        // Generar Data URI (base64) para mostrar la imagen
                                                        $imageDataUri = $fileProcessor->getDataUri($image_path);
                                                    }
                                                }
                                            }

                                            $dato = $id . "||" .
                                                str_replace(['||', '"', "'"], ['|', '&quot;', '&#39;'], $title) . "||" .
                                                str_replace(['||', '"', "'"], ['|', '&quot;', '&#39;'], $body) . "||" .
                                                ($imageDataUri ?: '');
                                        ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-150">
                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    <?= $id ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <?php if (!empty($imageDataUri)): ?>
                                                        <img
                                                            src="<?= $imageDataUri ?>"
                                                            alt="Imagen del servicio"
                                                            class="w-12 h-12 object-cover rounded-lg border-2 border-gray-300 dark:border-gray-600 cursor-pointer hover:scale-110 transition-transform duration-200"
                                                            onclick="mostrarImagenCompleta('<?= htmlspecialchars($imageDataUri, ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>')">
                                                    <?php else: ?>
                                                        <div class="w-12 h-12 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                                            <i class="fas fa-image text-gray-400 dark:text-gray-500"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-xs">
                                                    <?php
                                                    if (mb_strlen($body) > 50) {
                                                        echo htmlspecialchars(mb_substr($body, 0, 50), ENT_QUOTES, 'UTF-8') . '...';
                                                    } else {
                                                        echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    <i class="far fa-calendar mr-1"></i><?= $fecha_creacion ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                    <i class="far fa-clock mr-1"></i><?= $fecha_actualizacion ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <div class="flex gap-1.5">
                                                        <!-- Botón Editar -->
                                                        <button
                                                            type="button"
                                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition duration-200"
                                                            onclick="editarServicioPublico('<?php echo $dato ?>')"
                                                            title="Editar servicio">
                                                            Editar
                                                        </button>

                                                        <!-- Botón Eliminar -->
                                                        <button
                                                            type="button"
                                                            class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded transition duration-200"
                                                            onclick="eliminarServicioPublico(<?php echo $id ?>)"
                                                            title="Eliminar servicio">
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // ============================================
            // VALIDACIÓN DE FORMULARIO
            // ============================================
            function validaServicioPublico() {
                ////////console.log("=== INICIO VALIDACIÓN SERVICIO PÚBLICO ===");

                var titulo = $("#titSer").val();
                var tituloTrimmed = titulo ? titulo.trim() : '';

                var body = $("#bodSer").val();
                var bodyTrimmed = body ? body.trim() : '';

                ////////console.log("Título:", JSON.stringify(tituloTrimmed));
                ////////console.log("Descripción:", JSON.stringify(bodyTrimmed));

                if (tituloTrimmed === '' || tituloTrimmed === null || tituloTrimmed === undefined) {
                    ////////console.log("ERROR: Título vacío detectado");
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'El título del servicio es obligatorio'
                    });
                    $("#titSer").focus();
                    return false;
                }

                if (tituloTrimmed.length < 3) {
                    ////////console.log("ERROR: Título muy corto:", tituloTrimmed.length, "caracteres");
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'El título del servicio debe tener al menos 3 caracteres'
                    });
                    $("#titSer").focus();
                    return false;
                }

                if (bodyTrimmed === '' || bodyTrimmed === null || bodyTrimmed === undefined) {
                    ////////console.log("ERROR: Descripción vacía detectada");
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'La descripción del servicio es obligatoria'
                    });
                    $("#bodSer").focus();
                    return false;
                }

                if (bodyTrimmed.length < 10) {
                    ////////console.log("ERROR: Descripción muy corta:", bodyTrimmed.length, "caracteres");
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'La descripción del servicio debe tener al menos 10 caracteres'
                    });
                    $("#bodSer").focus();
                    return false;
                }

                ////////console.log("✅ Validación exitosa");
                ////////console.log("=== FIN VALIDACIÓN SERVICIO PÚBLICO ===");
                return true;
            }

            // ============================================
            // VISTA PREVIA DE IMAGEN
            // ============================================
            function previewImage(input) {
                ////////console.log('📷 previewImage() llamada');
                
                if (input.files && input.files[0]) {
                    var file = input.files[0];
                    ////////console.log('📁 Archivo seleccionado:', file.name, 'Tamaño:', file.size, 'bytes');

                    // Validar tipo de archivo
                    var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                    if (!allowedTypes.includes(file.type)) {
                        ////////console.log('❌ Tipo no permitido:', file.type);
                        Swal.fire({
                            icon: 'error',
                            title: 'Tipo de archivo no válido',
                            text: 'Solo se permiten archivos JPG, PNG y GIF'
                        });
                        input.value = '';
                        return;
                    }

                    // Validar tamaño (2MB)
                    if (file.size > 2 * 1024 * 1024) {
                        ////////console.log('❌ Archivo muy grande:', file.size, 'bytes');
                        Swal.fire({
                            icon: 'error',
                            title: 'Archivo muy grande',
                            text: 'El archivo no debe superar los 2MB'
                        });
                        input.value = '';
                        return;
                    }

                    ////////console.log('✅ Validaciones pasadas, generando vista previa...');
                    
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#previewImg').attr('src', e.target.result);
                        $('#imagePreview').removeClass('hidden').show();
                        ////////console.log('✅ Vista previa mostrada');
                    };
                    reader.readAsDataURL(file);
                } else {
                    ////////console.log('⚠️ No se detectó archivo');
                }
            }

            function removeImage() {
                //////////console.log('🗑️ Removiendo imagen de vista previa');
                $('#imgSer').val('');
                $('#imagePreview').addClass('hidden').hide();
                $('#previewImg').attr('src', '');
                $('#imgActual').val('');
                ////////console.log('✅ Imagen removida');
            }

            // ============================================
            // GUARDAR SERVICIO CON IMAGEN
            // ============================================
            function guardarServicioPublico() {
                ////////console.log('=== INICIO guardarServicioPublico() ===');
                
                if (!validaServicioPublico()) {
                    ////////console.log('❌ Validación falló');
                    return;
                }
                
                ////////console.log('✅ Validación exitosa, preparando FormData...');

                var formData = new FormData();
                formData.append('action', 'guardarServicioPublico');
                formData.append('titSer', $('#titSer').val().trim());
                formData.append('bodSer', $('#bodSer').val().trim());
                
                // Obtener el token CSRF
                var csrfToken = $('input[name="csrf_token"]').val();
                ////////console.log('🔑 CSRF Token encontrado:', csrfToken ? '✅ SÍ' : '❌ NO');
                ////////console.log('🔑 Valor del token:', csrfToken);
                
                if (!csrfToken) {
                    console.error('❌ ERROR: No se encontró el token CSRF en la página');
                    Swal.fire({
                        icon: 'error',
                        title: '¡Error!',
                        text: 'No se encontró el token de seguridad. Por favor, recargue la página.'
                    });
                    return;
                }
                
                formData.append('inputs[csrf_token]', csrfToken);

                var imageFile = $('#imgSer')[0].files[0];
                if (imageFile) {
                    ////////console.log('📷 Imagen adjunta:', imageFile.name, '(' + imageFile.size + ' bytes)');
                    formData.append('imgSer', imageFile);
                } else {
                    ////////console.log('📷 Sin imagen adjunta');
                }
                
                ////////console.log('📤 Enviando datos al servidor...');

                $.ajax({
                    url: '../../src/cruds/crud_banca.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    beforeSend: function() {
                        Swal.fire({
                            title: 'Guardando...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    },
                    success: function(response) {
                        ////////console.log('📥 Respuesta del servidor:', response);
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: response.mensaje,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(function() {
                                limpiarInputsServicio();
                                recargarTablaServicios();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '¡Error!',
                                text: response.mensaje
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error AJAX:', {xhr, status, error});
                        console.error('❌ Respuesta:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: '¡Error!',
                            text: 'Error al procesar la solicitud: ' + error
                        });
                    }
                });
            }

            // ============================================
            // ACTUALIZAR SERVICIO
            // ============================================
            function actualizarServicioPublico() {
                if (!validaServicioPublico()) {
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'actualizarServicioPublico');
                formData.append('idSerUpdate', $('#idSer').val());
                formData.append('titSerUpdate', $('#titSer').val().trim());
                formData.append('bodSerUpdate', $('#bodSer').val().trim());
                formData.append('inputs[csrf_token]', $('input[name="csrf_token"]').val());

                var imageFile = $('#imgSer')[0].files[0];
                if (imageFile) {
                    formData.append('imgSerUpdate', imageFile);
                }

                $.ajax({
                    url: '../../src/cruds/crud_banca.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    beforeSend: function() {
                        Swal.fire({
                            title: 'Actualizando...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: response.mensaje,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(function() {
                                limpiarInputsServicio();
                                recargarTablaServicios();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '¡Error!',
                                text: response.mensaje
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: '¡Error!',
                            text: 'Error al procesar la solicitud'
                        });
                    }
                });
            }

            // ============================================
            // EDITAR SERVICIO
            // ============================================
            function editarServicioPublico(datos) {
                var campos = datos.split('||');

                $('#idSer').val(campos[0]);
                $('#titSer').val(campos[1].replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
                $('#bodSer').val(campos[2].replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
                $('#imgActual').val(campos[3]);

                if (campos[3] && campos[3] !== '') {
                    $('#previewImg').attr('src', campos[3]);
                    $('#imagePreview').removeClass('hidden').show();
                } else {
                    $('#imagePreview').addClass('hidden').hide();
                }

                $('#btnGua').hide();
                $('#btnAct').show();

                $('html, body').animate({
                    scrollTop: $(".bg-gradient-to-r").offset().top - 100
                }, 500);
            }

            // ============================================
            // ELIMINAR SERVICIO
            // ============================================
            function eliminarServicioPublico(id) {
                Swal.fire({
                    title: '¿Está seguro?',
                    text: 'Esta acción eliminará el servicio permanentemente',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '../../src/cruds/crud_banca.php',
                            type: 'POST',
                            data: {
                                action: 'eliminarServicioPublico',
                                'inputs[csrf_token]': $('input[name="csrf_token"]').val(),
                                id: id
                            },
                            dataType: 'json',
                            beforeSend: function() {
                                Swal.fire({
                                    title: 'Eliminando...',
                                    text: 'Por favor espere',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Eliminado!',
                                        text: response.mensaje,
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(function() {
                                        recargarTablaServicios();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: '¡Error!',
                                        text: response.mensaje
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: '¡Error!',
                                    text: 'Error al procesar la solicitud'
                                });
                            }
                        });
                    }
                });
            }

            // ============================================
            // LIMPIAR FORMULARIO
            // ============================================
            function limpiarInputsServicio() {
                $('#idSer').val('');
                $('#titSer').val('');
                $('#bodSer').val('');
                $('#imgSer').val('');
                $('#imgActual').val('');
                $('#imagePreview').addClass('hidden').hide();
                $('#previewImg').attr('src', '');

                $('#btnGuaSer').show();
                $('#btnActSer').hide();
            }

            // ============================================
            // RECARGAR TABLA SERVICIOS DINÁMICAMENTE
            // ============================================
            function recargarTablaServicios() {
                $.ajax({
                    url: '../../src/cruds/crud_banca.php?action=services_crud&subaction=list',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        ////////console.log('📥 Respuesta del servidor:', response);
                        
                        if (response.success) {
                            var table = $('#tbServiciosPublicos').DataTable();
                            table.clear();

                            response.servicios.forEach(function(servicio) {
                                ////////console.log('🔍 Procesando servicio:', servicio.id);
                                
                                var imageHtml = '';
                                
                                // El backend ahora envía un objeto con la información de la imagen
                                if (servicio.image && typeof servicio.image === 'object') {
                                    if (servicio.image.exists && servicio.image.is_image && servicio.image.data_uri) {
                                        // ✅ Imagen procesada correctamente - usar Data URI
                                        imageHtml = '<img src="' + servicio.image.data_uri + '" alt="' + servicio.title + '" ' +
                                            'class="w-16 h-16 object-cover rounded cursor-pointer" ' +
                                            'onclick="mostrarImagenCompleta(\'' + servicio.image.data_uri.replace(/'/g, "\\'") + '\', \'' + servicio.title.replace(/'/g, "\\'") + '\')" ' +
                                            'title="Click para ampliar">';
                                        ////////console.log('✅ Imagen cargada con Data URI');
                                    } else if (servicio.image.path) {
                                        // ⚠️ Archivo existe pero no es imagen
                                        imageHtml = '<div class="w-16 h-16 bg-yellow-100 rounded flex items-center justify-center" title="Archivo no es imagen">' +
                                            '<i class="fas fa-file text-yellow-600"></i></div>';
                                        ////////console.log('⚠️ Archivo no es imagen');
                                    } else {
                                        // ❌ Archivo no encontrado
                                        imageHtml = '<div class="w-16 h-16 bg-red-100 rounded flex items-center justify-center" title="Imagen no encontrada">' +
                                            '<i class="fas fa-exclamation-triangle text-red-600"></i></div>';
                                        ////////console.log('❌ Imagen no encontrada');
                                    }
                                } else {
                                    // Sin imagen
                                    imageHtml = '<span class="text-gray-400 text-sm">Sin imagen</span>';
                                    ////////console.log('📭 Sin imagen');
                                }

                                var descripcionCorta = servicio.body.length > 100 ?
                                    servicio.body.substring(0, 100) + '...' : servicio.body;

                                // Para editar, guardar el Data URI si existe
                                var imageForEdit = '';
                                if (servicio.image && typeof servicio.image === 'object' && servicio.image.data_uri) {
                                    imageForEdit = servicio.image.data_uri;
                                }
                                
                                var datos = servicio.id + '||' + servicio.title + '||' + servicio.body + '||' + imageForEdit;

                                var accionesHtml = '<div class="flex gap-2 justify-center">' +
                                    '<button onclick="editarServicioPublico(\'' + datos.replace(/'/g, "\\'") + '\')" ' +
                                    'class="px-3 py-1 text-white bg-blue-600 hover:bg-blue-700 rounded text-sm">Editar</button>' +
                                    '<button onclick="eliminarServicioPublico(' + servicio.id + ')" ' +
                                    'class="px-3 py-1 text-white bg-red-600 hover:bg-red-700 rounded text-sm">Eliminar</button>' +
                                    '</div>';

                                table.row.add([
                                    servicio.id,
                                    imageHtml,
                                    servicio.title,
                                    descripcionCorta,
                                    servicio.created_at,
                                    servicio.updated_at,
                                    accionesHtml
                                ]);
                            });

                            table.draw();
                            ////////console.log('✅ Tabla recargada con', response.servicios.length, 'servicios');
                        } else {
                            console.error('❌ Error en respuesta:', response.mensaje);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Error AJAX al recargar tabla:', {xhr, status, error});
                    }
                });
            }

            // ============================================
            // INICIALIZAR DATATABLE
            // ============================================
            function inicializarDataTableServicios() {
                if (!$.fn.DataTable.isDataTable('#tbServiciosPublicos')) {
                    $('#tbServiciosPublicos').DataTable({
                        "lengthMenu": [
                            [5, 10, 25, 50, -1],
                            ['5 filas', '10 filas', '25 filas', '50 filas', 'Mostrar todos']
                        ],
                        "language": {
                            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
                        },
                        "pageLength": 10,
                        "order": [
                            [0, "desc"]
                        ],
                        "columnDefs": [{
                                "orderable": false,
                                "targets": [1, 6]
                            },
                            {
                                "className": "text-center",
                                "targets": [0, 1, 6]
                            }
                        ]
                    });
                }
            }

            // ============================================
            // MOSTRAR IMAGEN COMPLETA
            // ============================================
            function mostrarImagenCompleta(imagen, titulo) {
                Swal.fire({
                    title: titulo,
                    imageUrl: imagen,
                    imageWidth: 400,
                    imageHeight: 300,
                    imageAlt: titulo,
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        image: 'rounded-lg'
                    }
                });
            }

            // ============================================
            // INICIALIZAR DATATABLE
            // ============================================
            $(document).ready(function() {
                inicializarDataTableServicios();
                recargarTablaServicios();
            });
        </script>
    <?php
        break;
}

function calcularPorcentajeSeguro($obtenido, $meta)
{
    $obtenidoNum = floatval($obtenido);
    $metaNum = floatval($meta);
    if ($metaNum == 0) {
        return ($obtenidoNum > 0) ? 100.00 : 0.00;
    }
    return round(($obtenidoNum / $metaNum) * 100, 2);
}
?>
?>