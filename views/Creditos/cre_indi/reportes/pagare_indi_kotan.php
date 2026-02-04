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
require '../../../../fpdf/fpdf.php';
include __DIR__ . '/../../../../includes/BD_con/db_con.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';
// include '../../../../src/funcphp/func_gen.php';

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
                cli.tel_no1,cli.no_identifica,usu.nombre nomanal,usu.apellido apeanal, cre.NintApro as intereses, cre.NtipPerC tipoperiodo, cre.CCodCta ccodcta, cli.estado_civil estadocivil, cre.Dictamen dictamen, cli.genero genero,
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
                ppg.ncapita capitalcuota,
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
$qGarantia = "SELECT tgc.id_garantia, clig.descripcionGarantia AS descripcionGarantia, clig.direccion AS direccionGarantia
                FROM tb_garantias_creditos tgc
                INNER JOIN cli_garantia clig ON clig.idGarantia=tgc.id_garantia
                WHERE tgc.id_cremcre_meta=?
                AND clig.idTipoGa!='1';";
$interesmora = "SELECT porcentaje_mora AS tasa_interes_mora FROM cre_productos INNER JOIN cremcre_meta ON cre_productos.id=cremcre_meta.CCODPRD WHERE cremcre_meta.CCODCTA=?;";


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
    $interesmoraresult = $database->getAllResults($interesmora, [$codcuenta]);
    if (empty($interesmoraresult)) {
        $showmensaje = true;
        throw new Exception("No se encontró la tasa de interés de mora");
    }

    $garantia = $database->getAllResults($qGarantia, [$codcuenta]);
    if (empty($garantia)) {
        $showmensaje = true;
        throw new Exception("No se encontraron datos de garantia");
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
        1 => 'UNO',
        2 => 'DOS',
        3 => 'TRES',
        4 => 'CUATRO',
        5 => 'CINCO',
        6 => 'SEIS',
        7 => 'SIETE',
        8 => 'OCHO',
        9 => 'NUEVE',
        10 => 'DIEZ',
        11 => 'ONCE',
        12 => 'DOCE',
        13 => 'TRECE',
        14 => 'CATORCE',
        15 => 'QUINCE',
        16 => 'DIECISÉIS',
        17 => 'DIECISIETE',
        18 => 'DIECIOCHO',
        19 => 'DIECINUEVE',
        20 => 'VEINTE',
        21 => 'VEINTIUNO',
        22 => 'VEINTIDÓS',
        23 => 'VEINTITRÉS',
        24 => 'VEINTICUATRO',
        25 => 'VEINTICINCO',
        26 => 'VEINTISÉIS',
        27 => 'VEINTISIETE',
        28 => 'VEINTIOCHO',
        29 => 'VEINTINUEVE',
        30 => 'TREINTA',
        31 => 'TREINTA Y UNO'
    ];

    if ($numero >= 1 && $numero <= 31) {
        return $unidades[$numero];
    }

    return 'NÚMERO INVÁLIDO';
}

// Función para convertir años a letras
function anioALetras($anio)
{
    $miles = [
        2000 => 'DOS MIL',
        2001 => 'DOS MIL UNO',
        2002 => 'DOS MIL DOS',
        2003 => 'DOS MIL TRES',
        2004 => 'DOS MIL CUATRO',
        2005 => 'DOS MIL CINCO',
        2006 => 'DOS MIL SEIS',
        2007 => 'DOS MIL SIETE',
        2008 => 'DOS MIL OCHO',
        2009 => 'DOS MIL NUEVE',
        2010 => 'DOS MIL DIEZ',
        2011 => 'DOS MIL ONCE',
        2012 => 'DOS MIL DOCE',
        2013 => 'DOS MIL TRECE',
        2014 => 'DOS MIL CATORCE',
        2015 => 'DOS MIL QUINCE',
        2016 => 'DOS MIL DIECISÉIS',
        2017 => 'DOS MIL DIECISIETE',
        2018 => 'DOS MIL DIECIOCHO',
        2019 => 'DOS MIL DIECINUEVE',
        2020 => 'DOS MIL VEINTE',
        2021 => 'DOS MIL VEINTIUNO',
        2022 => 'DOS MIL VEINTIDÓS',
        2023 => 'DOS MIL VEINTITRÉS',
        2024 => 'DOS MIL VEINTICUATRO',
        2025 => 'DOS MIL VEINTICINCO',
        2026 => 'DOS MIL VEINTISÉIS',
        2027 => 'DOS MIL VEINTISIETE',
        2028 => 'DOS MIL VEINTIOCHO',
        2029 => 'DOS MIL VEINTINUEVE',
        2030 => 'DOS MIL TREINTA'
    ];

    return isset($miles[$anio]) ? $miles[$anio] : 'AÑO NO SOPORTADO';
}

