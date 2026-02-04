<?php
//FUNCIONES GENERALES BENEQ EL MERO MERO CUAO
//BUSCA IDIOMA SEGUN CODIGO

use Complex\Functions;
// use Luecano\NumeroALetras\NumeroALetras;

function func_idiomas($id, $general)
{
    $idioma = "";
    $datos = mysqli_query($general, "SELECT cdescri FROM `tn_EtniaIdioma` WHERE Id_EtinIdiom = $id");
    while ($idiomas = mysqli_fetch_array($datos)) {
        $idioma = encode_utf8($idiomas["cdescri"]);
    }
    return array($idioma);
}
//valida campos $params: array con los datos o variables a validar, $valida: la condicion no permitida, por ejemplo "" para vacio
function validarcampo($params, $valida)
{
    $mensaje = "1";
    foreach ($params as $paramet) {
        if ($paramet == $valida) {
            $mensaje = "Llene todos los campos obligatorios";
        }
    }
    return $mensaje;
}

//VALIDAR NUMERO MINIMO Y MAXIMO PARA ETIQUETA NUMBER
function validar_limites($min, $max, $valor)
{
    $mensaje = "1";
    if ($valor < $min || $valor > $max) {
        $mensaje = "Ingrese un valor a partir de " . $min . " hasta " . $max;
    }
    return $mensaje;
}

//BUSCA departamento SEGUN CODIGO
/**
 * @deprecated Esta función está obsoleta y puede ser eliminada en futuras versiones.
 */
function departamento($id)
{
    include '../../../includes/BD_con/db_con.php';
    $depar = " ";
    $datos = mysqli_query($general, "SELECT * FROM `departamentos` WHERE codigo_departamento = '$id'");
    while ($depa = mysqli_fetch_array($datos)) {
        $depar = encode_utf8($depa["nombre"]);
    }
    // mysqli_close($general);
    return $depar;
}

/**
 * @deprecated Esta función está obsoleta y puede ser eliminada en futuras versiones.
 */
//BUSCA municipio SEGUN CODIGO
function municipio($id)
{
    include '../../../includes/BD_con/db_con.php';
    $muni = " ";
    $datos = mysqli_query($general, "SELECT * FROM `municipios` WHERE codigo_municipio = '$id'");
    while ($row = mysqli_fetch_array($datos)) {
        $muni = encode_utf8($row["nombre"]);
    }
    //mysqli_close($general);
    return $muni;
}
//BUSCA TIPO CUENTA POR ID
function tipocuenta($idtip, $tabla, $campo_tabla, $conexion)
{
    $tipo = "";
    $consulta = mysqli_query($conexion, "SELECT `$campo_tabla` FROM `$tabla` WHERE `ccodtip`=$idtip");
    while ($registro = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $tipo = encode_utf8($registro[$campo_tabla]);
    }
    return $tipo;
}

//ultima linea impresa en la libreta
function lastnumlin($codigo_cuenta, $nlibreta, $tabla, $campo, $conexion)
{
    if ($tabla == 'ahomcta' || $tabla == 'aprcta') {
        $ultimowhere = "";
    } else {
        $ultimowhere = " AND cestado!=2";
    }
    // include '../../includes/BD_con/db_con.php';
    $consultanum = mysqli_query($conexion, "SELECT MAX(`numlinea`) AS campo FROM `$tabla` WHERE `$campo`=$codigo_cuenta AND `nlibreta`= '$nlibreta'" . $ultimowhere);
    $ultimonum = 0;
    while ($ultimo = mysqli_fetch_array($consultanum, MYSQLI_ASSOC)) {
        $ultimonum = ($ultimo['campo']);
    }
    // mysqli_close($conexion);
    return $ultimonum;
}

//ultima linea impresa en la libreta
function lastcorrel($codigo_cuenta, $nlibreta, $tabla, $campo, $conexion)
{
    if ($tabla == 'ahomcta' || $tabla == 'aprcta') {
        $ultimowhere = "";
    } else {
        $ultimowhere = " AND cestado!=2";
    }
    // include '../../includes/BD_con/db_con.php';
    $consultanum = mysqli_query($conexion, "SELECT MAX(`correlativo`) AS campo FROM `$tabla` WHERE `$campo`= $codigo_cuenta AND `nlibreta`= '$nlibreta'" . $ultimowhere);
    $ultimonum = 0;
    while ($ultimo = mysqli_fetch_array($consultanum, MYSQLI_ASSOC)) {
        $ultimonum = ($ultimo['campo']);
    }
    // mysqli_close($conexion);
    return $ultimonum;
}
//
function numfront($tipcuenta, $tabla)
{
    include '../../includes/BD_con/db_con.php';
    $consultanum = mysqli_query($conexion, "SELECT `numfront` FROM `$tabla` WHERE `ccodtip`=$tipcuenta");
    $numfront = 0;
    while ($ultimo = mysqli_fetch_array($consultanum, MYSQLI_ASSOC)) {
        $numfront = ($ultimo['numfront']);
    }
    mysqli_close($conexion);
    return $numfront;
}

function numdorsal($tipcuenta, $tabla)
{
    include '../../includes/BD_con/db_con.php';
    $consultanum = mysqli_query($conexion, "SELECT `numdors` FROM `$tabla` WHERE `ccodtip`=$tipcuenta");
    $numdors = 0;
    while ($ultimo = mysqli_fetch_array($consultanum, MYSQLI_ASSOC)) {
        $numdors = ($ultimo['numdors']);
    }
    mysqli_close($conexion);
    return $numdors;
}

//BUSCA parentesco SEGUN CODIGO
function parenteco($id)
{
    include '../../includes/BD_con/db_con.php';
    $parent = "";
    $datos = mysqli_query($conexion, "SELECT * FROM `tb_parentescos` WHERE id = $id");
    while ($row = mysqli_fetch_array($datos)) {
        $parent = encode_utf8($row["descripcion"]);
    }
    mysqli_close($conexion);
    return $parent;
}

