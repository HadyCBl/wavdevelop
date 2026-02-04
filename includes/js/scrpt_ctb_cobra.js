//LIMPIAR MODAL DE BENEFICIARIO
function printdiv(condi, idiv, dir, xtra) {
  loaderefect(1);
  dire = "aho/" + dir + ".php";
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
          window.location.href = data2.url;
        }, 2000);
      }
      else {
        console.log(xhr);
      }
    }
  })
}
//---------obtener datos de inputs.. pasar datos como vectores con el id de los inputs, y retorna array
function getinputsval(datos) {
  const inputs2 = [''];
  var i = 0;
  while (i < datos.length) {
    inputs2[i] = document.getElementById(datos[i]).value;
    i++;
  }
  return inputs2;
}
//---------obtener datos de selects.. pasar datos como vectores con el id de los selects, y retorna array
function getselectsval(datos) {
  const selects2 = [''];
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
  const radios2 = [''];
  i = 0;
  while (i < datos.length) {
    radios2[i] = document.querySelector('input[name="' + datos[i] + '"]:checked').value;
    i++;
  }
  return radios2;
}
var tabla;
//---- -------------------------
function obtiene(inputs, selects, radios, condi, id, archivo, callback, confirmacion = false, mensaje = "¿Desea continuar con el proceso?") {
  var inputs2 = []; var selects2 = []; var radios2 = [];
  inputs2 = getinputsval(inputs);
  selects2 = getselectsval(selects);
  radios2 = getradiosval(radios);

  if (confirmacion) {
    Swal.fire({
      title: 'Confirmación',
      text: mensaje,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, continuar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        generico(inputs2, selects2, radios2, inputs, selects, radios, condi, id, archivo, callback);
      }
    });
  } else {
    generico(inputs2, selects2, radios2, inputs, selects, radios, condi, id, archivo, callback);
  }
}

//Nueva funcion ngenerido aplciada 
function generico(inputs, selects, radios, inputsn, selectsn, radiosn, condi, id, archivo, callback) {
  $.ajax({
    url: "../src/cruds/crud_ahorro.php",
    method: "POST",
    data: { inputs, selects, radios, inputsn, selectsn, radiosn, condi, id, archivo },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);

      // 1. Manejo de ALERTA IVE
      if (data2.status === 'alertaIVE') {
        // Verificar si la operación es un retiro
        if ($('#tiop').val() === 'R') {
          Swal.fire({
            title: 'Operación en espera',
            text: 'La operación de retiro está en espera de aprobación por un asesor.',
            icon: 'info',
            confirmButtonText: 'Aceptar'
          });
        } else {
          // Mostrar primero un SweetAlert con el mensaje
          Swal.fire({
            title: 'Alerta IVE',
            text: data2.message, // por ejemplo: 'ALERTA IVE... en los últimos 30 días...'
            icon: 'warning',
            confirmButtonText: 'Continuar'
          }).then((result) => {
            // Al confirmar, abrimos el modal
            if (result.isConfirmed) {
              $('#modalAlertaRTE').modal('show');
              // Llenar campos con la información devuelta
              $('#rte_ccdocta').val(data2.cuenta);
              $('#rte_mon').val(data2.monto);
              // Si quieres limpiar o setear otros campos, hazlo aquí
            }
          });
        }
        loaderefect(0);
        return; // Detenemos el flujo normal
      }

      // 2. Si la transacción fue exitosa (data2[1] === '1')
      if (data2[1] == "1") {
        Swal.fire({
          icon: 'success',
          title: 'Muy Bien!',
          text: data2[0]
        });
        if ((condi == "cdahommov" || condi == "create_depositos_ahommov")) {
          creaComprobante(data2);
          if (selects[0] == "1") {
            Swal.fire({
              title: 'Imprimir libreta?',
              showDenyButton: true,
              confirmButtonText: 'Imprimir',
              denyButtonText: `Cancelar`,
              allowOutsideClick: false
            }).then((result) => {
              if (result.isConfirmed) {
                creaLib(data2[2]);
              }
            });
          }
          printdiv2("#cuadro", id);
        } else if (condi == "liquidcrt" || condi == "printliquidcrt") {
          liquidcrt(data2);
          Swal.fire({
            title: 'Imprimir Comprobante?', showDenyButton: true, confirmButtonText: 'Imprimir', denyButtonText: `Cancelar`,
          }).then((result) => {
            if (result.isConfirmed) {
              comprobanteliquidcrt(data2);
            }
          })
          printdiv2("#cuadro", id);
        } else if ((condi == "create_aho_ben") || (condi == 'update_aho_ben')) {
          loaderefect(1);
          // tabla.ajax.reload();
          // traer_porcentaje_ben(id);
          cargar_datos_ben('lista_beneficiarios', id);
          loaderefect(0);
        } else if (condi == 'reimpresion_recibo') {
          // console.log(data2);
          creaComprobante(data2);
          cancelar_edit_recibo();
          printdiv2("#cuadro", id);
        } else if (condi == 'calculoprog') {
          loaderefect(0)
          loaddataprog(data2, inputs, selects);
        } else if (condi == 'procesCalculoIndi') {
          loaderefect(0)
          loadDataProcess(data2);
        } else {
          var reprint = ("reprint" in data2) ? data2.reprint : 1;
          if (reprint == 1) {
            printdiv2("#cuadro", id);
          }
        }
        if (typeof callback === 'function') {
          callback(data2);
        }
      }
      // 3. Otras respuestas: reportes o errores
      else if (data2[0] === "reportes_ahorros") {
        reportes_ahorros(data2);
      } else {
        var reprint = ("reprint" in data2) ? data2.reprint : 0;
        var timer = ("timer" in data2) ? data2.timer : 60000;
        Swal.fire({
          icon: 'error',
          title: '¡ERROR!',
          text: data2[0],
          timer: timer
        });
        if (reprint == 1) {
          setTimeout(function () {
            printdiv2("#cuadro", id);
          }, 1500);
        }
      }
    },
    complete: function () {
      loaderefect(0);
    }
  });
}
//funcion para obtener los campos para lo que sera lo del RTE 
/*
function toggleTitular(isTitular) {
  if (isTitular) {
    // Si es titular, deshabilitamos los campos para que no puedan editarse
    $('#rte_datos_personales input').prop('disabled', true);
    $('#rte_apellidos input').prop('disabled', true);

    // Obtenemos el código de cuenta del campo oculto o de entrada
    let cuenta = $('#rte_ccdocta').val();
    $.ajax({
      // Ajusta la ruta según tu estructura: desde "includes/js/scrpt_aho.js"
      // hacia "src/server_side/getDatosTitular.php" suele ser "../../src/server_side/getDatosTitular.php"
      url: '../../src/server_side/getDatosTitular.php',
      method: 'POST',
      data: { cuenta: cuenta },
      success: function(resp) {
        let info = JSON.parse(resp);
        if (info.status === 'ok') {
          // Asignar los campos con los datos obtenidos
          $('#rte_dpi').val(info.dpi);
          $('#rte_nombre1').val(info.nombre1);
          $('#rte_nombre2').val(info.nombre2); // Asegúrate que getDatosTitular.php retorne 'nombre2'
          $('#rte_nombre3').val(info.nombre3); // Lo mismo para 'nombre3'
          $('#rte_apellido1').val(info.apellido1);
          $('#rte_apellido2').val(info.apellido2);
          $('#rte_apellido3').val(info.apellido3);
        } else {
          // Si ocurre un error (por ejemplo, no se encontró la cuenta)
          Swal.fire('Error', info.message, 'error');
        }
      },
      error: function() {
        Swal.fire('Error', 'No se pudo obtener los datos del titular.', 'error');
      }
    });
  } else {
    // Si no es titular, habilitamos los campos para ingreso manual y los limpiamos
    $('#rte_datos_personales input').prop('disabled', false).val('');
    $('#rte_apellidos input').prop('disabled', false).val('');
  }
}
*/

