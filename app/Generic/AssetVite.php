<?php

namespace Micro\Generic;

use Micro\Helpers\Log;

/**
 * Clase para manejar assets compilados con Vite
 * Similar a Asset.php pero específica para Vite
 */
class AssetVite
{
    private static ?array $manifest = null;
    private static string $manifestPath = __DIR__ . '/../../public/assets/vite-dist/manifest.json';
    private static bool $isProduction = false;
    private static array $loadedScripts = [];
    private static string $hostUrl = '';
    private static bool $devMode = false;
    private static string $devServerUrl = 'http://localhost:5173';

    /**
     * Mapa de dependencias por bundle (similar a Asset.php)
     */
    private static array $bundleDependencies = [
        // Ejemplo incluido
        // 'example' => ['alpine'],
        'another' => ['jquery', 'alpine', 'datatables'],
        // Agrega tus dependencias aquí:
        // 'dashboard' => ['jquery', 'alpine'],
        // 'settings' => ['alpine'],
    ];

    public static function setEnvironment(bool $isProduction): void
    {
        self::$isProduction = $isProduction;
    }

    public static function setHostUrl(string $url): void
    {
        self::$hostUrl = rtrim($url, '/');
    }

    public static function getHostUrl(): string
    {
        return self::$hostUrl;
    }

    /**
     * Activa el modo desarrollo de Vite (HMR)
     * En desarrollo, Vite sirve los assets desde su dev server
     */
    public static function enableDevMode(bool $enable = true, string $serverUrl = 'http://localhost:5173'): void
    {
        self::$devMode = $enable;
        self::$devServerUrl = rtrim($serverUrl, '/');
    }

    public static function isDevMode(): bool
    {
        return self::$devMode;
    }

    private static function loadManifest(): void
    {
        if (self::$manifest === null) {
            if (file_exists(self::$manifestPath)) {
                $content = file_get_contents(self::$manifestPath);
                self::$manifest = json_decode($content, true) ?? [];
            } else {
                self::$manifest = [];
                if (self::$isProduction && !self::$devMode) {
                    error_log("AssetVite manifest no encontrado: " . self::$manifestPath);
                }
            }
        }
    }

    /**
     * Obtiene la URL de un asset desde el manifest de Vite
     */
    public static function get(string $name): ?string
    {
        // En modo desarrollo, usar el dev server de Vite
        if (self::$devMode) {
            // Eliminar la barra inicial si existe
            $path = ltrim($name, '/');
            return self::$devServerUrl . '/' . $path;
        }

        self::loadManifest();

        // Buscar en el manifest de Vite
        // El manifest usa la ruta completa como key: "includes/js/vite_example.js"
        // Pero nosotros pasamos "example.js", así que construimos la key correcta

        // Si es un .js, intentar construir la ruta completa
        if (strpos($name, '.js') !== false && strpos($name, 'includes/') === false) {
            // Extraer el nombre sin .js
            $bundleName = str_replace('.js', '', $name);
            // Construir la key del manifest
            $manifestKey = "includes/js/vite_{$bundleName}.js";

            if (isset(self::$manifest[$manifestKey])) {
                $entry = self::$manifest[$manifestKey];
                if (isset($entry['file'])) {
                    return self::$hostUrl . '/public/assets/vite-dist/' . $entry['file'];
                }
            }
        }

        // Búsqueda directa por key
        if (isset(self::$manifest[$name])) {
            $entry = self::$manifest[$name];

            // Vite manifest tiene estructura diferente a webpack
            if (isset($entry['file'])) {
                return self::$hostUrl . '/public/assets/vite-dist/' . $entry['file'];
            }
        }

        // Fallback a path directo
        return self::$hostUrl . "/public/assets/vite-dist/{$name}";
    }

    /**
     * Obtiene las dependencias de un bundle
     */
    private static function getBundleDependencies(string $bundleName): array
    {
        return self::$bundleDependencies[$bundleName] ?? [];
    }

    /**
     * Verifica si un bundle necesita una dependencia específica
     */
    private static function bundleNeeds(string $bundleName, string $dependency): bool
    {
        $dependencies = self::getBundleDependencies($bundleName);
        return in_array($dependency, $dependencies, true);
    }

    /**
     * Obtiene los CSS de un entry desde el manifest de Vite
     */
    private static function getEntryCss(string $entryName): array
    {
        self::loadManifest();

        $cssFiles = [];

        // Construir la key correcta del manifest
        $manifestKey = "includes/js/vite_{$entryName}.js";

        if (isset(self::$manifest[$manifestKey]['css'])) {
            foreach (self::$manifest[$manifestKey]['css'] as $cssFile) {
                $cssFiles[] = self::$hostUrl . '/public/assets/vite-dist/' . $cssFile;
            }
        }

        return $cssFiles;
    }

