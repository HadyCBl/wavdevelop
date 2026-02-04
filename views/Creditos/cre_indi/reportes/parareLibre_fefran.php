<?php
header('Content-Type: application/json');
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
include __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy  = date("Y-m-d");

// Variables Iniciales enviadas (ahora se incluye también 'archivo')
$datos   = $_POST["datosval"];
$inputs  = $datos[0];
$archivo = $datos[3];

// Se utiliza el dato del arreglo $archivo para la consulta (similar al ejemplo PDF)
$xtra = $archivo[0];

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
// Establecer estilo base: Bahnschrift Light Condensed, 12 pt, espaciado reducido para compactar
// ------------------------------------------------------------------------
$phpWord->setDefaultParagraphStyle([
    'alignment' => Jc::BOTH,
    'spacing'   => 200,  // Se reduce el espaciado de 200 a 120
]);

// Definición de estilos usando la fuente Bahnschrift Light Condensed
$normalStyle     = ['name' => 'Bahnschrift Light Condensed', 'size' => 11];
$boldStyle       = ['bold' => true, 'name' => 'Bahnschrift Light Condensed', 'size' => 10];
$centerStyle     = ['alignment' => Jc::CENTER];
$justifiedStyle  = ['alignment' => Jc::BOTH];
$justifiedNormal = array_merge($normalStyle, $justifiedStyle);
$justifiedBold   = array_merge($boldStyle, $justifiedStyle);
$headerStyle     = ['name' => 'Bahnschrift Light Condensed', 'size' => 10];

// ------------------------------------------------------------------------
// Encabezado: Logo a la izquierda y número de página a la derecha
// ------------------------------------------------------------------------
$header = $section->addHeader();

// Crear tabla de dos columnas en el encabezado
$headerTable = $header->addTable(['alignment' => Jc::BOTH, 'width' => 100 * 50]); // 100% ancho

// Fila del encabezado
$headerTable->addRow();

// Celda 1: Logo (izquierda)
$headerTable->addCell(4000)->addImage(
    __DIR__ . '/../../../../includes/img/fefran.png',
    [
        'width'     => 90,  // Puedes ajustar según preferencia
        'height'    => 80,
        'alignment' => Jc::LEFT
    ]
);

// Celda 2: Número de página (derecha)
$headerTable->addCell(8000)->addPreserveText(
    '{PAGE} de {NUMPAGES} hojas',
    $headerStyle,
    ['alignment' => Jc::RIGHT]
);


