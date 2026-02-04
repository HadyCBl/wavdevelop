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

// Manejar la carga del archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    if ($_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['excelFile']['tmp_name'];
        $fileName = $_FILES['excelFile']['name'];
        if (isset($_POST['tipo'])) {
            $_SESSION['tipopp'] = $_POST['tipo'];
        }

        if (isset($_POST['inputs'])) {
            $_SESSION['inputs'] = $_POST['inputs'];
        }
        if (isset($_POST['selects'])) {
            $_SESSION['selects'] = $_POST['selects'];
        }
        if (isset($_POST['radios'])) {
            $_SESSION['radios'] = $_POST['radios'];
        }
        if (isset($_POST['archivo'])) {
            $_SESSION['archivo'] = $_POST['archivo'];
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

        $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

        $database->openConnection();

        $database->beginTransaction();
        $identificadormigracion = generarCodigoAleatorio();

        $totalRows = count($datos);
        $conterrors = 0;
        foreach ($datos as $key => $value) {
            // Validaciones básicas
            $codigoOficina = $value["ccodage"] ?? '001';

            $productosExistentes=$database->selectColumns("aprtip", ["ccodtip"]);
            $codigosExistentes = array_column($productosExistentes, 'ccodtip');
           
            $newcode = generarCodigoUnico($codigosExistentes);
            $ccodtip = $value["ccodtip"] ?? $newcode;

            if (!isset($value['nombre'])) {
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $value['nombre'] . " NOMBRE VACIO"
                ]);
                continue;
            }

            $ahomtip = array(
                "ccodage" => $codigoOficina,
                "ccodtip" => $ccodtip,
                "nombre" => $value['nombre'],
                "cdescripcion" =>  $value["cdescripcion"] ?? $value["nombre"],
                "tasa" => $value["tasa"] ?? 0.00,
                // "diascalculo" => $value["diascalculo"] ?? 365,
                "tipcuen" => $value["tipcuen"] ?? 'cr',
                "mincalc" => $value["mincalc"] ?? 0.00,
                // "mindepo" => $value["mindepo"] ?? 0.00,
                "numfront" => $value["numfront"] ?? 30,
                "front_ini" => $value["front_ini"] ?? 10,
                "numdors" => $value["numdors"] ?? 30,
                "dors_ini" => $value["dors_ini"] ?? 10,
                "correlativo" => $value["correlativo"] ?? 0,
                "xlibreta" => $value["xlibreta"] ?? 10,
                "ylibreta" => $value["ylibreta"] ?? 10,
                "id_cuenta_contable" => $value["id_cuenta_contable"] ?? 1,
                "cuenta_aprmov" => $value["cuenta_aprmov"] ?? 1,
                "estado" => 1,
                "isr" => $value["isr"] ?? 10,
                "created_by" => $idusuario,
                "created_at" => $hoy2,
            );

            $database->insert("aprtip", $ahomtip);

            sendSSEMessage('progress', [
                'row' => $key + 1,
                'total' => $totalRows,
                'message' => "Procesando registro " . ($key + 1) . " de $totalRows => $ccodtip " . $value['nombre'] . $codigoOficina
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
