<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
// include __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

include __DIR__ . '/../../../includes/BD_con/db_con.php';
include __DIR__ . '/../../../src/funcphp/fun_ppg.php';

mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use Micro\Helpers\Log;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Border;
use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];
$idCertificado = $archivo[0];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT crt.*,tip.diascalculo,tip.ccodofi,cli.short_name,IFNULL(cli.date_birth,'X') fechaNacimiento,genero,estado_civil,profesion,
            dep.nombre departamento,ifnull(mun.nombre,'') municipio,cli.aldea_reside,no_identifica dpi,IFNULL(nacionalidad,'GT') nacionalidad
            FROM `ahomcrt` crt 
            INNER JOIN tb_cliente cli on crt.ccodcli=cli.idcod_cliente
            INNER JOIN ahomtip tip on tip.ccodtip=substr(crt.codaho,7,2) 
            LEFT JOIN tb_departamentos dep on dep.id=cli.depa_reside
            LEFT JOIN tb_municipios mun on mun.id=cli.id_muni_reside
            WHERE `id_crt` = ?";

$query2 = "SELECT ben.*,par.descripcion parentesco FROM ahomben ben
            INNER JOIN tb_parentescos par ON par.id=ben.codparent
            WHERE ccodcrt=? AND codaho=? LIMIT 1;";

// Log::info($query,[$idCertificado]);

// $opResult = array('status' => 0, 'mensaje' => $query);
// echo json_encode($opResult);
// return;
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$idCertificado]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontró información del certificado");
    }
    $beneficiarios = $database->getAllResults($query2, [$result[0]['ccodcrt'], $result[0]['codaho']]);
    if (empty($beneficiarios)) {
        $showmensaje = true;
        throw new Exception("No se encontró información de los beneficiarios");
    }
    $testigos = $database->selectColumns("ahotestigos", ["nombre", "dpi", "telefono", "direccion"], "id_certificado=?", [$idCertificado]);
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

/**
 * DATOS GENERALES DEL CERTIFICADO
 */
$letras = new NumeroALetras();
// $format_monto = new NumeroALetras();

$nombreCliente = $result[0]['short_name'];
$fechaApertura = $result[0]['fec_apertura'];
$fechaNacimiento = $result[0]['fechaNacimiento'];
$edadletrasaux = ($result[0]['fechaNacimiento'] == 'X') ? ' ' : mb_strtolower($letras->toWords((calcular_edad($fechaNacimiento))));
$genero = $result[0]['genero'];
$estadoCivil = mb_strtolower($result[0]['estado_civil']);
$paisOrigen = ($result[0]['nacionalidad'] == 'GT') ? 'Guatemalteco' : 'Extranjero';
$profesion = $result[0]['profesion'] ?? ' ';
$departamentoDomicilio = ucwords(mb_strtolower($result[0]['departamento']));
$municipioDomicilio = ucwords(mb_strtolower($result[0]['municipio']));
$aldeaDomicilio = $result[0]['aldea_reside'];
$documentoIdentificacion = 'Documento Personal de Identificación (DPI)';
$cui = dpi_format($result[0]['dpi']);
$dpiletras = dpi_letra($cui, $letras);
$fechaLetras = fechletras($fechaApertura);
$montoApertura = $result[0]['montoapr'];
$montoletra = $letras->toMoney($montoApertura, 2, 'QUETZALES', 'CENTAVOS');
$plazoEnDias = $result[0]['plazo'];
$plazoEnMeses = calcularPlazoEnMeses($fechaApertura, $plazoEnDias);
$plazoLetras = mb_strtoupper($letras->toWords($plazoEnMeses));
$fechaVencimiento = $result[0]['fec_ven'];
$fechaVencimientoLetras = fechletras($fechaVencimiento);
$tasaInteres = $result[0]['interes'];
$tasaInteresLetras = $letras->toWords($tasaInteres);

/**
 * SECCION DE BENEFICIARIOS
 */
$nombreBeneficiario = strtoupper($beneficiarios[0]['nombre']);
$parentescoBeneficiario = $beneficiarios[0]['parentesco'];
$dpiBeneficiario = dpi_format($beneficiarios[0]['dpi']);
$dpiLetrasBeneficiario = dpi_letra($dpiBeneficiario, $letras);
$telefonoBeneficiario = $beneficiarios[0]['telefono'];
$direccionBeneficiario = $beneficiarios[0]['direccion'];

