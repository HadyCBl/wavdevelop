<?php

// use Micro\Helpers\Log;

// Log::info("aki tamos ASS", [
//     'url' => BASE_URL
// ]);
// echo \Micro\Generic\Asset::script('another', ['defer' => true]);
// Cargar scripts
echo \Micro\Generic\AssetVite::script('another', [
    'type' => 'module'
]);

echo \Micro\Generic\AssetVite::script('reportes', [
    'type' => 'module'
]);

// Debug info (solo en desarrollo)
if (!$isProduction) {
    echo \Micro\Generic\AssetVite::debug();
}

?>

<script defer src="<?= BASE_URL; ?>/assets/js/apexcharts.min.js"></script>
</body>

</html>