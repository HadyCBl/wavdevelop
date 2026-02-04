//Funcion para eliminar una fila de plan de pago
function killFila() {
  var tabla = document.getElementById("tbPlanPagos");
  var filas = tabla.getElementsByTagName("tr");
  var noFila = filas.length - 1;

  fila = parseInt($("#" + noFila + "idCon").text());
  filaData = parseInt($("#" + noFila + "idData").text());

  if (noFila > 0) {
    tabla.deleteRow(noFila);
    calPlanDePago();
  }
}

function eliminarFila(ideliminar, condi, archivo = 0) {
  //alert('eliminando fila')
  dire = "../../src/cruds/crud_credito_indi.php";
  //alert(ideliminar + ' ' + condi + ' ' + archivo);
  //dire = "../../src/cruds/crud_admincre.php";
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
            killFila();
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

//Funcion para recoger los datos de la tabla
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

function actMasiva(matriz, condi, extra) {
  //console.log(matriz);
  var matrizJSON = JSON.stringify(matriz);
  // console.log(matrizJSON);
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    type: "POST",
    data: { matriz: matrizJSON, condi, extra },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      // console.log(data);
      if (data2[1] == 1) {
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function inyecCod111(
  idElem,
  condi,
  extra = "0",
  url = "../../src/cruds/crud_credito_indi.php"
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

function inyecCod(
  idElem,
  condi,
  extra = "0",
  url = "../../src/cruds/crud_credito_indi.php"
) {
  $.ajax({
    url: url,
    type: "POST",
    data: { condi, extra },
    beforeSend: function () {
      loaderefect(1);
    },
  })
    .done(function (data) {
      // console.log(data);
      // return;

      if (condi === "PlanPagos") {
        $(idElem).html(data);
      }
      if (condi === "modal_aho_plz") {
        $(idElem).html(data);
      }
      if (condi === "cu_aho" || condi === "cu_apr") {
        $(idElem).html(data);
      }
      if (condi === "consulta_cre") {
        $(idElem).html(data);
      }
      if (condi === "cre_productos") {
        $(idElem).html(data);
      }
    })
    .always(function () {
      loaderefect(0);
    });
}

function opInyec(op) {
  switch (op) {
    case 0:
      inyecCod(
        "#consulta_cre",
        "consulta_cre",
        (extra = "0"),
        (url = "../../views/Creditos/cre_indi/inyecCod/inyecCod.php")
      );
      break;
    case 1:
      inyecCod(
        "#consulta_cre_producto",
        "cre_productos",
        (extra = "0"),
        (url = "../../views/Creditos/cre_indi/inyecCod/inyecCod.php")
      );
      break;
  }
}

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

function capData(dataPhp, dataJava) {
  // console.log("Data de php--> " + dataPhp);
  // console.log("Data de js--> " + dataJava);

  // console.log("Inicio aqui");
  let data = dataPhp.split("||");
  for (let i = 0; i < dataJava.length; i++) {
    // console.log("Dato a insertar: " + dataJava[i]);
    if ($(dataJava[i]).is("input")) {
      $(dataJava[i]).val(data[i]);
    }
    if ($(dataJava[i]).is("label")) {
      $(dataJava[i]).text(data[i]);
    }
    if ($(dataJava[i]).is("textarea")) {
      $(dataJava[i]).val(data[i]);
    }
  }
}

//Funcion para capturar datos
function capDataEsp(dataPhp, dataJava = 0, pos = []) {
  let data = dataPhp.split("||");

  // console.log("Data de PHP--> " + data);
  // console.log("Data de JS--> " + dataJava);

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

function confirmchangestatus(button) {
  var ccodcta = $(button).data("ccodcta");
  var statuss = $(button).data("statuss");

  Swal.fire({
    title: "¿Estás seguro de realizar este proceso? Cuenta: " + ccodcta,
    text: "Esta acción no se puede deshacer. y se eliminaran datos Importantes",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: "Sí, Proceder",
  }).then((result) => {
    if (result.isConfirmed) {
      generico([], [], [], "changestatus", 0, [ccodcta, statuss]);
    }
  });
}

//Editar Garantia
function editargarantia(datos) {
  dato = datos.split("||");
  printdiv2("#cuadro", dato[0], 1);
}

//Capturar cliente
function cerrarModal(modalCloss) {
  $(modalCloss).modal("hide"); // CERRAR MODAL
}

//Capturar fiador
function capfiador(datos) {
  let dato = datos.split("||");
  console.log(dato);
  $("#nameFiador").val(dato[2]);
  $("#codigoFiador").val(dato[1]);
  // $("#modalFiador").modal("hide"); // CERRAR MODAL
}

/* PARA LOS SELECT QUE TIENEN, QUE BUSCAR A LOS DEPARTAMENTOS */
function municipio(idmuni, iddepa) {
  //alert(iddepa);
  aux = 0;
  var condi = "departa";
  $.ajax({
    url: "../../src/general.php",
    method: "POST",
    data: { iddepa: iddepa, condi: condi },
    success: function (data) {
      $(idmuni).html(data);
    },
  });
}

function focus(data) {
  $(data).focus();
}

//Para cargar los archivos en el fronend
// Función para procesar un archivo cargado
function readFile(input) {
  if (input.files && input.files[0]) {
    var file = input.files[0];
    var fileName = file.name;
    var fileType = file.type;
    var fileSize = (file.size / 1024).toFixed(2) + " KB";
    var reader = new FileReader();

    // Actualizar el ícono según el tipo de archivo
    var iconElement = $("#tipoArchivo i");
    if (fileType.startsWith("image/")) {
      iconElement.removeClass().addClass("fa fa-image text-success");
    } else if (fileType === "application/pdf") {
      iconElement.removeClass().addClass("fa fa-file-pdf text-danger");
    } else {
      iconElement.removeClass().addClass("fa fa-file text-primary");
    }

    reader.onload = function (e) {
      var contenedorVista = $("#contenedorVista");
      contenedorVista.empty(); // Limpiar el contenedor

      if (fileType.startsWith("image/")) {
        // Es una imagen, mostrar vista previa
        contenedorVista.html(`
          <img id="vistaPrevia" class="img-thumbnail" style="max-width:100%; max-height:200px;" src="${e.target.result}" />
          <p class="mt-2 mb-0">${fileName} (${fileSize})</p>
        `);
      } else if (fileType === "application/pdf") {
        // Es un PDF, mostrar icono y opciones para descargar y visualizar
        var dataUrl = e.target.result;
        contenedorVista.html(`
          <div class="pdf-preview p-3 border rounded" style="max-width:100%;">
            <i class="fa fa-file-pdf text-danger" style="font-size:48px;"></i>
            <p class="mt-2 mb-0">${fileName} (${fileSize})</p>
            <div class="mt-2">
              <a href="${dataUrl}" class="btn btn-sm btn-outline-primary" download="${fileName}">
                <i class="fa fa-download"></i> Descargar PDF
              </a>
              <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="previewPDF('${dataUrl}')">
                <i class="fa fa-eye"></i> Ver PDF
              </button>
            </div>
          </div>
        `);
      } else {
        // Otro tipo de archivo no soportado
        contenedorVista.html(`
          <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            El archivo seleccionado no es compatible con la vista previa.
          </div>
        `);
      }
    };

    reader.readAsDataURL(file);
  } else {
    // No hay archivo seleccionado, mostrar placeholder
    $("#contenedorVista").html(`
      <div id="vistaPrevia" class="text-center p-3 border rounded">
        <i class="fa fa-upload text-muted" style="font-size:48px;"></i>
        <p class="mt-2 mb-0 text-muted">No hay archivo seleccionado</p>
      </div>
    `);
    $("#tipoArchivo i").removeClass().addClass("fa fa-file");
  }
}

// Función para mostrar una vista previa de la imagen
function previewImage(imageDataUrl, fileName) {
  // Abrir en una nueva ventana
  var imgPreviewWindow = window.open("", "_blank");
  imgPreviewWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>${fileName || "Vista previa de imagen"}</title>
      <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: auto; background: #f0f0f0; text-align: center; }
        .container { display: flex; flex-direction: column; justify-content: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .image-container { margin: 0 auto; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.15); padding: 10px; border-radius: 5px; }
        img { max-width: 100%; max-height: 80vh; object-fit: contain; }
        h2 { font-family: Arial, sans-serif; color: #333; margin-bottom: 20px; }
        .download-btn {
          display: inline-block;
          margin: 15px 0;
          padding: 8px 20px;
          background: #007bff;
          color: white;
          text-decoration: none;
          border-radius: 4px;
          font-family: Arial, sans-serif;
          transition: background 0.3s;
        }
        .download-btn:hover { background: #0056b3; }
      </style>
    </head>
    <body>
      <div class="container">
        <h2>${fileName || "Vista previa de imagen"}</h2>
        <div class="image-container">
          <img src="${imageDataUrl}" alt="${fileName || "Vista previa"}">
        </div>
        <a href="${imageDataUrl}" download="${fileName || "imagen"}" class="download-btn">
          Descargar imagen
        </a>
      </div>
    </body>
    </html>
  `);
  imgPreviewWindow.document.close();
}

// Función para mostrar una vista previa del PDF
function previewPDF(pdfDataUrl) {
  // Solución 1: Abrir en una nueva ventana con opciones mejoradas
  var pdfPreviewWindow = window.open("", "_blank");
  pdfPreviewWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Vista previa del PDF</title>
      <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; }
        #pdfContainer { width: 100%; height: 100%; }
        .fallbackMessage { 
          display: none; 
          text-align: center; 
          margin: 50px auto; 
          max-width: 600px; 
          padding: 20px; 
          background: #f8f9fa; 
          border: 1px solid #dee2e6;
          border-radius: 5px;
        }
      </style>
    </head>
    <body>
      <div id="fallbackMessage" class="fallbackMessage">
        <h3>Tu navegador no puede mostrar el PDF directamente</h3>
        <p>Por favor, usa una de estas opciones:</p>
        <p>
          <a href="${pdfDataUrl}" download="documento.pdf" class="downloadBtn" style="display:inline-block; margin:10px; padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;">
            Descargar PDF
          </a>
        </p>
      </div>
      <object id="pdfContainer" data="${pdfDataUrl}" type="application/pdf" width="100%" height="100%">
        <embed src="${pdfDataUrl}" type="application/pdf" />
        <p>Este navegador no admite la visualización de PDF. <a href="${pdfDataUrl}" download="documento.pdf">Descargar PDF</a></p>
      </object>
      <script>
        // Detectar si el navegador no puede mostrar PDFs nativamente
        // var pdfObject = document.getElementById('pdfContainer');
        // setTimeout(function() {
        //   if (pdfObject.getElementsByTagName('embed').length > 0 && 
        //       pdfObject.getElementsByTagName('embed')[0].clientHeight < 50) {
        //     document.getElementById('fallbackMessage').style.display = 'block';
        //     pdfObject.style.display = 'none';
        //   }
        // }, 1000);
      </script>
    </body>
    </html>
  `);
}

function readImage(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function (e) {
      $("#vistaPrevia").attr("src", e.target.result); // Renderizamos la imagen
    };
    reader.readAsDataURL(input.files[0]);
  }
}

//Para guardar la imagen o el archivo PDF y su ruta en la db
function saveimage() {
  var ccodcli = document.getElementById("idGarantia").value; //Se obtienen el codigo de la grantia
  if (ccodcli != "") {
    var fileInput = document.getElementById("foto");
    var fileImage = $("#foto").val();
    if (fileImage != "") {
      var file = fileInput.files[0];
      var fileName = file.name;
      var fileType = file.type;

      var form_data = new FormData();
      var condi = "ingresoimg";
      form_data.append("ccodcli", ccodcli);
      form_data.append("condi", condi);
      form_data.append("fileImage", file);
      form_data.append("fileType", fileType); // Enviamos el tipo de archivo para procesarlo adecuadamente en el servidor

      let aja = new XMLHttpRequest();
      let progresbar = document.getElementById("barprogress");
      progresbar.style = "display: yes";
      let progresbardiv = document.getElementById("progressdiv");
      let progresbarcancel = document.getElementById("cancelprogress");

      aja.upload.addEventListener("progress", function (e) {
        let porcentaje = (e.loaded / e.total) * 100;
        progresbar.style = "Width:" + Math.round(porcentaje) + "%";
        if (porcentaje >= 100) {
        }
      });

      aja.onreadystatechange = function () {
        if (aja.readyState == 4 && aja.status == 200) {
          var data = $.parseJSON(aja.responseText);
          var uploadResult = data[1];

          if (uploadResult == "1") {
            progresbardiv.style = "display: yes";
            progresbar.style = "display: none";
            progresbarcancel.style = "display: none";
            Swal.fire({
              icon: "success",
              title: "Muy Bien!",
              text: data[0],
            });
          }

          if (uploadResult == "0") {
            progresbardiv.style = "display: none";
            progresbarcancel.style = "display: yes";
            Swal.fire({
              icon: "warning",
              title: "Error!",
              text: data[0],
            });
          }
        }
      };
      aja.open("POST", "../../src/cruds/crud_credito_indi.php");
      aja.send(form_data);
    } else {
      Swal.fire({
        icon: "warning",
        title: "Error!",
        text: "No se ingreso archivo de imagen",
      });
    }
  } else {
    Swal.fire({
      icon: "warning",
      title: "Error!",
      text: "No se selecciono un cliente",
    });
  }
}

//Scrip para eliminar
//funcion para eliminar una garantia
function eliminar(ideliminar) {
  var codCli = $("#codCliente").val();
  condi = "eliminaGarantia";
  archivo = $("#idUser").val();
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
        data: { condi, ideliminar, archivo },
        beforeSend: function () {
          loaderefect(1);
        },
        success: function (data) {
          const data2 = JSON.parse(data);

          if (data2[1] == "1") {
            Swal.fire("Correcto", "Eliminado", "success");
            tbGarantias(codCli);
          } else Swal.fire("X(", data2[0], "error");
        },
        complete: function () {
          loaderefect(0);
        },
      });
    }
  });
}

