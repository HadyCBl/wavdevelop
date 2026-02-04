<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';

$data = $_POST['datosval'];
$input = $data[0];
$usuCli = $input[0];
$codCu = $input[1];
$extra = $data[3];
$codusu = $extra[0];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ INFO DE LA INSTITUCION +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ DATOS DEL CREDITO ++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$queryInf = mysqli_query($conexion, "SELECT credi.CCODCTA AS ccodcta, cli.short_name AS nombre, credi.NCapDes AS montodes,credi.DFecDsbls fecdes from tb_cliente AS cli 
    INNER JOIN cremcre_meta AS credi ON cli.idcod_cliente = credi.CodCli
    WHERE  credi.ccodcta =" . $codCu);
$infocredito[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryInf)) {
    $infocredito[$j] = $fil;
    $j++;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++ PLAN DE PAGO EDITADO ++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$queryInf = mysqli_query($conexion, "SELECT pagos.Id_ppg AS id, pagos.dfecven AS fecha, pagos.Cestado, pagos.cnrocuo, pagos.ncapita, pagos.nintere, pagos.OtrosPagos, pagos.SaldoCapital, credi.NCapDes, credi.CtipCre
    FROM  Cre_ppg AS pagos 
    INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA
    WHERE credi.Cestado = 'F'  AND credi.ccodcta =" . $codCu);
$aux = mysqli_error($conexion);
if ($aux) {
    echo json_encode(['Error al consultar pagos', '0']);
    return;
}
$infoPP[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryInf)) {
    $infoPP[$j] = $fil;
    $j++;
}
printpdf($info, $infoPP, $infocredito);

function printpdf($info, $infoPP, $infocredito)
{
    class PDF extends FPDF
    {
        //atributos de la clase
        public $info;
        public $infocredito;

        public function __construct($info, $infocredito)
        {
            parent::__construct();
            $this->info = $info;
            $this->infocredito = $infocredito;
        }
        function Header()
        {
            $hoy = date("Y-m-d H:i:s");
            // Arial bold 15
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(0, 0, '', 0, 1, 'L', $this->Image('../../../../' . $this->info[0]["log_img"], 170, 12, 19));
            $this->Cell(0, 0, '', 0, 1, 'L', $this->Image('../../../../includes/img/logomicro.png', 20, 12, 19));
            //pruebas
            $this->Cell(190, 3, '' . $this->info[0]["nomb_comple"], 0, 1, 'C');
            $this->Cell(190, 3, '' . $this->info[0]["nomb_cor"], 0, 1, 'C');
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(190, 3, $this->info[0]["muni_lug"], 0, 1, 'C');
            $this->Cell(190, 3, 'Email:' . $this->info[0]["emai"], 0, 1, 'C');
            $this->Cell(190, 3, 'Tel:' . $this->info[0]["tel_1"] . " Y " . $this->info[0]["tel_2"], 0, 1, 'C');
            $this->Cell(190, 3, 'NIT:' . $this->info[0]["nit"], 0, 1, 'C');
            $this->Cell(0, 3, mb_strtoupper($this->info[0]["nom_agencia"], 'utf-8'), 'B', 1, 'C');
            $this->SetFont('Arial', '', 7);
            $this->SetXY(-30, 5);
            $this->Cell(10, 2, $hoy, 0, 1, 'L');
            $this->SetXY(-25, 8);
            $this->Ln(25);
            //************ */
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(50, 5, 'Plan de Pago', 0, 1, 'L');
            //   DATOS DEL CREDITO
            $this->SetFont('Arial', '', 9);
            $this->Cell(70, 5, 'Codigo Credito : ' . $this->infocredito[0]['ccodcta'], 0, 0, 'L');
            $this->Cell(0, 5, 'Cliente : ' . (mb_strtoupper($this->infocredito[0]['nombre'], 'utf-8')), 0, 1, 'L');
            $this->Cell(70, 5, 'Otorgamiento : ' . date("d-m-Y", strtotime($this->infocredito[0]['fecdes'])), 0, 0, 'L');
            $this->Cell(40, 5, 'Monto : Q ' . number_format($this->infocredito[0]['montodes'], 2), 0, 1, 'L');
            $this->Ln(5);
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

    $pdf = new PDF($info, $infocredito);
    //Configuracion para generar el pdf
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $fuente = "Courier";
    $ancholinea = 20;
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell($ancholinea * 2, 7, ' ', 0, 0, 'L');
    $pdf->Cell($ancholinea, 7, 'No Cuota', 1, 0, 'L');
    $pdf->Cell($ancholinea * 2, 7, 'Fecha', 1, 0, 'L');
    $pdf->Cell($ancholinea * 2, 7, 'Cuota', 1, 1, 'L');
    $pdf->SetFillColor(224, 235, 255);
    $pdf->SetTextColor(0);
    $fill = false;
    $totalFilas = count($infoPP); //Total de filas 
    $pdf->SetFont($fuente, '', 10);
    $sumcuotas = 0;
    for ($con = 0; $con < $totalFilas; $con++) {
        $cuota = ($infoPP[$con]["ncapita"] + $infoPP[$con]["nintere"] + $infoPP[$con]["OtrosPagos"]);
        $sumcuotas += $cuota;
        $pdf->CellFit($ancholinea * 2, 7, ' ', 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancholinea, 7, $infoPP[$con]["cnrocuo"], 'RL', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit($ancholinea * 2, 7, date("d/m/Y", strtotime($infoPP[$con]["fecha"])), 'RL', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($ancholinea * 2, 7, 'Q ' . number_format($cuota, 2), 'RL', 1, 'R', $fill, '', 1, 0);
        $fill = !$fill;
    }
    $pdf->CellFit($ancholinea * 2, 3, ' ', 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancholinea * 5, 3, ' ', 'T', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancholinea * 5, 7, 'TOTAL', 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancholinea * 2, 7, 'Q ' . $sumcuotas, 0, 1, 'R', $fill, '', 1, 0);

    $pdf->firmas(1, [' ']);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Plan de pagos generado correctamente',
        'namefile' => "Resumen plan de pagos",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
