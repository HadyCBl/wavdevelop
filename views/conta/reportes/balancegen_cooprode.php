<?php
/**
 * Este archivo sirve para generar el balance general en el formato que se especifique: PDF, XLSX o ver no más.
 * 
 * - Verifica si la solicitud es de tipo GET y redirige a una página 404 si es así.
 * - Inicia la sesión y verifica si la sesión del usuario ha expirado.
 * - Incluye archivos de configuración y funciones necesarias para la generación del balance.
 * - Configura la conexión a la base de datos.
 * - Utiliza las bibliotecas FPDF y PhpSpreadsheet para la generación de reportes en PDF y XLSX.
 * - Establece la zona horaria a 'America/Guatemala'.
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
    +++++++++++++++++++++++++++++++++++++++++ SE RECIBEN LOS DATOS +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ [`finicio`,`ffin`],[`codofi`,`fondoid`],[`rfondos`,`ragencia`,[]] +++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    /**
     * Este bloque de script procesa los datos enviados a través de una solicitud POST y realiza varias validaciones.
     * 
     * Variables de entrada:
     * - $datos: Array que contiene los datos enviados desde el formulario.
     * - $inputs: Array que contiene las fechas de inicio y fin.
     * - $selects: Array que contiene los valores seleccionados en los campos select.
     * - $radios: Array que contiene los valores seleccionados en los campos radio.
     * - $tipo: Tipo de reporte solicitado.
     * 
     * Validaciones realizadas:
     * 1. Validación de formato de fecha para las fechas de inicio y fin.
     * 2. Validación de rango de fechas (la fecha de inicio no puede ser mayor que la fecha de fin).
     * 3. Validación de que las fechas pertenezcan al mismo año.
     * 4. Validación de rango de niveles (el nivel inicial no puede ser mayor que el nivel final).
     * 
     * Respuestas en caso de error:
     * - Si las fechas no tienen el formato correcto, se devuelve un mensaje de error indicando "Fecha inválida, ingrese una fecha correcta".
     * - Si el rango de fechas es inválido, se devuelve un mensaje de error indicando "Rango de fechas inválido".
     * - Si las fechas no pertenecen al mismo año, se devuelve un mensaje de error indicando "Las fechas tienen que ser del mismo año".
     * - Si el rango de niveles es inválido, se devuelve un mensaje de error indicando "Rango de niveles inválido".
     */

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

list($finicio, $ffin) = $inputs;
list($codofi, $fondoid, $nivelinit, $nivelfin) = $selects;
list($rfondo, $ragencia) = $radios;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++
    +++[`finicio`,`ffin`],[`codofi`,`fondoid`,`nivelinit`,`nivelfin`],[`rfondos`,`ragencia`],[$idusuario ] +++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d')) {
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
$mesfin = date("m", $fechafin);
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

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++ CONSULTANDO EN LA BD XDXD ++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

/**
 * Este bloque de script se encarga de realizar las consultas necesarias en la bd basadas en los parámetros proporcionados.
 * 
 * Variables:
 * - $condi: Condición adicional para la consulta SQL basada en los parámetros $rfondo y $ragencia.
 * - $parameters: Array de parámetros para las consultas SQL.
 * - $titlereport: Título del reporte con la fecha final formateada.
 * - $strquery: Consulta SQL para obtener los movimientos contables.
 * - $query2: Consulta SQL para obtener la nomenclatura contable.
 * - $showmensaje: Bandera para mostrar mensajes de error.
 * 
 * Funcionalidad:
 * 1. Abre una conexión a la base de datos.
 * 2. Verifica si hay cuentas configuradas para el cálculo del balance.
 * 3. Obtiene los datos de movimientos contables.
 * 4. Si no hay datos, lanza una excepción.
 * 5. Obtiene las cuentas contables para el estado de resultados.
 * 6. Obtiene la nomenclatura contable.
 * 7. Si no hay nomenclatura, lanza una excepción.
 * 8. Obtiene la información de la institución.
 * 9. Si no se encuentra la institución, lanza una excepción.
 * 10. Cierra la conexión a la base de datos.
 * 11. Maneja excepciones y errores, registrando los errores y mostrando mensajes apropiados.
 * 12. Devuelve el resultado de la operación en formato JSON.
 * 
 * Excepciones:
 * - Si no hay cuentas configuradas para el cálculo del balance.
 * - Si no hay datos en la fecha indicada.
 * - Si no se encuentran cuentas contables.
 * - Si la institución asignada a la agencia no se encuentra.
 */