//#region LOADER
//FUNCION PARA EL EFECTO DEL LOADER
function loaderefect(sh) {
  // console.log("Loadergg: " + sh);
  const LOADING = document.querySelector(".loader-container");
  switch (sh) {
    case 1:
      // console.log("Loadere: 1");
      LOADING.classList.remove("loading--hide");
      LOADING.classList.add("loading--show");
      break;
    case 0:
      // console.log("Loaderiu: 0");
      LOADING.classList.add("loading--hide");
      LOADING.classList.remove("loading--show");
      break;
  }
}
//#endregion
//MODULO DE CREDITOS PARA EL INGRESO DE DATOS
function printdiv2(idiv, xtra, op = 0) {
  // console.log("printdiv2 called with iii");
  loaderefect(1);
  condi = $("#condi").val();
  dir = $("#file").val();
  dire = "./cre_indi/" + dir + ".php";
  option = op;
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra, option },
    beforeSend: function () {
      // console.log("printdiv2 beforeSend");
      loaderefect(1);
    },
    success: function (data) {
      // console.log("printdiv2 success");
      $(idiv).html(data);
    },
    complete: function () {
      // console.log("printdiv2 complete");
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
          window.location.href = data2.url;
        }, 2000);
      } else {
        console.log(xhr);
      }
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

