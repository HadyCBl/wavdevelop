<?php
// header('Content-Type: application/json');
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
    exit;
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    exit;
}

include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];

// Se utiliza el dato del arreglo $archivo para la consulta (similar al ejemplo PDF)
$xtra = $archivo[0];

use Luecano\NumeroALetras\NumeroALetras;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

$strquery = "SELECT cm.NCapDes AS ncapdes,
                    cl.short_name AS nombrecli,
                    cl.idcod_cliente AS codcli,
                    cl.no_identifica AS no_identifica,
                    cl.date_birth AS fecha_nacimiento,         
                    cl.estado_civil AS estado_civil,             
                    cl.nacionalidad AS nacionalidad,    
                    cl.Direccion AS direccion,          
                    ag.cod_agenc AS codagencia,
                    cm.Cestado,
                    cm.NIntApro AS nintapro,
                    cm.CCODPRD AS codprod,
                    cm.CCODCTA AS ccodcta,
                    cm.MonSug AS monsug,
                    cm.NIntApro AS interes,
                    cm.DFecDsbls AS fecdesembolso,
                    cm.noPeriodo AS cuotas,
                    CONCAT(usu.nombre, ' ', usu.apellido) AS nomanal,
                    cl.profesion,cl.genero, cm.noPeriodo,per.nombre formaPeriodo, dest.DestinoCredito destino, gd.nombre departamento, gm.nombre municipio,cm.Dictamen
             FROM cremcre_meta cm
             INNER JOIN tb_cliente cl ON cl.idcod_cliente = cm.CodCli
             INNER JOIN tb_agencia ag ON ag.cod_agenc = cm.CODAgencia
             INNER JOIN cre_productos prod ON prod.id = cm.CCODPRD
             INNER JOIN tb_usuario usu ON usu.id_usu = cm.CodAnal
             LEFT JOIN $db_name_general.tb_periodo per ON cm.NtipPerC=per.periodo
             LEFT JOIN $db_name_general.tb_destinocredito dest ON cm.Cdescre=dest.id_DestinoCredito
             LEFT JOIN tb_departamentos gd ON cl.depa_reside = gd.id
             LEFT JOIN tb_municipios gm ON cl.id_muni_reside = gm.id
             WHERE cm.TipoEnti = 'INDI' 
               AND cm.Cestado = 'F' 
               AND cm.CCODCTA = ?
             GROUP BY cm.CCODCTA";

$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$xtra]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("Cuenta de Crédito no existe o no ha sido desembolsada");
    }

    $fechaVencimiento = $database->getSingleResult("SELECT MAX(dfecven) fechaVence FROM Cre_ppg WHERE ccodcta = ?", [$xtra]);
    if (empty($fechaVencimiento)) {
        $showmensaje = true;
        throw new Exception("Verificar si la cuenta tiene plan de pagos");
    }

    $cuotas = $database->selectColumns('Cre_ppg', ['ncapita', 'nintere'], 'ccodcta = ?', [$xtra], 'cnrocuo ASC LIMIT 1');
    if (empty($cuotas)) {
        $showmensaje = true;
        throw new Exception("No se encontraron cuotas para la cuenta de crédito");
    }

    // $resultGarantia = $database->getAllResults($strqueryGarantia, [$xtra]);

    // if (empty($resultGarantia)) {
    //     $showmensaje = true;
    //     throw new Exception("La consulta de garantía no devolvió resultados.");
    // }

    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

$letras = new NumeroALetras();

// Variables extraídas de la consulta del cliente
$nombrecli = $result[0]['nombrecli'] ?? '';
$codcli = $result[0]['codcli'] ?? '';
$dpiCliente = dpi_format($result[0]['no_identifica']);
$dpiClienteLetra = dpi_letra($dpiCliente, $letras);

$codagencia = $result[0]['codagencia'] ?? '';
$ccodcta = $result[0]['ccodcta'] ?? '';
$monsug = $result[0]['monsug'] ?? '';
$interes = $result[0]['interes'] ?? '';
$fecdesembolso = $result[0]['fecdesembolso'] ?? '';
$dictamen = $result[0]['Dictamen'] ?? '';
// $cuotas       = $result[0]['cuotas'] ?? '';

$nomanal = $result[0]['nomanal'] ?? '';
$montoDesembolso = $result[0]['ncapdes'];
$montoDesembolsoLetra = $letras->toMoney($montoDesembolso, 2, 'QUETZALES', 'CENTAVOS');
$codprod = $result[0]['codprod'] ?? '';
$edad = $result[0]['fecha_nacimiento'] ?? ''; // ✅ Añadido
$edadLetrasCliente = ($result[0]['fecha_nacimiento'] == 'X') ? ' ' : mb_strtolower($letras->toWords((calcular_edad($result[0]['fecha_nacimiento']))));
$estado_civil = $result[0]['estado_civil'] ?? ''; // ✅ Añadido
$nacionalidad = $result[0]['nacionalidad'] ?? ''; // ✅ Añadido




