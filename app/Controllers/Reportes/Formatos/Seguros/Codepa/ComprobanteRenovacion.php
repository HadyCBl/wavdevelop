<?php

namespace Micro\Controllers\Reportes\Formatos\Seguros\Codepa;

use Exception;
use Micro\Controllers\Reportes\Formatos\BaseFormato;
use Micro\Exceptions\SoftException;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Models\Seguros\Renovacion;
use Micro\Helpers\Log;

/**
 * Formato personalizado de comprobante de Renovación para Codepa
 * 
 * Este es el comprobante personalizado de renovación para la institución Codepa.
 */
class ComprobanteRenovacion extends BaseFormato
{
    /**
     * Genera el comprobante con formato personalizado de la institución
     */
    public function generar($id): void
    {
        try {
            if (!$id) {
                throw new SoftException("ID de renovación no proporcionado");
            }

            // Obtener datos
            $renovacion = $this->obtenerRenovacion($id);
            if (!$renovacion) {
                throw new SoftException("No se encontro la renovacion");
            }

            // Crear PDF con formato personalizado
            $pdf = $this->crearPDFBase('P', 'Letter');
            $pdf->AddPage();

            // Cuerpo del documento
            $this->generarCuerpoPersonalizado($pdf, $renovacion);

            // Generar respuesta
            $this->generarRespuestaPDF($pdf, $id);
        } catch (SoftException $e) {
            Log::error("Error soft generando comprobante: " . $e->getMessage());
            $this->jsonResponse([
                'status' => 0,
                'mensaje' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode(
                $e->getMessage(),
                __FILE__,
                __LINE__,
                $e->getFile(),
                $e->getLine()
            );
            $this->jsonResponse([
                'status' => 0,
                'mensaje' => "Error al generar comprobante. Código: $codigoError"
            ]);
        }
    }

    /**
     * Obtiene los datos de la renovación
     */
    private function obtenerRenovacion($id)
    {
        return Renovacion::with([
            'cuenta.cliente:idcod_cliente,short_name,no_identifica',
            'cuenta.servicio:id,nombre,costo'
        ])
            ->where('id', $id)
            ->first();
    }

    /**
     * Genera el cuerpo del documento con formato personalizado
     */
    private function generarCuerpoPersonalizado($pdf, $renovacion)
    {
        // Sección: Datos del cliente
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('DATOS DE LA RENOVACIÓN'), 0, 1, 'L');
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, ('No. Renovación:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->numero, 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, ('No. Documento:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->numdoc ?? 'N/A', 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, ('Fecha Renovación:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, date('d/m/Y', strtotime($renovacion->fecha)), 0, 1);

        $pdf->Ln(4);

        // Sección: Información del Cliente
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, ('DATOS DEL CLIENTE'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Cliente:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->MultiCell(0, 6, ($renovacion->cuenta->cliente->short_name ?? 'N/A'));

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, ('Identificación:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->cuenta->cliente->no_identifica ?? 'N/A', 0, 1);

        $pdf->Ln(4);

        // Sección: Información del Servicio
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, ('DATOS DEL SERVICIO'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Servicio:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->MultiCell(0, 6, ($renovacion->cuenta->servicio->nombre ?? 'N/A'));

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Monto:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, Moneda::formato($renovacion->monto), 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Fecha Inicio:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, date('d/m/Y', strtotime($renovacion->fecha_inicio)), 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, ('Fecha Fin:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, date('d/m/Y', strtotime($renovacion->fecha_fin)), 0, 1);

        $pdf->Ln(4);

        // Sección: Forma de Pago
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, ('FORMA DE PAGO'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Tipo de Pago:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $formaPago = $renovacion->formaPago === 'banco' ? 'Boleta de Banco' : 'Efectivo';
        $pdf->Cell(0, 6, ($formaPago), 0, 1);

        // Si es pago con banco, mostrar detalles
        if ($renovacion->formaPago === 'banco') {
            $pdf->SetFont($this->fonte, 'B', 10);
            $pdf->Cell(50, 6, ('No. Boleta:'), 0, 0);
            $pdf->SetFont($this->fonte, '', 10);
            $pdf->Cell(0, 6, $renovacion->banco_numdoc ?? 'N/A', 0, 1);

            $pdf->SetFont($this->fonte, 'B', 10);
            $pdf->Cell(50, 6, 'Fecha Boleta:', 0, 0);
            $pdf->SetFont($this->fonte, '', 10);
            $fechaBoleta = $renovacion->banco_fecha ? date('d/m/Y', strtotime($renovacion->banco_fecha)) : 'N/A';
            $pdf->Cell(0, 6, $fechaBoleta, 0, 1);
        }

        $pdf->Ln(4);

        // Estado
        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Estado:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $estadoTexto = strtoupper($renovacion->estado ?? 'VIGENTE');
        $pdf->Cell(0, 6, ($estadoTexto), 0, 1);

        // Espacio para firmas
        $pdf->Ln(20);
        $pdf->SetFont($this->fonte, '', 9);
        $pdf->Cell(95, 6, '', 0, 0, 'C');
        $pdf->Cell(95, 6, '', 0, 1, 'C');
        $pdf->Cell(95, 6, '_________________________________', 0, 0, 'C');
        $pdf->Cell(95, 6, '_________________________________', 0, 1, 'C');
        $pdf->Cell(95, 6, ('Firma del Cliente'), 0, 0, 'C');
        $pdf->Cell(95, 6, ('Firma Autorizada'), 0, 1, 'C');
    }

    /**
     * Genera la respuesta JSON con el PDF codificado
     */
    private function generarRespuestaPDF($pdf, $id): void
    {
        ob_start();
        $pdf->Output('I', 'contrato.pdf');
        $pdfData = ob_get_contents();
        ob_end_clean();

        $nombreArchivo = "contrato_renovacion_{$id}_" . date('YmdHis') . ".pdf";
        $this->jsonResponse([
            'status' => 1,
            'mensaje' => 'Contrato generado exitosamente',
            'namefile' => $nombreArchivo,
            'tipo' => 'pdf',
            'data' => base64_encode($pdfData)
        ]);
    }
}
