/**
 * Módulo de Reportes - Sistema de Microfinanzas
 * Maneja la generación de reportes mediante API RESTful con FastRoute
 *
 * @features
 * - Validación automática usando validarCamposGeneric de bb_shared
 * - No requiere especificar campos manualmente (usa atributo 'required' en HTML)
 * - Integración con SweetAlert2 para mensajes de error
 * - Soporte para PDF, Excel y JSON
 */

import Swal from "sweetalert2";
// NO importar desde bb_shared para evitar problemas de bundle en producción
// Se usa directamente desde window (se carga en shared bundle)
// import { validarCamposGeneric as ValidFieldsGeneric } from "./bb_shared.js";

/**
 * Cliente API para reportes
 */
class ReporteAPI {
  constructor(baseURL = "/api/reportes") {
    this.baseURL = baseURL;
  }

  /**
   * Realiza petición a la API
   */
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;

    const defaultOptions = {
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    };

    try {
      const response = await fetch(url, { ...defaultOptions, ...options });
      const data = await response.json();

      // Manejar sesión expirada
      if (data.messagecontrol === "expired") {
        await Swal.fire({
          icon: "error",
          title: "Sesión Expirada",
          text: data.mensaje,
        });
        setTimeout(() => {
          window.location.href = data.url;
        }, 2000);
        throw new Error("Sesión expirada");
      }

      return data;
    } catch (error) {
      console.error("Error en petición API:", error);
      throw error;
    }
  }

  /**
   * Obtiene lista de reportes disponibles
   */
  async listarReportes() {
    return this.request("", { method: "GET" });
  }

  /**
   * Genera cualquier reporte de forma dinámica
   * @param {string} tipoReporte - Nombre del reporte (ej: 'visitas_prepago', 'creditos/ingresos-diarios')
   * @param {object} filtros - Parámetros del reporte
   * @returns {Promise} Respuesta de la API
   * 
   * Ejemplos de uso:
   * - generarReporte('visitas-prepago', {...}) -> /api/reportes/visitas-prepago
   * - generarReporte('creditos/visitas-prepago', {...}) -> /api/reportes/creditos/visitas-prepago
   * - generarReporte('creditos_desembolsados', {...}) -> /api/reportes/creditos-desembolsados
   */
  async generarReporte(tipoReporte, filtros) {
    // Convertir underscore a guiones para URL (visitas_prepago -> visitas-prepago)
    const endpoint = '/' + tipoReporte.replace(/_/g, '-');
    
    return this.request(endpoint, {
      method: "POST",
      body: JSON.stringify(filtros),
    });
  }
}

/**
 * Gestor de Reportes - Clase principal
 */
export class ReporteManager {
  constructor() {
    this.api = new ReporteAPI();
    this.loader = null;
  }

  /**
   * Muestra/oculta el loader
   */
  toggleLoader(show) {
    if (typeof loaderefect === "function") {
      loaderefect(show ? 1 : 0);
    } else {
      // Fallback si no existe loaderefect
      const loader = document.querySelector(".loader-container");
      if (loader) {
        loader.classList.toggle("loading--show", show);
      }
    }
  }

  /**
   * Obtiene valores de inputs del formulario o contenedor
   */
  getFormData(formSelector) {
    const container = document.querySelector(formSelector);
    if (!container) {
      throw new Error(`Contenedor ${formSelector} no encontrado`);
    }

    const data = {};

    // Si es un formulario, usar FormData
    if (container.tagName === "FORM") {
      const formData = new FormData(container);
      for (let [key, value] of formData.entries()) {
        data[key] = value;
      }
    } else {
      // Si es un div u otro contenedor, extraer valores de inputs
      const inputs = container.querySelectorAll("input, select, textarea");
      inputs.forEach((input) => {
        // Usar 'name' si existe, sino usar 'id'
        const key = input.name || input.id;
        if (key) {
          if (input.type === "checkbox") {
            data[key] = input.checked;
          } else if (input.type === "radio") {
            if (input.checked) {
              data[key] = input.value;
            }
          } else {
            data[key] = input.value;
          }
        }
      });
    }

    return data;
  }

