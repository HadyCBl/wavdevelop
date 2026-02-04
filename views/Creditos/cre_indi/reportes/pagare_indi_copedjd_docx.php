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
$query = "SELECT cre.DFecDsbls fechades,cre.DFecVen fecven,cre.noPeriodo cuotas,cre.NMonCuo moncuota,cre.Cestado,NCapDes mondes,MonSug monsug,cli.idcod_cliente ccodcli,cli.short_name nombrecli,cli.primer_name,cli.segundo_name,
                cli.tercer_name,cli.primer_last,cli.segundo_last,cli.Direccion direccion,cli.profesion,age.id_agencia,usu.id_usu, cli.tel_no1 tel_no1, cli.date_birth cumple, cli.profesion profesion,
                cli.tel_no1,cli.no_identifica,usu.nombre nomanal,usu.apellido apeanal, cre.NintApro as intereses, cre.NtipPerC tipoperiodo, cre.CCodCta ccodcta, cli.estado_civil estadocivil, cre.Dictamen dictamen,
                IFNULL((SELECT nombre FROM tb_municipios WHERE codigo=age.municipio),'-') municipio,
                IFNULL((SELECT nombre FROM tb_departamentos WHERE id=age.departamento),'-') departamento,
                IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_reside),'-') municipiocli,
                IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_reside),'-') departamentocli,
                IFNULL((SELECT Titulo FROM $db_name_general.tb_ActiEcono WHERE id_ActiEcono=cre.ActoEcono),'-') actividadecono,
                IFNULL((SELECT SectoresEconomicos FROM $db_name_general.tb_sectoreseconomicos WHERE id_SectoresEconomicos=cre.CSecEco),'-') sectorecono,
                IFNULL((SELECT DestinoCredito FROM $db_name_general.tb_destinocredito WHERE id_DestinoCredito=cre.Cdescre),'-') destinocredito,
                IFNULL((SELECT (ncapita+nintere) FROM Cre_ppg WHERE ccodcta=cre.CCODCTA GROUP BY id_ppg LIMIT 1),0) montocuota
                FROM cremcre_meta cre 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                INNER JOIN tb_usuario usu ON usu.id_usu=cre.CodAnal
                INNER JOIN tb_agencia age ON age.cod_agenc=cre.CODAgencia
                INNER JOIN Cre_ppg ppg ON ppg.ccodcta=cre.CCODCTA
                WHERE cre.CCODCTA=?;";
$qfiador = "SELECT cli.short_name nombrecli,cli.primer_name,cli.segundo_name, cli.estado_civil estadocivil, cli.profesion profesion,
                cli.tercer_name,cli.primer_last,cli.segundo_last,cli.Direccion direccion,cli.date_birth cumplefiador, cli.no_identifica dpifiador
                FROM tb_garantias_creditos tgc 
                INNER JOIN cli_garantia clig ON clig.idGarantia=tgc.id_garantia 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=clig.descripcionGarantia
                WHERE tgc.id_cremcre_meta=?;";
