<?php

namespace App\Generic;

use Micro\Helpers\Log;

/**
 * Servicio para manejar tipos de cambio de monedas
 * Soporta múltiples APIs de tipo de cambio con fallback automático
 * 
 * @author Sistema MicroSystem Plus
 * @version 1.0
 */
class CurrencyExchangeService
{
    /**
     * Cache de tipos de cambio en memoria
     */
    private array $cache = [];
    
    /**
     * Tiempo de vida del cache en segundos (1 hora por defecto)
     */
    private int $cacheLifetime = 3600;
    
    /**
     * Archivo de cache local
     */
    private string $cacheFile;
    
    /**
     * APIs disponibles para consultar tipos de cambio
     */
    private array $apis;
    
    /**
     * Configuración por defecto
     */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'cache_lifetime' => 3600, // 1 hora
            'cache_file' => __DIR__ . '/../../logs/currency_cache.json',
            'timeout' => 30,
            'user_agent' => 'MicroSystemPlus/1.0',
            'verify_ssl' => false, // Para desarrollo local
        ], $config);

        $this->cacheLifetime = $this->config['cache_lifetime'];
        $this->cacheFile = $this->config['cache_file'];
        
        $this->initializeAPIs();
        $this->loadCache();
    }

    /**
     * Inicializa las APIs disponibles
     */
    private function initializeAPIs(): void
    {
        $this->apis = [
            'exchangerate_api' => [
                'name' => 'ExchangeRate-API',
                'url' => 'https://api.exchangerate-api.com/v4/latest/{base}',
                'free' => true,
                'limit' => '1500/mes',
                'parser' => 'parseExchangeRateAPI'
            ],

            'exchangerate_host' => [
                'name' => 'ExchangeRate.host',
                'url' => 'https://api.exchangerate.host/latest?base={base}&symbols={symbols}',
                'free' => true,
                'limit' => 'Ilimitado',
                'parser' => 'parseExchangeRateHost'
            ]
        ];
    }

    /**
     * Obtiene el tipo de cambio entre dos monedas
     * 
     * @param string $from Moneda origen (ej: 'USD')
     * @param string $to Moneda destino (ej: 'GTQ')
     * @param bool $useCache Si usar cache o forzar consulta nueva
     * @return array|false Array con información del tipo de cambio o false si falla
     */
    public function getExchangeRate(string $from, string $to, bool $useCache = true): array|false
    {
        $cacheKey = strtoupper($from) . '_' . strtoupper($to);
        
        // Verificar cache si está habilitado
        if ($useCache && $this->isCacheValid($cacheKey)) {
            return $this->cache[$cacheKey];
        }

        // Intentar obtener de cada API
        foreach ($this->apis as $apiKey => $apiConfig) {
            try {
                $result = $this->queryAPI($apiKey, $from, $to);
                if ($result !== false) {
                    // Log::info("Tipo de cambio obtenido de {$apiConfig['name']}", [
                    //     'from' => $from,
                    //     'to' => $to,
                    //     'rate' => $result['rate'],
                    //     'source' => $apiConfig['name']
                    // ]);
                    // Guardar en cache
                    $this->saveToCache($cacheKey, $result);
                    return $result;
                }
            } catch (\Exception $e) {
                Log::error("Error al consultar API {$apiConfig['name']}: " . $e->getMessage(), [
                    'from' => $from,
                    'to' => $to,
                    'api' => $apiKey
                ]);
                continue;
            }
        }

        return false;
    }

    /**
     * Consulta una API específica
     */
    private function queryAPI(string $apiKey, string $from, string $to): array|false
    {
        $apiConfig = $this->apis[$apiKey];
        $url = $this->buildAPIUrl($apiConfig['url'], $from, $to);
        
        if ($url === false) {
            return false; // API requiere clave y no está configurada
        }

        $response = $this->makeHTTPRequest($url);
        
        if ($response === false) {
            return false;
        }

        $parser = $apiConfig['parser'];
        return $this->$parser($response, $from, $to, $apiConfig['name']);
    }

    /**
     * Construye la URL de la API
     */
    private function buildAPIUrl(string $template, string $from, string $to): string|false
    {
        $url = str_replace('{base}', strtoupper($from), $template);
        $url = str_replace('{symbols}', strtoupper($to), $url);
        
        // Verificar si necesita API key
        if (strpos($url, '{api_key}') !== false) {
            $apiKey = $this->getApiKey($template);
            if ($apiKey === null) {
                return false; // API key requerida pero no configurada
            }
            $url = str_replace('{api_key}', $apiKey, $url);
        }
        
        return $url;
    }

    /**
     * Obtiene la clave API según la URL
     */
    private function getApiKey(string $template): ?string
    {
        if (strpos($template, 'fixer.io') !== false) {
            return $this->config['fixer_api_key'] ?? null;
        }
        if (strpos($template, 'currencyapi.com') !== false) {
            return $this->config['currencyapi_key'] ?? null;
        }
        return null;
    }

    /**
     * Realiza la petición HTTP
     */
    private function makeHTTPRequest(string $url): string|false
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'],
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            error_log("cURL Error: $error");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode - Response: " . substr($response, 0, 200));
            return false;
        }

        return $response;
    }

    /**
     * Parser para ExchangeRate-API
     */
    private function parseExchangeRateAPI(string $response, string $from, string $to, string $source): array|false
    {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['rates'][strtoupper($to)])) {
            return false;
        }

        return [
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => (float) $data['rates'][strtoupper($to)],
            'date' => $data['date'] ?? date('Y-m-d'),
            'timestamp' => time(),
            'source' => $source,
            'inverse_rate' => 1 / (float) $data['rates'][strtoupper($to)]
        ];
    }

    /**
     * Parser para ExchangeRate.host
     */
    private function parseExchangeRateHost(string $response, string $from, string $to, string $source): array|false
    {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['rates'][strtoupper($to)])) {
            return false;
        }

        return [
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => (float) $data['rates'][strtoupper($to)],
            'date' => $data['date'] ?? date('Y-m-d'),
            'timestamp' => time(),
            'source' => $source,
            'inverse_rate' => 1 / (float) $data['rates'][strtoupper($to)]
        ];
    }

    /**
     * Parser para Fixer.io
     */
    private function parseFixerIO(string $response, string $from, string $to, string $source): array|false
    {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['rates'][strtoupper($to)])) {
            return false;
        }

        return [
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => (float) $data['rates'][strtoupper($to)],
            'date' => $data['date'] ?? date('Y-m-d'),
            'timestamp' => time(),
            'source' => $source,
            'inverse_rate' => 1 / (float) $data['rates'][strtoupper($to)]
        ];
    }

    /**
     * Parser para CurrencyAPI
     */
    private function parseCurrencyAPI(string $response, string $from, string $to, string $source): array|false
    {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data'][strtoupper($to)]['value'])) {
            return false;
        }

        $rate = (float) $data['data'][strtoupper($to)]['value'];
        
        return [
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => $rate,
            'date' => date('Y-m-d'),
            'timestamp' => time(),
            'source' => $source,
            'inverse_rate' => 1 / $rate
        ];
    }

    /**
     * Convierte un monto de una moneda a otra
     * 
     * @param float $amount Cantidad a convertir
     * @param string $from Moneda origen
     * @param string $to Moneda destino
     * @param bool $useCache Si usar cache
     * @return array|false Array con resultado de la conversión o false si falla
     */
    public function convert(float $amount, string $from, string $to, bool $useCache = true): array|false
    {
        $exchangeRate = $this->getExchangeRate($from, $to, $useCache);
        
        if ($exchangeRate === false) {
            return false;
        }

        $convertedAmount = $amount * $exchangeRate['rate'];

        return [
            'original_amount' => $amount,
            'converted_amount' => $convertedAmount,
            'from' => strtoupper($from),
            'to' => strtoupper($to),
            'rate' => $exchangeRate['rate'],
            'date' => $exchangeRate['date'],
            'source' => $exchangeRate['source'],
            'formatted' => [
                'original' => number_format($amount, 2),
                'converted' => number_format($convertedAmount, 2)
            ]
        ];
    }

    /**
     * Obtiene múltiples tipos de cambio para una moneda base
     * 
     * @param string $base Moneda base
     * @param array $targets Monedas objetivo
     * @param bool $useCache Si usar cache
     * @return array Array con todos los tipos de cambio
     */
    public function getMultipleRates(string $base, array $targets, bool $useCache = true): array
    {
        $results = [];
        
        foreach ($targets as $target) {
            $rate = $this->getExchangeRate($base, $target, $useCache);
            if ($rate !== false) {
                $results[$target] = $rate;
            }
        }
        
        return $results;
    }

    /**
     * Verifica si el cache es válido
     */
    private function isCacheValid(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        $cacheTime = $this->cache[$key]['timestamp'] ?? 0;
        return (time() - $cacheTime) < $this->cacheLifetime;
    }

    /**
     * Guarda en cache
     */
    private function saveToCache(string $key, array $data): void
    {
        $this->cache[$key] = $data;
        $this->persistCache();
    }

    /**
     * Carga el cache desde archivo
     */
    private function loadCache(): void
    {
        if (file_exists($this->cacheFile)) {
            $content = file_get_contents($this->cacheFile);
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->cache = $data;
            }
        }
    }

    /**
     * Persiste el cache en archivo
     */
    private function persistCache(): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($this->cacheFile, json_encode($this->cache, JSON_PRETTY_PRINT));
    }

    /**
     * Limpia el cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /**
     * Obtiene información sobre las APIs disponibles
     */
    public function getAvailableAPIs(): array
    {
        return $this->apis;
    }

    /**
     * Configura claves API
     */
    public function setApiKeys(array $keys): void
    {
        $this->config = array_merge($this->config, $keys);
    }

    /**
     * Obtiene estadísticas del cache
     */
    public function getCacheStats(): array
    {
        $total = count($this->cache);
        $valid = 0;
        $expired = 0;
        
        foreach ($this->cache as $key => $data) {
            if ($this->isCacheValid($key)) {
                $valid++;
            } else {
                $expired++;
            }
        }
        
        return [
            'total' => $total,
            'valid' => $valid,
            'expired' => $expired,
            'cache_file' => $this->cacheFile,
            'cache_lifetime' => $this->cacheLifetime
        ];
    }
}


// Usar la clase directamente
// $service = new CurrencyExchangeService();
// $rate = $service->getExchangeRate('USD', 'GTQ');

// if (!$rate) {
//     echo "No se pudo obtener el tipo de cambio.";
//     return;
// }

// echo "<h1>Pruebas de Tipos de Cambio</h1>";
// echo "<p>Tipo de cambio USD → GTQ: " . $rate['rate'] . "</p>";