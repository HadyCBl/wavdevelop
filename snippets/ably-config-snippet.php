<!-- 
    ========================================
    SNIPPET: Configuración de Ably
    ========================================
    Copia este código en el <head> de tus vistas PHP
    para habilitar las notificaciones en tiempo real
-->

<?php
use Micro\Helpers\NotificationService;

// Obtener configuración de Ably
$notificationService = NotificationService::getInstance();
$ablyConfig = $notificationService->getClientConfig();
?>

<!-- Configuración de Ably para JavaScript -->
<script>
    // Configuración del sistema de notificaciones
    window.ablyConfig = <?= json_encode($ablyConfig) ?>;
    
    // Usuario y agencia actuales
    window.currentUserId = <?= $_SESSION['id'] ?? 'null' ?>;
    window.currentAgencyId = <?= $_SESSION['id_agencia'] ?? 'null' ?>;
    
    // Configuración adicional
    window.ENVIRONMENT = '<?= $_ENV['APP_ENV'] ?? 'production' ?>';
</script>

<!-- 
    NOTA: No necesitas agregar más código JavaScript
    El sistema de notificaciones se inicializa automáticamente
    cuando cargas vite_another.js
-->
