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
    dire = "views/usuario/" + dir + ".php";
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
    dire = "views/usuario/" + dir + ".php";
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
    // generico(inputs2, selects2, radios2, condi, id, archivo);
}
//--
function generico(inputs, selects, radios, condi, id, archivo, callback = 'NULL') {
    $.ajax({
        url: "../../src/cruds/crud_usuario.php",
        method: "POST",
        data: { inputs, selects, radios, condi, id, archivo },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            //console.log(data2); return ; 
            if (data2[1] == "1") {
                Swal.fire({ icon: 'success', title: 'Muy Bien!', text: data2[0] });
                if (condi == "savetoken") {
                    saveSrnPc(inputs[0]);
                }
                if (condi == "update_user" || condi == "change_pass") {
                    if (data2[2] == "1") {
                        cerrar_sesion_activa('modificado');
                    }
                }
                else if (condi === 'parametrizaAgencia') {
                    cerrarModal('modal_nomenclatura')
                }
                printdiv2("#cuadro", id);
                if (typeof callback === 'function') {
                    callback(data2);
                }
            }
            else {
                var reprint = ("reprint" in data2) ? data2.reprint : 0;
                var timer = ("timer" in data2) ? data2.timer : 60000;
                Swal.fire({ icon: 'error', title: '¡ERROR!', text: data2[0], 'timer': timer });
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
    })
}
//#endregion
//#region funciones reutilzables
//funcion para eliminar cualquier registro
function eliminar(ideliminar, dir, xtra, condi) {
    dire = "../../src/cruds/" + dir + ".php";
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
                        if (condi == "deletetoken") {
                            removeSrn();
                        }
                        Swal.fire('Correcto', data2[0], 'success');
                        if (condi == "delete_user") {
                            if (data2[2] == "1") {
                                cerrar_sesion_activa('eliminado');
                            }
                        }
                        printdiv2("#cuadro", xtra);
                    }
                    else {
                        Swal.fire('Uff', data2[0], 'error')
                    }
                },
                complete: function () {
                    loaderefect(0);
                }
            })
        }
    })
}

// contrase;a. 
function consultar_password(id_usu) {
    $.ajax({
        url: "../../src/cruds/crud_usuario.php",
        method: "POST",
        data: { 'condi': 'get_password', 'id': id_usu },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            $("#password").val(data2);
            $("#confpass").val(data2);
        },
        complete: function () {
            loaderefect(0);
        }
    })
}

//FUNCION PARA CERRAR SESION EN CASO DE QUE EL USUARIO EDITADO SEA EL ACTIVO
function cerrar_sesion_activa(texto) {
    let timerInterval
    Swal.fire({
        title: 'Aviso Importante',
        html: 'Se cerrara su sesión en <b></b> segundos, porque el usuario ' + texto + ' es el activo. Para continuar vuelva a iniciar sesión',
        timer: 5000,
        timerProgressBar: true,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading()
            const b = Swal.getHtmlContainer().querySelector('b')
            timerInterval = setInterval(() => {
                b.textContent = Math.trunc((Swal.getTimerLeft() / 1000))
            }, 100)
        },
        willClose: () => {
            clearInterval(timerInterval)
        }
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.timer) {
            $.ajax({
                type: 'POST',
                url: '../../src/cruds/crud_usuario.php',
                data: { 'condi': 'salir' },
                dataType: 'json',
                success: function (data) {
                    // console.log(data);
                    window.location.reload();
                },
                error: function (xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'Codigo de error: ' + xhr.status + ', Información de error: ' + xhr.responseJSON
                    });
                },
            });
        }
    })
}
//#endregion
//#region funciones de printdiv 5
function habilitar_deshabilitar(hab, des) {
    var i = 0;
    while (i < hab.length) {
        // console.log("aqui3");
        document.getElementById(hab[i]).disabled = false;
        i++;
    }
    var i = 0;
    while (i < des.length) {
        // console.log("aqui4");
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
//#endregion
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
//#region demas funciones
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

function abrir_modal(id_modal, id_hidden, dato) {
    $(id_modal).modal('show');
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

//#region PARA LA GESTION DE PERMISOS
//Funcion para seleccionar checkboxs
function seleccionar_checks(estado, modulo) {
    alfabeto = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "U", "V", "W", "X", "Y", "Z"];
    for (let index = 0; index < alfabeto.length; index++) {
        if (alfabeto[index] == modulo) {
            var checkboxes = document.getElementsByName(alfabeto[index]);
            for (var checkbox of checkboxes) {
                checkbox.checked = estado;
            }
        }
    }
    evaluar_checkmods();
}
//Deseleccionar un radio button
function desseleccionar_checks(estado, modulo) {
    alfabeto = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "U", "V", "W", "X", "Y", "Z"];
    bandera = false;
    for (let index = 0; index < alfabeto.length; index++) {
        if (alfabeto[index] == modulo) {
            var checkboxes = document.getElementsByName(alfabeto[index]);
            for (var checkbox of checkboxes) {
                if (checkbox.checked == false) {
                    bandera = true;
                }
            }
            var checkboxmod = document.getElementsByName("M-" + alfabeto[index]);
            if (bandera) {
                checkboxmod[0].checked = false;
                var checkboxgen = document.getElementsByName("todos");
                checkboxgen[0].checked = false;

            } else {
                checkboxmod[0].checked = true;
                evaluar_checkmods();
            }
        }
    }
}

//Evaluar cada uno de los checks de modulos
function evaluar_checkmods() {
    alfabeto = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "U", "V", "W", "X", "Y", "Z"];
    bandera = false;
    for (let index = 0; index < alfabeto.length; index++) {
        var checkboxes = document.getElementsByName(alfabeto[index]);
        for (var checkbox of checkboxes) {
            if (checkbox.checked == false) {
                bandera = true;
            }
        }
        var checkboxgen = document.getElementsByName("todos");
        if (bandera) {
            checkboxgen[0].checked = false;
        } else {
            checkboxgen[0].checked = true;
        }

    }
}

