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
                WHERE tgc.id_cremcre_meta=?
                AND clig.idTipoGa = '1';";
$qdatosppg = "SELECT ppg.ccodcta, ppg.nintere interesesppg, ppg.ncapita capitalppg, ppg.cnrocuo cuotasppg, ppg.dfecven fechavenppg, ppg.OtrosPagos otrosppg,ppg.SaldoCapital saldo_capital, ppg.NAhoProgra vinculado,
                ppg.ncapita capitalcuota, ppg.dfecven fechacuota,
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MIN(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.ncapita ELSE 0 END) AS capital_primera,
                SUM(CASE WHEN ppg.cnrocuo = (SELECT MAX(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.ncapita ELSE 0 END) AS capital_ultima,
                MAX(CASE WHEN ppg.cnrocuo = (SELECT MIN(ppg2.cnrocuo) FROM Cre_ppg ppg2 WHERE ppg2.ccodcta = ppg.ccodcta) THEN ppg.dfecven ELSE 0 END) AS fecha_primera_cuota,
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
$productosgastos = "SELECT creProGa.monto AS monto_gastoAdmin
                    FROM cre_productos_gastos creProGa
                    INNER JOIN cremcre_meta cre ON cre.CCODPRD = creProGa.id_producto
                    WHERE creProGa.id_tipo_deGasto = 1
                    AND creProGa.tipo_deCobro = 1
                    AND cre.CCODCTA = ?;";




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
        $showmensaje = true;
        throw new Exception("No se encontraron datos de garantía");
    }

    $productosgastosresult = $database->getAllResults($productosgastos, [$codcuenta]);
    if (empty($productosgastosresult)) {
        // $showmensaje = true;
        // throw new Exception("No se encontraron datos de gastos administrativos");
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
$fechaprimeracuota = $ppg[0]["fecha_primera_cuota"];
$fechaprimeracuotaletras = fechaCompleta($fechaprimeracuota);
$tasamoraletras = str_ireplace("CON", "PUNTO", numeroATexto($tasamora));
$tasainteresfloat = floatval($tasaintere);
$tasainteresmensual = $tasainteresfloat / 12;

$porcentgastoadmin = $productosgastosresult[0]['monto_gastoAdmin'];
$porcentgastoadminTexto = str_ireplace("CON", "PUNTO", numeroATexto($porcentgastoadmin));


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
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // Margen superior 2.5 cm
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // Margen derecho 2.5 cm
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(4.0), // Margen inferior 2.5 cm
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2)  // Margen izquierdo 2.5 cm
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
    'lineHeight' => 1.5
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

// // Agregar la imagen centrada dentro de la celda
// $cell->addImage(
//     '../../../../includes/img/credisuma.png',
//     [
//         'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(5),
//         'wrappingStyle' => 'inline',
//         'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
//     ]
// );

// Texto de página con campos que Word resolverá: {PAGE} y {NUMPAGES}
$header->addPreserveText(
    'Página {PAGE} de {NUMPAGES}',
    ['bold' => true, 'name' => 'Corrier', 'size' => 9],
    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0]
);


if($generocliente == 'F'){
    $Nacionalidad = 'guatemalteca';
    $señor_a = 'la señora';
}else{
    $Nacionalidad = 'guatemalteco';
    $señor_a = 'el señor';
}

