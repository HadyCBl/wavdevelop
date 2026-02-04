var tablaRecibos;
//Funcion para eliminar Recibo de creditos individuales
function eliminar(ideliminar, condi, archivo) {
  //console.log(ideliminar+" - "+condi+" - "+archivo)
  //return
  dire = "../../src/cruds/crud_caja.php";
  //alert(ideliminar + ' ' + condi + ' ' + archivo);

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
          loaderefect(1);
        },
        success: function (data) {
          const data2 = JSON.parse(data);

          if (data2[1] == "1") {
            // if (condi === 'eliReGru') {
            //     recargarTabla();
            //     Swal.fire("Correcto", "Eliminado", "success");
            //     return;
            // }
            Swal.fire("Correcto", "Eliminado", "success");
            printdiv2("#cuadro", 0);
          } else Swal.fire("X(", data2[0], "error");
        },
        complete: function () {
          loaderefect(0);
        },
      });
    }
  });
}

//Funcion para capturar datos
function capData(dataPhp, dataJava = 0, pos = []) {
  let data = dataPhp.split("||");

  // console.log('Data de php--> ' + data);

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
}

function capDataMul(nameEle, totalEle) {
  total = $(totalEle).val();
  var array = [];
  // console.log("Totla de elementos " + total);
  for (var con = 1; con <= total; con++) {
    var dato = $("#" + con + nameEle)
      .val()
      .trim();
    //console.log("Info... "+ dato);
    array.push(dato);
  }
  return array;
}

function dataName(nameEle, tipo) {
  var elementos = document.querySelectorAll(
    "" + tipo + '[name="' + nameEle + '[]"]'
  );
  var valores = [];
  elementos.forEach(function (elemento) {
    if (tipo === "input") valores.push(elemento.value);
    if (tipo === "td") valores.push(elemento.textContent);
    if (tipo === "textarea") valores.push(elemento.value);

    //   if (tipo === 'textarea'){
    //     alert("Ingreso a la opcion ");
    //     valores.push(elemento.textContent);
    //   }
  });
  return valores;
}

// Buscar a los clientes
function inyecCod(
  idElem,
  condi,
  extra = "0",
  url = "../../src/cruds/crud_caja.php"
) {
  //console.log(typeof(extra)+" Informacion del array : "+extra);return;

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

//Cerrar modal
function cerrarModal(modalCloss) {
  $(modalCloss).modal("hide"); // CERRAR MODAL
}

//#region printdivs
function printdiv(condi, idiv, dir, xtra) {
  //   loaderefect(1);
  dire = "./caja/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      loaderefect(0);
      $(idiv).html(data);
    },
    complete: function () {
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
//para recargar en el mismo archivo, solo mandar id del cuadro y el extra
function printdiv2(idiv, xtra) {
  condi = $("#condi").val();
  dir = $("#file").val();
  dire = "caja/" + dir + ".php";
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

function abrir_modal(id_modal, id_hidden, dato) {
  $(id_modal).modal("show");
  $(id_hidden).val(dato);
}

function seleccionar_cuenta_ctb2(id_hidden, valores) {
  printdiv5(id_hidden, valores);
}

//cerrarModal
function cerrar_modal(id_modal, estado, id_hidden) {
  $(id_modal).modal(estado);
  $(id_hidden).val("");
}

//#endregion
//#region Obtiene
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
function generico(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback = "NULL"
) {
  //console.log("Datos "+inputs+" Cacep "+archivo[1]); return;
  $.ajax({
    url: "../../src/cruds/crud_caja.php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      // console.log(data);
      const data2 = JSON.parse(data);
      // console.log(data2);
      //return;
      if (data2[1] == "1") {
        // if (condi === 'actReciCreGru') {
        //     recargarTabla();
        //     Swal.fire({ icon: 'success', title: 'Muy Bien!', text: data2[0] })
        //     return;
        // }
        var reprint = "reprint" in data2 ? data2.reprint : 1;
        var timer = "timer" in data2 ? data2.timer : 60000;
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0], timer: timer });
        if (reprint == 1) {
          printdiv2("#cuadro", id);
        }

        if (condi == "create_pago_individual")
          reportes([[], [], [], [1, archivo[0], data2[3], data2[2]]],"pdf","14",0,1);
        if (condi == "create_pago_juridico")
          reportes(
            [[], [], [], [data2[2], data2[3], data2[4], data2[5]]],
            "pdf",
            "14",
            0,
            1
          );
        if (condi == "paggrupal") {
          reportes(
            [[], [], [], [archivo[1], data2[2], data2[3]]],
            "pdf",
            "15",
            0,
            1
          ); //COMPROBANTE NORMAL
          reportes(
            [[], [], [], [archivo[1], data2[2], data2[3]]],
            "pdf",
            "24",
            0,
            1
          ); //COMPROBANTE RESUMEN
        }
        if (condi == "create_caja_cierre")
          reportes([[], [], [], [data2[2]]], `pdf`, "arqueo_caja", 0);
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
      url: "../../src/cruds/crud_caja.php",
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

function reportes_xls(datos, tipo, file, download) {
  console.log(datos, tipo, file, download);
  //return;
  loaderefect(1);
  var url =
    "caja/reportes/" + file + ".php?cod_cuenta=" + encodeURIComponent(datos[3]);
  // Redirigir al navegador
  window.location.href = url;
  loaderefect(0);
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
      var url = "caja/reportes/" + file + ".php";
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
          // console.log(opResult)
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
            if (file != "arqueo_caja") {
              Swal.fire({
                icon: "success",
                title: "Muy Bien!",
                text: opResult.mensaje,
              });
            }
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
        error: function (xhr, status, error) {
          if (xhr.status === 404) {
            Swal.fire({
              icon: "error",
              title: "¡ERROR!",
              text: "El archivo solicitado no existe.",
            });
          } else {
            console.log(xhr.responseText);
            Swal.fire({
              icon: "error",
              title: "¡ERROR!",
              text: xhr.responseText,
            });
          }
        },
      });
    })
    .catch(function (error) {
      Swal.fire("Uff", error, "error");
    });
}
//#endregion

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
      changedisabled("#bt" + i.substring(1) + " .habi", 1);
    } else {
      changedisabled("#bt" + i.substring(1) + " .habi", 0);
    }
  }
}

