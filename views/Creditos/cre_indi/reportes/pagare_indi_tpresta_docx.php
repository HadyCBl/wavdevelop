<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
// include __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

include __DIR__ . '/../../../../includes/BD_con/db_con.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';

mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
// $hoy = date("Y-m-d");
$hoy = date("d-m-Y");

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Border;
use Luecano\NumeroALetras\NumeroALetras;
use PhpOffice\PhpWord\SimpleType\Jc;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];
$codcuenta = $archivo[0];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT cre.DFecDsbls fechades,cre.DFecVen fecven,cre.noPeriodo cuotas,cre.NMonCuo moncuota,cre.Cestado,NCapDes mondes,MonSug monsug,cli.idcod_cliente ccodcli,cli.short_name nombrecli,cli.primer_name,cli.segundo_name, cli.date_birth cumple, cre.DfecPago primerpago,
                cli.tercer_name,cli.primer_last,cli.segundo_last,cli.Direccion direccion,cli.profesion,age.id_agencia,usu.id_usu, cli.tel_no1 tel_no1,
                cli.tel_no1,cli.no_identifica,usu.nombre nomanal,usu.apellido apeanal, cre.NintApro as intereses, cre.NtipPerC tipoperiodo,
                IFNULL((SELECT nombre FROM tb_municipios WHERE codigo=age.municipio),'-') municipio,
                IFNULL((SELECT nombre FROM tb_departamentos WHERE id=age.departamento),'-') departamento,
                IFNULL((SELECT Titulo FROM $db_name_general.tb_ActiEcono WHERE id_ActiEcono=cre.ActoEcono),'-') actividadecono,
                IFNULL((SELECT SectoresEconomicos FROM $db_name_general.tb_sectoreseconomicos WHERE id_SectoresEconomicos=cre.CSecEco),'-') sectorecono,
                IFNULL((SELECT DestinoCredito FROM $db_name_general.tb_destinocredito WHERE id_DestinoCredito=cre.Cdescre),'-') destinocredito,
                IFNULL((SELECT (ncapita+nintere) FROM Cre_ppg WHERE ccodcta=cre.CCODCTA GROUP BY id_ppg LIMIT 1),0) montocuota
                FROM cremcre_meta cre 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                INNER JOIN tb_usuario usu ON usu.id_usu=cre.CodAnal
                INNER JOIN tb_agencia age ON age.cod_agenc=cre.CODAgencia
                WHERE CCODCTA=?; ";
$qfiador = "SELECT cli.short_name nombrecli,cli.primer_name,cli.segundo_name,
                cli.tercer_name,cli.primer_last,cli.segundo_last,cli.Direccion direccion
                FROM tb_garantias_creditos tgc 
                INNER JOIN cli_garantia clig ON clig.idGarantia=tgc.id_garantia 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=clig.descripcionGarantia
                WHERE tgc.id_cremcre_meta=?;";
$qdatosppg = "SELECT ppg.ccodcta, ppg.nintere AS interesesppg, ppg.ncapita AS capitalppg, ppg.cnrocuo AS cuotasppg, ppg.dfecven AS fechavenppg, ppg.OtrosPagos AS otrosppg,ppg.SaldoCapital AS saldo_capital,
                (SELECT SUM(ppg2.nintere) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_interes
                FROM Cre_ppg ppg
                WHERE ppg.ccodcta=?
                ORDER BY cnrocuo;";

// $opResult = array('status' => 0, 'mensaje' => $query);
// echo json_encode($opResult);
// return;
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$codcuenta]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontró la cuenta");
    }

    $fiador = $database->getAllResults($qfiador, [$codcuenta]);
    if (empty($fiador)) {
        // $showmensaje = true;
        // throw new Exception("No hay fiadores");
    }
    $ppg = $database->getAllResults($qdatosppg, [$codcuenta]);
    if (empty($ppg)) {
        $showmensaje = true;
        throw new Exception("No se encontraron datos de PPG");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}
