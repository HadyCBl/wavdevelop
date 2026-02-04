<?php
/**
 * Script para verificar caracteres problemáticos antes de conversión
 * latin1_swedish_ci -> utf8mb4_unicode_ci
 */


require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
$db_name_general = $_ENV['DDBB_NAME_GENERAL']; // Nombre de base de datos general
// Configuración de base de datos
$host = $db_host;
$dbname = $db_name;
// $dbname = $db_name_general;
$username = $db_user;
$password = $db_password;
return;
try {
    // Conexión con charset latin1 para leer datos tal como están
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=latin1", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VERIFICADOR DE CONVERSIÓN CHARSET ===<br>";
    
    // 1. Obtener SOLO las columnas que necesitan conversión
    $sql = "SELECT c.table_name, c.column_name, c.data_type, 
                   c.collation_name, t.table_collation
            FROM information_schema.columns c
            JOIN information_schema.tables t ON c.table_name = t.table_name 
                AND c.table_schema = t.table_schema
            WHERE c.table_schema = ? 
            AND c.data_type IN ('varchar', 'text', 'char', 'mediumtext', 'longtext')
            AND (
                -- Columnas que NO están en utf8mb4_general_ci
                c.collation_name != 'utf8mb4_general_ci' 
                OR c.collation_name IS NULL
                -- Y tablas que NO están en utf8mb4_general_ci
                OR t.table_collation != 'utf8mb4_general_ci'
            )
            ORDER BY c.table_name, c.column_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dbname]);
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $problemas_encontrados = [];
    $total_registros = 0;
    $registros_con_problemas = 0;
    
    foreach ($columnas as $col) {
        $tabla = $col['table_name'];
        $columna = $col['column_name'];
        $collation_actual = $col['collation_name'];
        $tabla_collation = $col['table_collation'];
        
        echo "Verificando: {$tabla}.{$columna} (actual: {$collation_actual})<br>";
        
        // Buscar registros con caracteres especiales
        $sql = "SELECT *, HEX($columna) as hex_value 
                FROM $tabla 
                WHERE $columna IS NOT NULL 
                AND $columna != '' 
                AND $columna REGEXP '[^\x00-\x7F]'
                LIMIT 100";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($registros as $registro) {
                $valor = $registro[$columna];
                $hex = $registro['hex_value'];
                
                // Análisis de caracteres problemáticos
                $analisis = analizarCaracteres($valor, $hex);
                
                if ($analisis['tiene_problemas']) {
                    $problemas_encontrados[] = [
                        'tabla' => $tabla,
                        'columna' => $columna,
                        'valor_original' => $valor,
                        'problemas' => $analisis['problemas'],
                        'sugerencia' => $analisis['sugerencia']
                    ];
                    $registros_con_problemas++;
                }
                $total_registros++;
            }
        } catch (Exception $e) {
            echo "  Error en $tabla.$columna: " . $e->getMessage() . "<br>";
        }
    }
    
    // Mostrar resultados
    mostrarResultados($problemas_encontrados, $total_registros, $registros_con_problemas);
    
    // Generar script de conversión segura
    generarScriptConversion($problemas_encontrados);
    
} catch (Exception $e) {
    echo "Error de conexión: " . $e->getMessage() . "<br>";
}