//funcion para crear un correlativo total y parcial (AHOMCTA CODIGOS DE CUENTA)
function correlativo_general($nombre_tabla, $camp_tabla, $tipo_tabla, $campo_agencia, $tipo_cuenta, $conexion)
{
    //AGENCIA 
    $query = mysqli_query($conexion, "SELECT `$campo_agencia` agencia FROM `$tipo_tabla` WHERE ccodtip=$tipo_cuenta");
    $agencia = "001";
    while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
        $agencia = $row['agencia'];
    }
    //CORRELATIVO
    $consulta = mysqli_query($conexion, "SELECT MAX(SUBSTR(`$camp_tabla`,9,6)) campo FROM `$nombre_tabla` WHERE SUBSTR(`$camp_tabla`,7,2)=$tipo_cuenta");
    $ultimocorrel = "0";
    while ($ultimo = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $ultimocorrel = $ultimo['campo'];
    }
    $correlactual = ((int)$ultimocorrel) + 1;
    //genera codigo
    $generar = '001' . $agencia . $tipo_cuenta . (sprintf('%06d', $correlactual));
    return array($correlactual, $generar);
}

function dias_dif($fec_ini, $fec_fin)
{
    $dateDifference = abs(strtotime($fec_fin) - strtotime($fec_ini));
    $dias_diferencia = $dateDifference / (60 * 60 * 24);
    //$dias_diferencia = abs($dias_diferencia); //valor absoluto y quitar posible negativo
    $dias_diferencia = floor($dias_diferencia); //quito los decimales a los días de diferencia
    return $dias_diferencia;
}
//GASTOS EN CUOTAS
function gastoscuota($idproducto, $idc, $conexion)
{
    $consulta = mysqli_query($conexion, "SELECT cg.*, cm.CCODPRD, cm.MonSug, cm.CodCli,tipg.nombre_gasto,cm.NtipPerC tiperiodo,cm.noPeriodo,cl.short_name  FROM cremcre_meta cm 
    INNER JOIN cre_productos_gastos cg ON cm.CCODPRD=cg.id_producto 
    INNER JOIN cre_tipogastos tipg ON tipg.id=cg.id_tipo_deGasto
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    WHERE cm.CCODCTA='$idc' AND cm.CCODPRD='$idproducto' AND tipo_deCobro=2 AND cg.estado=1");
    $datosgastos[] = [];
    $total = 0;
    $i = 0;
    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $datosgastos[$i] = $fila;
        $i++;
    }
    if ($i == 0) {
        return null;
    }
    return $datosgastos;
}

/**
 * @deprecated funcion legacy para obtener numero de poliza, usar \Micro\Helpers\Beneq::getNumcom en su lugar
 * @param mixed $userid
 * @param mixed $conexion
 */
function getnumcom($userid, $conexion)
{
    $numcom = 'Query error';
    $datos = mysqli_query($conexion, "SELECT ctb_codigo_poliza(" . $userid . ") numcom");
    while ($row = mysqli_fetch_array($datos)) {
        $numcom = $row["numcom"];
    }
    return $numcom;
}
function getnumcompdo($userid, $database)
{
    $result = $database->getAllResults("SELECT ctb_codigo_poliza(?) numcom", [$userid]);
    if (empty($result)) {
        $numcom = 'Query error';
    } else {
        $numcom = $result[0]['numcom'];
    }
    return $numcom;
}
//GENERACION DE CODIGO DE CUENTA DE CREDITO ANTERIOR, YA NO SE UTILIZA
function getcrecodcta($userid, $tipo, $conexion)
{
    $ccodcta = "Error en la generacion del codigo de cuenta";
    $flag = 0;
    $datos = mysqli_query($conexion, "SELECT cre_cod_cuenta(" . $userid . ",'" . $tipo . "') ccodcta");
    while ($row = mysqli_fetch_array($datos)) {
        $flag = $row["ccodcta"];
    }
    $ccodcta = ($flag == 0) ? $ccodcta : $flag;
    $flag = ($flag == 0) ? 0 : 1;
    return [$flag, $ccodcta];
}
//GENERACION DE CODIGO DE CUENTA DE CREDITO
function getcrecodcuenta($agenciaid, $tipo, $conexion)
{
    $ccodcta = "Error en la generacion del codigo de cuenta";
    $flag = 0;
    $datos = mysqli_query($conexion, "SELECT cre_crecodcta(" . $agenciaid . ",'" . $tipo . "') ccodcta");
    while ($row = mysqli_fetch_array($datos)) {
        $flag = $row["ccodcta"];
    }
    $ccodcta = ($flag == 0) ? $ccodcta : $flag;
    $flag = ($flag == 0) ? 0 : 1;
    return [$flag, $ccodcta];
}
//GENERACION DE CODIGO DE CUENTA DE AHORRO
function getccodaho($agenciaid, $tipo, $conexion)
{
    $ccodcta = "Error en la generacion del codigo de cuenta";
    $flag = 0;
    $datos = mysqli_query($conexion, "SELECT aho_ccodaho(" . $agenciaid . ",'" . $tipo . "') ccodaho");
    while ($row = mysqli_fetch_array($datos)) {
        $flag = $row["ccodaho"];
    }
    $ccodcta = ($flag == 0) ? $ccodcta : $flag;
    $flag = ($flag == 0) ? 0 : 1;
    return [$flag, $ccodcta];
}
function getccodahoPDO($agenciaid, $tipo, $database)
{
    try {
        $result = $database->getAllResults("SELECT aho_ccodaho(?,?) AS ccodaho", [$agenciaid, $tipo]);
        if (empty($result)) {
            throw new Exception("Error en la generacion del codigo de cuenta");
        }
        return $result[0]['ccodaho'];
    } catch (Exception $e) {
        throw new Exception($e->getMessage());
    }
}

