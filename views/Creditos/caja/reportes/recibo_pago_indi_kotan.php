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
$idusuario = $_SESSION['id'];

include '../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use Luecano\NumeroALetras\NumeroALetras;

//se recibe los datos
$datos = $_POST["datosval"];

//Informacion de datosval 
$inputs = $datos[0];
$archivo = $datos[3];

//Informacion de archivo 
$usuario = ucwords(strtolower($_SESSION['nombre'] . ' ' . $_SESSION['apellido']));
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

$consulta = "SELECT ck.DFECPRO AS fechadoc, CAST(ck.DFECSIS as Date) AS fechaaplica, cl.short_name AS nombre, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
  ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
  (IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
  ((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<=?)) AS saldo, cl.no_identifica AS dpi, ck.CNROCUO AS numoperacion,
  cm.CodCli AS codcliente, pd.nombre as producto
  FROM cremcre_meta cm
  INNER JOIN CREDKAR ck ON cm.CCODCTA=ck.CCODCTA
  INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
  INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
  INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo=ctf.id
  WHERE cm.CCODCTA=? AND ck.CNUMING=? AND ck.CESTADO!='X' AND ck.CTIPPAG='P'";
//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($consulta, [$numerocuota, $codigocredito, $cnuming]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                  INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $result, $info);

function printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $registro, $info)
{

    $hoy = date("d-m-Y H:i:s");
    $institucion = decode_utf8($info[0]["nomb_comple"]);

    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        // atributos de la clase
        public $institucion;

        public function __construct($institucion)
        {
            parent::__construct();
            $this->institucion = $institucion;
        }
    }

    $pdf = new PDF($institucion);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    recibo($pdf, $registro, $hoy, $usuario, $info);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Comprobanteindividual",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
function recibo($pdf, $registro, $hoy, $usuario, $info)
{

    /**
     * CellFit($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $scale = false, $force = true)
     * Ajusta el texto dentro de una celda para que se ajuste al ancho especificado.
     *
     * @param float $w Ancho de la celda.
     * @param float $h Altura de la celda. Si es 0, se ajusta automáticamente.
     * @param string $txt Texto a mostrar en la celda.
     * @param int $border Indica si se debe dibujar un borde alrededor de la celda. 0 = sin borde, 1 = borde completo.
     * @param int $ln Indica la posición siguiente de la celda. 0 = a la derecha, 1 = al comienzo de la siguiente línea.
     * @param string $align Alineación del texto. Valores posibles: 'L' (izquierda), 'C' (centro), 'R' (derecha).
     * @param bool $fill Indica si se debe rellenar el fondo de la celda. false = sin relleno, true = con relleno.
     * @param string $link URL o identificador de enlace.
     * @param bool $scale Indica si se debe escalar el texto para que se ajuste al ancho de la celda. false = no escalar, true = escalar.
     * @param bool $force Indica si se debe forzar el ajuste del texto incluso si es más pequeño que el ancho de la celda. false = no forzar, true = forzar.
     */

    $pdf->AddFont('Calibri', '', 'calibri.php');
    $pdf->AddFont('Calibri', 'B', 'calibrib.php');
    $fuente = "Calibri";

    $tabular = 30;
    $tamanio_linea = 7;
    $ancho_linea2 = 30;
    $pdf->ln(30);

    $pdf->SetFont($fuente, '', 7);

    // helper closure para dibujar un bloque en la X especificada, devuelve Y final
    $printBlock = function ($xStart) use ($pdf, $registro, $usuario, $hoy, $ancho_linea2, $tamanio_linea) {
        // comenzar desde la Y actual
        $y = $pdf->GetY();

        // linea 1 - Producto
        $pdf->SetXY($xStart, $y);
        $pdf->CellFit($ancho_linea2 + 17, $tamanio_linea - 1, decode_utf8('Producto: '), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea - 1, decode_utf8($registro[0]['producto']), 0, 0, 'L', 0, '', 1, 0);
        $y += 4.5;

        // linea 2 - Tipo de Operación
        $pdf->SetXY($xStart, $y);
        $pdf->CellFit($ancho_linea2 + 17, $tamanio_linea - 1, decode_utf8('Tipo de Operación: '), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea - 1, decode_utf8('PAGO CUOTA ') . $registro[0]['numoperacion'], 0, 0, 'L', 0, '', 1, 0);
        $y += 4.5;

        // linea 3 - Cuenta
        $pdf->SetXY($xStart, $y);
        $pdf->CellFit($ancho_linea2 + 17, $tamanio_linea - 1, decode_utf8('Cuenta: '), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea - 1, $registro[0]['ccodcta'], 0, 0, 'L', 0, '', 1, 0);
        $y += 4.5;

        // linea 4 - Cliente
        $pdf->SetXY($xStart, $y);
        $pdf->CellFit($ancho_linea2 + 17, $tamanio_linea - 1, decode_utf8('Cliente: '), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea - 1, $registro[0]['nombre'], 0, 0, 'L', 0, '', 1, 0);
        $y += 4.5;

        // linea 5 - Cantidad
        $pdf->SetXY($xStart, $y);
        $pdf->CellFit($ancho_linea2 + 17, $tamanio_linea - 1, decode_utf8('Cantidad: '), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea - 1, 'Q. ' . $registro[0]['total'] . '   N.Docto ' . $registro[0]['numdoc'], 0, 0, 'L', 0, '', 1, 0);
        $y += 4.5;

        // linea 6 - Fecha Operación
        $pdf->SetXY($xStart, $y);
        $pdf->CellFit($ancho_linea2 + 17, $tamanio_linea - 1, decode_utf8('Fecha Operación: '), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea - 1, setdatefrench($registro[0]['fechadoc']), 0, 0, 'L', 0, '', 1, 0);
        $y += 4.5;

        // linea 7 - Operador
        $pdf->SetXY($xStart, $y);
        $pdf->CellFit($ancho_linea2 + 17, $tamanio_linea - 1, decode_utf8('Operador: '), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea - 1, decode_utf8($usuario), 0, 0, 'L', 0, '', 1, 0);
        $y += 7;

        // espacio y concepto (MultiCell)
        $pdf->SetXY($xStart, $y);
        $pdf->MultiCell(85, $tamanio_linea - 4, decode_utf8($registro[0]["concepto"]), 0, 'C');

        // devolver Y actual despues del MultiCell
        return $pdf->GetY();
    };

    // posiciones X para las dos copias (ajustar si es necesario)
    $leftX = 10;
    $rightX = 120;

    // Y inicial para ambas
    $startY = $pdf->GetY();

    // dibujar la copia izquierda y obtener Y final
    $leftEndY = $printBlock($leftX);

    // restablecer Y al inicio para que la copia derecha comience alineada
    $pdf->SetY($startY);

    // dibujar la copia derecha y obtener Y final
    $rightEndY = $printBlock($rightX);

    // mover el cursor debajo de ambas copias (usar la mayor Y)
    $pdf->SetY(max($leftEndY, $rightEndY) + 7);
}
