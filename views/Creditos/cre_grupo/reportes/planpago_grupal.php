<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
include '../../../../src/funcphp/func_gen.php';

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

//se reciben los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];


$strquery = "SELECT CONCAT(usu.nombre,' ',usu.apellido) analista, grup.NombreGrupo, grup.codigo_grupo, grup.direc direccion, crem.DFecDsbls apertura,
SUM(crem.NCapDes) monto_desembolsado, crem.NCiclo, IFNULL((SELECT SUM(KP) FROM CREDKAR cred 
INNER JOIN cremcre_meta cre ON cre.CCODCTA = cred.ccodcta WHERE cre.CCodGrupo=crem.CCodGrupo AND cre.NCiclo=crem.NCiclo AND cre.Cestado='F' AND cred.CTIPPAG='P' AND cred.CESTADO!='X'),0) pagado, ppg.cnrocuo nocuota, ppg.dfecven fecha_cuota, SUM(ppg.ncapita) montocapital, SUM(ppg.nintere) montointeres, SUM(ppg.OtrosPagos) monto_otros, SUM(ppg.SaldoCapital) saldo_kp 
FROM Cre_ppg ppg 
INNER JOIN cremcre_meta crem ON crem.CCODCTA = ppg.ccodcta 
INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli 
INNER JOIN tb_grupo grup ON grup.id_grupos=crem.CCodGrupo 
INNER JOIN tb_usuario usu ON usu.id_usu=crem.CodAnal 
WHERE crem.CCodGrupo='$inputs[0]' AND crem.NCiclo='$inputs[1]' AND crem.Cestado='F' GROUP BY ppg.dfecven";

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
        'dato' => $inputs[0]
    );
    echo json_encode($opResult);
    return;
}


//FIN COMPROBACION
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

printpdf($registro, $info);

function printpdf($datos, $info)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = ($info[0]["emai"]);
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
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 8);
            //$this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 10, 33);

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 7);

            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(1);
            $this->Cell(0, 2, $_SESSION['id'], 0, 1, 'R');

            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(3);
        }

        // Pie de página
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    //aqui va el el cuerpo del reporte
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea = 30;

    //formato
    $pdf->SetFont($fuente, '', 11);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Ciclo '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['NCiclo'] == '' || $datos[0]['NCiclo'] == null) ? ' ' : ($datos[0]['NCiclo'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Nombre de grupo:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $nombreGrupo = ($datos[0]['NombreGrupo'] == '' || $datos[0]['NombreGrupo'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['NombreGrupo'], 'utf-8'));
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $nombreGrupo, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Codigo de Grupo:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $nombreGrupo = ($datos[0]['codigo_grupo'] == '' || $datos[0]['codigo_grupo'] == null) ? ' ' : ($datos[0]['codigo_grupo']);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $nombreGrupo, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Direccion:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $nombreGrupo = ($datos[0]['direccion'] == '' || $datos[0]['direccion'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['direccion'], 'utf-8'));
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $nombreGrupo, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Fecha de Apertura:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['apertura'] == '' || $datos[0]['apertura'] == null || $datos[0]['apertura'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['apertura']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Monto desembolsado:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $nombreGrupo = ($datos[0]['monto_desembolsado'] == '' || $datos[0]['monto_desembolsado'] == null) ? ' ' : ($datos[0]['monto_desembolsado']);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $nombreGrupo, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Saldo Actual:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $saldo_actual = 0;
    $saldo_actual += ($datos[0]['monto_desembolsado'] == '' || $datos[0]['monto_desembolsado'] == null) ? 0 : $datos[0]['monto_desembolsado'];
    $saldo_actual -= ($datos[0]['pagado'] == '' || $datos[0]['pagado'] == null) ? 0 : $datos[0]['pagado'];
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $saldo_actual, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Asesor:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $nombreGrupo = ($datos[0]['analista'] == '' || $datos[0]['analista'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['analista'], 'utf-8'));
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $nombreGrupo, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'b', 10);
    $pdf->CellFit(0, $tamanio_linea, (' '), 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, (' '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(7);


    //cuerpo de la tabla 
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 2, $tamanio_linea, ('Cuotas:'), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('fecha de Cuota:'), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Monto capital:'), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Monto Interes:'), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Otros Montos:'), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, ('Cuota Total:'), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 15, $tamanio_linea, ('Saldo Capital:'), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Ln(6);

    $desembolsado = $datos[0]['monto_desembolsado'];
    $monto_capital = round($desembolsado / (count($datos)), 2);
    $saldo_kp = $desembolsado;
    $i = 0;
    while ($i < count($datos)) {
        $cuota_total = 0;
        $nombreCuota = ($datos[$i]['nocuota'] == '' || $datos[$i]['nocuota'] == null) ? ' ' : $datos[$i]['nocuota'];
        $fecha_cuota = ($datos[$i]['fecha_cuota'] == '' || $datos[$i]['fecha_cuota'] == null || $datos[$i]['fecha_cuota'] == '0000-00-00') ? ' ' : date("d-m-y", strtotime($datos[$i]['fecha_cuota']));
        //$monto_capital = ($datos[$i]['montocapital'] == '' || $datos[$i]['montocapital'] == null) ? ' ' : $datos[$i]['montocapital'];

        //$monto_capital = $moncapital;
        $saldo_kp -= $monto_capital;

        //AJUSTE INICIO
        if ($i == array_key_last($datos) && $saldo_kp != 0) {
            $monto_capital = $monto_capital + $saldo_kp;
            $saldo_kp = 0;
        }
        //AJUSTE FIN

        $monto_interes = ($datos[$i]['montointeres'] == '' || $datos[$i]['montointeres'] == null) ? ' ' : $datos[$i]['montointeres'];
        $monto_otro = ($datos[$i]['monto_otros'] == '' || $datos[$i]['monto_otros'] == null) ? ' ' : $datos[$i]['monto_otros'];

        // $cuota_total += ($datos[$i]['montocapital'] == '' || $datos[$i]['montocapital'] == null) ? ' ' : $datos[$i]['montocapital'];
        $cuota_total += $monto_capital;
        $cuota_total += ($datos[$i]['montointeres'] == '' || $datos[$i]['montointeres'] == null) ? ' ' : $datos[$i]['montointeres'];
        $cuota_total += ($datos[$i]['monto_otros'] == '' || $datos[$i]['monto_otros'] == null) ? ' ' : $datos[$i]['monto_otros'];
        //$saldo_kp = ($datos[$i]['saldo_kp'] == '' || $datos[$i]['saldo_kp'] == null) ? ' ' : $datos[$i]['saldo_kp'];

        $pdf->CellFit($ancho_linea + 2, $tamanio_linea, $nombreCuota, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea + 10, $tamanio_linea, $fecha_cuota, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea + 10, $tamanio_linea, $monto_capital, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea + 10, $tamanio_linea, $monto_interes, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea + 10, $tamanio_linea, $monto_otro, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea + 10, $tamanio_linea, $cuota_total, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea + 15, $tamanio_linea, $saldo_kp, 0, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(5);
        $i++;
    }
    $pdf->CellFit(0, $tamanio_linea, (' '), 'B', 0, 'C', 0, '', 1, 0);

    //fin del reporte
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Estado de cuenta Grupal. " . $datos[0]['codigo_grupo'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
