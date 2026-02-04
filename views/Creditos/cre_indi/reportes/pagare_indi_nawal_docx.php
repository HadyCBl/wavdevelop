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
$hoy = date("Y-m-d");

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Border;
use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];
$codcuenta = $archivo[0];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT cre.DFecDsbls fechades,cre.DFecVen fecven,cre.noPeriodo cuotas,cre.NMonCuo moncuota,cre.Cestado,NCapDes mondes,MonSug monsug,cli.idcod_cliente ccodcli,cli.short_name nombrecli,cli.primer_name,cli.segundo_name,
                cli.tercer_name,cli.primer_last,cli.segundo_last,cli.Direccion direccion,cli.profesion,age.id_agencia,usu.id_usu,
                cli.tel_no1,cli.no_identifica,usu.nombre nomanal,usu.apellido apeanal,
                IFNULL((SELECT nombre FROM tb_municipios WHERE codigo=age.municipio),'-') municipio,
                IFNULL((SELECT nombre FROM tb_departamentos WHERE id=age.departamento),'-') departamento,
                IFNULL((SELECT Titulo FROM $db_name_general.tb_ActiEcono WHERE id_ActiEcono=cre.ActoEcono),'-') actividadecono,
                IFNULL((SELECT SectoresEconomicos FROM $db_name_general.tb_sectoreseconomicos WHERE id_SectoresEconomicos=cre.CSecEco),'-') sectorecono,
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

$monto = ($result[0]["Cestado"] == "F") ? $result[0]["mondes"] : $result[0]["monsug"];
$format_monto = new NumeroALetras();
$cantidadenletras = $format_monto->toMoney($monto, 2, 'QUETZALES', 'CENTAVOS');

$nocuotas = $result[0]["cuotas"];

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
    'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // Ajusta el margen superior
    'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // Ajusta el margen derecho
    // 'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // Ajusta el margen inferior
    'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2)  // Ajusta el margen izquierdo
]);

// Estilos
$fontStyle = ['name' => 'Arial', 'size' => 8];
$fontStyletext = ['name' => 'Arial', 'size' => 6.5];
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
$table = $section->addTable();

$row1 = $table->addRow(400);

$cell1 = $row1->addCell(4000, ['vMerge' => 'restart']);
// $cell1->addImage('../../../../includes/img/corfisernawal.jpg', ['width' => 60, 'height' => 60]);

$cell2 = $row1->addCell(1500, $borderStyle, $cellStyle);
$cell2->addText('TELÉFONO CLIENTE', $styletextos, $cellStyle);
$cell2->addText($telcliente, $styletextos, $cellStyle);

$row1->addCell(400);

$cell4 = $row1->addCell(4500, $borderStyle);
// $cell4->addText('PAGARÉ LIBRE DE PROTESTO', $styletextos,$cellStyle);
$cell4->addText('PAGARÉ LIBRE DE PROTESTO', $styletextos, [
    'align' => 'center',
    'textAlignment' => 'center',
    // 'spaceAfter' => 0,
    // 'spaceBefore' => 0, 
]);


$row2 = $table->addRow();
$row2->addCell(null, ['vMerge' => 'continue']); // Continua la fusión vertical

$row2->addCell(3000); // Segunda columna de la segunda fila
$row2->addCell(400);  // Tercera columna de la segunda fila
$row2->addCell(3000); // Cuarta columna de la segunda fila

// $section->addTextBreak(1, [$fontStyle]);
$table = $section->addTable(['borderSize' => 1, 'borderColor' => '000000', 'cellMargin' => 80]);
$rowt = $table->addRow(100);

$cellTexts = ['LUGAR Y FECHA DE EXPEDICIÓN', 'CÓDIGO CLIENTE', 'BUENO POR:', 'GIRO DEL NEGOCIO:'];
$widthTexts = [5000, 1000, 1000, 3000];
foreach ($cellTexts as $key => $text) {
    $cell = $rowt->addCell($widthTexts[$key], array_merge($cellStylemargins, $borderStyle));
    $cell->addText($text, $styletextos, array_merge($cellStyle, $paragraphStyle));
}

