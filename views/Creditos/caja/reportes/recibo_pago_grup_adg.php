<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
date_default_timezone_set('America/Guatemala');

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[3];

$idgrupo = $inputs[0];
$doc = $inputs[1];
$ciclo = $inputs[2];
/* if (array_key_exists(2, $inputs)) {
    $idgrupo = $inputs[2];
} else {
    $idgrupo = $datos[3][0];
} */

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
printpdf($doc, $idgrupo, $ciclo, $conexion,$info);

function printpdf($documento, $codgrupo, $ciclo, $conexion,$info)
{
    $strquery = "SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,crem.CCodGrupo,crem.NCiclo,crem.noPeriodo, cli.short_name,crem.CodCli,crem.CodAnal,crem.DFecApr,crem.DFecVen,crem.MonSug, cred.* FROM CREDKAR cred 
    INNER JOIN cremcre_meta crem ON crem.CCODCTA=cred.CCODCTA
    INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
    INNER JOIN tb_grupo gru ON gru.id_grupos=crem.CCodGrupo
    WHERE cred.CESTADO!='X' AND cred.CNUMING='" . $documento . "' AND crem.CCodGrupo=" . $codgrupo . " AND crem.NCiclo=" . $ciclo . "";

    $query = mysqli_query($conexion, $strquery);
    $registro[] = [];
    
    $j = 0;
    $flag = false;
    while ($fil = mysqli_fetch_array($query)) {
        $registro[$j] = $fil;
        $flag = true;
        $j++;
    }
    //COMPROBACION: SI SE ENCONTRARON REGISTROS
    if ($flag == false) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'No se encontraron datos',
            'dato' => $strquery
        );
        echo json_encode($opResult);
        return;
    }
    //FIN COMPROBACION