$qdatosppg = "SELECT ppg.ccodcta, ppg.nintere interesesppg, ppg.ncapita capitalppg, ppg.cnrocuo cuotasppg, ppg.dfecven fechavenppg, ppg.OtrosPagos otrosppg,ppg.SaldoCapital saldo_capital, ppg.NAhoProgra vinculado,
                (SELECT SUM(ppg2.nintere) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_interes,
                (SELECT SUM(ppg2.NAhoProgra) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_vinculado,
                (SELECT SUM(ppg2.OtrosPagos) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_otros
                FROM Cre_ppg ppg
                WHERE ccodcta=?
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


// Función para calcular la edad a partir de la fecha de nacimiento
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
$ppgtemp = creppg_temporal($codcuenta, $conexion);
// $opResult = array('status' => 0, 'mensaje' => $ppgtemp);
//     echo json_encode($opResult);
//     return;
$fechadesembolso = $result[0]["fechades"];
$fechavence = ($result[0]["Cestado"] == "F") ? $result[0]["fecven"] : $ppgtemp[count($ppgtemp) - 1]["dfecven"];
$fechaenletras = fechletras($fechadesembolso);
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
$codigocred = $result[0]["ccodcta"];
$departamentocli = $result[0]["departamentocli"];
$municipiocli = $result[0]["municipiocli"];

$nombrescliente = $result[0]["primer_name"] . " " . $result[0]["segundo_name"] . " " . $result[0]["tercer_name"];
$apellidoscliente = $result[0]["primer_last"] . " " . $result[0]["segundo_last"];

$dia = "Jueves";
$mes = "Septiembre";
$anio = "2024";

$nombrecliente = $result[0]["nombrecli"];
$diredomicilio = $result[0]["direccion"];
$dpicli = $result[0]["no_identifica"];
$celular = $result[0]["tel_no1"];
$edadcliente = $result[0]["cumple"];
$estadocivilcli = $result[0]["estadocivil"];
$profesioncliente = $result[0]["profesion"];
$dictamen = $result[0]["dictamen"];

$totalintereses = $ppg[0]["total_interes"];
$totalivinculados = $ppg[0]["total_vinculado"];
$totalotros = $ppg[0]["total_otros"];

$monto = ($result[0]["Cestado"] == "F") ? $result[0]["mondes"] : $result[0]["monsug"];
$format_monto = new NumeroALetras();
$monto_total = $monto + $totalintereses + $totalivinculados + $totalotros;
$cantidadenletras = $format_monto->toMoney($monto_total, 2, 'QUETZALES', 'CENTAVOS');

//PARA LA TABLA DE PAGOS
$fechapagoppg = $ppg[0]["fechavenppg"];
$cuotasppg = $ppg[0]["cuotasppg"];
$interesesppg = $ppg[0]["interesesppg"];
$capitalppg = $ppg[0]["capitalppg"];
$otrosppg = $ppg[0]["otrosppg"];
$saldo_capital = $ppg[0]["saldo_capital"];
$viculosaho = $ppg[0]["vinculado"];


$nocuotas = $result[0]["cuotas"];

$tiposPeridodo = [
    'D' => 'DIARIO',
    'S' => 'SEMANAL',
    'Q' => 'CATORCENAL',
    '15D' => 'QUINCENAL',
    '1M' => 'MENSUAL',
    '2M' => 'BIMENSUAL',
    '3M' => 'TRIMESTRAL',
    '6M' => 'SEMESTRAL',
    '1' => 'ANUAL',
    '0D' => 'OTROS'
];
$tipoperiodo = $result[0]["tipoperiodo"];

$cantdias = dias_dif($fechadesembolso, $fechavence);
$cuota = ($result[0]["Cestado"] == "F") ?  $result[0]["montocuota"] : ($ppgtemp[0]["ncapita"] + $ppgtemp[0]["nintere"]);
$profesion =  $result[0]["profesion"];

//DATOS DE AVAL
$nombresaval = (empty($fiador)) ? " " : ($fiador[0]["primer_name"] . " " . $fiador[0]["segundo_name"] . " " . $fiador[0]["tercer_name"]);
$apellidosaval = (empty($fiador)) ? " " : ($fiador[0]["primer_last"] . " " . $fiador[0]["segundo_last"]);
$nombrecompletoaval = (empty($fiador)) ? " " : $nombresaval . " " . $apellidosaval;
$direccionaval = (empty($fiador)) ? " " : $fiador[0]["direccion"];
$edadfiador = (empty($fiador)) ? " " : $fiador[0]["cumplefiador"];
$estadocivilfiador = (empty($fiador)) ? " " : $fiador[0]["estadocivil"];
$profesionfiador = (empty($fiador)) ? " " : $fiador[0]["profesion"];
// $municipiofiador = (empty($fiador)) ? " " : $fiador[0]["municipiofiador"];
// $departamentofiador = (empty($fiador)) ? " " : $fiador[0]["departamentofiador"];
$dpifiador = (empty($fiador)) ? " " : $fiador[0]["dpifiador"];

// Calcular edades
$edadClienteNumero = calcularEdad($edadcliente);
$edadFiadorNumero = calcularEdad($edadfiador);

// Convertir a texto
$edadClienteTexto = numeroATexto($edadClienteNumero);
$edadFiadorTexto = numeroATexto($edadFiadorNumero);

// Formatear DPIs
$dpiClienteFormateado = formatearDPI($dpicli);
$dpiFiadorFormateado = formatearDPI($dpifiador);

// Convertir a texto
$dpiClienteTexto = dpiATexto($dpicli);
$dpiFiadorTexto = dpiATexto($dpifiador);

//Cuota a texto
$cuotaTexto = numeroATexto($nocuotas);

$razonsocial = ($result[0]['id_usu'] == 17) ? "GRUPO EMPRESARIAL SERVIALIANZA" : "INJELIFID S.A.";
$namecomercial = ($result[0]['id_usu'] == 17) ? "CREDIRAPI" : "NAWAL";
$phpWord = new PhpWord();

// Configuracion del tamaño del papel a A4
$section = $phpWord->addSection([
    'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5), // Ancho en pulgadas convertido a Twips
    'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(13.0), // Altura en pulgadas convertida a Twips
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // Ajusta el margen superior
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.8), // Ajusta el margen derecho
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // Ajusta el margen inferior
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.8)  // Ajusta el margen izquierdo
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
$headerStyle = ['bold' => true, 'name' => 'Arial', 'size' => 11];
$headerStyleSmall = ['bold' => true, 'name' => 'Arial', 'size' => 9];
$titleStyle = ['bold' => true, 'name' => 'Arial', 'size' => 14];
$subtitleStyle = ['bold' => true, 'name' => 'Arial', 'size' => 12];
$amountStyle = ['bold' => true, 'name' => 'Arial', 'size' => 12];
$bodyStyle = ['name' => 'Arial', 'size' => 9];
$smallStyle = ['name' => 'Arial', 'size' => 8];

$centerAlign = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 60];
$rightAlign = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT, 'spaceAfter' => 60];
$justifyAlign = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH, 'spaceAfter' => 120];
$normalSpacing = ['spaceAfter' => 80];