//GENERACION DE CODIGO DE CUENTA DE APORTACIONES
function getccodaport($agenciaid, $tipo, $conexion)
{
    $ccodcta = "Error en la generacion del codigo de cuenta";
    $flag = 0;
    $datos = mysqli_query($conexion, "SELECT apr_ccodaho(" . $agenciaid . ",'" . $tipo . "') ccodaho");
    while ($row = mysqli_fetch_array($datos)) {
        $flag = $row["ccodaho"];
    }
    $ccodcta = ($flag == 0) ? $ccodcta : $flag;
    $flag = ($flag == 0) ? 0 : 1;
    return [$flag, $ccodcta];
}
//GENERACION DE CODIGO DE CLIENTE
function getcodcli($userid, $conexion)
{
    $codcli = "Error en la generacion del codigo del Cliente";
    $flag = 0;
    $datos = mysqli_query($conexion, "SELECT cli_codcliente(" . $userid . ") codcli");
    while ($row = mysqli_fetch_array($datos)) {
        $flag = $row["codcli"];
    }
    $codcli = ($flag == 0) ? $codcli : $flag;
    $flag = ($flag == 0) ? 0 : 1;
    return [$flag, $codcli];
}
//  nueva FUNCION PARA GENERAR ID CLIENTE POR LA AGENCIA  getcodcli() SERA DESACTUALIZADO 
function cli_gencodcliente($agencia, $conexion)     /* ᕕ(⌐■_■)ᕗ ♪♬ */
{
    $codcli = "Error en la generacion del codigo del Cliente";
    $flag = 0;
    $datos = mysqli_query($conexion, "SELECT cli_gencodcliente(" . $agencia . ") codcli");
    while ($row = mysqli_fetch_array($datos)) {
        $flag = $row["codcli"];
    }
    $codcli = ($flag == 0) ? $codcli : $flag;
    $flag = ($flag == 0) ? 0 : 1;
    return [$flag, $codcli];
}
function cli_gencodclientePDO($agencia, $database)     /* ᕕ(⌐■_■)ᕗ ♪♬ */
{
    // $codcli = "Error en la generacion del codigo del Cliente";
    // $flag = 0;
    // $datos = mysqli_query($conexion, "SELECT cli_gencodcliente(" . $agencia . ") codcli");
    // while ($row = mysqli_fetch_array($datos)) {
    //     $flag = $row["codcli"];
    // }
    // $codcli = ($flag == 0) ? $codcli : $flag;
    // $flag = ($flag == 0) ? 0 : 1;
    // return [$flag, $codcli];
    $showmensaje = false;
    try {
        $result = $database->getAllResults("SELECT cli_gencodcliente(?) AS codcli", [$agencia]);
        if (empty($result)) {
            $showmensaje = true;
            throw new Exception("Error en la generacion del codigo del Cliente");
        }
        return $result[0]['codcli'];
    } catch (Exception $e) {
        $showmensaje = ($showmensaje || $e->getCode() == 1);
        $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
        if (!$showmensaje) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        }
        $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        throw new Exception($mensaje, $codigoDevuelto);
    } finally {
        //$database->closeConnection();
    }
}


function getnumcnrocuo($ccodcta, $conexion)
{
    $cnrocuo = 'Query error';
    $datos = mysqli_query($conexion, "SELECT IFNULL(MAX(ck.CNROCUO),0)+1 AS correlrocuo FROM CREDKAR ck WHERE ck.CCODCTA='" . $ccodcta . "'");
    while ($row = mysqli_fetch_array($datos)) {
        $cnrocuo = $row["correlrocuo"];
    }
    return $cnrocuo;
}
//COMPROBACION DEL MES CONTABLE
function comprobar_cierre($userid, $fecha, $conexion)
{
    $mensajes = ['Mes Contable Cerrado, no se puede completar la operacion', 'Mes abierto :)', 'Oficina no existe o usuario invalido', 'Mes contable no existe', 'Estado del mes contable invalido'];
    $flag = 0;
    $estado = 0;
    $datos = mysqli_query($conexion, "SELECT comprobar_cierre(" . $userid . ",'" . $fecha . "') estado");
    while ($row = mysqli_fetch_array($datos)) {
        $flag = $row["estado"];
    }
    $estado = ($flag == 1) ? 1 : 0;
    return [$estado, $mensajes[$flag]];
}
//COMPROBACION DEL MES CONTABLE
function comprobar_cierrePDO($userid, $fecha, $database)
{
    try {
        $mensajes = ['Mes Contable Cerrado, no se puede completar la operacion', 'Mes abierto :)', 'Oficina no existe o usuario invalido', 'Mes contable no existe', 'Estado del mes contable invalido'];
        $result = $database->getAllResults("SELECT comprobar_cierre(?,?) AS estado", [$userid, $fecha]);
        if (empty($result)) {
            $estado = 0;
            $flag = 0;
            throw new Exception("Error en la consulta de comprobar cierre");
        }
        $flag = $result[0]['estado'];
        $estado = ($flag == 1) ? 1 : 0;
    } catch (Exception $e) {
        $mensaje_error = $e->getMessage();
        return [0, $mensaje_error];
    } finally {
        //$database->closeConnection();
    }
    return [$estado, $mensajes[$flag]];
}
//funcion para obtener el registro insertado
function get_id_insertado($conexion)
{
    $id = 'Query error';
    $datos = mysqli_query($conexion, "SELECT LAST_INSERT_ID() AS last_id");
    while ($row = mysqli_fetch_array($datos)) {
        $id = $row["last_id"];
    }
    return $id;
}

//FUNCION PARA OBTENER EL ID DE AHOMCTB, CUENTA1, CUENTA2
function get_ctb_nomenclatura($tabla, $campcondi2, $idcondi1, $idcondi2, $conexion)
{
    $id = 'X';
    $cuenta1 = 'X';
    $cuenta2 = 'X';
    $datos = mysqli_query($conexion, "SELECT ctb.id, ctb.id_cuenta1, ctb.id_cuenta2 FROM `$tabla` ctb WHERE ctb.id_tipo_cuenta = $idcondi1 AND ctb.`$campcondi2`=$idcondi2");
    while ($row = mysqli_fetch_array($datos)) {
        $id = $row["id"];
        $cuenta1 = $row["id_cuenta1"];
        $cuenta2 = $row["id_cuenta2"];
    }
    return array($id, $cuenta1, $cuenta2);
}

