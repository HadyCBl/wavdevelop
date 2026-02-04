<?php

namespace App\Utils;

use Micro\Helpers\Log;
use Exception;

class DeploymentManager
{
    private string $projectRoot;
    private string $composerBinary;
    private string $composerHashFile;
    private ?string $lastKnownCommit = null;
    private ?string $composerPhpPrefix = null;

    public function __construct(string $projectRoot = null, ?string $composerPhpPrefix = null)
    {
        $this->projectRoot = $projectRoot ?? PROJECT_ROOT;
        $this->composerBinary = $this->findComposerBinary();
        $this->composerHashFile = $this->projectRoot . '/.composer.installed';
        $this->composerPhpPrefix = $composerPhpPrefix ?? (getenv('COMPOSER_PHP_PREFIX') ?: null);
    }

    /**
     * Encuentra el binario de Composer disponible
     */
    private function findComposerBinary(): string
    {
        $possiblePaths = [
            '/opt/cpanel/composer/bin/composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            'composer.phar',
            'composer'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) || $this->commandExists($path)) {
                return $path;
            }
        }

        throw new Exception('Composer binary not found');
    }

    /**
     * Verifica si un comando existe en el sistema
     */
    private function commandExists(string $command): bool
    {
        if (function_exists('shell_exec')) {
            $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
            return !empty($return);
        }
        return false;
    }

    /**
     * ==========================================
     * NUEVA: Ejecuta comandos con múltiples métodos de fallback
     * ==========================================
     */
    private function executeCommand(string $command): array
    {
        $homeDir = getenv('HOME') ?: '/home/' . get_current_user();
        $composerHome = $homeDir . '/.composer';
        
        // Crear directorio .composer si no existe
        if (!is_dir($composerHome)) {
            @mkdir($composerHome, 0755, true);
        }

        $env = [
            'HOME' => $homeDir,
            'COMPOSER_HOME' => $composerHome,
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'
        ];
        
        // Método 1: proc_open (más robusto)
        if (function_exists('proc_open')) {
            $descriptorspec = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            
            $process = proc_open($command, $descriptorspec, $pipes, $this->projectRoot, $env);
            
            if (is_resource($process)) {
                fclose($pipes[0]);
                
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
        
        // Método 2: shell_exec
        if (function_exists('shell_exec')) {
            $fullCommand = sprintf(
                'cd %s && HOME=%s COMPOSER_HOME=%s %s 2>&1',
                escapeshellarg($this->projectRoot),
                escapeshellarg($homeDir),
                escapeshellarg($composerHome),
                $command
            );
            
            $output = shell_exec($fullCommand);
            $lines = $output ? explode("\n", trim($output)) : [];
            $returnCode = (stripos($output, 'error') !== false || stripos($output, 'fatal') !== false) ? 1 : 0;
            
            return [
                'output' => $lines,
                'return_code' => $returnCode,
                'method' => 'shell_exec'
            ];
        }
        
        // Método 3: exec
        if (function_exists('exec')) {
            $fullCommand = sprintf(
                'cd %s && HOME=%s COMPOSER_HOME=%s %s 2>&1',
                escapeshellarg($this->projectRoot),
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
        
        // Método 4: passthru
        if (function_exists('passthru')) {
            $fullCommand = sprintf(
                'cd %s && HOME=%s COMPOSER_HOME=%s %s 2>&1',
                escapeshellarg($this->projectRoot),
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
        
        // Ninguna función disponible
        throw new Exception('No execution functions available (exec, shell_exec, proc_open, passthru are disabled)');
    }

    /**
     * Guarda el commit actual antes del pull para comparación posterior
     */
    public function saveCurrentCommit(): void
    {
        try {
            if (function_exists('shell_exec')) {
                $this->lastKnownCommit = trim(shell_exec("cd " . escapeshellarg($this->projectRoot) . " && git rev-parse HEAD"));
            }
        } catch (Exception $e) {
            Log::error("Error saving current commit: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el hash MD5 del archivo composer.lock
     */
    private function getComposerLockHash(): ?string
    {
        $composerLock = $this->projectRoot . '/composer.lock';

        if (!file_exists($composerLock)) {
            Log::warning("composer.lock not found");
            return null;
        }

        return md5_file($composerLock);
    }

    /**
     * Obtiene el hash guardado de la última instalación
     */
    private function getSavedComposerHash(): ?string
    {
        if (!file_exists($this->composerHashFile)) {
            Log::info("No previous composer installation record found");
            return null;
        }

        $content = file_get_contents($this->composerHashFile);
        return trim($content) ?: null;
    }

    /**
     * Guarda el hash actual del composer.lock
     */
    private function saveComposerHash(string $hash): bool
    {
        try {
            $result = file_put_contents($this->composerHashFile, $hash);
            if ($result === false) {
                Log::error("Failed to write composer hash file");
                return false;
            }
            Log::info("Composer hash saved: {$hash}");
            return true;
        } catch (Exception $e) {
            Log::error("Error saving composer hash: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si composer.lock ha cambiado desde la última instalación
     */
    public function hasComposerChanges(): bool
    {
        $composerLock = $this->projectRoot . '/composer.lock';
        $composerJson = $this->projectRoot . '/composer.json';

        if (!file_exists($composerLock) || !file_exists($composerJson)) {
            Log::info("composer.lock or composer.json not found, skipping composer check");
            return false;
        }

        try {
            $currentHash = $this->getComposerLockHash();
            $savedHash = $this->getSavedComposerHash();

            if ($savedHash === null) {
                Log::info("No previous installation found - composer install required");
                return true;
            }

            if ($currentHash === null) {
                Log::error("Could not read current composer.lock");
                return true;
            }

            if ($currentHash !== $savedHash) {
                Log::info("Composer.lock has changed (hash mismatch)");
                Log::info("Saved hash: {$savedHash}");
                Log::info("Current hash: {$currentHash}");
                return true;
            }

            Log::info("No composer changes detected (hashes match)");
            return false;
        } catch (Exception $e) {
            Log::error("Error checking composer changes: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Construye el ejecutable de composer
     */
    private function getComposerCommand(): string
    {
        $binary = $this->composerBinary;
        if ($this->composerPhpPrefix && trim($this->composerPhpPrefix) !== '') {
            return trim($this->composerPhpPrefix . ' ' . $binary);
        }
        return $binary;
    }

    /**
     * Ejecuta composer install/update según sea necesario
     */
    public function runComposerInstall(bool $isProduction = true): array
    {
        $results = [];

        try {
            $composerCmd = $this->getComposerCommand();
            
            if ($isProduction) {
                $command = "$composerCmd install --no-dev --optimize-autoloader --prefer-dist --no-interaction";
            } else {
                $command = "$composerCmd install --optimize-autoloader --prefer-dist --no-interaction";
            }

            // Usar el nuevo método executeCommand con fallback
            $execResult = $this->executeCommand($command);

            $results['command'] = $command;
            $results['output'] = $execResult['output'];
            $results['return_code'] = $execResult['return_code'];
            $results['success'] = $execResult['return_code'] === 0;
            $results['execution_method'] = $execResult['method']; // Nuevo: para debugging

            if ($execResult['return_code'] === 0) {
                Log::info("Composer install completed successfully using: " . $execResult['method']);

                $newHash = $this->getComposerLockHash();
                if ($newHash) {
                    $this->saveComposerHash($newHash);
                    $results['hash_saved'] = true;
                } else {
                    $results['hash_saved'] = false;
                    Log::warning("Could not save composer hash after installation");
                }
            } else {
                Log::error("Composer install failed with return code: " . $execResult['return_code']);
                Log::error("Output: " . implode("\n", $execResult['output']));
            }
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            Log::error("Exception during composer install: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Ejecuta composer dump-autoload para optimizar el autoloader
     */
    public function optimizeAutoloader(): array
    {
        $results = [];

        try {
            $command = $this->getComposerCommand() . " dump-autoload --optimize --no-interaction";

            // Usar el nuevo método executeCommand con fallback
            $execResult = $this->executeCommand($command);

            $results['command'] = $command;
            $results['output'] = $execResult['output'];
            $results['return_code'] = $execResult['return_code'];
            $results['success'] = $execResult['return_code'] === 0;
            $results['execution_method'] = $execResult['method'];

            if ($execResult['return_code'] === 0) {
                Log::info("Autoloader optimization completed using: " . $execResult['method']);
            }
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            Log::error("Exception during autoloader optimization: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Ejecuta el proceso completo de deployment
     */
    public function deploy(bool $forceComposer = false): array
    {
        $deployResults = [
            'composer_changes' => false,
            'composer_install' => null,
            'autoloader_optimize' => null,
            'deployment_time' => date('Y-m-d H:i:s'),
            'success' => true,
            'check_method' => 'hash_comparison'
        ];

        try {
            $hasChanges = $this->hasComposerChanges();
            $deployResults['composer_changes'] = $hasChanges;

            if ($hasChanges || $forceComposer) {
                $composerResults = $this->runComposerInstall(true);
                $deployResults['composer_install'] = $composerResults;

                if (!$composerResults['success']) {
                    $deployResults['success'] = false;
                    return $deployResults;
                }

                $autoloaderResults = $this->optimizeAutoloader();
                $deployResults['autoloader_optimize'] = $autoloaderResults;

                if (!$autoloaderResults['success']) {
                    $deployResults['success'] = false;
                }
            }
        } catch (Exception $e) {
            $deployResults['success'] = false;
            $deployResults['error'] = $e->getMessage();
            Log::error("Deployment failed: " . $e->getMessage());
        }

        return $deployResults;
    }
}
