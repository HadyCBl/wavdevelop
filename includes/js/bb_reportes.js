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
      // console.error("Error en petición API:", error);
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
   * Genera reporte de visitas prepago
   */
  async visitasPrepago(filtros) {
    return this.request("/creditos/visitas-prepago", {
      method: "POST",
      body: JSON.stringify(filtros),
    });
  }

  /**
   * Genera reporte de créditos desembolsados
   */
  async creditosDesembolsados(filtros) {
    return this.request("/creditos-desembolsados", {
      method: "POST",
      body: JSON.stringify(filtros),
    });
  }

  /**
   * Genera reporte de créditos a vencer
   */
  async creditosVencer(filtros) {
    return this.request("/creditos-vencer", {
      method: "POST",
      body: JSON.stringify(filtros),
    });
  }

  /**
   * Genera reporte de prepago recuperado
   */
  async prepagoRecuperado(filtros) {
    return this.request("/prepago-recuperado", {
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
    if (typeof window.validarCamposGeneric === 'function') {
      return window.validarCamposGeneric(inputs, selects, Array.from(radios));
    } else {
      console.error('validarCamposGeneric no está disponible en window. Asegúrate de cargar bb_shared primero.');
      return {
        esValido: false,
        errores: ['Error de validación: función no disponible'],
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

      // Preparar filtros
    //   const filtros = { ...formData, ...opciones.tipo };
      const filtros = {
        ...formData,
        tipo: opciones.tipo || "pdf",
      };
      // console.log("Filtros preparados:", filtros);

      // Llamar al endpoint correspondiente
      let response;
      switch (tipoReporte) {
        case "visitas_prepago":
          response = await this.api.visitasPrepago(filtros);
          break;
        case "creditos_desembolsados":
          response = await this.api.creditosDesembolsados(filtros);
          break;
        case "creditos_vencer":
          response = await this.api.creditosVencer(filtros);
          break;
        case "prepago_recuperado":
          response = await this.api.prepagoRecuperado(filtros);
          break;
        default:
          throw new Error("Tipo de reporte no válido");
      }

      // console.log("Respuesta de la API:", response);
      // Procesar respuesta
      if (response.status === 1) {
        await this.procesarRespuesta(response, opciones);
      } else {
        this.toggleLoader(false);
        await Swal.fire({
          icon: "error",
          title: "Error",
          text: response.mensaje || "Error al generar reporte",
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
          opciones.onSuccess(response.datos);
        } else {
          await Swal.fire({
            icon: "success",
            title: "Reporte Generado",
            text: `Se obtuvieron ${response.datos.length} registros`,
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
      columns: keys.map(() => ({ className: "text-center" })),
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json",
      },
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
}

/**
 * Funciones auxiliares para compatibilidad con código legacy
 */

// Instancia global - Crear una vez
const reporteManager = new ReporteManager();

/**
 * Genera reporte de visitas prepago (compatible con código anterior)
 */
export function generarReporteVisitasPrepago(opciones = {}) {
  return reporteManager.generarReporte(
    "visitas_prepago",
    "#formReport",
    opciones
  );
}

/**
 * Genera reporte de créditos desembolsados
 */
export function generarReporteCreditosDesembolsados(opciones = {}) {
  return reporteManager.generarReporte(
    "creditos_desembolsados",
    "#formReport",
    opciones
  );
}

/**
 * Genera reporte de créditos a vencer
 */
export function generarReporteCreditosVencer(opciones = {}) {
  return reporteManager.generarReporte(
    "creditos_vencer",
    "#formReport",
    opciones
  );
}

/**
 * Exportar por defecto
 */
export default reporteManager;

/**
 * Exponer en window para compatibilidad con código legacy
 */
if (typeof window !== "undefined") {
  window.reporteManager = reporteManager;
  window.generarReporteVisitasPrepago = generarReporteVisitasPrepago;
  window.generarReporteCreditosDesembolsados =
    generarReporteCreditosDesembolsados;
  window.generarReporteCreditosVencer = generarReporteCreditosVencer;
}
