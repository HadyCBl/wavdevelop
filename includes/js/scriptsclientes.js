//FUNCION PARA EL EFECTO DEL LOADER
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

/* PARA LOS SELECT QUE TIENEN, QUE BUSCAR A LOS DEPARTAMENTOS */
function municipio(idmuni, iddepa) {
  //alert(iddepa);
  aux = 0;
  var condi = "departa";
  $.ajax({
    url: "../src/general.php",
    method: "POST",
    data: { iddepa: iddepa, condi: condi },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      $(idmuni).html(data);
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function printreportgroup() {
  var codgrupo = document.getElementById("CodigoGrupo").value;
  if (codgrupo != "") {
    var win = window.open(
      "Clientes/reportes/reporte_grupo.php?codgrupo=" + codgrupo + "",
      "_blank"
    );
  }
}

function fichacli(id) {
  console.log("Generating PDF for client ID:", id); // Debug log
  if (!id) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: "ID de cliente no válido",
    });
    return;
  }

  // Ensure id is properly formatted
  id = id.trim();
  reportes([[], [], [], [id]], "pdf", 32, 0, 1);
}

function printxls() {
  loaderefect(1);
  var e = document.getElementById("activo");
  var activo = e.options[e.selectedIndex].value;
  var finicio = document.getElementById("baja_inicio").value;
  var ffin = document.getElementById("baja_fin").value;
  var ainicio = document.getElementById("alta_inicio").value;
  var afin = document.getElementById("alta_fin").value;
  var checkalta = document.getElementById("checkalta").checked;
  var checkbaja = document.getElementById("checkbaja").checked;
  //----------------
  var url = "Clientes/reportes/clientesxls.php";
  $.ajax({
    url: url,
    async: true,
    type: "POST",
    dataType: "html", //html
    contentType: "application/x-www-form-urlencoded",
    data: { activo, finicio, ffin, ainicio, afin, checkalta, checkbaja },
    success: function (data) {
      loaderefect(0);
      var opResult = JSON.parse(data);
      var $a = $("<a>");
      $a.attr("href", opResult.data);
      $("body").append($a);
      $a.attr("download", "Clientes.xlsx");
      $a[0].click();
      $a.remove();
    },
  });
}

function fichaclijuri(id) {
  reportes([[], [], [], [id]], "pdf", 33, 0, 1);
}

function tipcli(tip) {
  if (tip == 1) {
    document.getElementById("filter_baja").style = "display: none;";
  } else {
    document.getElementById("filter_baja").style = "display: yes;";
  }
}
//funcion de los reportes

