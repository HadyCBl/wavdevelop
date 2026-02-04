<?php
/**
 * Este script maneja la carga de archivos Excel y su almacenamiento en un directorio permanente.
 * 
 * Funciones y variables principales:
 * 
 * - `sendSSEMessage($event, $data)`: Envía un mensaje SSE (Server-Sent Events) al cliente.
 * - `$_SESSION['id']`: ID del usuario almacenado en la sesión.
 * - `date_default_timezone_set('America/Guatemala')`: Establece la zona horaria predeterminada.
 * - `$_SERVER['REQUEST_METHOD']`: Verifica si la solicitud es de tipo POST.
 * - `$_FILES['excelFile']`: Verifica si el archivo Excel ha sido cargado.
 * - `$_POST['tipo']`: Tipo de operacion (proporcionado a través de POST) [1: Insertar-commit, 0: solo revisar-rollback]
 * - `$_SESSION['excel_file_path']`: Ruta del archivo Excel almacenada en la sesión.
 * 
 * Flujo del script:
 * 
 * 1. Inicia el almacenamiento en búfer de salida y la sesión.
 * 2. Requiere los archivos necesarios para la ejecución.
 * 3. Establece la zona horaria.
 * 4. Define la función `sendSSEMessage` para enviar mensajes SSE.
 * 5. Maneja la carga del archivo Excel:
 *    - Verifica si la solicitud es de tipo POST y si el archivo ha sido cargado correctamente.
 *    - Define un directorio permanente para almacenar el archivo.
 *    - Crea la carpeta `uploads` si no existe.
 *    - Mueve el archivo a la ubicación permanente.
 *    - Guarda la ruta del archivo en la sesión.
 *    - Devuelve una respuesta JSON indicando el estado de la operación.
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
 * Manejar SSE (Server-Sent Events) para procesar un archivo Excel y almacenar los datos en la base de datos.
 *
 * Este script escucha una solicitud GET con el parámetro 'listen' y procesa un archivo Excel cargado en la sesión.
 * Envía actualizaciones de progreso a través de SSE y maneja errores durante el proceso.
 *
 * @throws Exception Si ocurre un error durante el procesamiento del archivo Excel.
 *
 * @return void
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

        $identificadormigracion = generarCodigoAleatorio();

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

        sendSSEMessage('progress', [
            'row' => 0,
            'total' => 0,
            'message' => "INICIANDO, CODIGO DE MIGRACION: $identificadormigracion"
        ]);

        $totalRows = count($datos);
        $conterrors = 0;
        foreach ($datos as $key => $value) {
            // Validaciones básicas
            if (!isset($value["CODAgencia"])) {
                throw new Exception("$key No existe la agencia");
            }

            $codcliente = (is_null($value["CodCli"]) || $value['CodCli'] == '') ? substr($value['NAMES'], 0, 20) : $value['CodCli'];
            if (is_null($value["CCODPRD"]) || $value['CCODPRD'] == '') {
                throw new Exception("$key No existe un Codigo de producto [CCODPRD]");
            }
            if (is_null($value["CodAnal"]) || $value['CodAnal'] == '') {
                throw new Exception("$key No existe un Codigo de Analista [CodAnal]");
            }
            if (is_null($value["DFecDsbls"]) || $value['DFecDsbls'] == '') {
                throw new Exception("$key No existe una fecha de desembolso [DFecDsbls]");
            }
            if (is_null($value["DfecPago"]) || $value['DfecPago'] == '') {
                throw new Exception("$key No existe una fecha de primer pago [DfecPago]");
            }
            if (is_null($value["CtipCre"]) || $value['CtipCre'] == '' || (!in_array($value['CtipCre'], ['Flat', 'Franc', 'Germa', 'Amer', 'Flat']))) {
                throw new Exception("$key No existe un tipo de amortizacion válido [CtipCre]");
            }
            if (is_null($value["noPeriodo"]) || $value['noPeriodo'] == '' || $value['noPeriodo'] <= 0) {
                throw new Exception("$key El numero de periodo o plazo es inválido [noPeriodo]");
            }

            $result = $database->selectColumns('tb_cliente', ['no_identifica'], 'idcod_cliente=? AND estado=1', [$value["CodCli"]]);
            // if (empty($result)) {
            if (5 == 4) {
                $conterrors++;
                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $value['CodCli'] . " No existe ningun cliente con el codigo indicado"
                ]);
            } else {

                //GENERACION DE CODIGO DE CUENTA
                $tipoenti = $value["TipoEnti"] == "GRUP" ? '02' : '01';
                $result = $database->getAllResults("SELECT cre_crecodcta(?,?) ccodcta", [intval($value['CODAgencia']), $tipoenti]);
                if (empty($result)) {
                    throw new Exception("Error en la generación de código de cuenta");
                }
                $identi = $value['Dictamen'] ?? "x";

                $ccodcta = $result[0]["ccodcta"];
                $cremcre_meta = array(
                    "CCODCTA" => $value['CCODCTA'] ?? $ccodcta,
                    "CodCli" => $codcliente,
                    "CCODPRD" => $value['CCODPRD'],
                    "CODAgencia" => str_pad(intval($value['CODAgencia']), 3, '0', STR_PAD_LEFT),
                    "CodAnal" => $value['CodAnal'],
                    "Cestado" => $value['Cestado'] ?? "F",
                    "DfecSol" => $value['DfecSol'] ?? $value["DFecDsbls"],
                    "DFecAnal" => $value['DFecAnal'] ?? $value["DFecDsbls"],
                    "DFecApr" => $value['DFecApr'] ?? $value["DFecDsbls"],
                    "DsmblsAproba" => $value['DsmblsAproba'] ?? $value["DFecDsbls"],
                    "DFecDsbls" => $value["DFecDsbls"],
                    "ActoEcono" => $value["ActoEcono"] ?? "1",
                    // "CTipCon" => "A", //NO SE USA
                    "NMonCuo" => $value["NMonCuo"] ?? 0,
                    "CtipCre" => $value["CtipCre"],
                    "NtipPerC" => $value["NtipPerC"] ?? "1M",
                    "peripagcap" => $value["peripagcap"] ?? 1,
                    "afectaInteres" => $value["afectaInteres"] ?? 1,
                    "Cdescre" => $value["Cdescre"] ?? 1,
                    // "CAldea" => "", //NO SE USA
                    // "CodMuni" => "", //NO SE USA
                    // "NDiaGra" => 0, //NO SE USA
                    // "Plazo" => 0, //NO SE USA
                    "NDesApr" => $value["NDesApr"], //NO SE USA
                    "DFecVig" => $value['DFecVig'] ?? $value["DFecDsbls"],
                    "DFecVen" => $value['DFecVen'] ?? NULL,
                    "fecincobrable" => NULL,
                    // "NDesEje" => 0, //NO SE USA
                    // "NCapPag" => 0, //NO SE USA
                    // "AhoPrgPag" => 0, //NO SE USA
                    // "NIntPag" => 0, //NO SE USA
                    // "NMorPag" => 0, //NO SE USA
                    "NCapDes" => $value['NCapDes'] ?? 0,
                    // "CConDic" => "", //NO SE USA
                    // "NDiaAtr" => 0, //NO SE USA
                    // "CCalif" => "", //NO SE USA
                    // "DUltPag" => "0000-00-00", //NO SE USA
                    // "CMarJud" => "", //NO SE USA
                    "CSecEco" => $value['CSecEco'] ?? "C",
                    "CCodGrupo" => $value['CCodGrupo'] ?? $value["nomgrup"],
                    // "NAhoPrg" => 0, //NO SE USA
                    "CCodAho" => $identificadormigracion, //NO SE USA
                    // "Cpagare" => "", //NO SE USA
                    // "GarantiasCantidad" => 0.00, //NO SE USA
                    // "PlazoRefi" => 0, //NO SE USA
                    // "DfecIngreRefina" => "0000-00-00", //NO SE USA
                    // "DfecVenciRefina" => "0000-00-00", //NO SE USA
                    // "ActaOp" => "", //NO SE USA
                    // "SoliCop" => "", //NO SE USA
                    // "CODFIA" => "", //NO SE USA
                    "id_pro_gas" => $value['id_pro_gas'] ?? 0,
                    "moduloafecta" => $value['moduloafecta'] ?? 0,
                    "cntAho" => $value['cntAho'] ?? "",
                    "DfecPago" => $value['DfecPago'] ?? "0000-00-00",
                    "MontoSol" => $value['NCapDes'] ?? 0,
                    "MonSug" => $value['NCapDes'] ?? 0,
                    "TipoEnti" => $value['TipoEnti'] ?? "INDI",
                    // "CtipCuo" => 0, //NO SE USA
                    "cuotassolicita" => $value['noPeriodo'] ?? 0,
                    "noPeriodo" => $value['noPeriodo'] ?? 0,
                    // "Dictamen" => $value['Dictamen'] ?? "",
                    "Dictamen" => $identi,
                    "NIntApro" => $value['NIntApro'] ?? 0,
                    "NCiclo" => $value['NCiclo'] ?? 1,
                    "crecimiento" => $value['crecimiento'] ?? "",
                    "recomendacion" => $value['recomendacion'] ?? "",
                    "fecha_operacion" => $value['fecha_operacion'] ?? $value["DFecDsbls"],
                    // "P_ahoCr" => 0, // NO SE USA
                    "TipDocDes" => $value['TipDocDes'] ?? "E",
                    "id_rechazo_cred" => NULL,
                );

                $database->insert("cremcre_meta", $cremcre_meta);

                sendSSEMessage('progress', [
                    'row' => $key + 1,
                    'total' => $totalRows,
                    'message' => "Procesando registro " . ($key + 1) . " de $totalRows => $ccodcta " . $value['CodCli'] . " " . ($value['codigoso'] ?? "")
                ]);
            }
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
    }

    exit;
}