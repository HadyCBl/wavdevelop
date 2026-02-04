<?php
$showmensaje = false;
try {
    $database->openConnection();

    //CONSULTA DE PERMISOS DEL USUARIO
    $permisos = getpermisosuser($database, $idusuario, 'I', 23, $db_name_general);

    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}
?>

<?php if (!$status) { ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md" role="alert">
        <div class="flex items-center justify-between">
            <div>
                <strong class="font-bold">¡Error!</strong>
                <span class="block sm:inline"><?= $mensaje; ?></span>
            </div>
            <button type="button" class="text-yellow-700 hover:text-yellow-900" aria-label="Close" onclick="this.parentElement.parentElement.remove();">
                &times;
            </button>
        </div>
    </div>
<?php } ?>

<!-- ===== Dropdown Buttons Start ===== -->
<div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6 flex space-x-4">

    <!-- DROPDOWN DYNAMICS START-->
    <?php
    if ($status) {
        end($permisos[1]);
        $lastKey = key($permisos[1]);
        reset($permisos[1]);

        $showmenuheader = true;
        $showmenufooter = false;
        foreach ($permisos[1] as $key => $permiso) {
            $menu = $permiso["menu"];
            $descripcion = $permiso["descripcion"];
            $condi = $permiso["condi"];
            $file = $permiso["file"];
            $caption = $permiso["caption"];

            if ($showmenuheader) {
    ?>
                <!-- Dropdown 1 - Subtle Style con Iconos -->
                <div x-data="{ open: false }" class="relative inline-block text-left">
                    <div>
                        <button @click="open = !open" type="button" class="inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:text-gray-200" id="menu-button-1-subtle" aria-expanded="true" aria-haspopup="true">
                            <!-- Icono para el botón principal -->
                            <svg class="mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                            </svg>
                            <?= $descripcion; ?>
                            <svg class="ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                    <!-- Dropdown menu -->
                    <div x-show="open"
                        @click.outside="open = false"
                        x-transition
                        class="origin-top-left absolute left-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-gray-700 z-10"
                        role="menu" aria-orientation="vertical" aria-labelledby="menu-button-1-subtle" tabindex="-1">
                        <div class="py-1" role="none">

                        <?php
                        $showmenuheader = false;
                    }
                        ?>
                        <a style="cursor: pointer;" onclick="printdiv(`<?= $condi; ?>`, `#cuadro`, `<?= $file; ?>`, `0`)" class="text-gray-500 dark:text-gray-200 block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700" role="menuitem" tabindex="-1" id="menu-item-0-1-subtle">
                            <!-- Icono para sub-opción 1 -->
                            <svg class="mr-3 h-4 w-4 inline-block align-middle" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                            </svg>
                            <!-- Aplicar clases aquí -->
                            <span class="align-middle whitespace-nowrap overflow-hidden text-ellipsis"><?= $caption; ?></span>
                        </a>

                        <?php
                        if ($key === $lastKey) {
                            $showmenufooter = true;
                        } else {
                            if ($permisos[1][$key + 1]['menu'] != $menu) {
                                $showmenufooter = true;
                            }
                        }

                        if ($showmenufooter) {
                        ?>
                        </div>
                    </div>
                </div>
    <?php
                            // echo '';
                            $showmenufooter = false;
                            $showmenuheader = true;
                        }
                    }
                }
    ?>
    <!-- DROPDOWN DYNAMICS END-->

    <!-- ===== Main Content Start ===== -->

    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6" id="cuadro">

    </div>

    <!-- ===== Main Content End ===== -->