  /**
   * Valida formulario usando validarCamposGeneric de bb_shared
   */
  validateForm(formSelector) {
    const container = document.querySelector(formSelector);
    if (!container) {
      return {
        esValido: false,
        errores: [`Contenedor ${formSelector} no encontrado`],
      };
    }

    // Recolectar inputs, selects y radios con atributo required
    const inputs = [];
    const selects = [];
    const radios = new Set();

    container
      .querySelectorAll("input[required], select[required], textarea[required]")
      .forEach((element) => {
        if (element.type === "radio") {
          radios.add(element.name);
        } else if (element.tagName === "SELECT") {
          selects.push(element.id);
        } else {
          inputs.push(element.id);
        }
      });

    // Usar validarCamposGeneric de bb_shared desde window (cargada en bundle shared)
    if (typeof window.validarCamposGeneric === "function") {
      return window.validarCamposGeneric(inputs, selects, Array.from(radios));
    } else {
      console.error(
        "validarCamposGeneric no está disponible en window. Asegúrate de cargar bb_shared primero."
      );
      return {
        esValido: false,
        errores: ["Error de validación: función no disponible"],
      };
    }
  }

  /**
   * Genera reporte genérico
   */
  async generarReporte(tipoReporte, formSelector, opciones = {}) {
    try {
      this.toggleLoader(true);

      // Validar formulario usando bb_shared
      const validation = this.validateForm(formSelector);

      if (!validation.esValido) {
        this.toggleLoader(false);
        await Swal.fire({
          icon: "warning",
          title: "Validación de Campos",
          html: validation.errores.join("<br>"),
          confirmButtonText: "Entendido",
        });
        return;
      }

      // Obtener datos del formulario (ya validados)
      const formData = this.getFormData(formSelector);

      console.log("Generando reporte:", tipoReporte, formData, opciones);

      // Preparar filtros
      const filtros = {
        ...formData,
        tipo: opciones.tipo || "pdf",
      };

      // Llamar al endpoint de forma dinámica
      // El tipoReporte puede ser: 'visitas_prepago', 'creditos/visitas-prepago', etc.
      // Se convertirá automáticamente a la URL correcta
      const response = await this.api.generarReporte(tipoReporte, filtros);
      // Procesar respuesta
      if (response.status === 1) {
        await this.procesarRespuesta(response, opciones);
      } else {
        // console.error("Error al generar reporte:", response);
        this.toggleLoader(false);
        await Swal.fire({
          icon: "error",
          title: response.error || "Error",
          text: response.mensaje || response.message || "Error al generar reporte",
        });
      }
    } catch (error) {
      this.toggleLoader(false);
      //   console.error("Error al generar reporte:", error);
      await Swal.fire({
        icon: "error",
        title: "Error",
        text: "Ocurrió un error al generar el reporte",
      });
    } finally {
      this.toggleLoader(false);
    }
  }

  /**
   * Genera reporte directamente con datos (sin formulario)
   * @param {string} tipoReporte - Nombre del reporte (ej: 'seguros/comprobante-renovacion')
   * @param {object} datos - Datos para el reporte
   * @param {object} opciones - Opciones de configuración
   * @param {string} opciones.tipo - Tipo de salida: 'pdf', 'xlsx', 'json', 'show' (default: 'pdf')
   * @param {boolean} opciones.showLoader - Mostrar loader (default: true)
   * @param {function} opciones.onSuccess - Callback ejecutado al éxito
   * @param {function} opciones.onError - Callback ejecutado en error
   * 
   * @example
   * // Generar comprobante PDF
   * await reporteManager.generarReporteDirect('seguros/comprobante-renovacion', 
   *   { id: 123 }, 
   *   { tipo: 'pdf' }
   * );
   * 
   * // Generar reporte JSON con callback
   * await reporteManager.generarReporteDirect('creditos/ingresos-diarios',
   *   { fecha_inicio: '2024-01-01', fecha_fin: '2024-01-31' },
   *   { 
   *     tipo: 'json',
   *     onSuccess: (response) => console.log(response.datos)
   *   }
   * );
   */
  async generarReporteDirect(tipoReporte, datos = {}, opciones = {}) {
    const showLoader = opciones.showLoader !== false;
    
    try {
      if (showLoader) this.toggleLoader(true);

      // Preparar filtros
      const filtros = {
        ...datos,
        tipo: opciones.tipo || "pdf",
      };

      console.log("Generando reporte directo:", tipoReporte, filtros);

      // Llamar al endpoint
      const response = await this.api.generarReporte(tipoReporte, filtros);
      
      // Procesar respuesta
      if (response.status === 1) {
        await this.procesarRespuesta(response, opciones);
        
        // Ejecutar callback de éxito si existe
        if (opciones.onSuccess && typeof opciones.onSuccess === 'function') {
          opciones.onSuccess(response);
        }
        
        return response;
      } else {
        // Error controlado del servidor
        if (showLoader) this.toggleLoader(false);
        
        const errorMsg = response.mensaje || response.message || "Error al generar reporte";
        
        await Swal.fire({
          icon: "error",
          title: response.error || "Error",
          text: errorMsg,
        });
        
        // Ejecutar callback de error si existe
        if (opciones.onError && typeof opciones.onError === 'function') {
          opciones.onError(response);
        }
        
        throw new Error(errorMsg);
      }
    } catch (error) {
      if (showLoader) this.toggleLoader(false);
      
      console.error("Error al generar reporte:", error);
      
      // Solo mostrar SweetAlert si no es un error ya manejado
      if (!error.message || !error.message.includes('Error al generar')) {
        await Swal.fire({
          icon: "error",
          title: "Error",
          text: error.message || "Ocurrió un error al generar el reporte",
        });
      }
      
      // Ejecutar callback de error si existe
      if (opciones.onError && typeof opciones.onError === 'function') {
        opciones.onError(error);
      }
      
      throw error;
    } finally {
      if (showLoader) this.toggleLoader(false);
    }
  }

