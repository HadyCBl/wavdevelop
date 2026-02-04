<?php

namespace Micro\Controllers\Reportes\Creditos;

use App\Generic\Models\TipoDocumentoTransaccion;
use Micro\Controllers\BaseReporteController;
use Micro\Helpers\Log;
use Exception;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Helpers\Beneq;

/**
 * Controlador de Reportes de Créditos
 */
class IngresoController extends BaseReporteController
{
    /**
     * Reporte de ingresos diarios
     * POST /api/reportes/creditos/ingresos-diarios
     */
    protected $widthsColumns = [
        'fecha' => 18,
        'codigo_cuenta' => 28,
        'nombre_cliente' => 58,
        'total' => 19,
        'capital' => 19,
        'interes' => 15,
        'mora' => 14,
        'otros' => 14,
        'doc' => 20,
        'agencia' => 20,
        'origen' => 20,
        'formaPago' => 20,
    ];
    protected $filtros = [];
    protected $dataReporte = [];
    protected $sizeFont = 8;
    public function ingresosDiarios()
    {

        // Log::info("inputs", $this->input);
        //inputs {"ragencia":"anyuser","codofi":"1","codusu":"4","rfondos":"allf","fondoid":"1","finicio":"2025-12-10",
        // "ffin":"2025-12-10","radCreditos":"allCreditos","creAgenciaSelect":"1","creUserSelect":"4","tipo":"show"}
        $status = false;
        try {
            $data = [
                // 'token_csrf' => $_POST['inputs'][$csrf->getTokenName()] ?? '',
                'fecha_inicio' => $this->input['finicio'] ?? null,
                'fecha_fin' => $this->input['ffin'] ?? null,
                'codofi' => trim($this->input['codofi'] ?? ''),
                'fondoid' => trim($this->input['fondoid'] ?? ''),
                'codusu' => trim($this->input['codusu'] ?? ''),
                'creAgenciaSelect' => trim($this->input['creAgenciaSelect'] ?? ''),
                'creUserSelect' => trim($this->input['creUserSelect'] ?? ''),
                'radio_fondos' => trim($this->input['rfondos'] ?? ''),
                'radio_agencia' => trim($this->input['ragencia'] ?? ''),
                'radio_creditos' => trim($this->input['radCreditos'] ?? ''),
            ];

            $rules = [
                // 'token_csrf' => 'required',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date',
                'radio_fondos' => 'required|string',
                'radio_agencia' => 'required|string',
                'radio_creditos' => 'required|string',
            ];

            if ($data['radio_fondos'] == 'anyf') {
                $rules['fondoid'] = 'required|integer|min:1|exists:ctb_fuente_fondos,id';
            }
            if ($data['radio_agencia'] == 'anyofi') {
                $rules['codofi'] = 'required|integer|min:1|exists:tb_agencia,id_agencia';
            }
            if ($data['radio_agencia'] == 'anyusu') {
                $rules['codusu'] = 'required|integer|min:1|exists:tb_usuario,id_usu';
            }
            if ($data['radio_creditos'] == 'creAgencia') {
                $rules['creAgenciaSelect'] = 'required|integer|min:1|exists:tb_agencia,id_agencia';
            }
            if ($data['radio_creditos'] == 'creUser') {
                $rules['creUserSelect'] = 'required|integer|min:1|exists:tb_usuario,id_usu';
            }

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules);
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
            } elseif ($tipo === 'json') {
                $this->reporteJSON();
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
    public function reporteJSON()
    {
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();

            // 2. Procesar datos para gráficas (agrupación por fecha)
            $datosPorFecha = [];
            $totalesPorTipo = [
                'INDIVIDUALES' => 0,
                'GRUPALES' => 0
            ];

            foreach ($datos as $key => $dato) {
                $datos[$key]['formaPago'] = TipoDocumentoTransaccion::getDescripcion($dato['FormPago'], 3);
                $fecha = $dato['DFECPRO'];
                
                // Agrupar por fecha para gráfica
                if (!isset($datosPorFecha[$fecha])) {
                    $datosPorFecha[$fecha] = [
                        'fecha' => Date::toDMY($fecha),
                        'total' => 0,
                        'cantidad' => 0,
                        'capital' => 0,
                        'interes' => 0,
                        'mora' => 0,
                        'otros' => 0
                    ];
                }

                $datosPorFecha[$fecha]['total'] += floatval($dato['NMONTO'] ?? 0);
                $datosPorFecha[$fecha]['cantidad'] += 1;
                $datosPorFecha[$fecha]['capital'] += floatval($dato['KP'] ?? 0);
                $datosPorFecha[$fecha]['interes'] += floatval($dato['INTERES'] ?? 0);
                $datosPorFecha[$fecha]['mora'] += floatval($dato['MORA'] ?? 0);
                $datosPorFecha[$fecha]['otros'] += floatval($dato['OTR'] ?? 0);

                // Totales por tipo
                $tipoKey = ($dato['TipoEnti'] == 'GRUP') ? 'GRUPALES' : 'INDIVIDUALES';
                $totalesPorTipo[$tipoKey] += floatval($dato['NMONTO'] ?? 0);
            }

            // Convertir a array indexado para facilitar uso en JS
            $datosGrafica = array_values($datosPorFecha);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 1,
                'datos' => $datos,
                'datosGrafica' => $datosGrafica,
                'resumen' => [
                    'total_registros' => count($datos),
                    'total_ingresos' => array_sum(array_column($datos, 'NMONTO')),
                    'total_capital' => array_sum(array_column($datos, 'KP')),
                    'total_interes' => array_sum(array_column($datos, 'INTERES')),
                    'total_mora' => array_sum(array_column($datos, 'MORA')),
                    'total_otros' => array_sum(array_column($datos, 'OTR')),
                    'por_tipo' => $totalesPorTipo
                ]
            ]);
            exit;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
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

