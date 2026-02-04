<?php

/**
 * Reporte de créditos desembolsados
 * @deprecated reporte obsoleto. revisar la nueva implementacion
 *  Micro\Controllers\Reportes\Creditos\DesembolsosController
 */
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
include '../../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
    return;
}

// Se reciben los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$estado = $selects[0][0];
$radios = $datos[2];
$tipo = $_POST["tipo"];
$tipoconsulta = 0;

// Validaciones iniciales
if ($inputs[1] < $inputs[0]) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha final no debe ser menor que la fecha inicial']);
    return;
}
if ($selects[0] == '0') {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar un estado de crédito']);
    return;
}

// Filtro adicional por usuario (caso "CRE_desembol_Filtro")
$codusu = '';
if ($inputs[2] == "CRE_desembol_Filtro") {
    $agencia = $selects[1];
    $codusu = $inputs[3];
    $estado = $selects[0];

    $query2 = "SELECT COUNT(CCODCTA) FROM cremcre_meta 
               WHERE CodAnal=$codusu 
                 AND CODAgencia='$agencia' 
                 AND Cestado ='$estado' 
                 AND (DfecAnal BETWEEN '" . $inputs[0] . "' AND '" . $inputs[1] . "') ";
    $rs = mysqli_query($conexion, $query2) or die(mysqli_error($conexion));
    $resultado = mysqli_fetch_array($rs);
    if ($resultado[0] > 0) {
        // Sí hay datos
        $codusu = " AND cm.CodAnal=$codusu ";
    } else {
        echo json_encode(["status" => 0, 'mensaje' => 'NO HAY DATOS']);
        return;
    }
}

// Armado de filtros
// 1) Filtro tipo de Entidad
$filtroentidad = ($radios[0] == "ALL") ? "" : " AND cm.TipoEnti='" . $radios[0] . "' ";
// 2) Filtro agencia
/* 
   radios[1] puede ser:
   - 'allg' => Consolidado de todas las agencias
   - 'anyg' => (supuestamente: permitir seleccionar 1)
   - '???'
*/
$filtroagencia = ($radios[1] == 'allg')
    ? ''
    : (
        ($radios[1] == 'anyg')
        ? (($selects[1] == "0") ? "" : " AND ag.id_agencia='" . $selects[1] . "' ")
        : ''
    );

// 2.1) Filtro región (se suma con AND, no reemplaza el de agencia)
$regionRadio = $radios[2] ?? 'allregion';
$regionId = isset($selects[2]) ? (int)$selects[2] : 0;

if ($regionRadio === 'anyregion' && $regionId <= 0) {
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Debe seleccionar una región'
    ]);
    return;
}

$filtroregion = ($regionRadio === 'anyregion' && $regionId > 0)
    ? " AND cm.CODAgencia IN (\n          SELECT id_agencia\n          FROM cre_regiones_agencias\n          WHERE id_region = {$regionId}\n      )"
    : "";

// Nombre de región (solo para mostrar en encabezado cuando se aplica el filtro)
$nombre_region = '';
if ($regionRadio === 'anyregion' && $regionId > 0) {
    $qreg = mysqli_query($conexion, "SELECT nombre FROM cre_regiones WHERE id=" . (int)$regionId . " LIMIT 1");
    if ($qreg) {
        $rowReg = mysqli_fetch_assoc($qreg);
        $nombre_region = $rowReg['nombre'] ?? '';
    }
}

// 3) Filtro estado
/*
   NOTA: si es "FG" => traer todos con estado 'F' o 'G'.
         de lo contrario, traer el estado exacto.
*/
$filtrostatus = ($selects[0] == "FG")
    ? " AND (cm.Cestado= 'G' OR cm.Cestado='F') "
    : " AND cm.Cestado='" . $selects[0] . "' ";

// Determinamos la fecha de referencia según el estado
$DFec = "";
switch ($selects[0]) {
    case 'D':
        $DFec = 'DFecAnal';
        break; // estado 'D': en análisis
    case 'E':
        $DFec = 'DFecApr';
        break; // estado 'E': aprobado
    case 'F':
        $DFec = 'DFecDsbls';
        break; // estado 'F': desembolsado
    case 'A':
        $DFec = 'DfecSol';
        break; // estado 'A': solicitud
    case 'G':
        $DFec = 'fecha_operacion';
        break;
    case 'L':
        $DFec = 'fecha_operacion';
        break;
    case 'X':
        $DFec = 'fecha_operacion';
        break;
    case 'FG':
        $DFec = 'DFecDsbls';
        break; // F ó G
}