  /**
   * Procesa respuesta de la API
   */
  async procesarRespuesta(response, opciones) {
    const tipo = opciones.tipo || "json";

    switch (tipo) {
      case "xlsx":
      case "pdf":
        // Descargar archivo
        // console.log("Descargando archivo:", response);
        this.descargarArchivo(response.data, response.namefile, response.tipo);
        this.toggleLoader(false);
        await Swal.fire({
          icon: "success",
          title: "Reporte Generado",
          text: "El archivo se ha descargado correctamente",
        });
        break;

      case "show":
        // Mostrar en ventana nueva
        this.toggleLoader(false);
        const blob = this.base64ToBlob(response.data, "application/pdf");
        const url = URL.createObjectURL(blob);
        window.open(url, "_blank");
        break;

      case "json":
      default:
        // Mostrar datos en tabla o gráfica
        this.toggleLoader(false);
        
        if (opciones.onSuccess && typeof opciones.onSuccess === "function") {
          // Callback personalizado - pasar respuesta completa
          opciones.onSuccess(response);
        } else if (opciones.mostrarTabla || opciones.mostrarGrafica) {
          // Modo automático: mostrar tabla y/o gráfica
          const datos = response.datos || response.data || [];
          
          if (datos && datos.length > 0) {
            // Mostrar tabla si está configurado
            if (opciones.mostrarTabla && opciones.configTabla) {
              this.actualizarTabla(
                datos,
                opciones.configTabla.encabezados,
                opciones.configTabla.keys,
                opciones.configTabla.selector || "#tbdatashow"
              );
            }

            // Mostrar gráfica si está configurado
            if (opciones.mostrarGrafica && opciones.configGrafica) {
              // Determinar qué datos usar para la gráfica
              let datosGrafica = datos;
              
              // Si hay datos procesados específicos para gráfica, usarlos
              if (opciones.configGrafica.dataKey && response[opciones.configGrafica.dataKey]) {
                datosGrafica = response[opciones.configGrafica.dataKey];
              }
              
              // Usar método mejorado de gráfica si está configurado
              if (opciones.configGrafica.datasets) {
                this.crearGraficaAvanzada(datosGrafica, opciones.configGrafica);
              } else {
                // Método legacy
                this.actualizarGrafica(
                  datosGrafica,
                  opciones.configGrafica.titulo,
                  opciones.configGrafica.topDown || 1,
                  opciones.configGrafica.selector || "#myChart"
                );
              }
            }

            await Swal.fire({
              icon: "success",
              title: "Reporte Generado",
              text: `Se procesaron ${datos.length} registros`,
              timer: 2000,
              showConfirmButton: false
            });
          } else {
            await Swal.fire({
              icon: "info",
              title: "Sin Datos",
              text: "No se encontraron registros para los filtros seleccionados",
            });
          }
        } else {
          // Modo simple: solo mostrar mensaje
          await Swal.fire({
            icon: "success",
            title: "Reporte Generado",
            text: `Se obtuvieron ${response.datos?.length || 0} registros`,
          });
        }
        break;
    }
  }

