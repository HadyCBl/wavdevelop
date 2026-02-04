// function getinputsval(datos) {
//   const inputs2 = [""];
//   var i = 0;
//   while (i < datos.length) {
//     inputs2[i] = document.getElementById(datos[i]).value;
//     i++;
//   }
//   return inputs2;
// }
// function getselectsval(datos) {
//   const selects2 = [""];
//   i = 0;
//   while (i < datos.length) {
//     var e = document.getElementById(datos[i]);
//     selects2[i] = e.options[e.selectedIndex].value;
//     i++;
//   }
//   return selects2;
// }
// function getradiosval(datos) {
//   const radios2 = [""];
//   i = 0;
//   while (i < datos.length) {
//     radios2[i] = document.querySelector(
//       'input[name="' + datos[i] + '"]:checked'
//     ).value;
//     i++;
//   }
//   return radios2;
// }
// function printdiv(condi, idiv, dir, xtra) {
//   loaderefect(1);
//   dire = "" + dir + ".php";
//   $.ajax({
//     url: dire,
//     method: "POST",
//     data: {
//       condi,
//       xtra,
//     },
//     success: function (data) {
//       // console.log(data);
//       $(idiv).html(data);
//       loaderefect(0);
//     },
//     error: function (xhr) {
//       // console.log(xhr);
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

// function obtiene(
//   inputs,
//   selects,
//   radios,
//   condi,
//   id,
//   archivo,
//   callback = "NULL",
//   messageConfirm = false
// ) {
//   const validacion = validarCamposGeneric(inputs, selects, radios);

//   if (!validacion.esValido) {
//     return false;
//   }
//   var inputs2 = [];
//   var selects2 = [];
//   var radios2 = [];
//   inputs2 = getinputsval(inputs);
//   selects2 = getselectsval(selects);
//   radios2 = getradiosval(radios);
//   if (messageConfirm !== false) {
//     Swal.fire({
//       title: "Confirmación",
//       text: messageConfirm,
//       icon: "warning",
//       showCancelButton: true,
//       confirmButtonText: "Sí, continuar",
//       cancelButtonText: "Cancelar",
//     }).then((result) => {
//       if (result.isConfirmed) {
//         generico(inputs2, selects2, radios2, condi, id, archivo, callback);
//       }
//     });
//   } else {
//     generico(inputs2, selects2, radios2, condi, id, archivo, callback);
//   }
// }
// //--
// function generico(inputs, selects, radios, condi, id, archivo) {
//   $.ajax({
//     url: "functions/functions.php",
//     method: "POST",
//     data: { inputs, selects, radios, condi, id, archivo },
//     beforeSend: function () {
//       loaderefect(1);
//     },
//     success: function (data) {
//       console.log(data);
//       const data2 = JSON.parse(data);
//       console.log(data2);
//       //  return;
//       if (data2[1] == "1") {
//         Swal.fire({ icon: "success", title: "Muy Bien!", text: data2[0] });
//         printdiv2("#cuadro", id);
//         if (typeof callback === "function") {
//           callback(data2);
//         }
//       } else {
//         Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
//       }
//     },
//     complete: function () {
//       loaderefect(0);
//     },
//   });
// }
// function reportes(
//   datos,
//   tipo,
//   file,
//   download,
//   label = "NULL",
//   columdata = "NULL",
//   tipodata = 1,
//   labeltitle = "",
//   top = 1
// ) {
//   loaderefect(1);
//   var datosval = [];
//   datosval[0] = getinputsval(datos[0]);
//   datosval[1] = getselectsval(datos[1]);
//   datosval[2] = getradiosval(datos[2]);
//   datosval[3] = datos[3];
//   var url = "reportes/" + file + ".php";
//   $.ajax({
//     url: url,
//     async: true,
//     type: "POST",
//     dataType: "text",
//     data: { datosval, tipo },
//     success: function (data) {
//       // console.log(data)
//       loaderefect(0);
//       var opResult = JSON.parse(data);
//       // console.log(opResult)
//       if (opResult.status == 1) {
//         if (tipo == "show") {
//           updatetable(opResult.data, opResult.encabezados, opResult.keys);
//           builddata(opResult.data, label, columdata, tipodata, labeltitle, top);
//         } else {
//           switch (download) {
//             case 0:
//               const ventana = window.open();
//               ventana.document.write(
//                 "<object data='" +
//                   opResult.data +
//                   "' type='application/" +
//                   opResult.tipo +
//                   "' width='100%' height='100%'></object>"
//               );
//               break;
//             case 1:
//               var $a = $(
//                 "<a href='" +
//                   opResult.data +
//                   "' download='" +
//                   opResult.namefile +
//                   "." +
//                   tipo +
//                   "'>"
//               );
//               $("body").append($a);
//               $a[0].click();
//               $a.remove();
//               break;
//           }
//           Swal.fire({
//             icon: "success",
//             title: "Muy Bien!",
//             text: opResult.mensaje,
//           });
//         }
//       } else {
//         Swal.fire({ icon: "error", title: "¡ERROR!", text: opResult.mensaje });
//       }
//     },
//     complete: function (data) {},
//     error: function (xhr, status, error) {
//       loaderefect(0);
//       Swal.fire({
//         icon: "error",
//         title: "¡ERROR!",
//         text: "Error en la solicitud AJAX: " + error,
//       });
//     },
//   });
// }

