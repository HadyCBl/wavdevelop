<?php
/* ********************************************************** 
  AQUI SE VAN A AGREGAR TODAS LAS CONSULTAS DEL BURO 
  DESPUES SE PUEDEN CREAR VISTAS Y PROCEDIMIENTOS 
  Y TAMBIEN OPTIMIZAR LAS CONSULTAS CON SERVER ASIDE
 ********************************************************** */

/* +++++++++++++++++++++++++++++++++++++++ INFO DE LA INSTITUCION +++++++++++++++++++++++++++++++++++++++++++ */
$queryInsti = "SELECT ib.* FROM " . $db_name_general . ".info_coperativa ins 
  INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
  INNER JOIN " . $db_name_general . ".tb_infoinstituciones_buro ib ON ib.id_institucion=ins.id_cop 
  WHERE ag.id_agencia=? AND ib.id_buro=1";
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

/* +++++++++++++++ CONSULTA DE LOS CLIENTES QUE ESTAN CON CREDITOS EN EL RANGO DE FECHAS DADO +++++++++++++++ */
// $qryCli = "SELECT * FROM clientesCrediref WHERE ((CESTADO='F' AND DFecDsbls <= ?)
//   OR (CESTADO='G' AND fecha_operacion BETWEEN ? AND ?))
//   AND (primer_name IS NOT NULL AND primer_name != '')
//   AND (primer_last IS NOT NULL AND primer_last != '')";

$qryCli="SELECT 
    cli.idcod_cliente,
    cli.id_tipoCliente AS tipo,
    cli.primer_name,
    cli.segundo_name,
    cli.tercer_name,
    cli.primer_last,
    cli.segundo_last,
    cli.casada_last,
    cli.date_birth,
    cli.genero,
    cli.estado_civil,
    cli.type_doc,
    cli.no_identifica,
    cli.no_tributaria,
    cli.no_igss,
    cli.nacionalidad,
    m1.codigo muni_extiende,
    cli.tel_no1,
    cli.tel_no2,
    cli.Direccion,
    cm.CCODCTA,
    cm.Cestado AS CESTADO,
    cm.DFecDsbls,
    cm.fecha_operacion,
    COALESCE(m1.cod_crediref, '10001') AS municipio,
    COALESCE(m1.cod_crediref, 'X') AS codigo_postal
FROM tb_cliente cli
LEFT JOIN tb_municipios m1 
    ON m1.id = cli.id_muni_extiende
INNER JOIN cremcre_meta cm 
    ON cm.CodCli = cli.idcod_cliente
LEFT JOIN (
          SELECT ccodcta, SUM(KP) AS sum_KP
        FROM CREDKAR
        WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
        GROUP BY ccodcta
    ) AS kar ON kar.ccodcta = cm.CCODCTA
