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
                cli.genero AS generocli, cli.pais_nacio AS nacionalidadcli, cli.profesion AS profesioncli, cli.aldea_reside AS recidenciacli,
                cli.tel_no1,cli.no_identifica,usu.nombre nomanal,usu.apellido apeanal, cre.NintApro as intereses, cre.NtipPerC tipoperiodo,cre.CCodCta ccodcta, cli.estado_civil estadocivil, cre.Dictamen dictamen, cli.genero genero, cli.firma firma,
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
                WHERE tgc.id_cremcre_meta=?
                AND clig.idTipoGa = '1';";
$qdatosppg = "SELECT ppg.ccodcta, ppg.nintere interesesppg, ppg.ncapita capitalppg, ppg.cnrocuo cuotasppg, ppg.dfecven fechavenppg, ppg.OtrosPagos otrosppg,ppg.SaldoCapital saldo_capital, ppg.NAhoProgra vinculado,
                ppg.ncapita capitalcuota,
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MIN(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.ncapita ELSE 0 END) AS capital_primera,
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MAX(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.ncapita ELSE 0 END) AS capital_ultima,
                (SELECT SUM(ppg2.nintere) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_interes,
                (SELECT SUM(ppg2.NAhoProgra) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_vinculado,
                (SELECT SUM(ppg2.OtrosPagos) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) AS total_otros
                FROM Cre_ppg ppg
                WHERE ccodcta=?
                ORDER BY cnrocuo;";
$credkar = "SELECT CREDKAR.*, IFNULL((SELECT nombre FROM tb_bancos WHERE id = CREDKAR.CBANCO), '-') AS nombrebanco FROM CREDKAR 
            WHERE CCODCTA=?
            AND CTIPPAG= 'D'
            ;";

$fondos = "SELECT fondos.descripcion AS tipo_fondo FROM ctb_fuente_fondos fondos 
            INNER JOIN ctb_mov mov ON mov.id_fuente_fondo = fondos.id
            INNER JOIN ctb_diario diario ON diario.id = mov.id_ctb_diario
            INNER JOIN CREDKAR cred ON cred.CNUMING = diario.numdoc
            WHERE cred.CCODCTA = ?
            ;";
$interesmora = "SELECT porcentaje_mora AS tasa_interes_mora, dias_calculo 
                FROM cre_productos 
                INNER JOIN cremcre_meta ON cre_productos.id=cremcre_meta.CCODPRD 
                WHERE cremcre_meta.CCODCTA=?;";
$qGarantia = "SELECT tgc.id_garantia, clig.descripcionGarantia AS descripcionGarantia, clig.direccion AS direccionGarantia
                FROM tb_garantias_creditos tgc 
                INNER JOIN cli_garantia clig ON clig.idGarantia=tgc.id_garantia 
                WHERE tgc.id_cremcre_meta=?
                AND clig.idTipoGa!='1';";


            

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

    $credkar = $database->getAllResults($credkar, [$codcuenta]);
    if (empty($credkar)) {
        $showmensaje = true;
        throw new Exception("No se encontraron datos de CREDKAR");
    }

    $fondos = $database->getAllResults($fondos, [$codcuenta]);
    if (empty($fondos)) {
        $showmensaje = true;
        throw new Exception("No se encontraron datos de fondos");
    }

    $interesmoraresult = $database->getAllResults($interesmora, [$codcuenta]);
    if (empty($interesmora)) {
        $showmensaje = true;
        throw new Exception("No se encontraron datos de interés de mora");
    }

    $garantia = $database->getAllResults($qGarantia, [$codcuenta]);
    if (empty($garantia)) {
        // $showmensaje = true;
        // throw new Exception("No se encontraron datos de garantía");
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
$fechaenletras = fechaCompleta($fechadesembolso);

// Calcular fecha de un año después del desembolso
$fechaDesembolsoObj = new DateTime($fechadesembolso);
$fechaDesembolsoObj->add(new DateInterval('P1Y')); // Añade 1 año
$fechaUnAnoDespues = $fechaDesembolsoObj->format('Y-m-d');
$fechaUnAnoDespuesLetras = fechaCompleta($fechaUnAnoDespues);
$fechavence = ($result[0]["Cestado"] == "F") ? $result[0]["fecven"] : $ppgtemp[count($ppgtemp) - 1]["dfecven"];
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
$generocliente = $result[0]["genero"];
$destinocredito = $result[0]["destinocredito"];
$nombrescliente = $result[0]["primer_name"] . " " . $result[0]["segundo_name"] . " " . $result[0]["tercer_name"];
$apellidoscliente = $result[0]["primer_last"] . " " . $result[0]["segundo_last"];

$generocli = $result[0]['generocli'];
$nacionalidadcli = $result[0]['nacionalidadcli'];
$nacli = ($nacionalidadcli == 'GT' && $generocli == 'M') ? 'guatemalteco' : (($nacionalidadcli == 'GT' && $generocli == 'F') ? 'guatemalteca' : (($nacionalidadcli != 'GT' && $generocli == 'M') ? 'extranjero' : 'extranjera'));
$gencli = ($generocli == 'M') ? ' el señor ' : ' la señora ';
$geninver = ($generocli == 'M') ? ' EL INVERSIONISTA ' : ' LA INVERSIONISTA ';
$profcli = !empty(trim($result[0]['profesioncli'] ?? '')) ? $result[0]['profesioncli'] : 'profesión';

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
$tasamora = $interesmoraresult[0]["tasa_interes_mora"];
$diascalculo = $interesmoraresult[0]["dias_calculo"];

$elclientefirma = $result[0]['firma'];


$formadesembolso = "";
if(count($credkar) > 1){ //solo un desembolso
    for ($i = 0; $i < count($credkar); $i++) {

        if ($i == count($credkar) - 1) { //cuando es ultimo desembolso
          if($credkar[$i]["FormPago"] == 1){ //ultimo desembolso en efectivo
            $formadesembolso = $formadesembolso . "y un Desembolso en efectivo por la cantidad de Q" . number_format($credkar[$i]["NMONTO"], 2);
          }
          if ($credkar[$i]["FormPago"] == 2){ //ultimo desembolso por cheque
            $formadesembolso = $formadesembolso . "y un Desembolso mediante cheque del banco " . $credkar[$i]["nombrebanco"] . " por la cantidad de Q" . number_format($credkar[$i] ["NMONTO"], 2). ", a nombre de " . $nombrecliente . ", numero del cheque " . numeroATexto($credkar[$i]["boletabanco"]);
          }
        }else{
          if($credkar[$i]["FormPago"] == 1){ //desembolso en efectivo
            $formadesembolso = $formadesembolso . "Un Desembolso en efectivo por la cantidad de Q " . number_format($credkar[$i]["NMONTO"], 2) .  ", ";
          }
          if ($credkar[$i]["FormPago"] == 2){ //ultimo desembolso por cheque
            $formadesembolso = $formadesembolso . "Un Desembolso mediante cheque del banco " . $credkar[$i]["nombrebanco"] . " por la cantidad de Q " . number_format($credkar[$i]  ["NMONTO"], 2). ", a nombre de " . $nombrecliente . ", numero del cheque " . numeroATexto($credkar[$i]["boletabanco"]) . ", ";
          }
        }
    }
}else {
    if ($credkar[0]["FormPago"] == 1) { //desembolso en efectivo
        $formadesembolso = "Un solo Desembolso en efectivo por la cantidad de Q " . number_format($credkar[0]["NMONTO"], 2);
    }
    if ($credkar[0]["FormPago"] == 2) { //desembolso por cheque
        $formadesembolso = "Un solo Desembolso mediante cheque del banco " . $credkar[0]["nombrebanco"] . " por la cantidad de Q " . number_format($credkar[0]["NMONTO"], 2) . ", a nombre de " . $nombrecliente . ", numero del cheque " . numeroATexto($credkar[0]["boletabanco"]);
    }
}


$tipofondo = $fondos[0]["tipo_fondo"];

$nocuotas = $result[0]["cuotas"];
$nocuotassob = $nocuotas - 1;
$nocuotas2 = numeroATexto($nocuotas);
$hoy3 = date("Y-m-d");
$nocuotras3 = numeroATexto($nocuotas);
$hoy3text = fechaCompleta($hoy3);
$hoytextprint = fechaCompleta($hoy);


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

$tiposPeridodo4 = [
    'D' => 'DIARIAS',
    'S' => 'SEMANALES',
    'Q' => 'CATORCENALES',
    '15D' => 'QUINCENALES',
    '1M' => 'MENSUALES',
    '1C' => 'MENSUALES',
    '2M' => 'BIMENSUALES',
    '3M' => 'TRIMESTRALES',
    '6M' => 'SEMESTRALES',
    '1' => 'ANUALES',
    '0D' => 'OTROS'
];

$tipoperiodo = $result[0]["tipoperiodo"];
$tipoPeriodoText2 = $tiposPeridodo2[$tipoperiodo];

$tipoPeriodoText1 = $tiposPeridodo[$tipoperiodo];

$tipoPeriodoText3 = $tiposPeridodo3[$tipoperiodo];

$tipoPeriodoText4 = $tiposPeridodo4[$tipoperiodo];

$cantdias = dias_dif($fechadesembolso, $fechavence);
$cuota = ($result[0]["Cestado"] == "F") ?  $result[0]["montocuota"] : ($ppgtemp[0]["ncapita"] + $ppgtemp[0]["nintere"]);
$profesion =  $result[0]["profesion"];

//DATOS DE AVAL
$nombresaval = (empty($fiador)) ? " " : ($fiador[0]["primer_name"] . " " . $fiador[0]["segundo_name"] . " " . $fiador[0]["tercer_name"]);
$apellidosaval = (empty($fiador)) ? " " : ($fiador[0]["primer_last"] . " " . $fiador[0]["segundo_last"]);
$nombrecompletoaval = (empty($fiador)) ? " Fiador " : $nombresaval . " " . $apellidosaval;
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

// $dictamen
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

$razonsocial = ($result[0]['id_usu'] == 17) ? "GRUPO EMPRESCorrier SERVIALIANZA" : "INJELIFID S.A.";
$namecomercial = ($result[0]['id_usu'] == 17) ? "CREDIRAPI" : "NAWAL";
$phpWord = new PhpWord();

// Configuración para tamaño carta (Letter) con márgenes de 2.5 cm
$section = $phpWord->addSection([
    'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5), // Ancho carta en pulgadas
    'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(14.0), // Alto carta en pulgadas
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0), // Margen superior 2.5 cm
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // Margen derecho 2.5 cm
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3), // Margen inferior 2.5 cm
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(4.5)  // Margen izquierdo 2.5 cm
]);

// Estilos
$fontStyle = ['name' => 'Corrier', 'size' => 8];
$fontStyletext = ['name' => 'Arial', 'size' => 12];
$fontStyletextfiador = ['name' => 'Arial', 'size' => 11];
$cellStyle = ['valign' => 'center', 'align' => 'center',];
$borderStyle = ['borderSize' => 1, 'borderColor' => '000000'];
$styletextos = [
    'bold' => true,
    'size' => 8,
    'name' => 'Corrier',
];
$fontStyletextbold = [
    'bold' => true,
    'size' => 6.5,
    'name' => 'Corrier',
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
    'lineHeight' => 1.15
];
$headerStyle = ['bold' => true, 'name' => 'Corrier', 'size' => 11];
$headerStyleSmall = ['bold' => true, 'name' => 'Corrier', 'size' => 9];
$titleStyle = ['bold' => true, 'name' => 'Corrier', 'size' => 14];
$subtitleStyle = ['bold' => true, 'name' => 'Corrier', 'size' => 12];
$amountStyle = ['bold' => true, 'name' => 'Corrier', 'size' => 12];
$bodyStyle = ['name' => 'Corrier', 'size' => 9];
$smallStyle = ['name' => 'Corrier', 'size' => 8];

$centerAlign = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 60];
$rightAlign = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT, 'spaceAfter' => 60];
$justifyAlign = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH, 'spaceAfter' => 120];
$normalSpacing = ['spaceAfter' => 80];

