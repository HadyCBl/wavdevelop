<?php

return function ($r) {
    // Grupo de rutas con prefijo /api
    $r->addGroup('/api', function ($r) {

        $r->addGroup('/seguros', function ($r) {
            $r->addRoute('GET', '/servicios/index', 'ServiciosController@index');
            $r->addRoute('POST', '/servicios', 'ServiciosController@store');
            $r->addRoute('PUT', '/servicios', 'ServiciosController@update');
            $r->addRoute('GET', '/servicios/edit/{id}', 'ServiciosController@edit');
            $r->addRoute('DELETE', '/servicios/{id}', 'ServiciosController@delete');

            $r->addRoute('GET', '/cuentas/index', 'CuentasController@index');
            $r->addRoute('POST', '/cuentas', 'CuentasController@store');
            // $r->addRoute('PUT', '/cuentas', 'CuentasController@update');
            $r->addRoute('GET', '/cuentas/edit/{id}', 'CuentasController@edit');
            // $r->addRoute('DELETE', '/cuentas/{id}', 'CuentasController@delete');

            $r->addRoute('GET', '/renovaciones/index', 'RenovacionesController@index');
            $r->addRoute('GET', '/renovaciones/{id}', 'RenovacionesController@show');
            $r->addRoute('POST', '/renovaciones', 'RenovacionesController@store');
            // $r->addRoute('PUT', '/renovaciones', 'RenovacionesController@update');
            $r->addRoute('GET', '/renovaciones/edit/{id}', 'RenovacionesController@edit');
            // $r->addRoute('DELETE', '/renovaciones/{id}', 'RenovacionesController@delete');

            /**
             * RUTAS PARA BENEFICIARIOS
             */
            $r->addRoute('GET', '/beneficiarios/index', 'BeneficiariosController@index');
             $r->addRoute('GET', '/beneficiarios/{id}', 'BeneficiariosController@show');
            $r->addRoute('POST', '/beneficiarios', 'BeneficiariosController@store');
            $r->addRoute('GET', '/beneficiarios/edit/{id}', 'BeneficiariosController@edit');
            $r->addRoute('PUT', '/beneficiarios', 'BeneficiariosController@update');
            $r->addRoute('DELETE', '/beneficiarios/{id}', 'BeneficiariosController@destroy');

            /**
             * RUTAS PARA AUXILIOS
             * IMPORTANTE: Rutas estáticas ANTES de rutas dinámicas
             */
            $r->addRoute('GET', '/auxilios/index', 'AuxilioController@index');
            $r->addRoute('GET', '/auxilios/buscar-cuentas', 'AuxilioController@buscarCuentas');
            $r->addRoute('GET', '/auxilios/listar', 'AuxilioController@listar');
            $r->addRoute('GET', '/auxilios/historial', 'AuxilioController@historial');
            $r->addRoute('GET', '/auxilios/cuentas-bancos', 'AuxilioController@listarBancos');
            $r->addRoute('GET', '/auxilios/estadisticas-dashboard', 'ReporteController@estadisticasDashboard');
            $r->addRoute('GET', '/auxilios/archivo/{ruta:.+}', 'AuxilioController@servirArchivo');
            
            $r->addRoute('POST', '/auxilios/verificar-renovacion', 'AuxilioController@verificarRenovacion');
            $r->addRoute('POST', '/auxilios', 'AuxilioController@store');
            $r->addRoute('GET', '/auxilios/{id}', 'AuxilioController@show');
            $r->addRoute('PUT', '/auxilios/{id}/aprobar', 'AuxilioController@aprobar');
            $r->addRoute('PUT', '/auxilios/{id}/rechazar', 'AuxilioController@rechazar');
            $r->addRoute('POST', '/auxilios/{id}/pagar', 'AuxilioController@registrarPago');
            
            /**
             * RUTAS PARA REPORTES DE AUXILIOS
             */
            $r->addRoute('GET', '/reportes/index', 'ReporteController@index');

        });
        $r->addGroup('/conta', function ($r) {
            $r->addRoute('GET', '/mayor/index', 'MayorController@index');
        });
        
        // $r->addGroup('/bancos', function ($r) {
        //     $r->addRoute('GET', '/auxilios/listar', 'AuxilioController@listar');
        // });

        // ===== RUTAS DE REPORTES =====
        $r->addGroup('/reportes', function ($r) {

            // === REPORTES DE CRÉDITOS ===
            $r->addGroup('/creditos', function ($r) {
                $r->addRoute('POST', '/visitas-prepago', 'PrepagoController@visitasPrepago');
                $r->addRoute('POST', '/ingresos-diarios', 'IngresoController@ingresosDiarios');
                $r->addRoute('POST', '/desembolsos', 'DesembolsosController@desembolsos');
                $r->addRoute('POST', '/mora', 'MoraController@mora');
                $r->addRoute('POST', '/a-vencer', 'CreditoReporteController@aVencer');
                $r->addRoute('POST', '/juridicos', 'CreditoReporteController@juridicos');
                $r->addRoute('POST', '/incobrables', 'CreditoReporteController@incobrables');
                $r->addRoute('POST', '/prepago-recuperado', 'CreditoReporteController@prepagoRecuperado');
            });

            // === REPORTES DE CONTABILIDAD ===
            $r->addGroup('/contabilidad', function ($r) {
                $r->addRoute('POST', '/libro-mayor', 'LibroMayorController@index');
            });

            // === REPORTES DE SEGUROS ===
            $r->addGroup('/seguros', function ($r) {
                $r->addRoute('POST', '/comprobante-renovacion', 'ComprobanteRenovacionController@index');
                $r->addRoute('POST', '/contrato-renovacion', 'ContratoRenovacionController@index');
                $r->addRoute('POST', '/comprobante-pago', 'ComprobantePagoController@index');
                
                // Reportes de Auxilios Póstumos
                $r->addRoute('POST', '/cuentas', '\Micro\Controllers\Seguros\ReporteController@reporteCuentas');
                $r->addRoute('POST', '/auxilios', '\Micro\Controllers\Seguros\ReporteController@reporteAuxilios');
                $r->addRoute('POST', '/pagos', '\Micro\Controllers\Seguros\ReporteController@reportePagos');
                $r->addRoute('POST', '/estadisticas', '\Micro\Controllers\Seguros\ReporteController@reporteEstadisticas');
                $r->addRoute('POST', '/personalizado', '\Micro\Controllers\Seguros\ReporteController@reportePersonalizado');
            });

            // === RUTA ÍNDICE: Lista todos los reportes disponibles ===
            $r->addRoute('GET', '', function () {
                echo json_encode([
                    'status' => 1,
                    'modulos' => [
                        'creditos' => [
                            'nombre' => 'Reportes de Créditos',
                            'endpoints' => [
                                'POST /api/reportes/creditos/visitas-prepago',
                                'POST /api/reportes/creditos/desembolsados',
                                'POST /api/reportes/creditos/a-vencer',
                                'POST /api/reportes/creditos/juridicos',
                                'POST /api/reportes/creditos/incobrables',
                                'POST /api/reportes/creditos/prepago-recuperado'
                            ]
                        ],
                        'ahorros' => [
                            'nombre' => 'Reportes de Ahorros',
                            'endpoints' => [
                                'POST /api/reportes/ahorros/cuentas-activas',
                                'POST /api/reportes/ahorros/movimientos',
                                'POST /api/reportes/ahorros/programado'
                            ]
                        ],
                        'contabilidad' => [
                            'nombre' => 'Reportes de Contabilidad',
                            'endpoints' => [
                                'POST /api/reportes/contabilidad/balance-general',
                                'POST /api/reportes/contabilidad/estado-resultados',
                                'POST /api/reportes/contabilidad/libro-diario'
                            ]
                        ]
                    ]
                ]);
            });
        });

        // ===== RUTAS DE EJEMPLO (pueden eliminarse) =====
        $r->addRoute('GET', '/health', function () {
            echo json_encode(['status' => 'ok', 'timestamp' => time()]);
        });

        // Ruta de prueba POST para testear CSRF
        $r->addRoute('POST', '/test-csrf', function () {
            echo json_encode([
                'status' => 'ok',
                'message' => 'CSRF válido - esta ruta requiere token',
                'timestamp' => time()
            ]);
        });
    });
};
