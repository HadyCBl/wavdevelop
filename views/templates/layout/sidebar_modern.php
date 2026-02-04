<?php

use App\Generic\Agencia;
use App\Generic\Institucion;
use Micro\Helpers\Log;

$showmodules[] = [];
$showmensaje = false;
$i = 0;
try {
    $database->openConnection();

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

$makeUrl = BASE_URL . "/views/indicadores/";
?>

<!-- ===== Sidebar Start ===== -->
<aside
    :class="sidebarToggle ? 'translate-x-0 lg:w-[90px]' : '-translate-x-full'"
    class="sidebar fixed left-0 top-0 z-[9999] flex h-screen w-[280px] flex-col overflow-hidden bg-[#0A0E27] shadow-2xl lg:static lg:translate-x-0 border-r border-white/5">
    
    <!-- SIDEBAR HEADER -->
    <div class="flex flex-col items-center py-8 px-4 border-b border-white/10">
        <a href="<?= $makeUrl ?>" class="flex flex-col items-center gap-4 group">
            <!-- Logo Icon con efecto -->
            <div class="relative">
                <div class="absolute inset-0 bg-blue-500 rounded-2xl blur-xl opacity-20 group-hover:opacity-30 transition-opacity"></div>
                <div class="relative w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 p-3 shadow-lg group-hover:scale-105 transition-transform duration-300">
                    <img src="<?= BASE_URL; ?>/assets/svg/iconms.svg" alt="Logo" class="w-full h-full object-contain" />
                </div>
            </div>
            <!-- Nombre de la institución -->
            <span
                :class="sidebarToggle ? 'hidden' : ''"
                class="text-sm font-bold text-white text-center leading-tight tracking-wide">
                <?= $shortNameInstitution ?? 'MicroSystem+'; ?>
            </span>
        </a>
    </div>
    <!-- SIDEBAR HEADER -->

    <?php if (!$status) { ?>
        <div class="mx-4 mt-4 p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="text-xs text-yellow-200"><?= $mensaje; ?></span>
            </div>
        </div>
    <?php } ?>

    <div class="flex flex-col flex-1 overflow-y-auto sidebar-scroll py-6">
        <!-- Sidebar Menu -->
        <nav x-data="{selected: $persist('Dashboard')}" class="space-y-6 px-4">
            
            <!-- Menu Group Principal -->
            <div>
                <h3 class="text-[10px] font-bold uppercase tracking-wider text-white/40 mb-3 px-3"
                    :class="sidebarToggle ? 'lg:hidden' : ''">
                    Menú Principal
                </h3>

                <ul class="space-y-1">
                    <!-- Menu Item Dashboard -->
                    <li>
                        <a
                            href="<?= $makeUrl ?>"
                            @click="selected = (selected === 'Dashboard' ? '':'Dashboard')"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group"
                            :class="(selected === 'Dashboard') || (page === 'Dashboard') ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/30' : 'text-gray-300 hover:bg-white/5 hover:text-white'">
                            <div class="flex items-center justify-center w-5 h-5">
                                <svg
                                    class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"
                                    :class="(selected === 'Dashboard') || (page === 'Dashboard') ? 'fill-white' : 'fill-gray-400 group-hover:fill-blue-400'"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z" />
                                </svg>
                            </div>
                            <span :class="sidebarToggle ? 'lg:hidden' : ''">Dashboard</span>
                        </a>
                    </li>

                    <!-- Menu Items Dinámicos -->
                    <?php
                    if (!empty($showmodules)) {
                        foreach ($showmodules as $fila) {
                            $descripcionModulo = $fila['descripcion'];
                            $iconModulo = $fila['icon'];
                            $ruta = $fila['ruta'];
                            $identificadorModulo = 'id_' . $fila['id'];

                            $permisosPlano = $fila['permissions'];
                            $menuAgrupado = [];
                            foreach ($permisosPlano as $permiso) {
                                $menuId = $permiso['menu'];
                                $menuDescripcion = $permiso['descripcion'];

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
                            $permisosAgrupados = array_values($menuAgrupado);

                            if (!empty($permisosAgrupados)) {
                    ?>
                                <li>
                                    <details :open="selected === '<?= $identificadorModulo ?>'" @toggle="selected = $event.target.open ? '<?= $identificadorModulo ?>' : ''" class="group">
                                        <summary class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 cursor-pointer list-none"
                                                 :class="selected === '<?= $identificadorModulo ?>' ? 'bg-white/5 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white'">
                                            <div class="flex items-center justify-center w-5 h-5">
                                                <svg class="w-5 h-5 transition-all duration-200 group-hover:scale-110" :class="selected === '<?= $identificadorModulo ?>' ? 'fill-blue-400' : 'fill-gray-400 group-hover:fill-blue-400'" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <?= $iconModulo ?>
                                                </svg>
                                            </div>
                                            <span :class="sidebarToggle ? 'lg:hidden' : ''" class="flex-1"><?= $descripcionModulo ?></span>
                                            <svg class="w-4 h-4 transition-transform duration-200 shrink-0" :class="selected === '<?= $identificadorModulo ?>' ? 'rotate-180 text-blue-400' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </summary>

                                        <ul x-data="{ subSelected: '',activeItem: ''  }" class="mt-1 ml-8 space-y-1">
                                            <?php
                                            foreach ($permisosAgrupados as $menuPrincipal) :
                                                $identificadorMenu = 'id_menu_' . $menuPrincipal['menu'];
                                                $opcionesSubmenuNivel2 = array_map(function ($sub) {
                                                    return $sub['opcion'];
                                                }, $menuPrincipal['submenus']);
                                                $opcionesSubmenuNivel2Json = htmlspecialchars(json_encode($opcionesSubmenuNivel2), ENT_QUOTES, 'UTF-8');
                                            ?>
                                                <li>
                                                    <details :open="subSelected === '<?= $identificadorMenu ?>' || JSON.parse('<?= $opcionesSubmenuNivel2Json ?>').includes(activeItem)" @toggle="subSelected = $event.target.open ? '<?= $identificadorMenu ?>' : ''" class="group">
                                                        <summary class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200 cursor-pointer list-none"
                                                                 :class="(subSelected === '<?= $identificadorMenu ?>' || JSON.parse('<?= $opcionesSubmenuNivel2Json ?>').includes(activeItem)) ? 'text-blue-400 bg-blue-400/10' : 'text-gray-400 hover:text-gray-300'">
                                                            <span class="flex-1"><?= $menuPrincipal['descripcion'] ?></span>
                                                            <svg class="w-3 h-3 transition-transform duration-200 shrink-0" :class="(subSelected === '<?= $identificadorMenu ?>' || JSON.parse('<?= $opcionesSubmenuNivel2Json ?>').includes(activeItem)) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                            </svg>
                                                        </summary>

                                                        <ul class="mt-1 ml-4 space-y-0.5">
                                                            <?php foreach ($menuPrincipal['submenus'] as $submenu) :
                                                                $rutaArchivo = '../' . $ruta . '/views/' . $submenu['file'];
                                                                $onclickAction = sprintf(
                                                                    "printdiv(`%s`, `#cuadro`, `%s`, `0`)",
                                                                    $submenu['condi'],
                                                                    $rutaArchivo,
                                                                );
                                                                $opcionId = $submenu['opcion'];
                                                            ?>
                                                                <li>
                                                                    <a href="#"
                                                                        onclick="<?= htmlspecialchars($onclickAction, ENT_QUOTES); ?>"
                                                                        @click="activeItem = '<?= $opcionId ?>'"
                                                                        class="flex items-center justify-between px-3 py-2 rounded-lg text-xs transition-all duration-200 group relative"
                                                                        :class="activeItem === '<?= $opcionId ?>' ? 'text-blue-400 bg-blue-400/10 font-medium' : 'text-gray-500 hover:text-gray-300 hover:bg-white/5'">
                                                                        <span class="flex items-center gap-2">
                                                                            <span class="w-1 h-1 rounded-full transition-colors" :class="activeItem === '<?= $opcionId ?>' ? 'bg-blue-400' : 'bg-gray-600'"></span>
                                                                            <?= $submenu['caption'] ?>
                                                                        </span>
                                                                        <span x-show="activeItem === '<?= $opcionId ?>'" class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </details>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                </li>
                    <?php
                            }
                        }
                    }
                    ?>
                </ul>
            </div>

            <!-- Sección Otros -->
            <div class="mt-auto pt-6 border-t border-white/10">
                <h3 class="text-[10px] font-bold uppercase tracking-wider text-white/40 mb-3 px-3"
                    :class="sidebarToggle ? 'lg:hidden' : ''">
                    Acciones
                </h3>

                <ul class="space-y-1">
                    <li>
                        <a
                            href="<?= BASE_URL . "/views/" ?>"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 text-gray-300 hover:bg-white/5 hover:text-white group border border-white/10 hover:border-blue-500/50">
                            <svg class="w-5 h-5 transition-transform duration-200 group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            <span :class="sidebarToggle ? 'lg:hidden' : ''">Volver al Inicio</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</aside>
<!-- ===== Sidebar End ===== -->