//FUNCION PRINTDIV UTILIZADA PARA MOSTRAR LOS DATOS FALTANTES
function printdiv(condi, idiv, dir, xtra) {
  dire = "./cre_indi/" + dir + ".php";
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
// REVISAR ESTA FUNCION
function creperi(condi, idiv, dir, xtra, callback) {
  printdiv("prdscre", "#peri", dir, xtra);
  dire = "./cre_indi/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      $(idiv).html(data);
      if (typeof callback === "function") {
        callback();
      }
      if (xtra != "Flat") {
        changestatus();
      }
    },
  });
}

//FUNCIONES PARA CREACION DE GARANTIAS DESDE CREDITOS
function abrir_modal_cualquiera(identificador) {
  // console.log("Inicio");
  $(identificador).modal("show");
}

function cerrar_modal_cualquiera(identificador) {
  $(identificador).modal("hide");
}

function abrir_modal_garantias(identificador, valor) {
  abrir_modal_cualquiera(identificador);
}
//FUNCION PARA ABRIR MODALES EN DONDE SE LES PASE UN PARAMETRO A UN INPUT TEXT
function abrir_modal_cualquiera_con_valor(
  identificador,
  id_hidden,
  valores,
  campos
) {
  $(identificador).modal("show");
  $(id_hidden).val(valores);
  var datos = obtener_valores_modal_hidden(id_hidden);
  for (let index = 0; index < datos.length; index++) {
    $(campos[index]).val(datos[index]);
  }
}

function cerrar_modal_cualquiera_con_valor(identificador, id_hidden, campos) {
  $(identificador).modal("hide");
  $(id_hidden).val("");
  for (let index = 0; index < campos.length; index++) {
    $(campos[index]).val("");
  }
}

function obtener_valores_modal_hidden(id_hidden) {
  var todo = $(id_hidden).val().split(",");
  return todo;
}

//FUNCION PARA GRABAR O CANCELAR LA CANCELACION DE UN CREDITO
function grabar_cancelar_credito(id_hidden, xtra) {
  var ideliminar = obtener_valores_modal_hidden(id_hidden);
  var rechazo = $("#rechazoid").val();
  ideliminar.push(rechazo);
  // console.log(ideliminar);
  eliminar_mejorado(
    ideliminar,
    "0",
    "rechazar_individual",
    "¿Esta de seguro de cancelar el crédito?"
  );
}

