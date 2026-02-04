<?php

namespace Micro\Controllers\Reportes\Config;

/**
 * Configuración base para reportes
 */
abstract class BaseReporteConfig
{
    /**
     * Query SQL con placeholders
     */
    abstract public function getQuery(): string;

    /**
     * Campos requeridos para validación
     */
    abstract public function getCamposRequeridos(): array;

    /**
     * Columnas del reporte con sus configuraciones
     */
    abstract public function getColumnas(): array;

    /**
     * Título del reporte
     */
    abstract public function getTitulo(): string;

    /**
     * Formatos disponibles
     */
    public function getFormatosDisponibles(): array
    {
        return ['xlsx', 'pdf', 'json'];
    }

    /**
     * ¿Tiene totales?
     */
    public function tieneTotales(): bool
    {
        return false;
    }

    /**
     * Columnas a totalizar
     */
    public function getColumnasTotales(): array
    {
        return [];
    }

    /**
     * ¿Tiene subtotales por grupo?
     */
    public function tieneSubtotales(): bool
    {
        return false;
    }

    /**
     * Columna para agrupar subtotales
     */
    public function getColumnaAgrupacion(): ?string
    {
        return null;
    }

    /**
     * Ancho de columnas para Excel (opcional)
     */
    public function getAnchosColumnas(): array
    {
        return [];
    }

    /**
     * Formato de números (opcional)
     */
    public function getFormatoNumeros(): string
    {
        return '#,##0.00';
    }

    /**
     * Orientación PDF (P=vertical, L=horizontal)
     */
    public function getOrientacionPDF(): string
    {
        return 'P';
    }

    /**
     * Procesamiento adicional de datos antes de exportar
     */
    public function procesarDatos(array $datos): array
    {
        return $datos;
    }

    /**
     * Información adicional para mostrar en el reporte
     */
    public function getInfoAdicional(array $filtros): array
    {
        return [];
    }
}