// --------------------------------------------------------------------------------
// Consulta SQL para extraer datos dinámicos
// --------------------------------------------------------------------------------
$strquery = "SELECT 
                    cm.NCapDes AS ncapdes,
                    ppg.ncapita AS couta,
                    ppg.nintere AS intere,
                    cl.short_name AS nombrecli,
                    cl.idcod_cliente AS codcli,
                    cl.no_identifica AS no_identifica,
                    cl.date_birth AS fecha_nacimiento,         -- ✅ Añadido
                    cl.estado_civil AS estado_civil,             -- ✅ Añadido
                    cl.nacionalidad AS nacionalidad,             -- ✅ Añadido  
                    (ppg.ncapita + ppg.nintere) AS suma_cuota_intere,
                    ag.cod_agenc AS codagencia,
                    cm.Cestado,
                    cm.NIntApro AS nintapro,
                    cm.CCODPRD AS codprod,
                    cm.CCODCTA AS ccodcta,
                    cm.MonSug AS monsug,
                    cm.NIntApro AS interes,
                    cm.DFecDsbls AS fecdesembolso,
                    cm.noPeriodo AS cuotas,
                    ((cm.MonSug) - (SELECT IFNULL(SUM(ck.KP), 0)
                                   FROM CREDKAR ck 
                                   WHERE ck.CESTADO != 'X' 
                                     AND ck.CTIPPAG = 'P' 
                                     AND ck.CCODCTA = cm.CCODCTA)) AS saldocap,
                    '-' AS municipio,
                    '-' AS departamento,
                    CONCAT(usu.nombre, ' ', usu.apellido) AS nomanal
             FROM cremcre_meta cm
             INNER JOIN tb_cliente cl ON cl.idcod_cliente = cm.CodCli
             INNER JOIN tb_agencia ag ON ag.cod_agenc = cm.CODAgencia
             INNER JOIN cre_productos prod ON prod.id = cm.CCODPRD
             INNER JOIN tb_usuario usu ON usu.id_usu = cm.CodAnal
             INNER JOIN Cre_ppg ppg ON ppg.ccodcta = cm.CCODCTA
             WHERE cm.TipoEnti = 'INDI' 
               AND cm.Cestado = 'F' 
               AND cm.CCODCTA = ?
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
// --------------------------------------------------------------------------------
// Consulta SQL para extraer datos dinámicos Garantias 
// --------------------------------------------------------------------------------
$strqueryGarantia = "SELECT
    cg.idGarantia as idGarantia,
    cg.idCliente as idClientegarantia,
    cg.idTipoGa as idTipoGargantia,
    cg.direccion as diregarantia,
    cg.muni as municipiogarantia,
    cg.depa as departamentogarantia,
    cg.descripcionGarantia as descripciongarantia,
    cg.valorComercial   as valorComercial,
    cg.fechaCreacion as fechaCreacion,
    tg.TiposGarantia   as tipogarantia,
    cli.idcod_cliente   as codcligarantia,
    cli.short_name as nombrecligarantia,
    cli.estado_civil as estado_civilgarantia,
    cli.date_birth as fecha_nacimientogarantia,
    cli.Direccion as direcciongarantia,
    cli.no_identifica as no_identificagarantia
FROM cli_garantia cg
INNER JOIN $db_name_general.tb_tiposgarantia tg
    ON cg.idTipoGa = tg.id_TiposGarantia
INNER JOIN tb_cliente cli
    ON cg.descripcionGarantia = cli.idcod_cliente
WHERE cg.estado = 1
  AND cg.idTipoGa = 1
  AND cg.idCliente = (
      SELECT CodCli 
      FROM cremcre_meta 
      WHERE CCODCTA = ?
  );";
try {
    // Abrir conexión a la base de datos
    $database->openConnection();

    // Ejecutar la consulta utilizando $strqueryGarantia y pasando el parámetro correspondiente (por ejemplo, $xtra)
    $resultGarantia = $database->getAllResults($strqueryGarantia, [$xtra]);

    // Verificar si se obtuvo algún resultado
    if (empty($resultGarantia)) {
        throw new Exception("La consulta de garantía no devolvió resultados.");
    }

    // Establecer el estado o continuar con la lógica, por ejemplo:
    $status = 1;
} catch (Exception $e) {
    // En caso de error, enviar un JSON con el mensaje del error
    echo json_encode(['status' => 0, 'mensaje' => $e->getMessage()]);
    exit;
} finally {
    // Cerrar la conexión a la base de datos
    $database->closeConnection();
}




// Variables extraídas de la consulta del cliente
$nombrecli    = $result[0]['nombrecli'] ?? '';
    $codcli       = $result[0]['codcli'] ?? '';
    $no_identifica = $result[0]['no_identifica'] ?? '';
    $codagencia   = $result[0]['codagencia'] ?? '';
    $ccodcta      = $result[0]['ccodcta'] ?? '';
    $monsug       = $result[0]['monsug'] ?? '';
    $interes      = $result[0]['interes'] ?? '';
    $fecdesembolso = $result[0]['fecdesembolso'] ?? '';
    $cuotas       = $result[0]['cuotas'] ?? '';
    $saldocap     = $result[0]['saldocap'] ?? '';
    $municipio    = $result[0]['municipio'] ?? '';
    $departamento = $result[0]['departamento'] ?? '';
    $nomanal      = $result[0]['nomanal'] ?? '';
    $NcapDes      = $result[0]['ncapdes'] ?? '';
    $codprod      = $result[0]['codprod'] ?? '';
    $edad         = $result[0]['fecha_nacimiento'] ?? ''; // ✅ Añadido
    $estado_civil = $result[0]['estado_civil'] ?? ''; // ✅ Añadido
    $nacionalidad = $result[0]['nacionalidad'] ?? ''; // ✅ Añadido
    $interes      = $result[0]['interes'] ?? ''; // ✅ Añadido
    $municipio    = $result[0]['municipio'] ?? ''; // ✅ Añadido
