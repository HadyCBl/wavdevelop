<?php

use App\Generic\Agencia;
use App\Generic\Institucion;
use Micro\Helpers\Log;

// Log::info("aki tamos", [
//     'url' => BASE_URL
// ]);
$showmodules[] = [];
$showmensaje = false;
$i = 0;
try {
    $database->openConnection();

    // $dataGeneral = new Institucion($idagencia);
    $shortNameInstitution = (new Agencia($idagencia))->institucion?->getNombreCortoInstitucion();

    $result = getpermisosmodules($database, $idagencia, 'I', $db_name_general);
    if ($result[0] == 0) {
        $showmensaje = true;
        throw new Exception($result[1]);
    }

    foreach ($result[1] as $pm) {
        $idmodulo = $pm["id"];
        $rama = $pm["rama"];
        $resultado = getpermisosuser($database, $idusuario, $rama, $idmodulo, $db_name_general);
        if ($resultado[0] == 1) {
            $showmodules[$i] = $pm;
            $showmodules[$i]['permissions'] = $resultado[1];
            $i++;
        }
    }

    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

// Log::info("aki tamos", [
//     'url' => BASE_URL,
//     'shortNameInstitution' => $shortNameInstitution,
//     'showmodules' => $showmodules
// ]);

$makeUrl = BASE_URL . "/views/indicadores/";

// Log::info("aki tamos", [
//     'url' => BASE_URL
// ]);
?>
<!-- END SECTION HEADER -->

<!-- ===== Sidebar Start ===== -->
<aside
    :class="sidebarToggle ? 'translate-x-0 lg:w-[90px]' : '-translate-x-full'"
    class="sidebar fixed left-0 top-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-hidden border-r border-gray-200 bg-white px-5 dark:border-gray-800 dark:bg-black lg:static lg:translate-x-0">
    <!-- SIDEBAR HEADER -->
    <div class="flex flex-col items-center pt-6 pb-6 border-b border-base-200 dark:border-gray-800">
        <a href="index.html" class="flex flex-col items-center gap-3 hover:opacity-80 transition-opacity duration-200">
            <!-- Logo Icon con efecto -->
            <div class="avatar">
                <div class="w-12 rounded-xl ring ring-primary ring-offset-base-100 ring-offset-2">
                    <img class="dark:hidden" src="<?= BASE_URL; ?>/assets/svg/iconms.svg" alt="Logo" />
                    <img class="hidden dark:block" src="<?= BASE_URL; ?>/assets/svg/iconms.svg" alt="Logo" />
                </div>
            </div>
            <!-- Nombre de la institución -->
            <span
                :class="sidebarToggle ? 'hidden' : ''"
                class="text-base font-bold text-primary dark:text-white text-center leading-tight">
                <?= $shortNameInstitution ?? 'MicroSystem+'; ?>
            </span>
        </a>
    </div>
    <!-- SIDEBAR HEADER -->


    <?php if (!$status) { ?>
        <div class="alert alert-warning mt-4 mx-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <span class="text-sm"><?= $mensaje; ?></span>
        </div>
    <?php } ?>

    <div
        class="flex flex-col flex-1 overflow-y-auto sidebar-scroll py-4">
        <!-- Sidebar Menu -->
        <nav x-data="{selected: $persist('Dashboard')}" class="space-y-2">
            <!-- Menu Group -->
            <div class="px-3">
                <div class="divider divider-start text-xs font-semibold uppercase text-base-content/60"
                     :class="sidebarToggle ? 'lg:hidden' : ''">
                    <span>Menú Principal</span>
                </div>

                <ul class="menu menu-sm gap-1">
                    <!-- Menu Item Dashboard -->
                    <li>
                        <a
                            href="<?= $makeUrl ?>"
                            @click="selected = (selected === 'Dashboard' ? '':'Dashboard')"
                            class="group sidebar-transition menu-hover-effect"
                            :class="(selected === 'Dashboard') || (page === 'Dashboard') ? 'active bg-primary text-primary-content' : ''">
                            <svg
                                class="w-5 h-5"
                                :class="(selected === 'Dashboard') || (page === 'Dashboard') ? 'fill-primary-content' : 'fill-base-content/70 group-hover:fill-primary'"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    fill-rule="evenodd"
                                    clip-rule="evenodd"
                                    d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z" />
                            </svg>
                            <span :class="sidebarToggle ? 'lg:hidden' : ''" class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <!-- Menu Item Dashboard -->

                    <!-- Menu Item dinamics 2 start -->
                    <?php
                    if (!empty($showmodules)) {
                        foreach ($showmodules as $fila) {
                            $descripcionModulo = $fila['descripcion'];
                            $iconModulo = $fila['icon'];
                            $ruta = $fila['ruta'];
                            $identificadorModulo = 'id_' . $fila['id'];

                            // --- Inicio de la Agrupación ---
                            $permisosPlano = $fila['permissions'];
                            $menuAgrupado = [];
                            foreach ($permisosPlano as $permiso) {
                                $menuId = $permiso['menu'];
                                $menuDescripcion = $permiso['descripcion']; // Descripción del sub-menú principal

                                if (!isset($menuAgrupado[$menuId])) {
                                    $menuAgrupado[$menuId] = [
                                        'menu' => $menuId,
                                        'descripcion' => $menuDescripcion,
                                        'submenus' => []
                                    ];
                                }
                                $menuAgrupado[$menuId]['submenus'][] = [
                                    'opcion' => $permiso['opcion'],
                                    'condi' => $permiso['condi'],
                                    'file' => $permiso['file'],
                                    'caption' => $permiso['caption']
                                ];
                            }
                            $permisosAgrupados = array_values($menuAgrupado); // Array agrupado para este módulo
                            // --- Fin de la Agrupación ---


                            if (!empty($permisosAgrupados)) {
                    ?>
                                <!-- Menu Item Forms -->
                                <!-- Menu Item Módulo Principal (Nivel 1) -->
                                <li>
                                    <details :open="selected === '<?= $identificadorModulo ?>'" @toggle="selected = $event.target.open ? '<?= $identificadorModulo ?>' : ''">
                                        <summary class="group sidebar-transition menu-hover-effect" :class="selected === '<?= $identificadorModulo ?>' ? 'bg-base-200 dark:bg-gray-800' : ''">
                                            <svg class="w-5 h-5" :class="selected === '<?= $identificadorModulo ?>' ? 'fill-primary' : 'fill-base-content/70 group-hover:fill-primary'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <?= $iconModulo ?>
                                            </svg>
                                            <span :class="sidebarToggle ? 'lg:hidden' : ''" class="font-medium"><?= $descripcionModulo ?></span>
                                        </summary>

                                        <!-- Dropdown Menu Start (Nivel 2 - Menús Agrupados) -->
                                        <ul x-data="{ subSelected: '',activeItem: ''  }" class="menu menu-sm gap-1 before:hidden">
                                            <?php
                                            // Crear un array simple con los IDs de opción para este menú principal
                                            $opcionesDeEsteMenu = [];
                                            foreach ($permisosAgrupados as $menuPrincipalTemp) {
                                                foreach ($menuPrincipalTemp['submenus'] as $submenuTemp) {
                                                    $opcionesDeEsteMenu[] = $submenuTemp['opcion'];
                                                }
                                            }
                                            $opcionesJson = htmlspecialchars(json_encode($opcionesDeEsteMenu), ENT_QUOTES, 'UTF-8');
                                            foreach ($permisosAgrupados as $menuPrincipal) :
                                                $identificadorMenu = 'id_menu_' . $menuPrincipal['menu']; // ID único para el menú agrupado
                                                // Crear array de opciones solo para ESTE submenú de nivel 2
                                                $opcionesSubmenuNivel2 = array_map(function ($sub) {
                                                    return $sub['opcion'];
                                                }, $menuPrincipal['submenus']);
                                                $opcionesSubmenuNivel2Json = htmlspecialchars(json_encode($opcionesSubmenuNivel2), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <!-- Menu Item Menú Agrupado (Nivel 2) -->
                                                <li>
                                                    <details :open="subSelected === '<?= $identificadorMenu ?>' || JSON.parse('<?= $opcionesSubmenuNivel2Json ?>').includes(activeItem)" @toggle="subSelected = $event.target.open ? '<?= $identificadorMenu ?>' : ''">
                                                        <summary class="text-sm sidebar-transition" :class="(subSelected === '<?= $identificadorMenu ?>' || JSON.parse('<?= $opcionesSubmenuNivel2Json ?>').includes(activeItem)) ? 'text-primary font-semibold' : 'text-base-content/80'">
                                                            <span><?= $menuPrincipal['descripcion'] ?></span>
                                                        </summary>

                                                        <!-- Dropdown Menu Start (Nivel 3 - Submenús Finales) -->
                                                        <ul class="menu menu-sm gap-1 before:hidden">
                                                            <?php foreach ($menuPrincipal['submenus'] as $submenu) :
                                                                $rutaArchivo = '../' . $ruta . '/views/' . $submenu['file'];
                                                                $onclickAction = sprintf(
                                                                    "printdiv(`%s`, `#cuadro`, `%s`, `0`)",
                                                                    $submenu['condi'],
                                                                    $rutaArchivo,
                                                                );
                                                                // printdiv(`p4`, `#cuadro`, `../pearls/views/proteccion`, `0`)
                                                                $opcionId = $submenu['opcion']; // Guardar ID para Alpine
                                                            ?>
                                                                <li>
                                                                    <a href="#"
                                                                        onclick="<?= htmlspecialchars($onclickAction, ENT_QUOTES); ?>"
                                                                        @click="activeItem = '<?= $opcionId ?>'"
                                                                        class="text-xs sidebar-transition menu-hover-effect flex items-center justify-between group"
                                                                        :class="activeItem === '<?= $opcionId ?>' ? 'active bg-primary/10 text-primary font-medium' : 'text-base-content/70 hover:text-primary'">
                                                                        <span><?= $submenu['caption'] ?></span>
                                                                        <span x-show="activeItem === '<?= $opcionId ?>'" class="badge badge-primary badge-xs">●</span>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </details>
                                                    <!-- Dropdown Menu End (Nivel 3) -->
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                    <!-- Dropdown Menu End (Nivel 2) -->
                                </li>
                                <!-- Menu Item Forms -->
                    <?php
                            }
                        }
                    }
                    ?>

                    <!-- Menu Item dinamics 2 end -->

                </ul>
            </div>
            
            <!-- Sección Otros -->
            <div class="px-3 mt-auto pt-4 border-t border-base-200 dark:border-gray-800">
                <div class="divider divider-start text-xs font-semibold uppercase text-base-content/60"
                     :class="sidebarToggle ? 'lg:hidden' : ''">
                    <span>Acciones</span>
                </div>

                <ul class="menu menu-sm gap-1">
                    <!-- Menu Item back to home -->
                    <li>
                        <a
                            href="<?= BASE_URL . "/views/" ?>"
                            class="group sidebar-transition menu-hover-effect btn btn-outline btn-sm gap-2">
                            <svg class="w-5 h-5 fill-base-content/70 group-hover:fill-primary" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            <span :class="sidebarToggle ? 'lg:hidden' : ''" class="font-medium">Volver al Inicio</span>
                        </a>
                    </li>
                    <!-- Menu Item Back to home -->
                </ul>
            </div>

        </nav>
        <!-- Sidebar Menu -->
    </div>
</aside>

<!-- ===== Sidebar End ===== -->