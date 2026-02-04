<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include '../../../../src/funcphp/fun_ppg.php';
require '../../../../fpdf/WriteTag.php';
// require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';

use Luecano\NumeroALetras\NumeroALetras as NumeroALetrasEsp;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Format;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];
$codcredito = $archivo[0];

// //SE CARGAN LOS DATOS
$strquery = "SELECT 
cm.CCODCTA AS ccodcta, 
cm.Cdescre AS Dest,
cm.DFecDsbls AS fecdesem, 
cm.DFecVen AS fecven,
cm.TipoEnti AS formcredito, 
cm.NCapDes AS montodesem, 
cm.noPeriodo AS cuotas, 
tbp.nombre AS frecuencia, 
cm.Dictamen AS dictamen,
cl.idcod_cliente AS codcli, 
cl.short_name AS nomcli,
cl.no_identifica AS numdpi, 
cl.Direccion AS direccioncliente,
cl.date_birth, 
cl.estado_civil, 
cl.profesion,
cl.firma AS firma,
cm.NIntApro AS tasaprod,
cl.aldea_reside,
cl.profesion,
tbc.id_garantia AS id_garantia,
cg.idTipoGa AS TipoGara,
cg.descripciongarantia AS DesGara,
cg.direccion AS direccionGara,
cg.valorComercial AS ValorCGara,
cg.montoAvaluo AS Montoavaluo,
cg.fechaCreacion AS creacionGara,
gtg.Tiposgarantia AS TipoGarantia,
dest.DestinoCredito AS Destino,
IFNULL(
    (SELECT dep.nombre 
     FROM tb_departamentos dep 
     WHERE dep.id = cl.depa_reside),
    '-'
) AS nomdep,
IFNULL(
    (SELECT mun.nombre 
     FROM tb_municipios mun 
     WHERE mun.id = cl.id_muni_reside),
    '-'
) AS nommun,
IFNULL(
    (SELECT dep.nombre 
     FROM tb_departamentos dep 
     WHERE dep.id = cl.depa_extiende),
    '-'
) AS nomdepext,
IFNULL(
    (SELECT SUM(ncapita) + SUM(nintere) 
     FROM Cre_ppg 
     WHERE ccodcta = cm.CCODCTA),
    0
) AS moncuota,
IFNULL(
    (SELECT SUM(OTR) 
     FROM CREDKAR 
     WHERE CCODCTA = cm.CCODCTA 
       AND CTIPPAG = 'D' 
       AND CESTADO != 'X'),
    0
) AS mongasto,
IFNULL(
    (SELECT CNUMING 
     FROM CREDKAR 
     WHERE CCODCTA = cm.CCODCTA 
       AND CTIPPAG = 'D' 
       AND CESTADO != 'X'),
    0
) AS nocheque,
IFNULL(
    (SELECT tbb.nombre 
     FROM tb_bancos tbb 
     INNER JOIN CREDKAR kk ON kk.CBANCO = tbb.id 
     WHERE CCODCTA = cm.CCODCTA 
       AND CTIPPAG = 'D' 
       AND CESTADO != 'X'),
    0
) AS nombanco,
CONCAT(usu.nombre, ' ', usu.apellido) AS analista, 
usu.dpi AS dpianal
FROM 
cremcre_meta cm
INNER JOIN 
tb_cliente cl ON cm.CodCli = cl.idcod_cliente
LEFT JOIN
tb_garantias_creditos tbc ON cm.CCODCTA = tbc.id_cremcre_meta
LEFT JOIN 
cli_garantia cg ON tbc.id_garantia = cg.idGarantia
LEFT JOIN 
 $db_name_general.tb_tiposgarantia gtg ON cg.idTipoGa = id_TiposGarantia
INNER JOIN 
tb_usuario usu ON cm.CodAnal = usu.id_usu
INNER JOIN 
$db_name_general.tb_destinocredito dest ON cm.Cdescre = dest.id_DestinoCredito
LEFT JOIN 
$db_name_general.tb_periodo tbp ON cm.NtipPerC = tbp.periodo
    WHERE 
        cm.Cestado = 'F' 
        AND cm.CCODCTA = '$codcredito'
    GROUP BY 
        CCODCTA";


$query = mysqli_query($conexion, $strquery);
$data[] = [];
$j = 0;
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $data[$j] = $fila;
    $j++;
}
//BUSCAR DATOS DE PLANES DE PLAGO
$querycreppg = "SELECT * FROM Cre_ppg cp WHERE cp.ccodcta = '" . $codcredito . "'";
$query = mysqli_query($conexion, $querycreppg);

$creppg = []; // Inicializar correctamente el array
$j = 0;

while ($fil = mysqli_fetch_array($query)) {
    $creppg[$j] = $fil;
    $creppg[$j]['totalcuota'] = $creppg[$j]['ncapita'] + $creppg[$j]['nintere'] + $creppg[$j]['OtrosPagos'];
    
    if ($j == 0) {
        $GLOBALS['first_day_pay'] = $creppg[$j]['dfecven']; // Asignar el valor de la primera posición
        
        // Suponiendo que dfecven tiene un formato 'YYYY-MM-DD'
        $fecha = new DateTime($GLOBALS['first_day_pay']);
        $day_only = $fecha->format('d'); // Extraer solo los días
        
        // Convertir el número del día a letras
        $GLOBALS['day_in_letters'] = convertirNumeroALetras($day_only);
    }

    $j++;
}

// $opResult = array(
//     'status' => 0,
//     'mensaje' => ' '. $GLOBALS['day_in_letters'].' '
//     // 'mensaje' => $strquery // Descomentar para depuración
// );
// echo json_encode($opResult);
// return;

// //BUSCAR DATOS DE GARANTIAS
$strquery = "SELECT 
clig.*, 
tc.short_name AS  name_fiador,
tc.date_birth AS fecha_cumple,
tc.estado_civil AS estado_civil,
tc.depa_nacio AS nodepa,
tc.muni_nacio AS nomuni,
tc.no_identifica AS dpi_fiador,
tc.profesion AS profe_fiador,
tc.Direccion AS direc_fiador,
tc.tel_no1 AS tel_fiador,
tc.firma AS firma_fiador,
gd.nombre AS departamento_fiador,
gm.nombre AS municipio_fiador
FROM 
tb_garantias_creditos tgc
INNER JOIN 
cli_garantia clig ON clig.idGarantia = tgc.id_garantia
LEFT JOIN 
tb_cliente tc ON clig.descripcionGarantia = tc.idcod_cliente
LEFT JOIN 
tb_departamentos gd ON tc.depa_nacio = gd.id
LEFT JOIN 
tb_municipios gm ON tc.id_muni_nacio = gm.id
WHERE 
tgc.id_cremcre_meta = '$codcredito'
AND clig.idTipoGa IN (1, 3, 12)
AND tc.short_name IS NOT NULL;";
$query = mysqli_query($conexion, $strquery);

