import "../css/index.css";

import Alpine from "alpinejs";
import persist from "@alpinejs/persist";

import $ from "jquery";
import DataTable from "datatables.net-dt";
import language from "datatables.net-plugins/i18n/es-ES.mjs";

import TomSelect from "tom-select";
import select2 from "select2";
import { formatNumeral, unformatNumeral } from "cleave-zen";

// Importar Ably Notification Helper
import * as Ably from "ably";
import AblyNotificationHelper, {
  NotificationHelper,
} from "./AblyNotificationHelper.js";

// Inicializar Select2 con jQuery
select2($);

Alpine.plugin(persist);
window.Alpine = Alpine;
Alpine.start();

function initCleaveZen() {
  const inputs = document.querySelectorAll(".decimal-cleave-zen");
  console.log("Inicializando Cleave-Zen en", inputs.length, "inputs");

  inputs.forEach((input) => {
    if (input.dataset.cleaveInitialized) return;

    // Leer opciones personalizadas desde data-attributes
    const decimals = input.dataset.decimals || 2;
    const prefix = input.dataset.prefix || "";

    input.addEventListener("input", (e) => {
      e.target.value = formatNumeral(e.target.value, {
        numeralThousandsGroupStyle: "thousand",
        numeralDecimalScale: parseInt(decimals),
        prefix: prefix,
      });
    });

    input.dataset.cleaveInitialized = "true";

    if (input.value) {
      input.value = formatNumeral(input.value, {
        numeralThousandsGroupStyle: "thousand",
        numeralDecimalScale: parseInt(decimals),
        prefix: prefix,
      });
    }
  });
}

document.addEventListener("DOMContentLoaded", function () {
  // viewLoader.init();
  const searchInput = document.getElementById("search-input");
  const searchButton = document.getElementById("search-button");

  // Function to focus the search input
  function focusSearchInput() {
    searchInput.focus();
  }

  // Add click event listener to the search button
  searchButton.addEventListener("click", focusSearchInput);

  // Add keyboard event listener for Cmd+K (Mac) or Ctrl+K (Windows/Linux)
  document.addEventListener("keydown", function (event) {
    if ((event.metaKey || event.ctrlKey) && event.key === "k") {
      event.preventDefault(); // Prevent the default browser behavior
      focusSearchInput();
    }
  });

  // Add keyboard event listener for "/" key
  document.addEventListener("keydown", function (event) {
    if (event.key === "/" && document.activeElement !== searchInput) {
      event.preventDefault(); // Prevent the "/" character from being typed
      focusSearchInput();
    }
  });

  loaderefect(0);
  (function () {
    let devtoolsOpen = false;

    const threshold = 160; // Un valor que puede ajustarse según la pantalla
    const checkDevTools = function () {
      if (
        window.outerWidth - window.innerWidth > threshold ||
        window.outerHeight - window.innerHeight > threshold
      ) {
        if (!devtoolsOpen) {
          devtoolsOpen = true;
          console.log(
            "%c Advertencia: No utilices esta consola a menos que sepas exactamente lo que estás haciendo. Puedes estar expuesto a riesgos de seguridad. TE LO DIJO CHEMA ALONSO",
            "color: red; font-size: 20px;"
          );
        }
      } else {
        devtoolsOpen = false;
      }
    };

    window.addEventListener("resize", checkDevTools);
  })();
});

/**
 * FUNCIONES GENERICAS
 */

function loaderefect(sh) {
  const LOADING = document.querySelector(".loader-container");
  switch (sh) {
    case 1:
      LOADING.classList.remove("loading--hide");
      LOADING.classList.add("loading--show");
      break;
    case 0:
      LOADING.classList.add("loading--hide");
      LOADING.classList.remove("loading--show");
      break;
  }
}

/**
 * Normaliza una URL eliminando slashes duplicados
 * @param {string} baseUrl - URL base (ej: 'http://localhost:8080/' o 'http://localhost:8080')
 * @param {string} path - Path a concatenar (ej: '/api/path' o 'api/path')
 * @returns {string} URL normalizada sin dobles slashes
 */
function normalizeUrl(baseUrl, path) {
  // Eliminar slash final de baseUrl si existe
  const base = baseUrl.replace(/\/+$/, "");
  // Asegurar que path empiece con slash
  const normalizedPath = path.startsWith("/") ? path : "/" + path;
  // Concatenar y reemplazar cualquier doble slash (excepto en el protocolo http://)
  return (base + normalizedPath).replace(/([^:])\/\/+/g, "$1/");
}

function printdiv(condi, idiv, dir, xtra) {
  // console.log(xtra);
  loaderefect(1);
  let dire = "views/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      loaderefect(0);
      $(idiv).html(data);
      initCleaveZen();
    },
    error: function (xhr) {
      loaderefect(0);
      const data2 = JSON.parse(xhr.responseText);
      if ("messagecontrol" in data2) {
        Swal.fire({
          icon: "error",
          title: "¡ERROR!",
          text: "Información de error: " + data2.mensaje,
        }).then(() => {});
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        console.log(xhr);
      }
    },
  });
}
function condimodal() {
  var condi = document.getElementById("condi").value;
  return condi;
}
function filenow() {
  var file = document.getElementById("file").value;
  return file;
}
function printdiv2(idiv, xtra) {
  loaderefect(1);
  let condi = condimodal();
  let dir = filenow();
  let dire = dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      loaderefect(0);
      $(idiv).html(data);
      initCleaveZen();
    },
    error: function (xhr) {
      loaderefect(0);
      const data2 = JSON.parse(xhr.responseText);
      if ("messagecontrol" in data2) {
        Swal.fire({
          icon: "error",
          title: "¡ERROR!",
          text: "Información de error: " + data2.mensaje,
        }).then(() => {});
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        console.log(xhr);
      }
    },
  });
}

/**
 * NUEVA FUNCIÓN PARA MIGRACIÓN A CONTROLADORES CON FASTROUTE
 * Carga vistas usando el nuevo sistema de rutas o fallback a legacy
 *
 * @param {string} idiv - Selector del contenedor donde se imprimirá la vista
 * @param {object} options - Opciones de configuración
 * @param {string} options.route - Ruta de la API (ej: '/api/vistas/creditos/lista')
 * @param {string} options.method - Método HTTP (default: 'POST')
 * @param {string} options.legacyDir - Ruta legacy para fallback (ej: 'views/creditos/lista')
 * @param {string} options.condi - Condición o acción a ejecutar
 * @param {object} options.data - Datos adicionales a enviar
 * @param {boolean} options.useLegacy - Forzar uso del sistema legacy (default: false)
 * @param {string} options.csrfToken - Token CSRF a enviar (opcional, se envía en header X-CSRF-TOKEN)
 * @param {function} options.onSuccess - Callback ejecutado al cargar exitosamente
 * @param {function} options.onError - Callback ejecutado en caso de error
 */