//FUNCION PARA OBTENER EL ID DE AHOMCTB, CUENTA1, CUENTA2
function get_ctb_nomenclatura2($tabla, $campcondi2, $idcondi1, $idcondi2, $id_reg_ant, $conexion)
{
    $id = 'X';
    $datos = mysqli_query($conexion, "SELECT ctb.id FROM `$tabla` ctb WHERE ctb.id_tipo_cuenta = '$idcondi1' AND ctb.`$campcondi2`='$idcondi2' AND ctb.id != '$id_reg_ant'");
    while ($row = mysqli_fetch_array($datos)) {
        $id = $row["id"];
    }
    return $id;
}

//obtener el id de ahotipdoc en base a codtip
function get_id_tipdoc($TipoDoc, $tabla, $conexion)
{
    $id = 'X';
    $datos = mysqli_query($conexion, "SELECT ctb.id FROM `$tabla` ctb WHERE ctb.codtip = '$TipoDoc'");
    while ($row = mysqli_fetch_array($datos)) {
        $id = $row["id"];
    }
    return $id;
}

//FUNCION PARA GENERACION DE GLOSAS PARA APORTACIONES Y AHORROS
//FUNCIONES PARA RECUPERAR EL TIPO DE TRASACCION 
function glosa_obtenerTipoTransaccion($id, $tabla, $campo_tabla, $conexion)
{
    $transaccion = "#TIPO DE DOCUMENTO NO ENCONTRADO#";
    $consulta = mysqli_query($conexion, "SELECT `$campo_tabla` FROM `$tabla` WHERE `id_tipo`=$id");
    while ($registro = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $transaccion = $registro[$campo_tabla];
    }
    return $transaccion;
}

//FUNCION PARA OBTENER MODULO DE APRT O AHORRO
function glosa_obtenerTipoModulo($pos)
{
    $array = array("AHORRO", "APORTACIÓN");
    $longitud = count($array);
    if ($pos < 0 || $pos >= $longitud) {
        return "#TIPO DE MODULO NO ENCONTRADO#";
    }
    return ($array[$pos]);
}

//FUNCION QUE DEVUELVE TIPO DE MOVIMIENTO
function glosa_obtenerMovimiento($pos)
{
    $array = array("DEPÓSITO", "RETIRO", "ACREDITACIÓN DE INTERESES", "RETENCIÓN DE ISR", "PROVISION DE INTERESES");
    $longitud = count($array);

    if ($pos < 0 || $pos >= $longitud) {
        return "#TIPO DE MOVIMIENTO NO ENCONTRADO#";
    }
    return $array[$pos];
}

//FUNCION QUE DUELVE EL NUMERO DE RECIBO
function glosa_obtenerRecibo($codigo)
{
    return "CON RECIBO NO. " . $codigo;
}

//FUNCION QUE DEVUELVE CONECTORES
function glosa_obtenerConector($pos)
{
    $array = array("DE", "CON", "A", "AL");
    $longitud = count($array);

    if ($pos < 0 || $pos >= $longitud) {
        return "#TIPO DE CONECTOR NO ENCONTRADO#";
    }
    return $array[$pos];
}

//FUNCION PARA DEVOLVER UN ESPACIO EN BLANCO
function glosa_obtenerEspacio()
{
    return " ";
}

//Conversion de numeros a letras en el apartado de fechas
function fechletras($date)
{
    $date = substr($date, 0, 10);
    $numeroDia = date('d', strtotime($date));
    $mes = date('F', strtotime($date));
    $anio = date('Y', strtotime($date));
    $meses_ES = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
    $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
    $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
    return $numeroDia . " de " . $nombreMes . " de " . $anio;
}

function calcular_edad($fecha)
{
    // Separar la fecha en año, mes y día
    $partes_fecha = explode("-", $fecha, 3);
    $anio = $partes_fecha[0];
    $mes = $partes_fecha[1];
    $dia = $partes_fecha[2];

    // Obtener la fecha actual
    $hoy = getdate();
    $anio_actual = $hoy["year"];
    $mes_actual = $hoy["mon"];
    $dia_actual = $hoy["mday"];

    // Calcular la edad
    $edad = $anio_actual - $anio;

    // Ajustar la edad si aún no ha pasado el cumpleaños de este año
    if ($mes_actual < $mes || ($mes_actual == $mes && $dia_actual < $dia)) {
        $edad--;
    }

    return $edad;
}

