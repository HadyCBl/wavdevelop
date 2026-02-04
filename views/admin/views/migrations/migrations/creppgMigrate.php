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

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Functions\ValidadorCampos;

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excelFile']['tmp_name'];
        $fileName = $_FILES['excelFile']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Detectar el tipo de archivo basado en la extensión
        if ($fileExtension === 'json') {
            // Procesar archivo JSON
            $destinationFilePath = __DIR__ . '/uploads/' . $fileName;
            if (!file_exists(__DIR__ . '/uploads/')) {
                mkdir(__DIR__ . '/uploads/', 0777, true);
            }
            if (move_uploaded_file($fileTmpPath, $destinationFilePath)) {
                $_SESSION['json_file_path'] = $destinationFilePath;
                echo json_encode(['status' => 'success', 'message' => 'Archivo JSON cargado correctamente']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al mover el archivo JSON']);
            }
        } elseif (in_array($fileExtension, ['xls', 'xlsx'])) {
            // Procesar archivo Excel
            $destinationFilePath = __DIR__ . '/uploads/' . $fileName;
            if (!file_exists(__DIR__ . '/uploads/')) {
                mkdir(__DIR__ . '/uploads/', 0777, true);
            }
            if (move_uploaded_file($fileTmpPath, $destinationFilePath)) {
                $_SESSION['excel_file_path'] = $destinationFilePath;
                echo json_encode(['status' => 'success', 'message' => 'Archivo Excel cargado correctamente']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al mover el archivo Excel']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Formato de archivo no soportado']);
        }

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
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se cargó ningún archivo']);
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

    $tipopp = $_SESSION['tipopp'];

    try {

        if (isset($_SESSION['json_file_path'])) {
            // Procesar archivo JSON
            $fileTmpPath = $_SESSION['json_file_path'];
            if (!file_exists($fileTmpPath)) {
                throw new Exception("El archivo JSON no existe en la ruta especificada: $fileTmpPath");
            }
            $jsonContent = file_get_contents($fileTmpPath);
            $datos = json_decode($jsonContent, true);
        } elseif (isset($_SESSION['excel_file_path'])) {
            // Procesar archivo Excel
            $fileTmpPath = $_SESSION['excel_file_path'];
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);
            $headers = array_shift($data);
            $datos = [];

            foreach ($data as $row) {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $row[$index] ?? null;
                }
                $datos[] = $rowData;
            }
        } else {
            throw new Exception("No se encontró ningún archivo para procesar");
        }

        /**
         * DICCIONARIO DE CAMPOS ENVIADOS DESDE LA VISTA
         */
        // ['idcuenta1','idcuenta2','lotes','seguimiento'],[],['checkCuenta']

        if (!isset($_SESSION['inputs']['idcuenta1']) || !isset($_SESSION['inputs']['idcuenta2'])) {
            sendSSEMessage('error', ['message' => 'No se han enviado los campos de cuenta']);
            throw new Exception("No se han enviado los campos de cuenta.");
            exit;
        }

        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

        $database->openConnection();

        $transaccion = false;

        $contaux = 0;
        $conttransaccion = 0;

        $lotes = $_SESSION['inputs']['lotes'] ?? 1000;

        $seguimiento = $_SESSION['inputs']['seguimiento'] ?? 1;
        $contadorSeguimiento = 0;

        $campoCuentaAnfitrion = $_SESSION['inputs']['idcuenta1'];
        $campoCuentaDestino = $_SESSION['inputs']['idcuenta2'];

        $identificadormigracion = generarCodigoAleatorio();

        sendSSEMessage('progress', [
            'row' => 0,
            'total' => 0,
            'message' => "Iniciando el proceso de migración de datos..."
        ]);

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
             * y el campo destino es el campo que contiene la cuenta guardada en la tabla cremcre
             */
            /**
             * USO DE LA CLASE ValidadorCampos PARA VALIDAR LOS CAMPOS OBLIGATORIOS
             */
            $nombresCampos = [
                'dfecven' => 'Fecha de vencimiento',
                'ncapita' => 'Cuota de capital',
                $campoCuentaAnfitrion =>  'Cuenta de vinculacion',
            ];
            $validador = new ValidadorCampos($value, $nombresCampos);
            $camposAValidar = [
                'dfecven' => ['required', 'date'],
                'ncapita' => ['required', 'numeric'],
                $campoCuentaAnfitrion => ['required'],
            ];
            $errores = $validador->validar($camposAValidar);
            if ($validador->hayErrores()) {
                $conterrors++;
                foreach ($errores as $error) {
                    sendSSEMessage('progress', [
                        'row' => $key + 1,
                        'total' => $totalRows,
                        'message' => "ERROR: Procesando registro " . ($key + 1) . " de {$totalRows} => " . $error['mensaje']
                    ]);
                }
                // continue;
                throw new Exception("Error en la validación de campos obligatorios: ");
            }

            /**
             * VALIDACION DE CUENTA
             */
            $cuenta = $database->selectColumns("cremcre_meta", ["CCODCTA"], "$campoCuentaDestino=?", [$value[$campoCuentaAnfitrion]]);
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

            /**
             * INICIO DE CARGA DE DATOS
             */

            $codcuenta = $cuenta[0]["CCODCTA"];

            $Cre_ppg = [
                'ccodcta' => $codcuenta,
                'dfecven' => $value['dfecven'],
                'dfecpag' => "0000-00-00",
                'cestado' => 'X',
                'ctipope' => $value['ctipope'] ?? "",
                'cnrocuo' => $value['cnrocuo'] ?? "",
                'SaldoCapital' => $value['SaldoCapital'] ?? 0.00,
                'nmorpag' => $value['nmorpag'] ?? 0.00,
                'ncappag' => $value['ncappag'] ?? $value['ncapita'] ?? 0.00,
                'nintpag' => $value['nintpag'] ?? $value['nintere'] ?? 0.00,
                'OtrosPagosPag' => $value['OtrosPagosPag'] ?? 0.00,
                'ccodusu' => $value['ccodusu'] ?? $idusuario,
                'dfecmod' => $value['dfecmod'] ?? $hoy,
                'cflag' => $value['cflag'] ?? "N",
                'codigo' => $value['codigo'] ?? "",
                'creditosaf' => $value['creditosaf'] ?? 0.00,
                'saldo' => $value['saldo'] ?? $value['SaldoCapital'] ?? 0.00,
                'nintmor' => $value['nintmor'] ?? 0.00,
                'ncapita' => $value['ncapita'] ?? 0.00,
                'nintere' => $value['nintere'] ?? 0.00,
                'OtrosPagos' => $value['OtrosPagos'] ?? 0.00,
            ];



            $database->insert("Cre_ppg", $Cre_ppg);
            $contaux++;
            $contadorSeguimiento++;

            if ($contadorSeguimiento >= $seguimiento) {
                $contadorSeguimiento = 0;
                // Enviar mensaje de progreso al cliente
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "Procesando registro " . ($key + 1) . " de $totalRows con cuenta: $codcuenta y cuota: " . ($value['cnrocuo'] ?? "X")
                ]);
            }

            if ($tipopp == 1) {
                if ($contaux == $lotes || $key == array_key_last($datos)) {
                    $transaccion = true;
                }

                if ($transaccion) {
                    $database->commit();
                    $conttransaccion++;
                    $contaux = 0;
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
                break;
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
        // sendSSEMessage('error', ['message' => $e->getMessage()]);
        $errorDetails = [
            'message' => $e->getMessage(),
            // 'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => array_slice($e->getTrace(), 0, 3), // Primeros 3 elementos del stack trace
            'type' => get_class($e)
        ];

        sendSSEMessage('error', [
            'message' => $e->getMessage(),
            'details' => $errorDetails,
            'displayMessage' => "Error en línea {$e->getLine()}: {$e->getMessage()}"
        ]);
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

        //destruir las variables de session
        unset($_SESSION['excel_file_path']);
        unset($_SESSION['json_file_path']);
        unset($_SESSION['tipopp']);
        unset($_SESSION['inputs']);
        unset($_SESSION['selects']);
        unset($_SESSION['radios']);
        unset($_SESSION['archivo']);
    }

    exit;
}
