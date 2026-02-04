const body = document.querySelector("body"),
  sidebar = body.querySelector("nav"),
  toggle = body.querySelector(".toggle"),
  searchBtn = body.querySelector(".search-box"),
  modeSwitch = body.querySelector(".toggle-switch"),
  modeText = body.querySelector(".mode-text");

// ABRE Y CIERRA EL MENU DEL LATERAL
if (toggle) {
  toggle.addEventListener("click", () => {
    if (sidebar) {
      sidebar.classList.toggle("close");
      localStorage.setItem(
        "sidebar-status",
        sidebar.classList.contains("close") ? "close" : "open"
      );
    }
  });
}

function changeTheme() {
  const currentTheme = document.documentElement.getAttribute("data-bs-theme");
  const newTheme = currentTheme === "dark" ? "light" : "dark";
  document.documentElement.setAttribute("data-bs-theme", newTheme);
  localStorage.setItem("theme-mode", newTheme);
  if (newTheme === "dark") {
    body.classList.add("dark");
    // modeText.innerText = "Modo Claro";
  } else {
    body.classList.remove("dark");
    // modeText.innerText = "Modo Oscuro";
  }
}

//funcion para el toogle
function active_modo(bandera = 1, retornos = "..") {
  changeTheme();
  // var color = "";
  // if (body.classList.contains("dark")) {
  //   color = 0;
  // } else {
  //   color = 1;
  // }
  // //Realizar una consulta ajax
  // $.ajax({
  //   type: "POST",
  //   url: retornos + "/src/cruds/crud_usuario.php",
  //   data: { condi: "modo", color: color, bandera: bandera },
  //   dataType: "json",
  //   beforeSend: function () {
  //     loaderefect(1);
  //   },
  //   success: function (data) {
  //     loaderefect(0);
  //     // console.log(data);
  //     if (data[2] == "1") {
  //       body.classList.add("dark");
  //       modeText.innerText = "Modo Claro";
  //     } else {
  //       body.classList.remove("dark");
  //       modeText.innerText = "Modo Oscuro";
  //     }
  //   },
  //   error: function (xhr) {
  //     loaderefect(0);
  //     Swal.fire({
  //       icon: "error",
  //       title: "¡ERROR!",
  //       text:
  //         "Codigo de error: " +
  //         xhr.status +
  //         ", Información de error: " +
  //         xhr.responseJSON,
  //     });
  //   },
  //   complete: function () {
  //     loaderefect(0);
  //   },
  // });
}

//#region loader
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
//#endregion
//#region obtener datos de inputs, selects, radios
//---------obtener datos de inputs.. pasar datos como vectores con el id de los inputs, y retorna array
function getinputsval(datos) {
  const inputs2 = [""];
  var i = 0;
  while (i < datos.length) {
    inputs2[i] = document.getElementById(datos[i]).value;
    i++;
  }
  return inputs2;
}
//---------obtener datos de selects.. pasar datos como vectores con el id de los selects, y retorna array
function getselectsval(datos) {
  const selects2 = [""];
  let i = 0;
  while (i < datos.length) {
    const e = document.getElementById(datos[i]);
    if (e) {
      const values = Array.from(e.selectedOptions).map((opt) => opt.value);
      selects2[i] = values.join(",");
    } else {
      selects2[i] = "";
    }
    i++;
  }
  return selects2;
}
//---------obtener datos de radios.. pasar datos como vectores con el name de los radios, y retorna array
function getradiosval(datos) {
  const radios2 = [""];
  i = 0;
  while (i < datos.length) {
    radios2[i] = document.querySelector(
      'input[name="' + datos[i] + '"]:checked'
    ).value;
    i++;
  }
  return radios2;
}
function getfilesval(datos) {
  const files2 = [];
  var i = 0;
  while (i < datos.length) {
    const fileInput = document.getElementById(datos[i]);
    if (fileInput && fileInput.files.length > 0) {
      files2[i] = fileInput.files[0]; // Obtenemos el primer archivo seleccionado
    } else {
      files2[i] = null; // Si no hay archivo, ponemos null
    }
    i++;
  }
  return files2;
}
//#endregion