//-----------------------------------------------------------------------------------------------------------------------------------------------
//-------------------------------------------------------- FUNCIONES PARA GENERAR LA FACTURA ----------------------------------------------------
//-----------------------------------------------------------------------------------------------------------------------------------------------
let contador = 0; // Para llevar la cuenta del número correlativo de las filas

// Función para agregar una nueva fila vacía con ID correlativo
function agregarFilaVacia() {
  // Obtener la referencia al tbody de la tabla
  const tabla = document
    .getElementById("miTabla")
    .getElementsByTagName("tbody")[0];

  // Crear una nueva fila
  const nuevaFila = tabla.insertRow();

  // Incrementamos el contador para asignar IDs correlativos
  contador++;

  // Insertar celdas con inputs vacíos en cada celda
  const celda1 = nuevaFila.insertCell(0);
  const selectBienServicio = document.createElement("select");
  selectBienServicio.className = "form-select";
  selectBienServicio.innerHTML = `
        <option value="B">Bien</option>
        <option value="S">Servicio</option>
    `;
  celda1.appendChild(selectBienServicio);

  const celda2 = nuevaFila.insertCell(1);
  const inputCant = document.createElement("input");
  inputCant.type = "number";
  inputCant.className = "form-control";
  inputCant.min = "0";
  inputCant.id = `cant-${contador}`; // Asignamos el ID correlativo
  inputCant.value = 1;
  inputCant.onchange = (function (capturaContador) {
    return function () {
      calculos(capturaContador);
    };
  })(contador);
  celda2.appendChild(inputCant);

  const celda3 = nuevaFila.insertCell(2);
  const inputDesc = document.createElement("input");
  inputDesc.type = "text";
  inputDesc.className = "form-control";
  inputDesc.id = `desc-${contador}`; // Asignamos el ID correlativo
  celda3.appendChild(inputDesc);

  const celda4 = nuevaFila.insertCell(3);
  const inputPrecio = document.createElement("input");
  inputPrecio.type = "number";
  inputPrecio.className = "form-control";
  inputPrecio.min = "0";
  inputPrecio.id = `precio-${contador}`; // Asignamos el ID correlativo
  inputPrecio.onchange = (function (capturaContador) {
    return function () {
      calculos(capturaContador);
    };
  })(contador);
  celda4.appendChild(inputPrecio);

  const celda5 = nuevaFila.insertCell(4);
  const inputDesc1 = document.createElement("input");
  inputDesc1.type = "number";
  inputDesc1.className = "form-control";
  inputDesc1.min = "0";
  inputDesc1.value = 0;
  inputDesc1.id = `desc1-${contador}`; // Asignamos el ID correlativo
  inputDesc1.onchange = (function (capturaContador) {
    return function () {
      calculos(capturaContador);
    };
  })(contador);
  celda5.appendChild(inputDesc1);

  const celda6 = nuevaFila.insertCell(5);
  const inputDesc2 = document.createElement("input");
  inputDesc2.type = "number";
  inputDesc2.className = "form-control";
  inputDesc2.min = "0";
  inputDesc2.value = 0;
  inputDesc2.id = `desc2-${contador}`; // Asignamos el ID correlativo
  inputDesc2.onchange = (function (capturaContador) {
    return function () {
      calculos(capturaContador);
    };
  })(contador);
  celda6.appendChild(inputDesc2);

  const celda7 = nuevaFila.insertCell(6);
  const inputTotal = document.createElement("input");
  inputTotal.type = "number";
  inputTotal.className = "form-control";
  inputTotal.min = "0";
  inputTotal.id = `total-${contador}`; // Asignamos el ID correlativo
  celda7.appendChild(inputTotal);

  const celda8 = nuevaFila.insertCell(7);
  const inputImpuestos = document.createElement("input");
  inputImpuestos.type = "number";
  inputImpuestos.className = "form-control";
  inputImpuestos.min = "0";
  inputImpuestos.id = `impuestos-${contador}`; // Asignamos el ID correlativo
  celda8.appendChild(inputImpuestos);
  inputImpuestos.disabled = true;

  const celda9 = nuevaFila.insertCell(8);
  // Agregamos un botón de "Eliminar" a cada fila
  const btnEliminar = document.createElement("button");
  btnEliminar.type = "button";
  btnEliminar.className = "btn btn-danger";
  btnEliminar.innerText = "Eliminar";
  btnEliminar.onclick = function () {
    eliminarFila(btnEliminar);
  };
  celda9.appendChild(btnEliminar);
  //console.log(contador);
}

