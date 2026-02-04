<?php

namespace Micro\Generic;

use Micro\Helpers\Log;

class Asset
{
    private static ?array $manifest = null;
    private static string $manifestPath = __DIR__ . '/../../public/assets/dist/manifest.json';
    private static bool $isProduction = false;
    private static array $loadedScripts = [];
    private static string $hostUrl = '';

    /**
     * Mapa de dependencias por bundle
     * Define qué librerías necesita cada entrypoint
     */
    private static array $bundleDependencies = [
        'another' => ['jquery', 'alpine', 'datatables'],
        'caja' => [],
        'shared' => ['alpine'],
        'otros_ingresos' => [],
        'compras_ventas' => ['jquery'],
    ];

    public static function setEnvironment(bool $isProduction): void
    {
        self::$isProduction = $isProduction;
    }

    public static function setHostUrl(string $url): void
    {
        self::$hostUrl = rtrim($url, '/');
        // Log::info("Host URL de assets establecido a: " . self::$hostUrl);
    }

    public static function getHostUrl(): string
    {
        return self::$hostUrl;
    }

    private static function loadManifest(): void
    {
        if (self::$manifest === null) {
            if (file_exists(self::$manifestPath)) {
                $content = file_get_contents(self::$manifestPath);
                self::$manifest = json_decode($content, true) ?? [];
            } else {
                self::$manifest = [];
                if (self::$isProduction) {
                    error_log("Asset manifest no encontrado: " . self::$manifestPath);
                }
            }
        }
    }

