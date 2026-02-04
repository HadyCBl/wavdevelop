<?php
/**
 * getDatosTitular.php
 *
 * Archivo que devuelve en formato JSON la información básica (DPI, nombres, apellidos) del titular
 * asociado a una cuenta de ahorros (ahomcta).
 */

// Ajustar la cabecera para retornar JSON
header('Content-Type: application/json; charset=utf-8');

// Incluir archivos de configuración y conexión
include __DIR__ . '/../../includes/Config/config.php';
include __DIR__ . '/../../includes/BD_con/db_con.php';
include __DIR__ . '/../../includes/Config/database.php';

session_start();
if (!isset($_SESSION['usu'])) {
    // Si no hay sesión, devolver un error 401 (No autorizado)
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Ajustar zona horaria si es necesario
date_default_timezone_set('America/Guatemala');

try {
    // Inicializar y abrir la conexión a la base de datos
    $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
    $conn = $database->openConnection(); // Retorna un objeto PDO

    // Leer el parámetro 'cuenta' enviado por POST
    $cuenta = isset($_POST['cuenta']) ? trim($_POST['cuenta']) : '';

    // Validar que se haya recibido la cuenta
    if (empty($cuenta)) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'No se recibió la cuenta.'
        ]);
        exit;
    }

    /**
     * Función auxiliar para ejecutar consultas con PDO de forma segura.
     *
     * @param PDO    $conn   Conexión PDO
     * @param string $sql    Consulta SQL con marcadores
     * @param array  $params Parámetros de la consulta
     *
     * @return array         Arreglo asociativo con la primera fila de resultados, o vacío si no hay resultados
     * @throws Exception     En caso de error al ejecutar
     */
    function executeQuery(PDO $conn, string $sql, array $params = []): array {
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Error al ejecutar la consulta: $sql");
        }
        // fetch() traerá solo la primera fila
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Consulta para obtener el cliente asociado a la cuenta
    $sql = "
        SELECT 
            cta.ccodcli,
            cli.no_identifica, 
            cli.primer_name, 
            cli.segundo_name, 
            cli.tercer_name, 
            cli.primer_last, 
            cli.segundo_last, 
            cli.casada_last
        FROM ahomcta cta
        INNER JOIN tb_cliente cli ON cta.ccodcli = cli.idcod_cliente
        WHERE cta.ccodaho = :cuenta
        LIMIT 1
    ";

    // Ejecutar la consulta
    $row = executeQuery($conn, $sql, [':cuenta' => $cuenta]);

    // Verificar si se obtuvo algún resultado
    if ($row) {
        // Armar la respuesta con los campos necesarios
        $data = [
            'status'    => 'ok',
            'dpi'       => $row['no_identifica']   ?? '',
            'nombre1'   => $row['primer_name']     ?? '',
            'nombre2'   => $row['segundo_name']    ?? '',
            'nombre3'   => $row['tercer_name']     ?? '',
            'apellido1' => $row['primer_last']     ?? '',
            'apellido2' => $row['segundo_last']    ?? '',
            'apellido3' => $row['casada_last']     ?? ''
        ];
        echo json_encode($data);
    } else {
        // No se encontró información para la cuenta dada
        echo json_encode([
            'status'  => 'error',
            'message' => 'No se encontró información para la cuenta especificada.'
        ]);
    }

} catch (Exception $e) {
    // Manejo de excepciones: retornar 500 y el mensaje de error
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error al consultar los datos: ' . $e->getMessage()
    ]);
} finally {
    // Cerrar la conexión en el bloque finally
    if (isset($database)) {
        $database->closeConnection();
    }
}
