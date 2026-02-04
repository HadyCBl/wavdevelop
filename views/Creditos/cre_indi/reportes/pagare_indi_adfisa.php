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
$query = "SELECT cre.DFecDsbls fechades,cre.DFecVen fecven,cre.noPeriodo cuotas,cre.NMonCuo moncuota,cre.Cestado,NCapDes mondes,MonSug monsug,cli.idcod_cliente ccodcli,cli.short_name nombrecli,cli.primer_name,cli.segundo_name, cre.DfecPago primerpago,
                cli.tercer_name,cli.primer_last,cli.segundo_last,cli.Direccion direccion,cli.profesion,age.id_agencia,usu.id_usu, cli.tel_no1 tel_no1, cli.date_birth cumple, cli.profesion profesion,
                cli.tel_no1,cli.no_identifica,usu.nombre nomanal,usu.apellido apeanal, cre.NintApro as intereses, cre.NtipPerC tipoperiodo, cre.CCodCta ccodcta, cli.estado_civil estadocivil, cre.Dictamen dictamen,
                IFNULL((SELECT nombre FROM tb_municipios WHERE codigo=age.municipio),'-') municipio,
                IFNULL((SELECT nombre FROM tb_departamentos WHERE id=age.departamento),'-') departamento,
                IFNULL((SELECT nombre FROM tb_municipios WHERE codigo=cli.muni_reside),'-') municipiocli,
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
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MIN(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.ncapita ELSE 0 END) AS capital_primera,
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MAX(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.ncapita ELSE 0 END) AS capital_ultima,
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MIN(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.nintere ELSE 0 END) AS interes_primera,
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MAX(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.nintere ELSE 0 END) AS interes_ultima,
                (SELECT SUM(ppg2.nintere) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_interes,
                (SELECT SUM(ppg2.NAhoProgra) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_vinculado,
                (SELECT SUM(ppg2.OtrosPagos) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_otros
                FROM Cre_ppg ppg
                WHERE ccodcta=?
                ORDER BY cnrocuo;";
$qgarantia = "SELECT clig.descripcionGarantia descripcionGarantia, clig.direccion direccioncliente
                FROM tb_garantias_creditos tgc 
                INNER JOIN cli_garantia clig ON clig.idGarantia=tgc.id_garantia 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=clig.idCliente
                WHERE tgc.id_cremcre_meta=? AND clig.idTipoGa !=1 ;";

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
    $garantia = $database->getAllResults($qgarantia, [$codcuenta]);
    if (empty($ppg)) {
        // $showmensaje = true;
        // throw new Exception("No se encontraron datos de PPG");
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
$primerpago = $result[0]["primerpago"];
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
$ultimacuota = $ppg[0]["capital_ultima"];
$ultimacuota2 = numeroALetras($ultimacuota);
$primeracouta = $ppg[0]["capital_primera"];
$primeracuota2 = numeroALetras($primeracouta);

$ultimointeres = $ppg[0]["interes_ultima"];
$ultimointeres2 = numeroALetras($ultimointeres);
$primerinteres = $ppg[0]["interes_primera"];
$primerinteres2 = numeroALetras($primerinteres);

$total_primera_cuota = $primeracouta + $primerinteres;
$total_ultima_cuota = $ultimacuota + $ultimointeres;

$descripcionGarantia = (empty($garantia)) ? "0" : $garantia[0]['descripcionGarantia'];

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
// $header = $section->addHeader();

// // // Crear una tabla para el layout horizontal
// // $table = $header->addTable([
// //     'cellMargin' => 0,
// //     'width' => 100 * 50, // Ancho completo (100%)
// // ]);

// $table->addRow();


$section->addTextBreak(1);

$section->addText(
    "PAGARÉ SIN PROTESTO",
    ['bold' => true, 'name' => 'Bernard MT Condensed', 'size' => 14],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "________________________________",
    ['bold' => true, 'name' => 'Bernard MT Condensed', 'size' => 2],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "Prestamo:      01224   /  ADFISA",
    ['normal' => true, 'name' => 'Arial', 'size' => 10],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "__________________________________________________________________________________________________________________________________",
    ['bold' => true, 'name' => 'Bernard MT Condensed', 'size' => 2],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "Pagaré:             " . $dictamen . '   / ADFISA',
    ['normal' => true, 'name' => 'Arial', 'size' => 10],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "________________________________",
    ['bold' => true, 'name' => 'Bernard MT Condensed', 'size' => 2],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);


//fechas formateadas
$fechadesembolso = date('d-m-Y', strtotime($fechadesembolso));
$fechavence = date('d-m-Y', strtotime($fechavence));
$fechaprimerpago = date('d-m-Y', strtotime($primerpago));
$fechadiapago = date('d', strtotime($primerpago));


$hoy2 = DateTime::createFromFormat('d-m-Y', $hoy);

// Comprobar si la creación fue exitosa
if ($hoy2 === false) {
    die("Error al procesar la fecha. Asegúrate de que el formato de entrada sea 'dd-mm-yyyy'.");
}
$formato = new IntlDateFormatter(
    'es', 
    IntlDateFormatter::FULL, 
    IntlDateFormatter::NONE, 
    'America/Guatemala',
    IntlDateFormatter::GREGORIAN,
    'EEEE, dd MMMM yyyy'
);

$fecha_formateada_hoy = $formato->format($hoy2);



$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('PROMESA INCONDICIONAL DE PAGO: ' . $nombrecliente . ', quien es de ' . $edadClienteNumero . ' Años, ' . $estadocivilcli . ', Guatemalteco de este domicilio, títular del Documento Personal de Identificación (DPI) con el número de CUI' . $dpiClienteFormateado . ' extendida en el municipio de ' . $municipiocli . ' departamento de ' . $departamentocli . ' , a quien en lo sucesivo de este pagaré se le denominará "LA PARTE DEUDORA"; prometeincondicionalmente pagar a la orden de la entidad denominada  " ASESORIA DE DESARROLLO FINANCIERO INTEGRAL, S.A ", que puede abreviarse "ADFISA" en adelante denominado "LA PARTE ACREEDORA", la cantidad de ' . $cantidadenletras . ' ( Q' . $monto_total . ' ). Plazo: El plazo para el pago total de la suma adeudada es de '. $nocuotas . ' meses contados a partir del ' . $fechadesembolso . 'por lo que el plazo vencerá el ' . $fechavence . '. Pago: La cantidad adeudada será pagada a "LA PARTE ACREEDORA", en ' . $nocuotas . ' pagos, mensuales y consecutivos, de Q' . $total_primera_cuota . ' y un ultimo de Q' .  $total_ultima_cuota . ' , comenzando a partir del ' . $fechaprimerpago . 'y así cada ' . $fechadiapago . ' de los siguientes meses hasta su cancelación total. INTERESES: La suma adeudada devengará
 una tasa de interés variable, la cual podrá ser modificada por decisión unilateral de la parte acreedora conforme a las condiciones de
 mercado. La tasa de interés inicial se fija en el ' . $tasaintere . ' % anual, interés que va calculado e incluido en la cuota antes indicada. Conforme al
 artículo un mil novecientos cuarenta y ocho del Código Civil, la tasa de interés pactada es aceptada libremente por la parte deudora. Así
 mismo la parte deudora reconoce y pagará un interés moratorio del diez por ciento mensual calculado sobre cualquier cuota dejada de
 pagar, hasta la efectiva cancelación de la misma, sin perjuicio de entablar la acción ejecutiva correspondiente. GARANTIA: Este pagaré
 tendrá la garantía total de "LA PARTE DEUDORA", con todos sus bienes presentes y futuros que tenga, indicados e individualizados en la
 solicitud de crédito y en el estado patrimonial adjunto a la solicitud. Específicamente la parte deudora constituye gravamen sobre ' . $descripcionGarantia . ' a favor de: ' . $nombrecompletoaval . ' que se localiza en '. $direccionaval . ' del municipio de: ' . $municipiocli . ' del Departamento de: ' . $departamentocli . ' LUGAR DE PAGO: Todo pago
 de capital e intereses, se hará efectivo en las oficinas de "LA PARTE ACREEDORA", ubicadas en la Avenida Petapa 15 avenida 50-56 zona
 12 colonia la Colina, Ciudad de Guatemala, sin necesidad de cobro o requerimiento alguno, en quetzales moneda de curso legal en
 Guatemala. VENCIMIENTO: El último tenedor y titular de este título de crédito podrá exigir el pago del adeudo, extrajudicial o
 judicialmente, dando por vencido anticipadamente el plazo, en los siguientes casos: a) Si "LA PARTE DEUDORA" incumpliere con un solo
 pago de las cuotas a las cuales se obligó en este pagaré en el día señalado para cada pago; b) Si "LA PARTE DEUDORA" dejare de cumplir
 con sus obligaciones frente a terceras personas o admitiere, escrito, encontrarse incapacitada para pagar sus deudas o hiciere cualquier
 arreglo de cualquier tipo o naturaleza que tenga por objeto compensar, ceder o dar en pago a sus acreedora bienes, derechos o acciones
 de su propiedad; c) Si se promoviere por "LA PARTE DEUDORA", concurso voluntario de acreedores, o si se entablara en su contra
 concurso forzoso de acreedores o proceso de quiebra; y d) Si "LA PARTE DEUDORA" fuere demandada por cualquier clase de acción
 judicial, o fuese objeto de intervención, embargo de sus bienes, anotación de demanda o que se decretare cualquier medida precautoria
 en su contra o de sus bienes. LEYES APLICABLES: El ejercicio de los derechos y el cumplimiento de las obligaciones que se deriven del
 presente pagaré se regirán de conformidad con las normas del Código de Comercio de Guatemala, contenido en el Decreto 2-70 del
 Congreso de la República. El ejercicio de los derechos y el cumplimiento de las obligaciones que en material procesal se deriven del
 presente pagaré, se regirán de conformidad con las normas procesales del país donde se ejercite la acción ejecutiva que se derive del
 presente título. EFECTOS PROCESALES: "LA PARTE DEUDORA" y los avalistas, aceptan lo siguiente: a) Renuncian al fuero de sus
 domicilios y se somete a la competencia de los tribunales que la parte acreedora elija; b) Que cualquier depositario o interventor que se
 nombre, no estará obligado a prestar fianza o garantía de ninguna clase; c) Como buenas, exactas, liquidas, exigibles y de plazo vencido
 las cuentas que se le presente con ocasión de este título de crédito; d) Que todos los gastos y honorarios que genere este titulo de
 crédito, serán por su cuenta, incluyendo los gastos y honorarios que se generen por el cobro judicial o extrajudicial, si fuese el caso; e)
 Que este pagaré esta libre de protesto, de formalidad alguna para su presentación y cobro o requerimiento alguno. DOMICILIO: "LA
 PARTE DEUDORA" acepta expresamente que cualquier notificación, citación y emplazamiento se haga en la siguiente dirección ' . $direccionaval . ' , dando por bien hechas las que se allí se hagan, al
 menos que hubiere dado aviso a "LA PARTE ACREEDORA" de cualquier cambio de la misma. El presente pagaré esta exento del Impuesto
 de timbre Fiscal y de Papel Sellado Especial para Protocolos, conforme al artículo 11 numeral 9 del Decreto 37-92 del Congreso de la
 República.
 EN TESTIMONIO DE LO ANTERIOR, EXTIENDO EL PRESENTE PAGARE EN LA CIUDAD DE San Cristobal Verapaz DEL
 DEPARTAMENTO DE Alta Verapaz EL DIA ' . $fecha_formateada_hoy . '.', ['bold' => false, 'name' => 'Arial', 'size' => 10 ]);

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

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS


$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('F: '. $nombrecliente , ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('CUI:   ' . $dpiClienteFormateado  .  '                 _____________________________________', ['bold' => false, 'name' => 'Arial', 'size' => 10]); 

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Extendida en: ' . $municipiocli . ', ' . $departamentocli, ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('AVAL:', ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Por este medio los signatarios o los que dejan su huella digital, si fuese el caso, titulares de los documentos personales de identificación
 que se indican, se constituyen expresamente en avalistas del presente pagaré en todas y cada una de las obligaciones que asume la parte
 libradora del mismo.', ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('F: '. $nombrecompletoaval , ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('CUI:   ' . $dpiFiadorFormateado  .  '                 _____________________________________', ['bold' => false, 'name' => 'Arial', 'size' => 10]); 

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Extendida en: ' . $municipiocli . ', ' . $departamentocli, ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS






$section->addTextBreak(3);

$section->addText(
    "PAGARÉ SIN PROTESTO",
    ['bold' => true, 'name' => 'Bernard MT Condensed', 'size' => 14],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "Prestamo",
    ['normal' => true, 'name' => 'Arial', 'size' => 10],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);
$section->addText(
    "Pagaré",
    ['normal' => true, 'name' => 'Arial', 'size' => 10],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'lineHeight' => 0.9,
        'spaceAfter' => 40,
        'spaceBefore' => 0
    ]
);