// Función para eliminar una fila
function eliminarFila(boton) {
  // Obtener la fila a eliminar
  const fila = boton.closest("tr");
  const index = Array.from(fila.parentNode.children).indexOf(fila) + 1; // Índice de la fila en la tabla
  calculos(index); // Pasar el índice a la función de cálculos

  // Eliminar la fila
  fila.remove();

  // Restablecer el contador y los IDs de las filas restantes
  contador = 0;
  // Recorremos todas las filas y les asignamos nuevos IDs correlativos
  const filas = document
    .getElementById("miTabla")
    .getElementsByTagName("tbody")[0]
    .getElementsByTagName("tr");
  for (let i = 0; i < filas.length; i++) {
    contador = i + 1;
    filas[i].cells[1].querySelector("input").id = `cant-${contador}`;
    filas[i].cells[2].querySelector("input").id = `desc-${contador}`;
    filas[i].cells[3].querySelector("input").id = `precio-${contador}`;
    filas[i].cells[4].querySelector("input").id = `desc1-${contador}`;
    filas[i].cells[5].querySelector("input").id = `desc2-${contador}`;
    filas[i].cells[6].querySelector("input").id = `total-${contador}`;
    filas[i].cells[7].querySelector("input").id = `impuestos-${contador}`;
    sumageneral();
  }
  if (contador == 0) {
    document.getElementById("sumtotal").value = "0.00";
  }
  //console.log(contador);
}

function calculos(index) {
  //console.log(contador);
  // Obtener los valores de cantidad y precio
  const cantidad = parseFloat(document.getElementById("cant-" + index).value);
  const precio = parseFloat(document.getElementById("precio-" + index).value);
  const desc1 = parseFloat(document.getElementById("desc1-" + index).value);
  const desc2 = parseFloat(document.getElementById("desc2-" + index).value);
  if (cantidad >= 0 && precio >= 0) {
    // Calcular el total (cantidad * precio)
    var total = cantidad * precio;
    total = total - desc1 - desc2;
    // Calcular el impuesto (suponiendo que el total incluye IVA del 12%)
    const impuesto = total - total / 1.12; // El impuesto sería la diferencia del 12%

    // Asignar los valores calculados a los inputs correspondientes
    document.getElementById("total-" + index).value = total.toFixed(2); // Redondeamos a 2 decimales
    document.getElementById("impuestos-" + index).value = impuesto.toFixed(2); // Redondeamos a 2 decimales

    // Mostrar en consola los resultados para verificar
    //console.log("Total: " + total);
    //console.log("Impuesto: " + impuesto);
    sumageneral();
  }
}

function sumageneral() {
  var i = 1;
  var temp = 0;
  while (i <= contador) {
    const inputTotal = document.getElementById("total-" + i);
    if (inputTotal && inputTotal.value) {
      const intotal = parseFloat(inputTotal.value);
      temp += intotal;
    }
    i++;
  }
  document.getElementById("sumtotal").value = temp.toFixed(2);
}

function validarCampos() {
  // Obtener todas las filas de la tabla
  const filas = document
    .getElementById("miTabla")
    .getElementsByTagName("tbody")[0]
    .getElementsByTagName("tr");

  // Bandera para saber si hay algún campo vacío
  let camposCompletos = true;
  //DATOS GENERALES
  const nitEmisor2 = document.getElementById("nitcliente2").value;
  const nombre2 = document.getElementById("nitcliente2").value;
  const direccion2 = document.getElementById("nitcliente2").value;
  const sum = document.getElementById("sumtotal").value;
  if (
    sum >= 2500 &&
    (nombre2 === "CF" || nombre2 === "cf" || nombre2 === "Cf")
  ) {
    Swal.fire({
      icon: "warning",
      title: "ALERTA",
      text: "El monto maximo a facturar a un consumidor final no puede se igual o superar los Q 2,500.00",
    });
  } else {
    // Recorremos todas las filas y verificamos los campos
    if (filas.length === 0) {
      camposCompletos = false;
    } else {
      for (let i = 0; i < filas.length; i++) {
        // Verificar los campos de la fila actual
        const cantidad = filas[i].cells[1].querySelector("input").value;
        const descripcion = filas[i].cells[2].querySelector("input").value;
        const precio = filas[i].cells[3].querySelector("input").value;
        const desc1 = filas[i].cells[4].querySelector("input").value;
        const desc2 = filas[i].cells[5].querySelector("input").value;
        const total = filas[i].cells[6].querySelector("input").value;
        const impuestos = filas[i].cells[7].querySelector("input").value;

        // Comprobamos si algún campo está vacío
        if (
          !cantidad ||
          !descripcion ||
          !precio ||
          !desc1 ||
          !desc2 ||
          !total ||
          !impuestos
        ) {
          camposCompletos = false;
          break; // Si se encuentra un campo vacío, salimos del bucle
        }
      }
    }

    if (!camposCompletos || !nitEmisor2 || !nombre2 || !direccion2) {
      /*Swal.fire({
                title: "Alerta!",
                width: 600,
                padding: "3em",
                color: "#716add",
                background: "#fff url(/images/trees.png)",
                backdrop: `
                rgba(0,0,123,0.4)
                url("https://art.pixilart.com/4b680819d6447f3.gif")
                left top
                `
            });*/

      Swal.fire({
        icon: "warning",
        title: "ALERTA",
        text: "Por favor, complete todos los campos antes de avanzar",
      });
    } else {
      Swal.fire({
        icon: "success",
        title: "FACTURA",
        text: "Emitiendo Factura",
      });
      deshabilitarCampos();
      //generarXML();
      enviarDatos();
      loaderefect(1);
      cargardatos();
    }
  }
}

