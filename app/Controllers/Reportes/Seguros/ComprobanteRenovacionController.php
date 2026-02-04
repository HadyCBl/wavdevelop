<?php

namespace Micro\Controllers\Reportes\Seguros;

use Exception;
use Micro\Controllers\BaseReporteController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Helpers\Log;
use Micro\Models\Seguros\Renovacion;

class ComprobanteRenovacionController extends BaseReporteController
{
    public function index()
    {
        try {
            $id = $this->input['id'] ?? null;

            if (!$id) {
                return $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'ID de renovación no proporcionado'
                ]);
            }
            $this->idDocument = 46;  // ID del reporte de comprobante de Renovación

            $this->generarReporteConFormato($id, function ($id) {
                $this->generarFormatoGenerico($id);
            });
        } catch (SoftException $se) {
            Log::error("Error soft generando comprobante: " . $se->getMessage());
            return $this->jsonResponse([
                'status' => 0,
                'mensaje' => $se->getMessage()
            ]);
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            return $this->jsonResponse([
                'status' => 0,
                'mensaje' => "Error al generar comprobante. Código: $codigoError"
            ]);
        }
    }

    private function generarFormatoGenerico($id)
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
            $this->generarEncabezadoInstitucional($pdf, 'COMPROBANTE DE RENOVACIÓN', $info);

            // Generar cuerpo del comprobante
            $this->generarCuerpoComprobante($pdf, $renovacion, $info);

            // Generar pie de página
            $this->generarPieComprobante($pdf, 'Documento generado por el sistema');

            // Generar respuesta con base64
            ob_start();
            $pdf->Output('I', 'comprobante.pdf');
            $pdfData = ob_get_contents();
            ob_end_clean();

            $nombreArchivo = "comprobante_renovacion_{$id}_" . date('YmdHis') . ".pdf";

            return $this->jsonResponse([
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
    private function generarCuerpoComprobante($pdf, $renovacion, $info)
    {
        // Sección: Información de la Renovación
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('DATOS DE LA RENOVACIÓN'), 0, 1, 'L');
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, Utf8::decode('No. Renovación:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->numero, 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, Utf8::decode('No. Documento:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->numdoc ?? 'N/A', 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, Utf8::decode('Fecha Renovación:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, date('d/m/Y', strtotime($renovacion->fecha)), 0, 1);

        $pdf->Ln(4);

        // Sección: Información del Cliente
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('DATOS DEL CLIENTE'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Cliente:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->MultiCell(0, 6, Utf8::decode($renovacion->cuenta->cliente->short_name ?? 'N/A'));

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, Utf8::decode('Identificación:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, $renovacion->cuenta->cliente->no_identifica ?? 'N/A', 0, 1);

        $pdf->Ln(4);

        // Sección: Información del Servicio
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('DATOS DEL SERVICIO'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Servicio:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->MultiCell(0, 6, Utf8::decode($renovacion->cuenta->servicio->nombre ?? 'N/A'));

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Monto:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, Moneda::formato($renovacion->monto), 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Fecha Inicio:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, date('d/m/Y', strtotime($renovacion->fecha_inicio)), 0, 1);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, Utf8::decode('Fecha Fin:'), 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 6, date('d/m/Y', strtotime($renovacion->fecha_fin)), 0, 1);

        $pdf->Ln(4);

        // Sección: Forma de Pago
        $pdf->SetFont($this->fonte, 'B', 11);
        $pdf->Cell(0, 7, Utf8::decode('FORMA DE PAGO'), 0, 1, 'L');
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont($this->fonte, 'B', 10);
        $pdf->Cell(50, 6, 'Tipo de Pago:', 0, 0);
        $pdf->SetFont($this->fonte, '', 10);
        $formaPago = $renovacion->formaPago === 'banco' ? 'Boleta de Banco' : 'Efectivo';
        $pdf->Cell(0, 6, Utf8::decode($formaPago), 0, 1);

        // Si es pago con banco, mostrar detalles
        if ($renovacion->formaPago === 'banco') {
            $pdf->SetFont($this->fonte, 'B', 10);
            $pdf->Cell(50, 6, Utf8::decode('No. Boleta:'), 0, 0);
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
        $pdf->Cell(0, 6, Utf8::decode($estadoTexto), 0, 1);

        // Espacio para firmas
        $pdf->Ln(20);
        $pdf->SetFont($this->fonte, '', 9);
        $pdf->Cell(95, 6, '', 0, 0, 'C');
        $pdf->Cell(95, 6, '', 0, 1, 'C');
        $pdf->Cell(95, 6, '_________________________________', 0, 0, 'C');
        $pdf->Cell(95, 6, '_________________________________', 0, 1, 'C');
        $pdf->Cell(95, 6, Utf8::decode('Firma del Cliente'), 0, 0, 'C');
        $pdf->Cell(95, 6, Utf8::decode('Firma Autorizada'), 0, 1, 'C');
    }
}