WHERE (((cm.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 AND cm.DFecDsbls <= ?)
    OR ((cm.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0 AND cm.fecha_operacion BETWEEN ? AND ?))
    AND (cli.primer_name IS NOT NULL AND cli.primer_name != '')
    AND (cli.primer_last IS NOT NULL AND cli.primer_last != '');";

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

/* +++++++++ CONSULTA DE TODAS LAS GARANTIAS DE LOS CREDITOS QUE ESTAN EN EL RANGO DE FECHAS DADO +++++++++ */
// $qryGaranti = "SELECT DISTINCT idGarantia,idTipoGa,idCliente,id_cremcre_meta,descripcionGarantia,
//     IFNULL((SELECT cod_crediref FROM " . $db_name_general . ".tb_tiposgarantia WHERE id_TiposGarantia=gar.idTipoGa),'099') codgarantia
//     FROM cli_garantia gar INNER JOIN tb_garantias_creditos creg ON creg.id_garantia=gar.idGarantia WHERE creg.id_cremcre_meta IN 
//     (SELECT CCODCTA FROM cremcre_meta WHERE (CESTADO='F' AND DFecDsbls<=?) OR 
//     (CESTADO='G' AND fecha_operacion BETWEEN ? AND ?));";

$qryGaranti="SELECT DISTINCT idGarantia,idTipoGa,idCliente,id_cremcre_meta,descripcionGarantia,
    IFNULL((SELECT cod_crediref FROM $db_name_general.tb_tiposgarantia WHERE id_TiposGarantia=gar.idTipoGa),'099') codgarantia
    FROM cli_garantia gar INNER JOIN tb_garantias_creditos creg ON creg.id_garantia=gar.idGarantia WHERE creg.id_cremcre_meta IN 
    (
	 SELECT cmm.CCODCTA FROM cremcre_meta cmm
	 LEFT JOIN (
          SELECT ccodcta, SUM(KP) AS sum_KP
        FROM CREDKAR
        WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
        GROUP BY ccodcta
    ) AS kar ON kar.ccodcta = cmm.CCODCTA
	 WHERE 
	 ((cmm.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 AND DFecDsbls<=?) OR 
    ((cmm.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0 AND fecha_operacion BETWEEN ? AND ?)
	 );";
/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

/*++++++++++++++++++++++ CONSULTA DE LOS CLIENTES QUE ESTAN COMO FIADORES +++++++++++++++++++++++++++++ */
// $qryFiador = "SELECT cli.*, creg.id_garantia, gar.idGarantia FROM clientesCrediref cli
// LEFT JOIN cli_garantia gar ON cli.idcod_cliente=gar.descripcionGarantia 
// INNER JOIN tb_garantias_creditos creg ON creg.id_garantia=gar.idGarantia
// WHERE (CESTADO='F' AND DFecDsbls <= ? ) OR (CESTADO='G' AND fecha_operacion BETWEEN ? AND ? )";

$qryFiador="SELECT 
    DISTINCT cli.idcod_cliente,
    cli.id_tipoCliente AS tipo,
    cli.primer_name,
    cli.segundo_name,
    cli.tercer_name,
    cli.primer_last,
    cli.segundo_last,
    cli.casada_last,
    cli.date_birth,
    cli.genero,
    cli.estado_civil,
    cli.type_doc,
    cli.no_identifica,
    cli.no_tributaria,
    cli.no_igss,
    cli.nacionalidad,
    m1.codigo muni_extiende,
    cli.tel_no1,
    cli.tel_no2,
    cli.Direccion,
    cm.CCODCTA,
    cm.Cestado AS CESTADO,
    COALESCE(m1.cod_crediref, '10001') AS municipio,
    COALESCE(m1.cod_crediref, 'X') AS codigo_postal
FROM tb_cliente cli
LEFT JOIN tb_municipios m1 
    ON m1.id = cli.id_muni_extiende
INNER JOIN cli_garantia gar 
    ON cli.idcod_cliente = gar.descripcionGarantia 
INNER JOIN tb_garantias_creditos creg 
    ON creg.id_garantia = gar.idGarantia
INNER JOIN cremcre_meta cm 
    ON cm.CCODCTA=creg.id_cremcre_meta
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cm.CCODCTA
WHERE (((cm.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 AND cm.DFecDsbls <= ?)
    OR ((cm.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0 AND cm.fecha_operacion BETWEEN ? AND ?))
	 GROUP BY 
    cli.idcod_cliente ;";

/*++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  ++++++++++++++++++++++ CONSULTA DE LOS CREDITOS VIGENTES HASTA LA FECHA FINAL ++++++++++++++++++++++++++++
  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
function CreQUERY($qrydata, $db_name_general)
{

  $qryCre = "SELECT cremi.CCODCTA ccodcta, cremi.Cestado estado, cli.idcod_cliente ccodcli, cremi.NCapDes ncapdes,kar.sum_KP cappag,
    COALESCE(
      ( SELECT SUM(ncapita + nintere + OtrosPagos) cuota
        FROM Cre_ppg
        WHERE ccodcta = cremi.CCODCTA AND (dfecven >= ? OR dfecven < ?)
        GROUP BY Id_ppg
        ORDER BY dfecven DESC
        LIMIT 1	), 0) cuota_mes,
    COALESCE(
      (	SELECT SUM(ncapita)
        FROM Cre_ppg
        WHERE dfecven <= ? AND ccodcta = cremi.CCODCTA
        GROUP BY ccodcta
      ), 0) capcalafec,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) atraso,
    COALESCE(
      (	SELECT cod_crediref
        FROM " . $db_name_general . ".tb_cre_periodos
        WHERE cod_msplus = cremi.NtipPerC
      ), 'O') periodo,
    COALESCE(
      (	SELECT cod_crediref
        FROM " . $db_name_general . ".tb_destinocredito
        WHERE id_DestinoCredito = cremi.Cdescre
      ), 'O') destino
    FROM  cremcre_meta cremi
        LEFT JOIN (
          SELECT ccodcta, SUM(KP) AS sum_KP
        FROM CREDKAR
        WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
        GROUP BY ccodcta
    ) AS kar ON kar.ccodcta = cremi.CCODCTA
    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
    INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
    WHERE (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') " . $qrydata[0] . "  AND " . $qrydata[1] . " 
    ORDER BY cremi.DFecDsbls; ";

  return $qryCre;
}
//   function CreQUERY($qrydata, $db_name_general ){   
//     $qryCre = "SELECT cremi.CCODCTA ccodcta, cremi.Cestado estado, cli.idcod_cliente ccodcli, cremi.NCapDes ncapdes,
//     COALESCE(
//       ( SELECT SUM(ncapita + nintere + OtrosPagos) cuota
//         FROM Cre_ppg
//         WHERE ccodcta = cremi.CCODCTA AND (dfecven >= ? OR dfecven < ?)
//         GROUP BY Id_ppg
//         ORDER BY dfecven DESC
//         LIMIT 1	), 0) cuota_mes,
//     COALESCE(
//       (	SELECT SUM(ncapita)
//         FROM Cre_ppg
//         WHERE dfecven <= ? AND ccodcta = cremi.CCODCTA
//         GROUP BY ccodcta
//       ), 0) capcalafec,
//     COALESCE(
//       (	SELECT SUM(KP)
//         FROM CREDKAR
//         WHERE dfecpro <= ? AND ccodcta = cremi.CCODCTA AND cestado != 'X' AND ctippag = 'P'
//         GROUP BY ccodcta
//       ), 0) cappag,
//     CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) atraso,
//     COALESCE(
//       (	SELECT cod_crediref
//         FROM ".$db_name_general.".tb_cre_periodos
//         WHERE cod_msplus = cremi.NtipPerC
//       ), 'O') periodo,
//     COALESCE(
//       (	SELECT cod_crediref
//         FROM ".$db_name_general.".tb_destinocredito
//         WHERE id_DestinoCredito = cremi.Cdescre
//       ), 'O') destino
//     FROM  cremcre_meta cremi
//     INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
//     INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
//     WHERE cremi.CESTADO='".$qrydata[0]."' AND ".$qrydata[1]." 
//     ORDER BY cremi.DFecDsbls; ";

//   return $qryCre;
// }
/* ++++++++++++++++++++++ CONSULTA executequery FUNCTION +++++++++++++++++++++++++++++ */
function executequery($query, $params, $conexion)
{
  $stmt = $conexion->prepare($query);
  $aux = mysqli_error($conexion);
  if ($aux) {
    return ['ERROR: ' . $aux, false];
  }
  $types = '';
  $bindParams = [];
  $bindParams[] = &$types;
  foreach ($params as &$param) {
    $types .= 's';
    $bindParams[] = &$param;
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
  // Cerrar la conexión  //mysqli_close($conexion);
  return [$data, true];
}

function DiasMes($anio, $mes)
{
  $primerDia = date('Y-m-01', strtotime("$anio-$mes"));
  $ultimoDia = date('Y-m-t', strtotime("$anio-$mes"));

  return [
    'primer_dia' => $primerDia,
    'ultimo_dia' => $ultimoDia,
  ];
}

/*************************** MOSTRAR RESULTADOS DE LAS CONSULTAS ****************************************
	echo json_encode(['status' => 0, 'mensaje' =>  $query ]);
	return ;
/*********************************************************************************************************

 */
