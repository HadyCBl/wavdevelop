//LIMPIAR MODAL DE BENEFICIARIO
// function printdiv(condi, idiv, dir, xtra) {
//   // console.log(xtra);
//   loaderefect(1);
//   dire = "views/" + dir + ".php";
//   $.ajax({
//     url: dire,
//     method: "POST",
//     data: { condi, xtra },
//     success: function (data) {
//       loaderefect(0);
//       $(idiv).html(data);
//     },
//     error: function (xhr) {
//       loaderefect(0);
//       const data2 = JSON.parse(xhr.responseText);
//       if ("messagecontrol" in data2) {
//         Swal.fire({
//           icon: "error",
//           title: "¡ERROR!",
//           text: "Información de error: " + data2.mensaje,
//         }).then(() => {});
//         setTimeout(() => {
//           window.location.href = data2.url;
//         }, 2000);
//       } else {
//         console.log(xhr);
//       }
//     },
//   });
// }
// function condimodal() {
//   var condi = document.getElementById("condi").value;
//   return condi;
// }
// function filenow() {
//   var file = document.getElementById("file").value;
//   return file;
// }
// function printdiv2(idiv, xtra) {
//   loaderefect(1);
//   condi = condimodal();
//   dir = filenow();
//   dire = dir + ".php";
//   $.ajax({
//     url: dire,
//     method: "POST",
//     data: { condi, xtra },
//     success: function (data) {
//       loaderefect(0);
//       $(idiv).html(data);
//     },
//     error: function (xhr) {
//       loaderefect(0);
//       const data2 = JSON.parse(xhr.responseText);
//       if ("messagecontrol" in data2) {
//         Swal.fire({
//           icon: "error",
//           title: "¡ERROR!",
//           text: "Información de error: " + data2.mensaje,
//         }).then(() => {});
//         setTimeout(() => {
//           window.location.href = data2.url;
//         }, 2000);
//       } else {
//         console.log(xhr);
//       }
//     },
//   });
// }
// function getinputsval(datos) {
//   const inputs2 = {};
//   var i = 0;
//   while (i < datos.length) {
//     inputs2[datos[i]] = document.getElementById(datos[i]).value;
//     i++;
//   }
//   return inputs2;
// }

// function getselectsval(datos) {
//   const selects2 = {};
//   var i = 0;
//   while (i < datos.length) {
//     var e = document.getElementById(datos[i]);
//     selects2[datos[i]] = e.options[e.selectedIndex].value;
//     i++;
//   }
//   return selects2;
// }

// function getradiosval(datos) {
//   const radios2 = {};
//   var i = 0;
//   while (i < datos.length) {
//     radios2[datos[i]] = document.querySelector(
//       'input[name="' + datos[i] + '"]:checked'
//     ).value;
//     i++;
//   }
//   return radios2;
// }

// function obtiene(
//   inputs,
//   selects,
//   radios,
//   condi,
//   id,
//   archivo,
//   callback,
//   confirmacion = false,
//   mensaje = "¿Desea continuar con el proceso?",
//   fileDestino = "src/cruds/kpi.php"
// ) {
//   const validacion = validarCampos(inputs, selects, radios);

//   if (!validacion.esValido) {
//     return false;
//   }

//   var inputs2 = [];
//   var selects2 = [];
//   var radios2 = [];
//   inputs2 = getinputsval(inputs);
//   selects2 = getselectsval(selects);
//   radios2 = getradiosval(radios);

//   if (confirmacion) {
//     Swal.fire({
//       title: "Confirmación",
//       text: mensaje,
//       icon: "warning",
//       showCancelButton: true,
//       confirmButtonText: "Sí, continuar",
//       cancelButtonText: "Cancelar",
//     }).then((result) => {
//       if (result.isConfirmed) {
//         generico(
//           inputs2,
//           selects2,
//           radios2,
//           condi,
//           id,
//           archivo,
//           callback,
//           fileDestino
//         );
//       }
//     });
//   } else {
//     generico(
//       inputs2,
//       selects2,
//       radios2,
//       condi,
//       id,
//       archivo,
//       callback,
//       fileDestino
//     );
//   }
// }

// function generico(
//   inputs,
//   selects,
//   radios,
//   condi,
//   id,
//   archivo,
//   callback,
//   fileDestino
// ) {
//   // console.log(fileDestino)
//   $.ajax({
//     url: fileDestino,
//     method: "POST",
//     data: { inputs, selects, radios, condi, id, archivo },
//     beforeSend: function () {
//       loaderefect(1);
//     },
//     success: function (data) {
//       // console.log(data);
//       const data2 = JSON.parse(data);
//       if (data2.status == 1) {
//         Swal.fire({
//           icon: "success",
//           title: "Muy Bien!",
//           text: data2.message,
//         });
//         printdiv2("#cuadro", id);

//         if (typeof callback === "function") {
//           callback(data2);
//         }
//       } else {
//         var reprint = "reprint" in data2 ? data2.reprint : 0;
//         var timer = "timer" in data2 ? data2.timer : 60000;
//         Swal.fire({
//           icon: "error",
//           title: "¡ERROR!",
//           text: data2.message,
//           timer: timer,
//         });
//         if (reprint == 1) {
//           setTimeout(function () {
//             printdiv2("#cuadro", id);
//           }, 1500);
//         }
//       }
//     },
//     complete: function () {
//       loaderefect(0);
//     },
//   });
// }

// function validarCampos(inputs, selects, radios) {
//   let errores = [];

//   // Función helper para agregar mensaje de error
//   function agregarMensajeError(elemento, mensaje) {
//     // Eliminar mensaje anterior si existe
//     const feedbackAnterior = elemento.nextElementSibling;
//     if (
//       feedbackAnterior &&
//       feedbackAnterior.classList.contains("error-message")
//     ) {
//       feedbackAnterior.remove();
//     }