  /**
   * Descarga archivo desde base64
   */
  descargarArchivo(base64Data, nombreArchivo, tipo) {
    const mimeTypes = {
      xlsx: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      pdf: "application/pdf",
    };

    const blob = this.base64ToBlob(base64Data, mimeTypes[tipo]);
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = nombreArchivo;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  /**
   * Convierte base64 a Blob
   */
  base64ToBlob(base64, contentType = "") {
    // Extraer solo la parte base64 si viene con data URI prefix
    let base64Data = base64;
    if (base64.includes(",")) {
      base64Data = base64.split(",")[1];
    }

    const byteCharacters = atob(base64Data);
    const byteArrays = [];

    for (let offset = 0; offset < byteCharacters.length; offset += 512) {
      const slice = byteCharacters.slice(offset, offset + 512);
      const byteNumbers = new Array(slice.length);

      for (let i = 0; i < slice.length; i++) {
        byteNumbers[i] = slice.charCodeAt(i);
      }

      const byteArray = new Uint8Array(byteNumbers);
      byteArrays.push(byteArray);
    }

    return new Blob(byteArrays, { type: contentType });
  }

  /**
   * Actualiza tabla con datos del reporte
   */
  actualizarTabla(datos, encabezados, keys, tableSelector = "#tbdatashow") {
    const table = $(tableSelector);

    if (!table.length) {
      // console.error(`Tabla ${tableSelector} no encontrada`);
      return;
    }

    // Mostrar contenedor
    $("#divshow").show();

    // Encabezados
    table.find("thead").empty();
    const tr = $("<tr></tr>");
    encabezados.forEach((header) => {
      tr.append(`<th class="text-center">${header}</th>`);
    });
    table.find("thead").append(tr);

    // Datos
    let dataTable = table.DataTable();

    if (dataTable) {
      dataTable.destroy();
    }

    dataTable = table.DataTable({
      data: datos.map((obj) => keys.map((key) => obj[key])),
      columns: keys.map(() => ({ className: "text-center" }))
    });
  }

  /**
   * Actualiza gráfica con datos del reporte
   */
  actualizarGrafica(
    datos,
    labelTitle,
    topDown = 1,
    chartSelector = "#myChart"
  ) {
    $("#divshowchart").show();

    const top = topDown == 1 ? datos.slice(0, 30) : datos.slice(-30);

    const labels = top.map((item) => item.fecha);
    const values = top.map((item) => item.cantidad);

    const ctx = document.querySelector(chartSelector);

    if (!ctx) {
      // console.error(`Canvas ${chartSelector} no encontrado`);
      return;
    }

    // Destruir instancia anterior
    if (window.myChart) {
      window.myChart.destroy();
    }

    window.myChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: labelTitle,
            data: values,
            backgroundColor: "#1E90FF",
            borderColor: "#87CEEB",
            borderWidth: 4,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: true,
          },
          datalabels: {
            anchor: "end",
            align: "top",
            formatter: Math.round,
            font: { size: 12 },
          },
        },
        scales: {
          y: {
            beginAtZero: true,
          },
        },
      },
    });
  }

  /**
   * Crea una gráfica avanzada con configuración personalizada
   * @param {Array} datos - Datos para la gráfica
   * @param {Object} config - Configuración de la gráfica
   * @param {string} config.titulo - Título de la gráfica
   * @param {string} config.type - Tipo de gráfica (bar, line, pie, etc.)
   * @param {string} config.labels - Key para las etiquetas del eje X
   * @param {Array<Object>} config.datasets - Configuración de datasets
   * @param {string} config.datasets[].label - Etiqueta del dataset
   * @param {string} config.datasets[].key - Key de los datos
   * @param {string} config.datasets[].color - Color del dataset
   * @param {string} config.selector - Selector del canvas
   */
  crearGraficaAvanzada(datos, config) {
    const canvas = document.querySelector(config.selector || "#myChart");
    if (!canvas) {
      console.warn("Canvas no encontrado:", config.selector);
      return;
    }

    // Destruir gráfica anterior si existe
    if (this.chart) {
      this.chart.destroy();
    }

    $("#divshowchart").show();

    const ctx = canvas.getContext("2d");

    // Extraer labels
    const labels = datos.map(item => item[config.labels]);

    // Preparar datasets
    const datasets = config.datasets.map(ds => ({
      label: ds.label,
      data: datos.map(item => item[ds.key]),
      backgroundColor: ds.color,
      borderColor: ds.color,
      borderWidth: 1
    }));

    // Crear gráfica
    this.chart = new Chart(ctx, {
      type: config.type || 'bar',
      data: {
        labels: labels,
        datasets: datasets
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top',
          },
          title: {
            display: true,
            text: config.titulo || 'Gráfica'
          }
        },
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });
  }
}

/**
 * Funciones auxiliares para compatibilidad con código legacy
 */

// Instancia global - Crear una vez
const reporteManager = new ReporteManager();

/**
 * Exportar por defecto
 */
export default reporteManager;

/**
 * Exponer en window para compatibilidad con código legacy
 */
if (typeof window !== "undefined") {
  window.reporteManager = reporteManager;
}