// Almacenamiento de resultados en arrays individuales
$shortname_fiador_a = [];
$dpi_fiador_a = [];
$departamento_fiador_a = [];
$municipio_fiador_a = [];
$firma_fiador_a = [];
$direccion_fiador_a = [];
$segundo_fiador = 0;

while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $shortname_fiador_a[] = $fila['name_fiador'];
    $dpi_fiador_a[] = $fila['dpi_fiador'];
    $departamento_fiador_a[] = $fila['departamento_fiador'];
    $municipio_fiador_a[] = $fila['municipio_fiador'];
    $firma_fiador_a[] = $fila['firma_fiador'];
    $direccion_fiador_a[] = $fila['direccion'];
}
// Verificar si hay al menos un elemento en cada array
if (count($shortname_fiador_a) > 0) {
    $shortname_fiador = $shortname_fiador_a[0];
    $dpi_fiador = $dpi_fiador_a[0];
    $departamento_fiador = $departamento_fiador_a[0];
    $municipio_fiador = $municipio_fiador_a[0];
    $firma_fiador = $firma_fiador_a[0];
    $direccion_fiador = $direccion_fiador_a[0];

    $name_fiador =  $shortname_fiador;
    $dpi_f = $dpi_fiador;
    $dep_f = $departamento_fiador ;
    $muni_f = $municipio_fiador;
    $firm_f = $firma_fiador;
} else {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'Para generar este documento necesitas almenos un fiador '
        // 'mensaje' => $strquery // Descomentar para depuración
    );
    echo json_encode($opResult);
    return;
}


function first_fiador($name_fiador,$dpi_f,$dep_f,$municipio_fiador, $firma_fiador) {
    
    $GLOBALS['name_fiador'] = $name_fiador;
    $GLOBALS['dpi_f'] = $dpi_f;
    $GLOBALS['departamento_fiador'] = $dep_f;
    $GLOBALS['municipio_fiador'] = $municipio_fiador;
    $GLOBALS['firma_fiador'] = $firma_fiador;
    $GLOBALS['firma_fiador_ptm,'] = $firma_fiador;

}

// $opResult = array(
//     'status' => 0,
//     'mensaje' => $GLOBALS['dpi_fiador'] 
// );
// echo json_encode($opResult);
// return;




//asigna la segunda varibale 
if (count($shortname_fiador_a) > 1) {
    $shortname_fiador2 = $shortname_fiador_a[1];
    $dpi_fiador2 = $dpi_fiador_a[1];
    $departamento_fiador2 = $departamento_fiador_a[1];
    $municipio_fiador2 = $municipio_fiador_a[1];
    $firma_fiador2 = $firma_fiador_a[1];
    $direccion_fiador2 = $direccion_fiador_a[1];
    $segundo_fiador = 1;
} else{
    $segundo_fiador = 0;
    $shortname_fiador2  = 0;
    $dpi_fiador2 = 0;
    $departamento_fiador2 = 0;
    $municipio_fiador2  = 0;
    $firma_fiador2 = 0;
    $direccion_fiador2 = 0;

}

$GLOBALS['count_fiador']  = '';


    if ($segundo_fiador === 1) {
        $GLOBALS['segundo_fiador'] = 1;
        $GLOBALS['count_fiador'] = 'Nos constituimos como AVALISTAS del librador y garantizamos en forma mancomunada solidaria, renunciando a cualquier derecho de exclusión y orden que pudiere correspondernos';
    } elseif ($segundo_fiador === 0) {
        $GLOBALS['segundo_fiador'] = 0;
        $GLOBALS['count_fiador'] = 'Me constituyo como AVALISTA del librador y garantizo en forma mancomunada solidaria, renunciando a cualquier derecho de exclusión y orden que pudiere corresponderme';
    } else {
        $GLOBALS['count_fiador'] = 'no funciona el if';
    }



// Verificar si no se encontraron datos
if (empty($shortname_fiador)) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron garantias vinculadas con el crédito o/y Fiador'
        // 'mensaje' => $strquery // Descomentar para depuración
    );
    echo json_encode($opResult);
    return;
}
$GLOBALS['firma_fiador2Origin'] =0;

function data_secondf ($shortname_fiador2 , $dpi_fiador2 ,$departamento_fiador2 , $municipio_fiador2,$firma_fiador2,$direccion_fiador2){

    $GLOBALS['shortname_fiador2'] = $shortname_fiador2;
    $GLOBALS['dpi_fiador2'] = $dpi_fiador2;
    $GLOBALS['departamento_fiador2'] = $departamento_fiador2;
    $GLOBALS['municipio_fiador2'] = $municipio_fiador2;
    $GLOBALS['firma_fiador2Origin'] = $firma_fiador2;
    $GLOBALS['direccion_fiador2'] = $direccion_fiador2;
}

// $opResult = array(
//         'status' => 0,
//         'mensaje' => '  '.$firma_fiador2.'  '
//         'mensaje' => $strquery // Descomentar para depuración
//     );
//     echo json_encode($opResult);
//     return;

$GLOBALS['letras1_2'] = '';
$GLOBALS['letras2_2'] = '';
$GLOBALS['letras3_2'] = '';
$GLOBALS['letras4_2'] = '';
$GLOBALS['letras5_2'] = '';
$GLOBALS['letras6_2'] = '';
$GLOBALS['resultado_dpi_f_2'] = '';


function dpiletra3($dpi_fiador_letras) {
    // Extraer los segmentos del DPI en  4, 5, 4
    $segmento1 = substr($dpi_fiador_letras, 0, 4);
    $segmento2 = substr($dpi_fiador_letras, 4, 5);
    $segmento3 = substr($dpi_fiador_letras, 9, 4);

    // segmentos adicionales para ceros iniciales
    $ceros1 = ($segmento1[0] === '0') ? 'cero' : '';
    $ceros2 = ($segmento2[0] === '0') ? 'cero' : '';
    $ceros3 = ($segmento3[0] === '0') ? 'cero' : '';

    $GLOBALS['letras1_2'] = convertirNumeroALetras($segmento1);
    $GLOBALS['letras2_2'] = convertirNumeroALetras($segmento2);
    $GLOBALS['letras3_2'] = convertirNumeroALetras($segmento3);
    $GLOBALS['letras4_2'] = $ceros1;
    $GLOBALS['letras5_2'] = $ceros2;
    $GLOBALS['letras6_2'] = $ceros3;

    // Concatenar los resultados
    $resultado_dpi_2 = ($ceros1 ? $ceros1 . " " : "") . $GLOBALS['letras1_2'] . ", " . 
    ($ceros2 ? $ceros2 . " " : "") . $GLOBALS['letras2_2'] . ", " . 
    ($ceros3 ? $ceros3 . " " : "") . $GLOBALS['letras3_2'];

    // Asignar a la variable global
    $GLOBALS['resultado_dpi_f_2'] = $resultado_dpi_2;
}

