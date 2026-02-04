function KillRowAux() {
  con = 0;
  while (codCu[con] != null) {
    //console.log('Dato encontrado '+codCu[con])
    killFila(codCu[con]);
    con++;
  }
}

//Funcion para eliminar una fila de plan de pago
function killFila(nametb) {
  var tabla = document.getElementById(nametb);
  var filas = tabla.getElementsByTagName("tr");
  var noFila = filas.length - 1;

  if (noFila != 1) {
    tabla.deleteRow(noFila);
    calPlanDePago(nametb);
  }
}
function salir() {
  $(location).attr("href", "index.php");
}

function actMasiva(vecGeneral, condi, extra) {
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    type: "POST",
    data: { vecGeneral, condi, extra },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == 1) {
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[1] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//Funcion para eliminar uan fila y los datos en la base datos
function eliminarFila(ideliminar, condi, archivo = 0) {
  dire = "../../src/cruds/crud_credito_indi.php";
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
        data: { condi, ideliminar },
        beforeSend: function () {
          loaderefect(1);
        },
        success: function (data) {
          const data2 = JSON.parse(data);

          if (data2[1] == "1") {
            KillRowAux();
            Swal.fire("Correcto", "Eliminado", "success");
            var res = result.isConfirmed;
            return res;
          } else Swal.fire("X(", data2[0], "error");
        },
        complete: function () {
          loaderefect(0);
        },
      });
    } else {
      var res = result.isConfirmed;
      return res;
    }
  });
}