//Funcion para seleecionar todos
function seleccionar_todos(estado) {
    alfabeto = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "U", "V", "W", "X", "Y", "Z"];
    for (let index = 0; index < alfabeto.length; index++) {
        //seleccionar encabezados
        var checkboxmod = document.getElementsByName("M-" + alfabeto[index]);
        for (var checkboxs of checkboxmod) {
            checkboxs.checked = estado;
        }
        //seleccionar por modulo
        var checkboxes = document.getElementsByName(alfabeto[index]);
        for (var checkbox of checkboxes) {
            checkbox.checked = estado;
        }
    }
}

//Funcion para capturar los checkboxs marcados
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

//Funcion para recuperar los permisos del usuario
function recuperar_permisos(id) {
    $.ajax({
        url: "../../src/cruds/crud_usuario.php",
        method: "POST",
        data: { 'condi': 'obtener_permisos', 'id': id },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            if (data2[1] == "1") {
                seleccionar_todos(false);
                marcar_permisos_recuperados(data2);
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

//funcion para marcar permisos recuperador
function marcar_permisos_recuperados(data2) {
    for (let index = 0; index < data2[2].length; index++) {
        var check = document.getElementById("S_" + data2[2][index]['id_submenu']);
        check.checked = true;
    }
    //seleccionar 
    alfabeto = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "U", "V", "W", "X", "Y", "Z"];
    bandera = false;
    $contador = 0;
    for (let index = 0; index < alfabeto.length; index++) {

        var checkboxes = document.getElementsByName(alfabeto[index]);
        for (var checkbox of checkboxes) {
            if (checkbox.checked == false) {
                bandera = true;
            }
            $contador++;
        }
        var checkboxmod = document.getElementsByName("M-" + alfabeto[index]);
        if (bandera) {
            checkboxmod[0].checked = false;
            var checkboxgen = document.getElementsByName("todos");
            checkboxgen[0].checked = false;

        } else {
            if ($contador != 0) {
                checkboxmod[0].checked = true;
                evaluar_checkmods();
            }
        }
        bandera = false;
        $contador = 0;
    }
}

//funcion para guardar y editar registros
function guardar_editar_permisos(condi) {
    //validar campos
    var id_usuario = $('#id_usuario').val();
    var id_usuario_past = $('#id_usuario_past').val();
    var usuario = $('#usuario').val();
    var permisos = recoletar_checks();
    // console.log(permisos);
    // return;

    $.ajax({
        url: "../../src/cruds/crud_usuario.php",
        method: "POST",
        data: { 'condi': condi, 'id_actual': id_usuario, 'id_pasado': id_usuario_past, 'usuario': usuario, 'permisos': permisos },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            const data2 = JSON.parse(data);
            // console.log(data2);
            if (data2[1] == "1") {
                Swal.fire('Correcto', data2[0], 'success');
                printdiv2("#cuadro", 0);
            }
            else {
                Swal.fire('Uff', data2[0], 'error')
            }
        },
        complete: function () {
            loaderefect(0);
        }
    })
}



//#endregion

//Iyeccion de codigo 
function inyecCod(idElem, condi, extra = "0", url = "../../views/admin/views/usuario/inyecCod/inyecCod.php") {
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
