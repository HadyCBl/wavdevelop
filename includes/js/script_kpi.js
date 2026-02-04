function validateForm() {
    // console.log("joli")
    const fields = document.querySelectorAll('#cuadro [required]');
    let isValid = true;
    // console.log(fields)
    fields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });

    return isValid;
}

//#region FUNCION PARA CARGAR UNA TABLA TIPO DataTable
function convertir_tabla_a_datatable(id_tabla) {
    console.log("convertir_tabla_a_datatable");
    try {
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
    } catch (error) {
        console.error("Error al inicializar DataTable:", error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al cargar la tabla. Intente de nuevo.'
        });
    }
}

function updateResumen() {
    const cuentaAplicacion = document.getElementById("ccodcta").value;
    const selectUsuario = document.getElementById("ccodcta");
    const nombreUsuario = selectUsuario.options[selectUsuario.selectedIndex].text;
    const rol = document.getElementById("rol").value;
    const salario = document.getElementById("salario").value;

    const salarioFormateado = Number(salario).toLocaleString('es-GT', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const resumen = `Usuario: ${nombreUsuario}, || Rol: ${rol}, || Salario: Q${salarioFormateado}`;

    document.getElementById("resumen").value = resumen;
}

function verifi_ejec() {
    var selectUsuario = document.getElementById("ccodcta");
    var selectRol = document.getElementById("rol");
    var selectSalario = document.getElementById("salario");
    var usuarioSeleccionado = selectUsuario.value;
    var rolSeleccionado = selectRol.value;
    var salarioSeleccionado = selectSalario.value;

    if (usuarioSeleccionado === "0" || rolSeleccionado === "0" || salarioSeleccionado === "" || salarioSeleccionado === "0") {
        Swal.fire({
            icon: 'warning',
            title: 'Campos vacíos',
            text: 'Todos los campos son obligatorios.'
        });
        return;
    }

    $.ajax({
        url: "../../../src/cruds/crud_kpi.php",
        type: "POST",
        data: {
            condi: 'add_eject',
            usuario: usuarioSeleccionado,
            rol: rolSeleccionado,
            salario: salarioSeleccionado
        },
        beforeSend: function () {
            loaderefect(1);
        },
        success: function (data) {
            // console.log(data); // Ver la respuesta del servidor
            const data2 = JSON.parse(data);
            if (data2.status == 1) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Muy Bien!',
                    text: data2.message
                });
                $('#tabla_ejecutivos').DataTable().ajax.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '¡ERROR!',
                    text: data2.message
                });
            }
        },
        complete: function () {
            loaderefect(0);
        }
    });
}

function cargarEjecutivos() {
    $('#tabla_ejecutivos').DataTable().ajax.reload();
}

function verifi_poa() {
    var selectUsuario = document.getElementById("ccodcta");
    var selectRol = document.getElementById("anio");
    var selectcartera = document.getElementById("cartera_cred");
    var selectClientes = document.getElementById("clientes");
    var selectGrupos = document.getElementById("grupos");
    var selectColocaciones = document.getElementById("colocaciones");
    var selectresumen = document.getElementById("resumen");
    var selectmes_cal = document.getElementById("mes_cal");

    let usuarioSeleccionado = selectUsuario.value;
    let anioSeleccionado = selectRol.value;
    let CarteraSeleccionado = selectcartera.value;
    let ClientesSeleccionado = selectClientes.value;
    let GruposSeleccionado = selectGrupos.value;
    let ColocacionesSeleccionado = selectColocaciones.value;
    let mes_cal = selectmes_cal.value;

    // Validar campos vacíos
    if (usuarioSeleccionado === "0" || anioSeleccionado === "" || CarteraSeleccionado === "" || ClientesSeleccionado === "" || GruposSeleccionado === "" || mes_cal === "") {
        Swal.fire({
            icon: 'warning',
            title: 'Campos vacíos',
            text: 'Todos los campos son obligatorios excepto "Colocaciones".'
        });
        return;
    }

    // Validar campos numéricos
    if (isNaN(anioSeleccionado) || isNaN(CarteraSeleccionado) || isNaN(ClientesSeleccionado) || isNaN(GruposSeleccionado) || isNaN(mes_cal)) {
        Swal.fire({
            icon: 'warning',
            title: 'Datos inválidos',
            text: 'Todos los campos deben contener valores numéricos válidos.'
        });
        return;
    }

    Swal.fire({
        title: 'Calculo',
        text: "¿Quieres calcular automáticamente o manualmente?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Automático',
        cancelButtonText: 'Manual'
    }).then((result) => {
        if (result.isConfirmed) {
            // Cálculo automático
            let carteraMensual = CarteraSeleccionado / mes_cal;
            let clientesMensual = ClientesSeleccionado / mes_cal;
            let gruposMensual = GruposSeleccionado / mes_cal;

            $.ajax({
                url: "../../../src/cruds/crud_kpi.php",
                type: "POST",
                dataType: "json", // Asegura que la respuesta sea interpretada como JSON
                data: {
                    condi: 'add_poa',
                    usuario: usuarioSeleccionado,
                    anio: anioSeleccionado,
                    cartera: CarteraSeleccionado,
                    clientes: ClientesSeleccionado,
                    grupos: GruposSeleccionado,
                    colocaciones: ColocacionesSeleccionado,
                    mes_cal: mes_cal,
                    cartera_mensual: carteraMensual,
                    clientes_mensual: clientesMensual,
                    grupos_mensual: gruposMensual
                },
                beforeSend: function () {
                    loaderefect(1);
                },
                success: function (data) {
                    if (data.status == 1) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Muy Bien!',
                            text: data.message
                        }).then(() => {
                            // Limpiar 
                            selectUsuario.value = "0";
                            selectRol.value = "0";
                            selectcartera.value = "0";
                            selectClientes.value = "0";
                            selectGrupos.value = "0";
                            selectColocaciones.value = "0";
                            selectresumen.value = " ";
                            // Recargar la tabla después de la inserción exitosa
                            recargarTablaPOA();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '¡ERROR!',
                            text: data.message // Mostrar mensaje de error del servidor
                        });
                    }
                },
                complete: function () {
                    loaderefect(0); // Finalizar el efecto de carga
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    Swal.fire({
                        icon: 'error',
                        title: '¡ERROR!',
                        text: 'Error en la solicitud: ' + textStatus + ', ' + errorThrown
                    });
                }
            });
        } else {
            // Ejecutar manualmente (mostrar el modal para que el usuario vea los datos)
            update_verifi_poa(usuarioSeleccionado, anioSeleccionado, false);  // Pasar `false` para ejecución manual
        }
    });
}

