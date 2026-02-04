<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
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
include __DIR__ . '/../../../../src/funcphp/func_gen.php'; // Aquí se incluye la función fecha_letras()
require_once __DIR__ . '/../../../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");

// Variables que vienen del formulario
$datos = $_POST["datosval"];
$inputs = $datos[0];

// Para evitar el error de índice undefined, si no se envía "xtra" se asigna un valor default (puedes ajustarlo)
$xtra = isset($_POST["xtra"]) ? $_POST["xtra"] : '';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

$phpWord = new PhpWord();

// ------------------------------------------------------------------------
// Configuración del papel para tamaño oficio (8.5 x 13 pulgadas, márgenes de 1 pulgada)
// ------------------------------------------------------------------------
$section = $phpWord->addSection([
    'pageSizeW'    => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5),
    'pageSizeH'    => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(13),
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(1),
    'headerHeight' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(0.3)
]);

// ------------------------------------------------------------------------
// Establecer estilo base: Bahnschrift Light Condensed, 12 pt, doble espacio
// ------------------------------------------------------------------------
$phpWord->setDefaultParagraphStyle([
    'alignment' => Jc::BOTH,
    'spacing'   => 240
]);

// Definición de estilos usando la fuente Bahnschrift Light Condensed
$normalStyle     = ['name' => 'Bahnschrift Light Condensed', 'size' => 12];
$boldStyle       = ['bold' => true, 'name' => 'Bahnschrift Light Condensed', 'size' => 12];
$centerStyle     = ['alignment' => Jc::CENTER];
$justifiedStyle  = ['alignment' => Jc::BOTH];
$justifiedNormal = array_merge($normalStyle, $justifiedStyle);
$justifiedBold   = array_merge($boldStyle, $justifiedStyle);
$headerStyle     = ['name' => 'Bahnschrift Light Condensed', 'size' => 10];

// ------------------------------------------------------------------------
// Encabezado: Se muestra el número de página
// ------------------------------------------------------------------------
$header = $section->addHeader();
$header->addPreserveText('{PAGE} de {NUMPAGES} hojas', $headerStyle, [
    'alignment'   => Jc::RIGHT,
    'spaceBefore' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2),
    'spaceAfter'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1)
]);

// --------------------------------------------------------------------------------
// Consulta SQL para extraer datos dinámicos según el crédito
// --------------------------------------------------------------------------------
$strquery = "SELECT cl.short_name AS nombrecli,
                    cl.idcod_cliente AS codcli,
                    ag.cod_agenc AS codagencia,
                    cm.Cestado,
                    cm.CCODPRD AS codprod,
                    cm.CCODCTA AS ccodcta,
                    cm.MonSug AS monsug,
                    cm.NIntApro AS interes,
                    cm.DFecDsbls AS fecdesembolso,
                    cm.noPeriodo AS cuotas,
                    ((cm.MonSug)-(SELECT IFNULL(SUM(ck.KP),0)
                                  FROM CREDKAR ck 
                                  WHERE ck.CESTADO!='X' AND ck.CTIPPAG='P' AND ck.CCODCTA=cm.CCODCTA)) AS saldocap,
                    IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_reside LIMIT 1),'-') AS municipio,
                    IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_reside LIMIT 1),'-') AS departamento,
                    concat(usu.nombre, ' ', usu.apellido) as nomanal
             FROM cremcre_meta cm
             INNER JOIN tb_cliente cl ON cl.idcod_cliente = cm.CodCli
             INNER JOIN tb_agencia ag ON ag.cod_agenc = cm.CODAgencia
             INNER JOIN cre_productos prod ON prod.id = cm.CCODPRD
             INNER JOIN tb_usuario usu ON usu.id_usu = cm.CodAnal
             WHERE cm.TipoEnti='INDI' AND cm.Cestado='F' AND cm.CCODCTA=?
             GROUP BY cm.CCODCTA";
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$xtra]);
    if (empty($result)) {
        throw new Exception("Cuenta de Crédito no existe");
    }
    $status = 1;
} catch (Exception $e) {
    echo json_encode(['status' => 0, 'mensaje' => $e->getMessage()]);
    exit;
} finally {
    $database->closeConnection();
}

