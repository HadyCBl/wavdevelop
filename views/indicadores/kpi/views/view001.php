<?php

use App\Generic\User;
use Micro\Helpers\Log;

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

require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

include __DIR__ . '/../../../../includes/BD_con/db_con.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
date_default_timezone_set('America/Guatemala');

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");

$condi = $_POST["condi"];
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

switch ($condi) {
    case 'categorys':
        $id = $_POST["xtra"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $categorys = $database->selectColumns('kpi_categorys', ['id', 'nombre', 'descripcion', 'monto_maximo', 'monto_minimo'], "estado=1");

            if ($id == 0) {
                $showmensaje = true;
                throw new Exception("Creando nueva categoría");
            }

            $category = $database->selectColumns('kpi_categorys', ['id', 'nombre', 'descripcion', 'monto_maximo', 'monto_minimo'], "estado=1 AND id=?", [$id]);
            if (empty($category)) {
                $showmensaje = true;
                throw new Exception("No se encontró la categoría");
            }
            $category = $category[0];
            $status = true;
        } catch (Exception $e) {
            $status = false;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }
        // echo "<pre>";
        // echo print_r($category);
        // echo "</pre>";
        $title = ($id == 0) ? "Nueva Categoría" : "Actualizacion de categoría ";

    ?>
        <!-- Título Principal -->
        <h2 class="text-2xl font-semibold text-center text-gray-500 dark:text-gray-100 mb-6">Categorías</h2> <!-- Ajustado dark:text-gray-100 -->
        <input type="hidden" value="categorys" id="condi">
        <input type="hidden" value="kpi/views/view001" id="file">

        <!-- Contenedor Principal -->
        <div class="container mx-auto px-4 space-y-8">
            <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 rounded">
                    <p><?= htmlspecialchars($mensaje) ?></p>
                </div>
            <?php endif; ?>
            <!-- Formulario de Edición/Creación -->
            <div class="border border-gray-200 rounded-lg shadow-sm bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6">
                    <h4 class="text-xl font-medium text-gray-600 dark:text-gray-100 mb-4"><?= $title ?></h4>
                    <form id="formCategory" class="space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Nombre -->
                            <div class="col-span-2">
                                <label for="nombre" class="block text-sm font-medium text-gray-500 dark:text-gray-200 mb-1">Nombre</label>
                                <input type="text" class="block validator w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    id="nombre" required value="<?= htmlspecialchars($category['nombre'] ?? '', ENT_QUOTES) ?>">
                            </div>

                            <!-- Descripción -->
                            <div class="col-span-2">
                                <label for="descripcion" class="block text-sm font-medium text-gray-500 dark:text-gray-200 mb-1">Descripción</label>
                                <input type="text" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    id="descripcion" required value="<?= htmlspecialchars($category['descripcion'] ?? '', ENT_QUOTES) ?>">
                            </div>

                            <!-- Monto Mínimo -->
                            <div class="col-span-1">
                                <label for="minimo" class="block text-sm font-medium text-gray-500 dark:text-gray-200 mb-1">Monto Mínimo</label>
                                <input type="number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    id="minimo" step="0.01" required value="<?= htmlspecialchars($category['monto_minimo'] ?? 0, ENT_QUOTES) ?>">
                            </div>

                            <!-- Monto Máximo -->
                            <div class="col-span-1">
                                <label for="maximo" class="block text-sm font-medium text-gray-500 dark:text-gray-200 mb-1">Monto Máximo</label>
                                <input type="number" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    id="maximo" step="0.01" required value="<?= htmlspecialchars($category['monto_maximo'] ?? 0, ENT_QUOTES) ?>">
                            </div>
                        </div>



                        <?php echo $csrf->getTokenField(); ?>
                        <!-- Botones de Acción -->
                        <div class="flex justify-center items-center mt-6 space-x-4">
                            <?php if ($id == "0") : ?>
                                <!-- Ajustado dark:text-green-300 y dark:hover:bg-green-800/30 -->
                                <button onclick="obtiene(['<?= $csrf->getTokenName() ?>','nombre','descripcion','minimo','maximo'],[],[],'ccategory','0',['category'],'NULL',false,'crud_kpi')" type="button" class="inline-flex items-center px-4 py-2 border border-green-500 text-sm font-medium rounded-md shadow-sm text-green-500 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:text-green-300 dark:border-green-600 dark:hover:bg-green-800/30">
                                    <i class="fa fa-floppy-disk mr-2"></i> Guardar
                                </button>
                            <?php else : ?>
                                <!-- Ajustado dark:text-blue-300 y dark:hover:bg-blue-800/30 -->
                                <button onclick="obtiene(['<?= $csrf->getTokenName() ?>','nombre','descripcion','minimo','maximo'],[],[],`ucategory`,'0',['<?= htmlspecialchars($secureID->encrypt($id)) ?>'],'NULL',false,'crud_kpi')" type="button" class="inline-flex items-center px-4 py-2 border border-blue-500 text-sm font-medium rounded-md shadow-sm text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:text-blue-300 dark:border-blue-600 dark:hover:bg-blue-800/30">
                                    <i class="fa fa-floppy-disk mr-2"></i> Actualizar
                                </button>
                            <?php endif; ?>
                            <!-- Ajustado dark:text-red-300 y dark:hover:bg-red-800/30 -->
                            <button type="button" id="undo" class="inline-flex items-center px-4 py-2 border border-red-500 text-sm font-medium rounded-md shadow-sm text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:text-red-300 dark:border-red-600 dark:hover:bg-red-800/30" onclick="printdiv2(`#cuadro`,'0')">
                                <i class="fa fa-ban mr-2"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Categorías Existentes -->
            <div class="border border-gray-200 rounded-lg shadow-sm bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Categorías Existentes</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <!-- table header start -->
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <th class="px-5 py-3 sm:px-6">
                                        <div class="flex items-center">
                                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                #
                                            </p>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 sm:px-6">
                                        <div class="flex items-center">
                                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                NOMBRE
                                            </p>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 sm:px-6">
                                        <div class="flex items-center">
                                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                DESCRIPCION
                                            </p>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 sm:px-6">
                                        <div class="flex items-center">
                                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                MÍNIMO
                                            </p>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 sm:px-6">
                                        <div class="flex items-center">
                                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                MÁXIMO
                                            </p>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 sm:px-6">
                                        <div class="flex items-center">
                                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">
                                                ACCIONES
                                            </p>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <!-- table header end -->
                            <!-- table body start -->
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <?php if (!empty($categorys)) : ?>
                                    <?php foreach ($categorys as $key => $categ) : ?>
                                        <tr>
                                            <td class="px-5 py-4 sm:px-6">
                                                <div class="flex items-center">
                                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                        <?= $key + 1 ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 sm:px-6">
                                                <div class="flex items-center">
                                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                        <?= htmlspecialchars($categ["nombre"], ENT_QUOTES) ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 sm:px-6">
                                                <div class="flex items-center">
                                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                        <?= htmlspecialchars($categ["descripcion"], ENT_QUOTES) ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 sm:px-6">
                                                <div class="flex items-center">
                                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                        <?= number_format($categ["monto_minimo"], 2) ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 sm:px-6">
                                                <div class="flex items-center">
                                                    <p class="text-gray-500 text-theme-sm dark:text-gray-400">
                                                        <?= number_format($categ["monto_maximo"], 2) ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-5">
                                                    <button title="Editar" onclick="printdiv2(`#cuadro`, <?= $categ['id'] ?>)"
                                                        class="inline-flex items-center gap-2 px-4 py-3 text-sm font-medium text-white transition rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600">
                                                        Editar
                                                    </button>

                                                    <button title="Eliminar"
                                                        onclick="obtiene(['<?= $csrf->getTokenName() ?>'],[],[],`dcategory`,'0',['<?= htmlspecialchars($secureID->encrypt($categ['id'])) ?>'],'NULL','Está seguro de eliminar esta categoría?','crud_kpi')"
                                                        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]">
                                                        Eliminar
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No hay categorías registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                startValidationGeneric('#formCategory');
            });
        </script>
    <?php
        break;
    case 'bonifications':
        $xtra = $_POST["xtra"];

        echo "for bonifications " . $xtra;
    ?>

    <?php
        //case add ejecutivos 
        break;

    case 'add_ejecutivos':
        $xtra = $_POST["xtra"];
    ?>
        <div class="px-6 py-4 lg:px-8 space-y-8 dark:bg-gray-900">
            <!-- Card: Asignación de Ejecutivos -->
            <div class="bg-white rounded-2xl shadow-md overflow-hidden dark:bg-gray-800 dark:border-gray-700">
                <div class="px-6 py-4 bg-indigo-600 dark:bg-indigo-700">
                    <h5 class="text-lg font-semibold text-white">
                        <i class="fas fa-user-plus mr-2"></i>Asignación de Ejecutivos
                    </h5>
                </div>
                <div class="p-6">
                    <form id="form_ejecutivos">
                        <!-- Campos ocultos (inamovibles) -->
                        <input type="hidden" id="condi" name="condi" value="add_ejecutivos">
                        <input type="hidden" id="file" name="file" value="view001">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Selector de Usuario -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <h6 class="text-sm font-medium text-gray-500 dark:text-gray-300 mb-2">
                                    <i class="fas fa-user mr-2"></i>Selección de Usuario
                                </h6>
                                <label for="ccodcta" class="block text-sm text-gray-700 dark:text-gray-200 mb-1">Usuario</label>
                                <select
                                    id="ccodcta" name="ccodcta"
                                    onchange="updateResumen()"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white">
                                    <option value="0" disabled selected>Seleccione un usuario</option>
                                    <?php
                                    $stmt = $conexion->prepare("SELECT id_usu, nombre, apellido FROM tb_usuario WHERE estado = ?");
                                    $estado = '1';
                                    $stmt->bind_param("s", $estado);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        while ($fila = $result->fetch_assoc()) {
                                            $idUsuario      = $fila['id_usu'];
                                            $nombreCompleto = htmlspecialchars("{$fila['nombre']} {$fila['apellido']}");
                                            echo "<option value=\"$idUsuario\">$nombreCompleto</option>";
                                        }
                                    } else {
                                        echo '<option value="">No hay usuarios disponibles</option>';
                                    }
                                    $stmt->close();
                                    ?>
                                </select>
                            </div>

                            <!-- Roles y Salario -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <h6 class="text-sm font-medium text-gray-500 dark:text-gray-300 mb-2">
                                    <i class="fas fa-user-tag mr-2"></i>Configuración del Rol
                                </h6>
                                <label for="rol" class="block text-sm text-gray-700 dark:text-gray-200 mb-1">Rol</label>
                                <select
                                    id="rol" name="rol"
                                    onchange="updateResumen()"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white">
                                    <option value="0" disabled selected>Seleccione un rol</option>
                                    <option value="1">Ejecutivo</option>
                                </select>

                                <label for="salario" class="block text-sm text-gray-700 dark:text-gray-200 mt-4 mb-1">Salario Base</label>
                                <input
                                    type="number"
                                    id="salario" name="salario"
                                    oninput="updateResumen()"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                                    placeholder="Ingrese el salario" />
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                            <h6 class="text-sm font-medium text-gray-500 dark:text-gray-300 mb-2">
                                <i class="fas fa-clipboard-list mr-2"></i>Resumen de Asignación
                            </h6>
                            <input
                                type="text"
                                id="resumen" name="resumen"
                                readonly
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-800 dark:border-gray-500 dark:placeholder-gray-400 dark:text-white"
                                placeholder="Detalles de la Asignación" />
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex justify-center items-center mt-6 space-x-4">
                            <button
                                type="button"
                                onclick="verifi_ejec()"
                                class="inline-flex items-center px-4 py-2 border border-green-500 text-sm font-medium rounded-md shadow-sm text-green-500 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:text-green-300 dark:border-green-600 dark:hover:bg-green-800/30">
                                <i class="fa fa-floppy-disk mr-2"></i> Guardar
                            </button>
                            <button
                                type="button"
                                onclick="salir()"
                                class="inline-flex items-center px-4 py-2 border border-red-500 text-sm font-medium rounded-md shadow-sm text-red-500 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:text-red-300 dark:border-red-600 dark:hover:bg-red-800/30">
                                <i class="fa fa-ban mr-2"></i> Cancelar
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Tabla: Ejecutivos Asignados -->
            <div class="border border-gray-200 rounded-lg shadow-sm bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Ejecutivos Asignados</h3>
                    <div class="overflow-x-auto">
                        <table id="tabla_ejecutivos" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Apellido</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Salario</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                $('#tabla_ejecutivos').DataTable({
                    ajax: {
                        url: "../../../src/cruds/crud_kpi.php",
                        type: "POST",
                        data: {
                            condi: "table_ejecutivos"
                        },
                        dataSrc: function(json) {
                            if (json.error) {
                                Swal.fire({
                                    icon: 'error',
                                    title: '¡ERROR!',
                                    text: json.message
                                });
                                return [];
                            }
                            return json.data;
                        }
                    },
                    columns: [{
                            data: "id"
                        },
                        {
                            data: "nombre"
                        },
                        {
                            data: "apellido"
                        },
                        {
                            data: "salario"
                        }
                    ]
                });
            });

            function updateResumen() {
                const uSel = document.getElementById('ccodcta');
                const uText = uSel.options[uSel.selectedIndex]?.text || '';
                const rSel = document.getElementById('rol');
                const rText = rSel.options[rSel.selectedIndex]?.text || '';
                const sal = document.getElementById('salario').value;
                const salFmt = sal ?
                    Number(sal).toLocaleString('es-GT', {
                        style: 'currency',
                        currency: 'GTQ'
                    }) :
                    '';
                document.getElementById('resumen').value =
                    `Usuario: ${uText} | Rol: ${rText} | Salario: ${salFmt}`;
            }


            function verifi_ejec() {
                var selectUsuario = document.getElementById("ccodcta");
                var selectRol = document.getElementById("rol");
                var selectSalario = document.getElementById("salario");
                var usuarioSeleccionado = selectUsuario.value;
                var rolSeleccionado = selectRol.value;
                var salarioSeleccionado = selectSalario.value;

                if (usuarioSeleccionado === "0" || rolSeleccionado === "0" || salarioSeleccionado === "" || salarioSeleccionado === "0") {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Campos vacíos',
                        text: 'Todos los campos son obligatorios.'
                    });
                    return;
                }

                $.ajax({
                    url: "../../src/cruds/crud_kpi.php",
                    type: "POST",
                    data: {
                        condi: 'add_eject',
                        usuario: usuarioSeleccionado,
                        rol: rolSeleccionado,
                        salario: salarioSeleccionado
                    },
                    beforeSend: function() {
                        loaderefect(1);
                    },
                    success: function(data) {
                        // console.log(data); // Ver la respuesta del servidor
                        const data2 = JSON.parse(data);
                        if (data2.status == 1) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Muy Bien!',
                                text: data2.message
                            });
                            $('#tabla_ejecutivos').DataTable().ajax.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: data2.message
                            });
                        }
                    },
                    complete: function() {
                        loaderefect(0);
                    }
                });
            }

            function salir() {
                history.back();
            }
        </script>
    <?php
        break;

    case 'add_poa':

        $extra = $_POST["xtra"];

        // Asegúrate de que estas variables estén definidas, incluso si $extra no es un array al principio
        $selected_year = date('Y');
        $selected_executive = null;
        $selected_month = date('n');

        $meses_list = [
            '1' => 'Enero',
            '2' => 'Febrero',
            '3' => 'Marzo',
            '4' => 'Abril',
            '5' => 'Mayo',
            '6' => 'Junio',
            '7' => 'Julio',
            '8' => 'Agosto',
            '9' => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre'
        ];

        // Log::info("Ejecutando consulta con año: $selected_year, ejecutivo: $selected_executive, mes: $selected_month");
        // Log::info("datos post: ", [json_encode($_POST)]);
        $query = "SELECT te.id_usuario, tu.nombre, tu.apellido FROM tb_ejecutivos te INNER JOIN tb_usuario tu ON te.id_usuario = tu.id_usu";

        $showmensaje = false;
        try {
            $database->openConnection();
            if (is_array($extra) && $extra[1] == 0) {
                $showmensaje = true;
                $extra = 0;
                $usuarios = $database->getAllResults($query);
                throw new Exception("Seleccione un Ejecutivo");
            }

            if (is_array($extra)) {
                $selected_year = $extra[0] ?? date('Y');
                $selected_executive = $extra[1] ?? null;

                $queryPoa = "SELECT hed.`year`,hed.id_ejecutivo,mes,cartera_creditos,clientes,cancel,grupos,colocaciones
                        FROM kpi_poa_header hed 
                        INNER JOIN kpi_poa poa ON poa.id_poa=hed.id 
                        WHERE hed.`year`=? AND hed.id_ejecutivo=? AND hed.estado=1";
                $poaIngresada = $database->getAllResults($queryPoa, [$selected_year, $selected_executive]);

                if (empty($poaIngresada)) {
                    $query = "SELECT * FROM kpi_poa_aux WHERE id_ejecutivo = ? AND `year`=? ORDER BY mes DESC LIMIT 1";
                    $poaAux = $database->getSingleResult($query, [$selected_executive, $selected_year - 1]);
                }

                $ejecutivoSeleccionado = new User($selected_executive);

                // $extra = json_encode($extra);
                //traer los datos de la poa, si existe si no dejarla tabla vacia
            } else {
                $usuarios = $database->getAllResults($query);
                if (empty($usuarios)) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron usuarios");
                }
            }

            $status = true;
        } catch (Exception $e) {
            $status = false;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

        $cartera_aux = (empty($poaAux)) ? 0 : $poaAux['cartera_creditos'];
        $clientes_aux = (empty($poaAux)) ? 0 : $poaAux['clientes'];
        $grupos_aux = (empty($poaAux)) ? 0 : $poaAux['grupos'];
        // $colocaciones = (empty($poaAux)) ? 0 : $poaAux['colocaciones'];
        $mesAux = (empty($poaAux)) ? 12 : $poaAux['mes'];
    ?>
        <input type="hidden" id="condi" value="add_poa" />
        <input type="hidden" id="file" value="kpi/views/view001" />
        <div class="container mx-auto px-4 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-200">
                        <i class="fas fa-calendar-alt mr-2"></i>Registro de POA
                    </h2>
                </div>

                <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                    <div class="p-4">
                        <div class="alert alert-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><?= htmlspecialchars($mensaje) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="p-6">
                    <?php if (is_array($extra)): ?>
                        <div class="grid grid-cols-1 gap-6">
                            <!-- Vista para mostrar datos del usuario y POA -->
                            <div class="space-y-6">
                                <!-- Información del Usuario -->
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Información del Ejecutivo</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Nombre:</label>
                                            <p class="text-gray-900 dark:text-gray-100"><?= htmlspecialchars($ejecutivoSeleccionado->getNombre() ?? '') ?></p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Apellido:</label>
                                            <p class="text-gray-900 dark:text-gray-100"><?= htmlspecialchars($ejecutivoSeleccionado->getApellido() ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>

                                <?php if (empty($poaIngresada)): ?>
                                    <!-- Formulario para nuevo POA -->
                                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                                        <form id="formPOA" class="space-y-6">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <!-- Cartera de Créditos -->
                                                <div class="form-control w-full">
                                                    <label class="label" for="cartera">
                                                        <span class="label-text text-gray-700 dark:text-gray-200">Crecimiento Cartera de Créditos</span>
                                                    </label>
                                                    <input type="number" id="cartera" name="cartera" class="input input-bordered w-full" step="0.01" required>
                                                </div>

                                                <!-- Crecimiento Clientes -->
                                                <div class="form-control w-full">
                                                    <label class="label" for="clientes">
                                                        <span class="label-text text-gray-700 dark:text-gray-200">Crecimiento Clientes</span>
                                                    </label>
                                                    <input type="number" id="clientes" name="clientes" class="input input-bordered w-full" required>
                                                </div>

                                                <!-- Crecimiento Grupos -->
                                                <div class="form-control w-full">
                                                    <label class="label" for="grupos">
                                                        <span class="label-text text-gray-700 dark:text-gray-200">Crecimiento Grupos</span>
                                                    </label>
                                                    <input type="number" id="grupos" name="grupos" class="input input-bordered w-full" required>
                                                </div>

                                                <!-- Meses a Calcular -->
                                                <div class="form-control w-full">
                                                    <label class="label" for="meses">
                                                        <span class="label-text text-gray-700 dark:text-gray-200">Meses a Calcular</span>
                                                    </label>
                                                    <select id="meses" name="meses" class="select select-bordered w-full" required>
                                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                                            <option value="<?= $i ?>" <?= $i == 12 ? 'selected' : '' ?>><?= $i ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Cálculo Automático -->
                                            <div class="form-control w-full flex flex-row items-center gap-4 mt-2">
                                                <label class="label cursor-pointer mb-0" for="calculoAutomatico">
                                                    <span class="label-text text-gray-700 dark:text-gray-200">Cálculo Automático</span>
                                                </label>
                                                <input type="checkbox" id="calculoAutomatico" class="toggle toggle-primary" checked>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
                                        <div class="overflow-x-auto">
                                            <div class="badge badge-warning">Datos año anterior (<?= $selected_year - 1 ?>)</div>
                                            <table class="table" id="tabla_poa_aux">
                                                <thead>
                                                    <tr>
                                                        <th>Mes</th>
                                                        <th>Cartera</th>
                                                        <th>Clientes</th>
                                                        <th>Grupos</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <?php
                                                        echo "<tr>";
                                                        echo "<td>" . htmlspecialchars($meses_list[$mesAux]) . "</td>";
                                                        echo "<td><input type='number' name='cartera_ant' class='input input-bordered input-sm w-full cartera-input' value='" . number_format($cartera_aux, 2, '.', '') . "' step='0.01'></td>";
                                                        echo "<td><input type='number' name='clientes_ant' class='input input-bordered input-sm w-full clientes-input' value='" . round($clientes_aux) . "'></td>";
                                                        echo "<td><input type='number' name='grupos_ant' class='input input-bordered input-sm w-full grupos-input' value='" . round($grupos_aux) . "'></td>";
                                                        echo "</tr>";
                                                        ?>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Mensaje de POA ya ingresada -->
                                    <div class="alert alert-info">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span>Los datos del POA ya han sido ingresados para el año <?= htmlspecialchars($selected_year) ?>.</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Tabla de POA -->
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-x-auto">
                                    <div class="badge badge-success">Proyeccion año <?= $selected_year ?></div>
                                    <table class="table table-zebra w-full" id="tabla_poa">
                                        <thead>
                                            <tr>
                                                <th>Mes</th>
                                                <th>Cartera</th>
                                                <th>Clientes</th>
                                                <th>Grupos</th>
                                                <th>Cancel. Anticipadas</th>
                                                <th>Colocaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if (!empty($poaIngresada)) {
                                                foreach ($poaIngresada as $poa) {
                                                    echo "<tr>";
                                                    echo "<td>" . htmlspecialchars($meses_list[$poa['mes']]) . "</td>";
                                                    echo "<td>" . number_format($poa['cartera_creditos'], 2) . "</td>";
                                                    echo "<td>" . htmlspecialchars($poa['clientes']) . "</td>";
                                                    echo "<td>" . htmlspecialchars($poa['grupos']) . "</td>";
                                                    echo "<td>" . number_format($poa['cancel'] ?? 0, 2) . "</td>";
                                                    echo "<td>" . number_format($poa['colocaciones'], 2) . "</td>";
                                                    echo "</tr>";
                                                }
                                            } else {

                                                // Inicializar acumulativos
                                                $cartera_acumulada = $cartera_aux;
                                                $clientes_acumulados = $clientes_aux;
                                                $grupos_acumulados = $grupos_aux;

                                                $meses = [
                                                    'Enero',
                                                    'Febrero',
                                                    'Marzo',
                                                    'Abril',
                                                    'Mayo',
                                                    'Junio',
                                                    'Julio',
                                                    'Agosto',
                                                    'Septiembre',
                                                    'Octubre',
                                                    'Noviembre',
                                                    'Diciembre'
                                                ];
                                                $numMeses = 12; // Por defecto 10 meses
                                                $cartera_mensual = 0;
                                                $clientes_mensual = 0;
                                                $grupos_mensual = 0;

                                                for ($i = 0; $i < $numMeses; $i++) {
                                                    $cartera_acumulada += $cartera_mensual;
                                                    $clientes_acumulados += $clientes_mensual;
                                                    $grupos_acumulados += $grupos_mensual;

                                                    echo "<tr>";
                                                    echo "<td>" . $meses[$i] . "</td>";
                                                    echo "<td><input type='number' name='cartera_$i' class='input input-bordered input-sm w-full cartera-input' value='" . number_format($cartera_acumulada, 2, '.', '') . "' step='0.01'></td>";
                                                    echo "<td><input type='number' name='clientes_$i' class='input input-bordered input-sm w-full clientes-input' value='" . round($clientes_acumulados) . "'></td>";
                                                    echo "<td><input type='number' name='grupos_$i' class='input input-bordered input-sm w-full grupos-input' value='" . round($grupos_acumulados) . "'></td>";
                                                    echo "<td><input type='number' name='colocaciones_$i' class='input input-bordered input-sm w-full colocaciones-input' value='0' step='0.01'></td>";
                                                    echo "<td><input type='number' name='cancel_$i' class='input input-bordered input-sm w-full cancel-input' value='0' step='0.01'></td>";
                                                    echo "</tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Botones de Acción -->
                                <div class="flex justify-end space-x-4">
                                    <?php if (empty($poaIngresada)): ?>
                                        <button type="button" class="btn btn-primary" onclick="guardarPOA(<?= $selected_executive ?>,'<?= $selected_year ?>')">
                                            <i class="fas fa-save mr-2"></i>Guardar
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-ghost" onclick="printdiv(`add_poa`, `#cuadro`, `../kpi/views/view001`, `0`)">
                                        <i class="fas fa-times mr-2"></i>Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Formulario inicial PARA SELECCIONAR USUARIO Y AÑO A TRABAJAR-->
                        <div class="card bg-base-100 shadow-xl">
                            <div class="card-body">
                                <form class="space-y-6">
                                    <input type="hidden" id="condi" value="add_poa">
                                    <input type="hidden" id="file" value="view001">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Ejecutivo -->
                                        <div class="form-control w-full">
                                            <label class="label" for="selectEjecutivo">
                                                <span class="label-text">Ejecutivo</span>
                                                <span class="label-text-alt text-error">*</span>
                                            </label>
                                            <select id="selectEjecutivo" class="select select-bordered w-full">
                                                <option value="0" disabled selected>Seleccione un Ejecutivo</option>
                                                <?php foreach ($usuarios as $usuario): ?>
                                                    <option value="<?= $usuario['id_usuario'] ?>">
                                                        <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Año -->
                                        <div class="form-control w-full">
                                            <label class="label" for="filtro_anio">
                                                <span class="label-text">Año</span>
                                                <span class="label-text-alt text-error">*</span>
                                            </label>
                                            <select id="filtro_anio" class="select select-bordered w-full">
                                                <?php
                                                $anioActual = date('Y');
                                                for ($anio = $anioActual - 5; $anio <= $anioActual; $anio++) {
                                                    $selected = ($anio == $anioActual) ? 'selected' : '';
                                                    echo "<option value=\"$anio\" $selected>$anio</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="divider">Datos del POA</div>

                                    <!-- Botones de acción -->
                                    <div class="flex justify-center space-x-4">
                                        <button type="button"
                                            class="btn btn-primary"
                                            onclick="printdiv(`add_poa`, `#cuadro`, `../kpi/views/view001`, [getselectsval(['filtro_anio']).filtro_anio, getselectsval(['selectEjecutivo']).selectEjecutivo])">
                                            <i class="fas fa-save mr-2"></i>Procesar
                                        </button>
                                        <button type="button"
                                            class="btn btn-ghost"
                                            onclick="printdiv2()">
                                            <i class="fas fa-times mr-2"></i>Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            $("document").ready(function() {
                const carteraInput = document.getElementById('cartera');
                const clientesInput = document.getElementById('clientes');
                const gruposInput = document.getElementById('grupos');
                const mesesSelect = document.getElementById('meses');
                const calculoAutomaticoToggle = document.getElementById('calculoAutomatico');
                const carteraAntInput = document.querySelector('input[name="cartera_ant"]');
                const clientesAntInput = document.querySelector('input[name="clientes_ant"]');
                const gruposAntInput = document.querySelector('input[name="grupos_ant"]');

                async function actualizarTabla() {
                    const cartera = parseFloat(carteraInput?.value) || 0;
                    const clientes = parseInt(clientesInput?.value) || 0;
                    const grupos = parseInt(gruposInput?.value) || 0;
                    const numMeses = parseInt(mesesSelect?.value) || 10;
                    const isAutomatico = calculoAutomaticoToggle?.checked;

                    // Actualizar filas de la tabla según el número de meses
                    const tbody = document.querySelector('#tabla_poa tbody');
                    if (!tbody) return;

                    tbody.innerHTML = ''; // Limpiar tabla existente

                    const meses = [
                        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                    ];

                    // Calcular valores mensuales si está en modo automático
                    const carteraMensual = isAutomatico ? parseFloat((cartera / numMeses).toFixed(2)) : 0;
                    const clientesMensual = isAutomatico ? clientes / numMeses : 0;
                    const gruposMensual = isAutomatico ? grupos / numMeses : 0;
                    // console.log(carteraMensual, clientesMensual, gruposMensual);

                    // Obtener valores iniciales
                    // Obtener valores iniciales desde los inputs de la tabla superior
                    let carteraAcumulada = parseFloat(carteraAntInput?.value) || 0;
                    let clientesAcumulados = parseFloat(clientesAntInput?.value) || 0;
                    let gruposAcumulados = parseFloat(gruposAntInput?.value) || 0;

                    let recuperaciones = carteraAcumulada;
                    let colocaciones = 0;

                    // console.log(carteraAcumulada);

                    // Generar filas
                    for (let i = 0; i < numMeses; i++) {
                        if (isAutomatico) {
                            recuperaciones = +(carteraAcumulada / numMeses).toFixed(2);
                            carteraAcumulada += carteraMensual;
                            clientesAcumulados += clientesMensual;
                            gruposAcumulados += gruposMensual;
                            colocaciones = recuperaciones + carteraMensual; //se tiene que sumar las cancelaciones anticipadas
                        }
                        // console.log(recuperaciones);

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><input type='hidden' name='mes_${i}' value='${i}'>${meses[i]}</td>
                            <td><input type='number' name='cartera_${i}' class='input input-bordered input-sm w-full cartera-input' 
                                value='${isAutomatico ? carteraAcumulada.toFixed(2) : 0}' step='0.01' 
                                ${isAutomatico ? '' : ''}></td>
                            <td><input type='number' name='clientes_${i}' class='input input-bordered input-sm w-full clientes-input' 
                                value='${isAutomatico ? Math.round(clientesAcumulados) : 0}' 
                                ${isAutomatico ? '' : ''}></td>
                            <td><input type='number' name='grupos_${i}' class='input input-bordered input-sm w-full grupos-input' 
                                value='${isAutomatico ? Math.round(gruposAcumulados) : 0}' 
                                ${isAutomatico ? '' : ''}></td>
                            <td><input type='number' name='cancel_${i}' class='input input-bordered input-sm w-full cancel-input' 
                                value='0' step='0.01' onchange="actualizarColocaciones(${i},${recuperaciones},${carteraMensual})"></td>
                            <td><input type='number' name='colocaciones_${i}' class='input input-bordered input-sm w-full colocaciones-input' 
                                value='${isAutomatico ? colocaciones.toFixed(2) : 0}' step='0.01' ></td>
                        `;
                        tbody.appendChild(tr);
                    }
                }

                // Agregar event listeners
                [carteraInput, clientesInput, gruposInput, carteraAntInput, clientesAntInput, gruposAntInput].forEach(input => {
                    input?.addEventListener('input', actualizarTabla);
                });

                mesesSelect?.addEventListener('change', actualizarTabla);
                calculoAutomaticoToggle?.addEventListener('change', function() {
                    actualizarTabla();
                });

                // Inicializar la tabla
                if (calculoAutomaticoToggle?.checked) actualizarTabla();
            });
            // Agregar función para actualizar colocaciones
            function actualizarColocaciones(index, recuperaciones, carteraMensual) {
                const cancelInput = document.querySelector(`input[name="cancel_${index}"]`);
                const colocacionesInput = document.querySelector(`input[name="colocaciones_${index}"]`);
                const recuperacionesBase = parseFloat(recuperaciones) || 0;
                const carteraMensualBase = parseFloat(carteraMensual) || 0;
                const cancelValue = parseFloat(cancelInput.value) || 0;

                // Calcular nuevo valor de colocaciones
                const nuevoValorColocaciones = recuperacionesBase + carteraMensualBase + cancelValue;

                // Actualizar el valor en el input de colocaciones
                colocacionesInput.value = nuevoValorColocaciones.toFixed(2);
            }
            // Agregar funciones auxiliares para el cálculo
            function obtenerValorNumerico(valor) {
                return parseFloat(valor) || 0;
            }

            function guardarPOA(ejecutivo, year) {
                // const tbody = document.querySelector('#tabla_poa tbody');
                // if (!tbody) return;
                const filas = document.querySelectorAll("#tabla_poa tbody tr");
                if (!filas) return;

                let datosActualizados = [];
                let validacionExitosa = true;
                const regexNumero = /^\d+(\.\d+)?$/;

                filas.forEach((fila, index) => {
                    const mes = fila.querySelector(`input[name="mes_${index}"]`).value.trim();
                    const cartera_creditos = fila.querySelector(`input[name="cartera_${index}"]`).value.trim();
                    const clientes = fila.querySelector(`input[name="clientes_${index}"]`).value.trim();
                    const grupos = fila.querySelector(`input[name="grupos_${index}"]`).value.trim();
                    const colocaciones = fila.querySelector(`input[name="colocaciones_${index}"]`).value.trim();
                    const cancel = fila.querySelector(`input[name="cancel_${index}"]`).value.trim();

                    if (!regexNumero.test(cartera_creditos) ||
                        !regexNumero.test(clientes) ||
                        !regexNumero.test(grupos) ||
                        !regexNumero.test(colocaciones) ||
                        !regexNumero.test(cancel)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error en los datos',
                            text: `Por favor verifica los campos de la fila ${index + 1} (Unicamente numeros)`,
                            confirmButtonText: 'Aceptar'
                        });
                        validacionExitosa = false;
                        return;
                    }

                    datosActualizados.push({
                        mes,
                        cartera_creditos,
                        clientes,
                        grupos,
                        colocaciones,
                        cancel
                    });
                });

                // console.log(datosActualizados);
                // return;

                obtiene([], [], [], 'add_poa', '0', [ejecutivo, year, datosActualizados], function(data) {
                    // console.log(data);
                }, 'Está seguro de guardar la informacion de la tabla?', 'crud_kpi')
            }
        </script>
    <?php
        break;

    case 'poa_origin':
        /**
         * Este es el resumen de cada ejecutivo o agencia
         */
        $extra = $_POST["xtra"];
        $queryData = "WITH poa_con_fecha AS (
            SELECT poah.`year`,poah.id_ejecutivo,mes,cartera_creditos,clientes,cancel,grupos,colocaciones, 
                LAST_DAY(STR_TO_DATE(CONCAT(poah.`year`, '-', poa.mes, '-01'), '%Y-%m-%d')) AS fecha_limite
            FROM kpi_poa poa
            INNER JOIN kpi_poa_header poah ON poa.id_poa = poah.id
            )
            SELECT  
            usu.id_usu, 
            usu.nombre, 
            usu.apellido, 
            poa.mes, 
            poa.cartera_creditos,
            poa.clientes,
            poa.grupos,
            poa.colocaciones,
            -- Créditos INDIVIDUALES
            (
                SELECT COUNT(DISTINCT crem.CodCli)
                FROM cremcre_meta crem
                WHERE crem.TipoEnti = 'INDI'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_clientes_indi,
            -- Créditos GRUPALES
            (
                SELECT COUNT(DISTINCT crem.CCodGrupo)
                FROM cremcre_meta crem
                WHERE crem.TipoEnti = 'GRUP'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_grupos,
            -- Colocaciones
            (
                SELECT SUM(crem.NCapDes)
                FROM cremcre_meta crem
                WHERE crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_colocaciones,
            -- Saldo de cartera: usando función de correlación
            (
                SELECT SUM(cremi.NCapDes - IFNULL(
                (SELECT SUM(k.KP)
                FROM CREDKAR k
                WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                )
                FROM cremcre_meta cremi
                INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                WHERE cremi.Cestado IN ('F', 'G')
                AND cremi.DFecDsbls <= poa.fecha_limite
                AND cremi.CodAnal = usu.id_usu
                AND (cremi.NCapDes - IFNULL(
                    (SELECT SUM(k.KP)
                    FROM CREDKAR k
                    WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                ) > 0
            ) AS saldo_cartera
            FROM tb_ejecutivos eje
            INNER JOIN tb_usuario usu ON usu.id_usu = eje.id_usuario
            LEFT JOIN poa_con_fecha poa ON usu.id_usu = poa.id_ejecutivo AND poa.`year` = ?
            WHERE usu.id_usu=? AND poa.mes=?;";

        // Asegúrate de que estas variables estén definidas, incluso si $extra no es un array al principio
        $selected_year = date('Y');
        $selected_executive = null;
        $selected_month = date('n');

        $meses_list_filtro = [
            '1' => 'Enero',
            '2' => 'Febrero',
            '3' => 'Marzo',
            '4' => 'Abril',
            '5' => 'Mayo',
            '6' => 'Junio',
            '7' => 'Julio',
            '8' => 'Agosto',
            '9' => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre'
        ];

        // Log::info("Ejecutando consulta con año: $selected_year, ejecutivo: $selected_executive, mes: $selected_month");
        // Log::info("datos post: ", [json_encode($_POST)]);

        $showmensaje = false;
        try {
            $database->openConnection();
            $agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'cod_agenc', 'nom_agencia']);
            if (empty($agencias)) {
                $showmensaje = true;
                throw new Exception("No se encontraron agencias");
            }

            $query = "SELECT te.id_usuario, tu.nombre, tu.apellido FROM tb_ejecutivos te INNER JOIN tb_usuario tu ON te.id_usuario = tu.id_usu";
            $usuarios = $database->getAllResults($query);

            if (empty($usuarios)) {
                $showmensaje = true;
                throw new Exception("No se encontraron usuarios");
            }

            if (is_array($extra)) {
                // Si $extra viene con datos, úsalos para preseleccionar los selects
                $selected_year = $extra[0] ?? $selected_year;
                $selected_executive = $extra[1] ?? $selected_executive;
                $selected_month = $extra[2] ?? $selected_month;

                //armar fecha, ultimo dia del mes
                $ultimoDiaMes = date('t', strtotime("$selected_year-$selected_month-01"));
                $fechaLimite = "$selected_year-$selected_month-$ultimoDiaMes";

                // Aquí iría tu lógica para cargar $dataEjecutivo si es necesario basado en $extra
                $dataEjecutivo = $database->getAllResults($queryData, [$selected_year, $selected_executive, $selected_month]);

                /**
                 * CONSULTA DE PRONOSTICOS
                 */
                // Datos para Pronóstico (placeholders o basados en datos actuales si aplica)
                $mesPronosticoNumero = ($selected_month % 12) + 1;
                $anioPronostico = ($selected_month == 12) ? $selected_year + 1 : $selected_year;
                $nombreMesPronostico = htmlspecialchars($meses_list_filtro[$mesPronosticoNumero] ?? 'N/A');

                $proyeccionColocacion = $database->getSingleResult("SELECT IFNULL(SUM(crem.NCapDes),0) colocacion FROM cremcre_meta crem WHERE crem.Cestado IN ('F', 'G') AND MONTH(crem.DFecDsbls) = ? AND YEAR(crem.DFecDsbls) = ? AND crem.CodAnal = ?", [$mesPronosticoNumero, $anioPronostico, $selected_executive]);
                $proyeccionColocacion = $proyeccionColocacion['colocacion'] ?: 0;

                $recuperacionSiguiente = $database->getSingleResult("SELECT IFNULL(SUM(KP),0) recuperacion FROM CREDKAR kar INNER JOIN cremcre_meta crem ON crem.CCODCTA=kar.CCODCTA WHERE crem.CodAnal = ? AND MONTH(kar.DFECPRO) = ? AND YEAR(kar.DFECPRO) = ?", [$selected_executive, $mesPronosticoNumero, $anioPronostico]);
                // Log::info("Recuperacion Siguiente: ", [$recuperacionSiguiente]);

                $recuperacionSiguiente = $recuperacionSiguiente['recuperacion'] ?: 0;

                $poaSiguienteMes = $database->getSingleResult(
                    "SELECT IFNULL(SUM((cremi.NCapDes-IFNULL(kar.sum_KP, 0))),0) AS poa_siguiente_mes 
                    FROM cremcre_meta cremi 
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                    INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
                    LEFT JOIN 
                        (SELECT ccodcta, SUM(KP) AS sum_KP FROM CREDKAR WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P' GROUP BY ccodcta) AS kar 
                            ON kar.ccodcta = cremi.CCODCTA 
                            WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 AND CodAnal=?",
                    [$fechaLimite, $fechaLimite, $selected_executive]
                );
                $poaSiguienteMes = $poaSiguienteMes['poa_siguiente_mes'] ?: 0;

                $pronosticoClientesSiguiente = $database->getSingleResult(
                    "SELECT COUNT(DISTINCT crem.CodCli) clientes FROM cremcre_meta crem 
                        WHERE crem.TipoEnti = 'INDI' AND crem.Cestado IN ('F', 'G') 
                            AND MONTH(crem.DFecDsbls) = ? AND YEAR(crem.DFecDsbls) = ? 
                            AND crem.CodAnal = ?",
                    [$mesPronosticoNumero, $anioPronostico, $selected_executive]
                );
                $pronosticoClientesSiguiente = $pronosticoClientesSiguiente['clientes'] ?: 0;

                /**
                 * CARTERA EN RIESGO
                 */

                $riesgo = $database->getSingleResult(
                    "SELECT
                    -- Conteo de cuentas con 1 a 30 días de atraso
                    SUM(CASE 
                            WHEN atraso.dias_atraso BETWEEN 1 AND 30 THEN 1 
                            ELSE 0 
                        END) AS de_1_a_30,

                    -- Conteo de cuentas con más de 30 días de atraso
                    SUM(CASE 
                            WHEN atraso.dias_atraso > 30 THEN 1 
                            ELSE 0 
                        END) AS mayores_a_30,

                    -- Saldo de cuentas con 1 a 30 días de atraso
                    SUM(CASE 
                            WHEN atraso.dias_atraso BETWEEN 1 AND 30 THEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0))
                            ELSE 0 
                        END) AS saldo_de_1_a_30,

                    -- Saldo de cuentas con más de 30 días de atraso
                    SUM(CASE 
                            WHEN atraso.dias_atraso > 30 THEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0))
                            ELSE 0 
                        END) AS saldo_mayores_a_30

                    FROM cremcre_meta cremi
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
                    INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
                    LEFT JOIN (
                        SELECT ccodcta, SUM(KP) AS sum_KP
                        FROM CREDKAR
                        WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
                        GROUP BY ccodcta
                    ) AS kar ON kar.ccodcta = cremi.CCODCTA
                    INNER JOIN (
                        SELECT 
                            ccodcta,
                            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, ccodcta), '#', 1), '_', 1) AS SIGNED) AS dias_atraso
                        FROM cremcre_meta
                    ) AS atraso ON atraso.ccodcta = cremi.CCODCTA
                    WHERE cremi.CESTADO IN ('F', 'G')
                    AND cremi.DFecDsbls <= ?
                    AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0
                    AND CodAnal = ?
                    AND atraso.dias_atraso >= 1;",
                    [$fechaLimite, $fechaLimite, $fechaLimite, $selected_executive]
                );

                // if (empty($riesgo)) {
                //     $showmensaje = true;
                //     throw new Exception("No se encontraron datos de riesgo");
                // }


                // Log::info("Riesgo: ", [$riesgo]);

            } else {
                // Si no hay datos en $extra, asigna valores por defecto
                $selected_year = date('Y');
                $selected_executive = null;
                $selected_month = date('n');
            }


            $status = true;
        } catch (Exception $e) {
            $status = false;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

    ?>
        <div class="container mx-auto px-4 py-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-200">Consultar POA por Ejecutivo</h2>
                </div>
                <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                        <p><?= htmlspecialchars($mensaje) ?></p>
                    </div>
                <?php endif; ?>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-md items-end">
                        <!-- Año -->
                        <div class="form-control w-full max-w-xs">
                            <label class="label" for="filtro_anio">
                                <span class="label-text text-gray-700 dark:text-gray-300">Año</span>
                            </label>
                            <select id="filtro_anio" name="filtro_anio" class="select select-bordered w-full dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                                <?php
                                $anioActual_filtro = date("Y");
                                $anioInicio_filtro = $anioActual_filtro - 5;
                                $anioFin_filtro = $anioActual_filtro;
                                for ($anio_loop = $anioInicio_filtro; $anio_loop <= $anioFin_filtro; $anio_loop++) {
                                    $is_selected = ($anio_loop == $selected_year) ? ' selected' : '';
                                    echo '<option value="' . $anio_loop . '"' . $is_selected . '>' . $anio_loop . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Ejecutivo -->
                        <div class="form-control w-full max-w-xs">
                            <label class="label" for="filtro_ejecutivo">
                                <span class="label-text text-gray-700 dark:text-gray-300">Ejecutivo</span>
                            </label>
                            <select id="filtro_ejecutivo" name="filtro_ejecutivo" class="select select-bordered w-full dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                                <option value="0" <?= ($selected_executive === null) ? 'selected' : '' ?> disabled>Seleccione un Ejecutivo</option>
                                <?php
                                if (!empty($usuarios)) {
                                    foreach ($usuarios as $usuario_data) {
                                        $idUsuario = $usuario_data['id_usuario'];
                                        $nombreCompleto = htmlspecialchars($usuario_data['nombre'] . ' ' . $usuario_data['apellido']);
                                        $is_selected = ($idUsuario == $selected_executive) ? ' selected' : '';
                                        echo '<option value="' . $idUsuario . '"' . $is_selected . '>' . $nombreCompleto . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Mes -->
                        <div class="form-control w-full max-w-xs">
                            <label class="label" for="filtro_mes">
                                <span class="label-text text-gray-700 dark:text-gray-300">Mes</span>
                            </label>
                            <select id="filtro_mes" name="filtro_mes" class="select select-bordered w-full dark:bg-gray-900 dark:border-gray-600 dark:text-white">
                                <option value="0" <?= ($selected_month === null || $selected_month == 0) ? 'selected' : '' ?> disabled>Seleccione Mes</option>
                                <?php

                                foreach ($meses_list_filtro as $numero_mes => $nombre_mes) {
                                    $is_selected = ($numero_mes == $selected_month) ? ' selected' : '';
                                    echo '<option value="' . $numero_mes . '"' . $is_selected . '>' . $nombre_mes . '</option>';
                                }
                                ?>
                            </select>
                        </div>


                    </div>
                    <div class="flex justify-center mt-4">
                        <button type="button"
                            class="btn btn-primary dark:bg-brand-600 dark:hover:bg-brand-700"
                            onclick="printdiv(`poa_origin`, `#cuadro`, `../kpi/views/view001`, [getselectsval(['filtro_anio']).filtro_anio, getselectsval(['filtro_ejecutivo']).filtro_ejecutivo, getselectsval(['filtro_mes']).filtro_mes])">
                            <i class="fa-solid fa-search mr-2"></i> Procesar
                        </button>
                    </div>
                    <?php

                    if (!empty($dataEjecutivo) && isset($dataEjecutivo[0])) {
                        $datosPOA = $dataEjecutivo[0]; // Tomamos el primer (y único esperado) resultado

                        // Obtener nombre del ejecutivo
                        $nombreEjecutivo = htmlspecialchars($datosPOA['nombre'] . ' ' . $datosPOA['apellido']);
                        $nombreMesEvaluado = htmlspecialchars($meses_list_filtro[$selected_month] ?? 'N/A');

                        // Calcular porcentajes de cumplimiento

                        $porcCartera = calcularPorcentajeSeguro($datosPOA['saldo_cartera'], $datosPOA['cartera_creditos']);
                        $porcClientes = calcularPorcentajeSeguro($datosPOA['total_clientes_indi'], $datosPOA['clientes']);
                        $porcGrupos = calcularPorcentajeSeguro($datosPOA['total_grupos'], $datosPOA['grupos']);
                        $porcColocaciones = calcularPorcentajeSeguro($datosPOA['total_colocaciones'], $datosPOA['colocaciones']);

                        // Promedio de cumplimiento (simple, puedes ajustarlo)
                        $promedioCumplimiento = round(($porcCartera + $porcClientes + $porcGrupos + $porcColocaciones) / 4, 2);

                        // Pronóstico
                        $saldoKAnterior = floatval($datosPOA['saldo_cartera']); // Saldo actual como anterior para el pronóstico
                        $saldoKProyectado = $saldoKAnterior + $proyeccionColocacion - $recuperacionSiguiente;

                        /**
                         * riesgo
                         */
                        $porcentaje1a30 = calcularPorcentajeSeguro($riesgo['saldo_de_1_a_30'], $datosPOA['saldo_cartera']);
                        $porcentajeMayor30 = calcularPorcentajeSeguro($riesgo['saldo_mayores_a_30'], $datosPOA['saldo_cartera']);

                        $mensaje1a30 = ($porcentaje1a30 <= 0) ? "!!!FELICIDADES!!!" : (($porcentaje1a30 > 0 && $porcentaje1a30 <= 1) ? "¡Propóngase, a controlar la K en riesgo!" : (($porcentaje1a30 > 1 && $porcentaje1a30 <= 2) ? "¡¡Preocúpese, se esta elevando su indicador!!" : "¿QUÉ PASÓ?"));
                        $mensajeMayor30 = ($porcentajeMayor30 <= 0) ? "!!!FELICIDADES!!!" : (($porcentajeMayor30 > 0 && $porcentajeMayor30 <= 1) ? "¡Recuerde, *NO*, debe de seguir madurando sus casos!" : (($porcentajeMayor30 > 1 && $porcentajeMayor30 <= 2) ? "¡¡Preocúpese, se esta elevando su indicador!!" : "¿QUÉ PASÓ?"));
                    ?>
                        <div class="mt-8 p-4 md:p-6 bg-gray-100 dark:bg-gray-900 rounded-lg shadow-lg">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">

                                <!-- Columna Izquierda: POA Original y Pronóstico -->
                                <div class="md:col-span-5 space-y-4">
                                    <!-- Sección POA ORIGINAL -->
                                    <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
                                        <h3 class="text-lg font-semibold text-indigo-700 dark:text-indigo-400 mb-3 border-b-2 border-indigo-200 dark:border-indigo-700 pb-2">POA ORIGINAL</h3>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between"><span>¿Mes que Evalúa?</span> <span class="font-medium text-gray-700 dark:text-gray-300"><?= $nombreMesEvaluado . ' ' . $selected_year ?></span></div>
                                            <div class="flex justify-between"><span>Ejecutivo de Negocio:</span> <span class="font-medium text-gray-700 dark:text-gray-300"><?= $nombreEjecutivo ?></span></div>
                                            <div class="flex justify-between"><span>Cartera de Créditos:</span> <span class="font-medium text-gray-700 dark:text-gray-300">Q <?= number_format(floatval($datosPOA['cartera_creditos']), 2) ?></span></div>
                                            <div class="flex justify-between"><span>Clientes:</span> <span class="font-medium text-gray-700 dark:text-gray-300"><?= intval($datosPOA['clientes']) ?></span></div>
                                            <div class="flex justify-between"><span>Grupos:</span> <span class="font-medium text-gray-700 dark:text-gray-300"><?= intval($datosPOA['grupos']) ?></span></div>
                                            <div class="flex justify-between"><span>Colocaciones o desembolsos:</span> <span class="font-medium text-gray-700 dark:text-gray-300">Q <?= number_format(floatval($datosPOA['colocaciones']), 2) ?></span></div>
                                        </div>
                                    </div>

                                    <!-- Sección PRONÓSTICO -->
                                    <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
                                        <h3 class="text-lg font-semibold text-yellow-600 dark:text-yellow-400 mb-3 border-b-2 border-yellow-200 dark:border-yellow-700 pb-2">PRONÓSTICO</h3>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between"><span>¿Mes que Pronostica?</span> <span class="font-medium text-gray-700 dark:text-gray-300 bg-pink-200 dark:bg-pink-700 px-2 py-1 rounded"><?= $nombreMesPronostico . ' ' . $anioPronostico ?></span></div>
                                            <div class="flex justify-between"><span>Saldo K mes anterior:</span> <span class="font-medium text-gray-700 dark:text-gray-300">Q <?= number_format($saldoKAnterior, 2) ?></span></div>
                                            <div class="flex justify-between"><span>Recuperación del mes siguiente:</span> <span class="font-medium text-gray-700 dark:text-gray-300">Q <?= number_format($recuperacionSiguiente, 2) ?></span></div>
                                            <div class="flex justify-between text-red-600 dark:text-red-400"><span>Proyec. De colocación:</span> <span class="font-medium">Q <?= number_format($proyeccionColocacion, 2) ?></span></div>
                                            <div class="flex justify-between"><span>Saldo K Proyectada, mes:</span> <span class="font-medium text-gray-700 dark:text-gray-300">Q <?= number_format($saldoKProyectado, 2) ?></span></div>
                                            <div class="flex justify-between"><span>POA mes siguiente:</span> <span class="font-medium text-gray-700 dark:text-gray-300">Q <?= number_format($poaSiguienteMes, 2) ?></span></div>
                                            <hr class="my-1 dark:border-gray-700">
                                            <div class="flex justify-between"><span>Pronóstico de Clientes del siguiente mes:</span> <span class="font-medium text-gray-700 dark:text-gray-300"><?= $pronosticoClientesSiguiente ?></span></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna Derecha: Cumplimiento, Gauge, Coeficiente -->
                                <div class="md:col-span-7 space-y-4">
                                    <!-- Sección CUMPLIMIENTO -->
                                    <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
                                        <h3 class="text-lg font-semibold text-green-700 dark:text-green-400 mb-3 border-b-2 border-green-200 dark:border-green-700 pb-2">CUMPLIMIENTO</h3>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                            <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded"><span>Cartera: Q <?= number_format(floatval($datosPOA['saldo_cartera']), 2) ?></span> <span class="font-bold text-white px-2 py-1 rounded <?= $porcCartera >= 70 ? ($porcCartera >= 100 ? 'bg-green-500' : 'bg-yellow-500') : 'bg-red-500' ?>"><?= $porcCartera ?>%</span></div>
                                            <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded"><span>Clientes obt.: <?= intval($datosPOA['total_clientes_indi']) ?></span> <span class="font-bold text-white px-2 py-1 rounded <?= $porcClientes >= 70 ? ($porcClientes >= 100 ? 'bg-green-500' : 'bg-yellow-500') : 'bg-red-500' ?>"><?= $porcClientes ?>%</span></div>
                                            <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded"><span>Grupos alcanzados: <?= intval($datosPOA['total_grupos']) ?></span> <span class="font-bold text-white px-2 py-1 rounded <?= $porcGrupos >= 70 ? ($porcGrupos >= 100 ? 'bg-green-500' : 'bg-yellow-500') : 'bg-red-500' ?>"><?= $porcGrupos ?>%</span></div>
                                            <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded"><span>Colocaciones: Q <?= number_format(floatval($datosPOA['total_colocaciones']), 2) ?></span> <span class="font-bold text-white px-2 py-1 rounded <?= $porcColocaciones >= 70 ? ($porcColocaciones >= 100 ? 'bg-green-500' : 'bg-yellow-500') : 'bg-red-500' ?>"><?= $porcColocaciones ?>%</span></div>

                                            <!-- K en Riesgo (Placeholders) -->
                                            <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded mt-2 sm:col-span-2">
                                                <div class="flex-grow">K en riesgo de 1 a 30 días: <span class="font-medium"><?= moneda($riesgo['saldo_de_1_a_30'] ?? 0) ?></span></div>
                                                <span class="font-bold text-white px-2 py-1 rounded bg-green-500"><?= $porcentaje1a30 ?>%</span>
                                            </div>
                                            <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                                <div class="flex-grow">K en riesgo &gt; a 30 días: <span class="font-medium"><?= moneda($riesgo['saldo_mayores_a_30'] ?? 0) ?></span></div>
                                                <span class="font-bold text-white px-2 py-1 rounded bg-red-500"><?= $porcentajeMayor30 ?>%</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sección GAUGE con ApexCharts -->
                                    <div class="bg-white dark:bg-gray-800 p-4 rounded shadow text-center">
                                        <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300 mb-2">Promedio de Cumplimiento</h4>
                                        <div id="gaugeChartPromedio" class="min-h-[200px]"></div> <!-- Contenedor para ApexCharts -->
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Obt., <?= $promedioCumplimiento ?>% de Promedio</p>
                                    </div>


                                    <!-- Sección COEFICIENTE DE VARIACIÓN (Placeholders) -->
                                    <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
                                        <h3 class="text-lg font-semibold text-purple-700 dark:text-purple-400 mb-3 border-b-2 border-purple-200 dark:border-purple-700 pb-2">COEFICIENTE DE VARIACIÓN</h3>
                                        <div class="space-y-2 text-sm">
                                            <?php
                                            // Determinar clase de color para mensaje1a30
                                            $colorClase1a30 = 'text-gray-700 dark:text-gray-300'; // Default
                                            if ($porcentaje1a30 <= 0) {
                                                $colorClase1a30 = 'text-green-600 dark:text-green-400 font-bold'; // FELICIDADES
                                            } elseif ($porcentaje1a30 > 0 && $porcentaje1a30 <= 1) {
                                                $colorClase1a30 = 'text-blue-600 dark:text-blue-400'; // Propóngase
                                            } elseif ($porcentaje1a30 > 1 && $porcentaje1a30 <= 2) {
                                                $colorClase1a30 = 'text-yellow-600 dark:text-yellow-400 font-semibold'; // Preocúpese
                                            } else {
                                                $colorClase1a30 = 'text-red-600 dark:text-red-400 font-bold'; // QUÉ PASÓ?
                                            }

                                            // Determinar clase de color para mensajeMayor30

                                            $colorClaseMayor30 = 'text-gray-700 dark:text-gray-300'; // Default
                                            if ($porcentajeMayor30 <= 0) { // Usando $porcentajeMayor30
                                                $colorClaseMayor30 = 'text-green-600 dark:text-green-400 font-bold';
                                            } elseif ($porcentajeMayor30 > 0 && $porcentajeMayor30 <= 1) {
                                                $colorClaseMayor30 = 'text-blue-600 dark:text-blue-400';
                                            } elseif ($porcentajeMayor30 > 1 && $porcentajeMayor30 <= 2) {
                                                $colorClaseMayor30 = 'text-yellow-600 dark:text-yellow-400 font-semibold';
                                            } else {
                                                $colorClaseMayor30 = 'text-red-600 dark:text-red-400 font-bold';
                                            }
                                            ?>
                                            <div class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/30 rounded">
                                                <span>K en riesgo &lt; a 30 días:</span>
                                                <span class="<?= $colorClase1a30 ?>"><?= htmlspecialchars($mensaje1a30) ?></span>
                                            </div>
                                            <div class="flex justify-between p-2 bg-gray-50 dark:bg-gray-700/30 rounded">
                                                <span>K en riesgo &gt; a 30 días:</span>
                                                <span class="<?= $colorClaseMayor30 ?>"><?= htmlspecialchars($mensajeMayor30) ?></span>
                                            </div>

                                            <!-- <div class="flex justify-between"><span>Monto (ejemplo): Q 1,602.27</span> <span class="font-medium">153%</span></div>
                                            <hr class="my-1 dark:border-gray-700">
                                            <div class="flex justify-between"><span>Variación (ejemplo): -49</span> <span class="font-medium bg-yellow-400 dark:bg-yellow-600 px-2 rounded">62%</span></div> -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $("document").ready(function() {
                                // Inicializar el gráfico de gauge
                                const gaugeContainer = document.getElementById('gaugeChartPromedio');
                                const promedioCumplimiento = <?= $promedioCumplimiento ?? 0 ?>; // Pasar el valor PHP a JS

                                if (gaugeContainer) {
                                    let gaugeColor = '#ef4444'; // Rojo por defecto (bajo)
                                    if (promedioCumplimiento >= 90) {
                                        gaugeColor = '#22c55e'; // Verde (alto)
                                    } else if (promedioCumplimiento >= 70) {
                                        gaugeColor = '#eab308'; // Amarillo (medio)
                                    }

                                    var optionsGauge = {
                                        chart: {
                                            height: 250, // Ajusta la altura según necesites
                                            type: 'radialBar',
                                            toolbar: {
                                                show: false
                                            }
                                        },
                                        plotOptions: {
                                            radialBar: {
                                                startAngle: -135,
                                                endAngle: 135,
                                                hollow: {
                                                    margin: 0,
                                                    size: '70%',
                                                    background: 'transparent', // O dark: '#4B5563' para modo oscuro si es necesario
                                                    image: undefined,
                                                    imageOffsetX: 0,
                                                    imageOffsetY: 0,
                                                    position: 'front',
                                                },
                                                track: {
                                                    background: '#e5e7eb', // Color de fondo de la pista (gris claro)
                                                    strokeWidth: '67%',
                                                    margin: 0, // margin is in pixels
                                                },
                                                dataLabels: {
                                                    show: true,
                                                    name: {
                                                        offsetY: -10,
                                                        show: true,
                                                        color: document.body.classList.contains('dark') ? '#9CA3AF' : '#6B7280', // Gris para modo oscuro/claro
                                                        fontSize: '13px'
                                                    },
                                                    value: {
                                                        formatter: function(val) {
                                                            return parseFloat(val).toFixed(2) + "%";
                                                        },
                                                        color: document.body.classList.contains('dark') ? '#F3F4F6' : '#1F2937', // Color del valor para modo oscuro/claro
                                                        fontSize: '30px',
                                                        show: true,
                                                        offsetY: 10,
                                                    }
                                                }
                                            }
                                        },
                                        fill: {
                                            colors: [gaugeColor] // Color dinámico basado en el cumplimiento
                                        },
                                        series: [promedioCumplimiento],
                                        stroke: {
                                            lineCap: 'round'
                                        },
                                        labels: ['Cumplimiento'],
                                    };
                                    var chartGauge = new ApexCharts(gaugeContainer, optionsGauge);
                                    chartGauge.render();
                                }
                            });
                        </script>
                    <?php
                    } else if (is_array($extra)) {
                    ?>
                        <div class="mt-6 p-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg dark:bg-yellow-200 dark:text-yellow-800" role="alert">
                            No se encontraron datos para los filtros seleccionados para mostrar este resumen.
                        </div>
                    <?php
                    }
                    ?>

                    <?php
                    ?>
                    <div id="tablaContenedor" class="overflow-x-auto mt-6">
                        <!-- Aquí puedes mostrar la tabla si $dataEjecutivo tiene datos -->
                        <?php if (!empty($dataEjecutivo)): ?>
                            <table id="tabla_poa_resultados" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mes</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ejecutivo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cartera Créditos (POA)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Clientes (POA)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Grupos (POA)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Colocaciones (POA)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Saldo Cartera (Real)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Clientes (Real)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Grupos (Real)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Colocaciones (Real)</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($dataEjecutivo as $fila): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($meses_list_filtro[$fila['mes']] ?? $fila['mes']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($fila['nombre'] . ' ' . $fila['apellido']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right"><?= htmlspecialchars(number_format(floatval($fila['cartera_creditos']), 2)) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center"><?= htmlspecialchars(intval($fila['clientes'])) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center"><?= htmlspecialchars(intval($fila['grupos'])) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right"><?= htmlspecialchars(number_format(floatval($fila['colocaciones']), 2)) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right font-semibold"><?= htmlspecialchars(number_format(floatval($fila['saldo_cartera']), 2)) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center font-semibold"><?= htmlspecialchars(intval($fila['total_clientes_indi'])) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center font-semibold"><?= htmlspecialchars(intval($fila['total_grupos'])) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right font-semibold"><?= htmlspecialchars(number_format(floatval($fila['total_colocaciones']), 2)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif (is_array($extra)):
                        ?>
                            <div class="mt-6 p-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg dark:bg-yellow-200 dark:text-yellow-800" role="alert">
                                No se encontraron datos para los filtros seleccionados.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

    <?php
        break;

    case 'metas_month':
        /**
         * Este es el resumen de cada ejecutivo o agencia
         */
        $anio = ($_POST["xtra"] == 0) ? date('Y') : $_POST["xtra"];

        $queryEjecutivos = " WITH poa_con_fecha AS (
            SELECT poah.`year`,poah.id_ejecutivo,mes,cartera_creditos,clientes,cancel,grupos,colocaciones, 
                LAST_DAY(STR_TO_DATE(CONCAT(poah.`year`, '-', poa.mes, '-01'), '%Y-%m-%d')) AS fecha_limite
            FROM kpi_poa poa
            INNER JOIN kpi_poa_header poah ON poa.id_poa = poah.id
            )
            SELECT  
            usu.id_usu, 
            usu.nombre, 
            usu.apellido, 
            poa.mes, 
            poa.cartera_creditos,
            poa.clientes,
            -- poa.cancel,
            poa.grupos,
            poa.colocaciones,
            -- Créditos INDIVIDUALES
            (
                SELECT COUNT(DISTINCT crem.CodCli)
                FROM cremcre_meta crem
                WHERE crem.TipoEnti = 'INDI'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_clientes_indi,
            -- Créditos GRUPALES
            (
                SELECT COUNT(DISTINCT crem.CCodGrupo)
                FROM cremcre_meta crem
                WHERE crem.TipoEnti = 'GRUP'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_grupos,
            -- Colocaciones
            (
                SELECT SUM(crem.NCapDes)
                FROM cremcre_meta crem
                WHERE crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CodAnal = usu.id_usu
            ) AS total_colocaciones,
            -- Saldo de cartera: usando función de correlación
            (
                SELECT SUM(cremi.NCapDes - IFNULL(
                (SELECT SUM(k.KP)
                FROM CREDKAR k
                WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                )
                FROM cremcre_meta cremi
                INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                WHERE cremi.Cestado IN ('F', 'G')
                AND cremi.DFecDsbls <= poa.fecha_limite
                AND cremi.CodAnal = usu.id_usu
                AND (cremi.NCapDes - IFNULL(
                    (SELECT SUM(k.KP)
                    FROM CREDKAR k
                    WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                ) > 0
            ) AS saldo_cartera
            FROM tb_ejecutivos eje
            INNER JOIN tb_usuario usu ON usu.id_usu = eje.id_usuario
            LEFT JOIN poa_con_fecha poa ON usu.id_usu = poa.id_ejecutivo AND poa.`year` = ?;";

        $queryAgencias = "WITH poa_con_fecha AS (
             SELECT poah.`year`,poah.id_ejecutivo,mes,cartera_creditos,clientes,cancel,grupos,colocaciones, 
                LAST_DAY(STR_TO_DATE(CONCAT(poah.`year`, '-', poa.mes, '-01'), '%Y-%m-%d')) AS fecha_limite
            FROM kpi_poa poa
            INNER JOIN kpi_poa_header poah ON poa.id_poa = poah.id
            )
            SELECT  
            age.id_agencia,
            age.nom_agencia,
            poa.mes, 
            SUM(poa.cartera_creditos) AS cartera_creditos,
            SUM(poa.clientes) AS clientes,
            SUM(poa.cancel) AS cancel,
            SUM(poa.grupos) AS grupos,
            SUM(poa.colocaciones) AS colocaciones,

            -- Créditos INDIVIDUALES
            (
                SELECT COUNT(DISTINCT crem.CodCli)
                FROM cremcre_meta crem
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
                WHERE crem.TipoEnti = 'INDI'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CODAgencia = age.cod_agenc
            ) AS total_clientes_indi,

            -- Créditos GRUPALES
            (
                SELECT COUNT(DISTINCT crem.CCodGrupo)
                FROM cremcre_meta crem
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
                WHERE crem.TipoEnti = 'GRUP'
                AND crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CODAgencia = age.cod_agenc
            ) AS total_grupos,

            -- Colocaciones
            (
                SELECT SUM(crem.NCapDes)
                FROM cremcre_meta crem
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
                WHERE crem.Cestado IN ('F', 'G')
                AND MONTH(crem.DFecDsbls) = poa.mes
                AND YEAR(crem.DFecDsbls) = poa.`year`
                AND crem.CODAgencia = age.cod_agenc
            ) AS total_colocaciones,

            -- Saldo de cartera por agencia
            (
                SELECT SUM(cremi.NCapDes - IFNULL(
                (SELECT SUM(k.KP)
                FROM CREDKAR k
                WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                )
                FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cremi.CodCli
                WHERE cremi.Cestado IN ('F', 'G')
                AND cremi.DFecDsbls <= poa.fecha_limite
                AND cremi.CODAgencia = age.cod_agenc
                AND (cremi.NCapDes - IFNULL(
                    (SELECT SUM(k.KP)
                    FROM CREDKAR k
                    WHERE k.ccodcta = cremi.CCODCTA
                    AND k.cestado != 'X' 
                    AND k.ctippag = 'P' 
                    AND k.dfecpro <= poa.fecha_limite), 0)
                ) > 0
            ) AS saldo_cartera

            FROM tb_usuario usu
            INNER JOIN tb_agencia age ON age.id_agencia = usu.id_agencia
            LEFT JOIN poa_con_fecha poa ON usu.id_usu = poa.id_ejecutivo AND poa.`year` = ?
            WHERE poa.mes IS NOT NULL
            GROUP BY age.id_agencia, age.nom_agencia, poa.mes, poa.`year`
            ORDER BY age.nom_agencia, poa.mes;";

        $showmensaje = false;
        try {
            $database->openConnection();
            $datos = $database->getAllResults($queryEjecutivos, [$anio]);

            $datos2 = $database->getAllResults($queryAgencias, [$anio]);

            $status = true;
        } catch (Exception $e) {
            $status = false;
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        } finally {
            $database->closeConnection();
        }

        // Agrupar datos por ejecutivo
        $ejecutivosData = [];
        foreach ($datos as $dato) {
            $ejecutivosData[$dato['id_usu']]['nombre'] = $dato['nombre'] . ' ' . $dato['apellido'];
            // Solo añadir el mes si tiene datos relevantes (no solo el nombre del ejecutivo)
            if ($dato['mes'] !== null && $dato['mes'] !== '') {
                $ejecutivosData[$dato['id_usu']]['meses'][] = $dato;
            } else if (!isset($ejecutivosData[$dato['id_usu']]['meses'])) {
                // Asegurarse de que 'meses' exista incluso si está vacío para la lógica posterior
                $ejecutivosData[$dato['id_usu']]['meses'] = [];
            }
        }

        $agenciasData = [];
        foreach ($datos2 as $dato) {
            $agenciasData[$dato['id_agencia']]['nombre'] = $dato['nom_agencia'];
            // Solo añadir el mes si tiene datos relevantes (no solo el nombre de la agencia)
            if ($dato['mes'] !== null && $dato['mes'] !== '') {
                $agenciasData[$dato['id_agencia']]['meses'][] = $dato;
            } else if (!isset($agenciasData[$dato['id_agencia']]['meses'])) {
                // Asegurarse de que 'meses' exista incluso si está vacío para la lógica posterior
                $agenciasData[$dato['id_agencia']]['meses'] = [];
            }
        }

        // Array de nombres de meses para mostrar
        $nombresMeses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        // Función para calcular porcentaje de forma segura
        function calcularPorcentaje($obtenido, $meta)
        {
            if ($meta == 0) {
                return ($obtenido > 0) ? 100 : 0; // Si la meta es 0 y se obtuvo algo, 100%, sino 0%
            }
            return round(($obtenido / $meta) * 100, 2);
        }

    ?>
        <div class="container mx-auto px-4 py-8">
            <!-- Card Principal -->
            <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg overflow-hidden mt-6 mb-6 mx-auto">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-200">Análisis de Metas por Periodo</h2>
                </div>
                <?php if (isset($status) && $status === false && isset($mensaje)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                        <p><?= htmlspecialchars($mensaje) ?></p>
                    </div>
                <?php endif; ?>
                <!-- Selector de Año -->
                <div class="flex justify-center my-4 mb-4">
                    <select class="select select-neutral" id="anio" aria-label="Seleccione el Año">
                        <?php
                        $currentYear = date('Y');
                        for ($year = $currentYear - 5; $year <= $currentYear; $year++) {
                            $selected = ($year == $anio) ? 'selected' : '';
                            echo "<option value='$year' $selected>$year</option>";
                        }
                        ?>
                    </select>
                </div>
                <!-- Botones de Acción -->
                <div class="flex justify-center space-x-4 pt-6 mt-4">
                    <div class="tooltip">
                        <div class="tooltip-content">
                            <div class="animate-bounce text-orange-400 -rotate-10 text-2xl font-black">El procesamiento de la informacion puede llevar un poco de tiempo!.</div>
                        </div>
                        <button type="button" onclick="printdiv(`metas_month`, `#cuadro`, `../kpi/views/view001`, getselectsval(['anio']).anio)" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:hover:bg-indigo-500">
                            <i class="fas fa-search mr-2"></i>Consultar
                        </button>
                    </div>
                    <!-- Botón Exportar PDF -->
                    <button type="button" onclick="reportes([[],['anio'],[],[]], 'pdf', 'metas_mensuales', 0)" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:hover:bg-red-500">
                        <i class="fas fa-file-pdf mr-2"></i>Exportar PDF
                    </button>
                    <button type="button" onclick="reportes([[],['anio'],[],[]], 'xlsx', 'metas_mensuales', 1)" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:hover:bg-green-500">
                        <i class="fas fa-file-excel mr-2"></i>Exportar Excel
                    </button>
                </div>
                <!-- name of each tab group should be unique -->
                <div class="tabs tabs-lift">
                    <label class="tab"> <!-- Asegúrate que el primer tab esté activo -->
                        <input type="radio" name="my_tabs_4" checked="checked" aria-label="Ejecutivos" />
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 me-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" />
                        </svg>
                        Ejecutivos
                    </label>
                    <div class="tab-content bg-base-100 border-base-300 p-6">
                        <div class="space-y-8">
                            <?php if (!empty($ejecutivosData)) : ?>
                                <?php foreach ($ejecutivosData as $idEjecutivo => $dataEjecutivo) : ?>
                                    <div class="card bg-white dark:bg-gray-800 shadow-lg compact">
                                        <div class="card-body">
                                            <h3 class="card-title text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">
                                                <i class="fas fa-user-tie mr-2"></i><?= htmlspecialchars($dataEjecutivo['nombre']) ?>
                                            </h3>

                                            <?php if (!empty($dataEjecutivo['meses'])) : ?>
                                                <div class="overflow-x-auto">
                                                    <table class="table table-zebra table-sm w-full">
                                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                                            <tr>
                                                                <th rowspan="2" class="text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider align-middle">Mes</th>
                                                                <th colspan="4" class="text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Metas POA</th>
                                                                <th colspan="4" class="text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Resultados Reales</th>
                                                                <th colspan="3" class="text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Cumplimiento (%)</th>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Cartera</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Clientes</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Grupos</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Colocación</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Saldo Cart.</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Clientes</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Grupos</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Colocación</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Cart.</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Clientes</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Coloc.</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                            <?php
                                                            $totalesPoaCartera = 0;
                                                            $totalesPoaClientes = 0;
                                                            $totalesPoaGrupos = 0;
                                                            $totalesPoaColocaciones = 0;
                                                            $totalesRealCartera = 0;
                                                            $totalesRealClientes = 0;
                                                            $totalesRealGrupos = 0;
                                                            $totalesRealColocaciones = 0;
                                                            ?>
                                                            <?php foreach ($dataEjecutivo['meses'] as $mesData) : ?>
                                                                <?php
                                                                // Acumular totales para el pie de tabla
                                                                $totalesPoaCartera += floatval($mesData['cartera_creditos']);
                                                                $totalesPoaClientes += intval($mesData['clientes']);
                                                                $totalesPoaGrupos += intval($mesData['grupos']);
                                                                $totalesPoaColocaciones += floatval($mesData['colocaciones']);

                                                                $totalesRealCartera += floatval($mesData['saldo_cartera']);
                                                                $totalesRealClientes += intval($mesData['total_clientes_indi']); // Asumiendo que 'total_clientes_indi' es el real
                                                                $totalesRealGrupos += intval($mesData['total_grupos']);
                                                                $totalesRealColocaciones += floatval($mesData['total_colocaciones']);

                                                                $porcentajeCartera = calcularPorcentaje(floatval($mesData['saldo_cartera']), floatval($mesData['cartera_creditos']));
                                                                $porcentajeClientes = calcularPorcentaje(intval($mesData['total_clientes_indi']), intval($mesData['clientes']));
                                                                $porcentajeColocaciones = calcularPorcentaje(floatval($mesData['total_colocaciones']), floatval($mesData['colocaciones']));
                                                                ?>
                                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 font-medium"><?= htmlspecialchars($nombresMeses[$mesData['mes']] ?? 'N/A') ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-right"><?= htmlspecialchars(number_format(floatval($mesData['cartera_creditos']), 2)) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-center"><?= htmlspecialchars($mesData['clientes'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-center"><?= htmlspecialchars($mesData['grupos'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-right"><?= htmlspecialchars(number_format(floatval($mesData['colocaciones']), 2)) ?></td>

                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right font-semibold"><?= htmlspecialchars(number_format(floatval($mesData['saldo_cartera']), 2)) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center font-semibold"><?= htmlspecialchars($mesData['total_clientes_indi'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center font-semibold"><?= htmlspecialchars($mesData['total_grupos'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right font-semibold"><?= htmlspecialchars(number_format(floatval($mesData['total_colocaciones']), 2)) ?></td>

                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-center font-bold <?= ($porcentajeCartera >= 100) ? 'text-green-600 dark:text-green-400' : (($porcentajeCartera >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                        <?= $porcentajeCartera ?>%
                                                                    </td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-center font-bold <?= ($porcentajeClientes >= 100) ? 'text-green-600 dark:text-green-400' : (($porcentajeClientes >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                        <?= $porcentajeClientes ?>%
                                                                    </td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-center font-bold <?= ($porcentajeColocaciones >= 100) ? 'text-green-600 dark:text-green-400' : (($porcentajeColocaciones >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                        <?= $porcentajeColocaciones ?>%
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold text-gray-700 dark:text-gray-200">
                                                            <tr>
                                                                <td class="px-3 py-2 text-sm">TOTALES</td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesPoaCartera, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesPoaClientes ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesPoaGrupos ?></td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesPoaColocaciones, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesRealCartera, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesRealClientes ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesRealGrupos ?></td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesRealColocaciones, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-center font-bold <?= (calcularPorcentaje($totalesRealCartera, $totalesPoaCartera) >= 100) ? 'text-green-600 dark:text-green-400' : ((calcularPorcentaje($totalesRealCartera, $totalesPoaCartera) >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                    <?= calcularPorcentaje($totalesRealCartera, $totalesPoaCartera) ?>%
                                                                </td>
                                                                <td class="px-3 py-2 text-sm text-center font-bold <?= (calcularPorcentaje($totalesRealClientes, $totalesPoaClientes) >= 100) ? 'text-green-600 dark:text-green-400' : ((calcularPorcentaje($totalesRealClientes, $totalesPoaClientes) >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                    <?= calcularPorcentaje($totalesRealClientes, $totalesPoaClientes) ?>%
                                                                </td>
                                                                <td class="px-3 py-2 text-sm text-center font-bold <?= (calcularPorcentaje($totalesRealColocaciones, $totalesPoaColocaciones) >= 100) ? 'text-green-600 dark:text-green-400' : ((calcularPorcentaje($totalesRealColocaciones, $totalesPoaColocaciones) >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                    <?= calcularPorcentaje($totalesRealColocaciones, $totalesPoaColocaciones) ?>%
                                                                </td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            <?php else : ?>
                                                <div role="alert" class="alert alert-vertical sm:alert-horizontal">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info h-6 w-6 shrink-0">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <div>
                                                        <h3 class="font-bold">Uff!</h3>
                                                        <div class="text-xs">No hay datos de metas mensuales para mostrar para este ejecutivo. </div>
                                                    </div>
                                                    <button class="btn btn-sm">Agregar Informacion</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="alert alert-warning shadow-lg">
                                    <div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span>No se encontraron datos de ejecutivos para el año seleccionado.</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <label class="tab">
                        <input type="radio" name="my_tabs_4" aria-label="Agencias" />
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 me-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>
                        Agencias
                    </label>
                    <div class="tab-content bg-base-100 border-base-300 p-6">
                        <div class="space-y-8">
                            <?php if (!empty($agenciasData)) : ?>
                                <?php foreach ($agenciasData as $idAgencia => $dataAgencia) : ?>
                                    <div class="card bg-white dark:bg-gray-800 shadow-lg compact">
                                        <div class="card-body">
                                            <h3 class="card-title text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">
                                                <i class="fas fa-user-tie mr-2"></i><?= htmlspecialchars($dataAgencia['nombre']) ?>
                                            </h3>

                                            <?php if (!empty($dataAgencia['meses'])) : ?>
                                                <div class="overflow-x-auto">
                                                    <table class="table table-zebra table-sm w-full">
                                                        <thead class="bg-gray-100 dark:bg-gray-700">
                                                            <tr>
                                                                <th rowspan="2" class="text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider align-middle">Mes</th>
                                                                <th colspan="4" class="text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Metas POA</th>
                                                                <th colspan="4" class="text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Resultados Reales</th>
                                                                <th colspan="3" class="text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b border-gray-300 dark:border-gray-600">Cumplimiento (%)</th>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Cartera</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Clientes</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Grupos</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Colocación</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Saldo Cart.</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Clientes</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Grupos</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Colocación</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Cart.</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Clientes</th>
                                                                <th class="text-xs font-medium text-gray-500 dark:text-gray-300">Coloc.</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                            <?php
                                                            $totalesPoaCartera = 0;
                                                            $totalesPoaClientes = 0;
                                                            $totalesPoaGrupos = 0;
                                                            $totalesPoaColocaciones = 0;
                                                            $totalesRealCartera = 0;
                                                            $totalesRealClientes = 0;
                                                            $totalesRealGrupos = 0;
                                                            $totalesRealColocaciones = 0;
                                                            ?>
                                                            <?php foreach ($dataAgencia['meses'] as $mesData) : ?>
                                                                <?php
                                                                // Acumular totales para el pie de tabla
                                                                $totalesPoaCartera += floatval($mesData['cartera_creditos']);
                                                                $totalesPoaClientes += intval($mesData['clientes']);
                                                                $totalesPoaGrupos += intval($mesData['grupos']);
                                                                $totalesPoaColocaciones += floatval($mesData['colocaciones']);

                                                                $totalesRealCartera += floatval($mesData['saldo_cartera']);
                                                                $totalesRealClientes += intval($mesData['total_clientes_indi']); // Asumiendo que 'total_clientes_indi' es el real
                                                                $totalesRealGrupos += intval($mesData['total_grupos']);
                                                                $totalesRealColocaciones += floatval($mesData['total_colocaciones']);

                                                                $porcentajeCartera = calcularPorcentaje(floatval($mesData['saldo_cartera']), floatval($mesData['cartera_creditos']));
                                                                $porcentajeClientes = calcularPorcentaje(intval($mesData['total_clientes_indi']), intval($mesData['clientes']));
                                                                $porcentajeColocaciones = calcularPorcentaje(floatval($mesData['total_colocaciones']), floatval($mesData['colocaciones']));
                                                                ?>
                                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 font-medium"><?= htmlspecialchars($nombresMeses[$mesData['mes']] ?? 'N/A') ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-right"><?= htmlspecialchars(number_format(floatval($mesData['cartera_creditos']), 2)) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-center"><?= htmlspecialchars($mesData['clientes'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-center"><?= htmlspecialchars($mesData['grupos'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400 text-right"><?= htmlspecialchars(number_format(floatval($mesData['colocaciones']), 2)) ?></td>

                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right font-semibold"><?= htmlspecialchars(number_format(floatval($mesData['saldo_cartera']), 2)) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center font-semibold"><?= htmlspecialchars($mesData['total_clientes_indi'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center font-semibold"><?= htmlspecialchars($mesData['total_grupos'] ?? 0) ?></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-right font-semibold"><?= htmlspecialchars(number_format(floatval($mesData['total_colocaciones']), 2)) ?></td>

                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-center font-bold <?= ($porcentajeCartera >= 100) ? 'text-green-600 dark:text-green-400' : (($porcentajeCartera >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                        <?= $porcentajeCartera ?>%
                                                                    </td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-center font-bold <?= ($porcentajeClientes >= 100) ? 'text-green-600 dark:text-green-400' : (($porcentajeClientes >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                        <?= $porcentajeClientes ?>%
                                                                    </td>
                                                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-center font-bold <?= ($porcentajeColocaciones >= 100) ? 'text-green-600 dark:text-green-400' : (($porcentajeColocaciones >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                        <?= $porcentajeColocaciones ?>%
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot class="bg-gray-100 dark:bg-gray-700 font-semibold text-gray-700 dark:text-gray-200">
                                                            <tr>
                                                                <td class="px-3 py-2 text-sm">TOTALES</td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesPoaCartera, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesPoaClientes ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesPoaGrupos ?></td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesPoaColocaciones, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesRealCartera, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesRealClientes ?></td>
                                                                <td class="px-3 py-2 text-sm text-center"><?= $totalesRealGrupos ?></td>
                                                                <td class="px-3 py-2 text-sm text-right"><?= number_format($totalesRealColocaciones, 2) ?></td>
                                                                <td class="px-3 py-2 text-sm text-center font-bold <?= (calcularPorcentaje($totalesRealCartera, $totalesPoaCartera) >= 100) ? 'text-green-600 dark:text-green-400' : ((calcularPorcentaje($totalesRealCartera, $totalesPoaCartera) >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                    <?= calcularPorcentaje($totalesRealCartera, $totalesPoaCartera) ?>%
                                                                </td>
                                                                <td class="px-3 py-2 text-sm text-center font-bold <?= (calcularPorcentaje($totalesRealClientes, $totalesPoaClientes) >= 100) ? 'text-green-600 dark:text-green-400' : ((calcularPorcentaje($totalesRealClientes, $totalesPoaClientes) >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                    <?= calcularPorcentaje($totalesRealClientes, $totalesPoaClientes) ?>%
                                                                </td>
                                                                <td class="px-3 py-2 text-sm text-center font-bold <?= (calcularPorcentaje($totalesRealColocaciones, $totalesPoaColocaciones) >= 100) ? 'text-green-600 dark:text-green-400' : ((calcularPorcentaje($totalesRealColocaciones, $totalesPoaColocaciones) >= 70) ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') ?>">
                                                                    <?= calcularPorcentaje($totalesRealColocaciones, $totalesPoaColocaciones) ?>%
                                                                </td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            <?php else : ?>
                                                <div role="alert" class="alert alert-vertical sm:alert-horizontal">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info h-6 w-6 shrink-0">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <div>
                                                        <h3 class="font-bold">Uff!</h3>
                                                        <div class="text-xs">No hay datos de metas mensuales para mostrar para esta agencia. </div>
                                                    </div>
                                                    <!-- <button class="btn btn-sm">Agregar Informacion</button> -->
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="alert alert-warning shadow-lg">
                                    <div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span>No se encontraron datos de ejecutivos en la agencia para el año seleccionado.</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

<?php
        break;
}

function calcularPorcentajeSeguro($obtenido, $meta)
{
    $obtenidoNum = floatval($obtenido);
    $metaNum = floatval($meta);
    if ($metaNum == 0) {
        return ($obtenidoNum > 0) ? 100.00 : 0.00;
    }
    return round(($obtenidoNum / $metaNum) * 100, 2);
}
?>