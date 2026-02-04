<?php
// esta es la copia de seguridad por si la cago tratando de ARREGALARLO 

session_start();
include '../../../../../includes/BD_con/db_con.php';
include './consulta.php';

//LIMITE DE TIEMPO 
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3000');
// ----------------------

mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
$hoy = date("Y-m-d");
//[[`finicio`,`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[]]
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

/* $mes = $selects[0]; $anio = $selects[1]; */
$DiasMes = DiasMes($selects[1], $selects[0]);
$fecha_inicio = $DiasMes['primer_dia'];
$fecha_fin        = $DiasMes['ultimo_dia'];

/* ============ TRAE TODOS LOS TIPOS DE COMPORTAMIENTOS DE CREDITO ============ */
$query = "SELECT * FROM " . $db_name_general . ".tb_condicioncredito where cod_crediref != ?;";
$response = executequery($query, ['iu'], $general);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$estados = $response[0];

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
+++++++++++++++++++++++++++++++++++++++ INFO DE LA INSTITUCION +++++++++++++++++++++++++++++++++++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

/* +++++++++++++++++++++++++++++++++++++++ INFO DE LA INSTITUCION +++++++++++++++++++++++++++++++++++++++++++ */
$idagencia = $_SESSION['id_agencia'];
global $queryInsti;
$response = executequery($queryInsti, [$idagencia], $conexion);

/* ------------- VALIDACIONES  ------------- */
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$info = $response[0];
if (count($info) == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion no válida para el Buró']);
    return;
}

// ***************** variables de fechas *****************
$fechas = [$fecha_inicio, $fecha_inicio, $fecha_fin, $fecha_fin, $fecha_fin]; //,$fecha_inicio,$fecha_fin
$fechas2 = [$fecha_fin, $fecha_inicio, $fecha_fin];
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
++++++++++++++++++++++ CONSULTA DE LOS CREDITOS VIGENTES HASTA LA FECHA FINAL+++++++++++++++++++++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$rango = "cremi.DFecDsbls <= '$fecha_fin'";
$qrydata = ["AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0", $rango]; // CREDITOS VIGENTES
$query = CreQUERY($qrydata, $db_name_general);
$response = executequery($query, $fechas, $conexion);

/* +++++++++++++++++++++++++++ VALIDACION DE DATOS +++++++++++++++++++++++++++++++++ */
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$vigentes = $response[0];  // CONSULTA DE LOS CREDITOS vigentes

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
++++++++++++++++++++++ CONSULTA DE LOS CREDITOS CANCELADOS EN EL RANGO DE FECHAS DADO+++++++++++++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$rango = "cremi.fecha_operacion BETWEEN '$fecha_inicio' AND '$fecha_fin'";
$qrydata = ["AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0", $rango]; // para CANCELADOS
$query = CreQUERY($qrydata, $db_name_general);
$response = executequery($query, $fechas, $conexion);
/* +++++++++++++++++++++++++++ VALIDACION DE DATOS +++++++++++++++++++++++++++++++++ */
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$cancelados = $response[0];  // CONSULTA DE LOS CREDITOS CANCELADOS

