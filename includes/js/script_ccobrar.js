//#region ajax generico
function obtiene(inputs, selects, radios, condi, id, archivo, callback = "NULL", messageConfirm = false, mensaje = "", fileDestino = "") {
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
                generico(inputs2, selects2, radios2, condi, id, archivo, callback, fileDestino);
            }
        });
    } else {
        generico(inputs2, selects2, radios2, condi, id, archivo, callback, fileDestino);
    }
}

function generico(inputs, selects, radios, condi, id, archivo, callback, fileDestino = "") {
    // Determinar la URL del archivo destino
    var url = "";
    if (fileDestino && fileDestino !== "NULL" && fileDestino !== "") {
        url = fileDestino;
    } else {
        url = "../../src/cruds/crud_cuentas_cobra.php";
    }
    
    $.ajax({
        url: url,
        method: "POST",
        data: { inputs, selects, radios, condi, id, archivo },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            console.log("Datos recibidos del servidor:", data);
            try {
                const data2 = JSON.parse(data);
                console.log("Respuesta del servidor:", data2);
                
                if (data2.status == 1) {
                    Swal.fire({ icon: "success", title: "Muy Bien!", text: data2.msg });
                    printdiv2("#cuadro", id);
                    if (typeof callback === "function") {
                        callback(data2);
                    }
                } else {
                    Swal.fire({ icon: "error", title: "¡ERROR!", text: data2.msg });
                }
            } catch (e) {
                console.error("Error al parsear JSON:", e);
                console.error("Respuesta del servidor:", data);
                Swal.fire({ icon: "error", title: "¡ERROR!", text: "Error al procesar la respuesta: " + e.message });
            }
        },
        error: function (xhr, status, error) {
            console.error("Error en la petición AJAX:", {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });
            loaderefect(0);
            Swal.fire({ 
                icon: "error", 
                title: "¡ERROR!", 
                text: "Error en la petición: " + xhr.status + " " + xhr.statusText 
            });
        },
        complete: function () {
            loaderefect(0);
        },
    });
}

function getinputsval(datos) {
    const inputs2 = {};
    var i = 0;
    while (i < datos.length) {
        inputs2[datos[i]] = document.getElementById(datos[i]).value;
        i++;
    }
    return inputs2;
}

function getselectsval(datos) {
    const selects2 = {};
    var i = 0;
    while (i < datos.length) {
        var e = document.getElementById(datos[i]);
        selects2[datos[i]] = e.options[e.selectedIndex].value;
        i++;
    }
    return selects2;
}

function getradiosval(datos) {
    const radios2 = {};
    var i = 0;
    while (i < datos.length) {
        radios2[datos[i]] = document.querySelector('input[name="' + datos[i] + '"]:checked').value;
        i++;
    }
    return radios2;
}


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