<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$hoy = date("Y-m-d");

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\Auth;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\FpdfExtend;

$csrf = new CSRFProtection();

if (!($csrf->validateToken($_POST['datosval'][0][0] ?? '', false))) {
    $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
    $opResult = array(
        $errorcsrf,
        0,
        "reprint" => 1,
        "timer" => 3000
    );
    echo json_encode($opResult);
    return;
}

// Log::info("post datosval", $_POST['datosval']);

$status = false;
try {
    $database = new DatabaseAdapter();
    $db_name_general = $_ENV['DDBB_NAME_GENERAL'];

    $data = [
        'idInteres' => $_POST['datosval'][3][0]['id'] ?? null,
        'idIsr' => $_POST['datosval'][3][0]['id2'] ?? null,
        'tipo' => $_POST['tipo'] ?? null,
    ];

    $rules = [
        'idInteres' => 'optional|numeric|min:1',
        'idIsr' => 'optional|numeric|min:1',
        'tipo' => 'required|in:pdf',
    ];

    $validator = Validator::make($data, $rules);
    if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
    }

    // Validar que al menos uno de los dos IDs esté presente
    if (empty($data['idInteres']) && empty($data['idIsr'])) {
        throw new SoftException("Debe proporcionar al menos un ID (Interés o ISR)");
    }

    $database->openConnection();

    $result = [];
    $resultIsr = [];

    // Consultar información de interés si existe el ID
    if (!empty($data['idInteres'])) {
        $query =
            "SELECT mov.ccodaho,mov.dfecope,mov.ctipope,mov.cnumdoc, mov.crazon,mov.concepto,mov.nlibreta,mov.monto, cli.short_name, cli.no_identifica,tip.nombre
                FROM ahommov mov 
                INNER JOIN ahomcta cta ON cta.ccodaho=mov.ccodaho
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                INNER JOIN ahomtip tip ON tip.ccodtip = SUBSTR(cta.ccodaho,7,2)
                WHERE mov.id_mov = ? AND mov.cestado=1";

        $result = $database->getAllResults($query, [$data['idInteres']]);

        if (empty($result)) {
            throw new SoftException("No se encontro la informacion de la transaccion de interes");
        }
    }

    // Consultar información de ISR si existe el ID
    if (!empty($data['idIsr'])) {
        $queryIsr =
            "SELECT mov.ccodaho,mov.dfecope,mov.ctipope,mov.cnumdoc, mov.crazon,mov.concepto,mov.nlibreta,mov.monto, cli.short_name, cli.no_identifica,tip.nombre
            FROM ahommov mov 
            INNER JOIN ahomcta cta ON cta.ccodaho=mov.ccodaho
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
            INNER JOIN ahomtip tip ON tip.ccodtip = SUBSTR(cta.ccodaho,7,2)
            WHERE mov.id_mov = ? AND mov.cestado=1";

        $resultIsr = $database->getAllResults($queryIsr, [$data['idIsr']]);

        if (empty($resultIsr)) {
            throw new SoftException("No se encontro la informacion de la transaccion de ISR");
        }
    }

    $info = $database->getAllResults(
        "SELECT nom_agencia, nomb_comple, muni_lug, emai, tel_1, tel_2, nit, log_img 
            FROM {$db_name_general}.info_coperativa ins
            INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?",
        [$_SESSION['id_agencia']]
    );
    if (empty($info)) {
        throw new SoftException("Institucion asignada a la agencia no encontrada");
    }

    $status = true;
} catch (SoftException $se) {
    $mensaje = "Advertencia: " . $se->getMessage();
} catch (Exception $e) {
    $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}


switch ($data['tipo']) {
    case 'pdf':
        printpdf($result, $resultIsr, $info);
        break;
}


