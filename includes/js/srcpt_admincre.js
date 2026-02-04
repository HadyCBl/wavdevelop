//limipar inputs 
function limpiarInputs() {
    printdiv2("#cuadro", 0);
    var inputs = document.querySelectorAll('input');
    inputs.forEach(function (input) {
        input.value = '';
    });
}
//#region loader
function loaderefect(sh) {
    const LOADING = document.querySelector('.loader-container');
    switch (sh) {
        case 1:
            LOADING.classList.remove('loading--hide');
            LOADING.classList.add('loading--show');
            break;
        case 0:
            LOADING.classList.add('loading--hide');
            LOADING.classList.remove('loading--show');
            break;
    }
}

function obtiene(inputs, selects, radios, condi, id, archivo) {
    var inputs2 = []; var selects2 = []; var radios2 = [];
    inputs2 = getinputsval(inputs)
    selects2 = getselectsval(selects)
    radios2 = getradiosval(radios)
    //console.log("Datos ya fueron procesado y enviados")
    generico(inputs2, selects2, radios2, condi, id, archivo);
}

function normalizar_respuesta_json(data) {
    if (data === null || data === undefined) return null;
    // Si jQuery ya parseó el JSON (por Content-Type: application/json), data será Array/Object
    if (typeof data === 'object') return data;
    if (typeof data !== 'string') return null;
    const txt = (data || '').trim();
    if (!txt) return null;
    return JSON.parse(txt);
}
//--
function generico(inputs, selects, radios, condi, id, archivo) {
    $.ajax({
        url: "../../src/cruds/crud_admincre.php",
        method: "POST",
        data: { inputs, selects, radios, condi, id, archivo },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            let data2;
            try {
                data2 = normalizar_respuesta_json(data);
                if (!data2) throw new Error('empty');
            } catch (e) {
                console.error('Respuesta no-JSON en generico():', data);
                loaderefect(0);
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Respuesta inválida del servidor. Revise la consola/logs.' });
                return;
            }
            if (data2[1] == "1") {
                //SECCION DE ACTUALIZACION DE PARAMETROS DE CREDITOS
                if (condi == "create_parametrizacion_creditos") {
                    printdiv3('section_parametrizacion_creditos', '#contenedor_section', '0');
                    table.ajax.reload();
                }
                else {
                    printdiv2("#cuadro", id);
                }
                Swal.fire({ icon: 'success', title: 'Muy Bien!', text: data2[0] })
            }
            else {
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: data2[0] })
                if (condi == "update_dias_laborales") {
                    printdiv2("#cuadro", 0);
                }
            }
        },
        error: function (xhr, status, error) {
            console.error('Error AJAX en generico():', { status, error, responseText: xhr && xhr.responseText });
            Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'No se pudo completar la operación (error de conexión/servidor).' });
        },
        complete: function () {
            loaderefect(0);
        }
    })
}

//Scrip para eliminar 
//funcion para eliminar cualquier registro

function eliminar(ideliminar, dir, xtra, condi, archivo) {
    dire = "../../src/cruds/" + dir + ".php";
    //alert(ideliminar + ' ' + condi + ' ' + archivo);
    //dire = "../../src/cruds/crud_admincre.php";
    Swal.fire({
        title: '¿ESTA SEGURO DE ELIMINAR?', showDenyButton: true, confirmButtonText: 'Eliminar', denyButtonText: `Cancelar`,
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
                    let data2;
                    try {
                        data2 = normalizar_respuesta_json(data);
                        if (!data2) throw new Error('empty');
                    } catch (e) {
                        console.error('Respuesta no-JSON en eliminar():', data);
                        loaderefect(0);
                        Swal.fire('¡ERROR!', 'Respuesta inválida del servidor. Revise la consola/logs.', 'error');
                        return;
                    }
                    if (data2[1] == "1") {
                        Swal.fire('Correcto', 'Eliminado', 'success');
                        printdiv2("#cuadro", xtra);
                    }

                    else Swal.fire('X(', data2[0], 'error')
                },
                error: function (xhr, status, error) {
                    console.error('Error AJAX en eliminar():', { status, error, responseText: xhr && xhr.responseText });
                    Swal.fire('¡ERROR!', 'No se pudo eliminar (error de conexión/servidor).', 'error');
                },
                complete: function () {
                    loaderefect(0);
                }
            })
        }
    })
}
//Genera PDF de data table  
function gPdf(idTabla) {
    $('#idTabla').DataTable({
        buttons: [
            {
                extend: 'pdf',
                text: 'Save current page',
                exportOptions: {
                    modifier: {
                        page: 'current'
                    }
                }
            }
        ]
    });
}
//data table 
function inicializarDataTable(idTabla) {
    $('#' + idTabla).DataTable({
        "order": [
            [0, 'desc'],
            [1, 'desc']
        ],
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
            "sProcessing": "Procesando..."
        }
    });
}