// Esta función se encargará de procesar la respuesta cuando no haya alerta especial
function genericoSuccess(resp) {
  let data = JSON.parse(resp);
  // Si la transacción se completó exitosamente
  if (data[1] === '1') {
    Swal.fire('Éxito', 'Transacción completada.', 'success');
  } else {
    // En caso de error
    Swal.fire('Error', data[0], 'error');
  }
}


function printdiv2(idiv, xtra) {
  loaderefect(1);
  condi = condimodal();
  dir = filenow();
  dire = "aho/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      if (condi === 'ApertCuenAhor') {
        buscarcuentas(xtra);
      }
      loaderefect(0);
      $(idiv).html(data);
    }
  })
}

function printdiv3(condi, idiv, xtra) {
  loaderefect(1);
  dir = filenow();
  dire = "aho/" + dir + ".php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      loaderefect(0);
      $(idiv).html(data);
    }
  })
}

function abrir_modal(id_modal, estado, id_hidden, dato) {
  $(id_modal).modal(estado);
  //pasar el dato
  $(id_hidden).val(dato);
}


function seleccionar_cuenta_ctb(id_hidden, id) {
  var valor = $(id_hidden).val();
  if (valor == 1) {
    printdiv3('cuenta__1', '#div_cuenta1', id)
  }
  else if (valor == 2) {
    printdiv3('cuenta__2', '#div_cuenta2', id)
  }
  cerrar_modal('#modal_nomenclatura', 'hide', '#id_modal_hidden')
}

function cerrar_modal(id_modal, estado, id_hidden) {
  $(id_modal).modal(estado);
  //pasar el dato
  $(id_hidden).val("");
}


function condimodal() {
  var condi = document.getElementById("condi").value;
  return condi;
}
function filenow() {
  var file = document.getElementById("file").value;
  return file;
}
//funcion para eliminar cualquier registro
function eliminar(ideliminar, dir, xtra, condi) {
  dire = "../src/cruds/" + dir + ".php";
  Swal.fire({
    title: '¿ESTA SEGURO DE ELIMINAR?', showDenyButton: true, confirmButtonText: 'Eliminar', denyButtonText: `Cancelar`,
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
            Swal.fire('Correcto', data2[0], 'success');
            printdiv2("#cuadro", xtra);
          }
          else Swal.fire('Uff', data2[0], 'error')
        },
        complete: function () {
          loaderefect(0);
        }
      })
    }
  })
}
//cargar datos de selects o radiobuttons
function cargaselects(datos, ids) {
  if (document.getElementById("activado").checked == false) {
    document.getElementById("activado").checked = true;
    //console.log("holi1");
    var i = 0;
    ids.forEach(function (valores, indice, array) {
      document.getElementById(valores).value = datos[i];
      i++;
    });
  }
}

// function correltipcuenta(tipo, ins, ofi) {
//   console.log(tipo, ins, ofi)
//   dire = "../src/cruds/crud_ahorro.php";
//   condi = "correl";
//   var ant = document.getElementsByName('targets');
//   // console.log(ant)
//   i = 0;
//   while (i < (ant.length)) {
//     ant[i].className = 'tarjeta';
//     i++;
//   }
//   var intro = document.getElementById('' + tipo);
//   intro.className = 'tarjeta tarjeta-activa';
//   $.ajax({
//     url: dire,
//     method: "POST",
//     data: { condi, tipo, ins, ofi },
//     success: function (data) {
//       const data2 = JSON.parse(data);
//       document.getElementById("correla").value = data2[0];
//       document.getElementById("tasa").value = data2[1];
//       document.getElementById("tipCuenta").value = tipo;
//       document.getElementById("ccodofi").value = data2[2];
//     }
//   })
// }

function getCorrelativo(tipo, dire, tasa) {
  const ant = document.getElementsByName('targets');
  ant.forEach(element => {
    element.className = 'tarjeta';
  });

  const intro = document.getElementById(tipo);
  if (intro) {
    intro.className = 'tarjeta tarjeta-activa';
  }
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi: "correl", tipo },
    success: function (data) {
      // console.log(data);
      try {
        const data2 = JSON.parse(data);
        if (data2[1] == "1") {
          document.getElementById("correla").value = data2[0];
          document.getElementById("tasa").value = tasa;
        }
        else {
          Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
        }
      } catch (error) {
        console.error(error);
      }
    },
    error: function (xhr, status, error) {
      console.error("AJAX request failed:", status, error);
    }
  });
}

function selectahomtip(ccodtip, tasa) {
  const ant = document.getElementsByName('targets');
  const totalTargets = ant.length;
  for (let i = 0; i < totalTargets; i++) {
    ant[i].className = 'tarjeta';
  }
  const intro = document.getElementById(ccodtip);
  if (intro) {
    intro.className = 'tarjeta tarjeta-activa';
  }
  document.getElementById("tipCuenta").value = ccodtip;
  document.getElementById("tasaInteres").value = tasa;
}

function keypress(event) {
  // Verificar si la tecla presionada es "Enter" (código 13)
  if (event.keyCode === 13) {
    aplicarcod('ccodaho')
  }
}
function aplicarcod(codigo) {
  var cod = document.getElementById(codigo).value;
  if (cod == "") { cod = "01"; }
  printdiv2("#cuadro", cod);
}
function habdeshab(hab, des) {
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
function tipdoc(ids) {
  banco = document.getElementById('region_cheque');
  switch (ids) {
    case "E":
      banco.style = "display:none";
      break;
    case "D":
      banco.style = "display:block";
      break;
    case "C":
      banco.style = "display:block";
      break;
    case "T":
      habdeshab(['ccodahodestino'], ['nrochq', 'tipchq', 'feccom', 'numpartida'])
      break;
    default:
      banco.style = "display:none";
      break;
  }
}
function buscar_cuentas() {
  idbanco = document.getElementById('bancoid').value;
  $.ajax({
    url: "../src/cruds/crud_bancos.php",
    method: "POST",
    data: { 'condi': 'buscar_cuentas', 'id': idbanco },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      $("#cuentaid").empty();
      $("#cuentaid").append("<option value='0' selected disabled>Seleccione una cuenta</option>");
      if (data2[1] == "1") {
        for (var i = 0; i < data2[2].length; i++) {
          $("#cuentaid").append("<option value='" + data2[2][i]["id"] + "'>" + data2[2][i]["numcuenta"] + "</option>");
        }
      } else {
        Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
      }
    },
    complete: function () {
      loaderefect(0);
    }
  })
}
//FUNCION PARA TRAER EL NUMERO DE CHEQUE EN AUTOMATICO
function cheque_automatico(id_cuenta_banco, id_reg_cheque) {
  $.ajax({
    url: "../src/cruds/crud_bancos.php",
    method: "POST",
    data: { 'condi': 'cheque_automatico', 'id_cuenta_banco': id_cuenta_banco, 'id_reg_cheque': id_reg_cheque },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      const data2 = JSON.parse(data);
      $("#numcheque").val(data2[2]);
    },
    complete: function () {
      loaderefect(0);
    }
  })
}