$condi = (($rfondo == "anyf") ? " AND id_fuente_fondo=? " : "") . (($ragencia == "anyofi") ? " AND id_agencia2=? " : "");
$parameters = [$finicio, $ffin];

if ($rfondo == "anyf") {
    $parameters[] = $fondoid;
}
if ($ragencia == "anyofi") {
    $parameters[] = $codofi;
}

$titlereport = "AL " . setdatefrench($ffin);

$strquery = "SELECT ccodcta,id_ctb_nomenclatura,SUM(debe) sumdeb, SUM(haber) sumhab from ctb_diario_mov 
    WHERE estado=1 AND (feccnt BETWEEN ? AND ?) 
    AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipopol != 13 and (id_tipo>=1 AND id_tipo<=3) || id_tipo=6) $condi
    GROUP BY ccodcta ORDER BY ccodcta;";

$query2 = "SELECT cg.id_tipo idparam,cc.id,ccodcta,cdescrip from ctb_nomenclatura cc 
INNER JOIN ctb_parametros_general cg ON cg.id_ctb_nomenclatura=cc.id
WHERE cg.id_tipo>=1 AND cg.id_tipo<=2 AND cc.estado=1";


$showmensaje = false;
try {
    $database->openConnection();

    $parametros = $database->selectColumns("ctb_parametros_cuentas", ["id_tipo", "clase"], "id_tipo>=1 AND id_tipo<=6");
    if (empty($parametros)) {
        $showmensaje = true;
        throw new Exception("No hay cuentas configuradas para el calculo del Balance.");
    }

    $ctbmovdata = $database->getAllResults($strquery, $parameters);
    if (empty($ctbmovdata)) {
        $showmensaje = true;
        throw new Exception("No hay datos en la fecha indicada");
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++ ESTADO DE RESULTADOS ++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $cuentacontableer = $database->getAllResults($query2);
    if (!empty($cuentacontableer)) {
        $query = "SELECT ccodcta,id_ctb_nomenclatura,SUM(debe) sumdeb, SUM(haber) sumhab,SUM(debe)-SUM(haber) saldo from ctb_diario_mov 
        WHERE estado=1 AND (feccnt BETWEEN ? AND ?) 
        AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE (id_tipo>=4 AND id_tipo<=5)) $condi
        GROUP BY ccodcta ORDER BY ccodcta;";

        $registroer = $database->getAllResults($query, $parameters);
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++++++++++ CUENTAS CONTABLES ++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $strquery = "SELECT id,ccodcta,cdescrip from ctb_nomenclatura 
    WHERE estado=1 AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE (id_tipo>=1 AND id_tipo<=3) || id_tipo=6) AND LENGTH(ccodcta) <=15 
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
    ++++++++++++++++++++++++++++++++++ CUENTAS PARAMETRIZADAS PAL BALANCE ++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/**
 * Filtra y clasifica las cuentas parametrizadas para el balance general.
 * 
 * Variables:
 * - $cuentasactivo: Contiene las cuentas de tipo activo (id_tipo = 1).
 * - $reguladorasactivo: Contiene las cuentas reguladoras de activo (id_tipo = 6).
 * - $cuentaspasivo: Contiene las cuentas de tipo pasivo (id_tipo = 2).
 * - $cuentascapital: Contiene las cuentas de tipo capital (id_tipo = 3).
 * - $cuentasingreso: Contiene las cuentas de tipo ingreso (id_tipo = 4).
 * - $cuentasegreso: Contiene las cuentas de tipo egreso (id_tipo = 5).
 * 
 * @param array $parametros Array de parámetros que contiene las cuentas con sus respectivos tipos.
 * @return void
 */

$cuentasactivo = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 1 ? $var : null;
});
$reguladorasactivo = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 6;
});
$cuentaspasivo = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 2;
});
$cuentascapital = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 3;
});
$cuentasingreso = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 4;
});
$cuentasegreso = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 5;
});

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++  RESULTADO DEL EJERCICIO DEL ER FECHA ACTUAL +++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/**
 * Calcula el resultado del estado de resultados (ER) que se va a colocar en el balance general.
 *
 * Variables:
 * - $resultadoer: Resultado del estado de resultados (ER).
 * - $registroer: Registro del estado de resultados.
 * - $cuentasingreso: Lista de cuentas de ingreso.
 * - $cuentasegreso: Lista de cuentas de egreso.
 * - $cuentacontableer: Lista de cuentas contables del estado de resultados.
 * - $ctbmovdata: Datos de movimiento contable.
 *
 * Funciones utilizadas:
 * - calculo($registroer, $clase, $tipo): Calcula el saldo de una cuenta.
 * - buscarDatoPorId($array, $valor, $campoBuscar, $campoRetornar): Busca un dato en un array por un campo específico.
 *
 * Proceso:
 * 1. Verifica si $registroer está definido y no está vacío [si se ha parametrizado una cuenta contable para mostrar en el balance 
 *          correspondiente al Resultado del ejercicio].
 * 2. Calcula el total de ingresos sumando los saldos de las cuentas de ingreso.
 * 3. Calcula el total de egresos sumando los saldos de las cuentas de egreso.
 * 4. Convierte los ingresos a negativo.
 * 5. Calcula el resultado del estado de resultados restando los egresos de los ingresos.
 * 6. Busca las cuentas de ganancia y pérdida en $cuentacontableer.
 * 7. Determina la cuenta contable a mostrar según el resultado del estado de resultados.
 * 8. Busca el registro de movimiento contable correspondiente en $ctbmovdata.
 * 9. Si no existe el registro, lo agrega a $ctbmovdata.
 * 10. Si existe el registro, actualiza los valores de sumdeb y sumhab.
 */
