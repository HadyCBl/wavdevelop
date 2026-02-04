<!-- Modal de Análisis Financiero Individual -->
<div class="modal fade" id="modal_analisis_financiero" tabindex="-1" aria-labelledby="modalAnalisisFinancieroLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title mb-0" id="modalAnalisisFinancieroLabel">
                    <i class="fa-solid fa-chart-line me-2"></i>ANÁLISIS FINANCIERO - <span id="af_nombre_cliente_header"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" style="background-color: #f8f9fa;">
                
                <!-- SECCIÓN 1: Información del Cliente (Compacta) -->
                <div class="card mb-2 shadow-sm">
                    <div class="card-header bg-info text-white py-1">
                        <small class="mb-0"><i class="fa-solid fa-user me-1"></i>Información del Cliente</small>
                    </div>
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <small><strong>Código:</strong> <span id="af_cod_cliente"></span></small>
                            </div>
                            <div class="col-md-6">
                                <small><strong>Nombre:</strong> <span id="af_nombre_cliente"></span></small>
                            </div>
                            <div class="col-md-3">
                                <small><strong>DPI:</strong> <span id="af_dpi_cliente"></span></small>
                            </div>
                            <div class="col-md-3">
                                <small><strong>Teléfono:</strong> <span id="af_telefono_cliente"></span></small>
                            </div>
                            <div class="col-md-3">
                                <small><strong>Email:</strong> <span id="af_email_cliente"></span></small>
                            </div>
                            <div class="col-md-3">
                                <small><strong>Profesión:</strong> <span id="af_profesion_cliente"></span></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: Productos Financieros -->
                <div class="card mb-2 shadow-sm">
                    <div class="card-header bg-success text-white py-1">
                        <small class="mb-0"><i class="fa-solid fa-piggy-bank me-1"></i>Productos Financieros en la Institución</small>
                    </div>
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <!-- Cuentas de Ahorro -->
                            <div class="col-md-4">
                                <div class="card border-success h-100">
                                    <div class="card-header bg-success text-white py-1">
                                        <small><i class="fa-solid fa-money-bill-wave me-1"></i>Ahorros</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Código Cuenta</th>
                                                        <th>Estado</th>
                                                        <th class="text-end">Saldo</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="af_tabla_ahorros">
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">
                                                            <i class="fa-solid fa-circle-info me-1"></i>Sin registros
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mt-2 pt-2 border-top">
                                            <strong style="font-size: 0.85rem;">Total: <span id="af_total_ahorros" class="text-success">Q 0.00</span></strong>
                                        </div>
                                        <div id="af_alerta_ahorros" class="alert alert-warning alert-sm mt-2 mb-0 py-1 px-2" style="font-size: 0.7rem; display: none;">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Cliente sin ahorros
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cuentas de Aportaciones -->
                            <div class="col-md-4">
                                <div class="card border-info h-100">
                                    <div class="card-header bg-info text-white py-1">
                                        <small><i class="fa-solid fa-hand-holding-dollar me-1"></i>Aportaciones</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Código Cuenta</th>
                                                        <th>Estado</th>
                                                        <th class="text-end">Saldo</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="af_tabla_aportaciones">
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">
                                                            <i class="fa-solid fa-circle-info me-1"></i>Sin registros
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mt-2 pt-2 border-top">
                                            <strong style="font-size: 0.85rem;">Total: <span id="af_total_aportaciones" class="text-info">Q 0.00</span></strong>
                                        </div>
                                        <div id="af_alerta_aportaciones" class="alert alert-warning alert-sm mt-2 mb-0 py-1 px-2" style="font-size: 0.7rem; display: none;">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>Cliente sin aportaciones
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Créditos -->
                            <div class="col-md-4">
                                <div class="card border-danger h-100">
                                    <div class="card-header bg-danger text-white py-1">
                                        <small><i class="fa-solid fa-credit-card me-1"></i>Créditos</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.75rem;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Estado</th>
                                                        <th class="text-end">Saldo</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="af_tabla_creditos">
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">
                                                            <i class="fa-solid fa-circle-check me-1"></i>Sin créditos
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mt-2 pt-2 border-top">
                                            <strong style="font-size: 0.85rem;">Total: <span id="af_total_creditos" class="text-danger">Q 0.00</span></strong>
                                        </div>
                                        <div id="af_alerta_creditos" class="alert alert-danger alert-sm mt-2 mb-0 py-1 px-2" style="font-size: 0.7rem; display: none;">
                                            <i class="fa-solid fa-exclamation-circle me-1"></i>Alto endeudamiento
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumen General de Productos -->
                        <div class="row g-2 mt-2">
                            <div class="col-12">
                                <div class="card border-primary">
                                    <div class="card-body p-2">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Patrimonio Total</small>
                                                <h5 class="mb-0 text-primary" id="af_patrimonio_total">Q 0.00</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Saldo Créditos</small>
                                                <h5 class="mb-0 text-danger" id="af_saldo_creditos_total">Q 0.00</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Relación Deuda/Patrimonio</small>
                                                <h5 class="mb-0" id="af_relacion_deuda">
                                                    <span id="af_relacion_deuda_valor">0.00%</span>
                                                    <span class="badge ms-1" style="font-size: 0.7rem;" id="af_relacion_deuda_estado">-</span>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: Estado Patrimonial (Compacto) -->
                <div class="card mb-2 shadow-sm">
                    <div class="card-header bg-dark text-white py-1">
                        <small class="mb-0"><i class="fa-solid fa-scale-balanced me-1"></i>Estado Patrimonial</small>
                    </div>
                    <div class="card-body p-2">
                        <div class="row g-2">
                            <!-- ACTIVOS -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-2" style="font-size: 0.9rem;">ACTIVOS</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
                                        <tbody>
                                            <tr class="table-light">
                                                <td colspan="2"><strong>CIRCULANTE</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Disponible</td>
                                                <td class="text-end" id="af_disponible">Q 0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Cuentas por cobrar</td>
                                                <td class="text-end" id="af_cuentas_cobrar">Q 0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Inventario</td>
                                                <td class="text-end" id="af_inventario">Q 0.00</td>
                                            </tr>
                                            <tr class="table-info">
                                                <td><strong>Total Circulante</strong></td>
                                                <td class="text-end"><strong id="af_total_circulante">Q 0.00</strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="2"><strong>ACTIVO FIJO</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Total Activo Fijo</td>
                                                <td class="text-end" id="af_activo_fijo">Q 0.00</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td><strong>SUMA TOTAL DEL ACTIVO</strong></td>
                                                <td class="text-end"><strong id="af_total_activo">Q 0.00</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- PASIVOS Y PATRIMONIO -->
                            <div class="col-md-6">
                                <h6 class="text-danger mb-2" style="font-size: 0.9rem;">PASIVOS Y PATRIMONIO</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
                                        <tbody>
                                            <tr class="table-light">
                                                <td colspan="2"><strong>OBLIGACIONES A CORTO PLAZO</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Proveedores</td>
                                                <td class="text-end" id="af_proveedores">Q 0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Otros préstamos</td>
                                                <td class="text-end" id="af_otros_prestamos">Q 0.00</td>
                                            </tr>
                                            <tr>
                                                <td>Préstamos a instituciones</td>
                                                <td class="text-end" id="af_prest_instituciones">Q 0.00</td>
                                            </tr>
                                            <tr class="table-danger">
                                                <td><strong>Suma Pasivo</strong></td>
                                                <td class="text-end"><strong id="af_total_pasivo">Q 0.00</strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="2"><strong>PATRIMONIO</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Patrimonio</td>
                                                <td class="text-end" id="af_patrimonio">Q 0.00</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td><strong>PASIVO Y PATRIMONIO</strong></td>
                                                <td class="text-end"><strong id="af_total_pasivo_patrimonio">Q 0.00</strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 4: Estado de Ingresos y Egresos -->
                <div class="card mb-2 shadow-sm">
                    <div class="card-header bg-secondary text-white py-1">
                        <small class="mb-0"><i class="fa-solid fa-money-bill-trend-up me-1"></i>Estado de Ingresos y Egresos</small>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-end">Mensual</th>
                                        <th class="text-end">Anual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-success">
                                        <td colspan="3"><strong>INGRESOS</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Ventas</td>
                                        <td class="text-end" id="af_ventas_mensual">Q 0.00</td>
                                        <td class="text-end" id="af_ventas_anual">Q 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Recup. cuentas por cobrar</td>
                                        <td class="text-end" id="af_recup_mensual">Q 0.00</td>
                                        <td class="text-end" id="af_recup_anual">Q 0.00</td>
                                    </tr>
                                    <tr class="table-info">
                                        <td><strong>Total Ingresos</strong></td>
                                        <td class="text-end"><strong id="af_total_ingresos_mensual">Q 0.00</strong></td>
                                        <td class="text-end"><strong id="af_total_ingresos_anual">Q 0.00</strong></td>
                                    </tr>
                                    <tr class="table-danger">
                                        <td colspan="3"><strong>EGRESOS</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Compra de mercadería</td>
                                        <td class="text-end" id="af_mercaderia_mensual">Q 0.00</td>
                                        <td class="text-end" id="af_mercaderia_anual">Q 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Gastos del negocio</td>
                                        <td class="text-end" id="af_negocio_mensual">Q 0.00</td>
                                        <td class="text-end" id="af_negocio_anual">Q 0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Pagos de créditos</td>
                                        <td class="text-end" id="af_pagos_mensual">Q 0.00</td>
                                        <td class="text-end" id="af_pagos_anual">Q 0.00</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td><strong>Total Egresos</strong></td>
                                        <td class="text-end"><strong id="af_total_egresos_mensual">Q 0.00</strong></td>
                                        <td class="text-end"><strong id="af_total_egresos_anual">Q 0.00</strong></td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td><strong>DIF. INGRESOS - EGRESOS</strong></td>
                                        <td class="text-end"><strong id="af_diferencia_mensual">Q 0.00</strong></td>
                                        <td class="text-end"><strong id="af_diferencia_anual">Q 0.00</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 5: Razones Financieras -->
                <div class="card mb-2 shadow-sm">
                    <div class="card-header bg-primary text-white py-1">
                        <small class="mb-0"><i class="fa-solid fa-calculator me-1"></i>Razones Financieras</small>
                    </div>
                    <div class="card-body p-2">
                        
                        <!-- A. INDICADORES DE LIQUIDEZ -->
                        <h6 class="text-primary mb-2" style="font-size: 0.9rem;"><strong>INDICADORES DE LIQUIDEZ</strong></h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="card border-primary h-100">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Razón Circulante</small>
                                        <h4 class="mb-1" id="af_razon_circulante_valor">0.00</h4>
                                        <span class="badge" style="font-size: 0.75rem;" id="af_razon_circulante_estado">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info h-100">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Prueba del Ácido</small>
                                        <h4 class="mb-1" id="af_prueba_acido_valor">0.00</h4>
                                        <span class="badge" style="font-size: 0.75rem;" id="af_prueba_acido_estado">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success h-100">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Capital de Trabajo</small>
                                        <h4 class="mb-1" id="af_capital_trabajo_valor">Q 0.00</h4>
                                        <span class="badge" style="font-size: 0.75rem;" id="af_capital_trabajo_estado">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- B. INDICADORES DE SOLVENCIA -->
                        <h6 class="text-danger mb-2" style="font-size: 0.9rem;"><strong>INDICADORES DE SOLVENCIA</strong></h6>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="card border-danger h-100">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Apalancamiento</small>
                                        <h4 class="mb-1" id="af_apalancamiento_valor">0.00</h4>
                                        <span class="badge" style="font-size: 0.75rem;" id="af_apalancamiento_estado">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- C. INDICADORES DE EFICIENCIA OPERATIVA -->
                        <h6 class="text-warning mb-2" style="font-size: 0.9rem;"><strong>INDICADORES DE EFICIENCIA OPERATIVA</strong></h6>
                        <div class="row g-2 mb-2">
                            <div class="col-md-3">
                                <div class="card border-secondary h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Rotación CXC</small>
                                        <p class="mb-0 fw-bold"><span id="af_rotacion_cxc_valor">0.00</span> veces</p>
                                        <small class="text-muted"><span id="af_dias_cxc_valor">0</span> días</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-secondary h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Rotación Inventario</small>
                                        <p class="mb-0 fw-bold"><span id="af_rotacion_inventario_valor">0.00</span> veces</p>
                                        <small class="text-muted"><span id="af_dias_inventario_valor">0</span> días</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-secondary h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Rotación Activos</small>
                                        <p class="mb-0 fw-bold" id="af_rotacion_activos_valor">0.00 veces</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-secondary h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Rotación Act. Fijos</small>
                                        <p class="mb-0 fw-bold" id="af_rotacion_activos_fijos_valor">0.00 veces</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-secondary h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Rotación CTN</small>
                                        <p class="mb-0 fw-bold" id="af_rotacion_ctn_valor">0.00 veces</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- D. INDICADORES DE RENTABILIDAD -->
                        <h6 class="text-success mb-2" style="font-size: 0.9rem;"><strong>INDICADORES DE RENTABILIDAD</strong></h6>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="card border-success h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">ROE</small>
                                        <h4 class="mb-1" id="af_roe_valor">0.00%</h4>
                                        <span class="badge" style="font-size: 0.75rem;" id="af_roe_estado">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">ROA</small>
                                        <h4 class="mb-1" id="af_roa_valor">0.00%</h4>
                                        <span class="badge" style="font-size: 0.75rem;" id="af_roa_estado">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Margen Ventas</small>
                                        <h4 class="mb-1" id="af_margen_ventas_valor">0.00%</h4>
                                        <span class="badge" style="font-size: 0.75rem;" id="af_margen_ventas_estado">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success h-100 shadow-sm">
                                    <div class="card-body p-2 text-center">
                                        <small class="text-muted d-block mb-1">Margen Neto</small>
                                        <h4 class="mb-1" id="af_margen_neto_valor">Q 0.00</h4>
                                        <small class="text-muted" id="af_margen_neto_porcentaje">0.00%</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-primary" onclick="generarPDFAnalisisFinanciero()">
                    <i class="fa-solid fa-file-pdf me-1"></i>Generar PDF
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-circle-xmark me-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Funciones de análisis financiero
function abrirAnalisisFinanciero(codCliente) {
    if (!codCliente) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'Debe seleccionar un cliente primero'
        });
        return;
    }
    
    // Cargar datos del cliente
    cargarDatosAnalisisFinanciero(codCliente);
    
    // Abrir modal
    $('#modal_analisis_financiero').modal('show');
}