$summa_cuota_interes     = $result[0]['suma_cuota_intere'] ?? ''; // ✅ Añadido
// Convertir la fecha a letras
$fechaReporte = fecha_letras($hoy);

$totcuotaLetras = convertir_a_letras2($summa_cuota_interes);

function edad ($fecha_nacimiento) {
    $hoy = new DateTime();
    $nacimiento = new DateTime($fecha_nacimiento);
    $edad = $hoy->diff($nacimiento);
    return $edad->y;
}
function edad_letras($fecha_nacimiento) {
    $edad = edad($fecha_nacimiento);
    $letras = convertir_a_letras2($edad);
    return $letras;
}
$edadL = edad_letras($edad);
$no_identificaLetra= dpialetras2($no_identifica);

$ncapdesL= convertir_a_letras2($NcapDes);

//fecha extra
function fechaextra($hoy){
    return date("Y-m-d", strtotime("+3 months", strtotime($hoy)));
}
$fechaextra = fechaextra($hoy);
$fechaextraLetras = convertir_a_letras2($fechaextra);

//funcciones de Garantia individual 
// Variables extraídas de la consulta de garantía
// Extracción de las variables según los alias definidos en la consulta
$idGarantia                = $resultGarantia[0]['idGarantia']                ?? '';
$idClientegarantia         = $resultGarantia[0]['idClientegarantia']         ?? '';
$idTipoGargantia           = $resultGarantia[0]['idTipoGargantia']           ?? '';
$diregarantia              = $resultGarantia[0]['diregarantia']              ?? '';
$municipiogarantia         = $resultGarantia[0]['municipiogarantia']         ?? '';
$departamentogarantia      = $resultGarantia[0]['departamentogarantia']      ?? '';
$descripciongarantia       = $resultGarantia[0]['descripciongarantia']       ?? '';
$valorComercial            = $resultGarantia[0]['valorComercial']            ?? '';
$fechaCreacion             = $resultGarantia[0]['fechaCreacion']             ?? '';
$tipogarantia              = $resultGarantia[0]['tipogarantia']              ?? '';
$codcligarantia            = $resultGarantia[0]['codcligarantia']            ?? '';
$nombrecligarantia         = $resultGarantia[0]['nombrecligarantia']         ?? '';
$estado_civilgarantia      = $resultGarantia[0]['estado_civilgarantia']      ?? '';
$fecha_nacimientogarantia  = $resultGarantia[0]['fecha_nacimientogarantia']  ?? '';
// Ten en cuenta: el alias "direcciongarantia" se repite; aquí se toma el valor de cli.Direccion según se definió en la consulta.
$direcciongarantia         = $resultGarantia[0]['direcciongarantia']         ?? '';
$no_identificagarantia     = $resultGarantia[0]['no_identificagarantia']     ?? '';

// Por ejemplo, si deseas convertir fechas o números a letras, puedes seguir utilizando tus funciones existentes:
$fechaReporteGarantia = fecha_letras($hoy);

// Funciones ejemplo (asegúrate de tenerlas definidas en tu código)
function edad2($fecha_nacimientogarantia) {
    $hoy = new DateTime();
    $nacimiento = new DateTime($fecha_nacimientogarantia);
    $edad = $hoy->diff($nacimiento);
    return $edad->y;
}

function edad_letras2($fecha_nacimientogarantia) {
    $edad = edad($fecha_nacimientogarantia);
    $letras = convertir_a_letras2($edad);
    return $letras;
}

$edadGarantia       = $fecha_nacimientogarantia; // en este caso, la fecha de nacimiento, para calcular la edad si es necesario
$edadLGarantia      = edad_letras($edadGarantia);
$no_identificaLGarantia = dpialetras2($no_identificagarantia); // Suponiendo que dpialetras2() está definida