$consulta = "
    SELECT 
        cm.TipoEnti,
        us.puesto, 
        ag.id_agencia,
        ag.cod_agenc,
        cm.CCODCTA AS cuenta,
        cm.CodCli AS codigocliente,  
        cl.short_name AS nombre,
        cm.Cestado AS estado,
        cm.DFecSol AS fecsolicitud,
        IFNULL(cm.DFecDsbls,'-') AS fecdesembolsado,
        cm.DFecVen AS fecvencimiento,
        cm.MontoSol AS montosoli,
        cm.MonSug AS montoaprobado,
        cm.NCapDes AS montodesembolsado,
        cm.TipDocDes AS tipo,
        cm.DFecDsbls AS fecdes,
        cm.NCiclo AS ciclo,
        -- Los gastos individuales ya NO se cargan aquí en una sola columna
        -- sino que haremos un 'pivot' luego usando credkar_detalle

        IFNULL((SELECT NombreGrupo 
                FROM tb_grupo 
                WHERE id_grupos=cm.CCodGrupo), ' ') AS NombreGrupo,
        IFNULL((SELECT f.descripcion 
                FROM ctb_fuente_fondos f 
                INNER JOIN cre_productos c ON c.id_fondo=f.id 
                WHERE c.id=cm.CCODPRD),' - ') AS fondesc,
        IFNULL((SELECT f.id 
                FROM ctb_fuente_fondos f 
                INNER JOIN cre_productos c ON c.id_fondo=f.id 
                WHERE c.id=cm.CCODPRD),' - ') AS fondoid,
        IFNULL((SELECT descripcion 
                FROM `jpxdcegu_bd_general_coopera`.`tb_cre_periodos` 
                WHERE cod_msplus=cm.NtipPerC),' - ') AS frecuencia,
        IFNULL((SELECT DestinoCredito 
                FROM `jpxdcegu_bd_general_coopera`.`tb_destinocredito` 
                WHERE id_DestinoCredito=cm.Cdescre),' - ') AS destino,
        IFNULL((SELECT descripcionGarantia 
                FROM tb_garantias_creditos gr 
                  INNER JOIN cli_garantia clgr on gr.id_garantia = clgr.idGarantia 
                WHERE gr.id_cremcre_meta =cm.CCODCTA 
                LIMIT 1),' - ') AS garantia,

        prod.nombre AS producto,
        cm.CCodGrupo id_grupos,
        cm.noPeriodo numcuotas,
        CONCAT(us.nombre,' ',us.apellido) AS responsable,
        
        -- Sector y actividad
        IFNULL(sector.SectoresEconomicos, '-') AS sectorEconomico,
        IFNULL(actividad.Titulo, '-') AS actividadEconomica

    FROM cremcre_meta cm
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu
    INNER JOIN tb_agencia ag ON cm.CODAgencia=ag.cod_agenc
    INNER JOIN cre_productos prod ON cm.CCODPRD = prod.id
    INNER JOIN ctb_fuente_fondos ff ON ff.id=prod.id_fondo
    LEFT JOIN jpxdcegu_bd_general_coopera.`tb_sectoreseconomicos` sector 
        ON sector.id_SectoresEconomicos=cm.CSecEco
    LEFT JOIN jpxdcegu_bd_general_coopera.`tb_ActiEcono` actividad 
        ON actividad.id_ActiEcono=cm.ActoEcono

    WHERE (DATE($DFec) BETWEEN '" . $inputs[0] . "' AND '" . $inputs[1] . "')
      $filtrostatus 
      $filtroagencia 
      $filtroentidad 
            $filtroregion
      $codusu
    ORDER BY ff.id, cm.CCodGrupo, cm.DFecDsbls, cm.CCODCTA
";

// Texto de reporte para mostrar en encabezados
$texto_reporte = "REPORTE DE CRÉDITOS DEL PERIODO " . date("d-m-Y", strtotime($inputs[0])) . " AL " . date("d-m-Y", strtotime($inputs[1])) . " ";
$queryest = "SELECT EstadoCredito AS est 
             FROM tb_estadocredito 
             WHERE id_EstadoCredito='" . $selects[0] . "' ";
