<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // Asegúrate de tener instalado firebase/php-jwt
require_once __DIR__ . '/../funcphp/func_gen.php'; // Asegúrate de tener instalado vlucas/phpdotenv
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CzProject\GitPhp\Git;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Content-Type: application/json');

// Configuración
define('JWT_SECRET', $_ENV['JWT_SECRET']); // Debe ser única por cliente
define('JWT_ALGO', 'HS256');
define('DB_CONFIG_FILE', '../config/database.php');
// define('PROJECT_ROOT', $_ENV['HOST']);
define('PROJECT_ROOT', __DIR__ . '/../../');

date_default_timezone_set('America/Guatemala');
class ApiAuth
{
    private static function getAuthHeader()
    {
        $logFile = __DIR__ . '/debug.log';

        file_put_contents($logFile, "\n\n--- NUEVO REQUEST " . date('Y-m-d H:i:s') . " ---\n", FILE_APPEND);

        $headers = getallheaders();
        file_put_contents($logFile, "getallheaders(): " . print_r($headers, true) . "\n", FILE_APPEND);

        if (!isset($headers['Authorization'])) {
            return null;
        }
        return str_replace('Bearer ', '', $headers['Authorization']);
    }

    public static function validateToken()
    {
        try {
            $token = self::getAuthHeader();
            if (!$token) {
                throw new Exception('No token provided' . $token);
            }
            JWT::$timestamp = time(); // Establecer el tiempo actual
            JWT::$leeway = 30; // Añadir una tolerancia de 30 segundos
            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));

            // Validar expiración
            if ($decoded->exp < time()) {
                throw new Exception('Token expired');
            }

            // Validar origen permitido
            if (!isset($decoded->iss) || $decoded->iss !== 'central_system') {
                throw new Exception('Invalid token issuer');
            }

            return true;
        } catch (Exception $e) {
            http_response_code(401);
            die(json_encode([
                'error' => 'Unauthorized',
                'message' => $e->getMessage()
            ]));
        }
    }

    public static function generateToken()
    {
        $payload = [
            'iss' => 'client_system',
            'aud' => 'central_system',
            'iat' => time(),
            'exp' => time() + (60 * 60), // 1 hora
            'client_id' => $_ENV['ID_CLIENTE'] // Identificador único del cliente
        ];

        return JWT::encode($payload, JWT_SECRET, JWT_ALGO);
    }
}

// [Las funciones anteriores se mantienen igual: getGitInfo(), checkDatabase(), etc.]
// Obtener información de Git

function getGitInfo() {
    $logFile = __DIR__ . '/debug.log';
    
    try {
        // Configurar el binario de Git específicamente para cPanel
        // $gitBinary = '/usr/local/cpanel/3rdparty/lib/path-bin/git';
        $gitBinary = $_ENV['GIT_BINARY'] ?? '/usr/local/cpanel/3rdparty/lib/path-bin/git';
        
        // Verificar si el binario existe
        if (!file_exists($gitBinary)) {
            throw new Exception('Git binary not found at: ' . $gitBinary);
        }
        
        // Crear instancia de Git con el binario específico
        $git = new Git(new \CzProject\GitPhp\Runners\CliRunner($gitBinary));
        
        file_put_contents($logFile, "Using git binary: {$gitBinary}\n", FILE_APPEND);
        
        $repo = $git->open(PROJECT_ROOT);
        
        // Obtener información del repositorio
        $branch = $repo->getCurrentBranchName();
        $commitId = $repo->getLastCommitId();
        $commit = $repo->getCommit($commitId);
        
        return [
            'branch' => $branch,
            'last_commit' => $commit->getId()->__toString(),
            'last_commit_date' => $commit->getCommitterDate()->format('Y-m-d H:i:s'),
            'message_commit' => $commit->getSubject(),
            // 'git_binary' => $gitBinary,
            // 'git_version' => trim(shell_exec($gitBinary . ' --version')),
            'method' => 'git-php'
        ];

    } catch (Exception $e) {
        file_put_contents($logFile, "Error en getGitInfo: " . $e->getMessage() . "\n", FILE_APPEND);
        return [
            'branch' => 'unknown',
            'last_commit' => 'unknown',
            'last_commit_date' => null,
            'message_commit' => 'Error: ' . $e->getMessage(),
            'error_details' => $e->getMessage()
        ];
    }
}
// Verificar estado de la base de datos
function checkDatabase()
{
    try {
        if (file_exists(DB_CONFIG_FILE)) {
            include DB_CONFIG_FILE;

            if (isset($db_config)) {
                $conn = new PDO(
                    "mysql:host={$db_config['host']};dbname={$db_config['database']}",
                    $db_config['username'],
                    $db_config['password']
                );

                // Verificar la última actualización de una tabla importante
                $stmt = $conn->query("SHOW TABLE STATUS");
                $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'status' => 'connected',
                    'database_name' => $db_config['database'],
                    'tables_count' => count($tables),
                    'last_update' => array_reduce($tables, function ($latest, $table) {
                        $update = strtotime($table['Update_time'] ?? 0);
                        return $update > $latest ? $update : $latest;
                    }, 0)
                ];
            }
        }
        return ['status' => 'config_not_found'];
    } catch (PDOException $e) {
        return [
            'status' => 'error',
            'message' => 'Database connection failed'
        ];
    }
}

// Obtener información del sistema
function getSystemInfo()
{
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'server_os' => PHP_OS,
        'server_time' => date('Y-m-d H:i:s'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'disk_free_space' => disk_free_space('/'),
        'disk_total_space' => disk_total_space('/')
    ];
}

// Verificar estado de archivos importantes
function checkCriticalFiles()
{
    $criticalFiles = [
        // '/index.php',
        'includes/config/database.php',
        // Añade aquí otros archivos críticos de tu aplicación
    ];

    $filesStatus = [];
    foreach ($criticalFiles as $file) {
        $fullPath = PROJECT_ROOT . $file;
        $filesStatus[$file] = [
            'exists' => file_exists($fullPath),
            'size' => file_exists($fullPath) ? filesize($fullPath) : 0,
            'last_modified' => file_exists($fullPath) ? date('Y-m-d H:i:s', filemtime($fullPath)) : null
        ];
    }

    return $filesStatus;
}


// Validar el token antes de procesar
ApiAuth::validateToken();

// Preparar la respuesta
$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'domain' => $_SERVER['HTTP_HOST'],
    'git_info' => getGitInfo(),
    'database' => checkDatabase(),
    'system_info' => getSystemInfo(),
    'critical_files' => checkCriticalFiles()
];
$logFile = __DIR__ . '/debug.log';
file_put_contents($logFile, "Response: " . print_r($response, true) . "\n", FILE_APPEND);


// Generar un nuevo token para la siguiente solicitud
$response['next_token'] = ApiAuth::generateToken();

echo json_encode($response, JSON_PRETTY_PRINT);
