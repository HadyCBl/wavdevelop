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
    cm.MonSug,cm.TipDocDes,cred.DFECPRO,cred.CCONCEP,SUM(cred.KP) KP,SUM(cred.OTR) OTR,SUM(cred.NMONTO) NMONTO,cm.TipoEnti,
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
            parent::__construct('P', 'mm', 'Letter');
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
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');

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
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 7;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell(0, $tamanio_linea, "NOTA DE CREDITO", 0, 1, 'C');
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, "CLIENTE: " . $registro[0]["idcod_cliente"], 0, 0, 'L');
    $pdf->Cell(0, $tamanio_linea, "NOMBRE:" . Utf8::decode(mb_strtoupper($registro[0]["short_name"], 'utf-8')), 0, 1, 'L');

    $fecha = date("d-m-Y", strtotime($registro[0]["DFECPRO"]));
    $pdf->Cell($ancho_linea2 * 3, $tamanio_linea, "FECHA DE EMISION: " . $fecha, 0, 0, 'L');

    $entidadnombre = ($registro[0]['TipoEnti'] == "GRUP") ? 'GRUPO: ' . $registro[0]['NombreGrupo'] : 'INDIVIDUAL';
    $pdf->CellFit(0, $tamanio_linea, $entidadnombre, 0, 1, 'L', 0, '', 1, 0);

    $pdf->CellFit(0, $tamanio_linea, "DESCRIPCION: " . Utf8::decode($registro[0]["CCONCEP"]), 0, 1, 'L', 0, '', 1, 0);

    $tipdes = ($registro[0]['TipDocDes'] == 'C') ? 'CHEQUE' : (($registro[0]['TipDocDes'] == 'T') ? 'TRANSFERENCIA' : 'EFECTIVO');
    $pdf->CellFit(0, $tamanio_linea, "TIPO DE DESEMBOLSO: " . ($tipdes), 0, 1, 'L', 0, '', 1, 0);

    $pdf->Ln(6);
    $pdf->Cell($ancho_linea2 + 10, $tamanio_linea, "PRESTAMO", 1, 0, 'C');
    $pdf->Cell($ancho_linea2 * 3 + 20, $tamanio_linea, "INTEGRACION DEL PRESTAMO", 1, 0, 'C');
    $pdf->Cell($ancho_linea2 + 10, $tamanio_linea, "MONTOS", 1, 1, 'C');

    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, $registro[0]["CCODCTA"], 'RL', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3 + 20, $tamanio_linea, 'CAPITAL', 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, number_format($registro[0]['NMONTO'], 2, '.', ','), 'RL', 1, 'R', 0, '', 1, 0);

    $sal = $registro[0]["NMONTO"];
    $gastoscredkar = $registro[0]["OTR"];
    $fila = 0;
    $pdf->SetFont($fuente, '', 9);
    while ($gastosflag == true && $fila < count($gastos)) {
        //$mongas = ($gastos[$fila]["tipo_deMonto"] == 1) ? $gastos[$fila]["monto"] : ($gastos[$fila]["MonSug"] * $gastos[$fila]["monto"] / 100);
        $mongas = $gastos[$fila]["monto"];
        $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, " ", 'RL', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 3 + 20, $tamanio_linea, $gastos[$fila]["concepto"], 'RL', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, number_format($mongas, 2, '.', ','), 'RL', 1, 'R', 0, '', 1, 0);
        $fila++;
    }
    //por si no hubiera detalles pero sí hay descuentos
    if ($gastoscredkar > 0 && $fila == 0) {
        $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, " ", 'RL', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 3 + 20, $tamanio_linea, 'DESCUENTOS', 'RL', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, number_format($gastoscredkar, 2, '.', ','), 'RL', 1, 'R', 0, '', 1, 0);
    }
    $sal = $sal - $gastoscredkar;
    $pdf->SetFont($fuente, 'B', 9);

    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea * 3, ' ', 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3 + 20, $tamanio_linea * 3, 'A ENTREGAR', 'RL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea * 3, number_format($sal, 2, '.', ','), 'RL', 1, 'R', 0, '', 1, 0);

    $format_monto = new NumeroALetras();
    $montoletra = $format_monto->toMoney($sal, 2, 'QUETZALES', 'CENTAVOS');

    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea * 2, 'CANTIDAD EN LETRAS: ', 'BTL', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 5, $tamanio_linea * 2, $montoletra, 'BTR', 1, 'L', 0, '', 1, 0);

    $pdf->Ln(25);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, 'ELABORADO POR ', 'T', 0, 'C', 0, '', 1, 0); // cuenta
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, 'CLIENTE', 'T', 1, 'C', 0, '', 1, 0); // cuenta
    // $pdf->Output();

    //USUARIO
    $pdf->Ln(7);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(0, $tamanio_linea + 1, 'USUARIO:' . $_SESSION['nombre'] . ' ' . $_SESSION['apellido'], 0, 0, 'C', 0, '', 1, 0);

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