$resEst = mysqli_query($general, $queryest);
$nomestado = "";
while ($fil = mysqli_fetch_array($resEst)) {
    $nomestado = strtoupper($fil["est"]);
    if ($nomestado == 'VIGENTE') {
        $nomestado = "DESEMBOLSADO";
    }
    $texto_reporte .= " CON ESTADO DE " . $nomestado;
}

// Consultar la agencia
$nom_agencia = "";
$cod_agenc = "";
$rsAgencia = mysqli_query($conexion, "SELECT * FROM tb_agencia WHERE id_agencia='" . $selects[1] . "' ");
while ($fil = mysqli_fetch_array($rsAgencia)) {
    $nom_agencia = strtoupper($fil["nom_agencia"]);
    $cod_agenc   = strtoupper($fil["cod_agenc"]);
}
$texto_reporte .= ($radios[1] == "allg") ? " CONSOLIDADO " : " DE LA AGENCIA: " . $nom_agencia;

// Identificador visible del filtro de región
if ($regionRadio === 'anyregion' && $nombre_region !== '') {
    $texto_reporte .= " / REGIÓN: " . strtoupper($nombre_region);
}

// Ejecutamos la consulta principal
$datos_sql = mysqli_query($conexion, $consulta);
$data = [];
$i = 0;
while ($fila = mysqli_fetch_assoc($datos_sql)) {
    $data[$i] = $fila;
    $i++;
}
if ($i == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos para mostrar en el reporte']);
    return;
}

// Información de la cooperativa para el encabezado del PDF
$queryins = "
    SELECT * 
    FROM jpxdcegu_bd_general_coopera.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop
    WHERE ag.id_agencia=" . $_SESSION['id_agencia'];
$resInfo = mysqli_query($conexion, $queryins);
$info = [];
$j = 0;
while ($fil = mysqli_fetch_array($resInfo)) {
    $info[$j] = $fil;
    $j++;
}
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institución asignada a la agencia no encontrada']);
    return;
}

/* 
   ------------------------------------------------------------------------------
   NUEVO: OBTENER DETALLE DE GASTOS POR TIPO
   ------------------------------------------------------------------------------
   1) Tomamos todos los CCODCTA del array principal
   2) Buscamos en CREDKAR + credkar_detalle + cre_productos_gastos + cre_tipogastos 
      la suma de cada gasto por CCODCTA.
   3) Formamos un array pivotado: $pivotData[ccodcta][id_tipogasto] = monto
   4) También sacamos un array de todos los tipos de gasto usados: $tiposGasto = [ [id, nombre], ... ]
*/

// 1) Obtener todos los CCODCTA en un array para filtrar
$ccodctas = array_column($data, 'cuenta'); // índice 'cuenta'
$ccodctas_str = implode("','", $ccodctas); // para uso en la cláusula IN

// 2) Buscar tipos de gasto y sumas
//    Solo buscamos aquellos con CTIPPAG='D' (que se interpretan normalmente como desembolso)
$pivotData = [];  // pivotData[ ccodcta ][ id_tipogasto ] = total
$tiposGastoSet = []; // para ir guardando [id_tipogasto => nombre_gasto]
$sql_gastos = "
SELECT 
    ck.CCODCTA,
    tg.id AS id_tipogasto,
    tg.nombre_gasto,
    SUM(cd.monto) AS total
FROM CREDKAR ck
INNER JOIN credkar_detalle cd ON cd.id_credkar=ck.CODKAR
INNER JOIN cre_productos_gastos pg ON pg.id=cd.id_concepto
INNER JOIN cre_tipogastos tg ON tg.id=pg.id_tipo_deGasto
WHERE cd.tipo='otro' AND ck.CCODCTA IN ('" . $ccodctas_str . "')
  AND ck.CTIPPAG='D'
  AND ck.CESTADO <> 'X'
GROUP BY ck.CCODCTA, tg.id
ORDER BY tg.id
";
$res_gastos = mysqli_query($conexion, $sql_gastos);
while ($rowg = mysqli_fetch_assoc($res_gastos)) {
    $cc = $rowg['CCODCTA'];
    $idg = $rowg['id_tipogasto'];
    $monto = (float)$rowg['total'];

    // acumulamos
    if (!isset($pivotData[$cc])) {
        $pivotData[$cc] = [];
    }
    $pivotData[$cc][$idg] = $monto;

    // guardamos el tipo de gasto en un set
    if (!isset($tiposGastoSet[$idg])) {
        $tiposGastoSet[$idg] = $rowg['nombre_gasto'];
    }
}

