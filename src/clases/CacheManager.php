<?php

namespace App\Generic;

use Micro\Helpers\Log;

class CacheManager
{
    private bool $enabled = false;
    private string $prefix = '';
    private int $defaultTtl = 3600; // 1 hora por defecto

    public function __construct(string $prefix = 'app_', int $defaultTtl = 3600)
    {
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
        $this->enabled = $this->isApcuAvailable();

        if (!$this->enabled) {
            // Log::warning("APCu no está disponible. El caché estará deshabilitado.");
        }
    }

    /**
     * Verifica si APCu está disponible y habilitado
     */
    private function isApcuAvailable(): bool
    {
        return function_exists('\\apcu_enabled') && function_exists('\\apcu_store') && @\apcu_enabled();
    }

    /**
     * Genera la clave completa con prefijo
     */
    private function getFullKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Almacena un valor en caché
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $fullKey = $this->getFullKey($key);

        try {
            $result = \apcu_store($fullKey, $value, $ttl);
            if ($result) {
                Log::debug("Valor almacenado en caché", ['key' => $key, 'ttl' => $ttl]);
            } else {
                Log::warning("Falló al almacenar en caché", ['key' => $key]);
            }
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al almacenar en caché: " . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * Obtiene un valor del caché
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }

        $fullKey = $this->getFullKey($key);

        // Verifica si la clave existe antes de intentar obtener el valor
        if (!\apcu_exists($fullKey)) {
            Log::debug("Clave no existe en caché", ['key' => $key]);
            return $default;
        }