function generarXML() {
  // Obtener todos los datos de la tabla
  const filas = document
    .getElementById("miTabla")
    .getElementsByTagName("tbody")[0]
    .getElementsByTagName("tr");

  let itemsXML = "";
  let totalImpuesto = 0;
  let granTotal = 0;

  // Recorremos todas las filas de la tabla
  for (let i = 0; i < filas.length; i++) {
    const cantidad = parseFloat(filas[i].cells[1].querySelector("input").value);
    const descripcion = filas[i].cells[2].querySelector("input").value;
    const precio = parseFloat(filas[i].cells[3].querySelector("input").value);
    const descuento1 = parseFloat(
      filas[i].cells[4].querySelector("input").value
    );
    const descuento2 = parseFloat(
      filas[i].cells[5].querySelector("input").value
    );
    const total = parseFloat(filas[i].cells[6].querySelector("input").value);
    const impuestos = parseFloat(
      filas[i].cells[7].querySelector("input").value
    );

    // Crear un Item XML para cada fila
    itemsXML += `
        <dte:Item BienOServicio="B" NumeroLinea="${i + 1}">
            <dte:Cantidad>${cantidad}</dte:Cantidad>
            <dte:UnidadMedida>UNI</dte:UnidadMedida>
            <dte:Descripcion>${descripcion}</dte:Descripcion>
            <dte:PrecioUnitario>${precio.toFixed(2)}</dte:PrecioUnitario>
            <dte:Precio>${total.toFixed(2)}</dte:Precio>
            <dte:Descuento>${(descuento1 + descuento2).toFixed(2)}</dte:Descuento>
            <dte:Impuestos>
                <dte:Impuesto>
                    <dte:NombreCorto>IVA</dte:NombreCorto>
                    <dte:CodigoUnidadGravable>1</dte:CodigoUnidadGravable>
                    <dte:MontoGravable>${(total - impuestos).toFixed(2)}</dte:MontoGravable>
                    <dte:MontoImpuesto>${impuestos.toFixed(2)}</dte:MontoImpuesto>
                </dte:Impuesto>
            </dte:Impuestos>
            <dte:Total>${total.toFixed(2)}</dte:Total>
        </dte:Item>`;

    totalImpuesto += impuestos;
    granTotal += total;
  }

  // Crear la estructura XML completa
  const xmlData = `
<?xml version="1.0" encoding="UTF-8"?>
<dte:GTDocumento xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="0.1" xsi:schemaLocation="http://www.sat.gob.gt/dte/fel/0.2.0">
    <dte:SAT ClaseDocumento="dte">
        <dte:DTE ID="DatosCertificados">
            <dte:DatosEmision ID="DatosEmision">
                <dte:DatosGenerales CodigoMoneda="GTQ" FechaHoraEmision="${new Date().toISOString()}" Tipo="FCAM"></dte:DatosGenerales>
                <dte:Emisor AfiliacionIVA="GEN" CodigoEstablecimiento="1" NITEmisor="11201065K" NombreComercial="INFILE, SOCIEDAD ANONIMA" NombreEmisor="INFILE, SOCIEDAD ANONIMA">
                    <dte:DireccionEmisor>
                        <dte:Direccion>CUIDAD</dte:Direccion>
                        <dte:CodigoPostal>01010</dte:CodigoPostal>
                        <dte:Municipio>GUATEMALA</dte:Municipio>
                        <dte:Departamento>GUATEMALA</dte:Departamento>
                        <dte:Pais>GT</dte:Pais>
                    </dte:DireccionEmisor>
                </dte:Emisor>
                <dte:Receptor IDReceptor="CF" NombreReceptor="CONSUMIDOR FINAL">
                    <dte:DireccionReceptor>
                        <dte:Direccion>CUIDAD</dte:Direccion>
                        <dte:CodigoPostal>01010</dte:CodigoPostal>
                        <dte:Municipio>GUATEMALA</dte:Municipio>
                        <dte:Departamento>GUATEMALA</dte:Departamento>
                        <dte:Pais>GT</dte:Pais>
                    </dte:DireccionReceptor>
                </dte:Receptor>
                <dte:Frases>
                    <dte:Frase CodigoEscenario="1" TipoFrase="1"></dte:Frase>
                </dte:Frases>
                <dte:Items>
                    ${itemsXML}
                </dte:Items>
                <dte:Totales>
                    <dte:TotalImpuestos>
                        <dte:TotalImpuesto NombreCorto="IVA" TotalMontoImpuesto="${totalImpuesto.toFixed(2)}"></dte:TotalImpuesto>
                    </dte:TotalImpuestos>
                    <dte:GranTotal>${granTotal.toFixed(2)}</dte:GranTotal>
                </dte:Totales>
                <dte:Complementos>
                    <dte:Complemento IDComplemento="TEXT" NombreComplemento="TEXT" URIComplemento="TEXT">
                        <cfc:AbonosFacturaCambiaria xmlns:cfc="http://www.sat.gob.gt/dte/fel/CompCambiaria/0.1.0" Version="1" xsi:schemaLocation="http://www.sat.gob.gt/dte/fel/CompCambiaria/0.1.0 C:\Users\Desktop\SAT_FEL_FINAL_V1\Esquemas\GT_Complemento_Cambiaria-0.1.0.xsd">
                            <cfc:Abono>
                                <cfc:NumeroAbono>1</cfc:NumeroAbono>
                                <cfc:FechaVencimiento>2020-12-06</cfc:FechaVencimiento>
                                <cfc:MontoAbono>${granTotal.toFixed(2)}</cfc:MontoAbono>
                            </cfc:Abono>
                        </cfc:AbonosFacturaCambiaria>
                    </dte:Complemento>
                </dte:Complementos>
            </dte:DatosEmision>
        </dte:DTE>
        <dte:Adenda>
            <Diseno>M</Diseno>
        </dte:Adenda>
    </dte:SAT>
</dte:GTDocumento>`;
  // Enviar el XML generado al servidor usando AJAX (Fetch API)
  fetch("../../views/comprasventas/functions/functions.php", {
    method: "POST",
    data: { condi: "emitirfel" },
    headers: {
      "Content-Type": "application/xml",
    },
    body: xmlData,
  })
    .then((response) => response.text())
    .then((data) => {
      //console.log('Respuesta del servidor:', data);
      // Aquí puedes hacer algo con la respuesta del servidor si es necesario
    })
    .catch((error) => {
      console.error("Error al enviar el XML:", error);
    });
}