function consultar_reporte(file, bandera) {
  return new Promise(function (resolve, reject) {
    if (bandera == 0) {
      resolve("Aprobado");
    }
    $.ajax({
      url: "../../src/cruds/crud_cliente.php",
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

//funcion de los reportes
function reportes(datos, tipo, file, download, bandera = 0) {
  loaderefect(1);
  var datosval = [];
  datosval[0] = getinputsval(datos[0]);
  datosval[1] = getselectsval(datos[1]);
  datosval[2] = getradiosval(datos[2]);
  datosval[3] = datos[3];

  consultar_reporte(file, bandera)
    .then(function (action) {
      //PARTE ENCARGADA DE GENERAR EL REPORTE
      if (bandera == 1) {
        file = action;
      } else {
        file = file;
      }
      var url = "Clientes/reportes/" + file + ".php";
      $.ajax({
        url: url,
        async: true,
        type: "POST",
        dataType: "html",
        data: { datosval, tipo },
        success: function (data) {
          loaderefect(0);
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
                // mostrarPDFModal(opResult.data);
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
      });
    })
    .catch(function (error) {
      Swal.fire("Uff", error, "error");
    });
}

// Función para crear y mostrar el modal con el PDF
function mostrarPDFModal(datab) {
  var modal = document.createElement("div");
  modal.classList.add("modal", "fade");
  modal.id = "pdfModal";
  modal.tabIndex = "-1";
  modal.role = "dialog";
  modal.ariaLabelledby = "pdfModalLabel";
  modal.ariaHidden = "true";

  var modalDialog = document.createElement("div");
  modalDialog.classList.add("modal-dialog", "modal-fullscreen");
  modalDialog.role = "document";

  var modalContent = document.createElement("div");
  modalContent.classList.add("modal-content");
  // modalContent.width = '100%';
  // modalContent.height = '100%';

  var modalHeader = document.createElement("div");
  modalHeader.classList.add("modal-header");

  var modalTitle = document.createElement("h5");
  modalTitle.classList.add("modal-title");
  modalTitle.id = "pdfModalLabel";
  modalTitle.textContent = "Visor de PDF";

  var closeButton = document.createElement("button");
  closeButton.type = "button";
  closeButton.classList.add("close");
  closeButton.dataset.bsDismiss = "modal";
  closeButton.ariaLabel = "Cerrar";

  var closeIcon = document.createElement("span");
  closeIcon.ariaHidden = "true";
  closeIcon.textContent = "×";

  closeButton.appendChild(closeIcon);
  modalHeader.appendChild(modalTitle);
  modalHeader.appendChild(closeButton);

  var modalBody = document.createElement("div");
  modalBody.classList.add("modal-body");

  var pdfViewer = document.createElement("embed");
  pdfViewer.id = "pdf-viewer";
  pdfViewer.type = "application/pdf";
  pdfViewer.width = "100%";
  pdfViewer.height = "600";
  // pdfViewer.src = opResult.data;
  pdfViewer.src = datab;

  modalBody.appendChild(pdfViewer);
  modalContent.appendChild(modalHeader);
  modalContent.appendChild(modalBody);
  modalDialog.appendChild(modalContent);
  modal.appendChild(modalDialog);

  document.body.appendChild(modal);

  // Mostrar el modal
  var pdfModal = new bootstrap.Modal(modal);
  pdfModal.show();
}

//#region REGION DE GRUPOS
var dato = 0;
function opciones(op) {
  dato = op;
}

function limpiar() {
  printdiv("add_grupo", "#cuadro", "grupos", "0");
  $("#btnGua").show();
  $("#btnAct").hide();
  $("#btnEli").hide();
  //refresh();
}
//------------PARA INSERTAR DATOS DE LA BUSQUEDA DEL GRUPO, EN EL FROMULARIO
function instgrp(
  codgrp,
  nmgrp,
  direc,
  cnt,
  mun,
  depa,
  alde,
  nomMuni,
  estadoGrupo,
  control
) {
  consultaCre(control);
  if (estadoGrupo == "C") {
    $("#ingresarCliente").hide();
    $("#cerrar").hide();
    $("#abrir").show();
  }
  if (estadoGrupo == "A") {
    $("#ingresarCliente").show();
    $("#cerrar").show();
    $("#abrir").hide();
  }

  document.getElementById("CodigoGrupo").value = codgrp;
  document.getElementById("NombreGrupo").value = nmgrp;
  document.getElementById("canton").value = alde;
  document.getElementById("direcciongrupo").value = direc;

  // document.getElementById("Fecha").value = date;
  document.getElementById("contador").value = cnt;
  $("#depargrupo").val(depa);
  if (control == 0) {
    $("#EliminaGrupo").show();
    $("#msjCreditosPen").hide();
  }

  if (control == 1) {
    $("#EliminaGrupo").hide();
    $("#msjCreditosPen").show();
  }

  /* const select = document.getElementById('depargrupo');
  select.value = "GUATEMALA";
  var a = document.createElement("munigrupo");  */
  //EJECUTAR LA FUNCION DEL MUNICIPIO
  var condi = "departa";
  $.ajax({
    url: "../src/general.php",
    method: "POST",
    data: { iddepa: depa, condi: condi },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      $("#munigrupo").html(data);
      $("#munigrupo").val(mun);
    },
    complete: function () {
      loaderefect(0);
    },
  });

  if ((dato = 1)) {
    $("#btnGua").hide();
    $("#btnAct").show();
    $("#btnEli").show();
  }

  $("#buscargrupo").modal("hide");
  //swal('Muy Bien!','Grupo Seleccionado','success' )
  Swal.fire({
    icon: "success",
    title: "Muy Bien!",
    text: "Grupo Seleccionado",
  });

  grptable(cnt, "grptable");
}

//------------ Consultar credito
function consultaCre(dato) {
  if (dato == 1) {
    $("#msjAlerta").show();
    $("#msjAlerta1").hide();
  }
  if (dato == 0) {
    $("#msjAlerta").hide();
    $("#msjAlerta1").show();
  }
}
//------------ PARA MOSTRA EN LA TABLA LOS CLIENTES DEL GRUPO
function grptable(cnt, condi) {
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { cnt, condi },
    complete: function () {
      beforeSend(1);
    },
    success: function (data) {
      $("#tbgrpclt").html(data);
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//------------FUNCION PARA INSERTAR O EDITAR DATOS DE LOS GRUPOS
function insttbl(usuario) {
  // console.log("Datos " + usuario);
  //ARRAY DE LOS INPUTS
  const grpinptval = [
    "NombreGrupo",
    "canton",
    "direcciongrupo",
    "CodigoGrupo",
    "contador",
  ];
  const grpinptval2 = [];

  grpinptval.forEach(function (valor, indice, array) {
    var datos = document.getElementById(valor).value;
    grpinptval2.push(datos);
  });

  var e = document.getElementById("depargrupo");
  var depa = e.options[e.selectedIndex].value;

  var e = document.getElementById("munigrupo");
  var muni = e.options[e.selectedIndex].value;

  //AREGALAR LOS DATOS DEL POST Y LOS DATOS DEL IF PARA LA CONDICION DE VACIO
  if (grpinptval2[0] == "" || grpinptval2[2] == "") {
    // Swal.fire('¡ERROR!',"No Dejar Campos Vacios",'error' );
    Swal.fire({
      icon: "error",
      title: "Oops...",
      text: "No Dejar Campos Vacios!",
    });
  } else {
    loaderefect(1);
    var cnt = document.getElementById("contador").value;
    var condi = "instupftgrp";

    $.ajax({
      url: "../src/cruds/crud_cliente.php",
      method: "POST",
      data: { cnt, grpinptval2, depa, muni, condi, usuario },
      success: function (data) {
        //$("#cardexform").html(data)
        if (data == "GRUPO INGRESADO" || data == "GRUPO ACTUALIZADO") {
          loaderefect(0);
          Swal.fire({ icon: "success", title: "Muy Bien! :)", text: data });
          printdiv2("#cuadro", 0);
        } else {
          Swal.fire({
            icon: "error",
            title: "ERROR",
            text: data,
          });
        }
      },
    });
  }
}
//------------Mensaje para indicarle al administrador del sistema, que no se puede eliminar grupo y clientes por que hay creditos pendites
function msjCreditosPen() {
  Swal.fire("Función denegada", "El grupo tiene creditos pendientes.", "error");
}
//------------ELIMINA CLIENTE HAY QUE VALIDARLO EN EL ALERT
function dltgrpcli(val1, nma) {
  Swal.fire({
    title: "¿ESTA SEGURO DE ELIMINAR?",
    showDenyButton: true,

    confirmButtonText: "Cancelar",
    denyButtonText: `Eliminar`,
  }).then((result) => {
    /* Read more about isConfirmed, isDenied below */
    if (result.isConfirmed) {
      Swal.fire("Uff", "Cancelado", "success");
    } else if (result.isDenied) {
      //Swal.fire('ELIMINADO', '', 'info')
      dltclntgrp(val1, nma);
    }
  });
}
//--------------------FUNCION PARA SELECCIONAR UN CARGO PARA EL INTEGRANTE DE UN GRUPO

function cambcargo(idcli, codgrup, codcargo) {
  var condi = "agrcargo";

  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { idcli, codgrup, condi, codcargo },
    success: function (data) {
      // Aquí manejas la respuesta
      //console.log(data);
      Swal.fire({
        icon: "success",
        title: "Asignacion",
        text: "Cargo asignado",
      });
      var cnt = document.getElementById("contador").value;
      grptable(cnt, "grptable");
    },
    error: function (xhr, status, error) {
      // Manejo de errores
      //console.error(error);
      Swal.fire({
        icon: "error",
        title: "ERROR",
        text: "Error al asignar cargo",
      });
    },
  });
}

//------------FUNCION PARA ELIMINAR CLIENTES DE UN GRUPO
function dltclntgrp(id, nme) {
  var usuario = document.getElementById("idUsuario").value;
  var condi = "dltclntgrp";
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { id, nme, condi, usuario },
    beforeSend: function () {
      loaderefect(0);
    },
    success: function (data) {
      //alert(data)
      if (data == "Cliente ELIMINADO DEL GRUPO") {
        Swal.fire({
          icon: "info",
          title: "!OJO¡",
          text: "Cliente ELIMINADO DEL GRUPO",
        });

        $("#asignarcargo").modal("hide");
        var cnt = document.getElementById("contador").value;
        grptable(cnt, "grptable");
      } else {
        Swal.fire({
          icon: "error",
          title: "ERROR",
          text: data,
        });
      }
    },
    complete: function () {
      loaderefect(1);
    },
  });
}

//------------PARA INSERTAR UN CLIENTE DENTRO DE UN GRUPO, ADD GRUPO
function addclntgrp() {
  var cnt = document.getElementById("contador").value;
  if (cnt == 0) {
    Swal.fire({
      icon: "info",
      title: "!OJO¡",
      text: "SELECIONE UN GRUPO",
    });
  } else {
    $("#Bscrclntgrp").modal("show");
  }
}
/*Proceso para cerrar un grupo cuando ya se ingresaron a todos lo clientes*/
function cerrarGrupo() {
  var cnt = document.getElementById("contador").value;
  var usuario = document.getElementById("idUsuario").value;
  var condi = "cerrarGrupo";
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { cnt, condi, usuario },
    success: function (data) {
      if (data == "Grupo cerrado") {
        $("#cerrar").hide();
        $("#abrir").show();
        $("#ingresarCliente").hide();
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data });
        var cnt = document.getElementById("contador").value;
        grptable(cnt, "grptable");
      } else {
        Swal.fire({
          icon: "error",
          title: "ERROR",
          text: data,
        });
      }
    },
  });
}
/*Proceso para abrir un grupo*/
function abrirGrupo() {
  var cnt = document.getElementById("contador").value;
  var usuario = document.getElementById("idUsuario").value;
  var condi = "abrirGrupo";
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { cnt, condi, usuario },
    success: function (data) {
      if (data == "El grupo esta abierto") {
        $("#cerrar").show();
        $("#abrir").hide();
        $("#ingresarCliente").show();
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data });
        var cnt = document.getElementById("contador").value;
        grptable(cnt, "grptable");
      } else {
        Swal.fire({
          icon: "error",
          title: "ERROR",
          text: data,
        });
      }
    },
  });
}
//------------FUNCION PARA INSERTAR A UN CLIENTE EN UN GRUPO
function instclntingrp(cln) {
  var cnt = document.getElementById("contador").value;
  var usuario = document.getElementById("idUsuario").value;
  var condi = "instclntingrp";

  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { cln, cnt, condi, usuario },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      //Cliente Agregado
      if (data == "Cliente Agregado") {
        Swal.fire({
          icon: "success",
          title: "Muy Bien!",
          text: data,
        });
        var cnt = document.getElementById("contador").value;
        grptable(cnt, "grptable");
      } else {
        Swal.fire({
          icon: "error",
          title: "ERROR",
          text: data,
        });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//PARA ELIMINAR UN GRUPO
function anulargrp() {
  Swal.fire({
    title: "¿ESTA SEGURO DE ELIMINAR?",
    showDenyButton: true,
    confirmButtonText: "Cancelar",
    denyButtonText: `Eliminar`,
  }).then((result) => {
    if (result.isConfirmed) {
      // console.log('hola');
      Swal.fire("Uff", "Cancelado", "success");
    } else if (result.isDenied) {
      dltgrp();
    }
  });
}

// ---- ----  funcion para eliminar grupos
function dltgrp() {
  var cnt = document.getElementById("contador").value;
  var usuario = document.getElementById("idUsuario").value;
  var condi = "dltgrp";
  if (cnt == 0) {
    Swal.fire({
      icon: "error",
      title: "ERROR",
      text: "Debe de Buscar un grupo",
    });
  } else {
    $.ajax({
      url: "../src/cruds/crud_cliente.php",
      method: "POST",
      data: { cnt, condi, usuario },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        if (data == "GRUPO ELIMINADO") {
          Swal.fire({
            icon: "success",
            title: "Muy Bien!",
            text: data,
          });
          printdiv("consulta_grupo", "#cuadro", "grupos", "0");
        } else {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: data,
          });
        }
      },
      complete: function () {
        loaderefect(0);
      },
    });
  }
}
//#endregion

