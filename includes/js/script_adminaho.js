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
  dire = "views/ahorros/" + dir + ".php";
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
          icon: 'error',
          title: '¡ERROR!',
          text: 'Información de error: ' + data2.mensaje
        }).then(() => {

        });
        setTimeout(() => {
          location.reload();
        }, 2000);
      }
      else {
        console.log(xhr);
      }
    }
  })
}
//para recargar en el mismo archivo, solo mandar id del cuadro y el extra
function printdiv2(idiv, xtra) {
    loaderefect(1);
    condi = $("#condi").val();
    dir = $("#file").val();
    dire = "views/ahorros/" + dir + ".php";
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
    i = 0;
    while (i < datos.length) {
        var e = document.getElementById(datos[i]);
        selects2[i] = e.options[e.selectedIndex].value;
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
//#endregion
//#region ajax generico
function obtiene(inputs, selects, radios, condi, id, archivo, callback = "NULL", messageConfirm = false) {
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
function generico(inputs, selects, radios, condi, id, archivo, callback) {
    $.ajax({
        url: "../../src/cruds/crud_ahorro.php",
        method: "POST",
        data: { inputs, selects, radios, condi, id, archivo },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            // console.log(data2);
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