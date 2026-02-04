<?php
include_once __DIR__ . '/../../includes/Config/config.php';
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    return;
}

$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';

require_once __DIR__ . '/../../vendor/autoload.php';

date_default_timezone_set('America/Guatemala');

require_once __DIR__ . '/layout/header.php';
?>
<!-- ===== Page Wrapper Start ===== -->
<div class="flex h-screen overflow-hidden">
    <?php
    require_once __DIR__ . '/layout/sidebar.php';
    ?>

    <!-- ===== Content Area Start ===== -->
    <div
        class="relative flex flex-col flex-1 overflow-x-hidden overflow-y-auto">
        <!-- Small Device Overlay Start -->
        <div
            @click="sidebarToggle = false"
            :class="sidebarToggle ? 'block lg:hidden' : 'hidden'"
            class="fixed w-full h-screen z-9 bg-gray-900/50"></div>
        <!-- Small Device Overlay End -->
        <?php require_once __DIR__ . '/layout/topbar.php'; ?>
        <main>
            <?php require_once $contenido; ?>
        </main>
    </div>
    <!-- ===== Content Area End ===== -->
</div>
<!-- ===== Page Wrapper End ===== -->
<div class="loader-container loading--show">
    <div class="loader"></div>
    <div class="loaderimg"></div>
    <div class="loader2"></div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
// Cargar scripts específicos de la página si están definidos
if (isset($scripts_pagina) && is_array($scripts_pagina)) {
    foreach ($scripts_pagina as $script_ruta) {
        // Asegúrate de que la ruta sea correcta relativa a tu URL base o raíz del proyecto
        // Asumiendo que tienes una constante BASE_URL o similar definida
        echo '<script src="' . ltrim($script_ruta, '/') . '"></script>' . "\n";
    }
}
require_once __DIR__ . '/layout/footer.php';