//FUNCION PARA IMPRIMIR AN ALGUN DIV (CONDI, #ID_DIV, url, DATOxTRA)
function printdiv2(idiv, xtra) {
  loaderefect(1);
  condi = condimodal();
  dir = filenow();
  dire = "Clientes/" + dir + ".php";
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

function condimodal() {
  var condi = document.getElementById("condi").value;
  return condi;
}
function filenow() {
  var file = document.getElementById("file").value;
  return file;
}

//FUNCION PARA IMPRIMIR AN ALGUN DIV (CONDI, #ID_DIV, url, DATOxTRA)
function printdiv(condi, idiv, dir, xtra) {
  loaderefect(1);
  let dire = "Clientes/" + dir + ".php";
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

function eliminar(ideliminar, dir, xtra, condi) {
  dire = "../src/cruds/" + dir + ".php";
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
        success: function (data) {
          if (data == "1") {
            Swal.fire("Correcto", "Eliminado", "success");
            printdiv2("#cuadro", xtra);
          } else Swal.fire("Uff", "Error al eliminar", "danger");
        },
      });
    } else if (result.isDenied) {
      Swal.fire("Uff", "Cancelado", "success");
    }
  });
}

//FUNCIONES AGREGADAS POR CARLOS PARA MEJORAR EN CUANTO A ACTIVIDAD ECONOMICA
//#region Modal Nomenclatura
function abrir_modal(id_modal, id_hidden, dato) {
  $(id_modal).modal("show");
  $(id_hidden).val(dato);
}