function loadModuleView(idiv, options = {}) {
  const {
    route = null,
    method = "GET",
    legacyDir = null,
    condi = "",
    data = {},
    useLegacy = false,
    csrfToken = null,
    onSuccess = null,
    onError = null,
  } = options;

  // Si se fuerza legacy o no hay ruta, usar printdiv tradicional
  if (useLegacy || !route) {
    if (!legacyDir) {
      console.error(
        "loadModuleView: Se requiere legacyDir cuando useLegacy=true o no hay route"
      );
      return;
    }
    printdiv(condi, idiv, legacyDir, data);
    return;
  }

  // Usar el nuevo sistema de rutas con FastRoute
  loaderefect(1);

  const requestData = {
    condi: condi,
    ...data,
  };

  // Configurar headers para CSRF (si se proporciona token)
  const headers = {};
  if (csrfToken) {
    headers["X-CSRF-TOKEN"] = csrfToken;
  } else {
    // Intentar obtener token CSRF de un input hidden en el DOM
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput && csrfInput.value) {
      headers["X-CSRF-TOKEN"] = csrfInput.value;
    }
  }

  $.ajax({
    url: normalizeUrl(BASE_URL_FOR_JS, route),
    method: method,
    data: requestData,
    headers: headers,
    success: function (response) {
      loaderefect(0);
      // console.log('Respuesta recibida en loadModuleView:', response);
      try {
        // Si la respuesta es JSON, extraer el HTML
        const parsedResponse =
          typeof response === "string" ? JSON.parse(response) : response;

        if (parsedResponse.status === 1) {
          // Respuesta exitosa del controlador
          const htmlContent = parsedResponse.html || parsedResponse.data || "";
          $(idiv).html(htmlContent);
          initCleaveZen();

          // Ejecutar callback de éxito si existe
          if (onSuccess && typeof onSuccess === "function") {
            onSuccess(parsedResponse);
          }
        } else {
          // Error controlado desde el controlador
          throw new Error(parsedResponse.message || "Error al cargar la vista");
        }
      } catch (e) {
        // Si no es JSON, asumir que es HTML directo
        $(idiv).html(response);
        initCleaveZen();

        if (onSuccess && typeof onSuccess === "function") {
          onSuccess({ html: response });
        }
      }
    },
    error: function (xhr) {
      loaderefect(0);

      // Intentar parsear el error
      let errorMessage = "Error al cargar la vista";
      try {
        const errorData = JSON.parse(xhr.responseText);
        errorMessage = errorData.message || errorData.error || errorMessage;

        if (errorData.messagecontrol) {
          Swal.fire({
            icon: "error",
            title: "Error",
            html: errorData.messagecontrol,
            confirmButtonText: "Aceptar",
          });
        }
      } catch (e) {
        // Error no es JSON
        console.error("Error en loadModuleView:", xhr.responseText);
      }

      // Si hay legacyDir, intentar fallback
      if (legacyDir) {
        console.warn("loadModuleView: Fallback a sistema legacy");
        printdiv(condi, idiv, legacyDir, data);
      } else {
        // Mostrar error genérico
        Swal.fire({
          icon: "error",
          title: "Error",
          text: errorMessage,
          confirmButtonText: "Aceptar",
        });
      }

      // Ejecutar callback de error si existe
      if (onError && typeof onError === "function") {
        onError(xhr);
      }
    },
  });
}

/**
 * VERSIÓN SIMPLIFICADA - Detecta automáticamente si usar nuevo sistema o legacy
 * basándose en un atributo data en el elemento HTML
 *
 * @param {string} idiv - Selector del contenedor
 * @param {string} condi - Condición o acción
 * @param {string} module - Nombre del módulo
 * @param {string} view - Nombre de la vista
 * @param {object} extraData - Datos adicionales
 */
function loadView(idiv, condi, module, view, extraData = {}) {
  // Verificar si el módulo está migrado consultando un registry
  const migratedModules = window.MIGRATED_MODULES || {};

  if (migratedModules[module] && migratedModules[module][view]) {
    // Usar nuevo sistema
    const route = migratedModules[module][view].route;
    const legacyDir = migratedModules[module][view].legacy;

    loadModuleView(idiv, {
      route: route,
      legacyDir: legacyDir,
      condi: condi,
      data: extraData,
    });
  } else {
    // Usar sistema legacy
    const legacyPath = `${module}/${view}`;
    printdiv(condi, idiv, legacyPath, extraData);
  }
}

function getinputsval(datos) {
  const inputs2 = {};
  for (let i = 0; i < datos.length; i++) {
    const id = datos[i];
    const el = document.getElementById(id);
    if (!el) {
      inputs2[id] = "";
      continue;
    }

    if (el.classList.contains("decimal-cleave-zen")) {
      try {
        // unformatNumeral puede devolver número o string según implementación
        inputs2[id] = String(unformatNumeral(el.value));
      } catch (e) {
        // fallback al valor crudo si ocurre un error
        inputs2[id] = el.value;
      }
    } else {
      inputs2[id] = el.value;
    }
  }
  return inputs2;
}

function getselectsval(datos) {
  const selects2 = {};
  var i = 0;
  while (i < datos.length) {
    var e = document.getElementById(datos[i]);
    selects2[datos[i]] = e.options[e.selectedIndex].value;
    i++;
  }
  return selects2;
}

function getradiosval(datos) {
  const radios2 = {};
  var i = 0;
  while (i < datos.length) {
    radios2[datos[i]] = document.querySelector(
      'input[name="' + datos[i] + '"]:checked'
    ).value;
    i++;
  }
  return radios2;
}