function generarYDescargarXML() {
  // Obtener todos los datos de la tabla
  const filas = document
    .getElementById("miTabla")
    .getElementsByTagName("tbody")[0]
    .getElementsByTagName("tr");

  let itemsXML = "";
  let totalImpuesto = 0;
  let granTotal = 0;

  // Recorremos todas las filas de la tabla
  for (let i = 0; i < filas.length; i++) {
    const bienServicio = filas[i].cells[0].querySelector("select").value; // Obtener valor de Bien o Servicio ("B" o "S")
    const cantidad = parseFloat(filas[i].cells[1].querySelector("input").value);
    const descripcion = filas[i].cells[2].querySelector("input").value;
    const precio = parseFloat(filas[i].cells[3].querySelector("input").value);
    const descuento1 = parseFloat(
      filas[i].cells[4].querySelector("input").value
    );
    const descuento2 = parseFloat(
      filas[i].cells[5].querySelector("input").value
    );
    const total = parseFloat(filas[i].cells[6].querySelector("input").value);
    const impuestos = parseFloat(
      filas[i].cells[7].querySelector("input").value
    );

    // Crear un Item XML para cada fila
    itemsXML += `
        <dte:Item BienOServicio="${bienServicio}" NumeroLinea="${i + 1}">
            <dte:Cantidad>${cantidad}</dte:Cantidad>
            <dte:UnidadMedida>UNI</dte:UnidadMedida>
            <dte:Descripcion>${descripcion}</dte:Descripcion>
            <dte:PrecioUnitario>${precio.toFixed(2)}</dte:PrecioUnitario>
            <dte:Precio>${total.toFixed(2)}</dte:Precio>
            <dte:Descuento>${(descuento1 + descuento2).toFixed(2)}</dte:Descuento>
            <dte:Impuestos>
                <dte:Impuesto>
                    <dte:NombreCorto>IVA</dte:NombreCorto>
                    <dte:CodigoUnidadGravable>1</dte:CodigoUnidadGravable>
                    <dte:MontoGravable>${(total - impuestos).toFixed(2)}</dte:MontoGravable>
                    <dte:MontoImpuesto>${impuestos.toFixed(2)}</dte:MontoImpuesto>
                </dte:Impuesto>
            </dte:Impuestos>
            <dte:Total>${total.toFixed(2)}</dte:Total>
        </dte:Item>`;

    totalImpuesto += impuestos;
    granTotal += total;
  }

  // Crear la estructura XML completa
  const xmlData = `
    <?xml version="1.0" encoding="UTF-8"?>
    <dte:GTDocumento xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="0.1" xsi:schemaLocation="http://www.sat.gob.gt/dte/fel/0.2.0">
        <dte:SAT ClaseDocumento="dte">
            <dte:DTE ID="DatosCertificados">
                <dte:DatosEmision ID="DatosEmision">
                    <dte:DatosGenerales CodigoMoneda="GTQ" FechaHoraEmision="${new Date().toISOString()}" Tipo="FCAM"></dte:DatosGenerales>
                    <dte:Emisor AfiliacionIVA="GEN" CodigoEstablecimiento="1" NITEmisor="11201065K" NombreComercial="INFILE, SOCIEDAD ANONIMA" NombreEmisor="INFILE, SOCIEDAD ANONIMA">
                        <dte:DireccionEmisor>
                            <dte:Direccion>CUIDAD</dte:Direccion>
                            <dte:CodigoPostal>01010</dte:CodigoPostal>
                            <dte:Municipio>GUATEMALA</dte:Municipio>
                            <dte:Departamento>GUATEMALA</dte:Departamento>
                            <dte:Pais>GT</dte:Pais>
                        </dte:DireccionEmisor>
                    </dte:Emisor>
                    <dte:Receptor IDReceptor="CF" NombreReceptor="CONSUMIDOR FINAL">
                        <dte:DireccionReceptor>
                            <dte:Direccion>CUIDAD</dte:Direccion>
                            <dte:CodigoPostal>01010</dte:CodigoPostal>
                            <dte:Municipio>GUATEMALA</dte:Municipio>
                            <dte:Departamento>GUATEMALA</dte:Departamento>
                            <dte:Pais>GT</dte:Pais>
                        </dte:DireccionReceptor>
                    </dte:Receptor>
                    <dte:Frases>
                        <dte:Frase CodigoEscenario="1" TipoFrase="1"></dte:Frase>
                    </dte:Frases>
                    <dte:Items>
                        ${itemsXML}
                    </dte:Items>
                    <dte:Totales>
                        <dte:TotalImpuestos>
                            <dte:TotalImpuesto NombreCorto="IVA" TotalMontoImpuesto="${totalImpuesto.toFixed(2)}"></dte:TotalImpuesto>
                        </dte:TotalImpuestos>
                        <dte:GranTotal>${granTotal.toFixed(2)}</dte:GranTotal>
                    </dte:Totales>
                    <dte:Complementos>
                        <dte:Complemento IDComplemento="TEXT" NombreComplemento="TEXT" URIComplemento="TEXT">
                            <cfc:AbonosFacturaCambiaria xmlns:cfc="http://www.sat.gob.gt/dte/fel/CompCambiaria/0.1.0" Version="1" xsi:schemaLocation="http://www.sat.gob.gt/dte/fel/CompCambiaria/0.1.0 C:\Users\Desktop\SAT_FEL_FINAL_V1\Esquemas\GT_Complemento_Cambiaria-0.1.0.xsd">
                                <cfc:Abono>
                                    <cfc:NumeroAbono>1</cfc:NumeroAbono>
                                    <cfc:FechaVencimiento>2020-12-06</cfc:FechaVencimiento>
                                    <cfc:MontoAbono>${granTotal.toFixed(2)}</cfc:MontoAbono>
                                </cfc:Abono>
                            </cfc:AbonosFacturaCambiaria>
                        </dte:Complemento>
                    </dte:Complementos>
                </dte:DatosEmision>
            </dte:DTE>
            <dte:Adenda>
                <Diseno>M</Diseno>
            </dte:Adenda>
        </dte:SAT>
    </dte:GTDocumento>`;

  // Crear un Blob del XML
  const blob = new Blob([xmlData], { type: "application/xml" });

  // Crear un enlace temporal para la descarga
  const link = document.createElement("a");
  const url = URL.createObjectURL(blob);
  link.href = url;
  link.download = "factura.xml"; // Nombre del archivo para descargar
  link.click();

  // Liberar el objeto URL creado
  URL.revokeObjectURL(url);
}