function seleccionar_cuenta_ctb(id_hidden, valores) {
  printdiv5(id_hidden, valores);
  cerrar_modal("#modal_nomenclatura", "hide", "#id_modal_hidden");
}
//seleccionar cuenta mejorado
function seleccionar_cuenta_ctb2(id_hidden, valores) {
  printdiv5(id_hidden, valores);
}

function cerrar_modal(id_modal, estado, id_hidden) {
  $(id_modal).modal(estado);
  $(id_hidden).val("");
}
//#endregion

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

function limpiarhabdes(hab, des) {
  var i = 0;
  while (i < hab.length) {
    document.getElementById(hab[i]).value = "";
    i++;
  }
  var i = 0;
  while (i < des.length) {
    document.getElementById(des[i]).value = "";
    i++;
  }
}

// AQUI VOY A PONER TODO MI CODIGO PARA EL INGRESO DE CLIENTES-------------------------

//#region FUNCIONES CREADAS POR CARLOS PARA EL BALANCE ECONOMICO
function printdiv2_plus(idiv, xtra) {
  condi = $("#condi").val();
  dir = $("#file").val();
  dire = "Clientes/" + dir + ".php";
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

function printdiv3_plus(condi, idiv, xtra) {
  dir = $("#file").val();
  dire = "Clientes/" + dir + ".php";
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

//Function para obtener los valores de la fotografia
//antiguia obtinee plus
function obtiene_plus(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback = "null",
  messageConfirm = false
) {
  var inputs2 = [];
  var selects2 = [];
  var radios2 = [];
  inputs2 = getinputsval(inputs);
  selects2 = getselectsval(selects);
  radios2 = getradiosval(radios);
  // generico_plus(inputs2, selects2, radios2, condi, id, archivo, callback);
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
        generico_plus(inputs2, selects2, radios2, condi, id, archivo, callback);
      }
    });
  } else {
    generico_plus(inputs2, selects2, radios2, condi, id, archivo, callback);
  }
}
//funcion para obtener los valores de los inputs

//nueva obtiene PLUS""esta es solo para ingreso de clientes "
function obtiene_plus2(inputs, selects, radios, condi, id, archivo) {
  var inputs2 = getinputsval(inputs);
  var selects2 = getselectsval(selects);
  var radios2 = getradiosval(radios);
  var formData = new FormData();
  formData.append("inputs", JSON.stringify(inputs2));
  formData.append("selects", JSON.stringify(selects2));
  formData.append("radios", JSON.stringify(radios2));
  formData.append("condi", condi);
  formData.append("id", id);
  formData.append("archivo", JSON.stringify(archivo));

  // Agregar la imagen si existe
  var fotoCliente = document.getElementById("fotoCliente");
  if (fotoCliente.files.length > 0) {
    formData.append("fileimg", fotoCliente.files[0]);
  }
  // console.log(formData);
  // console.log(fotoCliente.files[0]);

  generico_plus2(formData, id);
}

