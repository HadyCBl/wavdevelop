<?php

namespace App\Utils;

use Micro\Helpers\Log;
use Exception;

class HtaccessManager
{
    private string $projectRoot;
    private string $htaccessFile;
    private string $htaccessExampleFile;
    private string $htaccessHashFile;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
        $this->htaccessFile = $projectRoot . '/.htaccess';
        $this->htaccessExampleFile = $projectRoot . '/.htaccess.example';
        $this->htaccessHashFile = $projectRoot . '/.htaccess.synced';
    }

    /**
     * Obtiene el hash MD5 del archivo .htaccess.example
     */
    private function getExampleHash(): ?string
    {
        if (!file_exists($this->htaccessExampleFile)) {
            Log::warning(".htaccess.example not found");
            return null;
        }

        return md5_file($this->htaccessExampleFile);
    }

    /**
     * Obtiene el hash guardado de la última sincronización
     */
    private function getSavedHash(): ?string
    {
        if (!file_exists($this->htaccessHashFile)) {
            return null;
        }

        $content = file_get_contents($this->htaccessHashFile);
        return trim($content) ?: null;
    }

    /**
     * Guarda el hash actual del .htaccess.example
     */
    private function saveHash(string $hash): bool
    {
        try {
            $result = file_put_contents($this->htaccessHashFile, $hash);
            if ($result === false) {
                Log::error("Failed to write .htaccess hash file");
                return false;
            }
            Log::info(".htaccess hash saved: {$hash}");
            return true;
        } catch (Exception $e) {
            Log::error("Error saving .htaccess hash: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si .htaccess.example ha cambiado
     */
    public function hasExampleChanged(): bool
    {
        if (!file_exists($this->htaccessExampleFile)) {
            Log::info(".htaccess.example not found, skipping sync");
            return false;
        }

        $currentHash = $this->getExampleHash();
        $savedHash = $this->getSavedHash();

        if ($savedHash === null) {
            Log::info("No previous .htaccess sync found");
            return true;
        }

        if ($currentHash === null) {
            Log::error("Could not read .htaccess.example");
            return false;
        }

        if ($currentHash !== $savedHash) {
            Log::info(".htaccess.example has changed (hash mismatch)");
            return true;
        }

        Log::info("No .htaccess.example changes detected");
        return false;
    }

    /**
     * Sincroniza .htaccess con .htaccess.example
     */
    public function syncHtaccess(): array
    {
        $results = [
            'success' => false,
            'action' => null,
            'message' => null
        ];

        try {
            // Verificar que existe .htaccess.example
            if (!file_exists($this->htaccessExampleFile)) {
                $results['message'] = '.htaccess.example not found';
                Log::warning($results['message']);
                return $results;
            }

            // Leer contenido del example
            $exampleContent = file_get_contents($this->htaccessExampleFile);
            if ($exampleContent === false) {
                $results['message'] = 'Could not read .htaccess.example';
                Log::error($results['message']);
                return $results;
            }

            // Determinar si es creación o actualización
            $htaccessExists = file_exists($this->htaccessFile);
            $results['action'] = $htaccessExists ? 'updated' : 'created';

            // Copiar contenido
            $writeResult = file_put_contents($this->htaccessFile, $exampleContent);
            if ($writeResult === false) {
                $results['message'] = 'Could not write to .htaccess';
                Log::error($results['message']);
                return $results;
            }

            // Guardar hash
            $newHash = $this->getExampleHash();
            if ($newHash) {
                $this->saveHash($newHash);
            }

            $results['success'] = true;
            $results['message'] = ".htaccess {$results['action']} successfully";
            Log::info($results['message']);

        } catch (Exception $e) {
            $results['message'] = 'Exception: ' . $e->getMessage();
            Log::error("Error syncing .htaccess: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Verifica y sincroniza si es necesario
     */
    public function checkAndSync(): array
    {
        $results = [
            'checked' => true,
            'needs_sync' => false,
            'sync_result' => null
        ];

        // Caso 1: .htaccess no existe
        if (!file_exists($this->htaccessFile)) {
            Log::info(".htaccess does not exist, needs creation");
            $results['needs_sync'] = true;
            $results['sync_result'] = $this->syncHtaccess();
            return $results;
        }

        // Caso 2: .htaccess.example cambió
        if ($this->hasExampleChanged()) {
            Log::info(".htaccess.example changed, needs update");
            $results['needs_sync'] = true;
            $results['sync_result'] = $this->syncHtaccess();
            return $results;
        }

        // Caso 3: Todo está sincronizado
        $results['needs_sync'] = false;
        $results['message'] = '.htaccess is up to date';
        Log::info($results['message']);

        return $results;
    }
}
