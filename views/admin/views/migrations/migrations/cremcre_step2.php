<?php
/**
 * Este archivo maneja la recepción y procesamiento de un código de migración enviado a través de una solicitud POST.
 * 
 * Funciones principales:
 * - Inicia el almacenamiento en búfer de salida y la sesión.
 * - Incluye archivos necesarios para la configuración y funciones generales.
 * - Establece la zona horaria a 'America/Guatemala'.
 * - Define la función `sendSSEMessage` para enviar mensajes SSE (Server-Sent Events).
 * - Maneja la recepción del código de migración a través de una solicitud POST.
 * - Establece una conexión a la base de datos y busca registros relacionados con el código de migración proporcionado.
 * - Si se encuentran registros, guarda el código de migración y el tipo en la sesión.
 * - Devuelve una respuesta JSON indicando el éxito o el error del proceso.
 * 
 * Variables:
 * - $idusuario: ID del usuario almacenado en la sesión.
 * - $hoy2: Fecha y hora actual en formato 'Y-m-d H:i:s'.
 * - $hoy: Fecha actual en formato 'Y-m-d'.
 * 
 * Función `sendSSEMessage`:
 * - Parámetros:
 *   - $event: Nombre del evento.
 *   - $data: Datos a enviar en formato JSON.
 * - Envía un mensaje SSE con el evento y los datos proporcionados.
 * 
 * Manejo de la solicitud POST:
 * - Verifica si la solicitud es de tipo POST y si contiene el parámetro 'migrationCode'.
 * - Obtiene el código de migración y el tipo (si está presente [1 commit, 0 rollback]) de la solicitud POST.
 * - Intenta establecer una conexión a la base de datos y buscar registros relacionados con el código de migración.
 * - Si no se encuentran registros, lanza una excepción.
 * - Si se encuentran registros, guarda el código de migración y el tipo en la sesión.
 * - Devuelve una respuesta JSON indicando el éxito o el error del proceso.
 * - Cierra la conexión a la base de datos.
 */
ob_start();
session_start();

require __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../../src/funcphp/func_gen.php';

$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

function sendSSEMessage($event, $data)
{
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Manejar la recepción del código de migración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrationCode'])) {
    $migrationCode = $_POST['migrationCode'];
    $tipo = $_POST['tipo'] ?? 0;

    try {
        // Establece conexión a la base de datos
        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
        $database->openConnection();
        $query = "SELECT CCODCTA FROM cremcre_meta WHERE CCodAho= ?";
        $datos = $database->getAllResults($query, [$migrationCode]);

        if (empty($datos)) {
            throw new Exception("No se encontraron registros para el código de migración proporcionado.");
        }
        $_SESSION['codeMigration'] = $migrationCode;
        if (isset($_POST['tipo'])) {
            $_SESSION['tipopp'] = $_POST['tipo'];
        }
        echo json_encode(['status' => 'success', 'message' => 'Codigos de cuenta encontrados']);
    } catch (Exception $e) {
        // sendSSEMessage('error', ['message' => $e->getMessage()]);
        echo json_encode(['status' => 'error', 'message' => 'Error al procesar la solicitud' . $e->getMessage()]);
    } finally {
        $database->closeConnection();
    }
    exit;
}

/**
 * Manejar SSE (Server-Sent Events) para el proceso de migración.
 * 
 * Este script maneja eventos enviados por el servidor para actualizar el progreso de un proceso de migración.
 * 
 * Parámetros:
 * - $_GET['listen']: Si está presente, inicia el proceso de SSE.
 * - $_SESSION['codeMigration']: Código de migración cargado en la sesión.
 * - $_SESSION['tipopp']: Tipo de proceso (0 rollback, o 1 commit) cargado en la sesión.
 * 
 * Funciones:
 * - sendSSEMessage($type, $data): Envía un mensaje SSE al cliente.
 * - generarCodigoAleatorio(): Genera un código aleatorio para identificar la migración.
 * 
 * Proceso:
 * 1. Inicia la conexión SSE y envía un mensaje de inicio.
 * 2. Verifica si existe un código de migración en la sesión.
 * 3. Abre una conexión a la base de datos.
 * 4. Selecciona los datos de la tabla 'cremcre_meta' basados en el código de migración.
 * 5. Inicia una transacción en la base de datos.
 * 6. Itera sobre los registros seleccionados y realiza las siguientes acciones:
 *    - Verifica si existe una fecha de desembolso.
 *    - Verifica si ya existe un desembolso para el crédito.
 *    - Inserta un nuevo registro en la tabla 'CREDKAR'.
 *    - Envía mensajes SSE con el progreso del proceso.
 * 7. Realiza commit o rollback de la transacción basado en el valor de 'tipopp'.
 * 8. Envía un mensaje SSE indicando la finalización del proceso.
 * 9. Maneja excepciones y realiza rollback en caso de error.
 * 10. Cierra la conexión a la base de datos.
 */

