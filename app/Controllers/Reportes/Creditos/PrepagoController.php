<?php

namespace Micro\Controllers\Reportes\Creditos;

use Micro\Controllers\BaseReporteController;
use Micro\Helpers\Log;
use Exception;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;

/**
 * Controlador de Reportes de Créditos
 */
class PrepagoController extends BaseReporteController
{
    /**
     * Reporte de visitas prepago
     * POST /api/reportes/creditos/visitas-prepago
     */
    protected $widthsColumns = [
        'codigo_cuenta' => 30,
        'nombre_cliente' => 75,
        'fecha' => 18,
        'saldo' => 25,
        'mora' => 22,
        'pag2' => 22,
        'cuota' => 22,
        'capital' => 25,
        'interes' => 25
    ];
    protected $filtros = [];
    protected $dataReporte = [];
    public function visitasPrepago()
    {
        // Log::info("inputs", $this->input);
        $status = false;
        try {
            $data = [
                'radio_agencia' => trim($this->input['ragencia'] ?? ''),
                'creRegionSelect' => trim($this->input['regionid'] ?? ''),
                'creAgenciaSelect' => trim($this->input['codofi'] ?? ''),
                'creUserSelect' => trim($this->input['codanal'] ?? ''),
                'fecha_inicio' => $this->input['fechaInicio'] ?? null,
                'fecha_fin' => $this->input['fechaFinal'] ?? null,
                'tipo' => $this->input['tipo'] ?? 'pdf',
            ];

            $rules = [
                'radio_agencia' => 'required|string',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date'
            ];

            if ($data['radio_agencia'] == 'anyofi') {
                $rules['creAgenciaSelect'] = 'required|integer|min:1|exists:tb_agencia,id_agencia';
            }
            if ($data['radio_agencia'] == 'anyasesor') {
                $rules['creUserSelect'] = 'required|integer|min:1|exists:tb_usuario,id_usu';
            }
            if ($data['radio_agencia'] == 'anyregion') {
                $rules['creRegionSelect'] = 'required|integer|min:1|exists:cre_regiones,id';
            }

            $messages = [
                'creRegionSelect.min' => 'Seleccione una región válida.',
            ];

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            $this->dataReporte = $data;

            $tipo = $this->input['tipo'] ?? 'pdf';
            if ($tipo === 'pdf' || $tipo === 'show') {
                $this->reportePDF();
            } elseif ($tipo === 'xlsx') {
                $this->reporteExcel();
            } else {
                throw new SoftException('Tipo de reporte no soportado.');
            }
            $status = true;
        } catch (SoftException $se) {
            $mensaje = "Advertencia: " . $se->getMessage();
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        }
        if (!$status) {
            echo json_encode([
                'status' => 0,
                'mensaje' => $mensaje
            ]);
            exit;
        }
    }
    /**
     * Genera reporte PDF con plantilla base
     */
    public function reportePDF()
    {
        $showmensaje = false;
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();

            $title = 'REPORTE DE VISITAS PREPAGO ' . @date('d/m/Y', @strtotime($this->input['fechaInicio'])) . ' AL ' . @date('d/m/Y', @strtotime($this->input['fechaFinal']));

            $headerExtra = function ($pdf) {
                $this->addHeaderExtraPDF($pdf);
            };

            $bodyFunc = function ($pdf, $datos) {
                $this->bodyPDF($pdf, $datos);
            };

            // 3. Generar PDF con plantilla
            $response = $this->generarPlantillaPDF(
                $title,
                $bodyFunc,
                $headerExtra,
                $datos,
                $this->filtros,
                'L' // Orientación horizontal (Landscape)
            );

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }

