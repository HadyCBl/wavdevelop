<?php

namespace Micro\Controllers\Reportes\Formatos\Contabilidad\Cope27;

use Exception;
use Micro\Controllers\Reportes\Contabilidad\LibroMayorController;
use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Helpers\Log;

/**
 * Formato personalizado de Libro Mayor para COPE27
 * 
 */
class LibroMayor extends LibroMayorController
{
    /**
     * Método requerido por BaseReporteController
     * Recibe los datos validados y genera el reporte
     */
    public function generar($data = null): void
    {
        // Asignar datos a $this->dataReporte para que getData() funcione
        if ($data !== null) {
            $this->dataReporte = $data;
        }

        // Llamar al método del padre
        parent::generarFormatoGenericoPdf($data);
    }

    public function generarHeaderPDF($pdf, $titulo, $filtros, $info, $session, $orientacion, $headerAdd = null)
    {
        $pdf->Ln(h: 33);
        $pdf->SetFillColor(41, 128, 185);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', $this->sizeFontHeader);
        $pdf->Cell(0, 5, Utf8::decode($titulo), 0, 1, 'C', true);
        // Llamar función adicional si existe
        if ($headerAdd !== null && is_callable($headerAdd)) {
            $pdf->SetFont($this->fonte, 'B', $this->sizeFontHeader);
            try {
                // $pdf->Ln(5);
                call_user_func($headerAdd, $pdf);
            } catch (Exception $e) {
                Log::error('Error en headerAdd personalizado: ' . $e->getMessage());
            }
        }
    }

    public function generarFooterPDF($pdf, $orientacion)
    {
        // Cope27 no quiere nada en el footer
    }
}