/**
 * FUNCIÓN MODERNA PARA OPERACIONES CRUD CON FASTROUTE - ESTILO RESTful
 * Auto-detecta campos y usa métodos HTTP estándar (GET, POST, PUT, PATCH, DELETE)
 *
 * @param {string} containerSelector - Selector del contenedor (ej: '#miForm')
 * @param {object} options - Opciones de configuración
 * @param {string} options.action - Endpoint de la API (ej: '/api/clientes' o '/api/clientes/123')
 *                                  Si no se proporciona, se obtiene del atributo data-action del contenedor
 * @param {string} options.method - Método HTTP: GET, POST, PUT, PATCH, DELETE
 *                                  Si no se proporciona, se obtiene del atributo data-method (default: 'POST')
 * @param {object} options.extraData - Datos adicionales a enviar
 * @param {function} options.onSuccess - Callback ejecutado al éxito
 * @param {function} options.onError - Callback ejecutado en error
 * @param {string|false} options.confirmMessage - Mensaje de confirmación (false para omitir)
 * @param {string} options.successMessage - Mensaje personalizado de éxito
 * @param {boolean} options.reloadAfter - Ejecutar callback después del éxito (default: false)
 * @param {function} options.afterSuccess - Función a ejecutar después del éxito
 *
 * NOTA: El token CSRF se auto-detecta de un input con name="csrf_token" dentro del contenedor
 *
 * @example
 * // HTML
 * <div id="formCliente" data-action="/api/clientes" data-method="POST">
 *   <input name="nombre" required>
 *   <input name="email" type="email" required>
 * </div>
 *
 * // JavaScript - Crear (POST)
 * submitForm('#formCliente');
 *
 * // Actualizar (PUT)
 * submitForm('#formCliente', {
 *   action: '/api/clientes/123',
 *   method: 'PUT'
 * });
 *
 * // Eliminar (DELETE)
 * submitForm('body', {
 *   action: '/api/clientes/123',
 *   method: 'DELETE',
 *   confirmMessage: '¿Está seguro de eliminar?'
 * });
 */
function submitForm(containerSelector, options = {}) {
  const container = document.querySelector(containerSelector);

  if (!container) {
    console.error("submitForm: Contenedor no encontrado:", containerSelector);
    return;
  }

  const {
    action = container.getAttribute("data-action") ||
      container.getAttribute("action"),
    method = container.getAttribute("data-method") ||
      container.getAttribute("method") ||
      "POST",
    extraData = {},
    onSuccess = null,
    onError = null,
    confirmMessage = false,
    successMessage = null,
    reloadAfter = false,
    afterSuccess = null,
  } = options;

  if (!action) {
    console.error(
      "submitForm: No se especificó action. Use data-action en el elemento o páselo en options"
    );
    return;
  }

  // Normalizar método HTTP
  const httpMethod = method.toUpperCase();
  const validMethods = ["GET", "POST", "PUT", "PATCH", "DELETE"];

  if (!validMethods.includes(httpMethod)) {
    console.error(
      `submitForm: Método HTTP inválido: ${httpMethod}. Use: ${validMethods.join(", ")}`
    );
    return;
  }

  // Recolectar automáticamente inputs, selects, radios con required
  const inputs = [];
  const selects = [];
  const radios = new Set();

  container
    .querySelectorAll("input[required], select[required], textarea[required]")
    .forEach((element) => {
      if (element.type === "radio") {
        radios.add(element.name);
      } else if (element.tagName === "SELECT") {
        if (element.id) selects.push(element.id);
      } else {
        if (element.id) inputs.push(element.id);
      }
    });

  // Validar formulario (solo si no es DELETE o GET)
  if (httpMethod !== "DELETE" && httpMethod !== "GET") {
    const validacion = validarCamposGeneric(
      inputs,
      selects,
      Array.from(radios)
    );

    if (!validacion.esValido) {
      return false;
    }
  }

  // Función para ejecutar el submit
  const executeSubmit = () => {
    // Detectar si hay archivos en el formulario
    const hasFiles = container.querySelector('input[type="file"]') !== null;
    let requestData;

    if (hasFiles) {
      // Usar FormData para soportar archivos
      requestData = new FormData();
      
      // Agregar todos los campos del formulario
      const fields = container.querySelectorAll("input, select, textarea");
      fields.forEach((field) => {
        const key = field.name || field.id;
        if (!key) return;

        if (field.type === "file") {
          // Manejar archivos
          if (field.files && field.files.length > 0) {
            // Si es multiple, agregar todos los archivos con [] para que PHP los reciba como array
            if (field.multiple) {
              Array.from(field.files).forEach((file) => {
                requestData.append(`${key}[]`, file);
              });
            } else {
              requestData.append(key, field.files[0]);
            }
          }
        } else if (field.type === "checkbox") {
          requestData.append(key, field.checked ? "1" : "0");
        } else if (field.type === "radio") {
          if (field.checked) {
            requestData.append(key, field.value);
          }
        } else {
          requestData.append(key, field.value);
        }
      });

      // Agregar datos extra
      Object.keys(extraData).forEach(key => {
        requestData.append(key, extraData[key]);
      });
    } else {
      // Sin archivos, usar objeto plano
      const formData = getFormDataFromContainer(containerSelector);
      requestData = {
        ...formData,
        ...extraData,
      };
    }

    // Hacer la petición
    loaderefect(1);

    // Auto-detectar token CSRF de un input hidden dentro del contenedor
    const csrfInput = container.querySelector('input[name="csrf_token"]');
    const headers = {};
    if (csrfInput && csrfInput.value) {
      if (!hasFiles) {
        // Solo agregar en headers si NO hay archivos
        headers["X-CSRF-TOKEN"] = csrfInput.value;
      }
      // Si hay archivos, el token se envía como campo del FormData
      if (hasFiles) {
        requestData.append('csrf_token', csrfInput.value);
      }
    }

    // Configurar AJAX según el método
    const ajaxConfig = {
      url: normalizeUrl(BASE_URL_FOR_JS, action),
      method: httpMethod,
      headers: headers,
      success: function (response) {
        loaderefect(0);

        try {
          const data =
            typeof response === "string" ? JSON.parse(response) : response;

          if (data.status === 1 || data.status === "1") {
            // Éxito
            Swal.fire({
              icon: "success",
              title: "¡Éxito!",
              text:
                successMessage ||
                data.message ||
                "Operación completada exitosamente",
            });

            // Auto-reload de vista si el servidor lo indica
            if (data.reload && data.reload.route) {
              const reloadTarget = data.reload.target || "#cuadro";
              const reloadOptions = {
                route: data.reload.route,
                method: data.reload.method || "GET",
                condi: data.reload.condi || "",
                data: data.reload.data || {},
                csrfToken: data.reload.csrfToken || csrfInput?.value || null,
              };

              // console.log('Auto-recarga de vista:', reloadTarget, reloadOptions);

              setTimeout(() => {
                loadModuleView(reloadTarget, reloadOptions);
              }, 800);
            }

            // Ejecutar callback de éxito
            if (onSuccess && typeof onSuccess === "function") {
              onSuccess(data);
            }
            // Ejecutar acción después del éxito si está configurada
            if (
              reloadAfter &&
              afterSuccess &&
              typeof afterSuccess === "function"
            ) {
              setTimeout(() => {
                afterSuccess(data);
              }, 1000);
            }
          } else {
            // Error controlado
            const timer = data.timer || 6000;

            Swal.fire({
              icon: "warning",
              title: "¡Advertencia!",
              text: data.message || "Error en la operación",
              timer: timer,
            });

            if (onError && typeof onError === "function") {
              onError(data);
            }
          }
        } catch (e) {
          console.error("Error al parsear respuesta:", e);
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "Error al procesar la respuesta del servidor",
          });
        }
      },
      error: function (xhr) {
        loaderefect(0);

        try {
          const errorData = JSON.parse(xhr.responseText);

          if (errorData.messagecontrol) {
            Swal.fire({
              icon: "error",
              title: "Error",
              html: errorData.messagecontrol,
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "Error",
              text:
                errorData.message || errorData.error || "Error en la operación",
            });
          }
        } catch (e) {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "Error al comunicarse con el servidor",
          });
        }

        if (onError && typeof onError === "function") {
          onError(xhr);
        }
      },
    };

    // Configurar datos según si hay archivos
    if (httpMethod === "GET") {
      const queryParams = new URLSearchParams(hasFiles ? {} : requestData).toString();
      ajaxConfig.url += queryParams ? "?" + queryParams : "";
    } else {
      // Para POST, PUT, PATCH, DELETE enviar en body
      ajaxConfig.data = requestData;
      
      // Si hay archivos, configurar opciones especiales para FormData
      if (hasFiles) {
        ajaxConfig.processData = false; // No procesar los datos
        ajaxConfig.contentType = false; // jQuery establece automáticamente multipart/form-data
      } else {
        ajaxConfig.contentType = "application/x-www-form-urlencoded; charset=UTF-8";
      }
    }

    $.ajax(ajaxConfig);
  };

  // Si hay mensaje de confirmación, mostrar
  if (confirmMessage !== false) {
    Swal.fire({
      title: "Confirmación",
      text: confirmMessage,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, continuar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        executeSubmit();
      }
    });
  } else {
    executeSubmit();
  }
}

