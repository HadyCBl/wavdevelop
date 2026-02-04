<?php

/**
 * Bootstrap script que garantiza que vendor/ exista
 */

$projectRoot = __DIR__ . '/..';
$vendorDir = $projectRoot . '/vendor';
$autoloadFile = $vendorDir . '/autoload.php';

// Si vendor existe, no hacer nada
if (file_exists($autoloadFile)) {
    return require_once $autoloadFile;
}

// ==========================================
// Vendor no existe, ejecutar composer
// ==========================================
error_log("[BOOTSTRAP] vendor/ no encontrado, ejecutando composer install...");

// Cargar .env manualmente
$envFile = $projectRoot . '/.env';
$envVars = [];
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    foreach ($envVars as $key => $value) {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

$composerPhpPrefix = $envVars['COMPOSER_PHP_PREFIX'] ?? '';
$composerBinary = null;

// Buscar composer
$possiblePaths = [
    '/opt/cpanel/composer/bin/composer',
    '/usr/local/bin/composer',
    '/usr/bin/composer',
    $projectRoot . '/composer.phar'
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $composerBinary = $path;
        break;
    }
}

if (!$composerBinary) {
    http_response_code(500);
    die(json_encode([
        'error' => 'Composer Not Found',
        'message' => 'vendor/ missing and composer binary not found',
        'searched_paths' => $possiblePaths
    ], JSON_PRETTY_PRINT));
}

// ==========================================
// NUEVO: Establecer variables de entorno necesarias
// ==========================================
$homeDir = getenv('HOME') ?: '/home/' . get_current_user();
$composerHome = $homeDir . '/.composer';

// Crear directorio .composer si no existe
if (!is_dir($composerHome)) {
    @mkdir($composerHome, 0755, true);
    error_log("[BOOTSTRAP] Directorio COMPOSER_HOME creado: {$composerHome}");
}

// ==========================================
// NUEVO: Función para ejecutar comandos con fallback
// ==========================================
function executeCommand($command, $projectRoot, $homeDir, $composerHome) {
    $env = [
        'HOME' => $homeDir,
        'COMPOSER_HOME' => $composerHome,
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'
    ];
    
    error_log("[BOOTSTRAP] Ejecutando: {$command}");
    
    // Método 1: proc_open (más confiable y respeta variables de entorno)
    if (function_exists('proc_open')) {
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        
        $process = proc_open($command, $descriptorspec, $pipes, $projectRoot, $env);
        
        if (is_resource($process)) {
            fclose($pipes[0]); // No necesitamos stdin
            
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $returnCode = proc_close($process);
            
            $output = array_merge(
                explode("\n", trim($stdout)),
                explode("\n", trim($stderr))
            );
            
            return [
                'output' => array_filter($output),
                'return_code' => $returnCode,
                'method' => 'proc_open'
            ];
        }
    }
    
    // Método 2: shell_exec (menos control pero funciona en más servidores)
    if (function_exists('shell_exec')) {
        // Establecer variables de entorno inline
        $fullCommand = sprintf(
            'cd %s && HOME=%s COMPOSER_HOME=%s %s 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($homeDir),
            escapeshellarg($composerHome),
            $command
        );
        
        $output = shell_exec($fullCommand);
        
        // shell_exec no devuelve código de retorno, así que verificamos la salida
        $lines = $output ? explode("\n", trim($output)) : [];
        $returnCode = (stripos($output, 'error') !== false || stripos($output, 'fatal') !== false) ? 1 : 0;
        
        return [
            'output' => $lines,
            'return_code' => $returnCode,
            'method' => 'shell_exec'
        ];
    }
    
    // Método 3: exec (tu método original)
    if (function_exists('exec')) {
        $fullCommand = sprintf(
            'cd %s && HOME=%s COMPOSER_HOME=%s %s 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($homeDir),
            escapeshellarg($composerHome),
            $command
        );
        
        exec($fullCommand, $output, $returnCode);
        
        return [
            'output' => $output,
            'return_code' => $returnCode,
            'method' => 'exec'
        ];
    }
    
    // Método 4: passthru (última opción)
    if (function_exists('passthru')) {
        $fullCommand = sprintf(
            'cd %s && HOME=%s COMPOSER_HOME=%s %s 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($homeDir),
            escapeshellarg($composerHome),
            $command
        );
        
        ob_start();
        passthru($fullCommand, $returnCode);
        $output = ob_get_clean();
        
        return [
            'output' => explode("\n", trim($output)),
            'return_code' => $returnCode,
            'method' => 'passthru'
        ];
    }
    
    // Si ninguna función está disponible
    return [
        'output' => ['ERROR: No hay funciones de ejecución disponibles en este servidor (exec, shell_exec, proc_open, passthru están deshabilitadas)'],
        'return_code' => 1,
        'method' => 'none'
    ];
}

// Construir comando de composer
$composerCmd = trim($composerPhpPrefix) !== ''
    ? "$composerPhpPrefix $composerBinary"
    : $composerBinary;

$command = "$composerCmd install --no-dev --optimize-autoloader --prefer-dist --no-interaction";

// Ejecutar comando con fallback
$result = executeCommand($command, $projectRoot, $homeDir, $composerHome);

if ($result['return_code'] !== 0) {
    http_response_code(500);
    die(json_encode([
        'error' => 'Composer Install Failed',
        'output' => $result['output'],
        'command' => $command,
        'return_code' => $result['return_code'],
        'execution_method' => $result['method'],
        'home_dir' => $homeDir,
        'composer_home' => $composerHome,
        'disabled_functions' => ini_get('disable_functions')
    ], JSON_PRETTY_PRINT));
}

error_log("[BOOTSTRAP] Composer install completado exitosamente usando: " . $result['method']);

// Cargar autoloader recién instalado
return require_once $autoloadFile;