function changedisabled(padre, status) {
  if (status == 0) $(padre).attr("disabled", "disabled");
  else $(padre).removeAttr("disabled");
}

let numOr0 = (n) => (isNaN(parseFloat(n)) ? 0 : parseFloat(n));

function summon(id) {
  let rows = id.substring(7, 9);
  let filas = getinputsval([
    "capital" + rows,
    "interes" + rows,
    "monmora" + rows,
    "otrospg" + rows,
  ]);
  let sumdata = filas.reduce((a, b) => numOr0(a) + numOr0(b));
  $("#totalpg" + rows).val(sumdata.toFixed(2));
  var i = 0;
  let filtot = [];
  while (i != -1) {
    filtot[i] = getinputsval(["totalpg" + i]);
    i = !!document.getElementById("totalpg" + (i + 1)) ? i + 1 : -1;
  }
  let total = filtot.reduce((a, b) => numOr0(a) + numOr0(b));
  $("#totalgen").val(parseFloat(total).toFixed(2));
}

//#region PRINTDIV5
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

var tablaRecibos;
function tablaRecibos(codusu) {
  tablaRecibos = $("#tabla_recibos").DataTable({
    processing: true,
    serverSide: true,
    sAjaxSource: "../../src/server_side/recibo_credito_grupales.php",
    columns: [
      {
        data: [4],
      },
      {
        data: [6],
      },
      {
        data: [1],
      },
      {
        data: [2],
      },
      {
        data: [3],
      },
      {
        data: [0], //Es la columna de la tabla
        render: function (data, type, row) {
          imp = "";

          const separador = "||";
          var dataRow = row.join(separador);

          //console.log(dataRow);
          imp = `<button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="reportes([[], [], [], ['${row[5]}', '${row[1]}', '${row[6]}']], 'pdf', 'comp_grupal', 0)"><i class="fa-solid fa-print me-2"></i>Reimprimir</button>`;

          imp1 = `<button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCreReGrup" onclick="capData('${dataRow}',['#idGru','#ciclo','#fecha', '#nomGrupo', '#codGrup', '#recibo', '#antRe'],[5,6,2,4,7,1,1]);inyecCod('#integrantes','reciboDeGrupos','${row[1]}||${row[5]}||${row[6]}')"><i class="fa-sharp fa-solid fa-pen-to-square"></i></button>`;

          imp2 = `<button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="eliminar('${row[1]}|*-*|${row[5]}|*-*|${row[6]}','eliReGru', ${codusu});"><i class="fa-solid fa-trash-can"></i></button>`;

          return imp + imp1 + imp2;
        },
      },
    ],
    bDestroy: true,
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

function recargarTabla() {
  tablaRecibos.ajax.reload();
}

//FUNCIONES PARA APERTURA Y CIERRE DE CAJA
function save_apertura_cierre(
  idusuario,
  banderaoperacion,
  condi = "create_caja_apertura",
  idreg = "0",
  saldoinicial = 0,
  saldofinal = 0
) {
  const formatter = new Intl.NumberFormat("es-GT", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  // var saldoinicial = $('#saldoinicial').val() || 0;
  // var saldofinal = $('#saldofinal').val() || 0;

  // Swal.fire({
  // 	title: 'Confirmación de ' + (banderaoperacion ? 'apertura' : 'cierre') + ' de caja',
  // 	html: `
  // 					<div style="text-align: center;">
  // 							<label><b style="margin-right: 5px;">Saldo inicial:</b>${formatter.format(saldoinicial)}</label>
  // 					</div>`+
  // 		(banderaoperacion ? `` : `
  // 							<div style="text-align: center;">
  // 									<label><b style="margin-right: 5px;">Saldo final:</b>${formatter.format(saldofinal)}</label>
  // 							</div>`) +
  // 		`
  // 					<div style="text-align: center; margin-top: 5px;">
  // 							<label style="color: #0D6EFD; font-size: 0.8rem;">-Revise bien y confirme, ya que no se podra revertir la acción-</label>
  // 					</div>`+
  // 		`<input id="passconf" class="swal2-input" type="password" placeholder="contraseña" autocapitalize="off">`,
  // 	showCancelButton: true,
  // 	confirmButtonText: 'Confirmar ' + (banderaoperacion ? 'apertura' : 'cierre'),
  // 	showLoaderOnConfirm: true,
  // 	preConfirm: () => {
  // 		const password = document.getElementById('passconf').value;
  // 		//AJAX PARA CONSULTAR EL USUARIO
  // 		return $.ajax({
  // 			url: "../../src/cruds/crud_usuario.php",
  // 			method: "POST",
  // 			data: { 'condi': 'confirmar_apertura_cierre_caja', 'idusuario': idusuario, 'pass': password },
  // 			dataType: 'json',
  // 			success: function (data) {
  // 				// console.log(data);
  // 				if (data[1] != "1") {
  // 					Swal.showValidationMessage(data[0]);
  // 				}
  // 			}
  // 		}).catch(xhr => {
  // 			Swal.showValidationMessage(`${xhr.responseJSON[0]}`);
  // 		});
  // 	},
  // 	allowOutsideClick: (outsideClickEvent) => {
  // 		const isLoading = Swal.isLoading();
  // 		const isClickInsideDialog = outsideClickEvent?.target?.closest('.swal2-container') !== null;
  // 		return !isLoading && !isClickInsideDialog;
  // 	}
  // }).then((result) => {
  // 	if (result.isConfirmed) {
  obtiene([`iduser`, `fec_apertura`, `saldoinicial`], [], [], condi, `0`, [
    idusuario,
    idreg,
    saldoinicial,
    saldofinal,
  ]);
  // 	}
  // });
}

// NEGROY MOSTRAR OCULTAR BTN BOLETAS INFO PRUEBAS GAYs
function showBTN() {
  // Obtener el checkbox de para ocultar
  //var checkshow = document.getElementById('ShowCheck');
  var metodoPagoSelect = document.getElementById("metodoPago");
  // Elementos que se mostrarán u ocultarán con CLASES no ID
  var elementos = document.querySelectorAll(".mostrar");

  elementos.forEach(function (elemento) {
    if (metodoPagoSelect.value === "2") {
      // checkshow.checked
      // Mostrar el elemento si el checkbox está seleccionado
      elemento.classList.remove("d-none");
      //cambio.classList.add('d-none');
    } else {
      // Ocultar el elemento si el checkbox no está seleccionado
      elemento.classList.add("d-none");
      //cambio.classList.remove('d-none');
    }
  });
}

function buscar_cuentas() {
  idbanco = document.getElementById("bancoid").value;
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "buscar_cuentas", id: idbanco },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      // console.log(data2);
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
          "<option value='F000'>Seleccione una cuenta de banco</option>"
        );
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//------------------------------------------FUNCION DE MOVIMIENTOS DE CAJA
function calcularSubtotal(input, valorDenominacion) {
  const cantidad = parseInt(input.value) || 0;
  const subtotal = cantidad * valorDenominacion;
  const subtotalBadge = input.closest(".card-body").querySelector(".subtotal");
  subtotalBadge.textContent = `Q${subtotal.toFixed(2)}`;
  calcularTotalGeneral();
}

function calcularTotalGeneral() {
  let total = 0;
  const subtotales = document.querySelectorAll(".subtotal");
  subtotales.forEach((subtotal) => {
    total += parseFloat(subtotal.textContent.replace("Q", "")) || 0;
  });
  document.getElementById("totalGeneral").value = total.toFixed(2);
}

function validarcajas() {
  let isValid = true;
  const desglose = document.querySelector(
    'input[name="desglosarMonto"]:checked'
  ).value;

  if (desglose === "no") {
    const totalSolicitado = parseFloat(
      document.getElementById("totalGeneral").value
    );
    if (isNaN(totalSolicitado) || totalSolicitado <= 0) {
      isValid = false;
      document.getElementById("totalGeneral").classList.add("is-invalid");
    } else {
      document.getElementById("totalGeneral").classList.remove("is-invalid");
    }
  } else {
    let inputs = document.querySelectorAll(".card input[type='number']");
    inputs.forEach((input) => {
      if (input.value === "" || input.value < 0) {
        isValid = false;
        input.classList.add("is-invalid");
      } else {
        input.classList.remove("is-invalid");
      }
    });
  }

  if (isValid) {
    Solicitud();
    //alert("Solicitud válida. Procediendo con el cálculo.");
  } else {
    Swal.fire({
      icon: "info",
      title: "Comprobar datos ingresados",
      text: "El formulario posee campos invalidos o vacíos, los cuales debes completar correctamente",
    });
  }
}

function mostrarFormularioDesglose() {
  const desglose = document.querySelector(
    'input[name="desglosarMonto"]:checked'
  ).value;
  const formularioDesglose = document.getElementById("formularioDesglose");
  const totalGeneral = document.getElementById("totalGeneral");

  if (desglose === "si") {
    formularioDesglose.style.display = "block";
    totalGeneral.value = "0.00";
    calcularTotalGeneral();
    totalGeneral.disabled = true;
  } else {
    formularioDesglose.style.display = "none";
    const inputs = document.querySelectorAll(".card input[type='number']");
    inputs.forEach((input) => {
      input.value = 0;
      const subtotalBadge = input
        .closest(".card-body")
        .querySelector(".subtotal");
      if (subtotalBadge) {
        subtotalBadge.textContent = "Q0.00";
      }
    });
    totalGeneral.value = "0.00";
    totalGeneral.disabled = false;
    calcularTotalGeneral();
  }
}

function cambiarMoneda() {
  const tipoOperacion = document.getElementById("tipMoneda").value;
  const desglose = document.querySelector(
    'input[name="desglosarMonto"]:checked'
  ).value;

  limpiarTarjetas();
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "bdenominacion", id: tipoOperacion },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const response = JSON.parse(data);

      if (response.status === "success" && response.data.length > 0) {
        let billetesHTML = "";
        let monedasHTML = "";

        const monedaSimbolo =
          response.data2.length > 0 ? response.data2[0].abr : "Q";
        response.data.forEach((denominacion) => {
          const denominacionId = denominacion.id;
          if (denominacion.tipo === 1) {
            // Billetes
            billetesHTML += `
                            <div class="col-12 col-md-4">
                                <div class="card bg-light shadow-sm">
                                    <div class="card-body">
                                        <div class="text-center text-success"><b>${monedaSimbolo} ${denominacion.monto}</b></div>
                                        <div class="input-group mt-2">
                                            <span class="input-group-text">Cantidad:</span>
                                            <input type="number" class="form-control" min="0" step="1" value="0" 
                                                placeholder="0" oninput="calcularSubtotal(this, ${denominacion.monto})">
                                        </div>
                                        <div class="mt-2 text-center">
                                            <b>Subtotal:</b>
                                            <span class="badge bg-success text-white subtotal">${monedaSimbolo} 0.00</span>
                                        </div>
                                        <!-- Campo oculto para el ID -->
                                        <input type="hidden" class="denominacion-id" value="${denominacionId}">
                                    </div>
                                </div>
                            </div>
                        `;
          } else if (denominacion.tipo === 2) {
            // Monedas
            monedasHTML += `
                            <div class="col-12 col-md-4">
                                <div class="card bg-light shadow-sm">
                                    <div class="card-body">
                                        <div class="text-center text-success"><b>${monedaSimbolo} ${denominacion.monto}</b></div>
                                        <div class="input-group mt-2">
                                            <span class="input-group-text">Cantidad:</span>
                                            <input type="number" class="form-control" min="0" step="1" value="0" 
                                                placeholder="0" oninput="calcularSubtotal(this, ${denominacion.monto})">
                                        </div>
                                        <div class="mt-2 text-center">
                                            <b>Subtotal:</b>
                                            <span class="badge bg-success text-white subtotal">${monedaSimbolo} 0.00</span>
                                        </div>
                                        <!-- Campo oculto para el ID -->
                                        <input type="hidden" class="denominacion-id" value="${denominacionId}">
                                    </div>
                                </div>
                            </div>
                        `;
          }
        });

        const formularioDesglose =
          document.getElementById("formularioDesglose");
        if (billetesHTML) {
          formularioDesglose.innerHTML += `
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="p-2 mb-3 bg-success text-white text-center">
                                    <b>Billetes</b>
                                </div>
                                <div class="row gy-3">
                                    ${billetesHTML}
                                </div>
                            </div>
                        </div>
                    `;
        }
        if (monedasHTML) {
          formularioDesglose.innerHTML += `
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="p-2 mb-3 bg-success text-white text-center">
                                    <b>Monedas</b>
                                </div>
                                <div class="row gy-3">
                                    ${monedasHTML}
                                </div>
                            </div>
                        </div>
                    `;
        }
        document.getElementById("totalGeneral").disabled = true;
        document.getElementById("modal_footer").style.display = "block";
      } else {
        Swal.fire({
          title: "Sin datos",
          text: "No se encontraron denominaciones para esta moneda.",
          icon: "info",
          confirmButtonText: "Aceptar",
        });

        document.getElementById("totalGeneral").disabled = true;
        document.getElementById("modal_footer").style.display = "none";
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function limpiarTarjetas() {
  const formularioDesglose = document.getElementById("formularioDesglose");
  formularioDesglose.innerHTML = `
        <div class="row">
            <div class="col text-center">
                <h5 class="text-success">Denominaciones posibles</h5>
                <p class="text-muted">Ingrese la cantidad para cada denominación.</p>
            </div>
        </div>
    `;
}

function Solicitud() {
  const tipoMoneda = document.getElementById("tipMoneda").value;
  const tipoOperacion = document.getElementById("tipoOperacion").value;
  const desglosarMonto = document.querySelector(
    'input[name="desglosarMonto"]:checked'
  ).value;
  const totalGeneral = document.getElementById("totalGeneral").value;

  let datosEnvio = {
    tipoMoneda: tipoMoneda,
    tipoOperacion: tipoOperacion,
    desglosarMonto: desglosarMonto,
    totalGeneral: parseFloat(totalGeneral) || 0,
    denominaciones: [],
  };

  if (desglosarMonto === "si") {
    const billetes = document.querySelectorAll(
      '#formularioDesglose .card-body input[type="number"]'
    );
    billetes.forEach((input) => {
      const monto = input.getAttribute("oninput").match(/,\s*([0-9.]+)/)?.[1];
      const cantidad = parseInt(input.value) || 0;
      const denominacionId = input
        .closest(".card")
        .querySelector(".denominacion-id").value;

      if (monto && cantidad > 0) {
        datosEnvio.denominaciones.push({
          id: denominacionId, // Incluir el ID de la denominación
          monto: parseFloat(monto),
          cantidad: cantidad,
        });
      }
    });
  }

  if (desglosarMonto === "si" && datosEnvio.denominaciones.length === 0) {
    Swal.fire({
      title: "Advertencia",
      text: "Debe ingresar al menos una denominación válida si desglosa el monto.",
      icon: "warning",
      confirmButtonText: "Aceptar",
    });
    return;
  }

  //console.log(datosEnvio);

  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "procesarMovimiento", datos: JSON.stringify(datosEnvio) },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (response) {
      bloquearInputs();
      try {
        const respuesta = JSON.parse(response);
        if (respuesta.status === "success") {
          Swal.fire({
            title: "Éxito",
            text: "Movimiento procesado correctamente.",
            icon: "success",
            confirmButtonText: "Aceptar",
          }).then(() => {
            document.getElementById("GenSolicitar").style.display = "none";
            document.getElementById("GenPDF").style.display = "inline-block";
            document.getElementById("GenPDF").value = respuesta.cod;
          });
        } else {
          Swal.fire({
            title: "Error",
            text: respuesta.message || "No se pudo procesar el movimiento.",
            icon: "error",
            confirmButtonText: "Aceptar",
          });
        }
      } catch (error) {
        Swal.fire({
          title: "Error",
          text: "Ocurrió un error inesperado.",
          icon: "error",
          confirmButtonText: "Aceptar",
        });
      }
    },
    complete: function () {
      loaderefect(0);
    },
    error: function () {
      Swal.fire({
        title: "Error",
        text: "No se pudo conectar con el servidor.",
        icon: "error",
        confirmButtonText: "Aceptar",
      });
    },
  });
}

function bloquearInputs() {
  const inputs = document.querySelectorAll("#formularioDesglose input");
  inputs.forEach((input) => {
    input.disabled = true;
  });

  const selectElements = document.querySelectorAll(
    "#tipMoneda, #tipoOperacion"
  );
  selectElements.forEach((select) => {
    select.disabled = true;
  });

  const radioButtons = document.querySelectorAll(
    'input[name="desglosarMonto"]'
  );
  radioButtons.forEach((radio) => {
    radio.disabled = true;
  });
}

function buscarmovi(flagperm) {
  //console.log(flagperm); INDICA EL NIVEL DE PERMISO 0-. SIN PERMISOS, 1-. PERMISOS A NIVEL DE AGENCIA, 2-. A NIVEL GENERAL
  const agenciaDiv = document.querySelector("#contAgencia");
  if (flagperm !== 2) {
    agenciaDiv.style.display = "none";
  }

  const fecha = document.getElementById("fecha").value;
  const agencia = document.getElementById("selectagencia").value;

  //console.log("Fecha: " + fecha + ", Agencia: " + agencia);

  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: {
      condi: "buscarmovimientos",
      fecha: fecha,
      agencia: agencia,
      flagperm: flagperm,
    },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      //console.log('DATOS SIN PROCESAR: ' + data);
      const data2 = JSON.parse(data);

      if (data2.status === "success") {
        const movimientos = data2.data;
        const rowsPerPage = 10;
        let currentPage = 1;
        const totalPages = Math.ceil(movimientos.length / rowsPerPage);
        function renderPage(page) {
          const start = (page - 1) * rowsPerPage;
          const end = start + rowsPerPage;
          const movimientosPaginados = movimientos.slice(start, end);

          const tbody = document.getElementById("tabla-movimientos");
          tbody.innerHTML = "";

          movimientosPaginados.forEach((movimiento, index) => {
            const tipoMovimiento = movimiento.tipo == 1 ? "Depósito" : "Retiro";
            let estadoMovimiento;
            switch (movimiento.estado) {
              case 0:
                estadoMovimiento = "Rechazado";
                break;
              case 1:
                estadoMovimiento = "Solicitud";
                break;
              case 2:
                estadoMovimiento = "Aprobado";
                break;
              default:
                estadoMovimiento = "Desconocido";
            }
            let acciones = `<button class="btn btn-info btn-sm" onclick="reportes([[], [], [], [${movimiento.id},'solicitud']], 'pdf', '28', 0,1);">Ver Detalles</button>`;
            if (movimiento.estado === 1 && flagperm !== 0) {
              acciones += `
                                <button class="btn btn-success btn-sm" onclick="aprobarMovimiento(${movimiento.id}, ${flagperm});">Aprobar</button>
                                <button class="btn btn-danger btn-sm" onclick="rechazarMovimiento(${movimiento.id}, ${flagperm});">Rechazar</button>
                            `;
            }
            const fila = `
                            <tr>
                                <th scope="row">${start + index + 1}</th>
                                <td>${movimiento.nombre} ${movimiento.apellido}</td>
                                <td>${movimiento.total}</td>
                                <td>${tipoMovimiento}</td>
                                <td>${movimiento.created_at}</td>
                                <td>${estadoMovimiento}</td>
                                <td>${acciones}</td>
                            </tr>
                        `;
            tbody.innerHTML += fila;
          });
        }
        function renderPaginationControls() {
          const paginationControls = document.getElementById(
            "pagination-controls"
          );
          paginationControls.innerHTML = "";

          for (let i = 1; i <= totalPages; i++) {
            const activeClass = i === currentPage ? "active" : "";
            const pageButton = `
                            <li class="page-item ${activeClass}">
                                <a class="page-link" href="#" data-page="${i}">${i}</a>
                            </li>
                        `;
            paginationControls.innerHTML += pageButton;
          }

          document
            .querySelectorAll("#pagination-controls .page-link")
            .forEach((button) => {
              button.addEventListener("click", function (event) {
                event.preventDefault();
                const selectedPage = parseInt(this.getAttribute("data-page"));
                if (selectedPage !== currentPage) {
                  currentPage = selectedPage;
                  renderPage(currentPage);
                  renderPaginationControls();
                }
              });
            });
        }

        renderPage(currentPage);
        renderPaginationControls();
      } else {
        Swal.fire({
          icon: "error",
          title: "Oops...",
          text: data2.message,
        });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function rechazarMovimiento(cod, flagperm) {
  Swal.fire({
    title: "¿Estás seguro de rechazar este movimiento?",
    text: "Esta acción no se puede deshacer.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, rechazar",
    cancelButtonText: "No, cancelar",
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: "../../src/cruds/crud_credito_indi.php",
        method: "POST",
        data: { condi: "rechazarMovimiento", cod: cod },
        beforeSend: function () {
          loaderefect(1);
        },
        success: function (data) {
          const data2 = JSON.parse(data);
          if (data2.status === "success") {
            Swal.fire({
              icon: "success",
              title: "Rechazado",
              text: data2.data,
            });
          } else {
            Swal.fire({
              icon: "error",
              title: "Error",
              text:
                data2.data || "Ocurrió un problema al rechazar el movimiento.",
            });
          }
          buscarmovi(flagperm);
        },
        error: function () {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "No se pudo procesar la solicitud. Intente nuevamente.",
          });
        },
        complete: function () {
          loaderefect(0);
        },
      });
    } else {
      Swal.fire({
        icon: "info",
        title: "Cancelado",
        text: "El rechazo ha sido cancelado.",
      });
    }
  });
}