function numeroALetras($numero)
{
    $unidades = [
        '',
        'UNO',
        'DOS',
        'TRES',
        'CUATRO',
        'CINCO',
        'SEIS',
        'SIETE',
        'OCHO',
        'NUEVE',
        'DIEZ',
        'ONCE',
        'DOCE',
        'TRECE',
        'CATORCE',
        'QUINCE',
        'DIECISÉIS',
        'DIECISIETE',
        'DIECIOCHO',
        'DIECINUEVE'
    ];

    $decenas = [
        '',
        '',
        'VEINTE',
        'TREINTA',
        'CUARENTA',
        'CINCUENTA',
        'SESENTA',
        'SETENTA',
        'OCHENTA',
        'NOVENTA'
    ];

    $centenas = [
        '',
        'CIENTO',
        'DOSCIENTOS',
        'TRESCIENTOS',
        'CUATROCIENTOS',
        'QUINIENTOS',
        'SEISCIENTOS',
        'SETECIENTOS',
        'OCHOCIENTOS',
        'NOVECIENTOS'
    ];

    // separa parte entera y decimal
    $parteEntera = floor($numero);
    $parteDecimal = round(($numero - $parteEntera) * 100);

    // convierte números de 0 a 999
    $convertirCentenas = function ($n) use ($unidades, $decenas, $centenas) {
        if ($n == 0) return '';
        if ($n == 100) return 'CIEN';

        $texto = '';
        $c = floor($n / 100);
        $d = floor(($n % 100) / 10);
        $u = $n % 10;

        if ($c > 0) {
            $texto .= $centenas[$c] . ' ';
        }

        if ($d == 1) {
            $texto .= $unidades[$d * 10 + $u];
        } elseif ($d == 2 && $u > 0) {
            $texto .= 'VEINTI' . $unidades[$u];
        } else {
            if ($d > 0) {
                $texto .= $decenas[$d];
                if ($u > 0) {
                    $texto .= ' Y ' . $unidades[$u];
                }
            } elseif ($u > 0) {
                $texto .= $unidades[$u];
            }
        }

        return trim($texto);
    };

    // convierte números grandes en grupos de miles
    $convertirMiles = function ($n) use ($convertirCentenas) {
        if ($n == 0) return 'CERO';

        $grupos = [
            '',
            'MIL',
            'MILLÓN',
            'MILLONES',
            'MIL MILLONES',
            'BILLÓN',
            'BILLONES'
        ];

        $partes = [];
        $grupo = 0;

        while ($n > 0) {
            $tres = $n % 1000;
            if ($tres > 0) {
                $textoGrupo = $convertirCentenas($tres);
                if ($grupo == 1 && $tres == 1) {
                    $partes[] = 'MIL';
                } elseif ($grupo == 2) {
                    $partes[] = ($tres == 1) ? 'UN MILLÓN' : $textoGrupo . ' MILLONES';
                } elseif ($grupo == 3) {
                    $partes[] = ($tres == 1) ? 'MIL MILLONES' : $textoGrupo . ' MIL MILLONES';
                } else {
                    if ($grupo > 0) {
                        $partes[] = $textoGrupo . ' ' . $grupos[$grupo];
                    } else {
                        $partes[] = $textoGrupo;
                    }
                }
            }
            $n = floor($n / 1000);
            $grupo++;
        }

        return implode(' ', array_reverse($partes));
    };

    // parte entera en letras
    $texto = $convertirMiles($parteEntera) . " QUETZALES";

    // parte decimal en letras
    if ($parteDecimal > 0) {
        $texto .= " CON " . $convertirMiles($parteDecimal) . " CENTAVOS";
    }

    return strtoupper(trim($texto));
}
function numeroALetrasCompleto($numero)
{
    $unidades = [
        0 => '',
        1 => 'un',
        2 => 'dos',
        3 => 'tres',
        4 => 'cuatro',
        5 => 'cinco',
        6 => 'seis',
        7 => 'siete',
        8 => 'ocho',
        9 => 'nueve',
        10 => 'diez',
        11 => 'once',
        12 => 'doce',
        13 => 'trece',
        14 => 'catorce',
        15 => 'quince',
        16 => 'dieciséis',
        17 => 'diecisiete',
        18 => 'dieciocho',
        19 => 'diecinueve',
        20 => 'veinte',
        21 => 'veintiuno',
        22 => 'veintidós',
        23 => 'veintitrés',
        24 => 'veinticuatro',
        25 => 'veinticinco',
        26 => 'veintiséis',
        27 => 'veintisiete',
        28 => 'veintiocho',
        29 => 'veintinueve',
        30 => 'treinta',
        31 => 'treinta y uno'
    ];

    if ($numero >= 1 && $numero <= 31) {
        return $unidades[$numero];
    }

    return 'número inválido';
}