// Función para consumir la API y generar el XML
async function generarYDescargarXML2() {
  try {
    // Consumiendo la API para obtener los datos de la factura
    const response = await fetch("https://"); // Reemplaza con tu URL de la API
    const data = await response.json();

    // Obtención de datos del formulario HTML
    const nitEmisor = document.getElementById("nitcliente").value;
    const nombreEmisor = document.getElementById("nombrecliente").value;
    const direccionEmisor = document.getElementById("direccioncliente").value;

    const nitReceptor = document.getElementById("nitcliente").value; // Asegúrate de tener un campo separado para receptor
    const nombreReceptor = document.getElementById("nombrecliente").value;
    const direccionReceptor = document.getElementById("direccioncliente").value;

    // Generar el contenido XML
    let xmlContent = `<?xml version="1.0" encoding="UTF-8"?>
  <dte:GTDocumento xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="0.1" xsi:schemaLocation="http://www.sat.gob.gt/dte/fel/0.2.0">
    <dte:SAT ClaseDocumento="dte">
      <dte:DTE ID="DatosCertificados">
        <dte:DatosEmision ID="DatosEmision">
          <dte:DatosGenerales CodigoMoneda="GTQ" FechaHoraEmision="${new Date().toISOString()}" Tipo="FCAM"></dte:DatosGenerales>
          
          <dte:Emisor AfiliacionIVA="GEN" CodigoEstablecimiento="1" NITEmisor="${data.emisor.NIT}" NombreComercial="${data.emisor.nombre}" NombreEmisor="${data.emisor.nombre}">
            <dte:DireccionEmisor>
              <dte:Direccion>${data.emisor.direccion}</dte:Direccion>
              <dte:CodigoPostal>01010</dte:CodigoPostal>
              <dte:Municipio>${data.emisor.municipio}</dte:Municipio>
              <dte:Departamento>GUATEMALA</dte:Departamento>
              <dte:Pais>${data.emisor.pais}</dte:Pais>
            </dte:DireccionEmisor>
          </dte:Emisor>
  
          <dte:Receptor IDReceptor="CF" NombreReceptor="${data.receptor.nombre}">
            <dte:DireccionReceptor>
              <dte:Direccion>${data.receptor.direccion}</dte:Direccion>
              <dte:CodigoPostal>01010</dte:CodigoPostal>
              <dte:Municipio>${data.receptor.municipio}</dte:Municipio>
              <dte:Departamento>GUATEMALA</dte:Departamento>
              <dte:Pais>${data.receptor.pais}</dte:Pais>
            </dte:DireccionReceptor>
          </dte:Receptor>
  
          <dte:Frases>
            <dte:Frase CodigoEscenario="1" TipoFrase="1"></dte:Frase>
          </dte:Frases>
  
          <dte:Items>`;

    // Iterar sobre los items de la factura
    data.items.forEach((item) => {
      xmlContent += `
            <dte:Item BienOServicio="${item.bienOServicio}" NumeroLinea="${item.numeroLinea}">
              <dte:Cantidad>${item.cantidad}</dte:Cantidad>
              <dte:UnidadMedida>UNI</dte:UnidadMedida>
              <dte:Descripcion>${item.descripcion}</dte:Descripcion>
              <dte:PrecioUnitario>${item.precioUnitario}</dte:PrecioUnitario>
              <dte:Precio>${item.total}</dte:Precio>
              <dte:Descuento>${item.descuento}</dte:Descuento>
              <dte:Impuestos>
                ${item.impuestos
                  .map(
                    (impuesto) => `
                <dte:Impuesto>
                  <dte:NombreCorto>${impuesto.nombreCorto}</dte:NombreCorto>
                  <dte:CodigoUnidadGravable>1</dte:CodigoUnidadGravable>
                  <dte:MontoGravable>${impuesto.montoGravable}</dte:MontoGravable>
                  <dte:MontoImpuesto>${impuesto.montoImpuesto}</dte:MontoImpuesto>
                </dte:Impuesto>
                `
                  )
                  .join("")}
              </dte:Impuestos>
              <dte:Total>${item.total}</dte:Total>
            </dte:Item>`;
    });

    // Totalizar
    xmlContent += `
          </dte:Items>
          <dte:Totales>
            <dte:TotalImpuestos>
              <dte:TotalImpuesto NombreCorto="IVA" TotalMontoImpuesto="${data.totales.totalImpuestos}"></dte:TotalImpuesto>
            </dte:TotalImpuestos>
            <dte:GranTotal>${data.totales.granTotal}</dte:GranTotal>
          </dte:Totales>
        </dte:DatosEmision>
      </dte:DTE>
    </dte:SAT>
  </dte:GTDocumento>`;

    // Crear un Blob con el contenido XML
    const blob = new Blob([xmlContent], { type: "application/xml" });

    // Crear un enlace para descargar el archivo XML
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "factura.xml";
    link.click();
  } catch (error) {
    console.error("Error al generar el XML:", error);
  }
}

