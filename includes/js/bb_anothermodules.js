import "../css/index.css";

import Alpine from "alpinejs";
import persist from "@alpinejs/persist";

import $ from "jquery";
import DataTable from "datatables.net-dt";
import language from "datatables.net-plugins/i18n/es-ES.mjs";

import TomSelect from "tom-select";
import { formatNumeral, unformatNumeral } from "cleave-zen";

Alpine.plugin(persist);
window.Alpine = Alpine;
Alpine.start();

function initCleaveZen() {
  const inputs = document.querySelectorAll(".decimal-cleave-zen");
  //console.log("Inicializando Cleave-Zen en", inputs.length, "inputs");

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
function printdiv(condi, idiv, dir, xtra) {
  // //console.log(xtra);
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
        //console.log(xhr);
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
        //console.log(xhr);
      }
    },
  });
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
  // //console.log("🔵 === INICIO OBTIENE ===");
  // //console.log("🔵 Parámetros recibidos:");
  // //console.log("  - inputs (IDs):", inputs);
  // //console.log("  - selects (IDs):", selects);
  // //console.log("  - radios (names):", radios);
  // //console.log("  - condi:", condi);
  // //console.log("  - id:", id);
  // //console.log("  - archivo:", archivo);
  // //console.log("  - fileDestino:", fileDestino);
  
  const validacion = validarCamposGeneric(inputs, selects, radios);
  // //console.log("🔵 Validación:", validacion.esValido ? "✅ VÁLIDA" : "❌ INVÁLIDA");
  if (!validacion.esValido) {
    // console.error("❌ Validación falló:", validacion.mensaje);
    return false;
  }

  var inputs2 = [];
  var selects2 = [];
  var radios2 = [];
  inputs2 = getinputsval(inputs);
  selects2 = getselectsval(selects);
  radios2 = getradiosval(radios);
  
  // //console.log("🔵 Valores extraídos:");
  // //console.log("  - inputs2 (valores):", inputs2);
  // //console.log("  - selects2 (valores):", selects2);
  // //console.log("  - radios2 (valores):", radios2);
  
  // Log específico para crear credencial
  if (condi === 'crear_credencial') {
    // //console.log("🔵 === DETALLES CREAR CREDENCIAL ===");
    // //console.log("  - inputs2[0] (csrf_token):", inputs2[0]);
    // //console.log("  - inputs2[1] (codcli):", inputs2[1]);
    // //console.log("  - inputs2[2] (usuario):", inputs2[2]);
    // //console.log("  - inputs2[3] (pass):", inputs2[3] ? "***" : "VACÍO");
    // //console.log("  - Longitud de inputs2:", inputs2.length);
  }

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
  // Logs detallados antes de enviar
  // //console.log("🚀 === INICIO GENERICO ===");
  // //console.log("📤 URL destino:", BASE_URL_FOR_JS + "src/cruds/" + fileDestino + ".php");
  // //console.log("📤 Condición (condi):", condi);
  // //console.log("📤 ID:", id);
  // //console.log("📤 Inputs array:", inputs);
  // //console.log("📤 Selects array:", selects);
  // //console.log("📤 Radios array:", radios);
  // //console.log("📤 Archivo:", archivo);
  // //console.log("📤 FileDestino:", fileDestino);
  // //console.log("📤 Callback:", typeof callback === "function" ? "Función definida" : callback);
  
  // Mostrar valores específicos de inputs si es para crear credencial
  if (condi === 'crear_credencial' && inputs && inputs.length >= 4) {
    // //console.log("🔍 Detalles para crear credencial:");
    // //console.log("  - inputs[0] (csrf_token):", inputs[0]);
    // //console.log("  - inputs[1] (codcli):", inputs[1]);
    // //console.log("  - inputs[2] (usuario):", inputs[2]);
    // //console.log("  - inputs[3] (pass):", inputs[3] ? "***" : "VACÍO");
    // //console.log("  - ¿codcli válido?:", inputs[1] ? "SÍ" : "NO");
    // //console.log("  - ¿usuario válido?:", inputs[2] ? "SÍ" : "NO");
    // //console.log("  - ¿pass válido?:", inputs[3] ? "SÍ" : "NO");
  }
  
  $.ajax({
    url: BASE_URL_FOR_JS + "src/cruds/" + fileDestino + ".php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      //console.log("⏳ Enviando petición AJAX...");
      loaderefect(1);
    },
    success: function (data) {
      // //console.log("📥 === RESPUESTA DEL SERVIDOR ===");
      // //console.log("📥 Respuesta raw:", data);
      // //console.log("📥 Tipo de dato:", typeof data);
      // //console.log("📥 Longitud:", data ? data.length : 0);
      
      let data2;
      try {
        data2 = typeof data === 'string' ? JSON.parse(data) : data;
        // //console.log("📥 Respuesta parseada:", data2);
        // //console.log("📥 Status:", data2.status);
        // //console.log("📥 Message:", data2.message);
        // //console.log("📥 Msg:", data2.msg);
        // //console.log("📥 Data:", data2.data);
      } catch (e) {
        console.error("❌ Error al parsear JSON:", e);
        console.error("❌ Datos recibidos:", data);
        console.error("❌ Stack trace:", e.stack);
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Error al procesar la respuesta del servidor: " + e.message,
        });
        loaderefect(0);
        return;
      }
      
      // Usar 'message' o 'msg' según lo que venga
      const mensaje = data2.message || data2.msg || 'Sin mensaje';
      //console.log("📥 Mensaje final a mostrar:", mensaje);
      
      if (data2.status == 1) {
        //console.log("✅ === OPERACIÓN EXITOSA ===");
        //console.log("✅ Mensaje:", mensaje);
        //console.log("✅ Datos adicionales:", data2.data);
        Swal.fire({
          icon: "success",
          title: "Muy Bien!",
          text: mensaje,
        });
        printdiv2("#cuadro", id);

        if (typeof callback === "function") {
          // //console.log("🔄 Ejecutando callback...");
          try {
            callback(data2);
            // //console.log("✅ Callback ejecutado correctamente");
          } catch (callbackError) {
           // console.error("❌ Error en callback:", callbackError);
          }
        } else {
          // //console.log("⚠️ No hay callback definido o no es una función");
        }
      } else {
        // console.warn("⚠️ === OPERACIÓN FALLIDA ===");
        // console.warn("⚠️ Status:", data2.status);
        // console.warn("⚠️ Mensaje:", mensaje);
        // console.warn("⚠️ Respuesta completa:", data2);
        var reprint = "reprint" in data2 ? data2.reprint : 0;
        var timer = "timer" in data2 ? data2.timer : 60000;
        Swal.fire({
          icon: "warning",
          title: "¡Advertencia!",
          text: mensaje,
          timer: timer,
        });
        if (reprint == 1) {
          setTimeout(function () {
            printdiv2("#cuadro", id);
          }, 1500);
        }
      }
    },
    error: function(xhr, status, error) {
      console.error("❌ Error AJAX:", {xhr, status, error});
      console.error("❌ Respuesta del servidor:", xhr.responseText);
      Swal.fire({
        icon: "error",
        title: "Error de conexión",
        text: "No se pudo conectar con el servidor: " + error,
      });
      loaderefect(0);
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
  // //console.log(
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
      // //console.log("Validando input/textarea:", e.target.id);
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
        // //console.log(data);
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
  // //console.log("DataTable inicializado:", tableSelector);

  return table;
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
      // //console.log("TomSelect inicializado en:", selector);
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
  window.printdiv = printdiv;
  window.printdiv2 = printdiv2;
  window.getinputsval = getinputsval;
  window.getselectsval = getselectsval;
  window.getradiosval = getradiosval;
  window.loaderefect = loaderefect;
  window.initServerSideDataTable = initServerSideDataTable;
  window.initTomSelect = initTomSelect;
  window.getAlpineData = getAlpineData;
  window.$ = $;
  // window.formatNumeral = formatNumeral;
}
