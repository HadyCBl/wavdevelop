<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
header('Content-Type: text/html; charset=utf-8');
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';

use Luecano\NumeroALetras\NumeroALetras;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
// date_default_timezone_set('America/Guatemala');
$data = $_POST['datosval'];
$input = $data[0];
$usuCli = $input[0];
$codCu = $input[1];
$extra = $data[3];
$codusu = $extra[0];

printpdf($conexion, $codusu, $usuCli, $codCu, $db_name_general);

function printpdf($conexion, $codusu, $usuCli, $codCu, $db_name_general)
{
    //Informacion de la entidad 
    $strquery = "SELECT * FROM $db_name_general.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop
    INNER JOIN tb_usuario usu ON usu.id_agencia = ag.id_agencia
    WHERE usu.id_usu =" . $codusu;
    $query = mysqli_query($conexion, $strquery);
    $aux = mysqli_error($conexion);
    if ($aux) {
        echo json_encode(['Error al consultar info de la institucion', '0']);
        $conexion->rollback();
        return;
    }
    $info[] = [];

    $j = 0;
    $flag = false;

    while ($fil = mysqli_fetch_array($query)) {
        $info[$j] = $fil;
        $flag = true;
        $j++;
    }

    //Plan de pago editado 
    //Plan de pago editado 
    $queryInf = mysqli_query($conexion, "SELECT pagos.Id_ppg AS id, pagos.dfecven AS fecha, pagos.Cestado, pagos.cnrocuo, pagos.ncapita, pagos.nintere, pagos.OtrosPagosPag OtrosPagos, pagos.SaldoCapital, credi.NCapDes, credi.CtipCre
    FROM  Cre_ppg AS pagos 
    INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA
    WHERE credi.Cestado = 'F'  AND credi.ccodcta =" . $codCu);
    $aux = mysqli_error($conexion);
    if ($aux) {
        echo json_encode(['Error al consultar pagos', '0']);
        $conexion->rollback();
        return;
    }
    $infoPP[] = [];

    $j = 0;
    $flagPP = false;

    while ($fil = mysqli_fetch_array($queryInf)) {
        $infoPP[$j] = $fil;
        $flagPP = true;
        $j++;
    }
    //Informacion sobre el tipo de credito
    $tipoCre = mysqli_query($conexion, "SELECT descr FROM $db_name_general.tb_credito WHERE abre = '" . $infoPP[0]['CtipCre'] . "'");
    $aux = mysqli_error($conexion);
    if ($aux) {
        echo json_encode(['Error al consultar tipo de credito', '0']);
        $conexion->rollback();
        return;
    }
    $row = $tipoCre->fetch_assoc();
    $res = $row['descr'];


    $oficina = utf8_decode($info[0]["nom_agencia"]);
    $institucion = utf8_decode($info[0]["nomb_comple"]);
    $direccionins = utf8_decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . ' y ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];


    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        //atributos de la clase
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $rango;
        public $tipocuenta;
        public $saldoant;
        public $datos;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
        }

        // Cabecera de página
        function Header()
        {
            $hoy = date("Y-m-d H:i:s");
            // Logo 
            $this->Image($this->pathlogoins, 10, 8, 33);
            $this->SetFont('Arial', 'B', 8);
            //Fecha
            $this->SetFont('Arial', '', 7);
            $this->SetXY(-30, 5);

            $this->Cell(10, 2, $hoy, 0, 1, 'L');
            $this->SetXY(-25, 8);
            $this->Ln(15);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    //Configuracion para generar el pdf
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $fuente = "Arial";
    $tamanio_linea = 7;
    $ancho_linea2 = 15;
    $masY = 3;
    //Datos de la entidad
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell(0, $tamanio_linea, $institucion, 0, 1, 'C');

    $pdf->SetFont($fuente, 'B', 8);
    $pdf->Cell(0, 0, $direccionins, 0, 1, 'C');
    $y = $pdf->GetY();
    $pdf->SetY($y + $masY);
    $pdf->Cell(0, 0, $emailins, 0, 1, 'C');
    $y = $pdf->GetY();
    $pdf->SetY($y + $masY);
    $pdf->Cell(0, 0, "Tel: " . $telefonosins, 0, 1, 'C');
    $y = $pdf->GetY();
    $pdf->SetY($y + $masY);
    $pdf->Cell(0, 0, "Nit: " . $nitins, 0, 1, 'C');
    $pdf->Ln(6);
    //Encavezado cliente
    $pdf->SetFont($fuente, 'B', 14);
    $pdf->Cell(0, $tamanio_linea, "Plan de pago - " . utf8_decode($res), 0, 1, 'L');

    //Datos del cliente
    $y = $pdf->GetY();
    $pdf->SetY($y + 2);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(60, 0, "No. Cuenta: " . $codCu, 0, 0, 'L');
    $pdf->Cell(0, 0, (strtoupper(utf8_decode($usuCli))), 0, 0, 'L'); // Decodificador para haceptar tildes y ñ
    $y = $pdf->GetY();
    $pdf->SetY($y + 5);
    //$pdf->Cell(22,0, 'Fecha de pago: '.(date("d/m/Y",strtotime(($infoPP[0]['fecha'])), 0, 0, 'L')));
    $pdf->Cell(22, 0, 'Fecha de pago: ' . date("d/m/Y", strtotime($infoPP[0]['fecha'])), 0, 0, 'L');

    $x = $pdf->GetX();
    $pdf->SetX($x + 35);

    $pdf->Cell(0, 0, 'Monto: Q ' . number_format($infoPP[0]['NCapDes'], 2), 0, 0, 'L');
    $pdf->Ln(6);

    //$pdf->Cell(100, 10 ,'No.'.$infoPP[0]['id'], 1, 1,'L');
    // Set border color to green
    $pdf->SetDrawColor(0, 128, 0);
    $pdf->SetFont('Times', 'B', 12);
    // Headers
    // Calculate page width and content width to center
    $pageWidth = $pdf->GetPageWidth();
    $contentWidth = 96; // Total width of all cells (22+18+28+28)
    $leftMargin = ($pageWidth - $contentWidth) / 2;
    $pdf->SetX($leftMargin);

    $pdf->SetX($leftMargin);
    $pdf->CellFit(22, 7, 'Fecha', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(18, 7, 'No Cuota', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(28, 7, 'Cuota', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(28, 7, 'Saldo', 1, 1, 'C', 0, '', 1, 0);
    $saldointeres = array_sum(array_column($infoPP, 'nintere'));
    $totalFilas = count($infoPP);
    for ($con = 0; $con < $totalFilas; $con++) {
        $pdf->SetX($leftMargin);
        $fill = false;
        if ($con % 2 == 0) {
            $pdf->SetFillColor(224, 235, 255);
            $fill = true;
        }

        $cuota = ($infoPP[$con]["ncapita"] + $infoPP[$con]["nintere"] + $infoPP[$con]["OtrosPagos"]);
        $pdf->SetFont('Times', '', 12);
        $pdf->SetDrawColor(0, 128, 0);
        $pdf->CellFit(22, 7, date("d/m/Y", strtotime($infoPP[$con]["fecha"])), 'RL', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit(18, 7, $infoPP[$con]["cnrocuo"], 'RL', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit(28, 7, 'Q ' . number_format($cuota, 2, '.', ','), 'RL', 0, 'C', $fill, '', 1, 0);
        $saldocap = $infoPP[$con]["SaldoCapital"];

        $saldointeres = $saldointeres - $infoPP[$con]['nintere'];

        $saldocap_formatted = number_format($saldocap, 2, '.', ',');
        $saldoint_formatted = number_format($saldointeres, 2, '.', ',');
        $saldoTotal = $saldocap + $saldointeres;
        $pdf->CellFit(28, 7, 'Q ' . number_format($saldoTotal, 2, '.', ','), 'RL', 1, 'C', $fill, '', 1, 0);
    }

    // Bottom border line
    $pdf->SetX($leftMargin);
    $pdf->SetDrawColor(0, 128, 0);
    $pdf->CellFit(96, 3, ' ', 'T', 1, 'R', 0, '', 1, 0);

    // Totals
    $pdf->SetX($leftMargin);
    $pdf->CellFit(40, 7, 'TOTALES', 0, 0, 'C', 0, '', 1, 0);
    $sumkp = array_sum(array_column($infoPP, 'ncapita'));
    $sumint = array_sum(array_column($infoPP, 'nintere'));
    $sumotr = array_sum(array_column($infoPP, 'OtrosPagos'));
    $pdf->CellFit(28, 7, 'Q ' . number_format($sumkp + $sumint + $sumotr, 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(10);

    $pdf->Cell(35, 7, decode_utf8('Número de cuenta a depositar:'), 0, 1, 'L', false);
    $pdf->Cell(35, 7, 'SOLUCIONES FEFRAN S.A.: 366907301', 0, 1, 'L', false);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Nota de desembolso",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
