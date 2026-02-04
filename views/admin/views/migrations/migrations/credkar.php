<?php

/**
 * Este archivo maneja la carga de archivos Excel y su almacenamiento en el servidor.
 * 
 * Funciones principales:
 * - sendSSEMessage: Envía mensajes SSE (Server-Sent Events) al cliente.
 * - Manejo de la carga del archivo Excel a través de una solicitud POST.
 * 
 * Dependencias:
 * - PhpOffice\PhpSpreadsheet\IOFactory: Para manejar archivos Excel.
 * - Autoload de Composer.
 * - Configuración de la base de datos.
 * - Funciones generales.
 * 
 * Variables de sesión:
 * - idusuario: ID del usuario actual.
 * - tipopp: Tipo de operacion (proporcionado a través de POST) [1: Insertar-commit, 0: solo revisar-rollback]
 * - excel_file_path: Ruta del archivo Excel cargado.
 * 
 * Variables de fecha y hora:
 * - hoy2: Fecha y hora actual en formato "Y-m-d H:i:s".
 * - hoy: Fecha actual en formato "Y-m-d".
 * 
 * Proceso de carga de archivos:
 * - Verifica si la solicitud es de tipo POST y si el archivo está presente.
 * - Verifica si hay errores en la carga del archivo.
 * - Define un directorio permanente para almacenar el archivo.
 * - Crea la carpeta 'uploads' si no existe.
 * - Mueve el archivo a la ubicación permanente.
 * - Guarda la ruta del archivo en la sesión.
 * - Devuelve una respuesta JSON indicando el estado de la operación.
 */
ob_start();
session_start();

require __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../../src/funcphp/func_gen.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0); // Sin límite de tiempo de ejecución