function selecliente(id, nit, nombre, direccion, correo) {
  //console.log(id);
  document.getElementById("nitcliente2").value = nit;
  document.getElementById("nombrecliente2").value = nombre;
  document.getElementById("emailcliente2").value = direccion;
  document.getElementById("direccioncliente2").value = correo;
  document.getElementById("clienteid2").value = id;
}

function buscarfact(
  id,
  nombre,
  fecha,
  codigo_aut,
  nautorizacion,
  serie,
  total
) {
  // console.log(id);

  document.getElementById("anulardte").value = id;
  document.getElementById("nombrcliente").value = nombre;
  document.getElementById("fechemision").value = fecha;
  document.getElementById("nodte").value = nautorizacion;
  document.getElementById("noautorizacion").value = codigo_aut;
  document.getElementById("noserie").value = serie;
  document.getElementById("total").value = total;
}

function deshabilitarCampos() {
  // Deshabilitar todos los campos fuera de la tabla
  const camposFormulario = document.querySelectorAll(
    "#btnbuscarnit, #tipodte, #nitcliente2, #nombrecliente2, #emailcliente2, #direccioncliente2, #clienteid2"
  );
  camposFormulario.forEach((campo) => (campo.disabled = true));

  // Deshabilitar todos los campos de la tabla (en todas las filas)
  const filasTabla = document.querySelectorAll("#miTabla tbody tr");
  filasTabla.forEach((fila) => {
    const camposFila = fila.querySelectorAll("input, select");
    camposFila.forEach((campo) => (campo.disabled = true));

    // Eliminar los botones de acción dentro de la fila (si los hay)
    const botonesAccion = fila.querySelectorAll("button");
    botonesAccion.forEach((boton) => boton.remove());
  });

  // Eliminar la columna de acciones del encabezado de la tabla
  const encabezadoTabla = document.querySelector("#miTabla thead tr");
  const columnaAcciones = encabezadoTabla.querySelector("th:nth-child(9)");
  if (columnaAcciones) {
    columnaAcciones.remove();
  }

  // Eliminar la columna de acciones de las filas de la tabla
  const celdasAcciones = document.querySelectorAll("#miTabla tbody tr");
  celdasAcciones.forEach((fila) => {
    const celdaAccion = fila.querySelector("td:nth-child(9)");
    if (celdaAccion) {
      celdaAccion.remove();
    }
  });

  // Deshabilitar el campo de total y la suma total
  document.getElementById("sumtotal").disabled = true;
}