// Función para recargar la tabla POA mediante un
function recargarTablaPOA() {
    printdiv2("#cuadro", 0);
}

function guardarCambiosPOA() {
    const filas = document.querySelectorAll("#detalle_poa tr");

    let datosActualizados = [];
    let validacionExitosa = true;
    const regexNumero = /^\d+(\.\d+)?$/;

    filas.forEach((fila, index) => {
        const id = fila.querySelector('input[name^="id"]').value.trim();
        const cartera_creditos = fila.querySelector('input[name^="cartera_creditos"]').value.trim();
        const clientes = fila.querySelector('input[name^="clientes"]').value.trim();
        const grupos = fila.querySelector('input[name^="grupos"]').value.trim();
        const colocaciones = fila.querySelector('input[name^="colocaciones"]').value.trim();
        const cancel = fila.querySelector('input[name^="cancel"]').value.trim();

        if (!regexNumero.test(cartera_creditos) ||
            !regexNumero.test(clientes) ||
            !regexNumero.test(grupos) ||
            !regexNumero.test(colocaciones) ||
            !regexNumero.test(cancel)) {
            Swal.fire({
                icon: 'error',
                title: 'Error en los datos',
                text: `Por favor verifica los campos de la fila ${index + 1} (Unicamente numeros)`,
                confirmButtonText: 'Aceptar'
            });
            validacionExitosa = false;
            return;
        }

        datosActualizados.push({
            id,
            cartera_creditos,
            clientes,
            grupos,
            colocaciones,
            cancel
        });
    });

    if (!validacionExitosa) return;

    Swal.fire({
        icon: 'question',
        title: 'Confirmación',
        text: 'Todos los datos son válidos. ¿Deseas guardar estos datos permanentemente?',
        showCancelButton: true,
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        console, log("llego");
        if (result.isConfirmed) {
            console, log("llego");
            $.ajax({
                url: "../../../src/cruds/crud_kpi.php",
                type: "POST",
                data: {
                    condi: 'update_data_poa', // Parámetro único y validador
                    datos: datosActualizados
                },
                success: function (response) {
                    console.log(response);
                    console.log("funciona");
                    Swal.fire({
                        icon: response.status === "success" ? 'success' : 'error',
                        title: response.status === "success" ? 'Datos guardados' : 'Error',
                        text: response.message,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        // Recargar la tabla después de la actualización exitosa
                        recargarTablaPOA();
                    });
                    console.log(response);
                },
                error: function (xhr, status, error) {
                    console.log(error);
                    console.log("fallo con exito");
                    Swal.fire({

                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al guardar los datos. Por favor, intenta de nuevo.',
                        confirmButtonText: 'Aceptar'
                    });
                    console.error(xhr, status, error);
                }
            });
        }
    });
    console.log(datosActualizados);
}
//Guardar cambios POA esta si debe de funcionar 
function saveDataPoa() {
    const filas = document.querySelectorAll("#detalle_poa tr");

    let datosActualizados = [];
    let validacionExitosa = true;
    const regexNumero = /^\d+(\.\d+)?$/;

    filas.forEach((fila, index) => {
        const id = fila.querySelector('input[name^="id"]').value.trim();
        const cartera_creditos = fila.querySelector('input[name^="cartera_creditos"]').value.trim();
        const clientes = fila.querySelector('input[name^="clientes"]').value.trim();
        const grupos = fila.querySelector('input[name^="grupos"]').value.trim();
        const colocaciones = fila.querySelector('input[name^="colocaciones"]').value.trim();
        const cancel = fila.querySelector('input[name^="cancel"]').value.trim();

        if (!regexNumero.test(cartera_creditos) ||
            !regexNumero.test(clientes) ||
            !regexNumero.test(grupos) ||
            !regexNumero.test(colocaciones) ||
            !regexNumero.test(cancel)) {
            Swal.fire({
                icon: 'error',
                title: 'Error en los datos',
                text: `Por favor verifica los campos de la fila ${index + 1} (Unicamente numeros)`,
                confirmButtonText: 'Aceptar'
            });
            validacionExitosa = false;
            return;
        }

        datosActualizados.push({
            id,
            cartera_creditos,
            clientes,
            grupos,
            colocaciones,
            cancel
        });
    });

    if (!validacionExitosa) return;

    Swal.fire({
        icon: 'question',
        title: 'Confirmación',
        text: 'Todos los datos son válidos. ¿Deseas guardar estos datos permanentemente?',
        showCancelButton: true,
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "../../../src/cruds/crud_kpi.php",
                type: "POST",
                data: {
                    condi: 'update_data_poa',
                    datos: datosActualizados
                },
                success: function (response) {
                    const res = JSON.parse(response);
                    Swal.fire({
                        icon: res.status === "success" ? 'success' : 'error',
                        title: res.status === "success" ? 'Datos guardados' : 'Error',
                        text: res.message,
                        confirmButtonText: 'Aceptar'
                    });
                },
                error: function (xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al guardar los datos. Por favor, intenta de nuevo.',
                        confirmButtonText: 'Aceptar'
                    });
                    console.error(xhr, status, error);
                }
            });
        }
    });
}

