/**
 * Muestra un mensaje en un modal con su contenido completo
 * @param {string} titulo - Título del mensaje
 * @param {string} contenido - Contenido completo del mensaje
 * @param {number} idMensaje - ID del mensaje (opcional)
 */
// function verMensajeCompleto(titulo, contenido, idMensaje = null) {
//     // Si hay una función específica para mostrar el modal, usarla
//     if (typeof mostrarAyudaGarantias === 'function') {
//         mostrarAyudaGarantias(titulo, contenido);
//     } else {
//         // Crear un modal genérico si no existe una función específica
//         const modalId = 'modalMensajeCompleto';

//         // Eliminar modal previo si existe
//         $('#' + modalId).remove();

//         // Crear el modal
//         const modal = $(`
//             <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
//                 <div class="modal-dialog modal-lg">
//                     <div class="modal-content">
//                         <div class="modal-header bg-primary text-white">
//                             <h5 class="modal-title">${titulo}</h5>
//                             <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
//                         </div>
//                         <div class="modal-body">
//                             ${contenido}
//                         </div>
//                         <div class="modal-footer">
//                             <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
//                         </div>
//                     </div>
//                 </div>
//             </div>
//         `);

//         // Agregar al DOM
//         $('body').append(modal);

//         // Mostrar el modal
//         const modalInstance = new bootstrap.Modal(document.getElementById(modalId));
//         modalInstance.show();

//         // Marcar como visto cuando se cierra
//         // if (idMensaje) {
//         //     $('#' + modalId).on('hidden.bs.modal', function() {
//         //         marcarMensajeComoVisto(idMensaje);
//         //     });
//         // }
//     }
// }

// Inicializar manejadores de eventos cuando el documento esté listo
// $(document).ready(function() {
//     // Cuando se cierra manualmente un mensaje, marcarlo como visto
//     // $(document).on('close.bs.alert', '.mensaje-sistema', function() {
//     //     const idMensaje = $(this).data('mensaje-id');
//     //     if (idMensaje) {
//     //         marcarMensajeComoVisto(idMensaje);
//     //     }
//     // });

//     // Manejar botones "Ver más" que no usan mostrarAyudaGarantias
//     $(document).on('click', '.btn-ver-mensaje-completo', function() {
//         const idMensaje = $(this).data('mensaje-id');
//         const titulo = $(this).data('titulo');
//         const contenido = $(this).data('contenido');

//         verMensajeCompleto(titulo, contenido, idMensaje);
//     });
// });

// Función para mostrar la guía de cambios
function mostrarGuiaActualizaciones(id = 0, contenido, titulo = null) {
  // Título predeterminado si no se proporciona
  const modalTitulo = titulo || "Guía de actualizaciones";

  // Contenido predeterminado si no se proporciona
  let modalContenido = "";

  // Si se proporciona contenido específico, usarlo directamente
  modalContenido = `
                    <div class="card mb-3">
                        <div class="card-body">
                            ${contenido}
                        </div>
                    </div>
                    `;

  // Crear contenido HTML para el modal
  let contenidoModal = `
                    <div class="modal fade" id="modalAyudaActualizaciones${id}" tabindex="-1" aria-labelledby="modalAyudaTitulo" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title" id="modalAyudaTitulo">${modalTitulo}</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    ${modalContenido}
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

  // Agregar el modal al DOM y mostrarlo
  if (!$(`#modalAyudaActualizaciones${id}`).length) {
    $("body").append(contenidoModal);
  }

  // Mostrar el modal
  let modalAyuda = new bootstrap.Modal(
    document.getElementById(`modalAyudaActualizaciones${id}`)
  );
  modalAyuda.show();
}

function pointToBack(accion, id, otros, callback = null) {
  $.ajax({
    url: BASE_URL_FOR_JS + "/src/services/mensajes_service.php",
    method: "POST",
    data: { accion, id, otros },
    beforeSend: function () {
      loaderefect(1);
    },
    success: function (data) {
      //   console.log("Datos recibidos:", data);
      //   const data2 = JSON.parse(data);
      if (data["success"]) {
        if (typeof callback === "function") {
          callback(data);
        }
      } else {
        // Swal.fire({ icon: "error", title: "¡ERROR!", text: data["mensaje"] });
      }
    },
    complete: function (data) {
      //   console.log("Operación completada:", data);
      loaderefect(0);
    },
  });
}

function loadMessagesAtDivContainer(success = false) {
  const todosLosDivs = document.querySelectorAll(
    "#containerMensajesUpdatedCondi"
  );

  todosLosDivs.forEach((div) => {
    const condi = div.getAttribute("data-condi");
    pointToBack("get_mensajes", 0, [condi], function (data) {
      $(`#containerMensajesUpdatedCondi[data-condi="${condi}"]`).html(
        data.html
      );

      if (success && data.mensajesId && data.mensajesId.length > 0) {
        pointToBack("marcar_todos_vistos", 0, [data.mensajesId]);
      }
    });
  });
}