function salir() {
  $(location).attr("href", "index.php");
}
//#region LOADER
//FUNCION PARA EL EFECTO DEL LOADER
// function loaderefect(sh) {
//   const LOADING = document.querySelector('.loader-container');
//   switch (sh) {
//     case 1:
//       LOADING.classList.remove('loading--hide');
//       LOADING.classList.add('loading--show');
//       break;
//     case 0:
//       LOADING.classList.add('loading--hide');
//       LOADING.classList.remove('loading--show');
//       break;
//   }
// }
//#endregion

//script para eliminar la session
// $("#eliminarsesion").click(function (e) {
//   // console.log('ci');
//   e.preventDefault();
//   var url = window.location.origin + '/src/cruds/crud_usuario.php';
//   console.log(window.location.origin)
//   $.ajax({
//     type: 'POST',
//     url: url,
//     data: { 'condi': 'salir' },
//     dataType: 'json',
//     beforeSend: function () {
//       loaderefect(1);
//     },
//     success: function (data) {
//       loaderefect(0);
//       window.location.reload();
//     },
//     error: function (xhr) {
//       loaderefect(0);
//       Swal.fire({
//         icon: 'error',
//         title: '¡ERROR!',
//         text: 'Codigo de error: ' + xhr.status + ', Información de error: ' + xhr.responseJSON
//       });
//     },
//     complete: function () {
//       loaderefect(0);
//     },
//   });
// });