$GLOBALS['letras1_4'] = '';
$GLOBALS['letras2_4'] = '';
$GLOBALS['letras3_4'] = '';
$GLOBALS['letras4_4'] = '';
$GLOBALS['letras5_4'] = '';
$GLOBALS['letras6_4'] = '';
$GLOBALS['resultado_dpi_f_4'] = '';

function dpiletra4($dpi) {
    // Remover cualquier espacio en blanco del DPI
    $dpi = str_replace(' ', '', $dpi);

    // Extraer los segmentos del DPI en 4, 5, 4
    $segmento1 = substr($dpi, 0, 4);
    $segmento2 = substr($dpi, 4, 5);
    $segmento3 = substr($dpi, 9, 4);

    // Verificar y manejar ceros iniciales
    $ceros1 = ($segmento1[0] === '0') ? 'cero' : '';
    $ceros2 = ($segmento2[0] === '0') ? 'cero' : '';
    $ceros3 = ($segmento3[0] === '0') ? 'cero' : '';

    $GLOBALS['letras1_4'] = convertirNumeroALetras($segmento1);
    $GLOBALS['letras2_4'] = convertirNumeroALetras($segmento2);
    $GLOBALS['letras3_4'] = convertirNumeroALetras($segmento3);
    $GLOBALS['letras4_4'] = $ceros1;
    $GLOBALS['letras5_4'] = $ceros2;
    $GLOBALS['letras6_4'] = $ceros3;

    // Concatenar los resultados
    $resultado_dpi_4 = ($ceros1 ? $ceros1 . " " : "") . $GLOBALS['letras1_4'] . ", " . 
                       ($ceros2 ? $ceros2 . " " : "") . $GLOBALS['letras2_4'] . ", " . 
                       ($ceros3 ? $ceros3 . " " : "") . $GLOBALS['letras3_4'];

    // Asignar a la variable global
    $GLOBALS['resultado_dpi_f_4'] = $resultado_dpi_4;
}

function convertirNumeroALetras($numero) {
    $unidades = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
        'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'
    ];
    $decenas = [
        '', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'
    ];
    $centenas = [
        '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
        'seiscientos', 'setecientos', 'ochocientos', 'novecientos'
    ];

    if ($numero == '0') {
        return 'cero';
    }

    $letras = '';
    $numero = intval($numero);

    if ($numero >= 1000) {
        $miles = floor($numero / 1000);
        $numero = $numero % 1000;
        if ($miles == 1) {
            $letras .= 'mil ';
        } else {
            $letras .= convertirNumeroALetras($miles) . ' mil ';
        }
    }

    if ($numero >= 100) {
        $cientos = floor($numero / 100);
        $numero = $numero % 100;
        $letras .= $centenas[$cientos] . ' ';
    }

    if ($numero >= 20) {
        $dec = floor($numero / 10);
        $unidad = $numero % 10;
        if ($unidad == 0) {
            $letras .= $decenas[$dec] . ' ';
        } else {
            $letras .= $decenas[$dec] . ' y ' . $unidades[$unidad] . ' ';
        }
    } else if ($numero > 0) {
        $letras .= $unidades[$numero] . ' ';
    }

    return trim($letras);
}

///////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////
//////////////////---Variables Globales de firma--////////////////////
///////////////////innecesario pero funcional xd  ////////////////////
//////////////////////////////////////////////////////////////////////

if ($firma_fiador === 'Si') {
    $firma_fiador_k = 1;
} elseif ($firma_fiador === 'No') {
    $firma_fiador_k = 0;
}else {
    # code...
}

if ($firma_fiador_k === 1) {
    $GLOBALS['firma_fiador_texto_0'] = ' dejamos firma donde corresponde';
    $GLOBALS['firma_fiador2_texto'] = ' dejo mi firma donde corresponde, ruego';
    $GLOBALS['banderafirma2'] = 1;
    $GLOBALS['banderafirma'] = 1;
} if ($firma_fiador=== 0) {
    $GLOBALS['firma_fiador_texto_0'] = ' por no saber firmar dejo la impresión dactilar de mi dedo pulgar derecho firmando a mi ruego la';
    $GLOBALS['firma_fiador2_texto'] = ' dejamos la impresión dactilar de nuestros dedos pulgares derechos donde corresponde';
    $GLOBALS['banderafirma2'] = 0;
    $GLOBALS['banderafirma'] = 0;
} 

// $opResult = array(
//     'status' => 0,
//     'mensaje' => ''.$GLOBALS['banderafirma'].''
// );
// echo json_encode($opResult);
// return;

function establecerFirma($firma_fiador_k) {
    
    if ($firma_fiador_k === 1) {
        $GLOBALS['firma_fiador_texto_0'] = ' dejamos firma donde corresponde';
        $GLOBALS['firma_fiador2_texto'] = ' dejo mi firma donde corresponde, ruego';
        $GLOBALS['banderafirma2'] = 1;
        $GLOBALS['banderafirma'] = 1;
    } if ($firma_fiador_k === 0) {
        $GLOBALS['firma_fiador_texto_0'] = ' por no saber firmar dejo la impresión dactilar de mi dedo pulgar derecho firmando a mi ruego la';
        $GLOBALS['firma_fiador2_texto'] = ' dejamos la impresión dactilar de nuestros dedos pulgares derechos donde corresponde';
        $GLOBALS['banderafirma2'] = 0;
        $GLOBALS['banderafirma'] = 0;
    } 
    
}


$cod_pagare = substr($codcredito, -3);
// //BUSCAR DATOS DE INSTITUCION
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins, MYSQLI_ASSOC)) {
    $info[$j] = $fil;
    $j++;
}


printpdf($info, $data, $creppg,$cod_pagare,$shortname_fiador,$dpi_fiador,$departamento_fiador,$municipio_fiador,$firma_fiador,$firma_fiador2, $segundo_fiador,$shortname_fiador2,$dpi_fiador2,$departamento_fiador2 ,$municipio_fiador2 ,$firma_fiador2,  );