function actualizarResumenTextarea() {
    // Obtener elementos
    const selectUsuario = document.getElementById('ccodcta');
    const inputAnio = document.getElementById('anio');
    const carteraCred = formatearMoneda(document.getElementById('cartera_cred').value);
    const clientes = document.getElementById('clientes').value;
    const grupos = document.getElementById('grupos').value;
    const colocaciones = formatearMoneda(document.getElementById('colocaciones').value);
    const anio = inputAnio ? inputAnio.value : '';
    const nombreUsuario = selectUsuario.options[selectUsuario.selectedIndex].text;

    const resumen = `
    ${'Ejecutivo:'.padEnd(50)} ${nombreUsuario !== "Seleccione un Ejecutivo" ? nombreUsuario : 'No seleccionado'}
    ${'Año:'.padEnd(48)} ${anio || 'No ingresado'}
    ${'Cartera de Créditos:'.padEnd(34)} ${carteraCred || 'No ingresado'}
    ${'Clientes:'.padEnd(45)} ${clientes || 'No ingresado'}
    ${'Grupos:'.padEnd(45)} ${grupos || 'No ingresado'}
    ${'Colocaciones:'.padEnd(39)} ${colocaciones || 'No ingresado'}
    `;
    // Actualizar el textarea con el resumen
    document.getElementById('resumenTextarea').value = resumen.trim();
}

// Escuchar cambios
document.querySelectorAll('#ccodcta, #anio, #cartera_cred, #clientes, #grupos, #colocaciones').forEach(input => {
    input.addEventListener('input', actualizarResumenTextarea);
});

function formatearMoneda(valor) {
    if (!valor) return '';
    return parseFloat(valor).toLocaleString('es-GT', {
        style: 'currency',
        currency: 'GTQ',
        minimumFractionDigits: 2
    });
}

// Declarar y configurar el año una sola vez
if (!window.anioActual) { // Prevenir redeclaración
    const anioActual = new Date().getFullYear();
    const inputAnio = document.getElementById('anio');
    if (inputAnio) {
        inputAnio.setAttribute('min', anioActual);
        inputAnio.setAttribute('value', anioActual);
    }
}

// Función para recargar la tabla POA


// Función modal update POA
/*
function update_verifi_poa(usuario, anio) {
    $.ajax({
        url: "../../../src/cruds/crud_kpi.php",
        type: "POST",
        data: {
            condi: 'get_poa',
            usuario: usuario,
            anio: anio
        },
        success: function(data) {
            try {
                const response = JSON.parse(data);
                if (response.status === 1) {
                    // Actualizar el modal con los datos recibidos
                    document.getElementById('poa_header').innerHTML = response.header;
                    document.getElementById('detalle_poa').innerHTML = response.detalle;
                    $('#modal_verifi_poa').modal('show');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '¡Error!',
                        text: response.message
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: '¡Error!',
                    text: 'Hubo un problema al procesar la respuesta del servidor.'
                });
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            Swal.fire({
                icon: 'error',
                title: '¡ERROR!',
                text: 'Error en la solicitud: ' + textStatus + ', ' + errorThrown
            });
        }
    });
}*/

function showModal() {
    document.getElementById('modal_verifi_poa').classList.remove('hidden');
}

function hideModal() {
    document.getElementById('modal_verifi_poa').classList.add('hidden');
    location.reload();
}


