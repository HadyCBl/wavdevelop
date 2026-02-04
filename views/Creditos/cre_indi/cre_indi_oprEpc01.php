<?php
session_start();
///formularios para el modulo de creditos individuales,
// AQUI ESTAN LAS VENTANAS, solicitud, analisis, desembolso
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include '../../../src/funcphp/valida.php';
include '../../../src/funcphp/func_gen.php';
$mtmax = 0;
$condi = $_POST["condi"];
$idusuario = $_SESSION['id'];
$usu = $_SESSION['usu'];
$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];
switch ($condi) {
    case 'restruc_ppg01':
?>
        <div class="card">
            <h5 class="card-header">Restructuración de plan de pago</h5>
            <div class="card-body">
                <form id="formulario">
                    <!-- ini card-->
                    <div class="card border-primary container contenedort mt-3" style="max-width: 100% !important;">
                        <div class="card-header font-weight-bold">
                            <h3>Datos del crédito</h3>
                        </div>
                        <div class="card-body alert alert-primary">
                            <!-- ini fila -->
                            <div class="row">
                                <div class="col-lg-4 col-md-12 mt-2">
                                    <label class="form-label fw-bold">Codígo de crédito</label><br>
                                    <div class="input-group mb-3">
                                        <button class="btn btn-warning" type="button" id="button-addon1" onclick="abrir_modal_cualquiera('#mdl_consulta_cre')">Buscar</button>
                                        <input id="codCre" type="text" class="form-control" placeholder="Credito" aria-label="Example text with button addon" aria-describedby="button-addon1" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-2 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Ciclo</label>
                                        <input id="ciclo" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Fecha de desembolso</label>
                                        <input id="fecDes" class="form-control" type="date" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Desembolsado</label>
                                        <input id="desembolso" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">

                                <div class="col-lg-6 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Cliente</label>
                                        <input id="cliente" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Fecha del ultimo pago</label>
                                        <input id="fecUltPago" class="form-control" type="date" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Saldo</label>
                                        <input id="saldo" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                            </div>
                            <!-- fin fila -->
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <button type="button" class="btn btn-outline-danger" onclick="restruc(1)"><i class="fa-solid fa-wand-magic-sparkles"></i> Restructurar el plan de pago </button>
                        </div>
                    </div>

                    <!-- fin card-->

                    <!-- ini card -->
                    <div class="card border-primary container contenedort mt-3" style="max-width: 100% !important;" id="card1">
                        <div class="row">
                            <input type="text" id="idProduc" hidden readonly>
                            <div class="col mt-3">
                                <button type="button" class="btn btn-outline-primary" onclick="abrir_modal_cualquiera('#mdl_cre_producto')"><i class="fa-solid fa-magnifying-glass"></i> Buscar linea de crédito </button>
                            </div>
                        </div>
                        <div class="card-body alert alert-primary">

                            <div class="row">
                                <div class="col-lg-2 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Codigo del producto</label>
                                        <input id="codProducto" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-5 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Producto</label>
                                        <input id="nomProducto" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-5 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Fuente de fondos</label>
                                        <input id="tipFondo" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>



                            </div>

                            <div class="row">
                                <div class="col-lg-7 col-md-12 mt-2">
                                    <div class="mb-45">
                                        <label class="form-label fw-bold">Descripción</label>
                                        <input id="descript" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Monto Maximo</label>
                                        <input id="montoMax" class="form-control" type="text" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-2 col-md-12 mt-2 alert alert-success">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">%Interes</label>
                                        <input id="interes" class="form-control" type="number" min="0" step="0.01" placeholder="" aria-label="default input example" onblur="restruc(2)">
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                    <!-- fin card -->

                    <!-- ini card-->
                    <div class="card border-primary container contenedort mt-3" style="max-width: 100% !important;" id="card2">


                        <div class="row font-weight-bold text-primary">
                            <div class="col-lg-6 col-md-12 mt-2 d-flex flex-row">
                                <h4>Restructuración</h4>
                            </div>
                            <div class="col-lg-6 col-md-12 mt-2 d-flex flex-row-reverse">
                                <h4>Analista: <?= (isset($usu) ? utf8_decode(($nombre)) . " " . utf8_decode(($apellido)) : "XXX") ?></h4>
                            </div>
                        </div>


                        <div class="card-body alert alert-primary">
                            <!-- ini fila -->
                            <div class="row">

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Saldo</label>
                                        <input id="salRestruturacion" class="form-control" type="number" placeholder="" aria-label="default input example" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Plazo</label>
                                        <input id="plazo" class="form-control" type="number" placeholder="" aria-label="default input example" min=(1)>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Fecha del primer pago</label>
                                        <input id="fecSigPago" class="form-control" type="date" placeholder="" aria-label="default input example">
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <label class="form-label fw-bold">Tipo de credito</label>
                                    <select id="tipocred" class="form-select" aria-label="Default select example" onchange="controlSelect('tipocred')">
                                        <option value="Franc" selected="selectd">Sobre Saldos</option>
                                        <option value="Flat">Nivelada</option>
                                        <option value="Germa">Cap.Fijo,Int.Variable</option>
                                        <option value="Amer">Capital Vencimiento</option>
                                    </select>

                                </div>
                            </div>
                            <!-- fin fila -->

                            <!-- ini fila -->
                            <div class="row">

                                <div class="col-lg-3 col-md-12 mt-2">
                                    <label class="form-label fw-bold">Tipo de periodo</label>
                                    <select id="periodo" class="form-select" aria-label="Default select example">
                                        <option value="1D">Diario</option>
                                        <option value="7D">Semanales</option>
                                        <option value="15D">Quincenal</option>
                                        <option value="14D">Catorcenal</option>
                                        <option value="1M" selected="selectd">Mensual</option>
                                    </select>

                                </div>

                            </div>
                            <!-- fin fila -->

                        </div>

                        <div class="row">
                            <div class="col-lg-6 col-md-12">
                                <button type="button" class="btn btn-outline-danger" onclick="limpiarForm(['formulario'])"><i class="fa-solid fa-trash"></i> Cacelar </button>
                                <button type="button" class="btn btn-outline-primary" onclick="restruc(4)"><i class="fa-regular fa-eye"></i> Vista previa </button>
                                <button type="button" id="btnGua" class="btn btn-outline-success" onclick="restruc(3)"><i class="fa-solid fa-gears"></i> Guardar y generar restructuación </button>
                            </div>
                        </div>

                    </div>
                    <!-- fin card-->
                </form>
            </div>

        </div>

        <!-- INI OTR -->
        <div class="container">
            <div class="row" id="consulta_cre"></div>
            <div class="row" id="consulta_cre_producto"></div>
        </div>
        <!-- INI FIN -->

        <script>
            $(document).ready(function() {
                // Aquí va el código que quieres ejecutar cuando el DOM esté listo
                opInyec(0);
                opInyec(1);
                ocultaHabilita(["card1", "card2"], 0);
                $("#btnGua").prop("disabled", true);
            });
        </script>

<?php
        break;
}
?>