function printpdf($info, $data, $creppg,$cod_pagare,$shortname_fiador,$dpi_fiador,$departamento_fiador,$municipio_fiador,$firma_fiador, $segundo_fiador,$shortname_fiador2,$dpi_fiador2,$departamento_fiador2 ,$municipio_fiador2 ,$firma_fiador2, $banderafirma )
{

    //FIN COMPROBACION
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '  ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];
    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends PDF_WriteTag
    {
        //atributos de la clase
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $rango;
        public $tipocuenta;
        public $saldoant;
        public $datos;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            $this->Image($this->pathlogoins, 10, 13, 33);
            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 12);
            // $this->Cell(0, 10, 'CONTRATO MUTUO CON GARANTIA', 'T', 1, 'C');
            // $this->Cell(0, 10, 'PRENDARIA', 'B', 1, 'C');
            // Salto de línea
            $this->Ln(22);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea = 30;
    $tamanofuente = 10;
    // $pdf->SetMargins(30, 25, 25);
    $pdf->SetFont($fuente, '', $tamanofuente);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    // Stylesheet

    $pdf->SetStyle("p", $fuente, "N", 11, "0,0,0", 0);
    $pdf->SetStyle("h1", $fuente, "N", 11, "0,0,0", 0);
    $pdf->SetStyle("a", $fuente, "BU", 11, "0,0,0");
    $pdf->SetStyle("pers", $fuente, "I", 0, "0,0,0");
    $pdf->SetStyle("place", $fuente, "U", 0, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", 0, "0,0,0");

    //DATOS AVAL1
    $nombreaval1 = $data[0]["analista"];
    $dpi1 = $data[0]["dpianal"];
    $dpiletra1 = dpiletra($dpi1);
    $dpi1 = dpiformat($dpi1);
    $direccion1 = " ";
    //DATOS AVAL2
    $dpi2 = $dpi_fiador;
    $dpi_fiador = $dpi_fiador;
    $dpiletra2 = dpiletra($dpi_fiador);

    // $dpiletra2 = dpiletra($dpi2);
    // $dpi2 = dpiformat($dpi2);
    $direccion2 = "_______________";

    //DATOS CLIENTE
    $nombrecliente = $data[0]["nomcli"];
    $firmacliente0 = $data[0]["firma"];
    $dpi = $data[0]["numdpi"];
    $dpiletras = dpiletra($dpi);
    $dpi = dpiformat($dpi);
    $direccion = $data[0]["direccioncliente"];
    $referencia = $data[0]["aldea_reside"];
    $fechanacimiento = $data[0]["date_birth"];
    $edad = calcular_edad($fechanacimiento);
    $edadletra = numtoletrasv2($edad);

    $estadocivil = $data[0]["estado_civil"];
    $profesion = $data[0]["profesion"];
    $depadomicilio = $data[0]["nomdep"];
    $depaextiende = $data[0]["nomdepext"];
    $munidomicilio = $data[0]["nommun"];
    $TipoGarantia = $data[0]["TipoGarantia"];
    $DescripcionGara = $data[0]["DesGara"];
    $cli_firma = $data[0]["firma"];
    $TIPO = 'N/A';

    // $opResult = array(
    //     'status' => 0,
    //     'mensaje' => ''.$cli_firma.''
      
    // );
    // echo json_encode($opResult);
    // return;

    switch ($TipoGarantia) {
        case 'PERSONAL':
        case 'PRENDARIA':
        case 'DOCUMENTOS DESCONTADOS':
        case 'FACTORAJE':
        case 'OTROS':
            $TIPO = 'La Enajenacion';
            break;
        case 'HIPOTECARIA':
        case 'FIDUCIARIA-HIPOTECARIA':
        case 'HIPOTECARIA-PRENDARIA':
        case 'FIDUCIARIA':
            $TIPO = 'La Hipotecación';
            break;
        case 'OBLIGACIONES PROPIAS':
        case 'DOCUMENTOS POR COBRAR':
            $TIPO = 'El Cobro';
            break;
        default:
            $TIPO = 'La Enajenacion';
            break;
    }

    $firma_cli_k = 0;

    if ($firmacliente0 === 'Si') {
        $firma_cli_k = 1;
    } else {
        $firma_cli_k = 0;
    }
    
 
    
    function establecerFirmacli($cli_firma) {
        if ($cli_firma === 'Si') { // comparación, no asignación
            $GLOBALS['firma_cli_texto_0'] = ' dejo mi firma al pie de la misma';
        } else if ($cli_firma === 'No') { // comparación, no asignación
            $GLOBALS['firma_cli_texto_0'] = ' y por no saber firmar dejo la impresion dactilar de mi dedo pulgar derecho  ';
        }    
    }

    if ($cli_firma === 'Si') { // comparación, no asignación
        $GLOBALS['firma_cli_texto_0'] = ' dejo mi firma al pie de la misma';
    } else if ($cli_firma === 'No') { // comparación, no asignación
        $GLOBALS['firma_cli_texto_0'] = ' y por no saber firmar dejo la impresion dactilar de mi dedo pulgar derecho  ';
    }         

    //DATOS CREDITO
    $destinocredito = $data[0]["Destino"];
    $ccodcta = $data[0]["ccodcta"];
    $frecuencia = $data[0]["frecuencia"];
    $plazo = $data[0]["cuotas"];
    $plazoletras = numtoletrasv2($plazo);
    $moncuotas = round($data[0]["moncuota"]);
    $decimal = explode(".", $moncuotas);
    $res = isset($decimal[1]) ? " con " . $decimal[1] . "/100" : "";
    $mondecimal = numtoletrasv2($decimal[0]);
    $moncuotasletra = $mondecimal . " " . $res;
    $montogasto = round($data[0]["mongasto"]);
    $decimal = explode(".", $montogasto);
    $res = isset($decimal[1]) ? " con " . $decimal[1] . "/100" : "";
    $mondecimal = numtoletrasv2($decimal[0]);
    $gastoletra = $mondecimal . " " . $res;
    $fechadesembolso =date('d-m-Y', strtotime($data[0]["fecdesem"])) ;
    $fechavenorigin =date('d-m-Y', strtotime($data[0]["fecven"])) ;
    $fechaGara =date('d-m-Y', strtotime($data[0]["creacionGara"])) ;
    $fechaven = fechaletras($fechavenorigin);
    $fechaGarantia = fechaletras($fechaGara);
    $fechaletras = fechaletras($fechadesembolso);
    $monto = round($data[0]['montodesem'], 2);
    $decimal = explode(".", $monto);
    $res = isset($decimal[1]) ? " con " . $decimal[1] . "/100" : "";
    $mondecimal = numtoletrasv2($decimal[0]);
    $montoletras = $mondecimal . " " . $res;
    $tasa = $data[0]["tasaprod"];
    $tasaletra = numtoletrasv2($tasa);
    //DATOS CHEQUE
    $nombanco = $data[0]["nombanco"];
    $nocheque = $data[0]["nocheque"];

    $GLOBALS['fechadesembolsoOrigin'] =  $fechadesembolso;
    function fechadesm($fechadesembolso) {
        $GLOBALS['fechadesembolsoOrigin'] = $fechadesembolso;
    }


            ///////////////////////////////////////////////////////////////////////
            //////////////////////////////////////////////////////////////////////
            //////////////////---AQUI COMIENZA LA IMPRESION--/////////////////////
            //////////////////////////////////////////////////////////////////////
            //////////////////////////////////////////////////////////////////////

    encabezado($pdf, 125, $cod_pagare);
    //PARTE INICIAL
    $texto = limpiar(introduccion($nombrecliente, $dpi, $dpiletras, $edadletra, $depadomicilio, $depaextiende, $munidomicilio, $direccion, $montoletras, $monto, $nocheque, $nombanco, $destinocredito, $referencia , $profesion , $estadocivil, $ccodcta));
    $texto .= limpiar(puntoa1($plazoletras, $plazo));

    
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(6);
    //ESPACIO PARA CUADRITOS
    plandepagos($pdf, $plazo, $creppg);

    $texto = limpiar(puntoa2($fechaven, $tasaletra, $tasa, $gastoletra, $montogasto, $frecuencia));
    $texto .= limpiar(puntob($tasaletra, $tasa, $gastoletra, $montogasto, $frecuencia));
    $texto .= limpiar(puntosc2($fechadesembolso, $firma_cli_k));

    $pdf->Ln(6);
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
         //primera firma
         if ($cli_firma === 'Si') {
            $pdf->firmas(1, [$nombrecliente]);
            $pdf->Ln(10);
        } elseif ($cli_firma === 'No') {
            $pdf->Ln(15);
            $lineaarriba = "";
            $lineaabajo = "";
            for ($i = 0; $i < 5; $i++) {
                if ($i == 0) {
                    $lineaarriba = "T";
                }
                if ($i == 4) {
                    $lineaabajo = "B";
                }
                $pdf->Cell(75, 5, ' ', 0, 0, 'R');
                $pdf->Cell(40, 5, ' ', "$lineaabajo" . "RL$lineaarriba", 0, 'C');
                $pdf->Ln(5);
                $lineaarriba = "";
                $lineaabajo = "";
            }
            $pdf->Ln(5);
            $pdf->CellFit(190, 5, $nombrecliente, 0, 1, 'C', 0, '', 1, 0);
        } else {
           
        }
        

        ///////////////////
        ////HERE
        //////////////////`
        $GLOBALS['letra_1']  ='A';
        $GLOBALS['letra_2']  =0;
        if ($GLOBALS['segundo_fiador'] == 1) {
            $texto = segundofiador($shortname_fiador2, $segundo_fiador, $shortname_fiador2, $dpi_fiador2, $departamento_fiador2, $municipio_fiador2, $firma_fiador2);
            $pdf->Ln(5);

            $GLOBALS['letra_1']  = 'A' ;
            $GLOBALS['letra_2'] = 'B';
        } else {
            $GLOBALS['letra_1']  = 'A' ;
            $GLOBALS['letra_2'] = 'A';
            $texto = '';
        }
    //PARTE FINAL
    $texto .= limpiar(puntofinal($nombreaval1, $dpiletra1, $dpi1, $direccion1, $dpiletra2, $dpi2, $direccion2, $fechadesembolso,$departamento_fiador,$municipio_fiador,$firma_fiador,$firma_fiador2, $segundo_fiador,$GLOBALS['name_fiador']));
    $pdf->Ln(5);
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);
    $testigo = 'Testigo';



// Validaciones aval firma
if ($GLOBALS['banderafirma2'] === 1) {
    $pdf->firmas(2, [$shortname_fiador,$testigo]);
    $pdf->Ln(5);
} elseif ($GLOBALS['banderafirma2'] === 0)  { 
    $lineaarriba = "";
    $lineaabajo = "";
    for ($i = 0; $i < 5; $i++) {
        if ($i == 0) {
            $lineaarriba = "T";
        }
        if ($i == 4) {
            $lineaabajo = "B";
        }
        $pdf->Cell(28, 5, ' ', 0, 0, 'R');
        $pdf->Cell(40, 5, ' ', "$lineaabajo" . "RL$lineaarriba", 0, 'C');
        $pdf->Cell(50, 5, ' ', 0, 0, 'R');
        $pdf->Cell(40, 5, ' ', "$lineaabajo" . "RL$lineaarriba", 0, 'C');
        $pdf->Ln(5);
        $lineaarriba = "";
        $lineaabajo = "";
    }
    $pdf->CellFit(100, 5, $shortname_fiador, 0, 1, 'C', 0, '', 1, 0);
    $pdf->Ln(-5);
    $pdf->CellFit(150, 5, $testigo, 0, 1, 'R', 0, '', 1, 0);
}else {
    # code...
}

if (isset($GLOBALS['firma_fiador2Origin']) && $GLOBALS['firma_fiador2Origin'] === 'Si') { 
    $pdf->firmas(1, [$GLOBALS['shortname_fiador2']]);
    $pdf->Ln(5);
} elseif (isset($GLOBALS['firma_fiador2Origin']) && $GLOBALS['firma_fiador2Origin'] === 'No') { 
    $pdf->Ln(15);
    $lineaarriba = "";
    $lineaabajo = "";
    for ($i = 0; $i < 5; $i++) {
        if ($i == 0) {
            $lineaarriba = "T";
        }
        if ($i == 4) {
            $lineaabajo = "B";
        }
        $pdf->Cell(75, 5, ' ', 0, 0, 'R');
        $pdf->Cell(40, 5, ' ', "$lineaabajo" . "RL$lineaarriba", 0, 'C');
        $pdf->Ln(5);
        $lineaarriba = "";
        $lineaabajo = "";
    }
    $pdf->Ln(5);
    $pdf->CellFit(190, 2, $GLOBALS['shortname_fiador2'], 0, 1, 'C', 0, '', 1, 0);
} else {
}

    $pdf->Ln(25);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Pagare-",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}


function encabezado($pdf, $nopagare,$cod_pagare)
{
    $pdf->Ln(-23);
    $pdf->Cell(0, 10, decode_utf8('PAGARÉ NO: ') . $cod_pagare, '', 1, 'C');
    $pdf->Cell(0, 10, 'Libre de Protesto', '', 1, 'C');
    $pdf->Ln(3);
}
function introduccion($name, $dpi, $dpiletra, $edadletra, $depadomicilio, $depaextiende, $munidomicilio, $direccion, $montoletra, $monto, $nocheque, $nombanco, $destinocredito, $referencia, $profesion, $estadocivil, $ccodcta )

{
    dpiletra4($dpi);
    if (empty($referencia)) {
        $referencia = 'sin referencia';
    }
    $data = decode_utf8('<p>Yo: ' . $name . ' de ' . $edadletra . ' años de edad, ' . $estadocivil . '(A) , Guatemalteco (a), ' . $profesion . ', con domicilio en el departamento 
    de ' . $depaextiende . ', me identifico con Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) 
    números: ' . $GLOBALS['resultado_dpi_f_4']. ' (' . $dpi . '), extendido por el Registro Nacional de las Personas de la República de Guatemala.<p>');

    $data .= decode_utf8('<p>En adelante denominado "la parte deudora o librador", y lugar para recibir comunicaciones y/o notificaciones que 
    para efectos de este título será en ' . $direccion . ' jurisdicción del municipio de ' . $munidomicilio . ' departamento de ' . $depadomicilio . '; 
    referencia: ' . $referencia . '. Por el presente <vb>PAGARÉ</vb> libre de protesto, prometo pagar incondicionalmente a la orden o endoso de <vb>"Cooperativa Integral de Ahorro y Crédito, Alianza
    y Valores de Guatemala, Responsabilidad Limitada)"</vb> la que podrá abreviarse <vb>"ADG, R.L"</vb>, en adelante llamada "Acreedor y/o entidad Acreedora", la suma 
    de ' . $montoletra . ' (Q ' . number_format($monto) . ') exactos, los que recibo a través del cheque número ' . $nocheque . ', del Banco ' . $nombanco . ', Sociedad Anónima, de la cual me 
    declaro liso y llano deudor, a su vez declaro bajo juramento de ley que utilizaré este financiamiento única y exclusivamente para: ' . $destinocredito . ', 
    el pago de la suma adeudada lo haré bajo las siguientes condiciones:</p>');
    return $data;
}


function puntoa1($plazoletras, $plazo)
{
    $data = decode_utf8('<p><vb>a. DEL PLAZO Y FORMA DE PAGO:</vb></p>');
    $data .= decode_utf8('<p>Me obligo a pagar la suma adeudada de este título en el plazo de ' . $plazoletras . ' meses (' . $plazo . '), 
    contados  a partir de la presente fecha, cantidad que pagaré sin necesidad de previo cobro o requerimiento, mediante el pago de amortizaciones 
    mensuales y consecutivas de conformidad a la siguiente tabla de amortizaciones:</p>');
    return $data;
}

function puntoa2($fechaven)
{
    $data = decode_utf8('<p>Cuotas que incluyen capital e intereses, cuyo monto se obtiene de acuerdo a la fórmula que se utiliza para calcular 
    las cuotas aprobadas por el sistema financiero del país, que conozco y acepto plenamente, cada una se harán efectivas dentro de los primeros 
    '. $GLOBALS['day_in_letters'].' días calendario de cada mes, y el saldo al vencimiento del plazo, que principiarán a hacerse efectivas a partir del día de hoy culminando 
    el ' . $fechaven . '. Todo pago lo hare en efectivo y al contado en moneda de curso legal en las oficinas centrales de 
    la entidad acreedora, situada en la 02 Calle  6-041 zona 3 Barrio Patacabaj ,Tecpan Guatemala, Chimaltenango, de preferencia, y hago constar que el lugar descrito lo conozco plenamente, o a través de depósito Bancario de la 
    entidad acreedora cuenta monetaria número <vb>3374019571 </vb> a nombre de ADG R.L., y/o cuenta Monetaria 
    No. <vb>3374020370</vb>; a nombre de ADG R.L./FONDOS PROPIOS, ambas del Banco de Desarrollo Rural, Sociedad Anónima.</p>');

    return $data;
}

function puntob($tasaletra, $tasa, $montogastoletra, $montogasto,$frecuencia)
{
    $data = ('<p><vb>b. INTERESES:</vb></p>');

    $data .= decode_utf8('<p>Reconozco y me obligo a pagar los intereses calculados bajo la tasa del cuatro porciento  (4 %), 
    '. $frecuencia .' dicha tasa de interés es pactada por ambas partes. La tasa del interés en la presente operación corresponderá exactamente con la 
    tasa de interés nominal anteriormente indicada y aceptada, siempre y cuando los abonos a capital y pagos de intereses se realicen exactamente 
    en la forma y tiempo aquí establecido. La parte Deudora estará obligada al pago de la nueva tasa desde la fecha en que cobre vigencia a 
    disposición de la resolución de la entidad Administrativa de la entidad acreedora, para aumentarla o reducirla. En ningún caso la variación 
    de la tasa de interés constituye novación. Los intereses se liquidarán y pagarán en cada amortización mensual. La falta de pago de los intereses y 
    de capital en las fechas pactadas facultará al acreedor, para cobrar un recargo sobre intereses y capital en mora del ocho por ciento (8%) mensual 
    sobre cuota o cuotas atrasadas cuantificando de mes a mes la que es aceptada por la parte deudora. b.1): Gastos administrativos comisiones y 
    descuentos. El deudor (a) desde ya acepta y autoriza el descuento por gastos administrativos, comisiones y manejo de cuenta de un 
    monto de ' . $montogastoletra . ' quetzales exactos (Q.' . $montogasto . ') ) equivalentes al 4% del total otorgado. </p>');

    $data .= decode_utf8('<p><vb>c. ACEPTACION Y OBLIGACION DE LA PARTE DEUDORA:</vb></p>');

    $data .= decode_utf8('<p>La entidad acreedora podrá dar por vencido el plazo de este título en forma anticipada y exigir ejecutivamente el pago 
    total del saldo adeudado, en los siguientes casos: a.1) Si no se cumple cualesquiera de las obligaciones aquí contraídas y las que se establecen 
    en las leyes de la República de Guatemala y las que rigen a la entidad acreedora; a.2) Si se dictare mandamiento de embargo en contra del deudor 
    o del avalista(s); a.3) Si dejare de pagar puntualmente una sola de las amortizaciones al capital y los respectivos intereses; y a.4) Si la 
    entidad acreedora comprobare que se utilizó el financiamiento para fines distintos a lo antes consignado, b) Renuncio al fuero de mi respectivo 
    domicilio; me someto y sujeto a la jurisdicción de los Tribunales que elija La entidad acreedora; y éste puede utilizar a su elección y para el 
    caso de ejecución el procedimiento y esencia de este título de crédito, y/o el Código Procesal Civil y Mercantil y/o Código de Comercio, así como 
    para señalar los bienes objeto de embargo, secuestro, depósito e intervención, sin sujetarse a orden legal alguno, c) Acepto como buenas y exactas 
    las cuentas que la entidad acreedora formule acerca de este título y como líquido, exigible y de plazo vencido la cantidad que se exija, d) Acepto 
    que se tenga como válidas y bien hechas legalmente las comunicaciones y/o notificaciones que se realicen y/o dirijan al lugar indicado como 
    domicilio, a no ser que notifique por escrito a la entidad acreedora, de cualquier cambio en la misma y que obre en su poder aviso de recepción 
    de la entidad acreedora de lo contrario serán bien hechas las que ahí me realicen, e) Acepto todos los gastos que se ocasione o motive este 
    negocio en el que incurra la entidad acreedora, directa o indirectamente son por mi cuenta inclusive lo de cobranza judicial o extrajudicial 
    honorarios de abogado, f) Acepto que para el caso de ejecución, la entidad acreedora no está obligada a prestar fianza o garantía alguna, 
    exoneración que se hará extensiva a los depositarios e interventores nombrados, no quedando el acreedor responsable por las actuaciones de 
    éstos, y que para el caso de remate sirva de base el valor de los bienes embargados o el monto total de la demanda incluyendo intereses y 
    costas, a elección de la entidad acreedora, garantizando la presente obligación con todos mis bienes presentes y futuros.');
    return $data;
}