$rowte = $table->addRow(75);
$dataceldas = ["$municipio, $fechaenletras", $codcliente, number_format($monto, 2), $actividadeconomica];
for ($i = 0; $i < 4; $i++) {
    $cell = $rowte->addCell($widthTexts[$key], array_merge($cellStyle, $borderStyle));
    $cell->addText($dataceldas[$i], $fontStyle, array_merge($cellStyle, $paragraphStyle));
}

$section->addText("En el Municipio de $municipio del departamento $departamento el día $fechaenletras YO: $nombrecliente Guatemalteco (a), $profesion, con domicilio ubicado en $diredomicilio, me identifico con DPI número $dpi extendido por el Registro Nacional de Personas (RENAP), por medio del presente PAGARÉ LIBRE DE PROTESTO, Prometo incondicionalmente pagar a la orden de la entidad $razonsocial cuyo nombre comercial es $namecomercial La cantidad de:", $fontStyletext);

$table = $section->addTable();
$row21 = $table->addRow();
$cell2 = $row21->addCell(10000, $borderStyle);
$cell2->addText($cantidadenletras, $styletextos, $cellStyle);


$section->addText("Dicha cantidad se amortizará de la siguiente manera: $nocuotas pagos consecutivos en $cantdias días, en cuotas de Q $cuota a partir de la presente fecha del contrato, hasta completar la totalidad de la deuda, la falta de pago y el vencimiento del plazo, la entidad $razonsocial podrá exigir íntegramente el cumplimiento de la obligación generada por el presente título de crédito. En caso de incumplimiento RENUNCIO al fuero de mi domicilio y me someto a los tribunales que elija la entidad prestadora del servicio, para ejecución del presente título, señalo como lugar para recibir notificaciones mi residencia los intereses moratorios por atraso o incumplimiento ascenderán a la cantidad del 7% mensual.", $fontStyletext);

$table = $section->addTable(['borderSize' => 1, 'borderColor' => '000000', 'cellMargin' => 80]);
$table->addRow(100);

$cell = $table->addCell(4500, array_merge($cellStyle, $borderStyle));
$cell->addText('NOMBRE Y DATOS DEL DEUDOR', $fontStyletextbold, $paragraphStyle);

$cell = $table->addCell(1000, array_merge($cellStyle, $borderStyle));
$cell->addText('HUELLA', $fontStyletextbold, $paragraphStyle);

$cell = $table->addCell(4500, array_merge($cellStyle, $borderStyle));
$cell->addText('AVAL', $fontStyletextbold, $paragraphStyle);
$cell = $table->addCell(1000, array_merge($cellStyle, $borderStyle));
$cell->addText('HUELLA', $fontStyletextbold, $paragraphStyle);

$table->addRow(400);
$cell = $table->addCell(4500, array_merge(['height' => 1000], $borderStyle));
$cell->addText("NOMBRES: $nombrescliente", $fontStyletextbold, $paragraphStyle);
$cell->addText("APELLIDOS: $apellidoscliente", $fontStyletextbold, $paragraphStyle);
$cell->addText("DOMICILIO: $diredomicilio", $fontStyletextbold, $paragraphStyle);

$cell = $table->addCell(1000, array_merge(['height' => 1000], $borderStyle));
$cell->addText(' ', $fontStyletextbold);

$cell = $table->addCell(4500, array_merge(['height' => 1000], $borderStyle));
$cell->addText("NOMBRES: $nombresaval", $fontStyletextbold, $paragraphStyle);
$cell->addText("APELLIDOS: $apellidosaval", $fontStyletextbold, $paragraphStyle);
$cell->addText("DOMICILIO: $direccionaval", $fontStyletextbold, $paragraphStyle);

$cell = $table->addCell(1000, array_merge(['height' => 1000], $borderStyle));
$cell->addText(' ', $fontStyletextbold);


$table->addRow(400);
$cell = $table->addCell(4000, array_merge($cellStyle, $borderStyle));
$cell->addText('FIRMA DEUDOR', $fontStyletextbold, $paragraphStyle);

$cell->getStyle()->setGridSpan(2);

$cell = $table->addCell(4000, array_merge($cellStyle, $borderStyle));
$cell->addText('FIRMA AVAL', $fontStyletextbold, $paragraphStyle);
$cell->getStyle()->setGridSpan(2);

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