// Variables dinámicas extraídas de la consulta
$nombrecli    = $result[0]['nombrecli'] ?? '';
$codcli       = $result[0]['codcli'] ?? '';
$codagencia   = $result[0]['codagencia'] ?? '';
$ccodcta      = $result[0]['ccodcta'] ?? '';
$monsug       = $result[0]['monsug'] ?? '';
$interes      = $result[0]['interes'] ?? '';
$fecdesembolso= $result[0]['fecdesembolso'] ?? '';
$cuotas       = $result[0]['cuotas'] ?? '';
$saldocap     = $result[0]['saldocap'] ?? '';
$municipio    = $result[0]['municipio'] ?? 'Nebaj';
$departamento = $result[0]['departamento'] ?? 'El Quiché';
$nomanal      = $result[0]['nomanal'] ?? '';

// Convertir la fecha (por ejemplo, la fecha actual) a letras usando la función definida externamente
$fechaReporte = fecha_letras($hoy);

// --------------------------------------------------------------------------------
// GENERACIÓN DEL REPORTE CON PHPWORD
// --------------------------------------------------------------------------------

// PÁGINA 1
$section->addTextBreak(1);
$section->addText(
    "En el municipio de $municipio, departamento de $departamento, a $fechaReporte, $nombrecli (Código Cliente: $codcli), quien se identifica con su documento correspondiente, se declara como PARTE DEUDORA. Por otra parte, se asume que existe un fiador (datos fijos) que respalda la operación. Por medio del presente pagaré, PROMETO PAGAR EN FORMA INCONDICIONAL A LA ORDEN DE IN KUMDOL BUSSINES, SOCIEDAD ANÓNIMA, entidad constituida de conformidad con la legislación guatemalteca y en adelante referida como la PARTE ACREEDORA, la cantidad TREINTA Y TRES MIL QUETZALES (Q33,000.00), siendo el monto sugerido de desembolso ($monsug) y con un saldo de capital de ($saldocap).",
    $justifiedNormal
);
$section->addTextBreak();

$section->addText(
    "INTERÉS: La suma de dinero que por este título se obliga a pagar la PARTE DEUDORA, en caso de incumplimiento, incurrirá en un interés SEMANAL del 3% sobre el saldo adeudado. PLAZO: El plazo del presente pagaré es de 3 (MESES), contado a partir del veinticinco de febrero del año dos mil veinticinco, venciendo el día veinte de mayo del mismo año.",
    $justifiedNormal
);
$section->addTextBreak();

$section->addText(
    "FORMA DE PAGO: Las partes acuerdan que la cantidad adeudada se pagará mediante Doce pagos, siendo cada amortización de DOS MIL SETECIENTOS CINCUENTA QUETZALES (Q2,750.00), todos de manera semanal y consecutiva, hasta completar un total de TREINTA Y TRES MIL QUETZALES (Q33,000.00). Los pagos se efectuarán mediante depósitos a la cuenta bancaria de la PARTE ACREEDORA, y el cumplimiento se realizará sin necesidad de protesto ni requerimiento.",
    $justifiedNormal
);
$section->addTextBreak();
$section->addPageBreak();

// PÁGINA 2
$section->addTextBreak(1);
$section->addText("DESTINO: ", $justifiedBold);
$section->addText(
    "El dinero que por este pagaré se entrega a la PARTE DEUDORA será destinado única y exclusivamente para COMERCIO; quedando prohibido un uso alternativo. El incumplimiento de esta condición faculta a la PARTE ACREEDORA para declarar vencido el crédito y demandar a la PARTE DEUDORA.",
    $justifiedNormal
);
$section->addTextBreak();

$section->addText("FORMA DE CUMPLIMIENTO: ", $justifiedBold);
$section->addText(
    "El pago se realizará conforme a lo indicado, obligándose la PARTE DEUDORA y la PARTE FIADORA a cumplir con sus obligaciones mediante depósitos a la cuenta bancaria de la PARTE ACREEDORA.",
    $justifiedNormal
);
$section->addTextBreak();