/* +++++++++++++++++++++++++++ VALIDACION DE DATOS +++++++++++++++++++++++++++++++++ */
$flag = ((count($vigentes) + count($cancelados)) > 0) ? true : false;
if (!$flag) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos en la fecha indicada']);
    return;
}

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
++++++++++++++++ CONSULTA DE LOS CLIENTES QUE ESTAN CON CREDITOS EN EL RANGO DE FECHAS DADO ++++++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
global $qryCli; // para CLIENTES
$response = executequery($qryCli, [$fecha_fin, $fecha_fin, $fecha_inicio, $fecha_fin], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$clientes = $response[0];
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
++++++++++ CONSULTA DE TODAS LAS GARANTIAS DE LOS CREDITOS QUE ESTAN EN EL RANGO DE FECHAS DADO ++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
global $qryGaranti;
$response = executequery($qryGaranti, [$fecha_fin, $fecha_fin, $fecha_inicio, $fecha_fin], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$garantias = $response[0];
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
+++++++++++++++++++++++++ CONSULTA DE LOS CLIENTES QUE ESTAN COMO FIADORES +++++++++++++++++++++++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
global $qryFiador;
$response = executequery($qryFiador, [$fecha_fin, $fecha_fin, $fecha_inicio, $fecha_fin], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$fiadores = $response[0];

switch ($tipo) {
    case 'txt':
        printpdf([$fecha_inicio, $fecha_fin], $info, $vigentes, $cancelados, $clientes, $garantias, $fiadores, $estados);
        break;
}

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
+++++++++++++++++++++++++++++++ FUNCION PARA LA GENERACION DE PDF ++++++++++++++++++++++++++++++++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
function printpdf($datos, $info, $vigentes, $cancelados, $clientes, $garantias, $fiadores, $estados)
{
    $codigoinstitucion = ($info[0]["codigo"]);
    $fechainicio = $datos[0];
    $fechafin    = $datos[1];

    $filename = "reporte_" . $fechainicio . 'CRD.txt';
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++ PRIMER APARTADO: SECCION A; REGISTRO DE IDENTIFICACION ++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $header = [
        'A',
        transformer($codigoinstitucion, 4, ' '),
        'A', //A: CREDITOS DE MICROFINANZAS
        date("Ymd", strtotime($fechafin))
    ];

    $contenido_a = implode('', $header);
    $contenido_a .= "\n";

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++ SEGUNDO APARTADO: SECCION B; REGISTRO DE IDENTIFICACION CREDITICIA +++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $archivo = fopen($filename, "w");
    fwrite($archivo, $contenido_a);

    $contenido_b = "";
    $contenido_c = "";
    $contenido_general = "";

    $i = 0;
    while ($i < count($vigentes)) {
        $cuenta = $vigentes[$i]['ccodcta'];
        $ccodcli = $vigentes[$i]['ccodcli'];
        $contenido_b = datoscreditos($cuenta, $vigentes, $garantias, $estados);
        $contenido_b .= "\n";
        fwrite($archivo, $contenido_b);

        $indexcli = array_search($ccodcli, array_column($clientes, 'idcod_cliente'));
        gen_cliente($indexcli, $clientes, $archivo, "D");

        //FIADOR DATOS GENERALES
        $indexgar = array_search($cuenta, array_column($garantias, 'id_cremcre_meta'));
        if ($indexgar !== false) {
            $coddescripcion = $garantias[$indexgar]['descripcionGarantia'];
            $codfiador = array_search($coddescripcion, array_column($fiadores, 'idcod_cliente'));
            if ($codfiador !== false) {
                gen_cliente($codfiador, $fiadores, $archivo, "F");
            }
        }

        //fwrite($archivo, "\n"); //ELIMINAR DESPUES DE COMPLETAR EL REPORTE
        $i++;
    }

    //CANCELADOS
    // fwrite($archivo, "CANCELADOS\n");
    $i = 0;
    while ($i < count($cancelados)) {
        $cuenta = $cancelados[$i]['ccodcta'];
        $ccodcli = $cancelados[$i]['ccodcli'];
        $contenido_b = datoscreditos($cuenta, $cancelados, $garantias, $estados);
        $contenido_b .= "\n";
        fwrite($archivo, $contenido_b);

        $indexcli = array_search($ccodcli, array_column($clientes, 'idcod_cliente'));
        gen_cliente($indexcli, $clientes, $archivo, "D");
        //FIADOR DATOS GENERALES
        $indexgar = array_search($cuenta, array_column($garantias, 'id_cremcre_meta'));
        if ($indexgar !== false) {
            $coddescripcion = $garantias[$indexgar]['descripcionGarantia'];
            $codfiador = array_search($coddescripcion, array_column($fiadores, 'idcod_cliente'));
            if ($codfiador !== false) {
                gen_cliente($codfiador, $fiadores, $archivo, "F");
            }
        }
        //fwrite($archivo, "\n"); //ELIMINAR DESPUES DE COMPLETAR EL REPORTE
        $i++;
    }

    /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
++++++++++++++++++++++++ INICIO DE CREACION DE ARCHIVO Y LLENADO DEL CONTENIDO RESUELTO ++++++++++++++++++
++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    fclose($archivo);

    ob_start();
    readfile($filename);
    $doc_data = ob_get_contents();
    ob_end_clean();

    unlink($filename); //ELIMINAR EL ARCHIVO TEMPORAL
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => $filename,
        'tipo' => "txt",
        'data' => "data:application/pdf;base64," . base64_encode($doc_data)
    );
    echo json_encode($opResult);
    return;
}

function gen_cliente($indice, $datos, $archivo, $VINCULO)
{
    $contenido_c = datosclientes($datos[$indice]['idcod_cliente'], $datos, $VINCULO);
    $contenido_c .= "\n";
    fwrite($archivo, $contenido_c);

    //CAMPOS OPCIONALES DIRECCIONES
    $direccion = direccion($datos[$indice], 'D');
    $contenido_d = ($direccion === false) ? '' : $direccion . "\n";
    fwrite($archivo, $contenido_d);

    //CAMPOS OPCIONALES TELEFONOS
    $tel = telefono($datos[$indice], 'C');
    $contenido_e = ($tel === false) ? '' : $tel . "\n";
    fwrite($archivo, $contenido_e);

    $tel = telefono($datos[$indice], 'D');
    $contenido_e = ($tel === false) ? '' : $tel . "\n";
    fwrite($archivo, $contenido_e);
}

function direccion($data, $uso)
{
    if (strlen(trim($data['Direccion'])) < 1) {
        return false;
    }
    $contenido = [
        'D',
        transformer(quitar_tildes(strtoupper($data['Direccion'])), 75, ' '),
        ($data['codigo_postal'] == "X") ? transformer(' ', 5, ' ') : transformer($data['codigo_postal'], 5, ' '),
        $uso
    ];
    return implode('', $contenido);
    // return  $contenido;
}
function telefono($data, $uso)
{
    //c :celular(tel1) d:domicilio(tel2)
    $tel = ($uso == "C") ? $data['tel_no1'] : $data['tel_no2'];
    if (strlen(trim($tel)) < 6) {
        return false;
    }
    $contenido = [
        'E',
        transformer($tel, 10, ' '),
        $uso,
    ];
    return implode('', $contenido);
}
function datosclientes($codcli, $datos, $vinculo)
{
    $indice = array_search($codcli, array_column($datos, 'idcod_cliente'));
    $nombre1 = trim(strtoupper($datos[$indice]['primer_name']));
    $nombre2 = trim(strtoupper($datos[$indice]['segundo_name'])) . ' ' . trim(strtoupper($datos[$indice]['tercer_name']));
    $nombre3 = trim(strtoupper($datos[$indice]['tercer_name']));

    $apellido1 = trim(strtoupper($datos[$indice]['primer_last']));
    $apellido2 = trim(strtoupper($datos[$indice]['segundo_last'] ?? ""));
    $apellidocasada = trim(strtoupper($datos[$indice]['casada_last'] ?? ""));

    $genero = $datos[$indice]['genero'];
    $estado_civil = $datos[$indice]['estado_civil'];
    $igss = $datos[$indice]['no_igss'];
    $nit = $datos[$indice]['no_tributaria'];
    $fecnac = $datos[$indice]['date_birth'];
    $nacionalidad = $datos[$indice]['nacionalidad'];
    $dpi = $datos[$indice]['no_identifica'];
    $codpostal = $datos[$indice]['municipio'];
    /* ------------------------------ VALIDACIONES NEGROY  ------------------------------------------ */
    // validacion si la cadena es mayor a 8 y menor a 20; si no que sea vacio
    if (!(strlen($igss) >= 8 && strlen($igss) <= 20)) {
        $igss = ' ';
    }
    if (!(strlen($nit) >= 8 && strlen($nit) <= 20)) {
        $nit = ' ';
    }
    if (!(strlen($dpi) >= 8 && strlen($dpi) <= 20)) {
        $dpi = ' ';
    }
    // v

    $contenido = [
        'C',
        'I', //PARA PERSONA INDIVIDUAL ES I, E PARA EMPRESAS O PERSONA JURIDICA
        $vinculo,
        transformer(quitar_tildes($apellido1), 25, ' '),
        transformer(quitar_tildes($nombre1), 25, ' '),
        transformer(quitar_tildes($apellido2), 25, ' '),
        transformer(quitar_tildes($nombre2), 25, ' '),
        transformer(quitar_tildes($apellidocasada), 25, ' '),
        (mb_strlen($genero) > 0) ? (($genero == 'F' || $genero == 'M') ? $genero : ' ') : ' ',
        (mb_strlen($estado_civil) > 0) ? substr($estado_civil, 0, 1) : ' ',
        transformer(' ', 15, ' '), //CEDULA
        transformer(' ', 20, ' '), //PASAPORTE
        transformer($igss, 20, ' '),
        transformer(' ', 10, ' '), //LICENCIA  ANTES ERAN 20 
        transformer($nit, 12, ' '),
        (validateDate($fecnac, 'Y-m-d')) ? transformer(date("Ymd", strtotime($fecnac)), 8, ' ') : transformer(' ', 8, ' '),
        (mb_strlen($nacionalidad) > 0) ? transformer($nacionalidad, 2, ' ') : 'GT',
        transformer($codcli, 20, ' '),
        transformer($codpostal, 5, ' '),
        transformer($dpi, 13, ' '),
    ];
    return implode('', $contenido);
}
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function datoscreditos($cuenta, $datos, $garantias, $tipoestados)
{
    $indice = array_search($cuenta, array_column($datos, 'ccodcta'));
    $indicegarantia = array_search($cuenta, array_column($garantias, 'id_cremcre_meta'));
    $tipocuenta = ($indicegarantia === false) ? '099' : $garantias[$indicegarantia]['codgarantia'];
    $status = statuscredito($datos[$indice]['atraso'], $tipoestados, $datos[$indice]['estado']);
    $saldo = ($datos[$indice]['ncapdes'] - $datos[$indice]['cappag']);
    $saldo = ($saldo > 0) ? $saldo : 0;
    $saldovencido = ($datos[$indice]['capcalafec'] - $datos[$indice]['cappag']);
    $saldovencido = ($saldovencido > 0 && $saldo > 0) ? $saldovencido : 0;
    $periodo = $datos[$indice]['periodo'];

    //Registro DE INFORMACION CREDITICI
    $contenido = [
        'B',         // TIPO DE REGISTRO, (1, B )
        $tipocuenta, // Subtipo , (3, 099)   
        transformer($cuenta, 20, ' '), // CODCTA (20, 0180010100000001)
        $status,     // Estatus, (1, 0)
        '320',       // MONEDA: 320 QUETZALES ; 840 DOLARES 
        transformer(' ', 50, ' '),     // OBSERVACIONES (50,VACIO)
        transformer($datos[$indice]['ncapdes'], 12, 0, 1),  // 
        transformer($saldo, 13, 0, 1), // Saldo Actual, (13,0000)
        transformer($saldovencido, 12, 0, 1), // 
        transformer($datos[$indice]['cuota_mes'], 12, 0, 1),
        $datos[$indice]['periodo'],
        $datos[$indice]['destino'],
    ];

    return implode('', $contenido);
}
function transformer($texto, $longitud, $caracter, $condi = 0)
{
    /* Con esta validacion, se eliminan los espacios mas grandes del tamaño dado */
    if (strlen($texto) > $longitud) {
        $texto = substr($texto, 0, $longitud);
    }
    /* Con esta validacion,  */
    $dire = STR_PAD_RIGHT;
    if ($condi == 1) {
        $dire = STR_PAD_LEFT;
        $texto = number_format($texto, 2, '.', '');
    }
    $cadena = str_pad($texto, $longitud, $caracter, $dire);
    return trim($cadena, "\n\r");
}

function statuscredito($atraso, $parametros, $statusmplus)
{
    if ($statusmplus == "F") {
        $estado = '0';
        if ($atraso > 0) {
            $fila = array_keys((array_filter($parametros, function ($var) use ($atraso) {
                return ($atraso >= $var['min_dia'] && $atraso <= $var['max_dia']);
            })));
            $estado = (count($fila) > 0) ? $parametros[$fila[0]]['cod_crediref'] : '0';
        }
    } else {
        $estado = 'X';
    }
    return $estado;
}

function quitar_tildes($texto)
{
    $no_acentos = array("á", "é", "í", "ó", "ú", "Á", "É", "Í", "Ó", "Ú", "ñ", "Ñ");
    $acentos = array("a", "e", "i", "o", "u", "A", "E", "I", "O", "U", "n", "N");
    $texto = str_replace($no_acentos, $acentos, $texto);
    return $texto;
}
