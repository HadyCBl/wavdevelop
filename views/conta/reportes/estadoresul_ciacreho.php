<?php

/**
 * Este archivo genera un estado de resultados segun el tipo solicitado.
 * 
 * - Verifica si la solicitud es de tipo GET y redirige a una página 404.
 * - Inicia una sesión y verifica si la sesión ha expirado.
 * - Incluye archivos de configuración y funciones necesarias.
 * - Configura la zona horaria a 'America/Guatemala'.
 * - Utiliza las bibliotecas FPDF y PhpSpreadsheet para la generación de documentos.
 * 
 * Dependencias:
 * - Configuración: config.php, database.php
 * - Funciones: func_gen.php, func_ctb.php
 * - Librerías: FPDF, PhpSpreadsheet
 * 
 * Variables:
 * - $idusuario: ID del usuario obtenido de la sesión.
 * - $hoy2: Fecha y hora actual en formato "Y-m-d H:i:s".
 * - $hoy: Fecha actual en formato "Y-m-d".
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
include __DIR__ . '/../funciones/func_ctb.php';

require '../../../fpdf/fpdf.php';
require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++ [`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`,[]] ++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

list($finicio, $ffin) = $inputs;
list($codofi, $fondoid, $nivelinit, $nivelfin) = $selects;
list($rfondo, $ragencia) = $radios;
$sector = (!empty($selects[4])) ? $selects[4] : null;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++
    +++[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`,`sectors`],[`rfondos`,`ragencia`],[ ] +++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

if (!validateDate($finicio, 'Y-m-d') || !validateDate($ffin, 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    return;
}
if ($finicio > $ffin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}
$fechaini = strtotime($finicio);
$fechafin = strtotime($ffin);
$anioini = date("Y", $fechaini);
$aniofin = date("Y", $fechafin);

if ($anioini != $aniofin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Las fechas tienen que ser del mismo año']);
    return;
}
//NIVELES
if ($nivelinit > $nivelfin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de niveles inválido']);
    return;
}

//VALIDAR SECTOR
if ($ragencia == "anysector" && empty($sector)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar un sector válido']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++ CONSULTANDO EN LA BD XDXD ++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/**
 * Este bloque del script realiza consultas en la bd, de los datos necesarios para la generacion del estado de resultados.
 * 
 * Variables de entrada:
 * - $finicio: Fecha de inicio del reporte.
 * - $ffin: Fecha de fin del reporte.
 * - $fondoid: ID del fuente de fondo (si aplica).
 * - $codofi: Código de la oficina (si aplica).
 * 
 * El script realiza las siguientes acciones:
 * 1. Construye la condición de la consulta SQL basada en los parámetros de entrada.
 * 2. Define los parámetros para la consulta SQL.
 * 3. Genera el título del reporte basado en las fechas de inicio y fin.
 * 4. Ejecuta una consulta SQL para obtener los movimientos contables en el rango de fechas especificado.
 * 5. Verifica si hay parámetros configurados para el cálculo del estado de resultados.
 * 6. Verifica si hay datos de movimientos contables en el rango de fechas especificado.
 * 7. Ejecuta una consulta SQL para obtener las cuentas contables.
 * 8. Verifica si hay cuentas contables configuradas.
 * 
 * En caso de errores, se captura la excepción y se registra el error en un log.
 * Finalmente, se cierra la conexión a la base de datos.
 * 
 * Excepciones manejadas:
 * - No hay cuentas configuradas para el cálculo del estado de resultados.
 * - No hay datos en la fecha indicada.
 * - No se encontraron cuentas contables.
 * 
 * Variables de salida:
 * - $status: Estado de la operación (1: éxito, 0: error).
 * - $mensaje: Mensaje de error en caso de fallo.
 */

$condi = (($rfondo == "anyf") ? " AND id_fuente_fondo=? " : "") . (($ragencia == "anyofi") ? " AND id_agencia2=? " : "");
$parameters = [$finicio, $ffin];

if ($rfondo == "anyf") {
    $parameters[] = $fondoid;
}
if ($ragencia == "anyofi") {
    $parameters[] = $codofi;
}

