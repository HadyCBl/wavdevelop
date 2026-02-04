<?php

// funcion para recuperar datos de un grupo
function  grupos_data($xtra)
{
    include '../../../includes/BD_con/db_con.php';
    $data4 = mysqli_query($conexion, "SELECT id_grupos,NombreGrupo,Depa,muni,canton,direc,fecha_sys,direc FROM `tb_grupo` WHERE id_grupos = ".$xtra[0]. ""); // conexion a base de datos para imprimir en patalla desde modal
    $re = mysqli_fetch_array($data4, MYSQLI_NUM);     //la consulta(SELECT id_grupos,NombreGrupo,fecha_sys,direc FROM `tb_grupo` WHERE id_grupos = 2) es como se filtra en msql
    return $re;
}
function integ($xtra)
{
    include '../../../includes/BD_con/db_con.php';
    $data5 = mysqli_query($conexion, "SELECT count(*) FROM tb_cliente INNER JOIN tb_cliente_tb_grupo ON tb_cliente.idcod_cliente = tb_cliente_tb_grupo.cliente_id WHERE Codigo_grupo=" . $xtra . "");
    $total = mysqli_fetch_array($data5, MYSQLI_NUM);
    return $total[0];
}

// INTEGRANTES DE UN GRUOP
function cli_grp($xtra)
{
    include '../../../includes/BD_con/db_con.php';
    mysqli_set_charset($conexion, 'utf8');
    $i = 0;
    $consulta = mysqli_query($conexion, "SELECT idcod_cliente, short_name, no_identifica, date_birth, id_grupo 
    FROM tb_cliente INNER JOIN tb_cliente_tb_grupo ON tb_cliente.idcod_cliente = tb_cliente_tb_grupo.cliente_id WHERE Codigo_grupo = " . $xtra);
    while ($re = mysqli_fetch_array($consulta, MYSQLI_NUM)) {
        echo ' <tr>
        <td>' . $i . '</td>
        <td>' . $re[0] . '</td>
        <td>' . $re[1] . '</td>
        <td>' . $re[2] . '</td>
        <td>' . $re[3] . '</td>
        </tr>';
        $i++;
    }
}