// Función para convertir años a letras
function anioALetras($anio)
{
    $miles = [
        2000 => 'dos mil',
        2001 => 'dos mil uno',
        2002 => 'dos mil dos',
        2003 => 'dos mil tres',
        2004 => 'dos mil cuatro',
        2005 => 'dos mil cinco',
        2006 => 'dos mil seis',
        2007 => 'dos mil siete',
        2008 => 'dos mil ocho',
        2009 => 'dos mil nueve',
        2010 => 'dos mil diez',
        2011 => 'dos mil once',
        2012 => 'dos mil doce',
        2013 => 'dos mil trece',
        2014 => 'dos mil catorce',
        2015 => 'dos mil quince',
        2016 => 'dos mil dieciséis',
        2017 => 'dos mil diecisiete',
        2018 => 'dos mil dieciocho',
        2019 => 'dos mil diecinueve',
        2020 => 'dos mil veinte',
        2021 => 'dos mil veintiuno',
        2022 => 'dos mil veintidós',
        2023 => 'dos mil veintitrés',
        2024 => 'dos mil veinticuatro',
        2025 => 'dos mil veinticinco',
        2026 => 'dos mil veintiséis',
        2027 => 'dos mil veintisiete',
        2028 => 'dos mil veintiocho',
        2029 => 'dos mil veintinueve',
        2030 => 'dos mil treinta'
    ];

    return isset($miles[$anio]) ? $miles[$anio] : 'año no soportado';
}

// Función principal para convertir fecha completa
function fechaCompleta($fecha)
{
    // Separar la fecha
    $partes = explode('-', $fecha);
    $dia = (int)$partes[0];
    $mes = (int)$partes[1];
    $anio = (int)$partes[2];

    // Array de meses
    $meses = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    ];

    // Convertir cada parte
    $diaTexto = numeroALetrasCompleto($dia);
    $mesTexto = $meses[$mes];
    $anioTexto = anioALetras($anio);

    // Formar el texto completo
    $plural = ($dia == 1) ? 'día' : 'días';

    return $diaTexto . ' ' . $plural . ' del mes de ' . ucfirst($mesTexto) . ' del año ' . ucfirst($anioTexto);
}

