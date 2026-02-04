//#region ajax generico
function printdiv2(idiv, xtra) {
  condi = $("#condi").val();
  dir = $("#file").val();
  dire = "otr_ingresos/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      $(idiv).html(data);
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function obtiene(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback,
  messageConfirm = false
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
        generico(inputs2, selects2, radios2, condi, id, archivo, callback);
      }
    });
  } else {
    generico(inputs2, selects2, radios2, condi, id, archivo, callback);
  }
}

// function obtiene(inputs, selects, radios, condi, id, archivo) {
//   var inputs2 = [];
//   var selects2 = [];
//   var radios2 = [];
//   inputs2 = getinputsval(inputs);
//   selects2 = getselectsval(selects);
//   radios2 = getradiosval(radios);
//   generico(inputs2, selects2, radios2, condi, id, archivo);
// }

//--------------------------------------------------------------
function generico(inputs, selects, radios, condi, id, archivo, callback) {
  $.ajax({
    url: "../src/cruds/crud_otrosGastos.php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      loaderefect(0);
      const data2 = JSON.parse(data);
      // console.log(data2);
      if (data2[1] == "1") {
        var reprint = "reprint" in data2 ? data2.reprint : 1;
        var timer = "timer" in data2 ? data2.timer : 60000;
        Swal.fire({
          icon: "success",
          title: "Muy Bien!",
          text: data2[0],
          timer: timer,
        });
        if (reprint == 1) {
          // printdiv2("#cuadro", id);
        }
        // Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
        if (condi === "ins_otrGasto" || condi === "act_otrGasto") {
          inyecCod("#tbOtrosG", "rep_otroGas");
          verEle(["#btnAct", "#btnCan"]);
          verEle(["#btnGua"], 1);
          $("#miForm")[0].reset();
        } else if (condi === "act_otrRecibo") {
          reportes([[], [], [], [data2[2]]], "pdf", "21", 0, 1);
          printdiv(
            "recibos_otros_ingresos",
            "#cuadro",
            "otros_ingresos_01",
            "0"
          );
        }
        if (typeof callback === "function") {
          callback(data2);
        }
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}
//Funcion para obtener reportes
function inyecCod(
  idElem,
  condi,
  extra = "0",
  url = "../views/otr_ingresos/inyecCod/inyecCod.php"
) {
  $.ajax({
    url: url,
    type: "POST",
    data: { condi, extra },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      $(idElem).html(data);
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function inyecEsp(
  condi,
  extra = "0",
  url = "../views/otr_ingresos/inyecCod/inyecCod.php"
) {
  return new Promise(function (resolve, reject) {
    $.ajax({
      url: url,
      type: "POST",
      data: { condi, extra },
      beforeSend: function () {
        loaderefect(1);
      },
    })
      .done(function (data) {
        resolve(data); // Resuelve la promesa con los datos recibidos
      })
      .fail(function (error) {
        reject(error); // Rechaza la promesa en caso de error
      })
      .always(function () {});
  });
}
//Funcion para eliminar
function eliminar(ideliminar, condi, archivo, xtra = "") {
  dire = "../src/cruds/crud_otrosGastos.php";
  Swal.fire({
    title: "¿ESTA SEGURO DE ELIMINAR?",
    showDenyButton: true,
    confirmButtonText: "Eliminar",
    denyButtonText: `Cancelar`,
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: dire,
        method: "POST",
        data: { condi, ideliminar, archivo },
        beforeSend: function () {
          loaderefect(0);
        },
        success: function (data) {
          const data2 = JSON.parse(data);
          if (data2[1] == "1") {
            Swal.fire("Correcto", "Eliminado", "success");
            if (condi === "eli_otrGasto") {
              inyecCod("#tbOtrosG", "rep_otroGas");
            } else if (condi === "eli_otrGasto1") {
              $("#tbRecibo " + xtra).remove();
              sum();
            } else {
              printdiv2("#cuadro", 0);
            }
          } else Swal.fire("Eliminado", data2[0], "error");
        },
        complete: function () {
          loaderefect(0);
        },
      });
    }
  });
}
//Funcion para capturar datos
function capData(dataJava, data, pos = []) {
  var data = data.split("||");

  if (pos.length == 0) dataPos = dataJava.length;
  else dataPos = pos.length;

  for (let i = 0; i < dataPos; i++) {
    if ($(dataJava[i]).is("input")) {
      $(dataJava[i]).val(data[i]);
    }
    if ($(dataJava[i]).is("label")) {
      $(dataJava[i]).text(data[i]);
    }
    if ($(dataJava[i]).is("textarea")) {
      $(dataJava[i]).val(data[i]);
    }
    if ($(dataJava[i]).is("select")) {
      $(dataJava[i]).val(data[i]);
    }
  }
}
//Funcion para capturar todo tipo de datos
function capEsp(data) {
  var con = 0;
  var datos = [];
  while (con < data.length) {
    if ($(dataJava[i]).is("input")) inputs2[i] = $(datos[i]).val();
    if ($(dataJava[i]).is("input")) inputs2[i] = $(datos[i]).val();
    if ($(dataJava[i]).is("input")) inputs2[i] = $(datos[i]).val();
    con++;
  }
}

//Escript especiales para cargar las paginas
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
//#region printdivs
function printdiv(condi, idiv, dir, xtra) {
  loaderefect(1);
  dire = "otr_ingresos/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      loaderefect(0);
      $(idiv).html(data);
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
          window.location.href = data2.url;
        }, 2000);
      } else {
        console.log(xhr);
      }
    },
  });
}
//para recargar en el mismo archivo, solo mandar id del cuadro y el extra
function printdiv2(idiv, xtra) {
  loaderefect(1);
  condi = $("#condi").val();
  dir = $("#file").val();
  dire = "otr_ingresos/" + dir + ".php";
  //nomcla = validarbancos();
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      loaderefect(0);
      $(idiv).html(data);
    },
  });
}