// $section->addImage(
//     '../../../../includes/img/jireh.png', // Ruta a tu imagen
//     [
//         'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(5), // Puedes ajustar el tamaño
//         'height' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(3), // Opcional
//         'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, // Centra la imagen
//         'wrappingStyle' => 'inline'
//     ]
// );
// Crear el encabezado
$header = $section->addHeader();

// Crear una tabla para el layout horizontal
$table = $header->addTable([
    'cellMargin' => 0,
    'width' => 100 * 50, // Ancho completo (100%)
]);

$table->addRow();

// Primera celda: Logo (lado izquierdo) - SIN BORDES
$cell1 = $table->addCell(1500, [
    'valign' => 'center',
]); 
$cell1->addImage(
    '../../../../includes/img/copedjd.png',
    [
        // 'width' => 70,
        'height' => 70,
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT,
    ]
);

// Segunda celda: Información de texto (lado derecho, centrado) - SIN BORDES
$cell2 = $table->addCell(5700, [
    'valign' => 'center',
]);

$cell2->addText(
    "COOPERATIVA DJD MAYALAND R.L.",
    ['bold' => true, 'name' => 'Arial', 'size' => 10],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]
);

$cell2->addText(
    "Aldea Mayaland Ixcán, El Quiché",
    ['bold' => false, 'name' => 'Arial', 'size' => 9],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]
);

$cell2->addText(
    "Nit: 11302232-8",
    ['bold' => false, 'name' => 'Arial', 'size' => 9],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]
);

$cell2->addText(
    "Teléfonos: 3146-5919 y 3144-2273",
    ['bold' => false, 'name' => 'Arial', 'size' => 9],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]
);

$cell2->addText(
    "djdmayalandrl@gmail.com",
    ['bold' => false, 'name' => 'Arial', 'size' => 9],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]
);

$section->addTextBreak(1);

$section->addText(
    "PAGARÉ",
    ['bold' => true, 'name' => 'Arial', 'size' => 9],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "(Libreta de Protesto)",
    ['bold' => true, 'name' => 'Arial', 'size' => 8],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    number_format($monto_total, 2),
    ['bold' => true, 'name' => 'Arial', 'size' => 8],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "Prestamos No. " . $codigocred,
    ['bold' => true, 'name' => 'Arial', 'size' => 8],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);