function analizarCaracteres($valor, $hex) {
    $problemas = [];
    $tiene_problemas = false;
    
    // Caracteres comunes problemáticos en latin1
    $caracteres_problematicos = [
        'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'à', 'á', 'â', 'ã', 'ä', 'å',
        'È', 'É', 'Ê', 'Ë', 'è', 'é', 'ê', 'ë',
        'Ì', 'Í', 'Î', 'Ï', 'ì', 'í', 'î', 'ï',
        'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø',
        'Ù', 'Ú', 'Û', 'Ü', 'ù', 'ú', 'û', 'ü',
        'Ý', 'ý', 'ÿ', 'Ñ', 'ñ', 'Ç', 'ç', '¿', '¡'
    ];
    
    foreach ($caracteres_problematicos as $char) {
        if (strpos($valor, $char) !== false) {
            $problemas[] = "Contiene: $char";
            $tiene_problemas = true;
        }
    }
    
    // Verificar bytes sospechosos en hex
    $bytes_sospechosos = ['C0', 'C1', 'F5', 'F6', 'F7', 'F8', 'F9', 'FA', 'FB', 'FC', 'FD', 'FE', 'FF'];
    foreach ($bytes_sospechosos as $byte) {
        if (strpos($hex, $byte) !== false) {
            $problemas[] = "Byte sospechoso: 0x$byte";
            $tiene_problemas = true;
        }
    }
    
    $sugerencia = $tiene_problemas ? 
        "Verificar conversión manualmente" : 
        "Conversión debería ser segura";
    
    return [
        'tiene_problemas' => $tiene_problemas,
        'problemas' => $problemas,
        'sugerencia' => $sugerencia
    ];
}

function mostrarResultados($problemas, $total, $con_problemas) {
    echo "<br>=== RESUMEN DE ANÁLISIS ===<br>";
    echo "Total de registros analizados: $total<br>";
    echo "Registros con caracteres especiales: $con_problemas<br>";
    if ($total > 0) {
        echo "Porcentaje de registros con problemas: " . round(($con_problemas/$total)*100, 2) . "%<br>";
    } else {
        echo "No se analizaron registros.<br>";
    }
 
    if (count($problemas) > 0) {
        echo "=== PROBLEMAS ENCONTRADOS ===<br>";
        foreach ($problemas as $i => $problema) {
            echo "[$i] Tabla: {$problema['tabla']}, Columna: {$problema['columna']}<br>";
            echo "    Valor: {$problema['valor_original']}<br>";
            echo "    Problemas: " . implode(', ', $problema['problemas']) . "<br>";
            echo "    Sugerencia: {$problema['sugerencia']}<br><br>";

            if ($i >= 20) { // Limitar salida
                echo "... y " . (count($problemas) - 20) . " más<br><br>";
                break;
            }
        }
    } else {
        echo "¡No se encontraron problemas obvios!<br>";
        echo "La conversión debería ser segura.<br><br>";
    }
}

function generarScriptConversion($problemas) {
    echo "=== SCRIPT DE CONVERSIÓN RECOMENDADO ===<br>";
    
    if (count($problemas) > 0) {
        echo "-- PRECAUCIÓN: Se encontraron caracteres especiales<br>";
        echo "-- Ejecutar en entorno de prueba primero<br><br>";

        // Agrupar por tabla
        $tablas_afectadas = [];
        foreach ($problemas as $problema) {
            $tablas_afectadas[$problema['tabla']] = true;
        }

        echo "-- Tablas que requieren atención especial:<br>";
        foreach (array_keys($tablas_afectadas) as $tabla) {
            echo "-- $tabla<br>";
        }
        echo "<br>";
    }

    echo "-- 1. Backup completo<br>";
    echo "-- mysqldump -u usuario -p base_de_datos > backup_antes_conversion.sql<br><br>";

    echo "-- 2. Ajustar índice problemático identificado<br>";
    echo "ALTER TABLE cambios_pendientes DROP INDEX nombre;<br>";
    echo "ALTER TABLE cambios_pendientes ADD INDEX nombre (nombre(191));<br><br>";

    echo "-- 3. Conversión por tablas (ejecutar una por una)<br>";
    echo "-- ALTER TABLE tabla_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;<br><br>";

    echo "-- 4. Verificar después de cada conversión<br>";
    echo "-- SELECT COUNT(*) FROM tabla_name;<br>";
    echo "-- SELECT * FROM tabla_name WHERE columna LIKE '%ñ%' LIMIT 5;<br><br>";
}
?>