$section->addText("INCUMPLIMIENTO DE PAGO: ", $justifiedBold);
$section->addText(
    "Si la PARTE DEUDORA no efectúa dos pagos consecutivos, la PARTE ACREEDORA podrá declarar vencido el plazo y exigir el cumplimiento de la obligación.",
    $justifiedNormal
);
$section->addTextBreak();

$section->addText("CESIÓN DE DERECHO: ", $justifiedBold);
$section->addText(
    "El presente pagaré no es transmitible ni negociable por endoso.",
    $justifiedNormal
);
$section->addTextBreak();

$section->addText("EFECTOS PROCESALES: ", $justifiedBold);
$section->addText(
    "La PARTE DEUDORA y la PARTE FIADORA renuncian expresamente al fuero de cualquier domicilio y se someten a los tribunales del departamento de Guatemala, estableciendo como lugar de notificaciones el de su residencia: Cantón la Laguna, municipio de Nebaj, departamento de Quiché.",
    $justifiedNormal
);
$section->addTextBreak();

$section->addText("COSTAS Y GASTOS JUDICIALES: ", $justifiedBold);
$section->addText(
    "Todos los gastos y costas derivados del cobro o ejecución del presente título correrán a cuenta de la PARTE DEUDORA. La PARTE FIADORA declara constituirse como fiadora, solidaria y mancomunada de la PARTE DEUDORA por la obligación aquí contraída.",
    $justifiedNormal
);
$section->addTextBreak();
$section->addPageBreak();

// PÁGINA 3
$section->addTextBreak(1);
$section->addText("ACEPTACIÓN: ", $justifiedBold);
$section->addText(
    "El presente pagaré tiene carácter de título ejecutivo y, en tal concepto, la PARTE DEUDORA y la PARTE FIADORA declaran aceptar como correctas las condiciones establecidas para el cobro del saldo adeudado. La falta de pago en el plazo acordado dará por vencido el presente título y facultará al tenedor para iniciar las acciones judiciales correspondientes. Los derechos y obligaciones derivados se regirán por lo estipulado en el Código de Comercio de Guatemala, decreto 2-70 del Congreso de la República de Guatemala. Leído lo escrito, lo ratificamos, aceptamos y firmamos, dejando impresa la huella digital del dedo pulgar de la mano derecha.",
    $justifiedNormal
);
$section->addPageBreak();

// PÁGINA 4: Sección de firmas y nota
$tableStyle = ['borderSize' => 6, 'cellMargin' => 100];
$table = $section->addTable(['alignment' => Jc::LEFT], $tableStyle);

// Firma de la PARTE DEUDORA
$table->addRow();
$cell1 = $table->addCell(6000);
$cell1->addText("Firma deudor:", $normalStyle);
$cell1->addText("F.__________________________", $normalStyle);
$cell2 = $table->addCell(2000, ['valign' => 'center', 'borderSize' => 6]);
$cell2->addText("", $normalStyle);

// Firma de la PARTE FIADORA
$table->addRow();
$cell1 = $table->addCell(6000);
$cell1->addText("Firma fiador:", $normalStyle);
$cell1->addText("F.__________________________", $normalStyle);
$cell2 = $table->addCell(2000, ['valign' => 'center', 'borderSize' => 6]);
$cell2->addText("", $normalStyle);

// Firma del REPRESENTANTE DE LA EMPRESA
$table->addRow();
$cell1 = $table->addCell(6000);
$cell1->addText("Representante de la empresa:", $normalStyle);
$cell1->addText("F.__________________________", $normalStyle);

$section->addTextBreak(2);
$section->addText(
    "Nota: Se recuerda que la firma del representante de la empresa es imprescindible para la validación de este pagaré, junto con las firmas del deudor y el fiador.",
    $normalStyle,
    $centerStyle
);

// ------------------------------------------------------------------------
// Generación del documento Word
// ------------------------------------------------------------------------
ob_start();
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save("php://output");
$worddata = ob_get_contents();
ob_end_clean();

$opResult = [
    'status'    => 1,
    'mensaje'   => 'Reporte generado correctamente',
    'namefile'  => "Pagare",
    'tipo'      => "vnd.ms-word",
    'extension' => "docx",
    'download'  => 1,
    'data'      => "data:application/vnd.ms-word;base64," . base64_encode($worddata)
];

echo json_encode($opResult);
exit;
?>
