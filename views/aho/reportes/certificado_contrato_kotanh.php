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
$porcentajeBeneficiario = $beneficiarios[0]['porcentaje'];

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
$fontStyle = ['name' => 'Candara', 'size' => 10];
$fontStyletext = ['name' => 'Candara', 'size' => 10];
$cellStyle = ['valign' => 'center', 'align' => 'center',];
$borderStyle = ['borderSize' => 1, 'borderColor' => '000000'];
$styletextos = [
    'bold' => true,
    'size' => 10,
    'name' => 'Candara',
];
$fontStyletextbold = [
    'bold' => true,
    'size' => 10,
    'name' => 'Candara',
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
$normalStyle = ['size' => 10];
$header = $section->addHeader();
$header->addImage(__DIR__ . '../../../../includes/img/KOTAN.png', [
    'width' => 200,
    'height' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER
]);
$header->addPreserveText('{PAGE} de {NUMPAGES} hojas', $normalStyle, [
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT,
    'spaceBefore' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1),
    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1)
]);

$formatTextRun = [
    'size' => 10,
    'name' => 'Candara',
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

$textrun->addText("NOSOTROS: ", $normalStyle);
$textrun->addText("Sebastián Juan Baltazar, ", $boldStyle);

$textrun->addText('de treinta y dos años de edad, casado, guatemalteco, comerciante, de este domicilio,  me identifico con el Documento Personal de identificación, con Código Único de Identificación número Dos mil ciento cuarenta y cinco Diecisiete mil ciento cuarenta y cuatro Mil trescientos cuarenta y cinco (2145 17144 1325) extendida por el Registro Nacional de las Personas de la República de Guatemala; señalo que comparezco y actúo en calidad de Presidente del Consejo de Administración y Representante Legal de la entidad denominada GRUPO KOTANH, SOCIEDAD ANONIMA, calidad que acredito con el razonamiento del acta emitido por el Registro Mercantil de fecha dieciocho de marzo de dos mil veinticuatro, inscrita bajo el número Setecientos treinta y cinco mil cuatrocientos sesenta y ocho (735,468), Folio Setecientos cuarenta y seis (746), Libro Ochocientos veintinueve (829) de Auxiliares de Comercio del Registro Mercantil de la República de Guatemala, quien en lo sucesivo se llamara ', $normalStyle);
$textrun->addText('" GRUPO KOTANH, S.A." o "el AGENTE"; ', $boldStyle);
$textrun->addText('y por la otra parte ', $normalStyle);
$textrun->addText("Ana Baltazar José Nicolas, de ", $boldStyle);
$textrun->addText($edadletrasaux . ' años de edad, ' . $estadoCivil . ', guatemalteca, ' . $profesion . ', de este domicilio, y con residencia en '. $aldeaDomicilio . ', del municipio de ' . $municipioDomicilio . ', del departamento de ' . $departamentoDomicilio . ', me identifico con el Documento Personal de Identificación con Código Único de identificación número ' . $dpiletras . ' (' . $cui . '), extendió por el Registro Nacional de las Personas de la República de Guatemala, quien en lo sucesivo me denominare ', $normalStyle);
$textrun->addText('"INVERSIONISTA". ', $boldStyle);
$textrun->addText('Los otorgantes manifestamos: A) Ser de las generales indicadas y encontrarnos en el libre ejercicio de nuestros derechos civiles. B) Que las representaciones que ejercitamos son suficientes de conformidad con la ley para la celebración de este contrato; y C) Que por el presente DOCUMENTO PRIVADO otorgamos ', $normalStyle);
$textrun->addText('CONTRATO DE INVERSION ', $boldStyle);
$textrun->addText('de conformidad con lo dispuesto en las siguientes cláusulas:', $normalStyle);

$textrun->addText("PRIMERA: ", $boldStyle);
$textrun->addText("Yo Sebastián Juan Baltazar, en la calidad con que actuó manifiesto que mi representada ", $normalStyle);
$textrun->addText('GRUPO KOTANH, SOCIEDAD ANONIMA. ', $boldStyle);
$textrun->addText("Por el presente acto acepta la ", $normalStyle);
$textrun->addText("INVERSIÓN, ", $boldStyle);
$textrun->addText("que desea hacer el/la señor/a ", $normalStyle);

$textrun->addText($nombreCliente . '; SEGUNDA: OBJETO. ', $boldStyle);
$textrun->addText("Por el presente contrato de Inversión las partes manifestamos que estamos de acuerdo que ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("recibirá dinero del/la ", $normalStyle);
$textrun->addText("INVERSIONISTA, ", $boldStyle);
$textrun->addText("con el objeto de invertirlo por cuenta y riesgo de éstas, en el tiempo, forma y modo que se pactan más adelante, de manera sistemática y profesional la entidad ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("inscrita, para poder después devolver al/a", $normalStyle);
$textrun->addText(" INVERSIONISTA ", $boldStyle);
$textrun->addText("el dinero y sus frutos si los hubiere, una vez liquidadas las inversiones; sin que exista un rendimiento mínimo garantizado al/a ", $normalStyle);
$textrun->addText("INVERSIONISTA. ", $boldStyle);
$textrun->addText("y el ", $normalStyle);
$textrun->addText("AGENTE ", $boldStyle);
$textrun->addText("acuerdan que la remuneración que corresponde a éste último por la ejecución del presente contrato la podrá obtener el ", $normalStyle);
$textrun->addText("AGENTE ", $boldStyle);
$textrun->addText("directamente de las comisiones que cobre, por lo que el/la ", $normalStyle);
$textrun->addText("INVERSIONISTA ", $boldStyle);
$textrun->addText("no quedará obligada al pago de la comisión, salvo el caso en que el rendimiento de la inversión proveniente de este contrato fuera superior al porcentaje estipulado en él, en cuyo caso el ", $normalStyle);
$textrun->addText("AGENTE ", $boldStyle);
$textrun->addText("sí cobrará una comisión según lo estipulado. La inversión la realizará ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("en el momento que considere oportuno, quedando facultado para colocar temporalmente el capital que a su juicio considere solventes, para evitar que el mismo quede ocioso. ", $normalStyle);

$textrun->addText("TERCERA: KOTANH S.A., ", $boldStyle);
$textrun->addText("actuará como Agente y Administrador de la presente inversión con todas las facultades. Para este efecto el/la ", $normalStyle);
$textrun->addText("INVERSIONISTA ", $boldStyle);
$textrun->addText("confiere a ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("todas aquellas facultades tan amplias como sean necesarias, que se desprenden de la Ley y del presente contrato, y en lo que a las acciones emitidas por sociedades mercantiles se refiere, ejercitará por cuenta de los titulares los derechos que de las mismas se deriven, conforme   a su leal saber y entender pudiendo ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("concretar definitivamente cualquier negocio para invertir el capital que ", $normalStyle);
$textrun->addText(" LA INVERSIONISTA ", $boldStyle);
$textrun->addText("entregará, sin necesidad de autorización o ratificación previa. Por lo tanto, será ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("el encargado de cobrar oportunamente el rendimiento de las acciones o liquidar la inversión y trasladar su resultado al inversionista, según corresponda el/la ", $normalStyle);
$textrun->addText("INVERSIONISTA. ", $boldStyle);
$textrun->addText("declara que es de su conocimiento y que no tiene ningún inconveniente en que ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("a la vez que actúa como Agente Administrador del presente contrato, actúa como Agente de ", $normalStyle);

$textrun->addText("KOTANH, S.A. CUARTA: DE LA INVERSION. ", $boldStyle);
$textrun->addText("EL/LA ", $normalStyle);
$textrun->addText("INVERSIONISTA ", $boldStyle);
$textrun->addText("por el presente acto le entrega a ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("la cantidad de ", $normalStyle);
$textrun->addText($montoletra . ' (Q. ' . number_format($montoApertura, 2) . '). ', $boldStyle);
$textrun->addText("en concepto de inversión el que se pagara en los próximos ", $normalStyle);
$textrun->addText($plazoLetras . ' MESES, ', $boldStyle);
$textrun->addText("antes del " . $fechaVencimientoLetras . ", ", $normalStyle);
$textrun->addText("el cual generará un interés del " . $tasaInteresLetras . " por ciento anual, con el encargo de que en su nombre y a través de la inversión constituida por el presente contrato, lo invierta conforme las condiciones y estipulaciones que se establecen en este contrato. Derivado de la inversión que", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA ", $boldStyle);
$textrun->addText("realiza, el ", $normalStyle);
$textrun->addText("AGENTE ", $boldStyle);
$textrun->addText("entrega a éste un Certificado de Participación en el que contiene el número de participaciones de ", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA, ", $boldStyle);
$textrun->addText("y al vencimiento del plazo de la inversión, el ", $normalStyle);
$textrun->addText("AGENTE ", $boldStyle);
$textrun->addText("redimirá a ", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA ", $boldStyle);
$textrun->addText("las participaciones que éste tenga. ", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA ", $boldStyle);
$textrun->addText("declara que los que conforme a este contrato invierte son de libre disposición, por lo que el riesgo de la presente inversión no lo corre en perjuicio de ningún tercero. Las inversiones que se realicen en objeto de este contrato, se realizan sin que los inversionistas reciban garantía de un rendimiento mínimo determinado. Las inversiones que realice son por cuenta y riesgo de ", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA. ", $boldStyle);

$textrun->addText("QUINTA: CONTABILIZACION DE LA INVERSION. ", $boldStyle);
$textrun->addText("El dinero recibido por ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("por cuenta de los inversionistas, no se podrán mezclar con el patrimonio de ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("la cual solo podrá emplear el dinero ya relacionado, para los fines expresamente indicados en este contrato. La inversión deberá contabilizarse debidamente separados de las cuentas propias de ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("como Agente Administrador. ", $normalStyle);

$textrun->addText("SEXTA: PLAZO. ", $boldStyle);
$textrun->addText("El plazo del presente contrato es de ". $plazoLetras . " meses contados a partir del " . $fechaLetras . " y cesara el día " . $fechaVencimientoLetras . '. ', $normalStyle);
$textrun->addText("SEPTIMA: CAUSAS DE TERMINACIÓN DE LA INVERSION. "  , $boldStyle);
$textrun->addText("Son causas de terminación del presente contrato las siguientes: A) Imposibilidad de poder seguir realizando el objeto principal de La inversión. B) Resolución tomada por ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("como Agente Administrador comunicada a la inversionista con treinta días de anticipación. C) Las previstas en el contrato o futuras modificaciones; D) Cuando la Ley o un Tribunal Competente así lo determine. ", $normalStyle);

$textrun->addText("OCTAVA: BENEFICIARIOS DE EL/LA INVERSIONISTA: ", $boldStyle);
$textrun->addText("En caso de que ", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA ", $boldStyle);
$textrun->addText("falleciere durante el plazo del presente contrato, será tomado como beneficiario a: " . $nombreBeneficiario . ' su ' . mb_strtolower($parentescoBeneficiario, 'UTF-8') . ' con el ' . $porcentajeBeneficiario . '%. ', $normalStyle);
$textrun->addText("NOVENA: EFECTOS PROCESALES. ", $boldStyle);
$textrun->addText("Las partes expresamente manifiestan que para efectos de cualquier conflicto derivado del presente contrato aceptan como ley aplicable al mismo la legislación de la República de Guatemala, y en Todos los conflictos que surjan con ocasión del presente contrato, tanto durante su vigencia como a la terminación del mismo, por cualquier causa, ya sea entre ", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA", $boldStyle);
$textrun->addText("o su sucesores, y el AGENTE o ", $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("respecto a la interpretación, alcances y efectos del presente contrato, serán sometidos a un arbitraje de equidad en la Ciudad de Huehuetenango, con exclusión de cualquier otra vía judicial o extrajudicial Para el nombramiento de los árbitros, las partes acuerdan que el Tribunal Arbitral estará conformado por tres árbitros. Cada parte nominará a un árbitro y ambos árbitros así nominados deberán nominar, de común acuerdo, a un tercer árbitro que fungirá como presidente del Tribunal Arbitral. Adicionalmente, se acuerda que el arbitraje se regirá bajo el procedimiento contenido en el Reglamento de Arbitraje de la Comisión de las Naciones Unidas para el Derecho Mercantil Internacional (CNUDMI) Cualquier comunicación que las partes deban dirigirse en ocasión de este contrato y demás obligaciones convenidas, deberá hacerse a las siguientes direcciones señaladas por las partes: ", $normalStyle);
$textrun->addText("EL/LA INVERSIONISTA ", $boldStyle);
$textrun->addText("señala como lugar para recibir notificaciones en la " . $aldeaDomicilio . ', ' . $municipioDomicilio . ', ' . $departamentoDomicilio . ';', $normalStyle);
$textrun->addText("KOTANH, S.A., ", $boldStyle);
$textrun->addText("señala como lugar para recibir notificaciones en la aldea Ticolal Pueblo Nuevo Jucup, San Sebastián Coatán, Huehuetenango. Las partes no podrán cambiar las direcciones relacionadas para efectos de este contrato, sin previo aviso por escrito a la otra parte con por lo menos quince días de anticipación, por lo cual las partes aceptan como válidas las notificaciones, avisos, requerimientos o citaciones que se le hagan en las direcciones señaladas o en aquellas que señalen conforme lo pactado en esta cláusula ", $normalStyle);
$textrun->addText("DÉCIMA: INTEGRACION, INTERPRETACION, Y EJECUCION DEI CONTRATO. ", $boldStyle);
$textrun->addText("El presente contrato y sus modificaciones se integrará, interpretará y ejecutará en todo caso, conforme los principios de la buena fe y la verdad sabida, lo cual prevalecerá en cualquier punto del contrato que sea ambiguo, oscuro o contradictorio. ", $normalStyle);
$textrun->addText("DECIMA PRIMERA: ACEPTACIÓN. ", $boldStyle);
$textrun->addText("Los comparecientes enterados del contenido, validez y demás efectos legales del presente documento lo aceptamos, ratificamos y firmamos en dos originales. 
en la aldea Ticolal Pueblo Nuevo Jucup, San Sebastián Coatán, Huehuetenango el " . $fechaLetras . "." , $normalStyle);

$textrun->addTextBreak(2);
$textrun->addText("Firmas: ", $normalStyle);

$textrun->addTextBreak(2);
$section->addText("F.__________________________                 F.__________________________ ", $normalStyle);
$section->addText("          EL AGENTE                                             EL (LA) INVERSIONISTA", $normalStyle);




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