$ejecutado_puntosc2 = false;

function puntosc2($fechadesembolso, $firma_cli_k) { 
    establecerFirmacli($firma_cli_k);
    
    global $ejecutado_puntosc2;

    if (!$ejecutado_puntosc2) {
        $data = decode_utf8('g) Acepto que este título es cedible o negociable, mediante simple endoso; sin necesidad previa o posterior aviso 
        o notificación; h) Expresamente dejo constancia que todos y cada uno de los datos que he proporcionado a la entidad acreedora, en la 
        inexactitud o falta de veracidad en los mismos que determine deberá tenerse con una acción dolosa de mi parte generadora de prejuicios 
        a su patrimonio y susceptible del ejercicio de acción penal que en derecho corresponda a esta última; i) Renuncio expresamente a los 
        derechos que pudieren conferirme las leyes vigentes o que en el futuro entraren en vigor y que pudieren permitirme cumplir las obligaciones 
        contraídas en este documento en forma distinta a la pactada, cuyo contenido declara conocer y entender, '. $GLOBALS['firma_cli_texto_0'] .' </p>');
        $data .= decode_utf8('<p>Lugar y fecha de Emisión:</p>');
        $data .= decode_utf8('<p>02 Calle  6-041 zona 3 Barrio Patacabaj ,Tecpan Guatemala, Chimaltenango ' . $fechadesembolso . ' </p>');
        $ejecutado_puntosc2 = true;
        return $data;
    }

    return ''; // Devolver cadena vacía si ya se ejecutó puntosc2
}