/**
 * Recolecta todos los valores de inputs/selects/textareas de un contenedor
 * Similar a getFormData de reporteManager pero más completo
 *
 * @param {string} containerSelector - Selector del contenedor
 * @returns {object} Objeto con todos los valores
 */
function getFormDataFromContainer(containerSelector) {
  const container = document.querySelector(containerSelector);
  if (!container) {
    console.warn("Contenedor no encontrado:", containerSelector);
    return {};
  }

  const data = {};

  // Recolectar inputs, selects y textareas
  const fields = container.querySelectorAll("input, select, textarea");

  fields.forEach((field) => {
    const key = field.name || field.id;
    if (!key) return; // Ignorar campos sin name o id

    // Manejar diferentes tipos de campos
    if (field.type === "checkbox") {
      data[key] = field.checked;
    } else if (field.type === "radio") {
      if (field.checked) {
        data[key] = field.value;
      }
    } else if (field.classList.contains("decimal-cleave-zen")) {
      // Campos numéricos formateados
      try {
        data[key] = String(unformatNumeral(field.value));
      } catch (e) {
        data[key] = field.value;
      }
    } else {
      data[key] = field.value;
    }
  });

  return data;
}

/**
 * Envía una petición HTTP sin necesidad de formulario
 * Útil para operaciones CRUD directas (especialmente DELETE)
 *
 * @param {object} options - Opciones de configuración
 * @param {string} options.url - URL del endpoint (ej: '/api/seguros/servicios/123')
 * @param {string} options.method - Método HTTP: GET, POST, PUT, PATCH, DELETE (default: 'POST')
 * @param {object} options.data - Datos a enviar (opcional)
 * @param {function} options.onSuccess - Callback ejecutado al éxito
 * @param {function} options.onError - Callback ejecutado en error
 * @param {string|false} options.confirmMessage - Mensaje de confirmación (false para omitir)
 * @param {string} options.successMessage - Mensaje personalizado de éxito
 *
 * NOTA: La recarga automática se maneja desde el backend. El servidor puede incluir en la respuesta:
 * {
 *   status: 1,
 *   message: 'Éxito',
 *   reload: {
 *     route: '/api/seguros/servicios/index',
 *     target: '#cuadro',  // opcional, default: '#cuadro'
 *     method: 'GET',       // opcional, default: 'GET'
 *     data: {}            // opcional
 *   }
 * }
 *
 * @example
 * // Eliminar un registro (el backend indica dónde recargar)
 * sendRequest({
 *   url: '/api/seguros/servicios/123',
 *   method: 'DELETE',
 *   confirmMessage: '¿Está seguro de eliminar este servicio?'
 * });
 *
 * // Crear sin formulario
 * sendRequest({
 *   url: '/api/seguros/servicios',
 *   method: 'POST',
 *   data: { nombre: 'Nuevo servicio', costo: 100 },
 *   successMessage: 'Servicio creado'
 * });
 */