$resultadoer = 0;
if (isset($registroer) && !empty($registroer)) {
    $ingresos = 0;
    foreach ($cuentasingreso as $ingreso) {
        $ingresos += array_sum(array_column(calculo($registroer, $ingreso['clase'], 1), 'saldo'));
    }
    $egresos = 0;
    foreach ($cuentasegreso as $egreso) {
        $egresos += array_sum(array_column(calculo($registroer, $egreso['clase'], 1), 'saldo'));
    }
    $ingresos = $ingresos * (-1);

    $resultadoer = $ingresos - $egresos;

    $idcuentaganancia = buscarDatoPorId($cuentacontableer, 1, 'idparam', 'id');
    $idcuentaperdida = buscarDatoPorId($cuentacontableer, 2, 'idparam', 'id');

    $cuentaganancia = buscarDatoPorId($cuentacontableer, 1, 'idparam', 'ccodcta');
    $cuentaperdida = buscarDatoPorId($cuentacontableer, 2, 'idparam', 'ccodcta');
    $cuentacontableshow = ($resultadoer > 0) ? $cuentaganancia : $cuentaperdida;
    $idcuentacontableshow = ($resultadoer > 0) ? $idcuentaganancia : $idcuentaperdida;

    $keyregistro = buscarDatoPorId($ctbmovdata, ($resultadoer > 0) ? $idcuentaganancia : $idcuentaperdida, 'id_ctb_nomenclatura');
    if ($keyregistro == false) {
        $ctbmovdata[] = ['ccodcta' => $cuentacontableshow, 'id_ctb_nomenclatura' => $idcuentacontableshow, 'sumdeb' => $egresos, 'sumhab' => $ingresos];
    } else {
        $ctbmovdata[$keyregistro]['sumdeb'] += $egresos;
        $ctbmovdata[$keyregistro]['sumhab'] += $ingresos;
    }
}

