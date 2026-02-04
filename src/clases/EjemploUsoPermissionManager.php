<?php

use Micro\Generic\PermissionManager;

/**
 * EJEMPLO PRÁCTICO DE USO DE PermissionManager
 * 
 * Esta clase funciona exactamente como pediste:
 * - Permisos definidos como constantes
 * - Niveles flexibles por permiso (1, 2 o 3 niveles)
 * - Métodos simples para verificar acceso
 */

try {
    // ===== INSTANCIAR LA CLASE =====
    $idUsuarioActual = 123; // ID del usuario actual
    $permisos = new PermissionManager($idUsuarioActual);
    
    echo "=== SISTEMA DE PERMISOS MEJORADO ===\n\n";
    
    // ===== EJEMPLO 1: VERIFICAR NIVEL DE ACCESO =====
    echo "1. VERIFICAR NIVEL DE ACCESO:\n";
    
    $nivelCaja = $permisos->getLevelAccess(PermissionManager::APERTURA_CAJA);
    echo "   - Nivel en APERTURA_CAJA: $nivelCaja\n";
    
    // $nivelUsuarios = $permisos->getLevelAccess(PermissionManager::GESTION_USUARIOS);
    // echo "   - Nivel en GESTION_USUARIOS: $nivelUsuarios\n";
    
    // $nivelReportes = $permisos->getLevelAccess(PermissionManager::REPORTES_FINANCIEROS);
    // echo "   - Nivel en REPORTES_FINANCIEROS: $nivelReportes\n\n";
    
    // ===== EJEMPLO 2: VERIFICAR NIVELES ESPECÍFICOS =====
    echo "2. VERIFICAR NIVELES ESPECÍFICOS:\n";
    
    if ($permisos->isLevelOne(PermissionManager::APERTURA_CAJA)) {
        echo "   ✅ Puede CONSULTAR caja\n";
    } else {
        echo "   ❌ NO puede consultar caja\n";
    }
    
    if ($permisos->isLevelTwo(PermissionManager::APERTURA_CAJA)) {
        echo "   ✅ Puede OPERAR apertura de caja\n";
    } else {
        echo "   ❌ NO puede operar apertura de caja\n";
    }
    
    // if ($permisos->isLevelThree(PermissionManager::GESTION_USUARIOS)) {
    //     echo "   ✅ Es ADMINISTRADOR de usuarios\n";
    // } else {
    //     echo "   ❌ NO es administrador de usuarios\n";
    // }
    
    echo "\n";
    
    // ===== EJEMPLO 3: VERIFICAR ACCESO SIMPLE =====
    echo "3. VERIFICAR ACCESO SIMPLE:\n";
    
    if ($permisos->hasAccess(PermissionManager::MODIFICAR_INTERES)) {
        echo "   ✅ Tiene acceso a MODIFICAR_INTERES\n";
    } else {
        echo "   ❌ NO tiene acceso a MODIFICAR_INTERES\n";
    }
    
    // if ($permisos->hasAccess(PermissionManager::REPORTES_FINANCIEROS)) {
    //     echo "   ✅ Tiene acceso a REPORTES_FINANCIEROS\n";
    // } else {
    //     echo "   ❌ NO tiene acceso a REPORTES_FINANCIEROS\n";
    // }
    
    echo "\n";
    
    // ===== EJEMPLO 4: VERIFICAR NIVEL EXACTO =====
    echo "4. VERIFICAR NIVEL EXACTO:\n";
    
    // if ($permisos->hasExactLevel(PermissionManager::GESTION_CLIENTES, 2)) {
    //     echo "   ✅ Tiene EXACTAMENTE nivel 2 en gestión de clientes\n";
    // } else {
    //     echo "   ❌ NO tiene exactamente nivel 2 en gestión de clientes\n";
    // }
    
    echo "\n";
    
    // ===== EJEMPLO 5: OBTENER INFORMACIÓN COMPLETA =====
    echo "5. INFORMACIÓN COMPLETA DE UN PERMISO:\n";
    
    $infoAperturaCaja = $permisos->getPermissionInfo(PermissionManager::APERTURA_CAJA);
    echo "   Permiso: " . $infoAperturaCaja['permission'] . "\n";
    echo "   Nivel del usuario: " . $infoAperturaCaja['user_level'] . "\n";
    echo "   Tiene acceso: " . ($infoAperturaCaja['has_access'] ? 'SÍ' : 'NO') . "\n";
    echo "   Niveles disponibles: " . implode(', ', $infoAperturaCaja['available_levels']) . "\n";
    echo "   Nivel máximo disponible: " . $infoAperturaCaja['max_available_level'] . "\n";
    
    if (!empty($infoAperturaCaja['level_names'])) {
        echo "   Nombres de niveles accesibles:\n";
        foreach ($infoAperturaCaja['level_names'] as $nivel => $nombre) {
            echo "     - Nivel $nivel: $nombre\n";
        }
    }
    
    echo "\n";
    
    // ===== EJEMPLO 6: VERIFICAR MÚLTIPLES PERMISOS =====
    echo "6. VERIFICAR MÚLTIPLES PERMISOS:\n";
    
    $permisosRequeridos = [
        PermissionManager::APERTURA_CAJA,
        PermissionManager::CIERRE_CAJA
    ];
    
    if ($permisos->hasAllPermissions($permisosRequeridos)) {
        echo "   ✅ Puede manejar caja COMPLETAMENTE (apertura y cierre)\n";
    } else {
        echo "   ❌ NO puede manejar caja completamente\n";
    }
    
    // $permisosAlternativos = [
    //     PermissionManager::REPORTES_FINANCIEROS,
    //     PermissionManager::BALANCE_Y_ER,
    //     PermissionManager::LIBRO_DIARIO
    // ];
    
    // if ($permisos->hasAnyPermission($permisosAlternativos)) {
    //     echo "   ✅ Tiene acceso a AL MENOS UNO de los reportes contables\n";
    // } else {
    //     echo "   ❌ NO tiene acceso a ningún reporte contable\n";
    // }
    
    echo "\n";
    
    // ===== EJEMPLO 7: OBTENER TODOS LOS PERMISOS DEL USUARIO =====
    echo "7. TODOS LOS PERMISOS DEL USUARIO:\n";
    
    $todosLosPermisos = $permisos->getAllUserPermissions();
    
    if (empty($todosLosPermisos)) {
        echo "   ⚠️ El usuario no tiene permisos asignados\n";
    } else {
        foreach ($todosLosPermisos as $permiso => $datos) {
            echo "   - $permiso (Nivel: {$datos['level']})\n";
        }
    }
    
    echo "\n";
    
    // ===== EJEMPLO 8: USO EN APLICACIÓN REAL =====
    echo "8. EJEMPLO DE USO EN APLICACIÓN REAL:\n";
    
    // Simular una función de la aplicación
    function mostrarOpcionesCaja($permisos) {
        echo "   === OPCIONES DE CAJA DISPONIBLES ===\n";
        
        if ($permisos->isLevelOne(PermissionManager::APERTURA_CAJA)) {
            echo "   • Consultar estado de caja\n";
        }
        
        if ($permisos->isLevelTwo(PermissionManager::APERTURA_CAJA)) {
            echo "   • Realizar apertura de caja\n";
        }
        
        if ($permisos->isLevelOne(PermissionManager::CIERRE_CAJA)) {
            echo "   • Ver movimientos de cierre\n";
        }
        
        if ($permisos->isLevelTwo(PermissionManager::CIERRE_CAJA)) {
            echo "   • Realizar cierre de caja\n";
        }
        
        if ($permisos->hasAccess(PermissionManager::VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA)) {
            echo "   • Ver movimientos bancarios en arqueo\n";
        }
        
        if ($permisos->isLevelTwo(PermissionManager::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA)) {
            echo "   • Aprobar/Rechazar movimientos\n";
        }
    }
    
    mostrarOpcionesCaja($permisos);
    
    echo "\n";
    
    // ===== EJEMPLO 9: VALIDACIÓN EN CONTROLADORES =====
    echo "9. VALIDACIÓN EN CONTROLADORES:\n";
    
    // Simular validación en un controlador
    // function validarAccesoControlador($permisos, $accion) {
    //     switch ($accion) {
    //         case 'ver_usuarios':
    //             return $permisos->isLevelOne(PermissionManager::GESTION_USUARIOS);
                
    //         case 'crear_usuario':
    //             return $permisos->isLevelTwo(PermissionManager::GESTION_USUARIOS);
                
    //         case 'eliminar_usuario':
    //             return $permisos->isLevelThree(PermissionManager::GESTION_USUARIOS);
                
    //         case 'abrir_caja':
    //             return $permisos->isLevelTwo(PermissionManager::APERTURA_CAJA);
                
    //         case 'reportes_confidenciales':
    //             return $permisos->isLevelThree(PermissionManager::REPORTES_FINANCIEROS);
                
    //         default:
    //             return false;
    //     }
    // }
    
    // $acciones = ['ver_usuarios', 'crear_usuario', 'eliminar_usuario', 'abrir_caja', 'reportes_confidenciales'];
    
    // foreach ($acciones as $accion) {
    //     $tieneAcceso = validarAccesoControlador($permisos, $accion);
    //     $estado = $tieneAcceso ? '✅ PERMITIDO' : '❌ DENEGADO';
    //     echo "   - $accion: $estado\n";
    // }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . " (línea " . $e->getLine() . ")\n";
}