function generico_plus(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback = "null"
) {
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      // console.log(data);
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        var reprint = "reprint" in data2 ? data2.reprint : 1;
        var timer = "timer" in data2 ? data2.timer : 60000;
        Swal.fire({
          icon: "success",
          title: "Muy Bien!",
          text: data2[0],
          timer: timer,
        });

        if (condi == "activarSensor") {
          cargar_push();
        }
        if (reprint == 1) {
          setTimeout(function () {
            printdiv2_plus("#cuadro", id);
          }, 1500);
        }
        if (typeof callback === "function") {
          callback(data2);
        }
      } else {
        // Swal.fire({ icon: "error", title: "¡ALERTA!", text: data2[0] });
        var relogin = "relogin" in data2 ? data2.relogin : 0;

        if (relogin == 1) {
          showRenewModalSession(
            data2[0],
            function () {
              generico_plus(inputs, selects, radios, condi, id, archivo);
            },
            data2.key
          );
        } else {
          Swal.fire({ icon: "error", title: "¡ALERTA!", text: data2[0] });
        }
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}
//generico PLus 2 la secuela""esta es s9olo para ingreso de clientes "
function generico_plus2(formData, id) {
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: formData,
    processData: false,
    contentType: false,
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      loaderefect(0);
      // console.log(data);
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

        if (condi == "activarSensor") {
          cargar_push();
        }
        if (reprint == 1) {
          setTimeout(function () {
            printdiv2_plus("#cuadro", id);
          }, 1500);
        }
      } else {
        // Swal.fire({ icon: "error", title: "¡ALERTA!", text: data2[0] });
        var relogin = "relogin" in data2 ? data2.relogin : 0;

        if (relogin == 1) {
          showRenewModalSession(
            data2[0],
            function () {
              generico_plus2(formData, id);
            },
            data2.key
          );
        } else {
          Swal.fire({ icon: "error", title: "¡ALERTA!", text: data2[0] });
        }
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//Obtiene los valores de los inputsCarga mulptiple de archivos
function obtiene_plus3(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  fileInputId = null
) {
  var inputs2 = getinputsval(inputs);
  var selects2 = getselectsval(selects);
  var radios2 = getradiosval(radios);

  // Verificar cada posición del array resultante
  if (Array.isArray(inputs2)) {
    console.log("=== ANÁLISIS DETALLADO DE inputs2 ===");
    inputs2.forEach((valor, index) => {
      console.log(
        `inputs2[${index}]: "${valor}" (tipo: ${typeof valor}, length: ${valor ? valor.length : "N/A"})`
      );
    });
  }

  var formData = new FormData();

  formData.append("inputs", JSON.stringify(inputs2));
  formData.append("selects", JSON.stringify(selects2));
  formData.append("radios", JSON.stringify(radios2));
  formData.append("condi", condi);
  formData.append("id", id);
  formData.append("archivo", JSON.stringify(archivo));

  // Agregar archivos múltiples si se especifica el ID del input
  if (fileInputId) {
    var fileInput = document.getElementById(fileInputId);
    if (fileInput && fileInput.files) {
      console.log("=== ARCHIVOS DETECTADOS ===");
      console.log("Cantidad de archivos:", fileInput.files.length);
      for (let i = 0; i < fileInput.files.length; i++) {
        console.log(
          `Archivo ${i}:`,
          fileInput.files[i].name,
          "Tamaño:",
          fileInput.files[i].size
        );
        formData.append("archivos_adjuntos[]", fileInput.files[i]);
      }
    }
  }

  // Agregar la imagen del cliente si existe (para compatibilidad)
  var fotoCliente = document.getElementById("fotoCliente");
  if (fotoCliente && fotoCliente.files && fotoCliente.files.length > 0) {
    formData.append("fileimg", fotoCliente.files[0]);
  }

  // Debug: mostrar contenido del FormData DETALLADO
  console.log("=== FormData contenido DETALLADO ===");
  for (var pair of formData.entries()) {
    console.log(pair[0] + ":", pair[1]);
    if (pair[0] === "inputs") {
      try {
        const inputsParsed = JSON.parse(pair[1]);
        console.log("  - inputs parseado:", inputsParsed);
        console.log("  - inputs parseado[5] (altitud):", inputsParsed[5]);
        console.log("  - inputs parseado[6] (precision):", inputsParsed[6]);
      } catch (e) {
        console.log("  - Error al parsear inputs:", e);
      }
    }
  }

  generico_plus3(formData, id, condi);
}

//Generico_plus_3 para pordesar archivos multiples sin que falle
function generico_plus3(formData, id, condi) {
  console.log("=== INICIANDO generico_plus3 ===");
  console.log("FormData enviado, id:", id, "condición:", condi);

  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: formData,
    processData: false,
    contentType: false,
    beforeSend: function () {
      console.log("=== ENVIANDO DATOS AL SERVIDOR ===");
      loaderefect(1);
    },
    success: function (data) {
      loaderefect(0);

      try {
        const data2 = JSON.parse(data);

        if (data2[1] == "1") {
          var reprint = "reprint" in data2 ? data2.reprint : 1;
          var timer = "timer" in data2 ? data2.timer : 60000;

          Swal.fire({
            icon: "success",
            title: "Muy Bien!",
            text: data2[0],
            timer: timer,
          });

          // Funciones específicas según el tipo de operación
          if (condi == "activarSensor") {
            cargar_push();
          }

          if (condi == "guardar_info_adicional_cliente") {
            // Recargar la página o actualizar la tabla
            setTimeout(function () {
              printdiv2_plus("#cuadro", id);
            }, 1500);
          }

          if (reprint == 1) {
            setTimeout(function () {
              printdiv2_plus("#cuadro", id);
            }, 1500);
          }
        } else {
          console.log("=== ERROR EN LA RESPUESTA ===");
          console.log("Mensaje de error:", data2[0]);
          Swal.fire({
            icon: "error",
            title: "¡ALERTA!",
            text: data2[0],
          });
        }
      } catch (error) {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "Error en la respuesta del servidor",
        });
      }
    },
    error: function (xhr, status, error) {
      loaderefect(0);
      console.error("=== ERROR AJAX ===");
      console.error("Error AJAX:", error);
      console.error("Status:", status);
      console.error("XHR:", xhr);
      Swal.fire({
        icon: "error",
        title: "Error de conexión",
        text: "No se pudo conectar con el servidor",
      });
    },
    complete: function () {
      console.log("=== AJAX COMPLETADO ===");
      loaderefect(0);
    },
  });
}

function eliminar_plus(
  ideliminar,
  xtra,
  condi,
  pregunta = "¿Esta seguro de eliminar?"
) {
  Swal.fire({
    title: pregunta,
    showDenyButton: true,
    confirmButtonText: "Confirmar",
    denyButtonText: `Cancelar`,
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: "../src/cruds/crud_cliente.php",
        method: "POST",
        data: { condi, ideliminar, xtra },
        beforeSend: function () {
          loaderefect(1);
        },
        success: function (data) {
          const data2 = JSON.parse(data);
          if (data2[1] == "1") {
            Swal.fire("Correcto", data2[0], "success");
            if (condi == "delete_cliente_natural") {
              printdiv("Delete_Cliente", "#cuadro", "clientes_001", "0");
            } else {
              printdiv2_plus("#cuadro", xtra);
            }
          } else {
            Swal.fire("Uff", data2[0], "error");
          }
        },
        complete: function () {
          loaderefect(0);
        },
      });
    }
  });
}

function cerrarModulo(nameEle) {
  $("#" + nameEle).hide();
}

function abrirModulo(nameEle) {
  $("#" + nameEle).show();
}

function sumar_valores_inputs(resultado, inputs, inputs2, tipo) {
  var total = 0;
  var total2 = 0;
  var resto = 0;
  inputs.forEach((element) => {
    var valor = parseFloat($(element).val());
    if (isNaN(valor)) {
      valor = 0;
    }
    total += valor;
  });
  inputs2.forEach((element) => {
    var valor = parseFloat($(element).val());
    if (isNaN(valor)) {
      valor = 0;
    }
    total2 += valor;
  });
  resto = total - total2;
  resto = resto.toFixed(2);
  if (tipo == 1) {
    $(resultado).text("Q " + resto);
  } else {
    $(resultado).val(resto);
  }
}
//#endregion

