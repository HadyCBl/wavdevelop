<?php
//VERIFICAR SI TIENE EL PARAMETRO EN LA URL
if (!isset($_GET['test']) || $_GET['test'] !== 'soygay') {
    //mostrar dedo de en medio
    echo "🖕";
    return false;
}

function testAPCu()
{
    echo "<h2>Test de APCu</h2>";

    // 1. Verificar si existe
    if (!function_exists('apcu_enabled')) {
        echo "❌ APCu NO está instalado<br>";
        return false;
    }

    echo "✅ APCu está instalado<br>";

    // 2. Verificar si está habilitado
    if (!apcu_enabled()) {
        echo "❌ APCu está instalado pero deshabilitado<br>";
        return false;
    }

    echo "✅ APCu está habilitado<br>";

    // 3. Test de escritura/lectura
    $testKey = 'test_' . time();
    $testValue = 'Hello APCu!';

    if (apcu_store($testKey, $testValue, 60)) {
        echo "✅ Escritura exitosa<br>";

        $retrieved = apcu_fetch($testKey);
        if ($retrieved === $testValue) {
            echo "✅ Lectura exitosa<br>";

            // Limpiar
            apcu_delete($testKey);
            echo "✅ APCu está completamente funcional<br>";

            // Mostrar estadísticas
            $info = apcu_cache_info();
            echo "<br><strong>Estadísticas:</strong><br>";
            echo "Memoria total: " . number_format($info['mem_size'] / 1024 / 1024, 2) . " MB<br>";
            echo "Memoria disponible: " . number_format($info['avail_mem'] / 1024 / 1024, 2) . " MB<br>";
            echo "Entradas almacenadas: " . $info['num_entries'] . "<br>";
            echo "Hits: " . $info['num_hits'] . "<br>";
            echo "Misses: " . $info['num_misses'] . "<br>";


            // Obtener información completa del caché
            $info = apcu_cache_info();

            echo "<h2>Estadísticas Generales</h2>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Métrica</th><th>Valor</th></tr>";
            echo "<tr><td>Memoria Total</td><td>" . number_format($info['mem_size'] / 1024 / 1024, 2) . " MB</td></tr>";
            echo "<tr><td>Memoria Disponible</td><td>" . number_format($info['avail_mem'] / 1024 / 1024, 2) . " MB</td></tr>";
            echo "<tr><td>Memoria Usada</td><td>" . number_format(($info['mem_size'] - $info['avail_mem']) / 1024 / 1024, 2) . " MB</td></tr>";
            echo "<tr><td>Entradas</td><td>" . $info['num_entries'] . "</td></tr>";
            echo "<tr><td>Hits</td><td>" . $info['num_hits'] . "</td></tr>";
            echo "<tr><td>Misses</td><td>" . $info['num_misses'] . "</td></tr>";
            $hitRate = $info['num_hits'] > 0 ? round(($info['num_hits'] / ($info['num_hits'] + $info['num_misses'])) * 100, 2) : 0;
            echo "<tr><td>Hit Rate</td><td>" . $hitRate . "%</td></tr>";
            echo "<tr><td>Tiempo de inicio</td><td>" . date('Y-m-d H:i:s', $info['start_time']) . "</td></tr>";
            echo "</table>";

            // Mostrar todas las entradas del caché
            echo "<h2>Entradas del Caché</h2>";
            if (isset($info['cache_list']) && !empty($info['cache_list'])) {
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Clave</th><th>Tamaño</th><th>TTL</th><th>Tiempo Creación</th><th>Tiempo Acceso</th><th>Hits</th><th>Valor</th></tr>";

                foreach ($info['cache_list'] as $entry) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($entry['info']) . "</td>";
                    echo "<td>" . number_format($entry['mem_size']) . " bytes</td>";
                    echo "<td>" . ($entry['ttl'] > 0 ? $entry['ttl'] . "s" : "Sin expiración") . "</td>";
                    echo "<td>" . date('Y-m-d H:i:s', $entry['creation_time']) . "</td>";
                    echo "<td>" . date('Y-m-d H:i:s', $entry['access_time']) . "</td>";
                    echo "<td>" . $entry['ref_count'] . "</td>";

                    // Obtener el valor actual
                    $value = apcu_fetch($entry['info']);
                    if ($value !== false) {
                        $displayValue = is_string($value) ? $value : json_encode($value);
                        echo "<td>" . htmlspecialchars(substr($displayValue, 0, 50)) . (strlen($displayValue) > 50 ? '...' : '') . "</td>";
                    } else {
                        echo "<td>No disponible</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "No hay entradas en el caché";
            }

            return true;
        } else {
            echo "❌ Error en la lectura<br>";
        }
    } else {
        echo "❌ Error en la escritura<br>";
    }

    return false;
}

testAPCu();

// if (function_exists('opcache_get_status')) {
//     var_dump(opcache_get_status());
// } else {
//     echo "opcache no esta habiltado";
// }


//mostrar php info
phpinfo();