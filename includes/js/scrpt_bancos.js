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
//#endregion
//#region printdivs
function printdiv(condi, idiv, dir, xtra) {
    loaderefect(1);
    dire = "bancos/" + dir + ".php";
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
//para recargar en el mismo archivo, solo mandar id del cuadro y el extra
function printdiv2(idiv, xtra) {
    loaderefect(1);
    condi = $("#condi").val();
    dir = $("#file").val();
    dire = "bancos/" + dir + ".php";
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
//
function printdiv3(condi, idiv, xtra) {
    loaderefect(1);
    dir = $("#file").val();
    dire = "bancos/" + dir + ".php";

    $.ajax({
        url: dire, method: "POST", data: { condi, xtra },
        success: function (data) {
            loaderefect(0);
            $(idiv).html(data);
            //Actualizar tabla
            if (condi == 'section_cheques') {
                var indice = table_cheques_aux.page.info().page;
                table_cheques_aux.ajax.reload(function () {
                    var total = table_cheques_aux.page.info().pages;
                    if (indice == total) {
                        indice--;
                    }
                    table_cheques_aux.page(indice).draw('page');
                });
            }
            if (condi == 'section_partidas_conta') {
                var indice = table_cheques_aux.page.info().page;
                table_cheques_aux.ajax.reload(function () {
                    var total = table_cheques_aux.page.info().pages;
                    if (indice == total) {
                        indice--;
                    }
                    table_cheques_aux.page(indice).draw('page');
                });
            }
        }
    })
}
//inprimir datos en inputs
function printdiv4(data, idinputs) {
    i = 0;
    while (i < data.length) {
        $("#" + idinputs[i]).val(data[i]);
        i++;
    }
}
//#endregion
//#region Modal Nomenclatura
function abrir_modal(id_modal, id_hidden, dato) {
    $(id_modal).modal('show');
    $(id_hidden).val(dato);
}

function seleccionar_cuenta_ctb(id_hidden, valores) {
    printdiv5(id_hidden, valores);
    cerrar_modal('#modal_nomenclatura', 'hide', '#id_modal_hidden')
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
function obtiene(inputs, selects, radios, condi, id, archivo, callback = 'NULL', messageConfirm = false) {
    const validacion = validarCamposGeneric(inputs, selects, radios);

    if (!validacion.esValido) {
        return false;
    }

    var inputs2 = []; var selects2 = []; var radios2 = [];
    inputs2 = getinputsval(inputs)
    selects2 = getselectsval(selects)
    radios2 = getradiosval(radios)
    // generico(inputs2, selects2, radios2, condi, id, archivo);
    if (messageConfirm !== false) {
        Swal.fire({
            title: 'Confirmación',
            text: messageConfirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
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
function generico(inputs, selects, radios, condi, id, archivo, callback = 'NULL') {
    $.ajax({
        url: "../src/cruds/crud_bancos.php",
        method: "POST",
        data: { inputs, selects, radios, condi, id, archivo },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            if (data2[1] == "1") {
                Swal.fire({ icon: 'success', title: 'Muy Bien!', text: data2[0] });
                if (condi == 'create_cheques') {
                    printdiv('cheques', '#cuadro', 'ban001', '0')
                }
                else if (condi == 'update_cheques' || condi == 'anular_cheques') {
                    printdiv3('section_cheques', '#contenedor_section', id);
                }
                else if (condi == 'create_depositos_bancos') {
                    printdiv('deposito_bancos', '#cuadro', 'ban001', '0')
                }
                else if (condi == 'update_depositos_bancos') {
                    printdiv3('section_partidas_deposito', '#contenedor_section', id);
                }
                else {
                    printdiv2("#cuadro", id);
                }
                if (typeof callback === 'function') {
                    callback(data2);
                }
            }
            else {
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: data2[0] })
            }
        },
        complete: function (data) {
            loaderefect(0);
        }
    })
}
//#endregion
//#region funciones reutilzables
//desactiva o activa elementos: padre:mandar id, name o lo que sea pa identificar    status:1 activar, 0: desactivar
function changedisabled(padre, status) {
    if (status == 0) $(padre).attr('disabled', 'disabled');
    else $(padre).removeAttr('disabled');
}
//funcion para eliminar cualquier registro
function eliminar(ideliminar, dir, xtra, condi) {
    dire = "../src/cruds/" + dir + ".php";
    Swal.fire({
        title: '¿ESTA SEGURO DE ELIMINAR?', showDenyButton: true, confirmButtonText: 'Eliminar', denyButtonText: `Cancelar`,
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: dire, method: "POST", data: { condi, ideliminar },
                beforeSend: function () {
                    loaderefect(1);
                },
                success: function (data) {
                    const data2 = JSON.parse(data);
                    if (data2[1] == "1") {
                        Swal.fire('Correcto', data2[0], 'success');
                        if (condi == 'delete_cheques') {
                            printdiv3('section_cheques', '#contenedor_section', '0');
                        }
                        else {
                            printdiv2("#cuadro", xtra);
                        }
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

function consultar_reporte(file, bandera) {
    return new Promise(function (resolve, reject) {
        if (bandera == 0) {
            resolve('Aprobado');
        }
        $.ajax({
            url: "../src/cruds/crud_bancos.php",
            method: "POST",
            data: { 'condi': 'consultar_reporte', 'id_descripcion': file },
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

//FUNCION GENERAL PARA LOS REPORTES download: 1 si, 0 no(lo muestra en una nueva ventana)
function reportes(datos, tipo, file, download, bandera = 0) {
    var datosval = [];
    datosval[0] = getinputsval(datos[0]);
    datosval[1] = getselectsval(datos[1]);
    datosval[2] = getradiosval(datos[2]);
    datosval[3] = datos[3];
    //CONSULTA PARA TRAER QUE REPORTE SE QUIERE
    fileaux = file;
    consultar_reporte(file, bandera).then(function (action) {
        //PARTE ENCARGADA DE GENERAR EL REPORTE
        if (bandera == 1) {
            file = action;
        } else {
            file = file;
        }
        //PARTE ENCARGADA DE GENERAR EL REPORTE
        var url = "bancos/reportes/" + file + ".php";
        $.ajax({
            url: url, async: true, type: "POST", dataType: "html", data: { datosval, tipo },
            beforeSend: function () {
                loaderefect(1);
            },
            success: function (data) {
                var opResult = JSON.parse(data);
                if (opResult.status == 1) {
                    switch (download) {
                        case 0:
                            const ventana = window.open();
                            ventana.document.write("<object data='" + opResult.data + "' type='application/" + opResult.tipo + "' width='100%' height='100%'></object>")
                            break;
                        case 1:
                            var $a = $("<a href='" + opResult.data + "' download='" + opResult.namefile + "." + opResult.tipo + "'>");
                            $("body").append($a);
                            $a[0].click();
                            $a.remove();
                            break;
                    }
                    Swal.fire({ icon: 'success', title: 'Muy Bien!', text: opResult.mensaje });
                    if (tipo == "pdf" && fileaux == "13") { //13 == es el identificador del cheque
                        printdiv3('section_cheques', '#contenedor_section', datos[3][0]);
                    }
                }
                else {
                    Swal.fire({ icon: 'error', title: '¡ERROR!', text: opResult.mensaje })
                }
            },
            complete: function () {
                loaderefect(0);
            }
        })
    }).catch(function (error) {
        Swal.fire("Uff", error, "error");
    });
}
//#endregion
//#region FUNCION PARA CARGAR UNA TABLA TIPO DataTable
function convertir_tabla_a_datatable(id_tabla) {
    $('#' + id_tabla).on('search.dt')
        .DataTable({
            "lengthMenu": [
                [3, 5, 10, 15, -1],
                ['3 filas', '5 filas', '10 filas', '15 filas', 'Mostrar todos']
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
//#region PRINTDIV5 Y CARGAR DATATABLE
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
    // console.log(todo);

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

//#region FUNCION QUE EVALUA CUANDO CARGA TODOS LOS RECURSOS Y DESAPARECE EL LOADER
$(document).ready(function () {
    loaderefect(0);
});
//#endregion
//#region FUNCION PARA CONTROLAR LOS BOTONES
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

function HabDes_boton(valor) {
    if (valor == 1) {
        $('#btGuardar').hide();
        $('#btEditar').show();
    }
    if (valor == 0) {
        $('#btGuardar').show();
        $('#btEditar').hide();
    }
}
//#endregion



//#region EMISION DE CHEQUES  --------------------------------------------------------------------------------------------------------------------------------------------
var countrow = 0;
var countid = 0;
var datoseliminados = [];
function newrow() {
    if (validanewrow() == 1) {
        countrow++;
        countid++;
        var t = $('#Cuentas').DataTable();
        t.row.add([countrow, countid, genbtn('cuenta' + countid), input('debe' + countid, 'number', 'yes'), input('habe' + countid, 'number', 'yes')]).draw(false);
        var column = t.column(1);
        column.visible(false);
    }
    else {
        Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Hay registros sin completarse, verique que se hayan ingresado montos y se hayan seleccionado cuentas.' })
    }
}
//NEWROW PARA DEPOSITO A BANCOS
function newrow2(datas) {
    if (validanewrow() == 1) {
        countrow++;
        countid++;
        var t = $('#Cuentas').DataTable();
        t.row.add([countrow, countid, genbtn('cuenta' + countid), input('debe' + countid, 'number', 'yes'), input('habe' + countid, 'number', 'yes'), genfondos(datas, countid)]).draw(false);
        var column = t.column(1);
        column.visible(false);
    }
    else {
        Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Hay registros sin completarse, verique que se hayan ingresado montos y se hayan seleccionado cuentas.' })
    }
}
function genfondos(datafondos, count) {
    i = 0;
    fondoselect = '<select class="form-select" id="fondoid' + count + '">';
    while (i < datafondos.length) {
        fondoselect += '<option value="' + datafondos[i]['id'] + '">' + datafondos[i]['descripcion'] + '</option>';
        i++;
    }
    fondoselect += '</select>';
    return fondoselect;
}
//verifica que se hayan ingresado datos en cada fila
function validanewrow() {
    var rows = 1;
    var aux1 = 0;
    while (rows <= countid) {
        var mm = datoseliminados.includes(rows);
        if (mm == false) {
            aux1 = getinputsval(['debe' + (rows), 'habe' + (rows), 'cuenta' + (rows)]);
            if ((aux1[2] == "") || (aux1[0] == "" && aux1[1] == "")) {
                return 0;
            }
        }
        rows++;
    }
    return 1;
}
function genbtn(id) {
    return '<div class="input-group"><input style="display:none;" type="text" class="form-control" id="id' + id + '"><input type="text" readonly class="form-control" id="' + id + '"><button class="btn btn-outline-success" type="button" onclick="abrir_modal(`#modal_nomenclatura`, `#id_modal_hidden`, `id' + id + ',' + id + '/A,A//#/#/#/#`)" title="Buscar Cuenta contable"><i class="fa fa-magnifying-glass"></i></button></div>';
}
function input(id, type) {
    return '<div class="input-group"><span class="input-group-text">Q</span><input style="text-align: right;" type="' + type + '" step="0.01" class="form-control" id="' + id + '" onblur="validadh(this.id,this.value)"></div>';
}
function validadh(id, value) {
    var lado = id.substr(0, 4);
    var numero = id.substr(4, 3);
    var contra = (lado == 'debe') ? getinputsval(['habe' + numero]) : getinputsval(['debe' + numero]);
    var advertencia = "";
    if (value != "" && value != 0 && contra[0] != "" && contra[0] != 0) {
        advertencia = 'No se puede agregar una cantidad en este lado de la cuenta, verifique';
    }
    // if (value < 0) {
    //     advertencia = 'No se admiten negativos, verifique';
    // }
    if (advertencia != "") {
        Swal.fire({ icon: 'error', title: '¡ERROR!', text: advertencia })
        $("#" + id).val("");
        return;
    }
    if (value != "") {
        var valor = parseFloat(value)
        $("#" + id).val(valor.toFixed(2));
    }
    totaldh();
}
function totaldh() {
    var rows = 1;
    var totdebe = 0;
    var tothaber = 0;
    var pibo = 0;
    while (rows <= countid) {
        var mm = datoseliminados.includes(rows);
        if (mm == false) {
            pibo = getinputsval(['debe' + (rows), 'habe' + (rows)]);
            // console.log("pibo " + pibo)
            totdebe += parseFloat((pibo[0] == "") ? 0 : pibo[0]);
            tothaber += parseFloat((pibo[1] == "") ? 0 : pibo[1]);
        }
        rows++;
    }
    $("#totdebe").val(totdebe.toFixed(2));
    $("#tothaber").val(tothaber.toFixed(2));
}
function deletefila() {
    var t = $('#Cuentas').DataTable();
    var data = t.row('.selected').data();
    if (data != undefined) {
        t.row('.selected').remove().draw(false);
        datoseliminados.push(parseInt(data[1]))
        var num = 0;
        $('#Cuentas tbody tr').each(function () {
            $(this).find('td').eq(0).text(num + 1);
            num++;
        });
        countrow = num;
        counter = num;
        totaldh();
    }
}
function reinicio(num) {
    countrow = num;
    countid = num;
    datoseliminados = [];
}
function savecom(usuario, condio, idr) {
    loaderefect(1)
    if (validanewrow() == 0) {
        Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'Hay registros sin completarse, verique que se hayan ingresado montos y se hayan seleccionado cuentas.' })
        loaderefect(0);
        return;
    }
    var datainputsd = [''];
    var datainputsh = [''];
    var datacuentas = [''];
    var datafondos = [''];
    var datainputs = [];
    var dataselects = [];
    if (condio != 'anular_cheques') {
        var rows = 1;
        var fila = 0;
        var pibo = 0;
        while (rows <= countid) {
            var mm = datoseliminados.includes(rows);
            if (mm == false) {
                pibo = getinputsval(['debe' + (rows), 'habe' + (rows), 'idcuenta' + (rows), 'fondoid' + (rows)]);
                datainputsd[fila] = (pibo[0] == "") ? 0 : pibo[0];
                datainputsh[fila] = (pibo[1] == "") ? 0 : pibo[1];
                datacuentas[fila] = pibo[2];
                datafondos[fila] = pibo[3];
                fila++;
            }
            rows++;
        }
        datainputs = getinputsval(['datedoc', 'datecont', 'codofi', 'id_agencia', 'cantidad', 'numdoc', 'paguese', 'numletras', 'numcheque', 'glosa', 'totdebe', 'tothaber'])
        dataselects = getinputsval(['negociable', 'bancoid', 'cuentaid', 'id_usuario_asignado'])
        dataRadios = getradiosval(['tipoAsignacion']);
    }
    generico([datainputs, datainputsd, datainputsh, datacuentas, datafondos], dataselects, dataRadios, condio, idr, [usuario, idr]);
}
//#endregion ---------------------------------------------------------------------------------------------------------------------------------------------------------------

//funcion para buscar cuentas de un banco
function buscar_cuentas() {
    idbanco = document.getElementById('bancoid').value;
    id_cuenta = document.getElementById('id_cuenta_b').value;
    //consultar a la base de datos
    $.ajax({
        url: "../src/cruds/crud_bancos.php",
        method: "POST",
        data: { 'condi': 'buscar_cuentas', 'id': idbanco },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            if (data2[1] == "1") {
                $("#cuentaid").empty();
                for (var i = 0; i < data2[2].length; i++) {
                    $("#cuentaid").append("<option value='" + data2[2][i]['id'] + "'>" + data2[2][i]['numcuenta'] + "</option>");
                }
                if (id_cuenta != "") {
                    $("#cuentaid option[value=" + id_cuenta + "]").attr("selected", true);
                }
            }
            else {
                $("#cuentaid").empty();
                $("#cuentaid").append("<option value=''></option>");
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: data2[0] })
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
            // $("#numdoc").val(data2[2]);
            $("#numcheque").val(data2[2]);

            // console.log(data2);
        },
        complete: function () {
            loaderefect(0);
        }
    })
}

//funcion para convertir numeros a letras
function cantidad_a_letras() {
    monto = document.getElementById('cantidad').value;
    //redondear a dos decimales
    montoredondeado = parseFloat(monto).toFixed(2);
    //separar decimales
    var numero_formateado = (montoredondeado).split(".");
    texto = numeroALetras(Number(numero_formateado[0]), {
        plural: " ",
        singular: " "
    });
    $("#numletras").val(texto + numero_formateado[1] + '/100');
}

//funcion para ocultar y mostrar elementos
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

//FUNCION PARA CARGAR EL DATATABLE DE DATOS DE CUENTAS CONTABLES
function cargar_datos_cheques(id_agencia) {
    var tabla;
    tabla = $('#tb_cheques').on('search.dt').DataTable({
        "aProcessing": true,
        "aServerSide": true,
        "ordering": false,
        "lengthMenu": [
            [10, 15, -1],
            ['10 filas', '15 filas', 'Mostrar todos']
        ],
        "ajax": {
            url: '../src/cruds/crud_bancos.php',
            type: "POST",
            beforeSend: function () {
                loaderefect(1);
            },
            data: {
                'condi': "listar_cheques", 'id_agencia': id_agencia
            },
            dataType: "json",
            complete: function () {
                loaderefect(0);
            }
        },
        "bDestroy": true,
        "iDisplayLength": 10,
        "order": [
            [1, "desc"]
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


function cargar_datos_cheques2(id_agencia) {
    $('#tb_cheques').on('search.dt').DataTable({
        "processing": true,
        "serverSide": true,
        "sAjaxSource": "../src/server_side/lista_cheques.php",
        columns: [
            { data: [1] },
            { data: [2] },
            { data: [4] },
            {
                data: [5],
                render: function (data, type, row) {
                    imp = '';
                    if (data == 1) {
                        imp = `<span class="badge bg-success">Sí</span>`;
                    }
                    else {
                        if (row[6] == '' || row[6] == null) {
                            imp = `<span class="badge bg-danger">No</span>`;
                        } else {
                            imp = `<span class="badge bg-warning text-dark">No</span>`;
                        }
                    }
                    // console.log(data);
                    return imp;
                }
            },
            {
                data: [0],
                render: function (data, type, row) {
                    return `<button type="button" class="btn btn-outline-success btn-sm" onclick="printdiv2('#cuadro','${data}')" ><i class="fa-sharp fa-solid fa-eye"></i></i></button>`;
                }
            },
        ],
        "fnServerParams": function (aoData) {
            //PARAMETROS EXTRAS QUE SE LE PUEDEN ENVIAR AL SERVER ASIDE
            aoData.push({ "name": "whereextra", "value": "id_agencia=" + id_agencia });
        },
        "bDestroy": true,
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