    /**
     * Genera los tags de script para un bundle de Vite
     * Similar a Asset::script() pero adaptado para Vite
     */
    public static function script(string $name, array $options = []): string
    {
        $scripts = [];
        $defer = $options['defer'] ?? false; // No usar defer con modules
        $async = $options['async'] ?? false;
        $type = $options['type'] ?? 'module'; // Vite usa ES modules

        $attributes = '';
        // Los módulos ES6 ya son diferidos por defecto, no agregar defer si es module
        if ($type === 'module') {
            $attributes .= ' type="module"';
        } else {
            if ($defer) $attributes .= ' defer';
            if ($async) $attributes .= ' async';
            if ($type) $attributes .= ' type="' . $type . '"';
        }

        // En modo desarrollo, cargar el cliente Vite primero
        if (self::$devMode && !in_array('@vite/client', self::$loadedScripts)) {
            $scripts[] = '<script type="module" src="' . self::$devServerUrl . '/@vite/client"></script>';
            self::$loadedScripts[] = '@vite/client';
        }

        // Cargar chunks de vendors si existen
        if (self::$isProduction && !self::$devMode) {
            $includeVendors = $options['includeVendors'] ?? true;

            if ($includeVendors) {
                // jQuery
                if (self::bundleNeeds($name, 'jquery') && !in_array('jquery', self::$loadedScripts)) {
                    $jqueryUrl = self::getChunkUrl('jquery');
                    if ($jqueryUrl && !in_array('jquery', self::$loadedScripts)) {
                        $scripts[] = '<script src="' . htmlspecialchars($jqueryUrl) . '"' . $attributes . '></script>';
                        self::$loadedScripts[] = 'jquery';
                    }
                }

                // Alpine.js
                if (self::bundleNeeds($name, 'alpine') && !in_array('alpine', self::$loadedScripts)) {
                    $alpineUrl = self::getChunkUrl('alpine');
                    if ($alpineUrl && !in_array('alpine', self::$loadedScripts)) {
                        $scripts[] = '<script src="' . htmlspecialchars($alpineUrl) . '"' . $attributes . '></script>';
                        self::$loadedScripts[] = 'alpine';
                    }
                }

                // DataTables
                if (self::bundleNeeds($name, 'datatables') && !in_array('datatables', self::$loadedScripts)) {
                    $datatablesUrl = self::getChunkUrl('datatables');
                    if ($datatablesUrl && !in_array('datatables', self::$loadedScripts)) {
                        $scripts[] = '<script src="' . htmlspecialchars($datatablesUrl) . '"' . $attributes . '></script>';
                        self::$loadedScripts[] = 'datatables';
                    }
                }

                // Vendors generales
                if (!in_array('vendors', self::$loadedScripts)) {
                    $vendorsUrl = self::getChunkUrl('vendors');
                    if ($vendorsUrl) {
                        $scripts[] = '<script src="' . htmlspecialchars($vendorsUrl) . '"' . $attributes . '></script>';
                        self::$loadedScripts[] = 'vendors';
                    }
                }
            }
        }

        // Bundle principal
        $bundleKey = $name . '.js';
        if (!in_array($bundleKey, self::$loadedScripts)) {
            // En dev mode, construir la ruta completa desde las entradas de vite.config.js
            if (self::$devMode) {
                $url = self::$devServerUrl . '/includes/js/vite_' . $name . '.js';
            } else {
                $url = self::get($bundleKey);
            }

            if ($url) {
                $scripts[] = '<script src="' . htmlspecialchars($url) . '"' . $attributes . '></script>';
                self::$loadedScripts[] = $bundleKey;
            }
        }

        return implode("\n    ", $scripts);
    }

    /**
     * Busca la URL de un chunk en el manifest
     */
    private static function getChunkUrl(string $chunkName): ?string
    {
        self::loadManifest();

        foreach (self::$manifest as $key => $entry) {
            if (isset($entry['file']) && strpos($entry['file'], "chunk_{$chunkName}") !== false) {
                return self::$hostUrl . '/public/assets/vite-dist/' . $entry['file'];
            }
        }

        return null;
    }

    /**
     * Genera múltiples scripts
     */
    public static function scripts(array $bundles, array $options = []): string
    {
        $scripts = [];

        foreach ($bundles as $bundle) {
            $bundleScripts = self::script($bundle, $options);
            if (!empty($bundleScripts)) {
                $scripts[] = $bundleScripts;
            }
        }

        return implode("\n    ", $scripts);
    }