function fechaCompleta2($fecha)
{
    // Convertir la fecha a objeto DateTime
    $dateTime = new DateTime($fecha);
    
    // Crear formateador con locale español
    $formatterDia = new IntlDateFormatter('es_ES', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'EEEE');
    $formatterMes = new IntlDateFormatter('es_ES', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM');
    
    // Obtener día de la semana, día, mes y año
    $diaSemana = $formatterDia->format($dateTime);
    $dia = $dateTime->format('j');
    $mes = $formatterMes->format($dateTime);
    $anio = $dateTime->format('Y');
    
    // Formar el texto completo
    return strtolower($diaSemana) . ' ' . $dia . ' de ' . strtolower($mes) . ' de ' . $anio;
}
function calcularEdad($fechaNacimiento)
{
    try {
        $birthDate = new DateTime($fechaNacimiento);
        $currentDate = new DateTime();
        return $currentDate->diff($birthDate)->y;
    } catch (Exception $e) {
        return 0;
    }
}
// Función para convertir un número a  texto, se usa para las edades
function numeroATexto($numero)
{
    $unidades = [
        '',
        'uno',
        'dos',
        'tres',
        'cuatro',
        'cinco',
        'seis',
        'siete',
        'ocho',
        'nueve',
        'diez',
        'once',
        'doce',
        'trece',
        'catorce',
        'quince',
        'dieciséis',
        'diecisiete',
        'dieciocho',
        'diecinueve'
    ];

    $decenas = [
        '',
        '',
        'veinte',
        'treinta',
        'cuarenta',
        'cincuenta',
        'sesenta',
        'setenta',
        'ochenta',
        'noventa'
    ];

    $centenas = [
        '',
        'ciento',
        'doscientos',
        'trescientos',
        'cuatrocientos',
        'quinientos',
        'seiscientos',
        'setecientos',
        'ochocientos',
        'novecientos'
    ];

    if ($numero == 0) return 'cero';
    if ($numero < 20) return $unidades[$numero];
    if ($numero < 30) return $numero == 20 ? 'veinte' : 'veinti' . $unidades[$numero - 20];
    if ($numero < 100) return $decenas[intval($numero / 10)] . ($numero % 10 ? ' y ' . $unidades[$numero % 10] : '');
    if ($numero == 100) return 'cien';
    if ($numero < 1000) return $centenas[intval($numero / 100)] . ($numero % 100 ? ' ' . numeroATexto($numero % 100) : '');
    // Para miles
    if ($numero < 1000000) {
        $miles = intval($numero / 1000);
        $resto = $numero % 1000;
        $textoMiles = $miles == 1 ? 'mil' : numeroATexto($miles) . ' mil';
        return $resto > 0 ? $textoMiles . ' ' . numeroATexto($resto) : $textoMiles;
    }

    return (string)$numero;
}

function formatearDPI($dpi)
{
    // Asegurarse de que el DPI tenga 13 dígitos
    $dpi = str_pad($dpi, 13, '0', STR_PAD_LEFT);

    // Formato: XXXX XXXXX XXXX
    $parte1 = substr($dpi, 0, 4);   // Primeros 4 dígitos
    $parte2 = substr($dpi, 4, 5);   // Siguientes 5 dígitos
    $parte3 = substr($dpi, 9, 4);   // Últimos 4 dígitos

    return $parte1 . ' ' . $parte2 . ' ' . $parte3;
}

function dpiATexto($dpi)
{
    // Asegurarse de que el DPI tenga 13 dígitos
    $dpi = str_pad($dpi, 13, '0', STR_PAD_LEFT);

    // Dividir en las tres partes
    $parte1 = intval(substr($dpi, 0, 4));   // Primeros 4 dígitos
    $parte2 = intval(substr($dpi, 4, 5));   // Siguientes 5 dígitos
    $parte3 = intval(substr($dpi, 9, 4));   // Últimos 4 dígitos

    $texto1 = numeroATexto($parte1);
    $texto2 = numeroATexto($parte2);
    $texto3 = numeroATexto($parte3);

    return $texto1 . ', ' . $texto2 . ', ' . $texto3;
}



$ppgtemp = creppg_temporal($codcuenta, $conexion);
// $opResult = array('status' => 0, 'mensaje' => $ppgtemp);
//     echo json_encode($opResult);
//     return;
$fechadesembolso = $result[0]["fechades"];
$fechadesembolso2 = date('d-m-Y', strtotime($result[0]["fechades"]));
$fechaprimerpago = $result[0]["primerpago"];
$fechavence = ($result[0]["Cestado"] == "F") ? $result[0]["fecven"] : $ppgtemp[count($ppgtemp) - 1]["dfecven"];
$fechavenceformateado = date('d-m-Y', strtotime($fechavence));
$fechprimerpagoformateado = date('d-m-Y', strtotime($fechaprimerpago));
$fechaenletras = fechletras($fechadesembolso);
$fechavenceletras = fechletras($fechavenceformateado);
$fechprimerpagoletras = fechletras($fechprimerpagoformateado);
$codcliente = $result[0]["ccodcli"];
$telcliente = $result[0]["tel_no1"];
$nombreasesor = $result[0]["nomanal"];
$tasaintere = $result[0]["intereses"];
$tasaintereLetra = numeroALetras($tasaintere) . ' por ciento';
$apellidoasesor = $result[0]["apeanal"];
$asesor = $nombreasesor . " " . $apellidoasesor;
$actividadeconomica = $result[0]["actividadecono"];
$sectoreconomico = $result[0]["sectorecono"];
$municipio = $result[0]["municipio"];
$departamento = $result[0]["departamento"];

$nombrescliente = $result[0]["primer_name"] . " " . $result[0]["segundo_name"] . " " . $result[0]["tercer_name"];
$apellidoscliente = $result[0]["primer_last"] . " " . $result[0]["segundo_last"];

$dia = "Jueves";
$mes = "Septiembre";
$anio = "2024";

$nombrecliente = $result[0]["nombrecli"];
$edadcliente = $result[0]["cumple"];
$diredomicilio = $result[0]["direccion"];
$dpi = $result[0]["no_identifica"];
$celular = $result[0]["tel_no1"];
$totalintereses = $ppg[0]["total_interes"];

// Formatear DPIs
$dpiClienteFormateado = formatearDPI($dpi);

// Convertir a texto
$dpiClienteTexto = dpiATexto($dpi);


// Calcular edades
$edadClienteNumero = calcularEdad($edadcliente);

// Convertir a texto
$edadClienteTexto = numeroATexto($edadClienteNumero);

$monto = ($result[0]["Cestado"] == "F") ? $result[0]["mondes"] : $result[0]["monsug"];
$format_monto = new NumeroALetras();
$monto_total = floatval($monto) + floatval($totalintereses);
$cantidadenletras = $format_monto->toMoney($monto_total, 2, 'QUETZALES', 'CENTAVOS');

$nocuotas = $result[0]["cuotas"];

$tiposPeridodo = [
    '1D'  => 'DIARIO',
    '7D'  => 'SEMANALES',
    '15D' => 'QUINCENAL',
    '14D' => 'CATORCENAL',
    '1M'  => 'MENSUAL',
    '2M'  => 'BIMENSUAL',
    '3M'  => 'TRIMESTRAL',
    '4M'  => 'CUATRIMESTRE',
    '5M'  => 'QUINQUEMESTRE',
    '6M'  => 'SEMESTRAL',
    '1C'  => 'ANUAL',
    '0D'  => 'OTROS'
];
$tipoperiodo = $result[0]["tipoperiodo"];
$tipoperiodoTexto = isset($tiposPeridodo[$tipoperiodo]) ? $tiposPeridodo[$tipoperiodo] : 'DESCONOCIDO';

$cantdias = dias_dif($fechadesembolso, $fechavence);
$cuota = ($result[0]["Cestado"] == "F") ?  $result[0]["montocuota"] : ($ppgtemp[0]["ncapita"] + $ppgtemp[0]["nintere"]);
$profesion =  $result[0]["profesion"];

//DATOS DE AVAL
$nombresaval = (empty($fiador)) ? " " : ($fiador[0]["primer_name"] . " " . $fiador[0]["segundo_name"] . " " . $fiador[0]["tercer_name"]);
$apellidosaval = (empty($fiador)) ? " " : ($fiador[0]["primer_last"] . " " . $fiador[0]["segundo_last"]);
$direccionaval = (empty($fiador)) ? " " : $fiador[0]["direccion"];

$razonsocial = ($result[0]['id_usu'] == 17) ? "GRUPO EMPRESARIAL SERVIALIANZA" : "INJELIFID S.A.";
$namecomercial = ($result[0]['id_usu'] == 17) ? "CREDIRAPI" : "NAWAL";
$phpWord = new PhpWord();

// Configuracion del tamaño del papel a A4
$section = $phpWord->addSection([
    'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.27), // Ancho en pulgadas convertido a Twips
    'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(11.69), // Altura en pulgadas convertida a Twips
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), // Ajusta el margen superior
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5), // Ajusta el margen derecho
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), // Ajusta el margen inferior
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5)  // Ajusta el margen izquierdo
]);