function cargarDatosAnalisisFinanciero(codCliente) {
    $.ajax({
        url: '../../views/Creditos/cre_indi/cre_indi_01.php',
        method: 'POST',
        data: {
            condi: 'obtener_datos_analisis_financiero',
            xtra: codCliente
        },
        dataType: 'json',
        beforeSend: function() {
            loaderefect(1);
        },
        success: function(response) {
            //console.log('=== RESPUESTA COMPLETA DEL SERVIDOR ===');
            //console.log('Respuesta:', response);
            //console.log('Productos:', response.productos);
            if (response.productos) {
                //console.log('Ahorros:', response.productos.ahorros);
                //console.log('Aportaciones:', response.productos.aportaciones);
                //console.log('Créditos:', response.productos.creditos);
            }
            //console.log('Balance:', response.balance);
            
            Swal.close();
            
            if (response.success) {
                // Actualizar encabezado con nombre del cliente
                $('#af_nombre_cliente_header').text(response.cliente.nombre);
                
                // Información del cliente
                $('#af_cod_cliente').text(response.cliente.codcli);
                $('#af_nombre_cliente').text(response.cliente.nombre);
                $('#af_dpi_cliente').text(response.cliente.dpi);
                $('#af_telefono_cliente').text(response.cliente.telefono);
                $('#af_email_cliente').text(response.cliente.email || 'No registrado');
                $('#af_profesion_cliente').text(response.cliente.profesion || 'No especificada');
                
                // Mensaje de balance
                if (response.mensaje_balance) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Información',
                        text: response.mensaje_balance,
                        showCancelButton: false,
                        confirmButtonText: 'Entendido'
                    });
                }
                
                // Balance y cálculos
                // Cargar productos financieros
                if (response.productos) {
                    cargarProductosFinancieros(response.productos);
                }
                
                if (response.balance) {
                    //console.log('Balance:', response.balance);
                    mostrarEstadoPatrimonial(response.balance);
                    mostrarEstadoResultados(response.balance);
                    calcularYMostrarRazonesFinancieras(response.balance);
                    
                    if (response.mensaje_balance) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Información',
                            text: response.mensaje_balance
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Balance no disponible',
                        text: 'El cliente no tiene un balance económico registrado'
                    });
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.mensaje || 'Error al cargar los datos'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión al servidor: ' + error
            });
        },
        complete: function() {
            loaderefect(0);
        }
    });
}