/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ SECCION DE PREPARACION +++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
// Convertir la fecha de desembolso completamente a letras (día, mes y año)
$dateStr = $result[0]['fecdesembolso'] ?? '';
$fechaDesembolsoLetras = '';
$fechaVencimientoLetras = '';

function fechaEnLetras($dateStr, $letras)
{
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return '';
    }
    try {
        $dt = new DateTime($dateStr);
        $day = (int) $dt->format('j');
        $monthNum = (int) $dt->format('n');
        $year = (int) $dt->format('Y');

        $months = [
            'enero',
            'febrero',
            'marzo',
            'abril',
            'mayo',
            'junio',
            'julio',
            'agosto',
            'septiembre',
            'octubre',
            'noviembre',
            'diciembre'
        ];

        // Convertir números a palabras usando NumeroALetras
        $diaLetra = $letras->toWords($day);
        $anioLetra = $letras->toWords($year);

        // Construir fecha en letras: "veintitrés de octubre de dos mil veinticinco"
        return mb_strtolower(trim($diaLetra)) . ' de ' . $months[$monthNum - 1] . ' de ' . mb_strtolower(trim($anioLetra));
    } catch (Exception $e) {
        // Fallback a la función existente si ocurre algún error
        return fechletras($dateStr);
    }
}

$fechaDesembolsoLetras = fechaEnLetras($dateStr, $letras);
$fechaVencimientoLetras = fechaEnLetras($fechaVencimiento['fechaVence'] ?? '', $letras);

$profesionCliente = $result[0]['profesion'] ?? '';
$generoCliente = $result[0]['genero'] ?? 'M';

// $fechaVencimientoLetras = fechletras($fechaVencimiento['fechaVence'] ?? '');
$diferenciaEnDias = dias_dif($result[0]['fecdesembolso'], $fechaVencimiento['fechaVence'] ?? '');
$diferenciaMeses = calcularPlazoEnMeses($result[0]['fecdesembolso'], $diferenciaEnDias);
$noCuotaLetra = ($letras->toWords($result[0]['noPeriodo']));

$cuotaTotal = $cuotas[0]['ncapita'] + $cuotas[0]['nintere'];

$cuotaTotalLetra = $letras->toMoney($cuotaTotal, 2, 'QUETZALES', 'CENTAVOS');

$formaPeriodo = $result[0]['formaPeriodo'] ?? 'mensual';

$destino = mb_strtoupper($result[0]['destino'] ?? 'COMERCIO');

$direccion = $result[0]['direccion'] ?? '';
$municipio = $result[0]['municipio'] ?? '';
$departamento = $result[0]['departamento'] ?? '';

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++ FIN DE SECCION DE PREPARACION ++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

// Funciones ejemplo (asegúrate de tenerlas definidas en tu código)
// function edad2($fecha_nacimientogarantia)
// {
//     $hoy = new DateTime();
//     $nacimiento = new DateTime($fecha_nacimientogarantia);
//     $edad = $hoy->diff($nacimiento);
//     return $edad->y;
// }

// function edad_letras2($fecha_nacimientogarantia)
// {
//     $edad = edad($fecha_nacimientogarantia);
//     $letras = convertir_a_letras2($edad);
//     return $letras;
// }


$phpWord = new PhpWord();

// ------------------------------------------------------------------------
// Configuración del papel para tamaño oficio (8.5 x 13 pulgadas, márgenes de 1 pulgada)
// ------------------------------------------------------------------------
$section = $phpWord->addSection([
    'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5),
    'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(13),
    'marginTop' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'marginRight' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'headerHeight' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(0.2)
]);

// ------------------------------------------------------------------------
// Establecer estilo base: Bahnschrift Light Condensed, 12 pt, espaciado reducido para compactar
// ------------------------------------------------------------------------
$phpWord->setDefaultParagraphStyle([
    'alignment' => Jc::BOTH,
    'spacing' => 200,  // Se reduce el espaciado de 200 a 120
]);