// Función para calcular una fecha extra (por ejemplo, 3 meses más adelante)
function fechaextra2($fecha) {
    return date("Y-m-d", strtotime("+3 months", strtotime($fecha)));
}

$fechaExtraGarantia      = fechaextra($hoy);
$fechaExtraLetrasGarantia= convertir_a_letras2($fechaExtraGarantia);

// --------------------------------------------------------------------------------
// Generación del Reporte con PHPWord
// --------------------------------------------------------------------------------

// ==================== PÁGINA 1 ====================
$textrunTitulo = $section->addTextRun(['alignment' => Jc::CENTER]);
$textrunTitulo->addText("PAGARÉ\n", [
    'name' => 'Bahnschrift Light Condensed',
    'size' => 12,
    'bold' => true,
]);


$section->addText("En el municipio de Nebaj, departamento de Quiché, a $fechaReporte, $nombrecli, de $edadL años de edad, $estado_civil, Guatemalteco y comerciante de este domicilio, me identifico con mi documento personal de identificación con código único de identificación número $no_identificaLetra ($no_identifica), extendido por el Registro Nacional de las Personas de la República de Guatemala, quien en lo sucesivo del presente pagaré se me denominará indistintamente como la PARTE DEUDORA. Por otra parte, $nombrecligarantia, de $edadLGarantia, $estado_civilgarantia, guatemalteca, comerciante de este domicilio, me identifico con mi documento personal de identificación con código único de identificación número $no_identificaLGarantia ($no_identificagarantia), extendido por el Registro Nacional de las Personas de la República de Guatemala, quien en lo sucesivo del presente pagaré se me denominará indistintamente como la PARTE FIADORA. Por medio del presente pagaré, PROMETO PAGAR EN FORMA INCONDICIONAL A LA ORDEN DE IN Fefran, SOCIEDAD ANÓNIMA, entidad constituida de conformidad con la legislación guatemalteca y en adelante referida simple e indistintamente como la PARTE ACREEDORA, la cantidad $ncapdesL (Q $NcapDes), cantidad que he recibido en plena satisfacción y para el efecto deberé cumplir con las obligaciones y condiciones que seguidamente se expresan.", $justifiedNormal
);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("INTERÉS: ", $justifiedBold);
$textrun->addText("La suma de dinero que por este título se obliga a pagar la parte deudora, siendo que, en caso de incumplimiento por pagar en la forma establecida en este pagaré, la parte deudora reconocerá pagarle a la parte acreedora el cinco por ciento ($interes%) de interés SEMANAL sobre el saldo adeudado, en concepto de mora hasta el día en que se haga efectivo el pago. ", $justifiedNormal);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("PLAZO: ", $justifiedBold);
$textrun->addText("El plazo del presente pagaré es de 3 (MESES) contado a partir del $fechaReporte por lo que vencerá el día $fechaextraLetras , fecha en que deberá pagar en su totalidad el monto referido en el pagaré, así como los intereses que se generen.", $justifiedNormal);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("FORMA DE PAGO: ", $justifiedBold);
$textrun->addText(
    " Las partes acuerdan que la cantidad adeudada se pagará mediante Doce pagos, siendo cada amortización de $totcuotaLetras (Q $summa_cuota_interes), todos de manera semanal y consecutiva, hasta completar un total de $ncapdesL (Q $NcapDes). Los pagos se efectuarán mediante depósitos a la cuenta bancaria de la PARTE ACREEDORA, y el cumplimiento se realizará sin necesidad de protesto ni requerimiento.",
    $justifiedNormal
);
$section->addTextBreak();

// Separar la primera página con un único salto de página


