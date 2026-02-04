<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');

use Luecano\NumeroALetras\NumeroALetras;
use Micro\Generic\Utf8;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];
$codigo = $archivo[0];

printpdf($codigo, $conexion, $_SESSION['id_agencia'], $db_name_general);

function printpdf($codigo, $conexion, $idagencia, $db_name_general)
{

    $strquery = 'SELECT cred.CODKAR,cli.idcod_cliente,cli.short_name,cli.compl_name,cm.CCODCTA, cm.CCODPRD, 
    cm.MonSug,cm.TipDocDes,cred.DFECPRO,cred.CCONCEP,cred.CNUMING, cm.noPeriodo,SUM(cred.KP) KP,SUM(cred.OTR) OTR,SUM(cred.NMONTO) NMONTO,cm.TipoEnti,
    IFNULL((SELECT NombreGrupo from tb_grupo where id_grupos=cm.CCodGrupo)," ") NombreGrupo
    FROM cremcre_meta cm 
    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cm.CodCli
    INNER JOIN CREDKAR cred ON cred.CCODCTA=cm.CCODCTA
    WHERE cm.CCODCTA="' . $codigo . '" AND cred.CTIPPAG="D" AND cred.CESTADO!="X" GROUP BY cred.CCODCTA';
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

    $consulta = "   SELECT cm.id_fuente_fondo, cm.id_ctb_nomenclatura, cm.debe, cm.haber, cd.id AS id_ctb_diario, cd.fecdoc, cn.ccodcta, cn.cdescrip
    FROM ctb_diario cd
    INNER JOIN ctb_mov cm ON cd.id = cm.id_ctb_diario
    INNER JOIN ctb_nomenclatura cn ON cm.id_ctb_nomenclatura = cn.id
    WHERE cd.cod_aux = '$codigo' AND cd.estado = '1' AND fecdoc = '" . $registro[0]['DFECPRO'] . "';";

    $resultConsulta = mysqli_query($conexion, $consulta);

    if ($resultConsulta && mysqli_num_rows($resultConsulta) > 0) {
        $dataConsulta = [];
        while ($row = mysqli_fetch_assoc($resultConsulta)) {
            $dataConsulta[] = $row;
        }
    }

    $strgastos = 'SELECT cd.*,(SELECT tg.nombre_gasto FROM cre_tipogastos tg INNER JOIN cre_productos_gastos cgg ON cgg.id_tipo_deGasto=tg.id WHERE cgg.id=cd.id_concepto ) concepto 
    FROM credkar_detalle cd 
    INNER JOIN CREDKAR cr ON cr.CODKAR=cd.id_credkar
    WHERE cd.tipo="otro" AND cr.CCODCTA="' . $codigo . '" AND cr.CODKAR=' . $registro[0]["CODKAR"];

    /*     $strgastos = 'SELECT cg.*, cm.CCODPRD, cm.MonSug, cm.CodCli,tipg.nombre_gasto  FROM cremcre_meta cm 
    INNER JOIN cre_productos_gastos cg ON cm.CCODPRD=cg.id_producto 
    INNER JOIN cre_tipogastos tipg ON tipg.id=cg.id_tipo_deGasto
    WHERE cm.CCODCTA="' . $codigo . '" AND cg.tipo_deCobro=1 '; */

    $query = mysqli_query($conexion, $strgastos);
    $gastos[] = [];
    $j = 0;
    $gastosflag = false;
    while ($fil = mysqli_fetch_array($query)) {
        $gastos[$j] = $fil;
        $gastosflag = true;
        $j++;
    }
    //FIN COMPROBACION
    $queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $idagencia);
    $info[] = [];
    $j = 0;
    while ($fil = mysqli_fetch_array($queryins)) {
        $info[$j] = $fil;
        $j++;
    }
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
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
            // $hoy = date("Y-m-d H:i:s");
            // // Logo 
            // $this->Image($this->pathlogoins, 10, 8, 33);
            // $this->SetFont('Arial', 'B', 8);
            // // Título
            // $this->Cell(0, 3, $this->institucion, 0, 1, 'C');

            // $this->SetFont('Arial', '', 7);
            // $this->SetXY(-30, 5);
            // $this->Cell(10, 2, $hoy, 0, 1, 'L');
            // $this->SetXY(-25, 8);
            // $this->Ln(15);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            // $this->SetY(-15);
            // $this->SetFont('Arial', 'I', 8);
            // Número de página
            // $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->AddFont('Calibri', '', 'calibri.php');
    $pdf->AddFont('Calibri', 'B', 'calibrib.php');
    $fuente = "Calibri";
    $tamanio_linea = 6;
    $ancho_linea2 = 30;
    $ejex = 40;
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->Ln(8);
    // $pdf->Cell(0, $tamanio_linea, "Comprobante de desembolso", 0, 1, 'C');
    // $pdf->Ln(5);
    // $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, "CLIENTE No: " . $registro[0]["idcod_cliente"], 0, 0, 'L');
    $pdf->Ln(5);
    // $pdf->Cell(0, $tamanio_linea, Utf8::decode(mb_strtoupper($registro[0]["short_name"], 'utf-8')), 0, 1, 'L'); // NOMBRE DEL CLIENTE

    $fecha = date("d-m-Y", strtotime($registro[0]["DFECPRO"]));
    $fechaSegundos = strtotime($registro[0]['DFECPRO']);
    $meses = array("ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE");

    $pdf->SetX($ejex);
    $pdf->CellFit($ancho_linea2 + 80, $tamanio_linea + 1, "GUATEMALA, " . date("d", $fechaSegundos) . " DE " . $meses[date("n", $fechaSegundos) - 1] . " DE " . date("Y", $fechaSegundos), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, number_format($registro[0]['NMONTO'], 2, '.', ','), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln();
    $pdf->SetX($ejex);
    $pdf->CellFit($ancho_linea2 + 107, $tamanio_linea + 1, "**** " . Utf8::decode(mb_strtoupper($registro[0]["short_name"], 'utf-8')) . " ****", 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln();
    $montototal = $registro[0]['NMONTO'];

    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0]['NMONTO']);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->SetX($ejex);
    $pdf->MultiCell($ancho_linea2 + 105, $tamanio_linea + 1, "**** " . $format_monto->toMoney($decimal[0], 2, '', '') . $res . "/100 ****", 0, 'L');
    // $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, "FECHA DE EMISION: " . $fecha, 0, 0, 'L');

    $pdf->Ln(40);
    $entidadnombre = ($registro[0]['TipoEnti'] == "GRUP") ? 'GRUPO: ' . $registro[0]['NombreGrupo'] : 'INDIVIDUAL';
    $pdf->CellFit(0, $tamanio_linea, 'CREDITO ' . $entidadnombre .": " . $registro[0]['CCODCTA'], 0, 1, 'L', 0, '', 1, 0);
    $pdf->MultiCell(0, $tamanio_linea, Utf8::decode($registro[0]['CCONCEP']), 0, 'L');


    $tipdes = ($registro[0]['TipDocDes'] == 'C') ? 'CHEQUE' : (($registro[0]['TipDocDes'] == 'T') ? 'TRANSFERENCIA' : 'EFECTIVO');
    // $pdf->CellFit(0, $tamanio_linea, "TIPO DE DESEMBOLSO: " . ($tipdes), 0, 1, 'L', 0, '', 1, 0);

    // $pdf->Cell(0, $tamanio_linea, "NUMERO DE DOC: " . $registro[0]['CNUMING'], 0, 'L');

    // $pdf->Cell(0, $tamanio_linea, "CANTIDAD DE CUOTAS: " . $registro[0]['noPeriodo'], 0, 'L');

    // $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea * 1.5, 'DESCRIPCION DEL EGRESO: ', 'BTL', 0, 'L', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea2 * 5, $tamanio_linea * 1.5, Utf8::decode($registro[0]['CCONCEP']), 'BTR', 1, 'L', 0, '', 1, 0);

    // Encabezados de la tabla
    // $pdf->Cell($ancho_linea2 + 10, $tamanio_linea, "CUENTAS", 1, 0, 'C');
    // $pdf->Cell($ancho_linea2 + 50, $tamanio_linea, Utf8::decode("DESCRIPCION INTEGRACION PRÉSTAMO"), 0, 0, 'C');
    // $pdf->Cell($ancho_linea2 + 5, $tamanio_linea, "DEBE", 1, 0, 'C');
    // $pdf->Cell($ancho_linea2 + 5, $tamanio_linea, "HABER", 1, 1, 'C');

    // Inicializa los totales
    $totalDebe = 0;
    $totalHaber = 0;

    // Verifica si hay datos en $dataConsulta
    if (!empty($dataConsulta)) {
        foreach ($dataConsulta as $row) {
            // Suma los valores de debe y haber
            $totalDebe += $row['debe'];
            $totalHaber += $row['haber'];

            //datos en la tabla
            $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, $row['ccodcta'], '', 0, 'C', 0, ' ', 1, 0);
            $pdf->CellFit($ancho_linea2 + 50, $tamanio_linea, Utf8::decode($row['cdescrip']), '', 0, 'L', 0, ' ', 1, 0);
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, number_format($row['debe'], 2, '.', ','), '', 0, 'R', 0, ' ', 1, 0);
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, number_format($row['haber'], 2, '.', ','), '', 1, 'R', 0, ' ', 1, 0);
        }
    } else {
        $pdf->Cell(0, $tamanio_linea, "No hay datos de contabilidad para mostrar.", 0, 1, 'L');
    }

    // Total a entregar
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea * 2, ' ', ' ', 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 50, $tamanio_linea * 2, 'TOTALES', '', 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea * 2, number_format($totalDebe, 2, '.', ','), '', 0, 'R', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea * 2, number_format($totalHaber, 2, '.', ','), '', 1, 'R', 0, ' ', 1, 0);

    // Cantidad en letras
    $format_monto = new NumeroALetras();
    $montoletra = $format_monto->toMoney($totalDebe, 2, 'QUETZALES', 'CENTAVOS');

    // $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea * 1.5, 'CANTIDAD EN LETRAS: ', 'BTL', 0, 'L', 0, ' ', 1, 0);
    // $pdf->CellFit($ancho_linea2 * 5, $tamanio_linea * 1.5, $montoletra, 'BTR', 1, 'L', 0, ' ', 1, 0);
    // $pdf->Output();

    $pdf->Ln(30);
    $pdf->firmas(3, ['ELABORADO POR', 'REVISADO POR', 'AUTORIZADO POR']);

    $pdf->Ln(5);
    //DATOS DE QUIEN AUTORIZA EL CHEQUE
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, 'RECIBIDO POR: ', 0, 0, 'L');
    $pdf->Cell($ancho_linea2 + 50, $tamanio_linea + 1, ' ', 'B', 0, 'R');
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 1, 'DPI: ', 0, 0, 'R');
    $pdf->Cell($ancho_linea2 + 30, $tamanio_linea + 1, ' ', 'B', 0, 'R');
    $pdf->Ln(12);
    $pdf->Cell($ancho_linea2, $tamanio_linea + 1, 'FIRMA: ', 0, 0, 'L');
    $pdf->Cell($ancho_linea2 + 50, $tamanio_linea + 1, ' ', 'B', 0, 'R');
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 1, 'FECHA: ', 0, 0, 'R');
    $pdf->Cell($ancho_linea2 + 30, $tamanio_linea + 1, ' ', 'B', 0, 'R');

    // $pdf->Ln(3);
    // $pdf->SetX(114);
    // $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, Utf8::decode(mb_strtoupper($registro[0]["short_name"], 'utf-8')), 0, 0, 'C');

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