// Definición de estilos usando la fuente Bahnschrift Light Condensed
$boldStyle = ['bold' => true, 'size' => 10, 'name' => 'Bahnschrift Light Condensed'];
$normalStyle = ['size' => 10, 'name' => 'Bahnschrift Light Condensed'];
$centerStyle = ['alignment' => Jc::CENTER];
$justifiedStyle = ['alignment' => Jc::BOTH];
$justifiedNormal = array_merge($normalStyle, $justifiedStyle);
$justifiedBold = array_merge($boldStyle, $justifiedStyle);
$headerStyle = ['name' => 'Bahnschrift Light Condensed', 'size' => 9];

// ------------------------------------------------------------------------
// Encabezado: Logo a la izquierda y número de página a la derecha
// ------------------------------------------------------------------------
$header = $section->addHeader();

// Crear tabla de dos columnas en el encabezado
$headerTable = $header->addTable(['alignment' => Jc::BOTH, 'width' => 100 * 50]); // 100% ancho

// Fila del encabezado
$headerTable->addRow(500);

// Celda 1: Logo (izquierda)
$headerTable->addCell(4000)->addImage(
    __DIR__ . '/../../../../includes/img/kumool.png',
    [
        'width' => 120,  // Puedes ajustar según preferencia
        'height' => 70,
        'alignment' => Jc::LEFT
    ]
);

// Celda 2: Espacio en el centro (vacía)
$headerTable->addCell(2000);

// Celda 3: Línea gruesa (derecha) - del centro hacia la derecha
$cellLinea = $headerTable->addCell(6000, ['valign' => 'center']);

// Crear línea usando texto con guiones bajos
$cellLinea->addText(
    str_repeat('_', 50), // 50 guiones bajos para crear la línea
    [
        'name' => 'Bahnschrift Light Condensed',
        'size' => 14,
        'bold' => true,
        'color' => '000000'
    ],
    ['alignment' => Jc::LEFT]
);

// ------------------------------------------------------------------------
// PIE DE PÁGINA
// ------------------------------------------------------------------------
$footer = $section->addFooter();

// Opción 1: Pie de página simple centrado
// Pie de página con menor espacio entre líneas
$footerStyle = [
    'name' => 'Bahnschrift Light Condensed',
    'size' => 9,
    'color' => '666666'
];
$footerParagraphStyle = [
    'alignment' => Jc::CENTER,
    'spacing' => 0 // Sin espacio extra entre líneas
];

$footer->addText('Calzada 15 de Septiembre, Cantón Batzbacá', $footerStyle, $footerParagraphStyle);
$footer->addText('Nebaj-El Quiché', $footerStyle, $footerParagraphStyle);
$footer->addText('Tel. 7927-6842', $footerStyle, $footerParagraphStyle);

// ==================== PÁGINA 1 ====================
$section->addText(
    "ID: " . ($dictamen),
    [
        'name' => 'Bahnschrift Light Condensed',
        'size' => 9,
        'bold' => false,
    ],
    [
        'alignment' => Jc::RIGHT
    ]
);

$textrunTitulo = $section->addTextRun([
    'alignment' => Jc::CENTER,
    'spacing' => 0 // Reduce el espacio entre líneas
]);

$textrunTitulo->addText("PAGARÉ", [
    'name' => 'Bahnschrift Light Condensed',
    'size' => 12,
    'bold' => true,
    'underline' => 'single'
]);
$textrunTitulo2 = $section->addTextRun([
    'alignment' => Jc::CENTER,
    'spacing' => 0 // Reduce el espacio entre líneas
]);
$textrunTitulo2->addText("LIBRE DE PROTESTO", [
    'name' => 'Bahnschrift Light Condensed',
    'size' => 12,
    'bold' => true,
    'underline' => 'single'
]);

$formatTextRun = [
    'size' => 10,
    'name' => 'Bahnschrift Light Condensed',
    'spacing' => 240,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH
];

$textrun = $section->addTextRun($formatTextRun);

// Contenido del documento
$textrun->addText("En el municipio de Nebaj, departamento de El Quiché, el $fechaDesembolsoLetras, yo ", $normalStyle);
$textrun->addText("$nombrecli, ", $boldStyle);
// create explicit font styles for underline (underline is a font attribute, not a paragraph style)
$underlineFont = array_merge($normalStyle, ['underline' => 'single']);
$boldUnderline = array_merge($boldStyle, ['underline' => 'single']);