    public static function get(string $name): ?string
    {
        self::loadManifest();

        if (isset(self::$manifest[$name]) && !empty(self::$manifest[$name])) {
            return self::$hostUrl . self::$manifest[$name];
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $baseName = pathinfo($name, PATHINFO_FILENAME);

        return self::$hostUrl . "/public/assets/dist/{$extension}/bundle_{$baseName}.{$extension}";
    }

    /**
     * Obtiene las dependencias de un bundle específico
     * 
     * @param string $bundleName Nombre del bundle (sin extensión)
     * @return array Lista de dependencias ['jquery', 'alpine', etc.]
     */
    private static function getBundleDependencies(string $bundleName): array
    {
        return self::$bundleDependencies[$bundleName] ?? ['alpine']; // Por defecto, solo Alpine
    }

    /**
     * Verifica si un bundle necesita una dependencia específica
     * 
     * @param string $bundleName Nombre del bundle
     * @param string $dependency Nombre de la dependencia
     * @return bool
     */
    private static function bundleNeeds(string $bundleName, string $dependency): bool
    {
        $dependencies = self::getBundleDependencies($bundleName);
        return in_array($dependency, $dependencies, true);
    }

    public static function script(string $name, array $options = []): string
    {
        self::loadManifest();

        $scripts = [];
        $defer = $options['defer'] ?? false;
        $async = $options['async'] ?? false;
        $includeRuntime = $options['includeRuntime'] ?? true;
        $includeVendors = $options['includeVendors'] ?? true;

        $attributes = '';
        if ($defer) $attributes .= ' defer';
        if ($async) $attributes .= ' async';

        // 1. Runtime (webpack runtime) - SIEMPRE necesario en producción
        if ($includeRuntime && self::exists('runtime.js') && !in_array('runtime.js', self::$loadedScripts)) {
            $runtimeUrl = self::get('runtime.js');
            if ($runtimeUrl) {
                $scripts[] = '<script src="' . htmlspecialchars($runtimeUrl) . '"' . $attributes . '></script>';
                self::$loadedScripts[] = 'runtime.js';
            }
        }

        // 2. Vendors (código común) - SIEMPRE necesario
        if ($includeVendors && self::exists('vendors.js') && !in_array('vendors.js', self::$loadedScripts)) {
            $vendorsUrl = self::get('vendors.js');
            if ($vendorsUrl) {
                $scripts[] = '<script src="' . htmlspecialchars($vendorsUrl) . '"' . $attributes . '></script>';
                self::$loadedScripts[] = 'vendors.js';
            }
        }

        // 3. jQuery - SOLO si el bundle lo necesita
        if (
            $includeVendors
            && self::bundleNeeds($name, 'jquery')
            && self::exists('jquery.js')
            && !in_array('jquery.js', self::$loadedScripts)
        ) {

            $jqueryUrl = self::get('jquery.js');
            if ($jqueryUrl) {
                $scripts[] = '<script src="' . htmlspecialchars($jqueryUrl) . '"' . $attributes . '></script>';
                self::$loadedScripts[] = 'jquery.js';
            }
        }

        // 4. Alpine.js - SOLO si el bundle lo necesita
        if (
            $includeVendors
            && self::bundleNeeds($name, 'alpine')
            && self::exists('alpine.js')
            && !in_array('alpine.js', self::$loadedScripts)
        ) {

            $alpineUrl = self::get('alpine.js');
            if ($alpineUrl) {
                $scripts[] = '<script src="' . htmlspecialchars($alpineUrl) . '"' . $attributes . '></script>';
                self::$loadedScripts[] = 'alpine.js';
            }
        }

        // 5. DataTables - SOLO si el bundle lo necesita
        if (
            $includeVendors
            && self::bundleNeeds($name, 'datatables')
            && self::exists('datatables.js')
            && !in_array('datatables.js', self::$loadedScripts)
        ) {

            $datatablesUrl = self::get('datatables.js');
            if ($datatablesUrl) {
                $scripts[] = '<script src="' . htmlspecialchars($datatablesUrl) . '"' . $attributes . '></script>';
                self::$loadedScripts[] = 'datatables.js';
            }
        }

        // 6. Bundle principal
        $bundleKey = $name . '.js';
        $url = self::get($bundleKey);
        if ($url) {
            $scripts[] = '<script src="' . htmlspecialchars($url) . '"' . $attributes . '></script>';
            self::$loadedScripts[] = $bundleKey;
        }

        return implode("\n    ", $scripts);
    }

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

    public static function style(string $name, array $options = []): string
    {
        self::loadManifest();

        static $vendorsLoaded = false;
        static $commonLoaded = false;

        $styles = [];
        $media = $options['media'] ?? 'all';
        $includeVendors = $options['includeVendors'] ?? ($name !== 'vendors');

        // 1. Cargar CSS de vendors (librerías externas) si existe
        if ($includeVendors && $name !== 'vendors' && !$vendorsLoaded && self::exists('vendors.css')) {
            $vendorsUrl = self::get('vendors.css');
            if ($vendorsUrl) {
                $styles[] = '<link rel="stylesheet" href="' . htmlspecialchars($vendorsUrl) . '" media="' . $media . '">';
                $vendorsLoaded = true;
            }
        }

        // 2. Cargar CSS común (código compartido entre tus bundles) si existe
        if ($includeVendors && $name !== 'common' && !$commonLoaded && self::exists('common.css')) {
            $commonUrl = self::get('common.css');
            if ($commonUrl) {
                $styles[] = '<link rel="stylesheet" href="' . htmlspecialchars($commonUrl) . '" media="' . $media . '">';
                $commonLoaded = true;
            }
        }

        // 3. Cargar CSS específico del bundle
        $url = self::get($name . '.css');
        if ($url) {
            $styles[] = '<link rel="stylesheet" href="' . htmlspecialchars($url) . '" media="' . $media . '">';
        }

        return implode("\n    ", $styles);
    }

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

    public static function exists(string $name): bool
    {
        self::loadManifest();
        return isset(self::$manifest[$name]) && !empty(self::$manifest[$name]);
    }

    public static function bundle(string $name, array $options = []): array
    {
        return [
            'css' => self::style($name, $options),
            'js' => self::script($name, $options),
        ];
    }

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

    public static function manifest(): array
    {
        self::loadManifest();
        return self::$manifest;
    }

    public static function clearCache(): void
    {
        self::$manifest = null;
        self::$loadedScripts = [];
    }

    public static function resetLoadedScripts(): void
    {
        self::$loadedScripts = [];
    }

    /**
     * Registra las dependencias de un bundle dinámicamente
     * 
     * @param string $bundleName Nombre del bundle
     * @param array $dependencies Array de dependencias ['jquery', 'alpine', etc.]
     */
    public static function registerBundleDependencies(string $bundleName, array $dependencies): void
    {
        self::$bundleDependencies[$bundleName] = $dependencies;
    }

    /**
     * Obtiene el mapa completo de dependencias
     * 
     * @return array
     */
    public static function getDependenciesMap(): array
    {
        return self::$bundleDependencies;
    }

    public static function debug(): string
    {
        self::loadManifest();

        $html = '<!-- Asset Debug Information -->' . "\n";
        $html .= '<!-- Environment: ' . (self::$isProduction ? 'PRODUCTION' : 'DEVELOPMENT') . ' -->' . "\n";
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
            foreach (self::$manifest as $key => $path) {
                $html .= '<!--   ' . $key . ' => ' . $path . ' -->' . "\n";
            }
        }

        return $html;
    }
}