//#region printdivs
function printdiv(condi, idiv, dir, xtra) {
    dire = "views/creditos/" + dir + ".php";
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

function printdiv2(idiv, xtra) {
    condi = $("#condi").val();
    dir = $("#file").val();
    dire = "views/creditos/" + dir + ".php";
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

function printdiv3(condi, idiv, xtra) {
    dir = $("#file").val();
    dire = "views/creditos/" + dir + ".php";
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
        }
    })
}

function printdiv5(id_hidden, valores) {
    //ver si sacar el dato de un idhidden o directamente un toString
    var cadena = id_hidden.substr(0, 1);
    if (cadena == "#") {
        //todo el input
        var todo = ($(id_hidden).val()).split("/");
    }
    else {
        //todo la cadena
        var todo = id_hidden.split("/");
    }

    //se extraen los nombres de los inputs
    var nomInputs = (todo[0].toString()).split(",");
    //se extraen los rangos
    var rangos = (todo[1].toString()).split(",");
    //se extrae el separador
    var separador = todo[2].toString();

    //todo lo relacionado a la habilitacion o deshabilitacion
    var habilitar = [];
    var deshabilitar = [];
    if (todo[3].toString() != "#") {
        habilitar = (todo[3].toString()).split(",")
    }
    if (todo[4].toString() != "#") {
        deshabilitar = (todo[4].toString()).split(",")
    }
    habilitar_deshabilitar(habilitar, deshabilitar);
    //----fin de la habilitacion y deshabilitacion

    //todo lo relacionado con show y hide de elementos
    var mostrar = [];
    var ocultar = [];
    if (todo[5].toString() != "#") {
        mostrar = (todo[5].toString()).split(",")
    }
    if (todo[6].toString() != "#") {
        ocultar = (todo[6].toString()).split(",")
    }
    mostrar_nomostrar(mostrar, ocultar);
    //fin de los elementos hidden o visible

    // tratar de validar o unir campos para mandarlos a un solo input
    var contador = 0;
    for (var index = 0; index < nomInputs.length; index++) {
        if (rangos[index] !== 'A') {
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

function abrir_modal(id_modal, id_hidden, dato) {
    $(id_modal).modal('show');
    $(id_hidden).val(dato);
}

//seleccionar cuenta mejorado
function seleccionar_cuenta_ctb(id_hidden, valores) {
    printdiv5(id_hidden, valores);
    cerrar_modal('#modal_nomenclatura', 'hide', '#id_modal_hidden')
}
function seleccionar_cuenta_ctb2(id_hidden, valores) {
    document.getElementById("nomenclatura").classList.remove("is-invalid");
    printdiv5(id_hidden, valores);
}
function seleccionar_cuenta_ctb3(id_hidden, valores) {
    document.getElementById("tipoGasto").classList.remove("is-invalid");
    printdiv5(id_hidden, valores);
}

function cerrar_modal(id_modal, estado, id_hidden) {
    $(id_modal).modal(estado);
    $(id_hidden).val("");
}

//Valida si los input, select y radios contienen información 
function validaG(input, select, radios) {
    // console.log(input, select, radios);
    var inputs;
    var i = 0;
    var ban = true;

    while (i < input.length) {
        inputs = document.getElementById(input[i]).value;
        if (inputs === "") {
            document.getElementById(input[i]).classList.add("is-invalid");
            ban = false;
        }
        i++;
    }

    i = 0;
    while (i < select.length) {
        var dato = document.getElementById(select[i]);
        var selectedValue = dato.options[dato.selectedIndex].value;
        // console.log("DATO *** " + selectedValue);
        if (selectedValue === "Grado") {
            // console.log("El select no se ha utilizado");
            dato.classList.add("is-invalid");
            ban = false;
        }
        i++;
    }

    //var radios2;
    i = 0;
    while (i < radios.length) {
        var radioGroup = document.querySelectorAll('input[name="' + radios[i] + '"]');
        var checkedRadio = false;

        for (var j = 0; j < radioGroup.length; j++) {
            if (radioGroup[j].checked) {
                checkedRadio = true;
                break;
            }
        }

        if (!checkedRadio) {
            for (var j = 0; j < radioGroup.length; j++) {
                radioGroup[j].classList.add("is-invalid");
            }
            ban = false;
        }

        i++;
    }
    if (ban == false) {
        Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'No se puede registrar campos vacíos, favor de ingresar la información en los campos marcados.' })
    }
    return ban;
}

function validaMinMaxPor(input, min, max, text, op) {
    /*
        input = dato a analizar 
        min = rango minimo 
        max = rango maximo 
        text = nombre del campo donde exsite el problema de rango 
        op = si desea analizar un porcentaje o un numero 
            1 para porcentajes 
            2 para números
            NOTA. si el max es igual 0 significa que solo se tieen que analiza el minimo pero no exite un maximo
    */
    var datoInput = document.getElementById(input).value;
    switch (op) {
        //Opcion 1 para porcentajes 
        case 1: {
            if (datoInput < min || datoInput > max) {
                Swal.fire(text, "El rango del porcentaje que tiene que utilizar es de, " + min + " a " + max, 'error')
                return false;
            }
        } break;
        //Para numeros enteros
        case 2: {
            if (max == 0) {
                if (datoInput < min) {
                    Swal.fire(text, "Ingresar un numero mayor a 0", 'error')
                    return false;
                }
            } else {
                if (datoInput < min || datoInput > max) {
                    Swal.fire(text, "El rango que tiene que utilizar es de, " + min + " a " + max, 'error')
                    return false;
                }
            }
        } break;
    }
    return true;
}

function validaMon(input, radio) {
    var datoInput = document.getElementById(input).value;
    var datoRatio = $("input[name=" + radio + "]:checked").val();
    //console.log(datoInput);

    if (datoRatio == 2 && datoInput && datoInput > 100) {
        Swal.fire('X(', "El porcentaje no puede ser mayor a 100%", 'error')
        return false;
    }
    if (datoRatio == 2 && datoInput < 0) {
        Swal.fire('X(', "El porcentaje no puede ser menor a 1%", 'error')
        return false;
    }
    if (datoRatio == 1 && datoInput < 1) {
        // console.log("dato..... " + datoInput);
        Swal.fire('X(', "El monto fijo tienen que ser mayor a 0", 'error')
        return false;
    } return true;
}
//FUNCIONES PARA INICIAR A EDITAR...
function edita(datos) {
    dato = datos.split('||');
    $('#idRegistro').val(dato[0]);
    $('#gasto').val(dato[1]);
    $('#nomenclatura').val(dato[2] + " - " + dato[3]);
    $('#idCon').val(dato[4])

    $('#btnAct').show();
    $('#btnCan').show();
    $('#btnGua').hide();

    if (dato[5] > 0) {
        $("#flexSwitchCheckDefault").prop("checked", true); // Para marcar el checkbox
        $("#tipCuenta").show();
        $("input[name='opTipC']:eq(" + dato[5] + ")").prop("checked", true);
    } else {
        $("#flexSwitchCheckDefault").prop("checked", false); // Para marcar el checkbox
        $("#tipCuenta").hide();
        $("input[name='opTipC']:first").prop("checked", true);
    }

}
function editProCre(datos) {
    dato = datos.split('||')
    $('#idPro').val(dato[0])
    $('#nomPro').val(dato[3])

    //$('#selector').val(dato[1])
    //$('#selector').val(dato[4]) 
    $("#selector").val(dato[10]);
    $("#diascalculo").val(dato[12]);
    $("#configgracia").val(dato[13]);

    $('#desPro').val(dato[4])
    $('#montoMax').val(dato[5])
    $('#tasaInt').val(dato[6])
    $('#porMo').val(dato[7])
    $('#diaGra').val(dato[8])

    $("input[name='opMo']").filter("[value='" + dato[9] + "']").prop("checked", true);

    if (dato[9] == 1) {
        $('#divfactordia').hide()
        $('#radTipoMora').show()
        $("input[name='opCal']").filter("[value='" + dato[11] + "']").prop("checked", true);
        document.getElementById("porcentajeMora").innerHTML = "Mora por porcentaje: ";
    } else {
        $('#divfactordia').show()
        $('#radTipoMora').hide()
        $("#factordia").val(dato[11]);
        document.getElementById("porcentajeMora").innerHTML = "Mora por monto fijo: ";
    }
    //**** Botones
    $('#btnAct').show();
    $('#btnCan').show();
    $('#btnGua').hide();
}
function editGastoProducto(datos) {
    dato = datos.split('||');

    //ACTIVAR TIPO DE COBRO
    $("input[name='opCobro']").filter("[value='" + dato[3] + "']").prop("checked", true);
    tipcobro(dato[3]);

    //ACTIVAR TIPO DE MONTO
    $("input[name='opMonto']").filter("[value='" + dato[4] + "']").prop("checked", true);
    tipmonto(dato[4]);

    //ACTIVAR TIPO DE CALCULO
    $("input[name='opcalculo']").filter("[value='" + dato[8] + "']").prop("checked", true);

    $('#datoMonto').val(dato[5]);

    $('#idTipoG').val(dato[6]);
    $('#idGastoPro').val(dato[0]);
    $('#selecPro').val(dato[7]);
    $('#tipoGasto').val(dato[1]);

    $('#btnAct').show();
    $('#btnCan').show();
    $('#btnGua').hide();
}
// function editGastoPro(datos) {
//     dato = datos.split('||');
//     tipcobro(dato[3]);
//     $('#idGastoPro').val(dato[0]);
//     //INPUT DE TIPO GASTO 
//     //SELECT 
//     $('#selecPro').val(dato[7]);//6 se le envia la ID
//     $('#tipoGasto').val(dato[1]);//2 se envia el alor de tipo de gasto 
//     //RADIOS 
//     $("input[name='opCobro']")
//         .filter("[value='" + dato[3] + "']")
//         .prop("checked", true);
//     var spanMont = document.querySelector(".input-group-addon#txtMon");
//     document.getElementById("grpradio3").style.display = "block";
//     if (dato[4] == 1) {
//         spanMont.textContent = "Monto Fijo";
//         var input = document.getElementById("datoMonto");
//         input.min = 0;
//         input.removeAttribute("max");
//     } else if (dato[4] == 2) {
//         spanMont.textContent = "1 al 100 %";
//         document.getElementById("txtMon").value = "1 al 100 %"
//         var input = document.getElementById("datoMonto");
//         input.min = 0;
//         input.max = 100;
//     }
//     else {
//         spanMont.textContent = "Monto Variable";
//         document.getElementById("datoMonto").value = "0";
//         document.getElementById("grpradio3").style.display = "none";
//     }

//     $("input[name='opMonto']")
//         .filter("[value='" + dato[4] + "']")
//         .prop("checked", true);

//     $('#datoMonto').val(dato[5]);

//     //ID ESPECIALES 
//     $('#idTipoG').val(dato[6]);//7 Se el envia la id del producto}

//     //----------
//     $("input[name='opcalculo']")
//         .filter("[value='" + dato[8] + "']")
//         .prop("checked", true);
//     //--------

//     $('#btnAct').show();
//     $('#btnCan').show();
//     $('#btnGua').hide();
// }

function cancelar() {
    document.getElementById("gasto").value = "";
    document.getElementById("nomenclatura").value = "";
    $('#btnAct').hide();
    $('#btnGua').show();
    $('#btnCan').hide();
}

function cancelarPro() {
    document.getElementById("idPro").value = "";
    document.getElementById("nomPro").value = "";
    //document.getElementById("select").value = "Seleccione un fondo";
    document.getElementById("desPro").value = "";
    document.getElementById("montoMax").value = "";
    document.getElementById("tasaInt").value = "";
    document.getElementById("porMo").value = "";
    document.getElementById("diaGra").value = "";

    $("input[name='opMo']")
        .filter("[value='" + 1 + "']")
        .prop("checked", true);

    $("input[name='opCal']")
        .filter("[value='" + 1 + "']")
        .prop("checked", true);

    $('#btnAct').hide();
    $('#btnGua').show();
    $('#btnCan').hide();
}

$(document).ready(function () {
    $('#btnAct').hide();
    $('#btnCan').hide();
});

$(document).ready(function () {
    $('#btnGua').click(function () {
        if ($('#cod').val().length == 0 || $('#fondos').val().length == -0) {
            alert('Los camposestan vacios');
            return false;
        }
    });
});

function validar() {
    if ($('#cod').val().length == 0 && $('#fondos').val().length == -0) {
        alert('Los campos estan vacios');
        return false;
    }
}

//#region REGION DE PARAMETROS DE CREDITOS
//#endregion

function reportes(datos, tipo, file, download) {
    
    loaderefect(1);
    var datosval = [];
    datosval[0] = getinputsval(datos[0]); datosval[1] = getselectsval(datos[1]); datosval[2] = getradiosval(datos[2]); datosval[3] = datos[3];
    var url = "views/creditos/reportes/" + file + ".php";
    $.ajax({
        url: url, async: true, type: "POST", dataType: "html", data: { datosval, tipo },
        success: function (data) {
            let opResult;
            try {
                opResult = normalizar_respuesta_json(data);
                if (!opResult) throw new Error('empty');
            } catch (e) {
                console.error('Respuesta no-JSON en reportes():', data);
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Respuesta inválida del servidor. Revise la consola/logs.' });
                return;
            }
            //console.log(opResult)
            if (opResult.status == 1) {
                switch (download) {
                    case 0:
                        const ventana = window.open();
                        ventana.document.write("<object data='" + opResult.data + "' type='application/" + opResult.tipo + "' width='100%' height='100%'></object>")
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
            else {
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: opResult.mensaje })
            }
        },
        error: function (xhr, status, error) {
            console.error('Error AJAX en reportes():', { status, error, responseText: xhr && xhr.responseText });
            Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'No se pudo generar el reporte (error de conexión/servidor).' });
        },
        complete: function () {
            loaderefect(0);
        }
    })
}

