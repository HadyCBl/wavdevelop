<?php

namespace Micro\Controllers\Reportes\Formatos\Contabilidad\Ammi;

use Exception;
use Micro\Controllers\Reportes\Contabilidad\LibroMayorController;
use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Helpers\Log;

/**
 * Formato personalizado de Libro Mayor para AMMI
 */
class LibroMayor extends LibroMayorController
{
    // Configuración personalizada
    protected $sizeFontHeader = 7;
    protected $sizeFontBody = 6;
    protected $sizeLine = 3;

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
        $pdf->SetFont($this->fonte, '', 5);
        $pdf->Cell(0, 3, 'Generado: ' . date('Y-m-d H:i:s'), 0, 1, 'R');

        $pdf->SetFont($this->fonte, 'B', $this->sizeFontHeader + 2);
        $pdf->Cell(0, 5, Utf8::decode($info['nomb_comple']), 0, 1, 'C');

        $pdf->SetFillColor(41, 128, 185);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', $this->sizeFontHeader + 1);
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
}
