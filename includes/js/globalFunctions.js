/**
 * Protección contra pérdida de datos en formularios
 * @param {Object} config - Configuración de la protección
 * @param {string} config.formId - ID del formulario a proteger
 * @param {string} config.namespace - Namespace único para evitar conflictos
 * @param {Array} config.excludeSelectors - Selectores de campos a excluir (opcional)
 * @param {Function} config.onBeforeUnload - Callback personalizado (opcional)
 */
function initFormProtection(config = {}) {
  const {
    formId,
    namespace = "FormProtection",
    excludeSelectors = [],
    onBeforeUnload = null,
  } = config;

  // Validar parámetros requeridos
  if (!formId) {
    // console.error("FormProtection: formId es requerido");
    return;
  }

  const formContainer = document.getElementById(formId);
  if (!formContainer) {
    // console.error(
    //   `FormProtection: No se encontró el formulario con ID: ${formId}`
    // );
    return;
  }

  // Crear namespace único
  const namespaceName = `${namespace}_${formId}`;
  if (typeof window[namespaceName] === "undefined") {
    // console.log(`Inicializando ${namespaceName}`);
    window[namespaceName] = {};
  }

  // Limpiar event listeners anteriores
  if (window[namespaceName].initialized) {
    // console.log(`Limpiando event listeners anteriores de ${namespaceName}`);
    window.removeEventListener(
      "beforeunload",
      window[namespaceName].beforeUnloadHandler
    );
  }

  // Seleccionar campos del formulario
  let selector = "input, textarea, select";
  if (excludeSelectors.length > 0) {
    selector += `:not(${excludeSelectors.join("):not(")})`;
  }

  const campos = formContainer.querySelectorAll(selector);
  window[namespaceName].hayCambiosSinGuardar = false;

  function verificarCambios() {
    // console.log(`${namespaceName}: Hay cambios sin guardar`);
    window[namespaceName].hayCambiosSinGuardar = true;
  }

  // Agregar event listeners a los campos
  campos.forEach((campo) => {
    campo.addEventListener("input", verificarCambios);
    campo.addEventListener("change", verificarCambios);
  });

  // Crear la función beforeunload
  window[namespaceName].beforeUnloadHandler = function (e) {
    // console.log(`${namespaceName}: Antes de descargar la página`);

    // NUEVA VALIDACIÓN: Verificar si el formulario todavía existe en el DOM
    const formStillExists = document.getElementById(formId);
    if (!formStillExists) {
      //   console.log(
      //     `${namespaceName}: El formulario ya no existe en el DOM, omitiendo advertencia`
      //   );
      // Resetear la bandera ya que el formulario no existe
      window[namespaceName].hayCambiosSinGuardar = false;
      return;
    }

    if (window[namespaceName].hayCambiosSinGuardar) {
      //   console.log(
      //     `${namespaceName}: Hay cambios sin guardar - mostrando advertencia`
      //   );

      // Ejecutar callback personalizado si existe
      if (onBeforeUnload && typeof onBeforeUnload === "function") {
        const customResult = onBeforeUnload();
        if (customResult === false) return; // No mostrar advertencia
      }

      e.preventDefault();
      e.returnValue = "¡Tienes cambios sin guardar!";
      return e.returnValue;
    }
  };

  window.addEventListener(
    "beforeunload",
    window[namespaceName].beforeUnloadHandler
  );

  // Función para resetear el estado
  window[`resetearCambiosSinGuardar_${formId}`] = function () {
    // console.log(`${namespaceName}: Resetear cambios sin guardar`);
    window[namespaceName].hayCambiosSinGuardar = false;
  };

  // Marcar como inicializado
  window[namespaceName].initialized = true;

  //   console.log(`${namespaceName}: Protección inicializada exitosamente`);

  // Retornar objeto con métodos útiles
  return {
    resetChanges: () => (window[namespaceName].hayCambiosSinGuardar = false),
    hasChanges: () => window[namespaceName].hayCambiosSinGuardar,
    destroy: () => {
      window.removeEventListener(
        "beforeunload",
        window[namespaceName].beforeUnloadHandler
      );
      delete window[namespaceName];
      delete window[`resetearCambiosSinGuardar_${formId}`];
    },
  };
}

