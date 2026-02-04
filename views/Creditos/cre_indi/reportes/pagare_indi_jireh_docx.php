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

$nombrescliente = $result[0]["primer_name"] . " " . $result[0]["segundo_name"] . " " . $result[0]["tercer_name"];
$apellidoscliente = $result[0]["primer_last"] . " " . $result[0]["segundo_last"];

$dia = "Jueves";
$mes = "Septiembre";
$anio = "2024";

$nombrecliente = $result[0]["nombrecli"];
$diredomicilio = $result[0]["direccion"];
$dpi = $result[0]["no_identifica"];
$celular = $result[0]["tel_no1"];
$totalintereses = $ppg[0]["total_interes"];

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

$section->addImage(
    '../../../../includes/img/jireh.png', // Ruta a tu imagen
    [
        'width' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(5), // Puedes ajustar el tamaño
        'height' => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(3), // Opcional
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, // Centra la imagen
        'wrappingStyle' => 'inline'
    ]
);

$section->addText("PAGARÉ", ['bold' => true, 'name' => 'Arial', 'size' => 12], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$section->addText("LIBRE DE PROTESTO", ['bold' => true, 'name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$section->addText("POR: Q" . number_format($monto_total, 2), ['bold' => true, 'name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText("POR EL VALOR RECIBIDO", ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(' YO: ' . $nombrecliente . ', ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText("quien se identifica con el documento personal de identificación DPI, con el Código Único de Identificación (CUI) Número " . '(' . $dpi . '), 
Extendido por el registro Nacional de las Personas de la República de Guatemala (RENAP), en adelante indistintamente como ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('LA PARTE DEUDORA, ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('prometo incondicionalmente pagar libre de protesto a ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('INVERSIONES Y SERVICIOS FINANCIEROS JIREH, SOCIEDAD ANÓNIMA, ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('la Suma de ' . $cantidadenletras . ' (Q. ' . number_format($monto_total, 2) . '), ' . 'autorizado en el Municipio de Tecpán celebrada el ' . $hoy . ', 
en los términos que se detallan a continuación:', ['bold' => false, 'name' => 'Arial', 'size' => 11]);

$section->addText(' ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);

$phpWord->addParagraphStyle('justificadoConSangria', [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
    'indentation' => ['left' => 720, 'hanging' => 360],
    'spaceAfter' => 120,
]);

$phpWord->addNumberingStyle(
    'viñetasLetras',
    [
        'type' => 'singleLevel',
        'levels' => [
            [
                'format' => 'lowerLetter',
                'text' => '%1)',
                'left' => 720,
                'hanging' => 360,
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT,
            ],
        ],
    ]
);

$tipoperiodoDescripcion = $tiposPeridodo[$tipoperiodo] ?? 'NO DEFINIDO';
$section->addListItem(
    'EL PLAZO: El plazo del presente es de ' . $nocuotas . ' cuotas ' . strtolower($tipoperiodoDescripcion) . ' que se computaran a partir del desembolso del préstamo.',
    0,
    ['name' => 'Arial', 'size' => 11],
    'viñetasLetras',
    'justificadoConSangria'
);
$section->addListItem(
    'LA TASA DE INTERÉS: ' . $tasaintereLetra . ' (' . $tasaintere . '%), tasa anual variable, pagaderos mensualmente.',
    0,
    ['name' => 'Arial', 'size' => 11],
    'viñetasLetras',
    'justificadoConSangria'
);
$section->addListItem(
    'DESTINO: ' . $result[0]['destinocredito'],
    0,
    ['name' => 'Arial', 'size' => 11],
    'viñetasLetras',
    'justificadoConSangria'
);


$section->addListItem(
    'FORMA DE PAGO: ' . $nocuotas . ' cuotas ' . strtolower($tipoperiodoDescripcion) . ' de Q. ' . number_format($cuota, 2) . '.',
    0,
    ['name' => 'Arial', 'size' => 11],
    'viñetasLetras',
    'justificadoConSangria'
);
$section->addListItem(
    'LUGAR DE PAGO: Todo pago se realizara en la agencia ubicada en Zona 2 Tecpán  camino a finca la giralda  o a través de un Gestor de cobros autorizado por 
    INVERSIONES Y SERVICIOS FINANCIEROS JIREH, SOCIEDAD ANÓNIMA.',
    0,
    ['name' => 'Arial', 'size' => 11],
    'viñetasLetras',
    'justificadoConSangria'
);
$section->addListItem(
    'FORMA DE ENTREGA: Un solo pago contra la firma del presente pagaré.',
    0,
    ['name' => 'Arial', 'size' => 11],
    'viñetasLetras',
    'justificadoConSangria'
);
$section->addListItem(
    'INCUMPLIMIENTO: SI LA PARTE DEUDORA no hiciera a la fecha convenida la provisión de los fondos  se le aplicara un recargo para cubrir los gastos de cobranza, 
    cuyo monto no excederá del quince  por ciento (15%) por cada día o mes de retraso computado sobre el importe de la cuota o cuotas en mora por capital e intereses, 
    que el incumplimiento de estas obligaciones da derecho al tenedor del presente título de crédito dar por vencido el plazo de esta obligación en forma anticipada y exigir 
    el pago íntegro del saldo del capital, intereses, intereses moratorios, gastos y costos derivados del cobro judicial o extrajudicial del mismo.',
    0,
    ['name' => 'Arial', 'size' => 11],
    'viñetasLetras',
    'justificadoConSangria'
);


$section->addText("OTRAS ESTIPULACIONES", ['bold' => true, 'name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText("Expresamente renuncio al fuero de mi domicilio y me someto a los tribunales que INVERSIONES Y SERVICIOS FINANCIEROS JIREH, SOCIEDAD ANÓNIMA 
elija para el caso de ejecución y acepto expresamente: A) Que INVERSIONES Y SERVICIOS FINANCIEROS JIREH, SOCIEDAD ANÓNIMA pueda a su elección utilizar el procedimiento 
que señala la ley de Bancos, el Código Procesal Civil Mercantil o cualquier otra ley que al respecto se emita en el futuro; B) Que los depositarios e interventores que 
INVERSIONES Y SERVICIOS FINANCIEROS JIREH, SOCIEDAD ANÓNIMA pueda nombrar con motivo de ejecución en su contra no deban prestar fianza o caución alguna y que 
INVERSIONES Y SERVICIOS FINANCIEROS JIREH, SOCIEDAD ANÓNIMA no es responsable de las actuaciones de estos C) que cualquier notificación, sea judicial o extrajudicial, citación,
Emplazamiento, correspondencia o cualquiera otra comunicación se le dirija a mi residencia ubicada en ", ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($diredomicilio . ', ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('dándolas desde ya por válidas y bien hechas las que en ese lugar se me hagan, salvo que por escrito y con quince días de anticipación haya dado aviso a 
INVERSIONES Y SERVICIOS FINANCIEROS JIREH, SOCIEDAD ANÓNIMA de haberla mudado, para la cual deberá presentar aviso de recepción de dicha noticia por parte de INVERSIONES Y SERVICIOS FINANCIEROS JIREH, 
SOCIEDAD ANÓNIMA.', ['bold' => false, 'name' => 'Arial', 'size' => 11]);

$section->addTextBreak(3);
$section->addText("F_______________________________", ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText("DPI: " . $dpi, ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText("Celular: " . $celular, ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);

$section->addTextBreak(1);

$section->addText("Tecpán Guatemala, Departamento de Chimaltenango el " . $hoy, ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);

$section->addText("AUTENTICA:", ['bold' => true, 'name' => 'Arial', 'size' => 10], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

$textRun = $section->addTextRun($paragraphJustify);
$textRun->addText("En el Municipio de Tecpán Guatemala, Departamento de Chimaltenango a los " . fechaCompleta($hoy) . ", ", ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('YO, ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('el infrascrito Notario doy fe que la firma que antecede es ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(' AUTENTICA ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('por haber sido puesta el día de hoy en mi presencia ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText($nombrecliente . ', ', ['bold' => true, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('quien se identifica con el documento personal de identificación DPI, con el Código Único de Identificación (CUI) Número ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText(' (' . $dpi . '), ', ['bold' => false, 'name' => 'Arial', 'size' => 11]);
$textRun->addText('extendido por el registro Nacional de las Personas de la República de Guatemala (RENAP), quien firma nuevamente la presente Acta de Legalización.', ['bold' => false, 'name' => 'Arial', 'size' => 11]);

$section->addTextBreak(3);
$section->addText("F_______________________________", ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText($nombrecliente, ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText("DPI: " . $dpi, ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
$section->addText("Celular: " . $celular, ['bold' => false, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);

$section->addTextBreak(3);

$section->addText("ANTE MÍ", ['bold' => true, 'name' => 'Arial', 'size' => 11], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
// $section->addText("POR EL VALOR RECIBIDO YO: $nombrecliente Guatemalteco (a), $profesion, con domicilio ubicado en $diredomicilio, me identifico con DPI número $dpi extendido por el Registro Nacional de Personas (RENAP), por medio del presente PAGARÉ LIBRE DE PROTESTO, Prometo incondicionalmente pagar a la orden de la entidad $razonsocial cuyo nombre comercial es $namecomercial La cantidad de:", $fontStyletext,$paragraphJustify);


// $section->addText("Dicha cantidad se amortizará de la siguiente manera: $nocuotas pagos consecutivos en $cantdias días, en cuotas de Q $cuota a partir de la presente fecha del contrato, hasta completar la totalidad de la deuda, la falta de pago y el vencimiento del plazo, la entidad $razonsocial podrá exigir íntegramente el cumplimiento de la obligación generada por el presente título de crédito. En caso de incumplimiento RENUNCIO al fuero de mi domicilio y me someto a los tribunales que elija la entidad prestadora del servicio, para ejecución del presente título, señalo como lugar para recibir notificaciones mi residencia los intereses moratorios por atraso o incumplimiento ascenderán a la cantidad del 7% mensual.", $fontStyletext,$paragraphJustify);


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