//FUNCION PARA CONSULTAR SI HAY UN CIERRE PENDIENTE
function comprobar_cierre_caja($idusuario, $conexion, $bandera = '1', $fechainicio = "0000-00-00", $fechafin = "0000-00-00", $fechavalue = "0000-00-00")
{
    try {
        $resultado = ["0", "No se encontro info de la institucion, verifique", "No se encontro el rol de usuario", "Realice una apertura de caja para iniciar sus labores", "Realice el cierre de caja pendiente para iniciar sus labores", "No se puede realizar esta acción porque ya se ha vencido el plazo para realizarlo", "1", "1", "1"];
        $stmtaux = $conexion->prepare("SELECT comprobar_cierre_caja(?,?,?,?,?,?) AS cierre");
        if (!$stmtaux) {
            throw new Exception("Error en la consulta de comprobar cierre" . $conexion->error);
        }
        $aux = date('Y-m-d');
        $aux2 = $idusuario;
        $stmtaux->bind_param("ssisss", $aux2, $aux, $bandera, $fechainicio, $fechafin, $fechavalue);
        if (!$stmtaux->execute()) {
            throw new Exception("Error al consultar comprobar cierre" . $stmtaux->error);
        }
        $result = $stmtaux->get_result();
        $rowdatos = $result->fetch_assoc();
        return [$rowdatos['cierre'], $resultado[$rowdatos['cierre']]];
    } catch (Exception $e) {
        //Captura el error
        $mensaje_error = $e->getMessage();
        return [0, $mensaje_error];
        $conexion->close();
    } finally {
        if ($stmtaux !== false) {
            $stmtaux->close();
        }
    }
}
function comprobar_cierre_cajaPDO($idusuario, $database, $bandera = 1, $fechainicio = "0000-00-00", $fechafin = "0000-00-00", $fechavalue = "0000-00-00")
{
    try {
        $aux = date('Y-m-d');
        $resultado = ["0", "No se encontro info de la institucion, verifique", "No se encontro el rol de usuario", "Realice una apertura de caja para iniciar sus labores", "Realice el cierre de caja pendiente para iniciar sus labores", "No se puede realizar esta acción porque ya se ha vencido el plazo para realizarlo", "1", "1", "1"];
        $result = $database->getAllResults("SELECT comprobar_cierre_caja(?,?,?,?,?,?) AS cierre", [$idusuario, $aux, (int)$bandera, $fechainicio, $fechafin, $fechavalue]);
        if (empty($result)) {
            throw new Exception("Error en la consulta de comprobar cierre");
        }
        return [$result[0]['cierre'], $resultado[$result[0]['cierre']]];
    } catch (Exception $e) {
        //Captura el error
        $mensaje_error = $e->getMessage();
        return [0, $mensaje_error];
    } finally {
        //$database->closeConnection();
    }
}
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function generarCuadro($ancho, $alto, $colorFondo, $colorBorde)
{
    // Creamos una imagen en blanco con el tamaño especificado
    $imagen = imagecreatetruecolor($ancho, $alto);

    // Definimos el color de fondo
    $colorFondo = imagecolorallocate($imagen, $colorFondo[0], $colorFondo[1], $colorFondo[2]);

    // Dibujamos un cuadrado con un borde
    $colorBorde = imagecolorallocate($imagen, $colorBorde[0], $colorBorde[1], $colorBorde[2]);
    imagefilledrectangle($imagen, 0, 0, $ancho - 1, $alto - 1, $colorFondo);
    imagerectangle($imagen, 0, 0, $ancho - 1, $alto - 1, $colorBorde);

    // Devolvemos la imagen resultante
    return $imagen;
}
function logerrores($mensaje, $file1, $line1, $file2 = 0, $line2 = 0)
{
    $archivoLog = __DIR__ . '/../../logs/errores.log';
    if (!file_exists($archivoLog)) {
        // Si no existe, crear el archivo con permisos de escritura
        $creado = touch($archivoLog);
        if (!$creado) {
            return 'Archivo de registro no creado, verificar';
        }
    }
    $codigoError = rand(1000, 9999);
    $mensajelog = "Error [" . date("Y-m-d H:i:s") . "] [Código: $codigoError] - Error en el archivo: " . $file1 . " Linea " . $line1 . "; file secundario:" . $file2 . " en la línea " . $line2 . ": " . $mensaje . PHP_EOL;
    error_log($mensajelog, 3, $archivoLog);
    return $codigoError;
}
/**
 * La función calcula la diferencia en días entre dos fechas convirtiéndolas en objetos DateTime
 * y considerando un año como 360 días y un mes como 30 días.
 * 
 * @param fecha1 Fecha inicial
 * @param fecha2 Fecha final
 * 
 * @return integer Valor absoluto de la diferencia en dias enteros
 */