$preName = ($genero == 'F') ? 'de la señora' : 'del señor';

/**
 * FORMATO PARA EL DOCUMENTO
 */

$phpWord = new PhpWord();

// Configuracion del tamaño del papel a A4
$section = $phpWord->addSection([
    'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(8.5), // Ancho en pulgadas convertido a Twips
    'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::inchToTwip(14), // Altura en pulgadas convertida a Twips
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // Ajusta el margen superior
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // Ajusta el margen derecho
    // 'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // Ajusta el margen inferior
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(5),  // Ajusta el margen izquierdo
    'headerHeight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(3) // Altura del encabezado
]);

// Estilos
$fontStyle = ['name' => 'Arial', 'size' => 11];
$fontStyletext = ['name' => 'Arial', 'size' => 11];
$cellStyle = ['valign' => 'center', 'align' => 'center',];
$borderStyle = ['borderSize' => 1, 'borderColor' => '000000'];
$styletextos = [
    'bold' => true,
    'size' => 11,
    'name' => 'Arial',
];
$fontStyletextbold = [
    'bold' => true,
    'size' => 11,
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

//++++++++++
// Estilos de texto
$boldStyle = ['bold' => true];
$normalStyle = ['size' => 11];

$header = $section->addHeader();
// $header->addText("________ de _____________  hoja ________", $normalStyle);
// $header->addPreserveText('{PAGE} de {NUMPAGES} hojas', $normalStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);
$header->addPreserveText('{PAGE} de {NUMPAGES} hojas', $normalStyle, [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
    'spaceBefore' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // Espacio antes
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1)   // Espacio después
]);

$formatTextRun = [
    'size' => 11,
    'name' => 'Arial',
    'spacing' => 240,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH
];

$textrun = $section->addTextRun($formatTextRun);

// Valores generales (personalizar antes de generar)
$entidadNombre = $entidadNombre ?? 'LA ENTIDAD PRESTADORA';
$representanteNombre = $representanteNombre ?? 'EL/LA REPRESENTANTE LEGAL';
$representanteCargo = $representanteCargo ?? 'REPRESENTANTE LEGAL';
$entidadDescripcion = $entidadDescripcion ?? ''; // texto adicional sobre la entidad si aplica
$entidadDireccion = $entidadDireccion ?? 'domicilio conocido';
$notarioNombre = $notarioNombre ?? 'EL NOTARIO PÚBLICO';

// Texto principal (generalizado)
$textrun = $section->addTextRun($formatTextRun);

$textrun->addText("En el municipio de Cobán, departamento de Alta Verapaz, el $fechaLetras, ", $normalStyle);
$textrun->addText("NOSOTROS: ", $boldStyle);
$textrun->addText("Por una parte: ", $normalStyle);

$textrun->addText("$entidadNombre, ", $boldStyle);
if (!empty($entidadDescripcion)) {
    $textrun->addText($entidadDescripcion . " ", $normalStyle);
}
$textrun->addText("a través de ", $normalStyle);
$textrun->addText("$representanteNombre, ", $boldStyle);
$textrun->addText("en su calidad de $representanteCargo, ", $normalStyle);

$textrun->addText(
    "quien se identifica con su Documento Personal de Identificación (DPI) y manifiesta ser representante legal de la entidad antes mencionada, con domicilio en $entidadDireccion, actúa en la calidad indicada, ",
    $normalStyle
);

$textrun->addText("a quien en este contrato se le denominará simplemente ", $normalStyle);
$textrun->addText("“LA PARTE DEUDORA.” ", $boldStyle);

$textrun->addText("Y por la otra parte: Yo, ", $normalStyle);
$textrun->addText($nombreCliente . ", ", $boldStyle);
$textrun->addText(
    "de $edadletrasaux años de edad, $estadoCivil, $paisOrigen, $profesion, con domicilio en el departamento de $departamentoDomicilio y con residencia en Aldea $aldeaDomicilio, del municipio de $municipioDomicilio, quien se identifica con el Documento Personal de Identificación (DPI) número: $dpiletras, ($cui), extendido por el Registro Nacional de las Personas (RENAP) de la República de Guatemala, a quien en el presente contrato se le denominará ",
    $normalStyle
);
$textrun->addText("“LA PARTE ACREEDORA.” ", $boldStyle);