function cargarProductosFinancieros(productos) {
    let totalAhorros = 0;
    let totalAportaciones = 0;
    let totalCreditos = 0;
    
    // Ahorros
    if (productos.ahorros && productos.ahorros.length > 0) {
        let htmlAhorros = '';
        productos.ahorros.forEach(function(cuenta) {
            let saldo = parseFloat(cuenta.saldo) || 0;
            totalAhorros += saldo;
            let estadoBadge = cuenta.estado == 'A' ? '<span class="badge bg-success" style="font-size: 0.65rem;">ACT</span>' : '<span class="badge bg-secondary" style="font-size: 0.65rem;">INA</span>';
            htmlAhorros += `
                <tr>
                    <td>${cuenta.codigo}</td>
                    <td>${estadoBadge}</td>
                    <td class="text-end">Q ${formatoNumero(saldo)}</td>
                </tr>
            `;
        });
        $('#af_tabla_ahorros').html(htmlAhorros);
        $('#af_alerta_ahorros').hide();
    } else {
        $('#af_tabla_ahorros').html('<tr><td colspan="3" class="text-center text-muted"><i class="fa-solid fa-circle-info me-1"></i>Sin registros</td></tr>');
        $('#af_alerta_ahorros').show();
    }
    
    // Aportaciones
    if (productos.aportaciones && productos.aportaciones.length > 0) {
        let htmlAportaciones = '';
        productos.aportaciones.forEach(function(cuenta) {
            let saldo = parseFloat(cuenta.saldo) || 0;
            totalAportaciones += saldo;
            let estadoBadge = cuenta.estado == 'A' ? '<span class="badge bg-success" style="font-size: 0.65rem;">ACT</span>' : '<span class="badge bg-secondary" style="font-size: 0.65rem;">INA</span>';
            htmlAportaciones += `
                <tr>
                    <td>${cuenta.codigo}</td>
                    <td>${estadoBadge}</td>
                    <td class="text-end">Q ${formatoNumero(saldo)}</td>
                </tr>
            `;
        });
        $('#af_tabla_aportaciones').html(htmlAportaciones);
        $('#af_alerta_aportaciones').hide();
    } else {
        $('#af_tabla_aportaciones').html('<tr><td colspan="3" class="text-center text-muted"><i class="fa-solid fa-circle-info me-1"></i>Sin registros</td></tr>');
        $('#af_alerta_aportaciones').show();
    }
    
    // Créditos
    if (productos.creditos && productos.creditos.length > 0) {
        let htmlCreditos = '';
        productos.creditos.forEach(function(credito) {
            let saldo = parseFloat(credito.saldo_capital) || 0;
            totalCreditos += saldo;
            let estadoBadge = credito.estado == 'F' ? '<span class="badge bg-success" style="font-size: 0.65rem;">VIG</span>' : 
                             (credito.estado == 'G' ? '<span class="badge bg-secondary" style="font-size: 0.65rem;">CAN</span>' : 
                             '<span class="badge bg-warning" style="font-size: 0.65rem;">INA</span>');
            htmlCreditos += `
                <tr>
                    <td>${credito.codigo_credito}</td>
                    <td>${estadoBadge}</td>
                    <td class="text-end">Q ${formatoNumero(saldo)}</td>
                </tr>
            `;
        });
        $('#af_tabla_creditos').html(htmlCreditos);
    } else {
        $('#af_tabla_creditos').html('<tr><td colspan="3" class="text-center text-muted"><i class="fa-solid fa-circle-check me-1"></i>Sin créditos</td></tr>');
    }
    
    // Totales y resumen
    let patrimonioTotal = totalAhorros + totalAportaciones;
    let relacionDeuda = patrimonioTotal > 0 ? (totalCreditos / patrimonioTotal) * 100 : 0;
    
    $('#af_total_ahorros').text('Q ' + formatoNumero(totalAhorros));
    $('#af_total_aportaciones').text('Q ' + formatoNumero(totalAportaciones));
    $('#af_total_creditos').text('Q ' + formatoNumero(totalCreditos));
    $('#af_patrimonio_total').text('Q ' + formatoNumero(patrimonioTotal));
    $('#af_saldo_creditos_total').text('Q ' + formatoNumero(totalCreditos));
    $('#af_relacion_deuda_valor').text(relacionDeuda.toFixed(2) + '%');
    
    // Estado de la relación deuda
    if (relacionDeuda > 80) {
        $('#af_relacion_deuda_estado').removeClass().addClass('badge bg-danger ms-1').text('ALTO RIESGO');
        $('#af_alerta_creditos').show().html('<i class="fa-solid fa-exclamation-circle me-1"></i>Relación deuda superior al 80%');
    } else if (relacionDeuda > 50) {
        $('#af_relacion_deuda_estado').removeClass().addClass('badge bg-warning ms-1').text('MODERADO');
        $('#af_alerta_creditos').hide();
    } else {
        $('#af_relacion_deuda_estado').removeClass().addClass('badge bg-success ms-1').text('SALUDABLE');
        $('#af_alerta_creditos').hide();
    }
}