function aprobarMovimiento(cod, flagperm) {
  //console.log('DATO ANTES DE ENVIAR: ' + cod);
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "aprobarMovimiento", cod: cod },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      console.log(data2);

      const modalContent = document.querySelector(
        "#modalDenominaciones .modal-content"
      );
      modalContent.innerHTML = "";

      if (data2.data0 === 0) {
        const totalGeneral = data2.data3 || 0;
        modalContent.innerHTML = `
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Total Solicitado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center">
                            <h5>Total Solicitado</h5>
                        </div>
                        <div class="input-group mt-3">
                            <span class="input-group-text bg-success text-white">Q</span>
                            <input type="number" id="totalGeneral" min="0" class="form-control text-center" value="${totalGeneral}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="validarmovimiento(${cod}, ${flagperm})">Aprobar</button>
                    </div>
                `;
      } else {
        const denominaciones = data2.data1 || [];
        const monedas = data2.data2 || [];
        const monedaSimbolo = monedas.length > 0 ? monedas[0].abr : "Q";

        let billetesHTML = "";
        let monedasHTML = "";

        monedas.forEach((denominacion) => {
          const denominacionId = denominacion.id;
          const denominacionMonto = denominacion.monto;
          const iddeta =
            denominaciones.find((d) => d.id === denominacionId)?.iddeta || 0;
          const cantidad =
            denominaciones.find((d) => d.id === denominacionId)?.cantidad || 0;

          if (cantidad >= 0) {
            if (denominacion.monto >= 1) {
              // Asignar tipo 1 para billetes (si monto es mayor o igual a 1)
              denominacion.tipo = 1;
              billetesHTML += generarCardDenominacion(
                monedaSimbolo,
                denominacionMonto,
                cantidad,
                iddeta
              );
            } else {
              // Asignar tipo 2 para monedas (si monto es menor a 1)
              denominacion.tipo = 2;
              monedasHTML += generarCardDenominacion(
                monedaSimbolo,
                denominacionMonto,
                cantidad,
                iddeta
              );
            }
          }
        });

        let bovedas = data2.bovedas || [];

        // Agregar switch para definir si debita de la bóveda
        // Generar el select de bóvedas si hay disponibles
        let bovedaSelectHTML = "";
        if (bovedas.length > 0) {
          bovedaSelectHTML = `
                                ${bovedas.map((bov) => `<option value="${bov.id}">${bov.nombre}</option>`).join("")}
                    `;
        }

        // Si no hay bóvedas, desactivar el switch
        const debitarBovedaDisabled = bovedas.length === 0 ? "disabled" : "";

        modalContent.innerHTML = `
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Total Solicitado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="debitarBoveda" ${debitarBovedaDisabled} onclick="document.getElementById('bovedaSelectContainer').classList.toggle('d-none', !this.checked);">
                                <label class="form-check-label" for="debitarBoveda">Debitar de la bóveda</label>
                            </div>
                        <div class="mb-3 d-none" id="bovedaSelectContainer">
                            <label for="selectBoveda" class="form-label">Seleccionar Bóveda</label>
                            <select class="form-select" id="selectBoveda">
                                <option value="" disabled selected>Seleccione una bóveda</option>
                                ${bovedaSelectHTML}
                            </select>
                        </div>
                            
                        </div>
                        
                        ${crearSeccion("Billetes", billetesHTML)}
                        ${crearSeccion("Monedas", monedasHTML)}
                        <div class="text-center mt-3">
                            <b>Total General:</b>
                            <div class="input-group mt-3">
                                <span class="input-group-text bg-success text-white">Q</span>
                                <input type="number" id="totalGeneral" min="0" class="form-control text-center" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="validarmovimiento(${cod}, ${flagperm})">Aprobar</button>
                    </div>
                `;

        actualizarTotalGeneral();
      }

      const myModal = new bootstrap.Modal(
        document.getElementById("modalDenominaciones")
      );
      myModal.show();
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function generarCardDenominacion(simbolo, monto, cantidad, id) {
  const subtotal = cantidad * monto;
  const denId = 'deno_' + id;

  // Verifica si la cantidad es mayor que 0 o si es igual a 0, pero aún así quieres mostrar la tarjeta con cantidad 0
  if (cantidad >= 0) {
    const tipo = monto >= 1 ? 1 : 2; // Asigna el tipo 1 para billetes (si monto >= 1), y tipo 2 para monedas (si monto < 1)

    return `
            <div class="col-12 col-md-4" style="display: block;"> <!-- siempre se muestra -->
                <div class="card bg-light shadow-sm">
                    <div class="card-body">
                        <div class="text-center text-success"><b>${simbolo} ${monto}</b></div>
                        <div class="input-group mt-2">
                            <span class="input-group-text">Cantidad:</span>
                            <input id="${denId}" type="number" class="form-control den-input" min="0" step="1" 
                            value="${cantidad}" data-den="${monto}"
                                placeholder="0" oninput="updateSubtotal('${denId}', '${simbolo}')">
                        </div>
                        <div class="mt-2 text-center">
                            <b>Subtotal:</b>
                            <span class="badge bg-success text-white subtotal" id="s${denId}">${simbolo} ${subtotal.toFixed(2)}</span>
                        </div>
                        <input type="hidden" class="denominacion-id" value="${id}">
                        <!-- Label oculto para almacenar el tipo -->
                        <label id="tipoDenominacion-${id}" class="d-none">${tipo}</label>
                    </div>
                </div>
            </div>
        `;
  } else {
    return "";
  }
}