function segundofiador(){
    data_secondf ( $GLOBALS['shortname_fiador2'],$GLOBALS['dpi_fiador2'], $GLOBALS['departamento_fiador2'],$GLOBALS['municipio_fiador2'],$GLOBALS['firma_fiador2'],$GLOBALS['direccion_fiador2'] );
    dpiletra3($GLOBALS['dpi_fiador2']);
    
    $data = decode_utf8('<p><vb>VALIDO POR AVAL DEL LIBRADOR</vb></p>');

    $data .= decode_utf8('<p>'.$GLOBALS['letra_1'] .')Yo '.$GLOBALS['shortname_fiador2'].', me identifico con el Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) '.$GLOBALS['dpi_fiador2'].'  ('.$GLOBALS['resultado_dpi_f_2'].') extendido por el Registro Nacional de las Personas de la República de Guatemala, con domicilio en el departamento de '.$GLOBALS['departamento_fiador2'].' y residencia en '.$GLOBALS['direccion_fiador2'].', jurisdicción del municipio de '.$GLOBALS['municipio_fiador2'].' Guatemala. </p>');
    return $data;
}

function puntofinal($firma_fiador, $segundo_fiador) {
    // Llamada a la función para establecer el valor de count_fiador


    fechadesm($GLOBALS['fechadesembolsoOrigin']);
    establecerFirma($firma_fiador);
    dpiletra2($GLOBALS['dpi_f']);
    first_fiador($GLOBALS['name_fiador'], $GLOBALS['dpi_f'], $GLOBALS['departamento_fiador'], $GLOBALS['municipio_fiador'], $GLOBALS['firma_fiador']);
    
    // Uso de la variable global firma_fiador_texto_0
    establecerFirma($firma_fiador);
    
    $data = decode_utf8('<p><vb>VALIDO POR AVAL DEL LIBRADOR</vb></p>');
    $data .= decode_utf8('<p>'.$GLOBALS['letra_2'].') Yo '.$GLOBALS['name_fiador'].', me identifico con el Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) número '.$GLOBALS['dpi_f'].' , ('.$GLOBALS['resultado_dpi_f'].') extendido por el Registro Nacional de las Personas de la República de Guatemala, con domicilio en el departamento de '.$GLOBALS['departamento_fiador'].' y residencia en '.$GLOBALS['municipio_fiador'].', jurisdicción del municipio de Tecpán Guatemala. </p>');
    $data .= decode_utf8('<p>'.$GLOBALS['count_fiador'].' , el total del pago del anterior pagaré y nos obligamos a pagar el título de crédito en su totalidad bajo las mismas condiciones aceptadas por el librador, las cuales declaramos que conocemos, entendemos y aceptamos expresamente sin reserva alguna de todas las obligaciones aceptadas por el librador en este título, renunciando al fuero de nuestro domicilio, sometiéndonos a los tribunales que elija la entidad acreedora y señalando como lugar para recibir comunicaciones o notificaciones el (los) domicilio (s) antes indicado (s),obligándonos a comunicar de inmediato al acreedor de cualquier cambio de la misma, la prueba de la comunicación corre a nuestro cargo, aceptando para el caso de no dar este aviso como válida cualquier notificación que se nos haga en la dirección que hemos señalado en este título, desde ya aceptamos y respondemos con nuestros bienes presentes y futuros, y para lo cual signamos la presente y '. $GLOBALS['firma_fiador2_texto'].' , más no así yo '.$GLOBALS['name_fiador'].' '. $GLOBALS['firma_fiador_texto_0'].' a la señor(a)_________________________ (testigo), quien se identifica con el Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) número ____________________ extendido por el Registro Nacional de las Personas de la República de Guatemala, quien es testigo investida con las formalidades de ley.</p>');
    $data .= decode_utf8('<p>Lugar y fecha</p>');
    $data .= decode_utf8('<p>02 Calle 6-041 zona 3 Barrio Patacabaj, Tecpán Guatemala, Chimaltenango '.$GLOBALS['fechadesembolsoOrigin'].' </p>');
    $data .= decode_utf8('            ');
    
    return $data;
}
$GLOBALS['dpi_f'] = '';
$GLOBALS['letras1'] = '';
$GLOBALS['letras2'] = '';
$GLOBALS['letras3'] = '';
$GLOBALS['letras4'] = '';
$GLOBALS['letras5'] = '';
$GLOBALS['letras6'] = '';
$GLOBALS['resultado_dpi_f'] = '';

