//#region FUNCIONES PARA FORMATOS DE LIBRETA
// MAIN
function impresion_libreta_main(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(10, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";

            doc.text(30, pos, transaccion);
            doc.text(64, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";
            doc.text(30, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(88, pos, "" + pad(currency(monto)));
        }
        doc.text(113, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_coopedjd(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    // console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    var x = -6;
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(x + 25, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(x + 45, pos, transaccion);
            doc.text(x + 64, pos, "");
            doc.text(x + 105, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(x + 45, pos, transaccion);
            doc.text(x + 75, pos, "" + pad(currency(monto)));
            doc.text(x + 88, pos, "");
        }
        doc.text(x + 130, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_certificado_coopedjd(datos) {
    // console.log(datos);
    alert("Impresion de certificado");
    var opciones = {
        orientation: "p",
        unit: "mm",
        format: [240, 300],
    };
    var doc = new jsPDF(opciones);
    doc.setFont("courier", "normal");
    doc.setFontSize(8);

    var oficina = datos[0][17];
    var recibo = datos[0][18];
    // doc.setFontStyle('bold');
    doc.text(7, 55, "CERTIFICADO No.  " + "DJD - Y" + datos[0][0]);
    doc.text(37, 55, " "); //NO.
    // doc.text( 65, 34, 'C.I. ' + datos[0][16]); //C.I.
    doc.setFontSize(14);
    doc.text(80, 62, "Q : " + datos[0][7]); // Monto
    doc.setFontSize(8);
    doc.text(7, 67, "ASOCIADO/A:");
    doc.text(35, 67, " " + datos[0][1]); //Nombre
    doc.text(7, 70.5, "IDENTIFICACIÓN DPI:");
    doc.text(48, 70.5, " " + datos[0][4]); //Dpi
    doc.text(7, 73.5, "DIRECCIÓN:");
    doc.text(48, 73.5, " " + datos[0][3]); //Direccion
    doc.text(7, 77, "TELEFONO:");
    doc.text(48, 77, "  " + datos[0][5]); //Tel
    doc.text(7, 80.5, "CUENTA DE AHORRO A PLAZO FIJO No.");
    doc.text(80, 80.5, " " + datos[0][2]); //CUENTA

    //conversion Cantidad en letras
    let montoNumerico = parseFloat(datos[0][7]);
    let ptdecimal;

    //formatea decimales del monto y los muestra en  + total en letras
    if (!isNaN(montoNumerico)) {
        let monto = montoNumerico.toFixed(2);

        let partes = monto.split('.');
        let parteEntera = partes[0];
        ptdecimal = partes[1];

        let montoFormateado = ptdecimal + '/100';

        //  doc.text(140, 120, montoFormateado);
    } else {
        // console.error(' monto [][7]no es un número válido.');
    }
    doc.text(7, 87, "SUMA DE :");
    doc.text(38, 87, " " + datos[0][6] + '  ' + ptdecimal + '/100'); // La cantidad en letras

    var tasaFormateada = parseFloat(datos[0][11]).toFixed(2) + "%";
    doc.text(7, 94, "TASA DE INTERES:");
    doc.text(38, 94, " " + tasaFormateada); // tasa

    // Verificar el valor en datos[0][25]
    var texto;
    if (datos[0][25] === "M") {
        texto = "MENSUAL";
    } else if (datos[0][25] === "V") {
        texto = "VENCIMIENTO";
    } else {
        texto = " "; // Dejar vacío si no es "M" ni "V"
    }
    // Mostrar el texto en el documento
    doc.text(7, 100.5, "CAPITALIZACIÓN DE INTERESES:");
    doc.text(60, 100.5, " " + texto); // Mensual o Vencimiento

    doc.text(7, 107.5, "FECHA DE INICIO:");
    doc.text(50, 107.5, " " + datos[0][9]); //Fec.deposito
    doc.text(7, 114, "FECHA DE VENCIMIENTO:");
    doc.text(50, 114, " " + datos[0][10]); //Fec. vencimiento

    doc.text(7, 121, "PLAZO:");
    doc.text(20, 121, " " + datos[0][8] + "   " + "Días"); //plazos

    // Restablece el estilo de fuente a normal
    // doc.setFontStyle("normal");

    doc.text(7, 127, "INTERESES:");
    doc.text(50, 127, " " + parseFloat(datos[0][12]).toFixed(2)); // Interes calcu
    doc.text(7, 131, "(-) I.S.R.C.");
    doc.text(50, 131, " " + parseFloat(datos[0][13]).toFixed(2)); // ipf
    doc.text(7, 134.5, "TOTAL A PAGAR:");
    doc.text(50, 134.5, " " + parseFloat(datos[0][14]).toFixed(2)); //totalrecibir

    // BENEFICIARIOS
    doc.text(7, 140.5, "BENEFICIARIO:");
    doc.text(7, 144.5, "BENEFICIARIO:");

    var i = 1;
    var ini = 125;

    while (i < datos[1].length) {
        var nombres = [];
        var dpis = [];
        var fechasNacimiento = [];
        var direcciones = [];
        var parentescos = [];

        // Recorre datos y agrupa
        for (var j = i; j < i + 5 && j < datos[1].length; j++) {
            nombres.push(datos[1][j]["nombre"]); // Nombres
            parentescos.push(" " + datos[1][j]["codparent"]); // Parentesco
            // dpis.push(" " + datos[1][j]["dpi"]); // DPI
            // direcciones.push(" " + datos[1][j]["direccion"]); // Dirección

        }
        // fechasNacimiento.push(" " + datos[1][i]["fecnac"]); // Nacimiento

        // Mostrar nombres con posiciones individuales
        var baseXNombre = 35;
        var baseXParentesco = 100;
        var baseY = 140.5;
        var lineHeight = 4;

        nombres.forEach((nombre, index) => {
            var posY = baseY + (index * lineHeight);
            doc.text(baseXNombre, posY, nombre); // Nombre
            doc.text(baseXParentesco, posY, parentescos[index]); // Parentesco
        });

        // doc.text(45, 143, " " + dpis.join(", ")); // DPI
        // doc.text(160, 143, " " + fechasNacimiento.join(" ")); // Fechas de Nacimiento
        // doc.text(50, 152, " " + direcciones.join(", ")); // Direcciones


        ini += 30;
        i += 5;
    }
    // Recibo
    // doc.text(150, 159, "Recibo: " + recibo);
    var lugaroficina = (oficina == "002") ? "Playa Grande Ixcán, Ixcán, Quiché, " : ((oficina == "003") ? "Santa Cruz Barillas, Huehuetenango, " : "Aldea Mayaland, Ixcán, Quiché, ");
    doc.text(7, 154, lugaroficina + datos[0][15]);  //lugar y fecha

    doc.autoPrint();
    window.open(doc.output("bloburl"));
}

function impresion_recibo_dep_ret_coopedjd(datos) {
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFont("courier", "normal");
    var i = 1;
    var ini = 30;
    var margenizquierdo = 40;
    while (i < 2) {
        doc.setFontSize(9);
        doc.text(margenizquierdo, ini, 'Cuenta de ahorro No. ' + datos[2]);
        doc.text(140, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Docto: ' + datos[5]);
        doc.text(140, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);

        doc.setFontSize(8);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_cooprode(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    var pos = 67;
    while (i < (datos[1].length)) {
        num = parseInt(datos[1][i]['numlinea']);
        //pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(16, pos, "" + fecha);
        doc.text(42, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            doc.text(68, pos, "");
            doc.text(68, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);

            doc.text(94, pos, "" + pad(currency(monto)));
            doc.text(94, pos, "");
        }

        doc.text(120, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        pos += 4;
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }

    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}



function impresion_libreta_primavera(datos, resta, ini, posini, posfin, saldoo, numi, file) {

    // console.log("impresion libreta main")
    // console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = -5;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(23, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(41, pos, transaccion);
            doc.text(103, pos, "" + pad(currency(monto)));
            doc.text(79, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(41, pos, transaccion);
            doc.text(103, pos, "");
            doc.text(79, pos, "" + pad(currency(monto)));
        }
        doc.text(130, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }

}


//#endregion

//#region FUNCIONES PARA FORMATO DE RETIROS Y DEPOSITOS
// MAIN
function impresion_recibo_dep_ret_credivasquez(datos) {
    // console.log(datos);
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 50;
    var margenizquierdo = 20;

    while (i < 2) {
        doc.setFontSize(12);
        doc.text(margenizquierdo, ini, 'Cuenta de aportación No. ' + datos[2]);
        doc.text(115, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Monto: Q.' + datos[3]);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, datos[16] > 0 ? 'Cuota de Ingreso: Q.' + datos[16] : '');
        ini = ini + 7;
        var CuotaIngreso = parseFloat(datos[16]);
        var montototal = parseFloat(datos[3]) + CuotaIngreso;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(150, ini, 'Monto Total: Q.' + datos[18]);

        ini = ini + 7;
        doc.text(60, ini, 'Operación: ' + datos[6]);

        doc.setFontSize(10);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_main(datos) {
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 30;
    var margenizquierdo = 20;

    while (i < 2) {
        doc.setFontSize(12);
        doc.text(margenizquierdo, ini, 'Cuenta de aportación No. ' + datos[2]);
        doc.text(115, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(150, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 7;
        doc.text(60, ini, 'Operación: ' + datos[6]);

        doc.setFontSize(10);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

// PRIMAVERA -> FORMAS
function impresion_recibo_dep_ret_primavera(datos) {
    alert("Impresion de recibo *2");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var fuente = 'Courier';
    doc.setFont(fuente);

    var i = 1;
    var ini = 40;
    var margenizquierdo = 10;
    while (i < 2) {
        doc.setFontSize(13);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo, ini, 'Cuenta de aportación: ' + datos[2]);
        doc.text(115, ini, 'Fecha doc: ' + datos[4]);
        //doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
        doc.text(150, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 7;
        doc.text(60, ini, 'Operación: ' + datos[6]);

        doc.setFontSize(11);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_copeplus(datos) {
    // console.log(datos);
    //     return;

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 50;
    var ini2 = 40;
    var margenizquierdo = 25;
    while (i < 2) {

        if (datos[17] == "Aportacion adicional 3% (TOTONICAPAN)" || datos[17] == "Aportación infantil 0% (TOTONICAPAN)" || datos[17] == "Aportacion 0% (TOTONICAPAN)") {
            doc.setFontSize(10);
            doc.text(margenizquierdo, ini2, 'Cuenta de ahorro No. ' + datos[2]);
            doc.text(110, ini2, 'Fecha doc: ' + datos[4]);
            doc.text(180, ini2, '' + datos[5]);

            ini2 = ini2 + 6;
            doc.text(margenizquierdo, ini2, 'Cliente: ' + datos[7]);

            ini2 = ini2 + 6;
            doc.text(margenizquierdo, ini2, 'Operacion y No. Docto: ' + datos[5]);
            doc.text(140, ini2, 'Monto: Q ', { align: 'right' });
            doc.text(180, ini2, datos[3].toString(), { align: 'right' });

            if (datos[16] > 0) {
                ini2 = ini2 + 5;
                doc.text(140, ini2, 'Cuota de ingreso: Q ', { align: 'right' });
                doc.text(180, ini2, datos[16].toString(), { align: 'right' });
            }

            doc.text(140, ini2 + 6, 'Total: Q ', { align: 'right' });
            doc.text(180, ini2 + 6, datos[18].toString(), { align: 'right' });

            ini2 = ini2 + 6;
            doc.text(60, ini2, datos[6]);

            doc.setFontSize(8);
            ini2 = ini2 + 6;
            doc.text(margenizquierdo, ini2, 'Operador: ' + datos[8] + ' ' + datos[9]);
            ini2 = ini2 + 4;
            doc.text(margenizquierdo, ini2, 'Fecha operación: ' + datos[10]);
        } else {
            doc.setFontSize(12);
            doc.text(margenizquierdo, ini, 'Cuenta de ahorro No. ' + datos[2]);
            doc.text(110, ini, 'Fecha doc: ' + datos[4]);
            doc.text(180, ini, '' + datos[5]);

            ini = ini + 7;
            doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

            ini = ini + 7;
            doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
            doc.text(140, ini, 'Monto: Q ', { align: 'right' });
            doc.text(180, ini, datos[3].toString(), { align: 'right' });

            if (datos[16] > 0) {
                ini = ini + 5;
                doc.text(140, ini, 'Cuota de ingreso: Q ', { align: 'right' });
                doc.text(180, ini, datos[16].toString(), { align: 'right' });
            }

            doc.text(140, ini + 6, 'Total: Q ', { align: 'right' });
            doc.text(180, ini + 6, datos[18].toString(), { align: 'right' });

            ini = ini + 7;
            doc.text(60, ini, datos[6]);

            doc.setFontSize(10);
            ini = ini + 7;
            doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
            ini = ini + 5;
            doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);
        }



        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_coditoto(datos) {
    alert("Impresion de recibo");
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var fuente = 'Courier';
    doc.setFont(fuente);

    var i = 1;
    var ini = 37;
    var margenizquierdo = 80;
    while (i <= 1) {
        doc.setFontSize(11);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo + 100, ini - 32, ' ' + datos[5]);
        doc.setFontSize(13);
        doc.text(margenizquierdo + 4, ini - 15, ' ' + datos[17]);
        doc.text(margenizquierdo, ini - 10, 'Número de cuenta de Aportacion ' + datos[2]);
        doc.text(margenizquierdo, ini - 5, 'Fecha del documento: ' + datos[4]);
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo, ini + 5, 'Operación y No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini + 10, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo, ini + 15, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : " "));
        doc.text(margenizquierdo, ini + 20, 'Operación: ' + datos[6]);

        doc.setFontSize(11);
        doc.text(margenizquierdo, ini + 25, 'Operador: ' + datos[8] + ' ' + datos[9]);
        doc.text(margenizquierdo, ini + 30, 'Fecha de la operación: ' + datos[10]);
        ini += 53;

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_mass(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var fuente = 'Courier';
    doc.setFont(fuente);

    var i = 1;
    var ini = 37;
    var margenizquierdo = 80;
    while (i <= 1) {
        doc.setFontSize(11);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo + 85, ini - 28, ' ' + datos[5]);
        doc.setFontSize(13);
        doc.text(margenizquierdo, ini - 10, 'Número de cuenta de Aportacion ' + datos[2]);
        doc.text(margenizquierdo, ini - 5, 'Fecha del documento: ' + datos[4]);
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo, ini + 5, 'Operación y No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini + 10, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo, ini + 15, 'Operación: ' + datos[6]);

        doc.setFontSize(11);
        doc.text(margenizquierdo, ini + 20, 'Operador: ' + datos[8] + ' ' + datos[9]);
        doc.text(margenizquierdo, ini + 25, 'Fecha de la operación: ' + datos[10]);
        ini += 53;

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

//mayaland
function impresion_recibo_dep_ret_mayaland(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var fuente = 'Courier';
    doc.setFont(fuente);

    var i = 1;
    var ini = 25;
    var margenizquierdo = 90;
    while (i <= 1) {
        doc.setFontSize(11);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo + 85, ini - 28, ' ');
        doc.setFontSize(13);
        // doc.text(margenizquierdo, ini - 10, 'Número de cuenta de Aportacion ' + datos[2]);
        doc.text(margenizquierdo, ini - 5, 'Fecha del documento: ' + datos[4]);
        doc.text(margenizquierdo, ini + 2, '' + datos[7]);
        doc.text(margenizquierdo, ini + 35, 'Operación y No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini + 7, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo, ini + 40, 'Operación: ' + datos[6]);
        doc.setFontSize(11);
        // doc.text(margenizquierdo, ini + 20, 'Operador: ' + datos[8] + ' ' + datos[9]);
        // doc.text(margenizquierdo, ini + 25, 'Fecha de la operación: ' + datos[10]);
        ini += 53;
        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_coopetikal(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var fuente = 'Courier';
    doc.setFont(fuente);

    var i = 1;
    var ini = 35;
    var margenizquierdo = 80;
    while (i <= 1) {
        doc.setFontSize(11);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo, ini - 16, 'Recibo No. ' + datos[5]);
        doc.setFontSize(13);
        doc.text(margenizquierdo, ini - 10, 'Número de cuenta de Aportacion ' + datos[2]);
        doc.text(margenizquierdo, ini - 5, 'Fecha del documento: ' + datos[4]);
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo, ini + 5, 'Operación y No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini + 10, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo, ini + 15, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : ""));
        doc.text(margenizquierdo, ini + 20, 'Operación: ' + datos[6]);

        doc.setFontSize(11);
        doc.text(margenizquierdo, ini + 25, 'Operador: ' + datos[8] + ' ' + datos[9]);
        doc.text(margenizquierdo, ini + 30, 'Fecha de la operación: ' + datos[10]);
        ini += 53;

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

// CIACREHO ->FORMAS
function impresion_libreta_ciacreho(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);

    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(10, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";

            doc.text(30, pos, transaccion);
            doc.text(64, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(30, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(88, pos, "" + pad(currency(monto)));
        }
        doc.text(113, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

//libreta de copibelen
function impresion_libreta_copefuente(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);

    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(10, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";

            doc.text(30, pos, transaccion);
            doc.text(64, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(30, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(88, pos, "" + pad(currency(monto)));
        }
        doc.text(113, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}


function impresion_libreta_coditoto(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    // console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(10, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(30, pos, transaccion);
            doc.text(64, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(30, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(88, pos, "" + pad(currency(monto)));
        }
        doc.text(113, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_copeixil(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // FRONT-INI -- 90
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        //console.log(datos[1][i]);
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (4 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        // doc.text(4, pos, String(datos[1][i]['correlativo']));
        doc.text(10, pos, fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        const cnumdoc = String(datos[1][i]['cnumdoc']);
        doc.text(45, pos, cnumdoc);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "RET/DEB";
            doc.text(30, pos, transaccion);
            doc.text(74, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "DEP/INT";

            doc.text(30, pos, transaccion);
            doc.text(102, pos, "" + pad(currency(monto)));
        }
        doc.text(126, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}
function impresion_libreta_copeadif(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    // console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(8);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(8);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (5 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(17, pos, "" + fecha);//fecha de documento
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "RETIRO";
            let cnumdoc = datos[1][i]['cnumdoc'];
            if (cnumdoc.length > 10) {
                // Truncar
                cnumdoc = cnumdoc.substring(0, 10) + "...";
            }
            doc.text(35, pos, "" + cnumdoc);//numero de documento
            doc.text(53, pos, transaccion);//tipo de transaccion DEPOSITO O RETIRO
            doc.text(59, pos, "");
            doc.text(80, pos, "" + pad(currency(monto))); //MONTO
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            let cnumdoc = datos[1][i]['cnumdoc'];
            if (cnumdoc.length > 10) {
                // Truncar
                cnumdoc = cnumdoc.substring(0, 8) + "...";
            }
            doc.text(35, pos, "" + cnumdoc);
            transaccion = "DEPOSITO";
            doc.text(73, pos, "");
            doc.text(53, pos, transaccion);
            doc.text(105, pos, "" + pad(currency(monto)));
        }
        doc.text(125, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_cooprode(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(13);

    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    var pos = 67;
    while (i < (datos[1].length)) {
        num = parseInt(datos[1][i]['numlinea']);
        //pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(16, pos, "" + fecha);
        doc.text(42, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            doc.text(68, pos, "");
            doc.text(68, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);

            doc.text(94, pos, "" + pad(currency(monto)));
            doc.text(94, pos, "");
        }

        doc.text(120, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        pos += 4;
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }

    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_adif(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = -5;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(8);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);

        // Column 1: No. (line number)
        doc.text(10, pos, "" + num);

        // Column 2: Fecha
        fecha = conviertefecha(datos[1][i]['dfecope']);
        doc.text(18, pos, "" + fecha);

        // Column 3: DocNo.
        doc.text(36, pos, "" + datos[1][i]['cnumdoc']);

        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];

        // Column 4: Concepto
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(50, pos, transaccion);
            // Column 5: Retiro
            doc.text(70, pos, "" + pad(currency(monto)));
            // Column 6: Depositos/Interes
            doc.text(90, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Dep/Int";
            doc.text(50, pos, transaccion);
            // Column 5: Retiro
            doc.text(70, pos, "");
            // Column 6: Depositos/Interes
            doc.text(90, pos, "" + pad(currency(monto)));
        }

        // Column 7: Saldo
        doc.text(115, pos, "" + pad(currency(datos[1][i]['saldo'])));

        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }

}

function impresion_libreta_altascumbres(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    // console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = -5;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    var posvert;
    posvert = ini + 9;

    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        //console.log(datos[1][i]);
        //return;
        //posvert += 5;

        doc.setFontSize(8);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(posvert);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        doc.text(5, pos, "" + fecha);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";
            doc.text(30, pos, transaccion);
            doc.text(55, pos, "" + pad(currency(monto)));
            doc.text(86, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";

            doc.text(30, pos, transaccion);
            doc.text(72, pos, "");
            doc.text(75, pos, "" + pad(currency(monto)));
        }
        doc.text(100, pos, "" + pad(currency(datos[1][i]['saldo'])));


        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            posvert += 5;
            break;
        }
        i++;

        // aqui se termina y aunemnta el bucle

    }
    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_coopeadg(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    // console.log(datos)
    //return;
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        doc.text(8, pos, "" + fecha);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "RETIRO";
            let cnumdoc = datos[1][i]['cnumdoc'];
            if (cnumdoc.length > 10) {
                // Truncar
                cnumdoc = cnumdoc.substring(0, 10) + "...";
            }

            doc.text(30, pos, "" + cnumdoc);
            doc.text(51, pos, transaccion);
            doc.text(69, pos, "");
            doc.text(73, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            let cnumdoc = datos[1][i]['cnumdoc'];
            if (cnumdoc.length > 10) {
                // Truncar
                cnumdoc = cnumdoc.substring(0, 10) + "...";
            }
            doc.text(30, pos, "" + cnumdoc);
            transaccion = "DEPOSITO";
            doc.text(38, pos, "");
            doc.text(51, pos, transaccion);
            doc.text(94, pos, "" + pad(currency(monto)));
        }
        doc.text(120, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

// funcion = ahorros
function impresion_libreta_copeplus(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    // console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        fecha = conviertefecha(datos[1][i]['dfecope']);

        doc.text(13, pos, "" + datos[1][i]['correlativo']);
        doc.text(20, pos, "" + fecha);

        let numdoc = datos[1][i]['cnumdoc'].length > 6 ? datos[1][i]['cnumdoc'].substring(0, 6) + "..." : datos[1][i]['cnumdoc'];
        doc.text(38, pos, "" + numdoc);

        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        interes = datos[1][i]['crazon'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Ret./Deb.";
            doc.text(56, pos, transaccion);
            doc.text(92, pos, "" + pad(currency(monto)));
            doc.text(105, pos, "");//este no se usa

        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Dep./Int.";
            doc.text(56, pos, transaccion);
            doc.text(112, pos, "" + pad(currency(monto)));
            doc.text(105, pos, "");// este no se usa
        }
        doc.text(137, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}
//end libretas

// CIACREHO
function impresion_recibo_dep_ret_ciacreho(datos) {
    alert("Impresion de recibo *3");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var fuente = 'Courier';
    doc.setFont(fuente);

    var i = 1;
    var ini = 40;
    var margenizquierdo = 10;
    while (i < 2) {
        doc.setFontSize(13);
        doc.setFontStyle('bold');
        doc.text(margenizquierdo, ini, 'Cuenta de aportación: ' + datos[2]);
        doc.text(115, ini, 'Fecha doc: ' + datos[4]);
        //doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
        doc.text(150, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 7;
        doc.text(60, ini, 'Operación: ' + datos[6]);

        doc.setFontSize(11);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
// COOPRODE
function impresion_recibo_dep_ret_cooprode(datos) {
    alert("Impresion de recibo *4");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var i = 1;
    var ini = 43;
    while (i < 2) {
        doc.text(66, ini, datos[14]);
        doc.text(80, ini, datos[13]);
        doc.text(92, ini, datos[12]);

        ini = ini + 8;
        doc.text(23, ini, datos[7]);

        ini = ini + 25;
        doc.text(12, ini, datos[6]);
        doc.text(167, ini, datos[3]);

        ini = ini + 35;
        doc.text(167, ini, datos[3]);

        ini = ini + 6;
        doc.text(36, ini, datos[11]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

// CORPOCREDIT aportaciones
function impresion_recibo_dep_ret_corpocredit(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300],

    };

    //  console.log(datos);
    //  return;
    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();


    var i = 1;
    var ini = 50;
    var margenizquierdo = 20;
    while (i < 2) {
        doc.setFontSize(12);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }
        var Tot_efectivo = 0;
        var apr = 0;
        doc.text(margenizquierdo, ini - 6, "Fecha: " + datos[10]);
        doc.text(margenizquierdo, ini, 'CORPOCREDIT R.L. /PRODUCTO: ' + datos[17]);
        ini += 6;
        doc.text(margenizquierdo, ini, 'No. de Cuenta: ' + datos[2]);
        doc.text(margenizquierdo, ini + 6, 'No. de Boleta de Transaccion: ' + datos[5]);
        ini += 6;
        var apr = parseFloat(datos[3]);
        doc.text(margenizquierdo, ini + 6, 'Letras: ' + datos[19]);
        doc.text(margenizquierdo + 107, ini + 12, "Aportacion:                Q." + datos[3]);
        doc.text(margenizquierdo + 101, ini + 18, ((datos[16] > 0) ? "     Cuota de ingreso:      Q. " + parseFloat(datos[16]).toFixed(2) : ""));
        var total_format = parseFloat(datos[18]).toFixed(2);
        doc.text(margenizquierdo + 107, ini + 24, "Efectivo:                     Q." + total_format);
        doc.text(margenizquierdo + 20, ini + 30, 'Asociado / A:' + datos[7]);
        doc.text(margenizquierdo + 140, ini + 30, "C.I. " + datos[21]);
        doc.text(margenizquierdo + 22, ini + 36, 'Operación: ' + ((datos[6] === "D") ? "DEPOSITO A CUENTA " : ((datos[6] === "R") ? "RETIRO A CUENTA " : datos[6])));
        doc.text(margenizquierdo + 50, ini + 42, 'Usuario: ' + datos[20]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

//copibelen
function impresion_recibo_dep_ret_copibelen(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300],

    };

    //  console.log(datos);
    //  return;
    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();
    var i = 1;
    var ini = 30;
    var margenizquierdo = 20;
    while (i < 2) {
        doc.setFontSize(10);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }
        var espacios = '                      ';
        const datoview0 = datos[16] ? parseFloat(datos[16]).toFixed(2) : '          -';
        const datoview = datos[6].charAt(0) === 'D' ? datos[3] : '';
        const datoview2 = datos[6].charAt(0) === 'R' ? datos[3] : '';
        const datoview3 = datos[6].charAt(0) === 'D' ? ' ' : '-';
        const datoview4 = datos[6].charAt(0) === 'R' ? ' ' : '-';


        doc.text(margenizquierdo + 20, ini + 5, 'NOMBRE DEL ASOCIADO      ' + datos[7] + '    ' + datos[4]);
        doc.text(margenizquierdo, ini + 10, 'ASOCIADO No. ' + datos[15] + "           " + datos[6]);
        doc.text(margenizquierdo + 60, ini + 15, ' DESCRIPCION');

        //primwera columna
        doc.text(margenizquierdo, ini + 20, 'INSCRIPCION' + espacios + espacios + datoview);
        doc.text(margenizquierdo, ini + 25, 'APORTACION' + espacios + espacios + datoview0);
        doc.text(margenizquierdo, ini + 30, 'RETIRO DE APORTACION' + espacios + datoview2);
        doc.text(margenizquierdo, ini + 35, 'AHORRO CORRIENTE' + '            ' + espacios);
        doc.text(margenizquierdo, ini + 40, 'RETIRO DE AHORRO FIJO' + '     ');
        doc.text(margenizquierdo, ini + 45, 'INGRESOS VARIOS' + '                   ');
        doc.text(margenizquierdo, ini + 55, 'TOTAL EN LETRAS; ' + datos[11]);

        //SEGUNDA COLUMNA  Q and -
        doc.text(margenizquierdo + 8, ini + 20, espacios + espacios + 'Q' + espacios + datoview3 + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 25, espacios + espacios + 'Q' + espacios + espacios + espacios + espacios + ' Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 30, espacios + espacios + 'Q' + espacios + datoview4 + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 35, espacios + espacios + 'Q' + espacios + '-' + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 40, espacios + espacios + 'Q' + espacios + '-' + espacios + espacios + espacios + 'Q' + espacios + '-');

        //TERCERA COLUMNA
        doc.text(margenizquierdo + 90, ini + 20, 'CAPITAL');
        doc.text(margenizquierdo + 90, ini + 25, 'INTERES');
        doc.text(margenizquierdo + 90, ini + 30, 'MORA');
        doc.text(margenizquierdo + 90, ini + 35, 'DESEMBOLSO');
        doc.text(margenizquierdo + 90, ini + 40, 'SALDO ACTUAL');

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_recibo_dep_ret_adg(datos) {
    alert("Impresion de recibo");

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   console.log(datos);
    //   return;

    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();
    var i = 1;
    var ini = 30;
    var cantidad = 0;
    var aportacion = 0;
    var margenizquierdo = 15;
    while (i < 2) {
        doc.setFontSize(10);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }
        if (datos[3]) {
            cantidad = parseFloat(datos[3].replace(',', ''));
        } if (datos[16]) {
            aportacion = parseFloat(datos[16].replace(',', ''));
        }
        // Calcular la suma
        var total = cantidad + aportacion;
        doc.text(margenizquierdo + 150, ini, total.toString());//efectivo
        doc.text(margenizquierdo + 15, ini - 5, 'ADG  ' + fecha);//no de boleta
        doc.text(margenizquierdo + 8, ini + 3, 'ASOCIACION DE DESARROLLO GUATEMALTECO "ADG" '); //recibo de
        doc.text(margenizquierdo + 8, ini + 8, ' 2a. Calle 01-0310 Zona 4 Tecpan Guatemmala, Chimaltenango'); //DIRECCION
        doc.text(margenizquierdo + 15, ini + 15, datos[11] + ' CON ' + decimal + '/100');//en letras
        //ini += 6;
        doc.text(margenizquierdo, ini + 30, 'No. de Boleta de Transaccion: ' + datos[5]);
        doc.text(margenizquierdo, ini + 35, 'Asociado / A:  ' + datos[7]);
        doc.text(margenizquierdo, ini + 40, 'Operación: ' + (datos[6] === "D" ? "DEPOSITO A CUENTA AHORRO" : (datos[6] === "R") ? "RETIRO A CUENTA AHORRO" : datos[6]));
        doc.text(margenizquierdo, ini + 45, 'Cantidad:: ');
        doc.text(margenizquierdo + 168, ini + 45, 'Q.' + datos[3]);
        doc.text(margenizquierdo, ini + 50, 'Aportacion: ');
        doc.text(margenizquierdo + 168, ini + 50, 'Q.' + datos[16]);
        doc.text(margenizquierdo, ini + 60, 'Operador: ' + datos[8] + ' ' + datos[9]);

        doc.text(margenizquierdo + 168, ini + 97, total.toString());
        ini = ini + 40;
        i++;

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_recibo_dep_ret_credysa(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   console.log(datos);
    //   return;
    var doc = new jsPDF(opciones);
    var fechaActual = new Date();

    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();


    var i = 1;
    var ini = 45;
    var margenizquierdo = 20;
    while (i < 2) {
        doc.setFontSize(12);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }

        doc.text(margenizquierdo, ini - 6, 'Fecha ' + datos[4] + '  ' + hora);
        doc.text(margenizquierdo, ini, 'CREDYSA S.A. /PRODUCTO: ' + datos[16]);
        doc.text(margenizquierdo, ini + 6, 'No. de Cuenta: ' + datos[2]);
        doc.text(margenizquierdo, ini + 12, 'No. de Boleta de Transaccion: ' + datos[5]);

        doc.text(margenizquierdo + 130, ini + 15, 'Efectivo: Q. ' + datos[3]);
        doc.text(margenizquierdo, ini + 19, 'Letras: ' + datos[11]);

        // doc.text(margenizquierdo, ini + 28, 'C.I. ' + datos[18]);

        doc.text(margenizquierdo + 33, ini + 30, 'Asociado / A:  ' + datos[7]);
        doc.text(margenizquierdo + 33, ini + 35, 'Operación: ' + (datos[6] === "D" ? "DEPOSITO A CUENTA" : (datos[6] === "R") ? "RETIRO A CUENTA " : datos[6]));
        doc.text(margenizquierdo + 50, ini + 40, 'Usuario: ' + datos[8] + ' ' + datos[9]);

        ini = ini + 40;
        i++;

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

//coopeadg
function impresion_recibo_dep_ret_coopeadg(datos) {
    console.log(datos);
    // return;
    var agencia = '';
    if(datos[26]==1){
        agencia = "Tecpán Guatemala, Chimaltenango. ";

    } if(datos[26]==2){
        agencia = " San José Poaquil ";

    } if(datos[26]==3){
        agencia = "Chupol ";
    }

    var primeraletra = datos[6][0];
    var recibos = primeraletra;
    if (recibos == 'D') {
        alert("Impresion de recibo  ");
        var opciones = {
            orientation: 'p',
            unit: 'mm',
            format: [216, 300]
        };
        var doc = new jsPDF(opciones);

        var i = 1;
        var ini = 52;
        var margenizquierdo = 20;
        let fecha = datos[4];

        while (i < 2) {
            doc.setFontSize(12);
            // doc.text(margenizquierdo + 170, ini - 10, datos[5]);
            doc.text(margenizquierdo + 50, ini - 12, agencia + datos[4]);
            doc.text(margenizquierdo, ini, datos[6]);
            doc.text(margenizquierdo, ini + 5, 'Cliente: ' + datos[7] + ' Cod No. ' + datos[21]);
            doc.text(margenizquierdo, ini + 10, datos[17]);
            doc.text(margenizquierdo, ini + 15, 'Fecha del documento: ' + fecha);
            doc.text(margenizquierdo, ini + 20, 'No. Docto: ' + datos[5]);
            doc.text(margenizquierdo, ini + 25, 'Monto: Q ' + datos[3] + "        " + 'Cuota de Ingreso: Q ' + parseFloat(datos[16]).toFixed(2));
            doc.text(margenizquierdo, ini + 30, 'Total: Q ' + parseFloat(datos[18]).toFixed(2));
            doc.text(margenizquierdo, ini + 35, 'Monto en letras: ' + datos[11]);
            doc.setFontSize(10);
            i++;
        }

    } if (recibos == 'R') {

        alert("Impresion de recibo  ");
        var opciones = {
            orientation: 'p',
            unit: 'mm',
            format: [216, 300]
        };
        var doc = new jsPDF(opciones);
        var i = 1;
        var ini = 25;
        var margenizquierdo = 20;

        doc.setFontSize(12);
        ini = ini + 20;
        doc.text(margenizquierdo, ini, agencia + datos[4]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Pagado a:  ' + datos[7] + ' Cod No. ' + datos[21]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Por Concepto: ' + datos[6]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, datos[17]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Q: ' + datos[3]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'La suma de: ' + datos[11]);
        ini = ini + 6;
        // Información adicional si es banco
        if (datos[24] == "C") {
            doc.text(margenizquierdo, ini, 'Cheque NO.: ' + datos[22]);
        }
        doc.setFontSize(10);
        // Validación de efectivo
        var validae = (datos[24] == "E") ? "X" : " ";
        doc.rect(margenizquierdo * 9 - 1, ini - 4, 5, 5); // Dibuja un cuadro para la "X"
        doc.text(margenizquierdo * 9, ini, validae); // Coloca la "X" dentro del cuadro
        doc.text(margenizquierdo + 134, ini, 'EFECTIVO:');
        ini = ini + 6;
        // Validación de banco
        var validac = (datos[24] == "C") ? "X" : " ";
        doc.rect(margenizquierdo * 9 - 1, ini - 4, 5, 5); // Dibuja un cuadro para la "X"
        doc.text(margenizquierdo * 9, ini, validac); // Coloca la "X" dentro del cuadro
        doc.text(margenizquierdo * 8, ini, 'BANCO:');
        ini = ini + 6;

        doc.setFontSize(12);
        ini = ini + 13;
        doc.text(margenizquierdo + 10, ini, '________________________');
        doc.text(margenizquierdo + 100, ini, '________________________');
        ini = ini + 6;
        doc.text(margenizquierdo + 35, ini, 'FIRMA');
        doc.text(margenizquierdo + 114, ini, 'FIRMA Y SELLO');
        ini = ini + 6;
        doc.setFontSize(10);
        doc.text(margenizquierdo + 33, ini - 2, '(CLIENTE)');
        doc.text(margenizquierdo + 117, ini - 2, '(OPERADOR) ');



    } else {
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_altascumbres(datos) {
    alert("Impresion de recibo");

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   console.log(datos);
    //   return;

    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();


    var i = 1;
    var ini = 33;
    var margenizquierdo = 20;
    while (i < 2) {
        doc.setFontSize(7);
        // doc.text(margenizquierdo + 170, ini - 10, datos[5]);
        // doc.text(margenizquierdo + 50 , ini - 12, agencia + datos[4]);
        doc.text(margenizquierdo, ini, fecha,);
        doc.text(margenizquierdo, ini + 5, datos[7]);
        doc.text(margenizquierdo, ini + 10, datos[15]);
        doc.text(margenizquierdo, ini + 15, '        ' + datos[2],);
        doc.text(margenizquierdo, ini + 20, ' ' + datos[3]);
        doc.text(margenizquierdo, ini + 25, ' ' + datos[19]);
        doc.text(margenizquierdo, ini + 30, ' ' + datos[3]);
        doc.text(margenizquierdo * 5 + 20, ini, fecha);
        doc.text(margenizquierdo * 5 + 20, ini + 5, datos[7]);
        doc.text(margenizquierdo * 5 + 20, ini + 10, datos[15]);
        doc.text(margenizquierdo * 5 + 20, ini + 15, '        ' + datos[2],);
        doc.text(margenizquierdo * 5 + 20, ini + 20, ' ' + datos[3]);
        doc.text(margenizquierdo * 5 + 20, ini + 25, ' ' + datos[19]);
        doc.text(margenizquierdo * 5 + 20, ini + 30, ' ' + datos[3]);
        i++;

    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_copefuente(datos) {
    alert("Impresion de recibo");

    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    //   console.log(datos);
    //   return;

    var doc = new jsPDF(opciones);
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();
    var i = 1;
    var ini = 30;
    var margenizquierdo = 20;
    while (i < 2) {
        doc.setFontSize(10);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }
        var espacios = '                      ';

        doc.text(margenizquierdo + 20, ini + 5, 'NOMBRE DEL ASOCIADO      ' + datos[7] + '    ' + datos[4]);
        doc.text(margenizquierdo, ini + 10, 'ASOCIADO No. ' + datos[15] + "           " + datos[6]);
        doc.text(margenizquierdo + 60, ini + 15, ' DESCRIPCION');

        const datoview = datos[18];  // Obtener el valor original
        const datoview_format = parseFloat(datoview).toFixed(2)
        let descrip = datos[17];  //  valor original
        descrip = descrip.toString().toUpperCase();  // mayúsculas

        if (descrip.length > 20) {
            descrip = descrip.slice(0, 20) + '...';  // Truncar
        }
        const datoview2 = '-';
        const datoview3 = '-';
        const datoview4 = '-';

        //primwera columna
        doc.text(margenizquierdo, ini + 20, 'INSCRIPCION');
        doc.text(margenizquierdo, ini + 25, descrip + espacios + '      ');
        doc.text(margenizquierdo, ini + 30, 'AHORRO CORRIENTE' + '            ' + espacios + '      ');
        doc.text(margenizquierdo, ini + 35, 'RETIRO DE AHORRO FIJO' + '     ' + espacios);
        doc.text(margenizquierdo, ini + 40, 'INGRESOS VARIOS' + '                   ');
        doc.text(margenizquierdo, ini + 50, 'TOTAL EN LETRAS: ' + datos[11]);

        //SEGUNDA COLUMNA  Q and -
        doc.text(margenizquierdo + 8, ini + 20, espacios + espacios + 'Q' + '      ' + datos[16] + espacios + espacios + espacios + '        Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 25, espacios + espacios + 'Q' + '      ' + datos[31]);
        doc.text(margenizquierdo + 8, ini + 30, espacios + espacios + 'Q' + espacios + datoview3 + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 35, espacios + espacios + 'Q' + espacios + datoview4 + espacios + espacios + espacios + 'Q' + espacios + '-');
        doc.text(margenizquierdo + 8, ini + 40, espacios + espacios + 'Q' + espacios + '-' + espacios + espacios + espacios + 'Q' + espacios + '-');

        //TERCERA COLUMNA
        doc.text(margenizquierdo + 90, ini + 20, 'CAPITAL');
        doc.text(margenizquierdo + 90, ini + 25, 'INTERES');
        doc.text(margenizquierdo + 90, ini + 30, 'MORA');
        doc.text(margenizquierdo + 90, ini + 35, 'DESEMBOLSO');
        doc.text(margenizquierdo + 90, ini + 40, 'SALDO ACTUAL');


        //Cuarta columna
        doc.text(margenizquierdo + 8, ini + 25, espacios + espacios + espacios + espacios + espacios + espacios + '    ' + 'Q' + espacios + '-');


        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_recibo_dep_ret_cope27(datos) {
    //alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    let doc = new jsPDF(opciones);
    let fuente = 'Courier';
    doc.setFont(fuente);

    let i = 1;
    let ini = 28;
    let margenizquierdo = 78;
    while (i <= 1) {
        doc.setFontSize(11);
        doc.setFontStyle('bold');
        doc.setFontSize(13);
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo, ini + 6, 'Número de cuenta de ahorro:  ' + datos[2]);
        doc.text(margenizquierdo, ini + 12, 'Operación y No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini + 18, 'Fecha del documento: ' + datos[4]);
        doc.setFontSize(14);
        doc.text(margenizquierdo, ini + 24, 'Monto: Q ' + datos[3]);
        doc.setFontSize(14);
        doc.text(margenizquierdo, ini + 30, 'Operación: ' + datos[6]);
        doc.text(margenizquierdo, ini + 36, 'Fecha de Impresion: ' + datos[10]);
        // Procesar los nombres es decir iniciales
        let inicialesNombre = datos[8].split(' ').map(palabra => palabra.charAt(0).toUpperCase()).join('.');
        let inicialesApellido = datos[9].split(' ').map(palabra => palabra.charAt(0).toUpperCase()).join('.');
        let textoOperador = `Operador:  ${inicialesNombre}.${inicialesApellido}.`;
        doc.text(margenizquierdo, ini + 42, textoOperador);


        ini += 53;
        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_copeixil(datos) {
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 35;
    var margenizquierdo = 16;
    while (i < 2) {
        doc.setFontSize(10);
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);


        if (datos[16] > 0) {
            ini = ini + 6;
            doc.text(margenizquierdo + 74, ini, 'CuotaIngreso: Q ' + datos[16]);
        }

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, ' ' + datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_coinco(datos) {
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 30;
    var margenizquierdo = 20;
    while (i < 2) {
        doc.setFontSize(11);
        if (datos[23] === 'D') {
            ini = ini + 2;
            doc.text(160, ini, datos[3]);
            ini = ini + 10;
            ini = ini + 8;
            doc.text(40, ini, datos[21]);
            doc.text(100, ini, datos[7]);
            ini = ini + 8;
            doc.text(50, ini, datos[11]);
            ini = ini + 7;
            ini = ini + 7;
            ini = ini + 7;
            ini = ini + 7;
            ini = ini + 3;
            doc.text(7, ini, 'X');
            ini = ini + 14;
            if (datos[20] === 'E') {
                doc.text(40, ini, 'X');
            } else if (datos[20] === 'D') {
                doc.text(160, ini, 'X');
            }
            ini = ini + 9;
            doc.text(100, ini, datos[14]);
            doc.text(149, ini, datos[13]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(182, ini, ultimosDosDigitos);

        } else if (datos[23] === 'R') {
            ini = ini + 2;
            doc.text(156, ini, datos[3]);
            ini = ini + 10;
            ini = ini + 16;
            doc.text(60, ini, datos[11]);
            ini = ini + 28.6;
            doc.text(74, ini, 'X');
            doc.text(112, ini, 'Retiro de Ahorros');
            ini = ini + 11.5;
            if (datos[20] === 'E') {
                doc.text(36, ini, 'X');
            } else if (datos[20] === 'C') {
                doc.text(100, ini, 'X');
            }
            ini = ini + 9;
            doc.text(90, ini, datos[14]);
            doc.text(140, ini, datos[13]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(176, ini, ultimosDosDigitos);
        }
        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'));
}

//#endregion

//#region FORMATO PARA CERTIFICADO INICIAL
//MAIN
function impresion_certificado_main(datos) {
    alert("Impresion de certificado de aportación");
    //configuraciones generales del tamaño del reporte
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    //configuraciones generales
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    doc.text(20, 20, 'Certificacion a: ' + datos[1]);
    //ciclo for para recorrer a los beneficiarios
    var beneficiarios = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
        }
    }
    //se redimensiona, es como un multicel
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    doc.text(20, 30, splitTitle);
    //la fecha se divide
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    //se transforma el mes en letras
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];
    //se muestra la fecha
    doc.text(20, 40, 'Fecha: ' + dia + '       ' + mes_convertido + '      ' + ano);
    //se muestra el codigo del certificado
    doc.text(20, 50, datos[3]);
    //se muestra el monto en numeros
    doc.text(20, 60, datos[4]);
    //se muestra el monto en letras
    var splitTitle1 = doc.splitTextToSize(datos[5], 180);
    doc.text(20, 70, splitTitle1);
    //se muestra si es impresion o reimpresion
    doc.text(20, 80, datos[6]);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_micasa(datos) {
    // console.log(datos);
    alert("Impresion de certificado de aportación");
    //configuraciones generales del tamaño del reporte
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    //configuraciones generales
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);

    //ciclo for para recorrer a los beneficiarios
    var beneficiarios = "";
    var descripcion = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        descripcion = descripcion + datos[0][i]['descripcion'];
    }
    //la fecha se divide
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    //se transforma el mes en letras
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    doc.text(88, 87, datos[1]); // Cliente
    doc.text(19, 92, datos[15]); // Codigo Cliente
    doc.text(18, 99, datos[4]); // Monto Numero

    //BENEFICIARIOS
    doc.text(135, 145, beneficiarios);
    doc.text(100, 152, descripcion);//parentezco

    //se muestra la fecha
    doc.text(75, 172, dia + '                ' + mes_convertido + '             ' + ano);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_credivasquez(datos) {
    alert("Impresion de certificado de aportación");
    //configuraciones generales del tamaño del reporte
    // console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    //configuraciones generales
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    doc.text(100, 76, datos[1]);//Cliente
    doc.text(50, 86.5, datos[15]); //Codigo CLiente
    //ciclo for para recorrer a los beneficiarios
    var beneficiarios = "";
    var oficina = datos[12];
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
        }
    }
    //se redimensiona, es como un multicel
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    doc.text(20, 198, splitTitle);
    //la fecha se divide
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    //se transforma el mes en letras
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];
    doc.text(150, 97, datos[4]); // MONTO NUMERO
    //se muestra la fecha
    // doc.text(20, 40, 'Fecha: ' + dia + '       ' + mes_convertido + '      ' + ano);
    var lugaroficina = (oficina == "1") ? "Aldea Vásquez, Totonicapán" : "Aldea Vásquez, Totonicapán";
    doc.text(45, 212, lugaroficina + "  " + datos[2]);
    //se muestra el codigo del certificado
    //doc.text(20, 50, datos[3]);

    //se muestra el monto en letras
    // var splitTitle1 = doc.splitTextToSize(datos[5], 180);
    //doc.text(20, 70, splitTitle1);
    //se muestra si es impresion o reimpresion
    //doc.text(20, 80, datos[6]);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

// CORPOCREDIT
function impresion_certificado_corpocredit(datos) {
    // console.log(datos);
    //  return;
    alert("Impresion de certificado de aportación");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var codcuenta = datos[9];
    var norecibo = datos[8];
    var controlinterno = datos[10];


    doc.text(90, 66, ' ' + codcuenta); // CODCUENTA
    doc.text(92, 72, ' ' + datos[1]); // NOMBRE
    doc.text(55, 79, 'C.I. ' + controlinterno);//COD INTERNO
    doc.text(132, 79, norecibo);// RECIBO CAJA
    var beneficiarios = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
        }
    }
    var splitTitle1 = doc.splitTextToSize(datos[5], 180);
    doc.text(115, 87, splitTitle1);//cantidad en letras
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);

    doc.text(23, 92, datos[4]);//monto
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    var message = datos[6] === "R" ? " R  (Reimpreso)" : "I (Original)";
    // doc.text(160, 136, message);

    doc.text(20, 149, splitTitle); //beneficiario
    doc.text(60, 156, ' Nebaj Quiché, ' + dia + '       ' + mes_convertido + '      ' + ano); //Fecha

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

// ADIF
function impresion_certificado_adif(datos) {
    // console.log(datos);
    //  return;
    alert("Impresion de certificado de aportación");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    doc.text(170, 25, datos[3]);//cod
    doc.text(25, 88, ' ' + datos[1]); // NOMBRE
    doc.text(75, 95, ' ' + datos[7]); // DPI

    var beneficiarios = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
        }
    }
    var splitTitle1 = doc.splitTextToSize(datos[5], 180);//EN LETRAS
    doc.text(115, 115, splitTitle1);
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    doc.text(25, 120, '(Q.' + datos[4] + ')');//monto

    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];
    var message = datos[6] === "R" ? " R  (Reimpreso)" : "I (Original)";

    //doc.text(160, 136, message);

    // doc.text(20, 145, splitTitle);
    // doc.text(60, 154, datos[2]); //Fecha
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_coditoto(datos)
// console.log(datos);
// return;
{
    alert("Impresion de certificado de aportación");
    //configuraciones generales del tamaño del reporte
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    //configuraciones generales
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);
    var margenIzquierdo = 80;

    //nombre
    doc.text(margenIzquierdo, 110, ' ' + datos[1]);

    //ciclo for para recorrer a los beneficiarios
    var beneficiarios = "                       ";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
        }
    }
    //se redimensiona, es como un multicel
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    doc.text(20, 209, splitTitle);

    //la fecha se divide
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];

    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    var espacios = '                      ';

    doc.text(118, 236, dia + espacios + '     ' + mes_convertido + '      ' + espacios + ano);


    // //se muestra el codigo del certificado
    // doc.text(20, 150, datos[3]);
    // //se muestra el monto en numeros
    // doc.text(20, 160, datos[4]);
    // //se muestra el monto en letras
    // var splitTitle1 = doc.splitTextToSize(datos[5], 180);
    // doc.text(20, 170, splitTitle1);
    // //se muestra si es impresion o reimpresion
    // doc.text(20, 180, datos[6]);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function certificado_aportacion_coopeadg(datos) {
    // console.log(datos);
    // return;
    alert("Impresion de certificado de aportación");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
     var agencia = '';
    if(datos[12]==1){
        agencia = "Tecpán Guatemala, Chimaltenango. ";

    } if(datos[12]==2){
        agencia = " San José Poaquil ";

    } if(datos[12]==3){
        agencia = "Chupol ";
    }
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);
    var codcuenta = datos[9];
    var norecibo = datos[8];
    var controlinterno = datos[10];
    var telcli = datos[11];
    var dpi_cli = datos[7];
    var splitTitle1 = doc.splitTextToSize(datos[5], 180);

    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    var Y = 10;

    doc.setFont('helvetica', 'bold');
    doc.text(20, 40 + Y, 'LUGAR Y FECHA: ');
    doc.setFont('helvetica', 'normal');
    doc.text(51, 40 + Y, agencia + dia + ' de ' + mes_convertido + ' del año ' + ano); //Fecha

    doc.setFont('helvetica', 'bold');
    doc.text(165, 48 + Y, 'MONTO: Q. ' + datos[4]);//monto
    doc.text(100, 55 + Y, 'TELEFONO:');
    doc.setFont('helvetica', 'normal');
    doc.text(123, 55 + Y, telcli); // telefono

    doc.text(145, 55 + Y, 'DPI ' + dpi_cli); //dpi cliente

    doc.setFont('helvetica', 'bold');
    doc.text(20, 55 + Y, 'CUENTA DE APORTACION: ');
    doc.setFont('helvetica', 'normal');
    doc.text(68, 55 + Y, codcuenta); // CODCUENTA

    doc.setFont('helvetica', 'bold');
    doc.text(20, 51 + Y, 'NOMBRE DEL ASOCIADO: ');
    doc.setFont('helvetica', 'normal');
    doc.text(66, 51 + Y, datos[1]); // NOMBRE

    doc.setFont('helvetica', 'bold');
    doc.text(53, 60 + Y, 'MONTO EN LETRAS: '); //guia inicio en linia 57 sumar a todos +
    doc.setFont('helvetica', 'normal');
    doc.text(90, 60 + Y, splitTitle1);//cantidad en letras


    //   doc.text(132, 79, norecibo);// RECIBO CAJA
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        dpi = dpi + datos[0][i]['dpi'];
        decripc = decripc + datos[0][i]['descripcion'];
        tel = tel + datos[0][i]['telefono'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + "\n";
            dpi = dpi + "\n";
            decripc = decripc + "\n";
            tel = tel + "\n";
        }
    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);

    doc.setFont('helvetica', 'bold');
    doc.text(25, 65 + Y, 'DATOS DE BENEFICIARIO ');
    doc.setFont('helvetica', 'normal');

    doc.setFont('helvetica', 'bold');
    doc.text(20, 70 + Y, 'NOMBRE COMPLETO');
    doc.text(95, 70 + Y, 'PARENTESCO');
    // doc.text(130, 70, 'DOC. IDENTIFICACION');
    // doc.text(175, 70, 'NO. TELEFONO');
    doc.setFont('helvetica', 'normal');

    doc.text(20, 74 + Y, splitTitle); //beneficiario
    doc.text(95, 74 + Y, decripc2); //parentezco
    // doc.text(130, 74, dpi2); //DPI beneficiario
    // doc.text(175, 74, tel2); //telefono

    var message = datos[6] === "R" ? " R  (Reimpreso)" : "I (Original)";
    // doc.text(160, 136, message);



    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function certificado_aportacion_altascumbres(datos) {
    alert("Impresion de certificado de aportación");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    // var agencia = (datos[12] == 1) ? "Tecpán Guatemala, Chimaltenango. " : " San José Poaquil ";
    var direccion = "GUATEMALA";
    var doc = new jsPDF(opciones);
    doc.setFontSize(14);
    var codcuenta = datos[9];
    var fechacrt = datos[13];

    var array_fechasol = fechacrt.split("-")
    var ano = array_fechasol[0];
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[2];

    doc.setFont('helvetica', 'bold');
    doc.text(50, 40, codcuenta); // CODCUENTA
    doc.text(70, 140, datos[1]); // NOMBRE
    doc.text(158, 176, "una");
    doc.text(90, 203, direccion); // telefono
    doc.text(90, 215, dia + ' de ' + mes_convertido + ' del año ' + ano);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function certificado_aportacion_otziles(datos) {
    // console.log(datos);
    // return;
    alert("Impresion de certificado de aportación");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var agencia = (datos[12] == 1) ? "Tecpán Guatemala, Chimaltenango. " : " San José Poaquil ";
    var doc = new jsPDF(opciones);
    doc.setFontSize(8);
    var codcuenta = datos[9];
    var norecibo = datos[8];
    var controlinterno = datos[10];
    var telcli = datos[11];
    var dpi_cli = datos[7];
    var splitTitle1 = doc.splitTextToSize(datos[5], 180);

    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    doc.setFont('helvetica', 'bold');
    doc.text(20, 51, 'LUGAR Y FECHA: ');
    doc.setFont('helvetica', 'normal');
    doc.text(51, 51, agencia + dia + ' de ' + mes_convertido + ' del año ' + ano); //Fecha

    doc.setFont('helvetica', 'bold');
    doc.text(165, 48, 'MONTO: Q. ' + datos[4]);//monto
    doc.text(100, 55, 'TELEFONO:');
    doc.setFont('helvetica', 'normal');
    doc.text(123, 55, telcli); // telefono

    doc.text(145, 55, 'DPI ' + dpi_cli); //dpi cliente

    doc.setFont('helvetica', 'bold');
    doc.text(20, 55, 'CUENTA DE APORTACION: ');
    doc.setFont('helvetica', 'normal');
    doc.text(68, 55, codcuenta); // CODCUENTA

    // doc.setFont('helvetica', 'bold');
    // doc.text(20, 38, 'NOMBRE DEL ASOCIADO: ');
    doc.setFont('helvetica', 'normal');
    doc.text(63, 40, datos[1]); // NOMBRE

    doc.setFont('helvetica', 'bold');
    doc.text(53, 60, 'MONTO EN LETRAS: '); //guia inicio en linia 57 sumar a todos +
    doc.setFont('helvetica', 'normal');
    doc.text(90, 60, splitTitle1);//cantidad en letras


    //   doc.text(132, 79, norecibo);// RECIBO CAJA
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        dpi = dpi + datos[0][i]['dpi'];
        decripc = decripc + datos[0][i]['descripcion'];
        tel = tel + datos[0][i]['telefono'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + "\n";
            dpi = dpi + "\n";
            decripc = decripc + "\n";
            tel = tel + "\n";
        }
    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);

    doc.setFont('helvetica', 'bold');
    doc.text(25, 65, 'DATOS DE BENEFICIARIO ');
    doc.setFont('helvetica', 'normal');

    doc.setFont('helvetica', 'bold');
    doc.text(20, 70, 'NOMBRE COMPLETO');
    doc.text(95, 70, 'PARENTESCO');
    // doc.text(130, 70, 'DOC. IDENTIFICACION');
    // doc.text(175, 70, 'NO. TELEFONO');
    doc.setFont('helvetica', 'normal');

    doc.text(20, 74, splitTitle); //beneficiario
    doc.text(95, 74, decripc2); //parentezco
    // doc.text(130, 74, dpi2); //DPI beneficiario
    // doc.text(175, 74, tel2); //telefono

    var message = datos[6] === "R" ? " R  (Reimpreso)" : "I (Original)";
    // doc.text(160, 136, message);



    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_otziles(datos) {
    alert("Impresion de recibo");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300],

    };
    console.log(datos);
    //  return;
    var doc = new jsPDF(opciones);
    doc.setFont("courier", "normal");
    var fechaActual = new Date();
    // Obtener la fecha y hora
    var fecha = fechaActual.toLocaleDateString();
    var hora = fechaActual.toLocaleTimeString();


    var i = 1;
    var ini = 28;
    var margenizquierdo = 55;
    while (i < 2) {
        doc.setFontSize(9);

        decimal = datos[3].toString();
        if (decimal.indexOf('.') != -1) {
            var decimal = decimal.split('.')[1];
        }
        var Tot_efectivo = 0;
        var apr = 0;
        doc.text(margenizquierdo, ini, "Fecha: " + datos[10]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'OTZ´ILES R.L. /PRODUCTO: ' + datos[17]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'No. de Cuenta: ' + datos[2]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'No. de Boleta de Transaccion: ' + datos[5]);
        var apr = parseFloat(datos[3]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Letras: ' + datos[19]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, "Aportacion:                Q." + datos[3]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, ((datos[16] > 0) ? "Cuota de ingreso:          Q. " + parseFloat(datos[16]).toFixed(2) : ""));
        var total_format = parseFloat(datos[18]).toFixed(2);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, "Efectivo:                  Q." + total_format);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Asociado / A:' + datos[7]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Operación: ' + ((datos[6] === "D") ? "DEPOSITO A CUENTA " : ((datos[6] === "R") ? "RETIRO A CUENTA " : datos[6])));

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Usuario: ' + datos[20]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function certificado_aportacion_ixoj(datos) {
    // console.log(datos);
    // return;
    alert("Impresion de certificado de aportación");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(8);
    var codcuenta = datos[9];
    var norecibo = datos[8];
    var controlinterno = datos[10];
    var telcli = datos[11];
    var dpi_cli = datos[7];
    var splitTitle1 = doc.splitTextToSize(datos[5], 180);

    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    var ultimosDos = ano.slice(-2);
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    doc.setFont('helvetica', 'normal');
    doc.text(70, 52, datos[1]); // NOMBRE DEL CLIENTE
    doc.text(40, 57, datos[15]); // CODIGO DEl cliente
    doc.text(190, 57, datos[4]);//monto

    //   doc.text(132, 79, norecibo);// RECIBO CAJA
    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        dpi = dpi + datos[0][i]['dpi'];
        decripc = decripc + datos[0][i]['descripcion'];
        tel = tel + datos[0][i]['telefono'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + "\n";
            dpi = dpi + "\n";
            decripc = decripc + "\n";
            tel = tel + "\n";
        }
    }
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);

    doc.text(130, 96, splitTitle); //beneficiario
    //doc.text(95, 74, decripc2); //parentezco
    // doc.text(130, 74, dpi2); //DPI beneficiario
    // doc.text(175, 74, tel2); //telefono

    doc.text(140, 113, dia);
    doc.text(170, 113, mes);
    doc.text(200, 113, ultimosDos);
    // doc.text(160, 136, message);
    var message = datos[6] === "R" ? " R  (Reimpreso)" : "I (Original)";

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_ixoj(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 41;
    var margenizquierdo = 18;
    while (i < 2) {

        if (datos[27] == 'D') {

            doc.setFontSize(10);

            doc.text(15, 42, datos[14]);
            doc.text(43, 42, datos[13]);
            // doc.text(68, 42, datos[12]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(71, 42, ultimosDosDigitos);

            doc.text(margenizquierdo + 3, 50, datos[7]); //Cliente

            doc.text(margenizquierdo + 12, 63.5, datos[11]); // Cantidad en letras
            // doc.text(margenizquierdo + 16,ini,datos[26]); //Direccion

            doc.text(margenizquierdo + 12, 77.5, datos[2]); //CUENTA

            //doc.text(margenizquierdo + 105,60,datos[3]); //cantidad

            if (datos[17] == 'Aportacion Obligatoria') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 143, 57, datos[3]);
                doc.text(margenizquierdo + 143, 129, datos[3]); // TOTAL
            }
            if (datos[17] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 143, 100, datos[3]);
                doc.text(margenizquierdo + 143, 129, datos[3]); // TOTAL
            }
            if (datos[17] == 'Ahorro infanto juvenil') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 143, 106, datos[3]);
                doc.text(margenizquierdo + 143, 129, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            // let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            // let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            // let saldoAnterior1 = saldoActual1 - deposito;
            // doc.text(30, 102, ' ' + saldoAnterior1); //SALDO ANTERIOR
            doc.text(30, 108, ' ' + datos[3]); //ABONO
            // doc.text(30, 115, ' ' + datos[24]); //SALDO ACTUAL
        }

        if (datos[27] == 'R') {

            doc.setFontSize(10);

            doc.text(15, 39, datos[14]);
            doc.text(43, 39, datos[13]);
            // doc.text(68, 42, datos[12]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(75, 39, ultimosDosDigitos);

            // doc.text(margenizquierdo + 3, 50,datos[7]); //Cliente

            doc.text(margenizquierdo + 12, 55, datos[11]); // Cantidad en letras
            // doc.text(margenizquierdo + 16,ini,datos[26]); //Direccion

            doc.text(10, 68, ' ' + datos[3]); //CANTIDAD EN NUMERO

            doc.text(margenizquierdo + 12, 78, datos[2]); //CUENTA

            //doc.text(margenizquierdo + 105,60,datos[3]); //cantidad

            if (datos[17] == 'Aportacion Obligatoria') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 150, 55, datos[3]);
                doc.text(margenizquierdo + 150, 118, datos[3]); // TOTAL
            }
            if (datos[17] == 'Ahorro programado') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 150, 93, datos[3]);
                doc.text(margenizquierdo + 150, 118, datos[3]); // TOTAL
            }
            if (datos[17] == 'Ahorro infanto juvenil') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 150, 100, datos[3]);
                doc.text(margenizquierdo + 150, 118, datos[3]); // TOTAL
            }

            // let saldoActual = parseFloat(datos[24]);
            // let retiro = parseFloat(datos[25]);
            // let saldoAnterior = saldoActual + retiro;
            // ini = ini + 6;
            // doc.text(35, 105, ' ' + saldoAnterior); //SALDO ANTERIOR
            doc.text(35, 111, ' ' + datos[3]); //ABONO
            // doc.text(35, 118, ' ' + datos[24]); //SALDO ACTUAL
        }

        // ini = ini + 6;
        // doc.text(margenizquierdo, ini, 'Operador: ' + datos[8]);
        // ini = ini + 5;
        // doc.text(margenizquierdo, ini,datos[10]);

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_coopetikal(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(8.5);
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(22, pos, "" + fecha);
        doc.text(42, pos, "" + datos[1][i]['cnumdoc']);
        detalle = datos[1][i]['crazon'];
        doc.text(58, pos, "" + detalle);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            // transaccion = "Retiro";

            // doc.text(30, pos, transaccion);
            doc.text(103, pos, "" + pad(currency(monto)));
            doc.text(103, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            // transaccion = "Deposito";
            // doc.text(30, pos, transaccion);
            doc.text(81, pos, "");
            doc.text(81, pos, "" + pad(currency(monto)));
        }
        doc.text(130, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_recibo_dep_ret_copeadif(datos) {
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 30;
    var margenizquierdo = 20;

    while (i < 2) {
        doc.setFontSize(12);
        doc.text(margenizquierdo, ini, 'Cuenta de aportación No. ' + datos[2]);
        doc.text(115, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(150, ini, 'Monto: Q ' + datos[3]);
        ini = ini + 4;
        doc.text(margenizquierdo + 110, ini, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : ""));

        ini = ini + 7;
        doc.text(60, ini, 'Operación: ' + datos[6]);

        doc.setFontSize(10);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
function impresion_recibo_dep_ret_adif(datos) {
    console.log(datos);
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 33;
    var margenizquierdo = 5;

    while (i < 2) {
        doc.setFontSize(9);
        doc.text(margenizquierdo, ini, 'Cuenta de Aportaciones No. ' + datos[2]);
        doc.text(margenizquierdo + 90, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 100, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 4;
        doc.text(margenizquierdo + 85, ini, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : ""));

        ini = ini + 7;
        doc.text(margenizquierdo + 20, ini, 'Operación: ' + datos[6]);

        doc.setFontSize(8);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

//#endregion xcertificados

//#region FUNCIONES PARA FORMATO DE LIQUIDACION DE CERTIFICADO
// MAIN
//#region

//#region FUNCIONES PARA FORMATO DE COMPROBANTE DE CERTIFICADO
// MAIN
//#region

function impresion_certificado_codepa(datos) {
    // console.log(datos);
    // return;
    alert("Impresion de certificado de aportación");
    datosCuenta = datos[14];
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    const y = 50;
    const x = 10;
    // var agencia = (datos[12] == 1) ? "Tecpán Guatemala, Chimaltenango. " : " San José Poaquil ";
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);
    var codcuenta = datosCuenta['ccodaport'];
    var norecibo = datosCuenta['norecibo'];
    var controlinterno = datosCuenta['controlinterno'];
    var telcli = datosCuenta['telcli'];
    var dpi_cli = datosCuenta['dpi_cli'];
    var splitTitle1 = doc.splitTextToSize(datos[5], 180);

    var array_fechasol = datosCuenta['fecha_hoy'].split("-")
    var ano = array_fechasol[2];
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    doc.setFont('helvetica', 'bold');
    doc.text(x + 20, y + 15, datosCuenta['cliente']);
    doc.text(x + 20, y + 43, datosCuenta['cliente']);
    doc.text(x + 35, y + 53, datosCuenta['monto_cert']);

    var beneficiarios = "";
    let dpi = "";
    let decripc = "";
    let tel = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = datos[0][i]['nombre'];
        dpi = dpi + datos[0][i]['dpi'];
        decripc = datos[0][i]['descripcion'];
        tel = tel + datos[0][i]['telefono'];
        doc.text(x + 45, y + 54 + (i * 9), beneficiarios + " (" + decripc + ")");
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
            dpi = dpi + ", ";
            decripc = decripc + ", ";
            tel = tel + ", ";
        }

    }

    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var dpi2 = doc.splitTextToSize(dpi, 180);
    var decripc2 = doc.splitTextToSize(decripc, 180);
    var tel2 = doc.splitTextToSize(tel, 180);

    doc.text(x + 160, y + 137, datosCuenta['norecibo']);

    doc.text(x + 40, y + 192, dia + " de " + mes_convertido + " del año " + ano);

    var message = datos[6] === "R" ? " R  (Reimpreso)" : "I (Original)";
    // doc.text(160, 136, message);



    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_copim(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    //console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFont('Arial', 'bold');
    doc.setFontSize(10);

    // Margen izquierdo
    var margenIzquierdo = 20;

    //var i = 0;
    var i = posini;
    var tiptr;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        // doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(margenIzquierdo + 17, pos, "" + fecha);
        doc.text(margenIzquierdo + 60, pos, " " + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        // console.log("TIPO DE TRANSACCION: " + tiptr);
        // console.log(datos[1][i]['ctipdoc']);
        if (tiptr == "D" && datos[1][i]['ctipdoc'] !== "IN") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito";
            doc.text(margenIzquierdo + 45, pos, transaccion);
            doc.text(margenIzquierdo + 54, pos, "");
            doc.text(margenIzquierdo + 90, pos, "" + pad(currency(monto)));
        }
        if (tiptr == "D" && datos[1][i]['ctipdoc'] == "IN") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Dep/Int.";
            doc.text(margenIzquierdo + 45, pos, transaccion);
            doc.text(margenIzquierdo + 54, pos, "");
            doc.text(margenIzquierdo + 105, pos, "" + pad(currency(monto)));
        }

        if (tiptr == "R" && datos[1][i]['ctipdoc'] !== "IP") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro";
            doc.text(margenIzquierdo + 45, pos, transaccion);
            doc.text(margenIzquierdo + 72, pos, "" + pad(currency(monto)));
            doc.text(margenIzquierdo + 78, pos, "");
        }
        if (tiptr == "R" && datos[1][i]['ctipdoc'] == "IP") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Ret/ISR.";
            doc.text(margenIzquierdo + 45, pos, transaccion);
            doc.text(margenIzquierdo + 72, pos, "" + pad(currency(monto)));
            doc.text(margenIzquierdo + 78, pos, "");
        }

        doc.text(margenIzquierdo + 128, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;

    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_recibo_dep_ret_copim2(datos) {
    alert("Impresion de recibo");
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 43;
    var margenizquierdo = 20;
    var monto = parseFloat(datos[3]) + parseFloat(datos[16]);

    while (i < 2) {
        doc.setFont('Arial', 'bold');
        doc.setFontSize(11);

        doc.text(margenizquierdo + 83, ini - 4, datos[14]);//dia
        doc.text(margenizquierdo + 100, ini - 4, datos[13]);//mes
        doc.text(margenizquierdo + 115, ini - 4, datos[12]);//año
        // doc.text(margenizquierdo + 130, ini, 'Fecha operación: ' + datos[10]);
        ini = ini + 25;
        doc.setFontSize(10);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Fecha doc: ' + datos[4]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Cuenta por Pagar No. ' + datos[2]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);

        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Monto: Q ' + String(datos[3]));
        ini = ini + 4;
        doc.text(margenizquierdo, ini, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : ""));

        // concepto con salto de linea
        var maxWidth = 120;
        var lineas = doc.splitTextToSize("CONCEPTO: " + datos[6], maxWidth);
        ini = ini + 7;
        lineas.forEach(function (linea, index) {
            doc.text(margenizquierdo, ini + (index * 5), linea);
        });
        i++;
        if (datos[27] == 'D') {
            doc.text(170, ini + 13, String(datos[18]));//saldo
            ini = ini + 23;
            doc.text(margenizquierdo + 25, ini, datos[19]); // Cantidad en letras
        }
        if (datos[27] == 'R') {
            doc.text(180, ini + 6, "Q. " + String(datos[18]));//saldo
            ini = ini + 23;
            doc.text(margenizquierdo + 33, ini - 11, datos[19]); // Cantidad en letras
        }
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_copim(datos) {
    alert("Impresion de recibo");
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 43;
    var margenizquierdo = 20;

    while (i < 2) {
        doc.setFont('Arial', 'bold');
        doc.setFontSize(11);

        doc.text(margenizquierdo + 83, ini - 4, datos[14]);//dia
        doc.text(margenizquierdo + 100, ini - 4, datos[13]);//mes
        doc.text(margenizquierdo + 115, ini - 4, datos[12]);//año
        // doc.text(margenizquierdo + 130, ini, 'Fecha operación: ' + datos[10]);
        ini = ini + 25;
        doc.setFontSize(10);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Fecha doc: ' + datos[4]);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cuenta por Pagar No. ' + datos[2]);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        ini = ini + 7;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
        doc.text(170, ini - 4, 'Monto:');
        doc.text(170, ini, 'Q. ' + datos[3]);
        doc.text(170, ini + 6, ((datos[16] > 0) ? "Cuota de ingreso:" : " "));
        doc.text(170, ini + 10, ((datos[16] > 0) ? "Q. " + parseFloat(datos[16]).toFixed(2) : ""));
        // concepto con salto de linea
        var maxWidth = 120;
        var lineas = doc.splitTextToSize("CONCEPTO: " + datos[6], maxWidth);
        ini = ini + 7;
        lineas.forEach(function (linea, index) {
            doc.text(margenizquierdo, ini + (index * 5), linea);
        });
        i++;
        if (datos[27] == 'D') {
            doc.text(margenizquierdo + 55, 54, datos[7]);//nombre
            doc.setFontSize(13);
            doc.text(170, ini + 13, parseFloat(datos[18]).toFixed(1));//saldo
            doc.setFontSize(10);
            ini = ini + 23;
            doc.text(margenizquierdo + 25, ini + 3, datos[11]); // Cantidad en letras
        }
        if (datos[27] == 'R') {
            doc.setFontSize(13);
            doc.text(180, ini + 6, "Q. " + parseFloat(datos[18]).toFixed(1));//saldo
            doc.setFontSize(10);
            ini = ini + 23;
            doc.text(margenizquierdo + 33, ini - 11, datos[11]); // Cantidad en letras
        }
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_codepa(datos) {
    alert("Impresion de recibo");
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 43;
    var margenizquierdo = 10;

    while (i < 2) {
        doc.setFont('courier', 'normal');
        doc.setFontSize(8);
        doc.text(30, 10, datos[10]); //fecha operacion
        doc.setFontSize(12);
        fecha = conviertefecha(datos[28]);

        // doc.text(margenizquierdo + 83, ini - 4, datos[14]);//dia
        // doc.text(margenizquierdo + 100, ini - 4, datos[13]);//mes
        // doc.text(margenizquierdo + 115, ini - 4, datos[12]);//año
        // doc.text(margenizquierdo + 130, ini, 'Fecha operación: ' + datos[10]);
        ini = ini + 5;
        doc.text(margenizquierdo + 20, ini, datos[7]);//nombre
        doc.text(margenizquierdo + 130, ini, fecha);//fecha doc
        ini = ini + 7;
        doc.text(margenizquierdo + 22, ini, datos[30]); //Direccion
        doc.text(margenizquierdo + 140, ini, datos[2]); //Cuenta
        ini = ini + 20;
        doc.text(margenizquierdo, ini, datos[17]); //Operacion
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha doc: ' + fecha);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[6]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Operacion y No. Docto: ' + datos[5]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Monto: Q ' + datos[3]);
        if (parseFloat(datos[16]) > 0) {
            doc.text(margenizquierdo + 50, ini, ' Cuota de ingreso: Q ' + datos[16]);
        }
        // // doc.text(margenizquierdo, ini, 'Cantidad en Letras:'); // Cantidad en letras
        // // ini = ini + 5;
        // doc.text(margenizquierdo, ini,datos[11]);
        doc.text(margenizquierdo + 165, ini + 13, "Q. " + datos[18]);//saldo

        // concepto con salto de linea
        var maxWidth = 130;
        var lineas = doc.splitTextToSize("CONCEPTO: " + datos[29], maxWidth);
        ini = ini + 7;
        lineas.forEach(function (linea, index) {
            doc.text(margenizquierdo, ini + (index * 5), linea);
        });
        i++;
        if (datos[23] == 'D') {
            // doc.text(margenizquierdo + 55, 54, datos[7]);//nombre

            ini = ini + 23;
        }
        if (datos[23] == 'R') {
            // doc.text(200, ini + 6, "Q. " + datos[3]);//saldo
            ini = ini + 23;
        }
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_coperural(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 48;
    var margenizquierdo = 18;
    while (i < 2) {

        if (datos[27] == 'D') {

            doc.setFontSize(10);
            doc.text(margenizquierdo + 8, ini, datos[7]); //Nombre del cliente
            // doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
            doc.text(margenizquierdo + 138, ini, datos[2]); //Numero de asociado
            // doc.text(180, ini, '' + datos[5]);

            ini = ini + 6;
            doc.text(margenizquierdo + 8, ini, datos[30]); //direccion
            //doc.text(margenizquierdo + 130, ini,  datos[4]); //fecha
            ini = ini + 4;
            doc.text(margenizquierdo + 138, ini - 4, datos[14]);//dia
            doc.text(margenizquierdo + 147, ini - 4, datos[13]);//mes
            doc.text(margenizquierdo + 155, ini - 4, datos[12]);//año


            doc.text(margenizquierdo + 100, 85, datos[11]); // Cantidad en letras

            //doc.text(margenizquierdo + 105,60,datos[3]); //cantidad

            if (datos[17] == 'Aportacion Obligatoria') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 40, 61, datos[3]);
                doc.text(margenizquierdo + 40, 110.1, datos[3]); // TOTAL
            }
            if (datos[17] == 'Aportacion adicionales') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 40, 70, datos[3]);
                doc.text(margenizquierdo + 40, 110.1, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            let saldoActual1 = parseFloat(datos[3]); // SALDO ACTUAL
            let deposito = parseFloat(datos[16]); // SALDO ANTERIOR
            let saldoAnterior1 = saldoActual1 + deposito;
            doc.text(119, 68, ' ' + datos[16]); //SALDO ANTERIOR
            doc.text(119, 73, ' ' + datos[3]); //ABONO
            doc.text(119, 78, ' ' + saldoAnterior1); //SALDO ACTUAL
        }

        if (datos[27] == 'R') {

            doc.setFontSize(10);
            doc.text(margenizquierdo + 24, ini + 1.3, datos[7]); //Nombre del cliente
            // doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
            doc.text(margenizquierdo + 138, ini + 2, datos[15]); //Numero de asociado
            doc.text(margenizquierdo + 19, ini - 6, datos[2]); //CUENTA
            // doc.text(180, ini, '' + datos[5]);

            ini = ini + 6;
            //doc.text(margenizquierdo + 130, ini,  datos[4]); //fecha
            //ini = ini + 4;
            doc.text(margenizquierdo + 143, 40, datos[14]);//dia
            doc.text(margenizquierdo + 155, 40, datos[13]);//mes
            doc.text(margenizquierdo + 163, 40, datos[12]);//año

            doc.text(margenizquierdo + 33, 91.3, datos[11]); // Cantidad en letras

            if (datos[17] == 'Aportacion Obligatoria') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 75, 59, datos[3]);
                doc.text(margenizquierdo + 75, 86, datos[3]); // TOTAL
            }
            if (datos[17] == 'Aportacion adicionales') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 75, 62, datos[3]);
                doc.text(margenizquierdo + 75, 86, datos[3]); // TOTAL
            }

        }

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_certificado_coperural(datos) {
    console.log(datos);
    alert("Impresion de certificado de aportación");
    //configuraciones generales del tamaño del reporte
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    //configuraciones generales
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);

    //ciclo for para recorrer a los beneficiarios
    var beneficiarios = "";
    var descripcion = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        descripcion = descripcion + datos[0][i]['descripcion'];
    }
    //la fecha se divide
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    //se transforma el mes en letras
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    doc.text(88, 45, datos[1]); // Cliente
    doc.text(29, 51, datos[15]); // Codigo Cliente
    doc.text(25, 57, datos[4]); // Monto Numero

    //BENEFICIARIOS
    doc.text(118, 95, beneficiarios);
    doc.text(95, 101, descripcion);//parentezco

    //se muestra la fecha
    doc.text(65, 109, dia + '                ' + mes_convertido + '                                  ' + ano);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

// function impresion_certificado_lareinita(datos) {
//     console.log(datos);
//     alert("Impresion de certificado de aportación");
//     //configuraciones generales del tamaño del reporte
//     var opciones = {
//         orientation: 'p',
//         unit: 'mm',
//         format: [240, 300]
//     };
//     //configuraciones generales
//     var doc = new jsPDF(opciones);
//     doc.setFontSize(13);

//     //ciclo for para recorrer a los beneficiarios
//     var beneficiarios = ""; 
//     var descripcion = "";
//     const max_caracteres = 10;
//     for (let i = 1; i < datos[0].length; i++) {
//         beneficiarios = beneficiarios + datos[0][i]['nombre'];
//         descripcion = descripcion + datos[0][i]['descripcion'];
//     }
//     //la fecha se divide
//     var array_fechasol = datos[2].split("-")
//     var ano = array_fechasol[2];
//     //se transforma el mes en letras
//     var mes = array_fechasol[1];
//     var mes_convertido = convertir_mes(mes);
//     var dia = array_fechasol[0];

//     doc.text(88, 25, datos[1]); // Cliente
//     doc.text(29, 28, datos[15]); // Codigo Cliente
//     doc.text(25, 31, datos[4]); // Monto Numero

//     //BENEFICIARIOS
//     doc.text(118, 95, beneficiarios);
//    // doc.text(95, 101, descripcion);//parentezco

//     //se muestra la fecha
//     doc.text(69, 109, dia + '                ' + mes_convertido + '                                  ' + ano);

//     doc.autoPrint();
//     window.open(doc.output('bloburl'))
// }

function impresion_certificado_lareinita(datos) {
    console.log(datos);
    alert("Impresion de certificado de aportación");
    //configuraciones generales del tamaño del reporte
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    //configuraciones generales
    var doc = new jsPDF(opciones);
    doc.setFontSize(12);

    //ciclo for para recorrer a los beneficiarios
    var beneficiarios = "";
    var descripcion = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        descripcion = descripcion + datos[0][i]['descripcion'];
    }
    //la fecha se divide
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    //se transforma el mes en letras
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];

    doc.text(88, 66, datos[1]); // Cliente
    doc.text(39.5, 72, datos[15]); // Codigo Cliente
    doc.text(28, 77, datos[4]); // Monto Numero

    //BENEFICIARIOS
    doc.text(135, 105, beneficiarios);
    //doc.text(100, 152, descripcion);//parentezco

    //se muestra la fecha
    doc.text(75, 123, dia + '                                 ' + mes_convertido + '                                   ' + ano);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_emprendedor(datos) {
    console.log(datos);
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 40;
    var margenizquierdo = 20;

    while (i < 2) {
        if (datos[23] == 'D') {
            transaccion = "Deposito de Ahorro";
        }
        if (datos[23] == 'R') {
            transaccion = "Retiro de Ahorro";
        }
        doc.setFontSize(10);

        doc.text(margenizquierdo, ini, 'Operacion No.:');  doc.text(margenizquierdo + 40, ini, datos[5]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Fecha:'); doc.text(margenizquierdo + 40, ini, datos[28]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Nombre:'); doc.text(margenizquierdo + 40, ini, datos[7]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Código:') ; doc.text(margenizquierdo + 40, ini, datos[25]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'No. de Cuenta:');   doc.text(margenizquierdo + 40, ini, datos[2]);  doc.text(margenizquierdo + 85, ini, datos[17]);
        ini = ini + 4;
        doc.text(margenizquierdo, ini, 'Tipo de Transacción:');     doc.text(margenizquierdo + 40, ini, datos[6]);

        ini = ini + 8;
        doc.text(margenizquierdo, ini, 'Monto:');   doc.text(margenizquierdo + 40, ini, 'Q ' + datos[3]); doc.text(margenizquierdo + 70 , ini, 'Cuota de Ingreso:');   doc.text(margenizquierdo +  100, ini, 'Q ' + datos[16]);
        ini = ini + 4;
        doc.text(margenizquierdo+ 6, ini, 'TOTAL:');   doc.text(margenizquierdo + 40, ini, 'Q ' + parseFloat(datos[18]));  doc.text(margenizquierdo + 70, ini, 'Saldo Actual:');   doc.text(margenizquierdo +  100, ini, 'Q ' + datos[31]);

         //firmas
        ini = ini + 15;
        doc.text(margenizquierdo + 8, ini, 'F.__________________________');
        doc.setFontSize(9);
        doc.text(margenizquierdo + 10, ini + 5, datos[7]);
        doc.setFontSize(8);
        doc.text(margenizquierdo + 15, ini+10, 'Asociado');
        doc.setFontSize(12);
        doc.text(margenizquierdo + 95, ini, 'F._________________________');
        doc.setFontSize(9);
        doc.text(margenizquierdo + 110, ini + 5, datos[8] + ' ' + datos[9]);
        doc.setFontSize(8);
        doc.text(margenizquierdo + 105, ini + 10, 'Receptor');
        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}
//#region LIBRETA LA REINITA
function impresion_recibo_dep_ret_reinita(datos) {
    alert("Impresion de recibo *1 ");
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 30;
    var margenizquierdo = 20;

    while (i < 2) {
        doc.setFontSize(7);
        doc.text(150, 7, 'Fecha operación: ' + datos[4]);
        ini = ini + 25;
        doc.setFontSize(11);
        doc.text(margenizquierdo, ini, 'Cuenta de aportación No. ' + datos[2]);
        // Formatear datos[28] como dd-mm-aaaa
        var fechaDoc = datos[28];
        if (fechaDoc && typeof fechaDoc === 'string') {
            var partes = fechaDoc.split('-');
            if (partes.length === 3) {
                fechaDoc = partes[2] + '-' + partes[1] + '-' + partes[0];
            }
        }
        doc.text(115, ini, 'Fecha doc: ' + fechaDoc);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Monto: Q.' + datos[3]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, datos[16] > 0 ? 'Cuota de Ingreso: Q.' + datos[16] : '');
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(150, ini, 'Monto Total: Q.' + datos[3]);
        ini = ini + 5;
        // Mostrar 'Operación' con salto de línea automático si es muy largo
        var maxWidth = 160; // ancho máximo de la hoja
        var lineasOperacion = doc.splitTextToSize('Operación: ' + datos[6], maxWidth);
        lineasOperacion.forEach(function (linea, idx) {
            doc.text(margenizquierdo, ini + (idx * 5), linea);
        });
        ini += (lineasOperacion.length - 1) * 5;
        doc.setFontSize(10);
        ini = ini + 5;
        // Espacio para firmas
        // doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 15;
        doc.text(margenizquierdo + 10, ini, 'F. ________________________');
        doc.text(margenizquierdo + 110, ini, 'F. ________________________');
        ini = ini + 6;
        doc.setFontSize(9);
        doc.text(margenizquierdo, ini, "CLIENTE: " + datos[7]);
        doc.text(margenizquierdo + 100, ini, "OPERADOR: " + datos[8] + ' ' + datos[9]);


        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_emprendedor(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log("impresion libreta main")
    console.log(datos)
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(8);
    //var i = 0;
    var i = posini;
    var tiptr;
    var ctipdoc;
    //var saldo = parseFloat(datos[0][4])*porsaldo;
    var saldo = saldoo;
    var docto;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        // console.log(datos[1][i]);
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(6, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        docto = datos[1][i]['cnumdoc'];
        ctipdoc = datos[1][i]['ctipdoc'];
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (ctipdoc == "IN" || ctipdoc == "IP") {

            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "IN - IP/" + " " + docto;
            doc.text(27, pos, transaccion);
            doc.text(53, pos, "");
            doc.text(80, pos, "" + pad(currency(monto)));
            doc.text(106, pos, "");
        } else {
            if (tiptr == "D") {
                saldo = parseFloat(saldo) + parseFloat(monto);
                transaccion = "Dep" + "/" + " " + docto;
                doc.text(27, pos, transaccion);
                doc.text(53, pos, "" + pad(currency(monto)));
                doc.text(80, pos, "");
                doc.text(106, pos, "");
            }
            if (tiptr == "R") {
                saldo = parseFloat(saldo) - parseFloat(monto);
                transaccion = "Ret" + "/" + " " + docto;
                doc.text(27, pos, transaccion);
                doc.text(53, pos, "");
                doc.text(80, pos, "");
                doc.text(106, pos, "" + pad(currency(monto)));
            }
        }

        doc.text(129, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            // console.log("aki papu");
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }
    if (numi > nfront) {
        // console.log("aki papu 2");
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        // console.log("aki papu 3");
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        // console.log("aki papu 4");
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}
function impresion_libreta_reinita(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = posini;
    var tiptr;
    var cnumdoc;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        cnumdoc = datos[1][i]['cnumdoc'];
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(8, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = cnumdoc; // Retiro/Debito

            doc.text(28, pos, transaccion);
            doc.text(50, pos, "" + pad(currency(monto)));
            doc.text(88, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = cnumdoc; // Deposito/Abono
            doc.text(28, pos, transaccion);
            doc.text(64, pos, "");
            doc.text(78, pos, "" + pad(currency(monto)));
        }
        doc.text(120, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}


//pendiente 

function impresion_recibo_dep_ret_construfuturo(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 41;
    var margenizquierdo = 18;
    while (i < 2) {

        if (datos[27] == 'D') {

            doc.setFontSize(10);

            doc.text(33, 37, datos[14]);
            doc.text(45, 37, datos[13]);
            // doc.text(68, 37, datos[12]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(60, 37, ultimosDosDigitos);

            doc.setFontSize(9);
            doc.text(margenizquierdo + 12, 50, datos[7]); //Cliente

            doc.setFontSize(10);
            doc.text(margenizquierdo + 12, 57, 'Q.' + datos[3]); // Cantidad

            doc.text(margenizquierdo + 12, 64, datos[2]); //CUENTA


            if (datos[17] == 'Aportaciones obligatorias') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 38, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[17] == 'Aportaciones adicionales') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 48, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            // let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            // let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            // let saldoAnterior1 = saldoActual1 - deposito;
            // doc.text(46, 81 + 5, ' ' + saldoAnterior1); //SALDO ANTERIOR
            doc.text(46, 88 + 5, ' ' + datos[3]); //ABONO
            // doc.text(46, 95 + 5, ' ' + datos[24]); //SALDO ACTUAL

            doc.text(46, 107, datos[11]);
        }

        if (datos[27] == 'R') {

            doc.setFontSize(10);

            doc.text(33, 40, datos[14]);
            doc.text(45, 40, datos[13]);
            // doc.text(68, 40, datos[12]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(60, 40, ultimosDosDigitos);

            doc.setFontSize(9);
            doc.text(margenizquierdo + 10, 55, datos[7]); //Cliente

            doc.setFontSize(10);
            // doc.text(margenizquierdo + 12, 57, 'Q.' + datos[3]); // Cantidad

            doc.text(margenizquierdo + 12, 65, datos[2]); //CUENTA


            if (datos[17] == 'Aportaciones obligatorias') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 40, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }
            if (datos[17] == 'Aportaciones adicionales') {
                doc.setFontSize(10);
                doc.text(margenizquierdo + 146, 45, datos[3]);
                doc.text(margenizquierdo + 146, 98, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            // let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            // let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            // let saldoAnterior1 = saldoActual1 - deposito;
            // doc.text(46, 81 + 5, ' ' + saldoAnterior1); //SALDO ANTERIOR
            // doc.text(46, 88 + 5, ' ' + datos[3]); //ABONO
            // doc.text(46, 95 + 5, ' ' + datos[24]); //SALDO ACTUAL

            doc.text(46, 108, datos[11]);
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_recibo_dep_ret_kotan(datos) {
    // console.log(datos);
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 30;
    var margenizquierdo = 30;

    while (i < 2) {
        doc.setFontSize(8);

        ini = ini + 6;
        if (datos[27] == 'D') {
            doc.text(margenizquierdo, ini, 'Tipo de Operacion: DEPOSITO');
        } else if (datos[27] == 'R') {
            doc.text(margenizquierdo, ini, 'Tipo de Operacion: RETIRO');
        }

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        // doc.text(margenizquierdo + 80, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        // doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo, ini, 'Cantidad: Q ' + datos[3] + '     N.OP ' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Fecha Operación: ' + datos[4]);

        ini = ini + 8;
        doc.text(margenizquierdo - 15, ini, datos[6]); //concepto

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_recibo_dep_ret_edificando(datos) {
    // alert("Impresion de recibo *1 ");
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 38;
    var margenizquierdo = 55;

    while (i < 2) {
        doc.setFontSize(9);
        doc.text(margenizquierdo, ini, 'Cuenta: ' + datos[2]);
        doc.text(margenizquierdo + 70, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Monto: Q ' + datos[3]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : " "));
        doc.text(margenizquierdo + 70, ini, 'Total: Q.' + datos[18]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);

        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Producto: ' + datos[17]);

        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 5;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}


function impresion_libreta_edificando(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(10, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro";

            doc.text(30, pos, transaccion);
            doc.text(55, pos, "" + pad(currency(monto)));
            doc.text(78, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Dep/Intereses";
            doc.text(30, pos, transaccion);
            doc.text(55, pos, "");
            doc.text(78, pos, "" + pad(currency(monto)));
        }
        doc.text(103, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}


function impresion_recibo_dep_ret_cofai(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 49;
    var margenizquierdo = 18;
    while (i < 2) {
        doc.setFontSize(10);

        if (datos[27] == 'D') {
            doc.text(78, ini, datos[14]);//dia
            doc.text(96, ini, datos[13]);//fechas
            doc.text(110, ini, datos[12]);//fechas
            ini = ini + 10;
            doc.text(40, ini, datos[7]); // CLIENTE
            ini = ini + 25;
            doc.text(margenizquierdo + 12, ini, 'DEPOSITO DE APORTACIÓN A LA CUENTA ' + datos[2] + ' - ' + datos[16]);
            doc.text(152, 102, 'TOTAL:            Q. ' + datos[3]);
            doc.text(65, 111, datos[11]);
        }
        if (datos[27] == 'R') {
            doc.text(78, ini, datos[14]);//dia
            doc.text(96, ini, datos[13]);//fechas
            doc.text(110, ini, datos[12]);//fechas
            ini = ini + 10;
            doc.text(45, ini, 'COOPERATIVA INTEGRAL DE AHORRO Y CRÉDITO COFAI, R.L.'); // CLIENTE
            ini = ini + 25;
            doc.text(20, ini, 'RETIRO DE APORTACIÓN DE LA CUENTA ' + datos[2] + ' - ' + datos[16]);
            doc.text(145, 80, datos[3]);
            doc.text(152, 102, 'TOTAL:            Q. ' + datos[3]);
            doc.text(65, 112, datos[11]);
            doc.text(40, 130, datos[7]); // CLIENTE
            doc.text(40, 137, datos[15]); // DPI CLIENTE
        }

        ini = ini + 40;
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_cofai(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(7);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(30, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";

            doc.text(74, pos, transaccion);
            doc.text(138, pos, "" + pad(currency(monto)));
            doc.text(121, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";
            doc.text(74, pos, transaccion);
            doc.text(138, pos, "");
            doc.text(121, pos, "" + pad(currency(monto)));
        }
        doc.text(154, pos, "" + pad(currency(datos[1][i]['saldo'])));
        doc.text(178, pos, "" + datos[1][i]['codusu']);
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}


function impresion_certificado_kumooles(datos) {
    alert("Impresion de certificado de aportación");
    //configuraciones generales del tamaño del reporte
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    //configuraciones generales
    var doc = new jsPDF(opciones);
    doc.setFontSize(10);
    doc.text(88, 75, datos[1]);
    //ciclo for para recorrer a los beneficiarios
    var beneficiarios = "";
    var descripcion = "";
    const max_caracteres = 10;
    for (let i = 1; i < datos[0].length; i++) {
        beneficiarios = beneficiarios + datos[0][i]['nombre'];
        descripcion = descripcion + datos[0][i]['descripcion'];
        if (i !== ((datos[0].length) - 1)) {
            beneficiarios = beneficiarios + ", ";
            descripcion = descripcion + ", ";
        }
    }
    //se redimensiona, es como un multicel
    var splitTitle = doc.splitTextToSize(beneficiarios, 180);
    var splitDescription = doc.splitTextToSize(descripcion, 180);
    doc.text(125, 155, splitTitle);
    doc.text(105, 162, splitDescription);
    //la fecha se divide
    var array_fechasol = datos[2].split("-")
    var ano = array_fechasol[2];
    //se transforma el mes en letras
    var mes = array_fechasol[1];
    var mes_convertido = convertir_mes(mes);
    var dia = array_fechasol[0];
    //se muestra la fecha
    doc.text(80, 184, dia + '                       ' + mes_convertido + '                                 ' + ano);
    // //se muestra el codigo del certificado
    // doc.text(20, 50, datos[3]);
    //se muestra el monto en numeros
    doc.text(40, 85, datos[4]);

    //no. de codigo de cliente
    doc.text(36, 80, datos[15]);

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_kumooles(datos) {
    console.log(datos);
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 41;
    var margenizquierdo = 18;
    while (i < 2) {

        if (datos[27] == 'D') {

            doc.setFontSize(10);

            doc.text(margenizquierdo * 4 + 74, 42, datos[14]);
            doc.text(margenizquierdo * 4 + 84, 42, datos[13]);
            // doc.text(68, 37, datos[12]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(margenizquierdo * 4 + 97, 42, ultimosDosDigitos);

            doc.setFontSize(9);
            doc.text(margenizquierdo + 12, 36, datos[7]); //Cliente

            doc.setFontSize(8);
            // doc.text(margenizquierdo + 12, 57, 'Q.' + datos[3]); // Cantidad en letras
            doc.text(margenizquierdo * 4 + 60, 28, datos[2]); //CUENTA

            doc.setFontSize(10);
            doc.text(margenizquierdo * 4 + 82, 36, datos[25]); //codcliente
            doc.text(margenizquierdo + 12, 42, datos[30]); //Dirección


            if (datos[17] == 'APORTACION OBLIGATORIA') {
                doc.setFontSize(10);
                doc.text(68, 50, datos[3]);
                doc.text(68, 126, datos[3]); // TOTAL
            }
            if (datos[17] == 'Aportaciones adicionales') {
                doc.setFontSize(10);
                doc.text(68, 62, datos[3]);
                doc.text(68, 126, datos[3]); // TOTAL
            }
            if (datos[17] == 'CUOTA DE INGRESO') {
                doc.setFontSize(10);
                doc.text(68, 56, datos[3]);
                doc.text(68, 126, datos[3]); // TOTAL
            }

            // doc.setFontSize(10);
            // let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            // let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            // let saldoAnterior1 = saldoActual1 - deposito;
            // doc.text(135, 50 + 2, ' ' + saldoAnterior1); //SALDO ANTERIOR
            // doc.text(135, 57 + 2, ' ' + datos[3]); //ABONO
            // doc.text(135, 64 + 2, ' ' + datos[24]); //SALDO ACTUAL

            // doc.text(46, 107, datos[11]);
        }

        if (datos[27] == 'R') {

            doc.setFontSize(10);

            doc.text(margenizquierdo * 4 + 62, 41, datos[14]);
            doc.text(margenizquierdo * 4 + 72, 41, datos[13]);
            // doc.text(68, 40, datos[12]);
            let valor = datos[12].toString();
            let ultimosDosDigitos = valor.substring(valor.length - 2);
            doc.text(margenizquierdo * 4 + 84, 41, ultimosDosDigitos);

            doc.setFontSize(9);
            doc.text(margenizquierdo + 12, 49, datos[7]); //Cliente

            doc.setFontSize(10);
            // doc.text(margenizquierdo + 12, 57, 'Q.' + datos[3]); // Cantidad

            doc.text(margenizquierdo * 4 + 60, 49, datos[15]); // DPI CLIENTE

            doc.text(margenizquierdo + 14, 43, datos[2]); //CUENTA


            if (datos[17] == 'APORTACIÓN OBLIGATORIA') {
                doc.setFontSize(10);
                doc.text(83, 55, datos[3]);
                doc.text(83, 124, datos[3]); // TOTAL
            }
            if (datos[17] == 'Aportaciones adicionales') {
                doc.setFontSize(10);
                doc.text(83, 65, datos[3]);
                doc.text(83, 124, datos[3]); // TOTAL
            }

            doc.setFontSize(10);
            // let saldoActual1 = parseFloat(datos[24]); // SALDO ACTUAL
            // let deposito = parseFloat(datos[25]); // SALDO ANTERIOR
            // let saldoAnterior1 = saldoActual1 - deposito;
            // doc.text(46, 81 + 5, ' ' + saldoAnterior1); //SALDO ANTERIOR
            // doc.text(46, 88 + 5, ' ' + datos[3]); //ABONO
            // doc.text(46, 95 + 5, ' ' + datos[24]); //SALDO ACTUAL

            // doc.text(46, 108, datos[11]);
        }
        i++;
    }

    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_micasa(datos) {
    //console.log(datos);
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 60;
    var margenizquierdo = 50;

    while (i < 2) {
        doc.setFontSize(10);
        doc.text(margenizquierdo, ini, 'Cuenta de aportación No. ' + datos[2]);
        doc.text(margenizquierdo + 80, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 80, ini, 'Monto: Q ' + datos[3]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);

        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 6;
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 40;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_recibo_dep_ret_codelago(datos) {
    //console.log(datos);
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 36;
    var margenizquierdo = 50;

    while (i < 2) {
        doc.setFontSize(8);
        doc.text(margenizquierdo, ini, 'Cuenta de aportación No. ' + datos[2]);
        doc.text(margenizquierdo + 70, ini, 'Fecha doc: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);
        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + 70, ini, 'Monto: Q ' + datos[3]);
        ini = ini + 3.5;
        doc.text(margenizquierdo + 70, ini, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : ""));
        ini = ini + 3.5;
        doc.text(margenizquierdo + 70, ini, 'Total: Q.' + datos[18]);
        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        ini = ini + 3;
        doc.setFontSize(7);
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 30;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}

function impresion_libreta_codelago(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        cnumdoc = datos[1][i]['cnumdoc'];
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(25, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            var cont = parseInt(datos[1][i]['numlinea']);
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = cnumdoc; // retiro/debito

            doc.text(10, pos, String(cont));
            doc.text(55, pos, transaccion);
            doc.text(105, pos, "" + pad(currency(monto)));
            doc.text(77, pos, "");
        }
        if (tiptr == "D") {
            var cont = parseInt(datos[1][i]['numlinea']);
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = cnumdoc; // deposito/credito

            doc.text(10, pos, String(cont));
            doc.text(55, pos, transaccion);
            doc.text(105, pos, "");
            doc.text(77, pos, "" + pad(currency(monto)));
        }
        doc.text(135, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_kumooles(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    // console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini);
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(14, pos, "" + fecha);
        // doc.text(36, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            transaccion = "Retiro/Debito";

            doc.text(36, pos, transaccion);
            doc.text(72, pos, "" + pad(currency(monto)));
            doc.text(100, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            transaccion = "Deposito/Intereses";
            doc.text(36, pos, transaccion);
            doc.text(72, pos, "");
            doc.text(100, pos, "" + pad(currency(monto)));
        }
        doc.text(123, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_libreta_cope27(datos, resta, ini, posini, posfin, saldoo, numi, file) {
    console.log(datos);
    var inif = parseInt(datos[0][2]);
    var nfront = parseInt(datos[0][0]);
    var inid = parseInt(datos[0][3]);
    var ndors = parseInt(datos[0][1]);

    posac = 0;
    bandera = 0;
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [240, 300]
    };
    var doc = new jsPDF(opciones);
    doc.setFontSize(9);
    var i = posini;
    var tiptr;
    var saldo = saldoo;
    var monto = parseFloat(0);
    while (i < (datos[1].length)) {
        idagencia = datos[1][i]['id_agencia'];
        if( idagencia == null ){
            idagencia = "1";
        }

        doc.setFontSize(9);
        num = parseInt(datos[1][i]['numlinea']);
        pos = (6 * (parseInt(num) - parseInt(resta))) + parseInt(ini) - 8;
        // doc.text(10, pos, "" + datos[1][i]['correlativo']);
        fecha = conviertefecha(datos[1][i]['dfecope']);
        // console.log(fecha);
        doc.text(8, pos, "" + fecha);
        doc.text(28, pos, "" + idagencia);
        doc.text(45, pos, "" + datos[1][i]['cnumdoc']);
        monto = parseFloat(datos[1][i]['monto']);
        tiptr = datos[1][i]['ctipope'];
        detalle = datos[1][i]['crazon'];
        //*** */
        if (tiptr == "R") {
            saldo = parseFloat(saldo) - parseFloat(monto);
            // transaccion = "Retiro/Debito";

            doc.text(85, pos, "" + pad(currency(monto)));
            doc.text(112, pos, "");
        }
        if (tiptr == "D") {
            saldo = parseFloat(saldo) + parseFloat(monto);
            doc.text(85, pos, "");
            doc.text(60, pos, "" + pad(currency(monto)));
        }
        doc.text(120, pos, "" + pad(currency(datos[1][i]['saldo'])));
        if (num >= posfin) {
            posac = i + 1;
            bandera = (num >= (nfront + ndors)) ? 0 : 1;
            break;
        }
        i++;
    }

    if (numi > nfront) {
        Swal.fire({
            title: 'Entrando en la reversa de libreta',
            showDenyButton: false,
            confirmButtonText: 'Listo Imprimir',
        }).then((result) => {
            if (result.isConfirmed) {
                //
                doc.autoPrint();
                window.open(doc.output('bloburl'));

            } else if (result.isDenied) {
                Swal.fire('Uff', 'Cancelado', 'success')
            }
        })
    }
    else {
        doc.autoPrint();
        window.open(doc.output('bloburl'));
    }
    if (bandera == 1) {
        //SE EJECUTA RECURSIVAMENTE
        window[file](datos, nfront, inid, posac, nfront + ndors, saldo, nfront + 1, file);
    }
}

function impresion_recibo_dep_ret_cemadec(datos) {
    //console.log(datos);
    alert("Impresion de recibo *1 ");
    var opciones = {
        orientation: 'p',
        unit: 'mm',
        format: [216, 300]
    };
    var doc = new jsPDF(opciones);
    var i = 1;
    var ini = 33;
    var rec2 = 100
    var margenizquierdo = 15;

    while (i < 2) {
        doc.setFontSize(8);
        doc.text(margenizquierdo, ini, 'Cuenta de APRT No. ' + datos[2]);
        doc.text(margenizquierdo + rec2, ini, 'Cuenta de APRT No. ' + datos[2]);

        doc.text(margenizquierdo + 55, ini, 'Fecha: ' + datos[4]);
        doc.text(margenizquierdo + rec2 + 55, ini, 'Fecha: ' + datos[4]);
        // doc.text(180, ini, '' + datos[5]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Cliente: ' + datos[7]);
        doc.text(margenizquierdo + rec2, ini, 'Cliente: ' + datos[7]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'No. Docto: ' + datos[5]);
        doc.text(margenizquierdo + rec2, ini, 'No. Docto: ' + datos[5]);

        doc.text(margenizquierdo + 55, ini, 'Monto: Q ' + datos[3]);
        doc.text(margenizquierdo + rec2 + 55, ini, 'Monto: Q ' + datos[3]);
        ini = ini + 3.5;
        doc.text(margenizquierdo + 55, ini, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : ""));
        doc.text(margenizquierdo + rec2 + 55, ini, ((datos[16] > 0) ? "Cuota de ingreso: Q." + parseFloat(datos[16]).toFixed(2) : ""));
        ini = ini + 3.5;
        doc.text(margenizquierdo + 55, ini, 'Monto Total: Q ' + datos[18]);
        doc.text(margenizquierdo + rec2 + 55, ini, 'Monto Total: Q ' + datos[18]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operación: ' + datos[6]);
        doc.text(margenizquierdo + rec2, ini, 'Operación: ' + datos[6]);

        ini = ini + 3.5;
        doc.text(margenizquierdo, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);
        doc.text(margenizquierdo + rec2, ini, 'Operador: ' + datos[8] + ' ' + datos[9]);

        ini = ini + 3;
        doc.setFontSize(7);
        doc.text(margenizquierdo, ini, 'Fecha operación: ' + datos[10]);
        doc.text(margenizquierdo + rec2, ini, 'Fecha operación: ' + datos[10]);

        ini = ini + 30;
        i++;
    }
    doc.autoPrint();
    window.open(doc.output('bloburl'))
}