function crearSeccion(titulo, contenidoHTML) {
  return `
        <div class="row mt-4">
            <div class="col-12">
                <div class="p-2 mb-3 bg-success text-white text-center">
                    <b>${titulo}</b>
                </div>
                <div class="row gy-3">${contenidoHTML}</div>
            </div>
        </div>
    `;
}

function actualizarTotalGeneral() {
  let totalGeneral = 0;
  const tarjetas = document.querySelectorAll(".card");

  // Verifica que haya tarjetas
  if (tarjetas.length === 0) {
    console.warn("No se encontraron tarjetas con denominaciones.");
    return;
  }

  tarjetas.forEach((card) => {
    const cantidadInput = obtenerCantidadInput(card);

    if (!cantidadInput) {
      //console.warn('Cantidad input no encontrado en una tarjeta:', card.outerHTML);
      return;
    }

    const monto =
      parseFloat(
        card.querySelector(".text-center").textContent.replace(/[^\d.-]/g, "")
      ) || 0;
    const cantidad = parseFloat(cantidadInput.value) || 0;
    const subtotal = cantidad * monto;

    const subtotalElement = card.querySelector(".subtotal");
    if (subtotalElement) {
      subtotalElement.textContent = `Q ${subtotal.toFixed(2)}`;
    }

    totalGeneral += subtotal;
  });

  const totalGeneralInput = document.getElementById("totalGeneral");
  if (totalGeneralInput) {
    totalGeneralInput.value = totalGeneral.toFixed(2);
  } else {
    console.warn("Elemento totalGeneral no encontrado en el DOM.");
  }
}

