<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
session_start();

use Luecano\NumeroALetras\NumeroALetras;
use Micro\Generic\Utf8;

date_default_timezone_set('America/Guatemala');
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
//se recibe los datos
$datos = $_POST["datosval"];

//Informacion de datosval 
$inputs = $datos[0];
$archivo = $datos[3];

//Informacion de archivo 
$usuario = $_SESSION['nombre'];
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general);

function obtenerDescripcionTipCuota($tipcuota) {
    $tipos = [
        '1M'  => 'Mensual',
        '2M'  => 'Bimensual',
        '3M'  => 'Trimestre',
        '4M'  => 'Cuatrimestre',
        '5M'  => 'Quinquemestre',
        '6M'  => 'Semestral',
        '1D'  => 'Diario',
        '7D'  => 'Semanales',
        '15D' => 'Quincenal',
        '1C'  => 'Mensual',
        '14D' => 'Catorcenal',
    ];

    return $tipos[$tipcuota] ?? NULL;
}
function printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general)
{
    $consulta = "SELECT ck.DFECPRO AS fechadoc, CAST(ck.DFECSIS as Date) AS fechaaplica, cl.short_name AS nombre, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
	ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
	(IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
    (IFNULL((SELECT SUM(nintere) FROM Cre_ppg WHERE ccodcta = cm.CCODCTA GROUP BY ccodcta), 0) - (SELECT IFNULL(SUM(ck3.INTERES), 0) FROM CREDKAR ck3 WHERE ck3.CTIPPAG = 'P' AND ck3.CCODCTA = cm.CCODCTA AND ck3.CNROCUO <= '" . $numerocuota . "')) AS diferencia_interes,
	((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA)-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<='" . $numerocuota . "')) AS saldo, cl.no_identifica AS dpi, cm.NtipPerC AS tipcuota, cm.MonSug AS montosu, cm.DFecVig AS fecvig, cm.DFecVen AS fecven, cpp.dfecven AS ppg_dfecven,IF(CAST(ck.DFECSIS AS DATE) <= cpp.dfecven, 'Pago puntual', 'Pago impuntual') AS estado_pago, cpp.ncapita AS capital_ppg, cpp.nintere AS interes_ppg, ck.MORA AS morapendiente, ck.NMONTO AS montopagado
    FROM cremcre_meta cm
    INNER JOIN CREDKAR ck ON cm.CCODCTA=ck.CCODCTA
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
    INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo=ctf.id
    INNER JOIN Cre_ppg cpp ON cpp.CCODCTA = ck.CCODCTA AND cpp.CNROCUO = ck.CNROCUO
    WHERE cm.CCODCTA='" . $codigocredito . "' AND ck.CNUMING='" . $cnuming . "'";
    $datos = mysqli_query($conexion, $consulta);
    $aux = mysqli_error($conexion);
    if ($aux) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'Fallo en la consulta de los datos',
            'dato' => $datos
        );
        echo json_encode($opResult);
        return;
    }
    if (!$datos) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'No se logro consultar los datos',
            'dato' => $datos
        );
        echo json_encode($opResult);
    }
    $registro[] = [];
    $j = 0;
    $flag = false;
    while ($fila = mysqli_fetch_array($datos)) {
        $registro[$j] = $fila;
        $flag = true;
        $j++;
    }
    //COMPROBACION: SI SE ENCONTRARON REGISTROS
    if ($flag == false) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'No se encontraron datos',
            'dato' => $datos
        );
        echo json_encode($opResult);
        return;
    }
    //FIN COMPROBACION

    //PARA LA CABEZERA 
    mysqli_next_result($conexion);
    $queryins = mysqli_query($conexion, "SELECT * FROM jpxdcegu_bd_general_coopera.info_coperativa ins
        INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
    
    $info = [];
    $j = 0;
    
    while ($fil = mysqli_fetch_array($queryins)) {
        $info[$j] = $fil;
        $j++;
    }
    
    if ($j == 0) {
        echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
        return;
    }
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = isset($info[0]["nomb_cor"]) ? Utf8::decode($info[0]["nomb_cor"]) : 'Nombre no disponible';

    $hoy = date("d-m-Y H:i:s");
    $direccionins = "12 Av. 2-23 zona 8 de Mixco Pinares de San Cristobal";
    
    class PDF extends FPDF
    {
        // Atributos de la clase
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
    
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    //120 PARA TODA LAS LINEAS SEGUN EL ROLLO
    // NUMERO DE DOCUMENTO
    $pdf->SetFont($fuente, 'B', 40); 
    // if ($rutalogoins) {
    //     $pdf->Image($rutalogoins,40, 10, 110); 
    //     $pdf->Ln(55); 
    // }

    $pdf->CellFit(0, $tamanio_linea, " ", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(30);

    $pdf->CellFit(0, $tamanio_linea, "Servicios Financieros Jireh S.A.", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40); 
    $pdf->CellFit(0, $tamanio_linea, Utf8::decode("Barrio Poroma, Zona 2, Tecpán Guatemala,"), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $pdf->CellFit(0, $tamanio_linea, "Chimaltenango", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->CellFit(0, $tamanio_linea, Utf8::decode("Teléfonos: 4514 - 8218"), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->CellFit(0, $tamanio_linea, "Email: info@jirehsa.com.gt", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(22);
    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(9);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea, Utf8::decode('Información de cliente'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(8);
    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(17);

    // CLIENTE
    $pdf->SetFont($fuente, '',40);
    $pdf->CellFit(0, $tamanio_linea ,"Cliente:". Utf8::decode(mb_strtoupper($registro[0][2],'utf-8')), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->CellFit(0, $tamanio_linea ,"DPI: ". $registro[0]['dpi'], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    
    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(9);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea, Utf8::decode('Detalles de crédito'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(8);
    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(17);

    // DATOS DEL CREDITO
    $pdf->SetFont($fuente, '',40);
    $pdf->CellFit(0, $tamanio_linea ,"Credito No.: ". $registro[0][3], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $descripcionCuota = obtenerDescripcionTipCuota($registro[0]['tipcuota']);
    $pdf->CellFit(0, $tamanio_linea ,"Modalidad de pago: ". ($descripcionCuota ?? 'NULL'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->CellFit(0, $tamanio_linea ,"Monto: ". $registro[0]['montosu'], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->CellFit(0, $tamanio_linea ,"Fecha de inicio: ". $registro[0]['fecvig'], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->CellFit(0, $tamanio_linea ,"Fecha de vencimiento: ". $registro[0]['fecven'], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(15);

    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(9);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea, Utf8::decode('Recibo No. ').$registro[0][4], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(8);
    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(17);

    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,"Concepto: ", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $pdf->MultiCell(0, $tamanio_linea + 7, Utf8::decode(mb_strtoupper($registro[0][5],'utf-8')), 0, 'C');
    $pdf->Ln(13);

    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(9);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea, Utf8::decode('Monto Pagado: Q. ').$registro[0]["montopagado"], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(8);
    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(17);

    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,Utf8::decode("Mora Pendiente: "), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $pdf->CellFit(0, $tamanio_linea ,'Q. ' . number_format($registro[0][9], 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);


    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,Utf8::decode("Saldo de crédito: "), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $total = $registro[0]['capital_ppg'] + $registro[0]['diferencia_interes'];
    $total2 = $total + $registro[0]['saldo'];
    $total3 = $total2 + $registro[0]['morapendiente'];
    $totalcancelar = $registro[0]['saldo'] + $registro[0]['diferencia_interes'];
    $pdf->CellFit(0, $tamanio_linea ,"Q. " . number_format($registro[0]['saldo'], 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,"Total a cancelar: ", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $pdf->CellFit(0, $tamanio_linea ,"Q. " . number_format($totalcancelar, 2), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,Utf8::decode("Estado de crédito: "), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $morapendiente = (float)$registro[0]['morapendiente'];
    $mensajeMora = $morapendiente > 0 ? 'Pago en mora' : 'Pago puntual';
    $pdf->CellFit(0, $tamanio_linea ,$mensajeMora, 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);

    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(9);

    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,"Cajero: ", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $pdf->CellFit(0, $tamanio_linea ,Utf8::decode(Utf8::decode($usuario)), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,"Sucursal: ", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40);
    $pdf->CellFit(0, $tamanio_linea ,$oficina, 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,"Fecha y hora: ", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(13);
    $pdf->SetFont($fuente, '',40); // 
    $pdf->CellFit(0, $tamanio_linea + 3, $hoy, 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(17);

    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(17);

    $pdf->MultiCell(0, $tamanio_linea + 7, Utf8::decode('Gracias por confiar en nosotros.'), 0, 'C');
    $pdf->Ln(17);

    $pdf->CellFit(0, $tamanio_linea , ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(17);
    $pdf->SetFont($fuente, 'B',40);
    $pdf->CellFit(0, $tamanio_linea ,"COPIA CLIENTE", 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(17);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();
    $fechaHoraActual = date("Y-m-d_H-i-s");
    $nombre = Utf8::decode(mb_strtoupper($registro[0][2],'utf-8'));
    $palabras = explode(' ', $nombre);
    $primer_palabra = isset($palabras[0]) ? $palabras[0] : ''; // Si la cadena no tiene ninguna palabra, devolverá una cadena vacía
    $segunda_palabra = isset($palabras[1]) ? $palabras[1] : ''; 

    $nombreArchivo =   $primer_palabra."_Recibo_con_fecha_" . $fechaHoraActual;

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => $nombreArchivo,
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}