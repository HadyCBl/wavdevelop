<?php

require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
// $db_name = $_ENV['DDBB_NAME_GENERAL']; 
return;
class CollationIntegrityChecker
{
    private $pdo;
    private $dbname;

    public function __construct($host, $dbname, $user, $pass)
    {
        $this->dbname = $dbname;
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function runCompleteCheck()
    {
        echo "<h2>🔍 VERIFICACIÓN DE INTEGRIDAD POST-COLLATION</h2>";
        echo "<h3>Base de datos: {$this->dbname}</h3>";
        echo "<hr>";

        $this->checkTableCollations();
        $this->checkColumnCollations();
        $this->checkDataIntegrity();
        $this->checkCharacterIssues();
        $this->checkIndexIntegrity();
        $this->checkForeignKeyIntegrity();

        echo "<hr>";
        echo "<h3>✅ Verificación completada</h3>";
    }

    private function checkTableCollations()
    {
        echo "<h4>📋 1. Verificando collation de tablas</h4>";

        $stmt = $this->pdo->query("
            SELECT TABLE_NAME, TABLE_COLLATION 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = '{$this->dbname}'
            ORDER BY TABLE_NAME
        ");

        $incorrectTables = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['TABLE_COLLATION'] !== 'utf8mb4_general_ci') {
                $incorrectTables[] = $row;
                echo "⚠️  Tabla `{$row['TABLE_NAME']}`: {$row['TABLE_COLLATION']}<br>";
            } else {
                echo "✅ Tabla `{$row['TABLE_NAME']}`: {$row['TABLE_COLLATION']}<br>";
            }
        }

        if (empty($incorrectTables)) {
            echo "<strong>✅ Todas las tablas tienen el collation correcto</strong><br><br>";
        } else {
            echo "<strong>⚠️  " . count($incorrectTables) . " tablas con collation incorrecto</strong><br><br>";
        }
    }

