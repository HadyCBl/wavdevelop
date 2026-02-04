<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');

use Luecano\NumeroALetras\NumeroALetras;
use Micro\Generic\Utf8;

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
    cm.MonSug,cm.TipDocDes,cred.DFECPRO,cred.CCONCEP,cred.KP,cred.OTR,cred.NMONTO,cm.TipoEnti,
    IFNULL((SELECT NombreGrupo from tb_grupo where id_grupos=cm.CCodGrupo)," ") NombreGrupo , cm.noPeriodo AS periodo
    FROM cremcre_meta cm 
    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cm.CodCli
    INNER JOIN CREDKAR cred ON cred.CCODCTA=cm.CCODCTA
    WHERE cm.CCODCTA="' . $codigo . '" AND cred.CTIPPAG="D"';

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
            $hoy = date("Y-m-d H:i:s");

            // Logo 
            //$this->Image($this->pathlogoins, 10, 8, 33);
            $this->SetFont('Arial', 'B', 11);

            // Título
            //$this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->SetFont('Arial', 'B', 10);
            $this->SetXY(-45, 5);
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

    $pdf->AddFont('Calibri', '', 'calibri.php');
    $pdf->AddFont('Calibri', 'B', 'calibrib.php');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Calibri";
    $tamanio_linea = 7;
    $ancho_linea2 = 30;

    $pdf->Ln(15);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->Cell(0, $tamanio_linea, "NOTA DE DESEMBOLSO", 0, 0, 'C');
    $pdf->Ln(9);

    $fecha = date("d-m-Y", strtotime($registro[0]["DFECPRO"]));
    $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, "FECHA DE EMISION: " . $fecha, 0, 0, 'L');
    $pdf->Ln(6);

    $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, "PRESTAMO: " . $registro[0]["idcod_cliente"], 0, 0, 'L');
    $pdf->Ln(6);
    $pdf->Cell(110, $tamanio_linea, "NOMBRE: " . Utf8::decode(mb_strtoupper($registro[0]["short_name"], 'utf-8')), 0, 0, 'L');
    $pdf->Cell(87, $tamanio_linea, "Q " . $registro[0]['NMONTO'], 0, 0, 'C');

    $pdf->Ln(9);
    $pdf->Cell(0, $tamanio_linea, Utf8::decode(mb_strtoupper("RESEPCIÓN DE CREDITO A ", 'utf-8')) . Utf8::decode(mb_strtoupper($registro[0]["short_name"], 'utf-8')) . ", PARA UN PERIODO", 0, 0, 'L');
    $pdf->Ln(6);
    $pdf->Cell(0, $tamanio_linea, "DE " . $registro[0]['periodo'] . " MESES, SEGUN CONTRATO FIRMADO CON LA COOPERATIVA.", 0, 0, 'L');

    $format_monto = new NumeroALetras();
    $montoletra = $format_monto->toMoney($registro[0]['NMONTO'], 2, 'QUETZALES', 'CENTAVOS');
    $pdf->Ln(8);

    $pdf->Cell(0, $tamanio_linea, "Cantidad en Letras", 0, 0, 'L');
    $pdf->Ln(3);

    $pdf->Cell(130, $tamanio_linea * 2, Utf8::decode($montoletra), 0, 0, 'L');
    $pdf->Cell(67, $tamanio_linea * 2, "TOTAL: Q " . $registro[0]['NMONTO'], 0, 0, 'C');
    $pdf->Ln(20);

    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, 'Firma Autorizado ', 'T', 0, 'C', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, 'Firma Recibido', 'T', 1, 'C', 0, '', 1, 0); // cuenta

    //USUARIO
    $pdf->Ln(4);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit(0, $tamanio_linea + 1, Utf8::decode('USUARIO: ' . $_SESSION['nombre']) . ' ' . Utf8::decode($_SESSION['apellido']), 0, 0, 'C', 0, '', 1, 0);

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