function cargarGarantiasAnalisis(garantias) {
    let totalGarantias = 0;
    
    if (garantias && garantias.length > 0) {
        let htmlGarantias = '';
        garantias.forEach(function(garantia) {
            let valor = parseFloat(garantia.montogravamen) || 0;
            totalGarantias += valor;
            htmlGarantias += `
                <tr>
                    <td>${garantia.nomtipgar}</td>
                    <td>${garantia.nomtipdoc}</td>
                    <td>${garantia.descripcion}</td>
                    <td>${garantia.direccion}</td>
                    <td class="text-end">Q ${formatoNumero(valor)}</td>
                </tr>
            `;
        });
        $('#af_tabla_garantias').html(htmlGarantias);
    }
    
    $('#af_total_garantias').text('Q ' + formatoNumero(totalGarantias));
}

function mostrarEstadoPatrimonial(balance) {
    // Activos
    let disponible = parseFloat(balance.disponible) || 0;
    let cuentasCobrar = parseFloat(balance.cuenta_por_cobrar2) || 0;
    let inventario = parseFloat(balance.inventario) || 0;
    let activoFijo = parseFloat(balance.activo_fijo) || 0;
    
    let totalCirculante = disponible + cuentasCobrar + inventario;
    let totalActivo = totalCirculante + activoFijo;
    
    $('#af_disponible').text('Q ' + formatoNumero(disponible));
    $('#af_cuentas_cobrar').text('Q ' + formatoNumero(cuentasCobrar));
    $('#af_inventario').text('Q ' + formatoNumero(inventario));
    $('#af_activo_fijo').text('Q ' + formatoNumero(activoFijo));
    $('#af_total_circulante').text('Q ' + formatoNumero(totalCirculante));
    $('#af_total_activo').text('Q ' + formatoNumero(totalActivo));
    
    // Pasivos
    let proveedores = parseFloat(balance.proveedores) || 0;
    let otrosPrestamos = parseFloat(balance.otros_prestamos) || 0;
    let prestInstituciones = parseFloat(balance.prest_instituciones) || 0;
    let patrimonio = parseFloat(balance.patrimonio) || 0;
    
    let totalPasivo = proveedores + otrosPrestamos + prestInstituciones;
    let totalPasivoPatrimonio = totalPasivo + patrimonio;
    
    $('#af_proveedores').text('Q ' + formatoNumero(proveedores));
    $('#af_otros_prestamos').text('Q ' + formatoNumero(otrosPrestamos));
    $('#af_prest_instituciones').text('Q ' + formatoNumero(prestInstituciones));
    $('#af_patrimonio').text('Q ' + formatoNumero(patrimonio));
    $('#af_total_pasivo').text('Q ' + formatoNumero(totalPasivo));
    $('#af_total_pasivo_patrimonio').text('Q ' + formatoNumero(totalPasivoPatrimonio));
}

