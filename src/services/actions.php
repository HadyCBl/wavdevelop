<?php
// require_once __DIR__ . '/../../vendor/autoload.php'; // Para JWT
require_once __DIR__ . '/../../app/bootstrap.php';

use Micro\Helpers\Log;
use App\Utils\DeploymentManager;
use App\Utils\HtaccessManager;
use Micro\Services\TelegramService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use CzProject\GitPhp\Git;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

header('Content-Type: application/json');

define('JWT_SECRET', $_ENV['JWT_SECRET']);
define('JWT_ALGO', 'HS256');
define('PROJECT_ROOT', __DIR__ . '/../../');

date_default_timezone_set('America/Guatemala');

// Inicializar servicio de Telegram
$telegram = new TelegramService();


class ApiAuth
{
    private static function getAuthHeader()
    {
        $headers = getallheaders();
        return isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    }

    public static function validateToken()
    {
        try {
            $token = self::getAuthHeader();
            if (!$token) throw new Exception('No token provided');

            JWT::$timestamp = time(); // Establecer el tiempo actual
            JWT::$leeway = 30; // Añadir una tolerancia de 30 segundos
            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));

            if ($decoded->exp < time()) throw new Exception('Token expired');
            if (!isset($decoded->iss) || $decoded->iss !== 'central_system') throw new Exception('Invalid token issuer');

            return true;
        } catch (Exception $e) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]));
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

    public static function validateRequest()
    {
        // 1. Primero verificar si es un webhook de GitLab
        if (self::isGitLabWebhook()) {
            return true;
        }

        // 2. Si no es GitLab, validar JWT ( flujo normal)
        return self::validateToken();
    }

    private static function isGitLabWebhook()
    {
        // Solo validar GitLab si hay token configurado
        if (empty(JWT_SECRET)) return false;

        // Verificar headers específicos de GitLab
        $event = $_SERVER['HTTP_X_GITLAB_EVENT'] ?? null;
        $token = $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? null;

        // Si no tiene headers de GitLab, no es un webhook
        if (!$event || !$token) return false;

        // Validar el token del webhook
        if ($token !== JWT_SECRET) {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden', 'message' => 'Invalid GitLab token']));
        }

        // Opcional: Validar IPs de GitLab para mayor seguridad
        // $gitlabIps = ['172.16.0.0/12', '...'];
        // if (!in_array($_SERVER['REMOTE_ADDR'], $gitlabIps)) { ... }

        return true;
    }
}

// Validar token antes de ejecutar comandos
// ApiAuth::validateToken();
ApiAuth::validateRequest();

// Recibir datos del request
$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? ($_SERVER['HTTP_X_GITLAB_EVENT'] ? 'pull' : null);
$commitId = $input['commit_id'] ?? null;

$isGitLabWebhook = false;
if (isset($_SERVER['HTTP_X_GITLAB_EVENT'])) {
    $action = 'pull'; // Siempre ejecuta pull si es un webhook de GitLab
    $isGitLabWebhook = true;
    
    // RESPUESTA INMEDIATA PARA GITLAB (antes de los 10 segundos)
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'accepted',
        'message' => 'Webhook received, processing in background',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
    // Forzar el envío del buffer y cerrar la conexión con GitLab
    if (ob_get_level()) {
        ob_end_flush();
    }
    flush();
    
    // Cerrar la conexión con FastCGI si está disponible
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Ahora continuar con el procesamiento sin que GitLab espere
    // Log para confirmar que la respuesta fue enviada
    Log::info("GitLab webhook response sent immediately. Continuing background processing...");
}

// Inicializar el repositorio de Git
// $git = new Git;
$gitBinary = $_ENV['GIT_BINARY'] ?? '/usr/local/cpanel/3rdparty/lib/path-bin/git';