    protected function bodyPDF($pdf, $datos)
    {
        $tamanio_linea = 5;

        $pdf->Ln(5);

        $controlAccount = null;
        $controlGroup = null;
        $totalesGrupo = [
            'saldo' => 0,
            'mora' => 0,
            'pag2' => 0,
            'cuota' => 0,
            'capital' => 0,
            'interes' => 0
        ];
        $totalesPorTipo = [
            'INDIVIDUALES' => [
                'saldo' => 0,
                'mora' => 0,
                'pag2' => 0,
                'cuota' => 0,
                'capital' => 0,
                'interes' => 0
            ],
            'GRUPALES' => [
                'saldo' => 0,
                'mora' => 0,
                'pag2' => 0,
                'cuota' => 0,
                'capital' => 0,
                'interes' => 0
            ]
        ];

        foreach ($datos as $index => $dato) {
            $saldo = Moneda::formato($dato["saldo"] ?? 0, '');
            $mora = Moneda::formato($dato["mora"] ?? 0, '');
            $pag2 = Moneda::formato($dato["pag2"] ?? 0, '');
            $cuota = Moneda::formato($dato["cuota"] ?? 0, '');
            $capital = Moneda::formato($dato["capital"] ?? 0, '');
            $interes = Moneda::formato($dato["interes"] ?? 0, '');

            $nombreGrupo = ($dato['TipoEnti'] == 'GRUP') ? $dato['NombreGrupo'] : 'INDIVIDUALES';

            $dato['nombre'] = isset($dato['nombre']) ? mb_convert_case(trim($dato['nombre']), MB_CASE_TITLE, "UTF-8") : '';
            if ($controlAccount === $dato['cuenta']) {
                $dato['cuenta'] = ' ';
                $dato['nombre'] = ' ';
            } else {
                $controlAccount = $dato['cuenta'];
            }


            if ($controlGroup !== $nombreGrupo) {
                // Imprimir totales del grupo anterior
                if ($controlGroup !== null) {
                    $this->imprimirTotalesGrupo($pdf, $totalesGrupo);
                    // Reiniciar totales
                    $totalesGrupo = [
                        'saldo' => 0,
                        'mora' => 0,
                        'pag2' => 0,
                        'cuota' => 0,
                        'capital' => 0,
                        'interes' => 0
                    ];
                }

                // nuevo grupo
                $controlGroup = $nombreGrupo;
                // imprimir nombre de grupo
                $pdf->SetFont('Courier', 'B', 9);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(0, 6, Utf8::decode($nombreGrupo), 0, 1, 'L', false);
                // línea de separación
                $pdf->SetDrawColor(200, 200, 200);
                $pdf->SetLineWidth(0.3);
                $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - 20, $pdf->GetY());
                $pdf->SetFont('Courier', '', 9);
                $pdf->Ln(2);
            }

            // Acumular totales del grupo
            $totalesGrupo['saldo'] += floatval($dato["saldo"] ?? 0);
            $totalesGrupo['mora'] += floatval($dato["mora"] ?? 0);
            $totalesGrupo['pag2'] += floatval($dato["pag2"] ?? 0);
            $totalesGrupo['cuota'] += floatval($dato["cuota"] ?? 0);
            $totalesGrupo['capital'] += floatval($dato["capital"] ?? 0);
            $totalesGrupo['interes'] += floatval($dato["interes"] ?? 0);

            // Acumular totales por tipo de entidad
            $tipoKey = ($dato['TipoEnti'] == 'GRUP') ? 'GRUPALES' : 'INDIVIDUALES';
            $totalesPorTipo[$tipoKey]['saldo'] += floatval($dato["saldo"] ?? 0);
            $totalesPorTipo[$tipoKey]['mora'] += floatval($dato["mora"] ?? 0);
            $totalesPorTipo[$tipoKey]['pag2'] += floatval($dato["pag2"] ?? 0);
            $totalesPorTipo[$tipoKey]['cuota'] += floatval($dato["cuota"] ?? 0);
            $totalesPorTipo[$tipoKey]['capital'] += floatval($dato["capital"] ?? 0);
            $totalesPorTipo[$tipoKey]['interes'] += floatval($dato["interes"] ?? 0);

            $pdf->CellFit($this->widthsColumns['codigo_cuenta'], $tamanio_linea + 1, $dato['cuenta'], 0, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, Utf8::decode($dato['nombre']), 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['fecha'], $tamanio_linea + 1, Date::toDMY($dato['fecha']), 0, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['saldo'], $tamanio_linea + 1, $saldo, 0, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, $mora, 0, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['pag2'], $tamanio_linea + 1, $pag2, 0, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['cuota'], $tamanio_linea + 1, $cuota, 0, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, $capital, 0, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, $interes, 0, 0, 'R', 0, '', 1, 0);
            $pdf->Ln(5);
        }

