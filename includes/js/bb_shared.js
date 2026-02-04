import language from "datatables.net-plugins/i18n/es-ES.mjs";
import Alpine from "alpinejs";

// Importa persist si lo necesitas en este módulo
// import persist from '@alpinejs/persist'
// Alpine.plugin(persist)

window.Alpine = Alpine;
Alpine.start();
export default Alpine;

/**
 * VALIDACION DE CAMPOS GENERICOS
 */
export function validarCamposGeneric(inputs, selects, radios) {
  let errores = [];

  // Función helper para agregar mensaje de error
  function agregarMensajeError(elemento, mensaje) {
    // Eliminar mensaje anterior si existe
    const feedbackAnterior = elemento.nextElementSibling;
    if (
      feedbackAnterior &&
      feedbackAnterior.classList.contains("invalid-feedback")
    ) {
      feedbackAnterior.remove();
    }

    // Crear y agregar nuevo mensaje
    const feedbackDiv = document.createElement("div");
    feedbackDiv.className = "invalid-feedback";
    feedbackDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${mensaje}`;

    // Agregar clases de error al elemento
    elemento.classList.add("is-invalid");
    elemento.classList.remove("is-valid");

    // Insertar mensaje después del elemento
    elemento.parentNode.insertBefore(feedbackDiv, elemento.nextSibling);

    return mensaje;
  }

  // Función helper para limpiar error
  function limpiarError(elemento) {
    // Remover clases de error
    elemento.classList.remove("is-invalid");
    elemento.classList.add("is-valid");

    // Eliminar mensaje de error si existe
    const feedbackDiv = elemento.nextElementSibling;
    if (feedbackDiv && feedbackDiv.classList.contains("invalid-feedback")) {
      feedbackDiv.remove();
    }
  }

  // Validar inputs
  inputs.forEach((input) => {
    const elemento = document.getElementById(input);
    if (elemento) {
      // Verifica si el campo es requerido
      if (elemento.hasAttribute("required")) {
        if (!elemento.value.trim()) {
          errores.push(
            agregarMensajeError(
              elemento,
              `El campo ${elemento.getAttribute("data-label") || input} es obligatorio`
            )
          );
        } else {
          limpiarError(elemento);
        }
      }

      // Validar tipo email
      if (elemento.type === "email" && elemento.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(elemento.value)) {
          errores.push(
            agregarMensajeError(elemento, `Ingrese un email válido`)
          );
        }
      }

      // Validar números
      if (elemento.type === "number" && elemento.value) {
        const min = elemento.getAttribute("min");
        const max = elemento.getAttribute("max");
        const valor = parseFloat(elemento.value);

        if (min && valor < parseFloat(min)) {
          errores.push(
            agregarMensajeError(elemento, `El valor mínimo es ${min}`)
          );
        }
        if (max && valor > parseFloat(max)) {
          errores.push(
            agregarMensajeError(elemento, `El valor máximo es ${max}`)
          );
        }
      }
    }
  });

  // Validar selects
  selects.forEach((select) => {
    const elemento = document.getElementById(select);
    if (elemento && elemento.hasAttribute("required")) {
      if (elemento.value === "0" || elemento.value === "") {
        errores.push(agregarMensajeError(elemento, `Seleccione una opción`));
      } else {
        limpiarError(elemento);
      }
    }
  });

  // Validar radios
  radios.forEach((radio) => {
    const elementos = document.getElementsByName(radio);
    if (elementos.length > 0 && elementos[0].hasAttribute("required")) {
      let checked = false;
      elementos.forEach((el) => {
        if (el.checked) checked = true;
      });
      if (!checked) {
        // Para radios, agregamos el mensaje después del último radio
        errores.push(
          agregarMensajeError(
            elementos[elementos.length - 1],
            `Seleccione una opción`
          )
        );
      } else {
        elementos.forEach((el) => limpiarError(el));
      }
    }
  });

  return {
    esValido: errores.length === 0,
    errores: errores,
  };
}

/**
 * Inicializa la validación automática para todos los campos de un formulario
 * @param {string} formSelector - Selector del formulario (ej: '#formPartidaDiario')
 */
export function inicializarValidacionAutomaticaGeneric(formSelector) {
  // console.log("Inicializando validación automática para", formSelector);
  const form = document.querySelector(formSelector);
  if (!form) return;

  // También capturar eventos keyup y blur para inputs de texto
  form.addEventListener(
    "keyup",
    (e) => {
      if (e.target.matches('input:not([type="radio"]), textarea')) {
        validarCamposGeneric([e.target.id], [], []);
      }
    },
    true
  );

  // Event delegation para mejor performance
  form.addEventListener("change", (e) => {
    if (e.target.matches('input:not([type="radio"]), textarea')) {
      validarCamposGeneric([e.target.id], [], []);
    }
    if (e.target.matches("select")) {
      validarCamposGeneric([], [e.target.id], []);
    }
    if (e.target.matches('input[type="radio"]')) {
      validarCamposGeneric([], [], [e.target.name]);
    }
  });
}

export function showHideElement(elementIds, action, option = 1) {
  if (!Array.isArray(elementIds)) {
    elementIds = [elementIds];
  }
  elementIds.forEach((elementId) => {
    const element = document.getElementById(elementId);
    if (element) {
      if (action === "show") {
        if (option === 1) {
          element.style.display = "block";
        } else if (option === 2) {
          element.removeAttribute("hidden");
        } else if (option === 3) {
          element.classList.remove("d-none");
        }
      } else if (action === "hide") {
        if (option === 1) {
          element.style.display = "none";
        } else if (option === 2) {
          element.setAttribute("hidden", "");
        } else if (option === 3) {
          element.classList.add("d-none");
        }
      }
    } else {
      console.warn(`Element with ID ${elementId} not found.`);
    }
  });
}

/**
 * Convierte una tabla HTML en DataTable con configuración personalizable
 * @param {string} id_tabla - ID de la tabla a convertir (sin el #)
 * @param {Object} opciones - Opciones de configuración (opcional)
 * @param {Array} opciones.lengthMenu - Opciones de cantidad de registros por página
 * @param {boolean} opciones.mostrarBuscador - Mostrar o ocultar el buscador (default: true)
 * @param {boolean} opciones.mostrarInfo - Mostrar información de registros (default: true)
 * @param {boolean} opciones.mostrarPaginacion - Mostrar paginación (default: true)
 * @param {number} opciones.pageLength - Cantidad de registros inicial (default: 10)
 * @param {Object} opciones.language - Personalización de textos (se mezcla con los defaults)
 * @param {Object} opciones.datatableOptions - Opciones adicionales de DataTable
 * @returns {Object} Instancia de DataTable o null si hay error
 */
export function convert_table_to_datatable(id_tabla, opciones = {}) {
  // console.log("convertir_tabla_a_datatable - Tabla:", id_tabla);

  try {
    // Configuración por defecto
    const defaultOptions = {
      lengthMenu: [
        [5, 10, 15, -1],
        ["5 filas", "10 filas", "15 filas", "Mostrar todos"],
      ],
      mostrarBuscador: true,
      mostrarInfo: true,
      mostrarPaginacion: true,
      pageLength: 10,
      language: {
        lengthMenu: "Mostrar _MENU_ registros",
        zeroRecords: "No se encontraron registros",
        info: " ",
        infoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
        infoFiltered: "(filtrado de un total de: _MAX_ registros)",
        sSearch: "Buscar: ",
        oPaginate: {
          sFirst: "Primero",
          sLast: "Último",
          sNext: "Siguiente",
          sPrevious: "Anterior",
        },
        sProcessing: "Procesando...",
      },
      datatableOptions: {},
    };

    // Mezclar opciones con valores por defecto
    const config = {
      ...defaultOptions,
      ...opciones,
      language: {
        ...defaultOptions.language,
        ...(opciones.language || {}),
      },
    };

    // Construir configuración de DataTable
    const datatableConfig = {
      lengthMenu: config.lengthMenu,
      pageLength: config.pageLength,
      language: config.language,
      searching: config.mostrarBuscador,
      info: config.mostrarInfo,
      paging: config.mostrarPaginacion,
      ...config.datatableOptions,
    };

    // Inicializar DataTable
    const table = $("#" + id_tabla).DataTable(datatableConfig);
    return table;
  } catch (error) {
    console.error("Error al inicializar DataTable:", error);
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "Ocurrió un error al cargar la tabla. Intente de nuevo.",
    });
    return null;
  }
}

/**
 * Inicializa un DataTable con procesamiento server-side
 * @param {string} tableSelector - Selector del elemento tabla (ej: '#clientesTable')
 * @param {string} ajaxUrl - URL del endpoint PHP (ej: '../../app/server_side/clientes_all.php')
 * @param {array} columns - Configuración de columnas DataTable
 * @param {object} options - Opciones adicionales (opcional)
 * @returns {DataTable} - Instancia de DataTable
 */
function initServerSideDataTable(
  tableSelector,
  ajaxUrl,
  columns,
  options = {}
) {
  const defaultOptions = {
    processing: true,
    serverSide: true,
    ajax: {
      url: "../../app/server_side/" + ajaxUrl + ".php",
      type: options.ajaxMethod || "GET",
      error: function (xhr, error, thrown) {
        // console.error("Error al cargar datos:", error);
        // console.error("Respuesta:", xhr.responseText);

        if (options.onError && typeof options.onError === "function") {
          options.onError(xhr, error, thrown);
        }
      },
    },
    columns: columns,
    pageLength: options.pageLength || 10,
    lengthMenu: options.lengthMenu || [
      [5, 10, 25, 50, 100],
      [5, 10, 25, 50, 100],
    ],
    ordering: options.ordering !== undefined ? options.ordering : true,
    searching: options.searching !== undefined ? options.searching : true,
    language: language,
    dom:
      options.dom ||
      '<"flex flex-col sm:flex-row justify-between items-center mb-4 gap-2"lf>rt<"flex flex-col sm:flex-row justify-between items-center mt-4 gap-2"ip>',
    order: options.order || [[0, "desc"]],
    headerCallback: function (thead, data, start, end, display) {
      // Clases por defecto para todos los headers
      const defaultHeaderClass =
        "bg-base-300 text-base-content font-bold text-sm py-3 px-4 border-b-2 border-primary";

      $(thead)
        .find("th")
        .each(function (index) {
          // Aplicar clase por defecto primero
          $(this).addClass(defaultHeaderClass);

          // Si hay clases personalizadas, las agrega también (o las sobrescribe)
          if (options.headerClasses && Array.isArray(options.headerClasses)) {
            if (options.headerClasses[index]) {
              // Remover la clase por defecto si se proporciona una personalizada
              $(this).removeClass(defaultHeaderClass);
              $(this).addClass(options.headerClasses[index]);
            }
          }
        });

      if (
        options.onHeaderCallback &&
        typeof options.onHeaderCallback === "function"
      ) {
        options.onHeaderCallback(thead, data, start, end, display);
      }
    },

    // NUEVO: Callback para filas
    rowCallback: function (row, data, index) {
      if (options.rowClasses && Array.isArray(options.rowClasses)) {
        $(row)
          .find("td")
          .each(function (colIndex) {
            if (options.rowClasses[colIndex]) {
              $(this).addClass(options.rowClasses[colIndex]);
            }
          });
      }

      if (
        options.onRowCallback &&
        typeof options.onRowCallback === "function"
      ) {
        options.onRowCallback(row, data, index);
      }
    },
    drawCallback: function (settings) {
      if (
        options.onDrawCallback &&
        typeof options.onDrawCallback === "function"
      ) {
        options.onDrawCallback(settings);
      }
    },
  };

  // Merge con opciones personalizadas
  const finalOptions = { ...defaultOptions, ...options };

  // Crear DataTable
  const table = $(tableSelector).DataTable(finalOptions);

  // Log de inicialización
  // console.log("DataTable inicializado:", tableSelector);

  return table;
}

/**
 * esta parte solo aplica si uso las funciones en los archivos php
 */
if (typeof window !== "undefined") {
  window.validarCamposGeneric = validarCamposGeneric;
  window.inicializarValidacionAutomaticaGeneric =
    inicializarValidacionAutomaticaGeneric;
  window.showHideElement = showHideElement;
  window.convert_table_to_datatable = convert_table_to_datatable;
  window.initServerSideDataTable = initServerSideDataTable;
}