// Función para convertir un número a  texto, se usa para las edades
function numeroATexto($numero)
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

    // --- Convertir solo la parte entera ---
    $convertir = function($n) use (&$convertir, $unidades, $decenas, $centenas) {
        if ($n == 0) return 'CERO';
        if ($n < 20) return $unidades[$n];
        if ($n < 30) return $n == 20 ? 'VEINTE' : 'VEINTI' . $unidades[$n - 20];
        if ($n < 100) return $decenas[intval($n / 10)] . ($n % 10 ? ' Y ' . $unidades[$n % 10] : '');
        if ($n == 100) return 'CIEN';
        if ($n < 1000) return $centenas[intval($n / 100)] . ($n % 100 ? ' ' . $convertir($n % 100) : '');
        if ($n < 1000000) {
            $miles = intval($n / 1000);
            $resto = $n % 1000;
            $textoMiles = $miles == 1 ? 'MIL' : $convertir($miles) . ' MIL';
            return $resto > 0 ? $textoMiles . ' ' . $convertir($resto) : $textoMiles;
        }
        return (string)$n; // fallback
    };

    // --- Manejo de parte entera y decimal ---
    $partes = explode('.', number_format($numero, 2, '.', '')); // fuerza 2 decimales
    $entero = intval($partes[0]);
    $decimal = intval($partes[1]);

    $textoEntero = $convertir($entero);

    if ($decimal > 0) {
        $textoDecimal = " CON " . $convertir($decimal);
    } else {
        $textoDecimal = "";
    }

    return trim($textoEntero . $textoDecimal);
}



function formatearDPI($dpi)
{
    // Asegurarse de que el DPI tenga 13 dígitos
    $dpi = str_pad($dpi, 13, '0', STR_PAD_LEFT);

    // Formato: XXXX XXXXX XXXX
    $parte1 = substr($dpi, 0, 4);
    $parte2 = substr($dpi, 4, 5);
    $parte3 = substr($dpi, 9, 4);

    return $parte1 . ' ' . $parte2 . ' ' . $parte3;
}

function dpiATexto($dpi)
{
    // Asegurarse de que el DPI tenga 13 dígitos
    $dpi = str_pad($dpi, 13, '0', STR_PAD_LEFT);

    // Dividir en las tres partes
    $parte1 = intval(substr($dpi, 0, 4));
    $parte2 = intval(substr($dpi, 4, 5));
    $parte3 = intval(substr($dpi, 9, 4));
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
    $partes = explode('-', $fecha);
    $anio = (int)$partes[0];
    $mes = (int)$partes[1];
    $dia = (int)$partes[2];

    $meses = [
        1 => 'ENERO',
        2 => 'FEBRERO',
        3 => 'MARZO',
        4 => 'ABRIL',
        5 => 'MAYO',
        6 => 'JUNIO',
        7 => 'JULIO',
        8 => 'AGOSTO',
        9 => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE'
    ];

    $diaTexto = numeroALetrasCompleto($dia);
    $mesTexto = $meses[$mes];
    $anioTexto = anioALetras($anio);

    $plural = ($dia == 1) ? 'DÍA' : 'DÍAS';

    return $diaTexto . ' DE ' . $mesTexto . ' DEL AÑO ' . $anioTexto;
}

