<?php
session_start();
$usuario = $_SESSION["id"];
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
$condi = $_POST["condi"];

switch ($condi) {
    case 'clireport':
?>
<div class="text" style="text-align: center">FILTRADO DE DATOS</div>
<!----------------------------Seleccion de cliente-------------------------------->
<div class="container-fluid" id="datos">
    <div class="card crdbody">
        <div class="card-header panelcolor">DATOS </div>
        <div class="card-body">
            <div class="row crdbody contenedort">
                <div class="col-sm-10 col-md-8">
                    <div class="thumbnail">
                        <div class="caption">
                            <label for="activo">TIPO DE CLIENTE</label>
                            <select id="activo" class="form-select" onchange="tipcli(this.value)">
                                <option value="1">ACTIVOS</option>
                                <option value="0">NO ACTIVOS</option>
                            </select>
                        </div>
                    </div>
                </div>
                <br>
            </div>
            <div class="row crdbody contenedort">
                <div class="card">
                    <div class="card-header">
                        <div class="form-check form-switch">
                            <label class="form-check-label" for="checkalta">Filtro por Fecha de alta</label>
                            <input class="form-check-input" type="checkbox" role="switch" id="checkalta">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class=" col-sm-3">
                                <label for="alta_inicio">Desde</label>
                                <input type="date" class="form-control" id="alta_inicio" min="1950-01-01"
                                    value="<?php echo date("Y-m-d"); ?>">
                            </div>
                            <div class=" col-sm-3">
                                <label for="alta_fin">Hasta</label>
                                <input type="date" class="form-control" id="alta_fin" min="1950-01-01"
                                    value="<?php echo date("Y-m-d"); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row crdbody contenedort" id="filter_baja" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <div class="form-check form-switch">
                            <label class="form-check-label" for="checkbaja">Filtro por Fecha de baja</label>
                            <input class="form-check-input" type="checkbox" id="checkbaja">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class=" col-sm-3">
                                <label for="date_inicio">Desde</label>
                                <input type="date" class="form-control" id="baja_inicio" min="1950-01-01"
                                    value="<?php echo date("Y-m-d"); ?>">
                            </div>
                            <div class=" col-sm-3">
                                <label for="date_inicio">Hasta</label>
                                <input type="date" class="form-control" id="baja_fin" min="1950-01-01"
                                    value="<?php echo date("Y-m-d"); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-danger mt-2" onclick="printpdfcli()">
                <i class="fa-regular fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-outline-success mt-2" onclick="printxls()">
                <i class="fas fa-file-excel"></i> Generar Excel
            </button>
            <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                    <i class="fa-solid fa-circle-xmark"></i> Salir
                </button>

        </div>
    </div>
</div>
<!----------------------------seleccion de cliente fin---------------------------->

<?php
        break;
    case 'balance_economico': {
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
            $xtra = $_POST["xtra"];
            // echo $xtra;
            //consultar
            $i = 0;
            $bandera = false;
            $bandera_balance = false;
            $datos[] = [];
            $datosbalances[] = [];

            if ($xtra != 0) {
                $consulta = mysqli_query($conexion, "SELECT cl.idcod_cliente AS codcli, cl.short_name AS nombre, cl.no_identifica AS dpi, cl.Direccion AS direccion, cl.date_birth AS fechacumple,  cl.tel_no1 AS telefono, cl.genero AS genero
                FROM tb_cliente cl WHERE cl.estado=1 AND cl.idcod_cliente='$xtra'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $genero = ($fila['genero'] == 'F') ? 'Femenino' : 'Masculino';
                    $datos[$i] = $fila;
                    $datos[$i]['genero2'] = $genero;
                    $i++;
                    $bandera = true;
                }

                //CONSULTAR TODOS LOS BALANCES
                $i = 0;
                $consulta = mysqli_query($conexion, "SELECT * FROM tb_cli_balance WHERE ccodcli='" . $datos[0]['codcli'] . "'");
                while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                    $datosbalances[$i] = $fila;
                    $i++;
                    $bandera_balance = true;
                }
            }
            // echo '<pre>';
            // print_r($datosbalances);
            // echo '</pre>';

            include_once __DIR__ . "/../../src/cris_modales/modal_clientes_all.php";
        ?>
<!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
<input type="text" id="file" value="tem_clint" style="display: none;">
<input type="text" id="condi" value="balance_economico" style="display: none;">
<div class="text" style="text-align:center">BALANCE ECONÓMICO DEL CLIENTE</div>
<div class="card">
    <div class="card-header">Balance económico del cliente</div>
    <div class="card-body" style="padding-bottom: 0px !important;">

        <!-- seleccion de cliente y su credito-->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row">
                <div class="col">
                    <div class="text-center mb-2"><b>Información de cliente</b></div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-2 mt-2">
                        <input type="text" class="form-control" id="nomcli" placeholder="Nombre del cliente" readonly
                            <?php if ($bandera) {
                                                                                                                                        echo 'value="' . $datos[0]['nombre'] . '"';
                                                                                                                                    } ?>>
                        <label for="nomcli">Nombre del cliente</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <button type="button" class="btn btn-primary pt-3 pb-3 mb-2 mt-2 col-12 col-sm-12"
                        data-bs-toggle="modal" data-bs-target="#buscar_cli_all"><i
                            class="fa-solid fa-magnifying-glass-plus me-2"></i>Buscar cliente</button>
                </div>
            </div>
            <!-- cargo, nombre agencia y codagencia  -->
            <div class="row">
                <div class="col-12 col-sm-12 col-md-3">
                    <div class="form-floating mb-2 mt-2">
                        <input type="text" class="form-control" id="codcli" placeholder="Código de cliente" readonly
                            <?php if ($bandera) {
                                                                                                                                        echo 'value="' . $datos[0]['codcli'] . '"';
                                                                                                                                    } ?>>

                        <label for="codcli">Código de cliente</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="form-floating mb-2 mt-2">
                        <input type="text" class="form-control" id="dpi" placeholder="DPI" readonly <?php if ($bandera) {
                                                                                                                    echo 'value="' . $datos[0]['dpi'] . '"';
                                                                                                                } ?>>
                        <label for="dpi">DPI</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-6">
                    <div class="form-floating mb-2 mt-2">
                        <input type="text" class="form-control" id="direccion" placeholder="Dirección" readonly
                            <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['direccion'] . '"';
                                                                                                                            } ?>>
                        <label for="direccion">Dirección</label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="form-floating mb-3 mt-2">
                        <input type="date" class="form-control" id="fechacumple" placeholder="Fecha de nacimiento"
                            readonly
                            <?php if ($bandera) {
                                                                                                                                            echo 'value="' . $datos[0]['fechacumple'] . '"';
                                                                                                                                        } ?>>
                        <label for="fechacumple">Fecha de nacimiento</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="form-floating mb-3 mt-2">
                        <input type="text" class="form-control" id="telefono" placeholder="Teléfono" readonly
                            <?php if ($bandera) {
                                                                                                                                echo 'value="' . $datos[0]['telefono'] . '"';
                                                                                                                            } ?>>
                        <label for="telefono">Teléfono</label>
                    </div>
                </div>
                <div class="col-12 col-sm-12 col-md-4">
                    <div class="form-floating mb-3 mt-2">
                        <input type="text" class="form-control" id="genero" placeholder="Género" readonly
                            <?php if ($bandera) {
                                                                                                                            echo 'value="' . $datos[0]['genero2'] . '"';
                                                                                                                        } ?>>
                        <label for="genero">Género</label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="table-responsive">
                    <table class="table">
                        <caption>Historial de registro de balances</caption>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Fecha Evaluacion</th>
                                <th scope="col">Fecha Balance</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bandera_balance) {
                                            $i = 0;
                                            foreach ($datosbalances as $regbal) {
                                                echo '<tr>';
                                                echo '<th scope="row">' . ($i + 1) . '</th>';
                                                echo '<th scope="row">' . $regbal['fechaeval'] . '</th>';
                                                echo '<th scope="row">' . $regbal['fechabalance'] . '</th>';
                                                echo '<th scope="row">';
                                                echo '
                                                    <button type="button" class="btn btn-warning" onclick="lodata(' . $regbal["id"]  . ',`' . $datos[0]['codcli']  . '`)">Editar</button>
                                                </th>';
                                                echo '</tr>';
                                                $i++;
                                            }
                                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <?php if ($bandera) {
                                    //echo '<button type="button" class="btn btn-success" onclick="newrow()">Nuevo Registro</button>';
                                } ?>
                </div>
            </div>
        </div>
        <!-- SECCION DE FECHAS -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row">
                <div class="col">
                    <div class="text-center mb-2"><b>Fechas de evaluación y de balance</b></div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3 mt-2">
                        <input type="date" class="form-control" id="fecevaluacion" placeholder="Fecha de evaluación"
                            <?php echo 'value="' . date("Y-m-d") . '"'; ?>>
                        <label for="fecevaluacion">Fecha de evaluación</label>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="form-floating mb-3 mt-2">
                        <input type="date" class="form-control" id="fecbalance" placeholder="Fecha de balance"
                            <?php echo 'value="' . date("Y-m-d") . '" readonly disabled'; ?>>
                        <label for="fecbalance">Fecha de balance</label>
                    </div>
                </div>
            </div>
        </div>
        <!-- SECCION DE INGRESOS Y EGRESOS -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row">
                <div class="col">
                    <div class="text-center mb-2"><b>Ingresos, Egresos y Saldo</b></div>
                </div>
            </div>
            <div class="row m-1">
                <div class="col-12 col-sm-6 col-md-4 mb-2 border border-primary">
                    <div class="row">
                        <div class="col-12">
                            <div class="text-center mt-2 text-primary">INGRESOS</div>
                        </div>
                        <div class="col-12">
                            <div class="text-center mb-2"><span class="badge text-bg-primary" id="ingresostotal">Q.
                                    0.00</span></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control"
                                    onkeyup="sumar_valores_inputs('#ingresostotal',['#ventas','#cporcobrar'],[],1); sumar_valores_inputs('#saldo',['#ventas','#cporcobrar'],['#mercaderia','#gastosporcobrar','#pagoscreditos'],2);"
                                    id="ventas" placeholder="Ventas">
                                <label for="ventas">Ventas</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating mb-2 mt-1">
                                <input type="number" step="0.01" class="form-control"
                                    onkeyup="sumar_valores_inputs('#ingresostotal',['#ventas','#cporcobrar'],[],1); sumar_valores_inputs('#saldo',['#ventas','#cporcobrar'],['#mercaderia','#gastosporcobrar','#pagoscreditos'],2);"
                                    id="cporcobrar" placeholder="Recup. cuentas por cobrar">
                                <label for="cporcobrar">Recup. cuentas por cobrar</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 mb-2 border border-primary">
                    <div class="row">
                        <div class="col-12">
                            <div class="text-center mt-2 text-primary">EGRESOS</div>
                        </div>
                        <div class="col-12">
                            <div class="text-center mb-2"><span class="badge text-bg-primary" id="egresostotal">Q.
                                    0.00</span></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control"
                                    onkeyup="sumar_valores_inputs('#egresostotal',['#mercaderia','#gastosporcobrar','#pagoscreditos'],[],1); sumar_valores_inputs('#saldo',['#ventas','#cporcobrar'],['#mercaderia','#gastosporcobrar','#pagoscreditos'],2);"
                                    id="mercaderia" placeholder="Compra de mercadería">
                                <label for="mercaderia">Compra de mercadería</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control"
                                    onkeyup="sumar_valores_inputs('#egresostotal',['#mercaderia','#gastosporcobrar','#pagoscreditos'],[],1); sumar_valores_inputs('#saldo',['#ventas','#cporcobrar'],['#mercaderia','#gastosporcobrar','#pagoscreditos'],2);"
                                    id="gastosporcobrar" placeholder="Gastos del negocio">
                                <label for="gastosporcobrar">Gastos del negocio</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating mb-2 mt-1">
                                <input type="number" step="0.01" class="form-control"
                                    onkeyup="sumar_valores_inputs('#egresostotal',['#mercaderia','#gastosporcobrar','#pagoscreditos'],[],1); sumar_valores_inputs('#saldo',['#ventas','#cporcobrar'],['#mercaderia','#gastosporcobrar','#pagoscreditos'],2);"
                                    id="pagoscreditos" placeholder="Pagos de créditos">
                                <label for="pagoscreditos">Pagos de créditos</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-12 col-md-4 mb-2 border border-primary">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2 mt-2 text-primary">SALDO DISPONIBLE</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-2 mt-2">
                                <input type="text" class="form-control" id="saldo" placeholder="Saldo disponible"
                                    readonly disabled>
                                <label for="saldo">Saldo disponible</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ACTIVO Y PASIVO -->
        <div class="container contenedort" style="max-width: 100% !important;">
            <div class="row">
                <div class="col">
                    <div class="text-center mb-2"><b>Activo, pasivo y saldo</b></div>
                </div>
            </div>
            <div class="row m-1">
                <div class="col-12 col-sm-6 col-md-4 mb-2 border border-primary">
                    <div class="row">
                        <div class="col-12">
                            <div class="text-center mt-2 text-primary">ACTIVO</div>
                        </div>
                        <div class="col-12">
                            <div class="text-center mb-2"><span class="badge text-bg-primary" id="activototal">Q.
                                    0.00</span></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control" id="actcirculante"
                                    placeholder="Activo circulante" readonly disabled>
                                <label for="actcirculante">Activo circulante</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control" id="disponible"
                                    onkeyup="sumar_valores_inputs('#actcirculante',['#disponible','#cuentascobrar','#inventario'],[],2); sumar_valores_inputs('#activototal',['#actcirculante','#activofijo'],[],1); sumar_valores_inputs('#saldo2',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#patrimonio',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#pasivototal',['#pasivo','#patrimonio'],[],1);"
                                    placeholder="Disponible">
                                <label for="disponible">Disponible</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control" id="cuentascobrar"
                                    onkeyup="sumar_valores_inputs('#actcirculante',['#disponible','#cuentascobrar','#inventario'],[],2); sumar_valores_inputs('#activototal',['#actcirculante','#activofijo'],[],1); sumar_valores_inputs('#saldo2',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#patrimonio',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#pasivototal',['#pasivo','#patrimonio'],[],1);"
                                    placeholder="Cuentas por cobrar">
                                <label for="cuentascobrar">Cuentas por cobrar</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control" id="inventario"
                                    onkeyup="sumar_valores_inputs('#actcirculante',['#disponible','#cuentascobrar','#inventario'],[],2); sumar_valores_inputs('#activototal',['#actcirculante','#activofijo'],[],1); sumar_valores_inputs('#saldo2',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#patrimonio',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#pasivototal',['#pasivo','#patrimonio'],[],1);"
                                    placeholder="Inventario">
                                <label for="inventario">Inventario</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-floating mb-2 mt-1">
                                <input type="number" step="0.01" class="form-control" id="activofijo"
                                    onkeyup="sumar_valores_inputs('#actcirculante',['#disponible','#cuentascobrar','#inventario'],[],2); sumar_valores_inputs('#activototal',['#actcirculante','#activofijo'],[],1); sumar_valores_inputs('#saldo2',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#patrimonio',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#pasivototal',['#pasivo','#patrimonio'],[],1);"
                                    placeholder="Activo fijo">
                                <label for="activofijo">Activo fijo</label>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 mb-2 border border-primary">
                    <div class="row">
                        <div class="col-12">
                            <div class="text-center mt-2 text-primary">PASIVO Y PATRIMONIO</div>
                        </div>
                        <div class="col-12">
                            <div class="text-center mb-2"><span class="badge text-bg-primary" id="pasivototal">Q.
                                    0.00</span></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-1 mt-1">
                                <input type="number" step="0.01" class="form-control" id="pasivo" placeholder="Pasivo"
                                    readonly disabled>
                                <label for="pasivo">Pasivo</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating mb-1 mt-1">
                            <input type="number" step="0.01" class="form-control" id="proveedores"
                                onkeyup="sumar_valores_inputs('#pasivo',['#proveedores','#otrosprestamos','#prestamosinstituciones'],[],2); sumar_valores_inputs('#patrimonio',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#pasivototal',['#pasivo','#patrimonio'],[],1); sumar_valores_inputs('#saldo2',['#actcirculante','#activofijo'],['#pasivo','#patrimonio'],2);"
                                placeholder="Proveedores">
                            <label for="proveedores">Proveedores</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating mb-1 mt-2">
                            <input type="number" step="0.01" class="form-control" id="otrosprestamos"
                                onkeyup="sumar_valores_inputs('#pasivo',['#proveedores','#otrosprestamos','#prestamosinstituciones'],[],2);  sumar_valores_inputs('#patrimonio',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#pasivototal',['#pasivo','#patrimonio'],[],1); sumar_valores_inputs('#saldo2',['#actcirculante','#activofijo'],['#pasivo','#patrimonio'],2);"
                                placeholder="Otros préstamos">
                            <label for="otrosprestamos">Otros préstamos</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating mb-1 mt-2">
                            <input type="number" step="0.01" class="form-control" id="prestamosinstituciones"
                                onkeyup="sumar_valores_inputs('#pasivo',['#proveedores','#otrosprestamos','#prestamosinstituciones'],[],2); sumar_valores_inputs('#patrimonio',['#actcirculante','#activofijo'],['#pasivo'],2); sumar_valores_inputs('#pasivototal',['#pasivo','#patrimonio'],[],1); sumar_valores_inputs('#saldo2',['#actcirculante','#activofijo'],['#pasivo','#patrimonio'],2);"
                                placeholder="Préstamos a instituciones">
                            <label for="prestamosinstituciones">Préstamos a instituciones</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating mb-2 mt-2">
                            <input type="number" step="0.01" class="form-control" id="patrimonio"
                                placeholder="Patrimonio">
                            <label for="patrimonio">Patrimonio</label>
                        </div>
                    </div>
                </div>
                <!-- SALDO -->
                <div class="col-12 col-sm-12 col-md-4 mb-2 border border-primary">
                    <div class="row">
                        <div class="col">
                            <div class="text-center mb-2 mt-2 text-primary">SALDO</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-floating mb-2 mt-2">
                                <input type="number" class="form-control" id="saldo2" placeholder="Saldo" readonly
                                    disabled>
                                <label for="saldo2">Saldo</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="max-width: 100% !important;">
        <div class="row justify-items-md-center">
            <div class="col align-items-center mb-3 ms-2" id="modal_footer">
                <!-- Boton de rechazo -->
                <?php if ($bandera && count($datosbalances) <= 2) { ?>
                <button id="save" class="btn btn-outline-success mt-2"
                    onclick="obtiene_plus([`codcli`,`nomcli`,`ventas`,`cporcobrar`,`mercaderia`,`gastosporcobrar`,`pagoscreditos`,`disponible`,`cuentascobrar`,`inventario`,`activofijo`,`proveedores`,`otrosprestamos`,`prestamosinstituciones`,`patrimonio`,`saldo2`,`fecevaluacion`,`fecbalance`],[],[],`create_balance_economico`,'<?= $xtra; ?>',['<?= $codusu; ?>'])"><i
                        class="fa-solid fa-floppy-disk me-2"></i>Guardar</button>
                <?php } else {
                                echo '<div id="save" class="alert alert-danger" role="alert">
                                No se pueden registrar mas balances economicos para el cliente seleccionado</div>';
                            }
                            ?>
                <?php if ($bandera_balance && $bandera) { ?>
                <div id="divbuttons" style="display: none;">
                    <button id="btupdate" class="btn btn-outline-primary mt-2"><i
                            class="fa-solid fa-pen-to-square me-2"></i>Actualizar</button>
                    <button id="btdeclarada" type="button" class="btn btn-outline-primary mt-2">Ficha declarada</button>
                    <button id="btpersonal" type="button" class="btn btn-outline-primary mt-2">Ficha Personal</button>
                    <button id="btdelete" type="button" class="btn btn-outline-danger mt-2"><i
                            class="fa-solid fa-trash me-2"></i>Eliminar</button>
                </div>

                <?php } ?>
                <button type="button" class="btn btn-outline-danger mt-2" onclick="printdiv2('#cuadro','0')">
                    <i class="fa-solid fa-ban"></i> Cancelar
                </button>
                <button type="button" class="btn btn-outline-warning mt-2" onclick="salir()">
                    <i class="fa-solid fa-circle-xmark"></i> Salir
                </button>
            </div>
        </div>
    </div>
</div>
<!-- <div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="pdfModalLabel">Visor de PDF</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <embed id="pdf-viewer" type="application/pdf" width="100%" height="600" />
                        </div>
                    </div>
                </div>
            </div> -->
<?php include_once "../../src/cris_modales/mdls_cli.php"; ?>
<script>
function lodata(idr, codcli) {
    arraybalances = <?php echo json_encode($datosbalances); ?>;
    var registroEncontrado = arraybalances.find(function(registro) {
        return registro.id == idr;
    });
    if (registroEncontrado) {
        console.log("Registro encontrado:", registroEncontrado);
        $("#fecevaluacion").val(registroEncontrado.fechaeval);
        $("#fecbalance").val(registroEncontrado.fechabalance);
        $("#ventas").val(Number(registroEncontrado.ventas).toFixed(2));
        $("#cporcobrar").val(Number(registroEncontrado.cuenta_por_cobrar).toFixed(2));
        $("#mercaderia").val(Number(registroEncontrado.mercaderia).toFixed(2));
        $("#gastosporcobrar").val(Number(registroEncontrado.negocio).toFixed(2));
        $("#pagoscreditos").val(Number(registroEncontrado.pago_creditos).toFixed(2));

        $("#disponible").val(Number(registroEncontrado.disponible).toFixed(2));
        $("#cuentascobrar").val(Number(registroEncontrado.cuenta_por_cobrar2).toFixed(2));
        $("#inventario").val(Number(registroEncontrado.inventario).toFixed(2));
        $("#activofijo").val(Number(registroEncontrado.activo_fijo).toFixed(2));
        $("#proveedores").val(Number(registroEncontrado.proveedores).toFixed(2));
        $("#otrosprestamos").val(Number(registroEncontrado.otros_prestamos).toFixed(2));
        $("#prestamosinstituciones").val(Number(registroEncontrado.prest_instituciones).toFixed(2));
        $("#patrimonio").val(Number(registroEncontrado.patrimonio).toFixed(2));
        sumatorias();
        $("#divbuttons").show();
        $("#save").hide();
        divbuttons(idr, codcli);
    } else {
        alert("No se encontró ningún registro con la indice especificado.", idr);
    }
}

function divbuttons(id, codcli) {
    $("#btupdate").off('click');
    $("#btupdate").click(function() {
        obtiene_plus([`codcli`, `nomcli`, `ventas`, `cporcobrar`, `mercaderia`, `gastosporcobrar`,
            `pagoscreditos`, `disponible`, `cuentascobrar`, `inventario`, `activofijo`, `proveedores`,
            `otrosprestamos`, `prestamosinstituciones`, `patrimonio`, `saldo2`, `fecevaluacion`,
            `fecbalance`
        ], [], [], `update_balance_economico`, codcli, [id]);
    });
    $("#btdeclarada").off('click');
    $("#btdeclarada").click(function() {
        reportes([
            [],
            [],
            [],
            [id]
        ], `pdf`, `balance_economico`, 1);
    });
    $("#btpersonal").off('click');
    $("#btpersonal").click(function() {
        reportes([
            [],
            [],
            [],
            [id]
        ], `pdf`, `balance_economico_personal`, 0);
    });
    $("#btdelete").off('click');
    $("#btdelete").click(function() {
        obtiene_plus([`codcli`, `nomcli`], [], [], `delete_balance_economico`, codcli, [id])
    });
}

function sumatorias() {
    //PRIMERA PARTE
    sumar_valores_inputs('#ingresostotal', ['#ventas', '#cporcobrar'], [], 1);
    sumar_valores_inputs('#ingresostotal', ['#ventas', '#cporcobrar'], [], 1);
    sumar_valores_inputs('#saldo', ['#ventas', '#cporcobrar'], ['#mercaderia', '#gastosporcobrar', '#pagoscreditos'],
        2);
    //SEGUNDA PARTE
    sumar_valores_inputs('#actcirculante', ['#disponible', '#cuentascobrar', '#inventario'], [], 2);
    sumar_valores_inputs('#activototal', ['#actcirculante', '#activofijo'], [], 1);
    sumar_valores_inputs('#pasivo', ['#proveedores', '#otrosprestamos', '#prestamosinstituciones'], [], 2);
    sumar_valores_inputs('#pasivototal', ['#pasivo', '#patrimonio'], [], 1);
    sumar_valores_inputs('#saldo2', ['#actcirculante', '#activofijo'], ['#pasivo', '#patrimonio'], 2);
}
</script>
<?php
        }
        break;
}
?>