function dpiletra2($dpi_fiador)
{
    // Extraer los segmentos del DPI en el orden 4, 5, 4
    $segmento1 = substr($dpi_fiador, 0, 4);
    $segmento2 = substr($dpi_fiador, 4, 5);
    $segmento3 = substr($dpi_fiador, 9, 4);

    // Crear segmentos adicionales para ceros iniciales
    $ceros1 = ($segmento1[0] === '0') ? 'cero' : '';
    $ceros2 = ($segmento2[0] === '0') ? 'cero' : '';
    $ceros3 = ($segmento3[0] === '0') ? 'cero' : '';

    // Convertir cada segmento a su representación en letras
    $GLOBALS['letras1'] = convertirNumeroALetras($segmento1);
    $GLOBALS['letras2'] = convertirNumeroALetras($segmento2);
    $GLOBALS['letras3'] = convertirNumeroALetras($segmento3);
    $GLOBALS['letras4'] = $ceros1;
    $GLOBALS['letras5'] = $ceros2;
    $GLOBALS['letras6'] = $ceros3;

    // Concatenar los resultados
    $resultado_dpi = ($ceros1 ? $ceros1 . " " : "") . $GLOBALS['letras1'] . ", " . 
    ($ceros2 ? $ceros2 . " " : "") . $GLOBALS['letras2'] . ", " . 
    ($ceros3 ? $ceros3 . " " : "") . $GLOBALS['letras3'];

    // Asignar a la variable global
    $GLOBALS['resultado_dpi_f'] = $resultado_dpi;
}