//funcion modal
function update_verifi_poa(usuario, anio) {
    $.ajax({
        url: "../../../src/cruds/crud_kpi.php",
        type: "POST",
        data: {
            condi: 'fetch_poa_details',
            usuario,
            anio
        },
        beforeSend: () => loaderefect(1),
        success: data => {
            try {
                const response = JSON.parse(data);
                if (response.status !== "success") {
                    return Swal.fire('Error', response.message || 'No hubo datos', 'error');
                }

                // Nombres de los meses
                const meses = [
                    "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
                    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
                ];

                // 1) Objetivos anuales tomados DEL FORMULARIO PRINCIPAL
                const carteraAnual = parseFloat($('#cartera_cred').val()) || 0;
                const clientesAnual = parseFloat($('#clientes').val()) || 0;
                const gruposAnual = parseFloat($('#grupos').val()) || 0;

                // 2) Cálculo uniforme por mes (o pon aquí tus pesos si quieres)
                const mesesCount = 12;
                const carteraMensual = carteraAnual / mesesCount;
                const clientesMensual = clientesAnual / mesesCount;
                const gruposMensual = gruposAnual / mesesCount;

                // 3) Creamos 12 filas (response.detalles ya no se usa para las metas)
                const filas = Array.from({ length: 12 }, (_, i) => ({
                    id: 0,
                    mes: i + 1,
                    cartera: carteraMensual.toFixed(2),
                    diferencia: (carteraMensual * 0.6).toFixed(2), // 60%
                    clientes: clientesMensual.toFixed(0),
                    grupos: gruposMensual.toFixed(0),
                    cancel: '',  // manual
                    colocaciones: ''   // manual
                }));

                // 4) Generamos el HTML de detalle
                const filasHTML = filas.map((d, idx) => `
          <tr data-row="${idx}">
            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">
              <input type="hidden" name="id[${idx}]" value="${d.id}">
              ${d.id ? d.id : '-'}
            </td>
            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">
              <input type="hidden" name="mes[${idx}]" value="${d.mes}">
              ${meses[d.mes - 1]}
            </td>
            <td class="px-4 py-2">
              <input type="number" 
                     name="cartera_creditos[${idx}]" 
                     value="${d.cartera}" 
                     class="w-full px-2 py-1 border rounded" readonly>
            </td>
            <td class="px-4 py-2">
              <input type="number" 
                     name="diferencia[${idx}]" 
                     value="${d.diferencia}" 
                     class="w-full px-2 py-1 border rounded" readonly>
            </td>
            <td class="px-4 py-2">
              <input type="number"
                     name="clientes[${idx}]" 
                     value="${d.clientes}" 
                     class="w-full px-2 py-1 border rounded" readonly>
            </td>
            <td class="px-4 py-2">
              <input type="number" 
                     name="grupos[${idx}]" 
                     value="${d.grupos}" 
                     class="w-full px-2 py-1 border rounded" readonly>
            </td>
            <td class="px-4 py-2">
              <input type="number" 
                     name="cancel[${idx}]" 
                     value="${d.cancel}" 
                     class="w-full px-2 py-1 border rounded">
            </td>
            <td class="px-4 py-2">
              <input type="number" 
                     name="colocaciones[${idx}]" 
                     value="${d.colocaciones}" 
                     class="w-full px-2 py-1 border rounded">
            </td>
          </tr>
        `).join("");

                // 5) Insertamos SOLO el detalle en el modal
                document.getElementById('poa_header').innerHTML = '';
                document.getElementById('detalle_poa').innerHTML = filasHTML;

                showModal();
            } catch (err) {
                console.error("Error procesando el modal:", err, data);
                Swal.fire('Error', 'No se pudo procesar el modal', 'error');
            }
        },
        complete: () => loaderefect(0),
        error: () => Swal.fire('Error', 'Fallo al cargar datos', 'error')
    });
}


function setupCalculationListeners() {
    // Función para calcular colocaciones
    function calculateColocaciones(row) {
        const cartera = parseFloat(row.querySelector('.cartera').value) || 0;
        const diferencia = parseFloat(row.querySelector('.diferencia').value) || 0;
        const cancel = parseFloat(row.querySelector('.cancel').value) || 0;
        const colocaciones = cartera + diferencia + cancel;
        row.querySelector('.colocaciones').value = colocaciones.toFixed(2);
    }
    //  cálculo
    document.querySelectorAll('.calc-input').forEach(input => {
        input.addEventListener('input', function () {
            const row = this.closest('tr');
            calculateColocaciones(row);
        });
    });
    document.querySelectorAll('tr[data-row]').forEach(row => {
        calculateColocaciones(row);
    });
}

function saveDataPoa() {
    const filas = document.querySelectorAll("#detalle_poa tr");

    let datosActualizados = [];
    let validacionExitosa = true;
    const regexNumero = /^\d+(\.\d+)?$/;

    filas.forEach((fila, index) => {
        const id = fila.querySelector('input[name^="id"]').value.trim();
        const cartera_creditos = fila.querySelector('input[name^="cartera_creditos"]').value.trim();
        const clientes = fila.querySelector('input[name^="clientes"]').value.trim();
        const grupos = fila.querySelector('input[name^="grupos"]').value.trim();
        const colocaciones = fila.querySelector('input[name^="colocaciones"]').value.trim();
        const cancel = fila.querySelector('input[name^="cancel"]').value.trim();

        if (!regexNumero.test(cartera_creditos) ||
            !regexNumero.test(clientes) ||
            !regexNumero.test(grupos) ||
            !regexNumero.test(colocaciones) ||
            !regexNumero.test(cancel)) {
            Swal.fire({
                icon: 'error',
                title: 'Error en los datos',
                text: `Por favor verifica los campos de la fila ${index + 1} (Unicamente numeros)`,
                confirmButtonText: 'Aceptar'
            });
            validacionExitosa = false;
            return;
        }

        datosActualizados.push({
            id,
            cartera_creditos,
            clientes,
            grupos,
            colocaciones,
            cancel
        });
    });

    if (!validacionExitosa) return;

    Swal.fire({
        icon: 'question',
        title: 'Confirmación',
        text: 'Todos los datos son válidos. ¿Deseas guardar estos datos permanentemente?',
        showCancelButton: true,
        confirmButtonText: 'Aceptar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "../../../src/cruds/crud_kpi.php",
                type: "POST",
                data: {
                    condi: 'update_data_poa',
                    datos: datosActualizados
                },
                success: function (response) {
                    const res = JSON.parse(response);
                    Swal.fire({
                        icon: res.status === "success" ? 'success' : 'error',
                        title: res.status === "success" ? 'Datos guardados' : 'Error',
                        text: res.message,
                        confirmButtonText: 'Aceptar'
                    });
                },
                

                error: function (xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al guardar los datos. Por favor, intenta de nuevo.',
                        confirmButtonText: 'Aceptar'
                    });
                    console.error(xhr, status, error);
                }
            });
            hideModal();
        }
    });
}