// Estilos
$fontStyle = ['name' => 'Arial', 'size' => 8];
$fontStyletext = ['name' => 'Arial', 'size' => 11];
$cellStyle = ['valign' => 'center', 'align' => 'center',];
$borderStyle = ['borderSize' => 1, 'borderColor' => '000000'];
$styletextos = [
    'bold' => true,
    'size' => 8,
    'name' => 'Arial',
];
$fontStyletextbold = [
    'bold' => true,
    'size' => 6.5,
    'name' => 'Arial',
];
$cellStylemargins = [
    'valign' => 'center', // Alineación vertical
    'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2),
    'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2),
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2),
    'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.2),
];
$paragraphStyle = [
    'spaceAfter' => 0, // Espacio después del párrafo en puntos
    'spaceBefore' => 0, // Espacio antes del párrafo en puntos
];
// $section = $phpWord->addSection();

$paragraphJustify = [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH, // Justificado
    'spaceAfter' => 0,
    'spaceBefore' => 0,
];

$section->addText("PAGARÉ LIBRE DE PROTESTO", ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$section->addText("CORPORACIÓN DE SERVICIOS INTEGRADOS DE NOR-ORIENTE", ['bold' => true, 'name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$section->addText("COSINSA", ['bold' => true, 'name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText("En el Municipio de Chiquimula, Departamento de Chiquimula, el día hoy " . fechaCompleta2($fechadesembolso2) . '. ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(' YO: ' . $nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(', de ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($edadClienteTexto, ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(' años de edad, ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('con domicilio en el departamento de ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($departamento . ', ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(' me identifico con el Documento Personal de Identificación (DPI) con Código Único de Identificación (CUI) número: ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($dpiClienteTexto . '( ' . $dpiClienteFormateado . '), ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('extendido por el Registro Nacional de las Personas de la República de Guatemala. Por medio del presente ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('TÍTULO DE CRÉDITO ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('manifesto que ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('DEBO Y PAGARÉ INCONDICIONALMENTE a CORPORACIÓN DE SERVICIOS INTEGRADOS DE NOR-ORIENTE, SOCIEDAD ANÓNIMA, ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('la cantidad de: ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($cantidadenletras . ' (Q. ' . number_format($monto_total, 2) . '), ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('en la forma que a continuación se detalla, y reconociendo expresamente que el presente pagaré se regirá por las estipulaciones siguientes: ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('PRIMERA. PLAZO Y FORMA DE PAGO: ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('El plazo de la presente obligación será de ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(numeroATexto($nocuotas) . '('.$nocuotas.') ' . $tipoperiodoTexto . ', ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('por lo que la suma adeudada a ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('CORPORACIÓN DE SERVICIOS INTEGRADOS DE NOR-ORIENTE, SOCIEDAD ANÓNIMA, ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('se pagará mediante ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(numeroATexto($nocuotas) . '('.$nocuotas.') ' . 'AMORTIZACIONES: vencidas y consecutivas por el valor de Q. ' . $cuota . ' CADA UNA, ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('las cuales se pagaran de forma ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('('.$tipoperiodoTexto.'), ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('que incluye capital, intereses convencionales del ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('DOS PORCIENTO ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('(2%), gastos administrativos, operativos, y legales, más el impuesto al valor agregado ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('(IVA). ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('Iniciando en fecha ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($fechprimerpagoletras . '('. $fechprimerpagoformateado .'), ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('por lo que finaliza en fecha ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($fechavenceletras . '('. $fechavenceformateado .'). ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('SEGUNDA. LUGAR DE PAGO: ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('Pagaré la suma adeudada en la, ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('TERCERA CALLE 8-01 LOCAL 5 ZONA 1, ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('del Municipio de Chiquimula, Departamento de Chiquimula, u otro lugar que el legítimo tenedor de este título indique, sin necesidad de cobro ni requerimiento alguno. ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('TERCERA. OTRAS CONDICIONES DEL PAGARÉ: ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('Esta obligación se cumplirá con las siguientes condiciones adicionales las cuales desde ya acepto: a) Desde este momento como buenas y exactas las cuentas que se presenten respecto de este pagaré; b) Que todos los gastos que se ocasionen por razón de este título de crédito, directa o indirectamente, inclusive los de cobranza judicial o extrajudicial son y corren por mi cuenta; c) Si este pagaré fuere endosado a cualquier persona o entidad, los pagos deberán efectuarse de la misma forma en virtud de que el titular del presente pagaré reciba íntegramente las amortizaciones acordadas, los intereses convencionales , gastos administrativos, operativos, legales y cualquier otro gasto causado más el impuesto de valor agregado (IVA); d) Para los efectos legales, renuncio al fuera de mi domicilio y señalo como lugar para recibir citaciones, notificaciones y emplazamientos en: ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($diredomicilio . ', ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('Municipio de Chiquimula, Departamento de Chiquimula; y en caso de cambio de la misma, me obligo a notificarlo al tenedor de este título y si no lo hiciere así, acepto como válidas las que se hicieran en la dirección antes indicada; e) Expresamente declaro que con dos (2) amortizaciones que deje de pagar en el lugar, tiempo y forma convenida, dará derecho al tenedor de este pagaré, a dar por vencido el plazo de este título y exigir la cancelación total del saldo, por la vía que el beneficiario estime conveniente, para lo cual este documento tendrá fuerza ejecutiva, y valdrá como ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('TÍTULO EJECUTIVO ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('en caso de incumplimiento, y acepto como buenas, líquidas y exigibles y de plazo vencido las cuentas que el legítimo tenedor del pagare presente;  f) En caso de mora  se pagara  en concepto de interés moratorio el equivalente a una taza del veinte por ciento (20%) calculado sobre el monto total del crédito; g) Este pagaré se emite libre de protesto, libre de formalidades de presentación y cobro o requerimiento; h) En caso de juicio, ni el tenedor de este PAGARÉ ni los auxiliares que proponga estarán obligados a prestar garantía. En los términos relacionados acepto expresamente el contenido íntegro del presente título de crédito   y de las obligaciones que se deriven del mismo. Doy integra lectura a lo escrito y bien enterado/a de su contenido, objeto, validez y demás efectos legales, lo ratifico, acepto, y firmo. ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(' ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);

$section->addTextBreak(3);
$section->addText("F_______________________________", ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText("DPI: " . $dpiClienteFormateado, ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);

$section->addTextBreak(1);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText("En el Municipio de Esquipulas, del Departamento de Chiquimula , el día hoy " . fechaCompleta($hoy) . ", ", ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('DOY FE: ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('que la firma que antecede y calza el anterior documento consistente en ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('PAGARÉ LIBRE DE PROTESTO ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('es ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('AUTÉNTICA', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('por haber sido signada el día de hoy en mi presencia por:', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText( $nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('quien se identifica con el Documento Personal de Identificación (DPI) con Código Único de Identificación (CUI) número: ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($dpiClienteTexto . '( ' . $dpiClienteFormateado . '), ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('extendido por el Registro Nacional de las Personas de la República de Guatemala; quien firma nuevamente la presente Acta de legalización, junto al Notario que autoriza.', ['bold' => false, 'name' => 'Arial', 'size' => 11]);

$section->addTextBreak(3);
$section->addText("F_______________________________", ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText('ANTE MÍ:', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);


ob_start();
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save("php://output");
$worddata = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile' => "Pagare",
    'tipo' => "vnd.ms-word",
    'extension' => "docx",
    'download' => 1,
    'data' => "data:application/vnd.ms-word;base64," . base64_encode($worddata)
);
echo json_encode($opResult);
exit;
 