$textrun->addText("de ", $normalStyle);
$textrun->addText("$edadLetrasCliente");
$textrun->addText(" años de edad, ", $normalStyle);
$textrun->addText("$estado_civil");
$textrun->addText(", " . ($generoCliente == "F" ? "Guatemalteca" : "Guatemalteco") . " ", $normalStyle);
$textrun->addText("$profesionCliente");
$textrun->addText(" de este domicilio, me identifico con mi documento personal de identificación con código único de identificación número ", $normalStyle);
$textrun->addText("$dpiClienteLetra", $underlineFont);
$textrun->addText(" ($dpiCliente)", $underlineFont);
$textrun->addText(", extendido por el Registro Nacional de las Personas de la República de Guatemala, quien en lo sucesivo del presente pagaré se me denominará indistintamente como la ", $normalStyle);
$textrun->addText("PARTE DEUDORA. ", $boldStyle);
$textrun->addText("Por medio del presente pagaré, ", $normalStyle);
$textrun->addText("PROMETO PAGAR EN FORMA INCONDICIONAL A LA ORDEN DE IN KUMDOL BUSSINES, SOCIEDAD ANÓNIMA, ", $boldStyle);
$textrun->addText("entidad constituida de conformidad con la legislación guatemalteca y en adelante referida simple e indistintamente como la ", $normalStyle);
$textrun->addText("PARTE ACREEDORA, ", $boldStyle);
$textrun->addText("la cantidad de ", $normalStyle);
$textrun->addText("$montoDesembolsoLetra (Q $montoDesembolso), cantidad que he recibido en plena satisfacción ", $boldStyle);
$textrun->addText("y para el efecto deberé cumplir con las obligaciones y condiciones que seguidamente se expresan.", $normalStyle);

$section->addText("Este pagaré queda sujeto a las siguientes modalidades:", $normalStyle);

$textrun = $section->addTextRun();
// Use boldUnderline so the label itself is underlined
$textrun->addText("INTERÉS: ", $boldUnderline);
$textrun->addText("La suma de dinero que por este título se obliga a pagar la parte deudora, siendo que, en caso de incumplimiento por pagar en la forma establecida en este pagaré, la parte deudora reconocerá pagarle a la parte acreedora el cinco por ciento (5%) de interés SEMANAL sobre el saldo adeudado, en concepto de mora hasta el día en que se haga efectivo el pago. ", $normalStyle);

$textrun = $section->addTextRun();
// Use boldUnderline for the label
$textrun->addText("PLAZO: ", $boldUnderline);
$textrun->addText("El plazo del presente pagaré es de ", $normalStyle);
// Use underlineFont for the dynamic values that should be underlined
$textrun->addText("$diferenciaMeses (MESES) ", $underlineFont);
$textrun->addText("contado a partir del ", $normalStyle);
$textrun->addText("$fechaDesembolsoLetras ", $underlineFont);
$textrun->addText("por lo que vencerá el día ", $normalStyle);
$textrun->addText("$fechaVencimientoLetras ", $underlineFont);
$textrun->addText("fecha en que deberá pagar en su totalidad el monto referido en el pagaré, así como los intereses que se generen.", $normalStyle);
// $section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("FORMA DE PAGO: ", $boldUnderline);
$textrun->addText("Las partes acuerdan que la cantidad adeudada se pagará mediante ", $normalStyle);
$textrun->addText("$noCuotaLetra pagos", $underlineFont);
$textrun->addText(", amortizaciones de las cuales se harán por la cantidad de ", $normalStyle);
$textrun->addText("$cuotaTotalLetra (Q $cuotaTotal) ", $boldStyle);
$textrun->addText("cada amortización, todos de manera ", $normalStyle);
$textrun->addText("$formaPeriodo", $underlineFont);
$textrun->addText(" y consecutiva, pagaderas, sin necesidad de cobro o requerimiento alguno hasta completar un total de ", $normalStyle);
$textrun->addText("$montoDesembolsoLetra (Q $montoDesembolso). ", $boldStyle);
$textrun->addText(
    "monto que incluye pago a capital y gastos de cobranza. Los pagos deben realizarse mediante depósitos que la PARTE DEUDORA realizará a la cuenta bancaria de la PARTE ACREEDORA, ampliamente reconocida por este. El cumplimiento del pagaré se hará sin necesidad de protesto, cobro ni requerimiento en el lugar o modo indicado para realizar el pago.",
    $normalStyle
);
// $section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("DESTINO: ", $boldUnderline);
$textrun->addText("El dinero que por este pagaré se entrega a la PARTE DEUDORA será destinado única y exclusivamente para ", $normalStyle);
$textrun->addText("$destino, ", $boldStyle);
$textrun->addText("quedándole totalmente prohibido a la PARTE DEUDORA a darle un uso o destino diferente al acordado. El incumplimiento de la presente condición, faculta a la PARTE ACREEDORA a dar por vencido el presente crédito y a demandar a la PARTE DEUDORA, promoviendo las acciones judiciales que considere pertinentes ante los tribunales de justicia.", $normalStyle);