//#region FUNCION PARA GENERAR JSON
function generar_json(idcliente) {
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    type: "POST",
    dataType: "text",
    data: { condi: "generar_json_cli", idcliente: idcliente },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (opResult) {
      var jsonData = JSON.parse(opResult);
      //VALIDAR SI HAY ERROR O NO
      if (jsonData.status == 0) {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: jsonData.msj });
        return;
      }
      //MENSAJE DE SATISFACTORIO
      Swal.fire({ icon: "success", title: "Muy Bien!", text: jsonData.msj });
      // console.log(jsonData.data);
      // CREAR PESTAÑA EN BLANCO
      var newTab = window.open();
      newTab.document.write(
        "<pre>" + JSON.stringify(jsonData.data, null, 2) + "</pre>"
      );
      newTab.document.close();

      // Crear un enlace de descarga
      var downloadLink = $("<a>", {
        href:
          "data:text/json;charset=utf-8," +
          encodeURIComponent(JSON.stringify(jsonData.data, null, 2)),
        download: jsonData.file + ".json",
      });
      // Simular un clic en el enlace de descarga
      downloadLink[0].click();
      // Eliminar el elemento downloadLink
      downloadLink.remove();
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//buscar municipios en base a departamentos
function buscar_municipios(condi, select, id_departamento) {
  //consultar a la base de datos
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    method: "POST",
    data: { condi: condi, id: id_departamento },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        $(select).empty();
        for (var i = 0; i < data2[2].length; i++) {
          $(select).append(
            "<option value='" +
              data2[2][i]["codigo_municipio"] +
              "'>" +
              data2[2][i]["nombre"] +
              "</option>"
          );
        }
      } else {
        $(select).empty();
        $(select).append("<option value='0'>Seleccione un municipio</option>");
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function buscar_municipios_plus(condi, select, id_departamento) {
  return new Promise(function (resolve, reject) {
    //consultar a la base de datos
    $.ajax({
      url: "../src/cruds/crud_cliente.php",
      method: "POST",
      data: { condi: condi, id: id_departamento },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        const data2 = JSON.parse(data);
        if (data2[1] == "1") {
          $(select).empty();
          for (var i = 0; i < data2[2].length; i++) {
            $(select).append(
              "<option value='" +
                data2[2][i]["codigo_municipio"] +
                "'>" +
                data2[2][i]["nombre"] +
                "</option>"
            );
          }
          resolve(); // Resuelve la promesa cuando se ha completado con éxito.
        } else {
          $(select).empty();
          $(select).append(
            "<option value='0'>Seleccione un municipio</option>"
          );
          Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
          reject(data2[0]); // Rechaza la promesa en caso de error.
        }
      },
      complete: function () {
        loaderefect(0);
      },
    });
  });
}

function seleccionarValueSelect(selectId, valorASeleccionar) {
  var select = $(selectId);
  // Verificar si existe el valor deseado
  var existeValor =
    select.find("option[value='" + valorASeleccionar + "']").length > 0;
  if (existeValor) {
    // Seleccionar por valor si existe
    select.val(valorASeleccionar);
  } else {
    // Sino, seleccionar el primer option
    select.find("option").eq(0).prop("selected", true);
  }
}

function seleccionarValueRadio(grupo, valor) {
  const $radio = $(`input[name="${grupo}"][value="${valor}"]`);
  if ($radio.length) {
    $radio.prop("checked", true);
  } else {
    $(`input[name="${grupo}"]`).first().prop("checked", true);
  }
}

const LeerImagen = async (input) => {
  // Validación
  if (!input.files || !input.files[0]) {
    return;
  }
  // Leer archivo
  const file = input.files[0];
  const reader = new FileReader();
  try {
    const res = await new Promise((resolve, reject) => {
      reader.onload = (e) => resolve(e.target.result);
      reader.onerror = (e) => reject(e);
      reader.readAsDataURL(file);
    });
    // Mostrar vista previa
    $("#vistaPrevia").attr("src", res);
  } catch (e) {
    Swal.fire({
      icon: "error",
      title: "¡ERROR!",
      text: "Error leyendo la imagen:" + e,
    });
    // console.error('Error leyendo la imagen', e);
  }
};