$textrun->addText("Manifestamos que nos encontramos en el libre ejercicio de nuestros derechos civiles y suscribimos el presente ", $normalStyle);
$textrun->addText("CONTRATO DE MUTUO, ", $boldStyle);
$textrun->addText("de conformidad con las siguientes cláusulas: ", $normalStyle);

$textrun->addText("PRIMERA: ", $boldStyle);
$textrun->addText("Manifiesto yo, ", $normalStyle);
$textrun->addText("$nombreCliente, ", $boldStyle);
$textrun->addText("es decir, ", $normalStyle);
$textrun->addText("LA PARTE ACREEDORA, ", $boldStyle);
$textrun->addText("que por este acto entrego a ", $normalStyle);
$textrun->addText("$entidadNombre, ", $boldStyle);
$textrun->addText("a través de $representanteNombre, ", $normalStyle);
$textrun->addText("en la calidad con la que actúa, la suma de ", $normalStyle);
$textrun->addText("$montoletra (Q. " . number_format($montoApertura, 2) . ") ", $boldStyle);
$textrun->addText("en efectivo en moneda de curso legal, en concepto de ", $normalStyle);
$textrun->addText("MUTUO. ", $boldStyle);

$textrun->addText("SEGUNDA: ", $boldStyle);
$textrun->addText("La cantidad otorgada deberá ser cancelada en la forma, plazo y demás estipulaciones que a continuación se detallan: ", $normalStyle);

$textrun->addText("a) PLAZO: ", $boldStyle);
$textrun->addText("El plazo es de ", $normalStyle);
$textrun->addText($plazoLetras . " MESES, ", $boldStyle);
$textrun->addText("contados a partir de la fecha de este contrato, el cual vencerá el día $fechaVencimientoLetras. ", $normalStyle);

$textrun->addText("b) INTERÉS: ", $boldStyle);
$textrun->addText("El capital mutuado generará un interés anual del ", $normalStyle);
$textrun->addText("$tasaInteresLetras ($tasaInteres %) por ciento, ", $boldStyle);
$textrun->addText("menos el diez por ciento (10%) para el pago del Impuesto Sobre la Renta. Los intereses se sumarán al capital y serán entregados a ", $normalStyle);
$textrun->addText("LA PARTE ACREEDORA ", $boldStyle);
$textrun->addText("al finalizar el plazo. ", $normalStyle);

$textrun->addText("c) FORMA DE PAGO: ", $boldStyle);
$textrun->addText("El capital se pagará al vencimiento mediante una sola amortización de ", $normalStyle);
$textrun->addText("$montoletra (Q. " . number_format($montoApertura, 2) . ") ", $boldStyle);
$textrun->addText("más los intereses generados, de la forma que ", $normalStyle);
$textrun->addText("LA PARTE ACREEDORA ", $boldStyle);
$textrun->addText("indique (transferencia bancaria o efectivo). ", $normalStyle);

$textrun->addText("d) CESIÓN DEL CRÉDITO: ", $boldStyle);
$textrun->addText("Este crédito no es cedible ni negociable con terceras personas sin el consentimiento expreso de ambas partes. ", $normalStyle);

$textrun->addText("e) CARTA TOTAL DE PAGO: ", $boldStyle);
$textrun->addText("LA PARTE ACREEDORA se obliga a otorgar la ", $normalStyle);
$textrun->addText("CARTA TOTAL DE PAGO ", $boldStyle);
$textrun->addText("al cancelar totalmente el capital e intereses. ", $normalStyle);

$textrun->addText("f) PENALIZACIÓN: ", $boldStyle);
$textrun->addText("Si alguna de las partes solicita la cancelación anticipada, se aplicarán las penalizaciones convencionadas en este contrato o las que ambas partes acuerden por escrito. ", $normalStyle);