function fechaletras($date)
{
    $date = substr($date, 0, 10);
    $numeroDia = date('d', strtotime($date));
    $mes = date('F', strtotime($date));
    $anio = date('Y', strtotime($date));
    $meses_ES = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
    $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
    $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
    return $numeroDia . " de " . $nombreMes . " de " . $anio;
}



function dpiletra($numdpi )
{

    $texto = preg_replace('/\s+/', '', $numdpi);
    $parte1 = substr($texto, 0, 4);
    $parte2 = substr($texto, 4, 5);
    $parte3 = substr($texto, 9, 4);
    $letra_dpi1 = numtoletrasv2($parte1);
    $letra_dpi2 = numtoletrasv2($parte2);
    $letra_dpi3 = numtoletrasv2($parte3);
    $resultado = ("{$letra_dpi1}, {$letra_dpi2}, {$letra_dpi3}");


    return $resultado;
}
function dpiformat($numdpi)
{
    $texto = preg_replace('/\s+/', '', $numdpi);
    $parte1 = substr($texto, 0, 4);
    $parte2 = substr($texto, 4, 5);
    $parte3 = substr($texto, 9, 4);
    $resultado = ("{$parte1} {$parte2} {$parte3}");
    return $resultado;
}
function numtoletrasv2($numero)
{
    $letra = new NumeroALetrasEsp();
    $letra_d = mb_strtolower($letra->toWords(intval(trim($numero))));
    return $letra_d;
}
function plazos($plazos)
{
    $result = "Meses";
    switch ($plazos) {
        case 'Mensual':
            $result = "Meses";
            break;
        case 'Semanal':
            $result = "Semanas";
            break;
    }
    return $result;
}
function limpiar($text)
{
    // $strash = array("\r", "\n", "\t");
    // $texto_limpio = str_replace($strash, '', $text);
    $texto_limpio = preg_replace('/\s+/', ' ', $text);
    return $texto_limpio;
}
function plandepagos($pdf, $nocuotas, $creppg)
{
    $divperiodo = (($nocuotas / 4));
    $parteentera = $divperiodo;
    $partedecimal = 0;
    $banderadecimal = false;
    if (is_float($divperiodo)) {
        $banderadecimal = true;
        $parteentera = (int)$divperiodo;
        $partedecimal = $divperiodo - $parteentera;
    }

    $pdf->SetFont('Times', 'B', 7);
    if ($parteentera == 0) {
        if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
            $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        }
        if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        }
        if (($partedecimal >= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        }
    } else {
        $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
    }

    $pdf->Ln(4);
    $pdf->SetFont('Times', '', 7);
    for ($i = 0; $i < $parteentera; $i++) {
        $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[$i]['totalcuota'])) ? 'Q ' . number_format($creppg[$i]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[$i]['dfecven'])) ? date('d-m-Y', strtotime($creppg[$i]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        if ($banderadecimal) {
            if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
                $i++;
            }
        }
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1 + ($parteentera)), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + $parteentera)]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + $parteentera)]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + $parteentera)]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + $parteentera)]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        if ($banderadecimal) {
            if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
                $i++;
            }
        }
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1 + ($parteentera * 2)), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 2))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + ($parteentera * 2))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 2))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + ($parteentera * 2))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        if ($banderadecimal) {
            if (($partedecimal >= 0.75)) {
                $i++;
            }
        }
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1 + ($parteentera * 3)), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 3))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + ($parteentera * 3))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 3))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + ($parteentera * 3))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(4);
        if ($banderadecimal) {
            if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
                $i--;
            }
            if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
                $i--;
            }
            if (($partedecimal >= 0.75)) {
                $i--;
            }
        }
    }
    if ($banderadecimal) {
        $i = $parteentera;
        if ($parteentera == 0) {
            $i = 0;
        }
        if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
            $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, ($i + 1), 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i)]['totalcuota'])) ? 'Q ' . number_format($creppg[($i)]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i)]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i)]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        }
        if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, ($i + 2 + ($parteentera)), 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 1 + ($parteentera))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + 1 + ($parteentera))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 1 + ($parteentera))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + 1 + ($parteentera))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        }
        if (($partedecimal >= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, ($i + 3 + ($parteentera * 2)), 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 2 + ($parteentera * 2))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + (2 + $parteentera * 2))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 2 + ($parteentera * 2))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + 2 + ($parteentera * 2))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        }
        $pdf->Ln(4);
    }
}