$section->addText(
    "Pagaré No:  " . $dictamen,
    ['bold' => true, 'name' => 'Arial', 'size' => 8],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "PAGINA No. 1/2",
    ['bold' => true, 'name' => 'Arial', 'size' => 8],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('A los ' . fechaCompleta($hoy) . ', en el municipio de Ixcán, departamento de El Quiché, NOSOTROS: ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);
$textRun->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 7]);
$textRun->addText(' y ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);
$textRun->addText($nombrecompletoaval, ['bold' => true, 'name' => 'Arial', 'size' => 7]);
$textRun->addText(', de ' . $edadClienteTexto . ' y ' . $edadFiadorTexto . ' años de edad; ' . $estadocivilcli . ' y ' . $estadocivilfiador . '; ' .
    'guatemaltecos de origen, con Domicilio en el departamento de ' . $departamentocli . ', con residencia en ' . $diredomicilio . ' del municipio de ' . $municipiocli . ', 
nos identificamos con el Documento Personal de Identificación -DPI- con Código Único de Identificación -CUI- números: ' .
    $dpiClienteTexto . ' (' . $dpiClienteFormateado . ') y ' . $dpiFiadorTexto . ' (' . $dpiFiadorFormateado . '), extendidos por el Registro Nacional de las Personas de la República de 
Guatemala.', ['bold' => false, 'name' => 'Arial', 'size' => 7]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Por medio del Presente Título de Crédito consistente en Pagaré, prometemos pagar incondicionalmente a la orden de la entidad denominada: COOPERATIVA INTEGRAL DE AHORRO 
Y CREDITO DJD MAYALAND, RESPONSABILIDAD LIMITADA; con domicilio en aldea Mayaland, del municipio de Ixcán, del departamento de El Quiché. ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('La cantidad de: ' . $cantidadenletras . ' (' . $monto_total . '), mediante ' . $cuotaTexto . ' (' . $nocuotas . '), pagos de forma mensual abonando capital, interés, ahorro y gastos de 
papeleria proyectados mensualmente. CONSECUTIVOS DE: ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);

//AQUI VA LA TABLA DE PAGOS
// Crear la tabla de pagos
$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
    'width' => 100 * 50, // Ancho de tabla en twips
]);



$totalCuotas = count($ppg);

