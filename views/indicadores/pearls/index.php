<?php
include_once __DIR__ . '/../../../includes/Config/config.php';
// public/index.php
// Define la ruta de la vista a cargar según el módulo
$contenido = __DIR__ . '/../content/perlasIndexContent.php';

// Verifica que exista antes de incluir
if (!file_exists($contenido)) {
    $contenido = __DIR__ . '/../../../404.php';
}

// Array con los scripts específicos para esta página
$scripts_pagina = [
    BASE_URL . '/includes/js/script.js',
    BASE_URL . '/includes/js/script_pearls.js',
    'https://cdn.jsdelivr.net/npm/chart.js@3.0.0/dist/chart.min.js',
    'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0'
];

// Carga la plantilla principal
require_once __DIR__ . '/../../templates/layout.php';
