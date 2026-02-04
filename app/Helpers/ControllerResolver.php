<?php

namespace Micro\Helpers;

/**
 * Resuelve el namespace completo de los controladores basándose en la estructura de la URL
 * Similar al comportamiento de Laravel Route Resolution
 */
class ControllerResolver
{
    /**
     * Mapeo de segmentos de URL a namespaces
     * @var array
     */
    private static $namespaceMap = [
        '/api/reportes/creditos' => 'Micro\\Controllers\\Reportes\\Creditos',
        '/api/reportes/ahorros' => 'Micro\\Controllers\\Reportes\\Ahorros',
        '/api/reportes/contabilidad' => 'Micro\\Controllers\\Reportes\\Contabilidad',
        '/api/reportes/seguros' => 'Micro\\Controllers\\Reportes\\Seguros',
        '/api/crud' => 'Micro\\Controllers\\Crud',
        '/api/seguros' => 'Micro\\Controllers\\Seguros',
        '/api/conta' => 'Micro\\Controllers\\Contabilidad',
    ];

    /**
     * Namespace base para controladores
     * @var string
     */
    private static $baseNamespace = 'Micro\\Controllers';

    /**
     * Resuelve el namespace completo del controlador
     *
     * @param string $class Nombre del controlador (ej: 'MoraController')
     * @param string $uri URI de la solicitud (ej: '/api/reportes/creditos/mora')
     * @return string Namespace completo (ej: 'Micro\Controllers\Reportes\Creditos\MoraController')
     */
    public static function resolve(string $class, string $uri): string
    {
        // Si el controlador ya incluye namespace completo (contiene \), retornarlo directamente
        if (strpos($class, '\\') !== false) {
            return $class;
        }

        // Buscar coincidencia en el mapa de namespaces
        foreach (self::$namespaceMap as $pattern => $namespace) {
            if (strpos($uri, $pattern) === 0) {
                return "{$namespace}\\{$class}";
            }
        }

        // Fallback: usar namespace base
        return self::$baseNamespace . "\\{$class}";
    }

    /**
     * Registra un nuevo mapeo de URL a namespace
     *
     * @param string $urlPattern Patrón de URL (ej: '/api/reportes/seguros')
     * @param string $namespace Namespace correspondiente (ej: 'Micro\Controllers\Reportes\Seguros')
     * @return void
     */
    public static function registerNamespace(string $urlPattern, string $namespace): void
    {
        self::$namespaceMap[$urlPattern] = $namespace;
    }

    /**
     * Establece el namespace base para controladores
     *
     * @param string $namespace
     * @return void
     */
    public static function setBaseNamespace(string $namespace): void
    {
        self::$baseNamespace = $namespace;
    }

    /**
     * Obtiene todos los mapeos registrados
     *
     * @return array
     */
    public static function getNamespaceMap(): array
    {
        return self::$namespaceMap;
    }
}