function comprobanteliquidcrt(datos, file = 7, bandera = 1) {
  consultar_reporte(file, bandera).then(function (action) {
    //PARTE ENCARGADA DE RETORNAR EL NOMBRE DE LA FUNCION A EJECUTAR
    if (bandera == 1) {
      file = action.file;
    } else {
      file = file;
    }
    //EJECUTA FUNCION BASADO EN LA VARIABLE FILE
    window[file](datos);
  }).catch(function (error) {
    Swal.fire("Uff", error, "error");
  });
}
function liquidcrt(datos, file = 6, bandera = 1) {
  consultar_reporte(file, bandera).then(function (action) {
    //PARTE ENCARGADA DE RETORNAR EL NOMBRE DE LA FUNCION A EJECUTAR
    if (bandera == 1) {
      file = action.file;
    } else {
      file = file;
    }
    //EJECUTA FUNCION BASADO EN LA VARIABLE FILE
    window[file](datos);
  }).catch(function (error) {
    Swal.fire("Uff", error, "error");
  });
}

function creaComprobante(datos, file = 4, bandera = 1) {
  consultar_reporte(file, bandera).then(function (action) {
    //PARTE ENCARGADA DE RETORNAR EL NOMBRE DE LA FUNCION A EJECUTAR
    if (bandera == 1) {
      file = action.file;
    } else {
      file = file;
    }
    //EJECUTA FUNCION BASADO EN LA VARIABLE FILE
    window[file](datos);
  }).catch(function (error) {
    Swal.fire("Uff", error, "error");
  });
}

function creaLib(cod, file = 2, bandera = 1) {
  //CONSULTA PARA TRAER QUE REPORTE SE QUIERE
  var data2;
  consultar_reporte(file, bandera).then(function (action) {
    //PARTE ENCARGADA DE GENERAR EL REPORTE
    // console.log(action);
    if (bandera == 1) {
      file = action.file;
    } else {
      file = file;
    }
    return consultar_movimientos_libreta(file, cod);
  }).then(function (action2) {
    // DATOS DE MOVIMIENTOS DE AHORRO
    data2 = (action2);
    // IDENTIFICADOR DE ARCHIVO DE OPERACIONES DE LIBRETA
    file = 3;
    return consultar_reporte(file, bandera);
  }).then(function (action3) {
    // console.log(action3);
    // INTERCAMBIO DE VARIABLES
    if (bandera == 1) {
      file = action3.file;
    } else {
      file = file;
    }
    // console.log("creaLib")
    // console.log(data2)
    //SE PREPARAN LOS DATOSecho json_encode([[$numfront,$numdors,$inifront,$inidors,$saldo],$array,$confirma]);
    var inif = parseInt(data2[0][2]);
    var nfront = parseInt(data2[0][0]);
    var inid = parseInt(data2[0][3]);
    var ndors = parseInt(data2[0][1]);

    numi = parseInt(data2[1][0]['numlinea']);//ANTERIOR: parseInt(data2[1][1]['numlinea']);
    numf = parseInt(data2[1][data2[1].length - 1]['numlinea']);
    saldo = parseFloat(data2[0][4]);

    resta = 0;
    ini = 0;
    posfin = 0;
    if (numi <= nfront) {
      resta = 0;
      ini = inif;
      posfin = nfront;
    }
    if (numi > nfront) {
      resta = nfront;
      ini = inid;
      posfin = nfront + ndors;
    }
    //EJECUTA FUNCION BASADO EN LA VARIABLE FILE
    // window[file](data2, resta, ini, 1, posfin, saldo, numi, file);anterior
    window[file](data2, resta, ini, 0, posfin, saldo, numi, file);
    // libprint(data2, resta, ini, 1, posfin, saldo, numi);
  }).catch(function (error) {
    // console.log(error);
    Swal.fire("Uff", error, "error");
  });
}

function Lib_reprint(producto, movimientos, file) {
  //SE PREPARAN LOS DATOSecho json_encode([[$numfront,$numdors,$inifront,$inidors,$saldo],$array,$confirma]);
  console.log(producto)
  console.log(movimientos)
  console.log(file)
  var inif = parseInt(producto[0]['front_ini']);
  var nfront = parseInt(producto[0]['numfront']);
  var inid = parseInt(producto[0]['dors_ini']);
  var ndors = parseInt(producto[0]['numdors']);

  numi = parseInt(movimientos[0]['numlinea']);//ANTERIOR: parseInt(data2[1][1]['numlinea']);
  numf = parseInt(movimientos[movimientos.length - 1]['numlinea']);
  saldo = parseFloat(movimientos[0]['saldo']);

  resta = 0;
  ini = 0;
  posfin = 0;
  if (numi <= nfront) {
    resta = 0;
    ini = inif;
    posfin = nfront;
  }
  if (numi > nfront) {
    resta = nfront;
    ini = inid;
    posfin = nfront + ndors;
  }
  //EJECUTA FUNCION BASADO EN LA VARIABLE FILE
  // window[file](data2, resta, ini, 1, posfin, saldo, numi, file);anterior
  window[file]([[producto[0]['numfront'], producto[0]['numdors'], producto[0]['front_ini'], producto[0]['dors_ini'], movimientos[0]['saldo']], movimientos], resta, ini, 0, posfin, saldo, numi, file);
}

function consultar_movimientos_libreta(file, cod) {
  return new Promise(function (resolve, reject) {
    dire = "../views/aho/reportes/" + file + ".php";
    condi = "lib";
    $.ajax({
      url: dire,
      method: "POST",
      data: { condi, cod },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        const data2 = JSON.parse(data);
        if (data2[2] == "1") {
          resolve(data2);
        } else {
          reject("NO HAY DATOS PARA IMPRIMIR")
        }
      },
      complete: function () {
        loaderefect(0);
      },
    });
  });
}

//FUNCION QUE ABRE MODAL PARA LA EDICION DE RECIBO
function modal_edit_recibo(id_recibo, numdoc_ant, ccodaport, codusu) {
  $('#edicion_recibo').modal('show');
  document.getElementById("id_recibo").value = id_recibo;
  document.getElementById("id_codusu").value = codusu;
  document.getElementById("numdoc_modal_recibo_ant").value = numdoc_ant;
  document.getElementById("ccodaho_recibo").value = ccodaport;
}