        // Imprimir totales del último grupo
        if ($controlGroup !== null) {
            $this->imprimirTotalesGrupo($pdf, $totalesGrupo);
        }

        // Imprimir resumen por tipo de entidad
        $this->imprimirResumenPorTipo($pdf, $totalesPorTipo);
    }

    /**
     * Imprime la línea de totales para un grupo
     */
    private function imprimirTotalesGrupo($pdf, $totales)
    {
        $tamanio_linea = 5;

        // Línea de separación antes del total
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - 20, $pdf->GetY());
        $pdf->Ln(2);

        // Estilo para totales
        $pdf->SetFont('Courier', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);

        // Espacios vacíos para alinear con las columnas de datos
        $pdf->CellFit($this->widthsColumns['codigo_cuenta'], $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'SUBTOTAL:', 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['fecha'], $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);

        // Totales formateados
        $pdf->CellFit($this->widthsColumns['saldo'], $tamanio_linea + 1, Moneda::formato($totales['saldo'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, Moneda::formato($totales['mora'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['pag2'], $tamanio_linea + 1, Moneda::formato($totales['pag2'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['cuota'], $tamanio_linea + 1, Moneda::formato($totales['cuota'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, Moneda::formato($totales['capital'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Moneda::formato($totales['interes'], ''), 0, 0, 'R', 0, '', 1, 0);

        $pdf->Ln(5);

        // Restaurar fuente normal
        $pdf->SetFont('Courier', '', 9);
        $pdf->Ln(3);
    }

    /**
     * Imprime el resumen por tipo de entidad (INDIVIDUALES/GRUPALES)
     */
    private function imprimirResumenPorTipo($pdf, $totalesPorTipo)
    {
        $tamanio_linea = 5;

        // Espacio antes del resumen
        $pdf->Ln(8);

        // Título del resumen
        $pdf->SetFont('Courier', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 7, 'RESUMEN', 0, 1, 'C', false);
        $pdf->Ln(3);

        // Línea de separación superior
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - 20, $pdf->GetY());
        $pdf->Ln(3);

        // Encabezados del resumen
        $pdf->SetFont('Courier', 'B', 9);
        $pdf->CellFit($this->widthsColumns['codigo_cuenta'] + $this->widthsColumns['nombre_cliente'] + $this->widthsColumns['fecha'], $tamanio_linea + 1, 'TIPO', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo'], $tamanio_linea + 1, 'SALDO', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, 'MORA', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['pag2'], $tamanio_linea + 1, 'OTROS', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['cuota'], $tamanio_linea + 1, 'CUOTA', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, 'CAPITAL', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Utf8::decode('INTERÉS'), 1, 0, 'C', 0, '', 1, 0);
        $pdf->Ln(5);

        // Datos del resumen
        $pdf->SetFont('Courier', '', 9);

        foreach ($totalesPorTipo as $tipo => $totales) {
            // Solo mostrar si tiene valores
            if (
                $totales['saldo'] > 0 || $totales['mora'] > 0 || $totales['pag2'] > 0 ||
                $totales['cuota'] > 0 || $totales['capital'] > 0 || $totales['interes'] > 0
            ) {

                $pdf->CellFit($this->widthsColumns['codigo_cuenta'] + $this->widthsColumns['nombre_cliente'] + $this->widthsColumns['fecha'], $tamanio_linea + 1, $tipo, 0, 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo'], $tamanio_linea + 1, Moneda::formato($totales['saldo'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, Moneda::formato($totales['mora'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['pag2'], $tamanio_linea + 1, Moneda::formato($totales['pag2'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['cuota'], $tamanio_linea + 1, Moneda::formato($totales['cuota'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, Moneda::formato($totales['capital'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Moneda::formato($totales['interes'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->Ln(5);
            }
        }

        // Total general
        $totalGeneral = [
            'saldo' => $totalesPorTipo['INDIVIDUALES']['saldo'] + $totalesPorTipo['GRUPALES']['saldo'],
            'mora' => $totalesPorTipo['INDIVIDUALES']['mora'] + $totalesPorTipo['GRUPALES']['mora'],
            'pag2' => $totalesPorTipo['INDIVIDUALES']['pag2'] + $totalesPorTipo['GRUPALES']['pag2'],
            'cuota' => $totalesPorTipo['INDIVIDUALES']['cuota'] + $totalesPorTipo['GRUPALES']['cuota'],
            'capital' => $totalesPorTipo['INDIVIDUALES']['capital'] + $totalesPorTipo['GRUPALES']['capital'],
            'interes' => $totalesPorTipo['INDIVIDUALES']['interes'] + $totalesPorTipo['GRUPALES']['interes']
        ];

        // Línea de separación antes del total
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - 20, $pdf->GetY());
        $pdf->Ln(2);

        $pdf->SetFont('Courier', 'B', 9);
        $pdf->CellFit($this->widthsColumns['codigo_cuenta'] + $this->widthsColumns['nombre_cliente'] + $this->widthsColumns['fecha'], $tamanio_linea + 1, 'TOTAL GENERAL', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo'], $tamanio_linea + 1, Moneda::formato($totalGeneral['saldo'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, Moneda::formato($totalGeneral['mora'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['pag2'], $tamanio_linea + 1, Moneda::formato($totalGeneral['pag2'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['cuota'], $tamanio_linea + 1, Moneda::formato($totalGeneral['cuota'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, Moneda::formato($totalGeneral['capital'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Moneda::formato($totalGeneral['interes'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(5);
    }

    protected function addHeaderExtraPDF($pdf)
    {
        $tamanio_linea = 5;

        //Fuente
        $pdf->SetFont('Courier', '', 9);
        $pdf->SetTextColor(0, 0, 0);

        // $pdf->Ln(5);
        $pdf->CellFit($this->widthsColumns['codigo_cuenta'], $tamanio_linea + 1, Utf8::decode("CÓDIGO CUENTA"), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'NOMBRE CLIENTE', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['fecha'], $tamanio_linea + 1, 'FECHA', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo'], $tamanio_linea + 1, 'SALDO', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, 'MORA', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['pag2'], $tamanio_linea + 1, 'OTROS', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['cuota'], $tamanio_linea + 1, 'CUOTA', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, 'CAPITAL', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Utf8::decode('INTERÉS'), 1, 0, 'C', 0, '', 1, 0);
        $pdf->Ln(5);
    }

    /**
     * Genera reporte Excel con plantilla base
     */
    public function reporteExcel()
    {
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();

            // 2. Preparar filtros
            $filtros = [
                'Periodo' => date('d/m/Y', strtotime($this->input['fechaInicio'])) . ' al ' . date('d/m/Y', strtotime($this->input['fechaFinal'])),
                'Estado' => 'Activos'
            ];

            $title = 'REPORTE DE VISITAS PREPAGO ' . date('d/m/Y', strtotime($this->input['fechaInicio'])) . ' AL ' . date('d/m/Y', strtotime($this->input['fechaFinal']));

            $bodyFunc = function ($sheet, $row, $datos) {
                return $this->bodyExcel($sheet, $row, $datos);
            };
            // 3. Generar Excel con plantilla
            $response = $this->generarPlantillaExcel(
                $title,
                $bodyFunc,
                $datos,
                $filtros
            );

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }

    protected function bodyExcel($sheet, $row, $datos)
    {
        // Column mapping
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $keysToColumns = [
            'codigo_cuenta' => 'A',
            'nombre_cliente' => 'B',
            'fecha' => 'C',
            'saldo' => 'D',
            'mora' => 'E',
            'pag2' => 'F',
            'cuota' => 'G',
            'capital' => 'H',
            'interes' => 'I'
        ];

        // Ajustar anchos de columnas aproximando desde $this->widthsColumns
        foreach ($keysToColumns as $key => $col) {
            $width = $this->widthsColumns[$key] ?? 15;
            // escala para PhpSpreadsheet (aprox)
            $sheet->getColumnDimension($col)->setWidth(max(6, round($width / 6)));
        }

        // Encabezados (misma disposición que el PDF)
        $sheet->setCellValue("A{$row}", "CÓDIGO CUENTA");
        $sheet->setCellValue("B{$row}", 'NOMBRE COMPLETO');
        $sheet->setCellValue("C{$row}", 'FECHA');
        $sheet->setCellValue("D{$row}", 'SALDO');
        $sheet->setCellValue("E{$row}", 'MORA');
        $sheet->setCellValue("F{$row}", 'OTROS');
        $sheet->setCellValue("G{$row}", 'CUOTA');
        $sheet->setCellValue("H{$row}", 'CAPITAL');
        $sheet->setCellValue("I{$row}", "INTERÉS");

        // Estilo encabezado
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF34495E');

        $row++;

        // Datos: lógica similar al PDF con agrupación por grupo
        $controlAccount = null;
        $controlGroup = null;
        $totalesGrupo = [
            'saldo' => 0,
            'mora' => 0,
            'pag2' => 0,
            'cuota' => 0,
            'capital' => 0,
            'interes' => 0
        ];
        $totalesGenerales = [
            'saldo' => 0,
            'mora' => 0,
            'pag2' => 0,
            'cuota' => 0,
            'capital' => 0,
            'interes' => 0
        ];
        $totalesPorTipo = [
            'INDIVIDUALES' => [
                'saldo' => 0,
                'mora' => 0,
                'pag2' => 0,
                'cuota' => 0,
                'capital' => 0,
                'interes' => 0
            ],
            'GRUPALES' => [
                'saldo' => 0,
                'mora' => 0,
                'pag2' => 0,
                'cuota' => 0,
                'capital' => 0,
                'interes' => 0
            ]
        ];

        foreach ($datos as $item) {
            $fecha = isset($item['fecha']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($item['fecha'])) : '';
            $cuenta = $item['cuenta'] ?? '';
            $nombre = isset($item['nombre']) ? mb_convert_case(trim($item['nombre']), MB_CASE_TITLE, "UTF-8") : '';
            $saldo = floatval($item['saldo'] ?? 0);
            $mora = floatval($item['mora'] ?? 0);
            $pag2 = floatval($item['pag2'] ?? 0);
            $cuota = floatval($item['cuota'] ?? 0);
            $capital = floatval($item['capital'] ?? 0);
            $interes = floatval($item['interes'] ?? 0);

            $nombreGrupo = ($item['TipoEnti'] == 'GRUP') ? $item['NombreGrupo'] : 'INDIVIDUAL';

            // Detectar cambio de grupo
            if ($controlGroup !== $nombreGrupo) {
                // Imprimir subtotales del grupo anterior
                if ($controlGroup !== null) {
                    $row = $this->imprimirSubtotalExcel($sheet, $row, $totalesGrupo);
                    // Reiniciar totales del grupo
                    $totalesGrupo = [
                        'saldo' => 0,
                        'mora' => 0,
                        'pag2' => 0,
                        'cuota' => 0,
                        'capital' => 0,
                        'interes' => 0
                    ];
                }

                // Imprimir encabezado del nuevo grupo
                $controlGroup = $nombreGrupo;
                $sheet->setCellValue("A{$row}", strtoupper($nombreGrupo));
                $sheet->mergeCells("A{$row}:I{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8E8E8');
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $row++;
            }

            // Control de cuenta para ocultar repeticiones
            if ($controlAccount === $cuenta) {
                $cuenta = '';
                $nombre = '';
            } else {
                $controlAccount = $cuenta;
            }

            // Datos de la fila
            $sheet->setCellValue("A{$row}", $cuenta);
            $sheet->setCellValue("B{$row}", $nombre);

            // Fecha
            if ($fecha !== '') {
                $sheet->setCellValue("C{$row}", $fecha);
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            } else {
                $sheet->setCellValue("C{$row}", '');
            }

            // Valores numéricos con formato moneda
            $sheet->setCellValue("D{$row}", $saldo);
            $sheet->setCellValue("E{$row}", $mora);
            $sheet->setCellValue("F{$row}", $pag2);
            $sheet->setCellValue("G{$row}", $cuota);
            $sheet->setCellValue("H{$row}", $capital);
            $sheet->setCellValue("I{$row}", $interes);

            $sheet->getStyle("D{$row}:I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');

            // Acumular totales del grupo
            $totalesGrupo['saldo'] += $saldo;
            $totalesGrupo['mora'] += $mora;
            $totalesGrupo['pag2'] += $pag2;
            $totalesGrupo['cuota'] += $cuota;
            $totalesGrupo['capital'] += $capital;
            $totalesGrupo['interes'] += $interes;

            // Acumular totales generales
            $totalesGenerales['saldo'] += $saldo;
            $totalesGenerales['mora'] += $mora;
            $totalesGenerales['pag2'] += $pag2;
            $totalesGenerales['cuota'] += $cuota;
            $totalesGenerales['capital'] += $capital;
            $totalesGenerales['interes'] += $interes;

            // Acumular totales por tipo de entidad
            $tipoKey = ($item['TipoEnti'] == 'GRUP') ? 'GRUPALES' : 'INDIVIDUALES';
            $totalesPorTipo[$tipoKey]['saldo'] += $saldo;
            $totalesPorTipo[$tipoKey]['mora'] += $mora;
            $totalesPorTipo[$tipoKey]['pag2'] += $pag2;
            $totalesPorTipo[$tipoKey]['cuota'] += $cuota;
            $totalesPorTipo[$tipoKey]['capital'] += $capital;
            $totalesPorTipo[$tipoKey]['interes'] += $interes;

            $row++;
        }

        // Imprimir subtotales del último grupo
        if ($controlGroup !== null) {
            $row = $this->imprimirSubtotalExcel($sheet, $row, $totalesGrupo);
        }

        // Fila de total general
        $row++; // Espacio adicional
        $sheet->setCellValue("A{$row}", 'TOTAL GENERAL:');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$row}:C{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF34495E');
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');

        $sheet->setCellValue("D{$row}", $totalesGenerales['saldo']);
        $sheet->setCellValue("E{$row}", $totalesGenerales['mora']);
        $sheet->setCellValue("F{$row}", $totalesGenerales['pag2']);
        $sheet->setCellValue("G{$row}", $totalesGenerales['cuota']);
        $sheet->setCellValue("H{$row}", $totalesGenerales['capital']);
        $sheet->setCellValue("I{$row}", $totalesGenerales['interes']);

        $sheet->getStyle("D{$row}:I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("D{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("D{$row}:I{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF34495E');
        $sheet->getStyle("D{$row}:I{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');

        // Imprimir resumen por tipo de entidad
        $row = $this->imprimirResumenPorTipoExcel($sheet, $row, $totalesPorTipo);

        return $row;
    }

    /**
     * Imprime la fila de subtotales para un grupo en Excel
     */
    private function imprimirSubtotalExcel($sheet, $row, $totales)
    {
        // Línea de subtotal
        $sheet->setCellValue("A{$row}", 'SUBTOTAL:');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:C{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCDCDC');

        $sheet->setCellValue("D{$row}", $totales['saldo']);
        $sheet->setCellValue("E{$row}", $totales['mora']);
        $sheet->setCellValue("F{$row}", $totales['pag2']);
        $sheet->setCellValue("G{$row}", $totales['cuota']);
        $sheet->setCellValue("H{$row}", $totales['capital']);
        $sheet->setCellValue("I{$row}", $totales['interes']);

        $sheet->getStyle("D{$row}:I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("D{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("D{$row}:I{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCDCDC');

        $row++; // Espacio después del subtotal

        return $row;
    }

    /**
     * Imprime el resumen por tipo de entidad en Excel
     */
    private function imprimirResumenPorTipoExcel($sheet, $row, $totalesPorTipo)
    {
        $row += 3; // Espacio antes del resumen

        // Título del resumen
        $sheet->setCellValue("A{$row}", 'RESUMEN');
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF95A5A6');
        $row++;

        // Encabezados del resumen
        $sheet->setCellValue("A{$row}", 'TIPO');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", 'SALDO');
        $sheet->setCellValue("E{$row}", 'MORA');
        $sheet->setCellValue("F{$row}", 'OTROS');
        $sheet->setCellValue("G{$row}", 'CUOTA');
        $sheet->setCellValue("H{$row}", 'CAPITAL');
        $sheet->setCellValue("I{$row}", 'INTERÉS');

        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF34495E');
        $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        // Datos del resumen
        foreach ($totalesPorTipo as $tipo => $totales) {
            // Solo mostrar si tiene valores
            if (
                $totales['saldo'] > 0 || $totales['mora'] > 0 || $totales['pag2'] > 0 ||
                $totales['cuota'] > 0 || $totales['capital'] > 0 || $totales['interes'] > 0
            ) {

                $sheet->setCellValue("A{$row}", $tipo);
                $sheet->mergeCells("A{$row}:C{$row}");
                $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:C{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF8F9F9');

                $sheet->setCellValue("D{$row}", $totales['saldo']);
                $sheet->setCellValue("E{$row}", $totales['mora']);
                $sheet->setCellValue("F{$row}", $totales['pag2']);
                $sheet->setCellValue("G{$row}", $totales['cuota']);
                $sheet->setCellValue("H{$row}", $totales['capital']);
                $sheet->setCellValue("I{$row}", $totales['interes']);

                $sheet->getStyle("D{$row}:I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
                $sheet->getStyle("D{$row}:I{$row}")->getFont()->setBold(true);
                $sheet->getStyle("D{$row}:I{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF8F9F9');

                $row++;
            }
        }

        // Total general del resumen
        $totalGeneral = [
            'saldo' => $totalesPorTipo['INDIVIDUALES']['saldo'] + $totalesPorTipo['GRUPALES']['saldo'],
            'mora' => $totalesPorTipo['INDIVIDUALES']['mora'] + $totalesPorTipo['GRUPALES']['mora'],
            'pag2' => $totalesPorTipo['INDIVIDUALES']['pag2'] + $totalesPorTipo['GRUPALES']['pag2'],
            'cuota' => $totalesPorTipo['INDIVIDUALES']['cuota'] + $totalesPorTipo['GRUPALES']['cuota'],
            'capital' => $totalesPorTipo['INDIVIDUALES']['capital'] + $totalesPorTipo['GRUPALES']['capital'],
            'interes' => $totalesPorTipo['INDIVIDUALES']['interes'] + $totalesPorTipo['GRUPALES']['interes']
        ];

        $sheet->setCellValue("A{$row}", 'TOTAL GENERAL');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:C{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2C3E50');

        $sheet->setCellValue("D{$row}", $totalGeneral['saldo']);
        $sheet->setCellValue("E{$row}", $totalGeneral['mora']);
        $sheet->setCellValue("F{$row}", $totalGeneral['pag2']);
        $sheet->setCellValue("G{$row}", $totalGeneral['cuota']);
        $sheet->setCellValue("H{$row}", $totalGeneral['capital']);
        $sheet->setCellValue("I{$row}", $totalGeneral['interes']);

        $sheet->getStyle("D{$row}:I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("D{$row}:I{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("D{$row}:I{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2C3E50');

        $row++;

        return $row;
    }

    /**
     * Obtiene datos de ejemplo
     */
    private function getData()
    {
        try {
            $this->database->openConnection();
            $query = "SELECT ag.nom_agencia, pg.dfecven AS fecha, cm.CCODCTA AS cuenta, 
                    cl.idcod_cliente AS cliente, cl.short_name AS nombre, 
                    ((cm.NCapDes) - (SELECT IFNULL(SUM(KP),0) FROM CREDKAR WHERE ccodcta=cm.CCODCTA AND ctippag='P' AND cestado!='X')) AS saldo,
                    pg.nmorpag AS mora, pg.AhoPrgPag AS pag1, pg.OtrosPagosPag AS pag2, (pg.ncappag + pg.nintpag) AS cuota, 
                    pg.ncappag AS capital, pg.nintpag AS interes, grup.NombreGrupo,cm.TipoEnti,us.nombre nombreUser,us.apellido apellidoUser
                FROM cremcre_meta cm
                INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu
                INNER JOIN tb_agencia ag ON cm.CODAgencia=ag.cod_agenc
                INNER JOIN Cre_ppg pg ON cm.CCODCTA=pg.ccodcta
                LEFT JOIN tb_grupo grup ON grup.id_grupos=cm.CCodGrupo
                WHERE cm.Cestado='F' AND pg.cestado='X' 
                AND (pg.dfecven BETWEEN ? AND ?) ";
            $parameters = [
                $this->dataReporte['fecha_inicio'],
                $this->dataReporte['fecha_fin']
            ];

            if ($this->dataReporte['radio_agencia'] == 'anyofi') {
                $query .= " AND ag.id_agencia = ? ";
                $parameters[] = $this->dataReporte['creAgenciaSelect'];
            }
            if ($this->dataReporte['radio_agencia'] == 'anyasesor') {
                $query .= " AND cm.CodAnal=?";
                $parameters[] = $this->dataReporte['creUserSelect'];
            }
            if ($this->dataReporte['radio_agencia'] == 'anyregion') {
                $query .= " AND ag.id_agencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region = ?)";
                $parameters[] = $this->dataReporte['creRegionSelect'];
            }
            $query .= " ORDER BY TipoEnti,cm.CCodGrupo, cm.CCODCTA, pg.dfecven";
            $datos = $this->database->getAllResults($query, $parameters);
            if (empty($datos)) {
                throw new SoftException("No se encontraron datos para los filtros seleccionados.");
            }

            if ($this->dataReporte['radio_agencia'] == 'anyofi') {
                $this->filtros['Agencia'] = $datos[0]['nom_agencia'] ?? '';
            }
            if ($this->dataReporte['radio_agencia'] == 'anyasesor') {
                $this->filtros['Ejecutivo'] = $datos[0]['nombreUser'] . ' ' . $datos[0]['apellidoUser'] ?? '';
            }
            if ($this->dataReporte['radio_agencia'] == 'anyregion') {
                // Obtener nombre de la región
                $regionData = $this->database->getSingleResult(
                    "SELECT nombre FROM cre_regiones WHERE id = ?",
                    [$this->dataReporte['creRegionSelect']]
                );
                $this->filtros['Región'] = $regionData['nombre'] ?? '';
            }

            return $datos;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }
}