if (!file_exists($gitBinary)) {
    // Devolver error JSON en lugar de lanzar excepción directamente aquí para consistencia
    http_response_code(500);
    echo json_encode([
        'error' => 'Configuration Error',
        'message' => 'Git binary not found at: ' . $gitBinary
    ], JSON_PRETTY_PRINT);
    exit;
}

// Crear instancia de Git con el binario específico
$git = new Git(new \CzProject\GitPhp\Runners\CliRunner($gitBinary));
$repo = $git->open(PROJECT_ROOT);
// $repo = new GitRepository(PROJECT_ROOT);
$output = ['action' => $action];
$currentBranch = $repo->getCurrentBranchName();

try {
    switch ($action) {
        case 'pull':
            /**
             * NUEVO: Manejo de Deployment Automático
             * guardar el commit actual antes del pull
             */
            // Pasar el prefijo configurado en .env (COMPOSER_PHP_PREFIX)
            $deploymentManager = new DeploymentManager(PROJECT_ROOT, $_ENV['COMPOSER_PHP_PREFIX'] ?? getenv('COMPOSER_PHP_PREFIX') ?? null);

            // CAMBIO DE REPOSITORIO REMOTO (ejecutar una sola vez antes de fin de año)
            try {
                $newRemoteUrl = "https://oauth2:glpat-P3wboseoRQVgMk_6qfnVe286MQp1OjhoYjY5Cw.01.1217gnble@gitlab.com/sotecpro/microsystemplus.git";
                if ($newRemoteUrl) {
                    $currentRemoteUrl = trim($repo->execute('remote', 'get-url', 'origin')[0] ?? '');
                    
                    if ($currentRemoteUrl !== $newRemoteUrl) {
                        $repo->execute('remote', 'set-url', 'origin', $newRemoteUrl);
                        Log::info("Remote URL changed from {$currentRemoteUrl} to {$newRemoteUrl}");
                        
                        $telegram->sendMarkdown(
                            "*🔄 Repositorio Remoto Actualizado*\n\n" .
                            "*Dominio:* `" . ($_SERVER['HTTP_HOST'] ?? php_uname('n')) . "`\n" .
                            "*URL Anterior:* `{$currentRemoteUrl}`\n" .
                            "*URL Nueva:* `{$newRemoteUrl}`\n" .
                            "*Fecha:* `" . date('Y-m-d H:i:s') . "`"
                        );
                        
                        $output['remote_changed'] = [
                            'from' => $currentRemoteUrl,
                            'to' => $newRemoteUrl,
                            'timestamp' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            } catch (Exception $e) {
                Log::error("Remote URL change failed: " . $e->getMessage());
                // No crítico, continuar con el pull
            }

            // NUEVO: Sincronización forzada con remoto (fetch + reset --hard)
            // Esto descarta TODOS los cambios locales y sincroniza exactamente con origin
            try {
                // 1. Fetch desde origin para la rama actual
                $output['fetch'] = $repo->fetch(['origin', $currentBranch]);
                
                // 2. Reset hard a origin/rama (descarta todo local)
                $output['reset'] = $repo->execute('reset', '--hard', 'origin/' . $currentBranch);
                
                $output['sync'] = 'Repository synchronized with origin/' . $currentBranch;
            } catch (Exception $e) {
                Log::error("Fetch/Reset failed: " . $e->getMessage());
                throw new Exception("Failed to synchronize with remote: " . $e->getMessage());
            }

            // NUEVO: Verificar y sincronizar .htaccess
            try {
                $htaccessManager = new HtaccessManager(PROJECT_ROOT);
                $htaccessResults = $htaccessManager->checkAndSync();
                $output['htaccess'] = $htaccessResults;
            } catch (Exception $e) {
                Log::error("Htaccess sync error (non-critical): " . $e->getMessage());
                $output['htaccess'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'critical' => false
                ];
            }

            // NUEVO: Ejecutar deployment después del pull - SEPARADO EN TRY-CATCH
            $deploymentMessage = "";
            $deploymentResults = [];

            try {
                $deploymentResults = $deploymentManager->deploy();
                $output['deployment'] = $deploymentResults;

                // Mensaje adicional para Telegram si hubo deployment
                if (isset($deploymentResults['composer_changes']) && $deploymentResults['composer_changes']) {
                    $composerStatus = $deploymentResults['composer_install']['success'] ? "✅ Exitoso" : "❌ Falló";
                    $deploymentMessage = "\n\n*📦 Deployment Ejecutado:*\n" .
                        "Composer Install: `{$composerStatus}`\n" .
                        "Método de verificación: `{$deploymentResults['check_method']}`\n";

                    if (!$deploymentResults['success']) {
                        $deploymentMessage .= "❌ *Error en deployment*";
                    }
                }
            } catch (Exception $e) {
                // ❗ ERROR CRÍTICO: Pero no afecta el pull principal
                $output['deployment'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'critical' => false
                ];
                $deploymentMessage = "\n\n⚠️ *Deployment con errores (no crítico):* `" . addslashes($e->getMessage()) . "`";
                Log::error("Deployment error (non-critical): " . $e->getMessage());

                $telegram->sendError(
                    "Deployment Error (Non-Critical): " . $e->getMessage(),
                    [
                        'domain' => $_SERVER['HTTP_HOST'] ?? php_uname('n'),
                        'date' => date('Y-m-d H:i:s')
                    ]
                );
            }

            // Preparar mensaje de .htaccess para Telegram
            $htaccessMessage = "";
            if (isset($output['htaccess']['needs_sync']) && $output['htaccess']['needs_sync']) {
                $htaccessAction = $output['htaccess']['sync_result']['action'] ?? 'unknown';
                $htaccessStatus = $output['htaccess']['sync_result']['success'] ?? false ? "✅" : "❌";
                $htaccessMessage = "\n\n*🔧 .htaccess:*\n" .
                    "Acción: `{$htaccessAction}` {$htaccessStatus}";
            }

            // Enviar notificación de Telegram
            $domain = $_SERVER['HTTP_HOST'] ?? php_uname('n'); // Dominio o hostname
            $dateTime = date('Y-m-d H:i:s');
            $source = $isGitLabWebhook ? "GitLab Webhook" : "API Call";

            $telegramMessage = "*🚀 Sincronización con Remoto 🚀*\n\n" .
                "*Dominio:* `" . $domain . "`\n" .
                "*Rama:* `" . $currentBranch . "`\n" .
                "*Fecha/Hora:* `" . $dateTime . "`\n" .
                "*Origen:* `" . $source . "`\n\n" .
                "*Resultados:*\n" .
                "Fetch: `✅ Exitoso`\n" .
                "Reset Hard: `✅ Exitoso a origin/{$currentBranch}`\n" .
                $htaccessMessage .
                $deploymentMessage;

            $telegram->sendMarkdown($telegramMessage);

            // Ejecutar tasksPending - TAMBIÉN con su propio try-catch
            try {
                $actualizador = new \App\Utils\SystemUpdate();
                $actualizador->tasksPending();
            } catch (\Throwable $e) {
                Log::error("Error en SystemUpdate::tasksPending: " . $e->getMessage());
                $telegram->sendError(
                    "Error en SystemUpdate: " . $e->getMessage(),
                    [
                        'date' => date('Y-m-d H:i:s'),
                        'stack_trace' => $e->getTraceAsString()
                    ]
                );
            }
            
            // Responder SOLO si NO es webhook de GitLab (ya respondimos al inicio)
            if (!$isGitLabWebhook) {
                $output['next_token'] = ApiAuth::generateToken();
                http_response_code(200);
                echo json_encode($output, JSON_PRETTY_PRINT);
            } else {
                // Para webhook de GitLab, solo registrar en log que terminó
                Log::info("GitLab webhook processing completed successfully");
            }
            break;

        case 'reset':
            if (!$commitId) {
                http_response_code(400);
                die(json_encode(['error' => 'Bad Request', 'message' => 'commit_id is required for reset']));
            }

            // Ejecutar reset --hard usando execute()
            $output['reset'] = $repo->execute('reset', '--hard', $commitId);
            break;

        case 'log':
            // Obtener los últimos 10 commits con formato específico
            $logFormat = '%h|%s|%an|%ae|%ad|%cn|%ce|%cd';
            $commits = $repo->execute('log', '--pretty=format:' . $logFormat, '--date=iso', '-n', '10');

            $formattedCommits = [];
            foreach ($commits as $commit) {
                $parts = explode('|', $commit);
                if (count($parts) === 8) {
                    $formattedCommits[] = [
                        'hash' => $parts[0],
                        'message' => $parts[1],
                        'author_name' => $parts[2],
                        'author_email' => $parts[3],
                        'author_date' => $parts[4],
                        'committer_name' => $parts[5],
                        'committer_email' => $parts[6],
                        'commit_date' => $parts[7]
                    ];
                }
            }

            // Obtener la rama actual usando GitPhp
            $branch = $repo->getCurrentBranchName();

            $output = [
                'action' => 'log',
                'data' => [
                    'commits' => $formattedCommits,
                    'branch' => $branch
                ],
            ];
            break;

        case 'deploy':
            // Nueva acción para deployment manual
            try {
                $forceComposer = $input['force_composer'] ?? false;
                $deploymentManager = new DeploymentManager(PROJECT_ROOT, $_ENV['COMPOSER_PHP_PREFIX'] ?? null);
                $deployResults = $deploymentManager->deploy($forceComposer);

                $output = [
                    'action' => 'deploy',
                    'deployment' => $deployResults,
                    'forced' => $forceComposer
                ];

                // Notificación de Telegram para deployment manual
                $status = $deployResults['success'] ? "✅ Exitoso" : "❌ Falló";
                $telegramMessage = "*🔧 Deployment Manual Ejecutado*\n\n" .
                    "*Estado:* `{$status}`\n" .
                    "*Cambios en Composer:* `" . ($deployResults['composer_changes'] ? 'Sí' : 'No') . "`\n" .
                    "*Forzado:* `" . ($forceComposer ? 'Sí' : 'No') . "`\n" .
                    "*Fecha/Hora:* `" . $deployResults['deployment_time'] . "`";

                $telegram->sendMarkdown($telegramMessage);
            } catch (Exception $e) {
                $output = [
                    'action' => 'deploy',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                Log::error("Manual deployment error: " . $e->getMessage());
            }
            break;

        default:
            http_response_code(400);
            die(json_encode(['error' => 'Bad Request', 'message' => 'Invalid action']));
    }

    // Responder SOLO para acciones que NO sean 'pull' (pull ya responde dentro de su case)
    // Y SOLO si NO es un webhook de GitLab
    if (!$isGitLabWebhook && $action !== 'pull') {
        $output['next_token'] = ApiAuth::generateToken();
        http_response_code(200);
        echo json_encode($output, JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = '[ERROR API] ' . $e->getMessage();
    
    // MEJORADO: Agregar más información al error
    Log::error($errorMsg . " | Working directory: " . PROJECT_ROOT);
    
    // Notificar por Telegram si es posible
    $domain = $_SERVER['HTTP_HOST'] ?? php_uname('n'); // Dominio o hostname
    $telegram->sendError(
        "Mixna' Error en actions.php: " . $e->getMessage(),
        [
            'date' => date('Y-m-d H:i:s'),
            'accion' => $action ?? 'N/A',
            'dominio' => $domain,
            'directorio' => PROJECT_ROOT
        ]
    );
    
    // Responder SOLO si NO es webhook de GitLab (GitLab ya recibió respuesta exitosa al inicio)
    if (!$isGitLabWebhook) {
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    } else {
        // Para webhook de GitLab, solo registrar que hubo un error en el procesamiento
        Log::error("GitLab webhook processing failed after sending success response");
    }
}