$ppgtemp = creppg_temporal($codcuenta, $conexion);
// $opResult = array('status' => 0, 'mensaje' => $ppgtemp);
//     echo json_encode($opResult);
//     return;
$fechadesembolso = $result[0]["fechades"];
$fechavence = ($result[0]["Cestado"] == "F") ? $result[0]["fecven"] : $ppgtemp[count($ppgtemp) - 1]["dfecven"];
$fechaenletras = fechaCompleta($fechadesembolso);
$fechavenceletras = fechaCompleta($fechavence);
$codcliente = $result[0]["ccodcli"];
$telcliente = $result[0]["tel_no1"];
$nombreasesor = $result[0]["nomanal"];
$tasaintere = $result[0]["intereses"];
$tasainteres = numeroATexto($tasaintere);
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
$generocliente = $result[0]["genero"];
$profesioncliente = $result[0]["profesion"];
$dictamen = $result[0]["dictamen"];

$totalintereses = $ppg[0]["total_interes"];
$totalivinculados = $ppg[0]["total_vinculado"];
$totalotros = $ppg[0]["total_otros"];

$monto = ($result[0]["Cestado"] == "F") ? $result[0]["mondes"] : $result[0]["monsug"];
$format_monto = new NumeroALetras();
$monto_total = $monto + $totalintereses;
$monto_total2 = $monto + $totalintereses + $totalivinculados + $totalotros;
$cantidadenletras = $format_monto->toMoney($monto_total, 2, 'QUETZALES', 'CENTAVOS');
$monto2 = numeroALetras($monto);

//PARA LA TABLA DE PAGOS
$fechapagoppg = $ppg[0]["fechavenppg"];
$cuotasppg = $ppg[0]["cuotasppg"];
$interesesppg = $ppg[0]["interesesppg"];
$capitalppg = $ppg[0]["capitalppg"];
$capitalppg2 = numeroALetras($capitalppg);
$otrosppg = $ppg[0]["otrosppg"];
$saldo_capital = $ppg[0]["saldo_capital"];
$viculosaho = $ppg[0]["vinculado"];
$ultimacuota = $ppg[0]["capital_ultima"];
$ultimacuota2 = numeroALetras($ultimacuota);
$primeracouta = $ppg[0]["capital_primera"];
$primeracuota2 = numeroALetras($primeracouta);

$interes_primera = $ppg[0]["interes_primera"];
$interes_ultima = $ppg[0]["interes_ultima"];

$primeracoutamensual = $primeracouta + $interes_primera;
$ultimacuotamensual = $ultimacuota + $interes_ultima;


$nocuotas = $result[0]["cuotas"];
$nocuotassob = $nocuotas - 1;
$nocuotas2 = numeroATexto($nocuotassob);
$hoy3 = date("Y-m-d");
$hoy3text = fechaCompleta($hoy3);

$tiposPeridodo = [
    'D' => 'DIARIO',
    'S' => 'SEMANAL',
    'Q' => 'CATORCENAL',
    '15D' => 'QUINCENAL',
    '1M' => 'MENSUAL',
    '1C' => 'MENSUAL',
    '2M' => 'BIMENSUAL',
    '3M' => 'TRIMESTRAL',
    '6M' => 'SEMESTRAL',
    '1' => 'ANUAL',
    '0D' => 'OTROS'
];

$tiposPeridodo2 = [
    'D' => 'Dias',
    'S' => 'Semanas',
    'Q' => 'CATORCENAs',
    '15D' => 'QUINCENAs',
    '1M' => 'MESES',
    '1C' => 'MESES',
    '2M' => 'BIMESTRES',
    '3M' => 'TRIMESTRES',
    '6M' => 'SEMESTRES',
    '1' => 'AÑOS',
    '0D' => 'OTROS'
];

$tiposPeridodo3 = [
    'D' => 'Dia',
    'S' => 'Semana',
    'Q' => 'CATORCENA',
    '15D' => 'QUINCENA',
    '1M' => 'MES',
    '1C' => 'MES',
    '2M' => 'BIMESTRE',
    '3M' => 'TRIMESTRE',
    '6M' => 'SEMESTRE',
    '1' => 'AÑO',
    '0D' => 'OTROS'
];

$tipoperiodo = $result[0]["tipoperiodo"];
$tiposPeridodoText = $tiposPeridodo2[$tipoperiodo];

$tipoPeriodoText1 = $tiposPeridodo[$tipoperiodo];

$tipoPeriodoText3 = $tiposPeridodo3[$tipoperiodo];

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
$interesfloat = (float)$totalintereses;
$interestotal = numeroALetras($interesfloat);

