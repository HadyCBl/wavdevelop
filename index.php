<?php
use App\DatabaseAdapter;

include 'includes/Config/config.php';
session_start();

//manejo de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $errorMessage = "[PHP Error] $errstr en $errfile línea $errline";
    error_log($errorMessage, 3, __DIR__ . '/logs/php_errors.log');
});

set_exception_handler(function ($exception) {
    $errorMessage = "[PHP Exception] {$exception->getMessage()} en {$exception->getFile()} línea {$exception->getLine()}";
    error_log($errorMessage, 3, __DIR__ . '/logs/php_errors.log');
});

if (isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL . 'views/');
} else {
    require_once __DIR__ . '/includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();

    /**
     * Carga de datos de agencia
     */
    $showmensaje = false;
    try {
        $nameSystem = $appConfigGeneral->getNombreSistema();
        $logoLogin = $appConfigGeneral->getLogoLogin();
        $status = 1;
    } catch (Throwable $e) {
        if (!$showmensaje) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        }
        $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        $status = 0;
    } finally {
    }
?>
<script>
// 1. Bloquear reCAPTCHA
window.grecaptcha = {
    ready: function(callback) {
        console.log('reCAPTCHA bloqueado - ejecutando callback vacío');
        if (callback) {
            setTimeout(function() {
                callback();
            }, 100);
        }
    },
    execute: function(siteKey, options) {
        console.log('reCAPTCHA.execute bloqueado - devolviendo token vacío');
        return Promise.resolve('');
    },
    render: function() {
        console.log('reCAPTCHA.render bloqueado');
        return '';
    }
};

// 2. Deshabilitar Service Workers
document.addEventListener('DOMContentLoaded', function() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            registrations.forEach(function(registration) {
                registration.unregister().then(function(success) {
                    console.log('Service Worker desregistrado:', success);
                });
            });
        });
        
        if ('caches' in window) {
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        return caches.delete(cacheName);
                    })
                );
            }).then(function() {
                console.log('Cache limpiado');
            });
        }
    }
});
</script>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= $nameSystem ?? 'Wavdevelop' ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="includes/img/favmicro.ico">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: flex;
            max-width: 1000px;
            width: 100%;
        }
        
        .login-section {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .brand-section {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .brand-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 20px;
        }
        
        .brand-title {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .brand-subtitle {
            font-size: 18px;
            opacity: 0.9;
            font-weight: 300;
        }
        
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
            }
            
            .login-section, .brand-section {
                padding: 30px;
            }
        }
        
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading--hide {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <!-- Sección de Login -->
        <div class="login-section">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Iniciar Sesión</h2>
                <p class="text-gray-600">Por favor inicia sesión con tu cuenta</p>
            </div>
            
            <form method="POST" id="frmlogin" class="space-y-6">
                <!-- Usuario -->
                <div class="relative">
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        placeholder="Usuario"
                        required
                        class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300"
                        value="harvey001">
                </div>
                
                <!-- Contraseña -->
                <div class="relative">
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Contraseña"
                        required
                        class="w-full pl-12 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-300 password-input">
                    <button
                        type="button"
                        id="togglePasswordindex"
                        class="password-toggle">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
                
                <input type="hidden" name="condi" value="acceso">
                <?php echo $csrf->getTokenField(); ?>
                
                <button 
                    type="submit" 
                    id="btnEnviar" 
                    class="w-full login-btn text-white font-semibold py-3 px-4 rounded-lg shadow-lg">
                    INICIAR SESIÓN
                </button>
            </form>
        </div>
        
        <!-- Sección de Marca -->
        <div class="brand-section">
            <img 
                src="<?= $logoLogin ?? 'https://imagen.wavdevelop.com/ico.avif' ?>" 
                alt="<?= $nameSystem ?? 'Wavdevelop' ?> Logo" 
                class="brand-logo"
                oncontextmenu="return false;">
            
            <h1 class="brand-title">
                <?= $nameSystem ?? '' ?>
            </h1>
            
            <p class="brand-subtitle"></p>
        </div>
    </div>

    <!-- Loader -->
    <div class="loader-container loading--hide">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>/public/assets/mane/log.js"></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>/public/assets/mane/all.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePasswordindex').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>
<?php
}