    /**
     * Genera el tag de CSS para un bundle de Vite
     */
    public static function style(string $name, array $options = []): string
    {
        $styles = [];
        $media = $options['media'] ?? 'all';

        // En modo desarrollo, Vite inyecta CSS automáticamente vía HMR
        if (self::$devMode) {
            return '';
        }

        // En producción, cargar CSS desde manifest
        $cssFiles = self::getEntryCss($name);

        foreach ($cssFiles as $cssUrl) {
            if (!in_array($cssUrl, self::$loadedScripts)) {
                $styles[] = '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '" media="' . $media . '">';
                self::$loadedScripts[] = $cssUrl;
            }
        }

        return implode("\n    ", $styles);
    }

    /**
     * Genera múltiples estilos
     */
    public static function styles(array $bundles, array $options = []): string
    {
        $styles = [];

        foreach ($bundles as $bundle) {
            $bundleStyles = self::style($bundle, $options);
            if (!empty($bundleStyles)) {
                $styles[] = $bundleStyles;
            }
        }

        return implode("\n    ", $styles);
    }

    /**
     * Verifica si un asset existe en el manifest
     */
    public static function exists(string $name): bool
    {
        if (self::$devMode) {
            return true; // En dev mode, asumimos que existe
        }

        self::loadManifest();
        return isset(self::$manifest[$name]);
    }

    /**
     * Obtiene el bundle completo (CSS + JS)
     */
    public static function bundle(string $name, array $options = []): array
    {
        return [
            'css' => self::style($name, $options),
            'js' => self::script($name, $options),
        ];
    }

    /**
     * Renderiza el bundle completo
     */
    public static function render(string $name, array $options = []): string
    {
        $bundle = self::bundle($name, $options);
        $output = [];

        if (!empty($bundle['css'])) {
            $output[] = $bundle['css'];
        }

        if (!empty($bundle['js'])) {
            $output[] = $bundle['js'];
        }

        return implode("\n    ", $output);
    }

    /**
     * Obtiene el manifest completo
     */
    public static function manifest(): array
    {
        self::loadManifest();
        return self::$manifest;
    }

    /**
     * Limpia la cache
     */
    public static function clearCache(): void
    {
        self::$manifest = null;
        self::$loadedScripts = [];
    }

    /**
     * Resetea los scripts cargados
     */
    public static function resetLoadedScripts(): void
    {
        self::$loadedScripts = [];
    }

    /**
     * Registra dependencias de un bundle
     */
    public static function registerBundleDependencies(string $bundleName, array $dependencies): void
    {
        self::$bundleDependencies[$bundleName] = $dependencies;
    }

    /**
     * Obtiene el mapa de dependencias
     */
    public static function getDependenciesMap(): array
    {
        return self::$bundleDependencies;
    }

    /**
     * Información de debug
     */
    public static function debug(): string
    {
        self::loadManifest();

        $html = '<!-- AssetVite Debug Information -->' . "\n";
        $html .= '<!-- Environment: ' . (self::$isProduction ? 'PRODUCTION' : 'DEVELOPMENT') . ' -->' . "\n";
        $html .= '<!-- Dev Mode: ' . (self::$devMode ? 'YES (' . self::$devServerUrl . ')' : 'NO') . ' -->' . "\n";
        $html .= '<!-- Host URL: ' . self::$hostUrl . ' -->' . "\n";
        $html .= '<!-- Manifest Path: ' . self::$manifestPath . ' -->' . "\n";
        $html .= '<!-- Manifest Exists: ' . (file_exists(self::$manifestPath) ? 'YES' : 'NO') . ' -->' . "\n";
        $html .= '<!-- Assets in manifest: ' . count(self::$manifest) . ' -->' . "\n";
        $html .= '<!-- Loaded scripts: ' . implode(', ', self::$loadedScripts) . ' -->' . "\n";

        $html .= '<!-- Bundle Dependencies: -->' . "\n";
        foreach (self::$bundleDependencies as $bundle => $deps) {
            $html .= '<!--   ' . $bundle . ' => [' . implode(', ', $deps) . '] -->' . "\n";
        }

        if (!empty(self::$manifest)) {
            $html .= '<!-- Available assets: -->' . "\n";
            foreach (self::$manifest as $key => $entry) {
                if (is_array($entry) && isset($entry['file'])) {
                    $html .= '<!--   ' . $key . ' => ' . $entry['file'] . ' -->' . "\n";
                }
            }
        }

        return $html;
    }
}