function sendSSEMessage($event, $data, $id = null)
{
    if ($id) {
        echo "id: $id\n";
    }
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Manejar la carga del archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    if ($_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excelFile']['tmp_name'];
        $fileName = $_FILES['excelFile']['name'];
        if (isset($_POST['tipo'])) {
            $_SESSION['tipopp'] = $_POST['tipo'];
        }

        // Define un directorio permanente para almacenar el archivo
        $destinationFilePath = __DIR__ . '/uploads/' . $fileName;

        // Crea la carpeta uploads si no existe
        if (!file_exists(__DIR__ . '/uploads/')) {
            mkdir(__DIR__ . '/uploads/', 0777, true);
        }

        // Mover el archivo a la ubicación permanente
        if (move_uploaded_file($fileTmpPath, $destinationFilePath)) {
            // Guarda la ruta del archivo en la sesión
            $_SESSION['excel_file_path'] = $destinationFilePath;
            echo json_encode(['status' => 'success', 'message' => 'Archivo cargado correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al mover el archivo a su destino final']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al cargar el archivo']);
    }
    exit;
}
/**
 * Manejar SSE (Server-Sent Events) para procesar un archivo Excel y registrar datos en la base de datos.
 *
 * Este script escucha solicitudes SSE y procesa un archivo Excel cargado en la sesión.
 * Realiza validaciones básicas y registra los datos en la tabla "CREDKAR" de la base de datos.
 * Envía mensajes SSE para informar el progreso y los errores durante el procesamiento.
 *
 * Parámetros de entrada:
 * - $_GET['listen']: Si está presente, inicia el procesamiento SSE.
 *
 * Variables de sesión requeridas:
 * - $_SESSION['excel_file_path']: Ruta del archivo Excel cargado.
 * - $_SESSION['tipopp']: Tipo de procesamiento (opcional, por defecto 0).
 *
 * Funciones utilizadas:
 * - generarCodigoAleatorio($length): Genera un código aleatorio de longitud especificada, para identificar la migracion a realizar.
 * - sendSSEMessage($event, $data): Envía un mensaje SSE con el evento y los datos especificados.
 *
 * Dependencias:
 * - IOFactory: Clase para cargar y manipular archivos Excel.
 * - Database: Clase para manejar la conexión y operaciones con la base de datos.
 *
 * Flujo del script:
 * 1. Configura las cabeceras para SSE.
 * 2. Verifica la existencia del archivo Excel en la sesión.
 * 3. Carga y convierte el archivo Excel en un array.
 * 4. Procesa cada fila del archivo Excel, realizando validaciones y registrando datos en la base de datos.
 * 5. Envía mensajes SSE para informar el progreso y los errores.
 * 6. Realiza commit o rollback de la transacción según el tipo de procesamiento.
 * 7. Envía un mensaje SSE final con el resultado del procesamiento.
 * 8. Cierra la conexión a la base de datos y elimina el archivo temporal.
 *
 * Manejo de errores:
 * - Si ocurre una excepción, se realiza rollback de la transacción y se envía un mensaje SSE de error.
 */

// Manejar SSE (Server-Sent Events)
if (isset($_GET['listen'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    $identificadormigracion = generarCodigoAleatorio(3);
    sendSSEMessage('progress', [
        'row' => 0,
        'total' => 0,
        'message' => "INICIO DE PROCESO, CODIGO DE MIGRACION: $identificadormigracion"
    ]);
    if (!isset($_SESSION['excel_file_path'])) {
        sendSSEMessage('error', ['message' => 'No se ha cargado ningún archivo']);
        exit;
    }

    $fileTmpPath = $_SESSION['excel_file_path'];
    $tipopp = $_SESSION['tipopp'] ?? 0;
    // $tipopp =  0;

    try {
        // Cargar la hoja de cálculo
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();

        // Convertir la hoja de cálculo en un array
        $data = $sheet->toArray(null, true, true, false);
        $headers = array_shift($data);
        $datos = [];

        // Procesar filas del archivo Excel
        foreach ($data as $row) {
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $row[$index] ?? null;
            }
            $datos[] = $rowData;
        }

        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

        $database->openConnection();
        $database->beginTransaction();

        $totalRows = count($datos);
        $conterrors = 0;
        foreach ($datos as $key => $value) {
            if (is_null($value["CCODCTA"]) || $value['CCODCTA'] == '') {
                throw new Exception("$key No existe un Codigo de Credito [CCODCTA]");
            }

            if (is_null($value["DFECPRO"]) || $value['DFECPRO'] == '') {
                // throw new Exception("$key No existe una fecha de PAGO [DFECPRO]");
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $value['CCODCTA'] . "  No existe una fecha de PAGO [DFECPRO]"
                ]);
                continue;
            }

            $result = $database->selectColumns('cremcre_meta', ['CCODCTA'], 'CCODCTA=?', [$value["CCODCTA"]]);
            if (empty($result)) {
                // if (5 == 4) {
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $value['CCODCTA'] . " No existe ningun Credito con el codigo indicado"
                ]);
                continue;
            }

            $CREDKAR = array(
                "CCODCTA" => $value["CCODCTA"],
                "DFECPRO" => $value["DFECPRO"],
                "DFECSIS" => $hoy2,
                "CNROCUO" => 1,
                "NMONTO" => $value["NMONTO"] ?? 0,
                "CNUMING" => $value["CNUMING"] ?? "",
                "CCONCEP" => $value["CCONCEP"] ?? "PAGO CON BOLETA NO. " . ($value["CNUMING"] ?? ""),
                "KP" => $value["KP"] ?? 0,
                "INTERES" => $value["INTERES"] ?? 0,
                "MORA" => $value["MORA"] ?? 0,
                // "AHOPRG" => $value["CNUMING"],
                "OTR" => $value["OTR"] ?? 0,
                "CCODINS" => $value["CCODINS"] ?? "ip3",
                "CCODOFI" => "ip3",
                "CCODUSU" => $value["CCODUSU"] ?? "4",
                "CTIPPAG" => "P",
                "CMONEDA" => "1",
                "FormPago" => $value["FormPago"] ?? "1",
                "DFECBANCO" => $value["DFECBANCO"] ?? "0000-00-00",
                "boletabanco" => $value["boletabanco"] ?? "",
                "CBANCO" => $value["CBANCO"] ?? "",
                "CCODBANCO" => $value["CCODBANCO"] ?? "",
                "CESTADO" => "1",
                "DFECMOD" => $hoy,
                "CTERMID" => $identificadormigracion,
            );

            $database->insert("CREDKAR", $CREDKAR);

            sendSSEMessage('progress', [
                'row' => $key + 1,
                'total' => $totalRows,
                'message' => "Procesando registro " . ($key + 1) . " de $totalRows =>  " . $value['CCODCTA'] . " " . ($value['DFECPRO'] ?? "")
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
    } catch (Error $e) {
        // Capturar errores fatales de PHP
        $errorDetails = [
            'message' => $e->getMessage(),
            // 'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'type' => get_class($e)
        ];

        sendSSEMessage('error', [
            'message' => $e->getMessage(),
            'details' => $errorDetails,
            'displayMessage' => "Error fatal en línea {$e->getLine()}: {$e->getMessage()}"
        ]);
    } finally {
        $database->closeConnection();
        unlink($fileTmpPath);
    }

    exit;
}