$razonsocial = ($result[0]['id_usu'] == 17) ? "GRUPO EMPRESARIAL SERVIALIANZA" : "INJELIFID S.A.";
$namecomercial = ($result[0]['id_usu'] == 17) ? "CREDIRAPI" : "NAWAL";
$phpWord = new PhpWord();

// Configuración para tamaño carta (Letter) con márgenes de 2.5 cm
$section = $phpWord->addSection([
    'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5), // Ancho carta en pulgadas
    'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(12.0), // Alto carta en pulgadas
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0), // Margen superior 2.5 cm
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), // Margen derecho 2.5 cm
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5), // Margen inferior 2.5 cm
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1.5)  // Margen izquierdo 2.5 cm
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


$section->addTextBreak(1);

$section->addText('PAGARE SIN PROTESTO', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

$table2 = $section->addTable(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$table2->addRow();
$table2->addCell(3000)->addText('Ticolal Pueblo Nuevo Jucup', ['name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$cell = $table2->addCell(5000);
$textRun = $cell->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);

$textRun->addText('Préstamo: ' . $codigocred . ' / AGENCIA 001', ['name' => 'Arial', 'size' => 10, 'underline' => 'single']);
$textRun->addTextBreak(); // Salto de línea
$textRun->addText('Pagaré:     ' . $dictamen, ['name' => 'Arial', 'size' => 10, 'underline' => 'single']);

// // Celda derecha para la imagen
// $cellImage = $table->addCell(2500);
// $cellImage->addImage(
//     '../../../../includes/img/credisuma.png',
//     [
//         'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(4),
//         'height' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(2),
//         'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT
//     ]
// );

// Agregar encabezado a la sección para que la imagen aparezca en cada página
// $header = $section->addHeader();
// $headerTable = $header->addTable(['width' => 15000, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH]);
// $headerTable->addRow();
// $headerTable->addCell(8000)->addText('');
// $headerImageCell = $headerTable->addCell(2000);
// $headerImageCell->addImage(
//     '../../../../includes/img/credisuma.png',
//     [
//         'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(4),
//         'height' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(2),
//         'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT
//     ]
// );


$section->getStyle()->setHeaderHeight(300);

$header = $section->addHeader();
$headerTable = $header->addTable([
    'width' => 15000,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
    'cellMarginTop' => 0,
    'cellMarginBottom' => 0,
    'cellMarginLeft' => 0,
    'cellMarginRight' => 0,
]);

$headerTable->addRow(400);
$headerTable->addCell(8000)->addText('');

$headerImageCell = $headerTable->addCell(2000);
$headerImageCell->addImage(
    '../../../../includes/img/kotanh.png',
    [
        'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(3),
        'height' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(1.5),
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT
    ]
);


// $section->addTextBreak(1);

$fechadesembolso = date('d-m-Y', strtotime($fechadesembolso));
$fechavence = date('d-m-Y', strtotime($fechavence));
$diafechadesembolso = date('d', strtotime($fechadesembolso));

$textRun = $section->addTextRun($paragraphJustify);

if($generocliente == 'F'){
    $Nacionalidad = 'Guatemalteca';
}else{
    $Nacionalidad = 'Guatemalteco';
}

$descripcionGarantia = "";
if(empty($garantia)){
    $descripcionGarantia = "0";
    $direcgarantia = "0";
}else{
    if(count($garantia) > 1){
        for($i = 0; $i < count($garantia); $i++){

            if($i == count($garantia) - 1){
                $descripcionGarantia .= 'y ' . $garantia[$i]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en '. $garantia[$i]['direccionGarantia'] . '. ';
            }else{
                $descripcionGarantia .= '' . $garantia[$i]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en ' . $garantia[$i]['direccionGarantia'] . ', ';
            }
        }
    }else{
        $descripcionGarantia = '' . $garantia[0]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en ' . $garantia[0]['direccionGarantia'] . '. ';
    }

}


$tasamora = $interesmoraresult[0]["tasa_interes_mora"];


$textRun->addText('PROMESA INCONDICIONAL DE PAGO: ' .  $nombrecliente . ' quien es de ' . $edadClienteNumero . ' Años, ' . $estadocivilcli . ', ' . $Nacionalidad . ' de este domicilio, titular del Documento Personal de Identificación (DPI) con el número de CUI ' . $dpiClienteFormateado . ' extendida en el municipio de ' . $municipiocli . ' departamento de ' . $departamentocli. ', a quien en lo sucesivo de este pagaré se le denominará "LA PARTE DEUDORA"; promete incondicionalmente pagar a la orden de la entidad denominada "GRUPO KOTANH, SOCIEDAD ANONIMA", en adelante denominado "LA PARTE ACREEDORA", la cantidad de ' . $monto2 . ' ( Q' . $monto . ' ). PLAZO: El plazo para el pago total de la suma adeudada es de ' . $nocuotas . ' ' . $tiposPeridodoText . ' contados a partir del día de hoy por lo que el plazo vencerá el día ' . $fechavenceletras . '. PAGO: La cantidad adeudada será pagada a "LA PARTE ACREEDORA", en ' . $nocuotas . ' pagos, ' . $tipoPeriodoText1 . ' y consecutivos, de Q' . $primeracoutamensual
. ' cada uno, y un último de Q' . $ultimacuotamensual . ', comenzando a partir del día ' . $fechaenletras . ' y así cada ' . $diafechadesembolso . ' de los siguientes meses hasta su cancelación total. INTERESES: La suma adeudada devengará una tasa de interés variable, la cual podrá ser modificada por decisión unilateral de la parte acreedora conforme a las condiciones de mercado. La tasa de interés inicial se fija en el ' . $tasaintere . ' % anual, interés que va calculado e incluido en la cuota antes indicada. Conforme al artículo un mil novecientos cuarenta y ocho del Código Civil, la tasa de interés pactada es aceptada libremente por la parte deudora. Así mismo la parte deudora reconoce y pagará un interés moratorio del ' . $tasamora . ' por ciento mensual calculado sobre cualquier cuota dejada de pagar, hasta la efectiva cancelación de la misma, sin perjuicio de entablar la acción ejecutiva correspondiente. GARANTIA: Este pagaré tendrá la garantía total de "LA PARTE DEUDORA", con todos sus bienes presentes y futuros que tenga, indicados e individualizados en la solicitud de crédito y en el estado patrimonial adjunto a la solicitud. Específicamente la parte deudora constituye gravamen sobre ' . $descripcionGarantia . ' LUGAR DE PAGO: Todo pago de capital e intereses, se hará efectivo en las oficinas de "LA PARTE ACREEDORA", ubicada en la aldea Ticolal Pueblo Nuevo Jucup, del municipio de San Sebastián Coatán departamento de Huehuetenango, sin necesidad de cobro o requerimiento alguno, en quetzales moneda de curso legal en Guatemala. VENCIMIENTO: El último tenedor y titular de este título de crédito podrá exigir el pago del adeudo, extrajudicial o judicialmente, dando por vencido anticipadamente el plazo, en los siguientes casos: a) Si "LA PARTE DEUDORA" incumpliere con un solo pago de las cuotas a las cuales se obligó en este pagaré en el día señalado para cada pago; b) Si "LA PARTE DEUDORA" dejare de cumplir con sus obligaciones frente a terceras personas o admitiere, escrito, encontrarse incapacitada para pagar sus deudas o hiciere cualquier arreglo de cualquier tipo o naturaleza que tenga por objeto compensar, ceder o dar en pago a sus acreedora bienes, derechos o acciones de su propiedad; c) Si se promoviere por "LA PARTE  DEUDORA", concurso voluntario de acreedores, o si se entablara en su contra concurso forzoso de acreedores o proceso de quiebra; y d) Si "LA PARTE DEUDORA" fuere demandada por cualquier clase de acción judicial, o fuese objeto de intervención, embargo de sus bienes, anotación de demanda o que se decretare cualquier medida precautoria en su contra o de sus bienes. LEYES APLICABLES: El ejercicio de los derechos y el cumplimiento de las obligaciones que se deriven del presente pagaré se regirán de conformidad con las normas del Código de Comercio de Guatemala, contenido en el Decreto 2-70 del Congreso de la República. El ejercicio de los derechos y el cumplimiento de las obligaciones que en material procesal se deriven del presente pagaré, se regirán de conformidad con las normas procesales del país donde se ejercite la acción ejecutiva que se derive del presente título. EFECTOS PROCESALES: "LA PARTEDEUDORA" y los avalistas, aceptan lo siguiente: a) Renuncian al fuero de sus domicilios y se somete a la competencia de los tribunales que la parte acreedora elija; b) Que cualquier depositario o interventor que se nombre, no estará obligado a prestar fianza o garantía alguna; c) Como buenas, exactas, liquidas, exigibles y plazo vencido las cuentas que se le presente con ocasión del este título; d) Que todos los gastos y honorarios que genere este título será por su cuenta incluyendo los gastos y honorarios generados por cobro judicial u extrajudicial si fuese necesario; e) Que este pagaré está libre protesto formalidad alguna para su presentación cobro requerimiento alguno. DOMICILIO: "LA PARTE DEUDORA" acepta expresamente que cualquier notificación citación emplazamiento se haga en dirección siguiente: ' . $diredomicilio . ' del Municipio '. $municipiocli .' Departamento '. $departamentocli .' dando por bien hechas las allí hechas al menos hubiere dado aviso a LA PARTE ACREEDORA cambio dirección misma El presente pagaré está exento Impuesto timbre Fiscal Papel Sellado Especial Protocolos conforme artículo 11 numeral 9 Decreto 37-92 Congreso República EN TESTIMONIO LO ANTERIOR EXTIENDO PRESENTE PAGARE ALDEA TICOLAL PUEBLO NUEVO JUCUP SAN SEBASTIAN COATAN DEPARTAMENTO HUEHUETENANGO DIA '. $fechaenletras .'', ['bold' => false, 'name' => 'Arial', 'size' => 9]);
// Firma del cliente y del aval
$section->addTextBreak(2);

$displayNombreCliente = isset($nombreclietne) ? $nombreclietne : (isset($nombrecliente) ? $nombrecliente : '');
$displayNombreAval = isset($nombrecompletoaval) ? $nombrecompletoaval : '';
// Estilo de párrafo compacto
$parrafoCompacto = [
    'spaceBefore' => 0,
    'spaceAfter'  => 0,
    'spacing'     => 0
];

$parrafoCompacto2 = [
    'spaceBefore' => 2,
    'spaceAfter'  => 2,
    'spacing'     => 2
];

// Firma del cliente
$section->addTextBreak(0.4);
$section->addText('_' . str_repeat('_', 40), ['name' => 'Arial', 'size' => 9], $parrafoCompacto2);
$section->addTextBreak(0.9);
$section->addText('Firma: ' . $displayNombreCliente, ['name' => 'Arial', 'size' => 8], $parrafoCompacto2);
$section->addTextBreak(0.9);
$section->addText('CUI: ' . $dpiClienteFormateado, ['name' => 'Arial', 'size' => 8], $parrafoCompacto2);
$section->addTextBreak(0.9);
$section->addText('EXTENDIDA EN: ' . $municipiocli, ['name' => 'Arial', 'size' => 8], $parrafoCompacto2);
$section->addTextBreak(3);

if(empty($fiador)){

}else{
    // Texto del avalista
$section->addText('AVAL: ', ['bold' => true, 'name' => 'Arial', 'size' => 9]);
$section->addText('Por este medio los signatarios o los que dejan su huella digital, si fuese el caso, titulares de los documentos personales de identificación que se indican, se constituyen expresamente en avalistas del presente pagaré en todas y cada una de las obligaciones que asume la parte libradora del mismo.', ['name' => 'Arial', 'size' => 9], $paragraphJustify);

$section->addTextBreak(1);


if(count($fiador) == 2){

    $nombreaval2 = $fiador[1]["primer_name"] . " " . $fiador[1]["segundo_name"] . " " . $fiador[1]["tercer_name"];
    $apellidosaval2 = $fiador[1]["primer_last"] . " " . $fiador[1]["segundo_last"];
    $nombrecompletoaval2 = $nombreaval2 . " " . $apellidosaval2;
    $direccionaval2 = $fiador[1]["direccion"];
    $dpifiador2 = $fiador[1]["dpifiador"];

    $dpiFiadorFormateado2 = formatearDPI($dpifiador2);


    $tableFirmas = $section->addTable([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
    ]);

    // ---------------- LÍNEAS DE FIRMA ----------------
    $tableFirmas->addRow(350, ['exactHeight' => true]);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 40),
        ['name' => 'Arial', 'size' => 9],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 40),
        ['name' => 'Arial', 'size' => 9],
        $parrafoCompacto
    );

    // ---------------- NOMBRES ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'Firma: ' . $displayNombreAval,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(3000)->addText(
        'Firma: ' . $nombrecompletoaval2,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

    // ---------------- CUI ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'CUI: ' . $dpiFiadorFormateado,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(3000)->addText(
        'CUI: ' . $dpiFiadorFormateado2,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

    // ---------------- DIRECCIÓN ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'EXTENDIDA EN: ' . $municipiocli,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(3000)->addText(
        'EXTENDIDA EN: ' . $municipiocli,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

}else{
    // Tabla de firmas centrada
    $tableFirmas = $section->addTable([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT
    ]);

    // ---------------- LÍNEAS DE FIRMA ----------------
    $tableFirmas->addRow(350, ['exactHeight' => true]);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 40),
        ['name' => 'Arial', 'size' => 9],
        $parrafoCompacto
    );


    // ---------------- NOMBRES ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'Firma: ' . $displayNombreAval,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );


    // ---------------- CUI ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'CUI: ' . $dpiFiadorFormateado,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );


        // ---------------- DIRECCIÓN ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'EXTENDIDA EN: ' . $municipiocli,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

}
}


