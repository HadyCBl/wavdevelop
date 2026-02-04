<?php

/**
 * Configuración de Middlewares y Rutas Protegidas
 * 
 * Este archivo centraliza la configuración de:
 * - Rutas protegidas con autenticación
 * - Excepciones de CSRF
 * - Middlewares globales
 * 
 * @package Micro\API
 */

return [
    /**
     * Middlewares globales - Se aplican a TODAS las rutas
     * 
     * Ejemplo:
     * 'global' => [
     *     new CorsMiddleware(),
     *     new LoggingMiddleware(),
     * ]
     */
    'global' => [],

    /**
     * Rutas protegidas con autenticación
     * 
     * Todas las rutas que requieran usuario autenticado.
     * Usa wildcard (*) para incluir sub-rutas.
     * 
     * Ejemplo:
     * - '/api/admin/*' protege /api/admin/users, /api/admin/settings, etc.
     * - '/api/profile' protege solo esa ruta exacta
     */
    'protected' => [
        '/api/crud/*',           // Todos los CRUDs requieren autenticación
        '/api/vistas/*',         // Todas las vistas requieren autenticación
        '/api/reportes/*',       // Todos los reportes requieren autenticación
        '/api/seguros/*',        // Rutas de seguros
    ],

    /**
     * Rutas excluidas de validación CSRF
     * 
     * Útil para:
     * - APIs públicas
     * - Webhooks externos
     * - Health checks
     * - Endpoints que no modifican estado
     * 
     * IMPORTANTE: Usar /* al final para incluir sub-rutas
     * 
     * Ejemplo:
     * - '/api/webhook/*' excluye todos los webhooks
     * - '/api/health' excluye solo ese endpoint
     */
    'csrf_exceptions' => [
        '/api/health',                  // Health check público
        '/api/webhook/*',               // Webhooks externos (todas las sub-rutas)
        '/api/reportes/seguros/*', 
        // '/api/seguros/auxilios/verificar-renovacion',
        // '/api/seguros/auxilios/*',
        // '/api/seguros/auxilios'
    ],
];