$descripcionGarantia = "";
if(empty($garantia)){
    $descripcionGarantia = "0";
    $direcgarantia = "0";
}else{
    if(count($garantia) > 1){
        for($i = 0; $i < count($garantia); $i++){

            if($i == count($garantia) - 1){
                $descripcionGarantia .= 'y una ' . $garantia[$i]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en '. $garantia[$i]['direccionGarantia'] . ', ';
            }else{
                $descripcionGarantia .= 'Una ' . $garantia[$i]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en ' . $garantia[$i]['direccionGarantia'] . ', ';
            }
        }
    }else{
        $descripcionGarantia = 'Una ' . $garantia[0]['descripcionGarantia'] . ' a favor de: Grupo Kotanh, Sociedad Anónima que se localiza en ' . $garantia[0]['direccionGarantia'] . ', ';
    }

}

    $montogastoadmin = ($monto /100) * $porcentgastoadmin;
    $resultmontgastoadmin = $monto - $montogastoadmin;

    $tasaintmensualmodificado = str_ireplace("con", "PUNTO", numeroATexto($tasainteresmensual));

    $diapagocuotas = date('d', strtotime($fechaprimeracuota));
    $diapagocuotasletras = numeroALetrasCompleto($diapagocuotas);

    $section->addText('CONTRATO MUTUO', ['bold' => true, 'name' => 'Corrier', 'size' => 12], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

    $textRun = $section->addTextRun($paragraphJustify);

    $textRun->addText('En el municipio de Santa Cruz Barillas, departamento de Huehuetenango, el dieciséis de octubre de dos mil veinticinco, NOSOTROS: TOMÁS FRANCISCO PEDRO, de treinta y cuatro años de edad, Soltero, guatemalteco, comerciante, con domicilio en en el departamento de Huehuetenango, me identifico con el Documento Personal de Identificación con Código Único de Identificación número: dos mil noventa y uno espacio veintiséis mil setecientos sesenta espacio un mil trescientos veintiséis (2091 26760 1326), extendido por el Registro Nacional de las Personas de la República de Guatemala. Actúo en calidad de propietario y representante de la empresa mercantil denominada: CREDISUMA R L, calidad que acredito con la Patente de Comercio de Empresa, debidamente inscrita en el Registro Mercantil de la República de Guatemala, Centroamérica, bajo el número: un millón diecisiete mil novecientos sesenta y ocho (1017968), folio cuarenta y cuatro (44) del libro mil ciento noventa (1190) de Empresas Mercantiles, con fecha de inscripción doce de enero del año dos mil veintitrés, quien en lo sucesivo del presente documento podré denominarme simple e indistintamente como la "PARTE ACREEDORA"; y por la otra parte: ', $fontStyletext, $paragraphStyle);

    $textRun->addText($nombrecliente . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);

    $textRun->addText('de ' . $edadClienteTexto . ' años de edad, ' . $estadocivilcli . ', ' . $Nacionalidad . ', ' . $profesioncliente . ', de este domicilio, me identifico con el Documento Personal de Identificación con Código Único de Identificación número: ', $fontStyletext, $paragraphStyle);

    $textRun->addText($dpiClienteTexto . ' (' . $dpiClienteFormateado . '), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('quien en lo sucesivo del presente documento podré denominarme simple e indistintamente como la ', $fontStyletext, $paragraphStyle);

    $textRun->addText('"PARTE DEUDORA";', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' y ', $fontStyletext, $paragraphStyle);

    $textRun->addText($nombrecompletoaval . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);

    $textRun->addText('de ' . $edadFiadorTexto . ' años de edad, ' . $estadocivilfiador . ', guatemalteco, ' . $profesionfiador . ', de este domicilio, me identifico con el Documento Personal de Identificación con Código Único de Identificación número: ', $fontStyletextfiador, $paragraphStyle);

    $textRun->addText($dpiFiadorTexto . ' (' . $dpiFiadorFormateado . '), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('quien en lo sucesivo del presente documento podré denominarme simple e indistintamente como la ', $fontStyletext, $paragraphStyle);
    $textRun->addText('"PARTE FIADORA";', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);

    $textRun->addText('Aseguramos ser de los datos de identificación consignados y hallarnos en el libre ejercicio de nuestros derechos civiles y manifestamos que por este acto celebramos: ', $fontStyletext, $paragraphStyle);

    $textRun->addText('CONTRATO DE RECONOCIMIENTO DE DEUDA EN DOCUMENTO PRIVADO CON FIRMA LEGALIZADA', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', de conformidad con las siguientes cláusulas: ', $fontStyletext, $paragraphStyle);
    $textRun->addText('PRIMERA, CONCESIÓN DEL CRÉDITO: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('YO ', $fontStyletext, $paragraphStyle);
    $textRun->addText('TOMÁS FRANCISCO PEDRO', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', en la calidad con la que actúo, manifiesto de conformidad con la resolución para aprobación de créditos ', $fontStyletext, $paragraphStyle);
    $textRun->addText('(CRED-2025), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('que el Comité de Crédito de ', $fontStyletext, $paragraphStyle);
    $textRun->addText('CREDISUMA R L, ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('por medio del Acta Número: - - - - - (229), de fecha quince de octubre de dos mil veinticinco, APROBÓ la solicitud de préstamo de el señor ', $fontStyletext, $paragraphStyle);
    $textRun->addText($nombrecliente . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('con número de asociado: ', $fontStyletext, $paragraphStyle);
    $textRun->addText($codcliente . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('correspondiente al número de crédito: ', $fontStyletext, $paragraphStyle);
    $textRun->addText($codigocred . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('concediéndosele una cantidad de: ', $fontStyletext, $paragraphStyle);
    $textRun->addText($monto2 . ' (Q.' . $monto . '), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('cantidad por la que desde ahora el señor: ', $fontStyletext, $paragraphStyle);
    $textRun->addText($nombrecliente . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('se reconoce liso y llano deudor de ', $fontStyletext, $paragraphStyle);
    $textRun->addText('CREDISUMA R L, ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('en la forma y condiciones que expresamos más adelante. ', $fontStyletext, $paragraphStyle);

    $textRun->addText('SEGUNDA, PLAZO Y FORMA DE PAGO: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('Ambas partes declaramos que las condiciones a la que va estar sujeto el préstamo son: a) El plazo para el cumplimiento de la obligación es de ', $fontStyletext, $paragraphStyle);
    $textRun->addText($nocuotas2 . ' ' . $tipoPeriodoText2, ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', contados a partir de la fecha de desembolso de los fondos, siendo el dieciséis de octubre de dos mil veinticinco. b) La ', $fontStyletext, $paragraphStyle);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' pagará a ', $fontStyletext, $paragraphStyle);
    $textRun->addText('CREDISUMA R L, ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('el monto del préstamo mediante ', $fontStyletext, $paragraphStyle);
    $textRun->addText($nocuotas2 . ' CUOTAS ',['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText($tipoPeriodoText4 . ', vencidas y consecutivas, de conformidad con el siguiente plan de amortizaciones: ' . $nocuotas2 . ' amortizaciones a capital de ', $fontStyletext, $paragraphStyle);
    $textRun->addText(numeroALetras($primeracouta) . ' (Q.' . number_format($primeracouta, 2) . '), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('más sus respectivos intereses. La primera cuota se realizará el ' . $fechaprimeracuotaletras . ', debiéndose hacer efectivas las demás el ' . $diapagocuotasletras .' de cada mes calendario, hasta cumplir con el plazo de los ', $fontStyletext, $paragraphStyle);
    $textRun->addText($nocuotas2 . ' ' . $tipoPeriodoText2 . 'TERCERA, INTERÉS SOBRE EL CAPITAL: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphJustify);
    $textRun->addText('Yo, la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"LA PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', reconozco y pagaré intereses a razón del ', $fontStyletext, $paragraphJustify);
    $textRun->addText($tasaintereLetra . ' por ciento anual (' . $tasaintere . '), ' , ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphJustify);
    $textRun->addText('sobre la cantidad total concedida en préstamo, la cual se dividirá por el ', $fontStyletext, $paragraphJustify);
    $textRun->addText($tasaintmensualmodificado . ' por ciento (' . $tasainteresmensual . '%) ',['bold' => true, 'name' => 'Corrier', 'size' => 11] ,$paragraphJustify);
    $textRun->addText('mensual sobre saldo, pago que comenzaré a hacer efectivo juntamente con el plan de amortizaciones.', $fontStyletext, $paragraphJustify);
    $textRun->addText('CUARTA, INTERESES MORATORIOS: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('Cuando exista un retraso en el pago pactado, ya sea de capital o intereses, CREDISUMA R L cobrará un recargo del ', $fontStyletext, $paragraphJustify);
    $textRun->addText($tasamoraletras . ' POR CIENTO (' . $tasamora . '%), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('arriba de la tasa de interés anual que se esté aplicando al crédito al momento de producirse la mora, el cual se aplicará a partir de los dos días siguientes de vencido el plazo para el pago de intereses y capital. ', $fontStyletext, $paragraphJustify);
    $textRun->addText('QUINTA, GASTOS ADMINISTRATIVOS: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('Ambas partes continuamos manifestando que los gastos administrativos realizados a causa de este negocio corren a cuenta de la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', los cuales serán adquiridos sobre la cantidad concedida, por lo tanto, sobre los ', $fontStyletext, $paragraphJustify);
    $textRun->addText($monto2 . ' (Q.' . $monto . ') ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('se descontarán el ', $fontStyletext, $paragraphJustify);
    $textRun->addText($porcentgastoadminTexto . ' POR CIENTO (' . $porcentgastoadmin . '%), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('designado a gastos administrativos, entregándole a la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' una cantidad de ', $fontStyletext, $paragraphJustify);
    $textRun->addText(numeroALetras($resultmontgastoadmin) . ' (Q.' . $resultmontgastoadmin . '). ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('La ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' enfatiza que el monto sobre el cual se reconoce como liso y llano deudor de ', $fontStyletext, $paragraphJustify);
    $textRun->addText('CREDISUMA R L ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('es de ', $fontStyletext, $paragraphJustify);
    $textRun->addText($monto2 . ' (Q.' . $monto . '). SEXTA, LUGAR DE PAGO: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('Manifestamos que todo pago de intereses y capital lo hará la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('en las instalaciones de CREDISUMA R L, ubicada en tercera avenida seis guion cero seis zona uno Santa Cruz Barillas, departamento de Huehuetenango, en las fechas estipuladas y sin necesidad de cobro o requerimiento. El pago se acreditará mediante recibo simple que extenderá la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE ACREEDORA". SÉPTIMA, CONSTITUCIÓN DE LA GARANTÍA: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('Yo, ', $fontStyletext, $paragraphJustify);
    $textRun->addText($nombrecompletoaval . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' expreso que con el objeto de garantizar todas y cada una de las obligaciones contraídas por la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' con la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE ACREEDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', en virtud de este contrato, o que sean exigibles de conformidad con la ley, por concepto de capital adeudado, intereses, gastos de cualquier naturaleza y costas judiciales o extrajudiciales, en sus respectivos casos, si llegaren a causarse, me constituyo como ', $fontStyletext, $paragraphJustify);
    $textRun->addText('FIADORA ILIMITADA Y MANCOMUNADA SOLIDARIAMENTE ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('de el señor ', $fontStyletext, $paragraphJustify);
    $textRun->addText($nombrecliente . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('a efecto de garantizar el pago de capital, intereses, intereses moratorios, gastos de cobranza, costas procesales si llegaren a ocasionarse, y todas y cada una de las obligaciones asumidas por la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' de conformidad con el presente contrato, manifiesto que la fianza subsistirá no solo por el plazo original del contrato, sino también por las prórrogas que puedan concedérsele, sea cual fuera su duración, hasta el total cumplimiento de las obligaciones de la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' sin necesidad de algún aviso ni suscripción de documento alguno y desde ahora acepto dicho contrato y garantizo su fianza con los bienes presentes y futuros. ', $fontStyletext, $paragraphJustify);
    $textRun->addText('OCTAVA, DISPOSICIONES PROCESALES: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('La ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' y la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE FIADORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' a) Reconocemos desde esta fecha como buenas y exactas las cuentas que la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE ACREEDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' presente sobre este negocio y como válido, líquido y de plazo vencido el saldo que se me reclame y como ', $fontStyletext, $paragraphJustify);
    $textRun->addText('TÍTULO EJECUTIVO ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('el presente contrato; b) Que todos los gastos judiciales y extrajudiciales que origine este negocio a la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE ACREEDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' sean de mi cuenta, la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' y la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE FIADORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('; c) Para los efectos de cumplimiento y ejecución del presente contrato, renunciamos al fuero de nuestro domicilio y aceptamos que se nos someta a los órganos jurisdiccionales competentes de la República de Guatemala, que elija la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE ACREEDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', para el caso de ejecución o acción judicial, y señalamos como lugar para recibir citaciones y notificaciones nuestro domicilio ubicado en ', $fontStyletext, $paragraphJustify);
    $textRun->addText($diredomicilio, ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' del Municipio de ' . $municipiocli .', Departamento de ' . $departamentocli .', , obligándonos a comunicar por escrito cualquier cambio que tuviéremos, en el entendido de que si no lo hiciéremos serán válidas y surtirán pleno efecto las citaciones, emplazamientos y notificaciones judiciales y extrajudiciales, que se hicieren en los lugares señalados. ', $fontStyletext, $paragraphJustify);
    $textRun->addText('NOVENA, VENCIMIENTO ANTICIPADO DEL PLAZO: ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText('La ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE ACREEDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' podrá dar por vencido el plazo de esta obligación por cualquiera de las causas siguientes: a) Si la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' deja de pagar más de dos amortizaciones en la fecha, forma y modo convenido, que correspondan a capital e interés; b) Si la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE DEUDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' incumple cualquiera de las obligaciones que en este documento contrae o las que establece la ley. En ese caso, la ', $fontStyletext, $paragraphJustify);
    $textRun->addText('"PARTE ACREEDORA"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' lo tomará en forma negativa a la voluntad de cumplir con lo convenido por lo que iniciará las acciones legales correspondientes para exigir el pago del préstamo otorgado.', $fontStyletext, $paragraphJustify);
    $textRun->addText('DECIMA', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(' Yo ', $fontStyletext, $paragraphJustify);
    $textRun->addText('TOMÁS FRANCISCO PEDRO', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun->addText(', en calidad con que actúo, declaro que en los términos relacionados acepto el reconocimiento de deuda que se hace a mi favor en este documento y la forma de pago. Que leemos individualmente y debidamente escrito, y bien enterados de su contenido, objeto, validez y demás efectos legales, lo aceptamos, ratificamos y firmamos.', $fontStyletext, $paragraphJustify);


    $section->addTextBreak(3);

        $tableFirmas = $section->addTable([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
    ]);
    
    // ---------------- LÍNEAS DE FIRMA ----------------
    $tableFirmas->addRow(350, ['exactHeight' => true]);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 35),
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    $tableFirmas->addCell(2000)->addText('', null, $paragraphJustify);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 35),
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    
    // ---------------- NOMBRES ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'TOMÁS FRANCISCO PEDRO' ,
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    $tableFirmas->addCell(2000)->addText('', null, $paragraphJustify);
    $tableFirmas->addCell(3000)->addText(
        $nombrecliente ,
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );

    // ---------------- xd ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'ACREEDOR',
        ['bold'=>true,'name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    $tableFirmas->addCell(2000)->addText('', null, $paragraphJustify);
    $tableFirmas->addCell(3000)->addText(
        'DEUDOR',
        ['bold'=>true,'name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );


    $section->addTextBreak(3);
    $section->addText('_' . str_repeat('_', 40), ['name' => 'Arial', 'size' => 9], $paragraphJustify);
    $section->addTextBreak(0.9);
    $section->addText($nombrecompletoaval, ['name' => 'Arial', 'size' => 9], $paragraphJustify);
    $section->addTextBreak(0.9);
    $section->addText('FIADOR', ['bold'=>true,'name' => 'Arial', 'size' => 9], $paragraphJustify);
    
    



    $section->addTextBreak(2);
    $textRun2 = $section->addTextRun($paragraphJustify);


    $textRun2->addText('"AUTENTICA DE FIRMAS".', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText(' En el municipio de ' . $municipiocli . ', departamento de ' . $departamentocli . ', el ' . $hoy3text . ',', $fontStyletext, $paragraphJustify);
    $textRun2->addText('COMO NOTARIA HAGO CONSTAR;', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText(' que ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('LAS FIRMAS', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText(' que anteceden ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('"SON AUTENTICAS"', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText(' por haber sido puestas el día de hoy en mi presencia por los señores: ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('TOMÁS FRANCISCO PEDRO, ' . $nombrecliente . ' y ' . $nombrecompletoaval . ', ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText(' quienes se identifican respectivamente con el Documento Personal de Identificación con Código Único de Identificación números: ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('dos mil noventa y uno espacio veintiséis mil setecientos sesenta espacio un mil trescientos veintiséis (2091 26760 1326); ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText(' segundo: ', $fontStyletext, $paragraphJustify);
    $textRun2->addText($dpiClienteTexto . ' (' . $dpiClienteFormateado . ');', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText(' y tercero: ', $fontStyletext, $paragraphJustify);
    $textRun2->addText($dpiFiadorTexto . ' (' . $dpiFiadorFormateado . '), ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText('extendido por el Registro Nacional de las Personas de la República de Guatemala; las cuales constan ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('EN UN DOCUMENTO PRIVADO DE RECONOCIMIENTO DE DEUDA SUSCRITO Y FIRMADO POR LAS PERSONAS IDENTIFICADAS ANTERIORMENTE, ', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);
    $textRun2->addText('contenido en dos hojas, suscritas en su lado anverso y reverso, las cuales numero, sello y firmo y adjunto una tercera hoja la cual contiene la presente Acta de Legalización de Firmas. Que leo lo escrito a los signatarios y estando bien impuestos de su contenido, objeto, validez y demás efectos legales que les advertí; expresan que lo aceptan, ratifican y firman, firmando a continuación la Notaria autorizante, quien de todo lo relacionado, ', $fontStyletext, $paragraphJustify);
    $textRun2->addText('DA FE.', ['bold' => true, 'name' => 'Corrier', 'size' => 11], $paragraphStyle);

    $section->addTextBreak(3);
 
            $tableFirmas = $section->addTable([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
    ]);
    
    // ---------------- LÍNEAS DE FIRMA ----------------
    $tableFirmas->addRow(350, ['exactHeight' => true]);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 35),
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    $tableFirmas->addCell(2000)->addText('', null, $paragraphJustify);
    $tableFirmas->addCell(4000)->addText(
        '_' . str_repeat('_', 35),
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    
    // ---------------- NOMBRES ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'TOMÁS FRANCISCO PEDRO' ,
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    $tableFirmas->addCell(2000)->addText('', null, $paragraphJustify);
    $tableFirmas->addCell(3000)->addText(
        $nombrecliente ,
        ['name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );

    // ---------------- xd ----------------
    $tableFirmas->addRow(300, ['exactHeight' => true]);
    $tableFirmas->addCell(3000)->addText(
        'ACREEDOR',
        ['bold'=>true,'name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );
    $tableFirmas->addCell(2000)->addText('', null, $paragraphJustify);
    $tableFirmas->addCell(3000)->addText(
        'DEUDOR',
        ['bold'=>true,'name' => 'Arial', 'size' => 9],
        $paragraphJustify
    );


    $section->addTextBreak(3);
    $section->addText('_' . str_repeat('_', 40), ['name' => 'Arial', 'size' => 9], $paragraphJustify);
    $section->addTextBreak(0.9);
    $section->addText($nombrecompletoaval, ['name' => 'Arial', 'size' => 9], $paragraphJustify);
    $section->addTextBreak(0.9);
    $section->addText('FIADOR', ['bold'=>true,'name' => 'Arial', 'size' => 9], $paragraphJustify);
    
    



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