// $section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("FORMA DE CUMPLIMIENTO: ", $boldUnderline);
$textrun->addText("El pago deberá realizarse conforme lo anteriormente indicado y en todo caso, la PARTE DEUDORA deberá cumplir con sus obligaciones y en la forma ya establecida mediante depósitos bancarios a la cuenta de la PARTE ACREEDORA ampliamente conocida por este.", $normalStyle);
// $section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("INCUMPLIMIENTO DE PAGO: ", $boldUnderline);
$textrun->addText("Si por cualquier causa la PARTE DEUDORA no pagara dos de las amortizaciones continúas pactadas, la PARTE ACREEDORA, tendrá derecho a dar por vencido el plazo y exigir ejecutivamente el cumplimiento de la obligación.", $normalStyle);
// $section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("CESIÓN DE DERECHO: ", $boldUnderline);
$textrun->addText("El presente pagaré no es transmitible ni negociable por endoso.", $normalStyle);
// $section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("EFECTOS PROCESALES: ", $boldUnderline);
$textrun->addText("La parte deudora conforme a este pagaré renuncia expresamente al fuero de cualquier domicilio que pudiera corresponderle y se somete expresamente a los tribunales del departamento de Guatemala, república de Guatemala, señalando la parte deudora desde ahora como lugar para recibir notificaciones citaciones y emplazamientos, en su lugar de residencia ubicada en: ", $normalStyle);
$textrun->addText("$direccion, municipio de $municipio, departamento de $departamento", $underlineFont);
$textrun->addText(" obligándose a comunicar por escrito cualquier cambio que el hiciere, en el entendido que ha falta de tal aviso, se tendrán por falta válidas y bien hechas las citaciones, notificaciones y emplazamientos que se efectuaren en el lugar señalado garantizando la presente obligación con sus bienes presentes y futuros tanto personales como de su representado.", $normalStyle);

// $section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("COSTAS Y GASTOS JUDICIALES: ", $boldUnderline);
$textrun->addText("Corren por cuenta de la PARTE DEUDORA todos los gastos y costas de cobranza extrajudicial o judicial que se causen por el cobro o ejecución del presente título.", $normalStyle);
// $section->addTextBreak();


$textrun = $section->addTextRun();
$textrun->addText("ACEPTACIÓN: ", $boldUnderline);
$textrun->addText(
    "El presente pagaré tiene carácter de título ejecutivo y en tal concepto, la PARTE DEUDORA declara aceptar como buenas y exactas las cuentas que formule la PARTE ACREEDORA y como líquido, válido, exigible y de plazo vencido, el saldo que la PARTE ACREEDORA demande. La falta de pago en el plazo y forma acordados dará por vencido el presente título y su tenedor podrá procesar al ejercicio de la acción judicial de ley. Los derechos y obligaciones que emanen del presente pagaré se rigen en lo que no esté expresamente consignado en el mismo, por las normas establecidas en el código de comercio de Guatemala, decreto dos guion setenta (2-70) del congreso de la República de Guatemala. Leído lo escrito y enterados de contenido, objeto, validez y demás efectos legales, lo ratificamos, aceptamos y firmamos. La PARTE DEUDORA de manera voluntaria deja impresa su huella digital del dedo pulgar de la mano derecha.",
    $normalStyle
);

$section->addTextBreak();

// Sección de firmas con cuadro para huella digital (alineada a la izquierda)
// Usamos una tabla para organizar cada firma y un recuadro para la huella digital.
$tableStyle = ['borderSize' => 6, 'cellMargin' => 100];
$table = $section->addTable(['alignment' => Jc::LEFT], $tableStyle);
$section->addTextBreak();
// Firma de la PARTE DEUDORA
// Firma de la PARTE DEUDORA (con mayor separación entre firmas)
$table->addRow();
$cell1 = $table->addCell(6000);
$cell1->addText("Firma deudor: _____________________________________________________", $normalStyle);

$cell2 = $table->addCell(2000, ['valign' => 'center', 'borderSize' => 6]);
$cell2->addText("", $normalStyle);
$cell2->addText("", $normalStyle);

$table->addRow(1500);
$cell1 = $table->addCell(6000, ['valign' => 'bottom']);
$cell1->addText("Representante de la empresa: _____________________________________________________", $normalStyle);


ob_start();
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save("php://output");
$worddata = ob_get_contents();
ob_end_clean();

$opResult = [
    'status' => 1,
    'mensaje' => 'Reporte generado correctamente',
    'namefile' => "Pagare",
    'tipo' => "vnd.ms-word",
    'extension' => "docx",
    'download' => 1,
    'data' => "data:application/vnd.ms-word;base64," . base64_encode($worddata)
];

echo json_encode($opResult);
exit;