    private function checkColumnCollations()
    {
        echo "<h4>📝 2. Verificando collation de columnas</h4>";

        $stmt = $this->pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME, DATA_TYPE 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = '{$this->dbname}'
              AND COLLATION_NAME IS NOT NULL
            ORDER BY TABLE_NAME, COLUMN_NAME
        ");

        $incorrectColumns = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['COLLATION_NAME'] !== 'utf8mb4_general_ci') {
                $incorrectColumns[] = $row;
                echo "⚠️  `{$row['TABLE_NAME']}`.`{$row['COLUMN_NAME']}` ({$row['DATA_TYPE']}): {$row['COLLATION_NAME']}<br>";
            }
        }

        if (empty($incorrectColumns)) {
            echo "✅ Todas las columnas tienen el collation correcto<br><br>";
        } else {
            echo "<strong>⚠️  " . count($incorrectColumns) . " columnas con collation incorrecto</strong><br><br>";
        }
    }

    private function checkDataIntegrity()
    {
        echo "<h4>🔢 3. Verificando integridad de datos</h4>";

        $stmt = $this->pdo->query("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = '{$this->dbname}'
            ORDER BY TABLE_NAME
        ");

        $totalRows = 0;
        $tablesChecked = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tableName = $row['TABLE_NAME'];

            try {
                $countStmt = $this->pdo->query("SELECT COUNT(*) as total FROM `$tableName`");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                $totalRows += $count;
                $tablesChecked++;

                echo "✅ `$tableName`: $count registros<br>";
            } catch (PDOException $e) {
                echo "❌ Error leyendo `$tableName`: " . $e->getMessage() . "<br>";
            }
        }

        echo "<strong>📊 Total: $tablesChecked tablas verificadas, $totalRows registros en total</strong><br><br>";
    }

    private function checkCharacterIssues()
    {
        echo "<h4>🔤 4. Buscando caracteres problemáticos</h4>";

        $stmt = $this->pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = '{$this->dbname}'
              AND DATA_TYPE IN ('varchar', 'text', 'char', 'longtext', 'mediumtext', 'tinytext')
            ORDER BY TABLE_NAME, COLUMN_NAME
        ");

        $issuesFound = false;
        $checkedColumns = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tableName = $row['TABLE_NAME'];
            $columnName = $row['COLUMN_NAME'];
            $checkedColumns++;

            try {
                // 1. Buscar caracteres de reemplazo (�) que indican corrupción
                $checkStmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM `$tableName` 
                    WHERE `$columnName` LIKE '%�%'
                ");
                $checkStmt->execute();
                $replacementChars = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];

                // 2. Buscar secuencias de bytes inválidas comunes
                $checkStmt2 = $this->pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM `$tableName` 
                    WHERE `$columnName` LIKE '%\xC0\x80%'  -- Secuencia UTF-8 inválida
                       OR `$columnName` LIKE '%\xC1\x81%'  -- Secuencia UTF-8 inválida
                       OR `$columnName` LIKE '%\xEF\xBF\xBD%' -- Carácter de reemplazo UTF-8
                ");
                $checkStmt2->execute();
                $invalidBytes = $checkStmt2->fetch(PDO::FETCH_ASSOC)['count'];

                // 3. Verificar caracteres de control problemáticos
                $checkStmt3 = $this->pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM `$tableName` 
                    WHERE `$columnName` REGEXP '[[:cntrl:]]'
                      AND `$columnName` NOT REGEXP '^[[:space:][:print:]]*$'
                ");
                $checkStmt3->execute();
                $controlChars = $checkStmt3->fetch(PDO::FETCH_ASSOC)['count'];

                // 4. Buscar caracteres que no deberían estar en texto normal
                $checkStmt4 = $this->pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM `$tableName` 
                    WHERE LENGTH(`$columnName`) != CHAR_LENGTH(`$columnName`)
                ");
                $checkStmt4->execute();
                $lengthMismatch = $checkStmt4->fetch(PDO::FETCH_ASSOC)['count'];

                $totalIssues = $replacementChars + $invalidBytes + $controlChars + $lengthMismatch;

                if ($totalIssues > 0) {
                    echo "⚠️  `$tableName`.`$columnName`: ";
                    $details = [];
                    if ($replacementChars > 0) $details[] = "$replacementChars caracteres de reemplazo (�)";
                    if ($invalidBytes > 0) $details[] = "$invalidBytes secuencias UTF-8 inválidas";
                    if ($controlChars > 0) $details[] = "$controlChars caracteres de control problemáticos";
                    if ($lengthMismatch > 0) $details[] = "$lengthMismatch registros con discrepancia de longitud";

                    echo implode(', ', $details) . "<br>";
                    $issuesFound = true;

                    // Mostrar ejemplos de registros problemáticos
                    if ($replacementChars > 0) {
                        $exampleStmt = $this->pdo->prepare("
                            SELECT `$columnName` 
                            FROM `$tableName` 
                            WHERE `$columnName` LIKE '%�%' 
                            LIMIT 3
                        ");
                        $exampleStmt->execute();
                        while ($example = $exampleStmt->fetch(PDO::FETCH_ASSOC)) {
                            $sample = substr($example[$columnName], 0, 100);
                            echo "&nbsp;&nbsp;&nbsp;📝 Ejemplo: " . htmlspecialchars($sample) . "...<br>";
                        }
                    }
                }
            } catch (PDOException $e) {
                echo "❌ Error verificando `$tableName`.`$columnName`: " . $e->getMessage() . "<br>";

                // Intentar una verificación más simple
                try {
                    $simpleCheck = $this->pdo->prepare("SELECT COUNT(*) as count FROM `$tableName` WHERE `$columnName` LIKE '%�%'");
                    $simpleCheck->execute();
                    $simpleResult = $simpleCheck->fetch(PDO::FETCH_ASSOC)['count'];
                    if ($simpleResult > 0) {
                        echo "&nbsp;&nbsp;&nbsp;⚠️  Verificación simple: $simpleResult registros con caracteres de reemplazo<br>";
                        $issuesFound = true;
                    }
                } catch (PDOException $e2) {
                    echo "&nbsp;&nbsp;&nbsp;❌ No se pudo verificar esta columna<br>";
                }
            }
        }

        if (!$issuesFound) {
            echo "✅ No se encontraron caracteres problemáticos en $checkedColumns columnas verificadas<br>";
        } else {
            echo "<br><strong>💡 Sugerencia:</strong> Para reparar caracteres problemáticos, considera ejecutar:<br>";
            echo "<code>UPDATE tabla SET columna = REPLACE(columna, '�', '');</code><br>";
        }
        echo "<br>";
    }

    private function checkIndexIntegrity()
    {
        echo "<h4>🗂️  5. Verificando integridad de índices</h4>";

        $stmt = $this->pdo->query("
            SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = '{$this->dbname}'
            GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
            ORDER BY TABLE_NAME, INDEX_NAME
        ");

        $indexCount = 0;
        $errors = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $indexCount++;
            try {
                // Verificar que el índice funcione
                $this->pdo->query("SHOW INDEX FROM `{$row['TABLE_NAME']}` WHERE Key_name = '{$row['INDEX_NAME']}'");
            } catch (PDOException $e) {
                echo "❌ Error en índice `{$row['INDEX_NAME']}` de tabla `{$row['TABLE_NAME']}`: " . $e->getMessage() . "<br>";
                $errors++;
            }
        }

        if ($errors === 0) {
            echo "✅ Todos los índices ($indexCount) están funcionando correctamente<br>";
        } else {
            echo "⚠️  $errors errores encontrados en índices<br>";
        }
        echo "<br>";
    }

    private function checkForeignKeyIntegrity()
    {
        echo "<h4>🔗 6. Verificando llaves foráneas</h4>";

        $stmt = $this->pdo->query("
            SELECT 
                TABLE_NAME,
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = '{$this->dbname}'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, CONSTRAINT_NAME
        ");

        $fkCount = 0;
        $errors = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $fkCount++;
            try {
                // Verificar integridad referencial
                $checkStmt = $this->pdo->prepare("
                    SELECT COUNT(*) as orphans
                    FROM `{$row['TABLE_NAME']}` t1
                    LEFT JOIN `{$row['REFERENCED_TABLE_NAME']}` t2 
                        ON t1.`{$row['COLUMN_NAME']}` = t2.`{$row['REFERENCED_COLUMN_NAME']}`
                    WHERE t1.`{$row['COLUMN_NAME']}` IS NOT NULL 
                      AND t2.`{$row['REFERENCED_COLUMN_NAME']}` IS NULL
                ");
                $checkStmt->execute();
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($result['orphans'] > 0) {
                    echo "⚠️  FK `{$row['CONSTRAINT_NAME']}`: {$result['orphans']} registros huérfanos<br>";
                    $errors++;
                }
            } catch (PDOException $e) {
                echo "❌ Error verificando FK `{$row['CONSTRAINT_NAME']}`: " . $e->getMessage() . "<br>";
                $errors++;
            }
        }

        if ($errors === 0) {
            echo "✅ Todas las llaves foráneas ($fkCount) tienen integridad correcta<br>";
        } else {
            echo "⚠️  $errors problemas encontrados en llaves foráneas<br>";
        }
        echo "<br>";
    }
}

// Ejecutar verificación
try {
    $checker = new CollationIntegrityChecker($db_host, $db_name, $db_user, $db_password);
    $checker->runCompleteCheck();
} catch (Exception $e) {
    echo "❌ Error ejecutando verificación: " . $e->getMessage();
}
