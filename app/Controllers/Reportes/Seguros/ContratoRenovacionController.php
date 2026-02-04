<?php

namespace Micro\Controllers\Reportes\Seguros;

use Exception;
use Micro\Controllers\BaseReporteController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Helpers\Log;
use Micro\Models\Seguros\Renovacion;

class ContratoRenovacionController extends BaseReporteController
{
    public function index(): void
    {
        try {
            $id = $this->input['id'] ?? null;

            if (!$id) {
                $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'ID de renovación no proporcionado'
                ]);
                return;
            }

            $this->idDocument = 47;  // ID del reporte de Contrato de Renovación

            $this->generarReporteConFormato($id, function ($id) {
                $this->generarFormatoGenerico($id);
            });
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
                'mensaje' => "Error al generar el contrato. Código: $codigoError"
            ]);
        }
    }

    /**
     * Genera el formato genérico/base del contrato de renovación.
     * Este se usa cuando no hay formato personalizado definido
     */
    private function generarFormatoGenerico($id): void
    {
        try {
            // Obtener renovación con todas sus relaciones usando Eloquent
            $renovacion = Renovacion::with([
                'cuenta.cliente:idcod_cliente,short_name,no_identifica',
                'cuenta.servicio:id,nombre,costo'
            ])
                ->where('id', $id)
                ->first();

            if (!$renovacion) {
                $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'Renovación no encontrada'
                ]);
                return;
            }

            // Obtener información de la institución
            $info = $this->getInfoInstitucion();

            // Crear PDF base sin encabezado predefinido
            $pdf = $this->crearPDFBase('P', 'Letter');
            $pdf->AddPage();

            // Generar encabezado institucional
            $this->generarEncabezadoInstitucional($pdf, 'CONTRATO DE RENOVACIÓN', $info);

            // Generar cuerpo del comprobante
            $this->generarCuerpoContrato($pdf, $renovacion, $info);

            // Generar pie de página
            $this->generarPieComprobante($pdf, 'Documento generado por el sistema');

            // Generar respuesta con base64
            ob_start();
            $pdf->Output('I', 'ContratoRenovacion.pdf');
            $pdfData = ob_get_contents();
            ob_end_clean();

            $nombreArchivo = "contrato_renovacion_{$id}_" . date('YmdHis') . ".pdf";
            $this->jsonResponse([
                'status' => 1,
                'mensaje' => 'Comprobante generado exitosamente',
                'namefile' => $nombreArchivo,
                'tipo' => 'pdf',
                'data' => base64_encode($pdfData)
            ]);
        } catch (SoftException $se) {
            Log::error("Error soft generando comprobante: " . $se->getMessage());
            $this->jsonResponse([
                'status' => 0,
                'mensaje' => $se->getMessage()
            ]);
        }
    }

    /**
     * Genera el cuerpo del comprobante de renovación
     */
    private function generarCuerpoContrato($pdf, $renovacion, $info)
    {
        $cliente = $renovacion->cuenta->cliente ?? null;
        $servicio = $renovacion->cuenta->servicio ?? null;

        // === TÍTULO DEL DOCUMENTO ===
        $pdf->SetFont($this->fonte, 'B', 14);
        $pdf->Ln(10);
        $pdf->Cell(0, 8, 'CONTRATO DE RENOVACION DE SEGURO', 0, 1, 'C');
        $pdf->Ln(5);

        // === NÚMERO DE CONTRATO ===
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(60, 6, 'No. de Contrato:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 11);
        $pdf->Cell(0, 6, str_pad($renovacion->id, 6, '0', STR_PAD_LEFT), 0, 1, 'L');
        $pdf->Ln(3);

        // === DATOS DEL ASOCIADO ===
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('DATOS DEL ASOCIADO'), 0, 1, 'L');
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Nombre:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, Utf8::decode($cliente->short_name ?? ''), 0, 1, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, Utf8::decode('DPI/Identificación:'), 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(65, 6, $cliente->no_identifica ?? '', 0, 0, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(40, 6, 'No. de Cuenta:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->cuenta->id ?? '', 0, 1, 'L');

        $pdf->Ln(5);

        // === DATOS DE LA RENOVACIÓN ===
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('INFORMACIÓN DE LA RENOVACIÓN'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Tipo de Servicio:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, Utf8::decode($servicio->nombre ?? ''), 0, 1, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Fecha de Inicio:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(65, 6, $renovacion->cuenta->fecha_inicio ?? '', 0, 0, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(40, 6, Utf8::decode('Número de Renovación:'), 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->numero ?? '1', 0, 1, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, Utf8::decode('Fecha de Renovación:'), 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(65, 6, $renovacion->fecha ?? date('d/m/Y'), 0, 0, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(40, 6, 'Vigencia desde:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->fecha_inicio ?? '', 0, 1, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'No. de Recibo:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(65, 6, $renovacion->numdoc ?? '', 0, 0, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(40, 6, 'Vigencia hasta:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->fecha_fin ?? '', 0, 1, 'L');

        $pdf->Ln(5);

        // === INFORMACIÓN FINANCIERA ===
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('INFORMACIÓN FINANCIERA'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Cuota Anual:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(65, 6, Moneda::formato($servicio->costo ?? 0), 0, 0, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(40, 6, 'Monto Pagado:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, Moneda::formato($renovacion->monto ?? 0), 0, 1, 'L');

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Forma de Pago:', 0, 0, 'L');
        $pdf->SetFont($this->fonte, '', 10);
        $formaPago = match ($renovacion->formaPago ?? 'efectivo') {
            'efectivo' => 'Efectivo',
            'banco' => 'Transferencia Bancaria',
            'otro' => 'Otro',
            default => 'Efectivo'
        };
        $pdf->Cell(65, 6, $formaPago, 0, 0, 'L');

        if ($renovacion->formaPago === 'banco' && $renovacion->banco_numdoc) {
            $pdf->SetFont($this->fonte, 'B', 10);
            $pdf->Cell(40, 6, 'No. de Documento:', 0, 0, 'L');
            $pdf->SetFont($this->fonte, '', 10);
            $pdf->Cell(0, 6, $renovacion->banco_numdoc ?? '', 0, 1, 'L');
        } else {
            $pdf->Ln(6);
        }

        $pdf->Ln(5);

        // === BENEFICIARIOS ===
        $beneficiarios = $renovacion->cuenta->beneficiarios ?? [];

        if (count($beneficiarios) > 0) {
            $pdf->SetFont($this->fonte, 'B', 11);
            $pdf->Cell(0, 7, 'BENEFICIARIOS', 0, 1, 'L');
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(5);

            // Encabezado de tabla
            $pdf->SetFont($this->fonte, 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(80, 6, 'Nombre', 1, 0, 'C', true);
            $pdf->Cell(50, 6, 'Parentesco', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Porcentaje', 1, 1, 'C', true);

            // Contenido de beneficiarios
            $pdf->SetFont($this->fonte, '', 9);
            foreach ($beneficiarios as $beneficiario) {
                $nombreCompleto = trim(($beneficiario->nombres ?? '') . ' ' . ($beneficiario->apellidos ?? ''));
                $pdf->Cell(80, 6, Utf8::decode($nombreCompleto), 1, 0, 'L');
                $pdf->Cell(50, 6, Utf8::decode($beneficiario->pivot->parentesco ?? ''), 1, 0, 'C');
                $pdf->Cell(25, 6, ($beneficiario->pivot->porcentaje ?? '0') . '%', 1, 1, 'C');
            }

            $pdf->Ln(5);
        }

        // === TÉRMINOS Y CONDICIONES ===
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('TÉRMINOS Y CONDICIONES'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);

        $pdf->SetFont($this->fonte, '', 9);
        $pdf->MultiCell(0, 5, Utf8::decode(
            "1. El presente contrato de renovación tiene una vigencia de un año a partir de la fecha de inicio.\n\n" .
                "2. La cobertura del seguro estará vigente únicamente si el pago ha sido realizado en su totalidad.\n\n" .
                "3. Los beneficiarios designados recibirán los beneficios según el porcentaje asignado.\n\n" .
                "4. Cualquier modificación a los beneficiarios deberá hacerse mediante documento formal.\n\n" .
                "5. El asociado acepta los términos y condiciones establecidos en el reglamento de seguros."
        ), 0, 'J');

        $pdf->Ln(10);

        // === FIRMAS ===
        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(90, 6, '', 0, 0); // Espaciado
        $pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y'), 0, 1, 'L');
        $pdf->Ln(15);

        // Líneas de firma
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(20, $pdf->GetY(), 90, $pdf->GetY());
        $pdf->Line(120, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);

        $pdf->SetFont($this->fonte, 'B', 9);
        $pdf->Cell(90, 5, 'Firma del Asociado', 0, 0, 'C');
        $pdf->Cell(0, 5, Utf8::decode('Firma y Sello de la Institución'), 0, 1, 'C');

        $pdf->Ln(3);
        $pdf->SetFont($this->fonte, '', 8);
        $pdf->Cell(90, 5, Utf8::decode($cliente->short_name ?? ''), 0, 0, 'C');
        $pdf->Cell(0, 5, Utf8::decode($info['nomb_comple'] ?? ''), 0, 1, 'C');
    }
}