//funcion para generar pdf
function printpdf($interes, $isr, $info)
{

    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];


    $pdf = new FpdfExtend();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";

    // Configuración inicial
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Logo y Encabezado Institucional
    if (file_exists($rutalogoins)) {
        $pdf->Image($rutalogoins, 15, 10, 25);
    }

    $pdf->SetFont($fuente, 'B', 14);
    $pdf->Cell(0, 6, $institucion, 0, 1, 'C');

    $pdf->SetFont($fuente, '', 9);
    $pdf->Cell(0, 4, $direccionins, 0, 1, 'C');
    $pdf->Cell(0, 4, "NIT: " . $nitins, 0, 1, 'C');
    $pdf->Cell(0, 4, "Tel: " . $telefonosins, 0, 1, 'C');
    $pdf->Cell(0, 4, $emailins, 0, 1, 'C');

    $pdf->Ln(3);

    // Título del comprobante
    $pdf->SetFont($fuente, 'B', 12);
    $pdf->Cell(0, 6, "COMPROBANTE DE ACREDITACION DE INTERESES", 0, 1, 'C');

    $pdf->Ln(2);

    // Determinar qué datos mostrar (usar interes si existe, sino isr)
    $datosPrincipales = !empty($interes) ? $interes : $isr;

    // Información de la transacción
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell(0, 5, "DATOS DE LA ACREDITACION", 0, 1, 'L');
    $pdf->SetFont($fuente, '', 9);

    // Línea separadora
    $pdf->Cell(0, 0, '', 'T', 1);
    $pdf->Ln(2);

    // Datos del cliente
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(40, 5, "Cliente:", 0, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Cell(0, 5, Utf8::decode($datosPrincipales[0]['short_name']), 0, 1);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(40, 5, "Identificacion:", 0, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Cell(0, 5, $datosPrincipales[0]['no_identifica'], 0, 1);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(40, 5, "Cuenta:", 0, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Cell(60, 5, $datosPrincipales[0]['ccodaho'], 0, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(30, 5, "Libreta:", 0, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Cell(0, 5, $datosPrincipales[0]['nlibreta'], 0, 1);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(40, 5, "Tipo de Cuenta:", 0, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Cell(0, 5, Utf8::decode($interes[0]['nombre'] ?? $isr[0]['nombre'] ?? ''), 0, 1);

    $pdf->Ln(3);

    // Detalles de la transacción de interés (si existe)
    if (!empty($interes)) {
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->Cell(40, 5, "Fecha Operacion:", 0, 0);
        $pdf->SetFont($fuente, '', 9);
        $fechaInteres = Date::format($interes[0]['dfecope'], 'd/m/Y');
        $pdf->Cell(60, 5, $fechaInteres, 0, 0);
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->Cell(30, 5, "No. Doc:", 0, 0);
        $pdf->SetFont($fuente, '', 9);
        $pdf->Cell(0, 5, $interes[0]['cnumdoc'], 0, 1);

        $pdf->SetFont($fuente, 'B', 9);
        $pdf->Cell(40, 5, "Concepto:", 0, 0);
        $pdf->SetFont($fuente, '', 9);
        $pdf->MultiCell(0, 5, Utf8::decode($interes[0]['concepto']));

        $pdf->SetFont($fuente, 'B', 11);
        $pdf->Cell(40, 6, "Monto Intereses:", 0, 0);
        $pdf->SetFont($fuente, 'B', 11);
        $montoInteres = Moneda::formato($interes[0]['monto']);
        $pdf->Cell(0, 6, $montoInteres, 0, 1, 'R');
    }

    // Si existe información de ISR, mostrarla
    if (!empty($isr)) {
        $pdf->Ln(4);
        $pdf->SetFont($fuente, 'B', 10);
        $pdf->Cell(0, 5, "RETENCION DE ISR", 0, 1, 'L');
        $pdf->Cell(0, 0, '', 'T', 1);
        $pdf->Ln(2);

        $pdf->SetFont($fuente, 'B', 9);
        $pdf->Cell(40, 5, "Fecha Operacion:", 0, 0);
        $pdf->SetFont($fuente, '', 9);
        $fechaIsr = Date::format($isr[0]['dfecope'], 'd/m/Y');
        $pdf->Cell(60, 5, $fechaIsr, 0, 1);

        $pdf->SetFont($fuente, 'B', 9);
        $pdf->Cell(40, 5, "Concepto:", 0, 0);
        $pdf->SetFont($fuente, '', 9);
        $pdf->MultiCell(0, 5, Utf8::decode($isr[0]['concepto']));

        $pdf->SetFont($fuente, 'B', 11);
        $pdf->Cell(40, 6, "Monto ISR:", 0, 0);
        $pdf->SetFont($fuente, 'B', 11);
        $montoIsr = Moneda::formato($isr[0]['monto']);
        $pdf->Cell(0, 6, $montoIsr, 0, 1, 'R');
    }
    $pdf->Ln(5);

    // Información adicional
    $pdf->SetFont($fuente, '', 8);
    $pdf->Cell(0, 4, "Agencia: " . $oficina, 0, 1);
    $pdf->Cell(0, 4, "Fecha Impresion: " . date("d/m/Y H:i:s"), 0, 1);
    $userName = Auth::getUserName() ?? 'N/D';
    $pdf->Cell(0, 4, "Usuario: " . Utf8::decode($userName), 0, 1);

    $pdf->Ln(8);

    // Espacio para firmas
    $pdf->SetFont($fuente, '', 9);
    $pdf->Cell(0, 5, "FIRMAS DE AUTORIZACION", 0, 1, 'C');
    $pdf->Ln(3);

    // Utilizar el método firmas de FpdfExtend
    $pdf->firmas(['Elaborado por', 'Autorizado por']);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Comprobante_Interes_Manual",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
