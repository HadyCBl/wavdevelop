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
$idagencia = $_SESSION['id_agencia'];
$usuario = $_SESSION['id'];
$nombreusuario = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];

printpdf($codigo, $conexion, $idagencia, $db_name_general, $nombreusuario);

function printpdf($codigo, $conexion, $idagencia, $db_name_general, $nombreusuario)
{
    $strquery = 'SELECT cred.CODKAR,cli.idcod_cliente,cli.short_name,cli.compl_name,cm.CCODCTA, cm.CCODPRD, 
    cm.MonSug,cm.TipDocDes,cred.DFECPRO,cred.CCONCEP,SUM(cred.KP) KP,SUM(cred.OTR) OTR,SUM(cred.NMONTO) NMONTO,cm.TipoEnti, cli.no_identifica,
    IFNULL((SELECT NombreGrupo from tb_grupo where id_grupos=cm.CCodGrupo)," ") NombreGrupo, cm.CtipCre,cm.NtipPerC, cred.CNUMING, cm.NCapDes
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

    $codkars = [];
    foreach ($registro as $reg) {
        if (!empty($reg['CODKAR'])) {
            $codkars[] = (int)$reg['CODKAR'];
        }
    }

    // si no hay CODKAR válidos, usamos un IN con 0 para evitar error SQL
    $in = (count($codkars) > 0) ? implode(',', $codkars) : '0';

    $strgastos = 'SELECT cd.monto, cd.id_concepto,
    (SELECT tg.nombre_gasto FROM cre_tipogastos tg 
     INNER JOIN cre_productos_gastos cgg ON cgg.id_tipo_deGasto=tg.id 
     WHERE cgg.id=cd.id_concepto) as concepto 
    FROM credkar_detalle cd 
    INNER JOIN CREDKAR cr ON cr.CODKAR=cd.id_credkar
    WHERE cd.tipo="otro" AND cr.CCODCTA="' . mysqli_real_escape_string($conexion, $codigo) . '" AND cr.CODKAR IN (' . $in . ')';

    $query = mysqli_query($conexion, $strgastos);
    $gastos = [];
    $j = 0;
    $gastosflag = false;
    while ($fil = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
        $gastos[$j] = $fil;
        $gastosflag = true;
        $j++;
    }

    // Agrupar montos por concepto (normalizar a mayúsculas)
    $montos_por_concepto = [];
    foreach ($gastos as $g) {
        if (empty($g) || !isset($g['concepto'])) continue;
        $concept = mb_strtoupper(trim($g['concepto']), 'UTF-8');
        $monto = floatval($g['monto']);
        if (!isset($montos_por_concepto[$concept])) $montos_por_concepto[$concept] = 0.0;
        $montos_por_concepto[$concept] += $monto;
    }

    // montos específicos que se solicitaron
    $monto_papeleria = $montos_por_concepto['PAPELERIA'] ?? 0.0;
    $monto_formulario = $montos_por_concepto['FORMULARIO'] ?? 0.0;

    /*     $strgastos = 'SELECT cg.*, cm.CCODPRD, cm.MonSug, cm.CodCli,tipg.nombre_gasto  FROM cremcre_meta cm 
    INNER JOIN cre_productos_gastos cg ON cm.CCODPRD=cg.id_producto 
    INNER JOIN cre_tipogastos tipg ON tipg.id=cg.id_tipo_deGasto
    WHERE cm.CCODCTA="' . $codigo . '" AND cg.tipo_deCobro=1 '; */

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
            // // Posición: a 1 cm del final
            // $this->SetY(-15);
            // $this->SetFont('Arial', 'I', 8);
            // // Número de página
            // $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $tiposPeridodo = [
        'D' => 'DIARIO',
        'S' => 'SEMANAL',
        'Q' => 'CATORCENAL',
        '15D' => 'QUINCENAL',
        '1M' => 'MENSUAL',
        '2M' => 'BIMENSUAL',
        '3M' => 'TRIMESTRAL',
        '6M' => 'SEMESTRAL',
        '1' => 'ANUAL',
        '0D' => 'OTROS'
    ];

    $tiposcuocredito = [
        'Germa' => 'SOBRE SALDO',
        'Flat' => 'FLAT',
        'Franc' => 'FRANCES',
        'Amer' => 'AMER',
    ];
    $tipformacredito = $tiposcuocredito[$registro[0]['CtipCre']] ?? $registro[0]['CtipCre'];

    $periodo = $tiposPeridodo[$registro[0]['NtipPerC']];

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4;
    $ancho_linea2 = 30;

    $pdf->Ln(15);

    $offset_x = 50;
    $offset_y = 18;

    $pdf->Ln(6);


    $pdf->SetFont($fuente, 'B', 10);
    $fecha = date("d-m-Y", strtotime($registro[0]["DFECPRO"]));
    $entidadnombre = ($registro[0]['TipoEnti'] == "GRUP") ? 'GRUPO: ' . $registro[0]['NombreGrupo'] : 'INDIVIDUAL';
    $tipdes = ($registro[0]['TipDocDes'] == 'C') ? 'CHEQUE' : (($registro[0]['TipDocDes'] == 'T') ? 'TRANSFERENCIA' : 'EFECTIVO');
     
    $pdf->SetX($pdf->GetX() + $offset_x);
    $pdf->Cell(0, $tamanio_linea, mb_strtoupper("DESEMBOLSO/CREDITO/" . $tipformacredito . "/" . $periodo . "/" . $entidadnombre . "/" . $tipdes, 'UTF-8'), 0, 1, '');

    $pdf->SetX($pdf->GetX() + $offset_x);
    $pdf->Cell(0, $tamanio_linea, "Recibo Nu.: " . $registro[0]["CNUMING"] . '. Fecha de Pago: ' . $fecha . ', Agencia:A' . $idagencia, 0, 1, 'L');

    $pdf->SetX($pdf->GetX() + $offset_x);
    $pdf->Cell(0, $tamanio_linea, "Credito Nu.:" . $registro[0]["CCODCTA"] . ' Aso. Nu.:' . $registro[0]["idcod_cliente"], 0, 1, 'L');

    $pdf->SetX($pdf->GetX() + $offset_x);
    $pdf->Cell(0, $tamanio_linea, "Asociado:" . Utf8::decode($registro[0]["short_name"]), 0, 1, 'L');
    // $pdf->Cell(0, $tamanio_linea, "D.P.I: ". $registro[0]["no_identifica"].' Receptor: '.$nombreusuario, 0, 1, 'L');

    $pdf->SetX($pdf->GetX() + $offset_x);
    $pdf->Cell(0, $tamanio_linea, "D.P.I:" . $registro[0]["no_identifica"] . ", Receptor:" . obtenerIniciales($nombreusuario), 0, 1, 'L');
    $pdf->SetX($pdf->GetX() + $offset_x);
    $pdf->Cell(0, $tamanio_linea, 'Valor de credito:Q.:' . $registro[0]["NCapDes"], 0, 1, 'L');
    $sal = $registro[0]["NMONTO"];
    $gastoscredkar = $registro[0]["OTR"];
    $fila = 0;
    $conceptos_por_linea = 2;
    $contador_linea = 0;


    while ($gastosflag == true && $fila < count($gastos)) {
        //$mongas = ($gastos[$fila]["tipo_deMonto"] == 1) ? $gastos[$fila]["monto"] : ($gastos[$fila]["MonSug"] * $gastos[$fila]["monto"] / 100);
        $mongas = $gastos[$fila]["monto"];

        // Ancho de cada celda (dividir el ancho total entre 2)
        $ancho_celda = 50 / $conceptos_por_linea;
         
        $pdf->SetX($pdf->GetX() + $offset_x);

        $pdf->Cell($ancho_celda, $tamanio_linea, $gastos[$fila]["concepto"] . ': ' . number_format($mongas, 2, '.', ','), 0, 0, 'L');

        $contador_linea++;

        // Hacer salto de línea cada 2 conceptos o al final
        if ($contador_linea >= $conceptos_por_linea || $fila == count($gastos) - 1) {
            $pdf->Ln();
            $contador_linea = 0;
        }

        $fila++;
    }

    // Si quedó una línea incompleta, hacer salto
    if ($contador_linea > 0) {
        $pdf->Ln();
    }

    //por si no hubiera detalles pero sí hay descuentos
    if ($gastoscredkar > 0 && $fila == 0) {
        $pdf->SetX($pdf->GetX() + $offset_x);
        $pdf->Cell(0, $tamanio_linea, 'DESCUENTOS: ' . number_format($gastoscredkar, 2, '.', ','), 0, 1, 'L');
    }
     
    $pdf->SetX($pdf->GetX() + $offset_x);
    $sal = $sal - $gastoscredkar;
    $pdf->Cell(0, $tamanio_linea, 'MONTO ENTREGADO: ' . number_format($sal, 2, '.', ','), 0, 1, 'L');
    $pdf->Ln(6);

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

function obtenerIniciales($nombre) {
    $palabras = explode(' ', trim($nombre));
    $iniciales = '';
    foreach ($palabras as $palabra) {
        if (!empty($palabra)) {
            $iniciales .= strtoupper(substr($palabra, 0, 1)) . '';
        }
    }
    return trim($iniciales);
}