// Manejar SSE (Server-Sent Events)
if (isset($_GET['listen'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    sendSSEMessage('progress', [
        'row' => 0,
        'total' => 0,
        'message' => "INICIO DE PROCESO"
    ]);
    if (!isset($_SESSION['codeMigration'])) {
        sendSSEMessage('error', ['message' => 'No se ha cargado ningún codigo de migracion']);
        exit;
    }

    $codeMigration = $_SESSION['codeMigration'];
    $tipopp = $_SESSION['tipopp'] ?? 0;
    // $tipopp =  0;

    try {

        $datos = [];

        $identificadormigracion = generarCodigoAleatorio();

        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

        $database->openConnection();

        $creditos = $database->selectColumns('cremcre_meta', ['CCODCTA', 'Dictamen', 'TipDocDes', 'DfecPago', 'CtipCre', 'DFecDsbls', 'NIntApro', 'noPeriodo', 'MonSug', 'NCapDes', 'DFecVig'], "CCodAho=?", [$codeMigration]);
        $database->beginTransaction();

        sendSSEMessage('progress', [
            'row' => 0,
            'total' => 0,
            'message' => "INICIANDO, CODIGO DE MIGRACION: $identificadormigracion"
        ]);

        $totalRows = count($creditos);
        $conterrors = 0;
        foreach ($creditos as $key => $value) {
            if (is_null($value["DFecDsbls"]) || $value['DFecDsbls'] == '') {
                throw new Exception("$key No existe una fecha de desembolso [DFecDsbls]");
            }

            $result = $database->selectColumns('CREDKAR', ['CCODCTA'], "CCODCTA=? AND CESTADO!='X' AND CTIPPAG='D'", [$value["CCODCTA"]]);
            if (!empty($result)) {
                // if (5 == 4) {
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $value['CCODCTA'] . " YA EXISTE UN DESEMBOLSO PARA ESTE CRÉDITO"
                ]);
                continue;
            }

            $CREDKAR = array(
                "CCODCTA" => $value["CCODCTA"],
                "DFECPRO" => $value["DFecDsbls"],
                "DFECSIS" => $hoy2,
                "CNROCUO" => 1,
                "NMONTO" => $value["NCapDes"],
                "CNUMING" => $value["Dictamen"],
                "CCONCEP" => "DESEMBOLSO DE CRÉDITO",
                "KP" => $value["NCapDes"],
                "INTERES" => 0,
                "MORA" => 0,
                "AHOPRG" => 0,
                "OTR" => 0,
                "CCODINS" => "",
                "CCODOFI" => "1",
                "CCODUSU" => $idusuario,
                "CTIPPAG" => "D",
                "CMONEDA" => "Q",
                "FormPago" => ($value['TipDocDes'] == "C") ? "2" : "1",
                "DFECBANCO" => $value["DFecDsbls"],
                "boletabanco" => '',
                "CBANCO" => "",
                "CCODBANCO" => "",
                "CESTADO" => "1",
                "DFECMOD" => $hoy
            );

            $database->insert("CREDKAR", $CREDKAR);

            sendSSEMessage('progress', [
                'row' => $key + 1,
                'total' => $totalRows,
                'message' => "Procesando registro " . ($key + 1) . " de $totalRows => " . $value['CCODCTA'] . " " . ($value['NCapDes'] ?? "")
            ]);
        }

        if ($tipopp == 1) {
            $database->commit();
        } else {
            $database->rollback();
        }
        sendSSEMessage('done', ['message' => "Proceso concluido correctamente. Se insertaron " . ($totalRows - $conterrors) . " registros. Registros no insertados: $conterrors; de un total de $totalRows"]);
    } catch (Exception $e) {
        if (isset($database)) {
            $database->rollback();
        }
        sendSSEMessage('error', ['message' => $e->getMessage()]);
    } finally {
        $database->closeConnection();
    }

    exit;
}