            $title = 'INGRESOS POR CAJA DEL ' . @date('d/m/Y', @strtotime($this->input['finicio'])) . ' AL ' . @date('d/m/Y', @strtotime($this->input['ffin']));

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
        $tamanio_linea = 3;

        $pdf->Ln(5);
        $pdf->SetFont($this->fonte, '', $this->sizeFont);

        $controlGroup = null;
        $totalesGrupo = [
            'total' => 0,
            'capital' => 0,
            'interes' => 0,
            'mora' => 0,
            'otros' => 0
        ];
        $totalesPorTipo = [
            'INDIVIDUALES' => [
                'total' => 0,
                'capital' => 0,
                'interes' => 0,
                'mora' => 0,
                'otros' => 0
            ],
            'GRUPALES' => [
                'total' => 0,
                'capital' => 0,
                'interes' => 0,
                'mora' => 0,
                'otros' => 0
            ]
        ];

        foreach ($datos as $index => $dato) {
            $formapago = TipoDocumentoTransaccion::getDescripcion($dato['FormPago'], 3);
            $nombreGrupo = ($dato['TipoEnti'] == 'GRUP') ? $dato['NombreGrupo'] : 'INDIVIDUALES';

            // Detectar cambio de grupo
            if ($controlGroup !== $nombreGrupo) {
                // Imprimir totales del grupo anterior
                if ($controlGroup !== null) {
                    $this->imprimirTotalesGrupo($pdf, $totalesGrupo);
                    // Reiniciar totales
                    $totalesGrupo = [
                        'total' => 0,
                        'capital' => 0,
                        'interes' => 0,
                        'mora' => 0,
                        'otros' => 0
                    ];
                }

                // Nuevo grupo
                $controlGroup = $nombreGrupo;
                // Imprimir nombre de grupo
                $pdf->SetFont('Courier', 'B', 9);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(0, 6, Utf8::decode($nombreGrupo), 0, 1, 'L', false);
                // Línea de separación
                $pdf->SetDrawColor(200, 200, 200);
                $pdf->SetLineWidth(0.3);
                $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - 20, $pdf->GetY());
                $pdf->SetFont($this->fonte, '', $this->sizeFont);
                $pdf->Ln(2);
            }

            // Acumular totales del grupo
            $totalesGrupo['total'] += floatval($dato['NMONTO'] ?? 0);
            $totalesGrupo['capital'] += floatval($dato['KP'] ?? 0);
            $totalesGrupo['interes'] += floatval($dato['INTERES'] ?? 0);
            $totalesGrupo['mora'] += floatval($dato['MORA'] ?? 0);
            $totalesGrupo['otros'] += floatval($dato['OTR'] ?? 0);

            // Acumular totales por tipo de entidad
            $tipoKey = ($dato['TipoEnti'] == 'GRUP') ? 'GRUPALES' : 'INDIVIDUALES';
            $totalesPorTipo[$tipoKey]['total'] += floatval($dato['NMONTO'] ?? 0);
            $totalesPorTipo[$tipoKey]['capital'] += floatval($dato['KP'] ?? 0);
            $totalesPorTipo[$tipoKey]['interes'] += floatval($dato['INTERES'] ?? 0);
            $totalesPorTipo[$tipoKey]['mora'] += floatval($dato['MORA'] ?? 0);
            $totalesPorTipo[$tipoKey]['otros'] += floatval($dato['OTR'] ?? 0);