// 3) Convertimos ese $tiposGastoSet en un array ordenado para poder iterar en un orden fijo
//    (cada vez que se necesite imprimir en PDF/Excel)
$tiposGasto = [];
foreach ($tiposGastoSet as $id => $nombre) {
    $tiposGasto[] = [
        'id' => $id,
        'nombre' => $nombre
    ];
}
// Ordenamos por id, si queremos
usort($tiposGasto, function ($a, $b) {
    return ($a['id'] < $b['id']) ? -1 : 1;
});

// Con esto, ya tenemos:
//    $tiposGasto => [ ['id'=>1, 'nombre'=>'Gasto X'], ['id'=>2, 'nombre'=>'Gasto Y'], ... ]
//    $pivotData  => [ 'CCODCTA123' => [1=>120.50, 2=>95.00], 'CCODCTA456'=>..., ... ]

// Dependiendo del tipo de exportación (xls ó pdf) llamamos a la función
switch ($tipo) {
    case 'xlsx':
        printxls($data, [$texto_reporte, $_SESSION['id'], $hoy, $conexion, $nomestado], $tiposGasto, $pivotData);
        break;
    case 'pdf':
        printpdf($data, [$texto_reporte, $_SESSION['id'], $hoy, $conexion, $nomestado], $info, $tiposGasto, $pivotData);
        break;
}

/* 
   ==========================================================================
   =            FUNCIONES PARA GENERAR EL REPORTE PDF y EXCEL
   ==========================================================================
*/

/**
 * Función para generar el PDF.
 * Se crea una cabecera dinámica para las columnas de gastos según $tiposGasto.
 */
