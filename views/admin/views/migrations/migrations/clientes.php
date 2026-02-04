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

use Micro\Models\Municipio;
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

        // Log para mostrar todo lo que trae en la sesión
        //   $logFile = __DIR__ . '/uploads/session_log.txt';
        // $logData = print_r($_POST, true);
        // file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
        // $logData = print_r($_SESSION, true);
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
            error_log("Archivo cargado y movido a: " . $destinationFilePath, true, __DIR__ . '/uploads/log.txt');
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
    sendSSEMessage('progress', [
        'row' => 0,
        'total' => 33,
        'message' => "Iniciando proceso de migración de datos..."
    ]);
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
            if (!isset($value["agencia"])) {
                throw new Exception("$key No existe la agencia");
            }
            if (!is_numeric($value["agencia"])) {
                throw new Exception("$key Número de agencia inválido");
            }
            if (is_null($value["nombre_corto"])) {
                throw new Exception("$key No existe un nombre corto [nombre_corto]");
            }
            $dpi_cleaned = str_replace([' ', '-'], '', $value['dpi']);

            if ($_SESSION['radios']['validateDPI'] == "yes") {
                $result = $database->selectColumns('tb_cliente', ['no_identifica'], 'no_identifica=? AND estado=1', [$dpi_cleaned]);
                if (!empty($result)) {
                    $conterrors++;
                    // $showmensaje = true;
                    // throw new Exception("Numero de documento de DPI ya existente, Favor verificar");
                    sendSSEMessage('progress', [
                        'row' => $key + 1,
                        'total' => $totalRows,
                        'message' => "ERROR: Procesando registro " . ($key + 1) . " de $totalRows => " . $value['nombre_corto'] . " DPI YA EXISTENTE"
                    ]);

                    continue;
                }
            }

            $result = $database->getAllResults("SELECT cli_gencodcliente(?) codcli", [intval($value['agencia'])]);
            if (empty($result)) {
                throw new Exception("Error en la generación de código de cuenta");
            }

            $codcli = $result[0]["codcli"];

            $departamento = substr($dpi_cleaned, -2);
            $municipio = substr($dpi_cleaned, -4);

            $dataMunicipio = Municipio::obtenerPorCodigo($municipio);
            // echo $municipio['nombre'];

            $tb_cliente = [
                "idcod_cliente" => $value['idcod_cliente'] ?? $codcli,
                // "idcod_cliente" => $codcli,
                "id_tipoCliente" => "NATURAL",
                "agencia" => str_pad($value['agencia'], 3, '0', STR_PAD_LEFT),
                "primer_name" => $value["primer_nombre"] ?? "",
                "segundo_name" => $value["segundo_nombre"] ?? "",
                "tercer_name" => $value["tercer_nombre"] ?? "",
                "primer_last" => $value["primer_apellido"] ?? "",
                "segundo_last" => $value["segundo_apellido"] ?? "",
                "casada_last" => $value["apellido_de_casada"] ?? "",
                "short_name" => $value["nombre_corto"] ?? "",
                "compl_name" => $value["nombre_completo"] ?? $value["nombre_corto"],
                "url_img" => "",
                "date_birth" => $value["fecha_de_nacimiento"] ?? "0000-00-00",
                "genero" => $value["genero"] ?? "X",
                "estado_civil" => $value["estado_civil"] ?? "X",
                "origen" => $value["origen"] ?? "",
                "pais_nacio" => $value["pais_nacio"] ?? "",
                "depa_nacio" => $departamento,
                "muni_nacio" => $municipio,
                "id_muni_nacio" => $dataMunicipio ? $dataMunicipio['id'] : null,
                "aldea" => $value["referencia_donde_reside"] ?? "",
                "type_doc" => $value["tipo_de_documento"] ?? "DPI",
                "no_identifica" => $dpi_cleaned ?? "",
                "pais_extiende" => $value["pais_extiende"] ?? "",
                "nacionalidad" => $value["nacionalidad"] ?? "GT",
                "depa_extiende" => $departamento,
                "muni_extiende" => $municipio,
                "id_muni_extiende" => $dataMunicipio ? $dataMunicipio['id'] : null,
                "otra_nacion" => $value["otra_nacion"] ?? "",
                "identi_tribu" => $value["identi_tribu"] ?? "NIT",
                "no_tributaria" => $value["nit"] ?? $dpi_cleaned ?? "",
                "no_igss" => $value["no_igss"] ?? "",
                "profesion" => $value["profesion"] ?? "",
                "Direccion" => $value["Direccion"] ?? "",
                "depa_reside" => $departamento,
                "muni_reside" => $municipio,
                "id_muni_reside" => $dataMunicipio ? $dataMunicipio['id'] : null,
                "aldea_reside" => $value["referencia_donde_reside"] ?? "",
                "tel_no1" => $value["tel_no1"] ?? "",
                "tel_no2" => $value["tel_no2"] ?? "",
                "area" => $value["area"] ?? "",
                "ano_reside" => $value["ano_reside"] ?? "",
                "vivienda_Condi" => $value["vivienda_Condi"] ?? "",
                "email" => $value["email"] ?? "",
                "relac_propo" => $value["relac_propo"] ?? "",
                "monto_ingre" => $value["monto_ingre"] ?? 0,
                "actu_Propio" => $value["actu_Propio"] ?? "1",
                "representante_name" => $value["representante_name"] ?? "",
                "repre_calidad" => $value["repre_calidad"] ?? "",
                "id_religion" => $value["id_religion"] ?? "1",
                "leer" => $value["leer"] ?? "Si",
                "escribir" => $value["escribir"] ?? "Si",
                "firma" => $value["firma"] ?? "Si",
                "cargo_grupo" => $identificadormigracion,
                "educa" => $value["nivel_academico"] ?? "",
                "idioma" => $value["idioma"] ?? "1",
                "Rel_insti" => $value["Rel_insti"] ?? "",
                "datos_Adicionales" => $value["datos_Adicionales"] ?? "",
                "Conyuge" => $value["Conyuge"] ?? "",
                "telconyuge" => $value["telefono_del_conyugue"] ?? "",
                "zona" => $value["zona"] ?? "",
                "barrio" => $value["barrio"] ?? "",
                "hijos" => $value["hijos"] ?? "0",
                "dependencia" => $value["dependencia"] ?? "0",
                "Nomb_Ref1" => $value["Nombre_de_referente1"] ?? "",
                "Nomb_Ref2" => $value["Nombre_de_referente2"] ?? "",
                "Nomb_Ref3" => $value["Nombre_de_referente3"] ?? "",
                "Tel_Ref1" => $value["Telefono_de_referente1"] ?? "",
                "Tel_Ref2" => $value["Telefono_de_referente2"] ?? "",
                "Tel_Ref3" => $value["Telefono_de_referente3"] ?? "",
                "PEP" => $value["PEP"] ?? "No",
                "CPE" => $value["CPE"] ?? "No",
                "control_interno" => $value["codigo_interno"] ?? "",
                "estado" => "1",
                "created_by" => $value["created_by"] ?? $idusuario,
                "fecha_alta" => $value["fecha_ingreso"] ?? $hoy2,
                "fecha_baja" => $value["fecha_baja"] ?? NULL,
                "fecha_mod" => $hoy2,
            ];

            $database->insert("tb_cliente", $tb_cliente);

            sendSSEMessage('progress', [
                'row' => $key + 1,
                'total' => $totalRows,
                'message' => "Procesando registro " . ($key + 1) . " de $totalRows => $codcli " . $value['short_name'] . $value['agencia']
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