function cargardatos() {
  document.getElementById("EmitFel").style.display = "none";
  document.getElementById("DesXML").style.display = "none";
  document.getElementById("AddFila").style.display = "none";
  document.getElementById("ImpFel").style.display = "inline-block";

  var condi = "buscardat";
  $.ajax({
    url: "../../views/comprasventas/functions/functions.php",
    method: "POST",
    data: { condi },
    success: function (response) {
      //console.log('Respuesta del servidor:', response);
      var data = JSON.parse(response);
      // Verificar si la respuesta tiene éxito
      if (data.status === "success") {
        document.getElementById("noautorizacion").value =
          data.processed_data.no_autorizacion;
        document.getElementById("fechemision").value =
          data.processed_data.fechahora_emision;
        document.getElementById("noserie").value = data.processed_data.serie;
        document.getElementById("nodte").value =
          data.processed_data.codigo_autorizacion;
        document.getElementById("ImpFel").value = data.processed_data.id;
      } else {
        console.log("Error:", data.message);
      }
      //  return;
    },
  });
}

function imprimirfel(id) {
  var condi = "consultarfel";
  $.ajax({
    url: "../../views/comprasventas/reportes/plantilla_factura.php",
    method: "POST",
    data: {
      id: id,
    },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      //console.log(data);
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

function validarcamposanulacion(id) {
  // Obtener todos los campos del formulario
  let nombrcliente = document.getElementById("nombrcliente").value;
  let noautorizacionn = document.getElementById("noautorizacion").value;
  let noserie = document.getElementById("noserie").value;
  let fechemision = document.getElementById("fechemision").value;
  let nodte = document.getElementById("nodte").value;
  let total = document.getElementById("total").value;
  let motivoanulacion = document.getElementById("motivoanulacion").value;

  if (
    !motivoanulacion ||
    !total ||
    !nodte ||
    !fechemision ||
    !noserie ||
    !noautorizacionn ||
    !nombrcliente
  ) {
    Swal.fire({
      icon: "warning",
      title: "ALERTA",
      text: "Por favor, complete todos los campos antes de avanzar",
    });
  } else {
    Swal.fire({
      title: "Confirmacion",
      text: "Esta seguro de anular la factura",
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Anular",
    }).then((result) => {
      if (result.isConfirmed) {
        loaderefect(1);
        document.getElementById("motivoanulacion").disabled = true;
        document.getElementById("btnbuscarfact").disabled = true;
        document.getElementById("anulardte").disabled = true;
        Swal.fire({
          title: "Anulando!",
          text: "Se ha iniciado el proceso",
          icon: "success",
        });

        var condi = "anularfel";
        $.ajax({
          url: "../../views/comprasventas/functions/functions.php",
          method: "POST",
          data: { condi, id, motivoanulacion },
          success: function (response) {
            //console.log('Respuesta del servidor:', response);
            var data = JSON.parse(response);
            // Verificar si la respuesta tiene éxito
            if (data.status === "success") {
              if (
                data.processed_data.estado === true &&
                data.processed_data.cantidad === 0
              ) {
                loaderefect(0);
                Swal.fire({
                  title: "Factura Anulada",
                  text: "El proceso se ha completado correctamente",
                  icon: "success",
                });
              } else {
                loaderefect(0);
                Swal.fire({
                  icon: "error",
                  title: "ERROR",
                  text: data.processed_data.problema,
                });
              }
            } else {
              loaderefect(0);
              Swal.fire({
                title: "Error",
                text: data.message,
                icon: "alert",
              });
            }
            //  return;
          },
        });
      } else {
        Swal.fire({
          title: "Cancelado",
          text: "Proceso cancelado",
          icon: "info",
        });
      }
    });
  }
}