function printpdf($datos, $otros, $info, $tiposGasto, $pivotData)
{
    // Datos de la institución para encabezado
    $oficina     = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccion   = decode_utf8($info[0]["muni_lug"]);
    $email       = $info[0]["emai"];
    $telefonos   = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nit         = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins    = "../../../.." . $info[0]["log_img"];

    class PDF extends FPDF
    {
        public $oficina;
        public $institucion;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $pathlogo;
        public $pathlogoins;
        public $titulo;
        public $user;
        public $conexion;

        public function __construct($oficina, $institucion, $direccion, $email, $telefono, $nit, $pathlogo, $pathlogoins, $titulo, $user, $conexion)
        {
            parent::__construct('L', 'mm', 'Letter');
            $this->oficina     = $oficina;
            $this->institucion = $institucion;
            $this->direccion   = $direccion;
            $this->email       = $email;
            $this->telefono    = $telefono;
            $this->nit         = $nit;
            $this->pathlogo    = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->titulo      = $titulo;
            $this->user        = $user;
            $this->conexion    = $conexion;
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";

            // Fecha / usuario
            $hoy = date("Y-m-d H:i:s");
            $this->SetFont('Arial', '', 7);
            $this->Cell(0, 3, $hoy, 0, 1, 'R');
            $this->Cell(0, 3, $this->user, 0, 1, 'R');
            $this->Ln(3);

            // Logo de la agencia
            $this->Image($this->pathlogoins, 15, 18, 35);

            // Encabezado de institución
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 4, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 4, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 4, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 4, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 4, 'NIT: ' . $this->nit, 0, 1, 'C');
            $this->Ln(5);

            // Título
            $this->SetFont($fuente, 'B', 12);
            $this->Cell(0, 6, 'REPORTE', 0, 1, 'C');
            $this->Cell(0, 6, decode_utf8($this->titulo), 0, 1, 'C');
            $this->Ln(8);

            // Armamos la línea de cabeceras con más espacio
            $this->SetFont($fuente, 'B', 8);

            $encabezadoCompleto = [
                ['txt' => 'CRÉDITO',        'w' => 22, 'align' => 'C'],
                ['txt' => 'NOMBRE CLIENTE', 'w' => 40, 'align' => 'C'],
                ['txt' => 'SOLICITADO',     'w' => 34, 'align' => 'R'],
                ['txt' => 'APROBADO',       'w' => 18, 'align' => 'R'],
                ['txt' => 'DESEMBOLSADO',   'w' => 26, 'align' => 'R'],
                ['txt' => 'GASTOS',         'w' => 16, 'align' => 'R'],
                ['txt' => 'TIP.DOC.',       'w' => 20, 'align' => 'C'],
                ['txt' => 'F.SOLICITUD',    'w' => 26, 'align' => 'C'],
                ['txt' => 'F.DESEMBOLSO',   'w' => 22, 'align' => 'C'],
                ['txt' => 'RESPONSABLE',    'w' => 40, 'align' => 'L'],
            ];

            foreach ($encabezadoCompleto as $col) {
                $this->Cell($col['w'], 8, decode_utf8($col['txt']), 'B', 0, $col['align']);
            }
            $this->Ln(10);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo 
            // $this->Image($this->pathlogo, 175, 279, 28);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Instanciamos nuestro PDF
    $pdf = new PDF(
        $oficina,
        $institucion,
        $direccion,
        $email,
        $telefonos,
        $nit,
        $rutalogomicro,
        $rutalogoins,
        $otros[0],     // título
        $otros[1],     // usuario
        $otros[3]      // conexión
    );
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $fuente = "Courier";

    // Para subtotales de la sección
    $sumasoli = 0;
    $sumamontoapro = 0;
    $sumamontodes = 0;
    $totalcl = 0;

    // Manejo de totales globales
    $fila = 0;
    $auxfondo = null;
    $auxgrupo = -1;
    $resumen  = false;

    // Acumuladores para el "resumen por grupo"
    $acumSoliGrupo = 0;
    $acumAproGrupo = 0;
    $acumDesGrupo  = 0;
    $acumGastosGrupo = 0;

    // Inicializamos un array para totalizar "por todo el reporte"
    $grandSoli = 0;
    $grandApro = 0;
    $grandDes  = 0;
    $grandGastos = 0;

    // Función local para imprimir el subtotal del grupo con más espacio
    $imprimirSubtotales = function () use (
        &$pdf,
        &$acumSoliGrupo,
        &$acumAproGrupo,
        &$acumDesGrupo,
        &$acumGastosGrupo,
        $fuente
    ) {
        if ($pdf->GetY() > 170) { // Control de salto de página más temprano
            $pdf->AddPage();
        }
        $pdf->Ln(4);
        $pdf->SetFont($fuente, 'B', 7);
        $pdf->Cell(20, 6, '', 0, 0, 'R');
        $pdf->Cell(40, 6, 'Subtotal grupo:', 0, 0, 'R');
        $pdf->Cell(34, 6, number_format($acumSoliGrupo, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(20, 6, number_format($acumAproGrupo, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(22, 6, number_format($acumDesGrupo, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(19, 6, number_format($acumGastosGrupo, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(20, 6, '', 0, 0, 'C');
        $pdf->Cell(22, 6, '', 0, 0, 'C');
        $pdf->Cell(22, 6, '', 0, 0, 'C');
        $pdf->Cell(40, 6, '', 0, 1, 'C');
        $pdf->Ln(4);

        // Reset
        $acumSoliGrupo = 0;
        $acumAproGrupo = 0;
        $acumDesGrupo  = 0;
        $acumGastosGrupo = 0;
    };

    // Bucle principal con más espacio entre filas
    while ($fila < count($datos)) {
        $row = $datos[$fila];

        $cuenta = $row["cuenta"];
        $nombre = strtoupper(decode_utf8(trim(preg_replace('/\s+/', ' ', $row["nombre"]))));
        $montosolicitado   = (float)$row["montosoli"];
        $montoaprobado     = (float)$row["montoaprobado"];
        $montodesembolsado = (float)$row["montodesembolsado"];
        $tipo    = $row["tipo"];
        $fecsol  = date("d-m-Y", strtotime($row["fecsolicitud"]));
        $fecdes  = ($row["fecdesembolsado"] == '-') ? '-' : date("d-m-Y", strtotime($row["fecdesembolsado"]));
        $responsable = strtoupper(decode_utf8($row["responsable"]));

        $tipoenti    = $row["TipoEnti"];
        $idfondos    = $row["fondoid"];
        $nombrefondo = $row["fondesc"];
        $idgrupo     = ($tipoenti == "GRUP") ? $row["id_grupos"] : 0;
        $nomgrupo    = $row["NombreGrupo"];

        // Verificamos si cambia de fondo
        if ($idfondos != $auxfondo) {
            if ($fila > 0) {
                $imprimirSubtotales();
            }
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->Cell(0, 6, 'FUENTE DE FONDOS: ' . strtoupper($nombrefondo), 0, 1, 'L');
            $pdf->Ln(2);
            $pdf->SetFont($fuente, '', 9);
            $auxfondo = $idfondos;
            $auxgrupo = -1;
        }

        // Verificamos si cambia de grupo
        if ($idgrupo != $auxgrupo) {
            if ($fila > 0) {
                $imprimirSubtotales();
            }
            $pdf->SetFont($fuente, 'B', 8);
            $textoGrupo = ($tipoenti == 'GRUP') ? 'GRUPO: ' . strtoupper($nomgrupo) : 'CREDITOS INDIVIDUALES';
            $pdf->Cell(0, 6, $textoGrupo, 0, 1, 'L');
            $pdf->Ln(2);
            $pdf->SetFont($fuente, '', 9);
            $auxgrupo = $idgrupo;
        }

        // Obtenemos el total de gastos para esta cuenta
        $detalleGastos = isset($pivotData[$cuenta]) ? $pivotData[$cuenta] : [];
        $totalGastosFila = array_sum($detalleGastos);

        // Incrementamos acumuladores
        $acumSoliGrupo += $montosolicitado;
        $acumAproGrupo += $montoaprobado;
        $acumDesGrupo  += $montodesembolsado;
        $acumGastosGrupo += $totalGastosFila;

        $grandSoli += $montosolicitado;
        $grandApro += $montoaprobado;
        $grandDes  += $montodesembolsado;
        $grandGastos += $totalGastosFila;

        // Impresión de la fila con más altura
        $pdf->SetFont($fuente, '', 7);
        $pdf->Cell(25, 3, $cuenta, 0, 0, 'L');
        $pdf->SetFont($fuente, '', 7);
        $pdf->Cell(48, 3, $nombre, 0, 0, 'L');
        $pdf->Cell(20, 3, number_format($montosolicitado, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(20, 3, number_format($montoaprobado, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(22, 3, number_format($montodesembolsado, 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(20, 3, number_format($totalGastosFila, 2, '.', ','), 0, 0, 'R');

        $tipdoc = '';
        if ($tipo == 'E') $tipdoc = 'EFECTIVO';
        if ($tipo == 'T') $tipdoc = 'TRANSFER';
        if ($tipo == 'C') $tipdoc = 'CHEQUE';
        $pdf->Cell(25, 3, $tipdoc, 0, 0, 'C');

        $pdf->Cell(22, 3, $fecsol, 0, 0, 'C');
        $pdf->Cell(22, 3, $fecdes, 0, 0, 'C');
        $pdf->Cell(40, 3, $responsable, 0, 1, 'L');

        $pdf->Ln(1); // Espacio adicional entre filas

        $fila++;
    }

    // Al finalizar el bucle, imprimimos los últimos subtotales
    $imprimirSubtotales();

    // Totales globales en el reporte con más espacio
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->Cell(0, 1, '', 'T', 1, 'R');
    $pdf->Ln(3);

    $pdf->Cell(22, 6, '', 0, 0, 'R');
    $pdf->Cell(40, 6, 'TOTAL CREDITOS: ' . count($datos), 0, 0, 'R');
    $pdf->Cell(34, 6, number_format($grandSoli, 2, '.', ','), 0, 0, 'R');
    $pdf->Cell(20, 6, number_format($grandApro, 2, '.', ','), 0, 0, 'R');
    $pdf->Cell(22, 6, number_format($grandDes, 2, '.', ','), 0, 0, 'R');
    $pdf->Cell(18, 6, number_format($grandGastos, 2, '.', ','), 0, 0, 'R');
    $pdf->Cell(20, 6, '', 0, 0, 'C');
    $pdf->Cell(22, 6, '', 0, 0, 'C');
    $pdf->Cell(22, 6, '', 0, 0, 'C');
    $pdf->Cell(40, 6, '', 0, 1, 'C');

    $pdf->Ln(12);
    // Firmas con más espacio
    $pdf->SetFont($fuente, '', 10);
    $pdf->Cell(0, 6, 'HECHO POR: __________________________', 0, 1, 'C');
    $pdf->Cell(0, 6, 'REVISADO POR: __________________________', 0, 1, 'C');
    $pdf->Ln(10);

    // Salida en Base64
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = [
        'status'  => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "CREDITOS_" . $otros[4] . "_" . $otros[2],
        'data'    => "data:application/pdf;base64," . base64_encode($pdfData),
        'tipo'    => "pdf"
    ];
    echo json_encode($opResult);
}

/**
 * Función para generar el Excel con columnas dinámicas de gastos
 */
function printxls($datos, $otros, $tiposGasto, $pivotData)
{
    $hoy = date("Y-m-d H:i:s");

    $spread = new Spreadsheet();
    $spread->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Reporte Créditos')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');

    $hoja = $spread->getActiveSheet();
    $hoja->setTitle("Reporte de desembolsos");

    // Títulos en filas superiores
    $hoja->setCellValue("A1", $hoy);
    $hoja->setCellValue("A2", $otros[1]); // usuario
    $hoja->mergeCells('A1:H1');
    $hoja->mergeCells('A2:H2');

    $hoja->setCellValue("A4", "REPORTE");
    $hoja->mergeCells('A4:H4');
    $hoja->setCellValue("A5", strtoupper($otros[0]));
    $hoja->mergeCells('A5:H5');

    // Encabezados de tabla fijos
    // Armamos un array que luego "fromArray()" insertará
    $encabezadosFijos = [
        'CRÉDITO',
        'CÓDIGO CLIENTE',
        'NOMBRE CLIENTE',
        'MONTO SOLICITADO',
        'MONTO APROBADO',
        'MONTO DESEMBOLSADO'
    ];

    // Encabezados dinámicos (tipos de gasto)
    $encabezadosGastos = [];
    foreach ($tiposGasto as $tg) {
        $encabezadosGastos[] = strtoupper($tg['nombre']);
    }

    // Encabezados finales
    $encabezadosFinales = [
        'TIPO DE DOCUMENTO',
        'FECHA DE SOLICITUD',
        'FECHA DE DESEMBOLSO',
        'FECHA DE VENCIMIENTO',
        'RESPONSABLE',
        'FUENTE DE FONDOS',
        'TIPO',
        'NOMBRE GRUPO',
        'FRECUENCIA',
        'CUOTAS',
        'NCICLO',   // <-- Nuevo encabezado para mostrar “NCiclo”
        'DESTINO',
        'GARANTÍA',
        'SECTOR ECONÓMICO',
        'ACTIVIDAD ECONÓMICA',
        'LÍNEA DE CRÉDITO'
    ];


    // Construimos la fila completa de cabecera
    $encabezado_tabla = array_merge($encabezadosFijos, $encabezadosGastos, $encabezadosFinales);

    // Insertamos el encabezado en la fila 7
    $hoja->fromArray($encabezado_tabla, null, 'A7');

    // Ajustes de estilo para las filas de título y encabezados
    $hoja->getStyle("A1:H1")->getFont()->setSize(9)->setName("Arial");
    $hoja->getStyle("A2:H2")->getFont()->setSize(9)->setName("Arial");
    $hoja->getStyle("A4:H4")->getFont()->setSize(11)->setName("Courier")->setBold(true);
    $hoja->getStyle("A5:H5")->getFont()->setSize(11)->setName("Courier")->setBold(true);

    // Cabecera (fila 7)
    $hoja->getStyle("A7:AZ7")->getFont()->setSize(10)->setName("Courier")->setBold(true);
    $hoja->getStyle("A7:AZ7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Vamos llenando desde la fila 8 en adelante
    $fila_excel = 8;

    // Para totales globales
    $sumSoli = 0;
    $sumApro = 0;
    $sumDes  = 0;
    // Array de totales para gastos
    $sumGastos = [];

    foreach ($datos as $row) {
        $cuenta       = $row["cuenta"];
        $codigocliente = $row["codigocliente"];
        $nombre       = strtoupper($row["nombre"]);
        $montosoli    = (float)$row["montosoli"];
        $montoapro    = (float)$row["montoaprobado"];
        $montodes     = (float)$row["montodesembolsado"];

        $tipoDoc = $row["tipo"];
        if ($tipoDoc == 'E') $tipoDoc = 'EFECTIVO';
        if ($tipoDoc == 'T') $tipoDoc = 'TRANSFERENCIA';
        if ($tipoDoc == 'C') $tipoDoc = 'CHEQUE';

        $fecsolicitud   = $row["fecsolicitud"];
        $fecdesembolsado = $row["fecdesembolsado"];
        $fecvencimiento = $row["fecvencimiento"];
        $responsable    = strtoupper($row["responsable"]);
        $nombrefondos   = $row["fondesc"];
        $tipoenti       = $row["TipoEnti"];
        $tipoenti       = ($tipoenti == "GRUP") ? "GRUPAL" : "INDIVIDUAL";
        $nomgrupo       = $row["NombreGrupo"];
        $frec           = $row["frecuencia"];
        $ncuotas        = $row["numcuotas"];
        $nciclo = $row["ciclo"]; // <-- Valor que vino como "cm.NCiclo AS ciclo"
        $destinocre     = $row["destino"];
        $garantia       = $row["garantia"];
        $producto       = $row["producto"];
        $sectorecon     = $row["sectorEconomico"];
        $actividadecon  = $row["actividadEconomica"];

        // Sumas globales
        $sumSoli += $montosoli;
        $sumApro += $montoapro;
        $sumDes  += $montodes;

        // Revisamos los gastos de esta cuenta
        $detalleGastos = isset($pivotData[$cuenta]) ? $pivotData[$cuenta] : [];

        // Acumulamos totales
        foreach ($tiposGasto as $tg) {
            $idg = $tg['id'];
            if (!isset($sumGastos[$idg])) {
                $sumGastos[$idg] = 0;
            }
            $m = isset($detalleGastos[$idg]) ? $detalleGastos[$idg] : 0;
            $sumGastos[$idg] += $m;
        }

        // Preparamos la fila para fromArray
        $filaX = [
            $cuenta,
            $codigocliente,
            $nombre,
            $montosoli,
            $montoapro,
            $montodes
        ];

        // Agregamos en orden los montos de cada tipo de gasto
        foreach ($tiposGasto as $tg) {
            $idg  = $tg['id'];
            $m    = isset($detalleGastos[$idg]) ? $detalleGastos[$idg] : 0;
            $filaX[] = $m;
        }

        // Finalmente las columnas "fijas" restantes
        $filaX[] = $tipoDoc;
        $filaX[] = $fecsolicitud;
        $filaX[] = $fecdesembolsado;
        $filaX[] = $fecvencimiento;
        $filaX[] = $responsable;
        $filaX[] = $nombrefondos;
        $filaX[] = $tipoenti;
        $filaX[] = $nomgrupo;
        $filaX[] = $frec;
        $filaX[] = $ncuotas;
        $filaX[] = $nciclo;  // <-- Aquí se agrega para que aparezca en su columna
        $filaX[] = $destinocre;
        $filaX[] = $garantia;
        $filaX[] = $sectorecon;
        $filaX[] = $actividadecon;
        $filaX[] = $producto;

        // Insertamos la fila
        $hoja->fromArray($filaX, null, "A" . $fila_excel);

        // Ajuste de tipo de letra
        $hoja->getStyle("A" . $fila_excel . ":AZ" . $fila_excel)->getFont()->setName("Courier")->setSize(9);

        $fila_excel++;
    }

    // Totales finales en la siguiente fila
    $hoja->setCellValue("C" . $fila_excel, "TOTAL CRÉDITOS: " . count($datos));
    $hoja->setCellValue("D" . $fila_excel, $sumSoli);
    $hoja->setCellValue("E" . $fila_excel, $sumApro);
    $hoja->setCellValue("F" . $fila_excel, $sumDes);
    // Y cada gasto
    // Determinamos la columna en la que inician los tipos de gasto en la fila "encabezadosFijos"
    //   => Los fijos son 6 columnas al inicio, así que la 7ma columna es la primera de gastos
    //   => 'G' es la 7ma. Iremos sumando.
    $colGastoIndex = 7;
    foreach ($tiposGasto as $tg) {
        $idg = $tg['id'];
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colGastoIndex);
        $hoja->setCellValue($colLetter . $fila_excel, (isset($sumGastos[$idg]) ? $sumGastos[$idg] : 0));
        $colGastoIndex++;
    }
    // Ajuste de estilo a esa fila de totales
    $hoja->getStyle("A" . $fila_excel . ":AZ" . $fila_excel)->getFont()->setName("Courier")->setBold(true)->setSize(9);

    // Ajustar ancho de columnas
    $lastCol = $colGastoIndex + count($encabezadosFinales) - 1; // para la parte final
    // Vamos a suponer que no nos pasamos de la 'Z' + ...
    // pero para no quedarnos cortos, haremos un "autoSize" en un rango grande
    $columnas = range('A', 'Z');
    foreach ($columnas as $col) {
        $hoja->getColumnDimension($col)->setAutoSize(true);
    }
    // Si consideras que sí hay más columnas (AA, AB, etc.), harías un bucle más grande.

    // Salida en Base64
    ob_start();
    $writer = new Xlsx($spread);
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = [
        'status'  => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "CREDITOS_" . $otros[4] . "_" . $otros[2],
        'data'    => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    ];
    echo json_encode($opResult);
}