//FUNCION PARA CANCELAR LA EDICION
function cancelar_edit_recibo() {
  document.getElementById("id_recibo").value = "";
  document.getElementById("id_codusu").value = "";
  document.getElementById("numdoc_modal_recibo_ant").value = "";
  document.getElementById("ccodaho_recibo").value = "";
  document.getElementById("numdoc_modal_recibo").value = "";
  $('#edicion_recibo').modal('hide');
}

//formatear numeros en moneda
const currency = function (number) {
  return new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ', minimumFractionDigits: 2 }).format(number);
};
//relleno
function pad(input) {
  var cadenaNumerica = '*************';
  var resultado = cadenaNumerica + input;
  return resultado = resultado.substring(resultado.length - cadenaNumerica.length);
}
function conviertefecha(fecharecibidatexto) {
  fec = fecharecibidatexto;

  anio = fec.substring(0, 4);
  mes = fec.substring(5, 7);
  dia = fec.substring(8, 10);

  ensamble = mes + "-" + dia + "-" + anio;
  fecha = new Date(ensamble).toLocaleDateString('es-GT');
  return fecha;
}

function editben(idahomben, bennom, bendpi, bendire, benparent, benfec, benporcent, bentel) {
  console.log(idahomben, bennom, bendpi, bendire, benparent, benfec, benporcent, bentel);
  $('#databen').modal('show');
  document.getElementById("createben").style = "display:none;";
  document.getElementById("updateben").style = "display:yes;";
  document.getElementById("idben").value = idahomben;
  document.getElementById("benname").value = bennom;
  document.getElementById("bendpi").value = bendpi;
  document.getElementById("bendire").value = bendire;
  document.getElementById("bentel").value = bentel;
  document.getElementById("bennac").value = benfec;
  document.getElementById("benporcent").value = benporcent;
  document.getElementById("benporcentant").value = benporcent;
  document.getElementById("benparent").value = benparent;
}
//#region ahoprog
//#endregion
function pagintere(pagintere) {
  switch (pagintere) {
    case '1':
      habdeshab([], ['bancom', 'cuentacor'])
      break;
    case '2':
      habdeshab([], ['bancom', 'cuentacor'])
      break;
    case '3':
      habdeshab(['bancom', 'cuentacor'], [])
      break;
  }
}
function pignora(pignora) {
  switch (pignora) {
    case 'S':
      habdeshab(['codpres'], [])
      break;
    case 'N':
      habdeshab([], ['codpres'])
      break;
  }
}

function calcfecven(cond) {
  //mostrar el elemento spinner
  document.getElementById("spinner").style.display = "block";
  var plazo = document.getElementById("plazo").value
  var fecven = document.getElementById("fecven").value
  var fecapr = document.getElementById("fecaper").value
  var mon = document.getElementById("monapr").value
  var int = document.getElementById("tasint").value
  let account = document.getElementById("codaho").value;
  $.ajax({
    url: "../src/cruds/crud_ahorro.php",
    method: "POST",
    data: { condi: 'calfec', fecapr, plazo, mon, int, cond, fecven, account },
    success: function (data) {
      //ocultar el elemento spinner
      document.getElementById("spinner").style.display = "none";
      // console.log(data);
      const data2 = JSON.parse(data);
      // console.log(data2);
      if (data2.status == '1') {
        document.getElementById("moncal").value = data2.montos[0]
        document.getElementById("intcal").value = data2.montos[1]
        document.getElementById("totcal").value = data2.montos[2]

        document.getElementById("fecven").value = data2.fecha[0]

        document.getElementById("plazo").value = data2.plazo[0]
      }
      else {
        var toastLive = document.getElementById('toastalert')
        document.getElementById("body_text").innerHTML = data2.message
        var toast = new bootstrap.Toast(toastLive)
        toast.show()
      }
    }
  })
}
function calcfecvenANT(cond) {
  var plazo = document.getElementById("plazo").value
  var fecven = document.getElementById("fecven").value
  var fecapr = document.getElementById("fecaper").value
  var mon = document.getElementById("monapr").value
  var int = document.getElementById("tasint").value
  var days = document.getElementById("dayscalc").value
  var inicioCalculo = document.getElementById("inicioCalculo").value
  condi = "calfec";
  $.ajax({
    url: "../src/cruds/crud_ahorro.php",
    method: "POST",
    data: { condi, fecapr, plazo, mon, int, cond, fecven, days, inicioCalculo },
    success: function (data) {
      const data2 = JSON.parse(data);
      if (data2[0] == '1' && cond == 1) {
        document.getElementById("moncal").value = data2[1]
        document.getElementById("intcal").value = data2[2]
        document.getElementById("totcal").value = data2[3]
      }
      if (data2[0] == '1' && cond == 2) {
        document.getElementById("fecven").value = data2[1]
      }
      if (data2[0] == '1' && cond == 3) {
        document.getElementById("plazo").value = data2[1]
      }
      if (data2[0] == '0') {
        var toastLive = document.getElementById('toastalert')
        document.getElementById("body_text").innerHTML = data2[1]
        var toast = new bootstrap.Toast(toastLive)
        toast.show()
      }

    }
  })
}
function penalizacion(interescalc) {
  var mon = interescalc;
  var monapr = document.getElementById("monapr").value;
  var porcpena = document.getElementById("porc_pena").value;
  (porcpena == "") ? porcpena = 0 : porcpena = porcpena;
  var penaliza = mon * (porcpena / 100);
  var moncal = mon - penaliza;

  var ipf = moncal * 0.10;
  var total = moncal - ipf;
  var totaltodo = (parseFloat(monapr) + parseFloat(moncal)) - (parseFloat(ipf) + parseFloat(penaliza));

  document.getElementById("penaliza").value = parseFloat(penaliza.toFixed(2));
  document.getElementById("moncal").value = parseFloat(moncal.toFixed(2));
  document.getElementById("intcal").value = parseFloat(ipf.toFixed(2));
  document.getElementById("totcal").value = parseFloat(total.toFixed(2));
  document.getElementById("totaltodo").value = parseFloat(totaltodo.toFixed(2));
}

