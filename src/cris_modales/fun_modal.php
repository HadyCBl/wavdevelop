<?php
//llamar funciones con ajax
if (isset($_POST['condi2'])) {
    $condi = $_POST['condi2'];
    switch ($condi) {    
        case 'gruposcredito':
            include '../../includes/BD_con/db_con.php';
            mysqli_set_charset($conexion, 'utf8');
            $status1 = $_POST["status1"];
            $status2 = $_POST["status2"];
            $query = "SELECT id_grupos,codigo_grupo,NombreGrupo,fecha_sys,direc,' ' as NCiclo,' ' as estado,' ' AS Cestado FROM `tb_grupo` WHERE estado=1";
            if ($status1 != "all") {
                $i = 0;
                $condistatus = "";
                while ($i < count($status2)) {
                    $condistatus .= " cre.Cestado='" . $status2[$i] . "'";
                    if ($i != array_key_last($status2)) {
                        $condistatus .= " OR";
                    }
                    $i++;
                }
                $query = "SELECT grup.id_grupos,grup.codigo_grupo,grup.NombreGrupo,grup.fecha_sys,grup.direc,cre.NCiclo,cre.Cestado,est.EstadoCredito estado FROM `tb_grupo` grup 
                        INNER JOIN cremcre_meta cre ON cre.CCodGrupo=grup.id_grupos
                        INNER JOIN $db_name_general.`tb_estadocredito` est on est.id_EstadoCredito=cre.Cestado
                        WHERE cre.TipoEnti='GRUP' AND grup.estado=1 AND (" . $condistatus . ") GROUP BY grup.id_grupos,cre.NCiclo";
            }
            $consulta = mysqli_query($conexion, $query);
            $array_datos = array();
            $i = 0;
            $contador = 1;
            while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                $array_datos[] = array(

                    "0" => $fila["codigo_grupo"],
                    "1" => $fila["NombreGrupo"],
                    "2" => $fila["direc"],
                    "3" => $fila["NCiclo"],
                    "4" => $fila["estado"],
                    "5" => '<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="printdiv2( `#cuadro`,[`' . $fila["id_grupos"] . '`,' . $fila["NCiclo"] . ',`' . $fila["Cestado"] . '`,`' . $fila["id_grupos"] . '`]);" >Aceptar</button>'
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

        case 'lincred':
            include '../../includes/BD_con/db_con.php';
            mysqli_set_charset($conexion, 'utf8');
            //$consulta = mysqli_query($conexion, "SELECT iD_Prod,ccodprdct, Plazo,MontoMAx, descriprod, TasaInteres, nnompro,Prc_aho FROM `productos` ORDER BY iD_Prod ASC");
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
                    "6" => '<button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick= "select_item(`#id_modal_hidden`,[' . $fila["id"] . ',`' . $fila["cod_producto"] . '`,`' . $fila["nompro"] . '`,`' . $fila["descriprod"] . '`,' . $fila["tasa_interes"] . ',' . $fila["monto_maximo"] . ',`' . $fila["fondesc"] . '`]); cerrar_modal(`#findcredlin`, `hide`, `#id_modal_hidden`);" >Aceptar</button>'
                );
                $i++;
            }
            $results = array(
                "sEcho" => 1, 
                "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
                "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
                "aaData" => $array_datos
            );
            mysqli_close($conexion);
            echo json_encode($results);
            break;
    }
}