/* REPORTE PARA EL BURO ************************************************** */
function validarFecha() {
    var yearSelect = document.getElementById("yearSelect").value;
    var mesSelect = document.getElementById("mesSelect").value;

    var anioActual = new Date().getFullYear();
    var mesActual = new Date().getMonth() + 1; // Sumar 1 porque los meses en JavaScript son de 0 a 11

    if (yearSelect >= anioActual && mesSelect >= mesActual) {
        Swal.fire("ALERTA", "Seleccionaste un mes futuro. Por favor, elige un mes anterior.", 'error').then(function () {
            //window.location.reload();
            return false;
        });
    } else {
        reportes([[], ['mesSelect', 'yearSelect'], [], [0]], 'txt', 'reporte_crediref', 1);
    }
    //([[`finicio`,`ffin`],[],[],[0]],`txt`,`reporte_crediref`,1)
    // alert(yearSelect+" / "+mesSelect);
}

function cargarAnios() {
    var yearSelect = document.getElementById("yearSelect");
    var anioActual = new Date().getFullYear();
    yearSelect.innerHTML = "";
    for (var anio = 2023; anio <= anioActual; anio++) {
        var option = document.createElement("option");
        option.value = anio;
        option.textContent = anio;
        yearSelect.appendChild(option);
    }
}