function calcularSubtotal2(inputElement, denominacionMonto, denominacionId) {
  const cantidad = parseFloat(inputElement.value) || 0;
  const subtotal = cantidad * denominacionMonto;
  const subtotalElement = inputElement
    .closest(".card")
    .querySelector(".subtotal");
  if (subtotalElement) {
    subtotalElement.textContent = `Q ${subtotal.toFixed(2)}`;
  }
  actualizarTotalGeneral();
}

function obtenerCantidadInput(card) {
  return card.querySelector('input[type="number"]');
}

function validarmovimiento(cod, flagperm) {
  const totalGeneral =
    parseFloat(document.getElementById("totalGeneral").value) || 0;
  const debitarBoveda =
    document.getElementById("debitarBoveda")?.checked || false;
  if (debitarBoveda) {
    const selectBoveda = document.getElementById("selectBoveda");
    if (!selectBoveda || !selectBoveda.value) {
      alert("Debe seleccionar una bóveda para debitar.");
      return;
    }
  }

  const denominacionesData = [];
  document.querySelectorAll(".card").forEach((card) => {
    const denominacionIdElem = card.querySelector(".denominacion-id");
    const cantidadElem = card.querySelector('input[type="number"]');
    const montoElem = card.querySelector(".card-body .text-center");

    if (denominacionIdElem && cantidadElem && montoElem) {
      const denominacionId = denominacionIdElem.value;
      const cantidad = parseFloat(cantidadElem.value) || 0;
      const denominacionMonto =
        parseFloat(montoElem.textContent.replace(/[^\d.-]/g, "")) || 0;

      let tipoDenominacion = 0;
      const tipoElemento = card.querySelector(
        `#tipoDenominacion-${denominacionId}`
      );
      if (tipoElemento) {
        tipoDenominacion = parseInt(tipoElemento.textContent) || 0;
      }

      //console.log('Denominación:', denominacionId, 'Cantidad:', cantidad, 'Monto:', denominacionMonto, 'Tipo:', tipoDenominacion);
      if (cantidad > 0) {
        denominacionesData.push({
          id: denominacionId,
          cantidad: cantidad,
          monto: denominacionMonto,
          tipo: tipoDenominacion,
        });
      }
    }
  });

  if (denominacionesData.length === 0 && totalGeneral === 0) {
    alert("Debe ingresar al menos una denominación.");
    return;
  }

  const dataToSend = {
    cod: cod,
    flagperm: flagperm,
    totalGeneral: totalGeneral,
    denominaciones: denominacionesData,
    debitarBoveda: debitarBoveda,
    bovedaId: debitarBoveda
      ? document.getElementById("selectBoveda").value
      : null,
  };

  //console.log('Datos a enviar:', dataToSend);

  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "validarmovimiento", dat: JSON.stringify(dataToSend) },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (response) {
      //console.log("Respuesta del servidor:", response);  // Agregado para ver qué devuelve el servidor
      try {
        const result = JSON.parse(response);
        if (result.success) {
          Swal.fire({
            title: "La aprobacion se completo correctamente",
            icon: "success",
            draggable: true,
          });
          console.log(result.data);
          const myModal = bootstrap.Modal.getInstance(
            document.getElementById("modalDenominaciones")
          );
          myModal.hide();
        } else {
          //console.log(result.data);
          Swal.fire({
            title: "Se genero un error en el proceso de aprobacion",
            icon: "error",
            draggable: true,
          });
        }
      } catch (e) {
        alert("Error al procesar la respuesta del servidor.");
        console.error(e, response);
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });

  buscarmovi(flagperm);
}