            $pdf->CellFit($this->widthsColumns['fecha'], $tamanio_linea + 1, Date::isValid($dato['DFECPRO']) ? Date::toDMY($dato['DFECPRO']) : '', '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['codigo_cuenta'], $tamanio_linea + 1, $dato['CCODCTA'], '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, strtoupper(Utf8::decode($dato['short_name'])), '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['total'], $tamanio_linea + 1, Moneda::formato($dato['NMONTO'], ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, Moneda::formato($dato['KP'], ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Moneda::formato($dato['INTERES'], ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, Moneda::formato($dato['MORA'], ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['otros'], $tamanio_linea + 1, Moneda::formato($dato['OTR'], ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['doc'], $tamanio_linea + 1, Beneq::karely($dato['CNUMING']), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['agencia'], $tamanio_linea + 1, $dato['nom_agencia'], '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['origen'], $tamanio_linea + 1, $dato['agenciaorigen'], '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['formaPago'], $tamanio_linea + 1, $formapago, '', 1, 'R', 0, '', 1, 0);
        }

        // Imprimir totales del último grupo
        if ($controlGroup !== null) {
            $this->imprimirTotalesGrupo($pdf, $totalesGrupo);
        }

        // Imprimir resumen por tipo de entidad
        $this->imprimirResumenPorTipo($pdf, $totalesPorTipo);
    }

    /**
     * Imprime la línea de totales para un grupo en PDF
     */
    private function imprimirTotalesGrupo($pdf, $totales)
    {
        $tamanio_linea = 3;

        // Línea de separación antes del total
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - 20, $pdf->GetY());
        $pdf->Ln(2);

        // Estilo para totales
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);

        // Espacios vacíos para alinear con las columnas de datos
        $pdf->CellFit($this->widthsColumns['fecha'] + $this->widthsColumns['codigo_cuenta'] + $this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'SUBTOTAL:', '', 0, 'R', 0, '', 1, 0);

        // Totales formateados
        $pdf->CellFit($this->widthsColumns['total'], $tamanio_linea + 1, Moneda::formato($totales['total'], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, Moneda::formato($totales['capital'], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Moneda::formato($totales['interes'], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, Moneda::formato($totales['mora'], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['otros'], $tamanio_linea + 1, Moneda::formato($totales['otros'], ''), '', 1, 'R', 0, '', 1, 0);

        $pdf->Ln(2);

        // Restaurar fuente normal
        $pdf->SetFont($this->fonte, '', $this->sizeFont);
        $pdf->Ln(3);
    }

    /**
     * Imprime el resumen por tipo de entidad (INDIVIDUALES/GRUPALES) en PDF
     */
    private function imprimirResumenPorTipo($pdf, $totalesPorTipo)
    {
        $tamanio_linea = 3;

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
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->CellFit($this->widthsColumns['fecha'] + $this->widthsColumns['codigo_cuenta'] + $this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'TIPO', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['total'], $tamanio_linea + 1, 'TOTAL', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, 'CAPITAL', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Utf8::decode('INTERÉS'), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, 'MORA', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['otros'], $tamanio_linea + 1, 'OTROS', 1, 0, 'C', 0, '', 1, 0);
        $pdf->Ln(5);

        // Datos del resumen
        $pdf->SetFont('Courier', '', 8);

        foreach ($totalesPorTipo as $tipo => $totales) {
            // Solo mostrar si tiene valores
            if (
                $totales['total'] > 0 || $totales['capital'] > 0 || $totales['interes'] > 0 ||
                $totales['mora'] > 0 || $totales['otros'] > 0
            ) {
                $pdf->CellFit($this->widthsColumns['fecha'] + $this->widthsColumns['codigo_cuenta'] + $this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, $tipo, 0, 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['total'], $tamanio_linea + 1, Moneda::formato($totales['total'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, Moneda::formato($totales['capital'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Moneda::formato($totales['interes'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, Moneda::formato($totales['mora'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['otros'], $tamanio_linea + 1, Moneda::formato($totales['otros'], ''), 0, 0, 'R', 0, '', 1, 0);
                $pdf->Ln(5);
            }
        }

        // Total general
        $totalGeneral = [
            'total' => $totalesPorTipo['INDIVIDUALES']['total'] + $totalesPorTipo['GRUPALES']['total'],
            'capital' => $totalesPorTipo['INDIVIDUALES']['capital'] + $totalesPorTipo['GRUPALES']['capital'],
            'interes' => $totalesPorTipo['INDIVIDUALES']['interes'] + $totalesPorTipo['GRUPALES']['interes'],
            'mora' => $totalesPorTipo['INDIVIDUALES']['mora'] + $totalesPorTipo['GRUPALES']['mora'],
            'otros' => $totalesPorTipo['INDIVIDUALES']['otros'] + $totalesPorTipo['GRUPALES']['otros']
        ];

        // Línea de separación antes del total
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetPageWidth() - 20, $pdf->GetY());
        $pdf->Ln(2);

        $pdf->SetFont('Courier', 'B', 8);
        $pdf->CellFit($this->widthsColumns['fecha'] + $this->widthsColumns['codigo_cuenta'] + $this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'TOTAL GENERAL', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['total'], $tamanio_linea + 1, Moneda::formato($totalGeneral['total'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, Moneda::formato($totalGeneral['capital'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Moneda::formato($totalGeneral['interes'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, Moneda::formato($totalGeneral['mora'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['otros'], $tamanio_linea + 1, Moneda::formato($totalGeneral['otros'], ''), 0, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(5);
    }

    protected function addHeaderExtraPDF($pdf)
    {
        $tamanio_linea = 5;

        //Fuente
        $pdf->SetFont('Courier', '', 8);
        $pdf->SetTextColor(0, 0, 0);

        // $pdf->Ln(5);
        $pdf->CellFit($this->widthsColumns['fecha'], $tamanio_linea + 1, 'FECHA', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['codigo_cuenta'], $tamanio_linea + 1, Utf8::decode("CÓDIGO CUENTA"), 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'NOMBRE CLIENTE', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['total'], $tamanio_linea + 1, 'TOTAL', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital'], $tamanio_linea + 1, 'CAPITAL', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes'], $tamanio_linea + 1, Utf8::decode('INTERÉS'), 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['mora'], $tamanio_linea + 1, 'MORA', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['otros'], $tamanio_linea + 1, 'OTROS', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['doc'], $tamanio_linea + 1, 'DOC', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['agencia'], $tamanio_linea + 1, 'AGENCIA', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['origen'], $tamanio_linea + 1, 'ORIGEN', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['formaPago'], $tamanio_linea + 1, 'FORMA PAGO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->Ln(5);
    }

    /**
     * Genera reporte Excel con plantilla base
     */
    public function reporteExcel()
    {
        // $showmensaje = false;
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();

            $otros = $this->getDataOtros($datos);

            // 2. Preparar filtros
            $filtros = [
                'Periodo' => date('d/m/Y', strtotime($this->input['finicio'])) . ' al ' . date('d/m/Y', strtotime($this->input['ffin'])),
                'Estado' => 'Activos'
            ];

            $title = 'REPORTE DE INGRESOS ' . date('d/m/Y', strtotime($this->input['finicio'])) . ' AL ' . date('d/m/Y', strtotime($this->input['ffin']));
            $bodyFunc = function ($sheet, $row, $datos,) use ($otros) {
                return $this->bodyExcel($sheet, $row, $datos, $otros['tiposGasto'], $otros['pivotData']);
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

    protected function bodyExcel($sheet, $row, $datos, $tiposGasto, $pivotData)
    {
        // Column mapping
        $sheet->setTitle("IngresosDiarios");
        # Encabezado de la tabla
        $encabezadosFijos = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "TOTAL INGRESO", "CAPITAL", "INTERES", "MORA"];
        $encabezadosOtros = [];
        foreach ($tiposGasto as $tg) {
            $encabezadosOtros[] = strtoupper($tg['nombre']);
        }
        // Columna para el resto de gastos no catalogados
        $encabezadosOtros[] = 'OTROS';
        $encabezadosFinales = ["NUMDOC", "DPI CLIENTE", "NIT CLIENTE", "ANALISTA", "GRUPO", "AGENCIA", "USUARIO", "FORMA PAGO", "FECHA BOLETA", "BANCO", "CUENTA BANCARIA", "FECHA OPERACION"];
        $encabezado_tabla = array_merge($encabezadosFijos, $encabezadosOtros, $encabezadosFinales);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($encabezado_tabla));
        $sheet->fromArray($encabezado_tabla, null, 'A9')->getStyle("A9:" . $lastCol . "9")->getFont()->setName($this->fonte)->setBold(true);

        // Variables para control de grupos
        $controlGroup = null;
        $totalesGrupo = [
            'total' => 0,
            'capital' => 0,
            'interes' => 0,
            'mora' => 0,
            'otros_detalle' => array_fill(0, count($tiposGasto) + 1, 0)
        ];
        $totalesPorTipo = [
            'INDIVIDUALES' => [
                'total' => 0,
                'capital' => 0,
                'interes' => 0,
                'mora' => 0,
                'otros_total' => 0,
                'otros_detalle' => array_fill(0, count($tiposGasto) + 1, 0)
            ],
            'GRUPALES' => [
                'total' => 0,
                'capital' => 0,
                'interes' => 0,
                'mora' => 0,
                'otros_total' => 0,
                'otros_detalle' => array_fill(0, count($tiposGasto) + 1, 0)
            ]
        ];

        foreach ($datos as $key => $dato) {
            $nombreGrupo = ($dato['TipoEnti'] == 'GRUP') ? $dato['NombreGrupo'] : 'INDIVIDUALES';

            // Detectar cambio de grupo
            if ($controlGroup !== $nombreGrupo) {
                // Imprimir subtotales del grupo anterior
                if ($controlGroup !== null) {
                    $row = $this->imprimirSubtotalExcel($sheet, $row, $totalesGrupo, count($tiposGasto) + 1);
                    // Reiniciar totales del grupo
                    $totalesGrupo = [
                        'total' => 0,
                        'capital' => 0,
                        'interes' => 0,
                        'mora' => 0,
                        'otros_detalle' => array_fill(0, count($tiposGasto) + 1, 0)
                    ];
                }

                // Imprimir encabezado del nuevo grupo
                $controlGroup = $nombreGrupo;
                $sheet->setCellValue("A{$row}", strtoupper($nombreGrupo));
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8E8E8');
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $row++;
            }

            $fechaOperacionSistema = (Date::isValid($dato["DFECSIS"], 'Y-m-d H:i:s')) ? date("d-m-Y H:i:s", strtotime($dato["DFECSIS"])) : '';

            $formapago = $dato["FormPago"];
            $fechabanco = ($formapago == '2') ? $dato["DFECBANCO"] : ' ';
            $nombrebanco = ($formapago == '2') ? $dato["CBANCO"] : ' ';
            $cuentabanco = ($formapago == '2') ? $dato["CCODBANCO"] : ' ';
            // $formapago = ($formapago == '2') ? 'BOLETA DE BANCO' :  (($formapago == '4') ? 'CREF' : 'EFECTIVO');
            $formapago = TipoDocumentoTransaccion::getDescripcion($formapago, 3);

            $rowData = [
                $dato['DFECPRO'],
                $dato['CCODCTA'],
                strtoupper($dato['short_name']),
                $dato['NMONTO'],
                $dato['KP'],
                $dato['INTERES'],
                $dato['MORA']
            ];
            $detalleOtros = isset($pivotData[$dato['CODKAR']]) ? $pivotData[$dato['CODKAR']] : [];
            $sumaCatalogados = 0;
            $otrosDetalleArray = [];
            foreach ($tiposGasto as $idx => $tg) {
                $idg = $tg['id'];
                $val = isset($detalleOtros[$idg]) ? $detalleOtros[$idg] : 0;
                $rowData[] = $val;
                $otrosDetalleArray[$idx] = $val;
                $sumaCatalogados += $val;
            }
            $restanteOtros = max(0, $dato['OTR'] - $sumaCatalogados);
            $rowData[] = $restanteOtros;
            $otrosDetalleArray[count($tiposGasto)] = $restanteOtros;

            // Acumular totales del grupo
            $totalesGrupo['total'] += floatval($dato['NMONTO']);
            $totalesGrupo['capital'] += floatval($dato['KP']);
            $totalesGrupo['interes'] += floatval($dato['INTERES']);
            $totalesGrupo['mora'] += floatval($dato['MORA']);
            foreach ($otrosDetalleArray as $idx => $val) {
                $totalesGrupo['otros_detalle'][$idx] += $val;
            }

            // Acumular totales por tipo de entidad
            $tipoKey = ($dato['TipoEnti'] == 'GRUP') ? 'GRUPALES' : 'INDIVIDUALES';
            $totalesPorTipo[$tipoKey]['total'] += floatval($dato['NMONTO']);
            $totalesPorTipo[$tipoKey]['capital'] += floatval($dato['KP']);
            $totalesPorTipo[$tipoKey]['interes'] += floatval($dato['INTERES']);
            $totalesPorTipo[$tipoKey]['mora'] += floatval($dato['MORA']);
            $totalesPorTipo[$tipoKey]['otros_total'] += floatval($dato['OTR']);
            foreach ($otrosDetalleArray as $idx => $val) {
                $totalesPorTipo[$tipoKey]['otros_detalle'][$idx] += $val;
            }

            $rowData[] = $dato['CNUMING'];
            $rowData[] = $dato['dpi'];
            $rowData[] = $dato['nit'];
            $rowData[] = $dato['analista'];
            $rowData[] = $dato['NombreGrupo'];
            $rowData[] = $dato['nom_agencia'];
            $rowData[] = $dato['usuario'];
            $rowData[] = $formapago;
            $rowData[] = $fechabanco;
            $rowData[] = $nombrebanco;
            $rowData[] = $cuentabanco;
            $rowData[] = $fechaOperacionSistema;
            $sheet->fromArray($rowData, null, 'A' . $row);

            // Columnas a formatear como texto para evitar notación científica

            $otrosCols = count($tiposGasto) + 1; // subcategorías + columna "OTROS"

            $baseFinal = 8 + $otrosCols; // columna NUMDOC en índice 1-base
            $textIndexes = [
                2,                  // CUENTA
                $baseFinal,         // NUMDOC
                $baseFinal + 1,     // DPI CLIENTE
                $baseFinal + 2,     // NIT CLIENTE
                $baseFinal + 10     // CUENTA BANCARIA
            ];
            foreach ($textIndexes as $colIdx) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx) . $row;
                $value = $sheet->getCell($coord)->getValue();
                $sheet->setCellValueExplicit($coord, (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $row++;
        }

        // Imprimir subtotales del último grupo
        if ($controlGroup !== null) {
            $row = $this->imprimirSubtotalExcel($sheet, $row, $totalesGrupo, count($tiposGasto) + 1);
        }

        // Imprimir resumen por tipo de entidad
        $row = $this->imprimirResumenPorTipoExcel($sheet, $row, $totalesPorTipo, count($tiposGasto) + 1);

        return $row;
    }

    /**
     * Imprime la fila de subtotales para un grupo en Excel
     */
    private function imprimirSubtotalExcel($sheet, $row, $totales, $otrosCols = 0)
    {
        // Calcular la última columna de subcategorías de "otros"
        $baseCol = 8 + $otrosCols; // 8 es después de MORA, luego vienen las subcategorías
        $lastDataCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($baseCol);

        // Línea de subtotal
        $sheet->setCellValue("A{$row}", 'SUBTOTAL:');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:C{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCDCDC');

        $sheet->setCellValue("D{$row}", $totales['total']);
        $sheet->setCellValue("E{$row}", $totales['capital']);
        $sheet->setCellValue("F{$row}", $totales['interes']);
        $sheet->setCellValue("G{$row}", $totales['mora']);

        // Agregar los valores de subcategorías de otros si existen
        if (isset($totales['otros_detalle']) && is_array($totales['otros_detalle'])) {
            $col = 8;
            foreach ($totales['otros_detalle'] as $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $val);
                $col++;
            }
        }

        $sheet->getStyle("D{$row}:{$lastDataCol}{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("D{$row}:{$lastDataCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("D{$row}:{$lastDataCol}{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCDCDC');

        $row++; // Espacio después del subtotal

        return $row;
    }

    /**
     * Imprime el resumen por tipo de entidad en Excel
     */
    private function imprimirResumenPorTipoExcel($sheet, $row, $totalesPorTipo, $otrosCols = 0)
    {
        // Calcular la última columna
        $lastCol = 7 + $otrosCols;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);

        $row += 3; // Espacio antes del resumen

        // Título del resumen
        $sheet->setCellValue("A{$row}", 'RESUMEN');
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF95A5A6');
        $row++;

        // Encabezados del resumen
        $sheet->setCellValue("A{$row}", 'TIPO');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", 'TOTAL');
        $sheet->setCellValue("E{$row}", 'CAPITAL');
        $sheet->setCellValue("F{$row}", 'INTERÉS');
        $sheet->setCellValue("G{$row}", 'MORA');

        // Si hay subcategorías de otros, agregar encabezado general
        if ($otrosCols > 0) {
            $sheet->setCellValue("H{$row}", 'OTROS');
            if ($otrosCols > 1) {
                $sheet->mergeCells("H{$row}:{$lastColLetter}{$row}");
            }
        }

        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF34495E');
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        // Datos del resumen
        foreach ($totalesPorTipo as $tipo => $totales) {
            // Solo mostrar si tiene valores
            if (
                $totales['total'] > 0 || $totales['capital'] > 0 || $totales['interes'] > 0 ||
                $totales['mora'] > 0 || $totales['otros_total'] > 0
            ) {
                $sheet->setCellValue("A{$row}", $tipo);
                $sheet->mergeCells("A{$row}:C{$row}");
                $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:C{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF8F9F9');

                $sheet->setCellValue("D{$row}", $totales['total']);
                $sheet->setCellValue("E{$row}", $totales['capital']);
                $sheet->setCellValue("F{$row}", $totales['interes']);
                $sheet->setCellValue("G{$row}", $totales['mora']);

                // Agregar valores de subcategorías de otros
                if (isset($totales['otros_detalle']) && is_array($totales['otros_detalle'])) {
                    $col = 8;
                    foreach ($totales['otros_detalle'] as $val) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                        $sheet->setCellValue("{$colLetter}{$row}", $val);
                        $col++;
                    }
                }

                $sheet->getStyle("D{$row}:{$lastColLetter}{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
                $sheet->getStyle("D{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true);
                $sheet->getStyle("D{$row}:{$lastColLetter}{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF8F9F9');

                $row++;
            }
        }

        // Total general del resumen
        $totalGeneral = [
            'total' => $totalesPorTipo['INDIVIDUALES']['total'] + $totalesPorTipo['GRUPALES']['total'],
            'capital' => $totalesPorTipo['INDIVIDUALES']['capital'] + $totalesPorTipo['GRUPALES']['capital'],
            'interes' => $totalesPorTipo['INDIVIDUALES']['interes'] + $totalesPorTipo['GRUPALES']['interes'],
            'mora' => $totalesPorTipo['INDIVIDUALES']['mora'] + $totalesPorTipo['GRUPALES']['mora']
        ];

        // Calcular totales de subcategorías
        if ($otrosCols > 0 && isset($totalesPorTipo['INDIVIDUALES']['otros_detalle'])) {
            $totalGeneral['otros_detalle'] = [];
            foreach ($totalesPorTipo['INDIVIDUALES']['otros_detalle'] as $idx => $val) {
                $totalGeneral['otros_detalle'][$idx] = $val + ($totalesPorTipo['GRUPALES']['otros_detalle'][$idx] ?? 0);
            }
        }

        $sheet->setCellValue("A{$row}", 'TOTAL GENERAL');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:C{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2C3E50');

        $sheet->setCellValue("D{$row}", $totalGeneral['total']);
        $sheet->setCellValue("E{$row}", $totalGeneral['capital']);
        $sheet->setCellValue("F{$row}", $totalGeneral['interes']);
        $sheet->setCellValue("G{$row}", $totalGeneral['mora']);

        // Agregar valores de subcategorías de otros al total general
        if (isset($totalGeneral['otros_detalle']) && is_array($totalGeneral['otros_detalle'])) {
            $col = 8;
            foreach ($totalGeneral['otros_detalle'] as $val) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$colLetter}{$row}", $val);
                $col++;
            }
        }

        $sheet->getStyle("D{$row}:{$lastColLetter}{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("D{$row}:{$lastColLetter}{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("D{$row}:{$lastColLetter}{$row}")->getFill()
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
            $where = ($this->dataReporte['radio_agencia']  == "allofi") ? "" : (($this->dataReporte['radio_agencia']  == "anyofi") ? " AND usu.id_agencia=" . $this->dataReporte['codofi'] : " AND usu.id_usu=" . $this->dataReporte['codusu']);
            $where .= ($this->dataReporte['radio_fondos'] == "anyf") ? " AND prod.id_fondo=" . $this->dataReporte['fondoid'] : "";
            $where .= ($this->dataReporte['radio_creditos'] == "creAgencia") ? " AND ofi2.id_agencia=" . $this->dataReporte['creAgenciaSelect'] : (($this->dataReporte['radio_creditos'] == "creUser") ? " AND crem.CodAnal=" . $this->dataReporte['creUserSelect'] : "");

            $query = "SELECT kar.CODKAR,kar.CCODCTA,cli.short_name,cli.no_identifica dpi,cli.no_tributaria nit,kar.DFECPRO,kar.NMONTO,kar.CNUMING,kar.KP,kar.INTERES,kar.MORA,kar.AHOPRG,kar.OTR,
                ofi.nom_agencia,CONCAT(usu.nombre,' ',usu.apellido) usuario ,CONCAT(usu2.nombre,' ',usu2.apellido) analista, 
                ofi2.nom_agencia agenciaorigen, FormPago,DFECBANCO, DFECSIS,
                IFNULL((SELECT nombre FROM tb_bancos where id=CBANCO),'-') CBANCO,
                IFNULL((SELECT numcuenta FROM ctb_bancos where id=CCODBANCO),'-') CCODBANCO ,boletabanco,  grup.NombreGrupo,crem.TipoEnti
                FROM CREDKAR kar 
                INNER JOIN cremcre_meta crem ON crem.CCODCTA=kar.CCODCTA 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli 
                INNER JOIN tb_usuario usu on usu.id_usu=kar.CCODUSU
                INNER JOIN tb_usuario usu2 on usu2.id_usu=crem.CodAnal
                INNER JOIN tb_agencia ofi on ofi.id_agencia=usu.id_agencia
                INNER JOIN tb_agencia ofi2 on ofi2.id_agencia=crem.CODAgencia
                INNER JOIN cre_productos prod on prod.id=crem.CCODPRD
                LEFT JOIN tb_grupo grup ON grup.id_grupos=crem.CCodGrupo
                WHERE kar.CESTADO!='X' AND kar.CTIPPAG='P' " . $where . " AND (kar.DFECPRO BETWEEN ? AND ?) 
                ORDER BY crem.TipoEnti DESC, grup.NombreGrupo, kar.DFECPRO, kar.CNUMING";
            $datos = $this->database->getAllResults($query, [
                $this->dataReporte['fecha_inicio'],
                $this->dataReporte['fecha_fin']
            ]);
            if (empty($datos)) {
                // $showmensaje = true;
                throw new SoftException("No se encontraron datos para los filtros seleccionados.");
            }

            /**
             * aqui agregar los filtros aplicados, para que los liste automaticamente en el reporte
             */

            // if ($this->input['filter_type'] == 'office') {
            //     $this->filtros['Agencia'] = $datos[0]['nom_agencia'] ?? '';
            // }
            // if ($this->input['filter_type'] == 'executive') {
            //     $this->filtros['Ejecutivo'] = $datos[0]['nombreUser'] . ' ' . $datos[0]['apellidoUser'] ?? '';
            // }

            return $datos;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }
    private function getDataOtros($datos)
    {
        $tiposGasto = [];
        $pivotData = [];
        try {
            $this->database->openConnection();
            $codkars = array_column($datos, 'CODKAR');
            if (!empty($codkars)) {
                $placeholders = implode(',', array_fill(0, count($codkars), '?'));
                $sqlOtros = "SELECT ck.CODKAR, tg.id AS id_tipogasto, tg.nombre_gasto, SUM(cd.monto) AS total
                      FROM CREDKAR ck
                      INNER JOIN credkar_detalle cd ON cd.id_credkar = ck.CODKAR
                      INNER JOIN cre_productos_gastos pg ON pg.id = cd.id_concepto
                      INNER JOIN cre_tipogastos tg ON tg.id = pg.id_tipo_deGasto
                      WHERE cd.tipo='otro' AND ck.CODKAR IN ($placeholders) AND ck.CTIPPAG='P' AND ck.CESTADO!='X'
                      GROUP BY ck.CODKAR, tg.id
                      ORDER BY tg.id";
                $detOtros = $this->database->getAllResults($sqlOtros, $codkars);
                $tiposSet = [];
                foreach ($detOtros as $rowo) {
                    $idg = $rowo['id_tipogasto'];
                    if (!isset($pivotData[$rowo['CODKAR']])) {
                        $pivotData[$rowo['CODKAR']] = [];
                    }
                    $pivotData[$rowo['CODKAR']][$idg] = (float)$rowo['total'];
                    if (!isset($tiposSet[$idg])) {
                        $tiposSet[$idg] = $rowo['nombre_gasto'];
                    }
                }
                foreach ($tiposSet as $id => $nom) {
                    $tiposGasto[] = ['id' => $id, 'nombre' => $nom];
                }
                usort($tiposGasto, function ($a, $b) {
                    return ($a['id'] < $b['id']) ? -1 : 1;
                });
            }

            return [
                'tiposGasto' => $tiposGasto,
                'pivotData' => $pivotData
            ];
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }
}
