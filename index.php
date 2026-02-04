<?php
use App\DatabaseAdapter;

include 'includes/Config/config.php';
session_start();

// Manejo de errores
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
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= $nameSystem ?? 'Microsystem' ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="includes/img/favmicro.ico">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, 
                rgba(0, 150, 255, 0.1) 0%, 
                rgba(0, 150, 255, 0.05) 25%, 
                rgba(0, 0, 0, 0.95) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Fondo decorativo con elementos geométricos */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 150, 255, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 150, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(0, 0, 0, 0.2) 0%, transparent 50%);
            z-index: -1;
        }
        
        /* Elementos flotantes */
        .floating-element {
            position: fixed;
            background: rgba(0, 150, 255, 0.1);
            border: 1px solid rgba(0, 150, 255, 0.2);
            border-radius: 50%;
            z-index: -1;
            animation: float 15s infinite linear;
        }
        
        .floating-element:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -150px;
            left: -150px;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: -100px;
            right: -100px;
            animation-delay: 5s;
        }
        
        .floating-element:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 50%;
            right: 10%;
            animation-delay: 10s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg);
            }
            25% {
                transform: translate(20px, 20px) rotate(90deg);
            }
            50% {
                transform: translate(0, 40px) rotate(180deg);
            }
            75% {
                transform: translate(-20px, 20px) rotate(270deg);
            }
        }
        
        /* Tarjeta de login */
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 0 40px rgba(0, 150, 255, 0.05);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
            padding: 40px;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 30px 60px -12px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.15),
                inset 0 0 50px rgba(0, 150, 255, 0.08);
        }
        
        /* Logo */
        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        
        /* Títulos */
        .login-title {
            color: #111827;
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .login-subtitle {
            color: #6b7280;
            text-align: center;
            font-size: 15px;
            margin-bottom: 32px;
            font-weight: 400;
        }
        
        /* Campos de entrada */
        .input-group {
            position: relative;
            margin-bottom: 24px;
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #0096FF;
            font-size: 18px;
            transition: color 0.3s ease;
        }
        
        .login-input {
            width: 100%;
            padding: 16px 20px 16px 52px;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            color: #111827;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .login-input:focus {
            border-color: #0096FF;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 150, 255, 0.1);
        }
        
        .login-input::placeholder {
            color: #9ca3af;
        }
        
        /* Botón de mostrar contraseña */
        .toggle-password {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 18px;
            transition: color 0.3s ease;
            padding: 0;
        }
        
        .toggle-password:hover {
            color: #0096FF;
        }
        
        /* Botón de login */
        .login-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #0096FF 0%, #0077CC 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 150, 255, 0.3);
        }
        
        .login-button:hover::before {
            left: 100%;
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        /* Sección inferior */
        .footer-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .brand-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin: 0 auto 15px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
        
        .brand-name {
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }
        
        .brand-tagline {
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 24px;
                margin: 20px;
            }
            
            .login-title {
                font-size: 24px;
            }
            
            .login-subtitle {
                font-size: 14px;
            }
        }
        
        /* Animación de entrada */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-container {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* Loader */
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        
        .loader-container.active {
            opacity: 1;
            visibility: visible;
        }
        
        .loading--hide {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Elementos flotantes de fondo -->
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    <div class="floating-element"></div>
    
    <div class="login-container">
        <!-- Logo y Título -->
        <div class="logo-container">
            <img src="includes/img/fondologo.png" alt="Logo" class="logo-img">
        </div>
        
        <h1 class="login-title">Iniciar Sesión</h1>
        <p class="login-subtitle">Por favor inicia sesión con tu cuenta</p>
        
        <!-- Formulario -->
        <form method="POST" id="frmlogin" class="space-y-6">
            <!-- Campo Usuario -->
            <div class="input-group">
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                </div>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    placeholder="Usuario"
                    required
                    class="login-input"
                    value="harvey001">
            </div>
            
            <!-- Campo Contraseña -->
            <div class="input-group">
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Contraseña"
                    required
                    class="login-input password-input">
                <button
                    type="button"
                    id="togglePasswordindex"
                    class="toggle-password">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
            
            <!-- Campos ocultos -->
            <input type="hidden" name="condi" value="acceso">
            <?php echo $csrf->getTokenField(); ?>
            
            <!-- Botón de login -->
            <button type="submit" id="btnEnviar" class="login-button">
                INICIAR SESIÓN
            </button>
        </form>
        
        <!-- Sección inferior con información de la marca -->
        <div class="footer-section">
            <img 
                src="<?= $logoLogin ?? 'https://imagen.wavdevelop.com/ico.avif' ?>" 
                alt="<?= $nameSystem ?? 'Microsystem' ?>" 
                class="brand-logo"
                oncontextmenu="return false;">
            <div class="brand-name"><?= $nameSystem ?? 'Microsystem' ?></div>
            <div class="brand-tagline">Digital Solutions</div>
        </div>
    </div>

    <!-- Loader -->
    <div class="loader-container loading--hide">
        <div class="loader"></div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>/public/assets/mane/log.js"></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>/public/assets/mane/all.min.js"></script>
    
    <script>
        // Bloquear reCAPTCHA
        window.grecaptcha = {
            ready: function(callback) {
                if (callback) {
                    setTimeout(function() {
                        callback();
                    }, 100);
                }
            },
            execute: function(siteKey, options) {
                return Promise.resolve('');
            },
            render: function() {
                return '';
            }
        };
        
        // Deshabilitar Service Workers
        document.addEventListener('DOMContentLoaded', function() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    registrations.forEach(function(registration) {
                        registration.unregister();
                    });
                });
                
                if ('caches' in window) {
                    caches.keys().then(function(cacheNames) {
                        return Promise.all(
                            cacheNames.map(function(cacheName) {
                                return caches.delete(cacheName);
                            })
                        );
                    });
                }
            }
            
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePasswordindex');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (togglePassword && passwordInput && eyeIcon) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    if (type === 'text') {
                        eyeIcon.classList.remove('fa-eye');
                        eyeIcon.classList.add('fa-eye-slash');
                    } else {
                        eyeIcon.classList.remove('fa-eye-slash');
                        eyeIcon.classList.add('fa-eye');
                    }
                });
            }
            
            // Form submission animation
            const form = document.getElementById('frmlogin');
            const submitBtn = document.getElementById('btnEnviar');
            const loader = document.querySelector('.loader-container');
            
            if (form && submitBtn && loader) {
                form.addEventListener('submit', function(e) {
                    // Mostrar loader
                    loader.classList.remove('loading--hide');
                    loader.classList.add('active');
                    
                    // Cambiar texto del botón
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESANDO...';
                    submitBtn.disabled = true;
                    
                    // Restaurar después de 3 segundos (solo para demo)
                    setTimeout(function() {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        loader.classList.add('loading--hide');
                        loader.classList.remove('active');
                    }, 3000);
                });
            }
        });
    </script>
</body>
</html>
<?php
}