function sendRequest(options = {}) {
  const {
    url,
    method = "POST",
    data = {},
    onSuccess = null,
    onError = null,
    confirmMessage = false,
    successMessage = null,
    loader = true,
    showMessage = 1,
  } = options;

  if (!url) {
    console.error("sendRequest: Se requiere una URL");
    return;
  }

  // Normalizar método HTTP
  const httpMethod = method.toUpperCase();
  const validMethods = ["GET", "POST", "PUT", "PATCH", "DELETE"];

  if (!validMethods.includes(httpMethod)) {
    console.error(
      `sendRequest: Método HTTP inválido: ${httpMethod}. Use: ${validMethods.join(", ")}`
    );
    return;
  }

  // Función para ejecutar la petición
  const executeRequest = () => {
    if (loader) loaderefect(1);

    // Auto-detectar token CSRF
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const headers = {};
    if (csrfInput && csrfInput.value) {
      headers["X-CSRF-TOKEN"] = csrfInput.value;
    }

    // Configurar AJAX
    const ajaxConfig = {
      url: normalizeUrl(BASE_URL_FOR_JS, url),
      method: httpMethod,
      headers: headers,
      success: function (response) {
        if (loader) loaderefect(0);

        try {
          const responseData =
            typeof response === "string" ? JSON.parse(response) : response;

          if (responseData.status === 1 || responseData.status === "1") {
            // Éxito

            if (showMessage !== 0) {
              Swal.fire({
                icon: "success",
                title: "¡Éxito!",
                text:
                  successMessage ||
                  responseData.message ||
                  "Operación completada exitosamente",
              });
            }

            // Auto-reload de vista si el servidor lo indica
            if (responseData.reload && responseData.reload.route) {
              const reloadTarget = responseData.reload.target || "#cuadro";
              const reloadOptions = {
                route: responseData.reload.route,
                method: responseData.reload.method || "GET",
                condi: responseData.reload.condi || "",
                data: responseData.reload.data || {},
                csrfToken:
                  responseData.reload.csrfToken || csrfInput?.value || null,
              };

              // console.log('Auto-recarga de vista:', reloadTarget, reloadOptions);

              setTimeout(() => {
                loadModuleView(reloadTarget, reloadOptions);
              }, 800);
            }

            // Ejecutar callback de éxito
            if (onSuccess && typeof onSuccess === "function") {
              onSuccess(responseData);
            }
          } else {
            // Error controlado
            if (showMessage !== 0) {
              Swal.fire({
                icon: "warning",
                title: "¡Advertencia!",
                text: responseData.message || "Error en la operación",
                timer: responseData.timer || 6000,
              });
            }

            if (onError && typeof onError === "function") {
              onError(responseData);
            }
          }
        } catch (e) {
          console.error("Error al parsear respuesta:", e);
          if (showMessage !== 0) {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: "Error al procesar la respuesta del servidor",
            });
          }
        }
      },
      error: function (xhr) {
        if (loader) loaderefect(0);

        if (showMessage !== 0) {
          try {
            const errorData = JSON.parse(xhr.responseText);
            Swal.fire({
              icon: "error",
              title: "Error",
              text:
                errorData.message || errorData.error || "Error en la operación",
            });
          } catch (e) {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: "Error al comunicarse con el servidor",
            });
          }
        }

        if (onError && typeof onError === "function") {
          onError(xhr);
        }
      },
    };

    // Si es GET, añadir datos como query params
    if (httpMethod === "GET") {
      const queryParams = new URLSearchParams(data).toString();
      ajaxConfig.url += queryParams ? "?" + queryParams : "";
    } else {
      // Para POST, PUT, PATCH, DELETE enviar en body
      ajaxConfig.data = data;
      ajaxConfig.contentType =
        "application/x-www-form-urlencoded; charset=UTF-8";
    }

    $.ajax(ajaxConfig);
  };

  // Mostrar confirmación si se especifica
  if (confirmMessage !== false) {
    Swal.fire({
      title: "Confirmación",
      text: confirmMessage,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, continuar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        executeRequest();
      }
    });
  } else {
    executeRequest();
  }
}

function obtiene(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback = "NULL",
  messageConfirm = false,
  fileDestino = "crud_kpi"
) {
  const validacion = validarCamposGeneric(inputs, selects, radios);

  if (!validacion.esValido) {
    return false;
  }

  var inputs2 = [];
  var selects2 = [];
  var radios2 = [];
  inputs2 = getinputsval(inputs);
  selects2 = getselectsval(selects);
  radios2 = getradiosval(radios);

  if (messageConfirm !== false) {
    Swal.fire({
      title: "Confirmación",
      text: messageConfirm,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, continuar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        generico(
          inputs2,
          selects2,
          radios2,
          condi,
          id,
          archivo,
          callback,
          fileDestino
        );
      }
    });
  } else {
    generico(
      inputs2,
      selects2,
      radios2,
      condi,
      id,
      archivo,
      callback,
      fileDestino
    );
  }
}

