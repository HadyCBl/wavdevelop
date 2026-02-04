<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta
        name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>
        Indicadores
    </title>
    <link rel="icon" href="favicon.ico">
    <?php

    // echo \Micro\Generic\Asset::style('another');
       echo \Micro\Generic\AssetVite::style('another');
    ?>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/apexcharts.css">
</head>

<body
    :data-theme="darkMode ? 'dark' : 'light'"
    x-data="{ page: 'ecommerce', 'loaded': true, 'darkMode': false, 'stickyMenu': false, 'sidebarToggle': false, 'scrollTop': false }"
    x-init="
         darkMode = JSON.parse(localStorage.getItem('darkMode'));
         $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))"
    :class="{'dark bg-gray-900': darkMode === true}">
    <script>
        const BASE_URL_FOR_JS = "<?= BASE_URL ?>";
    </script>
    <!-- ===== Preloader Start ===== -->
    <div
        x-show="loaded"
        x-init="window.addEventListener('DOMContentLoaded', () => {setTimeout(() => loaded = false, 500)})"
        class="fixed left-0 top-0 z-[999999] flex h-screen w-screen items-center justify-center bg-base-100 dark:bg-gray-950">
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>
    <!-- ===== Preloader End ===== -->