if ($totalCuotas <= 18) {

    // Definir anchos de columnas
    $table->addRow(300, ['exactHeight' => true]);
    $table->addCell(1800, ['bgColor' => 'CCCCCC'])->addText('Fecha de Pago', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1200, ['bgColor' => 'CCCCCC'])->addText('No. Cuota', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1400, ['bgColor' => 'CCCCCC'])->addText('Cuota Pactada', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1200, ['bgColor' => 'CCCCCC'])->addText('Interés', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1200, ['bgColor' => 'CCCCCC'])->addText('Capital', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1400, ['bgColor' => 'CCCCCC'])->addText('Gastos/Ahorro', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1400, ['bgColor' => 'CCCCCC'])->addText('Saldo Capital', ['bold' => true, 'size' => 7], ['alignment' => 'center']);

    foreach ($ppg as $index => $pago) {
        $table->addRow(300, ['exactHeight' => true]);

        // Formatear la fecha
        $fechaPago = date('d-m-Y', strtotime($pago['fechavenppg']));

        // Calcular cuota pactada (capital + interés)
        $cuotaPactada = $pago['capitalppg'] + $pago['interesesppg'];

        $table->addCell(1800)->addText($fechaPago, ['size' => 7], ['alignment' => 'center']);
        $table->addCell(1200)->addText($pago['cuotasppg'], ['size' => 7], ['alignment' => 'center']);
        $table->addCell(1400)->addText(number_format($cuotaPactada, 2), ['size' => 7], ['alignment' => 'center']);
        $table->addCell(1200)->addText(number_format($pago['interesesppg'], 2), ['size' => 7], ['alignment' => 'center']);
        $table->addCell(1200)->addText(number_format($pago['capitalppg'], 2), ['size' => 7], ['alignment' => 'center']);
        $table->addCell(1400)->addText(number_format($pago['otrosppg'], 2), ['size' => 7], ['alignment' => 'center']);
        $table->addCell(1400)->addText(number_format($pago['saldo_capital'], 2), ['size' => 7], ['alignment' => 'center']);
    }
} else {

    $mitad = ceil($totalCuotas / 2); // Mitad redondeada hacia arriba
    $ppg1 = array_slice($ppg, 0, $mitad);        // Primera mitad
    $ppg2 = array_slice($ppg, $mitad);           // Segunda mitad

    // Encabezado doble fila (con nuevo orden)
    $table->addRow(300, ['exactHeight' => true]);
    $table->addCell(1200, ['bgColor' => 'CCCCCC'])->addText('No. Cuota', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1800, ['bgColor' => 'CCCCCC'])->addText('Fecha de Pago', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1400, ['bgColor' => 'CCCCCC'])->addText('Cuota Pactada', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1200, ['bgColor' => 'CCCCCC'])->addText('No. Cuota', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1800, ['bgColor' => 'CCCCCC'])->addText('Fecha de Pago', ['bold' => true, 'size' => 7], ['alignment' => 'center']);
    $table->addCell(1400, ['bgColor' => 'CCCCCC'])->addText('Cuota Pactada', ['bold' => true, 'size' => 7], ['alignment' => 'center']);

    // Mostrar ambas mitades en paralelo
    for ($i = 0; $i < $mitad; $i++) {
        $table->addRow(300, ['exactHeight' => true]);

        // ----- Columna izquierda -----
        if (isset($ppg1[$i])) {
            $p1 = $ppg1[$i];
            $fecha1 = date('d-m-Y', strtotime($p1['fechavenppg']));
            $cuota1 = $p1['capitalppg'] + $p1['interesesppg']+$p1['otrosppg']+ $p1['vinculado'];

            $table->addCell(1200)->addText($p1['cuotasppg'], ['size' => 7], ['alignment' => 'center']);
            $table->addCell(1800)->addText($fecha1, ['size' => 7], ['alignment' => 'center']);
            $table->addCell(1400)->addText(number_format($cuota1, 2), ['size' => 7], ['alignment' => 'center']);
        } else {
            $table->addCell(1200)->addText('');
            $table->addCell(1800)->addText('');
            $table->addCell(1400)->addText('');
        }

        // ----- Columna derecha -----
        if (isset($ppg2[$i])) {
            $p2 = $ppg2[$i];
            $fecha2 = date('d-m-Y', strtotime($p2['fechavenppg']));
            $cuota2 = $p2['capitalppg'] + $p2['interesesppg']+ $p2['otrosppg']+ $p2['vinculado'];

            $table->addCell(1200)->addText($p2['cuotasppg'], ['size' => 7], ['alignment' => 'center']);
            $table->addCell(1800)->addText($fecha2, ['size' => 7], ['alignment' => 'center']);
            $table->addCell(1400)->addText(number_format($cuota2, 2), ['size' => 7], ['alignment' => 'center']);
        } else {
            $table->addCell(1200)->addText('');
            $table->addCell(1800)->addText('');
            $table->addCell(1400)->addText('');
        }
    }
}
$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

//fechas formateadas
$fechadesembolso = date('d-m-Y', strtotime($fechadesembolso));
$fechavence = date('d-m-Y', strtotime($fechavence));

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Durante las fechas del ' . $fechadesembolso . ' hasta el ' . $fechavence . '.', ['bold' => false, 'name' => 'Arial', 'size' => 7]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Las condiciones en que cumpliremos con la obligación son las siguientes: -----------------------------------------------------------------------------', ['bold' => false, 'name' => 'Arial', 'size' => 7]); 


