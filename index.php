<?php
use App\DatabaseAdapter;

include 'includes/Config/config.php';
session_start();

//manejo de  errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $errorMessage = "[PHP Error] $errstr en $errfile línea $errline";
    // echo "<script>console.error(" . json_encode($errorMessage) . ");</script>";
    error_log($errorMessage, 3, __DIR__ . '/logs/php_errors.log');
});

set_exception_handler(function ($exception) {
    $errorMessage = "[PHP Exception] {$exception->getMessage()} en {$exception->getFile()} línea {$exception->getLine()}";
    // echo "<script>console.error(" . json_encode($errorMessage) . ");</script>";
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
            // Simular token vacío después de 100ms
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
        // Desregistrar todos los Service Workers
        navigator.serviceWorker.getRegistrations().then(function(registrations) {
            registrations.forEach(function(registration) {
                registration.unregister().then(function(success) {
                    console.log('Service Worker desregistrado:', success);
                });
            });
        });
        
        // Limpiar cache
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
        <title><?= $nameSystem ?? 'Microsystem' ?></title>
        <link rel="shortcut icon" type="image/x-icon" href="includes/img/favmicro.ico">
        <link rel="stylesheet" href="includes/css/login.css">
        <link rel="stylesheet" href="includes/css/login-custom.css">
        <link rel="manifest" href="/manifest.json">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="includes/css/estiloslog.css">
        <style>

        </style>
    </head>

    <body>
        <div class="login-container">
            <!-- Formulario de inicio de sesión -->
            <div class="login-form-section">
                <h3 class="login-title">Iniciar Sesión</h3>

                <div class="logo-container">
                    <img
                        src="includes/img/fondologo.png"
                        alt="Logo Microsystem"
                        class="logo-img">
                </div>

                <p class="login-subtitle">
                    Por favor inicia sesión con tu cuenta
                </p>

                <form method="POST" id="frmlogin" class="login-form">
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
                            class="login-input">
                    </div>
                    <div class="input-group password-group">
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

                    <input name="condi" value="acceso" class="hidden-input">
                    <?php echo $csrf->getTokenField(); ?>

                    <button type="submit" id="btnEnviar" class="login-button">
                        INICIAR SESIÓN
                    </button>
                </form>
            </div>
            <!--  Informativa -->
            <div class="info-section">

                <img
                    src="<?= $logoLogin ?? "https://imagen.wavdevelop.com/ico.avif" ?>"
                    alt="Microsystem+ Logo"
                    class="info-logo"
                    oncontextmenu="return false;">
                <h1 class="info-title cursiva-font" id="microsystem-title">
                    <span id="plus-sign" class="info-plus-sign"><?= $nameSystem ?? 'Microsystem' ?></span>
                </h1>

                <p class="info-subtitle">Digital Solutions</p>
            </div>
        </div>

        <div class="loader-container loading--hide">
            <div class="loader"></div>
            <div class="loaderimg"></div>
            <div class="loader2"></div>
        </div>

      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous">
</script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>/public/assets/mane/log.js"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>/public/assets/mane/all.min.js"></script>


       

    </body>

    </html>
<?php
}
