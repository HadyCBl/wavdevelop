<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema en Mantenimiento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <div class="bg-white rounded-3xl shadow-2xl p-8 md:p-12 text-center">
            <!-- Icono animado -->
            <div class="float-animation mb-8">
                <i class="fas fa-tools text-8xl text-indigo-600"></i>
            </div>

            <!-- Título -->
            <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
                🚧 Sistema en Mantenimiento
            </h1>

            <!-- Mensaje -->
            <p class="text-xl text-gray-600 mb-8">
                Estamos realizando tareas de actualización para mejorar tu experiencia.
            </p>

            <!-- Detalles técnicos (solo en desarrollo) -->
            <?php if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development'): ?>
                <div class="bg-gray-100 rounded-lg p-6 mb-6 text-left">
                    <h3 class="text-lg font-bold text-gray-700 mb-3">
                        <i class="fas fa-info-circle"></i> Información técnica:
                    </h3>
                    <ul class="space-y-2 text-gray-600">
                        <?php if (!file_exists(__DIR__ . '/../vendor/autoload.php')): ?>
                            <!-- <li class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-2"></i>
                                <span>No se encontró <code class="bg-gray-200 px-2 py-1 rounded">vendor/</code></span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-terminal text-blue-500 mt-1 mr-2"></i>
                                <span>Ejecute: <code class="bg-gray-200 px-2 py-1 rounded">composer install</code></span>
                            </li> -->
                        <?php endif; ?>
                        
                        <?php if (isset($_ENV['APP_MAINTENANCE']) && $_ENV['APP_MAINTENANCE'] === 'true'): ?>
                            <li class="flex items-start">
                                <i class="fas fa-wrench text-purple-500 mt-1 mr-2"></i>
                                <span>Modo mantenimiento activado  <code class="bg-gray-200 px-2 py-1 rounded"></code></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Información de contacto -->
            <div class="border-t border-gray-200 pt-6">
                <p class="text-gray-500">
                    <i class="far fa-clock mr-2"></i>
                    Tiempo estimado: <strong>15-30 minutos</strong>
                </p>
                <p class="text-gray-500 mt-2">
                    <i class="fas fa-envelope mr-2"></i>
                    ¿Urgente? Contacta a: <strong>soporte</strong>
                    <!-- ¿Urgente? Contacta a: <strong>soporte@microsystem.com</strong> -->
                </p>
            </div>

            <!-- Botón de recarga -->
            <button 
                onclick="location.reload()" 
                class="mt-8 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-full transition duration-300 transform hover:scale-105 shadow-lg">
                <i class="fas fa-sync-alt mr-2"></i>
                Reintentar
            </button>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-white">
            <p class="text-sm">
                <i class="fas fa-shield-alt mr-1"></i>
                Microsystem+ © <?= date('Y') ?>
            </p>
        </div>
    </div>

    <!-- Auto-recarga cada 60 segundos (opcional) -->
    <script>
        // Descomentar para auto-recarga
        // setTimeout(() => location.reload(), 60000);
    </script>
</body>
</html>
