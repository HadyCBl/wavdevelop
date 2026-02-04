<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
include __DIR__ . '/../../includes/Config/database.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';

//verificar si existe la sesion
session_start();
$condisList = ["create_analisis", "update_analisis"];
if (in_array($_POST["condi"], $condisList) && !isset($_SESSION['id'])) {
    $encrypt = $secureID->encrypt('reloginKeyUniqueXD');
    echo json_encode(['Su sesión ha expirado, por favor inicie sesión nuevamente', '0', 'relogin' => 1, 'key' => $encrypt]);
    return;
}
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

use App\Generic\Agencia;
use App\Generic\FileProcessor;
use Micro\Helpers\Log;
use Micro\Helpers\SecureID;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use Micro\Helpers\Beneq;
$secureID = new SecureID($_ENV['MYKEYPASS']);
include '../../includes/BD_con/db_con.php';

mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
// include '../../src/funcphp/func_gen.php';
include '../../src/funcphp/fun_ppg.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$usu = $_SESSION['usu'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];
$flaggrup = 1;
$condi = $_POST["condi"];
switch ($condi) {
    case 'list_creditos_a_desembolsar': {
            $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name, cm.CodCli AS codcli, cm.CODAgencia AS codagencia, pd.cod_producto AS codproducto, cm.MonSug AS monto, cm.Cestado AS estado   FROM cremcre_meta cm
            INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
            INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.Cestado='E' AND cm.TipoEnti='INDI'");
            //se cargan los datos de las beneficiarios a un array
            $array_datos = array();
            $array_parenteco[] = [];
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $array_datos[] = array(
                    "0" => $i + 1,
                    "1" => $fila["short_name"],
                    "2" => $fila["codproducto"],
                    "3" => $fila["ccodcta"],
                    "4" => $fila["monto"],
                    // "5" => '<button type="button" class="btn btn-success" onclick="seleccionar_credito_a_desembolsar(`#id_modal_hidden`,[`' . $fila["codcli"] . '`,`' . $fila["short_name"] . '`,`' . $fila["codagencia"] . '`,`' . $fila["codproducto"] . '`,`' . $fila["ccodcta"] . '`,`' . $fila["monto"] . '`]); consultar_gastos_monto(`' . $fila["ccodcta"] . '`); mostrar_tabla_gastos(`' . $fila["ccodcta"] . '`); cerrar_modal(`#modal_creditos_a_desembolsar`, `hide`, `#id_modal_hidden`); $(`#bt_desembolsar`).show(); concepto_default(`' . $fila["short_name"] . '`, `0`);">Aceptar</button>'
                    // "5" => '<button type="button" class="btn btn-success" onclick="seleccionar_credito_a_desembolsar(`#id_modal_hidden`,[`' . $fila["codcli"] . '`,`' . $fila["short_name"] . '`,`' . $fila["codagencia"] . '`,`' . $fila["codproducto"] . '`,`' . $fila["ccodcta"] . '`,`' . $fila["monto"] . '`]); consultar_gastos_monto(`' . $fila["ccodcta"] . '`); mostrar_tabla_gastos(`' . $fila["ccodcta"] . '`); cerrar_modal(`#modal_creditos_a_desembolsar`, `hide`, `#id_modal_hidden`); $(`#bt_desembolsar`).show(); concepto_default(`' . $fila["short_name"] . '`, `0`);">Aceptar</button>'
                    "5" => '<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2(`#cuadro`,`' . $fila["ccodcta"] . '`)" >Aceptar</button>'
                );
                $i++;

                // eliminar(ideliminar, dir, xtra, condi)
            }
            $results = array(
                "sEcho" => 1, //info para datatables
                "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
                "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
                "aaData" => $array_datos
            );
            mysqli_close($conexion);
            echo json_encode($results);
        }
        break;

    case 'listado_consultar_estado_cuenta_for_delete': {
            $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cm.CodCli AS codcli, cl.short_name AS nombre, cm.NCiclo AS ciclo, cm.MonSug AS monsug, cm.TipoEnti AS tipocred, cm.Cestado
            FROM cremcre_meta cm
            INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
            WHERE (cm.Cestado='F') AND cm.TipoEnti = 'INDI'
            ORDER BY cm.CCODCTA ASC;");
            //se cargan los datos de las beneficiarios a un array
            $array_datos = array();
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $array_datos[] = array(
                    "0" => $i + 1,
                    "1" => $fila["ccodcta"],
                    "2" => $fila["nombre"],
                    "3" => $fila["ciclo"],
                    "4" => $fila["monsug"],
                    "5" => ($fila["tipocred"] == 'INDI') ? ('Individual') : ('Grupal'),
                    "6" => '<button type="button" class="btn btn-danger btn-sm mr-2" data-bs-dismiss="modal" data-statuss="X" data-ccodcta="' . $fila["ccodcta"] . '" onclick="confirmchangestatus(this)"><i class="fas fa-trash-alt"></i> Eliminar</button>',
                    "7" => '<button type="button" class="btn btn-warning btn-sm" data-bs-dismiss="modal" data-statuss="E" data-ccodcta="' . $fila["ccodcta"] . '" onclick="confirmchangestatus(this)"><i class="fas fa-arrow-left"></i> Estado a Aprobación</button>'
                );
                $i++;
            }
            $results = array(
                "sEcho" => 1,
                "iTotalRecords" => count($array_datos),
                "iTotalDisplayRecords" => count($array_datos),
                "aaData" => $array_datos
            );
            echo json_encode($results);
            mysqli_close($conexion);
        }
        break;
    case 'changestatus':
        if (!isset($_SESSION['id'])) {
            echo json_encode(["Sesión expirada, Inicie sesion nuevamente", 0]);
            return;
        }
        $archivo = $_POST["archivo"];
        $ccodcta = $archivo[0];
        $status = $archivo[1];
        // $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns('cremcre_meta', ['Cestado', 'DFecDsbls', 'fecha_operacion'], 'CCODCTA=?', [$ccodcta]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró el código de cuenta proporcionado");
            }
            $estadoactual = $result[0]['Cestado'];
            $fechadesembolso = $result[0]['DFecDsbls'];
            $fecha_op = $result[0]['fecha_operacion'];
            if ($estadoactual == "F") {
                //COMPROBAR CIERRE DE MES CONTABLE
                $cierre_mes = comprobar_cierrePDO($_SESSION['id'], $fechadesembolso, $database);
                if ($cierre_mes[0] == 0) {
                    $showmensaje = true;
                    throw new Exception($cierre_mes[1]);
                }
            }

            $database->beginTransaction();

            //ELIMINACION DE REGISTROS EN KARDEX Y PLANES DE PAGO
            // $database->delete("CREDKAR", "CCODCTA=?", [$ccodcta]);
            $database->delete("Cre_ppg", "ccodcta=?", [$ccodcta]);
            $database->delete("creppg_detalle", "ccodcta=?", [$ccodcta]);

            $credkar = array(
                'CESTADO' => 'X',
                'deleted_at' => $hoy2,
                'deleted_by' => $idusuario
            );
            $database->update('CREDKAR', $credkar, 'CCODCTA=?', [$ccodcta]);

            //ELIMINACION DE PARTIDAS CONTABLES
            $data = array(
                'estado' => '0',
                'deleted_at' => date("Y-m-d H:i:s"),
                'deleted_by' => $idusuario,
            );
            $database->update("ctb_diario", $data, "cod_aux=?", [$ccodcta]);

            //ACTUALIZACION DE DATOS EN LA CREMCRE_META
            $data = array(
                'Cestado' => $status
            );
            $database->update("cremcre_meta", $data, "CCODCTA=?", [$ccodcta]);

            //REGISTRO DE CAMBIOS EN LA TABLA LOG DE LA CREMCRE
            $datoaux = ($estadoactual == "F") ? 'Reversion de desembolso' : "Cambio de estado";
            $fechafija = ($estadoactual == "F") ? $fechadesembolso : $fecha_op;
            $datos = array(
                'ccodcta' => $ccodcta,
                'status_ant' => $estadoactual,
                'status_post' => $status,
                'fecha_fija' => $fechafija,
                'aux' => $datoaux,
                'updated_at' => $hoy2,
                'updated_by' => $idusuario
            );
            $database->insert('cre_logcremcre', $datos);
            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    case 'gastos_desembolsos':
        $idc = $_POST['id'];
        $datas = gastoscredito($idc, $conexion);
        if ($datas != null) {
            $datas = array_filter($datas, function ($item) {
                return $item['afecta_modulo'] != 3;
            });
            // Reindexar el array si es necesario (opcional)
            $datas = array_values($datas);
        }
        $capital = 0;
        $suma_gasto = 0;
        if ($datas != null) {
            $suma_gasto = array_sum(array_column($datas, 'mongas'));
        }
        $nombrecliente = "";
        $consulta = mysqli_query($conexion, "SELECT cl.short_name, MonSug FROM cremcre_meta cm INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.CCODCTA='" . $idc . "'");
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode(['Error en la recuperacion de los gastos, intente nuevamente', '0']);
            return;
        }
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $nombrecliente = $fila['short_name'];
            $capital = $fila['MonSug'];
        }
        $suma_a_desembolsar = $capital - $suma_gasto;
        echo json_encode(['Satisfactorio', '1', $capital, $suma_gasto, $suma_a_desembolsar, $nombrecliente]);
        break;
    case 'lista_gastos':
        $idc = $_POST['id'];
        $filcuenta = $_POST['filcuenta'];
        $datas = gastoscredito($idc, $conexion);
        if ($datas != null) {
            $datas = array_filter($datas, function ($item) {
                return $item['afecta_modulo'] != 3;
            });
            // Reindexar el array si es necesario (opcional)
            $datas = array_values($datas);
        }
        $array_datos = array();
        $total = 0;
        $i = 0;
        while ($datas != null && $i <  count($datas)) {
            $id = $datas[$i]['id'];
            $contable = $datas[$i]['id_nomenclatura'];
            $tipo = $datas[$i]['tipo_deMonto'];
            $nombregasto = $datas[$i]['nombre_gasto'];
            $mongas = $datas[$i]['mongas'];
            $afectaotros = $datas[$i]['afecta_modulo'];
            $fecdes = $datas[$i]['fecdes'];
            $disabled = ($tipo < 3) ? ' ' : ' ';
            $array_datos[] = array(
                "0" => $i + 1,
                "1" => '<input type="number" id="idg_' . $i . '_' . substr($idc, 8) . '" min="0" value="' . $id . '" hidden>',
                "2" => $nombregasto,
                "3" => '<input type="number" class="form-control" onblur="calculateExpense()"
                id="mon_' . $i . '_' . substr($idc, 8) . '" min="0" step="0.01" value="' . $mongas . '" ' . $disabled . '>',
                "4" => '<input type="number" id="con_' . $i . '_' . substr($idc, 8) . '" min="0" value="' . $contable . '" hidden>',
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;
    case 'gastos_desembolsos_grupo':
        $idc = $_POST['id'];
        $datas = gastoscredito($idc, $conexion);
        $capital = 0;
        $suma_gasto = 0;
        if ($datas != null) {
            $suma_gasto = array_sum(array_column($datas, 'mongas'));
        }
        $nombrecliente = "";
        $consulta = mysqli_query($conexion, "SELECT cl.short_name, MonSug FROM cremcre_meta cm INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.CCODCTA='" . $idc . "'");
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode(['Error en la recuperacion de los gastos, intente nuevamente', '0']);
            return;
        }
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $nombrecliente = $fila['short_name'];
            $capital = $fila['MonSug'];
        }
        $suma_a_desembolsar = $capital - $suma_gasto;
        echo json_encode(['Satisfactorio', '1', $capital, $suma_gasto, $suma_a_desembolsar, $nombrecliente]);
        break;
    case 'lista_gastos_grupo':
        $idc = $_POST['id'];
        $filcuenta = $_POST['filcuenta'];
        $datas = gastoscredito($idc, $conexion);
        $array_datos = array();
        $total = 0;
        $i = 0;
        while ($datas != null && $i <  count($datas)) {
            $id = $datas[$i]['id'];
            $contable = $datas[$i]['id_nomenclatura'];
            $tipo = $datas[$i]['tipo_deMonto'];
            $nombregasto = $datas[$i]['nombre_gasto'];
            $mongas = $datas[$i]['mongas'];
            $afectaotros = $datas[$i]['afecta_modulo'];
            $fecdes = $datas[$i]['fecdes'];

            $dataselect = "";
            $visible = "none";
            if ($afectaotros == 3) {
                //BANDERA PARA ACTIVAR O DESACTIVAR EL CALCULO AUTOMATICO DEL INTERES HASTA LA FECHA DE HOY Y/O FECHA DE DESEMBOLSO
                $calculointeres = true;
                $cuentas = getcuentas($idc, $conexion);
                $j = 0;
                while ($cuentas != null && $j <  count($cuentas)) {
                    $account = $cuentas[$j]['CCODCTA'];
                    $pagadokp = $cuentas[$j]['pagadokp'];
                    $capdes = $cuentas[$j]['NCapDes'];
                    $intpen = $cuentas[$j]['intpen'];
                    $fecpago = $cuentas[$j]['fecpago'];
                    $fecult = ($cuentas[$j]['fecult'] == "-") ? $fecdes : $cuentas[$j]['fecult'];
                    $fecult = (($fecult) > ($hoy)) ? $hoy : $fecult;
                    $intapro = $cuentas[$j]['intapro'];
                    $saldo = round($capdes - $pagadokp, 2);
                    $diasdif = dias_dif($fecult, $hoy);
                    if ($calculointeres) {
                        $intpen = $saldo * $intapro / 100 / 360 * $diasdif;
                    }
                    $intpen = round($intpen, 2);
                    $intpen = ($intpen < 0) ? 0 : $intpen;
                    $visible = "block";

                    $dataselect .= '<option data-saldo="' . $saldo . '" data-intpen="' . $intpen . '" value="' . $account . '">' . $account . ' | ' . $saldo . ' | ' . $intpen . '</option>';
                    $j++;
                }
            }
            $cuentasshow = '<select style="display:' . $visible . ';" id="ant_' . $i . '_' . substr($idc, 8) . '" class="form-select form-select-sm" aria-label=Cuentas anteriores" onchange="handleSelectChange(`mon_' . $i . '_' . substr($idc, 8) . '`,this);summongas(`' . substr($idc, 8) . '`,0)">';
            $cuentasshow .= '<option selected disabled value="0">Seleccione Cuenta | Saldokp | Int Calculado</option>';
            $cuentasshow .= $dataselect;
            $cuentasshow .= '</select>';

            $disabled = ($tipo < 3) ? ' ' : ' ';
            $array_datos[] = array(
                "0" => $i + 1,
                "1" => '<input type="number" id="idg_' . $i . '_' . substr($idc, 8) . '" min="0" value="' . $id . '" hidden>',
                "2" => $nombregasto,
                "3" => $cuentasshow,
                "4" => '<input type="number" class="form-control" onblur="summongas(`' . substr($idc, 8) . '`, ' . $filcuenta . ')"
                id="mon_' . $i . '_' . substr($idc, 8) . '" min="0" step="0.01" value="' . $mongas . '" ' . $disabled . '>',
                "5" => '<input type="number" id="con_' . $i . '_' . substr($idc, 8) . '" min="0" value="' . $contable . '" hidden>',
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;

    case 'buscar_cuentas':
        $id = $_POST['id'];
        $data[] = [];
        $bandera = true;
        $consulta = mysqli_query($conexion, "SELECT cbn.id, cbn.numcuenta FROM tb_bancos bn INNER JOIN ctb_bancos cbn ON bn.id=cbn.id_banco WHERE bn.estado='1' AND cbn.estado='1' AND bn.id='$id'");
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode(['Error en la recuperacion de cuentas de bancos, intente nuevamente', '0']);
            return;
        }
        if ($consulta) {
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = false;
                $data[$i] = $fila;
                $i++;
            }
            if ($bandera) {
                echo json_encode(['El banco seleccionado no tiene cuentas creadas, por lo que no se puede realizar un desembolso con cheque', '0']);
                return;
            }
            echo json_encode(['Satisfactorio', '1', $data]);
        } else {
            echo json_encode(['Error en la recuperacion de cuentas de bancos, intente nuevamente', '0']);
        }
        mysqli_close($conexion);
        break;
    case 'buscar_cargos':
        $id = $_POST['id'];
        $data[] = [];
        $bandera = true;
        $consulta = mysqli_query($conexion, "SELECT tb_cliente_tb_grupo.cod_cargo, $db_name_general.tb_cargo_grupo.nombre, tb_cliente.short_name
                                            FROM tb_cliente_tb_grupo
                                            INNER JOIN $db_name_general.tb_cargo_grupo ON
                                            $db_name_general.tb_cargo_grupo.id = tb_cliente_tb_grupo.cod_cargo
                                            INNER JOIN tb_cliente ON
                                            tb_cliente.idcod_cliente = tb_cliente_tb_grupo.cliente_id
                                            WHERE Codigo_grupo = " . $id . " AND cod_cargo > 1 GROUP BY cod_cargo");
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode(['Error en la recuperacion de cargos, intente nuevamente', '0']);
            return;
        }
        if ($consulta) {
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = false;
                $data[$i] = $fila;
                $i++;
            }
            if ($bandera) {
                echo json_encode(['No se puede desembolsar grupalmente, ya que no se tienen cargos asignados', '0']);
                return;
            }
            echo json_encode(['Satisfactorio', '1', $data]);
        } else {
            echo json_encode(['Error en la recuperacion de cargos, intente nuevamente', '0']);
        }
        mysqli_close($conexion);
        break;

    case 'buscar_cuentas_ahorro_cli':
        $id = $_POST['id'];
        $data[] = [];
        $bandera = true;
        $consulta = mysqli_query($conexion, "SELECT cta.ccodaho, tp.nombre FROM ahomcta cta INNER JOIN ahomtip tp ON SUBSTR(cta.ccodaho, 7, 2)=tp.ccodtip WHERE cta.estado='A' AND cta.ccodcli='$id'");
        $aux = mysqli_error($conexion);
        if ($aux) {
            echo json_encode(['Error en la recuperacion de cuentas de ahorro del cliente, intente nuevamente', '0']);
            return;
        }
        if ($consulta) {
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = false;
                $data[$i] = $fila;
                $i++;
            }

            if ($bandera) {
                echo json_encode(['El cliente no tiene ninguna cuenta de ahorro, debe crear al menos una cuenta para efectuar esta operación', '0']);
                return;
            }
            echo json_encode(['Satisfactorio', '1', $data]);
        } else {
            echo json_encode(['Error en la recuperacion de cuentas de ahorro del cliente, intente nuevamente', '0']);
        }
        mysqli_close($conexion);
        break;
    case 'create_desembolso':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $idagencia = $_SESSION['id_agencia'];
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        // Desestructuración usando list
        list($codcliente, $nomcli, $codagencia, $codproducto, $codcredito, $ccapital, $gastosInput, $desembolsar, $cantidad, $numcheque, $paguese, $numletras, $glosa) = $inputs;
        list($tipo_desembolso, $negociable, $bancoid, $cuentaid, $cuentaaho) = $selects;
        list($idusu, $idagencia2, $filgas, $idPro_gas, $afec, $ahorro) = $archivo;

        $numdoc = $inputs[13] ?? '';

        $refinance = (array_key_exists(6, $archivo)) ? $archivo[6] : NULL;

        $validar = validar_campos([
            [$nomcli, "", 'Debe seleccionar un cliente'],
            [$codagencia, "", 'Debe tener un código de agencia, seleccione un cliente'],
            [$codproducto, "", 'Debe tener un código de producto, seleccione un cliente'],
            [$codcredito, "", 'Debe tener un código de crédito, seleccione un cliente'],
            [$ccapital, "", 'Debe tener un capital, seleccione un cliente'],
            [$gastosInput, "", 'Debe tener un gasto, seleccione un cliente'],
            [$desembolsar, "", 'Debe tener un total a desembolsar, seleccione un cliente'],
            [$tipo_desembolso, "", 'Debe seleccionar un tipo de desembolso']
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        // Log::info("archivo", $archivo);

        // echo json_encode(["sssss", '0']);
        // return;

        $gastos = (array_key_exists(2, $archivo)) ? $archivo[2] : NULL;
        $gastos = (is_array($gastos)) ? $gastos : NULL;

        //COMPROBACION DE REFINANCIAMIENTO
        if ($refinance !== NULL) {
            if (count(array_filter(array_column($refinance, 'interes'), function ($var) {
                return ($var < 0);
            })) > 0) {
                echo json_encode(["Monto negativo en un interés detectado, favor verificar", '0']);
                return;
            }
        }
        //FIN COMPROBACION DE REFINANCIAMIENTO

        //COMPROBACION DE GASTOS
        if ($gastos !== NULL) {
            if (count(array_filter(array_column($gastos, 1), function ($var) {
                return ($var < 0);
            })) > 0) {
                echo json_encode(["Monto negativo en el gasto detectado, favor verificar", '0']);
                return;
            }
        }
        //FIN COMPROBACION DE GASTOS

        $validar = validar_campos([[$glosa, "", 'Debe digitar un concepto']]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        if ($tipo_desembolso == '2' || $tipo_desembolso == '4') {
            //validaciones del cheque
            $validar = validar_campos([
                [$cantidad, "", 'Debe digitar una cantidad del cheque'],
                // [$numcheque, "", 'Debe digitar un número de cheque'],
                [$paguese, "", 'Debe digitar el campo paguese a la orden de'],
                [$numletras, "", 'Debe digitar el campo la suma de'],
                [$negociable, "", 'Debe seleccionar un tipo de cheque'],
                [$bancoid, "", 'Debe seleccionar un banco'],
                [$cuentaid, "", 'Debe seleccionar una cuenta de banco']
            ]);
            if ($validar[2]) {
                echo json_encode([$validar[0], $validar[1]]);
                return;
            }
        }
        if ($selects[0] == '3') {
            //validaciones de la transferencia
            $validar = validar_campos([
                [$cuentaaho, "", 'Seleccione una cuenta de ahorro, sino aparece ninguno, es posible que deba crear una cuenta de ahorro']
            ]);
            if ($validar[2]) {
                echo json_encode([$validar[0], $validar[1]]);
                return;
            }
        }

        if ($desembolsar < 0) {
            echo json_encode(["El monto a desembolsar es menor a 0, verifique los descuentos aplicados", '0']);
            return;
        }

        //++++++++++++++++++++++++++++++++++++++++++++++++
        $showmensaje = false;
        try {
            $database->openConnection();
            //COMPROBAR CIERRE DE CAJA
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                $showmensaje = true;
                throw new Exception($cierre_caja[1]);
            }

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++ CONSULTAR INFORMACION PARA FORMAR LA GLOSA ++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $query = "SELECT cm.CCODCTA AS ccodcta, cl.short_name, cf.descripcion, cm.DFecDsbls, cm.MonSug, cf.id AS id_fuente,
                        cm.CodCli, cm.Dictamen, pr.id_cuenta_capital,pr.id_cuenta_interes,cm.Cestado
                FROM cremcre_meta cm
                INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                INNER JOIN cre_productos pr ON cm.CCODPRD=pr.id
                INNER JOIN ctb_fuente_fondos cf ON pr.id_fondo=cf.id
                WHERE cm.CCODCTA=?";
            $dataCredito = $database->getAllResults($query, [$codcredito]);
            if (empty($dataCredito)) {
                $showmensaje = true;
                throw new Exception("No se encontró el código de cuenta proporcionado");
            }

            if ($dataCredito[0]['Cestado'] != 'E') {
                $showmensaje = true;
                throw new Exception("El crédito no se encuentra en estado de aprobación. Solo los créditos aprobados pueden ser desembolsados.");
            }

            //COMPROBAR CIERRE DE MES CONTABLE
            $cierre_mes = comprobar_cierrePDO($_SESSION['id'], $dataCredito[0]['DFecDsbls'], $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }

            $cliente = strtoupper($dataCredito[0]['short_name']);
            $fuente = strtoupper($dataCredito[0]['descripcion']);
            $fechadesembolso = $dataCredito[0]['DFecDsbls'];
            $monto_desembolsar = $dataCredito[0]['MonSug'];
            $id_fuente = $dataCredito[0]['id_fuente'];
            $ccodcli = $dataCredito[0]['CodCli'];
            $dictamen = $dataCredito[0]['Dictamen'];
            $id_cuenta_capital = $dataCredito[0]['id_cuenta_capital'];
            $id_cuenta_interes = $dataCredito[0]['id_cuenta_interes'];

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++ buscar la nomenclatura de la cuenta en la tabla de agencia (DEFAULT)++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $dataAgencia = $database->selectColumns('tb_agencia', ['id_nomenclatura_caja'], 'id_agencia=?', [$idagencia]);
            if (empty($dataAgencia)) {
                $showmensaje = true;
                throw new Exception("No se encontro la cuenta contable para el desembolso real");
            }
            $id_nomenclatura_caja = $dataAgencia[0]['id_nomenclatura_caja'];
            $cuentadebita = $id_nomenclatura_caja;
            //valida negroy
            if ($tipo_desembolso == '4') {
                $caja_nomen = $id_nomenclatura_caja;
            }
            $tipodesembolso = "efectivo";

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++ SELECCION DE LA CUENTA DE BANCOS SI ES CON CHEQUES +++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($tipo_desembolso == "2" || $tipo_desembolso == '4') {
                //buscar la nomenclatura del banco
                $dataBanco = $database->selectColumns('ctb_bancos', ['id_nomenclatura'], 'id=?', [$cuentaid]);
                if (empty($dataBanco)) {
                    $showmensaje = true;
                    throw new Exception("No se encontro la cuenta contable para el banco seleccionado");
                }
                $id_nomenbanco = $dataBanco[0]['id_nomenclatura'];
                $cuentadebita = $id_nomenbanco;
                $tipodesembolso = "cheque";
            }

            $database->beginTransaction();
            // $numpartida = getnumcompdo($idusuario, $database);
            $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fechadesembolso);

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++++++++++ CONTABILIDAD: DIARIO +++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $glosa = "CRÉDITO INDIVIDUAL:" . $codcredito . " - FONDO:" . $fuente . " - BENEFICIARIO:" . $cliente;
            $ctb_diario = array(
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 1,
                'id_tb_moneda' => 1,
                'numdoc' => $numdoc,
                'glosa' => $glosa,
                'fecdoc' => $fechadesembolso,
                'feccnt' => $fechadesembolso,
                'cod_aux' => $codcredito,
                'id_tb_usu' => $idusuario,
                'id_agencia' => $idagencia,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 0
            );
            $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);
            $capital = $monto_desembolsar;

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++ CONTABILIDAD: MOVIMIENTO -> CAPITAL +++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $ctb_mov = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fuente,
                'id_ctb_nomenclatura' => $id_cuenta_capital,
                'debe' => $capital,
                'haber' => 0
            );
            $database->insert('ctb_mov', $ctb_mov);

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++ CONTABILIDAD: MOVIMIENTO - GASTOS ++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            if ($gastos != null) {
                foreach ($gastos as $key => $gastoValue) {
                    if ($gastoValue[1] > 0) {
                        $ctb_mov = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_fuente_fondo' => $id_fuente,
                            'id_ctb_nomenclatura' => $gastoValue[2],
                            'debe' => 0,
                            'haber' => $gastoValue[1]
                        );
                        $database->insert('ctb_mov', $ctb_mov);
                    }
                }
            }

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++ CONTABILIDAD: MOVIMIENTO - REFINANCIAMIENTO ++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            $sumaRefinance = 0;
            if ($refinance != null) {
                foreach ($refinance as $refinanceCurrent) {
                    //Consulta:
                    $dataCuentas = $database->getAllResults("SELECT crep.id_fondo, crep.id_cuenta_interes, crep.id_cuenta_capital
                    FROM cremcre_meta cm
                    INNER JOIN cre_productos crep ON cm.CCODPRD=crep.id
                    WHERE cm.CCODCTA=?", [$refinanceCurrent['creditoActivo']]);
                    if ($refinanceCurrent['saldoCapital'] > 0) {
                        $ctb_mov = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_fuente_fondo' => $dataCuentas[0]['id_fondo'],
                            'id_ctb_nomenclatura' => $dataCuentas[0]['id_cuenta_capital'],
                            'debe' => 0,
                            'haber' => $refinanceCurrent['saldoCapital']
                        );
                        $database->insert('ctb_mov', $ctb_mov);
                    }
                    if ($refinanceCurrent['interes'] > 0) {
                        $ctb_mov = array(
                            'id_ctb_diario' => $id_ctb_diario,
                            'id_fuente_fondo' => $dataCuentas[0]['id_fondo'],
                            'id_ctb_nomenclatura' => $dataCuentas[0]['id_cuenta_interes'],
                            'debe' => 0,
                            'haber' => $refinanceCurrent['interes']
                        );
                        $database->insert('ctb_mov', $ctb_mov);
                    }
                }
            }

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++ CONTABILIDAD: MOVIMIENTO - MONTO REAL ++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            $suma_gasto = $gastosInput;
            $suma_a_desembolsar = $capital - ($suma_gasto);
            // VALIDACION PARA LOS DESEMBOLSOS MIXTOS, si es mixto mete uno mas como efectivo
            if ($tipo_desembolso == '4') {
                $suma_a_desembolsar = $inputs[15]; // CHEQUE monto en cheque
                $EFECTIVO = $inputs[14];
                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => $id_fuente,
                    'id_ctb_nomenclatura' => $caja_nomen,
                    'debe' => 0,
                    'haber' => $EFECTIVO
                );
                $database->insert('ctb_mov', $ctb_mov);
            }
            $ctb_mov = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => $id_fuente,
                'id_ctb_nomenclatura' => $cuentadebita,
                'debe' => 0,
                'haber' => $suma_a_desembolsar
            );
            $database->insert('ctb_mov', $ctb_mov);

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ BANCOS: SI EL DESEMBOLSO ES CON CHEQUE ++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($tipo_desembolso == "2"  || $tipo_desembolso == '4') {
                //INSERCION EN CUENTAS DE CHEQUES
                $ctb_chq = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_cuenta_banco' => $cuentaid,
                    'numchq' => $numcheque,
                    'nomchq' => $paguese,
                    'monchq' => $suma_a_desembolsar,
                    'emitido' => 0,
                    'modocheque' => $negociable
                );
                $database->insert('ctb_chq', $ctb_chq);
            }

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++ CREDKAR: REGISTRO DE DESEMBOLSO +++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $cnrocuo = 1;
            $concepto = strtoupper($inputs[12]);
            // VALIDACION NUEVA SI ES UN DESEMBOLSO MIXTO
            if ($tipo_desembolso == '4') {
                $capital = $suma_a_desembolsar + $suma_gasto;
                $credkar = array(
                    'CCODCTA' => $codcredito,
                    'DFECPRO' => $fechadesembolso,
                    'DFECSIS' => $hoy2,
                    'CNROCUO' => $cnrocuo,
                    'NMONTO' => $EFECTIVO,
                    'CNUMING' => $numdoc,
                    'CCONCEP' => "EN EFECTIVO $concepto",
                    'KP' => $EFECTIVO,
                    'INTERES' => 0,
                    'MORA' => 0,
                    'OTR' => 0,
                    'CCODUSU' => $idusuario,
                    'CTIPPAG' => "D",
                    'CMONEDA' => "Q",
                    'FormPago' => "1",
                    'boletabanco' => "0",
                    'CESTADO' => "1",
                    'DFECMOD' =>  $hoy,
                );
                $database->insert('CREDKAR', $credkar);
                $cnrocuo++;
            }

            $credkar = array(
                'CCODCTA' => $codcredito,
                'DFECPRO' => $fechadesembolso,
                'DFECSIS' => $hoy2,
                'CNROCUO' => $cnrocuo,
                'NMONTO' => $capital,
                'CNUMING' => $numdoc,
                'CCONCEP' => $concepto,
                'KP' => $suma_a_desembolsar,
                'INTERES' => 0,
                'MORA' => 0,
                'OTR' => $suma_gasto,
                'CCODUSU' => $idusuario,
                'CTIPPAG' => "D",
                'CMONEDA' => "Q",
                'FormPago' => ($tipo_desembolso == '2' || $tipo_desembolso == '4') ? '2' : $tipo_desembolso,
                'boletabanco' => ($tipo_desembolso == '2' || $tipo_desembolso == '4') ? $numcheque : '0',
                'CESTADO' => "1",
                'DFECMOD' =>  $hoy,
                'CBANCO' => ($tipo_desembolso == '2' || $tipo_desembolso == '4') ? $bancoid : '0',
                'CCODBANCO' => ($tipo_desembolso == '2' || $tipo_desembolso == '4') ? $cuentaid : '0'
            );
            $id_credkar = $database->insert('CREDKAR', $credkar);

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++ CREDKAR_DETALLE: REGISTRO DE DESCUENTOS SI HUBIERAN ++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($gastos != null) {
                foreach ($gastos as $key => $gastoValue) {
                    if ($gastoValue[1] > 0) {
                        $credkar_detalle = array(
                            'id_credkar' => $id_credkar,
                            'id_concepto' => $gastoValue[0],
                            'monto' => $gastoValue[1]
                        );
                        $database->insert('credkar_detalle', $credkar_detalle);
                    }
                }
            }

            /* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++ CREDKAR_DETALLE: REGISTRO DE DESCUENTOS SI HUBIERAN ++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($refinance != null) {
                foreach ($refinance as $refinanceCurrent) {
                    $totalRefinanceCurrent = $refinanceCurrent['interes'] + $refinanceCurrent['saldoCapital'];
                    if ($totalRefinanceCurrent > 0) {
                        $credkar_detalle = array(
                            'id_credkar' => $id_credkar,
                            'id_concepto' => $refinanceCurrent['gasto'],
                            'monto' => $totalRefinanceCurrent
                        );
                        $database->insert('credkar_detalle', $credkar_detalle);
                    }
                }
            }

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++ AHORROS: SI ES POR TRANSFERENCIA SE REALIZA LA TRANSACCION +++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($tipo_desembolso == '3') {
                /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                    ++++++++++++++++++++++++++++++ DATOS DE LA CUENTA DE AHORROS +++++++++++++++++++++++++++++++++++++++++++ */
                $a_cuenta = $cuentaaho;
                $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,cli.no_identifica dpi,
                IFNULL(numfront,0) numfront, IFNULL(numdors,0) numdors,tip.id_cuenta_contable cuenta_contable,tip.nombre nombreProducto,
                IFNULL((SELECT MAX(`numlinea`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimonum,
                IFNULL((SELECT MAX(`correlativo`) FROM ahommov WHERE ccodaho=cta.ccodaho AND `nlibreta`= cta.nlibreta AND cestado!=2),0) AS ultimocorrel
                                    FROM `ahomcta` cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                                    INNER JOIN ahomtip tip ON SUBSTR(cta.ccodaho, 7, 2)=tip.ccodtip
                                    WHERE `ccodaho`=?";
                $dataAhorro = $database->getAllResults($query, [$a_cuenta]);
                if (empty($dataAhorro)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró el código de cuenta de ahorro proporcionado");
                }

                $a_idcli = $dataAhorro[0]['ccodcli'];
                $a_nit = $dataAhorro[0]['num_nit'];
                $a_dpi = $dataAhorro[0]['dpi'];
                $a_nlibreta = $dataAhorro[0]['nlibreta'];
                $a_estado = $dataAhorro[0]['estado'];
                $a_nombre = strtoupper($dataAhorro[0]['short_name']);
                $a_ultimonum = $dataAhorro[0]['ultimonum'];
                $a_ultimocorrel = $dataAhorro[0]['ultimocorrel'];
                $cuenta_tipo = $dataAhorro[0]['cuenta_contable']; //cuenta contable del tipo de ahorro
                $producto = $dataAhorro[0]['nombreProducto'];

                $a_numlib = $dataAhorro[0]['numfront'] + $dataAhorro[0]['numdors'];
                if ($a_ultimonum >= $a_numlib) {
                    $showmensaje = true;
                    throw new Exception("El número de líneas en libreta ha llegado a su límite, se recomienda abrir otra libreta");
                }
                if ($a_estado != "A") {
                    $showmensaje = true;
                    throw new Exception("Cuenta de ahorros Inactiva");
                }

                // $a_camp_numcom = getnumcompdo($idusuario, $database);
                $a_camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $fechadesembolso);

                $ahommov = array(
                    'ccodaho' => $a_cuenta,
                    'dfecope' => $fechadesembolso,
                    'ctipope' => 'D',
                    'cnumdoc' => $numdoc,
                    'ctipdoc' => 'T',
                    'crazon' => 'DEPÓSITO POR DESEMBOLSO',
                    'nlibreta' => $a_nlibreta,
                    'nrochq' => 0,
                    'tipchq' => 0,
                    'numpartida' => 0,
                    'monto' => $suma_a_desembolsar,
                    'lineaprint' => 'N',
                    'numlinea' => ($a_ultimonum + 1),
                    'correlativo' => ($a_ultimocorrel + 1),
                    'dfecmod' => $hoy2,
                    'codusu' => $idusuario,
                    'cestado' => 1,
                    'auxi' => 'DESEMBOLSO CRÉDITO INDIVIDUAL',
                    'created_at' => $hoy2,
                    'created_by' => $idusuario
                );
                $database->insert('ahommov', $ahommov);

                $ctb_diario = array(
                    'numcom' => $a_camp_numcom,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => $numdoc,
                    'glosa' => "DEPÓSITO DE AHORRO DE " . $a_nombre,
                    'fecdoc' => $fechadesembolso,
                    'feccnt' => $fechadesembolso,
                    'cod_aux' => $a_cuenta,
                    'id_tb_usu' => $idusuario,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                    'id_agencia' => $idagencia
                );
                $a_id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                // Preparar la tercera consulta para INSERT ctbmov
                //REGISTRO DE LA CUENTA DEL TIPO DE AHORRO
                $ctb_mov = array(
                    'id_ctb_diario' => $a_id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuenta_tipo,
                    'debe' => $suma_a_desembolsar,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $ctb_mov);

                //REGISTRO DE LA CUENTA DE CAJA
                $ctb_mov = array(
                    'id_ctb_diario' => $a_id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $id_nomenclatura_caja,
                    'debe' => 0,
                    'haber' => $suma_a_desembolsar
                );

                $tipodesembolso = "transferencia";
            }

            /*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
              ++++++++++++++++++++ CREMCRE: ACTUALIZACION DE ESTADO DE CREDITO Y TIPO DE DESEMBOLSO ++++++++++++++++++
              ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            //$tipodes = ($selects[0] == '1') ? "E" : (($selects[0] == '2') ? 'C' : 'T');
            $tipodes = ($tipo_desembolso == '1') ? "E" : (($tipo_desembolso  == '2') ? 'C' : (($tipo_desembolso  == '4') ? 'M' : 'T'));
            $cntaho = (isset($archivo[5])) ? $archivo[5] : 0;

            if ($tipo_desembolso  == '4') {
                $capital += $EFECTIVO;
            }
            $cremcre_meta = array(
                'Cestado' => 'F',
                'fecha_operacion' => $hoy,
                'NCapDes' => $capital,
                'TipDocDes' => $tipodes,
                'id_pro_gas' => $idPro_gas,
                'moduloafecta' => $afec,
                'cntAho' => $cntaho
            );
            $database->update('cremcre_meta', $cremcre_meta, 'CCODCTA=?', [$codcredito]);

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++ CRE_PPG: INSERCION DE CADA CUOTA A LA TABLA DE PAGOS +++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            saveCredppg($codcredito, $database, $db_name_general, $idusuario, $idagencia, idGastoModuloAdicional: $idPro_gas);

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++ Cambio del estado de la cuenta de ahorro de plazo fijo por que ingreso como garantia +++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            $queryahomctaupdate = "UPDATE ahomcta cta SET cta.dep = 1 , cta.ret = 0 WHERE cta.ccodaho IN
                        (SELECT cg.descripcionGarantia  FROM tb_garantias_creditos tgc
                        INNER JOIN cli_garantia cg ON cg.idGarantia  = tgc.id_garantia
                        WHERE tgc.id_cremcre_meta = ? AND cg.idTipoGa=3 AND cg.idTipoDoc=8 AND cg.estado=1)";

            $database->executeQuery($queryahomctaupdate, [$codcredito]);

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++ Cambio del estado de la cuenta de ahorro de plazo fijo por que ingreso como garantia +++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            $queryaprctaupdate = "UPDATE aprcta cta SET cta.ret = 0 WHERE cta.ccodaport IN
                        (SELECT cg.descripcionGarantia  FROM tb_garantias_creditos tgc
                        INNER JOIN cli_garantia cg ON cg.idGarantia  = tgc.id_garantia
                        WHERE tgc.id_cremcre_meta = ? AND cg.idTipoGa=3 AND cg.idTipoDoc=18 AND cg.estado=1)";

            $database->executeQuery($queryaprctaupdate, [$codcredito]);


            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++ SI HAY DESCUENTOS POR REFINANCIAMIENTO, AQUI SE CONTROLAN ($ARCHIVOS[3][3] TIENE LA CUENTA)+++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($refinance != null) {
                foreach ($refinance as $key => $gastoValue) {
                    $cuentaref = $gastoValue['creditoActivo'];
                    $montoref = $gastoValue['interes'] + $gastoValue['saldoCapital'];

                    if ($cuentaref != 0 && $montoref > 0) { //Si se seleccionó una cuenta y el monto a descontar es mayor a 0
                        $query = 'SELECT NCapDes,IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG="P" AND CESTADO!="X"),0) pagadokp,
                                            pr.id_fondo,pr.id_cuenta_capital,pr.id_cuenta_interes,
                                            IFNULL((SELECT MAX(CNROCUO) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG="P" AND CESTADO!="X"),0) nocuota
                                            FROM cremcre_meta cm INNER JOIN cre_productos pr ON pr.id=cm.CCODPRD
                                            WHERE CCODCTA=?';

                        $data = $database->getAllResults($query, [$cuentaref]);

                        if (empty($data)) {
                            $showmensaje = true;
                            throw new Exception('No se encontró la cuenta a cancelar: ' . $cuentaref);
                        }

                        $fondoref = $data[0]['id_fondo'];
                        $ccntkpref = $data[0]['id_cuenta_capital'];
                        $ccntintref = $data[0]['id_cuenta_interes'];
                        $mondesref = $data[0]['NCapDes'];
                        $pagadoref = $data[0]['pagadokp'];
                        $nocuota = $data[0]['nocuota'];
                        $saldoref = round($mondesref - $pagadoref, 2);

                        if ($montoref < $saldoref) {
                            $showmensaje = true;
                            throw new Exception('El monto ingresado (' . $montoref . ') no cubre el saldo pendiente(' . $saldoref . '), verificar');
                        }

                        $credkar = array(
                            'CCODCTA' => $cuentaref,
                            'DFECPRO' => $fechadesembolso,
                            'DFECSIS' => $hoy2,
                            'CNROCUO' => ($nocuota + 1),
                            'NMONTO' => $montoref,
                            'CNUMING' => 'CREF',
                            'CCONCEP' => "Cancelacion por refinanciamiento",
                            'KP' => $gastoValue['saldoCapital'],
                            'INTERES' => ($gastoValue['interes']),
                            'MORA' => 0,
                            'AHOPRG' => 0,
                            'OTR' => 0,
                            'CCODINS' => "1",
                            'CCODOFI' => "1",
                            'CCODUSU' => $idusuario,
                            'CTIPPAG' => "P",
                            'CMONEDA' => "Q",
                            'CBANCO' => "",
                            'FormPago' => "4", /* LA FORMA DE PAGO ES 4 POR QUE NO ES 1 EFECTIVO, 2 BANCOS NI 3 TRANSFERENCIA */
                            'CCODBANCO' => "C55",
                            'CESTADO' => "1",
                            'DFECMOD' =>  $fechadesembolso,
                            'CTERMID' => "0",
                            'MANCOMUNAD' => "0"
                        );
                        $database->insert('CREDKAR', $credkar);

                        //ACTUALIZACION DE CUOTAS DEL PLAN DE PAGO
                        $database->executeQuery('CALL update_ppg_account(?);', [$cuentaref]);
                        //READECUACION DE LAS CUOTAS PENDIENTES EN EL PLAN DE PAGO
                        //UPDATE Cre_ppg SET ncapita=(ncapita-ncappag),nintere=(nintere-nintpag) WHERE ccodcta="0020010200000006" AND cestado='X';

                        $database->executeQuery("UPDATE Cre_ppg SET ncapita=(ncapita-ncappag),nintere=(nintere-nintpag) WHERE ccodcta=? AND cestado='X'", [$cuentaref]);

                        //ACTUALIZACION DE CUOTAS DEL PLAN DE PAGO PARA
                        $database->executeQuery('CALL update_ppg_account(?);', [$cuentaref]);
                    }
                }
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Correcto,  Desembolso con $tipodesembolso generado, con No.: $numpartida";
            $status = 1;
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status, $codcredito, ($tipodesembolso ?? 1), ($id_ctb_diario ?? 0)]);
        break;
    case 'listado_consultar_estado_cuenta': {
            $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cm.CodCli AS codcli,cl.no_identifica as dpi, cl.short_name AS nombre, cm.NCiclo AS ciclo, cm.MonSug AS monsug, cm.TipoEnti AS tipocred, cm.Cestado FROM cremcre_meta cm INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE (cm.Cestado='F' OR cm.Cestado='G') ORDER BY cm.CCODCTA ASC");
            //se cargan los datos de las beneficiarios a un array
            $array_datos = array();
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $array_datos[] = array(
                    "0" => $i + 1,
                    "1" => $fila["ccodcta"],
                    "2" => $fila["dpi"],
                    "3" => $fila["nombre"],
                    "4" => $fila["ciclo"],
                    "5" => $fila["monsug"],
                    "6" => ($fila["tipocred"] == 'INDI') ? ('Individual') : ('Grupal'),
                    "7" => ($fila["Cestado"] == 'F') ? ('Vigente') : ('Cancelado'),
                    "8" => '<button type="button" class="btn btn-success btn-sm"  data-bs-dismiss="modal" onclick="printdiv2(`#cuadro`,`' . $fila["ccodcta"] . '`)">Aceptar</button> '
                );
                $i++;
            }
            $results = array(
                "sEcho" => 1,
                "iTotalRecords" => count($array_datos),
                "iTotalDisplayRecords" => count($array_datos),
                "aaData" => $array_datos
            );
            echo json_encode($results);
            mysqli_close($conexion);
        }
        break;
    case 'restructuracionPpg':
        include_once "../../includes/Config/model/sqlBasica/sql.php";
        $sqlBasic = new ConsutlaSql();
        $conn = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
        $conn->openConnection();
        $conn->beginTransaction();
        /******************************************************
         *DATOS PARA INICIAR, LA RESTRUCTURACION PPG***********
         ******************************************************/
        $inputs = $_POST['inputs'];
        $selects = $_POST['selects'];
        /******************************************************
         *VALIDAR SI EL CREDITO YA FUE RESTRUCTADO ************
         ******************************************************/
        $data = array(
            'ccodcta' => $inputs[0],
            'cnrocuo' => 0
        );
        $sqlVal = 'SELECT EXISTS (SELECT *FROM Cre_ppg cp WHERE ccodcta = :ccodcta AND cnrocuo = :cnrocuo) restructurado';
        $rst = $conn->selectEspecial($sqlVal, $data);
        // if (!$rst) {
        //     $conn->rollback();
        //     echo json_encode(['Restructuracion erro 000', '0']);
        //     return;
        // }
        if ($rst == 1) {
            echo json_encode(['El crédito no se puede restructurar por segunda vez... ', '2']);
            return;
        }

        /******************************************************
         *OBTENER UNA COPIA DEL LA Cre_ppg ANTERIOR************
         ******************************************************/
        $creppg_ant = $conn->selectDataID("Cre_ppg", "ccodcta", $inputs[0]);
        foreach ($creppg_ant as $row) {
            $datos = array(
                'ccodcta' => $row['ccodcta'],
                'dfecven' => $row['dfecven'],
                'dfecpag' => $row['dfecpag'],
                'cestado' => $row['cestado'],
                'ctipope' => $row['ctipope'],
                'cnrocuo' => $row['cnrocuo'],
                'SaldoCapital' => $row['SaldoCapital'],
                'nmorpag' => $row['nmorpag'],
                'ncappag' => $row['ncappag'],
                'nintpag' => $row['nintpag'],
                'AhoPrgPag' => $row['AhoPrgPag'],
                'OtrosPagosPag' => $row['OtrosPagosPag'],
                'ccodusu' => $row['ccodusu'],
                'dfecmod' => $row['dfecmod'],
                'cflag' => $row['cflag'],
                'codigo' => $row['codigo'],
                'creditosaf' => $row['creditosaf'],
                'saldo' => $row['saldo'],
                'nintmor' => $row['nintmor'],
                'ncapita' => $row['ncapita'],
                'nintere' => $row['nintere'],
                'NAhoProgra' => $row['NAhoProgra'],
                'OtrosPagos' => $row['OtrosPagos'],
                'delete_by' => $idusuario,
                'OtrosPagos' => $hoy2
            );
            /******************************************************
             *LA COPIA DE LA CRE_PPG INSERTARLA EN LA BITACORA*****
             ******************************************************/
            $sqlBitacoraCre_ppg = $sqlBasic->g_insert("bitacora_Cre_ppg", $datos);
            $rst = $conn->executeQuery($sqlBitacoraCre_ppg, $datos);

            if (!$rst) {
                $conn->rollback();
                echo json_encode(['Restructuracion erro 001', '0']);
                return;
            }
        }
        /******************************************************
         *OBTENER UNA COPIA DEL LA CREMCRE ANTERIOR************
         ******************************************************/
        $cremcre = $conn->selectDataID("cremcre_meta", "CCODCTA", $inputs[0]);
        foreach ($cremcre as $datos) {
            $datos['create_by'] = $idusuario;
            $datos['create_at'] = $hoy2;

            $sqlBitacoraCremcre = $sqlBasic->g_insert("bitacora_cremcre_meta", $datos);
            $rst = $conn->executeQuery($sqlBitacoraCremcre, $datos);

            if (!$rst) {
                $conn->rollback();
                echo json_encode(['Restructuracion erro cop000', '0']);
                return;
            }
        }
        // echo json_encode(['Copia generada de la cremcre', '0']);
        // return;
        /******************************************************
         *OBTENER SALDO E INT PAGADO ASI COMO LA MORA Y MORA PENDIENTE
         ******************************************************/
        $SUM_cap_int_mor_otr = $conn->selectAtributos("SELECT SUM(ncapita) capPag, SUM(nintere) intPag, IFNULL(SUM(nmorpag),0) moraPag, IFNULL(SUM(OtrosPagosPag),0) otrPag FROM", " Cre_ppg ", ['ccodcta', 'cestado'], [$inputs[0], 'P']);
        /******************************************************
         *PERDON DE MORA***************************************
         ******************************************************/
        $diaANT = date("Y-m-d", strtotime("-1 day"));
        $sqlMora = "SELECT ccodcta, cnrocuo, IFNULL(SUM(nmorpag),0) mora FROM Cre_ppg WHERE cestado = :cestado AND dfecven <= :dfecven AND ccodcta = :ccodcta AND nmorpag > :nmorpag";
        $data1 = array(
            'cestado' => "X",
            'dfecven' => $diaANT,
            'ccodcta' => $inputs[0],
            'nmorpag' => 0,
        );

        $perdonMora = $conn->selectNom($sqlMora, $data1);

        foreach ($perdonMora as $row) {
            if (isset($row['mora']) && $row['mora'] > 0) {
                $datos = array(
                    "tipo" => 2,
                    "ccodcta" => $row['ccodcta'],
                    "num_pago" => $row['cnrocuo'],
                    "efec_real" => $row['mora'],
                    "efec_perdonado" => $row['mora'],
                    "created_by" => $idusuario,
                    "created_at" => $hoy2
                );
                $sql_perMora = $sqlBasic->g_insert('tb_rpt_perdon', $datos);
                $rst = $conn->executeQuery($sql_perMora, $datos);
                if (!$rst) {
                    $conn->rollback();
                    echo json_encode(['Restructuracion erro 002', '0']);
                    return;
                }
            }
        }
        /******************************************************
         *ELIMINAR LA CRE_PPG ANTIGUA**************************
         ******************************************************/
        $dataDelete = array(
            'ccodcta' => $inputs[0]
        );
        if (isset($inputs[0])) {
            $conn->delete("Cre_ppg", "ccodcta = :ccodcta", $dataDelete);
        } else {
            $conn->rollback();
            echo json_encode(['Restructuracion erro 003', '0']);
            return;
        }
        /******************************************************
         *CREAR UNA CUOTA DE PAGO NUEVO DONDE SE INCLUE, EL CAP, INT Y MORA PAGADA
         ******************************************************/
        $datos = array(
            'ccodcta' => $inputs[0],
            'dfecven' => $inputs[7],
            'dfecpag' => $inputs[7],
            'cestado' => 'P',
            'ctipope' => 0,
            'cnrocuo' => 0,
            'SaldoCapital' => $inputs[3],
            'nmorpag' => $SUM_cap_int_mor_otr['moraPag'],
            'ncappag' => 0,
            'nintpag' => 0,
            'AhoPrgPag' => 0,
            'OtrosPagosPag' => 0,
            'ccodusu' => $idusuario,
            'dfecmod' => $inputs[7],
            'nintmor' => 0,
            'ncapita' => $SUM_cap_int_mor_otr['capPag'],
            'nintere' => $SUM_cap_int_mor_otr['intPag'],
            'NAhoProgra' => 0,
            'OtrosPagos' => $SUM_cap_int_mor_otr['otrPag'],
        );

        $PAGO0 = $sqlBasic->g_insert('Cre_ppg', $datos);
        $rst = $conn->executeQuery($PAGO0, $datos);
        if (!$rst) {
            $conn->rollback();
            echo json_encode(['Restructuracion erro 004', '0']);
            return;
        }

        /******************************************************
         *ACTUALIZAR LA CREMCRE META CON LOS NUEVOS DATOS******
         ******************************************************/

        $datos = array(
            'CCODPRD' => $inputs[6],
            'NIntApro' => $inputs[2],
            'DfecPago' => $inputs[4],
            'noPeriodo' => $inputs[5],

            'CtipCre' => $selects[0],
            'NtipPerC' => $selects[1]
        );
        $upCremcre = $sqlBasic->g_update("cremcre_meta", $datos, "CCODCTA");
        $datos['CCODCTA'] = $inputs[0];

        // echo json_encode(['Restructuracion erro 005 ' . $upCremcre, '0']);
        // $conn->rollback();
        // return;

        $rst = $conn->executeQuery($upCremcre, $datos);

        if (!$rst) {
            $conn->rollback();
            echo json_encode(['Restructuracion erro 005', '0']);
            return;
        }
        $conn->commit(); //NOTA. Se realizo el comit en esta parte ya que la coneccion que esta utilizando es PDO+POO+CONSULTA PREPARADA y la conexion que genera la ppg es basica
        /******************************************************
         *CREAR EL NUEVO PLAN DE PAGOS *************************
         ******************************************************/
        $rst = creppg_INST($inputs[0], $conexion, $inputs[3]);
        if (!$rst) {
            $conn->rollback();
            echo json_encode(['Restructuracion erro 004', '0']);
            return;
        }

        echo json_encode(['La restructuración de plan de pagos se generó y guardo con éxito.', '1']);
        return;

        break;
    case 'buscar_actividadeconomica':
        $id = $_POST['id'];
        $data[] = [];
        $bandera = true;
        $consulta = mysqli_query($general, "SELECT act.id_ActiEcono AS id, act.Titulo AS descripcion FROM `tb_ActiEcono` act where act.Id_SctrEcono='$id'");
        $aux = mysqli_error($general);
        if ($aux) {
            echo json_encode([$aux, '0']);
            return;
        }
        if ($consulta) {
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $bandera = false;
                $data[$i] = $fila;
                $i++;
            }
            if ($bandera) {
                echo json_encode(['El sector no cuenta con actividades economicas', '0']);
                return;
            }
            echo json_encode(['Satisfactorio', '1', $data]);
        } else {
            echo json_encode(['Error en la recuperacion de cuentas de bancos, intente nuevamente', '0']);
        }
        mysqli_close($general);
        break;
    case 'lincred':
        $consulta = mysqli_query($conexion, "SELECT pro.id,pro.cod_producto,pro.nombre nompro,pro.descripcion descriprod,ff.descripcion fondesc,pro.tasa_interes, pro.monto_maximo
            FROM cre_productos pro
            INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo WHERE pro.estado=1
             ORDER BY pro.id ASC");
        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $array_datos[] = array(
                "0" => $fila["cod_producto"],
                "1" => $fila["descriprod"],
                "2" => $fila["nompro"],
                "3" => $fila["fondesc"],
                "4" => $fila["tasa_interes"],
                "5" => $fila["monto_maximo"],
                "6" => '<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick= "seleccionar_cuenta_ctb2(`#id_modal_hidden`,[' . $fila["id"] . ',`' . $fila["cod_producto"] . '`,`' . $fila["nompro"] . '`,`' . $fila["descriprod"] . '`,' . $fila["tasa_interes"] . ',' . $fila["monto_maximo"] . ',`' . $fila["fondesc"] . '`]); cerrar_modal(`#modal_tiposcreditos`, `hide`, `#id_modal_hidden`);" >Aceptar</button>'
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;
    case 'create_solicitud':
        //validar todos los campos necesarios
        //[`codcli`,`nomcli`,`ciclo`,`codprod`,`tasaprod`,`maxprod`,`montosol`,`idprod`,`primerpago`,`cuota`,`crecimiento`,`recomendacion`],
        //[`analista`,`destino`,`sector`,`actividadeconomica`,`agenciaaplica`,`tipocred`,`peri`]
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        $validar = validar_campos([
            [$inputs[0], "", 'Debe seleccionar un cliente'],
            [$inputs[1], "", 'Debe seleccionar un cliente'],
            [$inputs[2], "", 'Debe tener un ciclo'],
            [$inputs[3], "", 'Debe seleccionar un producto'],
            [$inputs[7], "", 'Debe seleccionar un productor'],
            [$inputs[4], "", 'Debe tener una tasa de interes'],
            [$inputs[5], "", 'Debe existir un monto máximo'],
            [$selects[0], "0", 'Debe seleccionar un analista'],
            [$inputs[6], "", 'Debe digitar un monto a solicitar'],
            [$selects[1], "0", 'Debe seleccionar un destino de crédito'],
            [$selects[2], "0", 'Debe seleccionar un sector económico'],
            [$selects[3], "0", 'Debe seleccionar una actividad económica'],
            [$inputs[9], "", 'Debe digitar la cuota'],
            [$selects[5], "0", 'Debe seleccionar una tipo de credito'],
            [$selects[6], "0", 'Debe seleccionar un tipo de periodo'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        //validar que el monto maximo no sea mayor que el monto solicitado
        if ($inputs[6] < 0) {
            echo json_encode(["El monto solicitado no puede ser negativo", '0']);
            return;
        }
        if ($inputs[6] < 100) {
            echo json_encode(["El monto solicitado es demasiado pequeño, no es posible solicitar su crédito", '0']);
            return;
        }
        if ($inputs[6] > $inputs[5]) {
            echo json_encode(["No es posible solicitar el crédito, el monto solicitado no puede ser mayor que el monto máximo del producto", '0']);
            return;
        }
        //validar que se ha seleccionado al menos una garantia
        if (!isset($archivo[4])) {
            echo json_encode(["Debe seleccionar al menos una garantia para el crédito a solicitar", '0']);
            return;
        }

        $idagenciacredito = $selects[4];
        //GENERACION DEL CODIGO DE CREDITO
        // $codcredito = getcrecodcta($archivo[0], '01', $conexion);
        $codcredito = getcrecodcuenta($idagenciacredito, '01', $conexion);
        if ($codcredito[0] == 0) {
            echo json_encode(["Fallo!, No se pudo generar el código de crédito", '0']);
            return;
        }
        $codigoagencia = "";
        $consulta = mysqli_query($conexion, "SELECT ofi.cod_agenc FROM tb_agencia ofi WHERE ofi.id_agencia=$idagenciacredito");
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $codigoagencia = $fila["cod_agenc"];
        }
        if ($codigoagencia == "") {
            echo json_encode(["No se encontro la agencia especificada: " . $idagenciacredito, '0']);
            return;
        }

        //INSERTAR EN LA CREMCRE META
        $conexion->autocommit(false);
        try {
            //INSERCCION EN LA CREMCRE META
            $res = $conexion->query("INSERT INTO `cremcre_meta`(`CCODCTA`, `CodCli`, `Cestado`, `MontoSol`, `CODAgencia`, `CodAnal`,`CCODPRD`,`DfecSol`,`Cdescre`,`CSecEco`,`ActoEcono`,`TipoEnti`,`NCiclo`,`NIntApro`,`fecha_operacion`,`DfecPago`,`cuotassolicita`,`crecimiento`,`recomendacion`,`CtipCre`,`NtipPerC`)
            VALUES ('$codcredito[1]','$inputs[0]','A','$inputs[6]','$codigoagencia','$selects[0]','$inputs[7]','$hoy2','$selects[1]','$selects[2]','$selects[3]','INDI','$inputs[2]','$inputs[4]','$hoy','$inputs[8]',$inputs[9],'$inputs[10]','$inputs[11]','$selects[5]','$selects[6]')");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Error en la inserción de datos del credito', '0']);
                return;
            }
            if (!$res) {
                $conexion->commit();
                echo json_encode(['No se logro guardar los datos del crédito solicitado', '1']);
            }
            //INSERCCION DE GARANTIAS
            for ($i = 0; $i < count($archivo[4]); $i++) {
                $res = $conexion->query("INSERT INTO `tb_garantias_creditos`(`id_cremcre_meta`, `id_garantia`) VALUES ('$codcredito[1]'," . $archivo[4][$i] . ")");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    $conexion->rollback();
                    echo json_encode(['Error en la inserción de garantias del crédito', '0']);
                    return;
                }
                if (!$res) {
                    $conexion->rollback();
                    echo json_encode(['Solicitud no generada satisfactoriamente', '0']);
                }
            }
            $conexion->commit();
            echo json_encode(['Solicitud generada satisfactoriamente, código crédito: ' . $codcredito[1], '1', $codcredito[1]]);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'clientes_a_analizar':
        $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name AS nombre, cl.Direccion AS direccion, (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo, cm.Cestado AS estado FROM cremcre_meta cm
        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
        WHERE (cm.Cestado='A' OR cm.Cestado='D') AND cm.TipoEnti='INDI'");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $i = 0;
        $contador = 1;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $estado = ($fila['estado'] == 'A') ? 'Solicitado' : 'Analizado';
            $array_datos[] = array(
                "0" => $contador,
                "1" => $fila["ccodcta"],
                "2" => $fila["nombre"],
                "3" => $fila["direccion"],
                "4" => $fila["ciclo"],
                "5" => $estado,
                "6" => '<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2(`#cuadro`,`' . $fila["ccodcta"] . '`)" >Aceptar</button>'
            );
            $i++;
            $contador++;
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;
    case 'rechazar_individual':
        $id = $_POST["ideliminar"];
        if ($id[0] == "" || $id[1] == "") {
            echo json_encode(['Tiene que seleccionar un crédito a cancelar', '0']);
            return;
        }
        if ($id[2] == "0") {
            echo json_encode(['Tiene que seleccionar un motivo de rechazo', '0']);
            return;
        }

        $conexion->autocommit(false);
        try {
            $res = $conexion->query("UPDATE `cremcre_meta` SET  Cestado='L', `fecha_operacion`='$hoy', id_rechazo_cred='" . $id[2] . "'  WHERE `cremcre_meta`.`CCODCTA`='" . $id[0] . "'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Fallo al actualizar datos', '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                echo json_encode(['Credito rechazado satisfactoriamente', '1']);
            } else {
                $conexion->rollback();
                echo json_encode(['Crédito no rechazado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la eliminacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;

    case 'update_analisis':
        //validar todos los campos necesarios
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];
        // `ccodcta`,`codcli`,`nomcli`,`montosol`,`montosug`,`primerpago`,`cuota`,`fecdesembolso`,`dictamen`
        $validar = validar_campos([
            [$inputs[0], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[1], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[2], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[3], "", 'Debe digitar un interes'],
            [$selects[2], "0", 'Debe seleccionar un analista'],
            [$inputs[4], "", 'Debe tener un monto solicitado'],
            [$inputs[5], "", 'Debe digitar un monto a aprobar'],
            [$selects[0], "0", 'Debe seleccionar un tipo de crédito'],
            [$selects[1], "0", 'Debe seleccionar un tipo de periodo'],
            [$inputs[6], "", 'Debe digitar una fecha de primer pago'],
            [$inputs[7], "", 'Debe digitar un numero de cuotas o plazo'],
            [$inputs[8], "", 'Debe digitar una fecha de desembolso'],
            [$inputs[9], "", 'Debe digitar un número de dictamen'],
            [$inputs[10], "", 'Debe seleccionar un producto'],
            [$inputs[11], "", 'Debe seleccionar un producto'],
            [$inputs[12], "", 'Debe existir un monto maximo']
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        //validar que el monto maximo no sea mayor que el monto solicitado
        if ($inputs[3] < 0) {
            echo json_encode(["No puede digitar un interes menor a 0", '0']);
            return;
        }
        if ($inputs[5] < 0) {
            echo json_encode(["No puede digitar un monto a aprobar menor a 0", '0']);
            return;
        }
        if ($inputs[5] < 100) {
            echo json_encode(["El monto a aprobar no puede ser muy pequeño, tiene que ser mayor a Q100.00", '0']);
            return;
        }
        // if ($inputs[5] > $inputs[12]) {
        //     echo json_encode(["El monto a aprobar no puede ser mayor que el monto maximo que permite el producto", '0']);
        //     return;
        // }
        /*         if ($inputs[6] < $hoy) {
            echo json_encode(["La fecha de primer pago no pueder ser menor al dia de hoy", '0']);
            return;
        } */
        if ($inputs[7] < 1) {
            echo json_encode(["El número de cuotas tiene que ser al menos 1", '0']);
            return;
        }
        /* if ($inputs[8] < $hoy) {
            echo json_encode(["La fecha de desembolso no pueder ser menor al dia de hoy", '0']);
            return;
        } */
        if ($inputs[6] < $inputs[8]) {
            echo json_encode(["La fecha del primer pago no debe ser menor a la fecha de desembolso", '0']);
            return;
        }

        $afectaInteres = (!empty($archivo[4]) && $archivo[4] === "true") ? 1 : 0;

        $peripagcap = $selects[3];
        //ACTUALIZACION EN LA CREMCRE META
        $conexion->autocommit(false);
        try {
            $res = $conexion->query("UPDATE `cremcre_meta` SET  Cestado='A', CCODPRD='$inputs[10]', NIntApro='" . $inputs[3] . "', MonSug='$inputs[5]', CtipCre='$selects[0]', NtipPerC='$selects[1]', DfecPago='$inputs[6]',
            noPeriodo='" . $inputs[7] . "', DfecDsbls='$inputs[8]', Dictamen='$inputs[9]', NCiclo='$archivo[3]', CodAnal='$selects[2]',`fecha_operacion`='$hoy',`peripagcap`='$peripagcap',`afectaInteres`='$afectaInteres' WHERE `cremcre_meta`.`CCODCTA`='$inputs[0]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Fallo al actualizar datos', '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                echo json_encode(['Credito analizado satisfactoriamente', '1']);
            } else {
                $conexion->rollback();
                echo json_encode(['Crédito no analizado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la actualizacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'update_garantias':
        //validar todos los campos necesarios
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];
        // `ccodcta`,`codcli`,`nomcli`,`montosol`,`montosug`,`primerpago`,`cuota`,`fecdesembolso`,`dictamen`
        $validar = validar_campos([
            [$inputs[0], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[1], "", 'Debe seleccionar un cliente con crédito'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        //validar que se ha seleccionado al menos una garantia
        if (!isset($archivo[4])) {
            echo json_encode(["Debe seleccionar al menos una garantia para el crédito a aprobar", '0']);
            return;
        }

        //ACTUALIZACION EN LA CREMCRE META
        $conexion->autocommit(false);
        try {
            $res = $conexion->query("DELETE FROM `tb_garantias_creditos` WHERE id_cremcre_meta='$inputs[0]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Error en la actualizacion de garantias del crédito -E1', '0']);
                return;
            }
            if (!$res) {
                $conexion->rollback();
                echo json_encode(['Garantias no actualizadas correctamente-E1', '0']);
            }

            //INSERCCION DE GARANTIAS
            for ($i = 0; $i < count($archivo[4]); $i++) {
                $res = $conexion->query("INSERT INTO `tb_garantias_creditos`(`id_cremcre_meta`, `id_garantia`) VALUES ('$inputs[0]'," . $archivo[4][$i] . ")");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    $conexion->rollback();
                    echo json_encode(['Error en la actualizacion de garantias del crédito-E2', '0']);
                    return;
                }
                if (!$res) {
                    $conexion->rollback();
                    echo json_encode(['Garantias no actualizadas satisfactoriamente-E2', '0']);
                }
            }

            $conexion->commit();
            echo json_encode(['Garantias actualizadas satisfactoriamente', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la actualizacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'create_analisis':


        //obtiene([`ccodcta`,`codcli`,`nomcli`,`tasaprod`,`montosol`,`montosug`,`primerpago`,`cuota`,`fecdesembolso`,`dictamen`,`idprod`,`codprod`,`maxprod`],
        //[`tipocred`,`peri`,`analista`],[],`create_analisis`
        //validar todos los campos necesarios
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];
        // `ccodcta`,`codcli`,`nomcli`,`montosol`,`montosug`,`primerpago`,`cuota`,`fecdesembolso`,`dictamen`
        $validar = validar_campos([
            [$inputs[0], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[1], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[2], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[3], "", 'Debe digitar un interes'],
            [$selects[2], "0", 'Debe seleccionar un analista'],
            [$inputs[4], "", 'Debe tener un monto solicitado'],
            [$inputs[5], "", 'Debe digitar un monto a aprobar'],
            [$selects[0], "0", 'Debe seleccionar un tipo de crédito'],
            [$selects[1], "0", 'Debe seleccionar un tipo de periodo'],
            [$inputs[6], "", 'Debe digitar una fecha de primer pago'],
            [$inputs[7], "", 'Debe digitar un numero de cuotas o plazo'],
            [$inputs[8], "", 'Debe digitar una fecha de desembolso'],
            [$inputs[9], "", 'Debe digitar un número de dictamen'],
            [$inputs[10], "", 'Debe seleccionar un producto'],
            [$inputs[11], "", 'Debe seleccionar un producto'],
            [$inputs[12], "", 'Debe existir un monto maximo']
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        //validar que el monto maximo no sea mayor que el monto solicitado
        if ($inputs[3] < 0) {
            echo json_encode(["No puede digitar un interes menor a 0", '0']);
            return;
        }
        if (!$archivo[4]) {
            echo json_encode(["No puede aprobar el crédito porque no tiene al menos una garantía", '0']);
            return;
        }
        if ($inputs[5] < 0) {
            echo json_encode(["No puede digitar un monto a aprobar menor a 0", '0']);
            return;
        }
        if ($inputs[5] < 100) {
            echo json_encode(["El monto a aprobar no puede ser muy pequeño, tiene que ser mayor a Q100.00", '0']);
            return;
        }
        if ($inputs[5] > $inputs[12]) {
            echo json_encode(["El monto a aprobar no puede ser mayor que el monto maximo que permite el producto", '0']);
            return;
        }
        /*         if ($inputs[6] < $hoy) {
            echo json_encode(["La fecha de primer pago no pueder ser menor al dia de hoy", '0']);
            return;
        } */
        if ($inputs[7] < 1) {
            echo json_encode(["El número de cuotas tiene que ser al menos 1", '0']);
            return;
        }
        if ($inputs[6] < $inputs[8]) {
            echo json_encode(["La fecha del primer pago no debe ser menor a la fecha de desembolso", '0']);
            return;
        }
        /*         if ($inputs[8] < $hoy) {
            echo json_encode(["La fecha de desembolso no pueder ser menor al dia de hoy", '0']);
            return;
        } */
        //validar que se ha seleccionado al menos una garantia
        if (!isset($archivo[5])) {
            echo json_encode(["Debe seleccionar al menos una garantia para el crédito a aprobar", '0']);
            return;
        }
        $peripagcap = $selects[3];
        //ACTUALIZACION EN LA CREMCRE META
        $conexion->autocommit(false);
        try {
            //ACTUALIZACION EN LA CREMCRE META
            $res = $conexion->query("UPDATE `cremcre_meta` SET  Cestado='D', CCODPRD='$inputs[10]', NIntApro='$inputs[3]', MonSug='$inputs[5]', CtipCre='$selects[0]', NtipPerC='$selects[1]', DfecPago='$inputs[6]',
            noPeriodo='$inputs[7]', DfecDsbls='$inputs[8]', Dictamen='$inputs[9]', NCiclo='$archivo[3]', DFecAnal='$hoy2', CodAnal='$selects[2]',`fecha_operacion`='$hoy',`peripagcap`='$peripagcap' WHERE `cremcre_meta`.`CCODCTA`='$inputs[0]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Error al aprobar el credito', '0']);
                return;
            }
            if (!$res) {
                $conexion->rollback();
                echo json_encode(['Crédito no aprobado satisfactoriamente', '0']);
            }
            //ELIMINACION DE DATOS DE GARANTIAS
            $res = $conexion->query("DELETE FROM `tb_garantias_creditos` WHERE id_cremcre_meta='$inputs[0]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Error en la actualizacion de garantias del crédito -E1', '0']);
                return;
            }
            if (!$res) {
                $conexion->rollback();
                echo json_encode(['Garantias no actualizadas correctamente-E1', '0']);
            }

            //INSERCCION DE GARANTIAS
            for ($i = 0; $i < count($archivo[5]); $i++) {
                $res = $conexion->query("INSERT INTO `tb_garantias_creditos`(`id_cremcre_meta`, `id_garantia`) VALUES ('$inputs[0]'," . $archivo[5][$i] . ")");
                $aux = mysqli_error($conexion);
                if ($aux) {
                    $conexion->rollback();
                    echo json_encode(['Error en la actualizacion de garantias del crédito-E2', '0']);
                    return;
                }
                if (!$res) {
                    $conexion->rollback();
                    echo json_encode(['Garantias no actualizadas satisfactoriamente-E2', '0']);
                }
            }

            $conexion->commit();
            echo json_encode(['Credito aprobado satisfactoriamente', '1', $inputs[0]]);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la actualizacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;

    case 'clientes_a_aprobar':
        $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name AS nombre, cl.Direccion AS direccion, (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo, cm.Cestado AS estado FROM cremcre_meta cm
        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
        WHERE cm.Cestado='D' AND cm.TipoEnti='INDI'");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $i = 0;
        $contador = 1;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $estado = ($fila['estado'] == 'D') ? 'Analizado' : ' ';
            $array_datos[] = array(
                "0" => $contador,
                "1" => $fila["ccodcta"],
                "2" => $fila["nombre"],
                "3" => $fila["direccion"],
                "4" => $fila["ciclo"],
                "5" => $estado,
                "6" => '<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2(`#cuadro`,`' . $fila["ccodcta"] . '`)" >Aceptar</button>'
            );
            $i++;
            $contador++;
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;

    case 'cred_analisis_a_solicitud':
        $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name AS nombre, cl.Direccion AS direccion, (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo, cm.Cestado AS estado FROM cremcre_meta cm
            INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
            WHERE cm.Cestado='D' AND cm.TipoEnti='INDI'");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $i = 0;
        $contador = 1;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $estado = ($fila['estado'] == 'D') ? 'Analizado' : ' ';
            $array_datos[] = array(
                "0" => $contador,
                "1" => $fila["ccodcta"],
                "2" => $fila["nombre"],
                "3" => $fila["direccion"],
                "4" => $fila["ciclo"],
                "5" => $estado,
                "6" => '<button type="button" class="btn btn-warning" data-bs-dismiss="modal"   data-ccodcta="' . $fila["ccodcta"] . '" onclick="enviarAprob(this)">Aprobacion a Solicitud</button>'
            );
            $i++;
            $contador++;
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;

    case 'listadp_desembolso_a_solicitud':

        $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name, cm.CodCli AS codcli, cm.CODAgencia AS codagencia, pd.cod_producto AS codproducto, cm.MonSug AS monto, cm.Cestado AS estado   FROM cremcre_meta cm
        INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.Cestado='E' AND cm.TipoEnti='INDI'");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $array_parenteco[] = [];
        $total = 0;
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $array_datos[] = array(
                "0" => $i + 1,
                "1" => $fila["short_name"],
                "2" => $fila["codproducto"],
                "3" => $fila["ccodcta"],
                "4" => $fila["monto"],
                "5" => '<button type="button" class="btn btn-danger" data-bs-dismiss="modal" data-statuss="D" data-ccodcta="' . $fila["ccodcta"] . '" onclick="confirmchangestatus(this)">Pasar crédito a Análisis</button>'
            );
            $i++;

            // eliminar(ideliminar, dir, xtra, condi)
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
        break;

    case 'create_aprobacion':
        //validar todos los campos necesarios
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];
        // `ccodcta`,`codcli`,`nomcli`,`montosol`,`montosug`,`primerpago`,`cuota`,`fecdesembolso`,`dictamen`
        $validar = validar_campos([
            [$inputs[0], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[1], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[2], "", 'Debe seleccionar un cliente con crédito'],
            [$inputs[3], "", 'Debe tener un código de producto'],
            [$inputs[4], "", 'Debe tener un código de producto'],
            [$selects[0], "", 'Debe seleccionar un tipo de contrato'],
            [$archivo[3], "", 'Debe tener un ciclo'],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        //ACTUALIZACION EN LA CREMCRE META
        $conexion->autocommit(false);
        try {
            $res = $conexion->query("UPDATE `cremcre_meta` SET  Cestado='E', CTipCon='$selects[0]', NCiclo='$archivo[3]', DFecApr='$hoy2',`fecha_operacion`='$hoy' WHERE `cremcre_meta`.`CCODCTA`='$inputs[0]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Fallo al confirmar aprobación de crédito', '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                echo json_encode(['Credito aprobado satisfactoriamente', '1', $inputs[0]]);
            } else {
                $conexion->rollback();
                echo json_encode(['Crédito no aprobado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al hacer la actualizacion: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;

    case 'consultar_garantias': {
            $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name, cm.CodCli AS codcli, cm.CODAgencia AS codagencia, pd.cod_producto AS codproducto, cm.MonSug AS monto, cm.Cestado AS estado   FROM cremcre_meta cm
            INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
            INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.Cestado='E'");
            //se cargan los datos de las beneficiarios a un array
            $array_datos = array();
            $array_parenteco[] = [];
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $array_datos[] = array(
                    "0" => $i + 1,
                    "1" => $fila["short_name"],
                    "2" => $fila["codproducto"],
                    "3" => $fila["ccodcta"],
                    "4" => $fila["monto"],
                    // "5" => '<button type="button" class="btn btn-success" onclick="seleccionar_credito_a_desembolsar(`#id_modal_hidden`,[`' . $fila["codcli"] . '`,`' . $fila["short_name"] . '`,`' . $fila["codagencia"] . '`,`' . $fila["codproducto"] . '`,`' . $fila["ccodcta"] . '`,`' . $fila["monto"] . '`]); consultar_gastos_monto(`' . $fila["ccodcta"] . '`); mostrar_tabla_gastos(`' . $fila["ccodcta"] . '`); buscar_cuentas(); cerrar_modal(`#modal_creditos_a_desembolsar`, `hide`, `#id_modal_hidden`);">Aceptar</button> '
                    "5" => '<button type="button" class="btn btn-success" onclick="seleccionar_credito_a_desembolsar(`#id_modal_hidden`,[`' . $fila["codcli"] . '`,`' . $fila["short_name"] . '`,`' . $fila["codagencia"] . '`,`' . $fila["codproducto"] . '`,`' . $fila["ccodcta"] . '`,`' . $fila["monto"] . '`]); consultar_gastos_monto(`' . $fila["ccodcta"] . '`); mostrar_tabla_gastos(`' . $fila["ccodcta"] . '`); cerrar_modal(`#modal_creditos_a_desembolsar`, `hide`, `#id_modal_hidden`); $(`#bt_desembolsar`).show(); concepto_default(`' . $fila["short_name"] . '`, `0`);">Aceptar</button>'

                );
                $i++;

                // eliminar(ideliminar, dir, xtra, condi)
            }
            $results = array(
                "sEcho" => 1, //info para datatables
                "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
                "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
                "aaData" => $array_datos
            );
            mysqli_close($conexion);
            echo json_encode($results);
        }
        break;

    case 'create_garantia_gen':
        // obtienePlus(['descripcion','direccion','valorComercial','montoAvaluo','montoGravamen','latitud','longitud','altitud','precision_gps'],
        // ['selecTipoGa','selecTipoDoc','departamento','selectMunicipio'],[],'create_garantia_gen','idCliente',['idCliente'],'NULL','¿Está seguro de guardar la garantía?',['foto'])

        list($descripcion, $direccion, $valorComercial, $montoAvaluo, $montoGravamen, $latitud, $longitud, $altitud, $precision_gps) = $_POST['inputs'];
        list($selecTipoGa, $selecTipoDoc, $departamento, $selectMunicipio) = $_POST['selects'];
        list($idCliente) = $_POST['archivo'];

        $validar = validacionescampos([
            [$descripcion, "", 'Ingrese una descripcion', 1],
            [$selecTipoGa, "", 'Seleccione un tipo de garantia', 1],
            [$selecTipoDoc, "", 'Seleccione un tipo de documento', 1],
            [$valorComercial, 0, 'Valor comercial invalido', 2],
            [$montoAvaluo, 0, 'Monto de avalúo invalido', 2],
            [$montoGravamen, 0, 'Monto de gravamen invalido', 2],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        // Validaciones adicionales si al menos uno de los tres valores es mayor a 0
        if ($valorComercial > 0 || $montoAvaluo > 0 || $montoGravamen > 0) {
            if ($montoGravamen > $montoAvaluo) {
                echo json_encode(['El monto de gravamen no puede ser mayor que el monto de avalúo', '0']);
                return;
            }
            if ($montoAvaluo > $valorComercial) {
                echo json_encode(['El monto de avalúo no puede ser mayor que el valor comercial', '0']);
                return;
            }
        }

        // Limpiar la descripción: quitar saltos de línea y espacios extra, y asegurar codificación UTF-8
        $descripcion = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $descripcion)));

        // Validar y limpiar coordenadas
        $latitud = !empty($latitud) && is_numeric($latitud) ? (float)$latitud : null;
        $longitud = !empty($longitud) && is_numeric($longitud) ? (float)$longitud : null;
        $altitud = !empty($altitud) && is_numeric($altitud) ? (float)$altitud : null;
        $precision_gps = !empty($precision_gps) && is_numeric($precision_gps) ? (float)$precision_gps : null;

        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();

            // 1. Insertar en cli_garantia
            $cli_garantia = array(
                "idCliente" => $idCliente,
                "idTipoGa" => $selecTipoGa,
                "idTipoDoc" => $selecTipoDoc,
                "archivo" => "r",
                "descripcionGarantia" => $descripcion,
                "direccion" => $direccion,
                "depa" => $departamento,
                "muni" => $selectMunicipio,
                "valorComercial" => $valorComercial,
                "montoAvaluo" => $montoAvaluo,
                "montoGravamen" => $montoGravamen,
                "fechaCreacion" => $hoy,
                "estado" => 1,
                "created_by" => $idusuario,
                "created_at" => $hoy2,
            );

            $idGarantiaCreada = $database->insert('cli_garantia', $cli_garantia);

            if (!$idGarantiaCreada) {
                $showmensaje = true;
                throw new Exception("Error al crear la garantía");
            }

            // 2. Insertar información adicional de geolocalización si hay coordenadas
            $idAdicionalCreado = null;
            if ($latitud !== null && $longitud !== null) {
                // Intentar con diferentes combinaciones de campos según la estructura real de la tabla
                $cli_adicionales_attempts = [
                    // Intento 1: Con todos los campos incluyendo auditoría
                    array(
                        "entidad_tipo" => "garantia",
                        "entidad_id" => $idGarantiaCreada,
                        "descripcion" => $descripcion,
                        "latitud" => $latitud,
                        "longitud" => $longitud,
                        "altitud" => $altitud,
                        "precision" => $precision_gps,
                        "direccion_texto" => $direccion,
                        "estado" => 1,
                        "created_by" => $idusuario,
                        "created_at" => $hoy2,
                    ),
                    // Intento 2: Sin campos de auditoría
                    array(
                        "entidad_tipo" => "garantia",
                        "entidad_id" => $idGarantiaCreada,
                        "descripcion" => $descripcion,
                        "latitud" => $latitud,
                        "longitud" => $longitud,
                        "altitud" => $altitud,
                        "precision" => $precision_gps,
                        "direccion_texto" => $direccion,
                        "estado" => 1,
                    ),
                    // Intento 3: Solo campos esenciales
                    array(
                        "entidad_tipo" => "garantia",
                        "entidad_id" => $idGarantiaCreada,
                        "latitud" => $latitud,
                        "longitud" => $longitud,
                        "estado" => 1,
                    ),
                ];

                foreach ($cli_adicionales_attempts as $index => $cli_adicionales) {
                    try {
                        $idAdicionalCreado = $database->insert('cli_adicionales', $cli_adicionales);
                        if ($idAdicionalCreado) {
                            break; // Salir del loop si fue exitoso
                        }
                    } catch (Exception $e) {
                        if ($index === count($cli_adicionales_attempts) - 1) {
                            // Si es el último intento y falló, log del error pero no fallar todo el proceso
                            error_log("Error insertando cli_adicionales (último intento): " . $e->getMessage());
                        }
                    }
                }
            }

            // 3. Procesar archivos si existen
            $rutaArchivoGuardado = null;
            if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                $folderInstitucion = (new Agencia($idagencia))->institucion?->getFolderInstitucion();

                if ($folderInstitucion === null) {
                    $showmensaje = true;
                    throw new Exception("No se pudo obtener la carpeta de la institución.");
                }

                $rutaSave = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/" . "garantias/" . $idCliente;
                $rutaEnServidor = "../../../" . $rutaSave;

                foreach ($_FILES['files']['name'] as $key => $name) {
                    if (!empty($name)) {
                        // Comprobar si existe la ruta, si no, se crea
                        if (!is_dir($rutaEnServidor)) {
                            mkdir($rutaEnServidor, 0777, true);
                        }

                        $tmp_name = $_FILES['files']['tmp_name'][$key];
                        $error = $_FILES['files']['error'][$key];
                        $size = $_FILES['files']['size'][$key];

                        // Validar errores de carga
                        if ($error !== UPLOAD_ERR_OK) {
                            $showmensaje = true;
                            throw new Exception("Error en la carga del archivo: Código de error " . $error);
                        }

                        // Validar tamaño (5MB máximo)
                        if ($size > 5 * 1024 * 1024) {
                            $showmensaje = true;
                            throw new Exception("El archivo es muy grande. Tamaño máximo permitido: 5MB");
                        }

                        // Validar extensión
                        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (!in_array($extension, $extensionesPermitidas)) {
                            $showmensaje = true;
                            throw new Exception("Tipo de archivo no permitido. Solo se permiten: JPG, PNG, PDF");
                        }

                        // Generar un nombre único para el archivo
                        $nombreImagen = $idCliente . '_' . $idGarantiaCreada . '_' . date('Ymdhis');
                        $nuevo_nombre = $nombreImagen . '.' . $extension;
                        $ruta_destino = $rutaEnServidor . '/' . $nuevo_nombre;

                        // Mover el archivo a la carpeta de destino
                        if (move_uploaded_file($tmp_name, $ruta_destino)) {
                            $rutaArchivoGuardado = $rutaSave . '/' . $nuevo_nombre;

                            // Actualizar el campo archivo en cli_garantia
                            $database->update(
                                'cli_garantia',
                                ['archivo' => $rutaArchivoGuardado, 'updated_by' => $idusuario, 'updated_at' => $hoy2],
                                'idGarantia=?',
                                [$idGarantiaCreada]
                            );

                            // Insertar en cli_adicional_archivos si se creó información adicional
                            if ($idAdicionalCreado) {
                                $cli_adicional_archivos_attempts = [
                                    // Intento 1: Con campos de auditoría
                                    array(
                                        "id_adicional" => $idAdicionalCreado,
                                        "path_file" => $rutaArchivoGuardado,
                                        "created_by" => $idusuario,
                                        "created_at" => $hoy2,
                                    ),
                                    // Intento 2: Solo campos básicos
                                    array(
                                        "id_adicional" => $idAdicionalCreado,
                                        "path_file" => $rutaArchivoGuardado,
                                    ),
                                ];

                                foreach ($cli_adicional_archivos_attempts as $cli_adicional_archivos) {
                                    try {
                                        $database->insert('cli_adicional_archivos', $cli_adicional_archivos);
                                        break; // Salir si fue exitoso
                                    } catch (Exception $e) {
                                        // Log del error pero continuar
                                        error_log("Error insertando cli_adicional_archivos: " . $e->getMessage());
                                    }
                                }
                            }

                            break; // Solo procesar el primer archivo válido
                        } else {
                            $showmensaje = true;
                            throw new Exception("Error al mover el archivo al directorio de destino: " . $name);
                        }
                    }
                }
            }

            $database->commit();
            $mensaje = "Garantía creada correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    case 'update_garantia_gen':
        // obtienePlus(['descripcion','direccion','valorComercial','montoAvaluo','montoGravamen','latitud','longitud','altitud','precision_gps'],
        // ['selecTipoGa','selecTipoDoc','departamento','selectMunicipio'],[],'update_garantia_gen','idCliente',['idGarantia'],'NULL','¿Está seguro de actualizar la garantía?',['foto'])

        list($descripcion, $direccion, $valorComercial, $montoAvaluo, $montoGravamen, $latitud, $longitud, $altitud, $precision_gps) = $_POST['inputs'];
        list($selecTipoGa, $selecTipoDoc, $departamento, $selectMunicipio) = $_POST['selects'];
        list($idGarantia) = $_POST['archivo'];

        $validar = validacionescampos([
            [$descripcion, "", 'Ingrese una descripcion', 1],
            [$selecTipoGa, "", 'Seleccione un tipo de garantia', 1],
            [$selecTipoDoc, "", 'Seleccione un tipo de documento', 1],
            [$valorComercial, 0, 'Valor comercial invalido', 2],
            [$montoAvaluo, 0, 'Monto de avalúo invalido', 2],
            [$montoGravamen, 0, 'Monto de gravamen invalido', 2],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        // Validaciones adicionales si al menos uno de los tres valores es mayor a 0
        if ($valorComercial > 0 || $montoAvaluo > 0 || $montoGravamen > 0) {
            if ($montoGravamen > $montoAvaluo) {
                echo json_encode(['El monto de gravamen no puede ser mayor que el monto de avalúo', '0']);
                return;
            }
            if ($montoAvaluo > $valorComercial) {
                echo json_encode(['El monto de avalúo no puede ser mayor que el valor comercial', '0']);
                return;
            }
        }

        // Limpiar la descripción: quitar saltos de línea y espacios extra, y asegurar codificación UTF-8
        $descripcion = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $descripcion)));

        // Validar y limpiar coordenadas
        $latitud = !empty($latitud) && is_numeric($latitud) ? (float)$latitud : null;
        $longitud = !empty($longitud) && is_numeric($longitud) ? (float)$longitud : null;
        $altitud = !empty($altitud) && is_numeric($altitud) ? (float)$altitud : null;
        $precision_gps = !empty($precision_gps) && is_numeric($precision_gps) ? (float)$precision_gps : null;

        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();

            // Verificar que la garantía existe
            $getGarantia = $database->selectColumns('cli_garantia', ['idCliente'], 'idGarantia=? AND estado=1', [$idGarantia]);
            if (empty($getGarantia)) {
                $showmensaje = true;
                throw new Exception("La garantía no existe o ya ha sido eliminada.");
            }

            $idCliente = $getGarantia[0]['idCliente'];

            // 1. Actualizar cli_garantia
            $cli_garantia = array(
                "idTipoGa" => $selecTipoGa,
                "idTipoDoc" => $selecTipoDoc,
                //  "archivo" => !empty($archivos) ? json_encode($archivos) : "r", // Guardar los nombres de los archivos como JSON

                "descripcionGarantia" => $descripcion,
                "direccion" => $direccion,
                "depa" => $departamento,
                "muni" => $selectMunicipio,
                "valorComercial" => $valorComercial,
                "montoAvaluo" => $montoAvaluo,
                "montoGravamen" => $montoGravamen,
                "updated_by" => $idusuario,
                "updated_at" => $hoy2,
            );

            // 2. Manejar información adicional de geolocalización
            $idAdicionalExistente = null;
            if ($latitud !== null && $longitud !== null) {
                // Verificar si ya existe información adicional para esta garantía
                $adicionalExistente = $database->selectColumns(
                    'cli_adicionales',
                    ['id'],
                    'entidad_tipo=? AND entidad_id=? AND estado=1',
                    ['garantia', $idGarantia]
                );

                $cli_adicionales_attempts = [
                    // Intento 1: Con todos los campos incluyendo auditoría para actualización
                    array(
                        "entidad_tipo" => "garantia",
                        "entidad_id" => $idGarantia,
                        "descripcion" => $descripcion,
                        "latitud" => $latitud,
                        "longitud" => $longitud,
                        "altitud" => $altitud,
                        "precision" => $precision_gps,
                        "direccion_texto" => $direccion,
                        "estado" => 1,
                        "updated_by" => $idusuario,
                        "updated_at" => $hoy2,
                    ),
                    // Intento 2: Sin campos de auditoría
                    array(
                        "entidad_tipo" => "garantia",
                        "entidad_id" => $idGarantia,
                        "descripcion" => $descripcion,
                        "latitud" => $latitud,
                        "longitud" => $longitud,
                        "altitud" => $altitud,
                        "precision" => $precision_gps,
                        "direccion_texto" => $direccion,
                        "estado" => 1,
                    ),
                    // Intento 3: Solo campos esenciales
                    array(
                        "entidad_tipo" => "garantia",
                        "entidad_id" => $idGarantia,
                        "latitud" => $latitud,
                        "longitud" => $longitud,
                        "estado" => 1,
                    ),
                ];

                if (!empty($adicionalExistente)) {
                    // Actualizar el registro existente
                    $idAdicionalExistente = $adicionalExistente[0]['id'];

                    foreach ($cli_adicionales_attempts as $cli_adicionales) {
                        try {
                            $database->update('cli_adicionales', $cli_adicionales, 'id=?', [$idAdicionalExistente]);
                            break; // Salir si fue exitoso
                        } catch (Exception $e) {
                            // Continuar con el siguiente intento
                            error_log("Error actualizando cli_adicionales: " . $e->getMessage());
                        }
                    }
                } else {
                    // Crear nuevo registro
                    foreach ($cli_adicionales_attempts as $index => $cli_adicionales) {
                        // Para nuevos registros, usar created_by en lugar de updated_by
                        if (isset($cli_adicionales['updated_by'])) {
                            $cli_adicionales['created_by'] = $cli_adicionales['updated_by'];
                            $cli_adicionales['created_at'] = $cli_adicionales['updated_at'];
                            unset($cli_adicionales['updated_by']);
                            unset($cli_adicionales['updated_at']);
                        }

                        try {
                            $idAdicionalExistente = $database->insert('cli_adicionales', $cli_adicionales);
                            if ($idAdicionalExistente) {
                                break; // Salir si fue exitoso
                            }
                        } catch (Exception $e) {
                            if ($index === count($cli_adicionales_attempts) - 1) {
                                error_log("Error insertando cli_adicionales (último intento): " . $e->getMessage());
                            }
                        }
                    }
                }
            } else {
                // Si no hay coordenadas, marcar como eliminado el registro adicional si existe
                $deleteAttempts = [
                    // Intento 1: Con campos de auditoría
                    ['estado' => 0, 'deleted_by' => $idusuario, 'deleted_at' => $hoy2],
                    // Intento 2: Solo cambiar estado
                    ['estado' => 0],
                ];

                foreach ($deleteAttempts as $deleteData) {
                    try {
                        $database->update(
                            'cli_adicionales',
                            $deleteData,
                            'entidad_tipo=? AND entidad_id=? AND estado=1',
                            ['garantia', $idGarantia]
                        );
                        break; // Salir si fue exitoso
                    } catch (Exception $e) {
                        error_log("Error eliminando lógicamente cli_adicionales: " . $e->getMessage());
                    }
                }
            }

            // 3. Procesar archivos si existen
            if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                $folderInstitucion = (new Agencia($idagencia))->institucion?->getFolderInstitucion();

                if ($folderInstitucion === null) {
                    $showmensaje = true;
                    throw new Exception("No se pudo obtener la carpeta de la institución.");
                }

                $rutaSave = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/" . "garantias/" . $idCliente;
                $rutaEnServidor = "../../../" . $rutaSave;

                foreach ($_FILES['files']['name'] as $key => $name) {
                    if (!empty($name)) {
                        // Comprobar si existe la ruta, si no, se crea
                        if (!is_dir($rutaEnServidor)) {
                            mkdir($rutaEnServidor, 0777, true);
                        }

                        $tmp_name = $_FILES['files']['tmp_name'][$key];
                        $error = $_FILES['files']['error'][$key];
                        $size = $_FILES['files']['size'][$key];

                        // Validar errores de carga
                        if ($error !== UPLOAD_ERR_OK) {
                            $showmensaje = true;
                            throw new Exception("Error en la carga del archivo: Código de error " . $error);
                        }

                        // Validar tamaño (5MB máximo)
                        if ($size > 5 * 1024 * 1024) {
                            $showmensaje = true;
                            throw new Exception("El archivo es muy grande. Tamaño máximo permitido: 5MB");
                        }

                        // Validar extensión
                        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (!in_array($extension, $extensionesPermitidas)) {
                            $showmensaje = true;
                            throw new Exception("Tipo de archivo no permitido. Solo se permiten: JPG, PNG, PDF");
                        }

                        // Generar un nombre único para el archivo
                        $nombreImagen = $idCliente . '_' . $idGarantia . '_' . date('Ymdhis');
                        $nuevo_nombre = $nombreImagen . '.' . $extension;
                        $ruta_destino = $rutaEnServidor . '/' . $nuevo_nombre;

                        // Mover el archivo a la carpeta de destino
                        if (move_uploaded_file($tmp_name, $ruta_destino)) {
                            $rutaArchivoGuardado = $rutaSave . '/' . $nuevo_nombre;
                            $cli_garantia['archivo'] = $rutaArchivoGuardado;

                            // Insertar en cli_adicional_archivos si existe información adicional
                            if ($idAdicionalExistente) {
                                // Eliminar archivos anteriores de esta garantía
                                $deleteArchivosAttempts = [
                                    // Intento 1: Con campos de auditoría
                                    ['deleted_by' => $idusuario, 'deleted_at' => $hoy2],
                                    // Intento 2: Eliminación física (si no hay soft delete)
                                    // Se podría implementar eliminación física si es necesario
                                ];

                                foreach ($deleteArchivosAttempts as $deleteArchivosData) {
                                    try {
                                        $database->update(
                                            'cli_adicional_archivos',
                                            $deleteArchivosData,
                                            'id_adicional=? AND deleted_at IS NULL',
                                            [$idAdicionalExistente]
                                        );
                                        break; // Salir si fue exitoso
                                    } catch (Exception $e) {
                                        error_log("Error eliminando archivos anteriores: " . $e->getMessage());
                                    }
                                }

                                // Insertar nuevo archivo
                                $cli_adicional_archivos_attempts = [
                                    // Intento 1: Con campos de auditoría
                                    array(
                                        "id_adicional" => $idAdicionalExistente,
                                        "path_file" => $rutaArchivoGuardado,
                                        "created_by" => $idusuario,
                                        "created_at" => $hoy2,
                                    ),
                                    // Intento 2: Solo campos básicos
                                    array(
                                        "id_adicional" => $idAdicionalExistente,
                                        "path_file" => $rutaArchivoGuardado,
                                    ),
                                ];

                                foreach ($cli_adicional_archivos_attempts as $cli_adicional_archivos) {
                                    try {
                                        $database->insert('cli_adicional_archivos', $cli_adicional_archivos);
                                        break; // Salir si fue exitoso
                                    } catch (Exception $e) {
                                        error_log("Error insertando cli_adicional_archivos: " . $e->getMessage());
                                    }
                                }
                            }

                            break; // Solo procesar el primer archivo válido
                        } else {
                            $showmensaje = true;
                            throw new Exception("Error al mover el archivo al directorio de destino: " . $name);
                        }
                    }
                }
            }

            // Actualizar cli_garantia
            $database->update('cli_garantia', $cli_garantia, 'idGarantia=?', [$idGarantia]);

            $database->commit();
            $mensaje = "Garantía actualizada correctamente";
            $status = 1;
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    case 'delete_file_garantia':
        list($idGarantia) = $_POST['archivo'];

        $validar = validacionescampos([
            [$idGarantia, "", 'Seleccione una garantía', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $showmensaje = false;
        try {
            $database->openConnection();

            $comprobacion = $database->selectColumns('cli_garantia', ['archivo'], 'idGarantia=? AND estado=1', [$idGarantia]);
            if (empty($comprobacion)) {
                $showmensaje = true;
                throw new Exception("La garantía seleccionada no existe o ya ha sido eliminada.");
            }

            $fileProcessor = new FileProcessor(__DIR__ . '/../../../');

            $relativePath = $comprobacion[0]['archivo'];
            $database->beginTransaction();

            $res = $database->update('cli_garantia', ['archivo' => ''], 'idGarantia=?', [$idGarantia]);

            if ($fileProcessor->fileExists($relativePath)) {
                $fileProcessor->deleteFile($relativePath);
            } else {
                $showmensaje = true;
                throw new Exception("El archivo no existe en el servidor.");
            }



            $database->commit();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    case 'create_garantia_fiador':
        // ['codigoFiador'],['selecDocFiador'],[],'create_garantia_fiador','0',['<?= $idCliente']

        list($codigoFiador) = $_POST['inputs'];
        list($tipoDoc) = $_POST['selects'];
        list($idCliente) = $_POST['archivo'];

        $validar = validacionescampos([
            [$codigoFiador, "", 'Seleccione un fiador', 1],
            [$tipoDoc, "", 'Seleccione un tipo', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $showmensaje = false;
        try {
            $database->openConnection();

            $comprobacion = $database->selectColumns('cli_garantia', ['idGarantia'], 'idCliente=? AND idTipoGa=1 AND descripcionGarantia=? AND estado=1', [$idCliente, $codigoFiador]);
            if (!empty($comprobacion)) {
                $showmensaje = true;
                throw new Exception("El fiador seleccionado ya se encuentra registrado para este cliente.");
            }

            $database->beginTransaction();

            $cli_garantia = array(
                "idCliente" => $idCliente,
                "idTipoGa" => 1,
                "idTipoDoc" => $tipoDoc,
                "archivo" => "r",
                "descripcionGarantia" => $codigoFiador,
                // "direccion" => $direccion,
                // "depa" => $departamento,
                // "muni" => $selectMunicipio,
                // "valorComercial" => $valorComercial,
                // "montoAvaluo" => $montoAvaluo,
                // "montoGravamen" => $montoGravamen,
                "fechaCreacion" => $hoy,
                "estado" => 1,
                "created_by" => $idusuario,
                "created_at" => $hoy2,
            );

            $res = $database->insert('cli_garantia', $cli_garantia);

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;
    case 'update_garantia_fiador':
        // ['codigoFiador'],['selecDocFiador'],[],'create_garantia_fiador','0',['<?= $idGarantia']

        list($codigoFiador) = $_POST['inputs'];
        list($tipoDoc) = $_POST['selects'];
        list($idGarantia) = $_POST['archivo'];

        $validar = validacionescampos([
            [$codigoFiador, "", 'Seleccione un fiador', 1],
            [$tipoDoc, "", 'Seleccione un tipo', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $showmensaje = false;
        try {
            $database->openConnection();

            $comprobacion = $database->selectColumns('cli_garantia', ['idCliente'], 'idGarantia=? AND estado=1', [$idGarantia]);
            if (empty($comprobacion)) {
                $showmensaje = true;
                throw new Exception("No se encontró el fiador.");
            }

            $getGarantia = $database->selectColumns('cli_garantia', ['idGarantia'], 'idCliente=? AND idTipoGa=1 AND descripcionGarantia=? AND estado=1 AND idGarantia!=?', [$comprobacion[0]['idCliente'], $codigoFiador, $idGarantia]);
            if (!empty($getGarantia)) {
                $showmensaje = true;
                throw new Exception("El fiador seleccionado ya se encuentra registrado para este cliente.");
            }

            $database->beginTransaction();

            $cli_garantia = array(
                // "idCliente" => $idCliente,
                "idTipoGa" => 1,
                "idTipoDoc" => $tipoDoc,
                // "archivo" => "r",
                "descripcionGarantia" => $codigoFiador,
                // "direccion" => $direccion,
                // "depa" => $departamento,
                // "muni" => $selectMunicipio,
                // "valorComercial" => $valorComercial,
                // "montoAvaluo" => $montoAvaluo,
                // "montoGravamen" => $montoGravamen,
                // "fechaCreacion" => $hoy,
                "estado" => 1,
                "updated_by" => $idusuario,
                "updated_at" => $hoy2,
            );

            $res = $database->update('cli_garantia', $cli_garantia, 'idGarantia=?', [$idGarantia]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    case 'guardar_cuentas_garantia':

        list($cuentas, $idCliente) = $_POST['archivo'];

        Log::info("Guardar cuentas de garantía para el cliente: $idCliente", ['cuentas' => $cuentas]);
        // Log::info("Garantias anteriores: $idCliente", ['cuentas' => $idsGarantiasAnteriores]);

        $showmensaje = false;
        try {
            $database->openConnection();

            $hayCambios = false;

            foreach ($cuentas as $cuenta) {
                $idGarantia = $cuenta[2];
                $codigoCuenta = $cuenta[1];
                $tipoCuenta = $cuenta[0];

                $condicion = ($idGarantia == 'NULL' || $idGarantia == '') ? '' : ' AND idGarantia!=' . $idGarantia;
                $comprobacion = $database->selectColumns('cli_garantia', ['idGarantia'], 'idCliente=? AND idTipoDoc=? AND descripcionGarantia=? AND estado=1 ' . $condicion, [$idCliente, $tipoCuenta, $codigoCuenta]);
                if (!empty($comprobacion)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta '$codigoCuenta' ya se encuentra registrada para este cliente.");
                }

                if ($idGarantia == 'NULL' || $idGarantia == '') {
                    $cli_garantia = array(
                        "idCliente" => $idCliente,
                        "idTipoGa" => 3,
                        "idTipoDoc" => $tipoCuenta,
                        // "archivo" => "r",
                        "descripcionGarantia" => $codigoCuenta,
                        "fechaCreacion" => $hoy,
                        "estado" => 1,
                        "created_by" => $idusuario,
                        "created_at" => $hoy2,
                    );

                    $res = $database->insert('cli_garantia', $cli_garantia);
                    $hayCambios = true;
                }
            }

            if (!$hayCambios) {
                $showmensaje = true;
                throw new Exception("No se agregaron nuevas cuentas de garantía.");
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);

        break;

    case 'delete_garantia':
        list($idGarantia) = $_POST['archivo'];

        $showmensaje = false;
        try {
            $database->openConnection();

            $getGarantia = $database->selectColumns('cli_garantia', ['idGarantia'], 'idGarantia=? AND estado=1', [$idGarantia]);
            if (empty($getGarantia)) {
                $showmensaje = true;
                throw new Exception("La garantía no existe o ya ha sido eliminada.");
            }

            $comprobacion1 = $database->getAllResults("SELECT crem.Cestado,clig.descripcionGarantia FROM tb_garantias_creditos gar 
                            INNER JOIN cli_garantia clig ON clig.idGarantia=gar.id_garantia
                            INNER JOIN cremcre_meta crem ON crem.CCODCTA=gar.id_cremcre_meta 
                            WHERE gar.id_garantia=?", [$idGarantia]);

            if (!empty($comprobacion1) && $comprobacion1[0]['Cestado'] == 'F') {
                $showmensaje = true;
                throw new Exception("Ésta garantía está asociada a un credito todavia vigente y no puede ser eliminada.");
            }

            $database->beginTransaction();

            $cli_garantia = array(
                "estado" => 0,
                "deleted_by" => $idusuario,
                "deleted_at" => $hoy2,
            );

            $res = $database->update('cli_garantia', $cli_garantia, 'idGarantia=?', [$idGarantia]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    case 'actMasPlanPagos':
        $matrizJSON = $_POST['matriz'];
        $codCu = $_POST['extra'];
        $matriz = json_decode($matrizJSON, true);
        // $res;
        // $res1;
        $showmensaje = false;
        try {
            $database->openConnection();

            $Cre_ppg = $database->selectColumns('Cre_ppg', ['*'], 'ccodcta=?', [$codCu]);

            if (empty($Cre_ppg)) {
                $showmensaje = true;
                throw new Exception("No se encontró el plan de pagos para la cuenta especificada.");
            }

            $database->beginTransaction();

            /**
             * GUARDAR EN BITACORA ANTES DE ELIMINAR
             */

            foreach ($Cre_ppg as $registro) {
                $bitacoraRegistro = $registro;
                $bitacoraRegistro['id_real'] = $registro['Id_ppg'];
                $bitacoraRegistro['delete_by'] = $idusuario;
                $bitacoraRegistro['delete_at'] = date('Y-m-d H:i:s');
                unset($bitacoraRegistro['Id_ppg']); // Eliminar el Id_ppg original para evitar conflictos de clave primaria

                $database->insert('bitacora_Cre_ppg', $bitacoraRegistro);
            }

            // Eliminar registros existentes en Cre_ppg para la cuenta especificada
            $database->delete('Cre_ppg', 'ccodcta=?', [$codCu]);

            // Insertar nuevos registros desde la matriz proporcionada
            foreach ($matriz as $fila) {
                $nuevoRegistro = array(
                    'ccodcta' => $codCu,
                    'dfecven' => $fila[1],
                    'cestado' => 'X',
                    'cnrocuo' => $fila[2],
                    'ncappag' => $fila[3],
                    'nintpag' => $fila[4],
                    'OtrosPagosPag' => $fila[5],
                    'ncapita' => $fila[3],
                    'nintere' => $fila[4],
                    'SaldoCapital' => $fila[6],
                    'dfecmod' => $hoy,
                    'OtrosPagos' => $fila[5],
                );
                $database->insert('Cre_ppg', $nuevoRegistro);
            }

            $database->executeQuery('CALL update_ppg_account(?);', [$codCu]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        echo json_encode([$mensaje, $status]);
        break;

    //Funcion para eliminar la fila de plan de pagos
    case 'deleteFilaPlanPagos':

        $id = $_POST['ideliminar'];

        // Log::info("Eliminar fila de plan de pagos con ID: $id");

        $showmensaje = false;
        try {
            $database->openConnection();

            $Cre_ppg = $database->selectColumns('Cre_ppg', ['*'], 'Id_ppg=?', [$id]);

            if (empty($Cre_ppg)) {
                $showmensaje = true;
                throw new Exception("No se encontró el registro.");
            }

            $database->beginTransaction();

            $bitacoraRegistro = $Cre_ppg[0];
            $bitacoraRegistro['id_real'] = $Cre_ppg[0]['Id_ppg'];
            $bitacoraRegistro['delete_by'] = $idusuario;
            $bitacoraRegistro['delete_at'] = date('Y-m-d H:i:s');
            unset($bitacoraRegistro['Id_ppg']); // Eliminar el Id_ppg original para evitar conflictos de clave primaria

            $database->insert('bitacora_Cre_ppg', $bitacoraRegistro);

            $database->delete('Cre_ppg', 'Id_ppg=?', [$id]);

            $database->commit();

            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;

    case 'PlanPagos':
        $codCu = $_POST['extra'];

        $slq = mysqli_query($conexion, "SELECT EXISTS(SELECT a.estado FROM tb_autorizacion a
        INNER JOIN $db_name_general.tb_rol r ON r.id = a.id_rol
        INNER JOIN $db_name_general.tb_restringido rs ON rs.id = a.id_restringido
        WHERE r.siglas = 'ADM' AND a.id_restringido = 1 AND a.estado = 1) AS rst");

        $rst = $slq->fetch_assoc()['rst'];

        ob_start();
        $consulta = mysqli_query($conexion, "SELECT credi.NCapDes, pagos.Id_ppg AS id, pagos.dfecven AS fecha, pagos.Cestado AS estado, pagos.cnrocuo AS cuota, pagos.ncapita, pagos.nintere, pagos.OtrosPagos, pagos.SaldoCapital
            FROM  Cre_ppg AS pagos
            INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA
            WHERE credi.Cestado = 'F'  AND credi.ccodcta = '$codCu'");
        $con = 0;
        $aux = 0;
        $ban = true;
        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $con++;
?>
            <tr>
                <?php if ($ban) {
                    $aux = $row['NCapDes'];
                    $ban = false;
                } ?>
                <?php
                $aux = bcdiv(($aux - $row['ncapita']), '1', 2);
                // $auxEstado = ($esCou == "X") ? echo "<i class="fa-solid fa-money-bill" style="color: #c01111;"></i>": echo "<i class='fa-duotone fa-money-bill' style='--fa-primary-color: #0dab2c; --fa-secondary-color: #30d952;'></i>";

                $auxEstado = ($row['estado'] == "X") ? '<i class="fa-solid fa-money-bill" style="color: #c01111;"></i>' : '<i class="fa-solid fa-money-bill" style="color: #178109;"></i>';

                // $aux=$aux-$row['ncapita']
                ?>
                <td id="<?= $con . 'idCon' ?>"><?= $con ?></td> <!-- No -->
                <td id="idDes<?= $con; ?>" hidden> <?= $row['NCapDes'] ?></td> <!-- Capital Desembolsado -->
                <td id="<?= $con . 'idData' ?>" name="idPP[]" hidden><?= $row['id'] ?></td> <!-- ID -->
                <td><input id="<?= $con . 'fechaP' ?>" type="date" name="fecha[]" class="form-control" value="<?= $row['fecha'] ?>" onblur="validaF()"></td> <!-- Fecha -->
                <td><?= $auxEstado ?></td> <!-- Estado -->
                <td name="noCuo[]"><?= $row['cuota'] ?></td> <!-- No Cuota -->
                <td><input min="0" step="0.01" id="<?= $con . 'cap' ?>" name="capita[]" onkeyup="calPlanDePago()" type="number" class="form-control" value="<?= $row['ncapita'] ?>"></td> <!-- Capital -->
                <td><input min="0" step="0.01" id="<?= $con . 'inte' ?>" name="interes[]" onkeyup="calPlanDePago()" onchange="validaInteres(['<?= $usu ?>','<?= $codCu ?>', '<?= $row['cuota'] ?>', '<?= $con . 'inte' ?>','<?= $row['nintere'] ?>'])" type="number" class="form-control" value="<?= $row['nintere'] ?>" min="0" <?= ($rst == 1) ? "" : "disabled" ?>></td> <!-- Interes -->
                <td><input min="0" step="0.01" id="<?= $con . 'otros' ?>" name="otrosP[]" onkeyup="calPlanDePago()" type="number" class="form-control" value="<?= $row['OtrosPagos'] ?>" min="0"></td> <!-- Otros -->
                <td id="<?= $con . 'salCap' ?>" name="saldoCap[]"> <?= $aux ?> </td> <!-- Saldo Capital -->
                <td id="<?= $con . 'total' ?>"><?= ($row['ncapita'] + $row['nintere'] + $row['OtrosPagos']) ?></td> <!-- Total -->
            </tr>

        <?php }
        $output = ob_get_clean();
        echo $output;
        break;


    case 'couFech':
        $codCu = $_POST['extra'];

        //Obtener Codigo de cuenta de uno de los clientes
        $consulta = mysqli_query($conexion, "SELECT credi.CCODCTA AS codCu, gruCli.Codigo_grupo AS grup
              FROM tb_cliente_tb_grupo AS gruCli
              INNER JOIN tb_cliente AS cli ON gruCli.cliente_id = cli.idcod_cliente
              INNER JOIN cremcre_meta AS credi ON cli.idcod_cliente = credi.CodCli
              WHERE credi.cestado = 'F' AND gruCli.Codigo_grupo = $codCu[0] AND credi.NCiclo = $codCu[1] AND gruCli.estado = 1 AND credi.TipoEnti = 'GRUP' GROUP BY grup");

        $dato = mysqli_fetch_assoc($consulta);
        $codCu = $dato['codCu'];

        ob_start();
        $consulta = mysqli_query($conexion, "SELECT pagos.dfecven AS fecha, pagos.cnrocuo AS cuota
                                                        FROM  Cre_ppg AS pagos
                                                        INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA
                                                        WHERE credi.Cestado = 'F'  AND credi.ccodcta = '$codCu'");
        $con = 0;
        while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $con++;
        ?>

            <tr>
                <td id="<?= $con . 'conRow' ?>" hidden>kill</td> <!-- ID -->
                <td name="noCuo[]" id="<?= $row['cuota'] . 'idCon' ?>"> <?= $row['cuota'] ?> </td> <!-- No Cuota -->
                <td><input id="<?= $con . 'fechaP' ?>" type="date" name="fecha[]" class="form-control" value="<?= $row['fecha'] ?>" onchange="validaF()"></td> <!-- Fecha -->
            </tr>

            <?php
        }
        $output = ob_get_clean();
        echo $output;
        break;

    //Plan de pago de gurpos
    case 'planPagoGru':

        function planPago($conexion, $codCu)
        {
            $consulta1 = mysqli_query($conexion, "SELECT pagos.Id_ppg AS id, credi.NCapDes, pagos.Cestado AS estado, pagos.ncapita, pagos.nintere, pagos.OtrosPagos, pagos.SaldoCapital
            FROM  Cre_ppg AS pagos
            INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA
            WHERE credi.Cestado = 'F'  AND credi.ccodcta = '$codCu'");
            return $consulta1;
        }

        $codGru = $_POST['extra'];

        //Obtener Todos los numeros de cuenta
        $consulta = mysqli_query($conexion, "SELECT credi.CCODCTA AS codCu, cli.short_name AS nombre, credi.NCapDes AS capDes
                        FROM tb_cliente_tb_grupo AS gruCli
                        INNER JOIN tb_cliente AS cli ON gruCli.cliente_id = cli.idcod_cliente
                        INNER JOIN cremcre_meta AS credi ON cli.idcod_cliente = credi.CodCli
                        WHERE credi.cestado = 'F' AND gruCli.Codigo_grupo = $codGru[0] AND credi.NCiclo = $codGru[1] AND gruCli.estado = 1 AND credi.TipoEnti = 'GRUP';");

        $error = mysqli_error($conexion);
        $con = 0;

        ob_start();

        while ($rowData = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) { //Extraer cada nnumero de cuenta de forma individual

            $codCu = $rowData['codCu'];
            $consulta1 = planPago($conexion, $codCu);

            if ($con == 0) {
            ?>
                <div class="carousel-item active">

                    <!-- INI TABAL -->

                    <!-- INICIO DE LA TABLA -->
                    <div class="container table-responsive">
                        <table class="table" id="<?= $rowData['codCu'] ?>" name="tbCodCu[]">
                            <label><b>Cuenta:</b> </label> <label id="<?= 'codCu' . $rowData['codCu'] ?>"> <?= $rowData['codCu'] ?>
                            </label><br>
                            <label><b>Cliente:</b> <?= $rowData['nombre'] ?> <b>Capital desembolsado: Q </b></label> <label id="<?= 'capDes' . $rowData['codCu'] ?>"> <?= $rowData['capDes'] ?></label>
                            <thead class="table-dark">
                                <tr>
                                    <th class="col-1">Estado</th>
                                    <th class="col-2">Capital</th>
                                    <th class="col-2">Interes</th>
                                    <th class="col-2">Otros pagos</th>
                                    <th class="col-2">S. Capital</th>
                                    <th class="col-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- INI -->
                                <?php
                                $aux = 0;
                                $flag = true;
                                $con1 = 1;
                                while ($row = mysqli_fetch_array($consulta1, MYSQLI_ASSOC)) {
                                    if ($flag) {
                                        $aux = $rowData['capDes'];
                                        $flag = false;
                                    }
                                    $aux = bcdiv(($aux - $row['ncapita']), '1', 2);
                                    $auxEstado = ($row['estado'] == "X") ? '<i class="fa-solid fa-money-bill" style="color: #c01111;"></i>' : '<i class="fa-solid fa-money-bill" style="color: #178109;"></i>';

                                ?>
                                    <tr>
                                        <td id="<?= $con1 . 'idData' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'idPP[]' ?>" hidden><?= $row['id'] ?></td> <!-- ID -->
                                        <td><?= $auxEstado ?></td> <!-- Estado -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'cap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'capita[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['ncapita'] ?>"></td> <!-- Capital -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'inte' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'interes[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['nintere'] ?>" min="0"></td> <!-- Interes -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'otros' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'otrosP[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['OtrosPagos'] ?>" min="0"></td> <!-- Otros -->
                                        <td id="<?= $con1 . 'salCap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'saldoCap[]' ?>">
                                            <?= $aux ?> </td> <!-- Saldo Capital -->
                                        <td id="<?= $con1 . 'total' . $rowData['codCu'] ?>">
                                            <?= ($row['ncapita'] + $row['nintere'] + $row['OtrosPagos']) ?></td> <!-- Total -->
                                    </tr>
                                <?php
                                    $con1++;
                                }
                                ?>
                                <!-- FIN -->
                            </tbody>

                        </table>
                    </div>
                    <!-- FIN DE LA TABLA -->

                    <!-- FIN TABLA -->

                </div>
            <?php
            } else {
            ?>
                <div class="carousel-item">

                    <!-- INI TABAL -->

                    <!-- INICIO DE LA TABLA -->
                    <div class="container table-responsive">

                        <table class="table" id="<?= $rowData['codCu'] ?>" name="tbCodCu[]">
                            <label><b>Cuenta:</b> </label> <label id="<?= 'codCu' . $rowData['codCu'] ?>"> <?= $rowData['codCu'] ?>
                            </label><br>
                            <label><b>Cliente:</b> <?= $rowData['nombre'] ?> <b>Capital desembolsado: Q </b></label> <label id="<?= 'capDes' . $rowData['codCu'] ?>"> <?= $rowData['capDes'] ?></label>
                            <thead class="table-dark">
                                <tr>
                                    <th class="col-1">Estado</th>
                                    <th class="col-2">Capital</th>
                                    <th class="col-2">Interes</th>
                                    <th class="col-2">Otros pagos</th>
                                    <th class="col-2">S. Capital</th>
                                    <th class="col-2">Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                <!-- INI -->
                                <?php
                                $aux = 0;
                                $flag = true;
                                $con1 = 1;
                                while ($row = mysqli_fetch_array($consulta1, MYSQLI_ASSOC)) {
                                    if ($flag) {
                                        $aux = $rowData['capDes'];
                                        $flag = false;
                                    }
                                    $aux = bcdiv(($aux - $row['ncapita']), '1', 2);
                                    $auxEstado = ($row['estado'] == "X") ? '<i class="fa-solid fa-money-bill" style="color: #c01111;"></i>' : '<i class="fa-solid fa-money-bill" style="color: #178109;"></i>';

                                ?>
                                    <tr>
                                        <td id="<?= $con1 . 'idData' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'idPP[]' ?>" hidden><?= $row['id'] ?></td> <!-- ID -->
                                        <td><?= $auxEstado ?></td> <!-- Estado -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'cap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'capita[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['ncapita'] ?>"></td> <!-- Capital -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'inte' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'interes[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['nintere'] ?>" min="0"></td> <!-- Interes -->
                                        <td><input min="0" step="0.01" id="<?= $con1 . 'otros' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'otrosP[]' ?>" onkeyup="calPlanDePago('<?= $rowData['codCu'] ?>')" type="number" class="form-control" value="<?= $row['OtrosPagos'] ?>" min="0"></td> <!-- Otros -->
                                        <td id="<?= $con1 . 'salCap' . $rowData['codCu'] ?>" name="<?= $rowData['codCu'] . 'saldoCap[]' ?>">
                                            <?= $aux ?> </td> <!-- Saldo Capital -->
                                        <td id="<?= $con1 . 'total' . $rowData['codCu'] ?>">
                                            <?= ($row['ncapita'] + $row['nintere'] + $row['OtrosPagos']) ?></td> <!-- Total -->
                                    </tr>
                                <?php
                                    $con1++;
                                }
                                ?>
                                <!-- FIN -->
                            </tbody>

                        </table>
                    </div>
                    <!-- FIN DE LA TABLA -->

                    <!-- FIN TABLA -->

                </div>
<?php
            }
            $con++;
        }
        $output = ob_get_clean();
        echo $output;
        break;

    //Actualizar plan de pagos de los grupos
    case 'gruPlanPagosAct':
        $vecGeneral = $_POST['vecGeneral'];
        $codCu = $_POST['extra'];

        $totalEle = (count($vecGeneral) - 1); //Cantidad de Matrices que ingresaron

        $cuoFech = $vecGeneral[count($vecGeneral) - 1]; //Selecciona la matriz que contiene el plan de pagos
        $numF = count($cuoFech); //Valida cuanta filaz  tiene la matriz de plan de pagos

        $aux = 0;

        for ($conTE = 0; $conTE < $totalEle; $conTE++) {
            $aux = count($vecGeneral[$conTE]);
            if ($aux != $numF) {
                echo json_encode(['El plan de pago de una cuenta no cumple con el número de cuotras ' . $aux, '1']);
                return; //
            }
        }

        $res; // Encargada de capturar el resultado de la consulta
        $res1; // Encargada de camturar el resultado de la consutla

        try {
            for ($conTE = 0; $conTE < $totalEle; $conTE++) {
                $matriz = $vecGeneral[$conTE]; //obtener la matriz a trabajar
                if (isset($codCu[$conTE])) {
                    $res1 = $conexion->query("DELETE FROM Cre_ppg WHERE ccodcta = " . $codCu[$conTE]);
                    $aux = mysqli_error($conexion);
                    if ($aux) {
                        echo json_encode(['Error: ' . $aux, '0']);
                        $conexion->rollback();
                        return;
                    }
                }

                //Blokc de codigo, para crear un array de los datos a insertar
                for ($fil = 0; $fil < $numF; $fil++) {
                    $res = $conexion->query("INSERT INTO Cre_ppg (ccodcta, dfecven, cestado, cnrocuo, ncappag, nintpag, OtrosPagosPag, ncapita, nintere, SaldoCapital,dfecmod) VALUES ('{$codCu[$conTE]}', '{$cuoFech[$fil][1]}', 'X', {$cuoFech[$fil][0]}, {$matriz[$fil][1]}, {$matriz[$fil][2]}, {$matriz[$fil][3]}, {$matriz[$fil][1]}, {$matriz[$fil][2]}, {$matriz[$fil][4]},'$hoy')");
                    $aux = mysqli_error($conexion);
                    echo $aux;

                    if ($aux) {
                        echo 0;
                        $conexion->rollback();
                        return;
                    }

                    if (!$res) {
                        echo 0;
                        $conexion->rollback();
                        return;
                    }
                }
                $res4 = $conexion->query("CALL update_ppg_account('" . $codCu[$conTE] . "')");
                if (!$res4) {
                    echo json_encode(['Error al actualizar el plan de pago ', '0']);
                    $conexion->rollback();
                    return;
                }
            }

            if ($res && $res1) {
                //$conexion->commit();
                $conexion->rollback();
                echo json_encode(['Los datos se actualizaron con Exito', '1']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo 0;
        }
        break;

    //Funcion para eliminar la fila de plan de pagos de los grupos
    case 'deleteFilaPlanDePagosGrup':

        $id = $_POST['ideliminar'];
        $conTE = count($id);

        $res;
        $conexion->autocommit(false);
        for ($con = 0; $con < $conTE; $con++) {
            $res = $conexion->query("DELETE FROM Cre_ppg WHERE Id_ppg = " . $id[$con]);

            $aux = mysqli_error($conexion);
            echo $aux;

            if ($aux) {
                echo json_encode(['Error', '0']);
                $conexion->rollback();
                return;
            }

            if (!$res) {
                echo 'Error al ingresar';
                $conexion->rollback();
                return;
            }
        }

        if ($res) {
            $conexion->commit();
            echo json_encode(['Los datos, se eliminaron exitosamente. ', '1']);
            mysqli_close($conexion);
        }
        break;
    case 'consultar_reporte':
        $id_descripcion = $_POST["id_descripcion"];
        $validar = validar_campos_plus([
            [$id_descripcion, "", 'No se ha detectado un identificador de reporte válido', 1],
            [$id_descripcion, "0", 'Ingrese un número de reporte mayor a 0', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        try {
            //Validar si de casualidad ya se hizo el cierre otro usuario
            $stmt = $conexion->prepare("SELECT * FROM tb_documentos td WHERE td.id = ?");
            if (!$stmt) {
                throw new Exception("Error en la consulta 1: " . $conexion->error);
            }
            $stmt->bind_param("s", $id_descripcion); //El arroba omite el warning de php
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecucion de la consulta 1: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $numFilas2 = $result->num_rows;
            if ($numFilas2 == 0) {
                throw new Exception("No se encontro el reporte en el listado de documentos disponible");
            }
            $fila = $result->fetch_assoc();
            echo json_encode(["Reporte encontrado", '1', $fila['nombre']]);
        } catch (Exception $e) {
            //Captura el error
            $mensaje_error = $e->getMessage();
            echo json_encode([$mensaje_error, '0']);
        } finally {
            if ($stmt !== false) {
                $stmt->close();
            }
            $conexion->close();
        }
        break;
    case 'consultar_history':
        $ccodcta = $_POST['cuenta'];
        $consulta = mysqli_query($conexion, "SELECT cnrocuo,dfecven,dfecpag,
            IF((timestampdiff(DAY,dfecven,'$hoy'))<0, 0,(timestampdiff(DAY,dfecven,'$hoy'))) AS diasatraso, cestado,ncapita,nintere,
            cflag FROM Cre_ppg WHERE ccodcta='$ccodcta'");
        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $estado = ($fila['cestado'] == 'P') ? 'Pagada' : (($fila['cestado'] == 'X' && $fila['diasatraso'] > 0) ? 'Vencida' : 'Por pagar');
            $color = ($fila['cestado'] == 'P') ? 'success' : (($fila['cestado'] == 'X' && $fila['diasatraso'] > 0) ? 'danger' : 'primary');
            $status = '<span class="badge text-bg-' . $color . '">' . $estado . '</span>';

            $pago = ($fila["cflag"] == 1) ? 'Puntual' : (($fila["cflag"] == 0) ? 'Impuntual' : 'Pendiente');
            $color = ($fila["cflag"] == 1) ? 'success' : (($fila["cflag"] == 0) ? 'danger' : 'primary');
            $calificacion = '<span class="badge text-bg-' . $color . '">' . $pago . '</span>';

            $array_datos[$i] = array(
                "0" => $fila["cnrocuo"],
                "1" => $fila["dfecven"],
                "2" => ($fila["dfecpag"] == "0000-00-00 00:00:00") ? '-' : date("d-m-Y", strtotime($fila["dfecpag"])),
                "3" => ($fila["cestado"] == 'P') ? 
                    (($fila["dfecpag"] != "0000-00-00 00:00:00") ? 
                        max(0, (strtotime($fila["dfecpag"]) - strtotime($fila["dfecven"])) / (60 * 60 * 24)) : 
                        0) : 
                    $fila["diasatraso"],
                "4" => $status,
                "5" => round($fila["ncapita"] + $fila["nintere"], 2),
                "6" => $calificacion,
            );
            // $array_datos[$i] = array(
            //     "0" => $fila["cnrocuo"],
            //     "1" => $fila["dfecven"],
            //     "2" => ($fila["dfecpag"] == "0000-00-00 00:00:00") ? '-' : date("d-m-Y", strtotime($fila["dfecpag"])),
            //     "3" => $status,
            //     "4" => $fila["ncapita"],
            //     "5" => $fila["nintere"],
            //     "6" => $calificacion,
            // );
            $i++;
        }
        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($array_datos),
            "iTotalDisplayRecords" => count($array_datos),
            "aaData" => $array_datos
        );
        echo json_encode($results);
        mysqli_close($conexion);
        break;

    case 'act_interes':
        $data =  $_POST['datos'];
        // echo json_encode([$data[0], '1']);
        // return ;
        $sql  = "UPDATE Cre_ppg SET  nintpag = " . $data[0][5] . ", nintere = " . $data[0][5] . " WHERE ccodcta = '" . $data[0][1] . "' AND cnrocuo = " . $data[0][2] . "";
        $sql1 = "INSERT INTO tb_rpt_perdon (tipo, ccodcta, num_pago, efec_real, efec_perdonado, created_by, created_at) VALUES (1, '" . $data[0][1] . "', " . $data[0][2] . ", " . $data[0][4] . ", " . $data[0][5] . ", (SELECT id_usu FROM tb_usuario WHERE usu = '" . $data[0][0] . "'), '$hoy2');";

        $conexion->autocommit(false);
        $res = $conexion->query($sql);
        $res1 = $conexion->query($sql1);

        $aux = mysqli_error($conexion);
        echo $aux;

        if ($aux || !$res || !$res1) {
            echo json_encode(['Error', '0']);
            $conexion->rollback();
            return;
        }
        if ($res) {
            $conexion->commit();
            echo json_encode(['Interes modificado. ', '1']);
            mysqli_close($conexion);
        }
        break;
    case 'list_creditos_all':
        $query = "SELECT cm.CCODCTA AS ccodcta,cm.Cestado, cm.CodCli AS codcli,cl.no_identifica AS dpi, cl.short_name AS nombre,
                    cm.NCiclo AS ciclo, cm.MonSug AS monsug
                    FROM cremcre_meta cm
                    INNER JOIN  tb_cliente cl ON cm.CodCli = cl.idcod_cliente
                    WHERE cm.Cestado IN ('A','D','E','F') AND cm.TipoEnti = 'INDI' AND cl.estado=1;";
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->getAllResults($query);
            if (empty($result)) {
                $result = [];
            }
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $estados = array(
            "A" => "Solicitado",
            "D" => "Analizado",
            "E" => "Aprobado",
            "F" => "Vigente"
        );

        $array_datos = array();
        $total = 0;
        $i = 0;
        foreach ($result as $fila) {
            $array_datos[] = array(
                "0" => $i + 1,
                "1" => $fila["ccodcta"],
                // "2" => $fila["codcli"],
                "2" => $fila["dpi"],
                "3" => $fila["nombre"],
                "4" => $fila["ciclo"],
                "5" => $estados[$fila["Cestado"]],
                "6" => $fila["monsug"],
                // "7" => '<button type="button" class="btn btn-success btn-sm"  data-bs-dismiss="modal" onclick="printdiv2(`#cuadro`,`' . $fila["ccodcta"] . '`)">Aceptar</button> '
            );
            $i++;
        }
        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($array_datos),
            "iTotalDisplayRecords" => count($array_datos),
            "aaData" => $array_datos
        );
        echo json_encode($results);
        break;

    case 'dattemp':
        $concepto = $_POST['concepto'];
        $nocheque = $_POST['nocheque'];
        $_SESSION['concepto'] = $concepto;
        $_SESSION['nocheque'] = $nocheque;
        if ($_POST['flaggrup'] != 2) {
            $_SESSION['flaggrup'] = 1;
        } {
            $_SESSION['flaggrup'] = 2;
        }

        break;

    case 'cargmonchq':
        $total = $_POST['total'];
        $_SESSION['total'] = $total;
        break;
    case 'bdenominacion':
        $idMoneda = $_POST['id'];
        try {
            $database->openConnection(2);
            $datos = $database->selectColumns("denominaciones", ["id", "id_moneda", "monto", "tipo"], "id_moneda=?", [$idMoneda]);
            $datos2 = $database->selectColumns("tb_monedas", ["abr"], "id=?", [$idMoneda]);
            // Asegúrate de enviar la respuesta en formato JSON
            if ($datos) {
                echo json_encode([
                    'status' => 'success',
                    'data' => $datos,
                    'data2' => $datos2
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No se encontraron datos'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ]);
        } finally {
            $database->closeConnection();
        }
        break;
    case 'procesarMovimiento':
        $data = json_decode($_POST['datos'], true);
        if ($data === null) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error al recibir los datos, JSON mal formado'
            ]);
            exit;
        }

        $tipoMoneda = $data['tipoMoneda'];
        $tipoOperacion = $data['tipoOperacion'];
        $desglosarMonto = ($data['desglosarMonto'] === 'si') ? 1 : 2;
        $totalGeneral = $data['totalGeneral'];
        $denominaciones = $data['denominaciones'];
        $codMovimiento = 0;
        if ($desglosarMonto === 'si' && empty($denominaciones)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Debe ingresar al menos una denominación válida.'
            ]);
            exit;
        }


        try {
            $database->openConnection(1);
            $aperturacaja = $database->selectColumns("tb_caja_apertura_cierre", ["id"], "id_usuario = ? AND fecha_apertura = ? AND estado=?", [$idusuario, $hoy, 1]);
            if (!empty($aperturacaja)) {
                $datos = [
                    'id_caja' => $aperturacaja[0]['id'],
                    'total' => $totalGeneral,
                    'tipo' => $tipoOperacion,
                    'detalle' => $desglosarMonto,
                    'estado' => 1,
                    'created_at' => $hoy2,
                    'created_by' => $idusuario
                ];
                $codMovimiento = $database->insert('tb_movimientos_caja', $datos);

                foreach ($denominaciones as $denominacion) {
                    $idDenominacion = $denominacion['id'];
                    $monto = $denominacion['monto'];
                    $cantidad = $denominacion['cantidad'];
                    $datos = [
                        'id_movimiento' => $codMovimiento,
                        'id_denominacion' => $idDenominacion,
                        'cantidad' => $cantidad,
                    ];
                    $database->insert('detalle_movimiento', $datos);
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Movimiento procesado correctamente.',
                'cod' => $codMovimiento,
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error al procesar el movimiento: ' . $e->getMessage()
            ]);
        } finally {
            $database->closeConnection();
        }
        break;
    case 'buscarmovimientos':
        $flagperm = 0;
        if ($_POST['fecha'] && isset($_POST['agencia'])) {
            $fec = $_POST['fecha'];
            $ag = $_POST['agencia'];
            if (isset($_POST['flagperm'])) {
                $flagperm = $_POST['flagperm'];
            } else {
                $flagperm = 0;
            }
            try {
                $database->openConnection();
                $consul = "SELECT us.nombre, us.apellido, movca.id, movca.total, movca.tipo, movca.created_at, us.id_agencia, us.id_usu, movca.estado
                                                       FROM tb_usuario us
                                                       INNER JOIN tb_movimientos_caja movca ON us.id_usu = movca.created_by
                                                       WHERE DATE(movca.created_at) =:fec";
                if ($flagperm == 0) {
                    $consul .= " AND us.id_usu =:usuari";
                    $valores = [
                        'fec' => $fec,
                        'usuari' => $idusuario,
                    ];
                } else if ($flagperm == 1) {
                    $consul .= " AND us.id_agencia =:agen";
                    $valores = [
                        'fec' => $fec,
                        'agen' => $ofi,
                    ];
                } else if ($flagperm == 2) {
                    if ($ag != 0) {
                        $consul .= " AND us.id_agencia =:agen";
                        $valores = [
                            'fec' => $fec,
                            'agen' => $ag,
                        ];
                    } else {
                        $valores = [
                            'fec' => $fec,
                        ];
                    }
                }

                $agencias = $database->selectColumns("tb_agencia", ["id_agencia", "nom_agencia", "cod_agenc"]);
                $movimientos = $database->getAllResults($consul, $valores);

                // Convertir el resultado a un array JSON válido
                $movimientosArray = [];
                foreach ($movimientos as $movimiento) {
                    $movimientosArray[] = [
                        'nombre' => $movimiento['nombre'],
                        'apellido' => $movimiento['apellido'],
                        'id' => $movimiento['id'],
                        'total' => $movimiento['total'],
                        'tipo' => $movimiento['tipo'],
                        'created_at' => $movimiento['created_at'],
                        'id_agencia' => $movimiento['id_agencia'],
                        'estado' => $movimiento['estado'],
                    ];
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $movimientosArray
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los movimientos: ' . $e->getMessage()
                ]);
            } finally {
                $database->closeConnection();
            }
        } else {
            if (!$fech && !$agen) {
                echo json_encode(['status' => 'error', 'message' => 'Debe ingresar una fecha y una agencia.']);
            } elseif (!$fech) {
                echo json_encode(['status' => 'error', 'message' => 'Debe ingresar una fecha.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar una agencia.']);
            }
        }
        break;
    case 'rechazarMovimiento':
        if (isset($_POST['cod'])) {
            $cod = $_POST['cod'];

            try {
                $database->openConnection();
                $data = [
                    "estado" => 0,
                    "updated_at" => $hoy2,
                    "updated_by" => $idusuario
                ];
                $condition = "id = ?";
                $resultado = $database->update("tb_movimientos_caja", $data, $condition, [$cod]);
                if ($resultado > 0) {
                    echo json_encode([
                        'status' => 'success',
                        'data' => 'Proceso Rechazado',
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'success',
                        'data' => 'No se pudo procesar el rechazo',
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Ocurrió un error al obtener los movimientos: ' . $e->getMessage()
                ]);
            } finally {
                $database->closeConnection();
            }

            exit;
        }
        break;
    case 'aprobarMovimiento':
        $cod = $_POST['cod'];
        if (!$cod) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Código no válido'
            ]);
            break;
        }

        try {
            $database->openConnection();
            //Temp1 Busca el detalle de lo solicitado por el cajero
            $temp1 = $database->getAllResults("SELECT deno.id_moneda, deno.id, deno.monto, deno.tipo, deta.cantidad, deta.id AS iddeta
                                FROM " . $db_name_general . ".denominaciones deno
                                INNER JOIN detalle_movimiento deta ON deta.id_denominacion = deno.id
                                WHERE deta.id_movimiento =?", [$cod]);


            if (count($temp1) <= 0) {
                $temp3 = $database->getAllResults("SELECT total FROM tb_movimientos_caja WHERE id=?", [$cod]);
                echo json_encode([
                    'status' => 'error',
                    'data0' => 0, //Es cero cuando el cajero NO solicito un tipo de denominacion especifico
                    'data1' => $datos ?? [],
                    'data2' => $datos2 ?? [],
                    'data3' => $temp3[0]['total'],
                ]);
                break;
            }
            //TEMP2 busca los valores de las distintas denominaciones que se encuentren correspondiente al tipo de movimiento de la moneda usada de la solicitud
            $temp2 = $database->getAllResults("SELECT deno.id, deno.id_moneda, deno.monto, deno.tipo, mone.abr
                                FROM " . $db_name_general . ".denominaciones deno
                                INNER JOIN " . $db_name_general . ".tb_monedas mone ON mone.id = deno.id_moneda
                                WHERE deno.id_moneda =?", [$temp1[0]['id_moneda']]);

            $bovedasDisponibles = $database->selectColumns("bov_bovedas", ["id", "nombre"], "estado='1'");
            echo json_encode([
                'status' => 'success',
                'data0' => 1,
                'data1' => $temp1 ?? [],
                'data2' => $temp2 ?? [],
                'bovedas' => $bovedasDisponibles ?? [],
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ]);
            break;
        } finally {
            $database->closeConnection();
        }

        break;
    case 'validarmovimiento':
        if (isset($_POST['dat'])) {
            $data = json_decode($_POST['dat'], true);

            if (!empty($data)) {
                $cod = $data['cod'] ?? null;
                $flagperm = $data['flagperm'] ?? null;
                $totalGeneral = $data['totalGeneral'] ?? 0;
                $denominaciones = $data['denominaciones'] ?? [];
                $debitarBoveda = $data['debitarBoveda'] ?? 0;
                $bovedaSeleccionada = $data['bovedaId'] ?? 0;

                if ($totalGeneral <= 0) {
                    echo json_encode(['success' => false, 'message' => 'El monto total debe ser mayor a cero.']);
                    exit;
                }
                if (empty($denominaciones)) {
                    try {
                        $database->openConnection();
                        $data = [
                            "total" => $totalGeneral,
                            "estado" => 2,
                            "updated_at" => $hoy2,
                            "updated_by" => $idusuario
                        ];
                        $condition = "id = ?";
                        $resultado = $database->update("tb_movimientos_caja", $data, $condition, [$cod]);
                        if ($resultado > 0) {
                            echo json_encode(['success' => true, 'message' => 'Validación completada sin desglose de denominaciones.', 'data' => 0]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'No se realizo la aprobacion.', 'data' => 0]);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            'success' => 'false',
                            'message' => 'Error: ' . $e->getMessage()
                        ]);
                        break;
                    } finally {
                        $database->closeConnection();
                    }
                } else {
                    try {
                        $database->openConnection();
                        // Actualizar el registro del detalle del movimiento de CAJA
                        foreach ($denominaciones as $denominacion) {
                            if (isset($denominacion['id'], $denominacion['cantidad'], $denominacion['monto'], $denominacion['tipo'])) {
                                $idDenominacion = $denominacion['id'];
                                $montoDenominacion = $denominacion['monto'];
                                $cantidadDenominacion = $denominacion['cantidad'];
                                $tipo = $denominacion['tipo'];
                                $cod = $cod;

                                if ($idDenominacion == '0') {
                                    // Busca la denominación para el número de registro
                                    $nDenominacion = $database->getAllResults("SELECT id, id_moneda
                                                                                FROM " . $db_name_general . ".denominaciones
                                                                                WHERE monto = ? AND tipo = ?", [$montoDenominacion, $tipo]);

                                    if (count($nDenominacion) > 0) {
                                        $datos = [
                                            'id_movimiento' => $cod,
                                            'id_denominacion' => $nDenominacion[0]['id'],
                                            'cantidad' => $cantidadDenominacion,
                                        ];
                                        $resuldeta = $database->insert('detalle_movimiento', $datos);
                                        if ($resuldeta <= 0) {
                                            echo json_encode(['success' => false, 'message' => 'No se realizó la inserción de la denominación.', 'data' => $data]);
                                            exit;
                                        }
                                    } else {
                                        echo json_encode(['success' => false, 'message' => 'Denominación no encontrada.', 'data' => 0]);
                                        exit;
                                    }
                                } else {
                                    $data = [
                                        'cantidad' => $cantidadDenominacion,
                                    ];
                                    $condition = "id=?";
                                    $resuldeta = $database->update("detalle_movimiento", $data, $condition, [$idDenominacion]);
                                }
                            }
                        }

                        // Actualizar el registro del movimiento en CAJA
                        $data = [
                            "total" => $totalGeneral,
                            "estado" => 2,
                            "updated_at" => $hoy2,
                            "updated_by" => $idusuario
                        ];
                        $condition = "id = ?";
                        $resultado = $database->update("tb_movimientos_caja", $data, $condition, [$cod]);

                        if ($resultado <= 0) {
                            echo json_encode(['success' => false, 'message' => 'No se realizó la actualización del movimiento.', 'data' => $data]);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            'success' => 'false',
                            'message' => 'Error: ' . $e->getMessage()
                        ]);
                    } finally {
                        $database->closeConnection();
                    }

                    echo json_encode(['success' => true, 'message' => 'Validación completada con denominaciones.', 'data' => $data]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Datos enviados están vacíos o no son válidos.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No se recibió información.']);
        }
        break;
}


//************************************************************************************** AREA ROJA  XD */

function validar_campos($validaciones)
{
    for ($i = 0; $i < count($validaciones); $i++) {
        if ($validaciones[$i][0] == $validaciones[$i][1]) {
            return [$validaciones[$i][2], '0', true];
            $i = count($validaciones) + 1;
        }
    }
    return ["", '0', false];
}

function validar_campos_plus($validaciones)
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
        }
    }
    return ["", '0', false];
}

function gastoscredito($idc, $conexion)
{
    $consulta = mysqli_query($conexion, "SELECT cg.*, cm.CCODPRD, cm.MonSug, cm.CodCli,tipg.nombre_gasto,cm.NtipPerC tiperiodo,cm.noPeriodo,cl.short_name,tipg.id_nomenclatura,tipg.afecta_modulo,DFecDsbls fecdes  FROM cremcre_meta cm
    INNER JOIN cre_productos_gastos cg ON cm.CCODPRD=cg.id_producto
    INNER JOIN cre_tipogastos tipg ON tipg.id=cg.id_tipo_deGasto
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    WHERE cm.CCODCTA='$idc' AND tipo_deCobro=1 AND cg.estado=1");
    $datosgastos[] = [];
    $total = 0;
    $i = 0;
    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $datosgastos[$i] = $fila;
        $id = $fila['id'];
        $tipo = $fila['tipo_deMonto'];
        $nombregasto = $fila['nombre_gasto'];
        $monapro = $fila['MonSug'];
        $cant = $fila['monto'];
        $calculax = $fila['calculox'];
        $cuotas = $fila['noPeriodo'];
        $tiperiodo = $fila['tiperiodo'];
        $plazo = ($tiperiodo == '1M') ? $cuotas : (($tiperiodo == '15D' || $tiperiodo == '14D') ? ($cuotas / 2) : (($tiperiodo == '7D') ? ($cuotas / 4) : (($tiperiodo == '1D') ? ($cuotas / 28) : $cuotas)));
        $mongas = 0;
        if ($tipo == 1) {
            $mongas = ($calculax == 1) ? ($cant) : (($calculax == 2) ? ($cant * $plazo) : (($calculax == 3) ? ($cant * $plazo * $monapro) : ($cant * $monapro)));
        }
        if ($tipo == 2) {
            $mongas = ($calculax == 1) ? ($cant / 100 * $monapro) : (($calculax == 2) ? ($cant / 100 * $plazo) : (($calculax == 3) ? ($cant / 100 * $plazo * $monapro) : ($cant / 100 * $monapro)));
        }
        $datosgastos[$i]['mongas'] = round($mongas, 2);
        $i++;
    }
    if ($i == 0) {
        return null;
    }
    return $datosgastos;
}
function getcuentas($idc, $conexion)
{
    $consulta = mysqli_query($conexion, 'SELECT CCODCTA,NCapDes,DfecPago fecpago,NIntApro intapro,IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CTIPPAG="P" AND CESTADO!="X"),0) pagadokp,
        IFNULL((SELECT SUM(nintpag) FROM Cre_ppg WHERE ccodcta=cm.CCODCTA),0) intpen,
        IFNULL((SELECT MAX(dfecven) from Cre_ppg where cestado="P" AND ccodcta=cm.CCODCTA),"-") fecult
        FROM cremcre_meta cm WHERE CodCli IN (SELECT Codcli FROM cremcre_meta WHERE CCODCTA="' . $idc . '")
        AND CCODCTA!="' . $idc . '" AND Cestado="F" AND TipoEnti="INDI";');

    $datoscreditos[] = [];
    $total = 0;
    $i = 0;
    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
        $datoscreditos[$i] = $fila;
        $i++;
    }
    if ($i == 0) {
        return null;
    }
    return $datoscreditos;
}
function executequery($query, $params, $typparams, $conexion)
{
    $stmt = $conexion->prepare($query);
    $aux = mysqli_error($conexion);
    if ($aux) {
        return ['ERROR: ' . $aux, false];
    }
    $types = '';
    $bindParams = [];
    $bindParams[] = &$types;
    $i = 0;
    foreach ($params as &$param) {
        // $types .= 's';
        $types .= $typparams[$i];
        $bindParams[] = &$param;
        $i++;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    if (!$stmt->execute()) {
        return ["Error en la ejecución de la consulta: " . $stmt->error, false];
    }
    $data = [];
    $resultado = $stmt->get_result();
    $i = 0;
    while ($fila = $resultado->fetch_assoc()) {
        $data[$i] = $fila;
        $i++;
    }
    $stmt->close();
    return [$data, true];
}
?>