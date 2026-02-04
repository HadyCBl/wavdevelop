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
use Micro\Helpers\Beneq;

/**
 * Controlador de Reportes de Créditos
 */
class MoraController extends BaseReporteController
{
    /**
     * Reporte de creditos desembolsados
     * POST /api/reportes/creditos/mora
     */
    protected $widthsColumns = [
        'credito' => 24,
        'nombre_cliente' => 55,
        'fec_otorgamiento' => 19,
        'fec_vencimiento' => 19,
        'fec_ultimo_pago' => 19,
        'monto' => 20,
        'saldo_capital' => 20,
        'capital_mora' => 20,
        'interes_corriente' => 20,
        'interes_mora' => 20,
        'saldo_deudor' => 20,
        'dias_atraso' => 10,
    ];
    protected $filtros = [];
    protected $dataReporte = [];
    protected $sizeFont = 8;
    public function mora()
    {

        // Log::info("inputs", $this->input);
        /**
         * {"ragencia":"anyofi","regionid":"0","codofi":"1","codanal":"4","rfondos":"allf","fondoid":"1",
         * "ffin":"2025-12-23","csrf_token":"6eb7c7953bb9b882520b62512f539683ab55d9930764ca4be3372782f62a86a7","tipo":"show"}
         */
        $status = false;
        try {
            $data = [
                'radio_agencia' => trim($this->input['ragencia'] ?? ''),
                'creRegionSelect' => trim($this->input['regionid'] ?? ''),
                'creAgenciaSelect' => trim($this->input['codofi'] ?? ''),
                'creUserSelect' => trim($this->input['codanal'] ?? ''),
                'radio_fondos' => trim($this->input['rfondos'] ?? ''),
                'creFondoSelect' => trim($this->input['fondoid'] ?? ''),
                'fecha_corte' => $this->input['ffin'] ?? null
            ];

            $rules = [
                'radio_agencia' => 'required|string',
                'radio_fondos' => 'required|string',
                'fecha_corte' => 'required|date'
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
            if ($data['radio_fondos'] == 'anyf') {
                $rules['creFondoSelect'] = 'required|integer|min:1|exists:ctb_fuente_fondos,id';
            }

            $messages = [
                'creRegionSelect.min' => 'Seleccione una región válida.',
            ];

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            if ($data['fecha_corte'] > date('Y-m-d')) {
                throw new SoftException('La fecha de inicio no puede ser mayor a la fecha actual.');
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

            // 2. Formatear datos para tabla
            $valores = [];
            $datosPorAnalista = [];
            $totalesPorTipo = [
                'GRUPALES' => 0,
                'INDIVIDUALES' => 0
            ];

            foreach ($datos as $fila) {
                $fila["DFecDsbls"] = Date::isValid($fila["DFecDsbls"]) ? Date::toDMY($fila["DFecDsbls"]) : '-';
                $fila["fechaultpag"] = Date::isValid($fila["fechaultpag"]) ? Date::toDMY($fila["fechaultpag"]) : '-';
                $fila["fechaven"] = Date::isValid($fila["fechaven"]) ? Date::toDMY($fila["fechaven"]) : '-';

                $monto = (float)$fila["NCapDes"];
                $cappag = (float)$fila["cappag"];
                $intpag = (float)$fila["intpag"];
                $intcal = (float)$fila["intcal"];
                $capcalafec = (float)$fila["capcalafec"];
                $intcalafec = (float)$fila["intcalafec"];
                $intmora = (float)$fila["intmora"];

                // Cálculos
                $salcap = max(0, $monto - $cappag);
                $salint = max(0, $intcal - $intpag);
                $capmora = max(0, $capcalafec - $cappag);
                $intatrasado = max(0, $intcalafec - $intpag);
                $saldodeudor = $capmora + $intatrasado + $intmora;

                $fila["salcap"] = number_format($salcap, 2);
                $fila["salint"] = number_format($salint, 2);
                $fila["capenmora"] = number_format($capmora, 2);
                $fila["intatrasado"] = number_format($intatrasado, 2);
                $fila["saldodeudor"] = number_format($saldodeudor, 2);

                $valores[] = $fila;

                // Agrupar por analista para gráfica
                $analista = $fila['analista'];
                if (!isset($datosPorAnalista[$analista])) {
                    $datosPorAnalista[$analista] = [
                        'analista' => $analista,
                        'cantidad' => 0,
                        'monto' => 0,
                        'salcap' => 0,
                        'capmora' => 0,
                        'intatrasado' => 0,
                        'intmora' => 0,
                        'saldodeudor' => 0
                    ];
                }

                $datosPorAnalista[$analista]['cantidad'] += 1;
                $datosPorAnalista[$analista]['monto'] += $monto;
                $datosPorAnalista[$analista]['salcap'] += $salcap;
                $datosPorAnalista[$analista]['capmora'] += $capmora;
                $datosPorAnalista[$analista]['intatrasado'] += $intatrasado;
                $datosPorAnalista[$analista]['intmora'] += $intmora;
                $datosPorAnalista[$analista]['saldodeudor'] += $saldodeudor;

                // Totales por tipo
                $tipoKey = ($fila['TipoEnti'] == 'GRUP') ? 'GRUPALES' : 'INDIVIDUALES';
                $totalesPorTipo[$tipoKey] += $saldodeudor;
            }

            // Convertir a array indexado para facilitar uso en JS
            $datosGrafica = array_values($datosPorAnalista);

            $keys = ["nom_agencia", "analista", "CCODCTA", "short_name", "DFecDsbls", "fechaven", "NCapDes", "salcap", "capenmora", "intatrasado", "intmora", "atraso"];
            $encabezados = ["Agencia", "Asesor", "Cuenta", "Nombre Cliente", "Fec. Inicio", "Fec. Vence", "Monto", "Saldo kp", "Kp. Mora", "Int. Corr.", "Mora", "Días"];

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 1,
                'mensaje' => 'Reporte generado correctamente',
                'datos' => $valores,
                'datosGrafica' => $datosGrafica,
                'keys' => $keys,
                'encabezados' => $encabezados,
                'resumen' => [
                    'total_registros' => count($valores),
                    'total_monto' => array_sum(array_column($valores, 'NCapDes')),
                    'total_capmora' => array_sum(array_map(function($v) { return floatval(str_replace(',', '', $v['capenmora'])); }, $valores)),
                    'total_intmora' => array_sum(array_column($valores, 'intmora')),
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
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();

            $title = 'CARTERA EN MORA AL ' . Date::toDMY($this->dataReporte['fecha_corte']);

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

        $pdf->Ln(7);
        $pdf->SetFont($this->fonte, '', $this->sizeFont);

        $auxfondo = null;
        $auxagencia = null;
        $auxanalista = null;

        // Totales globales
        $sum_montos = 0;
        $sum_salcap = 0;
        $sum_capmora = 0;
        $sumintatrasado = 0;
        $sumintmora = 0;
        $contador = 0;

        // Acumuladores por TipoEnti
        $tipo_enti = [];
        $tipo_enti['GRUP'] = ['contador' => 0, 'montos' => 0, 'salcap' => 0, 'capmora' => 0, 'intatrasado' => 0, 'intmora' => 0];
        $tipo_enti['INDI'] = ['contador' => 0, 'montos' => 0, 'salcap' => 0, 'capmora' => 0, 'intatrasado' => 0, 'intmora' => 0];

        // Acumuladores por ejecutivo
        $ejecutivo_montos = 0;
        $ejecutivo_salcap = 0;
        $ejecutivo_capmora = 0;
        $ejecutivo_intatrasado = 0;
        $ejecutivo_intmora = 0;
        $ejecutivo_contador = 0;

        // Acumuladores por agencia
        $agencia_montos = 0;
        $agencia_salcap = 0;
        $agencia_capmora = 0;
        $agencia_intatrasado = 0;
        $agencia_intmora = 0;
        $agencia_contador = 0;

        // Acumuladores por fondo
        $fondo_montos = 0;
        $fondo_salcap = 0;
        $fondo_capmora = 0;
        $fondo_intatrasado = 0;
        $fondo_intmora = 0;
        $fondo_contador = 0;

        // Función para imprimir subtotal ejecutivo
        $imprimirSubtotalEjecutivo = function () use (
            &$pdf,
            &$ejecutivo_montos,
            &$ejecutivo_salcap,
            &$ejecutivo_capmora,
            &$ejecutivo_intatrasado,
            &$ejecutivo_intmora,
            &$ejecutivo_contador
        ) {
            if ($ejecutivo_contador > 0) {
                $pdf->Ln(2);
                $pdf->SetFont($this->fonte, 'B', 7);
                $pdf->CellFit(
                    $this->widthsColumns['credito'] +
                        $this->widthsColumns['nombre_cliente'] +
                        $this->widthsColumns['fec_otorgamiento'] +
                        $this->widthsColumns['fec_vencimiento'] +
                        $this->widthsColumns['fec_ultimo_pago'],
                    5,
                    'Subtotal Ejecutivo (' . $ejecutivo_contador . '):',
                    '',
                    0,
                    'R',
                    0,
                    '',
                    1,
                    0
                );
                $pdf->CellFit($this->widthsColumns['monto'], 5, Moneda::formato($ejecutivo_montos, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_capital'], 5, Moneda::formato($ejecutivo_salcap, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['capital_mora'], 5, Moneda::formato($ejecutivo_capmora, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_corriente'], 5, Moneda::formato($ejecutivo_intatrasado, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_mora'], 5, Moneda::formato($ejecutivo_intmora, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_deudor'], 5, Moneda::formato($ejecutivo_capmora + $ejecutivo_intatrasado + $ejecutivo_intmora, ''), '', 1, 'R', 0, '', 1, 0);
                $pdf->SetFont($this->fonte, '', 7);

                // Reset
                $ejecutivo_montos = 0;
                $ejecutivo_salcap = 0;
                $ejecutivo_capmora = 0;
                $ejecutivo_intatrasado = 0;
                $ejecutivo_intmora = 0;
                $ejecutivo_contador = 0;
            }
        };

        // Función para imprimir subtotal agencia
        $imprimirSubtotalAgencia = function () use (
            &$pdf,
            &$agencia_montos,
            &$agencia_salcap,
            &$agencia_capmora,
            &$agencia_intatrasado,
            &$agencia_intmora,
            &$agencia_contador
        ) {
            if ($agencia_contador > 0) {
                $pdf->Ln(2);
                $pdf->SetFont($this->fonte, 'B', 7);
                $pdf->CellFit(
                    $this->widthsColumns['credito'] +
                        $this->widthsColumns['nombre_cliente'] +
                        $this->widthsColumns['fec_otorgamiento'] +
                        $this->widthsColumns['fec_vencimiento'] +
                        $this->widthsColumns['fec_ultimo_pago'],
                    5,
                    'Subtotal Agencia (' . $agencia_contador . '):',
                    '',
                    0,
                    'R',
                    0,
                    '',
                    1,
                    0
                );
                $pdf->CellFit($this->widthsColumns['monto'], 5, Moneda::formato($agencia_montos, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_capital'], 5, Moneda::formato($agencia_salcap, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['capital_mora'], 5, Moneda::formato($agencia_capmora, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_corriente'], 5, Moneda::formato($agencia_intatrasado, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_mora'], 5, Moneda::formato($agencia_intmora, ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_deudor'], 5, Moneda::formato($agencia_capmora + $agencia_intatrasado + $agencia_intmora, ''), '', 1, 'R', 0, '', 1, 0);
                $pdf->SetFont($this->fonte, '', 7);

                // Reset
                $agencia_montos = 0;
                $agencia_salcap = 0;
                $agencia_capmora = 0;
                $agencia_intatrasado = 0;
                $agencia_intmora = 0;
                $agencia_contador = 0;
            }
        };

        // Función para imprimir subtotal fondo
        $imprimirSubtotalFondo = function () use (
            &$pdf,
            &$fondo_montos,
            &$fondo_salcap,
            &$fondo_capmora,
            &$fondo_intatrasado,
            &$fondo_intmora,
            &$fondo_contador
        ) {
            if ($fondo_contador > 0) {
                $pdf->Ln(3);
                $pdf->SetFont($this->fonte, 'B', 8);
                $pdf->CellFit(
                    $this->widthsColumns['credito'] +
                        $this->widthsColumns['nombre_cliente'] +
                        $this->widthsColumns['fec_otorgamiento'] +
                        $this->widthsColumns['fec_vencimiento'] +
                        $this->widthsColumns['fec_ultimo_pago'],
                    6,
                    'SUBTOTAL FONDO (' . $fondo_contador . '):',
                    'T',
                    0,
                    'R',
                    0,
                    '',
                    1,
                    0
                );
                $pdf->CellFit($this->widthsColumns['monto'], 6, Moneda::formato($fondo_montos, ''), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_capital'], 6, Moneda::formato($fondo_salcap, ''), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['capital_mora'], 6, Moneda::formato($fondo_capmora, ''), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_corriente'], 6, Moneda::formato($fondo_intatrasado, ''), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_mora'], 6, Moneda::formato($fondo_intmora, ''), 'T', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_deudor'], 6, Moneda::formato($fondo_capmora + $fondo_intatrasado + $fondo_intmora, ''), 'T', 1, 'R', 0, '', 1, 0);
                $pdf->SetFont($this->fonte, '', 7);
                $pdf->Ln(2);

                // Reset
                $fondo_montos = 0;
                $fondo_salcap = 0;
                $fondo_capmora = 0;
                $fondo_intatrasado = 0;
                $fondo_intmora = 0;
                $fondo_contador = 0;
            }
        };

        foreach ($datos as $index => $dato) {
            $cuenta = $dato['CCODCTA'];
            $nombre = strtoupper(Utf8::decode(trim($dato['short_name'])));
            $fechades = Date::isValid($dato['DFecDsbls']) ? Date::toDMY($dato['DFecDsbls']) : '-';
            $fechaven = Date::isValid($dato['fechaven']) ? Date::toDMY($dato['fechaven']) : '-';
            $fultpag = Date::isValid($dato['fechaultpag']) ? Date::toDMY($dato['fechaultpag']) : '-';

            $monto = (float)$dato['NCapDes'];
            $cappag = (float)$dato['cappag'];
            $intpag = (float)$dato['intpag'];
            $intcal = (float)$dato['intcal'];
            $capcalafec = (float)$dato['capcalafec'];
            $intcalafec = (float)$dato['intcalafec'];
            $intmora = (float)$dato['intmora'];
            $diasatr = (int)$dato['atraso'];

            $idfondos = $dato['id_fondos'];
            $nombrefondo = $dato['nombre_fondo'];
            $idagencia = $dato['id_agencia'];
            $nomagencia = $dato['nom_agencia'];
            $codanal = $dato['CodAnal'];
            $nomanal = $dato['analista'];

            // Cálculos
            $salcap = max(0, $monto - $cappag);
            $salint = max(0, $intcal - $intpag);
            $capmora = max(0, $capcalafec - $cappag);
            $intatrasado = max(0, $intcalafec - $intpag);
            $saldodeudor = $capmora + $intatrasado + $intmora;

            // Título FONDO
            if ($idfondos != $auxfondo) {
                // Imprimir subtotales anteriores
                if ($auxfondo !== null) {
                    $imprimirSubtotalEjecutivo();
                    $imprimirSubtotalAgencia();
                    $imprimirSubtotalFondo();
                }

                $pdf->Ln(2);
                $pdf->SetFont($this->fonte, 'B', 9);
                $pdf->Cell($this->widthsColumns['credito'] * 2, 5, 'FUENTE DE FONDOS: ', '', 0, 'R');
                $pdf->Cell(0, 5, strtoupper($nombrefondo), '', 1, 'L');
                $pdf->SetFont($this->fonte, '', 7);
                $auxfondo = $idfondos;
            }

            // Título AGENCIA
            if ($idagencia != $auxagencia) {
                // Imprimir subtotales anteriores de ejecutivo y agencia
                if ($auxagencia !== null) {
                    $imprimirSubtotalEjecutivo();
                    $imprimirSubtotalAgencia();
                }

                $pdf->Ln(2);
                $pdf->SetFont($this->fonte, 'B', 8);
                $pdf->Cell($this->widthsColumns['credito'] * 2, 5, 'AGENCIA: ', '', 0, 'R');
                $pdf->Cell(0, 5, strtoupper($nomagencia), '', 1, 'L');
                $pdf->SetFont($this->fonte, '', 7);
                $auxagencia = $idagencia;
                $auxanalista = null;
            }

            // Título EJECUTIVO
            if ($codanal != $auxanalista) {
                // Imprimir subtotal anterior de ejecutivo
                if ($auxanalista !== null) {
                    $imprimirSubtotalEjecutivo();
                }

                $pdf->SetFont($this->fonte, 'BI', 7);
                $pdf->Cell($this->widthsColumns['credito'] * 2, 5, $codanal . ' EJECUTIVO: ', '', 0, 'R');
                $pdf->Cell(0, 5, strtoupper($nomanal), '', 1, 'L');
                $pdf->SetFont($this->fonte, '', 7);
                $auxanalista = $codanal;
            }

            // Fila de datos
            $pdf->CellFit($this->widthsColumns['credito'], $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, $nombre, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['fec_otorgamiento'], $tamanio_linea + 1, $fechades, '', 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['fec_vencimiento'], $tamanio_linea + 1, $fechaven, '', 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['fec_ultimo_pago'], $tamanio_linea + 1, $fultpag, '', 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['monto'], $tamanio_linea + 1, Moneda::formato($monto, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['saldo_capital'], $tamanio_linea + 1, Moneda::formato($salcap, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['capital_mora'], $tamanio_linea + 1, Moneda::formato($capmora, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['interes_corriente'], $tamanio_linea + 1, Moneda::formato($intatrasado, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['interes_mora'], $tamanio_linea + 1, Moneda::formato($intmora, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['saldo_deudor'], $tamanio_linea + 1, Moneda::formato($saldodeudor, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['dias_atraso'], $tamanio_linea + 1, $diasatr, '', 1, 'R', 0, '', 1, 0);

            // Acumular totales
            $sum_montos += $monto;
            $sum_salcap += $salcap;
            $sum_capmora += $capmora;
            $sumintatrasado += $intatrasado;
            $sumintmora += $intmora;
            $contador++;

            // Acumular por TipoEnti
            $tipoenti = $dato['TipoEnti'];
            if (isset($tipo_enti[$tipoenti])) {
                $tipo_enti[$tipoenti]['contador']++;
                $tipo_enti[$tipoenti]['montos'] += $monto;
                $tipo_enti[$tipoenti]['salcap'] += $salcap;
                $tipo_enti[$tipoenti]['capmora'] += $capmora;
                $tipo_enti[$tipoenti]['intatrasado'] += $intatrasado;
                $tipo_enti[$tipoenti]['intmora'] += $intmora;
            }

            // Acumular por ejecutivo
            $ejecutivo_montos += $monto;
            $ejecutivo_salcap += $salcap;
            $ejecutivo_capmora += $capmora;
            $ejecutivo_intatrasado += $intatrasado;
            $ejecutivo_intmora += $intmora;
            $ejecutivo_contador++;

            // Acumular por agencia
            $agencia_montos += $monto;
            $agencia_salcap += $salcap;
            $agencia_capmora += $capmora;
            $agencia_intatrasado += $intatrasado;
            $agencia_intmora += $intmora;
            $agencia_contador++;

            // Acumular por fondo
            $fondo_montos += $monto;
            $fondo_salcap += $salcap;
            $fondo_capmora += $capmora;
            $fondo_intatrasado += $intatrasado;
            $fondo_intmora += $intmora;
            $fondo_contador++;
        }

        // Imprimir últimos subtotales
        $imprimirSubtotalEjecutivo();
        $imprimirSubtotalAgencia();
        $imprimirSubtotalFondo();

        // Totales globales
        $pdf->Ln(2);
        $pdf->SetFont($this->fonte, 'B', 7);
        $pdf->Cell(0, 1, '', 'T', 1, 'R');
        $pdf->Ln(3);
        $pdf->CellFit(
            $this->widthsColumns['credito'] + $this->widthsColumns['nombre_cliente'] + $this->widthsColumns['fec_otorgamiento'] + $this->widthsColumns['fec_vencimiento'] + $this->widthsColumns['fec_ultimo_pago'],
            6,
            Utf8::decode('Número de créditos: ' . $contador),
            'T',
            0,
            'C',
            0,
            '',
            1,
            0
        );
        $pdf->CellFit($this->widthsColumns['monto'], 6, Moneda::formato($sum_montos, ''), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo_capital'], 6, Moneda::formato($sum_salcap, ''), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital_mora'], 6, Moneda::formato($sum_capmora, ''), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes_corriente'], 6, Moneda::formato($sumintatrasado, ''), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes_mora'], 6, Moneda::formato($sumintmora, ''), 'T', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo_deudor'], 6, Moneda::formato($sum_capmora + $sumintatrasado + $sumintmora, ''), 'T', 1, 'R', 0, '', 1, 0);

        // Resumen por TipoEnti
        $pdf->Ln(5);
        $pdf->SetFont($this->fonte, 'B', 9);
        $pdf->Cell(0, 6, 'RESUMEN POR TIPO', '', 1, 'C');
        $pdf->Ln(2);

        $tiposDescripcion = ['GRUP' => 'GRUPALES', 'INDI' => 'INDIVIDUALES'];
        foreach ($tipo_enti as $tipo => $datos) {
            if ($datos['contador'] > 0) {
                $pdf->SetFont($this->fonte, 'B', 8);
                $pdf->CellFit(
                    $this->widthsColumns['credito'] + $this->widthsColumns['nombre_cliente'] + $this->widthsColumns['fec_otorgamiento'] + $this->widthsColumns['fec_vencimiento'] + $this->widthsColumns['fec_ultimo_pago'],
                    6,
                    Utf8::decode($tiposDescripcion[$tipo] . ' (' . $datos['contador'] . '):'),
                    '',
                    0,
                    'R',
                    0,
                    '',
                    1,
                    0
                );
                $pdf->CellFit($this->widthsColumns['monto'], 6, Moneda::formato($datos['montos'], ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_capital'], 6, Moneda::formato($datos['salcap'], ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['capital_mora'], 6, Moneda::formato($datos['capmora'], ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_corriente'], 6, Moneda::formato($datos['intatrasado'], ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['interes_mora'], 6, Moneda::formato($datos['intmora'], ''), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['saldo_deudor'], 6, Moneda::formato($datos['capmora'] + $datos['intatrasado'] + $datos['intmora'], ''), '', 1, 'R', 0, '', 1, 0);
            }
        }
    }

    protected function addHeaderExtraPDF($pdf)
    {
        $tamanio_linea = 5;

        //Fuente
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->CellFit($this->widthsColumns['credito'], $tamanio_linea + 1, Utf8::decode('CRÉDITO'), 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'NOMBRE DEL CLIENTE', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['fec_otorgamiento'], $tamanio_linea + 1, 'OTORGAMIENTO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['fec_vencimiento'], $tamanio_linea + 1, 'VENCIMIENTO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['fec_ultimo_pago'], $tamanio_linea + 1, Utf8::decode('ÚLTIMO PAGO'), 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['monto'], $tamanio_linea + 1, 'MONTO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo_capital'], $tamanio_linea + 1, 'SAL. CAP.', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['capital_mora'], $tamanio_linea + 1, 'CAP.MORA', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes_corriente'], $tamanio_linea + 1, 'INT. CORR.', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['interes_mora'], $tamanio_linea + 1, 'INT. MORA', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo_deudor'], $tamanio_linea + 1, 'SALDO DEUDOR', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['dias_atraso'], $tamanio_linea + 1, 'ATRASO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->Ln(7);
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

            $filtros = $this->filtros;

            // 2. Preparar filtros
            $filtros['Fecha de Corte'] = Date::toDMY($this->dataReporte['fecha_corte']);

            $title = 'CARTERA EN MORA AL ' . Date::toDMY($this->dataReporte['fecha_corte']);

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
        $sheet->setTitle("Cartera en Mora");

        // Encabezados
        $encabezados = [
            "FONDO",
            "AGENCIA",
            "EJECUTIVO",
            "CRÉDITO",
            "NOMBRE DEL CLIENTE",
            "DIRECCIÓN",
            "TEL 1",
            "TEL 2",
            "OTORGAMIENTO",
            "VENCIMIENTO",
            "ÚLTIMO PAGO",
            "MONTO DESEMBOLSADO",
            "SALDO CAPITAL",
            "CAPITAL EN MORA",
            "INTERES CORRIENTE",
            "INTERES EN MORA",
            "SALDO DEUDOR",
            "ATRASO",
            "DPI"
        ];

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($encabezados));
        $sheet->fromArray($encabezados, null, 'A9')->getStyle("A9:" . $lastCol . "9")->getFont()->setName($this->fonte)->setBold(true);

        // Totales
        $sum_montos = 0;
        $sum_salcap = 0;
        $sum_capmora = 0;
        $sumintatrasado = 0;
        $sumintmora = 0;
        $contador = 0;

        // Acumuladores por TipoEnti
        $tipo_enti = [];
        $tipo_enti['GRUP'] = ['contador' => 0, 'montos' => 0, 'salcap' => 0, 'capmora' => 0, 'intatrasado' => 0, 'intmora' => 0];
        $tipo_enti['INDI'] = ['contador' => 0, 'montos' => 0, 'salcap' => 0, 'capmora' => 0, 'intatrasado' => 0, 'intmora' => 0];

        foreach ($datos as $dato) {
            $cuenta = $dato['CCODCTA'];
            $nombre = strtoupper($dato['short_name']);
            $direccion = $dato['direccion'] ?? '';
            $tel1 = $dato['tel1'] ?? '';
            $tel2 = $dato['tel2'] ?? '';
            $dpi = $dato['dpi'] ?? '';
            $fechades = Date::isValid($dato['DFecDsbls']) ? $dato['DFecDsbls'] : '-';
            $fechaven = Date::isValid($dato['fechaven']) ? $dato['fechaven'] : '-';
            $fultpag = Date::isValid($dato['fechaultpag']) ? $dato['fechaultpag'] : '-';

            $monto = (float)$dato['NCapDes'];
            $cappag = (float)$dato['cappag'];
            $intpag = (float)$dato['intpag'];
            $intcal = (float)$dato['intcal'];
            $capcalafec = (float)$dato['capcalafec'];
            $intcalafec = (float)$dato['intcalafec'];
            $intmora = (float)$dato['intmora'];
            $diasatr = (int)$dato['atraso'];

            $nombrefondo = $dato['nombre_fondo'];
            $nomagencia = $dato['nom_agencia'];
            $nomanal = $dato['analista'];

            // Cálculos
            $salcap = max(0, $monto - $cappag);
            $capmora = max(0, $capcalafec - $cappag);
            $intatrasado = max(0, $intcalafec - $intpag);
            $saldodeudor = $capmora + $intatrasado + $intmora;

            // Datos de la fila
            $rowData = [
                strtoupper($nombrefondo),
                strtoupper($nomagencia),
                strtoupper($nomanal),
                $cuenta,
                $nombre,
                $direccion,
                $tel1,
                $tel2,
                $fechades,
                $fechaven,
                $fultpag,
                $monto,
                $salcap,
                $capmora,
                $intatrasado,
                $intmora,
                $saldodeudor,
                $diasatr
            ];

            $sheet->fromArray($rowData, null, 'A' . $row);

            // DPI como texto explícitamente para evitar notación científica
            $sheet->setCellValueExplicit('S' . $row, $dpi, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Acumular totales
            $sum_montos += $monto;
            $sum_salcap += $salcap;
            $sum_capmora += $capmora;
            $sumintatrasado += $intatrasado;
            $sumintmora += $intmora;
            $contador++;

            // Acumular por TipoEnti
            $tipoenti = $dato['TipoEnti'];
            if (isset($tipo_enti[$tipoenti])) {
                $tipo_enti[$tipoenti]['contador']++;
                $tipo_enti[$tipoenti]['montos'] += $monto;
                $tipo_enti[$tipoenti]['salcap'] += $salcap;
                $tipo_enti[$tipoenti]['capmora'] += $capmora;
                $tipo_enti[$tipoenti]['intatrasado'] += $intatrasado;
                $tipo_enti[$tipoenti]['intmora'] += $intmora;
            }

            $row++;
        }

        // Total general
        $sheet->setCellValue("A{$row}", "Número de créditos: " . $contador);
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("L{$row}", $sum_montos);
        $sheet->setCellValue("M{$row}", $sum_salcap);
        $sheet->setCellValue("N{$row}", $sum_capmora);
        $sheet->setCellValue("O{$row}", $sumintatrasado);
        $sheet->setCellValue("P{$row}", $sumintmora);
        $sheet->setCellValue("Q{$row}", $sum_capmora + $sumintatrasado + $sumintmora);

        $sheet->getStyle("L{$row}:Q{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFF00');

        $row++;
        $row++;

        // Resumen por TipoEnti
        $sheet->setCellValue("A{$row}", "RESUMEN POR TIPO");
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;
        $row++;

        $tiposDescripcion = ['GRUP' => 'GRUPALES', 'INDI' => 'INDIVIDUALES'];
        foreach ($tipo_enti as $tipo => $datos) {
            if ($datos['contador'] > 0) {
                $sheet->setCellValue("A{$row}", $tiposDescripcion[$tipo] . ' (' . $datos['contador'] . ')');
                $sheet->mergeCells("A{$row}:K{$row}");
                $sheet->setCellValue("L{$row}", $datos['montos']);
                $sheet->setCellValue("M{$row}", $datos['salcap']);
                $sheet->setCellValue("N{$row}", $datos['capmora']);
                $sheet->setCellValue("O{$row}", $datos['intatrasado']);
                $sheet->setCellValue("P{$row}", $datos['intmora']);
                $sheet->setCellValue("Q{$row}", $datos['capmora'] + $datos['intatrasado'] + $datos['intmora']);

                $sheet->getStyle("L{$row}:Q{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('E0E0E0');
                $row++;
            }
        }

        return $row;
    }

    /**
     * Obtiene datos de ejemplo
     */
    private function getData()
    {
        try {
            $this->database->openConnection();
            $where = ($this->dataReporte['radio_agencia']  == "anyofi") ? " AND ofi.id_agencia=" . $this->dataReporte['creAgenciaSelect'] : "";
            $where .= ($this->dataReporte['radio_agencia']  == "anyasesor") ? " AND usu.id_usu=" . $this->dataReporte['creUserSelect'] : "";
            $where .= ($this->dataReporte['radio_agencia']  == "anyregion") ? " AND ofi.id_agencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $this->dataReporte['creRegionSelect'] . ")" : "";
            $where .= ($this->dataReporte["radio_fondos"] == "anyf") ? " AND ffon.id=" . $this->dataReporte["creFondoSelect"] : "";

            $query = "SELECT
                    ofi.id_agencia,
                    cremi.CODAgencia,
                    ofi.nom_agencia,
                    cremi.CodAnal,
                    CONCAT(usu.nombre, ' ', usu.apellido) AS analista,
                    cremi.CCODCTA,
                    cremi.CESTADO,
                    prod.id_fondo AS id_fondos,
                    ffon.descripcion AS nombre_fondo,
                    prod.id AS id_producto,
                    prod.descripcion AS nombre_producto,
                    prod.tasa_interes AS tasa,
                    prod.porcentaje_mora AS tasamora,
                    cli.short_name,
                    cli.date_birth,
                    cli.genero,
                    cli.estado_civil,
                    cli.Direccion AS direccion,
                    cli.tel_no1 AS tel1,
                    cli.tel_no2 AS tel2,
                    cli.no_identifica As dpi,
                    cremi.DFecDsbls,
                    cremi.NCapDes,
                    cremi.TipoEnti,
                    IFNULL(ppg.dfecven, 0) AS fechaven,
                    IFNULL(ppg.sum_nintere, 0) AS intcal,
                    IFNULL(kar.dfecpro_ult, 0) AS fechaultpag,
                    IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
                    IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
                    IFNULL(kar.sum_KP, 0) AS cappag,
                    IFNULL(kar.sum_interes, 0) AS intpag,
                    IFNULL(kar.sum_MORA, 0) AS morpag,
                    IFNULL(kar.sum_AHOPRG_OTR, 0) AS otrpag,
                    cre_dias_atraso(?, cremi.CCODCTA) AS todos,
                    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso
                FROM cremcre_meta cremi
                INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
                INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
                INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo
                INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
                INNER JOIN tb_agencia ofi ON ofi.cod_agenc = cremi.CODAgencia
                LEFT JOIN (
                    SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(nintere) AS sum_nintere
                    FROM Cre_ppg
                    GROUP BY ccodcta
                ) AS ppg ON ppg.ccodcta = cremi.CCODCTA
                LEFT JOIN (
                    SELECT ccodcta, MAX(dfecpro) AS dfecpro_ult, SUM(KP) AS sum_KP, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
                    FROM CREDKAR
                    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
                    GROUP BY ccodcta
                ) AS kar ON kar.ccodcta = cremi.CCODCTA
                LEFT JOIN (
                    SELECT ccodcta, SUM(ncapita) AS sum_ncapita, SUM(nintere) AS sum_nintere
                    FROM Cre_ppg
                    WHERE dfecven <= ?
                    GROUP BY ccodcta
                ) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
                WHERE cremi.DFecDsbls <= ? AND (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 
                " . $where . "
                HAVING atraso > 0
                ORDER BY prod.id_fondo, ofi.id_agencia, cremi.CodAnal, prod.id, cremi.DFecDsbls;";

            $datos = $this->database->getAllResults($query, [
                $this->dataReporte['fecha_corte'],
                $this->dataReporte['fecha_corte'],
                $this->dataReporte['fecha_corte'],
                $this->dataReporte['fecha_corte'],
                $this->dataReporte['fecha_corte'],
            ]);

            if (empty($datos)) {
                throw new SoftException("No se encontraron datos para los filtros seleccionados.");
            }

            /**
             * procesamiento de los datos para el reporte
             * 
             */

            $data[] = [];
            $j = 0;
            foreach ($datos as $fil) {
                $diasatr = $fil["atraso"];
                if ($diasatr > 0) {
                    $data[$j] = $fil;

                    $todos = $fil['todos'];
                    $filasaux = substr($todos, 0, -1);
                    $filas = explode("#", $filasaux);

                    $intmora = 0;
                    for ($i = 0; $i < count($filas); $i++) {
                        $data[$j]["atrasadas"][$i] = explode("_", $filas[$i]);
                        // $intmora += ($data[$j]["atrasadas"][$i][1] * (($data[$j]['tasamora'] / 100) / 365) * $diasatr);
                        $moracalculada = $data[$j]["atrasadas"][$i][5];
                        $intmora += $moracalculada;
                    }
                    $data[$j]["intmora"] = $intmora;
                    unset($data[$j]["todos"]);
                    unset($data[$j][27]);

                    /**
                     * SI INTMORA NO ES MAYOR A CER0
                     * NO INCLUIR EN EL REPORTE
                     */
                    if ($intmora <= 0) {
                        unset($data[$j]);
                        continue;
                    }

                    $j++;
                }
            }

            /**
             * aqui agregar los filtros aplicados, para que los liste automaticamente en el reporte
             */

            if ($this->dataReporte['radio_agencia'] == 'anyregion') {
                $regionData = $this->database->selectColumns(
                    "cre_regiones",
                    ["nombre"],
                    "id=?",
                    [$this->dataReporte['creRegionSelect']]
                );
                $this->filtros['Region'] = $regionData[0]['nombre'] ?? '';
            }

            return $data;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }
}