//TIPO DE ARCHIVO A IMPRIMIR
switch ($tipo) {
    case 'show':
        showresults($ctbmovdata, $nomenclatura, $nivelinit, $nivelfin, $cuentasactivo, $cuentaspasivo, $cuentascapital, $reguladorasactivo, $cuentacontableer, $resultadoer);
        break;
    case 'xlsx';
        printxls($ctbmovdata, [$nomenclatura], $cuentasactivo, $cuentaspasivo, $cuentascapital, $reguladorasactivo, $cuentacontableer, $resultadoer);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport, $nomenclatura], $info, $nivelinit, $nivelfin, $cuentasactivo, $cuentaspasivo, $cuentascapital, $reguladorasactivo, $cuentacontableer, $resultadoer);
        break;
}

function showresults($registro, $cuentas, $nivelinit, $nivelfin, $cuentasactivo, $cuentaspasivo, $cuentascapital, $reguladorasactivo, $cuentacontableer, $resultadoer)
{
    /**
     * Genera un reporte de balance general configurada para mostrarla en la pantalla sin ningun formato.
     * 
     * Variables:
     * - $keys: Array de claves para las columnas de la tabla a mostrar.
     * - $encabezados: Array de encabezados para las columnas de la tabla a mostrar.
     * - $monbal: Array de niveles de cuentas contables soportados.
     * - $valores: Array que almacena los valores del reporte.
     * - $printresumenactivo: Bandera para imprimir el resumen de activos.
     * - $totalactivo: Total de activos.
     * - $totalpasivo: Total de pasivos.
     * - $totalcapital: Total de capital.
     * 
     * Procesamiento:
     * - Recorre cada cuenta y calcula el monto basado en los registros.
     * - Ajusta el monto si la cuenta pertenece a cuentas de Activo.
     *      + las cuentas de activo funcionan asi: DEBE - HABER
     *      + las cuentas de pasivo y patrimonio funcionan asi: HABER - DEBE
     * - Formatea el monto según el nivel de la cuenta.
     * - Verifica si el nivel de la cuenta está en el rango seleccionado para imprimir.
     * - Actualiza los valores del reporte y las sumatorias de activos, pasivos y capital.
     * - Imprime el resumen de activos si es necesario.
     * - Imprime el total de pasivos y capital al final del reporte.
     * 
     * @param array $cuentas Array de cuentas contables parametrizadas para el balance general.
     * @param array $registro Array de registros contables extraídos de ctb_diario y ctb_mov.
     * @param array $cuentasactivo Array de cuentas de la categoria de Activo.
     * @param array $reguladorasactivo Array de cuentas reguladoras del activo.
     * @param array $cuentaspasivo Array de cuentas del Pasvo.
     * @param array $cuentascapital Array de cuentas de capital o patrimonio.
     * @param int $nivelfin Nivel final a mostrar en el balance.
     * @param int $nivelinit Nivel inicial a mostrar en el balance.
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
    $printresumenactivo = false;
    $nivelant = 0;
    $totalactivo = 0;
    $totalpasivo = 0;
    $totalcapital = 0;

    $index = 0;

    foreach ($cuentas as $key => $account) {
        $id = $account["id"];
        $cuenta = $account["ccodcta"];
        $nombre = $account["cdescrip"];
        $nivel = strlen($cuenta);
        $monto = 0;

        foreach ($registro as $reg) {
            if ($cuenta == substr($reg["ccodcta"], 0, $nivel)) {
                $sal = $reg["sumhab"] - $reg["sumdeb"];
                $monto = $monto + $sal;
            }
        }
        $clase = substr($cuenta, 0, 1);
        $result = array_search($clase, array_column($cuentasactivo, 'clase'));
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
            $result = array_search($cuenta, array_column($cuentasactivo, 'clase'));
            if ($result !== false) {
                $totalactivo = $totalactivo + $monto;
            }
            $result = array_search($cuenta, array_column($reguladorasactivo, 'clase'));
            if ($result !== false) {
                $totalactivo = $totalactivo + $monto;
            }
            $result = array_search($cuenta, array_column($cuentaspasivo, 'clase'));
            if ($result !== false) {
                $totalpasivo = $totalpasivo + $monto;
            }
            $result = array_search($cuenta, array_column($cuentascapital, 'clase'));
            if ($result !== false) {
                $totalcapital = $totalcapital + $monto;
            }
        }

        if ($key != array_key_last($cuentas)) {
            $nextcuenta = $cuentas[$key + 1]["ccodcta"];
            $result = array_search($nextcuenta, array_column($cuentaspasivo, 'clase'));

            if ($result !== false && !$printresumenactivo) {
                $printresumenactivo = true;

                $valores[$index] = [
                    'id' => ($index + 1),
                    'ccodcta' => '++++++',
                    'nombre' => 'TOTAL ACTIVO',
                    '1' => ($nivelinit == 1) ? number_format($totalactivo, 2, '.', ',') : '*****',
                    '2' => ($nivelinit == 2) ? number_format($totalactivo, 2, '.', ',') : '*****',
                    '3' => ($nivelinit == 3) ? number_format($totalactivo, 2, '.', ',') : '*****',
                    '4' => ($nivelinit == 4) ? number_format($totalactivo, 2, '.', ',') : '*****',
                    '5' => ($nivelinit == 5) ? number_format($totalactivo, 2, '.', ',') : '*****',
                    '6' => ($nivelinit == 6) ? number_format($totalactivo, 2, '.', ',') : '*****',
                    '7' => ($nivelinit == 7) ? number_format($totalactivo, 2, '.', ',') : '*****',
                    '8' => ($nivelinit == 8) ? number_format($totalactivo, 2, '.', ',') : '*****',
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

            $segundaParte = $totalpasivo + $totalcapital;
            $valores[$index] = [
                'id' => ($index + 1),
                'ccodcta' => '++++++',
                'nombre' => 'TOTAL PASIVO Y CAPITAL',
                '1' => ($nivelinit == 1) ? number_format($segundaParte, 2, '.', ',') : '*****',
                '2' => ($nivelinit == 2) ? number_format($segundaParte, 2, '.', ',') : '*****',
                '3' => ($nivelinit == 3) ? number_format($segundaParte, 2, '.', ',') : '*****',
                '4' => ($nivelinit == 4) ? number_format($segundaParte, 2, '.', ',') : '*****',
                '5' => ($nivelinit == 5) ? number_format($segundaParte, 2, '.', ',') : '*****',
                '6' => ($nivelinit == 6) ? number_format($segundaParte, 2, '.', ',') : '*****',
                '7' => ($nivelinit == 7) ? number_format($segundaParte, 2, '.', ',') : '*****',
                '8' => ($nivelinit == 8) ? number_format($segundaParte, 2, '.', ',') : '*****',
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
function printpdf($registro, $datos, $info, $nivelinit, $nivelfin,  $cuentasactivo, $cuentaspasivo, $cuentascapital, $reguladorasactivo, $cuentacontableer, $resultadoer)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

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

            $this->SetFont($fuente, 'B', 8);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'BALANCE GENERAL ' . $this->datos[0], 0, 1, 'C', true);
            $this->Cell(0, 5, '(CIFRAS EN QUETZALES)', 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            /*  $ancho_linea = 21; */
            $ancho_linea = 195 / ($this->nivelfin - $this->nivelinit + 4);
            $this->Cell($ancho_linea, 5, 'CUENTA', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, 'DESCRIPCION', 'B', 0, 'L');
            $monbal = ['Nivel 1', 'Nivel 2', 'Nivel 3', 'Nivel 4', 'Nivel 5', 'Nivel 6', 'Nivel 7', 'Nivel 8'];
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
    $ancho_linea2 = 195 / ($nivelfin - $nivelinit + 4);
    $pdf->SetFont($fuente, '', 7);
    //1 3 4 6 8 10
    $printresumenactivo = false;
    $nivelant = 0;
    $totalactivo = 0;
    $totalpasivo = 0;
    $totalcapital = 0;
    $cuentas = $datos[1];
    $f = 0;
    while ($f < count($cuentas)) {
        $id = $cuentas[$f]["id"];
        $cuenta = trim($cuentas[$f]["ccodcta"]);
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
                $clase = substr($codcta, 0, 1);
                $result = array_search($clase, array_column($cuentasactivo, 'clase'));
                if ($result !== false) {
                    $sal = $sdebe - $shaber;
                } else {
                    $sal = $shaber - $sdebe;
                }
                // if (substr($codcta, 0, 1) >= 1 && substr($codcta, 0, 1) <= 2) {
                //     $sal = $sdebe - $shaber;
                // }
                // if (substr($codcta, 0, 1) >= 3 && substr($codcta, 0, 1) <= 5) {
                //     $sal = $shaber - $sdebe;
                // }
                $monto = $monto + $sal;
            }
            $fila++;
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
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, Utf8::decode($nombre), '', 0, 'L', 0, '', 1, 0);

                $niv = $nivelfin;
                while ($niv >= $nivelinit) {
                    $pdf->CellFit($ancho_linea2, $tamanio_linea, $monbal[$niv - 1], '', 0, 'R', 0, '', 1, 0);
                    $niv--;
                }
                $pdf->Ln(3);
                $nivelant = $nivel;
            }

            //***************SUMATORIAS*********************
            $result = array_search($cuenta, array_column($cuentasactivo, 'clase'));
            if ($result !== false) {
                $totalactivo = $totalactivo + $monto;
            }
            $result = array_search($cuenta, array_column($reguladorasactivo, 'clase'));
            if ($result !== false) {
                $totalactivo = $totalactivo + $monto;
            }
            $result = array_search($cuenta, array_column($cuentaspasivo, 'clase'));
            if ($result !== false) {
                $totalpasivo = $totalpasivo + $monto;
            }
            $result = array_search($cuenta, array_column($cuentascapital, 'clase'));
            if ($result !== false) {
                $totalcapital = $totalcapital + $monto;
            }
            //else {
            //     $sal = $shaber - $sdebe;
            // }

            // $activo = ($cuenta <= 2) ? $monto : 0;
            // $totalactivo = $totalactivo + $activo;

            // $pasivo = ($cuenta >= 3 && $cuenta <= 5) ? $monto : 0;
            // $totalpasivo = $totalpasivo + $pasivo;
        }
        $pdf->SetFont($fuente, 'B', 9);
        if ($f != array_key_last($cuentas)) {
            //----------
            $nextcuenta = $cuentas[$f + 1]["ccodcta"];
            $result = array_search($nextcuenta, array_column($cuentaspasivo, 'clase'));
            if ($result !== false && $printresumenactivo == false) {
                $printresumenactivo = true;
                $pdf->Ln(2);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, 'TOTAL ACTIVO', '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit(($nivelfin - $nivelinit + 1) * $ancho_linea2, $tamanio_linea, number_format($totalactivo, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
                $pdf->Ln(3);
            }
        } else {
            $pdf->Ln(2);
            $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, 'TOTAL PASIVO Y CAPITAL', '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(($nivelfin - $nivelinit + 1) * $ancho_linea2, $tamanio_linea, number_format($totalpasivo + $totalcapital, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            $pdf->Ln(3);
        }
        $pdf->SetFont($fuente, '', 8);
        $f++;
    }
    $pdf->Ln(4);
    $pdf->MultiCell(0, 4, strtoupper(Utf8::decode('El infrascrito perito contador EDUARDO XO CHOC, con número de registro 30309786 ante la Superintendencia de Administración Tributaria certifica: que el balance general que antecede muestra la situación financiera de la cooperativa ' . $datos[0] . ', el cual fue obtenido de los registros contables de la entidad.')));
    $pdf->Ln(15);
    $pdf->firmas(2, ['CONTADOR', 'REPRESENTANTE LEGAL']);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Balance General",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $datos,  $cuentasactivo, $cuentaspasivo, $cuentascapital, $reguladorasactivo, $cuentacontableer, $resultadoer)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("BalanceGeneral");

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
    $activa->setCellValue('C1', 'NIVEL 8');
    $activa->setCellValue('D1', 'NIVEL 7');
    $activa->setCellValue('E1', 'NIVEL 6');
    $activa->setCellValue('F1', 'NIVEL 5');
    $activa->setCellValue('G1', 'NIVEL 4');
    $activa->setCellValue('H1', 'NIVEL 3');
    $activa->setCellValue('I1', 'NIVEL 2');
    $activa->setCellValue('J1', 'NIVEL 1');

    $totalactivo = 0;
    $totalpasivo = 0;
    $totalcapital = 0;
    $nivelant = 0;
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
                $clase = substr($codcta, 0, 1);
                $result = array_search($clase, array_column($cuentasactivo, 'clase'));
                if ($result !== false) {
                    $sal = $sdebe - $shaber;
                } else {
                    $sal = $shaber - $sdebe;
                }
                // if (substr($codcta, 0, 1) >= 1 && substr($codcta, 0, 1) <= 2) {
                //     $sal = $sdebe - $shaber;
                // }
                // if (substr($codcta, 0, 1) >= 3 && substr($codcta, 0, 1) <= 5) {
                //     $sal = $shaber - $sdebe;
                // }
                $monto = $monto + $sal;
            }
            $fila++;
        }
        $nivel1 = ($nivel == 1) ? $monto : " ";
        $nivel2 = ($nivel == 2 || $nivel == 3) ? $monto : " ";
        $nivel3 = ($nivel == 4 || $nivel == 5) ? $monto : " ";
        $nivel4 = ($nivel == 6 || $nivel == 7) ? $monto : " ";
        $nivel5 = ($nivel == 8 || $nivel == 9) ? $monto : " ";
        $nivel6 = ($nivel == 10 || $nivel == 11) ? $monto : " ";
        $nivel7 = ($nivel == 12 || $nivel == 13) ? $monto : " ";
        $nivel8 = ($nivel == 14 || $nivel == 15) ? $monto : " ";

        // if ($f != array_key_last($cuentas)) {
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
            $result = array_search($cuenta, array_column($cuentasactivo, 'clase'));
            if ($result !== false) {
                $totalactivo = $totalactivo + $monto;
            }
            $result = array_search($cuenta, array_column($reguladorasactivo, 'clase'));
            if ($result !== false) {
                $totalactivo = $totalactivo + $monto;
            }
            $result = array_search($cuenta, array_column($cuentaspasivo, 'clase'));
            if ($result !== false) {
                $totalpasivo = $totalpasivo + $monto;
            }
            $result = array_search($cuenta, array_column($cuentascapital, 'clase'));
            if ($result !== false) {
                $totalcapital = $totalcapital + $monto;
            }
            $i++;
        }
        // }
        $f++;
    }
    //-------

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Balance General",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
