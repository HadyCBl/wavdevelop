/**
 * Cliente API para Reportes de Créditos
 */
export class CreditoReporteAPI {
    constructor(baseURL = '/api/reportes/creditos') {
        this.baseURL = baseURL;
    }

    async visitasPrepago(filtros) {
        return this.request('/visitas-prepago', filtros);
    }

    async desembolsados(filtros) {
        return this.request('/desembolsados', filtros);
    }

    async aVencer(filtros) {
        return this.request('/a-vencer', filtros);
    }

    async juridicos(filtros) {
        return this.request('/juridicos', filtros);
    }

    async incobrables(filtros) {
        return this.request('/incobrables', filtros);
    }

    async prepagoRecuperado(filtros) {
        return this.request('/prepago-recuperado', filtros);
    }

    async request(endpoint, data) {
        const response = await fetch(this.baseURL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });

        return response.json();
    }
}

/**
 * Cliente API para Reportes de Ahorros
 */
export class AhorroReporteAPI {
    constructor(baseURL = '/api/reportes/ahorros') {
        this.baseURL = baseURL;
    }

    async cuentasActivas(filtros) {
        return this.request('/cuentas-activas', filtros);
    }

    async movimientos(filtros) {
        return this.request('/movimientos', filtros);
    }

    async programado(filtros) {
        return this.request('/programado', filtros);
    }

    async request(endpoint, data) {
        const response = await fetch(this.baseURL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });

        return response.json();
    }
}

/**
 * Cliente API para Reportes de Contabilidad
 */
export class ContabilidadReporteAPI {
    constructor(baseURL = '/api/reportes/contabilidad') {
        this.baseURL = baseURL;
    }

    async balanceGeneral(filtros) {
        return this.request('/balance-general', filtros);
    }

    async estadoResultados(filtros) {
        return this.request('/estado-resultados', filtros);
    }

    async libroDiario(filtros) {
        return this.request('/libro-diario', filtros);
    }

    async request(endpoint, data) {
        const response = await fetch(this.baseURL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });

        return response.json();
    }
}

/**
 * Instancias globales
 */
export const creditoAPI = new CreditoReporteAPI();
export const ahorroAPI = new AhorroReporteAPI();
export const contabilidadAPI = new ContabilidadReporteAPI();

/**
 * Exponer en window para compatibilidad
 */
if (typeof window !== 'undefined') {
    window.creditoAPI = creditoAPI;
    window.ahorroAPI = ahorroAPI;
    window.contabilidadAPI = contabilidadAPI;
}