function setupCalculationListeners() {
    // Función para calcular colocaciones
    function calculateColocaciones(row) {
        const cartera = parseFloat(row.querySelector('.cartera').value) || 0;
        const diferencia = parseFloat(row.querySelector('.diferencia').value) || 0;
        const cancel = parseFloat(row.querySelector('.cancel').value) || 0;
        const colocaciones = cartera + diferencia + cancel;
        row.querySelector('.colocaciones').value = colocaciones.toFixed(2);
    }
    //  cálculo
    document.querySelectorAll('.calc-input').forEach(input => {
        input.addEventListener('input', function () {
            const row = this.closest('tr');
            calculateColocaciones(row);
        });
    });
    document.querySelectorAll('tr[data-row]').forEach(row => {
        calculateColocaciones(row);
    });
}


function cargarEjecutivos_poa() {
    $('#tabla_poa').DataTable().ajax.reload();
}

//JS para modulo consultar POA
function consultar_kpi1() {
    let ejecutivo = document.getElementById("ejecutivo").value;
    let codofi = document.getElementById("codofi").value;
    let anio = document.getElementById("anio").value;
    let mes = document.getElementById("mes").value;
    let tipo = document.querySelector('input[name="tipo"]:checked').value;

    if (tipo === "agencia") {
        if (!codofi || codofi === "0") {
            Swal.fire({
                icon: 'warning',
                title: 'Advertencia',
                text: 'Seleccione una agencia antes de consultar.'
            });
            return;
        }
    } else if (tipo === "individual") {
        if (!ejecutivo || ejecutivo === "0") {
            Swal.fire({
                icon: 'warning',
                title: 'Advertencia',
                text: 'Seleccione un ejecutivo antes de consultar.'
            });
            return;
        }
    }

    if (anio === "0" || mes === "0") {
        Swal.fire({
            icon: 'warning',
            title: 'Advertencia',
            text: 'Seleccione un año y un mes antes de consultar.'
        });
        return;
    }

    let condi = (tipo === "agencia") ? "consult_poa_agen" : "consult_poa1";

    loaderefect(1);
    $.ajax({
        url: "../../../src/cruds/crud_kpi.php",
        type: "POST",
        dataType: "json",
        cache: false,
        data: {
            condi: condi,
            ejecutivo: ejecutivo,
            anio: anio,
            mes: mes,
            codofi: codofi,
            tipo: tipo
        },
        success: function (response) {
            loaderefect(0);
            if (response.status === "success") {
                mostrarDatosEnCards(response.data);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message
                });
            }
        },
        error: function (xhr, status, error) {
            loaderefect(0);
            Swal.fire({
                icon: 'error',
                title: 'Error en la solicitud',
                text: "Hubo un error en la solicitud. Verifique la consola para más detalles."
            });
            console.error("Error en la solicitud AJAX: ", error);
        }
    });
}


function mostrarDatosEnCards(data) {
    console.log("Datos para mostrar:", data);

    // Limpiar contenedores primero
    document.querySelector(".mes-text").textContent = "";
    document.querySelector(".ejecutivo-text").textContent = "";
    document.querySelector(".creditos-text").textContent = "0";
    document.querySelector(".clientes_for").textContent = "0";
    document.querySelector(".grupos_for").textContent = "0";
    document.querySelector(".colocaciones_for").textContent = "0";

    if (data.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Sin datos',
            text: 'No se encontraron registros para los filtros seleccionados.'
        });
        return;
    }

    document.getElementById("contenedorCards").style.display = "block";
    document.getElementById("contenedorCards2").style.display = "block";
    document.getElementById("tablaContenedor").style.display = "none";

    const tieneFlag1 = data.some(item => item.flag1);
    if (tieneFlag1) {
        document.getElementById("contenedorCards").style.display = "none";
        document.getElementById("contenedorCards2").style.display = "none";
        document.getElementById("tablaContenedor").style.display = "block";
        llenarTabla(data);
    } else {
        data.forEach((item) => {
            document.querySelector(".mes-text").textContent = item.mes || "Mes no especificado";
            document.querySelector(".ejecutivo-text").textContent = item.nombre_comp || "Nombre de ejecutivo";
            document.querySelector(".creditos-text").textContent = item.cartera_creditos_for || "0";
            document.querySelector(".clientes_for").textContent = item.clientes_for || "0";
            document.querySelector(".grupos_for").textContent = item.grupos_for || "0";
            document.querySelector(".colocaciones_for").textContent = item.colocaciones_for || "0";
        });
    }
}