function mostrarEstadoResultados(balance) {
    let ventasMensual = parseFloat(balance.ventas) || 0;
    let recupMensual = parseFloat(balance.cuenta_por_cobrar) || 0;
    let mercaderiaMensual = parseFloat(balance.mercaderia) || 0;
    let negocioMensual = parseFloat(balance.negocio) || 0;
    let pagosMensual = parseFloat(balance.pago_creditos) || 0;
    
    let totalIngresosMensual = ventasMensual + recupMensual;
    let totalEgresosMensual = mercaderiaMensual + negocioMensual + pagosMensual;
    let diferenciaMensual = totalIngresosMensual - totalEgresosMensual;
    
    // Anuales
    let ventasAnual = ventasMensual * 12;
    let recupAnual = recupMensual * 12;
    let mercaderiaAnual = mercaderiaMensual * 12;
    let negocioAnual = negocioMensual * 12;
    let pagosAnual = pagosMensual * 12;
    
    let totalIngresosAnual = ventasAnual + recupAnual;
    let totalEgresosAnual = mercaderiaAnual + negocioAnual + pagosAnual;
    let diferenciaAnual = totalIngresosAnual - totalEgresosAnual;
    
    // Mostrar valores
    $('#af_ventas_mensual').text('Q ' + formatoNumero(ventasMensual));
    $('#af_ventas_anual').text('Q ' + formatoNumero(ventasAnual));
    $('#af_recup_mensual').text('Q ' + formatoNumero(recupMensual));
    $('#af_recup_anual').text('Q ' + formatoNumero(recupAnual));
    $('#af_total_ingresos_mensual').text('Q ' + formatoNumero(totalIngresosMensual));
    $('#af_total_ingresos_anual').text('Q ' + formatoNumero(totalIngresosAnual));
    
    $('#af_mercaderia_mensual').text('Q ' + formatoNumero(mercaderiaMensual));
    $('#af_mercaderia_anual').text('Q ' + formatoNumero(mercaderiaAnual));
    $('#af_negocio_mensual').text('Q ' + formatoNumero(negocioMensual));
    $('#af_negocio_anual').text('Q ' + formatoNumero(negocioAnual));
    $('#af_pagos_mensual').text('Q ' + formatoNumero(pagosMensual));
    $('#af_pagos_anual').text('Q ' + formatoNumero(pagosAnual));
    $('#af_total_egresos_mensual').text('Q ' + formatoNumero(totalEgresosMensual));
    $('#af_total_egresos_anual').text('Q ' + formatoNumero(totalEgresosAnual));
    
    $('#af_diferencia_mensual').text('Q ' + formatoNumero(diferenciaMensual));
    $('#af_diferencia_anual').text('Q ' + formatoNumero(diferenciaAnual));
}