// Encabezado para cada hoja con imagen y numeración dinámica "Página X de Y"
$header = $section->addHeader();

// Imagen en el encabezado (ajústala según necesites)
// Centrar la imagen en el encabezado usando una tabla de una sola celda
$table = $header->addTable();
$table->addRow();
$availableWidthCm = 21.59 - 2.5 - 2.5; // ancho carta (cm) menos márgenes izquierdo y derecho
$cell = $table->addCell(\PhpOffice\PhpWord\Shared\Converter::cmToTwip($availableWidthCm), ['valign' => 'center']);

// Agregar la imagen centrada dentro de la celda
$cell->addImage(
    '../../../../includes/img/kotanh.png',
    [
        'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(5),
        'wrappingStyle' => 'inline',
        'alignment' => Jc::CENTER
    ]
);

// Texto de página con campos que Word resolverá: {PAGE} y {NUMPAGES}
$header->addPreserveText(
    'Página {PAGE} de {NUMPAGES}',
    ['bold' => true, 'name' => 'Corrier', 'size' => 9],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]
);


if($generocliente == 'F'){
    $Nacionalidad = 'guatemalteca';
    $señor_a = 'la señora';
    $deudor_a = 'la deudora';
}else{
    $Nacionalidad = 'guatemalteco';
    $señor_a = 'el señor';
    $deudor_a = 'el deudor';
}