function llenarTabla(data) {
    const tablaBody = document.getElementById("tablaContenedor").querySelector("tbody");
    const tablaFoot = document.getElementById("tablaContenedor").querySelector("tfoot");

    // Destruir la tabla DataTable si existe
    if ($.fn.DataTable.isDataTable('#tabla_ejecutivos')) {
        $('#tabla_ejecutivos').DataTable().clear().destroy();
    }

    // Limpiar contenido actual de la tabla
    tablaBody.innerHTML = "";
    tablaFoot.innerHTML = "";

    // Inicializar totales
    let totalCartera = 0;
    let totalClientes = 0;
    let totalGrupos = 0;
    let totalColocaciones = 0;

    // Mapeo de meses
    const meses = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

    // Funciones de formato y parseo
    const parseFormattedNumber = (str) => parseFloat((str || "0").replace(/[^0-9.-]+/g, "")) || 0;
    const formatNumber = (num) => new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2 }).format(num);

    // Generar filas dinámicamente
    data.forEach((item) => {
        const fila = document.createElement("tr");

        fila.innerHTML = `
            <td>${meses[item.mes - 1] || "Mes no especificado"}</td>
            <td>${item.nombre_comp || "Nombre de ejecutivo"}</td>
            <td data-value="${parseFormattedNumber(item.cartera_creditos_for)}">${formatNumber(parseFormattedNumber(item.cartera_creditos_for))}</td>
            <td data-value="${parseFormattedNumber(item.clientes_for)}">${Math.round(parseFormattedNumber(item.clientes_for))}</td>
            <td data-value="${parseFormattedNumber(item.grupos_for)}">${Math.round(parseFormattedNumber(item.grupos_for))}</td>
            <td data-value="${parseFormattedNumber(item.colocaciones_for)}">${formatNumber(parseFormattedNumber(item.colocaciones_for))}</td>
        `;

        totalCartera += parseFormattedNumber(item.cartera_creditos_for);
        totalClientes += parseFormattedNumber(item.clientes_for);
        totalGrupos += parseFormattedNumber(item.grupos_for);
        totalColocaciones += parseFormattedNumber(item.colocaciones_for);

        tablaBody.appendChild(fila);
    });

    // Agregar fila de totales al footer
    const filaTotales = document.createElement("tr");
    filaTotales.innerHTML = `
        <td colspan="2"><strong>Total:</strong></td>
        <td><strong>${formatNumber(totalCartera)}</strong></td>
        <td><strong>${Math.round(totalClientes)}</strong></td>
        <td><strong>${Math.round(totalGrupos)}</strong></td>
        <td><strong>${formatNumber(totalColocaciones)}</strong></td>
    `;
    tablaFoot.appendChild(filaTotales);

    // Inicializar DataTable
    $('#tabla_ejecutivos').DataTable({
        destroy: true,
        paging: true,
        searching: true,
        ordering: true,
        autoWidth: false,
        columnDefs: [
            {
                targets: [2, 3, 4, 5], // Columnas con datos numéricos
                type: 'num',
                render: function (data, type, row) {
                    if (type === 'sort') {
                        return $(row).attr('data-value');
                    }
                    return data;
                }
            }
        ]
    });
}

function consultar_cumpli_metas() {
    // Obtener los valores de los campos
    let ejecutivo = document.getElementById("ejecutivo").value;
    let codofi = document.getElementById("codofi").value;
    let anio = document.getElementById("anio").value;
    let tipo = document.querySelector('input[name="tipo"]:checked').value;

    // Validaciones
    if (tipo === "agencia") {
        if (!codofi || codofi === "0") {
            Swal.fire({
                icon: "error",
                title: "Validación fallida",
                text: "Debe seleccionar al menos una agencia.",
                confirmButtonText: "Entendido",
            });
            return false;
        }
    } else if (tipo === "individual") {
        if (!ejecutivo || ejecutivo === "0") {
            Swal.fire({
                icon: "error",
                title: "Validación fallida",
                text: "Debe seleccionar al menos un ejecutivo.",
                confirmButtonText: "Entendido",
            });
            return false;
        }
    }

    // Validar año
    if (anio === "0") {
        Swal.fire({
            icon: 'warning',
            title: 'Advertencia',
            text: 'Seleccione un año.'
        });
        return;
    }

    // Determinar condición basada en el tipo
    let condi = (tipo === "agencia") ? "consult_cump_agen" : "consult_cump_indi";

    // Mostrar efecto de carga
    loaderefect(1);

    // Llamada AJAX
    $.ajax({
        url: "../../../src/cruds/crud_kpi.php",
        type: "POST",
        dataType: "json",
        cache: false,
        data: {
            condi: condi,
            ejecutivo: ejecutivo,
            anio: anio,
            codofi: codofi,
            tipo: tipo
        },
        success: function (response) {
            loaderefect(0);
            console.log(response);
            if (response.status === "success") {
                mostrarDatosEnTabla(response);
                Swal.fire({
                    icon: 'success',
                    title: 'Consulta exitosa',
                    text: 'Datos consultados correctamente.',
                    timer: 1000,
                    timerProgressBar: true
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || "Error en la consulta."
                });
            }
        },
        error: function (xhr, status, error) {
            loaderefect(0);
            Swal.fire({
                icon: 'error',
                title: 'Error en la solicitud',
                text: "Hubo un error en la solicitud."
            });
            console.error("Error en la solicitud AJAX: ", error);
        }
    });
}