function diferenciaEnDias($fecha1, $fecha2)
{
    // Convertir las fechas en objetos DateTime
    $fecha1 = new DateTime($fecha1);
    $fecha2 = new DateTime($fecha2);

    // Descomponer las fechas en años, meses y días
    $ano1 = (int)$fecha1->format('Y');
    $mes1 = (int)$fecha1->format('m');
    $dia1 = (int)$fecha1->format('d');

    $ano2 = (int)$fecha2->format('Y');
    $mes2 = (int)$fecha2->format('m');
    $dia2 = (int)$fecha2->format('d');

    // Calcular la diferencia en años, meses y días
    $anoDiff = $ano2 - $ano1;
    $mesDiff = $mes2 - $mes1;
    $diaDiff = $dia2 - $dia1;

    // Convertir todo a días considerando meses de 30 días y años de 360 días
    $diferenciaEnDias = $anoDiff * 360 + $mesDiff * 30 + $diaDiff;

    return abs($diferenciaEnDias);
}
function agregarMes($fecha, $meses = 1)
{
    $nuevaFecha = strtotime("+$meses month", strtotime($fecha));
    return date('Y-m-d', $nuevaFecha);
}
function diasDelMes($fecha)
{
    $anio = date('Y', strtotime($fecha));
    $mes = date('m', strtotime($fecha));

    // Crear una fecha temporal con el primer día del siguiente mes
    $primerDiaSiguienteMes = strtotime('+1 month', strtotime($anio . '-' . $mes . '-01'));

    // Restar un día para obtener el último día del mes actual
    $ultimoDiaDelMes = date('d', strtotime('-1 day', $primerDiaSiguienteMes));

    return $ultimoDiaDelMes;
}
function sumarDiasBase30($fecha, $dias)
{
    // $fechaObj = DateTime::createFromFormat('d-m-Y', $fecha);
    // $anio = (int)$fechaObj->format('Y');
    // $mes = (int)$fechaObj->format('m');
    // $dia = (int)$fechaObj->format('d');

    // // Extraer año, mes y día de la fecha de entrada
    list($anio, $mes, $dia) = explode('-', $fecha);
    $anio = (int)$anio;
    $mes = (int)$mes;
    $dia = (int)$dia;

    // Calcular los meses y días a sumar
    $mesesSumar = floor($dias / 30);
    $diasSumar = $dias % 30;

    // Sumar meses y días
    $mes += $mesesSumar;
    $dia += $diasSumar;

    // Ajustar si los días superan 30
    while ($dia > 30) {
        $mes++;
        $dia -= 30;
    }

    // Ajustar si los meses superan 12
    while ($mes > 12) {
        $anio++;
        $mes -= 12;
    }

    // Formatear la fecha de salida en formato 'yyyy-mm-dd'
    $nuevaFecha = sprintf("%04d-%02d-%02d", $anio, $mes, $dia);

    return $nuevaFecha;
}
function agregarDias($fecha, $dias = 1)
{
    $nuevaFecha = strtotime("+$dias day", strtotime($fecha));
    return date('Y-m-d', $nuevaFecha);
}
function convert_to_utf8($data)
{
    if (is_array($data)) {
        return array_map('convert_to_utf8', $data);
    } elseif (is_string($data)) {
        return encode_utf8($data);
    } else {
        return $data;
    }
}
function getpermisosuser($database, $iduser, $rama, $idmodulo, $db_name_general)
{
    $query = "SELECT tbp.id_usuario, tbs.id AS menu, tbs.descripcion, tbm.id AS opcion, tbm.condi, tbm.`file`, tbm.caption FROM tb_usuario tbu
        INNER JOIN tb_permisos2 tbp ON tbu.id_usu=tbp.id_usuario
        INNER JOIN $db_name_general.tb_submenus tbm ON tbp.id_submenu=tbm.id
        INNER JOIN $db_name_general.tb_menus tbs ON tbm.id_menu =tbs.id
        INNER JOIN $db_name_general.tb_modulos tbo ON tbs.id_modulo =tbo.id
        INNER JOIN $db_name_general.tb_permisos_modulos tbps ON tbo.id=tbps.id_modulo
        WHERE tbu.id_usu=? AND tbo.estado='1' AND tbs.estado='1' AND tbm.estado='1' AND tbps.estado='1' AND
          tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1) 
        AND tbo.rama=? AND  tbo.id=? ORDER BY tbo.orden, tbs.orden,tbs.id, tbm.orden ASC;";
    try {
        $result = $database->getAllResults($query, [$iduser, $rama, $idmodulo]);
        if (empty($result)) {
            throw new Exception("No tiene ningun permiso otorgado a éste modulo");
        }
        return [1, $result];
    } catch (Exception $e) {
        $mensaje_error = $e->getMessage();
        return [0, $mensaje_error];
    } finally {
        //$database->closeConnection();
    }
}
function getpermisosmodules($database, $idagencia, $rama, $db_name_general)
{
    $query = "SELECT tbo.id,tbo.descripcion, tbo.icon, tbo.ruta, tbo.rama FROM $db_name_general.tb_permisos_modulos tbps
                INNER JOIN $db_name_general.tb_modulos tbo ON tbps.id_modulo =tbo.id
                WHERE tbo.estado='1' AND tbps.estado='1' AND
                    tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 WHERE id_agencia=? LIMIT 1) 
                AND tbo.rama=? GROUP BY tbo.id ORDER BY tbo.orden ASC;";
    try {
        $result = $database->getAllResults($query, [$idagencia, $rama]);
        if (empty($result)) {
            throw new Exception("No tiene permiso a ningún Módulo del sistema");
        }
        return [1, $result];
    } catch (Exception $e) {
        $mensaje_error = $e->getMessage();
        return [0, $mensaje_error];
    } finally {
        //$database->closeConnection();
    }
}
function decode_utf8($string)
{
    return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
}
function setdatefrench($date_string)
{
    return date("d-m-Y", strtotime($date_string));
}
function encode_utf8($string)
{
    return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
}

/**
 * Genera un código aleatorio de dos dígitos que no esté en el array de códigos existentes.
 *
 * @param array $codigosExistentes Un array de cadenas que contiene los códigos existentes.
 * @return string Devuelve un código de dos dígitos que no está en el array.
 */
function generarCodigoUnico($codigosExistentes)
{
    do {
        $codigo = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    } while (in_array($codigo, $codigosExistentes));

    return $codigo;
}

function dpialetras2($numero)
{
    // Validar que sea un número válido
    if (!is_numeric($numero)) {
        return '';
    }

    // Convertir a string y mantener ceros significativos
    $numeroStr = (string)$numero;

    // Caso especial para cero
    if ($numeroStr === '0' || ltrim($numeroStr, '0') === '') {
        return 'cero';
    }

    // Dividir en grupos de máximo 4 dígitos, pero manteniendo la estructura natural
    $grupos = [];
    $length = strlen($numeroStr);

    // Empezar desde el final para formar los grupos naturales
    while ($length > 0) {
        $grupoLength = min(4, $length);
        $grupo = substr($numeroStr, max(0, $length - $grupoLength), $grupoLength);
        array_unshift($grupos, $grupo);
        $length -= $grupoLength;
    }

    $resultado = [];
    $escalas = ['', 'mil', 'millones', 'billones', 'trillones'];

    foreach ($grupos as $index => $grupo) {
        $numGrupo = intval($grupo);
        if ($numGrupo > 0) {
            $nombreGrupo = convertir_grupo_natural($numGrupo, $grupo);
            $escala = $escalas[count($grupos) - $index - 1] ?? '';
            $resultado[] = $nombreGrupo . ($escala ? ' ' . $escala : '');
        }
    }

    return implode(' ', $resultado) ?: 'cero';
}

