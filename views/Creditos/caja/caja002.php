<?php
session_start();
$usuario = $_SESSION["id"];
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");
include '../../../includes/BD_con/db_con.php';
include '../cre_grupo/functions/group_functions.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');


$condi = $_POST["condi"];
switch ($condi) {

    /*----------------------------------------------------------
PAGO GRUPAL BY BENEQ
------------------------------------------------------------*/
    case 'PagGrupAutom':
        $id_agencia = $_SESSION['id_agencia'];
        $datpost = $_POST["xtra"];
        $extra = $datpost[0];

        $numrecibo = 0;
        $i = 0;
        $consulta = mysqli_query($conexion, "SELECT MAX(CAST(CNUMING AS SIGNED)) numactual,usu.id_agencia FROM CREDKAR cred 
    INNER JOIN tb_usuario usu ON usu.id_usu=cred.CCODUSU WHERE usu.id_agencia=" . $id_agencia . " GROUP BY usu.id_agencia;");
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $numrecibo = $fila['numactual'];
            $i++;
        }
        $numrecibo++;

        $bandera = "Grupo sin cuentas vigentes";
        if ($extra != 0) {
            $numciclo = $datpost[1];
            //CREDITOS DEL GRUPO
            $datos[] = [];
            $datacre = mysqli_query($conexion, 'SELECT DISTINCT 
        gru.NombreGrupo,
        gru.direc,
        gru.codigo_grupo,
        cli.short_name,
        cre.CCODCTA,
        cre.NCiclo,
        cre.noPeriodo AS cuotas,
        cre.MonSug,
        cre.NIntApro AS interes,
        cre.DFecDsbls,
        ce.Credito AS tipocred,
        per.nombre AS nomper,
        cre.NCapDes,
        IFNULL(
            (
                SELECT SUM(KP)  FROM CREDKAR  WHERE ctippag = "P" AND CESTADO != "X" AND ccodcta = cre.CCODCTA GROUP BY ccodcta
            ), 
            0
        ) AS cappag,
        ((cre.MonSug) - (
            SELECT IFNULL(SUM(ck.KP), 0) FROM CREDKAR ck WHERE ck.CESTADO != "X"   AND ck.CTIPPAG = "P"   AND ck.CCODCTA = cre.CCODCTA
        )) AS saldocap,
        (
            (SELECT IFNULL(SUM(nintere), 0) 
             FROM Cre_ppg 
             WHERE ccodcta = cre.CCODCTA
            ) - 
            (SELECT IFNULL(SUM(ck.INTERES), 0) 
             FROM CREDKAR ck 
             WHERE ck.CESTADO != "X" 
               AND ck.CTIPPAG = "P" 
               AND ck.CCODCTA = cre.CCODCTA
            )
        ) AS saldoint,
        prod.id_fondo
    FROM 
        cremcre_meta cre
    INNER JOIN 
        tb_cliente cli 
        ON cli.idcod_cliente = cre.CodCli
    INNER JOIN 
        tb_grupo gru 
        ON gru.id_grupos = cre.CCodGrupo
    LEFT JOIN 
        cre_productos prod 
        ON prod.id = cre.CCODPRD
    LEFT JOIN 
        ' . $db_name_general . '.tb_credito ce 
        ON cre.CtipCre = ce.abre
        LEFT JOIN 
    ' . $db_name_general . '.tb_periodo per 
    ON cre.NtipPerC = per.periodo
        WHERE cre.TipoEnti = "GRUP" AND cre.NCiclo = ' . $numciclo . ' AND cre.CESTADO = "F" AND cre.CCodGrupo = "' . $extra . '"');
            $i = 0;

            $i = 0;
            while ($da = mysqli_fetch_array($datacre, MYSQLI_ASSOC)) {
                $datos[$i] = $da;
                $i++;
                $bandera = "";
            }

            //CUOTAS PENDIENTES DEL GRUPO
            $cuotas[] = [];
            $datacuo = mysqli_query($conexion, 'SELECT timestampdiff(DAY,ppg.dfecven,"' . $hoy . '") atraso,ppg.* FROM Cre_ppg ppg WHERE ccodcta IN (SELECT cre.CCODCTA From cremcre_meta cre WHERE cre.CESTADO="F" AND cre.CCodGrupo="' . $extra . '") 
                        AND ppg.CESTADO="X" ORDER BY ppg.ccodcta,ppg.dfecven,ppg.cnrocuo');
            $i = 0;
            while ($da = mysqli_fetch_array($datacuo, MYSQLI_ASSOC)) {
                $cuotas[$i] = $da;
                $i++;
            }

            //UNION DE TODOS LOS DATOS
            if ($bandera == "") {
                $datacom[] = [];
                $j = 0;
                while ($j < count($datos)) {
                    $ccodcta = $datos[$j]["CCODCTA"];
                    $datos[$j]["cuotaspen"] = [];
                    $datacom[$j] = $datos[$j];

                    //FILTRAR LAS CUOTAS DE LA CUENTA ACTUAL
                    $keys = filtro($cuotas, "ccodcta", $ccodcta, $ccodcta);
                    $fila = 0;
                    $count = 0;
                    while ($fila < count($keys)) {
                        $i = $keys[$fila];
                        $fecven = $cuotas[$i]["dfecven"];
                        if ($fecven <= $hoy) {
                            $cuotas[$i]["estado"] = ($fecven < $hoy) ? 2 : 1;
                            $count++;
                        } else {
                            $cuotas[$i]["estado"] = 0;
                        }
                        $datacom[$j]["cuotaspen"][$fila] = $cuotas[$i];
                        $fila++;
                    }
                    //COMPROBAR SI SOLO TIENE CUOTAS VENCIDAS O IMPRIMIR LA CUOTA SIGUIENTE A PAGAR
                    if (count(filtro($datacom[$j]["cuotaspen"], 'estado', 1, 2)) == 0) {
                        //echo 'No hay cuotas vencidas o por vencer'; SE IMPRIMIRA SIGUIENTE NO PAGADA
                        $keyses = filtro($datacom[$j]["cuotaspen"], 'estado', 0, 0);
                        $fa = 0;
                        while ($fa < count($keyses) && $fa < 1) {
                            $il = $keyses[$fa];
                            $datacom[$j]["cuotaspen"][$il]["estado"] = 3;
                            $fa++;
                        }
                    }
                    //ELIMINACION DEL ARRAY LAS CUOTAS QUE NO SERAN IMPRESAS
                    $keynot = filtro($datacom[$j]["cuotaspen"], 'estado', 0, 0);
                    $faf = 0;
                    while ($faf < count($keynot)) {
                        $il = $keynot[$faf];
                        unset($datacom[$j]["cuotaspen"][$il]);
                        $faf++;
                    }

                    $datacom[$j]["sumcapital"] = array_sum(array_column($datacom[$j]["cuotaspen"], "ncappag"));
                    $datacom[$j]["sumintere"] = array_sum(array_column($datacom[$j]["cuotaspen"], "nintpag"));
                    // $datacom[$j]["sumaho"] = array_sum(array_column($datacom[$j]["cuotaspen"], "AhoPrgPag"));
                    $datacom[$j]["summora"] = array_sum(array_column($datacom[$j]["cuotaspen"], "nmorpag"));
                    $datacom[$j]["sumotrospagos"] = array_sum(array_column($datacom[$j]["cuotaspen"], "OtrosPagosPag"));
                    $j++;
                }
            }
        }
        // echo '<pre>';
        // print_r($datacom);
        // echo '</pre>';

?>
        <input type="text" readonly hidden value='PagGrupAutom' id='condi'>
        <input type="text" hidden value="caja002" id="file">
        <div class="card crdbody contenedort">
            <div class="card-header" style="text-align:left">
                <h4>Pago Grupal Automatico</h4>
            </div>
            <div class="card-body">
                <div class="row contenedort">
                    <h5>Detalle de Grupo</h5>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <div>
                                <span class="input-group-addon col-8">Nombre Grupo</span>
                                <input type="text" class="form-control " id="name"
                                    value="<?php if ($bandera == "") echo $datos[0]["NombreGrupo"]; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <br>
                            <button type="button" onclick="loadconfig('any',['F'])" class="btn btn-primary"
                                data-bs-toggle="modal" data-bs-target="#buscargrupo">
                                <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <div>
                                <span class="input-group-addon col-8">Direccion</span>
                                <input type="text" class="form-control " id="name1"
                                    value="<?php if ($bandera == "") echo $datos[0]["direc"]; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="codgrup" class="input-group-addon">Codigo de Grupo</label>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(9rem,90%);" class="badge rounded-pill text-bg-success">' . $datos[0]["codigo_grupo"] . '</span>'; ?>

                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="row" style="display:grid;align-content:center; align-items: center;">
                                <label for="nciclo" class="input-group-addon">Ciclo</label>
                                <?php if ($bandera == "") echo '<span style="font-size:1rem;width:min(6rem,80%);" class="badge rounded-pill text-bg-success">' . $datos[0]["NCiclo"] . '</span>';
                                if ($bandera == "") echo '<input style="display:none;" id="nciclo" value="' . $datos[0]["NCiclo"] . '">';
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($bandera != "" && $extra != "0") {
                        echo '<div class="alert alert-danger" role="alert">' . $bandera . '</div>';
                    }
                    ?>
                </div>
                <div class="row contenedort">
                    <h5>Detalle de boleta de pago</h5>
                    <div class="row crdbody">
                        <div class="form-group col-md-4">
                            <label class="input-group-addon fw-bold">No. Documento</label>
                            <input type="text" class="form-control" placeholder="00000-111111111111" id="numdoc"
                                value="<?= $numrecibo ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <span class="input-group-addon fw-bold">Fecha de Pago</span>
                            <input type="date" class="form-control" id="fecha" value="<?php echo $hoy; ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <span class="input-group-addon fw-bold">Método de Pago:</span>
                            <select id="metodoPago" name="metodoPago" aria-label="Default select example" onchange="showBTN()"
                                class="form-select">
                                <option selected value="1">Pago en Efectivo</option>
                                <option value="2">Boleta de Banco</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="row contenedort">
                    <div class="row col">
                        <div class="text-center mb-2"><b>Forma de Pago </b></div>
                    </div>
                    <!-- fila de seleccion de bancos -->
                    <div class="row d-none mostrar">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="bancoid" onchange="buscar_cuentas()">
                                    <option value="F000" disabled selected>Seleccione un banco</option>
                                    <?php $bancos = mysqli_query($conexion, "SELECT * FROM tb_bancos WHERE estado='1'");
                                    while ($banco = mysqli_fetch_array($bancos)) {
                                        echo '<option value="' . $banco['id'] . '">' . $banco['id'] . "-" . $banco['nombre'] . '</option>';
                                    }
                                    ?>
                                </select>
                                <label for="bancoid">Banco</label>
                            </div>
                        </div>
                        <!-- id de cuenta para edicion -->
                        <!-- select normal -->
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="cuentaid">
                                    <option selected disabled value="F000">Seleccione una cuenta</option>
                                </select>
                                <label for="cuentaid">No. de Cuenta</label>
                            </div>
                        </div>
                    </div>
                    <!-- FECHA DE LA BOLETA  -->
                    <div class="row d-none mostrar">
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="fecpagBANC" value="<?= date('Y-m-d') ?>">
                                <label for="fecpag">Fecha Boleta:</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="noboletabanco"
                                    placeholder="Número de boleta de banco">
                                <label for="noboletabanco">No. Boleta de Banco</label>
                            </div>
                        </div>
                        <div class="row d-none">
                            <input type="date" class="form-control" id="efectivo" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                <div class="row contenedort"
                    style="background-image: url(https://mdbootstrap.com/img/Photos/new-templates/glassmorphism-article/img9.jpg);">
                    <h5>Montos sugeridos por cuentas</h5>
                    <?php
                    if ($bandera == "") {
                        $j = 0;
                        $modales_html = ''; // Variable para almacenar los modales
                        while ($j < count($datacom)) {
                            $ccodcta = $datacom[$j]["CCODCTA"];
                            $name = $datacom[$j]["short_name"];
                            $saldoint = $datacom[$j]["saldoint"];
                            $tipocred = $datacom[$j]["tipocred"];
                            $nomper = $datacom[$j]["nomper"];
                            $cuotas = $datacom[$j]["cuotas"];
                            $interes = $datacom[$j]["interes"];
                            $MonSug = $datacom[$j]["MonSug"];
                            $fecdes = date("d-m-Y", strtotime($datacom[$j]["DFecDsbls"]));
                            $capdes = $datacom[$j]["NCapDes"];
                            $cappag = $datacom[$j]["cappag"];
                            $salcap = (($capdes - $cappag) < 0) ? 0 : ($capdes - $cappag);
                            $sumcapital = $datacom[$j]["sumcapital"];
                            $suminteres = $datacom[$j]["sumintere"];
                            $summora = $datacom[$j]["summora"];
                            // $sumahorro = $datacom[$j]["sumaho"];
                            $sumotrospagos = $datacom[$j]["sumotrospagos"];
                            $total = $sumcapital + $suminteres + $sumotrospagos + $summora;
                            $datacom[$j]["totalparcial"] = $total;
                            $idit = "collaps" . $j; //id de cada collaps
                            $concepto = 'PAGO DE CREDITO A NOMBRE DE ' . $name . ', NUMERO DE RECIBO ' . $numrecibo;
                            //IMPRESION DE TITULOS DE CADA CREDITO 
                    ?>
                            <div class="accordion" id="cuotas">
                                <div class="accordion-item  border border-4 border-success rounded-4">
                                    <div class="accordion" id="creditAccordion">
                                        <!-- Acordeón -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?= $ccodcta; ?>">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#collapse<?= $ccodcta; ?>" aria-expanded="true"
                                                    aria-controls="collapse<?= $ccodcta; ?>">
                                                    <!-- Información Principal del Crédito -->
                                                    <?= strtoupper($name); ?> - <?= $ccodcta; ?> | Capital: <?= $MonSug; ?> | Saldo:
                                                    <?= $salcap; ?>
                                                </button>
                                            </h2>
                                            <div id="collapse<?= $ccodcta; ?>" class="accordion-collapse collapse"
                                                aria-labelledby="heading<?= $ccodcta; ?>" data-bs-parent="#creditAccordion">
                                                <div class="accordion-body">
                                                    <!-- Tarjetas de Detalle -->
                                                    <div class="row m-1" style="font-size: 0.80rem;">
                                                        <div class="col-sm-4 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h6 class="card-title text-primary"><?php echo strtoupper($name); ?>
                                                                    </h6>
                                                                    <div class="input-group">
                                                                        <span class="input-group-addon"><?php echo $ccodcta; ?></span>
                                                                        <input id="<?php echo 'ccodcta' . $j; ?>" type="text"
                                                                            value="<?php echo $ccodcta; ?>" hidden>
                                                                        <input id="<?php echo 'namecli' . $j; ?>" type="text"
                                                                            value="<?php echo $name; ?>" hidden>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Otorgamiento</h5>
                                                                    <p class="card-text" style="color: #555;"><?php echo $fecdes; ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Otorgado</h5>
                                                                    <span class="card-text"><?php echo $capdes; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body" id="capital">
                                                                    <h5 class="card-title small text-primary">Capital</h5>
                                                                    <span class="card-text"><?php echo $MonSug; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Saldo Capital</h5>
                                                                    <span class="card-text"><?php echo $salcap; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Interes</h5>
                                                                    <span class="card-text"><?php echo $interes; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Saldo Interes</h5>
                                                                    <span class="card-text"><?php echo $saldoint; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Tipo de Credito</h5>
                                                                    <span class="card-text"><?php echo $tipocred; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Tipo de Periodo</h5>
                                                                    <span class="card-text"><?php echo $nomper; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-2 mb-2">
                                                            <div class="card"
                                                                style="border: 1px solid #007bff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);">
                                                                <div class="card-body">
                                                                    <h5 class="card-title small text-primary">Cuotas</h5>
                                                                    <span class="card-text"><?php echo $cuotas; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 mt-3 text-center">
                                                            <button class="btn btn-outline-primary"
                                                                onclick="mostrar_planpago('<?= $ccodcta; ?>');" data-bs-toggle="modal"
                                                                data-bs-target="#modal_plan_pago">
                                                                <i class="fa-solid fa-rectangle-list me-2"></i> Plan de pago
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="row">
                                            <div class="col-12 mb-2">
                                                <span class="badge text-bg-primary">Verifique el concepto antes de guardar</span>
                                            </div>
                                            <div class="col-sm-12">
                                                <textarea style="height: auto; font-size: 12px;" class="form-control"
                                                    id="concepto<?php echo $j; ?>" rows="1"><?= $concepto ?></textarea>
                                            </div>
                                        </div>
                                    </div>


                                    <h2 class="accordion-header">
                                        <button id="<?php echo 'bt' . $j; ?>" onclick="opencollapse(<?php echo $j; ?>)"
                                            style="--bs-bg-opacity: .2;"
                                            class="accordion-button collapsed bg-<?php echo ($datacom[$j]["cuotaspen"][0]['atraso'] > 0) ? 'danger' : 'success'; ?>"
                                            data-bs-target="#<?php echo $idit; ?>" aria-expanded="false"
                                            aria-controls="<?php echo $idit; ?>">
                                            <div class="row" style="font-size: 0.80rem;">
                                                <div class="col-sm-2">
                                                    <span class="input-group-addon">Capital</span>
                                                    <input id="<?php echo 'capital' . $j; ?>" disabled min="0"
                                                        onclick="opencollapse(-1)" onblur="summon(this.id)" type="number" step="0.01"
                                                        class="form-control habi form-control-sm" value="<?php echo $sumcapital; ?>">
                                                </div>
                                                <div class="col-sm-2">
                                                    <span class="input-group-addon">Interes</span>
                                                    <input id="<?php echo 'interes' . $j; ?>" disabled min="0"
                                                        onclick="opencollapse(-1)" onblur="summon(this.id)" type="number" step="0.01"
                                                        class="form-control habi form-control-sm" value="<?php echo $suminteres; ?>">
                                                </div>
                                                <div class="col-sm-2">
                                                    <span class="input-group-addon">Mora</span>
                                                    <input id="<?php echo 'monmora' . $j; ?>" disabled min="0"
                                                        onclick="opencollapse(-1)" onblur="summon(this.id)" type="number" step="0.01"
                                                        class="form-control habi form-control-sm" value="<?php echo $summora; ?>">
                                                </div>
                                                <!-- <div class="col-sm-1">
                                                <span class="input-group-addon">Otros</span>
                                                <input id="<?php echo 'otrospg' . $j; ?>" disabled min="0" onclick="opencollapse(-1)" onblur="summon(this.id)" type="number" step="0.01" class="form-control habi form-control-sm" value="<?php echo $sumotrospagos; ?>">
                                            </div> -->
                                                <div class="col-sm-2">
                                                    <span class="input-group-addon">Otros</span>
                                                    <div class="input-group">
                                                        <input style="height: 10px !important;" id="<?php echo 'otrospg' . $j; ?>"
                                                            disabled readonly onclick="opencollapse(-1);" onchange="summon(this.id)"
                                                            type="number" step="0.01" class="form-control habi form-control-sm"
                                                            value="<?= $sumotrospagos; ?>">
                                                        <span id="<?php echo 'lotrospg' . $j; ?>" title="Modificar detalle otros"
                                                            class="input-group-addon btn btn-link" data-bs-toggle="modal"
                                                            data-bs-target="#modalgastos<?php echo $j; ?>"
                                                            onclick="opencollapse(-1);"><i class="fa-solid fa-pen-to-square"></i></span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <span class="input-group-addon">Total</span>
                                                    <input id="<?php echo 'totalpg' . $j; ?>" readonly onclick="opencollapse(-1)"
                                                        type="number" step="0.01" class="form-control habi form-control-sm"
                                                        value="<?php echo $total; ?>">
                                                </div>
                                                <div class="col-sm-1">
                                                    <div class="form-check form-switch">
                                                        <br>
                                                        <input onclick="opencollapse('<?php echo 's' . $j; ?>');"
                                                            class="form-check-input" type="checkbox" role="switch"
                                                            id="<?php echo 's' . $j; ?>" title="Modificar Cantidades">
                                                    </div>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="<?php echo $idit; ?>" class="accordion-collapse collapse" data-bs-parent="#cuotas">
                                        <div class="accordion-body">
                                            <ul class="list-group">
                                                <?php
                                                $i = 0;
                                                while ($i < count($datacom[$j]["cuotaspen"])) {
                                                    $fecven = date("d-m-Y", strtotime($datacom[$j]["cuotaspen"][$i]["dfecven"]));
                                                    $atraso = $datacom[$j]["cuotaspen"][$i]["atraso"];
                                                    $atraso = ($atraso < 0) ? 0 : $atraso;
                                                    $ncuota = $datacom[$j]["cuotaspen"][$i]["cnrocuo"];
                                                    $capcal = $datacom[$j]["cuotaspen"][$i]["ncapita"];
                                                    $intcal = $datacom[$j]["cuotaspen"][$i]["nintere"];
                                                    $morcal = $datacom[$j]["cuotaspen"][$i]["nintmor"];
                                                    // $ahocal = $datacom[$j]["cuotaspen"][$i]["NAhoProgra"];

                                                    $cappen = $datacom[$j]["cuotaspen"][$i]["ncappag"];
                                                    $intpen = $datacom[$j]["cuotaspen"][$i]["nintpag"];
                                                    $morpen = $datacom[$j]["cuotaspen"][$i]["nmorpag"];
                                                    // $ahopen = $datacom[$j]["cuotaspen"][$i]["AhoPrgPag"];

                                                    $estado = $datacom[$j]["cuotaspen"][$i]["estado"];
                                                    $variables = [["warning", "X vencer"], ["danger", "Vencida"], ["success", "Vigente"]];
                                                    if ($estado > 0) {
                                                        //DETALLE DE CADA CUOTA ATRASADA
                                                ?>
                                                        <li class="list-group-item">
                                                            <div class="row" style="font-size: 0.80rem;">
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">No. Cuota</span>
                                                                        <span class="input-group-addon"><?php echo $ncuota; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Vencimiento:</span>
                                                                        <span class="input-group-addon"><?php echo $fecven; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Capital</span>
                                                                        <span class="input-group-addon"><?php echo $cappen; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Interes</span>
                                                                        <span class="input-group-addon"><?php echo $intpen; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Dias Atraso</span>
                                                                        <span class="input-group-addon"><?php echo $atraso; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-sm-2">
                                                                    <div class="row">
                                                                        <span class="input-group-addon">Estado</span>
                                                                        <span
                                                                            class="input-group-addon badge text-bg-<?php echo $variables[$estado - 1][0]; ?>"><?php echo  $variables[$estado - 1][1]; ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </li>
                                                <?php
                                                    }
                                                    $i++;
                                                    //FIN DETALLE CADA CUOTA ATRASADA
                                                }

                                                ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                </div>
                            </div>
                            <?php
                            // Almacenar el modal en la variable en lugar de imprimirlo aquí
                            ob_start(); // Capturar la salida del modal
                            ?>
                            <!-- Modal -->
                            <div class="modal fade " id="modalgastos<?php echo $j; ?>" tabindex="-1" aria-labelledby="exampleModalLabel"
                                aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h1 class="modal-title fs-5" id="exampleModalLabel">Detalle de otros cobros</h1>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            <input style="display: none;" id="flagid" readonly type="number" value="0">
                                        </div>
                                        <div class="modal-body">
                                            <table class="table" id="tbgastoscuota<?php echo $j; ?>" class="display" style="width:100%">
                                                <thead>
                                                    <tr>
                                                        <th>Descripcion</th>
                                                        <th>Afecta Otros modulos</th>
                                                        <th>Adicional</th>
                                                        <th>Monto</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="categoria_tb">
                                                    <tr>
                                                        <td>OTROS</td>
                                                        <td data-id="0">-</td>
                                                        <td data-cuenta="0">-</td>
                                                        <td>
                                                            <div class="row d-flex justify-content-end">
                                                                <input style="display:none;" type="text" name="idgasto" value="0">
                                                                <input style="display:none;" type="text" name="idcontable" value="0">
                                                                <input onkeyup="sumotros(<?php echo $j; ?>)" style="text-align: right;"
                                                                    id="DS" type="number" step="0.01"
                                                                    class="form-control form-control-sm inputNoNegativo"
                                                                    value="<?= $sumotrospagos; ?>" data-j="<?php echo $j; ?>">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    $query = "SELECT gas.id,gas.id_nomenclatura,gas.nombre_gasto,gas.afecta_modulo,cre.cntAho ccodaho,cre.NCapDes mondes, cre.noPeriodo cuotas,cre.moduloafecta,pro.* FROM cre_productos_gastos pro 
                                                INNER JOIN cre_tipogastos gas ON gas.id=pro.id_tipo_deGasto
                                                INNER JOIN cremcre_meta cre ON cre.CCODPRD=pro.id_producto
                                                WHERE cre.CCODCTA='" . $ccodcta . "' AND pro.tipo_deCobro=2 AND gas.estado=1 AND pro.estado=1";
                                                    $consulta = mysqli_query($conexion, $query);
                                                    $array_datos = array();

                                                    $k = 0;
                                                    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                                                        $tipo = $fila['tipo_deMonto'];
                                                        $cant = $fila['monto'];
                                                        $calculax = $fila['calculox'];
                                                        $mondes = $fila['mondes'];
                                                        $cuotas = $fila['cuotas'];
                                                        if ($tipo == 1) { //MONTO FIJO
                                                            $mongas = ($calculax == 1) ? $cant : (($calculax == 2) ? ($cant / $cuotas) : $cant);
                                                        }
                                                        if ($tipo == 2) { //PORCENTAJE
                                                            //$mongas = ($calculax == 1) ? ($cant / 100 * $amortiza1[$i]) : (($calculax == 2) ? ($cant / 100 * $row) : (($calculax == 3) ? ($cant / 100 * ($amortiza1[$i] + $row)) : 0));
                                                        }
                                                        $afecta = $fila["afecta_modulo"];
                                                        $cremmodulo = $fila["moduloafecta"];
                                                        $modulo = ($afecta == 1) ? 'AHORROS' : (($afecta == 2) ? 'APORTACIONES' : 'NO');
                                                        $cuenta = ($afecta == 1 || $afecta == 2) ? (($cremmodulo == $afecta && strlen(trim($fila["ccodaho"])) >= 12) ? $fila["ccodaho"] : 'No hay cuenta vinculada') : 'NO';
                                                        $disabled = ($afecta == 1 || $afecta == 2) ? (($cremmodulo == $afecta && strlen(trim($fila["ccodaho"])) >= 12) ? '' : 'disabled readonly') : '';

                                                        // $cuenta = ($cremmodulo == $afecta) ? $fila["ccodaho"] : 'NO';
                                                        echo '<tr>
                                                <td>' . $fila["nombre_gasto"] . '</td>
                                                <td data-id="' . $afecta . '">' . $modulo . '</td>
                                                <td data-cuenta="' . $cuenta . '">' . $cuenta . '</td>
                                                <td><div class="row d-flex justify-content-end">
                                                        <input style="display:none;" type="text" name="idgasto" value="' . $fila['id'] . '">
                                                        <input style="display:none;" type="text" name="idcontable" value="' . $fila['id_nomenclatura'] . '">
                                                        <input ' . $disabled . ' onkeyup="sumotros(' . $j . ')" style="text-align: right;" type="number" step="0.01" class="form-control form-control-sm inputNoNegativo" value="0" data-j="' . $j . '">
                                                    </div></td>
                                            </tr>';

                                                        $k++;
                                                    }
                                                    $cantfila = $k;
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php
                            $modales_html .= ob_get_clean(); // Guardar el contenido del modal
                            $j++;
                        }
                        echo '<div class="row">
                            <div class="col-9"></div>
                            <div class="col-3">
                            <span class="input-group-addon">Total General</span>
                            <input id="totalgen" readonly type="number" step="0.01" class="form-control form-control-sm" value="' . array_sum(array_column($datacom, "totalparcial")) . '">
                            </div>
                            </div>';
                    } else {
                        echo '<span class="badge rounded-pill text-bg-danger">SELECCIONAR UNA CUENTA</span>';
                        $j = -1;
                    }
                    ?>

                </div>
            </div>
            <div class="row justify-items-md-center">
                <div class="col align-items-center" id="modal_footer">
                    <?php
                    if ($bandera == "") {
                        echo '<button type="button" class="btn btn-outline-success" onclick="guardar_pagos_grupales(' . ($j - 1) . ',' . $usuario . ',' . $extra . ',' . $numciclo . ',' . $datacom[0]["id_fondo"] . ',' . $id_agencia . ',' . array_sum(array_column($datacom, "summora")) . ')">
                            <i class="fa fa-floppy-disk"></i> Guardar
                        </button>';
                    }
                    ?>
                    <button type="button" class="btn btn-outline-danger"
                        onclick="printdiv('PagGrupAutom', '#cuadro', 'caja_cre', 0)">
                        <i class="fa-solid fa-ban"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="salir()">
                        <i class="fa-solid fa-circle-xmark"></i> Salir
                    </button>
                    <!-- <button onclick="reportes([['numdoc', 'nciclo'], [], [], [5]], 'pdf', 'comp_grupal', 0)">asdfas</button> -->
                </div>
            </div>
        </div>
        
        <?php 
        // Imprimir todos los modales fuera del contenedor principal
        if (isset($modales_html)) {
            echo $modales_html;
        }
        ?>
        
        <script>
            //------------INICIO CHAKA
            function guardar_pagos_grupales(cant, idusuario, idgrup, ciclo, idfondo, idagencia, summora) {
                var datos = [];
                var rows = 0;
                var filas = [];
                var morainput = 0;
                while (rows <= (cant)) {
                    filas = getinputsval(['ccodcta' + (rows), 'namecli' + (rows), 'capital' + (rows), 'interes' + (rows),
                        'monmora' + (rows), 'otrospg' + (rows), 'totalpg' + (rows), 'concepto' + (rows)
                    ]);
                    datos[rows] = filas;
                    morainput += parseFloat(numOr0(filas[4]));

                    //OBTENER DETALLES DE OTROS
                    var detalles = [];
                    detalles[0] = [0, 0, 0, 0, 0]
                    var i = 0;
                    $('#tbgastoscuota' + rows + ' tr').each(function(index, fila) {
                        var monto = $(fila).find('td:eq(3) input[type="number"]');
                        monto = (isNaN(monto.val())) ? 0 : Number(monto.val());
                        var idgasto = $(fila).find('td:eq(3) input[name="idgasto"]');
                        idgasto = Number(idgasto.val());
                        var idcontable = $(fila).find('td:eq(3) input[name="idcontable"]');
                        idcontable = Number(idcontable.val());
                        var modulo = $(fila).find('td:eq(1)').data('id');
                        var codaho = $(fila).find('td:eq(2)').data('cuenta');

                        if (monto > 0) {
                            detalles[i] = [monto, idgasto, idcontable, modulo, codaho];
                            i++;
                        }
                    });
                    datos[rows][8] = detalles;

                    //FIN OBTENER DETALLES DE OTROS
                    rows++;
                }
                var verificamora = (morainput.toFixed(2) != summora.toFixed(2)) ? 1 :
                    0; //una manera vaga de verificar pero por lo pronto

                // console.log(datos)
                // return;

                if (verificamora == 1) {
                    //SE TIENE QUE AUTORIZAR CAMBIO DE MORA
                    clave_confirmar_mora_grupal(cant, idusuario, idgrup, idfondo, ciclo, idagencia, datos);
                } else {
                    // PASA DIRECTAMENTE A GUARDAR
                    savepag(cant, idusuario, idgrup, idfondo, ciclo, idagencia, datos);
                }
            }

            function clave_confirmar_mora_grupal(cant, idusuario, idgrup, idfondo, ciclo, idagencia, datos) {
                Swal.fire({
                    title: 'Autorización para modificación de mora',
                    html: '<input id="user" class="swal2-input" type="text" placeholder="Usuario" autocapitalize="off">' +
                        '<input id="pass" class="swal2-input" type="password" placeholder="contraseña" autocapitalize="off">',
                    showCancelButton: true,
                    confirmButtonText: 'Validar autorización',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        const username = document.getElementById('user').value;
                        const password = document.getElementById('pass').value;
                        //AJAX PARA CONSULTAR EL USUARIO
                        return $.ajax({
                            url: "../../src/cruds/crud_usuario.php",
                            method: "POST",
                            data: {
                                'condi': 'validar_usuario_por_mora',
                                'username': username,
                                'pass': password
                            },
                            dataType: 'json',
                            success: function(data) {
                                // console.log(data);
                                if (data[1] != "1") {
                                    Swal.showValidationMessage(data[0]);
                                }
                            }
                        }).catch(xhr => {
                            Swal.showValidationMessage(`${xhr.responseJSON[0]}`);
                        });
                    },
                    allowOutsideClick: (outsideClickEvent) => {
                        const isLoading = Swal.isLoading();
                        const isClickInsideDialog = outsideClickEvent?.target?.closest('.swal2-container') !== null;
                        return !isLoading && !isClickInsideDialog;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        //aqui debera ejecutarse el obtiene
                        savepag(cant, idusuario, idgrup, idfondo, ciclo, idagencia, datos);
                    }
                });
            }

            function savepag(cant, user, idgrup, idfondo, ciclo, id_agencia, datainputs) {
                datadetal = getinputsval(['numdoc', 'fecha', 'metodoPago', 'bancoid', 'cuentaid', 'fecpagBANC', 'noboletabanco']);
                // console.log(datainputs)
                // console.log(datadetal)
                // return;
                generico([datainputs, datadetal], 0, 0, 'paggrupal', [0], [user, idgrup, idfondo, ciclo, id_agencia], 'crud_caja');
            }
            //------------ FIN CHAKA

            function sumotros(n) {
                var valor = 0;
                $('#tbgastoscuota' + n + ' tr').each(function(index, fila) {
                    var inputDS = $(fila).find('td:eq(3) input[type="number"]');
                    var valorDS = Number(inputDS.val());
                    valor += (isNaN(valorDS)) ? 0 : valorDS;
                });
                $("#otrospg" + n).val(valor);
                summon("otrospg" + n)
            }
            var inputs = document.querySelectorAll('.inputNoNegativo');
            inputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    var expresion = input.value;
                    var esValida = /^-?\d+(\.\d+)?([-+*/]\d+(\.\d+)?)*$/.test(expresion);
                    var j = input.dataset.j;
                    if (!esValida) {
                        //alert("Por favor, ingresa una expresión matemática válida.");
                        input.value = 0;
                        sumotros(j);
                    }
                    if (parseFloat(input.value) < 0) {
                        input.value = 0;
                        sumotros(j);
                    }
                });
            });
        </script>
<?php
        include_once "../../../src/cris_modales/mdls_planpago.php";
        break;
}
?>