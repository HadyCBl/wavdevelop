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
  dire = "views/superadmin/" + dir + ".php";
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
//para recargar en el mismo archivo, solo mandar id del cuadro y el extra
function printdiv2(idiv, xtra) {
  loaderefect(1);
  condi = $("#condi").val();
  dir = $("#file").val();
  dire = "views/superadmin/" + dir + ".php";
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
function generico(inputs, selects, radios, condi, id, archivo, callback) {
  $.ajax({
    url: "../../src/cruds/crud_superadmin.php",
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
//#endregion
//#region funciones reutilzables
//funcion para eliminar cualquier registro
function eliminar(ideliminar, dir, xtra, condi) {
  dire = "../../src/cruds/" + dir + ".php";
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
            Swal.fire("Correcto", data2[0], "success");
            printdiv2("#cuadro", xtra);
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

//#endregion
//#region funciones de printdiv 5
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
//#endregion
//#region FUNCION PARA CARGAR UNA TABLA TIPO DataTable
function convertir_tabla_a_datatable(id_tabla) {
  $("#" + id_tabla)
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
//#endregion
//#region demas funciones
function HabDes_boton(valor) {
  if (valor == 1) {
    $("#btGuardar").hide();
    $("#btEditar").show();
  }
  if (valor == 0) {
    $("#btGuardar").show();
    $("#btEditar").hide();
  }
}

function abrir_modal(id_modal, id_hidden, dato) {
  $(id_modal).modal("show");
  $(id_hidden).val(dato);
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

//ingresar un nuevo permiso
function new_permise(condi) {
  var nombre = document.getElementById("Nombre").value;
  var estado = document.getElementById("estado").value;

  if (nombre.trim() === "") {
    Swal.fire("Error", "Ingrese un Nombre", "error");
    return;
  }
  if (estado.trim() === "") {
    Swal.fire("Error", "Seleccione un estado", "error");
    return;
  }
  // console.log(condi);
  // console.log("Nombre:", nombre);
  // console.log("Estado:", estado);
  //  return;
  $.ajax({
    url: "../../src/cruds/crud_superadmin.php",
    type: "POST",
    data: { condi, nombre, estado },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire("Correcto", data2[0], "success");
      } else {
        Swal.fire("Uff", data2[0], "error");
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function update_permise(condi) {
  var update_estado = document.getElementById("update_estado").value;
  var estado = document.getElementById("estado").value;

  if (update_estado.trim() === "") {
    Swal.fire("Error", "Ingrese un Nombre", "error");
    return;
  }
  if (estado.trim() === "") {
    Swal.fire("Error", "Seleccione un estado", "error");
    return;
  }

  console.log(condi);
  console.log("Nombre:", update_estado);
  console.log("Estado:", estado);

  $.ajax({
    url: "../../src/cruds/crud_superadmin.php",
    type: "POST",
    data: { condi, update_estado, estado },
    beforeSend: function () {
      // loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire("Correcto", data2[0], "success");
      } else {
        Swal.fire("Uff", data2[0], "error");
      }
    },
    complete: function () {
      // loaderefect(0);
    },
  });
}

//asignar permisos
function create_permisos(condi) {
  var id = document.getElementById("id_usuario").value;
  var update_estado = document.getElementById("update_estado2").value;
  var estado = document.getElementById("value_estado").value;
  var id_cargo = document.getElementById("id_cargo").value;

  if (id.trim() === "") {
    Swal.fire("Error", "Debe seleccionar un Usuario", "error");
    return;
  }
  if (update_estado.trim() === "") {
    Swal.fire("Error", "Debe seleccionar un Modulo", "error");
    return;
  }
  if (estado.trim() === "") {
    Swal.fire("Error", "Debe seleccionar un estado", "error");
    return;
  }
  // console.log(id);
  // console.log(condi);
  // console.log(id_cargo);
  // return;
  $.ajax({
    url: "../../src/cruds/crud_superadmin.php",
    type: "POST",
    data: { id, condi, update_estado, estado, id_cargo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire("Correcto", data2[0], "success");
      } else {
        Swal.fire("Uff", data2[0], "error");
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function viewAcordeon(id, nombre, apellido, rol, restringido) {
  //detruir tabla con infomacion anterior si tuviera
  destroyTable();
  var acordeon = document.getElementById("acordeon");
  if (acordeon.classList.contains("d-none")) {
    acordeon.classList.remove("d-none");
    document.getElementById("nombre").innerText = nombre;
    document.getElementById("apellido").innerText = apellido;
    document.getElementById("id").innerText = id;
    document.getElementById("rol").innerText = rol;
    document.getElementById("restringido").innerText = restringido;
  } else {
    acordeon.classList.add("d-none");
  }
}

function destroyTable() {
  var tablePlaceholder = document.getElementById("table-placeholder");
  if (tablePlaceholder) {
    tablePlaceholder.innerHTML = "";
  }
}

function search_id(condi) {
  var id = document.getElementById("id").innerText;
  // console.log(id);
  // console.log(condi);
  //return;
  $.ajax({
    url: "../../src/cruds/crud_superadmin.php",
    type: "POST",
    data: { id: id, condi: condi },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      // Inserta la tabla recibida en el lugar deseado
      $("#table-placeholder").html(data);
      document.getElementById("acordeon").classList.remove("d-none");
    },
    error: function () {
      Swal.fire("Uff", "Hubo un error al cargar los datos", "error");
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function updateEstado(condi, id_autorizacion, estado, modulo_area) {
  var estado_convertido = estado ? 1 : 0;

  // console.log("condi:", condi);
  // return;
  $.ajax({
    url: "../../src/cruds/crud_superadmin.php",
    type: "POST",
    data: { condi, id_autorizacion, estado_convertido, modulo_area },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire("Correcto", data2[0], "success");
      } else {
        Swal.fire("Uff", data2[0], "error");
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//replace select and button
function replaceInputs() {
  var inputNombre = document.getElementById("inputNombre");
  var selectEstado = document.getElementById("Select_modules");
  var estado = document.getElementById("estado");

  inputNombre.style.display = "none";
  selectEstado.style.display = "block";
  replaceButtons();
}

function replaceButtons() {
  var btnGuardar = document.getElementById("btGuardar");
  var btnActualizar = document.getElementById("btActualizar");

  btnGuardar.style.display = "none";
  btnActualizar.style.display = "block";
}

//cancel replace selects and buttons
function cancelReplace() {
  var inputNombre = document.getElementById("inputNombre");
  var selectEstado = document.getElementById("estado");
  var selectModules = document.getElementById("Select_modules");
  var btnGuardar = document.getElementById("btGuardar");
  var btnActualizar = document.getElementById("btActualizar");

  btnGuardar.style.display = "block";
  btnActualizar.style.display = "none";
  inputNombre.style.display = "block";
  selectEstado.style.display = "block";
  selectModules.style.display = "none";
}
