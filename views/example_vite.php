<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejemplo Vite</title>
    
    <?php
    // Incluir autoload o bootstrap de tu app
    // require_once __DIR__ . '/../../app/bootstrap.php';
    include_once __DIR__ . '/../includes/Config/config.php';
    
    use Micro\Generic\AssetVite;
    
    // // Configuración
    // // $isProduction = false; // Cambiar a true en producción
    // AssetVite::setEnvironment($isProduction);
    // AssetVite::setHostUrl($host);
    
    // IMPORTANTE: Habilitar modo desarrollo para HMR
    // if (!$isProduction) {
    //     AssetVite::enableDevMode(true, 'http://localhost:5173');
    // }
    
    // Registrar dependencias del bundle
    AssetVite::registerBundleDependencies('example', ['alpine']);
    
    // Cargar estilos
    echo AssetVite::style('example');
    ?>
</head>
<body>
    <div id="app" x-data="{ message: 'Hola desde Vite + Alpine!' }">
        <h1 x-text="message"></h1>
        <button @click="message = 'Cambio con HMR funcionando!'">
            Cambiar mensaje
        </button>
    </div>
    
    <?php
    // Cargar scripts
    echo AssetVite::script('example', [
        'defer' => true,
        'type' => 'module'
    ]);
    
    // Debug info (solo en desarrollo)
    if (!$isProduction) {
        echo AssetVite::debug();
    }
    ?>
</body>
</html>