$descripcionGarantia = "";
if(empty($garantia)){
    $descripcionGarantia = "0";
    $direcgarantia = "0";
}else{
    if(count($garantia) > 1){
        for($i = 0; $i < count($garantia); $i++){
            
            if($i == count($garantia) - 1){
                $descripcionGarantia .= 'y ' . $garantia[$i]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en '. $garantia[$i]['direccionGarantia'] . ', ';
            }else{
                $descripcionGarantia .= '' . $garantia[$i]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en ' . $garantia[$i]['direccionGarantia'] . ', ';
            }
        }
    }else{
        $descripcionGarantia = '' . $garantia[0]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en ' . $garantia[0]['direccionGarantia'] . ', ';
    }

}

if(empty($fiador)){

    $section->addText('CONTRATO MUTUO', ['bold' => true, 'name' => 'Corrier', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

    $textRun = $section->addTextRun($paragraphJustify);
    
    $textRun->addText('CONTRATO No. ' . $codigocred . '. En la aldea Ticolal Pueblo Nuevo Jucup, municipio de San Sebastián Coatán, departamento de Huehuetenango, el ' . $fechaenletras . ', comparecen por una parte el seño Sebastián Juan Baltazar, de treinta y cuatro años de edad, casado, guatemalteco, comerciante, de este domicilio,  quien se identifica con el Documento Personal de identificación DPI,  con Código Único de Identificación número Dos mil ciento cuarenta y cinco Diecisiete mil ciento cuarenta y cuatro Mil trescientos veinticinco (2145 17144 1325) extendida por el Registro Nacional de las Personas de la República de Guatemala; señalo que comparezco y actúo en calidad de Presidente del Consejo de Administración y Representante Legal de la entidad denominada GRUPO KOTANH, SOCIEDAD ANONIMA, calidad que acredito con el razonamiento del acta emitido por el Registro Mercantil de fecha dieciocho de marzo de dos mil veinticuatro, inscrita bajo el número Setecientos treinta y cinco mil cuatrocientos sesenta y ocho (735,468), Folio Setecientos cuarenta y seis (746), Libro Ochocientos veintinueve (829) de Auxiliares de Comercio del Registro Mercantil de la República de Guatemala; siendo la representación suficiente de conformidad con la ley para la celebración del presente contrato a quien en éste documento se denominará el  “ACREEDOR” y  por otra parte comparece ' . $señor_a , $fontStyletext, $paragraphJustify);
    
    $textRun->addText(' '. $nombrecliente .', ', ['name' => 'Arial', 'size' => 12, 'bold'=> true]);
    
    $textRun->addText('de ' . $edadClienteTexto . ' años de edad, ' . $estadocivilcli . ', ' . $Nacionalidad . ', de este domicilio y residencia en ' . $diredomicilio . ', del municipio de ' . $municipiocli . ', departamento de ' . $departamentocli . '; quien se identifica con el documento personal de identificación; CUI: ' . $dpiClienteTexto . ' (' . $dpiClienteFormateado .'), extendido por el Registro Nacional de las Personas de la República de Guatemala, que en el curso de este contrato se denominará simplemente ' , $fontStyletext, $paragraphJustify);
    $textRun->addText('DEUDOR, ', ['name' => 'Arial', 'size' => 12, 'bold'=> true]);
    $textRun->addText('los dos aseguramos ser de la generales expuestas anteriormente, y hallarnos en el libre ejercicio de nuestro derechos civiles y de palabra y por eso celebramos el siguiente ', $fontStyletext, $paragraphJustify);
    $textRun->addText('CONTRATO DE MUTUO, CON GARANTIA DE DERECHOS POSESORIOS', ['name' => 'Arial', 'size' => 12, 'bold'=> true]);
    $textRun->addText(', conforme a las cláusulas siguientes: ', $fontStyletext, $paragraphJustify);
    
    $textRun->addText('PRIMERA: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText($señor_a, $fontStyletext, $paragraphJustify);
    $textRun->addText(' ' . $nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText(', declara que por este acto se reconoce lisa y llana', $fontStyletext, $paragraphJustify);
    $textRun->addText(' DEUDOR, ', ['name' => 'Arial', 'size' => 12, 'bold'=> true]);
    $textRun->addText(' de la entidad GRUPO KOTANH, SOCIEDAD ANONIMA, ', $fontStyletext, $paragraphJustify);
    $textRun->addText('POR LA CANTIDAD DE ' . $monto2 . ' (Q ' . $monto . ') ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('que ha recibido en calidad de mutuo que a continuación se indica.', $fontStyletext, $paragraphJustify);
    
    $textRun->addText(' SEGUNDA: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA; por medio de su representante legal, otorga al', $fontStyletext, $paragraphJustify);
    $textRun->addText(' deudor, ', ['name' => 'Arial', 'size' => 12, 'bold'=> true]);
    $textRun->addText('el presente crédito;  haciendo constar que de conformidad al dictamen número ' . $dictamen . ', de fecha ' . $fechaenletras .', del libro respectivo de la entidad GRUPO KOTANH, SOCIEDAD ANONIMA, se autorizó  dicho crédito; con el visto bueno del Plan de Inversión de Crédito de la entidad GRUPO KOTANH, SOCIEDAD ANONIMA; el presente crédito estará  bajo las condiciones siguientes: ', $fontStyletext, $paragraphJustify);
    $textRun->addText('1. MONTO: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('El que ya quedo consignado o sea la cantidad de ', $fontStyletext, $paragraphJustify);
    $textRun->addText($monto2 . ' (Q ' . $monto . '). 2. DESTINO: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('La suma anteriormente indicada se destinará exclusivamente para ' . $destinocredito . '. ', $fontStyletext, $paragraphJustify);
    
    $textRun->addText('3. FORMA DE DESEMBOLSO: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA dará el crédito en la forma siguiente: ' . $formadesembolso, $fontStyletext, $paragraphJustify);
    
    $textRun->addText(' 4. PROVENENCIA DE LOS FONDOS: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('La entidad otorga el presente crédito con fondos provenientes de ' . $tipofondo . ' de GRUPO KOTANH, SOCIEDAD ANONIMA. ', $fontStyletext, $paragraphJustify);
    
    
    $textRun->addText('5. PLAZO: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('El plazo del presente crédito será de ' . $nocuotas2 . ' ' . $tipoPeriodoText2 . ' a partir del día de hoy.', $fontStyletext, $paragraphJustify);
    
    $textRun->addText(' 6. FORMA DE PAGO: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('El reintegro o pago del crédito por parte de la deudora será en ' . $nocuotas2 . ' amortizaciones ' . $tipoPeriodoText4 . ', siendo la última amortización el ' . $fechavenceletras . '; haciendo constar a la deudora que en caso de que se pueda reintegrar el capital y el interés de la cantidad mutuada la misma podrá hacerse antes del plazo estipulado mediante acuerdo interno con GRUPO KOTANH, SOCIEDAD ANONIMA; el interés que devengará el presente crédito será del ' . $tasaintere . '% anual. Los pagos que se efectúen se aplicarán en el siguiente orden: A los recargos por mora el ' . $tasamora . '% anual, si lo hubiere a capital, en caso de que exista casos fortuitos o actos de hecho, naturales se le permitirá a la deudora un tiempo máximo que establezca GRUPO KOTANH, SOCIEDAD ANONIMA, esta justificación deberá de ser acreditada.', $fontStyletext, $paragraphJustify);
    
    $textRun->addText(' 7. CÓMPUTO DE INTERESES Y RECARGOS: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Para el cómputo de intereses y recargos el año será de ' . numeroATexto($diascalculo) . ' días, los meses se tomarán por sus días reales.', $fontStyletext, $paragraphJustify);
    
    $textRun->addText('TERCERA: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Por su parte '. $señor_a, $fontStyletext, $paragraphJustify);
    $textRun->addText(' ' . $nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText(', de todos sus bienes presentes y futuros, específicamente de ' . $descripcionGarantia . ' ', $fontStyletext, $paragraphJustify);
    $textRun->addText('documento que se tiene a la vista y que el mismo quedara en calidad de garantía, el bien inmueble se encuentra libre de gravámenes, anotaciones o de cualquier otra anotación que perjudiquen intereses de terceras personas; y de conformidad al artículo 1464 del Código Civil su obligación se garantiza con hipoteca sobre el bien inmueble antes identificado en caso de incumplimiento y transfiere la cosa pignorada o hipotecada sobre la deuda respectiva en caso de incumplimiento, con todas sus consecuencias y modalidades,  declarando así mismo que renuncia al fuero de su domicilio y se somete a los tribunales que GRUPO KOTANH, SOCIEDAD ANONIMA elija en caso de incumplimiento de la obligación contraída.-', $fontStyletext, $paragraphJustify);
    
    $textRun->addText(' CUARTA: DISPOSICIONES GENERALES: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText($señor_a . ' ', $fontStyletext, $paragraphJustify);
    $textRun->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText(', expresamente se obliga a lo siguiente: ', $fontStyletext, $paragraphJustify);
    $textRun->addText('a) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Permitir el libre acceso de los personeros de GRUPO KOTANH, SOCIEDAD ANONIMA para supervisar el crédito invertido ya sea en visitas de rutina o en comisiones específicas que demanden el presente crédito.- ', $fontStyletext, $paragraphJustify);
    $textRun->addText('b) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA podrá supervisar las operaciones de este crédito con el objeto de constatar el buen uso de los recursos del préstamo.- ', $fontStyletext, $paragraphJustify);
    $textRun->addText('c) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Rendir en un tiempo prudencial la información que GRUPO KOTANH, SOCIEDAD ANONIMA pudiera requerir, relacionada con el préstamo otorgado.- ', $fontStyletext, $paragraphJustify);
    $textRun->addText('d) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Se conviene expresamente que todo pago tanto de capital como de intereses, serán efectuadas por la parte deudora sin necesidad de cobro o requerimiento alguno, en quetzales, moneda de curso legal en la oficina de GRUPO KOTANH, SOCIEDAD ANONIMA, la cual es conocidas perfectamente por la parte deudora.- ', $fontStyletext, $paragraphJustify);
    $textRun->addText('e) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA podrá dar por vencido el plazo del préstamo y exigir el cumplimiento de la obligación en juicio ejecutivo, en los casos siguientes: ', $fontStyletext, $paragraphJustify);
    $textRun->addText('I) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Si la parte deudora diera a los fondos un destino diferente al pactado; ', $fontStyletext, $paragraphJustify);
    $textRun->addText('II) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Si incurriera en mora; ', $fontStyletext, $paragraphJustify);
    $textRun->addText('III) ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText('Si se incumpliere cualquiera de las obligaciones que se asume en este contrato o violare las prohibiciones consignadas en el mismo. Y en estos casos desde ya acepta como buenas exactas de plazo vencido, líquidos y exigibles, las cantidades, que se  demanden.- ', $fontStyletext, $paragraphJustify);
    
    $textRun->addText('QUINTA: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText($señor_a, $fontStyletext, $paragraphJustify);
    $textRun->addText(' ' . $nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun->addText(', declara que en forma expresa acepta el presente contrato a su favor  y renuncia al fuero de su domicilio y se sujeta a los tribunales que GRUPO KOTANH, SOCIEDAD ANONIMA elija en caso de incumplimiento al presente contrato de mutuo (préstamo) y señala como lugar para recibir notificaciones su residencia situada en ' . $diredomicilio . ', del municipio de ' . $municipiocli . ' departamento de ' . $departamentocli . '. ', $fontStyletext, $paragraphJustify);
    
    
    if(empty($elclientefirma)){
        $textRun->addText('SEXTA: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
        $textRun->addText('Los comparecientes, declaran que después de estar informados de su contenido, valor, objetivo, costos legales, los términos consignados y en las calidades que comparecen, aceptan y firman el presente contrato, no así ' . $deudor_a .' quien por ignorar hacerlo deja estampada la impresión digital del dedo pulgar de la mano derecha, firmando a su ruego la testigo civilmente capaz, idónea y de mi conocimiento Roberto Juan Baltazar, quien estuvo presente desde el inicio hasta el final del presente contrato.', $fontStyletext, $paragraphJustify);
    
    }else{
        $textRun->addText('SEXTA: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
        $textRun->addText('Los comparecientes, declaran que después de estar informados de su contenido, valor, objetivo, costos legales, los términos consignados y en las calidades que   comparecen, aceptan y firma el presente contrato.', $fontStyletext, $paragraphJustify);
    }
    
    
    // Firma del cliente y del aval
    $section->addTextBreak(1.5);
    
    $displayNombreCliente = isset($nombreclietne) ? $nombreclietne : (isset($nombrecliente) ? $nombrecliente : '');
    $displayNombreAval = isset($nombrecompletoaval) ? $nombrecompletoaval : '';
    
    // Tabla con dos columnas para las firmas
    $table = $section->addTable([
        'alignment' => Jc::CENTER,
        'cellMarginTop' => 80,
        'cellMarginBottom' => 80,
    ]);
    
    $table->addRow();
    $cellWidth = 4500;
    
    if(empty($elclientefirma)){
        $cell1 = $table->addCell($cellWidth);
        $cell2 = $table->addCell($cellWidth);
        $cell3 = $table->addCell($cellWidth);
    
        // Espacio para la firma (línea)
        $line = '_______________________________';
        $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
        // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
        $cell2->addText('', null, ['spaceAfter' => 0]);
        // $cell3->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell2->addText('TESTIGO (A)', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
    
        $cell3->addText('', null, ['spaceAfter' => 0]);
        // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell3->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
        
    }else{
        $cell1 = $table->addCell($cellWidth);
        $cell2 = $table->addCell($cellWidth);
    
        // Espacio para la firma (línea)
        $line = '_______________________________';
        $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
        // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
        $cell2->addText('', null, ['spaceAfter' => 0]);
        // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell2->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
    }
    
    
    
    $textRun2 = $section->addTextRun($paragraphJustify);
    
    $textRun2->addTextBreak(1.5);
    
    $textRun2->addText('ACTA DE LEGALIZACIÓN DE FIRMAS: ', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun2->addText('En la aldea Ticolal Pueblo Nuevo Jucup del municipio de San Sebastián Coatán, departamento de Huehuetenango, el día ' . $fechaenletras .', el infrascrito notario da fe: Que las firmas que anteceden son ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('AUTENTICAS', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun2->addText(', en virtud de haber sido puestas el día de hoy y en mi presencia por los señores/as: ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('SEBASTIAN JUAN BALTAZAR', ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    $textRun2->addText(', quien se identifica con el documento personal de identificación DPI; Código Único de Identificación: Dos mil ciento cuarenta y cinco Diecisiete mil ciento cuarenta y cuatro Mil trescientos veinticinco (2145 17144 1325), extendido por el Registro Nacional de las Personas de la República de Guatemala; quien comparece en calidad de REPRESENTANTE LEGAL de la entidad GRUPO KOTNAH, SOCIEDAD ANONIMA, así mismo el signatario ', $fontStyletext, $paragraphJustify);
    $textRun2->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 12], $paragraphJustify);
    
    if(empty($elclientefirma)){
        $textRun2->addText(', quien se identifica con el documento personal de identificación; CUI: ' . $dpiClienteTexto . ' (' . $dpiClienteFormateado .'), extendido por el Registro Nacional de las Personas de la República de Guatemala; personas que firman nuevamente conmigo al final de la presente acta de legalización, no así ' . $deudor_a . ' quien por ignorar hacerlo deja estampada la impresión digital del dedo pulgar de la mano derecha, firmando a su ruego la testigo civilmente capaz, idónea y de mi conocimiento Roberto Juan Baltzar, quien estuvo presente desde el inicio hasta el final de la presente acta de legalización DOY FE.', $fontStyletext, $paragraphJustify);
    }else{
        $textRun2->addText(', quien se identifica con el documento personal de identificación; CUI: ' . $dpiClienteTexto . ' (' . $dpiClienteFormateado .'), extendido por el Registro Nacional de las Personas de la República de Guatemala; firma nuevamente la presente acta de legalización DOY FE.', $fontStyletext, $paragraphJustify);
    }
    $section->addTextBreak(1.5);
    
    $displayNombreCliente = isset($nombreclietne) ? $nombreclietne : (isset($nombrecliente) ? $nombrecliente : '');
    $displayNombreAval = isset($nombrecompletoaval) ? $nombrecompletoaval : '';
    
    // Tabla con dos columnas para las firmas
    $table = $section->addTable([
        'alignment' => Jc::CENTER,
        'cellMarginTop' => 80,
        'cellMarginBottom' => 80,
    ]);
    
    $table->addRow();
    $cellWidth = 4500;
    
    if(empty($elclientefirma)){
        $cell1 = $table->addCell($cellWidth);
        $cell2 = $table->addCell($cellWidth);
        $cell3 = $table->addCell($cellWidth);
    
        // Espacio para la firma (línea)
        $line = '_______________________________';
        $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
        // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
        $cell2->addText('', null, ['spaceAfter' => 0]);
        // $cell3->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell2->addText('TESTIGO (A)', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
    
        $cell3->addText('', null, ['spaceAfter' => 0]);
        // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell3->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
        
    }else{
        $cell1 = $table->addCell($cellWidth);
        $cell2 = $table->addCell($cellWidth);
    
        // Espacio para la firma (línea)
        $line = '_______________________________';
        $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
        // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
        $cell2->addText('', null, ['spaceAfter' => 0]);
        // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
        $cell2->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => Jc::CENTER]);
        // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
    }
}else{


       
    $section->addText('CONTRATO MUTUO', ['bold' => true, 'name' => 'Corrier', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

    $textRun = $section->addTextRun($paragraphJustify);

    $textRun->addText('CONTRATO No.' . $codigocred .' En la aldea Ticolal Pueblo Nuevo Jucup, municipio de San Sebastián Coatán, departamento de Huehuetenango, el ' . $fechaenletras . ', comparecen por una parte el señor Sebastián Juan Baltazar, de treinta y cuatro años de edad, casado, guatemalteco, comerciante, de este domicilio,  quien se identifica con el Documento Personal de identificación DPI,  con Código Único de Identificación número Dos mil ciento cuarenta y cinco Diecisiete mil ciento cuarenta y cuatro Mil trescientos veinticinco (2145 17144 1325) extendida por el Registro Nacional de las Personas de la República de Guatemala; señalo que comparezco y actúo en calidad de Presidente del Consejo de Administración y Representante Legal de la entidad denominada GRUPO KOTANH, SOCIEDAD ANONIMA, calidad que acredito con el razonamiento del acta emitido por el Registro Mercantil de fecha dieciocho de marzo de dos mil veinticuatro, inscrita bajo el número Setecientos treinta y cinco mil cuatrocientos sesenta y ocho (735,468), Folio Setecientos cuarenta y seis (746), Libro Ochocientos veintinueve (829) de Auxiliares de Comercio del Registro Mercantil de la República de Guatemala; siendo la representación suficiente de conformidad con la ley para la celebración del presente contrato a quien en éste documento se denominará el  “ACREEDOR” y  por otra parte comparecen los señores/as ', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText($nombrecliente, ['name' => 'Arial', 'size' => 11, 'bold'=> true]);
    $textRun->addText(', de ' . $edadClienteTexto .' años de edad, ' . $estadocivilcli . ', ' . $Nacionalidad . ', de este domicilio y residencia en ' . $diredomicilio . ', del municipio de ' . $municipiocli . ', departamento de ' . $departamentocli . '; quien se identifica con el documento personal de identificación; CUI: ' . $dpiClienteTexto . ' (' . $dpiClienteFormateado . '), extendido por el Registro Nacional de las Personas de la República de Guatemala, y ', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText($nombrecompletoaval, ['name' => 'Arial', 'size' => 11, 'bold'=> true]);
    $textRun->addText(', de ' . $edadFiadorTexto .' años de edad, ' . $estadocivilfiador . ', guatemalteco/a, de este domicilio y residencia en ' . $direccionaval . ', del municipio de ' . $municipiocli . ', departamento de ' . $departamentocli . '; quien se identifica con el documento personal de identificación; CUI: ' . $dpiFiadorTexto . ' (' . $dpiFiadorFormateado . '), extendido por el Registro Nacional de las Personas de la República de Guatemala, quienes en el curso de este contrato se denominarán simplemente ', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText('LOS DEUDORES, ', ['name' => 'Arial', 'size' => 11, 'bold'=> true]);
    $textRun->addText('los dos aseguramos ser de la generales expuestas anteriormente, y hallarnos en el libre ejercicio de nuestro derechos civiles y de palabra y por eso celebramos el siguiente ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('CONTRATO DE MUTUO, CON GARANTIA DE DERECHOS POSESORIOS', ['name' => 'Arial', 'size' => 11, 'bold'=> true]);
    $textRun->addText(', conforme a las cláusulas siguientes: ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('PRIMERA: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Los señores/as ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText($nombrecliente . ' Y ' . $nombrecompletoaval,['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText(', declaran que por este acto se reconocen lisos y llanos ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('DEUDORES, ', ['name' => 'Arial', 'size' => 11, 'bold'=> true]);
    $textRun->addText(' de la entidad GRUPO KOTANH, SOCIEDAD ANONIMA, ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('POR LA CANTIDAD DE ' . $monto2 . ' (Q ' . $monto . ') ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('que han recibido en calidad de mutuo que a continuación se indica. ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText(' SEGUNDA: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA; por medio de su representante legal, otorga a ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('los deudores, ', ['name' => 'Arial', 'size' => 11, 'bold'=> true]);
    $textRun->addText('el presente crédito;  haciendo constar que de conformidad al dictamen número ' . $dictamen . ', de fecha ' . $fechaenletras .', del libro respectivo de la entidad GRUPO KOTANH, SOCIEDAD ANONIMA, se autorizó  dicho crédito; con el visto bueno del Plan de Inversión de Crédito de la entidad GRUPO KOTANH, SOCIEDAD ANONIMA; el presente crédito estará  bajo las condiciones siguientes: ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('1. MONTO: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('El que ya quedo consignado o sea la cantidad de ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText($monto2 . ' (Q ' . $monto . '). 2. DESTINO: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('La suma anteriormente indicada se destinará exclusivamente para ' . $destinocredito . '. ', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText('3. FORMA DE DESEMBOLSO: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA dará el crédito en la forma siguiente: ' . $formadesembolso, $fontStyletextfiador, $paragraphJustify);

    $textRun->addText(' 4. PROVENENCIA DE LOS FONDOS: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('La entidad otorga el presente crédito con fondos provenientes de ' . $tipofondo . ' de GRUPO KOTANH, SOCIEDAD ANONIMA. ', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText('5. PLAZO: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('El plazo del presente crédito será de ' . $nocuotas2 . ' ' . $tipoPeriodoText2 . ' a partir del día de hoy.', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText(' 6. FORMA DE PAGO: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('El reintegro o pago del crédito por parte de los deudores será en ' . $nocuotas2 . ' amortizaciones ' . $tipoPeriodoText4 . ', siendo la última amortización el ' . $fechavenceletras . '; haciendo constar a los deudores que en caso de que se pueda reintegrar el capital y el interés de la cantidad mutuada la misma podrá hacerse antes del plazo estipulado mediante acuerdo interno con GRUPO KOTANH, SOCIEDAD ANONIMA; el interés que devengará el presente crédito será del ' . $tasaintere . '% anual. Los pagos que se efectúen se aplicarán en el siguiente orden: A los recargos por mora el ' . $tasamora . '% anual, si lo hubiere a capital, en caso de que exista casos fortuitos o actos de hecho, naturales se le permitirá al deudor un tiempo máximo que establezca GRUPO KOTANH, SOCIEDAD ANONIMA, esta justificación deberá de ser acreditada. ', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText(' 7. CÓMPUTO DE INTERESES Y RECARGOS: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Para el cómputo de intereses y recargos el año será de ' . numeroATexto($diascalculo) . ' días, los meses se tomarán por sus días reales.', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText('TERCERA: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Por su parte el/la señor/as', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText(' ' . $nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText(' y el/la señor/as ' . $nombrecompletoaval . ', de todos sus bienes presentes y futuros, específicamente de ' . $descripcionGarantia . ' ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('documento que se tiene a la vista y que el mismo quedara en calidad de garantía, el bien inmueble se encuentra libre de gravámenes, anotaciones o de cualquier otra anotación que perjudiquen intereses de terceras personas; y de conformidad al artículo 1464 del Código Civil su obligación se garantiza con hipoteca sobre el bien inmueble antes identificado en caso de incumplimiento y transfiere la cosa pignorada o hipotecada sobre la deuda respectiva en caso de incumplimiento, con todas sus consecuencias y modalidades,  declarando así mismo que renuncia al fuero de su domicilio y se somete a los tribunales que GRUPO KOTANH, SOCIEDAD ANONIMA elija en caso de incumplimiento de la obligación contraída.-', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText(' CUARTA: DISPOSICIONES GENERALES: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Los señores/as ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText($nombrecliente . ' Y ' . $nombrecompletoaval,['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText(', expresamente se obligan a lo siguiente: ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('a) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Permitir el libre acceso de los personeros de GRUPO KOTANH, SOCIEDAD ANONIMA para supervisar el crédito invertido ya sea en visitas de rutina o en comisiones específicas que demanden el presente crédito.- ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('b) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA podrá supervisar las operaciones de este crédito con el objeto de constatar el buen uso de los recursos del préstamo.- ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('c) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Rendir en un tiempo prudencial la información que GRUPO KOTANH, SOCIEDAD ANONIMA pudiera requerir, relacionada con el préstamo otorgado.- ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('d) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Se conviene expresamente que todo pago tanto de capital como de intereses, serán efectuadas por la parte deudora sin necesidad de cobro o requerimiento alguno, en quetzales, moneda de curso legal en la oficina de GRUPO KOTANH, SOCIEDAD ANONIMA, la cual es conocidas perfectamente por la parte deudora.- ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('e) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('La entidad GRUPO KOTANH, SOCIEDAD ANONIMA podrá dar por vencido el plazo del préstamo y exigir el cumplimiento de la obligación en juicio ejecutivo, en los casos siguientes: ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('I) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Si la parte deudora diera a los fondos un destino diferente al pactado; ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('II) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Si incurriera en mora; ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText('III) ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Si se incumpliere cualquiera de las obligaciones que se asume en este contrato o violare las prohibiciones consignadas en el mismo. Y en estos casos desde ya acepta como buenas exactas de plazo vencido, líquidos y exigibles, las cantidades, que se  demanden.- ', $fontStyletextfiador, $paragraphJustify);


    if(empty($elclientefirma)){ //si el cliente no  firmar

    $textRun->addText('QUINTA: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Los deudores ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText(', declaran que en forma expresa aceptan el presente contrato a su favor  y renuncian al fuero de su domicilio y se sujetan a los tribunales que GRUPO KOTANH, SOCIEDAD ANONIMA elija en caso de incumplimiento al presente contrato de mutuo (préstamo) y señalan como lugar para recibir notificaciones sus residencias situadas en ' . $diredomicilio . ', del municipio de ' . $municipiocli . ' departamento de ' . $departamentocli . '. ', $fontStyletextfiador, $paragraphJustify);

    $textRun->addText('SEXTA: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Los comparecientes, declaran que después de estar informados de su contenido, valor, objetivo, costos legales, los términos consignados y en las calidades que comparecen, aceptan y firman el presente contrato, no así el/la deudor/a quien por ignorar hacerlo deja estampada la impresión digital del dedo pulgar de la mano derecha, firmando a su ruego la testigo civilmente capaz, idónea y de mi conocimiento Roberto Juan Baltazar, quien estuvo presente desde el inicio hasta el final del presente contrato.', $fontStyletextfiador, $paragraphJustify);

    // Firma del cliente y del aval
    $section->addTextBreak(2); 


    $displayNombreCliente = isset($nombreclietne) ? $nombreclietne : (isset($nombrecliente) ? $nombrecliente : '');
    $displayNombreAval = isset($nombrecompletoaval) ? $nombrecompletoaval : '';
    
    // Tabla con dos columnas para las firmas
    $table = $section->addTable([
        'alignment' => Jc::CENTER,
        'cellMarginTop' => 80,
        'cellMarginBottom' => 80,
    ]);
    
    $table->addRow();
    $cellWidth = 4000;
    $cell1 = $table->addCell($cellWidth);
    $cell2 = $table->addCell($cellWidth);
    $cell3 = $table->addCell($cellWidth);
    $cell4 = $table->addCell($cellWidth);

    // Espacio para la firma (línea)
    $line = '_______________________________';
    $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
    // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    $cell2->addText('', null, ['spaceAfter' => 0]);
    // $cell3->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell2->addText('FIADOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);

    $cell3->addText('', null, ['spaceAfter' => 0]);
    // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell3->addText('TESTIGO', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);
    
    $cell4->addText('', null, ['spaceAfter' => 0]);
    // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell4->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    $section->addTextBreak(2);
    $textRun2 = $section->addTextRun($paragraphJustify);

    $textRun2->addText('ACTA DE LEGALIZACIÓN DE FIRMAS: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText('En la aldea Ticolal Pueblo Nuevo Jucup del municipio de San Sebastián Coatán, departamento de Huehuetenango, el día ' . $fechaenletras .', el infrascrito notario da fe: Que las firmas que anteceden son ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText('AUTENTICAS', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText(', en virtud de haber sido puestas el día de hoy y en mi presencia por los señores/as: ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText('SEBASTIAN JUAN BALTAZAR', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText(', quien se identifica con el documento personal de identificación DPI; Código Único de Identificación: Dos mil ciento cuarenta y cinco Diecisiete mil ciento cuarenta y cuatro Mil trescientos veinticinco (2145 17144 1325), extendido por el Registro Nacional de las Personas de la República de Guatemala; quien comparece en calidad de REPRESENTANTE LEGAL de la entidad GRUPO KOTNAH, SOCIEDAD ANONIMA, así mismo los signatarios señores/as ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText(', quien se identifica con el documento personal de identificación; CUI: ' . $dpiClienteTexto . ' (' . $dpiClienteFormateado .'), extendido por el Registro Nacional de las Personas de la República de Guatemala; y ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText($nombrecompletoaval, ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify); 
    $textRun2->addText(', quien se identifica con el documento personal de identificación; CUI: ' . $dpiFiadorTexto . ' (' . $dpiFiadorFormateado .'), extendido por el Registro Nacional de las Personas de la República de Guatemala; personas que firman nuevamente conmigo al final de la presente acta de legalización, no así el/la deudor/a quien por ignorar hacerlo deja estampada la impresión digital del dedo pulgar de la mano derecha, firmando a su ruego la testigo civilmente capaz, idónea y de mi conocimiento Roberto Juan Baltzar, quien estuvo presente desde el inicio hasta el final de la presente acta de legalización DOY FE.', $fontStyletextfiador, $paragraphJustify);

        $section->addTextBreak(2); 


    $displayNombreCliente = isset($nombreclietne) ? $nombreclietne : (isset($nombrecliente) ? $nombrecliente : '');
    $displayNombreAval = isset($nombrecompletoaval) ? $nombrecompletoaval : '';
    
    // Tabla con dos columnas para las firmas
    $table = $section->addTable([
        'alignment' => Jc::CENTER,
        'cellMarginTop' => 80,
        'cellMarginBottom' => 80,
    ]);
    
    $table->addRow();
    $cellWidth = 4000;
    $cell1 = $table->addCell($cellWidth);
    $cell2 = $table->addCell($cellWidth);
    $cell3 = $table->addCell($cellWidth);
    $cell4 = $table->addCell($cellWidth);

    // Espacio para la firma (línea)
    $line = '_______________________________';
    $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
    // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    $cell2->addText('', null, ['spaceAfter' => 0]);
    // $cell3->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell2->addText('FIADOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);

    $cell3->addText('', null, ['spaceAfter' => 0]);
    // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell3->addText('TESTIGO', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    $cell4->addText('', null, ['spaceAfter' => 0]);
    // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell4->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    }else{ //si el cliente si sabe firmar

    $textRun->addText('QUINTA: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Los señores/as ', $fontStyletextfiador, $paragraphJustify);
    $textRun->addText($nombrecliente . ' Y ' . $nombrecompletoaval,['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText(', declaran que en forma expresa aceptan el presente contrato a su favor  y renuncian al fuero de su domicilio y se sujetan a los tribunales que GRUPO KOTANH, SOCIEDAD ANONIMA elija en caso de incumplimiento al presente contrato de mutuo (préstamo) y señalan como lugar para recibir notificaciones sus residencias situadas en ' . $diredomicilio . ', del municipio de ' . $municipiocli . ' departamento de ' . $departamentocli . '. ', $fontStyletextfiador, $paragraphJustify);


    $textRun->addText('SEXTA: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun->addText('Los comparecientes, declaran que después de estar informados de su contenido, valor, objetivo, costos legales, los términos consignados y en las calidades que comparecen, aceptan y firman el presente contrato.', $fontStyletextfiador, $paragraphJustify);

    // Firma del cliente y del aval
    $section->addTextBreak(2); 


    $displayNombreCliente = isset($nombreclietne) ? $nombreclietne : (isset($nombrecliente) ? $nombrecliente : '');
    $displayNombreAval = isset($nombrecompletoaval) ? $nombrecompletoaval : '';
    
    // Tabla con dos columnas para las firmas
    $table = $section->addTable([
        'alignment' => Jc::CENTER,
        'cellMarginTop' => 80,
        'cellMarginBottom' => 80,
    ]);
    
    $table->addRow();
    $cellWidth = 4500;
    $cell1 = $table->addCell($cellWidth);
    $cell2 = $table->addCell($cellWidth);
    $cell3 = $table->addCell($cellWidth);

    // Espacio para la firma (línea)
    $line = '_______________________________';
    $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
    // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    $cell2->addText('', null, ['spaceAfter' => 0]);
    // $cell3->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell2->addText('FIADOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);

    $cell3->addText('', null, ['spaceAfter' => 0]);
    // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell3->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    $section->addTextBreak(2);
    $textRun2 = $section->addTextRun($paragraphJustify);

    $textRun2->addText('ACTA DE LEGALIZACIÓN DE FIRMAS: ', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText('En la aldea Ticolal Pueblo Nuevo Jucup del municipio de San Sebastián Coatán, departamento de Huehuetenango, el día ' . $fechaenletras .', el infrascrito notario da fe: Que las firmas que anteceden son ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText('AUTENTICAS', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText(', en virtud de haber sido puestas el día de hoy y en mi presencia por los señores/as: ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText('SEBASTIAN JUAN BALTAZAR', ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText(', quien se identifica con el documento personal de identificación DPI; Código Único de Identificación: Dos mil ciento cuarenta y cinco Diecisiete mil ciento cuarenta y cuatro Mil trescientos veinticinco (2145 17144 1325), extendido por el Registro Nacional de las Personas de la República de Guatemala; quien comparece en calidad de REPRESENTANTE LEGAL de la entidad GRUPO KOTNAH, SOCIEDAD ANONIMA, así mismo los signatarios señores/as ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify);
    $textRun2->addText(', quien se identifica con el documento personal de identificación; CUI: ' . $dpiClienteTexto . ' (' . $dpiClienteFormateado .'), extendido por el Registro Nacional de las Personas de la República de Guatemala; y ', $fontStyletextfiador, $paragraphJustify);
    $textRun2->addText($nombrecompletoaval, ['bold' => true, 'name' => 'Arial', 'size' => 11], $paragraphJustify); 
    $textRun2->addText(', quien se identifica con el documento personal de identificación; CUI: ' . $dpiFiadorTexto . ' (' . $dpiFiadorFormateado .'), extendido por el Registro Nacional de las Personas de la República de Guatemala; personas que firman nuevamente conmigo al final de la presente acta de legalización DOY FE.', $fontStyletextfiador, $paragraphJustify);

        $section->addTextBreak(2); 


    $displayNombreCliente = isset($nombreclietne) ? $nombreclietne : (isset($nombrecliente) ? $nombrecliente : '');
    $displayNombreAval = isset($nombrecompletoaval) ? $nombrecompletoaval : '';
    
    // Tabla con dos columnas para las firmas
    $table = $section->addTable([
        'alignment' => Jc::CENTER,
        'cellMarginTop' => 80,
        'cellMarginBottom' => 80,
    ]);
    
    $table->addRow();
    $cellWidth = 4500;
    $cell1 = $table->addCell($cellWidth);
    $cell2 = $table->addCell($cellWidth);
    $cell3 = $table->addCell($cellWidth);

    // Espacio para la firma (línea)
    $line = '_______________________________';
    $cell1->addText('', null, ['spaceAfter' => 0]); // pequeño espaciado
    // $cell1->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell1->addText('DEUDOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell1->addText('El AGENTE', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);

    $cell2->addText('', null, ['spaceAfter' => 0]);
    // $cell3->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell2->addText('FIADOR (A)', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);

    $cell3->addText('', null, ['spaceAfter' => 0]);
    // $cell2->addText($line, ['name' => 'Corrier', 'size' => 9], ['alignment' => Jc::CENTER]);
    $cell3->addText('REPRESENTANTE LEGAL', ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => Jc::CENTER]);
    // $cell2->addText('EL(LA)', ['name' => 'Corrier', 'size' => 8], ['alignment' => Jc::CENTER]);


    }
}

   

    





$section->addTextBreak(1);

ob_start();
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save("php://output");
$worddata = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile' => "Contrato",
    'tipo' => "vnd.ms-word",
    'extension' => "docx",
    'download' => 1,
    'data' => "data:application/vnd.ms-word;base64," . base64_encode($worddata)
);
echo json_encode($opResult);
exit;