//     // Crear y agregar nuevo mensaje
//     const feedbackDiv = document.createElement("p");
//     feedbackDiv.className = "error-message text-theme-xs text-error-500 mt-1.5";
//     feedbackDiv.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i>${mensaje}`;

//     // Agregar clases de error al elemento
//     elemento.classList.add(
//       "border-error-300",
//       "focus:border-error-300",
//       "focus:ring-error-500/10",
//       "dark:border-error-700",
//       "dark:focus:border-error-800"
//     );

//     // Insertar mensaje después del elemento
//     elemento.parentNode.insertBefore(feedbackDiv, elemento.nextSibling);

//     return mensaje;
//   }

//   // Función helper para limpiar error
//   function limpiarError(elemento) {
//     // Remover clases de error
//     elemento.classList.remove(
//       "border-error-300",
//       "focus:border-error-300",
//       "focus:ring-error-500/10",
//       "dark:border-error-700",
//       "dark:focus:border-error-800"
//     );

//     // Eliminar mensaje de error si existe
//     const feedbackDiv = elemento.nextElementSibling;
//     if (feedbackDiv && feedbackDiv.classList.contains("error-message")) {
//       feedbackDiv.remove();
//     }
//   }

//   // Validar inputs
//   inputs.forEach((input) => {
//     const elemento = document.getElementById(input);
//     if (elemento) {
//       // Verifica si el campo es requerido
//       if (elemento.hasAttribute("required")) {
//         if (!elemento.value.trim()) {
//           errores.push(
//             agregarMensajeError(
//               elemento,
//               `El campo ${elemento.getAttribute("data-label") || input} es obligatorio`
//             )
//           );
//         } else {
//           limpiarError(elemento);
//         }
//       }

//       // Validar tipo email
//       if (elemento.type === "email" && elemento.value) {
//         const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
//         if (!emailRegex.test(elemento.value)) {
//           errores.push(
//             agregarMensajeError(elemento, `Ingrese un email válido`)
//           );
//         }
//       }

//       // Validar números
//       if (elemento.type === "number" && elemento.value) {
//         const min = elemento.getAttribute("min");
//         const max = elemento.getAttribute("max");
//         const valor = parseFloat(elemento.value);

//         if (min && valor < parseFloat(min)) {
//           errores.push(
//             agregarMensajeError(elemento, `El valor mínimo es ${min}`)
//           );
//         }
//         if (max && valor > parseFloat(max)) {
//           errores.push(
//             agregarMensajeError(elemento, `El valor máximo es ${max}`)
//           );
//         }
//       }
//     }
//   });

//   // Validar selects
//   selects.forEach((select) => {
//     const elemento = document.getElementById(select);
//     if (elemento && elemento.hasAttribute("required")) {
//       if (elemento.value === "0" || elemento.value === "") {
//         errores.push(agregarMensajeError(elemento, `Seleccione una opción`));
//       } else {
//         limpiarError(elemento);
//       }
//     }
//   });

//   // Validar radios
//   radios.forEach((radio) => {
//     const elementos = document.getElementsByName(radio);
//     if (elementos.length > 0 && elementos[0].hasAttribute("required")) {
//       let checked = false;
//       elementos.forEach((el) => {
//         if (el.checked) checked = true;
//       });
//       if (!checked) {
//         // Para radios, agregamos el mensaje después del último radio
//         errores.push(
//           agregarMensajeError(
//             elementos[elementos.length - 1],
//             `Seleccione una opción`
//           )
//         );
//       } else {
//         elementos.forEach((el) => limpiarError(el));
//       }
//     }
//   });

//   return {
//     esValido: errores.length === 0,
//     errores: errores,
//   };
// }

// function reportes(
//   datos,
//   tipo,
//   file,
//   download = 1,
//   bandera = 0,
//   modulo = "kpi"
// ) {
//   var datosval = [];
//   datosval[0] = getinputsval(datos[0]);
//   datosval[1] = getselectsval(datos[1]);
//   datosval[2] = getradiosval(datos[2]);
//   datosval[3] = datos[3];
//   var url = `./${modulo}/reportes/${file}.php`;
//   $.ajax({
//     url: url,
//     async: true,
//     type: "POST",
//     dataType: "html", //html
//     contentType: "application/x-www-form-urlencoded",
//     data: { datosval, tipo },
//     beforeSend: function () {
//       loaderefect(1);
//     },
//     success: function (data) {
//       // console.log(data);
//       var opResult = JSON.parse(data);
//       if (opResult.status == 1) {
//         switch (download) {
//           case 0:
//             const ventana = window.open();
//             ventana.document.write(
//               "<object data='" +
//                 opResult.data +
//                 "' type='application/" +
//                 opResult.tipo +
//                 "' width='100%' height='100%'></object>"
//             );
//             break;
//           case 1:
//             var $a = $(
//               "<a href='" +
//                 opResult.data +
//                 "' download='" +
//                 opResult.namefile +
//                 "." +
//                 tipo +
//                 "'>"
//             );
//             $("body").append($a);
//             $a[0].click();
//             $a.remove();
//             break;
//         }
//         Swal.fire({
//           icon: "success",
//           title: "Muy Bien!",
//           text: opResult.mensaje,
//         });
//       } else {
//         Swal.fire({ icon: "error", title: "¡ERROR!", text: opResult.mensaje });
//       }
//     },
//     complete: function () {
//       loaderefect(0);
//     },
//   });
//   //-------------------------------------FIN SEGUNDA FUNCION
// }