function slctActi() {
    document.getElementById('yearSelect').disabled = false;
    document.getElementById('btngen').disabled = false;
}
/* ************************************************************************** */

function selecOp() {
    var bool = $("#flexSwitchCheckDefault").prop("checked");
    if (bool) {
        $("#tipCuenta").show();
    } else {
        $("input[name='opTipC']:first").prop("checked", true);
        $("#tipCuenta").hide();
    }
}

function validaTipC() {
    var bool = $("#flexSwitchCheckDefault").prop("checked");
    da = ['opTipC'];
    if (bool) {
        data = getradiosval(da);
        if (data[0] == 0) {
            console.log("error");
            Swal.fire({ icon: 'error', title: '¡ERROR!', text: "Tiene que seleccionar un tipo de cuenta..." })
            return false;
        }
    }
    return true;
}

//Funciones para select2
function getSelectedSelect2(selectId) {
    var selectedValues = $('#' + selectId).val();
    console.log(selectedValues);

    return selectedValues;
}

function setSelectedSelect2(selectId, selectedValues) {
    if (typeof selectedValues === 'string') {
        selectedValues = selectedValues.split(',');
    }
    $('#' + selectId).val(selectedValues).trigger('change');
}

// Función para convertir tabla a DataTable con manejo seguro
function convertir_tabla_a_datatable(id_tabla, opciones = {}) {
    const tabla = $('#' + id_tabla);
    
    // Si la tabla no existe, salir
    if (!tabla.length) {
        console.warn('convertir_tabla_a_datatable: Tabla #' + id_tabla + ' no existe');
        return;
    }
    
    // Si DataTable no está cargado, salir
    if (typeof $.fn.DataTable === 'undefined') {
        console.warn('convertir_tabla_a_datatable: DataTables no está cargado');
        return;
    }
    
    // Si ya es DataTable, destruir primero
    if ($.fn.DataTable.isDataTable('#' + id_tabla)) {
        tabla.DataTable().destroy();
    }
    
    // Configuración por defecto
    const configDefault = {
        "lengthMenu": [
            [3, 5, 10, 15, -1],
            ['3 filas', '5 filas', '10 filas', '15 filas', 'Mostrar todos']
        ],
        "language": {
            "lengthMenu": "Mostrar _MENU_ registros",
            "zeroRecords": "No se encontraron registros",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
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
        "destroy": true,
        "retrieve": true
    };
    
    // Combinar opciones
    const config = $.extend({}, configDefault, opciones);
    
    // Inicializar DataTable
    return tabla.DataTable(config);
}