echo "\n=== FIN DEL EJEMPLO ===\n";

/**
 * RESUMEN DE LA IMPLEMENTACIÓN:
 * 
 * ✅ Permisos definidos como constantes en la clase
 * ✅ Niveles flexibles por permiso (algunos tienen 1, otros 2, otros 3)
 * ✅ Métodos simples: getLevelAccess(), isLevelOne(), isLevelTwo(), isLevelThree()
 * ✅ Compatible con tu sistema actual (usa las mismas tablas)
 * ✅ Fácil de usar y mantener
 * ✅ Documentado y con ejemplos
 * 
 * MÉTODOS PRINCIPALES:
 * - getLevelAccess(CONSTANTE) → Obtiene el nivel (0, 1, 2, 3)
 * - isLevelOne(CONSTANTE) → ¿Tiene nivel 1 o superior?
 * - isLevelTwo(CONSTANTE) → ¿Tiene nivel 2 o superior?  
 * - isLevelThree(CONSTANTE) → ¿Tiene nivel 3?
 * - hasAccess(CONSTANTE) → ¿Tiene cualquier nivel de acceso?
 * - hasExactLevel(CONSTANTE, nivel) → ¿Tiene exactamente ese nivel?
 * 
 * USO TÍPICO:
 * $permisos = new PermissionManager($userId);
 * 
 * if ($permisos->isLevelTwo(PermissionManager::APERTURA_CAJA)) {
 *     // Puede operar apertura de caja
 * }
 * 
 * $nivel = $permisos->getLevelAccess(PermissionManager::GESTION_USUARIOS);
 * // $nivel puede ser 0, 1, 2 o 3
 */