function sessiondestroy(url) {
  $.ajax({
    type: "POST",
    url: url,
    data: { condi: "salir" },
    dataType: "json",
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      loaderefect(0);
      window.location.reload();
    },
    error: function (xhr) {
      loaderefect(0);
      Swal.fire({
        icon: "error",
        title: "¡ERROR!",
        text:
          "Codigo de error: " +
          xhr.status +
          ", Información de error: " +
          xhr.responseJSON,
      });
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

$("#eliminarsesion2").click(function (e) {
  // console.log('ci');
  e.preventDefault();
  $.ajax({
    type: "POST",
    url: "../../src/cruds/crud_usuario.php",
    data: { condi: "salir" },
    dataType: "json",
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      loaderefect(0);
      window.location.reload();
    },
    error: function (xhr) {
      loaderefect(0);
      Swal.fire({
        icon: "error",
        title: "¡ERROR!",
        text:
          "Codigo de error: " +
          xhr.status +
          ", Información de error: " +
          xhr.responseJSON,
      });
    },
    complete: function () {
      loaderefect(0);
    },
  });
});
$("#eliminarsesion3").click(function (e) {
  // console.log('ci');
  e.preventDefault();
  $.ajax({
    type: "POST",
    url: "../../../src/cruds/crud_usuario.php",
    data: { condi: "salir" },
    dataType: "json",
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      loaderefect(0);
      window.location.reload();
    },
    error: function (xhr) {
      loaderefect(0);
      Swal.fire({
        icon: "error",
        title: "¡ERROR!",
        text:
          "Codigo de error: " +
          xhr.status +
          ", Información de error: " +
          xhr.responseJSON,
      });
    },
    complete: function () {
      loaderefect(0);
    },
  });
});

//INYECTAR CODIGO
function inyecCod(
  idElem = "#tbAlerta",
  condi = "alertas",
  url = "../src/menu/alertatb.php"
) {
  // console.log(condi);
  $.ajax({
    url: url,
    type: "POST",
    data: {
      condi,
    },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      if (condi === "alertas") {
        if (data !== false) {
          // consulta();
          // timerLock();
          $(idElem).html(data);
          inyecCod(
            (idElem = ""),
            (condi = "dataTablef"),
            (url = "../src/menu/alertatb.php")
          );
          return;
        }
      }
      if (condi === "dataTablef") {
        const data2 = JSON.parse(data);
        for (con = 0; con < data2[0].length; con++) {
          if ($("#" + data2[0][con]).length > 0) {
            dataTable(data2[0][con]);
          }
        }
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function dataTable(id_tabla) {
  $("#tb" + id_tabla)
    .on("search.dt")
    .DataTable({
      lengthMenu: [
        [5, 10, 15, -1],
        ["5 filas", "10 filas", "15 filas", "Mostrar todos"],
      ],
      language: {
        lengthMenu: "Mostrar _MENU_ registros",
        zeroRecords: "No se encontraron registros",
        info: " ",
        infoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
        infoFiltered: "(filtrado de un total de: _MAX_ registros)",
        sSearch: "Buscar: ",
        oPaginate: {
          sFirst: "Primero",
          sLast: "Ultimo",
          sNext: "Siguiente",
          sPrevious: "Anterior",
        },
        sProcessing: "Procesando...",
      },
    });
}

function obtieneAux(datos) {
  var condi = "proceIVE";
  genericoAux(datos, 1, condi);
}

//ASEPTAR LOS DATOS DEL IVE
function genericoAux(
  datos,
  archivo,
  condi,
  url = (typeof BASE_URL_FOR_JS !== 'undefined' ? BASE_URL_FOR_JS : '') + "/src/cruds/crud_alerta.php"
) {
  $.ajax({
    url: url,
    method: "POST",
    data: {
      datos,
      archivo,
      condi,
    },
    beforeSend: function () {
      loaderefect(1);
    },

    success: function (data) {
      const data2 = JSON.parse(data);

      if (data2[1] == "1") {
        if (condi === "validar_usuario_por_interes") {
          // console.log(datos[0][1]);
          inte = $("#" + datos[0][3]).val();
          datos[0].push(inte);
          genericoAux(
            [datos[0]],
            [""],
            "act_interes",
            "../../src/cruds/crud_credito_indi.php"
          );
        } else if (condi === "act_interes") {
          Swal.fire({
            icon: "success",
            title: "Muy Bien!",
            text: data2[0],
          });
        } else if (condi === "proceIVE" || condi === "autorizarMora") {
          Swal.fire({
            icon: "success",
            title: "Muy Bien!",
            text: data2[0],
          });
          // cerrarModal();
          // inyecCod();
        }
      } else {
        if (condi === "validar_usuario_por_interes") {
          $("#" + datos[0][3]).val(datos[0][4]);
        }
        Swal.fire({
          icon: "error",
          title: "¡ERROR!",
          text: data2[0],
        });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

/*
  Alerta cuando un usuario necesita realizar una modificacion y se tienen que autenticar
*/

function validaInteres(extra) {
  int_act = parseFloat($("#" + extra[3]).val());
  // if( int_act > parseFloat(extra[4])){
  //   $('#'+extra[3]).val(extra[4]);
  //   Swal.fire({
  //     icon: "error",
  //     title: "¡ERROR!",
  //     text: "El nuevo interés tiene que ser menor al interés actual… :)"
  //   });
  // }else{
  alertaRestrincion(extra);
  // }
}

async function alertaRestrincion(extra) {
  const swalWithBootstrapButtons = Swal.mixin({
    customClass: {
      confirmButton: "btn btn-success",
      cancelButton: "btn btn-danger",
    },
    buttonsStyling: false,
  });

  const result = await swalWithBootstrapButtons.fire({
    title: "ALERTA?",
    text: "¿Está seguro de cambiar el interés?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Guardar cambios",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    allowOutsideClick: false,
  });

  if (result.isConfirmed) {
    // const { value: password } = await Swal.fire({
    //   title: "Ingrese su contraseña",
    //   input: "password",
    //   inputLabel: "Contraseña",
    //   inputPlaceholder: "Ingrese su contraseña",
    //   inputAttributes: {
    //     maxlength: "30",
    //     autocapitalize: "off",
    //     autocorrect: "off"
    //   },
    //   allowOutsideClick: false
    // });

    // if (password.length > 0) {
    //   loaderefect(0);
    //   genericoAux([extra, password], [''], 'validar_usuario_por_interes', '../../src/cruds/crud_usuario.php')
    // }else{
    //   $('#'+extra[3]).val(extra[4]);
    // }
    //--REQ--ADG--1-- No validar usuario al modificar plan de pagos
    datos = [extra];
    inte = $("#" + extra[3]).val();
    datos[0].push(inte);
    genericoAux(
      [datos[0]],
      [""],
      "act_interes",
      "../../src/cruds/crud_credito_indi.php"
    );
  } else if (result.dismiss === Swal.DismissReason.cancel) {
    $("#" + extra[3]).val(extra[4]);
    swalWithBootstrapButtons.fire({
      title: "Cancelado",
      text: "Se cancelo la actualización del interes :)",
      icon: "error",
    });
  }
}

//CONTROLA LA VENTANA DE NOTIFICACIONES
$(document).ready(function () {
  // console.log("holi")
  $("#bell").click(function (e) {
    e.stopPropagation(); // Evita que el clic se propague
    $(".notifications").toggleClass("open");
  });

  $(document).on("click", function (e) {
    if (
      !$(e.target).closest(".notifications").length &&
      !$(e.target).is("#bell")
    ) {
      $(".notifications").removeClass("open");
    }
  });

  if (document.getElementById("notificationsContainer1") !== null) {
    const alertaUrl = (typeof BASE_URL_FOR_JS !== 'undefined' ? BASE_URL_FOR_JS : '') + "/src/cruds/crud_alerta.php";
    loadnotifications(alertaUrl, 1);
  }

  timerLock();
  loaderefect(0);
  window.addEventListener("online", checkInternetConnection);
  window.addEventListener("offline", checkInternetConnection);
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

  /**
   * los temas
   */
  if (document.documentElement.hasAttribute("data-bs-theme")) {
    const savedTheme = localStorage.getItem("theme-mode") || "light";
    document.documentElement.setAttribute("data-bs-theme", savedTheme);
    if (savedTheme === "dark") {
      body.classList.add("dark");
      // modeText.innerText = "Modo Claro";
    } else {
      body.classList.remove("dark");
      // modeText.innerText = "Modo Oscuro";
    }
  }

  /**
   * Estado del sidebar
   */
  const sidebarStatus = localStorage.getItem("sidebar-status") || "open";
  const sidebar = document.querySelector(".sidebar");
  if (sidebarStatus === "close") {
    sidebar.classList.add("close");
  } else {
    sidebar.classList.remove("close");
  }
});

function checkInternetConnection() {
  // const imgElement = document.getElementById('imgiconnetwork');
  //console.log(imgElement)
  if (navigator.onLine) {
    // imgElement.src = './../assets/svg/networkgreen.svg'
    Swal.fire({
      position: "top-end",
      icon: "success",
      title: "Se reestableció la conexión a internet :)",
      showConfirmButton: false,
      timer: 5000,
    });
  } else {
    // imgElement.src = './../assets/svg/networkdisabled.svg'
    Swal.fire({
      icon: "error",
      title: "Ups!",
      text: "Se perdió la conexion a internet :(",
    });
  }
}

function timerLock() {
  var intervalo = setInterval(function () {
    const notificationsContainer = document.getElementById(
      "notificationsContainer1"
    );
    // console.log(notificationsContainer)
    if (
      notificationsContainer === undefined ||
      notificationsContainer === null ||
      !navigator.onLine
    ) {
      clearInterval(intervalo);
    } else {
      const alertaUrl = (typeof BASE_URL_FOR_JS !== 'undefined' ? BASE_URL_FOR_JS : '') + "/src/cruds/crud_alerta.php";
      loadnotifications(alertaUrl, 2);
    }
  }, 5000);
}

function loadnotifications(url, option) {
  fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ condi: "notifications", opcion: option }),
  })
    .then((response) => response.text())
    .then((text) => {
      // console.log('Raw response:', text);
      try {
        const data = JSON.parse(text); // Intenta analizar el JSON
        // console.log('Parsed JSON:', data);
        if (data[1]) {
          const notificationsContainer = document.getElementById(
            "notificationsContainer1"
          );
          notificationsContainer.innerHTML = "";
          const notificationsContainer2 = document.getElementById(
            "notificationsContainer2"
          );
          let contadorive = 0;
          let contadorpfpass = 0;
          data[0].forEach((notification) => {
            if (
              notification.imgSrc &&
              notification.title &&
              notification.message
            ) {
              const notificationItem = createNotificationItem(notification);
              if (notification.tipo == 1) {
                notificationsContainer.appendChild(notificationItem);
              } else {
                if (option == 1) {
                  notificationsContainer2.appendChild(notificationItem);
                }
              }
              contadorpfpass += notification.tipo > 1 ? 1 : 0;
              contadorive += notification.tipo == 1 ? 1 : 0;
            } else {
              console.warn(
                "Notification item is missing required properties:",
                notification
              );
            }
          });
          let contgeneral = 0;
          if (option == 1) {
            contgeneral = contadorpfpass + contadorive;
            document.querySelector("#auxcontadorpfpass").value = contadorpfpass;
          } else {
            contgeneral =
              Number(document.querySelector("#auxcontadorpfpass").value) +
              contadorive;
          }
          document.querySelector("#id_con_alt").textContent =
            contgeneral.toString();
        } else {
        }
      } catch (error) {
        console.error("Error parsing JSON:", error);
      }
    })
    .catch((error) => {
      console.error("There was a problem with your fetch operation:", error);
    });
}

function createNotificationItem(notification) {
  const item = document.createElement("div");
  item.className = "notifications-item";

  const img = document.createElement("img");
  img.src = notification.imgSrc;
  img.alt = "img";
  item.appendChild(img);

  const textDiv = document.createElement("div");
  textDiv.className = "text";

  const title = document.createElement("h4");
  title.textContent = notification.title;
  textDiv.appendChild(title);

  const message = document.createElement("p");
  message.textContent = notification.message;
  textDiv.appendChild(message);

  if ("button" in notification) {
    textDiv.insertAdjacentHTML("beforeend", notification.button);
  }

  item.appendChild(textDiv);
  return item;
}

function printd2(condi, idiv, dir, xtra) {
  loaderefect(1);
  $.ajax({
    url: dir,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      $(idiv).html(data);
      loaderefect(0);
    },
  });
}
function directaccess(modulopen, condi, file, extra) {
  var nuevaVentana = window.open(modulopen, "_blank");
  nuevaVentana.onload = function () {
    nuevaVentana.printdiv(condi, "#cuadro", file, extra);
  };
}
function validateForm() {
  // console.log("joli")
  const fields = document.querySelectorAll("#cuadro [required]");
  let isValid = true;
  // console.log(fields)
  fields.forEach((field) => {
    if (!field.value.trim()) {
      field.classList.add("error");
      isValid = false;
    } else {
      field.classList.remove("error");
    }
  });

  return isValid;
}

//Making by beneq

//FUNCION PARA VERIFICAR SI LA OPERACION NECSEITA VERIFICACION DE HUELLA
function verifyFingerprint(callback, operationType = 0, peopleCode = 0) {
  loaderefect(1);
  $.ajax({
    url: "../src/cruds/endpoints.php",
    data: { condi: "verifyFingerprint", operationType },
    method: "POST",
    success: function (response) {
      // console.log(response);
      var opResult = JSON.parse(response);
      if (opResult.status == 1) {
        if (opResult.verify == 1) {
          loaderefect(0);
          //console.log(opResult);
          if (opResult.huella_version == 2) {
            openFingerprintWindow2(
              () => {
                callback(true);
              },
              peopleCode,
              opResult.keyClient,
              opResult.channelPrefix,
              operationType
            );
          } else {
            openFingerprintWindow(() => {
              callback(true);
            }, peopleCode, operationType);
          }
        } else {
          loaderefect(0);
          callback(true);
        }
      } else {
        loaderefect(0);
        Swal.fire({ icon: "error", title: "¡ERROR!", text: opResult.mensaje });
      }
    },
    complete: function (data) {
      // loaderefect(0);
    },
    error: function (err) {
      loaderefect(0);
      // console.error('Error verifying fingerprint', err);
    },
  });
}

//FUNCION PARA ABRIR LA VENTANA DE HUELLA CUANDO SE NECESITA VALIDAR UNA HUELLA
function openFingerprintWindow(callback, peopleCode, operationType = 1) {
  // Generar el HTML del modal personalizado
  const modalHTML = `
    <div class="modal fade" id="fingerprintModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="fingerprintModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="fingerprintModalLabel">Autorización de transacción por huella</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div style="display: block; padding-left: 3px;">
              <label class="form-label" id="statusPlantilla" style="margin-left: 5px;">Estado del sensor: Inactivo</label>
              <div class="card">
                <div class="card-body" id="textoSensor"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" id="validateFingerprintBtn">Validar huella!!</button>
          </div>
        </div>
      </div>
    </div>
  `;

  // Añadir el modal al body si no existe
  if (!document.getElementById("fingerprintModal")) {
    document.body.insertAdjacentHTML("beforeend", modalHTML);
  }

  // Mostrar el modal
  const modal = new bootstrap.Modal(
    document.getElementById("fingerprintModal")
  );
  modal.show();

  // Variables para controlar el estado
  let isVerified = false;
  let isSensorActive = false;

  // Función para cerrar el modal y limpiar
  const closeModal = (success = false) => {
    stopSensor();
    modal.hide();
    if (success && callback && typeof callback === "function") {
      callback();
    }
    // Opcional: remover el modal del DOM después de cerrar
    document.getElementById("fingerprintModal")?.remove();
  };

  // Evento del botón de validar
  document
    .getElementById("validateFingerprintBtn")
    ?.addEventListener("click", () => {
      const validateBtn = document.getElementById("validateFingerprintBtn");
      validateBtn.disabled = true;
      validateBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Validando...';

      activarSensor(
        (isActivated) => {
          //console.log("Sensor activado:", isActivated);
          if (isActivated) {
            //console.log("Activando sensor...");
            isSensorActive = true;
            load_push((verified) => {
              const sensorText = document.getElementById("textoSensor");
              if (verified) {
                //console.log("Huella verificada correctamente ss");
                sensorText.innerHTML =
                  '<div class="alert alert-success">Huella verificada correctamente.</div>';
                validateBtn.disabled = true;
                validateBtn.textContent = "Validada";

                //retardar 1.5 segundos antes de cerrar el modal
                setTimeout(() => {
                  isVerified = true;
                  closeModal(true); // Cierra el modal y ejecuta el callback
                }, 1500);
              } else {
                isVerified = false;
                //console.log("Huella no verificada o aún no se ha validado");
                // Mostrar mensaje de error

                sensorText.innerHTML =
                  '<div class="alert alert-danger">Aún no se ha validado la huella. Por favor, inténtalo de nuevo.</div>';
                validateBtn.disabled = false;
                validateBtn.textContent = "Validar huella!!";
              }
            });
          } else {
            isSensorActive = false;
            const sensorText = document.getElementById("textoSensor");
            sensorText.innerHTML =
              '<div class="alert alert-danger">Error al activar el sensor. Inténtalo de nuevo.</div>';
            validateBtn.disabled = false;
            validateBtn.textContent = "Validar huella!!";
          }
        },
        operationType,
        peopleCode
      );
    });

  // Manejar el cierre del modal (cancelar, backdrop, esc)
  document
    .getElementById("fingerprintModal")
    ?.addEventListener("hidden.bs.modal", () => {
      closeModal(false);
    });
}

function openFingerprintWindow2(
  callback,
  peopleCode,
  keyClient,
  channelPrefix, operationType = 1
) {
  //console.log("Abriendo ventana de huella dactilar versión 2");
  // Generar el HTML del modal personalizado
  const modalHTML = `
    <div class="modal fade" id="fingerprintModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="fingerprintModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="fingerprintModalLabel">Autorización de transacción por huella</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div style="display: block; padding-left: 3px;">
              <label class="form-label" id="statusPlantilla" style="margin-left: 5px;">Estado del sensor: Inactivo</label>
              <div class="card">
                <div class="card-body" id="textoSensor"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" >Cancelar</button>
            <button type="button" class="btn btn-primary" id="validateFingerprintBtn">Validar huella!!</button>
          </div>
        </div>
      </div>
    </div>
  `;

  // Añadir el modal al body si no existe
  if (!document.getElementById("fingerprintModal")) {
    document.body.insertAdjacentHTML("beforeend", modalHTML);
  }

  // Mostrar el modal
  const modal = new bootstrap.Modal(
    document.getElementById("fingerprintModal")
  );
  modal.show();

  // Variables para controlar el estado
  let isVerified = false;
  let isSensorActive = false;

  // Función para cerrar el modal y limpiar
  const closeModal = (success = false) => {
    stopSensor();
    modal.hide();
    if (success && callback && typeof callback === "function") {
      callback();
    }
    // Opcional: remover el modal del DOM después de cerrar
    document.getElementById("fingerprintModal")?.remove();
  };

  // Evento del botón de validar
  document
    .getElementById("validateFingerprintBtn")
    ?.addEventListener("click", () => {
      const validateBtn = document.getElementById("validateFingerprintBtn");
      validateBtn.disabled = true;
      validateBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Validando...';

      activarSensor(
        (isActivated) => {
          //console.log("Sensor activado2:", isActivated);
          $("#statusPlantilla").text("Estado del sensor: Activo");
          if (isActivated) {
            // console.log("Activando sensor...2");
            isSensorActive = true;

            loadSincData(keyClient, channelPrefix, function (verified) {
              const sensorText = document.getElementById("textoSensor");
              if (verified) {
                // console.log("Huella verificada correctamente ss");
                sensorText.innerHTML =
                  '<div class="alert alert-success">Huella verificada correctamente.</div>';
                validateBtn.disabled = true;
                validateBtn.textContent = "Validada";

                //retardar 1.5 segundos antes de cerrar el modal
                setTimeout(() => {
                  isVerified = true;
                  closeModal(true); // Cierra el modal y ejecuta el callback
                }, 1500);
              } else {
                isVerified = false;
                //console.log("Huella no verificada o aún no se ha validado");
                // Mostrar mensaje de error
                // Agregar con formato mejorado
                const timestamp = new Date().toLocaleTimeString();
                sensorText.innerHTML += `
                    <div class="alert alert-danger mt-2">
                      <strong>[${timestamp}] Intento fallido:</strong> 
                      Huella no reconocida. Por favor, inténtalo de nuevo.
                    </div>
                  `;

                // sensorText.innerHTML =
                //   sensorText.innerHTML +
                //   '<div class="alert alert-danger">Aún no se ha validado la huella. Por favor, inténtalo de nuevo.</div>';
                // validateBtn.disabled = false;
                // validateBtn.textContent = "Validar huella!!";
              }
            });
          } else {
            isSensorActive = false;
            const sensorText = document.getElementById("textoSensor");
            sensorText.innerHTML =
              '<div class="alert alert-danger">Error al activar el sensor. Inténtalo de nuevo.</div>';
            validateBtn.disabled = false;
            validateBtn.textContent = "Validar huella!!";
          }
        },
        operationType,
        peopleCode
      );
    });

  // Manejar el cierre del modal (cancelar, backdrop, esc)
  document
    .getElementById("fingerprintModal")
    ?.addEventListener("hidden.bs.modal", () => {
      closeModal(false);
    });
}

function loadDataFinger(callback) {
  let srn = localStorage.getItem("srnPc");
  // console.log(srn)
  $.ajax({
    async: true,
    type: "POST",
    url: "../src/cruds/endpoints.php",
    data: { condi: "sincData", srn },
    dataType: "json",
    success: function (data) {
      var opResult = JSON.parse(JSON.stringify(data));
      if (opResult.status === 1) {
        if (callback && typeof callback === "function") {
          callback(opResult.data);
        }
      } else {
        console.error(
          "Error al cargar los datos de la huella:",
          opResult.mensaje
        );
      }
    },
    complete: function (data) {
      // console.log(data);
    },
    error: function (xhr, status, error) {
      console.log("Error en la solicitud:");
      console.error(error);
      console.error(status);
      console.dir(xhr);
    },
  });
}

function loadSincData(clientKey, channelPrefix, callback) {
  let isVerified = false; // Variable para controlar si ya se verificó
  let subscription = null; // Variable para almacenar la suscripción

  async function subscribe() {
    const realtime = new Ably.Realtime.Promise(clientKey);
    let srn = localStorage.getItem("srnPc");
    const channel = realtime.channels.get(channelPrefix + "_" + srn);

    subscription = await channel.subscribe("sinc", (message) => {
      if (isVerified) return; // Si ya está verificado, ignorar mensajes adicionales

      //console.log("Message received: " + message.data);
      loadDataFinger(function (data) {
        $("#statusPlantilla").text(data["statusPlantilla"]);
        $("#textoSensor").text(data["texto"]);

        if (data["statusPlantilla"] === "Usuario Verificado") {
          // console.log("Huella verificada correctamente");
          isVerified = true; // Marcar como verificado

          // Desuscribirse del canal
          if (subscription) {
            // subscription.unsubscribe();
            channel.detach();
            realtime.close();
          }

          if (callback && typeof callback === "function") {
            //console.log("Ejecutando callback de validación de huella");
            callback(true);
          }
        } else {
          callback(false);
          //console.log("aún no se ha validado la huella");
        }
      });
    });
  }

  subscribe();
}

var timestamp = null;
let continuePolling = true;

function load_push(callback) {
  let srn = localStorage.getItem("srnPc");
  if (!srn) {
    // console.error("Token not found in localStorage");
    if (callback && typeof callback === "function") {
      callback(false); // Indica fallo
    }
    return;
  }

  $.ajax({
    async: true,
    type: "POST",
    url: "../src/huella/httpush.php",
    data: "&timestamp=" + timestamp + "&token=" + srn,
    dataType: "json",
    success: function (data) {
      // console.log(data);
      var json = JSON.parse(JSON.stringify(data));
      timestamp = json["timestamp"];
      imageHuella = json["imgHuella"];
      tipo = json["tipo"];
      id = json["id"];

      // Actualiza elementos del DOM
      $("#statusPlantilla").text(json["statusPlantilla"]);
      $("#textoSensor").text(json["texto"]);

      if (json["statusPlantilla"] === "Usuario Verificado") {
        if (callback && typeof callback === "function") {
          callback(true); // Indica que la huella fue validada
        }
      } else {
        $("#textoSensor").text(
          "Aún no se ha validado la huella. Por favor, inténtalo de nuevo."
        );
        // console.log("intentando njuevamente");
        setTimeout(() => load_push(callback), 1000); // Continuar polling
      }
    },
    complete: function (data) {
      // console.log(data);
    },
    error: function (xhr, status, error) {
      // console.error(error);
    },
  });
}

//ACTIVAR SENSOR
function activarSensor(callback, operation, peopleCode) {
  // console.log("Activando sensor...");
  let token = localStorage.getItem("srnPc");
  if (!token) {
    // console.error("Token not found in localStorage");
    simpleAlert(
      "Error: No se pudo activar el sensor. Token no encontrado en esta instancia."
    );
    if (callback && typeof callback === "function") {
      callback(false); // Indica fallo
    }
    return;
  }
  let sessionSerial = $("#sessionSerial").val();

  $.ajax({
    type: "POST",
    url: "../src/cruds/endpoints.php",
    data: {
      token,
      condi: "activarSensor",
      sessionSerial,
      peopleCode,
      operation,
    },
    dataType: "json",
    success: function (response) {
      // console.log("Respuesta del sensor:", response);
      if (response.status == 1) {
        // console.log("Sensor activado correctamente");
        if (callback && typeof callback === "function") {
          callback(true); // Indica éxito
        }
      } else {
        console.error("Error al activar el sensor:", response.message);
        simpleAlert(response.message);
        if (callback && typeof callback === "function") {
          callback(false); // Indica fallo
        }
      }
    },
    error: function (xhr, status, error) {
      console.error("Error al intentar activar el sensor:", error);
      if (callback && typeof callback === "function") {
        callback(false); // Indica fallo
      }
    },
  });
}

function stopSensor() {
  // console.log("Deteniendo sensor...");
  let token = localStorage.getItem("srnPc");
  let sessionSerial = $("#sessionSerial").val();

  $.ajax({
    type: "POST",
    url: "../src/cruds/endpoints.php",
    data: { token, condi: "detenerSensor", sessionSerial },
    dataType: "json",
    success: function (response) {
      // console.log("Respuesta del sensor:", response);
      if (response.status == 1) {
        // console.log("Sensor detenido correctamente");
      } else {
        simpleAlert(response.message);
        // console.error("Error al detener el sensor:", response.message);
      }
    },
    error: function (xhr, status, error) {
      // console.error("Error al intentar detener el sensor:", error);
    },
  });
}

function simpleAlert(mensaje) {
  iziToast.warning({
    title: "Advertencia",
    message: mensaje,
    position: "center",
  });
}
//end makings

function initializeDriver(stepsArray) {
  const driver = window.driver.js.driver;

  // Mapeamos el array proporcionado para crear la estructura necesaria para driver.js
  const steps = stepsArray.map((step) => ({
    element: step.element,
    popover: {
      title: step.title,
      description: step.description,
    },
  }));

  // Creamos el objeto driver con las configuraciones deseadas
  const driverObj = driver({
    nextBtnText: "Sig.",
    prevBtnText: "Ant.",
    doneBtnText: "Cerrar",
    showProgress: true,
    steps: steps,
  });

  return driverObj;
}

/**
 * VALIDACION DE CAMPOS GENERICOS
 */
function validarCamposGeneric(inputs, selects, radios) {
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
function inicializarValidacionAutomaticaGeneric(formSelector) {
  //  console.log("Inicializando validación automática para sss", formSelector);
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

function showHideElement(elementIds, action, option = 1) {
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