function printcrt(idcrt, data2) {
  file = 5; bandera = 1;
  consultar_reporte(file, bandera).then(function (action) {
    file = action.file;
    if (action.type == "php") {
      reportes([[], [], [], [idcrt]], 'pdf', file, 0, 0);
    }
    else {
      window[file](data2.datosCertificado);
    }
  }).catch(function (error) {
    Swal.fire("Uff", error, "error");
  });
}
// function printcrt(idcrt, crud, condi) {
//   file = 5; bandera = 1;
//   consultar_reporte(file, bandera).then(function (action) {
//     file = action.file;
//     if (action.type == "php") {
//       reportes([[], [], [], [idcrt]], 'pdf', file, 0, 0);
//     }
//     else {
//       $.ajax({
//         url: "../src/cruds/" + crud + ".php",
//         method: "POST",
//         data: { condi, idcrt },
//         beforeSend: function () {
//           loaderefect(1);
//         },
//         success: function (data) {
//           const data2 = JSON.parse(data);
//           window[file](data2);
//         },
//         complete: function () {
//           loaderefect(0);
//         }
//       });
//     }
//   }).catch(function (error) {
//     Swal.fire("Uff", error, "error");
//   });
// }
/----------------------------------------*/
function edittestigo(id, nombre, dpi, direccion, telefono) {
  document.getElementById("testigo_nombre").value = nombre;
  document.getElementById("testigo_dpi").value = dpi;
  document.getElementById("testigo_direccion").value = direccion;
  document.getElementById("testigo_telefono").value = telefono;
  document.getElementById("idtestigo").value = id;

  document.getElementById("createtestigo").style.display = "none";
  document.getElementById("updatetestigo").style.display = "block";

  $('#databen').modal('show');
}
/----------------------------------------*/
function consultar_reporte(file, bandera) {
  return new Promise(function (resolve, reject) {
    if (bandera == 0) {
      resolve('Aprobado');
    }
    $.ajax({
      url: "../src/cruds/crud_ahorro.php",
      method: "POST",
      data: { 'condi': 'consultar_reporte', 'id_descripcion': file },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        const data2 = JSON.parse(data);
        if (data2[1] == "1") {
          resolve({ file: data2[2], type: data2[3] });
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

function reportes(datos, tipo, file, download = 1, bandera = 0, label = "NULL", columdata = "NULL", tipodata = 1, labeltitle = "", top = 1) {
  var datosval = [];
  datosval[0] = getinputsval(datos[0]);
  datosval[1] = getselectsval(datos[1]);
  datosval[2] = getradiosval(datos[2]);
  datosval[3] = datos[3];
  //CONSULTA PARA TRAER QUE REPORTE SE QUIERE
  consultar_reporte(file, bandera).then(function (action) {
    // console.log(action);
    //PARTE ENCARGADA DE GENERAR EL REPORTE
    if (bandera == 1) {
      file = action.file;
    } else {
      file = file;
    }
    var url = "aho/reportes/" + file + ".php";
    $.ajax({
      url: url,
      async: true,
      type: "POST",
      dataType: "html",//html
      contentType: "application/x-www-form-urlencoded",
      data: { datosval, tipo },
      beforeSend: function () {
        loaderefect(1);
      },
      success: function (data) {
        // console.log(data);
        var opResult;
        try {
          opResult = JSON.parse(data);
        } catch (err) {
          console.error('Invalid JSON:', data, err);
          Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Respuesta inválida del servidor' });
          return;
        }
        if (opResult.status == 1) {
          if (tipo == "show") {
            updatetable(opResult.data, opResult.encabezados, opResult.keys);
            builddata(opResult.data, label, columdata, tipodata, labeltitle, top);
          }
          else {
            var extension = ("extension" in opResult) ? opResult.extension : tipo;
            download = ("download" in opResult) ? opResult.download : download;
            switch (download) {
              case 0:
                const ventana = window.open();
                ventana.document.write("<object data='" + opResult.data + "' type='application/" + opResult.tipo + "' width='100%' height='100%'></object>")
                break;
              case 1:
                var $a = $("<a href='" + opResult.data + "' download='" + opResult.namefile + "." + extension + "'>");
                $("body").append($a);
                $a[0].click();
                $a.remove();
                break;
            }
            Swal.fire({ icon: 'success', title: 'Muy Bien!', text: opResult.mensaje })
          }
        }
        else {
          Swal.fire({ icon: 'error', title: '¡ERROR!', text: opResult.mensaje })
        }
      },
      complete: function () {
        loaderefect(0);
      },
    });
    //-------------------------------------FIN SEGUNDA FUNCION
  }).catch(function (error) {
    Swal.fire("Uff", error, "error");
  });
}

//FUNCION GENERAL PARA LOS REPORTES
function reportes_ahorros(data1) {
  //  console.log(data1);
  //  return;
  loaderefect(1);
  $.ajax({
    url: 'aho/reportes/' + data1[1] + '.php',
    async: true,
    type: "POST",
    dataType: "html",
    contentType: "application/x-www-form-urlencoded",
    data: { data: data1 },
    success: function (data) {
      // console.log(data);
      loaderefect(0);
      var opResult = JSON.parse(data);
      var $a = $("<a>");
      $a.attr("href", opResult.data);
      $("body").append($a);
      $a.attr("download", data1[1] + "_" + data1[4] + "." + data1[3]);
      $a[0].click();
      $a.remove();
    }
  })
}

//FUNCION PARA ACTIVAR Y DESACTIVAR UN SELECT CUANDO SE PRESIONA UN RADIO BUTTON
function activar_select_cuentas(radio, estado, select) {
  if (radio.checked) {
    if (estado) {
      document.getElementById(select).disabled = estado;
      $("#" + select).val(0);
    }
    else {
      //cuando se seleccionan una cuenta se habilita el select
      document.getElementById(select).disabled = estado;
    }
  }
}

//FUNCION PARA ACTIVAR Y DESACTIVAR INPUTS CUANDO SE PRESION UN RADIO BUTTON
function activar_input_dates(radio, estado, dateInicial, dateFinal) {
  if (radio.checked) {
    if (estado) {
      document.getElementById(dateInicial).disabled = estado;
      document.getElementById(dateFinal).disabled = estado;
      var date1 = document.getElementById(dateInicial);
      date1.value = formato_fecha();
      var date2 = document.getElementById(dateFinal);
      date2.value = formato_fecha();

    }
    else {
      //cuando se seleccionan una cuenta se habilita el select
      document.getElementById(dateInicial).disabled = estado;
      document.getElementById(dateFinal).disabled = estado;
    }
  }
}

function formato_fecha() {
  let yourDate = new Date()
  yourDate.toISOString().split('T')[0]
  const offset = yourDate.getTimezoneOffset()
  yourDate = new Date(yourDate.getTime() - (offset * 60 * 1000))
  return yourDate.toISOString().split('T')[0]
}

//funcion para agregar beneficiario desde la ventana principal
function crear_editar_beneficiario(ccodaho, nombre) {
  if (ccodaho === "0" || nombre === "") {
    Swal.fire({
      icon: 'error',
      title: '¡ERROR!',
      text: 'Debe seleccionar una cuenta de ahorro'
    });
    return;
  }
  cargar_datos_ben('lista_beneficiarios', ccodaho);
  $('#databen').modal('show');
  document.getElementById("ccodaho_modal").value = ccodaho;
  document.getElementById("name_modal").value = nombre;

}

function cargar_datos_ben(condi, id) {
  traer_porcentaje_ben(id);
  tabla = $('#tabla_ben').dataTable({
    "aProcessing": true, //activamos el procedimiento del datatable
    "aServerSide": true, //paginacion y filrado realizados por el server
    "searching": false,
    "paging": false,
    "ordering": false,
    "info": false,
    "ajax": {
      url: '../src/cruds/crud_ahorro.php',
      type: "POST",
      data: {
        'condi': condi, 'l_codaho': id
      },
      dataType: "json",
    },
    "bDestroy": true,
    "iDisplayLength": 10, //paginacion
    "order": [
      [0, "desc"]
    ] //ordenar (columna, orden)
  }).DataTable();
}

function traer_porcentaje_ben(id) {
  loaderefect(1);
  dire = "../src/cruds/crud_ahorro.php";
  $.ajax({
    url: dire,
    type: "POST",
    data: { 'condi': 'obtener_total_ben', 'l_codaho2': id },
    dataType: "JSON",
    success: function (data) {
      loaderefect(0);
      document.querySelector('#total').innerText = 'Total: ' + data + '%';
      limpiar_modal_ben();
    }
  });
}

function limpiar_modal_ben() {
  document.getElementById("createben").style = "display:yes;";
  document.getElementById("updateben").style = "display:none;";
  document.getElementById("idben").value = "";
  document.getElementById("benname").value = "";
  document.getElementById("bendpi").value = "";
  document.getElementById("bendire").value = "";
  document.getElementById("bentel").value = "";
  var date1 = document.getElementById('bennac');
  date1.value = formato_fecha();
  document.getElementById("benporcent").value = "";
  document.getElementById("benporcentant").value = "";
  document.getElementById("benparent").value = "";
}

function cancelar_crear_editar_beneficiario(condi, id) {
  loaderefect(1);
  dire = "../src/cruds/crud_ahorro.php";
  $.ajax({
    url: dire,
    type: "POST",
    data: { 'condi': 'obtener_total_ben', 'l_codaho2': id },
    dataType: "JSON",
    success: function (data) {
      loaderefect(0);
      if ((data == null || data == "")) {
        printdiv2('#cuadro', id)
        $('#databen').modal('hide');
      }
      //cuando el porcentaje es 100, despues de existir un registro
      else if (data == 100) {
        printdiv2('#cuadro', id)
        $('#databen').modal('hide');
        //cuando el porcentaje no es 100
      } else {
        Swal.fire({
          icon: 'error',
          title: '¡ERROR!',
          text: 'Tiene que ajustar a los beneficiarios para que en total sumen 100%, de lo contrario no puede salir de la ventana'
        });
        limpiar_modal_ben();
      }
    }
  });
}

//#region FUNCION PARA CARGAR UNA TABLA TIPO DataTable
function convertir_tabla_a_datatable(id_tabla) {
  $('#' + id_tabla).on('search.dt')
    .DataTable({
      "lengthMenu": [
        [5, 10, 15, -1],
        ['5 filas', '10 filas', '15 filas', 'Mostrar todos']
      ],
      "language": {
        "lengthMenu": "Mostrar _MENU_ registros",
        "zeroRecords": "No se encontraron registros",
        "info": " ",
        "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
        "infoFiltered": "(filtrado de un total de: _MAX_ registros)",
        "sSearch": "Buscar: ",
        "oPaginate": {
          "sFirst": "Primero",
          "sLast": "Ultimo",
          "sNext": "Siguiente",
          "sPrevious": "Anterior"
        },
        "sProcessing": "Procesando...",
      },
    });
}
//#endregion
function updatetable(datos, encabezados, keys) {
  $("#divshow").show();
  //ENCABEZADOS
  $('#tbdatashow thead').empty();
  var tr = $('<tr></tr>');
  for (var i = 0; i < encabezados.length; i++) {
    tr.append('<th class="text-center">' + encabezados[i] + '</th>');
  }
  $('#tbdatashow thead').append(tr);
  //FIN ENCABEZADOS

  var table = $('#tbdatashow').DataTable();

  // Mapear los datos a un formato que DataTables pueda entender
  var datosTabla = datos.map(function (obj) {
    return keys.map(function (key) {
      return obj[key];
    });
  });

  // Limpiar la tabla y agregar los datos
  table.clear();
  table.rows.add(datosTabla).draw();

}
/**
   * La función `builddata` procesa datos para contar el número de registros únicos o
   * calcular la suma de una columna específica, luego organiza y actualiza un gráfico con los
   * resultados.
   * data: matriz de datos a graficar
   * label: indice o columna de la matriz por el cual se agruparan
   * columndata: columna a sumar o contar
   * tipodata: 1-contara la cantidad de registros, 2-suma los registros de la columa `columndata`
   * @return La función `builddata` no devuelve ningún valor explícitamente. Está realizando
   * operaciones en los datos de entrada y luego llamando a la función `actualizarGrafica` con los
   * datos procesados y el título de la etiqueta como parámetros. La función `actualizarGrafica` es
   * probablemente responsable de actualizar un gráfico o visualización basada en los datos procesados.
   */
function builddata(data, label, columndata, tipodata, labeltitle, top) {
  // console.log(data)
  // console.log(label)
  // console.log(columndata)
  // console.log(tipodata)
  // console.log(labeltitle)
  //TRAER UNICOS
  const uniqueArray = data.reduce((accumulator, currentValue) => {
    // Verificar si la palabra ya existe en el objeto temporal
    if (!accumulator.lookup[currentValue[label]]) {
      accumulator.lookup[currentValue[label]] = true; // Marcar la palabra como encontrada
      accumulator.result.push(currentValue); // Agregar el registro único al array de resultados
    }
    return accumulator;
  }, {
    lookup: {},
    result: []
  }).result;
  //CONTAR ITEMS
  let datos = [];
  var i = 0;
  uniqueArray.forEach(unico => {
    palabrafind = unico[label];
    tabulado = data.filter(function (sym) {
      return sym[label] == palabrafind;
    })

    //CANTIDAD DE REGISTROS POR LA PROPIEDAD INDICADA
    var cant = tabulado.length;
    //SUMATORIA DE LA COLUMNA
    var valores = tabulado.map(obj => parseFloat(obj[columndata]));
    var suma = valores.reduce((acc, val) => acc + val, 0);

    //SE FORMA EL ARRAY DONDE SE TABULARAN LOS DATOS
    datos[i] = {};
    datos[i]['no'] = i + 1;
    datos[i]['fecha'] = palabrafind;
    datos[i]['cantidad'] = (tipodata == 1) ? cant : suma;
    i++;
  })
  const datosOrdenados = datos.sort((a, b) => b.fecha - a.fecha);
  actualizarGrafica(datosOrdenados, labeltitle, top)
}
let myChart;

function actualizarGrafica(datos, labeltitle, topdown) {
  $("#divshowchart").show();
  const top = (topdown == 1) ? datos.slice(0, 30) : datos.slice(-30);

  var title = top.length + ' sda.fsd';

  let palabras = top.map(item => item.fecha);
  let cant = top.map(item => item.cantidad);

  const ctx = document.getElementById('myChart');

  // Destruir la instancia anterior si existe
  if (myChart) {
    myChart.destroy();
  }

  const data = {
    labels: palabras,
    datasets: [{
      label: labeltitle,
      data: cant,
      lineTension: 0,
      backgroundColor: '#1E90FF',
      borderColor: '#87CEEB',
      borderWidth: 4,
      pointBackgroundColor: '#007bff'
    }]
  };
  const config = {
    type: 'bar',
    data: data,
    options: {
      plugins: {
        datalabels: {
          anchor: 'end',
          align: 'top',
          formatter: Math.round,
          font: {
            size: 12
          },
          backgroundColor: '#033bff',
          borderColor: '#ffffff',
          borderRadius: 4,
          borderWidth: 1,
          offset: 2,
          padding: 0,
          display: function (context) {
            return context.dataset.data[context.dataIndex] !== 0;
          }
        }
      },
      scales: {
        xAxes: [{
          ticks: {
            beginAtZero: true
          }
        }]
      }
    },
    options: {
      datasets: {
        bar: {
          borderSkipped: 'left'
        }
      }
    }
  };
  myChart = new Chart(ctx, config);
}

//antigus funcion para atualizacionde campos de forma dinamica
/* function updateValues() {
  const monto = document.getElementById('monto').value;
  const montoView = document.getElementById('monto_view');
  const montoLetras = document.getElementById('monto_letras');
  const resultSection = document.getElementById('result-section');

  if (monto) {
    // Mostrar sección de resultados
    resultSection.classList.remove('hidden');

    // Formatear monto con comas
    montoView.value = parseFloat(monto).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Convertir monto a letras usando la función del archivo externo
    montoLetras.value = numeroALetras(parseFloat(monto));
  } else {
    // Ocultar sección de resultados
    resultSection.classList.add('hidden');
  }
}*/

//nueva funcion para actualizacion de campos de forma dinamica()
function updateValues() {
  const monto = document.getElementById('monto').value;
  const montoView = document.getElementById('monto_view');
  const montoLetras = document.getElementById('monto_letras');
  const resultSection = document.getElementById('result-section');
  const concepto = document.getElementById('concepto');
  const name = document.getElementById('name').value;
  const tipoMovimiento = document.getElementById('tiop').value === 'E' ? 'DEPÓSITO' : 'RETIRO'; // Determinar si es depósito o retiro

  if (monto) {
    // Mostrar sección de resultadoswww
    resultSection.classList.remove('hidden');
    // Formatear monto con comas
    montoView.value = parseFloat(monto).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // Convertir monto a letras usando la función del archivo externo
    montoLetras.value = numeroALetras(parseFloat(monto));
    // Generar el concepto automáticamente
    const conceptoTexto = `${tipoMovimiento} DE AHORRO DE ${name} POR UN MONTO DE Q${montoView.value} (${montoLetras.value})`;
    concepto.value = conceptoTexto;
  } else {
    // Ocultar sección de resultados
    resultSection.classList.add('hidden');
    concepto.value = ''; // Limpiar el concepto si no hay monto
  }
}

var numeroALetras = (function () {
  function Unidades(num) {
    switch (num) {
      case 1: return 'UNO';
      case 2: return 'DOS';
      case 3: return 'TRES';
      case 4: return 'CUATRO';
      case 5: return 'CINCO';
      case 6: return 'SEIS';
      case 7: return 'SIETE';
      case 8: return 'OCHO';
      case 9: return 'NUEVE';
    }
    return '';
  }

  function Decenas(num) {
    let decena = Math.floor(num / 10);
    let unidad = num - (decena * 10);

    switch (decena) {
      case 1:
        switch (unidad) {
          case 0: return 'DIEZ';
          case 1: return 'ONCE';
          case 2: return 'DOCE';
          case 3: return 'TRECE';
          case 4: return 'CATORCE';
          case 5: return 'QUINCE';
          default: return 'DIECI' + Unidades(unidad);
        }
      case 2:
        switch (unidad) {
          case 0: return 'VEINTE';
          default: return 'VEINTI' + Unidades(unidad);
        }
      case 3: return DecenasY('TREINTA', unidad);
      case 4: return DecenasY('CUARENTA', unidad);
      case 5: return DecenasY('CINCUENTA', unidad);
      case 6: return DecenasY('SESENTA', unidad);
      case 7: return DecenasY('SETENTA', unidad);
      case 8: return DecenasY('OCHENTA', unidad);
      case 9: return DecenasY('NOVENTA', unidad);
      case 0: return Unidades(unidad);
    }
  }

  function DecenasY(strSin, numUnidades) {
    if (numUnidades > 0)
      return strSin + ' Y ' + Unidades(numUnidades);
    return strSin;
  }

  function Centenas(num) {
    let centenas = Math.floor(num / 100);
    let decenas = num - (centenas * 100);

    switch (centenas) {
      case 1:
        if (decenas > 0)
          return 'CIENTO ' + Decenas(decenas);
        return 'CIEN';
      case 2: return 'DOSCIENTOS ' + Decenas(decenas);
      case 3: return 'TRESCIENTOS ' + Decenas(decenas);
      case 4: return 'CUATROCIENTOS ' + Decenas(decenas);
      case 5: return 'QUINIENTOS ' + Decenas(decenas);
      case 6: return 'SEISCIENTOS ' + Decenas(decenas);
      case 7: return 'SETECIENTOS ' + Decenas(decenas);
      case 8: return 'OCHOCIENTOS ' + Decenas(decenas);
      case 9: return 'NOVECIENTOS ' + Decenas(decenas);
    }

    return Decenas(decenas);
  }

  function Seccion(num, divisor, strSingular, strPlural) {
    let cientos = Math.floor(num / divisor);
    let resto = num - (cientos * divisor);

    let letras = '';

    if (cientos > 0)
      if (cientos > 1)
        letras = Centenas(cientos) + ' ' + strPlural;
      else
        letras = strSingular;

    if (resto > 0)
      letras += '';

    return letras;
  }

  function Miles(num) {
    let divisor = 1000;
    let cientos = Math.floor(num / divisor);
    let resto = num - (cientos * divisor);

    let strMiles = Seccion(num, divisor, 'UN MIL', 'MIL');
    let strCentenas = Centenas(resto);

    if (strMiles == '')
      return strCentenas;

    return strMiles + ' ' + strCentenas;
  }

  function Millones(num) {
    let divisor = 1000000;
    let cientos = Math.floor(num / divisor);
    let resto = num - (cientos * divisor);

    let strMillones = Seccion(num, divisor, 'UN MILLON DE', 'MILLONES DE');
    let strMiles = Miles(resto);

    if (strMillones == '')
      return strMiles;

    return strMillones + ' ' + strMiles;
  }

  return function NumeroALetras(num, currency) {
    currency = currency || {};
    let data = {
      numero: num,
      enteros: Math.floor(num),
      centavos: (((Math.round(num * 100)) - (Math.floor(num) * 100))),
      letrasCentavos: '',
      letrasMonedaPlural: currency.plural || 'QUETZALES',
      letrasMonedaSingular: currency.singular || 'QUETZAL',
      letrasMonedaCentavoPlural: currency.centPlural || 'CENTAVOS',
      letrasMonedaCentavoSingular: currency.centSingular || 'CENTAVOS'
    };

    if (data.centavos > 0) {
      data.letrasCentavos = 'CON ' + (function () {
        if (data.centavos == 1)
          return Millones(data.centavos) + ' ' + data.letrasMonedaCentavoSingular;
        else
          return Millones(data.centavos) + ' ' + data.letrasMonedaCentavoPlural;
      })();
    }

    if (data.enteros == 0)
      return 'CERO ' + data.letrasMonedaPlural + ' ' + data.letrasCentavos;
    if (data.enteros == 1)
      return Millones(data.enteros) + ' ' + data.letrasMonedaSingular + ' ' + data.letrasCentavos;
    else
      return Millones(data.enteros) + ' ' + data.letrasMonedaPlural + ' ' + data.letrasCentavos;
  };
})();


function buscarcuentas(xtra) {
  let condi = "cargarcuentas";
  let dire = "aho/funciones/funcion.php";

  $.ajax({
    url: dire,
    method: "POST",
    data: { condi, xtra },
    success: function (data) {
      // Asegúrate de que los datos estén parseados
      let response = JSON.parse(data);
      //console.log(response);
      if (response.status === 'success') {
        const selectElement = document.getElementById('cuentasSelect');

        // Vaciar el contenido actual del select
        selectElement.innerHTML = `
                                  <option value="0" selected>No Aplica</option>
                                  <option value="-1">Crear cuenta nueva</option>
                                `;
        // Verificar si hay cuentas
        if (Array.isArray(response.cuentas) && response.cuentas.length > 0) {
          response.cuentas.forEach(cuenta => {
            // Crear un nuevo elemento <option>
            const option = document.createElement('option');
            option.value = cuenta.ccodaho; // Código de cuenta
            option.textContent = `${cuenta.nombre} (${cuenta.ccodaho})`; // Nombre y código de cuenta
            selectElement.appendChild(option); // Añadir al <select>
          });
        } else {
          // Si no hay cuentas, agregar una opción de aviso

        }
      } else {
        //console.error("Error en la respuesta del servidor");
      }
    },
    error: function (xhr, status, error) {
      console.error("Error en la solicitud AJAX:", error);
    }
  });
}
function corinteres() {

  const selectElement = document.getElementById('cuentasSelect');
  const selectedValue = selectElement.value;
  //alert(selectedValue);

  if (selectedValue === '-1') {
    const input = document.getElementById("selecproducto");
    input.style.display = "block";
    buscarproductos();
    document.getElementById("correlainteres").value = '0';
  } else if (selectedValue === '0') {
    const input = document.getElementById("selecproducto");
    input.style.display = "none";
    document.getElementById("correlainteres").value = '0';
  } else {
    document.getElementById("correlainteres").value = selectedValue;
  }

}

let skippedRTE = false;

function guardarRTE() {
  // Obtener valores del formulario
  let esTitular = document.querySelector('input[name="rte_esTitular"]:checked').value; // "si" o "no"
  let recurrente = $('#rte_recurrente').val(); // "0" o "1"
  let dpi = $('#rte_dpi').val().trim();
  let cuenta = $('#rte_ccdocta').val().trim();
  let nombre1 = $('#rte_nombre1').val().trim();
  let nombre2 = $('#rte_nombre2').val().trim();
  let nombre3 = $('#rte_nombre3').val().trim();
  let apellido1 = $('#rte_apellido1').val().trim();
  let apellido2 = $('#rte_apellido2').val().trim();
  let apellido3 = $('#rte_apellido3').val().trim(); // Apellido de casada
  let oriFondos = $('#rte_ori_fondos').val().trim();
  let destFondos = $('#rte_desti_fondos').val().trim();
  let nacionalidad = $('#rte_nacionalidad').val().trim();
  let monto = $('#rte_mon').val().trim();
  let propietario = (esTitular === 'si') ? 1 : 0; // Determinar si es propietario

  // Validaciones en el frontend
  if (!cuenta) {
    Swal.fire('Atención', 'El código de cuenta no puede estar vacío.', 'warning');
    return;
  }

  if (!monto || isNaN(monto) || parseFloat(monto) <= 0) {
    Swal.fire('Atención', 'El monto debe ser un número válido y mayor que cero.', 'warning');
    return;
  }

  if (esTitular === 'no' && !dpi) {
    Swal.fire('Atención', 'El DPI no puede estar vacío si el depositante no es el titular.', 'warning');
    return;
  }

  // Enviar datos al servidor
  $.ajax({
    url: '../src/cruds/crud_ahorro.php', // Ruta correcta al archivo PHP
    type: 'POST',
    data: {
      action: 'addIveform',
      condi: 'addIveform', // Añadir el parámetro condi
      esTitular: esTitular,
      recurrente: recurrente,
      dpi: dpi,
      cuenta: cuenta,
      nombre1: nombre1,
      nombre2: nombre2,
      nombre3: nombre3,
      apellido1: apellido1,
      apellido2: apellido2,
      apellido3: apellido3,
      oriFondos: oriFondos,
      destFondos: destFondos,
      nacionalidad: nacionalidad,
      monto: monto,
      propietario: propietario // Añadir el campo propietario
    },
    success: function (resp) {
      try {
        let data = JSON.parse(resp);
        if (data.status === 'ok') {
          $('#modalAlertaRTE').modal('hide');
          Swal.fire('Éxito', 'Datos RTE guardados correctamente.', 'success');
          confirmSave('D'); // Continuar con el flujo de depósito
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      } catch (e) {
        Swal.fire('Error', 'Respuesta no válida del servidor.', 'error');
        console.error('Error al parsear la respuesta:', resp);
      }
    },
    error: function (xhr, status, error) {
      Swal.fire('Error', 'No se pudo guardar la información RTE.', 'error');
      console.error('Error en la solicitud AJAX:', error);
    }
  });
}



function ncorrelativo() {
  const selectElement = document.getElementById('createdselect');
  const codig = selectElement.value;
  //console.log(codig, age, instt);
  loaderefect(1);
  dire = "../src/cruds/crud_ahorro.php";
  $.ajax({
    url: dire,
    method: "POST",
    data: { condi: 'correl', tipo: codig },
    success: function (data) {
      //console.log(data); // Verifica qué está recibiendo el cliente
      try {
        const data2 = JSON.parse(data);
        document.getElementById("correlainteres").value = data2[0];
      } catch (e) {
        console.error("Error al parsear JSON:", e.message);
      }
    }
  });
  loaderefect(0);

}

function buscarproductos() {
  let condi = "cargarproductos";
  let dire = "aho/funciones/funcion.php";

  $.ajax({
    url: dire,
    method: "POST",
    data: { condi },
    success: function (data) {
      // Asegúrate de que los datos estén parseados
      let response = JSON.parse(data);
      //console.log(response);
      if (response.status === 'success') {
        const selectElement = document.getElementById('createdselect');
        selectElement.innerHTML = `
        <option value="0" selected>Seleccione una opcion</option>
      `;
        // Verificar si hay cuentas
        if (Array.isArray(response.cuentas) && response.cuentas.length > 0) {
          response.cuentas.forEach(cuenta => {
            // Crear un nuevo elemento <option>
            const option = document.createElement('option');
            option.value = cuenta.ccodtip; // Código de cuenta
            option.textContent = `${cuenta.nombre}`; // Nombre y código de cuenta
            selectElement.appendChild(option); // Añadir al <select>
          });
        } else {
          // Si no hay cuentas, agregar una opción de aviso

        }
      } else {
        //console.error("Error en la respuesta del servidor");
      }
    },
    error: function (xhr, status, error) {
      console.error("Error en la solicitud AJAX:", error);
    }
  });
}