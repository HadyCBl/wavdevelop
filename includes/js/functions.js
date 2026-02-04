

//---------obtener datos de archivos.. pasar datos como vectores con el id de los inputs file, y retorna array
function getfilesval(datos) {
  const files2 = [];
  var i = 0;
  while (i < datos.length) {
    const fileInput = document.getElementById(datos[i]);
    if (fileInput && fileInput.files.length > 0) {
      files2[i] = fileInput.files[0]; // Obtenemos el primer archivo seleccionado
    } else {
      files2[i] = null; // Si no hay archivo, ponemos null
    }
    i++;
  }
  return files2;
}


//opcion 1
/**
 * Función para gestionar formularios con archivos
 * @param {Array} inputs - Array con los IDs de los inputs de texto
 * @param {Array} selects - Array con los IDs de los selects
 * @param {Array} radios - Array con los names de los radios
 * @param {Array} files - Array con los IDs de los inputs file
 * @param {String} condi - Condición para el servidor
 * @param {String} id - ID del registro
 * @param {String} archivo - Nombre del archivo donde se procesará la petición
 * @param {Function|String} callback - Función de callback o 'NULL'
 * @param {Boolean|String} messageConfirm - Mensaje de confirmación o false
 * @returns {Boolean} - False si no pasa la validación
 */
function obtieneFiles(
  inputs,
  selects,
  radios,
  files,
  condi,
  id,
  archivo,
  callback = "NULL",
  messageConfirm = false
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
  var files2 = getfilesval(files);

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
        genericoFiles(
          inputs2,
          selects2,
          radios2,
          files2,
          condi,
          id,
          archivo,
          callback
        );
      }
    });
  } else {
    genericoFiles(
      inputs2,
      selects2,
      radios2,
      files2,
      condi,
      id,
      archivo,
      callback
    );
  }
}

/**
 * Función para enviar datos de formulario incluyendo archivos al servidor
 */
function genericoFiles(
  inputs,
  selects,
  radios,
  files,
  condi,
  id,
  archivo,
  callback
) {
  // Crear el FormData para enviar archivos
  var formData = new FormData();

  // Agregar parámetros básicos
  formData.append("condi", condi);
  formData.append("id", id);
  formData.append("archivo", archivo);

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

  // Agregar los archivos
  for (let i = 0; i < files.length; i++) {
    if (files[i] !== null) {
      formData.append("files[" + i + "]", files[i], files[i].name);
    } else {
      formData.append("files[" + i + "]", "");
    }
  }

  // Enviar los datos al servidor
  $.ajax({
    url: "../../src/cruds/" + archivo,
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
        } else {
          Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
        }
      } catch (e) {
        console.error("Error al procesar la respuesta:", e);
        Swal.fire({
          icon: "error",
          title: "¡ERROR!",
          text: "Ocurrió un error al procesar la respuesta del servidor",
        });
      }
    },
    error: function (xhr, status, error) {
      console.error("Error en la petición:", error);
      Swal.fire({
        icon: "error",
        title: "¡ERROR!",
        text: "No se pudo completar la operación. Error de conexión.",
      });
    },
    complete: function () {
      loaderefect(0);
    },
  });
}

 //opcion 2
/**
 * Versión mejorada de obtiene que soporta archivos opcionalmente
 * @param {Array} inputs - Array con los IDs de los inputs de texto
 * @param {Array} selects - Array con los IDs de los selects
 * @param {Array} radios - Array con los names de los radios
 * @param {String} condi - Condición para el servidor
 * @param {String} id - ID del registro
 * @param {String} archivo - Nombre del archivo donde se procesará la petición
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
  formData.append("archivo", archivo);

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
  }

  // Enviar los datos al servidor
  $.ajax({
    url: "../../src/cruds/" + archivo,
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
        } else {
          Swal.fire({ icon: "error", title: "¡ERROR!", text: data2[0] });
        }
      } catch (e) {
        console.error("Error al procesar la respuesta:", e);
        Swal.fire({
          icon: "error",
          title: "¡ERROR!",
          text: "Ocurrió un error al procesar la respuesta del servidor",
        });
      }
    },
    error: function (xhr, status, error) {
      console.error("Error en la petición:", error);
      Swal.fire({
        icon: "error",
        title: "¡ERROR!",
        text: "No se pudo completar la operación. Error de conexión.",
      });
    },
    complete: function () {
      loaderefect(0);
    },
  });
}