$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('INTERES: ', ['bold' => true, 'name' => 'Arial', 'size' => 7]); 
$textRun->addText('La suma adeudada devengará una tasa de interés variable, la cual podrá ser modificada por decisión unilateral de la parte acreedora, conforme a las 
condiciones del mercado o del sistema bancario nacional. La tasa de interés variable inicial es del ' . $tasaintere . '%  anual sobre saldos, interés que va calculada e incluido en 
las cuotas antes indicadas; conforme el artículo un mil novecientos cuarenta y ocho del Código Civil, la tasa de interés es pactada libremente por el deudor.', ['bold' => false, 'name' => 'Arial', 'size' => 7]); 

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('GASTOS ADMINISTRATIVOS: ', ['bold' => true, 'name' => 'Arial', 'size' => 7]); 
$textRun->addText('Adicional a los intereses se aplicará una cuota de gasto programado, que corresponde al gasto por formalización del crédito 
con un porcentaje del 5% sobre el total aprobado, el cual será amortizado mediante doce pagos de forma mensual. El gasto administrativo programado va 
calculado e incluido en las cuotas antes indicadas.', ['bold' => false, 'name' => 'Arial', 'size' => 7]); 

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('AHORRO VINCULADO: ', ['bold' => true, 'name' => 'Arial', 'size' => 7]); 
$textRun->addText('Adicional a los intereses y gastos administrativos se aplicará una cuota de ahorro vinculado, con un porsentaje de 5% sobre el total aprobado, el cual será amortizado 
de forma mensual, el saldo acumulado será devuelto al asociado al cancelar el crédito. El ahorro programado va calculadoen las cuotas antes indicadas', ['bold' => false, 'name' => 'Arial', 'size' => 7]); 

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Por incumplimiento de pago de cada una de las cuotas, antes expresadas se pagarán intereses moratorios variable del ocho por ciento (8%) mensual.  Todos los 
gastos que directa o indirectamente ocasione esta negociación son por nuestra cuenta, incluyendo los de cobranza judicial y/o extra-judicial, honorarios de 
abogados si fuere necesario. RENUNCIA AL FUERO DE SU DOMICILIO, el librador y el avalista, renuncian en forma voluntaria al fuero de sus respectivos 
domicilios y se someten a los tribunales que el tenedor de este pagaré elija.  Aceptamos que la falta de pago de una sola cuota de las amortizaciones estipuladas 
dará derecho al tenedor de este pagaré a dar por vencido el plazo y exigir el pago del saldo adeudado y también si se dictare mandamiento de ejecución y/o 
embargo en nuestra contra. ESTE PAGARE SE EMITE LIBRE DE PROTESTO, LIBRE DE FORMALIDADES DE PRESENTACIÓN, COBRO O 
REQUERIMIENTO. En caso de juicio, ni el tenedor de este pagaré ni los auxiliares que proponga deberán prestar garantía. En caso de remate servirá de base el 
avalúo o monto del saldo adeudado o la primera postura a opción del tenedor de este pagaré. El librador y el avalista aceptan como buenas, liquidas y exigibles las 
cuentas que el tenedor del pagaré presente. Los intereses han sido recalculados e incluidos en cuotas, pero aceptamos expresamente que el tenedor nos cobre 
proporcionalmente cualquier incremento que se de en la tasa activa promedio del sistema bancario nacional. Aceptamos expresamente el contenido íntegro del 
presente pagare, firman ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);
$textRun->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 7]);
$textRun->addText(' y ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);
$textRun->addText($nombrecompletoaval, ['bold' => true, 'name' => 'Arial', 'size' => 7]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('ACEPTAMOS LIBRE DE PROTESTO:', ['bold' => false, 'name' => 'Arial', 'size' => 7]);

// Tabla principal con dos columnas: LIBRADOR y AVALISTA
$table = $section->addTable(['alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
$table->addRow();

// === Celda 1: LIBRADOR ===
$cellLibrador = $table->addCell(4500);
$cellLibrador->addText('POR LIBRADOR:', ['bold' => true, 'name' => 'Arial', 'size' => 8]);
$cellLibrador->addTextBreak(1);


// Subtabla para alinear firma (izquierda) + recuadro (derecha)
$subTableL = $cellLibrador->addTable();
$subTableL->addRow();

// Celda de texto con salto de línea y centrado
$cellTextL = $subTableL->addCell(3000, ['valign' => 'center']);
$cellTextL->addText("F.___________________", ['name' => 'Arial', 'size' => 8], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$cellTextL->addText($nombrecliente, ['name' => 'Arial', 'size' => 8], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$cellTextL->addText("DPI: " . $dpiClienteFormateado, ['name' => 'Arial', 'size' => 8], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

// Celda del recuadro a la derecha
$subTableL->addCell(1000, [
    'borderSize' => 1,
    'borderColor' => '000000',
    'width' => 1000,
    'height' => 1000,
    'valign' => 'center'
]);

// === Celda 2: AVALISTA ===
$cellAvalista = $table->addCell(4500);
$cellAvalista->addText('POR AVALISTA:', ['bold' => true, 'name' => 'Arial', 'size' => 8]);
$cellAvalista->addTextBreak(1);

// Subtabla para alinear firma (izquierda) + recuadro (derecha)
$subTableR = $cellAvalista->addTable();
$subTableR->addRow();

// Celda de texto con salto de línea y centrado
$cellTextR = $subTableR->addCell(3000, ['valign' => 'center']);
$cellTextR->addText("F.___________________", ['name' => 'Arial', 'size' => 8], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$cellTextR->addText($nombrecompletoaval, ['name' => 'Arial', 'size' => 8], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$cellTextR->addText("DPI: " . $dpiFiadorFormateado, ['name' => 'Arial', 'size' => 8], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

// Celda del recuadro a la derecha
$subTableR->addCell(1000, [
    'borderSize' => 1,
    'borderColor' => '000000',
    'width' => 1000,
    'height' => 1000,
    'valign' => 'center'
]);

$section->addTextBreak(3);

$section->addText(
    "Pagaré No:  " . $dictamen,
    ['bold' => true, 'name' => 'Arial', 'size' => 8],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "PAGINA No. 2/2",
    ['bold' => true, 'name' => 'Arial', 'size' => 8],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('AUTENTICA:', ['bold' => true, 'name' => 'Arial', 'size' => 7]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText(' ', ['bold' => true, 'name' => 'Arial', 'size' => 7]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('En el municipio de Ixcán, del departamento de El Quiché, a los' . fechaCompleta($hoy) . ', ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);
$textRun->addText('como notario doy fe; ', ['bold' => true, 'name' => 'Arial', 'size' => 7]);
$textRun->addText(' que las firmas que anteceden son auténticas, por haber sido puestas el día de hoy en mi presencia por los-as señores-as: ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);
$textRun->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 7]);
$textRun->addText(' y ', ['bold' => false, 'name' => 'Arial', 'size' => 7]);
$textRun->addText($nombrecompletoaval, ['bold' => true, 'name' => 'Arial', 'size' => 7]);
$textRun->addText(', quienes se identifican con el Documento Personal de Identificación -DPI- con Código Único de Identificación -CUI- número: ' . 
$dpiClienteTexto . ' (' . $dpiClienteFormateado . ') y ' . $dpiFiadorTexto . ' (' . $dpiFiadorFormateado . '), extendidos por el Registro Nacional de las Personas de la República de 
Guatemala. Que las firmas relacionadas, están puestas en un PAGARE (libre de 
Protesto) de esta misma fecha a favor de la, COOPERATIVA INTEGRAL DE AHORRO Y CREDITO DJD MAYALAND, RESPONSABILIDAD 
LIMITADA, el cual está contenido en una hoja de papel bond tamaño oficio, hábil en su anverso y reverso, la cual número sello y firmo en constancia de 
su autenticidad. En fe de lo anterior las personas antes relacionadas vuelven a firmar al final de la presente acta de legalización. DOY FE.', ['bold' => false, 'name' => 'Arial', 'size' => 7]);

$section->addTextBreak(3);

// Crear tabla centrada
$table = $section->addTable([
    'alignment' => Jc::CENTER, // Centrar la tabla en el documento
]);

// Fila de firmas
$table->addRow();

// Columna 1
$cell1 = $table->addCell(4500, [
    'valign' => 'center', // Alineación vertical al centro
]);

$cell1TextRun = $cell1->addTextRun(['alignment' => Jc::CENTER]);
$cell1TextRun->addText('F.________________________', ['name' => 'Arial', 'size' => 7]);

$cell1->addText($nombrecliente, ['name' => 'Arial', 'size' => 7], ['alignment' => Jc::CENTER]);
$cell1->addText('DPI: ' . $dpiClienteFormateado, ['name' => 'Arial', 'size' => 7], ['alignment' => Jc::CENTER]);

// Columna 2
$cell2 = $table->addCell(4500, [
    'valign' => 'center',
]);

$cell2TextRun = $cell2->addTextRun(['alignment' => Jc::CENTER]);
$cell2TextRun->addText('F.________________________', ['name' => 'Arial', 'size' => 7]);

$cell2->addText($nombrecompletoaval, ['name' => 'Arial', 'size' => 7], ['alignment' => Jc::CENTER]);
$cell2->addText('DPI: ' . $dpiFiadorFormateado, ['name' => 'Arial', 'size' => 7], ['alignment' => Jc::CENTER]);

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