function consultar_reporte(file, bandera) {
  return new Promise(function (resolve, reject) {
    if (bandera == 0) {
      resolve("Aprobado");
    }
    $.ajax({
      url: "../../src/cruds/crud_credito_indi.php",
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

function reportes_xls(datos, tipo, file) {
  // console.log(datos,tipo,file);
  // return;
  loaderefect(1);
  var cod_cuenta = document.getElementById("codCu").value;
  console.log(cod_cuenta);
  var url =
    "cre_indi/reportes/" +
    file +
    ".php?cod_cuenta=" +
    encodeURIComponent(cod_cuenta);
  // Redirigir al navegador
  window.location.href = url;
  loaderefect(0);
}

//FUNCION PARA LOS REPORTES EN CREDITOS INDIVIDUALES
function reportes(datos, tipo, file, download, bandera = 0) {
  var datosval = [];
  datosval[0] = getinputsval(datos[0]);
  datosval[1] = getselectsval(datos[1]);
  datosval[2] = getradiosval(datos[2]);
  datosval[3] = datos[3];
  //console.log(tipo);
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
      var url = "cre_indi/reportes/" + file + ".php";
      $.ajax({
        url: url,
        async: true,
        type: "POST",
        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
        dataType: "html",
        data: { datosval, tipo },
        beforeSend: function () {
          loaderefect(1);
        },
        success: function (data) {
          // console.log(data);
          var opResult = JSON.parse(data);
          //  console.log(opResult)
          if (opResult.status == 1) {
            var extension = "extension" in opResult ? opResult.extension : tipo;
            download = "download" in opResult ? opResult.download : download;
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
                    extension +
                    "'>"
                );
                $("body").append($a);
                $a[0].click();
                $a.remove();
                break;
            }
            if (fileaux != 18 && fileaux != 19 && fileaux != 20) {
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
        complete: function (data) {
          loaderefect(0);
          // console.log(data)
        },
      });
      //-------------------------------------FIN SEGUNDA FUNCION
    })
    .catch(function (error) {
      Swal.fire("Uff", error, "error");
    });
}

//RELACION CON EL DESEMBOLSO DE CRÉDITOS INDIVIDUALES PARA ELIMINAR
function abrir_modal_for_delete(id_modal, id_hidden, dato) {
  $(id_modal).modal("show");
  $(id_hidden).val(dato);
  console.log(id_modal, id_hidden, dato);
  return;
}

//RELACION CON EL DESEMBOLSO DE CRÉDITOS INDIVIDUALES
function abrir_modal(id_modal, id_hidden, dato) {
  $(id_modal).modal("show");
  $(id_hidden).val(dato);
}

//seleccionar cuenta mejorado
function seleccionar_cuenta_ctb2(id_hidden, valores) {
  printdiv5(id_hidden, valores);
}
function seleccionar_credito_a_desembolsar(id_hidden, valores) {
  printdiv5(id_hidden, valores);
}

function cerrar_modal(id_modal, estado, id_hidden) {
  $(id_modal).modal(estado);
  $(id_hidden).val("");
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
    // p.removeAttribute("hidden");
    document.getElementById(ocultar[i]).removeAttribute("hidden");
    document.getElementById(ocultar[i]).style.display = "none";
    i++;
  }
}

//CONSULTAR GASTOS ADMINISTRATIVOS
function consultar_gastos_monto(codcredito) {
  //consultar a la base de datos
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "gastos_desembolsos", id: codcredito },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      // console.log(data2);
      if (data2[1] == "1") {
        //imprimir en los inputs
        $("#ccapital").val(data2[2]);
        $("#gastos").val(parseFloat(data2[3]).toFixed(2));
        $("#desembolsar").val(parseFloat(data2[4]).toFixed(2));
        $("#cantidad").val(data2[4]);
        $("#paguese").val(data2[5]);
        cantidad_a_letras(data2[4]);
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

//MOSTRAR GASTOS EN LA TABLA
function mostrar_tabla_gastos(codcredito) {
  $("#tabla_gastos_desembolso")
    .on("search.dt")
    .DataTable({
      aProcessing: true,
      aServerSide: true,
      ordering: false,
      lengthMenu: [
        [5, 10, 15, -1],
        ["5 filas", "10 filas", "15 filas", "Mostrar todos"],
      ],
      ajax: {
        url: "../../src/cruds/crud_credito_indi.php",
        type: "POST",
        beforeSend: function () {
          loaderefect(1);
        },
        data: {
          condi: "lista_gastos",
          id: codcredito,
          filcuenta: 0,
        },
        dataType: "json",
        complete: function () {
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
        $("#cuentaid").append("<option value=''></option>");
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function buscar_actividadeconomica(idsector) {
  //consultar a la base de datos
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { condi: "buscar_actividadeconomica", id: idsector },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      // console.log(data2);
      if (data2[1] == "1") {
        $("#actividadeconomica").empty();
        for (var i = 0; i < data2[2].length; i++) {
          $("#actividadeconomica").append(
            "<option value='" +
              data2[2][i]["id"] +
              "'>" +
              data2[2][i]["descripcion"] +
              "</option>"
          );
        }
      } else {
        $("#actividadeconomica").empty();
        $("#actividadeconomica").append(
          "<option value='0' selected>Seleccione una actividad económica</option>"
        );
      }
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

function cantidad_a_letras(monto) {
  let montoNumerico = Math.abs(parseFloat(monto));
  let montoredondeado = montoNumerico.toFixed(2);
  let numero_formateado = montoredondeado.split(".");
  let texto = numeroALetras(Number(numero_formateado[0]), {
    plural: " ",
    singular: " ",
  }).trim();
  if (monto < 0) {
    texto = "DEBE " + texto;
  }
  $("#numletras").val(texto + " " + numero_formateado[1] + "/100");
}

//FUNCION PARA MOSTRAR Y OCULTAR DIV
function ocultar_div_desembolso(seleccion) {
  if (seleccion == "1") {
    document.getElementById("region_cheque").style.display = "none";
    document.getElementById("region_transferencia").style.display = "none";
  }
  if (seleccion == "2") {
    document.getElementById("region_cheque").style.display = "block";
    document.getElementById("region_transferencia").style.display = "none";
    //buscar_cuentas();
  }

  if (seleccion == "3") {
    document.getElementById("region_transferencia").style.display = "block";
    document.getElementById("region_cheque").style.display = "none";
    buscar_cuentas_ahorro_cli();
  }
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
  // console.log("Datos procesados y enviados");
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
  // loaderefect(1);
}
//--
function generico(inputs, selects, radios, condi, id, archivo, callback) {
  // console.log("Inputs " + inputs + " selects " + selects + " radios " + radios); return;
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: { inputs, selects, radios, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },

    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[1] == "1") {
        Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
        //Garantias
        if (condi === "restructuracionPpg") {
          limpiarForm(["formulario"]);
          ocultaHabilita(["card1", "card2"], 0);
          return;
        }

        //SECCION DE DESEMBOLSOS PARA LOS CREDITOS INDIVIDUALES
        if (condi == "create_desembolso") {
          reportes([[], [], [], [data2[2]]], "pdf", 18, 0, 1);
          if (data2[3] == "cheque") {
            if (data2[5] != "") {
              cheque_desembolso([[], [], [], [data2[4]]], "pdf", 13, 0, 1);
            } else {
              Swal.fire({
                icon: "success",
                title: "Muy Bien!",
                text: "Desembolso con cheque generado correctamente, debe ir al apartado de emisión de cheques para realizar la impresión de este",
              });
            }
          }
          printdiv2("#cuadro", id);
        }

        //SECCION DE IMPRESION DE FICHA DE SOLICITUD
        if (condi == "create_solicitud") {
          reportes([[], [], [], [data2[2]]], `pdf`, 29, 0, 1);
        }
        //SECCION DE IMPRESION DE FICHA DE ANALISIS
        if (condi == "create_analisis") {
          reportes([[], [], [], [data2[2]]], `pdf`, 30, 0, 1);
          imp_documentos_legales(
            [[], [], [], [data2[2]]],
            "pdf",
            "20",
            0,
            "dictamen",
            1
          );
        }
        if (condi == "create_aprobacion") {
          reportes([[], [], [], [data2[2]]], `pdf`, 31, 0, 1);
          imp_documentos_legales(
            [[], [], [], [data2[2]]],
            "pdf",
            "19",
            0,
            "contrato",
            1
          );
        }
        printdiv2("#cuadro", id);
        if (typeof callback === "function") {
          callback(data2);
        }
      } else if (data2[1] == "2") {
        Swal.fire({ icon: "warning", title: "¡Alerta!", text: data2[0] });
      } else {
        var relogin = "relogin" in data2 ? data2.relogin : 0;

        if (relogin == 1) {
          showRenewModalSession(
            data2[0],
            function () {
              generico(inputs, selects, radios, condi, id, archivo, callback);
            },
            data2.key
          );
        } else {
          Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
        }
      }
    },
    complete: function () {
      // console.log("complete on generico xd")
      loaderefect(0);
    },
  });
}

function eliminar_mejorado(ideliminar, xtra, condi, pregunta) {
  Swal.fire({
    title: pregunta,
    showDenyButton: true,
    confirmButtonText: "Confirmar",
    denyButtonText: `Cancelar`,
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        url: "../../src/cruds/crud_credito_indi.php",
        method: "POST",
        data: { condi, ideliminar },
        beforeSend: function () {
          loaderefect(1);
        },
        success: function (data) {
          const data2 = JSON.parse(data);
          if (data2[1] == "1") {
            Swal.fire("Correcto", data2[0], "success");
            if (condi == "rechazar_individual") {
              cerrar_modal_cualquiera_con_valor(
                "#modal_cancelar_credito",
                "#id_hidden",
                [`#credito`, `#nombre`]
              );
            }
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

//FIN DE DESEMBOLSO DE CREDITOS INDIVIDUALES

//IMPRESION DE CONTRATO O DICTAMEN
function imp_documentos_legales(
  datos,
  tipo,
  file,
  download,
  documento,
  bandera = 0
) {
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
      Swal.fire({
        allowOutsideClick: false,
        title: "¿Desea generar la impresión del " + documento + "?",
        showDenyButton: true,
        confirmButtonText: "Si",
        denyButtonText: `No`,
      }).then((result) => {
        if (result.isConfirmed) {
          var datosval = [];
          datosval[0] = getinputsval(datos[0]);
          datosval[1] = getselectsval(datos[1]);
          datosval[2] = getradiosval(datos[2]);
          datosval[3] = datos[3];
          var url = "cre_indi/reportes/" + file + ".php";
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
        }
      });
      //FIN DE REPORTE
    })
    .catch(function (error) {
      Swal.fire("Uff", error, "error");
    });
}

//FUNCION PARA IMPRIMIR CHEQUE AL MOMENTO DE DESEMBOLSAR
function cheque_desembolso(datos, tipo, file, download, bandera = 0) {
  consultar_reporte(file, bandera)
    .then(function (action) {
      //PARTE ENCARGADA DE GENERAR EL REPORTE
      if (bandera == 1) {
        file = action;
      } else {
        file = file;
      }
      //ESPACIO QUE GENERA EL REPORTE
      Swal.fire({
        allowOutsideClick: false,
        title: "¿Desea generar la impresión del cheque?",
        showDenyButton: true,
        confirmButtonText: "Si",
        denyButtonText: `No`,
      }).then((result) => {
        if (result.isConfirmed) {
          var datosval = [];
          datosval[0] = getinputsval(datos[0]);
          datosval[1] = getselectsval(datos[1]);
          datosval[2] = getradiosval(datos[2]);
          datosval[3] = datos[3];
          var url = "../bancos/reportes/" + file + ".php";
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
        }
      });
      //FIN DE ESPACIO PARA REPORTE
    })
    .catch(function (error) {
      Swal.fire("Uff", error, "error");
    });
}

//FUNCION PARA BUSCAR CUENTAS DE AHORRO DEL CLIENTE SELECCIONADO
function buscar_cuentas_ahorro_cli() {
  codcli = document.getElementById("id_cod_cliente").value;
  if (codcli != "") {
    //consultar a la base de datos
    $.ajax({
      url: "../../src/cruds/crud_credito_indi.php",
      method: "POST",
      data: { condi: "buscar_cuentas_ahorro_cli", id: codcli },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        const data2 = JSON.parse(data);
        if (data2[1] == "1") {
          $("#cuentaaho").empty();
          for (var i = 0; i < data2[2].length; i++) {
            $("#cuentaaho").append(
              "<option value='" +
                data2[2][i]["ccodaho"] +
                "'>" +
                data2[2][i]["ccodaho"] +
                " - " +
                data2[2][i]["nombre"] +
                "</option>"
            );
          }
        } else {
          $("#cuentaaho").empty();
          $("#cuentaaho").append(
            "<option value=''>Seleccione una cuenta de ahorro</option>"
          );
          Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
        }
      },
      complete: function () {
        loaderefect(0);
      },
    });
  }
}

//FUNCION PARA CREAR CONCEPTO POR DEFAULT
function concepto_default(nombre, ccodcta) {
  nombremayusculas = nombre.toUpperCase();
  // console.log(nombremayusculas);
  texto = "DESEMBOLSO DE CRÉDITO A NOMBRE DE " + nombremayusculas;
  $("#glosa").val(texto);
}

//FUNCION PARA RECOLECTAR CHECKBOXS MARCADOS
function recoletar_checks() {
  var permisos = [];
  var index = 0;
  var checkboxsubmenus = document.getElementsByClassName("S");
  for (var checkboxs of checkboxsubmenus) {
    if (checkboxs.checked) {
      permisos[index] = checkboxs.value;
      index++;
    }
  }
  return permisos;
}

//FUNCION PARA MARCAR LAS GARANTIAS MARCADAS
function marcar_garantias_recuperadas(data) {
  // console.log(data);
  for (let index = 0; index < data.length; index++) {
    var check = document.getElementById("S_" + data[index]["id"]);
    check.checked = true;
  }
}

function suma_garantias_de_chequeados(idinput) {
  var checkboxsubmenus = document.getElementsByClassName("S");
  var total = 0;
  for (var checkboxs of checkboxsubmenus) {
    if (checkboxs.checked) {
      var valor = document.getElementById("MA_" + checkboxs.value).innerText;
      total = total + Number(valor);
    }
  }
  $(idinput).val(total);
}

let numOr0 = (n) => (isNaN(parseFloat(n)) ? 0 : parseFloat(n));
function summongas(nocuenta, id) {
  var i = 0;
  let filtot = [];
  while (i != -1) {
    filtot[i] = getinputsval(["mon_" + i + "_" + nocuenta]);
    i = !!document.getElementById("mon_" + (i + 1) + "_" + nocuenta)
      ? i + 1
      : -1;
  }

  let gastos = parseFloat(
    filtot.reduce((a, b) => numOr0(a) + numOr0(b))
  ).toFixed(2);
  let capital = $("#ccapital").val();
  $("#gastos").val(gastos);
  let desembolsar = parseFloat(capital - gastos).toFixed(2);
  $("#desembolsar").val(desembolsar);
  $("#cantidad").val(desembolsar);
  cantidad_a_letras(desembolsar);

  // VALIDAR SI ES UNA MIXTO QUE SE VUELVA EL CHEQUE IGUAL AL MONTO
  var condi = document.getElementById("condi").value;
  if (condi == "INDI_DESEM_MULTI") {
    calcularCheque();
    //alert("HOLA "+ condi);
  }
}

//NUEVA FUNCION PARA CALCULAR TODOS LOS GASTOS:
function calculateExpense() {
  const tabla = document.getElementById("tabla_gastos_desembolso");
  const filas = tabla.querySelector("tbody")?.rows;
  let otrosGastos = 0;
  let gastosRefinance = 0;
  if (filas) {
    otrosGastos = Array.from(filas).reduce((total, fila) => {
      const celda = fila.cells[3]; // Columna "Monto"
      if (!celda) return total;

      const input = celda.querySelector("input");
      const valor = parseFloat(input?.value || celda.textContent) || 0;

      return total + valor;
    }, 0);
  }

  let totalSaldoCapital = 0;
  let totalInteres = 0;
  // Recorremos cada fila de la tabla
  document
    .querySelectorAll("#table-refinance tbody tr")
    .forEach(function (fila) {
      let checkbox = fila.querySelector('input[type="checkbox"]');
      if (checkbox && checkbox.checked) {
        let saldoCapital = parseFloat(fila.cells[1].innerText.trim()) || 0;
        let interes =
          parseFloat(fila.cells[2].querySelector("input").value) || 0;

        totalSaldoCapital += saldoCapital;
        totalInteres += interes;
      }
    });
  gastosRefinance = totalSaldoCapital + totalInteres;
  let gastoTotal = otrosGastos + gastosRefinance;

  let capital = $("#ccapital").val();
  let desembolsar = parseFloat(capital - gastoTotal).toFixed(2);
  // console.log(desembolsar);
  $("#gastos").val(parseFloat(gastoTotal).toFixed(2));
  $("#desembolsar").val(desembolsar);
  $("#cantidad").val(desembolsar);
  cantidad_a_letras(desembolsar);

  // VALIDAR SI ES UNA MIXTO QUE SE VUELVA EL CHEQUE IGUAL AL MONTO
  var condi = document.getElementById("condi").value;
  if (condi == "INDI_DESEM_MULTI") {
    calcularCheque();
  }
}

function savedesem(idusu, idagencia) {
  var filgas = [];
  let cuenta = $("#codcredito").val();
  let nocuenta = cuenta.substring(8, 20);
  filgas[0] = [0, 0, 0];
  k = 0;
  while (k != -1) {
    if (!!document.getElementById("idg_" + k + "_" + nocuenta));
    else break;
    filgas[k] = getinputsval([
      "idg_" + k + "_" + nocuenta,
      "mon_" + k + "_" + nocuenta,
      "con_" + k + "_" + nocuenta,
    ]);
    filgas[k][1] = numOr0(filgas[k][1]);
    k++;
  }
  refinance = obtenerDatosSeleccionados();
  // console.log(filgas)
  // console.log(refinance)
  // console.log(idPro_gas)
  // console.log(afec)
  // console.log(ahorro)
  obtiene(
    [
      `id_cod_cliente`,
      `nomcli`,
      `codagencia`,
      `codproducto`,
      `codcredito`,
      `ccapital`,
      `gastos`,
      `desembolsar`,
      `cantidad`,
      `numcheque`,
      `paguese`,
      `numletras`,
      `glosa`,
      `numdoc`
    ],
    [`tipo_desembolso`, `negociable`, `bancoid`, `cuentaid`, `cuentaaho`],
    [],
    `create_desembolso`,
    `0`,
    [idusu, idagencia, filgas, idPro_gas, afec, ahorro, refinance]
  );
}

//*************************************INI
//BUSCA LAS CUENTAS DE AHO Y APR DE UN CLIENTE
var idPro_gas = 0;
var afec = 0;
var ahorro = 0;
function bus_ahoVin(codCli) {
  // Usando jQuery para obtener el valor seleccionado del radio button
  var fila = $('input[name="data_tipcu"]:checked').val();
  var data_fila = document.getElementById(fila);

  // Hacer algo con el valor seleccionado
  if (data_fila.cells[4].innerText === "Cuenta de Ahorro") {
    idPro_gas = data_fila.cells[0].innerText;
    afec = 1;
    inyecCod(
      "#tip_cu",
      "cu_aho",
      codCli,
      (url = "../../src/cris_modales/mdls_aho_apr.php")
    );
  } else {
    inyecCod(
      "#tip_cu",
      "cu_apr",
      codCli,
      (url = "../../src/cris_modales/mdls_aho_apr.php")
    );
    afec = 2;
    idPro_gas = data_fila.cells[0].innerText;
  }
}
//PARA SELECCIONAR EL TIPO DE CUENTA
function selec_cu() {
  var fila = $('input[name="cu_aho_apr"]:checked').val();
  var data_fila = document.getElementById(fila);
  ahorro = data_fila.cells[1].innerText;
}
//PARA OMITIR LA CUENTA DE AHORRO VINCULADO...
function omitir_aho_vin() {
  idPro_gas = 0;
  ahorro = 0;
  afec = 0;
  ac_even("aho_vin", "vista", 0);
}
//VALIDA DATOS DE AHORROS VINCULADOS
function val_aho_vin() {
  if (idPro_gas > 0 && ahorro == 0) {
    $("#ar_ahoVin").focus();
    // Obtenemos la posición del contenedor y hacemos que la página se desplace hacia esa posición
    var posicion = $("#ar_ahoVin").offset().top;
    $("html, body").animate({ scrollTop: posicion }, 1000);

    Swal.fire({
      icon: "question",
      title: "Ahorro vinculado…",
      text: "Favor de seleccionar una cuenta o puede omitir el proceso haciendo click en el booton.",
    });
    return false;
  } else {
    return true;
  }
}
//*************************************FIN

// NEGROY FUNCION DE DESEMBOLSO MULTIPLE
function calcularCheque() {
  // Obtener los valores actuales
  var total = parseFloat(document.getElementById("desembolsar").value);
  var efectivo = parseFloat(document.getElementById("MontoEFECTIVO").value);

  // Validar que el efectivo no sea mayor al total
  if (efectivo <= total) {
    // Calcular el monto en cheque
    var cheque = total - efectivo;

    // Actualizar el valor del input de cheque
    document.getElementById("MontoCHEQUE").value = cheque.toFixed(2);
    document.getElementById("cantidad").value = cheque.toFixed(2);
  } else {
    alert("¡Error! El monto en efectivo no puede ser mayor al total.");
    // Reiniciar el valor del input de efectivo
    document.getElementById("MontoEFECTIVO").value = "0";
    document.getElementById("MontoCHEQUE").value = total;
  }
  cantidad_a_letras(cheque);
}

// MULTI DESEMBOLSO BTN
function saveMultiDsmbls(idusu, idagencia) {
  var filgas = [];
  let cuenta = $("#codcredito").val();
  let nocuenta = cuenta.substring(8, 20);

  // Validación de MontoCHEQUE
  let montoCheque = $("#MontoCHEQUE").val(); // Obtener el valor del input MontoCHEQUE
  if (!montoCheque) {
    Swal.fire({
      icon: "error",
      title: "¡Alerta!",
      text: "Por favor ingrese el monto del EFECTIVO.",
    });
    return; // Salir de la función
  }
  // Validate that the "cuentaid" has a selected value before proceeding
  let cuentaid = $("#cuentaid").val();
  let numcheque = $("#numcheque").val();
  if (!cuentaid) {
    Swal.fire({
      icon: "error",
      title: "¡Alerta!",
      text: "Seleccione una cuenta antes de continuar.",
    });
    return;
  }

  if (!numcheque) {
    Swal.fire({
      icon: "error",
      title: "¡Alerta!",
      text: "AGREGUE UN No DE CHEQUE.",
    });
    return;
  }

  filgas[0] = [0, 0, 0, 0];
  k = 0;
  while (k != -1) {
    if (!!document.getElementById("idg_" + k + "_" + nocuenta));
    else break;
    filgas[k] = getinputsval([
      "idg_" + k + "_" + nocuenta,
      "mon_" + k + "_" + nocuenta,
      "con_" + k + "_" + nocuenta,
    ]);
    filgas[k][1] = numOr0(filgas[k][1]);
    k++;
  }
  refinance = obtenerDatosSeleccionados();
  obtiene(
    [
      `id_cod_cliente`,
      `nomcli`,
      `codagencia`,
      `codproducto`,
      `codcredito`,
      `ccapital`,
      `gastos`,
      `desembolsar`,
      `cantidad`,
      `numcheque`,
      `paguese`,
      `numletras`,
      `glosa`,
      `numdoc`,
      `MontoEFECTIVO`,
      `MontoCHEQUE`,
    ],
    [`desembolso1`, `negociable`, `bancoid`, `cuentaid`, `cuentaaho`],
    [],
    `create_desembolso`,
    `0`,
    [idusu, idagencia, filgas, idPro_gas, afec, ahorro, refinance]
  );
}

function ac_even(namEle, op_eve, op) {
  switch (op_eve) {
    case "vista":
      op == 0 ? $("#" + namEle).hide() : $("#" + namEle).show();
      break;
  }
}
/*****************************************************
 ******RESTRUCTURACION DE PLAN DE PAGO ***************
 ******************************************************/
function restruc(op) {
  switch (op) {
    case 1:
      codCre = $("#codCre").val();
      if (codCre === "") {
        msjAlert(
          "warning",
          "Primero tiene que seleccionar un credito",
          "¡Alerta!"
        );
        return false;
      } else {
        ocultaHabilita(["card1", "card2"], 1);
        msjAlert(
          "success",
          "A continuación, puede realizar los cambios necesarios. Ojo un crédito no se puede restructurar por segunda vez. ",
          "¡Restructuración!"
        );
        return true;
      }
      break;
    case 2:
      interes = $("#interes").val();
      if (interes === "" || interes < 1) {
        msjAlert("warning", "El interes tiene que ser mayor 0", "¡Alerta!");
        return false;
      } else {
        return true;
      }
      break;
    case 3:
      if (restruc(2) === false) return false;

      plazo = $("#plazo").val();
      if (plazo === "" || plazo < 1) {
        msjAlert("warning", "El plazo tiene que ser mayor 0", "¡Alerta!");
        return false;
      } else {
        obtiene(
          [
            "codCre",
            "codProducto",
            "interes",
            "salRestruturacion",
            "fecSigPago",
            "plazo",
            "idProduc",
            "fecUltPago",
          ],
          ["tipocred", "periodo"],
          [],
          "restructuracionPpg",
          "",
          ""
        );
        $("#btnGua").prop("disabled", true);
      }
      break;
    case 4:
      restruc(2);
      if ($("#plazo").val() === "" || plazo < 1) {
        msjAlert("warning", "El plazo tiene que ser mayor 0", "¡Alerta!");
        return false;
      }
      reportes(
        [
          [
            "codCre",
            "cliente",
            "codProducto",
            "salRestruturacion",
            "interes",
            "fecDes",
            "fecSigPago",
            "plazo",
          ],
          ["tipocred", "periodo"],
          [],
          [],
        ],
        "pdf",
        "planPago_restructuracion",
        0
      );
      $("#btnGua").prop("disabled", false);
      break;
  }
}

function msjAlert(tipo, msj, title) {
  Swal.fire({
    icon: tipo,
    title: title,
    text: msj,
  });
}

function ocultaHabilita(nameElemento, op) {
  for (con = 0; con < nameElemento.length; con++) {
    if (op == 0) {
      $("#" + nameElemento[con]).hide();
    }
    if (op == 1) {
      $("#" + nameElemento[con]).show();
    }
  }
}

function limpiarForm(nameElemento) {
  for (con = 0; con < nameElemento.length; con++) {
    document.getElementById("formulario").reset();
    ocultaHabilita(["card1", "card2"], 0);
  }
}

function controlSelect(nameSelect) {
  var data = $("#" + nameSelect).val();
  console.log(data);
  if (data === "Amer") {
    $("#periodo option[value='1D']").prop("disabled", true);
    $("#periodo option[value='7D']").prop("disabled", true);
    $("#periodo option[value='15D']").prop("disabled", true);
    $("#periodo option[value='14D']").prop("disabled", true);
    $("#periodo option[value='1M']").prop("disabled", false);
    $("#periodo").val("1M");
  } else {
    $("#periodo option[value='1D']").prop("disabled", false);
    $("#periodo option[value='7D']").prop("disabled", false);
    $("#periodo option[value='15D']").prop("disabled", false);
    $("#periodo option[value='14D']").prop("disabled", false);
    $("#periodo option[value='1M']").prop("disabled", false);
  }
}

//NUEVAS FUNCIONES CARLOS PARA NUEVOS DESARROLLOS
/**
 * Obtiene datos de filas seleccionadas de la tabla de refinanciamiento
 * @returns {Array} Retorna un array
 */
const obtenerDatosSeleccionados = () => {
  const datos = [
    ...document.querySelectorAll(".form-check-input.S:checked"),
  ].map((checkbox) => {
    const row = checkbox.closest("tr");
    const saldoRaw = parseFloat(
      row.cells[1].textContent.trim().replace(/,/g, "")
    );
    const interesRaw = parseFloat(
      row.querySelector('input[type="number"]').value
    );
    const saldoCapital = Number.isInteger(saldoRaw)
      ? saldoRaw
      : +saldoRaw.toFixed(2);
    const interes = Number.isInteger(interesRaw)
      ? interesRaw
      : +interesRaw.toFixed(2);
    const gastoSelect = row.querySelector("select");
    return {
      creditoActivo: row.cells[0].textContent.trim(),
      saldoCapital,
      interes,
      gasto: parseInt(gastoSelect.value, 10),
      nomenclatura: parseInt(
        gastoSelect.options[gastoSelect.selectedIndex].dataset.idextra,
        10
      ),
      id: parseInt(checkbox.id.replace("R_", ""), 10),
    };
  });

  // console.log('Datos seleccionados:', datos);
  return datos;
};

/**
 * NUEVAS FUNCIONES GENERICAS CON SOPORTE PARA FILES
 */
//opcion 2
/**
 * Versión mejorada de obtiene que soporta archivos opcionalmente
 * @param {Array} inputs - Array con los IDs de los inputs de texto
 * @param {Array} selects - Array con los IDs de los selects
 * @param {Array} radios - Array con los names de los radios
 * @param {String} condi - Condición para el servidor
 * @param {String} id - ID del registro
 * @param {Array} archivo - array de otros datos
 * @param {Function|String} callback - Función de callback o 'NULL'
 * @param {Boolean|String} messageConfirm - Mensaje de confirmación o false
 * @param {Array} files - [OPCIONAL] Array con los IDs de los inputs file
 * @returns {Boolean} - False si no pasa la validación
 */
function obtienePlus(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback = "NULL",
  messageConfirm = false,
  files = []
) {
  // Validación de campos
  const validacion = validarCamposGeneric(inputs, selects, radios);
  if (!validacion.esValido) {
    return false;
  }

  // Recolección de datos
  var inputs2 = getinputsval(inputs);
  var selects2 = getselectsval(selects);
  var radios2 = getradiosval(radios);

  // Verificar si tenemos archivos
  const hasFiles = files && files.length > 0;
  var files2 = hasFiles ? getfilesval(files) : [];

  // Verificar si hay confirmación
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
        if (hasFiles) {
          // Si hay archivos, usamos el método para archivos
          genericoPlus(
            inputs2,
            selects2,
            radios2,
            condi,
            id,
            archivo,
            callback,
            files2
          );
        } else {
          // Si no hay archivos, usamos el método regular
          generico(inputs2, selects2, radios2, condi, id, archivo, callback);
        }
      }
    });
  } else {
    if (hasFiles) {
      // Si hay archivos, usamos el método para archivos
      genericoPlus(
        inputs2,
        selects2,
        radios2,
        condi,
        id,
        archivo,
        callback,
        files2
      );
    } else {
      // Si no hay archivos, usamos el método regular
      generico(inputs2, selects2, radios2, condi, id, archivo, callback);
    }
  }
}

/**
 * Función extendida de generico que maneja archivos
 */
function genericoPlus(
  inputs,
  selects,
  radios,
  condi,
  id,
  archivo,
  callback,
  files
) {
  // Crear el FormData para enviar archivos
  var formData = new FormData();

  // Agregar parámetros básicos
  formData.append("condi", condi);
  formData.append("id", id);

  // Debug: Log de archivo

  archivo.forEach((item, idx) => {
    formData.append(`archivo[${idx}]`, item);
  });

  // Agregar los inputs, selects y radios
  for (let i = 0; i < inputs.length; i++) {
    formData.append("inputs[" + i + "]", inputs[i]);
  }

  for (let i = 0; i < selects.length; i++) {
    formData.append("selects[" + i + "]", selects[i]);
  }

  for (let i = 0; i < radios.length; i++) {
    formData.append("radios[" + i + "]", radios[i]);
  }

  // Agregar los archivos si hay
  if (files && files.length > 0) {
    for (let i = 0; i < files.length; i++) {
      if (files[i] !== null) {
        formData.append("files[" + i + "]", files[i], files[i].name);
      } else {
        formData.append("files[" + i + "]", "");
      }
    }
  } else {
    // console.log("No hay archivos para procesar");
  }

  // Debug: Mostrar todo el FormData
  for (let pair of formData.entries()) {
    // console.log(pair[0] + ': ', pair[1]);
  }

  // Enviar los datos al servidor
  $.ajax({
    url: "../../src/cruds/crud_credito_indi.php",
    method: "POST",
    data: formData,
    processData: false, // Importante para FormData
    contentType: false, // Importante para FormData
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      try {
        const data2 = JSON.parse(data);

        if (data2[1] == "1") {
          Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });

          // Si hay callback, ejecutarlo
          if (callback !== "NULL" && typeof callback === "function") {
            callback(data2);
          }
          printdiv2("#cuadro", id);
        } else {
          var relogin = "relogin" in data2 ? data2.relogin : 0;

          if (relogin == 1) {
            showRenewModalSession(
              data2[0],
              function () {
                genericoPlus(
                  inputs,
                  selects,
                  radios,
                  condi,
                  id,
                  archivo,
                  callback,
                  files
                );
              },
              data2.key
            );
          } else {
            Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
          }
        }
      } catch (e) {
        // Buscar posibles errores PHP
        if (
          data.includes("<br />") ||
          data.includes("Fatal error") ||
          data.includes("Warning") ||
          data.includes("Notice")
        ) {
        }

        Swal.fire({
          icon: "error",
          title: "¡ERROR DE RESPUESTA!",
          html: `
            <p>La respuesta del servidor no es JSON válido.</p>
            <details>
              <summary>Ver detalles técnicos</summary>
              <pre style="text-align: left; font-size: 10px; max-height: 200px; overflow-y: auto;">${data.substring(0, 1000)}</pre>
            </details>
          `,
        });
      }
    },
    error: function (xhr, status, error) {
      Swal.fire({
        icon: "error",
        title: "¡ERROR DE CONEXIÓN!",
        html: `
          <p>No se pudo completar la operación.</p>
          <small>Status: ${status} | Error: ${error}</small>
        `,
      });
    },
    complete: function () {
      loaderefect(0);
    },
  });
}