//metodos y funciones para mostrar datos del resumen de los KPI
function consultarResumenEjecutivo() {
    let ejecutivo = document.getElementById("ejecutivo").value;
    let anio = document.getElementById("anio").value;
    let mes = document.getElementById("mes").value;

    if (!ejecutivo || ejecutivo === "0") {
        Swal.fire({
            icon: "error",
            title: "Validación fallida",
            text: "Debe seleccionar al menos un ejecutivo.",
            confirmButtonText: "Entendido",
        });
        return false;
    }

    if (anio === "0" || mes === "0") {
        Swal.fire({
            icon: 'warning',
            title: 'Advertencia',
            text: 'Seleccione un año y un mes antes de consultar.'
        });
        return;
    }

    let condi = "consult_cump_indi";

    loaderefect(1);
    $.ajax({
        url: "../../../src/cruds/crud_kpi.php",
        type: "POST",
        dataType: "json",
        cache: false,
        data: {
            condi: condi,
            ejecutivo: ejecutivo,
            anio: anio,
            mes: mes
        },
        success: function (response) {
            loaderefect(0);
            if (response.status === "success") {
                mostrarDatosEnResumen(response.data);
                Swal.fire({
                    icon: 'success',
                    title: 'Consulta exitosa',
                    text: 'Datos consultados correctamente.',
                    timer: 1000,
                    timerProgressBar: true
                });
            } else {
                console.error("Error en la consulta: ", response.message);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || "Error en la consulta."
                });
            }
        },
        error: function (xhr, status, error) {
            loaderefect(0);
            console.error("Error en la solicitud AJAX: ", error);
            Swal.fire({
                icon: 'error',
                title: 'Error en la solicitud',
                text: "Hubo un error en la solicitud."
            });
        }
    });
}
//fin resumen ejecutivo
//inicio resumen por agencia 

function consultarResumenAgencia() {
    let codofi = document.getElementById("codofi").value;
    let anio = document.getElementById("anio").value;
    let mes = document.getElementById("mes").value;

    if (!codofi || codofi === "0") {
        Swal.fire({
            icon: "error",
            title: "Validación fallida",
            text: "Debe seleccionar al menos una agencia.",
            confirmButtonText: "Entendido",
        });
        return false;
    }

    if (anio === "0" || mes === "0") {
        Swal.fire({
            icon: 'warning',
            title: 'Advertencia',
            text: 'Seleccione un año y un mes antes de consultar.'
        });
        return;
    }

    let condi = "consult_resumen_agencia";

    loaderefect(1);
    $.ajax({
        url: "../../../src/cruds/crud_kpi.php",
        type: "POST",
        dataType: "json",
        cache: false,
        data: {
            condi: condi,
            codofi: codofi,
            anio: anio,
            mes: mes
        },
        success: function (response) {
            loaderefect(0);
            if (response.status === "success") {
                mostrarDatosEnResumen(response.data);
                Swal.fire({
                    icon: 'success',
                    title: 'Consulta exitosa',
                    text: 'Datos consultados correctamente.',
                    timer: 1000,
                    timerProgressBar: true
                });
            } else {
                console.error("Error en la consulta: ", response.message);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || "Error en la consulta."
                });
            }
        },
        error: function (xhr, status, error) {
            loaderefect(0);
            console.error("Error en la solicitud AJAX: ", error);
            Swal.fire({
                icon: 'error',
                title: 'Error en la solicitud',
                text: "Hubo un error en la solicitud."
            });
        }
    });
}

function mostrarDatosEnResumen(data) {
    const cardContainer = document.getElementById("cardContainer");
    cardContainer.style.display = "block";

    // Actualizar los datos de la tarjeta con los datos obtenidos
    document.getElementById("total-desembolsado").textContent = `Q${data[0].total_desembolsado}`;
    document.getElementById("cumplimiento").textContent = `${data[0].cumplimiento}%`;
    document.getElementById("clientes-obtenidos").textContent = `${data[0].clientes_kpi} Clientes obt.`;
    document.getElementById("clientes-cumplimiento").textContent = `${data[0].clientes_real}%`;
    document.getElementById("grupos-alcanzados").textContent = `${data[0].grupos_kpi} grupos alcanzados`;
    document.getElementById("grupos-cumplimiento").textContent = `${data[0].grupos_real}%`;
    document.getElementById("saldo-anterior").textContent = `Q${data[0].saldo_actual}`;
    document.getElementById("saldo-cumplimiento").textContent = `${data[0].tasa_recuperacion}%`;

    // Actualizar el gauge
    const pointer = document.getElementById("pointer");
    const porcentaje = (data[0].cartera_kpi / 100) * 180; // Ajusta el cálculo según tus necesidades
    pointer.style.transform = `rotate(${porcentaje}deg)`;

    // Actualizar las métricas de riesgo
    document.getElementById("riesgo-menor-30").textContent = `Q${data[0].saldo_actual}`;
    document.getElementById("riesgo-mayor-30").textContent = `Q${data[0].cartera_en_riesgo}`;

    // Actualizar el pronóstico
    document.getElementById("mes-pronostico").textContent = data[0].mes;
    document.getElementById("saldo-mes-anterior").textContent = `Q${data[0].saldo_actual}`;
    document.getElementById("proyeccion-colocacion").textContent = `Q${data[0].colocaciones_kpi}`;
}