function CargarImagen(idinput, codigocli) {
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
  if (codigocli == "") {
    Swal.fire({
      icon: "error",
      title: "¡ERROR!",
      text: "No se ha seleccionado un cliente a editar",
    });
    return;
  }
  var archivo = archivoInput.files[0];
  // Datos de envío
  var datos = new FormData();
  datos.append("fileimg", archivo);
  datos.append("codcli", codigocli);
  datos.append("condi", "cargar_imagen");
  loaderefect(1);
  // Petición AJAX
  $.ajax({
    url: "../src/cruds/crud_cliente.php",
    type: "POST",
    data: datos,
    processData: false,
    contentType: false,
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
        printdiv2_plus("#cuadro", codigocli);
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function ejecutarDespuesDeBuscarMunicipios(
  condi,
  select,
  id_departamento,
  id_municipio
) {
  buscar_municipios_plus(condi, select, id_departamento)
    .then(function () {
      seleccionarValueSelect(select, id_municipio);
    })
    .catch(function (error) {
      Swal.fire({ icon: "error", title: "¡ERROR!", text: error });
    });
}

//FUNCION PARA CREAR EL SHORTNAME
function concatenarValores(inputsIds, inputsIds2, opcion = 1, inputres) {
  var concatenatedValue1 = "";
  inputsIds.forEach((id) => {
    const inputElement = document.getElementById(id);
    if (inputElement) {
      concatenatedValue1 += inputElement.value;
      concatenatedValue1 += " ";
    }
  });

  var concatenatedValue2 = "";
  inputsIds2.forEach((id) => {
    const inputElement = document.getElementById(id);
    if (inputElement) {
      concatenatedValue2 += inputElement.value;
      concatenatedValue2 += " ";
    }
  });

  concatenatedValue1 = concatenatedValue1.trim();
  concatenatedValue2 = concatenatedValue2.trim();
  if (opcion == 1) {
    if (concatenatedValue2 != "") {
      concatenatedValue1 += " ";
    }
    concatenatedValue1 += concatenatedValue2;
  }
  if (opcion == 2) {
    if (concatenatedValue1 != "") {
      concatenatedValue1 += ", ";
    }
    concatenatedValue1 += concatenatedValue2;
  }
  $(inputres).val(concatenatedValue1);
}

//CALCULAR EDAD
function calcularEdad_plus(birthday, resultado) {
  birthday = new Date(birthday.split("/").reverse().join("-"));
  var ageDifMs = Date.now() - birthday.getTime();
  var ageDate = new Date(ageDifMs);
  $(resultado).val(Math.abs(ageDate.getUTCFullYear() - 1970));
}

function ocultar_actuacion_propia(valor) {
  if (valor == "1") {
    habilitar_deshabilitar([], ["representante", "actcalidad"]);
  }
  if (valor == "2") {
    habilitar_deshabilitar(["representante", "actcalidad"], []);
  }
}

function ocultar_nit(valor) {
  if (valor === "X") {
    habilitar_deshabilitar(
      [],
      ["tipoidentri", "numbernit", "actividadEconomicaSat"]
    );
  } else {
    habilitar_deshabilitar(
      ["tipoidentri", "numbernit", "actividadEconomicaSat"],
      []
    );
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

$(document).ready(function () {
  loaderefect(0);
});
//#endregion

//  -------------DPI NEGROY VALIDATION PRUEBA----------------------------------
// function dpivalidate(dpi, cli) {
//   var condi = "dpivalidate";

//   // Petición AJAX
//   $.ajax({
//     url: "../src/cruds/crud_cliente.php",
//     method: "POST",
//     data: { dpi: dpi, cli: cli, condi: condi },
//     success: function (data) {
//       let alertHtml = "";
//       if (data >= 1) {
//         alertHtml = `
//           <div class="alert alert-warning alert-dismissible fade show" role="alert" id="alertDPI">
//         <strong>DPI REPETIDO:</strong> ${data}
//         <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
//           </div>
//         `;
//       } else {
//         alertHtml = `
//           <div class="alert alert-success alert-dismissible fade show" role="alert" id="alertDPI">
//         <strong>DPI Disponible</strong>
//         <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
//           </div>
//         `;
//       }
//       setTimeout(function () {
//         $("#alertDPI").alert("close");
//       }, 5000);
//       $("#section_notification").html(alertHtml);
//     },
//   });
// }

/**************************
 ***CODIGO PARA LA HUELLA DIGITAL**************
 **************************/
function controlHuella(op) {
  switch (op) {
    case "activarSensor":
      // srn = "9EO21b1723396234525"; // *************
      srn = localStorage.getItem("srnPc"); // *************
      console.log("empezando captura");
      $.ajax({
        async: true,
        type: "POST",
        url: "../src/huella/ActivarSensorAdd.php",
        data: "&token=" + srn,
        dataType: "json",
        success: function (data) {
          var json = JSON.parse(data);
          console.log(json);
          if (json["filas"] === 1) {
            console.log("q pasa");
            $("#activeSensorLocal").attr("disabled", true);
            $("#fingerPrint").css("display", "block");
          }
        },
        complete: function (data) {
          console.log(data);
        },
      });
      break;

    case "guadarHuella":
      loaderefect(1);
      srn = localStorage.getItem("srnPc");
      var data = new FormData();
      data.append("token", srn);
      data.append("codCli", $("#codCli").val());
      data.append("mano", $("#mano").val());
      data.append("dedo", $("#dedo").val());

      $.ajax({
        async: true,
        type: "POST",
        url: "../src/huella/CrearUsuario.php",
        data: data,
        contentType: false,
        processData: false,
        cache: false,
        dataType: "json",

        success: function (data) {
          console.log("inicio el proceso");
          var json = JSON.parse(data);
          if (json["filas"] === 1) {
            loaderefect(0);
            Swal.fire({
              icon: "success",
              title: "Registro Exsitoso",
              text: "La huella digital se registro con exito",
            });
            inyecCod(
              "#tb_huellas",
              "huella_registrada",
              "../../views/Clientes/inyecCod/inyecCod.php",
              $("#codCli").val()
            );
            $("#imghuella").attr(
              "src",
              "https://c0.klipartz.com/pngpicture/1000/646/gratis-png-logo-huella-digital-computadora-iconos-digito-diseno.png"
            );
          }
        },
      });
      break;
    case "actualizarHuella":
      break;
    case "eliminarHuella":
      break;
    case "validarHuella": //Valida si la huella del cliente existe o no "0 no existe, 1 exite"
      break;
  }
}

function capData(dataPhp, dataJava = 0, pos = []) {
  var data = dataPhp.split(",");
  var dataJava = dataJava.split(",");
  var pos = pos.split(",");

  if (pos.length == 0) dataPos = dataJava.length;
  else dataPos = pos.length;

  for (let i = 0; i < dataPos; i++) {
    if ($(dataJava[i]).is("input")) {
      $(dataJava[i]).val(data[pos[i]]);
    }
    if ($(dataJava[i]).is("label")) {
      $(dataJava[i]).text(data[pos[i]]);
    }
    if ($(dataJava[i]).is("textarea")) {
      $(dataJava[i]).val(data[pos[i]]);
    }
  }
  $("#preIni").hide();
  inyecCod(
    "#tb_huellas",
    "huella_registrada",
    "../../views/Clientes/inyecCod/inyecCod.php",
    $("#codCli").val()
  );
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

function inyecCod(idElem, condi, url, extra = "0") {
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

var timestamp = null;
function cargar_push() {
  console.log("empieza carga");
  let srn = localStorage.getItem("srnPc");
  // console.log(srn)
  $.ajax({
    async: true,
    type: "POST",
    url: "../src/huella/httpush.php",
    data: "&timestamp=" + timestamp + "&token=" + srn,
    dataType: "json",
    success: function (data) {
      // console.log("slv1");
      console.log(data);
      var json = JSON.parse(JSON.stringify(data));
      timestamp = json["timestamp"];
      imageHuella = json["imgHuella"];
      tipo = json["tipo"];
      id = json["id"];

      $("#statusPlantilla").text(json["statusPlantilla"]);
      $("#textoSensor").text(json["texto"]);

      if (imageHuella !== null) {
        console.log("desde aka");
        $("#imghuella").attr("src", "data:image/png;base64," + imageHuella);
        if (json["statusPlantilla"] === "Muestras Restantes: 0") {
          // console.log("se desactiva el sensor");
          $("#buttonSave").removeAttr("hidden");
          $("#buttonShot").attr("hidden", true);
        }
        // if (tipo === "leer") {
        //   console.log(json["statusPlantilla"]);
        //   console.log(json["texto"]);
        //   $("#documento").text(json["documento"]);
        //   $("#nombre").text(json["nombre"]);
        //   $("#imageUser").attr(
        //     "src",
        //     "Model/imageUser.php?documento=" + json["documento"]
        //   );
        // }
      }
      // console.log("findelcargarpush");
      setTimeout(cargar_push, 1000);
    },
    complete: function (data) {
      console.log(data);
    },
    error: function (xhr, status, error) {
      console.log("Error en la solicitud:");
      console.error(error);
      console.error(status);
      console.dir(xhr);
    },
  });
}
// function printpdf() {
//   var codcli = document.getElementById("codcli").value;

//   if (!codcli) {
//     Swal.fire({
//       icon: "warning",
//       title: "Campo vacío",
//       text: "Por favor, selecciona un cliente.",
//     });
//   } else {
//     $.ajax({
//       url: "../views/Clientes/reportes/perfil_eco_imprimir.php",
//       method: "POST",
//       data: {
//         codcli: codcli,
//       },
//       beforeSend: function () {
//         loaderefect(1);
//       },
//       success: function (data) {
//         var resultado = JSON.parse(data);
//         if (resultado.status === 1) {
//           Swal.fire({
//             icon: "success",
//             showCloseButton: true,
//             title: "PDF generado correctamente",
//             text: "",
//           }).then(() => {
//             var pdfWindow = window.open("");
//             pdfWindow.document.write(
//               "<iframe width='100%' height='100%' src='" +
//                 resultado.data +
//                 "'></iframe>"
//             );
//           });
//         } else {
//           Swal.fire({
//             icon: "error",
//             showCloseButton: true,
//             title: "Error al generar el PDF",
//             text: resultado.mensaje || "Ha ocurrido un error.",
//           });
//         }
//       },
//       complete: function () {
//         loaderefect(0);
//       },
//       error: function (status, error) {
//         console.error("Error en la solicitud AJAX:", error);
//         Swal.fire({
//           icon: "error",
//           showCloseButton: true,
//           title: "Error en la solicitud AJAX",
//           text: error,
//         });
//       },
//     });
//   }
// }
function printpdfcli() {
  let e = document.getElementById("activo");
  let activo = e.options[e.selectedIndex].value;
  let finicio = document.getElementById("baja_inicio").value;
  let ffin = document.getElementById("baja_fin").value;
  let ainicio = document.getElementById("alta_inicio").value;
  let afin = document.getElementById("alta_fin").value;
  let checkalta = document.getElementById("checkalta").checked;
  let checkbaja = document.getElementById("checkbaja").checked;

  console.log(e, activo, finicio, ffin, ainicio, afin, checkbaja, checkalta);

  $.ajax({
    url: "../views/Clientes/reportes/clientespdf.php",
    method: "POST",
    data: {
      activo: activo,
      finicio: finicio,
      ffin: ffin,
      ainicio: ainicio,
      afin: afin,
      checkalta: checkalta,
      checkbaja: checkbaja,
    },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      var resultado = JSON.parse(data);
      if (resultado.status === 1) {
        Swal.fire({
          icon: "success",
          showCloseButton: true,
          title: "PDF generado correctamente",
          text: "",
        }).then(() => {
          var pdfWindow = window.open("");
          pdfWindow.document.write(
            "<iframe width='100%' height='100%' src='" +
              resultado.data +
              "'></iframe>"
          );
        });
      } else {
        Swal.fire({
          icon: "error",
          showCloseButton: true,
          title: "Error al generar el PDF",
          text: resultado.mensaje || "Ha ocurrido un error",
        });
      }
    },
    error: function (jqXHR, textStatus, errorThrown) {
      Swal.fire({
        icon: "error",
        showCloseButton: true,
        title: "Error al generar el PDF",
        text: "Ha ocurrido un error durante la solicitud: " + textStatus,
      });
    },
    complete: function () {
      loaderefect(0);
    },
  });
}
//Funiones de alerta listado de cumpleaños
function sendBirthdayEmail(nombre, email) {
  var subject = "Feliz Cumpleaños " + encodeURIComponent(nombre);
  var body =
    "Hola " +
    encodeURIComponent(nombre) +
    ",%0D%0A%0D%0A" +
    "🎉🎂 ¡Feliz cumpleaños! 🎂🎉%0D%0A%0D%0A" +
    "Esperamos que pases un excelente día lleno de alegría y felicidad. " +
    "Queremos aprovechar esta ocasión especial para recordarte la importancia de mantenerte al día con tus pagos.%0D%0A%0D%0A" +
    "Si tienes alguna duda o necesitas asistencia, no dudes en contactarnos.%0D%0A%0D%0A" +
    "¡Disfruta tu día al máximo!%0D%0A%0D%0A" +
    "Saludos cordiales,%0D%0A" +
    "Tu equipo de " +
    encodeURIComponent("<?php echo $ofi; ?>");
  window.location.href =
    "mailto:" + email + "?subject=" + subject + "&body=" + body;
}
//funciones para Evio de notificaciones