if ($ragencia == "anysector" && is_numeric($sector) && (int)$sector > 0) {
    $dataAgencies = [];
    try {
        $database->openConnection();
        $dataAgencies = $database->getAllResults("SELECT sag.id_agencia FROM ctb_sectores_agencia sag WHERE sag.id_sector=?", [$sector]);
    } catch (Exception $e) {
    } finally {
        $database->closeConnection();
    }
    if (!empty($dataAgencies)) {
        $ids = array_column($dataAgencies, 'id_agencia');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $condi .= " AND id_agencia2 IN ($placeholders) ";
        $parameters = array_merge($parameters, $ids);
    }
}


$titlereport = " DEL " . setdatefrench($finicio) . " AL " . setdatefrench($ffin);

$strquery = "SELECT ccodcta,id_ctb_nomenclatura,SUM(debe) sumdeb, SUM(haber) sumhab from ctb_diario_mov 
    WHERE estado=1 AND (feccnt BETWEEN ? AND ?) 
    AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipo>=4 AND id_tipo<=5) $condi 
    GROUP BY ccodcta ORDER BY ccodcta;";

$showmensaje = false;
try {
    $database->openConnection();

    $parametros = $database->selectColumns("ctb_parametros_cuentas", ["id_tipo", "clase"], "id_tipo>=4 AND id_tipo<=5");
    if (empty($parametros)) {
        $showmensaje = true;
        throw new Exception("No hay cuentas configuradas para el calculo del Estado de Resultados.");
    }

    $ctbmovdata = $database->getAllResults($strquery, $parameters);
    if (empty($ctbmovdata)) {
        $showmensaje = true;
        throw new Exception("No hay datos en la fecha indicada");
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++++++++++ CUENTAS CONTABLES ++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $strquery = "SELECT id,ccodcta,cdescrip from ctb_nomenclatura 
    WHERE estado=1 AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipo>=4 AND id_tipo<=5) AND LENGTH(ccodcta) <=15 
    ORDER BY ccodcta;";

    $nomenclatura = $database->getAllResults($strquery);
    if (empty($nomenclatura)) {
        $showmensaje = true;
        throw new Exception("No se encontraron cuentas contables");
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++ INFO INSTITUCION ++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++ CUENTAS PARAMETRIZADAS PAL ESTADO DE RESULTADOS +++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

/**
 * Filtra los parámetros para obtener las cuentas de ingreso y egreso.
 *
 * @param array $parametros Arreglo de parámetros que contiene información de las cuentas.
 *
 * @return array $cuentasingreso Arreglo de cuentas cuyo 'id_tipo' es igual a 4 (ingresos).
 * @return array $cuentasegreso Arreglo de cuentas cuyo 'id_tipo' es igual a 5 (egresos).
 */
$cuentasingreso = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 4;
});
$cuentasegreso = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 5;
});

switch ($tipo) {
    case 'show':
        showresults($ctbmovdata, $nomenclatura, $cuentasingreso, $cuentasegreso, $nivelinit, $nivelfin);
        break;
    case 'xlsx';
        printxls($ctbmovdata, [$nomenclatura], $cuentasingreso, $cuentasegreso);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport, $nomenclatura], $info, $cuentasingreso, $cuentasegreso, $nivelinit, $nivelfin);
        break;
}