// Función para mostrar modal de renovación de sesión
function showRenewModalSession(
  mensaje,
  callbackReintento,
  tokenRenovacion = ""
) {
  Swal.fire({
    title: "Sesión Expirada",
    text:
      mensaje ||
      "Su sesión ha expirado. Debe iniciar sesión nuevamente para continuar.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Renovar Sesión",
    cancelButtonText: "Cancelar",
    allowOutsideClick: false,
    allowEscapeKey: false,
    preConfirm: () => {
      return new Promise((resolve) => {
        Swal.fire({
          title: "Iniciar Sesión",
          html: `
      <div style="text-align: left;">
        <label for="swal-input-usuario" style="display: block; margin-bottom: 5px; font-weight: bold;">Usuario:</label>
        <input id="swal-input-usuario" class="swal2-input" placeholder="Ingrese su usuario" style="margin-bottom: 15px;">
        
        <label for="swal-input-password" style="display: block; margin-bottom: 5px; font-weight: bold;">Contraseña:</label>
        <input id="swal-input-password" type="password" class="swal2-input" placeholder="Ingrese su contraseña">
      </div>
    `,
          showCancelButton: true,
          confirmButtonText: "Iniciar Sesión",
          cancelButtonText: "Cancelar",
          allowOutsideClick: false,
          allowEscapeKey: false,
          focusConfirm: false,
          preConfirm: () => {
            const usuario = document.getElementById("swal-input-usuario").value;
            const password = document.getElementById(
              "swal-input-password"
            ).value;

            if (!usuario || !password) {
              Swal.showValidationMessage("Por favor complete todos los campos");
              return false;
            }

            return { usuario: usuario, password: password };
          },
        }).then((loginResult) => {
          if (loginResult.isConfirmed) {
            // Realizar petición AJAX para renovar sesión
            $.ajax({
              url: BASE_URL_FOR_JS + "/src/cruds/crud_usuario.php",
              method: "POST",
              data: {
                condi: "renew_ses",
                usuario: loginResult.value.usuario,
                password: loginResult.value.password,
                token_renovacion: tokenRenovacion,
              },
              beforeSend: function () {
                Swal.fire({
                  title: "Validando...",
                  text: "Verificando credenciales",
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  showConfirmButton: false,
                  didOpen: () => {
                    Swal.showLoading();
                  },
                });
              },
              success: function (data) {
                try {
                  const response = JSON.parse(data);
                  if (response[0] === true) {
                    // Login exitoso
                    Swal.fire({
                      icon: "success",
                      title: "¡Sesión Renovada!",
                      text: "Sesión renovada exitosamente. Reintentando operación...",
                      timer: 1500,
                      showConfirmButton: false,
                    }).then(() => {
                      // Ejecutar callback para reintentar la operación original
                      if (typeof callbackReintento === "function") {
                        callbackReintento();
                      }
                    });
                  } else {
                    // Error en login
                    Swal.fire({
                      icon: response.icon || "error",
                      title: response.title || "Error de Autenticación",
                      text: response[1] || "Usuario / contraseña incorrectos",
                      showCancelButton: true,
                      confirmButtonText: "Intentar de Nuevo",
                      cancelButtonText: "Cancelar",
                    }).then((result) => {
                      if (result.isConfirmed) {
                        // Volver a mostrar el modal de login
                        showRenewModalSession(
                          mensaje,
                          callbackReintento,
                          tokenRenovacion
                        );
                      }
                    });
                  }
                } catch (e) {
                  console.error("Error al procesar respuesta de login:", e);
                  Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Ocurrió un error al procesar la respuesta del servidor",
                  });
                }
              },
              error: function (xhr, status, error) {
                console.error("Error en petición de login:", error);
                Swal.fire({
                  icon: "error",
                  title: "Error de Conexión",
                  text: "No se pudo conectar con el servidor",
                });
              },
            });
          }
          resolve();
        });
      });
    },
  });
}