// ==================== PÁGINA 2 ====================
$textrun = $section->addTextRun();
$textrun->addText("DESTINO: ", $justifiedBold);
$textrun->addText("El dinero que por este pagaré se entrega a la PARTE DEUDORA será destinado única y exclusivamente para COMERCIO; quedando prohibido un uso alternativo. El incumplimiento de esta condición faculta a la PARTE ACREEDORA para declarar vencido el crédito y demandar a la PARTE DEUDORA.", $justifiedNormal);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("FORMA DE CUMPLIMIENTO: ", $justifiedBold);
$textrun->addText("El pago se realizará conforme a lo indicado, obligándose la PARTE DEUDORA y la PARTE FIADORA a cumplir con sus obligaciones mediante depósitos a la cuenta bancaria de la PARTE ACREEDORA.", $justifiedNormal);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("INCUMPLIMIENTO DE PAGO: ", $justifiedBold);
$textrun->addText("Si la PARTE DEUDORA no efectúa dos pagos consecutivos, la PARTE ACREEDORA podrá declarar vencido el plazo y exigir el cumplimiento de la obligación.", $justifiedNormal);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("CESIÓN DE DERECHO: ", $justifiedBold);
$textrun->addText("El presente pagaré no es transmitible ni negociable por endoso.", $justifiedNormal);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("EFECTOS PROCESALES: ", $justifiedBold);
$textrun->addText("La parte deudora y parte fiadora conforme a este pagaré renuncian expresamente al fuero de cualquier domicilio que pudiera corresponderles y se somete a los tribunales del departamento de Guatemala, república de Guatemala, señalando la parte deudora y parte fiadora desde ahora como lugar para recibir notificaciones, citaciones y emplazamientos, en su lugar de residencia ubicada en: Cantón la Laguna, zona (, municipio de Nebaj, departamento de Quiche obligándose a comunicar por escrito cualquier cambio que el hiciere, en el entendido que ha falta de tal aviso, se tendrán por falta válidas y bien hechas las citaciones, notificaciones y emplazamientos que se efectuaren en el lugar señalado garantizando la presente obligación con sus bienes presentes y futuros.", $justifiedNormal);
$section->addTextBreak();

$textrun = $section->addTextRun();
$textrun->addText("COSTAS Y GASTOS JUDICIALES: ", $justifiedBold);
$textrun->addText("Todos los gastos y costas derivados del cobro o ejecución del presente título correrán a cuenta de la PARTE DEUDORA. La PARTE FIADORA declara constituirse como fiadora, solidaria y mancomunada de la PARTE DEUDORA por la obligación aquí contraída.", $justifiedNormal);
$section->addTextBreak();


// ------------------------------------------------------------------------
// PÁGINA 3
// ------------------------------------------------------------------------


$textrun = $section->addTextRun();
$textrun->addText("ACEPTACIÓN: ", $justifiedBold);
$textrun->addText(
    "El presente pagaré tiene carácter de título ejecutivo y en tal concepto, la PARTE DEUDORA y PARTE FIADORA declaran aceptar como buenas y exactas las cuentas que formule la PARTE ACREEDORA y como líquido, válido, exigible y de Plazo vencido, el saldo! que la PARTE ACREEDORA demande. La falta de pago en el plazo y forma acordados dará por vencido el presente título y su tenedor podrá procesar al ejercicio de la acción judicial de ley. Los derechos y obligaciones que emanen del presente pagaré se rigen en lo que no esté expresamente consignado en el mismo, por las normas establecidas en el código de comercio de Guatemala, decreto dos guion setenta (2-70) del congreso de la República de Guatemala. Leído lo escrito y enterados de contenido, objeto, validez y demás efectos legales, lo ratificamos, aceptamos y firmamos. La PARTE DEUDORA Y PARTE FIADORA de manera voluntaria dejan impresa su huella digital del dedo pulgar de la mano derecha.",
    $justifiedNormal
);



// Sección de firmas con cuadro para huella digital (alineada a la izquierda)
// Usamos una tabla para organizar cada firma y un recuadro para la huella digital.
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

// Nota final (centrada)
$section->addTextBreak(2);
$section->addText("Nota: Se recuerda que la firma del representante de la empresa es imprescindible para la validación de este pagaré, junto con las firmas del deudor y el fiador.", $normalStyle, $centerStyle);

// ------------------------------------------------------------------------
// Generación del documento Word
// ------------------------------------------------------------------------s
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
