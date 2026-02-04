<?php

/**
 * Configuración de Illuminate Database (Eloquent ORM)
 * Mantiene compatibilidad con DatabaseAdapter existente
 */

use Micro\Database\DatabaseManager;

// Inicializar Database Manager (Singleton)
$dbManager = DatabaseManager::getInstance()->boot([
    // Configuración personalizada opcional
    // 'events' => true, // Habilitar event dispatcher
    
    // Puedes sobrescribir configs específicas:
    'default' => ['charset' => 'utf8mb4'],
    // 'general' => ['strict' => false],
]);

// Retornar la instancia para uso opcional
return $dbManager;
