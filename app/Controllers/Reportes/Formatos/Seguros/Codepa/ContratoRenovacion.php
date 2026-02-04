<?php

namespace Micro\Controllers\Reportes\Formatos\Seguros\Codepa;

use Exception;
use Micro\Controllers\Reportes\Formatos\BaseFormato;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Models\Seguros\Renovacion;
use Micro\Helpers\Log;

/**
 * Formato personalizado de Contrato de Renovación para Codepa
 * 
 * Este es el contrato personalizado de renovación para la institución Codepa.
 */
class ContratoRenovacion extends BaseFormato
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
                throw new SoftException("Renovación no encontrada");
            }

            $info = $this->getInfoInstitucion();

            // Crear PDF con formato personalizado
            $pdf = $this->crearPDFBase('P', 'Letter');
            $pdf->AddPage();

            // Cuerpo del documento
            $this->generarCuerpoPersonalizado($pdf, $renovacion, $info);

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
            'cuenta:id,fecha_inicio,id_cliente,id_servicio',
            'cuenta.cliente:idcod_cliente,short_name,no_identifica,date_birth',
            'cuenta.servicio:id,nombre,costo',
            'cuenta.beneficiarios' => function ($q) {
                $q->select(
                    'cli_beneficiarios.id',
                    'nombres',
                    'apellidos',
                    'aux_beneficiarios.porcentaje',
                    'tb_parentescos.descripcion as parentesco'
                )
                    ->leftJoin(
                        'tb_parentescos',
                        'tb_parentescos.id',
                        '=',
                        'aux_beneficiarios.parentesco'
                    );
            }
        ])
            ->where('id', $id)
            ->first();
    }


    /**
     * Genera el cuerpo del documento con formato personalizado
     * IMPORTANTE: Solo genera los datos para llenar un formato pre-impreso
     */
    private function generarCuerpoPersonalizado($pdf, $renovacion, $info)
    {
        $cliente = $renovacion->cuenta->cliente ?? null;
        $servicio = $renovacion->cuenta->servicio ?? null;
        Log::info("cliente datos: " . print_r($renovacion, true));
        $edadCliente = ($cliente && $cliente->date_birth && Date::isValid($cliente->date_birth))
            ? Date::calculateAge($cliente->date_birth)
            : '';

        // === CONFIGURACIÓN INICIAL ===
        $pdf->SetY(22); // Posición inicial vertical

        // // === NÚMERO DE CONTRATO (esquina superior derecha) ===
        // $pdf->SetFont('Arial', 'B', 11);
        // $pdf->Cell(168, 6, '', 0, 0); // Espacio izquierdo
        // $pdf->Cell(35, 6, str_pad($renovacion->id, 6, '0', STR_PAD_LEFT), 0, 1, 'C');

        $pdf->Ln(22); // Salto a línea 50

        // === NOMBRE DEL ASOCIADO Y DPI ===
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(8, 5, '', 0, 0); // Margen izquierdo 18mm (8 + 10 del margen default)
        $pdf->Cell(130, 5, $this->decode($cliente->short_name ?? ''), 0, 0, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(19, 5, '', 0, 0); // Espacio entre campos
        $pdf->Cell(38, 5, $cliente->no_identifica ?? '', 0, 1, 'L');

        $pdf->Ln(7); // Salto a línea 62

        // === SEGUNDA FILA: No. DE CUENTA, FECHA DE NACIMIENTO, EDAD ===
        $pdf->Cell(25, 5, '', 0, 0); // Margen a posición 35
        $pdf->Cell(30, 5, $renovacion->cuenta->id ?? '', 0, 0, 'C');

        $pdf->Cell(30, 5, '', 0, 0); // Espacio a posición 95
        $pdf->Cell(30, 5, $cliente->date_birth ?? '', 0, 0, 'C');

        $pdf->Cell(30, 5, '', 0, 0); // Espacio a posición 155
        $pdf->Cell(25, 5, $edadCliente ?? '', 0, 1, 'C');

        $pdf->Ln(7); // Salto a línea 74

        // === TERCERA FILA: FECHAS ===
        $pdf->Cell(25, 5, '', 0, 0); // Margen a posición 35
        $pdf->Cell(30, 5, $renovacion->cuenta->fecha_inicio ?? '', 0, 0, 'C');

        // Fecha de Emisión (fecha actual)
        $pdf->Cell(20, 5, '', 0, 0); // Espacio a posición 85
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 5, date('d/m/Y'), 0, 0, 'C');

        // Fecha inicio vigencia
        $pdf->Cell(35, 5, '', 0, 0); // Espacio a posición 150
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(30, 5, $renovacion->fecha_inicio ?? date('d/m/Y'), 0, 0, 'C');

        // Fecha vencimiento (agregar 1 año)
        $pdf->Cell(5, 5, '', 0, 0); // Espacio a posición 185
        $fechaVencimiento = date('d/m/Y', strtotime('+1 year'));
        $pdf->Cell(20, 5, $fechaVencimiento, 0, 1, 'C');

        $pdf->Ln(7); // Salto a línea 86

        // === CUARTA FILA: PLAN CUOTA ANUAL, COBERTURA, NO DE RECIBO ===
        $pdf->Cell(25, 5, '', 0, 0); // Margen a posición 35
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 5, $this->formatoMoneda($servicio->costo ?? 0), 0, 0, 'C');

        $pdf->Cell(20, 5, '', 0, 0); // Espacio a posición 85
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, $renovacion->cobertura ?? 'cobertura', 0, 0, 'C');

        $pdf->Cell(25, 5, '', 0, 0); // Espacio a posición 170
        $pdf->Cell(35, 5, $renovacion->numdoc ?? '', 0, 1, 'C');

        $pdf->Ln(19); // Salto a línea 110

        // === BENEFICIARIOS (3 líneas) ===
        $beneficiarios = $renovacion->cuenta->beneficiarios ?? [];

        foreach ($beneficiarios as $index => $beneficiario) {
            if ($index >= 3) break; // Máximo 3 beneficiarios

            // Nombre del beneficiario
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(5, 5, '', 0, 0); // Margen a posición 15
            $pdf->Cell(130, 5, $this->decode($beneficiario['nombres'] ?? '') . ' ' . $this->decode($beneficiario['apellidos'] ?? ''), 0, 0, 'L');

            // Parentesco
            $pdf->Cell(5, 5, '', 0, 0); // Espacio a posición 150
            $pdf->Cell(30, 5, $this->decode($beneficiario['parentesco'] ?? ''), 0, 0, 'C');

            // Porcentaje
            $pdf->Cell(5, 5, '', 0, 0); // Espacio a posición 185
            $pdf->Cell(20, 5, ($beneficiario['porcentaje'] ?? '') . '%', 0, 1, 'C');

            $pdf->Ln(2); // Espacio entre beneficiarios
        }
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
