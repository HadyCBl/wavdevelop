<?php

ob_start();
session_start();

require __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../../src/funcphp/func_gen.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// session_start();
$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

if ($_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['excelFile']['tmp_name'];

    $inputFileType = 'Xlsx';

    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();

    $data = $sheet->toArray(null, true, true, false);

    //----------
    $headers = array_shift($data);
    $datos = [];

    // Procesar cada fila
    foreach ($data as $row) {
        $rowData = [];
        foreach ($headers as $index => $header) {
            // Usar el encabezado como clave y el valor de la celda correspondiente
            $rowData[$header] = $row[$index] ?? null;
        }
        $datos[] = $rowData;
    }



    $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

    try {
        $database->openConnection();
        $database->beginTransaction();
        foreach ($datos as $key => $value) {
            if (!isset($value["agencia"])) {
                throw new Exception("$key No existe la agencia");
            }
            if (!is_numeric($value["agencia"])) {
                throw new Exception("$key Numero de agencia inválido");
            }
            if (is_null($value["short_name"])) {
                throw new Exception("$key No existe un nombre corto [short_name]");
            }

            $result = $database->getAllResults("SELECT cli_gencodcliente(?) codcli", [$value['agencia']]);
            if (empty($result)) {
                throw new Exception("Error en la generacion de codigo de cuenta ");
            }

            $codcli = $result[0]["codcli"];

            $tb_cliente = array(
                "idcod_cliente" => $codcli,
                "id_tipoCliente" => "NATURAL",
                "agencia" => str_pad($value['agencia'], 3, '0', STR_PAD_LEFT),
                "primer_name" => $value["primer_name"] ?? "",
                "segundo_name" => $value["segundo_name"] ?? "",
                "tercer_name" => $value["tercer_name"] ?? "",
                "primer_last" => $value["primer_last"] ?? "",
                "segundo_last" => $value["segundo_last"] ?? "",
                "casada_last" => $value["casada_last"] ?? "",
                "short_name" => $value["short_name"] ?? "",
                "compl_name" => $value["compl_name"] ?? $value["short_name"],
                "url_img" => "",
                "date_birth" => $value["date_birth"] ?? "0000-00-00",
                "genero" => $value["genero"] ?? "X",
                "estado_civil" => $value["estado_civil"] ?? "X",
                "origen" => $value["origen"] ?? "",
                "pais_nacio" => $value["pais_nacio"] ?? "",
                "depa_nacio" => $value["depa_nacio"] ?? "",
                "muni_nacio" => $value["muni_nacio"] ?? "",
                "aldea" => $value["aldea"] ?? "",
                "type_doc" => $value["type_doc"] ?? "",
                "no_identifica" => $value["no_identifica"] ?? "",
                "pais_extiende" => $value["pais_extiende"] ?? "",
                "nacionalidad" => $value["nacionalidad"] ?? "GT",
                "depa_extiende" => $value["depa_extiende"] ?? "",
                "muni_extiende" => $value["muni_extiende"] ?? "",
                "otra_nacion" => $value["otra_nacion"] ?? "",
                "identi_tribu" => $value["identi_tribu"] ?? "",
                "no_tributaria" => $value["no_tributaria"] ?? "",
                "no_igss" => $value["no_igss"] ?? "",
                "profesion" => $value["profesion"] ?? "",
                "Direccion" => $value["Direccion"] ?? "",
                "depa_reside" => $value["depa_reside"] ?? "",
                "muni_reside" => $value["muni_reside"] ?? "",
                "aldea_reside" => $value["aldea_reside"] ?? "",
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
                // "cargo_grupo" => $value["cargo_grupo"] ??"",
                "educa" => $value["educa"] ?? "",
                "idioma" => $value["idioma"] ?? "1",
                "Rel_insti" => $value["Rel_insti"] ?? "",
                "datos_Adicionales" => $value["datos_Adicionales"] ?? "",
                "Conyuge" => $value["Conyuge"] ?? "",
                "telconyuge" => $value["telconyuge"] ?? "",
                "zona" => $value["zona"] ?? "1",
                "barrio" => $value["barrio"] ?? "",
                "hijos" => $value["hijos"] ?? "",
                "dependencia" => $value["dependencia"] ?? "",
                "Nomb_Ref1" => $value["Nomb_Ref1"] ?? "",
                "Nomb_Ref2" => $value["Nomb_Ref2"] ?? "",
                "Nomb_Ref3" => $value["Nomb_Ref3"] ?? "",
                "Tel_Ref1" => $value["Tel_Ref1"] ?? "",
                "Tel_Ref2" => $value["Tel_Ref2"] ?? "",
                "Tel_Ref3" => $value["Tel_Ref3"] ?? "",
                "PEP" => $value["PEP"] ?? "No",
                "CPE" => $value["CPE"] ?? "No",
                "control_interno" => $value["control_interno"] ?? "",
                "estado" => "1",
                "created_by" => $idusuario,
                // "updated_by" => 4,
                "fecha_alta" => $value["fecha_alta"] ?? $hoy2,
                // "fecha_baja" => NULL,
                "fecha_mod" => $hoy2,
            );
            echo "event: progress\n";
            echo 'data: {"row": ' . $key . ', "message": "Procesando registro ' . $key . '"}' . "\n\n";
            // echo 'Procesando registro ' . $key . '';
            flush(); // Enviar la respuesta en tiempo real
            $database->insert("tb_cliente", $tb_cliente);
            // echo "$key => CODIGO CARGADO " . $codcli . "<br>";

        }

        // $database->commit();
        $database->rollback();
        $mensaje = "Pr0sez0 conklusado kodectamente (is suseful)";
        $status = 1;
    } catch (Exception $e) {
        $database->rollback();
        $status = 0;
        $mensaje = $e->getMessage();
    } finally {
        $database->closeConnection();
    }

    echo json_encode(['status' => $status, 'message' => $mensaje]);
    echo "event: done\n";
    echo 'data: {"message": "Proceso completado."}' . "\n\n";
    flush();
} 
else {
    echo json_encode(['status' => false, 'message' => 'Error al cargar el archivo.']);
    echo "event: error\n";
    echo 'data: {"error": "Error al cargar el archivo."}' . "\n\n";
    flush();
}