function inyecCod(
  condi,
  extra = "0",
  url = "../../views/Creditos/cre_grupo/inyecCod/inyecCod.php"
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

$(document).ready(function () {
  loaderefect(0);
});

//#region printdivs
function printdiv(condi, idiv, dir, xtra) {
  loaderefect(1);
  dire = "./cre_grupo/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      $(idiv).html(data);
      loaderefect(0);
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

function printdiv2(idiv, xtra) {
  loaderefect(1);
  condi = $("#condi").val();
  dir = $("#file").val();
  dire = "cre_grupo/" + dir + ".php";
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
function printdiv5(id_hidden, valores) {
  //ver si sacar el dato de un idhidden o directamente un toString
  var cadena = id_hidden.substr(0, 1);
  if (cadena == "#") {
    //todo el input
    var todo = $(id_hidden).val().split("/");
  } else {
    //todo la cadena
    var todo = id_hidden.split("/");
  }

  //se extraen los nombres de los inputs
  var nomInputs = todo[0].toString().split(",");
  //se extraen los rangos
  var rangos = todo[1].toString().split(",");
  //se extrae el separador
  var separador = todo[2].toString();

  //todo lo relacionado a la habilitacion o deshabilitacion
  var habilitar = [];
  var deshabilitar = [];
  if (todo[3].toString() != "#") {
    habilitar = todo[3].toString().split(",");
  }
  if (todo[4].toString() != "#") {
    deshabilitar = todo[4].toString().split(",");
  }
  habilitar_deshabilitar(habilitar, deshabilitar);
  //----fin de la habilitacion y deshabilitacion

  //todo lo relacionado con show y hide de elementos
  var mostrar = [];
  var ocultar = [];
  if (todo[5].toString() != "#") {
    mostrar = todo[5].toString().split(",");
  }
  if (todo[6].toString() != "#") {
    ocultar = todo[6].toString().split(",");
  }
  mostrar_nomostrar(mostrar, ocultar);
  //fin de los elementos hidden o visible

  // tratar de validar o unir campos para mandarlos a un solo input
  var contador = 0;
  for (var index = 0; index < nomInputs.length; index++) {
    if (rangos[index] !== "A") {
      var aux = rangos[index].toString();
      var arrayaux = aux.split("-");
      var concatenacion = "";
      for (var index2 = arrayaux[0]; index2 <= arrayaux[1]; index2++) {
        if (index2 === arrayaux[0]) {
          concatenacion = concatenacion + valores[index2 - 1];
        } else {
          concatenacion = concatenacion + " " + separador + " ";
          concatenacion = concatenacion + valores[index2 - 1];
        }
        contador++;
      }
      if (nomInputs[index] != "") {
        $("#" + nomInputs[index]).val(concatenacion);
      }
    } else {
      if (nomInputs[index] != "") {
        $("#" + nomInputs[index]).val(valores[contador]);
      }
      contador++;
    }
  }
}
function abrir_modal(id_modal, id_hidden, dato) {
  $(id_modal).modal("show");
  $(id_hidden).val(dato);
}

function abrir_modal_for_delete(id_modal, id_hidden, dato) {
  $(id_modal).modal("show");
  $(id_hidden).val(dato);
  console.log(id_modal, id_hidden, dato);
  return;
}

function select_item(id_hidden, valores) {
  printdiv5(id_hidden, valores);
}

function cerrar_modal(id_modal, estado, id_hidden) {
  $(id_modal).modal(estado);
  $(id_hidden).val("");
}
//#endregion
//#region beneq
function opencollapse(i) {
  event.stopPropagation();
  if (i >= 0) {
    if ($("#collaps" + i).hasClass("collapse")) {
      $(".accordion-collapse").addClass("collapse");
      $("#collaps" + i).removeClass("collapse");
    } else {
      $(".accordion-collapse").addClass("collapse");
    }
  }
  if (i.toString().substring(0, 1) == "s") {
    if ($("#" + i).is(":checked")) {
      changedisabled("#bt" + i.substring(1, 2) + " .form-control", 1);
    } else {
      changedisabled("#bt" + i.substring(1, 2) + " .form-control", 0);
    }
  }
}
function changedisabled(padre, status) {
  if (status == 0) $(padre).attr("disabled", "disabled");
  else $(padre).removeAttr("disabled");
}
function mostrar_nomostrar(mostrar, ocultar) {
  var i = 0;
  while (i < mostrar.length) {
    document.getElementById(mostrar[i]).style.display = "block";
    i++;
  }
  var i = 0;
  while (i < ocultar.length) {
    document.getElementById(ocultar[i]).style.display = "none";
    i++;
  }
}
function habilitar_deshabilitar(hab, des) {
  var i = 0;
  while (i < hab.length) {
    document.getElementById(hab[i]).disabled = false;
    i++;
  }
  var i = 0;
  while (i < des.length) {
    document.getElementById(des[i]).disabled = true;
    i++;
  }
}
function obtiene(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback = null,
  messageConfirm = false
) {
  // loaderefect(1);
  var inputs2 = [];
  var selects2 = [];
  var radios2 = [];
  inputs2 = getinputsval(inputs);
  selects2 = getselectsval(selects);
  radios2 = getradiosval(radios);
  // generico(inputs2, selects2, radios2, condi, id, archivo, "crud_credito");
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
          "crud_credito",
          callback
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
      "crud_credito",
      callback
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
  filecrud,
  callback = null
) {
  $.ajax({
    url: "../../src/cruds/" + filecrud + ".php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      // console.log(data);
      const data2 = JSON.parse(data);
      // console.log(data2)
      if (data2[1] == "1") {
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
        var reprint = "reprint" in data2 ? data2.reprint : 1;
        if (reprint == 1) {
          printdiv2("#cuadro", id);
        }
        // if (condi == "desemgrupal") {
        //   printdiv("comprobantechq", "#cuadro", "grup001", [
        //     archivo[0],
        //     archivo[1],
        //     data2[2],
        //     data2[3],
        //     inputs[1][6],
        //   ]);
        // }
        if (condi == "analgrupal")
          reportes([[], [], [], [id[0], id[1]]], `pdf`, `ficha_analisis`, 0);
        if (condi == "aprobgrupal")
          reportes(
            [[], [], [], [archivo[0], archivo[1]]],
            `pdf`,
            `ficha_aprobacion`,
            0
          );
        if (condi == "soligrupal")
          reportes(
            [[], [], [], [archivo[1], inputs[1][0]]],
            `pdf`,
            `ficha_solicitud`,
            0
          );
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

function consultar_reporte(file, bandera) {
  return new Promise(function (resolve, reject) {
    if (bandera == 0) {
      resolve("Aprobado");
    }
    $.ajax({
      url: "../../src/cruds/crud_credito.php",
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
  fileaux = file;
  consultar_reporte(file, bandera)
    .then(function (action) {
      //PARTE ENCARGADA DE GENERAR EL REPORTE
      if (bandera == 1) {
        file = action;
      } else {
        file = file;
      }
      //INICIO DE REPORTE
      if (fileaux == 18 || fileaux == 19 || fileaux == 20 || fileaux == 40) {
        var url = "cre_indi/reportes/" + file + ".php";
      } else if (fileaux == 13) {
        var url = "../bancos/reportes/" + file + ".php";
      } else {
        var url = "cre_grupo/reportes/" + file + ".php";
      }
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
          var opResult = JSON.parse(data);
          //console.log(opResult)
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
      //FIN DE REPORTE
    })
    .catch(function (error) {
      Swal.fire("Uff", error, "error");
    });
}

function savesol(cant, user, idgrup, idofi) {
  var datainputs = [];
  var rows = 0;
  var filas = [];
  while (rows <= cant) {
    filas = getinputsval([
      "ccodcli" + rows,
      "monsol" + rows,
      "descre" + rows,
      "sectorecono" + rows,
      "actecono" + rows,
    ]);
    datainputs[rows] = filas;
    rows++;
  }
  datadetal = getinputsval(["nciclo", "codanal"]);
  generico(
    [datainputs, datadetal],
    0,
    0,
    "soligrupal",
    [0],
    [user, idgrup, idofi],
    "crud_credito"
  );
}
function saveanal(cant, ciclo, idgrup, idofi) {
  var datainputs = [];
  var rows = 0;
  var filas = [];
  while (rows <= cant) {
    filas = getinputsval(["ccodcta" + rows, "monapr" + rows]);
    datainputs[rows] = filas;
    rows++;
  }
  datadetal = getinputsval([
    "idprod",
    "maxprod",
    "tipcre",
    "peri",
    "fecinit",
    "nrocuo",
    "fecdes",
    "dictmn",
    "tasaprod",
  ]);
  generico(
    [datainputs, datadetal],
    0,
    0,
    "analgrupal",
    [idgrup, ciclo],
    [idofi],
    "crud_credito"
  );
}
function saveapro(cant, idgrupo, nciclo) {
  var datainputs = [];
  var rows = 0;
  var filas = [];
  while (rows <= cant) {
    filas = getinputsval(["ccodcta" + rows]);
    datainputs[rows] = filas;
    rows++;
  }
  generico(
    datainputs,
    0,
    0,
    "aprobgrupal",
    [0, 0],
    [idgrupo, nciclo],
    "crud_credito"
  );
}

let numOr0 = (n) => (isNaN(parseFloat(n)) ? 0 : parseFloat(n));
function summon(id) {
  let rows = id.substring(7, 9);
  let filas = getinputsval([
    "capital" + rows,
    "interes" + rows,
    "monmora" + rows,
    "ahorrop" + rows,
    "otrospg" + rows,
  ]);
  $("#totalpg" + rows).val(filas.reduce((a, b) => numOr0(a) + numOr0(b)));
  var i = 0;
  let filtot = [];
  while (i != -1) {
    filtot[i] = getinputsval(["totalpg" + i]);
    i = !!document.getElementById("totalpg" + (i + 1)) ? i + 1 : -1;
  }
  $("#totalgen").val(filtot.reduce((a, b) => numOr0(a) + numOr0(b)));
}
// function summongas(nocuenta, id) {
//   var i = 0;
//   let filtot = [];
//   while (i != -1) {
//     filtot[i] = getinputsval(["mon_" + i + "_" + nocuenta]);
//     i = !!document.getElementById("mon_" + (i + 1) + "_" + nocuenta)
//       ? i + 1
//       : -1;
//   }
//   let gastos = parseFloat(
//     filtot.reduce((a, b) => numOr0(a) + numOr0(b), 0).toFixed(2)
//   );
//   let capital = $("#monapr" + id).val();
//   $("#mondesc" + id).val(gastos);
//   $("#monentrega" + id).val((capital - gastos).toFixed(2));
//   sugeneral();
// }
// function sugeneral() {
//   let sum = 0;
//   let i = 0;
//   while (true) {
//     const input = document.getElementById("monentrega" + i);
//     if (input) {
//       sum += parseFloat(input.value) || 0;
//       //console.log('Valor de input ' + i + ':', input.value);
//       i++;
//     } else {
//       break;
//     }
//   }
//   const totalInput = document.getElementById("montogrupal");
//   if (totalInput) {
//     totalInput.value = sum;
//   }
//   //console.log('Sumatoria total:', sum);
//   //ENVIAR LOS DATOS A SESSION
//   // $.ajax({
//   //     url: "../../src/cruds/crud_credito_indi.php",
//   //     method: "POST",
//   //     data: { 'condi': 'cargmonchq', 'total': sum},
//   //     beforeSend: function () {
//   //         loaderefect(1);
//   //     },
//   //     success: function (data) {
//   //         // console.log('MONTO ENVIADO')

//   //     },
//   //     complete: function () {
//   //         loaderefect(0);
//   //     }
//   // })
// }

//#endregion
function creperi(condi, idiv, dir, xtra) {
  dire = "../Creditos/cre_indi/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      $(idiv).html(data);
    },
  });
  printdiv("prdscre", "#peri", "../cre_indi/" + dir, xtra);
}
function showhide(seleccion) {
  var data = ["none", "block"];
  document.getElementById("region_cheque").style.display = data[seleccion - 1];
  const nodeList = document.getElementsByClassName("classchq");
  for (let i = 0; i < nodeList.length; i++) {
    nodeList[i].style.display = data[seleccion - 1];
  }
}
function showopp(seleccion) {
  var data = ["none", "block"];
  document.getElementById("grupind").style.display = data[seleccion - 1];
}
function showgrup(seleccion) {
  var data = ["none", "block"];
  var data2 = ["block", "none"];
  document.getElementById("region_grupo").style.display = data[seleccion - 1];
  const nodeList = document.getElementsByClassName("grup2");
  // const input = document.getElementById("flaggrup");
  // input.value = seleccion;
  for (let i = 0; i < nodeList.length; i++) {
    nodeList[i].style.display = data2[seleccion - 1];
  }
}
function buscar_cuentas() {
  idbanco = document.getElementById("bancoid").value;
  //consultar a la base de datos
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "buscar_cuentas", id: idbanco },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        $("#cuentaid").empty();
        for (var i = 0; i < data2[2].length; i++) {
          $("#cuentaid").append(
            "<option value='" +
              data2[2][i]["id"] +
              "'>" +
              data2[2][i]["numcuenta"] +
              "</option>"
          );
        }
      } else {
        $("#cuentaid").empty();
        $("#cuentaid").append(
          "<option value='0'>Seleccione una cuenta</option>"
        );
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}
//MOSTRAR GASTOS EN LA TABLA
function mostrar_tabla_gastos(codcredito, id) {
  $("#tabla_gastos_desembolso" + id)
    .on("search.dt")
    .DataTable({
      searching: false,
      paging: false,
      aProcessing: true,
      aServerSide: true,
      ordering: false,
      lengthMenu: [
        [10, 20, 30, -1],
        ["5 filas", "10 filas", "15 filas", "Mostrar todos"],
      ],
      ajax: {
        url: "../../src/cruds/crud_credito_indi.php",
        type: "POST",
        beforeSend: function () {
          loaderefect(1);
        },
        data: {
          condi: "lista_gastos_grupo",
          id: codcredito,
          filcuenta: id,
        },
        dataType: "json",
        complete: function (data) {
          // console.log(data);
          loaderefect(0);
        },
      },
      bDestroy: true,
      iDisplayLength: 10,
      order: [[1, "desc"]],
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

//CONSULTAR GASTOS ADMINISTRATIVOS
function consultar_gastos_monto(codcredito, id) {
  //consultar a la base de datos
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "gastos_desembolsos_grupo", id: codcredito },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      //console.log(data2);
      if (data2[1] == "1") {
        //imprimir en los inputs
        $("#monapr" + id).val(data2[2]);
        $("#mondesc" + id).val(data2[3]);
        $("#monentrega" + id).val(data2[4]);
        //cantidad_a_letras(data2[4]);
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

// para OBTENER LOS VALORES de ACTI/SECTOR ECONO
function SctrEcono(id, dtass, agregado) {
  var condi = "SctrEcono";
  //  alert (dtass);
  $.ajax({
    url: "../../src/general.php",
    method: "POST",
    data: { dtass, condi },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      $(id).html(data);
      $(agregado).val("");
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//------------------------------- CHEQUE POR GRUPO

function obtenerValor(dat) {
  // Obtener el elemento span por su id
  const input = document.getElementById("miInput");
  // Asignar un nuevo valor
  input.value = dat;
}

function buscar_cargos() {
  codgrupo = document.getElementById("idgrupo").value;
  //consultar a la base de datos
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "buscar_cargos", id: codgrupo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        $("#cargoid").empty();
        $("#cargoid").append("<option value='0'>Seleccione un cargo</option>");
        for (var i = 0; i < data2[2].length; i++) {
          $("#cargoid").append(
            "<option value='" +
              data2[2][i]["short_name"] +
              "'>" +
              data2[2][i]["nombre"] +
              " - " +
              data2[2][i]["short_name"] +
              "</option>"
          );
        }
      } else {
        $("#cargoid").empty();
        $("#cargoid").append("<option value='0'>Seleccione un cargo</option>");
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function cconcepto() {
  concepto = document.getElementById("cargoid").value;
  grupo = document.getElementById("nongrupo").textContent;
  const input = document.getElementById("conceptogrupal");
  input.value =
    "DESEMBOLSO DE CRÉDITO A NOMBRE DE " + concepto + " - GRUPO " + grupo;
}
function camcheq(cont) {
  chequegeneral = document.getElementById("cheqgeneral").value;
  concepto = document.getElementById("desgrupal").value;
  flaggg = document.getElementById("flaggrup").value;
  concepto = document.getElementById("cargoid").value;
  let i = 0;
  while (i < cont) {
    let na = "numcheque" + i;
    const input = document.getElementById(na);
    input.value = chequegeneral;
    i++;
  }
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: {
      condi: "dattemp",
      nocheque: chequegeneral,
      concepto: concepto,
      flaggrup: flaggg,
    },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      // console.log('DATOS ENVIADOS')
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//Inicia la funcion de data table
//data table
function inicializarDataTable(idTabla) {
  $("#" + idTabla).DataTable({
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
}

//-----------------------------------------------------------------------------------------------------------------------------------------

function agregarPersona() {
  const nameInput = document.getElementById("name");
  const montoInput = document.getElementById("monto");
  const tabla = document.getElementById("tabla-detalles");
  const tablaBody = document.getElementById("tabla-body");

  const nombre = nameInput.value.trim();
  const monto = parseFloat(montoInput.value);

  // Validar campos
  if (nombre === "" || isNaN(monto) || monto <= 0) {
    Swal.fire({
      icon: "info",
      title: "Atencion",
      text: "Por favor, ingresa un nombre válido y un monto mayor a 0.",
      confirmButtonText: "Aceptar",
    });
    return;
  }

  // Mostrar la tabla si está oculta
  tabla.style.display = "table";

  // Crear una nueva fila con los datos
  const fila = document.createElement("tr");
  fila.innerHTML = `
        <td class="nombre">${nombre}</td>
        <td class="monto">Q${monto.toLocaleString("es-GT", { minimumFractionDigits: 2 })}</td>
        <td>
            <button class="btn btn-danger" onclick="eliminarPersona(this)">Eliminar</button>
            <button class="btn btn-primary" onclick="PlanIndi('${nombre}', ${monto})">Plan de Pago</button>
        </td>
    `;
  tablaBody.appendChild(fila);

  // Limpiar los campos
  nameInput.value = "";
  montoInput.value = "";

  // Actualizar el total
  actualizarTotal();
}
// Función para eliminar una fila de la tabla
function eliminarPersona(button) {
  const fila = button.parentElement.parentElement;
  fila.remove();
  actualizarTotal();
}

// Función para calcular y actualizar el total
function actualizarTotal() {
  const tablaBody = document.getElementById("tabla-body");
  const filas = tablaBody.getElementsByTagName("tr");
  let total = 0;

  // Sumar todos los montos de la tabla
  for (let fila of filas) {
    const montoTexto = fila.cells[1].textContent
      .replace("Q", "")
      .replace(/,/g, "");
    const monto = parseFloat(montoTexto);
    if (!isNaN(monto)) {
      total += monto;
    }
  }

  // Actualizar el valor del total en formato contable de Guatemala
  const totalMonto = document.getElementById("total-monto");
  totalMonto.textContent = `Q${total.toLocaleString("es-GT", { minimumFractionDigits: 2 })}`;

  // Ocultar la tabla si no hay filas
  const tabla = document.getElementById("tabla-detalles");
  if (filas.length === 0) {
    tabla.style.display = "none";
  }
}

//---------------------------------------------------------------------------------------------------------------------------------------------
function recolectarInformacion() {
  const tablaDetalles = document.querySelector("#tabla-detalles tbody");
  const filas = tablaDetalles.querySelectorAll("tr");
  const tipoCredito = document.getElementById("tipcre").value;
  const tipoPeriodo = document.getElementById("peri").value;
  const fechaCuota = document.getElementById("fecinit").value;
  const nroCuotas = parseInt(document.getElementById("nrocuo").value, 10);
  const fechaDesembolso = document.getElementById("fecdes").value;

  // Recolectar el código del producto y % de interés asignado
  const codigoProducto = document.getElementById("codprod").value;
  const interesAsignado = parseFloat(document.getElementById("tasaprod").value);

  let datosGrupo = [];
  let errores = [];

  // Validar y recolectar información de las filas
  filas.forEach((fila, index) => {
    const nombre = fila.querySelector(".nombre").textContent.trim();
    const monto = parseFloat(
      fila.querySelector(".monto").textContent.replace(/[^\d.-]/g, "")
    );

    if (!nombre) {
      errores.push(`La fila ${index + 1} no tiene un nombre válido.`);
    }
    if (isNaN(monto) || monto <= 0) {
      errores.push(`El monto de la fila ${index + 1} no es válido.`);
    }

    datosGrupo.push({ nombre, monto });
  });

  // Validaciones adicionales
  if (datosGrupo.length < 2) {
    errores.push("Debe haber al menos 2 personas en el grupo.");
  }
  if (!tipoCredito || tipoCredito === "0") {
    errores.push("Debe seleccionar un tipo de crédito.");
  }
  if (!tipoPeriodo || tipoPeriodo === "0") {
    errores.push("Debe seleccionar un tipo de período.");
  }
  if (!fechaCuota) {
    errores.push("Debe ingresar la fecha de la primera cuota.");
  }
  if (!nroCuotas || nroCuotas <= 0) {
    errores.push("Debe ingresar un número válido de cuotas.");
  }
  if (!fechaDesembolso) {
    errores.push("Debe ingresar la fecha de desembolso.");
  }
  if (!codigoProducto || codigoProducto === "") {
    errores.push("Debe seleccionar un código de producto.");
  }
  if (isNaN(interesAsignado) || interesAsignado <= 0) {
    errores.push("Debe ingresar un porcentaje de interés válido.");
  }

  if (fechaCuota && fechaDesembolso) {
    const fechaCuotaDate = new Date(fechaCuota);
    const fechaDesembolsoDate = new Date(fechaDesembolso);

    if (fechaDesembolsoDate > fechaCuotaDate) {
      errores.push(
        "La fecha de desembolso no puede ser menor a la fecha de la primera cuota."
      );
    }
  }

  // Mostrar errores si los hay usando Swal
  if (errores.length > 0) {
    Swal.fire({
      icon: "error",
      title: "Errores encontrados",
      text: errores.join("\n"),
      confirmButtonText: "Aceptar",
    });
    return;
  }

  // Datos finales para enviar o procesar
  const datosFinales = {
    datosGrupo,
    tipoCredito,
    tipoPeriodo,
    fechaCuota,
    nroCuotas,
    fechaDesembolso,
    codigoProducto,
    interesAsignado,
  };

  //console.log("Datos recolectados:", datosFinales);

  $.ajax({
    url: "../../views/Creditos/cre_grupo/functions/function.php",
    method: "POST",
    data: {
      condi: "calculos",
      datosFinales: JSON.stringify(datosFinales),
    },
    beforeSend: function () {
      loaderefect(1); // Mostrar el loader antes de la solicitud
    },
    success: function (data) {
      //('Respuesta del servidor (raw):', data);  // Muestra la respuesta sin parsear

      try {
        // Parsear la respuesta a JSON
        const response = JSON.parse(data);

        if (response.status === "success") {
          // Decodificar el contenido base64
          const base64Data = response.data.split(",")[1]; // Ignorar el prefijo "data:application/pdf;base64,"
          const blob = new Blob(
            [Uint8Array.from(atob(base64Data), (c) => c.charCodeAt(0))],
            { type: "application/pdf" }
          );

          // Abrir el PDF en una nueva pestaña
          const pdfUrl = URL.createObjectURL(blob);
          window.open(pdfUrl, "_blank"); // Abrir en nueva pestaña

          // Liberar memoria después de un tiempo
          setTimeout(() => URL.revokeObjectURL(pdfUrl), 10000);
        } else {
          console.error(
            "Error en la generación del reporte:",
            response.message
          );
          alert("Error al generar el reporte: " + response.message);
        }
      } catch (e) {
        console.error("Error al procesar la respuesta del servidor:", e);
        alert(
          "Ocurrió un error inesperado al procesar la respuesta del servidor."
        );
      }
    },
    complete: function (e) {
      // console.log(e);
      loaderefect(0); // Ocultar el loader después de la solicitud
    },
    error: function (xhr, status, error) {
      // Manejar errores de la solicitud AJAX
      console.error("Error AJAX:", error);
      alert("Error al procesar la solicitud: " + error);
    },
  });

  // Mostrar mensaje de éxito
  Swal.fire({
    icon: "success",
    title: "Información recolectada con éxito",
    confirmButtonText: "Aceptar",
  });
}

function PlanIndi(nombre, monto) {
  const tipoCredito = document.getElementById("tipcre").value;
  const tipoPeriodo = document.getElementById("peri").value;
  const fechaCuota = document.getElementById("fecinit").value;
  const nroCuotas = parseInt(document.getElementById("nrocuo").value, 10);
  const fechaDesembolso = document.getElementById("fecdes").value;

  // Recolectar el código del producto y % de interés asignado
  const codigoProducto = document.getElementById("codprod").value;
  const interesAsignado = parseFloat(document.getElementById("tasaprod").value);

  let errores = [];

  // Validaciones adicionales

  if (!tipoCredito || tipoCredito === "0") {
    errores.push("Debe seleccionar un tipo de crédito.");
  }
  if (!tipoPeriodo || tipoPeriodo === "0") {
    errores.push("Debe seleccionar un tipo de período.");
  }
  if (!fechaCuota) {
    errores.push("Debe ingresar la fecha de la primera cuota.");
  }
  if (!nroCuotas || nroCuotas <= 0) {
    errores.push("Debe ingresar un número válido de cuotas.");
  }
  if (!fechaDesembolso) {
    errores.push("Debe ingresar la fecha de desembolso.");
  }
  if (!codigoProducto || codigoProducto === "") {
    errores.push("Debe seleccionar un código de producto.");
  }
  if (isNaN(interesAsignado) || interesAsignado <= 0) {
    errores.push("Debe ingresar un porcentaje de interés válido.");
  }

  if (fechaCuota && fechaDesembolso) {
    const fechaCuotaDate = new Date(fechaCuota);
    const fechaDesembolsoDate = new Date(fechaDesembolso);

    if (fechaDesembolsoDate > fechaCuotaDate) {
      errores.push(
        "La fecha de desembolso no puede ser menor a la fecha de la primera cuota."
      );
    }
  }

  if (errores.length > 0) {
    Swal.fire({
      icon: "error",
      title: "Errores encontrados",
      text: errores.join("\n"),
      confirmButtonText: "Aceptar",
    });
    return;
  }

  const datosFinales = {
    nombre,
    monto,
    tipoCredito,
    tipoPeriodo,
    fechaCuota,
    nroCuotas,
    fechaDesembolso,
    codigoProducto,
    interesAsignado,
  };

  $.ajax({
    url: "../../views/Creditos/cre_grupo/functions/function.php",
    method: "POST",
    data: {
      condi: "calculosindi",
      datosFinales: JSON.stringify(datosFinales),
    },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      try {
        const response = JSON.parse(data);
        if (response.status === "success") {
          const base64Data = response.data.split(",")[1];
          const blob = new Blob(
            [Uint8Array.from(atob(base64Data), (c) => c.charCodeAt(0))],
            { type: "application/pdf" }
          );

          const pdfUrl = URL.createObjectURL(blob);
          window.open(pdfUrl, "_blank");

          setTimeout(() => URL.revokeObjectURL(pdfUrl), 10000);
        } else {
          console.error(
            "Error en la generación del reporte:",
            response.message
          );
          alert("Error al generar el reporte: " + response.message);
        }
      } catch (e) {
        console.error("Error al procesar la respuesta del servidor:", e);
        alert(
          "Ocurrió un error inesperado al procesar la respuesta del servidor."
        );
      }
    },
    complete: function () {
      loaderefect(0);
    },
    error: function (xhr, status, error) {
      console.error("Error AJAX:", error);
      alert("Error al procesar la solicitud: " + error);
    },
  });

  Swal.fire({
    icon: "success",
    title: "Información recolectada con éxito",
    confirmButtonText: "Aceptar",
  });
}

function planGrup(codGrup, NCiclo, tip) {
  $.ajax({
    url: "../../views/Creditos/cre_grupo/reportes/planillagrup.php",
    method: "POST",
    data: { condi: tip, codGrup: codGrup, NCiclo: NCiclo },
    xhrFields: {
      responseType: "blob",
    },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data, textStatus, xhr) {
      var blob = data;
      var contentDisposition = xhr.getResponseHeader("Content-Disposition");
      var fileName = "Planilla_Grupos.xlsx";
      var matches = contentDisposition.match(/filename="(.+)"/);
      if (matches != null && matches.length > 1) {
        fileName = matches[1];
      }

      var link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = fileName;
      link.click();
    },
    complete: function () {
      loaderefect(0);
    },
    error: function (xhr, status, error) {
      console.log("Error al generar el archivo Excel: ", error);
      alert(
        "Hubo un error al generar el reporte. Por favor, inténtelo nuevamente."
      );
    },
  });
}