function convertir_grupo_natural($numero, $grupoStr)
{
    $unidad = [
        '',
        'uno',
        'dos',
        'tres',
        'cuatro',
        'cinco',
        'seis',
        'siete',
        'ocho',
        'nueve',
        'diez',
        'once',
        'doce',
        'trece',
        'catorce',
        'quince',
        'dieciséis',
        'diecisiete',
        'dieciocho',
        'diecinueve'
    ];

    $decena = [
        '',
        '',
        'veinte',
        'treinta',
        'cuarenta',
        'cincuenta',
        'sesenta',
        'setenta',
        'ochenta',
        'noventa'
    ];

    $centena = [
        '',
        'ciento',
        'doscientos',
        'trescientos',
        'cuatrocientos',
        'quinientos',
        'seiscientos',
        'setecientos',
        'ochocientos',
        'novecientos'
    ];

    // Casos especiales para grupos de 4 dígitos
    if (strlen($grupoStr) == 4) {
        $millar = floor($numero / 1000);
        $resto = $numero % 1000;

        $resultado = '';

        // Manejar el millar
        if ($millar > 0) {
            if ($millar == 1) {
                $resultado .= 'mil';
            } else {
                $resultado .= $unidad[$millar] . ' mil';
            }
        }

        // Manejar el resto (centenas, decenas, unidades)
        if ($resto > 0) {
            if (!empty($resultado)) {
                $resultado .= ' ';
            }

            $centenas = floor($resto / 100);
            $restoDec = $resto % 100;

            if ($centenas > 0) {
                $resultado .= $centena[$centenas];
            }

            if ($restoDec > 0) {
                if ($centenas > 0) {
                    $resultado .= ' ';
                }

                if ($restoDec < 20) {
                    $resultado .= $unidad[$restoDec];
                } else {
                    $decenas = floor($restoDec / 10);
                    $unidades = $restoDec % 10;

                    if ($decenas == 2 && $unidades != 0) {
                        $resultado .= 'veinti' . $unidad[$unidades];
                    } elseif ($unidades == 0) {
                        $resultado .= $decena[$decenas];
                    } else {
                        $resultado .= $decena[$decenas] . ' y ' . $unidad[$unidades];
                    }
                }
            }
        }

        return $resultado;
    }

    // Para grupos de 1-3 dígitos (igual que antes)
    if ($numero == 0) {
        return '';
    }

    if ($numero == 100) {
        return 'cien';
    }

    $resultado = '';
    $centenas = floor($numero / 100);
    $resto = $numero % 100;

    if ($centenas > 0) {
        $resultado .= $centena[$centenas];
    }

    if ($resto > 0) {
        if ($centenas > 0) {
            $resultado .= ' ';
        }

        if ($resto < 20) {
            $resultado .= $unidad[$resto];
        } else {
            $decenas = floor($resto / 10);
            $unidades = $resto % 10;

            if ($decenas == 2 && $unidades != 0) {
                $resultado .= 'veinti' . $unidad[$unidades];
            } elseif ($unidades == 0) {
                $resultado .= $decena[$decenas];
            } else {
                $resultado .= $decena[$decenas] . ' y ' . $unidad[$unidades];
            }
        }
    }

    return $resultado;
}


function convertir_a_letras2($numero)
{
    $unidad = [
        '',
        'uno',
        'dos',
        'tres',
        'cuatro',
        'cinco',
        'seis',
        'siete',
        'ocho',
        'nueve',
        'diez',
        'once',
        'doce',
        'trece',
        'catorce',
        'quince',
        'dieciséis',
        'diecisiete',
        'dieciocho',
        'diecinueve'
    ];

    $decena = [
        '',
        '',
        'veinte',
        'treinta',
        'cuarenta',
        'cincuenta',
        'sesenta',
        'setenta',
        'ochenta',
        'noventa'
    ];

    $centena = [
        '',
        'ciento',
        'doscientos',
        'trescientos',
        'cuatrocientos',
        'quinientos',
        'seiscientos',
        'setecientos',
        'ochocientos',
        'novecientos'
    ];

    if ($numero == 0) {
        return 'cero';
    }

    if ($numero == 100) {
        return 'cien';
    }

    if ($numero > 0 && $numero < 20) {
        return $unidad[$numero];
    }

    if ($numero >= 20 && $numero < 100) {
        $d = floor($numero / 10);
        $u = $numero % 10;
        if ($d == 2 && $u != 0) {
            return 'veinti' . $unidad[$u];
        } elseif ($u == 0) {
            return $decena[$d];
        } else {
            return $decena[$d] . ' y ' . $unidad[$u];
        }
    }

    if ($numero >= 100 && $numero < 1000) {
        $c = floor($numero / 100);
        $resto = $numero % 100;
        return $centena[$c] . ($resto > 0 ? ' ' . convertir_a_letras2($resto) : '');
    }

    if ($numero >= 1000 && $numero < 10000) {
        $m = floor($numero / 1000);
        $resto = $numero % 1000;
        $mil = ($m == 1) ? 'mil' : $unidad[$m] . ' mil';
        return $mil . ($resto > 0 ? ' ' . convertir_a_letras2($resto) : '');
    }

    return $numero; // Si el número está fuera del rango soportado
}

/**
 * Valida un array de condiciones de comparación y retorna el resultado de la primera que sea verdadera.
 *
 * @param array $validaciones Un array bidimensional que contiene las validaciones.
 * Cada subarray debe contener:
 *  - [0] mixed: Primer valor a comparar.
 *  - [1] mixed: Segundo valor a comparar o expresión regular según el tipo de validación.
 *  - [2] mixed: Identificador del campo o mensaje relacionado con la validación.
 *  - [3] int: Tipo de validación, los valores posibles son:
 *    - 1: Igualdad (`==`)
 *    - 2: Menor que (`<`)
 *    - 3: Mayor que (`>`)
 *    - 4: Validar expresiones regulares (usa la función `validar_expresion_regular`)
 *    - 5: Escapar de la validación (no hace nada, continúa con la siguiente)
 *    - 6: Menor o igual que (`<=`)
 *    - 7: Mayor o igual que (`>=`)
 *    - 8: Diferente de (`!=`)
 *
 * @return array Devuelve un array con tres elementos:
 *  - [0] mixed: Identificador del campo o mensaje asociado con la validación exitosa.
 *  - [1] string: '0', valor fijo en el resultado.
 *  - [2] bool: true si la validación fue exitosa, false si no lo fue.
 */

