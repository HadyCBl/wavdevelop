<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejemplo - Sistema de Notificaciones Ably</title>
    
    <?php
    // Cargar configuración de Ably
    use Micro\Helpers\NotificationService;
    
    $notification = NotificationService::getInstance();
    $ablyConfig = $notification->getClientConfig();
    ?>
    
    <!-- Inyectar configuración de Ably en JavaScript -->
    <script>
        // Configuración de Ably
        window.ablyConfig = <?= json_encode($ablyConfig) ?>;
        
        // Variables de usuario actual
        window.currentUserId = <?= $_SESSION['id'] ?? 'null' ?>;
        window.currentAgencyId = <?= $_SESSION['id_agencia'] ?? 'null' ?>;
        
        // Configuración adicional
        window.ENVIRONMENT = '<?= $_ENV['APP_ENV'] ?? 'production' ?>';
        window.ablyConfig.enableSound = <?= json_encode($_SESSION['enable_notifications_sound'] ?? true) ?>;
    </script>
    
    <!-- CSS de SweetAlert2 para notificaciones -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="container">
        <h1>Dashboard</h1>
        
        <!-- Indicador de estado de Ably (opcional) -->
        <div class="connection-status">
            <span id="ably-status" class="px-3 py-1 rounded-full text-xs bg-gray-500 text-white">
                Inicializando...
            </span>
        </div>
        
        <!-- Contenido de la página -->
        <div class="content">
            <!-- Tu contenido aquí -->
        </div>
        
        <!-- Botón de prueba (solo para desarrollo) -->
        <?php if ($_ENV['APP_ENV'] === 'development'): ?>
        <div class="mt-4">
            <button onclick="testNotification()" class="btn btn-primary">
                Probar Notificación
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Vite Entry Point - Ably se inicializa automáticamente -->
    <?php if ($_ENV['APP_ENV'] === 'development'): ?>
        <!-- Desarrollo -->
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/includes/js/vite_another.js"></script>
    <?php else: ?>
        <!-- Producción -->
        <?php
        $manifest = json_decode(file_get_contents(__DIR__ . '/public/assets/vite-dist/manifest.json'), true);
        $anotherEntry = $manifest['includes/js/vite_another.js'];
        ?>
        <link rel="stylesheet" href="/public/assets/vite-dist/<?= $anotherEntry['css'][0] ?>">
        <script type="module" src="/public/assets/vite-dist/<?= $anotherEntry['file'] ?>"></script>
    <?php endif; ?>
    
    <!-- Script de prueba (solo desarrollo) -->
    <?php if ($_ENV['APP_ENV'] === 'development'): ?>
    <script>
        // Función de prueba para enviar notificación desde el frontend
        function testNotification() {
            if (window.notificationHelper) {
                window.notificationHelper.showToast({
                    type: 'success',
                    message: 'Esta es una notificación de prueba'
                });
            } else {
                alert('Sistema de notificaciones no disponible');
            }
        }
        
        // Log de estado de Ably después de cargar
        window.addEventListener('load', () => {
            setTimeout(() => {
                if (window.ablyHelper) {
                    console.log('Estado de Ably:', window.ablyHelper.getConnectionState());
                    console.log('Conectado:', window.ablyHelper.isConnected());
                } else {
                    console.warn('Ably Helper no disponible');
                }
            }, 2000);
        });
    </script>
    <?php endif; ?>
</body>
</html>
