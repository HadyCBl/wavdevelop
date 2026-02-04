<?php

require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
$db_name_general = $_ENV['DDBB_NAME_GENERAL']; // Nombre de base de datos general

$host = $db_host;
$dbname = $db_name;
// $dbname = $db_name_general;
$user = $db_user;
$pass = $db_password;
return;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta las tablas con utf8mb4_general_ci
    $stmt = $pdo->query("
        SELECT TABLE_NAME 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = '$dbname'
        AND TABLE_COLLATION != 'utf8mb4_general_ci'
    ");
    // $stmt = $pdo->query("
    //     SELECT TABLE_NAME 
    //     FROM information_schema.TABLES 
    //     WHERE TABLE_SCHEMA = '$dbname'
    //       AND TABLE_COLLATION = 'utf8mb4_general_ci'
    // ");

    echo "BASE DE DATOS: $dbname<br>";

    echo "Tablas con collation diferente a utf8mb4_general_ci:<br>";
    if ($stmt->rowCount() === 0) {
        echo "✅ Todas las tablas ya están en utf8mb4_general_ci.<br>";
        exit;
    }


    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
        echo "Ejecutando: $sql<br>";

        try {
            $pdo->exec($sql);
            echo "✅ Tabla `$table` actualizada correctamente.<br>";
        } catch (PDOException $e) {
            echo "❌ Error actualizando `$table`: " . $e->getMessage() . "<br>";
        }
    }

    echo "<br>Proceso completado.<br>";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}



// ALTER TABLE cli_garantia DROP FOREIGN KEY FK_cli_garantia_tb_cliente;

// -- ejecutar el cambio de collation

// ALTER TABLE cli_garantia ADD CONSTRAINT FK_cli_garantia_tb_cliente 
// FOREIGN KEY (idCliente) REFERENCES tb_cliente(idcod_cliente);

// ALTER TABLE tb_garantias_creditos DROP FOREIGN KEY tb_garantias_creditos_ibfk_1;

// -- ejecutar el cambio

// ALTER TABLE tb_garantias_creditos ADD CONSTRAINT tb_garantias_creditos_ibfk_1 
// FOREIGN KEY (id_cremcre_meta) REFERENCES cremcre_meta(CCODCTA);