function generico(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback,
  fileDestino
) {
  // console.log(fileDestino)
  $.ajax({
    url: BASE_URL_FOR_JS + "src/cruds/" + fileDestino + ".php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      // console.log(data);
      const data2 = JSON.parse(data);
      if (data2.status == 1) {
        Swal.fire({
          icon: "success",
          title: "Muy Bien!",
          text: data2.message,
        });
        printdiv2("#cuadro", id);

        if (typeof callback === "function") {
          callback(data2);
        }
      } else {
        var reprint = "reprint" in data2 ? data2.reprint : 0;
        var timer = "timer" in data2 ? data2.timer : 60000;
        Swal.fire({
          icon: "warning",
          title: "¡Advertencia!",
          text: data2.message,
          timer: timer,
        });
        if (reprint == 1) {
          setTimeout(function () {
            printdiv2("#cuadro", id);
          }, 1500);
        }
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

/**
 * para validacion de formularios
 * @param {array} inputs - Array de IDs de inputs a validar
 * @param {array} selects - Array de IDs de selects a validar
 * @param {array} radios - Array de nombres de grupos de radios a validar
 * @returns {object} - { esValido: boolean, errores: array }
 */
function validarCamposGeneric(inputs, selects, radios) {
  let errores = [];

  function agregarMensajeError(elemento, mensaje) {
    // clave para vincular feedback con el elemento
    const key =
      elemento.id ||
      elemento.name ||
      "input-" + Math.random().toString(36).slice(2);
    const feedbackId = `feedback-${key}`;

    // helper para escapar selectores si CSS.escape no está disponible
    const escapeForSelector = (s) =>
      window.CSS && CSS.escape
        ? CSS.escape(s)
        : String(s).replace(/(["\\\[\]])/g, "\\$1");

    // Eliminar cualquier feedback previo vinculado a este campo (busca por data-feedback-for)
    const prevs = document.querySelectorAll(
      `[data-feedback-for="${key}"], #${escapeForSelector(feedbackId)}`
    );
    prevs.forEach((n) => n.remove());

    // Crear contenedor del mensaje
    const feedbackDiv = document.createElement("div");
    feedbackDiv.className =
      "error-message text-sm text-error mt-2 flex items-start gap-2";
    feedbackDiv.id = feedbackId;
    feedbackDiv.setAttribute("role", "alert");
    feedbackDiv.setAttribute("data-feedback-for", key);
    feedbackDiv.innerHTML = `<i class="fas fa-exclamation-circle mr-1 mt-0.5"></i><div>${mensaje}</div>`;

    // Marcar accesibilidad en el elemento
    try {
      elemento.setAttribute("aria-invalid", "true");
      elemento.setAttribute("aria-describedby", feedbackId);
    } catch (e) {
      // elemento puede no soportar atributos, ignorar
      console.warn(e);
    }

    // Quitar clases de estado positivo y añadir de error (DaisyUI)
    elemento.classList.remove(
      "input-success",
      "select-success",
      "textarea-success"
    );
    elemento.classList.add("input-error", "select-error", "textarea-error");

    // También limpian clases comunes de otros sistemas
    elemento.classList.remove("is-invalid", "is-valid"); // si venía de bootstrap

    // Determinar el contenedor donde insertar el feedback: preferir .form-control o el parent
    const container =
      elemento.closest(".form-control") ||
      elemento.closest(".input-group") ||
      elemento.parentNode ||
      document.body;

    // Insertar después del elemento si es directo hijo o al final del container
    if (container && container.contains(elemento) && elemento.nextSibling) {
      // si nextSibling es un label o similar, insertar después del elemento
      elemento.parentNode.insertBefore(feedbackDiv, elemento.nextSibling);
    } else {
      container.appendChild(feedbackDiv);
    }

    return mensaje;
  }

  // Función helper para limpiar error
  function limpiarError(elemento) {
    const key = elemento.id || elemento.name;
    // Remover atributos de accesibilidad
    try {
      elemento.removeAttribute("aria-invalid");
      elemento.removeAttribute("aria-describedby");
    } catch (e) {}

    // Remover clases de error y añadir clases de éxito (DaisyUI)
    elemento.classList.remove("input-error", "select-error", "textarea-error");
    elemento.classList.remove("is-invalid");
    // opcional: marcar como válido con DaisyUI
    if (elemento.tagName === "SELECT") {
      elemento.classList.add("select-success");
    } else if (elemento.tagName === "TEXTAREA") {
      elemento.classList.add("textarea-success");
    } else {
      elemento.classList.add("input-success");
    }

    // Eliminar cualquier feedback vinculado por data-feedback-for o id
    if (key) {
      const feedbacks = document.querySelectorAll(
        `[data-feedback-for="${key}"]`
      );
      feedbacks.forEach((n) => n.remove());
      const byId = document.getElementById(`feedback-${key}`);
      if (byId) byId.remove();
    }

    // Eliminar otros selectores de mensajes residuales
    const next = elemento.nextElementSibling;
    if (
      next &&
      (next.classList.contains("error-message") ||
        next.classList.contains("invalid-feedback"))
    ) {
      next.remove();
    }
  }

  // Validar inputs
  inputs.forEach((input) => {
    const elemento = document.getElementById(input);
    if (elemento) {
      let elementoError = false;
      const label = elemento.getAttribute("data-label") || input;
      const valorRaw = elemento.value || "";
      // Detectar si es un campo decimal con cleave-zen
      const isDecimalCleaveZen =
        elemento.classList.contains("decimal-cleave-zen");

      // Usar unformatNumeral si es un campo con cleave-zen
      const valorTrim = isDecimalCleaveZen
        ? String(unformatNumeral(valorRaw)).trim()
        : String(valorRaw).trim();

      // Verifica si el campo es requerido
      if (elemento.hasAttribute("required")) {
        if (!valorTrim) {
          errores.push(
            agregarMensajeError(elemento, `El campo ${label} es obligatorio`)
          );
          elementoError = true;
        }
      }

      // Validar tipo email
      if (elemento.type === "email" && valorTrim) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(valorTrim)) {
          errores.push(
            agregarMensajeError(elemento, `Ingrese un email válido`)
          );
          elementoError = true;
        }
      }

      // Validar números (input type="number" O clase decimal-cleave-zen)
      if ((elemento.type === "number" || isDecimalCleaveZen) && valorTrim) {
        const min = elemento.getAttribute("min");
        const max = elemento.getAttribute("max");
        const valor = parseFloat(valorTrim);

        // Verificar que sea un número válido
        if (isNaN(valor)) {
          errores.push(
            agregarMensajeError(elemento, `Ingrese un valor numérico válido`)
          );
          elementoError = true;
        } else {
          if (min && valor < parseFloat(min)) {
            errores.push(
              agregarMensajeError(elemento, `El valor mínimo es ${min}`)
            );
            elementoError = true;
          }
          if (max && valor > parseFloat(max)) {
            errores.push(
              agregarMensajeError(elemento, `El valor máximo es ${max}`)
            );
            elementoError = true;
          }
        }
      }

      // Validar minlength / maxlength para inputs y textarea (solo si NO es decimal-cleave-zen)
      if (!isDecimalCleaveZen) {
        const minlength = elemento.getAttribute("minlength");
        const maxlength = elemento.getAttribute("maxlength");
        const longitud = valorTrim.length;

        if (minlength && longitud < parseInt(minlength, 10)) {
          errores.push(
            agregarMensajeError(elemento, `Mínimo ${minlength} caracteres`)
          );
          elementoError = true;
        }

        if (maxlength && longitud > parseInt(maxlength, 10)) {
          errores.push(
            agregarMensajeError(elemento, `Máximo ${maxlength} caracteres`)
          );
          elementoError = true;
        }
      }

      // Si no hubo errores para este elemento, limpiar estados previos
      if (!elementoError) {
        limpiarError(elemento);
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
// Inicializador de validación automática para script_indicadores.js
export function startValidationGeneric(formSelector) {
  // console.log(
  //   "Inicializando validación automática (indicadores) para",
  //   formSelector
  // );
  const form = document.querySelector(formSelector);
  if (!form) return;

  // Validar en keyup/blur para inputs y textarea (delegación)
  form.addEventListener(
    "keyup",
    (e) => {
      if (e.target.matches('input:not([type="radio"]), textarea')) {
        validarCamposGeneric([e.target.id], [], []);
      }
    },
    true
  );

  // Delegación para cambios en inputs/selects/radios
  form.addEventListener("change", (e) => {
    if (e.target.matches('input:not([type="radio"]), textarea')) {
      // console.log("Validando input/textarea:", e.target.id);
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

function reportes(
  datos,
  tipo,
  file,
  download = 1,
  bandera = 0,
  modulo = "kpi",
  messageConfirm = false
) {
  // Función interna que ejecuta el reporte
  const ejecutarReporte = () => {
    var datosval = [];
    datosval[0] = getinputsval(datos[0]);
    datosval[1] = getselectsval(datos[1]);
    datosval[2] = getradiosval(datos[2]);
    datosval[3] = datos[3];
    var url = `./${modulo}/reportes/${file}.php`;
    $.ajax({
      url: url,
      async: true,
      type: "POST",
      dataType: "html", //html
      contentType: "application/x-www-form-urlencoded",
      data: { datosval, tipo },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        // console.log(data);
        var opResult = JSON.parse(data);
        if (opResult.status == 1) {
          switch (download) {
            case 0:
              const ventana = window.open();
              ventana.document.write(
                "<object data='" +
                  opResult.data +
                  "' type='application/" +
                  opResult.tipo +
                  "' width='100%' height='100%'></object>"
              );
              break;
            case 1:
              var $a = $(
                "<a href='" +
                  opResult.data +
                  "' download='" +
                  opResult.namefile +
                  "." +
                  tipo +
                  "'>"
              );
              $("body").append($a);
              $a[0].click();
              $a.remove();
              break;
          }
          Swal.fire({
            icon: "success",
            title: "Muy Bien!",
            text: opResult.mensaje,
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "¡ERROR!",
            text: opResult.mensaje,
          });
        }
      },
      complete: function () {
        loaderefect(0);
      },
    });
  };

  // Si messageConfirm es diferente de false, mostrar confirmación
  if (messageConfirm !== false) {
    Swal.fire({
      title: "Confirmación",
      text: messageConfirm,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, continuar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (result.isConfirmed) {
        ejecutarReporte();
      }
    });
  } else {
    ejecutarReporte();
  }
  //-------------------------------------FIN SEGUNDA FUNCION
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
      data: function (d) {
        // Agregar whereExtra si existe en options
        if (options.whereExtra) {
          d.whereExtra =
            typeof options.whereExtra === "function"
              ? options.whereExtra()
              : options.whereExtra;
        }
        // Agregar customData si existe
        if (options.customData) {
          Object.assign(
            d,
            typeof options.customData === "function"
              ? options.customData(d)
              : options.customData
          );
        }
      },
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
function convert_table_to_datatable(id_tabla, opciones = {}) {
  // console.log("convert_table_to_datatable - Tabla:", id_tabla);

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
 * Inicializa Tom Select en un select con configuración predeterminada
 * @param {string} selector - Selector del select (ej: '#periodo_id')
 * @param {object} options - Opciones personalizadas (opcional)
 * @returns {TomSelect} - Instancia de TomSelect
 */
function initTomSelect(selector, options = {}) {
  const defaultOptions = {
    create: false,
    sortField: {
      field: "text",
      direction: "asc",
    },
    placeholder: "Seleccione una opción",
    maxOptions: 200,
    allowEmptyOption: true,
    plugins: {
      clear_button: {
        title: "Limpiar selección",
      },
    },
    render: {
      no_results: function (data, escape) {
        return '<div class="no-results">No se encontraron resultados</div>';
      },
      option_create: function (data, escape) {
        return (
          '<div class="create">Agregar <strong>' +
          escape(data.input) +
          "</strong>&hellip;</div>"
        );
      },
    },
    onInitialize: function () {
      console.log("TomSelect inicializado en:", selector);
    },
  };

  const finalOptions = { ...defaultOptions, ...options };
  const element = document.querySelector(selector);

  if (!element) {
    console.error(`Elemento no encontrado: ${selector}`);
    return null;
  }

  const tomSelectInstance = new TomSelect(element, finalOptions);

  return tomSelectInstance;
}

/**
 * Inicializa Select2 en un select con configuración predeterminada
 * Select2 maneja mejor los optgroups que Tom Select
 * @param {string} selector - Selector del select (ej: '#cuenta_contable')
 * @param {object} options - Opciones personalizadas (opcional)
 * @returns {jQuery} - Instancia de Select2
 */
function initSelect2(selector, options = {}) {
  const defaultOptions = {
    width: "100%",
    placeholder: "Seleccione una opción",
    allowClear: true,
    language: {
      noResults: function () {
        return "No se encontraron resultados";
      },
      searching: function () {
        return "Buscando...";
      },
      loadingMore: function () {
        return "Cargando más resultados...";
      },
    },
    theme: "default", // Puedes usar 'bootstrap-5' si tienes el CSS
  };

  const finalOptions = { ...defaultOptions, ...options };
  const $element = $(selector);

  if ($element.length === 0) {
    console.error(`Elemento no encontrado: ${selector}`);
    return null;
  }

  // Destruir instancia previa si existe
  if ($element.data("select2")) {
    $element.select2("destroy");
  }

  // Inicializar Select2
  $element.select2(finalOptions);

  console.log("Select2 inicializado en:", selector);

  return $element;
}

/**
 * Obtiene datos de Alpine.js desde el scope global
 * @param {string} selector - Selector del elemento con x-data
 * @param {string} property - Propiedad a obtener
 * @returns {any} Valor de la propiedad
 */
function getAlpineData(selector, property) {
  const element = document.querySelector(selector);
  if (!element || !element._x_dataStack) {
    console.error("Elemento Alpine no encontrado:", selector);
    return null;
  }

  const data = element._x_dataStack[0];
  return property ? data[property] : data;
}

// Exponer globalmente por si se inicializa desde templates PHP/HTML
if (typeof window !== "undefined") {
  window.startValidationGeneric = startValidationGeneric;
  window.validarCamposGeneric = validarCamposGeneric;
  window.reportes = reportes;
  window.obtiene = obtiene;
  window.submitForm = submitForm; // Nueva función moderna para CRUD
  window.sendRequest = sendRequest; // Función para peticiones HTTP sin formulario
  window.getFormDataFromContainer = getFormDataFromContainer; // Helper de recolección
  window.printdiv = printdiv;
  window.printdiv2 = printdiv2;
  window.loadModuleView = loadModuleView; // Nueva función para migración
  window.loadView = loadView; // Función simplificada auto-detecta legacy/nuevo
  window.getinputsval = getinputsval;
  window.getselectsval = getselectsval;
  window.getradiosval = getradiosval;
  window.loaderefect = loaderefect;
  window.initServerSideDataTable = initServerSideDataTable;
  window.convert_table_to_datatable = convert_table_to_datatable;
  window.initTomSelect = initTomSelect;
  window.initSelect2 = initSelect2;
  window.getAlpineData = getAlpineData;
  window.$ = $;
  // window.formatNumeral = formatNumeral;

  // Registry de módulos migrados - se puede extender en archivos específicos de módulos
  window.MIGRATED_MODULES = window.MIGRATED_MODULES || {};
}

// ============================================
// INICIALIZACIÓN DE ABLY NOTIFICATIONS
// ============================================

/**
 * Inicializa el sistema de notificaciones Ably
 * Se ejecuta automáticamente si la configuración está disponible en window.ablyConfig
 */
function initAblyNotifications() {
  // Verificar que la configuración esté disponible
  if (
    !window.ablyConfig ||
    !window.ablyConfig.enabled ||
    !window.ablyConfig.clientKey
  ) {
    console.warn(
      "[Ably] Sistema de notificaciones no configurado o deshabilitado"
    );
    return null;
  }

  try {
    // Crear instancia del helper
    const ably = new AblyNotificationHelper({
      clientKey: window.ablyConfig.clientKey,
      channelPrefix: window.ablyConfig.channelPrefix || "app",
      channelHuella: window.ablyConfig.channelHuella || "huella",
      userId: window.currentUserId || null,
      agencyId: window.currentAgencyId || null,
      enabled: window.ablyConfig.enabled !== false,
      debug: window.ENVIRONMENT === "development",
    });

    // Suscribirse a notificaciones del usuario si userId está disponible
    if (window.currentUserId) {
      ably.subscribeToUser(
        // Callback para notificaciones generales
        (data) => {
          console.log("[Ably] Notificación recibida:", data);
          NotificationHelper.showToast(data);

          // Reproducir sonido si está habilitado
          if (data.playSound !== false && window.ablyConfig.enableSound) {
            NotificationHelper.playSound();
          }
        },

        // Callback para actualizaciones de datos
        (data) => {
          console.log("[Ably] Datos actualizados:", data);

          // Recargar tabla según la entidad
          const tableMap = {
            cliente: "clientesTable",
            prestamo: "prestamosTable",
            pago: "pagosTable",
            caja: "cajaTable",
            ahorro: "ahorrosTable",
          };

          const tableId = tableMap[data.entity];
          if (tableId) {
            NotificationHelper.reloadDataTable(tableId);
          }

          // Mostrar notificación de actualización
          NotificationHelper.showToast({
            type: "info",
            message: `${data.entity} actualizado`,
          });
        },

        // Callback para alertas
        (data) => {
          console.log("[Ably] Alerta recibida:", data);

          // Mostrar alerta según severidad
          if (typeof Swal !== "undefined") {
            const icons = {
              info: "info",
              warning: "warning",
              critical: "error",
            };

            Swal.fire({
              icon: icons[data.severity] || "info",
              title:
                data.severity === "critical" ? "⚠️ ALERTA CRÍTICA" : "Alerta",
              text: data.message,
              confirmButtonText: "Entendido",
              allowOutsideClick: data.severity !== "critical",
            });
          }
        }
      );
    }

    // Suscribirse a notificaciones de agencia si agencyId está disponible
    if (window.currentAgencyId) {
      ably.subscribeToAgency((data) => {
        console.log("[Ably] Notificación de agencia:", data);
        NotificationHelper.showToast({
          type: data.type || "info",
          message: `📢 ${data.message}`,
        });
      });
    }

    // Suscribirse al canal broadcast
    ably.subscribeToBroadcast((data) => {
      console.log("[Ably] Broadcast recibido:", data);

      // Mostrar alerta destacada para broadcasts
      if (typeof Swal !== "undefined") {
        Swal.fire({
          icon: data.type === "warning" ? "warning" : "info",
          title: "Anuncio del Sistema",
          html: data.message,
          showConfirmButton: true,
          confirmButtonText: "Entendido",
          allowOutsideClick: false,
          position: "top",
        });
      }
    });

    // Manejar eventos de conexión
    ably.on("connected", () => {
      console.log("[Ably] ✅ Conectado al sistema de notificaciones");
      updateConnectionIndicator("connected");
    });

    ably.on("disconnected", () => {
      console.warn("[Ably] ⚠️ Desconectado del sistema de notificaciones");
      updateConnectionIndicator("disconnected");
    });

    ably.on("failed", () => {
      console.error("[Ably] ❌ Error de conexión");
      updateConnectionIndicator("failed");
    });

    // Exponer globalmente
    window.ablyHelper = ably;
    window.notificationHelper = NotificationHelper;

    console.log("[Ably] Sistema de notificaciones inicializado correctamente");
    return ably;
  } catch (error) {
    console.error(
      "[Ably] Error inicializando sistema de notificaciones:",
      error
    );
    return null;
  }
}

/**
 * Actualiza el indicador visual de estado de conexión
 */
function updateConnectionIndicator(state) {
  const indicator = document.getElementById("ably-status");
  if (!indicator) return;

  const states = {
    connected: { text: "Conectado", class: "bg-green-500 text-white" },
    connecting: { text: "Conectando...", class: "bg-yellow-500 text-white" },
    disconnected: { text: "Desconectado", class: "bg-red-500 text-white" },
    failed: { text: "Error", class: "bg-red-700 text-white" },
  };

  const current = states[state] || states.disconnected;
  indicator.textContent = current.text;
  indicator.className = `px-3 py-1 rounded-full text-xs ${current.class}`;
}

// Inicializar Ably cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", () => {
  // Esperar un poco para que las variables globales se configuren
  setTimeout(() => {
    initAblyNotifications();
  }, 500);
});

// Cerrar conexión al salir de la página
window.addEventListener("beforeunload", () => {
  if (window.ablyHelper) {
    window.ablyHelper.close();
  }
});

// Exponer función de inicialización globalmente
window.initAblyNotifications = initAblyNotifications;
