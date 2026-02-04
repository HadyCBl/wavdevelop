 <?php
    // Script de prueba para verificar la clase Database
    return;

    use Micro\Helpers\Log;
    use Creditos\Utilidades\CreditoAmortizationSystem;

    session_start();


    require_once __DIR__ . '/includes/Config/config.php';
    require_once __DIR__ . '/includes/Config/database.php';
    require_once __DIR__ . '/src/funcphp/func_gen.php';


    echo "<h2>🔧 Prueba de la Clase Database</h2>";
    // return;
    try {
        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

        $database->openConnection();
        echo "✅ Conexión abierta correctamente<br>";
        $codigoCredito = "0150010100000169";
        $fechaPago = "2025-10-22";

        Log::info("Reestructurando credito", [$codigoCredito, $fechaPago]);
        $credito = new CreditoAmortizationSystem($codigoCredito, $database);

        // Simula una reestructuración
        $credito->procesaReestructura();
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
        echo "📁 Archivo: " . $e->getFile() . "<br>";
        echo "📍 Línea: " . $e->getLine() . "<br>";
    }