function calcularYMostrarRazonesFinancieras(balance) {
    // Valores base
    let disponible = parseFloat(balance.disponible) || 0;
    let cuentasCobrar = parseFloat(balance.cuenta_por_cobrar2) || 0;
    let inventario = parseFloat(balance.inventario) || 0;
    let activoFijo = parseFloat(balance.activo_fijo) || 0;
    let proveedores = parseFloat(balance.proveedores) || 0;
    let otrosPrestamos = parseFloat(balance.otros_prestamos) || 0;
    let prestInstituciones = parseFloat(balance.prest_instituciones) || 0;
    let patrimonio = parseFloat(balance.patrimonio) || 0;
    let ventas = parseFloat(balance.ventas) || 0;
    let recupCuentasCobrar = parseFloat(balance.cuenta_por_cobrar) || 0;
    let mercaderia = parseFloat(balance.mercaderia) || 0;
    let negocio = parseFloat(balance.negocio) || 0;
    let pagoCreditos = parseFloat(balance.pago_creditos) || 0;
    
    // Cálculos intermedios
    let activoCirculante = disponible + cuentasCobrar + inventario;
    let activoTotal = activoCirculante + activoFijo;
    let pasivoTotal = proveedores + otrosPrestamos + prestInstituciones;
    let utilidadNeta = (ventas + recupCuentasCobrar) - (mercaderia + negocio + pagoCreditos);
    let ventasAnuales = ventas * 12;
    
    // 1. Razón Circulante
    let razonCirculante = pasivoTotal > 0 ? activoCirculante / pasivoTotal : 0;
    $('#af_razon_circulante_valor').text(razonCirculante.toFixed(2));
    if (razonCirculante >= 2) {
        $('#af_razon_circulante_estado').removeClass().addClass('badge bg-success').text('BUENO');
        $('#af_razon_circulante_interpretacion').text('Excelente liquidez para cubrir obligaciones.');
    } else if (razonCirculante >= 1) {
        $('#af_razon_circulante_estado').removeClass().addClass('badge bg-warning').text('REGULAR');
        $('#af_razon_circulante_interpretacion').text('Liquidez aceptable, puede mejorar.');
    } else {
        $('#af_razon_circulante_estado').removeClass().addClass('badge bg-danger').text('MALO');
        $('#af_razon_circulante_interpretacion').text('Problemas de liquidez a corto plazo.');
    }
    
    // 2. Prueba del Ácido
    let pruebaAcido = pasivoTotal > 0 ? (disponible + cuentasCobrar) / pasivoTotal : 0;
    $('#af_prueba_acido_valor').text(pruebaAcido.toFixed(2));
    if (pruebaAcido >= 1) {
        $('#af_prueba_acido_estado').removeClass().addClass('badge bg-success').text('EXCELENTE');
        $('#af_prueba_acido_interpretacion').text('Capacidad inmediata de pago sin inventario.');
    } else if (pruebaAcido >= 0.7) {
        $('#af_prueba_acido_estado').removeClass().addClass('badge bg-warning').text('ACEPTABLE');
        $('#af_prueba_acido_interpretacion').text('Liquidez inmediata aceptable.');
    } else {
        $('#af_prueba_acido_estado').removeClass().addClass('badge bg-danger').text('BAJO');
        $('#af_prueba_acido_interpretacion').text('Depende mucho del inventario para liquidez.');
    }
    
    // 3. Capital de Trabajo
    let capitalTrabajo = activoCirculante - pasivoTotal;
    $('#af_capital_trabajo_valor').text('Q ' + formatoNumero(capitalTrabajo));
    if (capitalTrabajo > 0) {
        $('#af_capital_trabajo_estado').removeClass().addClass('badge bg-success').text('POSITIVO');
        $('#af_capital_trabajo_interpretacion').text('Tiene recursos para operar el negocio.');
    } else {
        $('#af_capital_trabajo_estado').removeClass().addClass('badge bg-danger').text('NEGATIVO');
        $('#af_capital_trabajo_interpretacion').text('Problemas para cubrir operaciones.');
    }
    
    // 4. Apalancamiento
    let apalancamiento = patrimonio > 0 ? pasivoTotal / patrimonio : 0;
    $('#af_apalancamiento_valor').text(apalancamiento.toFixed(2));
    if (apalancamiento < 1) {
        $('#af_apalancamiento_estado').removeClass().addClass('badge bg-success').text('BAJO');
        $('#af_apalancamiento_interpretacion').text('Bajo endeudamiento, buena solvencia.');
    } else if (apalancamiento <= 2) {
        $('#af_apalancamiento_estado').removeClass().addClass('badge bg-warning').text('MODERADO');
        $('#af_apalancamiento_interpretacion').text('Endeudamiento moderado y controlable.');
    } else {
        $('#af_apalancamiento_estado').removeClass().addClass('badge bg-danger').text('ALTO');
        $('#af_apalancamiento_interpretacion').text('Alto endeudamiento, riesgo elevado.');
    }
    
    // 5. Rotación CXC
    let rotacionCXC = cuentasCobrar > 0 ? ventasAnuales / cuentasCobrar : 0;
    $('#af_rotacion_cxc_valor').text(rotacionCXC.toFixed(2));
    $('#af_rotacion_cxc_interpretacion').text('Las cuentas se cobran ' + rotacionCXC.toFixed(1) + ' veces al año.');
    
    // 6. Días CXC
    let diasCXC = rotacionCXC > 0 ? Math.round(365 / rotacionCXC) : 0;
    $('#af_dias_cxc_valor').text(diasCXC);
    $('#af_dias_cxc_interpretacion').text('Se tarda ' + diasCXC + ' días en recuperar las ventas a crédito.');
    
    // 7. Rotación Inventario
    let rotacionInventario = inventario > 0 ? mercaderia / inventario : 0;
    $('#af_rotacion_inventario_valor').text(rotacionInventario.toFixed(2));
    $('#af_rotacion_inventario_interpretacion').text('El inventario se renueva ' + rotacionInventario.toFixed(1) + ' veces al mes.');
    
    // 8. Días Inventario
    let diasInventario = rotacionInventario > 0 ? Math.round(365 / (rotacionInventario * 12)) : 0;
    $('#af_dias_inventario_valor').text(diasInventario);
    $('#af_dias_inventario_interpretacion').text('Se requieren ' + diasInventario + ' días para procesar el inventario.');
    
    // 9. Rotación Activos Totales
    let rotacionActivos = activoTotal > 0 ? ventasAnuales / activoTotal : 0;
    $('#af_rotacion_activos_valor').text(rotacionActivos.toFixed(2));
    $('#af_rotacion_activos_interpretacion').text('Cada Q1 en activos genera Q' + rotacionActivos.toFixed(2) + ' en ventas.');
    
    // 10. Rotación Activos Fijos
    let rotacionActivosFijos = activoFijo > 0 ? ventasAnuales / activoFijo : 0;
    $('#af_rotacion_activos_fijos_valor').text(rotacionActivosFijos.toFixed(2));
    $('#af_rotacion_activos_fijos_interpretacion').text('Cada Q1 en activo fijo genera Q' + rotacionActivosFijos.toFixed(2) + ' en ventas.');
    
    // 11. Rotación Capital Trabajo
    let rotacionCTN = capitalTrabajo > 0 ? ventasAnuales / capitalTrabajo : 0;
    $('#af_rotacion_ctn_valor').text(rotacionCTN.toFixed(2));
    $('#af_rotacion_ctn_interpretacion').text('El capital de trabajo genera ' + rotacionCTN.toFixed(2) + ' veces su valor en ventas.');
    
    // 12. ROE (Rentabilidad / Capital)
    let roe = patrimonio > 0 ? (utilidadNeta / patrimonio) * 100 : 0;
    $('#af_roe_valor').text(roe.toFixed(2));
    if (roe > 15) {
        $('#af_roe_estado').removeClass().addClass('badge bg-success').text('EXCELENTE');
        $('#af_roe_interpretacion').text('Excelente rendimiento sobre el capital.');
    } else if (roe >= 5) {
        $('#af_roe_estado').removeClass().addClass('badge bg-warning').text('ACEPTABLE');
        $('#af_roe_interpretacion').text('Rendimiento aceptable sobre el capital.');
    } else {
        $('#af_roe_estado').removeClass().addClass('badge bg-danger').text('BAJO');
        $('#af_roe_interpretacion').text('Bajo rendimiento sobre el capital.');
    }
    
    // 13. ROA (Rentabilidad / Activos)
    let roa = activoTotal > 0 ? (utilidadNeta / activoTotal) * 100 : 0;
    $('#af_roa_valor').text(roa.toFixed(2));
    if (roa > 10) {
        $('#af_roa_estado').removeClass().addClass('badge bg-success').text('EXCELENTE');
        $('#af_roa_interpretacion').text('Excelente eficiencia en uso de activos.');
    } else if (roa >= 5) {
        $('#af_roa_estado').removeClass().addClass('badge bg-warning').text('ACEPTABLE');
        $('#af_roa_interpretacion').text('Eficiencia aceptable en uso de activos.');
    } else {
        $('#af_roa_estado').removeClass().addClass('badge bg-danger').text('BAJO');
        $('#af_roa_interpretacion').text('Baja eficiencia en uso de activos.');
    }
    
    // 14. Rentabilidad / Ventas
    let margenVentas = ventasAnuales > 0 ? (utilidadNeta / ventasAnuales) * 100 : 0;
    $('#af_margen_ventas_valor').text(margenVentas.toFixed(2));
    if (margenVentas > 15) {
        $('#af_margen_ventas_estado').removeClass().addClass('badge bg-success').text('EXCELENTE');
        $('#af_margen_ventas_interpretacion').text('Excelente margen de utilidad por ventas.');
    } else if (margenVentas >= 5) {
        $('#af_margen_ventas_estado').removeClass().addClass('badge bg-warning').text('ACEPTABLE');
        $('#af_margen_ventas_interpretacion').text('Margen aceptable de utilidad por ventas.');
    } else {
        $('#af_margen_ventas_estado').removeClass().addClass('badge bg-danger').text('BAJO');
        $('#af_margen_ventas_interpretacion').text('Bajo margen de utilidad por ventas.');
    }
    
    // 15. Margen Neto
    $('#af_margen_neto_valor').text('Q ' + formatoNumero(utilidadNeta));
    $('#af_margen_neto_porcentaje').text(margenVentas.toFixed(2) + '%');
    $('#af_margen_neto_interpretacion').text('Utilidad neta de Q' + formatoNumero(utilidadNeta) + ' después de todos los gastos.');
}

function formatoNumero(numero) {
    return Number(numero).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function generarPDFAnalisisFinanciero() {
    let codCliente = $('#af_cod_cliente').text();
    if (!codCliente) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'No hay datos para generar el PDF'
        });
        return;
    }
    
    // Redirigir a la generación de PDF
    window.open('../../../views/Creditos/reportes/analisis_financiero_pdf.php?cliente=' + codCliente, '_blank');
}
</script>