$textrun->addText("g) INCUMPLIMIENTO: ", $boldStyle);
$textrun->addText("Las partes acuerdan someterse a los tribunales competentes y asumen los gastos judiciales o extrajudiciales que se deriven del incumplimiento, aceptando el presente contrato como título ejecutivo en los términos de la ley. ", $normalStyle);

$textrun->addText("TERCERA: ", $boldStyle);
$textrun->addText("Por este acto, ", $normalStyle);
$textrun->addText("$entidadNombre, ", $boldStyle);
$textrun->addText("representada por $representanteNombre, ", $normalStyle);
$textrun->addText("reconoce adeudar a ", $normalStyle);
$textrun->addText("$nombreCliente, ", $boldStyle);
$textrun->addText("la suma indicada anteriormente, la cual recibe a su entera satisfacción. ", $normalStyle);

$textrun->addText("CUARTA: DE LOS BENEFICIARIOS: ", $boldStyle);
$textrun->addText("En caso de imposibilidad para ejercer derechos por parte de LA PARTE ACREEDORA, la cantidad deberá devolverse a la persona beneficiaria designada: ", $normalStyle);
$textrun->addText("$nombreBeneficiario, ", $boldStyle);
$textrun->addText("DPI: $dpiLetrasBeneficiario ($dpiBeneficiario), quien es " . mb_strtolower($parentescoBeneficiario, 'UTF-8') . " de LA PARTE ACREEDORA. ", $normalStyle);

$textrun->addText("QUINTA: ", $boldStyle);
$textrun->addText("Ambas partes aceptamos el contenido íntegro del presente contrato, y firmamos en señal de conformidad. ", $normalStyle);

if (!empty($testigos)) {
    $nombreTestigo = $testigos[0]['nombre'];
    $dpiTestigo = dpi_format($testigos[0]['dpi']);
    $dpiLetrasTestigo = dpi_letra($dpiTestigo, $letras);

    $textrun->addText("Se deja constancia que quien no sabe firmar deja su impresión dactilar y firma a ruego el testigo: ", $normalStyle);
    $textrun->addText("$nombreTestigo, ", $boldStyle);
    $textrun->addText("DPI: $dpiLetrasTestigo ($dpiTestigo). ", $normalStyle);
} else {
    $textrun->addText("Firmamos de conformidad. ", $normalStyle);
}

$section->addText("F.__________________________                 F.__________________________ ", $normalStyle);
$section->addText("         DEUDOR                                         ACREEDOR", $normalStyle);

if (!empty($testigos)) {
    $section->addTextBreak(2);
    $section->addText("F.__________________________ ", $normalStyle,['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    $section->addText("TESTIGO", $normalStyle,['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
}

$nombreAutentica = (!empty($testigos)) ? $nombreTestigo : $nombreCliente;
$dpiAutentica = (!empty($testigos)) ? $dpiTestigo : $cui;
$dpiLetrasAutentica = (!empty($testigos)) ? $dpiLetrasTestigo : $dpiletras;

$section->addText("AUTENTICA. ", $boldStyle);

$textrun = $section->addTextRun($formatTextRun);
$textrun->addText("En el municipio de Nebaj, departamento de El Quiché el $fechaLetras, Yo, ", $normalStyle);
$textrun->addText("$notarioNombre, ", $boldStyle);
$textrun->addText("DOY FE: Que las firmas son auténticas por haber sido puestas en mi presencia por: ", $normalStyle);
$textrun->addText("$representanteNombre, ", $boldStyle);
$textrun->addText("y por $nombreAutentica, DPI: $dpiLetrasAutentica ($dpiAutentica). ", $normalStyle);

$textrun->addText("Dichas firmas calzan un CONTRATO DE MUTUO celebrado en documento privado. En fe de lo anterior los signatarios firman la presente Acta de Legalización de Firmas. ", $normalStyle);

$textrun->addText("DA FE.", $boldStyle);

$section->addText("F.__________________________                 F.__________________________ ", $normalStyle);

if (!empty($testigos)) {
    $section->addTextBreak(2);
    $section->addText("F.__________________________ ", $normalStyle,['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    $section->addTextBreak(1);
}

$section->addText("ANTE MÍ: ", $boldStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);


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