function showresults($registro, $cuentas, $cuentasingreso, $cuentasegreso, $nivelinit, $nivelfin)
{
    /**
     * Este bloque de script genera una tabla de estado de resultados basado en las cuentas y registros proporcionados.
     * 
     * Variables:
     * - $keys: Array de claves para los valores de las cuentas a mostrar en la tabla de la vista.
     * - $encabezados: Array de encabezados para el reporte a mostrar.
     * - $monbal: Array de niveles de cuentas soportados en el Estado de resultados.
     * - $valores: Array que almacena los valores del reporte.
     * - $printresumeningreso: Bandera para imprimir el resumen de ingresos.
     * - $totali: Total de ingresos.
     * - $totalg: Total de egresos.
     * 
     * Procesamiento:
     * - Se recorren las cuentas y se calcula el monto para cada cuenta basado en los registros.
     * - Se ajusta el monto si la cuenta pertenece a ingresos.
     *      + las cuentas de ingresos funcionan asi: HABER - DEBE
     *      + las cuentas de egresos funcionan asi: DEBE - HABER
     * - Se formatean los montos según el nivel de la cuenta.
     * - Se almacenan los valores en el array $valores si el monto es diferente de cero y el nivel está en el rango seleccionado.
     * - Se calculan las sumatorias de ingresos y egresos.
     * - Se imprime el resumen de ingresos y egresos en el reporte.
     * - Se calcula y se imprime el resultado del ejercicio.
     * 
     * Funciones auxiliares:
     * - moneda($monto): Formatea el monto en formato monetario. Dispoble en func_gen.php.
     * 
     * Notas:
     * - El script asume que las variables $cuentas, $registro, $cuentasingreso, $cuentasegreso, $nivelinit y $nivelfin están definidas previamente.
     * - El array $valores se utiliza para generar el reporte final.
     */
    $keys = ["id", "ccodcta", "nombre"];
    $encabezados = ["#", "CUENTA", "DESCRIPCION"];


    $monbal = ['1', '2', '3', '4', '5', '6', '7'];
    $niv = $nivelfin;
    while ($niv >= $nivelinit) {
        $encabezados[] = "- {$monbal[$niv - 1]} -";
        $keys[] = $monbal[$niv - 1];
        $niv--;
    }

    $valores[] = [];
    $printresumeningreso = false;
    $nivelant = 0;
    $totali = 0;
    $totalg = 0;

    $index = 0;

    foreach ($cuentas as $key => $account) {
        $id = $account["id"];
        $cuenta = $account["ccodcta"];
        $nombre = $account["cdescrip"];
        $nivel = strlen($cuenta);
        $monto = 0;

        foreach ($registro as $reg) {
            if ($cuenta == substr($reg["ccodcta"], 0, $nivel)) {
                $sal = $reg["sumdeb"] - $reg["sumhab"];
                $monto = $monto + $sal;
            }
        }
        $clase = substr($cuenta, 0, 1);
        $result = array_search($clase, array_column($cuentasingreso, 'clase'));
        if ($result !== false) {
            $monto *= -1;
        }

        $nivel1 = ($nivel == 1) ? moneda($monto) : " ";
        $nivel2 = ($nivel == 2 || $nivel == 3) ? moneda($monto) : " ";
        $nivel3 = ($nivel == 4 || $nivel == 5) ? moneda($monto) : " ";
        $nivel4 = ($nivel == 6 || $nivel == 7) ? moneda($monto) : " ";
        $nivel5 = ($nivel == 8 || $nivel == 9) ? moneda($monto) : " ";
        $nivel6 = ($nivel == 10 || $nivel == 11) ? moneda($monto) : " ";
        $nivel7 = ($nivel == 12 || $nivel == 13) ? moneda($monto) : " ";
        $nivel8 = ($nivel == 14 || $nivel == 15) ? moneda($monto) : " ";

        if ($monto != 0) {
            $monbal = [$nivel1, $nivel2, $nivel3, $nivel4, $nivel5, $nivel6, $nivel7, $nivel8];
            $niveles = [[1, 2, 4, 6, 8, 10, 12, 14], [1, 3, 5, 7, 9, 11, 13, 15]];
            $niv = $nivelfin;
            $flag = false;
            //SE VAN A IMPRIMIR LOS NIVELES QUE ESTAN EN EL RANGO SELECCIONADO, PARA ESO SE ACTIVA EL FLAG
            while ($niv >= $nivelinit) {
                if ($niveles[0][$niv - 1] == $nivel || $niveles[1][$niv - 1] == $nivel) {
                    $flag = true;
                }
                $niv--;
            }
            //SE VAN A IMPRIMIR LOS NIVELES QUE ESTAN EN EL RANGO SELECCIONADO SI LA BANDERA ESTA ACTIVA
            if ($flag) {
                if ($nivel < $nivelant) {
                    // $pdf->Ln(3);
                }
                $valores[$index] = [
                    'id' => ($index + 1),
                    'ccodcta' => $cuenta,
                    'nombre' => $nombre,
                ];

                $niv = $nivelfin;
                while ($niv >= $nivelinit) {
                    $valores[$index][$niv] = $monbal[$niv - 1];
                    $niv--;
                }
                $nivelant = $nivel;

                $index++;
            }

            //***************SUMATORIAS*********************
            $result = array_search($cuenta, array_column($cuentasingreso, 'clase'));
            if ($result !== false) {
                $totali = $totali + $monto;
            }
            $result = array_search($cuenta, array_column($cuentasegreso, 'clase'));
            if ($result !== false) {
                $totalg = $totalg + $monto;
            }
        }

        if ($key != array_key_last($cuentas)) {
            $nextcuenta = $cuentas[$key + 1]["ccodcta"];
            $result = array_search($nextcuenta, array_column($cuentasegreso, 'clase'));

            if ($result !== false && !$printresumeningreso) {
                $printresumeningreso = true;

                $valores[$index] = [
                    'id' => ($index + 1),
                    'ccodcta' => '++++++',
                    'nombre' => 'TOTAL INGRESOS',
                    '1' => ($nivelinit == 1) ? moneda($totali) : '*****',
                    '2' => ($nivelinit == 2) ? moneda($totali) : '*****',
                    '3' => ($nivelinit == 3) ? moneda($totali) : '*****',
                    '4' => ($nivelinit == 4) ? moneda($totali) : '*****',
                    '5' => ($nivelinit == 5) ? moneda($totali) : '*****',
                    '6' => ($nivelinit == 6) ? moneda($totali) : '*****',
                    '7' => ($nivelinit == 7) ? moneda($totali) : '*****',
                    '8' => ($nivelinit == 8) ? moneda($totali) : '*****',
                ];
                $index++;

                $valores[$index] = [
                    'id' => ($index + 1),
                    'ccodcta' => ' ',
                    'nombre' => ' ',
                    '1' => " ",
                    '2' => " ",
                    '3' => " ",
                    '4' => " ",
                    '5' => " ",
                    '6' => " ",
                    '7' => " ",
                    '8' => " ",
                ];
                $index++;
            }
        } else {

            $valores[$index] = [
                'id' => ($index + 1),
                'ccodcta' => '++++++',
                'nombre' => 'TOTAL EGRESOS',
                '1' => ($nivelinit == 1) ? moneda($totalg) : '*****',
                '2' => ($nivelinit == 2) ? moneda($totalg) : '*****',
                '3' => ($nivelinit == 3) ? moneda($totalg) : '*****',
                '4' => ($nivelinit == 4) ? moneda($totalg) : '*****',
                '5' => ($nivelinit == 5) ? moneda($totalg) : '*****',
                '6' => ($nivelinit == 6) ? moneda($totalg) : '*****',
                '7' => ($nivelinit == 7) ? moneda($totalg) : '*****',
                '8' => ($nivelinit == 8) ? moneda($totalg) : '*****',
            ];
            $index++;

            $valores[$index] = [
                'id' => ($index + 1),
                'ccodcta' => ' ',
                'nombre' => ' ',
                '1' => " ",
                '2' => " ",
                '3' => " ",
                '4' => " ",
                '5' => " ",
                '6' => " ",
                '7' => " ",
                '8' => " ",
            ];
            $index++;
        }
    }

    $resultado = ($totali - $totalg);

    $valores[$index] = [
        'id' => ($index + 1),
        'ccodcta' => '++++++',
        'nombre' => 'RESULTADO DEL EJERCICIO',
        '1' => ($nivelinit == 1) ? moneda($resultado) : '*****',
        '2' => ($nivelinit == 2) ? moneda($resultado) : '*****',
        '3' => ($nivelinit == 3) ? moneda($resultado) : '*****',
        '4' => ($nivelinit == 4) ? moneda($resultado) : '*****',
        '5' => ($nivelinit == 5) ? moneda($resultado) : '*****',
        '6' => ($nivelinit == 6) ? moneda($resultado) : '*****',
        '7' => ($nivelinit == 7) ? moneda($resultado) : '*****',
        '8' => ($nivelinit == 8) ? moneda($resultado) : '*****',
    ];

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'data' => $valores,
        'keys' => $keys,
        'encabezados' => $encabezados,
    );
    echo json_encode($opResult);
    return;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info, $cuentasingreso, $cuentasegreso, $nivelinit, $nivelfin)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];
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
        public $nivelfin;
        public $nivelinit;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $nivelinit, $nivelfin)
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
            $this->nivelfin = $nivelfin;
            $this->nivelinit = $nivelinit;
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
            $this->Image($this->pathlogoins, 10, 13, 33);

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 9);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(10);

            $this->SetFont($fuente, 'B', 9);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'ESTADO DE RESULTADOS ' . $this->datos[0], 0, 1, 'C', true);
            $this->Cell(0, 5, '(CIFRAS EN QUETZALES)', 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 195 / ($this->nivelfin - $this->nivelinit + 4);

            $this->Cell($ancho_linea, 5, 'CUENTA', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, 'DESCRIPCION', 'B', 0, 'L');
            $monbal = ['1', '2', '3', '4', '5', '6', '7', '8'];
            $niv = $this->nivelfin;
            while ($niv >= $this->nivelinit) {
                $this->CellFit($ancho_linea, 5, $monbal[$niv - 1], 'B', 0, 'R', 0, '', 1, 0);
                $niv--;
            }

            $this->Ln(6);
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
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $nivelinit, $nivelfin);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $pdf->SetFont($fuente, '', 8);

    $ancho_linea2 = 195 / ($nivelfin - $nivelinit + 4);

    $cuentas = $datos[1];
    $printresumeningreso = false;
    $nivelant = 0;
    $totali = 0;
    $totalg = 0;

    foreach ($cuentas as $key => $account) {
        $id = $account["id"];
        $cuenta = $account["ccodcta"];
        $nombre = $account["cdescrip"];
        $nivel = strlen($cuenta);
        $monto = 0;

        foreach ($registro as $reg) {
            if ($cuenta == substr($reg["ccodcta"], 0, $nivel)) {
                $sal = $reg["sumdeb"] - $reg["sumhab"];
                $monto = $monto + $sal;
            }
        }
        $clase = substr($cuenta, 0, 1);
        $result = array_search($clase, array_column($cuentasingreso, 'clase'));
        if ($result !== false) {
            $monto *= -1;
        }

        $nivel1 = ($nivel == 1) ? number_format($monto, 2, '.', ',') : " ";
        $nivel2 = ($nivel == 2 || $nivel == 3) ? number_format($monto, 2, '.', ',') : " ";
        $nivel3 = ($nivel == 4 || $nivel == 5) ? number_format($monto, 2, '.', ',') : " ";
        $nivel4 = ($nivel == 6 || $nivel == 7) ? number_format($monto, 2, '.', ',') : " ";
        $nivel5 = ($nivel == 8 || $nivel == 9) ? number_format($monto, 2, '.', ',') : " ";
        $nivel6 = ($nivel == 10 || $nivel == 11) ? number_format($monto, 2, '.', ',') : " ";
        $nivel7 = ($nivel == 12 || $nivel == 13) ? number_format($monto, 2, '.', ',') : " ";
        $nivel8 = ($nivel == 14 || $nivel == 15) ? number_format($monto, 2, '.', ',') : " ";

        if ($monto != 0) {
            $monbal = [$nivel1, $nivel2, $nivel3, $nivel4, $nivel5, $nivel6, $nivel7, $nivel8];
            $niveles = [[1, 2, 4, 6, 8, 10, 12, 14], [1, 3, 5, 7, 9, 11, 13, 15]];
            $niv = $nivelfin;
            $flag = false;
            //SE VAN A IMPRIMIR LOS NIVELES QUE ESTAN EN EL RANGO SELECCIONADO, PARA ESO SE ACTIVA EL FLAG
            while ($niv >= $nivelinit) {
                if ($niveles[0][$niv - 1] == $nivel || $niveles[1][$niv - 1] == $nivel) {
                    $flag = true;
                }
                $niv--;
            }
            //SE VAN A IMPRIMIR LOS NIVELES QUE ESTAN EN EL RANGO SELECCIONADO SI LA BANDERA ESTA ACTIVA
            if ($flag) {
                if ($nivel < $nivelant) {
                    $pdf->Ln(3);
                }
                $pdf->CellFit($ancho_linea2, $tamanio_linea, $cuenta, '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, decode_utf8($nombre), '', 0, 'L', 0, '', 1, 0);

                $niv = $nivelfin;
                while ($niv >= $nivelinit) {
                    $pdf->CellFit($ancho_linea2, $tamanio_linea, $monbal[$niv - 1], '', 0, 'R', 0, '', 1, 0);
                    $niv--;
                }
                $pdf->Ln(3);
                $nivelant = $nivel;
            }

            //***************SUMATORIAS*********************
            $result = array_search($cuenta, array_column($cuentasingreso, 'clase'));
            if ($result !== false) {
                $totali = $totali + $monto;
            }
            $result = array_search($cuenta, array_column($cuentasegreso, 'clase'));
            if ($result !== false) {
                $totalg = $totalg + $monto;
            }

            $pdf->Ln(1);
        }

        if ($key != array_key_last($cuentas)) {
            $nextcuenta = $cuentas[$key + 1]["ccodcta"];
            $result = array_search($nextcuenta, array_column($cuentasegreso, 'clase'));

            if ($result !== false && !$printresumeningreso) {
                $printresumeningreso = true;
                $pdf->SetFont($fuente, 'B', 8);
                $pdf->Ln(2);
                $pdf->CellFit($ancho_linea2 * 3, $tamanio_linea, 'TOTAL INGRESOS: ', '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit(($nivelfin - $nivelinit + 1) * $ancho_linea2, $tamanio_linea, number_format($totali, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
                $pdf->SetFont($fuente, '', 8);
                $pdf->Ln(2);
            }
            // if (strlen($nextcuenta) < $nivel && $monto != 0) {
            //     $pdf->Ln(2);
            // }
        } else {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->CellFit($ancho_linea2 * 3, $tamanio_linea, 'TOTAL EGRESOS: ', '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(($nivelfin - $nivelinit + 1) * $ancho_linea2, $tamanio_linea, number_format($totalg, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            $pdf->Ln(3);
        }
    }

    $pdf->Ln(4);
    $pdf->CellFit($ancho_linea2 * 3, $tamanio_linea, 'RESULTADO NETO: ', '', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(($nivelfin - $nivelinit + 1) * $ancho_linea2, $tamanio_linea, number_format($totali - $totalg, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(4);
    $pdf->SetFont($fuente, '', 9);
    // $pdf->MultiCell(0, 4, strtoupper(decode_utf8('El infrascrito perito contador Cristian Erika Rafael Matias, con número de registro 77631862 ante la Superintendencia de Administración Tributaria certifica: que el estado de resultados que antecede muestra resultado de las operaciones de la cooperativa por el periodo comprendido ' . $datos[0] . ', el cual fue obtenido de los registros contables de la entidad.')));
    $pdf->MultiCell(0, 4, strtoupper(utf8_decode('El infrascrito perito contador Cristian Erika Rafael Matias, con número de registro 77631862 extendida por la Superintendencia de Administración Tributaria SAT, certifica QUE: LOS DATOS QUE CONTIENE EL PRESENTE ESTADO DE SITUACION GENERAL, SON REALES DE ACUERDO A PRINCIPIOS DE CONTABILIDAD GENERALMENTE ACEPTADAS, REFLEJANDO CLARAMENTE LA SITUACION FINANCIERA DE LA ' . $institucion . ', DETERMINADO AL ' . $datos[0])));
    $pdf->firmas(2, ['CONTADOR', 'REPRESENTANTE LEGAL']);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Estado de resultados",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $datos, $cuentasingreso, $cuentasegreso)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Estado de resultados");

    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(65);
    $activa->getColumnDimension("C")->setWidth(20);
    $activa->getColumnDimension("D")->setWidth(20);
    $activa->getColumnDimension("E")->setWidth(20);
    $activa->getColumnDimension("F")->setWidth(20);
    $activa->getColumnDimension("G")->setWidth(20);
    $activa->getColumnDimension("H")->setWidth(20);
    $activa->getColumnDimension("I")->setWidth(20);

    $activa->setCellValue('A1', 'CUENTA');
    $activa->setCellValue('B1', 'NOMBRE CUENTA');
    
    $activa->setCellValue('C1', '8');
    $activa->setCellValue('D1', '7');
    $activa->setCellValue('E1', '6');
    $activa->setCellValue('F1', '5');
    $activa->setCellValue('G1', '4');
    $activa->setCellValue('H1', '3');
    $activa->setCellValue('I1', '2');
    $activa->setCellValue('J1', '1');
    //-------
    $nivelant = 0;
    $totali = 0;
    $totalg = 0;
    $cuentas = $datos[0];
    $f = 0;
    $i = 2;
    while ($f < count($cuentas)) {
        $id = $cuentas[$f]["id"];
        $cuenta = $cuentas[$f]["ccodcta"];
        $nombre = $cuentas[$f]["cdescrip"];
        $nivel = strlen($cuenta);
        $monto = 0;
        //BUSCAR CUENTA EN LOS MOVIMIENTOS
        $fila = 0;
        while ($fila < count($registro)) {
            $codcta = $registro[$fila]["ccodcta"];
            $sdebe = $registro[$fila]["sumdeb"];
            $shaber = $registro[$fila]["sumhab"];

            if ($cuenta == substr($codcta, 0, $nivel)) {
                $sal = $sdebe - $shaber;
                $monto = $monto + $sal;
            }
            $fila++;
        }
        $clase = substr($cuenta, 0, 1);
        $result = array_search($clase, array_column($cuentasingreso, 'clase'));
        if ($result !== false) {
            $monto = $monto * (-1);
        }

        $nivel1 = ($nivel == 1) ? $monto : " ";
        $nivel2 = ($nivel == 2 || $nivel == 3) ? $monto : " ";
        $nivel3 = ($nivel == 4 || $nivel == 5) ? $monto : " ";
        $nivel4 = ($nivel == 6 || $nivel == 7) ? $monto : " ";
        $nivel5 = ($nivel == 8 || $nivel == 9) ? $monto : " ";
        $nivel6 = ($nivel == 10 || $nivel == 11) ? $monto : " ";
        $nivel7 = ($nivel == 12 || $nivel == 13) ? $monto : " ";
        $nivel8 = ($nivel == 14 || $nivel == 15) ? $monto : " ";

        if ($monto != 0) {
            if ($nivel < $nivelant) {
                $i++;
            }
            $nivelant = $nivel;
            $activa->setCellValueExplicit('A' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('B' . $i, $nombre);
            $activa->setCellValue('C' . $i, $nivel8);
            $activa->setCellValue('D' . $i, $nivel7);
            $activa->setCellValue('E' . $i, $nivel6);
            $activa->setCellValue('F' . $i, $nivel5);
            $activa->setCellValue('G' . $i, $nivel4);
            $activa->setCellValue('H' . $i, $nivel3);
            $activa->setCellValue('I' . $i, $nivel2);
            $activa->setCellValue('J' . $i, $nivel1);
            

            //***************SUMATORIAS*********************
            $result = array_search($cuenta, array_column($cuentasingreso, 'clase'));
            if ($result !== false) {
                $totali = $totali + $monto;
            }
            $result = array_search($cuenta, array_column($cuentasegreso, 'clase'));
            if ($result !== false) {
                $totalg = $totalg + $monto;
            }
            $i++;
        }

        $f++;
    }


    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Estado de resultados",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
