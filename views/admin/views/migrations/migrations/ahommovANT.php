<?php

/**
 * Este script maneja la carga de archivos Excel y su almacenamiento en un directorio específico, para su posterior registro en la bd.
 * 
 * Dependencias:
 * - PhpOffice\PhpSpreadsheet\IOFactory
 * - Autoload de Composer
 * 
 * Variables de sesión:
 * - idusuario: ID del usuario actual
 * - tipopp: Tipo de operacion (proporcionado a través de POST) [1: Insertar-commit, 0: solo revisar-rollback]
 * - excel_file_path: Ruta del archivo Excel cargado
 * 
 * Funciones:
 * - sendSSEMessage($event, $data): Envía un mensaje SSE (Server-Sent Event) al cliente.
 * 
 * Flujo del script:
 * 1. Inicia el almacenamiento en búfer de salida y la sesión.
 * 2. Si se recibe una solicitud POST con un archivo Excel:
 *    - Verifica si no hay errores en la carga del archivo.
 *    - Guarda el tipo de archivo en la sesión si se proporciona.
 *    - Define un directorio permanente para almacenar el archivo.
 *    - Crea la carpeta 'uploads' si no existe.
 *    - Mueve el archivo cargado a la ubicación permanente.
 *    - Guarda la ruta del archivo en la sesión.
 *    - Devuelve una respuesta JSON indicando el éxito o el error de la operación.
 */
ob_start();
session_start();

require __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../../includes/Config/model/ahorros/Ahomtip.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0); // Sin límite de tiempo de ejecución

