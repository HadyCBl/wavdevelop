//#region printdivs
function printdiv(condi, idiv, dir, xtra) {
    loaderefect(1);
    dire = "views_reporte/" + dir + ".php";
    $.ajax({
        url: dire, method: "POST", data: { condi, xtra },
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

function normalizar_respuesta_json(data) {
    if (data === null || data === undefined) return null;
    if (typeof data === 'object') return data;
    if (typeof data !== 'string') return null;
    const txt = (data || '').trim();
    if (!txt) return null;
    try {
        return JSON.parse(txt);
    } catch (e) {
        return null;
    }
}

//para recargar en el mismo archivo, solo mandar id del cuadro y el extra
function printdiv2(idiv, xtra) {
    loaderefect(1);
    condi = $("#condi").val();
    dir = $("#file").val();
    dire = "views_reporte/" + dir + ".php";
    $.ajax({
        url: dire, method: "POST", data: { condi, xtra },
        success: function (data) {
            loaderefect(0);
            $(idiv).html(data);
        }
    })
}

function printdiv2_1(idiv, xtra) {
    // console.log(idiv,xtra );
    // return;
    //  loaderefect(1);
    condi = $("#condi").val();
    dir = $("#file").val();
    dire = "views_reporte/" + dir + ".php";
    console.log(idiv, xtra, condi, dir, dire);
    return;
    $.ajax({
        url: dire, method: "POST", data: { condi, xtra },
        success: function (data) {
            loaderefect(0);
            $(idiv).html(data);
        }
    })
}



//#region obtener datos de inputs, selects, radios
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
//#endregion
//#region ajax generico
function obtiene(inputs, selects, radios, condi, id, archivo) {
    var inputs2 = []; var selects2 = []; var radios2 = [];
    inputs2 = getinputsval(inputs)
    selects2 = getselectsval(selects)
    radios2 = getradiosval(radios)
    generico(inputs2, selects2, radios2, condi, id, archivo);
}
//--
function generico(inputs, selects, radios, condi, id, archivo) {
    $.ajax({
        url: "../src/cruds/crud_menu_reporte.php",
        method: "POST",
        data: { inputs, selects, radios, condi, id, archivo },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            //  console.log(data2);
            //  return;
            if (data2[1] == "1") {
                Swal.fire({ icon: 'success', title: 'Muy Bien!', text: data2[0] })
                printdiv2("#cuadro", id);
            }
            else {
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: data2[0] })
            }
        },
        complete: function () {
            loaderefect(0);
        }
    })
}
//#endregion
//FUNCION GENERAL PARA LOS REPORTES download: 1 si, 0 no(lo muestra en una nueva ventana)
function reportes(datos, tipo, file, download, label = "NULL", columdata = "NULL", tipodata = 1, labeltitle = "", top = 1) {
    loaderefect(1);
    var datosval = [];
    datosval[0] = getinputsval(datos[0]); datosval[1] = getselectsval(datos[1]); datosval[2] = getradiosval(datos[2]); datosval[3] = datos[3];
    var url = "views_reporte/reportes/" + file + ".php";
    $.ajax({
        url: url, async: true, type: "POST", dataType: "text", data: { datosval, tipo },
        success: function (data) {
            var opResult = normalizar_respuesta_json(data);
            if (!opResult || typeof opResult !== 'object') {
                console.error('Respuesta no-JSON en reportes():', data);
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Respuesta inválida del servidor. Revise la consola/logs.' });
                return;
            }
            // console.log(opResult)
            if (opResult.status == 1) {
                if (tipo == "show") {
                    updatetable(opResult.data, opResult.encabezados, opResult.keys);
                    builddata(opResult.data, label, columdata, tipodata, labeltitle, top);
                } else {
                    switch (download) {
                        case 0:
                            const ventana = window.open('', '_blank');
                            if (ventana) {
                                ventana.document.write("<html><head><title>" + (opResult.namefile || 'Reporte') + "</title></head><body style='margin:0;padding:0;'><object data='" + opResult.data + "' type='application/" + opResult.tipo + "' width='100%' height='100%'></object></body></html>");
                                ventana.document.close();
                            } else {
                                // Fallback: Si el popup está bloqueado, descargar directamente
                                console.warn('Popup bloqueado, descargando archivo directamente...');
                                var $a = $("<a href='" + opResult.data + "' download='" + (opResult.namefile || 'reporte') + "." + (opResult.tipo || tipo) + "'>");
                                $("body").append($a);
                                $a[0].click();
                                $a.remove();
                                Swal.fire({ icon: 'info', title: 'Popup bloqueado', text: 'El navegador bloqueó la ventana. El archivo se descargará automáticamente.' });
                                return;
                            }
                            break;
                        case 1:
                            var $a = $("<a href='" + opResult.data + "' download='" + opResult.namefile + "." + tipo + "'>");
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
        error: function (xhr, status, error) {
            console.error('Error AJAX en reportes():', { status, error, responseText: xhr && xhr.responseText });
            Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'No se pudo generar el reporte (error de conexión/servidor).' });
        }
    })
}
//#endregion
//#region FUNCIONES REUTILIZABLES}
function changedisabled(padre, status) {
    if (status == 0) $(padre).attr('disabled', 'disabled');
    else $(padre).removeAttr('disabled');
}
//#endregion

//#region 
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
    var title = top.length + ' sdafsd';

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
//#endregion
function showhide(ids, estados) {
    var estado = ['none', 'block'];
    for (let i = 0; i < ids.length; i++) {
        document.getElementById(ids[i]).style.display = estado[estados[i]];
    }
  }