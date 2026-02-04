function printdiv(condi, idiv, dir, xtra) {
	dire = "./views/" + dir + ".php";
	$.ajax({
		url: dire, method: "POST", data: { condi, xtra },
		beforeSend: function () {
			loaderefect(1);
		},
		success: function (data) {
			$(idiv).html(data);
		},
		complete: function () {
			loaderefect(0);
		}
	})
}

//demas scripst recilacdos 
function reportes(datos, tipo, file, download = 1, bandera = 0, label = "NULL", columdata = "NULL", tipodata = 1, labeltitle = "", top = 1) {
	var datosval = [];
	datosval[0] = getinputsval(datos[0]);
	datosval[1] = getselectsval(datos[1]);
	datosval[2] = getradiosval(datos[2]);
	datosval[3] = datos[3];
	// CONSULTA PARA TRAER QUE REPORTE SE QUIERE
	consultar_reporte(file, bandera).then(function (action) {
		if (bandera == 1) {
			file = action.file;
		}
		var url = "./reportes/" + file + ".php";
		console.log("URL del reporte:", url); // DEBUG
		console.log("Datos enviados:", { datosval, tipo }); // DEBUG
		$.ajax({
			url: url,
			async: true,
			type: "POST",
			dataType: "json",   // Esperamos JSON directamente
			contentType: "application/x-www-form-urlencoded",
			data: { datosval, tipo },
			beforeSend: function () {
				loaderefect(1);
			},
			success: function (opResult) {
				console.log("Respuesta recibida:", opResult); // DEBUG
				if (opResult.status == 1) {
					if (tipo == "show") {
						updatetable(opResult.data, opResult.encabezados, opResult.keys);
						builddata(opResult.data, label, columdata, tipodata, labeltitle, top);
					} else {
						var extension = (("extension" in opResult) ? opResult.extension : tipo);
						download = (("download" in opResult) ? opResult.download : download);
						switch (download) {
							case 0:
								const ventana = window.open();
								ventana.document.write("<object data='" + opResult.data + "' type='application/" + opResult.tipo + "' width='100%' height='100%'></object>");
								break;
							case 1:
								var $a = $("<a href='" + opResult.data + "' download='" + opResult.namefile + "." + extension + "'>");
								$("body").append($a);
								$a[0].click();
								$a.remove();
								break;
						}
						Swal.fire({ icon: 'success', title: 'Muy Bien!', text: opResult.mensaje });
					}
				} else {
					Swal.fire({ icon: 'error', title: '¡ERROR!', text: opResult.mensaje });
				}
			},
			error: function(xhr, status, error) {
				console.log("Error AJAX:", status, error); // DEBUG
				console.log("Respuesta:", xhr.responseText); // DEBUG
				Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Error en la solicitud: ' + error });
			},
			complete: function () {
				loaderefect(0);
			},
		});
	}).catch(function (error) {
		Swal.fire("Uff", error, "error");
	});
}

function consultar_reporte(file, bandera) {
	return new Promise(function (resolve, reject) {
	  if (bandera == 0) {
		resolve('Aprobado');
		return; // IMPORTANTE: salir para no ejecutar el AJAX
	  }
	  $.ajax({
		url: "../../src/cruds/crud_ahorro.php",
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

// Función para habilitar/deshabilitar un campo basado en radios
function changedisabled(padre, status) {
	if (status == 0) $(padre).attr('disabled', 'disabled');
	else $(padre).removeAttr('disabled');
}