/*     $oficina = "Coban";
    $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    $direccionins = "Canton vipila zona 1";
    $emailins = "fape@gmail.com";
    $telefonosins = "502 43987876";
    $nitins = "1323244234";
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../../includes/img/fape.jpeg"; */
    $oficina = utf8_decode($info[0]["nom_agencia"]);
    $institucion = utf8_decode($info[0]["nomb_comple"]);
    $direccionins = utf8_decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
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

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos)
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
            $this->datos = $datos;
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 9);

            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'REPORTE DE INGRESOS POR CLIENTE', 0, 1, 'C');
            $this->Ln(2);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 40;

            $this->Cell($ancho_linea, 6, 'NOMBRE DEL GRUPO:', '', 0, 'R');
            $this->Cell($ancho_linea * 2, 6, mb_strtoupper($this->datos[0]["NombreGrupo"],'utf-8'), 'B', 0, 'L');

            $this->Cell($ancho_linea, 6, 'CODIGO DE GRUPO:', '', 0, 'R');
            $this->Cell($ancho_linea, 6, $this->datos[0]["codigo_grupo"], 'B', 0, 'L');

            $this->Cell($ancho_linea, 6, 'CICLO:', '', 0, 'R');
            $this->Cell($ancho_linea / 2, 6,  $this->datos[0]["NCiclo"], 'B', 1, 'L');

            $this->Cell($ancho_linea, 6, 'APERTURA:', '', 0, 'R');
            $this->Cell($ancho_linea, 6, $this->safeDate($this->datos[0]["DFecApr"]), 'B', 0, 'L');

            $this->Cell($ancho_linea * 2, 6, 'VENCIMIENTO:', '', 0, 'R');
            $this->Cell($ancho_linea, 6, $this->safeDate($this->datos[0]["DFecVen"]), 'B', 0, 'L');

            $this->Cell($ancho_linea, 6, 'MESES:', '', 0, 'R');
            $this->Cell($ancho_linea / 2, 6, $this->datos[0]["noPeriodo"], 'B', 1, 'L');

            $this->Cell($ancho_linea, 6, 'NO. RECIBO:', '', 0, 'R');
            $this->Cell($ancho_linea, 6, $this->datos[0]["CNUMING"], 'B', 0, 'L');

            $this->Cell($ancho_linea * 2, 6, 'FECHA DE RECIBO:', '', 0, 'R');
            $this->Cell($ancho_linea, 6, $this->safeDate($this->datos[0]["DFECPRO"]), 'B', 1, 'L');

               $boleta = isset($this->datos[0]["boletabanco"]) ? trim((string)$this->datos[0]["boletabanco"]) : '';
            if ($boleta !== '') {
                $this->Cell($ancho_linea, 6, 'NO. BOLETA BANCO:', '', 0, 'R');
                $this->Cell($ancho_linea, 6, $boleta, 'B', 0, 'L');
            }
            $this->Ln(10);


            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 30;
            $this->Cell($ancho_linea / 3, 5, 'No.', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'CODIGO CREDITO', 'B', 0, 'L');
            $this->Cell($ancho_linea * 3 + 3, 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'L');
            $this->Cell($ancho_linea - 10, 5, 'MONTO OTORGADO', 'B', 0, 'R');
            $this->Cell($ancho_linea - 10, 5, 'CAPITAL', 'B', 0, 'R');
            $this->Cell($ancho_linea - 10, 5, 'INTERES', 'B', 0, 'R');
            $this->Cell($ancho_linea - 10, 5, 'MORA', 'B', 0, 'R');
            $this->Cell($ancho_linea - 10, 5, 'AHORRO', 'B', 0, 'R');
            $this->Cell($ancho_linea - 10, 5, 'OTROS', 'B', 0, 'R');
            $this->Cell($ancho_linea - 10, 5, 'TOTAL', 'B', 1, 'R');
            $this->Ln(1);
        }

        // Pie de página
        private function safeDate($date)
        {
            // Return empty string for null/empty or invalid dates to avoid passing null to strtotime()
            if (empty($date) || $date === '0000-00-00') {
                return '';
            }
            $ts = @strtotime($date);
            if ($ts === false) {
                return '';
            }
            return date("d-m-Y", $ts);
        }

        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo 
            //$this->Image($this->pathlogo, 175, 279, 28);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $registro);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 8);

    $fila = 0;
    while ($fila < count($registro)) {
        $codcta = $registro[$fila]["CCODCTA"];
        $namecli =  $registro[$fila]["short_name"];
        $codcli = $registro[$fila]["CodCli"];
        $monapr =  $registro[$fila]["MonSug"];
        $cap =  $registro[$fila]["KP"];
        $int = $registro[$fila]["INTERES"];
        $mor =  $registro[$fila]["MORA"];
        $ahorro =  $registro[$fila]["AHOPRG"];
        $otros = $registro[$fila]["OTR"];
        $total =  $registro[$fila]["NMONTO"];

        $pdf->CellFit($ancho_linea2 / 3, $tamanio_linea + 1, $fila + 1, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $codcta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 3 + 3, $tamanio_linea + 1, mb_strtoupper($namecli,'utf-8'), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($monapr, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($cap, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($int, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($mor, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($ahorro, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($otros, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($total, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        $fila++;
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $sum_montos = array_sum(array_column($registro, "MonSug"));
    $sum_cappag = array_sum(array_column($registro, "KP"));
    $sum_intpag = array_sum(array_column($registro, "INTERES"));
    $sum_morpag = array_sum(array_column($registro, "MORA"));
    $sum_ahoprg = array_sum(array_column($registro, "AHOPRG"));
    $sum_otro = array_sum(array_column($registro, "OTR"));
    $sum_total = array_sum(array_column($registro, "NMONTO"));

    $pdf->CellFit($ancho_linea2 * 4 + 13, $tamanio_linea + 1, ' ', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($sum_montos, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($sum_cappag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($sum_intpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($sum_morpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($sum_ahoprg, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($sum_otro, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, number_format($sum_total, 2, '.', ','), 'T', 1, 'R', 0, '', 1, 0);

    //USUARIO
    $pdf->Ln(7);
    $pdf->SetFont($fuente, '', 8);
    $pdf->CellFit(0, $tamanio_linea + 1, 'USUARIO:'.utf8_decode(utf8_decode($_SESSION['nombre'].' '.$_SESSION['apellido'])), 0, 0, 'C', 0, '', 1, 0);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "ComprobanteGrupal",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