function sendSSEMessage($event, $data)
{
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

        if (isset($_POST['inputs'])) {
            $_SESSION['inputs'] = json_decode($_POST['inputs'], true); // Convertir JSON a array PHP
        }
        if (isset($_POST['selects'])) {
            $_SESSION['selects'] = json_decode($_POST['selects'], true);
        }
        if (isset($_POST['radios'])) {
            $_SESSION['radios'] = json_decode($_POST['radios'], true);
        }
        if (isset($_POST['archivo'])) {
            $_SESSION['archivo'] = json_decode($_POST['archivo'], true);
        }

        //guardar en un log, todo lo que tiene la variable $_SESSION
        // $logFile = __DIR__ . '/uploads/session_log.txt';
        // $logData = print_r($_POST, true);
        // file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);



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
 * Este script maneja la carga y procesamiento de un archivo Excel para la migración de datos de clientes.
 * 
 * - Verifica si se ha cargado un archivo Excel en la sesión.
 * - Carga el archivo Excel y convierte su contenido en un array.
 * - Procesa cada fila del archivo Excel, realizando validaciones y generando códigos de cliente.
 * - Inserta los datos procesados en la base de datos.
 * - Utiliza Server-Sent Events (SSE) para enviar mensajes de progreso y errores al cliente.
 * 
 * @file /path/server/microsystemplus/views/admin/views/migrations/migrations/clientes.php
 * 
 * @throws Exception Si ocurre un error durante el procesamiento de los datos.
 */
if (isset($_GET['listen'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    if (!isset($_SESSION['excel_file_path'])) {
        sendSSEMessage('error', ['message' => 'No se ha cargado ningún archivo']);
        exit;
    }

    $fileTmpPath = $_SESSION['excel_file_path'];
    $tipopp = $_SESSION['tipopp'];

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

        /**
         * DICCIONARIO DE CAMPOS ENVIADOS DESDE LA VISTA
         */
        // [['idcuenta1','idcuenta2','lotes'],[],['checkCliente'],[]]

        if(!isset($_SESSION['inputs']['idcuenta1']) || !isset($_SESSION['inputs']['idcuenta2'])) {
            sendSSEMessage('error', ['message' => 'No se han enviado los campos de cuenta']);
            exit;
        }

        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

        $database->openConnection();

        $transaccion = false;

        $contaux = 0;
        $conttransaccion = 0;

        $lotes = $_SESSION['inputs']['lotes'] ?? 1000;

        $campoCuentaAnfitrion = $_SESSION['inputs']['idcuenta1'];
        $campoCuentaDestino = $_SESSION['inputs']['idcuenta2'];

        $identificadormigracion = generarCodigoAleatorio();

        $totalRows = count($datos);
        $conterrors = 0;
        foreach ($datos as $key => $value) {

            // Iniciar una nueva transacción al comienzo de un lote
            if (!$transaccion) {
                $database->beginTransaction();
                // $transaccion = true;
            }

            /**
             * VALIDACION DE CAMPOS OBLIGATORIOS, el campo anfitrion es el campo que contiene la cuenta guardada en el archivo excel
             * y el campo destino es el campo que contiene la cuenta guardada en la tabla ahomcta
             */
            if(!isset($value[$campoCuentaAnfitrion])) {
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $campoCuentaAnfitrion . " NO ENVIADO"
                ]);
                continue;
            }

            /**
             * VALIDACION DE CUENTA
             */
            $cuenta = $database->selectColumns("ahomcta", ["ccodaho", "nlibreta"], "$campoCuentaDestino=?", [$value[$campoCuentaAnfitrion]]);
            if (empty($cuenta)) {
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $campoCuentaAnfitrion . " CUENTA NO EXISTE" . $campoCuentaDestino
                ]);

                if ($_SESSION['radios']['checkCuenta'] == "Si") {
                    continue;
                } else {
                    throw new Exception("La cuenta no existe en la base de datos.");
                }
            }

            $codcuenta = $cuenta[0]["ccodaho"];
            $nlibreta = $cuenta[0]["nlibreta"];

            $ahommov = array(
                "ccodaho" => $codcuenta,
                "dfecope" => $value['dfecope'],
                "ctipope" => $value['ctipope'],
                "cnumdoc" => $value['cnumdoc'] ?? "",
                "ctipdoc" => $value['ctipdoc'] ?? "E",
                "crazon" => $value['crazon'] ?? "",
                "concepto" => $value['concepto'] ?? "",
                "nlibreta" => $value['nlibreta'] ?? $nlibreta,
                "nrochq" => $value['nrochq'] ?? "",
                "tipchq" => $value['tipchq'] ?? "",
                "dfeccomp" => $value['dfeccomp'] ?? NULL,
                "numpartida" => $value['numpartida'] ?? "",
                "monto" => $value['monto'] ?? 0,
                "lineaprint" => $value['lineaprint'] ?? "S",
                "numlinea" => $value['numlinea'] ?? 1,
                "correlativo" => $value['correlativo'] ?? 1,
                "dfecmod" => $value['dfecmod'] ?? $hoy2,
                "codusu" => $value['codusu'] ?? $idusuario,
                "cestado" => 1,
                "auxi" => $value['auxi'] ?? "",
                "created_at" => $value['created_at'] ?? $hoy2,
                "created_by" => $value['created_by'] ?? $idusuario,
            );


            $database->insert("ahommov", $ahommov);
            $contaux++;

            // Enviar mensaje de progreso al cliente
            sendSSEMessage('progress', [
                'row' => $key + 1,
                'total' => $totalRows,
                'message' => "Procesando registro " . ($key + 1) . " de $totalRows con cuenta: $codcuenta y documento: " . ($value['cnumdoc'] ?? "")
            ]);

            if ($tipopp == 1) {
                // $database->commit();
                if ($contaux == $lotes || $key == array_key_last($datos)) {
                    $transaccion = true;
                }

                if ($transaccion) {
                    $database->commit();
                    $conttransaccion++;
                    $contaux = 0;
                    // echo "LOTE " . $conttransaccion . " CONFIRMADO CORRECTAMENTE<br>";
                    sendSSEMessage('progress', [
                        'row' => $key + 1,
                        'total' => $totalRows,
                        'message' => "++++++++ LOTE " . $conttransaccion . " CONFIRMADO CORRECTAMENTE +++++++++++++"
                    ]);
                    $transaccion = false;
                }
            }

            if ($tipopp == 0 && $contaux >= $lotes) {
                $contaux = 0;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "++++++++ LOTE " . $conttransaccion . " ROLLBACK CORRECTAMENTE +++++++++++++"
                ]);
                break; // Detener el foreach
            }
        }

        if ($tipopp == 0) {
            $database->rollback();
        } else {
            $database->commit();
        }
        sendSSEMessage('done', ['message' => "Proceso concluido correctamente. Se insertaron " . ($totalRows - $conterrors) . " registros. Registros no insertados: $conterrors; de un total de $totalRows"]);
    } catch (Exception $e) {
        if (isset($database)) {
            $database->rollback();
        }
        sendSSEMessage('error', ['message' => $e->getMessage()]);
    } finally {
        $database->closeConnection();
        unlink($fileTmpPath);
        //destruir las variables de session
        unset($_SESSION['excel_file_path']);
        unset($_SESSION['tipopp']);
        unset($_SESSION['inputs']);
        unset($_SESSION['selects']);
        unset($_SESSION['radios']);
        unset($_SESSION['archivo']);
    }

    exit;
}