function dataTB(nameTb) {
  $(document).ready(function () {
    $("#otr_gastos").DataTable({
      order: [
        [0, "desc"],
        [1, "desc"],
      ],
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
  });
}
//Capturar los datos de una columna que le pertenece a una tabla.
function capDataTb(nameEle, tipo) {
  var elementos = document.querySelectorAll(
    "" + tipo + '[name="' + nameEle + '[]"]'
  );
  var valores = [];
  elementos.forEach(function (elemento) {
    if (tipo === "input") valores.push(elemento.value);
    if (tipo === "td") valores.push(elemento.textContent);
  });
  return valores;
}
//Genera la Matriz
function gMatriz(vacMaster) {
  // Obtener la cantidad de filas
  var filas = 0;
  for (var i = 0; i < vacMaster.length; i++) {
    var longitudVector = vacMaster[i].length;
    filas = Math.max(filas, longitudVector);
  }
  // Crear la matriz
  var matriz = [];
  // Generar la matriz automáticamente
  for (var i = 0; i < filas; i++) {
    var fila = [];
    for (var j = 0; j < vacMaster.length; j++) {
      fila.push(vacMaster[j][i] || null);
    }
    matriz.push(fila);
  }
  //console.log(matriz);
  return matriz;
}
//Valida si los coampos tienen informacion
function vaData(data) {
  //console.log(data);
  ban = 0;
  for (var con = 0; con < data.length; con++) {
    if ($(data[con]).val() == "") {
      ban += 1;
    }
  }
  if (ban > 0) {
    Swal.fire({
      icon: "info",
      title: "Error ",
      text: "Todos los campos son obligatorios, favor de ingresar la información. ",
    });
    return false;
  }
  if (ban == 0) return true;
}
//Oculatar o mostar elementos
function verEle(data, op = 0) {
  for (var con = 0; con < data.length; con++) {
    if (op == 0) $(data[con]).hide();
    if (op == 1) $(data[con]).show();
  }
}

function consultar_reporte(file, bandera) {
  return new Promise(function (resolve, reject) {
    if (bandera == 0) {
      resolve("Aprobado");
    }
    $.ajax({
      url: "../src/cruds/crud_otrosGastos.php",
      method: "POST",
      data: { condi: "consultar_reporte", id_descripcion: file },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        const data2 = JSON.parse(data);
        if (data2[1] == "1") {
          resolve(data2[2]);
        } else {
          reject(data2[0]);
        }
      },
      complete: function () {
        loaderefect(0);
      },
    });
  });
}

function reportes(datos, tipo, file, download, bandera = 0) {
  var datosval = [];
  datosval[0] = getinputsval(datos[0]);
  datosval[1] = getselectsval(datos[1]);
  datosval[2] = getradiosval(datos[2]);
  datosval[3] = datos[3];
  //CONSULTA PARA TRAER QUE REPORTE SE QUIERE
  consultar_reporte(file, bandera)
    .then(function (action) {
      //PARTE ENCARGADA DE GENERAR EL REPORTE
      if (bandera == 1) {
        file = action;
      } else {
        file = file;
      }

      var url = "otr_ingresos/reportes/" + file + ".php";
      $.ajax({
        url: url,
        async: true,
        type: "POST",
        dataType: "html",
        data: { datosval, tipo },
        beforeSend: function () {
          loaderefect(1);
        },
        success: function (data) {
          // console.log(data);
          var opResult = JSON.parse(data);
          // console.log(opResult);
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
    })
    .catch(function (error) {
      Swal.fire("Uff", error, "error");
    });
}

//METODOS PARA SUBIR FOTOS O ARCHIVOS DE INGRESOS
const LeerImagen = async (input) => {
  // Validación
  if (!input.files || !input.files[0]) {
    return;
  }
  // Leer archivo
  const file = input.files[0];
  const extension = file.name.split(".").pop();
  const reader = new FileReader();
  try {
    const res = await new Promise((resolve, reject) => {
      reader.onload = (e) => resolve(e.target.result);
      reader.onerror = (e) => reject(e);
      reader.readAsDataURL(file);
    });
    if (extension == "pdf") {
      // Mostrar vista previa
      $("#vistaPrevia").attr("src", "../includes/img/icon-pdf.png");
    } else {
      // Mostrar vista previa cuando es una imagen valida
      if (
        extension == "jpg" ||
        extension == "jpeg" ||
        extension == "pjpeg" ||
        extension == "png" ||
        extension == "gif"
      ) {
        $("#vistaPrevia").attr("src", res);
      } else {
        //Mostrar una imagen de not found cuando no es un formato valido
        $("#vistaPrevia").attr("src", "../includes/img/file_not_found.png");
      }
    }
  } catch (e) {
    Swal.fire({
      icon: "error",
      title: "¡ERROR!",
      text: "Error leyendo la imagen:" + e,
    });
    // console.error('Error leyendo la imagen', e);
  }
};

function CargarImagen(idinput, codigoimg) {
  // Verifica si se ha cargado un archivo
  var archivoInput = document.getElementById(idinput);
  if (archivoInput.files.length == 0) {
    Swal.fire({
      icon: "error",
      title: "¡ERROR!",
      text: "Debe seleccionar una imagen o foto",
    });
    return;
  }
  // Verifica si se tiene un código de cliente
  if (codigoimg == "") {
    Swal.fire({
      icon: "error",
      title: "¡ERROR!",
      text: "No se ha seleccionado un recurso",
    });
    return;
  }
  var archivo = archivoInput.files[0];
  // Datos de envío
  var datos = new FormData();
  datos.append("fileimg", archivo);
  datos.append("codimage", codigoimg);
  datos.append("condi", "cargar_imagen_ingreso");
  loaderefect(1);
  // Petición AJAX
  $.ajax({
    url: "../src/cruds/crud_otrosGastos.php",
    type: "POST",
    data: datos,
    processData: false,
    contentType: false,
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//Descargar imagen o pdf
function download_image_or_pdf(datos, condi, download) {
  var datosval = [];
  datosval[0] = getinputsval(datos[0]);
  datosval[1] = getselectsval(datos[1]);
  datosval[2] = getradiosval(datos[2]);
  datosval[3] = datos[3];
  var url = "../src/cruds/crud_otrosGastos.php";
  $.ajax({
    url: url,
    async: true,
    type: "POST",
    dataType: "html",
    data: { datosval, condi },
    success: function (data) {
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
                opResult.tipo +
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
        Swal.fire({ icon: "error", title: "¡ERROR!", text: opResult.mensaje });
      }
    },
  });
}
function showhide(ids, estados) {
  var estado = ["none", "block"];
  for (let i = 0; i < ids.length; i++) {
    document.getElementById(ids[i]).style.display = estado[estados[i]];
  }
}

function impuesto(estado) {
  const hidrocarburos = document.getElementById("imphidrocarburos");
  const cantgalon = document.getElementById("congalon");
  if (estado == "1") {
    // Mostrar el elemento y habilitarlo
    hidrocarburos.removeAttribute("hidden");
    hidrocarburos.removeAttribute("enabled");
    cantgalon.setAttribute("hidden", true);
  } else {
    // Ocultar el elemento y deshabilitarlo
    hidrocarburos.setAttribute("hidden", true);
    hidrocarburos.setAttribute("disabled", true);
    cantgalon.setAttribute("disabled", true);
  }
}

function impuestohidro(estado) {
  const hidrocarburos = document.getElementById("congalon");

  if (estado > 0) {
    // Mostrar el elemento y habilitarlo
    hidrocarburos.removeAttribute("hidden");
    hidrocarburos.removeAttribute("enabled");
  } else {
    // Ocultar el elemento y deshabilitarlo
    hidrocarburos.setAttribute("hidden", true);
    hidrocarburos.setAttribute("disabled", true);
  }
}

function orign() {
  const toggleSwitch = document.getElementById("toggleSwitch");
  const textLabel = document.getElementById("textLabel");
  const listban = document.getElementById("contbancos");

  if (toggleSwitch.checked) {
    // loaderefect(1);
    listban.removeAttribute("hidden");
    textLabel.textContent = "BANCOS";
    textLabel.style.color = "#4CAF50";
  } else {
    listban.setAttribute("hidden", true);
    textLabel.textContent = "CAJA";
    textLabel.style.color = "black";
  }
}