function validacionescampos($validaciones)
{
    for ($i = 0; $i < count($validaciones); $i++) {
        if ($validaciones[$i][3] == 1) { //igual
            if ($validaciones[$i][0] == $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 2) { //menor que
            if ($validaciones[$i][0] < $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 3) { //mayor que
            if ($validaciones[$i][0] > $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 4) { //Validarexpresionesregulares
            if (validar_expresion_regular($validaciones[$i][0], $validaciones[$i][1])) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 5) { //Escapar de la validacion
        } elseif ($validaciones[$i][3] == 6) { //menor o igual
            if ($validaciones[$i][0] <= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 7) { //menor o igual
            if ($validaciones[$i][0] >= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 8) { //diferente de
            if ($validaciones[$i][0] != $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        }
    }
    return ["", '0', false];
}
function validacionesER($cadena, $expresion_regular)
{
    if (preg_match($expresion_regular, $cadena)) {
        return false;
    } else {
        return true;
    }
}
/**
 * Genera un código aleatorio de una longitud especificada.
 *
 * @param int $longitud La longitud del código aleatorio a generar. Por defecto es 10.
 * @return string El código aleatorio generado.
 */
function generarCodigoAleatorio($longitud = 10)
{
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $codigo = '';
    $max = strlen($caracteres) - 1;

    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $caracteres[random_int(0, $max)];
    }

    return $codigo;
}

//number_format(numero,2);
function moneda($numero, $moneda = 'Q')
{
    return "{$moneda} " . number_format($numero, 2);
}

/**
 * Incrementa o reinicia un contador estático.
 *
 * Esta función mantiene un contador estático que se incrementa en 1 cada vez que se llama a la función.
 * Si el parámetro opcional `$restart` se establece en `true`, el contador se reiniciará.
 *
 * @param bool $restart Opcional. Si se establece en `true`, el contador se reiniciará. El valor predeterminado es `false`.
 * @return int El valor actual del contador.
 */
function getContador($restart = false)
{
    static $contador = 0;
    $contador = ($restart == false) ? $contador + 1 : $restart;
    return $contador;
}
function calcularPlazoEnMeses($fechaInicio, $cantidadDias)
{
    // Convertir la fecha de inicio a objeto DateTime
    $fecha1 = new DateTime($fechaInicio);

    // Crear fecha final sumando los días
    $fecha2 = new DateTime($fechaInicio);
    $fecha2->add(new DateInterval("P{$cantidadDias}D"));

    // Calcular diferencia
    $interval = $fecha1->diff($fecha2);

    // Calcular meses totales
    $meses = ($interval->y * 12) + $interval->m;

    // Si hay más de 15 días adicionales, redondear al mes siguiente
    if ($interval->d > 15) {
        $meses++;
    }

    return $meses;
}

function fecha_letras($fecha)
{
    $dias = [
        1 => 'uno',
        'dos',
        'tres',
        'cuatro',
        'cinco',
        'seis',
        'siete',
        'ocho',
        'nueve',
        'diez',
        'once',
        'doce',
        'trece',
        'catorce',
        'quince',
        'dieciséis',
        'diecisiete',
        'dieciocho',
        'diecinueve',
        'veinte',
        'veintiuno',
        'veintidós',
        'veintitrés',
        'veinticuatro',
        'veinticinco',
        'veintiséis',
        'veintisiete',
        'veintiocho',
        'veintinueve',
        'treinta',
        'treinta y uno'
    ];

    $meses = [
        1 => 'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre'
    ];

    $anio_numeros = [
        '0' => 'cero',
        '1' => 'uno',
        '2' => 'dos',
        '3' => 'tres',
        '4' => 'cuatro',
        '5' => 'cinco',
        '6' => 'seis',
        '7' => 'siete',
        '8' => 'ocho',
        '9' => 'nueve'
    ];

    $anio_especiales = [
        '10' => 'diez',
        '11' => 'once',
        '12' => 'doce',
        '13' => 'trece',
        '14' => 'catorce',
        '15' => 'quince',
        '16' => 'dieciséis',
        '17' => 'diecisiete',
        '18' => 'dieciocho',
        '19' => 'diecinueve',
        '20' => 'veinte',
        '30' => 'treinta',
        '40' => 'cuarenta',
        '50' => 'cincuenta',
        '60' => 'sesenta',
        '70' => 'setenta',
        '80' => 'ochenta',
        '90' => 'noventa',
        '100' => 'cien'
    ];

    $timestamp = strtotime($fecha);
    $dia = date('j', $timestamp);
    $mes = date('n', $timestamp);
    $anio = date('Y', $timestamp);

    // Convertir año a letras
    $anio_letras = '';
    if ($anio == 2000) {
        $anio_letras = 'dos mil';
    } else if ($anio > 2000 && $anio < 2010) {
        $anio_letras = 'dos mil ' . $anio_numeros[substr($anio, 3, 1)];
    } else if ($anio >= 2010 && $anio < 2020) {
        $anio_letras = 'dos mil ' . $anio_especiales[substr($anio, 2, 2)];
    } else if ($anio >= 2020) {
        $anio_letras = 'dos mil ' . (substr($anio, 2, 1) == '2' ? 'veinti' : '') .
            $anio_numeros[substr($anio, 3, 1)];
    }

    return $dias[$dia] . ' de ' . $meses[$mes] . ' del año ' . $anio_letras;
}

function dpi_letra($numdpi, $letra)
{

    $texto = preg_replace('/\s+/', '', $numdpi);
    $parte1 = substr($texto, 0, 4);
    $parte2 = substr($texto, 4, 5);
    $parte3 = substr($texto, 9, 4);
    $letra_dpi1 = numToLetras($parte1, $letra);
    $letra_dpi2 = numToLetras($parte2, $letra);
    $letra_dpi3 = numToLetras($parte3, $letra);
    $resultado = ("{$letra_dpi1}, {$letra_dpi2}, {$letra_dpi3}");


    return $resultado;
}
function dpi_format($numdpi)
{
    $texto = preg_replace('/\s+/', '', $numdpi);
    $parte1 = substr($texto, 0, 4);
    $parte2 = substr($texto, 4, 5);
    $parte3 = substr($texto, 9, 4);
    $resultado = ("{$parte1} {$parte2} {$parte3}");
    return $resultado;
}

function numToLetras($numero, $letra)
{
    $letra_d = mb_strtolower($letra->toWords(intval(trim($numero))));
    return $letra_d;
}

function karely($text, $default = ' ')
{
    return (!isset($text) || trim($text) === '') ? $default : htmlspecialchars($text);
}