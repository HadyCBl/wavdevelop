// import Alpine from "alpinejs";

// // Importa persist si lo necesitas en este módulo
// // import persist from '@alpinejs/persist'
// // Alpine.plugin(persist)

// window.Alpine = Alpine;
// Alpine.start();
// export default Alpine;

function getinputsval(datos) {
  const inputs2 = [""];
  var i = 0;
  while (i < datos.length) {
    inputs2[i] = document.getElementById(datos[i]).value;
    i++;
  }
  return inputs2;
}
function getselectsval(datos) {
  const selects2 = [""];
  let i = 0;
  while (i < datos.length) {
    var e = document.getElementById(datos[i]);
    selects2[i] = e.options[e.selectedIndex].value;
    i++;
  }
  return selects2;
}
function getradiosval(datos) {
  const radios2 = [""];
  let i = 0;
  while (i < datos.length) {
    radios2[i] = document.querySelector(
      'input[name="' + datos[i] + '"]:checked'
    ).value;
    i++;
  }
  return radios2;
}
function printdiv(condi, idiv, dir, xtra) {
  loaderefect(1);
  let dire = dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: {
      condi,
      xtra,
    },
    success: function (data) {
      // console.log(data);
      $(idiv).html(data);
      loaderefect(0);
    },
    error: function (xhr) {
      // console.log(xhr);
      loaderefect(0);
      const data2 = JSON.parse(xhr.responseText);
      if ("messagecontrol" in data2) {
        Swal.fire({
          icon: "error",
          title: "¡ERROR!",
          text: "Información de error: " + data2.mensaje,
        }).then(() => {});
        setTimeout(() => {
          location.reload();
        }, 2000);
      } else {
        console.log(xhr);
      }
    },
  });
}

function printdiv2(idiv, xtra) {
  loaderefect(1);
  let condi = $("#condi").val();
  let dir = $("#file").val();
  let dire = dir + ".php";
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
          location.reload();
        }, 2000);
      } else {
        console.log(xhr);
      }
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
  callback = "NULL",
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
//--
function generico(inputs, selects, radios, condi, id, archivo) {
  $.ajax({
    url: "functions/functions.php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
        printdiv2("#cuadro", id);
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
function reportes(
  datos,
  tipo,
  file,
  download,
  label = "NULL",
  columdata = "NULL",
  tipodata = 1,
  labeltitle = "",
  top = 1
) {
  loaderefect(1);
  var datosval = [];
  datosval[0] = getinputsval(datos[0]);
  datosval[1] = getselectsval(datos[1]);
  datosval[2] = getradiosval(datos[2]);
  datosval[3] = datos[3];
  var url = "reportes/" + file + ".php";
  $.ajax({
    url: url,
    async: true,
    type: "POST",
    dataType: "text",
    data: { datosval, tipo },
    success: function (data) {
      // console.log(data)
      loaderefect(0);
      var opResult = JSON.parse(data);
      // console.log(opResult)
      if (opResult.status == 1) {
        if (tipo == "show") {
          updatetable(opResult.data, opResult.encabezados, opResult.keys);
          builddata(opResult.data, label, columdata, tipodata, labeltitle, top);
        } else {
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
        }
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: opResult.mensaje });
      }
    },
    complete: function (data) {},
    error: function (xhr, status, error) {
      loaderefect(0);
      Swal.fire({
        icon: "error",
        title: "¡ERROR!",
        text: "Error en la solicitud AJAX: " + error,
      });
    },
  });
}

if (typeof window !== "undefined") {
  window.obtiene = obtiene;
  window.printdiv = printdiv;
  window.printdiv2 = printdiv2;
  window.reportes = reportes;
}
