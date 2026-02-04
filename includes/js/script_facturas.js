function enviarDatos() {
    let numeroAutorizacion = document.getElementById('noautorizacion').value;
    let serie = document.getElementById('noserie').value;
    let fechaEmision = document.getElementById('fechemision').value;
    let numDte = document.getElementById('nodte').value;

    var selectdte = document.getElementById('tipodte').value; 
    let emisor_nit = document.getElementById('nitcliente').value;
    let emisor_nombre = document.getElementById('nombrecliente').value;
    let emisor_email = document.getElementById('emailcliente').value;
    let emisor_direccion = document.getElementById('direccioncliente').value;

    let receptor_nit = document.getElementById('nitcliente2').value;
    let receptor_nombre = document.getElementById('nombrecliente2').value;
    let receptor_email = document.getElementById('emailcliente2').value;
    let receptor_direccion = document.getElementById('direccioncliente2').value;

    let tabla = document.getElementById('miTabla');
    let filas = tabla.getElementsByTagName('tr');
    let productos = [];

    for (let i = 0; i < filas.length; i++) {
        let celdas = filas[i].getElementsByTagName('td');
        if (celdas.length > 0) {
            let producto = {
                bs: celdas[0].querySelector('select') ? celdas[0].querySelector('select').value : '',
                cantidad: celdas[1].querySelector('input') ? celdas[1].querySelector('input').value : '',
                descripcion: celdas[2].querySelector('input') ? celdas[2].querySelector('input').value : '',
                precioUnitario: celdas[3].querySelector('input') ? celdas[3].querySelector('input').value : '',
                descuentos: celdas[4].querySelector('input') ? celdas[4].querySelector('input').value : '',
                otrosDescuentos: celdas[5].querySelector('input') ? celdas[5].querySelector('input').value : '',
                total: celdas[6].querySelector('input') ? celdas[6].querySelector('input').value : '',
                impuestos: celdas[7].querySelector('input') ? celdas[7].querySelector('input').value : ''
            };
            productos.push(producto);
        }
    }

    let condi = "gestion";

    // Verificar los datos antes de enviarlos
   /* console.log({
        selectdte,
        emisor_nit,
        emisor_nombre,
        emisor_email,
        emisor_direccion,
        receptor_nit,
        receptor_nombre,
        receptor_email,
        receptor_direccion,
        productos,
        condi
    });
*/
    $.ajax({
        url: '../../views/comprasventas/functions/functions.php',
        method: 'POST',
        data: {
            selectdte,
            emisor_nit,
            emisor_nombre,
            emisor_email,
            emisor_direccion,
            receptor_nit,
            receptor_nombre,
            receptor_email,
            receptor_direccion,
            productos,
            condi
        },
        success: function(response) {
            loaderefect(0);
            //console.log(response);
            var data = JSON.parse(response);
            if (data.status === 'success') {
                if(data.processed_data.estado === true){
                    document.getElementById("noautorizacion").value = data.processed_data.no_autorizacion;
                    document.getElementById("fechemision").value = data.processed_data.fechahora_emision;
                    document.getElementById("noserie").value = data.processed_data.serie;
                    document.getElementById("nodte").value = data.processed_data.codigo_autorizacion;
                    Swal.fire({
                        icon: 'success',
                        title: 'FACTURA',
                        text: 'Factura Emitida'
                    });
                }else{
                    Swal.fire({
                        icon: 'error',
                        title: 'Factura NO EMITIDA',
                        text: data.processed_data.problema
                    });
                    document.getElementById("ImpFel").style.display = "none";
                }
            }else if(data.status === 'alert'){
                if(data.processed_data.estado === true){
                    document.getElementById("noautorizacion").value = data.processed_data.no_autorizacion;
                    document.getElementById("fechemision").value = data.processed_data.fechahora_emision;
                    document.getElementById("noserie").value = data.processed_data.serie;
                    document.getElementById("nodte").value = data.processed_data.codigo_autorizacion;
                    Swal.fire({
                        icon: 'alert',
                        title: 'Factura Duplicada',
                        text: data.processed_data.problema
                    });
                }else{
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Factura NO EMITIDA',
                        text: data.processed_data.problema
                    });
                    document.getElementById("ImpFel").style.display = "none";
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Factura NO EMITIDA',
                    text: data.processed_data.problema
                });
                document.getElementById("ImpFel").style.display = "none";
            }
           
        },
        error: function(xhr, status, error) {
            console.log('Error en la solicitud AJAX:', error);
        }
    });
        /*
        success: function(data) {
            Swal.fire({
                icon: 'success',
                title: 'FACTURA',
                text: 'Factura Emitida'
            });
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'ERROR',
                text: 'Error al emitir factura'
            });
        }
    });*/
}