        try {
            $value = \apcu_fetch($fullKey, $success);
            if ($success && $value !== false) {
                Log::debug("Valor obtenido del caché (HIT)", ['key' => $fullKey, 'value' => $value]);
                return $value;
            } else {
                Log::debug("Valor no encontrado en caché (MISS)", ['key' => $fullKey]);
                return $default;
            }
        } catch (\Exception $e) {
            Log::error("Error al obtener del caché: " . $e->getMessage(), ['key' => $fullKey]);
            return $default;
        }
    }

    /**
     * Verifica si existe una clave en el caché
     */
    public function has(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->getFullKey($key);
        return \apcu_exists($fullKey);
    }

    /**
     * Elimina un valor del caché
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->getFullKey($key);

        try {
            $result = \apcu_delete($fullKey);
            if ($result) {
                Log::debug("Valor eliminado del caché", ['key' => $key]);
            }
            return $result;
        } catch (\Exception $e) {
            Log::error("Error al eliminar del caché: " . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * Limpia todo el caché con el prefijo actual
     */
    public function clear(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $info = \apcu_cache_info();
            $cleared = 0;

            if (isset($info['cache_list'])) {
                foreach ($info['cache_list'] as $entry) {
                    if (strpos($entry['info'], $this->prefix) === 0) {
                        if (\apcu_delete($entry['info'])) {
                            $cleared++;
                        }
                    }
                }
            }

            Log::info("Caché limpiado", ['entries_cleared' => $cleared]);
            return true;
        } catch (\Exception $e) {
            Log::error("Error al limpiar caché: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene o establece un valor (patrón cache-aside)
     */
    public function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * Incrementa un valor numérico en el caché
     */
    public function increment(string $key, int $step = 1): int|false
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->getFullKey($key);

        try {
            return \apcu_inc($fullKey, $step);
        } catch (\Exception $e) {
            Log::error("Error al incrementar en caché: " . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * Decrementa un valor numérico en el caché
     */
    public function decrement(string $key, int $step = 1): int|false
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->getFullKey($key);

        try {
            return \apcu_dec($fullKey, $step);
        } catch (\Exception $e) {
            Log::error("Error al decrementar en caché: " . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * Obtiene estadísticas del caché
     */
    public function getStats(): array
    {
        if (!$this->enabled) {
            return ['enabled' => false];
        }

        try {
            if (!function_exists('\\apcu_cache_info')) {
                return ['enabled' => true, 'error' => 'APCu functions not available'];
            }
            
            $info = \apcu_cache_info();
            $memSize = $info['mem_size'] ?? 0;
            $availMem = $info['avail_mem'] ?? 0;
            $numEntries = $info['num_entries'] ?? 0;
            $numHits = $info['num_hits'] ?? 0;
            $numMisses = $info['num_misses'] ?? 0;
            $startTime = $info['start_time'] ?? time();
            
            return [
                'enabled' => true,
                'memory_total' => $memSize,
                'memory_total_mb' => round($memSize / 1024 / 1024, 2),
                'memory_available_mb' => $availMem > 0 ? round($availMem / 1024 / 1024, 2) : 0,
                'memory_used' => $memSize - $availMem,
                'memory_used_mb' => $availMem > 0 ? round(($memSize - $availMem) / 1024 / 1024, 2) : round($memSize / 1024 / 1024, 2),
                'entries' => $numEntries,
                'hits' => $numHits,
                'misses' => $numMisses,
                'hit_rate' => ($numHits + $numMisses) > 0 ? round(($numHits / ($numHits + $numMisses)) * 100, 2) : 0,
                'start_time' => date('Y-m-d H:i:s', $startTime),
            ];
        } catch (\Exception $e) {
            Log::error("Error al obtener estadísticas de caché: " . $e->getMessage());
            return ['enabled' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verifica si el caché está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Obtiene el prefijo actual
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Obtiene el TTL por defecto
     */
    public function getDefaultTtl(): int
    {
        return $this->defaultTtl;
    }

    /**
     * Lista todas las claves que coinciden con el prefijo
     */
    public function listKeys(): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            if (!function_exists('\\apcu_cache_info')) {
                return [];
            }
            
            $info = \apcu_cache_info();
            $keys = [];

            if (isset($info['cache_list'])) {
                foreach ($info['cache_list'] as $entry) {
                    if (strpos($entry['info'], $this->prefix) === 0) {
                        $keys[] = [
                            'key' => $entry['info'],
                            'full_key' => $entry['info'],
                            'size' => $entry['mem_size'] ?? 0,
                            'ttl' => $entry['ttl'] ?? 0,
                            'creation_time' => isset($entry['creation_time']) ? date('Y-m-d H:i:s', $entry['creation_time']) : 'N/A',
                            'access_time' => isset($entry['access_time']) ? date('Y-m-d H:i:s', $entry['access_time']) : 'N/A',
                            'num_hits' => $entry['ref_count'] ?? 0
                        ];
                    }
                }
            }

            return $keys;
        } catch (\Exception $e) {
            Log::error("Error al listar claves: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el valor y metadatos de una clave
     */
    public function getWithMetadata(string $key): array
    {
        if (!$this->enabled) {
            return ['exists' => false];
        }

        if (!function_exists('\\apcu_fetch')) {
            return ['exists' => false, 'error' => 'APCu functions not available'];
        }

        $fullKey = $this->getFullKey($key);
        $success = false;
        $value = \apcu_fetch($fullKey, $success);

        if (!$success) {
            return ['exists' => false];
        }

        // Buscar metadatos en cache_info
        try {
            if (!function_exists('\\apcu_cache_info')) {
                return [
                    'exists' => true,
                    'value' => $value,
                    'error' => 'Metadata not available'
                ];
            }
            
            $info = \apcu_cache_info();
            $metadata = null;

            if (isset($info['cache_list'])) {
                foreach ($info['cache_list'] as $entry) {
                    if ($entry['info'] === $fullKey) {
                        $metadata = $entry;
                        break;
                    }
                }
            }

            return [
                'exists' => true,
                'value' => $value,
                'metadata' => $metadata
            ];
        } catch (\Exception $e) {
            return [
                'exists' => true,
                'value' => $value,
                'metadata' => null
            ];
        }
    }
}
