<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
    return;
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
include_once __DIR__ . '/../formulas/proteccion.php';

use App\Configuracion;
use Micro\Helpers\Log;


$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];
switch ($condi) {
    case 'p1':
        /*rovisión para préstamos incobrables / provisión requerida para préstamos con morosidad >12 meses*/

        // try {
        //     // $database->openConnection();
        //     // $rows = $database->selectAll('ctb_fuente_fondos');

        // } catch (Exception $e) {
        //     echo "Error: " . $e->getMessage();
        // } finally {
        //     $database->closeConnection();
        // }

?>

    <?php
        break;
    case 'p2':
        /*P2. Provisión neta para préstamos incobrables / provisión requerida para préstamos morosos menor a 12 meses*/

    ?>

    <?php
        break;
    case 'p3':
        /* */

    ?>

    <?php
        break;
    case 'p4':
        /*Préstamos castigados / total cartera de préstamos promedio*/
        $xtra = $_POST["xtra"];
        // echo "<pre>";
        // echo print_r($xtra);
        // echo "</pre>";

        Log::info("Proteccion", [
            'xtra' => $xtra,
        ]);
        $year2 = ($xtra == 0) ? date('Y') : $xtra['year2'];
        $year1 = ($xtra == 0) ? $year2 - 1 : $xtra['year1'];

        $fechaini1 = ($year1 - 1) . '-12-31'; //AL 31 DE DICIEMBRE DEL AÑO ANTERIOR
        $fechafin1 = $year1 . '-12-31'; //AL 31 DE DICIEMBRE DEL AÑO  QUE ESTAMOS REVISANDO

        $fechaini2 = ($year2 - 1) . '-12-31'; //AL 31 DE DICIEMBRE DEL AÑO ANTERIOR
        $fechafin2 = $year2 . '-12-31'; //AL 31 DE DICIEMBRE DEL AÑO  QUE ESTAMOS REVISANDO

        // echo $fechaini1;
        // echo '<br>';
        // echo $fechafin1;
        // echo '<hr>';
        // echo $fechaini2;
        // echo '<br>';
        // echo $fechafin2;
        // return;

        $querygeneral = "SELECT IFNULL(SUM(saldokp),0) AS saldos FROM (
                    SELECT (cremi.NCapDes-IFNULL(kar.sum_KP, 0)) AS saldokp FROM cremcre_meta cremi 
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                    INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
                    INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
                    LEFT JOIN (
                        SELECT ccodcta, SUM(KP) AS sum_KP, SUM(interes) AS sum_interes FROM CREDKAR
                        WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P' GROUP BY ccodcta
                    ) AS kar ON kar.ccodcta = cremi.CCODCTA
                    WHERE (cremi.Cestado='F' OR cremi.Cestado='G') AND cremi.DFecDsbls <= ? AND (NCapDes-IFNULL(kar.sum_KP, 0)) > 0
            ) AS sumasaldos";
        $queryincobrables = "SELECT IFNULL(SUM(saldokp),0) saldos FROM (
            SELECT (cremi.NCapDes-IFNULL(kar.sum_KP, 0)) AS saldokp
            FROM cremcre_meta cremi 
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
            LEFT JOIN (
                SELECT ccodcta, SUM(KP) AS sum_KP, SUM(interes) AS sum_interes FROM CREDKAR
                WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P' GROUP BY ccodcta
            ) AS kar ON kar.ccodcta = cremi.CCODCTA
            WHERE cremi.Cestado='I' AND cremi.DFecDsbls <= ? AND cremi.fecincobrable<= ? AND (NCapDes-IFNULL(kar.sum_KP, 0))>0
            ) AS sumasaldos;";
        try {
            $database->openConnection();
            //AÑO 1
            $result = $database->getSingleResult($queryincobrables, [$fechafin1, $fechafin1, $fechafin1]);
            $a1 = $result['saldos'];
            $result = $database->getSingleResult($queryincobrables, [$fechaini1, $fechaini1, $fechaini1]);
            $b1 = $result['saldos'];
            $result = $database->getSingleResult($querygeneral, [$fechafin1, $fechafin1]);
            $c1 = $result['saldos'];
            $result = $database->getSingleResult($querygeneral, [$fechaini1, $fechaini1]);
            $d1 = $result['saldos'];
            //PRESTAMOS CASTIGADOS = a-b
            $castigados1 = bcdiv((floatval($a1) - floatval($b1)), 1, 4);
            //TOTAL CARTERA PRESTAMOS = (c+d)/2
            $totalcartera1 = bcdiv((floatval($c1) + floatval($d1)), 2, 4);
            //RESULTADO FINANCIERO = castigados1/totalcartera1
            $resultado1 = bcdiv((floatval($castigados1)), (floatval($totalcartera1)), 6);
            // $resultado1 = ($saldocartera1 != 0) ? $saldoincobrable1 / $saldocartera1 : 0;
            // $resultado2 = ($saldocartera2 != 0) ? $saldoincobrable2 / $saldocartera2 : 0;
            // $resultadogeneral = ($saldoincobrable2 - $saldoincobrable1) / (($saldocartera2 + $saldocartera1) / 2);
            //AÑO 2
            $result = $database->getSingleResult($queryincobrables, [$fechafin2, $fechafin2, $fechafin2]);
            $a2 = $result['saldos'];
            $result = $database->getSingleResult($queryincobrables, [$fechaini2, $fechaini2, $fechaini2]);
            $b2 = $result['saldos'];
            $result = $database->getSingleResult($querygeneral, [$fechafin2, $fechafin2]);
            $c2 = $result['saldos'];
            $result = $database->getSingleResult($querygeneral, [$fechaini2, $fechaini2]);
            $d2 = $result['saldos'];
            $database->closeConnection();

            //PRESTAMOS CASTIGADOS = a-b
            $castigados2 = bcdiv((floatval($a2) - floatval($b2)), 1, 4);
            //TOTAL CARTERA PRESTAMOS = (c+d)/2
            $totalcartera2 = bcdiv((floatval($c2) + floatval($d2)), 2, 4);
            //RESULTADO FINANCIERO = castigados1/totalcartera1
            $resultado2 = bcdiv((floatval($castigados2)), (floatval($totalcartera2)), 6);

            $interpretacion = "Al evaluar los préstamos castigados en relación al total de la cartera de préstamos bruta, 
            se evidencia que la cooperativa para el año " . $year1 . " posee un " . $resultado1 . "% y en el año " . $year2 . " presenta un castigo de " . $resultado2 . "; 
            denotándose un" . (($resultado2 > $resultado1) ? " incremento " : " decremento") . " en el periodo " . $year2 . ".";
        } catch (Exception $e) {
            echo "Error al ejecutar la consulta: " . $e->getMessage();
            // Inicializar variables en caso de error para evitar problemas en el HTML
            $a1 = $b1 = $c1 = $d1 = $castigados1 = $totalcartera1 = $resultado1 = 0;
            $a2 = $b2 = $c2 = $d2 = $castigados2 = $totalcartera2 = $resultado2 = 0;
            $interpretacion = "Error al calcular los datos.";
        }
    ?>
        <style>
            @keyframes moveAndRotateFormula {
                0% {
                    transform: translateX(10px) rotate(10deg);
                }

                25% {
                    transform: translateX(10px) rotate(0deg);
                }

                50% {
                    transform: translateX(10px) rotate(-10deg);
                }

                75% {
                    transform: translateX(10px) rotate(0deg);
                }

                100% {
                    transform: translateX(10px) rotate(10deg);
                }
            }

            #formula {
                position: absolute;
                z-index: 1;
                animation: moveAndRotateFormula 0.5s infinite;
                background-color: #33FF33 !important;
                /* Manteniendo el color verde brillante */
                color: #1a202c;
                /* Color de texto oscuro para contraste */
            }

            /* Ajuste para que los gráficos ApexCharts ocupen el espacio */
            .apexcharts-canvas {
                width: 100% !important;
                height: auto !important;
            }
        </style>
        <div class="container mx-auto px-4"> <!-- Reemplaza container -->
            <div class="mb-6 text-center relative"> <!-- Reemplaza row mb-3 y centrado -->
                <span id="nombre" class="inline-block bg-blue-500 text-white text-lg font-semibold px-4 py-2 rounded-full">Préstamos castigados / total cartera de préstamos promedio</span>
                <span id="formula" class="inline-block text-xl font-bold px-5 py-2 rounded-lg mt-2"> <?php echo $P4; ?></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6 mt-16"> <!-- Reemplaza row mt-3 y col-sm-6 -->
                <!-- Ejercicio 1 -->
                <div class="border border-gray-200 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-800 dark:border-gray-700 h-full"> <!-- Reemplaza card text-bg-light -->
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-center font-semibold">EJERCICIO 1</div> <!-- Reemplaza card-header -->
                    <div class="p-4"> <!-- Reemplaza card-body -->
                        <div class="flex items-center space-x-4"> <!-- Reemplaza row -->
                            <label for="year1" class="flex-shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300"> <!-- Reemplaza col-3 y form-label -->
                                <i class="fas fa-calendar-alt mr-1"></i> AÑO:
                            </label>
                            <input type="number" id="year1" name="year1" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo $year1; ?>" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" /> <!-- Reemplaza col-9 y form-control -->
                        </div>
                    </div>
                </div>
                <!-- Ejercicio 2 -->
                <div class="border border-gray-200 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-800 dark:border-gray-700 h-full"> <!-- Reemplaza card text-bg-light -->
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-center font-semibold">EJERCICIO 2</div> <!-- Reemplaza card-header -->
                    <div class="p-4"> <!-- Reemplaza card-body -->
                        <div class="flex items-center space-x-4"> <!-- Reemplaza row -->
                            <label for="year2" class="flex-shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300"> <!-- Reemplaza col-3 y form-label -->
                                <i class="fas fa-calendar-alt mr-1"></i> AÑO:
                            </label>
                            <input type="number" id="year2" name="year2" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo $year2; ?>" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" /> <!-- Reemplaza col-9 y form-control -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center"> <!-- Reemplaza row justify-items-md-center mt-3 -->
                <button type="button" id="btnSave" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" onclick="printdiv('p4', `#cuadro`, `../pearls/views/proteccion`, getinputsval(['year1', 'year2']));"> <!-- Reemplaza btn btn-primary -->
                    <i class="fa-solid fa-circle mr-2"></i> Procesar
                </button>
                <!-- Otros botones si los necesitas -->
            </div>
        </div>

        <div class="container mx-auto px-4 mt-6"> <!-- Reemplaza container mt-3 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"> <!-- Reemplaza row -->
                <!-- Columna Año 1 -->
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4"> <!-- Reemplaza row -->
                        <!-- Card Castigos Acumulados 1 -->
                        <div class="border border-gray-200 rounded-lg shadow-sm dark:border-gray-700"> <!-- Reemplaza card -->
                            <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700"> <!-- Reemplaza card-header -->
                                <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Castigos acumulados</h6>
                            </div>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700"> <!-- Reemplaza list-group list-group-flush -->
                                <li class="px-4 py-3 flex justify-between items-center text-sm"> <!-- Reemplaza list-group-item d-flex ... -->
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">a</span> Ejercicio en curso</div>
                                    <span class="inline-block bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-red-900 dark:text-red-300"><?php echo number_format($a1, 2, '.', ','); ?></span>
                                </li>
                                <li class="px-4 py-3 flex justify-between items-center text-sm">
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">b</span> Ejercicio anterior</div>
                                    <span class="inline-block bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-red-900 dark:text-red-300"><?php echo number_format($b1, 2, '.', ','); ?></span>
                                </li>
                            </ul>
                        </div>
                        <!-- Card Cartera Bruta 1 -->
                        <div class="border border-gray-200 rounded-lg shadow-sm dark:border-gray-700">
                            <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                                <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Cartera de Préstamos Bruta</h6>
                            </div>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <li class="px-4 py-3 flex justify-between items-center text-sm">
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">c</span> Ejercicio en curso</div>
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300"><?php echo number_format($c1, 2, '.', ','); ?></span>
                                </li>
                                <li class="px-4 py-3 flex justify-between items-center text-sm">
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">d</span> Ejercicio anterior</div>
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300"><?php echo number_format($d1, 2, '.', ','); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="text-center mt-4"> <!-- Reemplaza row y col-12 -->
                        <span class="inline-block bg-yellow-100 text-yellow-800 text-lg font-semibold px-4 py-2 rounded-full dark:bg-yellow-900 dark:text-yellow-300">RESULTADO FINAL: <?php echo number_format($resultado1, 5, '.', ','); ?></span> <!-- Reemplaza badge text-bg-warning fs-5 -->
                    </div>
                </div>
                <!-- Columna Año 2 -->
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Card Castigos Acumulados 2 -->
                        <div class="border border-gray-200 rounded-lg shadow-sm dark:border-gray-700">
                            <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                                <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Castigos acumulados</h6>
                            </div>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <li class="px-4 py-3 flex justify-between items-center text-sm">
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">a</span> Ejercicio en curso</div>
                                    <span class="inline-block bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-red-900 dark:text-red-300"><?php echo number_format($a2, 2, '.', ','); ?></span>
                                </li>
                                <li class="px-4 py-3 flex justify-between items-center text-sm">
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">b</span> Ejercicio anterior</div>
                                    <span class="inline-block bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-red-900 dark:text-red-300"><?php echo number_format($b2, 2, '.', ','); ?></span>
                                </li>
                            </ul>
                        </div>
                        <!-- Card Cartera Bruta 2 -->
                        <div class="border border-gray-200 rounded-lg shadow-sm dark:border-gray-700">
                            <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                                <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Cartera de Préstamos Bruta</h6>
                            </div>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <li class="px-4 py-3 flex justify-between items-center text-sm">
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">c</span> Ejercicio en curso</div>
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300"><?php echo number_format($c2, 2, '.', ','); ?></span>
                                </li>
                                <li class="px-4 py-3 flex justify-between items-center text-sm">
                                    <div><span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium mr-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300">d</span> Ejercicio anterior</div>
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300"><?php echo number_format($d2, 2, '.', ','); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <span class="inline-block bg-yellow-100 text-yellow-800 text-lg font-semibold px-4 py-2 rounded-full dark:bg-yellow-900 dark:text-yellow-300">RESULTADO FINAL: <?php echo number_format($resultado2, 5, '.', ','); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-4 mt-6"> <!-- Reemplaza container mt-3 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"> <!-- Reemplaza row -->
                <!-- Gráfico 1 -->
                <div class="border border-gray-200 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-800 dark:border-gray-700 p-4 min-h-[350px]"> <!-- Añadido min-h para espacio -->
                    <div id="chart_div"></div> <!-- Cambiado canvas a div -->
                </div>
                <!-- Gráfico 2 -->
                <div class="border border-gray-200 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-800 dark:border-gray-700 p-4 min-h-[350px]"> <!-- Añadido min-h para espacio -->
                    <div id="chart_div2"></div> <!-- Cambiado canvas a div -->
                </div>
            </div>
        </div>

        <div class="container mx-auto px-4 mt-6 mb-6"> <!-- Reemplaza container mt-3 -->
            <div class="text-center"> <!-- Reemplaza row mb-3 y col-12 -->
                <div class="border border-gray-200 rounded-lg shadow-sm bg-gray-50 dark:bg-gray-800 dark:border-gray-700 p-4 inline-block w-full max-w-4xl"> <!-- Reemplaza card text-bg-light y card-body -->
                    <p class="text-gray-700 dark:text-gray-300 text-justify"><?php echo $interpretacion; ?></p> <!-- Reemplaza card-text -->
                </div>
            </div>
        </div>

        <script>

            // Función para inicializar los gráficos
            function initializeCharts() {
                // Obtener los años
                const year1 = document.getElementById("year1").value;
                const year2 = document.getElementById("year2").value;

                // Destruir gráficos existentes si existen
                if (chart1) chart1.destroy();
                if (chart2) chart2.destroy();

                // --- Gráfico 1 con ApexCharts ---
                var options1 = {
                    series: [{
                        name: 'Resultado',
                        data: [<?php echo $resultado1; ?>, <?php echo $resultado2; ?>]
                    }],
                    chart: {
                        type: 'bar',
                        height: 350, // Altura del gráfico
                        toolbar: {
                            show: true
                        } // Mostrar barra de herramientas (zoom, pan, descarga)
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%', // Ancho de las barras
                            endingShape: 'rounded' // Forma de las barras (opcional)
                        },
                    },
                    dataLabels: {
                        enabled: false // Ocultar etiquetas de datos en las barras
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: [year1, year2], // Etiquetas del eje X
                    },
                    yaxis: {
                        title: {
                            text: 'Valor' // Título del eje Y
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) {
                                return val.toFixed(5) // Formatear tooltip
                            }
                        }
                    },
                    title: {
                        text: 'Protección #4 - Resultado General',
                        align: 'left'
                    },
                    colors: ['#3B82F6', '#10B981'] // Colores para las barras (Tailwind blue-500, emerald-500)
                };

                var chartElement1 = document.querySelector("#chart_div");
                if (chartElement1) {
                    var chart1 = new ApexCharts(chartElement1, options1);
                    chart1.render();
                } else {
                    console.error("Elemento #chart_div no encontrado");
                }
                // --- Gráfico 2 con ApexCharts ---
                var options2 = {
                    series: [{
                        name: year1,
                        data: [<?php echo $castigados1; ?>, <?php echo $totalcartera1; ?>]
                    }, {
                        name: year2,
                        data: [<?php echo $castigados2; ?>, <?php echo $totalcartera2; ?>]
                    }],
                    chart: {
                        type: 'bar',
                        height: 350,
                        toolbar: {
                            show: true
                        }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%',
                            endingShape: 'rounded'
                        },
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        show: true,
                        width: 2,
                        colors: ['transparent']
                    },
                    xaxis: {
                        categories: ['Castigada', 'Bruta'], // Etiquetas del eje X
                    },
                    yaxis: {
                        title: {
                            text: 'Monto (Q)' // Título del eje Y
                        }
                    },
                    fill: {
                        opacity: 1
                    },
                    tooltip: {
                        y: {
                            formatter: function(val) {
                                return "Q " + parseFloat(val).toFixed(2) // Formatear tooltip con moneda
                            }
                        }
                    },
                    title: {
                        text: 'Comparativa por Cartera',
                        align: 'left'
                    },
                    colors: ['#3B82F6', '#10B981'] // Mismos colores para consistencia
                };
                var chartElement2 = document.querySelector("#chart_div2");
                if (chartElement2) {
                    var chart2 = new ApexCharts(chartElement2, options2);
                    chart2.render();
                } else {
                    console.error("Elemento #chart_div2 no encontrado");
                }
            }

            $("document").ready(function() {
                initializeCharts();
            });

            var yearInputs = document.querySelectorAll('#year1, #year2');
            yearInputs.forEach(yearInput => {
                yearInput.addEventListener('input', () => {
                    if (yearInput.value.length > 4) {
                        yearInput.value = yearInput.value.slice(0, 4);
                    }
                });

                yearInput.addEventListener('blur', () => {
                    const currentYear = <?php echo date('Y'); ?>;
                    if (yearInput.value < 1900 || yearInput.value > currentYear) {
                        yearInput.classList.add('border-red-500'); // Ejemplo de validación visual
                        console.error('Ingrese un año valido entre 1900 y el año actual.');
                        // Considera usar setCustomValidity si necesitas validación HTML5
                        // yearInput.setCustomValidity('Ingrese un año valido entre 1900 y el año actual.');
                    } else {
                        yearInput.classList.remove('border-red-500');
                        // yearInput.setCustomValidity('');
                    }
                });
            });
        </script>
    <?php
        break;
    case 'p5':
        /* */

        // try {
        //     // $database->openConnection();
        //     // $rows = $database->selectAll('ctb_fuente_fondos');

        // } catch (Exception $e) {
        //     echo "Error: " . $e->getMessage();
        // } finally {
        //     $database->closeConnection();
        // }

    ?>

<?php
        break;
}

?>