function mostrarResumen() {
    const cardContainer = document.getElementById("cardContainer");
    const botonResumen = document.getElementById("botonResumen");

    if (cardContainer.style.display === "none" || cardContainer.style.display === "") {
        const tipo = document.querySelector('input[name="tipo"]:checked').value;
        if (tipo === "agencia") {
            consultarResumenAgencia();
        } else if (tipo === "individual") {
            consultarResumenEjecutivo();
        }
        cardContainer.style.display = "block";
        botonResumen.innerHTML = '<i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Ocultar Resumen';
    } else {
        cardContainer.style.display = "none";
        botonResumen.innerHTML = '<i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Mostrar Resumen';
    }
}


function mostrarDatosEnTabla(response) {
    // Capta y muestra los datos tal y como se reciben
    response.data.forEach(row => {
        row.total_desembolsado = parseFloat(row.total_desembolsado.replace(/,/g, ''));
        row.cartera_kpi = parseFloat(row.cartera_kpi.replace(/,/g, ''));
        row.total_pagado = parseFloat(row.total_pagado.replace(/,/g, ''));
        row.saldo_actual = parseFloat(row.saldo_actual.replace(/,/g, ''));
    });

    // Encabezados
    const headers = [
        { title: 'Mes', data: 'mes' },
        { title: 'Año', data: 'anio' },
        { data: 'periodo', title: 'Periodo', visible: false },
        { title: 'Código Agencia', data: 'codagencia', visible: false },
        {
            title: 'Cartera Proy.',
            data: 'cartera_kpi',
            render: $.fn.dataTable.render.number(',', '.', 2, 'Q')
        },
        {
            title: 'Saldo Actual',
            data: 'saldo_actual',
            render: $.fn.dataTable.render.number(',', '.', 2, 'Q')
        },
        {
            title: 'Porcentaje',
            data: null,
            render: function (data, type, row) {
                if (row.cartera_kpi > 0) {
                    const porcentaje = (row.saldo_actual / row.cartera_kpi) * 100;
                    return porcentaje.toFixed(2) + '%';
                } else {
                    return '0';
                }
            },
            exportOptions: {
                render: function (data, type, row) {
                    if (row.cartera_kpi > 0) {
                        const porcentaje = (row.saldo_actual / row.cartera_kpi) * 100;
                        return porcentaje.toFixed(2) + '%';
                    } else {
                        return '0';
                    }
                }
            }
        },
        {
            title: 'Total Pagado',
            data: 'total_pagado',
            render: $.fn.dataTable.render.number(',', '.', 2, 'Q'),
            visible: false
        },
        {
            title: 'Saldo Actual',
            data: 'saldo_actual',
            render: $.fn.dataTable.render.number(',', '.', 2, 'Q'),
            visible: false
        },
        { title: 'Clientes Proy.', data: 'clientes_kpi' },
        { title: 'Clientes', data: 'clientes_real' },
        {
            title: 'Porcentaje Clientes',
            data: null,
            render: function (data, type, row) {
                if (row.clientes_kpi > 0) {
                    const porcentaje = (row.clientes_real / row.clientes_kpi) * 100;
                    return porcentaje.toFixed(2) + '%';
                } else {
                    return '0';
                }
            },
            exportOptions: {
                orthogonal: 'export'
            }
        },
        { title: 'Grupos Proy.', data: 'grupos_kpi' },
        { title: 'Grupos', data: 'grupos_real' },
        {
            title: 'Porcentaje Grupos',
            data: null,
            render: function (data, type, row) {
                if (row.grupos_kpi > 0) {
                    const porcentaje = (row.grupos_real / row.grupos_kpi) * 100;
                    return porcentaje.toFixed(2) + '%';
                } else {
                    return '0';
                }
            },
            exportOptions: {
                orthogonal: 'export'
            }
        }
    ];

    // Encabezado
    const thead = $('#tabla-datos thead');
    thead.empty(); // Limpiar contenido previo
    let headerRow = '<tr>';
    headers.forEach(header => {
        headerRow += `<th>${header.title}</th>`;
    });
    headerRow += '</tr>';
    thead.append(headerRow);

    // Destruir cualquier DataTable
    if ($.fn.DataTable.isDataTable('#tabla-datos')) {
        $('#tabla-datos').DataTable().destroy();
    }

    // Inicializar DataTable
    $('#tabla-datos').DataTable({
        data: response.data,
        columns: headers,
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: 'Excel',
                filename: 'Cumplimiento_metas',
                title: 'Reporte Detallado',
                customize: function (xlsx) {
                    const sheet = xlsx.xl.worksheets['sheet1.xml'];
                    $('row c[r]', sheet).attr('s', '42'); // Predefined cell style
                    $('row:first c', sheet).each(function () {
                        $(this).attr('s', '55'); // Header row style
                    });

                    // Update the percentage column with the correct formatting
                    $('row c[r]:nth-child(7)', sheet).each(function (index) {
                        const porcentaje = parseFloat($('td:nth-child(7)', 'table > tbody > tr').eq(index).text());
                        $(this).text(porcentaje.toFixed(2) + '%');
                    });
                },
                exportOptions: {
                    columns: ':visible',
                    orthogonal: 'export'
                },
                init: function (dt, node, config) {
                    $(node).css({
                        'background-color': '#28a745',
                        'border-color': '#28a745',
                        'color': 'white',
                        'padding': '8px 16px',
                        'font-size': '14px',
                        'border-radius': '4px',
                        'cursor': 'pointer'
                    });
                }
            }
        ]
    });
}