$section->addTextBreak(1);

$section->addText('PAGARE SIN PROTESTO', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

$table2 = $section->addTable(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$table2->addRow();
$table2->addCell(3000)->addText('Ticolal Pueblo Nuevo Jucup', ['name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$cell = $table2->addCell(5000);
$textRun = $cell->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);

$textRun->addText('Préstamo: ' . $codigocred . ' / AGENCIA 001', ['name' => 'Arial', 'size' => 10, 'underline' => 'single']);
$textRun->addTextBreak(); // Salto de línea
$textRun->addText('Pagaré:     ' . $dictamen, ['name' => 'Arial', 'size' => 10, 'underline' => 'single']);

$section->addTextBreak(0.5);

$section->addText('A ruego de las personas que por no saber firmar dejaron impresa la huella digital, si fuese el caso', ['bold' => false, 'name' => 'Arial', 'size' => 9]);

$section->addTextBreak(1);

// Firma del cliente
$section->addTextBreak(0.8);
$section->addText('_' . str_repeat('_', 40), ['name' => 'Arial', 'size' => 9], $parrafoCompacto);
$section->addTextBreak(0.8);
$section->addText('ROBERTO JUAN BALTAZAR', ['name' => 'Arial', 'size' => 8], $parrafoCompacto);
$section->addTextBreak(0.8);
$section->addText('CUI: 322 86689 1325', ['name' => 'Arial', 'size' => 8], $parrafoCompacto);

$section->addTextBreak(1);

$section->addText('Auntentica:', ['bold' => true, 'name' => 'Arial', 'size' => 9]);
$section->addTextBreak(0.5);
$section->addText('En la aldea Ticolal Pueblo Nuevo Jucup, municipio de San Sebastián Coatán Departamento Huehuetenango el día ' . $fechaenletras .' el infrascrito Notario da fe: a) Que las firmas o huellas digitales, si fuese el caso, que anteceden son auténticas por haber sido puestas a mi presencia por los siguientes señores, quienes se identifican con los Documentos Personal de Identificación (DPI) que se indican más adelante, b) que las firmas, o huellas digitales, están puestas en un pagaré de esta misma fecha a favor de la entidad GRUPO KOTANH, SOCIEDAD ANÓNIMA. Los signatarios firman la presente acta de legalización junto con el Notario autorizante que da fe de lo expuesto. Que por parte de las personas que por no saber firmar dejaron impresa la huella digital, si fuera el caso, firma a su ruego el señor Roberto Juan Baltazar quien se identifica con el Documento Personal de Identificación que registra el número de CUI 3222 86689 1325 extendido por el Registro Nacional de las Personas de la República de Guatemala.', ['name' => 'Arial', 'size' => 9], $paragraphJustify);


$section->addTextBreak(1);

// Firma del cliente
$section->addTextBreak(0.4);
$section->addText('_' . str_repeat('_', 40), ['name' => 'Arial', 'size' => 9], $parrafoCompacto2);
$section->addTextBreak(0.9);
$section->addText('Firma: ' . $displayNombreCliente, ['name' => 'Arial', 'size' => 8], $parrafoCompacto2);
$section->addTextBreak(0.9);
$section->addText('CUI: ' . $dpiClienteFormateado, ['name' => 'Arial', 'size' => 8], $parrafoCompacto2);
$section->addTextBreak(0.9);
$section->addText('EXTENDIDA EN: ' . $municipiocli, ['name' => 'Arial', 'size' => 8], $parrafoCompacto2);
$section->addTextBreak(1.5);

if(empty($fiador)){

}else{
    if(count($fiador) == 2){
    
    
    $nombreaval2 = $fiador[1]["primer_name"] . " " . $fiador[1]["segundo_name"] . " " . $fiador[1]["tercer_name"];
    $apellidosaval2 = $fiador[1]["primer_last"] . " " . $fiador[1]["segundo_last"];
    $nombrecompletoaval2 = $nombreaval2 . " " . $apellidosaval2;
    $direccionaval2 = $fiador[1]["direccion"];
    $dpifiador2 = $fiador[1]["dpifiador"];

    $dpiFiadorFormateado2 = formatearDPI($dpifiador2);


    $tableFirmas = $section->addTable([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
    ]);

    // ---------------- LÍNEAS DE FIRMA ----------------
    $tableFirmas->addRow(350, ['exactHeight' => true]);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 40),
        ['name' => 'Arial', 'size' => 9],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 40),
        ['name' => 'Arial', 'size' => 9],
        $parrafoCompacto
    );

    // ---------------- NOMBRES ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'Firma: ' . $displayNombreAval,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(3000)->addText(
        'Firma: ' . $nombrecompletoaval2,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

    // ---------------- CUI ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'CUI: ' . $dpiFiadorFormateado,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(3000)->addText(
        'CUI: ' . $dpiFiadorFormateado2,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

    // ---------------- DIRECCIÓN ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'EXTENDIDA EN: ' . $municipiocli,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );
    $tableFirmas->addCell(2000)->addText('', null, $parrafoCompacto);
    $tableFirmas->addCell(3000)->addText(
        'EXTENDIDA EN: ' . $municipiocli,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

}else{
    // Tabla de firmas centrada
    $tableFirmas = $section->addTable([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT
    ]);

    // ---------------- LÍNEAS DE FIRMA ----------------
    $tableFirmas->addRow(350, ['exactHeight' => true]);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 40),
        ['name' => 'Arial', 'size' => 9],
        $parrafoCompacto
    );


    // ---------------- NOMBRES ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'Firma: ' . $displayNombreAval,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );


    // ---------------- CUI ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'CUI: ' . $dpiFiadorFormateado,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );


        // ---------------- DIRECCIÓN ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'EXTENDIDA EN: ' . $municipiocli,
        ['name' => 'Arial', 'size' => 8],
        $parrafoCompacto
    );

}
}


$section->addTextBreak(1);

$section->addText('A ruego de las personas que por no saber firmar dejaron impresa la huella digital, si fuese el caso', ['bold' => false, 'name' => 'Arial', 'size' => 9]);

$section->addTextBreak(1);

// Firma del cliente
$section->addTextBreak(0.4);
$section->addText('_' . str_repeat('_', 40), ['name' => 'Arial', 'size' => 9], $parrafoCompacto);
$section->addTextBreak(0.8);
$section->addText('ROBERTO JUAN BALTAZAR', ['name' => 'Arial', 'size' => 8], $parrafoCompacto);
$section->addTextBreak(0.8);
$section->addText('CUI: 322 86689 1325', ['name' => 'Arial', 'size' => 8], $parrafoCompacto);
$section->addTextBreak(0.8);

$section->addText('ANTE MI:', ['name' => 'Arial', 'size' => 12, 'bold' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);



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