$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('A ruego de las personas que por  no saber firmar dejaron impresa la huella digital, si  fuese el caso', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS
$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 7]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS


$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS
$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('_________________________________________________' , ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('F: Ronaldo Alexander Cú Suc' , ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('CUI:  2762 04719 1603', ['bold' => false, 'name' => 'Arial', 'size' => 10]); 

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('Extendida en:   San Cristobal Verapaz Alta Verapaz', ['bold' => false, 'name' => 'Arial', 'size' => 10]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('  ', ['bold' => false, 'name' => 'Arial', 'size' => 10]); //ESPACIO VACIO PARA SEPARAR LAS LINEAS


$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('AUTENTICA:', ['bold' => true, 'name' => 'Arial black', 'size' => 11]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText(' ', ['bold' => true, 'name' => 'Arial', 'size' => 7]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText('En la ciudad de San Cristobal Verapaz del Departamento Alta Verapaz el día ' . $fecha_formateada_hoy . ' el infrascrito Notario da fe: a) Que las
 firmas o huellas digitales, si fuese el caso, que anteceden son auténticas por haber sido puestas a mi presencia por los siguientes
 señores, quienes se identifican con los Documentos Personal de Indentificación (DPI) que se indican más adelante, b) que las firmas, o
 huellas digitales, están puestas en un pagaré de esta misma fecha a favor de la entidad ASESORIA DE DESARROLLO FINANCIERO
 INTEGRAL, S.A. que puede abreviarse "ADFISA". Los signatarios firman la presente acta de legalización junto con el Notario autorizante
 que da fe de lo expuesto. Que por parte de las personas que por no saber firmar dejaron impresa la huella digital, si fuera el caso, firma a
 su ruego el señor Ronaldo Alexander Cú Suc quien se identifica con el Documento Personal de Indentificación que registra el número de
 CUI   2762 04719 1603 extendida en  el municipio de  San Cristobal Verapaz del departamento de  Alta Verapaz.', ['bold' => false, 'name' => 'Arial', 'size' => 10 ]);

$section->addTextBreak(3);

// Crear tabla centrada
$table = $section->addTable([
    'alignment' => Jc::CENTER, // Centrar la tabla en el documento
]);

// Fila de firmas
$table->addRow();

// Columna 1
$cell1 = $table->addCell(8000, [
    'valign' => 'center', // Alineación vertical al centro
]);

$cell1TextRun = $cell1->addTextRun(['alignment' => Jc::LEFT]);
$cell1TextRun->addText('__________________________________________', ['name' => 'Arial', 'size' => 10]);


$cell1TextRun = $cell1->addTextRun(['alignment' => Jc::LEFT]);
$cell1TextRun->addText('F: ' . $nombrecliente, ['name' => 'Arial', 'size' => 10]);
$cell1->addText('CUI: ' . $dpiClienteFormateado, ['name' => 'Arial', 'size' => 10], ['alignment' => Jc::LEFT]);
$cell1->addText('Extendida en: ' . $municipiocli . ', ' . $departamentocli, ['name' => 'Arial', 'size' => 10], ['alignment' => Jc::LEFT]);

// Columna 2
$cell2 = $table->addCell(6000, [
    'valign' => 'center',
]);

$cell1TextRun = $cell2->addTextRun(['alignment' => Jc::LEFT]);
$cell1TextRun->addText('_________________________________________', ['name' => 'Arial', 'size' => 10]);

$cell2TextRun = $cell2->addTextRun(['alignment' => Jc::LEFT]);
$cell2TextRun->addText('F: ' . $nombrecompletoaval, ['name' => 'Arial', 'size' => 10]);
$cell2->addText('CUI: ' . $dpiFiadorFormateado, ['name' => 'Arial', 'size' => 10], ['alignment' => Jc::LEFT]);
$cell2->addText('Extendida en: ' . $municipiocli . ', ' . $departamentocli, ['name' => 'Arial', 'size' => 10], ['alignment' => Jc::LEFT]);

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
